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
    $isAuditorUser = in_array('auditor', $rawUserRoles) || in_array('audit', $rawUserRoles) || in_array('internal_auditor', $rawUserRoles) || in_array('audit_team', $rawUserRoles);
@endphp

<div class="sidebar-scroll">
    <ul class="sidebar-nav">

        @if(!auth()->check() || (!$isSiteStaffUser && !$isGeneralServiceUser))
        @php
            $isGmUser = auth()->check() && (auth()->user()->hasAnyRole(['gm', 'general_manager', 'General Manager', 'GM']) || in_array('gm', $rawUserRoles) || in_array('general_manager', $rawUserRoles));
            $isFinanceUser = auth()->check() && auth()->user()->hasAnyRole(['Finance head', 'finance_head', 'finance', 'Finance', 'finance_manager', 'cashier', 'accountant']);
            $dashUrl = \Illuminate\Support\Facades\Route::has('dashboard') ? route('dashboard') : url('/dashboard');
            $dashTitle = 'Dashboard';
            if ($isAuditorUser) {
                $dashUrl = \Illuminate\Support\Facades\Route::has('dashboard.audit') ? route('dashboard.audit') : url('/dashboard/audit');
                $dashTitle = 'Audit Dashboard';
            } elseif ($isGmUser) {
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
            <a href="{{ route('leave-requests.create') }}" class="sidebar-nav-link {{ request()->routeIs('leave-requests.create') || request()->routeIs('leave-requests.my-requests') ? 'active' : '' }}">
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

        @if(!$isSecretary && !$isStoreKeeper && !$isGeneralServiceUser)
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

        @if(!$isSecretary && !$isStoreKeeper && !$isGeneralServiceUser)
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
        @if(!$isSecretary && !$isGeneralServiceUser)
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
        @endif
        {{-- Masters --}}

        @if(!auth()->check() || (!$isSiteStaffUser && !$isGeneralServiceUser && !$isSecretary && !$isStoreKeeper && !$isStoreManager && !$isAuditorUser))
        @canany(['projects.view', 'planning.view', 'schedule.view', 'stores.view', 'stores.create', 'stores.edit', 'stores.delete', 'products.view', 'products.create', 'products.edit', 'products.delete'])

        @canany(['projects.view', 'planning.view', 'schedule.view'])
        <li class="sidebar-nav-item">
            <a href="{{ route('projects.index') }}" class="sidebar-nav-link {{ request()->routeIs('projects.*') ? 'active' : '' }}">
                <i class="fa-solid fa-building"></i>
                <span>Projects</span>
            </a>
        </li>
        @endcanany
        @canany(['stores.view', 'stores.create', 'stores.edit', 'stores.delete'])
        <li class="sidebar-nav-item">
            <a href="{{ route('stores.index') }}" class="sidebar-nav-link {{ request()->routeIs('stores.*') ? 'active' : '' }}">
                <i class="fa-solid fa-warehouse"></i>
                <span>Stores</span>
            </a>
        </li>
        @endcanany
        @canany(['products.view', 'products.create', 'products.edit', 'products.delete'])
        <li class="sidebar-nav-item">
            <a href="{{ route('products.index') }}" class="sidebar-nav-link {{ request()->routeIs('products.*') ? 'active' : '' }}">
                <i class="fa-solid fa-boxes-stacked"></i>
                <span>Products</span>
            </a>
        </li>
        @endcanany
        @endcanany
        @endif

        {{-- Inventory --}}
        @if(!auth()->check() || (!$isSiteStaffUser && !$isGeneralServiceUser && !$isStoreKeeper && !$isStoreManager && !$isAuditorUser))
        @canany(['inventory.view', 'inventory.view_all_stores', 'inventory.*'])

        <li class="sidebar-nav-item">
            <a href="{{ route('inventory.index') }}" class="sidebar-nav-link {{ request()->routeIs('inventory.*') ? 'active' : '' }}">
                <i class="fa-solid fa-clipboard-list"></i>
                <span>Stock Levels</span>
            </a>
        </li>
        @endcanany
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
            <a href="{{ route('store-manager.material-requests.index') }}" class="sidebar-nav-link {{ request()->routeIs('store-manager.material-requests.*') ? 'active' : '' }}">
                <i class="fa-solid fa-clipboard-list text-danger"></i>
                <span>Material Requests</span>
            </a>
        </li>
        <li class="sidebar-nav-item">
            <a href="{{ route('store-manager.transfers.index') }}" class="sidebar-nav-link {{ request()->routeIs('store-manager.transfers.*') ? 'active' : '' }}">
                <i class="fa-solid fa-truck-moving text-warning"></i>
                <span>Transfers</span>
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

        {{-- Store Hub (Central Store Manager / Admins) --}}
        @if(auth()->check() && !$isGeneralServiceUser && !$isStoreKeeper && auth()->user()->hasAnyRole(['store_manager', 'admin', 'global_admin']))

        @if(!$isStoreManager)
        <li class="sidebar-nav-item">
            <a href="{{ route('dashboard.store-manager') }}" class="sidebar-nav-link {{ request()->routeIs('dashboard.store-manager') || request()->routeIs('store-manager.dashboard') ? 'active' : '' }}">
                <i class="fa-solid fa-gauge-high text-primary"></i>
                <span>Store Dashboard</span>
            </a>
        </li>
        @endif
        <li class="sidebar-nav-item">
            <a href="{{ route('store-manager.inventory.all') }}" class="sidebar-nav-link {{ request()->routeIs('store-manager.inventory.*') ? 'active' : '' }}">
                <i class="fa-solid fa-boxes-stacked text-info"></i>
                <span>All Inventory</span>
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
            <a href="{{ route('store-manager.transfers.index') }}" class="sidebar-nav-link {{ request()->routeIs('store-manager.transfers.index') || request()->routeIs('store-manager.transfers.show') ? 'active' : '' }}">
                <i class="fa-solid fa-truck-moving text-warning"></i>
                <span>Transfer List</span>
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
            <a href="{{ route('material-requests.create', ['source' => 'Emergency']) }}" class="sidebar-nav-link {{ request()->fullUrlIs('*source=Emergency*') ? 'active' : '' }}">
                <i class="fa-solid fa-bolt text-danger"></i>
                <span>Ask Emergency Material Request</span>
            </a>
        </li>
        <li class="sidebar-nav-item">
            <a href="{{ route('material-requests.index') }}" class="sidebar-nav-link {{ request()->routeIs('material-requests.*') && !request()->fullUrlIs('*source=Emergency*') ? 'active' : '' }}">
                <i class="fa-solid fa-cart-flatbed text-warning"></i>
                <span>Material Requests</span>
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
        <!-- Expenses -->
        <li class="sidebar-nav-item">
            <a href="{{ route('expenses.index') }}" class="sidebar-nav-link {{ request()->routeIs('expenses.*') ? 'active' : '' }}">
                <i class="fa-solid fa-arrow-trend-down text-danger"></i>
                <span>Expenses</span>
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



        {{-- Admin --}}
        @php $isAuditorUser = auth()->user()->hasAnyRole(['auditor', 'audit', 'internal_auditor']); @endphp
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
            <a href="{{ route('admin.activity-logs') }}" class="sidebar-nav-link {{ request()->routeIs('admin.activity-logs') ? 'active' : '' }}">
                <i class="fa-solid fa-list-ol text-primary"></i>
                <span>Activity Logs</span>
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

        @if(auth()->check() && (auth()->user()->hasAnyRole(['auditor', 'audit', 'internal_auditor', 'admin', 'global_admin']) || (method_exists(auth()->user(), 'can') && (auth()->user()->can('audit.view') || auth()->user()->can('finance.audit.view') || auth()->user()->can('admin.audit.view')))))
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
        <li class="sidebar-nav-item">
            <a href="{{ route('coa.index') }}" class="sidebar-nav-link {{ request()->routeIs('coa.*') ? 'active' : '' }}">
                <i class="fa-solid fa-sitemap text-secondary"></i>
                <span>Chart of Accounts (COA)</span>
            </a>
        </li>
        @endif




        @endcanany

        @role('global_admin')
        <li class="sidebar-nav-item mt-4">
            <a href="{{ route('dev.roles') }}" class="sidebar-nav-link text-warning">
                <i class="fa-solid fa-vial"></i>
                <span>Role Tester</span>
            </a>
        </li>
        <li class="sidebar-nav-item mt-1">
            <a href="{{ route('system.run-migrations') }}" class="sidebar-nav-link" style="color: #20c997;" onclick="return confirm('Run database migrations now? This will apply all pending changes.')">
                <i class="fa-solid fa-database"></i>
                <span>Auto Migrate DB</span>
            </a>
        </li>
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
