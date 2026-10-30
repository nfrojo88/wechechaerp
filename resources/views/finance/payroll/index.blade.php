@extends('layouts.app')
@section('title', 'Payroll Management — Finance Head')

@section('content')
@php
    $periodTitle = ($periodData['full_label'] ?? "Month {$month}") . " (" . ($periodData['label_en'] ?? "EC") . ")";
    $batchApproved  = $payrolls->where('gm_status','approved')->count() === $payrolls->count() && $payrolls->count() > 0;
    $batchSubmitted = $payrolls->where('gm_status','submitted')->count() > 0;
    $batchRejected  = $payrolls->where('gm_status','rejected')->count()  > 0;
@endphp

<div class="container-fluid px-0">

    {{-- Page Header --}}
    <x-page-header
        title="Payroll Management"
        subtitle="Generate, audit, and submit monthly payroll for GM approval, synced with Ethiopian biometric attendance periods (26th–25th)."
        :breadcrumbs="[
            ['label' => 'Dashboard', 'url' => route('dashboard')],
            ['label' => 'Finance', 'url' => route('dashboard.finance')],
            ['label' => 'Payroll Management']
        ]">
        <x-slot:actions>
            {{-- Ethiopian Attendance Payroll Period Selector --}}
            <form method="GET" action="{{ route('finance.payroll.index') }}" class="d-flex flex-wrap gap-2 align-items-center">
                <div class="input-group input-group-sm" style="min-width: 260px;" title="Ethiopian Attendance Payroll Period (26th–25th)">
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
                <button type="submit" class="btn btn-sm btn-primary rounded-pill px-3 shadow-xs">
                    <i class="fa-solid fa-filter me-1"></i>Filter
                </button>
            </form>

            {{-- Direct Links --}}
            <a href="{{ route('attendance.index', ['period' => $selectedPeriodKey]) }}" target="_blank" class="btn btn-sm btn-outline-primary shadow-xs rounded-pill px-3" title="Open verified attendance matrix">
                <i class="fa-solid fa-fingerprint me-1.5 text-primary"></i>Attendance Matrix
            </a>
            <a href="{{ route('attendance.export-pdf', ['period' => $selectedPeriodKey]) }}" target="_blank" class="btn btn-sm btn-outline-danger shadow-xs rounded-pill px-2.5" title="Export Clock In/Out matrix as PDF">
                <i class="fa-solid fa-file-pdf me-1"></i>PDF
            </a>
        </x-slot:actions>
    </x-page-header>

    {{-- System Flash Alerts --}}
    @if(session('success'))
        <div class="alert alert-success border-0 shadow-sm rounded-4 mb-4 d-flex align-items-center gap-3 p-3 bg-success bg-opacity-10 border-start border-4 border-success">
            <div class="p-2 bg-success text-white rounded-circle fs-5 flex-shrink-0">
                <i class="fa-solid fa-circle-check"></i>
            </div>
            <div class="flex-grow-1 text-dark fw-medium">{{ session('success') }}</div>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger border-0 shadow-sm rounded-4 mb-4 d-flex align-items-center gap-3 p-3 bg-danger bg-opacity-10 border-start border-4 border-danger">
            <div class="p-2 bg-danger text-white rounded-circle fs-5 flex-shrink-0">
                <i class="fa-solid fa-triangle-exclamation"></i>
            </div>
            <div class="flex-grow-1 text-dark fw-medium">{{ session('error') }}</div>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    {{-- Period & GM Status Banner --}}
    <div class="card border-0 shadow-sm rounded-4 mb-4 overflow-hidden
        @if($batchApproved) border-start border-4 border-success
        @elseif($batchRejected) border-start border-4 border-danger
        @elseif($batchSubmitted) border-start border-4 border-warning
        @else border-start border-4 border-info @endif bg-white">
        <div class="card-body p-3 p-md-4">
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
                <div class="d-flex align-items-center flex-wrap gap-2">
                    <span class="fs-5 fw-bold text-dark"><i class="fa-solid fa-calendar-days text-primary me-2"></i>{{ $periodTitle }} Payroll</span>
                    <span class="badge bg-light text-dark border px-2.5 py-1.5 small">
                        <i class="fa-solid fa-calendar-check text-success me-1"></i>Attendance Window: 
                        <strong class="text-primary">{{ $attendanceDayWindow }}</strong>
                        <span class="text-muted ms-1">({{ \Carbon\Carbon::parse($startDate)->format('M d, Y') }} &ndash; {{ \Carbon\Carbon::parse($endDate)->format('M d, Y') }})</span>
                    </span>
                    <span class="text-muted">—</span>
                    @if($batchApproved)
                        <span class="badge bg-success rounded-pill px-3 py-1.5"><i class="fa-solid fa-check me-1"></i>GM Approved</span>
                    @elseif($batchRejected)
                        <span class="badge bg-danger rounded-pill px-3 py-1.5"><i class="fa-solid fa-xmark me-1"></i>GM Rejected — Revision Needed</span>
                    @elseif($batchSubmitted)
                        <span class="badge bg-warning text-dark rounded-pill px-3 py-1.5"><i class="fa-solid fa-clock me-1"></i>Awaiting GM Approval</span>
                    @elseif($payrolls->isEmpty())
                        <span class="badge bg-secondary rounded-pill px-3 py-1.5">No payroll generated yet</span>
                    @else
                        <span class="badge bg-info text-dark rounded-pill px-3 py-1.5">Draft — Not submitted</span>
                    @endif
                </div>

                <div class="d-flex gap-2 flex-wrap">
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
                            <button type="submit" class="btn btn-primary btn-sm rounded-pill px-3 py-2 shadow-sm fw-semibold"
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
                            <button type="submit" class="btn btn-outline-primary btn-sm rounded-pill px-3 py-2 shadow-sm fw-semibold"
                                    onclick="return confirm('Recalculate payroll for {{ $periodTitle }} (Attendance Window: {{ $attendanceDayWindow }})? This will refresh all attendance records and loan deductions.')">
                                <i class="fa-solid fa-arrows-rotate me-1"></i>Recalculate / Sync
                            </button>
                        </form>
                        <button class="btn btn-warning btn-sm rounded-pill px-3 py-2 shadow-sm text-dark fw-bold" data-bs-toggle="modal" data-bs-target="#submitGMModal">
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
                            <button type="submit" class="btn btn-outline-primary btn-sm rounded-pill px-3 py-2 shadow-sm fw-semibold"
                                    onclick="return confirm('Recalculate payroll for {{ $periodTitle }} (Attendance Window: {{ $attendanceDayWindow }})?')">
                                <i class="fa-solid fa-arrows-rotate me-1"></i>Recalculate / Sync
                            </button>
                        </form>
                        <button class="btn btn-warning btn-sm rounded-pill px-3 py-2 shadow-sm text-dark fw-bold" data-bs-toggle="modal" data-bs-target="#submitGMModal">
                            <i class="fa-solid fa-paper-plane me-1"></i>Re-Submit to GM
                        </button>
                    @endif
                </div>
            </div>

            @if($batchRejected)
                @php $rejection = $payrolls->where('gm_status','rejected')->first(); @endphp
                @if($rejection && $rejection->gm_notes)
                    <div class="mt-3 p-3 bg-danger bg-opacity-10 rounded-3 text-danger small">
                        <strong><i class="fa-solid fa-circle-exclamation me-1"></i>GM Rejection Notes:</strong> {{ $rejection->gm_notes }}
                    </div>
                @endif
            @endif
        </div>
    </div>

    {{-- Summary KPI Cards --}}
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3 col-xl">
            <x-kpi-card
                title="Employees"
                :value="number_format($totals['count'])"
                changeLabel="On payroll sheet"
                icon="fa-solid fa-users"
                color="primary" />
        </div>
        <div class="col-6 col-md-3 col-xl">
            <x-kpi-card
                title="Total Basic"
                :value="'ETB ' . number_format($totals['basic'], 2)"
                changeLabel="Base salary sum"
                icon="fa-solid fa-money-bill"
                color="dark" />
        </div>
        <div class="col-6 col-md-3 col-xl">
            <x-kpi-card
                title="Total Allowances"
                :value="'ETB ' . number_format(($totals['transport'] ?? 0) + ($totals['house'] ?? 0) + ($totals['position'] ?? 0), 2)"
                changeLabel="Transport, house, role"
                icon="fa-solid fa-hand-holding-dollar"
                color="info" />
        </div>
        <div class="col-6 col-md-3 col-xl">
            <x-kpi-card
                title="Taxable Income"
                :value="'ETB ' . number_format($totals['taxable_income'], 2)"
                changeLabel="After standard exemptions"
                icon="fa-solid fa-calculator"
                color="primary" />
        </div>
        <div class="col-6 col-md-3 col-xl">
            <x-kpi-card
                title="Pension (Emp 7%)"
                :value="'ETB ' . number_format($totals['pension'], 2)"
                changeLabel="Employee deduction"
                icon="fa-solid fa-vault"
                color="secondary" />
        </div>
        <div class="col-6 col-md-3 col-xl">
            <x-kpi-card
                title="Co. Pension (11%)"
                :value="'ETB ' . number_format($totals['company_pension'], 2)"
                changeLabel="Employer contribution"
                icon="fa-solid fa-landmark"
                color="purple" />
        </div>
        <div class="col-6 col-md-3 col-xl">
            <x-kpi-card
                title="Total Tax"
                :value="'ETB ' . number_format($totals['tax'], 2)"
                changeLabel="Income tax to ERCA"
                icon="fa-solid fa-receipt"
                color="warning" />
        </div>
        <div class="col-6 col-md-3 col-xl">
            <x-kpi-card
                title="Total Net Pay"
                :value="'ETB ' . number_format($totals['net'], 2)"
                changeLabel="Disbursement amount"
                icon="fa-solid fa-circle-check"
                color="success" />
        </div>
    </div>

    {{-- Payroll Sheet Table --}}
    <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4 wc-printable-area">
        <div class="card-header bg-white py-3 px-4 border-bottom d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <h6 class="fw-bold mb-0 text-dark"><i class="fa-solid fa-table-list text-primary me-2"></i>{{ $periodTitle }} Payroll Sheet</h6>
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
                        <button type="submit" class="btn btn-sm btn-outline-primary rounded-pill px-3 shadow-xs"
                                onclick="return confirm('Recalculate payroll for {{ $periodTitle }} (Attendance Window: {{ $attendanceDayWindow }})?')">
                            <i class="fa-solid fa-arrows-rotate me-1"></i>Recalculate
                        </button>
                    </form>
                @endif
                @if($payrolls->isNotEmpty())
                    <button type="button" onclick="window.print()" class="btn btn-sm btn-outline-secondary rounded-pill px-3 shadow-xs">
                        <i class="fa-solid fa-print me-1"></i>Print Sheet
                    </button>
                @endif
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 small wc-table-mobile-cards">
                <thead class="table-light">
                    <tr>
                        <th class="ps-4 text-secondary text-uppercase fw-bold" style="width: 40px;">#</th>
                        <th class="text-secondary text-uppercase fw-bold" style="min-width: 220px;">Employee &amp; Attendance</th>
                        <th class="text-secondary text-uppercase fw-bold">Department</th>
                        <th class="text-end text-secondary text-uppercase fw-bold">Basic Salary</th>
                        <th class="text-end text-secondary text-uppercase fw-bold text-info">Allowances</th>
                        <th class="text-end text-secondary text-uppercase fw-bold text-primary">Gross</th>
                        <th class="text-end text-secondary text-uppercase fw-bold text-dark">Taxable</th>
                        <th class="text-end text-secondary text-uppercase fw-bold">Pension (7%)</th>
                        <th class="text-end text-secondary text-uppercase fw-bold" style="color: #6f42c1;">Co. Pen (11%)</th>
                        <th class="text-end text-secondary text-uppercase fw-bold text-warning">Tax</th>
                        <th class="text-end text-secondary text-uppercase fw-bold text-danger" style="min-width: 135px;">Other Ded.</th>
                        <th class="text-end text-secondary text-uppercase fw-bold text-success">Net Pay</th>
                        <th class="pe-4 text-center text-secondary text-uppercase fw-bold">Status</th>
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
                            $allowancesSum = ($p->transport_allowance ?? 0) + ($p->house_allowance ?? 0) + ($p->position_allowance ?? 0);
                        @endphp
                        <tr>
                            <td class="ps-4 text-muted font-monospace" data-label="#">{{ $i + 1 }}</td>
                            <td data-label="Employee">
                                <div class="d-flex align-items-center justify-content-between gap-1">
                                    <div class="fw-semibold text-dark">{{ $p->employee->full_name ?? 'N/A' }}</div>
                                    <a href="{{ route('attendance.index', ['period' => $selectedPeriodKey, 'search' => $p->employee->employee_code ?: $p->employee->full_name]) }}" target="_blank" class="badge bg-primary-subtle text-primary border border-primary-subtle text-decoration-none" title="Audit verified punches in Attendance Section">
                                        <i class="fa-solid fa-fingerprint me-0.5"></i>Audit &rarr;
                                    </a>
                                </div>
                                <div class="text-muted" style="font-size: 0.72rem;">{{ $p->employee->employee_code ?? '' }}</div>
                                {{-- Attendance Summary Badges --}}
                                @if($attStat)
                                    <div class="d-flex align-items-center gap-1 mt-1 flex-wrap font-monospace" style="font-size: 0.68rem;">
                                        <span class="badge bg-success-subtle text-success border border-success-subtle px-1 py-0.5" title="Present: {{ $attStat['present'] }} days">P: {{ $attStat['present'] }}</span>
                                        @if($attStat['site'] > 0)
                                            <span class="badge bg-info-subtle text-info border border-info-subtle px-1 py-0.5" title="Site: {{ $attStat['site'] }} days">S: {{ $attStat['site'] }}</span>
                                        @endif
                                        @if($attStat['leave'] > 0)
                                            <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-1 py-0.5" title="Leave: {{ $attStat['leave'] }} days">L: {{ $attStat['leave'] }}</span>
                                        @endif
                                        @if($attStat['late'] > 0)
                                            <span class="badge bg-warning-subtle text-warning border border-warning-subtle px-1 py-0.5" title="Late: {{ $attStat['late'] }} days">Late: {{ $attStat['late'] }}</span>
                                        @endif
                                        @if($attStat['effective_absent'] > 0)
                                            <span class="badge bg-danger-subtle text-danger border border-danger-subtle px-1 py-0.5" title="Effective Absent: {{ $attStat['effective_absent'] }} days">Abs: {{ $attStat['effective_absent'] }}d</span>
                                        @endif
                                    </div>
                                @endif
                            </td>
                            <td class="text-muted" data-label="Department">{{ $p->employee->department ?? '—' }}</td>
                            <td class="text-end font-tabular fw-semibold text-dark" data-label="Basic Salary">{{ number_format($p->basic_salary, 2) }}</td>
                            <td class="text-end font-tabular text-info" data-label="Allowances">{{ number_format($allowancesSum, 2) }}</td>
                            <td class="text-end font-tabular fw-semibold text-primary" data-label="Gross Pay">{{ number_format($p->gross_salary ?? ($p->basic_salary + $p->allowances + $p->overtime_pay), 2) }}</td>
                            <td class="text-end font-tabular fw-semibold text-dark" data-label="Taxable">{{ number_format($calcTaxable, 2) }}</td>
                            <td class="text-end font-tabular text-secondary" data-label="Pension 7%">{{ number_format($p->pension ?? round($p->basic_salary * 0.07, 2), 2) }}</td>
                            <td class="text-end font-tabular" style="color: #6f42c1;" data-label="Co Pen 11%">{{ number_format($calcCoPension, 2) }}</td>
                            <td class="text-end font-tabular text-warning fw-semibold" data-label="Tax">{{ number_format($p->tax, 2) }}</td>
                            <td class="text-end font-tabular text-danger" data-label="Deductions">
                                <div class="fw-semibold">{{ number_format($p->deductions, 2) }}</div>
                                @if(($p->loan_deduction ?? 0) > 0 || ($p->absence_deduction ?? 0) > 0 || ($p->absent_days ?? 0) > 0)
                                    <div class="text-muted" style="font-size: 0.68rem; line-height: 1.15;">
                                        @if(($p->loan_deduction ?? 0) > 0)
                                            <span title="Salary Advance Loan Repayment" class="d-block text-warning"><i class="fa-solid fa-hand-holding-dollar me-0.5"></i>Loan: {{ number_format($p->loan_deduction, 2) }}</span>
                                        @endif
                                        @if(($p->absence_deduction ?? 0) > 0 || ($p->absent_days ?? 0) > 0)
                                            <a href="#" class="d-block text-danger text-decoration-none mt-0.5" data-bs-toggle="modal" data-bs-target="#absenceDetailModal_{{ $p->id }}" title="Click to view absence days breakdown">
                                                <i class="fa-solid fa-calendar-xmark me-0.5"></i>Absent ({{ $p->absent_days }}d): {{ number_format($p->absence_deduction, 2) }} <i class="fa-solid fa-circle-info ms-0.5 small opacity-75"></i>
                                            </a>
                                        @endif
                                    </div>
                                @endif
                            </td>
                            <td class="text-end font-tabular fw-bold text-success fs-6" data-label="Net Pay">{{ number_format($p->net_salary, 2) }}</td>
                            <td class="pe-4 text-center" data-label="Status">
                                @if($p->gm_status === 'approved')
                                    <span class="badge bg-success bg-opacity-10 text-success rounded-pill px-2.5 py-1">Approved</span>
                                @elseif($p->gm_status === 'submitted')
                                    <span class="badge bg-warning bg-opacity-10 text-warning rounded-pill px-2.5 py-1">Pending GM</span>
                                @elseif($p->gm_status === 'rejected')
                                    <span class="badge bg-danger bg-opacity-10 text-danger rounded-pill px-2.5 py-1">Rejected</span>
                                @elseif($p->status === 'paid')
                                    <span class="badge bg-success rounded-pill px-2.5 py-1">Paid</span>
                                @else
                                    <span class="badge bg-secondary bg-opacity-10 text-secondary rounded-pill px-2.5 py-1">Draft</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="13" class="text-center py-5 text-muted">
                                <x-empty-state
                                    title="No Payroll Generated Yet"
                                    description="No employee payroll records exist for {{ $periodTitle }}. Click Auto-Generate Payroll above to create the batch from attendance and contract files."
                                    icon="fa-solid fa-money-bill-wave" />
                            </td>
                        </tr>
                    @endforelse
                </tbody>
                @if($payrolls->isNotEmpty())
                    <tfoot class="bg-light fw-bold border-top border-2">
                        <tr>
                            <td colspan="3" class="ps-4 py-3 text-uppercase text-secondary align-middle">TOTALS</td>
                            <td class="text-end py-3 font-tabular text-dark align-middle">{{ number_format($totals['basic'], 2) }}</td>
                            <td class="text-end py-3 font-tabular text-info align-middle">{{ number_format(($totals['transport'] ?? 0) + ($totals['house'] ?? 0) + ($totals['position'] ?? 0), 2) }}</td>
                            <td class="text-end py-3 font-tabular text-primary align-middle">{{ number_format($totals['gross'], 2) }}</td>
                            <td class="text-end py-3 font-tabular text-dark align-middle">{{ number_format($totals['taxable_income'], 2) }}</td>
                            <td class="text-end py-3 font-tabular text-secondary align-middle">{{ number_format($totals['pension'], 2) }}</td>
                            <td class="text-end py-3 font-tabular align-middle" style="color: #6f42c1;">{{ number_format($totals['company_pension'], 2) }}</td>
                            <td class="text-end py-3 font-tabular text-warning align-middle">{{ number_format($totals['tax'], 2) }}</td>
                            <td class="text-end py-3 font-tabular text-danger align-middle">
                                <div>{{ number_format($totals['deductions'], 2) }}</div>
                                @if(($totals['loan_deductions'] ?? 0) > 0 || ($totals['absence_deductions'] ?? 0) > 0)
                                    <div style="font-size: 0.68rem; font-weight: normal; line-height: 1.15;">
                                        @if(($totals['loan_deductions'] ?? 0) > 0)
                                            <span class="d-block text-warning">Loans: {{ number_format($totals['loan_deductions'], 2) }}</span>
                                        @endif
                                        @if(($totals['absence_deductions'] ?? 0) > 0)
                                            <span class="d-block text-danger">Absence: {{ number_format($totals['absence_deductions'], 2) }}</span>
                                        @endif
                                    </div>
                                @endif
                            </td>
                            <td class="text-end py-3 font-tabular text-success fs-6 align-middle" style="border-bottom: 3px double #10b981;">
                                <span class="small text-muted me-1">ETB</span>{{ number_format($totals['net'], 2) }}
                            </td>
                            <td></td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
    </div>
