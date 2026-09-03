@php
    $authUser = auth()->user();
    $rawUserRoles = $authUser ? $authUser->roles->pluck('name')->map(fn($r) => strtolower(str_replace([' ', '-'], '_', trim($r))))->toArray() : [];
    $isGeneralServiceUser = in_array('general_service', $rawUserRoles) || in_array('general_services', $rawUserRoles);
    $isSiteStaffUser = in_array('site_engineer', $rawUserRoles) || in_array('foreman', $rawUserRoles);
    $isSecretary = in_array('secretary', $rawUserRoles);
    $isContractAdmin = in_array('contract_admin', $rawUserRoles);
    $isStoreKeeper = in_array('store_keeper', $rawUserRoles);
    $isHrOfficer = in_array('hr_officer', $rawUserRoles) || in_array('hr', $rawUserRoles);
    $isHrManager = in_array('hr_manager', $rawUserRoles);
    $isCoordinator = in_array('coordinator', $rawUserRoles);
    $isStoreManager = in_array('store_manager', $rawUserRoles);
    $isAuditorUser = in_array('auditor', $rawUserRoles) || in_array('audit', $rawUserRoles) || in_array('internal_auditor', $rawUserRoles) || in_array('audit_team', $rawUserRoles) || ($authUser && $authUser->hasAnyRole(['auditor', 'audit', 'internal_auditor', 'Auditor', 'Audit']));
@endphp

<div class="sidebar-scroll">
    <ul class="sidebar-nav">

@role('global_admin|admin')
{{-- ════════════════════════════════════════════════════════════
     GLOBAL ADMIN: ROLE-GROUPED COLLAPSIBLE ACCORDION SIDEBAR
═══════════════════════════════════════════════════════════════ --}}

{{-- ① Dashboard --}}
<li class="sidebar-nav-item" style="padding: 0.4rem 0.75rem 0.1rem;">
    <a href="{{ route('dashboard') }}" class="sidebar-nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}" style="font-weight:600;">
        <i class="fa-solid fa-gauge-high text-info"></i>
        <span>Dashboard</span>
    </a>
</li>

{{-- Quick Action: Ask Money --}}
<li class="sidebar-nav-item" style="padding: 0.1rem 0.75rem 0.1rem;">
    <a href="{{ route('expense-requests.index') }}" class="sidebar-nav-link {{ request()->routeIs('expense-requests.*') || request()->is('expense-requests*') ? 'active' : '' }}" style="font-weight:600;">
        <i class="fa-solid fa-hand-holding-dollar text-success"></i>
        <span>Ask Money</span>
        @php
            $adminPendingExpenseCount = 0;
            try {
                $adminPendingExpenseCount = \App\Models\ExpenseRequest::where('status', 'like', 'Pending%')->count();
            } catch (\Exception $e) {}
        @endphp
        @if($adminPendingExpenseCount > 0)
            <span class="badge bg-warning text-dark rounded-pill ms-auto" style="font-size:0.65rem;">{{ $adminPendingExpenseCount }}</span>
        @endif
    </a>
</li>

{{-- Quick Action: Expense Approvals --}}
@php
    $adminPendingApprovalCount = 0;
    try {
        $adminPendingApprovalCount = \App\Models\ExpenseRequest::whereIn('status', [
            \App\Models\ExpenseRequest::STATUS_PENDING_HR,
            \App\Models\ExpenseRequest::STATUS_PENDING_GM,
            \App\Models\ExpenseRequest::STATUS_APPROVED_ASSIGNED,
            \App\Models\ExpenseRequest::STATUS_ASSIGNED,
            'Pending (HR Review)',
            'Pending (GM Review)',
            'Assigned to Finance',
            'Approved - Assigned to Finance'
        ])->count();
        if (\Illuminate\Support\Facades\Schema::hasTable('purchase_requests')) {
            $adminPendingApprovalCount += \App\Models\PurchaseRequest::where('status', \App\Models\PurchaseRequest::STATUS_PENDING_PAYMENT)->count();
        }
    } catch (\Exception $e) {}
@endphp
<li class="sidebar-nav-item" style="padding: 0.1rem 0.75rem 0.1rem;">
    <a href="{{ route('expenses.index') }}" class="sidebar-nav-link {{ request()->routeIs('expenses.*') || request()->is('expenses*') || request()->routeIs('approvals.*') ? 'active' : '' }}" style="font-weight:600;">
        <i class="fa-solid fa-file-invoice-dollar text-warning"></i>
        <span>Expense Approvals</span>
        @if($adminPendingApprovalCount > 0)
            <span class="badge bg-warning text-dark rounded-pill ms-auto" style="font-size:0.65rem;">{{ $adminPendingApprovalCount }}</span>
        @endif
    </a>
</li>
<hr class="sidebar-section-divider">

{{-- ② Projects & Planning --}}
@php
    $planningRoutes = ['projects.*','boqs.*','schedules.*','erp-plans.*','standard-works.*','takeoff.*','dispatches.*','material-plans.*','budgets.*','material-damage-reports.*','tool-transactions.*','cut-optimizations.*','material-usages.*','eng-schedule.*','daily-reports.*','weekly-reports.*','issues.*'];
    $planningActive = collect($planningRoutes)->contains(fn($p) => request()->routeIs($p));
@endphp
<li class="sidebar-nav-item group-item">
    <a class="sidebar-nav-link sidebar-group-toggle {{ $planningActive ? '' : 'collapsed' }}"
       data-bs-toggle="collapse" href="#adminGroupPlanning" role="button"
       aria-expanded="{{ $planningActive ? 'true' : 'false' }}">
        <span class="group-icon" style="background:rgba(99,102,241,0.2);">
            <i class="fa-solid fa-diagram-project" style="color:#818cf8;"></i>
        </span>
        <span>Projects & Planning</span>
        <i class="fa-solid fa-chevron-down sidebar-chevron"></i>
    </a>
    <div class="collapse {{ $planningActive ? 'show' : '' }}" id="adminGroupPlanning">
        <ul class="sidebar-sub-nav">
            <li><a href="{{ route('projects.index') }}" class="sidebar-nav-link {{ request()->routeIs('projects.*') ? 'active' : '' }}"><i class="fa-solid fa-building"></i><span>Projects</span></a></li>
            <li><a href="{{ route('boqs.index') }}" class="sidebar-nav-link {{ request()->routeIs('boqs.*') ? 'active' : '' }}"><i class="fa-solid fa-file-invoice-dollar"></i><span>BOQ</span></a></li>
            <li><a href="{{ route('schedules.index') }}" class="sidebar-nav-link {{ request()->routeIs('schedules.*') ? 'active' : '' }}"><i class="fa-solid fa-calendar-days"></i><span>Schedules</span></a></li>
            <li><a href="{{ route('erp-plans.index') }}" class="sidebar-nav-link {{ request()->routeIs('erp-plans.*') ? 'active' : '' }}"><i class="fa-solid fa-diagram-project"></i><span>ERP Plans</span></a></li>
            <li><a href="{{ route('budgets.index') }}" class="sidebar-nav-link {{ request()->routeIs('budgets.*') ? 'active' : '' }}"><i class="fa-solid fa-sack-dollar text-warning"></i><span>Project Budgets</span></a></li>
            <li><a href="{{ route('standard-works.index') }}" class="sidebar-nav-link {{ request()->routeIs('standard-works.*') ? 'active' : '' }}"><i class="fa-solid fa-ruler-combined"></i><span>Standard Works</span></a></li>
            <li><a href="{{ route('takeoff.index') }}" class="sidebar-nav-link {{ request()->routeIs('takeoff.*') ? 'active' : '' }}"><i class="fa-solid fa-ruler-combined"></i><span>Quantity Takeoff</span></a></li>
            <li><a href="{{ route('dispatches.index') }}" class="sidebar-nav-link {{ request()->routeIs('dispatches.*') ? 'active' : '' }}"><i class="fa-solid fa-truck-fast"></i><span>Weekly Dispatches</span></a></li>
            @if(\Illuminate\Support\Facades\Route::has('material-plans.index'))
            <li><a href="{{ route('material-plans.index') }}" class="sidebar-nav-link {{ request()->routeIs('material-plans.*') ? 'active' : '' }}"><i class="fa-solid fa-list-check"></i><span>Material Plans</span></a></li>
            @endif
            <li><a href="{{ route('eng-schedule.index') }}" class="sidebar-nav-link {{ request()->routeIs('eng-schedule.*') ? 'active' : '' }}"><i class="fa-solid fa-calendar-days text-primary"></i><span>Engineer Schedules</span></a></li>
            <li><a href="{{ route('daily-reports.index') }}" class="sidebar-nav-link {{ request()->routeIs('daily-reports.*') ? 'active' : '' }}"><i class="fa-solid fa-calendar-day"></i><span>Daily Reports</span></a></li>
            <li><a href="{{ route('weekly-reports.index') }}" class="sidebar-nav-link {{ request()->routeIs('weekly-reports.*') ? 'active' : '' }}"><i class="fa-solid fa-calendar-week"></i><span>Weekly Reports</span></a></li>
            <li><a href="{{ route('material-damage-reports.index') }}" class="sidebar-nav-link {{ request()->routeIs('material-damage-reports.*') ? 'active' : '' }}"><i class="fa-solid fa-triangle-exclamation"></i><span>Damage Reports</span></a></li>
            <li><a href="{{ route('tool-transactions.index') }}" class="sidebar-nav-link {{ request()->routeIs('tool-transactions.*') ? 'active' : '' }}"><i class="fa-solid fa-toolbox"></i><span>Tool Check-out</span></a></li>
            <li><a href="{{ route('issues.index') }}" class="sidebar-nav-link {{ request()->routeIs('issues.*') ? 'active' : '' }}"><i class="fa-solid fa-triangle-exclamation text-danger"></i><span>Site Issues</span></a></li>
        </ul>
    </div>
</li>

{{-- ③ Store & Inventory --}}
@php
    $storeRoutes = ['store-manager.*','inventory.*','stores.*','products.*','transfers.*'];
    $storeActive = collect($storeRoutes)->contains(fn($p) => request()->routeIs($p));
@endphp
<li class="sidebar-nav-item group-item">
    <a class="sidebar-nav-link sidebar-group-toggle {{ $storeActive ? '' : 'collapsed' }}"
       data-bs-toggle="collapse" href="#adminGroupStore" role="button"
       aria-expanded="{{ $storeActive ? 'true' : 'false' }}">
        <span class="group-icon" style="background:rgba(14,165,233,0.2);">
            <i class="fa-solid fa-warehouse" style="color:#38bdf8;"></i>
        </span>
        <span>Store & Inventory</span>
        @php
            try {
                $adminStorePendingCount = \App\Models\Transfer::whereIn('status',['draft','pending_approval'])->count();
            } catch (\Throwable $e) { $adminStorePendingCount = 0; }
        @endphp
        @if($adminStorePendingCount > 0)
            <span class="badge bg-warning text-dark rounded-pill" style="font-size:0.6rem;">{{ $adminStorePendingCount }}</span>
        @endif
        <i class="fa-solid fa-chevron-down sidebar-chevron"></i>
    </a>
    <div class="collapse {{ $storeActive ? 'show' : '' }}" id="adminGroupStore">
        <ul class="sidebar-sub-nav">
            <li><a href="{{ route('stores.index') }}" class="sidebar-nav-link {{ request()->routeIs('stores.*') ? 'active' : '' }}"><i class="fa-solid fa-warehouse text-info"></i><span>Stores</span></a></li>
            <li><a href="{{ route('store-manager.inventory.all') }}" class="sidebar-nav-link {{ request()->routeIs('store-manager.inventory.*') ? 'active' : '' }}"><i class="fa-solid fa-boxes-stacked text-primary"></i><span>All Inventory</span></a></li>
            <li><a href="{{ route('material-usages.index') }}" class="sidebar-nav-link {{ request()->routeIs('material-usages.*') ? 'active' : '' }}"><i class="fa-solid fa-boxes-packing text-success"></i><span>Daily Consumption</span></a></li>
            <li><a href="{{ route('products.index') }}" class="sidebar-nav-link {{ request()->routeIs('products.*') ? 'active' : '' }}"><i class="fa-solid fa-book"></i><span>Material Catalog</span></a></li>
            <li><a href="{{ route('store-manager.fixed-assets.index') }}" class="sidebar-nav-link {{ request()->routeIs('store-manager.fixed-assets.*') ? 'active' : '' }}"><i class="fa-solid fa-truck-monster text-warning"></i><span>Fixed Assets</span></a></li>
            <li><a href="{{ route('store-manager.material-requests.index') }}" class="sidebar-nav-link {{ request()->routeIs('store-manager.material-requests.*') ? 'active' : '' }}"><i class="fa-solid fa-clipboard-list text-danger"></i><span>Material Requests</span></a></li>
            <li><a href="{{ route('store-manager.transfers.index') }}" class="sidebar-nav-link {{ request()->routeIs('store-manager.transfers.*') ? 'active' : '' }}"><i class="fa-solid fa-truck-moving text-warning"></i><span>Transfers & Drivers</span></a></li>
            <li><a href="{{ route('store-manager.issued.index') }}" class="sidebar-nav-link {{ request()->routeIs('store-manager.issued.*') ? 'active' : '' }}"><i class="fa-solid fa-hand-holding"></i><span>Issued Materials</span></a></li>
            <li><a href="{{ route('store-manager.store-keepers.index') }}" class="sidebar-nav-link {{ request()->routeIs('store-manager.store-keepers.*') ? 'active' : '' }}"><i class="fa-solid fa-users-gear text-success"></i><span>Assign Store Keepers</span></a></li>
            <li><a href="{{ route('store-manager.slip-sequences.index') }}" class="sidebar-nav-link {{ request()->routeIs('store-manager.slip-sequences.*') ? 'active' : '' }}"><i class="fa-solid fa-stream text-info"></i><span>Slip Sequences</span></a></li>
        </ul>
    </div>
</li>

{{-- ④ Procurement --}}
@php
    $procRoutes = ['dashboard.purchase','purchase-requests.*','procurement.*','material-requests.*'];
    $procActive = collect($procRoutes)->contains(fn($p) => request()->routeIs($p));
@endphp
<li class="sidebar-nav-item group-item">
    <a class="sidebar-nav-link sidebar-group-toggle {{ $procActive ? '' : 'collapsed' }}"
       data-bs-toggle="collapse" href="#adminGroupProcurement" role="button"
       aria-expanded="{{ $procActive ? 'true' : 'false' }}">
        <span class="group-icon" style="background:rgba(245,158,11,0.2);">
            <i class="fa-solid fa-boxes-packing" style="color:#fbbf24;"></i>
        </span>
        <span>Procurement</span>
        @php
            try {
                $adminProcPendingCount = \App\Models\PurchaseRequest::whereIn('status',['pending','submitted','sent_to_gm'])->count();
            } catch (\Throwable $e) { $adminProcPendingCount = 0; }
        @endphp
        @if($adminProcPendingCount > 0)
            <span class="badge bg-danger rounded-pill" style="font-size:0.6rem;">{{ $adminProcPendingCount }}</span>
        @endif
        <i class="fa-solid fa-chevron-down sidebar-chevron"></i>
    </a>
    <div class="collapse {{ $procActive ? 'show' : '' }}" id="adminGroupProcurement">
        <ul class="sidebar-sub-nav">
            <li><a href="{{ route('dashboard.purchase') }}" class="sidebar-nav-link {{ request()->routeIs('dashboard.purchase') ? 'active' : '' }}"><i class="fa-solid fa-chart-line text-info"></i><span>Purchase Dashboard</span></a></li>
            <li><a href="{{ route('purchase-requests.index') }}" class="sidebar-nav-link {{ request()->routeIs('purchase-requests.*') ? 'active' : '' }}"><i class="fa-solid fa-file-invoice text-warning"></i><span>Purchase Requests</span></a></li>
            <li><a href="{{ route('procurement.my-queue') }}" class="sidebar-nav-link {{ request()->routeIs('procurement.my-queue') ? 'active' : '' }}"><i class="fa-solid fa-tasks text-primary"></i><span>My Procurement Queue</span></a></li>
            <li><a href="{{ route('material-requests.index') }}" class="sidebar-nav-link {{ request()->routeIs('material-requests.*') ? 'active' : '' }}"><i class="fa-solid fa-cart-flatbed text-danger"></i><span>Material Requests</span></a></li>
            <li><a href="{{ route('delivery-receipts.index') }}" class="sidebar-nav-link {{ request()->routeIs('delivery-receipts.*') ? 'active' : '' }}"><i class="fa-solid fa-receipt text-success"></i><span>Delivery Receipts</span></a></li>
        </ul>
    </div>
</li>

{{-- ⑤ Finance --}}
@php
    $financeRoutes = ['coa.*','coa-transfers.*','finance.*','bank-accounts.*','payments.*','income.*','expenses.*','reports.*','payroll.*','finance.payroll.*','delivery-receipts.*','assigned-accounts.*'];
    $financeActive = collect($financeRoutes)->contains(fn($p) => request()->routeIs($p)) || request()->is('finance/*') || request()->is('assigned-accounts*') || request()->is('expense-requests*');
