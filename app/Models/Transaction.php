<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Traits\TransactionScopes;

class Transaction extends Model
{
    use HasFactory, TransactionScopes;

    protected $fillable = [
        'user_id',
        'property_id',
        'type',
        'price',
        'price_breakdown',
        'currency',
        'status',
        'paid',
        'notes',
        'start_date',
        'end_date',
        'paid_at',
        'buyer_approved_at',
        'rules_accepted',
        'rules_exceptions',
        'contract_path',
        'contract_generated_at',
        'seller_payment_confirmed_at',
        'refund_requested_at',
        'refund_confirmed_by_buyer_at',
        'cancel_reason',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'price_breakdown' => 'array',
        'currency' => 'string',
        'paid' => 'boolean',
        'start_date' => 'date',
        'end_date' => 'date',
        'paid_at' => 'datetime',
        'buyer_approved_at' => 'datetime',
        'rules_accepted' => 'boolean',
        'rules_exceptions' => 'string',
        'contract_path' => 'string',
        'contract_generated_at' => 'datetime',
        'seller_payment_confirmed_at' => 'datetime',
        'refund_requested_at' => 'datetime',
        'refund_confirmed_by_buyer_at' => 'datetime',
    ];

    /**
     * Get the user that owns the transaction.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the property associated with the transaction.
     */
    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    /**
     * Check if transaction is pending.
     */
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    /**
     * Check if transaction is paid.
     */
    public function isPaid(): bool
    {
        return (bool) $this->paid;
    }

    /**
     * Check if transaction is confirmed.
     */
    public function isConfirmed(): bool
    {
        return $this->status === 'confirmed';
    }

    /**
     * Check if transaction is completed.
     */
    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    /**
     * Check if transaction is cancelled.
     */
    public function isCancelled(): bool
    {
        return in_array($this->status, ['cancelled_by_buyer', 'cancelled_by_seller']);
    }

    /**
     * Check if transaction is refunded.
     */
    public function isRefunded(): bool
    {
        return $this->status === 'refunded';
    }

    /**
     * Check if transaction is a buy type.
     */
    public function isBuy(): bool
    {
        return $this->type === 'buy';
    }

    /**
     * Check if transaction is a rent type.
     */
    public function isRent(): bool
    {
        return $this->type === 'rent';
    }

    /**
     * Check if transaction is a refund type.
     */
    public function isRefund(): bool
    {
        return $this->type === 'refund';
    }

    // ===================== WORKFLOW STATE CHECKS =====================

    /**
     * Check if contract has been generated (ready for buyer approval)
     */
    public function hasContractGenerated(): bool
    {
        return $this->contract_generated_at !== null && $this->contract_path !== null;
    }

    /**
     * Check if buyer has approved/accepted the contract
     */
    public function isBuyerApproved(): bool
    {
        return $this->buyer_approved_at !== null;
    }

    /**
     * Check if seller has confirmed payment received
     */
    public function isSellerPaymentConfirmed(): bool
    {
        return $this->seller_payment_confirmed_at !== null;
    }

    /**
     * Check if refund has been requested (seller cancelled after payment)
     */
    public function isRefundRequested(): bool
    {
        return $this->refund_requested_at !== null;
    }

    /**
     * Check if buyer has confirmed receiving the refund
     */
    public function isRefundConfirmed(): bool
    {
        return $this->refund_confirmed_by_buyer_at !== null;
    }

    /**
     * Check if transaction can proceed to buyer approval (contract must exist)
     */
    public function canBuyerApprove(): bool
    {
        return $this->isPending() && $this->hasContractGenerated() && !$this->isBuyerApproved();
    }

    /**
     * Check if transaction can be marked as confirmed (buyer must approve)
     */
    public function canBeConfirmed(): bool
    {
        return $this->isPending() && $this->isBuyerApproved();
    }

    /**
     * Check if transaction can be marked as paid (seller must confirm payment)
     */
    public function canBePaid(): bool
    {
        return $this->isConfirmed() && !$this->isPaid();
    }

    /**
     * Check if transaction can be marked as completed.
     * Allowed when: (paid OR end date has passed) and not in refund process and not already completed.
     */
    public function canBeCompleted(): bool
    {
        if ($this->isCompleted() || $this->isRefundRequested()) {
            return false;
        }
        return $this->isPaid() || $this->endDateHasPassed();
    }

    /**
     * Check if the transaction end date has passed (for rentals).
     */
    public function endDateHasPassed(): bool
    {
        return $this->end_date && $this->end_date->isPast();
    }

    /**
     * Check if transaction can be refunded (can only refund if already paid)
     */
    public function canBeRefunded(): bool
    {
        return $this->isPaid() && $this->isRefundRequested() && !$this->isRefundConfirmed();
    }

