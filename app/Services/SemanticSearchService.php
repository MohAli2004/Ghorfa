<?php

namespace App\Services;

use App\Models\Property;
use App\Models\PropertyEmbedding;
use App\Support\FuzzyTextMatcher;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * AI-powered semantic search for Ghorfa.
 *
 * Pipeline:
 * 1. Expand query across Arabic ↔ English variants (بعلبك → baalbek, baalbak…).
 * 2. Keyword-score properties on title, description, address, city (prioritized).
 * 3. Embed enriched query + rank by cosine similarity on stored property vectors.
 * 4. Final order: relevance first, then nearest → farthest from searched location.
 */
class SemanticSearchService
{
    /** @var array<int, float> Property ID => display score for the current request */
    protected array $lastScores = [];

    /** @var list<string> */
    protected array $lastVariants = [];

    public function __construct(
        protected OpenAIEmbeddingService $embeddingService,
        protected SearchQueryLocalizationService $localizationService,
        protected GeocodingService $geocodingService,
    ) {}

    public function isAvailable(): bool
    {
        return config('semantic_search.enabled', true)
            && $this->embeddingService->isConfigured();
    }

    public function extractQueryFromRequest(Request $request): ?string
    {
        $query = trim((string) $request->input('q', ''));

        if ($query !== '') {
            return $query;
        }

        $location = trim((string) $request->input('location', ''));

        return $location !== '' ? $location : null;
    }

    public function shouldUseSemanticSearch(Request $request): bool
    {
        if (!$this->isAvailable() || !$this->extractQueryFromRequest($request)) {
            return false;
        }

        $sort = $request->input('sort', 'recommended');

        if ($sort === 'semantic') {
            return true;
        }

        return config('semantic_search.auto_when_query', true)
            && ($sort === 'recommended' || $sort === null || $sort === '');
    }

    /**
     * @return array<int, float>
     */
    public function getLastScores(): array
    {
        return $this->lastScores;
    }

    /**
     * @return list<string>
     */
    public function getLastVariants(): array
    {
        return $this->lastVariants;
    }

    /**
     * @return array{query: Builder, scores: array<int, float>, active: bool, variants: list<string>}
     */
    public function rankProperties(Builder $query, string $searchText): array
    {
        $this->lastScores = [];
        $this->lastVariants = [];

        $searchText = trim($searchText);

        if ($searchText === '') {
            return ['query' => $query, 'scores' => [], 'active' => false, 'variants' => []];
        }

        $variants = $this->localizationService->expand($searchText);
        $this->lastVariants = $variants;

        $searchCoords = $this->resolveSearchCoordinates($searchText, $variants);

        $embeddingInput = $this->localizationService->embeddingText($searchText, $variants);
        $queryVector = $this->embedQuery($embeddingInput, $searchText);

        $maxCandidates = (int) config('semantic_search.max_candidates', 500);
        $properties = (clone $query)
            ->with(['embedding', 'amenities'])
            ->limit($maxCandidates)
            ->get();

        if ($properties->isEmpty()) {
            return ['query' => $query, 'scores' => [], 'active' => false, 'variants' => $variants];
        }

        $minSimilarity = (float) config('semantic_search.min_similarity', 0.25);
        $keywordWeight = (float) config('semantic_search.keyword_weight', 0.60);
        $semanticWeight = (float) config('semantic_search.semantic_weight', 0.40);

        $ranked = [];

        foreach ($properties as $property) {
            $keywordScore = $this->keywordScore($property, $variants, $searchText);
            $semanticScore = 0.0;

            if ($queryVector && $property->embedding?->embedding) {
                $semanticScore = $this->embeddingService->cosineSimilarity(
                    $queryVector,
                    $property->embedding->embedding
                );
            }

            // Keep properties with a strong bilingual keyword hit OR good semantic similarity.
            if ($keywordScore <= 0 && $semanticScore < $minSimilarity) {
                continue;
            }

            $finalScore = ($keywordScore * $keywordWeight) + ($semanticScore * $semanticWeight);

            // Keyword matches (e.g. بعلبك ↔ baalbek in city/title) are ranked above semantic-only hits.
            if ($keywordScore > 0) {
                $finalScore += 0.35;
            }

            $ranked[$property->id] = [
                'keyword' => $keywordScore,
                'semantic' => $semanticScore,
                'final' => min(1.0, $finalScore),
                'distance_km' => $this->propertyDistanceKm($property, $searchCoords),
                'tier' => 1,
            ];
        }

        if (empty($ranked) && config('semantic_search.fuzzy_match_enabled', true)) {
            $ranked = $this->rankWithRelaxedThreshold(
                $properties,
                $variants,
                $searchText,
                $queryVector,
                $keywordWeight,
                $semanticWeight,
                $searchCoords
            );
        }

        if (config('semantic_search.include_nearby_properties', true) && $searchCoords) {
            $ranked = $this->appendNearbyProperties(
                $ranked,
                $properties,
                $searchCoords,
                (float) config('semantic_search.nearby_radius_km', 35)
            );
        }

        $ranked = $this->sortRankedResults($ranked);

        if (empty($ranked)) {
            return ['query' => $query, 'scores' => [], 'active' => false, 'variants' => $variants];
        }

        $this->lastScores = array_map(fn (array $row) => $row['final'], $ranked);

        $orderedIds = array_keys($ranked);
        $idList = implode(',', array_map('intval', $orderedIds));

        $orderedQuery = (clone $query)
            ->whereIn('properties.id', $orderedIds)
            ->orderByRaw('(FIELD(properties.id, ' . $idList . ') = 0)')
            ->orderByRaw('FIELD(properties.id, ' . $idList . ')');

        return [
            'query' => $orderedQuery,
            'scores' => $this->lastScores,
            'active' => true,
            'variants' => $variants,
        ];
    }

