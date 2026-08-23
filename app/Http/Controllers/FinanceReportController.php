<?php

namespace App\Http\Controllers;

use App\Models\ChartOfAccount;
use App\Models\JournalEntryLine;
use App\Models\JournalEntry;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class FinanceReportController extends Controller
{
    public function index()
    {
        return view('finance.reports.index');
    }

    private function getAccountBalances($typeIn, $endDate = null, $startDate = null)
    {
        $query = ChartOfAccount::whereIn('type', (array)$typeIn)->where('is_active', true);
        $accounts = $query->orderBy('code')->get();

        $linesQuery = JournalEntryLine::selectRaw('
                account_id,
                SUM(CASE WHEN side = "debit"  THEN amount ELSE 0 END) as total_debit,
                SUM(CASE WHEN side = "credit" THEN amount ELSE 0 END) as total_credit
            ');
        
        if ($startDate || $endDate) {
            $linesQuery->join('journal_entries', 'journal_entry_lines.journal_entry_id', '=', 'journal_entries.id');
        }

        if ($startDate) {
            $linesQuery->whereDate('journal_entries.entry_date', '>=', $startDate);
        }
        if ($endDate) {
            $linesQuery->whereDate('journal_entries.entry_date', '<=', $endDate);
        }

        $lines = $linesQuery->groupBy('account_id')->get()->keyBy('account_id');

        return $accounts->map(function ($acc) use ($lines, $startDate) {
            $line = $lines->get($acc->id);
            $debit = $line ? (float) $line->total_debit : 0;
            $credit = $line ? (float) $line->total_credit : 0;
            
            $net_change = 0;
            if (in_array($acc->type, ['asset', 'expense'])) {
                $net_change = $debit - $credit;
                $balance = $acc->opening_balance + $net_change;
            } else {
                $net_change = $credit - $debit;
                $balance = $acc->opening_balance + $net_change;
            }

            $acc->period_debit = $debit;
            $acc->period_credit = $credit;
            $acc->computed_balance = $balance;
            
            return $acc;
        })->filter(function($acc) {
            return abs($acc->computed_balance) > 0 || $acc->period_debit > 0 || $acc->period_credit > 0;
        });
    }

    public function trialBalance(Request $request)
    {
        $accounts = ChartOfAccount::where('is_active', true)->orderBy('code')->get();
        
        $lines = JournalEntryLine::selectRaw('
                account_id,
                SUM(CASE WHEN side = "debit"  THEN amount ELSE 0 END) as total_debit,
                SUM(CASE WHEN side = "credit" THEN amount ELSE 0 END) as total_credit
            ')
            ->groupBy('account_id')
            ->get()
            ->keyBy('account_id');

        $accounts = $accounts->map(function ($acc) use ($lines) {
            $line = $lines->get($acc->id);
            $opening_debit = in_array($acc->type, ['asset', 'expense']) && $acc->opening_balance > 0 ? $acc->opening_balance : 0;
            $opening_credit = in_array($acc->type, ['liability', 'equity', 'revenue']) && $acc->opening_balance > 0 ? $acc->opening_balance : 0;
            
            if (in_array($acc->type, ['asset', 'expense']) && $acc->opening_balance < 0) {
                $opening_credit = abs($acc->opening_balance);
            }
            if (in_array($acc->type, ['liability', 'equity', 'revenue']) && $acc->opening_balance < 0) {
                $opening_debit = abs($acc->opening_balance);
            }

            $acc->computed_debit  = $opening_debit + ($line ? (float) $line->total_debit : 0);
            $acc->computed_credit = $opening_credit + ($line ? (float) $line->total_credit : 0);
            
            if ($acc->computed_debit > $acc->computed_credit) {
                $acc->computed_debit -= $acc->computed_credit;
                $acc->computed_credit = 0;
            } elseif ($acc->computed_credit > $acc->computed_debit) {
                $acc->computed_credit -= $acc->computed_debit;
                $acc->computed_debit = 0;
            } else {
                $acc->computed_debit = 0;
                $acc->computed_credit = 0;
            }

            return $acc;
        })->filter(fn($acc) => $acc->computed_debit > 0 || $acc->computed_credit > 0);

        return view('finance.reports.trial_balance', compact('accounts'));
    }

    public function incomeStatement(Request $request)
    {
        $startDate = $request->input('start_date', Carbon::now()->startOfMonth()->format('Y-m-d'));
        $endDate = $request->input('end_date', Carbon::now()->endOfMonth()->format('Y-m-d'));

        $revenues = $this->getAccountBalances(['revenue', 'income'], $endDate, $startDate);
        $expenses = $this->getAccountBalances(['expense', 'cost_of_sale'], $endDate, $startDate);

        $totalRevenue = $revenues->sum('computed_balance');
        $totalExpense = $expenses->sum('computed_balance');
        $netIncome = $totalRevenue - $totalExpense;

        return view('finance.reports.income_statement', compact('revenues', 'expenses', 'totalRevenue', 'totalExpense', 'netIncome', 'startDate', 'endDate'));
    }

    public function balanceSheet(Request $request)
    {
        $asOfDate = $request->input('as_of_date', Carbon::now()->format('Y-m-d'));

        $assets = $this->getAccountBalances(['asset', 'current_asset', 'fixed_asset', 'fixed_assets', 'cash_and_bank', 'receivables', 'receivable'], $asOfDate);
        $liabilities = $this->getAccountBalances(['liability', 'current_liability', 'long_term_liability', 'liabilities', 'accounts_payable', 'other_current_liablity'], $asOfDate);
        $equity = $this->getAccountBalances(['equity', 'equity_dose_not_closed', 'equity-retend_earning'], $asOfDate);

        $revenues = $this->getAccountBalances(['revenue', 'income', 'other_income'], $asOfDate);
        $expenses = $this->getAccountBalances(['expense', 'cost_of_sale', 'expenss'], $asOfDate);
        $netIncome = $revenues->sum('computed_balance') - $expenses->sum('computed_balance');

        $totalAssets = $assets->sum('computed_balance');
        $totalLiabilities = $liabilities->sum('computed_balance');
        $totalEquity = $equity->sum('computed_balance') + $netIncome;

        return view('finance.reports.balance_sheet', compact('assets', 'liabilities', 'equity', 'totalAssets', 'totalLiabilities', 'totalEquity', 'netIncome', 'asOfDate'));
    }

    public function cashFlow(Request $request)
    {
        $startDate = $request->input('start_date', Carbon::now()->startOfYear()->format('Y-m-d'));
        $endDate = $request->input('end_date', Carbon::now()->format('Y-m-d'));

        $revenues = $this->getAccountBalances(['revenue', 'income'], $endDate, $startDate);
        $expenses = $this->getAccountBalances(['expense', 'cost_of_sale'], $endDate, $startDate);
        $netProfit = $revenues->sum('computed_balance') - $expenses->sum('computed_balance');

        $workingCapitalAccounts = ChartOfAccount::whereIn('type', ['asset', 'liability'])
            ->whereNotIn('subtype', ['cash_and_bank', 'fixed_assets'])
            ->get();
            
        $operatingAdjustments = [];
        $totalOperatingAdjustments = 0;
        
        foreach ($workingCapitalAccounts as $acc) {
            $bal = $this->getAccountBalances([$acc->type], $endDate, $startDate)->firstWhere('id', $acc->id);
            if ($bal && $bal->computed_balance != 0) {
                $change = $acc->type === 'asset' ? -$bal->computed_balance : $bal->computed_balance;
                if ($change != 0) {
                    $operatingAdjustments[] = [
                        'code' => $acc->code,
                        'name' => $acc->name,
                        'amount' => $change
                    ];
                    $totalOperatingAdjustments += $change;
                }
            }
        }

        $netCashOperating = $netProfit + $totalOperatingAdjustments;

        $investingAdjustments = [];
        $totalInvesting = 0;
        $fixedAssets = ChartOfAccount::where('subtype', 'fixed_assets')->get();
        foreach ($fixedAssets as $acc) {
            $bal = $this->getAccountBalances([$acc->type], $endDate, $startDate)->firstWhere('id', $acc->id);
            if ($bal && $bal->computed_balance != 0) {
                $change = -$bal->computed_balance;
                if ($change != 0) {
                    $investingAdjustments[] = [
                        'code' => $acc->code,
                        'name' => $acc->name,
                        'amount' => $change
                    ];
                    $totalInvesting += $change;
                }
            }
        }

        $financingAdjustments = [];
        $totalFinancing = 0;
        $financingAccounts = ChartOfAccount::whereIn('type', ['equity', 'long_term_liability'])->get();
        foreach ($financingAccounts as $acc) {
            $bal = $this->getAccountBalances([$acc->type], $endDate, $startDate)->firstWhere('id', $acc->id);
            if ($bal && $bal->computed_balance != 0) {
                $change = $bal->computed_balance;
                if ($change != 0) {
                    $financingAdjustments[] = [
                        'code' => $acc->code,
                        'name' => $acc->name,
                        'amount' => $change
                    ];
                    $totalFinancing += $change;
                }
            }
        }

        return view('finance.reports.cash_flow', compact(
            'netProfit', 'operatingAdjustments', 'totalOperatingAdjustments', 'netCashOperating',
            'investingAdjustments', 'totalInvesting',
            'financingAdjustments', 'totalFinancing',
            'startDate', 'endDate'
        ));
    }

    public function generalLedger(Request $request)
    {
        $startDate = $request->input('start_date', Carbon::now()->startOfMonth()->format('Y-m-d'));
        $endDate = $request->input('end_date', Carbon::now()->endOfMonth()->format('Y-m-d'));
        $accountId = $request->input('account_id');

        $query = JournalEntryLine::with(['journalEntry.creator', 'account'])
            ->join('journal_entries', 'journal_entry_lines.journal_entry_id', '=', 'journal_entries.id')
            ->select('journal_entry_lines.*', 'journal_entries.entry_date', 'journal_entries.entry_no as reference', 'journal_entries.description as je_description', 'journal_entries.created_by')
            ->orderBy('journal_entries.entry_date', 'desc')
            ->orderBy('journal_entries.id', 'desc');

        if ($startDate) {
            $query->whereDate('journal_entries.entry_date', '>=', $startDate);
        }
        if ($endDate) {
            $query->whereDate('journal_entries.entry_date', '<=', $endDate);
        }
        if ($accountId) {
            $query->where('account_id', $accountId);
        }

        $entries = $query->paginate(50);
        $accounts = ChartOfAccount::where('is_active', true)->orderBy('name')->get();

        return view('finance.reports.general_ledger', compact('entries', 'accounts', 'startDate', 'endDate', 'accountId'));
    }

    public function expenseBySite(Request $request)
    {
        $startDate = $request->input('start_date', Carbon::now()->startOfYear()->format('Y-m-d'));
        $endDate = $request->input('end_date', Carbon::now()->format('Y-m-d'));
        
        $expenses = JournalEntryLine::join('journal_entries', 'journal_entry_lines.journal_entry_id', '=', 'journal_entries.id')
            ->join('chart_of_accounts', 'journal_entry_lines.account_id', '=', 'chart_of_accounts.id')
            ->where('chart_of_accounts.type', 'expense')
            ->selectRaw('
                chart_of_accounts.name as account_name,
                chart_of_accounts.code as account_code,
                SUM(CASE WHEN side = "debit" THEN amount ELSE -amount END) as total_amount
            ')
            ->whereDate('journal_entries.entry_date', '>=', $startDate)
            ->whereDate('journal_entries.entry_date', '<=', $endDate)
            ->groupBy('chart_of_accounts.id', 'chart_of_accounts.name', 'chart_of_accounts.code')
            ->having('total_amount', '>', 0)
            ->get();

        return view('finance.reports.expense_by_site', compact('expenses', 'startDate', 'endDate'));
    }
}
