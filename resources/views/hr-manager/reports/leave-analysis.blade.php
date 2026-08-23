@extends('layouts.app')
@section('title', 'HR Leave Analysis Report - Construct-Pro ERP')

@section('content')
<div class="container-fluid py-4">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h2 class="h3 mb-1 fw-bold">
                <i class="fa-solid fa-plane-departure text-primary me-2"></i>HR Leave Analysis Report
            </h2>
            <p class="text-muted small mb-0">Analysis of employee leave requests, leave types distribution, and departmental leave days</p>
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
            <a href="{{ route('reports.cost-analysis') }}" class="nav-link rounded-pill px-3 fw-semibold text-secondary">
                <i class="fa-solid fa-money-bill-trend-up me-1"></i>Payroll Cost Analysis
            </a>
            <a href="{{ route('reports.leave-analysis') }}" class="nav-link active rounded-pill px-3 fw-semibold">
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
                            <div class="text-muted small text-uppercase fw-semibold">Total Requests</div>
                            <div class="h3 mb-0 fw-bold text-primary">{{ $stats['total_requests'] }}</div>
                        </div>
                        <div class="rounded-circle d-flex align-items-center justify-content-center" style="width:48px;height:48px;background:#dbeafe;">
                            <i class="fa-solid fa-envelope-open-text text-primary fa-lg"></i>
                        </div>
                    </div>
                    <div class="small text-muted mt-2">Submitted for year {{ $year }}</div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card border-0 shadow-sm rounded-4 h-100" style="border-left: 4px solid #10b981 !important;">
                <div class="card-body py-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="text-muted small text-uppercase fw-semibold">Approved Requests</div>
                            <div class="h3 mb-0 fw-bold text-success">{{ $stats['approved'] }}</div>
                        </div>
                        <div class="rounded-circle d-flex align-items-center justify-content-center" style="width:48px;height:48px;background:#d1fae5;">
                            <i class="fa-solid fa-calendar-check text-success fa-lg"></i>
                        </div>
                    </div>
                    <div class="small text-muted mt-2">Approved by management</div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card border-0 shadow-sm rounded-4 h-100" style="border-left: 4px solid #f59e0b !important;">
                <div class="card-body py-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="text-muted small text-uppercase fw-semibold">Total Leave Days</div>
                            <div class="h3 mb-0 fw-bold text-warning">{{ $stats['total_days'] ?? 0 }} <small class="fs-6 text-muted">days</small></div>
                        </div>
                        <div class="rounded-circle d-flex align-items-center justify-content-center" style="width:48px;height:48px;background:#fef3c7;">
                            <i class="fa-solid fa-business-time text-warning fa-lg"></i>
                        </div>
                    </div>
                    <div class="small text-muted mt-2">Cumulative days taken</div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card border-0 shadow-sm rounded-4 h-100" style="border-left: 4px solid #ef4444 !important;">
                <div class="card-body py-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="text-muted small text-uppercase fw-semibold">Rejected Requests</div>
                            <div class="h3 mb-0 fw-bold text-danger">{{ $stats['rejected'] }}</div>
                        </div>
                        <div class="rounded-circle d-flex align-items-center justify-content-center" style="width:48px;height:48px;background:#fee2e2;">
                            <i class="fa-solid fa-circle-xmark text-danger fa-lg"></i>
                        </div>
                    </div>
                    <div class="small text-muted mt-2">Denied leave applications</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Leave by Type Table -->
    <div class="card border-0 shadow-sm rounded-4 mb-4">
        <div class="card-header bg-light py-3 border-0 rounded-top-4">
            <h5 class="fw-bold mb-0 text-dark">
                <i class="fa-solid fa-tag me-2 text-primary"></i>Leave Breakdown by Type
            </h5>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Leave Type</th>
                        <th class="text-center">Total Requests</th>
                        <th class="text-center">Total Days Taken</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($leaveByType as $lt)
                    <tr>
                        <td class="fw-bold text-dark">{{ $lt->name }}</td>
                        <td class="text-center"><span class="badge bg-secondary rounded-pill px-3">{{ $lt->count }}</span></td>
                        <td class="text-center fw-bold text-primary">{{ $lt->days }} days</td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="3" class="text-center py-4 text-muted">No approved leave records found for {{ $year }}.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
