<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use App\Models\Property;
use App\Services\TransactionWorkflowService;
use App\Services\PropertyInteractionService;
use App\Http\Requests\StoreTransactionRequest;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class TransactionController extends Controller
{
    protected TransactionWorkflowService $workflowService;

    public function __construct(
        TransactionWorkflowService $workflowService,
        protected PropertyInteractionService $interactionService,
    ) {
        $this->workflowService = $workflowService;
    }

    public function store(StoreTransactionRequest $request)
    {
        $data = $request->validated();

        try {
            if ($data['type'] === 'rent') {
                $transaction = $this->workflowService->createRentalRequest(
                    userId: auth()->id(),
                    propertyId: $data['property_id'],
                    startDate: $data['start_date'],
                    endDate: $data['end_date'],
                    rulesAccepted: $data['rules_accepted'] ?? false,
                    rulesExceptions: $data['rules_exceptions'] ?? null,
                    notes: $data['notes'] ?? null
                );
            } else {
                $transaction = $this->workflowService->createPurchaseRequest(
                    userId: auth()->id(),
                    propertyId: $data['property_id'],
                    notes: $data['notes'] ?? null
                );
            }

            $property = Property::find($data['property_id']);
            if ($property) {
                $this->interactionService->recordContact(auth()->id(), $property, [
                    'channel' => 'transaction_request',
                    'type' => $data['type'],
                ]);
            }

            // If the client expects JSON (AJAX), keep JSON response.
            if ($request->wantsJson()) {
                return response()->json([
                    'message' => 'Transaction request created successfully',
                    'transaction' => $transaction,
                ], 201);
            }

            // For normal form submissions, redirect back with a success message.
            return redirect()
                ->back()
                ->with('success', 'Transaction request created successfully.');
        } catch (ValidationException $e) {
            if ($request->wantsJson()) {
                return response()->json([
                    'errors' => $e->errors(),
                ], 422);
            }

            return redirect()
                ->back()
                ->withErrors($e->errors())
                ->withInput();
        }
    }

    /**
     * Get transaction details with available actions.
     * Browser: full report view for buyer to review and approve contract.
     * JSON: API response for workflow/actions.
     */
    public function show(Transaction $transaction)
    {
        // Keep status fresh even if the scheduler has not run yet.
        if ($transaction->isPending() && $transaction->type === 'rent') {
            $this->workflowService->cancelExpiredPendingTransaction($transaction);
            $transaction->refresh();
        }

        $transaction->load([
            'user',
            'property.amenities',
            'property.rules',
            'property.images' => fn ($q) => $q->orderByDesc('is_primary')->limit(1),
        ]);

        if (request()->wantsJson()) {
            return response()->json([
                'transaction' => $transaction,
                'workflow_state' => $this->workflowService->getWorkflowState($transaction),
                'available_actions' => $this->workflowService->getAvailableActions($transaction),
            ]);
        }

        $this->authorizeViewTransaction($transaction);

        $canApprove = auth()->id() == $transaction->user_id && $transaction->canBuyerApprove();
        $isBuyer = auth()->id() == $transaction->user_id;

        return view('transactions.show', compact('transaction', 'canApprove', 'isBuyer'));
    }

    /**
     * Ensure the user can view this transaction (buyer or property owner/landlord).
     */
    private function authorizeViewTransaction(Transaction $transaction): void
    {
        if (auth()->check() && auth()->user()->isAdmin()) {
            return;
        }
        $userId = auth()->id();
        $isBuyer = $transaction->user_id == $userId;
        $isLandlord = $transaction->property && $transaction->property->user_id == $userId;
        if (!$isBuyer && !$isLandlord) {
            abort(403, 'You do not have access to this transaction.');
        }
    }

    /**
     * Admin generates contract for transaction
     * POST /transactions/{transaction}/generate-contract
     */
    public function generateContract(Request $request, Transaction $transaction)
    {
        $contractPath = $request->input('contract_path', "contracts/transaction_{$transaction->id}.pdf");

        try {
            $request->validate([
                'contract_path' => 'nullable|string|max:500',
            ]);

            $this->workflowService->generateContract($transaction, $contractPath);

            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Contract generated successfully',
                    'transaction' => $transaction->fresh(),
                ]);
            }

            $buyerViewUrl = route('transactions.show', $transaction);
