@extends('layouts.app')

@section('title', 'Inventory')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-0">Inventory</h1>
        @if(!empty($isSiteEngineer))
            <p class="text-muted small mb-0 mt-1">
                <i class="fa-solid fa-hard-hat text-warning me-1"></i>
                Site Engineer View — Showing only materials at your assigned site store(s):
                <strong>{{ $assignedStores->pluck('name')->implode(', ') ?: 'No site stores assigned yet' }}</strong>
            </p>
        @endif
    </div>
    <div class="d-flex gap-2">
        @if(auth()->user()->isGlobalAdmin())
        <button type="button" class="btn btn-danger fw-semibold shadow-sm px-3" style="border-radius:10px;" data-bs-toggle="modal" data-bs-target="#adminSkuDeleteModal">
            <i class="fa-solid fa-trash-can me-1"></i> Delete Material by SKU
        </button>
        @endif
        <a href="{{ route('inventory.all-movements') }}" class="btn btn-outline-info fw-semibold shadow-sm px-3" style="border-radius:10px;">
            <i class="fa-solid fa-arrow-right-arrow-left me-1"></i> Stock Movements
        </a>
        @can('inventory.edit')
        <a href="{{ route('inventory.bulk-adjust') }}" class="btn btn-warning fw-semibold shadow-sm px-4" style="border-radius:10px;">
            <i class="fa-solid fa-sliders me-2"></i> Manual Stock Adjustment
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
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label small">Search Name / SKU</label>
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-white"><i class="fa-solid fa-magnifying-glass text-muted"></i></span>
                    <input type="text" name="search" value="{{ request('search') }}" class="form-control" placeholder="Search product or SKU...">
                </div>
            </div>
            <div class="col-md-2">
                <label class="form-label small">{{ !empty($isSiteEngineer) ? 'Assigned Store' : 'Store' }}</label>
                <select name="store_id" class="form-select form-select-sm">
                    <option value="">{{ !empty($isSiteEngineer) ? 'All My Site Stores' : 'All Stores' }}</option>
                    @foreach($stores as $store)
                    <option value="{{ $store->id }}" @selected(request('store_id') == $store->id)>
                        {{ $store->name }}
                    </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label small">Product</label>
                <select name="product_id" class="form-select form-select-sm">
                    <option value="">All Products</option>
                    @foreach($products as $p)
                    <option value="{{ $p->id }}" @selected(request('product_id') == $p->id)>
                        {{ $p->name }} ({{ $p->sku ?: ($p->code ?: '—') }})
                    </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <div class="form-check mt-4">
                    <input class="form-check-input" type="checkbox" name="low_stock" value="1"
                           id="lowStock" @checked(request('low_stock'))>
                    <label class="form-check-label" for="lowStock">Low Stock Only</label>
                </div>
            </div>
            <div class="col-md-2 d-flex gap-1.5 justify-content-end">
                <button type="submit" class="btn btn-primary btn-sm px-2.5">Filter</button>
                <a href="{{ route('inventory.index', array_merge(request()->query(), ['sort' => 'name', 'direction' => (request('sort') === 'name' && request('direction') === 'asc') ? 'desc' : 'asc'])) }}" 
                   class="btn btn-sm {{ (!request('sort') || request('sort') === 'name') ? 'btn-success text-white' : 'btn-outline-secondary' }}" 
                   title="Sort Alphabetically (A-Z)">
                    <i class="fa-solid fa-arrow-down-a-z me-1"></i>A-Z
                </a>
                <a href="{{ route('inventory.index') }}" class="btn btn-outline-secondary btn-sm px-2">Reset</a>
            </div>
        </form>
    </div>
</div>

