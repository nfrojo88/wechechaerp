@extends('layouts.app')

@section('title', 'Purchase Order Details')

@section('content')
<div class="d-flex align-items-center mb-4">
    <a href="{{ route('purchase-orders.index') }}" class="btn btn-sm btn-outline-secondary me-3">
        <i class="fa-solid fa-arrow-left"></i>
    </a>
    <h1 class="h3 mb-0 me-3">PO: {{ $purchaseOrder->reference_number }}</h1>

    @php
        $badge = match($purchaseOrder->status) {
            'draft'              => 'secondary',
            'issued'             => 'primary',
            'partially_received' => 'warning',
            'completed'          => 'success',
            'cancelled'          => 'danger',
            default              => 'secondary'
        };
    @endphp
    <span class="badge bg-{{ $badge }} me-3">{{ strtoupper(str_replace('_',' ',$purchaseOrder->status)) }}</span>

    <div class="ms-auto d-flex gap-2">
        @if($purchaseOrder->status === 'draft')
            @can('purchases.create')
            <form method="POST" action="{{ route('purchase-orders.issue', $purchaseOrder) }}"
                  onsubmit="return confirm('Issue this PO to the supplier?');">
                @csrf
                <button type="submit" class="btn btn-success">
                    <i class="fa-solid fa-paper-plane me-1"></i> Issue PO
                </button>
            </form>
            @endcan
        @endif
    </div>
</div>

{{-- Header summary row --}}
<div class="row mb-4">
    <div class="col-md-8">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <h5 class="card-title text-muted mb-4">Order Details</h5>
                <table class="table table-borderless table-sm mb-0">
                    <tr>
                        <td class="text-muted w-25">Supplier</td>
                        <td class="fw-semibold">{{ $purchaseOrder->supplier_name }}</td>
                    </tr>
                    <tr>
                        <td class="text-muted">Project</td>
                        <td class="fw-semibold">
                            @if($purchaseOrder->project)
                                <a href="{{ route('projects.show', $purchaseOrder->project) }}" class="text-decoration-none">
                                    {{ $purchaseOrder->project->name }}
                                </a>
                            @else
                                <span class="text-muted">Central / HQ</span>
                            @endif
                        </td>
                    </tr>
                    <tr>
                        <td class="text-muted">Issued Date</td>
                        <td class="fw-semibold">{{ $purchaseOrder->issued_date?->format('d M Y') ?? '—' }}</td>
                    </tr>
                    @if($purchaseOrder->notes)
                    <tr>
                        <td class="text-muted">Notes</td>
                        <td>{{ $purchaseOrder->notes }}</td>
                    </tr>
                    @endif
                </table>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-0 shadow-sm bg-light h-100">
            <div class="card-body">
                <h6 class="text-muted text-uppercase small fw-bold mb-3">Financial Summary</h6>
                <div class="mb-3">
                    <div class="text-muted small">Total Order Value</div>
                    <div class="fs-3 fw-bold text-primary">{{ number_format($purchaseOrder->total_amount, 2) }} ETB</div>
                </div>
                <div class="text-muted small">
                    <div>Created By: <span class="fw-semibold text-dark">{{ $purchaseOrder->creator?->name ?? 'Staff' }}</span></div>
                    <div>{{ $purchaseOrder->created_at?->format('d M Y H:i') ?? '—' }}</div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Line items table --}}
<div class="card border-0 shadow-sm">
    <div class="card-header bg-transparent py-3 d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Line Items</h5>
        @if($purchaseOrder->status === 'draft')
        <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addPoItemModal">
            <i class="fa-solid fa-plus me-1"></i> Add Item
        </button>
        @endif
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Product / Material</th>
                        <th class="text-end">Qty</th>
                        <th class="text-end">Unit Price (ETB)</th>
                        <th class="text-end">Total (ETB)</th>
                        @if($purchaseOrder->status === 'draft')
                        <th class="text-end">Actions</th>
                        @endif
                    </tr>
                </thead>
                <tbody>
                    @forelse($purchaseOrder->items as $item)
                    <tr>
                        <td>
                            <div class="fw-semibold">{{ $item->product->name }}</div>
                            <code class="small text-muted">{{ $item->product->code }}</code>
                        </td>
                        <td class="text-end">{{ number_format($item->quantity, 3) }} <small class="text-muted">{{ $item->product->unit }}</small></td>
                        <td class="text-end">{{ number_format($item->unit_price, 2) }}</td>
                        <td class="text-end fw-bold">{{ number_format($item->total_price, 2) }}</td>
                        @if($purchaseOrder->status === 'draft')
                        <td class="text-end">
                            <form method="POST" action="{{ route('po-items.destroy', $item) }}"
                                  class="d-inline" onsubmit="return confirm('Remove this item?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-outline-danger">
                                    <i class="fa-solid fa-trash"></i>
                                </button>
                            </form>
                        </td>
                        @endif
                    </tr>
                    @empty
                    <tr>
                        <td colspan="{{ $purchaseOrder->status === 'draft' ? 5 : 4 }}"
                            class="text-center text-muted py-4">
                            No line items yet. Add materials to this purchase order.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
                <tfoot class="table-light">
                    <tr>
                        <th colspan="3" class="text-end text-uppercase">Grand Total:</th>
                        <th class="text-end fs-5 text-primary">{{ number_format($purchaseOrder->total_amount, 2) }} ETB</th>
                        @if($purchaseOrder->status === 'draft')<th></th>@endif
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>

{{-- Add Item Modal --}}
@if($purchaseOrder->status === 'draft')
<div class="modal fade" id="addPoItemModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="{{ route('po-items.store', $purchaseOrder) }}">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Add Line Item</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label">Product / Material <span class="text-danger">*</span></label>
                            <select name="product_id" class="form-select" required>
                                <option value="">— Select Product —</option>
                                @foreach($products as $p)
                                <option value="{{ $p->id }}">{{ $p->name }} ({{ $p->code }}) – {{ $p->unit }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label">Quantity <span class="text-danger">*</span></label>
                            <input type="number" step="0.001" min="0.001" name="quantity" class="form-control" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label">Unit Price (ETB) <span class="text-danger">*</span></label>
                            <input type="number" step="0.01" min="0" name="unit_price" class="form-control" required>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Item</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif
@endsection
