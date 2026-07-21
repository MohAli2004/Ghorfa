@extends('layouts.app')
@section('title', $property->title)

@push('styles')
<link rel="stylesheet" href="{{ asset('css/show.css') }}">
<link rel="stylesheet" href="{{ asset('css/transaction-request.css') }}">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
@endpush

@push('scripts')
<script src="{{ asset('js/show.js') }}" defer></script>
<script src="{{ asset('js/recommendations.js') }}" defer></script>
@endpush

@section('content')
<main class="property-show-page">
    <!-- Property Details Section -->
    <section class="property-details">
        <div class="container">
            <div class="property-layout">
                <div class="property-header">
                    <div class="property-title-section">
                        <h1 class="property-title">{{ $property->title }}</h1>
                        <div class="property-location">
                            <i class="fas fa-map-marker-alt"></i>
                            <span>{{ $property->address }}, {{ $property->city }}, {{ $property->country }}</span>
                        </div>
                    </div>
                    <div class="property-price-section">
                        <div class="price-main">${{ number_format($property->price) }}</div>
                        @if(($property->listing_type ?? null) === 'rent')
                            <div class="price-period">per {{ $property->price_duration ?? 'month' }}</div>
                        @endif
                    </div>
                </div>

                <!-- Gallery -->
                <div class="property-gallery-column">
                    @if($property->images->count() > 0)
                    <div class="property-gallery-preview" id="propertyGalleryPreview">
                        <div class="gallery-main-view">
                            @if($property->images->count() > 1)
                            <button type="button" class="gallery-nav gallery-nav--prev" id="galleryPrev" aria-label="Previous photo">
                                <i class="fas fa-chevron-left" aria-hidden="true"></i>
                            </button>
                            @endif
                            <button type="button" class="gallery-main-frame" id="galleryMainFrame" aria-label="View photo full size">
                                @php $firstImgUrl = Storage::url($property->images->first()->path); @endphp
                                <img
                                    id="galleryMainImage"
                                    src="{{ $firstImgUrl }}"
                                    alt="{{ $property->title }} — photo 1"
                                    class="gallery-image gallery-main-image"
                                    data-image-src="{{ $firstImgUrl }}"
                                >
                            </button>
                            @if($property->images->count() > 1)
                            <button type="button" class="gallery-nav gallery-nav--next" id="galleryNext" aria-label="Next photo">
                                <i class="fas fa-chevron-right" aria-hidden="true"></i>
                            </button>
                            @endif
                        </div>

                        @if($property->images->count() > 1)
                        <div class="gallery-thumbs" id="galleryThumbs" role="tablist" aria-label="Property photo thumbnails">
                            @foreach($property->images as $image)
                                @php $imgUrl = Storage::url($image->path); @endphp
                                <button
                                    type="button"
                                    class="gallery-thumb{{ $loop->first ? ' is-active' : '' }}"
                                    data-gallery-index="{{ $loop->index }}"
                                    data-image-src="{{ $imgUrl }}"
                                    role="tab"
                                    aria-selected="{{ $loop->first ? 'true' : 'false' }}"
                                    aria-label="Photo {{ $loop->iteration }}"
                                >
                                    <img src="{{ $imgUrl }}" alt="" loading="{{ $loop->index < 5 ? 'eager' : 'lazy' }}" decoding="async">
                                </button>
                            @endforeach
                        </div>
                        @endif
                    </div>
                    @else
                    <div class="property-gallery-preview property-gallery-preview--empty">
                        <div class="no-image-placeholder">
                            <i class="fas fa-image" aria-hidden="true"></i>
                            <p>No photos available</p>
                        </div>
                    </div>
                    @endif
                </div>

                <!-- Sidebar -->
                <div class="property-sidebar">
                    @php
                        $owner = $property->landlord;
                        $ownerInitial = $owner ? mb_strtoupper(mb_substr($owner->name, 0, 1)) : '?';
                        $ownerPhone = $owner?->phone_nb;
                        $whatsappPhone = $ownerPhone ? preg_replace('/\D+/', '', $ownerPhone) : null;
                        $propertyUrl = route('properties.show', $property);
                        $whatsappMessage = sprintf(
                            "Hi! I'm interested in \"%s\" in %s (Property #%d) on Ghorfa. Is it still available?\n\n%s",
                            $property->title,
                            $property->city,
                            $property->id,
                            $propertyUrl
                        );
                        $whatsappUrl = $whatsappPhone
                            ? 'https://wa.me/' . $whatsappPhone . '?text=' . rawurlencode($whatsappMessage)
                            : null;
                    @endphp
                    <div class="contact-card">
                        <div class="contact-header">
                            <h4>Contact Me</h4>
                        </div>
                        @if($owner)
                        <div class="contact-owner">
                            <div class="contact-owner__avatar" aria-hidden="true">{{ $ownerInitial }}</div>
                            <div class="contact-owner__info">
                                <span class="contact-owner__name">{{ $owner->name }}</span>
                                <span class="contact-owner__role">Owner</span>
                            </div>
                        </div>
                        @endif
                        <div class="contact-actions contact-actions--row">
                            @if($ownerPhone)
                            <a href="tel:{{ preg_replace('/\s+/', '', $ownerPhone) }}" class="contact-btn contact-btn--call" data-track-contact="call" data-property-id="{{ $property->id }}">
                                Call
                            </a>
                            <a href="{{ $whatsappUrl }}" class="contact-btn contact-btn--whatsapp" target="_blank" rel="noopener noreferrer" data-track-contact="whatsapp" data-property-id="{{ $property->id }}">
                                WhatsApp
                            </a>
                            @else
                            <button type="button" class="contact-btn contact-btn--call" disabled>
                                Call
                            </button>
                            <button type="button" class="contact-btn contact-btn--whatsapp" disabled>
                                WhatsApp
                            </button>
                            @endif
                        </div>
                        <div class="property-meta">
                            <div class="meta-item">
                                <i class="fas fa-calendar"></i>
                                <span>Listed {{ $property->created_at->diffForHumans() }}</span>
                            </div>
                            <div class="meta-item">
                                <i class="fas fa-eye"></i>
                                <span>Property ID: #{{ $property->id }}</span>
                            </div>
                        </div>
                    </div>

                    @auth
                    <div class="like-section">
                        <button
                            type="button"
                            class="like-btn"
                            data-property-id="{{ $property->id }}"
                            data-liked="{{ $property->isLikedBy(auth()->id()) ? 'true' : 'false' }}"
                            aria-pressed="{{ $property->isLikedBy(auth()->id()) ? 'true' : 'false' }}"
                        >
                            <i class="fa-{{ $property->isLikedBy(auth()->id()) ? 'solid' : 'regular' }} fa-heart"></i>
                            <span>{{ $property->isLikedBy(auth()->id()) ? 'Liked' : 'Like' }}</span>
                        </button>
                        <div class="like-count">{{ $property->likedBy()->count() }} likes</div>

                        <hr class="like-divider">

                        @php
                            $userHasReviewed = auth()->check() ? $property->hasUserReviewed(auth()->id()) : false;
                            $userReview = auth()->check() ? $property->getUserReview(auth()->id()) : null;
                        @endphp

                        <div class="rating-summary">
                            <div class="rating-stars">
                                @for ($i = 1; $i <= 5; $i++)
                                    <i class="fa-star {{ $i <= floor($avgRating) ? 'fas' : 'far' }}"></i>
                                @endfor
                            </div>
                            @if($reviewsCount > 0)
                                <span class="rating-text">{{ $avgRating }} / 5 · {{ $reviewsCount }} {{ \Illuminate\Support\Str::plural('review', $reviewsCount) }}</span>
                            @else
                                <span class="rating-text">No ratings yet</span>
                            @endif
                        </div>

                        @if($userHasReviewed && $userReview)
                            <div class="user-review-status">
                                <div class="user-review-info">
                                    <i class="fas fa-check-circle"></i>
                                    <span>You reviewed this property</span>
                                </div>
                                <button class="review-btn edit-review-btn" data-review-id="{{ $userReview->id }}" onclick="editUserReview(this.dataset.reviewId)">
                                    <i class="fas fa-edit"></i>
                                    Edit Review
                                </button>
                            </div>
                        @else
                            <button class="review-btn" onclick="openReviewModal()">
                                <i class="fas fa-star-half-alt"></i>
                                Write a Review
                            </button>
                        @endif
                    </div>
                    @endauth
                </div>

                <!-- Main Content -->
                <div class="property-main">
                    <!-- Property Location Map -->
                    @if($property->latitude && $property->longitude)
                    <div class="property-location-map-container">
                        <div id="property-location-map"></div>
                    </div>
                    @endif

                    <!-- Property Features -->
                    <div class="property-features">
                        <div class="feature-item">
                            <i class="fas fa-home"></i>
                            <span>{{ $property->property_type }}</span>
                        </div>
                        @if($property->area_m3)
                        <div class="feature-item">
                            <i class="fas fa-ruler-combined"></i>
                            <span>{{ $property->area_m3 }} m²</span>
                        </div>
                        @endif
                        <div class="feature-item">
                            <i class="fas fa-door-open"></i>
                            <span>{{ $property->room_nb }} rooms</span>
                        </div>
                        @if($property->bedroom_nb)
                        <div class="feature-item">
                            <i class="fas fa-bed"></i>
                            <span>{{ $property->bedroom_nb }} bedrooms</span>
                        </div>
                        @endif
                        @if($property->bathroom_nb)
                        <div class="feature-item">
                            <i class="fas fa-bath"></i>
                            <span>{{ $property->bathroom_nb }} bathrooms</span>
                        </div>
                        @endif
                    </div>

                    <!-- Description -->
                    <div class="property-description">
                        <h3>Description</h3>
                        <p>{{ $property->description }}</p>
                    </div>

                    <!-- Reviews Section -->
                    <div class="reviews-section">
                        <div class="reviews-header">
                            <h3>Reviews & Ratings</h3>
                            @if($reviewsCount > 0)
                                <div class="reviews-summary">
                                    <div class="overall-rating">
                                        <div class="rating-number">{{ $avgRating }}</div>
                                        <div class="rating-stars-large">
                                            @for ($i = 1; $i <= 5; $i++)
                                                <i class="fa-star {{ $i <= floor($avgRating) ? 'fas' : ($i <= $avgRating ? 'fas half-star' : 'far') }}"></i>
                                            @endfor
                                        </div>
                                        <div class="rating-count">{{ $reviewsCount }} {{ \Illuminate\Support\Str::plural('review', $reviewsCount) }}</div>
                                    </div>
                                </div>
                            @endif
                        </div>

                        @if($reviewsCount > 0)
                            <div class="reviews-list">
                                @foreach($property->reviews()->with('user')->latest()->take(5)->get() as $review)
                                    <div class="review-item">
                                        <div class="review-header">
                                            <div class="reviewer-info">
                                                <div class="reviewer-avatar">
                                                    @if($review->user->profile_image)
                                                        <img src="{{ Storage::url($review->user->profile_image) }}" alt="{{ $review->user->name }}" class="avatar-image">
                                                    @else
                                                        <i class="fas fa-user"></i>
                                                    @endif
                                                </div>
                                                <div class="reviewer-details">
                                                    <div class="reviewer-name">{{ $review->user->name }}</div>
                                                    <div class="review-date">{{ $review->created_at->format('M d, Y') }}</div>
                                                </div>
                                            </div>
                                            <div class="review-rating">
                                                @for ($i = 1; $i <= 5; $i++)
                                                    <i class="fa-star {{ $i <= $review->rating ? 'fas' : 'far' }}"></i>
                                                @endfor
                                            </div>
                                        </div>
                                        <div class="review-content">
                                            <p>{{ $review->comment }}</p>
                                        </div>
                                    </div>
                                @endforeach
                                
                                @if($reviewsCount > 5)
                                    <div class="view-all-reviews">
                                        <button class="view-all-btn" onclick="showAllReviews()">
                                            View all {{ $reviewsCount }} reviews
                                            <i class="fas fa-chevron-down"></i>
                                        </button>
                                    </div>
                                @endif
                            </div>
                        @else
                            <div class="no-reviews">
                                <div class="no-reviews-icon">
                                    <i class="fas fa-star"></i>
                                </div>
                                <h4>No reviews yet</h4>
                                <p>Be the first to share your experience with this property!</p>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Transaction Request Section -->
    @auth
    @if(auth()->id() !== $property->user_id && auth()->user()->role !== 'admin' && $property->landlord)
    <section class="transaction-request-section">
        <div class="container">
            <div class="request-card">
                @if($property->isSold())
                <div class="request-header">
                    <h2>Not available for new requests</h2>
                    <p class="text-muted">{{ $property->getAvailabilityMessage() }}</p>
                </div>
                @else
                <div class="request-card-content">
                    <div class="request-character">
                        <img src="{{ asset('images/character/view-thumbs-up.png') }}" alt="Great choice!" class="request-helper-character">
                    </div>
                    <div class="request-info">
                        <div class="request-header">
                            <h2>Interested in this property?</h2>
                            <p>Submit a request to {{ $property->landlord->name }}</p>
                            @if(strtolower($property->listing_type ?? '') === 'rent' && $property->rentedUntil())
                            @php $activeRental = $property->getActiveRentalDateRange(); @endphp
                            <p class="request-availability-hint">
                                <i class="fas fa-info-circle"></i>
                                Available from <strong>{{ $property->getMinRentalStartDate() }}</strong>.
                                @if($activeRental)
                                Current booking: {{ $activeRental['start']->format('M j, Y') }} – {{ $activeRental['end']->format('M j, Y') }} (these dates are not available).
                                @endif
                            </p>
                            @endif
                        </div>
                        <div class="request-buttons">
                            @if(strtolower($property->listing_type ?? '') === 'rent')
                            <button class="btn btn-primary" onclick="openRentalRequestModal()">
                                <i class="fas fa-calendar-check"></i>
                                Request to Rent
                            </button>
                            @elseif(strtolower($property->listing_type ?? '') === 'sale')
                            <button class="btn btn-primary" onclick="openPurchaseRequestModal()">
                                <i class="fas fa-handshake"></i>
                                Request to Buy
                            </button>
                            @endif
                        </div>
                    </div>
                </div>
                @endif
            </div>
        </div>
    </section>
    @endif
    @endauth

    {{-- Guest Transaction Request Section --}}
    @guest
    @if($property->landlord && !$property->isSold())
    <section class="transaction-request-section">
        <div class="container">
            <div class="request-card">
                <div class="request-card-content">
                    <div class="request-character">
                        <img src="{{ asset('images/character/view-thumbs-up.png') }}" alt="Great choice!" class="request-helper-character">
                    </div>
                    <div class="request-info">
                        <div class="request-header">
                            <h2>Interested in this property?</h2>
                            <p>Login to submit a request to {{ $property->landlord->name }}</p>
                        </div>
                        <div class="request-buttons">
                            <a href="{{ route('login') }}" class="btn btn-primary">
                                <i class="fas fa-sign-in-alt"></i>
                                Login to Request
                            </a>
                            <a href="{{ route('register') }}" class="btn btn-secondary">
                                <i class="fas fa-user-plus"></i>
                                Register
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
    @endif
    @endguest

    <!-- Action Buttons -->
    <div class="property-actions">
        <div class="container">
            <div class="property-actions-bar">
                <button type="button" class="property-action-btn property-action-btn--back back-btn">
                    <i class="fas fa-arrow-left" aria-hidden="true"></i>
                    <span>Back</span>
                </button>

                @auth
                    @if(auth()->id() === $property->user_id || auth()->user()->role === 'admin')
                    <div class="property-actions-bar__owner">
                        @if(auth()->id() === $property->user_id)
                        <a href="{{ route('properties.edit', $property) }}" class="property-action-btn property-action-btn--edit edit-btn">
                            <i class="fas fa-edit" aria-hidden="true"></i>
                            <span>Edit Property</span>
                        </a>
                        @endif
                        <form class="property-actions-delete-form delete-form" action="{{ route('properties.destroy', $property->id) }}" method="POST">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="property-action-btn property-action-btn--delete delete-btn" onclick="return confirm('Are you sure you want to delete this property?')">
                                <i class="fas fa-trash" aria-hidden="true"></i>
                                <span>Delete Property</span>
                            </button>
                        </form>
                    </div>
                    @endif
                @endauth
            </div>
        </div>
    </div>