{{-- Under Maintenance Fixed Assets (Stage 2 GS Verified & Active Tickets) --}}
@if(!empty($underMaintenanceRequests) && $underMaintenanceRequests->isNotEmpty())
<div class="card border-0 shadow-sm mb-4 border-start border-4 border-warning">
    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
        <div>
            <h5 class="card-title mb-0 text-dark fw-bold">
                <i class="fa-solid fa-screwdriver-wrench text-warning me-2"></i>Under Maintenance Fixed Assets
            </h5>
            <small class="text-muted">Equipment &amp; machinery verified and currently undergoing service or repair for this site store.</small>
        </div>
        <span class="badge bg-warning text-dark px-3 py-1.5 rounded-pill fw-bold">
            {{ $underMaintenanceRequests->count() }} In Maintenance
        </span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" style="font-size: 0.88rem;">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3">Ticket #</th>
                        <th>Asset Code / Tag</th>
                        <th>Equipment / Machine Name</th>
                        <th>Site Store</th>
                        <th>Assigned User</th>
                        <th>Current Stage</th>
                        <th>Est. Cost</th>
                        <th class="text-end pe-3">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($underMaintenanceRequests as $mReq)
                    @php
                        $gsBadge = $mReq->gs_status_badge;
                        $unit = $mReq->fixedAssetUnit;
                        $storeName = $unit?->parentAsset?->store?->name ?? 'Site Store';
                    @endphp
                    <tr>
                        <td class="ps-3">
                            <span class="font-monospace fw-bold text-primary">{{ $mReq->request_no }}</span>
                        </td>
                        <td>
                            <span class="badge bg-dark font-monospace">{{ $mReq->asset_code ?: ($unit?->unit_code ?? '—') }}</span>
                        </td>
                        <td>
                            <div class="fw-semibold text-dark">{{ $mReq->asset_name }}</div>
                            @if($unit && ($unit->brand || $unit->model))
                                <small class="text-muted">{{ $unit->brand }} {{ $unit->model }}</small>
                            @endif
                        </td>
                        <td>
                            <span class="badge bg-light text-dark border">
                                <i class="fa-solid fa-location-dot me-1 text-danger"></i>{{ $storeName }}
                            </span>
                        </td>
                        <td>
                            <small class="text-muted">{{ $mReq->employee?->full_name ?? ($unit?->assignedEmployee?->full_name ?? '—') }}</small>
                        </td>
                        <td>
                            <span class="badge {{ $gsBadge['class'] }} px-2 py-1">
                                <i class="fa-solid {{ $gsBadge['icon'] }} me-1"></i>{{ $gsBadge['label'] }}
                            </span>
                        </td>
                        <td class="font-monospace">
                            @if($mReq->total_cost > 0)
                                <span class="fw-bold text-success">{{ number_format($mReq->total_cost, 2) }} ETB</span>
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                        <td class="text-end pe-3">
                            <a href="{{ route('general-service.maintenance.show', $mReq->id) }}" class="btn btn-outline-primary btn-sm px-2 py-1">
                                <i class="fa-solid fa-eye me-1"></i>View Ticket
                            </a>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endif

