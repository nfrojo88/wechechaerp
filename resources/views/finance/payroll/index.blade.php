@extends('layouts.app')
@section('title', 'Payroll Management — Finance Head')

@section('content')
<div class="container-fluid px-4 py-3">

{{-- ── Header ────────────────────────────────────────────────────────────── --}}
<div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-3">
    <div>
        <h1 class="h3 fw-bold mb-1">
            <i class="fa-solid fa-money-bill-wave text-success me-2"></i>Payroll Management
        </h1>
        <p class="text-muted small mb-0">
            Generate, review, and submit monthly payroll for GM approval synced directly with Ethiopian Attendance Periods (26th–25th).
        </p>
    </div>
    <div class="d-flex align-items-center gap-2 flex-wrap">
        {{-- Ethiopian Attendance Payroll Period Selector --}}
        <form method="GET" action="{{ route('finance.payroll.index') }}" class="d-flex flex-wrap gap-2 align-items-center">
            <div class="input-group input-group-sm" style="min-width: 270px;" title="Ethiopian Attendance Payroll Period (26th–25th)">
                <span class="input-group-text bg-white text-primary border-end-0">
                    <i class="fa-solid fa-calendar-days"></i>
                </span>
                <select name="period" id="payrollPeriodSelect" class="form-select form-select-sm border-start-0 fw-semibold" onchange="this.form.submit()">
                    @foreach($availablePeriods as $p)
                        <option value="{{ $p['period_key'] }}" {{ $p['period_key'] === $selectedPeriodKey ? 'selected' : '' }}>
                            {{ $p['full_label'] }} ({{ $p['label_en'] }})
                        </option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="btn btn-sm btn-primary px-3 shadow-xs">
                <i class="fa-solid fa-filter me-1"></i>Filter
            </button>
        </form>

        {{-- Direct Link: View Attendance Matrix in Attendance Section --}}
        <a href="{{ route('attendance.index', ['period' => $selectedPeriodKey]) }}" target="_blank" class="btn btn-sm btn-outline-primary shadow-xs rounded-pill px-3" title="Open verified attendance matrix for this period in Attendance Section">
            <i class="fa-solid fa-fingerprint me-1.5 text-primary"></i>View in Attendance Matrix
        </a>

        {{-- Export Attendance PDF --}}
        <a href="{{ route('attendance.export-pdf', ['period' => $selectedPeriodKey]) }}" target="_blank" class="btn btn-sm btn-outline-danger shadow-xs rounded-pill px-2.5" title="Export this period's Clock In / Out Times matrix as PDF">
            <i class="fa-solid fa-file-pdf me-1"></i>Attendance PDF
        </a>
    </div>
</div>

