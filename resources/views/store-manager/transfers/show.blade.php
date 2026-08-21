@extends('layouts.app')

@section('title', 'Transfer Details - ' . ($transfer->transfer_no ?? 'Transfer'))

@section('content')
<div class="container-fluid px-4 py-3">

    {{-- Header --}}
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h4 class="fw-bold mb-0" style="color:var(--brand-800)">
                <i class="fas fa-truck-moving me-2 text-primary"></i>Transfer {{ $transfer->transfer_no }}
            </h4>
            <p class="text-muted small mb-0">Manage transfer details, physical paper slip numbers, and dispatch/receive actions</p>
        </div>
        <div class="d-flex gap-2">
            <button type="button" class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#physicalSlipModal">
                <i class="fas fa-file-invoice me-1"></i>{{ $transfer->physical_slip_no ? 'Edit Slip #' : 'Add Physical Slip #' }}
            </button>
            <a href="{{ route('store-manager.transfers.index') }}" class="btn btn-secondary btn-sm">
                <i class="fas fa-arrow-left me-1"></i>Back to Transfers
            </a>
        </div>
    </div>

    {{-- Physical Slip Notice Banner if present --}}
    @if($transfer->physical_slip_no)
    <div class="card border-0 shadow-sm rounded-3 mb-4 bg-light" style="border-left: 5px solid #10b981 !important;">
        <div class="card-body p-3 d-flex justify-content-between align-items-center">
            <div class="d-flex align-items-center gap-3">
                <div class="p-2 rounded bg-success bg-opacity-10 text-success">
                    <i class="fa-solid fa-file-circle-check fa-lg"></i>
                </div>
                <div>
                    <span class="text-muted small d-block">Physical Paper Slip / Waybill Number</span>
                    <h5 class="fw-bold text-dark font-monospace mb-0">{{ $transfer->physical_slip_no }}</h5>
                </div>
            </div>
            <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#physicalSlipModal">
                <i class="fa-solid fa-pen me-1"></i>Edit Slip #
            </button>
        </div>
    </div>
    @else
    <div class="alert alert-warning border-0 shadow-sm rounded-3 mb-4 p-3 d-flex justify-content-between align-items-center">
        <div class="d-flex align-items-center gap-2">
            <i class="fa-solid fa-triangle-exclamation text-warning fa-lg"></i>
            <span class="small"><strong>No Physical Slip # recorded yet.</strong> Add the physical paper slip or delivery waybill number to keep audit trails synchronized.</span>
        </div>
        <button type="button" class="btn btn-sm btn-warning shadow-sm text-dark" data-bs-toggle="modal" data-bs-target="#physicalSlipModal">
            <i class="fa-solid fa-plus me-1"></i>Add Slip #
        </button>
    </div>
    @endif

    {{-- Quick Action Cards for Dispatch / Receive --}}
    @php
        $userStoreId = $assignedStore?->id ?? auth()->user()->store_id;
        $isSenderStore = $userStoreId && ($transfer->from_store_id == $userStoreId);
        $isReceiverStore = $userStoreId && ($transfer->to_store_id == $userStoreId);
        $isAdmin = auth()->user()->hasAnyRole(['admin', 'global_admin', 'store_manager']);
    @endphp

    @if(in_array($transfer->status, ['draft', 'approved', 'in_transit']))
    <div class="card border-0 shadow-sm rounded-3 mb-4 p-3 bg-white">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
            <div>
                <h6 class="fw-bold mb-1"><i class="fa-solid fa-dolly me-2 text-primary"></i>Store Keeper Transfer Actions</h6>
                <p class="text-muted small mb-0">Update transfer state when sending goods from origin store or receiving into destination store.</p>
            </div>
            <div class="d-flex flex-wrap gap-2">
                {{-- Dispatch Button (Sender store or Store Manager/Admin) --}}
                @if(in_array($transfer->status, ['draft', 'approved']) && ($isSenderStore || $isAdmin))
                <form action="{{ route('store-manager.transfers.dispatch', $transfer) }}" method="POST" class="d-inline" onsubmit="return confirm('Confirm dispatch of this transfer from your store? Inventory will be deducted.');">
                    @csrf
                    <button type="submit" class="btn btn-primary btn-sm shadow-sm">
                        <i class="fa-solid fa-truck-fast me-1"></i> Dispatch &amp; Send Material
                    </button>
                </form>
                @endif

                {{-- Receive Button (Receiver store or Store Manager/Admin) --}}
                @if(in_array($transfer->status, ['in_transit', 'approved', 'draft']) && ($isReceiverStore || $isAdmin))
                <form action="{{ route('store-manager.transfers.receive', $transfer) }}" method="POST" class="d-inline" onsubmit="return confirm('Confirm receipt of these materials into your site store? Inventory will be credited.');">
                    @csrf
                    <button type="submit" class="btn btn-success btn-sm shadow-sm">
                        <i class="fa-solid fa-box-open me-1"></i> Confirm &amp; Receive Material
                    </button>
                </form>
                @endif
            </div>
        </div>
    </div>
    @endif

    <div class="row g-4 mb-4">
        {{-- Transfer Info --}}
        <div class="col-md-6">
            <div class="card shadow-sm border-0 rounded-3 h-100">
                <div class="card-header bg-white py-3 border-bottom">
                    <h6 class="mb-0 fw-bold text-dark"><i class="fa-solid fa-info-circle me-2 text-primary"></i>Transfer Information</h6>
                </div>
                <div class="card-body">
                    <table class="table table-borderless align-middle mb-0">
                        <tr>
                            <td class="fw-semibold text-muted" style="width: 40%;">Transfer #:</td>
                            <td><strong class="font-monospace text-primary">{{ $transfer->transfer_no }}</strong></td>
                        </tr>
                        <tr>
                            <td class="fw-semibold text-muted">Physical Slip #:</td>
                            <td>
                                @if($transfer->physical_slip_no)
                                    <span class="badge bg-success font-monospace">{{ $transfer->physical_slip_no }}</span>
                                @else
                                    <span class="text-muted small">Not set</span>
                                @endif
                            </td>
                        </tr>
                        <tr>
                            <td class="fw-semibold text-muted">From Store (Origin):</td>
                            <td><span class="fw-bold text-dark">{{ $transfer->fromStore->name ?? 'N/A' }}</span></td>
                        </tr>
                        <tr>
                            <td class="fw-semibold text-muted">To Store (Destination):</td>
                            <td><span class="fw-bold text-dark">{{ $transfer->toStore->name ?? 'N/A' }}</span></td>
                        </tr>
                        <tr>
                            <td class="fw-semibold text-muted">Requested By:</td>
                            <td>{{ $transfer->requestedBy->name ?? 'N/A' }}</td>
                        </tr>
                        <tr>
                            <td class="fw-semibold text-muted">Required Date:</td>
                            <td>{{ $transfer->required_date ? $transfer->required_date->format('M d, Y') : '-' }}</td>
                        </tr>
                        <tr>
                            <td class="fw-semibold text-muted">Status:</td>
                            <td>
                                @php
                                    $sBadge = match($transfer->status) {
                                        'completed'  => 'bg-success',
                                        'in_transit' => 'bg-info text-dark',
                                        'approved'   => 'bg-primary',
                                        'rejected'   => 'bg-danger',
                                        default      => 'bg-secondary',
                                    };
                                @endphp
                                <span class="badge {{ $sBadge }} px-2 py-1">{{ ucfirst(str_replace('_', ' ', $transfer->status)) }}</span>
                            </td>
                        </tr>
                        <tr>
                            <td class="fw-semibold text-muted">Reason / Notes:</td>
                            <td>{{ $transfer->reason ?: 'No notes provided' }}</td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>

        {{-- Verification & Audit Info --}}
        <div class="col-md-6">
            <div class="card shadow-sm border-0 rounded-3 h-100">
                <div class="card-header bg-white py-3 border-bottom">
                    <h6 class="mb-0 fw-bold text-dark"><i class="fa-solid fa-clipboard-check me-2 text-success"></i>Audit &amp; Receipt Records</h6>
                </div>
                <div class="card-body">
                    <table class="table table-borderless align-middle mb-0">
                        <tr>
                            <td class="fw-semibold text-muted" style="width: 40%;">Approved By:</td>
                            <td>{{ $transfer->approvedBy->name ?? '-' }}</td>
                        </tr>
                        <tr>
                            <td class="fw-semibold text-muted">Approved At:</td>
                            <td>{{ $transfer->approved_at ? $transfer->approved_at->format('M d, Y H:i') : '-' }}</td>
                        </tr>
                        <tr>
                            <td class="fw-semibold text-muted">Received By:</td>
                            <td>{{ $transfer->receivedBy->name ?? '-' }}</td>
                        </tr>
                        <tr>
                            <td class="fw-semibold text-muted">Received At:</td>
                            <td>{{ $transfer->received_at ? $transfer->received_at->format('M d, Y H:i') : '-' }}</td>
                        </tr>
                        @if($transfer->rejection_reason)
                        <tr>
                            <td class="fw-semibold text-danger">Rejection Reason:</td>
                            <td class="text-danger">{{ $transfer->rejection_reason }}</td>
                        </tr>
                        @endif
                    </table>
                </div>
            </div>
        </div>
    </div>

    {{-- Transfer Items --}}
    <div class="card shadow-sm border-0 rounded-3">
        <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
            <h6 class="mb-0 fw-bold text-dark"><i class="fa-solid fa-boxes-stacked me-2 text-primary"></i>Transfer Items</h6>
            <span class="badge bg-light text-dark">{{ $transfer->items->count() }} items</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-3">#</th>
                            <th>Product Name</th>
                            <th>Code / SKU</th>
                            <th class="text-end">Requested Qty</th>
                            <th class="text-end">Sent Qty</th>
                            <th class="text-end">Received Qty</th>
                            <th class="text-center">Unit</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($transfer->items as $item)
                        <tr>
                            <td class="ps-3">{{ $loop->iteration }}</td>
                            <td>
                                <strong class="text-dark">{{ $item->product->name ?? 'N/A' }}</strong>
                            </td>
                            <td><span class="font-monospace text-muted small">{{ $item->product->code ?? $item->product->sku ?? '—' }}</span></td>
                            <td class="text-end fw-bold">{{ number_format($item->requested_quantity, 2) }}</td>
                            <td class="text-end text-primary fw-bold">{{ number_format($item->sent_quantity, 2) }}</td>
                            <td class="text-end text-success fw-bold">{{ number_format($item->received_quantity, 2) }}</td>
                            <td class="text-center"><span class="badge bg-light text-dark">{{ $item->unit }}</span></td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="7" class="text-center py-4 text-muted">No items found in this transfer</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div>

{{-- Modal: Update Physical Slip No --}}
<div class="modal fade" id="physicalSlipModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content border-0 shadow">
            <form action="{{ route('store-manager.transfers.physical-slip', $transfer) }}" method="POST">
                @csrf
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title"><i class="fa-solid fa-file-invoice me-2"></i>Physical Slip / Waybill Number</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Physical Slip Number <span class="text-danger">*</span></label>
                        <input type="text" name="physical_slip_no" class="form-control" placeholder="e.g. SLIP-09823 or WB-2026-44" value="{{ old('physical_slip_no', $transfer->physical_slip_no) }}" required>
                        <small class="text-muted">Enter the physical paper slip or delivery receipt number that accompanies this transfer.</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="fa-solid fa-save me-1"></i>Save Physical Slip #</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
