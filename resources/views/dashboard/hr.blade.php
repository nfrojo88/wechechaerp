@extends('layouts.app')
@section('title', 'HR Dashboard')

@section('content')
<div class="wc-container py-3">

    {{-- Top Header --}}
    <x-page-header 
        title="Human Resources Command Center" 
        subtitle="Workforce headcount, biometric daily presence, payroll disbursement status, and official notices."
        :breadcrumbs="['Dashboard' => url('/dashboard'), 'Human Resources' => '']">
        <x-slot name="actions">
            <a href="{{ route('reports.attendance') }}" class="wc-btn-touch wc-btn-outline" title="HR Analytics & Attendance Reports">
                <i class="fa-solid fa-chart-line text-primary"></i>
                <span class="d-none d-sm-inline">HR Reports</span>
            </a>
            <a href="{{ route('employees.create') }}" class="wc-btn-touch wc-btn-primary">
                <i class="fa-solid fa-user-plus"></i>
                <span>Add Employee</span>
            </a>
        </x-slot>
    </x-page-header>

    {{-- 4 KPI Cards Row --}}
    <div class="row g-3 mb-4">
        <div class="col-6 col-xl-3">
            <x-kpi-card 
                label="Active Employees" 
                :value="$kpi['total_employees']" 
                delta="Staff" 
                trend="up" 
                icon="fa-solid fa-id-badge" 
                theme="primary" 
                subtext="Current active roster" />
        </div>

        <div class="col-6 col-xl-3">
            <x-kpi-card 
                label="Present Today" 
                :value="$kpi['present_today']" 
                delta="Biometric" 
                trend="up" 
                icon="fa-solid fa-calendar-check" 
                theme="success" 
                subtext="Clocked in across sites" />
        </div>

        <div class="col-6 col-xl-3">
            <x-kpi-card 
                label="Pending Payrolls" 
                :value="$kpi['pending_payroll']" 
                delta="Unapproved" 
                trend="neutral" 
                icon="fa-solid fa-money-bill-wave" 
                theme="warning" 
                subtext="Awaiting GM authorization" />
        </div>

        <div class="col-6 col-xl-3">
            <x-kpi-card 
                label="Official Letters" 
                :value="$kpi['total_letters'] ?? 0" 
                delta="Archive" 
                trend="neutral" 
                icon="fa-solid fa-file-signature" 
                theme="neutral" 
                subtext="Issued notices & warnings" />
        </div>
    </div>

    {{-- Main Content Grid --}}
    <div class="row g-3 mb-4">
        {{-- Left 8 Cols: Tables --}}
        <div class="col-lg-8">
            {{-- Recent Official Employee Letters --}}
            <div class="wc-card mb-4">
                <div class="card-header bg-white py-3 px-3 px-md-4 d-flex justify-content-between align-items-center border-bottom flex-wrap gap-2">
                    <div>
                        <h6 class="fw-bold text-dark mb-0">
                            <i class="fa-solid fa-file-lines text-primary me-2"></i>Recent Official Letters &amp; Notices
                        </h6>
                        <small class="text-muted">Disciplinary, appreciation, and role assignments</small>
                    </div>
                    <div class="d-flex gap-2">
                        <a href="{{ route('employee-letters.create') }}" class="wc-btn-touch wc-btn-primary p-1 px-2.5" style="font-size: var(--wc-text-xs);">
                            <i class="fa-solid fa-plus me-1"></i> Record Letter
                        </a>
                        <a href="{{ route('employee-letters.index') }}" class="wc-btn-touch wc-btn-outline p-1 px-2.5" style="font-size: var(--wc-text-xs);">
                            View All
                        </a>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0 wc-table-mobile-cards">
                        <thead class="bg-light text-uppercase text-muted" style="font-size: var(--wc-text-xs);">
                            <tr>
                                <th class="ps-3 ps-md-4">Ref / Date</th>
                                <th>Employee</th>
                                <th>Type</th>
                                <th>Subject</th>
                                <th class="text-end pe-3 pe-md-4">Actions</th>
                            </tr>
                        </thead>
                        <tbody style="font-size: var(--wc-text-sm);">
                            @forelse($recentLetters ?? [] as $letter)
                            <tr>
                                <td data-label="Ref / Date" class="ps-3 ps-md-4">
                                    <div class="font-monospace fw-bold small text-primary">{{ $letter->reference_number ?: 'LTR-#'.$letter->id }}</div>
                                    <small class="text-muted">{{ optional($letter->issued_date)->format('d M Y') }}</small>
                                </td>
                                <td data-label="Employee">
                                    <div class="fw-bold text-dark">{{ $letter->employee->full_name ?? 'N/A' }}</div>
                                    <small class="text-muted">{{ $letter->employee->employee_code ?? '' }}</small>
                                </td>
                                <td data-label="Type">
                                    <span class="badge bg-light text-dark border">{{ $letter->type_label }}</span>
                                </td>
                                <td data-label="Subject">
                                    <span class="text-truncate d-inline-block text-dark fw-medium" style="max-width: 200px;" title="{{ $letter->title }}">{{ $letter->title }}</span>
                                </td>
                                <td data-label="Actions" class="text-end pe-3 pe-md-4">
                                    <div class="d-inline-flex gap-1">
                                        <a href="{{ route('employee-letters.show', $letter) }}" class="wc-btn-touch wc-btn-outline p-1 px-2" title="View Letter">
                                            <i class="fa-solid fa-eye"></i>
                                        </a>
                                        <a href="{{ route('employee-letters.print', $letter) }}" target="_blank" class="wc-btn-touch wc-btn-outline p-1 px-2" title="Print Letterhead">
                                            <i class="fa-solid fa-print"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="5" class="text-center text-muted py-4 small">
                                    <i class="fa-solid fa-envelope-open-text fa-2x mb-2 d-block opacity-25"></i>
                                    No official letters recorded yet.
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- Recent Payrolls --}}
            <div class="wc-card">
                <div class="card-header bg-white py-3 px-3 px-md-4 d-flex justify-content-between align-items-center border-bottom">
                    <div>
                        <h6 class="fw-bold text-dark mb-0"><i class="fa-solid fa-file-invoice-dollar text-success me-2"></i>Recent Payroll Records</h6>
                        <small class="text-muted">Wage slips and monthly disbursements</small>
                    </div>
                    <a href="{{ route('payrolls.index') }}" class="text-primary small text-decoration-none fw-semibold">View All</a>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0 wc-table-mobile-cards">
                        <thead class="bg-light text-uppercase text-muted" style="font-size: var(--wc-text-xs);">
                            <tr>
                                <th class="ps-3 ps-md-4">Employee</th>
                                <th>Period</th>
                                <th class="text-end">Net Pay</th>
                                <th class="text-center pe-3 pe-md-4">Status</th>
                            </tr>
                        </thead>
                        <tbody style="font-size: var(--wc-text-sm);">
                            @forelse($recentPayrolls as $payroll)
                            @php $net = $payroll->basic_salary + $payroll->allowances + $payroll->overtime_pay - $payroll->deductions - $payroll->tax; @endphp
                            <tr>
                                <td data-label="Employee" class="ps-3 ps-md-4">
                                    <div class="fw-bold text-dark">{{ $payroll->employee->first_name ?? $payroll->employee->full_name ?? 'N/A' }} {{ $payroll->employee->last_name ?? '' }}</div>
                                    <small class="text-muted">{{ $payroll->employee->department ?? 'General' }}</small>
                                </td>
                                <td data-label="Period" class="small text-muted">{{ date('M', mktime(0,0,0,$payroll->month,1)) }} {{ $payroll->year }}</td>
                                <td data-label="Net Pay" class="text-end wc-num font-monospace fw-bold text-dark">ETB {{ number_format($net, 2) }}</td>
                                <td data-label="Status" class="text-center pe-3 pe-md-4">
                                    <x-status-badge :status="$payroll->status == 'paid' ? 'paid' : 'pending'" />
                                </td>
                            </tr>
                            @empty
                            <tr><td colspan="4" class="text-center text-muted py-4 small">No payroll records found.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        {{-- Right 4 Cols: Quick Actions Panel --}}
        <div class="col-lg-4">
            <div class="wc-card p-3 p-md-4 mb-4">
                <h6 class="fw-bold text-dark mb-3">
                    <i class="fa-solid fa-bolt text-warning me-2"></i>HR Quick Actions
                </h6>
                <div class="d-flex flex-column gap-2">
                    <a href="{{ route('employee-letters.create') }}" class="wc-btn-touch wc-btn-primary w-100 justify-content-start">
                        <i class="fa-solid fa-file-signature text-warning"></i>
                        <span>Record Official Notice</span>
                    </a>
                    <a href="{{ route('payrolls.create') }}" class="wc-btn-touch wc-btn-outline w-100 justify-content-start">
                        <i class="fa-solid fa-money-check-dollar text-success"></i>
                        <span>Generate Monthly Payroll</span>
                    </a>
                    <a href="{{ route('attendance.index') }}" class="wc-btn-touch wc-btn-outline w-100 justify-content-start">
                        <i class="fa-solid fa-clipboard-user text-primary"></i>
                        <span>Biometric Attendance Roster</span>
                    </a>
                    <a href="{{ route('manpower-requests.create') }}" class="wc-btn-touch wc-btn-outline w-100 justify-content-start">
                        <i class="fa-solid fa-person-circle-plus text-info"></i>
                        <span>Submit Manpower Request</span>
                    </a>
                    <a href="{{ route('reports.turnover') }}" class="wc-btn-touch wc-btn-outline w-100 justify-content-start">
                        <i class="fa-solid fa-chart-pie text-secondary"></i>
                        <span>Staff Turnover Analysis</span>
                    </a>
                </div>
            </div>

            {{-- Helpful Info Card --}}
            <div class="wc-card p-3 bg-light border-0">
                <div class="d-flex align-items-start gap-2">
                    <i class="fa-solid fa-circle-info text-primary mt-1"></i>
                    <small class="text-muted" style="font-size: var(--wc-text-xs);">
                        Daily site manpower attendance reports must be approved by the HR Manager before monthly wage calculations are compiled.
                    </small>
                </div>
            </div>
        </div>
    </div>

</div>
@endsection
