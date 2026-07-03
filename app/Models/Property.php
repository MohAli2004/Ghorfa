<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Carbon\Carbon;


class Property extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'description',
        'property_type',
        'listing_type',
        'country',
        'city',
        'address',
        'latitude',
        'longitude',
        'price',
        'price_per_day',
        'price_per_week',
        'price_per_month',
        'price_per_year',
        'price_duration',
        'rent_duration_units',
        'unit_id',
        'area_m3',
        'room_nb',
        'bathroom_nb',
        'bedroom_nb',
        'user_id',
        'status',
        'approved_at',
        'approved_by',
        'rejection_reason',
        'resubmit_notes',
        'views_count',
        'likes_count',
    ];

    protected $casts = [
        'views_count' => 'integer',
        'likes_count' => 'integer',
    ];

    public function amenities()
    {
        return $this->belongsToMany(Amenity::class, 'amenity_property');
    }

    public function rules()
    {
        return $this->belongsToMany(Rule::class, 'property_rule');
    }

    public function landlord()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function images()
    {
        return $this->hasMany(PropertyImage::class);
    }

    public function unit()
    {
        return $this->belongsTo(Unit::class, 'unit_id');
    }

    /**
     * Get the users who have liked this property
     */
    public function likedBy()
    {
        return $this->belongsToMany(User::class, 'property_likes')
                    ->withTimestamps();
    }

    /**
     * Check if a specific user has liked this property
     */
    public function isLikedBy($userId): bool
    {
        if (!$userId) {
            return false;
        }

        return $this->likedBy()->where('user_id', $userId)->exists();
    }

    /**
     * Get the total number of likes for this property.
     * Uses denormalized likes_count when available, falls back to pivot count.
     */
    public function getLikesCountAttribute()
    {
        if (array_key_exists('likes_count', $this->attributes)) {
            return (int) $this->attributes['likes_count'];
        }

        return $this->likedBy()->count();
    }

    // app/Models/Property.php
    public function reviews()
    {
        return $this->hasMany(Review::class);
    }

    public function interactions()
    {
        return $this->hasMany(PropertyInteraction::class);
    }

    public function embedding()
    {
        return $this->hasOne(PropertyEmbedding::class);
    }

    /**
     * Get the average rating for this property
     */
    public function getAverageRatingAttribute()
    {
        return round($this->reviews()->avg('rating') ?? 0, 1);
    }

    /**
     * Get the total number of reviews for this property
     */
    public function getReviewsCountAttribute()
    {
        return $this->reviews()->count();
    }

    /**
     * Check if a user has reviewed this property
     */
    public function hasUserReviewed($userId)
    {
        return $this->reviews()->where('user_id', $userId)->exists();
    }

    /**
     * Get user's review for this property
     */
    public function getUserReview($userId)
    {
        return $this->reviews()->where('user_id', $userId)->first();
    }

    /**
     * Get all transactions for this property
     */
    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }

    public function blockingRentTransactions()
    {
        return $this->transactions()
            ->where('type', 'rent')
            ->where(function ($q) {
                $q->whereIn('status', ['confirmed'])->orWhere('paid', true);
            });
    }

    public function isAvailableFor(string $startDate, string $endDate): bool
    {
        return !Transaction::query()
            ->where('property_id', $this->id)
            ->where('type', 'rent')
            ->where(function ($q) {
                $q->whereIn('status', ['confirmed'])->orWhere('paid', true);
            })
            ->where(function ($q) use ($startDate, $endDate) {
                $q->whereBetween('start_date', [$startDate, $endDate])
                ->orWhereBetween('end_date', [$startDate, $endDate])
                ->orWhere(function ($q2) use ($startDate, $endDate) {
                    $q2->where('start_date', '<=', $startDate)
                        ->where('end_date', '>=', $endDate);
                });
            })
            ->exists();
    }

    public function rentedUntil(): ?Carbon
    {
        $tx = $this->getActiveRentalTransaction();
        return $tx?->end_date;
    }

    /**
     * Get the active (current or upcoming) rental transaction blocking the property, if any.
     */
    public function getActiveRentalTransaction(): ?\App\Models\Transaction
    {
        return $this->transactions()
            ->where('type', 'rent')
            ->where(function ($q) {
                $q->whereIn('status', ['confirmed'])->orWhere('paid', true);
            })
            ->whereDate('end_date', '>=', now()->toDateString())
            ->orderBy('end_date')
            ->first();
    }

    /**
     * Get the date range of the active rental (for blocking dates in the request form). Returns ['start' => Carbon, 'end' => Carbon] or null.
     */
    public function getActiveRentalDateRange(): ?array
    {
        $tx = $this->getActiveRentalTransaction();
        if ($tx === null || !$tx->start_date || !$tx->end_date) {
            return null;
        }
        return ['start' => $tx->start_date, 'end' => $tx->end_date];
    }

    /**
     * Earliest date a new rental can start (day after current rental ends, or today).
     */
    public function getMinRentalStartDate(): string
    {
        $until = $this->rentedUntil();
        if ($until !== null) {
            return $until->copy()->addDay()->format('Y-m-d');
        }
        return now()->format('Y-m-d');
    }

    /**
     * Whether the property has been sold (has a completed/paid purchase transaction).
     */
    public function isSold(): bool
    {
        return $this->transactions()
            ->where('type', 'buy')
            ->where(function ($q) {
                $q->where('paid', true)->orWhere('status', 'completed');
            })
            ->exists();
    }

    /**
     * Human-readable availability message for display on search and listing pages.
     * Returns null when available.
     */
    public function getAvailabilityMessage(): ?string
    {
        if ($this->isSold()) {
            return 'Sold – Not available';
        }
        $until = $this->rentedUntil();
        if ($until !== null) {
            return 'Not available until ' . $until->format('M j, Y');
        }
        return null;
    }

    /**
     * Whether the property is currently available for new requests (not sold, not under an active rental).
     */
    public function isAvailableForListing(): bool
    {
        return !$this->isSold() && $this->rentedUntil() === null;
    }
}
