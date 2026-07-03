@extends('layouts.app')
@section('title', 'Recommended Properties')

@push('styles')
<link rel="stylesheet" href="{{ asset('css/search.css') }}">
<link rel="stylesheet" href="{{ asset('css/recommendations.css') }}">
@endpush

@push('scripts')
<script src="{{ asset('js/recommendations.js') }}" defer></script>
@endpush

@section('content')
<main class="search-page recommendations-page">
    <section class="search-results">
        <div class="results-header">
            <div class="results-count">
                <h2>Recommended For You</h2>
                <p>Listings picked based on your preferences and browsing activity.</p>
            </div>
        </div>

        @if($recommendations->isEmpty())
            <div class="no-results">
                <h3>No recommendations yet</h3>
                <p>Browse and save properties to see personalized suggestions here.</p>
                <a href="{{ route('search') }}" class="reset-filters-btn">Browse Properties</a>
            </div>
        @else
            <div class="listings-grid">
                @foreach($recommendations as $item)
                    @php $property = $item['property']; @endphp
                    <div class="listing-card" data-property-id="{{ $property->id }}">
                        <div class="listing-image">
                            <a href="{{ route('properties.show', $property) }}" data-track-click="recommendations_page">
                                <img src="{{ \App\Services\PropertyImageService::getImageUrl($property) }}" alt="{{ $property->title }}">
                            </a>
                            <span class="listing-tag">For {{ $property->listing_type }}</span>
                        </div>
                        <div class="listing-content">
                            <h3>{{ $property->title }}</h3>
                            <p class="listing-location">
                                <i class="fas fa-map-marker-alt"></i>
                                {{ $property->city }}, {{ $property->country }}
                            </p>
                        </div>
                        <div class="listing-meta">
                            <div class="listing-price">
                                <b>${{ number_format($property->price) }}</b>@if(($property->listing_type ?? null) === 'rent')/{{ $property->price_duration ?? 'month' }}@endif
                            </div>
                            <a href="{{ route('properties.show', $property) }}" class="view-btn" data-track-click="recommendations_page">View Details</a>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </section>
</main>
@endsection
