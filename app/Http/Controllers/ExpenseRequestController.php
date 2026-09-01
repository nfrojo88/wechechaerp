<?php

namespace App\Http\Controllers;

use App\Models\BankAccount;
use App\Models\BankTransaction;
use App\Models\ChartOfAccount;
use App\Models\ExpenseRequest;
use App\Models\PettyCashReplenishment;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use App\Models\ActivityLog;
use App\Models\User;
use App\Services\CloudinaryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ExpenseRequestController extends Controller
{
    /**
     * Ensure expense_requests table exists.
     */
    private function ensureTableExists()
    {
        try {
            if (!\Illuminate\Support\Facades\Schema::hasTable('expense_requests')) {
                \Illuminate\Support\Facades\Schema::create('expense_requests', function (\Illuminate\Database\Schema\Blueprint $table) {
                    $table->id();
                    $table->string('request_number', 50)->unique();
                    $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                    $table->foreignId('employee_id')->nullable()->constrained('employees')->nullOnDelete();
                    $table->string('category', 50);
                    $table->string('other_reason')->nullable();
                    $table->decimal('amount', 12, 2);
                    $table->text('description');
                    $table->string('attachment')->nullable();
                    $table->string('status', 50)->default('Pending (HR Review)');
                    $table->foreignId('hr_reviewer_id')->nullable()->constrained('users')->nullOnDelete();
                    $table->timestamp('hr_reviewed_at')->nullable();
                    $table->foreignId('gm_reviewer_id')->nullable()->constrained('users')->nullOnDelete();
                    $table->foreignId('gm_approver_id')->nullable()->constrained('users')->nullOnDelete();
                    $table->timestamp('gm_reviewed_at')->nullable();
                    $table->timestamp('gm_approved_at')->nullable();
                    $table->text('rejection_reason')->nullable();
                    $table->foreignId('finance_head_id')->nullable()->constrained('users')->nullOnDelete();
                    $table->foreignId('bank_account_id')->nullable()->constrained('bank_accounts')->nullOnDelete();
                    $table->foreignId('coa_id')->nullable()->constrained('chart_of_accounts')->nullOnDelete();
                    $table->foreignId('chart_of_account_id')->nullable()->constrained('chart_of_accounts')->nullOnDelete();
                    $table->foreignId('assigned_finance_staff_id')->nullable()->constrained('users')->nullOnDelete();
                    $table->foreignId('finance_staff_id')->nullable()->constrained('users')->nullOnDelete();
                    $table->timestamp('finance_assigned_at')->nullable();
                    $table->foreignId('paid_by')->nullable()->constrained('users')->nullOnDelete();
                    $table->timestamp('paid_at')->nullable();
                    $table->string('payment_reference')->nullable();
                    $table->text('payment_notes')->nullable();
                    $table->timestamps();
                    $table->softDeletes();
                });
            } else {
                \Illuminate\Support\Facades\Schema::table('expense_requests', function (\Illuminate\Database\Schema\Blueprint $table) {
                    if (!\Illuminate\Support\Facades\Schema::hasColumn('expense_requests', 'gm_approver_id')) {
                        $table->unsignedBigInteger('gm_approver_id')->nullable();
                    }
                    if (!\Illuminate\Support\Facades\Schema::hasColumn('expense_requests', 'gm_approved_at')) {
                        $table->timestamp('gm_approved_at')->nullable();
                    }
                    if (!\Illuminate\Support\Facades\Schema::hasColumn('expense_requests', 'finance_head_id')) {
                        $table->unsignedBigInteger('finance_head_id')->nullable();
                    }
                    if (!\Illuminate\Support\Facades\Schema::hasColumn('expense_requests', 'finance_staff_id')) {
                        $table->unsignedBigInteger('finance_staff_id')->nullable();
                    }
                    if (!\Illuminate\Support\Facades\Schema::hasColumn('expense_requests', 'chart_of_account_id')) {
                        $table->unsignedBigInteger('chart_of_account_id')->nullable();
                    }
                    if (!\Illuminate\Support\Facades\Schema::hasColumn('expense_requests', 'coa_id')) {
                        $table->unsignedBigInteger('coa_id')->nullable();
                    }
                    if (!\Illuminate\Support\Facades\Schema::hasColumn('expense_requests', 'assigned_finance_staff_id')) {
                        $table->unsignedBigInteger('assigned_finance_staff_id')->nullable();
                    }
                    if (!\Illuminate\Support\Facades\Schema::hasColumn('expense_requests', 'paid_by')) {
                        $table->unsignedBigInteger('paid_by')->nullable();
                    }
                    if (!\Illuminate\Support\Facades\Schema::hasColumn('expense_requests', 'paid_at')) {
                        $table->timestamp('paid_at')->nullable();
                    }
                    if (!\Illuminate\Support\Facades\Schema::hasColumn('expense_requests', 'payment_reference')) {
                        $table->string('payment_reference')->nullable();
                    }
                    if (!\Illuminate\Support\Facades\Schema::hasColumn('expense_requests', 'maintenance_request_id')) {
                        $table->unsignedBigInteger('maintenance_request_id')->nullable();
                    }
                    if (!\Illuminate\Support\Facades\Schema::hasColumn('expense_requests', 'gross_amount')) {
                        $table->decimal('gross_amount', 14, 2)->nullable();
                    }
                    if (!\Illuminate\Support\Facades\Schema::hasColumn('expense_requests', 'vat_type')) {
                        $table->string('vat_type', 30)->default('none');
                    }
                    if (!\Illuminate\Support\Facades\Schema::hasColumn('expense_requests', 'vat_rate')) {
                        $table->decimal('vat_rate', 5, 2)->default(15.00);
                    }
                    if (!\Illuminate\Support\Facades\Schema::hasColumn('expense_requests', 'vat_amount')) {
                        $table->decimal('vat_amount', 14, 2)->default(0);
                    }
                    if (!\Illuminate\Support\Facades\Schema::hasColumn('expense_requests', 'has_withholding')) {
                        $table->boolean('has_withholding')->default(false);
                    }
                    if (!\Illuminate\Support\Facades\Schema::hasColumn('expense_requests', 'withholding_rate')) {
                        $table->decimal('withholding_rate', 5, 2)->default(3.00);
                    }

                    if (!\Illuminate\Support\Facades\Schema::hasColumn('expense_requests', 'withholding_amount')) {
                        $table->decimal('withholding_amount', 14, 2)->default(0);
                    }
                    if (!\Illuminate\Support\Facades\Schema::hasColumn('expense_requests', 'withholding_receipt')) {
                        $table->string('withholding_receipt', 500)->nullable();
                    }
                    if (!\Illuminate\Support\Facades\Schema::hasColumn('expense_requests', 'withholding_receipt_number')) {
                        $table->string('withholding_receipt_number', 100)->nullable();
                    }
                    if (!\Illuminate\Support\Facades\Schema::hasColumn('expense_requests', 'net_amount')) {
                        $table->decimal('net_amount', 14, 2)->nullable();
                    }
                    if (!\Illuminate\Support\Facades\Schema::hasColumn('expense_requests', 'service_type')) {
                        $table->string('service_type', 100)->nullable();
                    }

                });
            }
        } catch (\Throwable $e) {
            Log::error('Expense table auto-heal error: ' . $e->getMessage());
        }
    }


    /**
     * Display listing of expense requests based on role & active tab.
     */
    public function index(Request $request)
    {
        $this->ensureTableExists();

        /** @var \App\Models\User $user */
        $user = auth()->user();
        if (!$user) {
            return redirect()->route('login');
        }

        $userRoleNames = strtolower(implode(' ', $user->getRoleNames()->toArray()));
        $isHr = $user->can('hr.view') || str_contains($userRoleNames, 'hr') || str_contains($userRoleNames, 'coordinator') || $user->hasAnyRole(['admin', 'global_admin', 'coordinator', 'Coordinator']);
        $isGm = str_contains($userRoleNames, 'gm') || $user->hasAnyRole(['gm', 'admin', 'global_admin']);
        $isFinanceHead = str_contains($userRoleNames, 'finance_head') || str_contains($userRoleNames, 'finance_manager') || $user->hasAnyRole(['finance_head', 'admin', 'global_admin']);
        $isFinanceStaff = str_contains($userRoleNames, 'finance') || str_contains($userRoleNames, 'cashier') || str_contains($userRoleNames, 'accountant') || $user->hasAnyRole(['admin', 'global_admin']);

        // Tab selection (strictly personal requests for Ask Money portal)
        $tab = $request->query('tab', 'my_requests');
        if (!in_array($tab, ['my_requests', 'paid_history', 'rejected_history'])) {
            $tab = 'my_requests';
        }

        // Counters for personal badges
        $counters = [
            'my_requests'      => ExpenseRequest::where('user_id', $user->id)->whereNotIn('status', [ExpenseRequest::STATUS_PAID, ExpenseRequest::STATUS_REJECTED])->count(),
            'paid_history'     => ExpenseRequest::where('user_id', $user->id)->where('status', ExpenseRequest::STATUS_PAID)->count(),
            'rejected_history' => ExpenseRequest::where('user_id', $user->id)->where('status', ExpenseRequest::STATUS_REJECTED)->count(),
        ];

        // Build query for logged-in user's own requests
        $query = ExpenseRequest::with([
            'user',
            'employee',
            'hrReviewer',
            'gmApprover',
            'gmReviewer',
            'financeHead',
            'financeStaff',
            'assignedFinanceStaff',
            'paidBy',
            'bankAccount',
            'chartOfAccount',
            'coa.manager',
            'maintenanceRequest',
        ])->where('user_id', $user->id);

        switch ($tab) {
            case 'paid_history':
                $query->where('status', ExpenseRequest::STATUS_PAID);
                break;

            case 'rejected_history':
                $query->where('status', ExpenseRequest::STATUS_REJECTED);
                break;

            case 'my_requests':
            default:
                $query->whereNotIn('status', [ExpenseRequest::STATUS_PAID, ExpenseRequest::STATUS_REJECTED]);
                break;
        }

        // Search & Filter
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('request_number', 'like', "%{$search}%")
                  ->orWhere('category', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%")
                  ->orWhereHas('user', function ($u) use ($search) {
                      $u->where('name', 'like', "%{$search}%");
                  });
            });
        }

        if ($request->filled('category')) {
            $query->where('category', $request->category);
        }

        /** @var \Illuminate\Pagination\LengthAwarePaginator $requests */
        $requests = $query->latest()->paginate(15)->withQueryString();

        // Fetch Bank / Asset Accounts directly from Chart of Accounts
        $coaBankAccounts = ChartOfAccount::with('manager')
            ->where('is_active', true)
            ->where('type', 'asset')
            ->orderBy('code')
            ->get();

        if ($coaBankAccounts->isEmpty()) {
            $coaBankAccounts = ChartOfAccount::with('manager')
                ->where('is_active', true)
                ->orderBy('code')
                ->get();
        }

        $bankAccounts = BankAccount::with(['assignedStaff', 'coa.manager'])->where('is_active', true)->get();
        $chartOfAccounts = ChartOfAccount::where('is_active', true)->where('type', 'expense')->get();

        // Fetch finance staff for assignment
        $financeStaff = User::whereHas('roles', function ($q) {
            $q->whereIn('name', ['finance', 'finance_head', 'finance_manager', 'accountant', 'cashier']);
        })->get();

        if ($financeStaff->isEmpty()) {
            $financeStaff = User::where('is_active', true)->get();
        }

        return view('expense-requests.index', compact(
            'requests',
            'counters',
            'tab',
            'bankAccounts',
            'chartOfAccounts',
            'financeStaff',
            'coaBankAccounts'
        ));
    }

    /**
     * View single expense request with strict policy check.
     */
    public function show(ExpenseRequest $expenseRequest)
    {
        $this->authorize('view', $expenseRequest);

        $expenseRequest->load([
            'user',
            'employee',
            'hrReviewer',
            'gmApprover',
            'financeHead',
            'financeStaff',
            'paidBy',
            'bankAccount',
            'chartOfAccount'
        ]);

        return redirect('/expense-requests?tab=my_requests');
    }

    /**
     * Preview or stream expense request attachment safely without 404 domain mismatch.
     */
    public function viewAttachment(ExpenseRequest $expenseRequest)
    {
        $attachment = $expenseRequest->attachment;
        if (empty($attachment)) {
            return response()->make('<div style="font-family:sans-serif;text-align:center;padding:50px;"><h3>No attachment file associated with this request.</h3><p><a href="javascript:window.close()">Close window</a></p></div>', 404, ['Content-Type' => 'text/html']);
        }

        // If Cloudinary / remote URL
        if (Str::startsWith($attachment, ['http://', 'https://', '//'])) {
            return redirect()->away($attachment);
        }

        // Clean local path
        $clean = ltrim($attachment, '/');
        $candidates = [
            public_path($clean),
            public_path('uploads/' . str_replace('uploads/', '', $clean)),
            public_path('storage/' . str_replace('storage/', '', $clean)),
            storage_path('app/public/' . str_replace(['storage/', 'public/'], '', $clean)),
            storage_path('app/' . $clean),
        ];

        foreach ($candidates as $candidate) {
            if (file_exists($candidate) && is_file($candidate)) {
                $ext = strtolower(pathinfo($candidate, PATHINFO_EXTENSION));
                $mime = match ($ext) {
                    'pdf'         => 'application/pdf',
                    'jpg', 'jpeg' => 'image/jpeg',
                    'png'         => 'image/png',
                    'webp'        => 'image/webp',
                    'gif'         => 'image/gif',
                    default       => mime_content_type($candidate) ?: 'application/octet-stream',
                };

                return response()->file($candidate, [
                    'Content-Type'        => $mime,
                    'Content-Disposition' => 'inline; filename="' . basename($candidate) . '"',
                ]);
            }
        }

        // Fallback to FileUploadService URL
        $fallbackUrl = \App\Services\FileUploadService::url($attachment);
        if ($fallbackUrl && Str::startsWith($fallbackUrl, ['http://', 'https://', '//'])) {
            return redirect()->away($fallbackUrl);
        }

        return response()->make('<div style="font-family:sans-serif;text-align:center;padding:50px;color:#333;"><h3>Attachment file not found on local disk</h3><p class="text-muted">' . e($attachment) . '</p><p><a href="javascript:window.close()">Close window</a></p></div>', 404, ['Content-Type' => 'text/html']);
    }

    /**
     * Store a new expense request ("Ask Money").
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'category' => 'required|string|in:Service,Transport,Office Material,Loading & Unloading,Loading / Unloading,Loading Unloading,Contract Work,Maintenance,Other',
            'other_reason' => 'required_if:category,Other|nullable|string|max:255',
            'service_type' => 'nullable|string|max:100',
            'maintenance_request_id' => 'nullable|exists:maintenance_requests,id',
            'amount' => 'required|numeric|min:0.01',
            'gross_amount' => 'nullable|numeric|min:0',
            'vat_type' => 'nullable|string|in:none,inclusive,exclusive,vat_b',
            'vat_rate' => 'nullable|numeric|min:0',
            'vat_amount' => 'nullable|numeric|min:0',
            'has_withholding' => 'nullable|boolean',
            'withholding_rate' => 'nullable|numeric|min:0',
            'withholding_amount' => 'nullable|numeric|min:0',
            'net_amount' => 'nullable|numeric|min:0',
            'description' => 'required|string|max:2000',
            'attachment' => 'nullable|file|mimes:jpeg,png,jpg,pdf,webp|max:10240',
            'withholding_receipt' => 'nullable|file|mimes:jpeg,png,jpg,pdf,webp|max:10240',
            'withholding_receipt_number' => 'nullable|string|max:100',
        ]);

        $user = auth()->user();
        $employee = $user->employee ?? null;

        // Auto-generate request number REQ-YYYYMMDD-XXXX
        $requestNumber = 'REQ-' . date('Ymd') . '-' . strtoupper(Str::random(4));

        // Handle attachment upload via CloudinaryService
        $attachmentUrl = null;
        if ($request->hasFile('attachment')) {
            try {
                $cloudinary = app(CloudinaryService::class);
                $attachmentUrl = $cloudinary->upload($request->file('attachment'), 'expense_receipts');
            } catch (\Throwable $e) {
                Log::error('Expense request attachment upload error: ' . $e->getMessage());
            }
        }

        // Handle withholding receipt upload
        $withholdingReceiptUrl = null;
        if ($request->hasFile('withholding_receipt')) {
            try {
                $cloudinary = app(CloudinaryService::class);
                $withholdingReceiptUrl = $cloudinary->upload($request->file('withholding_receipt'), 'expense_withholding_receipts');
            } catch (\Throwable $e) {
                Log::error('Withholding receipt upload error: ' . $e->getMessage());
                $withholdingReceiptUrl = $request->file('withholding_receipt')->store('uploads/withholding_receipts', 'public');
            }
        }


        // Standardize category name
        $category = $validated['category'];
        if (in_array($category, ['Loading / Unloading', 'Loading Unloading'])) {
            $category = 'Loading & Unloading';
        }

        // Tax & Amount Calculations
        $rawAmount = (float)$validated['amount'];
        $gross = isset($validated['gross_amount']) && (float)$validated['gross_amount'] > 0 ? (float)$validated['gross_amount'] : $rawAmount;
        $vatType = $request->input('vat_type', 'none');
        $vatRate = (float)$request->input('vat_rate', 15.00);
        $hasWithholding = $request->boolean('has_withholding');
        $withholdingRate = 3.00; // Strict 3.00% Withholding Tax per Ethiopian Tax Regulation


        $vatAmount = 0.0;
        $baseAmount = $gross;
        $withholdingAmount = 0.0;
        $netAmount = $gross;

        if ($vatType === 'exclusive') {
            $vatAmount = round($gross * ($vatRate / 100), 2);
            $baseAmount = $gross;
            $totalGrossWithVat = $gross + $vatAmount;
            if ($hasWithholding) {
                $withholdingAmount = round($baseAmount * ($withholdingRate / 100), 2);
            }
            $netAmount = $totalGrossWithVat - $withholdingAmount;
        } elseif ($vatType === 'inclusive' || $vatType === 'vat_b') {
            $baseAmount = round($gross / (1 + ($vatRate / 100)), 2);
            $vatAmount = round($gross - $baseAmount, 2);
            if ($hasWithholding) {
                $withholdingAmount = round($baseAmount * ($withholdingRate / 100), 2);
            }
            $netAmount = $gross - $withholdingAmount;
        } else {
            $baseAmount = $gross;
            $vatAmount = 0.0;
            if ($hasWithholding) {
                $withholdingAmount = round($baseAmount * ($withholdingRate / 100), 2);
            }
            $netAmount = $gross - $withholdingAmount;
        }

        $finalAmount = $netAmount > 0 ? $netAmount : $rawAmount;

        $expenseRequest = ExpenseRequest::create([
            'request_number' => $requestNumber,
            'user_id' => $user->id,
            'employee_id' => $employee ? $employee->id : null,
            'maintenance_request_id' => $request->input('maintenance_request_id'),
            'category' => $category,
            'service_type' => $validated['service_type'] ?? null,
            'other_reason' => $category === 'Other' ? $validated['other_reason'] : ($category === 'Maintenance' ? ($validated['other_reason'] ?? 'Equipment / Asset Maintenance') : null),
            'amount' => $finalAmount,
            'gross_amount' => $gross,
            'vat_type' => $vatType,
            'vat_rate' => $vatRate,
            'vat_amount' => $vatAmount,
            'has_withholding' => $hasWithholding,
            'withholding_rate' => $withholdingRate,
            'withholding_amount' => $withholdingAmount,
            'withholding_receipt' => $withholdingReceiptUrl,
            'withholding_receipt_number' => $request->input('withholding_receipt_number'),
            'net_amount' => $netAmount,
            'description' => $validated['description'],
            'attachment' => $attachmentUrl,
            'status' => ExpenseRequest::STATUS_PENDING_HR,
        ]);


        return redirect('/expense-requests?tab=my_requests')
            ->with('success', "Expense Request #{$expenseRequest->request_number} for ETB " . number_format($expenseRequest->amount, 2) . " submitted successfully!");
    }


    /**
     * Step 1 — HR Review (Approve or Reject).
     * If <= 5000 -> Approved - Assigned to Finance
     * If > 5000  -> Pending (GM Review)
     */
    public function hrReview(Request $request, ExpenseRequest $expenseRequest)
    {
        $this->authorize('hrReview', $expenseRequest);

        $validated = $request->validate([
            'action' => 'required|in:approve,reject',
            'rejection_reason' => 'nullable|string|max:1000',
        ]);

        if ($expenseRequest->status !== ExpenseRequest::STATUS_PENDING_HR) {
            return back()->with('error', 'Request is not in HR review state.');
        }

        $user = auth()->user();

        if ($validated['action'] === 'reject') {
            $reason = !empty($validated['rejection_reason']) ? $validated['rejection_reason'] : 'Rejected by HR reviewer';
            $expenseRequest->update([
                'status' => ExpenseRequest::STATUS_REJECTED,
                'hr_reviewer_id' => $user->id,
                'hr_reviewed_at' => now(),
                'rejection_reason' => $reason,
            ]);

            return back()->with('success', "Request #{$expenseRequest->request_number} rejected.");
        }

        // Approve action: <= 5000 ETB goes straight to Finance, > 5000 goes to GM
        if ($expenseRequest->amount <= 5000) {
            $expenseRequest->update([
                'status' => ExpenseRequest::STATUS_APPROVED_ASSIGNED,
                'hr_reviewer_id' => $user->id,
                'hr_reviewed_at' => now(),
            ]);

            $message = "Request #{$expenseRequest->request_number} (ETB " . number_format($expenseRequest->amount, 2) . ") approved by HR and routed to Finance Head.";
        } else {
            $expenseRequest->update([
                'status' => ExpenseRequest::STATUS_PENDING_GM,
                'hr_reviewer_id' => $user->id,
                'hr_reviewed_at' => now(),
            ]);

            $message = "Request #{$expenseRequest->request_number} exceeds 5,000 ETB. Forwarded to General Manager (GM) for approval.";
        }

        return back()->with('success', $message);
    }

    /**
     * Step 2 — GM Review (only for requests > 5000 ETB).
     */
    public function gmReview(Request $request, ExpenseRequest $expenseRequest)
    {
        $this->authorize('gmReview', $expenseRequest);

        $validated = $request->validate([
            'action' => 'required|in:approve,reject',
            'rejection_reason' => 'nullable|string|max:1000',
        ]);

        if ($expenseRequest->status !== ExpenseRequest::STATUS_PENDING_GM) {
            return back()->with('error', 'Request is not in GM review state.');
        }

        $user = auth()->user();

        if ($validated['action'] === 'reject') {
            $reason = !empty($validated['rejection_reason']) ? $validated['rejection_reason'] : 'Rejected by General Manager (GM)';
            $expenseRequest->update([
                'status' => ExpenseRequest::STATUS_REJECTED,
                'gm_reviewer_id' => $user->id,
                'gm_approver_id' => $user->id,
                'gm_reviewed_at' => now(),
                'gm_approved_at' => now(),
                'rejection_reason' => $reason,
            ]);

            if (str_starts_with($expenseRequest->request_number, 'EXP-PCR-')) {
                $pcrNo = substr($expenseRequest->request_number, 8);
                $pcr = \App\Models\PettyCashReplenishment::where('request_no', $pcrNo)->first();
                if ($pcr) {
                    $pcr->update([
                        'status' => \App\Models\PettyCashReplenishment::STATUS_REJECTED,
                        'rejected_at' => now(),
                        'rejection_reason' => 'GM Rejected: ' . $reason,
                    ]);
                }
            }

            return back()->with('success', "Request #{$expenseRequest->request_number} rejected by GM.");
        }

        $expenseRequest->update([
            'status' => ExpenseRequest::STATUS_APPROVED_ASSIGNED,
            'gm_reviewer_id' => $user->id,
            'gm_approver_id' => $user->id,
            'gm_reviewed_at' => now(),
            'gm_approved_at' => now(),
        ]);

        if (str_starts_with($expenseRequest->request_number, 'EXP-PCR-')) {
            $pcrNo = substr($expenseRequest->request_number, 8);
            $pcr = \App\Models\PettyCashReplenishment::where('request_no', $pcrNo)->first();
            if ($pcr) {
                $pcr->update([
                    'status' => \App\Models\PettyCashReplenishment::STATUS_PENDING,
                ]);
            }
        }

        return back()->with('success', "Request #{$expenseRequest->request_number} approved by GM and sent to Finance Head for disbursement!");
    }

    /**
     * Step 3 — Finance Head (Selects bank account and assigns Finance Staff).
     */
    public function financeAssign(Request $request, ExpenseRequest $expenseRequest)
    {
        $this->authorize('financeAssign', $expenseRequest);

        $validated = $request->validate([
            'coa_id' => 'required|exists:chart_of_accounts,id',
            'assigned_finance_staff_id' => 'nullable|exists:users,id',
            'bank_account_id' => 'nullable|exists:bank_accounts,id',
        ]);

        if (!in_array($expenseRequest->status, [ExpenseRequest::STATUS_APPROVED_ASSIGNED, ExpenseRequest::STATUS_ASSIGNED])) {
            return back()->with('error', 'Request is not ready for Finance assignment.');
        }

        $coa = ChartOfAccount::with('manager')->findOrFail($validated['coa_id']);
        $bankAccount = BankAccount::where('coa_id', $coa->id)->first();

        // Priority: form value → COA manager → bank account assigned staff → current user
        $assignedStaffId = $validated['assigned_finance_staff_id']
            ?? $coa->assigned_to
            ?? ($coa->manager ? $coa->manager->id : null)
            ?? ($bankAccount ? $bankAccount->assigned_to : null)
            ?? auth()->id();

        $expenseRequest->update([
            'status' => ExpenseRequest::STATUS_ASSIGNED,
            'coa_id' => $coa->id,
            'chart_of_account_id' => $coa->id,
            'bank_account_id' => $bankAccount ? $bankAccount->id : ($validated['bank_account_id'] ?? null),
            'finance_head_id' => auth()->id(),
            'assigned_finance_staff_id' => $assignedStaffId,
            'finance_staff_id' => $assignedStaffId,
            'finance_assigned_at' => now(),
        ]);

        $assignedStaff = User::find($assignedStaffId);
        $staffName = $assignedStaff ? $assignedStaff->name : 'Finance Staff';

        return back()->with('success', "Request #{$expenseRequest->request_number} assigned to finance staff member ({$staffName})!");
    }

    /**
     * Step 4 — Finance Staff (Process Payment & Atomically Deduct from COA/Bank).
     */
    public function markPaid(Request $request, ExpenseRequest $expenseRequest)
    {
        $this->authorize('markPaid', $expenseRequest);

        if ($expenseRequest->status === ExpenseRequest::STATUS_PAID) {
            return back()->with('error', 'Request has already been paid.');
        }

        $validated = $request->validate([
            'payment_reference'  => 'nullable|string|max:100',
            'payment_notes'      => 'nullable|string|max:1000',
            'paid_amount'        => 'nullable|numeric|min:0.01',
            'gross_amount'       => 'nullable|numeric|min:0',
            'vat_type'           => 'nullable|string|in:none,inclusive,exclusive,vat_b',
            'vat_rate'           => 'nullable|numeric|min:0',
            'vat_amount'         => 'nullable|numeric|min:0',
            'has_withholding'    => 'nullable|boolean',
            'withholding_rate'   => 'nullable|numeric|min:0',
            'withholding_amount' => 'nullable|numeric|min:0',
            'net_amount'         => 'nullable|numeric|min:0',
            'category'           => 'nullable|string',
            'service_type'       => 'nullable|string|max:100',
            'withholding_receipt' => 'nullable|file|mimes:jpeg,png,jpg,pdf,webp|max:10240',
            'withholding_receipt_number' => 'nullable|string|max:100',
        ]);

        DB::beginTransaction();
        try {
            $user = auth()->user();
            $paymentRef = $validated['payment_reference'] ?? ('PAY-' . strtoupper(Str::random(6)));

            // Handle Withholding Tax Receipt upload if attached
            $withholdingReceiptUrl = $expenseRequest->withholding_receipt;
            if ($request->hasFile('withholding_receipt')) {
                try {
                    $cloudinary = app(CloudinaryService::class);
                    $withholdingReceiptUrl = $cloudinary->upload($request->file('withholding_receipt'), 'expense_withholding_receipts');
                } catch (\Throwable $e) {
                    Log::error('Withholding receipt upload error: ' . $e->getMessage());
                    $withholdingReceiptUrl = $request->file('withholding_receipt')->store('uploads/withholding_receipts', 'public');
                }
            }


            // Calculate or fetch updated tax components if provided in modal
            $gross = isset($validated['gross_amount']) && (float)$validated['gross_amount'] > 0
                ? (float)$validated['gross_amount']
                : ((float)($expenseRequest->gross_amount ?? $expenseRequest->amount));

            $vatType = $validated['vat_type'] ?? ($expenseRequest->vat_type ?? 'none');
            $vatRate = isset($validated['vat_rate']) ? (float)$validated['vat_rate'] : (float)($expenseRequest->vat_rate ?? 15.00);
            $hasWithholding = isset($validated['has_withholding']) ? $request->boolean('has_withholding') : (bool)($expenseRequest->has_withholding ?? false);
            $withholdingRate = 3.00; // Strict 3.00% Withholding Tax per Ethiopian Tax Regulation


            $vatAmount = 0.0;
            $baseAmount = $gross;
            $withholdingAmount = 0.0;
            $netAmount = $gross;

            if ($vatType === 'exclusive') {
                $vatAmount = round($gross * ($vatRate / 100), 2);
                $baseAmount = $gross;
                $totalGrossWithVat = $gross + $vatAmount;
                if ($hasWithholding) {
                    $withholdingAmount = round($baseAmount * ($withholdingRate / 100), 2);
                }
                $netAmount = $totalGrossWithVat - $withholdingAmount;
            } elseif ($vatType === 'inclusive' || $vatType === 'vat_b') {
                $baseAmount = round($gross / (1 + ($vatRate / 100)), 2);
                $vatAmount = round($gross - $baseAmount, 2);
                if ($hasWithholding) {
                    $withholdingAmount = round($baseAmount * ($withholdingRate / 100), 2);
                }
                $netAmount = $gross - $withholdingAmount;
            } else {
                $baseAmount = $gross;
                $vatAmount = 0.0;
                if ($hasWithholding) {
                    $withholdingAmount = round($baseAmount * ($withholdingRate / 100), 2);
                }
                $netAmount = $gross - $withholdingAmount;
            }

            // Actual disbursed amount to deduct from funding account
            $disbursedAmount = isset($validated['net_amount']) && (float)$validated['net_amount'] > 0
                ? (float)$validated['net_amount']
                : (isset($validated['paid_amount']) && (float)$validated['paid_amount'] > 0 ? (float)$validated['paid_amount'] : $netAmount);

            if ($disbursedAmount <= 0) {
                $disbursedAmount = (float)$expenseRequest->amount;
            }

            // 1. Update Expense Request Status & Tax Breakdown
            $updateData = [
                'status'             => ExpenseRequest::STATUS_PAID,
                'paid_by'            => $user->id,
                'paid_at'            => now(),
                'finance_staff_id'   => $user->id,
                'payment_reference'  => $paymentRef,
                'payment_notes'      => $validated['payment_notes'] ?? null,
                'gross_amount'       => $gross,
                'vat_type'           => $vatType,
                'vat_rate'           => $vatRate,
                'vat_amount'         => $vatAmount,
                'has_withholding'    => $hasWithholding,
                'withholding_rate'   => $withholdingRate,
                'withholding_amount' => $withholdingAmount,
                'withholding_receipt' => $withholdingReceiptUrl,
                'withholding_receipt_number' => $request->input('withholding_receipt_number', $expenseRequest->withholding_receipt_number),
                'net_amount'         => $disbursedAmount,
            ];


            if (!empty($validated['category'])) {
                $updateData['category'] = $validated['category'];
            }
            if (!empty($validated['service_type'])) {
                $updateData['service_type'] = $validated['service_type'];
            }

            $expenseRequest->update($updateData);

            $isPettyCash = str_starts_with($expenseRequest->request_number, 'EXP-PCR-')
                || ($expenseRequest->category === ExpenseRequest::CATEGORY_OTHER && $expenseRequest->other_reason === 'Petty Cash Replenishment')
                || str_contains($expenseRequest->description, 'Petty Cash Replenishment')
                || str_contains($expenseRequest->category, 'Petty Cash Replenishment');

            // 2. Deduct disbursed amount from selected funding Bank Account
            if ($expenseRequest->bank_account_id) {
                $bankAccount = BankAccount::find($expenseRequest->bank_account_id);
                if ($bankAccount) {
                    $bankAccount->decrement('current_balance', $disbursedAmount);
                    $newBalance = $bankAccount->fresh()->current_balance;

                    // 3. Create Bank Transaction Ledger Entry
                    $taxNote = ($vatAmount > 0 ? " [VAT: {$vatAmount}]" : '') . ($withholdingAmount > 0 ? " [WHT: -{$withholdingAmount}]" : '');
                    BankTransaction::create([
                        'bank_account_id' => $bankAccount->id,
                        'transaction_date' => now()->toDateString(),
                        'type' => 'withdrawal',
                        'amount' => $disbursedAmount,
                        'balance_after' => $newBalance,
                        'reference_no' => $paymentRef,
                        'reference_type' => 'ExpenseRequest',
                        'reference_id' => $expenseRequest->id,
                        'description' => "Expense Request #{$expenseRequest->request_number}: {$expenseRequest->category}{$taxNote} - " . Str::limit($expenseRequest->description, 100),
                        'is_reconciled' => true,
                    ]);
                }
            }

            // 4. Deduct disbursed amount from Source Chart of Account if assigned (only if not petty cash target)
            $coaId = $expenseRequest->chart_of_account_id ?? $expenseRequest->coa_id;
            if ($coaId && !$expenseRequest->bank_account_id && !$isPettyCash) {
                $coa = ChartOfAccount::find($coaId);
                if ($coa) {
                    $coa->decrement('current_balance', $disbursedAmount);
                }
            }

            // 5. If this is a Petty Cash Replenishment, TOP UP the Petty Cash Account & fulfill Replenishment
            if ($isPettyCash) {
                $this->handlePettyCashReplenishmentFulfillment($expenseRequest, $disbursedAmount, $paymentRef, $user);
            }

            DB::commit();

            return back()->with('success', "Payment of ETB " . number_format($disbursedAmount, 2) . " processed successfully for Request #{$expenseRequest->request_number}" . ($isPettyCash ? " and credited to the Petty Cash Account!" : "!"));
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Expense payment error: ' . $e->getMessage());
            return back()->with('error', 'Failed to process payment: ' . $e->getMessage());
        }
    }

    /**
     * Helper: Top up Petty Cash Account and fulfill linked Replenishment cycle
     */
    private function handlePettyCashReplenishmentFulfillment(ExpenseRequest $expenseRequest, float $disbursedAmount, string $paymentRef, User $user): void
    {
        // 1. Resolve linked Petty Cash Replenishment
        $replenishment = $expenseRequest->linked_replenishment;
        if (!$replenishment) {
            $reqNo = (string)$expenseRequest->request_number;
            $cleanNo = preg_replace('/^EXP-PCR-/', '', $reqNo);
            $cleanNo = preg_replace('/^EXP-/', '', $cleanNo);

            $replenishment = PettyCashReplenishment::where('request_no', $cleanNo)
                ->orWhere('request_no', 'PCR-' . $cleanNo)
                ->orWhere('request_no', $reqNo)
                ->first();
        }

        if (!$replenishment && preg_match('/#?(PCR-[A-Za-z0-9\-]+)/', $expenseRequest->description, $matches)) {
            $replenishment = PettyCashReplenishment::where('request_no', $matches[1])->first();
        }

        // 2. Resolve target Petty Cash Chart of Account
        $pettyAccount = null;
        if ($replenishment && $replenishment->chart_of_account_id) {
            $pettyAccount = ChartOfAccount::find($replenishment->chart_of_account_id);
        }

        if (!$pettyAccount && preg_match('/\[(\d+)\]/', $expenseRequest->description, $codeMatches)) {
            $pettyAccount = ChartOfAccount::where('code', $codeMatches[1])->first();
        }

        if (!$pettyAccount && $expenseRequest->chart_of_account_id) {
            $candidate = ChartOfAccount::find($expenseRequest->chart_of_account_id);
            if ($candidate && (str_contains(strtolower($candidate->name), 'petty') || $candidate->code == '1010')) {
                $pettyAccount = $candidate;
            }
        }

        if (!$pettyAccount) {
            $pettyAccount = ChartOfAccount::where('code', '1010')
                ->orWhere('name', 'like', '%Petty Cash%')
                ->first();
        }

        if ($pettyAccount) {
            // Add funds to Petty Cash Account
            $isPettyAssetOrExpense = in_array($pettyAccount->type, ['asset', 'expense']);
            if ($isPettyAssetOrExpense) {
                $pettyAccount->increment('current_balance', $disbursedAmount);
            } else {
                $pettyAccount->decrement('current_balance', $disbursedAmount);
            }

            // Resolve funding source COA
            $sourceCoa = null;
            if ($expenseRequest->bank_account_id) {
                $bank = BankAccount::find($expenseRequest->bank_account_id);
                $sourceCoa = $bank?->chartOfAccount ?? ($bank?->coa_id ? ChartOfAccount::find($bank->coa_id) : null);
            }
            if (!$sourceCoa && $expenseRequest->coa_id && $expenseRequest->coa_id !== $pettyAccount->id) {
                $sourceCoa = ChartOfAccount::find($expenseRequest->coa_id);
            }

            // Record Journal Entry
            $jeCount = JournalEntry::count() + 1;
            $jeNo = 'JE-' . date('Ymd') . '-' . str_pad($jeCount, 4, '0', STR_PAD_LEFT);

            $journalEntry = JournalEntry::create([
                'entry_no'       => $jeNo,
                'entry_date'     => now(),
                'reference_type' => 'petty_cash_replenishment',
                'reference_id'   => $replenishment ? $replenishment->id : $expenseRequest->id,
                'description'    => "Petty Cash Replenishment: Disbursed ETB " . number_format($disbursedAmount, 2) . " into [{$pettyAccount->code}] {$pettyAccount->name} via Expense Request #{$expenseRequest->request_number}",
                'status'         => 'posted',
                'created_by'     => $user->id,
                'approved_by'    => $user->id,
                'posted_at'      => now(),
            ]);

            // Debit Petty Cash (Asset increase)
            JournalEntryLine::create([
                'journal_entry_id' => $journalEntry->id,
                'account_id'       => $pettyAccount->id,
                'description'      => "Replenishment Top-up from " . ($sourceCoa ? "[{$sourceCoa->code}] {$sourceCoa->name}" : "Bank Disbursement"),
                'side'             => $isPettyAssetOrExpense ? 'debit' : 'credit',
                'amount'           => $disbursedAmount,
            ]);

            // Credit Source Account if different from petty cash account
            if ($sourceCoa && $sourceCoa->id !== $pettyAccount->id) {
                $isSourceAssetOrExpense = in_array($sourceCoa->type, ['asset', 'expense']);
                JournalEntryLine::create([
                    'journal_entry_id' => $journalEntry->id,
                    'account_id'       => $sourceCoa->id,
                    'description'      => "Disbursement for Petty Cash Replenishment #{$expenseRequest->request_number}",
                    'side'             => $isSourceAssetOrExpense ? 'credit' : 'debit',
                    'amount'           => $disbursedAmount,
                ]);
            }

            // Update Replenishment record to Fulfilled
            if ($replenishment) {
                $replenishment->update([
                    'status'                => PettyCashReplenishment::STATUS_FULFILLED,
                    'finance_head_id'       => $user->id,
                    'fulfilled_amount'      => $disbursedAmount,
                    'source_coa_id'         => $sourceCoa?->id,
                    'journal_entry_id'      => $journalEntry->id,
                    'fulfillment_reference' => $paymentRef,
                    'fulfilled_at'          => now(),
                ]);

                ActivityLog::log(
                    'approved',
                    "Petty Cash Replenishment #{$replenishment->request_no} fulfilled via Expense Request Payment: Disbursed ETB " . number_format($disbursedAmount, 2) . " into [{$pettyAccount->code}] {$pettyAccount->name}. Journal Entry: {$jeNo}",
                    'Finance & Petty Cash Audit',
                    $replenishment
                );
            }
        }
    }


    /**
     * Dedicated Payment History View (strictly scoped per Section 3).
     */
    public function history(Request $request)
    {
        $user = auth()->user();
        if (!$user) {
            return redirect()->route('login');
        }

        $query = ExpenseRequest::with([
            'user',
            'employee',
            'hrReviewer',
            'gmApprover',
            'gmReviewer',
            'financeHead',
            'financeStaff',
            'paidBy',
            'bankAccount',
            'chartOfAccount'
        ]);

        // STRICT DATABASE-LEVEL PAID HISTORY SCOPING
        $query->paidHistoryForUser($user);

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('request_number', 'like', "%{$search}%")
                  ->orWhere('payment_reference', 'like', "%{$search}%")
                  ->orWhere('category', 'like', "%{$search}%")
                  ->orWhereHas('user', function ($u) use ($search) {
                      $u->where('name', 'like', "%{$search}%");
                  });
            });
        }

        if ($request->filled('category')) {
            $query->where('category', $request->category);
        }

        /** @var \Illuminate\Pagination\LengthAwarePaginator $paidRequests */
        $paidRequests = $query->latest('paid_at')->paginate(20)->withQueryString();

        return view('expense-requests.history', compact('paidRequests'));
    }
}
