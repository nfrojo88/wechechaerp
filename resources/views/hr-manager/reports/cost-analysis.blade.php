@extends('layouts.app')
@section('title', 'HR Payroll Cost Analysis - Construct-Pro ERP')

@section('content')
<div class="container-fluid py-4">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h2 class="h3 mb-1 fw-bold">
                <i class="fa-solid fa-money-bill-trend-up text-primary me-2"></i>HR Payroll Cost Analysis
            </h2>
            <p class="text-muted small mb-0">Analysis of departmental payroll expenditures, gross/net salaries, and tax distributions</p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('reports.attendance') }}" class="btn btn-outline-secondary btn-sm rounded-pill px-3 shadow-sm">
                <i class="fa-solid fa-arrow-left me-1"></i>All Reports
            </a>
        </div>
    </div>

    <!-- Navigation Report Tabs -->
    <div class="mb-4">
        <div class="nav nav-pills flex-column flex-sm-row gap-2 bg-light p-2 rounded-4 border">
            <a href="{{ route('reports.attendance') }}" class="nav-link rounded-pill px-3 fw-semibold text-secondary">
                <i class="fa-solid fa-calendar-check me-1"></i>Attendance Report
            </a>
            <a href="{{ route('reports.turnover') }}" class="nav-link rounded-pill px-3 fw-semibold text-secondary">
                <i class="fa-solid fa-user-slash me-1"></i>Turnover &amp; Retention
            </a>
            <a href="{{ route('reports.cost-analysis') }}" class="nav-link active rounded-pill px-3 fw-semibold">
                <i class="fa-solid fa-money-bill-trend-up me-1"></i>Payroll Cost Analysis
            </a>
            <a href="{{ route('reports.leave-analysis') }}" class="nav-link rounded-pill px-3 fw-semibold text-secondary">
                <i class="fa-solid fa-plane-departure me-1"></i>Leave Analysis
            </a>
            <a href="{{ route('reports.employee-cost') }}" class="nav-link rounded-pill px-3 fw-semibold text-secondary">
                <i class="fa-solid fa-id-card-clip me-1"></i>Employee Cost Breakdown
            </a>
        </div>
    </div>

    <!-- KPI Summary Cards -->
    <div class="row g-3 mb-4">
        <div class="col-xl-3 col-md-6">
            <div class="card border-0 shadow-sm rounded-4 h-100" style="border-left: 4px solid #3b82f6 !important;">
                <div class="card-body py-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="text-muted small text-uppercase fw-semibold">Total Gross Payroll</div>
                            <div class="h4 mb-0 fw-bold text-primary">{{ number_format($stats['total_gross'], 2) }} <small class="fs-6 text-muted">ETB</small></div>
                        </div>
                        <div class="rounded-circle d-flex align-items-center justify-content-center" style="width:48px;height:48px;background:#dbeafe;">
                            <i class="fa-solid fa-sack-dollar text-primary fa-lg"></i>
                        </div>
                    </div>
                    <div class="small text-muted mt-2">For {{ Carbon\Carbon::create($year, $month)->format('M Y') }}</div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card border-0 shadow-sm rounded-4 h-100" style="border-left: 4px solid #10b981 !important;">
                <div class="card-body py-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="text-muted small text-uppercase fw-semibold">Total Net Disbursed</div>
                            <div class="h4 mb-0 fw-bold text-success">{{ number_format($stats['total_net'], 2) }} <small class="fs-6 text-muted">ETB</small></div>
                        </div>
                        <div class="rounded-circle d-flex align-items-center justify-content-center" style="width:48px;height:48px;background:#d1fae5;">
                            <i class="fa-solid fa-hand-holding-dollar text-success fa-lg"></i>
                        </div>
                    </div>
                    <div class="small text-muted mt-2">Net paid to employees</div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card border-0 shadow-sm rounded-4 h-100" style="border-left: 4px solid #f59e0b !important;">
                <div class="card-body py-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="text-muted small text-uppercase fw-semibold">Total Taxes &amp; Deductions</div>
                            <div class="h4 mb-0 fw-bold text-warning">{{ number_format($stats['total_tax'] + $stats['total_deductions'], 2) }} <small class="fs-6 text-muted">ETB</small></div>
                        </div>
                        <div class="rounded-circle d-flex align-items-center justify-content-center" style="width:48px;height:48px;background:#fef3c7;">
                            <i class="fa-solid fa-file-invoice-dollar text-warning fa-lg"></i>
                        </div>
                    </div>
                    <div class="small text-muted mt-2">Income tax &amp; pension deductions</div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card border-0 shadow-sm rounded-4 h-100" style="border-left: 4px solid #6366f1 !important;">
                <div class="card-body py-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="text-muted small text-uppercase fw-semibold">Average Cost Per Head</div>
                            <div class="h4 mb-0 fw-bold text-indigo">{{ number_format($stats['avg_cost'], 2) }} <small class="fs-6 text-muted">ETB</small></div>
                        </div>
                        <div class="rounded-circle d-flex align-items-center justify-content-center" style="width:48px;height:48px;background:#e0e7ff;">
                            <i class="fa-solid fa-user-tag text-indigo fa-lg"></i>
                        </div>
                    </div>
                    <div class="small text-muted mt-2">{{ $stats['total_employees'] }} active staff processed</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Department Cost Breakdown -->
    <div class="card border-0 shadow-sm rounded-4 mb-4">
        <div class="card-header bg-light py-3 border-0 rounded-top-4">
            <h5 class="fw-bold mb-0 text-dark">
                <i class="fa-solid fa-building me-2 text-primary"></i>Department Payroll Summary
            </h5>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Department</th>
                        <th class="text-center">Staff Count</th>
                        <th class="text-end">Total Gross (ETB)</th>
                        <th class="text-end">Deductions</th>
                        <th class="text-end">Tax</th>
                        <th class="text-end">Total Net (ETB)</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($departmentCosts as $dept)
                    <tr>
                        <td class="fw-bold text-dark">{{ $dept->name }}</td>
                        <td class="text-center"><span class="badge bg-secondary rounded-pill px-3">{{ $dept->employee_count }}</span></td>
                        <td class="text-end fw-semibold text-primary">{{ number_format($dept->total_gross, 2) }}</td>
                        <td class="text-end text-danger">-{{ number_format($dept->total_deductions, 2) }}</td>
                        <td class="text-end text-warning">{{ number_format($dept->total_tax, 2) }}</td>
                        <td class="text-end fw-bold text-success">{{ number_format($dept->total_net, 2) }}</td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="text-center py-4 text-muted">No departmental payroll records found for this period.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
