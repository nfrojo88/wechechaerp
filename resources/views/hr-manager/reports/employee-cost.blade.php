@extends('layouts.app')
@section('title', 'HR Employee Cost Breakdown — Wechecha ERP')

@section('content')
@php
    $totalBasic = collect($employeeCosts)->sum('basic_salary');
    $totalAllowances = collect($employeeCosts)->sum('allowances');
    $totalGross = collect($employeeCosts)->sum('gross_salary');
    $totalNet = collect($employeeCosts)->sum('net_salary');
@endphp

<div class="container-fluid px-0">
    {{-- Page Header --}}
    <x-page-header
        title="Employee Cost Breakdown"
        subtitle="Itemized staff compensation breakdown: base salary, taxable and exempted allowances, statutory taxes, deductions, and net wage payouts."
        :breadcrumbs="[
            ['label' => 'Dashboard', 'url' => route('dashboard')],
            ['label' => 'HR Management', 'url' => route('dashboard.hr')],
            ['label' => 'HR Reports', 'url' => route('reports.attendance')],
            ['label' => 'Employee Cost Breakdown']
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
                <a href="{{ route('reports.cost-analysis') }}" class="nav-link rounded-pill px-3 py-2 fw-semibold text-secondary text-center">
                    <i class="fa-solid fa-money-bill-trend-up me-1.5"></i>Payroll Cost Analysis
                </a>
                <a href="{{ route('reports.leave-analysis') }}" class="nav-link rounded-pill px-3 py-2 fw-semibold text-secondary text-center">
                    <i class="fa-solid fa-plane-departure me-1.5"></i>Leave Analysis
                </a>
                <a href="{{ route('reports.employee-cost') }}" class="nav-link active rounded-pill px-3 py-2 fw-semibold text-center">
                    <i class="fa-solid fa-id-card-clip me-1.5"></i>Employee Cost Breakdown
                </a>
            </div>
        </div>
    </div>

    {{-- Executive Summary KPI Cards --}}
    <div class="row g-3 mb-4">
        <div class="col-6 col-lg-3">
            <x-kpi-card
                title="Staff Count"
                :value="number_format(count($employeeCosts))"
                :changeLabel="'Period ' . Carbon\Carbon::create($year, $month)->format('M Y')"
                icon="fa-solid fa-users"
                color="primary" />
        </div>
        <div class="col-6 col-lg-3">
            <x-kpi-card
                title="Total Basic Salary"
                :value="'ETB ' . number_format($totalBasic, 2)"
                changeLabel="Contracted base pay"
                icon="fa-solid fa-money-bill-1"
                color="info" />
        </div>
        <div class="col-6 col-lg-3">
            <x-kpi-card
                title="Total Gross Wages"
                :value="'ETB ' . number_format($totalGross, 2)"
                changeLabel="Base + allowances sum"
                icon="fa-solid fa-sack-dollar"
                color="dark" />
        </div>
        <div class="col-6 col-lg-3">
            <x-kpi-card
                title="Total Net Pay"
                :value="'ETB ' . number_format($totalNet, 2)"
                changeLabel="Disbursement liability"
                icon="fa-solid fa-circle-check"
                color="success" />
        </div>
    </div>

    {{-- Employee Cost Table Card --}}
    <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
        <div class="card-header bg-white py-3 px-4 border-bottom d-flex align-items-center justify-content-between">
            <div class="d-flex align-items-center gap-2">
                <span class="badge bg-primary bg-opacity-10 text-primary rounded-pill px-2.5 py-1 fw-bold small">
                    <i class="fa-solid fa-users me-1"></i>Staff Compensation
                </span>
                <h6 class="fw-bold mb-0 text-dark">
                    Staff Cost Breakdown for {{ Carbon\Carbon::create($year, $month)->format('F Y') }}
                </h6>
            </div>
            <span class="text-muted small">{{ count($employeeCosts) }} Records</span>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 wc-table-mobile-cards">
                <thead class="table-light">
                    <tr>
                        <th class="ps-4 text-secondary text-uppercase small fw-bold">Employee</th>
                        <th class="text-secondary text-uppercase small fw-bold">Department</th>
                        <th class="text-end text-secondary text-uppercase small fw-bold">Base Salary (ETB)</th>
                        <th class="text-end text-secondary text-uppercase small fw-bold text-success">Allowances</th>
                        <th class="text-end text-secondary text-uppercase small fw-bold text-primary">Gross Salary</th>
                        <th class="text-end text-secondary text-uppercase small fw-bold text-danger">Deductions</th>
                        <th class="text-end text-secondary text-uppercase small fw-bold text-warning">Tax</th>
                        <th class="pe-4 text-end text-secondary text-uppercase small fw-bold text-success">Net Pay (ETB)</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($employeeCosts as $row)
                        <tr>
                            <td class="ps-4" data-label="Employee">
                                <div class="fw-bold text-dark">{{ $row['employee']->name ?? $row['employee']->full_name }}</div>
                                <span class="badge bg-light text-muted border font-monospace" style="font-size: 0.7rem;">
                                    {{ $row['employee']->employee_id ?? 'EMP-' . $row['employee']->id }}
                                </span>
                            </td>
                            <td data-label="Department">
                                <span class="badge bg-secondary bg-opacity-10 text-secondary border">
                                    {{ $row['employee']->department->name ?? ($row['employee']->department_name ?? 'General') }}
                                </span>
                            </td>
                            <td class="text-end font-tabular fw-semibold text-dark" data-label="Base Salary">{{ number_format($row['basic_salary'], 2) }}</td>
                            <td class="text-end font-tabular text-success" data-label="Allowances">+{{ number_format($row['allowances'], 2) }}</td>
                            <td class="text-end font-tabular fw-bold text-primary" data-label="Gross">{{ number_format($row['gross_salary'], 2) }}</td>
                            <td class="text-end font-tabular text-danger" data-label="Deductions">-{{ number_format($row['deductions'], 2) }}</td>
                            <td class="text-end font-tabular text-warning fw-semibold" data-label="Tax">{{ number_format($row['tax'], 2) }}</td>
                            <td class="pe-4 text-end font-tabular fw-bold text-success fs-6" data-label="Net Pay">{{ number_format($row['net_salary'], 2) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center py-5 text-muted">
                                <x-empty-state
                                    title="No Employee Cost Records"
                                    description="No payroll compensation records found for this period."
                                    icon="fa-solid fa-id-card-clip" />
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
