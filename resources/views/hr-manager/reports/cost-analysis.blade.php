@extends('layouts.app')
@section('title', 'HR Payroll Cost Analysis — Wechecha ERP')

@section('content')
<div class="container-fluid px-0">
    {{-- Page Header --}}
    <x-page-header
        title="HR Payroll Cost & Wage Analytics"
        subtitle="Breakdown of departmental payroll liabilities, gross wage distribution, statutory tax withholdings, and net staff disbursements."
        :breadcrumbs="[
            ['label' => 'Dashboard', 'url' => route('dashboard')],
            ['label' => 'HR Management', 'url' => route('dashboard.hr')],
            ['label' => 'HR Reports', 'url' => route('reports.attendance')],
            ['label' => 'Payroll Cost Analysis']
        ]">
        <x-slot name="actions">
            <a href="{{ route('reports.attendance') }}" class="btn btn-outline-secondary btn-sm rounded-pill px-3 py-2 shadow-xs fw-semibold">
                <i class="fa-solid fa-arrow-left me-1.5"></i>All HR Reports
            </a>
            <a href="{{ route('finance.payroll.index') }}" class="btn btn-primary btn-sm rounded-pill px-3 py-2 shadow-xs fw-semibold">
                <i class="fa-solid fa-money-bill-wave me-1.5"></i>Payroll Ledger
            </a>
        </x-slot>
    </x-page-header>

    {{-- Unified HR Reports Navigation Pill Bar --}}
    <div class="card border-0 shadow-sm rounded-4 mb-4">
        <div class="card-body p-2">
            <div class="nav nav-pills flex-column flex-sm-row gap-2">
                <a href="{{ route('reports.attendance') }}" class="nav-link rounded-pill px-3 py-2 fw-semibold text-secondary text-center">
                    <i class="fa-solid fa-calendar-check me-1.5"></i>Attendance Report
                </a>
                <a href="{{ route('reports.turnover') }}" class="nav-link rounded-pill px-3 py-2 fw-semibold text-secondary text-center">
                    <i class="fa-solid fa-user-slash me-1.5"></i>Turnover &amp; Retention
                </a>
                <a href="{{ route('reports.cost-analysis') }}" class="nav-link active rounded-pill px-3 py-2 fw-semibold text-center">
                    <i class="fa-solid fa-money-bill-trend-up me-1.5"></i>Payroll Cost Analysis
                </a>
                <a href="{{ route('reports.leave-analysis') }}" class="nav-link rounded-pill px-3 py-2 fw-semibold text-secondary text-center">
                    <i class="fa-solid fa-plane-departure me-1.5"></i>Leave Analysis
                </a>
                <a href="{{ route('reports.employee-cost') }}" class="nav-link rounded-pill px-3 py-2 fw-semibold text-secondary text-center">
                    <i class="fa-solid fa-id-card-clip me-1.5"></i>Employee Cost Breakdown
                </a>
            </div>
        </div>
    </div>

    {{-- Executive Summary KPI Cards --}}
    <div class="row g-3 mb-4">
        <div class="col-6 col-lg-3">
            <x-kpi-card
                title="Total Gross Payroll"
                :value="'ETB ' . number_format($stats['total_gross'], 2)"
                :changeLabel="'For ' . Carbon\Carbon::create($year, $month)->format('M Y')"
                icon="fa-solid fa-sack-dollar"
                color="primary" />
        </div>
        <div class="col-6 col-lg-3">
            <x-kpi-card
                title="Total Net Disbursed"
                :value="'ETB ' . number_format($stats['total_net'], 2)"
                changeLabel="Net wages to employees"
                icon="fa-solid fa-hand-holding-dollar"
                color="success" />
        </div>
        <div class="col-6 col-lg-3">
            <x-kpi-card
                title="Taxes & Deductions"
                :value="'ETB ' . number_format($stats['total_tax'] + $stats['total_deductions'], 2)"
                changeLabel="Income tax & pension withholdings"
                icon="fa-solid fa-receipt"
                color="warning" />
        </div>
        <div class="col-6 col-lg-3">
            <x-kpi-card
                title="Average Cost / Head"
                :value="'ETB ' . number_format($stats['avg_cost'], 2)"
                :changeLabel="$stats['total_employees'] . ' active staff processed'"
                icon="fa-solid fa-user-tag"
                color="info" />
        </div>
    </div>

    {{-- Department Cost Breakdown Table --}}
    <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
        <div class="card-header bg-white py-3 px-4 border-bottom d-flex align-items-center justify-content-between">
            <div class="d-flex align-items-center gap-2">
                <span class="badge bg-primary bg-opacity-10 text-primary rounded-pill px-2.5 py-1 fw-bold small">
                    <i class="fa-solid fa-building me-1"></i>Departments
                </span>
                <h6 class="fw-bold mb-0 text-dark">Department Payroll Summary</h6>
            </div>
            <span class="text-muted small">{{ count($departmentCosts) }} Departments</span>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 wc-table-mobile-cards">
                <thead class="table-light">
                    <tr>
                        <th class="ps-4 text-secondary text-uppercase small fw-bold">Department</th>
                        <th class="text-center text-secondary text-uppercase small fw-bold">Staff Count</th>
                        <th class="text-end text-secondary text-uppercase small fw-bold">Total Gross (ETB)</th>
                        <th class="text-end text-secondary text-uppercase small fw-bold">Deductions</th>
                        <th class="text-end text-secondary text-uppercase small fw-bold">Income Tax</th>
                        <th class="pe-4 text-end text-secondary text-uppercase small fw-bold">Total Net (ETB)</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($departmentCosts as $dept)
                        <tr>
                            <td class="ps-4 fw-bold text-dark" data-label="Department">{{ $dept->name }}</td>
                            <td class="text-center" data-label="Staff Count">
                                <span class="badge bg-secondary bg-opacity-10 text-secondary rounded-pill px-3 py-1 font-tabular">
                                    {{ $dept->employee_count }}
                                </span>
                            </td>
                            <td class="text-end font-tabular fw-semibold text-primary" data-label="Gross">{{ number_format($dept->total_gross, 2) }}</td>
                            <td class="text-end font-tabular text-danger" data-label="Deductions">-{{ number_format($dept->total_deductions, 2) }}</td>
                            <td class="text-end font-tabular text-warning fw-semibold" data-label="Tax">{{ number_format($dept->total_tax, 2) }}</td>
                            <td class="pe-4 text-end font-tabular fw-bold text-success fs-6" data-label="Net Pay">
                                {{ number_format($dept->total_net, 2) }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center py-5 text-muted">
                                <x-empty-state
                                    title="No Department Payroll Records"
                                    description="No payroll ledger entries found for the selected month."
                                    icon="fa-solid fa-money-bill-wave" />
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
