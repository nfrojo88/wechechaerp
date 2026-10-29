@extends('layouts.app')

@section('title', 'Site Stock Movements')

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
    <div>
        <div class="d-flex align-items-center gap-2">
            <h1 class="h3 mb-0">{{ !empty($isSiteEngineer) ? 'Site Stock Movements' : 'Stock Movement History' }}</h1>
            <span class="badge bg-secondary-subtle text-secondary border">የዕቃዎች ዝውውር መዝገብ</span>
        </div>
        @if(!empty($isSiteEngineer))
            <p class="text-muted small mb-0 mt-1">
                <i class="fa-solid fa-hard-hat text-warning me-1"></i>
                Site Engineer View &mdash; Movements strictly scoped to your assigned site store(s):
                <strong>{{ $assignedStores->pluck('name')->implode(', ') ?: 'No site stores assigned yet' }}</strong>
            </p>
        @else
            <p class="text-muted small mb-0 mt-1">Comprehensive log of inventory intake, dispatches, transfers, and adjustments.</p>
        @endif
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('inventory.index') }}" class="btn btn-outline-primary fw-semibold shadow-sm px-3" style="border-radius:10px;">
            <i class="fa-solid fa-boxes-stacked me-1"></i> Stock on Hand
        </a>
        @can('inventory.edit')
        <a href="{{ route('inventory.bulk-adjust') }}" class="btn btn-warning fw-semibold shadow-sm px-3" style="border-radius:10px;">
            <i class="fa-solid fa-sliders me-1"></i> Stock Adjustment
        </a>
        @endcan
    </div>
</div>

@if(!empty($isSiteEngineer) && $assignedStores->isEmpty())
    <div class="alert alert-warning border-0 shadow-sm d-flex align-items-center mb-4">
        <i class="fa-solid fa-triangle-exclamation fs-4 me-3 text-warning"></i>
        <div>
            <strong>No Site Assigned</strong>: Your user account is not currently linked to an active project or site store. Please contact the administrator or project coordinator to assign you to a project site.
        </div>
    </div>
@endif

{{-- Filters --}}
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <form method="GET" action="{{ route('inventory.all-movements') }}" class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label small fw-semibold">Search Product / Remarks</label>
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-white"><i class="fa-solid fa-magnifying-glass text-muted"></i></span>
                    <input type="text" name="search" value="{{ request('search') }}" class="form-control" placeholder="Product name, SKU, remarks...">
                </div>
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-semibold">{{ !empty($isSiteEngineer) ? 'Assigned Store' : 'Store' }}</label>
                <select name="store_id" class="form-select form-select-sm">
                    <option value="">{{ !empty($isSiteEngineer) ? 'All My Site Stores' : 'All Stores' }}</option>
                    @foreach($stores as $store)
                    <option value="{{ $store->id }}" @selected(request('store_id') == $store->id)>
                        {{ $store->name }}
                    </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-semibold">Product</label>
                <select name="product_id" class="form-select form-select-sm">
                    <option value="">All Products</option>
                    @foreach($products as $p)
                    <option value="{{ $p->id }}" @selected(request('product_id') == $p->id)>
                        {{ $p->name }}
                    </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-semibold">Movement Type</label>
                <select name="type" class="form-select form-select-sm">
                    <option value="">All Types</option>
                    <option value="in" @selected(request('type') === 'in')>Stock In / Intake</option>
                    <option value="out" @selected(request('type') === 'out')>Stock Out / Dispatch</option>
                    <option value="transfer" @selected(request('type') === 'transfer')>Transfer</option>
                    <option value="adjustment" @selected(request('type') === 'adjustment')>Adjustment</option>
                    <option value="reserve" @selected(request('type') === 'reserve')>Reserved</option>
                    <option value="release" @selected(request('type') === 'release')>Released</option>
                </select>
            </div>
            <div class="col-md-3 d-flex gap-1.5 justify-content-end">
                <button type="submit" class="btn btn-primary btn-sm px-3">
                    <i class="fa-solid fa-filter me-1"></i> Filter
                </button>
                <a href="{{ route('inventory.all-movements') }}" class="btn btn-outline-secondary btn-sm px-2">
                    Reset
                </a>
            </div>
        </form>
    </div>