    /**
     * Check if seller can cancel before payment
     */
    public function canSellerCancelBeforePayment(): bool
    {
        return $this->isConfirmed() && !$this->isPaid();
    }

    /**
     * Check if buyer can cancel before payment (called by buyer before paying)
     */
    public function canBuyerCancelBeforePayment(): bool
    {
        return in_array($this->status, ['pending', 'confirmed']) && !$this->isPaid();
    }

    /**
     * Check if seller can initiate refund (only if paid)
     */
    public function canSellerInitiateRefund(): bool
    {
        return $this->isPaid() && !$this->isRefundRequested();
    }

    /**
     * Transition: Generate contract (admin action)
     */
    public function generateContract(string $contractPath): bool
    {
        if (!$this->isPending()) {
            return false;
        }
        return $this->update([
            'contract_path' => $contractPath,
            'contract_generated_at' => now(),
        ]);
    }

    /**
     * Transition: Buyer approves contract.
     * Sets buyer_approved_at and status to 'confirmed' so the transaction state reflects approval.
     */
    public function approveBuyerContract(): bool
    {
        if (!$this->canBuyerApprove()) {
            return false;
        }
        return $this->update([
            'buyer_approved_at' => now(),
            'status' => 'confirmed',
        ]);
    }

    /**
     * Transition: Move to confirmed (after buyer approves)
     */
    public function toConfirmed(): bool
    {
        if (!$this->canBeConfirmed()) {
            return false;
        }
        return $this->update([
            'status' => 'confirmed',
        ]);
    }

    /**
     * Transition: Mark as paid (seller confirms payment received)
     */
    public function confirmSellerPayment(): bool
    {
        if (!$this->canBePaid()) {
            return false;
        }
        return $this->update([
            'paid' => true,
            'paid_at' => now(),
            'seller_payment_confirmed_at' => now(),
        ]);
    }

    /**
     * Transition: Mark as completed (rental/sale finished)
     */
    public function toCompleted(): bool
    {
        if (!$this->canBeCompleted()) {
            return false;
        }
        return $this->update([
            'status' => 'completed',
        ]);
    }

    /**
     * Transition: Buyer rejects contract before payment
     */
    public function rejectContract(): bool
    {
        if (!$this->isPending() || !$this->isBuyerApproved()) {
            return false;
        }
        return $this->update([
            'status' => 'cancelled_by_buyer',
        ]);
    }

    /**
     * Transition: Buyer cancels before payment (before confirming)
     */
    public function cancelByBuyer(string $reason = null): bool
    {
        if (!$this->canBuyerCancelBeforePayment()) {
            return false;
        }
        return $this->update([
            'status' => 'cancelled_by_buyer',
            'cancel_reason' => $reason,
        ]);
    }

    /**
     * Transition: Seller cancels before payment
     */
    public function cancelBySeller(string $reason = null): bool
    {
        if (!$this->canSellerCancelBeforePayment()) {
            return false;
        }
        return $this->update([
            'status' => 'cancelled_by_seller',
            'cancel_reason' => $reason,
        ]);
    }

    /**
     * Auto-cancel a pending rental after its start date has passed.
     */
    public function cancelExpiredPending(string $reason = null): bool
    {
        if (!$this->isPending() || $this->type !== 'rent' || !$this->start_date) {
            return false;
        }

        if ($this->start_date->copy()->startOfDay()->gt(now()->startOfDay())) {
            return false;
        }

        return $this->update([
            'status' => 'cancelled_by_seller',
            'cancel_reason' => $reason ?: 'Automatically cancelled: rental start date passed while the request was still pending.',
        ]);
    }

    /**
     * Transition: Seller requests refund (after payment)
     */
    public function requestRefund(string $reason = null): bool
    {
        if (!$this->canSellerInitiateRefund()) {
            return false;
        }
        return $this->update([
            'refund_requested_at' => now(),
            'cancel_reason' => $reason,
        ]);
    }

    /**
     * Transition: Buyer confirms they received refund
     */
    public function confirmRefund(): bool
    {
        if (!$this->canBeRefunded()) {
            return false;
        }
        return $this->update([
            'status' => 'refunded',
            'refund_confirmed_by_buyer_at' => now(),
        ]);
    }

    public const RENT_BLOCKING_STATUSES = ['confirmed'];

    public function scopeBlocksAvailability($q)
    {
        return $q->where('type', 'rent')
                ->where(function ($q) {
                    $q->whereIn('status', self::RENT_BLOCKING_STATUSES)
                      ->orWhere('paid', true);
                });
    }

}
