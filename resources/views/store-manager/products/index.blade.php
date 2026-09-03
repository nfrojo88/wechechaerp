@extends('layouts.app')

@section('title', 'Material Catalog - Store Manager')

@section('content')
<script>window.location.replace("{{ route('products.index') }}");</script>
<div class="container-fluid">
    {{-- ── Page Header ──────────────────────────────────────────── --}}
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <div>
            <h1 class="h4 mb-0 fw-bold">
                <i class="fa-solid fa-boxes-stacked text-primary me-2"></i>Material Catalog
            </h1>
            <p class="text-muted small mb-0 mt-1">
                Manage all materials, consumables, and fixed assets available in the system inventory.
            </p>
        </div>
        <div class="d-flex gap-2 mt-2 mt-sm-0">
            <a href="{{ route('store-manager.products.create') }}" class="btn btn-primary btn-sm shadow-sm">
                <i class="fa-solid fa-plus me-1"></i> Add New Product
            </a>
            <a href="{{ route('store-manager.dashboard') }}" class="btn btn-secondary btn-sm shadow-sm">
                <i class="fa-solid fa-arrow-left me-1"></i> Back to Dashboard
            </a>
        </div>
    </div>

    {{-- ── Flash Messages ───────────────────────────────────────── --}}
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show shadow-sm py-2" role="alert">
            <i class="fa-solid fa-circle-check me-2"></i>{{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    {{-- ── Filters ──────────────────────────────────────────────── --}}
    <div class="card shadow-sm mb-4 border-0">
        <div class="card-body bg-light rounded">
            <form method="GET" class="row gx-2 gy-2 align-items-end">
                <div class="col-md-5">
                    <label class="form-label small text-muted fw-bold mb-1">Search Products</label>
                    <div class="input-group input-group-sm shadow-sm">
                        <span class="input-group-text bg-white border-end-0 text-muted"><i class="fa-solid fa-magnifying-glass"></i></span>
                        <input type="text" name="search" class="form-control border-start-0" value="{{ request('search') }}" placeholder="Name, Code, or Category...">
                    </div>
                </div>
                <div class="col-md-4">
                    <label class="form-label small text-muted fw-bold mb-1">Category Filter</label>
                    <select name="category" class="form-select form-select-sm shadow-sm">
                        <option value="">All Categories</option>
                        @foreach($categories as $category)
                            <option value="{{ $category }}" {{ request('category') == $category ? 'selected' : '' }}>
                                {{ $category }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3 text-end">
                    <button type="submit" class="btn btn-primary btn-sm shadow-sm px-3">
                        Filter
                    </button>
                    <a href="{{ route('store-manager.products.index') }}" class="btn btn-outline-secondary btn-sm shadow-sm px-3">
                        Reset
                    </a>
                </div>
            </form>
        </div>
    </div>

    {{-- ── Products Table ───────────────────────────────────────── --}}
    <div class="card shadow-sm border-0">
        <div class="card-header d-flex align-items-center gap-2 py-2 bg-white border-bottom">
            <i class="fa-solid fa-table-list text-primary"></i>
            <span class="fw-bold">Product List</span>
            <span class="badge bg-secondary ms-auto">{{ $products->total() }} total item(s)</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" style="font-size:13.5px;">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4">PRODUCT CODE</th>
                            <th>PRODUCT NAME</th>
                            <th>CATEGORY</th>
                            <th class="text-center">UNIT</th>
                            <th class="text-end">STD COST</th>
                            <th class="text-end">MIN STOCK</th>
                            <th class="text-center pe-4">STATUS</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($products as $product)
                            <tr>
                                <td class="ps-4">
                                    <span class="badge bg-light text-dark border font-monospace">
                                        {{ $product->code ?? $product->sku ?? '—' }}
                                    </span>
                                </td>
                                <td>
                                    <div class="fw-bold text-primary">{{ $product->name }}</div>
                                    @if($product->description)
                                        <div class="small text-muted text-truncate" style="max-width: 250px;" title="{{ $product->description }}">
                                            {{ $product->description }}
                                        </div>
                                    @endif
                                </td>
                                <td>
                                    @if($product->category)
                                        <span class="badge bg-info bg-opacity-10 text-info border border-info border-opacity-25">{{ $product->category }}</span>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td class="text-center fw-semibold text-muted">{{ $product->unit ?? '—' }}</td>
                                <td class="text-end font-monospace">{{ number_format($product->standard_cost ?? $product->unit_price ?? 0, 2) }}</td>
                                <td class="text-end font-monospace text-muted">{{ number_format($product->min_stock_level ?? $product->reorder_level ?? 0, 3) }}</td>
                                <td class="text-center pe-4">
                                    @if($product->is_active ?? true)
                                        <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25">
                                            <i class="fa-solid fa-circle-check me-1"></i>Active
                                        </span>
                                    @else
                                        <span class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-25">
                                            <i class="fa-solid fa-circle-xmark me-1"></i>Inactive
                                        </span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center py-5 text-muted">
                                    <i class="fa-solid fa-box-open fa-3x d-block mb-3 opacity-25"></i>
                                    No products found matching your criteria.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if($products->hasPages())
            <div class="card-footer bg-white border-top py-3 d-flex justify-content-end">
                {{ $products->links('pagination::bootstrap-4') }}
            </div>
        @endif
    </div>
</div>
@endsection
