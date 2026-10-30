@auth
@php
    $user = auth()->user();
    $activeRole = $user ? $user->getActiveRole() : null;
    
    // Determine dynamic dashboard route based on role with safe fallbacks
    $dashboardRoute = \Illuminate\Support\Facades\Route::has('dashboard.admin') ? route('dashboard.admin') : url('/dashboard');
    if ($user) {
        if ($user->hasRole('gm') && \Illuminate\Support\Facades\Route::has('dashboard.gm')) {
            $dashboardRoute = route('dashboard.gm');
        } elseif (($user->hasRole('finance') || $user->hasRole('finance_manager')) && \Illuminate\Support\Facades\Route::has('dashboard.finance')) {
            $dashboardRoute = route('dashboard.finance');
        } elseif (($user->hasRole('hr') || $user->hasRole('hr_manager')) && \Illuminate\Support\Facades\Route::has('dashboard.hr')) {
            $dashboardRoute = route('dashboard.hr');
        } elseif ($user->hasRole('store_manager') && \Illuminate\Support\Facades\Route::has('dashboard.store-manager')) {
            $dashboardRoute = route('dashboard.store-manager');
        } elseif ($user->hasRole('store_keeper') && \Illuminate\Support\Facades\Route::has('dashboard.store-keeper')) {
            $dashboardRoute = route('dashboard.store-keeper');
        } elseif ($user->hasRole('site_engineer') && \Illuminate\Support\Facades\Route::has('dashboard.site-engineer')) {
            $dashboardRoute = route('dashboard.site-engineer');
        } elseif (($user->hasRole('planning_manager') || $user->hasRole('planning')) && \Illuminate\Support\Facades\Route::has('dashboard.planning')) {
            $dashboardRoute = route('dashboard.planning');
        } elseif ($user->hasRole('general_service') && \Illuminate\Support\Facades\Route::has('dashboard.general_service')) {
            $dashboardRoute = route('dashboard.general_service');
        }
    }

    // Reports link with safe fallbacks
    $reportsRoute = \Illuminate\Support\Facades\Route::has('reports.index') ? route('reports.index') : url('/reports');
    if ($user) {
        if ($user->hasAnyRole(['hr', 'hr_manager', 'hr_officer']) && \Illuminate\Support\Facades\Route::has('reports.attendance')) {
            $reportsRoute = route('reports.attendance');
        } elseif ($user->hasRole('general_service') && \Illuminate\Support\Facades\Route::has('fuel-requests.reports')) {
            $reportsRoute = route('fuel-requests.reports');
        } elseif ($user->hasRole('store_manager') && \Illuminate\Support\Facades\Route::has('store-manager.inventory.all')) {
            $reportsRoute = route('store-manager.inventory.all');
        } elseif ($user->hasRole('marketing') && \Illuminate\Support\Facades\Route::has('marketing.reports.inflation')) {
            $reportsRoute = route('marketing.reports.inflation');
        } elseif ($user->hasRole('planning_manager') && \Illuminate\Support\Facades\Route::has('planning-manager.resource-report')) {
            $reportsRoute = route('planning-manager.resource-report');
        }
    }

    // Approvals link with safe fallbacks (approvals.index)
    $approvalsRoute = \Illuminate\Support\Facades\Route::has('approvals.index') ? route('approvals.index') : url('/approvals');
    if ($user) {
        if ($user->hasRole('gm') && \Illuminate\Support\Facades\Route::has('gm.hr-reports')) {
            $approvalsRoute = route('gm.hr-reports');
        } elseif ($user->hasAnyRole(['hr_manager', 'hr']) && \Illuminate\Support\Facades\Route::has('daily-reports.approval')) {
            $approvalsRoute = route('daily-reports.approval');
        }
    }

    // Profile link
    $profileRoute = \Illuminate\Support\Facades\Route::has('profile.edit') ? route('profile.edit') : url('/profile');
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
    <a href="{{ $profileRoute }}" class="wc-bottom-nav-item {{ request()->routeIs('profile.*') ? 'active' : '' }}">
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
