{{-- Reusable property card grid for recommendation sections --}}
@if(isset($properties) && $properties->count() > 0)
<section class="recommended-properties-section">
    <div class="recommended-properties-header">
        <div>
            <h2>{{ $title ?? 'Recommended For You' }}</h2>
            @if(!empty($subtitle))
                <p>{{ $subtitle }}</p>
            @else
                <p>Properties you might like.</p>
            @endif
        </div>
        @if(empty($hideViewAll))
            <a href="{{ route('recommendations.index') }}" class="recommended-view-all">View all</a>
        @endif
    </div>

    <div class="listings-grid recommended-listings-grid">
        @foreach($properties as $property)
            <div class="listing-card" data-property-id="{{ $property->id }}">
                <div class="listing-image">
                    <a href="{{ route('properties.show', $property) }}" class="listing-image-link" data-track-click="recommended">
                        <img src="{{ \App\Services\PropertyImageService::getImageUrl($property) }}" alt="{{ $property->title }}">
                    </a>
                    <span class="listing-tag">For {{ $property->listing_type }}</span>
                    @if($property->getAvailabilityMessage())
                        <span class="listing-tag listing-tag--unavailable" title="{{ $property->getAvailabilityMessage() }}">{{ $property->getAvailabilityMessage() }}</span>
                    @endif
                </div>
                <div class="listing-content">
                    <span class="available-from">Listed {{ $property->created_at->diffForHumans() }}</span>
                    <h3><a href="{{ route('properties.show', $property) }}" data-track-click="recommended">{{ $property->title }}</a></h3>
                    <p class="listing-location">
                        <i class="fas fa-map-marker-alt"></i>
                        {{ $property->address }}, {{ $property->city }}
                    </p>
                    <div class="listing-features">
                        <span><i class="fas fa-home"></i> {{ $property->property_type }}</span>
                        @if($property->bedroom_nb)
                            <span><i class="fas fa-bed"></i> {{ $property->bedroom_nb }} Bedrooms</span>
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
                    <a href="{{ route('properties.show', $property) }}" class="view-btn" data-track-click="recommended">View Details</a>
                </div>
            </div>
        @endforeach
    </div>
</section>
@endif
