<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Project;
use App\Models\User;
use App\Models\Inventory;
use App\Models\Transfer;
use App\Models\DeliveryReceipt;
use App\Models\MaterialRequest;

class DashboardController extends Controller
{
    // ─── Safely get a count from any model to avoid crashes on missing tables ───
    private function safe(callable $fn, $default = 0)
    {
        try {
            return $fn();
        } catch (\Throwable $e) {
            return $default;
        }
    }

    /**
     * Build latest market / product price map for all products.
     * Priority:
     * 1) Latest price from material_prices table (effective_date desc)
     * 2) Unit price from products catalog (products.unit_price)
     * 3) Inventory unit cost from stores (inventory.unit_cost)
     * 4) Latest purchase request item estimated cost (purchase_request_items.estimated_unit_cost)
     */
    public function getLatestMaterialPriceMap(): array
    {
        $prices = [];
        try {
            if (\Illuminate\Support\Facades\Schema::hasTable('products')) {
                $prices = \Illuminate\Support\Facades\DB::table('products')
                    ->whereNull('deleted_at')
                    ->pluck('unit_price', 'id')
                    ->map(fn($v) => (float)$v)
                    ->toArray();
            }

            if (\Illuminate\Support\Facades\Schema::hasTable('material_prices')) {
                $latestMatPrices = \Illuminate\Support\Facades\DB::table('material_prices')
                    ->select('product_id', 'price')
                    ->whereIn('id', function ($q) {
                        $q->select(\Illuminate\Support\Facades\DB::raw('MAX(id)'))
                          ->from('material_prices')
                          ->groupBy('product_id');
                    })
                    ->get();

                foreach ($latestMatPrices as $mp) {
                    if ((float)$mp->price > 0) {
                        $prices[$mp->product_id] = (float)$mp->price;
                    }
                }
            }

            if (\Illuminate\Support\Facades\Schema::hasTable('inventory')) {
                $invCosts = \Illuminate\Support\Facades\DB::table('inventory')
                    ->select('product_id', \Illuminate\Support\Facades\DB::raw('MAX(unit_cost) as max_cost'))
                    ->where('unit_cost', '>', 0)
                    ->groupBy('product_id')
                    ->pluck('max_cost', 'product_id');

                foreach ($invCosts as $prodId => $cost) {
                    if (empty($prices[$prodId]) || $prices[$prodId] <= 0) {
                        $prices[$prodId] = (float)$cost;
                    }
                }
            }

            if (\Illuminate\Support\Facades\Schema::hasTable('purchase_request_items')) {
                $prCosts = \Illuminate\Support\Facades\DB::table('purchase_request_items')
                    ->select('product_id', \Illuminate\Support\Facades\DB::raw('MAX(estimated_unit_cost) as pr_cost'))
                    ->where('estimated_unit_cost', '>', 0)
                    ->groupBy('product_id')
                    ->pluck('pr_cost', 'product_id');

                foreach ($prCosts as $prodId => $cost) {
                    if (empty($prices[$prodId]) || $prices[$prodId] <= 0) {
                        $prices[$prodId] = (float)$cost;
                    }
                }
            }
        } catch (\Throwable $e) {}

        return $prices;
    }

