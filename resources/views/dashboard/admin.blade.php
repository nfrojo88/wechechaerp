@extends('layouts.app')
@section('title', 'Global Admin Dashboard')
@section('content')
<div class="container-fluid py-2">

    {{-- ══════════════════════════════════════════════════════════════════════════
         1. EXECUTIVE HEADER & REAL-TIME SYSTEM BAR
         ══════════════════════════════════════════════════════════════════════════ --}}
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2 pb-3 border-bottom">
        <div>
            <div class="d-flex align-items-center gap-2 flex-wrap mb-1">
                <span class="badge bg-primary bg-opacity-10 text-primary px-3 py-1 rounded-pill fw-semibold" style="font-size:0.8rem;">
                    <i class="fa-solid fa-shield-halved me-1"></i>Global Admin Command Center
                </span>
                <span class="badge bg-light text-muted border px-2 py-1 rounded-pill" style="font-size:0.75rem;">
                    <i class="fa-regular fa-clock me-1"></i>{{ now()->format('l, F j, Y — H:i') }}
                </span>
                <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 px-2 py-1 rounded-pill" style="font-size:0.75rem;">
                    <i class="fa-solid fa-circle-check me-1"></i>System Active
                </span>
            </div>
            <h1 class="h3 mb-0 text-gray-800 fw-bold">
                <i class="fas fa-gauge-high text-primary me-2"></i>Global Admin Executive Dashboard
            </h1>
        </div>

        {{-- Executive Power Bar / Fast Controls --}}
        <div class="d-flex gap-2 flex-wrap align-items-center">
            <a href="{{ route('system.clear-cache') }}" class="btn btn-outline-secondary btn-sm rounded-pill px-3 shadow-xs" onclick="return confirm('Clear application and route caches now?')">
                <i class="fa-solid fa-broom me-1 text-warning"></i>Clear Cache
            </a>
            <a href="{{ route('system.run-migrations') }}" class="btn btn-outline-teal btn-sm rounded-pill px-3 shadow-xs text-success border-success" onclick="return confirm('Run database migrations and resync now?')">
                <i class="fa-solid fa-database me-1"></i>Auto Migrate DB
            </a>
            <a href="{{ route('admin.role-assignment.index') }}" class="btn btn-outline-info btn-sm rounded-pill px-3 shadow-xs">
                <i class="fa-solid fa-user-gear me-1"></i>User &amp; Roles
                @if(($kpi['unassigned_roles_count'] ?? 0) > 0)
                    <span class="badge bg-danger rounded-pill ms-1">{{ $kpi['unassigned_roles_count'] }}</span>
                @endif
            </a>
            <a href="{{ route('coa.index') }}" class="btn btn-outline-dark btn-sm rounded-pill px-3 shadow-xs">
                <i class="fa-solid fa-building-columns me-1 text-primary"></i>Chart of Accounts
            </a>
            <a href="{{ route('finance.tax-deductions.index') }}" class="btn btn-outline-primary btn-sm rounded-pill px-3 shadow-xs">
                <i class="fa-solid fa-file-invoice-dollar me-1"></i>Tax Compliance
            </a>
            <a href="#vat-withholding-report" class="btn btn-outline-danger btn-sm rounded-pill px-3 shadow-xs">
                <i class="fa-solid fa-receipt me-1"></i>VAT &amp; WHT
            </a>
        </div>
    </div>

    {{-- ══════════════════════════════════════════════════════════════════════════
         2. URGENT EXECUTIVE DECISION & ESCALATIONS SUMMARY
         ══════════════════════════════════════════════════════════════════════════ --}}
    @php
        $totalUrgentActions = ($kpi['pending_gm_prs'] ?? 0)
            + ($kpi['unassigned_prs_count'] ?? 0)
            + ($kpi['pending_gm_expenses'] ?? 0)
            + ($kpi['pending_approvals'] ?? 0)
            + ($kpi['unassigned_roles_count'] ?? 0)
            + ($kpi['pending_maintenance'] ?? 0)
            + ($ticketStats['open'] ?? 0);
    @endphp

    @if($totalUrgentActions > 0)
        <div class="card border-0 shadow-sm rounded-4 mb-4" style="background: linear-gradient(135deg, #1e293b, #0f172a); color: #fff;">
            <div class="card-body p-3 p-md-4">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                    <div class="d-flex align-items-center gap-2">
                        <span class="badge bg-danger rounded-pill px-3 py-1 fw-bold fs-6 animate-pulse">
                            <i class="fa-solid fa-bell me-1"></i>{{ $totalUrgentActions }} Actions Pending
                        </span>
                        <span class="text-white-50 small">Executive Attention Required</span>
                    </div>
                    <a href="#executive-approval-hub" class="btn btn-sm btn-light rounded-pill px-3 fw-semibold">
                        View Decision Hub <i class="fa-solid fa-arrow-down ms-1"></i>
                    </a>
                </div>

                <div class="row g-2 text-center">
                    <div class="col-6 col-md-3 col-xl">
                        <a href="{{ route('procurement.my-queue', ['status' => 'pending_gm']) }}" class="text-decoration-none d-block p-2 rounded-3 bg-white bg-opacity-10 hover-bg-opacity-20 transition text-white">
                            <div class="fs-5 fw-bold text-danger">{{ $kpi['pending_gm_prs'] ?? 0 }}</div>
                            <small class="text-white-50 text-truncate d-block" style="font-size:0.75rem;">PR Decisions</small>
                            <span class="text-warning small" style="font-size:0.7rem;">{{ number_format($kpi['pending_gm_prs_amount'] ?? 0, 0) }} ETB</span>
                        </a>
                    </div>
                    <div class="col-6 col-md-3 col-xl">
                        <a href="{{ route('expenses.index', ['tab' => 'pending_gm']) }}" class="text-decoration-none d-block p-2 rounded-3 bg-white bg-opacity-10 hover-bg-opacity-20 transition text-white">
                            <div class="fs-5 fw-bold text-warning">{{ $kpi['pending_gm_expenses'] ?? 0 }}</div>
                            <small class="text-white-50 text-truncate d-block" style="font-size:0.75rem;">Expense Requests</small>
                            <span class="text-warning small" style="font-size:0.7rem;">{{ number_format($kpi['pending_gm_expenses_amount'] ?? 0, 0) }} ETB</span>
                        </a>
                    </div>
                    <div class="col-6 col-md-3 col-xl">
                        <a href="{{ route('admin.role-assignment.index') }}" class="text-decoration-none d-block p-2 rounded-3 bg-white bg-opacity-10 hover-bg-opacity-20 transition text-white">
                            <div class="fs-5 fw-bold text-info">{{ $kpi['unassigned_roles_count'] ?? 0 }}</div>
                            <small class="text-white-50 text-truncate d-block" style="font-size:0.75rem;">Unassigned Roles</small>
                            <span class="text-info small" style="font-size:0.7rem;">Needs System Role</span>
                        </a>
                    </div>
                    <div class="col-6 col-md-3 col-xl">
                        <a href="{{ route('employees.pending-approval') }}" class="text-decoration-none d-block p-2 rounded-3 bg-white bg-opacity-10 hover-bg-opacity-20 transition text-white">
                            <div class="fs-5 fw-bold text-primary">{{ $kpi['pending_approvals'] ?? 0 }}</div>
                            <small class="text-white-50 text-truncate d-block" style="font-size:0.75rem;">Employee Reg.</small>
                            <span class="text-primary-emphasis small" style="font-size:0.7rem;">Awaiting Approval</span>
                        </a>
                    </div>
                    <div class="col-6 col-md-3 col-xl">
                        <a href="{{ route('gm.maintenance-approvals.index') }}" class="text-decoration-none d-block p-2 rounded-3 bg-white bg-opacity-10 hover-bg-opacity-20 transition text-white">
                            <div class="fs-5 fw-bold text-warning">{{ $kpi['pending_maintenance'] ?? 0 }}</div>
                            <small class="text-white-50 text-truncate d-block" style="font-size:0.75rem;">Maintenance</small>
                            <span class="text-warning small" style="font-size:0.7rem;">Repairs &amp; Spares</span>
                        </a>
                    </div>
                    <div class="col-6 col-md-3 col-xl">
                        <a href="{{ route('admin.tickets.index') }}" class="text-decoration-none d-block p-2 rounded-3 bg-white bg-opacity-10 hover-bg-opacity-20 transition text-white">
                            <div class="fs-5 fw-bold text-danger">{{ $ticketStats['open'] ?? 0 }}</div>
                            <small class="text-white-50 text-truncate d-block" style="font-size:0.75rem;">Support Tickets</small>
                            <span class="text-danger-emphasis small" style="font-size:0.7rem;">Open / In-Progress</span>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    @endif

    {{-- ══════════════════════════════════════════════════════════════════════════
         3. TIER-1 FINANCIAL & CASH COMMAND KPIS
         ══════════════════════════════════════════════════════════════════════════ --}}
    <div class="row g-3 mb-4">
        {{-- Total Cash & Bank Holdings --}}
        <div class="col-xl-3 col-md-6">
            <div class="card border-0 shadow-sm h-100 position-relative overflow-hidden" style="border-left: 4px solid #10b981 !important; border-radius: 12px;">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <div class="text-xs fw-bold text-success text-uppercase mb-1" style="font-size:0.75rem;letter-spacing:0.5px;">
                                <i class="fa-solid fa-building-columns me-1"></i>Cash &amp; Bank Holdings
                            </div>
                            <div class="h4 mb-0 fw-bold text-dark">{{ number_format($kpi['cash_bank_balance'] ?? 0, 2) }} ETB</div>
                            <small class="text-muted">
                                Across {{ $cashAndBankCoas->count() }} active accounts (COA)
                            </small>
                        </div>
                        <div class="rounded-circle p-3 d-flex align-items-center justify-content-center" style="background: rgba(16,185,129,0.12); width:50px; height:50px;">
                            <i class="fa-solid fa-wallet fa-xl text-success"></i>
                        </div>
                    </div>
                    <div class="mt-2 pt-2 border-top d-flex justify-content-between align-items-center">
                        <span class="badge bg-success bg-opacity-10 text-success rounded-pill px-2 py-0" style="font-size:0.7rem;">Live Liquidity</span>
                        <a href="{{ route('coa.index') }}" class="text-success fw-semibold small text-decoration-none">View Balances <i class="fa-solid fa-arrow-right ms-1"></i></a>
                    </div>
                </div>
            </div>
        </div>

        {{-- Monthly Company Revenue / Income --}}
        <div class="col-xl-3 col-md-6">
            <div class="card border-0 shadow-sm h-100 position-relative overflow-hidden" style="border-left: 4px solid #3b82f6 !important; border-radius: 12px;">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <div class="text-xs fw-bold text-primary text-uppercase mb-1" style="font-size:0.75rem;letter-spacing:0.5px;">
                                <i class="fa-solid fa-arrow-trend-up me-1"></i>Monthly Income / Revenue
                            </div>
                            <div class="h4 mb-0 fw-bold text-dark">{{ number_format($kpi['monthly_income'] ?? 0, 2) }} ETB</div>
                            <small class="text-muted">
                                YTD Total: <strong class="text-primary">{{ number_format($kpi['ytd_income'] ?? 0, 2) }} ETB</strong>
                            </small>
                        </div>
                        <div class="rounded-circle p-3 d-flex align-items-center justify-content-center" style="background: rgba(59,130,246,0.12); width:50px; height:50px;">
                            <i class="fa-solid fa-sack-dollar fa-xl text-primary"></i>
                        </div>
                    </div>
                    <div class="mt-2 pt-2 border-top d-flex justify-content-between align-items-center">
                        <span class="badge bg-primary bg-opacity-10 text-primary rounded-pill px-2 py-0" style="font-size:0.7rem;">Inflow</span>
                        <a href="{{ route('income.index') }}" class="text-primary fw-semibold small text-decoration-none">Income Records <i class="fa-solid fa-arrow-right ms-1"></i></a>
                    </div>
                </div>
            </div>
        </div>

        {{-- Monthly Operational Expenses --}}
        <div class="col-xl-3 col-md-6">
            <div class="card border-0 shadow-sm h-100 position-relative overflow-hidden" style="border-left: 4px solid #ef4444 !important; border-radius: 12px;">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <div class="text-xs fw-bold text-danger text-uppercase mb-1" style="font-size:0.75rem;letter-spacing:0.5px;">
                                <i class="fa-solid fa-arrow-trend-down me-1"></i>Monthly Expenses
                            </div>
                            <div class="h4 mb-0 fw-bold text-dark">{{ number_format($kpi['monthly_expenses'] ?? 0, 2) }} ETB</div>
                            <small class="text-muted">
                                YTD Total: <strong class="text-danger">{{ number_format($kpi['ytd_expenses'] ?? 0, 2) }} ETB</strong>
                            </small>
                        </div>
                        <div class="rounded-circle p-3 d-flex align-items-center justify-content-center" style="background: rgba(239,68,68,0.12); width:50px; height:50px;">
                            <i class="fa-solid fa-money-bill-transfer fa-xl text-danger"></i>
                        </div>
                    </div>
                    <div class="mt-2 pt-2 border-top d-flex justify-content-between align-items-center">
                        <span class="badge bg-danger bg-opacity-10 text-danger rounded-pill px-2 py-0" style="font-size:0.7rem;">Outflow</span>
                        <a href="{{ route('expenses.index') }}" class="text-danger fw-semibold small text-decoration-none">View Expenses <i class="fa-solid fa-arrow-right ms-1"></i></a>
                    </div>
                </div>
            </div>
        </div>

        {{-- Net Operating Cash Position --}}
        <div class="col-xl-3 col-md-6">
            @php $netCash = $kpi['net_cash_flow'] ?? 0; @endphp
            <div class="card border-0 shadow-sm h-100 position-relative overflow-hidden" style="border-left: 4px solid {{ $netCash >= 0 ? '#059669' : '#dc2626' }} !important; border-radius: 12px;">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <div class="text-xs fw-bold text-uppercase mb-1" style="font-size:0.75rem;letter-spacing:0.5px; color: {{ $netCash >= 0 ? '#059669' : '#dc2626' }};">
                                <i class="fa-solid fa-scale-balanced me-1"></i>Net Operating Cash Flow
                            </div>
                            <div class="h4 mb-0 fw-bold {{ $netCash >= 0 ? 'text-success' : 'text-danger' }}">
                                {{ $netCash >= 0 ? '+' : '' }}{{ number_format($netCash, 2) }} ETB
                            </div>
                            <small class="text-muted">
                                {{ $netCash >= 0 ? 'Operating Surplus' : 'Operating Deficit' }} this month
                            </small>
                        </div>
                        <div class="rounded-circle p-3 d-flex align-items-center justify-content-center" style="background: {{ $netCash >= 0 ? 'rgba(5,150,105,0.12)' : 'rgba(220,38,38,0.12)' }}; width:50px; height:50px;">
                            <i class="fa-solid {{ $netCash >= 0 ? 'fa-chart-line text-success' : 'fa-triangle-exclamation text-danger' }} fa-xl"></i>
                        </div>
                    </div>
                    <div class="mt-2 pt-2 border-top d-flex justify-content-between align-items-center">
                        <span class="badge {{ $netCash >= 0 ? 'bg-success' : 'bg-danger' }} bg-opacity-10 {{ $netCash >= 0 ? 'text-success' : 'text-danger' }} rounded-pill px-2 py-0" style="font-size:0.7rem;">
                            {{ $netCash >= 0 ? 'Surplus' : 'Deficit' }}
                        </span>
                        <a href="{{ route('finance.dashboard') }}" class="text-secondary fw-semibold small text-decoration-none">Finance Hub <i class="fa-solid fa-arrow-right ms-1"></i></a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ══════════════════════════════════════════════════════════════════════════
         4. TIER-2 ENTERPRISE ASSETS, PROJECTS & PEOPLE KPIS
         ══════════════════════════════════════════════════════════════════════════ --}}
    <div class="row g-3 mb-4">
        {{-- Active Construction Projects --}}
        <div class="col-xl-3 col-md-6">
            <div class="card border-0 shadow-sm h-100 rounded-3">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <div class="text-xs fw-bold text-muted text-uppercase mb-1" style="font-size:0.72rem;">Projects In Progress</div>
                            <div class="h4 mb-0 fw-bold text-dark">{{ $kpi['active_projects'] ?? 0 }} <span class="fs-6 fw-normal text-muted">/ {{ $kpi['total_projects'] ?? 0 }} Total</span></div>
                            <small class="text-muted">Value: <strong>{{ number_format($kpi['total_contract_value'] ?? 0, 0) }} ETB</strong></small>
                        </div>
                        <div class="rounded-circle p-3 d-flex align-items-center justify-content-center bg-primary bg-opacity-10 text-primary" style="width:46px;height:46px;">
                            <i class="fa-solid fa-city fa-lg"></i>
                        </div>
                    </div>
                    <div class="mt-2 pt-2 border-top">
                        <a href="{{ route('projects.index') }}" class="text-primary small text-decoration-none fw-semibold">Manage Projects <i class="fa-solid fa-arrow-right ms-1"></i></a>
                    </div>
                </div>
            </div>
        </div>

        {{-- Store Inventory Valuation --}}
        <div class="col-xl-3 col-md-6">
            <div class="card border-0 shadow-sm h-100 rounded-3">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <div class="text-xs fw-bold text-muted text-uppercase mb-1" style="font-size:0.72rem;">Stores Inventory Value</div>
                            <div class="h4 mb-0 fw-bold text-dark">{{ number_format($kpi['inventory_value'] ?? 0, 2) }} ETB</div>
                            <small class="text-muted">
                                @if($lowStockItems->count() > 0)
                                    <span class="text-warning"><i class="fa-solid fa-triangle-exclamation me-1"></i>{{ $lowStockItems->count() }} low stock items</span>
                                @else
                                    <span class="text-success"><i class="fa-solid fa-check me-1"></i>Stock healthy</span>
                                @endif
                            </small>
                        </div>
                        <div class="rounded-circle p-3 d-flex align-items-center justify-content-center bg-info bg-opacity-10 text-info" style="width:46px;height:46px;">
                            <i class="fa-solid fa-boxes-stacked fa-lg"></i>
                        </div>
                    </div>
                    <div class="mt-2 pt-2 border-top">
                        <a href="{{ route('store-manager.inventory.all') }}" class="text-info small text-decoration-none fw-semibold">All Inventory <i class="fa-solid fa-arrow-right ms-1"></i></a>
                    </div>
                </div>
            </div>
        </div>

        {{-- Total Active Workforce --}}
        <div class="col-xl-3 col-md-6">
            <div class="card border-0 shadow-sm h-100 rounded-3">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <div class="text-xs fw-bold text-muted text-uppercase mb-1" style="font-size:0.72rem;">Total Active Workforce</div>
                            <div class="h4 mb-0 fw-bold text-dark">{{ $kpi['total_employees'] ?? 0 }} <span class="fs-6 fw-normal text-muted">Employees</span></div>
                            <small class="text-muted">
                                +{{ $kpi['new_employees_month'] ?? 0 }} new this month • {{ $kpi['employees_on_leave'] ?? 0 }} on leave
                            </small>
                        </div>
                        <div class="rounded-circle p-3 d-flex align-items-center justify-content-center bg-success bg-opacity-10 text-success" style="width:46px;height:46px;">
                            <i class="fa-solid fa-users-gear fa-lg"></i>
                        </div>
                    </div>
                    <div class="mt-2 pt-2 border-top">
                        <a href="{{ route('employees.index') }}" class="text-success small text-decoration-none fw-semibold">Employee Directory <i class="fa-solid fa-arrow-right ms-1"></i></a>
                    </div>
                </div>
            </div>
        </div>

        {{-- System Users & Security --}}
        <div class="col-xl-3 col-md-6">
            <div class="card border-0 shadow-sm h-100 rounded-3">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <div class="text-xs fw-bold text-muted text-uppercase mb-1" style="font-size:0.72rem;">System User Accounts</div>
                            <div class="h4 mb-0 fw-bold text-dark">{{ $kpi['total_users'] ?? 0 }} <span class="fs-6 fw-normal text-muted">Users</span></div>
                            <small class="text-muted">
                                @if(($kpi['unassigned_roles_count'] ?? 0) > 0)
                                    <span class="text-danger fw-bold"><i class="fa-solid fa-user-xmark me-1"></i>{{ $kpi['unassigned_roles_count'] }} without roles</span>
                                @else
                                    <span class="text-success"><i class="fa-solid fa-shield-check me-1"></i>All roles assigned</span>
                                @endif
                            </small>
                        </div>
                        <div class="rounded-circle p-3 d-flex align-items-center justify-content-center bg-secondary bg-opacity-10 text-secondary" style="width:46px;height:46px;">
                            <i class="fa-solid fa-user-shield fa-lg"></i>
                        </div>
                    </div>
                    <div class="mt-2 pt-2 border-top">
                        <a href="{{ route('admin.role-assignment.index') }}" class="text-secondary small text-decoration-none fw-semibold">Assign &amp; Manage Roles <i class="fa-solid fa-arrow-right ms-1"></i></a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ══════════════════════════════════════════════════════════════════════════
         5. FINANCIAL TRENDS & RESOURCE DISTRIBUTION CHARTS
         ══════════════════════════════════════════════════════════════════════════ --}}
    <div id="admin-charts-payload" class="d-none"
         data-cashflow-labels="@json($monthlyAnalytics['labels'] ?? [])"
         data-cashflow-incomes="@json($monthlyAnalytics['incomes'] ?? [])"
         data-cashflow-expenses="@json($monthlyAnalytics['expenses'] ?? [])"
         data-budget-names="@json(isset($projectBudgets) ? $projectBudgets->pluck('name') : [])"
         data-budget-budgeted="@json(isset($projectBudgets) ? $projectBudgets->pluck('budgeted_total') : [])"
         data-budget-actual="@json(isset($projectBudgets) ? $projectBudgets->pluck('actual_total') : [])"
         data-expense-cat-labels="@json(isset($expenseCategories) ? $expenseCategories->pluck('category') : [])"
         data-expense-cat-totals="@json(isset($expenseCategories) ? $expenseCategories->pluck('total') : [])"
         data-role-names="@json(isset($usersByRole) ? $usersByRole->pluck('name') : [])"
         data-role-totals="@json(isset($usersByRole) ? $usersByRole->pluck('total') : [])">
    </div>

    <div class="row g-3 mb-4">
        {{-- 6-Month Cash Flow Trend --}}
        <div class="col-xl-8 col-lg-7">
            <div class="card border-0 shadow-sm h-100 rounded-3">
                <div class="card-header bg-transparent py-3 d-flex align-items-center justify-content-between border-0">
                    <h6 class="m-0 fw-bold text-dark">
                        <i class="fa-solid fa-chart-column text-primary me-2"></i>6-Month Cash Flow (Revenue vs Expenses)
                    </h6>
                    <span class="badge bg-light text-muted border px-2 py-1 rounded-pill" style="font-size:0.75rem;">Real Financial Inflow / Outflow</span>
                </div>
                <div class="card-body pt-0">
                    <div style="height: 310px; position: relative;">
                        <canvas id="cashFlowBarChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        {{-- Expense Category Breakdown --}}
        <div class="col-xl-4 col-lg-5">
            <div class="card border-0 shadow-sm h-100 rounded-3">
                <div class="card-header bg-transparent py-3 d-flex align-items-center justify-content-between border-0">
                    <h6 class="m-0 fw-bold text-dark">
                        <i class="fa-solid fa-chart-pie text-danger me-2"></i>Expense by Category
                    </h6>
                    <a href="{{ route('expenses.index') }}" class="small text-muted text-decoration-none">View All</a>
                </div>
                <div class="card-body pt-0">
                    <div style="height: 250px; position: relative;">
                        <canvas id="expenseCategoryChart"></canvas>
                    </div>
                    <div class="text-center mt-2 small text-muted">
                        Real breakdown from recorded company expense vouchers
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ══════════════════════════════════════════════════════════════════════════
         6. CASH & BANK ACCOUNTS LIQUIDITY SNAPSHOT (COA)
         ══════════════════════════════════════════════════════════════════════════ --}}
    <div class="card border-0 shadow-sm rounded-3 mb-4">
        <div class="card-header bg-transparent py-3 d-flex align-items-center justify-content-between flex-wrap gap-2 border-0">
            <div>
                <h6 class="m-0 fw-bold text-dark">
                    <i class="fa-solid fa-vault text-success me-2"></i>Cash &amp; Bank Accounts Ledger (Chart of Accounts)
                </h6>
                <small class="text-muted">Real-time balances across all company bank accounts and physical cash tills</small>
            </div>
            <div class="d-flex gap-2">
                <a href="{{ route('coa-transfers.index') }}" class="btn btn-sm btn-outline-secondary rounded-pill px-3">
                    <i class="fa-solid fa-money-bill-transfer me-1"></i>Internal Transfers
                </a>
                <a href="{{ route('coa.index') }}" class="btn btn-sm btn-outline-primary rounded-pill px-3">
                    <i class="fa-solid fa-list-check me-1"></i>Manage Chart of Accounts
                </a>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light text-uppercase text-muted" style="font-size:0.75rem;">
                        <tr>
                            <th class="ps-3">Code</th>
                            <th>Account Name</th>
                            <th>Account Type</th>
                            <th>Subtype</th>
                            <th>Manager / Custodian</th>
                            <th class="text-end pe-3">Current Balance (ETB)</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($cashAndBankCoas as $account)
                            <tr>
                                <td class="ps-3 fw-bold text-primary font-monospace">{{ $account->code }}</td>
                                <td>
                                    <div class="fw-semibold text-dark">{{ $account->name }}</div>
                                    @if($account->description)
                                        <small class="text-muted" style="font-size:0.75rem;">{{ Str::limit($account->description, 50) }}</small>
                                    @endif
                                </td>
                                <td><span class="badge bg-secondary bg-opacity-10 text-secondary">{{ ucfirst($account->type) }}</span></td>
                                <td><span class="badge bg-info bg-opacity-10 text-info">{{ $account->subtype ?? 'Cash and Bank' }}</span></td>
                                <td>
                                    <small class="text-muted">{{ $account->manager->name ?? 'Unassigned' }}</small>
                                </td>
                                <td class="text-end pe-3">
                                    <span class="fw-bold font-monospace {{ $account->current_balance < 0 ? 'text-danger' : 'text-success' }}">
                                        {{ number_format($account->current_balance, 2) }}
                                    </span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center py-4 text-muted">
                                    <i class="fa-solid fa-wallet fs-3 mb-2 opacity-50"></i>
                                    <p class="mb-0">No active Cash &amp; Bank accounts registered in Chart of Accounts.</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                    <tfoot class="table-light">
                        <tr>
                            <th colspan="5" class="ps-3 text-uppercase text-muted" style="font-size:0.8rem;">Total Available Cash &amp; Bank Liquidity:</th>
                            <th class="text-end pe-3 fs-6 fw-bold text-success font-monospace">
                                {{ number_format($kpi['cash_bank_balance'] ?? 0, 2) }} ETB
                            </th>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>

    {{-- ══════════════════════════════════════════════════════════════════════════
         7. ACTIVE CONSTRUCTION PROJECTS FINANCIAL & BUDGET HEALTH
         ══════════════════════════════════════════════════════════════════════════ --}}
    <div class="card border-0 shadow-sm rounded-3 mb-4">
        <div class="card-header bg-transparent py-3 d-flex align-items-center justify-content-between flex-wrap gap-2 border-0">
            <div>
                <h6 class="m-0 fw-bold text-dark">
                    <i class="fa-solid fa-helmet-safety text-warning me-2"></i>Active Projects Financial Health &amp; Budget Utilization
                </h6>
                <small class="text-muted">Real-time expenditure tracking (Direct cash vouchers + Daily site material consumption valued at market rates)</small>
            </div>
            <a href="{{ route('projects.index') }}" class="btn btn-sm btn-outline-secondary rounded-pill px-3">
                <i class="fa-solid fa-list me-1"></i>All Projects
            </a>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light text-uppercase text-muted" style="font-size:0.75rem;">
                        <tr>
                            <th class="ps-3">Project</th>
                            <th>Contract Value</th>
                            <th>Allocated Budget</th>
                            <th>Cash Vouchers</th>
                            <th>Material Used</th>
                            <th>Total Incurred</th>
                            <th style="min-width: 140px;">Utilization</th>
                            <th class="text-end pe-3">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($projectExpenses as $proj)
                            <tr>
                                <td class="ps-3">
                                    <div class="fw-bold text-dark">{{ $proj['name'] }}</div>
                                    <small class="text-muted font-monospace">{{ $proj['code'] ?? 'No Code' }}</small>
                                </td>
                                <td class="font-monospace text-muted">{{ number_format($proj['contract_value'], 2) }}</td>
                                <td class="font-monospace fw-semibold">{{ number_format($proj['budget_allocated'], 2) }}</td>
                                <td class="font-monospace text-secondary">{{ number_format($proj['cash_expenses'], 2) }}</td>
                                <td class="font-monospace text-secondary">{{ number_format($proj['material_cost'], 2) }}</td>
                                <td class="font-monospace fw-bold {{ $proj['budget_status'] === 'danger' ? 'text-danger' : 'text-dark' }}">
                                    {{ number_format($proj['total_expense'], 2) }}
                                </td>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="progress flex-grow-1" style="height: 7px;">
                                            <div class="progress-bar bg-{{ $proj['budget_status'] }}" role="progressbar" style="width: {{ min(100, $proj['utilization']) }}%;"></div>
                                        </div>
                                        <span class="badge bg-{{ $proj['budget_status'] }} bg-opacity-10 text-{{ $proj['budget_status'] }} font-monospace" style="font-size:0.72rem;">
                                            {{ $proj['utilization'] }}%
                                        </span>
                                    </div>
                                    <small class="text-muted" style="font-size:0.7rem;">Rem: {{ number_format($proj['remaining_budget'], 0) }} ETB</small>
                                </td>
                                <td class="text-end pe-3">
                                    <a href="{{ route('projects.show', $proj['id']) }}" class="btn btn-sm btn-outline-primary py-0 px-2" style="font-size:0.75rem;">
                                        Details
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center py-4 text-muted">
                                    <i class="fa-solid fa-folder-open fs-3 mb-2 opacity-50"></i>
                                    <p class="mb-0">No active projects found.</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- ══════════════════════════════════════════════════════════════════════════
         8. EXECUTIVE DECISION MATRIX & ACTION HUB (TABBED INTERFACE)
         ══════════════════════════════════════════════════════════════════════════ --}}
    <div id="executive-approval-hub" class="card border-0 shadow-sm rounded-3 mb-4">
        <div class="card-header bg-transparent py-3 border-0">
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-2">
                <h6 class="m-0 fw-bold text-dark">
                    <i class="fa-solid fa-gavel text-danger me-2"></i>Executive Decision Hub &amp; Operations Queues
                </h6>
                <small class="text-muted">High-priority items requiring Global Admin / GM decisions or escalation</small>
            </div>
            <ul class="nav nav-tabs card-header-tabs" id="decisionTabs" role="tablist">
                <li class="nav-item">
                    <button class="nav-link active fw-semibold" id="pr-tab" data-bs-toggle="tab" data-bs-target="#tab-prs" type="button" role="tab">
                        <i class="fa-solid fa-cart-arrow-down me-1 text-danger"></i>PR Decisions
                        @if(($kpi['pending_gm_prs'] ?? 0) > 0)
                            <span class="badge bg-danger rounded-pill ms-1">{{ $kpi['pending_gm_prs'] }}</span>
                        @endif
                    </button>
                </li>
                <li class="nav-item">
                    <button class="nav-link fw-semibold" id="expenses-tab" data-bs-toggle="tab" data-bs-target="#tab-expenses" type="button" role="tab">
                        <i class="fa-solid fa-file-invoice-dollar me-1 text-warning"></i>Expense Approvals
                        @if(($kpi['pending_gm_expenses'] ?? 0) > 0)
                            <span class="badge bg-warning text-dark rounded-pill ms-1">{{ $kpi['pending_gm_expenses'] }}</span>
                        @endif
                    </button>
                </li>
                <li class="nav-item">
                    <button class="nav-link fw-semibold" id="roles-tab" data-bs-toggle="tab" data-bs-target="#tab-roles" type="button" role="tab">
                        <i class="fa-solid fa-user-tag me-1 text-info"></i>Unassigned Roles
                        @if(($kpi['unassigned_roles_count'] ?? 0) > 0)
                            <span class="badge bg-danger rounded-pill ms-1">{{ $kpi['unassigned_roles_count'] }}</span>
                        @endif
                    </button>
                </li>
                <li class="nav-item">
                    <button class="nav-link fw-semibold" id="employees-tab" data-bs-toggle="tab" data-bs-target="#tab-employees" type="button" role="tab">
                        <i class="fa-solid fa-user-check me-1 text-primary"></i>Pending Employees
                        @if(($kpi['pending_approvals'] ?? 0) > 0)
                            <span class="badge bg-primary rounded-pill ms-1">{{ $kpi['pending_approvals'] }}</span>
                        @endif
                    </button>
                </li>
                <li class="nav-item">
                    <button class="nav-link fw-semibold" id="issues-tab" data-bs-toggle="tab" data-bs-target="#tab-issues" type="button" role="tab">
                        <i class="fa-solid fa-triangle-exclamation me-1 text-danger"></i>Site Issues
                        @if(($kpi['open_issues'] ?? 0) > 0)
                            <span class="badge bg-danger rounded-pill ms-1">{{ $kpi['open_issues'] }}</span>
                        @endif
                    </button>
                </li>
            </ul>
        </div>
        <div class="card-body p-0">
            <div class="tab-content" id="decisionTabsContent">

                {{-- TAB 1: PURCHASE REQUESTS PENDING GM/ADMIN --}}
                <div class="tab-pane fade show active p-3" id="tab-prs" role="tabpanel">
                    @if($pendingGmPrs->count() > 0)
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light text-uppercase text-muted" style="font-size:0.75rem;">
                                    <tr>
                                        <th>PR No</th>
                                        <th>Project</th>
                                        <th>Requested By</th>
                                        <th>Supplier / Proforma</th>
                                        <th>Est. Amount</th>
                                        <th>Created</th>
                                        <th class="text-end">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($pendingGmPrs as $pr)
                                        @php
                                            $prAmount = (float)($pr->direct_buy_amount ?: $pr->items->sum('estimated_total'));
                                        @endphp
                                        <tr>
                                            <td class="fw-bold font-monospace text-primary">{{ $pr->request_number ?? ('PR-#' . $pr->id) }}</td>
                                            <td>{{ $pr->project->name ?? 'General / Head Office' }}</td>
                                            <td>{{ $pr->requestedBy->name ?? 'Staff' }}</td>
                                            <td>
                                                <span class="badge bg-light text-dark border">{{ $pr->supplier->name ?? 'Proforma Comparison' }}</span>
                                            </td>
                                            <td class="fw-bold font-monospace text-danger">{{ number_format($prAmount, 2) }} ETB</td>
                                            <td><small class="text-muted">{{ $pr->created_at->diffForHumans() }}</small></td>
                                            <td class="text-end">
                                                <a href="{{ route('procurement.my-queue', ['status' => 'pending_gm']) }}" class="btn btn-sm btn-outline-danger py-1 px-3 rounded-pill text-xs">
                                                    Review &amp; Decide
                                                </a>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="text-center py-4 text-muted">
                            <i class="fa-solid fa-circle-check fs-3 mb-2 text-success opacity-75"></i>
                            <p class="mb-0 fw-semibold">No purchase requests awaiting GM / Admin decision.</p>
                        </div>
                    @endif
                </div>

                {{-- TAB 2: EXPENSE REQUESTS PENDING GM/ADMIN --}}
                <div class="tab-pane fade p-3" id="tab-expenses" role="tabpanel">
                    @if($pendingGmExpenses->count() > 0)
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light text-uppercase text-muted" style="font-size:0.75rem;">
                                    <tr>
                                        <th>Code</th>
                                        <th>Project</th>
                                        <th>Requester</th>
                                        <th>Category / Reason</th>
                                        <th>Amount</th>
                                        <th>Date</th>
                                        <th class="text-end">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($pendingGmExpenses as $exp)
                                        <tr>
                                            <td class="fw-bold font-monospace text-warning">{{ $exp->request_code ?? ('EXP-#' . $exp->id) }}</td>
                                            <td>{{ $exp->project->name ?? 'Company General' }}</td>
                                            <td>{{ $exp->requester->name ?? ($exp->user->name ?? 'Staff') }}</td>
                                            <td>
                                                <div class="fw-semibold text-dark">{{ $exp->category ?? 'Operational' }}</div>
                                                <small class="text-muted">{{ Str::limit($exp->reason ?? $exp->description, 40) }}</small>
                                            </td>
                                            <td class="fw-bold font-monospace text-danger">{{ number_format($exp->amount, 2) }} ETB</td>
                                            <td><small class="text-muted">{{ $exp->created_at->diffForHumans() }}</small></td>
                                            <td class="text-end">
                                                <a href="{{ route('expenses.index', ['tab' => 'pending_gm']) }}" class="btn btn-sm btn-outline-warning py-1 px-3 rounded-pill text-xs">
                                                    Authorize
                                                </a>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="text-center py-4 text-muted">
                            <i class="fa-solid fa-circle-check fs-3 mb-2 text-success opacity-75"></i>
                            <p class="mb-0 fw-semibold">No expense authorizations pending approval.</p>
                        </div>
                    @endif
                </div>

                {{-- TAB 3: UNASSIGNED ROLES (NEW USERS) --}}
                <div class="tab-pane fade p-3" id="tab-roles" role="tabpanel">
                    @php
                        $displayUnassigned = isset($unassignedUsers) ? $unassignedUsers->filter(function($u) {
                            if (!$u->is_active) return false;
                            $emp = $u->employee;
                            if ($emp && ($emp->is_dead_file || $emp->status === 'dead_file')) return false;
                            return true;
                        }) : collect();
                    @endphp

                    @if($displayUnassigned->count() > 0)
                        <div class="alert alert-warning border-0 p-2 mb-3 small d-flex align-items-center gap-2">
                            <i class="fa-solid fa-triangle-exclamation"></i>
                            <span>These registered employees have account credentials but cannot access system dashboards until a system role is assigned.</span>
                        </div>
                        <div class="row g-2">
                            @foreach($displayUnassigned as $uUser)
                                <div class="col-md-6 col-lg-4">
                                    <div class="card border shadow-xs h-100 p-2">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div>
                                                <div class="fw-bold text-dark text-sm">{{ $uUser->name }}</div>
                                                <small class="text-muted d-block">{{ $uUser->email }}</small>
                                                <small class="text-primary">{{ $uUser->employee->department ?? 'General' }} • {{ $uUser->employee->role_title ?? 'Employee' }}</small>
                                            </div>
                                            <a href="{{ route('admin.role-assignment.index') }}" class="btn btn-sm btn-info text-white rounded-pill px-3 py-1 text-xs">
                                                Assign
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="text-center py-4 text-muted">
                            <i class="fa-solid fa-user-check fs-3 mb-2 text-success opacity-75"></i>
                            <p class="mb-0 fw-semibold">All active users have valid system roles assigned.</p>
                        </div>
                    @endif
                </div>

                {{-- TAB 4: PENDING EMPLOYEE APPROVALS --}}
                <div class="tab-pane fade p-3" id="tab-employees" role="tabpanel">
                    @if($pendingEmployees->count() > 0)
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light text-uppercase text-muted" style="font-size:0.75rem;">
                                    <tr>
                                        <th>Name</th>
                                        <th>Department</th>
                                        <th>Position / Role</th>
                                        <th>Registered Date</th>
                                        <th class="text-end">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($pendingEmployees as $emp)
                                        <tr>
                                            <td class="fw-semibold text-dark">{{ $emp->name ?? ($emp->first_name . ' ' . $emp->last_name) }}</td>
                                            <td><span class="badge bg-light text-dark border">{{ $emp->department ?? 'General' }}</span></td>
                                            <td>{{ $emp->role_title ?? $emp->position ?? 'Staff' }}</td>
                                            <td><small class="text-muted">{{ $emp->created_at ? $emp->created_at->format('M d, Y') : 'N/A' }}</small></td>
                                            <td class="text-end">
                                                <a href="{{ route('employees.pending-approval') }}" class="btn btn-sm btn-outline-primary rounded-pill px-3 py-1 text-xs">
                                                    Approve File
                                                </a>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="text-center py-4 text-muted">
                            <i class="fa-solid fa-circle-check fs-3 mb-2 text-success opacity-75"></i>
                            <p class="mb-0 fw-semibold">No employee onboarding records awaiting approval.</p>
                        </div>
                    @endif
                </div>

                {{-- TAB 5: OPEN SITE ISSUES --}}
                <div class="tab-pane fade p-3" id="tab-issues" role="tabpanel">
                    @if($recentIssues->count() > 0)
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light text-uppercase text-muted" style="font-size:0.75rem;">
                                    <tr>
                                        <th>Project</th>
                                        <th>Issue Title / Description</th>
                                        <th>Severity</th>
                                        <th>Reported</th>
                                        <th class="text-end">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($recentIssues as $issue)
                                        <tr>
                                            <td class="fw-bold text-dark">{{ $issue->project->name ?? 'General Site' }}</td>
                                            <td>
                                                <div class="fw-semibold">{{ $issue->title ?? 'Site Incident' }}</div>
                                                <small class="text-muted">{{ Str::limit($issue->description, 50) }}</small>
                                            </td>
                                            <td>
                                                <span class="badge bg-{{ ($issue->severity ?? 'medium') === 'high' || ($issue->severity ?? '') === 'critical' ? 'danger' : 'warning' }}">
                                                    {{ ucfirst($issue->severity ?? 'Open') }}
                                                </span>
                                            </td>
                                            <td><small class="text-muted">{{ $issue->created_at->diffForHumans() }}</small></td>
                                            <td class="text-end">
                                                <a href="{{ route('issues.show', $issue->id) }}" class="btn btn-sm btn-outline-danger py-1 px-3 rounded-pill text-xs">
                                                    Inspect Issue
                                                </a>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="text-center py-4 text-muted">
                            <i class="fa-solid fa-circle-check fs-3 mb-2 text-success opacity-75"></i>
                            <p class="mb-0 fw-semibold">Zero critical open site issues reported.</p>
                        </div>
                    @endif
                </div>

            </div>
        </div>
    </div>

    {{-- ══════════════════════════════════════════════════════════════════════════
         9. STORES INVENTORY ALERTS & OFFICIAL CORRESPONDENCE OVERVIEW
         ══════════════════════════════════════════════════════════════════════════ --}}
    <div class="row g-3 mb-4">
        {{-- Left: Low Stock Store Inventory Alerts --}}
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm h-100 rounded-3">
                <div class="card-header bg-transparent py-3 d-flex align-items-center justify-content-between border-0">
                    <div>
                        <h6 class="m-0 fw-bold text-dark">
                            <i class="fa-solid fa-boxes-packing text-warning me-2"></i>Critical Low Stock Alerts
                        </h6>
                        <small class="text-muted">Warehouse materials with stock at or below critical reorder levels</small>
                    </div>
                    <a href="{{ route('store-manager.inventory.all') }}" class="btn btn-sm btn-outline-secondary rounded-pill px-3">
                        All Stores
                    </a>
                </div>
                <div class="card-body p-0">
                    @if($lowStockItems->count() > 0)
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light text-uppercase text-muted" style="font-size:0.75rem;">
                                    <tr>
                                        <th class="ps-3">Material</th>
                                        <th>Store / Warehouse</th>
                                        <th>Quantity On Hand</th>
                                        <th class="text-end pe-3">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($lowStockItems as $invItem)
                                        <tr>
                                            <td class="ps-3">
                                                <div class="fw-bold text-dark">{{ $invItem->product_name }}</div>
                                                <small class="text-muted font-monospace">{{ $invItem->item_code ?? 'MAT' }}</small>
                                            </td>
                                            <td><span class="badge bg-light text-dark border">{{ $invItem->store_name }}</span></td>
                                            <td>
                                                <span class="badge bg-{{ $invItem->quantity_on_hand <= 0 ? 'danger' : 'warning' }} font-monospace fs-6">
                                                    {{ number_format($invItem->quantity_on_hand, 2) }} {{ $invItem->unit_of_measure ?? 'PCS' }}
                                                </span>
                                            </td>
                                            <td class="text-end pe-3">
                                                <a href="{{ route('purchase-requests.create') }}" class="btn btn-sm btn-outline-primary py-0 px-2 text-xs">
                                                    Reorder PR
                                                </a>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="text-center py-4 text-muted">
                            <i class="fa-solid fa-check-circle fs-3 mb-2 text-success opacity-75"></i>
                            <p class="mb-0">All inventory items are currently well above reorder thresholds.</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        {{-- Right: Official Correspondence & Letters Overview --}}
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm h-100 rounded-3">
                <div class="card-header bg-transparent py-3 d-flex align-items-center justify-content-between border-0">
                    <div>
                        <h6 class="m-0 fw-bold text-dark">
                            <i class="fa-solid fa-envelope-open-text text-info me-2"></i>Official Correspondence &amp; Letters
                        </h6>
                        <small class="text-muted">Live incoming, outgoing, and draft letters tracked by OCR &amp; Secretary</small>
                    </div>
                    <a href="{{ route('letters.dashboard') }}" class="btn btn-sm btn-outline-info rounded-pill px-3">
                        Letter Center
                    </a>
                </div>
                <div class="card-body p-3">
                    <div class="row g-2 text-center mb-3">
                        <div class="col-4">
                            <div class="p-2 border rounded-3 bg-light">
                                <div class="fs-5 fw-bold text-primary">{{ $kpi['incoming_letters'] ?? 0 }}</div>
                                <small class="text-muted text-uppercase" style="font-size:0.7rem;">Incoming</small>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="p-2 border rounded-3 bg-light">
                                <div class="fs-5 fw-bold text-success">{{ $kpi['outgoing_letters'] ?? 0 }}</div>
                                <small class="text-muted text-uppercase" style="font-size:0.7rem;">Outgoing</small>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="p-2 border rounded-3 bg-light">
                                <div class="fs-5 fw-bold text-warning">{{ $kpi['draft_letters'] ?? 0 }}</div>
                                <small class="text-muted text-uppercase" style="font-size:0.7rem;">Drafts / Unregistered</small>
                            </div>
                        </div>
                    </div>

                    @if($recentLetters->count() > 0)
                        <div class="list-group list-group-flush">
                            @foreach($recentLetters as $letter)
                                <div class="list-group-item px-0 py-2 border-bottom-0 d-flex justify-content-between align-items-center">
                                    <div>
                                        <div class="fw-semibold text-dark text-truncate" style="max-width: 320px;">
                                            <i class="fa-solid {{ $letter->type === 'incoming' ? 'fa-arrow-down-left text-primary' : 'fa-arrow-up-right text-success' }} me-1"></i>
                                            {{ $letter->subject ?? 'Official Letter' }}
                                        </div>
                                        <small class="text-muted">
                                            Ref: <strong class="font-monospace">{{ $letter->ref_no ?? 'Unregistered Draft' }}</strong> • {{ $letter->created_at->diffForHumans() }}
                                        </small>
                                    </div>
                                    <a href="{{ route('letters.show', $letter->id) }}" class="btn btn-sm btn-outline-secondary py-0 px-2 text-xs">
                                        View
                                    </a>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="text-center py-3 text-muted">
                            <p class="mb-0 small">No recent correspondence items recorded.</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- ══════════════════════════════════════════════════════════════════════════
         10. FLEET & EQUIPMENT COMPLIANCE REMINDERS
         ══════════════════════════════════════════════════════════════════════════ --}}
    @if($overdueReminders->count() > 0)
        <div class="card border-0 shadow-sm rounded-3 mb-4 border-left-warning">
            <div class="card-header bg-transparent py-3 d-flex align-items-center justify-content-between border-0">
                <div>
                    <h6 class="m-0 fw-bold text-warning text-dark">
                        <i class="fa-solid fa-truck-monster text-warning me-2"></i>Fleet &amp; Machinery Reminders (Insurance, Bolo, Inspections)
                    </h6>
                    <small class="text-muted">Vehicles or equipment with statutory compliance documents due or expiring soon</small>
                </div>
                <a href="{{ route('vehicle-reminders.dashboard') }}" class="btn btn-sm btn-outline-warning rounded-pill px-3">
                    Reminders Hub
                </a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light text-uppercase text-muted" style="font-size:0.75rem;">
                            <tr>
                                <th class="ps-3">Vehicle / Equipment</th>
                                <th>Plate / Serial</th>
                                <th>Reminder Type</th>
                                <th>Expiry Date</th>
                                <th class="text-end pe-3">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($overdueReminders as $reminder)
                                <tr>
                                    <td class="ps-3 fw-bold text-dark">{{ $reminder->fixedAsset->name ?? 'Vehicle' }}</td>
                                    <td><span class="badge bg-light text-dark border font-monospace">{{ $reminder->assetUnit->plate_number ?? ($reminder->assetUnit->serial_number ?? 'N/A') }}</span></td>
                                    <td><span class="badge bg-warning bg-opacity-10 text-dark">{{ ucfirst(str_replace('_', ' ', $reminder->reminder_type)) }}</span></td>
                                    <td class="font-monospace text-danger fw-semibold">
                                        {{ $reminder->bolo_expiry_date ?? $reminder->insurance_expiry_date ?? 'Due Soon' }}
                                    </td>
                                    <td class="text-end pe-3">
                                        <a href="{{ route('vehicle-reminders.show', $reminder->id) }}" class="btn btn-sm btn-outline-secondary py-0 px-2 text-xs">
                                            Renew
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endif

    {{-- ══════════════════════════════════════════════════════════════════════════
         11. VAT & WITHHOLDING TAX COMPLIANCE REPORT (ERCA eTax LEDGER)
         ══════════════════════════════════════════════════════════════════════════ --}}
    <div id="vat-withholding-report" class="mb-4">
        @include('dashboard.partials.tax_compliance_report')
    </div>

    {{-- ══════════════════════════════════════════════════════════════════════════
         12. AUDIT TRAIL, HELPDESK & SERVER DIAGNOSTICS ROW
         ══════════════════════════════════════════════════════════════════════════ --}}
    <div class="row g-3 mb-4">
        {{-- Col 1: Live Activity Feed --}}
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm h-100 rounded-3">
                <div class="card-header bg-transparent py-3 d-flex align-items-center justify-content-between border-0">
                    <h6 class="m-0 fw-bold text-dark">
                        <i class="fa-solid fa-list-ol text-primary me-2"></i>Live System Audit Feed
                    </h6>
                    <a href="{{ route('admin.activity-logs') }}" class="small text-muted text-decoration-none">Full Audit</a>
                </div>
                <div class="card-body p-3" style="max-height: 380px; overflow-y: auto;">
                    @if(isset($activityLogs) && $activityLogs->count() > 0)
                        <div class="list-group list-group-flush">
                            @foreach($activityLogs as $log)
                                <div class="list-group-item px-0 py-2 border-bottom-0">
                                    <div class="d-flex w-100 justify-content-between">
                                        <h6 class="mb-1 fw-bold text-dark" style="font-size: 13px;">
                                            <i class="fa-solid {{ $log->action_icon ?? 'fa-circle-dot' }} text-{{ $log->action_color ?? 'secondary' }} me-1"></i>
                                            {{ $log->user->name ?? 'System' }}
                                        </h6>
                                        <small class="text-muted" style="font-size: 11px;">{{ $log->created_at->diffForHumans() }}</small>
                                    </div>
                                    <p class="mb-1 text-muted" style="font-size: 12px; margin-left: 18px;">
                                        {{ $log->description }}
                                    </p>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="text-center py-4 text-muted">
                            <i class="fa-solid fa-shield-halved fs-3 mb-2 opacity-50"></i>
                            <p class="mb-0 small">No recent activity logged.</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        {{-- Col 2: Support Tickets Desk --}}
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm h-100 rounded-3">
                <div class="card-header bg-transparent py-3 d-flex align-items-center justify-content-between border-0">
                    <h6 class="m-0 fw-bold text-dark">
                        <i class="fa-solid fa-ticket text-danger me-2"></i>Support Tickets Desk
                    </h6>
                    <a href="{{ route('admin.tickets.index') }}" class="small text-muted text-decoration-none">All Tickets</a>
                </div>
                <div class="card-body p-3">
                    <div class="row text-center mb-3">
                        <div class="col-4">
                            <div class="p-2 border rounded-3 bg-light">
                                <div class="fs-5 fw-bold text-danger">{{ $ticketStats['open'] ?? 0 }}</div>
                                <small class="text-muted text-uppercase" style="font-size:0.7rem;">Open</small>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="p-2 border rounded-3 bg-light">
                                <div class="fs-5 fw-bold text-warning">{{ $ticketStats['in_progress'] ?? 0 }}</div>
                                <small class="text-muted text-uppercase" style="font-size:0.7rem;">In Progress</small>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="p-2 border rounded-3 bg-light">
                                <div class="fs-5 fw-bold text-success">{{ $ticketStats['resolved'] ?? 0 }}</div>
                                <small class="text-muted text-uppercase" style="font-size:0.7rem;">Resolved</small>
                            </div>
                        </div>
                    </div>

                    <h6 class="text-xs fw-bold text-uppercase text-muted mb-2" style="font-size:0.75rem;">Recent Helpdesk Tickets</h6>
                    @if(isset($recentTickets) && $recentTickets->count() > 0)
                        <div class="list-group list-group-flush">
                            @foreach($recentTickets as $ticket)
                                <a href="{{ route('admin.tickets.show', $ticket) }}" class="list-group-item list-group-item-action px-0 py-2 border-bottom-0">
                                    <div class="d-flex w-100 justify-content-between">
                                        <h6 class="mb-1 text-sm fw-bold text-dark text-truncate" style="max-width: 70%;">{{ $ticket->subject }}</h6>
                                        <small>{!! $ticket->status_badge ?? '' !!}</small>
                                    </div>
                                    <small class="text-muted" style="font-size:11px;">By {{ $ticket->user->name ?? 'Staff' }} • {{ $ticket->created_at->diffForHumans() }}</small>
                                </a>
                            @endforeach
                        </div>
                    @else
                        <div class="text-center py-3 text-muted">
                            <p class="mb-0 small">No recent support tickets.</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        {{-- Col 3: System Health & Server Diagnostics --}}
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm h-100 rounded-3">
                <div class="card-header bg-transparent py-3 d-flex align-items-center justify-content-between border-0">
                    <h6 class="m-0 fw-bold text-dark">
                        <i class="fa-solid fa-server text-teal me-2"></i>Server &amp; System Health
                    </h6>
                    <span class="badge bg-success bg-opacity-10 text-success rounded-pill px-2 py-1" style="font-size:0.75rem;">
                        <i class="fa-solid fa-check me-1"></i>Healthy
                    </span>
                </div>
                <div class="card-body p-3">
                    <div class="list-group list-group-flush small mb-3">
                        <div class="list-group-item px-0 py-2 d-flex justify-content-between">
                            <span class="text-muted">PHP Version</span>
                            <span class="fw-bold font-monospace">{{ $systemDiagnostics['php_version'] ?? PHP_VERSION }}</span>
                        </div>
                        <div class="list-group-item px-0 py-2 d-flex justify-content-between">
                            <span class="text-muted">Laravel Framework</span>
                            <span class="fw-bold font-monospace">v{{ $systemDiagnostics['laravel_version'] ?? app()->version() }}</span>
                        </div>
                        <div class="list-group-item px-0 py-2 d-flex justify-content-between">
                            <span class="text-muted">Database Engine</span>
                            <span class="badge bg-light text-dark border font-monospace">{{ strtoupper($systemDiagnostics['db_connection'] ?? 'MYSQL') }}</span>
                        </div>
                        <div class="list-group-item px-0 py-2 d-flex justify-content-between">
                            <span class="text-muted">Environment</span>
                            <span class="badge bg-primary bg-opacity-10 text-primary">{{ ucfirst($systemDiagnostics['environment'] ?? 'production') }}</span>
                        </div>
                        <div class="list-group-item px-0 py-2 d-flex justify-content-between">
                            <span class="text-muted">Cache Driver</span>
                            <span class="fw-bold font-monospace">{{ $systemDiagnostics['cache_driver'] ?? 'file' }}</span>
                        </div>
                        <div class="list-group-item px-0 py-2 d-flex justify-content-between">
                            <span class="text-muted">Server Time</span>
                            <span class="text-dark font-monospace" style="font-size:11px;">{{ $systemDiagnostics['server_time'] ?? now()->toDayDateTimeString() }}</span>
                        </div>
                    </div>

                    <div class="d-grid gap-2">
                        <a href="{{ route('dev.roles') }}" class="btn btn-outline-warning btn-sm rounded-pill">
                            <i class="fa-solid fa-vial me-1"></i>Open Role Switcher &amp; Tester
                        </a>
                        <a href="{{ route('system.clear-cache') }}" class="btn btn-outline-secondary btn-sm rounded-pill" onclick="return confirm('Clear application and route caches now?')">
                            <i class="fa-solid fa-arrows-rotate me-1"></i>Flush &amp; Rebuild App Cache
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

