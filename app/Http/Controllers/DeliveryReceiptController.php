<?php

namespace App\Http\Controllers;

use App\Models\DeliveryReceipt;
use App\Models\ProcurementReceipt;
use App\Models\CreditStoreLedger;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequest;
use App\Models\Store;
use App\Models\ExpenseRequest;
use App\Models\Expense;
use App\Models\OfficeMaterialRequest;
use App\Models\ChartOfAccount;
use App\Models\BankAccount;
use App\Models\ActivityLog;
use App\Services\InventoryService;
use App\Services\FileUploadService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class DeliveryReceiptController extends Controller
{
    public function __construct(private InventoryService $inventoryService) {}

    public function index(Request $request)
    {
        $search = $request->input('search');
        $statusFilter = $request->input('status', 'all');

        $user = Auth::user();
        $userId = $user ? $user->id : null;
        $roles = $user ? $user->roles->pluck('name')->map(fn($r) => strtolower(str_replace([' ', '-'], '_', trim($r))))->toArray() : [];
        $isElevated = in_array('admin', $roles) || in_array('global_admin', $roles) || in_array('super_admin', $roles) || in_array('finance_head', $roles) || in_array('finance_manager', $roles) || in_array('auditor', $roles) || in_array('audit', $roles);
        $isFinance = $isElevated || in_array('finance', $roles) || in_array('finance_staff', $roles);

        // Accounts assigned to the logged-in user (Petty Cash, Bank Accounts, etc.)
        $assignedCoaIds = $userId ? ChartOfAccount::where('assigned_to', $userId)->pluck('id')->toArray() : [];
        $assignedBankIds = $userId ? BankAccount::where('assigned_to', $userId)->pluck('id')->toArray() : [];

        // -------------------------------------------------------------
        // Inquired Receipts (Auditor asked for receipts)
        // Scoped to: (1) Assigned Person (Requester/Staff) OR (2) Pay Assigned Account Person
        // -------------------------------------------------------------
        $erInqQuery = ExpenseRequest::with([
            'user', 'employee', 'paidBy', 'chartOfAccount.manager', 'coa.manager', 'bankAccount', 'auditReceiptRequestedBy'
        ])->where('audit_receipt_status', 'requested');

        if (!$isElevated && $userId) {
            $erInqQuery->where(function($q) use ($userId, $assignedCoaIds, $assignedBankIds) {
                // Assigned requester / staff
                $q->where('user_id', $userId)
                  ->orWhere('assigned_finance_staff_id', $userId)
                  ->orWhere('paid_by', $userId);

                // Pay assigned account holder
                if (!empty($assignedCoaIds)) {
                    $q->orWhereIn('coa_id', $assignedCoaIds)
                      ->orWhereIn('chart_of_account_id', $assignedCoaIds);
                }
                if (!empty($assignedBankIds)) {
                    $q->orWhereIn('bank_account_id', $assignedBankIds);
                }
            });
        }
        $inquiredExpenseRequests = $erInqQuery->latest('audit_receipt_requested_at')->get();

        $prInqQuery = PurchaseRequest::with([
            'project', 'requestedBy', 'payment.coaAccount.manager', 'payment.assignedStaff', 'payment.paidBy', 'receipt'
        ])->whereHas('receipt', function($rq) {
            $rq->where('verification_status', 'receipt_requested');
        });

        if (!$isElevated && $userId) {
            $prInqQuery->where(function($q) use ($userId, $assignedCoaIds) {
                $q->where('requested_by', $userId)
                  ->orWhereHas('payment', function($pq) use ($userId, $assignedCoaIds) {
                      $pq->where('paid_by', $userId)
                         ->orWhere('assigned_finance_staff_id', $userId);
                      if (!empty($assignedCoaIds)) {
                          $pq->orWhereIn('coa_account_id', $assignedCoaIds);
                      }
                  });
            });
        }
        $inquiredPurchaseRequests = $prInqQuery->latest()->get();

        $omrQuery = OfficeMaterialRequest::with(['requestedBy', 'approvedBy'])
            ->where('audit_receipt_status', 'requested');
        if (!$isElevated && $userId) {
            $omrQuery->where('requested_by', $userId);
        }
        $inquiredOmrs = $omrQuery->latest('audit_receipt_requested_at')->get();

        $expQuery = Expense::with(['creator', 'project'])
            ->where('audit_receipt_status', 'requested');
        if (!$isElevated && $userId) {
            $expQuery->where('created_by', $userId);
        }
        $inquiredExpenses = $expQuery->latest('audit_receipt_requested_at')->get();

        // Build consolidated list of inquired items
        $inquiredReceipts = collect();

        foreach ($inquiredExpenseRequests as $er) {
            $payingAcct = $er->chartOfAccount?->name ?? ($er->coa?->name ?? ($er->bankAccount?->account_name ?? 'Direct Account'));
            $assignedStaff = $er->chartOfAccount?->manager?->name ?? ($er->chartOfAccount?->assignedStaff?->name ?? null);
            $payingAcctLabel = $payingAcct . ($assignedStaff ? " (Assigned: {$assignedStaff})" : '');

            $inquiredReceipts->push((object)[
                'unique_key'       => 'er_' . $er->id,
                'source_type'      => 'expense_request',
                'source_id'        => $er->id,
                'reference_no'     => $er->request_number ?? ('REQ-' . $er->id),
                'date'             => $er->paid_at ?? $er->created_at,
                'requester'        => $er->employee?->full_name ?? ($er->user?->name ?? 'Staff'),
                'department'       => $er->employee?->department ?? ($er->user?->department ?? 'General'),
                'category'         => $er->category . ($er->other_reason ? ' (' . $er->other_reason . ')' : ''),
                'description'      => $er->description,
                'amount'           => (float)($er->net_amount > 0 ? $er->net_amount : $er->amount),
                'paying_account'   => $payingAcctLabel,
                'payment_status'   => 'Paid',
                'audit_note'       => $er->audit_receipt_notes,
                'requested_at'     => $er->audit_receipt_requested_at,
                'requested_by'     => $er->auditReceiptRequestedBy?->name ?? 'Auditor',
                'has_receipt'      => !empty($er->attachment),
                'receipt_url'      => !empty($er->attachment) ? FileUploadService::url($er->attachment) : null,
                'raw_model'        => $er,
            ]);
        }

        foreach ($inquiredPurchaseRequests as $pr) {
            $receipt = $pr->receipt;
            $payment = $pr->payment;
            $payingAcct = $payment?->coaAccount?->name ?? 'Procurement Fund';
            $assignedStaff = $payment?->coaAccount?->manager?->name ?? ($payment?->assignedStaff?->name ?? null);
            $payingAcctLabel = $payingAcct . ($assignedStaff ? " (Assigned: {$assignedStaff})" : '');

            $inquiredReceipts->push((object)[
                'unique_key'       => 'pr_' . $pr->id,
                'source_type'      => 'purchase_request',
                'source_id'        => $pr->id,
                'reference_no'     => str_starts_with((string)$pr->pr_no, 'PR-') ? $pr->pr_no : ('PR-' . ($pr->pr_no ?? $pr->id)),
                'date'             => $payment?->paid_at ?? $pr->created_at,
                'requester'        => $pr->requestedBy?->name ?? 'Procurement',
                'department'       => $pr->project?->name ?? 'Site / Project',
                'category'         => 'Material Purchase',
                'description'      => 'PR #' . $pr->pr_no . ($pr->justification ? ' - ' . $pr->justification : ''),
                'amount'           => (float)($payment?->amount ?? $pr->direct_buy_amount ?? 0),
                'paying_account'   => $payingAcctLabel,
                'payment_status'   => 'Paid',
                'audit_note'       => $receipt?->verification_notes ?? 'Auditor requested official vendor receipt.',
                'requested_at'     => $receipt?->updated_at,
                'requested_by'     => 'Auditor',
                'has_receipt'      => !empty($receipt?->file_path),
                'receipt_url'      => !empty($receipt?->file_path) ? FileUploadService::url($receipt->file_path) : null,
                'raw_model'        => $pr,
            ]);
        }

        foreach ($inquiredOmrs as $omr) {
            $inquiredReceipts->push((object)[
                'unique_key'       => 'omr_' . $omr->id,
                'source_type'      => 'office_material_request',
                'source_id'        => $omr->id,
                'reference_no'     => $omr->request_no ?? ('OMR-' . $omr->id),
                'date'             => $omr->created_at,
                'requester'        => $omr->requestedBy?->name ?? 'Staff',
                'department'       => $omr->department ?? 'General',
                'category'         => 'Office Material',
                'description'      => $omr->description ?? 'Office Material Request',
                'amount'           => (float)($omr->total_price ?? 0),
                'paying_account'   => 'Office Supply Account',
                'payment_status'   => 'Approved',
                'audit_note'       => $omr->audit_receipt_notes,
                'requested_at'     => $omr->audit_receipt_requested_at,
                'requested_by'     => 'Auditor',
                'has_receipt'      => !empty($omr->attachment),
                'receipt_url'      => !empty($omr->attachment) ? FileUploadService::url($omr->attachment) : null,
                'raw_model'        => $omr,
            ]);
        }

        foreach ($inquiredExpenses as $exp) {
            $inquiredReceipts->push((object)[
                'unique_key'       => 'exp_' . $exp->id,
                'source_type'      => 'expense',
                'source_id'        => $exp->id,
                'reference_no'     => 'EXP-' . $exp->id,
                'date'             => $exp->expense_date ?? $exp->created_at,
                'requester'        => $exp->creator?->name ?? 'Staff',
                'department'       => $exp->project?->name ?? 'General',
                'category'         => ucfirst($exp->category ?? 'Expense'),
                'description'      => $exp->description,
                'amount'           => (float)($exp->amount ?? 0),
                'paying_account'   => 'Company Expense Account',
                'payment_status'   => 'Paid',
                'audit_note'       => $exp->audit_receipt_notes,
                'requested_at'     => $exp->audit_receipt_requested_at,
                'requested_by'     => 'Auditor',
                'has_receipt'      => !empty($exp->receipt_path),
                'receipt_url'      => !empty($exp->receipt_path) ? FileUploadService::url($exp->receipt_path) : null,
                'raw_model'        => $exp,
            ]);
        }

        $inquiredReceipts = $inquiredReceipts->sortByDesc('requested_at')->values();
        $inquiredReceiptsCount = $inquiredReceipts->count();

        // If user has pending auditor inquiries and didn't specify a tab, default to inquired_receipts!
        $defaultTab = ($inquiredReceiptsCount > 0 && !$request->has('tab')) ? 'inquired_receipts' : 'pr_receipts';
        $activeTab = $request->input('tab', $defaultTab);

        // 1. Vendor Purchase Receipts (PR Receipts uploaded by Procurement for Finance verification)
        $prReceiptQuery = ProcurementReceipt::with([
            'purchaseRequest.project',
            'purchaseRequest.requestedBy',
            'purchaseRequest.supplier',
            'purchaseRequest.payment.coaAccount',
            'purchaseRequest.payment.assignedStaff',
            'uploadedBy',
            'verifiedBy'
        ]);

        if ($search) {
            $prReceiptQuery->where(function($q) use ($search) {
                $q->where('original_filename', 'like', "%{$search}%")
                  ->orWhere('notes', 'like', "%{$search}%")
                  ->orWhere('verification_notes', 'like', "%{$search}%")
                  ->orWhereHas('purchaseRequest', function($pq) use ($search) {
                      $pq->where('pr_no', 'like', "%{$search}%")
                         ->orWhere('title', 'like', "%{$search}%");
                  })
                  ->orWhereHas('uploadedBy', function($uq) use ($search) {
                      $uq->where('name', 'like', "%{$search}%");
                  });
            });
        }

        if ($statusFilter !== 'all') {
            $prReceiptQuery->where('verification_status', $statusFilter);
        }

        $procurementReceipts = $prReceiptQuery->latest()->paginate(15, ['*'], 'pr_page')->withQueryString();

        // 2. Store Goods Delivery Receipts (GRN / Model 19)
        $drQuery = DeliveryReceipt::with(['purchaseOrder.supplier', 'purchaseOrder.project', 'store', 'receivedBy', 'items.product']);
        if ($search) {
            $drQuery->where(function($q) use ($search) {
                $q->where('dr_no', 'like', "%{$search}%")
                  ->orWhere('challan_no', 'like', "%{$search}%")
                  ->orWhere('vehicle_no', 'like', "%{$search}%")
                  ->orWhereHas('store', function($sq) use ($search) {
                      $sq->where('name', 'like', "%{$search}%");
                  });
            });
        }
        $deliveryReceipts = $drQuery->latest()->paginate(15, ['*'], 'dr_page')->withQueryString();

        // 3. Credit Store Purchases & Invoices (COA 5110)
        $creditReceiptQuery = CreditStoreLedger::with(['purchaseRequest.project', 'project', 'payments', 'coaAccount']);
        if ($search) {
            $creditReceiptQuery->where(function($q) use ($search) {
                $q->where('pr_no', 'like', "%{$search}%")
                  ->orWhere('supplier_name', 'like', "%{$search}%")
                  ->orWhere('notes', 'like', "%{$search}%");
            });
        }
        $creditReceipts = $creditReceiptQuery->latest()->paginate(15, ['*'], 'credit_page')->withQueryString();

        // Stat counts
        $pendingPrReceiptsCount = ProcurementReceipt::where('verification_status', 'pending')->count();
        $verifiedPrReceiptsCount = ProcurementReceipt::where('verification_status', 'verified')->count();
        $totalPrReceiptsCount = ProcurementReceipt::count();
        $totalDeliveryReceiptsCount = DeliveryReceipt::count();

        return view('procurement.delivery-receipts.index', compact(
            'procurementReceipts',
            'deliveryReceipts',
            'creditReceipts',
            'inquiredReceipts',
            'inquiredReceiptsCount',
            'activeTab',
            'pendingPrReceiptsCount',
            'verifiedPrReceiptsCount',
            'totalPrReceiptsCount',
            'totalDeliveryReceiptsCount',
            'isFinance'
        ));
    }

    /**
     * Upload receipt directly in response to an Auditor inquiry
     */
    public function uploadInquiredReceipt(Request $request)
    {
        $request->validate([
            'source_type'  => 'required|in:expense_request,purchase_request,office_material_request,expense',
            'source_id'    => 'required|integer',
            'receipt_file' => 'required|file|mimes:pdf,jpg,jpeg,png,webp|max:10240',
            'notes'        => 'nullable|string|max:500',
        ]);

        $user = Auth::user();
        $sourceType = $request->source_type;
        $sourceId = $request->source_id;
        $uploadedPath = FileUploadService::upload($request->file('receipt_file'), 'expense_receipts');
        $refNo = '';

        if ($sourceType === 'expense_request') {
            $item = ExpenseRequest::findOrFail($sourceId);
            $item->update([
                'attachment'                 => $uploadedPath,
                'audit_receipt_status'       => 'attached',
                'audit_receipt_notes'        => ($item->audit_receipt_notes ? $item->audit_receipt_notes . ' | ' : '') . 'Receipt submitted: ' . ($request->notes ?: 'Uploaded via Receipts Center by ' . $user->name),
            ]);
            $refNo = $item->request_number ?? ('REQ-' . $item->id);
        } elseif ($sourceType === 'purchase_request') {
            $pr = PurchaseRequest::findOrFail($sourceId);
            $receipt = ProcurementReceipt::updateOrCreate(
                ['purchase_request_id' => $pr->id],
                [
                    'file_path'           => $uploadedPath,
                    'original_filename'   => $request->file('receipt_file')->getClientOriginalName(),
                    'notes'               => $request->notes ?: 'Receipt uploaded in response to audit inquiry',
                    'uploaded_by'         => $user->id,
                    'verification_status' => 'pending',
                ]
            );
            $refNo = $pr->pr_no;
        } elseif ($sourceType === 'office_material_request') {
            $item = OfficeMaterialRequest::findOrFail($sourceId);
            $item->update([
                'attachment'           => $uploadedPath,
                'audit_receipt_status' => 'attached',
                'audit_receipt_notes'  => ($item->audit_receipt_notes ? $item->audit_receipt_notes . ' | ' : '') . 'Receipt submitted: ' . ($request->notes ?: 'Uploaded by ' . $user->name),
            ]);
            $refNo = $item->request_no;
        } elseif ($sourceType === 'expense') {
            $item = Expense::findOrFail($sourceId);
            $item->update([
                'receipt_path'         => $uploadedPath,
                'audit_receipt_status' => 'attached',
                'audit_receipt_notes'  => ($item->audit_receipt_notes ? $item->audit_receipt_notes . ' | ' : '') . 'Receipt submitted: ' . ($request->notes ?: 'Uploaded by ' . $user->name),
            ]);
            $refNo = 'EXP-' . $item->id;
        }

        ActivityLog::log(
            'receipt_submitted',
            "User {$user->name} uploaded requested receipt for [{$refNo}]: " . ($request->notes ?? 'Attached via Receipts Center'),
            'Receipts & Compliance'
        );

        return back()->with('success', "Receipt for {$refNo} successfully uploaded and submitted for audit verification!");
    }

    public function verifyProcurementReceipt(Request $request, ProcurementReceipt $procurementReceipt)
    {
        $request->validate([
            'verification_status' => 'required|in:verified,rejected',
            'verification_notes'  => 'nullable|string|max:500',
        ]);

        $procurementReceipt->update([
            'verification_status' => $request->verification_status,
            'verification_notes'  => $request->verification_notes,
            'verified_by'         => Auth::id(),
            'verified_at'         => now(),
        ]);

        $statusText = $request->verification_status === 'verified' ? 'verified and approved' : 'marked as rejected';
        return back()->with('success', "Vendor Purchase Receipt for PR #{$procurementReceipt->purchaseRequest?->pr_no} has been {$statusText} by Finance.");
    }

    public function verifyDeliveryReceipt(Request $request, DeliveryReceipt $deliveryReceipt)
    {
        $deliveryReceipt->update([
            'status' => 'verified',
        ]);

        return back()->with('success', "Delivery Receipt #{$deliveryReceipt->dr_no} marked as verified.");
    }

    public function create()
    {
        $pos    = PurchaseOrder::where('status', '!=', 'cancelled')->get();
        $stores = Store::where('is_active', true)->get();
        return view('procurement.delivery-receipts.create', compact('pos', 'stores'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'purchase_order_id'         => 'required|exists:purchase_orders,id',
            'store_id'                  => 'required|exists:stores,id',
            'received_date'             => 'required|date',
            'challan_no'                => 'nullable|string|max:100',
            'vehicle_no'                => 'nullable|string|max:50',
            'notes'                     => 'nullable|string',
            'items'                     => 'required|array|min:1',
            'items.*.product_id'        => 'required|exists:products,id',
            'items.*.po_item_id'        => 'nullable|exists:purchase_order_items,id',
            'items.*.quantity_received' => 'required|numeric|min:0.001',
            'items.*.accepted_quantity' => 'required|numeric|min:0',
            'items.*.unit'              => 'required|string|max:20',
        ]);

        DB::transaction(function () use ($request) {
            $no = 'DR-' . date('Ymd') . '-' . str_pad(DeliveryReceipt::count() + 1, 4, '0', STR_PAD_LEFT);

            $dr = DeliveryReceipt::create([
                'dr_no'             => $no,
                'purchase_order_id' => $request->purchase_order_id,
                'received_by'       => Auth::id(),
                'store_id'          => $request->store_id,
                'received_date'     => $request->received_date,
                'notes'             => $request->notes,
                'challan_no'        => $request->challan_no,
                'vehicle_no'        => $request->vehicle_no,
                'status'            => 'verified',
            ]);

            foreach ($request->items as $item) {
                $dr->items()->create([
                    'product_id'        => $item['product_id'],
                    'po_item_id'        => $item['po_item_id'] ?? null,
                    'quantity_received' => $item['quantity_received'],
                    'accepted_quantity' => $item['accepted_quantity'],
                    'rejected_quantity' => $item['quantity_received'] - $item['accepted_quantity'],
                    'unit'              => $item['unit'],
                    'rejection_reason'  => $item['rejection_reason'] ?? null,
                ]);

                // Update inventory via service
                if ($item['accepted_quantity'] > 0) {
                    $this->inventoryService->stockIn(
                        $request->store_id,
                        $item['product_id'],
                        $item['accepted_quantity'],
                        $item['unit_price'] ?? 0,
                        'purchase_receipt',
                        Auth::id(),
                        'delivery_receipt',
                        $dr->id
                    );
                }
            }
        });

        return redirect()->route('delivery-receipts.index')->with('success', 'Delivery Receipt recorded and inventory updated.');
    }

    public function show(DeliveryReceipt $deliveryReceipt)
    {
        $deliveryReceipt->load(['purchaseOrder.supplier', 'store', 'receivedBy', 'items.product']);
        return view('procurement.delivery-receipts.show', compact('deliveryReceipt'));
    }
}
