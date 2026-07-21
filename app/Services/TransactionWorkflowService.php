<?php

namespace App\Services;

use App\Models\Transaction;
use App\Models\Property;
use App\Models\User;
use App\Traits\CreatesNotifications;
use Illuminate\Validation\ValidationException;
use Carbon\Carbon;

class TransactionWorkflowService
{
    use CreatesNotifications;

    public function __construct(
        protected RentalPriceCalculator $rentalPriceCalculator,
    ) {}

    /**
     * Validate if a property is available for rental during specified dates
     */
    public function validatePropertyAvailability(Property $property, string $startDate, string $endDate): bool
    {
        // Property is considered AVAILABLE when there is NO blocking rental
        // transaction that overlaps with the requested date range.
        //
        // We look for existing rental transactions on this property whose
        // status blocks availability (see Transaction::scopeBlocksAvailability),
        // and whose date ranges overlap [startDate, endDate].

        return !$property->transactions()
            ->blocksAvailability()
            ->where(function ($q) use ($startDate, $endDate) {
                $q
                    // Existing booking starts inside requested range
                    ->whereBetween('start_date', [$startDate, $endDate])
                    // OR ends inside requested range
                    ->orWhereBetween('end_date', [$startDate, $endDate])
                    // OR fully covers the requested range
                    ->orWhere(function ($q2) use ($startDate, $endDate) {
                        $q2->where('start_date', '<=', $startDate)
                           ->where('end_date', '>=', $endDate);
                    });
            })
            ->exists();
    }

    /**
     * Create a new rental request (initial transaction in pending status)
     */
    public function createRentalRequest(
        int $userId,
        int $propertyId,
        string $startDate,
        string $endDate,
        bool $rulesAccepted = false,
        ?string $rulesExceptions = null,
        ?string $notes = null
    ): Transaction {
        $property = Property::findOrFail($propertyId);

        // Validate availability
        if (!$this->validatePropertyAvailability($property, $startDate, $endDate)) {
            throw ValidationException::withMessages([
                'dates' => 'Property is not available for the requested dates.',
            ]);
        }

        try {
            $pricing = $this->rentalPriceCalculator->calculate($property, $startDate, $endDate);
        } catch (\InvalidArgumentException $e) {
            throw ValidationException::withMessages([
                'dates' => $e->getMessage(),
            ]);
        }

        $transaction = Transaction::create([
            'user_id' => $userId,
            'property_id' => $propertyId,
            'type' => 'rent',
            'price' => $pricing['total'],
            'price_breakdown' => $pricing,
            'currency' => 'USD', // Default, adjust as needed
            'status' => 'pending',
            'start_date' => $startDate,
            'end_date' => $endDate,
            'rules_accepted' => $rulesAccepted,
            'rules_exceptions' => $rulesExceptions,
            'notes' => $notes,
        ]);

        $this->notifyNewTransactionRequest($transaction, $property);

        return $transaction;
    }

    /**
     * Create a new purchase request (initial transaction in pending status)
     */
    public function createPurchaseRequest(
        int $userId,
        int $propertyId,
        ?string $notes = null
    ): Transaction {
        $property = Property::findOrFail($propertyId);

        $transaction = Transaction::create([
            'user_id' => $userId,
            'property_id' => $propertyId,
            'type' => 'buy',
            'price' => $property->price,
            'currency' => 'USD', // Default
            'status' => 'pending',
            'notes' => $notes,
        ]);

        $this->notifyNewTransactionRequest($transaction, $property);

        return $transaction;
    }

    /**
     * Admin generates contract/transaction file
     * In real implementation, this would generate a PDF
     */
    public function generateContract(Transaction $transaction, string $filePath): bool
    {
        if (!$transaction->isPending()) {
            throw ValidationException::withMessages([
                'status' => 'Contract can only be generated for pending transactions.',
            ]);
        }

        // TODO: Implement actual PDF generation and storage
        // For now, we just store the path
        $result = $transaction->generateContract($filePath);

        if ($result) {
            $transaction->loadMissing('property', 'user');
            $propertyTitle = $transaction->property?->title ?? 'a property';
            $this->notifyBuyer(
                $transaction,
                'transaction',
                'Contract Ready for Review',
                'A contract is ready for "' . $propertyTitle . '". Please review and approve it.'
            );
        }

        return $result;
    }

    /**
     * Buyer reviews and approves the contract
     */
    public function approveContract(Transaction $transaction): bool
    {
        if (!$transaction->canBuyerApprove()) {
            throw ValidationException::withMessages([
                'status' => 'Contract must be generated before buyer can approve.',
            ]);
        }

        $result = $transaction->approveBuyerContract();

        if ($result) {
            $transaction->loadMissing('property', 'user');
            $propertyTitle = $transaction->property?->title ?? 'your property';
            $buyerName = $transaction->user?->name ?? 'The buyer';
            $this->notifyLandlord(
                $transaction,
                'transaction',
                'Buyer Approved Contract',
                $buyerName . ' approved the contract for "' . $propertyTitle . '".'
            );
        }

        return $result;
    }

