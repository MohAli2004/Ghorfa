@php
    use App\Support\PdfText;

    $user = $transaction->user;
    $property = $transaction->property;
    $userPhone = $user ? ($user->phone_nb ?? $user->phone ?? '') : '';
    $statusLabel = $transaction->paid ? 'Payment Received' : ($transaction->status ? str_replace('_', ' ', ucwords($transaction->status, '_')) : 'Pending');
    $statusClass = $transaction->paid ? 'request-detail-status--paid' : ('request-detail-status--' . ($transaction->status ?? 'pending'));
    $forPdf = $forPdf ?? false;

    $pdf = fn (?string $text) => PdfText::html($text);
@endphp

<section class="request-detail-section">
    <h4 class="request-detail-section-title">
        @unless($forPdf)<i class="fas fa-user"></i>@endunless
        Buyer information
    </h4>
    <table class="request-details-grid">
        <tr>
            <td class="request-detail-item">
                <label>@unless($forPdf)<i class="fas fa-user"></i>@endunless Name</label>
                <span>{!! $pdf($user?->name) !!}</span>
            </td>
            <td class="request-detail-item">
                <label>@unless($forPdf)<i class="fas fa-envelope"></i>@endunless Email</label>
                <span>{{ $user ? $user->email : '—' }}</span>
            </td>
        </tr>
        @if($userPhone)
        <tr>
            <td class="request-detail-item" colspan="2">
                <label>@unless($forPdf)<i class="fas fa-phone"></i>@endunless Phone</label>
                <span>{{ $userPhone }}</span>
            </td>
        </tr>
        @endif
    </table>
</section>

<section class="request-detail-section">
    <h4 class="request-detail-section-title">
        @unless($forPdf)<i class="fas fa-file-invoice"></i>@endunless
        Transaction information
    </h4>
    <table class="request-details-grid">
        <tr>
            <td class="request-detail-item">
                <label>@unless($forPdf)<i class="fas fa-tag"></i>@endunless Request type</label>
                <span>{{ $transaction->type === 'rent' ? 'Rental Request' : 'Purchase Request' }}</span>
            </td>
            <td class="request-detail-item">
                <label>@unless($forPdf)<i class="fas fa-info-circle"></i>@endunless Status</label>
                <span class="request-detail-status {{ $statusClass }}">{{ $statusLabel }}</span>
            </td>
        </tr>
        <tr>
            <td class="request-detail-item">
                <label>@unless($forPdf)<i class="fas fa-calendar-alt"></i>@endunless Request date</label>
                <span>{{ $transaction->created_at ? $transaction->created_at->format('M j, Y') : 'N/A' }}</span>
            </td>
            <td class="request-detail-item">
                <label>@unless($forPdf)<i class="fas fa-check-double"></i>@endunless Rules accepted</label>
                <span class="request-detail-rules-accepted {{ $transaction->rules_accepted ? 'request-detail-rules-accepted--yes' : 'request-detail-rules-accepted--no' }}">
                    {{ $transaction->rules_accepted ? 'Yes ✓' : 'No ✗' }}
                </span>
            </td>
        </tr>
        @if($transaction->type === 'rent')
        <tr>
            <td class="request-detail-item">
                <label>@unless($forPdf)<i class="fas fa-calendar-check"></i>@endunless Check-in</label>
                <span>{{ $transaction->start_date ? \Carbon\Carbon::parse($transaction->start_date)->format('M j, Y') : 'N/A' }}</span>
            </td>
            <td class="request-detail-item">
                <label>@unless($forPdf)<i class="fas fa-calendar-times"></i>@endunless Check-out</label>
                <span>{{ $transaction->end_date ? \Carbon\Carbon::parse($transaction->end_date)->format('M j, Y') : 'N/A' }}</span>
            </td>
        </tr>
        <tr>
            <td class="request-detail-item">
                <label>@unless($forPdf)<i class="fas fa-dollar-sign"></i>@endunless Calculated total</label>
                <span><strong>${{ number_format((float) $transaction->price, 2) }}</strong> {{ $transaction->currency ?? 'USD' }}</span>
            </td>
            <td class="request-detail-item">
                <label>@unless($forPdf)<i class="fas fa-clock"></i>@endunless Stay period</label>
                <span>
                    @php
                        $breakdown = $transaction->price_breakdown ?? [];
                        $stayLabel = $breakdown['label'] ?? null;
                        $nights = $breakdown['nights'] ?? null;
                    @endphp
                    @if($stayLabel)
                        {{ $stayLabel }}@if($nights) ({{ $nights }} night{{ $nights == 1 ? '' : 's' }})@endif
                    @else
                        —
                    @endif
                </span>
            </td>
        </tr>
        @if(!empty($breakdown['units']))
        <tr>
            <td class="request-detail-item request-detail-item--full" colspan="2">
                <label>@unless($forPdf)<i class="fas fa-calculator"></i>@endunless Price breakdown</label>
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
            </td>
        </tr>
        @endif
        @endif
        @if($transaction->rules_exceptions && trim($transaction->rules_exceptions))
        <tr>
            <td class="request-detail-item request-detail-item--full" colspan="2">
                <label class="request-detail-label--warning">@unless($forPdf)<i class="fas fa-exclamation-circle"></i>@endunless Rules exceptions / concerns</label>
                <div class="request-detail-box request-detail-box--warning">{!! $pdf($transaction->rules_exceptions) !!}</div>
            </td>
        </tr>
        @endif
        @if($transaction->notes && trim($transaction->notes))
        <tr>
            <td class="request-detail-item request-detail-item--full" colspan="2">
                <label class="request-detail-label--notes">@unless($forPdf)<i class="fas fa-comment-dots"></i>@endunless Buyer notes</label>
                <div class="request-detail-box request-detail-box--notes">{!! $pdf($transaction->notes) !!}</div>
            </td>
        </tr>
        @endif
    </table>
