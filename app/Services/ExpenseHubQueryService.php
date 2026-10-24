<?php

namespace App\Services;

use App\Models\ProcurementPayment;
use App\Models\CreditStorePayment;
use App\Models\ExpenseRequest;
use App\Models\PettyCashMaterialPurchase;
use App\Models\Payroll;
use App\Models\EmployeeAdvance;
use App\Models\IpcRecord;
use App\Models\TaxSettlement;
use App\Models\FixedAsset;
use App\Models\ExpenseHubVatDecision;
use App\Models\ExpenseHubMonthLock;
use App\Models\Project;
use App\Models\User;
use App\Services\EthiopianDate;
use Illuminate\Support\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

class ExpenseHubQueryService
{
    /**
     * Get consolidated list of all expenses with linked VAT decisions.
     */
    public function getExpenses(array $filters = [], ?User $user = null): Collection
    {
        $items = new Collection();

        // Check user project scope
        $scopedProjectIds = $this->resolveScopedProjectIds($user);

        // Fetch decisions lookup to avoid N+1 queries
        $decisions = ExpenseHubVatDecision::all()->keyBy(function ($d) {
            return $d->source_type . ':' . $d->source_id;
        });

        // 1. Procurement Payments (Cash basis: status = paid)
        if ($this->shouldIncludeSource('procurement_payments', $filters)) {
            $this->loadProcurementPayments($items, $decisions, $scopedProjectIds);
        }

        // 2. Credit Store Payments (Actual settled amounts)
        if ($this->shouldIncludeSource('credit_store_payments', $filters)) {
            $this->loadCreditStorePayments($items, $decisions, $scopedProjectIds);
        }

        // 3. Expense Requests (Status = Paid, standalone only)
        if ($this->shouldIncludeSource('expense_requests', $filters)) {
            $this->loadExpenseRequests($items, $decisions, $scopedProjectIds);
        }

        // 4. Petty Cash Material Purchases
        if ($this->shouldIncludeSource('petty_cash_material_purchases', $filters)) {
            $this->loadPettyCashPurchases($items, $decisions, $scopedProjectIds);
        }

        // 5. Payrolls (Status = paid or GM approved)
        if ($this->shouldIncludeSource('payrolls', $filters)) {
            $this->loadPayrolls($items, $decisions, $scopedProjectIds);
        }

        // 6. Employee Advances (Disbursed or recovered)
        if ($this->shouldIncludeSource('employee_advances', $filters)) {
            $this->loadEmployeeAdvances($items, $decisions, $scopedProjectIds);
        }

        // 7. Subcontractor IPCs (Paid amount > 0 or approved)
        if ($this->shouldIncludeSource('ipc_records', $filters)) {
            $this->loadIpcRecords($items, $decisions, $scopedProjectIds);
        }

        // 8. Tax Settlements (Remittances paid to ERCA/MOR)
        if ($this->shouldIncludeSource('tax_settlements', $filters)) {
            $this->loadTaxSettlements($items, $decisions, $scopedProjectIds);
        }

        // 9. Fixed Assets (CapEx)
        if ($this->shouldIncludeSource('fixed_assets', $filters)) {
            $this->loadFixedAssets($items, $decisions, $scopedProjectIds);
        }

        // Apply In-Memory Filters
        return $this->applyFilters($items, $filters)->sortByDesc('disbursement_date')->values();
    }

    /**
     * Compute summary cards.
     */
    public function getSummaryCards(Collection $expenses): array
    {
        $totalSpent       = 0.0;
        $vatAppliesTotal  = 0.0;
        $totalInputVat    = 0.0;
        $noVatTotal       = 0.0;
        $pendingTotal     = 0.0;
        $pendingCount     = 0;
        $capexTotal       = 0.0;
        $withholdingTotal = 0.0;
        $withholdingCount = 0;

        foreach ($expenses as $item) {
            $amt = (float) $item->amount;
            $totalSpent += $amt;

            if ($item->is_capex) {
                $capexTotal += $amt;
            }

            if (!empty($item->has_withholding) || (float)($item->withholding_amount ?? 0) > 0) {
                $withholdingTotal += (float)($item->withholding_amount ?? 0);
                $withholdingCount++;
            }

            if ($item->vat_status === ExpenseHubVatDecision::STATUS_VAT_APPLIES) {
                $vatAppliesTotal += $amt;
                $totalInputVat += (float) ($item->decision_vat_amount ?? $item->source_vat_amount ?? 0);
            } elseif ($item->vat_status === ExpenseHubVatDecision::STATUS_NO_VAT) {
                $noVatTotal += $amt;
            } else {
                $pendingTotal += $amt;
                $pendingCount++;
            }
        }

        return [
            'total_spent'        => $totalSpent,
            'vat_applies_total'  => $vatAppliesTotal,
            'total_input_vat'    => $totalInputVat,
            'no_vat_total'       => $noVatTotal,
            'pending_total'      => $pendingTotal,
            'pending_count'      => $pendingCount,
            'capex_total'        => $capexTotal,
            'withholding_total'  => $withholdingTotal,
            'withholding_count'  => $withholdingCount,
            'total_count'        => $expenses->count(),
        ];
    }