<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Store</th>
                        <th>
                            <a href="{{ route('inventory.index', array_merge(request()->query(), ['sort' => 'name', 'direction' => (request('sort') === 'name' && request('direction') === 'asc') ? 'desc' : 'asc'])) }}" class="text-dark text-decoration-none d-inline-flex align-items-center gap-1">
                                <span>Product</span>
                                <i class="fa-solid {{ request('direction') === 'desc' && request('sort') === 'name' ? 'fa-arrow-down-z-a text-primary' : 'fa-arrow-down-a-z text-primary' }} small"></i>
                            </a>
                        </th>
                        <th>
                            <a href="{{ route('inventory.index', array_merge(request()->query(), ['sort' => 'sku', 'direction' => (request('sort') === 'sku' && request('direction') === 'asc') ? 'desc' : 'asc'])) }}" class="text-dark text-decoration-none d-inline-flex align-items-center gap-1">
                                <span>SKU Code</span>
                                <i class="fa-solid fa-barcode text-muted small"></i>
                            </a>
                        </th>
                        <th>Category</th>
                        <th>On Hand</th>
                        <th>Reserved</th>
                        <th>Available</th>
                        <th>Unit Cost</th>
                        <th>Total Value</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($inventory as $item)
                    @php
                        $isLow = $item->quantity_on_hand <= $item->min_stock && $item->min_stock > 0;
                        $effectiveCost = (float) (
                            $item->unit_cost ?: (
                                \Illuminate\Support\Facades\DB::table('material_prices')
                                    ->where('product_id', $item->product_id)
                                    ->orderByDesc('effective_date')
                                    ->orderByDesc('id')
                                    ->value('price') ?: (
                                        \Illuminate\Support\Facades\DB::table('purchase_order_items')
                                            ->where('product_id', $item->product_id)
                                            ->orderByDesc('id')
                                            ->value('unit_price') ?: ($item->product->unit_price ?? 0)
                                    )
                            )
                        );
                        $totalVal = $item->quantity_on_hand * $effectiveCost;
                        $skuCode = $item->product->sku ?: ($item->product->code ?: '—');
                    @endphp
                    <tr class="{{ $isLow ? 'table-warning' : '' }}">
                        <td>{{ $item->store->name ?? 'N/A' }}</td>
                        <td>
                            <div class="fw-semibold text-dark">{{ $item->product->name ?? 'N/A' }}</div>
                        </td>
                        <td>
                            <span class="badge bg-light text-primary border font-monospace px-2 py-1">
                                <i class="fa-solid fa-barcode me-1 text-muted"></i>{{ $skuCode }}
                            </span>
                        </td>
                        <td>{{ $item->product->category ?? '—' }}</td>
                        <td>{{ number_format($item->quantity_on_hand, 3) }} <small class="text-muted">{{ $item->product->unit ?? '' }}</small></td>
                        <td>{{ number_format($item->quantity_reserved, 3) }}</td>
                        <td class="{{ $isLow ? 'text-danger fw-bold' : '' }}">
                            {{ number_format($item->quantity_available, 3) }}
                        </td>
                        <td>{{ $effectiveCost ? number_format($effectiveCost, 2) : '—' }}</td>
                        <td class="fw-bold text-success">{{ $totalVal ? number_format($totalVal, 2) : '—' }}</td>
                        <td>
                            @if($isLow)
                            <span class="badge bg-danger"><i class="fa-solid fa-triangle-exclamation me-1"></i>Low</span>
                            @else
                            <span class="badge bg-success">OK</span>
                            @endif
                        </td>
                        <td class="text-end">
                            <a href="{{ route('inventory.show', $item) }}" class="btn btn-sm btn-outline-secondary" title="View Details">
                                <i class="fa-solid fa-eye"></i>
                            </a>
                            @can('inventory.edit')
                            <button class="btn btn-sm btn-outline-primary"
                                    data-bs-toggle="modal" data-bs-target="#adjustModal{{ $item->id }}" title="Stock Adjustment">
                                <i class="fa-solid fa-sliders"></i>
                            </button>
                            @endcan
                            @if(auth()->user()->isGlobalAdmin() && $item->product)
                            <button type="button" class="btn btn-sm btn-outline-danger" title="Global Admin: Complete Delete Material by SKU"
                                    onclick="openSkuDeleteModal('{{ $item->product->sku }}', '{{ addslashes($item->product->name) }}')">
                                <i class="fa-solid fa-trash-can"></i>
                            </button>
                            @endif
                        </td>
                    </tr>

                    @can('inventory.edit')
                    {{-- Adjustment Modal --}}
                    <div class="modal fade" id="adjustModal{{ $item->id }}" tabindex="-1">
                        <div class="modal-dialog">
                            <div class="modal-content">
                                <form method="POST" action="{{ route('inventory.adjust', $item) }}">
                                    @csrf
                                    <div class="modal-header">
                                        <h5 class="modal-title">Adjust: {{ $item->product->name ?? 'Product' }}</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                    </div>
                                    <div class="modal-body">
                                        <div class="mb-3">
                                            <label class="form-label">Type</label>
                                            <select name="type" class="form-select" required>
                                                <option value="in">Stock In (+)</option>
                                                <option value="out">Stock Out (−)</option>
                                                <option value="adjustment">Adjustment</option>
                                            </select>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Quantity ({{ $item->product->unit ?? '' }})</label>
                                            <input type="number" step="0.001" min="0.001" name="quantity"
                                                   class="form-control" required>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Unit Cost</label>
                                            <input type="number" step="0.01" min="0" name="unit_cost"
                                                   class="form-control" value="{{ $item->unit_cost }}">
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Remarks</label>
                                            <textarea name="remarks" rows="2" class="form-control"></textarea>
                                        </div>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                        <button type="submit" class="btn btn-primary">Apply Adjustment</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                    @endcan
                    @empty
                    <tr>
                        <td colspan="11" class="text-center text-muted py-5">
                            <i class="fa-solid fa-box-open fs-2 mb-2 d-block text-secondary"></i>
                            {{ !empty($isSiteEngineer) ? 'No inventory records found for your assigned site store(s).' : 'No inventory records found.' }}
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if($inventory->hasPages())
    <div class="card-footer bg-transparent">
        {{ $inventory->links() }}
    </div>
    @endif
</div>

@include('products.partials.admin_sku_delete_modal')

@endsection
