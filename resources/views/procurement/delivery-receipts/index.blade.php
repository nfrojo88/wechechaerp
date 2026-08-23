@extends('layouts.app')
@section('title', 'Receipts & Delivery Verification')

@section('content')
<div class="container-fluid py-3">
    <!-- Header -->
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
        <div>
            <h1 class="h3 mb-1 text-dark fw-bold">
                <i class="fa-solid fa-receipt text-warning me-2"></i>Receipts & Delivery Verification
            </h1>
            <p class="text-muted small mb-0">Verify goods delivery receipts, supplier delivery challans, and warehouse receipt vouchers.</p>
        </div>
        <div class="d-flex gap-2">
            @canany(['purchases.receive', 'purchases.*'])
                <a href="{{ route('delivery-receipts.create') }}" class="btn btn-primary rounded-pill px-4 shadow-sm fw-bold">
                    <i class="fas fa-plus me-1"></i> New Delivery Receipt
                </a>
            @endcanany
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show rounded-4 shadow-sm border-0 d-flex align-items-center">
            <i class="fas fa-check-circle fa-lg me-2"></i>
            <div>{{ session('success') }}</div>
            <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <!-- Receipts Table Card -->
    <div class="card shadow-sm border-0 rounded-4 overflow-hidden mb-4">
        <div class="card-header bg-white border-0 pt-4 px-4 pb-0 d-flex justify-content-between align-items-center">
            <h5 class="fw-bold text-dark mb-0">
                <i class="fas fa-boxes-packing text-primary me-2"></i>Delivery Receipts & Vouchers
            </h5>
            <span class="badge bg-light text-secondary border rounded-pill px-3 py-2">
                {{ $receipts->total() }} Total Records
            </span>
        </div>
        <div class="card-body p-0 mt-3">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light text-muted small text-uppercase">
                        <tr>
                            <th class="px-4 py-3">Receipt No (DR #)</th>
                            <th class="py-3">PO Reference</th>
                            <th class="py-3">Supplier</th>
                            <th class="py-3">Store / Site</th>
                            <th class="py-3">Received By / Date</th>
                            <th class="py-3 text-center">Status</th>
                            <th class="px-4 py-3 text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($receipts as $dr)
                            <tr>
                                <td class="px-4">
                                    <span class="fw-bold text-primary font-monospace">{{ $dr->dr_no }}</span>
                                    @if($dr->challan_no)
                                        <div class="small text-muted">Challan: {{ $dr->challan_no }}</div>
                                    @endif
                                </td>
                                <td>
                                    @if($dr->purchaseOrder)
                                        <span class="badge bg-light text-dark border">{{ $dr->purchaseOrder->po_no }}</span>
                                    @else
                                        <span class="text-muted small">Direct Delivery</span>
                                    @endif
                                </td>
                                <td>
                                    <div class="fw-semibold text-dark">{{ $dr->purchaseOrder->supplier->name ?? 'N/A' }}</div>
                                    @if($dr->vehicle_no)
                                        <small class="text-muted"><i class="fas fa-truck me-1"></i>{{ $dr->vehicle_no }}</small>
                                    @endif
                                </td>
                                <td>
                                    <span class="badge bg-primary-subtle text-primary border">{{ $dr->store->name ?? 'General Store' }}</span>
                                </td>
                                <td>
                                    <div class="fw-semibold text-dark">{{ $dr->receivedBy->name ?? 'System' }}</div>
                                    <small class="text-muted">{{ $dr->received_date ? $dr->received_date->format('M d, Y') : '-' }}</small>
                                </td>
                                <td class="text-center">
                                    @if($dr->status === 'verified')
                                        <span class="badge bg-success-subtle text-success border border-success px-3 py-2 rounded-pill">
                                            <i class="fas fa-check-circle me-1"></i> Verified
                                        </span>
                                    @else
                                        <span class="badge bg-warning-subtle text-warning-emphasis border border-warning px-3 py-2 rounded-pill">
                                            <i class="fas fa-clock me-1"></i> {{ ucfirst($dr->status ?? 'Pending') }}
                                        </span>
                                    @endif
                                </td>
                                <td class="px-4 text-end">
                                    <a href="{{ route('delivery-receipts.show', $dr->id) }}" class="btn btn-sm btn-outline-primary rounded-pill px-3 shadow-sm">
                                        <i class="fas fa-eye me-1"></i> View Receipt
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center py-5 text-muted">
                                    <i class="fas fa-receipt fa-3x mb-3 text-secondary opacity-50"></i>
                                    <h6>No delivery receipts found</h6>
                                    <p class="small text-muted mb-0">Delivery receipts and goods receipt vouchers will appear here once recorded.</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if($receipts->hasPages())
            <div class="card-footer bg-white border-0 py-3">
                {{ $receipts->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