</main>

<!-- Image Modal -->
<div id="imageModal" class="image-modal" onclick="closeImageModal()">
    <div class="modal-content" onclick="event.stopPropagation()">
        <span class="close-btn" onclick="closeImageModal()">&times;</span>
        
        <!-- Navigation Arrows -->
        <button class="nav-arrow nav-arrow-left" onclick="previousImage()" id="prevBtn">
            <i class="fas fa-chevron-left"></i>
        </button>
        <button class="nav-arrow nav-arrow-right" onclick="nextImage()" id="nextBtn">
            <i class="fas fa-chevron-right"></i>
        </button>
        
        <!-- Image Container -->
        <div class="image-container">
            <img id="modalImage" src="" alt="Property Image">
        </div>
        
        <!-- Image Counter -->
        <div class="image-counter">
            <span id="currentImageIndex">1</span> / <span id="totalImages">1</span>
        </div>
        
        <!-- Thumbnail Strip -->
        <div class="thumbnail-strip" id="thumbnailStrip">
            <!-- Thumbnails will be populated by JavaScript -->
        </div>
    </div>
</div>
<!-- Review Modal -->
<div id="reviewModal" class="image-modal review-modal" onclick="closeReviewModal()">
    <div class="modal-content modal-card" onclick="event.stopPropagation()">
        <span class="close-btn" onclick="closeReviewModal()">&times;</span>

        <div class="review-modal-header">
            <h3>Rate this property</h3>
            <p class="review-subtitle">Share your experience to help others</p>
        </div>

        @if(session('success'))
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                {{ session('success') }}
            </div>
        @endif

        @if(session('error'))
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i>
                {{ session('error') }}
            </div>
        @endif

        <form method="POST" action="{{ route('reviews.store', $property) }}" class="review-form">
            @csrf

            <div class="form-row">
                <label for="rating">Your Rating</label>
                <div class="star-rating-container">
                    <div class="star-rating" id="starRating">
                        @for($i = 5; $i >= 1; $i--)
                            <input type="radio" id="star{{ $i }}" name="rating" value="{{ $i }}" {{ old('rating') == $i ? 'checked' : '' }}>
                            <label for="star{{ $i }}" class="star-label">
                                <i class="fas fa-star"></i>
                            </label>
                        @endfor
                    </div>
                    <div class="rating-text-display" id="ratingText">Select a rating</div>
                </div>
                @error('rating') <div class="form-error">{{ $message }}</div> @enderror
            </div>

            <div class="form-row">
                <label for="comment">Your Review</label>
                <textarea id="comment" name="comment" rows="6" placeholder="Tell others about your experience with this property..." required>{{ old('comment') }}</textarea>
                <div class="char-counter">
                    <span id="charCount">0</span>/1000 characters
                </div>
                @error('comment') <div class="form-error">{{ $message }}</div> @enderror
            </div>

            <div class="review-actions">
                <button type="submit" class="submit-btn" id="submitReviewBtn">
                    <i class="fas fa-paper-plane"></i> Submit Review
                </button>
                <button type="button" class="cancel-btn" onclick="closeReviewModal()">Cancel</button>
            </div>
        </form>
    </div>
