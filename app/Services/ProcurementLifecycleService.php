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
use App\Models\Employee;
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

    /**
     * Send notification SMS to a role for a specific PR stage transition.
     */
    public function notifyStageRole(int $purchaseRequestId, string $roleName, string $message, ?int $projectId = null, ?int $storeId = null): void
    {
        $this->sms->notifyRole($purchaseRequestId, $roleName, $message, $projectId, $storeId);
    }

    /**
     * Centralized helper: notifies the next owner of the PR via SMS upon status transition.
     * 
     * Requirements:
     * - Keep all existing stages, statuses, and history records completely unchanged.
     * - Message payload format: PR number, Project name, Action needed, and Previous actor's name.
     * - Fallback to global_admin if role has no active users.
     * - Direct SMS routing to assigned person if assigned (e.g. finance_staff_id, driver_id).
     *
     * @param PurchaseRequest $pr
     * @param string          $newStatus
     * @param string          $actionNeeded
     * @param int|null        $assignedUserId
     * @param int|null        $assignedEmployeeId
     */
    public function notifyNextOwner(
        PurchaseRequest $pr,
        string $newStatus,
        string $actionNeeded,
        ?int $assignedUserId = null,
        ?int $assignedEmployeeId = null
    ): void {
        try {
            $prNo = $pr->pr_no;
            $projectName = $pr->project?->name ?? 'General / Head Office';
            $actorName = Auth::user()?->name ?? 'System';

            $message = "ConstructPro: PR #{$prNo} | Project: {$projectName} | Action: {$actionNeeded} | Actor: {$actorName}. Open: " . url("/purchase-requests/{$pr->id}");

            // 1. Direct routing to assigned Employee (e.g., driver)
            $driverEmployeeId = $assignedEmployeeId ?: ($pr->driverBooking?->driver_employee_id ?? null);
            if ($driverEmployeeId) {
                try {
                    $driver = Employee::find($driverEmployeeId);
                    if ($driver && !empty($driver->phone)) {
                        $this->sms->send($pr->id, $driver->phone, 'driver', $message);
                    }
                } catch (\Throwable $e) {
                    \Illuminate\Support\Facades\Log::error("notifyNextOwner driver SMS error: " . $e->getMessage());
                }
            }

            // 2. Direct routing to assigned User (e.g., assigned finance staff)
            $targetStaffId = $assignedUserId ?: ($pr->payment?->assigned_finance_staff_id ?? null);
            if ($targetStaffId && in_array($pr->current_owner_role, ['finance', 'finance_staff'])) {
                $staff = User::with('employee')->find($targetStaffId);
                $phone = $staff ? $this->sms->resolveUserPhone($staff) : null;
                if (!empty($phone)) {
                    $this->sms->send($pr->id, $phone, 'finance', $message);
                    return;
                }
            }

            // 3. Role-based routing to current owner role with automatic fallback to global_admin
            $targetRole = $pr->current_owner_role ?: 'global_admin';
            $resolvedRole = $this->resolveOwnerRole($targetRole, $pr);

            $this->sms->notifyRole($pr->id, $resolvedRole, $message, $pr->project_id, $pr->store_id);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error("ProcurementLifecycle notifyNextOwner error for PR #{$pr->id}: " . $e->getMessage());
        }
    }

    /**
     * Resolve target owner role. If no active users have this role,
     * route to 'global_admin' and log the system fallback.
     */
    public function resolveOwnerRole(string $targetRole, PurchaseRequest $pr): string
    {
        if ($targetRole === 'global_admin') {
            return 'global_admin';
        }

        $aliases = [
            'purchase_manager' => ['purchase_manager', 'Purchase Manager', 'Procurement Manager', 'procurement_manager'],
            'purchase'         => ['purchase', 'Purchase', 'procurement', 'Procurement', 'procurement_officer', 'procurement_team'],
            'market_research'  => ['market_research', 'Market Research', 'marketing', 'Marketing', 'marketing_officer'],
            'gm'               => ['gm', 'GM', 'general_manager', 'General Manager'],
            'store_manager'    => ['store_manager', 'Store Manager', 'store', 'Store'],
            'store_keeper'     => ['store_keeper', 'Store Keeper', 'storekeeper', 'Storekeeper', 'store_clerk'],
            'finance_head'     => ['finance_head', 'Finance Head', 'finance_manager', 'Finance Manager', 'cfo', 'CFO'],
            'finance'          => ['finance', 'Finance', 'accountant', 'Accountant', 'finance_staff'],
            'general_service'  => ['general_service', 'General Service', 'dispatcher', 'fleet_manager'],
            'coordinator'      => ['coordinator', 'Coordinator', 'project_coordinator', 'site_coordinator'],
            'planning'         => ['planning', 'Planning', 'planning_manager', 'Planning Manager'],
            'global_admin'     => ['global_admin', 'admin', 'Global Admin', 'Admin'],
        ];

        $rolesToCheck = $aliases[$targetRole] ?? [$targetRole];

        try {
            $hasActiveUsers = User::whereHas('roles', function ($q) use ($rolesToCheck) {
                $q->whereIn('name', $rolesToCheck);
            })->exists();

            if (!$hasActiveUsers) {
                \Log::warning("ProcurementLifecycle: No active users assigned to role [{$targetRole}] for PR #{$pr->pr_no}. Auto-routing to [global_admin].");

                try {
                    PrWorkflowLog::create([
                        'purchase_request_id' => $pr->id,
                        'from_status'         => $pr->status,
                        'to_status'           => $pr->status,
                        'action'              => 'reroute_to_global_admin',
                        'actor_role'          => 'system',
                        'actor_id'            => Auth::id() ?: null,
                        'notes'               => "No users currently assigned to '{$targetRole}' role. Automatically routed to Global Admin for action.",
                    ]);
                } catch (\Throwable $e) {}

                return 'global_admin';
            }
        } catch (\Throwable $e) {
            \Log::error("ProcurementLifecycle resolveOwnerRole error: " . $e->getMessage());
        }

        return $targetRole;
    }

    // ═══════════════════════════════════════════════════════════════════
    // STAGE 2 — Store Manager Routes MR
    // ═══════════════════════════════════════════════════════════════════

    /**
     * Store Manager decides to send MR to a Purchase Request
     */
    public function sendToProcurementManager(PurchaseRequest $pr, string $notes = null): void
    {
        $from = $pr->status;
        $targetRole = $this->resolveOwnerRole('purchase_manager', $pr);
        $pr->update([
            'status'             => PurchaseRequest::STATUS_PENDING_PROC_MANAGER,
            'current_owner_role' => $targetRole,
        ]);
        $this->log($pr, $from, PurchaseRequest::STATUS_PENDING_PROC_MANAGER, 'send_to_procurement_manager', 'store_manager', $notes);
        $this->notifyNextOwner($pr, PurchaseRequest::STATUS_PENDING_PROC_MANAGER, 'Review PR & Assign Sourcing Method');
    }

    // ═══════════════════════════════════════════════════════════════════
    // STAGE 3 — Procurement Manager Triage
    // ═══════════════════════════════════════════════════════════════════

    public function sendBackToStoreManager(PurchaseRequest $pr, string $reason): void
    {
        $from = $pr->status;
        $targetRole = $this->resolveOwnerRole('store_manager', $pr);
        $pr->update([
            'status'             => PurchaseRequest::STATUS_PENDING_STORE_REVIEW,
            'pm_sendback_reason' => $reason,
            'current_owner_role' => $targetRole,
        ]);
        $this->log($pr, $from, PurchaseRequest::STATUS_PENDING_STORE_REVIEW, 'send_back_to_store_manager', 'purchase_manager', $reason);
        $this->notifyNextOwner($pr, PurchaseRequest::STATUS_PENDING_STORE_REVIEW, "Returned to Store: {$reason}");
    }

    public function sendToProcurementTeam(PurchaseRequest $pr, string $sourcingMethod = 'proforma', string $notes = null): void
    {
        $from = $pr->status;
        $targetRole = $this->resolveOwnerRole('purchase', $pr);
        $pr->update([
            'sourcing_method'        => $sourcingMethod,
            'status'                 => PurchaseRequest::STATUS_PENDING_PROC_TEAM,
            'current_owner_role'     => $targetRole,
            'procurement_team_notes' => $notes,
        ]);
        $actionName = $sourcingMethod === 'direct_buy' ? 'send_to_proc_team_direct_buy' : 'send_to_proc_team_proforma';
        $this->log($pr, $from, PurchaseRequest::STATUS_PENDING_PROC_TEAM, $actionName, 'purchase_manager', $notes);
        
        $methodLabel = $sourcingMethod === 'direct_buy' ? 'Direct Buy (add material prices)' : 'Proforma Sourcing (collect quotes)';
        $this->notifyNextOwner($pr, PurchaseRequest::STATUS_PENDING_PROC_TEAM, "Sourcing: {$methodLabel}");
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

        $targetRole = $this->resolveOwnerRole('purchase_manager', $pr);
        $pr->update([
            'sourcing_method'       => 'direct_buy',
            'direct_buy_amount'     => $amount,
            'direct_buy_added_by'   => Auth::id(),
            'procurement_team_notes'=> $notes,
            'status'                => PurchaseRequest::STATUS_PENDING_MARKETING,
            'current_owner_role'    => $targetRole,
        ]);
        $this->log($pr, $from, PurchaseRequest::STATUS_PENDING_MARKETING, 'submit_direct_buy_pricing', 'purchase', $notes);
        $this->notifyNextOwner($pr, PurchaseRequest::STATUS_PENDING_MARKETING, 'Direct Buy Pricing Review (' . number_format($amount, 2) . ' ETB)');
    }

    public function submitProformas(PurchaseRequest $pr, string $notes = null): void
    {
        $from = $pr->status;
        $targetRole = $this->resolveOwnerRole('purchase_manager', $pr);
        $pr->update([
            'sourcing_method'        => 'proforma',
            'procurement_team_notes' => $notes,
            'status'                 => PurchaseRequest::STATUS_PENDING_PROFORMA_SELECTION,
            'current_owner_role'     => $targetRole,
        ]);
        $this->log($pr, $from, PurchaseRequest::STATUS_PENDING_PROFORMA_SELECTION, 'submit_proformas', 'purchase', $notes);
        $this->notifyNextOwner($pr, PurchaseRequest::STATUS_PENDING_PROFORMA_SELECTION, 'Review & Select Submitted Proformas');
    }

    // ═══════════════════════════════════════════════════════════════════
    // STAGE 5a — Purchasing Manager Price Review & Decision
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
        $targetRole = $this->resolveOwnerRole('gm', $pr);
        $pr->update([
            'status'             => PurchaseRequest::STATUS_PENDING_GM,
            'current_owner_role' => $targetRole,
        ]);
        $this->log($pr, $from, PurchaseRequest::STATUS_PENDING_GM, 'add_marketing_variance', 'purchase_manager', $data['variance_notes'] ?? null);
        $this->notifyNextOwner($pr, PurchaseRequest::STATUS_PENDING_GM, 'GM Decision on Direct Buy with Pricing Variance');
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

        $targetRole = $this->resolveOwnerRole('gm', $pr);
        $pr->update([
            'status'             => PurchaseRequest::STATUS_PENDING_GM,
            'current_owner_role' => $targetRole,
        ]);

        $this->log($pr, $from, PurchaseRequest::STATUS_PENDING_GM, 'send_proformas_to_gm', 'purchase_manager', $notes);
        $this->notifyNextOwner($pr, PurchaseRequest::STATUS_PENDING_GM, 'GM Decision on ' . count($proformaIds) . ' Selected Proforma Quote(s)');
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
                "ConstructPro: PR #{$pr->pr_no} was REJECTED by GM. Reason: {$notes}. Open: " . url("/purchase-requests/{$pr->id}"),
                $pr->project_id,
                $pr->store_id
            );
            $this->sms->notifyRole($pr->id, 'coordinator',
                "ConstructPro: PR #{$pr->pr_no} (" . ($pr->project?->name ?? 'Project') . ") was REJECTED by GM. Reason: {$notes}.",
                $pr->project_id,
                $pr->store_id
            );

        } elseif ($decision === 'send_back') {
            $pr->update([
                'status'             => PurchaseRequest::STATUS_PENDING_PROC_MANAGER,
                'gm_loop_count'      => $round,
                'current_owner_role' => 'purchase_manager',
            ]);
            $this->log($pr, $from, PurchaseRequest::STATUS_PENDING_PROC_MANAGER, 'gm_send_back', 'gm', $notes);
            $this->notifyNextOwner($pr, PurchaseRequest::STATUS_PENDING_PROC_MANAGER, "Returned by GM for Revision: {$notes}");
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

            // Calculate portion allocations from items if split or specific
            $items = $pr->items()->get();
            $creditAmount = 0.0;
            $financeAmount = 0.0;

            if ($paymentMethod === 'buy_by_credit') {
                $creditAmount = $finalAmount;
            } elseif ($paymentMethod === 'pay_and_buy') {
                $financeAmount = $finalAmount;
            } else { // split or mixed items
                $creditSum = (float)$items->filter(fn($i) => ($i->payment_method ?? '') === 'buy_by_credit')
                    ->sum(fn($i) => (float)$i->quantity * (float)($i->estimated_unit_price ?? $i->unit_price ?? $i->estimated_unit_cost ?? 0));
                $financeSum = (float)$items->filter(fn($i) => ($i->payment_method ?? 'pay_and_buy') !== 'buy_by_credit')
                    ->sum(fn($i) => (float)$i->quantity * (float)($i->estimated_unit_price ?? $i->unit_price ?? $i->estimated_unit_cost ?? 0));
                $totalCalc = $creditSum + $financeSum;

                if ($totalCalc > 0 && $finalAmount > 0) {
                    $ratio = $finalAmount / $totalCalc;
                    $creditAmount = round($creditSum * $ratio, 2);
                    $financeAmount = round($finalAmount - $creditAmount, 2);
                } else {
                    $creditAmount = round($creditSum, 2);
                    $financeAmount = round($financeSum, 2);
                }

                // Fallback if both 0
                if ($creditAmount <= 0 && $financeAmount <= 0) {
                    $financeAmount = $finalAmount;
                }
            }

            // 1. Credit Handling
            if ($creditAmount > 0) {
                $coa5110 = $this->ensureCreditCoaAccount();

                // Book CreditStoreLedger
                \App\Models\CreditStoreLedger::updateOrCreate(
                    ['purchase_request_id' => $pr->id],
                    [
                        'pr_no'          => $pr->pr_no,
                        'project_id'     => $pr->project_id,
                        'supplier_name'  => $supplierName,
                        'credit_amount'  => $creditAmount,
                        'coa_account_id' => $coa5110->id,
                        'status'         => 'outstanding',
                        'authorized_by'  => Auth::id(),
                        'authorized_at'  => now(),
                        'notes'          => $notes ?: 'GM Approved Buy with Credit (Auto-booked COA 5110)',
                        'created_by'     => Auth::id(),
                    ]
                );

                // If no finance portion, credit is the primary ProcurementPayment
                if ($financeAmount <= 0) {
                    ProcurementPayment::updateOrCreate(
                        ['purchase_request_id' => $pr->id],
                        [
                            'method'         => 'credit',
                            'coa_account_id' => $coa5110->id,
                            'amount'         => $creditAmount,
                            'notes'          => $notes ?: 'GM Approved Buy with Credit (Auto-booked COA 5110)',
                            'status'         => 'paid',
                            'created_by'     => Auth::id(),
                            'paid_by'        => Auth::id(),
                            'paid_at'        => now(),
                        ]
                    );
                }
            }

            // 2. Finance Handling
            if ($financeAmount > 0) {
                // Pre-create/update ProcurementPayment with the finance portion
                ProcurementPayment::updateOrCreate(
                    ['purchase_request_id' => $pr->id],
                    [
                        'method'         => 'cash',
                        'amount'         => $financeAmount,
                        'notes'          => $notes . ($creditAmount > 0 ? " (Split: Finance {$financeAmount} ETB, Credit {$creditAmount} ETB)" : ''),
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
                            'description'           => "GM Approved Purchase Request #{$pr->pr_no}" . ($supplierName ? " — Supplier: {$supplierName}" : '') . ($creditAmount > 0 ? " (Split: Finance {$financeAmount} ETB, Credit {$creditAmount} ETB)" : '') . ($notes ? ". Notes: {$notes}" : ''),
                            'amount'                => $financeAmount,
                            'gross_amount'          => $financeAmount,
                            'status'                => ExpenseRequest::STATUS_APPROVED_ASSIGNED,
                        ]
                    );
                } catch (\Throwable $e) {
                    \Illuminate\Support\Facades\Log::warning('Could not create ExpenseRequest for PR: ' . $e->getMessage());
                }

                $nextStatus   = PurchaseRequest::STATUS_PENDING_PAYMENT;
                $rawNextRole  = 'finance_head';
                $actionText   = ($paymentMethod === 'split')
                    ? 'Select Funding Account & Assign Staff (Split: Finance ' . number_format($financeAmount, 2) . ' ETB & Credit ' . number_format($creditAmount, 2) . ' ETB)'
                    : 'Select Funding Account & Assign Staff (Pay & Buy: ' . number_format($finalAmount, 2) . ' ETB)';
            } else {
                // Entirely credit, route directly to Store Keeper for material intake
                $nextStatus   = PurchaseRequest::STATUS_PENDING_STORE_REVIEW;
                $rawNextRole  = 'store_keeper';
                $actionText   = 'Material Intake (Credit Authorized COA 5110: ' . number_format($creditAmount, 2) . ' ETB)';
            }

            $nextRole = $this->resolveOwnerRole($rawNextRole, $pr);
            $pr->update([
                'status'             => $nextStatus,
                'gm_loop_count'      => $round,
                'current_owner_role' => $nextRole,
            ]);
            $this->log($pr, $from, $nextStatus, 'gm_approve_' . $paymentMethod, 'gm', $notes);
            $this->notifyNextOwner($pr, $nextStatus, $actionText);

            // Also notify coordinator that GM has approved
            $this->sms->notifyRole($pr->id, 'coordinator',
                "ConstructPro: PR #{$pr->pr_no} (" . ($pr->project?->name ?? 'Project') . ") has been APPROVED by GM (" . number_format($finalAmount, 2) . " ETB). Open: " . url("/purchase-requests/{$pr->id}"),
                $pr->project_id,
                $pr->store_id
            );
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

        // Advance directly to Store Keeper for material intake
        $targetRole = $this->resolveOwnerRole('store_keeper', $pr);
        $pr->update([
            'status'             => PurchaseRequest::STATUS_PENDING_STORE_REVIEW,
            'current_owner_role' => $targetRole,
        ]);
        $this->log($pr, $from, PurchaseRequest::STATUS_PENDING_STORE_REVIEW, 'finance_credit_approved_direct_intake', 'finance_head', $notes);
        $this->notifyNextOwner($pr, PurchaseRequest::STATUS_PENDING_STORE_REVIEW, 'Credit Authorized (COA 5110) — Perform Store Intake');
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

        $targetRole = $this->resolveOwnerRole('finance', $pr);
        $pr->update([
            'status'             => PurchaseRequest::STATUS_PENDING_PAYMENT,
            'current_owner_role' => $targetRole,
        ]);
        $this->log($pr, $from, PurchaseRequest::STATUS_PENDING_PAYMENT, 'finance_head_assign_payment', 'finance_head', $notes);

        // SMS directly to the assigned finance staff member (falls back to finance role / global_admin)
        $this->notifyNextOwner($pr, PurchaseRequest::STATUS_PENDING_PAYMENT, 'Disburse Cash Payment of ' . number_format($amount, 2) . ' ETB', $staffUserId);
    }

    // ═══════════════════════════════════════════════════════════════════
    // STAGE 7b continued — Finance Staff Executes Payment
    // ═══════════════════════════════════════════════════════════════════

    public function financeStaffPay(PurchaseRequest $pr, string $notes = null, array $taxData = []): void
    {
        $from    = $pr->status;
        $payment = $pr->payment;

        $disbursedAmount = isset($taxData['net_amount']) && (float)$taxData['net_amount'] > 0
            ? (float)$taxData['net_amount']
            : (float)($payment ? $payment->amount : 0);

        if ($payment) {
            $updateData = [
                'status'  => 'paid',
                'paid_by' => Auth::id(),
                'paid_at' => now(),
                'notes'   => $notes ?? $payment->notes,
            ];

            // Safely populate tax attributes if columns exist
            $taxColumns = [
                'gross_amount', 'vat_type', 'vat_rate', 'vat_amount',
                'has_withholding', 'withholding_rate', 'withholding_amount',
                'withholding_receipt', 'withholding_receipt_number', 'net_amount'
            ];
            foreach ($taxColumns as $col) {
                if (isset($taxData[$col]) && \Illuminate\Support\Facades\Schema::hasColumn('procurement_payments', $col)) {
                    $updateData[$col] = $taxData[$col];
                }
            }

            $payment->update($updateData);

            // Create journal entry: Debit → Procurement Expense; Credit → Cash/Bank COA
            $this->createJournalEntry($pr, $payment->coa_account_id, $disbursedAmount, 'cash', $taxData);
        }

        // Update Expense record to approved
        try {
            $taxNote = '';
            if (!empty($taxData['vat_amount']) && (float)$taxData['vat_amount'] > 0) {
                $taxNote .= " [VAT: +{$taxData['vat_amount']}]";
            }
            if (!empty($taxData['withholding_amount']) && (float)$taxData['withholding_amount'] > 0) {
                $taxNote .= " [WHT: -{$taxData['withholding_amount']}]";
            }

            \App\Models\Expense::updateOrCreate(
                [
                    'project_id'  => $pr->project_id,
                    'description' => "Material Purchase for PR #{$pr->pr_no}",
                ],
                [
                    'category'     => 'material',
                    'amount'       => $disbursedAmount,
                    'expense_date' => now()->toDateString(),
                    'status'       => 'approved',
                    'created_by'   => ($payment ? $payment->assigned_finance_staff_id : null) ?: Auth::id(),
                    'approved_by'  => Auth::id(),
                    'approved_at'  => now(),
                    'notes'        => "Payment executed by Finance Staff. PR #{$pr->pr_no}{$taxNote}",
                ]
            );
        } catch (\Throwable $e) {}

        // Mark any linked ExpenseRequest record as paid so it stays synchronized
        try {
            $expReq = \App\Models\ExpenseRequest::where('purchase_request_id', $pr->id)->first();
            if ($expReq && $expReq->status !== \App\Models\ExpenseRequest::STATUS_PAID) {
                $expUpdate = [
                    'status'            => \App\Models\ExpenseRequest::STATUS_PAID,
                    'paid_by'           => Auth::id(),
                    'paid_at'           => now(),
                    'payment_reference' => $notes ?? ($payment ? $payment->notes : null),
                ];
                if (!empty($taxData)) {
                    if (isset($taxData['gross_amount'])) $expUpdate['gross_amount'] = $taxData['gross_amount'];
                    if (isset($taxData['vat_type'])) $expUpdate['vat_type'] = $taxData['vat_type'];
                    if (isset($taxData['vat_rate'])) $expUpdate['vat_rate'] = $taxData['vat_rate'];
                    if (isset($taxData['vat_amount'])) $expUpdate['vat_amount'] = $taxData['vat_amount'];
                    if (isset($taxData['has_withholding'])) $expUpdate['has_withholding'] = $taxData['has_withholding'];
                    if (isset($taxData['withholding_rate'])) $expUpdate['withholding_rate'] = $taxData['withholding_rate'];
                    if (isset($taxData['withholding_amount'])) $expUpdate['withholding_amount'] = $taxData['withholding_amount'];
                    if (isset($taxData['withholding_receipt'])) $expUpdate['withholding_receipt'] = $taxData['withholding_receipt'];
                    if (isset($taxData['withholding_receipt_number'])) $expUpdate['withholding_receipt_number'] = $taxData['withholding_receipt_number'];
                    if (isset($taxData['net_amount'])) $expUpdate['net_amount'] = $taxData['net_amount'];
                }
                $expReq->update($expUpdate);
            }
        } catch (\Throwable $e) {}

        $targetRole = $this->resolveOwnerRole('purchase', $pr);
        $pr->update([
            'status'             => PurchaseRequest::STATUS_PENDING_RECEIPT_UPLOAD,
            'current_owner_role' => $targetRole,
        ]);
        $this->log($pr, $from, PurchaseRequest::STATUS_PENDING_RECEIPT_UPLOAD, 'finance_staff_paid', 'finance', $notes);
        $this->notifyNextOwner($pr, PurchaseRequest::STATUS_PENDING_RECEIPT_UPLOAD, 'Payment Disbursed (' . number_format($disbursedAmount, 2) . ' ETB) — Upload Vendor Receipt');
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
            $targetRole = $this->resolveOwnerRole('store_keeper', $pr);
            $pr->update([
                'status'             => PurchaseRequest::STATUS_PENDING_STORE_REVIEW,
                'current_owner_role' => $targetRole,
            ]);
            $this->log($pr, $from, PurchaseRequest::STATUS_PENDING_STORE_REVIEW, 'receipt_uploaded_sent_to_store', 'purchase', $notes);
            $this->notifyNextOwner($pr, PurchaseRequest::STATUS_PENDING_STORE_REVIEW, 'Receipt Uploaded — Perform Store Material Intake');
        } else {
            $targetRole = $this->resolveOwnerRole('finance', $pr);
            $pr->update([
                'status'             => PurchaseRequest::STATUS_PENDING_RECEIPT_VERIFY,
                'current_owner_role' => $targetRole,
            ]);
            $this->log($pr, $from, PurchaseRequest::STATUS_PENDING_RECEIPT_VERIFY, 'receipt_uploaded', 'purchase', $notes);
            $this->notifyNextOwner($pr, PurchaseRequest::STATUS_PENDING_RECEIPT_VERIFY, 'Verify Vendor Purchase Receipt');
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
            $targetRole = $this->resolveOwnerRole('general_service', $pr);
            $pr->update([
                'status'             => PurchaseRequest::STATUS_PENDING_DRIVER,
                'current_owner_role' => $targetRole,
            ]);
            $this->log($pr, $from, PurchaseRequest::STATUS_PENDING_DRIVER, 'receipt_verified', 'finance', $verificationNotes);
            $this->notifyNextOwner($pr, PurchaseRequest::STATUS_PENDING_DRIVER, 'Receipt Verified — Book Delivery Driver');
        } else {
            // Rejected receipt: send back to Procurement Team to re-upload
            $targetRole = $this->resolveOwnerRole('purchase', $pr);
            $pr->update([
                'status'             => PurchaseRequest::STATUS_PENDING_RECEIPT_UPLOAD,
                'current_owner_role' => $targetRole,
            ]);
            $this->log($pr, $from, PurchaseRequest::STATUS_PENDING_RECEIPT_UPLOAD, 'receipt_rejected', 'finance', $verificationNotes);
            $this->notifyNextOwner($pr, PurchaseRequest::STATUS_PENDING_RECEIPT_UPLOAD, "Receipt Rejected — Please Re-upload: {$verificationNotes}");
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

        $targetRole = $this->resolveOwnerRole('store_keeper', $pr);
        $pr->update([
            'status'             => PurchaseRequest::STATUS_PENDING_STORE_REVIEW, // Store Keeper does intake
            'current_owner_role' => $targetRole,
        ]);
        $this->log($pr, $from, PurchaseRequest::STATUS_PENDING_STORE_REVIEW, 'driver_booked', 'general_service', $notes);
        $this->notifyNextOwner($pr, PurchaseRequest::STATUS_PENDING_STORE_REVIEW, 'Driver Booked — Material Intake on Delivery', null, $driverEmployeeId);
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
    ): array {
        $from = $pr->status;
        $storeId = $storeId ?: ($pr->store_id ?: Store::where('is_active', true)->first()?->id ?: 1);
        $receivedDate = $receivedDate ?: now()->toDateString();

        $intakeResult = ['is_full' => true, 'remaining' => [], 'slip_no' => $slipNo];

        DB::transaction(function () use ($pr, $storeId, $slipNo, $receivedDate, $receivedItems, $notes, $from, &$intakeResult) {
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

            // 2. Create or find PO required for DeliveryReceipt foreign key
            $poId = $pr->purchaseOrders()->first()?->id;
            if (!$poId) {
                // Determine supplier name
                $supplierName = 'Direct Supplier';
                if (!empty($pr->supplier_name)) {
                    $supplierName = $pr->supplier_name;
                } elseif ($pr->supplier) {
                    $supplierName = $pr->supplier->name;
                } elseif ($pr->creditLedger && !empty($pr->creditLedger->supplier_name)) {
                    $supplierName = $pr->creditLedger->supplier_name;
                } elseif ($pr->proformaInvoices()->where('gm_selected', true)->exists()) {
                    $selectedPf = $pr->proformaInvoices()->where('gm_selected', true)->with('supplier')->first();
                    $supplierName = $selectedPf?->supplier?->name ?: 'Proforma Supplier';
                }

                $refNo = 'PO-PR-' . ($pr->pr_no ?: $pr->id);
                
                try {
                    $po = \App\Models\PurchaseOrder::where('reference_number', $refNo)
                        ->orWhere('purchase_request_id', $pr->id)
                        ->first();

                    if (!$po) {
                        $po = \App\Models\PurchaseOrder::create([
                            'project_id'          => $pr->project_id,
                            'purchase_request_id' => $pr->id,
                            'reference_number'    => $refNo,
                            'supplier_name'       => $supplierName,
                            'supplier_id'         => $pr->supplier_id ?? ($pr->creditLedger?->supplier_id ?? null),
                            'status'              => 'delivered',
                            'total_amount'        => (float)($pr->direct_buy_amount ?? 0),
                            'issued_date'         => now()->toDateString(),
                            'created_by'          => Auth::id() ?? ($pr->requested_by ?: 1),
                            'notes'               => "Auto-created PO for PR intake #{$pr->pr_no}",
                        ]);
                    }
                    $poId = $po->id;
                } catch (\Throwable $e) {
                    \Illuminate\Support\Facades\Log::error("Failed creating PO for PR {$pr->pr_no}: " . $e->getMessage());
                    // Fallback: search any existing PO
                    $poId = \App\Models\PurchaseOrder::value('id');
                }
            }

            // Ensure poId is never null
            if (!$poId) {
                try {
                    $fallbackPo = \App\Models\PurchaseOrder::create([
                        'project_id'       => $pr->project_id,
                        'reference_number' => 'PO-' . time() . '-' . rand(1000, 9999),
                        'supplier_name'    => 'Direct Sourcing',
                        'status'           => 'delivered',
                        'total_amount'     => 0,
                        'issued_date'      => now()->toDateString(),
                        'created_by'       => Auth::id() ?? 1,
                        'notes'            => 'System fallback PO for store intake',
                    ]);
                    $poId = $fallbackPo->id;
                } catch (\Throwable $e) {
                    $poId = \App\Models\PurchaseOrder::value('id');
                }
            }

            // 3. Create DeliveryReceipt record
            $receipt = DeliveryReceipt::create([
                'dr_no'               => $slipNo,
                'purchase_order_id'   => $poId,
                'purchase_request_id' => $pr->id,
                'store_id'            => $storeId,
                'received_date'       => $receivedDate,
                'received_by'         => Auth::id() ?? 1,
                'status'              => 'verified',
                'notes'               => $notes ?: "Intake for PR #{$pr->pr_no} (Slip #{$slipNo})",
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

                // Update cumulative received quantity on PurchaseRequestItem
                try {
                    $item->increment('received_quantity', $acceptedQty);
                } catch (\Throwable $e) {
                    try {
                        $item->received_quantity = (float)($item->received_quantity ?? 0) + $acceptedQty;
                        $item->save();
                    } catch (\Throwable $e2) {}
                }
            }

            // 5. Evaluate intake completion across all items
            $isFullyReceived = true;
            $remainingSummaries = [];
            $freshItems = $pr->items()->with('product')->get();

            foreach ($freshItems as $fItm) {
                $target = method_exists($fItm, 'getTargetIntakeQty') 
                    ? $fItm->getTargetIntakeQty() 
                    : ((float)($fItm->purchased_quantity ?? 0) > 0 ? (float)$fItm->purchased_quantity : (float)$fItm->quantity);
                
                $received = method_exists($fItm, 'getReceivedQty') 
                    ? $fItm->getReceivedQty() 
                    : (float)($fItm->received_quantity ?? 0);
                
                $rem = max(0.0, $target - $received);
                if ($rem > 0.001) {
                    $isFullyReceived = false;
                    $pName = $fItm->product?->name ?? "Item #{$fItm->product_id}";
                    $u = $fItm->unit ?? ($fItm->product?->unit ?? 'pcs');
                    $remainingSummaries[] = "{$pName}: " . number_format($rem, 2) . " {$u} remaining";
                }
            }

            if ($isFullyReceived) {
                $pr->update([
                    'status'             => PurchaseRequest::STATUS_INTAKE_COMPLETE,
                    'store_id'           => $storeId,
                    'current_owner_role' => null,
                ]);

                $this->log($pr, $from, PurchaseRequest::STATUS_INTAKE_COMPLETE, 'store_intake_complete', 'store_keeper', 
                    "Full material intake completed into store (Slip #{$slipNo}). " . ($notes ?? ''));
            } else {
                // Keep PR in pending_store_review for subsequent delivery slips
                $pr->update([
                    'status'             => PurchaseRequest::STATUS_PENDING_STORE_REVIEW,
                    'store_id'           => $storeId,
                    'current_owner_role' => 'store_keeper',
                ]);

                $remText = implode(', ', $remainingSummaries);
                $this->log($pr, $from, PurchaseRequest::STATUS_PENDING_STORE_REVIEW, 'partial_store_intake', 'store_keeper', 
                    "Partial intake recorded (Slip #{$slipNo}). {$remText}. Awaiting additional delivery slip(s) to fulfill balance. " . ($notes ?? ''));
            }

            $intakeResult = [
                'is_full'   => $isFullyReceived,
                'remaining' => $remainingSummaries,
                'slip_no'   => $slipNo,
            ];
        });

        // 6. Notify Requester and Coordinator
        $requester = $pr->requestedBy;
        $phone = $requester ? $this->sms->resolveUserPhone($requester) : null;
        if ($phone) {
            if ($intakeResult['is_full']) {
                $this->sms->send($pr->id, $phone, 'requester',
                    "ConstructPro: Your PR #{$pr->pr_no} items have been fully received (Slip #{$intakeResult['slip_no']}) and added to store inventory. Project: {$pr->project?->name}.");
            } else {
                $remText = implode(', ', $intakeResult['remaining']);
                $this->sms->send($pr->id, $phone, 'requester',
                    "ConstructPro: Partial delivery received for PR #{$pr->pr_no} under Slip #{$intakeResult['slip_no']}. Remaining balance: {$remText}.");
            }
        }

        // Also notify coordinator role
        $this->sms->notifyRole(
            $pr->id,
            'coordinator',
            "ConstructPro: PR #{$pr->pr_no} store intake recorded (Slip #{$intakeResult['slip_no']}). Project: {$pr->project?->name}. Status: " . ($intakeResult['is_full'] ? 'Full Delivery' : 'Partial Delivery'),
            $pr->project_id,
            $storeId
        );

        return $intakeResult;
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
    private function createJournalEntry(PurchaseRequest $pr, int $coaAccountId, float $amount, string $method, array $taxData = []): void
    {
        try {
            DB::transaction(function () use ($pr, $coaAccountId, $amount, $method, $taxData) {
                $entryNo = 'PROC-' . date('Ymd') . '-' . str_pad(JournalEntry::count() + 1, 5, '0', STR_PAD_LEFT);

                $taxDesc = '';
                if (!empty($taxData['vat_amount']) && (float)$taxData['vat_amount'] > 0) {
                    $taxDesc .= " [VAT: +{$taxData['vat_amount']}]";
                }
                if (!empty($taxData['withholding_amount']) && (float)$taxData['withholding_amount'] > 0) {
                    $taxDesc .= " [WHT: -{$taxData['withholding_amount']}]";
                }

                $entry = JournalEntry::create([
                    'entry_no'       => $entryNo,
                    'entry_date'     => now()->toDateString(),
                    'reference_type' => 'procurement_payment',
                    'reference_id'   => $pr->id,
                    'description'    => "Procurement payment for PR #{$pr->pr_no} ({$method}){$taxDesc}",
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
                    'description'      => "PR #{$pr->pr_no} — procurement debit{$taxDesc}",
                ]);

                // Credit the same COA balance (Finance Head's selection represents the funding source)
                JournalEntryLine::create([
                    'journal_entry_id' => $entry->id,
                    'account_id'       => $coaAccountId,
                    'side'             => 'credit',
                    'amount'           => $amount,
                    'description'      => "PR #{$pr->pr_no} — procurement credit ({$method})",
                ]);

                // Update COA current_balance with disbursed net amount
                \App\Models\ChartOfAccount::where('id', $coaAccountId)
                    ->decrement('current_balance', $amount);

                // Link journal entry to payment record
                $pr->payment?->update(['journal_entry_id' => $entry->id]);
            });
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error("ProcurementJournalEntry error: " . $e->getMessage());
        }
    }

    /**
     * Finance Head sends PR back to General Manager for review / reassessment.
     */
    public function financeHeadSendBackToGm(PurchaseRequest $pr, string $reason): void
    {
        $from = $pr->status;
        $targetRole = $this->resolveOwnerRole('gm', $pr);

        $pr->update([
            'status'             => PurchaseRequest::STATUS_PENDING_GM,
            'current_owner_role' => $targetRole,
        ]);

        try {
            if ($pr->payment) {
                $pr->payment->update([
                    'status' => 'returned_to_gm',
                    'notes'  => ($pr->payment->notes ? $pr->payment->notes . ' | ' : '') . "Returned to GM by Finance Head: {$reason}",
                ]);
            }
        } catch (\Throwable $e) {}

        try {
            $expReq = \App\Models\ExpenseRequest::where('purchase_request_id', $pr->id)->first();
            if ($expReq) {
                $expReq->update([
                    'status'      => 'rejected',
                    'description' => $expReq->description . " [Returned to GM by Finance Head: {$reason}]",
                ]);
            }
        } catch (\Throwable $e) {}

        $this->log($pr, $from, PurchaseRequest::STATUS_PENDING_GM, 'finance_send_back_to_gm', 'finance_head', $reason);
        $this->notifyNextOwner($pr, PurchaseRequest::STATUS_PENDING_GM, "Returned to GM by Finance Head: {$reason}");
    }
}
