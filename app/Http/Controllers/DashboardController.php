<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Project;
use App\Models\User;
use App\Models\Inventory;
use App\Models\Transfer;
use App\Models\DeliveryReceipt;

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
        $kpi = [
            'active_projects'     => $this->safe(fn() => \App\Models\Project::where('status', 'active')->count()),
            'total_contract_value'=> $this->safe(fn() => \App\Models\Project::sum('contract_value'), 0),
            'budget_utilization'  => $this->safe(function() {
                $contractValue = \App\Models\Project::sum('contract_value');
                $expenseValue = (\Illuminate\Support\Facades\DB::table('expenses')->sum('amount') ?? 0)
                              + (\App\Models\ExpenseRequest::where('status', \App\Models\ExpenseRequest::STATUS_PAID)->sum('amount') ?? 0);
                return $contractValue > 0 ? round(($expenseValue / $contractValue) * 100, 1) : 0;
            }),
            'pending_approvals'   => $this->safe(fn() => \App\Models\Employee::where('is_approved_by_gm', false)->orWhereNull('is_approved_by_gm')->count(), 0),
            'total_employees'     => $this->safe(fn() => \App\Models\Employee::where('status', 'active')->count(), 0),
            'pending_expenses'    => $this->safe(fn() => \Illuminate\Support\Facades\DB::table('expenses')->where('status', 'pending')->count() + \App\Models\ExpenseRequest::whereIn('status', ['pending', 'reviewed', 'approved', 'assigned'])->count(), 0),
            'pending_payroll'     => $this->safe(fn() => \Illuminate\Support\Facades\DB::table('payrolls')->where('status', 'pending')->count(), 0),
            'open_issues'         => $this->safe(fn() => \App\Models\Issue::where('status', 'open')->count(), 0),
        ];

        $projectStatus = $this->safe(fn() =>
            Project::select('status', DB::raw('count(*) as total'))->groupBy('status')->get(),
            collect()
        );

        $recentProjects = $this->safe(fn() => Project::latest()->take(5)->get(), collect());
        $pendingEmployees = $this->safe(fn() => \App\Models\Employee::where(function($q) {
            $q->where('is_approved_by_gm', false)->orWhereNull('is_approved_by_gm');
        })->latest()->take(5)->get(), collect());
        $recentExpenses = $this->safe(fn() => \Illuminate\Support\Facades\DB::table('expenses')
            ->orderByDesc('created_at')->take(5)->get(), collect());

        return view('dashboard.gm', compact('kpi', 'projectStatus', 'recentProjects', 'pendingEmployees', 'recentExpenses'));
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
        $myLetters           = $this->safe(fn() => \App\Models\Letter::where('created_by', $user->id)->latest()->take(8)->get(), collect());
        $recentLetters       = $this->safe(fn() => \App\Models\Letter::with(['creator', 'recipients'])->latest()->take(8)->get(), collect());

        // Projects
        $activeProjects      = $this->safe(fn() => \App\Models\Project::where('status', 'active')->count(), 0);
        $recentProjects      = $this->safe(fn() => \App\Models\Project::where('status', 'active')->latest()->take(5)->get(), collect());

        // Schedules
        $activeSchedules     = $this->safe(fn() => \App\Models\Schedule::whereDate('start_date', '<=', now())->whereDate('end_date', '>=', now())->count(), 0);
        $recentSchedules     = $this->safe(fn() => \App\Models\Schedule::with('project')->latest()->take(5)->get(), collect());

        // Employees
        $totalEmployees      = $this->safe(fn() => \App\Models\Employee::where('status', 'active')->count(), 0);

        // My expense requests
        $myExpenseRequests   = $this->safe(fn() => \App\Models\ExpenseRequest::where('requested_by', $user->id)->latest()->take(5)->get(), collect());

        return view('dashboard.secretary', compact(
            'totalLetters', 'pendingLetters', 'closedLetters',
            'myLetters', 'recentLetters',
            'activeProjects', 'recentProjects',
            'activeSchedules', 'recentSchedules',
            'totalEmployees', 'myExpenseRequests'
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
        ];

        $recentSchedules = $this->safe(fn() => \App\Models\Schedule::with('project')->latest()->take(5)->get(), collect());
        $recentDailyReports = $this->safe(fn() => \App\Models\DailyReport::with('project', 'creator')->latest()->take(5)->get(), collect());
        $pendingPlansFromPlanning = $this->safe(fn() => \App\Models\ProjectPlanWorkflow::with(['project', 'planningManager', 'creator'])
            ->where('status', 'planning_manager_approved')
            ->latest()
            ->take(10)
            ->get(), collect());
        $erpPlans = $this->safe(fn() => \App\Models\ErpPlanHeader::with('project', 'creator')->latest()->take(5)->get(), collect());

        return view('dashboard.coordinator', compact('kpi', 'recentSchedules', 'recentDailyReports', 'pendingPlansFromPlanning', 'erpPlans'));
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
        $assignedProjectIds = $assignedProjectIds->unique();

        $kpi = [
            'my_material_requests' => $this->safe(function() use ($user, $assignedProjectIds) {
                return \App\Models\MaterialRequest::where('requested_by', auth()->id())
                    ->when($assignedProjectIds->isNotEmpty(), fn($q) => $q->whereIn('project_id', $assignedProjectIds))
                    ->count();
            }),
            'issues_reported' => $this->safe(function() use ($user, $assignedProjectIds) {
                return \App\Models\Issue::where('reported_by', auth()->id())
                    ->when($assignedProjectIds->isNotEmpty(), fn($q) => $q->whereIn('project_id', $assignedProjectIds))
                    ->count();
            }),
            'attendance_today' => $this->safe(function() use ($user) {
                return \App\Models\Attendance::whereDate('attendance_date', now())
                    ->where('status', 'present')
                    ->when($user && $user->store_id, fn($q) => $q->where('store_id', $user->store_id))
                    ->count();
            }),
            'waste_recorded' => $this->safe(function() use ($user, $assignedProjectIds) {
                return \App\Models\Waste::whereMonth('waste_date', now()->month)
                    ->when($assignedProjectIds->isNotEmpty(), fn($q) => $q->whereIn('project_id', $assignedProjectIds))
                    ->count();
            }),
        ];

        $recentMR = $this->safe(function() use ($user, $assignedProjectIds) {
            return \App\Models\MaterialRequest::where('requested_by', auth()->id())
                ->when($assignedProjectIds->isNotEmpty(), fn($q) => $q->whereIn('project_id', $assignedProjectIds))
                ->with('project')
                ->latest()
                ->take(5)
                ->get();
        }, collect());

        return view('dashboard.site-engineer', compact('kpi', 'recentMR'));
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

    // ─── Store Manager / Store Keeper ───────────────────────────────────────────
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
            'total_employees'  => $this->safe(fn() => \App\Models\Employee::where('status', 'active')->count()),
            'present_today'    => $this->safe(fn() => \App\Models\Attendance::whereDate('attendance_date', now())->where('status', 'present')->count()),
            'pending_payroll'  => $this->safe(fn() => \App\Models\Payroll::where('status', 'pending')->count()),
            'open_requests'    => $this->safe(fn() => \App\Models\ManpowerRequest::where('status', 'pending')->count()),
        ];

        $recentPayrolls = $this->safe(fn() => \App\Models\Payroll::with('employee')->latest()->take(5)->get(), collect());

        return view('dashboard.hr', compact('kpi', 'recentPayrolls'));
    }

    // ─── Finance / Finance Head ─────────────────────────────────────────────────
    public function finance()
    {
        $user = auth()->user();
        $isFinanceHead = $user && $user->hasAnyRole(['Finance head', 'finance_head', 'admin', 'global_admin']);

        $kpi = [
            'total_income'    => $this->safe(fn() => (float) \App\Models\Payment::sum('amount') + (float) \Illuminate\Support\Facades\DB::table('client_ipcs')->where('status', 'paid')->sum('gross_amount'), 0),
            'total_expense'   => $this->safe(fn() => (float) \Illuminate\Support\Facades\DB::table('expenses')->sum('amount') + (float) \App\Models\ExpenseRequest::where('status', 'paid')->sum('amount') + (float) \Illuminate\Support\Facades\DB::table('payrolls')->sum('net_salary'), 0),
            'cash_balance'    => $this->safe(fn() => (float) \Illuminate\Support\Facades\DB::table('bank_accounts')->sum('current_balance'), 0),
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
        // Finance Head → ALL bank accounts
        // Regular Finance → only their assigned Chart of Account records
        if ($isFinanceHead) {
            $bankAccounts = $this->safe(fn() => \App\Models\BankAccount::where('is_active', true)->orderByDesc('current_balance')->get(), collect());
            $assignedAccounts = collect();
        } else {
            $bankAccounts = collect();
            $assignedAccounts = $this->safe(fn() => \App\Models\ChartOfAccount::where('assigned_to', $user->id)->get(), collect());
        }

        // ── Plan vs Actual (Finance Head only) ───────────────────────────────────
        // Compare project_budgets.budgeted_amount vs actual expenses per project
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
            'bankAccounts', 'assignedAccounts', 'planVsActual', 'isFinanceHead'
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
}
