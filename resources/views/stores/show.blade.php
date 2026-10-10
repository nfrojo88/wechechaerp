@extends('layouts.app')

@section('title', 'Store Details')

@section('content')
<div class="d-flex align-items-center mb-4">
    <a href="{{ route('stores.index') }}" class="btn btn-sm btn-outline-secondary me-3">
        <i class="fa-solid fa-arrow-left"></i>
    </a>
    <h1 class="h3 mb-0 me-3">{{ $store->name }}</h1>
    <span class="badge bg-{{ $store->is_active ? 'success' : 'secondary' }} me-2">
        {{ $store->is_active ? 'Active' : 'Inactive' }}
    </span>
    <code>{{ $store->code }}</code>
    
    <div class="ms-auto">
        @can('stores.edit')
        <a href="{{ route('stores.edit', $store) }}" class="btn btn-primary">
            <i class="fa-solid fa-pen me-1"></i> Edit Store
        </a>
        @endcan
    </div>
</div>

<div class="row g-4">
    <div class="col-md-4">
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-transparent py-3">
                <h5 class="mb-0">Store Information</h5>
            </div>
            <div class="card-body">
                <table class="table table-borderless table-sm mb-0">
                    <tr><td class="text-muted w-50">Type</td><td class="fw-semibold">{{ ucfirst($store->type) }}</td></tr>
                    <tr><td class="text-muted">Manager</td><td class="fw-semibold">{{ $store->manager->name ?? '—' }}</td></tr>
                    <tr><td class="text-muted">Associated Project</td>
                        <td class="fw-semibold">
                            @if($store->project)
                            <a href="{{ route('projects.show', $store->project) }}" class="text-decoration-none">{{ $store->project->name }}</a>
                            @else
                            <span class="text-muted">—</span>
                            @endif
                        </td>
                    </tr>
                    <tr>
                        <td class="text-muted">Site Petty Cash</td>
                        <td class="fw-semibold">
                            @if($store->pettyCashAccount)
                                <span class="badge bg-success-subtle text-success border border-success-subtle">
                                    <i class="fa-solid fa-wallet me-1"></i>{{ $store->pettyCashAccount->name }} [{{ $store->pettyCashAccount->code }}]
                                </span>
                                <div class="small text-muted mt-1">Balance: Br {{ number_format($store->pettyCashAccount->current_balance, 2) }}</div>
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                    </tr>
                </table>
                @if($store->address)
                <div class="mt-3 pt-3 border-top">
                    <div class="text-muted small mb-1">Physical Address</div>
                    <div>{{ $store->address }}</div>
                </div>
                @endif
                @if($store->notes)
                <div class="mt-3 pt-3 border-top">
                    <div class="text-muted small mb-1">Notes</div>
                    <div>{{ $store->notes }}</div>
                </div>
                @endif
            </div>
        </div>
    </div>
    <div class="col-md-8">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-transparent py-3 d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Current Inventory</h5>
                <a href="{{ route('inventory.index', ['store_id' => $store->id]) }}" class="btn btn-sm btn-outline-primary">View Full Inventory</a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Product</th>
                                <th>On Hand</th>
                                <th>Available</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($store->inventory()->with('product')->take(10)->get() as $inv)
                            @php $isLow = $inv->quantity_on_hand <= $inv->min_stock && $inv->min_stock > 0; @endphp
                            <tr>
                                <td>
                                    <a href="{{ route('products.show', $inv->product) }}" class="fw-semibold text-decoration-none">
                                        {{ $inv->product->name }}
                                    </a>
                                </td>
                                <td>{{ number_format($inv->quantity_on_hand, 3) }} <small class="text-muted">{{ $inv->product->unit }}</small></td>
                                <td class="{{ $isLow ? 'text-danger fw-bold' : 'text-success fw-bold' }}">
                                    {{ number_format($inv->quantity_available, 3) }}
                                </td>
                                <td>
                                    @if($isLow)
                                    <span class="badge bg-danger">Low</span>
                                    @else
                                    <span class="badge bg-success">OK</span>
                                    @endif
                                </td>
                            </tr>
                            @empty
                            <tr><td colspan="4" class="text-center text-muted py-4">No inventory items found in this store.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
