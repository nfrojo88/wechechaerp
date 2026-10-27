@extends('layouts.app')

@section('title', $product->name)

@section('content')
<div class="d-flex align-items-center mb-4 flex-wrap gap-2">
    <a href="{{ route('products.index') }}" class="btn btn-sm btn-outline-secondary me-2">
        <i class="fas fa-arrow-left"></i>
    </a>
    <h1 class="h3 mb-0 me-3">{{ $product->name }}</h1>
    @php
        $statusColors = [
            'Available'         => 'success',
            'In Use'            => 'primary',
            'Under Maintenance' => 'warning',
            'Damaged'           => 'danger',
            'Disposed'          => 'dark',
            'Lost'              => 'secondary',
        ];
        $sColor = $statusColors[$product->asset_status] ?? 'secondary';
    @endphp
    <span class="badge bg-{{ $sColor }} fs-6">{{ $product->asset_status }}</span>
    <code class="ms-1">{{ $product->sku }}</code>

    <div class="ms-auto d-flex gap-2">
        @if(auth()->user()?->isGlobalAdmin())
        <button type="button" class="btn btn-danger shadow-sm" onclick="openSkuDeleteModal('{{ $product->sku }}', '{{ addslashes($product->name) }}')">
            <i class="fas fa-trash-can me-1"></i> Complete Delete (SKU)
        </button>
        @endif
        @can('products.edit')
        <a href="{{ route('products.edit', $product) }}" class="btn btn-primary">
            <i class="fas fa-pen me-1"></i> Edit Product
        </a>
        @endcan
    </div>
</div>

<div class="row g-4">
    {{-- ── Left: Details ──────────────────────────────────── --}}
    <div class="col-md-5">
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-transparent py-3">
                <h5 class="mb-0"><i class="fas fa-box me-2 text-primary"></i>Product Details</h5>
            </div>
            <div class="card-body">
                <table class="table table-borderless table-sm mb-0">
                    <tr><td class="text-muted w-45">SKU</td><td class="fw-semibold"><code>{{ $product->sku }}</code></td></tr>
                    <tr><td class="text-muted">Category</td><td class="fw-semibold">{{ $product->category ?? '—' }}</td></tr>
                    <tr><td class="text-muted">Sub-Category</td><td class="fw-semibold">{{ $product->sub_category ?? '—' }}</td></tr>
                    <tr><td class="text-muted">Unit</td><td class="fw-semibold"><span class="badge bg-light text-dark">{{ $product->unit }}</span></td></tr>
                    <tr><td class="text-muted">Unit Price</td><td class="fw-semibold">ETB {{ number_format($product->unit_price, 2) }}</td></tr>
                    <tr><td class="text-muted">Selling Price</td><td class="fw-semibold">ETB {{ number_format($product->selling_price, 2) }}</td></tr>
                    <tr><td class="text-muted">Max Stock</td><td class="fw-semibold">{{ number_format($product->max_stock, 2) }}</td></tr>
                    <tr><td class="text-muted">Reorder Level</td><td class="fw-semibold">{{ $product->reorder_level }}</td></tr>
                    @if($product->carton_size)
                    <tr><td class="text-muted">Carton Size</td><td class="fw-semibold">{{ $product->carton_size }}</td></tr>
                    @endif
                    @if($product->standard_length > 0)
                    <tr><td class="text-muted">Std. Length</td><td class="fw-semibold">{{ number_format($product->standard_length, 2) }} m</td></tr>
                    @endif
                    @if($product->standard_width > 0)
                    <tr><td class="text-muted">Std. Width</td><td class="fw-semibold">{{ number_format($product->standard_width, 3) }} m</td></tr>
                    @endif
                    <tr><td class="text-muted">Purchase Threshold</td><td class="fw-semibold">{{ $product->purchase_threshold }}%</td></tr>
                </table>
            </div>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="card-header bg-transparent py-3">
                <h5 class="mb-0"><i class="fas fa-toolbox me-2 text-warning"></i>Asset / Equipment Info</h5>
            </div>
            <div class="card-body">
                <table class="table table-borderless table-sm mb-0">
                    <tr><td class="text-muted w-45">Condition</td><td class="fw-semibold">{{ $product->equipment_condition }}</td></tr>
                    <tr><td class="text-muted">Assigned To</td><td class="fw-semibold">{{ $product->assigned_to }}</td></tr>
                    <tr><td class="text-muted">Location</td><td class="fw-semibold">{{ $product->current_location }}</td></tr>
                    @if($product->baseline_date)
                    <tr><td class="text-muted">Baseline Date</td><td class="fw-semibold">{{ $product->baseline_date->format('d M Y') }}</td></tr>
                    @endif
                    <tr><td class="text-muted">Created</td><td class="fw-semibold">{{ $product->created_at->format('d M Y') }}</td></tr>
                </table>
            </div>
        </div>
    </div>

    {{-- ── Right: Inventory ────────────────────────────────── --}}
    <div class="col-md-7">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-transparent py-3 d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fas fa-warehouse me-2 text-success"></i>Inventory Locations</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Store</th>
                                <th>On Hand</th>
                                <th>Reserved</th>
                                <th>Available</th>
                                <th>Unit Cost</th>
                                <th>Status</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($product->inventory as $inv)
                            @php $isLow = $inv->quantity_on_hand <= $inv->min_stock && $inv->min_stock > 0; @endphp
                            <tr>
                                <td>
                                    <a href="{{ route('stores.show', $inv->store) }}" class="fw-semibold text-decoration-none">
                                        {{ $inv->store->name }}
                                    </a>
                                </td>
                                <td>{{ number_format($inv->quantity_on_hand, 3) }}</td>
                                <td>{{ number_format($inv->quantity_reserved, 3) }}</td>
                                <td class="{{ $isLow ? 'text-danger fw-bold' : '' }}">
                                    {{ number_format($inv->quantity_available, 3) }}
                                </td>
                                <td>{{ $inv->unit_cost ? number_format($inv->unit_cost, 2) : '—' }}</td>
                                <td>
                                    @if($isLow)
                                    <span class="badge bg-danger">Low</span>
                                    @else
                                    <span class="badge bg-success">OK</span>
                                    @endif
                                </td>
                                <td class="text-end">
                                    <a href="{{ route('inventory.show', $inv) }}" class="btn btn-sm btn-outline-secondary">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                </td>
                            </tr>
                            @empty
                            <tr><td colspan="7" class="text-center text-muted py-4">This product is not in any store inventory yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

@include('products.partials.admin_sku_delete_modal')

@endsection
