@extends('layouts.app')
@section('title', 'Transfer: ' . $transfer->transfer_no)
@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0"><i class="fas fa-exchange-alt me-2"></i>Transfer: {{ $transfer->transfer_no }}</h1>
        <a href="{{ route('transfers.index') }}" class="btn btn-outline-secondary"><i class="fas fa-arrow-left me-1"></i>Back</a>
    </div>
    @if(session('success'))<div class="alert alert-success alert-dismissible fade show">{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>@endif
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
                        @if($transfer->approvedBy)<tr><th>Approved By</th><td>{{ $transfer->approvedBy->name ?? 'Approver' }} — {{ optional($transfer->approved_at)->format('d M Y') }}</td></tr>@endif
                    </table>
                </div>
                @if(in_array($transfer->status, ['draft','pending_approval']))
                <div class="card-footer d-flex gap-2">
                    <form action="{{ route('transfers.approve', $transfer) }}" method="POST">
                        @csrf <button class="btn btn-success btn-sm"><i class="fas fa-check me-1"></i>Approve</button>
                    </form>
                    <form action="{{ route('transfers.reject', $transfer) }}" method="POST" class="d-flex gap-2">
                        @csrf
                        <input type="text" name="rejection_reason" class="form-control form-control-sm" placeholder="Reason" required>
                        <button class="btn btn-danger btn-sm"><i class="fas fa-times me-1"></i>Reject</button>
                    </form>
                </div>
                @endif
                @if($transfer->status === 'approved')
                <div class="card-footer">
                    <form action="{{ route('transfers.complete', $transfer) }}" method="POST">
                        @csrf <button class="btn btn-primary btn-sm"><i class="fas fa-check-double me-1"></i>Mark Completed / Received</button>
                    </form>
                </div>
                @endif
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
