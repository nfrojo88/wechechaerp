@extends('layouts.app')
@section('title', 'Payroll Management — Finance Head')

@section('content')
<div class="container-fluid px-4 py-3">

{{-- ── Header ────────────────────────────────────────────────────────────── --}}
<div class="d-flex align-items-center justify-content-between mb-4">
    <div>
        <h1 class="h3 fw-bold mb-1">
            <i class="fa-solid fa-money-bill-wave text-success me-2"></i>Payroll Management
        </h1>
        <p class="text-muted small mb-0">Generate, review, and submit monthly payroll for GM approval.</p>
    </div>
    <div class="d-flex gap-2">
        {{-- Period Selector --}}
        <form method="GET" action="{{ route('finance.payroll.index') }}" class="d-flex gap-2 align-items-center">
            <select name="month" class="form-select form-select-sm" style="width:130px">
                @for($m=1;$m<=12;$m++)
                    <option value="{{ $m }}" {{ $month==$m?'selected':'' }}>{{ date('F',mktime(0,0,0,$m,1)) }}</option>
                @endfor
            </select>
            <input type="number" name="year" class="form-control form-control-sm" value="{{ $year }}" style="width:90px" min="2020" max="2099">
            <button class="btn btn-sm btn-outline-primary px-3"><i class="fa-solid fa-filter me-1"></i>Filter</button>
        </form>
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
    $period = date('F Y', mktime(0,0,0,$month,1,$year));
    $batchApproved  = $payrolls->where('gm_status','approved')->count()  === $payrolls->count() && $payrolls->count() > 0;
    $batchSubmitted = $payrolls->where('gm_status','submitted')->count() > 0;
    $batchRejected  = $payrolls->where('gm_status','rejected')->count()  > 0;
@endphp

<div class="alert border-0 rounded-3 shadow-sm mb-4
    @if($batchApproved) alert-success
    @elseif($batchRejected) alert-danger
    @elseif($batchSubmitted) alert-warning
    @else alert-info @endif" role="alert">
    <div class="d-flex align-items-center justify-content-between">
        <div>
            <strong><i class="fa-solid fa-calendar-days me-2"></i>{{ $period }} Payroll</strong>
            &nbsp;—&nbsp;
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
                <input type="hidden" name="month" value="{{ $month }}">
                <input type="hidden" name="year"  value="{{ $year }}">
                <button type="submit" class="btn btn-primary btn-sm rounded-pill px-3"
                        onclick="return confirm('Auto-generate payroll for all active employees for {{ $period }}?')">
                    <i class="fa-solid fa-bolt me-1"></i>Auto-Generate Payroll
                </button>
            </form>
            @elseif(!$batchSubmitted && !$batchApproved)
            {{-- Recalculate / Sync Button --}}
            <form method="POST" action="{{ url('/finance/payroll/recalculate') }}">
                @csrf
                <input type="hidden" name="month" value="{{ $month }}">
                <input type="hidden" name="year"  value="{{ $year }}">
                <button type="submit" class="btn btn-outline-primary btn-sm rounded-pill px-3"
                        onclick="return confirm('Recalculate payroll for {{ $period }}? This will refresh all attendance records (absent days cut = Basic/30, overtime) and salary advance loan payments.')">
                    <i class="fa-solid fa-arrows-rotate me-1"></i>Recalculate / Sync
                </button>
            </form>
            <button class="btn btn-warning btn-sm rounded-pill px-3" data-bs-toggle="modal" data-bs-target="#submitGMModal">
                <i class="fa-solid fa-paper-plane me-1"></i>Send to GM for Approval
            </button>
            @elseif($batchRejected)
            {{-- Recalculate Button on Rejected --}}
            <form method="POST" action="{{ url('/finance/payroll/recalculate') }}">
                @csrf
                <input type="hidden" name="month" value="{{ $month }}">
                <input type="hidden" name="year"  value="{{ $year }}">
                <button type="submit" class="btn btn-outline-primary btn-sm rounded-pill px-3"
                        onclick="return confirm('Recalculate payroll for {{ $period }}?')">
                    <i class="fa-solid fa-arrows-rotate me-1"></i>Recalculate / Sync
                </button>
            </form>
            <button class="btn btn-warning btn-sm rounded-pill px-3" data-bs-toggle="modal" data-bs-target="#submitGMModal">
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
<div class="card border-0 shadow-sm rounded-4 overflow-hidden">
    <div class="card-header bg-white py-3 px-4 border-bottom d-flex align-items-center justify-content-between">
        <h6 class="fw-bold mb-0"><i class="fa-solid fa-table-list text-primary me-2"></i>{{ $period }} Payroll Sheet</h6>
        <div class="d-flex gap-2 align-items-center">
            @if($payrolls->isNotEmpty() && !$batchApproved && !$batchSubmitted)
            <form method="POST" action="{{ url('/finance/payroll/recalculate') }}">
                @csrf
                <input type="hidden" name="month" value="{{ $month }}">
                <input type="hidden" name="year"  value="{{ $year }}">
                <button type="submit" class="btn btn-sm btn-outline-primary rounded-pill">
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
                    <th>Employee</th>
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
                    <th class="text-end text-danger">Other Ded.</th>
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
                @endphp
                <tr>
                    <td class="ps-4 text-muted">{{ $i + 1 }}</td>
                    <td>
                        <div class="fw-semibold">{{ $p->employee->full_name ?? 'N/A' }}</div>
                        <div class="text-muted" style="font-size:.75rem">{{ $p->employee->employee_code ?? '' }}</div>
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
                        @if(($p->loan_deduction ?? 0) > 0 || ($p->absence_deduction ?? 0) > 0)
                        <div class="text-muted" style="font-size:.7rem; line-height: 1.15;">
                            @if(($p->loan_deduction ?? 0) > 0)
                                <span title="Salary Advance Loan Repayment" class="d-block text-warning"><i class="fa-solid fa-hand-holding-dollar me-1"></i>Loan: {{ number_format($p->loan_deduction, 2) }}</span>
                            @endif
                            @if(($p->absence_deduction ?? 0) > 0 || ($p->absent_days ?? 0) > 0)
                                <span title="{{ $p->absent_days }} day(s) missed without approved leave (Basic / 30 x {{ $p->absent_days }})" class="d-block text-danger"><i class="fa-solid fa-calendar-xmark me-1"></i>Absent ({{ $p->absent_days }}d): {{ number_format($p->absence_deduction, 2) }}</span>
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
                        No payroll entries for {{ $period }}.<br>
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

{{-- ── Submit to GM Modal ───────────────────────────────────────────────── --}}
<div class="modal fade" id="submitGMModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" action="{{ route('finance.payroll.submit-gm') }}" class="modal-content rounded-4 border-0 shadow">
            @csrf
            <input type="hidden" name="month" value="{{ $month }}">
            <input type="hidden" name="year"  value="{{ $year }}">
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
                    <strong>{{ $period }}</strong> to the <strong>General Manager</strong> for approval.
                </div>
                <div class="bg-light rounded-3 p-3 mb-3">
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