    // ─── Admin ──────────────────────────────────────────────────────────────────
    public function admin()
    {
        $kpi = [
            'total_projects'   => $this->safe(fn() => \App\Models\Project::where('status', 'active')->count()),
            'total_employees'  => $this->safe(fn() => \App\Models\Employee::where('status', 'active')->count()),
            'monthly_expenses' => $this->safe(function() {
                $now = now();
                $direct = (float) (\App\Models\Expense::whereMonth('expense_date', $now->month)
                    ->whereYear('expense_date', $now->year)
                    ->sum('amount') ?? 0);

                $paidRequests = (float) (\App\Models\ExpenseRequest::where('status', \App\Models\ExpenseRequest::STATUS_PAID)
                    ->where(function($q) use ($now) {
                        $q->where(function($sq) use ($now) {
                            $sq->whereNotNull('paid_at')
                               ->whereMonth('paid_at', $now->month)
                               ->whereYear('paid_at', $now->year);
                        })->orWhere(function($sq) use ($now) {
                            $sq->whereNull('paid_at')
                               ->whereMonth('updated_at', $now->month)
                               ->whereYear('updated_at', $now->year);
                        })->orWhere(function($sq) use ($now) {
                            $sq->whereNull('paid_at')
                               ->whereMonth('created_at', $now->month)
                               ->whereYear('created_at', $now->year);
                        });
                    })
                    ->sum('amount') ?? 0);

                return $direct + $paidRequests;
            }, 0),
            'inventory_value'  => $this->safe(function() {
                return \Illuminate\Support\Facades\DB::table('inventory')
                    ->join('products', 'inventory.product_id', '=', 'products.id')
                    ->whereNull('products.deleted_at')
                    ->sum(\Illuminate\Support\Facades\DB::raw('inventory.quantity_on_hand * COALESCE(
                        inventory.unit_cost,
                        (SELECT price FROM material_prices WHERE product_id = products.id ORDER BY effective_date DESC, id DESC LIMIT 1),
                        (SELECT unit_price FROM purchase_order_items WHERE product_id = products.id ORDER BY id DESC LIMIT 1),
                        products.unit_price,
                        0
                    )'));
            }),
        ];

        $usersByRole = $this->safe(fn() =>
            User::join('model_has_roles', 'users.id', '=', 'model_has_roles.model_id')
                ->join('roles', 'model_has_roles.role_id', '=', 'roles.id')
                ->select('roles.name', DB::raw('count(*) as total'))
                ->groupBy('roles.name')->get(),
            collect()
        );

        $projectBudgets = $this->safe(fn() =>
            Project::withSum('budgets as budgeted_total', 'budgeted_amount')
                   ->withSum('budgets as actual_total', 'actual_amount')
                   ->take(5)->get(),
            collect()
        );

        $activityLogs = $this->safe(fn() => \App\Models\ActivityLog::with('user')->latest()->take(20)->get(), collect());
        
        $unassignedUsers = $this->safe(fn() => User::whereDoesntHave('roles')->with('employee')->latest()->get(), collect());
        
        $ticketStats = $this->safe(fn() => [
            'open' => \App\Models\SupportTicket::where('status', 'open')->count(),
            'in_progress' => \App\Models\SupportTicket::where('status', 'in_progress')->count(),
            'resolved' => \App\Models\SupportTicket::where('status', 'resolved')->count(),
            'total' => \App\Models\SupportTicket::count(),
        ], ['open' => 0, 'in_progress' => 0, 'resolved' => 0, 'total' => 0]);

        $recentTickets = $this->safe(fn() => \App\Models\SupportTicket::with('user')->latest()->take(5)->get(), collect());

        return view('dashboard.admin', compact('kpi', 'usersByRole', 'projectBudgets', 'activityLogs', 'unassignedUsers', 'ticketStats', 'recentTickets'));
    }

    // ─── GM ─────────────────────────────────────────────────────────────────────
    public function gm()
    {
        // 1. Pending GM Purchase Requests (Decisions needed)
        $pendingGmPrs = $this->safe(function () {
            return \App\Models\PurchaseRequest::with([
                    'project', 'requestedBy', 'supplier',
                    'proformaInvoices' => fn($q) => $q->where('gm_selected', true)->orWhere('is_selected', true),
                    'marketingVariance', 'items'
                ])
                ->where('status', \App\Models\PurchaseRequest::STATUS_PENDING_GM)
                ->latest()
                ->get();
        }, collect());

        // 2. Unassigned Lifecycle Stage PRs (Auto-escalated to Admin / GM)
        $unassignedStagePrs = $this->safe(function () {
            return \App\Models\PurchaseRequest::with(['project', 'requestedBy'])
                ->where('current_owner_role', 'global_admin')
                ->whereNotIn('status', [
                    \App\Models\PurchaseRequest::STATUS_COMPLETED,
                    \App\Models\PurchaseRequest::STATUS_CANCELLED,
                    \App\Models\PurchaseRequest::STATUS_REJECTED
                ])
                ->latest()
                ->take(8)
                ->get();
        }, collect());

        // 3. Pending GM Expense Requests
        $pendingGmExpenses = $this->safe(function () {
            return \App\Models\ExpenseRequest::with(['project', 'requester'])
                ->where('status', \App\Models\ExpenseRequest::STATUS_PENDING_GM)
                ->latest()
                ->take(10)
                ->get();
        }, collect());

        // 4. Pending Employee Approvals
        $pendingEmployees = $this->safe(function () {
            return \App\Models\Employee::where(function($q) {
                $q->where('is_approved_by_gm', false)->orWhereNull('is_approved_by_gm');
            })->latest()->take(10)->get();
        }, collect());

        // 5. Pending Payroll, Loan Advances & Leaves
        $pendingPayrollCount = $this->safe(fn() => \Illuminate\Support\Facades\DB::table('payrolls')->where('status', 'pending')->count(), 0);
        $pendingLoansCount   = $this->safe(fn() => \App\Models\EmployeeAdvance::where('status', 'pending')->count(), 0);
        $pendingLeavesCount  = $this->safe(fn() => \App\Models\LeaveRequest::where('status', 'pending')->count(), 0);

        $kpi = [
            'active_projects'        => $this->safe(fn() => \App\Models\Project::where('status', 'active')->count()),
            'total_contract_value'   => $this->safe(fn() => \App\Models\Project::sum('contract_value'), 0),
            'budget_utilization'     => $this->safe(function() {
                $contractValue = \App\Models\Project::sum('contract_value');
                $expenseValue = (\Illuminate\Support\Facades\DB::table('expenses')->sum('amount') ?? 0)
                              + (\App\Models\ExpenseRequest::where('status', \App\Models\ExpenseRequest::STATUS_PAID)->sum('amount') ?? 0);
                return $contractValue > 0 ? round(($expenseValue / $contractValue) * 100, 1) : 0;
            }),
            'pending_gm_prs'         => $pendingGmPrs->count(),
            'pending_gm_prs_amount'  => (float)$pendingGmPrs->sum(fn($pr) => (float)($pr->direct_buy_amount ?: $pr->items->sum('estimated_total'))),
            'unassigned_prs_count'   => $unassignedStagePrs->count(),
            'pending_gm_expenses'    => $pendingGmExpenses->count(),
            'pending_gm_expenses_amount' => (float)$pendingGmExpenses->sum('amount'),
            'pending_approvals'      => $pendingEmployees->count(),
            'total_employees'        => $this->safe(fn() => \App\Models\Employee::where('status', 'active')->count(), 0),
            'pending_expenses'       => $pendingGmExpenses->count(),
            'pending_payroll'        => $pendingPayrollCount,
            'pending_loans'          => $pendingLoansCount,
            'pending_leaves'         => $pendingLeavesCount,
            'open_issues'            => $this->safe(fn() => \App\Models\Issue::where('status', 'open')->count(), 0),
        ];

        $projectStatus = $this->safe(fn() =>
            Project::select('status', DB::raw('count(*) as total'))->groupBy('status')->get(),
            collect()
        );

        $recentProjects = $this->safe(fn() => Project::latest()->take(5)->get(), collect());
        $recentExpenses = $this->safe(fn() => \Illuminate\Support\Facades\DB::table('expenses')
            ->orderByDesc('created_at')->take(5)->get(), collect());

        // ── 1. Latest material price map across the catalog ─────────────────────
        $latestPriceMap = $this->getLatestMaterialPriceMap();

        // ── 2. Project Expenses (cash + daily material consumption @ latest price) ──
        $projectExpenses = $this->safe(function () use ($latestPriceMap) {
            $usagesByProject = collect();
            if (\Illuminate\Support\Facades\Schema::hasTable('material_usages') && \Illuminate\Support\Facades\Schema::hasTable('material_usage_items')) {
                $usagesByProject = \Illuminate\Support\Facades\DB::table('material_usages')
                    ->join('material_usage_items', 'material_usages.id', '=', 'material_usage_items.material_usage_id')
                    ->whereNotIn('material_usages.status', ['cancelled', 'rejected'])
                    ->select(
                        'material_usages.project_id',
                        'material_usage_items.product_id',
                        'material_usage_items.unit_cost as stored_unit_cost',
                        \Illuminate\Support\Facades\DB::raw('COALESCE(material_usage_items.quantity, material_usage_items.used_quantity, 0) as qty')
                    )
                    ->get()
                    ->groupBy('project_id');
            }

            return \App\Models\Project::select('projects.id', 'projects.name', 'projects.code',
                    'projects.contract_value', 'projects.budget_allocated', 'projects.status')
                ->get()
                ->map(function ($project) use ($usagesByProject, $latestPriceMap) {
                    $cashExpenses = (float) \App\Models\ExpenseRequest::where('project_id', $project->id)
                        ->whereIn('status', [\App\Models\ExpenseRequest::STATUS_PAID, 'approved', 'assigned', 'reviewed'])
                        ->sum('amount');

                    // Material consumption from daily consumption logs priced with latest price
                    $projectItems = $usagesByProject->get($project->id, collect());
                    $materialCost = (float) $projectItems->sum(function ($item) use ($latestPriceMap) {
                        $qty = (float) $item->qty;
                        $unitPrice = (float) ($latestPriceMap[$item->product_id] ?? ((float)$item->stored_unit_cost ?: 0));
                        return $qty * $unitPrice;
                    });

                    $totalExpense     = $cashExpenses + $materialCost;
                    $contractValue    = (float) $project->contract_value;
                    $budgetAllocated  = (float) $project->budget_allocated;
                    $utilization      = $budgetAllocated > 0 ? min(999, round(($totalExpense / $budgetAllocated) * 100, 1)) : 0;
                    $budgetStatus     = $utilization >= 100 ? 'danger' : ($utilization >= 80 ? 'warning' : 'success');

                    return [
                        'id'               => $project->id,
                        'name'             => $project->name,
                        'code'             => $project->code,
                        'status'           => $project->status,
                        'contract_value'   => $contractValue,
                        'budget_allocated' => $budgetAllocated,
                        'cash_expenses'    => $cashExpenses,
                        'material_cost'    => $materialCost,
                        'total_expense'    => $totalExpense,
                        'remaining_budget' => max(0, $budgetAllocated - $totalExpense),
                        'utilization'      => $utilization,
                        'budget_status'    => $budgetStatus,
                    ];
                })
                ->sortByDesc('total_expense')
                ->values();
        }, collect());

        // ── 3. Material Consumption Report (top 20 materials across projects @ latest price) ──
        $materialConsumptionReport = $this->safe(function () use ($latestPriceMap) {
            if (!\Illuminate\Support\Facades\Schema::hasTable('material_usages') || !\Illuminate\Support\Facades\Schema::hasTable('material_usage_items')) {
                return collect();
            }

            $rawItems = \Illuminate\Support\Facades\DB::table('material_usage_items')
                ->join('material_usages', 'material_usages.id', '=', 'material_usage_items.material_usage_id')
                ->join('products', 'products.id', '=', 'material_usage_items.product_id')
                ->join('projects', 'projects.id', '=', 'material_usages.project_id')
                ->whereNotIn('material_usages.status', ['cancelled', 'rejected'])
                ->whereNull('products.deleted_at')
                ->select(
                    'projects.id as project_id',
                    'projects.name as project_name',
                    'projects.code as project_code',
                    'products.id as product_id',
                    'products.name as product_name',
                    'products.unit as product_unit',
                    'material_usage_items.unit_cost as stored_unit_cost',
                    \Illuminate\Support\Facades\DB::raw('COALESCE(material_usage_items.quantity, material_usage_items.used_quantity, 0) as qty')
                )
                ->get();

            return $rawItems
                ->groupBy(fn($i) => $i->project_id . '-' . $i->product_id)
                ->map(function ($group) use ($latestPriceMap) {
                    $first = $group->first();
                    $totalQty = (float) $group->sum('qty');
                    $latestPrice = (float) ($latestPriceMap[$first->product_id] ?? ((float)$first->stored_unit_cost ?: 0));
                    $totalCost = round($totalQty * $latestPrice, 2);

                    return (object) [
                        'project_id'   => $first->project_id,
                        'project_name' => $first->project_name,
                        'project_code' => $first->project_code,
                        'product_id'   => $first->product_id,
                        'product_name' => $first->product_name,
                        'product_unit' => $first->product_unit,
                        'total_qty'    => $totalQty,
                        'avg_unit_cost'=> $latestPrice,
                        'total_cost'   => $totalCost,
                    ];
                })
                ->filter(fn($row) => $row->total_qty > 0)
                ->sortByDesc('total_cost')
                ->take(20)
                ->values();
        }, collect());

        // ── 4. Monthly Expense Trend (last 6 months, cash + daily material consumption @ latest price) ──
        $monthlyExpenseTrend = $this->safe(function () use ($latestPriceMap) {
            $months = [];
            for ($i = 5; $i >= 0; $i--) {
                $date = now()->subMonths($i);
                $m = $date->month;
                $y = $date->year;

                $cashExp = (float) \App\Models\ExpenseRequest::where('status', \App\Models\ExpenseRequest::STATUS_PAID)
                    ->whereMonth('created_at', $m)->whereYear('created_at', $y)->sum('amount');

                $matCost = 0;
                if (\Illuminate\Support\Facades\Schema::hasTable('material_usages') && \Illuminate\Support\Facades\Schema::hasTable('material_usage_items')) {
                    $matItems = \Illuminate\Support\Facades\DB::table('material_usages')
                        ->join('material_usage_items', 'material_usages.id', '=', 'material_usage_items.material_usage_id')
                        ->whereNotIn('material_usages.status', ['cancelled', 'rejected'])
                        ->whereMonth('material_usages.usage_date', $m)
                        ->whereYear('material_usages.usage_date', $y)
                        ->select(
                            'material_usage_items.product_id',
                            'material_usage_items.unit_cost as stored_unit_cost',
                            \Illuminate\Support\Facades\DB::raw('COALESCE(material_usage_items.quantity, material_usage_items.used_quantity, 0) as qty')
                        )
                        ->get();

                    $matCost = (float) $matItems->sum(function ($item) use ($latestPriceMap) {
                        $qty = (float) $item->qty;
                        $unitPrice = (float) ($latestPriceMap[$item->product_id] ?? ((float)$item->stored_unit_cost ?: 0));
                        return $qty * $unitPrice;
                    });
                }

                $months[] = [
                    'label'    => $date->format('M Y'),
                    'cash'     => $cashExp,
                    'material' => $matCost,
                    'total'    => $cashExp + $matCost,
                ];
            }
            return $months;
        }, []);

        // ── Expense Category Breakdown ────────────────────────────────────────────
        $expenseCategoryBreakdown = $this->safe(function () {
            return \App\Models\ExpenseRequest::whereIn('status', [\App\Models\ExpenseRequest::STATUS_PAID, 'approved'])
                ->select('category', \Illuminate\Support\Facades\DB::raw('SUM(amount) as total'), \Illuminate\Support\Facades\DB::raw('COUNT(*) as count'))
                ->groupBy('category')
                ->orderByDesc('total')
                ->limit(8)
                ->get();
        }, collect());

        // ── KPI totals for material & cash ────────────────────────────────────────
        $kpi['total_material_cost'] = (float) $projectExpenses->sum('material_cost');
        $kpi['total_cash_expense'] = (float) $projectExpenses->sum('cash_expenses');
        $kpi['budget_utilization'] = $this->safe(function () use ($kpi, $projectExpenses) {
            $contractValue = (float) ($kpi['total_contract_value'] ?? 0);
            $totalSpend = (float) $projectExpenses->sum('total_expense');
            return $contractValue > 0 ? round(($totalSpend / $contractValue) * 100, 1) : 0;
        }, 0);

        return view('dashboard.gm', compact(
            'kpi', 'projectStatus', 'recentProjects', 'pendingEmployees', 'recentExpenses',
            'projectExpenses', 'materialConsumptionReport', 'monthlyExpenseTrend', 'expenseCategoryBreakdown',
            'pendingGmPrs', 'unassignedStagePrs', 'pendingGmExpenses'
        ));
    }

    // ─── Planning (used by: planning, planning_manager, technical_manager) ───────
    public function planning()
    {
        $projects = Project::where('status', 'active')->with(['budgets'])->get();
        $erpPlans = \App\Models\ErpPlanHeader::with('project', 'creator')->latest()->take(5)->get();
        $schedules = \App\Models\Schedule::with('project')->latest()->take(5)->get();
        $takeoffs = \App\Models\TakeoffSheet::with('project', 'creator')->latest()->take(5)->get();

        // Project Budgets, Costs & Current Balance Calculations
        $totalApprovedBudget = (float) $projects->sum(function ($p) {
            $sumDetailed = (float) $p->budgets->sum('budgeted_amount');
            return $sumDetailed > 0 ? $sumDetailed : (float) $p->budget_allocated;
        });

        $totalActualExpense = (float) $projects->sum(function ($p) {
            $sumDetailed = (float) $p->budgets->sum('actual_amount');
            return $sumDetailed > 0 ? $sumDetailed : (float) $p->budget_consumed;
        });

        $totalRemainingBudget = max(0, $totalApprovedBudget - $totalActualExpense);
        $overallUtilization = $totalApprovedBudget > 0 ? min(100, round(($totalActualExpense / $totalApprovedBudget) * 100, 1)) : 0;

        // Project-by-project breakdown
        $projectFinancials = $projects->map(function ($p) {
            $budget = (float) $p->budgets->sum('budgeted_amount');
            if ($budget <= 0) {
                $budget = (float) $p->budget_allocated;
            }
            $actual = (float) $p->budgets->sum('actual_amount');
            if ($actual <= 0) {
                $actual = (float) $p->budget_consumed;
            }
            $remaining = max(0, $budget - $actual);
            $utilization = $budget > 0 ? min(100, round(($actual / $budget) * 100, 1)) : 0;
            $status = $utilization >= 100 ? 'Exceeded' : ($utilization > 80 ? 'Warning' : 'Healthy');

            return [
                'id' => $p->id,
                'name' => $p->name,
                'code' => $p->code,
                'client_name' => $p->client_name,
                'approved_budget' => $budget,
                'actual_expense' => $actual,
                'remaining_budget' => $remaining,
                'utilization' => $utilization,
                'status' => $status,
            ];
        });

        return view('dashboard.planning', compact(
            'projects', 'erpPlans', 'schedules', 'takeoffs',
            'totalApprovedBudget', 'totalActualExpense', 'totalRemainingBudget',
            'overallUtilization', 'projectFinancials'
        ));
    }

    // ─── Secretary Dashboard ─────────────────────────────────────────────────────
    public function secretary()
    {
        $user = auth()->user();

        // Letters / Correspondence
        $totalLetters        = $this->safe(fn() => \App\Models\Letter::count(), 0);
        $pendingLetters      = $this->safe(fn() => \App\Models\Letter::whereIn('status', ['pending', 'forwarded', 'in_progress'])->count(), 0);
        $closedLetters       = $this->safe(fn() => \App\Models\Letter::where('status', 'closed')->count(), 0);
        $myLettersCount      = $this->safe(fn() => \App\Models\Letter::where('created_by', $user->id)->count(), 0);
        $myLetters           = $this->safe(fn() => \App\Models\Letter::where('created_by', $user->id)->latest()->take(8)->get(), collect());
        $recentLetters       = $this->safe(fn() => \App\Models\Letter::with(['creator', 'recipients'])->latest()->take(10)->get(), collect());

        // My expense requests
        $myExpenseRequests   = $this->safe(fn() => \App\Models\ExpenseRequest::where('requested_by', $user->id)->latest()->take(6)->get(), collect());
        $myExpenseCount      = $this->safe(fn() => \App\Models\ExpenseRequest::where('requested_by', $user->id)->count(), 0);

        // Office Material Requisitions (PR)
        $myOfficeRequests    = $this->safe(fn() => \App\Models\PurchaseRequest::with(['items.product', 'hrCoordinatorApprovedBy'])
            ->where(function($q) use ($user) {
                $q->where('is_office_request', true)
                  ->orWhere('status', \App\Models\PurchaseRequest::STATUS_PENDING_HR_APPROVAL);
            })
            ->where('requested_by', $user->id)
            ->latest()
            ->take(6)
            ->get(), collect());
        $myOfficeRequestsCount = $this->safe(fn() => \App\Models\PurchaseRequest::where(function($q) use ($user) {
                $q->where('is_office_request', true)
                  ->orWhere('status', \App\Models\PurchaseRequest::STATUS_PENDING_HR_APPROVAL);
            })
            ->where('requested_by', $user->id)
            ->count(), 0);
        $pendingOfficeRequestsCount = $this->safe(fn() => \App\Models\PurchaseRequest::where(function($q) use ($user) {
                $q->where('is_office_request', true)
                  ->orWhere('status', \App\Models\PurchaseRequest::STATUS_PENDING_HR_APPROVAL);
            })
            ->where('requested_by', $user->id)
            ->where('status', \App\Models\PurchaseRequest::STATUS_PENDING_HR_APPROVAL)
            ->count(), 0);

        return view('dashboard.secretary', compact(
            'totalLetters', 'pendingLetters', 'closedLetters', 'myLettersCount',
            'myLetters', 'recentLetters', 'myExpenseRequests', 'myExpenseCount',
            'myOfficeRequests', 'myOfficeRequestsCount', 'pendingOfficeRequestsCount'
        ));
    }

    // ─── Coordinator (also: general_service) ─────────────────────────────────────
    public function coordinator()
    {
        $kpi = [
            'total_inventory_value' => $this->safe(fn() => \App\Models\Inventory::sum('total_value')),
            'schedules_today'       => $this->safe(fn() => \App\Models\Schedule::whereDate('start_date', '<=', now())->whereDate('end_date', '>=', now())->count()),
            'daily_reports_today'   => $this->safe(fn() => \App\Models\DailyReport::whereDate('report_date', now())->count()),
            'material_requests'     => $this->safe(fn() => \App\Models\MaterialRequest::where('status', 'pending')->count()),
            'pending_plans'         => $this->safe(fn() => \App\Models\ProjectPlanWorkflow::where('status', 'planning_manager_approved')->count()),
            'pending_office_reqs'   => $this->safe(fn() => \App\Models\PurchaseRequest::where(function($q) {
                $q->where('is_office_request', true)
                  ->orWhere('status', \App\Models\PurchaseRequest::STATUS_PENDING_HR_APPROVAL);
            })->where('status', \App\Models\PurchaseRequest::STATUS_PENDING_HR_APPROVAL)->count(), 0),
        ];

        $recentSchedules = $this->safe(fn() => \App\Models\Schedule::with('project')->latest()->take(5)->get(), collect());
        $recentDailyReports = $this->safe(fn() => \App\Models\DailyReport::with('project', 'creator')->latest()->take(5)->get(), collect());
        $pendingPlansFromPlanning = $this->safe(fn() => \App\Models\ProjectPlanWorkflow::with(['project', 'planningManager', 'creator'])
            ->where('status', 'planning_manager_approved')
            ->latest()
            ->take(10)
            ->get(), collect());
        $erpPlans = $this->safe(fn() => \App\Models\ErpPlanHeader::with('project', 'creator')->latest()->take(5)->get(), collect());
        $pendingOfficeRequests = $this->safe(fn() => \App\Models\PurchaseRequest::with(['items.product', 'requestedBy'])
            ->where(function($q) {
                $q->where('is_office_request', true)
                  ->orWhere('status', \App\Models\PurchaseRequest::STATUS_PENDING_HR_APPROVAL);
            })
            ->where('status', \App\Models\PurchaseRequest::STATUS_PENDING_HR_APPROVAL)
            ->latest()
            ->take(5)
            ->get(), collect());

        return view('dashboard.coordinator', compact('kpi', 'recentSchedules', 'recentDailyReports', 'pendingPlansFromPlanning', 'erpPlans', 'pendingOfficeRequests'));
    }

    // ─── General Service Hub ───────────────────────────────────────────────────
    public function generalService(Request $request)
    {
        $kpi = [
            'pending_maintenance'     => $this->safe(fn() => \App\Models\MaintenanceRequest::where('status', 'pending')->count(), 0),
            'in_progress_maintenance' => $this->safe(fn() => \App\Models\MaintenanceRequest::where('status', 'in_progress')->count(), 0),
            'resolved_this_month'     => $this->safe(fn() => \App\Models\MaintenanceRequest::where('status', 'resolved')->whereMonth('resolved_at', now()->month)->count(), 0),
            'critical_breakdowns'     => $this->safe(fn() => \App\Models\MaintenanceRequest::whereIn('urgency', ['critical', 'urgent'])->whereIn('status', ['pending', 'in_progress'])->count(), 0),
            'transfers_count'         => $this->safe(fn() => \App\Models\Transfer::whereIn('status', ['approved', 'in_transit', 'draft', 'pending'])->count(), 0),
            'assets_in_maintenance'   => $this->safe(fn() => \App\Models\FixedAssetUnit::where('status', 'maintenance')->orWhereIn('condition', ['needs_repair', 'damaged'])->count(), 0),
        ];

        $maintenanceRequests = $this->safe(fn() => \App\Models\MaintenanceRequest::with(['employee', 'reportedBy', 'assignedTo', 'fixedAssetUnit.parentAsset'])
            ->latest()
            ->take(15)
            ->get(), collect());

        $transfers = $this->safe(fn() => \App\Models\Transfer::with(['fromStore', 'toStore', 'requestedBy', 'items.product'])
            ->latest()
            ->take(10)
            ->get(), collect());

        $maintenanceAssets = $this->safe(fn() => \App\Models\FixedAssetUnit::with('parentAsset', 'assignedEmployee')
            ->where('status', 'maintenance')
            ->orWhereIn('condition', ['needs_repair', 'damaged'])
            ->latest()
            ->take(10)
            ->get(), collect());

        $staff = $this->safe(fn() => User::whereHas('roles', fn($q) => $q->whereIn('name', [
            'global_admin', 'admin', 'store_manager', 'general_service', 'coordinator'
        ]))->get(['id', 'name']), collect());

        return view('dashboard.general-service', compact('kpi', 'maintenanceRequests', 'transfers', 'maintenanceAssets', 'staff'));
    }

    // ─── Site Engineer ──────────────────────────────────────────────────────────
    public function siteEngineer()
    {
        /** @var \App\Models\User|null $user */
        $user = auth()->user();
        $assignedProjectIds = $user ? $user->projects()->pluck('projects.id') : collect();
        if ($user && $user->store && $user->store->project_id) {
            $assignedProjectIds->push($user->store->project_id);
        }
        $assignedProjectIds = $assignedProjectIds->filter()->unique();

        $todayManpowerReport = $this->safe(function() use ($user) {
            return \App\Models\ManpowerDailyReport::where('submitted_by', $user->id)
                ->whereDate('report_date', today())
                ->with(['project', 'reviewer'])
                ->first();
        });

        $recentManpowerReports = $this->safe(function() use ($user) {
            return \App\Models\ManpowerDailyReport::where('submitted_by', $user->id)
                ->with(['project', 'reviewer'])
                ->orderByDesc('report_date')
                ->take(5)
                ->get();
        }, collect());

        $assignedWorkOrders = $this->safe(function() use ($user) {
            return \App\Models\EngWorkOrder::forEngineer($user->id)
                ->with(['project', 'assignedBy'])
                ->whereNotIn('status', ['cancelled'])
                ->latest('start_datetime')
                ->take(5)
                ->get();
        }, collect());

        $recentMR = $this->safe(function() use ($user, $assignedProjectIds) {
            return \App\Models\MaterialRequest::where('requested_by', $user->id)
                ->when($assignedProjectIds->isNotEmpty(), fn($q) => $q->whereIn('project_id', $assignedProjectIds))
                ->with('project')
                ->latest()
                ->take(5)
                ->get();
        }, collect());

        $recentIssues = $this->safe(function() use ($user, $assignedProjectIds) {
            return \App\Models\Issue::where('reported_by', $user->id)
                ->orWhere(fn($q) => $q->when($assignedProjectIds->isNotEmpty(), fn($sq) => $sq->whereIn('project_id', $assignedProjectIds)))
                ->with(['project'])
                ->latest()
                ->take(5)
                ->get();
        }, collect());

        $kpi = [
            'today_manpower_count' => $this->safe(function() use ($todayManpowerReport, $user) {
                if ($todayManpowerReport) {
                    return (int)$todayManpowerReport->total_present;
                }
                return (int)\App\Models\Attendance::whereDate('attendance_date', now())
                    ->where('status', 'present')
                    ->when($user && $user->store_id, fn($q) => $q->where('store_id', $user->store_id))
                    ->count();
            }, 0),
            'manpower_status' => $todayManpowerReport ? $todayManpowerReport->status : 'not_submitted',
            'my_material_requests' => $this->safe(function() use ($user, $assignedProjectIds) {
                return \App\Models\MaterialRequest::where('requested_by', $user->id)
                    ->when($assignedProjectIds->isNotEmpty(), fn($q) => $q->whereIn('project_id', $assignedProjectIds))
                    ->count();
            }, 0),
            'pending_mr_count' => $this->safe(function() use ($user, $assignedProjectIds) {
                return \App\Models\MaterialRequest::where('requested_by', $user->id)
                    ->where('status', 'pending')
                    ->when($assignedProjectIds->isNotEmpty(), fn($q) => $q->whereIn('project_id', $assignedProjectIds))
                    ->count();
            }, 0),
            'assigned_work_orders_count' => $this->safe(function() use ($user) {
                return \App\Models\EngWorkOrder::forEngineer($user->id)
                    ->whereIn('status', ['assigned', 'in_progress'])
                    ->count();
            }, 0),
            'open_issues_count' => $this->safe(function() use ($user, $assignedProjectIds) {
                return \App\Models\Issue::where('status', 'open')
                    ->when($assignedProjectIds->isNotEmpty(), fn($q) => $q->whereIn('project_id', $assignedProjectIds))
                    ->count();
            }, 0),
            'attendance_today' => $this->safe(function() use ($user) {
                return \App\Models\Attendance::whereDate('attendance_date', now())
                    ->where('status', 'present')
                    ->when($user && $user->store_id, fn($q) => $q->where('store_id', $user->store_id))
                    ->count();
            }, 0),
            'waste_recorded' => $this->safe(function() use ($user, $assignedProjectIds) {
                return \App\Models\Waste::whereMonth('waste_date', now()->month)
                    ->when($assignedProjectIds->isNotEmpty(), fn($q) => $q->whereIn('project_id', $assignedProjectIds))
                    ->count();
            }, 0),
        ];

        return view('dashboard.site-engineer', compact(
            'kpi',
            'todayManpowerReport',
            'recentManpowerReports',
            'assignedWorkOrders',
            'recentMR',
            'recentIssues'
        ));
    }

    // ─── Foreman ────────────────────────────────────────────────────────────────
    public function foreman()
    {
        $kpi = [
            'attendance_today' => $this->safe(fn() => \App\Models\Attendance::whereDate('attendance_date', now())->where('status', 'present')->count()),
            'my_mr_pending'    => $this->safe(fn() => \App\Models\MaterialRequest::where('requested_by', auth()->id())->where('status', 'pending')->count()),
            'daily_reports'    => $this->safe(fn() => \App\Models\DailyReport::whereMonth('report_date', now()->month)->count()),
            'issues_open'      => $this->safe(fn() => \App\Models\Issue::where('status', 'open')->count()),
        ];

        $recentDaily = $this->safe(fn() => \App\Models\DailyReport::with('project')->latest()->take(5)->get(), collect());

        return view('dashboard.foreman', compact('kpi', 'recentDaily'));
    }

    // ─── Store Keeper Dashboard (Scoped strictly to assigned store/site) ───────
    public function storeKeeper()
    {
        $user = auth()->user();
        
        // Find assigned store for this user
        $assignedStore = $user->store ?? \App\Models\Store::where('manager_id', $user->id)->first();
        $storeId = $assignedStore?->id;

        $storeInventory         = collect();
        $lowStockItems          = collect();
        $incomingTransfers      = collect();
        $outgoingTransfers      = collect();
        $recentTransfers        = collect();
        $materialRequests       = collect();
        $recentDeliveryReceipts = collect();
        $recentSlips            = collect();

        $kpi = [
            'total_items'       => 0,
            'total_stock_qty'   => 0,
            'low_stock_count'   => 0,
            'pending_incoming'  => 0,
            'pending_outgoing'  => 0,
            'total_transfers'   => 0,
            'pending_requests'  => 0,
            'completed_today'   => 0,
        ];

        if ($storeId) {
            // Inventory & Stock counts
            $kpi['total_items']     = $this->safe(fn() => \App\Models\Inventory::where('store_id', $storeId)->count(), 0);
            $kpi['total_stock_qty'] = (float) $this->safe(fn() => \App\Models\Inventory::where('store_id', $storeId)->sum('quantity_on_hand'), 0);
            
            $lowStockQuery = \App\Models\Inventory::with('product')
                ->where('store_id', $storeId)
                ->whereColumn('quantity_on_hand', '<=', 'min_stock');
            $kpi['low_stock_count'] = $this->safe(fn() => (clone $lowStockQuery)->count(), 0);
            $lowStockItems          = $this->safe(fn() => $lowStockQuery->take(8)->get(), collect());

            $storeInventory = $this->safe(fn() => \App\Models\Inventory::with('product')
                ->where('store_id', $storeId)
                ->orderBy('quantity_on_hand', 'desc')
                ->take(12)
                ->get(), collect());

            // Site Transfers for this store
            $kpi['pending_incoming'] = $this->safe(fn() => \App\Models\Transfer::where('to_store_id', $storeId)
                ->whereIn('status', ['draft', 'pending', 'in_transit', 'approved'])->count(), 0);
            $kpi['pending_outgoing'] = $this->safe(fn() => \App\Models\Transfer::where('from_store_id', $storeId)
                ->whereIn('status', ['draft', 'pending', 'in_transit', 'approved'])->count(), 0);
            $kpi['total_transfers']  = $this->safe(fn() => \App\Models\Transfer::where(function($q) use ($storeId) {
                $q->where('from_store_id', $storeId)->orWhere('to_store_id', $storeId);
            })->count(), 0);

            $incomingTransfers = $this->safe(fn() => \App\Models\Transfer::with(['fromStore', 'toStore', 'requestedBy', 'items.product'])
                ->where('to_store_id', $storeId)
                ->latest()
                ->take(8)
                ->get(), collect());

            $outgoingTransfers = $this->safe(fn() => \App\Models\Transfer::with(['fromStore', 'toStore', 'requestedBy', 'items.product'])
                ->where('from_store_id', $storeId)
                ->latest()
                ->take(8)
                ->get(), collect());

            $recentTransfers = $this->safe(fn() => \App\Models\Transfer::with(['fromStore', 'toStore', 'requestedBy', 'items.product'])
                ->where(function($q) use ($storeId) {
                    $q->where('from_store_id', $storeId)->orWhere('to_store_id', $storeId);
                })
                ->latest()
                ->take(10)
                ->get(), collect());

            // Site Material Requests for this store
            $kpi['pending_requests'] = $this->safe(fn() => \App\Models\MaterialRequest::where('destination_store_id', $storeId)->whereIn('status', ['pending', 'sent_to_store_manager'])->count(), 0);
            $materialRequests = $this->safe(fn() => \App\Models\MaterialRequest::with(['project', 'requestedBy', 'items.product'])
                ->where('destination_store_id', $storeId)
                ->latest()
                ->take(8)
                ->get(), collect());

            // Delivery Receipts for this store
            $recentDeliveryReceipts = $this->safe(fn() => \App\Models\DeliveryReceipt::with(['supplier', 'purchaseOrder'])
                ->where('store_id', $storeId)
                ->latest()
                ->take(6)
                ->get(), collect());
        }

        return view('dashboard.store-keeper', compact(
            'assignedStore', 'storeId', 'kpi',
            'storeInventory', 'lowStockItems',
            'incomingTransfers', 'outgoingTransfers', 'recentTransfers',
            'materialRequests', 'recentDeliveryReceipts'
        ));
    }

    // ─── Store Manager ──────────────────────────────────────────────────────────
    public function storeManager()
    {
        $kpi = [
            'total_items'       => $this->safe(fn() => Inventory::count(), 0),
            'total_value'       => $this->safe(fn() => Inventory::sum(DB::raw('quantity_on_hand * unit_cost')), 0),
            'low_stock_items'   => $this->safe(fn() => Inventory::whereColumn('quantity_on_hand', '<=', 'min_stock')->count(), 0),
            'pending_transfers' => $this->safe(fn() => Transfer::where('status', 'draft')->count(), 0),
            'received_today'    => $this->safe(fn() => DeliveryReceipt::where(function($q) {
                $q->whereDate('received_date', today())
                  ->orWhereDate('created_at', today());
            })->count(), 0),
            'pending_requests'  => $this->safe(fn() => MaterialRequest::where('status', 'pending')->count(), 0),
        ];

        // Total inventory value now (qty × unit_cost) per store
        $inventoryValueByStore = $this->safe(fn() => DB::table('inventory')
            ->join('stores', 'inventory.store_id', '=', 'stores.id')
            ->where('stores.is_active', true)
            ->selectRaw('stores.name as store_name, SUM(inventory.quantity_on_hand * inventory.unit_cost) as total_value, COUNT(*) as product_count')
            ->groupBy('stores.id', 'stores.name')
            ->orderByDesc('total_value')
            ->get(), collect());

        // Today's inventory value change (from manual adjustments made today)
        $todayAdjustmentValue = $this->safe(fn() => DB::table('inventory_movements')
            ->join('inventory', 'inventory_movements.inventory_id', '=', 'inventory.id')
            ->whereDate('inventory_movements.created_at', today())
            ->where('inventory_movements.type', 'adjustment')
            ->selectRaw('SUM(inventory_movements.quantity * inventory.unit_cost) as delta_value')
            ->value('delta_value') ?? 0, 0);

        // Money received this month (from delivery receipts / purchases)
        $monthlyReceiptsValue = $this->safe(fn() => DB::table('inventory_movements')
            ->join('inventory', 'inventory_movements.inventory_id', '=', 'inventory.id')
            ->whereMonth('inventory_movements.created_at', now()->month)
            ->whereYear('inventory_movements.created_at', now()->year)
            ->where('inventory_movements.type', 'in')
            ->selectRaw('SUM(ABS(inventory_movements.quantity) * inventory.unit_cost) as total')
            ->value('total') ?? 0, 0);

        // Last month receipts for comparison
        $lastMonthReceiptsValue = $this->safe(fn() => DB::table('inventory_movements')
            ->join('inventory', 'inventory_movements.inventory_id', '=', 'inventory.id')
            ->whereMonth('inventory_movements.created_at', now()->subMonth()->month)
            ->whereYear('inventory_movements.created_at', now()->subMonth()->year)
            ->where('inventory_movements.type', 'in')
            ->selectRaw('SUM(ABS(inventory_movements.quantity) * inventory.unit_cost) as total')
            ->value('total') ?? 0, 0);

        // Top 10 inventory items by value
        $topValueItems = $this->safe(fn() => DB::table('inventory')
            ->join('products', 'inventory.product_id', '=', 'products.id')
            ->join('stores', 'inventory.store_id', '=', 'stores.id')
            ->where('stores.is_active', true)
            ->selectRaw('products.name as product_name, products.sku, products.unit, stores.name as store_name,
                         inventory.quantity_on_hand, inventory.unit_cost,
                         (inventory.quantity_on_hand * inventory.unit_cost) as line_value')
            ->orderByDesc('line_value')
            ->limit(10)
            ->get(), collect());

        $allInventory = $this->safe(fn() => Inventory::with('product', 'store')
            ->whereHas('store', fn($q) => $q->where('is_active', true))
            ->orderBy('quantity_on_hand', 'desc')
            ->take(15)
            ->get(), collect());

        $lowStockItems = $this->safe(fn() => Inventory::with('product', 'store')
            ->whereColumn('quantity_on_hand', '<=', 'min_stock')
            ->take(8)
            ->get(), collect());

        $lowStock = $lowStockItems;

        $transfersToGeneralService = $this->safe(fn() => \App\Models\Transfer::with(['fromStore', 'toStore', 'requestedBy', 'items.product'])
            ->where('status', 'approved')
            ->latest()
            ->take(10)
            ->get(), collect());

        $materialRequests = $this->safe(fn() => \App\Models\MaterialRequest::with(['project', 'requestedBy', 'items.product'])
            ->where('status', 'pending')
            ->latest()
            ->take(10)
            ->get(), collect());

        $stores = $this->safe(fn() => \App\Models\Store::where('is_active', true)->orderBy('name')->get(), collect());

        return view('store-manager.dashboard', compact(
            'kpi', 'lowStockItems', 'lowStock', 'allInventory',
            'inventoryValueByStore', 'todayAdjustmentValue',
            'monthlyReceiptsValue', 'lastMonthReceiptsValue', 'topValueItems',
            'transfersToGeneralService', 'materialRequests', 'stores'
        ));
    }

    // ─── HR / HR Officer ────────────────────────────────────────────────────────
    public function hr()
    {
        $kpi = [
            'total_employees'  => $this->safe(fn() => \App\Models\Employee::where('status', 'active')->count(), 0),
            'present_today'    => $this->safe(fn() => \App\Models\Attendance::whereDate('attendance_date', now())->where('status', 'present')->count(), 0),
            'pending_payroll'  => $this->safe(fn() => \App\Models\Payroll::where('status', 'pending')->count(), 0),
            'open_requests'    => $this->safe(fn() => \App\Models\ManpowerRequest::where('status', 'pending')->count(), 0),
            'total_letters'    => $this->safe(fn() => \App\Models\EmployeeLetter::count(), 0),
            'warning_letters'  => $this->safe(fn() => \App\Models\EmployeeLetter::whereIn('letter_type', ['first_warning', 'second_warning', 'final_warning', 'show_cause'])->count(), 0),
        ];

        $recentPayrolls = $this->safe(fn() => \App\Models\Payroll::with('employee')->latest()->take(5)->get(), collect());
        $recentLetters  = $this->safe(fn() => \App\Models\EmployeeLetter::with(['employee', 'issuer'])->latest('issued_date')->take(6)->get(), collect());

        return view('dashboard.hr', compact('kpi', 'recentPayrolls', 'recentLetters'));
    }

    // ─── Finance / Finance Head ─────────────────────────────────────────────────
    public function finance()
    {
        $user = auth()->user();
        $isFinanceHead = $user && $user->hasAnyRole(['Finance head', 'finance_head', 'admin', 'global_admin']);

        // ── Cash & Bank Accounts (from Chart of Accounts where category is Cash and Bank) ──
        $cashAndBankCoas = $this->safe(function() {
            return \App\Models\ChartOfAccount::with('manager')
                ->where('is_active', true)
                ->where(function($q) {
                    $q->where('subtype', 'Cash and Bank')
                      ->orWhere('subtype', 'like', '%Cash%')
                      ->orWhere('subtype', 'like', '%Bank%')
                      ->orWhere('name', 'like', '%Bank%')
                      ->orWhere('name', 'like', '%Cash%')
                      ->orWhere('code', 'like', '10%');
                })
                ->orderBy('code')
                ->get();
        }, collect());

        $totalCashBankBalance = (float) $cashAndBankCoas->sum('current_balance');

        $kpi = [
            'total_income'    => $this->safe(fn() => (float) \App\Models\Payment::sum('amount') + (float) \Illuminate\Support\Facades\DB::table('client_ipcs')->where('status', 'paid')->sum('gross_amount'), 0),
            'total_expense'   => $this->safe(fn() => (float) \Illuminate\Support\Facades\DB::table('expenses')->sum('amount') + (float) \App\Models\ExpenseRequest::where('status', 'paid')->sum('amount') + (float) \Illuminate\Support\Facades\DB::table('payrolls')->sum('net_salary'), 0),
            'cash_balance'    => $totalCashBankBalance,
            'pending_payments'=> $this->safe(fn() => \Illuminate\Support\Facades\DB::table('expenses')->where('status', 'pending')->count() + \App\Models\ExpenseRequest::whereIn('status', ['pending', 'reviewed', 'approved', 'assigned'])->count() + \Illuminate\Support\Facades\DB::table('payrolls')->where('status', 'pending')->count(), 0),
        ];

        // 6-Month Real Monthly Income vs Expense Data
        $monthlyAnalytics = $this->safe(function() {
            $labels = [];
            $incomes = [];
            $expenses = [];
            for ($i = 5; $i >= 0; $i--) {
                $monthDate = \Carbon\Carbon::now()->subMonths($i);
                $labels[] = $monthDate->format('M');
                $inc = (float) \Illuminate\Support\Facades\DB::table('payments')
                    ->whereMonth('payment_date', $monthDate->month)
                    ->whereYear('payment_date', $monthDate->year)
                    ->sum('amount');
                $exp = (float) \Illuminate\Support\Facades\DB::table('expenses')
                    ->whereMonth('expense_date', $monthDate->month)
                    ->whereYear('expense_date', $monthDate->year)
                    ->sum('amount')
                    + (float) \App\Models\ExpenseRequest::where('status', 'paid')
                    ->where(function($q) use ($monthDate) {
                        $q->where(function($sq) use ($monthDate) {
                            $sq->whereNotNull('paid_at')->whereMonth('paid_at', $monthDate->month)->whereYear('paid_at', $monthDate->year);
                        })->orWhere(function($sq) use ($monthDate) {
                            $sq->whereNull('paid_at')->whereMonth('updated_at', $monthDate->month)->whereYear('updated_at', $monthDate->year);
                        })->orWhere(function($sq) use ($monthDate) {
                            $sq->whereNull('paid_at')->whereMonth('created_at', $monthDate->month)->whereYear('created_at', $monthDate->year);
                        });
                    })
                    ->sum('amount');
                $incomes[] = $inc;
                $expenses[] = $exp;
            }
            return ['labels' => $labels, 'incomes' => $incomes, 'expenses' => $expenses];
        }, ['labels' => [], 'incomes' => [], 'expenses' => []]);

        // Category Breakdown from real Expenses table
        $expenseCategories = $this->safe(function() {
            return \Illuminate\Support\Facades\DB::table('expenses')
                ->select('category', \Illuminate\Support\Facades\DB::raw('SUM(amount) as total'))
                ->groupBy('category')
                ->orderByDesc('total')
                ->get();
        }, collect());

        $recentTransactions = $this->safe(fn() => \App\Models\Payment::with('project')->latest('payment_date')->take(10)->get(), collect());
        $recentExpenses     = $this->safe(fn() => \Illuminate\Support\Facades\DB::table('expenses')->orderByDesc('created_at')->take(10)->get(), collect());
        $coas               = $this->safe(fn() => \App\Models\ChartOfAccount::with('parent')->orderBy('code')->get(), collect());

        // ── Bank Accounts (role-scoped) ──────────────────────────────────────────
        if ($isFinanceHead) {
            $bankAccounts = $cashAndBankCoas;
            $assignedAccounts = collect();
        } else {
            $bankAccounts = collect();
            $assignedAccounts = $this->safe(fn() => \App\Models\ChartOfAccount::with('manager')->where('assigned_to', $user->id)->get(), collect());
        }

        // ── Plan vs Actual (Finance Head only) ───────────────────────────────────
        $planVsActual = collect();
        if ($isFinanceHead) {
            $planVsActual = $this->safe(function() {
                return \App\Models\Project::where('status', 'active')
                    ->withSum('budgets as total_budget', 'budgeted_amount')
                    ->get()
                    ->map(function($project) {
                        $actualExpenses = (float) \Illuminate\Support\Facades\DB::table('expenses')
                            ->where('project_id', $project->id)
                            ->sum('amount');
                        $budget = (float) ($project->total_budget ?? 0);
                        $variance = $budget - $actualExpenses;
                        $percentage = $budget > 0 ? min(round(($actualExpenses / $budget) * 100, 1), 200) : 0;
                        return (object)[
                            'id'              => $project->id,
                            'name'            => $project->name,
                            'budget'          => $budget,
                            'actual'          => $actualExpenses,
                            'variance'        => $variance,
                            'percentage'      => $percentage,
                            'over_budget'     => $actualExpenses > $budget && $budget > 0,
                        ];
                    });
            }, collect());
        }

        return view('dashboard.finance', compact(
            'kpi', 'monthlyAnalytics', 'expenseCategories',
            'recentTransactions', 'recentExpenses', 'coas',
            'bankAccounts', 'assignedAccounts', 'planVsActual', 'isFinanceHead',
            'cashAndBankCoas', 'totalCashBankBalance'
        ));
    }

    // ─── Purchase / Purchase Manager / Market Research ──────────────────────────
    public function purchase()
    {
        $kpi = [
            'pending_prs' => $this->safe(fn() => \App\Models\PurchaseRequest::where('status', 'submitted')->count()),
            'active_pos'  => $this->safe(fn() => \App\Models\PurchaseOrder::where('status', 'confirmed')->count()),
            'total_spend' => $this->safe(fn() => \App\Models\PurchaseOrder::where('status', 'delivered')->sum('grand_total')),
            'vendors'     => $this->safe(fn() => \App\Models\Supplier::count()),
        ];

        $recentPOs = $this->safe(fn() => \App\Models\PurchaseOrder::with('supplier')->latest()->take(5)->get(), collect());

        return view('dashboard.purchase', compact('kpi', 'recentPOs'));
    }

    // ─── Contract Admin ─────────────────────────────────────────────────────────
    public function contractAdmin()
    {
        // ── KPI Cards ──────────────────────────────────────────────────────────
        $kpi = [
            'active_projects'       => $this->safe(fn() => \App\Models\Project::where('status', 'active')->count()),
            'total_boq_value'       => $this->safe(fn() => \App\Models\Boq::where('status', 'approved')->sum('total_amount')),
            'pending_client_ipcs'   => $this->safe(fn() => \App\Models\ClientIpc::whereIn('status', ['submitted', 'under_review'])->count()),
            'payment_this_month'    => $this->safe(fn() => \App\Models\Payment::whereMonth('payment_date', now()->month)->sum('amount')),
            'total_certified'       => $this->safe(fn() => \App\Models\ClientIpc::whereIn('status', ['approved', 'paid'])->sum('gross_amount')),
            'pending_subcon_ipcs'   => $this->safe(fn() => \App\Models\IpcRecord::where('status', 'submitted')->count()),
        ];

        // ── BOQ Progress per Project ─────────────────────────────────────────
        // For each active project: BOQ total, total certified by client IPCs, total paid, % complete
        $projectBOQProgress = $this->safe(function () {
            return \App\Models\Project::where('status', 'active')
                ->with(['boqs' => fn($q) => $q->where('status', 'approved')->withSum('items as boq_total', DB::raw('quantity * unit_rate'))])
                ->get()
                ->map(function ($project) {
                    $boqTotal = $project->boqs->sum('boq_total') ?: $project->boqs->sum('total_amount');
                    $certified = \App\Models\ClientIpc::where('project_id', $project->id)
                        ->whereIn('status', ['approved', 'paid'])->sum('gross_amount');
                    $paid = \App\Models\Payment::where('project_id', $project->id)->sum('amount');
                    $pct = $boqTotal > 0 ? round(($certified / $boqTotal) * 100, 1) : 0;
                    return [
                        'project'   => $project,
                        'boq_total' => $boqTotal,
                        'certified' => $certified,
                        'paid'      => $paid,
                        'pct'       => min($pct, 100),
                    ];
                });
        }, collect());

        // ── Company (Client) IPCs ────────────────────────────────────────────
        $clientIpcs = $this->safe(
            fn() => \App\Models\ClientIpc::with(['project', 'createdBy'])->latest()->take(15)->get(),
            collect()
        );

        // ── Subcontractor IPCs (pending action) ──────────────────────────────
        $subconIpcs = $this->safe(
            fn() => \App\Models\IpcRecord::with(['agreement.subcontractor' => fn($q) => $q->select('id', 'name')])->latest()->take(10)->get(),
            collect()
        );

        // ── Recent Payments ──────────────────────────────────────────────────
        $recentPayments = $this->safe(
            fn() => \App\Models\Payment::with('project')->latest('payment_date')->take(8)->get(),
            collect()
        );

        // ── Earned Value from Daily Reports ──────────────────────────────────
        // Group daily report items by project → calculate earned value
        // DailyReportItem has schedule_task_id; ScheduleTask can link to BOQ via schedule
        // Since no direct boq_item_id, we compute: SUM of daily report items' quantities
        // mapped to BOQ unit rates where description matches, OR we show the daily work value
        // as a % of BOQ for the project by using the project's reported progress.
        $dailyEarnedValue = $this->safe(function () {
            return \App\Models\Project::where('status', 'active')
                ->get()
                ->map(function ($project) {
                    // Total daily report items qty × their unit rate (from schedule task → BOQ item)
                    $earned = \App\Models\DailyReport::where('project_id', $project->id)
                        ->where('status', 'approved')
                        ->with('items')
                        ->get()
                        ->flatMap(fn($r) => $r->items)
                        ->sum(fn($item) => ($item->quantity ?? 0) * ($item->unit_rate ?? 0));

                    // BOQ certified (approved client IPCs)
                    $certified = \App\Models\ClientIpc::where('project_id', $project->id)
                        ->whereIn('status', ['approved', 'paid'])->sum('gross_amount');

                    $boqTotal = \App\Models\Boq::where('project_id', $project->id)
                        ->where('status', 'approved')->sum('total_amount');

                    return [
                        'project'   => $project,
                        'earned'    => $earned,
                        'certified' => $certified,
                        'boq_total' => $boqTotal,
                    ];
                })->filter(fn($row) => $row['boq_total'] > 0 || $row['earned'] > 0);
        }, collect());

        // ── BOQ List ─────────────────────────────────────────────────────────
        $allBoqs = $this->safe(
            fn() => \App\Models\Boq::with('project')->latest()->get(),
            collect()
        );

        // ── Subcontract Agreements (recent) ──────────────────────────────────
        $recentSubcons = $this->safe(
            fn() => \App\Models\SubconAgreement::with('subcontractor')->latest()->take(5)->get(),
            collect()
        );

        return view('dashboard.contract-admin', compact(
            'kpi', 'projectBOQProgress', 'clientIpcs', 'subconIpcs',
            'recentPayments', 'dailyEarnedValue', 'allBoqs', 'recentSubcons'
        ));
    }

    // ─── Audit / Compliance Dashboard ──────────────────────────────────────────
    public function audit()
    {
        $kpi = [
            'under_audit_count'            => $this->safe(fn() => \App\Models\PettyCashReplenishment::where('status', \App\Models\PettyCashReplenishment::STATUS_UNDER_AUDIT)->count()),
            'under_audit_amount'           => $this->safe(fn() => (float) \App\Models\PettyCashReplenishment::where('status', \App\Models\PettyCashReplenishment::STATUS_UNDER_AUDIT)->sum('requested_amount')),
            'pending_replenishments_count' => $this->safe(fn() => \App\Models\PettyCashReplenishment::where('status', 'pending')->count()),
            'pending_replenishments_amount'=> $this->safe(fn() => (float) \App\Models\PettyCashReplenishment::where('status', 'pending')->sum('requested_amount')),
            'fulfilled_replenishments_month'=> $this->safe(fn() => (float) \App\Models\PettyCashReplenishment::where('status', 'fulfilled')
                ->whereMonth('fulfilled_at', now()->month)
                ->whereYear('fulfilled_at', now()->year)
                ->sum('fulfilled_amount')),
            'total_reconciled_vouchers'    => $this->safe(fn() => \App\Models\PettyCashReplenishmentItem::count()),
            'total_reconciled_amount'      => $this->safe(fn() => (float) \App\Models\PettyCashReplenishmentItem::sum('amount')),
            'total_activity_logs'          => $this->safe(fn() => \App\Models\ActivityLog::count()),
            'total_paid_expenses_month'    => $this->safe(fn() => (float) \App\Models\ExpenseRequest::where('status', \App\Models\ExpenseRequest::STATUS_PAID)
                ->whereMonth('paid_at', now()->month)
                ->whereYear('paid_at', now()->year)
                ->sum('amount')),
            'total_journal_entries'        => $this->safe(fn() => \App\Models\JournalEntry::count()),
        ];

        // Replenishments awaiting Audit clearance (under_audit)
        $underAuditReplenishments = $this->safe(fn() => \App\Models\PettyCashReplenishment::with(['chartOfAccount.manager', 'requester', 'reviewer', 'auditor', 'sourceCoa', 'items'])
            ->where('status', \App\Models\PettyCashReplenishment::STATUS_UNDER_AUDIT)
            ->latest()
            ->get(), collect());

        // Recent Petty Cash Replenishments & Fund Movements
        $recentReplenishments = $this->safe(fn() => \App\Models\PettyCashReplenishment::with(['chartOfAccount.manager', 'requester', 'financeHead', 'reviewer', 'auditor', 'sourceCoa', 'items'])
            ->latest()
            ->take(10)
            ->get(), collect());

        // Recent Audit Activity Logs
        $recentActivityLogs = $this->safe(fn() => \App\Models\ActivityLog::with('user')
            ->latest()
            ->take(15)
            ->get(), collect());

        // Cash & Bank Account Balances for Liquidity / Imprest Audit
        $cashAndBankAccounts = $this->safe(fn() => \App\Models\ChartOfAccount::where('is_active', true)
            ->where(function($q) {
                $q->where('type', 'asset')
                  ->orWhere('name', 'like', '%Cash%')
                  ->orWhere('name', 'like', '%Bank%')
                  ->orWhere('subtype', 'like', '%Cash%')
                  ->orWhere('subtype', 'like', '%Bank%');
            })
            ->orderBy('code')
            ->take(12)
            ->get(), collect());

        // Procurement & Purchasing Lifecycle Oversight (Read-Only for Auditor)
        $activeProcurementCount = $this->safe(fn() => \App\Models\PurchaseRequest::where('status', '!=', \App\Models\PurchaseRequest::STATUS_INTAKE_COMPLETE)->count(), 0);
        $recentProcurements = $this->safe(fn() => \App\Models\PurchaseRequest::with(['project', 'requestedBy', 'proformaInvoices', 'payment'])
            ->latest()
            ->take(8)
            ->get(), collect());

        // Inter-Store Material Transfers Oversight (Read-Only for Auditor)
        $activeTransfersCount = $this->safe(fn() => \App\Models\Transfer::whereIn('status', ['draft', 'pending_approval', 'approved', 'in_transit'])->count(), 0);
        $recentTransfers = $this->safe(fn() => \App\Models\Transfer::with(['fromStore', 'toStore', 'requestedBy', 'driver', 'items.product'])
            ->latest()
            ->take(8)
            ->get(), collect());

        return view('dashboard.audit', compact('kpi', 'underAuditReplenishments', 'recentReplenishments', 'recentActivityLogs', 'cashAndBankAccounts', 'activeProcurementCount', 'recentProcurements', 'activeTransfersCount', 'recentTransfers'));
    }
}


