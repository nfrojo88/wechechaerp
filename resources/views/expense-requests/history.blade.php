@extends('layouts.app')

@section('title', 'Payment History - Expense Requests')

@section('content')
<div class="container-fluid py-3">

    {{-- Page Header --}}
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
        <div>
            <h3 class="fw-bold mb-1">
                <i class="fa-solid fa-receipt text-success me-2"></i>Expense Payment History
            </h3>
            <p class="text-muted small mb-0">Record of all paid employee expense requests with deducted Bank Accounts and Chart of Accounts codes.</p>
        </div>
        <div>
            <a href="{{ route('expense-requests.index') }}" class="btn btn-outline-secondary shadow-sm">
                <i class="fa-solid fa-arrow-left me-1"></i> Back to Requests
            </a>
        </div>
    </div>

    {{-- Main Table Card --}}
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white py-3 border-bottom d-flex flex-wrap justify-content-between align-items-center gap-2">
            <h6 class="mb-0 fw-bold"><i class="fa-solid fa-file-invoice-dollar me-2 text-success"></i>Disbursed Payments Log</h6>
            
            <form method="GET" action="{{ route('expense-requests.history') }}" class="d-flex gap-2 mb-0">
                <div class="input-group input-group-sm" style="max-width: 280px;">
                    <span class="input-group-text bg-light"><i class="fa-solid fa-search text-muted"></i></span>
                    <input type="text" name="search" class="form-control" placeholder="Search ref #, employee..." value="{{ request('search') }}">
                </div>
                <button type="submit" class="btn btn-sm btn-secondary">Search</button>
                @if(request('search'))
                    <a href="{{ route('expense-requests.history') }}" class="btn btn-sm btn-outline-danger"><i class="fa-solid fa-xmark"></i></a>
                @endif
            </form>
        </div>

        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-3">REQ #</th>
                            <th>Paid Date</th>
                            <th>Employee Name</th>
                            <th>Category</th>
                            <th>Paid Amount</th>
                            <th>Bank / COA Used</th>
                            <th>Approval Chain</th>
                            <th>Payment Ref</th>
                            <th class="pe-3 text-end">Receipt</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($paidRequests as $req)
                        <tr>
                            <td class="ps-3 fw-bold text-dark">{{ $req->request_number }}</td>
                            <td class="small text-muted">
                                {{ $req->paid_at ? $req->paid_at->format('M d, Y H:i') : 'N/A' }}
                            </td>
                            <td>
                                <div class="fw-semibold text-dark">{{ $req->user->name ?? 'Employee' }}</div>
                                <small class="text-muted">{{ $req->employee->role_title ?? $req->employee->department ?? $req->user->email }}</small>
                            </td>
                            <td>
                                @php
                                    $catIcon = match($req->category) {
                                        'Transport' => 'fa-car',
                                        'Office Material' => 'fa-boxes-packing',
                                        'Loading & Unloading', 'Loading / Unloading', 'Loading Unloading' => 'fa-truck-ramp-box',
                                        'Contract Work' => 'fa-file-signature',
                                        default => 'fa-list',
                                    };
                                @endphp
                                <span class="badge bg-light text-dark border">
                                    <i class="fa-solid {{ $catIcon }} me-1 text-primary"></i>
                                    {{ $req->category }}
                                </span>
                                @if($req->category === 'Transport')
                                    <div class="mt-1 small" style="font-size: 0.72rem; line-height: 1.35;">
                                        <div class="text-truncate text-muted"><i class="fa-solid fa-user-pen text-primary me-1"></i>Asked by: <strong class="text-dark">{{ $req->user->name ?? 'N/A' }}</strong></div>
                                        @php $appr = $req->approver_info; @endphp
                                        <div class="text-truncate text-muted"><i class="fa-solid fa-check-circle text-success me-1"></i>Approved: <strong class="text-success">{{ $appr['name'] ?? 'Authorized' }}</strong></div>
                                    </div>
                                @endif
                            </td>
                            <td class="fw-bold fs-6 text-success">
                                ETB {{ number_format($req->amount, 2) }}
                            </td>
                            <td>
                                @if($req->bankAccount)
                                    <div class="fw-semibold text-dark small">{{ $req->bankAccount->bank_name }}</div>
                                    <small class="text-muted">{{ $req->bankAccount->account_number }}</small>
                                @endif
                                @if($req->chartOfAccount)
                                    <div class="badge bg-dark mt-1" title="{{ $req->chartOfAccount->name }}">
                                        [{{ $req->chartOfAccount->code }}] {{ Str::limit($req->chartOfAccount->name, 18) }}
                                    </div>
                                @endif
                            </td>
                            <td class="small">
                                <div><span class="text-muted">HR:</span> {{ $req->hrReviewer->name ?? 'N/A' }}</div>
                                @if($req->amount > 5000 && ($req->gmApprover || $req->gmReviewer))
                                    <div><span class="text-muted">GM:</span> {{ $req->gmApprover->name ?? $req->gmReviewer->name }}</div>
                                @endif
                                <div><span class="text-muted">Paid by:</span> {{ $req->paidBy->name ?? 'Finance' }}</div>
                            </td>
                            <td>
                                <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 fw-mono">
                                    {{ $req->payment_reference ?? 'N/A' }}
                                </span>
                            </td>
                            <td class="pe-3 text-end">
                                @if($req->attachment)
                                    <a href="{{ route('expense-requests.attachment', $req->id) }}" target="_blank" class="btn btn-sm btn-outline-primary py-0 px-2 shadow-sm" title="View receipt">
                                        <i class="fa-solid fa-paperclip me-1"></i>View
                                    </a>
                                @else
                                    <span class="text-muted small">-</span>
                                @endif
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="9" class="text-center py-5 text-muted">
                                <i class="fa-solid fa-receipt fa-3x mb-3 text-secondary opacity-50"></i>
                                <h6>No paid expense requests found.</h6>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($paidRequests->hasPages())
            <div class="p-3 border-top">
                {{ $paidRequests->links() }}
            </div>
            @endif
        </div>
    </div>
</div>
@endsection
