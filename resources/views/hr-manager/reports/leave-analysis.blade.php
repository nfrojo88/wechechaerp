@extends('layouts.app')
@section('title', 'HR Leave Analysis Report — Wechecha ERP')

@section('content')
<div class="container-fluid px-0">
    {{-- Page Header --}}
    <x-page-header
        title="HR Leave & Absence Analysis"
        subtitle="Evaluation of annual leave utilization, medical leaves, compassionate approvals, and leave days taken across the enterprise."
        :breadcrumbs="[
            ['label' => 'Dashboard', 'url' => route('dashboard')],
            ['label' => 'HR Management', 'url' => route('dashboard.hr')],
            ['label' => 'HR Reports', 'url' => route('reports.attendance')],
            ['label' => 'Leave Analysis']
        ]">
        <x-slot:actions>
            <a href="{{ route('reports.attendance') }}" class="btn btn-outline-secondary btn-sm rounded-pill px-3 py-2 shadow-xs fw-semibold">
                <i class="fa-solid fa-arrow-left me-1.5"></i>All HR Reports
            </a>
            <a href="{{ route('leaves.index') }}" class="btn btn-primary btn-sm rounded-pill px-3 py-2 shadow-xs fw-semibold">
                <i class="fa-solid fa-plane-departure me-1.5"></i>Leave Requests
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
                <a href="{{ route('reports.turnover') }}" class="nav-link rounded-pill px-3 py-2 fw-semibold text-secondary text-center">
                    <i class="fa-solid fa-user-slash me-1.5"></i>Turnover &amp; Retention
                </a>
                <a href="{{ route('reports.cost-analysis') }}" class="nav-link rounded-pill px-3 py-2 fw-semibold text-secondary text-center">
                    <i class="fa-solid fa-money-bill-trend-up me-1.5"></i>Payroll Cost Analysis
                </a>
                <a href="{{ route('reports.leave-analysis') }}" class="nav-link active rounded-pill px-3 py-2 fw-semibold text-center">
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
                title="Total Leave Requests"
                :value="number_format($stats['total_requests'])"
                :changeLabel="'Submitted for year ' . $year"
                icon="fa-solid fa-envelope-open-text"
                color="primary" />
        </div>
        <div class="col-6 col-lg-3">
            <x-kpi-card
                title="Approved Requests"
                :value="number_format($stats['approved'])"
                changeLabel="Authorized by management"
                icon="fa-solid fa-calendar-check"
                color="success" />
        </div>
        <div class="col-6 col-lg-3">
            <x-kpi-card
                title="Total Days Taken"
                :value="number_format($stats['total_days'] ?? 0) . ' Days'"
                changeLabel="Cumulative days consumed"
                icon="fa-solid fa-business-time"
                color="warning" />
        </div>
        <div class="col-6 col-lg-3">
            <x-kpi-card
                title="Rejected Requests"
                :value="number_format($stats['rejected'])"
                changeLabel="Denied leave applications"
                icon="fa-solid fa-circle-xmark"
                color="danger" />
        </div>
    </div>

    {{-- Leave by Type Breakdown Table --}}
    <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
        <div class="card-header bg-white py-3 px-4 border-bottom d-flex align-items-center justify-content-between">
            <div class="d-flex align-items-center gap-2">
                <span class="badge bg-primary bg-opacity-10 text-primary rounded-pill px-2.5 py-1 fw-bold small">
                    <i class="fa-solid fa-tags me-1"></i>Leave Types
                </span>
                <h6 class="fw-bold mb-0 text-dark">Leave Breakdown by Category</h6>
            </div>
            <span class="text-muted small">Year {{ $year }}</span>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 wc-table-mobile-cards">
                <thead class="table-light">
                    <tr>
                        <th class="ps-4 text-secondary text-uppercase small fw-bold">Leave Type Category</th>
                        <th class="text-center text-secondary text-uppercase small fw-bold">Approved Applications</th>
                        <th class="pe-4 text-end text-secondary text-uppercase small fw-bold">Cumulative Days Taken</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($leaveByType as $lt)
                        <tr>
                            <td class="ps-4 fw-bold text-dark" data-label="Leave Type">
                                <i class="fa-solid fa-calendar-day text-primary me-2"></i>{{ $lt->name }}
                            </td>
                            <td class="text-center font-tabular" data-label="Requests">
                                <span class="badge bg-primary bg-opacity-10 text-primary rounded-pill px-3 py-1 fw-semibold">
                                    {{ $lt->count }} Applications
                                </span>
                            </td>
                            <td class="pe-4 text-end font-tabular fw-bold text-primary fs-6" data-label="Days Taken">
                                {{ $lt->days }} <span class="text-muted small fw-normal">Days</span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" class="text-center py-5 text-muted">
                                <x-empty-state
                                    title="No Leave Records"
                                    description="No approved leave applications recorded for year {{ $year }}."
                                    icon="fa-solid fa-plane-departure" />
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
