@extends('layouts/app')

@push('styles')
<link rel="stylesheet" href="{{ asset('css/profile/profile.css') }}">
<link rel="stylesheet" href="{{ asset('css/search.css') }}">
@endpush

@push('scripts')
<script src="{{ asset('js/search.js') }}" defer></script>
@endpush

@section('content')
@auth
<div class="profile-info-wrapper">
    <div class="profile-layout-container">
        @include('profile.partials.sidebar-nav')

        <main class="profile-main-content">
            <div class="search-page">
                <section class="search-results">
                    <div class="results-header">
                        <div class="results-count">
                            <h2>{{ $properties->total() }} {{ $properties->total() === 1 ? 'listing' : 'listings' }}</h2>
                            <p>Your published properties</p>
                        </div>
                    </div>

                    @if(session('success'))
                        <div class="alert alert-success mb-4" role="status">{{ session('success') }}</div>
                    @endif

                    @if(session('error'))
                        <div class="alert alert-danger mb-4" role="alert">{!! session('error') !!}</div>
                    @endif

                    <div class="listings-grid">
                        @foreach($properties as $property)
                            <div class="listing-card" data-price="{{ $property->price }}" data-created="{{ $property->created_at->timestamp }}">
                                <div class="listing-image">
                                    <img src="{{ \App\Services\PropertyImageService::getImageUrl($property) }}" alt="{{ $property->title }}">
                                    <span class="listing-tag">For {{ $property->listing_type }}</span>
                                    @if($property->status !== 'approved')
                                        <span class="listing-tag listing-tag--status">{{ ucfirst($property->status) }}</span>
                                    @endif
                                    @if($property->getAvailabilityMessage())
                                        <span class="listing-tag listing-tag--unavailable" title="{{ $property->getAvailabilityMessage() }}">{{ $property->getAvailabilityMessage() }}</span>
                                    @endif
                                    <button type="button" class="setting-btn" aria-label="Listing options"><i class="fa fa-ellipsis-v" aria-hidden="true"></i></button>
                                    <ul class="setting-list">
                                        <li><a href="{{ route('properties.show', $property->id) }}">View</a></li>
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
                                    </ul>
                                    <button
                                        type="button"
                                        class="favorite-btn like-btn"
                                        data-property-id="{{ $property->id }}"
                                        data-liked="{{ $property->isLikedBy(auth()->id()) ? 'true' : 'false' }}"
                                    >
                                        <i class="fa-{{ $property->isLikedBy(auth()->id()) ? 'solid' : 'regular' }} fa-heart"></i>
                                    </button>
                                    <span class="like-count" id="like-count-{{ $property->id }}">{{ $property->likedBy()->count() }}</span>
                                </div>
                                <div class="listing-content">
                                    <span class="available-from">Listed {{ $property->created_at->diffForHumans() }}</span>
                                    <h3>{{ $property->title }}</h3>
                                    <p class="listing-location">
                                        <i class="fas fa-map-marker-alt"></i>
                                        {{ $property->address }}, {{ $property->city }}, {{ $property->country }}
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
                                    </div>
                                </div>
                                <div class="listing-meta">
                                    <div class="listing-price">
                                        <b>${{ number_format($property->price) }}</b>@if(($property->listing_type ?? null) === 'rent')/{{ $property->price_duration ?? 'month' }}@endif
                                    </div>
                                    <a href="{{ route('properties.show', $property->id) }}" class="view-btn">View Details</a>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <div class="pagination">
                        @if ($properties->hasPages())
                            @if ($properties->onFirstPage())
                                <button type="button" class="pagination-btn" disabled>Previous</button>
                            @else
                                <a href="{{ $properties->previousPageUrl() }}" class="pagination-btn">Previous</a>
                            @endif

                            @foreach ($properties->getUrlRange(1, $properties->lastPage()) as $page => $url)
                                @if ($page == $properties->currentPage())
                                    <button type="button" class="pagination-btn active">{{ $page }}</button>
                                @else
                                    <a href="{{ $url }}" class="pagination-btn">{{ $page }}</a>
                                @endif
                            @endforeach

                            @if ($properties->hasMorePages())
                                <a href="{{ $properties->nextPageUrl() }}" class="pagination-btn">Next</a>
                            @else
                                <button type="button" class="pagination-btn" disabled>Next</button>
                            @endif
                        @endif
                    </div>
                </section>
            </div>
        </main>
    </div>
</div>
@endauth

@guest
<h1 class="guest-login-message">Please login first!</h1>
@endguest
@endsection
