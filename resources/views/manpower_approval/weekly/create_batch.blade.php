@extends('layouts.app')
@section('title', 'Generate Weekly Manpower Batch')
@section('content')
<div class="container-fluid">
    {{-- Header --}}
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 pb-3 border-bottom gap-2">
        <div>
            <div class="d-flex align-items-center gap-2">
                <a href="{{ route('manpower-approval.weekly.index', ['project_id' => $project->id]) }}" class="btn btn-sm btn-outline-secondary">
                    <i class="fa-solid fa-arrow-left"></i>
                </a>
                <h4 class="fw-bold text-dark mb-0">
                    <i class="fa-solid fa-boxes-packing text-primary me-2"></i>Generate Weekly Payment Batch
                </h4>
            </div>
            <p class="text-muted small mb-0 mt-1">
                Project: <strong>{{ $project->name }}</strong> &bull; Consolidating <strong>{{ $sheets->count() }}</strong> HR-approved daily manpower sheets.
            </p>
        </div>
    </div>

    <form action="{{ route('manpower-approval.weekly.store-batch') }}" method="POST">
        @csrf
        <input type="hidden" name="project_id" value="{{ $project->id }}">

        @foreach($sheets as $s)
        <input type="hidden" name="sheet_ids[]" value="{{ $s->id }}">
        @endforeach

        {{-- Week Dates Card --}}
        <div class="card border-0 shadow-xs rounded-3 mb-3 bg-white">
            <div class="card-header bg-white py-2 px-3 border-bottom">
                <strong class="text-dark small"><i class="fa-solid fa-calendar-days text-primary me-1"></i>Batch Payroll Week Period</strong>
            </div>
            <div class="card-body p-3">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label small fw-bold text-dark mb-1">Week Start Date <span class="text-danger">*</span></label>
                        <input type="date" name="week_start" class="form-control form-control-sm" value="{{ $minDate ? $minDate->toDateString() : today()->startOfWeek()->toDateString() }}" required>
                        <small class="text-muted">Typically Monday</small>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-bold text-dark mb-1">Week End Date <span class="text-danger">*</span></label>
                        <input type="date" name="week_end" class="form-control form-control-sm" value="{{ $maxDate ? $maxDate->toDateString() : today()->endOfWeek()->toDateString() }}" required>
                        <small class="text-muted">Typically Sunday</small>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-bold text-dark mb-1">Batch Remarks / Notes</label>
                        <input type="text" name="notes" class="form-control form-control-sm" placeholder="e.g. Week 39 Site Labor Payroll">
                    </div>
                </div>
            </div>
        </div>

        {{-- Worker Consolidated Wages & Deductions Table --}}
        <div class="card border-0 shadow-xs rounded-3 mb-4 bg-white">
            <div class="card-header bg-white py-2 px-3 border-bottom d-flex justify-content-between align-items-center">
                <strong class="text-dark small"><i class="fa-solid fa-users text-primary me-1"></i>Worker Summary &amp; Deduction / Advance Adjustments</strong>
                <span class="badge bg-light text-dark border font-monospace">{{ count($workersSummary) }} Worker(s)</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-bordered align-middle mb-0 small text-center">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 40px;">#</th>
                                <th class="text-start" style="min-width: 170px;">Worker Name &amp; Code</th>
                                <th>Trade</th>
                                <th style="width: 80px;">Days Worked</th>
                                <th style="width: 80px;">Reg. Hours</th>
                                <th style="width: 80px;">OT Hours</th>
                                <th style="width: 120px;">Gross Amount</th>
                                <th style="width: 120px;">Deductions (ETB)</th>
                                <th style="width: 120px;">Advances (ETB)</th>
                                <th style="width: 130px;" class="bg-success-subtle text-success">Net Payable (ETB)</th>
                            </tr>
                        </thead>
                        <tbody>
                            @php $i = 1; @endphp
                            @foreach($workersSummary as $wId => $data)
                            @php
                                $w = $data['worker'];
                                $gross = $data['gross_amount'];
                            @endphp
                            <tr class="worker-calc-row" data-worker-id="{{ $wId }}">
                                <td class="font-monospace text-muted">{{ $i++ }}</td>
                                <td class="text-start">
                                    <strong class="text-dark d-block">{{ $w?->name ?? 'Worker' }}</strong>
                                    <span class="font-monospace text-primary" style="font-size: 0.68rem;">{{ $w?->worker_code }}</span>
                                    @if($w?->phone)
                                    <span class="text-muted" style="font-size: 0.68rem;">&bull; Tel: {{ $w->phone }}</span>
                                    @endif
                                </td>
                                <td>
                                    <span class="badge bg-light text-dark border">{{ $w?->trade ?: 'General' }}</span>
                                </td>
                                <td class="font-monospace fw-semibold">{{ $data['days_worked'] }}</td>
                                <td class="font-monospace">{{ number_format($data['regular_hours'], 1) }}h</td>
                                <td class="font-monospace">{{ $data['overtime_hours'] > 0 ? number_format($data['overtime_hours'], 1) . 'h' : '—' }}</td>
                                <td class="font-monospace fw-bold text-dark gross-val" data-gross="{{ $gross }}">
                                    ETB {{ number_format($gross, 2) }}
                                </td>
                                <td>
                                    <input type="number" step="0.01" min="0" name="worker_deductions[{{ $wId }}]" class="form-control form-control-sm text-center ded-input" value="0.00" onchange="recalcWorkerRow(this)">
                                </td>
                                <td>
                                    <input type="number" step="0.01" min="0" name="worker_advances[{{ $wId }}]" class="form-control form-control-sm text-center adv-input" value="0.00" onchange="recalcWorkerRow(this)">
                                </td>
                                <td class="bg-success-subtle font-monospace fw-bold text-success fs-6 net-val">
                                    ETB {{ number_format($gross, 2) }}
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                        <tfoot class="table-light fw-bold font-monospace">
                            <tr>
                                <td colspan="6" class="text-end">TOTALS:</td>
                                <td id="footBatchGross">ETB 0.00</td>
                                <td id="footBatchDed" class="text-danger">-ETB 0.00</td>
                                <td id="footBatchAdv" class="text-danger">-ETB 0.00</td>
                                <td id="footBatchNet" class="text-success fs-6">ETB 0.00</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
            <div class="card-footer bg-light p-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div class="text-muted small">
                    <i class="fa-solid fa-lock text-warning me-1"></i>Generating this batch locks all {{ $sheets->count() }} daily sheets from further modification and forwards the batch to General Manager.
                </div>
                <button type="submit" class="btn btn-sm btn-primary shadow-xs">
                    <i class="fa-solid fa-paper-plane me-1"></i>Generate Batch &amp; Send to GM
                </button>
            </div>
        </div>
    </form>
