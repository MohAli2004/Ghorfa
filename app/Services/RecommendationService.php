<?php

namespace App\Services;

use App\Models\Property;
use App\Models\PropertyInteraction;
use App\Models\PropertySearch;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Hybrid property recommendation engine for Ghorfa capstone project.
 *
 * Final score formula (configurable in config/recommendation.php):
 *   final = collaborative * 0.40 + content * 0.25 + location * 0.25 + popularity * 0.10
 *
 * Components:
 * - Collaborative: users who interacted with the same properties as the current user
 * - Content: similarity to liked/contacted/viewed properties (type, city, price, amenities…)
 * - Location: Haversine distance from search coordinates or city match fallback
 * - Popularity: views_count + likes_count * 3 (normalized)
 *
 * Cold-start (no interactions): filter match, nearby, popular, newest listings.
 */
class RecommendationService
{
    public function __construct(
        protected PropertyInteractionService $interactionService,
        protected OpenAIEmbeddingService $embeddingService,
    ) {}

    /**
     * @return Collection<int, array{property: Property, scores: array<string, float>, final_score: float}>
     */
    public function getRecommendations(?User $user, array $context = [], int $limit = 12): Collection
    {
        $limit = $limit > 0 ? $limit : (int) config('recommendation.default_limit', 12);
        $cacheKey = $this->buildCacheKey($user, $context, $limit);

        return Cache::remember($cacheKey, (int) config('recommendation.cache_ttl', 900), function () use ($user, $context, $limit) {
            $candidates = $this->getCandidateProperties($context, $user);

            if ($candidates->isEmpty()) {
                return collect();
            }

            if (!$this->interactionService->userHasInteractions($user?->id)) {
                return $this->coldStartRecommendations($candidates, $context, $limit);
            }

            return $this->scoreCandidates($candidates, $user, $context)
                ->sortByDesc('final_score')
                ->take($limit)
                ->values();
        });
    }

    /**
     * Return ordered property IDs for SQL FIELD() ordering in search results.
     */
    public function getRecommendedPropertyIds(?User $user, array $context = [], int $limit = 200): array
    {
        return $this->getRecommendations($user, $context, $limit)
            ->pluck('property.id')
            ->all();
    }

    /**
     * Apply hybrid recommended sort to an existing approved-properties query.
     */
    public function applyRecommendedSort(Builder $query, ?User $user, array $context = []): Builder
    {
        $ids = $this->getRecommendedPropertyIds($user, $context);

        if (empty($ids)) {
            return $query->orderByDesc('created_at');
        }

        $idList = implode(',', array_map('intval', $ids));

        // Properties not in the recommendation list are pushed to the end.
        return $query
            ->orderByRaw('(FIELD(id, ' . $idList . ') = 0)')
            ->orderByRaw('FIELD(id, ' . $idList . ')');
    }

    protected function getCandidateProperties(array $context, ?User $user): Collection
    {
        $max = (int) config('recommendation.max_candidates', 200);

        $query = Property::query()
            ->where('status', 'approved')
            ->with(['images', 'amenities', 'embedding']);

        if ($user) {
            $query->where('user_id', '!=', $user->id);
        }

        $this->applyContextFilters($query, $context);

        $excludeIds = $this->getExcludedPropertyIds($user);
        if (!empty($excludeIds)) {
            $query->whereNotIn('id', $excludeIds);
        }

        return $query->limit($max)->get();
    }

    protected function applyContextFilters(Builder $query, array $context): void
    {
        if (!empty($context['location'])) {
            $location = $context['location'];
            $query->where(function ($q) use ($location) {
                $q->where('country', 'like', "%{$location}%")
                    ->orWhere('city', 'like', "%{$location}%")
                    ->orWhere('address', 'like', "%{$location}%")
                    ->orWhere('title', 'like', "%{$location}%");
            });
        }

        if (!empty($context['min-price'])) {
            $query->where('price', '>=', $context['min-price']);
        }

        if (!empty($context['max-price'])) {
            $query->where('price', '<=', $context['max-price']);
        }

        if (!empty($context['property_type'])) {
            $query->whereIn('property_type', (array) $context['property_type']);
        }

        if (!empty($context['listing_type'])) {
            $query->whereIn('listing_type', (array) $context['listing_type']);
        }
    }

