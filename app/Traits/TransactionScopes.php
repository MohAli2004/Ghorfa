<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Builder;

trait TransactionScopes
{
    /**
     * Filter by transaction type (buy or rent)
     */
    public function scopeOfType(Builder $query, string $type): Builder
    {
        return $query->where('type', $type);
    }

    /**
     * Filter only rental transactions
     */
    public function scopeRentals(Builder $query): Builder
    {
        return $query->where('type', 'rent');
    }

    /**
     * Filter only purchase transactions
     */
    public function scopePurchases(Builder $query): Builder
    {
        return $query->where('type', 'buy');
    }

    /**
     * Filter by transaction status
     */
    public function scopeWithStatus(Builder $query, string|array $statuses): Builder
    {
        if (is_string($statuses)) {
            return $query->where('status', $statuses);
        }
        return $query->whereIn('status', $statuses);
    }

    /**
     * Filter pending transactions
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', 'pending');
    }

    /**
     * Filter confirmed transactions
     */
    public function scopeConfirmed(Builder $query): Builder
    {
        return $query->where('status', 'confirmed');
    }

    /**
     * Filter paid transactions
     */
    public function scopePaid(Builder $query): Builder
    {
        return $query->where('paid', true);
    }

    /**
     * Filter completed transactions
     */
    public function scopeCompleted(Builder $query): Builder
    {
        return $query->where('status', 'completed');
    }

    /**
     * Filter active transactions (not cancelled or refunded)
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNotIn('status', ['cancelled_by_buyer', 'cancelled_by_seller', 'refunded']);
    }

    /**
     * Filter cancelled transactions
     */
    public function scopeCancelled(Builder $query): Builder
    {
        return $query->whereIn('status', ['cancelled_by_buyer', 'cancelled_by_seller']);
    }

    /**
     * Filter refunded transactions
     */
    public function scopeRefunded(Builder $query): Builder
    {
        return $query->where('status', 'refunded');
    }

    /**
     * Filter transactions with contract generated
     */
    public function scopeWithContract(Builder $query): Builder
    {
        return $query->whereNotNull('contract_generated_at')
                    ->whereNotNull('contract_path');
    }

    /**
     * Filter transactions approved by buyer
     */
    public function scopeBuyerApproved(Builder $query): Builder
    {
        return $query->whereNotNull('buyer_approved_at');
    }

    /**
     * Filter transactions with refund requested
     */
    public function scopeRefundRequested(Builder $query): Builder
    {
        return $query->whereNotNull('refund_requested_at');
    }

    /**
     * Filter transactions by property
     */
    public function scopeForProperty(Builder $query, int $propertyId): Builder
    {
        return $query->where('property_id', $propertyId);
    }

    /**
     * Filter transactions by user (buyer)
     */
    public function scopeByUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Filter rentals blocking availability for specific dates
     */
    public function scopeBlockingDates(Builder $query, string $startDate, string $endDate): Builder
    {
        return $query->rentals()
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
                    });
    }

    /**
     * Filter transactions within a date range (for rentals)
     */
    public function scopeWithinDates(Builder $query, string $startDate, string $endDate): Builder
    {
        return $query->whereBetween('start_date', [$startDate, $endDate])
                    ->orWhereBetween('end_date', [$startDate, $endDate]);
    }

    /**
     * Filter transactions by payment status (paid vs unpaid)
     */
    public function scopeUnpaid(Builder $query): Builder
    {
        return $query->where('paid', false);
    }

    /**
     * Filter paid transactions
     */
    public function scopePaidTransactions(Builder $query): Builder
    {
        return $query->where('paid', true);
    }

    /**
     * Filter transactions created within last N days
     */
    public function scopeRecentlyCreated(Builder $query, int $days = 30): Builder
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }

    /**
     * Order by most recent first
     */
    public function scopeNewest(Builder $query): Builder
    {
        return $query->orderByDesc('created_at');
    }

    /**
     * Order by oldest first
     */
    public function scopeOldest(Builder $query): Builder
    {
        return $query->orderBy('created_at');
    }

    /**
     * Transactions whose end date has passed (eligible for auto-complete).
     * Status is set to completed regardless of paid – paid remains false if payment was never completed.
     */
    public function scopeEligibleForAutoComplete(Builder $query): Builder
    {
        $today = now()->toDateString();
        return $query->where('status', '!=', 'completed')
            ->whereNotNull('end_date')
            ->where('end_date', '<', $today)
            ->whereNull('refund_requested_at');
    }

    /**
     * Pending rentals whose start date has already begun/passed (eligible for auto-cancel).
     */
    public function scopeEligibleForExpiredPendingCancel(Builder $query): Builder
    {
        $today = now()->toDateString();

        return $query->where('type', 'rent')
            ->where('status', 'pending')
            ->whereNotNull('start_date')
            ->whereDate('start_date', '<=', $today);
    }
}