</div>

{{-- Google Maps API for Property Location Map --}}
@if($property->latitude && $property->longitude)
<script async src="https://maps.googleapis.com/maps/api/js?key={{ config('services.google.maps_browser_key') }}&callback=initPropertyShowMap&libraries=places"></script>
<script>
function initPropertyShowMap() {
    const mapElement = document.getElementById('property-location-map');
    
    if (!mapElement) {
        return;
    }

    const propertyLat = parseFloat(@json($property->latitude));
    const propertyLng = parseFloat(@json($property->longitude));
    const propertyTitle = @json($property->title);
    const propertyAddress = @json($property->address);
    const propertyCity = @json($property->city);
    const propertyCountry = @json($property->country);
    const propertyPrice = @json(number_format($property->price));
    const unitSymbol = @json($property->unit ? $property->unit->symbol : '');

    // Initialize map centered on property location
    const propertyMap = new google.maps.Map(mapElement, {
        center: { lat: propertyLat, lng: propertyLng },
        zoom: 15,
        mapTypeId: 'roadmap',
        styles: [
            {
                featureType: 'poi',
                elementType: 'labels',
                stylers: [{ visibility: 'off' }]
            }
        ]
    });

    // Add marker for property location
    const propertyMarker = new google.maps.Marker({
        position: { lat: propertyLat, lng: propertyLng },
        map: propertyMap,
        title: propertyTitle,
        icon: {
            url: 'data:image/svg+xml;charset=UTF-8,' + encodeURIComponent(
                '<svg width="40" height="40" viewBox="0 0 40 40" xmlns="http://www.w3.org/2000/svg">' +
                '<circle cx="20" cy="20" r="18" fill="#ef4444" stroke="white" stroke-width="3"/>' +
                '<text x="20" y="26" text-anchor="middle" fill="white" font-family="Arial" font-size="18" font-weight="bold">📍</text>' +
                '</svg>'
            ),
            scaledSize: new google.maps.Size(40, 40),
            anchor: new google.maps.Point(20, 20)
        },
        animation: google.maps.Animation.DROP
    });

    // Create info window with property details
    const infoWindow = new google.maps.InfoWindow({
        content: '<div style="padding: 10px; min-width: 200px;">' +
            '<h3 style="margin: 0 0 8px 0; font-size: 16px; font-weight: bold; color: #1a1a1a;">' + propertyTitle + '</h3>' +
            '<div style="margin-bottom: 8px; color: #64748b; font-size: 14px;">' +
            '<i class="fas fa-map-marker-alt" style="color: #ef4444;"></i> ' +
            propertyAddress + ', ' + propertyCity + ', ' + propertyCountry +
            '</div>' +
            '<div style="color: #1a1a1a; font-size: 16px; font-weight: 600;">$' + propertyPrice + ' ' + unitSymbol + '</div>' +
            '</div>'
    });

    // Open info window on marker click
    propertyMarker.addListener('click', function() {
        infoWindow.open(propertyMap, propertyMarker);
    });

    // Open info window by default
    infoWindow.open(propertyMap, propertyMarker);
}
</script>
@endif