    /**
     * Exclude properties the user already contacted or strongly engaged with.
     */
    protected function getExcludedPropertyIds(?User $user): array
    {
        if (!$user) {
            return [];
        }

        return PropertyInteraction::query()
            ->where('user_id', $user->id)
            ->whereIn('action', ['contact', 'like'])
            ->pluck('property_id')
            ->unique()
            ->all();
    }

    protected function scoreCandidates(Collection $candidates, User $user, array $context): Collection
    {
        $userWeights = $this->interactionService->getUserPropertyWeights($user->id);
        $preferenceProfile = $this->buildContentPreferenceProfile($user->id, $userWeights);
        $neighborScores = $this->buildCollaborativeNeighborScores($user->id, $userWeights);
        $maxPopularity = max(1.0, $candidates->max(fn (Property $p) => $this->rawPopularity($p)));
        $userId = $user->id;

        return $candidates->map(function (Property $property) use ($userId, $userWeights, $preferenceProfile, $neighborScores, $context, $maxPopularity) {
            $collaborative = $this->normalizeCollaborativeScore($property->id, $neighborScores);
            $content = $this->contentScore($property, $preferenceProfile, $userId, $userWeights);
            $semantic = $this->semanticEmbeddingScore($property, $userWeights);
            $location = $this->locationScore($property, $context, $userId);
            $popularity = $this->popularityScore($property, $maxPopularity);

            $weights = config('recommendation.score_weights');
            $final = ($collaborative * $weights['collaborative'])
                + ($content * $weights['content'])
                + ($location * $weights['location'])
                + ($popularity * $weights['popularity']);

            return [
                'property' => $property,
                'scores' => [
                    'collaborative' => round($collaborative, 4),
                    'content' => round($content, 4),
                    'semantic' => $semantic !== null ? round($semantic, 4) : null,
                    'location' => round($location, 4),
                    'popularity' => round($popularity, 4),
                ],
                'final_score' => round($final, 4),
            ];
        });
    }

    /**
     * Cold-start: new users or users without interaction history.
     */
    protected function coldStartRecommendations(Collection $candidates, array $context, int $limit): Collection
    {
        $maxPopularity = max(1.0, $candidates->max(fn (Property $p) => $this->rawPopularity($p)));

        return $candidates->map(function (Property $property) use ($context, $maxPopularity) {
            $filterScore = $this->coldStartFilterScore($property, $context);
            $location = $this->locationScore($property, $context, null);
            $popularity = $this->popularityScore($property, $maxPopularity);
            $recency = $this->recencyScore($property);

            // Cold-start blend prioritizes filters, proximity, popularity, and freshness.
            $final = ($filterScore * 0.35) + ($location * 0.30) + ($popularity * 0.20) + ($recency * 0.15);

            return [
                'property' => $property,
                'scores' => [
                    'collaborative' => 0.0,
                    'content' => round($filterScore, 4),
                    'location' => round($location, 4),
                    'popularity' => round($popularity, 4),
                    'recency' => round($recency, 4),
                ],
                'final_score' => round($final, 4),
            ];
        })
            ->sortByDesc('final_score')
            ->take($limit)
            ->values();
    }

    /**
     * Collaborative filtering: find users with overlapping interactions, score overlap.
     */
    protected function buildCollaborativeNeighborScores(int $userId, array $userWeights): array
    {
        if (empty($userWeights)) {
            return [];
        }

        $propertyIds = array_keys($userWeights);

        $neighborRows = PropertyInteraction::query()
            ->whereIn('property_id', $propertyIds)
            ->where('user_id', '!=', $userId)
            ->where('weight', '>', 0)
            ->select('user_id', 'property_id', DB::raw('SUM(weight) as total_weight'))
            ->groupBy('user_id', 'property_id')
            ->get();

        $neighbors = [];
        foreach ($neighborRows as $row) {
            $neighbors[$row->user_id][$row->property_id] = (float) $row->total_weight;
        }

        $userNorm = sqrt(array_sum(array_map(fn ($w) => $w * $w, $userWeights)));
        $propertyScores = [];

        foreach ($neighbors as $neighborId => $neighborWeights) {
            $overlap = 0.0;
            foreach ($userWeights as $propertyId => $weight) {
                if (isset($neighborWeights[$propertyId])) {
                    $overlap += min($weight, $neighborWeights[$propertyId]);
                }
            }

            if ($overlap < config('recommendation.min_neighbor_overlap', 1)) {
                continue;
            }

            $neighborNorm = sqrt(array_sum(array_map(fn ($w) => $w * $w, $neighborWeights)));
            $similarity = $overlap / max($userNorm * $neighborNorm, 0.0001);

            foreach ($neighborWeights as $propertyId => $weight) {
                if (isset($userWeights[$propertyId])) {
                    continue;
                }

                $propertyScores[$propertyId] = ($propertyScores[$propertyId] ?? 0) + ($similarity * $weight);
            }
        }

        arsort($propertyScores);

        return array_slice($propertyScores, 0, (int) config('recommendation.max_neighbors', 50), true);
    }