@endphp
<li class="sidebar-nav-item group-item">
    <a class="sidebar-nav-link sidebar-group-toggle {{ $financeActive ? '' : 'collapsed' }}"
       data-bs-toggle="collapse" href="#adminGroupFinance" role="button"
       aria-expanded="{{ $financeActive ? 'true' : 'false' }}">
        <span class="group-icon" style="background:rgba(34,197,94,0.2);">
            <i class="fa-solid fa-coins" style="color:#4ade80;"></i>
        </span>
        <span>Finance</span>
        @php
            try {
                $adminFinPendingCount = \App\Models\ExpenseRequest::where('status', 'like', 'Pending%')->count();
            } catch (\Throwable $e) { $adminFinPendingCount = 0; }
        @endphp
        @if($adminFinPendingCount > 0)
            <span class="badge bg-warning text-dark rounded-pill" style="font-size:0.6rem;">{{ $adminFinPendingCount }}</span>
        @endif
        <i class="fa-solid fa-chevron-down sidebar-chevron"></i>
    </a>
    <div class="collapse {{ $financeActive ? 'show' : '' }}" id="adminGroupFinance">
        <ul class="sidebar-sub-nav">
            <li><a href="{{ route('dashboard.finance') }}" class="sidebar-nav-link {{ request()->routeIs('dashboard.finance') ? 'active' : '' }}"><i class="fa-solid fa-gauge-high text-primary"></i><span>Finance Dashboard</span></a></li>
            <li><a href="{{ route('coa.index') }}" class="sidebar-nav-link {{ request()->routeIs('coa.*') && !request()->routeIs('coa-transfers.*') ? 'active' : '' }}"><i class="fa-solid fa-sitemap"></i><span>Chart of Accounts</span></a></li>
            <li><a href="{{ route('coa-transfers.index') }}" class="sidebar-nav-link {{ request()->routeIs('coa-transfers.*') ? 'active' : '' }}"><i class="fa-solid fa-money-bill-transfer text-success"></i><span>COA Transfers</span></a></li>
            <li><a href="{{ route('expense-requests.index') }}" class="sidebar-nav-link {{ request()->routeIs('expense-requests.*') || request()->is('expense-requests*') ? 'active' : '' }}"><i class="fa-solid fa-hand-holding-dollar text-success"></i><span>Ask Money (Expenses)</span></a></li>
            <li><a href="{{ route('expenses.index') }}" class="sidebar-nav-link {{ request()->routeIs('expenses.*') || request()->is('expenses*') || request()->routeIs('approvals.*') ? 'active' : '' }}"><i class="fa-solid fa-file-invoice-dollar text-warning"></i><span>Expense Approvals</span>@if(($adminPendingApprovalCount ?? 0) > 0)<span class="badge bg-warning text-dark rounded-pill ms-auto" style="font-size:0.6rem;">{{ $adminPendingApprovalCount }}</span>@endif</a></li>
            <li><a href="{{ route('income.index') }}" class="sidebar-nav-link {{ request()->routeIs('income.*') ? 'active' : '' }}"><i class="fa-solid fa-arrow-trend-up"></i><span>Company Income</span></a></li>
            <li><a href="{{ route('finance.payroll.index') }}" class="sidebar-nav-link {{ request()->routeIs('finance.payroll.*') ? 'active' : '' }}"><i class="fa-solid fa-money-bill-wave text-success"></i><span>Payroll Management</span></a></li>
            <li><a href="{{ route('payroll.advances') }}" class="sidebar-nav-link {{ request()->routeIs('payroll.advances*') ? 'active' : '' }}"><i class="fa-solid fa-hand-holding-dollar text-warning"></i><span>Salary Advance Loans</span></a></li>
            <li><a href="{{ route('bank-accounts.index') }}" class="sidebar-nav-link {{ request()->routeIs('bank-accounts.*') ? 'active' : '' }}"><i class="fa-solid fa-building-columns"></i><span>Bank Accounts</span></a></li>
            <li><a href="{{ route('payments.index') }}" class="sidebar-nav-link {{ request()->routeIs('payments.*') ? 'active' : '' }}"><i class="fa-solid fa-chart-pie"></i><span>Payments</span></a></li>
            <li><a href="{{ route('finance.tax-deductions.index') }}" class="sidebar-nav-link {{ request()->routeIs('finance.tax-deductions.*') ? 'active' : '' }}"><i class="fa-solid fa-receipt text-danger"></i><span>VAT & Withholding Tax</span></a></li>
            <li><a href="{{ \Illuminate\Support\Facades\Route::has('finance.replenishments.index') ? route('finance.replenishments.index') : url('/finance/replenishments') }}" class="sidebar-nav-link {{ request()->is('finance/replenishments*') ? 'active' : '' }}"><i class="fa-solid fa-hand-holding-dollar text-warning"></i><span>Petty Cash Approvals</span></a></li>
            <li><a href="{{ \Illuminate\Support\Facades\Route::has('finance.credit-store.index') ? route('finance.credit-store.index') : url('/finance/credit-store') }}" class="sidebar-nav-link {{ request()->is('finance/credit-store*') ? 'active' : '' }}"><i class="fa-solid fa-credit-card text-info"></i><span>Credit Store Ledger</span></a></li>
            <li><a href="{{ route('reports.index') }}" class="sidebar-nav-link {{ request()->is('finance/reports*') ? 'active' : '' }}"><i class="fa-solid fa-file-lines text-primary"></i><span>Finance Reports</span></a></li>
            <li><a href="{{ \Illuminate\Support\Facades\Route::has('assigned-accounts.index') ? route('assigned-accounts.index') : url('/assigned-accounts') }}" class="sidebar-nav-link {{ request()->is('assigned-accounts*') ? 'active' : '' }}"><i class="fa-solid fa-briefcase text-primary"></i><span>Assigned Accounts</span></a></li>
        </ul>
    </div>
</li>

{{-- ⑥ HR & People --}}
@php
    $hrRoutes = ['employees.*','departments.*','attendance.*','leave-requests.*','weekly-manpower.*','manpower-forecast.*','performance-dashboard.*','payrolls.*','reports.attendance','employee.*','employee-letters.*'];
    $hrActive = collect($hrRoutes)->contains(fn($p) => request()->routeIs($p));
@endphp
<li class="sidebar-nav-item group-item">
    <a class="sidebar-nav-link sidebar-group-toggle {{ $hrActive ? '' : 'collapsed' }}"
       data-bs-toggle="collapse" href="#adminGroupHR" role="button"
       aria-expanded="{{ $hrActive ? 'true' : 'false' }}">
        <span class="group-icon" style="background:rgba(168,85,247,0.2);">
            <i class="fa-solid fa-users-gear" style="color:#c084fc;"></i>
        </span>
        <span>HR & People</span>
        @php
            try {
                $adminHrPendingLeave = \App\Models\LeaveRequest::where('status','pending')->count();
            } catch (\Throwable $e) { $adminHrPendingLeave = 0; }
        @endphp
        @if($adminHrPendingLeave > 0)
            <span class="badge bg-warning text-dark rounded-pill" style="font-size:0.6rem;">{{ $adminHrPendingLeave }}</span>
        @endif
        <i class="fa-solid fa-chevron-down sidebar-chevron"></i>
    </a>
    <div class="collapse {{ $hrActive ? 'show' : '' }}" id="adminGroupHR">
        <ul class="sidebar-sub-nav">
            <li><a href="{{ route('dashboard.hr') }}" class="sidebar-nav-link {{ request()->routeIs('dashboard.hr') ? 'active' : '' }}"><i class="fa-solid fa-gauge-high text-primary"></i><span>HR Dashboard</span></a></li>
            <li><a href="{{ route('employees.index') }}" class="sidebar-nav-link {{ request()->routeIs('employees.*') && !request()->routeIs('employees.history') ? 'active' : '' }}"><i class="fa-solid fa-users text-primary"></i><span>Employees</span></a></li>
            <li><a href="{{ route('employees.history') }}" class="sidebar-nav-link {{ request()->routeIs('employees.history') ? 'active' : '' }}"><i class="fa-solid fa-user-clock text-danger"></i><span>Employee History</span></a></li>
            <li><a href="{{ \Illuminate\Support\Facades\Route::has('employee-letters.index') ? route('employee-letters.index') : url('/employee-letters') }}" class="sidebar-nav-link {{ request()->routeIs('employee-letters.*') ? 'active' : '' }}"><i class="fa-solid fa-envelope-open-text text-warning"></i><span>Employee Letters</span></a></li>
            <li><a href="{{ route('departments.index') }}" class="sidebar-nav-link {{ request()->routeIs('departments.*') ? 'active' : '' }}"><i class="fa-solid fa-building text-secondary"></i><span>Departments</span></a></li>
            <li><a href="{{ route('attendance.index') }}" class="sidebar-nav-link {{ request()->routeIs('attendance.*') ? 'active' : '' }}"><i class="fa-solid fa-calendar-check text-success"></i><span>Attendance</span></a></li>
            <li><a href="{{ route('leave-requests.index') }}" class="sidebar-nav-link {{ request()->routeIs('leave-requests.*') ? 'active' : '' }}"><i class="fa-solid fa-calendar-minus text-info"></i><span>Leave Approvals</span></a></li>
            <li><a href="{{ route('payrolls.index') }}" class="sidebar-nav-link {{ request()->routeIs('payrolls.*') ? 'active' : '' }}"><i class="fa-solid fa-money-bill-wave text-success"></i><span>Payroll (HR)</span></a></li>
            <li><a href="{{ route('weekly-manpower.index') }}" class="sidebar-nav-link {{ request()->routeIs('weekly-manpower.*') ? 'active' : '' }}"><i class="fa-solid fa-chart-bar text-info"></i><span>Weekly Manpower</span></a></li>
            <li><a href="{{ route('manpower-forecast.index') }}" class="sidebar-nav-link {{ request()->routeIs('manpower-forecast.*') ? 'active' : '' }}"><i class="fa-solid fa-chart-line text-primary"></i><span>Manpower Forecast</span></a></li>
            <li><a href="{{ route('performance-dashboard.index') }}" class="sidebar-nav-link {{ request()->routeIs('performance-dashboard.*') ? 'active' : '' }}"><i class="fa-solid fa-chart-bar text-info"></i><span>Performance Reviews</span></a></li>
            <li><a href="{{ route('reports.attendance') }}" class="sidebar-nav-link {{ request()->routeIs('reports.*') ? 'active' : '' }}"><i class="fa-solid fa-chart-line text-danger"></i><span>HR Reports</span></a></li>
            <li><a href="{{ route('employee.dashboard') }}" class="sidebar-nav-link {{ request()->routeIs('employee.*') ? 'active' : '' }}"><i class="fa-solid fa-user-tie text-info"></i><span>Self-Service Portal</span></a></li>
            <li><a href="{{ \Illuminate\Support\Facades\Route::has('office-requests.index') ? route('office-requests.index') : url('/office-requests') }}" class="sidebar-nav-link {{ request()->is('office-requests*') ? 'active' : '' }}"><i class="fa-solid fa-boxes-stacked text-warning"></i><span>Office Material Requests</span></a></li>
        </ul>
    </div>
</li>

{{-- ⑦ Contracts & Subcon --}}
@php
    $contractRoutes = ['contracts.*','subcontractors.*','subcon-agreements.*','ipcs.*'];
    $contractActive = collect($contractRoutes)->contains(fn($p) => request()->routeIs($p));
@endphp
<li class="sidebar-nav-item group-item">
    <a class="sidebar-nav-link sidebar-group-toggle {{ $contractActive ? '' : 'collapsed' }}"
       data-bs-toggle="collapse" href="#adminGroupContracts" role="button"
       aria-expanded="{{ $contractActive ? 'true' : 'false' }}">
        <span class="group-icon" style="background:rgba(251,191,36,0.2);">
            <i class="fa-solid fa-file-contract" style="color:#fbbf24;"></i>
        </span>
        <span>Contracts & Subcon</span>
        <i class="fa-solid fa-chevron-down sidebar-chevron"></i>
    </a>
    <div class="collapse {{ $contractActive ? 'show' : '' }}" id="adminGroupContracts">
        <ul class="sidebar-sub-nav">
            <li><a href="{{ route('contracts.index') }}" class="sidebar-nav-link {{ request()->routeIs('contracts.*') ? 'active' : '' }}"><i class="fa-solid fa-file-contract text-warning"></i><span>Contracts</span></a></li>
            <li><a href="{{ route('ipcs.index') }}" class="sidebar-nav-link {{ request()->routeIs('ipcs.*') ? 'active' : '' }}"><i class="fa-solid fa-money-check-dollar text-success"></i><span>IPCs & Payments</span></a></li>
            <li><a href="{{ route('subcontractors.index') }}" class="sidebar-nav-link {{ request()->routeIs('subcontractors.*') ? 'active' : '' }}"><i class="fa-solid fa-handshake text-info"></i><span>Subcontractors</span></a></li>
            <li><a href="{{ route('subcon-agreements.index') }}" class="sidebar-nav-link {{ request()->routeIs('subcon-agreements.*') ? 'active' : '' }}"><i class="fa-solid fa-file-signature text-primary"></i><span>Subcon Agreements</span></a></li>
        </ul>
    </div>
</li>

{{-- ⑧ Marketing & Pricing --}}
@php
    $marketingRoutes = ['marketing.*'];
    $marketingActive = collect($marketingRoutes)->contains(fn($p) => request()->routeIs($p));
@endphp
<li class="sidebar-nav-item group-item">
    <a class="sidebar-nav-link sidebar-group-toggle {{ $marketingActive ? '' : 'collapsed' }}"
       data-bs-toggle="collapse" href="#adminGroupMarketing" role="button"
       aria-expanded="{{ $marketingActive ? 'true' : 'false' }}">
        <span class="group-icon" style="background:rgba(239,68,68,0.2);">
            <i class="fa-solid fa-bullhorn" style="color:#f87171;"></i>
        </span>
        <span>Marketing & Pricing</span>
        <i class="fa-solid fa-chevron-down sidebar-chevron"></i>
    </a>
    <div class="collapse {{ $marketingActive ? 'show' : '' }}" id="adminGroupMarketing">
        <ul class="sidebar-sub-nav">
            <li><a href="{{ route('marketing.dashboard') }}" class="sidebar-nav-link {{ request()->routeIs('marketing.dashboard') ? 'active' : '' }}"><i class="fa-solid fa-bullhorn text-primary"></i><span>Marketing Dashboard</span></a></li>
            <li><a href="{{ route('marketing.prices.create') }}" class="sidebar-nav-link {{ request()->routeIs('marketing.prices.create') ? 'active' : '' }}"><i class="fa-solid fa-calendar-plus text-success"></i><span>Price Update</span></a></li>
            <li><a href="{{ route('marketing.prices.history') }}" class="sidebar-nav-link {{ request()->routeIs('marketing.prices.history') ? 'active' : '' }}"><i class="fa-solid fa-clock-rotate-left text-info"></i><span>Price History & Trends</span></a></li>
            <li><a href="{{ route('marketing.reports.inflation') }}" class="sidebar-nav-link {{ request()->routeIs('marketing.reports.inflation') ? 'active' : '' }}"><i class="fa-solid fa-chart-line text-danger"></i><span>Inflation Report</span></a></li>
            <li><a href="{{ route('marketing.reports.planning-vs-actual') }}" class="sidebar-nav-link {{ request()->routeIs('marketing.reports.planning-vs-actual') ? 'active' : '' }}"><i class="fa-solid fa-scale-balanced text-warning"></i><span>Planning vs Actual</span></a></li>
        </ul>
    </div>
</li>

{{-- ⑨ Correspondence & Approvals --}}
@php
    $letterRoutes = ['letters.*'];
    $letterActive = collect($letterRoutes)->contains(fn($p) => request()->routeIs($p));
@endphp
<li class="sidebar-nav-item group-item">
    <a class="sidebar-nav-link sidebar-group-toggle {{ $letterActive ? '' : 'collapsed' }}"
       data-bs-toggle="collapse" href="#adminGroupLetters" role="button"
       aria-expanded="{{ $letterActive ? 'true' : 'false' }}">
        <span class="group-icon" style="background:rgba(14,165,233,0.15);">
            <i class="fa-solid fa-envelope-open-text" style="color:#38bdf8;"></i>
        </span>
        <span>Correspondence</span>
        @php
            try {
                $adminLetterCount = \App\Models\Letter::where('status','!=',\App\Models\Letter::STATUS_CLOSED)->count();
            } catch (\Throwable $e) { $adminLetterCount = 0; }
        @endphp
        @if($adminLetterCount > 0)
            <span class="badge bg-danger rounded-pill" style="font-size:0.6rem;">{{ $adminLetterCount }}</span>
        @endif
        <i class="fa-solid fa-chevron-down sidebar-chevron"></i>
    </a>
    <div class="collapse {{ $letterActive ? 'show' : '' }}" id="adminGroupLetters">
        <ul class="sidebar-sub-nav">
            <li><a href="{{ route('letters.index') }}" class="sidebar-nav-link {{ request()->routeIs('letters.index') || request()->routeIs('letters.show') ? 'active' : '' }}"><i class="fa-solid fa-envelope-open-text text-primary"></i><span>All Letters</span></a></li>
            <li><a href="{{ route('letters.create') }}" class="sidebar-nav-link {{ request()->routeIs('letters.create') ? 'active' : '' }}"><i class="fa-solid fa-pen-to-square text-success"></i><span>New Letter</span></a></li>
        </ul>
    </div>