{{-- ── Alerts ──────────────────────────────────────────────────────────── --}}
@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm rounded-3">
        <i class="fa-solid fa-circle-check me-2"></i>{{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif
@if(session('error'))
    <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm rounded-3">
        <i class="fa-solid fa-triangle-exclamation me-2"></i>{{ session('error') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

{{-- ── Period & GM Status Banner ────────────────────────────────────────── --}}
@php
    $periodTitle = ($periodData['full_label'] ?? "Month {$month}") . " (" . ($periodData['label_en'] ?? "EC") . ")";
    $batchApproved  = $payrolls->where('gm_status','approved')->count() === $payrolls->count() && $payrolls->count() > 0;
    $batchSubmitted = $payrolls->where('gm_status','submitted')->count() > 0;
    $batchRejected  = $payrolls->where('gm_status','rejected')->count()  > 0;
@endphp

<div class="alert border-0 rounded-3 shadow-sm mb-4
    @if($batchApproved) alert-success
    @elseif($batchRejected) alert-danger
    @elseif($batchSubmitted) alert-warning
    @else alert-info @endif" role="alert">
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
        <div class="d-flex align-items-center flex-wrap gap-2">
            <strong><i class="fa-solid fa-calendar-days me-1"></i>{{ $periodTitle }} Payroll</strong>
            <span class="badge bg-white text-dark border shadow-xs px-2.5 py-1.5" style="font-size: 0.78rem;">
                <i class="fa-solid fa-calendar-check text-success me-1"></i>Attendance Window: 
                <strong class="text-primary">{{ $attendanceDayWindow }}</strong>
                <span class="text-muted ms-1">({{ \Carbon\Carbon::parse($startDate)->format('M d, Y') }} &ndash; {{ \Carbon\Carbon::parse($endDate)->format('M d, Y') }})</span>
            </span>
            <span class="text-muted">—</span>
            @if($batchApproved)
                <span class="badge bg-success rounded-pill"><i class="fa-solid fa-check me-1"></i>GM Approved</span>
            @elseif($batchRejected)
                <span class="badge bg-danger rounded-pill"><i class="fa-solid fa-xmark me-1"></i>GM Rejected — Revision Needed</span>
            @elseif($batchSubmitted)
                <span class="badge bg-warning text-dark rounded-pill"><i class="fa-solid fa-clock me-1"></i>Awaiting GM Approval</span>
            @elseif($payrolls->isEmpty())
                <span class="badge bg-secondary rounded-pill">No payroll generated yet</span>
            @else
                <span class="badge bg-info text-dark rounded-pill">Draft — Not submitted</span>
            @endif
        </div>
        <div class="d-flex gap-2">
            @if($payrolls->isEmpty())
            {{-- Generate Button --}}
            <form method="POST" action="{{ route('finance.payroll.generate') }}">
                @csrf
                <input type="hidden" name="period"     value="{{ $selectedPeriodKey }}">
                <input type="hidden" name="month"      value="{{ $month }}">
                <input type="hidden" name="year"       value="{{ $year }}">
                <input type="hidden" name="start_date" value="{{ $startDate }}">
                <input type="hidden" name="end_date"   value="{{ $endDate }}">
                <input type="hidden" name="date"       value="{{ $endDate }}">
                <button type="submit" class="btn btn-primary btn-sm rounded-pill px-3 shadow-sm"
                        onclick="return confirm('Auto-generate payroll for all active employees for {{ $periodTitle }} (Attendance Window: {{ $attendanceDayWindow }})?')">
                    <i class="fa-solid fa-bolt me-1"></i>Auto-Generate Payroll
                </button>
            </form>
            @elseif(!$batchSubmitted && !$batchApproved)
            {{-- Recalculate / Sync Button --}}
            <form method="POST" action="{{ url('/finance/payroll/recalculate') }}">
                @csrf
                <input type="hidden" name="period"     value="{{ $selectedPeriodKey }}">
                <input type="hidden" name="month"      value="{{ $month }}">
                <input type="hidden" name="year"       value="{{ $year }}">
                <input type="hidden" name="start_date" value="{{ $startDate }}">
                <input type="hidden" name="end_date"   value="{{ $endDate }}">
                <input type="hidden" name="date"       value="{{ $endDate }}">
                <button type="submit" class="btn btn-outline-primary btn-sm rounded-pill px-3 shadow-sm"
                        onclick="return confirm('Recalculate payroll for {{ $periodTitle }} (Attendance Window: {{ $attendanceDayWindow }})? This will refresh all attendance records and loan deductions.')">
                    <i class="fa-solid fa-arrows-rotate me-1"></i>Recalculate / Sync
                </button>
            </form>
            <button class="btn btn-warning btn-sm rounded-pill px-3 shadow-sm text-dark fw-semibold" data-bs-toggle="modal" data-bs-target="#submitGMModal">
                <i class="fa-solid fa-paper-plane me-1"></i>Send to GM for Approval
            </button>
            @elseif($batchRejected)
            {{-- Recalculate Button on Rejected --}}
            <form method="POST" action="{{ url('/finance/payroll/recalculate') }}">
                @csrf
                <input type="hidden" name="period"     value="{{ $selectedPeriodKey }}">
                <input type="hidden" name="month"      value="{{ $month }}">
                <input type="hidden" name="year"       value="{{ $year }}">
                <input type="hidden" name="start_date" value="{{ $startDate }}">
                <input type="hidden" name="end_date"   value="{{ $endDate }}">
                <input type="hidden" name="date"       value="{{ $endDate }}">
                <button type="submit" class="btn btn-outline-primary btn-sm rounded-pill px-3 shadow-sm"
                        onclick="return confirm('Recalculate payroll for {{ $periodTitle }} (Attendance Window: {{ $attendanceDayWindow }})?')">
                    <i class="fa-solid fa-arrows-rotate me-1"></i>Recalculate / Sync
                </button>
            </form>
            <button class="btn btn-warning btn-sm rounded-pill px-3 shadow-sm text-dark fw-semibold" data-bs-toggle="modal" data-bs-target="#submitGMModal">
                <i class="fa-solid fa-paper-plane me-1"></i>Re-Submit to GM
            </button>
            @endif
        </div>
    </div>
    @if($batchRejected)
    @php $rejection = $payrolls->where('gm_status','rejected')->first(); @endphp
    @if($rejection && $rejection->gm_notes)
    <hr class="my-2">
    <small><strong>GM Notes:</strong> {{ $rejection->gm_notes }}</small>
    @endif
    @endif
</div>

{{-- ── Summary KPI Cards ────────────────────────────────────────────────── --}}
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3 col-xl">
        <div class="card border-0 shadow-sm rounded-3 h-100">
            <div class="card-body text-center py-3">
                <div class="text-muted small mb-1">Employees</div>
                <div class="fw-bold fs-4 text-primary">{{ $totals['count'] }}</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3 col-xl">
        <div class="card border-0 shadow-sm rounded-3 h-100">
            <div class="card-body text-center py-3">
                <div class="text-muted small mb-1">Total Basic</div>
                <div class="fw-bold fs-5 text-dark">{{ number_format($totals['basic'],2) }}</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3 col-xl">
        <div class="card border-0 shadow-sm rounded-3 h-100">
            <div class="card-body text-center py-3">
                <div class="text-muted small mb-1">Total Allowances</div>
                <div class="fw-bold fs-5 text-info">{{ number_format($totals['transport']+$totals['house']+$totals['position'],2) }}</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3 col-xl">
        <div class="card border-0 shadow-sm rounded-3 h-100">
            <div class="card-body text-center py-3">
                <div class="text-muted small mb-1">Taxable Income</div>
                <div class="fw-bold fs-5 text-dark" style="color:#0f5132!important;">{{ number_format($totals['taxable_income'],2) }}</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3 col-xl">
        <div class="card border-0 shadow-sm rounded-3 h-100">
            <div class="card-body text-center py-3">
                <div class="text-muted small mb-1">Pension (Emp 7%)</div>
                <div class="fw-bold fs-5 text-secondary">{{ number_format($totals['pension'],2) }}</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3 col-xl">
        <div class="card border-0 shadow-sm rounded-3 h-100">
            <div class="card-body text-center py-3">
                <div class="text-muted small mb-1">Company Pension (11%)</div>
                <div class="fw-bold fs-5 text-purple" style="color:#6f42c1!important;">{{ number_format($totals['company_pension'],2) }}</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3 col-xl">
        <div class="card border-0 shadow-sm rounded-3 h-100">
            <div class="card-body text-center py-3">
                <div class="text-muted small mb-1">Total Tax</div>
                <div class="fw-bold fs-5 text-warning">{{ number_format($totals['tax'],2) }}</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3 col-xl">
        <div class="card border-0 shadow-sm rounded-3 h-100" style="background:linear-gradient(135deg,#0d6efd,#0a58ca)">
            <div class="card-body text-center py-3">
                <div class="text-white-50 small mb-1">Total Net Pay</div>
                <div class="fw-bold fs-5 text-white">{{ number_format($totals['net'],2) }} ETB</div>
            </div>
        </div>
    </div>
</div>

{{-- ── Payroll Table ─────────────────────────────────────────────────────── --}}
<div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
    <div class="card-header bg-white py-3 px-4 border-bottom d-flex align-items-center justify-content-between flex-wrap gap-2">
        <div class="d-flex align-items-center gap-2 flex-wrap">
            <h6 class="fw-bold mb-0"><i class="fa-solid fa-table-list text-primary me-2"></i>{{ $periodTitle }} Payroll Sheet</h6>
            <span class="badge bg-light text-secondary border fw-normal" style="font-size: 0.78rem;">
                <i class="fa-regular fa-calendar-days text-primary me-1"></i>{{ $attendanceDayWindow }}
            </span>
        </div>
        <div class="d-flex gap-2 align-items-center">
            @if($payrolls->isNotEmpty() && !$batchApproved && !$batchSubmitted)
            <form method="POST" action="{{ url('/finance/payroll/recalculate') }}">
                @csrf
                <input type="hidden" name="period"     value="{{ $selectedPeriodKey }}">
                <input type="hidden" name="month"      value="{{ $month }}">
                <input type="hidden" name="year"       value="{{ $year }}">
                <input type="hidden" name="start_date" value="{{ $startDate }}">
                <input type="hidden" name="end_date"   value="{{ $endDate }}">
                <input type="hidden" name="date"       value="{{ $endDate }}">
                <button type="submit" class="btn btn-sm btn-outline-primary rounded-pill shadow-xs"
                        onclick="return confirm('Recalculate payroll for {{ $periodTitle }} (Attendance Window: {{ $attendanceDayWindow }})?')">
                    <i class="fa-solid fa-arrows-rotate me-1"></i>Recalculate
                </button>
            </form>
            @endif
            @if($payrolls->isNotEmpty())
            <a href="#" onclick="window.print()" class="btn btn-sm btn-outline-secondary rounded-pill">
                <i class="fa-solid fa-print me-1"></i>Print
            </a>
            @endif
        </div>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0 small">
            <thead class="bg-light">
                <tr>
                    <th class="ps-4">#</th>
                    <th style="min-width: 220px;">Employee &amp; Attendance</th>
                    <th>Department</th>
                    <th class="text-end">Basic Salary</th>
                    <th class="text-end text-info">Transport</th>
                    <th class="text-end text-info">House</th>
                    <th class="text-end text-info">Position</th>
                    <th class="text-end text-primary">Gross</th>
                    <th class="text-end fw-semibold text-dark">Taxable Income</th>
                    <th class="text-end text-secondary">Pension (7%)</th>
                    <th class="text-end text-purple" style="color:#6f42c1;">Co. Pension (11%)</th>
                    <th class="text-end text-warning">Income Tax</th>
                    <th class="text-end text-danger" style="min-width: 135px;">Other Ded.</th>
                    <th class="text-end fw-bold text-success">Net Pay</th>
                    <th class="text-center">Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse($payrolls as $i => $p)
                @php
                    $calcTaxable = $p->taxable_income ?? \App\Models\Payroll::calculateTaxableIncome(
                        (float) $p->basic_salary,
                        (float) ($p->house_allowance ?? 0),
                        (float) ($p->position_allowance ?? 0),
                        (float) ($p->transport_allowance ?? 0),
                        (float) ($p->overtime_pay ?? 0)
                    );
                    $calcCoPension = $p->company_pension ?? round($p->basic_salary * 0.11, 2);
                    $attStat = $attendanceStats[$p->employee_id] ?? null;
                @endphp
                <tr>
                    <td class="ps-4 text-muted">{{ $i + 1 }}</td>
                    <td>
                        <div class="d-flex align-items-center justify-content-between gap-1">
                            <div class="fw-semibold text-dark">{{ $p->employee->full_name ?? 'N/A' }}</div>
                            <a href="{{ route('attendance.index', ['period' => $selectedPeriodKey, 'search' => $p->employee->employee_code ?: $p->employee->full_name]) }}" target="_blank" class="badge bg-primary-subtle text-primary border border-primary-subtle text-decoration-none" title="Audit verified biometric punches for {{ $p->employee->full_name }} in Attendance Section">
                                <i class="fa-solid fa-fingerprint me-0.5"></i>Audit &rarr;
                            </a>
                        </div>
                        <div class="text-muted" style="font-size:.75rem">{{ $p->employee->employee_code ?? '' }}</div>
                        {{-- Attendance Summary Badges --}}
                        @if($attStat)
                        <div class="d-flex align-items-center gap-1 mt-1 flex-wrap font-monospace" style="font-size: 0.68rem;">
                            <span class="badge bg-success-subtle text-success border border-success-subtle px-1 py-0.5" title="Present (Biometric Punches): {{ $attStat['present'] }} days">P: {{ $attStat['present'] }}</span>
                            @if($attStat['site'] > 0)
                            <span class="badge bg-info-subtle text-info border border-info-subtle px-1 py-0.5" title="Approved Site Deployment: {{ $attStat['site'] }} days">S: {{ $attStat['site'] }}</span>
                            @endif
                            @if($attStat['leave'] > 0)
                            <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-1 py-0.5" title="Approved Leave: {{ $attStat['leave'] }} days">L: {{ $attStat['leave'] }}</span>
                            @endif
                            @if($attStat['late'] > 0)
                            <span class="badge bg-warning-subtle text-warning border border-warning-subtle px-1 py-0.5" title="Late Check-ins (&gt;08:40 AM): {{ $attStat['late'] }} days ({{ $attStat['late_penalty_days'] }} penalty days)">Late: {{ $attStat['late'] }}</span>
                            @endif
                            @if($attStat['effective_absent'] > 0)
                            <span class="badge bg-danger-subtle text-danger border border-danger-subtle px-1 py-0.5" title="Effective Absent: {{ $attStat['effective_absent'] }} days (Base: {{ $attStat['absent'] }}, Penalty: {{ $attStat['late_penalty_days'] }})">Eff. Abs: {{ $attStat['effective_absent'] }}d</span>
                            @endif
                        </div>
                        @endif
                    </td>
                    <td class="text-muted">{{ $p->employee->department ?? '—' }}</td>
                    <td class="text-end">{{ number_format($p->basic_salary, 2) }}</td>
                    <td class="text-end text-info">{{ number_format($p->transport_allowance ?? 0, 2) }}</td>
                    <td class="text-end text-info">{{ number_format($p->house_allowance ?? 0, 2) }}</td>
                    <td class="text-end text-info">{{ number_format($p->position_allowance ?? 0, 2) }}</td>
                    <td class="text-end fw-semibold text-primary">{{ number_format($p->gross_salary ?? ($p->basic_salary + $p->allowances + $p->overtime_pay), 2) }}</td>
                    <td class="text-end fw-semibold text-dark">{{ number_format($calcTaxable, 2) }}</td>
                    <td class="text-end text-secondary">{{ number_format($p->pension ?? round($p->basic_salary * 0.07, 2), 2) }}</td>
                    <td class="text-end" style="color:#6f42c1;">{{ number_format($calcCoPension, 2) }}</td>
                    <td class="text-end text-warning">{{ number_format($p->tax, 2) }}</td>
                    <td class="text-end text-danger">
                        <div class="fw-semibold">{{ number_format($p->deductions, 2) }}</div>
                        @if(($p->loan_deduction ?? 0) > 0 || ($p->absence_deduction ?? 0) > 0 || ($p->absent_days ?? 0) > 0)
                        <div class="text-muted" style="font-size:.7rem; line-height: 1.15;">
                            @if(($p->loan_deduction ?? 0) > 0)
                                <span title="Salary Advance Loan Repayment" class="d-block text-warning"><i class="fa-solid fa-hand-holding-dollar me-1"></i>Loan: {{ number_format($p->loan_deduction, 2) }}</span>
                            @endif
                            @if(($p->absence_deduction ?? 0) > 0 || ($p->absent_days ?? 0) > 0)
                                <a href="#" class="d-block text-danger text-decoration-none mt-0.5" data-bs-toggle="modal" data-bs-target="#absenceDetailModal_{{ $p->id }}" title="Click to view absence days breakdown formatted in Ethiopian attendance day format">
                                    <i class="fa-solid fa-calendar-xmark me-1"></i>Absent ({{ $p->absent_days }}d): {{ number_format($p->absence_deduction, 2) }} <i class="fa-solid fa-circle-info ms-0.5 small opacity-75"></i>
                                </a>
                            @endif
                        </div>
                        @endif
                    </td>
                    <td class="text-end fw-bold text-success">{{ number_format($p->net_salary, 2) }}</td>
                    <td class="text-center">
                        @if($p->gm_status === 'approved')
                            <span class="badge bg-success-subtle text-success rounded-pill">Approved</span>
                        @elseif($p->gm_status === 'submitted')
                            <span class="badge bg-warning-subtle text-warning rounded-pill">Pending GM</span>
                        @elseif($p->gm_status === 'rejected')
                            <span class="badge bg-danger-subtle text-danger rounded-pill">Rejected</span>
                        @elseif($p->status === 'paid')
                            <span class="badge bg-success rounded-pill">Paid</span>
                        @else
                            <span class="badge bg-secondary-subtle text-secondary rounded-pill">Draft</span>
                        @endif
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="15" class="text-center py-5 text-muted">
                        <i class="fa-solid fa-file-circle-xmark fa-2x mb-2 d-block opacity-25"></i>
                        No payroll entries for {{ $periodTitle }}.<br>
                        <small>Click <strong>Auto-Generate Payroll</strong> to create entries from employee records.</small>
                    </td>
                </tr>
                @endforelse
            </tbody>
            @if($payrolls->isNotEmpty())
            <tfoot class="bg-light fw-bold">
                <tr>
                    <td colspan="3" class="ps-4">TOTALS</td>
                    <td class="text-end">{{ number_format($totals['basic'],2) }}</td>
                    <td class="text-end text-info">{{ number_format($totals['transport'],2) }}</td>
                    <td class="text-end text-info">{{ number_format($totals['house'],2) }}</td>
                    <td class="text-end text-info">{{ number_format($totals['position'],2) }}</td>
                    <td class="text-end text-primary">{{ number_format($totals['gross'],2) }}</td>
                    <td class="text-end text-dark">{{ number_format($totals['taxable_income'],2) }}</td>
                    <td class="text-end text-secondary">{{ number_format($totals['pension'],2) }}</td>
                    <td class="text-end" style="color:#6f42c1;">{{ number_format($totals['company_pension'],2) }}</td>
                    <td class="text-end text-warning">{{ number_format($totals['tax'],2) }}</td>
                    <td class="text-end text-danger">
                        <div>{{ number_format($totals['deductions'],2) }}</div>
                        @if(($totals['loan_deductions'] ?? 0) > 0 || ($totals['absence_deductions'] ?? 0) > 0)
                        <div style="font-size:.68rem; font-weight:normal; line-height:1.15;">
                            @if(($totals['loan_deductions'] ?? 0) > 0)
                                <span class="d-block text-warning">Loans: {{ number_format($totals['loan_deductions'], 2) }}</span>
                            @endif
                            @if(($totals['absence_deductions'] ?? 0) > 0)
                                <span class="d-block text-danger">Absence ({{ $totals['absent_days'] }}d): {{ number_format($totals['absence_deductions'], 2) }}</span>
                            @endif
                        </div>
                        @endif
                    </td>
                    <td class="text-end text-success">{{ number_format($totals['net'],2) }}</td>
                    <td></td>
                </tr>
            </tfoot>
            @endif
        </table>
    </div>
</div>

</div>{{-- /container --}}

{{-- ── Itemized Absence Breakdown Modals (Formatted in Ethiopian Attendance Day Format) ── --}}
@foreach($payrolls as $p)
@if(($p->absence_deduction ?? 0) > 0 || ($p->absent_days ?? 0) > 0)
@php
    $attStat = $attendanceStats[$p->employee_id] ?? null;
    $detailedList = $attStat['detailed_absences'] ?? [];
    $dailyRate = $p->basic_salary > 0 ? ($p->basic_salary / 30) : 0;
@endphp
<div class="modal fade" id="absenceDetailModal_{{ $p->id }}" tabindex="-1" aria-labelledby="absenceDetailModalLabel_{{ $p->id }}" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-4 border-0 shadow">
            <div class="modal-header border-bottom py-3">
                <h6 class="modal-title fw-bold" id="absenceDetailModalLabel_{{ $p->id }}">
                    <i class="fa-solid fa-calendar-xmark text-danger me-2"></i>Absence Deduction Breakdown
                </h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-3">
                <div class="bg-light rounded-3 p-3 mb-3">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <span class="text-muted small">Employee</span>
                        <strong>{{ $p->employee->full_name ?? 'N/A' }} ({{ $p->employee->employee_code ?? '' }})</strong>
                    </div>
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <span class="text-muted small">Attendance Window</span>
                        <span class="badge bg-white text-dark border">{{ $attendanceDayWindow }}</span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <span class="text-muted small">Basic Salary</span>
                        <span>{{ number_format($p->basic_salary, 2) }} ETB</span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <span class="text-muted small">Daily Deduction Rate (Basic / 30)</span>
                        <span class="font-monospace fw-semibold">{{ number_format($dailyRate, 2) }} ETB / day</span>
                    </div>
                    <hr class="my-2">
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="fw-bold">Total Absence Deduction</span>
                        <span class="fw-bold text-danger fs-6">{{ number_format($p->absence_deduction, 2) }} ETB ({{ $p->absent_days }} days)</span>
                    </div>
                </div>

                <h6 class="fw-bold small text-dark mb-2">
                    <i class="fa-solid fa-list-ol text-primary me-1"></i>Unexcused Days &amp; Late Penalties (Attendance Day Format):
                </h6>
                @if(!empty($detailedList))
                    <ul class="list-group list-group-flush border rounded-3 mb-3 small">
                        @foreach($detailedList as $item)
                        <li class="list-group-item d-flex justify-content-between align-items-center py-2 px-3">
                            <div class="d-flex align-items-center gap-2">
                                @if(($item['type'] ?? '') === 'penalty')
                                    <i class="fa-solid fa-clock-rotate-left text-warning"></i>
                                    <span>{{ $item['label'] }}</span>
                                @else
                                    <i class="fa-solid fa-circle-xmark text-danger"></i>
                                    <span>{{ $item['label'] }}</span>
                                @endif
                            </div>
                            <span class="badge bg-danger-subtle text-danger font-monospace px-1.5 py-1">
                                @if(($item['type'] ?? '') === 'penalty') Penalty @else -1 day @endif
                            </span>
                        </li>
                        @endforeach
                    </ul>
                @else
                    <div class="alert alert-light border small text-muted mb-3">
                        {{ $p->absent_days }} unexcused absent days deducted during this period.
                    </div>
                @endif

                <div class="d-flex justify-content-between align-items-center">
                    <small class="text-muted">Rule: Official work hours, biometric punches, 3 Lates = 1 Absent penalty.</small>
                    <a href="{{ route('attendance.index', ['period' => $selectedPeriodKey, 'search' => $p->employee->employee_code ?: $p->employee->full_name]) }}" target="_blank" class="btn btn-xs btn-outline-primary rounded-pill px-2 py-1 shadow-xs">
                        <i class="fa-solid fa-fingerprint me-1"></i>Audit in Attendance Section &rarr;
                    </a>
                </div>
            </div>
            <div class="modal-footer border-top py-2">
                <button type="button" class="btn btn-sm btn-secondary rounded-pill px-3" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>
@endif
@endforeach

{{-- ── Submit to GM Modal ───────────────────────────────────────────────── --}}
<div class="modal fade" id="submitGMModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" action="{{ route('finance.payroll.submit-gm') }}" class="modal-content rounded-4 border-0 shadow">
            @csrf
            <input type="hidden" name="period" value="{{ $selectedPeriodKey }}">
            <input type="hidden" name="month"  value="{{ $month }}">
            <input type="hidden" name="year"   value="{{ $year }}">
            <div class="modal-header border-bottom">
                <h5 class="modal-title fw-bold">
                    <i class="fa-solid fa-paper-plane text-warning me-2"></i>Send to GM for Approval
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-warning border-0 rounded-3">
                    <i class="fa-solid fa-triangle-exclamation me-2"></i>
                    You are about to send <strong>{{ $payrolls->count() }} payroll entries</strong> for
                    <strong>{{ $periodTitle }}</strong> to the <strong>General Manager</strong> for approval.
                </div>
                <div class="bg-light rounded-3 p-3 mb-3">
                    <div class="d-flex justify-content-between mb-1">
                        <span class="text-muted small">Attendance Window</span>
                        <span class="fw-semibold">{{ $attendanceDayWindow }}</span>
                    </div>
                    <div class="d-flex justify-content-between mb-1">
                        <span class="text-muted small">Total Gross Pay</span>
                        <span class="fw-semibold">{{ number_format($totals['gross'],2) }} ETB</span>
                    </div>
                    <div class="d-flex justify-content-between mb-1">
                        <span class="text-muted small">Total Deductions</span>
                        <span class="fw-semibold text-danger">{{ number_format($totals['pension']+$totals['tax']+$totals['deductions'],2) }} ETB</span>
                    </div>
                    <hr class="my-2">
                    <div class="d-flex justify-content-between">
                        <span class="fw-bold">Net Pay to Disburse</span>
                        <span class="fw-bold text-success fs-5">{{ number_format($totals['net'],2) }} ETB</span>
                    </div>
                </div>
                <p class="text-muted small mb-0">
                    <i class="fa-solid fa-info-circle me-1 text-info"></i>
                    The GM will receive a notification and must approve before payment can be processed.
                </p>
            </div>
            <div class="modal-footer border-top">
                <button type="button" class="btn btn-outline-secondary rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-warning rounded-pill px-4">
                    <i class="fa-solid fa-paper-plane me-1"></i>Send to GM
                </button>
            </div>
        </form>
    </div>
</div>

<style>
@media print {
    .navbar, .sidebar, .btn, .modal, .alert, .card-header a, form { display:none!important; }
    .card { box-shadow:none!important; border:1px solid #dee2e6!important; }
}
</style>
@endsection
