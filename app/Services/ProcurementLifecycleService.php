<?php

namespace App\Services;

use App\Models\PurchaseRequest;
use App\Models\PrWorkflowLog;
use App\Models\PrGmDecision;
use App\Models\PrMarketingVariance;
use App\Models\ProcurementPayment;
use App\Models\ProcurementReceipt;
use App\Models\DriverBooking;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * ProcurementLifecycleService
 * 
 * Single-responsibility service that handles every stage transition
 * in the procurement lifecycle. Each method:
 *  1. Updates the PR status
 *  2. Logs the workflow handoff
 *  3. Sends SMS to the next role
 */
class ProcurementLifecycleService
{
    public function __construct(private ProcurementSmsService $sms) {}

    // ═══════════════════════════════════════════════════════════════════
    // STAGE 2 — Store Manager Routes MR
    // ═══════════════════════════════════════════════════════════════════

    /**
     * Store Manager decides to send MR to a Purchase Request
     */
    public function sendToProcurementManager(PurchaseRequest $pr, string $notes = null): void
    {
        $from = $pr->status;
        $pr->update([
            'status'             => PurchaseRequest::STATUS_PENDING_PROC_MANAGER,
            'current_owner_role' => 'purchase_manager',
        ]);
        $this->log($pr, $from, PurchaseRequest::STATUS_PENDING_PROC_MANAGER, 'send_to_procurement_manager', 'store_manager', $notes);
        $this->sms->notifyRole($pr->id, 'purchase_manager',
            "ConstructPro: PR #{$pr->pr_no} needs your review. Project: {$pr->project?->name}. Open: " . url("/purchase-requests/{$pr->id}"));
    }

    // ═══════════════════════════════════════════════════════════════════
    // STAGE 3 — Procurement Manager Triage
    // ═══════════════════════════════════════════════════════════════════

    public function sendBackToStoreManager(PurchaseRequest $pr, string $reason): void
    {
        $from = $pr->status;
        $pr->update([
            'status'             => PurchaseRequest::STATUS_PENDING_STORE_REVIEW,
            'pm_sendback_reason' => $reason,
            'current_owner_role' => 'store_manager',
        ]);
        $this->log($pr, $from, PurchaseRequest::STATUS_PENDING_STORE_REVIEW, 'send_back_to_store_manager', 'purchase_manager', $reason);
        $this->sms->notifyRole($pr->id, 'store_manager',
            "ConstructPro: PR #{$pr->pr_no} returned to you. Reason: {$reason}. Open: " . url("/purchase-requests/{$pr->id}"));
    }

    public function sendToProcurementTeam(PurchaseRequest $pr, string $sourcingMethod = 'proforma', string $notes = null): void
    {
        $from = $pr->status;
        $pr->update([
            'sourcing_method'    => $sourcingMethod,
            'status'             => PurchaseRequest::STATUS_PENDING_PROC_TEAM,
            'current_owner_role' => 'purchase',
        ]);
        $actionName = $sourcingMethod === 'direct_buy' ? 'send_to_proc_team_direct_buy' : 'send_to_proc_team_proforma';
        $this->log($pr, $from, PurchaseRequest::STATUS_PENDING_PROC_TEAM, $actionName, 'purchase_manager', $notes);
        
        $methodLabel = $sourcingMethod === 'direct_buy' ? 'Direct Buy (add material prices)' : 'Proforma Sourcing (collect quotes)';
        $this->sms->notifyRole($pr->id, 'purchase',
            "ConstructPro: PR #{$pr->pr_no} assigned to Procurement Team for {$methodLabel}. Open: " . url("/purchase-requests/{$pr->id}"));
    }

    // ═══════════════════════════════════════════════════════════════════
    // STAGE 4 — Procurement Team Sourcing
    // ═══════════════════════════════════════════════════════════════════