    protected function normalizeCollaborativeScore(int $propertyId, array $neighborScores): float
    {
        if (empty($neighborScores)) {
            return 0.0;
        }

        $score = $neighborScores[$propertyId] ?? 0.0;
        $max = max($neighborScores);

        return $max > 0 ? min(1.0, $score / $max) : 0.0;
    }

    /**
     * Build a weighted preference profile from the user's positive interactions.
     */
    protected function buildContentPreferenceProfile(int $userId, array $userWeights): array
    {
        if (empty($userWeights)) {
            return [];
        }

        $properties = Property::query()
            ->whereIn('id', array_keys($userWeights))
            ->with('amenities')
            ->get()
            ->keyBy('id');

        $profile = [
            'listing_types' => [],
            'property_types' => [],
            'cities' => [],
            'prices' => [],
            'bedrooms' => [],
            'bathrooms' => [],
            'areas' => [],
            'amenity_ids' => [],
        ];

        foreach ($userWeights as $propertyId => $weight) {
            $property = $properties->get($propertyId);
            if (!$property) {
                continue;
            }

            $profile['listing_types'][$property->listing_type] = ($profile['listing_types'][$property->listing_type] ?? 0) + $weight;
            $profile['property_types'][$property->property_type] = ($profile['property_types'][$property->property_type] ?? 0) + $weight;
            $profile['cities'][$property->city] = ($profile['cities'][$property->city] ?? 0) + $weight;
            $profile['prices'][] = ['value' => (float) $property->price, 'weight' => $weight];

            if ($property->bedroom_nb) {
                $profile['bedrooms'][] = ['value' => (int) $property->bedroom_nb, 'weight' => $weight];
            }
            if ($property->bathroom_nb) {
                $profile['bathrooms'][] = ['value' => (int) $property->bathroom_nb, 'weight' => $weight];
            }
            if ($property->area_m3) {
                $profile['areas'][] = ['value' => (float) $property->area_m3, 'weight' => $weight];
            }

            foreach ($property->amenities as $amenity) {
                $profile['amenity_ids'][$amenity->id] = ($profile['amenity_ids'][$amenity->id] ?? 0) + $weight;
            }
        }

        return $profile;
    }

    protected function contentScore(Property $property, array $profile, ?int $userId = null, array $userWeights = []): float
    {
        if (empty($profile)) {
            return 0.0;
        }

        $scores = [];

        $scores[] = $this->weightedCategoryMatch($property->listing_type, $profile['listing_types'] ?? []);
        $scores[] = $this->weightedCategoryMatch($property->property_type, $profile['property_types'] ?? []);
        $scores[] = $this->weightedCategoryMatch($property->city, $profile['cities'] ?? []);
        $scores[] = $this->weightedNumericSimilarity((float) $property->price, $profile['prices'] ?? []);
        $scores[] = $this->weightedNumericSimilarity($property->bedroom_nb, $profile['bedrooms'] ?? [], 3);
        $scores[] = $this->weightedNumericSimilarity($property->bathroom_nb, $profile['bathrooms'] ?? [], 2);
        $scores[] = $this->weightedNumericSimilarity($property->area_m3, $profile['areas'] ?? [], 50);
        $scores[] = $this->amenitySimilarity($property, $profile['amenity_ids'] ?? []);

        $attributeScore = array_sum($scores) / count($scores);

        // Optional OpenAI semantic similarity blended into the content component.
        if ($userId && config('recommendation.use_openai_embeddings', true)) {
            $semantic = $this->semanticEmbeddingScore($property, $userWeights);
            if ($semantic !== null) {
                $blend = (float) config('recommendation.semantic_blend_weight', 0.30);

                return ($attributeScore * (1 - $blend)) + ($semantic * $blend);
            }
        }

        return $attributeScore;
    }