<script>
    function openReviewModal(){ document.getElementById('reviewModal').style.display = 'flex'; }
    function closeReviewModal(){ document.getElementById('reviewModal').style.display = 'none'; }
    window.addEventListener('keydown', function(e){ if(e.key === 'Escape') closeReviewModal(); });

    // Transaction request modals
    function openRentalRequestModal() {
        @auth
            document.getElementById('rentalRequestModal').style.display = 'flex';
            var startInput = document.getElementById('start_date');
            var endInput = document.getElementById('end_date');
            var minStart = startInput ? (startInput.getAttribute('data-min-date') || startInput.getAttribute('min')) : null;
            if (startInput && endInput && minStart) {
                startInput.setAttribute('min', minStart);
                endInput.setAttribute('min', minStart);
            }
            if (startInput && endInput && startInput.value && startInput.value > endInput.value) {
                endInput.value = startInput.value;
                endInput.setAttribute('min', startInput.value);
            }
            var rulesAccepted = document.getElementById('rules_accepted');
            var rulesExceptionsGroup = document.getElementById('rules_exceptions_group');
            var rulesExceptionsInput = document.getElementById('rules_exceptions');
            if (rulesAccepted && rulesExceptionsGroup && rulesExceptionsInput) {
                if (rulesAccepted.checked) {
                    rulesExceptionsGroup.classList.remove('is-visible');
                    rulesExceptionsInput.removeAttribute('required');
                } else {
                    rulesExceptionsGroup.classList.add('is-visible');
                    rulesExceptionsInput.setAttribute('required', 'required');
                }
            }
            if (typeof updateRentalPriceEstimate === 'function') {
                updateRentalPriceEstimate();
            }
        @else
            window.location.href = '{{ route("login") }}';
        @endauth
    }

    document.addEventListener('DOMContentLoaded', function() {
        var startInput = document.getElementById('start_date');
        var endInput = document.getElementById('end_date');
        if (startInput && endInput) {
            startInput.addEventListener('change', function() {
                endInput.setAttribute('min', this.value);
                if (endInput.value && endInput.value < this.value) {
                    endInput.value = this.value;
                }
                updateRentalPriceEstimate();
            });
            endInput.addEventListener('change', updateRentalPriceEstimate);
        }
        var rulesAccepted = document.getElementById('rules_accepted');
        var rulesExceptionsGroup = document.getElementById('rules_exceptions_group');
        var rulesExceptionsInput = document.getElementById('rules_exceptions');
        function toggleRulesExceptions() {
            if (!rulesAccepted || !rulesExceptionsGroup || !rulesExceptionsInput) return;
            if (rulesAccepted.checked) {
                rulesExceptionsGroup.classList.remove('is-visible');
                rulesExceptionsInput.removeAttribute('required');
                rulesExceptionsInput.value = '';
            } else {
                rulesExceptionsGroup.classList.add('is-visible');
                rulesExceptionsInput.setAttribute('required', 'required');
            }
        }
        if (rulesAccepted) {
            rulesAccepted.addEventListener('change', toggleRulesExceptions);
            toggleRulesExceptions();
        }
        updateRentalPriceEstimate();
    });

    function rentalUnitsFromPrimary(primary) {
        if (primary === 'day') return ['day'];
        if (primary === 'week') return ['week', 'day'];
        if (primary === 'month') return ['month', 'week', 'day'];
        return ['year', 'month', 'week', 'day'];
    }

    function getAllowedRentalUnits(box) {
        try {
            var parsed = JSON.parse(box.getAttribute('data-allowed-units') || '[]');
            if (Array.isArray(parsed) && parsed.length) {
                return parsed;
            }
        } catch (e) {}
        return rentalUnitsFromPrimary(box.getAttribute('data-primary-unit') || 'month');
    }

    function addCalendarUnit(date, unit) {
        var next = new Date(date.getTime());
        if (unit === 'year') next.setFullYear(next.getFullYear() + 1);
        else if (unit === 'month') next.setMonth(next.getMonth() + 1);
        else if (unit === 'week') next.setDate(next.getDate() + 7);
        else next.setDate(next.getDate() + 1);
        return next;
    }

    function decomposeRentalStay(start, end, allowed) {
        var counts = { year: 0, month: 0, week: 0, day: 0 };
        var cursor = new Date(start.getFullYear(), start.getMonth(), start.getDate());
        var endDay = new Date(end.getFullYear(), end.getMonth(), end.getDate());
        ['year', 'month', 'week'].forEach(function(unit) {
            if (allowed.indexOf(unit) === -1) return;
            while (true) {
                var next = addCalendarUnit(cursor, unit);
                if (next > endDay) break;
                cursor = next;
                counts[unit] += 1;
            }
        });
        var leftoverDays = Math.max(0, Math.round((endDay - cursor) / 86400000));
        if (allowed.indexOf('day') !== -1) {
            counts.day = leftoverDays;
            leftoverDays = 0;
        }
        return { counts: counts, leftoverDays: leftoverDays };
    }

    function formatMoney(amount) {
        return '$' + Number(amount).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function updateRentalPriceEstimate() {
        var box = document.getElementById('rentalPriceEstimate');
        var startInput = document.getElementById('start_date');
        var endInput = document.getElementById('end_date');
        var totalEl = document.getElementById('rentalPriceTotal');
        var periodEl = document.getElementById('rentalPricePeriod');
        var linesEl = document.getElementById('rentalPriceLines');
        if (!box || !startInput || !endInput || !totalEl || !periodEl || !linesEl) return;

        if (!startInput.value || !endInput.value || endInput.value <= startInput.value) {
            box.hidden = true;
            return;
        }

        var start = new Date(startInput.value + 'T00:00:00');
        var end = new Date(endInput.value + 'T00:00:00');
        var allowed = getAllowedRentalUnits(box);
        var rates = {
            day: parseFloat(box.getAttribute('data-rate-day') || '0'),
            week: parseFloat(box.getAttribute('data-rate-week') || '0'),
            month: parseFloat(box.getAttribute('data-rate-month') || '0'),
            year: parseFloat(box.getAttribute('data-rate-year') || '0')
        };
        var decomposed = decomposeRentalStay(start, end, allowed);
        var counts = decomposed.counts;
        var nights = Math.round((end - start) / 86400000);

        if (decomposed.leftoverDays > 0) {
            totalEl.textContent = '—';
            periodEl.textContent = 'These dates do not fit accepted durations (' + allowed.join(', ') + ').';
            linesEl.innerHTML = '';
            box.hidden = false;
            return;
        }

        var total = 0;
        var lines = [];
        var periodParts = [];

        ['year', 'month', 'week', 'day'].forEach(function(unit) {
            var count = counts[unit] || 0;
            if (count <= 0) return;
            var rate = rates[unit] || 0;
            var subtotal = Math.round(count * rate * 100) / 100;
            total += subtotal;
            var label = unit + (count === 1 ? '' : 's');
            periodParts.push(count + ' ' + label);
            lines.push(
                '<li><span>' + count + ' ' + label + ' × ' + formatMoney(rate) + '</span><strong>' + formatMoney(subtotal) + '</strong></li>'
            );
        });

        totalEl.textContent = formatMoney(Math.round(total * 100) / 100);
        periodEl.textContent = nights + ' night' + (nights === 1 ? '' : 's') + ' · ' + (periodParts.join(' + ') || '0 days');
        linesEl.innerHTML = lines.join('');
        box.hidden = false;
    }

    function closeRentalRequestModal() {
        document.getElementById('rentalRequestModal').style.display = 'none';
    }

    function openPurchaseRequestModal() {
        @auth
            document.getElementById('purchaseRequestModal').style.display = 'flex';
        @else
            window.location.href = '{{ route("login") }}';
        @endauth
    }

    function closePurchaseRequestModal() {
        document.getElementById('purchaseRequestModal').style.display = 'none';
    }

    window.addEventListener('keydown', function(e){
        if(e.key === 'Escape') {
            closeRentalRequestModal();
            closePurchaseRequestModal();
        }
    });

    function submitRentalRequest() {
        const form = document.getElementById('rentalRequestForm');
        var startDate = document.getElementById('start_date');
        var endDate = document.getElementById('end_date');
        if (startDate && endDate) {
            if (!startDate.value.trim()) {
                alert('Please select a check-in date.');
                return;
            }
            if (!endDate.value.trim()) {
                alert('Please select a check-out date so the rental period is clear. The property cannot be rented for an unknown duration.');
                return;
            }
            if (endDate.value <= startDate.value) {
                alert('Check-out date must be after check-in date.');
                return;
            }

            var estimateBox = document.getElementById('rentalPriceEstimate');
            if (estimateBox) {
                var allowed = getAllowedRentalUnits(estimateBox);
                var start = new Date(startDate.value + 'T00:00:00');
                var end = new Date(endDate.value + 'T00:00:00');
                var decomposed = decomposeRentalStay(start, end, allowed);
                if (decomposed.leftoverDays > 0) {
                    alert(
                        'These dates do not fit the landlord\'s accepted rent durations (' +
                        allowed.join(', ') +
                        '). Please choose dates that match whole accepted units only.'
                    );
                    return;
                }
            }
        }
        var rulesAccepted = document.getElementById('rules_accepted');
        var rulesExceptions = document.getElementById('rules_exceptions');
        if (rulesAccepted && rulesExceptions) {
            if (!rulesAccepted.checked && !rulesExceptions.value.trim()) {
                alert('Please either accept all property rules, or explain which rules you do not accept.');
                return;
            }
        }
        const formData = new FormData(form);
        
        fetch('{{ route("transactions.store") }}', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Accept': 'application/json'
            },
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.message) {
                alert('Request submitted successfully! Waiting for landlord approval.');
                closeRentalRequestModal();
                location.reload();
            } else if (data.errors) {
                const errorMessage = Object.values(data.errors).flat().join('\n');
                alert('Error: ' + errorMessage);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred while submitting your request.');
        });
    }

    function submitPurchaseRequest() {
        const form = document.getElementById('purchaseRequestForm');
        const formData = new FormData(form);
        
        fetch('{{ route("transactions.store") }}', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Accept': 'application/json'
            },
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.message) {
                alert('Request submitted successfully! Waiting for landlord approval.');
                closePurchaseRequestModal();
                location.reload();
            } else if (data.errors) {
                const errorMessage = Object.values(data.errors).flat().join('\n');
                alert('Error: ' + errorMessage);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred while submitting your request.');
        });
    }