    public function submitDirectBuy(PurchaseRequest $pr, float $amount, string $notes = null, array $itemPrices = []): void
    {
        $from = $pr->status;

        // If individual item prices are supplied, update each PR item and calculate total
        if (!empty($itemPrices)) {
            $totalCalculated = 0;
            foreach ($itemPrices as $itemId => $unitCost) {
                $item = $pr->items()->find($itemId);
                if ($item) {
                    $cost = (float)$unitCost;
                    $item->update([
                        'estimated_unit_cost' => $cost,
                        'estimated_total'     => round($cost * (float)$item->quantity, 2),
                    ]);
                    $totalCalculated += ($cost * (float)$item->quantity);
                }
            }
            if ($amount <= 0 && $totalCalculated > 0) {
                $amount = round($totalCalculated, 2);
            }
        }

        $pr->update([
            'sourcing_method'       => 'direct_buy',
            'direct_buy_amount'     => $amount,
            'direct_buy_added_by'   => Auth::id(),
            'procurement_team_notes'=> $notes,
            'status'                => PurchaseRequest::STATUS_PENDING_MARKETING,
            'current_owner_role'    => 'market_research',
        ]);
        $this->log($pr, $from, PurchaseRequest::STATUS_PENDING_MARKETING, 'submit_direct_buy_pricing', 'purchase', $notes);
        $this->sms->notifyRole($pr->id, 'market_research',
            "ConstructPro: PR #{$pr->pr_no} needs marketing price variance. Amount: " . number_format($amount, 2) . " ETB. Open: " . url("/purchase-requests/{$pr->id}"));
    }

    public function submitProformas(PurchaseRequest $pr, string $notes = null): void
    {
        $from = $pr->status;
        $pr->update([
            'sourcing_method'        => 'proforma',
            'procurement_team_notes' => $notes,
            'status'                 => PurchaseRequest::STATUS_PENDING_PROFORMA_SELECTION,
            'current_owner_role'     => 'purchase_manager',
        ]);
        $this->log($pr, $from, PurchaseRequest::STATUS_PENDING_PROFORMA_SELECTION, 'submit_proformas', 'purchase', $notes);
        $this->sms->notifyRole($pr->id, 'purchase_manager',
            "ConstructPro: PR #{$pr->pr_no} proformas submitted — please review and select. Open: " . url("/purchase-requests/{$pr->id}"));
    }

    // ═══════════════════════════════════════════════════════════════════
    // STAGE 5a — Marketing Variance
    // ═══════════════════════════════════════════════════════════════════

    public function addMarketingVariance(PurchaseRequest $pr, array $data): void
    {
        $from = $pr->status;
        PrMarketingVariance::create([
            'purchase_request_id' => $pr->id,
            'market_price'        => $data['market_price'] ?? null,
            'variance_amount'     => $data['variance_amount'] ?? null,
            'variance_percentage' => $data['variance_percentage'] ?? null,
            'variance_notes'      => $data['variance_notes'] ?? null,
            'added_by'            => Auth::id(),
        ]);
        $pr->update([
            'status'             => PurchaseRequest::STATUS_PENDING_GM,
            'current_owner_role' => 'gm',
        ]);
        $this->log($pr, $from, PurchaseRequest::STATUS_PENDING_GM, 'add_marketing_variance', 'market_research', $data['variance_notes'] ?? null);
        $this->sms->notifyRole($pr->id, 'gm',
            "ConstructPro: PR #{$pr->pr_no} awaits your decision (Direct Buy + Marketing Review). Open: " . url("/purchase-requests/{$pr->id}"));
    }

    // ═══════════════════════════════════════════════════════════════════
    // STAGE 5b — Proforma Selection
    // ═══════════════════════════════════════════════════════════════════

