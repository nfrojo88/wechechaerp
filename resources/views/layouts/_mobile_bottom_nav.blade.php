@auth
@php
    $user = auth()->user();
    $activeRole = $user ? $user->getActiveRole() : null;
    
    // Determine dynamic dashboard route based on role
    $dashboardRoute = route('dashboard.admin');
    if ($user->hasRole('gm')) $dashboardRoute = route('dashboard.gm');
    elseif ($user->hasRole('finance') || $user->hasRole('finance_manager')) $dashboardRoute = route('dashboard.finance');
    elseif ($user->hasRole('hr') || $user->hasRole('hr_manager')) $dashboardRoute = route('dashboard.hr');
    elseif ($user->hasRole('store_manager')) $dashboardRoute = route('dashboard.store-manager');
    elseif ($user->hasRole('store_keeper')) $dashboardRoute = route('dashboard.store-keeper');
    elseif ($user->hasRole('site_engineer')) $dashboardRoute = route('dashboard.site-engineer');
    elseif ($user->hasRole('planning_manager') || $user->hasRole('planning')) $dashboardRoute = route('dashboard.planning');
    elseif ($user->hasRole('general_service')) $dashboardRoute = route('dashboard.general_service');

    // Reports link
    $reportsRoute = route('reports.index');
    if ($user->hasAnyRole(['hr', 'hr_manager', 'hr_officer'])) $reportsRoute = route('reports.attendance');
    elseif ($user->hasRole('general_service')) $reportsRoute = route('fuel-requests.reports');
    elseif ($user->hasRole('store_manager')) $reportsRoute = route('store-manager.inventory.all');
    elseif ($user->hasRole('marketing')) $reportsRoute = route('marketing.reports.inflation');
    elseif ($user->hasRole('planning_manager')) $reportsRoute = route('planning-manager.resource-report');

    // Approvals link
    $approvalsRoute = route('approvals.hub');
    if ($user->hasRole('gm')) $approvalsRoute = route('gm.hr-reports');
    elseif ($user->hasAnyRole(['hr_manager', 'hr'])) $approvalsRoute = route('daily-reports.approval');
@endphp

<nav class="wc-mobile-bottom-nav" aria-label="Mobile Navigation">
    {{-- 1. Dashboard --}}
    <a href="{{ $dashboardRoute }}" class="wc-bottom-nav-item {{ request()->is('dashboard*') ? 'active' : '' }}">
        <i class="fa-solid fa-house"></i>
        <span>Home</span>
    </a>

    {{-- 2. Reports --}}
    <a href="{{ $reportsRoute }}" class="wc-bottom-nav-item {{ request()->is('*report*') || request()->is('finance/reports*') ? 'active' : '' }}">
        <i class="fa-solid fa-chart-line"></i>
        <span>Reports</span>
    </a>

    {{-- 3. Approvals / Action Hub --}}
    <a href="{{ $approvalsRoute }}" class="wc-bottom-nav-item {{ request()->is('*approval*') ? 'active' : '' }}">
        <i class="fa-solid fa-clipboard-check"></i>
        <span>Approvals</span>
    </a>

    {{-- 4. User Profile --}}
    <a href="{{ route('profile.edit') }}" class="wc-bottom-nav-item {{ request()->routeIs('profile.*') ? 'active' : '' }}">
        <i class="fa-solid fa-user"></i>
        <span>Profile</span>
    </a>

    {{-- 5. Full Menu (Sidebar Trigger) --}}
    <button type="button" id="mobileBottomNavMenuBtn" class="wc-bottom-nav-item btn btn-link p-0 border-0" onclick="$('#sidebar').addClass('mobile-open'); $('#sidebarBackdrop').addClass('active'); $('body').addClass('sidebar-open');">
        <i class="fa-solid fa-bars"></i>
        <span>Menu</span>
    </button>
</nav>
@endauth
