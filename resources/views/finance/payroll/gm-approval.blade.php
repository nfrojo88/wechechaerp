@extends('layouts.app')
@section('title', 'Payroll Approval — General Manager')

@section('content')
<div class="container-fluid px-4 py-3">

<div class="d-flex align-items-center justify-content-between mb-4">
    <div>
        <h1 class="h3 fw-bold mb-1">
            <i class="fa-solid fa-file-signature text-primary me-2"></i>Payroll Approval
        </h1>
        <p class="text-muted small mb-0">Review and approve monthly payroll submissions from Finance Head.</p>
    </div>
</div>

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

{{-- ── Pending Approvals ─────────────────────────────────────────────────── --}}
<div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
    <div class="card-header bg-white py-3 px-4 border-bottom d-flex align-items-center justify-content-between">
        <h6 class="fw-bold mb-0">
            <i class="fa-solid fa-clock text-warning me-2"></i>Pending Approval
        </h6>
        <span class="badge bg-warning text-dark rounded-pill">{{ $batches->count() }} Batch(es)</span>
    </div>

    @if($batches->isEmpty())
    <div class="card-body text-center py-5 text-muted">
        <i class="fa-solid fa-inbox fa-3x mb-3 d-block opacity-25"></i>
        <p class="mb-0">No payroll submissions pending your approval.</p>
    </div>
    @else
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="bg-light">
                <tr>
                    <th class="ps-4">Period</th>
                    <th class="text-center">Employees</th>
                    <th class="text-end">Total Gross</th>
                    <th class="text-end text-success fw-bold">Total Net Pay</th>
                    <th class="text-center">Submitted</th>
                    <th class="text-center">Actions</th>
                </tr>
            </thead>
            <tbody>
                @foreach($batches as $batch)
                @php
                    $periodLabel = ($batch->month >= 1 && $batch->month <= 13 && isset(\App\Helpers\EthiopianCalendar::MONTHS_EN[$batch->month]))
                        ? (\App\Helpers\EthiopianCalendar::MONTHS_EN[$batch->month] . ' (' . \App\Helpers\EthiopianCalendar::MONTHS_AM[$batch->month] . ') ' . $batch->year . ' EC')
                        : date('F Y', mktime(0,0,0,$batch->month,1,$batch->year));
                @endphp
                <tr>
                    <td class="ps-4">
                        <div class="fw-semibold">{{ $periodLabel }}</div>
                        <div class="text-muted small">Monthly Payroll</div>
                    </td>
                    <td class="text-center">
                        <span class="badge bg-primary-subtle text-primary rounded-pill px-3">
                            {{ $batch->employee_count }} employees
                        </span>
                    </td>
                    <td class="text-end fw-semibold">{{ number_format($batch->total_gross, 2) }} ETB</td>
                    <td class="text-end fw-bold text-success fs-6">{{ number_format($batch->total_net, 2) }} ETB</td>
                    <td class="text-center text-muted small">
                        {{ $batch->submitted_at ? \Carbon\Carbon::parse($batch->submitted_at)->format('M d, Y H:i') : '—' }}
                    </td>
                    <td class="text-center">
                        <a href="{{ route('finance.payroll.gm.detail', ['month'=>$batch->month,'year'=>$batch->year]) }}"
                           class="btn btn-sm btn-primary rounded-pill px-3 me-1">
                            <i class="fa-solid fa-eye me-1"></i>Review
                        </a>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif
</div>

{{-- ── History ───────────────────────────────────────────────────────────── --}}
@if($history->isNotEmpty())
<div class="card border-0 shadow-sm rounded-4 overflow-hidden">
    <div class="card-header bg-white py-3 px-4 border-bottom">
        <h6 class="fw-bold mb-0"><i class="fa-solid fa-history text-secondary me-2"></i>Approval History</h6>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0 small">
            <thead class="bg-light">
                <tr>
                    <th class="ps-4">Period</th>
                    <th class="text-center">Employees</th>
                    <th class="text-end">Net Pay</th>
                    <th class="text-center">Decision</th>
                    <th class="text-center">Date</th>
                </tr>
            </thead>
            <tbody>
                @foreach($history as $h)
                @php
                    $hPeriodLabel = ($h->month >= 1 && $h->month <= 13 && isset(\App\Helpers\EthiopianCalendar::MONTHS_EN[$h->month]))
                        ? (\App\Helpers\EthiopianCalendar::MONTHS_EN[$h->month] . ' (' . \App\Helpers\EthiopianCalendar::MONTHS_AM[$h->month] . ') ' . $h->year . ' EC')
                        : date('F Y', mktime(0,0,0,$h->month,1,$h->year));
                @endphp
                <tr>
                    <td class="ps-4 fw-semibold">{{ $hPeriodLabel }}</td>
                    <td class="text-center">{{ $h->employee_count }}</td>
                    <td class="text-end fw-semibold">{{ number_format($h->total_net, 2) }} ETB</td>
                    <td class="text-center">
                        @if($h->gm_status === 'approved')
                            <span class="badge bg-success rounded-pill"><i class="fa-solid fa-check me-1"></i>Approved</span>
                        @else
                            <span class="badge bg-danger rounded-pill"><i class="fa-solid fa-xmark me-1"></i>Rejected</span>
                        @endif
                    </td>
                    <td class="text-center text-muted">
                        {{ $h->decided_at ? \Carbon\Carbon::parse($h->decided_at)->format('M d, Y') : '—' }}
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endif

</div>
@endsection
