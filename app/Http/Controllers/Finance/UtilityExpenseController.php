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

        $validated = $request->validate([
            'payment_method'    => 'required|string|max:50',
            'payment_reference' => 'nullable|string|max:100',
            'payment_date'      => 'required|date',
            'paid_amount'       => 'required|numeric|min:0.01',
            'payment_notes'     => 'nullable|string',
            'payment_receipt'   => 'nullable|file|mimes:pdf,jpg,jpeg,png,webp|max:10240',
        ]);

        $receiptPath = null;
        if ($request->hasFile('payment_receipt')) {
            $file = $request->file('payment_receipt');
            $receiptPath = $file->store('utility_receipts', 'public');
        }

        DB::transaction(function () use ($utilityExpense, $validated, $receiptPath, $user) {
            $disbursedAmount = (float) $validated['paid_amount'];

            $utilityExpense->update([
                'status'            => UtilityExpense::STATUS_PAID,
                'paid_amount'       => $disbursedAmount,
                'paid_by'           => $user->id,
                'paid_at'           => now(),
                'payment_method'    => $validated['payment_method'],
                'payment_reference' => $validated['payment_reference'] ?? null,
                'payment_date'      => $validated['payment_date'],
                'payment_notes'     => $validated['payment_notes'] ?? null,
                'payment_receipt'   => $receiptPath ?? $utilityExpense->payment_receipt,
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