    /**
     * Score how well property text matches any Arabic/English variant (exact + fuzzy).
     */
    protected function keywordScore(Property $property, array $variants, string $originalQuery = ''): float
    {
        $fields = [
            'city' => 1.0,
            'title' => 0.95,
            'address' => 0.9,
            'description' => 0.8,
            'country' => 0.7,
        ];

        $needles = $variants;
        if ($originalQuery !== '' && !in_array($originalQuery, $needles, true)) {
            $needles[] = $originalQuery;
        }

        $fuzzyEnabled = config('semantic_search.fuzzy_match_enabled', true);
        $fuzzyMin = (float) config('semantic_search.fuzzy_min_similarity', 0.72);
        $best = 0.0;

        foreach ($needles as $variant) {
            $needle = mb_strtolower(trim($variant));
            if ($needle === '' || mb_strlen($needle) < 2) {
                continue;
            }

            foreach ($fields as $field => $weight) {
                $value = (string) ($property->{$field} ?? '');
                if ($value === '') {
                    continue;
                }

                if (mb_stripos($value, $needle) !== false) {
                    $best = max($best, $weight);
                    continue;
                }

                if ($fuzzyEnabled) {
                    $similarity = FuzzyTextMatcher::bestSimilarity($value, $needle);
                    if ($similarity >= $fuzzyMin) {
                        $best = max($best, $weight * $similarity);
                    }
                }
            }

            foreach ($property->amenities as $amenity) {
                $name = (string) $amenity->name;
                if ($name === '') {
                    continue;
                }

                if (mb_stripos($name, $needle) !== false) {
                    $best = max($best, 0.65);
                    continue;
                }

                if ($fuzzyEnabled) {
                    $similarity = FuzzyTextMatcher::bestSimilarity($name, $needle);
                    if ($similarity >= $fuzzyMin) {
                        $best = max($best, 0.65 * $similarity);
                    }
                }
            }
        }

        return $best;
    }

    /**
     * Second pass with lower thresholds when strict matching returns nothing.
     *
     * @param  \Illuminate\Support\Collection<int, Property>  $properties
     * @return array<int, array{keyword: float, semantic: float, final: float}>
     */
    protected function rankWithRelaxedThreshold(
        $properties,
        array $variants,
        string $searchText,
        ?array $queryVector,
        float $keywordWeight,
        float $semanticWeight,
        ?array $searchCoords = null,
    ): array {
        $relaxedSemantic = max(0.15, (float) config('semantic_search.min_similarity', 0.25) - 0.08);
        $relaxedFuzzy = max(0.62, (float) config('semantic_search.fuzzy_min_similarity', 0.72) - 0.10);
        $ranked = [];

        foreach ($properties as $property) {
            $keywordScore = $this->keywordScoreRelaxed($property, $variants, $searchText, $relaxedFuzzy);
            $semanticScore = 0.0;

            if ($queryVector && $property->embedding?->embedding) {
                $semanticScore = $this->embeddingService->cosineSimilarity(
                    $queryVector,
                    $property->embedding->embedding
                );
            }

            if ($keywordScore <= 0 && $semanticScore < $relaxedSemantic) {
                continue;
            }

            $finalScore = ($keywordScore * $keywordWeight) + ($semanticScore * $semanticWeight);
            if ($keywordScore > 0) {
                $finalScore += 0.25;
            }

            $ranked[$property->id] = [
                'keyword' => $keywordScore,
                'semantic' => $semanticScore,
                'final' => min(1.0, $finalScore),
                'distance_km' => $this->propertyDistanceKm($property, $searchCoords),
                'tier' => 1,
            ];
        }

        return $ranked;
    }

    protected function keywordScoreRelaxed(
        Property $property,
        array $variants,
        string $originalQuery,
        float $fuzzyMin,
    ): float {
        $fields = [
            'city' => 1.0,
            'title' => 0.95,
            'address' => 0.9,
            'description' => 0.8,
            'country' => 0.7,
        ];

        $needles = array_merge($variants, [$originalQuery]);
        $best = 0.0;

        foreach ($needles as $needle) {
            $needle = trim((string) $needle);
            if ($needle === '' || mb_strlen($needle) < 2) {
                continue;
            }

            foreach ($fields as $field => $weight) {
                $value = (string) ($property->{$field} ?? '');
                if ($value === '') {
                    continue;
                }

                $similarity = FuzzyTextMatcher::bestSimilarity($value, $needle);
                if ($similarity >= $fuzzyMin) {
                    $best = max($best, $weight * $similarity);
                }
            }
        }

        return $best;
    }

