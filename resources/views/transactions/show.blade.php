@extends('layouts.app')
@section('title', 'Transaction #' . $transaction->id)

@push('styles')
<link rel="stylesheet" href="{{ asset('css/transaction-request.css') }}">
<link rel="stylesheet" href="{{ asset('css/landlord/dashboard.css') }}">
<link rel="stylesheet" href="{{ asset('css/transactions-show.css') }}">
@endpush

@section('content')
@php
    $user = $transaction->user;
    $property = $transaction->property;
    $landlord = $property?->landlord;
    $userPhone = $user ? ($user->phone_nb ?? $user->phone ?? '') : '';
    $statusLabel = $transaction->paid ? 'Payment received' : ($transaction->status ? str_replace('_', ' ', ucwords($transaction->status, '_')) : 'Pending');
    $statusClass = $transaction->paid ? 'request-detail-status--paid' : ('request-detail-status--' . ($transaction->status ?? 'pending'));
    $propertyRules = $property ? ($property->rules ?? collect()) : collect();
    $propertyAmenities = $property ? ($property->amenities ?? collect()) : collect();
    $typeLabel = match ($transaction->type) {
        'rent' => 'Rental',
        'buy' => 'Purchase',
        'refund' => 'Refund',
        default => ucfirst((string) $transaction->type),
    };
    $typeChipClass = match ($transaction->type) {
        'rent' => 'txn-show__chip--type-rent',
        'buy' => 'txn-show__chip--type-buy',
        default => '',
    };
    $propertyThumb = $property && $property->relationLoaded('images') && $property->images->isNotEmpty()
        ? $property->images->first()
        : null;
    $heroPillModifier = $transaction->paid ? 'paid' : str_replace('_', '-', $transaction->status ?? 'pending');
@endphp