    public function sendProformasToGm(PurchaseRequest $pr, array $selectedProformaIds, string $notes = null): void
    {
        // Mark selected proformas
        $pr->proformaInvoices()->whereIn('id', $selectedProformaIds)->update(['gm_selected' => true]);
        $pr->proformaInvoices()->whereNotIn('id', $selectedProformaIds)->update(['gm_selected' => false]);

        $from = $pr->status;
        $pr->update([
            'status'             => PurchaseRequest::STATUS_PENDING_GM,
            'current_owner_role' => 'gm',
        ]);
        $this->log($pr, $from, PurchaseRequest::STATUS_PENDING_GM, 'send_proformas_to_gm', 'purchase_manager', $notes);
        $this->sms->notifyRole($pr->id, 'gm',
            "ConstructPro: PR #{$pr->pr_no} proformas ready for your decision. Open: " . url("/purchase-requests/{$pr->id}"));
    }

    // ═══════════════════════════════════════════════════════════════════
    // STAGE 6 — GM Decision
    // ═══════════════════════════════════════════════════════════════════

    public function gmDecide(PurchaseRequest $pr, string $decision, string $paymentMethod = null, string $notes = null): void
    {
        $round = ($pr->gm_loop_count ?? 0) + 1;
        $from  = $pr->status;

        PrGmDecision::create([
            'purchase_request_id' => $pr->id,
            'round'               => $round,
            'decision'            => $decision,
            'payment_method'      => $paymentMethod,
            'notes'               => $notes,
            'decided_by'          => Auth::id(),
            'decided_at'          => now(),
        ]);

        if ($decision === 'reject') {
            $pr->update([
                'status'          => PurchaseRequest::STATUS_REJECTED,
                'gm_loop_count'   => $round,
                'rejection_reason'=> $notes,
                'current_owner_role' => null,
            ]);
            $this->log($pr, $from, PurchaseRequest::STATUS_REJECTED, 'gm_reject', 'gm', $notes);
            $this->sms->notifyRole($pr->id, 'purchase_manager',
                "ConstructPro: PR #{$pr->pr_no} was REJECTED by GM. Reason: {$notes}. Open: " . url("/purchase-requests/{$pr->id}"));

        } elseif ($decision === 'send_back') {
            $pr->update([
                'status'        => PurchaseRequest::STATUS_PENDING_PROC_MANAGER,
                'gm_loop_count' => $round,
                'current_owner_role' => 'purchase_manager',
            ]);
            $this->log($pr, $from, PurchaseRequest::STATUS_PENDING_PROC_MANAGER, 'gm_send_back', 'gm', $notes);
            $this->sms->notifyRole($pr->id, 'purchase_manager',
                "ConstructPro: PR #{$pr->pr_no} returned by GM for revision. Notes: {$notes}. Open: " . url("/purchase-requests/{$pr->id}"));

        } elseif ($decision === 'approve') {
            if ($paymentMethod === 'buy_by_credit') {
                $nextStatus   = PurchaseRequest::STATUS_PENDING_FINANCE;
                $nextRole     = 'finance_head';
                $smsMessage   = "ConstructPro: PR #{$pr->pr_no} approved (Credit) — please authorize the credit account. Open: " . url("/purchase-requests/{$pr->id}");
            } else { // pay_and_buy
                $nextStatus   = PurchaseRequest::STATUS_PENDING_PAYMENT;
                $nextRole     = 'finance_head';
                $smsMessage   = "ConstructPro: PR #{$pr->pr_no} approved (Pay & Buy) — please select payment account and assign staff. Open: " . url("/purchase-requests/{$pr->id}");
            }
            $pr->update([
                'status'             => $nextStatus,
                'gm_loop_count'      => $round,
                'current_owner_role' => $nextRole,
            ]);
            $this->log($pr, $from, $nextStatus, 'gm_approve_' . $paymentMethod, 'gm', $notes);
            $this->sms->notifyRole($pr->id, $nextRole, $smsMessage);
        }
    }

    // ═══════════════════════════════════════════════════════════════════
    // STAGE 7a — Finance Head: Credit Path
    // ═══════════════════════════════════════════════════════════════════