</script>

<!-- Rental Request Modal -->
@auth
<div id="rentalRequestModal" class="trxn-modal-overlay" role="dialog" aria-modal="true" aria-labelledby="rentalModalTitle" onclick="if(event.target===this)closeRentalRequestModal()">
    <div class="trxn-modal" onclick="event.stopPropagation()">
        <header class="trxn-modal__header trxn-modal__header--rent">
            <div class="trxn-modal__header-icon">
                <i class="fas fa-key" aria-hidden="true"></i>
            </div>
            <div class="trxn-modal__header-text">
                <h2 class="trxn-modal__title" id="rentalModalTitle">Request to Rent</h2>
                <p class="trxn-modal__subtitle">{{ $property->title }}</p>
            </div>
            <button class="trxn-modal__close" onclick="closeRentalRequestModal()" aria-label="Close modal" type="button">
                <i class="fas fa-times" aria-hidden="true"></i>
            </button>
        </header>

        <div class="trxn-modal__body">
            <form id="rentalRequestForm">
                @csrf
                <input type="hidden" name="property_id" value="{{ $property->id }}">
                <input type="hidden" name="type" value="rent">

                <div class="trxn-modal__dates-row">
                    <div class="trxn-field">
                        <label class="trxn-field__label" for="start_date">
                            <i class="fas fa-calendar-alt" aria-hidden="true"></i>
                            Check-in date <span class="trxn-required" aria-hidden="true">*</span>
                        </label>
                        <input class="trxn-field__input" type="date" id="start_date" name="start_date" required
                            min="{{ $property->getMinRentalStartDate() }}"
                            data-min-date="{{ $property->getMinRentalStartDate() }}">
                    </div>
                    <div class="trxn-field">
                        <label class="trxn-field__label" for="end_date">
                            <i class="fas fa-calendar-check" aria-hidden="true"></i>
                            Check-out date <span class="trxn-required" aria-hidden="true">*</span>
                        </label>
                        <input class="trxn-field__input" type="date" id="end_date" name="end_date" required
                            min="{{ $property->getMinRentalStartDate() }}">
                        <span class="trxn-field__hint">Both dates define your rental period.</span>
                    </div>
                </div>

                @php
                    $rentalCalculator = app(\App\Services\RentalPriceCalculator::class);
                    $rentalRates = $rentalCalculator->ratesFor($property);
                    $rentalAllowedUnits = $rentalCalculator->acceptedUnits($property);
                    $rentalPrimaryUnit = $property->price_duration ?? 'month';
                @endphp
                <div class="trxn-price-estimate" id="rentalPriceEstimate" hidden
                    data-primary-unit="{{ $rentalPrimaryUnit }}"
                    data-allowed-units='@json($rentalAllowedUnits)'
                    data-rate-day="{{ $rentalRates['day'] }}"
                    data-rate-week="{{ $rentalRates['week'] }}"
                    data-rate-month="{{ $rentalRates['month'] }}"
                    data-rate-year="{{ $rentalRates['year'] }}">
                    <div class="trxn-price-estimate__head">
                        <span class="trxn-price-estimate__title">
                            <i class="fas fa-calculator" aria-hidden="true"></i>
                            Estimated total
                        </span>
                        <strong class="trxn-price-estimate__total" id="rentalPriceTotal">$0.00</strong>
                    </div>
                    <p class="trxn-price-estimate__period" id="rentalPricePeriod"></p>
                    <ul class="trxn-price-estimate__lines" id="rentalPriceLines"></ul>
                    <p class="trxn-price-estimate__hint">
                        Accepted durations:
                        {{ implode(', ', $rentalAllowedUnits) }}
                    </p>
                </div>

                <div class="trxn-field">
                    <label class="trxn-field__label" for="rules_accepted">
                        <i class="fas fa-clipboard-check" aria-hidden="true"></i>
                        Property rules <span class="trxn-required" aria-hidden="true">*</span>
                    </label>
                    <label class="trxn-checkbox">
                        <input type="checkbox" id="rules_accepted" name="rules_accepted" value="1">
                        <span class="trxn-checkbox__box" aria-hidden="true"></span>
                        <span class="trxn-checkbox__text">I have read and accept all property rules</span>
                    </label>
                </div>

                <div class="trxn-field trxn-field--collapsible" id="rules_exceptions_group">
                    <label class="trxn-field__label trxn-field__label--warning" for="rules_exceptions">
                        <i class="fas fa-exclamation-circle" aria-hidden="true"></i>
                        Which rules do you not accept? <span class="trxn-required" aria-hidden="true">*</span>
                    </label>
                    <textarea class="trxn-field__input trxn-field__textarea" id="rules_exceptions" name="rules_exceptions"
                        rows="3" placeholder="Describe any rules you have concerns about…"></textarea>
                    <span class="trxn-field__hint">This is required when you have not accepted all rules.</span>
                </div>

                <div class="trxn-field">
                    <label class="trxn-field__label" for="notes">
                        <i class="fas fa-comment-alt" aria-hidden="true"></i>
                        Additional notes <span class="trxn-optional">(optional)</span>
                    </label>
                    <textarea class="trxn-field__input trxn-field__textarea" id="notes" name="notes"
                        rows="3" placeholder="Tell the landlord a little about yourself…"></textarea>
                </div>

                <footer class="trxn-modal__footer">
                    <button type="button" class="trxn-btn trxn-btn--ghost" onclick="closeRentalRequestModal()">Cancel</button>
                    <button type="button" class="trxn-btn trxn-btn--primary" onclick="submitRentalRequest()">
                        <i class="fas fa-paper-plane" aria-hidden="true"></i> Send request
                    </button>
                </footer>
            </form>
        </div>
    </div>
