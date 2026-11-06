<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\BankAccount;
use App\Models\ChartOfAccount;
use App\Models\User;
use App\Models\UtilityExpense;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

class UtilityExpenseController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public static function ensureTableExists(): void
    {
        try {
            if (!Schema::hasTable('utility_expenses')) {
                Schema::create('utility_expenses', function (Blueprint $table) {
                    $table->id();
                    $table->string('expense_number', 50)->unique();
                    $table->string('utility_type', 50)->default('other');
                    $table->string('title', 255);
                    $table->text('description')->nullable();
                    $table->string('billing_period', 100)->nullable();
                    $table->string('reference_no', 100)->nullable();
                    $table->date('due_date')->nullable();
                    $table->decimal('amount', 15, 2);
                    $table->decimal('gross_amount', 15, 2)->nullable();
                    $table->string('vat_type', 30)->default('none');
                    $table->decimal('vat_rate', 5, 2)->default(15.00);
                    $table->decimal('vat_amount', 15, 2)->default(0.00);
                    $table->boolean('has_withholding')->default(false);
                    $table->decimal('withholding_rate', 5, 2)->default(3.00);
                    $table->decimal('withholding_amount', 15, 2)->default(0.00);
                    $table->string('withholding_receipt', 500)->nullable();
                    $table->string('withholding_receipt_number', 100)->nullable();
                    $table->decimal('net_amount', 15, 2)->nullable();
                    $table->unsignedBigInteger('coa_id');
                    $table->unsignedBigInteger('bank_account_id')->nullable();
                    $table->unsignedBigInteger('created_by');
                    $table->unsignedBigInteger('assigned_to');
                    $table->string('status', 30)->default('pending_payment');
                    $table->string('priority', 20)->default('normal');
                    $table->string('bill_attachment', 255)->nullable();

                    $table->string('payment_method', 50)->nullable();
                    $table->string('payment_reference', 100)->nullable();
                    $table->date('payment_date')->nullable();
                    $table->decimal('paid_amount', 15, 2)->nullable();
                    $table->unsignedBigInteger('paid_by')->nullable();
                    $table->timestamp('paid_at')->nullable();
                    $table->string('payment_receipt', 255)->nullable();
                    $table->text('payment_notes')->nullable();

                    $table->timestamps();
                    $table->softDeletes();

                    $table->index(['status', 'created_at']);
                    $table->index('assigned_to');
                    $table->index('coa_id');
                    $table->index('utility_type');
                });
            } else {
                Schema::table('utility_expenses', function (Blueprint $table) {
                    if (!Schema::hasColumn('utility_expenses', 'gross_amount')) {
                        $table->decimal('gross_amount', 15, 2)->nullable()->after('amount');
                    }
                    if (!Schema::hasColumn('utility_expenses', 'vat_type')) {
                        $table->string('vat_type', 30)->default('none')->after('gross_amount');
                    }
                    if (!Schema::hasColumn('utility_expenses', 'vat_rate')) {
                        $table->decimal('vat_rate', 5, 2)->default(15.00)->after('vat_type');
                    }
                    if (!Schema::hasColumn('utility_expenses', 'vat_amount')) {
                        $table->decimal('vat_amount', 15, 2)->default(0.00)->after('vat_rate');
                    }
                    if (!Schema::hasColumn('utility_expenses', 'has_withholding')) {
                        $table->boolean('has_withholding')->default(false)->after('vat_amount');
                    }
                    if (!Schema::hasColumn('utility_expenses', 'withholding_rate')) {
                        $table->decimal('withholding_rate', 5, 2)->default(3.00)->after('has_withholding');
                    }
                    if (!Schema::hasColumn('utility_expenses', 'withholding_amount')) {
                        $table->decimal('withholding_amount', 15, 2)->default(0.00)->after('withholding_rate');
                    }
                    if (!Schema::hasColumn('utility_expenses', 'withholding_receipt')) {
                        $table->string('withholding_receipt', 500)->nullable()->after('withholding_amount');
                    }
                    if (!Schema::hasColumn('utility_expenses', 'withholding_receipt_number')) {
                        $table->string('withholding_receipt_number', 100)->nullable()->after('withholding_receipt');
                    }
                    if (!Schema::hasColumn('utility_expenses', 'net_amount')) {
                        $table->decimal('net_amount', 15, 2)->nullable()->after('withholding_receipt_number');
                    }
                });
            }
        } catch (\Throwable $e) {
            Log::error('UtilityExpense ensureTableExists error: ' . $e->getMessage());
        }
    }

    public function index(Request $request)
    {
        self::ensureTableExists();

        $user = Auth::user();
        $userRoleNames = strtolower(implode(' ', $user->getRoleNames()->toArray()));
        $isFinanceHead = str_contains($userRoleNames, 'finance_head') || str_contains($userRoleNames, 'finance_manager') || $user->hasAnyRole(['Finance head', 'finance_head', 'admin', 'global_admin']);
        $isAdmin = $user->hasAnyRole(['admin', 'global_admin']) || (method_exists($user, 'isGlobalAdmin') && $user->isGlobalAdmin());
        $isFinanceStaff = str_contains($userRoleNames, 'finance') || str_contains($userRoleNames, 'cashier') || str_contains($userRoleNames, 'accountant');

        $tab = $request->query('tab', 'all');
        if (!in_array($tab, ['all', 'pending', 'paid'])) {
            $tab = 'all';
        }

        $query = UtilityExpense::with([
            'coa',
            'bankAccount',
            'creator',
            'assignedPerson.employee',
            'payer',
        ]);

        // Non-finance / non-admin users only see expenses assigned to them
        if (!$isFinanceHead && !$isAdmin && !$isFinanceStaff) {
            $query->where('assigned_to', $user->id);
        }

        // Tab filter
        if ($tab === 'pending') {
            $query->where('status', UtilityExpense::STATUS_PENDING);
        } elseif ($tab === 'paid') {
            $query->where('status', UtilityExpense::STATUS_PAID);
        }

        // Search
        if ($request->filled('search')) {
            $search = trim($request->search);
            $query->where(function ($q) use ($search) {
                $q->where('expense_number', 'like', "%{$search}%")
                  ->orWhere('title', 'like', "%{$search}%")
                  ->orWhere('reference_no', 'like', "%{$search}%")
                  ->orWhere('billing_period', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%")
                  ->orWhereHas('assignedPerson', function ($u) use ($search) {
                      $u->where('name', 'like', "%{$search}%");
                  })
                  ->orWhereHas('coa', function ($c) use ($search) {
                      $c->where('code', 'like', "%{$search}%")->orWhere('name', 'like', "%{$search}%");
                  });
            });
        }

        // Utility Type filter
        if ($request->filled('utility_type')) {
            $query->where('utility_type', $request->utility_type);
        }

        // COA Account filter
        if ($request->filled('coa_id')) {
            $query->where('coa_id', $request->coa_id);
        }

        // Assigned person filter
        if ($request->filled('assigned_to')) {
            $query->where('assigned_to', $request->assigned_to);
        }

        // Date range
        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $utilityExpenses = $query->latest()->paginate(15)->withQueryString();

        // Summary metrics
        $metricsBase = UtilityExpense::query();
        if (!$isFinanceHead && !$isAdmin && !$isFinanceStaff) {
            $metricsBase->where('assigned_to', $user->id);
        }

        $counters = [
            'total_count'    => (clone $metricsBase)->count(),
            'total_amount'   => (float) (clone $metricsBase)->sum('amount'),
            'pending_count'  => (clone $metricsBase)->where('status', UtilityExpense::STATUS_PENDING)->count(),
            'pending_amount' => (float) (clone $metricsBase)->where('status', UtilityExpense::STATUS_PENDING)->sum('amount'),
            'paid_count'     => (clone $metricsBase)->where('status', UtilityExpense::STATUS_PAID)->count(),
            'paid_amount'    => (float) (clone $metricsBase)->where('status', UtilityExpense::STATUS_PAID)->sum(DB::raw('COALESCE(paid_amount, amount)')),
            'wifi_amount'    => (float) (clone $metricsBase)->where('utility_type', UtilityExpense::TYPE_WIFI)->sum('amount'),
            'rental_amount'  => (float) (clone $metricsBase)->where('utility_type', UtilityExpense::TYPE_RENTAL)->sum('amount'),
        ];

        // Active Chart of Accounts available for selection
        $coaAccounts = ChartOfAccount::with(['manager', 'bankAccounts.assignedStaff'])
            ->where('is_active', true)
            ->whereIn('type', ['asset', 'expense'])
            ->orderBy('code')
            ->get();

        // Eligible users to be assigned payment duty (Finance staff, cashiers, accountants, and general staff)
        $assignableUsers = User::where('is_active', true)
            ->with('employee')
            ->orderBy('name')
            ->get();

        $utilityTypes = UtilityExpense::UTILITY_TYPES;

        return view('finance.utility-expenses.index', compact(
            'utilityExpenses',
            'counters',
            'coaAccounts',
            'assignableUsers',
            'utilityTypes',
            'tab',
            'isFinanceHead',
            'isAdmin',
            'isFinanceStaff'
        ));
    }

    public function store(Request $request)
    {
        self::ensureTableExists();

        $validated = $request->validate([
            'utility_type'    => 'required|string|max:50',
            'title'           => 'required|string|max:255',
            'amount'          => 'required|numeric|min:0.01',
            'coa_id'          => 'required|exists:chart_of_accounts,id',
            'assigned_to'     => 'nullable|exists:users,id',
            'billing_period'  => 'nullable|string|max:100',
            'reference_no'    => 'nullable|string|max:100',
            'due_date'        => 'nullable|date',
            'description'     => 'nullable|string',
            'priority'        => 'nullable|string|in:low,normal,high,urgent',
            'bill_attachment' => 'nullable|file|mimes:pdf,jpg,jpeg,png,webp|max:10240',
        ]);

        $coa = ChartOfAccount::with(['manager', 'bankAccounts.assignedStaff'])->findOrFail($validated['coa_id']);
        $bankAccount = BankAccount::where('coa_id', $coa->id)->first();

        // Determine assigned person: prioritize explicit input, then COA manager, then bank assigned staff, then current user
        $assignedToId = !empty($validated['assigned_to'])
            ? (int) $validated['assigned_to']
            : ($coa->assigned_to
                ?? optional($coa->manager)->id
                ?? optional($bankAccount)->assigned_to
                ?? Auth::id());

        $attachmentPath = null;
        if ($request->hasFile('bill_attachment')) {
            $file = $request->file('bill_attachment');
            $attachmentPath = $file->store('utility_bills', 'public');
        }

        $expenseNumber = UtilityExpense::generateNextNumber();

        $utilityExpense = UtilityExpense::create([
            'expense_number'  => $expenseNumber,
            'utility_type'    => $validated['utility_type'],
            'title'           => $validated['title'],
            'amount'          => $validated['amount'],
            'coa_id'          => $coa->id,
            'bank_account_id' => $bankAccount ? $bankAccount->id : null,
            'created_by'      => Auth::id(),
            'assigned_to'     => $assignedToId,
            'billing_period'  => $validated['billing_period'] ?? null,
            'reference_no'    => $validated['reference_no'] ?? null,
            'due_date'        => $validated['due_date'] ?? null,
            'description'     => $validated['description'] ?? null,
            'priority'        => $validated['priority'] ?? 'normal',
            'status'          => UtilityExpense::STATUS_PENDING,
            'bill_attachment' => $attachmentPath,
        ]);

        $assignedUser = User::find($assignedToId);
        $assignedName = $assignedUser ? $assignedUser->name : 'staff';

        return redirect()->route('utility-expenses.index')
            ->with('success', "Utility Expense #{$expenseNumber} ({$utilityExpense->title}) for ETB " . number_format($validated['amount'], 2) . " has been created and assigned to {$assignedName} to pay.");
    }

    public function pay(Request $request, UtilityExpense $utilityExpense)
    {
        $user = Auth::user();
        $userRoleNames = strtolower(implode(' ', $user->getRoleNames()->toArray()));
        $isFinanceHead = str_contains($userRoleNames, 'finance_head') || str_contains($userRoleNames, 'finance_manager') || $user->hasAnyRole(['Finance head', 'finance_head', 'admin', 'global_admin']);
        $isAdmin = $user->hasAnyRole(['admin', 'global_admin']);
        $isAssigned = (int) $utilityExpense->assigned_to === (int) $user->id;
        $isCoaManager = ($utilityExpense->coa && (int) $utilityExpense->coa->assigned_to === (int) $user->id)
            || ($utilityExpense->bankAccount && (int) $utilityExpense->bankAccount->assigned_to === (int) $user->id);

        if (!$isFinanceHead && !$isAdmin && !$isAssigned && !$isCoaManager) {
            return back()->with('error', 'You are not authorized to disburse payment for this utility expense.');
        }

        if ($utilityExpense->status === UtilityExpense::STATUS_PAID) {
            return back()->with('error', 'This utility expense is already marked as paid.');
        }

        self::ensureTableExists();

        $validated = $request->validate([
            'payment_method'             => 'required|string|max:50',
            'payment_reference'          => 'nullable|string|max:100',
            'payment_date'               => 'required|date',
            'paid_amount'                => 'required|numeric|min:0.01',
            'payment_notes'              => 'nullable|string',
            'payment_receipt'            => 'nullable|file|mimes:pdf,jpg,jpeg,png,webp|max:10240',
            'gross_amount'               => 'nullable|numeric|min:0.01',
            'vat_type'                   => 'nullable|string|in:none,exclusive,vat_b,inclusive',
            'vat_rate'                   => 'nullable|numeric|min:0|max:100',
            'vat_amount'                 => 'nullable|numeric|min:0',
            'has_withholding'            => 'nullable',
            'withholding_rate'           => 'nullable|numeric|min:0|max:100',
            'withholding_amount'         => 'nullable|numeric|min:0',
            'withholding_receipt'        => 'nullable|file|mimes:pdf,jpg,jpeg,png,webp|max:10240',
            'withholding_receipt_number' => 'nullable|string|max:100',
            'net_amount'                 => 'nullable|numeric|min:0',
        ]);

        $receiptPath = $utilityExpense->payment_receipt;
        if ($request->hasFile('payment_receipt')) {
            if (class_exists(\App\Services\FileUploadService::class)) {
                $receiptPath = \App\Services\FileUploadService::upload($request->file('payment_receipt'), 'utility_receipts');
            } else {
                $receiptPath = $request->file('payment_receipt')->store('utility_receipts', 'public');
            }
        }

        $withholdingReceiptPath = $utilityExpense->withholding_receipt;
        if ($request->hasFile('withholding_receipt')) {
            if (class_exists(\App\Services\FileUploadService::class)) {
                $withholdingReceiptPath = \App\Services\FileUploadService::upload($request->file('withholding_receipt'), 'withholding_receipts');
            } else {
                $withholdingReceiptPath = $request->file('withholding_receipt')->store('withholding_receipts', 'public');
            }
        }

        // Tax & Withholding calculations
        $hasWithholding = $request->boolean('has_withholding') || in_array($request->input('has_withholding'), [1, '1', true, 'true', 'on'], true);
        $vatType = $validated['vat_type'] ?? ($utilityExpense->vat_type ?? 'none');
        $vatRate = isset($validated['vat_rate']) ? (float)$validated['vat_rate'] : (float)($utilityExpense->vat_rate ?? 15.00);
        $grossAmount = isset($validated['gross_amount']) ? (float)$validated['gross_amount'] : (float)($utilityExpense->gross_amount ?: $utilityExpense->amount);

        // Calculate VAT
        $vatAmount = isset($validated['vat_amount']) ? (float)$validated['vat_amount'] : 0.00;
        if ($vatType === 'exclusive' && $vatAmount <= 0) {
            $vatAmount = round($grossAmount * ($vatRate / 100), 2);
        } elseif (in_array(strtolower($vatType), ['inclusive', 'vat_b']) && $vatAmount <= 0) {
            $base = round($grossAmount / (1 + ($vatRate / 100)), 2);
            $vatAmount = round($grossAmount - $base, 2);
        }

        // Calculate Withholding Tax (applied on base amount before VAT according to Ethiopian tax rules)
        $withholdingRate = isset($validated['withholding_rate']) ? (float)$validated['withholding_rate'] : (float)($utilityExpense->withholding_rate ?? 3.00);
        $withholdingAmount = 0.00;
        if ($hasWithholding) {
            if (isset($validated['withholding_amount']) && (float)$validated['withholding_amount'] > 0) {
                $withholdingAmount = (float)$validated['withholding_amount'];
            } else {
                $taxableBase = in_array(strtolower($vatType), ['inclusive', 'vat_b'])
                    ? round($grossAmount / (1 + ($vatRate / 100)), 2)
                    : $grossAmount;
                $withholdingAmount = round($taxableBase * ($withholdingRate / 100), 2);
            }
        }

        $disbursedAmount = (float) $validated['paid_amount'];
        $netAmount = isset($validated['net_amount']) ? (float)$validated['net_amount'] : $disbursedAmount;

        DB::transaction(function () use (
            $utilityExpense,
            $validated,
            $receiptPath,
            $withholdingReceiptPath,
            $user,
            $disbursedAmount,
            $grossAmount,
            $vatType,
            $vatRate,
            $vatAmount,
            $hasWithholding,
            $withholdingRate,
            $withholdingAmount,
            $netAmount
        ) {
            $utilityExpense->update([
                'status'                     => UtilityExpense::STATUS_PAID,
                'gross_amount'               => $grossAmount,
                'vat_type'                   => $vatType,
                'vat_rate'                   => $vatRate,
                'vat_amount'                 => $vatAmount,
                'has_withholding'            => $hasWithholding,
                'withholding_rate'           => $withholdingRate,
                'withholding_amount'         => $withholdingAmount,
                'withholding_receipt'        => $withholdingReceiptPath,
                'withholding_receipt_number' => $validated['withholding_receipt_number'] ?? $utilityExpense->withholding_receipt_number,
                'net_amount'                 => $netAmount,
                'paid_amount'                => $disbursedAmount,
                'paid_by'                    => $user->id,
                'paid_at'                    => now(),
                'payment_method'             => $validated['payment_method'],
                'payment_reference'          => $validated['payment_reference'] ?? null,
                'payment_date'               => $validated['payment_date'],
                'payment_notes'              => $validated['payment_notes'] ?? null,
                'payment_receipt'            => $receiptPath,
            ]);

            // Deduct balance from the funding COA
            if ($utilityExpense->coa_id) {
                $coa = ChartOfAccount::find($utilityExpense->coa_id);
                if ($coa) {
                    $coa->decrement('current_balance', $disbursedAmount);
                }
            }

            // Deduct balance from linked Bank Account and log BankTransaction if exists
            $bankAccount = null;
            if ($utilityExpense->bank_account_id) {
                $bankAccount = BankAccount::find($utilityExpense->bank_account_id);
            } elseif ($utilityExpense->coa_id) {
                $bankAccount = BankAccount::where('coa_id', $utilityExpense->coa_id)->first();
            }

            if ($bankAccount) {
                $bankAccount->decrement('current_balance', $disbursedAmount);
                $newBal = $bankAccount->fresh()->current_balance;

                if (class_exists(\App\Models\BankTransaction::class) && Schema::hasTable('bank_transactions')) {
                    try {
                        \App\Models\BankTransaction::create([
                            'bank_account_id' => $bankAccount->id,
                            'transaction_date'=> $validated['payment_date'] ?? now()->toDateString(),
                            'type'            => 'withdrawal',
                            'amount'          => $disbursedAmount,
                            'balance_after'   => $newBal,
                            'reference_no'    => $validated['payment_reference'] ?? $utilityExpense->expense_number,
                            'reference_type'  => 'UtilityExpense',
                            'reference_id'    => $utilityExpense->id,
                            'description'     => "Utility Expense Payment #{$utilityExpense->expense_number}: {$utilityExpense->title}",
                            'is_reconciled'   => true,
                        ]);
                    } catch (\Throwable $e) {
                        Log::error('BankTransaction creation for UtilityExpense failed: ' . $e->getMessage());
                    }
                }
            }
        });

        return back()->with('success', "Payment of ETB " . number_format($validated['paid_amount'], 2) . " for {$utilityExpense->expense_number} ({$utilityExpense->title}) was recorded successfully and deducted from the account balance.");
    }

    public function destroy(UtilityExpense $utilityExpense)
    {
        $user = Auth::user();
        $userRoleNames = strtolower(implode(' ', $user->getRoleNames()->toArray()));
        $isFinanceHead = str_contains($userRoleNames, 'finance_head') || $user->hasAnyRole(['Finance head', 'finance_head', 'admin', 'global_admin']);

        if (!$isFinanceHead) {
            return back()->with('error', 'Only the Finance Head or Admin can delete or cancel a utility expense.');
        }

        if ($utilityExpense->status === UtilityExpense::STATUS_PAID) {
            return back()->with('error', 'Cannot delete a utility expense that has already been paid.');
        }

        $num = $utilityExpense->expense_number;
        $utilityExpense->delete();

        return redirect()->route('utility-expenses.index')
            ->with('success', "Utility expense #{$num} was deleted successfully.");
    }
}