    public function financeCreditApprove(PurchaseRequest $pr, int $coaAccountId, float $amount, string $notes = null): void
    {
        $from = $pr->status;

        ProcurementPayment::create([
            'purchase_request_id' => $pr->id,
            'method'              => 'credit',
            'coa_account_id'      => $coaAccountId,
            'amount'              => $amount,
            'notes'               => $notes,
            'status'              => 'paid', // credit is authorized immediately
            'created_by'          => Auth::id(),
            'paid_by'             => Auth::id(),
            'paid_at'             => now(),
        ]);

        // Create journal entry: Debit → Procurement Expense COA; Credit → Liability/Payable COA
        $this->createJournalEntry($pr, $coaAccountId, $amount, 'credit');

        $pr->update([
            'status'             => PurchaseRequest::STATUS_PENDING_DRIVER,
            'current_owner_role' => 'general_service',
        ]);
        $this->log($pr, $from, PurchaseRequest::STATUS_PENDING_DRIVER, 'finance_credit_approved', 'finance_head', $notes);
        $this->sms->notifyRole($pr->id, 'general_service',
            "ConstructPro: PR #{$pr->pr_no} ready for delivery — please book a driver. Open: " . url("/purchase-requests/{$pr->id}"));
    }

    // ═══════════════════════════════════════════════════════════════════
    // STAGE 7b — Finance Head: Cash Path — Assign Staff
    // ═══════════════════════════════════════════════════════════════════

    public function financeHeadAssignPayment(PurchaseRequest $pr, int $coaAccountId, float $amount, int $staffUserId, string $notes = null): void
    {
        $from = $pr->status;

        ProcurementPayment::create([
            'purchase_request_id'      => $pr->id,
            'method'                   => 'cash',
            'coa_account_id'           => $coaAccountId,
            'amount'                   => $amount,
            'assigned_finance_staff_id'=> $staffUserId,
            'notes'                    => $notes,
            'status'                   => 'pending_payment',
            'created_by'               => Auth::id(),
        ]);

        $pr->update([
            'status'             => PurchaseRequest::STATUS_PENDING_PAYMENT,
            'current_owner_role' => 'finance',
        ]);
        $this->log($pr, $from, PurchaseRequest::STATUS_PENDING_PAYMENT, 'finance_head_assign_payment', 'finance_head', $notes);

        // SMS to the specific staff member assigned
        $staff = \App\Models\User::with('employee')->find($staffUserId);
        $phone = $staff?->employee?->phone;
        if ($phone) {
            $this->sms->send($pr->id, $phone, 'finance',
                "ConstructPro: PR #{$pr->pr_no} payment of " . number_format($amount, 2) . " ETB assigned to you. Open: " . url("/purchase-requests/{$pr->id}"));
        }
    }

    // ═══════════════════════════════════════════════════════════════════
    // STAGE 7b continued — Finance Staff Executes Payment
    // ═══════════════════════════════════════════════════════════════════

    public function financeStaffPay(PurchaseRequest $pr, string $notes = null): void
    {
        $from    = $pr->status;
        $payment = $pr->payment;

        $payment->update([
            'status'  => 'paid',
            'paid_by' => Auth::id(),
            'paid_at' => now(),
            'notes'   => $notes ?? $payment->notes,
        ]);

        // Create journal entry: Debit → Procurement Expense; Credit → Cash/Bank COA
        $this->createJournalEntry($pr, $payment->coa_account_id, $payment->amount, 'cash');

        $pr->update([
            'status'             => PurchaseRequest::STATUS_PENDING_RECEIPT_UPLOAD,
            'current_owner_role' => 'purchase',
        ]);
        $this->log($pr, $from, PurchaseRequest::STATUS_PENDING_RECEIPT_UPLOAD, 'finance_staff_paid', 'finance', $notes);
        $this->sms->notifyRole($pr->id, 'purchase',
            "ConstructPro: PR #{$pr->pr_no} payment done — please upload the purchase receipt. Open: " . url("/purchase-requests/{$pr->id}"));
    }

