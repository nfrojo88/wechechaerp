<?php

namespace App\Http\Controllers;

use App\Models\ChartOfAccount;
use App\Models\JournalEntryLine;
use App\Models\JournalEntry;
use App\Services\Finance\TrialBalanceIntegrityService;
use App\Services\Finance\AbnormalBalanceService;
use App\Services\Finance\FinanceMetricsService;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class FinanceReportController extends Controller
{
    protected TrialBalanceIntegrityService $tbService;
    protected AbnormalBalanceService $abnormalService;
    protected FinanceMetricsService $metricsService;

    public function __construct(
        TrialBalanceIntegrityService $tbService,
        AbnormalBalanceService $abnormalService,
        FinanceMetricsService $metricsService
    ) {
        $this->tbService       = $tbService;
        $this->abnormalService = $abnormalService;
        $this->metricsService  = $metricsService;
    }

    public function index()
    {
        return view('finance.reports.index');
    }

    public function trialBalance(Request $request)
    {
        $asOfDate  = $request->input('as_of_date');
        $startDate = $request->input('start_date');

        $tbData = $this->tbService->getTrialBalance($asOfDate, $startDate);
        $integrity = $this->tbService->checkIntegrity($asOfDate, $startDate);

        // Map rows into account objects for blade view compatibility
        $accounts = $tbData['rows']->map(function ($r) {
            $acc = new ChartOfAccount();
            $acc->id              = $r['account_id'];
            $acc->code            = $r['code'];
            $acc->name            = $r['name'];
            $acc->type            = $r['type'];
            $acc->normal_side     = $r['normal_side'];
            $acc->computed_debit  = $r['ending_debit'];
            $acc->computed_credit = $r['ending_credit'];
            $acc->period_debit    = $r['period_debit'];
            $acc->period_credit   = $r['period_credit'];
            return $acc;
        });

        $unbalancedEntries = $integrity['unbalanced_entries'];
        $isBalanced        = $tbData['is_balanced'];
        $difference        = $tbData['difference'];

        return view('finance.reports.trial_balance', compact(
            'accounts', 'isBalanced', 'difference', 'unbalancedEntries', 'asOfDate', 'startDate'
        ));
    }

    public function abnormalBalances(Request $request)
    {
        $asOfDate = $request->input('as_of_date', Carbon::now()->toDateString());
        $abnormalAccounts = $this->abnormalService->getAbnormalAccounts($asOfDate);

        return view('finance.reports.abnormal_balances', compact('abnormalAccounts', 'asOfDate'));
    }

    public function balanceSheet(Request $request)
    {
        $asOfDate = $request->input('as_of_date', Carbon::now()->format('Y-m-d'));

        $bsData = $this->metricsService->getBalanceSheet($asOfDate);

        // Map to collection of objects with computed_balance for blade view compatibility
        $assets = $bsData['assets']->map(function ($item) {
            $acc = new ChartOfAccount();
            $acc->id = $item['id'];
            $acc->code = $item['code'];
            $acc->name = $item['name'];
            $acc->type = 'asset';
            $acc->subtype = $item['subtype'];
            $acc->computed_balance = $item['balance'];
            return $acc;
        });

        $liabilities = $bsData['liabilities']->map(function ($item) {
            $acc = new ChartOfAccount();
            $acc->id = $item['id'];
            $acc->code = $item['code'];
            $acc->name = $item['name'];
            $acc->type = 'liability';
            $acc->subtype = $item['subtype'];
            $acc->computed_balance = $item['balance'];
            return $acc;
        });

        $equity = $bsData['equity']->map(function ($item) {
            $acc = new ChartOfAccount();
            $acc->id = $item['id'];
            $acc->code = $item['code'];
            $acc->name = $item['name'];
            $acc->type = 'equity';
            $acc->subtype = $item['subtype'];
            $acc->computed_balance = $item['balance'];
            return $acc;
        });

        $totalAssets      = $bsData['total_assets'];
        $totalLiabilities = $bsData['total_liabilities'];
        $totalEquity      = $bsData['total_equity'];
        $netIncome        = $bsData['current_year_net_profit'];

        return view('finance.reports.balance_sheet', compact(
            'assets', 'liabilities', 'equity', 'totalAssets', 'totalLiabilities', 'totalEquity', 'netIncome', 'asOfDate'
        ));
    }

    public function incomeStatement(Request $request)
    {
        $startDate = $request->input('start_date', Carbon::now()->startOfMonth()->format('Y-m-d'));
        $endDate   = $request->input('end_date', Carbon::now()->endOfMonth()->format('Y-m-d'));

        $revenues = $this->getAccountBalances(['revenue', 'income'], $endDate, $startDate);
        $expenses = $this->getAccountBalances(['expense', 'cost_of_sale'], $endDate, $startDate);

        $totalRevenue = $revenues->sum('computed_balance');
        $totalExpense = $expenses->sum('computed_balance');
        $netIncome    = $totalRevenue - $totalExpense;

        return view('finance.reports.income_statement', compact('revenues', 'expenses', 'totalRevenue', 'totalExpense', 'netIncome', 'startDate', 'endDate'));
    }

    private function getAccountBalances($typeIn, $endDate = null, $startDate = null)
    {
        $query = ChartOfAccount::whereIn('type', (array)$typeIn)->where('is_active', true);
        $accounts = $query->orderBy('code')->get();

        $linesQuery = JournalEntryLine::selectRaw("
                account_id,
                SUM(CASE WHEN side = 'debit'  THEN amount ELSE 0 END) as total_debit,
                SUM(CASE WHEN side = 'credit' THEN amount ELSE 0 END) as total_credit
            ")
            ->join('journal_entries', 'journal_entry_lines.journal_entry_id', '=', 'journal_entries.id')
            ->where('journal_entries.status', 'posted');

        if ($startDate) {
            $linesQuery->whereDate('journal_entries.entry_date', '>=', $startDate);
        }
        if ($endDate) {
            $linesQuery->whereDate('journal_entries.entry_date', '<=', $endDate);
        }

        $lines = $linesQuery->groupBy('account_id')->get()->keyBy('account_id');

        return $accounts->map(function ($acc) use ($lines) {
            $line = $lines->get($acc->id);
            $debit  = $line ? (float) $line->total_debit : 0;
            $credit = $line ? (float) $line->total_credit : 0;

            $normalSide = $acc->normal_side ?? (in_array($acc->type, ['asset', 'expense']) ? 'debit' : 'credit');

            if ($normalSide === 'debit') {
                $opBal = (float)$acc->opening_balance;
                $balance = $opBal + ($debit - $credit);
            } else {
                $opBal = (float)$acc->opening_balance;
                $balance = $opBal + ($credit - $debit);
            }

            $acc->period_debit    = $debit;
            $acc->period_credit   = $credit;
            $acc->computed_balance = $balance;

            return $acc;
        })->filter(function($acc) {
            return abs($acc->computed_balance) > 0.009 || $acc->period_debit > 0 || $acc->period_credit > 0;
        });
    }

    public function cashFlow(Request $request)
    {
        $startDate = $request->input('start_date', Carbon::now()->startOfYear()->format('Y-m-d'));
        $endDate   = $request->input('end_date', Carbon::now()->format('Y-m-d'));

        $revenues  = $this->getAccountBalances(['revenue', 'income'], $endDate, $startDate);
        $expenses  = $this->getAccountBalances(['expense', 'cost_of_sale'], $endDate, $startDate);
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
                        'code'   => $acc->code,
                        'name'   => $acc->name,
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
                        'code'   => $acc->code,
                        'name'   => $acc->name,
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
                        'code'   => $acc->code,
                        'name'   => $acc->name,
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
        $endDate   = $request->input('end_date', Carbon::now()->endOfMonth()->format('Y-m-d'));
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
        $endDate   = $request->input('end_date', Carbon::now()->format('Y-m-d'));

        $expenses = JournalEntryLine::join('journal_entries', 'journal_entry_lines.journal_entry_id', '=', 'journal_entries.id')
            ->join('chart_of_accounts', 'journal_entry_lines.account_id', '=', 'chart_of_accounts.id')
            ->where('chart_of_accounts.type', 'expense')
            ->where('journal_entries.status', 'posted')
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