</div>

{{-- ── Itemized Absence Breakdown Modals ── --}}
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
                            <i class="fa-solid fa-list-ol text-primary me-1"></i>Unexcused Days &amp; Late Penalties:
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
                            <small class="text-muted">Rule: Biometric punches, 3 Lates = 1 Absent penalty.</small>
                            <a href="{{ route('attendance.index', ['period' => $selectedPeriodKey, 'search' => $p->employee->employee_code ?: $p->employee->full_name]) }}" target="_blank" class="btn btn-xs btn-outline-primary rounded-pill px-2 py-1 shadow-xs">
                                <i class="fa-solid fa-fingerprint me-1"></i>Audit Punches &rarr;
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

{{-- ── Submit to GM Modal ── --}}
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
                    The GM will receive an executive notification and must approve before disbursement.
                </p>
            </div>
            <div class="modal-footer border-top">
                <button type="button" class="btn btn-outline-secondary rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-warning rounded-pill px-4 fw-semibold text-dark">
                    <i class="fa-solid fa-paper-plane me-1"></i>Send to GM
                </button>
            </div>
        </form>
    </div>
</div>

<style>
@media print {
    body {
        background: #ffffff !important;
    }
    .navbar, .sidebar, .wc-bottom-nav, .btn, .modal, .alert, .card-header a, form {
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