</li>

{{-- ⑩ General Service --}}
@php
    $gsRoutes = ['general-service.*','maintenance.*','material-damage-reports.*'];
    $gsActive = collect($gsRoutes)->contains(fn($p) => request()->routeIs($p));
@endphp
<li class="sidebar-nav-item group-item">
    <a class="sidebar-nav-link sidebar-group-toggle {{ $gsActive ? '' : 'collapsed' }}"
       data-bs-toggle="collapse" href="#adminGroupGS" role="button"
       aria-expanded="{{ $gsActive ? 'true' : 'false' }}">
        <span class="group-icon" style="background:rgba(251,146,60,0.2);">
            <i class="fa-solid fa-screwdriver-wrench" style="color:#fb923c;"></i>
        </span>
        <span>General Service</span>
        @php
            try {
                $adminMaintPending = \App\Models\MaintenanceRequest::whereIn('status',['pending','sent_to_store_manager'])->count();
            } catch (\Throwable $e) { $adminMaintPending = 0; }
        @endphp
        @if($adminMaintPending > 0)
            <span class="badge bg-warning text-dark rounded-pill" style="font-size:0.6rem;">{{ $adminMaintPending }}</span>
        @endif
        <i class="fa-solid fa-chevron-down sidebar-chevron"></i>
    </a>
    <div class="collapse {{ $gsActive ? 'show' : '' }}" id="adminGroupGS">
        <ul class="sidebar-sub-nav">
            <li><a href="{{ route('dashboard.general_service') }}" class="sidebar-nav-link {{ request()->routeIs('dashboard.general_service') ? 'active' : '' }}"><i class="fa-solid fa-screwdriver-wrench text-warning"></i><span>GS Dashboard</span></a></li>
            <li><a href="{{ route('general-service.maintenance.index') }}" class="sidebar-nav-link {{ request()->routeIs('general-service.maintenance.*') ? 'active' : '' }}"><i class="fa-solid fa-wrench text-danger"></i><span>Maintenance Requests</span></a></li>
            <li><a href="{{ route('store-manager.fixed-assets.index') }}" class="sidebar-nav-link {{ request()->routeIs('store-manager.fixed-assets.*') ? 'active' : '' }}"><i class="fa-solid fa-truck-monster text-primary"></i><span>Workshop & Fixed Assets</span></a></li>
            <li><a href="{{ route('material-damage-reports.index') }}" class="sidebar-nav-link {{ request()->routeIs('material-damage-reports.*') ? 'active' : '' }}"><i class="fa-solid fa-triangle-exclamation text-warning"></i><span>Material Damage Reports</span></a></li>
        </ul>
    </div>
</li>

{{-- ⑪ Communication --}}
<li class="sidebar-nav-item group-item">
    <a class="sidebar-nav-link sidebar-group-toggle collapsed"
       data-bs-toggle="collapse" href="#adminGroupComm" role="button" aria-expanded="false">
        <span class="group-icon" style="background:rgba(16,185,129,0.2);">
            <i class="fa-solid fa-envelope" style="color:#34d399;"></i>
        </span>
        <span>Communication</span>
        <i class="fa-solid fa-chevron-down sidebar-chevron"></i>
    </a>
    <div class="collapse" id="adminGroupComm">
        <ul class="sidebar-sub-nav">
            <li><a href="{{ route('messages.index') }}" class="sidebar-nav-link {{ request()->routeIs('messages.*') ? 'active' : '' }}"><i class="fa-solid fa-envelope"></i><span>Messages</span></a></li>
            <li><a href="{{ route('tickets.index') }}" class="sidebar-nav-link {{ request()->routeIs('tickets.*') && !request()->routeIs('admin.tickets.*') ? 'active' : '' }}"><i class="fa-solid fa-headset text-warning"></i><span>My Support Tickets</span></a></li>
        </ul>
    </div>
</li>

{{-- ⑫ Admin & System --}}
@php
    $adminSysRoutes = ['users.*','admin.*','settings.*','dev.*','system.*'];
    $adminSysActive = collect($adminSysRoutes)->contains(fn($p) => request()->routeIs($p)) || request()->routeIs('dashboard.audit');
@endphp
<li class="sidebar-nav-item group-item">
    <a class="sidebar-nav-link sidebar-group-toggle {{ $adminSysActive ? '' : 'collapsed' }}"
       data-bs-toggle="collapse" href="#adminGroupSystem" role="button"
       aria-expanded="{{ $adminSysActive ? 'true' : 'false' }}">
        <span class="group-icon" style="background:rgba(100,116,139,0.25);">
            <i class="fa-solid fa-shield-halved" style="color:#94a3b8;"></i>
        </span>
        <span>Admin & System</span>
        @php
            try {
                $noRoleCount = \App\Models\User::whereDoesntHave('roles')->count();
            } catch (\Throwable $e) { $noRoleCount = 0; }
        @endphp
        @if($noRoleCount > 0)
            <span class="badge bg-danger rounded-pill" style="font-size:0.6rem;">{{ $noRoleCount }}</span>
        @endif
        <i class="fa-solid fa-chevron-down sidebar-chevron"></i>
    </a>
    <div class="collapse {{ $adminSysActive ? 'show' : '' }}" id="adminGroupSystem">
        <ul class="sidebar-sub-nav">
            <li><a href="{{ route('users.index') }}" class="sidebar-nav-link {{ request()->routeIs('users.*') ? 'active' : '' }}"><i class="fa-solid fa-user-shield"></i><span>User Management</span></a></li>
            <li><a href="{{ route('admin.role-assignment.index') }}" class="sidebar-nav-link {{ request()->routeIs('admin.role-assignment.*') ? 'active' : '' }}"><i class="fa-solid fa-user-tag text-info"></i><span>Role Assignment</span>@if($noRoleCount > 0)<span class="badge bg-warning text-dark ms-auto" style="font-size:0.6rem;">{{ $noRoleCount }}</span>@endif</a></li>
            <li><a href="{{ route('admin.employee-ratings.index') }}" class="sidebar-nav-link {{ request()->routeIs('admin.employee-ratings.*') ? 'active' : '' }}"><i class="fa-solid fa-star text-warning"></i><span>Employee Ratings</span></a></li>
            <li><a href="{{ route('admin.tickets.index') }}" class="sidebar-nav-link {{ request()->routeIs('admin.tickets.*') ? 'active' : '' }}"><i class="fa-solid fa-ticket text-danger"></i><span>Support Tickets</span></a></li>
            <li><a href="{{ route('settings.index') }}" class="sidebar-nav-link {{ request()->routeIs('settings.*') ? 'active' : '' }}"><i class="fa-solid fa-cogs"></i><span>System Settings</span></a></li>
            <hr class="sidebar-section-divider" style="margin: 0.3rem 0.5rem;">
            <li style="padding-top:0.1rem;"><small style="color:#475569; font-size:0.65rem; padding: 0 0.75rem; text-transform:uppercase; letter-spacing:0.05em;">Audit & Compliance</small></li>
            <li><a href="{{ \Illuminate\Support\Facades\Route::has('dashboard.audit') ? route('dashboard.audit') : url('/dashboard/audit') }}" class="sidebar-nav-link {{ request()->routeIs('dashboard.audit') ? 'active' : '' }}"><i class="fa-solid fa-chart-pie text-info"></i><span>Audit Dashboard</span></a></li>
            <li><a href="{{ route('admin.activity-logs') }}" class="sidebar-nav-link {{ request()->routeIs('admin.activity-logs') ? 'active' : '' }}"><i class="fa-solid fa-list-ol text-primary"></i><span>Activity Logs</span></a></li>
            <li><a href="{{ route('finance.tax-deductions.index') }}" class="sidebar-nav-link {{ request()->routeIs('finance.tax-deductions.*') ? 'active' : '' }}"><i class="fa-solid fa-receipt text-danger"></i><span>VAT & Tax Audit</span></a></li>
            <li><a href="{{ \Illuminate\Support\Facades\Route::has('finance.replenishments.index') ? route('finance.replenishments.index') : url('/finance/replenishments') }}" class="sidebar-nav-link {{ request()->is('finance/replenishments*') ? 'active' : '' }}"><i class="fa-solid fa-hand-holding-dollar text-warning"></i><span>Petty Cash Audit</span></a></li>
            <hr class="sidebar-section-divider" style="margin: 0.3rem 0.5rem;">
            <li style="padding-top:0.1rem;"><small style="color:#475569; font-size:0.65rem; padding: 0 0.75rem; text-transform:uppercase; letter-spacing:0.05em;">Developer Tools</small></li>
            <li><a href="{{ route('dev.roles') }}" class="sidebar-nav-link" style="color:#fbbf24;"><i class="fa-solid fa-vial"></i><span>Role Tester</span></a></li>
            <li><a href="{{ route('system.run-migrations') }}" class="sidebar-nav-link" style="color:#20c997;" onclick="return confirm('Run database migrations now?')"><i class="fa-solid fa-database"></i><span>Auto Migrate DB</span></a></li>
        </ul>
    </div>
</li>

