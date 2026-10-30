@extends('layouts.app')
@section('title', 'HR Payroll Analytics Dashboard — Wechecha ERP')

@section('content')
<div class="container-fluid px-0">
    {{-- Page Header --}}
    <x-page-header
        title="HR Payroll Analytics Dashboard"
        subtitle="Real-time employee wage calculations, taxable brackets, pension liabilities, and net payroll obligations managed by HR."
        :breadcrumbs="[
            ['label' => 'Dashboard', 'url' => route('dashboard')],
            ['label' => 'HR Management', 'url' => route('dashboard.hr')],
            ['label' => 'Payroll Analytics']
        ]">
        <x-slot name="actions">
            <span class="badge bg-light text-dark border px-3 py-2 fw-semibold">
                <i class="fa-solid fa-users-gear text-primary me-1.5"></i>Roster Managed by HR
            </span>
            <a href="{{ route('finance.payroll.index') }}" class="btn btn-primary btn-sm rounded-pill px-3 py-2 shadow-xs fw-semibold">
                <i class="fa-solid fa-money-bill-wave me-1.5"></i>Full Payroll Ledger
            </a>
        </x-slot>
    </x-page-header>

    {{-- Executive Summary KPI Cards --}}
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-4 col-xl-2">
            <x-kpi-card
                title="Total Employees"
                :value="number_format($totals['employees'])"
                changeLabel="Active on payroll"
                icon="fa-solid fa-users"
                color="primary" />
        </div>
        <div class="col-6 col-md-4 col-xl-2">
            <x-kpi-card
                title="Total Gross"
                :value="'ETB ' . number_format($totals['gross'], 2)"
                changeLabel="Base + allowances"
                icon="fa-solid fa-sack-dollar"
                color="info" />
        </div>
        <div class="col-6 col-md-4 col-xl-2">
            <x-kpi-card
                title="Total Tax"
                :value="'ETB ' . number_format($totals['tax'], 2)"
                changeLabel="Withheld income tax"
                icon="fa-solid fa-receipt"
                color="warning" />
        </div>
        <div class="col-6 col-md-4 col-xl-2">
            <x-kpi-card
                title="Emp. Pension (7%)"
                :value="'ETB ' . number_format($totals['emp_pension'], 2)"
                changeLabel="Employee deduction"
                icon="fa-solid fa-vault"
                color="secondary" />
        </div>
        <div class="col-6 col-md-4 col-xl-2">
            <x-kpi-card
                title="Comp. Pension (11%)"
                :value="'ETB ' . number_format($totals['comp_pension'], 2)"
                changeLabel="Employer contribution"
                icon="fa-solid fa-landmark"
                color="dark" />
        </div>
        <div class="col-6 col-md-4 col-xl-2">
            <x-kpi-card
                title="Net Payable"
                :value="'ETB ' . number_format($totals['net_payable'], 2)"
                changeLabel="Net staff payout"
                icon="fa-solid fa-circle-check"
                color="success" />
        </div>
    </div>

    {{-- Payroll Roster Table Card --}}
    <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
        <div class="card-header bg-white py-3 px-4 border-bottom d-flex align-items-center justify-content-between">
            <div class="d-flex align-items-center gap-2">
                <span class="badge bg-primary bg-opacity-10 text-primary rounded-pill px-2.5 py-1 fw-bold small">
                    <i class="fa-solid fa-list-check me-1"></i>Staff Compensation
                </span>
                <h6 class="fw-bold mb-0 text-dark">Active Payroll Roster</h6>
            </div>
            <span class="text-muted small">{{ count($payrollData) }} Staff Listed</span>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 small wc-table-mobile-cards">
                <thead class="table-light">
                    <tr>
                        <th class="ps-4 text-secondary text-uppercase fw-bold">Emp ID</th>
                        <th class="text-secondary text-uppercase fw-bold">Full Name</th>
                        <th class="text-secondary text-uppercase fw-bold">Department</th>
                        <th class="text-end text-secondary text-uppercase fw-bold">Base Salary</th>
                        <th class="text-end text-secondary text-uppercase fw-bold text-primary">Gross Salary</th>
                        <th class="text-end text-secondary text-uppercase fw-bold text-dark">Taxable</th>
                        <th class="text-end text-secondary text-uppercase fw-bold text-danger">Deductions</th>
                        <th class="text-end text-secondary text-uppercase fw-bold">Pension (7%)</th>
                        <th class="text-end text-secondary text-uppercase fw-bold text-warning">Tax Amount</th>
                        <th class="text-end text-secondary text-uppercase fw-bold text-success">Net Salary</th>
                        <th class="pe-4 text-end text-secondary text-uppercase fw-bold">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($payrollData as $row)
                        <tr>
                            <td class="ps-4 font-monospace text-muted" data-label="Emp ID">{{ $row['emp_id'] }}</td>
                            <td data-label="Full Name">
                                <span class="fw-bold text-dark">{{ $row['name'] }}</span>
                            </td>
                            <td data-label="Department">
                                <span class="badge bg-secondary bg-opacity-10 text-secondary border">{{ $row['department'] }}</span>
                            </td>
                            <td class="text-end font-tabular fw-semibold text-dark" data-label="Base Salary">
                                {{ number_format($row['base_salary'], 2) }}
                            </td>
                            <td class="text-end font-tabular fw-semibold text-primary" data-label="Gross Salary">
                                {{ number_format($row['gross_salary'], 2) }}
                            </td>
                            <td class="text-end font-tabular fw-semibold text-dark" data-label="Taxable">
                                {{ number_format($row['taxable'], 2) }}
                            </td>
                            <td class="text-end font-tabular text-danger" data-label="Deductions">
                                -{{ number_format($row['deductions'], 2) }}
                            </td>
                            <td class="text-end font-tabular text-secondary" data-label="Pension (7%)">
                                -{{ number_format($row['pension'], 2) }}
                            </td>
                            <td class="text-end font-tabular text-warning fw-semibold" data-label="Tax Amount">
                                -{{ number_format($row['tax_amount'], 2) }}
                            </td>
                            <td class="text-end font-tabular fw-bold text-success fs-6" data-label="Net Salary">
                                {{ number_format($row['net_salary'], 2) }}
                            </td>
                            <td class="pe-4 text-end" data-label="Action">
                                <a href="{{ route('payroll.employee', $row['id']) }}" class="btn btn-sm btn-outline-primary rounded-pill px-3 py-1 shadow-xs fw-semibold">
                                    <i class="fa-solid fa-eye me-1"></i>View
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="11" class="text-center py-5 text-muted">
                                <x-empty-state
                                    title="No Active Employees Found"
                                    description="Add employees with contracted salary details to inspect live payroll calculations."
                                    icon="fa-solid fa-users-slash" />
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