</section>

@if($property)
<section class="request-detail-section">
    <h4 class="request-detail-section-title">
        @unless($forPdf)<i class="fas fa-home"></i>@endunless
        Property information
    </h4>
    <table class="request-details-grid">
        <tr>
            <td class="request-detail-item">
                <label>@unless($forPdf)<i class="fas fa-building"></i>@endunless Property</label>
                <span>{!! $pdf($property->title) !!}</span>
            </td>
            <td class="request-detail-item">
                <label>@unless($forPdf)<i class="fas fa-map-marker-alt"></i>@endunless Location</label>
                <span>{!! $pdf($property->city) !!}, {!! $pdf($property->country) !!}</span>
            </td>
        </tr>
        <tr>
            <td class="request-detail-item">
                <label>@unless($forPdf)<i class="fas fa-layer-group"></i>@endunless Type</label>
                <span>{{ $property->property_type }}</span>
            </td>
            <td class="request-detail-item">
                <label>@unless($forPdf)<i class="fas fa-dollar-sign"></i>@endunless Listed rate</label>
                <span>
                    ${{ number_format($property->price) }}
                    @if(($property->listing_type ?? null) === 'rent')
                        /{{ $property->price_duration ?? 'month' }}
                    @endif
                </span>
            </td>
        </tr>
        @if($property->bedroom_nb || $property->bathroom_nb)
        <tr>
            @if($property->bedroom_nb)
            <td class="request-detail-item">
                <label>@unless($forPdf)<i class="fas fa-bed"></i>@endunless Bedrooms</label>
                <span>{{ $property->bedroom_nb }}</span>
            </td>
            @else
            <td></td>
            @endif
            @if($property->bathroom_nb)
            <td class="request-detail-item">
                <label>@unless($forPdf)<i class="fas fa-bath"></i>@endunless Bathrooms</label>
                <span>{{ $property->bathroom_nb }}</span>
            </td>
            @endif
        </tr>
        @endif
        @if($property->area_m3)
        <tr>
            <td class="request-detail-item" colspan="2">
                <label>@unless($forPdf)<i class="fas fa-ruler-combined"></i>@endunless Area</label>
                <span>{{ $property->area_m3 }} m²</span>
            </td>
        </tr>
        @endif
        @if($property->description && trim($property->description))
        <tr>
            <td class="request-detail-item request-detail-item--full" colspan="2">
                <label>@unless($forPdf)<i class="fas fa-align-left"></i>@endunless Description</label>
                <div class="request-detail-box">{!! $pdf($property->description) !!}</div>
            </td>
        </tr>
        @endif
    </table>

    @php
        $propertyRules = $property->rules ?? collect();
        $propertyAmenities = $property->amenities ?? collect();
    @endphp
    <table class="rules-amenities-table request-detail-rules-amenities-row">
        <tr>
            <td class="request-detail-rules-amenities-col">
                <label>@unless($forPdf)<i class="fas fa-list-alt"></i>@endunless House rules</label>
                @if($propertyRules->isNotEmpty())
                <ul class="request-detail-list">
                    @foreach($propertyRules as $rule)
                    <li>{!! $pdf($rule->name) !!}</li>
                    @endforeach
                </ul>
                @else
                <span class="request-detail-empty">None listed</span>
                @endif
            </td>
            <td class="request-detail-rules-amenities-col">
                <label>@unless($forPdf)<i class="fas fa-concierge-bell"></i>@endunless Amenities</label>
                @if($propertyAmenities->isNotEmpty())
                @if($forPdf)
                    @foreach($propertyAmenities as $amenity)
                    <span class="amenity-tag">{!! $pdf($amenity->name) !!}</span>
                    @endforeach
                @else
                <ul class="request-detail-list request-detail-list--amenities">
                    @foreach($propertyAmenities as $amenity)
                    <li>{!! $pdf($amenity->name) !!}</li>
                    @endforeach
                </ul>
                @endif
                @else
                <span class="request-detail-empty">None listed</span>
                @endif
            </td>
        </tr>
    </table>
</section>
@endif