</div>

<script>
function recalcWorkerRow(input) {
    const row = input.closest('tr');
    const gross = parseFloat(row.querySelector('.gross-val').getAttribute('data-gross')) || 0;
    const ded = parseFloat(row.querySelector('.ded-input').value) || 0;
    const adv = parseFloat(row.querySelector('.adv-input').value) || 0;
    const net = Math.max(0, gross - ded - adv);

    row.querySelector('.net-val').textContent = 'ETB ' + net.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    recalcBatchTotals();
}

function recalcBatchTotals() {
    let totGross = 0;
    let totDed = 0;
    let totAdv = 0;

    document.querySelectorAll('.worker-calc-row').forEach(row => {
        const gross = parseFloat(row.querySelector('.gross-val').getAttribute('data-gross')) || 0;
        const ded = parseFloat(row.querySelector('.ded-input').value) || 0;
        const adv = parseFloat(row.querySelector('.adv-input').value) || 0;

        totGross += gross;
        totDed += ded;
        totAdv += adv;
    });

    const totNet = Math.max(0, totGross - totDed - totAdv);

    document.getElementById('footBatchGross').textContent = 'ETB ' + totGross.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    document.getElementById('footBatchDed').textContent = '-ETB ' + totDed.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    document.getElementById('footBatchAdv').textContent = '-ETB ' + totAdv.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    document.getElementById('footBatchNet').textContent = 'ETB ' + totNet.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

document.addEventListener('DOMContentLoaded', function() {
    recalcBatchTotals();
});
</script>
@endsection
