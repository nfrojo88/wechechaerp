<?php

namespace App\Services\Finance;

use App\Models\BankAccount;
use App\Models\ChartOfAccount;
use App\Models\CreditStoreLedger;
use App\Models\JournalEntryLine;
use App\Models\PettyCashSubledger;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class FinanceMetricsService
{
    /**
     * Compute authoritative Balance Sheet strictly from ledger balances.
     * Enforces Assets = Liabilities + Equity with ZERO plug accounts.
     * Bank overdrafts (accounts 1010-1013 with net credit balance) are shown as liabilities under Overdrafts.
     * Current-year net operating profit flows directly into Equity.
     */
    public function getBalanceSheet(?string $asOfDate = null, bool $excludeSeedData = false): array
    {
        $asOfDate = $asOfDate ?: Carbon::now()->toDateString();

        $query = ChartOfAccount::where('is_active', true);
        if ($excludeSeedData) {
            $query->where('is_seed', false);
        }
        $accounts = $query->orderBy('code')->get();

        $linesQuery = JournalEntryLine::selectRaw("
                journal_entry_lines.account_id,
                SUM(CASE WHEN journal_entry_lines.side = 'debit'  THEN journal_entry_lines.amount ELSE 0 END) as total_debit,
                SUM(CASE WHEN journal_entry_lines.side = 'credit' THEN journal_entry_lines.amount ELSE 0 END) as total_credit
            ")
            ->join('journal_entries', 'journal_entry_lines.journal_entry_id', '=', 'journal_entries.id')
            ->where('journal_entries.status', 'posted')
            ->whereDate('journal_entries.entry_date', '<=', $asOfDate);

        if ($excludeSeedData) {
            $linesQuery->where('journal_entries.is_seed', false);
        }

        $lines = $linesQuery->groupBy('journal_entry_lines.account_id')
            ->get()
            ->keyBy('account_id');

        $assetAccounts     = [];
        $liabilityAccounts = [];
        $overdraftAccounts = [];
        $equityAccounts    = [];
        $revenueTotal      = 0.0;
        $expenseTotal      = 0.0;

        foreach ($accounts as $acc) {
            $line = $lines->get($acc->id);
            $debit  = $line ? (float)$line->total_debit : 0.0;
            $credit = $line ? (float)$line->total_credit : 0.0;
            $opBal  = (float)$acc->opening_balance;

            $normalSide = in_array($acc->type, ['liability', 'equity', 'revenue']) ? 'credit' : 'debit';
            if (!empty($acc->normal_side) && in_array($acc->normal_side, ['debit', 'credit'])) {
                $normalSide = $acc->normal_side;
            }

            // Compute net balance on normal side
            if ($normalSide === 'debit') {
                $openingDebit = ($opBal >= 0) ? $opBal : 0;
                $openingCredit = ($opBal < 0) ? abs($opBal) : 0;
                $netBalance = round(($openingDebit + $debit) - ($openingCredit + $credit), 2);
            } else {
                $openingCredit = ($opBal >= 0) ? $opBal : 0;
                $openingDebit = ($opBal < 0) ? abs($opBal) : 0;
                $netBalance = round(($openingCredit + $credit) - ($openingDebit + $debit), 2);
            }

            // Rule 5: Bank accounts (1010-1013 / cash_and_bank) allow either side
            $isBankOrCash = in_array($acc->code, ['1010', '1011', '1012', '1013']) || $acc->subtype === 'cash_and_bank';

            if ($isBankOrCash) {
                if ($netBalance >= 0) {
                    $assetAccounts[] = [
                        'id'      => $acc->id,
                        'code'    => $acc->code,
                        'name'    => $acc->name,
                        'subtype' => $acc->subtype,
                        'balance' => $netBalance,
                    ];
                } else {
                    // Credit balance presented as Bank Overdraft under Liabilities
                    $overdraftAccounts[] = [
                        'id'      => $acc->id,
                        'code'    => $acc->code,
                        'name'    => $acc->name . ' (Overdraft)',
                        'subtype' => 'bank_overdraft',
                        'balance' => abs($netBalance),
                    ];
                }
            } elseif ($acc->type === 'asset') {
                if (abs($netBalance) > 0.009 || $debit > 0 || $credit > 0) {
                    $assetAccounts[] = [
                        'id'      => $acc->id,
                        'code'    => $acc->code,
                        'name'    => $acc->name,
                        'subtype' => $acc->subtype,
                        'balance' => $netBalance,
                    ];
                }
            } elseif ($acc->type === 'liability') {
                if (abs($netBalance) > 0.009 || $debit > 0 || $credit > 0) {
                    $liabilityAccounts[] = [
                        'id'      => $acc->id,
                        'code'    => $acc->code,
                        'name'    => $acc->name,
                        'subtype' => $acc->subtype,
                        'balance' => $netBalance,
                    ];
                }
            } elseif ($acc->type === 'equity') {
                $equityAccounts[] = [
                    'id'      => $acc->id,
                    'code'    => $acc->code,
                    'name'    => $acc->name,
                    'subtype' => $acc->subtype,
                    'balance' => $netBalance,
                ];
            } elseif ($acc->type === 'revenue') {
                $revenueTotal += $netBalance;
            } elseif ($acc->type === 'expense') {
                $expenseTotal += $netBalance;
            }
        }

        $totalAssets       = round(collect($assetAccounts)->sum('balance'), 2);
        $totalStandardLiab = round(collect($liabilityAccounts)->sum('balance'), 2);
        $totalOverdrafts   = round(collect($overdraftAccounts)->sum('balance'), 2);
        $totalLiabilities  = round($totalStandardLiab + $totalOverdrafts, 2);
        $baseEquity        = round(collect($equityAccounts)->sum('balance'), 2);

        // Operating Net Profit flows into Equity
        $currentYearProfit = round($revenueTotal - $expenseTotal, 2);
        $totalEquity       = round($baseEquity + $currentYearProfit, 2);

        // Verification of accounting equation: Assets = Liabilities + Equity
        $totalLiabilitiesAndEquity = round($totalLiabilities + $totalEquity, 2);
        $imbalance = round($totalAssets - $totalLiabilitiesAndEquity, 2);

        return [
            'as_of_date'                   => $asOfDate,
            'assets'                       => collect($assetAccounts),
            'liabilities'                  => collect($liabilityAccounts),
            'overdrafts'                   => collect($overdraftAccounts),
            'total_assets'                 => $totalAssets,
            'total_liabilities'            => $totalLiabilities,
            'total_overdrafts'             => $totalOverdrafts,
            'base_equity'                  => $baseEquity,
            'current_year_net_profit'      => $currentYearProfit,
            'equity'                       => collect($equityAccounts),
            'total_equity'                 => $totalEquity,
            'total_liabilities_and_equity' => $totalLiabilitiesAndEquity,
            'is_balanced'                  => abs($imbalance) < 0.01,
            'imbalance'                    => $imbalance,
        ];
    }

    /**
     * Authoritative Days Sales Outstanding (DSO) calculation.
     * Formula: (Ending Trade Receivables / Total Billed Sales) * Days In Period
     */
    public function getDaysSalesOutstanding(?string $startDate = null, ?string $endDate = null): array
    {
        $startDate = $startDate ?: Carbon::now()->startOfMonth()->toDateString();
        $endDate   = $endDate ?: Carbon::now()->endOfMonth()->toDateString();
        $days      = max(1, Carbon::parse($startDate)->diffInDays(Carbon::parse($endDate)) + 1);

        $receivableAccount = ChartOfAccount::where('type', 'asset')
            ->where(function($q) {
                $q->where('subtype', 'like', '%receivable%')
                  ->orWhere('name', 'like', '%Receivable%')
                  ->orWhere('code', '1200');
            })
            ->first();

        $receivablesBalance = $receivableAccount ? (float)$receivableAccount->ledger_balance : 0.0;
        $revAccounts        = ChartOfAccount::where('type', 'revenue')->get();
        $batchRevBalances   = ChartOfAccount::getBatchLedgerBalances($revAccounts);
        $billedRevenue      = round(array_sum($batchRevBalances), 2);

        $dso = $billedRevenue > 0
            ? round(($receivablesBalance / $billedRevenue) * $days, 1)
            : 0.0;

        return [
            'dso_days'            => $dso,
            'receivables_balance' => $receivablesBalance,
            'billed_revenue'      => $billedRevenue,
            'days_in_period'      => $days,
            'formula'             => '(Ending Receivables / Billed Revenue) * Days',
        ];
    }

    /**
     * Authoritative Days Payables Outstanding (DPO) calculation.
     * Formula: (Ending Trade Payables / Cost of Materials & Purchases) * Days In Period
     */
    public function getDaysPayablesOutstanding(?string $startDate = null, ?string $endDate = null): array
    {
        $startDate = $startDate ?: Carbon::now()->startOfMonth()->toDateString();
        $endDate   = $endDate ?: Carbon::now()->endOfMonth()->toDateString();
        $days      = max(1, Carbon::parse($startDate)->diffInDays(Carbon::parse($endDate)) + 1);

        $apAccount = ChartOfAccount::where('code', '2001')->first()
            ?? ChartOfAccount::where('type', 'liability')->where('subtype', 'like', '%payable%')->first();

        $tradePayables = $apAccount ? abs((float)$apAccount->ledger_balance) : 0.0;

        $cogsAccount = ChartOfAccount::where('code', '5100')->first()
            ?? ChartOfAccount::where('type', 'expense')->where('subtype', 'like', '%cost%')->first();

        $materialPurchases = $cogsAccount ? (float)$cogsAccount->ledger_balance : 0.0;

        $dpo = $materialPurchases > 0
            ? round(($tradePayables / $materialPurchases) * $days, 1)
            : 0.0;

        return [
            'dpo_days'           => $dpo,
            'trade_payables'     => $tradePayables,
            'material_purchases' => $materialPurchases,
            'days_in_period'     => $days,
            'formula'            => '(Ending Trade Payables / Total Material Purchases) * Days',
        ];
    }

    /**
     * Reconcile AR Control Account with subledger (customer receivables).
     */
    public function reconcileArSubledger(): array
    {
        $controlAcc = ChartOfAccount::where('code', '1200')->first();
        $controlBal = $controlAcc ? (float)$controlAcc->ledger_balance : 0.0;

        // Subledger: open/pending customer receivables
        $subledgerTotal = 0.0;
        if (\Illuminate\Support\Facades\Schema::hasTable('client_ipcs')) {
            $subledgerTotal = (float) DB::table('client_ipcs')
                ->whereIn('status', ['submitted', 'certified', 'billed'])
                ->sum('net_payable_amount');
        }

        $diff = round($controlBal - $subledgerTotal, 2);

        return [
            'control_code'      => '1200',
            'control_name'      => 'Accounts Receivable',
            'control_balance'   => $controlBal,
            'subledger_total'   => $subledgerTotal,
            'discrepancy'       => $diff,
            'is_reconciled'     => abs($diff) < 0.01,
        ];
    }

    /**
     * Reconcile AP Control Account with subledger (vendor payables / credit store ledger).
     */
    public function reconcileApSubledger(): array
    {
        $controlAcc = ChartOfAccount::where('code', '2001')->first();
        $controlBal = $controlAcc ? abs((float)$controlAcc->ledger_balance) : 0.0;

        $subledgerTotal = 0.0;
        if (\Illuminate\Support\Facades\Schema::hasTable('credit_store_ledger')) {
            $subledgerTotal = (float) DB::table('credit_store_ledger')
                ->whereIn('payment_status', ['pending', 'partially_paid', 'unpaid'])
                ->sum('remaining_balance');
        }

        $diff = round($controlBal - $subledgerTotal, 2);

        return [
            'control_code'      => '2001',
            'control_name'      => 'Accounts Payable',
            'control_balance'   => $controlBal,
            'subledger_total'   => $subledgerTotal,
            'discrepancy'       => $diff,
            'is_reconciled'     => abs($diff) < 0.01,
        ];
    }

    /**
     * Reconcile Bank GL Control Accounts with bank_accounts subledger table.
     */
    public function reconcileBankSubledger(): array
    {
        $bankAccounts = BankAccount::where('is_active', true)->get();
        $subledgerTotal = (float) $bankAccounts->sum('current_balance');

        $coaIds = $bankAccounts->pluck('coa_id')->filter()->unique()->toArray();
        $batchBalances = ChartOfAccount::getBatchLedgerBalances($coaIds);

        $glTotal = 0.0;
        foreach ($bankAccounts as $ba) {
            if ($ba->coa_id && isset($batchBalances[$ba->coa_id])) {
                $glTotal += (float)$batchBalances[$ba->coa_id];
            }
        }

        $diff = round($glTotal - $subledgerTotal, 2);

        return [
            'bank_accounts_count' => $bankAccounts->count(),
            'gl_control_total'    => $glTotal,
            'subledger_total'     => $subledgerTotal,
            'discrepancy'         => $diff,
            'is_reconciled'       => abs($diff) < 0.01,
        ];
    }

    /**
     * Reconcile Petty Cash Control Account (1010) with petty_cash_subledgers table.
     */
    public function reconcilePettyCashSubledger(): array
    {
        return PettyCashSubledger::reconcileWithControlAccount();
    }

    /**
     * Reconcile Tax Compliance Report (VAT & WHT) totals with GL Control Accounts 2300 (VAT Payable) and 2600 (WHT Payable).
     */
    public function reconcileTaxReportWithGl(?string $startDate = null, ?string $endDate = null): array
    {
        // 1. GL Balance for 2300 VAT Payable
        $vatAcc = ChartOfAccount::where('code', '2300')->first();
        $glVat = 0.0;
        if ($vatAcc) {
            $vatLines = JournalEntryLine::join('journal_entries', 'journal_entry_lines.journal_entry_id', '=', 'journal_entries.id')
                ->where('journal_entries.status', 'posted')
                ->where('journal_entry_lines.account_id', $vatAcc->id);
            if ($startDate) $vatLines->whereDate('journal_entries.entry_date', '>=', $startDate);
            if ($endDate)   $vatLines->whereDate('journal_entries.entry_date', '<=', $endDate);
            $glVat = (float) $vatLines->selectRaw("SUM(CASE WHEN side = 'credit' THEN amount ELSE -amount END) as net_bal")->value('net_bal') ?? 0.0;
        }

        // 2. GL Balance for 2600 WHT Payable
        $whtAcc = ChartOfAccount::where('code', '2600')->first();
        $glWht = 0.0;
        if ($whtAcc) {
            $whtLines = JournalEntryLine::join('journal_entries', 'journal_entry_lines.journal_entry_id', '=', 'journal_entries.id')
                ->where('journal_entries.status', 'posted')
                ->where('journal_entry_lines.account_id', $whtAcc->id);
            if ($startDate) $whtLines->whereDate('journal_entries.entry_date', '>=', $startDate);
            if ($endDate)   $whtLines->whereDate('journal_entries.entry_date', '<=', $endDate);
            $glWht = (float) $whtLines->selectRaw("SUM(CASE WHEN side = 'credit' THEN amount ELSE -amount END) as net_bal")->value('net_bal') ?? 0.0;
        }

        // 3. Tax Report / Subledger Totals
        $taxReportVat = 0.0;
        if (\Illuminate\Support\Facades\Schema::hasTable('credit_store_payments') && \Illuminate\Support\Facades\Schema::hasColumn('credit_store_payments', 'vat_amount')) {
            $cpVat = DB::table('credit_store_payments');
            if ($startDate) $cpVat->whereDate('payment_date', '>=', $startDate);
            if ($endDate)   $cpVat->whereDate('payment_date', '<=', $endDate);
            $taxReportVat += (float) $cpVat->sum('vat_amount');
        }
        if (\Illuminate\Support\Facades\Schema::hasTable('expense_requests') && \Illuminate\Support\Facades\Schema::hasColumn('expense_requests', 'vat_amount')) {
            $erVat = DB::table('expense_requests')->where('status', 'paid');
            if ($startDate) $erVat->whereDate('created_at', '>=', $startDate);
            if ($endDate)   $erVat->whereDate('created_at', '<=', $endDate);
            $taxReportVat += (float) ($erVat->sum('vat_amount') ?? 0.0);
        }

        $taxReportWht = 0.0;
        if (\Illuminate\Support\Facades\Schema::hasTable('credit_store_payments') && \Illuminate\Support\Facades\Schema::hasColumn('credit_store_payments', 'withholding_amount')) {
            $cpWht = DB::table('credit_store_payments');
            if ($startDate) $cpWht->whereDate('payment_date', '>=', $startDate);
            if ($endDate)   $cpWht->whereDate('payment_date', '<=', $endDate);
            $taxReportWht += (float) $cpWht->sum('withholding_amount');
        }
        if (\Illuminate\Support\Facades\Schema::hasTable('expense_requests') && \Illuminate\Support\Facades\Schema::hasColumn('expense_requests', 'withholding_amount')) {
            $erWht = DB::table('expense_requests')->where('status', 'paid');
            if ($startDate) $erWht->whereDate('created_at', '>=', $startDate);
            if ($endDate)   $erWht->whereDate('created_at', '<=', $endDate);
            $taxReportWht += (float) ($erWht->sum('withholding_amount') ?? 0.0);
        }

        $vatDiff = round($glVat - $taxReportVat, 2);
        $whtDiff = round($glWht - $taxReportWht, 2);

        return [
            'vat' => [
                'gl_account_code'    => '2300',
                'gl_account_name'    => 'VAT Payable',
                'gl_balance'         => $glVat,
                'tax_report_total'   => $taxReportVat,
                'discrepancy'        => $vatDiff,
                'is_reconciled'      => abs($vatDiff) < 0.01,
            ],
            'wht' => [
                'gl_account_code'    => '2600',
                'gl_account_name'    => 'WHT Payable',
                'gl_balance'         => $glWht,
                'tax_report_total'   => $taxReportWht,
                'discrepancy'        => $whtDiff,
                'is_reconciled'      => abs($whtDiff) < 0.01,
            ],
            'is_fully_reconciled'    => abs($vatDiff) < 0.01 && abs($whtDiff) < 0.01,
        ];
    }

    /**
     * Consolidated subledger reconciliation health check.
     */
    public function getAllSubledgerReconciliations(): array
    {
        return [
            'ar'         => $this->reconcileArSubledger(),
            'ap'         => $this->reconcileApSubledger(),
            'bank'       => $this->reconcileBankSubledger(),
            'petty_cash' => $this->reconcilePettyCashSubledger(),
            'tax_report' => $this->reconcileTaxReportWithGl(),
        ];
    }

    /**
     * Authoritative total cash and bank balance (net of overdrafts).
     */
    public function getTotalCashAndBank(bool $excludeSeedData = false): float
    {
        $bs = $this->getBalanceSheet(null, $excludeSeedData);
        $cashAssets = $bs['assets']->filter(function($a) {
            return in_array($a['code'], ['1010', '1011', '1012', '1013']) || ($a['subtype'] ?? '') === 'cash_and_bank';
        })->sum('balance');

        $overdrafts = $bs['overdrafts']->sum('balance');
        return round($cashAssets - $overdrafts, 2);
    }

    /**
     * Authoritative total trade receivables balance.
     */
    public function getTotalTradeReceivables(bool $excludeSeedData = false): float
    {
        $bs = $this->getBalanceSheet(null, $excludeSeedData);
        return round($bs['assets']->filter(function($a) {
            return $a['code'] === '1200' || str_contains(strtolower($a['subtype'] ?? ''), 'receivable');
        })->sum('balance'), 2);
    }

    /**
     * Authoritative total trade payables balance.
     */
    public function getTotalTradePayables(bool $excludeSeedData = false): float
    {
        $bs = $this->getBalanceSheet(null, $excludeSeedData);
        return round($bs['liabilities']->filter(function($l) {
            return $l['code'] === '2001' || str_contains(strtolower($l['subtype'] ?? ''), 'payable');
        })->sum('balance'), 2);
    }

    /**
     * Authoritative Cash and Bank summary across the ERP.
     */
    public function getCashAndBankSummary(): array
    {
        $cbe       = ChartOfAccount::where('code', '1011')->first();
        $abyssinia = ChartOfAccount::where('code', '1012')->first();
        $petty     = ChartOfAccount::where('code', '1010')->first();

        return [
            'cbe_balance'                => $cbe ? (float)$cbe->ledger_balance : 0.0,
            'abyssinia_facility_balance' => $abyssinia ? (float)$abyssinia->ledger_balance : 0.0,
            'petty_cash_control_balance' => $petty ? (float)$petty->ledger_balance : 0.0,
            'subledger_reconciliations'  => $this->getAllSubledgerReconciliations(),
        ];
    }

    /**
     * Daily check that lists differences between stored current_balance and authoritative ledger_balance per account.
     */
    public function getDualSourceDiscrepancies(bool $onlyDiscrepancies = true): array
    {
        $accounts = ChartOfAccount::where('is_active', true)->orderBy('code')->get();

        // Fetch all posted lines in one grouped query for speed
        $lines = JournalEntryLine::selectRaw("
                journal_entry_lines.account_id,
                SUM(CASE WHEN journal_entry_lines.side = 'debit'  THEN journal_entry_lines.amount ELSE 0 END) as total_debit,
                SUM(CASE WHEN journal_entry_lines.side = 'credit' THEN journal_entry_lines.amount ELSE 0 END) as total_credit
            ")
            ->join('journal_entries', 'journal_entry_lines.journal_entry_id', '=', 'journal_entries.id')
            ->where('journal_entries.status', 'posted')
            ->groupBy('journal_entry_lines.account_id')
            ->get()
            ->keyBy('account_id');

        $report = [];
        $hasDifferences = false;

        foreach ($accounts as $acc) {
            $line = $lines->get($acc->id);
            $debits = $line ? (float)$line->total_debit : 0.0;
            $credits = $line ? (float)$line->total_credit : 0.0;

            $normalSide = $acc->normal_side;
            if (empty($normalSide) || !in_array($normalSide, ['debit', 'credit'])) {
                $normalSide = in_array($acc->type, ['liability', 'equity', 'revenue']) ? 'credit' : 'debit';
            }

            $ledgerBal = $normalSide === 'debit' ? round($debits - $credits, 2) : round($credits - $debits, 2);
            $storedBal = round((float)$acc->current_balance, 2);
            $diff = round($storedBal - $ledgerBal, 2);

            if (abs($diff) > 0.009) {
                $hasDifferences = true;
            }

            if (!$onlyDiscrepancies || abs($diff) > 0.009) {
                $report[] = [
                    'account_id'      => $acc->id,
                    'code'            => $acc->code,
                    'name'            => $acc->name,
                    'type'            => $acc->type,
                    'normal_side'     => $normalSide,
                    'stored_balance'  => $storedBal,
                    'ledger_balance'  => $ledgerBal,
                    'difference'      => $diff,
                    'is_synced'       => abs($diff) <= 0.009,
                ];
            }
        }

        return [
            'checked_at'           => Carbon::now()->toIso8601String(),
            'total_accounts'       => $accounts->count(),
            'discrepancy_count'    => collect($report)->where('is_synced', false)->count(),
            'has_discrepancies'    => $hasDifferences,
            'accounts'             => $report,
        ];
    }

    /**
     * Compute authoritative custodian petty cash float derived strictly from posted journal lines by party_id.
     */
    public function getCustodianPettyCashBalance(int $employeeId, ?int $pettyCashAccountId = null): float
    {
        $query = JournalEntryLine::join('journal_entries', 'journal_entry_lines.journal_entry_id', '=', 'journal_entries.id')
            ->where('journal_entries.status', 'posted')
            ->where('journal_entry_lines.party_type', 'employee')
            ->where('journal_entry_lines.party_id', $employeeId);

        if ($pettyCashAccountId) {
            $query->where('journal_entry_lines.account_id', $pettyCashAccountId);
        } else {
            $pettyAcc = ChartOfAccount::where('code', '1010')->first();
            if ($pettyAcc) {
                $query->where('journal_entry_lines.account_id', $pettyAcc->id);
            }
        }

        $balance = $query->selectRaw("SUM(CASE WHEN journal_entry_lines.side = 'debit' THEN journal_entry_lines.amount ELSE -journal_entry_lines.amount END) as net_float")->value('net_float');

        return round((float) ($balance ?? 0.0), 2);
    }

    /**
     * Daily check that reconciles every bank_accounts record against its linked Chart of Account ledger balance.
     */
    public function reconcileBankAccountsVsLedger(bool $onlyDiscrepancies = false): array
    {
        $bankAccounts = \App\Models\BankAccount::with('coa')->where('is_active', true)->get();
        $coaIds = $bankAccounts->pluck('coa_id')->filter()->unique()->toArray();
        $batchBalances = ChartOfAccount::getBatchLedgerBalances($coaIds);

        $report = [];
        $hasDifferences = false;

        foreach ($bankAccounts as $bank) {
            $coa = $bank->coa;
            $storedBankBal = round((float)$bank->current_balance, 2);
            $coaStoredBal  = $coa ? round((float)$coa->current_balance, 2) : 0.00;
            $coaLedgerBal  = ($bank->coa_id && isset($batchBalances[$bank->coa_id]))
                ? round((float)$batchBalances[$bank->coa_id], 2)
                : 0.00;
            $diffVsLedger  = round($storedBankBal - $coaLedgerBal, 2);

            $isDiscrepant = abs($diffVsLedger) > 0.009;
            if ($isDiscrepant) {
                $hasDifferences = true;
            }

            if (!$onlyDiscrepancies || $isDiscrepant) {
                $report[] = [
                    'bank_account_id'      => $bank->id,
                    'account_name'         => $bank->account_name,
                    'account_number'       => $bank->account_number,
                    'bank_name'            => $bank->bank_name,
                    'coa_id'               => $bank->coa_id,
                    'coa_code'             => $coa?->code,
                    'coa_name'             => $coa?->name,
                    'bank_stored_balance'  => $storedBankBal,
                    'coa_stored_balance'   => $coaStoredBal,
                    'coa_ledger_balance'   => $coaLedgerBal,
                    'variance'             => $diffVsLedger,
                    'is_reconciled'        => !$isDiscrepant,
                ];
            }
        }

        return [
            'reconciled'      => !$hasDifferences,
            'total_accounts'  => $bankAccounts->count(),
            'discrepancies'   => count(array_filter($report, fn($r) => !$r['is_reconciled'])),
            'report'          => $report,
        ];
    }
}
