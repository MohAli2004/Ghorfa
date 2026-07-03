<?php

namespace App\Services;

use App\Models\Property;
use App\Models\PropertyInteraction;
use App\Models\PropertySearch;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Records user–property interactions for the hybrid recommendation engine.
 * Also keeps denormalized views_count / likes_count on properties in sync.
 */
class PropertyInteractionService
{
    public function weightForAction(string $action): int
    {
        return (int) config("recommendation.action_weights.{$action}", 0);
    }

    /**
     * Record a weighted interaction row and update counters when needed.
     */
    public function record(int $userId, int $propertyId, string $action, array $metadata = []): ?PropertyInteraction
    {
        $weight = $this->weightForAction($action);

        if ($weight === 0 && $action !== 'unlike') {
            return null;
        }

        if ($action === 'view' && $this->shouldSkipDuplicateView($userId, $propertyId)) {
            return null;
        }

        $interaction = PropertyInteraction::create([
            'user_id' => $userId,
            'property_id' => $propertyId,
            'action' => $action,
            'weight' => $weight,
            'metadata' => $metadata ?: null,
        ]);

        $this->syncPropertyCounters($propertyId, $action);

        return $interaction;
    }

    public function recordView(int $userId, Property $property, array $metadata = []): ?PropertyInteraction
    {
        return $this->record($userId, $property->id, 'view', $metadata);
    }

    public function recordClick(int $userId, Property $property, array $metadata = []): ?PropertyInteraction
    {
        return $this->record($userId, $property->id, 'click', $metadata);
    }

    public function recordContact(int $userId, Property $property, array $metadata = []): ?PropertyInteraction
    {
        return $this->record($userId, $property->id, 'contact', $metadata);
    }

    public function recordLike(int $userId, Property $property, array $metadata = []): ?PropertyInteraction
    {
        return $this->record($userId, $property->id, 'like', $metadata);
    }

    public function recordUnlike(int $userId, Property $property, array $metadata = []): ?PropertyInteraction
    {
        return $this->record($userId, $property->id, 'unlike', $metadata);
    }

    public function recordSearchView(int $userId, Property $property, array $metadata = []): ?PropertyInteraction
    {
        return $this->record($userId, $property->id, 'search_view', $metadata);
    }

    /**
     * Persist search filters so content/location scoring can use recent intent.
     */
    public function recordSearch(Request $request, int $resultsCount): PropertySearch
    {
        $filters = $this->extractSearchFilters($request);

        return PropertySearch::create([
            'user_id' => $request->user()?->id,
            'session_id' => $request->session()->getId(),
            'filters' => $filters,
            'location_query' => $request->input('location'),
            'latitude' => $request->filled('latitude') ? (float) $request->input('latitude') : null,
            'longitude' => $request->filled('longitude') ? (float) $request->input('longitude') : null,
            'results_count' => $resultsCount,
        ]);
    }

    /**
     * Build a weighted map of property_id => total interaction weight for a user.
     */
    public function getUserPropertyWeights(int $userId): array
    {
        return PropertyInteraction::query()
            ->where('user_id', $userId)
            ->select('property_id', DB::raw('SUM(weight) as total_weight'))
            ->groupBy('property_id')
            ->having('total_weight', '>', 0)
            ->pluck('total_weight', 'property_id')
            ->map(fn ($w) => (float) $w)
            ->all();
    }

    public function userHasInteractions(?int $userId): bool
    {
        if (!$userId) {
            return false;
        }

        return PropertyInteraction::where('user_id', $userId)->exists();
    }

    protected function shouldSkipDuplicateView(int $userId, int $propertyId): bool
    {
        $minutes = (int) config('recommendation.view_dedupe_minutes', 60);

        return PropertyInteraction::query()
            ->where('user_id', $userId)
            ->where('property_id', $propertyId)
            ->where('action', 'view')
            ->where('created_at', '>=', now()->subMinutes($minutes))
            ->exists();
    }

    protected function syncPropertyCounters(int $propertyId, string $action): void
    {
        if ($action === 'view') {
            Property::whereKey($propertyId)->increment('views_count');
        }

        if (in_array($action, ['like', 'unlike'], true)) {
            $likesCount = DB::table('property_likes')->where('property_id', $propertyId)->count();
            Property::whereKey($propertyId)->update(['likes_count' => $likesCount]);
        }
    }

    protected function extractSearchFilters(Request $request): array
    {
        return array_filter([
            'location' => $request->input('location'),
            'min-price' => $request->input('min-price'),
            'max-price' => $request->input('max-price'),
            'property_type' => $request->input('property_type'),
            'listing_type' => $request->input('listing_type'),
            'amenities' => $request->input('amenities'),
            'rules' => $request->input('rules'),
            'sort' => $request->input('sort'),
            'latitude' => $request->input('latitude'),
            'longitude' => $request->input('longitude'),
        ], fn ($value) => $value !== null && $value !== '' && $value !== []);
    }
}
