@extends('layouts.app')

@section('title', 'Inventory Item')

@section('content')
<div class="d-flex align-items-center mb-4">
    <a href="{{ route('inventory.index') }}" class="btn btn-sm btn-outline-secondary me-3">
        <i class="fa-solid fa-arrow-left"></i>
    </a>
    <h1 class="h3 mb-0 me-3">Stock Item</h1>
    @if($inventory->quantity_on_hand <= $inventory->min_stock && $inventory->min_stock > 0)
    <span class="badge bg-danger"><i class="fa-solid fa-triangle-exclamation me-1"></i>Low Stock</span>
    @else
    <span class="badge bg-success">Stock OK</span>
    @endif
</div>

<div class="row g-4 mb-4">
    <div class="col-md-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <h5 class="card-title text-muted mb-4">Product Details</h5>
                <div class="d-flex mb-3">
                    <div class="me-4 text-center">
                        <i class="fa-solid fa-box-open fa-3x text-primary opacity-50"></i>
                    </div>
                    <div>
                        <h4 class="mb-1"><a href="{{ route('products.show', $inventory->product) }}" class="text-decoration-none">{{ $inventory->product->name }}</a></h4>
                        <code class="d-block mb-2">{{ $inventory->product->code }}</code>
                        <span class="badge bg-light text-dark">{{ $inventory->product->category }}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <h5 class="card-title text-muted mb-4">Location</h5>
                <div class="d-flex mb-3">
                    <div class="me-4 text-center">
                        <i class="fa-solid fa-warehouse fa-3x text-success opacity-50"></i>
                    </div>
                    <div>
                        <h4 class="mb-1"><a href="{{ route('stores.show', $inventory->store) }}" class="text-decoration-none text-success">{{ $inventory->store->name }}</a></h4>
                        <code class="d-block mb-2">{{ $inventory->store->code }}</code>
                        <div class="text-muted small"><i class="fa-solid fa-tag me-1"></i>{{ ucfirst($inventory->store->type) }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-4">
    <div class="col-md-4">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="mb-0">Stock Levels</h5>
                    @can('inventory.edit')
                    <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#adjustModal">
                        <i class="fa-solid fa-sliders me-1"></i> Adjust
                    </button>
                    @endcan
                </div>
                <table class="table table-borderless table-sm mb-0">
                    <tr>
                        <td class="text-muted w-50">On Hand</td>
                        <td class="fw-bold fs-5">{{ number_format($inventory->quantity_on_hand, 3) }} <small class="text-muted fs-6">{{ $inventory->product->unit }}</small></td>
                    </tr>
                    <tr>
                        <td class="text-muted">Reserved</td>
                        <td class="text-warning fw-semibold">{{ number_format($inventory->quantity_reserved, 3) }}</td>
                    </tr>
                    <tr class="border-top">
                        <td class="text-muted fw-bold">Available</td>
                        <td class="fw-bold text-success">{{ number_format($inventory->quantity_available, 3) }}</td>
                    </tr>
                    <tr class="border-top">
                        <td class="text-muted">Min Alert Level</td>
                        <td>{{ number_format($inventory->min_stock, 3) }}</td>
                    </tr>
                    <tr>
                        <td class="text-muted">Unit Cost</td>
                        <td>{{ $inventory->unit_cost ? number_format($inventory->unit_cost, 2) . ' ETB' : '—' }}</td>
                    </tr>
                    <tr class="border-top">
                        <td class="text-muted fw-bold">Total Value</td>
                        <td class="fw-bold">{{ $inventory->total_value ? number_format($inventory->total_value, 2) . ' ETB' : '—' }}</td>
                    </tr>
                </table>
            </div>
        </div>
    </div>
    <div class="col-md-8">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-transparent py-3 d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Recent Movements</h5>
                <a href="{{ route('inventory.movements', $inventory) }}" class="btn btn-sm btn-outline-secondary">View All</a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Date</th>
                                <th>Type</th>
                                <th class="text-end">Quantity</th>
                                <th class="text-end">Balance After</th>
                                <th>Performed By</th>
                                <th>Remarks</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($inventory->movements()->with('performer')->latest()->take(10)->get() as $mov)
                            @php
                                $badge = match($mov->type) {
                                    'in' => 'success',
                                    'out' => 'danger',
                                    'transfer' => 'info',
                                    'adjustment' => 'warning',
                                    'reserve' => 'secondary',
                                    'release' => 'secondary',
                                    default => 'secondary'
                                };
                                $mQty = (float) $mov->quantity;
                                $isPos = $mQty > 0;
                                $isNeg = $mQty < 0;
                            @endphp
                            <tr>
                                <td>{{ $mov->created_at->format('d M Y H:i') }}</td>
                                <td>
                                    <span class="badge bg-{{ $badge }}">{{ strtoupper($mov->type) }}</span>
                                </td>
                                <td class="text-end font-monospace fw-bold {{ $isPos ? 'text-success' : ($isNeg ? 'text-danger' : 'text-secondary') }}">
                                    {{ $isPos ? '+' : ($isNeg ? '-' : '') }}{{ number_format(abs($mQty), 3) }}
                                </td>
                                <td class="text-end font-monospace fw-semibold text-dark">
                                    {{ $mov->balance_after !== null ? number_format((float)$mov->balance_after, 3) : '—' }}
                                </td>
                                <td>{{ $mov->performer?->name ?? 'System' }}</td>
                                <td class="text-muted small text-truncate" style="max-width:200px;">{{ $mov->remarks }}</td>
                            </tr>
                            @empty
                            <tr><td colspan="6" class="text-center text-muted py-4">No movements recorded yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

@can('inventory.edit')
<div class="modal fade" id="adjustModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="{{ route('inventory.adjust', $inventory) }}">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Adjust Inventory</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-info">
                        <strong>Current On-Hand:</strong> {{ number_format($inventory->quantity_on_hand, 3) }} {{ $inventory->product->unit }}<br>
                        <strong>Current Available:</strong> {{ number_format($inventory->quantity_available, 3) }} {{ $inventory->product->unit }}
                        @if($inventory->quantity_reserved > 0)
                            <br><small class="text-muted">({{ number_format($inventory->quantity_reserved, 3) }} currently assigned/reserved to staff)</small>
                        @endif
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Adjustment Type</label>
                        <select name="type" class="form-select" required>
                            <option value="in">Stock In (+ Add)</option>
                            <option value="out">Stock Out (- Deduct)</option>
                            <option value="adjustment">Correction (Set exact count)</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Quantity ({{ $inventory->product->unit }})</label>
                        <input type="number" step="0.001" min="0" name="quantity" class="form-control" required>
                        <div class="form-text">For In/Out: amount to add or deduct. For Correction: new total on-hand count.</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Unit Cost (ETB)</label>
                        <input type="number" step="0.01" min="0" name="unit_cost" class="form-control" value="{{ $inventory->unit_cost }}">
                        <div class="form-text">Update the unit cost for new incoming stock.</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Reason / Remarks</label>
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
@endsection
