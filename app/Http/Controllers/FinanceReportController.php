<?php

namespace App\Http\Controllers;

use App\Models\ChartOfAccount;
use App\Models\JournalEntryLine;
use App\Models\JournalEntry;
use App\Models\Project;
use App\Models\IncomeRecord;
use App\Models\Expense;
use App\Models\ExpenseRequest;
use App\Models\ProcurementPayment;
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
        $selectedProjectId = $request->input('project_id');

        $projects = Project::where('status', '!=', 'cancelled')->orderBy('name')->get();
        if ($projects->isEmpty()) {
            $projects = Project::orderBy('name')->get();
        }

        // If a specific project is selected, fetch it
        $selectedProject = ($selectedProjectId && $selectedProjectId !== 'all') 
            ? Project::find($selectedProjectId) 
            : null;

        // 1. General GL Expense Breakdown
        $expenseQuery = JournalEntryLine::join('journal_entries', 'journal_entry_lines.journal_entry_id', '=', 'journal_entries.id')
            ->join('chart_of_accounts', 'journal_entry_lines.account_id', '=', 'chart_of_accounts.id')
            ->where('chart_of_accounts.type', 'expense')
            ->where('journal_entries.status', 'posted')
            ->whereDate('journal_entries.entry_date', '>=', $startDate)
            ->whereDate('journal_entries.entry_date', '<=', $endDate);

        if ($selectedProject && \Illuminate\Support\Facades\Schema::hasColumn('journal_entry_lines', 'project_id')) {
            $expenseQuery->where('journal_entry_lines.project_id', $selectedProject->id);
        }

        $expenses = $expenseQuery->selectRaw('
                chart_of_accounts.name as account_name,
                chart_of_accounts.code as account_code,
                SUM(CASE WHEN side = "debit" THEN amount ELSE -amount END) as total_amount
            ')
            ->groupBy('chart_of_accounts.id', 'chart_of_accounts.name', 'chart_of_accounts.code')
            ->having('total_amount', '>', 0)
            ->get();

        // 2. Fetch Income Records by Site
        $incomeRecordsQuery = IncomeRecord::with(['project', 'bankAccount', 'chartOfAccount'])
            ->where('status', '!=', 'cancelled')
            ->where(function ($q) use ($startDate, $endDate) {
                $q->whereBetween('income_date', [$startDate, $endDate])
                  ->orWhere(function ($sub) use ($startDate, $endDate) {
                      $sub->whereNull('income_date')->whereBetween('receipt_date', [$startDate, $endDate]);
                  });
            });

        if ($selectedProject) {
            $incomeRecordsQuery->where('project_id', $selectedProject->id);
        }

        $incomeRecords = $incomeRecordsQuery->latest('income_date')->latest('id')->get();
        $totalSiteIncome = (float) $incomeRecords->sum('amount');

        // 3. Multi-Site Comparison Matrix (Expenses vs Income per Project Site)
        $siteComparison = $projects->map(function ($proj) use ($startDate, $endDate) {
            // Realized Income for this site from IncomeRecord
            $siteIncome = (float) IncomeRecord::where('project_id', $proj->id)
                ->where('status', '!=', 'cancelled')
                ->where(function ($q) use ($startDate, $endDate) {
                    $q->whereBetween('income_date', [$startDate, $endDate])
                      ->orWhere(function ($sub) use ($startDate, $endDate) {
                          $sub->whereNull('income_date')->whereBetween('receipt_date', [$startDate, $endDate]);
                      });
                })
                ->sum('amount');

            $incomeCount = IncomeRecord::where('project_id', $proj->id)
                ->where('status', '!=', 'cancelled')
                ->where(function ($q) use ($startDate, $endDate) {
                    $q->whereBetween('income_date', [$startDate, $endDate])
                      ->orWhere(function ($sub) use ($startDate, $endDate) {
                          $sub->whereNull('income_date')->whereBetween('receipt_date', [$startDate, $endDate]);
                      });
                })
                ->count();

            // Real Expenses for this site
            $cashExp = 0.0;
            if (\Illuminate\Support\Facades\Schema::hasTable('expense_requests')) {
                $cashExp += (float) ExpenseRequest::where('project_id', $proj->id)
                    ->whereIn('status', [ExpenseRequest::STATUS_PAID, 'approved', 'assigned', 'reviewed'])
                    ->whereDate('created_at', '>=', $startDate)
                    ->whereDate('created_at', '<=', $endDate)
                    ->sum('amount');
            }
            if (\Illuminate\Support\Facades\Schema::hasTable('expenses')) {
                $cashExp += (float) Expense::where('project_id', $proj->id)
                    ->whereDate('expense_date', '>=', $startDate)
                    ->whereDate('expense_date', '<=', $endDate)
                    ->sum('amount');
            }
            if (\Illuminate\Support\Facades\Schema::hasTable('procurement_payments')) {
                $cashExp += (float) ProcurementPayment::where('status', 'paid')
                    ->whereHas('purchaseRequest', fn($pq) => $pq->where('project_id', $proj->id))
                    ->whereDate('payment_date', '>=', $startDate)
                    ->whereDate('payment_date', '<=', $endDate)
                    ->sum('amount');
            }

            // Fallback to journal lines if available
            if ($cashExp == 0 && \Illuminate\Support\Facades\Schema::hasColumn('journal_entry_lines', 'project_id')) {
                $journalExp = (float) JournalEntryLine::join('journal_entries', 'journal_entry_lines.journal_entry_id', '=', 'journal_entries.id')
                    ->join('chart_of_accounts', 'journal_entry_lines.account_id', '=', 'chart_of_accounts.id')
                    ->where('chart_of_accounts.type', 'expense')
                    ->where('journal_entries.status', 'posted')
                    ->where('journal_entry_lines.project_id', $proj->id)
                    ->whereDate('journal_entries.entry_date', '>=', $startDate)
                    ->whereDate('journal_entries.entry_date', '<=', $endDate)
                    ->sum(DB::raw('CASE WHEN side = "debit" THEN amount ELSE -amount END'));
                $cashExp = max(0, $journalExp);
            }

            $netMargin = $siteIncome - $cashExp;
            $marginPct = $siteIncome > 0 ? round(($netMargin / $siteIncome) * 100, 1) : ($cashExp > 0 ? -100 : 0);

            return [
                'project'      => $proj,
                'income'       => $siteIncome,
                'income_count' => $incomeCount,
                'expense'      => $cashExp,
                'net_margin'   => $netMargin,
                'margin_pct'   => $marginPct,
                'status'       => $netMargin >= 0 ? 'profitable' : 'loss',
            ];
        });

        // Calculate Totals
        $glExpenseSum = (float) $expenses->sum('total_amount');
        if ($selectedProject) {
            $selectedSiteStat = $siteComparison->firstWhere('project.id', $selectedProject->id);
            $grandTotalExpense = ($selectedSiteStat && $selectedSiteStat['expense'] > 0) ? $selectedSiteStat['expense'] : $glExpenseSum;
        } else {
            $comparisonTotalExp = (float) $siteComparison->sum('expense');
            $grandTotalExpense = $comparisonTotalExp > 0 ? $comparisonTotalExp : $glExpenseSum;
        }

        $allSitesIncome = (float) $siteComparison->sum('income');
        $grandTotalIncome = $selectedProject ? $totalSiteIncome : $allSitesIncome;
        $netOperatingPosition = $grandTotalIncome - $grandTotalExpense;

        return view('finance.reports.expense_by_site', compact(
            'expenses',
            'projects',
            'selectedProject',
            'incomeRecords',
            'siteComparison',
            'grandTotalIncome',
            'grandTotalExpense',
            'netOperatingPosition',
            'startDate',
            'endDate'
        ));
    }
}
