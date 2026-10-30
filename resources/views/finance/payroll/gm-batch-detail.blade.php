@extends('layouts.app')

@section('title', 'Review Payroll Batch — General Manager')

@section('content')
@php
    $periodLabel = ($month >= 1 && $month <= 13 && isset(\App\Helpers\EthiopianCalendar::MONTHS_EN[$month]))
        ? (\App\Helpers\EthiopianCalendar::MONTHS_EN[$month] . ' (' . \App\Helpers\EthiopianCalendar::MONTHS_AM[$month] . ') ' . $year . ' EC')
        : date('F Y', mktime(0,0,0,$month,1,$year));
@endphp

<div class="container-fluid px-0">
    {{-- Page Header --}}
    <x-page-header
        :title="'Review Payroll Batch: ' . $periodLabel"
        subtitle="Executive audit and approval of monthly payroll submitted by Finance Head, synced with verified biometric attendance."
        :breadcrumbs="[
            ['label' => 'Dashboard', 'url' => route('dashboard')],
            ['label' => 'Executive Office', 'url' => route('dashboard.gm')],
            ['label' => 'Payroll Approval', 'url' => route('finance.payroll.gm')],
            ['label' => $periodLabel]
        ]">
        <x-slot:actions>
            <a href="{{ route('finance.payroll.gm') }}" class="btn btn-outline-secondary btn-sm rounded-pill px-3 py-2 shadow-xs fw-semibold">
                <i class="fa-solid fa-arrow-left me-1.5"></i>Back to Inbox
            </a>
            <a href="{{ route('attendance.index', ['period' => $selectedPeriodKey ?? "{$year}-{$month}"]) }}" target="_blank" class="btn btn-outline-primary btn-sm rounded-pill px-3 py-2 shadow-xs fw-semibold">
                <i class="fa-solid fa-fingerprint me-1.5 text-primary"></i>View Attendance Matrix
            </a>
            <button type="button" class="btn btn-outline-dark btn-sm rounded-pill px-3 py-2 shadow-xs fw-semibold" onclick="window.print()">
                <i class="fa-solid fa-print me-1.5"></i>Print Batch
            </button>
        </x-slot:actions>
    </x-page-header>

    {{-- Executive Summary KPI Metrics --}}
    <div class="row g-3 mb-4">
        <div class="col-6 col-lg-3">
            <x-kpi-card
                title="Total Staff in Batch"
                :value="number_format($payrolls->count())"
                changeLabel="Verified employees on payroll"
                icon="fa-solid fa-users"
                color="primary" />
        </div>
        <div class="col-6 col-lg-3">
            <x-kpi-card
                title="Total Basic Salary"
                :value="'ETB ' . number_format($totals['basic'], 2)"
                changeLabel="Contracted base payroll"
                icon="fa-solid fa-money-bill-1"
                color="info" />
        </div>
        <div class="col-6 col-lg-3">
            <x-kpi-card
                title="Tax & Pension Remittance"
                :value="'ETB ' . number_format($totals['tax'] + $totals['pension'] + ($totals['company_pension'] ?? 0), 2)"
                changeLabel="Income tax & 18% total pension"
                icon="fa-solid fa-building-columns"
                color="warning" />
        </div>
        <div class="col-6 col-lg-3">
            <x-kpi-card
                title="Total Net Pay"
                :value="'ETB ' . number_format($totals['net'], 2)"
                changeLabel="Disbursement to staff bank accounts"
                icon="fa-solid fa-circle-check"
                color="success" />
        </div>
    </div>

    {{-- Payroll Breakdown Table Card --}}
    <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4 wc-printable-area">
        {{-- Formal Print Header (Only visible on print) --}}
        <div class="d-none d-print-block p-4 border-bottom text-center">
            <h2 class="fw-bold mb-1" style="color: #0F2944;">WECHECHA CONSTRUCTION ERP</h2>
            <h4 class="text-secondary fw-semibold mb-1">Official Executive Payroll Batch Sheet — {{ $periodLabel }}</h4>
            <p class="text-muted small mb-0">Generated for General Manager Final Approval | Currency: ETB</p>
        </div>

        <div class="card-header bg-white py-3 px-4 border-bottom d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div class="d-flex align-items-center gap-2">
                <span class="badge bg-primary bg-opacity-10 text-primary rounded-pill px-2.5 py-1 fw-bold small">
                    <i class="fa-solid fa-list-check me-1"></i>Employee Payroll Breakdown
                </span>
                <span class="text-muted small">{{ $payrolls->count() }} Employees Listed</span>
            </div>
            <span class="badge bg-success bg-opacity-10 text-success rounded-pill px-3 py-1 font-tabular fw-semibold">
                Net Pay: ETB {{ number_format($totals['net'], 2) }}
            </span>
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 small wc-table-mobile-cards">
                <thead class="table-light">
                    <tr>
                        <th class="ps-4 text-secondary text-uppercase fw-bold" style="width: 50px;">#</th>
                        <th class="text-secondary text-uppercase fw-bold">Employee</th>
                        <th class="text-secondary text-uppercase fw-bold">Department</th>
                        <th class="text-end text-secondary text-uppercase fw-bold">Basic Salary</th>
                        <th class="text-end text-secondary text-uppercase fw-bold text-info">Allowances</th>
                        <th class="text-end text-secondary text-uppercase fw-bold text-primary">Gross Pay</th>
                        <th class="text-end text-secondary text-uppercase fw-bold text-dark">Taxable</th>
                        <th class="text-end text-secondary text-uppercase fw-bold">Pension (7%)</th>
                        <th class="text-end text-secondary text-uppercase fw-bold" style="color: #6f42c1;">Co. Pension (11%)</th>
                        <th class="text-end text-secondary text-uppercase fw-bold text-warning">Income Tax</th>
                        <th class="text-end text-secondary text-uppercase fw-bold text-danger">Deductions</th>
                        <th class="pe-4 text-end text-secondary text-uppercase fw-bold text-success">Net Pay (ETB)</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($payrolls as $i => $p)
                        @php
                            $calcTaxable = $p->taxable_income ?? \App\Models\Payroll::calculateTaxableIncome(
                                (float) $p->basic_salary,
                                (float) ($p->house_allowance ?? 0),
                                (float) ($p->position_allowance ?? 0),
                                (float) ($p->transport_allowance ?? 0),
                                (float) ($p->overtime_pay ?? 0)
                            );
                            $calcCoPension = $p->company_pension ?? round($p->basic_salary * 0.11, 2);
                            $totalAllowances = ($p->transport_allowance ?? 0) + ($p->house_allowance ?? 0) + ($p->position_allowance ?? 0);
                        @endphp
                        <tr>
                            <td class="ps-4 text-muted font-monospace" data-label="#">{{ $i + 1 }}</td>
                            <td data-label="Employee">
                                <div class="fw-semibold text-dark">{{ $p->employee->full_name ?? 'N/A' }}</div>
                                <span class="badge bg-light text-muted border font-monospace" style="font-size: 0.7rem;">
                                    {{ $p->employee->employee_code ?? 'EMP-' . $p->employee_id }}
                                </span>
                            </td>
                            <td class="text-muted" data-label="Department">{{ $p->employee->department ?? '—' }}</td>
                            <td class="text-end font-tabular fw-semibold text-dark" data-label="Basic Salary">
                                {{ number_format($p->basic_salary, 2) }}
                            </td>
                            <td class="text-end font-tabular text-info" data-label="Allowances">
                                {{ number_format($totalAllowances, 2) }}
                            </td>
                            <td class="text-end font-tabular fw-semibold text-primary" data-label="Gross Pay">
                                {{ number_format($p->gross_salary ?? ($p->basic_salary + $p->allowances + $p->overtime_pay), 2) }}
                            </td>
                            <td class="text-end font-tabular fw-semibold text-dark" data-label="Taxable Income">
                                {{ number_format($calcTaxable, 2) }}
                            </td>
                            <td class="text-end font-tabular text-secondary" data-label="Pension (7%)">
                                {{ number_format($p->pension ?? round($p->basic_salary * 0.07, 2), 2) }}
                            </td>
                            <td class="text-end font-tabular" style="color: #6f42c1;" data-label="Co. Pension (11%)">
                                {{ number_format($calcCoPension, 2) }}
                            </td>
                            <td class="text-end font-tabular text-warning fw-semibold" data-label="Income Tax">
                                {{ number_format($p->tax, 2) }}
                            </td>
                            <td class="text-end font-tabular text-danger" data-label="Deductions">
                                <div class="fw-semibold">{{ number_format($p->deductions, 2) }}</div>
                                @if(($p->loan_deduction ?? 0) > 0 || ($p->absence_deduction ?? 0) > 0)
                                    <div class="text-muted" style="font-size: 0.68rem; line-height: 1.15;">
                                        @if(($p->loan_deduction ?? 0) > 0)
                                            <span class="d-block text-warning"><i class="fa-solid fa-hand-holding-dollar me-0.5"></i>Loan: {{ number_format($p->loan_deduction, 2) }}</span>
                                        @endif
                                        @if(($p->absence_deduction ?? 0) > 0 || ($p->absent_days ?? 0) > 0)
                                            <span class="d-block text-danger"><i class="fa-solid fa-calendar-xmark me-0.5"></i>Absent: {{ number_format($p->absence_deduction, 2) }}</span>
                                        @endif
                                    </div>
                                @endif
                            </td>
                            <td class="pe-4 text-end font-tabular fw-bold text-success fs-6" data-label="Net Pay (ETB)">
                                {{ number_format($p->net_salary, 2) }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot class="bg-light fw-bold border-top border-2">
                    <tr>
                        <td colspan="3" class="ps-4 py-3 text-uppercase text-secondary align-middle">BATCH TOTALS</td>
                        <td class="text-end py-3 font-tabular text-dark align-middle">{{ number_format($totals['basic'], 2) }}</td>
                        <td class="text-end py-3 font-tabular text-info align-middle">{{ number_format(($totals['transport'] ?? 0) + ($totals['house'] ?? 0) + ($totals['position'] ?? 0), 2) }}</td>
                        <td class="text-end py-3 font-tabular text-primary align-middle">{{ number_format($totals['gross'], 2) }}</td>
                        <td class="text-end py-3 font-tabular text-dark align-middle">{{ number_format($totals['taxable_income'], 2) }}</td>
                        <td class="text-end py-3 font-tabular text-secondary align-middle">{{ number_format($totals['pension'], 2) }}</td>
                        <td class="text-end py-3 font-tabular align-middle" style="color: #6f42c1;">{{ number_format($totals['company_pension'], 2) }}</td>
                        <td class="text-end py-3 font-tabular text-warning align-middle">{{ number_format($totals['tax'], 2) }}</td>
                        <td class="text-end py-3 font-tabular text-danger align-middle">{{ number_format($totals['deductions'] ?? $payrolls->sum('deductions'), 2) }}</td>
                        <td class="pe-4 text-end py-3 font-tabular text-success fs-6 align-middle" style="border-bottom: 3px double #10b981;">
                            <span class="small text-muted me-1">ETB</span>{{ number_format($totals['net'], 2) }}
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>

        {{-- Formal Print Signature Block --}}
        <div class="d-none d-print-block p-4 mt-5">
            <div class="row pt-5 text-center">
                <div class="col-4">
                    <div class="border-top border-dark pt-2">
                        <strong>Prepared By:</strong><br>
                        <span class="small text-muted">Payroll Officer</span>
                    </div>
                </div>
                <div class="col-4">
                    <div class="border-top border-dark pt-2">
                        <strong>Verified By:</strong><br>
                        <span class="small text-muted">Finance Head</span>
                    </div>
                </div>
                <div class="col-4">
                    <div class="border-top border-dark pt-2">
                        <strong>Authorized By:</strong><br>
                        <span class="small text-muted">General Manager</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Executive Decision Hub Card --}}
    <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4 p-4">
        <div class="d-flex align-items-center gap-2 mb-3">
            <span class="p-2 rounded-3 bg-primary bg-opacity-10 text-primary fs-5">
                <i class="fa-solid fa-gavel"></i>
            </span>
            <div>
                <h5 class="fw-bold mb-0 text-dark">General Manager Authorization Decision</h5>
                <p class="text-muted small mb-0">Record formal decision to release or reject this payroll batch.</p>
            </div>
        </div>

        <div class="row g-4 pt-2">
            {{-- Approval Option --}}
            <div class="col-12 col-lg-6 border-lg-end pe-lg-4">
                <div class="p-3 rounded-4 bg-success bg-opacity-10 mb-3 border border-success border-opacity-25">
                    <h6 class="fw-bold text-success mb-1">
                        <i class="fa-solid fa-circle-check me-1.5"></i>Authorize &amp; Release Payroll
                    </h6>
                    <p class="small text-muted mb-0">
                        Disbursement authorization for <strong class="text-success font-tabular">ETB {{ number_format($totals['net'], 2) }}</strong> across {{ $payrolls->count() }} employees.
                    </p>
                </div>
                <form method="POST" action="{{ route('finance.payroll.gm.approve') }}">
                    @csrf
                    <input type="hidden" name="month" value="{{ $month }}">
                    <input type="hidden" name="year"  value="{{ $year }}">
                    <div class="mb-3">
                        <label class="form-label text-muted small fw-bold text-uppercase" style="font-size: 0.72rem;">Approval Remarks (Optional)</label>
                        <textarea name="gm_notes" class="form-control" rows="2" placeholder="Add approval notes or disbursement instructions..."></textarea>
                    </div>
                    <button type="submit" class="btn btn-success rounded-pill px-4 py-2 fw-semibold shadow-xs" onclick="return confirm('Approve and release this payroll batch for disbursement?')">
                        <i class="fa-solid fa-check-circle me-1.5"></i>Authorize Payroll Batch
                    </button>
                </form>
            </div>

            {{-- Rejection Option --}}
            <div class="col-12 col-lg-6 ps-lg-4">
                <div class="p-3 rounded-4 bg-danger bg-opacity-10 mb-3 border border-danger border-opacity-25">
                    <h6 class="fw-bold text-danger mb-1">
                        <i class="fa-solid fa-circle-xmark me-1.5"></i>Reject &amp; Return to Finance Head
                    </h6>
                    <p class="small text-muted mb-0">
                        Return the batch for recalculation or attendance adjustments. Rejection reason is mandatory.
                    </p>
                </div>
                <form method="POST" action="{{ route('finance.payroll.gm.reject') }}">
                    @csrf
                    <input type="hidden" name="month" value="{{ $month }}">
                    <input type="hidden" name="year"  value="{{ $year }}">
                    <div class="mb-3">
                        <label class="form-label text-danger small fw-bold text-uppercase" style="font-size: 0.72rem;">Rejection Reason (Required)</label>
                        <textarea name="gm_notes" class="form-control border-danger border-opacity-50" rows="2" placeholder="Specify why this batch is rejected..." required></textarea>
                    </div>
                    <button type="submit" class="btn btn-outline-danger rounded-pill px-4 py-2 fw-semibold shadow-xs" onclick="return confirm('Reject this payroll batch and send back to Finance?')">
                        <i class="fa-solid fa-times-circle me-1.5"></i>Reject Payroll Batch
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<style>
@media print {
    body {
        background: #ffffff !important;
    }
    .sidebar, .navbar, .wc-bottom-nav, .btn, .breadcrumb, form, .card:not(.wc-printable-area) {
        display: none !important;
    }
    .wc-container, .container-fluid {
        max-width: 100% !important;
        padding: 0 !important;
        margin: 0 !important;
    }
    .wc-printable-area {
        box-shadow: none !important;
        border: none !important;
    }
}
</style>
@endsection
