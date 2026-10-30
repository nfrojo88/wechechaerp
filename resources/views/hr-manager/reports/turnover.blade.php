@extends('layouts.app')
@section('title', 'HR Turnover & Retention Report — Wechecha ERP')

@section('content')
<div class="container-fluid px-0">
    {{-- Page Header --}}
    <x-page-header
        title="HR Turnover &amp; Retention Analytics"
        subtitle="Analysis of staff recruitment joinings, resignations, contract expirations, and departmental retention trends."
        :breadcrumbs="[
            ['label' => 'Dashboard', 'url' => route('dashboard')],
            ['label' => 'HR Management', 'url' => route('dashboard.hr')],
            ['label' => 'HR Reports', 'url' => route('reports.attendance')],
            ['label' => 'Turnover & Retention']
        ]">
        <x-slot:actions>
            <a href="{{ route('reports.attendance') }}" class="btn btn-outline-secondary btn-sm rounded-pill px-3 py-2 shadow-xs fw-semibold">
                <i class="fa-solid fa-arrow-left me-1.5"></i>All HR Reports
            </a>
            <a href="{{ route('employees.index') }}" class="btn btn-primary btn-sm rounded-pill px-3 py-2 shadow-xs fw-semibold">
                <i class="fa-solid fa-users me-1.5"></i>Employee Registry
            </a>
        </x-slot:actions>
    </x-page-header>

    {{-- Unified HR Reports Navigation Pill Bar --}}
    <div class="card border-0 shadow-sm rounded-4 mb-4">
        <div class="card-body p-2">
            <div class="nav nav-pills flex-column flex-sm-row gap-2">
                <a href="{{ route('reports.attendance') }}" class="nav-link rounded-pill px-3 py-2 fw-semibold text-secondary text-center">
                    <i class="fa-solid fa-calendar-check me-1.5"></i>Attendance Report
                </a>
                <a href="{{ route('reports.turnover') }}" class="nav-link active rounded-pill px-3 py-2 fw-semibold text-center">
                    <i class="fa-solid fa-user-slash me-1.5"></i>Turnover &amp; Retention
                </a>
                <a href="{{ route('reports.cost-analysis') }}" class="nav-link rounded-pill px-3 py-2 fw-semibold text-secondary text-center">
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

    {{-- KPI Summary Cards --}}
    <div class="row g-3 mb-4">
        <div class="col-6 col-lg-3">
            <x-kpi-card
                title="New Joinings"
                :value="'+' . number_format($stats['total_joinings'])"
                changeLabel="New personnel onboarded"
                icon="fa-solid fa-user-plus"
                color="success" />
        </div>
        <div class="col-6 col-lg-3">
            <x-kpi-card
                title="Separations"
                :value="'-' . number_format($stats['total_separations'])"
                changeLabel="Resignations & terminations"
                icon="fa-solid fa-user-minus"
                color="danger" />
        </div>
        <div class="col-6 col-lg-3">
            <x-kpi-card
                title="Turnover Rate"
                :value="number_format($stats['turnover_rate'] ?? 0, 1) . '%'"
                changeLabel="Annualized employee turnover"
                icon="fa-solid fa-percent"
                color="warning" />
        </div>
        <div class="col-6 col-lg-3">
            <x-kpi-card
                title="Net Headcount Change"
                :value="($stats['net_change'] >= 0 ? '+' : '') . $stats['net_change']"
                changeLabel="Joinings minus separations"
                icon="fa-solid fa-chart-line"
                :color="$stats['net_change'] >= 0 ? 'primary' : 'danger'" />
        </div>
    </div>

    {{-- Department Turnover Table --}}
    <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
        <div class="card-header bg-white py-3 px-4 border-bottom d-flex align-items-center justify-content-between">
            <div class="d-flex align-items-center gap-2">
                <span class="badge bg-primary bg-opacity-10 text-primary rounded-pill px-2.5 py-1 fw-bold small">
                    <i class="fa-solid fa-building-user me-1"></i>Department Stats
                </span>
                <h6 class="fw-bold mb-0 text-dark">Department Turnover &amp; Net Change</h6>
            </div>
            <span class="text-muted small">{{ count($departmentTurnover) }} Departments Tracked</span>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 wc-table-mobile-cards">
                <thead class="table-light">
                    <tr>
                        <th class="ps-4 text-secondary text-uppercase small fw-bold">Department</th>
                        <th class="text-center text-secondary text-uppercase small fw-bold text-success">New Joinings</th>
                        <th class="text-center text-secondary text-uppercase small fw-bold text-danger">Separations</th>
                        <th class="pe-4 text-center text-secondary text-uppercase small fw-bold">Net Headcount Change</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($departmentTurnover as $dept)
                        <tr>
                            <td class="ps-4 fw-bold text-dark" data-label="Department">{{ $dept['department'] }}</td>
                            <td class="text-center font-tabular fw-bold text-success" data-label="Joinings">+{{ $dept['joinings'] }}</td>
                            <td class="text-center font-tabular fw-bold text-danger" data-label="Separations">-{{ $dept['separations'] }}</td>
                            <td class="pe-4 text-center" data-label="Net Change">
                                <span class="badge {{ $dept['net_change'] > 0 ? 'bg-success' : ($dept['net_change'] < 0 ? 'bg-danger' : 'bg-secondary') }} rounded-pill px-3 py-1 font-tabular">
                                    {{ $dept['net_change'] > 0 ? '+' . $dept['net_change'] : $dept['net_change'] }}
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="text-center py-5 text-muted">
                                <x-empty-state
                                    title="No Turnover Data"
                                    description="No employee separations or new joinings recorded for this period."
                                    icon="fa-solid fa-user-slash" />
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