    /**
     * Cosine similarity between the user's preference vector and a candidate property embedding.
     * Returns null when embeddings are unavailable (falls back to attribute-only content score).
     */
    protected function semanticEmbeddingScore(Property $property, array $userWeights): ?float
    {
        if (!$this->embeddingService->isConfigured() || empty($userWeights)) {
            return null;
        }

        $property->loadMissing('embedding');
        $candidateVector = $property->embedding?->embedding;

        if (!$candidateVector) {
            return null;
        }

        $userVector = $this->buildUserPreferenceVector($userWeights);

        if (!$userVector) {
            return null;
        }

        $similarity = $this->embeddingService->cosineSimilarity($userVector, $candidateVector);

        return max(0.0, min(1.0, $similarity));
    }

    /**
     * Weighted average embedding vector from properties the user interacted with.
     */
    protected function buildUserPreferenceVector(array $userWeights): ?array
    {
        $properties = Property::query()
            ->with('embedding')
            ->whereIn('id', array_keys($userWeights))
            ->get();

        $accumulator = null;
        $totalWeight = 0.0;

        foreach ($properties as $property) {
            $vector = $property->embedding?->embedding;
            $weight = (float) ($userWeights[$property->id] ?? 0);

            if (!$vector || $weight <= 0) {
                continue;
            }

            if ($accumulator === null) {
                $accumulator = array_fill(0, count($vector), 0.0);
            }

            for ($i = 0; $i < count($vector); $i++) {
                $accumulator[$i] += $vector[$i] * $weight;
            }

            $totalWeight += $weight;
        }

        if ($accumulator === null || $totalWeight <= 0) {
            return null;
        }

        return array_map(fn (float $value) => $value / $totalWeight, $accumulator);
    }

    protected function weightedCategoryMatch(?string $value, array $weightedCategories): float
    {
        if (!$value || empty($weightedCategories)) {
            return 0.0;
        }

        $total = array_sum($weightedCategories);

        return ($weightedCategories[$value] ?? 0) / max($total, 0.0001);
    }

    protected function weightedNumericSimilarity($value, array $weightedValues, float $tolerance = 0): float
    {
        if ($value === null || empty($weightedValues)) {
            return 0.5;
        }

        $weightedSum = 0.0;
        $weightTotal = 0.0;

        foreach ($weightedValues as $entry) {
            $diff = abs((float) $value - (float) $entry['value']);
            $range = max((float) $entry['value'], $tolerance, 1);
            $similarity = max(0.0, 1 - ($diff / $range));
            $weightedSum += $similarity * $entry['weight'];
            $weightTotal += $entry['weight'];
        }

        return $weightTotal > 0 ? $weightedSum / $weightTotal : 0.5;
    }

    protected function amenitySimilarity(Property $property, array $weightedAmenityIds): float
    {
        if (empty($weightedAmenityIds)) {
            return 0.5;
        }

        $propertyAmenityIds = $property->amenities->pluck('id')->all();
        if (empty($propertyAmenityIds)) {
            return 0.0;
        }

        $intersectionWeight = 0.0;
        foreach ($propertyAmenityIds as $amenityId) {
            $intersectionWeight += $weightedAmenityIds[$amenityId] ?? 0;
        }

        $unionWeight = array_sum($weightedAmenityIds);
        foreach ($propertyAmenityIds as $amenityId) {
            if (!isset($weightedAmenityIds[$amenityId])) {
                $unionWeight += 1;
            }
        }

        return $unionWeight > 0 ? $intersectionWeight / $unionWeight : 0.0;
    }

  /**
     * Location score using Haversine distance when coordinates are available.
     */
    protected function locationScore(Property $property, array $context, ?int $userId): float
    {
        [$lat, $lng] = $this->resolveSearchCoordinates($context, $userId);

        if ($lat !== null && $lng !== null && $property->latitude && $property->longitude) {
            $distanceKm = $this->haversineDistanceKm(
                $lat,
                $lng,
                (float) $property->latitude,
                (float) $property->longitude
            );

            return $this->distanceToScore($distanceKm);
        }

        return $this->cityFallbackScore($property, $context, $userId);
    }

