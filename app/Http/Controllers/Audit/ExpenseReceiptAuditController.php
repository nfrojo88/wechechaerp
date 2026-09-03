<?php

namespace App\Http\Controllers\Audit;

use App\Http\Controllers\Controller;
use App\Models\Expense;
use App\Models\ExpenseRequest;
use App\Models\OfficeMaterialRequest;
use App\Models\ProcurementReceipt;
use App\Models\PurchaseRequest;
use App\Models\ActivityLog;
use App\Models\User;
use App\Services\FileUploadService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

class ExpenseReceiptAuditController extends Controller
{
    /**
     * Self-healing schema to guarantee audit receipt tracking fields exist
     */
    protected static function ensureSchema(): void
    {
        try {
            if (Schema::hasTable('expense_requests')) {
                Schema::table('expense_requests', function (Blueprint $table) {
                    if (!Schema::hasColumn('expense_requests', 'audit_receipt_status')) {
                        $table->string('audit_receipt_status', 50)->nullable()->index();
                    }
                    if (!Schema::hasColumn('expense_requests', 'audit_receipt_notes')) {
                        $table->text('audit_receipt_notes')->nullable();
                    }
                    if (!Schema::hasColumn('expense_requests', 'audit_receipt_requested_at')) {
                        $table->timestamp('audit_receipt_requested_at')->nullable();
                    }
                    if (!Schema::hasColumn('expense_requests', 'audit_receipt_requested_by')) {
                        $table->foreignId('audit_receipt_requested_by')->nullable()->constrained('users')->nullOnDelete();
                    }
                    if (!Schema::hasColumn('expense_requests', 'audit_receipt_verified_at')) {
                        $table->timestamp('audit_receipt_verified_at')->nullable();
                    }
                    if (!Schema::hasColumn('expense_requests', 'audit_receipt_verified_by')) {
                        $table->foreignId('audit_receipt_verified_by')->nullable()->constrained('users')->nullOnDelete();
                    }
                });
            }

            if (Schema::hasTable('office_material_requests')) {
                Schema::table('office_material_requests', function (Blueprint $table) {
                    if (!Schema::hasColumn('office_material_requests', 'audit_receipt_status')) {
                        $table->string('audit_receipt_status', 50)->nullable()->index();
                    }
                    if (!Schema::hasColumn('office_material_requests', 'audit_receipt_notes')) {
                        $table->text('audit_receipt_notes')->nullable();
                    }
                    if (!Schema::hasColumn('office_material_requests', 'audit_receipt_requested_at')) {
                        $table->timestamp('audit_receipt_requested_at')->nullable();
                    }
                    if (!Schema::hasColumn('office_material_requests', 'audit_receipt_requested_by')) {
                        $table->foreignId('audit_receipt_requested_by')->nullable()->constrained('users')->nullOnDelete();
                    }
                    if (!Schema::hasColumn('office_material_requests', 'audit_receipt_verified_at')) {
                        $table->timestamp('audit_receipt_verified_at')->nullable();
                    }
                    if (!Schema::hasColumn('office_material_requests', 'audit_receipt_verified_by')) {
                        $table->foreignId('audit_receipt_verified_by')->nullable()->constrained('users')->nullOnDelete();
                    }
                });
            }

            if (Schema::hasTable('expenses')) {
                Schema::table('expenses', function (Blueprint $table) {
                    if (!Schema::hasColumn('expenses', 'receipt_path')) {
                        $table->string('receipt_path')->nullable();
                    }
                    if (!Schema::hasColumn('expenses', 'audit_receipt_status')) {
                        $table->string('audit_receipt_status', 50)->nullable()->index();
                    }
                    if (!Schema::hasColumn('expenses', 'audit_receipt_notes')) {
                        $table->text('audit_receipt_notes')->nullable();
                    }
                    if (!Schema::hasColumn('expenses', 'audit_receipt_requested_at')) {
                        $table->timestamp('audit_receipt_requested_at')->nullable();
                    }
                    if (!Schema::hasColumn('expenses', 'audit_receipt_requested_by')) {
                        $table->foreignId('audit_receipt_requested_by')->nullable()->constrained('users')->nullOnDelete();
                    }
                    if (!Schema::hasColumn('expenses', 'audit_receipt_verified_at')) {
                        $table->timestamp('audit_receipt_verified_at')->nullable();
                    }
                    if (!Schema::hasColumn('expenses', 'audit_receipt_verified_by')) {
                        $table->foreignId('audit_receipt_verified_by')->nullable()->constrained('users')->nullOnDelete();
                    }
                });
            }
        } catch (\Throwable $e) {
            // Silently continue if database cannot be altered
        }
    }

