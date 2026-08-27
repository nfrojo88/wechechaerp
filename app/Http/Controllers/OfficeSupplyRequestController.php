<?php

namespace App\Http\Controllers;

use App\Models\OfficeMaterialRequest;
use App\Models\OfficeMaterialRequestItem;
use App\Models\Product;
use App\Models\User;
use App\Models\ChartOfAccount;
use App\Models\BankAccount;
use App\Models\BankTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Str;

class OfficeSupplyRequestController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Auto-ensure table exists on live/local database.
     */
    private function ensureTableExists(): void
    {
        try {
            if (!Schema::hasTable('office_material_requests')) {
                Schema::create('office_material_requests', function (Blueprint $table) {
                    $table->id();
                    $table->string('request_no')->unique();
                    $table->foreignId('requested_by')->constrained('users')->cascadeOnDelete();
                    $table->string('office_purpose')->nullable();
                    $table->text('justification')->nullable();
                    $table->date('required_date')->nullable();
                    $table->string('urgency')->default('normal');
                    $table->string('attachment')->nullable();
                    $table->string('status')->default('pending_hr');

                    // Step 2: HR money addition
                    $table->decimal('amount', 14, 2)->nullable();
                    $table->foreignId('hr_reviewer_id')->nullable()->constrained('users')->nullOnDelete();
                    $table->timestamp('hr_reviewed_at')->nullable();
                    $table->text('hr_notes')->nullable();

                    // Step 3: Finance Head assignment
                    $table->foreignId('finance_head_id')->nullable()->constrained('users')->nullOnDelete();
                    $table->foreignId('coa_id')->nullable()->constrained('chart_of_accounts')->nullOnDelete();
                    $table->foreignId('bank_account_id')->nullable()->constrained('bank_accounts')->nullOnDelete();
                    $table->foreignId('assigned_finance_staff_id')->nullable()->constrained('users')->nullOnDelete();
                    $table->timestamp('finance_assigned_at')->nullable();
                    $table->text('finance_head_notes')->nullable();

                    // Step 4: Payment
                    $table->foreignId('paid_by')->nullable()->constrained('users')->nullOnDelete();
                    $table->timestamp('paid_at')->nullable();
                    $table->string('payment_reference')->nullable();
                    $table->text('payment_notes')->nullable();

                    // Rejection
                    $table->foreignId('rejected_by')->nullable()->constrained('users')->nullOnDelete();
                    $table->timestamp('rejected_at')->nullable();
                    $table->text('rejection_reason')->nullable();

                    $table->timestamps();
                });
            }

            if (!Schema::hasTable('office_material_request_items')) {
                Schema::create('office_material_request_items', function (Blueprint $table) {
                    $table->id();
                    $table->foreignId('office_material_request_id')->constrained('office_material_requests')->cascadeOnDelete();
                    $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
                    $table->string('item_name');
                    $table->decimal('quantity', 12, 2)->default(1);
                    $table->string('unit')->default('pcs');
                    $table->text('specifications')->nullable();
                    $table->decimal('estimated_unit_price', 14, 2)->nullable();
                    $table->timestamps();
                });
            }
        } catch (\Throwable $e) {}
    }

    /**
     * Check if user is HR / Coordinator / Admin
     */
    protected function isHrOrCoordinator(): bool
    {
        $user = Auth::user();
        if (!$user) return false;
        $userRoles = $user->roles->pluck('name')->map(fn($r) => strtolower(str_replace([' ', '-'], '_', trim($r))))->toArray();
        $allowed = ['hr_manager', 'hr_officer', 'hr', 'coordinator', 'admin', 'global_admin', 'gm', 'general_manager'];
        return count(array_intersect($allowed, $userRoles)) > 0 || $user->can('hr.view');
    }

    /**
     * Check if user is Finance Head / Finance Staff / Admin
     */
    protected function isFinance(): bool
    {
        $user = Auth::user();
        if (!$user) return false;
        $userRoles = $user->roles->pluck('name')->map(fn($r) => strtolower(str_replace([' ', '-'], '_', trim($r))))->toArray();
        $allowed = ['finance_head', 'finance_manager', 'finance', 'accountant', 'cashier', 'admin', 'global_admin', 'gm', 'general_manager'];
        return count(array_intersect($allowed, $userRoles)) > 0;
    }

    // ─── 1. Index / List ──────────────────────────────────────────────────────
    public function index(Request $request)
    {
        $this->ensureTableExists();
        $user = Auth::user();

        $isHr = $this->isHrOrCoordinator();
        $isFinance = $this->isFinance();
        $isSecretary = $user->hasRole('secretary') && !$isHr && !$isFinance;

        $query = OfficeMaterialRequest::with([
            'requestedBy',
            'hrReviewer',
            'financeHead',
            'assignedStaff',
            'paidBy',
            'coa',
            'bankAccount',
            'items.product'
        ])->latest();

        // If user is regular secretary/requester only, scope to own requests
        if ($isSecretary) {
            $query->where('requested_by', $user->id);
        }

        // Tab filter
        $tab = $request->query('tab', 'all');
        if ($tab === 'pending_hr') {
            $query->where('status', OfficeMaterialRequest::STATUS_PENDING_HR);
        } elseif ($tab === 'finance_queue') {
            $query->whereIn('status', [OfficeMaterialRequest::STATUS_APPROVED_BY_HR, OfficeMaterialRequest::STATUS_ASSIGNED_TO_FINANCE]);
        } elseif ($tab === 'paid') {
            $query->where('status', OfficeMaterialRequest::STATUS_PAID);
        } elseif ($tab === 'rejected') {
            $query->where('status', OfficeMaterialRequest::STATUS_REJECTED);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('request_no', 'like', "%{$search}%")
                  ->orWhere('office_purpose', 'like', "%{$search}%")
                  ->orWhere('justification', 'like', "%{$search}%")
                  ->orWhereHas('requestedBy', function ($u) use ($search) {
                      $u->where('name', 'like', "%{$search}%");
                  });
            });
        }

        $requests = $query->paginate(15)->withQueryString();

        // Calculate summary counters
        $countQuery = OfficeMaterialRequest::query();
        if ($isSecretary) {
            $countQuery->where('requested_by', $user->id);
        }

        $stats = [
            'all'           => (clone $countQuery)->count(),
            'pending_hr'    => (clone $countQuery)->where('status', OfficeMaterialRequest::STATUS_PENDING_HR)->count(),
            'finance_queue' => (clone $countQuery)->whereIn('status', [OfficeMaterialRequest::STATUS_APPROVED_BY_HR, OfficeMaterialRequest::STATUS_ASSIGNED_TO_FINANCE])->count(),
            'paid'          => (clone $countQuery)->where('status', OfficeMaterialRequest::STATUS_PAID)->count(),
            'rejected'      => (clone $countQuery)->where('status', OfficeMaterialRequest::STATUS_REJECTED)->count(),
        ];

        // COA & Bank accounts for modal
        $coaAccounts = ChartOfAccount::where('is_active', true)->where('type', 'expense')->orderBy('code')->get();
        if ($coaAccounts->isEmpty()) {
            $coaAccounts = ChartOfAccount::where('is_active', true)->orderBy('code')->get();
        }

        $bankAccounts = BankAccount::where('is_active', true)->get();

        $financeStaff = User::whereHas('roles', function ($q) {
            $q->whereIn('name', ['finance', 'finance_head', 'finance_manager', 'accountant', 'cashier']);
        })->get();
        if ($financeStaff->isEmpty()) {
            $financeStaff = User::where('is_active', true)->get();
        }

        return view('procurement.office-requests.index', compact(
            'requests',
            'stats',
            'tab',
            'isHr',
            'isFinance',
            'isSecretary',
            'coaAccounts',
            'bankAccounts',
            'financeStaff'
        ));
    }

    // ─── 2. Create Requisition ───────────────────────────────────────────────
    public function create()
    {
        $this->ensureTableExists();

        $products = Product::where('is_active', true)->orderBy('name')->get();
        if ($products->isEmpty()) {
            $products = Product::orderBy('name')->get();
        }

        $purposes = [
            'Stationery & Paper Supplies'      => 'Stationery & Paper Supplies (ደብተር፣ እስክሪብቶ፣ ወረቀት)',
            'Printing & Toners'                => 'Printing & Toners (ቶነር፣ ካርትሪጅ፣ ፕሪንተር እቃዎች)',
            'Pantry, Tea & Cleaning'           => 'Pantry, Tea & Cleaning (ሻይ፣ ስኳር፣ ሳሙና፣ የጽዳት እቃዎች)',
            'IT & Computer Accessories'        => 'IT & Computer Accessories (ፍላሽ፣ ኬብል፣ አይጥ፣ ኪቦርድ)',
            'Office Furniture & Fixtures'      => 'Office Furniture & Fixtures (ጠረጴዛ፣ ወንበር፣ መደርደሪያ)',
            'Office Equipment & Utilities'     => 'Office Equipment & Utilities (የቢሮ መገልገያዎች)',
            'General Office Materials'         => 'General Office Materials (አጠቃላይ የቢሮ እቃዎች)',
            'Other Office Supplies'            => 'Other Office Supplies (ሌላ የቢሮ ፍላጎት)',
        ];

        return view('procurement.office-requests.create', compact('products', 'purposes'));
    }

    // ─── 3. Store Requisition ────────────────────────────────────────────────
    public function store(Request $request)
    {
        $this->ensureTableExists();

        $validated = $request->validate([
            'office_purpose'  => 'required|string|max:255',
            'justification'   => 'nullable|string|max:2000',
            'required_date'   => 'nullable|date',
            'urgency'         => 'required|in:normal,urgent,emergency',
            'items'           => 'required|array|min:1',
            'items.*.name'    => 'required|string|max:255',
            'items.*.qty'     => 'required|numeric|min:0.01',
            'items.*.unit'    => 'required|string|max:50',
            'items.*.specs'   => 'nullable|string|max:500',
            'items.*.product_id' => 'nullable|exists:products,id',
            'attachment'      => 'nullable|file|mimes:pdf,jpg,jpeg,png,doc,docx|max:10240',
        ]);

        $attachmentPath = null;
        if ($request->hasFile('attachment')) {
            $attachmentPath = $request->file('attachment')->store('office-requests', 'public');
        }

        DB::beginTransaction();
        try {
            $today = date('Ymd');
            $count = OfficeMaterialRequest::whereDate('created_at', today())->count() + 1;
            $reqNo = 'OFF-' . $today . '-' . str_pad($count, 4, '0', STR_PAD_LEFT);

            $officeRequest = OfficeMaterialRequest::create([
                'request_no'     => $reqNo,
                'requested_by'   => Auth::id(),
                'office_purpose' => $validated['office_purpose'],
                'justification'  => $validated['justification'] ?? null,
                'required_date'  => $validated['required_date'] ?? null,
                'urgency'        => $validated['urgency'],
                'attachment'     => $attachmentPath,
                'status'         => OfficeMaterialRequest::STATUS_PENDING_HR,
            ]);

            foreach ($validated['items'] as $item) {
                if (empty($item['name']) || empty($item['qty'])) continue;

                OfficeMaterialRequestItem::create([
                    'office_material_request_id' => $officeRequest->id,
                    'product_id'                 => $item['product_id'] ?? null,
                    'item_name'                  => $item['name'],
                    'quantity'                   => $item['qty'],
                    'unit'                       => $item['unit'] ?? 'pcs',
                    'specifications'             => $item['specs'] ?? null,
                ]);
            }

            DB::commit();

            return redirect()->route('office-requests.show', $officeRequest->id)
                ->with('success', "Office Supply Request #{$reqNo} submitted successfully and sent to HR / Coordinator for review & budget assignment.");
        } catch (\Throwable $e) {
            DB::rollBack();
            return back()->withInput()->with('error', 'Failed to submit office request: ' . $e->getMessage());
        }
    }

    // ─── 4. Show Details & Timeline ──────────────────────────────────────────
    public function show($id)
    {
        $this->ensureTableExists();

        $officeRequest = OfficeMaterialRequest::with([
            'requestedBy',
            'hrReviewer',
            'financeHead',
            'assignedStaff',
            'paidBy',
            'rejectedBy',
            'coa',
            'bankAccount',
            'items.product'
        ])->findOrFail($id);

        $isHr = $this->isHrOrCoordinator();
        $isFinance = $this->isFinance();

        // Accounts for Finance Head modal
        $coaAccounts = ChartOfAccount::where('is_active', true)->where('type', 'expense')->orderBy('code')->get();
        if ($coaAccounts->isEmpty()) {
            $coaAccounts = ChartOfAccount::where('is_active', true)->orderBy('code')->get();
        }
        $bankAccounts = BankAccount::where('is_active', true)->get();
        $financeStaff = User::whereHas('roles', function ($q) {
            $q->whereIn('name', ['finance', 'finance_head', 'finance_manager', 'accountant', 'cashier']);
        })->get();
        if ($financeStaff->isEmpty()) {
            $financeStaff = User::where('is_active', true)->get();
        }

        return view('procurement.office-requests.show', compact(
            'officeRequest',
            'isHr',
            'isFinance',
            'coaAccounts',
            'bankAccounts',
            'financeStaff'
        ));
    }

    // ─── Step 2: HR / Coordinator Review & Money Addition ────────────────────
    public function hrApprove(Request $request, $id)
    {
        $this->ensureTableExists();

        if (!$this->isHrOrCoordinator()) {
            abort(403, 'Unauthorized. Only HR / Coordinator can approve and assign budget.');
        }

        $officeRequest = OfficeMaterialRequest::findOrFail($id);

        if ($officeRequest->status !== OfficeMaterialRequest::STATUS_PENDING_HR) {
            return back()->with('error', 'Request is not currently in Pending HR status.');
        }

        $validated = $request->validate([
            'amount'             => 'nullable|numeric|min:0',
            'hr_notes'           => 'nullable|string|max:1000',
            'items'              => 'nullable|array',
            'items.*.unit_price' => 'nullable|numeric|min:0',
        ]);

        DB::beginTransaction();
        try {
            $totalFromItems = 0;
            if (!empty($validated['items'])) {
                foreach ($validated['items'] as $itemId => $itemData) {
                    $item = OfficeMaterialRequestItem::where('office_material_request_id', $officeRequest->id)->find($itemId);
                    if ($item) {
                        $unitPrice = (float)($itemData['unit_price'] ?? 0);
                        $item->update([
                            'estimated_unit_price' => $unitPrice,
                        ]);
                        $totalFromItems += ((float)$item->quantity * $unitPrice);
                    }
                }
            }

            $finalAmount = !empty($validated['amount']) && (float)$validated['amount'] > 0
                ? (float)$validated['amount']
                : ($totalFromItems > 0 ? $totalFromItems : 0);

            if ($finalAmount <= 0) {
                return back()->withInput()->with('error', 'Please enter a valid price/amount for the requested materials.');
            }

            $officeRequest->update([
                'amount'         => $finalAmount,
                'hr_reviewer_id' => Auth::id(),
                'hr_reviewed_at' => now(),
                'hr_notes'       => $validated['hr_notes'] ?? null,
                'status'         => OfficeMaterialRequest::STATUS_APPROVED_BY_HR,
            ]);

            DB::commit();

            return back()->with('success', "Office Request #{$officeRequest->request_no} approved with itemized budget ETB " . number_format($finalAmount, 2) . " and sent to Finance Head for assignment.");
        } catch (\Throwable $e) {
            DB::rollBack();
            return back()->with('error', 'Approval failed: ' . $e->getMessage());
        }
    }


    // ─── Step 3: Finance Head Assigns COA/Bank & Staff ───────────────────────
    public function financeAssign(Request $request, $id)
    {
        $this->ensureTableExists();

        if (!$this->isFinance()) {
            abort(403, 'Unauthorized. Only Finance Head or Admin can assign funding account.');
        }

        $officeRequest = OfficeMaterialRequest::findOrFail($id);

        if (!in_array($officeRequest->status, [OfficeMaterialRequest::STATUS_APPROVED_BY_HR, OfficeMaterialRequest::STATUS_ASSIGNED_TO_FINANCE])) {
            return back()->with('error', 'Request is not ready for Finance assignment.');
        }

        $validated = $request->validate([
            'coa_id'                    => 'required|exists:chart_of_accounts,id',
            'bank_account_id'           => 'nullable|exists:bank_accounts,id',
            'assigned_finance_staff_id' => 'nullable|exists:users,id',
            'finance_head_notes'        => 'nullable|string|max:1000',
        ]);

        $coa = ChartOfAccount::find($validated['coa_id']);
        $bank = !empty($validated['bank_account_id']) ? BankAccount::find($validated['bank_account_id']) : ($coa ? BankAccount::where('coa_id', $coa->id)->first() : null);

        $assignedStaffId = $validated['assigned_finance_staff_id']
            ?? ($bank ? $bank->assigned_to : null)
            ?? ($coa ? $coa->assigned_to : null)
            ?? Auth::id();

        $officeRequest->update([
            'coa_id'                    => $coa ? $coa->id : null,
            'bank_account_id'           => $bank ? $bank->id : null,
            'finance_head_id'           => Auth::id(),
            'assigned_finance_staff_id' => $assignedStaffId,
            'finance_assigned_at'       => now(),
            'finance_head_notes'        => $validated['finance_head_notes'] ?? null,
            'status'                    => OfficeMaterialRequest::STATUS_ASSIGNED_TO_FINANCE,
        ]);

        $staffName = User::find($assignedStaffId)?->name ?? 'Finance Staff';

        return back()->with('success', "Office Request #{$officeRequest->request_no} funding assigned ({$coa?->name}) and forwarded to {$staffName} for payment.");
    }

    // ─── Step 4: Finance Staff / Cashier Disburses Payment ───────────────────
    public function markPaid(Request $request, $id)
    {
        $this->ensureTableExists();

        if (!$this->isFinance()) {
            abort(403, 'Unauthorized. Only Finance Staff can execute payment.');
        }

        $officeRequest = OfficeMaterialRequest::findOrFail($id);

        if ($officeRequest->status === OfficeMaterialRequest::STATUS_PAID) {
            return back()->with('error', 'Request is already marked as paid.');
        }

        $validated = $request->validate([
            'payment_reference' => 'nullable|string|max:100',
            'payment_notes'     => 'nullable|string|max:1000',
            'paid_amount'       => 'nullable|numeric|min:0.01',
        ]);

        DB::beginTransaction();
        try {
            $user = Auth::user();
            $paymentRef = $validated['payment_reference'] ?? ('PAY-OFF-' . strtoupper(Str::random(6)));
            $finalAmount = $validated['paid_amount'] ?? $officeRequest->amount;

            $officeRequest->update([
                'amount'            => $finalAmount,
                'status'            => OfficeMaterialRequest::STATUS_PAID,
                'paid_by'           => $user->id,
                'paid_at'           => now(),
                'payment_reference' => $paymentRef,
                'payment_notes'     => $validated['payment_notes'] ?? null,
            ]);

            // Deduct from bank account if set
            if ($officeRequest->bank_account_id && $finalAmount > 0) {
                $bankAccount = BankAccount::find($officeRequest->bank_account_id);
                if ($bankAccount) {
                    $bankAccount->decrement('current_balance', $finalAmount);
                    $newBalance = $bankAccount->fresh()->current_balance;

                    BankTransaction::create([
                        'bank_account_id' => $bankAccount->id,
                        'transaction_date'=> now()->toDateString(),
                        'type'            => 'withdrawal',
                        'amount'          => $finalAmount,
                        'balance_after'   => $newBalance,
                        'reference_no'    => $paymentRef,
                        'reference_type'  => 'OfficeMaterialRequest',
                        'reference_id'    => $officeRequest->id,
                        'description'     => "Office Material Request #{$officeRequest->request_no}: {$officeRequest->office_purpose}",
                        'is_reconciled'   => true,
                    ]);
                }
            }

            DB::commit();

            return back()->with('success', "Office Request #{$officeRequest->request_no} payment of ETB " . number_format($finalAmount, 2) . " disbursed successfully. Request is now COMPLETED.");
        } catch (\Throwable $e) {
            DB::rollBack();
            return back()->with('error', 'Payment execution failed: ' . $e->getMessage());
        }
    }

    // ─── Reject Request ──────────────────────────────────────────────────────
    public function reject(Request $request, $id)
    {
        $this->ensureTableExists();

        $officeRequest = OfficeMaterialRequest::findOrFail($id);

        $validated = $request->validate([
            'rejection_reason' => 'required|string|max:1000',
        ]);

        $officeRequest->update([
            'status'           => OfficeMaterialRequest::STATUS_REJECTED,
            'rejected_by'      => Auth::id(),
            'rejected_at'      => now(),
            'rejection_reason' => $validated['rejection_reason'],
        ]);

        return back()->with('success', "Office Request #{$officeRequest->request_no} has been rejected.");
    }
}