    /**
     * Compute VAT Monthly Summary Breakdown (Input VAT, No-VAT, Pending clearly flagged).
     */
    public function getMonthlyVatSummary(Collection $expenses): Collection
    {
        return $expenses->groupBy(function ($item) {
            $date = $item->disbursement_date;
            return $date ? Carbon::parse($date)->format('Y-m') : 'Undated';
        })->map(function ($group, $monthKey) {
            $monthLabel = $monthKey !== 'Undated' ? Carbon::parse($monthKey . '-01')->format('M Y') : 'Undated';
            $inputVat = 0.0;
            $vatBase  = 0.0;
            $noVat    = 0.0;
            $pending  = 0.0;
            $pendingCount = 0;
            $totalGross = 0.0;

            foreach ($group as $item) {
                $amt = (float) $item->amount;
                $totalGross += $amt;

                if ($item->vat_status === ExpenseHubVatDecision::STATUS_VAT_APPLIES) {
                    $vatBase += (float) ($item->decision_base_amount ?? ($amt - ($item->decision_vat_amount ?? 0)));
                    $inputVat += (float) ($item->decision_vat_amount ?? $item->source_vat_amount ?? 0);
                } elseif ($item->vat_status === ExpenseHubVatDecision::STATUS_NO_VAT) {
                    $noVat += $amt;
                } else {
                    $pending += $amt;
                    $pendingCount++;
                }
            }

            // Check if month is locked
            $isLocked = false;
            if ($monthKey !== 'Undated') {
                $carbon = Carbon::parse($monthKey . '-01');
                $isLocked = ExpenseHubMonthLock::isMonthLocked($carbon->year, $carbon->month, 'GC');
            }

            return (object) [
                'month_key'      => $monthKey,
                'month_label'    => $monthLabel,
                'total_gross'    => $totalGross,
                'vat_base'       => $vatBase,
                'input_vat'      => $inputVat,
                'no_vat'         => $noVat,
                'pending'        => $pending,
                'pending_count'  => $pendingCount,
                'items_count'    => $group->count(),
                'is_locked'      => $isLocked,
            ];
        })->sortByDesc('month_key')->values();
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Source Module Readers (100% Read-Only)
    // ─────────────────────────────────────────────────────────────────────────

    protected function loadProcurementPayments(Collection $items, Collection $decisions, ?array $scopedProjectIds): void
    {
        $query = ProcurementPayment::with([
            'purchaseRequest.supplier',
            'purchaseRequest.project',
            'purchaseRequest.requestedBy',
            'purchaseRequest.approvedBy',
            'purchaseRequest.items.product',
            'paidByUser',
            'createdBy',
        ])->where('status', 'paid');

        if ($scopedProjectIds !== null) {
            $query->whereHas('purchaseRequest', fn($q) => $q->whereIn('project_id', $scopedProjectIds));
        }

        foreach ($query->get() as $payment) {
            $pr = $payment->purchaseRequest;
            $supplierName = $pr?->supplier?->name ?: ($pr?->vendor_name ?: 'Procurement Supplier');
            $tin = $pr?->supplier?->tin ?: null;
            $gross = (float) ($payment->gross_amount > 0 ? $payment->gross_amount : $payment->amount);
            $vatAmt = (float) ($payment->vat_amount ?? 0);

            $attachment = $payment->receipt_path 
                ?: ($payment->withholding_receipt ?: ($pr?->receipt_path ?: null));

            $key = 'procurement_payment:' . $payment->id;
            $decision = $decisions->get($key);

            $defaultStatus = $vatAmt > 0 ? ExpenseHubVatDecision::STATUS_VAT_APPLIES : ExpenseHubVatDecision::STATUS_PENDING_DECISION;

            $lineItems = [];
            if ($pr && $pr->items) {
                foreach ($pr->items as $it) {
                    $itName = $it->product?->name ?: ($it->specifications ?: 'Procured Material');
                    $qty = (float)($it->purchased_quantity > 0 ? $it->purchased_quantity : $it->quantity);
                    $rate = (float)($it->estimated_unit_cost ?? 0);
                    $lineItems[] = [
                        'name'     => $itName,
                        'quantity' => $qty,
                        'unit'     => $it->unit ?: 'pcs',
                        'rate'     => $rate,
                        'total'    => (float)($it->estimated_total ?? ($qty * $rate)),
                    ];
                }
            }

            $createdByName = $pr?->requestedBy?->name ?: ($payment->createdBy?->name ?: 'Procurement Staff');
            $createdAt = $pr?->created_at ? $pr->created_at->format('d M Y, h:i A') : ($payment->created_at ? $payment->created_at->format('d M Y, h:i A') : null);
            $approvedByName = $pr?->approvedBy?->name ?: 'Management / Planning Approval';
            $approvedAt = $pr?->approved_at ? Carbon::parse($pr->approved_at)->format('d M Y, h:i A') : null;
            $paidByName = $payment->paidByUser?->name ?: 'Finance Staff';
            $paidAt = $payment->paid_at ? $payment->paid_at->format('d M Y, h:i A') : ($payment->created_at ? $payment->created_at->format('d M Y, h:i A') : null);

            $purpose = !empty($lineItems)
                ? ("Procurement of " . implode(', ', array_slice(array_column($lineItems, 'name'), 0, 3)) . (count($lineItems) > 3 ? ' +' . (count($lineItems) - 3) . ' more items' : '') . " from {$supplierName}")
                : ("Procurement Material Purchase: PR #{$pr?->pr_no} (" . ($supplierName) . ")");

            $items->push($this->buildHubItem([
                'source_type'          => 'procurement_payment',
                'source_module_label'  => 'Procurement Payment',
                'source_id'            => $payment->id,
                'reference_no'         => $pr?->pr_no ? "PR #{$pr->pr_no}" : "PAY-{$payment->id}",
                'view_url'             => $this->safeRoute(['purchase-requests.show', 'procurement.purchase-requests.show'], $pr?->id ?? 1, url('/purchase-requests/' . ($pr?->id ?? 1))),
                'project_id'           => $pr?->project_id,
                'project_name'         => $pr?->project?->name ?: 'General / Head Office',
                'payee_supplier'       => $supplierName,
                'tin'                  => $tin,
                'description'          => $purpose,
                'purpose_detail'       => $purpose,
                'amount'               => $gross,
                'source_vat_amount'    => $vatAmt,
                'payment_method'       => $payment->method ?: 'cash',
                'created_by_name'      => $createdByName,
                'created_at_formatted' => $createdAt,
                'approved_by_name'     => $approvedByName,
                'approved_at_formatted'=> $approvedAt,
                'paid_by_name'         => $paidByName,
                'paid_at_formatted'    => $paidAt,
                'disbursement_date'    => $payment->paid_at ? $payment->paid_at->toDateString() : ($payment->created_at ? $payment->created_at->toDateString() : null),
                'receipt_url'          => $attachment,
                'has_withholding'            => (bool)($payment->has_withholding || (float)($payment->withholding_amount ?? 0) > 0),
                'withholding_rate'           => (float)($payment->withholding_rate ?: 3.00),
                'withholding_amount'         => (float)($payment->withholding_amount ?? 0),
                'withholding_receipt'        => $payment->withholding_receipt ?: null,
                'withholding_receipt_number' => $payment->withholding_receipt_number ?: null,
                'net_amount'                 => $payment->net_amount,
                'category'             => 'material',
                'category_label'       => 'Material',
                'is_capex'             => false,
                'line_items'           => $lineItems,
                'decision'             => $decision,
                'default_status'       => $defaultStatus,
            ]));
        }
    }

    protected function loadCreditStorePayments(Collection $items, Collection $decisions, ?array $scopedProjectIds): void
    {
        $query = CreditStorePayment::with([
            'ledger.project',
            'ledger.purchaseRequest.supplier',
            'ledger.purchaseRequest.items.product',
            'ledger.createdByUser',
            'ledger.authorizedByUser',
            'recordedByUser',
        ]);

        if ($scopedProjectIds !== null) {
            $query->whereHas('ledger', fn($q) => $q->whereIn('project_id', $scopedProjectIds));
        }

        foreach ($query->get() as $payment) {
            $ledger = $payment->ledger;
            $supplierName = $ledger?->supplier_name ?: 'Credit Store';
            $tin = $ledger?->purchaseRequest?->supplier?->tin ?? null;
            $gross = (float) ($payment->gross_amount > 0 ? $payment->gross_amount : $payment->amount);
            $vatAmt = (float) ($payment->vat_amount ?? 0);

            $key = 'credit_store_payment:' . $payment->id;
            $decision = $decisions->get($key);

            $defaultStatus = $vatAmt > 0 ? ExpenseHubVatDecision::STATUS_VAT_APPLIES : ExpenseHubVatDecision::STATUS_PENDING_DECISION;

            $lineItems = [];
            if ($ledger && $ledger->purchaseRequest && $ledger->purchaseRequest->items) {
                foreach ($ledger->purchaseRequest->items as $it) {
                    $itName = $it->product?->name ?: ($it->specifications ?: 'Credit Material');
                    $qty = (float)($it->purchased_quantity > 0 ? $it->purchased_quantity : $it->quantity);
                    $rate = (float)($it->estimated_unit_cost ?? 0);
                    $lineItems[] = [
                        'name'     => $itName,
                        'quantity' => $qty,
                        'unit'     => $it->unit ?: 'pcs',
                        'rate'     => $rate,
                        'total'    => (float)($it->estimated_total ?? ($qty * $rate)),
                    ];
                }
            }

            $createdByName = $ledger?->createdByUser?->name ?: 'Store / Procurement Staff';
            $createdAt = $payment->created_at ? $payment->created_at->format('d M Y, h:i A') : null;
            $approvedByName = $ledger?->authorizedByUser?->name ?: 'Authorized by Finance Head';
            $approvedAt = $ledger?->authorized_at ? Carbon::parse($ledger->authorized_at)->format('d M Y, h:i A') : null;
            $paidByName = $payment->recordedByUser?->name ?: 'Finance Staff';
            $paidAt = $payment->payment_date ? Carbon::parse($payment->payment_date)->format('d M Y') : ($payment->created_at ? $payment->created_at->format('d M Y, h:i A') : null);

            $purpose = "Credit store invoice settlement for {$supplierName}" . ($ledger?->pr_no ? " (PR #{$ledger->pr_no})" : "") . ($ledger?->notes ? ": " . $ledger->notes : "");

            $items->push($this->buildHubItem([
                'source_type'          => 'credit_store_payment',
                'source_module_label'  => 'Credit Store Settlement',
                'source_id'            => $payment->id,
                'reference_no'         => $payment->reference_no ?: ($ledger?->pr_no ? "CS-PR-{$ledger->pr_no}" : "CS-PAY-{$payment->id}"),
                'view_url'             => $this->safeRoute(['finance.credit-store.index'], [], url('/finance/credit-store')),
                'project_id'           => $ledger?->project_id,
                'project_name'         => $ledger?->project?->name ?: 'General / Head Office',
                'payee_supplier'       => $supplierName,
                'tin'                  => $tin,
                'description'          => $purpose,
                'purpose_detail'       => $purpose,
                'amount'               => $gross,
                'source_vat_amount'    => $vatAmt,
                'payment_method'       => $payment->payment_method ?: 'bank_transfer',
                'created_by_name'      => $createdByName,
                'created_at_formatted' => $createdAt,
                'approved_by_name'     => $approvedByName,
                'approved_at_formatted'=> $approvedAt,
                'paid_by_name'         => $paidByName,
                'paid_at_formatted'    => $paidAt,
                'disbursement_date'    => $payment->payment_date ? $payment->payment_date->toDateString() : ($payment->created_at ? $payment->created_at->toDateString() : null),
                'receipt_url'          => $payment->receipt_path,
                'has_withholding'            => (bool)($payment->has_withholding || (float)($payment->withholding_amount ?? 0) > 0),
                'withholding_rate'           => (float)($payment->withholding_rate ?: 3.00),
                'withholding_amount'         => (float)($payment->withholding_amount ?? 0),
                'withholding_receipt'        => $payment->withholding_receipt ?: null,
                'withholding_receipt_number' => $payment->withholding_receipt_number ?: null,
                'net_amount'                 => $payment->net_amount,
                'category'             => 'material',
                'category_label'       => 'Credit Settlement',
                'is_capex'             => false,
                'line_items'           => $lineItems,
                'decision'             => $decision,
                'default_status'       => $defaultStatus,
            ]));
        }
    }

    protected function loadExpenseRequests(Collection $items, Collection $decisions, ?array $scopedProjectIds): void
    {
        $query = ExpenseRequest::with([
            'project',
            'employee',
            'user',
            'paidBy',
            'hrReviewer',
            'gmReviewer',
        ])
            ->where('status', ExpenseRequest::STATUS_PAID)
            ->whereNull('purchase_request_id')
            ->whereNull('credit_store_ledger_id')
            ->where(function ($q) {
                $q->whereNull('category')
                  ->orWhere(function ($sub) {
                      $sub->where('category', 'not like', '%Credit Settlement%')
                          ->where('category', 'not like', '%Petty Cash Replenishment%');
                  });
            })
            ->where(function ($q) {
                $q->whereNull('request_number')
                  ->orWhere(function ($sub) {
                      $sub->where('request_number', 'not like', 'EXP-QP-%')
                          ->where('request_number', 'not like', 'EXP-CR-%');
                  });
            })
            ->where(function ($q) {
                $q->whereNull('other_reason')
                  ->orWhere('other_reason', 'not like', '%Petty Cash Replenishment%');
            })
            ->where(function ($q) {
                $q->whereNull('description')
                  ->orWhere('description', 'not like', '%Petty Cash Replenishment%');
            });

        if ($scopedProjectIds !== null) {
            $query->whereIn('project_id', $scopedProjectIds);
        }

        foreach ($query->get() as $req) {
            $payee = $req->employee?->full_name ?: ($req->user?->name ?: ($req->account_name ?: 'General Payee'));
            $gross = (float) ($req->gross_amount > 0 ? $req->gross_amount : $req->amount);
            $vatAmt = (float) ($req->vat_amount ?? 0);

            $key = 'expense_request:' . $req->id;
            $decision = $decisions->get($key);

            $defaultStatus = $vatAmt > 0 ? ExpenseHubVatDecision::STATUS_VAT_APPLIES : ExpenseHubVatDecision::STATUS_PENDING_DECISION;

            $createdByName = $req->employee?->full_name ?: ($req->user?->name ?: 'Employee Applicant');
            $createdAt = $req->created_at ? $req->created_at->format('d M Y, h:i A') : null;
            $approvedByName = $req->gmReviewer?->name ?: ($req->hrReviewer?->name ?: 'Approved by HR / GM');
            $approvedAt = $req->gm_approved_at ? Carbon::parse($req->gm_approved_at)->format('d M Y, h:i A') : ($req->hr_reviewed_at ? Carbon::parse($req->hr_reviewed_at)->format('d M Y, h:i A') : null);
            $paidByName = $req->paidBy?->name ?: 'Cashier';
            $paidAt = $req->paid_at ? Carbon::parse($req->paid_at)->format('d M Y, h:i A') : null;

            $purpose = "Expense Request (" . ($req->category ?: 'General') . "): " . ($req->description ?: ($req->other_reason ?: 'Operational Expense'));

            $lineItems = [
                [
                    'name'     => $req->category ?: 'General Expense',
                    'quantity' => 1.0,
                    'unit'     => 'item',
                    'rate'     => $gross,
                    'total'    => $gross,
                ]
            ];

            $items->push($this->buildHubItem([
                'source_type'          => 'expense_request',
                'source_module_label'  => 'Expense Request / Voucher',
                'source_id'            => $req->id,
                'reference_no'         => $req->request_number ?: "EXP-REQ-{$req->id}",
                'view_url'             => $this->safeRoute(['expense-requests.show', 'expenses.requests.show'], $req->id, url('/expense-requests/' . $req->id)),
                'project_id'           => $req->project_id,
                'project_name'         => $req->project?->name ?: 'General / Head Office',
                'payee_supplier'       => $payee,
                'tin'                  => null,
                'description'          => $purpose,
                'purpose_detail'       => $purpose,
                'amount'               => $gross,
                'source_vat_amount'    => $vatAmt,
                'payment_method'       => $req->is_bank_payment ? 'bank_transfer' : 'cash',
                'created_by_name'      => $createdByName,
                'created_at_formatted' => $createdAt,
                'approved_by_name'     => $approvedByName,
                'approved_at_formatted'=> $approvedAt,
                'paid_by_name'         => $paidByName,
                'paid_at_formatted'    => $paidAt,
                'disbursement_date'    => $req->paid_at ? $req->paid_at->toDateString() : ($req->created_at ? $req->created_at->toDateString() : null),
                'receipt_url'          => $req->attachment ?: $req->withholding_receipt,
                'has_withholding'            => (bool)($req->has_withholding || (float)($req->withholding_amount ?? 0) > 0),
                'withholding_rate'           => (float)($req->withholding_rate ?: 3.00),
                'withholding_amount'         => (float)($req->withholding_amount ?? 0),
                'withholding_receipt'        => $req->withholding_receipt ?: null,
                'withholding_receipt_number' => $req->withholding_receipt_number ?: null,
                'net_amount'                 => $req->net_amount,
                'category'             => strtolower($req->category ?: 'overhead'),
                'category_label'       => $req->category ?: 'General Expense',
                'is_capex'             => false,
                'line_items'           => $lineItems,
                'decision'             => $decision,
                'default_status'       => $defaultStatus,
            ]));
        }
    }

    protected function loadPettyCashPurchases(Collection $items, Collection $decisions, ?array $scopedProjectIds): void
    {
        $query = PettyCashMaterialPurchase::with([
            'store.project',
            'purchaser',
            'items.product',
        ])
            ->whereNotIn('status', ['rejected', 'cancelled']);

        if ($scopedProjectIds !== null) {
            $query->whereHas('store', fn($q) => $q->whereIn('project_id', $scopedProjectIds));
        }

        foreach ($query->get() as $p) {
            $key = 'petty_cash_purchase:' . $p->id;
            $decision = $decisions->get($key);

            $lineItems = [];
            if ($p->items) {
                foreach ($p->items as $it) {
                    $itName = $it->product?->name ?: ($it->remarks ?: 'Site Material');
                    $qty = (float)$it->quantity;
                    $rate = (float)$it->unit_price;
                    $lineItems[] = [
                        'name'     => $itName,
                        'quantity' => $qty,
                        'unit'     => $it->unit ?: 'pcs',
                        'rate'     => $rate,
                        'total'    => (float)($it->total_price ?? ($qty * $rate)),
                    ];
                }
            }

            $createdByName = $p->purchaser?->name ?: 'Store Keeper';
            $createdAt = $p->created_at ? $p->created_at->format('d M Y, h:i A') : null;
            $approvedByName = 'Site Store / Approved Spot Intake';
            $approvedAt = $p->purchase_date ? Carbon::parse($p->purchase_date)->format('d M Y') : null;
            $paidByName = $p->purchaser?->name ?: 'Store Keeper (Petty Cash)';
            $paidAt = $p->purchase_date ? Carbon::parse($p->purchase_date)->format('d M Y') : null;

            $purpose = !empty($lineItems)
                ? ("Petty Cash Material Intake: " . implode(', ', array_slice(array_column($lineItems, 'name'), 0, 3)) . (count($lineItems) > 3 ? ' +' . (count($lineItems) - 3) . ' more' : '') . " from {$p->supplier_name}")
                : ("Petty Cash Material: #{$p->purchase_no} ({$p->supplier_name})");

            $items->push($this->buildHubItem([
                'source_type'          => 'petty_cash_purchase',
                'source_module_label'  => 'Petty Cash Purchase',
                'source_id'            => $p->id,
                'reference_no'         => $p->purchase_no ?: "PC-{$p->id}",
                'view_url'             => $this->safeRoute(['store-keeper.petty-cash-purchases.show', 'petty-cash-purchases.show'], $p->id, url('/store-keeper/petty-cash-purchases/' . $p->id)),
                'project_id'           => $p->store?->project_id,
                'project_name'         => $p->store?->project?->name ?: ($p->store?->name ?: 'General Site'),
                'payee_supplier'       => $p->supplier_name ?: 'Petty Cash Vendor',
                'tin'                  => $p->supplier_tin,
                'description'          => $purpose,
                'purpose_detail'       => $purpose,
                'amount'               => (float) $p->total_amount,
                'source_vat_amount'    => 0,
                'payment_method'       => 'petty_cash',
                'created_by_name'      => $createdByName,
                'created_at_formatted' => $createdAt,
                'approved_by_name'     => $approvedByName,
                'approved_at_formatted'=> $approvedAt,
                'paid_by_name'         => $paidByName,
                'paid_at_formatted'    => $paidAt,
                'disbursement_date'    => $p->purchase_date ? $p->purchase_date->toDateString() : ($p->created_at ? $p->created_at->toDateString() : null),
                'receipt_url'          => $p->attachment_path,
                'category'             => 'material',
                'category_label'       => 'Site Material',
                'is_capex'             => false,
                'line_items'           => $lineItems,
                'decision'             => $decision,
                'default_status'       => ExpenseHubVatDecision::STATUS_PENDING_DECISION,
            ]));
        }
    }

    protected function loadPayrolls(Collection $items, Collection $decisions, ?array $scopedProjectIds): void
    {
        $query = Payroll::with([
            'employee.project',
            'creator',
            'gmApprover',
            'processedBy',
        ])
            ->where(function ($q) {
                $q->where('status', 'paid');
                if (Schema::hasColumn('payrolls', 'gm_status')) {
                    $q->orWhere('gm_status', 'approved');
                }
            });

        if ($scopedProjectIds !== null) {
            $query->whereHas('employee', fn($q) => $q->whereIn('project_id', $scopedProjectIds));
        }

        foreach ($query->get() as $pay) {
            $employee = $pay->employee;
            $name = $employee ? trim(($employee->first_name ?? '') . ' ' . ($employee->last_name ?? '')) : 'Employee';
            $grossCost = (float) (($pay->gross_salary ?? 0) + ($pay->company_pension ?? 0));

            $key = 'payroll:' . $pay->id;
            $decision = $decisions->get($key);

            $createdByName = $pay->creator?->name ?: 'HR Payroll Officer';
            $createdAt = $pay->created_at ? $pay->created_at->format('d M Y, h:i A') : null;
            $approvedByName = $pay->gmApprover?->name ?: ($pay->processedBy?->name ?: 'General Manager Approval');
            $approvedAt = $pay->gm_approved_at ? Carbon::parse($pay->gm_approved_at)->format('d M Y, h:i A') : null;
            $paidByName = 'Finance Disbursement';
            $paidAt = $pay->paid_at ? Carbon::parse($pay->paid_at)->format('d M Y, h:i A') : null;

            $purpose = "Monthly Payroll Disbursement for {$name} (" . ($pay->month . '/' . $pay->year) . ") - Gross Salary: " . number_format($pay->gross_salary, 2) . " ETB";

            $lineItems = [
                ['name' => 'Monthly Gross Salary', 'quantity' => 1.0, 'unit' => 'month', 'rate' => (float)$pay->gross_salary, 'total' => (float)$pay->gross_salary],
                ['name' => 'Company Pension Contribution (11%)', 'quantity' => 1.0, 'unit' => 'month', 'rate' => (float)($pay->company_pension ?? 0), 'total' => (float)($pay->company_pension ?? 0)],
            ];

            $items->push($this->buildHubItem([
                'source_type'          => 'payroll',
                'source_module_label'  => 'Payroll Disbursement',
                'source_id'            => $pay->id,
                'reference_no'         => $pay->payroll_ref ?: ('PAYROLL-' . $pay->year . '-' . $pay->month . "-{$pay->id}"),
                'view_url'             => $this->safeRoute(['payrolls.show'], $pay->id, url('/payrolls/' . $pay->id)),
                'project_id'           => $employee?->project_id,
                'project_name'         => $employee?->project?->name ?: 'General Staff',
                'payee_supplier'       => $name,
                'tin'                  => $employee?->tin_number,
                'description'          => $purpose,
                'purpose_detail'       => $purpose,
                'amount'               => $grossCost,
                'source_vat_amount'    => 0,
                'payment_method'       => $pay->payment_method ?: 'bank_transfer',
                'created_by_name'      => $createdByName,
                'created_at_formatted' => $createdAt,
                'approved_by_name'     => $approvedByName,
                'approved_at_formatted'=> $approvedAt,
                'paid_by_name'         => $paidByName,
                'paid_at_formatted'    => $paidAt,
                'disbursement_date'    => $pay->paid_at ? $pay->paid_at->toDateString() : ($pay->gm_approved_at ? $pay->gm_approved_at->toDateString() : ($pay->created_at ? $pay->created_at->toDateString() : null)),
                'receipt_url'          => null,
                'category'             => 'labour',
                'category_label'       => 'Salary / Wages',
                'is_capex'             => false,
                'line_items'           => $lineItems,
                'decision'             => $decision,
                'default_status'       => ExpenseHubVatDecision::STATUS_NO_VAT,
                'default_reason_code'  => 'salary_payroll',
                'default_reason_note'  => 'Employee Salary / Labor Allowance',
            ]));
        }
    }

    protected function loadEmployeeAdvances(Collection $items, Collection $decisions, ?array $scopedProjectIds): void
    {
        if (!Schema::hasTable('employee_advances')) {
            return;
        }

        $query = EmployeeAdvance::with([
            'employee.project',
            'approvedByUser',
        ])
            ->whereIn('status', ['disbursed', 'recovered']);

        if ($scopedProjectIds !== null) {
            $query->whereHas('employee', fn($q) => $q->whereIn('project_id', $scopedProjectIds));
        }

        foreach ($query->get() as $adv) {
            $employee = $adv->employee;
            $name = $employee ? trim(($employee->first_name ?? '') . ' ' . ($employee->last_name ?? '')) : 'Employee';

            $key = 'employee_advance:' . $adv->id;
            $decision = $decisions->get($key);

            $createdByName = $name . ' (Employee Applicant)';
            $createdAt = $adv->created_at ? $adv->created_at->format('d M Y, h:i A') : null;
            $approvedByName = $adv->approvedByUser?->name ?: 'HR / GM Approval';
            $approvedAt = $adv->approved_at ? Carbon::parse($adv->approved_at)->format('d M Y, h:i A') : null;
            $paidByName = 'Finance Staff';
            $paidAt = $adv->disbursed_at ? Carbon::parse($adv->disbursed_at)->format('d M Y, h:i A') : null;

            $purpose = "Salary Advance Disbursed to {$name} (Reason: {$adv->reason})";
            $lineItems = [
                ['name' => 'Salary Advance (' . ($adv->reason ?: 'Personal Advance') . ')', 'quantity' => 1.0, 'unit' => 'advance', 'rate' => (float)$adv->amount, 'total' => (float)$adv->amount],
            ];

            $items->push($this->buildHubItem([
                'source_type'          => 'employee_advance',
                'source_module_label'  => 'Salary Advance',
                'source_id'            => $adv->id,
                'reference_no'         => "ADV-{$adv->id}",
                'view_url'             => $this->safeRoute(['payrolls.index'], [], url('/payrolls')),
                'project_id'           => $employee?->project_id,
                'project_name'         => $employee?->project?->name ?: 'General Staff',
                'payee_supplier'       => $name,
                'tin'                  => $employee?->tin_number,
                'description'          => $purpose,
                'purpose_detail'       => $purpose,
                'amount'               => (float) $adv->amount,
                'source_vat_amount'    => 0,
                'payment_method'       => 'bank_transfer',
                'created_by_name'      => $createdByName,
                'created_at_formatted' => $createdAt,
                'approved_by_name'     => $approvedByName,
                'approved_at_formatted'=> $approvedAt,
                'paid_by_name'         => $paidByName,
                'paid_at_formatted'    => $paidAt,
                'disbursement_date'    => $adv->disbursed_at ? $adv->disbursed_at->toDateString() : ($adv->advance_date ? $adv->advance_date->toDateString() : null),
                'receipt_url'          => null,
                'category'             => 'labour',
                'category_label'       => 'Salary Advance',
                'is_capex'             => false,
                'line_items'           => $lineItems,
                'decision'             => $decision,
                'default_status'       => ExpenseHubVatDecision::STATUS_NO_VAT,
                'default_reason_code'  => 'salary_payroll',
                'default_reason_note'  => 'Salary Advance Disbursed',
            ]));
        }
    }

    protected function loadIpcRecords(Collection $items, Collection $decisions, ?array $scopedProjectIds): void
    {
        if (!Schema::hasTable('ipc_records')) {
            return;
        }

        $query = IpcRecord::with([
            'agreement.subcontractor',
            'agreement.supplier',
            'project',
            'createdBy',
            'approvedBy',
            'items',
        ])
            ->where(function ($q) {
                $q->whereIn('status', ['paid', 'approved']);
                if (Schema::hasColumn('ipc_records', 'paid_amount')) {
                    $q->orWhere('paid_amount', '>', 0);
                }
            });

        if ($scopedProjectIds !== null) {
            $query->whereIn('project_id', $scopedProjectIds);
        }

        foreach ($query->get() as $ipc) {
            $subcon = $ipc->agreement?->subcontractor_display_name
                ?: ($ipc->agreement?->subcontractor?->name
                ?: ($ipc->agreement?->subcontractor_name ?: 'Subcontractor'));

            $tin = $ipc->agreement?->supplier?->tin
                ?? ($ipc->agreement?->subcontractor?->tin ?? null);

            $paidAmt = (float) (
                ($ipc->paid_amount ?? 0) > 0
                    ? $ipc->paid_amount
                    : (($ipc->current_certified ?? 0) > 0
                        ? $ipc->current_certified
                        : ($ipc->current_work_value ?? 0))
            );

            if ($paidAmt <= 0) {
                continue;
            }

            $key = 'ipc_record:' . $ipc->id;
            $decision = $decisions->get($key);

            $createdByName = $ipc->createdBy?->name ?: 'Site Engineer';
            $createdAt = $ipc->created_at ? $ipc->created_at->format('d M Y, h:i A') : null;
            $approvedByName = $ipc->approvedBy?->name ?: 'Project Manager / GM Approval';
            $approvedAt = $ipc->approved_at ? Carbon::parse($ipc->approved_at)->format('d M Y, h:i A') : null;
            $paidByName = 'Finance Disbursement';
            $paidAt = $ipc->approved_at ? Carbon::parse($ipc->approved_at)->format('d M Y, h:i A') : null;

            $lineItems = [];
            if ($ipc->items) {
                foreach ($ipc->items as $it) {
                    $qty = (float)$it->quantity;
                    $rate = (float)$it->unit_rate;
                    $lineItems[] = [
                        'name'     => $it->description,
                        'quantity' => $qty,
                        'unit'     => $it->unit ?: 'item',
                        'rate'     => $rate,
                        'total'    => (float)($it->total_amount ?? ($qty * $rate)),
                    ];
                }
            }

            $purpose = "Subcontractor Certified Payment: {$ipc->ipc_no} ({$subcon})" . ($ipc->period_from && $ipc->period_to ? " [Period: " . $ipc->period_from->format('d M Y') . " - " . $ipc->period_to->format('d M Y') . "]" : "");

            $items->push($this->buildHubItem([
                'source_type'          => 'ipc_record',
                'source_module_label'  => 'Subcontractor IPC',
                'source_id'            => $ipc->id,
                'reference_no'         => $ipc->ipc_no ?: "IPC-{$ipc->id}",
                'view_url'             => $this->safeRoute(['ipcs.show'], $ipc->id, url('/ipcs/' . $ipc->id)),
                'project_id'           => $ipc->project_id ?: $ipc->agreement?->project_id,
                'project_name'         => $ipc->project?->name ?: 'Project Site',
                'payee_supplier'       => $subcon,
                'tin'                  => $tin,
                'description'          => $purpose,
                'purpose_detail'       => $purpose,
                'amount'               => $paidAmt,
                'source_vat_amount'    => 0,
                'payment_method'       => 'bank_transfer',
                'created_by_name'      => $createdByName,
                'created_at_formatted' => $createdAt,
                'approved_by_name'     => $approvedByName,
                'approved_at_formatted'=> $approvedAt,
                'paid_by_name'         => $paidByName,
                'paid_at_formatted'    => $paidAt,
                'disbursement_date'    => $ipc->approved_at 
                    ? $ipc->approved_at->toDateString() 
                    : ($ipc->period_to 
                        ? $ipc->period_to->toDateString() 
                        : ($ipc->created_at ? $ipc->created_at->toDateString() : null)),
                'receipt_url'          => $ipc->attachment_path ?? ($ipc->agreement?->agreement_file ?? null),
                'category'             => 'subcontractor',
                'category_label'       => 'Subcontractor',
                'is_capex'             => false,
                'line_items'           => $lineItems,
                'decision'             => $decision,
                'default_status'       => ExpenseHubVatDecision::STATUS_PENDING_DECISION,
            ]));
        }
    }

    protected function loadTaxSettlements(Collection $items, Collection $decisions, ?array $scopedProjectIds): void
    {
        if (!Schema::hasTable('tax_settlements')) {
            return;
        }

        $query = TaxSettlement::with([
            'financeHead',
            'payer',
        ])->where('status', 'paid');

        foreach ($query->get() as $settle) {
            $key = 'tax_settlement:' . $settle->id;
            $decision = $decisions->get($key);

            $createdByName = $settle->financeHead?->name ?: 'Finance Head';
            $createdAt = $settle->created_at ? $settle->created_at->format('d M Y, h:i A') : null;
            $approvedByName = $settle->financeHead?->name ?: 'Finance Head Authorization';
            $approvedAt = $settle->created_at ? $settle->created_at->format('d M Y, h:i A') : null;
            $paidByName = $settle->payer?->name ?: 'Finance Head';
            $paidAt = $settle->paid_at ? Carbon::parse($settle->paid_at)->format('d M Y, h:i A') : null;

            $purpose = "Tax Remittance to Ministry of Revenue (MOR/ERCA): {$settle->settlement_number} (" . strtoupper($settle->tax_type) . ")";
            $lineItems = [
                ['name' => 'Tax Remittance to MOR (' . strtoupper($settle->tax_type) . ')', 'quantity' => 1.0, 'unit' => 'settlement', 'rate' => (float)$settle->total_tax_paid, 'total' => (float)$settle->total_tax_paid],
            ];

            $items->push($this->buildHubItem([
                'source_type'          => 'tax_settlement',
                'source_module_label'  => 'Tax Settlement',
                'source_id'            => $settle->id,
                'reference_no'         => $settle->settlement_number ?: "SETTLE-{$settle->id}",
                'view_url'             => $this->safeRoute(['finance.tax-deductions.index'], [], url('/finance/tax-deductions')),
                'project_id'           => null,
                'project_name'         => 'Head Office / Tax Authority',
                'payee_supplier'       => 'Ministry of Revenue (MOR) / ERCA',
                'tin'                  => 'MOR-GOV',
                'description'          => $purpose,
                'purpose_detail'       => $purpose,
                'amount'               => (float) $settle->total_tax_paid,
                'source_vat_amount'    => 0,
                'payment_method'       => $settle->payment_method ?: 'bank_transfer',
                'created_by_name'      => $createdByName,
                'created_at_formatted' => $createdAt,
                'approved_by_name'     => $approvedByName,
                'approved_at_formatted'=> $approvedAt,
                'paid_by_name'         => $paidByName,
                'paid_at_formatted'    => $paidAt,
                'disbursement_date'    => $settle->paid_at ? $settle->paid_at->toDateString() : ($settle->created_at ? $settle->created_at->toDateString() : null),
                'receipt_url'          => $settle->attachment,
                'category'             => 'overhead',
                'category_label'       => 'Tax Settlement',
                'is_capex'             => false,
                'line_items'           => $lineItems,
                'decision'             => $decision,
                'default_status'       => ExpenseHubVatDecision::STATUS_NO_VAT,
                'default_reason_code'  => 'tax_settlement',
                'default_reason_note'  => 'Government Tax Remittance to Ministry of Revenue',
            ]));
        }
    }

    protected function loadFixedAssets(Collection $items, Collection $decisions, ?array $scopedProjectIds): void
    {
        $query = FixedAsset::with([
            'store.project',
            'creator',
        ]);

        if ($scopedProjectIds !== null) {
            $query->whereHas('store', fn($q) => $q->whereIn('project_id', $scopedProjectIds));
        }

        foreach ($query->get() as $asset) {
            if ($asset->trashed()) continue;

            $cost = (float) (($asset->total_quantity ?? 1) * ($asset->unit_cost ?? 0));
            if ($cost <= 0) continue;

            $key = 'fixed_asset:' . $asset->id;
            $decision = $decisions->get($key);

            $createdByName = $asset->creator?->name ?: 'Store / Procurement Staff';
            $createdAt = $asset->created_at ? $asset->created_at->format('d M Y, h:i A') : null;
            $approvedByName = 'Store Manager / Fixed Asset Verification';
            $approvedAt = $asset->purchase_date ? Carbon::parse($asset->purchase_date)->format('d M Y') : null;
            $paidByName = 'Finance / Procurement';
            $paidAt = $asset->purchase_date ? Carbon::parse($asset->purchase_date)->format('d M Y') : null;

            $purpose = "CapEx Fixed Asset Acquisition: {$asset->name} ({$asset->total_quantity} units @ " . number_format($asset->unit_cost, 2) . " ETB) - Supplier: " . ($asset->supplier ?: 'Asset Vendor');

            $lineItems = [
                [
                    'name'     => $asset->name . ' (' . ($asset->category ?: 'Asset') . ')',
                    'quantity' => (float)($asset->total_quantity ?? 1),
                    'unit'     => 'unit',
                    'rate'     => (float)$asset->unit_cost,
                    'total'    => (float)$cost,
                ]
            ];

            $items->push($this->buildHubItem([
                'source_type'          => 'fixed_asset',
                'source_module_label'  => 'Fixed Asset Acquisition',
                'source_id'            => $asset->id,
                'reference_no'         => $asset->code_prefix ? "AST-{$asset->code_prefix}-{$asset->id}" : "AST-{$asset->id}",
                'view_url'             => $this->safeRoute(['store-manager.fixed-assets.show', 'fixed-assets.show'], $asset->id, url('/store-manager/fixed-assets/' . $asset->id)),
                'project_id'           => $asset->store?->project_id,
                'project_name'         => $asset->store?->project?->name ?: 'General Company',
                'payee_supplier'       => $asset->supplier ?: 'Asset Vendor',
                'tin'                  => null,
                'description'          => $purpose,
                'purpose_detail'       => $purpose,
                'amount'               => $cost,
                'source_vat_amount'    => 0,
                'payment_method'       => 'bank_transfer',
                'created_by_name'      => $createdByName,
                'created_at_formatted' => $createdAt,
                'approved_by_name'     => $approvedByName,
                'approved_at_formatted'=> $approvedAt,
                'paid_by_name'         => $paidByName,
                'paid_at_formatted'    => $paidAt,
                'disbursement_date'    => $asset->purchase_date ? $asset->purchase_date->toDateString() : ($asset->created_at ? $asset->created_at->toDateString() : null),
                'receipt_url'          => null,
                'category'             => 'capex',
                'category_label'       => 'CapEx (Fixed Asset)',
                'is_capex'             => true,
                'line_items'           => $lineItems,
                'decision'             => $decision,
                'default_status'       => ExpenseHubVatDecision::STATUS_VAT_APPLIES,
                'default_purchase_type'=> 1, // 1 = Local Capital Asset Line 65
            ]));
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Build Normalized Item with VAT Logic
    // ─────────────────────────────────────────────────────────────────────────

    protected function buildHubItem(array $data): object
    {
        $amount = (float) $data['amount'];
        $decision = $data['decision'] ?? null;

        if ($decision) {
            $vatStatus    = $decision->vat_status;
            $vatRate      = (float) $decision->vat_rate;
            $isInclusive  = (bool) $decision->is_inclusive;
            $baseAmount   = (float) $decision->base_amount;
            $vatAmount    = (float) $decision->vat_amount;
            $totalAmount  = (float) $decision->total_amount;
            $reasonCode   = $decision->no_vat_reason_code;
            $reasonNote   = $decision->no_vat_reason_note;
            $isLocked     = (bool) $decision->is_locked;
            $purchaseType = (int) $decision->purchase_type;
            $vatCategory  = $decision->vat_category ?: ($data['is_capex'] || $data['category'] === 'material' ? 'G' : 'S');
            $calendarType = $decision->calendar_type ?: 'E';
            $tin          = $decision->custom_seller_tin ?: $data['tin'];
            $fsNo         = $decision->custom_fs_no;
            $mrcNo        = $decision->custom_mrc_no;
            $itemDesc     = $decision->custom_item_description ?: $data['description'];
            $uom          = $decision->uom ?: '9';
            $qty          = (float) $decision->quantity;
            $unitPrice    = (float) $decision->unit_price;
            $hasWithholding    = (bool) ($decision->has_withholding ?? ($data['has_withholding'] ?? false));
            $withholdingRate   = (float) ($decision->withholding_rate > 0 ? $decision->withholding_rate : ($data['withholding_rate'] ?? 3.00));
            $withholdingAmount = (float) ($decision->withholding_amount ?? ($data['withholding_amount'] ?? 0.00));
            $withholdingReceipt = $decision->withholding_receipt ?: ($data['withholding_receipt'] ?? null);
            $withholdingReceiptNumber = $decision->withholding_receipt_number ?: ($data['withholding_receipt_number'] ?? null);
            $netPayableAmount  = (float) ($decision->net_payable_amount ?? max(0, $totalAmount - $withholdingAmount));
            $hasDecision  = true;
        } else {
            $vatStatus = $data['default_status'] ?? ExpenseHubVatDecision::STATUS_PENDING_DECISION;
            $vatRate   = 15.00;
            $isInclusive = true;
            $hasDecision = false;
            $isLocked    = false;
            $purchaseType = $data['default_purchase_type'] ?? ($data['is_capex'] ? 1 : 3);
            $vatCategory  = ($data['is_capex'] || $data['category'] === 'material') ? 'G' : 'S';
            $calendarType = 'E';
            $reasonCode   = $data['default_reason_code'] ?? null;
            $reasonNote   = $data['default_reason_note'] ?? null;
            $tin          = $data['tin'];
            $fsNo         = null;
            $mrcNo        = null;
            $itemDesc     = $data['description'];
            $uom          = '9';
            $qty          = 1.0;
            $unitPrice    = $amount;
            $hasWithholding    = (bool) ($data['has_withholding'] ?? false);
            $withholdingRate   = (float) ($data['withholding_rate'] ?? 3.00);
            $withholdingAmount = (float) ($data['withholding_amount'] ?? ($hasWithholding ? round($amount * ($withholdingRate / 100), 2) : 0.00));
            $withholdingReceipt = $data['withholding_receipt'] ?? null;
            $withholdingReceiptNumber = $data['withholding_receipt_number'] ?? null;
            $netPayableAmount  = max(0, $amount - $withholdingAmount);

            if ($vatStatus === ExpenseHubVatDecision::STATUS_VAT_APPLIES) {
                if (($data['source_vat_amount'] ?? 0) > 0) {
                    $vatAmount  = (float) $data['source_vat_amount'];
                    $baseAmount = max(0, $amount - $vatAmount);
                    $totalAmount = $amount;
                } else {
                    $calc = ExpenseHubVatDecision::computeVatAmounts($amount, 15.00, true);
                    $baseAmount  = $calc['base'];
                    $vatAmount   = $calc['vat'];
                    $totalAmount = $calc['total'];
                }
            } else {
                $baseAmount  = $amount;
                $vatAmount   = 0.00;
                $totalAmount = $amount;
            }
        }

        // Warnings check (non-blocking)
        $warnings = [];
        if ($vatStatus === ExpenseHubVatDecision::STATUS_VAT_APPLIES) {
            $cleanTin = preg_replace('/[^0-9]/', '', (string)$tin);
            if (empty($tin) || strlen($cleanTin) !== 10) {
                $warnings[] = "Missing or invalid 10-digit Vendor TIN ({$tin})";
            }
            if (empty($fsNo) && empty($data['receipt_url'])) {
                $warnings[] = "No Fiscal Receipt or voucher uploaded";
            }
        }

        $supplierName = !empty($decision?->custom_seller_name)
            ? $decision->custom_seller_name
            : ($data['payee_supplier'] ?? 'Supplier');

        return (object) array_merge($data, [
            'payee_supplier'             => $supplierName,
            'custom_seller_name'         => $supplierName,
            'tin'                        => $tin,
            'vat_status'                 => $vatStatus,
            'vat_rate'                   => $vatRate,
            'is_inclusive'               => $isInclusive,
            'has_withholding'            => $hasWithholding,
            'withholding_rate'           => $withholdingRate,
            'withholding_amount'         => $withholdingAmount,
            'withholding_receipt'        => $withholdingReceipt,
            'withholding_receipt_number' => $withholdingReceiptNumber,
            'net_payable_amount'         => $netPayableAmount,
            'decision_base_amount'       => $baseAmount,
            'decision_vat_amount'        => $vatAmount,
            'decision_total_amount'      => $totalAmount,
            'reason_code'                => $reasonCode,
            'reason_note'                => $reasonNote,
            'is_locked'                  => $isLocked,
            'has_decision'               => $hasDecision,
            'purchase_type'              => $purchaseType,
            'vat_category'               => $vatCategory,
            'calendar_type'              => $calendarType,
            'fs_no'                      => $fsNo,
            'mrc_no'                     => $mrcNo,
            'item_desc'                  => $itemDesc,
            'uom'                        => $uom,
            'qty'                        => $qty,
            'unit_price'                 => $unitPrice,
            'warnings'                   => $warnings,
            'decision_model'             => $decision,
        ]);
    }

    protected function applyFilters(Collection $items, array $filters): Collection
    {
        return $items->filter(function ($item) use ($filters) {
            if (!empty($filters['date_from'])) {
                if (!$item->disbursement_date || $item->disbursement_date < $filters['date_from']) {
                    return false;
                }
            }
            if (!empty($filters['date_to'])) {
                if (!$item->disbursement_date || $item->disbursement_date > $filters['date_to']) {
                    return false;
                }
            }
            if (!empty($filters['project_id'])) {
                if ((string)$item->project_id !== (string)$filters['project_id']) {
                    return false;
                }
            }
            if (!empty($filters['source_module'])) {
                if ($item->source_type !== $filters['source_module']) {
                    return false;
                }
            }
            if (!empty($filters['vat_status'])) {
                if ($item->vat_status !== $filters['vat_status']) {
                    return false;
                }
            }
            if (!empty($filters['withholding'])) {
                if ($filters['withholding'] === 'yes') {
                    if (empty($item->has_withholding) && (float)($item->withholding_amount ?? 0) <= 0) {
                        return false;
                    }
                } elseif ($filters['withholding'] === 'no') {
                    if (!empty($item->has_withholding) || (float)($item->withholding_amount ?? 0) > 0) {
                        return false;
                    }
                }
            }
            if (!empty($filters['category'])) {
                if ($item->category !== $filters['category']) {
                    return false;
                }
            }
            if (!empty($filters['supplier'])) {
                $term = strtolower(trim($filters['supplier']));
                if (!str_contains(strtolower($item->payee_supplier ?? ''), $term)) {
                    return false;
                }
            }
            if (!empty($filters['search'])) {
                $term = strtolower(trim($filters['search']));
                $haystack = strtolower(
                    ($item->reference_no ?? '') . ' ' .
                    ($item->payee_supplier ?? '') . ' ' .
                    ($item->tin ?? '') . ' ' .
                    ($item->description ?? '') . ' ' .
                    ($item->project_name ?? '')
                );
                if (!str_contains($haystack, $term)) {
                    return false;
                }
            }

            return true;
        });
    }

    protected function shouldIncludeSource(string $module, array $filters): bool
    {
        if (!Schema::hasTable($module)) {
            return false;
        }
        if (empty($filters['source_module'])) {
            return true;
        }
        return $filters['source_module'] === $module;
    }

    protected function resolveScopedProjectIds(?User $user): ?array
    {
        if (!$user) {
            return null;
        }

        // Global roles view all projects
        if ($user->hasAnyRole(['global_admin', 'admin', 'gm', 'general_manager', 'finance_head', 'finance', 'auditor', 'Auditor'])) {
            return null;
        }

        $projectIds = $user->projects()->pluck('projects.id')->toArray();
        if (empty($projectIds) && isset($user->project_id) && $user->project_id) {
            $projectIds[] = $user->project_id;
        }

        return $projectIds;
    }

    /**
     * Safely resolve a route by checking candidate route names in order, with fallback to URL.
     * Prevents RouteNotFoundException from crashing the Expense Hub page.
     *
     * @param string|array $routes Single route name or array of candidate names in priority order
     * @param mixed $parameters Route parameter(s)
     * @param string $fallbackUrl Fallback URL if route resolution fails
     */
    protected function safeRoute(string|array $routes, $parameters = [], string $fallbackUrl = '#'): string
    {
        $candidateNames = is_array($routes) ? $routes : [$routes];
        foreach ($candidateNames as $name) {
            try {
                if (Route::has($name)) {
                    return route($name, $parameters);
                }
            } catch (\Throwable $e) {
                // Ignore route parameter binding exception and continue to next or fallback
            }
        }
        return $fallbackUrl;
    }
}