    /**
     * Check if user is an Auditor or Admin
     */
    private function authorizeAuditor(): void
    {
        $user = Auth::user();
        if (!$user) {
            abort(403, 'Unauthorized. Please login.');
        }

        $rolesStr = strtolower(implode(' ', $user->getRoleNames()->toArray()));
        $isAuditorOrAdmin = $user->hasAnyRole([
            'auditor', 'Auditor', 'audit', 'audit_team', 'internal_auditor',
            'admin', 'global_admin', 'super_admin'
        ]) || str_contains($rolesStr, 'audit') || str_contains($rolesStr, 'admin') || $user->can('audit.view');

        if (!$isAuditorOrAdmin) {
            abort(403, 'Unauthorized access. Only Auditors and Administrators can view the Expense Receipt Audit Center.');
        }
    }

    /**
     * Main Expense Receipt Audit Center
     */
    public function index(Request $request)
    {
        $this->authorizeAuditor();
        self::ensureSchema();

        $items = new Collection();

        // 1. Fetch Expense Requests
        try {
            $expenseRequests = ExpenseRequest::with(['user', 'employee', 'paidBy', 'chartOfAccount', 'coa'])
                ->whereNull('purchase_request_id')
                ->where('request_number', 'not like', 'EXP-PR-%')
                ->latest()
                ->get()
                ->map(function ($req) {
                    $hasReceipt = !empty($req->attachment);
                    $receiptStatus = $req->audit_receipt_status;
                    if (!$receiptStatus) {
                        $receiptStatus = $hasReceipt ? 'attached' : 'missing';
                    }

                    $receiptUrl = null;
                    if ($hasReceipt) {
                        $receiptUrl = FileUploadService::url($req->attachment);
                    }

                    $applicantName = $req->employee ? $req->employee->full_name : ($req->user->name ?? 'Employee');
                    $dept = $req->employee->department ?? ($req->user->department ?? 'General');

                    return (object) [
                        'unique_key'       => 'er_' . $req->id,
                        'source_type'      => 'expense_request',
                        'source_id'        => $req->id,
                        'reference_no'     => $req->request_number ?? ('REQ-' . $req->id),
                        'date'             => $req->paid_at ?? $req->created_at,
                        'requester'        => $applicantName,
                        'department'       => $dept,
                        'category'         => $req->category . ($req->other_reason ? ' (' . $req->other_reason . ')' : ''),
                        'description'      => $req->description,
                        'amount'           => (float) ($req->net_amount > 0 ? $req->net_amount : $req->amount),
                        'payment_status'   => $req->status,
                        'paid_at'          => $req->paid_at,
                        'paid_by_name'     => $req->paidBy->name ?? null,
                        'payment_ref'      => $req->payment_reference,
                        'account_name'     => $req->chartOfAccount->name ?? ($req->coa->name ?? 'Direct Account'),
                        'has_receipt'      => $hasReceipt,
                        'receipt_path'     => $req->attachment,
                        'receipt_url'      => $receiptUrl,
                        'audit_status'     => $receiptStatus,
                        'audit_notes'      => $req->audit_receipt_notes,
                        'audit_req_at'     => $req->audit_receipt_requested_at,
                        'audit_verified_at'=> $req->audit_receipt_verified_at,
                        'raw_model'        => $req,
                    ];
                });
            $items = $items->concat($expenseRequests);
        } catch (\Throwable $e) {}

        // 2. Fetch Purchase Requests (Procurement Cash / Direct Buys)
        try {
            $prs = PurchaseRequest::with(['project', 'requestedBy', 'payment.coaAccount', 'payment.assignedStaff', 'payment.paidBy', 'receipt'])
                ->whereNotNull('status')
                ->whereIn('status', [
                    PurchaseRequest::STATUS_PENDING_PAYMENT,
                    PurchaseRequest::STATUS_PENDING_RECEIPT_UPLOAD,
                    PurchaseRequest::STATUS_PENDING_RECEIPT_VERIFY,
                    PurchaseRequest::STATUS_PENDING_STORE_REVIEW,
                    PurchaseRequest::STATUS_INTAKE_COMPLETE,
                    PurchaseRequest::STATUS_COMPLETED,
                ])
                ->latest()
                ->get()
                ->map(function ($pr) {
                    $payment = $pr->payment;
                    $receipt = $pr->receipt;
                    $hasReceipt = !empty($receipt?->file_path);
                    $receiptUrl = $hasReceipt ? FileUploadService::url($receipt->file_path) : null;

                    $receiptStatus = $receipt?->verification_status;
                    if (!$receiptStatus) {
                        $receiptStatus = $hasReceipt ? 'attached' : 'missing';
                    }

                    $amount = (float)($payment?->amount ?? $pr->direct_buy_amount ?? 0);
                    $prRef = str_starts_with((string)$pr->pr_no, 'PR-') ? $pr->pr_no : ('PR-' . ($pr->pr_no ?? $pr->id));

                    return (object) [
                        'unique_key'       => 'pr_' . $pr->id,
                        'source_type'      => 'purchase_request',
                        'source_id'        => $pr->id,
                        'reference_no'     => $prRef,
                        'date'             => $payment?->paid_at ?? $pr->created_at,
                        'requester'        => $pr->requestedBy?->name ?? 'Procurement',
                        'department'       => $pr->project ? $pr->project->name : 'Site / Project',
                        'category'         => 'Material Purchase',
                        'description'      => 'PR #' . $pr->pr_no . ($pr->justification ? ' - ' . $pr->justification : ''),
                        'amount'           => $amount,
                        'payment_status'   => $payment?->status ? ucfirst(str_replace('_', ' ', $payment->status)) : ucfirst(str_replace('_', ' ', $pr->status)),
                        'paid_at'          => $payment?->paid_at,
                        'paid_by_name'     => $payment?->paidBy?->name ?? null,
                        'payment_ref'      => $payment?->notes,
                        'account_name'     => $payment?->coaAccount?->name ?? 'Procurement Fund',
                        'has_receipt'      => $hasReceipt,
                        'receipt_path'     => $receipt?->file_path,
                        'receipt_url'      => $receiptUrl,
                        'audit_status'     => $receiptStatus,
                        'audit_notes'      => $receipt?->verification_notes ?? $receipt?->notes,
                        'audit_req_at'     => null,
                        'audit_verified_at'=> $receipt?->verified_at,
                        'raw_model'        => $pr,
                    ];
                });
            $items = $items->concat($prs);
        } catch (\Throwable $e) {}

        // 3. Fetch Office Material Requests
        try {
            if (Schema::hasTable('office_material_requests')) {
                $officeRequests = OfficeMaterialRequest::with(['requestedBy', 'paidBy', 'coa'])
                    ->latest()
                    ->get()
                    ->map(function ($req) {
                        $hasReceipt = !empty($req->attachment);
                        $receiptStatus = $req->audit_receipt_status;
                        if (!$receiptStatus) {
                            $receiptStatus = $hasReceipt ? 'attached' : 'missing';
                        }

                        $receiptUrl = $hasReceipt ? (Storage::disk('public')->exists($req->attachment) ? Storage::url($req->attachment) : FileUploadService::url($req->attachment)) : null;

                        return (object) [
                            'unique_key'       => 'omr_' . $req->id,
                            'source_type'      => 'office_material_request',
                            'source_id'        => $req->id,
                            'reference_no'     => $req->request_no,
                            'date'             => $req->paid_at ?? $req->created_at,
                            'requester'        => $req->requestedBy?->name ?? 'Office Staff',
                            'department'       => 'Head Office',
                            'category'         => 'Office Material',
                            'description'      => $req->office_purpose ?? 'Office Supplies & Materials',
                            'amount'           => (float) ($req->amount ?? 0),
                            'payment_status'   => ucfirst(str_replace('_', ' ', $req->status)),
                            'paid_at'          => $req->paid_at,
                            'paid_by_name'     => $req->paidBy?->name ?? null,
                            'payment_ref'      => $req->payment_reference,
                            'account_name'     => $req->coa?->name ?? 'Office Fund',
                            'has_receipt'      => $hasReceipt,
                            'receipt_path'     => $req->attachment,
                            'receipt_url'      => $receiptUrl,
                            'audit_status'     => $receiptStatus,
                            'audit_notes'      => $req->audit_receipt_notes,
                            'audit_req_at'     => $req->audit_receipt_requested_at,
                            'audit_verified_at'=> $req->audit_receipt_verified_at,
                            'raw_model'        => $req,
                        ];
                    });
                $items = $items->concat($officeRequests);
            }
        } catch (\Throwable $e) {}

        // 4. Fetch Direct Expenses
        try {
            $expenses = Expense::with(['project', 'creator', 'approver'])
                ->where('description', 'not like', 'Material Purchase for PR #%')
                ->latest()
                ->get()
                ->map(function ($exp) {
                    $hasReceipt = !empty($exp->receipt_path);
                    $receiptStatus = $exp->audit_receipt_status;
                    if (!$receiptStatus) {
                        $receiptStatus = $hasReceipt ? 'attached' : 'missing';
                    }

                    $receiptUrl = $hasReceipt ? FileUploadService::url($exp->receipt_path) : null;

                    return (object) [
                        'unique_key'       => 'exp_' . $exp->id,
                        'source_type'      => 'expense',
                        'source_id'        => $exp->id,
                        'reference_no'     => 'EXP-' . str_pad($exp->id, 5, '0', STR_PAD_LEFT),
                        'date'             => $exp->expense_date ?? $exp->created_at,
                        'requester'        => $exp->creator?->name ?? 'Direct Entry',
                        'department'       => $exp->project ? $exp->project->name : 'General',
                        'category'         => ucfirst($exp->category ?? 'Operational'),
                        'description'      => $exp->description,
                        'amount'           => (float) $exp->amount,
                        'payment_status'   => ucfirst($exp->status ?? 'Posted'),
                        'paid_at'          => $exp->created_at,
                        'paid_by_name'     => $exp->creator?->name ?? null,
                        'payment_ref'      => 'VOUCHER-' . $exp->id,
                        'account_name'     => 'Direct Ledger',
                        'has_receipt'      => $hasReceipt,
                        'receipt_path'     => $exp->receipt_path,
                        'receipt_url'      => $receiptUrl,
                        'audit_status'     => $receiptStatus,
                        'audit_notes'      => $exp->audit_receipt_notes,
                        'audit_req_at'     => $exp->audit_receipt_requested_at,
                        'audit_verified_at'=> $exp->audit_receipt_verified_at,
                        'raw_model'        => $exp,
                    ];
                });
            $items = $items->concat($expenses);
        } catch (\Throwable $e) {}

        // Calculate Overall Metrics before Tab filtering
        $totalExpensesCount = $items->count();
        $totalExpensesAmount = $items->sum('amount');
        
        $missingReceiptItems = $items->filter(fn($i) => !$i->has_receipt && !in_array($i->audit_status, ['requested', 'clarification_needed', 'verified_no_receipt']));
        $missingReceiptCount = $missingReceiptItems->count();
        $missingReceiptAmount = $missingReceiptItems->sum('amount');

        $requestedReceiptItems = $items->filter(fn($i) => $i->audit_status === 'requested' || $i->audit_status === 'clarification_needed');
        $requestedReceiptCount = $requestedReceiptItems->count();
        $requestedReceiptAmount = $requestedReceiptItems->sum('amount');

        $attachedReceiptItems = $items->filter(fn($i) => $i->has_receipt);
        $attachedReceiptCount = $attachedReceiptItems->count();
        $attachedReceiptAmount = $attachedReceiptItems->sum('amount');

        $verifiedNoReceiptItems = $items->filter(fn($i) => $i->audit_status === 'verified_no_receipt');
        $verifiedNoReceiptCount = $verifiedNoReceiptItems->count();
        $verifiedNoReceiptAmount = $verifiedNoReceiptItems->sum('amount');

        $verifiedCount = $items->filter(fn($i) => !empty($i->audit_verified_at) || in_array($i->audit_status, ['verified', 'verified_no_receipt']))->count();

        // Filter by Tab
        $tab = $request->input('tab', 'all');
        if ($tab === 'missing') {
            $items = $items->filter(fn($i) => !$i->has_receipt && !in_array($i->audit_status, ['requested', 'clarification_needed', 'verified_no_receipt']));
        } elseif ($tab === 'requested') {
            $items = $items->filter(fn($i) => $i->audit_status === 'requested' || $i->audit_status === 'clarification_needed');
        } elseif ($tab === 'attached') {
            $items = $items->filter(fn($i) => $i->has_receipt);
        } elseif ($tab === 'verified_no_receipt') {
            $items = $items->filter(fn($i) => $i->audit_status === 'verified_no_receipt');
        } elseif ($tab === 'verified') {
            $items = $items->filter(fn($i) => !empty($i->audit_verified_at) || in_array($i->audit_status, ['verified', 'verified_no_receipt']));
        }

        // Search Filter
        if ($request->filled('search')) {
            $search = strtolower($request->search);
            $items = $items->filter(function ($i) use ($search) {
                return str_contains(strtolower($i->reference_no), $search)
                    || str_contains(strtolower($i->requester), $search)
                    || str_contains(strtolower($i->description), $search)
                    || str_contains(strtolower($i->category), $search)
                    || str_contains(strtolower($i->department), $search);
            });
        }

        // Source Type Filter
        if ($request->filled('type') && $request->type !== 'all') {
            $items = $items->where('source_type', $request->type);
        }

        // Date Filters
        if ($request->filled('start_date')) {
            $startDate = Carbon::parse($request->start_date)->startOfDay();
            $items = $items->filter(fn($i) => Carbon::parse($i->date)->gte($startDate));
        }
        if ($request->filled('end_date')) {
            $endDate = Carbon::parse($request->end_date)->endOfDay();
            $items = $items->filter(fn($i) => Carbon::parse($i->date)->lte($endDate));
        }

        // Sort latest first
        $items = $items->sortByDesc(fn($i) => Carbon::parse($i->date)->timestamp)->values();

        // Paginate results manually for Collection
        $perPage = 25;
        $currentPage = (int) $request->input('page', 1);
        $paginatedItems = new \Illuminate\Pagination\LengthAwarePaginator(
            $items->forPage($currentPage, $perPage),
            $items->count(),
            $perPage,
            $currentPage,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        return view('audit.expense_receipts.index', [
            'items'                 => $paginatedItems,
            'tab'                   => $tab,
            'totalExpensesCount'    => $totalExpensesCount,
            'totalExpensesAmount'   => $totalExpensesAmount,
            'missingReceiptCount'   => $missingReceiptCount,
            'missingReceiptAmount'  => $missingReceiptAmount,
            'requestedReceiptCount' => $requestedReceiptCount,
            'requestedReceiptAmount'=> $requestedReceiptAmount,
            'attachedReceiptCount'  => $attachedReceiptCount,
            'attachedReceiptAmount' => $attachedReceiptAmount,
            'verifiedNoReceiptCount'=> $verifiedNoReceiptCount,
            'verifiedNoReceiptAmount'=> $verifiedNoReceiptAmount,
            'verifiedCount'         => $verifiedCount,
        ]);
    }

    /**
     * Auditor asks for / requests a receipt for an expense item
     */
    public function askReceipt(Request $request)
    {
        $this->authorizeAuditor();
        self::ensureSchema();

        $request->validate([
            'source_type'  => 'required|in:expense_request,purchase_request,office_material_request,expense',
            'source_id'    => 'required|integer',
            'inquiry_note' => 'required|string|max:1000',
        ]);

        $user = Auth::user();
        $sourceType = $request->source_type;
        $sourceId = $request->source_id;
        $note = $request->inquiry_note;
        $refNo = '';

        if ($sourceType === 'expense_request') {
            $item = ExpenseRequest::findOrFail($sourceId);
            $item->update([
                'audit_receipt_status'       => 'requested',
                'audit_receipt_notes'        => $note,
                'audit_receipt_requested_at' => now(),
                'audit_receipt_requested_by' => $user->id,
            ]);
            $refNo = $item->request_number;
        } elseif ($sourceType === 'purchase_request') {
            $pr = PurchaseRequest::findOrFail($sourceId);
            $receipt = ProcurementReceipt::firstOrCreate(
                ['purchase_request_id' => $pr->id],
                ['verification_status' => 'receipt_requested', 'verification_notes' => $note]
            );
            $receipt->update([
                'verification_status' => 'receipt_requested',
                'verification_notes'  => $note,
            ]);
            $refNo = $pr->pr_no;
        } elseif ($sourceType === 'office_material_request') {
            $item = OfficeMaterialRequest::findOrFail($sourceId);
            $item->update([
                'audit_receipt_status'       => 'requested',
                'audit_receipt_notes'        => $note,
                'audit_receipt_requested_at' => now(),
                'audit_receipt_requested_by' => $user->id,
            ]);
            $refNo = $item->request_no;
        } elseif ($sourceType === 'expense') {
            $item = Expense::findOrFail($sourceId);
            $item->update([
                'audit_receipt_status'       => 'requested',
                'audit_receipt_notes'        => $note,
                'audit_receipt_requested_at' => now(),
                'audit_receipt_requested_by' => $user->id,
            ]);
            $refNo = 'EXP-' . $item->id;
        }

        ActivityLog::log(
            'receipt_requested',
            "Auditor {$user->name} requested receipt for expense [{$refNo}]: {$note}",
            'Expense Audit & Compliance'
        );

        return redirect()->back()->with('success', "Formal receipt inquiry logged for {$refNo}. Requester and finance notified.");
    }

    /**
     * Upload & attach a receipt directly to an expense record
     */
    public function attachReceipt(Request $request)
    {
        $this->authorizeAuditor();
        self::ensureSchema();

        $request->validate([
            'source_type'  => 'required|in:expense_request,purchase_request,office_material_request,expense',
            'source_id'    => 'required|integer',
            'receipt_file' => 'required|file|mimes:pdf,jpg,jpeg,png,webp|max:10240',
            'notes'        => 'nullable|string|max:500',
        ]);

        $user = Auth::user();
        $sourceType = $request->source_type;
        $sourceId = $request->source_id;
        $uploadedPath = FileUploadService::upload($request->file('receipt_file'), 'expense_audit_receipts');
        $refNo = '';

        if ($sourceType === 'expense_request') {
            $item = ExpenseRequest::findOrFail($sourceId);
            $item->update([
                'attachment'                 => $uploadedPath,
                'audit_receipt_status'       => 'attached',
                'audit_receipt_notes'        => $request->notes ?: 'Receipt uploaded via Audit Center.',
                'audit_receipt_verified_at'  => now(),
                'audit_receipt_verified_by'  => $user->id,
            ]);
            $refNo = $item->request_number;
        } elseif ($sourceType === 'purchase_request') {
            $pr = PurchaseRequest::findOrFail($sourceId);
            $receipt = ProcurementReceipt::updateOrCreate(
                ['purchase_request_id' => $pr->id],
                [
                    'file_path'           => $uploadedPath,
                    'original_filename'   => $request->file('receipt_file')->getClientOriginalName(),
                    'notes'               => $request->notes ?: 'Receipt attached via Audit Hub',
                    'uploaded_by'         => $user->id,
                    'verification_status' => 'verified',
                    'verified_by'         => $user->id,
                    'verified_at'         => now(),
                ]
            );
            $refNo = $pr->pr_no;
        } elseif ($sourceType === 'office_material_request') {
            $item = OfficeMaterialRequest::findOrFail($sourceId);
            $item->update([
                'attachment'                 => $uploadedPath,
                'audit_receipt_status'       => 'attached',
                'audit_receipt_notes'        => $request->notes ?: 'Receipt uploaded via Audit Center.',
                'audit_receipt_verified_at'  => now(),
                'audit_receipt_verified_by'  => $user->id,
            ]);
            $refNo = $item->request_no;
        } elseif ($sourceType === 'expense') {
            $item = Expense::findOrFail($sourceId);
            $item->update([
                'receipt_path'               => $uploadedPath,
                'audit_receipt_status'       => 'attached',
                'audit_receipt_notes'        => $request->notes ?: 'Receipt uploaded via Audit Center.',
                'audit_receipt_verified_at'  => now(),
                'audit_receipt_verified_by'  => $user->id,
            ]);
            $refNo = 'EXP-' . $item->id;
        }

        ActivityLog::log(
            'receipt_attached',
            "Auditor {$user->name} attached receipt document for expense [{$refNo}]",
            'Expense Audit & Compliance'
        );

        return redirect()->back()->with('success', "Receipt successfully uploaded and attached to {$refNo}.");
    }

    /**
     * Auditor verifies an attached receipt
     */
    public function verifyReceipt(Request $request)
    {
        $this->authorizeAuditor();
        self::ensureSchema();

        $request->validate([
            'source_type' => 'required|in:expense_request,purchase_request,office_material_request,expense',
            'source_id'   => 'required|integer',
        ]);

        $user = Auth::user();
        $sourceType = $request->source_type;
        $sourceId = $request->source_id;
        $refNo = '';

        if ($sourceType === 'expense_request') {
            $item = ExpenseRequest::findOrFail($sourceId);
            $item->update([
                'audit_receipt_status'      => 'verified',
                'audit_receipt_verified_at' => now(),
                'audit_receipt_verified_by' => $user->id,
            ]);
            $refNo = $item->request_number;
        } elseif ($sourceType === 'purchase_request') {
            $pr = PurchaseRequest::findOrFail($sourceId);
            if ($pr->receipt) {
                $pr->receipt->update([
                    'verification_status' => 'verified',
                    'verified_by'         => $user->id,
                    'verified_at'         => now(),
                ]);
            }
            $refNo = $pr->pr_no;
        } elseif ($sourceType === 'office_material_request') {
            $item = OfficeMaterialRequest::findOrFail($sourceId);
            $item->update([
                'audit_receipt_status'      => 'verified',
                'audit_receipt_verified_at' => now(),
                'audit_receipt_verified_by' => $user->id,
            ]);
            $refNo = $item->request_no;
        } elseif ($sourceType === 'expense') {
            $item = Expense::findOrFail($sourceId);
            $item->update([
                'audit_receipt_status'      => 'verified',
                'audit_receipt_verified_at' => now(),
                'audit_receipt_verified_by' => $user->id,
            ]);
            $refNo = 'EXP-' . $item->id;
        }

        ActivityLog::log(
            'receipt_verified',
            "Auditor {$user->name} verified receipt for expense [{$refNo}]",
            'Expense Audit & Compliance'
        );

        return redirect()->back()->with('success', "Receipt verified successfully for {$refNo}.");
    }

    /**
     * Auditor verifies an expense without requiring a physical receipt (e.g. taxi, loading/unloading, parking, minor petty cash)
     */
    public function verifyWithoutReceipt(Request $request)
    {
        $this->authorizeAuditor();
        self::ensureSchema();

        $request->validate([
            'source_type'   => 'required|in:expense_request,purchase_request,office_material_request,expense',
            'source_id'     => 'required|integer',
            'justification' => 'nullable|string|max:1000',
        ]);

        $user = Auth::user();
        $sourceType = $request->source_type;
        $sourceId = $request->source_id;
        $notes = $request->justification ?: 'Verified by Auditor without physical receipt (operational cash expense waiver).';
        $refNo = '';

        if ($sourceType === 'expense_request') {
            $item = ExpenseRequest::findOrFail($sourceId);
            $item->update([
                'audit_receipt_status'      => 'verified_no_receipt',
                'audit_receipt_notes'       => $notes,
                'audit_receipt_verified_at' => now(),
                'audit_receipt_verified_by' => $user->id,
            ]);
            $refNo = $item->request_number;
        } elseif ($sourceType === 'purchase_request') {
            $pr = PurchaseRequest::findOrFail($sourceId);
            $receipt = ProcurementReceipt::firstOrCreate(
                ['purchase_request_id' => $pr->id],
                ['verification_status' => 'verified_no_receipt', 'verification_notes' => $notes]
            );
            $receipt->update([
                'verification_status' => 'verified_no_receipt',
                'verification_notes'  => $notes,
                'verified_by'         => $user->id,
                'verified_at'         => now(),
            ]);
            $refNo = $pr->pr_no;
        } elseif ($sourceType === 'office_material_request') {
            $item = OfficeMaterialRequest::findOrFail($sourceId);
            $item->update([
                'audit_receipt_status'      => 'verified_no_receipt',
                'audit_receipt_notes'       => $notes,
                'audit_receipt_verified_at' => now(),
                'audit_receipt_verified_by' => $user->id,
            ]);
            $refNo = $item->request_no;
        } elseif ($sourceType === 'expense') {
            $item = Expense::findOrFail($sourceId);
            $item->update([
                'audit_receipt_status'      => 'verified_no_receipt',
                'audit_receipt_notes'       => $notes,
                'audit_receipt_verified_at' => now(),
                'audit_receipt_verified_by' => $user->id,
            ]);
            $refNo = 'EXP-' . $item->id;
        }

        ActivityLog::log(
            'receipt_verified_without_attachment',
            "Auditor {$user->name} verified expense [{$refNo}] without physical receipt. Reason: {$notes}",
            'Expense Audit & Compliance'
        );

        return redirect()->back()->with('success', "Expense {$refNo} verified without receipt (audit waiver applied).");
    }
}