    protected function resolveSearchCoordinates(array $context, ?int $userId): array
    {
        if (!empty($context['latitude']) && !empty($context['longitude'])) {
            return [(float) $context['latitude'], (float) $context['longitude']];
        }

        if ($userId) {
            $latestSearch = PropertySearch::query()
                ->where('user_id', $userId)
                ->whereNotNull('latitude')
                ->whereNotNull('longitude')
                ->latest()
                ->first();

            if ($latestSearch) {
                return [(float) $latestSearch->latitude, (float) $latestSearch->longitude];
            }
        }

        return [null, null];
    }

    protected function cityFallbackScore(Property $property, array $context, ?int $userId): float
    {
        $locationQuery = $context['location'] ?? null;

        if (!$locationQuery && $userId) {
            $locationQuery = PropertySearch::query()
                ->where('user_id', $userId)
                ->whereNotNull('location_query')
                ->latest()
                ->value('location_query');
        }

        if (!$locationQuery) {
            return 0.5;
        }

        $needle = mb_strtolower($locationQuery);

        if ($property->city && str_contains($needle, mb_strtolower($property->city))) {
            return 1.0;
        }

        if ($property->city && str_contains(mb_strtolower($property->city), $needle)) {
            return 0.9;
        }

        if ($property->address && str_contains(mb_strtolower($property->address), $needle)) {
            return 0.75;
        }

        if ($property->country && str_contains($needle, mb_strtolower($property->country))) {
            return 0.4;
        }

        return 0.2;
    }

    protected function haversineDistanceKm(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earthRadius = 6371;
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2;

        return $earthRadius * (2 * atan2(sqrt($a), sqrt(1 - $a)));
    }

    protected function distanceToScore(float $distanceKm): float
    {
        $buckets = config('recommendation.distance_buckets', []);

        foreach ($buckets as $maxKm => $score) {
            if ($distanceKm <= $maxKm) {
                return (float) $score;
            }
        }

        return (float) config('recommendation.distance_beyond_km_score', 0.0);
    }

    protected function rawPopularity(Property $property): float
    {
        $multiplier = (int) config('recommendation.popularity_likes_multiplier', 3);

        return (float) ($property->views_count + ($property->likes_count * $multiplier));
    }

    protected function popularityScore(Property $property, float $maxPopularity): float
    {
        return min(1.0, $this->rawPopularity($property) / max($maxPopularity, 1.0));
    }

    protected function recencyScore(Property $property): float
    {
        $days = $property->created_at?->diffInDays(now()) ?? 365;

        return max(0.1, 1 - min($days / 365, 0.9));
    }

    protected function coldStartFilterScore(Property $property, array $context): float
    {
        if (empty($context)) {
            return 0.5;
        }

        $points = 0;
        $checks = 0;

        if (!empty($context['listing_type'])) {
            $checks++;
            if (in_array($property->listing_type, (array) $context['listing_type'], true)) {
                $points++;
            }
        }

        if (!empty($context['property_type'])) {
            $checks++;
            if (in_array($property->property_type, (array) $context['property_type'], true)) {
                $points++;
            }
        }

        if (!empty($context['min-price']) || !empty($context['max-price'])) {
            $checks++;
            $min = (float) ($context['min-price'] ?? 0);
            $max = (float) ($context['max-price'] ?? PHP_FLOAT_MAX);
            if ($property->price >= $min && $property->price <= $max) {
                $points++;
            }
        }

        if (!empty($context['location'])) {
            $checks++;
            $location = mb_strtolower((string) $context['location']);
            if (
                str_contains($location, mb_strtolower((string) $property->city))
                || str_contains(mb_strtolower((string) $property->city), $location)
            ) {
                $points++;
            }
        }

        return $checks > 0 ? $points / $checks : 0.5;
    }

    protected function buildCacheKey(?User $user, array $context, int $limit): string
    {
        $userPart = $user ? 'user:' . $user->id : 'guest';
        $contextHash = md5(json_encode($context));

        return "ghorfa.recommendations.{$userPart}.{$contextHash}.{$limit}";
    }

    /**
     * Build recommendation context array from an HTTP search request.
     */
    public function contextFromRequest(Request $request): array
    {
        return array_filter([
            'location' => $request->input('location'),
            'min-price' => $request->input('min-price'),
            'max-price' => $request->input('max-price'),
            'property_type' => $request->input('property_type'),
            'listing_type' => $request->input('listing_type'),
            'latitude' => $request->input('latitude'),
            'longitude' => $request->input('longitude'),
        ], fn ($value) => $value !== null && $value !== '');
    }
}
