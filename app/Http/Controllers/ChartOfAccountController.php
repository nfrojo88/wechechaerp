<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\BankAccount;
use App\Models\ChartOfAccount;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ChartOfAccountController extends Controller
{
    /**
     * Check if the authenticated user has permission to manage Chart of Accounts & Balances.
     */
    private function canManageCoaBalances(): bool
    {
        $user = Auth::user();
        if (!$user) {
            return false;
        }

        if (method_exists($user, 'hasAnyRole')) {
            return $user->hasAnyRole(['admin', 'global_admin', 'finance_head', 'Finance head', 'finance_manager', 'finance']);
        }

        return true;
    }

    public function index()
    {
        // Auto-purge account 1003 and any associated data if still present
        if (ChartOfAccount::where('code', '1003')->exists()) {
            ChartOfAccount::deleteAccountAndRelated('1003', true);
        }

        $accounts = ChartOfAccount::with(['parent', 'manager', 'bankAccounts'])->orderBy('code')->paginate(50);

        $stats = [
            'total_accounts'     => ChartOfAccount::where('is_active', true)->count(),
            'total_assets'       => (float) ChartOfAccount::where('is_active', true)->where('type', 'asset')->sum('current_balance'),
            'total_liabilities'  => (float) ChartOfAccount::where('is_active', true)->where('type', 'liability')->sum('current_balance'),
            'total_equity'       => (float) ChartOfAccount::where('is_active', true)->where('type', 'equity')->sum('current_balance'),
            'total_revenue'      => (float) ChartOfAccount::where('is_active', true)->where('type', 'revenue')->sum('current_balance'),
            'total_expenses'     => (float) ChartOfAccount::where('is_active', true)->where('type', 'expense')->sum('current_balance'),
        ];

        return view('finance.coa.index', compact('accounts', 'stats'));
    }

    /**
     * Display the One-Time Bulk Account Ledger & Initial Balance Preparation view.
     */
    public function initialBalances(Request $request)
    {
        if (!$this->canManageCoaBalances()) {
            abort(403, 'Unauthorized. Only Finance Managers or Administrators can access the One-Time Balance & Ledger Setup.');
        }

        if (ChartOfAccount::where('code', '1003')->exists()) {
            ChartOfAccount::deleteAccountAndRelated('1003', true);
        }

        $accounts = ChartOfAccount::with(['parent', 'manager', 'bankAccounts'])
            ->where('is_active', true)
            ->orderBy('code')
            ->get();

        $equityAccounts = ChartOfAccount::where('type', 'equity')
            ->where('is_active', true)
            ->orderBy('code')
            ->get();

        // Default equity balancing account (3151 Beginning Balance Equity or first equity)
        $defaultEquity = $equityAccounts->firstWhere('code', '3151') 
            ?? $equityAccounts->firstWhere('code', '3152') 
            ?? $equityAccounts->first();

        $stats = [
            'total_accounts'      => $accounts->count(),
            'assets_balance'      => (float) $accounts->where('type', 'asset')->sum('current_balance'),
            'liabilities_balance' => (float) $accounts->where('type', 'liability')->sum('current_balance'),
            'equity_balance'      => (float) $accounts->where('type', 'equity')->sum('current_balance'),
            'revenue_balance'     => (float) $accounts->where('type', 'revenue')->sum('current_balance'),
            'expense_balance'     => (float) $accounts->where('type', 'expense')->sum('current_balance'),
        ];

        $stats['total_debit_balance']  = $stats['assets_balance'] + $stats['expense_balance'];
        $stats['total_credit_balance'] = $stats['liabilities_balance'] + $stats['equity_balance'] + $stats['revenue_balance'];
        $stats['net_imbalance']        = $stats['total_debit_balance'] - $stats['total_credit_balance'];

        return view('finance.coa.initial_balances', compact('accounts', 'equityAccounts', 'defaultEquity', 'stats'));
    }

    /**
     * Save bulk initial balances and create baseline General Ledger journal entry.
     */
    public function saveInitialBalances(Request $request)
    {
        if (!$this->canManageCoaBalances()) {
            abort(403, 'Unauthorized.');
        }

        $validated = $request->validate([
            'mode'                 => 'required|in:set,add',
            'effective_date'       => 'required|date',
            'reference_note'       => 'nullable|string|max:500',
            'post_to_ledger'       => 'nullable|boolean',
            'sync_bank_accounts'   => 'nullable|boolean',
            'auto_balance_equity'  => 'nullable|boolean',
            'balancing_equity_id'  => 'nullable|exists:chart_of_accounts,id',
            'balances'             => 'required|array',
            'balances.*'           => 'nullable|numeric',
        ]);

        $mode              = $validated['mode'];
        $effectiveDate     = $validated['effective_date'];
        $refNote           = $validated['reference_note'] ?? null;
        $postToLedger      = $request->boolean('post_to_ledger');
        $syncBankAccounts  = $request->boolean('sync_bank_accounts');
        $autoBalanceEquity = $request->boolean('auto_balance_equity');
        $balancingEquityId = $validated['balancing_equity_id'] ?? null;
        $balances          = $validated['balances'];
        $user              = Auth::user();

        $updatedCount = 0;
        $journalEntry = null;

        try {
            DB::transaction(function () use (
                $balances, $mode, $effectiveDate, $refNote, $postToLedger,
                $syncBankAccounts, $autoBalanceEquity, $balancingEquityId, $user,
                &$updatedCount, &$journalEntry
            ) {
                $accounts = ChartOfAccount::with('bankAccounts')->whereIn('id', array_keys($balances))->get()->keyBy('id');
                $ledgerLinesToCreate = [];
                $totalDebitLineAmount = 0.0;
                $totalCreditLineAmount = 0.0;

                // 1. Process each account balance
                foreach ($balances as $accountId => $inputAmount) {
                    $account = $accounts->get($accountId);
                    if (!$account) {
                        continue;
                    }

                    if ($inputAmount === null || $inputAmount === '') {
                        continue;
                    }

                    $amountVal = (float) $inputAmount;
                    $oldCurrent = (float) $account->current_balance;
                    $oldOpening = (float) $account->opening_balance;

                    if ($mode === 'set') {
                        // Direct set of new balance
                        $newBalance = $amountVal;
                        $newOpening = $amountVal;
                        $ledgerDelta = $newBalance;
                    } else {
                        // Incremental adjustment (+ or -)
                        $newBalance = $oldCurrent + $amountVal;
                        $newOpening = ($oldOpening == 0) ? $newBalance : ($oldOpening + $amountVal);
                        $ledgerDelta = $amountVal;
                    }

                    // Update Chart of Account
                    $account->opening_balance = $newOpening;
                    $account->current_balance = $newBalance;
                    $account->save();
                    $updatedCount++;

                    // Sync linked bank accounts if requested
                    if ($syncBankAccounts && $account->bankAccounts->isNotEmpty()) {
                        foreach ($account->bankAccounts as $bankAcc) {
                            $bankAcc->update(['current_balance' => $newBalance]);
                        }
                    }

                    // Determine ledger line side for this account delta
                    if ($postToLedger && abs($ledgerDelta) > 0.0001) {
                        $isDebitNormal = in_array($account->type, ['asset', 'expense']);
                        $side = null;
                        $lineAmt = abs($ledgerDelta);

                        if ($isDebitNormal) {
                            $side = ($ledgerDelta > 0) ? 'debit' : 'credit';
                        } else {
                            $side = ($ledgerDelta > 0) ? 'credit' : 'debit';
                        }

                        $ledgerLinesToCreate[] = [
                            'account_id'  => $account->id,
                            'coa_id'      => $account->id,
                            'side'        => $side,
                            'amount'      => $lineAmt,
                            'description' => "Initial baseline setup [{$account->code}] {$account->name}",
                        ];

                        if ($side === 'debit') {
                            $totalDebitLineAmount += $lineAmt;
                        } else {
                            $totalCreditLineAmount += $lineAmt;
                        }
                    }
                }

                // 2. Post Journal Entry to General Ledger if enabled and lines exist
                if ($postToLedger && !empty($ledgerLinesToCreate)) {
                    // Check if balancing line is needed
                    $imbalance = round($totalDebitLineAmount - $totalCreditLineAmount, 2);

                    if (abs($imbalance) > 0.001 && $autoBalanceEquity && $balancingEquityId) {
                        $balancingEquity = ChartOfAccount::find($balancingEquityId);
                        if ($balancingEquity) {
                            if ($imbalance > 0) {
                                // Debits > Credits -> Add Credit to Equity
                                $ledgerLinesToCreate[] = [
                                    'account_id'  => $balancingEquity->id,
                                    'coa_id'      => $balancingEquity->id,
                                    'side'        => 'credit',
                                    'amount'      => abs($imbalance),
                                    'description' => "Opening balance offset / baseline equity reconciliation",
                                ];
                                $totalCreditLineAmount += abs($imbalance);
                                $balancingEquity->increment('current_balance', abs($imbalance));
                            } else {
                                // Credits > Debits -> Add Debit to Equity
                                $ledgerLinesToCreate[] = [
                                    'account_id'  => $balancingEquity->id,
                                    'coa_id'      => $balancingEquity->id,
                                    'side'        => 'debit',
                                    'amount'      => abs($imbalance),
                                    'description' => "Opening balance offset / baseline equity reconciliation",
                                ];
                                $totalDebitLineAmount += abs($imbalance);
                                $balancingEquity->decrement('current_balance', abs($imbalance));
                            }
                        }
                    }

                    $jeNo = 'JE-OPENING-' . date('Ymd', strtotime($effectiveDate)) . '-' . strtoupper(Str::random(4));
                    $journalEntry = JournalEntry::create([
                        'entry_no'       => $jeNo,
                        'entry_date'     => $effectiveDate,
                        'reference_type' => 'coa_initial_setup',
                        'reference_id'   => null,
                        'description'    => "COA One-Time Account Ledger & Initial Balance Setup" . ($refNote ? " | {$refNote}" : ""),
                        'status'         => 'posted',
                        'created_by'     => $user->id,
                        'approved_by'    => $user->id,
                        'posted_at'      => now(),
                    ]);

                    foreach ($ledgerLinesToCreate as $lineData) {
                        $journalEntry->lines()->create($lineData);
                    }
                }

                // 3. Record Audit Log
                if (class_exists(ActivityLog::class)) {
                    $actionDesc = "Prepared one-time initial balances for {$updatedCount} accounts [Mode: {$mode}].";
                    if ($journalEntry) {
                        $actionDesc .= " Posted Journal Entry {$journalEntry->entry_no} (Debit: " . number_format($totalDebitLineAmount, 2) . " ETB, Credit: " . number_format($totalCreditLineAmount, 2) . " ETB).";
                    }
                    ActivityLog::log('updated', $actionDesc, 'Finance / COA');
                }
            });

            $msg = "Successfully prepared and updated balances for {$updatedCount} Chart of Accounts.";
            if ($journalEntry) {
                $msg .= " General Ledger entry [{$journalEntry->entry_no}] was officially posted and balanced.";
            }

            return redirect()->route('coa.index')->with('success', $msg);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error("COA Initial Balance Setup Failed: " . $e->getMessage() . "\n" . $e->getTraceAsString());
            return back()->withInput()->with('error', 'Balance setup failed: ' . $e->getMessage());
        }
    }

    public function create()
    {
        $parents = ChartOfAccount::whereNull('parent_id')->orderBy('code')->get();
        $users = \App\Models\User::orderBy('name')->get();
        return view('finance.coa.create', compact('parents', 'users'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'code'            => 'required|string|max:20|unique:chart_of_accounts',
            'name'            => 'required|string|max:255',
            'parent_id'       => 'nullable|exists:chart_of_accounts,id',
            'type'            => 'required|in:asset,liability,equity,revenue,expense',
            'subtype'         => 'nullable|string|max:50',
            'opening_balance' => 'nullable|numeric',
            'current_balance' => 'nullable|numeric',
            'description'     => 'nullable|string',
            'sort_order'      => 'nullable|integer',
            'assigned_to'     => 'nullable|exists:users,id',
        ]);

        if (isset($data['opening_balance']) && !isset($data['current_balance'])) {
            $data['current_balance'] = $data['opening_balance'];
        }

        ChartOfAccount::create($data);
        return redirect()->route('coa.index')->with('success', 'Account created successfully.');
    }

    public function edit(ChartOfAccount $coa)
    {
        $parents = ChartOfAccount::whereNull('parent_id')->where('id', '!=', $coa->id)->orderBy('code')->get();
        $users = \App\Models\User::orderBy('name')->get();
        return view('finance.coa.edit', compact('coa', 'parents', 'users'));
    }

    public function update(Request $request, ChartOfAccount $coa)
    {
        $data = $request->validate([
            'code'            => 'required|string|max:20|unique:chart_of_accounts,code,' . $coa->id,
            'name'            => 'required|string|max:255',
            'parent_id'       => 'nullable|exists:chart_of_accounts,id',
            'type'            => 'required|in:asset,liability,equity,revenue,expense',
            'subtype'         => 'nullable|string|max:50',
            'opening_balance' => 'nullable|numeric',
            'current_balance' => 'nullable|numeric',
            'description'     => 'nullable|string',
            'sort_order'      => 'nullable|integer',
            'is_active'       => 'boolean',
            'assigned_to'     => 'nullable|exists:users,id',
        ]);

        $coa->update($data);
        return redirect()->route('coa.index')->with('success', 'Account updated successfully.');
    }

    public function destroy(Request $request, $coa)
    {
        if ($coa instanceof ChartOfAccount) {
            $account = $coa;
        } else {
            $account = ChartOfAccount::withTrashed()->where('id', $coa)->orWhere('code', $coa)->first();
        }

        if (!$account) {
            return redirect()->route('coa.index')->with('error', 'Chart of Account not found or already deleted.');
        }

        $code = $account->code;
        $name = $account->name;

        try {
            ChartOfAccount::deleteAccountAndRelated($account, true);
            return redirect()->route('coa.index')->with('success', "Account {$code} ({$name}) and all associated history have been permanently deleted.");
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error("Failed to delete Chart of Account {$code}: " . $e->getMessage() . "\n" . $e->getTraceAsString());
            return redirect()->route('coa.index')->with('error', "Failed to delete account {$code}: " . $e->getMessage());
        }
    }
}
