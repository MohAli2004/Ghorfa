<?php

namespace App\Http\Controllers;

use App\Models\Property;
use App\Models\Amenity;
use App\Models\Rule;
use App\Services\GeocodingService;
use App\Services\PropertyInteractionService;
use App\Services\SemanticSearchService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class MapController extends Controller
{
    public function __construct(
        protected SemanticSearchService $semanticSearchService,
        protected PropertyInteractionService $interactionService,
    ) {}

    public function index(Request $request)
    {
        $query = Property::with(['images', 'unit', 'amenities', 'reviews'])
            ->where('status', 'approved')
            ->whereNotNull('latitude')
            ->whereNotNull('longitude');

        $searchText = $this->semanticSearchService->extractQueryFromRequest($request);
        $useSemantic = $this->semanticSearchService->shouldUseSemanticSearch($request);

        if (!$useSemantic && $request->filled('location')) {
            $location = $request->input('location');
            $query->where(function ($q) use ($location) {
                $q->where('country', 'like', "%{$location}%")
                    ->orWhere('city', 'like', "%{$location}%")
                    ->orWhere('address', 'like', "%{$location}%")
                    ->orWhere('title', 'like', "%{$location}%")
                    ->orWhere('description', 'like', "%{$location}%");
            });
        }

        if ($request->filled('min_price')) {
            $query->where('price', '>=', $request->input('min_price'));
        }

        if ($request->filled('max_price')) {
            $query->where('price', '<=', $request->input('max_price'));
        }

        if ($request->has('property_type')) {
            $query->whereIn('property_type', (array) $request->input('property_type'));
        }

        if ($request->has('listing_type')) {
            $query->whereIn('listing_type', (array) $request->input('listing_type'));
        }

        if ($useSemantic && $searchText) {
            $result = $this->semanticSearchService->rankProperties($query, $searchText);
            $query = $result['query'];
        }

        $properties = $query->get();

        $this->interactionService->recordSearch($request, $properties->count());

        $amenities = Amenity::all();
        $rules = Rule::all();

        return view('map', compact('properties', 'amenities', 'rules', 'request'));
    }

    public function geocode(Request $request)
    {
        $address = $request->input('address');
        $apiKey = config('services.google.maps_server_key');

        if (!$address || !$apiKey) {
            return response()->json(['error' => 'Address or API key missing'], 400);
        }

        try {
            $response = Http::get('https://maps.googleapis.com/maps/api/geocode/json', [
                'address' => $address,
                'key' => $apiKey
            ]);

            $data = $response->json();

            if ($data['status'] === 'OK' && !empty($data['results'])) {
                $location = $data['results'][0]['geometry']['location'];
                return response()->json([
                    'latitude' => $location['lat'],
                    'longitude' => $location['lng'],
                    'formatted_address' => $data['results'][0]['formatted_address']
                ]);
            }

            return response()->json(['error' => 'Geocoding failed'], 400);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Geocoding service error'], 500);
        }
    }

    /**
     * Reverse geocode coordinates to get address
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function reverseGeocode(Request $request)
    {
        $latitude = $request->input('latitude');
        $longitude = $request->input('longitude');

        if (!$latitude || !$longitude) {
            return response()->json(['error' => 'Latitude and longitude are required'], 400);
        }

        try {
            $geocodingService = new GeocodingService();
            $result = $geocodingService->reverseGeocode(
                (float) $latitude,
                (float) $longitude
            );

            if ($result) {
                return response()->json([
                    'formatted_address' => $result['formatted_address'],
                    'address' => $result['formatted_address'],
                    'address_components' => $result['address_components'] ?? []
                ]);
            }

            return response()->json(['error' => 'Reverse geocoding failed'], 400);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Reverse geocoding service error',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}