</div>

{{-- ══════════════════════════════════════════════════════════════════════════
     CHARTS SCRIPT
     ══════════════════════════════════════════════════════════════════════════ --}}
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    document.addEventListener("DOMContentLoaded", function() {
        var payload = document.getElementById("admin-charts-payload");
        if (!payload) return;

        var cashFlowLabels = JSON.parse(payload.dataset.cashflowLabels || '[]');
        var cashFlowIncomes = JSON.parse(payload.dataset.cashflowIncomes || '[]');
        var cashFlowExpenses = JSON.parse(payload.dataset.cashflowExpenses || '[]');

        var expenseCatLabels = JSON.parse(payload.dataset.expenseCatLabels || '[]');
        var expenseCatTotals = JSON.parse(payload.dataset.expenseCatTotals || '[]');

        // 1. 6-Month Cash Flow Trend Bar Chart
        var ctxCashFlow = document.getElementById("cashFlowBarChart");
        if (ctxCashFlow) {
            new Chart(ctxCashFlow, {
                type: 'bar',
                data: {
                    labels: cashFlowLabels,
                    datasets: [
                        {
                            label: "Revenue / Inflow",
                            backgroundColor: "#10b981",
                            borderRadius: 6,
                            data: cashFlowIncomes
                        },
                        {
                            label: "Expenses / Outflow",
                            backgroundColor: "#ef4444",
                            borderRadius: 6,
                            data: cashFlowExpenses
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { position: 'top' },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return context.dataset.label + ': ' + Number(context.raw).toLocaleString() + ' ETB';
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) {
                                    return Number(value).toLocaleString();
                                }
                            }
                        }
                    }
                }
            });
        }

        // 2. Expense Category Breakdown Doughnut Chart
        var ctxExpenseCat = document.getElementById("expenseCategoryChart");
        if (ctxExpenseCat && expenseCatLabels.length > 0) {
            new Chart(ctxExpenseCat, {
                type: 'doughnut',
                data: {
                    labels: expenseCatLabels,
                    datasets: [{
                        data: expenseCatTotals,
                        backgroundColor: [
                            '#3b82f6', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6', '#ec4899', '#6b7280'
                        ],
                        borderWidth: 2
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { position: 'bottom' },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return context.label + ': ' + Number(context.raw).toLocaleString() + ' ETB';
                                }
                            }
                        }
                    }
                }
            });
        }
    });
</script>
@endsection
