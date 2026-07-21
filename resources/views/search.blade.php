@extends('layouts.app')
@section('title', 'Search')

@push('styles')
<link rel="stylesheet" href="{{asset('css/search.css')}}">
@endpush

@push('scripts')
<script src="{{asset('js/search.js')}}"></script>
@endpush

@section('content')
    <main class="search-page">
        <!-- Mobile filter overlay -->
        <div class="filter-overlay"></div>
        
        <section class="search-filters">
            <button class="filter-close-btn" aria-label="Close filters">
                <i class="fas fa-times"></i>
            </button>
            <div class="filter-container">
                <form id="search-filters-form" action="{{ route('filter-search') }}" method="GET">
                @if(request('sort'))
                    <input type="hidden" name="sort" value="{{ request('sort') }}">
                @endif
                <div class="filter-group">
                    <h3>Search</h3>
                    <div class="search-input">
                        <i class="fas fa-search"></i>
                        <input type="text" id="location" name="location" placeholder="City, neighborhood, or keywords..." value="{{ request('location') }}" autocomplete="off">
                    </div>
                </div>

                <div class="filter-group">
                    <h3>Price Range</h3>
                    <div class="price-range">
                        <div class="search-input">
                            <i class="fas fa-dollar-sign"></i>
                            <input type="number" id="min-price" name="min-price" placeholder="Min" min="0" value="{{ request('min-price') }}" oninput="validatePriceRange()">
                        </div>
                        <span>-</span>
                        <div class="search-input">
                            <i class="fas fa-dollar-sign"></i>
                            <input type="number" id="max-price" name="max-price" placeholder="Max" min="0" value="{{ request('max-price') }}" oninput="validatePriceRange()">
                        </div>
                    </div>
                </div>

                <div class="filter-group">
                    <h3>Property Type</h3>
                    <div class="checkbox-group">
                        <label>
                            <input type="checkbox" name="property_type[]" value="apartment" {{ is_array(request('property_type')) && in_array('apartment', request('property_type')) ? 'checked' : '' }}> Appartment
                        </label>
                        <label>
                            <input type="checkbox" name="property_type[]" value="house" {{ is_array(request('property_type')) && in_array('house', request('property_type')) ? 'checked' : '' }}> House
                        </label>
                        <label>
                            <input type="checkbox" name="property_type[]" value="dorm" {{ is_array(request('property_type')) && in_array('dorm', request('property_type')) ? 'checked' : '' }}> Dorm
                        </label>
                        <label>
                            <input type="checkbox" name="property_type[]" value="other" {{ is_array(request('property_type')) && in_array('other', request('property_type')) ? 'checked' : '' }}> Other
                        </label>
                    </div>
                </div>
                <div class="filter-group">
                    <h3>Listing Type</h3>
                    <div class="checkbox-group">
                        <label>
                            <input type="checkbox" name="listing_type[]" value="Rent" {{ is_array(request('listing_type')) && in_array('Rent', request('listing_type')) ? 'checked' : '' }}> For Rent
                        </label>
                        <label>
                            <input type="checkbox" name="listing_type[]" value="Sale" {{ is_array(request('listing_type')) && in_array('Sale', request('listing_type')) ? 'checked' : '' }}> For Sale
                        </label>
                    </div>
                </div>


                <div class="filter-group">
                    <h3>Amenities</h3>
                    <div class="checkbox-group">
                        @foreach ($amenities as $amenity )
                        <label>
                            <input type="checkbox" name="amenities[]" value="{{ $amenity->id }}" {{ is_array(request('amenities')) && in_array($amenity->id, request('amenities')) ? 'checked' : '' }}> {{ $amenity->name }}
                        </label>
                        @endforeach
                    </div>
                </div>

                <div class="filter-group">
                    <h3>Rules</h3>
                    <div class="checkbox-group">
                        @foreach ($rules as $rule )
                            <label>
                            <input type="checkbox" name="rules[]" value="{{ $rule->name }}" {{ is_array(request('rules')) && in_array($rule->name, request('rules')) ? 'checked' : '' }}> {{ $rule->name }}
                            </label>
                        @endforeach
                    </div>
                </div>

                <button type="submit" class="other-list-btn apply-filters">
                    <i class="fas fa-filter"></i> Apply Filters
                </button>
                </form>
            </div>
        </section>
        
        <section class="search-results">
            <button class="search-show-btn"><i class="fas fa-search"></i>Filters</button>
            <div class="results-header">
                <div class="results-count">
                    <h2>{{ $properties->total() }} Rooms Found</h2>
                    @php
                        $listedCountries = $properties->getCollection()
                            ->pluck('country')
                            ->filter()
                            ->unique()
                            ->values();
                        $countriesLabel = $listedCountries->isEmpty()
                            ? 'All locations'
                            : $listedCountries->join(', ');
                    @endphp
                    <p>in {{ $countriesLabel }}</p>
                </div>
                <div class="results-sort">
                    <button type="button" class="filter-toggle-btn" title="Clear all filters">
                        <i class="fas fa-rotate-left"></i> Clear
                    </button>
                    <select id="sort-options" name="sort">
                        <option value="recommended" {{ request('sort') == 'recommended' || !request('sort') ? 'selected' : '' }}>Recommended</option>
                        <option value="price-low" {{ request('sort') == 'price-low' ? 'selected' : '' }}>Price: Low to High</option>
                        <option value="price-high" {{ request('sort') == 'price-high' ? 'selected' : '' }}>Price: High to Low</option>
                        <option value="newest" {{ request('sort') == 'newest' ? 'selected' : '' }}>Newest</option>
                        <option value="latest" {{ request('sort') == 'latest' ? 'selected' : '' }}>Latest</option>
                    </select>
                </div>
            </div>

                @if($properties->count() > 0)
                <div class="listings-grid">    
                    @foreach($properties as $property)
                <div class="listing-card" data-price="{{ $property->price }}" data-created="{{ $property->created_at->timestamp }}" data-likes="{{ $property->likedBy()->count() }}" data-property-id="{{ $property->id }}">
                    <div class="listing-image">
                        <img src="{{ \App\Services\PropertyImageService::getImageUrl($property) }}" alt="{{ $property->title }}">
                        <span class="listing-tag">For {{ $property->listing_type }}</span>
                        
                        <button class="setting-btn"><i class="fa fa-ellipsis-v" aria-hidden="true"></i></button>
                        <ul class="setting-list">
                            <li><a href="{{route('properties.show', $property->id) }}">View</a></li>
                            @if(auth()->check())
                                @if(auth()->id() === $property->user_id)
                                    <li><a href="{{ route('properties.edit', $property->id) }}">Edit</a></li>
                                @endif
                                @if(auth()->user()->role === 'admin' || auth()->id() === $property->user_id)
                                    <li>
                                        <form action="{{ route('properties.destroy', $property->id) }}" method="POST" class="form-inline">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="delete-btn" onclick="return confirm('Are you sure you want to delete this property?')">Delete</button>
                                        </form>
                                    </li>
                                @endif
                            @endif
                        </ul>
                        @auth
                            <button
                                type="button"
                                class="favorite-btn like-btn"
                                data-property-id="{{ $property->id }}"
                                data-liked="{{ $property->isLikedBy(auth()->id()) ? 'true' : 'false' }}"
                                aria-pressed="{{ $property->isLikedBy(auth()->id()) ? 'true' : 'false' }}"
                            >
                                <i class="fa-{{ $property->isLikedBy(auth()->id()) ? 'solid' : 'regular' }} fa-heart"></i>
                            </button>
                            <span class="like-count" id="like-count-{{ $property->id }}">{{ $property->likedBy()->count() }}</span>
                        @else
                            <button type="button" class="favorite-btn" data-login-url="{{ route('login') }}">
                                <i class="fa-regular fa-heart"></i>
                            </button>
                        @endauth
                    </div>
                    <div class="listing-content">
                    <span class="available-from">Listed {{ $property->created_at->diffForHumans() }}</span>
                        <h3>{{ $property->title }}</h3>
                        <p class="listing-location">
                            <i class="fas fa-map-marker-alt"></i> 
                            {{ $property->address }}
                        </p>
                        <div class="listing-features">
                            <span><i class="fas fa-home"></i> {{ $property->property_type }}</span>
                            <span><i class="fa-solid fa-person-shelter"></i> {{ $property->room_nb }} Room</span>
                            @if($property->bedroom_nb)
                                <span><i class="fas fa-bed"></i> {{ $property->bedroom_nb }} Bedrooms</span>
                            @endif
                            @if($property->bathroom_nb)
                                <span><i class="fas fa-bath"></i> {{ $property->bathroom_nb }} Bathrooms</span>
                            @endif
                            @if($property->area_m3)
                                <span><i class="fas fa-ruler-combined"></i> {{ $property->area_m3 }}m²</span>
                            @endif
                            @if($property->getAvailabilityMessage())
                        <span class="listing-tag listing-tag--unavailable" title="{{ $property->getAvailabilityMessage() }}">{{ $property->getAvailabilityMessage() }}</span>
                        @endif
                        </div>
                    </div>
                    <div class="listing-meta">
                        <div class="listing-price">
                            <b>${{ $property->price }}</b>@if(($property->listing_type ?? null) === 'rent')/{{ $property->price_duration ?? 'month' }}@endif
                        </div>                       
                        <a href="{{ route('properties.show', $property->id) }}" class="view-btn" data-track-click="search_results">View Details</a>
                    </div>
                </div>
                @endforeach
                @else
                <div class="no-results">
                    <div class="no-results-character">
                        <img src="{{ asset('images/character/search-looking.png') }}" alt="No results" class="empty-state-character">
                    </div>
                    <h3>No properties found</h3>
                    <p>We couldn't find any properties matching your criteria. Try adjusting your filters or search in a different area.</p>
                    <a href="{{ route('search') }}" class="reset-filters-btn">Reset Filters</a>
                </div>
                @endif
            </div>

            <div class="pagination">
                @if ($properties->hasPages())
                    @if ($properties->onFirstPage())
                        <button class="pagination-btn" disabled>Previous</button>
                    @else
                        <a href="{{ $properties->previousPageUrl() }}" class="pagination-btn">Previous</a>
                    @endif

                    @foreach ($properties->getUrlRange(1, $properties->lastPage()) as $page => $url)
                        @if ($page == $properties->currentPage())
                            <button class="pagination-btn active">{{ $page }}</button>
                        @else
                            <a href="{{ $url }}" class="pagination-btn">{{ $page }}</a>
                        @endif
                    @endforeach

                    @if ($properties->hasMorePages())
                        <a href="{{ $properties->nextPageUrl() }}" class="pagination-btn">Next</a>
                    @else
                        <button class="pagination-btn" disabled>Next</button>
                    @endif
                @endif
            </div>
        </section>
    </main>
@endsection