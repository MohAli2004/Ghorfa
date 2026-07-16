{{-- Profile Info Page --}}
@extends('layouts/app')

@push('styles')
<link rel="stylesheet" href="{{asset('css/profile/profile.css')}}">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
@endpush

@push('scripts')
<script src="{{ asset('js/profile/profile.js') }}" defer></script>
@endpush

@section('content')
@auth
<div class="profile-info-wrapper">

    <div class="profile-layout-container">
        {{-- Left Sidebar --}}
        @include('profile.partials.sidebar-nav')

        {{-- Main Content --}}
        <main class="profile-main-content">
            <div class="profile-tab-content" id="infoTab">
                <div class="profile-card-modern">
                    {{-- Gradient Banner --}}
                    <div class="profile-banner">
                        <div class="profile-banner-character">
                            <img src="{{ asset('images/character/tie.png') }}" alt="Your Profile" class="banner-character-image">
                        </div>
                    </div>

                    {{-- Profile Header Section --}}
                    <div class="profile-header-section">
                        <div class="profile-avatar-section">
                            <div class="profile-avatar-large">
                                @if (Auth::user()->profile_image)
                                    <img src="{{ asset('storage/' . Auth::user()->profile_image) }}" alt="{{ Auth::user()->name }}">
                                @else
                                    <img src="https://ui-avatars.com/api/?name={{ urlencode(Auth::user()->name) }}&background=random&color=fff" alt="{{ Auth::user()->name }}">
                                @endif
                            </div>
                            <form method="POST" action="{{ route('profile.update.photo') }}" enctype="multipart/form-data" class="avatar-upload-form">
                                @csrf
                                @method('PUT')
                                <input type="file" name="profile_image" id="avatarFile" accept="image/*" hidden>
                                <label for="avatarFile" class="avatar-edit-icon">
                                    <i class="fas fa-camera"></i>
                                </label>
                            </form>
                        </div>
                        <div class="profile-name-section">
                            <h2 class="profile-full-name">{{ Auth::user()->first_name }} {{ Auth::user()->last_name }}</h2>
                            <p class="profile-email-display">{{ Auth::user()->email }}</p>
                        </div>
                        <div class="profile-header-actions">
                            <button type="button" class="edit-profile-btn-modern" id="toggleEditBtn">
                                <i class="fas fa-edit"></i> Edit
                            </button>
                        </div>
                    </div>

                    {{-- Flash message --}}
                    @if(session('success'))
                        <div class="alert-success-modern" role="status">{{ session('success') }}</div>
                    @endif
                    @if($errors->any())
                        <div class="alert-error-modern" role="alert">
                            Please fix the errors below and try again.
                        </div>
                    @endif

                    {{-- Prepare values --}}
                    @php
                        $user = Auth::user();
                        $dob = $user->date_of_birth ? \Carbon\Carbon::parse($user->date_of_birth) : null;
                        $showEdit = $errors->any();
                    @endphp

                    {{-- Profile Form --}}
                    <form method="POST" action="{{ route('profile.update') }}" enctype="multipart/form-data" id="editProfileSection" class="profile-form-modern" @if(!$showEdit) style="display:none" @endif>
                        @csrf
                        @method('PUT')

                        <div class="form-grid-two">
                            {{-- Full Name --}}
                            <div class="form-field-group">
                                <label for="full_name">Full Name</label>
                                <div class="input-field-modern">
                                    <input type="text" id="full_name" value="{{ $user->first_name }} {{ $user->last_name }}" placeholder="Your Full Name" readonly>
                                </div>
                            </div>

                            {{-- Phone Number --}}
                            <div class="form-field-group">
                                <label for="phone_nb">Phone Number</label>
                                <div class="input-field-modern">
                                    <span class="input-prefix">+961</span>
                                    <input type="tel" id="phone_nb" name="phone_nb" value="{{ old('phone_nb', $user->phone_nb) }}" placeholder="70 123 456" required>
                                </div>
                                @error('phone_nb') <div class="error-message">{{ $message }}</div> @enderror
                            </div>
                        </div>

                        <div class="form-grid-two">
                            {{-- First Name --}}
                            <div class="form-field-group">
                                <label for="first_name">First Name</label>
                                <div class="input-field-modern">
                                    <input type="text" id="first_name" name="first_name" value="{{ old('first_name', $user->first_name) }}" placeholder="Your First Name" required>
                                </div>
                                @error('first_name') <div class="error-message">{{ $message }}</div> @enderror
                            </div>

                            {{-- Last Name --}}
                            <div class="form-field-group">
                                <label for="last_name">Last Name</label>
                                <div class="input-field-modern">
                                    <input type="text" id="last_name" name="last_name" value="{{ old('last_name', $user->last_name) }}" placeholder="Your Last Name" required>
                                </div>
                                @error('last_name') <div class="error-message">{{ $message }}</div> @enderror
                            </div>
                        </div>

                        <div class="form-grid-two">
                            {{-- Date of Birth --}}
                            <div class="form-field-group">
                                <label>Date of Birth</label>
                                <div class="dob-selectors">
                                    <select name="dob_day" id="dob_day" aria-label="Day">
                                        <option value="">DD</option>
                                        @for ($d = 1; $d <= 31; $d++)
                                            <option value="{{ $d }}" @selected($dob && $dob->day == $d)>{{ sprintf('%02d',$d) }}</option>
                                        @endfor
                                    </select>
                                    <select name="dob_month" id="dob_month" aria-label="Month">
                                        <option value="">MM</option>
                                        @for ($m = 1; $m <= 12; $m++)
                                            <option value="{{ $m }}" @selected($dob && $dob->month == $m)>{{ sprintf('%02d',$m) }}</option>
                                        @endfor
                                    </select>
                                    <select name="dob_year" id="dob_year" aria-label="Year">
                                        <option value="">YYYY</option>
                                        @for ($y = date('Y'); $y >= 1900; $y--)
                                            <option value="{{ $y }}" @selected($dob && $dob->year == $y)>{{ $y }}</option>
                                        @endfor
                                    </select>
                                </div>
                                @error('date_of_birth') <div class="error-message">{{ $message }}</div> @enderror
                            </div>

                            {{-- Address --}}
                            <div class="form-field-group">
                                <label for="address">Address</label>
                                <div class="input-field-modern">
                                    <input type="text" id="address" name="address" value="{{ old('address', $user->address) }}" placeholder="Street, City, Country">
                                </div>
                                @error('address') <div class="error-message">{{ $message }}</div> @enderror
                            </div>
                        </div>

                        {{-- Email Address Section --}}
                        <div class="email-section-modern">
                            <div class="email-section-header">
                                <h3 class="email-section-title">
                                    <i class="fas fa-envelope"></i> My email Address
                                </h3>
                            </div>
                            <div class="email-display-item">
                                <div class="email-info">
                                    <i class="fas fa-envelope email-icon"></i>
                                    <div>
                                        <span class="email-value">{{ $user->email }}</span>
                                        <span class="email-meta">{{ $user->email_verified_at ? 'Verified' : 'Not verified' }} • {{ $user->created_at->diffForHumans() }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- Form Actions --}}
                        <div class="form-actions-modern">
                            <button type="button" class="btn-cancel" id="exitEditBtn" style="display:none;">
                                <i class="fas fa-times"></i> Cancel
                            </button>
                            <button type="submit" class="btn-save">
                                <i class="fas fa-save"></i> Save changes
                            </button>
                        </div>
                    </form>

                    {{-- View Mode (Non-editable display) --}}
                    <div id="viewMode" class="profile-view-mode" @if($showEdit) style="display:none" @endif>
                        <div class="form-grid-two">
                            <div class="info-display-group">
                                <label>Full Name</label>
                                <div class="info-value">{{ $user->first_name }} {{ $user->last_name }}</div>
                            </div>
                            <div class="info-display-group">
                                <label>Phone Number</label>
                                <div class="info-value">+961 {{ $user->phone_nb ?? 'N/A' }}</div>
                            </div>
                        </div>
                        <div class="form-grid-two">
                            <div class="info-display-group">
                                <label>Date of Birth</label>
                                <div class="info-value">{{ $dob ? $dob->format('F j, Y') : 'Not set' }}</div>
                            </div>
                            <div class="info-display-group">
                                <label>Address</label>
                                <div class="info-value">{{ $user->address ?? 'Not set' }}</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>
@endauth
@endsection
