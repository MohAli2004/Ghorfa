@extends('layouts.app')
@section('title', 'Home Page')

@push('styles')
<link rel="stylesheet" href="{{ asset('css/home.css') }}">
<link rel="stylesheet" href="{{ asset('css/search.css') }}">
<link rel="stylesheet" href="{{ asset('css/recommendations.css') }}">
@endpush

@push('scripts')
<script src="{{ asset('js/recommendations.js') }}" defer></script>
@endpush

@section('content')

@auth
<main>
        <section id="Main-Container" class="Main-Container">
            <div class="Main-Container-content">
                <div class="hero-text-content">
                    <h1>Find Your Perfect Living Space</h1>
                    <p>Connect with roommates and discover affordable housing in destination cities</p>
                    <div class="search-container">
                        <form class="search-box" action="{{ route('filter-search') }}" method="GET">
                            <input type="text" name="location" class="search-input" placeholder="Enter city or neighborhood...">
                            <button type="submit" class="main-list-btn">Find Rooms</button>
                        </form>
                        <div class="popular-searches">
                            <span>Popular:</span>
                            @if(isset($popularCities) && $popularCities->count() > 0)
                                @foreach($popularCities as $city)
                                    <a href="{{ $city['url'] }}">{{ $city['name'] }}</a>
                                @endforeach
                            @else
                                <a href="{{ route('filter-search', ['location' => 'Beirut']) }}">Beirut</a>
                                <a href="{{ route('filter-search', ['location' => 'Saida']) }}">Saida</a>
                                <a href="{{ route('filter-search', ['location' => 'Tyre']) }}">Tyre</a>
                                <a href="{{ route('filter-search', ['location' => 'Baalbak']) }}">Baalbak</a>
                            @endif
                        </div>
                    </div>
                </div>
                <div class="hero-character-container">
                    <img src="{{ asset('images/character/home-hero.png') }}" alt="Welcome!" class="hero-character-image">
                </div>
            </div>
        </section>

        <section id="features">
            <h2>Why Choose Ghorfa?</h2>
            <div class="feature-grid">
               
                <div class="card">
                    <div class="feature-icon-character">
                        <img src="{{ asset('images/character/search-looking.png') }}" alt="Search" class="feature-character">
                    </div>
                    <h3>Wide Range of Listings</h3>
                    <p>Explore various rental options including rooms, apartments, and PG accommodations.</p>
                </div>
                
                <div class="card">
                    <div class="feature-icon-character">
                        <img src="{{ asset('images/character/search-magnifying.png') }}" alt="Filter" class="feature-character">
                    </div>
                    <h3>Advanced Filters</h3>
                    <p>Find exactly what you need with our detailed search filters.</p>
                </div>
                <div class="card">
                    <div class="feature-icon-character">
                        <img src="{{ asset('images/character/transaction.png') }}" alt="Transactions" class="feature-character">
                    </div>
                    <h3>Transactions Online</h3>
                    <p>Rent or buy through the site—request, and get a full transaction report.</p>
                </div>
                <div class="card">
                    <div class="feature-icon-character">
                        <img src="{{ asset('images/character/map-navigating.png') }}" alt="Map" class="feature-character">
                    </div>
                    <h3>Search By Map</h3>
                    <p>Quick and simple way to choose the perfect location.</p>
                </div>
            </div>
        </section>

        @if(isset($recommendedProperties) && $recommendedProperties->count() > 0)
            @include('partials.recommended-properties-grid', [
                'properties' => $recommendedProperties,
                'title' => 'Recommended For You',
                'subtitle' => 'Properties you might like.',
            ])
        @endif

        <section id="how-it-works" class="how-it-works">
            <h2>How It Works</h2>
            <div class="steps">
                <div class="card">
                    <p class="step-number">1</p>
                    <i class="fas fa-search"></i>
                    <h3>Search</h3>
                    <p>Browse through our extensive listing of rooms and apartments</p>
                </div>
                <div class="card">
                    <p class="step-number">2</p>
                    <i class="fas fa-comments"></i>
                    <h3>Connect</h3>
                    <p>Chat with property owners or potential roommates</p>
                </div>
                <div class="card">
                    <p class="step-number">3</p>
                    <i class="fas fa-key"></i>
                    <h3>Move In</h3>
                    <p>Book your space and move into your new home</p>
                </div>
            </div>
        </section>

      

        <section id="additional-section" class="additional-section">
            <div class="additional-section-content">
                <div class="additional-section-character">
                    <img src="{{ asset('images/character/success-celebrating.png') }}" alt="Join us!" class="cta-character">
                </div>
                <div class="additional-section-text">
                    <h2>Ready to Find Your Next Home?</h2>
                    <p>Join thousands of happy users who found their perfect space through Ghorfa</p>
                </div>   
                <div class="additional-section-buttons">
                    <button onclick="location.href='{{ route('search') }}'" class="main-list-btn">
                        <i class="fas fa-search"></i> Find a Room
                    </button>
                    @auth
                        @if(auth()->user()->isLandlord())
                        <button onclick="location.href='{{ route('list-property') }}'" class="secondary-list-btn">
                            <i class="fas fa-plus"></i> List Your Space
                        </button>
                        @endif
                    @endauth
                </div>
            </div>
        </section>
    </main>
    @endauth
    @guest
    <main>
        <section id="Main-Container" class="Main-Container">
            <div class="Main-Container-content">
                <div class="hero-text-content">
                    <h1>Find Your Perfect Living Space</h1>
                    <p>Connect with roommates and discover affordable housing in destination cities</p>
                    <div class="search-container">
                        <form class="search-box" action="{{ route('filter-search') }}" method="GET">
                            <input type="text" name="location" class="search-input" placeholder="Enter city or neighborhood...">
                            <button type="submit" class="main-list-btn">Find Rooms</button>
                        </form>
                        <div class="popular-searches">
                            <span>Popular:</span>
                            @if(isset($popularCities) && $popularCities->count() > 0)
                                @foreach($popularCities as $city)
                                    <a href="{{ $city['url'] }}">{{ $city['name'] }}</a>
                                @endforeach
                            @else
                                {{-- Fallback to default cities if no data available --}}
                                <a href="{{ route('filter-search', ['location' => 'Beirut']) }}">Beirut</a>
                                <a href="{{ route('filter-search', ['location' => 'Saida']) }}">Saida</a>
                                <a href="{{ route('filter-search', ['location' => 'Tyre']) }}">Tyre</a>
                                <a href="{{ route('filter-search', ['location' => 'Baalbak']) }}">Baalbak</a>
                            @endif
                        </div>
                    </div>
                </div>
                <div class="hero-character-container">
                    <img src="{{ asset('images/character/home-hero.png') }}" alt="Welcome!" class="hero-character-image">
                </div>
            </div>
        </section>

        <section id="features">
            <h2>Why Choose Ghorfa?</h2>
            <div class="feature-grid">
               
                <div class="card">
                    <div class="feature-icon-character">
                        <img src="{{ asset('images/character/search-looking.png') }}" alt="Search" class="feature-character">
                    </div>
                    <h3>Wide Range of Listings</h3>
                    <p>Explore various rental options including rooms, apartments, and PG accommodations.</p>
                </div>
                
                <div class="card">
                    <div class="feature-icon-character">
                        <img src="{{ asset('images/character/search-magnifying.png') }}" alt="Filter" class="feature-character">
                    </div>
                    <h3>Advanced Filters</h3>
                    <p>Find exactly what you need with our detailed search filters.</p>
                </div>
                <div class="card">
                    <div class="feature-icon-character">
                        <img src="{{ asset('images/character/transaction.png') }}" alt="Transactions" class="feature-character">
                    </div>
                    <h3>Transactions Online</h3>
                    <p>Rent or buy through the site—request, and get a full transaction report.</p>
                </div>
                
                <div class="card">
                    <div class="feature-icon-character">
                        <img src="{{ asset('images/character/map-navigating.png') }}" alt="Map" class="feature-character">
                    </div>
                    <h3>Search By Map</h3>
                    <p>Quick and simple way to choose the perfect location.</p>
                </div>
            </div>
        </section>

        @if(isset($recommendedProperties) && $recommendedProperties->count() > 0)
            @include('partials.recommended-properties-grid', [
                'properties' => $recommendedProperties,
                'title' => 'Recommended For You',
                'subtitle' => 'Properties you might like.',
            ])
        @endif

        <section id="how-it-works" class="how-it-works">
            <h2>How It Works</h2>
            <div class="steps">
                <div class="card">
                    <p class="step-number">1</p>
                    <i class="fas fa-search"></i>
                    <h3>Search</h3>
                    <p>Browse through our extensive listing of rooms and apartments</p>
                </div>
                <div class="card">
                    <p class="step-number">2</p>
                    <i class="fas fa-comments"></i>
                    <h3>Connect</h3>
                    <p>Chat with property owners or potential roommates</p>
                </div>
                
                <div class="card">
                    <p class="step-number">3</p>
                    <i class="fas fa-key"></i>
                    <h3>Move In</h3>
                    <p>Book your space and move into your new home</p>
                </div>
            </div>
        </section>

        <section id="additional-section" class="additional-section">
            <div class="additional-section-content">
                <div class="additional-section-character">
                    <img src="{{ asset('images/character/success-celebrating.png') }}" alt="Join us!" class="cta-character">
                </div>
                <div class="additional-section-text">
                    <h2>Are you a property owner?</h2>
                    <p>List your property and start earning money by renting it out. Join our community of successful property owners today!</p>
                </div>   
                <div class="additional-section-buttons">
                    <button onclick="location.href='{{ route('register') }}'" class="main-list-btn">
                        <i class="fas fa-user-plus"></i> Register Now
                    </button>
                    <button onclick="location.href='{{ route('login') }}'" class="secondary-list-btn">
                        <i class="fas fa-sign-in-alt"></i> Login
                    </button>
                </div>
            </div>
        </section>
    </main>
    @endguest
    

    @endsection