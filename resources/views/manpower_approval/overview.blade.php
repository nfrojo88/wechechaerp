@extends('layouts.app')
@section('title', 'Manpower Attendance Approval')
@section('content')
<div class="container-fluid">
    {{-- Header --}}
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 pb-3 border-bottom gap-2">
        <div>
            <h4 class="fw-bold text-dark mb-0">
                <i class="fa-solid fa-users-viewfinder text-primary me-2"></i>Manpower Attendance Approval Pipeline
            </h4>
            <p class="text-muted small mb-0 mt-1">
                Multi-level approval chain: Site Engineer &rarr; Planning &rarr; Coordinator &rarr; HR &rarr; Weekly Collection &rarr; General Manager &rarr; Finance Payment.
            </p>
        </div>
        <div class="d-flex align-items-center gap-2 flex-wrap">
            <a href="{{ route('manpower-approval.site-engineer.create') }}" class="btn btn-sm btn-primary shadow-xs">
                <i class="fa-solid fa-plus me-1"></i>New Daily Sheet
            </a>
            <a href="{{ route('manpower-approval.review.inbox') }}" class="btn btn-sm btn-outline-secondary shadow-xs">
                <i class="fa-solid fa-inbox me-1"></i>Approval Inbox
            </a>
            <a href="{{ route('manpower-approval.weekly.index') }}" class="btn btn-sm btn-outline-info shadow-xs">
                <i class="fa-solid fa-calendar-week me-1"></i>Weekly Collection
            </a>
            <a href="{{ route('manpower-approval.payments.index') }}" class="btn btn-sm btn-outline-success shadow-xs">
                <i class="fa-solid fa-money-bill-wave me-1"></i>Finance Payments
            </a>
        </div>
    </div>

    {{-- Pipeline KPI Stage Cards --}}
    <div class="row g-2 mb-4">
        {{-- Stage 1: Planning Manager --}}
        <div class="col-6 col-md-4 col-xl-2">
            <div class="card border-0 shadow-xs rounded-3 bg-white h-100 p-3">
                <div class="d-flex align-items-center justify-content-between mb-1">
                    <span class="text-secondary small fw-bold text-uppercase" style="font-size: 0.68rem;">Planning Inbox</span>
                    <i class="fa-solid fa-clipboard-check text-primary"></i>
                </div>
                <div class="fs-4 fw-bold text-primary font-monospace">{{ number_format($pendingPlanning) }}</div>
                <div class="text-muted small" style="font-size: 0.72rem;">Pending Planning Review</div>
            </div>
        </div>

        {{-- Stage 2: Coordinator --}}
        <div class="col-6 col-md-4 col-xl-2">
            <div class="card border-0 shadow-xs rounded-3 bg-white h-100 p-3">
                <div class="d-flex align-items-center justify-content-between mb-1">
                    <span class="text-secondary small fw-bold text-uppercase" style="font-size: 0.68rem;">Coordinator</span>
                    <i class="fa-solid fa-user-tie text-info"></i>
                </div>
                <div class="fs-4 fw-bold text-info font-monospace">{{ number_format($pendingCoordinator) }}</div>
                <div class="text-muted small" style="font-size: 0.72rem;">Pending Coordinator Review</div>
            </div>
        </div>

        {{-- Stage 3: HR Verification --}}
        <div class="col-6 col-md-4 col-xl-2">
            <div class="card border-0 shadow-xs rounded-3 bg-white h-100 p-3">
                <div class="d-flex align-items-center justify-content-between mb-1">
                    <span class="text-secondary small fw-bold text-uppercase" style="font-size: 0.68rem;">HR Verify</span>
                    <i class="fa-solid fa-user-check text-warning"></i>
                </div>
                <div class="fs-4 fw-bold text-warning font-monospace">{{ number_format($pendingHr) }}</div>
                <div class="text-muted small" style="font-size: 0.72rem;">Worker &amp; Overtime Check</div>
            </div>
        </div>

        {{-- Stage 4: Ready for Weekly Batch --}}
        <div class="col-6 col-md-4 col-xl-2">
            <div class="card border-0 shadow-xs rounded-3 bg-white h-100 p-3">
                <div class="d-flex align-items-center justify-content-between mb-1">
                    <span class="text-secondary small fw-bold text-uppercase" style="font-size: 0.68rem;">Weekly Pool</span>
                    <i class="fa-solid fa-boxes-stacked text-purple"></i>
                </div>
                <div class="fs-4 fw-bold text-dark font-monospace">{{ number_format($readyForBatch) }}</div>
                <div class="text-muted small" style="font-size: 0.72rem;">HR Approved (Unbatched)</div>
            </div>
        </div>

        {{-- Stage 5: GM Approval --}}
        <div class="col-6 col-md-4 col-xl-2">
            <div class="card border-0 shadow-xs rounded-3 bg-white h-100 p-3">
                <div class="d-flex align-items-center justify-content-between mb-1">
                    <span class="text-secondary small fw-bold text-uppercase" style="font-size: 0.68rem;">GM Batches</span>
                    <i class="fa-solid fa-stamp text-danger"></i>
                </div>
                <div class="fs-4 fw-bold text-danger font-monospace">{{ number_format($pendingGm) }}</div>
                <div class="text-muted small" style="font-size: 0.72rem;">Batches Pending GM</div>
            </div>
        </div>

        {{-- Stage 6: Finance Payment --}}
        <div class="col-6 col-md-4 col-xl-2">
            <div class="card border-0 shadow-xs rounded-3 bg-success bg-opacity-10 border-success border-opacity-25 h-100 p-3">
                <div class="d-flex align-items-center justify-content-between mb-1">
                    <span class="text-success small fw-bold text-uppercase" style="font-size: 0.68rem;">Finance Queue</span>
                    <i class="fa-solid fa-hand-holding-dollar text-success"></i>
                </div>
                <div class="fs-4 fw-bold text-success font-monospace">{{ number_format($pendingFinance) }}</div>
                <div class="text-success text-opacity-75 small" style="font-size: 0.72rem;">Authorized for Payment</div>
            </div>
        </div>
    </div>

    {{-- Tables: Recent Sheets & Weekly Batches --}}
    <div class="row g-3">
        {{-- Recent Daily Sheets --}}
        <div class="col-lg-7">
            <div class="card border-0 shadow-xs rounded-3 bg-white h-100">
                <div class="card-header bg-white py-3 px-3 border-bottom d-flex justify-content-between align-items-center">
                    <h6 class="fw-bold text-dark mb-0">
                        <i class="fa-solid fa-list-check text-primary me-1"></i>Recent Daily Manpower Sheets
                    </h6>
                    <a href="{{ route('manpower-approval.review.inbox') }}" class="small text-decoration-none">View All &rarr;</a>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0 small">
                            <thead class="table-light">
                                <tr>
                                    <th>Sheet #</th>
                                    <th>Project</th>
                                    <th>Date</th>
                                    <th>Headcount</th>
                                    <th>Total Amount</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($recentSheets as $sheet)
                                <tr>
                                    <td>
                                        <a href="{{ route('manpower-approval.review.show', $sheet->id) }}" class="fw-bold text-primary font-monospace text-decoration-none">
                                            {{ $sheet->sheet_number }}
                                        </a>
                                    </td>
                                    <td>
                                        <div class="text-truncate" style="max-width: 140px;" title="{{ $sheet->project?->name }}">
                                            {{ $sheet->project?->name ?? 'General' }}
                                        </div>
                                    </td>
                                    <td>{{ $sheet->date->format('M d, Y') }}</td>
                                    <td><span class="badge bg-light text-dark border">{{ $sheet->total_headcount }}</span></td>
                                    <td class="font-monospace fw-bold">ETB {{ number_format($sheet->effective_total_amount, 2) }}</td>
                                    <td>
                                        @if($sheet->status === 'Draft')
                                            <span class="badge bg-secondary">Draft</span>
                                        @elseif($sheet->status === 'Submitted')
                                            <span class="badge bg-primary">Planning Review</span>
                                        @elseif($sheet->status === 'Planning Approved')
                                            <span class="badge bg-info text-white">Coordinator Review</span>
                                        @elseif($sheet->status === 'Coordinator Approved')
                                            <span class="badge bg-warning text-dark">HR Review</span>
                                        @elseif($sheet->status === 'HR Approved')
                                            <span class="badge bg-success">HR Approved</span>
                                        @elseif($sheet->status === 'In Weekly Batch')
                                            <span class="badge bg-purple text-white">In Weekly Batch</span>
                                        @elseif($sheet->status === 'GM Approved')
                                            <span class="badge bg-success">GM Approved</span>
                                        @elseif($sheet->status === 'Paid')
                                            <span class="badge bg-success"><i class="fa-solid fa-check me-1"></i>Paid</span>
                                        @elseif($sheet->status === 'Rejected')
                                            <span class="badge bg-danger" title="{{ $sheet->rejection_reason }}">Returned</span>
                                        @endif
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="6" class="text-center py-4 text-muted">No daily manpower sheets recorded yet.</td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        {{-- Recent Weekly Batches --}}
        <div class="col-lg-5">
            <div class="card border-0 shadow-xs rounded-3 bg-white h-100">
                <div class="card-header bg-white py-3 px-3 border-bottom d-flex justify-content-between align-items-center">
                    <h6 class="fw-bold text-dark mb-0">
                        <i class="fa-solid fa-file-invoice-dollar text-success me-1"></i>Recent Weekly Batches
                    </h6>
                    <a href="{{ route('manpower-approval.weekly.index') }}" class="small text-decoration-none">View All &rarr;</a>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0 small">
                            <thead class="table-light">
                                <tr>
                                    <th>Batch #</th>
                                    <th>Week</th>
                                    <th>Net Payable</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($recentBatches as $batch)
                                <tr>
                                    <td>
                                        <a href="{{ route('manpower-approval.weekly.show', $batch->id) }}" class="fw-bold text-primary font-monospace text-decoration-none">
                                            {{ $batch->batch_number }}
                                        </a>
                                        <div class="text-muted" style="font-size: 0.68rem;">{{ $batch->project?->name }}</div>
                                    </td>
                                    <td>{{ $batch->week_start->format('M d') }} &ndash; {{ $batch->week_end->format('M d') }}</td>
                                    <td class="font-monospace fw-bold text-success">ETB {{ number_format($batch->total_net_payable, 2) }}</td>
                                    <td>
                                        @if($batch->status === 'Submitted_GM')
                                            <span class="badge bg-warning text-dark">Pending GM</span>
                                        @elseif($batch->status === 'GM_Approved')
                                            <span class="badge bg-info text-white">Pending Finance</span>
                                        @elseif($batch->status === 'Paid')
                                            <span class="badge bg-success"><i class="fa-solid fa-check me-1"></i>Paid</span>
                                        @elseif($batch->status === 'Held')
                                            <span class="badge bg-warning text-dark">On Hold</span>
                                        @elseif($batch->status === 'Rejected')
                                            <span class="badge bg-danger">Returned</span>
                                        @endif
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="4" class="text-center py-4 text-muted">No weekly payment batches generated yet.</td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
