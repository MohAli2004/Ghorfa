<?php

namespace App\Http\Controllers;

use App\Models\Property;
use App\Services\PropertyInteractionService;
use App\Services\RecommendationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RecommendationController extends Controller
{
    public function __construct(
        protected RecommendationService $recommendationService,
        protected PropertyInteractionService $interactionService,
    ) {}

    /**
     * Dedicated page showing personalized property recommendations.
     */
    public function index(Request $request)
    {
        $context = $this->recommendationService->contextFromRequest($request);
        $recommendations = $this->recommendationService->getRecommendations(
            $request->user(),
            $context,
            (int) $request->input('limit', 12)
        );

        return view('recommendations.index', compact('recommendations', 'context'));
    }

    /**
     * JSON endpoint for AJAX recommendation widgets.
     */
    public function api(Request $request): JsonResponse
    {
        $context = $this->recommendationService->contextFromRequest($request);
        $limit = (int) $request->input('limit', 12);

        $recommendations = $this->recommendationService->getRecommendations(
            $request->user(),
            $context,
            $limit
        );

        return response()->json([
            'data' => $recommendations->map(function (array $item) {
                $property = $item['property'];

                return [
                    'id' => $property->id,
                    'title' => $property->title,
                    'city' => $property->city,
                    'price' => $property->price,
                    'listing_type' => $property->listing_type,
                    'final_score' => $item['final_score'],
                    'scores' => $item['scores'],
                    'url' => route('properties.show', $property),
                ];
            })->values(),
        ]);
    }

    public function trackClick(Request $request, Property $property): JsonResponse
    {
        if (!$request->user()) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $action = in_array($request->input('source'), ['search', 'search_results'], true)
            ? 'search_view'
            : 'click';

        $this->interactionService->record($request->user()->id, $property->id, $action, [
            'source' => $request->input('source', 'unknown'),
        ]);

        return response()->json(['status' => 'recorded']);
    }

    public function trackContact(Request $request, Property $property): JsonResponse
    {
        if (!$request->user()) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $this->interactionService->recordContact($request->user()->id, $property, [
            'channel' => $request->input('channel', 'unknown'),
        ]);

        return response()->json(['status' => 'recorded']);
    }
}