    public function embedQuery(string $embeddingInput, string $cacheKeySource): ?array
    {
        $embeddingInput = trim($embeddingInput);
        $cacheKeySource = trim($cacheKeySource);

        if ($embeddingInput === '') {
            return null;
        }

        $cacheKey = 'semantic_search.query.' . md5(mb_strtolower($cacheKeySource));
        $ttl = (int) config('semantic_search.query_cache_ttl', 3600);

        return Cache::remember($cacheKey, $ttl, fn () => $this->embeddingService->embedText($embeddingInput));
    }

    /**
     * @param  list<string>  $variants
     * @return array{lat: float, lng: float}|null
     */
    protected function resolveSearchCoordinates(string $searchText, array $variants): ?array
    {
        if (!config('semantic_search.sort_by_distance', true)) {
            return null;
        }

        $cacheKey = 'semantic_search.geocode.v1.' . md5(mb_strtolower($searchText));
        $ttl = (int) config('semantic_search.query_cache_ttl', 3600);

        return Cache::remember($cacheKey, $ttl, function () use ($searchText, $variants) {
            $attempts = [];

            foreach ($variants as $variant) {
                $variant = trim($variant);
                if ($variant === '') {
                    continue;
                }

                if (preg_match('/[a-z]/i', $variant)) {
                    $attempts[] = $variant . ', Lebanon';
                    $attempts[] = $variant;
                } else {
                    $attempts[] = $variant . ', Lebanon';
                    $attempts[] = $variant;
                }
            }

            $attempts[] = $searchText . ', Lebanon';
            $attempts[] = $searchText;

            foreach (array_unique($attempts) as $address) {
                $result = $this->geocodingService->geocode($address);
                if ($result && isset($result['latitude'], $result['longitude'])) {
                    return [
                        'lat' => (float) $result['latitude'],
                        'lng' => (float) $result['longitude'],
                    ];
                }
            }

            return null;
        });
    }

    protected function propertyDistanceKm(Property $property, ?array $searchCoords): ?float
    {
        if (!$searchCoords || !$property->latitude || !$property->longitude) {
            return null;
        }

        return $this->haversineDistanceKm(
            $searchCoords['lat'],
            $searchCoords['lng'],
            (float) $property->latitude,
            (float) $property->longitude
        );
    }

    /**
     * Add properties near the searched location that were not strong text matches.
     *
     * @param  array<int, array{keyword: float, semantic: float, final: float, distance_km?: float|null, tier?: int}>  $ranked
     * @param  \Illuminate\Support\Collection<int, Property>  $properties
     * @return array<int, array{keyword: float, semantic: float, final: float, distance_km?: float|null, tier?: int}>
     */
    protected function appendNearbyProperties(
        array $ranked,
        $properties,
        array $searchCoords,
        float $maxRadiusKm,
    ): array {
        foreach ($properties as $property) {
            if (isset($ranked[$property->id])) {
                continue;
            }

            $distance = $this->propertyDistanceKm($property, $searchCoords);
            if ($distance === null || $distance > $maxRadiusKm) {
                continue;
            }

            $ranked[$property->id] = [
                'keyword' => 0.0,
                'semantic' => 0.0,
                'final' => 0.0,
                'distance_km' => $distance,
                'tier' => 2,
            ];
        }

        return $ranked;
    }

    /**
     * Tier 1 = relevance matches. Tier 2 = nearby only. Within each tier: nearest first.
     *
     * @param  array<int, array{keyword: float, semantic: float, final: float, distance_km?: float|null, tier?: int}>  $ranked
     * @return array<int, array{keyword: float, semantic: float, final: float, distance_km?: float|null, tier?: int}>
     */
    protected function sortRankedResults(array $ranked): array
    {
        uasort($ranked, function (array $a, array $b) {
            $tierA = (int) ($a['tier'] ?? 1);
            $tierB = (int) ($b['tier'] ?? 1);

            if ($tierA !== $tierB) {
                return $tierA <=> $tierB;
            }

            if ($tierA === 1) {
                if ($a['keyword'] !== $b['keyword']) {
                    return $b['keyword'] <=> $a['keyword'];
                }

                if ($a['final'] !== $b['final']) {
                    return $b['final'] <=> $a['final'];
                }
            }

            if (config('semantic_search.sort_by_distance', true)) {
                $distA = $a['distance_km'] ?? PHP_FLOAT_MAX;
                $distB = $b['distance_km'] ?? PHP_FLOAT_MAX;

                if ($distA !== $distB) {
                    return $distA <=> $distB;
                }
            }

            return $b['final'] <=> $a['final'];
        });

        return $ranked;
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
}
