<?php

namespace App\Http\Controllers;

use App\Models\Rule;
use App\Models\Amenity;
use App\Models\Property;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Http\Request;
use App\Models\PropertyImage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use App\Services\GeocodingService;
use App\Services\PropertyImageCompressor;
use App\Services\PropertyInteractionService;
use App\Services\RecommendationService;
use App\Services\SemanticSearchService;
use App\Traits\CreatesNotifications;
use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Builder;

class PropertyController extends Controller
{
    use CreatesNotifications, LogsActivity;

    public function __construct(
        protected PropertyInteractionService $interactionService,
        protected RecommendationService $recommendationService,
        protected SemanticSearchService $semanticSearchService,
    ) {}

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        return $this->runPropertySearch($request);
    }

    public function filterSearch(Request $request)
    {
        return $this->runPropertySearch($request);
    }

    /**
     * Shared search pipeline: filters → semantic AI ranking or hybrid sort → paginate.
     */
    protected function runPropertySearch(Request $request)
    {
        $amenities = Amenity::all();
        $rules = Rule::all();
        $query = Property::query()->where('status', 'approved');

        $searchText = $this->semanticSearchService->extractQueryFromRequest($request);
        $useSemantic = $this->semanticSearchService->shouldUseSemanticSearch($request);

        $this->applySearchFilters($query, $request, skipTextSearch: $useSemantic);

        $sort = $request->input('sort', 'recommended');
        $context = $this->recommendationService->contextFromRequest($request);
        if ($useSemantic && $searchText) {
            $result = $this->semanticSearchService->rankProperties($query, $searchText);
            $query = $result['query'];
        } elseif ($sort === 'recommended') {
            $query = $this->recommendationService->applyRecommendedSort($query, $request->user(), $context);
        } elseif ($sort !== 'semantic') {
            $query = $this->applySorting($query, $sort);
        } else {
            $query = $this->recommendationService->applyRecommendedSort($query, $request->user(), $context);
        }

        $properties = $query->paginate(12)->withQueryString();

        $this->interactionService->recordSearch($request, $properties->total());

        return view('search', compact(
            'properties',
            'amenities',
            'rules',
            'request',
        ));
    }

    /**
     * Apply price/type/amenity filters. Text LIKE is skipped when semantic AI handles the query.
     */
    protected function applySearchFilters(Builder $query, Request $request, bool $skipTextSearch = false): void
    {
        if (!$skipTextSearch && $request->filled('location')) {
            $location = $request->input('location');
            $query->where(function ($q) use ($location) {
                $q->where('country', 'like', "%{$location}%")
                    ->orWhere('city', 'like', "%{$location}%")
                    ->orWhere('address', 'like', "%{$location}%")
                    ->orWhere('title', 'like', "%{$location}%")
                    ->orWhere('description', 'like', "%{$location}%");
            });
        }

        if ($request->filled('min-price')) {
            $query->where('price', '>=', $request->input('min-price'));
        }

        if ($request->filled('max-price')) {
            $query->where('price', '<=', $request->input('max-price'));
        }

        if ($request->has('property_type')) {
            $query->whereIn('property_type', (array) $request->input('property_type'));
        }

        if ($request->has('listing_type')) {
            $query->whereIn('listing_type', (array) $request->input('listing_type'));
        }

        if ($request->has('amenities')) {
            $amenityIds = $this->normalizeFilterIds((array) $request->input('amenities'));
            if (count($amenityIds) > 0) {
                $query->whereHas('amenities', function ($q) use ($amenityIds) {
                    $q->whereIn('amenities.id', $amenityIds);
                }, '=', count($amenityIds));
            }
        }

        if ($request->has('rules')) {
            $ruleNames = $this->normalizeFilterNames((array) $request->input('rules'));
            if (count($ruleNames) > 0) {
                $query->whereHas('rules', function ($q) use ($ruleNames) {
                    $q->whereIn('rules.name', $ruleNames);
                }, '=', count($ruleNames));
            }
        }
    }

    protected function normalizeFilterIds(array $items): array
    {
        return array_values(array_filter(array_map(function ($item) {
            if (is_array($item)) {
                return $item['id'] ?? null;
            }
            if (is_string($item) && str_starts_with($item, '{')) {
                $decoded = json_decode($item, true);

                return $decoded['id'] ?? null;
            }

            return $item;
        }, $items)));
    }

    protected function normalizeFilterNames(array $items): array
    {
        return array_values(array_filter(array_map(function ($item) {
            if (is_array($item)) {
                return $item['name'] ?? $item['title'] ?? null;
            }
            if (is_string($item) && str_starts_with($item, '{')) {
                $decoded = json_decode($item, true);

                return $decoded['name'] ?? $decoded['title'] ?? null;
            }

            return $item;
        }, $items)));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, Property $property)
    {
        // Only show approved properties, unless user is admin or property owner
        if ($property->status !== 'approved') {
            $user = Auth::user();
            if (!$user || ($user->role !== 'admin' && $user->id !== $property->user_id)) {
                abort(404, 'Property not found or pending approval');
            }
        }
        
        $property->load(['images', 'reviews.user', 'landlord']);
        
        $avgRating = $property->average_rating;
        $reviewsCount = $property->reviews_count;

        if ($requestUser = Auth::user()) {
            $this->interactionService->recordView($requestUser->id, $property, [
                'source' => 'property_show',
            ]);
        }
        
        return view('show', compact('property', 'avgRating', 'reviewsCount'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Property $property)
    {
        if (Auth::check() && (Auth::user()->role === 'admin' || $property->user_id === Auth::id())) {
            $amenities = Amenity::all();
            $rules = Rule::all();
            $units = Unit::all();

            $property->loadMissing(['amenities', 'rules', 'images', 'unit']);

            return view('edit-property', compact('property', 'amenities', 'rules', 'units'));
        }

        return redirect()->route('properties.show', $property)
            ->with('error', 'You are not authorized to edit this property!');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Property $property)
    {
        if (!(Auth::check() && (Auth::user()->role === 'admin' || $property->user_id === Auth::id()))) {
            return redirect()->route('properties.show', $property)
                ->with('error', 'You are not authorized to edit this property!');
        }

        $validated = $request->validate([
            'title'         => 'required|string|max:255',
            'description'   => 'required|string',
            'property_type' => 'required|string',
            'listing_type'  => 'required|string',
            'country'       => 'nullable|string',
            'city'          => 'nullable|string',
            'address'       => 'nullable|string',
            'price'         => 'required|numeric|min:0',
            'price_duration' => 'required_if:listing_type,rent|nullable|string|in:month,week,day,year',
            'price_per_day' => 'nullable|numeric|min:0',
            'price_per_week' => 'nullable|numeric|min:0',
            'price_per_month' => 'nullable|numeric|min:0',
            'price_per_year' => 'nullable|numeric|min:0',
            'rent_duration_units' => 'required_if:listing_type,rent|nullable|array|min:1',
            'rent_duration_units.*' => 'in:day,week,month,year',
            'unit'          => 'required|integer|exists:units,id',
            'area_m3'       => 'required|numeric|min:0',
            'room_nb'       => 'required|integer|min:0',
            'bathroom_nb'   => 'required|integer|min:0',
            'bedroom_nb'    => 'required|integer|min:0',

            'latitude'      => 'required|numeric|between:-90,90',
            'longitude'     => 'required|numeric|between:-180,180',

            'images'        => 'nullable|array',
            'images.*'      => 'image|mimes:jpeg,png,jpg,gif,webp|max:2048',

            'amenities'     => 'nullable|array',
            'amenities.*'   => 'integer|exists:amenities,id',
            'rules'         => 'nullable|array',
            'rules.*'       => 'integer|exists:rules,id',
            'remove_images' => 'nullable|array',
            'remove_images.*' => 'integer|exists:property_images,id',
        ]);

        DB::beginTransaction();

        try {
            $validated['unit_id'] = $validated['unit'];
            unset($validated['unit'], $validated['images'], $validated['remove_images']);

            // Store accepted rent duration units as comma-separated string for SET column
            if (($validated['listing_type'] ?? null) === 'rent') {
                $validated['rent_duration_units'] = implode(',', (array) ($validated['rent_duration_units'] ?? []));
            } else {
                $validated['rent_duration_units'] = null;
            }

            if (($validated['listing_type'] ?? null) === 'rent') {
                $days = ['day' => 1, 'week' => 7, 'month' => 30, 'year' => 365];
                $baseUnit = $validated['price_duration'] ?? 'month';
                $basePrice = (float) ($validated['price'] ?? 0);
                $divisor = $days[$baseUnit] ?? 30;
                $perDay = $divisor > 0 ? ($basePrice / $divisor) : 0;
                $validated['price_per_day'] = round($perDay, 2);
                $validated['price_per_week'] = round($perDay * 7, 2);
                $validated['price_per_month'] = round($perDay * 30, 2);
                $validated['price_per_year'] = round($perDay * 365, 2);
            } else {
                $validated['price_per_day'] = null;
                $validated['price_per_week'] = null;
                $validated['price_per_month'] = null;
                $validated['price_per_year'] = null;
            }

            if (isset($validated['latitude']) && isset($validated['longitude']) 
                && !empty($validated['latitude']) && !empty($validated['longitude'])) {
                $validated['latitude'] = (float) $validated['latitude'];
                $validated['longitude'] = (float) $validated['longitude'];
            } else {
                if ($this->shouldGeocode($property, $validated)) {
                    $coordinates = $this->geocodeAddress($validated);
                    if ($coordinates) {
                        $validated['latitude'] = $coordinates['latitude'];
                        $validated['longitude'] = $coordinates['longitude'];
                        if (isset($coordinates['country']) && !isset($validated['country'])) {
                            $validated['country'] = $coordinates['country'];
                        }
                        if (isset($coordinates['city']) && !isset($validated['city'])) {
                            $validated['city'] = $coordinates['city'];
                        }
                        if (isset($coordinates['address']) && !isset($validated['address'])) {
                            $validated['address'] = $coordinates['address'];
                        }
                    }
                }
            }

            $property->update($validated);

            $property->amenities()->sync((array)$request->input('amenities', []));
            $property->rules()->sync((array)$request->input('rules', []));

            // Log activity
            $this->logActivity(
                'property_updated',
                "Property '{$property->title}' was updated",
                $property,
                ['property_id' => $property->id, 'property_title' => $property->title]
            );

            $removeImageIds = collect($request->input('remove_images', []))
                ->map(fn ($id) => (int) $id)
                ->filter()
                ->unique();

            if ($removeImageIds->isNotEmpty()) {
                $imagesToDelete = $property->images()
                    ->whereIn('id', $removeImageIds)
                    ->get();

                foreach ($imagesToDelete as $image) {
                    $image->delete();
                }

                $property->load('images');

                if ($property->images()->where('is_primary', true)->doesntExist()) {
                    $nextImage = $property->images()->first();
                    if ($nextImage) {
                        $nextImage->setAsPrimary();
                    }
                }
            }

            if ($request->hasFile('images')) {
                $hasPrimary = $property->images()->where('is_primary', true)->exists();
                foreach ($request->file('images') as $image) {
                    $path = PropertyImageCompressor::store($image);
                    PropertyImage::create([
                        'property_id' => $property->id,
                        'path'        => $path,
                        'is_primary'  => !$hasPrimary && !$property->images()->exists(),
                    ]);
                    $hasPrimary = $hasPrimary || true;
                }
            }

            DB::commit();

            return redirect()->route('properties.show', $property)
                ->with('success', 'Property updated successfully!');
        } catch (\Throwable $e) {
            DB::rollBack();
            return redirect()->route('properties.show', $property)
                ->with('error', 'Failed to update property: '.$e->getMessage());
        }
    }


    public function destroy(Property $property)
    {
    
            if (Auth::check() && (Auth::user()->role === 'admin' || $property->user_id === Auth::id())) {
                $propertyTitle = $property->title;
                $propertyId = $property->id;
                
                foreach ($property->images as $image) {
                    Storage::disk('public')->delete($image->path);
                }

                $property->delete();

                // Log activity
                $this->logActivity(
                    'property_deleted',
                    "Property '{$propertyTitle}' was deleted",
                    null,
                    ['property_id' => $propertyId, 'property_title' => $propertyTitle]
                );

                return redirect()->route('search')->with('success', 'Property deleted successfully!');
            }

            return back()->with('error', 'You are not authorized to delete this property!');
       
    }

    public function submitListing(Request $request)
    {
        if (!Auth::check() || !Auth::user()->isLandlord()) {
            return redirect()->route('home')
                ->with('error', 'Only landlords and admins can list properties. <a href="' . route('landlord.apply') . '">Become a Landlord</a>');
        }

        try {
            $validated = $request->validate([
                'title'         => 'required|string|max:255',
                'description'   => 'nullable|string',
                'property_type' => 'required|string',
                'listing_type'  => 'required|string',
                'country'       => 'nullable|string',
                'city'          => 'nullable|string',
                'address'       => 'nullable|string',
                'price'         => 'required|numeric|min:0',
                'price_duration' => 'required_if:listing_type,rent|nullable|string|in:month,week,day,year',
                'price_per_day' => 'nullable|numeric|min:0',
                'price_per_week' => 'nullable|numeric|min:0',
                'price_per_month' => 'nullable|numeric|min:0',
                'price_per_year' => 'nullable|numeric|min:0',
                'rent_duration_units' => 'required_if:listing_type,rent|nullable|array|min:1',
                'rent_duration_units.*' => 'in:day,week,month,year',
                'unit'          => 'required|integer|exists:units,id',
                'area_m3'       => 'required|numeric|min:0',
                'room_nb'       => 'nullable|integer|min:0',
                'bathroom_nb'   => 'required|integer|min:0',
                'bedroom_nb'    => 'nullable|integer|min:0',
                'latitude'      => 'required|numeric|between:-90,90',
                'longitude'     => 'required|numeric|between:-180,180',
                'images'        => 'required|array|min:1',
                'images.*'      => 'required|image|mimes:jpeg,png,jpg,gif,webp|max:10240',
                'amenities'     => 'nullable|array',
                'amenities.*'   => 'integer|exists:amenities,id',
                'rules'         => 'nullable|array',
                'rules.*'       => 'integer|exists:rules,id',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::error('Validation failed:', $e->errors());
            Log::error('Request data:', $request->except(['images', 'password']));

            return back()
                ->withErrors($e->errors())
                ->withInput()
                ->with('error', 'Please fix the highlighted errors below and submit again.');
        }

        if (!$request->hasFile('images')) {
            Log::error('No images uploaded after validation', [
                'files' => array_keys($request->allFiles()),
            ]);

            return back()
                ->withErrors(['images' => 'Please upload at least one image.'])
                ->withInput()
                ->with('error', 'Your photos did not upload. Please choose them again and submit.');
        }

        $imageFiles = $request->file('images');
        if (empty($imageFiles) || count($imageFiles) < 1) {
            return back()
                ->withErrors(['images' => 'Please upload at least one image.'])
                ->withInput()
                ->with('error', 'Your photos did not upload. Please choose them again and submit.');
        }

        DB::beginTransaction();

        try {
            $user = Auth::user();
            $isAdminListing = $user->isAdmin();

            $validated['user_id'] = $user->id;
            $validated['unit_id'] = $validated['unit'] ?? null;
            $validated['status'] = $isAdminListing ? 'approved' : 'pending';
            if ($isAdminListing) {
                $validated['approved_at'] = now();
                $validated['approved_by'] = $user->id;
            }
            unset($validated['unit']);

            // Store accepted rent duration units as comma-separated string for SET column
            if (($validated['listing_type'] ?? null) === 'rent') {
                $validated['rent_duration_units'] = implode(',', (array) ($validated['rent_duration_units'] ?? []));
            } else {
                $validated['rent_duration_units'] = null;
            }

            // Calculate and store prices per duration unit (server-side source of truth)
            if (($validated['listing_type'] ?? null) === 'rent') {
                $days = ['day' => 1, 'week' => 7, 'month' => 30, 'year' => 365];
                $baseUnit = $validated['price_duration'] ?? 'month';
                $basePrice = (float) ($validated['price'] ?? 0);
                $divisor = $days[$baseUnit] ?? 30;
                $perDay = $divisor > 0 ? ($basePrice / $divisor) : 0;
                $validated['price_per_day'] = round($perDay, 2);
                $validated['price_per_week'] = round($perDay * 7, 2);
                $validated['price_per_month'] = round($perDay * 30, 2);
                $validated['price_per_year'] = round($perDay * 365, 2);
            } else {
                $validated['price_per_day'] = null;
                $validated['price_per_week'] = null;
                $validated['price_per_month'] = null;
                $validated['price_per_year'] = null;
            }
            $amenityIds = (array)($request->input('amenities', []));
            $ruleIds    = (array)($request->input('rules', []));

            $latitude = (float) $validated['latitude'];
            $longitude = (float) $validated['longitude'];
            
            $geocodingService = app(GeocodingService::class);
            $reverseGeocodeResult = $geocodingService->reverseGeocode($latitude, $longitude);
            
            if ($reverseGeocodeResult) {
                $addressComponents = $reverseGeocodeResult['address_components'] ?? [];
                
                $validated['country'] = $this->extractAddressComponent($addressComponents, 'country') 
                    ?? $validated['country'] ?? 'Lebanon';
                $validated['city'] = $this->extractAddressComponent($addressComponents, 'locality')
                    ?? $this->extractAddressComponent($addressComponents, 'administrative_area_level_1')
                    ?? $validated['city'] ?? 'Unknown';
                $streetNumber = $this->extractAddressComponent($addressComponents, 'street_number');
                $route = $this->extractAddressComponent($addressComponents, 'route');
                $address = trim(($streetNumber ?? '') . ' ' . ($route ?? ''));
                $validated['address'] = $address ?: ($reverseGeocodeResult['formatted_address'] ?? $validated['address'] ?? 'Unknown');
            } else {
                $validated['country'] = $validated['country'] ?? 'Lebanon';
                $validated['city'] = $validated['city'] ?? 'Unknown';
                $validated['address'] = $validated['address'] ?? 'Unknown';
                Log::warning('Reverse geocoding failed for coordinates', [
                    'latitude' => $latitude,
                    'longitude' => $longitude
                ]);
            }

            $validated['latitude'] = $latitude;
            $validated['longitude'] = $longitude;

            $property = Property::create($validated);

            if (!empty($amenityIds)) {
                $property->amenities()->sync($amenityIds);
            }
            if (!empty($ruleIds)) {
                $property->rules()->sync($ruleIds);
            }

            // Log activity
            $this->logActivity(
                'property_created',
                "New property '{$property->title}' was created",
                $property,
                [
                    'property_id' => $property->id,
                    'property_title' => $property->title,
                    'status' => $property->status,
                ]
            );

            if ($request->hasFile('images')) {
                foreach ($request->file('images') as $index => $image) {
                    $path = PropertyImageCompressor::store($image);
                    PropertyImage::create([
                        'property_id' => $property->id,
                        'path'        => $path,
                        'is_primary'  => $index === 0,
                    ]);
                }
            }

            DB::commit();

            if (!$isAdminListing) {
                User::where('role', 'admin')->each(function (User $admin) use ($property, $user) {
                    $this->createNotification(
                        $admin,
                        'pending',
                        'New Property Listing',
                        $user->name . ' submitted "' . $property->title . '" for approval.',
                        $property
                    );
                });
            }

            $successMessage = $isAdminListing
                ? 'Property listed successfully. It is now live and visible in search results.'
                : 'Your listing was submitted successfully and is pending admin approval. It will appear in search results once approved.';

            return redirect()
                ->route('profileProperties')
                ->with('success', $successMessage);
        } catch (\Throwable $e) {
            DB::rollBack();

            Log::error('Failed to create property', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->except(['images', 'password'])
            ]);

            if (isset($property) && $property->exists) {
                foreach ($property->images as $img) {
                    Storage::disk('public')->delete($img->path);
                }
                $property->delete();
            }

            return back()
                ->withErrors(['error' => 'Failed to create property: ' . $e->getMessage()])
                ->withInput()
                ->with('error', 'Failed to create property. Please check the errors below.');
        }
    }


    /**
     * Toggle like status for a property
     */
    public function like(Property $property)
    {
        $user = Auth::user();

        if (!$user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        if ($property->likedBy()->where('user_id', $user->id)->exists()) {
            $property->likedBy()->detach($user->id);
            $status = 'unliked';
            $this->interactionService->recordUnlike($user->id, $property);
        } else {
            $property->likedBy()->attach($user->id);
            $status = 'liked';
            $this->interactionService->recordLike($user->id, $property);
            
            if ($property->user_id !== $user->id && $property->user) {
                $this->createNotification(
                    $property->user,
                    'like',
                    'New Like on Your Property',
                    $user->name . ' liked your property: ' . $property->title,
                    $property
                );
            }
        }
        
        return response()->json([
            'status' => $status,
            'count' => $property->fresh()->likes_count,
        ]);
    }

    /**
     * Check if property address has changed and needs geocoding
     */
    private function shouldGeocode(Property $property, array $validated): bool
    {
        $locationFields = ['address', 'city', 'country'];
        
        foreach ($locationFields as $field) {
            if (isset($validated[$field]) && $property->$field !== $validated[$field]) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Geocode an address using the GeocodingService
     */
    private function geocodeAddress(array $data): ?array
    {
        $geocodingService = app(GeocodingService::class);
        
        $addressParts = array_filter([
            $data['address'] ?? '',
            $data['city'] ?? '',
            $data['country'] ?? ''
        ]);
        
        $address = implode(', ', $addressParts);
        
        if (empty($address)) {
            return null;
        }
        
        return $geocodingService->geocode($address);
    }

    private function extractAddressComponent(array $addressComponents, string $type): ?string
    {
        foreach ($addressComponents as $component) {
            if (in_array($type, $component['types'] ?? [])) {
                return $component['long_name'] ?? null;
            }
        }
        return null;
    }

       private function applySorting($query, $sort)
    {
        $sort = str_replace(['price_high', 'price_low'], ['price-high', 'price-low'], (string) $sort);

        switch ($sort) {
            case 'price-low':
                return $query->orderBy('price', 'asc');
            
            case 'price-high':
                return $query->orderBy('price', 'desc');
            
            case 'semantic':
                return $query->orderByDesc('created_at');
            
            case 'newest':
                return $query->orderBy('created_at', 'desc');
            
            case 'latest':
                return $query->orderBy('created_at', 'asc');
            
            case 'recommended':
            default:
                // Sort by number of likes (most liked first)
                return $query->withCount('likedBy')
                    ->orderBy('liked_by_count', 'desc')
                    ->orderBy('created_at', 'desc'); 
        }
    }

    public function popularCities($limit = 10)
    {
        return Property::where('status', 'approved')
            ->whereNotNull('city')
            ->where('city', '!=', '')
            ->selectRaw('city, COUNT(*) as property_count')
            ->groupBy('city')
            ->orderBy('property_count', 'desc')
            ->orderBy('city', 'asc')
            ->limit($limit)
            ->get()
            ->map(function ($item) {
                return [
                    'name' => $item->city,
                    'count' => $item->property_count,
                    'url' => route('filter-search', ['location' => $item->city])
                ];
            });
    }
}