    /**
     * Buyer rejects the contract (cancels transaction)
     */
    public function rejectContract(Transaction $transaction, string $reason = null): bool
    {
        if (!$transaction->isPending()) {
            throw ValidationException::withMessages([
                'status' => 'Can only reject contracts in pending status.',
            ]);
        }

        $result = $transaction->update([
            'status' => 'cancelled_by_buyer',
            'cancel_reason' => $reason,
        ]);

        if ($result) {
            $transaction->loadMissing('property', 'user');
            $propertyTitle = $transaction->property?->title ?? 'your property';
            $buyerName = $transaction->user?->name ?? 'The buyer';
            $this->notifyLandlord(
                $transaction,
                'reject',
                'Contract Rejected',
                $buyerName . ' rejected the contract for "' . $propertyTitle . '".'
            );
        }

        return $result;
    }

    /**
     * Move transaction to confirmed after buyer approves
     */
    public function confirmTransaction(Transaction $transaction): bool
    {
        if (!$transaction->canBeConfirmed()) {
            throw ValidationException::withMessages([
                'status' => 'Buyer must approve contract before confirming.',
            ]);
        }

        $result = $transaction->toConfirmed();

        if ($result) {
            $transaction->loadMissing('property', 'user');
            $propertyTitle = $transaction->property?->title ?? 'the property';
            $this->notifyBuyer(
                $transaction,
                'transaction',
                'Transaction Confirmed',
                'Your request for "' . $propertyTitle . '" has been confirmed.'
            );
            $this->notifyLandlord(
                $transaction,
                'transaction',
                'Transaction Confirmed',
                'The request for "' . $propertyTitle . '" has been confirmed.'
            );
        }

        return $result;
    }

    /**
     * Seller/Landlord confirms payment received from buyer
     */
    public function confirmPayment(Transaction $transaction): bool
    {
        if (!$transaction->canBePaid()) {
            throw ValidationException::withMessages([
                'status' => 'Transaction must be confirmed before marking as paid.',
            ]);
        }

        $result = $transaction->confirmSellerPayment();

        if ($result) {
            $transaction->loadMissing('property');
            $propertyTitle = $transaction->property?->title ?? 'the property';
            $this->notifyBuyer(
                $transaction,
                'transaction',
                'Payment Confirmed',
                'The landlord confirmed payment for "' . $propertyTitle . '".'
            );
        }

        return $result;
    }

    /**
     * Mark transaction as completed (rental period finished or sale/delivery complete)
     */
    public function completeTransaction(Transaction $transaction): bool
    {
        if (!$transaction->canBeCompleted()) {
            throw ValidationException::withMessages([
                'status' => 'Transaction must be paid and not already in refund process.',
            ]);
        }

        $result = $transaction->toCompleted();

        if ($result) {
            $transaction->loadMissing('property');
            $propertyTitle = $transaction->property?->title ?? 'the property';
            $message = 'The transaction for "' . $propertyTitle . '" is now complete.';
            $this->notifyBuyer($transaction, 'approve', 'Transaction Completed', $message);
            $this->notifyLandlord($transaction, 'approve', 'Transaction Completed', $message);
        }

        return $result;
    }

    /**
     * Buyer cancels before payment is made
     */
    public function buyerCancelBeforePayment(Transaction $transaction, string $reason = null): bool
    {
        if (!$transaction->canBuyerCancelBeforePayment()) {
            throw ValidationException::withMessages([
                'status' => 'Cannot cancel after payment has been made.',
            ]);
        }

        $result = $transaction->cancelByBuyer($reason);

        if ($result) {
            $transaction->loadMissing('property', 'user');
            $propertyTitle = $transaction->property?->title ?? 'your property';
            $buyerName = $transaction->user?->name ?? 'The buyer';
            $this->notifyLandlord(
                $transaction,
                'reject',
                'Request Cancelled',
                $buyerName . ' cancelled the request for "' . $propertyTitle . '".'
            );
        }

        return $result;
    }

    /**
     * Seller/Landlord cancels before payment is made
     */
    public function sellerCancelBeforePayment(Transaction $transaction, string $reason = null): bool
    {
        if (!$transaction->canSellerCancelBeforePayment()) {
            throw ValidationException::withMessages([
                'status' => 'Can only cancel confirmed transactions before payment.',
            ]);
        }

        $result = $transaction->cancelBySeller($reason);

        if ($result) {
            $transaction->loadMissing('property');
            $propertyTitle = $transaction->property?->title ?? 'the property';
            $this->notifyBuyer(
                $transaction,
                'reject',
                'Request Cancelled by Landlord',
                'The landlord cancelled the request for "' . $propertyTitle . '".'
            );
        }

        return $result;
    }