return redirect()->back()->with('success', "Contract generated. The buyer can view the full report and approve it here: {$buyerViewUrl}");
        } catch (ValidationException $e) {
            if ($request->expectsJson()) {
                return response()->json(['errors' => $e->errors()], 422);
            }
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    /**
     * Buyer approves contract
     * POST /transactions/{transaction}/approve-contract
     */
    public function approveContract(Request $request, Transaction $transaction)
    {
        if ($transaction->user_id != auth()->id()) {
            abort(403, 'Only the buyer can approve this contract.');
        }

        try {
            $this->workflowService->approveContract($transaction);

            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Contract approved successfully',
                    'transaction' => $transaction->fresh(),
                ]);
            }
            return redirect()->route('transactions.show', $transaction)
                ->with('success', 'You have approved the contract. The landlord will confirm next.');
        } catch (ValidationException $e) {
            if ($request->expectsJson()) {
                return response()->json(['errors' => $e->errors()], 422);
            }
            return redirect()->route('transactions.show', $transaction)
                ->with('error', $e->getMessage());
        }
    }

    /**
     * Buyer rejects contract
     * POST /transactions/{transaction}/reject-contract
     */
    public function rejectContract(Request $request, Transaction $transaction)
    {
        if ($transaction->user_id != auth()->id()) {
            abort(403, 'Only the buyer can reject this contract.');
        }

        try {
            $request->validate([
                'reason' => 'nullable|string|max:500',
            ]);

            $this->workflowService->rejectContract($transaction, $request->input('reason'));

            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Contract rejected successfully',
                    'transaction' => $transaction->fresh(),
                ]);
            }
            return redirect()->route('transactions.show', $transaction)
                ->with('success', 'You have rejected the contract. The transaction has been cancelled.');
        } catch (ValidationException $e) {
            if ($request->expectsJson()) {
                return response()->json(['errors' => $e->errors()], 422);
            }
            return redirect()->route('transactions.show', $transaction)
                ->with('error', $e->getMessage());
        }
    }

    /**
     * Confirm transaction (after buyer approves contract)
     * POST /transactions/{transaction}/confirm
     */
    public function confirm(Transaction $transaction)
    {
        try {
            $this->workflowService->confirmTransaction($transaction);

            return response()->json([
                'message' => 'Transaction confirmed successfully',
                'transaction' => $transaction->fresh(),
            ]);
        } catch (ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        }
    }

    /**
     * Seller confirms payment received
     * POST /transactions/{transaction}/confirm-payment
     */
    public function confirmPayment(Transaction $transaction)
    {
        try {
            $this->workflowService->confirmPayment($transaction);

            return response()->json([
                'message' => 'Payment confirmed successfully',
                'transaction' => $transaction->fresh(),
            ]);
        } catch (ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        }
    }

    /**
     * Complete transaction
     * POST /transactions/{transaction}/complete
     */
    public function complete(Transaction $transaction)
    {
        try {
            $this->workflowService->completeTransaction($transaction);

            return response()->json([
                'message' => 'Transaction completed successfully',
                'transaction' => $transaction->fresh(),
            ]);
        } catch (ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        }
    }

    /**
     * Buyer cancels before payment
     * POST /transactions/{transaction}/cancel
     */
    public function cancelByBuyer(Request $request, Transaction $transaction)
    {
        try {
            $request->validate([
                'reason' => 'nullable|string|max:500',
            ]);

            $this->workflowService->buyerCancelBeforePayment($transaction, $request->reason);

            return response()->json([
                'message' => 'Transaction cancelled successfully',
                'transaction' => $transaction->fresh(),
            ]);
        } catch (ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        }
    }

    /**
     * Seller cancels before payment
     * POST /transactions/{transaction}/cancel-seller
     */
    public function cancelBySeller(Request $request, Transaction $transaction)
    {
        try {
            $request->validate([
                'reason' => 'nullable|string|max:500',
            ]);

            $this->workflowService->sellerCancelBeforePayment($transaction, $request->reason);

            return response()->json([
                'message' => 'Transaction cancelled by seller',
                'transaction' => $transaction->fresh(),
            ]);
        } catch (ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        }
    }

    /**
     * Seller requests refund (after payment received)
     * POST /transactions/{transaction}/request-refund
     */
    public function requestRefund(Request $request, Transaction $transaction)
    {
        try {
            $request->validate([
                'reason' => 'nullable|string|max:500',
            ]);

            $this->workflowService->requestRefund($transaction, $request->reason);

            return response()->json([
                'message' => 'Refund requested successfully',
                'transaction' => $transaction->fresh(),
            ]);
        } catch (ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        }
    }

    /**
     * Buyer confirms refund received
     * POST /transactions/{transaction}/confirm-refund
     */
    public function confirmRefund(Transaction $transaction)
    {
        try {
            $this->workflowService->confirmRefundReceived($transaction);

            return response()->json([
                'message' => 'Refund confirmation received',
                'transaction' => $transaction->fresh(),
            ]);
        } catch (ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        }
    }

    /**
     * Download transaction report/contract
     * GET /transactions/{transaction}/download-report
     */
    public function downloadReport(Transaction $transaction)
    {
        $property = $transaction->property;
        $buyer = $transaction->user;
        $landlord = $property->landlord;

        // Generate HTML report
        $html = $this->generateTransactionReport($transaction);

        return response($html, 200)
            ->header('Content-Type', 'text/html; charset=utf-8')
            ->header('Content-Disposition', 'inline; filename="transaction_' . $transaction->id . '.html"');
    }

    /**
     * Generate HTML transaction report
     */
    private function generateTransactionReport(Transaction $transaction): string
    {
        $property = $transaction->property;
        $buyer = $transaction->user;
        $landlord = $property->landlord;

        // Precompute for HTML (heredoc cannot reliably call $this->method() — parsed as property access).
        $typeLabelForReport = $this->getTransactionTypeLabel($transaction);
        $statusBadgeClass = $transaction->paid ? 'status-paid' : 'status-'.preg_replace('/[^a-z0-9_-]/i', '', $transaction->status);
        $statusBadgeText = $transaction->paid ? 'Payment Received' : $this->getStatusLabel($transaction->status);
        $priceDisplay = '$'.number_format((float) $transaction->price, 2).' '.($transaction->currency ?? 'USD');
        $roomDetailsHtml = $this->getRoomDetails($property);
        $rentalDatesHtml = $this->getRentalDates($transaction);
        $rulesNotesHtml = $this->getRulesAndNotes($transaction);
        $timelineHtml = $this->getTimeline($transaction);
        $printedAt = now()->format('M d, Y H:i A');

        $html = <<<HTML
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Transaction Report - {$transaction->id}</title>
            <style>
                @page {
                    size: A4 portrait;
                    margin: 10mm;
                }

                :root {
                    --a4-width: 210mm;
                    --a4-height: 297mm;
                    --a4-pad-x: 12mm;
                    --a4-pad-y: 12mm;
                }

                * { margin: 0; padding: 0; box-sizing: border-box; }

                html {
                    -webkit-print-color-adjust: exact;
                    print-color-adjust: exact;
                }

                body {
                    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
                    font-size: 10pt;
                    color: #333;
                    line-height: 1.45;
                    background: #e8edf3;
                    margin: 0;
                }

                .report-viewport {
                    min-height: 100vh;
                    min-height: 100dvh;
                    display: flex;
                    justify-content: center;
                    align-items: flex-start;
                    padding: 16px;
                }

                .a4-scaler { flex-shrink: 0; }

                .a4-page {
                    width: var(--a4-width);
                    min-height: var(--a4-height);
                    max-width: var(--a4-width);
                    padding: var(--a4-pad-y) var(--a4-pad-x);
                    background: #fff;
                    box-shadow: 0 8px 32px rgba(15, 23, 42, 0.12);
                }

                .header {
                    border-bottom: 2px solid #667eea;
                    padding-bottom: 10px;
                    margin-bottom: 14px;
                }

                .header h1 {
                    color: #667eea;
                    font-size: 20pt;
                    margin-bottom: 6px;
                    line-height: 1.2;
                }

                .header .meta { color: #666; font-size: 9pt; }

                .section { margin-bottom: 14px; }

                .section-title {
                    font-size: 11pt;
                    font-weight: 700;
                    color: #333;
                    margin-bottom: 8px;
                    padding-bottom: 6px;
                    border-bottom: 1px solid #e8e8e8;
                }

                .info-row {
                    display: flex;
                    gap: 8px;
                    margin-bottom: 6px;
                    align-items: flex-start;
                }

                .info-label {
                    width: 34%;
                    min-width: 90px;
                    font-weight: 600;
                    color: #666;
                    font-size: 9pt;
                }

                .info-value {
                    flex: 1;
                    color: #333;
                    font-size: 9.5pt;
                    word-break: break-word;
                }

                .info-box {
                    background: #f9f9f9;
                    padding: 8px 10px;
                    border-radius: 4px;
                    border-left: 3px solid #667eea;
                    margin: 6px 0;
                    font-size: 9pt;
                }

                .info-box strong { font-size: 9.5pt; }

                .status-badge {
                    display: inline-block;
                    padding: 3px 8px;
                    border-radius: 4px;
                    font-size: 8pt;
                    font-weight: 600;
                }

                .status-pending { background: #fff3cd; color: #856404; }
                .status-confirmed { background: #d1ecf1; color: #0c5460; }
                .status-paid { background: #d4edda; color: #155724; }
                .status-completed { background: #d4edda; color: #155724; }

                .table {
                    width: 100%;
                    border-collapse: collapse;
                    margin: 8px 0;
                    font-size: 8.5pt;
                }

                .table th, .table td {
                    padding: 5px 6px;
                    text-align: left;
                    border-bottom: 1px solid #e0e0e0;
                }

                .table th { background: #f5f5f5; font-weight: 600; }

                .footer {
                    text-align: center;
                    color: #999;
                    font-size: 8pt;
                    margin-top: 16px;
                    padding-top: 10px;
                    border-top: 1px solid #e0e0e0;
                }

                .print-date { text-align: right; color: #999; font-size: 8pt; margin-top: 4px; }

                @media screen and (max-width: 900px) {
                    .report-viewport {
                        padding: 8px;
                        overflow-x: hidden;
                    }

                    .a4-page {
                        width: min(var(--a4-width), calc(100vw - 16px));
                        max-width: min(var(--a4-width), calc(100vw - 16px));
                        min-height: calc(min(var(--a4-width), calc(100vw - 16px)) * 297 / 210);
                        padding: 5mm 5mm;
                        font-size: 6.5pt;
                        line-height: 1.35;
                    }

                    .header { margin-bottom: 10px; padding-bottom: 6px; }
                    .header h1 { font-size: 11pt; margin-bottom: 4px; }
                    .header .meta { font-size: 6pt; }
                    .section { margin-bottom: 8px; }
                    .section-title { font-size: 7.5pt; margin-bottom: 5px; padding-bottom: 4px; }
                    .info-row { margin-bottom: 4px; gap: 4px; }
                    .info-label { width: 36%; min-width: 0; font-size: 6pt; }
                    .info-value { font-size: 6.5pt; }
                    .info-box { padding: 5px 6px; margin: 4px 0; font-size: 6pt; }
                    .info-box strong { font-size: 6.5pt; }
                    .status-badge { padding: 2px 5px; font-size: 5.5pt; }
                    .table { margin: 5px 0; font-size: 6pt; }
                    .table th, .table td { padding: 3px 4px; }
                    .footer { margin-top: 10px; padding-top: 6px; font-size: 5.5pt; }
                    .print-date { font-size: 5.5pt; }
                }

                @media print {
                    body { background: #fff; }
                    .report-viewport { padding: 0; min-height: auto; }
                    .a4-page {
                        width: 100%;
                        max-width: none;
                        min-height: auto;
                        margin: 0;
                        padding: 0;
                        box-shadow: none;
                    }
                }
            </style>
        </head>
        <body>
            <div class="report-viewport">
                <div class="a4-scaler">
                    <div class="a4-page">
                <div class="header">
                    <h1>Transaction Report</h1>
                    <div class="meta">Transaction ID: #{$transaction->id}</div>
                    <div class="meta">Generated: {$transaction->created_at->format('M d, Y H:i A')}</div>
                </div>

                <div class="section">
                    <h2 class="section-title">Transaction Details</h2>
                    <div class="info-row">
                        <span class="info-label">Type:</span>
                        <span class="info-value">{$typeLabelForReport}</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Status:</span>
                        <span class="info-value"><span class="status-badge {$statusBadgeClass}">{$statusBadgeText}</span></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Amount:</span>
                        <span class="info-value">{$priceDisplay}</span>
                    </div>
                </div>

                <div class="section">
                    <h2 class="section-title">Property Information</h2>
                    <div class="info-row">
                        <span class="info-label">Property:</span>
                        <span class="info-value">{$property->title}</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Address:</span>
                        <span class="info-value">{$property->address}, {$property->city}, {$property->country}</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Type:</span>
                        <span class="info-value">{$property->property_type}</span>
                    </div>
                    {$roomDetailsHtml}
                </div>

                <div class="section">
                    <h2 class="section-title">Parties Involved</h2>
                    <div class="info-box">
                        <strong>Buyer/Tenant:</strong>
                        <div class="info-row">
                            <span class="info-label">Name:</span>
                            <span class="info-value">{$buyer->name}</span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Email:</span>
                            <span class="info-value">{$buyer->email}</span>
                        </div>
                    </div>

                    <div class="info-box">
                        <strong>Landlord/Seller:</strong>
                        <div class="info-row">
                            <span class="info-label">Name:</span>
                            <span class="info-value">{$landlord->name}</span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Email:</span>
                            <span class="info-value">{$landlord->email}</span>
                        </div>
                    </div>
                </div>

                {$rentalDatesHtml}

                {$rulesNotesHtml}

                {$timelineHtml}

                <div class="footer">
                    <p>This is an official transaction report from Ghorfa Platform</p>
                    <div class="print-date">Printed on: {$printedAt}</div>
                </div>
                    </div>
                </div>
            </div>
        </body>
        </html>
        HTML;

        return $html;
    }

    /**
     * Get transaction type label
     */
    private function getTransactionTypeLabel(Transaction $transaction): string
    {
        return ucfirst($transaction->type . ($transaction->type === 'rent' ? 'al' : ''));
    }

    /**
     * Get status label
     */
    private function getStatusLabel(string $status): string
    {
        return match ($status) {
            'pending' => 'Pending Review',
            'paid' => 'Paid',
            'confirmed' => 'Confirmed',
            'completed' => 'Completed',
            'cancelled_by_buyer' => 'Cancelled by Buyer',
            'cancelled_by_seller' => 'Cancelled by Seller',
            'refunded' => 'Refunded',
            default => ucfirst(str_replace('_', ' ', $status)),
        };
    }

    /**
     * Get room details HTML
     */
    private function getRoomDetails(Property $property): string
    {
        $html = '';
        if ($property->bedroom_nb) {
            $html .= '<div class="info-row"><span class="info-label">Bedrooms:</span><span class="info-value">' . $property->bedroom_nb . '</span></div>';
        }
        if ($property->bathroom_nb) {
            $html .= '<div class="info-row"><span class="info-label">Bathrooms:</span><span class="info-value">' . $property->bathroom_nb . '</span></div>';
        }
        if ($property->area_m3) {
            $html .= '<div class="info-row"><span class="info-label">Area:</span><span class="info-value">' . $property->area_m3 . ' m²</span></div>';
        }
        return $html;
    }

    /**
     * Get rental dates HTML
     */
    private function getRentalDates(Transaction $transaction): string
    {
        if ($transaction->type !== 'rent') {
            return '';
        }

        $html = '<div class="section"><h2 class="section-title">Rental Period</h2>';
        if ($transaction->start_date) {
            $html .= '<div class="info-row"><span class="info-label">Check-in Date:</span><span class="info-value">' . $transaction->start_date->format('M d, Y') . '</span></div>';
        }
        if ($transaction->end_date) {
            $html .= '<div class="info-row"><span class="info-label">Check-out Date:</span><span class="info-value">' . $transaction->end_date->format('M d, Y') . '</span></div>';
        }
        $html .= '</div>';
        return $html;
    }

    /**
     * Get rules and notes HTML
     */
    private function getRulesAndNotes(Transaction $transaction): string
    {
        $html = '';
        if ($transaction->rules_accepted !== null) {
            $html .= '<div class="section"><h2 class="section-title">Property Rules</h2>';
            $html .= '<div class="info-row"><span class="info-label">Rules Accepted:</span><span class="info-value">' . ($transaction->rules_accepted ? 'Yes' : 'No') . '</span></div>';
            if ($transaction->rules_exceptions) {
                $html .= '<div class="info-box"><strong>Exceptions/Concerns:</strong><br>' . htmlspecialchars($transaction->rules_exceptions) . '</div>';
            }
            $html .= '</div>';
        }
        if ($transaction->notes) {
            $html .= '<div class="section"><h2 class="section-title">Additional Notes</h2>';
            $html .= '<div class="info-box">' . htmlspecialchars($transaction->notes) . '</div>';
            $html .= '</div>';
        }
        return $html;
    }

    /**
     * Get transaction timeline HTML
     */
    private function getTimeline(Transaction $transaction): string
    {
        $html = '<div class="section"><h2 class="section-title">Transaction Timeline</h2>';
        $html .= '<table class="table"><tbody>';

        $events = [
            ['date' => $transaction->created_at, 'label' => 'Request Submitted'],
            ['date' => $transaction->contract_generated_at, 'label' => 'Contract Generated'],
            ['date' => $transaction->buyer_approved_at, 'label' => 'Buyer Approved Contract'],
            ['date' => $transaction->paid_at, 'label' => 'Payment Received'],
            ['date' => $transaction->seller_payment_confirmed_at, 'label' => 'Seller Confirmed Payment'],
            ['date' => $transaction->refund_requested_at, 'label' => 'Refund Requested'],
            ['date' => $transaction->refund_confirmed_by_buyer_at, 'label' => 'Refund Confirmed'],
        ];

        foreach ($events as $event) {
            if ($event['date']) {
                $html .= '<tr><td><strong>' . $event['label'] . '</strong></td><td>' . $event['date']->format('M d, Y H:i A') . '</td></tr>';
            }
        }

        $html .= '</tbody></table></div>';
        return $html;
    }
}