<div class="txn-show">
    <div class="container py-4">
        <div class="txn-show__top">
            <div></div>
            <a href="{{ url()->previous() }}" class="txn-show__back">
                <i class="fas fa-arrow-left" aria-hidden="true"></i> Back
            </a>
        </div>

        <header class="txn-show__hero" aria-labelledby="txn-hero-title">
            <div class="txn-show__hero-bg" aria-hidden="true"></div>
            <div class="txn-show__hero-inner">
                <p class="txn-show__hero-kicker">Transaction workspace</p>
                <h1 id="txn-hero-title" class="txn-show__hero-title">Deal #{{ $transaction->id }}</h1>
                <div class="txn-show__hero-meta">
                    <span class="txn-show__chip {{ $typeChipClass }}">
                        <i class="fas fa-tag" aria-hidden="true"></i> {{ $typeLabel }}
                    </span>
                    <span class="txn-show__status-pill txn-show__status-pill--{{ $heroPillModifier }}">{{ $statusLabel }}</span>
                    @if($transaction->paid)
                        <span class="txn-show__chip"><i class="fas fa-check-circle" aria-hidden="true"></i> Paid</span>
                    @endif
                </div>
                <p class="txn-show__hero-price">${{ number_format($transaction->price, 2) }}</p>
                <p class="txn-show__hero-price-note">{{ $transaction->currency ?? 'USD' }}{{ $transaction->type === 'rent' ? ' · total for stay' : '' }} · requested {{ $transaction->created_at?->format('M j, Y') }}</p>

                <div class="txn-show__parties">
                    <div class="txn-show__party-card">
                        <div class="txn-show__party-label">Buyer / renter</div>
                        <div class="txn-show__party-name">{{ $user?->name ?? '—' }}</div>
                        @if($user)
                            <div class="txn-show__party-email">{{ $user->email }}</div>
                        @endif
                    </div>
                    <div class="txn-show__party-arrow" aria-hidden="true"><i class="fas fa-arrows-alt-h"></i></div>
                    <div class="txn-show__party-card">
                        <div class="txn-show__party-label">Landlord</div>
                        <div class="txn-show__party-name">{{ $landlord?->name ?? '—' }}</div>
                        @if($landlord)
                            <div class="txn-show__party-email">{{ $landlord->email }}</div>
                        @endif
                    </div>
                </div>
            </div>
        </header>

        <div class="txn-show__alerts">
            @if(session('success'))
                <div class="txn-show__alert txn-show__alert--success" role="status">
                    <i class="fas fa-check-circle" aria-hidden="true"></i>
                    <span>{{ session('success') }}</span>
                </div>
            @endif
            @if(session('error'))
                <div class="txn-show__alert txn-show__alert--danger" role="alert">
                    <i class="fas fa-exclamation-circle" aria-hidden="true"></i>
                    <span>{{ session('error') }}</span>
                </div>
            @endif
            @if($isBuyer && $transaction->hasContractGenerated() && !$transaction->isBuyerApproved())
                <div class="txn-show__alert txn-show__alert--info" role="status">
                    <i class="fas fa-file-signature" aria-hidden="true"></i>
                    <span><strong>Contract ready.</strong> Review the report below, then approve or reject.</span>
                </div>
            @endif
            @if($isBuyer && $transaction->isBuyerApproved() && $transaction->isPending())
                <div class="txn-show__alert txn-show__alert--success" role="status">
                    <i class="fas fa-check-circle" aria-hidden="true"></i>
                    <span>You approved this contract. Waiting for the landlord to confirm.</span>
                </div>
            @endif
            @if($isBuyer && !$transaction->hasContractGenerated())
                <div class="txn-show__alert txn-show__alert--wait" role="status">
                    <i class="fas fa-hourglass-half" aria-hidden="true"></i>
                    <span>Waiting for the landlord to generate the contract. You’ll review and approve it here when it’s ready.</span>
                </div>
            @endif
        </div>

        <div class="txn-show__layout">
            <div class="txn-show__main">
                <div class="request-details-content request-details-content--standalone">
                    <section class="request-detail-section txn-card txn-card--buyer">
                        <div class="txn-card__head">
                            <span class="txn-card__head-icon" aria-hidden="true"><i class="fas fa-user"></i></span>
                            <h2 class="txn-card__title">Guest details</h2>
                        </div>
                        <div class="txn-card__body">
                            <div class="request-details-grid">
                                <div class="request-detail-item">
                                    <label><i class="fas fa-user"></i> Name</label>
                                    <span>{{ $user ? $user->name : '—' }}</span>
                                </div>
                                <div class="request-detail-item">
                                    <label><i class="fas fa-envelope"></i> Email</label>
                                    <span>{{ $user ? $user->email : '—' }}</span>
                                </div>
                                @if($userPhone)
                                    <div class="request-detail-item">
                                        <label><i class="fas fa-phone"></i> Phone</label>
                                        <span>{{ $userPhone }}</span>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </section>

                    <section class="request-detail-section txn-card txn-card--deal">
                        <div class="txn-card__head">
                            <span class="txn-card__head-icon" aria-hidden="true"><i class="fas fa-file-invoice-dollar"></i></span>
                            <h2 class="txn-card__title">Deal terms</h2>
                        </div>
                        <div class="txn-card__body">
                            <div class="request-details-grid">
                                <div class="request-detail-item">
                                    <label><i class="fas fa-tag"></i> Request type</label>
                                    <span>{{ $typeLabel }}</span>
                                </div>
                                <div class="request-detail-item">
                                    <label><i class="fas fa-info-circle"></i> Status</label>
                                    <span class="request-detail-status {{ $statusClass }}">{{ $statusLabel }}</span>
                                </div>
                                <div class="request-detail-item">
                                    <label><i class="fas fa-dollar-sign"></i> Agreed price</label>
                                    <span><strong>${{ number_format($transaction->price, 2) }}</strong> {{ $transaction->currency ?? 'USD' }}{{ $transaction->type === 'rent' ? ' / total for stay' : '' }}</span>
                                </div>
                                <div class="request-detail-item">
                                    <label><i class="fas fa-calendar-alt"></i> Request date</label>
                                    <span>{{ $transaction->created_at ? $transaction->created_at->format('M j, Y') : 'N/A' }}</span>
                                </div>
                                @if($transaction->type === 'rent')
                                    <div class="request-detail-item">
                                        <label><i class="fas fa-calendar-check"></i> Check-in</label>
                                        <span>{{ $transaction->start_date ? $transaction->start_date->format('M j, Y') : 'N/A' }}</span>
                                    </div>
                                    <div class="request-detail-item">
                                        <label><i class="fas fa-calendar-times"></i> Check-out</label>
                                        <span>{{ $transaction->end_date ? $transaction->end_date->format('M j, Y') : 'N/A' }}</span>
                                    </div>
                                    @php $breakdown = $transaction->price_breakdown ?? []; @endphp
                                    @if(!empty($breakdown['label']))
                                        <div class="request-detail-item">
                                            <label><i class="fas fa-clock"></i> Stay period</label>
                                            <span>
                                                {{ $breakdown['label'] }}
                                                @if(!empty($breakdown['nights']))
                                                    ({{ $breakdown['nights'] }} night{{ $breakdown['nights'] == 1 ? '' : 's' }})
                                                @endif
                                            </span>
                                        </div>
                                    @endif
                                    @if(!empty($breakdown['units']))
                                        <div class="request-detail-item request-detail-item--full">
                                            <label><i class="fas fa-calculator"></i> Price breakdown</label>
                                            <ul class="request-detail-list">
                                                @foreach(['year', 'month', 'week', 'day'] as $unit)
                                                    @php $row = $breakdown['units'][$unit] ?? null; @endphp
                                                    @if($row && (int) ($row['count'] ?? 0) > 0)
                                                        <li>
                                                            {{ (int) $row['count'] }} {{ $unit }}{{ (int) $row['count'] === 1 ? '' : 's' }}
                                                            × ${{ number_format((float) ($row['rate'] ?? 0), 2) }}
                                                            = ${{ number_format((float) ($row['subtotal'] ?? 0), 2) }}
                                                        </li>
                                                    @endif
                                                @endforeach
                                            </ul>
                                        </div>
                                    @endif
                                    <div class="request-detail-item">
                                        <label><i class="fas fa-check-double"></i> Rules accepted</label>
                                        <span class="request-detail-rules-accepted {{ $transaction->rules_accepted ? 'request-detail-rules-accepted--yes' : 'request-detail-rules-accepted--no' }}">
                                            {{ $transaction->rules_accepted ? 'Yes ✓' : 'No ✗' }}
                                        </span>
                                    </div>
                                @endif
                            </div>
                            @if($transaction->rules_exceptions && trim($transaction->rules_exceptions))
                                <div class="request-detail-item request-detail-item--full" style="margin-top: 1rem;">
                                    <label class="request-detail-label--warning"><i class="fas fa-exclamation-circle"></i> Rules exceptions / concerns</label>
                                    <div class="request-detail-box request-detail-box--warning">{{ $transaction->rules_exceptions }}</div>
                                </div>
                            @endif
                            @if($transaction->notes && trim($transaction->notes))
                                <div class="request-detail-item request-detail-item--full" style="margin-top: 1rem;">
                                    <label class="request-detail-label--notes"><i class="fas fa-comment-dots"></i> Notes</label>
                                    <div class="request-detail-box request-detail-box--notes">{{ $transaction->notes }}</div>
                                </div>
                            @endif
                        </div>
                    </section>

                    @if($property)
                        <section class="request-detail-section txn-card txn-card--property">
                            <div class="txn-card__head">
                                <span class="txn-card__head-icon" aria-hidden="true"><i class="fas fa-home"></i></span>
                                <h2 class="txn-card__title">Property</h2>
                            </div>
                            <div class="txn-card__body">
                                <div class="request-details-grid">
                                    <div class="request-detail-item">
                                        <label><i class="fas fa-building"></i> Title</label>
                                        <span>{{ $property->title }}</span>
                                    </div>
                                    <div class="request-detail-item">
                                        <label><i class="fas fa-map-marker-alt"></i> Location</label>
                                        <span>{{ $property->city }}, {{ $property->country }}</span>
                                    </div>
                                    <div class="request-detail-item">
                                        <label><i class="fas fa-layer-group"></i> Type</label>
                                        <span>{{ $property->property_type }}</span>
                                    </div>
                                    <div class="request-detail-item">
                                        <label><i class="fas fa-dollar-sign"></i> Listed at</label>
                                        <span>
                                            ${{ number_format($property->price) }}
                                            @if(($property->listing_type ?? null) === 'rent')
                                                /{{ $property->price_duration ?? 'month' }}
                                            @endif
                                        </span>
                                    </div>
                                    @if($property->bedroom_nb)
                                        <div class="request-detail-item">
                                            <label><i class="fas fa-bed"></i> Bedrooms</label>
                                            <span>{{ $property->bedroom_nb }}</span>
                                        </div>
                                    @endif
                                    @if($property->bathroom_nb)
                                        <div class="request-detail-item">
                                            <label><i class="fas fa-bath"></i> Bathrooms</label>
                                            <span>{{ $property->bathroom_nb }}</span>
                                        </div>
                                    @endif
                                    @if($property->area_m3)
                                        <div class="request-detail-item">
                                            <label><i class="fas fa-ruler-combined"></i> Area</label>
                                            <span>{{ $property->area_m3 }} m²</span>
                                        </div>
                                    @endif
                                    @if($property->description && trim($property->description))
                                        <div class="request-detail-item request-detail-item--full">
                                            <label><i class="fas fa-align-left"></i> Description</label>
                                            <div class="request-detail-box">{{ $property->description }}</div>
                                        </div>
                                    @endif
                                    <div class="request-detail-item request-detail-item--full request-detail-rules-amenities-row">
                                        <div class="request-detail-rules-amenities-grid">
                                            <div class="request-detail-rules-amenities-col">
                                                <label><i class="fas fa-list-alt"></i> House rules</label>
                                                @if($propertyRules->isNotEmpty())
                                                    <ul class="request-detail-list">
                                                        @foreach($propertyRules as $rule)
                                                            <li>{{ $rule->name }}</li>
                                                        @endforeach
                                                    </ul>
                                                @else
                                                    <span class="request-detail-empty">None listed</span>
                                                @endif
                                            </div>
                                            <div class="request-detail-rules-amenities-col">
                                                <label><i class="fas fa-concierge-bell"></i> Amenities</label>
                                                @if($propertyAmenities->isNotEmpty())
                                                    <ul class="request-detail-list request-detail-list--amenities">
                                                        @foreach($propertyAmenities as $amenity)
                                                            <li>{{ $amenity->name }}</li>
                                                        @endforeach
                                                    </ul>
                                                @else
                                                    <span class="request-detail-empty">None listed</span>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </section>
                    @endif

                    @if($canApprove)
                        <section class="request-detail-section txn-card txn-card--actions request-detail-section--actions">
                            <div class="txn-card__head">
                                <span class="txn-card__head-icon" aria-hidden="true"><i class="fas fa-file-signature"></i></span>
                                <h2 class="txn-card__title">Your decision</h2>
                            </div>
                            <div class="txn-card__body">
                                <p class="text-muted mb-3" style="margin-top:0;">Confirm you agree with the terms, or reject with an optional reason.</p>
                                <div class="request-details-actions transaction-report-actions">
                                    <form method="POST" action="{{ route('transactions.approve-contract', $transaction) }}" class="transaction-report-action-form">
                                        @csrf
                                        <button type="submit" class="btn btn-success">
                                            <i class="fas fa-check"></i> Approve contract
                                        </button>
                                    </form>
                                    <form method="POST" action="{{ route('transactions.reject-contract', $transaction) }}" class="transaction-report-action-form transaction-report-reject-form" onsubmit="return confirm('Rejecting the contract will cancel this transaction. Continue?');">
                                        @csrf
                                        <input type="text" name="reason" class="form-control" placeholder="Reason (optional)" maxlength="500">
                                        <button type="submit" class="btn btn-outline-danger">
                                            <i class="fas fa-times"></i> Reject contract
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </section>
                    @endif
                </div>
            </div>

            @if($property)
                <aside class="txn-show__side" aria-label="Property preview">
                    <div class="txn-show__side-card">
                        @if($propertyThumb)
                            <img class="txn-show__side-img" src="{{ asset('storage/'.$propertyThumb->path) }}" alt="{{ $property->title }}">
                        @else
                            <div class="txn-show__side-placeholder" role="img" aria-label="No photo">
                                <i class="fas fa-image" aria-hidden="true"></i>
                            </div>
                        @endif
                        <div class="txn-show__side-body">
                            <h3 class="txn-show__side-title">{{ $property->title }}</h3>
                            <p class="txn-show__side-meta">{{ $property->city }}, {{ $property->country }}</p>
                            <a href="{{ route('properties.show', $property) }}" class="txn-show__side-link" target="_blank" rel="noopener noreferrer">
                                View listing <i class="fas fa-external-link-alt fa-sm" aria-hidden="true"></i>
                            </a>
                        </div>
                    </div>
                </aside>
            @endif
        </div>
    </div>
</div>
@endsection