</div>
@endauth

<!-- Purchase Request Modal -->
@auth
<div id="purchaseRequestModal" class="trxn-modal-overlay" role="dialog" aria-modal="true" aria-labelledby="purchaseModalTitle" onclick="if(event.target===this)closePurchaseRequestModal()">
    <div class="trxn-modal" onclick="event.stopPropagation()">
        <header class="trxn-modal__header trxn-modal__header--buy">
            <div class="trxn-modal__header-icon">
                <i class="fas fa-handshake" aria-hidden="true"></i>
            </div>
            <div class="trxn-modal__header-text">
                <h2 class="trxn-modal__title" id="purchaseModalTitle">Request to Buy</h2>
                <p class="trxn-modal__subtitle">{{ $property->title }}</p>
            </div>
            <button class="trxn-modal__close" onclick="closePurchaseRequestModal()" aria-label="Close modal" type="button">
                <i class="fas fa-times" aria-hidden="true"></i>
            </button>
        </header>

        <div class="trxn-modal__body">
            <div class="trxn-info-banner">
                <i class="fas fa-info-circle" aria-hidden="true"></i>
                <span>After submission the landlord will review your request and generate a contract for you to approve.</span>
            </div>

            <form id="purchaseRequestForm" action="{{ route('transactions.store') }}" method="POST">
                @csrf
                <input type="hidden" name="property_id" value="{{ $property->id }}">
                <input type="hidden" name="type" value="buy">

                <div class="trxn-field">
                    <label class="trxn-field__label" for="purchase_notes">
                        <i class="fas fa-comment-alt" aria-hidden="true"></i>
                        Message to landlord <span class="trxn-optional">(optional)</span>
                    </label>
                    <textarea class="trxn-field__input trxn-field__textarea" id="purchase_notes" name="notes"
                        rows="4" placeholder="Share your interest, questions, or anything you'd like the landlord to know…"></textarea>
                </div>

                <footer class="trxn-modal__footer">
                    <button type="button" class="trxn-btn trxn-btn--ghost" onclick="closePurchaseRequestModal()">Cancel</button>
                    <button type="submit" class="trxn-btn trxn-btn--primary">
                        <i class="fas fa-paper-plane" aria-hidden="true"></i> Send request
                    </button>
                </footer>
            </form>
        </div>
    </div>
</div>
@endauth

@endsection 
