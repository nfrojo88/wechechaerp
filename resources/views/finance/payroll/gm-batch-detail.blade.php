@extends('layouts.app')
@section('title', 'Review Payroll Batch — General Manager')

@section('content')
<div class="container-fluid px-4 py-3">

    {{-- Header --}}
    <div class="d-flex align-items-center justify-content-between mb-4">
        <div>
            <h1 class="h3 fw-bold mb-1">
                <i class="fa-solid fa-file-invoice-dollar text-primary me-2"></i>Review Payroll: {{ date('F Y', mktime(0,0,0,$month,1,$year)) }}
            </h1>
            <p class="text-muted small mb-0">Submitted by Finance Head for General Manager approval.</p>
        </div>
        <a href="{{ route('finance.payroll.gm') }}" class="btn btn-outline-secondary btn-sm rounded-pill px-3">
            <i class="fa-solid fa-arrow-left me-1"></i>Back to Inbox
        </a>
    </div>

    {{-- Summary KPI cards --}}
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card border-0 shadow-sm rounded-3">
                <div class="card-body text-center py-3">
                    <div class="text-muted small mb-1">Total Employees</div>
                    <div class="fw-bold fs-4 text-primary">{{ $payrolls->count() }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm rounded-3">
                <div class="card-body text-center py-3">
                    <div class="text-muted small mb-1">Total Basic Salary</div>
                    <div class="fw-bold fs-5 text-dark">{{ number_format($totals['basic'],2) }} ETB</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm rounded-3">
                <div class="card-body text-center py-3">
                    <div class="text-muted small mb-1">Total Tax & Pension</div>
                    <div class="fw-bold fs-5 text-warning">{{ number_format($totals['tax'] + $totals['pension'],2) }} ETB</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm rounded-3" style="background:linear-gradient(135deg,#198754,#146c43)">
                <div class="card-body text-center py-3">
                    <div class="text-white-50 small mb-1">Total Net Pay</div>
                    <div class="fw-bold fs-4 text-white">{{ number_format($totals['net'],2) }} ETB</div>
                </div>
            </div>
        </div>
    </div>

    {{-- Payroll Sheet Table --}}
    <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
        <div class="card-header bg-white py-3 px-4 border-bottom">
            <h6 class="fw-bold mb-0"><i class="fa-solid fa-list-check text-primary me-2"></i>Employee Payroll Breakdown</h6>
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
                        <th class="text-end" style="color:#6f42c1;">Co. Pension (11%)</th>
                        <th class="text-end text-warning">Income Tax</th>
                        <th class="text-end text-danger">Other Ded.</th>
                        <th class="text-end fw-bold text-success">Net Pay</th>
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
                        <td class="text-end text-danger">{{ number_format($p->deductions, 2) }}</td>
                        <td class="text-end fw-bold text-success">{{ number_format($p->net_salary, 2) }}</td>
                    </tr>
                    @endforeach
                </tbody>
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
                        <td class="text-end text-danger">{{ number_format($totals['deductions'],2) }}</td>
                        <td class="text-end text-success">{{ number_format($totals['net'],2) }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>

    {{-- Action Card --}}
    <div class="card border-0 shadow-sm rounded-4 p-4">
        <h5 class="fw-bold mb-3"><i class="fa-solid fa-gavel text-dark me-2"></i>Decision & Action</h5>
        <div class="row">
            <div class="col-md-6 border-end pe-md-4">
                <form method="POST" action="{{ route('finance.payroll.gm.approve') }}">
                    @csrf
                    <input type="hidden" name="month" value="{{ $month }}">
                    <input type="hidden" name="year"  value="{{ $year }}">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Approval Remarks (Optional)</label>
                        <textarea name="gm_notes" class="form-control" rows="2" placeholder="Add approval remarks or instructions..."></textarea>
                    </div>
                    <button type="submit" class="btn btn-success rounded-pill px-4" onclick="return confirm('Approve this payroll batch?')">
                        <i class="fa-solid fa-check-circle me-1"></i>Approve Payroll Batch
                    </button>
                </form>
            </div>
            <div class="col-md-6 ps-md-4 mt-3 mt-md-0">
                <form method="POST" action="{{ route('finance.payroll.gm.reject') }}">
                    @csrf
                    <input type="hidden" name="month" value="{{ $month }}">
                    <input type="hidden" name="year"  value="{{ $year }}">
                    <div class="mb-3">
                        <label class="form-label fw-semibold text-danger">Rejection Reason (Required)</label>
                        <textarea name="gm_notes" class="form-control" rows="2" placeholder="Specify reason for rejecting this payroll..." required></textarea>
                    </div>
                    <button type="submit" class="btn btn-outline-danger rounded-pill px-4" onclick="return confirm('Reject this payroll batch?')">
                        <i class="fa-solid fa-times-circle me-1"></i>Reject Payroll Batch
                    </button>
                </form>
            </div>
        </div>
    </div>

</div>
@endsection