    /**
     * Seller/Landlord requests refund (after payment was made)
     * This initiates the refund workflow
     */
    public function requestRefund(Transaction $transaction, string $reason = null): bool
    {
        if (!$transaction->canSellerInitiateRefund()) {
            throw ValidationException::withMessages([
                'status' => 'Can only request refund for paid transactions.',
            ]);
        }

        $result = $transaction->requestRefund($reason);

        if ($result) {
            $transaction->loadMissing('property');
            $propertyTitle = $transaction->property?->title ?? 'the property';
            $this->notifyBuyer(
                $transaction,
                'transaction',
                'Refund Requested',
                'The landlord requested a refund for "' . $propertyTitle . '".'
            );
        }

        return $result;
    }

    /**
     * Buyer confirms they received the refund
     * This completes the refund workflow
     */
    public function confirmRefundReceived(Transaction $transaction): bool
    {
        if (!$transaction->canBeRefunded()) {
            throw ValidationException::withMessages([
                'status' => 'Refund must be requested before confirmation.',
            ]);
        }

        $result = $transaction->confirmRefund();

        if ($result) {
            $transaction->loadMissing('property');
            $propertyTitle = $transaction->property?->title ?? 'your property';
            $this->notifyLandlord(
                $transaction,
                'transaction',
                'Refund Confirmed',
                'The buyer confirmed the refund for "' . $propertyTitle . '".'
            );
        }

        return $result;
    }

    private function notifyNewTransactionRequest(Transaction $transaction, Property $property): void
    {
        $transaction->loadMissing('user');
        $propertyTitle = $property->title ?? 'a property';
        $buyerName = $transaction->user?->name ?? 'A user';
        $typeLabel = $transaction->type === 'rent' ? 'rental' : 'purchase';

        $this->notifyLandlord(
            $transaction,
            'pending',
            'New ' . ucfirst($typeLabel) . ' Request',
            $buyerName . ' sent a ' . $typeLabel . ' request for "' . $propertyTitle . '".'
        );

        User::where('role', 'admin')->each(function (User $admin) use ($transaction, $buyerName, $propertyTitle, $typeLabel) {
            $this->createNotification(
                $admin,
                'pending',
                'New ' . ucfirst($typeLabel) . ' Request',
                $buyerName . ' sent a ' . $typeLabel . ' request for "' . $propertyTitle . '".',
                $transaction
            );
        });
    }

    private function notifyLandlord(Transaction $transaction, string $type, string $title, string $message): void
    {
        $transaction->loadMissing('property.user');
        $landlord = $transaction->property?->user;

        if ($landlord) {
            $this->createNotification($landlord, $type, $title, $message, $transaction);
        }
    }

    private function notifyBuyer(Transaction $transaction, string $type, string $title, string $message): void
    {
        $transaction->loadMissing('user');

        if ($transaction->user) {
            $this->createNotification($transaction->user, $type, $title, $message, $transaction);
        }
    }

    /**
     * Get the current transaction workflow state as a descriptive string
     */
    public function getWorkflowState(Transaction $transaction): string
    {
        // State machine based on status and workflow fields
        if ($transaction->isRefunded()) {
            return 'REFUNDED';
        }

        if ($transaction->isCancelled()) {
            return strtoupper($transaction->status);
        }

        if ($transaction->isCompleted()) {
            return 'COMPLETED';
        }

        if ($transaction->isPaid()) {
            if ($transaction->isRefundRequested()) {
                return 'REFUND_REQUESTED';
            }
            return 'PAID';
        }

        if ($transaction->isConfirmed()) {
            return 'CONFIRMED';
        }

        if ($transaction->isPending()) {
            if ($transaction->isBuyerApproved()) {
                return 'BUYER_APPROVED';
            }
            if ($transaction->hasContractGenerated()) {
                return 'CONTRACT_READY_FOR_APPROVAL';
            }
            return 'PENDING';
        }

        return 'UNKNOWN';
    }

    /**
     * Get available actions for the current transaction state
     */
    public function getAvailableActions(Transaction $transaction): array
    {
        $actions = [];

        // Pending state actions
        if ($transaction->isPending() && !$transaction->hasContractGenerated()) {
            $actions[] = 'generate_contract'; // Admin action
        }

        if ($transaction->canBuyerApprove()) {
            $actions[] = 'approve_contract'; // Buyer action
            $actions[] = 'reject_contract'; // Buyer action
        }

        if ($transaction->canBeConfirmed()) {
            $actions[] = 'confirm_transaction'; // System/Admin action
        }

        if ($transaction->canBuyerCancelBeforePayment()) {
            $actions[] = 'buyer_cancel'; // Buyer action
        }

        if ($transaction->canSellerCancelBeforePayment()) {
            $actions[] = 'seller_cancel'; // Seller/Landlord action
        }

        // Confirmed state actions
        if ($transaction->canBePaid()) {
            $actions[] = 'confirm_payment'; // Seller/Landlord action
        }

        // Paid state actions
        if ($transaction->canBeCompleted()) {
            $actions[] = 'complete_transaction'; // System/Admin action
        }

        if ($transaction->canSellerInitiateRefund()) {
            $actions[] = 'request_refund'; // Seller/Landlord action
        }

        // Refund state actions
        if ($transaction->canBeRefunded()) {
            $actions[] = 'confirm_refund'; // Buyer action
        }

        return $actions;
    }
}