</div>

{{-- Movements Table --}}
<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th style="min-width: 140px;">Date & Time</th>
                        <th>Store</th>
                        <th style="min-width: 200px;">Product</th>
                        <th>Type</th>
                        <th class="text-end" style="min-width: 120px;">Quantity</th>
                        <th class="text-end" style="min-width: 120px;">Balance Before</th>
                        <th class="text-end" style="min-width: 120px;">Balance After</th>
                        <th>Reference</th>
                        <th>Performed By</th>
                        <th style="min-width: 220px;">Remarks</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($movements as $mov)
                    @php
                        $badge = match(strtolower($mov->type ?? '')) {
                            'in', 'purchase_intake', 'grn', 'fa_register' => 'success',
                            'out', 'dispatch', 'issue', 'fa_damaged' => 'danger',
                            'transfer', 'transfer_in', 'transfer_out' => 'info',
                            'adjustment' => 'warning',
                            'reserve', 'fa_assign' => 'secondary',
                            'release', 'fa_return' => 'primary',
                            default => 'secondary'
                        };

                        $qty = (float) $mov->quantity;
                        $isPositive = $qty > 0;
                        $isNegative = $qty < 0;
                        $storeName = $mov->inventory?->store?->name ?? 'N/A';
                        $productName = $mov->inventory?->product?->name ?? 'N/A';
                        $skuCode = $mov->inventory?->product?->sku ?: ($mov->inventory?->product?->code ?: null);
                        $unit = $mov->inventory?->product?->unit ?? '';
                    @endphp
                    <tr>
                        <td>
                            <div class="fw-semibold text-dark">{{ $mov->created_at ? $mov->created_at->format('d M Y') : '—' }}</div>
                            <div class="text-muted small">{{ $mov->created_at ? $mov->created_at->format('H:i') : '' }}</div>
                        </td>
                        <td>
                            <span class="badge bg-light text-dark border">{{ $storeName }}</span>
                        </td>
                        <td>
                            <div class="fw-semibold text-dark">{{ $productName }}</div>
                            @if($skuCode)
                                <small class="text-muted font-monospace"><i class="fa-solid fa-barcode me-1"></i>{{ $skuCode }}</small>
                            @endif
                        </td>
                        <td>
                            <span class="badge bg-{{ $badge }} text-uppercase">{{ $mov->type ?? 'MOVEMENT' }}</span>
                        </td>
                        <td class="text-end font-monospace fw-bold fs-6 {{ $isPositive ? 'text-success' : ($isNegative ? 'text-danger' : 'text-secondary') }}">
                            {{ $isPositive ? '+' : ($isNegative ? '-' : '') }}{{ number_format(abs($qty), 3) }}
                            <small class="text-muted fw-normal ms-1">{{ $unit }}</small>
                        </td>
                        <td class="text-end font-monospace text-muted">
                            {{ $mov->balance_before !== null ? number_format((float)$mov->balance_before, 3) : '—' }}
                        </td>
                        <td class="text-end font-monospace fw-semibold text-dark">
                            {{ $mov->balance_after !== null ? number_format((float)$mov->balance_after, 3) : '—' }}
                        </td>
                        <td>
                            @if($mov->reference_type && $mov->reference_id)
                                <span class="badge bg-light text-secondary border font-monospace">
                                    {{ class_basename($mov->reference_type) }} #{{ $mov->reference_id }}
                                </span>
                            @else
                                <span class="text-muted">&mdash;</span>
                            @endif
                        </td>
                        <td>
                            <div class="small fw-semibold text-dark">{{ $mov->performer?->name ?? 'System' }}</div>
                        </td>
                        <td class="text-wrap small text-muted">
                            {{ $mov->remarks ?: '—' }}
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="10" class="text-center text-muted py-5">
                            <i class="fa-solid fa-arrow-right-arrow-left fs-2 mb-2 d-block text-secondary"></i>
                            {{ !empty($isSiteEngineer) ? 'No stock movements recorded for your assigned site store(s).' : 'No stock movements found.' }}
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if($movements->hasPages())
    <div class="card-footer bg-transparent">
        {{ $movements->links() }}
    </div>
    @endif
</div>
@endsection
