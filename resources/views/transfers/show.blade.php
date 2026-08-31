@extends('layouts.app')

@php
    $authUser = auth()->user();
    $rawUserRoles = $authUser ? $authUser->roles->pluck('name')->map(fn($r) => strtolower(str_replace([' ', '-'], '_', trim($r))))->toArray() : [];
    $isAuditorUser = in_array('auditor', $rawUserRoles) || in_array('audit', $rawUserRoles) || in_array('internal_auditor', $rawUserRoles) || in_array('audit_team', $rawUserRoles) || ($authUser && $authUser->hasAnyRole(['auditor', 'audit', 'internal_auditor', 'Auditor', 'Audit']));
@endphp

@section('title', 'Transfer: ' . $transfer->transfer_no)
@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h1 class="h3 mb-0"><i class="fas {{ $isAuditorUser ? 'fa-shield-halved text-info' : 'fa-exchange-alt text-primary' }} me-2"></i>Transfer: {{ $transfer->transfer_no }}</h1>
            @if($isAuditorUser)
                <small class="text-muted">Internal Audit inspection of transfer custody and item movement</small>
            @endif
        </div>
        <div class="d-flex gap-2">
            @if($isAuditorUser)
                <span class="badge bg-info text-dark px-3 py-2 fs-6 rounded-pill fw-bold"><i class="fa-solid fa-eye me-1"></i>Read-Only View</span>
            @endif
            <a href="{{ route('transfers.index') }}" class="btn btn-outline-secondary"><i class="fas fa-arrow-left me-1"></i>Back</a>
        </div>
    </div>
    @if(session('success'))<div class="alert alert-success alert-dismissible fade show">{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>@endif

    @if($isAuditorUser)
        <div class="alert alert-info border-start border-4 border-info shadow-sm mb-4 d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div class="d-flex align-items-center gap-2">
                <div class="p-2.5 rounded-circle bg-info bg-opacity-25 text-info">
                    <i class="fa-solid fa-shield-halved fa-lg"></i>
                </div>
                <div>
                    <strong class="d-block text-dark">Auditor Oversight Mode</strong>
                    <span class="text-muted small">You have read-only inspection visibility over this transfer record, including originating/destination stores, drivers, and item breakdown.</span>
                </div>
            </div>
            <span class="badge bg-white text-info border border-info px-3 py-2 fw-semibold"><i class="fa-solid fa-lock me-1"></i>Read-Only</span>
        </div>
    @endif

    <div class="row g-3">
        <div class="col-md-5">
            <div class="card shadow-sm">
                <div class="card-header fw-semibold">Transfer Info</div>
                <div class="card-body">
                    <table class="table table-sm table-borderless mb-0">
                        <tr><th width="40%">From Store</th><td>{{ $transfer->fromStore->name ?? 'Main Store' }}</td></tr>
                        <tr><th>To Store</th><td>{{ $transfer->toStore->name ?? 'Workshop / Site' }}</td></tr>
                        <tr><th>Requested By</th><td>{{ $transfer->requestedBy->name ?? 'Staff' }}</td></tr>
                        <tr><th>Required Date</th><td>{{ optional($transfer->required_date)->format('d M Y') ?? '-' }}</td></tr>
                        <tr><th>Reason</th><td>{{ $transfer->reason ?? '-' }}</td></tr>
                        <tr><th>Status</th><td><span class="badge bg-info">{{ str_replace('_',' ', ucfirst($transfer->status)) }}</span></td></tr>
                        @if($transfer->approvedBy)<tr><th>Approved By</th><td>{{ $transfer->approvedBy->name ?? 'Approver' }} — {{ optional($transfer->approved_at)->format('d M Y, H:i') }}</td></tr>@endif
                        @if($transfer->driver)<tr><th>Assigned Driver</th><td><span class="fw-bold text-dark">{{ $transfer->driver->full_name }}</span> <small class="text-muted">({{ $transfer->driver->phone }})</small></td></tr>@endif
                        @if($transfer->dispatched_at)<tr><th>Dispatched At</th><td>{{ $transfer->dispatched_at->format('d M Y, H:i A') }}</td></tr>@endif
                        @if($transfer->dispatch_notes)<tr><th>Dispatch Notes</th><td>{{ $transfer->dispatch_notes }}</td></tr>@endif
                    </table>
                </div>
                @if(!$isAuditorUser)
                    @if(in_array($transfer->status, ['draft','pending_approval']))
                    <div class="card-footer p-3 bg-light">
                        <form action="{{ route('transfers.approve', $transfer) }}" method="POST">
                            @csrf
                            <div class="mb-3">
                                <label class="form-label fw-bold small text-uppercase text-secondary">
                                    Select Driver <span class="text-danger">*</span>
                                </label>
                                <select name="driver_employee_id" class="form-select form-select-sm" required>
                                    <option value="">— Select Assigned Driver —</option>
                                    @foreach($drivers as $driver)
                                        <option value="{{ $driver->id }}" {{ old('driver_employee_id', $transfer->driver_employee_id) == $driver->id ? 'selected' : '' }}>
                                            {{ $driver->full_name }} ({{ $driver->department ?? 'Drivers Dept' }}) — {{ $driver->phone }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-bold small text-uppercase text-secondary">
                                    Dispatch Notes / Time Info
                                </label>
                                <textarea name="dispatch_notes" class="form-control form-control-sm" rows="2" placeholder="Enter store dispatch notes or estimated time of departure...">{{ old('dispatch_notes', $transfer->dispatch_notes) }}</textarea>
                            </div>
                            <div class="d-flex gap-2">
                                <button type="submit" class="btn btn-success btn-sm fw-bold">
                                    <i class="fas fa-check me-1"></i>Approve &amp; Assign Driver
                                </button>
                        </form>
                        <form action="{{ route('transfers.reject', $transfer) }}" method="POST" class="d-flex gap-1 flex-fill">
                            @csrf
                            <input type="text" name="rejection_reason" class="form-control form-control-sm" placeholder="Reject Reason" required>
                            <button class="btn btn-danger btn-sm text-nowrap"><i class="fas fa-times me-1"></i>Reject</button>
                        </form>
                            </div>
                    </div>
                    @endif

                    @if($transfer->status === 'approved')
                    <div class="card-footer p-3 bg-light">
                        <div class="alert alert-info py-2 px-3 mb-2 small">
                            <i class="fa-solid fa-circle-info me-1"></i>
                            <strong>Driver Assigned:</strong> {{ $transfer->driver->full_name ?? 'Driver' }} ({{ $transfer->driver->phone ?? 'N/A' }}). Click below to dispatch and send SMS.
                        </div>
                        <form action="{{ route('transfers.send-to-driver', $transfer) }}" method="POST">
                            @csrf
                            <button type="submit" class="btn btn-primary fw-bold w-100 shadow-sm">
                                <i class="fas fa-paper-plane me-2"></i>Send to Driver (Send SMS)
                            </button>
                        </form>
                    </div>
                    @endif

                    @if($transfer->status === 'dispatched')
                    <div class="card-footer p-3 bg-light">
                        <div class="alert alert-success py-2 px-3 mb-2 small">
                            <i class="fa-solid fa-truck-fast me-1"></i>
                            <strong>Dispatched:</strong> Materials are currently in transit with {{ $transfer->driver->full_name ?? 'Driver' }}.
                        </div>
                        <form action="{{ route('transfers.complete', $transfer) }}" method="POST">
                            @csrf 
                            <button class="btn btn-success btn-sm fw-bold w-100">
                                <i class="fas fa-check-double me-1"></i>Mark Completed / Received at Store
                            </button>
                        </form>
                    </div>
                    @endif
                @endif
            </div>
        </div>
            </div>
        </div>
        <div class="col-md-7">
            <div class="card shadow-sm">
                <div class="card-header fw-semibold">Items</div>
                <div class="card-body p-0">
                    <table class="table align-middle mb-0">
                        <thead class="table-light"><tr><th>Product</th><th>Requested</th><th>Approved</th><th>Sent</th><th>Received</th></tr></thead>
                        <tbody>
                            @foreach($transfer->items as $item)
                            <tr>
                                <td>{{ $item->product->name ?? 'Item' }}</td>
                                <td>{{ $item->requested_quantity }} {{ $item->unit }}</td>
                                <td>{{ $item->approved_quantity }} {{ $item->unit }}</td>
                                <td>{{ $item->sent_quantity }} {{ $item->unit }}</td>
                                <td>{{ $item->received_quantity }} {{ $item->unit }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