@else
{{-- ═══════════════════════════════════════════
     NON-ADMIN: EXISTING ROLE-BASED SIDEBAR
═══════════════════════════════════════════ --}}

        @if(!auth()->check() || (!$isSiteStaffUser && !$isGeneralServiceUser && !$isAuditorUser))
        @php
            $isGmUser = auth()->check() && (auth()->user()->hasAnyRole(['gm', 'general_manager', 'General Manager', 'GM']) || in_array('gm', $rawUserRoles) || in_array('general_manager', $rawUserRoles));
            $isFinanceUser = auth()->check() && auth()->user()->hasAnyRole(['Finance head', 'finance_head', 'finance', 'Finance', 'finance_manager', 'cashier', 'accountant']);
            $dashUrl = \Illuminate\Support\Facades\Route::has('dashboard') ? route('dashboard') : url('/dashboard');
            $dashTitle = 'Dashboard';
            if ($isGmUser) {
                $dashUrl = \Illuminate\Support\Facades\Route::has('dashboard.gm') ? route('dashboard.gm') : url('/dashboard/gm');
                $dashTitle = 'GM Dashboard & Analytics';
            } elseif ($isFinanceUser) {
                $dashUrl = \Illuminate\Support\Facades\Route::has('dashboard.finance') ? route('dashboard.finance') : url('/dashboard/finance');
                $dashTitle = 'Finance Dashboard';
            } elseif ($isSecretary) {
                $dashUrl = \Illuminate\Support\Facades\Route::has('dashboard.secretary') ? route('dashboard.secretary') : url('/dashboard/secretary');
                $dashTitle = 'Secretary Dashboard';
            } elseif ($isContractAdmin) {
                $dashUrl = \Illuminate\Support\Facades\Route::has('dashboard.contract-admin') ? route('dashboard.contract-admin') : url('/dashboard/contract-admin');
                $dashTitle = 'Contract Dashboard';
            } elseif ($isStoreKeeper) {
                $dashUrl = \Illuminate\Support\Facades\Route::has('dashboard.store-keeper') ? route('dashboard.store-keeper') : url('/dashboard/store-keeper');
                $dashTitle = 'Store Keeper Dashboard';
            } elseif ($isHrOfficer || $isHrManager) {
                $dashUrl = \Illuminate\Support\Facades\Route::has('dashboard.hr') ? route('dashboard.hr') : (\Illuminate\Support\Facades\Route::has('hr-manager.dashboard') ? route('hr-manager.dashboard') : url('/dashboard/hr'));
                $dashTitle = 'HR Dashboard';
            } elseif ($isCoordinator) {
                $dashUrl = \Illuminate\Support\Facades\Route::has('dashboard.coordinator') ? route('dashboard.coordinator') : url('/coordinator/dashboard');
                $dashTitle = 'Coordinator Dashboard';
            } elseif ($isStoreManager) {
                $dashUrl = \Illuminate\Support\Facades\Route::has('dashboard.store-manager') ? route('dashboard.store-manager') : url('/store-manager/dashboard');
                $dashTitle = 'Store Dashboard';
            }
        @endphp

        <li class="sidebar-nav-item">
            <a href="{{ $dashUrl }}" class="sidebar-nav-link {{ request()->routeIs('dashboard*') || request()->routeIs('hr-manager.dashboard') ? 'active' : '' }}">
                <i class="fa-solid fa-gauge-high"></i>
                <span>{{ $dashTitle }}</span>
            </a>
        </li>
        @elseif($isGeneralServiceUser)
        <li class="sidebar-nav-item">
            <a href="{{ route('dashboard.general_service') }}" class="sidebar-nav-link {{ request()->routeIs('dashboard.general_service') ? 'active' : '' }}">
                <i class="fa-solid fa-screwdriver-wrench text-warning"></i>
                <span>General Service Hub</span>
            </a>
        </li>
        <li class="sidebar-nav-item">
            <a href="{{ route('store-manager.transfers.index') }}" class="sidebar-nav-link {{ request()->routeIs('store-manager.transfers.*') ? 'active' : '' }}">
                <i class="fa-solid fa-truck-moving text-primary"></i>
                <span>Transfers &amp; Drivers</span>
                @php
                    $gsPendingDriverCount = 0;
                    try {
                        $gsPendingDriverCount = \App\Models\Transfer::whereIn('status', ['draft', 'pending_approval'])->count();
                    } catch (\Throwable $e) {}
                @endphp
                @if($gsPendingDriverCount > 0)
                    <span class="badge bg-warning text-dark rounded-pill ms-auto">{{ $gsPendingDriverCount }}</span>
                @endif
            </a>
        </li>
        <li class="sidebar-nav-item">
            <a href="{{ route('leave-requests.create') }}" class="sidebar-nav-link {{ request()->routeIs('leave-requests.create') || request()->routeIs('leave-requests.my-requests') ? 'active' : '' }}">
                <i class="fa-solid fa-calendar-plus text-info"></i>
                <span>Ask / Request Leave</span>
            </a>
        </li>
        @elseif($isAuditorUser)
        {{-- Clean & Dedicated Auditor Workspace --}}
        <li class="sidebar-nav-item">
            <a href="{{ \Illuminate\Support\Facades\Route::has('dashboard.audit') ? route('dashboard.audit') : url('/dashboard/audit') }}" class="sidebar-nav-link {{ request()->routeIs('dashboard.audit') || request()->routeIs('dashboard') ? 'active' : '' }}">
                <i class="fa-solid fa-chart-pie text-info"></i>
                <span>Audit Dashboard</span>
            </a>
        </li>
        <li class="sidebar-nav-item">
            <a href="{{ route('finance.replenishments.index', ['tab' => 'under_audit']) }}" class="sidebar-nav-link {{ request()->is('finance/replenishments*') ? 'active' : '' }}">
                <i class="fa-solid fa-hand-holding-dollar text-warning"></i>
                <span>Petty Cash Audit &amp; Approvals</span>
                @php
                    $pendingAudReplenishCount = 0;
                    try {
                        if (\Illuminate\Support\Facades\Schema::hasTable('petty_cash_replenishments')) {
                            $pendingAudReplenishCount = \App\Models\PettyCashReplenishment::where('status', \App\Models\PettyCashReplenishment::STATUS_UNDER_AUDIT)->count();
                        }
                    } catch (\Exception $e) {}
                @endphp
                @if($pendingAudReplenishCount > 0)
                    <span class="badge bg-danger rounded-pill ms-auto">{{ $pendingAudReplenishCount }}</span>
                @endif
            </a>
        </li>
        <li class="sidebar-nav-item">
            <a href="{{ route('expenses.index') }}" class="sidebar-nav-link {{ request()->routeIs('expenses.*') ? 'active' : '' }}">
                <i class="fa-solid fa-file-invoice-dollar text-success"></i>
                <span>Expense Audit &amp; Tracking</span>
            </a>
        </li>
        <li class="sidebar-nav-item">
            <a href="{{ route('procurement.my-queue') }}" class="sidebar-nav-link {{ request()->routeIs('procurement.*') ? 'active' : '' }}">
                <i class="fa-solid fa-boxes-packing text-primary"></i>
                <span>Procurement Status (Read-Only)</span>
            </a>
        </li>
        <li class="sidebar-nav-item">
            <a href="{{ route('purchase-requests.index') }}" class="sidebar-nav-link {{ request()->routeIs('purchase-requests.*') ? 'active' : '' }}">
                <i class="fa-solid fa-file-invoice text-info"></i>
                <span>Purchase Requests (Read-Only)</span>
            </a>
        </li>
        <li class="sidebar-nav-item">
            <a href="{{ route('store-manager.transfers.index') }}" class="sidebar-nav-link {{ request()->routeIs('store-manager.transfers.*') || request()->routeIs('transfers.*') ? 'active' : '' }}">
                <i class="fa-solid fa-truck-moving text-warning"></i>
                <span>Store Transfers (Read-Only)</span>
            </a>
        </li>
        <li class="sidebar-nav-item">
            <a href="{{ route('finance.tax-deductions.index') }}" class="sidebar-nav-link {{ request()->routeIs('finance.tax-deductions.*') ? 'active' : '' }}">
                <i class="fa-solid fa-receipt text-danger"></i>
                <span>VAT &amp; Withholding Tax</span>
            </a>
        </li>
        <li class="sidebar-nav-item">
            <a href="{{ route('leave-requests.create') }}" class="sidebar-nav-link {{ request()->routeIs('leave-requests.*') ? 'active' : '' }}">
                <i class="fa-solid fa-calendar-plus text-info"></i>
                <span>Ask / Request Leave</span>
            </a>
        </li>
        @endif

        @if($isSecretary)
        <li class="sidebar-nav-item">
            <a href="{{ route('letters.index') }}" class="sidebar-nav-link {{ request()->routeIs('letters.index') || request()->routeIs('letters.show') ? 'active' : '' }}">
                <i class="fa-solid fa-envelope-open-text text-primary"></i>
                <span>Letters &amp; Correspondence</span>
                @php
                    $secLetterCount = 0;
                    try {
                        $secLetterCount = \App\Models\Letter::whereIn('status', ['pending', 'forwarded', 'in_progress'])->count();
                    } catch (\Exception $e) {}
                @endphp
                @if($secLetterCount > 0)
                    <span class="badge bg-danger rounded-pill ms-auto">{{ $secLetterCount }}</span>
                @endif
            </a>
        </li>
        <li class="sidebar-nav-item">
            <a href="{{ route('letters.create') }}" class="sidebar-nav-link {{ request()->routeIs('letters.create') ? 'active' : '' }}">
                <i class="fa-solid fa-pen-to-square text-success"></i>
                <span>New Letter</span>
            </a>
        </li>
        <li class="sidebar-nav-item">
            <a href="{{ \Illuminate\Support\Facades\Route::has('office-requests.index') ? route('office-requests.index') : url('/office-requests') }}" class="sidebar-nav-link {{ request()->is('office-requests*') || request()->routeIs('office-requests.*') ? 'active' : '' }}">
                <i class="fa-solid fa-boxes-stacked text-warning"></i>
                <span>Office Supply Request</span>
                @php
                    $secOfficeReqCount = 0;
                    try {
                        if (\Illuminate\Support\Facades\Schema::hasTable('office_material_requests')) {
                            $secOfficeReqCount = \App\Models\OfficeMaterialRequest::where('requested_by', auth()->id())
                                ->where('status', \App\Models\OfficeMaterialRequest::STATUS_PENDING_HR)
                                ->count();
                        }
                    } catch (\Exception $e) {}
                @endphp
                @if($secOfficeReqCount > 0)
                    <span class="badge bg-warning text-dark rounded-pill ms-auto">{{ $secOfficeReqCount }}</span>
                @endif
            </a>
        </li>
        <li class="sidebar-nav-item">
            <a href="{{ \Illuminate\Support\Facades\Route::has('office-requests.create') ? route('office-requests.create') : url('/office-requests/create') }}" class="sidebar-nav-link {{ request()->is('office-requests/create') || request()->routeIs('office-requests.create') ? 'active' : '' }}">
                <i class="fa-solid fa-plus-circle text-info"></i>
                <span>New Office Request</span>
            </a>
        </li>
        <li class="sidebar-nav-item">
            <a href="{{ route('leave-requests.create') }}" class="sidebar-nav-link {{ request()->routeIs('leave-requests.create') || request()->routeIs('leave-requests.my-requests') ? 'active' : '' }}">
                <i class="fa-solid fa-calendar-plus text-info"></i>
                <span>Ask / Request Leave</span>
            </a>
        </li>
        @endif

        @if(!$isSecretary && !$isStoreKeeper && !$isGeneralServiceUser && !$isAuditorUser)
        <li class="sidebar-nav-item">
            <a href="{{ route('expense-requests.index') }}" class="sidebar-nav-link {{ request()->routeIs('expense-requests.*') ? 'active' : '' }}">
                <i class="fa-solid fa-hand-holding-dollar text-success"></i>
                <span>Ask Money</span>
                @php
                    $pendingExpenseCount = 0;
                    try {
                        $pendingExpenseCount = \App\Models\ExpenseRequest::where('status', 'like', 'Pending%')->count();
                    } catch (\Exception $e) {}
                @endphp
                @if($pendingExpenseCount > 0 && auth()->check() && (auth()->user()->hasAnyRole(['admin', 'global_admin', 'hr_manager', 'hr_officer', 'gm', 'finance_head', 'coordinator', 'Coordinator']) || in_array('coordinator', $rawUserRoles)))
                    <span class="badge bg-warning text-dark rounded-pill ms-auto">{{ $pendingExpenseCount }}</span>
                @endif
            </a>
        </li>
        @endif

        @if(!$isSecretary && !$isStoreKeeper && !$isGeneralServiceUser && !$isAuditorUser)
        <li class="sidebar-nav-item">
            <a href="{{ route('letters.index') }}" class="sidebar-nav-link {{ request()->routeIs('letters.*') ? 'active' : '' }}">
                <i class="fa-solid fa-envelope-open-text text-primary"></i>
                <span>Correspondence (Letters)</span>
                @php
                    $unreadLettersCount = 0;
                    try {
                        if (auth()->check()) {
                            $user = auth()->user();
                            $userRoles = $user->getRoleNames()->toArray();
                            $unreadLettersCount = \App\Models\Letter::whereHas('recipients', function($q) use ($user, $userRoles) {
                                $q->where('to_user_id', $user->id)
                                  ->orWhereIn('to_role_name', $userRoles);
                            })->where('status', '!=', \App\Models\Letter::STATUS_CLOSED)->count();
                        }
                    } catch (\Exception $e) {}
                @endphp
                @if($unreadLettersCount > 0)
                    <span class="badge bg-danger rounded-pill ms-auto">{{ $unreadLettersCount }}</span>
                @endif
            </a>
        </li>
        @endif

        {{-- Ask / Request Leave (Visible to All Roles) --}}
        @if(!$isSecretary && !$isGeneralServiceUser && !$isAuditorUser)
        <li class="sidebar-nav-item">
            <a href="{{ route('leave-requests.create') }}" class="sidebar-nav-link {{ request()->routeIs('leave-requests.create') || request()->routeIs('leave-requests.my-requests') ? 'active' : '' }}">
                <i class="fa-solid fa-calendar-plus text-info"></i>
                <span>Ask / Request Leave</span>
            </a>
        </li>
        @endif

        {{-- General Manager Section --}}
        @if(auth()->check() && (auth()->user()->hasAnyRole(['gm', 'general_manager', 'General Manager', 'GM']) || in_array('gm', $rawUserRoles) || in_array('general_manager', $rawUserRoles)))

        <li class="sidebar-nav-item">
            @php
                $pendingGmLeaveCount = 0;
                try {
                    if (\Illuminate\Support\Facades\Schema::hasTable('leave_requests')) {
                        $pendingGmLeaveCount = \App\Models\LeaveRequest::where('status', 'pending')->count();
                    }
                } catch (\Exception $e) {}
            @endphp
            <a href="{{ route('leave-requests.index') }}" class="sidebar-nav-link {{ (request()->routeIs('leave-requests.*') && !request()->routeIs('leave-requests.create') && !request()->routeIs('leave-requests.my-requests')) ? 'active' : '' }}">
                <i class="fa-solid fa-calendar-check text-success"></i>
                <span>GM Leave Decisions</span>
                @if($pendingGmLeaveCount > 0)
                    <span class="badge bg-warning text-dark rounded-pill ms-auto">{{ $pendingGmLeaveCount }}</span>
                @endif
            </a>
        </li>
        <li class="sidebar-nav-item">
            <a href="{{ route('expenses.index') }}?tab=pending_gm" class="sidebar-nav-link {{ (request()->routeIs('expenses.*') || request()->is('expenses*') || request()->routeIs('approvals.*')) && request('tab') === 'pending_gm' ? 'active' : '' }}">
                <i class="fa-solid fa-file-invoice-dollar text-warning"></i>
                <span>Expense Approvals</span>

                @php
                    $pendingGmExpCount = 0;
                    try {
                        $pendingGmExpCount = \App\Models\ExpenseRequest::where('status', \App\Models\ExpenseRequest::STATUS_PENDING_GM)->count();
                    } catch (\Exception $e) {}
                @endphp
                @if($pendingGmExpCount > 0)
                    <span class="badge bg-warning text-dark rounded-pill ms-auto">{{ $pendingGmExpCount }}</span>
                @endif
            </a>
        </li>
        <li class="sidebar-nav-item">
            <a href="{{ route('finance.payroll.gm') }}" class="sidebar-nav-link {{ request()->routeIs('finance.payroll.gm*') ? 'active' : '' }}">
                <i class="fa-solid fa-file-signature text-warning"></i>
                <span>Payroll Approvals</span>
            </a>
        </li>


        <li class="sidebar-nav-item">
            <a href="{{ route('payroll.advances') }}?status=pending" class="sidebar-nav-link {{ request()->routeIs('payroll.advances*') && request('status') === 'pending' ? 'active' : '' }}">
                <i class="fa-solid fa-hand-holding-dollar text-success"></i>
                <span>Loan Approvals</span>
                @php
                    $pendingLoansCount = 0;
                    try {
                        $pendingLoansCount = \App\Models\EmployeeAdvance::where('status', 'pending')->count();
                    } catch (\Exception $e) {}
                @endphp
                @if($pendingLoansCount > 0)
                    <span class="badge bg-danger rounded-pill ms-auto">{{ $pendingLoansCount }}</span>
                @endif
            </a>
        </li>
        <li class="sidebar-nav-item">
            <a href="{{ route('employees.pending-approval') }}" class="sidebar-nav-link {{ request()->routeIs('employees.pending-approval') ? 'active' : '' }}">
                <i class="fa-solid fa-user-clock text-warning"></i>
                <span>Employee Approvals</span>
                @php
                    $pendingEmpCount = 0;
                    try {
                        $pendingEmpCount = \App\Models\Employee::where(function($q) {
                            $q->where('is_approved_by_gm', false)->orWhereNull('is_approved_by_gm');
                        })->count();
                    } catch (\Exception $e) {}
                @endphp
                @if($pendingEmpCount > 0)
                    <span class="badge bg-warning text-dark rounded-pill ms-auto">{{ $pendingEmpCount }}</span>
                @endif
            </a>
        </li>

        {{-- ── GM Expense & Cost Tracking ──────────────────────────────────── --}}
        <hr class="sidebar-section-divider" style="margin:0.4rem 0.75rem;">
        <li style="padding: 0.1rem 0.75rem 0.05rem;">
            <small style="color:#64748b; font-size:0.65rem; text-transform:uppercase; letter-spacing:0.05em; font-weight:600;">
                <i class="fa-solid fa-chart-pie me-1" style="color:#20c997;"></i>Expense Tracking
            </small>
        </li>

        <li class="sidebar-nav-item">
            <a href="{{ route('dashboard.gm') }}#project-expenses"
               class="sidebar-nav-link {{ request()->routeIs('dashboard.gm') ? 'active' : '' }}"
               title="View per-project expense breakdown: cash + material consumption">
                <i class="fa-solid fa-chart-bar text-danger"></i>
                <span>Project Expenses</span>
                @php
                    $totalProjExpCount = 0;
                    try {
                        $totalProjExpCount = \App\Models\Project::where('status', 'active')->count();
                    } catch (\Exception $e) {}
                @endphp
                @if($totalProjExpCount > 0)
                    <span class="badge rounded-pill ms-auto" style="background:rgba(220,53,69,0.15);color:#dc3545;font-size:0.6rem;">{{ $totalProjExpCount }}</span>
                @endif
            </a>
        </li>

        <li class="sidebar-nav-item">
            <a href="{{ route('material-usages.index') }}"
               class="sidebar-nav-link {{ request()->routeIs('material-usages.*') ? 'active' : '' }}"
               title="Track material consumption priced by unit cost across all projects">
                <i class="fa-solid fa-boxes me-1" style="color:#20c997;"></i>
                <span>Material Consumption</span>
                @php
                    $pendingMatUsageCount = 0;
                    try {
                        $pendingMatUsageCount = \App\Models\MaterialUsage::where('status', 'draft')->count();
                    } catch (\Exception $e) {}
                @endphp
                @if($pendingMatUsageCount > 0)
                    <span class="badge bg-secondary rounded-pill ms-auto" style="font-size:0.6rem;">{{ $pendingMatUsageCount }}</span>
                @endif
            </a>
        </li>

        <li class="sidebar-nav-item">
            <a href="{{ route('expense-requests.index') }}"
               class="sidebar-nav-link {{ (request()->routeIs('expense-requests.*') && !request()->routeIs('expense-requests.create')) ? 'active' : '' }}"
               title="All expense requests across all projects">
                <i class="fa-solid fa-receipt text-warning"></i>
                <span>All Expense Requests</span>
                @php
                    $allExpReqCount = 0;
                    try {
                        $allExpReqCount = \App\Models\ExpenseRequest::whereIn('status', ['pending', 'reviewed', 'approved', 'assigned'])->count();
                    } catch (\Exception $e) {}
                @endphp
                @if($allExpReqCount > 0)
                    <span class="badge bg-warning text-dark rounded-pill ms-auto" style="font-size:0.6rem;">{{ $allExpReqCount }}</span>
                @endif
            </a>
        </li>

        @endif

        {{-- Masters --}}

        @if(!auth()->check() || (!$isSiteStaffUser && !$isGeneralServiceUser && !$isSecretary && !$isStoreKeeper && !$isStoreManager && !$isAuditorUser))
        @if($isCoordinator || (auth()->check() && (auth()->user()->hasAnyRole(['coordinator', 'Coordinator', 'admin', 'global_admin']) || auth()->user()->hasAnyPermission(['projects.view', 'planning.view', 'schedule.view', 'stores.view', 'stores.create', 'stores.edit', 'stores.delete', 'products.view', 'products.create', 'products.edit', 'products.delete']))))

        @if($isCoordinator || (auth()->check() && (auth()->user()->hasAnyRole(['coordinator', 'Coordinator', 'admin', 'global_admin']) || auth()->user()->hasAnyPermission(['projects.view', 'planning.view', 'schedule.view']))))
        <li class="sidebar-nav-item">
            <a href="{{ route('projects.index') }}" class="sidebar-nav-link {{ request()->routeIs('projects.*') ? 'active' : '' }}">
                <i class="fa-solid fa-building"></i>
                <span>Projects</span>
            </a>
        </li>
        @endif
        @if($isCoordinator || (auth()->check() && (auth()->user()->hasAnyRole(['coordinator', 'Coordinator', 'admin', 'global_admin']) || auth()->user()->hasAnyPermission(['stores.view', 'stores.create', 'stores.edit', 'stores.delete']))))
        <li class="sidebar-nav-item">
            <a href="{{ route('stores.index') }}" class="sidebar-nav-link {{ request()->routeIs('stores.*') ? 'active' : '' }}">
                <i class="fa-solid fa-warehouse text-info"></i>
                <span>Stores</span>
            </a>
        </li>
        @endif
        @if($isCoordinator || (auth()->check() && (auth()->user()->hasAnyRole(['coordinator', 'Coordinator', 'admin', 'global_admin']) || auth()->user()->hasAnyPermission(['products.view', 'products.create', 'products.edit', 'products.delete']))))
        <li class="sidebar-nav-item">
            <a href="{{ route('products.index') }}" class="sidebar-nav-link {{ request()->routeIs('products.*') ? 'active' : '' }}">
                <i class="fa-solid fa-boxes-stacked text-warning"></i>
                <span>Products</span>
            </a>
        </li>
        @endif
        @endif
        @endif

        {{-- Inventory --}}
        @if(!auth()->check() || (!$isSiteStaffUser && !$isGeneralServiceUser && !$isStoreKeeper && !$isStoreManager && !$isAuditorUser))
        @if($isCoordinator || (auth()->check() && (auth()->user()->hasAnyRole(['coordinator', 'Coordinator', 'admin', 'global_admin']) || auth()->user()->can('inventory.view') || auth()->user()->hasAnyPermission(['inventory.view', 'inventory.view_all_stores', 'inventory.*']))))
        <li class="sidebar-nav-item">
            <a href="{{ route('inventory.index') }}" class="sidebar-nav-link {{ request()->routeIs('inventory.*') ? 'active' : '' }}">
                <i class="fa-solid fa-boxes-stacked text-primary"></i>
                <span>All Store Inventory</span>
            </a>
        </li>
        @endif
        @endif


        {{-- Store Keeper Dedicated Menu --}}
        @if($isStoreKeeper)
        <li class="sidebar-nav-item">
            <a href="{{ route('store-manager.inventory.all') }}" class="sidebar-nav-link {{ request()->routeIs('store-manager.inventory.*') ? 'active' : '' }}">
                <i class="fa-solid fa-boxes-stacked text-info"></i>
                <span>Inventory</span>
            </a>
        </li>
        <li class="sidebar-nav-item">
            <a href="{{ route('material-usages.index') }}" class="sidebar-nav-link {{ request()->routeIs('material-usages.*') ? 'active' : '' }}">
                <i class="fa-solid fa-boxes-packing text-primary"></i>
                <span>Daily Consumption (ዕለታዊ ፍጆታ)</span>
            </a>
        </li>
        <li class="sidebar-nav-item">
            <a href="{{ route('store-manager.material-requests.index') }}" class="sidebar-nav-link {{ request()->routeIs('store-manager.material-requests.*') ? 'active' : '' }}">
                <i class="fa-solid fa-clipboard-list text-danger"></i>
                <span>Material Requests</span>
            </a>
        </li>
        <li class="sidebar-nav-item">
            <a href="{{ route('store-manager.transfers.index') }}" class="sidebar-nav-link {{ request()->routeIs('store-manager.transfers.*') || request()->routeIs('transfers.*') ? 'active' : '' }}">
                <i class="fa-solid fa-truck-moving text-warning"></i>
                <span>Material Transfers</span>
                @php
                    $skPendingTransferCount = 0;
                    try {
                        $skStoreId = auth()->user()->store_id ?? \App\Models\Store::where('manager_id', auth()->id())->value('id');
                        if ($skStoreId) {
                            $skPendingTransferCount = \App\Models\Transfer::where(function($q) use ($skStoreId) {
                                $q->where('from_store_id', $skStoreId)->where('status', 'approved')
                                  ->orWhere(function($sq) use ($skStoreId) {
                                      $sq->where('to_store_id', $skStoreId)->where('status', 'in_transit');
                                  });
                            })->count();
                        }
                    } catch (\Throwable $e) {}
                @endphp
                @if($skPendingTransferCount > 0)
                    <span class="badge bg-danger rounded-pill ms-auto">{{ $skPendingTransferCount }}</span>
                @endif
            </a>
        </li>
        <li class="sidebar-nav-item">
            <a href="{{ \Illuminate\Support\Facades\Route::has('store-keeper.weekly-material-demand') ? route('store-keeper.weekly-material-demand') : (\Illuminate\Support\Facades\Route::has('store-manager.weekly-material-demand') ? route('store-manager.weekly-material-demand') : url('/store-keeper/weekly-material-demand')) }}" class="sidebar-nav-link {{ request()->routeIs('*weekly-material-demand*') ? 'active' : '' }}">
                <i class="fa-solid fa-calendar-check text-success"></i>
                <span>Weekly Material Demand</span>
            </a>
        </li>
        <li class="sidebar-nav-item">
            <a href="{{ route('expense-requests.index') }}" class="sidebar-nav-link {{ request()->routeIs('expense-requests.*') ? 'active' : '' }}">
                <i class="fa-solid fa-hand-holding-dollar text-success"></i>
                <span>Petty Cash</span>
            </a>
        </li>
        @endif

        {{-- Store Hub (Central Store Manager / Admins / Coordinators) --}}
        @if(auth()->check() && !$isGeneralServiceUser && !$isStoreKeeper && (auth()->user()->hasAnyRole(['store_manager', 'admin', 'global_admin', 'coordinator', 'Coordinator']) || $isCoordinator))

        @if(!$isStoreManager && !$isCoordinator)
        <li class="sidebar-nav-item">
            <a href="{{ route('dashboard.store-manager') }}" class="sidebar-nav-link {{ request()->routeIs('dashboard.store-manager') || request()->routeIs('store-manager.dashboard') ? 'active' : '' }}">
                <i class="fa-solid fa-gauge-high text-primary"></i>
                <span>Store Dashboard</span>
            </a>
        </li>
        @endif
        <li class="sidebar-nav-item">
            <a href="{{ route('store-manager.inventory.all') }}" class="sidebar-nav-link {{ request()->routeIs('store-manager.inventory.*') ? 'active' : '' }}">
                <i class="fa-solid fa-warehouse text-info"></i>
                <span>All Store Inventory</span>
            </a>
        </li>
        <li class="sidebar-nav-item">
            <a href="{{ route('material-usages.index') }}" class="sidebar-nav-link {{ request()->routeIs('material-usages.*') ? 'active' : '' }}">
                <i class="fa-solid fa-boxes-packing text-primary"></i>
                <span>Daily Consumption</span>
            </a>
        </li>
        <li class="sidebar-nav-item">
            <a href="{{ route('store-manager.store-keepers.index') }}" class="sidebar-nav-link {{ request()->routeIs('store-manager.store-keepers.*') ? 'active' : '' }}">
                <i class="fa-solid fa-users-gear text-success"></i>
                <span>Assign Store Keepers</span>
            </a>
        </li>

        <li class="sidebar-nav-item">
            <a href="{{ route('store-manager.fixed-assets.index') }}" class="sidebar-nav-link {{ request()->routeIs('store-manager.fixed-assets.*') ? 'active' : '' }}">
                <i class="fa-solid fa-truck-monster text-warning"></i>
                <span>Fixed Assets</span>
                @php
                    $fixedUnitsCount = cache()->remember('sidebar_fixed_asset_units_count', 60, function() {
                        try {
                            return \App\Models\FixedAssetUnit::count();
                        } catch (\Throwable $e) {
                            return 0;
                        }
                    });
                @endphp
                @if($fixedUnitsCount > 0)
                    <span class="badge bg-warning text-dark rounded-pill ms-auto">{{ $fixedUnitsCount }}</span>
                @endif
            </a>
        </li>
        <li class="sidebar-nav-item">
            <a href="{{ route('store-manager.transfers.create') }}" class="sidebar-nav-link {{ request()->routeIs('store-manager.transfers.create') ? 'active' : '' }}">
                <i class="fa-solid fa-exchange-alt text-success"></i>
                <span>Create Transfer</span>
            </a>
        </li>
        <li class="sidebar-nav-item">
            <a href="{{ route('store-manager.transfers.index') }}" class="sidebar-nav-link {{ (request()->routeIs('store-manager.transfers.index') && !request()->has('tab')) || request()->routeIs('store-manager.transfers.show') ? 'active' : '' }}">
                <i class="fa-solid fa-truck-moving text-warning"></i>
                <span>Transfers &amp; Drivers</span>
                @php
                    $smTransferPendingCount = 0;
                    try {
                        $smTransferPendingCount = \App\Models\Transfer::where(function($q) {
                            $q->whereIn('status', ['draft', 'pending_approval'])
                              ->orWhereNull('driver_employee_id');
                        })->count();
                    } catch (\Throwable $e) {}
                @endphp
                @if($smTransferPendingCount > 0)
                    <span class="badge bg-warning text-dark rounded-pill ms-auto" title="Pending Driver Assignment">{{ $smTransferPendingCount }}</span>
                @endif
            </a>
        </li>
        <li class="sidebar-nav-item">
            <a href="{{ route('store-manager.transfers.index', ['tab' => 'in_transit']) }}" class="sidebar-nav-link {{ request()->input('tab') === 'in_transit' || request()->input('tab') === 'assigned_drivers' ? 'active' : '' }}">
                <i class="fa-solid fa-truck-fast text-info"></i>
                <span>Driver &amp; Dispatch Status</span>
                @php
                    $smInTransitCount = 0;
                    try {
                        $smInTransitCount = \App\Models\Transfer::where('status', 'in_transit')->count();
                    } catch (\Throwable $e) {}
                @endphp
                @if($smInTransitCount > 0)
                    <span class="badge bg-info text-dark rounded-pill ms-auto" title="In-Transit with Driver">{{ $smInTransitCount }}</span>
                @endif
            </a>
        </li>
        <li class="sidebar-nav-item">
            <a href="{{ route('store-manager.material-requests.index') }}" class="sidebar-nav-link {{ request()->routeIs('store-manager.material-requests.*') ? 'active' : '' }}">
                <i class="fa-solid fa-clipboard-list text-danger"></i>
                <span>Material Requests</span>
            </a>
        </li>
        <li class="sidebar-nav-item">
            <a href="{{ route('procurement.my-queue') }}" class="sidebar-nav-link {{ request()->routeIs('procurement.my-queue') ? 'active' : '' }}">
                <i class="fa-solid fa-tasks text-warning"></i>
                <span>Procurement My Queue</span>
                @php
                    $smQueueCount = 0;
                    try {
                        $smQueueCount = \App\Models\PurchaseRequest::where('current_owner_role', 'store_manager')->count();
                    } catch (\Throwable $e) {}
                @endphp
                @if($smQueueCount > 0)
                    <span class="badge bg-danger rounded-pill ms-auto">{{ $smQueueCount }}</span>
                @endif
            </a>
        </li>
        <li class="sidebar-nav-item">
            <a href="{{ route('store-manager.issued.index') }}" class="sidebar-nav-link {{ request()->routeIs('store-manager.issued.*') ? 'active' : '' }}">
                <i class="fa-solid fa-hand-holding text-warning"></i>
                <span>Issued Materials</span>
            </a>
        </li>
        <li class="sidebar-nav-item">
            <a href="{{ route('store-manager.products.index') }}" class="sidebar-nav-link {{ request()->routeIs('store-manager.products.*') ? 'active' : '' }}">
                <i class="fa-solid fa-book text-primary"></i>
                <span>Material Catalog</span>
            </a>
        </li>

        @if(auth()->user()->hasAnyRole(['store_manager', 'admin', 'global_admin']))
        <li class="sidebar-nav-item">
            <a href="{{ route('store-manager.slip-sequences.index') }}" class="sidebar-nav-link {{ request()->routeIs('store-manager.slip-sequences.*') ? 'active' : '' }}">
                <i class="fa-solid fa-stream text-info"></i>
                <span>Slip Sequences</span>
            </a>
        </li>
        @endif
        @endif

        {{-- Planning Section --}}
        @if(auth()->user() && !$isSiteStaffUser && !$isGeneralServiceUser && !$isSecretary && !$isContractAdmin && !$isStoreKeeper && !$isAuditorUser && (auth()->user()->hasAnyPermission(['planning.boq.manage', 'boq.view', 'boq.create', 'schedule.view', 'schedule.approve', 'schedule.create', 'schedule.edit', 'schedule.*', 'planning.view', 'planning.*', 'takeoff.view', 'takeoff.create', 'takeoff.edit', 'takeoff.*', 'resources.dispatch', 'material_planning.view', 'material_planning.*', 'material_requests.view', 'material_requests.create', 'material_requests.approve', 'material_requests.issue', 'material_requests.*', 'reports.view', 'reports.weekly.view', 'reports.*.view', 'finance.budgets.manage']) || auth()->user()->hasRole(['planning_manager', 'planning', 'technical_manager'])))


        @role('planning_manager|planning|technical_manager')
        <li class="sidebar-nav-item">
            <a href="{{ route('planning-manager.emergency-requests') }}"
               class="sidebar-nav-link {{ request()->routeIs('planning-manager.emergency-requests*') ? 'active' : '' }}">
                <i class="fa-solid fa-bell-exclamation" style="color:#f87171;"></i>
                <span>Emergency Approvals</span>
            </a>
        </li>
        <li class="sidebar-nav-item">
            <a href="{{ route('planning-manager.manpower-reports') }}"
               class="sidebar-nav-link {{ request()->routeIs('planning-manager.manpower-reports*') ? 'active' : '' }}">
                <i class="fa-solid fa-users-line" style="color:#60a5fa;"></i>
                <span>Morning Manpower Reports</span>
                @php
                    try {
                        $pendingMpCount = \App\Models\ManpowerDailyReport::where('status', 'pending')->count();
                    } catch (\Throwable $e) { $pendingMpCount = 0; }
                @endphp
                @if($pendingMpCount > 0)
                    <span class="badge bg-warning text-dark ms-auto rounded-pill">{{ $pendingMpCount }}</span>
                @endif
            </a>
        </li>
        @role('planning_manager')
        <li class="sidebar-nav-item">
            <a href="{{ route('planning.team.index') }}"
               class="sidebar-nav-link {{ request()->routeIs('planning.team.*') ? 'active' : '' }}">
                <i class="fa-solid fa-users-gear" style="color:#f472b6;"></i>
                <span>Assign Team</span>
            </a>
        </li>
        @endrole
        <li class="sidebar-nav-item">
            <a href="{{ route('planning-manager.resource-report') }}"
               class="sidebar-nav-link {{ request()->routeIs('planning-manager.resource-report*') ? 'active' : '' }}">
                <i class="fa-solid fa-chart-bar" style="color:#34d399;"></i>
                <span>Resource Report</span>
            </a>
        </li>
        <li class="sidebar-nav-item">
            <a href="{{ route('planning-manager.weekly-plan-setup') }}"
               class="sidebar-nav-link {{ request()->routeIs('planning-manager.weekly-plan-setup*') ? 'active' : '' }}">
                <i class="fa-solid fa-calendar-check" style="color:#a78bfa;"></i>
                <span>Weekly Plan Setup</span>
            </a>
        </li>
        @endrole

        @canany(['planning.boq.manage', 'boq.view', 'boq.create'])
        <li class="sidebar-nav-item">
            <a href="{{ route('boqs.index') }}" class="sidebar-nav-link {{ request()->routeIs('boqs.*') ? 'active' : '' }}">
                <i class="fa-solid fa-file-invoice-dollar"></i>
                <span>BOQ</span>
            </a>
        </li>
        @endcanany
        @canany(['schedule.view', 'schedule.approve', 'schedule.create', 'schedule.edit', 'schedule.*'])
        <li class="sidebar-nav-item">
            <a href="{{ route('schedules.index') }}" class="sidebar-nav-link {{ request()->routeIs('schedules.*') ? 'active' : '' }}">
                <i class="fa-solid fa-calendar-days"></i>
                <span>Schedules</span>
            </a>
        </li>
        @endcanany
        @canany(['planning.view', 'planning.*'])
        <li class="sidebar-nav-item">
            <a href="{{ route('erp-plans.index') }}" class="sidebar-nav-link {{ request()->routeIs('erp-plans.*') ? 'active' : '' }}">
                <i class="fa-solid fa-diagram-project"></i>
                <span>ERP Plans</span>
            </a>
        </li>
        @endcanany
        @hasanyrole('planning_manager|planning|technical_manager|admin|global_admin')
        <li class="sidebar-nav-item">
            <a href="{{ route('standard-works.index') }}" class="sidebar-nav-link {{ request()->routeIs('standard-works.*') ? 'active' : '' }}">
                <i class="fa-solid fa-ruler-combined"></i>
                <span>Standard Work</span>
            </a>
        </li>
        @endhasanyrole
        @canany(['takeoff.view', 'takeoff.create', 'takeoff.edit', 'takeoff.*'])
        <li class="sidebar-nav-item">
            <a href="{{ route('takeoff.index') }}" class="sidebar-nav-link {{ request()->routeIs('takeoff.*') ? 'active' : '' }}">
                <i class="fa-solid fa-ruler-combined"></i>
                <span>Quantity Takeoff</span>
            </a>
        </li>
        @endcanany
        @canany(['resources.dispatch', 'planning.*'])
        <li class="sidebar-nav-item">
            <a href="{{ route('dispatches.index') }}" class="sidebar-nav-link {{ request()->routeIs('dispatches.*') ? 'active' : '' }}">
                <i class="fa-solid fa-truck-fast"></i>
                <span>Weekly Dispatches</span>
            </a>
        </li>
        @endcanany
        @canany(['material_planning.view', 'material_planning.*', 'planning.*'])
        <li class="sidebar-nav-item">
            <a href="{{ route('material-plans.index') }}" class="sidebar-nav-link {{ request()->routeIs('material-plans.*') ? 'active' : '' }}">
                <i class="fa-solid fa-list-check"></i>
                <span>Material Plans</span>
            </a>
        </li>
        @endcanany
        @canany(['material_damage_reports.view', 'material_damage_reports.create', 'material_damage_reports.*'])
        @if(!auth()->user()->hasAnyRole(['general_service', 'general_services']))
        <li class="sidebar-nav-item">
            <a href="{{ route('material-damage-reports.index') }}" class="sidebar-nav-link {{ request()->routeIs('material-damage-reports.*') ? 'active' : '' }}">
                <i class="fa-solid fa-triangle-exclamation"></i>
                <span>Damage Reports</span>
            </a>
        </li>
        @endif
        @endcanany
        @canany(['tool_transactions.view', 'tool_transactions.create', 'tool_transactions.*'])
        <li class="sidebar-nav-item">
            <a href="{{ route('tool-transactions.index') }}" class="sidebar-nav-link {{ request()->routeIs('tool-transactions.*') ? 'active' : '' }}">
                <i class="fa-solid fa-toolbox"></i>
                <span>Tool Check-out</span>
            </a>
        </li>
        @endcanany
        @if(auth()->check() && (auth()->user()->hasAnyRole(['admin', 'global_admin', 'planning_manager', 'planning', 'technical_manager', 'general_manager', 'gm', 'project_manager']) || auth()->user()->canAny(['finance.budgets.manage', 'planning.*'])))
        <li class="sidebar-nav-item">
            <a href="{{ route('budgets.index') }}" class="sidebar-nav-link {{ request()->routeIs('budgets.*') ? 'active' : '' }}">
                <i class="fa-solid fa-sack-dollar text-warning"></i>
                <span>Project Budgets</span>
            </a>
        </li>
        @endif
        @endcanany

        {{-- Procurement / Stores --}}
        @if(auth()->check() && !$isSiteStaffUser && !$isGeneralServiceUser && !$isSecretary && !$isContractAdmin && !$isStoreKeeper && !$isStoreManager && !$isAuditorUser && (auth()->user()->hasAnyRole(['Purchase Manager', 'purchase_manager', 'admin', 'global_admin']) || auth()->user()->canAny(['inventory.view', 'inventory.*', 'purchases.suppliers.manage', 'suppliers.*', 'material_requests.view', 'material_requests.create', 'material_requests.approve', 'material_requests.issue', 'material_requests.*', 'purchases.requests.create', 'purchases.view', 'purchases.receive', 'purchases.*', 'transfers.view', 'transfers.*'])))


        @if(!auth()->user()->hasAnyRole(['planning_manager', 'planning']) && auth()->user()->hasAnyRole(['Purchase Manager', 'purchase_manager', 'admin', 'global_admin', 'gm', 'general_manager']))
        <li class="sidebar-nav-item">
            <a href="{{ route('dashboard.purchase') }}" class="sidebar-nav-link {{ request()->routeIs('dashboard.purchase') ? 'active' : '' }}">
                <i class="fa-solid fa-chart-line text-info"></i>
                <span>Purchase Dashboard</span>
            </a>
        </li>
        @endif

        @if(!auth()->user()->hasAnyRole(['planning_manager', 'planning']))
        <li class="sidebar-nav-item">
            <a href="{{ route('products.index') }}" class="sidebar-nav-link {{ request()->routeIs('products.*') ? 'active' : '' }}">
                <i class="fa-solid fa-box text-warning"></i>
                <span>Material Catalog</span>
            </a>
        </li>
        @endif

        @if(!auth()->user()->hasAnyRole(['planning_manager', 'planning']) && auth()->user()->hasAnyRole(['Purchase Manager', 'purchase_manager', 'admin', 'global_admin', 'finance_manager', 'gm', 'general_manager', 'marketing']))
        <li class="sidebar-nav-item">
            <a href="{{ route('price-intelligence.index') }}" class="sidebar-nav-link {{ request()->routeIs('price-intelligence.*') ? 'active' : '' }}">
                <i class="fa-solid fa-chart-line text-success"></i>
                <span>Price Intelligence</span>
            </a>
        </li>
        @endif
        <li class="sidebar-nav-item">
            <a href="{{ route('material-demand.index') }}" class="sidebar-nav-link {{ request()->routeIs('material-demand.*') ? 'active' : '' }}">
                <i class="fa-solid fa-cubes text-primary"></i>
                <span>Material Demand</span>
            </a>
        </li>
        @canany(['purchases.suppliers.manage', 'suppliers.*', 'purchases.*'])
        <li class="sidebar-nav-item">
            <a href="{{ route('suppliers.index') }}" class="sidebar-nav-link {{ request()->routeIs('suppliers.*') ? 'active' : '' }}">
                <i class="fa-solid fa-truck"></i>
                <span>Suppliers</span>
            </a>
        </li>
        @endcanany
        @canany(['material_requests.view', 'material_requests.create', 'material_requests.approve', 'material_requests.issue', 'material_requests.*'])
        <li class="sidebar-nav-item">
            <a href="{{ route('material-requests.index') }}" class="sidebar-nav-link {{ request()->routeIs('material-requests.*') ? 'active' : '' }}">
                <i class="fa-solid fa-cart-flatbed"></i>
                <span>Material Requests</span>
            </a>
        </li>
        @endcanany
        <li class="sidebar-nav-item">
            <a href="{{ route('procurement.my-queue') }}" class="sidebar-nav-link {{ request()->routeIs('procurement.my-queue') ? 'active' : '' }}">
                <i class="fa-solid fa-tasks text-warning"></i>
                <span>Procurement My Queue</span>
            </a>
        </li>
        @canany(['purchases.requests.create', 'purchases.*'])
        <li class="sidebar-nav-item">
            <a href="{{ route('purchase-requests.index') }}" class="sidebar-nav-link {{ request()->routeIs('purchase-requests.*') ? 'active' : '' }}">
                <i class="fa-solid fa-file-invoice"></i>
                <span>Purchase Requests</span>
            </a>
        </li>
        @endcanany
        @canany(['purchases.view', 'purchases.*'])
        <li class="sidebar-nav-item">
            <a href="{{ route('purchase-orders.index') }}" class="sidebar-nav-link {{ request()->routeIs('purchase-orders.*') ? 'active' : '' }}">
                <i class="fa-solid fa-file-contract"></i>
                <span>Purchase Orders</span>
            </a>
        </li>
        @endcanany
        @canany(['purchases.receive', 'purchases.*'])
        <li class="sidebar-nav-item">
            <a href="{{ route('delivery-receipts.index') }}" class="sidebar-nav-link {{ request()->routeIs('delivery-receipts.*') ? 'active' : '' }}">
                <i class="fa-solid fa-boxes-packing"></i>
                <span>Delivery Receipts</span>
            </a>
        </li>
        @endcanany
        @canany(['transfers.view', 'transfers.*'])
        <li class="sidebar-nav-item">
            <a href="{{ route('transfers.index') }}" class="sidebar-nav-link {{ request()->routeIs('transfers.*') ? 'active' : '' }}">
                <i class="fa-solid fa-exchange-alt"></i>
                <span>Transfers</span>
            </a>
        </li>
        @endcanany
        @endcanany

        {{-- ── Marketing & Pricing (Hidden from Planning Manager & Finance Staff) ───────────────────────── --}}
        @if(auth()->check() && !$isAuditorUser && auth()->user()->hasAnyRole(['marketing', 'admin', 'global_admin']) && !auth()->user()->hasAnyRole(['planning_manager', 'planning', 'contract_admin', 'secretary', 'store_keeper']))
        <li class="sidebar-nav-item sidebar-section-label" style="padding:8px 16px 4px; font-size:10px; font-weight:700; letter-spacing:.08em; text-transform:uppercase; color:#94a3b8; pointer-events:none; user-select:none;">Marketing &amp; Pricing</li>
        <li class="sidebar-nav-item">
            <a href="{{ route('marketing.dashboard') }}" class="sidebar-nav-link {{ request()->routeIs('marketing.dashboard') ? 'active' : '' }}">
                <i class="fa-solid fa-bullhorn text-primary"></i>
                <span>Marketing Dashboard</span>
            </a>
        </li>
        @if(auth()->user()->hasAnyRole(['marketing', 'admin', 'global_admin']))
        <li class="sidebar-nav-item">
            <a href="{{ route('marketing.prices.create') }}" class="sidebar-nav-link {{ request()->routeIs('marketing.prices.create') ? 'active' : '' }}">
                <i class="fa-solid fa-calendar-plus text-success"></i>
                <span>Price Update</span>
            </a>
        </li>
        @endif
        <li class="sidebar-nav-item">
            <a href="{{ route('marketing.prices.history') }}" class="sidebar-nav-link {{ request()->routeIs('marketing.prices.history') ? 'active' : '' }}">
                <i class="fa-solid fa-clock-rotate-left text-info"></i>
                <span>Price History &amp; Trends</span>
            </a>
        </li>
        <li class="sidebar-nav-item">
            <a href="{{ route('marketing.reports.inflation') }}" class="sidebar-nav-link {{ request()->routeIs('marketing.reports.inflation') ? 'active' : '' }}">
                <i class="fa-solid fa-chart-line text-danger"></i>
                <span>Inflation Report</span>
            </a>
        </li>
        @endif

        {{-- Planning vs Actual (Available to Planning Manager, PMs, Finance Head, Marketing, Admins) --}}
        @if(auth()->check() && !$isAuditorUser && auth()->user()->hasAnyRole(['marketing', 'admin', 'global_admin', 'planning_manager', 'planning', 'finance_head', 'Finance head', 'project_manager', 'gm', 'general_manager']) && !auth()->user()->hasAnyRole(['contract_admin', 'secretary', 'store_keeper']))

        <li class="sidebar-nav-item">
            <a href="{{ route('marketing.reports.planning-vs-actual') }}" class="sidebar-nav-link {{ request()->routeIs('marketing.reports.planning-vs-actual') ? 'active' : '' }}">
                <i class="fa-solid fa-scale-balanced text-warning"></i>
                <span>Planning vs Actual</span>
            </a>
        </li>
        @endif

        {{-- Coordinator Tools --}}
        @if(auth()->check() && (auth()->user()->hasAnyRole(['Coordinator', 'coordinator', 'admin', 'global_admin']) || in_array('coordinator', $rawUserRoles)))
        @if(!$isCoordinator)
        <li class="sidebar-nav-item">
            <a href="{{ route('dashboard.coordinator') }}" class="sidebar-nav-link {{ request()->routeIs('dashboard.coordinator') ? 'active' : '' }}">
                <i class="fa-solid fa-users-viewfinder text-primary"></i>
                <span>Coordinator Dashboard</span>
            </a>
        </li>
        @endif
        <li class="sidebar-nav-item">
            <a href="{{ route('expenses.index') }}?tab=pending_hr" class="sidebar-nav-link {{ (request()->routeIs('expenses.*') || request()->is('expenses*') || request()->routeIs('approvals.*')) && request('tab') === 'pending_hr' ? 'active' : '' }}">
                <i class="fa-solid fa-file-invoice-dollar text-warning"></i>
                <span>Expense Approvals</span>
                @php
                    $pendingCoordExpCount = 0;
                    try {
                        $pendingCoordExpCount = \App\Models\ExpenseRequest::where('status', \App\Models\ExpenseRequest::STATUS_PENDING_HR)->count();
                    } catch (\Exception $e) {}
                @endphp
                @if($pendingCoordExpCount > 0)
                    <span class="badge bg-warning text-dark rounded-pill ms-auto">{{ $pendingCoordExpCount }}</span>
                @endif
            </a>
        </li>
        <li class="sidebar-nav-item">
            <a href="{{ route('coordinator.forecast') }}" class="sidebar-nav-link {{ request()->routeIs('coordinator.forecast') ? 'active' : '' }}">
                <i class="fa-solid fa-chart-pie text-danger"></i>
                <span>Forecast Demand</span>
            </a>
        </li>
        @endif


        {{-- General Service & Operations Tools --}}
        @if(auth()->check() && ($isGeneralServiceUser || auth()->user()->hasAnyRole(['admin', 'global_admin'])))
        <li class="sidebar-heading">General Service</li>
        @if(!$isGeneralServiceUser)
        <li class="sidebar-nav-item">
            <a href="{{ route('dashboard.general_service') }}" class="sidebar-nav-link {{ request()->routeIs('dashboard.general_service') || request()->routeIs('general-service.*') ? 'active' : '' }}">
                <i class="fa-solid fa-screwdriver-wrench text-warning"></i>
                <span>General Service Hub</span>
            </a>
        </li>
        @endif
        <li class="sidebar-nav-item">
            <a href="{{ route('general-service.maintenance.index') }}" class="sidebar-nav-link {{ request()->routeIs('general-service.maintenance.*') ? 'active' : '' }}">
                <i class="fa-solid fa-wrench text-danger"></i>
                <span>Maintenance Requests</span>
                @php
                    $pendingMaintCount = 0;
                    try {
                        $pendingMaintCount = \App\Models\MaintenanceRequest::whereIn('status', ['pending', 'sent_to_store_manager'])->count();
                    } catch (\Exception $e) {}
                @endphp
                @if($pendingMaintCount > 0)
                    <span class="badge bg-warning text-dark ms-auto">{{ $pendingMaintCount }}</span>
                @endif
            </a>
        </li>
        <li class="sidebar-nav-item">
            <a href="{{ route('store-manager.fixed-assets.index') }}" class="sidebar-nav-link {{ request()->routeIs('store-manager.fixed-assets.*') ? 'active' : '' }}">
                <i class="fa-solid fa-truck-monster text-primary"></i>
                <span>Workshop &amp; Fixed Assets</span>
            </a>
        </li>
        <li class="sidebar-nav-item">
            <a href="{{ route('material-damage-reports.index') }}" class="sidebar-nav-link {{ request()->routeIs('material-damage-reports.*') ? 'active' : '' }}">
                <i class="fa-solid fa-triangle-exclamation text-warning"></i>
                <span>Material Damage Reports</span>
            </a>
        </li>
        <li class="sidebar-nav-item">
            <a href="{{ route('transfers.index') }}" class="sidebar-nav-link {{ request()->routeIs('transfers.*') ? 'active' : '' }}">
                <i class="fa-solid fa-dolly text-info"></i>
                <span>Store Transfers &amp; Logistics</span>
            </a>
        </li>
        @endif

        {{-- Site Engineer Tools --}}
        @if(auth()->check() && (auth()->user()->hasAnyRole(['site_engineer', 'admin', 'global_admin'])))

        <li class="sidebar-nav-item">
            <a href="{{ route('dashboard.site-engineer') }}" class="sidebar-nav-link {{ request()->routeIs('dashboard.site-engineer') ? 'active' : '' }}">
                <i class="fa-solid fa-hard-hat text-warning"></i>
                <span>Site Engineer Dashboard</span>
            </a>
        </li>
        <li class="sidebar-nav-item">
            <a href="{{ route('manpower-daily-report.create') }}" class="sidebar-nav-link {{ request()->routeIs('manpower-daily-report.create') ? 'active' : '' }}">
                <i class="fa-solid fa-users-line text-primary"></i>
                <span>Submit Manpower Report</span>
            </a>
        </li>
        <li class="sidebar-nav-item">
            <a href="{{ route('manpower-daily-report.index') }}" class="sidebar-nav-link {{ request()->routeIs('manpower-daily-report.index') || request()->routeIs('manpower-daily-report.show') ? 'active' : '' }}">
                <i class="fa-solid fa-clipboard-user text-info"></i>
                <span>My Manpower Reports</span>
            </a>
        </li>
        <li class="sidebar-nav-item">
            <a href="{{ route('material-requests.create', ['source' => 'Emergency']) }}" class="sidebar-nav-link {{ request()->fullUrlIs('*source=Emergency*') ? 'active' : '' }}">
                <i class="fa-solid fa-bolt text-danger"></i>
                <span>Ask Emergency MR</span>
            </a>
        </li>

        <li class="sidebar-nav-item">
            <a href="{{ route('dispatches.index') }}" class="sidebar-nav-link {{ request()->routeIs('dispatches.*') ? 'active' : '' }}">
                <i class="fa-solid fa-calendar-week text-info"></i>
                <span>Weekly Plans</span>
            </a>
        </li>
        <li class="sidebar-nav-item">
            <a href="{{ route('daily-reports.index') }}" class="sidebar-nav-link {{ request()->routeIs('daily-reports.*') ? 'active' : '' }}">
                <i class="fa-solid fa-file-signature text-success"></i>
                <span>Daily Reports</span>
            </a>
        </li>
        <li class="sidebar-nav-item">
            <a href="{{ route('weekly-reports.index') }}" class="sidebar-nav-link {{ request()->routeIs('weekly-reports.*') ? 'active' : '' }}">
                <i class="fa-solid fa-chart-bar text-primary"></i>
                <span>Weekly Reports</span>
            </a>
        </li>
        <li class="sidebar-nav-item">
            <a href="{{ route('attendance.index') }}" class="sidebar-nav-link {{ request()->routeIs('attendance.*') ? 'active' : '' }}">
                <i class="fa-solid fa-user-check text-warning"></i>
                <span>Site Attendance</span>
            </a>
        </li>
        <li class="sidebar-nav-item">
            <a href="{{ route('issues.index') }}" class="sidebar-nav-link {{ request()->routeIs('issues.*') ? 'active' : '' }}">
                <i class="fa-solid fa-triangle-exclamation text-danger"></i>
                <span>Site Issues</span>
            </a>
        </li>
        <li class="sidebar-nav-item">
            <a href="{{ route('boqs.index') }}" class="sidebar-nav-link {{ request()->routeIs('boqs.*') ? 'active' : '' }}">
                <i class="fa-solid fa-file-invoice-dollar text-danger"></i>
                <span>Site BOQ</span>
            </a>
        </li>
        <li class="sidebar-nav-item">
            <a href="{{ route('ipcs.index') }}" class="sidebar-nav-link {{ request()->routeIs('ipcs.*') ? 'active' : '' }}">
                <i class="fa-solid fa-money-check-dollar text-success"></i>
                <span>Takeoffs & Payments</span>
            </a>
        </li>
        @endif

        {{-- Foreman Tools --}}
        @if(auth()->check() && (auth()->user()->hasAnyRole(['foreman', 'admin', 'global_admin'])))

        <li class="sidebar-nav-item">
            <a href="{{ route('dashboard.foreman') }}" class="sidebar-nav-link {{ request()->routeIs('dashboard.foreman') ? 'active' : '' }}">
                <i class="fa-solid fa-hard-hat text-primary"></i>
                <span>Foreman Dashboard</span>
            </a>
        </li>
        <li class="sidebar-nav-item">
            <a href="{{ route('attendance.index') }}" class="sidebar-nav-link {{ request()->routeIs('attendance.*') ? 'active' : '' }}">
                <i class="fa-solid fa-users text-success"></i>
                <span>Team Attendance</span>
            </a>
        </li>
        <li class="sidebar-nav-item">
            <a href="{{ route('material-requests.index') }}" class="sidebar-nav-link {{ request()->routeIs('material-requests.*') ? 'active' : '' }}">
                <i class="fa-solid fa-cart-flatbed text-danger"></i>
                <span>Material Requests</span>
            </a>
        </li>
        <li class="sidebar-nav-item">
            <a href="{{ route('daily-reports.index') }}" class="sidebar-nav-link {{ request()->routeIs('daily-reports.*') ? 'active' : '' }}">
                <i class="fa-solid fa-file-signature text-success"></i>
                <span>Daily Reports</span>
            </a>
        </li>
        <li class="sidebar-nav-item">
            <a href="{{ route('issues.index') }}" class="sidebar-nav-link {{ request()->routeIs('issues.*') ? 'active' : '' }}">
                <i class="fa-solid fa-triangle-exclamation text-warning"></i>
                <span>Report Issues</span>
            </a>
        </li>
        @endif

        {{-- Engineer Work Schedule --}}
        @if(auth()->check() && auth()->user()->hasAnyRole(['admin', 'global_admin', 'planning_manager', 'planning', 'technical_manager', 'site_engineer', 'foreman']))

        {{-- Planners: Full calendar / management view --}}
        @if(auth()->user()->hasAnyRole(['admin', 'global_admin', 'planning_manager', 'planning', 'technical_manager']))
        <li class="sidebar-nav-item">
            <a href="{{ route('eng-schedule.index') }}" class="sidebar-nav-link {{ request()->routeIs('eng-schedule.index') ? 'active' : '' }}">
                <i class="fa-solid fa-calendar-days text-primary"></i>
                <span>Engineer Schedule</span>
            </a>
        </li>
        <li class="sidebar-nav-item">
            <a href="{{ route('eng-schedule.create') }}" class="sidebar-nav-link {{ request()->routeIs('eng-schedule.create') ? 'active' : '' }}">
                <i class="fa-solid fa-plus-circle text-success"></i>
                <span>Assign Work Order</span>
            </a>
        </li>
        @endif
        {{-- Engineers / Foremen: Personal view only --}}
        <li class="sidebar-nav-item">
            <a href="{{ route('eng-schedule.my') }}" class="sidebar-nav-link {{ request()->routeIs('eng-schedule.my') ? 'active' : '' }}">
                <i class="fa-solid fa-calendar-check text-warning"></i>
                <span>My Work Schedule</span>
            </a>
        </li>
        @endif

        {{-- Operational --}}
        @if(!$isContractAdmin && !$isSecretary && !$isStoreKeeper && !$isAuditorUser && (!auth()->check() || !auth()->user()->hasAnyRole(['finance', 'Finance', 'cashier', 'accountant'])))
        @canany(['material_usage.view', 'material_usage.*', 'cut_optimization.view_results', 'cut_optimization.*', 'issues.view', 'issues.create', 'issues.resolve', 'issues.*', 'waste.view', 'waste.create', 'waste.*', 'reports.daily.view', 'reports.daily.create', 'reports.weekly.view', 'reports.view', 'reports.*.view'])

        @canany(['material_usage.view', 'material_usage.*'])
        <li class="sidebar-nav-item">
            <a href="{{ route('material-usages.index') }}" class="sidebar-nav-link {{ request()->routeIs('material-usages.*') ? 'active' : '' }}">
                <i class="fa-solid fa-screwdriver-wrench"></i>
                <span>Material Usages</span>
            </a>
        </li>
        @endcanany
        @canany(['cut_optimization.view_results', 'cut_optimization.*'])
        <li class="sidebar-nav-item">
            <a href="{{ route('cut-optimizations.index') }}" class="sidebar-nav-link {{ request()->routeIs('cut-optimizations.*') ? 'active' : '' }}">
                <i class="fa-solid fa-scissors"></i>
                <span>Cut Optimization</span>
            </a>
        </li>
        @endcanany
        @canany(['issues.view', 'issues.create', 'issues.resolve', 'issues.*'])
        <li class="sidebar-nav-item">
            <a href="{{ route('issues.index') }}" class="sidebar-nav-link {{ request()->routeIs('issues.*') ? 'active' : '' }}">
                <i class="fa-solid fa-triangle-exclamation"></i>
                <span>Issues</span>
            </a>
        </li>
        @endcanany
        @canany(['waste.view', 'waste.create', 'waste.*'])
        <li class="sidebar-nav-item">
            <a href="{{ route('waste.index') }}" class="sidebar-nav-link {{ request()->routeIs('waste.*') ? 'active' : '' }}">
                <i class="fa-solid fa-trash-can"></i>
                <span>Waste</span>
            </a>
        </li>
        @endcanany
        @if(!auth()->user()->hasAnyRole(['site_engineer', 'foreman']))
        @canany(['reports.daily.view', 'reports.daily.create'])
        <li class="sidebar-nav-item">
            <a href="{{ route('daily-reports.index') }}" class="sidebar-nav-link {{ request()->routeIs('daily-reports.*') ? 'active' : '' }}">
                <i class="fa-solid fa-calendar-day"></i>
                <span>Daily Reports</span>
            </a>
        </li>
        @endcanany
        @canany(['reports.view', 'reports.weekly.view', 'reports.*.view'])
        <li class="sidebar-nav-item">
            <a href="{{ route('weekly-reports.index') }}" class="sidebar-nav-link {{ request()->routeIs('weekly-reports.*') ? 'active' : '' }}">
                <i class="fa-solid fa-calendar-week"></i>
                <span>Weekly Reports</span>
            </a>
        </li>
        @endcanany
        @endif
        @endcanany
        @endif

        {{-- Contract Admin Tools --}}
        @if(auth()->check() && !$isAuditorUser && (auth()->user()->hasAnyRole(['Contract admin', 'contract_admin', 'admin', 'global_admin'])))

        @if(!$isContractAdmin)
        <li class="sidebar-nav-item">
            <a href="{{ route('dashboard.contract-admin') }}" class="sidebar-nav-link {{ request()->routeIs('dashboard.contract-admin') ? 'active' : '' }}">
                <i class="fa-solid fa-gauge-high text-primary"></i>
                <span>Contract Dashboard</span>
            </a>
        </li>
        @endif
        <li class="sidebar-nav-item">
            <a href="{{ route('boqs.index') }}" class="sidebar-nav-link {{ request()->routeIs('boqs.*') ? 'active' : '' }}">
                <i class="fa-solid fa-file-invoice-dollar text-danger"></i>
                <span>Project BOQs</span>
            </a>
        </li>
        <li class="sidebar-nav-item">
            <a href="{{ route('ipcs.index') }}" class="sidebar-nav-link {{ request()->routeIs('ipcs.*') ? 'active' : '' }}">
                <i class="fa-solid fa-money-check-dollar text-success"></i>
                <span>IPCs &amp; Payments</span>
            </a>
        </li>
        <li class="sidebar-nav-item">
            <a href="{{ route('contracts.index') }}" class="sidebar-nav-link {{ request()->routeIs('contracts.*') ? 'active' : '' }}">
                <i class="fa-solid fa-file-contract text-warning"></i>
                <span>Contracts</span>
            </a>
        </li>
        <li class="sidebar-nav-item">
            <a href="{{ route('subcontractors.index') }}" class="sidebar-nav-link {{ request()->routeIs('subcontractors.*') ? 'active' : '' }}">
                <i class="fa-solid fa-handshake text-info"></i>
                <span>Subcontractors</span>
            </a>
        </li>
        <li class="sidebar-nav-item">
            <a href="{{ route('subcon-agreements.index') }}" class="sidebar-nav-link {{ request()->routeIs('subcon-agreements.*') ? 'active' : '' }}">
                <i class="fa-solid fa-file-signature text-primary"></i>
                <span>Subcon Agreements</span>
            </a>
        </li>
        @endif

        {{-- Finance --}}
        @if(auth()->check() && !$isAuditorUser && !auth()->user()->hasRole('site_engineer') && !$isContractAdmin && !$isStoreKeeper && (auth()->user()->hasAnyRole(['Finance head', 'finance_head', 'finance', 'admin', 'global_admin']) || auth()->user()->canAny(['finance.chart_of_accounts.view', 'finance.bank.manage', 'finance.income.view', 'finance.income.*', 'finance.expenses.view', 'finance.expenses.approve', 'finance.expenses.create', 'payments.view', 'payments.create', 'payments.approve', 'payments.*', 'subcon.view', 'subcon.create', 'subcon.edit', 'subcon.approve', 'subcon.*', 'finance.ipcs.manage', 'finance.*'])))


        @if(auth()->check() && auth()->user()->hasAnyRole(['Finance head', 'finance_head', 'admin', 'global_admin']))
        <li class="sidebar-nav-item">
            <a href="{{ route('coa.index') }}" class="sidebar-nav-link {{ request()->routeIs('coa.*') && !request()->routeIs('coa-transfers.*') ? 'active' : '' }}">
                <i class="fa-solid fa-sitemap"></i>
                <span>Chart of Accounts</span>
            </a>
        </li>
        <li class="sidebar-nav-item">
            <a href="{{ route('coa-transfers.index') }}" class="sidebar-nav-link {{ request()->routeIs('coa-transfers.*') ? 'active' : '' }}">
                <i class="fa-solid fa-money-bill-transfer text-success"></i>
                <span>COA Money Transfers</span>
            </a>
        </li>
        @endif
        <li class="sidebar-nav-item">
            <a href="{{ \Illuminate\Support\Facades\Route::has('assigned-accounts.index') ? route('assigned-accounts.index') : url('/assigned-accounts') }}" class="sidebar-nav-link {{ request()->is('assigned-accounts*') || request()->routeIs('assigned-accounts.*') ? 'active' : '' }}">
                <i class="fa-solid fa-briefcase text-primary"></i>
                <span>My Assigned Accounts</span>
            </a>
        </li>
        @if(auth()->check() && auth()->user()->hasAnyRole(['Finance head', 'finance_head', 'finance_manager', 'admin', 'global_admin']))
        <li class="sidebar-nav-item">
            <a href="{{ \Illuminate\Support\Facades\Route::has('finance.replenishments.index') ? route('finance.replenishments.index') : url('/finance/replenishments') }}" class="sidebar-nav-link {{ request()->is('finance/replenishments*') || request()->routeIs('finance.replenishments.*') ? 'active' : '' }}">
                <i class="fa-solid fa-hand-holding-dollar text-warning"></i>
                <span>Staff Replenishment Approvals</span>
                @php
                    $pendingReplenishCount = 0;
                    try {
                        if (\Illuminate\Support\Facades\Schema::hasTable('petty_cash_replenishments')) {
                            $pendingReplenishCount = \App\Models\PettyCashReplenishment::where('status', 'pending')->count();
                        }
                    } catch (\Exception $e) {}
                @endphp
                @if($pendingReplenishCount > 0)
                    <span class="badge bg-danger rounded-pill ms-auto">{{ $pendingReplenishCount }}</span>
                @endif
            </a>
        </li>
        @endif

        @if(auth()->check() && auth()->user()->hasAnyRole(['Finance head', 'finance_head', 'admin', 'global_admin']))
        <li class="sidebar-nav-item">
            <a href="{{ route('finance.payroll.index') }}" class="sidebar-nav-link {{ request()->routeIs('finance.payroll.*') ? 'active' : '' }}">
                <i class="fa-solid fa-money-bill-wave text-success"></i>
                <span>Payroll Management</span>
            </a>
        </li>

        <li class="sidebar-nav-item">
            <a href="{{ route('payroll.advances') }}" class="sidebar-nav-link {{ request()->routeIs('payroll.advances*') ? 'active' : '' }}">
                <i class="fa-solid fa-hand-holding-dollar text-warning"></i>
                <span>Salary Advance Loans</span>
            </a>
        </li>
        @endif
        <!-- Company Income -->
        @if(auth()->check() && auth()->user()->hasAnyRole(['Finance head', 'finance_head', 'admin', 'global_admin']))
        <li class="sidebar-nav-item">
            <a href="{{ route('income.index') }}" class="sidebar-nav-link {{ request()->routeIs('income.*') ? 'active' : '' }}">
                <i class="fa-solid fa-arrow-trend-up"></i>
                <span>Company Income</span>
            </a>
        </li>
        @endif
        <!-- Expenses Approvals -->
        <li class="sidebar-nav-item">
            <a href="{{ route('expenses.index') }}" class="sidebar-nav-link {{ request()->routeIs('expenses.*') || request()->is('expenses*') || request()->routeIs('approvals.*') ? 'active' : '' }}">
                <i class="fa-solid fa-file-invoice-dollar text-warning"></i>
                <span>Expense Approvals</span>
            </a>
        </li>

        <!-- Office Material Requests (Finance Head / Payment Tracking) -->
        @if(auth()->check() && auth()->user()->hasAnyRole(['Finance head', 'finance_head', 'finance', 'Finance', 'finance_manager', 'admin', 'global_admin']))
        <li class="sidebar-nav-item">
            <a href="{{ \Illuminate\Support\Facades\Route::has('office-requests.index') ? route('office-requests.index') : url('/office-requests') }}" class="sidebar-nav-link {{ request()->is('office-requests*') || request()->routeIs('office-requests.*') ? 'active' : '' }}">
                <i class="fa-solid fa-boxes-stacked" style="color: #7c3aed;"></i>
                <span>Office Material Requests</span>
                @php
                    $pendingFinOfficeReqCount = 0;
                    try {
                        if (\Illuminate\Support\Facades\Schema::hasTable('office_material_requests')) {
                            $pendingFinOfficeReqCount = \App\Models\OfficeMaterialRequest::whereIn('status', [
                                \App\Models\OfficeMaterialRequest::STATUS_APPROVED_BY_HR,
                                \App\Models\OfficeMaterialRequest::STATUS_ASSIGNED_TO_FINANCE
                            ])->count();
                        }
                    } catch (\Exception $e) {}
                @endphp
                @if($pendingFinOfficeReqCount > 0)
                    <span class="badge text-white rounded-pill ms-auto" style="background:#7c3aed;">{{ $pendingFinOfficeReqCount }}</span>
                @endif
            </a>
        </li>
        @endif

        <!-- Credit Store Ledger (Credit Purchases) -->
        @if(auth()->check() && auth()->user()->hasAnyRole(['Finance head', 'finance_head', 'finance', 'Finance', 'admin', 'global_admin', 'gm', 'general_manager']))
        <li class="sidebar-nav-item">
            <a href="{{ \Illuminate\Support\Facades\Route::has('finance.credit-store.index') ? route('finance.credit-store.index') : url('/finance/credit-store') }}" class="sidebar-nav-link {{ request()->is('finance/credit-store*') || request()->routeIs('finance.credit-store.*') ? 'active' : '' }}">
                <i class="fa-solid fa-credit-card text-info"></i>
                <span>Credit Store Ledger</span>
            </a>
        </li>
        @endif

        <!-- VAT & Withholding Tax Deductions Ledger -->
        @if(auth()->check() && auth()->user()->hasAnyRole(['Finance head', 'finance_head', 'finance', 'Finance', 'finance_manager', 'admin', 'global_admin', 'gm', 'general_manager', 'auditor', 'audit']))
        <li class="sidebar-nav-item">
            <a href="{{ route('finance.tax-deductions.index') }}" class="sidebar-nav-link {{ request()->routeIs('finance.tax-deductions.*') ? 'active' : '' }}">
                <i class="fa-solid fa-receipt text-danger"></i>
                <span>VAT &amp; Withholding Tax</span>
            </a>
        </li>
        @endif

        <!-- Receipts & Verification -->
        <li class="sidebar-nav-item">
            <a href="{{ route('delivery-receipts.index') }}" class="sidebar-nav-link {{ request()->routeIs('delivery-receipts.*') ? 'active' : '' }}">
                <i class="fa-solid fa-receipt text-warning"></i>
                <span>Receipts & Verification</span>
            </a>
        </li>

        <!-- Reports -->
        @if(auth()->check() && auth()->user()->hasAnyRole(['Finance head', 'finance_head', 'admin', 'global_admin']))
        <li class="sidebar-nav-item">
            <a href="{{ route('reports.index') }}" class="sidebar-nav-link {{ request()->is('finance/reports') || request()->is('finance/reports*') ? 'active' : '' }}">
                <i class="fa-solid fa-file-lines text-primary"></i>
                <span>Reports</span>
            </a>
        </li>
        @endif


        @canany(['finance.bank.manage', 'finance.*'])
        <li class="sidebar-nav-item">
            <a href="{{ route('bank-accounts.index') }}" class="sidebar-nav-link {{ request()->routeIs('bank-accounts.*') ? 'active' : '' }}">
                <i class="fa-solid fa-building-columns"></i>
                <span>Bank Accounts</span>
            </a>
        </li>
        @endcanany
        @canany(['payments.view', 'payments.create', 'payments.approve', 'payments.*'])
        <li class="sidebar-nav-item">
            <a href="{{ route('payments.index') }}" class="sidebar-nav-link {{ request()->routeIs('payments.*') ? 'active' : '' }}">
                <i class="fa-solid fa-chart-pie"></i>
                <span>Payments</span>
            </a>
        </li>
        @endcanany
        @canany(['subcon.view', 'subcon.create', 'subcon.edit', 'subcon.approve', 'subcon.*'])
        <li class="sidebar-nav-item">
            <a href="{{ route('subcon-agreements.index') }}" class="sidebar-nav-link {{ request()->routeIs('subcon-agreements.*') ? 'active' : '' }}">
                <i class="fa-solid fa-handshake"></i>
                <span>Subcontractors</span>
            </a>
        </li>
        @endcanany
        @canany(['finance.ipcs.manage', 'subcon.*'])
        <li class="sidebar-nav-item">
            <a href="{{ route('ipcs.index') }}" class="sidebar-nav-link {{ request()->routeIs('ipcs.*') ? 'active' : '' }}">
                <i class="fa-solid fa-file-invoice-dollar"></i>
                <span>IPCs</span>
            </a>
        </li>
        @endcanany
        @endcanany

        {{-- HR Management --}}
        @if(!$isContractAdmin && !$isSecretary && !$isStoreKeeper && (auth()->check() && (auth()->user()->hasAnyRole(['hr_officer', 'hr_manager', 'hr', 'admin', 'global_admin', 'gm', 'general_manager']) || auth()->user()->hasAnyPermission(['hr.departments.view', 'hr.employees.view', 'hr.employees.create', 'hr.employees.edit', 'hr.attendance.view', 'hr.attendance.manage', 'finance.payroll.process', 'hr.payroll.view', 'hr.*']))))

        <li class="sidebar-nav-item">
            <a href="{{ route('employees.index') }}" class="sidebar-nav-link {{ request()->routeIs('employees.*') && !request()->routeIs('employees.history') ? 'active' : '' }}">
                <i class="fa-solid fa-users text-primary"></i>
                <span>Employees</span>
            </a>
        </li>
        <li class="sidebar-nav-item">
            <a href="{{ route('employees.history') }}" class="sidebar-nav-link {{ request()->routeIs('employees.history') ? 'active' : '' }}">
                <i class="fa-solid fa-user-clock text-danger"></i>
                <span>Employee History</span>
            </a>
        </li>
        <li class="sidebar-nav-item">
            <a href="{{ \Illuminate\Support\Facades\Route::has('employee-letters.index') ? route('employee-letters.index') : url('/employee-letters') }}" class="sidebar-nav-link {{ request()->routeIs('employee-letters.*') ? 'active' : '' }}">
                <i class="fa-solid fa-envelope-open-text text-warning"></i>
                <span>Employee Letters &amp; History</span>
            </a>
        <li class="sidebar-nav-item">
            <a href="{{ route('subcon-agreements.index') }}" class="sidebar-nav-link {{ request()->routeIs('subcon-agreements.*') || request()->routeIs('hr.subcon-agreements.*') ? 'active' : '' }}">
                <i class="fa-solid fa-file-signature text-success"></i>
                <span>Subcon Agreements</span>
                @php
                    $pendingSubconCount = 0;
                    try {
                        if (\Illuminate\Support\Facades\Schema::hasTable('subcon_agreements')) {
                            $pendingSubconCount = \App\Models\SubconAgreement::whereIn('status', ['draft', 'pending'])->count();
                        }
                    } catch (\Exception $e) {}
                @endphp
                @if($pendingSubconCount > 0)
                    <span class="badge bg-warning text-dark rounded-pill ms-auto">{{ $pendingSubconCount }}</span>
                @endif
            </a>
        </li>
        <li class="sidebar-nav-item">
            <a href="{{ route('departments.index') }}" class="sidebar-nav-link {{ request()->routeIs('departments.*') ? 'active' : '' }}">
                <i class="fa-solid fa-building text-secondary"></i>
                <span>Departments</span>
            </a>
        </li>
        <li class="sidebar-nav-item">
            <a href="{{ route('attendance.index') }}" class="sidebar-nav-link {{ request()->routeIs('attendance.*') ? 'active' : '' }}">
                <i class="fa-solid fa-calendar-check text-success"></i>
                <span>Attendance</span>
            </a>
        </li>
        <li class="sidebar-nav-item">
            @php
                $pendingLeaveCount = 0;
                try {
                    if (\Illuminate\Support\Facades\Schema::hasTable('leave_requests')) {
                        $pendingLeaveCount = \App\Models\LeaveRequest::where('status', 'pending')->count();
                    }
                } catch (\Exception $e) {}
            @endphp
            <a href="{{ route('leave-requests.index') }}" class="sidebar-nav-link {{ request()->routeIs('leave-requests.*') ? 'active' : '' }}">
                <i class="fa-solid fa-calendar-minus text-info"></i>
                <span>Leave Approvals &amp; Quota</span>
                @if($pendingLeaveCount > 0)
                    <span class="badge bg-warning text-dark rounded-pill ms-auto">{{ $pendingLeaveCount }}</span>
                @endif
            </a>
        </li>

        <li class="sidebar-nav-item">
            <a href="{{ route('daily-reports.approval') }}" class="sidebar-nav-link {{ request()->routeIs('daily-reports.approval') ? 'active' : '' }}">
                <i class="fa-solid fa-file-check text-success"></i>
                <span>Approve Daily Reports</span>
            </a>
        </li>
        <li class="sidebar-nav-item">
            <a href="{{ \Illuminate\Support\Facades\Route::has('office-requests.index') ? route('office-requests.index') : url('/office-requests') }}" class="sidebar-nav-link {{ request()->is('office-requests*') || request()->routeIs('office-requests.*') ? 'active' : '' }}">
                <i class="fa-solid fa-boxes-stacked text-warning"></i>
                <span>Office Material Requests</span>
                @php
                    $pendingHrOfficeReqCount = 0;
                    try {
                        if (\Illuminate\Support\Facades\Schema::hasTable('office_material_requests')) {
                            $pendingHrOfficeReqCount = \App\Models\OfficeMaterialRequest::where('status', \App\Models\OfficeMaterialRequest::STATUS_PENDING_HR)->count();
                        }
                    } catch (\Exception $e) {}
                @endphp
                @if($pendingHrOfficeReqCount > 0)
                    <span class="badge bg-warning text-dark rounded-pill ms-auto">{{ $pendingHrOfficeReqCount }}</span>
                @endif
            </a>
        </li>
        <li class="sidebar-nav-item">
            <a href="{{ route('expenses.index') }}?tab=pending_hr" class="sidebar-nav-link {{ (request()->routeIs('expenses.*') || request()->is('expenses*') || request()->routeIs('approvals.*')) && request('tab') === 'pending_hr' ? 'active' : '' }}">
                <i class="fa-solid fa-file-invoice-dollar text-warning"></i>
                <span>Approve Expenses</span>
                @php
                    $pendingHrExpCount = 0;
                    try {
                        $pendingHrExpCount = \App\Models\ExpenseRequest::where('status', \App\Models\ExpenseRequest::STATUS_PENDING_HR)->count();
                    } catch (\Exception $e) {}
                @endphp
                @if($pendingHrExpCount > 0)
                    <span class="badge bg-warning text-dark rounded-pill ms-auto">{{ $pendingHrExpCount }}</span>
                @endif
            </a>
        </li>
        <li class="sidebar-nav-item">
            <a href="{{ route('weekly-manpower.index') }}" class="sidebar-nav-link {{ request()->routeIs('weekly-manpower.*') ? 'active' : '' }}">
                <i class="fa-solid fa-chart-bar text-info"></i>
                <span>Weekly Manpower</span>
            </a>
        </li>
        <li class="sidebar-nav-item">
            <a href="{{ route('manpower-forecast.index') }}" class="sidebar-nav-link {{ request()->routeIs('manpower-forecast.*') ? 'active' : '' }}">
                <i class="fa-solid fa-chart-line text-primary"></i>
                <span>Manpower Forecast</span>
            </a>
        </li>
        @if(!auth()->user()->hasAnyRole(['Finance head', 'finance_head']))
        <li class="sidebar-nav-item">
            <a href="{{ route('payrolls.index') }}" class="sidebar-nav-link {{ request()->routeIs('payrolls.*') ? 'active' : '' }}">
                <i class="fa-solid fa-money-bill-wave text-success"></i>
                <span>Payroll</span>
            </a>
        </li>
        <li class="sidebar-nav-item">
            <a href="{{ route('payroll.advances') }}" class="sidebar-nav-link {{ request()->routeIs('payroll.advances*') ? 'active' : '' }}">
                <i class="fa-solid fa-hand-holding-dollar text-warning"></i>
                <span>Salary Advance Loans</span>
            </a>
        </li>
        @endif
        <li class="sidebar-nav-item">
            <a href="{{ route('performance-dashboard.index') }}" class="sidebar-nav-link {{ request()->routeIs('performance-dashboard.*') ? 'active' : '' }}">
                <i class="fa-solid fa-chart-bar text-info"></i>
                <span>Performance Reviews</span>
            </a>
        </li>
        <li class="sidebar-nav-item">
            <a href="{{ route('reports.attendance') }}" class="sidebar-nav-link {{ request()->routeIs('reports.*') ? 'active' : '' }}">
                <i class="fa-solid fa-chart-line text-danger"></i>
                <span>HR Reports</span>
            </a>
        </li>
        <li class="sidebar-nav-item">
            <a href="{{ route('employee.dashboard') }}" class="sidebar-nav-link {{ request()->routeIs('employee.*') ? 'active' : '' }}">
                <i class="fa-solid fa-user-tie text-info"></i>
                <span>Self-Service Portal</span>
            </a>
        </li>
        @endif

        {{-- Communication --}}
        @if(!$isAuditorUser)
        <li class="sidebar-nav-item">
            <a href="{{ route('messages.index') }}" class="sidebar-nav-link {{ request()->routeIs('messages.*') ? 'active' : '' }}">
                <i class="fa-solid fa-envelope"></i>
                <span>Messages</span>
            </a>
        </li>
        <li class="sidebar-nav-item">
            <a href="{{ route('tickets.index') }}" class="sidebar-nav-link {{ request()->routeIs('tickets.*') ? 'active' : '' }}">
                <i class="fa-solid fa-headset text-warning"></i>
                <span>My Support Tickets</span>
                @php
                    $openTickets = 0;
                    try {
                        $openTickets = \App\Models\SupportTicket::where('user_id', auth()->id())->whereIn('status', ['open', 'in_progress'])->count();
                    } catch (\Exception $e) {
                        // Suppress exception if table does not exist
                    }
                @endphp
                @if($openTickets > 0)
                    <span class="badge bg-danger ms-auto">{{ $openTickets }}</span>
                @endif
            </a>
        </li>
        @endif

        {{-- Admin --}}
        @php $isAuditorUser = in_array('auditor', $rawUserRoles) || in_array('audit', $rawUserRoles) || in_array('internal_auditor', $rawUserRoles) || in_array('audit_team', $rawUserRoles) || ($authUser && $authUser->hasAnyRole(['auditor', 'audit', 'internal_auditor', 'Auditor', 'Audit'])); @endphp
        @if(auth()->check() && !$isAuditorUser && (auth()->user()->hasAnyRole(['global_admin', 'admin']) || auth()->user()->canAny(['users.view', 'users.*', 'settings.view', 'settings.*'])))
        @canany(['users.view', 'users.*'])
        <li class="sidebar-nav-item">
            <a href="{{ route('users.index') }}" class="sidebar-nav-link {{ request()->routeIs('users.*') ? 'active' : '' }}">
                <i class="fa-solid fa-user-shield"></i>
                <span>User Management</span>
            </a>
        </li>
        
        @role('global_admin|admin')
        <li class="sidebar-nav-item">
            <a href="{{ route('admin.role-assignment.index') }}" class="sidebar-nav-link {{ request()->routeIs('admin.role-assignment.*') ? 'active' : '' }}">
                <i class="fa-solid fa-user-tag text-info"></i>
                <span>Role Assignment</span>
                @php
                    $noRoleCount = 0;
                    try {
                        $noRoleCount = \App\Models\User::whereDoesntHave('roles')->count();
                    } catch (\Exception $e) {}
                @endphp
                @if($noRoleCount > 0)
                    <span class="badge bg-warning text-dark ms-auto">{{ $noRoleCount }}</span>
                @endif
            </a>
        </li>
        <li class="sidebar-nav-item">
            <a href="{{ route('admin.employee-ratings.index') }}" class="sidebar-nav-link {{ request()->routeIs('admin.employee-ratings.*') ? 'active' : '' }}">
                <i class="fa-solid fa-star text-warning"></i>
                <span>Employee Ratings</span>
            </a>
        </li>

        <li class="sidebar-nav-item">
            <a href="{{ route('admin.tickets.index') }}" class="sidebar-nav-link {{ request()->routeIs('admin.tickets.*') ? 'active' : '' }}">
                <i class="fa-solid fa-ticket text-danger"></i>
                <span>Support Tickets</span>
            </a>
        </li>
        @endrole
        @endcanany
        @canany(['settings.view', 'settings.*'])
        <li class="sidebar-nav-item">
            <a href="{{ route('settings.index') }}" class="sidebar-nav-link {{ request()->routeIs('settings.*') ? 'active' : '' }}">
                <i class="fa-solid fa-cogs"></i>
                <span>System Settings</span>
            </a>
        </li>
        @endcanany
        @endif

        @if(auth()->check() && !$isAuditorUser && (auth()->user()->hasAnyRole(['admin', 'global_admin']) || (method_exists(auth()->user(), 'can') && (auth()->user()->can('audit.view') || auth()->user()->can('finance.audit.view') || auth()->user()->can('admin.audit.view')))))
        <li class="sidebar-nav-item sidebar-section-label" style="padding:8px 16px 4px; font-size:10px; font-weight:700; letter-spacing:.08em; text-transform:uppercase; color:#94a3b8; pointer-events:none; user-select:none;">Audit &amp; Compliance</li>
        <li class="sidebar-nav-item">
            <a href="{{ \Illuminate\Support\Facades\Route::has('dashboard.audit') ? route('dashboard.audit') : url('/dashboard/audit') }}" class="sidebar-nav-link {{ request()->routeIs('dashboard.audit') ? 'active' : '' }}">
                <i class="fa-solid fa-chart-pie text-info"></i>
                <span>Audit Dashboard</span>
            </a>
        </li>
        <li class="sidebar-nav-item">
            <a href="{{ \Illuminate\Support\Facades\Route::has('finance.replenishments.index') ? route('finance.replenishments.index') : url('/finance/replenishments') }}" class="sidebar-nav-link {{ request()->is('finance/replenishments*') ? 'active' : '' }}">
                <i class="fa-solid fa-hand-holding-dollar text-warning"></i>
                <span>Petty Cash Audit &amp; Approvals</span>
                @php
                    $pendingAudReplenishCount = 0;
                    try {
                        if (\Illuminate\Support\Facades\Schema::hasTable('petty_cash_replenishments')) {
                            $pendingAudReplenishCount = \App\Models\PettyCashReplenishment::where('status', 'pending')->count();
                        }
                    } catch (\Exception $e) {}
                @endphp
                @if($pendingAudReplenishCount > 0)
                    <span class="badge bg-danger rounded-pill ms-auto">{{ $pendingAudReplenishCount }}</span>
                @endif
            </a>
        </li>
        <li class="sidebar-nav-item">
            <a href="{{ route('admin.activity-logs') }}" class="sidebar-nav-link {{ request()->routeIs('admin.activity-logs') ? 'active' : '' }}">
                <i class="fa-solid fa-list-ol text-primary"></i>
                <span>Audit &amp; Activity Trail</span>
            </a>
        </li>
        <li class="sidebar-nav-item">
            <a href="{{ route('expenses.index') }}" class="sidebar-nav-link {{ request()->routeIs('expenses.*') ? 'active' : '' }}">
                <i class="fa-solid fa-file-invoice-dollar text-success"></i>
                <span>Expense &amp; Payment Audit</span>
            </a>
        </li>


        @endif

        @endrole
    </ul>
</div>

<div class="sidebar-footer">
    <div class="sidebar-footer-avatar">
        {{ substr(auth()->user()->name ?? 'U', 0, 1) }}
    </div>
    <div class="sidebar-footer-info">
        <div class="user-name">{{ auth()->user()->name ?? 'User' }}</div>
        <div class="user-role">{{ ucfirst(str_replace('_', ' ', auth()->user()->roles->first()->name ?? 'Guest')) }}</div>
    </div>
</div>