    // ═══════════════════════════════════════════════════════════════════
    // STAGE 8 — Receipt Upload & Verification
    // ═══════════════════════════════════════════════════════════════════

    public function uploadReceipt(PurchaseRequest $pr, string $filePath, string $originalFilename, string $notes = null): void
    {
        $from = $pr->status;

        ProcurementReceipt::create([
            'purchase_request_id' => $pr->id,
            'file_path'           => $filePath,
            'original_filename'   => $originalFilename,
            'notes'               => $notes,
            'uploaded_by'         => Auth::id(),
            'verification_status' => 'pending',
        ]);

        $pr->update([
            'status'             => PurchaseRequest::STATUS_PENDING_RECEIPT_VERIFY,
            'current_owner_role' => 'finance',
        ]);
        $this->log($pr, $from, PurchaseRequest::STATUS_PENDING_RECEIPT_VERIFY, 'receipt_uploaded', 'purchase', $notes);
        $this->sms->notifyRole($pr->id, 'finance',
            "ConstructPro: PR #{$pr->pr_no} receipt uploaded — please verify. Open: " . url("/purchase-requests/{$pr->id}"));
    }

    public function verifyReceipt(PurchaseRequest $pr, string $verificationStatus, string $verificationNotes = null): void
    {
        $from    = $pr->status;
        $receipt = $pr->receipt;

        $receipt->update([
            'verification_status' => $verificationStatus,
            'verification_notes'  => $verificationNotes,
            'verified_by'         => Auth::id(),
            'verified_at'         => now(),
        ]);

        if ($verificationStatus === 'verified') {
            $pr->update([
                'status'             => PurchaseRequest::STATUS_PENDING_DRIVER,
                'current_owner_role' => 'general_service',
            ]);
            $this->log($pr, $from, PurchaseRequest::STATUS_PENDING_DRIVER, 'receipt_verified', 'finance', $verificationNotes);
            $this->sms->notifyRole($pr->id, 'general_service',
                "ConstructPro: PR #{$pr->pr_no} receipt verified — please book a driver for delivery. Open: " . url("/purchase-requests/{$pr->id}"));
        } else {
            // Rejected receipt: send back to Procurement Team to re-upload
            $pr->update([
                'status'             => PurchaseRequest::STATUS_PENDING_RECEIPT_UPLOAD,
                'current_owner_role' => 'purchase',
            ]);
            $this->log($pr, $from, PurchaseRequest::STATUS_PENDING_RECEIPT_UPLOAD, 'receipt_rejected', 'finance', $verificationNotes);
            $this->sms->notifyRole($pr->id, 'purchase',
                "ConstructPro: PR #{$pr->pr_no} receipt rejected — please re-upload. Reason: {$verificationNotes}. Open: " . url("/purchase-requests/{$pr->id}"));
        }
    }

    // ═══════════════════════════════════════════════════════════════════
    // STAGE 9 — Driver Booking
    // ═══════════════════════════════════════════════════════════════════

    public function bookDriver(PurchaseRequest $pr, int $driverEmployeeId, string $vehicleNumber = null, string $vehicleDescription = null, $scheduledAt = null, string $notes = null): void
    {
        $from = $pr->status;

        DriverBooking::create([
            'purchase_request_id' => $pr->id,
            'driver_employee_id'  => $driverEmployeeId,
            'vehicle_number'      => $vehicleNumber,
            'vehicle_description' => $vehicleDescription,
            'scheduled_at'        => $scheduledAt,
            'booking_notes'       => $notes,
            'booked_by'           => Auth::id(),
        ]);

        $pr->update([
            'status'             => PurchaseRequest::STATUS_PENDING_STORE_REVIEW, // Store Manager does intake
            'current_owner_role' => 'store_manager',
        ]);
        $this->log($pr, $from, PurchaseRequest::STATUS_PENDING_STORE_REVIEW, 'driver_booked', 'general_service', $notes);
        $this->sms->notifyRole($pr->id, 'store_manager',
            "ConstructPro: PR #{$pr->pr_no} driver booked — please perform final intake once goods arrive. Open: " . url("/purchase-requests/{$pr->id}"));
    }

