@extends('layouts.app')

@section('title', 'Products / Materials')

@push('styles')
<style>
/* ── Products index: category badge colours ─────────────────────── */
.cat-badge          { display:inline-block; padding:2px 8px; border-radius:4px; font-size:.75rem; font-weight:500; }
.cat-consumable     { background:#eff6ff; color:#1d4ed8; }
.cat-fixed-asset    { background:#f0fdf4; color:#166534; }
.cat-default        { background:#f9fafb; color:#374151; }

/* ── Products index: status pill colours ───────────────────────── */
.status-pill        { display:inline-flex; align-items:center; gap:5px; padding:3px 10px; border-radius:20px; font-size:.73rem; font-weight:600; white-space:nowrap; }
.status-dot         { width:6px; height:6px; border-radius:50%; display:inline-block; flex-shrink:0; }

.status-available        { background:#d1fae5; color:#065f46; }
.status-available   .status-dot { background:#10b981; }
.status-in-use           { background:#dbeafe; color:#1e40af; }
.status-in-use      .status-dot { background:#3b82f6; }
.status-maintenance      { background:#fef3c7; color:#92400e; }
.status-maintenance .status-dot { background:#f59e0b; }
.status-damaged          { background:#fee2e2; color:#991b1b; }
.status-damaged     .status-dot { background:#ef4444; }
.status-disposed         { background:#f3f4f6; color:#374151; }
.status-disposed    .status-dot { background:#6b7280; }
.status-lost             { background:#ede9fe; color:#5b21b6; }
.status-lost        .status-dot { background:#8b5cf6; }
.status-unknown          { background:#f3f4f6; color:#374151; }
.status-unknown     .status-dot { background:#9ca3af; }

/* ── SKU chip ───────────────────────────────────────────────────── */
.sku-chip { font-size:.75rem; color:#6366f1; background:#eef2ff; padding:2px 6px; border-radius:4px; font-weight:600; font-family:monospace; }

/* ── Table header ───────────────────────────────────────────────── */
.products-th { font-size:.75rem; letter-spacing:.04em; text-transform:uppercase; font-weight:600; color:#6b7280; }

/* ── Action buttons ─────────────────────────────────────────────── */
.btn-act-view    { background:#f3f4f6; color:#374151; border:none; padding:4px 8px; border-radius:6px; }
.btn-act-edit    { background:#eff6ff; color:#1d4ed8; border:none; padding:4px 8px; border-radius:6px; }
.btn-act-delete  { background:#fff5f5; color:#dc2626; border:none; padding:4px 8px; border-radius:6px; }
.btn-act-view:hover   { background:#e5e7eb; color:#111827; }
.btn-act-edit:hover   { background:#dbeafe; color:#1e40af; }
.btn-act-delete:hover { background:#fee2e2; color:#991b1b; }

/* ── Pagination: constrain Laravel's default SVG arrows ─────────── */
.pagination { margin:0; gap:2px; }
.pagination .page-link { font-size:.8rem; padding:5px 11px; border-radius:6px !important; border:1px solid #e5e7eb; color:#374151; line-height:1.4; }
.pagination .page-link svg { width:12px !important; height:12px !important; vertical-align:middle; }
.pagination .page-item.active .page-link { background:#1d4ed8; border-color:#1d4ed8; color:#fff; }
.pagination .page-item.disabled .page-link { color:#9ca3af; background:#f9fafb; }
.pagination .page-link:hover { background:#f3f4f6; color:#111827; }
.pagination .page-item.active .page-link:hover { background:#1e40af; }
</style>
@endpush

@section('content')

{{-- ── Page Header ──────────────────────────────────────────────── --}}
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-0 fw-bold">Products / Materials</h1>
        <p class="text-muted small mb-0 mt-1">
            {{ $products->total() }} {{ Str::plural('product', $products->total()) }} in the catalogue
        </p>
    </div>
    <div class="d-flex gap-2 align-items-center">
        @if(auth()->user()->isGlobalAdmin())
        <button type="button" class="btn btn-danger shadow-sm" data-bs-toggle="modal" data-bs-target="#adminSkuDeleteModal">
            <i class="fas fa-trash-can me-1"></i> Complete Delete by SKU
        </button>
        @endif
        @can('products.create')
        <a href="{{ route('products.create') }}" class="btn btn-primary">
            <i class="fas fa-plus me-1"></i> New Product
        </a>
        @endcan
    </div>
</div>

{{-- ── Flash Messages ───────────────────────────────────────────── --}}
@if(session('success'))
<div class="alert alert-success alert-dismissible fade show border-0 shadow-sm" role="alert">
    <i class="fas fa-check-circle me-2"></i>{{ session('success') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
@endif
@if(session('error'))
<div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm" role="alert">
    <i class="fas fa-exclamation-circle me-2"></i>{{ session('error') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
@endif

{{-- ── Filters ─────────────────────────────────────────────────── --}}
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body py-3">
        <form action="{{ route('products.index') }}" method="GET">
            <div class="row g-2 align-items-end">
                <div class="col-md-4">
                    <label class="form-label small fw-semibold text-muted mb-1">Search</label>
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-white">
                            <i class="fas fa-search text-muted"></i>
                        </span>
                        <input type="text" name="search" class="form-control form-control-sm"
                               placeholder="Name, SKU, category…" value="{{ request('search') }}">
                    </div>
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-semibold text-muted mb-1">Category</label>
                    <select name="category" class="form-select form-select-sm">
                        <option value="">All Categories</option>
                        @foreach($categories as $cat)
                            <option value="{{ $cat }}" {{ request('category') === $cat ? 'selected' : '' }}>{{ $cat }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-semibold text-muted mb-1">Status</label>
                    <select name="asset_status" class="form-select form-select-sm">
                        <option value="">All Statuses</option>
                        @foreach($assetStatuses as $status)
                            <option value="{{ $status }}" {{ request('asset_status') === $status ? 'selected' : '' }}>{{ $status }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <div class="d-flex gap-1">
                        <button type="submit" class="btn btn-primary btn-sm w-100">
                            <i class="fas fa-filter me-1"></i> Filter
                        </button>
                        @if(request()->hasAny(['search','category','asset_status']))
                        <a href="{{ route('products.index') }}" class="btn btn-outline-secondary btn-sm" title="Clear filters">
                            <i class="fas fa-times"></i>
                        </a>
                        @endif
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

{{-- ── Products Table ──────────────────────────────────────────── --}}
<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" style="font-size:.875rem;">
                <thead class="table-light border-bottom">
                    <tr>
                        <th class="ps-3 py-3 products-th" style="width:120px;">SKU</th>
                        <th class="py-3 products-th">Name</th>
                        <th class="py-3 products-th" style="width:150px;">Category</th>
                        <th class="py-3 products-th" style="width:75px;">Unit</th>
                        <th class="py-3 products-th text-end" style="width:140px;">Unit Price</th>
                        <th class="py-3 products-th text-end" style="width:105px;">Max Stock</th>
                        <th class="py-3 products-th text-center" style="width:130px;">Status</th>
                        <th class="py-3 products-th text-end pe-3" style="width:95px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($products as $product)
                    @php
                        /* Category CSS class */
                        $catClass = match($product->category) {
                            'Consumable'  => 'cat-consumable',
                            'Fixed Asset' => 'cat-fixed-asset',
                            default       => 'cat-default',
                        };

                        /* Status CSS class */
                        $statusClass = match($product->asset_status) {
                            'Available'         => 'status-available',
                            'In Use'            => 'status-in-use',
                            'Under Maintenance' => 'status-maintenance',
                            'Damaged'           => 'status-damaged',
                            'Disposed'          => 'status-disposed',
                            'Lost'              => 'status-lost',
                            default             => 'status-unknown',
                        };
                    @endphp
                    <tr class="border-bottom border-light">
                        {{-- SKU --}}
                        <td class="ps-3">
                            <span class="sku-chip">{{ $product->sku }}</span>
                        </td>

                        {{-- Name + sub-category --}}
                        <td>
                            <a href="{{ route('products.show', $product) }}"
                               class="fw-semibold text-decoration-none text-dark">
                                {{ $product->name }}
                            </a>
                            @if($product->sub_category)
                            <div class="text-muted" style="font-size:.75rem;">{{ $product->sub_category }}</div>
                            @endif
                        </td>

                        {{-- Category badge --}}
                        <td>
                            <span class="cat-badge {{ $catClass }}">
                                {{ $product->category ?? '—' }}
                            </span>
                        </td>

                        {{-- Unit --}}
                        <td>
                            <span class="badge bg-light text-dark fw-normal">
                                {{ $product->unit ?: '—' }}
                            </span>
                        </td>

                        {{-- Unit Price --}}
                        <td class="text-end fw-semibold">
                            @if($product->unit_price > 0)
                                <span class="text-muted fw-normal" style="font-size:.8rem;">ETB </span>{{ number_format($product->unit_price, 2) }}
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>

                        {{-- Max Stock --}}
                        <td class="text-end text-secondary">
                            {{ number_format($product->max_stock, 2) }}
                        </td>

                        {{-- Status pill --}}
                        <td class="text-center">
                            <span class="status-pill {{ $statusClass }}">
                                <span class="status-dot"></span>
                                {{ $product->asset_status ?? 'N/A' }}
                            </span>
                        </td>

                        {{-- Actions --}}
                        <td class="text-end pe-3">
                            <div class="d-flex gap-1 justify-content-end">
                                <a href="{{ route('products.show', $product) }}"
                                   class="btn btn-sm btn-act-view" title="View">
                                    <i class="fas fa-eye" style="font-size:.75rem;"></i>
                                </a>
                                @can('products.edit')
                                <a href="{{ route('products.edit', $product) }}"
                                   class="btn btn-sm btn-act-edit" title="Edit">
                                    <i class="fas fa-pen" style="font-size:.75rem;"></i>
                                </a>
                                @endcan
                                @can('products.delete')
                                <form method="POST" action="{{ route('products.destroy', $product) }}"
                                      class="d-inline" onsubmit="return confirm('Archive this product?')">
                                    @csrf @method('DELETE')
                                    <button class="btn btn-sm btn-act-delete" title="Archive">
                                        <i class="fas fa-archive" style="font-size:.75rem;"></i>
                                    </button>
                                </form>
                                @endcan
                                @if(auth()->user()->isGlobalAdmin())
                                <button type="button" class="btn btn-sm btn-danger text-white px-2"
                                        title="Global Admin: Complete Delete by SKU (Permanent)"
                                        onclick="openSkuDeleteModal('{{ $product->sku }}', '{{ addslashes($product->name) }}')">
                                    <i class="fas fa-trash-can" style="font-size:.75rem;"></i>
                                </button>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="8" class="text-center py-5">
                            <i class="fas fa-box-open text-muted mb-3" style="font-size:2.5rem; display:block; opacity:.4;"></i>
                            <div class="fw-semibold text-dark">No products found</div>
                            <div class="small text-muted mt-1">Try adjusting your search or filter criteria.</div>
                            @can('products.create')
                            <a href="{{ route('products.create') }}" class="btn btn-primary btn-sm mt-3">
                                <i class="fas fa-plus me-1"></i> Add First Product
                            </a>
                            @endcan
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Pagination --}}
    <div class="card-footer bg-white py-3 px-3 d-flex align-items-center justify-content-between" style="border-top:1px solid #f3f4f6;">
        <div class="text-muted small">
            @if($products->total() > 0)
                Showing
                <span class="fw-semibold text-dark">{{ $products->firstItem() }}</span>–<span class="fw-semibold text-dark">{{ $products->lastItem() }}</span>
                of <span class="fw-semibold text-dark">{{ $products->total() }}</span> results
            @else
                No results
            @endif
        </div>
        @if($products->hasPages())
        <div>
            {{ $products->withQueryString()->links() }}
        </div>
        @endif
    </div>
</div>

@include('products.partials.admin_sku_delete_modal')

@endsection
