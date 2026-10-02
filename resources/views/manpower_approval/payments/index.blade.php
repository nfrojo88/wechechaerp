@extends('layouts.app')
@section('title', 'Finance - Manpower Payments')
@section('content')
<div class="container-fluid">
    {{-- Header --}}
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 pb-3 border-bottom gap-2">
        <div>
            <h4 class="fw-bold text-dark mb-0">
                <i class="fa-solid fa-money-bill-transfer text-success me-2"></i>Finance - Manpower Payroll Payments
            </h4>
            <p class="text-muted small mb-0 mt-1">
                Disburse approved weekly site labor payroll batches authorized by the General Manager.
            </p>
        </div>
    </div>

    @if(session('success'))
    <div class="alert alert-success alert-dismissible fade show border-0 shadow-xs rounded-3 py-2 px-3 mb-3 d-flex align-items-center gap-2">
        <i class="fa-solid fa-circle-check text-success fs-5"></i>
        <div class="small flex-grow-1">{{ session('success') }}</div>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    @endif

    {{-- Tabs --}}
    <ul class="nav nav-tabs mb-3 border-bottom-0">
        <li class="nav-item">
            <a class="nav-link {{ $status === 'pending' ? 'active fw-bold text-success' : 'text-secondary' }}" href="{{ route('manpower-approval.payments.index', ['status' => 'pending']) }}">
                <i class="fa-solid fa-hourglass-half me-1"></i>Pending Payment (GM Authorized)
                @if($pendingCount > 0)
                <span class="badge bg-success ms-1">{{ $pendingCount }}</span>
                @endif
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link {{ $status === 'paid' ? 'active fw-bold text-primary' : 'text-secondary' }}" href="{{ route('manpower-approval.payments.index', ['status' => 'paid']) }}">
                <i class="fa-solid fa-circle-check me-1"></i>Paid Batches Archive
                <span class="badge bg-light text-dark border ms-1">{{ $paidCount }}</span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link {{ $status === 'all' ? 'active fw-bold text-dark' : 'text-secondary' }}" href="{{ route('manpower-approval.payments.index', ['status' => 'all']) }}">
                <i class="fa-solid fa-list me-1"></i>All Batches
            </a>
        </li>
    </ul>

    {{-- Batches Table --}}
    <div class="card border-0 shadow-xs rounded-3 bg-white mb-4">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 small text-center">
                    <thead class="table-light">
                        <tr>
                            <th>Batch Number</th>
                            <th>Project / Site</th>
                            <th>Week Range</th>
                            <th>Workers</th>
                            <th>Gross Total</th>
                            <th>Deductions</th>
                            <th>Net Payable</th>
                            <th>Status</th>
                            @if($status === 'paid')
                            <th>Paid Details</th>
                            @endif
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($batches as $b)
                        <tr>
                            <td>
                                <a href="{{ route('manpower-approval.weekly.show', $b->id) }}" class="fw-bold font-monospace text-primary text-decoration-none">
                                    {{ $b->batch_number }}
                                </a>
                            </td>
                            <td>
                                <strong class="text-dark d-block text-truncate" style="max-width: 160px;" title="{{ $b->project?->name }}">
                                    {{ $b->project?->name ?? 'General Site' }}
                                </strong>
                            </td>
                            <td>{{ $b->week_start->format('M d') }} &ndash; {{ $b->week_end->format('M d, Y') }}</td>
                            <td><span class="badge bg-light text-dark border font-monospace">{{ $b->total_workers_count }}</span></td>
                            <td class="font-monospace">ETB {{ number_format($b->total_gross_amount, 2) }}</td>
                            <td class="font-monospace text-danger">-ETB {{ number_format($b->total_deductions + $b->total_advances, 2) }}</td>
                            <td class="font-monospace fw-bold text-success fs-6">ETB {{ number_format($b->total_net_payable, 2) }}</td>
                            <td>
                                @if($b->status === 'GM_Approved')
                                    <span class="badge bg-info text-white">Authorized by GM</span>
                                @elseif($b->status === 'Paid')
                                    <span class="badge bg-success"><i class="fa-solid fa-check me-1"></i>Paid</span>
                                @elseif($b->status === 'Held')
                                    <span class="badge bg-warning text-dark">Held: {{ $b->hold_reason }}</span>
                                @else
                                    <span class="badge bg-secondary">{{ $b->status }}</span>
                                @endif
                            </td>
                            @if($status === 'paid')
                            <td>
                                <div class="small text-dark font-monospace">{{ optional($b->payment_date)->format('M d, Y') }} &bull; {{ $b->payment_method }}</div>
                                @if($b->payment_reference)<div class="text-muted" style="font-size: 0.68rem;">Ref: {{ $b->payment_reference }}</div>@endif
                            </td>
                            @endif
                            <td class="text-end">
                                <a href="{{ route('manpower-approval.weekly.show', $b->id) }}" class="btn btn-sm btn-outline-primary">
                                    <i class="fa-solid fa-eye me-1"></i>Details
                                </a>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="{{ $status === 'paid' ? 10 : 9 }}" class="py-5 text-center text-muted">
                                <i class="fa-solid fa-check-double fs-2 mb-2 d-block text-success"></i>
                                No payment batches found in this category.
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($batches->hasPages())
            <div class="p-3 border-top">
                {{ $batches->links() }}
            </div>
            @endif
        </div>
    </div>
</div>
@endsection
