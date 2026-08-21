@extends('layouts.app')

@section('title', 'Transfers - ' . ($isStoreKeeper ? 'Site Store' : 'Store Hub'))

@section('content')
<div class="container-fluid px-4 py-3">

    {{-- Header --}}
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h4 class="fw-bold mb-0" style="color:var(--brand-800)">
                <i class="fas fa-truck-moving me-2 text-primary"></i>{{ $isStoreKeeper ? 'Site Store Transfers' : 'Transfer List' }}
            </h4>
            <p class="text-muted small mb-0">
                {{ $isStoreKeeper ? 'Manage incoming and outgoing material transfers for ' . ($assignedStore->name ?? 'your store') : 'Manage material transfers between warehouses and project sites' }}
            </p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('store-manager.transfers.create') }}" class="btn btn-primary btn-sm shadow-sm">
                <i class="fas fa-plus me-1"></i>Create Transfer
            </a>
            @if($isStoreKeeper)
            <a href="{{ route('dashboard.store-keeper') }}" class="btn btn-outline-secondary btn-sm">
                <i class="fa-solid fa-arrow-left me-1"></i>Dashboard
            </a>
            @endif
        </div>
    </div>

    {{-- Filters --}}
    <div class="card shadow-sm border-0 rounded-3 mb-4">
        <div class="card-body p-3">
            <form method="GET" class="row g-2 align-items-center">
                <div class="col-md-3">
                    <label class="form-label small fw-semibold text-muted mb-1">Status</label>
                    <select name="status" class="form-select form-select-sm">
                        <option value="">All Statuses</option>
                        <option value="draft" {{ request('status') == 'draft' ? 'selected' : '' }}>Draft / Pending</option>
                        <option value="in_transit" {{ request('status') == 'in_transit' ? 'selected' : '' }}>In Transit</option>
                        <option value="approved" {{ request('status') == 'approved' ? 'selected' : '' }}>Approved</option>
                        <option value="completed" {{ request('status') == 'completed' ? 'selected' : '' }}>Completed</option>
                        <option value="rejected" {{ request('status') == 'rejected' ? 'selected' : '' }}>Rejected</option>
                    </select>
                </div>

                @if(!$isStoreKeeper)
                <div class="col-md-3">
                    <label class="form-label small fw-semibold text-muted mb-1">Store</label>
                    <select name="store_id" class="form-select form-select-sm">
                        <option value="">All Stores</option>
                        @foreach($stores as $store)
                        <option value="{{ $store->id }}" {{ request('store_id') == $store->id ? 'selected' : '' }}>
                            {{ $store->name }}
                        </option>
                        @endforeach
                    </select>
                </div>
                @endif

                <div class="col-md-3">
                    <label class="form-label small fw-semibold text-muted mb-1">&nbsp;</label>
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary btn-sm px-3"><i class="fas fa-search me-1"></i>Filter</button>
                        <a href="{{ route('store-manager.transfers.index') }}" class="btn btn-outline-secondary btn-sm">Reset</a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    {{-- Transfers Table --}}
    <div class="card shadow-sm border-0 rounded-3">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-3">Transfer #</th>
                            <th>Physical Slip #</th>
                            <th>From Store</th>
                            <th>To Store</th>
                            <th>Items</th>
                            <th>Required Date</th>
                            <th>Status</th>
                            <th class="text-end pe-3">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($transfers as $transfer)
                        @php
                            $userStoreId = $assignedStore?->id ?? auth()->user()->store_id;
                            $isIncoming = $userStoreId && ($transfer->to_store_id == $userStoreId);
                            $isOutgoing = $userStoreId && ($transfer->from_store_id == $userStoreId);
                        @endphp
                        <tr>
                            <td class="ps-3">
                                <a href="{{ route('store-manager.transfers.show', $transfer) }}" class="fw-bold font-monospace text-primary text-decoration-none">
                                    {{ $transfer->transfer_no }}
                                </a>
                                @if($isIncoming)
                                    <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 ms-1" style="font-size:0.68rem;">Incoming</span>
                                @elseif($isOutgoing)
                                    <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 ms-1" style="font-size:0.68rem;">Outgoing</span>
                                @endif
                            </td>
                            <td>
                                @if($transfer->physical_slip_no)
                                    <span class="badge bg-success font-monospace" style="font-size:0.75rem;">{{ $transfer->physical_slip_no }}</span>
                                @else
                                    <span class="text-muted small">—</span>
                                @endif
                            </td>
                            <td><span class="fw-semibold text-dark small">{{ $transfer->fromStore->name ?? 'N/A' }}</span></td>
                            <td><span class="fw-semibold text-dark small">{{ $transfer->toStore->name ?? 'N/A' }}</span></td>
                            <td><span class="badge bg-light text-dark">{{ $transfer->items->count() }} items</span></td>
                            <td><small class="text-muted">{{ $transfer->required_date ? $transfer->required_date->format('d M Y') : '-' }}</small></td>
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
                                <span class="badge {{ $sBadge }} small">
                                    {{ ucfirst(str_replace('_', ' ', $transfer->status)) }}
                                </span>
                            </td>
                            <td class="text-end pe-3">
                                <a href="{{ route('store-manager.transfers.show', $transfer) }}" class="btn btn-sm btn-outline-primary">
                                    <i class="fas fa-eye me-1"></i>View
                                </a>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="8" class="text-center py-4 text-muted">
                                <i class="fa-solid fa-truck-moving fa-2x mb-2 d-block opacity-25"></i>
                                No transfers found on record.
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if($transfers->hasPages())
        <div class="card-footer bg-white border-top py-2">
            {{ $transfers->links() }}
        </div>
        @endif
    </div>

</div>
@endsection
