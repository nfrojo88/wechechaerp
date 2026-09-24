@extends('layouts.app')

@section('title', 'All Inventory - Store Manager')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-0"><i class="fas fa-boxes-stacked me-2 text-primary"></i>All Inventory
                @if(request('store_id'))
                    <span class="text-muted fs-6 ms-2">— {{ $stores->where('id', request('store_id'))->first()->name ?? '' }}</span>
                @else
                    <span class="badge bg-primary ms-2 fs-6">All Stores</span>
                @endif
            </h4>
            @if($isGrouped)
                <small class="text-muted"><i class="fas fa-info-circle me-1"></i>Showing combined totals. Click any product row to see per-store breakdown.</small>
            @endif
        </div>
        <div class="d-flex gap-2">
            <button class="btn btn-success fw-semibold" data-bs-toggle="modal" data-bs-target="#addStockModal">
                <i class="fas fa-plus-circle me-1"></i> Add / Adjust Stock
            </button>
            <a href="{{ route('inventory.bulk-adjust') }}" class="btn btn-outline-primary fw-semibold">
                <i class="fas fa-list-check me-1"></i> Bulk Adjust
            </a>
        </div>
    </div>

    <div class="card shadow-sm mb-4">
        <div class="card-body py-3">
            <form method="GET" class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label class="form-label fw-semibold small text-uppercase">Store</label>
                    <select name="store_id" class="form-select">
                        <option value="">All Stores (Grouped)</option>
                        @foreach($stores as $store)
                            <option value="{{ $store->id }}" {{ request('store_id') == $store->id ? 'selected' : '' }}>{{ $store->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold small text-uppercase">Search Product</label>
                    <input type="text" name="search" class="form-control" value="{{ request('search') }}" placeholder="Product name or code...">
                </div>
                <div class="col-md-2 pt-4">
                    <div class="form-check">
                        <input type="checkbox" name="low_stock" value="1" class="form-check-input" id="lowStockCheck" {{ request('low_stock') ? 'checked' : '' }}>
                        <label class="form-check-label fw-semibold" for="lowStockCheck">Low Stock Only</label>
                    </div>
                </div>
                <div class="col-md-3 d-flex gap-2">
                    <button type="submit" class="btn btn-primary flex-fill"><i class="fas fa-filter me-1"></i>Filter</button>
                    <a href="{{ route('store-manager.inventory.all') }}" class="btn btn-secondary flex-fill">Reset</a>
                </div>
            </form>
        </div>
    </div>

    <div class="card shadow-sm border-0">
        <div class="card-body p-0">
            <div class="table-responsive">
                @if($isGrouped)
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-dark">
                        <tr>
                            <th>PRODUCT</th>
                            <th class="text-center">STORES</th>
                            <th class="text-end">TOTAL ON HAND</th>
                            <th class="text-end">TOTAL RESERVED</th>
                            <th class="text-end">TOTAL AVAILABLE</th>
                            <th class="text-end">UNIT COST</th>
                            <th class="text-end">TOTAL VALUE</th>
                            <th class="text-center">STATUS</th>
                            <th class="text-center">DETAILS</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($inventory as $item)
                        @php
                            if ($item['total_on_hand'] <= 0) { $sText = 'Out of Stock'; $sBg = 'dark'; }
                            elseif ($item['total_on_hand'] <= $item['total_min_stock']) { $sText = 'Low Stock'; $sBg = 'danger'; }
                            elseif ($item['total_on_hand'] <= $item['total_min_stock'] * 1.5) { $sText = 'Near Min'; $sBg = 'warning'; }
                            else { $sText = 'Available'; $sBg = 'success'; }
                        @endphp
                        <tr class="grouped-product-row" style="cursor:pointer;"
                            data-product-id="{{ $item['product_id'] }}"
                            data-product-name="{{ $item['product_name'] }}"
                            data-product-code="{{ $item['product_code'] }}"
                            data-product-category="{{ $item['product_category'] }}"
                            data-product-unit="{{ $item['product_unit'] }}"
                            data-product-desc="{{ $item['product_desc'] }}"
                            data-total-on-hand="{{ number_format($item['total_on_hand'], 3) }}"
                            data-total-reserved="{{ number_format($item['total_reserved'], 3) }}"
                            data-total-available="{{ number_format($item['total_available'], 3) }}"
                            data-total-min-stock="{{ number_format($item['total_min_stock'], 3) }}"
                            data-unit-cost="{{ number_format($item['effective_cost'], 2) }}"
                            data-total-value="{{ number_format($item['total_value'], 2) }}"
                            data-status="{{ $sText }}"
                            data-status-bg="{{ $sBg }}"
                            data-store-count="{{ $item['store_count'] }}"
                            data-stores="{{ json_encode($item['stores_breakdown']) }}">
                            <td>
                                <strong class="text-primary">{{ $item['product_name'] }}</strong>
                                <span class="badge bg-light text-primary border ms-1 fw-semibold">{{ $item['product_unit'] }}</span>
                                @if($item['product_code'])
                                    <br><small class="text-muted"><i class="fas fa-barcode me-1"></i>{{ $item['product_code'] }}</small>
                                @endif
                            </td>
                            <td class="text-center">
                                <span class="badge bg-secondary rounded-pill">
                                    <i class="fas fa-warehouse me-1"></i>{{ $item['store_count'] }} {{ Str::plural('store', $item['store_count']) }}
                                </span>
                            </td>
                            <td class="text-end fw-bold fs-6">
                                {{ number_format($item['total_on_hand'], 3) }}
                                <small class="text-muted fw-normal fs-7 ms-1">{{ $item['product_unit'] }}</small>
                            </td>
                            <td class="text-end text-warning">
                                {{ number_format($item['total_reserved'], 3) }}
                                <small class="text-muted fw-normal fs-7 ms-1">{{ $item['product_unit'] }}</small>
                            </td>
                            <td class="text-end text-success fw-semibold">
                                {{ number_format($item['total_available'], 3) }}
                                <small class="text-muted fw-normal fs-7 ms-1">{{ $item['product_unit'] }}</small>
                            </td>
                            <td class="text-end text-muted">{{ number_format($item['effective_cost'], 2) }}</td>
                            <td class="text-end fw-bold text-success">{{ number_format($item['total_value'], 2) }}</td>
                            <td class="text-center"><span class="badge bg-{{ $sBg }}">{{ $sText }}</span></td>
                            <td class="text-center">
                                <button type="button" class="btn btn-sm btn-outline-info">
                                    <i class="fas fa-store me-1"></i>Per Store
                                </button>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="9" class="text-center py-5 text-muted">
                                <i class="fas fa-box-open fa-3x mb-3 text-secondary d-block"></i>No inventory found.
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
                @else
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>PRODUCT</th>
                            <th>STORE</th>
                            <th class="text-end">ON HAND</th>
                            <th class="text-end">RESERVED</th>
                            <th class="text-end">AVAILABLE</th>
                            <th class="text-end">MIN STOCK</th>
                            <th class="text-end">UNIT COST</th>
                            <th class="text-end">TOTAL VALUE</th>
                            <th class="text-center">STATUS</th>
                            <th class="text-center">ACTION</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($inventory as $item)
                        @php
                            $eCost = (float)(
                                $item->unit_cost ?: (
                                    \Illuminate\Support\Facades\DB::table('material_prices')
                                        ->where('product_id', $item->product_id)->orderByDesc('effective_date')->orderByDesc('id')->value('price') ?: (
                                            \Illuminate\Support\Facades\DB::table('purchase_order_items')
                                                ->where('product_id', $item->product_id)->orderByDesc('id')->value('unit_price') ?: ($item->product->unit_price ?? 0)
                                        )
                                )
                            );
                            $tVal = $item->quantity_on_hand * $eCost;
                            $rQty = (float)($item->quantity_reserved ?? 0);
                            $aQty = max(0, $item->quantity_on_hand - $rQty);
                            if ($item->quantity_on_hand <= 0) { $sBg='dark'; $sText='Out of Stock'; }
                            elseif ($item->quantity_on_hand <= $item->min_stock) { $sBg='danger'; $sText='Low Stock'; }
                            elseif ($item->quantity_on_hand <= $item->min_stock * 1.5) { $sBg='warning'; $sText='Near Min'; }
                            else { $sBg='success'; $sText='Available'; }
                            $unitStr = $item->product->unit ?? 'pcs';
                        @endphp
                        <tr class="single-product-row" style="cursor:pointer;"
                            data-product-id="{{ $item->product_id }}"
                            data-name="{{ $item->product->name ?? 'N/A' }}"
                            data-code="{{ $item->product->code ?? '' }}"
                            data-store="{{ $item->store->name ?? 'N/A' }}"
                            data-store-id="{{ $item->store_id }}"
                            data-store-type="{{ $item->store->type ?? 'Site Store' }}"
                            data-on-hand="{{ number_format($item->quantity_on_hand, 3) }}"
                            data-reserved="{{ number_format($rQty, 3) }}"
                            data-available="{{ number_format($aQty, 3) }}"
                            data-min-stock="{{ number_format($item->min_stock, 3) }}"
                            data-unit-cost="{{ number_format($eCost, 2) }}"
                            data-total-val="{{ number_format($tVal, 2) }}"
                            data-status="{{ $sText }}"
                            data-status-bg="{{ $sBg }}"
                            data-category="{{ $item->product->category ?? 'General Material' }}"
                            data-unit="{{ $unitStr }}"
                            data-description="{{ $item->product->description ?? '' }}">
                            <td>
                                <strong class="text-primary">{{ $item->product->name ?? 'N/A' }}</strong>
                                <span class="badge bg-light text-primary border ms-1 fw-semibold">{{ $unitStr }}</span>
                                @if($item->product && $item->product->code)
                                    <br><small class="text-muted"><i class="fas fa-barcode me-1"></i>{{ $item->product->code }}</small>
                                @endif
                            </td>
                            <td>
                                <span class="badge bg-light text-dark border"><i class="fas fa-warehouse me-1 text-secondary"></i>{{ $item->store->name ?? 'N/A' }}</span>
                                <br><small class="text-muted">{{ $item->store->type ?? '' }}</small>
                            </td>
                            <td class="text-end fw-bold">
                                {{ number_format($item->quantity_on_hand, 3) }}
                                <small class="text-muted fw-normal ms-1">{{ $unitStr }}</small>
                            </td>
                            <td class="text-end text-warning">
                                {{ number_format($rQty, 3) }}
                                <small class="text-muted fw-normal ms-1">{{ $unitStr }}</small>
                            </td>
                            <td class="text-end text-success fw-semibold">
                                {{ number_format($aQty, 3) }}
                                <small class="text-muted fw-normal ms-1">{{ $unitStr }}</small>
                            </td>
                            <td class="text-end text-muted">
                                {{ number_format($item->min_stock, 3) }}
                                <small class="text-muted fw-normal ms-1">{{ $unitStr }}</small>
                            </td>
                            <td class="text-end">{{ number_format($eCost, 2) }}</td>
                            <td class="text-end fw-bold text-success">{{ number_format($tVal, 2) }}</td>
                            <td class="text-center"><span class="badge bg-{{ $sBg }}">{{ $sText }}</span></td>
                            <td class="text-center">
                                <button type="button" class="btn btn-sm btn-outline-primary">
                                    <i class="fas fa-eye me-1"></i>View
                                </button>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="10" class="text-center py-5 text-muted">
                                <i class="fas fa-box-open fa-3x mb-3 text-secondary d-block"></i>No inventory found.
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
                @endif
            </div>
        </div>
        <div class="card-footer bg-white py-3">{{ $inventory->links() }}</div>
    </div>
</div>

{{-- Add Stock Modal --}}
<div class="modal fade" id="addStockModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content shadow-lg border-0">
            <form action="{{ route('inventory.save-single') }}" method="POST">
                @csrf
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title fw-bold"><i class="fas fa-plus-circle me-2"></i>Add / Adjust Stock</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label fw-bold">Store <span class="text-danger">*</span></label>
                            <select name="store_id" id="addStockStoreSelect" class="form-select" required>
                                <option value="">— Select Store —</option>
                                @foreach($stores as $s)
                                    <option value="{{ $s->id }}">{{ $s->name }} ({{ strtoupper($s->type ?? 'site') }})</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-bold">Product <span class="text-danger">*</span></label>
                            <select name="product_id" id="addStockProductSelect" class="form-select" required>
                                <option value="">— Select Product —</option>
                                @foreach($products as $p)
                                    <option value="{{ $p->id }}">{{ $p->name }} ({{ $p->code }}) [{{ $p->unit ?? 'pcs' }}]</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">New Qty <span class="text-danger">*</span></label>
                            <input type="number" step="0.001" min="0" name="quantity" class="form-control" placeholder="0.000" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Unit Cost (ETB)</label>
                            <input type="number" step="0.01" min="0" name="unit_cost" class="form-control" placeholder="Optional">
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success fw-bold"><i class="fas fa-check me-1"></i>Save</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Single Store Detail Modal --}}
<div class="modal fade" id="singleDetailModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-primary text-white py-3">
                <div>
                    <h5 class="modal-title fw-bold mb-0" id="sdName">Product Name</h5>
                    <small id="sdCode" class="opacity-75"></small>
                </div>
                <span id="sdStatus" class="badge bg-success ms-auto me-3 px-3 py-2 fs-6">Available</span>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                {{-- Tabs Navigation --}}
                <ul class="nav nav-pills nav-fill bg-light p-1 rounded-3 mb-4 border" id="sdTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active fw-bold py-2" id="sd-tab-overview-btn" data-bs-toggle="pill" data-bs-target="#sd-tab-overview" type="button" role="tab">
                            <i class="fas fa-cubes me-1 text-primary"></i> Store Stock & Specs
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link fw-bold py-2" id="sd-tab-movements-btn" data-bs-toggle="pill" data-bs-target="#sd-tab-movements" type="button" role="tab">
                            <i class="fas fa-history me-1 text-info"></i> Every Movement
                            <span class="badge bg-info text-dark rounded-pill ms-1" id="sdMovementCountBadge">0</span>
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link fw-bold py-2" id="sd-tab-adjustments-btn" data-bs-toggle="pill" data-bs-target="#sd-tab-adjustments" type="button" role="tab">
                            <i class="fas fa-sliders-h me-1 text-warning"></i> Manual Adjustments
                            <span class="badge bg-warning text-dark rounded-pill ms-1" id="sdAdjustmentCountBadge">0</span>
                        </button>
                    </li>
                </ul>

                <div class="tab-content" id="sdTabContent">
                    {{-- Tab 1: Store Stock & Specs --}}
                    <div class="tab-pane fade show active" id="sd-tab-overview" role="tabpanel">
                        <div class="alert alert-light border d-flex align-items-center justify-content-between mb-4">
                            <div>
                                <span class="text-muted small d-block">STORE LOCATION</span>
                                <strong class="h6 mb-0" id="sdStore">Store</strong>
                            </div>
                            <span class="badge bg-secondary" id="sdStoreType">Site Store</span>
                        </div>
                        <div class="row g-3 mb-4">
                            <div class="col-md-4">
                                <div class="card border-0 bg-primary bg-opacity-10 p-3 rounded-3 text-center">
                                    <span class="text-primary fw-semibold small">ON HAND</span>
                                    <h4 class="fw-bold text-primary mb-0 mt-1" id="sdOnHand">0.000</h4>
                                    <small class="text-muted" id="sdUnit1">units</small>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card border-0 bg-warning bg-opacity-10 p-3 rounded-3 text-center">
                                    <span class="text-warning fw-semibold small">RESERVED</span>
                                    <h4 class="fw-bold text-warning mb-0 mt-1" id="sdReserved">0.000</h4>
                                    <small class="text-muted" id="sdUnit2">units</small>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card border-0 bg-success bg-opacity-10 p-3 rounded-3 text-center">
                                    <span class="text-success fw-semibold small">AVAILABLE</span>
                                    <h4 class="fw-bold text-success mb-0 mt-1" id="sdAvailable">0.000</h4>
                                    <small class="text-muted" id="sdUnit3">units</small>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card border-0 bg-secondary bg-opacity-10 p-3 rounded-3 text-center">
                                    <span class="text-secondary fw-semibold small">MIN STOCK</span>
                                    <h4 class="fw-bold text-secondary mb-0 mt-1" id="sdMinStock">0.000</h4>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card border-0 bg-info bg-opacity-10 p-3 rounded-3 text-center">
                                    <span class="text-info fw-semibold small">UNIT COST (ETB)</span>
                                    <h4 class="fw-bold text-info mb-0 mt-1" id="sdUnitCost">0.00</h4>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card border-0 bg-dark bg-opacity-10 p-3 rounded-3 text-center">
                                    <span class="text-dark fw-semibold small">TOTAL VALUE (ETB)</span>
                                    <h4 class="fw-bold text-dark mb-0 mt-1" id="sdTotalVal">0.00</h4>
                                </div>
                            </div>
                        </div>
                        <div class="card border-0 bg-light p-3">
                            <h6 class="fw-bold text-muted mb-2"><i class="fas fa-info-circle me-1"></i>Specifications</h6>
                            <div class="row g-2 small">
                                <div class="col-6"><strong>Category:</strong> <span id="sdCategory">—</span></div>
                                <div class="col-6"><strong>Unit:</strong> <span id="sdUnitSpec">—</span></div>
                                <div class="col-12 mt-1"><strong>Description:</strong><p class="mb-0 text-muted mt-1" id="sdDesc">—</p></div>
                            </div>
                        </div>
                    </div>

                    {{-- Tab 2: Movements History --}}
                    <div class="tab-pane fade" id="sd-tab-movements" role="tabpanel">
                        <div class="card border rounded-3 mb-0">
                            <div class="card-header bg-white border-bottom py-3 d-flex flex-wrap align-items-center justify-content-between gap-2">
                                <div class="d-flex align-items-center gap-2">
                                    <span class="badge bg-primary bg-opacity-10 text-primary p-2 rounded">
                                        <i class="fas fa-exchange-alt fa-lg"></i>
                                    </span>
                                    <div>
                                        <h6 class="mb-0 fw-bold text-dark">Every Detail Movement</h6>
                                        <small class="text-muted">Chronological history of receipts, transfers, dispatches, usages and adjustments</small>
                                    </div>
                                </div>
                            </div>
                            <div class="table-responsive" style="max-height: 420px; overflow-y: auto;">
                                <table class="table table-hover align-middle mb-0">
                                    <thead class="table-light sticky-top">
                                        <tr class="small text-muted text-uppercase">
                                            <th>Date & Time</th>
                                            <th>Movement Type</th>
                                            <th class="text-end">Quantity</th>
                                            <th>Ref / Doc #</th>
                                            <th>Action By</th>
                                            <th>Remarks</th>
                                        </tr>
                                    </thead>
                                    <tbody id="sdMovementsBody">
                                        <tr><td colspan="6" class="text-center py-4 text-muted"><i class="fas fa-spinner fa-spin fa-2x mb-2 d-block text-primary"></i>Loading movements...</td></tr>
                                    </tbody>
                                </table>
                            </div>
                            <div class="card-footer bg-light py-2 text-muted small d-flex justify-content-between">
                                <span>Audit trail for this store location</span>
                                <span id="sdMovementsSummary" class="fw-semibold"></span>
                            </div>
                        </div>
                    </div>

                    {{-- Tab 3: Manual Adjustments --}}
                    <div class="tab-pane fade" id="sd-tab-adjustments" role="tabpanel">
                        <div class="card border rounded-3 mb-0">
                            <div class="card-header bg-white border-bottom py-3 d-flex flex-wrap align-items-center justify-content-between gap-2">
                                <div class="d-flex align-items-center gap-2">
                                    <span class="badge bg-warning bg-opacity-10 text-warning p-2 rounded">
                                        <i class="fas fa-sliders-h fa-lg"></i>
                                    </span>
                                    <div>
                                        <h6 class="mb-0 fw-bold text-dark">All Manual Adjustments</h6>
                                        <small class="text-muted">Explicit stock overrides, counts, and discrepancies recorded for this item</small>
                                    </div>
                                </div>
                                <button type="button" class="btn btn-sm btn-outline-warning text-dark fw-bold" onclick="openManualAdjustmentFromSingle()">
                                    <i class="fas fa-plus me-1"></i>New Manual Adjustment
                                </button>
                            </div>
                            <div class="table-responsive" style="max-height: 420px; overflow-y: auto;">
                                <table class="table table-hover align-middle mb-0">
                                    <thead class="table-light sticky-top">
                                        <tr class="small text-muted text-uppercase">
                                            <th>Date & Time</th>
                                            <th>Adjustment Type</th>
                                            <th class="text-end">Qty Change</th>
                                            <th>Adjusted By</th>
                                            <th>Reason / Remarks</th>
                                        </tr>
                                    </thead>
                                    <tbody id="sdAdjustmentsBody">
                                        <tr><td colspan="5" class="text-center py-4 text-muted"><i class="fas fa-spinner fa-spin fa-2x mb-2 d-block text-warning"></i>Loading adjustments...</td></tr>
                                    </tbody>
                                </table>
                            </div>
                            <div class="card-footer bg-light py-2 text-muted small d-flex justify-content-between">
                                <span>Manual audit records</span>
                                <span id="sdAdjustmentsSummary" class="fw-semibold"></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light d-flex justify-content-between">
                <div class="d-flex gap-2">
                    <a id="sdBtnTransfer" href="{{ route('transfers.create') }}" class="btn btn-outline-primary btn-sm fw-semibold">
                        <i class="fas fa-truck-ramp-box me-1"></i>Transfer Stock
                    </a>
                    <a href="{{ route('purchase-requests.create') }}" class="btn btn-outline-success btn-sm fw-semibold">
                        <i class="fas fa-cart-plus me-1"></i>Create PR
                    </a>
                </div>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

{{-- All-Stores Grouped Breakdown Modal --}}
<div class="modal fade" id="groupedDetailModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-0 shadow-lg">
            {{-- Header --}}
            <div class="modal-header border-0 pb-0 pt-4 px-4">
                <div class="flex-grow-1">
                    <div class="d-flex align-items-center gap-2 mb-1">
                        <span id="gdStatus" class="badge px-3 py-2">Available</span>
                        <small id="gdCode" class="text-muted"></small>
                    </div>
                    <h5 class="modal-title fw-bold text-dark mb-0" id="gdName">Product Name</h5>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body px-4 pb-2 pt-3">
                {{-- Summary KPI cards --}}
                <div class="row g-3 mb-4">
                    <div class="col-6 col-md-3">
                        <div class="rounded-3 p-3 text-center" style="background:#f0f4ff;">
                            <div class="small fw-semibold text-primary mb-1">TOTAL ON HAND</div>
                            <div class="h4 fw-bold text-primary mb-0" id="gdOnHand">0.000</div>
                            <div class="small text-muted" id="gdUnit1">units</div>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="rounded-3 p-3 text-center" style="background:#fffbeb;">
                            <div class="small fw-semibold text-warning mb-1">RESERVED</div>
                            <div class="h4 fw-bold text-warning mb-0" id="gdReserved">0.000</div>
                            <div class="small text-muted" id="gdUnit2">units</div>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="rounded-3 p-3 text-center" style="background:#f0fdf4;">
                            <div class="small fw-semibold text-success mb-1">AVAILABLE</div>
                            <div class="h4 fw-bold text-success mb-0" id="gdAvailable">0.000</div>
                            <div class="small text-muted" id="gdUnit3">units</div>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="rounded-3 p-3 text-center" style="background:#f8f9fa;">
                            <div class="small fw-semibold text-secondary mb-1">TOTAL VALUE</div>
                            <div class="h4 fw-bold text-dark mb-0" id="gdTotalValue">0.00</div>
                            <div class="small text-muted">ETB</div>
                        </div>
                    </div>
                </div>

                {{-- Tabs Navigation --}}
                <ul class="nav nav-pills nav-fill bg-light p-1 rounded-3 mb-3 border" id="gdTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active fw-bold py-2" id="gd-tab-dist-btn" data-bs-toggle="pill" data-bs-target="#gd-tab-dist" type="button" role="tab">
                            <i class="fas fa-layer-group me-1 text-success"></i> Stock Distribution & Specs
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link fw-bold py-2" id="gd-tab-movements-btn" data-bs-toggle="pill" data-bs-target="#gd-tab-movements" type="button" role="tab">
                            <i class="fas fa-history me-1 text-primary"></i> Every Movement History
                            <span class="badge bg-primary rounded-pill ms-1" id="gdMovementCountBadge">0</span>
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link fw-bold py-2" id="gd-tab-adjustments-btn" data-bs-toggle="pill" data-bs-target="#gd-tab-adjustments" type="button" role="tab">
                            <i class="fas fa-sliders-h me-1 text-warning"></i> Manual Adjustments
                            <span class="badge bg-warning text-dark rounded-pill ms-1" id="gdAdjustmentCountBadge">0</span>
                        </button>
                    </li>
                </ul>

                <div class="tab-content" id="gdTabContent">
                    {{-- Tab 1: Stock Distribution & Specs --}}
                    <div class="tab-pane fade show active" id="gd-tab-dist" role="tabpanel">
                        {{-- Stock Distribution --}}
                        <div class="card border rounded-3 mb-3">
                            <div class="card-header bg-white border-bottom py-3">
                                <h6 class="mb-0 fw-bold text-success">
                                    <i class="fas fa-layer-group me-2 text-success"></i>Stock Distribution
                                </h6>
                            </div>
                            <div class="card-body p-0">
                                {{-- Header row --}}
                                <div class="d-flex justify-content-between px-3 py-2 bg-light border-bottom">
                                    <span class="small fw-bold text-muted text-uppercase">Site / Store</span>
                                    <span class="small fw-bold text-muted text-uppercase">Avail.</span>
                                </div>
                                <div id="gdDistributionList">
                                    {{-- Filled by JS --}}
                                </div>
                                {{-- Total row --}}
                                <div class="d-flex justify-content-between px-3 py-2 border-top" style="background:#f0f4ff;">
                                    <span class="small fw-bold text-primary" id="gdTotalLabel">TOTAL (0 stores)</span>
                                    <span class="small fw-bold text-primary" id="gdTotalAvailLabel">0.000</span>
                                </div>
                            </div>
                        </div>

                        {{-- Product specs --}}
                        <div class="rounded-3 p-3" style="background:#f8f9fa;">
                            <div class="row g-2 small">
                                <div class="col-md-4"><span class="text-muted">Category:</span> <strong id="gdCategory">—</strong></div>
                                <div class="col-md-4"><span class="text-muted">Unit:</span> <strong id="gdUnitLabel">—</strong></div>
                                <div class="col-md-4"><span class="text-muted">Unit Cost:</span> <strong id="gdUnitCost">—</strong> ETB</div>
                                <div class="col-12 mt-1"><span class="text-muted">Description:</span> <span id="gdDesc" class="text-dark">—</span></div>
                            </div>
                        </div>
                    </div>

                    {{-- Tab 2: Stock Movements History --}}
                    <div class="tab-pane fade" id="gd-tab-movements" role="tabpanel">
                        <div class="card border rounded-3 mb-3">
                            <div class="card-header bg-white border-bottom py-3 d-flex flex-wrap align-items-center justify-content-between gap-2">
                                <div class="d-flex align-items-center gap-2">
                                    <span class="badge bg-primary bg-opacity-10 text-primary p-2 rounded">
                                        <i class="fas fa-dolly-flatbed fa-lg"></i>
                                    </span>
                                    <div>
                                        <h6 class="mb-0 fw-bold text-dark">Every Detail Movement</h6>
                                        <small class="text-muted">Full chronological audit of all receipts, issues, dispatches, usages and transfers</small>
                                    </div>
                                </div>
                                <div class="d-flex align-items-center gap-2">
                                    <label class="small text-muted fw-semibold mb-0">Store:</label>
                                    <select class="form-select form-select-sm" id="gdStoreFilter" style="min-width: 170px;">
                                        <option value="">All Stores</option>
                                    </select>
                                </div>
                            </div>
                            <div class="table-responsive" style="max-height: 420px; overflow-y: auto;">
                                <table class="table table-hover align-middle mb-0" id="gdMovementsTable">
                                    <thead class="table-light sticky-top">
                                        <tr class="small text-muted text-uppercase">
                                            <th>Date & Time</th>
                                            <th>Store / Site</th>
                                            <th>Movement Type</th>
                                            <th class="text-end">Quantity</th>
                                            <th>Ref / Doc #</th>
                                            <th>Action By</th>
                                            <th>Remarks</th>
                                        </tr>
                                    </thead>
                                    <tbody id="gdMovementsBody">
                                        <tr><td colspan="7" class="text-center py-4 text-muted"><i class="fas fa-spinner fa-spin fa-2x mb-2 d-block text-primary"></i>Loading movements...</td></tr>
                                    </tbody>
                                </table>
                            </div>
                            <div class="card-footer bg-light py-2 text-muted small d-flex justify-content-between" id="gdMovementsFooter">
                                <span>Chronological inventory transactions</span>
                                <span id="gdMovementsSummary" class="fw-semibold"></span>
                            </div>
                        </div>
                    </div>

                    {{-- Tab 3: Manual Adjustments --}}
                    <div class="tab-pane fade" id="gd-tab-adjustments" role="tabpanel">
                        <div class="card border rounded-3 mb-3">
                            <div class="card-header bg-white border-bottom py-3 d-flex flex-wrap align-items-center justify-content-between gap-2">
                                <div class="d-flex align-items-center gap-2">
                                    <span class="badge bg-warning bg-opacity-10 text-warning p-2 rounded">
                                        <i class="fas fa-sliders-h fa-lg"></i>
                                    </span>
                                    <div>
                                        <h6 class="mb-0 fw-bold text-dark">All Manual Stock Adjustments</h6>
                                        <small class="text-muted">Explicit stock counts, overrides, and discrepancy adjustments</small>
                                    </div>
                                </div>
                                <button type="button" class="btn btn-sm btn-outline-warning text-dark fw-bold" onclick="openManualAdjustmentFromGrouped()">
                                    <i class="fas fa-plus me-1"></i>New Manual Adjustment
                                </button>
                            </div>
                            <div class="table-responsive" style="max-height: 420px; overflow-y: auto;">
                                <table class="table table-hover align-middle mb-0" id="gdAdjustmentsTable">
                                    <thead class="table-light sticky-top">
                                        <tr class="small text-muted text-uppercase">
                                            <th>Date & Time</th>
                                            <th>Store / Site</th>
                                            <th>Adjustment Type</th>
                                            <th class="text-end">Qty Change</th>
                                            <th>Adjusted By</th>
                                            <th>Reason / Remarks</th>
                                        </tr>
                                    </thead>
                                    <tbody id="gdAdjustmentsBody">
                                        <tr><td colspan="6" class="text-center py-4 text-muted"><i class="fas fa-spinner fa-spin fa-2x mb-2 d-block text-warning"></i>Loading adjustments...</td></tr>
                                    </tbody>
                                </table>
                            </div>
                            <div class="card-footer bg-light py-2 text-muted small d-flex justify-content-between" id="gdAdjustmentsFooter">
                                <span>Manual adjustments ledger</span>
                                <span id="gdAdjustmentsSummary" class="fw-semibold"></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="modal-footer bg-white border-top d-flex justify-content-between px-4">
                <div class="d-flex gap-2">
                    <a href="{{ route('transfers.create') }}" class="btn btn-outline-primary btn-sm fw-semibold">
                        <i class="fas fa-truck-ramp-box me-1"></i>Create Transfer
                    </a>
                    <a href="{{ route('purchase-requests.create') }}" class="btn btn-outline-success btn-sm fw-semibold">
                        <i class="fas fa-cart-plus me-1"></i>Create PR
                    </a>
                </div>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
var currentSingleProductId = null;
var currentSingleStoreId   = null;
var currentGroupedProductId = null;

function openManualAdjustmentFromSingle() {
    var pSelect = document.getElementById('addStockProductSelect');
    var sSelect = document.getElementById('addStockStoreSelect');
    if (pSelect && currentSingleProductId) pSelect.value = currentSingleProductId;
    if (sSelect && currentSingleStoreId)   sSelect.value = currentSingleStoreId;
    
    var sdEl = document.getElementById('singleDetailModal');
    if (sdEl) {
        var modal = bootstrap.Modal.getInstance(sdEl);
        if (modal) modal.hide();
    }
    var addEl = document.getElementById('addStockModal');
    if (addEl) {
        new bootstrap.Modal(addEl).show();
    }
}

function openManualAdjustmentFromGrouped() {
    var pSelect = document.getElementById('addStockProductSelect');
    if (pSelect && currentGroupedProductId) pSelect.value = currentGroupedProductId;
    
    var gdEl = document.getElementById('groupedDetailModal');
    if (gdEl) {
        var modal = bootstrap.Modal.getInstance(gdEl);
        if (modal) modal.hide();
    }
    var addEl = document.getElementById('addStockModal');
    if (addEl) {
        new bootstrap.Modal(addEl).show();
    }
}

document.addEventListener('DOMContentLoaded', function () {

    /* ── Single-store row popup ─────────────────── */
    var sdEl = document.getElementById('singleDetailModal');
    if (sdEl) {
        var sdModal = new bootstrap.Modal(sdEl);
        document.querySelectorAll('tr.single-product-row').forEach(function(row) {
            row.addEventListener('click', function () {
                var d = this.dataset;
                currentSingleProductId = d.productId || null;
                currentSingleStoreId   = d.storeId   || null;

                setText('sdName',      d.name || 'N/A');
                setText('sdCode',      d.code ? 'Code: ' + d.code : '');
                setText('sdStore',     d.store || 'N/A');
                setText('sdStoreType', (d.storeType || 'Site Store').toUpperCase());
                setText('sdOnHand',    d.onHand    || '0.000');
                setText('sdReserved',  d.reserved  || '0.000');
                setText('sdAvailable', d.available || '0.000');
                setText('sdMinStock',  d.minStock  || '0.000');
                setText('sdUnitCost',  d.unitCost  || '0.00');
                setText('sdTotalVal',  d.totalVal  || '0.00');
                setText('sdCategory',  d.category  || '—');
                setText('sdUnitSpec',  d.unit      || 'pcs');
                setText('sdDesc',      d.description || '—');
                var u = d.unit || 'units';
                setText('sdUnit1', u); setText('sdUnit2', u); setText('sdUnit3', u);
                setBadge('sdStatus', d.status, d.statusBg);
                var btnT = document.getElementById('sdBtnTransfer');
                if (btnT) btnT.href = '{{ route('transfers.create') }}?store_id=' + (d.storeId || '');

                // Reset tab to overview
                var firstTabBtn = document.getElementById('sd-tab-overview-btn');
                if (firstTabBtn) bootstrap.Tab.getOrCreateInstance(firstTabBtn).show();

                // Load product movement and adjustment history
                if (currentSingleProductId) {
                    loadProductHistory(currentSingleProductId, currentSingleStoreId, 'sd');
                }

                sdModal.show();
            });
        });
    }

    /* ── Grouped all-stores popup ──────────────── */
    var gdEl = document.getElementById('groupedDetailModal');
    if (gdEl) {
        var gdModal = new bootstrap.Modal(gdEl);
        document.querySelectorAll('tr.grouped-product-row').forEach(function(row) {
            row.addEventListener('click', function () {
                var d = this.dataset;
                currentGroupedProductId = d.productId || null;

                var rawStores = this.getAttribute('data-stores') || '[]';
                var stores = [];
                try { stores = JSON.parse(rawStores); } catch(e){
                    console.error('Store JSON parse error:', e, rawStores);
                }
                var unit = d.productUnit || 'units';

                setText('gdName',       d.productName  || 'N/A');
                setText('gdCode',       d.productCode ? 'Code: ' + d.productCode : '');
                setText('gdOnHand',     d.totalOnHand   || '0.000');
                setText('gdReserved',   d.totalReserved || '0.000');
                setText('gdAvailable',  d.totalAvailable || '0.000');
                setText('gdTotalValue', d.totalValue    || '0.00');
                setText('gdUnit1', unit); setText('gdUnit2', unit); setText('gdUnit3', unit);
                setText('gdCategory',  d.productCategory || '—');
                setText('gdUnitLabel', unit);
                setText('gdUnitCost',  d.unitCost  || '0.00');
                setText('gdDesc',      d.productDesc || '—');
                setBadge('gdStatus', d.status, d.statusBg);

                /* Build Stock Distribution list */
                var listEl = document.getElementById('gdDistributionList');
                listEl.innerHTML = '';
                var totA = 0;

                stores.forEach(function(s) {
                    totA += +s.available;
                    var avail = (+s.available).toFixed(3);
                    var row = document.createElement('div');
                    row.className = 'd-flex justify-content-between align-items-center px-3 py-2 border-bottom';
                    row.innerHTML =
                        '<div>' +
                        '<span class="fw-semibold text-dark">' + s.store_name + '</span>' +
                        '<span class="badge bg-light text-secondary border ms-2 small">' + (s.store_type||'Site').toUpperCase() + '</span>' +
                        '</div>' +
                        '<span class="fw-bold text-primary">' + avail + ' <small class="text-muted fw-normal ms-1">' + unit + '</small></span>';
                    listEl.appendChild(row);
                });

                setText('gdTotalLabel',     'TOTAL (' + stores.length + ' store' + (stores.length !== 1 ? 's' : '') + ')');
                setText('gdTotalAvailLabel', totA.toFixed(3) + ' ' + unit);

                // Reset tab to stock distribution
                var firstTabBtn = document.getElementById('gd-tab-dist-btn');
                if (firstTabBtn) bootstrap.Tab.getOrCreateInstance(firstTabBtn).show();

                // Load product movement and adjustment history
                if (currentGroupedProductId) {
                    loadProductHistory(currentGroupedProductId, null, 'gd');
                }

                gdModal.show();
            });
        });
    }

    function setText(id, val) {
        var el = document.getElementById(id);
        if (el) el.textContent = val;
    }
    function setBadge(id, status, bg) {
        var el = document.getElementById(id);
        if (!el) return;
        el.textContent = status || 'Available';
        el.className = 'badge bg-' + (bg || 'success') + ' ms-auto me-3 px-3 py-2 fs-6';
    }

    /* ── Product Movement & Adjustment History Loader ───────── */
    function loadProductHistory(productId, storeId, prefix) {
        var moveTbody = document.getElementById(prefix + 'MovementsBody');
        var adjTbody  = document.getElementById(prefix + 'AdjustmentsBody');
        var moveBadge = document.getElementById(prefix + 'MovementCountBadge');
        var adjBadge  = document.getElementById(prefix + 'AdjustmentCountBadge');
        var moveSumm  = document.getElementById(prefix + 'MovementsSummary');
        var adjSumm   = document.getElementById(prefix + 'AdjustmentsSummary');
        var storeSelect = document.getElementById(prefix + 'StoreFilter');

        if (moveBadge) moveBadge.textContent = '...';
        if (adjBadge)  adjBadge.textContent  = '...';
        if (moveTbody) {
            moveTbody.innerHTML = '<tr><td colspan="7" class="text-center py-4 text-muted"><i class="fas fa-spinner fa-spin fa-2x mb-2 d-block text-primary"></i>Loading movements history...</td></tr>';
        }
        if (adjTbody) {
            adjTbody.innerHTML = '<tr><td colspan="6" class="text-center py-4 text-muted"><i class="fas fa-spinner fa-spin fa-2x mb-2 d-block text-warning"></i>Loading adjustments history...</td></tr>';
        }

        var url = "{{ url('store-manager/inventory/product-history') }}/" + productId;
        if (storeId) {
            url += '?store_id=' + storeId;
        }

        fetch(url, {
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(function(res) {
            if (!res.ok) throw new Error('HTTP ' + res.status);
            return res.json();
        })
        .then(function(data) {
            var movements   = data.movements   || [];
            var adjustments = data.adjustments || [];
            var summary     = data.summary     || {};

            if (moveBadge) moveBadge.textContent = movements.length;
            if (adjBadge)  adjBadge.textContent  = adjustments.length;

            if (moveSumm) {
                moveSumm.textContent = 'Total: ' + movements.length + ' movements | Receipts: +' + (summary.total_in || 0).toFixed(3) + ' | Outflows: -' + (summary.total_out || 0).toFixed(3);
            }
            if (adjSumm) {
                adjSumm.textContent = adjustments.length + ' adjustment record' + (adjustments.length !== 1 ? 's' : '');
            }

            // Populate store filter dropdown if available (grouped modal)
            if (storeSelect) {
                var storesMap = {};
                movements.forEach(function(m) {
                    if (m.store_id) storesMap[m.store_id] = m.store_name;
                });
                storeSelect.innerHTML = '<option value="">All Stores (' + movements.length + ')</option>';
                Object.keys(storesMap).forEach(function(sId) {
                    var opt = document.createElement('option');
                    opt.value = sId;
                    opt.textContent = storesMap[sId];
                    storeSelect.appendChild(opt);
                });

                storeSelect.onchange = function() {
                    var filterVal = this.value;
                    var filtered = filterVal ? movements.filter(function(m){ return String(m.store_id) === String(filterVal); }) : movements;
                    renderMovementsTable(filtered, prefix);
                };
            }

            renderMovementsTable(movements, prefix);
            renderAdjustmentsTable(adjustments, prefix);
        })
        .catch(function(err) {
            console.error('Error loading product history:', err);
            if (moveTbody) {
                moveTbody.innerHTML = '<tr><td colspan="7" class="text-center py-4 text-danger"><i class="fas fa-exclamation-triangle fa-2x mb-2 d-block"></i>Failed to load movement history. Please try again.</td></tr>';
            }
            if (adjTbody) {
                adjTbody.innerHTML = '<tr><td colspan="6" class="text-center py-4 text-danger"><i class="fas fa-exclamation-triangle fa-2x mb-2 d-block"></i>Failed to load manual adjustments.</td></tr>';
            }
            if (moveBadge) moveBadge.textContent = '0';
            if (adjBadge)  adjBadge.textContent  = '0';
        });
    }

    function renderMovementsTable(items, prefix) {
        var tbody = document.getElementById(prefix + 'MovementsBody');
        if (!tbody) return;
        tbody.innerHTML = '';

        if (!items || items.length === 0) {
            tbody.innerHTML = '<tr><td colspan="7" class="text-center py-4 text-muted"><i class="fas fa-box-open fa-2x mb-2 d-block text-secondary opacity-50"></i>No stock movements recorded for this item yet.</td></tr>';
            return;
        }

        var isGrouped = (prefix === 'gd');

        items.forEach(function(m) {
            var tr = document.createElement('tr');
            var qtyClass = m.is_positive ? 'text-success' : 'text-danger';
            var qtyPrefix = m.is_positive ? '+' : '';

            var storeCol = isGrouped ? (
                '<td>' +
                    '<span class="fw-semibold text-dark">' + escapeHtml(m.store_name) + '</span>' +
                    '<br><span class="badge bg-light text-secondary border small">' + escapeHtml(m.store_type) + '</span>' +
                '</td>'
            ) : '';

            tr.innerHTML =
                '<td>' +
                    '<span class="fw-semibold text-dark">' + escapeHtml(m.created_at) + '</span>' +
                    (m.created_at_human ? '<br><small class="text-muted">' + escapeHtml(m.created_at_human) + '</small>' : '') +
                '</td>' +
                storeCol +
                '<td>' +
                    '<span class="badge bg-' + escapeHtml(m.badge_class) + '">' +
                        '<i class="fas ' + escapeHtml(m.icon) + ' me-1"></i>' + escapeHtml(m.type_label) +
                    '</span>' +
                '</td>' +
                '<td class="text-end fw-bold ' + qtyClass + '">' +
                    escapeHtml(m.quantity_formatted) +
                '</td>' +
                '<td>' +
                    (m.reference_no && m.reference_no !== '—'
                        ? '<span class="badge bg-light text-dark border font-monospace px-2 py-1"><i class="fas fa-file-alt me-1 text-primary"></i>' + escapeHtml(m.reference_no) + '</span>'
                        : '<span class="text-muted">—</span>') +
                '</td>' +
                '<td>' +
                    '<small class="text-dark fw-semibold"><i class="fas fa-user-circle text-secondary me-1"></i>' + escapeHtml(m.performed_by) + '</small>' +
                '</td>' +
                '<td>' +
                    '<span class="small text-muted" style="max-width:250px; display:inline-block;">' + escapeHtml(m.remarks) + '</span>' +
                '</td>';

            tbody.appendChild(tr);
        });
    }

    function renderAdjustmentsTable(items, prefix) {
        var tbody = document.getElementById(prefix + 'AdjustmentsBody');
        if (!tbody) return;
        tbody.innerHTML = '';

        if (!items || items.length === 0) {
            tbody.innerHTML = '<tr><td colspan="6" class="text-center py-4 text-muted"><i class="fas fa-check-circle fa-2x mb-2 d-block text-success opacity-50"></i>No manual adjustments recorded for this product.</td></tr>';
            return;
        }

        var isGrouped = (prefix === 'gd');

        items.forEach(function(a) {
            var tr = document.createElement('tr');
            var qtyClass = a.is_positive ? 'text-success' : 'text-danger';

            var storeCol = isGrouped ? (
                '<td>' +
                    '<span class="fw-semibold text-dark">' + escapeHtml(a.store_name) + '</span>' +
                    '<br><span class="badge bg-light text-secondary border small">' + escapeHtml(a.store_type) + '</span>' +
                '</td>'
            ) : '';

            tr.innerHTML =
                '<td>' +
                    '<span class="fw-semibold text-dark">' + escapeHtml(a.created_at) + '</span>' +
                    (a.created_at_human ? '<br><small class="text-muted">' + escapeHtml(a.created_at_human) + '</small>' : '') +
                '</td>' +
                storeCol +
                '<td>' +
                    '<span class="badge bg-warning text-dark">' +
                        '<i class="fas fa-sliders-h me-1"></i>Manual Adjustment' +
                    '</span>' +
                '</td>' +
                '<td class="text-end fw-bold ' + qtyClass + '">' +
                    escapeHtml(a.quantity_formatted) +
                '</td>' +
                '<td>' +
                    '<small class="text-dark fw-semibold"><i class="fas fa-user-check text-success me-1"></i>' + escapeHtml(a.performed_by) + '</small>' +
                '</td>' +
                '<td>' +
                    '<span class="small text-muted" style="max-width:250px; display:inline-block;">' + escapeHtml(a.remarks) + '</span>' +
                '</td>';

            tbody.appendChild(tr);
        });
    }

    function escapeHtml(str) {
        if (str === null || str === undefined) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }
});
</script>
@endpush
@endsection
