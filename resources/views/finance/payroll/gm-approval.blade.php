@extends('layouts.app')

@section('title', 'Payroll Approval — General Manager')

@section('content')
<div class="container-fluid px-0">
    {{-- Page Header --}}
    <x-page-header
        title="Executive Payroll Approval Hub"
        subtitle="Review, audit attendance summaries, and authorize monthly enterprise payroll batches submitted by Finance Head."
        :breadcrumbs="[
            ['label' => 'Dashboard', 'url' => route('dashboard')],
            ['label' => 'Executive Office', 'url' => route('dashboard.gm')],
            ['label' => 'Payroll Approval']
        ]">
        <x-slot name="actions">
            <a href="{{ route('dashboard.gm') }}" class="btn btn-outline-secondary btn-sm rounded-pill px-3 py-2 shadow-xs fw-semibold">
                <i class="fa-solid fa-arrow-left me-1.5"></i>GM Dashboard
            </a>
            <a href="{{ route('finance.payroll.index') }}" class="btn btn-outline-primary btn-sm rounded-pill px-3 py-2 shadow-xs fw-semibold">
                <i class="fa-solid fa-money-bill-wave me-1.5"></i>Payroll Register
            </a>
        </x-slot>
    </x-page-header>

    {{-- System Alerts --}}
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

    {{-- Executive Summary KPI Cards --}}
    <div class="row g-3 mb-4">
        <div class="col-12 col-md-4">
            <x-kpi-card
                title="Batches Pending Approval"
                :value="number_format($batches->count())"
                :changeLabel="$batches->count() > 0 ? 'Urgent GM review required' : 'Inbox clear'"
                icon="fa-solid fa-clock"
                :color="$batches->count() > 0 ? 'warning' : 'success'" />
        </div>
        <div class="col-12 col-md-4">
            <x-kpi-card
                title="Total Net Pay in Queue"
                :value="'ETB ' . number_format($batches->sum('total_net'), 2)"
                changeLabel="Combined pending disbursement"
                icon="fa-solid fa-money-bill-wave"
                color="primary" />
        </div>
        <div class="col-12 col-md-4">
            <x-kpi-card
                title="Historical Decided Batches"
                :value="number_format($history->count())"
                changeLabel="Past authorized payrolls"
                icon="fa-solid fa-circle-check"
                color="info" />
        </div>
    </div>

    {{-- Pending Batches Card --}}
    <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
        <div class="card-header bg-white py-3 px-4 border-bottom d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div class="d-flex align-items-center gap-2">
                <span class="p-2 rounded-3 bg-warning bg-opacity-10 text-warning fs-6">
                    <i class="fa-solid fa-hourglass-half"></i>
                </span>
                <h6 class="fw-bold mb-0 text-dark">Submissions Awaiting GM Authorization</h6>
            </div>
            <span class="badge bg-warning text-dark rounded-pill px-3 py-1.5 fw-bold small">
                {{ $batches->count() }} Batch(es) In Queue
            </span>
        </div>

        @if($batches->isEmpty())
            <div class="card-body text-center py-5">
                <x-empty-state
                    title="No Payroll Submissions In Queue"
                    description="All monthly payroll batches have been reviewed and processed. The Finance Head has not submitted new batches."
                    icon="fa-solid fa-inbox" />
            </div>
        @else
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 wc-table-mobile-cards">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4 text-secondary text-uppercase small fw-bold">Period &amp; Batch</th>
                            <th class="text-center text-secondary text-uppercase small fw-bold">Headcount</th>
                            <th class="text-end text-secondary text-uppercase small fw-bold">Total Gross (ETB)</th>
                            <th class="text-end text-secondary text-uppercase small fw-bold">Total Net Pay (ETB)</th>
                            <th class="text-center text-secondary text-uppercase small fw-bold">Submitted Date</th>
                            <th class="pe-4 text-center text-secondary text-uppercase small fw-bold">Action</th>
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
                                <td class="ps-4" data-label="Period">
                                    <div class="fw-bold text-dark fs-6">{{ $periodLabel }}</div>
                                    <span class="badge bg-light text-muted border" style="font-size: 0.72rem;">Monthly Staff Payroll</span>
                                </td>
                                <td class="text-center" data-label="Headcount">
                                    <span class="badge bg-primary bg-opacity-10 text-primary rounded-pill px-3 py-1.5 fw-semibold font-tabular">
                                        <i class="fa-solid fa-users me-1"></i>{{ $batch->employee_count }} Staff
                                    </span>
                                </td>
                                <td class="text-end font-tabular fw-semibold text-dark" data-label="Gross Pay">
                                    {{ number_format($batch->total_gross, 2) }}
                                </td>
                                <td class="text-end font-tabular fw-bold text-success fs-6" data-label="Net Pay">
                                    <span class="small text-muted me-1">ETB</span>{{ number_format($batch->total_net, 2) }}
                                </td>
                                <td class="text-center text-muted small" data-label="Submitted">
                                    {{ $batch->submitted_at ? \Carbon\Carbon::parse($batch->submitted_at)->format('M d, Y H:i') : '—' }}
                                </td>
                                <td class="pe-4 text-center" data-label="Action">
                                    <a href="{{ route('finance.payroll.gm.detail', ['month' => $batch->month, 'year' => $batch->year]) }}"
                                       class="btn btn-sm btn-primary rounded-pill px-3 py-1.5 shadow-xs fw-semibold d-inline-flex align-items-center gap-1.5">
                                        <i class="fa-solid fa-clipboard-check"></i>
                                        <span>Audit &amp; Authorize</span>
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    {{-- Historical Decisions Card --}}
    @if($history->isNotEmpty())
        <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
            <div class="card-header bg-white py-3 px-4 border-bottom d-flex align-items-center justify-content-between">
                <div class="d-flex align-items-center gap-2">
                    <span class="p-2 rounded-3 bg-secondary bg-opacity-10 text-secondary fs-6">
                        <i class="fa-solid fa-clock-rotate-left"></i>
                    </span>
                    <h6 class="fw-bold mb-0 text-dark">Executive Decision Audit Trail</h6>
                </div>
                <span class="text-muted small">{{ $history->count() }} past batches</span>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 small wc-table-mobile-cards">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4 text-secondary text-uppercase small fw-bold">Period</th>
                            <th class="text-center text-secondary text-uppercase small fw-bold">Employees</th>
                            <th class="text-end text-secondary text-uppercase small fw-bold">Authorized Net (ETB)</th>
                            <th class="text-center text-secondary text-uppercase small fw-bold">Decision</th>
                            <th class="pe-4 text-center text-secondary text-uppercase small fw-bold">Date Decided</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($history as $h)
                            @php
                                $hPeriodLabel = ($h->month >= 1 && $h->month <= 13 && isset(\App\Helpers\EthiopianCalendar::MONTHS_EN[$h->month]))
                                    ? (\App\Helpers\EthiopianCalendar::MONTHS_EN[$h->month] . ' (' . \App\Helpers\EthiopianCalendar::MONTHS_AM[$h->month] . ') ' . $h->year . ' EC')
                                    : date('F Y', mktime(0,0,0,$h->month,1,$h->year));
                                $isApproved = $h->gm_status === 'approved';
                            @endphp
                            <tr>
                                <td class="ps-4 fw-semibold text-dark" data-label="Period">{{ $hPeriodLabel }}</td>
                                <td class="text-center font-tabular" data-label="Employees">{{ $h->employee_count }}</td>
                                <td class="text-end font-tabular fw-semibold text-dark" data-label="Net Pay">{{ number_format($h->total_net, 2) }}</td>
                                <td class="text-center" data-label="Decision">
                                    @if($isApproved)
                                        <span class="badge bg-success bg-opacity-10 text-success rounded-pill px-2.5 py-1 fw-bold">
                                            <i class="fa-solid fa-circle-check me-1"></i>Approved
                                        </span>
                                    @else
                                        <span class="badge bg-danger bg-opacity-10 text-danger rounded-pill px-2.5 py-1 fw-bold">
                                            <i class="fa-solid fa-circle-xmark me-1"></i>Rejected
                                        </span>
                                    @endif
                                </td>
                                <td class="pe-4 text-center text-muted" data-label="Date">
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
