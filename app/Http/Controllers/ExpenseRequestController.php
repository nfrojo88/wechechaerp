<?php

namespace App\Http\Controllers;

use App\Models\BankAccount;
use App\Models\BankTransaction;
use App\Models\ChartOfAccount;
use App\Models\ExpenseRequest;
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
                    if (!\Illuminate\Support\Facades\Schema::hasColumn('expense_requests', 'payment_notes')) {
                        $table->text('payment_notes')->nullable();
                    }
                    if (!\Illuminate\Support\Facades\Schema::hasColumn('expense_requests', 'maintenance_request_id')) {
                        $table->unsignedBigInteger('maintenance_request_id')->nullable();
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
            'category' => 'required|string|in:Transport,Office Material,Loading & Unloading,Loading / Unloading,Loading Unloading,Contract Work,Maintenance,Other',
            'other_reason' => 'required_if:category,Other|nullable|string|max:255',
            'maintenance_request_id' => 'nullable|exists:maintenance_requests,id',
            'amount' => 'required|numeric|min:1',
            'description' => 'required|string|max:2000',
            'attachment' => 'nullable|file|mimes:jpeg,png,jpg,pdf,webp|max:10240',
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

        $expenseRequest = ExpenseRequest::create([
            'request_number' => $requestNumber,
            'user_id' => $user->id,
            'employee_id' => $employee ? $employee->id : null,
            'maintenance_request_id' => $request->input('maintenance_request_id'),
            'category' => $validated['category'],
            'other_reason' => $validated['category'] === 'Other' ? $validated['other_reason'] : ($validated['category'] === 'Maintenance' ? ($validated['other_reason'] ?? 'Equipment / Asset Maintenance') : null),
            'amount' => $validated['amount'],
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

            return back()->with('success', "Request #{$expenseRequest->request_number} rejected by GM.");
        }

        $expenseRequest->update([
            'status' => ExpenseRequest::STATUS_APPROVED_ASSIGNED,
            'gm_reviewer_id' => $user->id,
            'gm_approver_id' => $user->id,
            'gm_reviewed_at' => now(),
            'gm_approved_at' => now(),
        ]);

        return back()->with('success', "Request #{$expenseRequest->request_number} approved by GM and sent to Finance Head!");
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
            'payment_reference' => 'nullable|string|max:100',
            'payment_notes' => 'nullable|string|max:1000',
        ]);

        DB::beginTransaction();
        try {
            $user = auth()->user();

            $paymentRef = $validated['payment_reference'] ?? ('PAY-' . strtoupper(Str::random(6)));

            // 1. Update Expense Request Status
            $expenseRequest->update([
                'status' => ExpenseRequest::STATUS_PAID,
                'paid_by' => $user->id,
                'paid_at' => now(),
                'finance_staff_id' => $user->id,
                'payment_reference' => $paymentRef,
                'payment_notes' => $validated['payment_notes'] ?? null,
            ]);

            // 2. Deduct amount from selected Bank Account
            if ($expenseRequest->bank_account_id) {
                $bankAccount = BankAccount::find($expenseRequest->bank_account_id);
                if ($bankAccount) {
                    $bankAccount->decrement('current_balance', $expenseRequest->amount);
                    $newBalance = $bankAccount->fresh()->current_balance;

                    // 3. Create Bank Transaction Ledger Entry
                    BankTransaction::create([
                        'bank_account_id' => $bankAccount->id,
                        'transaction_date' => now()->toDateString(),
                        'type' => 'withdrawal',
                        'amount' => $expenseRequest->amount,
                        'balance_after' => $newBalance,
                        'reference_no' => $paymentRef,
                        'reference_type' => 'ExpenseRequest',
                        'reference_id' => $expenseRequest->id,
                        'description' => "Expense Request #{$expenseRequest->request_number}: {$expenseRequest->category} - " . Str::limit($expenseRequest->description, 100),
                        'is_reconciled' => true,
                    ]);
                }
            }

            // 4. Deduct amount from Chart of Account if assigned
            $coaId = $expenseRequest->chart_of_account_id ?? $expenseRequest->coa_id;
            if ($coaId) {
                $coa = ChartOfAccount::find($coaId);
                if ($coa) {
                    $coa->decrement('current_balance', $expenseRequest->amount);
                }
            }

            DB::commit();

            return back()->with('success', "Payment of ETB " . number_format($expenseRequest->amount, 2) . " processed successfully for Request #{$expenseRequest->request_number}!");
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Expense payment error: ' . $e->getMessage());
            return back()->with('error', 'Failed to process payment: ' . $e->getMessage());
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
