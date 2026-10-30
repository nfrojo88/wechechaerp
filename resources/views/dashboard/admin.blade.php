@extends('layouts.app')
@section('title', 'Global Admin Dashboard')
@section('content')
<div class="wc-container py-3 py-md-4">

    {{-- ══════════════════════════════════════════════════════════════════════════
         1. EXECUTIVE HEADER & REAL-TIME SYSTEM BAR
         ══════════════════════════════════════════════════════════════════════════ --}}
    <x-page-header
        title="Global Admin Executive Dashboard"
        subtitle="Enterprise management, cross-departmental operations, approvals &amp; strategic command"
        badge="Command Center"
    >
        <x-slot name="actions">
            <div class="d-flex gap-2 flex-wrap align-items-center">
                @if(!empty($ethiopianDateStr))
                    <span class="badge bg-warning bg-opacity-10 text-dark border border-warning border-opacity-50 px-2 py-1 rounded-pill fw-semibold d-none d-sm-inline-flex align-items-center" style="font-size:0.75rem;">
                        <i class="fa-solid fa-calendar-day me-1 text-warning"></i>{{ $ethiopianDateStr }}
                    </span>
                @endif
                <span class="badge bg-light text-muted border px-2 py-1 rounded-pill d-none d-md-inline-flex align-items-center" style="font-size:0.75rem;">
                    <i class="fa-regular fa-clock me-1"></i>{{ now()->format('l, F j, Y — H:i') }}
                </span>
                <a href="{{ route('system.clear-cache') }}" class="btn btn-outline-secondary btn-sm rounded-pill px-3 shadow-xs" style="min-height: 36px; display: inline-flex; align-items: center;" onclick="return confirm('Clear application and route caches now?')">
                    <i class="fa-solid fa-broom me-1 text-warning"></i>Clear Cache
                </a>
                <a href="{{ route('system.run-migrations') }}" class="btn btn-outline-success btn-sm rounded-pill px-3 shadow-xs" style="min-height: 36px; display: inline-flex; align-items: center;" onclick="return confirm('Run database migrations and resync now?')">
                    <i class="fa-solid fa-database me-1"></i>Auto Migrate DB
                </a>
                <a href="{{ route('admin.role-assignment.index') }}" class="btn btn-outline-info btn-sm rounded-pill px-3 shadow-xs" style="min-height: 36px; display: inline-flex; align-items: center;">
                    <i class="fa-solid fa-user-gear me-1"></i>User &amp; Roles
                    @if(($kpi['unassigned_roles_count'] ?? 0) > 0)
                        <span class="badge bg-danger rounded-pill ms-1">{{ $kpi['unassigned_roles_count'] }}</span>
                    @endif
                </a>
                <a href="{{ route('coa.index') }}" class="btn btn-outline-dark btn-sm rounded-pill px-3 shadow-xs" style="min-height: 36px; display: inline-flex; align-items: center;">
                    <i class="fa-solid fa-building-columns me-1 text-primary"></i>COA
                </a>
                <a href="#executive-approval-hub" class="btn btn-outline-warning text-dark btn-sm rounded-pill px-3 shadow-xs" style="min-height: 36px; display: inline-flex; align-items: center;">
                    <i class="fa-solid fa-gavel me-1"></i>Decision Hub
                    @if(($totalUrgentActions ?? 0) > 0)
                        <span class="badge bg-danger rounded-pill ms-1">{{ $totalUrgentActions ?? 0 }}</span>
                    @endif
                </a>
                <a href="#vat-withholding-report" class="btn btn-outline-primary btn-sm rounded-pill px-3 shadow-xs" style="min-height: 36px; display: inline-flex; align-items: center;">
                    <i class="fa-solid fa-receipt me-1"></i>Tax &amp; ERCA
                </a>
            </div>
        </x-slot>
    </x-page-header>

    {{-- ══════════════════════════════════════════════════════════════════════════
         DYNAMIC PERIOD & DATA SCOPE FILTER BAR (DON'T SHOW OLD DATA)
         ══════════════════════════════════════════════════════════════════════════ --}}
    <div class="card border-0 shadow-sm rounded-4 mb-4 bg-white">
        <div class="card-body p-2 p-md-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <span class="fw-bold text-dark small text-uppercase" style="letter-spacing: 0.5px; font-size: 0.75rem;">
                    <i class="fa-solid fa-filter text-primary me-1"></i>Active Period Scope:
                </span>
                <div class="btn-group btn-group-sm rounded-pill p-1 bg-light border shadow-xs" role="group">
                    <a href="{{ route('dashboard.admin', ['period' => 'current_month']) }}"
                       class="btn btn-sm rounded-pill px-3 py-1 fw-semibold {{ ($period ?? 'current_month') === 'current_month' ? 'btn-primary text-white shadow-xs' : 'btn-light text-muted' }}">
                        Current Month
                    </a>
                    <a href="{{ route('dashboard.admin', ['period' => 'today']) }}"
                       class="btn btn-sm rounded-pill px-3 py-1 fw-semibold {{ ($period ?? '') === 'today' ? 'btn-primary text-white shadow-xs' : 'btn-light text-muted' }}">
                        Today
                    </a>
                    <a href="{{ route('dashboard.admin', ['period' => 'last_30_days']) }}"
                       class="btn btn-sm rounded-pill px-3 py-1 fw-semibold {{ ($period ?? '') === 'last_30_days' ? 'btn-primary text-white shadow-xs' : 'btn-light text-muted' }}">
                        Last 30 Days
                    </a>
                    <a href="{{ route('dashboard.admin', ['period' => 'current_year']) }}"
                       class="btn btn-sm rounded-pill px-3 py-1 fw-semibold {{ ($period ?? '') === 'current_year' ? 'btn-primary text-white shadow-xs' : 'btn-light text-muted' }}">
                        Fiscal Year
                    </a>
                    <a href="{{ route('dashboard.admin', ['period' => 'all']) }}"
                       class="btn btn-sm rounded-pill px-3 py-1 fw-semibold {{ ($period ?? '') === 'all' ? 'btn-secondary text-white shadow-xs' : 'btn-light text-muted' }}">
                        All History
                    </a>
                </div>
            </div>
            <div class="d-flex align-items-center gap-2 text-muted small">
                <span class="badge bg-success bg-opacity-10 text-success rounded-pill px-2 py-1">
                    <i class="fa-solid fa-shield-check me-1"></i>Active Data Filter
                </span>
                <span class="font-monospace text-dark fw-semibold">{{ $periodLabel ?? 'Current Month' }}</span>
                <span class="text-muted d-none d-lg-inline">• Dead files &amp; closed projects excluded</span>
            </div>
        </div>
    </div>

    @php
        $totalUrgentActions = $totalUrgentActions ?? (
            ($kpi['pending_gm_prs'] ?? 0)
            + ($kpi['unassigned_prs_count'] ?? 0)
            + ($kpi['pending_gm_expenses'] ?? 0)
            + ($kpi['pending_approvals'] ?? 0)
            + ($kpi['unassigned_roles_count'] ?? 0)
            + ($kpi['pending_maintenance'] ?? 0)
            + ($ticketStats['open'] ?? 0)
        );
    @endphp

    {{-- ══════════════════════════════════════════════════════════════════════════
         TODAY'S OPERATIONS LIVE PULSE RIBBON
         ══════════════════════════════════════════════════════════════════════════ --}}
    <div class="card border-0 shadow-sm rounded-4 mb-4 bg-white" style="border-left: 5px solid #0284c7 !important;">
        <div class="card-body p-3 p-md-4">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                <div class="d-flex align-items-center gap-2">
                    <span class="badge bg-info bg-opacity-10 text-info px-3 py-1 rounded-pill fw-bold" style="font-size:0.75rem;">
                        <i class="fa-solid fa-bolt me-1"></i>TODAY'S OPERATIONS PULSE
                    </span>
                    <span class="text-muted small">Live enterprise activity for {{ now()->format('l, F j, Y') }}</span>
                </div>
                <div class="d-flex gap-2">
                    <a href="{{ route('attendance.index') }}" class="btn btn-sm btn-outline-secondary rounded-pill px-3">
                        <i class="fa-solid fa-user-clock me-1"></i>Attendance Sheet
                    </a>
                    <a href="{{ route('transfers.index') }}" class="btn btn-sm btn-outline-primary rounded-pill px-3">
                        <i class="fa-solid fa-truck-ramp-box me-1"></i>Material TRs
                    </a>
                </div>
            </div>

            <div class="row g-2 text-center">
                {{-- 1. Today Inflow --}}
                <div class="col-6 col-md-4 col-xl">
                    <div class="p-3 rounded-3 bg-light border h-100">
                        <small class="text-muted text-uppercase fw-bold d-block" style="font-size:0.7rem;">Today Inflow</small>
                        <div class="fs-5 fw-bold text-success font-monospace my-1">
                            +{{ number_format($todayPulse['income'] ?? 0, 2) }}
                        </div>
                        <span class="badge bg-success bg-opacity-10 text-success rounded-pill px-2 py-0" style="font-size:0.68rem;">
                            Direct Inflow ETB
                        </span>
                    </div>
                </div>

                {{-- 2. Today Outflow --}}
                <div class="col-6 col-md-4 col-xl">
                    <div class="p-3 rounded-3 bg-light border h-100">
                        <small class="text-muted text-uppercase fw-bold d-block" style="font-size:0.7rem;">Today Disbursements</small>
                        <div class="fs-5 fw-bold text-danger font-monospace my-1">
                            -{{ number_format($todayPulse['expense'] ?? 0, 2) }}
                        </div>
                        <span class="badge bg-danger bg-opacity-10 text-danger rounded-pill px-2 py-0" style="font-size:0.68rem;">
                            Paid Vouchers ETB
                        </span>
                    </div>
                </div>

                {{-- 3. Today Workforce Attendance --}}
                <div class="col-6 col-md-4 col-xl">
                    <div class="p-3 rounded-3 bg-light border h-100">
                        <small class="text-muted text-uppercase fw-bold d-block" style="font-size:0.7rem;">Workforce Present</small>
                        <div class="fs-5 fw-bold text-primary my-1">
                            {{ $attendanceKpi['present'] ?? 0 }} <span class="fs-6 fw-normal text-muted">/ {{ $attendanceKpi['total_active'] ?? 0 }}</span>
                        </div>
                        <span class="badge bg-primary bg-opacity-10 text-primary rounded-pill px-2 py-0" style="font-size:0.68rem;">
                            {{ $attendanceKpi['rate'] ?? 0 }}% Attendance Rate
                        </span>
                    </div>
                </div>

                {{-- 4. Today Material Transfers (TR) --}}
                <div class="col-6 col-md-4 col-xl">
                    <div class="p-3 rounded-3 bg-light border h-100">
                        <small class="text-muted text-uppercase fw-bold d-block" style="font-size:0.7rem;">Store Transfers (TR)</small>
                        <div class="fs-5 fw-bold text-info my-1">
                            {{ $transfersData['kpi']['in_transit'] ?? 0 }} <span class="fs-6 fw-normal text-muted">In-Transit</span>
                        </div>
                        <span class="badge bg-info bg-opacity-10 text-info rounded-pill px-2 py-0" style="font-size:0.68rem;">
                            {{ $todayPulse['transfers'] ?? 0 }} Today • {{ $transfersData['kpi']['pending_dispatch'] ?? 0 }} Pending
                        </span>
                    </div>
                </div>

                {{-- 5. Urgent Decision Pipeline --}}
                <div class="col-12 col-md-4 col-xl">
                    <div class="p-3 rounded-3 bg-light border h-100">
                        <small class="text-muted text-uppercase fw-bold d-block" style="font-size:0.7rem;">Urgent Decision Queue</small>
                        <div class="fs-5 fw-bold {{ ($totalUrgentActions ?? 0) > 0 ? 'text-danger' : 'text-success' }} my-1">
                            {{ $totalUrgentActions ?? 0 }} <span class="fs-6 fw-normal text-muted">Pending</span>
                        </div>
                        <span class="badge {{ ($totalUrgentActions ?? 0) > 0 ? 'bg-danger' : 'bg-success' }} bg-opacity-10 {{ ($totalUrgentActions ?? 0) > 0 ? 'text-danger' : 'text-success' }} rounded-pill px-2 py-0" style="font-size:0.68rem;">
                            {{ ($totalUrgentActions ?? 0) > 0 ? 'Action Needed' : 'Queue Clear' }}
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ══════════════════════════════════════════════════════════════════════════
         2. URGENT EXECUTIVE DECISION & ESCALATIONS SUMMARY
         ══════════════════════════════════════════════════════════════════════════ --}}
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
                    <div class="col-6 col-md-4 col-xl-2">
                        <a href="{{ route('procurement.my-queue', ['status' => 'pending_gm']) }}" class="text-decoration-none d-block p-2 rounded-3 bg-white bg-opacity-10 hover-bg-opacity-20 transition text-white h-100">
                            <div class="fs-5 fw-bold text-danger">{{ $kpi['pending_gm_prs'] ?? 0 }}</div>
                            <small class="text-white-50 text-truncate d-block" style="font-size:0.75rem;">PR Decisions</small>
                            <span class="text-warning small" style="font-size:0.7rem;">{{ number_format($kpi['pending_gm_prs_amount'] ?? 0, 0) }} ETB</span>
                        </a>
                    </div>
                    <div class="col-6 col-md-4 col-xl-2">
                        <a href="{{ route('expenses.index', ['tab' => 'pending_gm']) }}" class="text-decoration-none d-block p-2 rounded-3 bg-white bg-opacity-10 hover-bg-opacity-20 transition text-white h-100">
                            <div class="fs-5 fw-bold text-warning">{{ $kpi['pending_gm_expenses'] ?? 0 }}</div>
                            <small class="text-white-50 text-truncate d-block" style="font-size:0.75rem;">Expense Requests</small>
                            <span class="text-warning small" style="font-size:0.7rem;">{{ number_format($kpi['pending_gm_expenses_amount'] ?? 0, 0) }} ETB</span>
                        </a>
                    </div>
                    <div class="col-6 col-md-4 col-xl-2">
                        <a href="{{ route('admin.role-assignment.index') }}" class="text-decoration-none d-block p-2 rounded-3 bg-white bg-opacity-10 hover-bg-opacity-20 transition text-white h-100">
                            <div class="fs-5 fw-bold text-info">{{ $kpi['unassigned_roles_count'] ?? 0 }}</div>
                            <small class="text-white-50 text-truncate d-block" style="font-size:0.75rem;">Unassigned Roles</small>
                            <span class="text-info small" style="font-size:0.7rem;">Needs System Role</span>
                        </a>
                    </div>
                    <div class="col-6 col-md-4 col-xl-2">
                        <a href="{{ route('employees.pending-approval') }}" class="text-decoration-none d-block p-2 rounded-3 bg-white bg-opacity-10 hover-bg-opacity-20 transition text-white h-100">
                            <div class="fs-5 fw-bold text-primary">{{ $kpi['pending_approvals'] ?? 0 }}</div>
                            <small class="text-white-50 text-truncate d-block" style="font-size:0.75rem;">Employee Reg.</small>
                            <span class="text-primary-emphasis small" style="font-size:0.7rem;">Awaiting Approval</span>
                        </a>
                    </div>
                    <div class="col-6 col-md-4 col-xl-2">
                        <a href="{{ route('gm.maintenance-approvals.index') }}" class="text-decoration-none d-block p-2 rounded-3 bg-white bg-opacity-10 hover-bg-opacity-20 transition text-white h-100">
                            <div class="fs-5 fw-bold text-warning">{{ $kpi['pending_maintenance'] ?? 0 }}</div>
                            <small class="text-white-50 text-truncate d-block" style="font-size:0.75rem;">Maintenance</small>
                            <span class="text-warning small" style="font-size:0.7rem;">Repairs &amp; Spares</span>
                        </a>
                    </div>
                    <div class="col-6 col-md-4 col-xl-2">
                        <a href="{{ route('admin.tickets.index') }}" class="text-decoration-none d-block p-2 rounded-3 bg-white bg-opacity-10 hover-bg-opacity-20 transition text-white h-100">
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

        {{-- Period Company Revenue / Income --}}
        <div class="col-xl-3 col-md-6">
            <div class="card border-0 shadow-sm h-100 position-relative overflow-hidden" style="border-left: 4px solid #3b82f6 !important; border-radius: 12px;">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <div class="text-xs fw-bold text-primary text-uppercase mb-1" style="font-size:0.75rem;letter-spacing:0.5px;">
                                <i class="fa-solid fa-arrow-trend-up me-1"></i>{{ $periodLabel ?? 'Monthly' }} Income
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
                        <span class="badge bg-primary bg-opacity-10 text-primary rounded-pill px-2 py-0" style="font-size:0.7rem;">Inflow Scope</span>
                        <a href="{{ route('income.index') }}" class="text-primary fw-semibold small text-decoration-none">Income Records <i class="fa-solid fa-arrow-right ms-1"></i></a>
                    </div>
                </div>
            </div>
        </div>

        {{-- Period Operational Expenses --}}
        <div class="col-xl-3 col-md-6">
            <div class="card border-0 shadow-sm h-100 position-relative overflow-hidden" style="border-left: 4px solid #ef4444 !important; border-radius: 12px;">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <div class="text-xs fw-bold text-danger text-uppercase mb-1" style="font-size:0.75rem;letter-spacing:0.5px;">
                                <i class="fa-solid fa-arrow-trend-down me-1"></i>{{ $periodLabel ?? 'Monthly' }} Expenses
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
                        <span class="badge bg-danger bg-opacity-10 text-danger rounded-pill px-2 py-0" style="font-size:0.7rem;">Outflow Scope</span>
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
                                {{ $netCash >= 0 ? 'Operating Surplus' : 'Operating Deficit' }} ({{ $periodLabel ?? 'Current Scope' }})
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
            <div class="card border-0 shadow-sm h-100 position-relative overflow-hidden" style="border-left: 4px solid #3b82f6 !important; border-radius: 12px;">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <div class="text-xs fw-bold text-muted text-uppercase mb-1" style="font-size:0.72rem;">Active Construction Projects</div>
                            <div class="h4 mb-0 fw-bold text-dark">{{ $kpi['active_projects'] ?? 0 }} <span class="fs-6 fw-normal text-muted">/ {{ $kpi['total_projects'] ?? 0 }} Total</span></div>
                            <small class="text-muted">Active Value: <strong>{{ number_format($kpi['total_contract_value'] ?? 0, 0) }} ETB</strong></small>
                        </div>
                        <div class="rounded-circle p-3 d-flex align-items-center justify-content-center bg-primary bg-opacity-10 text-primary" style="width:46px;height:46px;">
                            <i class="fa-solid fa-city fa-lg"></i>
                        </div>
                    </div>
                    <div class="mt-2 pt-2 border-top d-flex justify-content-between align-items-center">
                        <a href="{{ route('projects.index') }}" class="text-primary small text-decoration-none fw-semibold">Manage Projects <i class="fa-solid fa-arrow-right ms-1"></i></a>
                        <span class="badge bg-success bg-opacity-10 text-success rounded-pill px-2 py-0" style="font-size:0.68rem;">Live Sites</span>
                    </div>
                </div>
            </div>
        </div>

        {{-- Store Inventory Valuation & TR Status --}}
        <div class="col-xl-3 col-md-6">
            <div class="card border-0 shadow-sm h-100 position-relative overflow-hidden" style="border-left: 4px solid #06b6d4 !important; border-radius: 12px;">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <div class="text-xs fw-bold text-muted text-uppercase mb-1" style="font-size:0.72rem;">Stores Inventory Value</div>
                            <div class="h4 mb-0 fw-bold text-dark">{{ number_format($kpi['inventory_value'] ?? 0, 2) }} ETB</div>
                            <small class="text-muted">
                                In-Transit: <strong class="text-info">{{ $transfersData['kpi']['in_transit'] ?? 0 }} TRs moving</strong>
                            </small>
                        </div>
                        <div class="rounded-circle p-3 d-flex align-items-center justify-content-center bg-info bg-opacity-10 text-info" style="width:46px;height:46px;">
                            <i class="fa-solid fa-boxes-stacked fa-lg"></i>
                        </div>
                    </div>
                    <div class="mt-2 pt-2 border-top d-flex justify-content-between align-items-center">
                        <a href="{{ route('store-manager.inventory.all') }}" class="text-info small text-decoration-none fw-semibold">All Stores <i class="fa-solid fa-arrow-right ms-1"></i></a>
                        <a href="{{ route('transfers.index') }}" class="text-primary small text-decoration-none fw-semibold">Transfers TR <i class="fa-solid fa-truck-moving ms-1"></i></a>
                    </div>
                </div>
            </div>
        </div>

        {{-- Total Active Workforce & Live Attendance --}}
        <div class="col-xl-3 col-md-6">
            <div class="card border-0 shadow-sm h-100 position-relative overflow-hidden" style="border-left: 4px solid #10b981 !important; border-radius: 12px;">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <div class="text-xs fw-bold text-muted text-uppercase mb-1" style="font-size:0.72rem;">Active Workforce &amp; Attendance</div>
                            <div class="h4 mb-0 fw-bold text-dark">{{ $kpi['total_employees'] ?? 0 }} <span class="fs-6 fw-normal text-muted">Active Staff</span></div>
                            <small class="text-muted">
                                Today: <strong class="text-success">{{ $attendanceKpi['present'] ?? 0 }} Present ({{ $attendanceKpi['rate'] ?? 0 }}%)</strong>
                            </small>
                        </div>
                        <div class="rounded-circle p-3 d-flex align-items-center justify-content-center bg-success bg-opacity-10 text-success" style="width:46px;height:46px;">
                            <i class="fa-solid fa-users-gear fa-lg"></i>
                        </div>
                    </div>
                    <div class="mt-2 pt-2 border-top d-flex justify-content-between align-items-center">
                        <a href="{{ route('employees.index') }}" class="text-success small text-decoration-none fw-semibold">Directory <i class="fa-solid fa-arrow-right ms-1"></i></a>
                        <a href="{{ route('attendance.index') }}" class="text-primary small text-decoration-none fw-semibold">Attendance <i class="fa-solid fa-clock ms-1"></i></a>
                    </div>
                </div>
            </div>
        </div>

        {{-- System Users & Security --}}
        <div class="col-xl-3 col-md-6">
            <div class="card border-0 shadow-sm h-100 position-relative overflow-hidden" style="border-left: 4px solid #64748b !important; border-radius: 12px;">
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
    <script type="application/json" id="admin-charts-json">
    {
        "cashFlowLabels": @json($monthlyAnalytics['labels'] ?? []),
        "cashFlowIncomes": @json($monthlyAnalytics['incomes'] ?? []),
        "cashFlowExpenses": @json($monthlyAnalytics['expenses'] ?? []),
        "expenseCatLabels": @json(isset($expenseCategories) ? $expenseCategories->pluck('category') : []),
        "expenseCatTotals": @json(isset($expenseCategories) ? $expenseCategories->pluck('total') : [])
    }
    </script>
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
                    @if(isset($expenseCategories) && $expenseCategories->count() > 0)
                        <div style="height: 250px; position: relative;">
                            <canvas id="expenseCategoryChart"></canvas>
                        </div>
                        <div class="text-center mt-2 small text-muted">
                            Real breakdown from recorded company expense vouchers
                        </div>
                    @else
                        <div class="text-center py-5 text-muted">
                            <i class="fa-solid fa-chart-pie fs-1 mb-2 opacity-25"></i>
                            <p class="mb-0 small">No expense categories recorded in this scope.</p>
                        </div>
                    @endif
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
            <div class="table-responsive wc-table-mobile-cards">
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
            <div class="table-responsive wc-table-mobile-cards">
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
                                <td data-label="Utilization">
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
         8. INTER-STORE MATERIAL TRANSFERS (TR) COMMAND CENTER
         ══════════════════════════════════════════════════════════════════════════ --}}
    <div class="card border-0 shadow-sm rounded-4 mb-4 bg-white overflow-hidden" style="border-top: 4px solid #0284c7 !important;">
        <div class="card-header bg-white py-3 px-3 px-md-4 border-bottom d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div class="d-flex align-items-center gap-3">
                <div class="rounded-3 p-2 bg-info bg-opacity-10 text-info fs-5 d-flex align-items-center justify-content-center" style="width:42px;height:42px;">
                    <i class="fa-solid fa-truck-ramp-box"></i>
                </div>
                <div>
                    <div class="d-flex align-items-center gap-2 flex-wrap">
                        <h6 class="m-0 fw-bold text-dark fs-6">Inter-Store Material Transfers (TR) Command Center</h6>
                        <span class="badge bg-info-subtle text-info border border-info-subtle rounded-pill px-2 py-0" style="font-size:0.7rem;">
                            Live Logistics &amp; Inventory Balance Sync
                        </span>
                    </div>
                    <small class="text-muted">Origin stock deduction, transit driver tracking, and destination store inventory synchronization</small>
                </div>
            </div>
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <form method="POST" action="{{ route('transfers.sync-all-inventory') }}" class="d-inline" onsubmit="return confirm('Check all store transfers and automatically synchronize inventory balances across origin and destination stores?')">
                    @csrf
                    <button type="submit" class="btn btn-sm btn-outline-success rounded-pill px-3 shadow-xs fw-semibold">
                        <i class="fa-solid fa-arrows-rotate me-1 text-success"></i>Check &amp; Auto-Adjust All Inventory
                    </button>
                </form>
                <a href="{{ route('transfers.create') }}" class="btn btn-sm btn-primary rounded-pill px-3 shadow-xs fw-semibold">
                    <i class="fa-solid fa-plus me-1"></i>New Transfer TR
                </a>
                <a href="{{ route('transfers.index') }}" class="btn btn-sm btn-outline-secondary rounded-pill px-3 shadow-xs">
                    <i class="fa-solid fa-list me-1"></i>All Transfers
                </a>
            </div>
        </div>

        <div class="card-body p-3 p-md-4">
            {{-- Transfer KPI Row --}}
            <div class="row g-2 text-center mb-4">
                <div class="col-6 col-md-3">
                    <div class="p-3 rounded-3 bg-light border h-100">
                        <div class="text-muted small text-uppercase fw-bold" style="font-size:0.7rem;">In-Transit (On Road)</div>
                        <div class="fs-4 fw-bold text-info my-1 font-monospace">{{ $transfersData['kpi']['in_transit'] ?? 0 }}</div>
                        <span class="badge bg-info bg-opacity-10 text-info rounded-pill px-2 py-0" style="font-size:0.68rem;">
                            Assigned Drivers &amp; Trucks
                        </span>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="p-3 rounded-3 bg-light border h-100">
                        <div class="text-muted small text-uppercase fw-bold" style="font-size:0.7rem;">Ready to Dispatch</div>
                        <div class="fs-4 fw-bold text-warning my-1 font-monospace">{{ $transfersData['kpi']['pending_dispatch'] ?? 0 }}</div>
                        <span class="badge bg-warning bg-opacity-10 text-dark rounded-pill px-2 py-0" style="font-size:0.68rem;">
                            Approved at Warehouse
                        </span>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="p-3 rounded-3 bg-light border h-100">
                        <div class="text-muted small text-uppercase fw-bold" style="font-size:0.7rem;">Completed / Received</div>
                        <div class="fs-4 fw-bold text-success my-1 font-monospace">{{ $transfersData['kpi']['completed'] ?? 0 }}</div>
                        <span class="badge bg-success bg-opacity-10 text-success rounded-pill px-2 py-0" style="font-size:0.68rem;">
                            Delivered &amp; Inventory Synced
                        </span>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="p-3 rounded-3 bg-light border h-100">
                        <div class="text-muted small text-uppercase fw-bold" style="font-size:0.7rem;">Total Scope Movements</div>
                        <div class="fs-4 fw-bold text-dark my-1 font-monospace">{{ $transfersData['kpi']['total_period'] ?? 0 }}</div>
                        <span class="badge bg-secondary bg-opacity-10 text-secondary rounded-pill px-2 py-0" style="font-size:0.68rem;">
                            {{ $periodLabel ?? 'Current Scope' }}
                        </span>
                    </div>
                </div>
            </div>

            {{-- Recent Transfers Table --}}
            @if(isset($transfersData['recent']) && $transfersData['recent']->count() > 0)
                <div class="table-responsive wc-table-mobile-cards">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light text-uppercase text-muted" style="font-size:0.72rem;">
                            <tr>
                                <th class="ps-3">Transfer #</th>
                                <th>Route (Origin &rarr; Destination)</th>
                                <th>Driver &amp; Vehicle</th>
                                <th>Materials</th>
                                <th>Origin Deduct</th>
                                <th>Dest. Receive</th>
                                <th>Status</th>
                                <th class="text-end pe-3">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($transfersData['recent'] as $t)
                                @php
                                    $isOriginDeducted = $t->isDeductedFromOrigin();
                                    $isDestAdded = $t->isAddedToDestination();
                                    $itemCount = $t->items->count();
                                    $firstItem = $t->items->first();
                                @endphp
                                <tr>
                                    <td class="ps-3">
                                        <a href="{{ route('transfers.show', $t->id) }}" class="fw-bold font-monospace text-primary text-decoration-none">
                                            {{ $t->transfer_no ?? ('TR-#' . $t->id) }}
                                        </a>
                                        @if($t->physical_slip_no)
                                            <small class="d-block text-muted font-monospace" style="font-size:0.7rem;">Slip: {{ $t->physical_slip_no }}</small>
                                        @endif
                                    </td>
                                    <td>
                                        <div class="fw-semibold text-dark text-sm">
                                            <span class="text-secondary">{{ $t->fromStore->name ?? 'Origin Store' }}</span>
                                            <i class="fa-solid fa-arrow-right-long text-muted mx-1"></i>
                                            <span class="text-primary">{{ $t->toStore->name ?? 'Destination Store' }}</span>
                                        </div>
                                        <small class="text-muted" style="font-size:0.72rem;">{{ $t->created_at->diffForHumans() }}</small>
                                    </td>
                                    <td>
                                        @if($t->driver)
                                            <div class="fw-semibold text-dark text-sm">
                                                <i class="fa-solid fa-id-badge text-muted me-1"></i>{{ $t->driver->full_name ?? ($t->driver->name ?? 'Assigned') }}
                                            </div>
                                        @else
                                            <span class="text-muted small">No Driver Assigned</span>
                                        @endif
                                        @if($t->vehicle_plate_no)
                                            <span class="badge bg-light text-dark border font-monospace" style="font-size:0.68rem;">
                                                <i class="fa-solid fa-truck me-1"></i>{{ $t->vehicle_plate_no }}
                                            </span>
                                        @endif
                                    </td>
                                    <td>
                                        <span class="badge bg-light text-dark border fw-semibold">
                                            {{ $itemCount }} {{ Str::plural('item', $itemCount) }}
                                        </span>
                                        @if($firstItem && $firstItem->product)
                                            <small class="d-block text-muted text-truncate" style="max-width:180px; font-size:0.72rem;">
                                                {{ $firstItem->product->name }} ({{ number_format($firstItem->quantity, 1) }})
                                            </small>
                                        @endif
                                    </td>
                                    <td>
                                        @if($isOriginDeducted)
                                            <span class="badge bg-success bg-opacity-10 text-success rounded-pill px-2 py-1" style="font-size:0.7rem;">
                                                <i class="fa-solid fa-check me-1"></i>Deducted
                                            </span>
                                        @else
                                            <span class="badge bg-warning bg-opacity-10 text-warning rounded-pill px-2 py-1" style="font-size:0.7rem;">
                                                <i class="fa-solid fa-clock me-1"></i>Pending Out
                                            </span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($isDestAdded)
                                            <span class="badge bg-success bg-opacity-10 text-success rounded-pill px-2 py-1" style="font-size:0.7rem;">
                                                <i class="fa-solid fa-check me-1"></i>Stock Added
                                            </span>
                                        @elseif($t->status === 'in_transit')
                                            <span class="badge bg-info bg-opacity-10 text-info rounded-pill px-2 py-1" style="font-size:0.7rem;">
                                                <i class="fa-solid fa-truck-fast me-1"></i>In Transit
                                            </span>
                                        @else
                                            <span class="badge bg-secondary bg-opacity-10 text-secondary rounded-pill px-2 py-1" style="font-size:0.7rem;">
                                                Pending In
                                            </span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($t->status === 'completed')
                                            <span class="badge bg-success rounded-pill px-2 py-1" style="font-size:0.7rem;">Completed</span>
                                        @elseif($t->status === 'in_transit')
                                            <span class="badge bg-info text-white rounded-pill px-2 py-1 animate-pulse" style="font-size:0.7rem;">In Transit</span>
                                        @elseif($t->status === 'approved')
                                            <span class="badge bg-warning text-dark rounded-pill px-2 py-1" style="font-size:0.7rem;">Approved</span>
                                        @elseif($t->status === 'pending_approval')
                                            <span class="badge bg-warning bg-opacity-25 text-warning-emphasis rounded-pill px-2 py-1" style="font-size:0.7rem;">Pending</span>
                                        @else
                                            <span class="badge bg-secondary rounded-pill px-2 py-1" style="font-size:0.7rem;">{{ ucfirst($t->status) }}</span>
                                        @endif
                                    </td>
                                    <td class="text-end pe-3">
                                        <div class="btn-group btn-group-sm">
                                            <a href="{{ route('transfers.show', $t->id) }}" class="btn btn-outline-primary py-0 px-2" style="font-size:0.75rem;">
                                                View
                                            </a>
                                            <form method="POST" action="{{ route('transfers.sync-inventory', $t->id) }}" class="d-inline">
                                                @csrf
                                                <button type="submit" class="btn btn-outline-success py-0 px-2" style="font-size:0.75rem;" title="Sync transfer inventory">
                                                    <i class="fa-solid fa-arrows-rotate"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="text-center py-4 text-muted">
                    <i class="fa-solid fa-truck-ramp-box fs-3 mb-2 opacity-50"></i>
                    <p class="mb-0">No active inter-store transfers in this scope.</p>
                </div>
            @endif
        </div>
    </div>

    {{-- ══════════════════════════════════════════════════════════════════════════
         9. WORKFORCE ATTENDANCE TODAY & SITE DEPLOYMENT COMMAND
         ══════════════════════════════════════════════════════════════════════════ --}}
    <div class="card border-0 shadow-sm rounded-4 mb-4 bg-white overflow-hidden" style="border-top: 4px solid #10b981 !important;">
        <div class="card-header bg-white py-3 px-3 px-md-4 border-bottom d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div class="d-flex align-items-center gap-3">
                <div class="rounded-3 p-2 bg-success bg-opacity-10 text-success fs-5 d-flex align-items-center justify-content-center" style="width:42px;height:42px;">
                    <i class="fa-solid fa-user-clock"></i>
                </div>
                <div>
                    <div class="d-flex align-items-center gap-2 flex-wrap">
                        <h6 class="m-0 fw-bold text-dark fs-6">Today's Workforce Attendance &amp; Site Deployments</h6>
                        <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-2 py-0" style="font-size:0.7rem;">
                            Live Biometric &amp; Site Dispatch
                        </span>
                    </div>
                    <small class="text-muted">Real-time attendance check-ins, punctuality tracking, and project field deployments for {{ now()->format('l, F j, Y') }}</small>
                </div>
            </div>
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <a href="{{ route('attendance.index') }}" class="btn btn-sm btn-primary rounded-pill px-3 shadow-xs fw-semibold">
                    <i class="fa-solid fa-clipboard-user me-1"></i>Daily Attendance Sheet
                </a>
                <a href="{{ route('attendance.site-deployments') }}" class="btn btn-sm btn-outline-info rounded-pill px-3 shadow-xs">
                    <i class="fa-solid fa-helmet-safety me-1"></i>Site Deployments
                </a>
                <a href="{{ route('admin.attendance.device-logs') }}" class="btn btn-sm btn-outline-secondary rounded-pill px-3 shadow-xs">
                    <i class="fa-solid fa-fingerprint me-1"></i>Biometric Sync
                </a>
            </div>
        </div>

        <div class="card-body p-3 p-md-4">
            <div class="row g-3 align-items-center">
                {{-- Left: Big attendance percentage gauge --}}
                <div class="col-lg-4 text-center border-end">
                    <div class="d-inline-flex flex-column align-items-center justify-content-center p-3">
                        <div class="position-relative d-inline-block">
                            <div class="display-5 fw-bold text-success font-monospace mb-0">
                                {{ $attendanceKpi['rate'] ?? 0 }}<span class="fs-4">%</span>
                            </div>
                        </div>
                        <div class="fw-semibold text-dark mt-1">Today's Attendance Rate</div>
                        <small class="text-muted">
                            {{ $attendanceKpi['present'] ?? 0 }} of {{ $attendanceKpi['total_active'] ?? 0 }} active personnel present
                        </small>
                        <div class="progress w-100 mt-2" style="height: 8px;">
                            <div class="progress-bar bg-success" role="progressbar" style="width: {{ $attendanceKpi['rate'] ?? 0 }}%;"></div>
                        </div>
                    </div>
                </div>

                {{-- Right: Breakdown chips --}}
                <div class="col-lg-8">
                    <div class="row g-2 text-center">
                        <div class="col-6 col-sm-3">
                            <div class="p-2 border rounded-3 bg-light">
                                <div class="fs-5 fw-bold text-success font-monospace">{{ $attendanceKpi['present'] ?? 0 }}</div>
                                <small class="text-muted text-uppercase fw-semibold" style="font-size:0.7rem;">Present</small>
                                <span class="d-block text-success small" style="font-size:0.68rem;">Office &amp; Site</span>
                            </div>
                        </div>
                        <div class="col-6 col-sm-3">
                            <div class="p-2 border rounded-3 bg-light">
                                <div class="fs-5 fw-bold text-primary font-monospace">{{ $attendanceKpi['site'] ?? 0 }}</div>
                                <small class="text-muted text-uppercase fw-semibold" style="font-size:0.7rem;">On Site (S)</small>
                                <span class="d-block text-primary small" style="font-size:0.68rem;">Project Field</span>
                            </div>
                        </div>
                        <div class="col-6 col-sm-3">
                            <div class="p-2 border rounded-3 bg-light">
                                <div class="fs-5 fw-bold text-warning font-monospace">{{ $attendanceKpi['leave'] ?? 0 }}</div>
                                <small class="text-muted text-uppercase fw-semibold" style="font-size:0.7rem;">On Leave</small>
                                <span class="d-block text-warning small" style="font-size:0.68rem;">Approved Leave</span>
                            </div>
                        </div>
                        <div class="col-6 col-sm-3">
                            <div class="p-2 border rounded-3 bg-light">
                                <div class="fs-5 fw-bold text-danger font-monospace">{{ $attendanceKpi['absent'] ?? 0 }}</div>
                                <small class="text-muted text-uppercase fw-semibold" style="font-size:0.7rem;">Absent</small>
                                <span class="d-block text-danger small" style="font-size:0.68rem;">Unaccounted</span>
                            </div>
                        </div>
                    </div>

                    <div class="mt-3 p-2 rounded-3 bg-light d-flex justify-content-between align-items-center flex-wrap gap-2 text-xs">
                        <div class="d-flex align-items-center gap-2">
                            <i class="fa-solid fa-clock-rotate-left text-warning"></i>
                            <span class="text-muted">Punctuality:</span>
                            <span class="fw-semibold text-dark">{{ $attendanceKpi['late'] ?? 0 }} employees flagged late (after 08:40 AM)</span>
                        </div>
                        <a href="{{ route('reports.attendance') }}" class="text-primary fw-semibold text-decoration-none">
                            Full HR Attendance Report <i class="fa-solid fa-arrow-right ms-1"></i>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ══════════════════════════════════════════════════════════════════════════
         10. EXECUTIVE DECISION MATRIX & ACTION HUB (TABBED INTERFACE)
         ══════════════════════════════════════════════════════════════════════════ --}}
    <div id="executive-approval-hub" class="card border-0 shadow-sm rounded-3 mb-4">
        <div class="card-header bg-transparent py-3 border-0">
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-2">
                <h6 class="m-0 fw-bold text-dark">
                    <i class="fa-solid fa-gavel text-danger me-2"></i>Executive Decision Hub &amp; Operations Queues
                </h6>
                <small class="text-muted">High-priority items requiring Global Admin / GM decisions or escalation</small>
            </div>
            <ul class="nav nav-tabs card-header-tabs flex-nowrap overflow-x-auto text-nowrap pb-1 border-bottom-0" id="decisionTabs" role="tablist">
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
                    <button class="nav-link fw-semibold" id="transfers-tab" data-bs-toggle="tab" data-bs-target="#tab-transfers" type="button" role="tab">
                        <i class="fa-solid fa-truck-ramp-box me-1 text-info"></i>Store Transfers
                        @if(($transfersData['kpi']['in_transit'] ?? 0) > 0 || ($transfersData['kpi']['pending_dispatch'] ?? 0) > 0)
                            <span class="badge bg-info text-white rounded-pill ms-1">{{ ($transfersData['kpi']['in_transit'] ?? 0) + ($transfersData['kpi']['pending_dispatch'] ?? 0) }}</span>
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
                        <div class="table-responsive wc-table-mobile-cards">
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
                        <div class="table-responsive wc-table-mobile-cards">
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
                        <div class="table-responsive wc-table-mobile-cards">
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
                                            <td class="fw-semibold text-dark">{{ $emp->full_name ?: ($emp->name ?: trim(($emp->first_name ?? '') . ' ' . ($emp->last_name ?? ''))) }}</td>
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
                        <div class="table-responsive wc-table-mobile-cards">
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

                {{-- TAB 6: STORE TRANSFERS (TR) IN-TRANSIT & DISPATCH --}}
                <div class="tab-pane fade p-3" id="tab-transfers" role="tabpanel">
                    @if(isset($transfersData['recent']) && $transfersData['recent']->count() > 0)
                        <div class="table-responsive wc-table-mobile-cards">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light text-uppercase text-muted" style="font-size:0.75rem;">
                                    <tr>
                                        <th>Transfer #</th>
                                        <th>Origin &rarr; Destination</th>
                                        <th>Driver / Truck</th>
                                        <th>Items</th>
                                        <th>Origin Deduct</th>
                                        <th>Dest. Receive</th>
                                        <th>Status</th>
                                        <th class="text-end">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($transfersData['recent'] as $t)
                                        <tr>
                                            <td class="fw-bold font-monospace text-primary">
                                                <a href="{{ route('transfers.show', $t->id) }}" class="text-decoration-none">
                                                    {{ $t->transfer_no ?? ('TR-#' . $t->id) }}
                                                </a>
                                            </td>
                                            <td>
                                                <span class="text-secondary fw-semibold">{{ $t->fromStore->name ?? 'Origin' }}</span>
                                                <i class="fa-solid fa-arrow-right text-muted mx-1"></i>
                                                <span class="text-primary fw-semibold">{{ $t->toStore->name ?? 'Destination' }}</span>
                                            </td>
                                            <td>
                                                {{ $t->driver->full_name ?? ($t->driver->name ?? 'Unassigned') }}
                                                @if($t->vehicle_plate_no)
                                                    <span class="badge bg-light text-dark border font-monospace ms-1">{{ $t->vehicle_plate_no }}</span>
                                                @endif
                                            </td>
                                            <td><span class="badge bg-light text-dark border">{{ $t->items->count() }} items</span></td>
                                            <td>
                                                @if($t->isDeductedFromOrigin())
                                                    <span class="badge bg-success bg-opacity-10 text-success"><i class="fa-solid fa-check me-1"></i>Deducted</span>
                                                @else
                                                    <span class="badge bg-warning bg-opacity-10 text-warning"><i class="fa-solid fa-clock me-1"></i>Pending Out</span>
                                                @endif
                                            </td>
                                            <td>
                                                @if($t->isAddedToDestination())
                                                    <span class="badge bg-success bg-opacity-10 text-success"><i class="fa-solid fa-check me-1"></i>Received</span>
                                                @elseif($t->status === 'in_transit')
                                                    <span class="badge bg-info bg-opacity-10 text-info"><i class="fa-solid fa-truck me-1"></i>In Transit</span>
                                                @else
                                                    <span class="badge bg-secondary bg-opacity-10 text-secondary">Pending In</span>
                                                @endif
                                            </td>
                                            <td>
                                                <span class="badge bg-{{ $t->status === 'in_transit' ? 'info' : ($t->status === 'completed' ? 'success' : 'warning') }}">
                                                    {{ ucfirst($t->status) }}
                                                </span>
                                            </td>
                                            <td class="text-end">
                                                <a href="{{ route('transfers.show', $t->id) }}" class="btn btn-sm btn-outline-primary py-1 px-3 rounded-pill text-xs">
                                                    View Details
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
                            <p class="mb-0 fw-semibold">No active store transfers currently in transit.</p>
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
                        <div class="table-responsive wc-table-mobile-cards">
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
        <div class="card border-0 shadow-sm rounded-4 mb-4" style="border-left: 5px solid #f59e0b !important;">
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
                <div class="table-responsive wc-table-mobile-cards">
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
        var chartDataEl = document.getElementById("admin-charts-json");
        var chartData = {};
        if (chartDataEl) {
            try {
                chartData = JSON.parse(chartDataEl.textContent || '{}');
            } catch(e) {
                console.error("Failed to parse chart json:", e);
            }
        }

        var payload = document.getElementById("admin-charts-payload");
        var parseFallback = function(key) {
            if (!payload) return [];
            try { return JSON.parse(payload.dataset[key] || '[]'); } catch(e) { return []; }
        };

        var cashFlowLabels = chartData.cashFlowLabels || parseFallback('cashflowLabels');
        var cashFlowIncomes = chartData.cashFlowIncomes || parseFallback('cashflowIncomes');
        var cashFlowExpenses = chartData.cashFlowExpenses || parseFallback('cashflowExpenses');

        var expenseCatLabels = chartData.expenseCatLabels || parseFallback('expenseCatLabels');
        var expenseCatTotals = chartData.expenseCatTotals || parseFallback('expenseCatTotals');

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
