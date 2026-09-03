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
use App\Models\ChartOfAccount;
use App\Models\CreditStoreLedger;
use App\Models\CreditStorePayment;
use App\Models\ExpenseRequest;
use App\Models\User;
use App\Models\Inventory;
use App\Models\InventoryMovement;
use App\Models\DeliveryReceipt;
use App\Models\DeliveryReceiptItem;
use App\Models\SlipSequence;
use App\Models\Store;
use App\Models\Product;
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

    public function sendProformasToGm(PurchaseRequest $pr, array $proformaIds, ?string $notes = null): void
    {
        $from = $pr->status;

        // Reset previous selections and mark the selected proformas for GM review
        $pr->proformaInvoices()->update(['gm_selected' => false]);
        $pr->proformaInvoices()->whereIn('id', $proformaIds)->update(['gm_selected' => true]);

        $pr->update([
            'status'             => PurchaseRequest::STATUS_PENDING_GM,
            'current_owner_role' => 'gm',
        ]);

        $this->log($pr, $from, PurchaseRequest::STATUS_PENDING_GM, 'send_proformas_to_gm', 'purchase_manager', $notes);
        $this->sms->notifyRole($pr->id, 'gm',
            "ConstructPro: PR #{$pr->pr_no} awaits your decision with " . count($proformaIds) . " selected proforma quote(s). Open: " . url("/purchase-requests/{$pr->id}"));
    }

    public function gmDecide(
        PurchaseRequest $pr,
        string $decision,
        ?string $paymentMethod = 'pay_and_buy',
        string $notes = null,
        ?int $selectedProformaId = null
    ): void {
        $from = $pr->status;
        $round = ($pr->gm_loop_count ?? 0) + 1;

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
                'status'             => PurchaseRequest::STATUS_REJECTED,
                'gm_loop_count'      => $round,
                'rejection_reason'   => $notes,
                'current_owner_role' => null,
            ]);
            $this->log($pr, $from, PurchaseRequest::STATUS_REJECTED, 'gm_reject', 'gm', $notes);
            $this->sms->notifyRole($pr->id, 'purchase_manager',
                "ConstructPro: PR #{$pr->pr_no} was REJECTED by GM. Reason: {$notes}. Open: " . url("/purchase-requests/{$pr->id}"));

        } elseif ($decision === 'send_back') {
            $pr->update([
                'status'             => PurchaseRequest::STATUS_PENDING_PROC_MANAGER,
                'gm_loop_count'      => $round,
                'current_owner_role' => 'purchase_manager',
            ]);
            $this->log($pr, $from, PurchaseRequest::STATUS_PENDING_PROC_MANAGER, 'gm_send_back', 'gm', $notes);
            $this->sms->notifyRole($pr->id, 'purchase_manager',
                "ConstructPro: PR #{$pr->pr_no} returned by GM for revision. Notes: {$notes}. Open: " . url("/purchase-requests/{$pr->id}"));

        } elseif ($decision === 'approve') {
            // 1. Handle Selected Proforma Quote
            $chosenProforma = null;
            if ($selectedProformaId) {
                $chosenProforma = $pr->proformaInvoices()->find($selectedProformaId);
            }
            if (!$chosenProforma) {
                $chosenProforma = $pr->proformaInvoices()->where('gm_selected', true)->first()
                    ?? $pr->proformaInvoices()->orderBy('grand_total', 'asc')->first();
            }

            if ($chosenProforma) {
                $pr->proformaInvoices()->update(['gm_selected' => false]);
                $chosenProforma->update(['gm_selected' => true]);
                $finalAmount = (float)$chosenProforma->grand_total;
                $supplierId = $chosenProforma->supplier_id;
                $supplierName = $chosenProforma->supplier?->name ?? $chosenProforma->supplier_name;
            } else {
                $finalAmount = (float)($pr->direct_buy_amount ?? 0);
                if ($finalAmount <= 0) {
                    $finalAmount = (float)$pr->items->sum(fn($i) => (float)$i->quantity * (float)($i->estimated_unit_price ?? $i->unit_price ?? 0));
                }
                $supplierId = $pr->supplier_id;
                $supplierName = $pr->supplier?->name;
            }

            $pr->update([
                'direct_buy_amount' => $finalAmount,
                'supplier_id'       => $supplierId ?: $pr->supplier_id,
            ]);

            if ($paymentMethod === 'buy_by_credit') {
                // 1. Ensure COA 5110 "Cost Of Material By Credit 5110"
                $coa5110 = $this->ensureCreditCoaAccount();

                // 2. Auto-book ProcurementPayment
                ProcurementPayment::updateOrCreate(
                    ['purchase_request_id' => $pr->id],
                    [
                        'method'         => 'credit',
                        'coa_account_id' => $coa5110->id,
                        'amount'         => $finalAmount,
                        'notes'          => $notes ?: 'GM Approved Buy with Credit (Auto-booked COA 5110)',
                        'status'         => 'paid',
                        'created_by'     => Auth::id(),
                        'paid_by'        => Auth::id(),
                        'paid_at'        => now(),
                    ]
                );

                // 3. Create or update CreditStoreLedger
                \App\Models\CreditStoreLedger::updateOrCreate(
                    ['purchase_request_id' => $pr->id],
                    [
                        'pr_no'          => $pr->pr_no,
                        'project_id'     => $pr->project_id,
                        'supplier_name'  => $supplierName,
                        'credit_amount'  => $finalAmount,
                        'coa_account_id' => $coa5110->id,
                        'status'         => 'outstanding',
                        'authorized_by'  => Auth::id(),
                        'authorized_at'  => now(),
                        'notes'          => $notes,
                        'created_by'     => Auth::id(),
                    ]
                );

                // 5. Route directly to Store Manager for material intake
                $nextStatus   = PurchaseRequest::STATUS_PENDING_STORE_REVIEW;
                $nextRole     = 'store_manager';
                $smsMessage   = "ConstructPro: PR #{$pr->pr_no} approved (Credit — COA 5110) — ready for material intake. Open: " . url("/purchase-requests/{$pr->id}");
            } else { // pay_and_buy
                // Pre-create/update ProcurementPayment with the chosen proforma amount
                ProcurementPayment::updateOrCreate(
                    ['purchase_request_id' => $pr->id],
                    [
                        'method'         => 'cash',
                        'amount'         => $finalAmount,
                        'notes'          => $notes,
                        'status'         => 'pending_assignment',
                        'created_by'     => Auth::id(),
                    ]
                );

                // Auto-create ExpenseRequest so Finance Head sees it in Expense section
                try {
                    $expNo = str_starts_with((string)$pr->pr_no, 'PR-') ? 'EXP-' . $pr->pr_no : 'EXP-PR-' . $pr->pr_no;
                    ExpenseRequest::updateOrCreate(
                        ['purchase_request_id' => $pr->id],
                        [
                            'request_number'        => $expNo,
                            'user_id'               => Auth::id(),
                            'project_id'            => $pr->project_id,
                            'category'              => 'Material',
                            'description'           => "GM Approved Purchase Request #{$pr->pr_no}" . ($supplierName ? " — Supplier: {$supplierName}" : '') . ($notes ? ". Notes: {$notes}" : ''),
                            'amount'                => $finalAmount,
                            'gross_amount'          => $finalAmount,
                            'status'                => ExpenseRequest::STATUS_APPROVED_ASSIGNED,
                        ]
                    );
                } catch (\Throwable $e) {
                    \Illuminate\Support\Facades\Log::warning('Could not create ExpenseRequest for PR: ' . $e->getMessage());
                }

                $nextStatus   = PurchaseRequest::STATUS_PENDING_PAYMENT;
                $nextRole     = 'finance_head';
                $smsMessage   = "ConstructPro: PR #{$pr->pr_no} approved (Pay & Buy — " . number_format($finalAmount, 2) . " ETB from " . ($supplierName ?: 'Vendor') . ") — please select funding account and assign staff. Open: " . url("/purchase-requests/{$pr->id}");
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
    // STAGE 7a — Finance Head: Credit Path (Direct fallback & authorization)
    // ═══════════════════════════════════════════════════════════════════

    public function financeCreditApprove(PurchaseRequest $pr, ?int $coaAccountId = null, ?float $amount = null, string $notes = null): void
    {
        $from = $pr->status;
        $coa5110 = $coaAccountId ? ChartOfAccount::find($coaAccountId) : $this->ensureCreditCoaAccount();
        if (!$coa5110) {
            $coa5110 = $this->ensureCreditCoaAccount();
        }

        if (!$amount || $amount <= 0) {
            $amount = (float)($pr->direct_buy_amount ?? 0);
            if ($amount <= 0) {
                $amount = (float)$pr->items->sum(fn($i) => (float)$i->quantity * (float)($i->estimated_unit_price ?? $i->unit_price ?? 0));
            }
        }

        ProcurementPayment::updateOrCreate(
            ['purchase_request_id' => $pr->id],
            [
                'method'         => 'credit',
                'coa_account_id' => $coa5110->id,
                'amount'         => $amount,
                'notes'          => $notes,
                'status'         => 'paid',
                'created_by'     => Auth::id(),
                'paid_by'        => Auth::id(),
                'paid_at'        => now(),
            ]
        );

        $selectedProforma = $pr->proformaInvoices()->where('gm_selected', true)->first() 
            ?? $pr->proformaInvoices()->latest()->first();
        $supplierName = $selectedProforma ? ($selectedProforma->supplier?->name ?? $selectedProforma->supplier_name) : null;

        \App\Models\CreditStoreLedger::updateOrCreate(
            ['purchase_request_id' => $pr->id],
            [
                'pr_no'          => $pr->pr_no,
                'project_id'     => $pr->project_id,
                'supplier_name'  => $supplierName,
                'credit_amount'  => $amount,
                'coa_account_id' => $coa5110->id,
                'status'         => 'outstanding',
                'authorized_by'  => Auth::id(),
                'authorized_at'  => now(),
                'notes'          => $notes,
                'created_by'     => Auth::id(),
            ]
        );

        // Advance directly to Store Manager for material intake
        $pr->update([
            'status'             => PurchaseRequest::STATUS_PENDING_STORE_REVIEW,
            'current_owner_role' => 'store_manager',
        ]);
        $this->log($pr, $from, PurchaseRequest::STATUS_PENDING_STORE_REVIEW, 'finance_credit_approved_direct_intake', 'finance_head', $notes);
        $this->sms->notifyRole($pr->id, 'store_manager',
            "ConstructPro: PR #{$pr->pr_no} credit authorized (COA 5110) — ready for material intake. Open: " . url("/purchase-requests/{$pr->id}"));
    }

    /**
     * Ensure Chart of Account 5110 (Cost Of Material By Credit 5110) exists
     */
    public function ensureCreditCoaAccount(): ChartOfAccount
    {
        $coa = ChartOfAccount::where('code', '5110')->first();
        if (!$coa) {
            $coa = ChartOfAccount::where('name', 'like', '%Cost Of Material By Credit%')->first();
        }
        if (!$coa) {
            $coa = ChartOfAccount::create([
                'code'            => '5110',
                'name'            => 'Cost Of Material By Credit 5110',
                'type'            => 'expense',
                'subtype'         => 'direct_expense',
                'is_active'       => true,
                'is_system'       => true,
                'current_balance' => 0,
                'description'     => 'Direct credit purchases for materials and site procurement',
            ]);
        }
        return $coa;
    }

    // ═══════════════════════════════════════════════════════════════════
    // STAGE 7b — Finance Head: Cash Path — Assign Staff
    // ═══════════════════════════════════════════════════════════════════

    public function financeHeadAssignPayment(PurchaseRequest $pr, int $coaAccountId, float $amount, int $staffUserId, string $notes = null): void
    {
        $from = $pr->status;

        ProcurementPayment::updateOrCreate(
            ['purchase_request_id' => $pr->id],
            [
                'method'                    => 'cash',
                'coa_account_id'            => $coaAccountId,
                'amount'                    => $amount,
                'assigned_finance_staff_id' => $staffUserId,
                'notes'                     => $notes,
                'status'                    => 'pending_payment',
                'created_by'                => Auth::id(),
            ]
        );

        // Pre-create/update Expense record assigned to that person
        try {
            $coa = ChartOfAccount::find($coaAccountId);
            \App\Models\Expense::updateOrCreate(
                [
                    'project_id'  => $pr->project_id,
                    'description' => "Material Purchase for PR #{$pr->pr_no}",
                ],
                [
                    'category'     => 'material',
                    'amount'       => $amount,
                    'expense_date' => now()->toDateString(),
                    'status'       => 'pending',
                    'created_by'   => $staffUserId,
                    'notes'        => "Assigned by Finance Head. Funding: " . ($coa?->name ?? 'COA #' . $coaAccountId),
                ]
            );
        } catch (\Throwable $e) {}

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

        // Update Expense record to approved
        try {
            \App\Models\Expense::updateOrCreate(
                [
                    'project_id'  => $pr->project_id,
                    'description' => "Material Purchase for PR #{$pr->pr_no}",
                ],
                [
                    'category'     => 'material',
                    'amount'       => $payment->amount,
                    'expense_date' => now()->toDateString(),
                    'status'       => 'approved',
                    'created_by'   => $payment->assigned_finance_staff_id ?: Auth::id(),
                    'approved_by'  => Auth::id(),
                    'approved_at'  => now(),
                    'notes'        => "Payment executed by Finance Staff. PR #{$pr->pr_no}",
                ]
            );
        } catch (\Throwable $e) {}

        // Mark any linked ExpenseRequest record as paid so it stays synchronized
        try {
            $expReq = \App\Models\ExpenseRequest::where('purchase_request_id', $pr->id)->first();
            if ($expReq && $expReq->status !== \App\Models\ExpenseRequest::STATUS_PAID) {
                $expReq->update([
                    'status'            => \App\Models\ExpenseRequest::STATUS_PAID,
                    'paid_by'           => Auth::id(),
                    'paid_at'           => now(),
                    'payment_reference' => $notes ?? $payment->notes,
                ]);
            }
        } catch (\Throwable $e) {}

        $pr->update([
            'status'             => PurchaseRequest::STATUS_PENDING_RECEIPT_UPLOAD,
            'current_owner_role' => 'purchase',
        ]);
        $this->log($pr, $from, PurchaseRequest::STATUS_PENDING_RECEIPT_UPLOAD, 'finance_staff_paid', 'finance', $notes);
        $this->sms->notifyRole($pr->id, 'purchase',
            "ConstructPro: PR #{$pr->pr_no} payment completed (" . number_format($payment->amount, 2) . " ETB) — please upload vendor purchase receipt. Open: " . url("/purchase-requests/{$pr->id}"));
    }

    // ═══════════════════════════════════════════════════════════════════
    // STAGE 8 — Receipt Upload & Direct Store Routing
    // ═══════════════════════════════════════════════════════════════════

    public function uploadReceipt(PurchaseRequest $pr, string $filePath, string $originalFilename, string $notes = null, bool $sendToStore = true): void
    {
        $from = $pr->status;

        ProcurementReceipt::create([
            'purchase_request_id' => $pr->id,
            'file_path'           => $filePath,
            'original_filename'   => $originalFilename,
            'notes'               => $notes,
            'uploaded_by'         => Auth::id(),
            'verification_status' => 'verified',
            'verified_by'         => Auth::id(),
            'verified_at'         => now(),
        ]);

        if ($sendToStore) {
            $pr->update([
                'status'             => PurchaseRequest::STATUS_PENDING_STORE_REVIEW,
                'current_owner_role' => 'store_manager',
            ]);
            $this->log($pr, $from, PurchaseRequest::STATUS_PENDING_STORE_REVIEW, 'receipt_uploaded_sent_to_store', 'purchase', $notes);
            $this->sms->notifyRole($pr->id, 'store_manager',
                "ConstructPro: PR #{$pr->pr_no} receipt uploaded — please perform material receiving and store intake. Open: " . url("/purchase-requests/{$pr->id}"));
        } else {
            $pr->update([
                'status'             => PurchaseRequest::STATUS_PENDING_RECEIPT_VERIFY,
                'current_owner_role' => 'finance',
            ]);
            $this->log($pr, $from, PurchaseRequest::STATUS_PENDING_RECEIPT_VERIFY, 'receipt_uploaded', 'purchase', $notes);
            $this->sms->notifyRole($pr->id, 'finance',
                "ConstructPro: PR #{$pr->pr_no} receipt uploaded — please verify. Open: " . url("/purchase-requests/{$pr->id}"));
        }
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
    // STAGE 9 Final — Store Intake (Slip Sequence & Inventory Increment)
    // ═══════════════════════════════════════════════════════════════════

    public function storeIntake(
        PurchaseRequest $pr,
        ?int $storeId = null,
        ?string $slipNo = null,
        ?string $receivedDate = null,
        array $receivedItems = [],
        ?string $notes = null
    ): void {
        $from = $pr->status;
        $storeId = $storeId ?: ($pr->store_id ?: Store::where('is_active', true)->first()?->id ?: 1);
        $receivedDate = $receivedDate ?: now()->toDateString();

        DB::transaction(function () use ($pr, $storeId, $slipNo, $receivedDate, $receivedItems, $notes, $from) {
            // 1. Determine or auto-generate Slip Number if empty
            if (empty($slipNo)) {
                $sequence = SlipSequence::where('store_id', $storeId)
                    ->where('slip_type', 'receive')
                    ->where('status', 'active')
                    ->first();
                if ($sequence) {
                    $slipNo = $sequence->generateSlipNumber();
                } else {
                    $slipNo = 'REC-' . date('Ymd') . '-' . str_pad($pr->id, 4, '0', STR_PAD_LEFT);
                }
            } else {
                // If manual/given slip number, increment sequence counter if matched
                try {
                    $numericPart = (int)preg_replace('/[^0-9]/', '', $slipNo);
                    $seq = SlipSequence::where('store_id', $storeId)
                        ->where('slip_type', 'receive')
                        ->where('status', 'active')
                        ->first();
                    if ($seq && $numericPart >= $seq->current_slip_no) {
                        $seq->update([
                            'current_slip_no' => $numericPart + 1,
                            'used_count'      => $seq->used_count + 1,
                        ]);
                    }
                } catch (\Throwable $e) {}
            }

            // 2. Create or find dummy PO if required for DeliveryReceipt foreign key
            $poId = $pr->purchaseOrders()->first()?->id;
            if (!$poId) {
                try {
                    $dummyPo = \App\Models\PurchaseOrder::firstOrCreate(
                        ['po_no' => 'PR-INTAKE-' . $pr->pr_no],
                        [
                            'project_id'          => $pr->project_id,
                            'supplier_id'         => 1,
                            'purchase_request_id' => $pr->id,
                            'order_date'          => now()->toDateString(),
                            'status'              => 'delivered',
                            'total_amount'        => (float)($pr->direct_buy_amount ?? 0),
                            'created_by'          => Auth::id(),
                        ]
                    );
                    $poId = $dummyPo->id;
                } catch (\Throwable $e) {}
            }

            // 3. Create DeliveryReceipt record
            $receipt = DeliveryReceipt::create([
                'dr_no'             => $slipNo,
                'purchase_order_id' => $poId,
                'store_id'          => $storeId,
                'received_date'     => $receivedDate,
                'received_by'       => Auth::id(),
                'status'            => 'received',
                'notes'             => $notes ?: "Intake for PR #{$pr->pr_no} (Slip #{$slipNo})",
            ]);

            // 4. Process items and increment Inventory
            $prItems = $pr->items()->with('product')->get();
            foreach ($prItems as $item) {
                $itemInput = $receivedItems[$item->id] ?? [];
                $qty = isset($itemInput['quantity']) && is_numeric($itemInput['quantity']) 
                    ? (float)$itemInput['quantity'] 
                    : (float)$item->quantity;
                $acceptedQty = isset($itemInput['accepted_quantity']) && is_numeric($itemInput['accepted_quantity']) 
                    ? (float)$itemInput['accepted_quantity'] 
                    : $qty;

                if ($qty <= 0 && $acceptedQty <= 0) {
                    continue;
                }

                // Create DeliveryReceiptItem
                DeliveryReceiptItem::create([
                    'delivery_receipt_id' => $receipt->id,
                    'product_id'          => $item->product_id,
                    'quantity_received'   => $qty,
                    'accepted_quantity'   => $acceptedQty,
                    'rejected_quantity'   => max(0, $qty - $acceptedQty),
                    'unit'                => $item->unit ?? ($item->product?->unit ?? 'pcs'),
                    'rejection_reason'    => $itemInput['notes'] ?? null,
                ]);

                // Increment Inventory in selected Store
                if ($item->product_id && $acceptedQty > 0) {
                    $unitPrice = (float)($item->estimated_unit_cost ?? $item->unit_price ?? 0);
                    $inventory = Inventory::firstOrCreate(
                        [
                            'store_id'   => $storeId,
                            'product_id' => $item->product_id,
                        ],
                        [
                            'quantity_on_hand'  => 0,
                            'quantity_reserved' => 0,
                            'unit_cost'         => $unitPrice,
                            'min_stock'         => 0,
                        ]
                    );

                    $inventory->increment('quantity_on_hand', $acceptedQty);
                    $inventory->update([
                        'last_movement_at' => now(),
                        'unit_cost'        => $unitPrice > 0 ? $unitPrice : $inventory->unit_cost,
                    ]);

                    // Record Inventory Movement (Audit Log)
                    try {
                        InventoryMovement::create([
                            'inventory_id'   => $inventory->id,
                            'type'           => 'in',
                            'quantity'       => $acceptedQty,
                            'reference_type' => PurchaseRequest::class,
                            'reference_id'   => $pr->id,
                            'performed_by'   => Auth::id(),
                            'remarks'        => "Stock In from PR #{$pr->pr_no} (Receipt Slip #{$slipNo})",
                        ]);
                    } catch (\Throwable $e) {}
                }
            }

            // 5. Update PR status
            $pr->update([
                'status'             => PurchaseRequest::STATUS_INTAKE_COMPLETE,
                'store_id'           => $storeId,
                'current_owner_role' => null,
            ]);

            $this->log($pr, $from, PurchaseRequest::STATUS_INTAKE_COMPLETE, 'store_intake_complete', 'store_manager', 
                "Received into store (Slip #{$slipNo}). " . ($notes ?? ''));
        });

        // 6. Notify Requester / Coordinator
        $requester = $pr->requestedBy;
        $phone = $requester?->employee?->phone;
        if ($phone) {
            $this->sms->send($pr->id, $phone, 'coordinator',
                "ConstructPro: Your PR #{$pr->pr_no} items have been received and added to store inventory. Project: {$pr->project?->name}.");
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