    // ═══════════════════════════════════════════════════════════════════
    // STAGE 9 Final — Store Intake
    // ═══════════════════════════════════════════════════════════════════

    public function storeIntake(PurchaseRequest $pr, string $notes = null): void
    {
        $from = $pr->status;
        $pr->update([
            'status'             => PurchaseRequest::STATUS_INTAKE_COMPLETE,
            'current_owner_role' => null,
        ]);
        $this->log($pr, $from, PurchaseRequest::STATUS_INTAKE_COMPLETE, 'store_intake_complete', 'store_manager', $notes);
        // Notify coordinator who originally requested
        $requester = $pr->requestedBy;
        $phone = $requester?->employee?->phone;
        if ($phone) {
            $this->sms->send($pr->id, $phone, 'coordinator',
                "ConstructPro: Your PR #{$pr->pr_no} has been received and intake is complete. Project: {$pr->project?->name}.");
        }
    }

    // ═══════════════════════════════════════════════════════════════════
    // HELPERS
    // ═══════════════════════════════════════════════════════════════════

    private function log(PurchaseRequest $pr, ?string $from, string $to, string $action, string $actorRole, ?string $notes): void
    {
        PrWorkflowLog::create([
            'purchase_request_id' => $pr->id,
            'from_stage'          => $from,
            'to_stage'            => $to,
            'action'              => $action,
            'actor_role'          => $actorRole,
            'notes'               => $notes,
            'actor_id'            => Auth::id(),
            'created_at'          => now(),
        ]);
    }

    /**
     * Create a double-entry journal for procurement payment.
     * Debit: Procurement Expense account (passed coaAccountId)
     * Credit: Cash/Payable (same COA — Finance Head decides)
     */
    private function createJournalEntry(PurchaseRequest $pr, int $coaAccountId, float $amount, string $method): void
    {
        try {
            DB::transaction(function () use ($pr, $coaAccountId, $amount, $method) {
                $entryNo = 'PROC-' . date('Ymd') . '-' . str_pad(JournalEntry::count() + 1, 5, '0', STR_PAD_LEFT);

                $entry = JournalEntry::create([
                    'entry_no'       => $entryNo,
                    'entry_date'     => now()->toDateString(),
                    'reference_type' => 'procurement_payment',
                    'reference_id'   => $pr->id,
                    'description'    => "Procurement payment for PR #{$pr->pr_no} ({$method})",
                    'status'         => 'posted',
                    'created_by'     => Auth::id(),
                    'posted_at'      => now(),
                ]);

                // Debit the selected COA (expense side)
                JournalEntryLine::create([
                    'journal_entry_id' => $entry->id,
                    'account_id'       => $coaAccountId,
                    'side'             => 'debit',
                    'amount'           => $amount,
                    'description'      => "PR #{$pr->pr_no} — procurement debit",
                ]);

                // Credit the same COA balance (Finance Head's selection represents the funding source)
                JournalEntryLine::create([
                    'journal_entry_id' => $entry->id,
                    'account_id'       => $coaAccountId,
                    'side'             => 'credit',
                    'amount'           => $amount,
                    'description'      => "PR #{$pr->pr_no} — procurement credit ({$method})",
                ]);

                // Update COA current_balance
                \App\Models\ChartOfAccount::where('id', $coaAccountId)
                    ->decrement('current_balance', $amount);

                // Link journal entry to payment record
                $pr->payment?->update(['journal_entry_id' => $entry->id]);
            });
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error("ProcurementJournalEntry error: " . $e->getMessage());
        }
    }
}
