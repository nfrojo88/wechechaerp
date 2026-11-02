@extends('layouts.app')

@section('title', 'Edit Product')

@section('content')
<div class="d-flex align-items-center mb-4">
    <a href="{{ route('products.index') }}" class="btn btn-sm btn-outline-secondary me-3">
        <i class="fas fa-arrow-left"></i>
    </a>
    <h1 class="h3 mb-0">Edit Product: {{ $product->name }}</h1>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body">
        <form method="POST" action="{{ route('products.update', $product) }}">
            @csrf
            @method('PUT')
            <div class="row g-3">

                {{-- ── Basic Info ────────────────────────────────────── --}}
                <div class="col-12"><h6 class="border-bottom pb-2 text-muted">Basic Information</h6></div>

                <div class="col-md-6">
                    <label class="form-label">Name <span class="text-danger">*</span></label>
                    <input type="text" name="name" class="form-control @error('name') is-invalid @enderror"
                           value="{{ old('name', $product->name) }}" required>
                    @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-3">
                    <label class="form-label">SKU / Code <span class="text-danger">*</span></label>
                    <input type="text" name="sku" class="form-control @error('sku') is-invalid @enderror"
                           value="{{ old('sku', $product->sku) }}" required>
                    @error('sku')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-3">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <label class="form-label mb-0">Unit of Measurement <span class="text-danger">*</span></label>
                        <button type="button" class="btn btn-link btn-sm text-decoration-none p-0 fw-semibold text-primary" data-bs-toggle="modal" data-bs-target="#manageUnitsModal" title="Manage Units">
                            <i class="fas fa-cog me-1"></i>Manage
                        </button>
                    </div>
                    <div class="input-group">
                        <select name="unit" id="product_unit_select" class="form-select @error('unit') is-invalid @enderror" required>
                            <option value="">— Select Unit —</option>
                            @foreach($units as $unit)
                                @php
                                    $uCode = is_object($unit) ? $unit->code : $unit;
                                    $uLabel = is_object($unit) ? ($unit->name . ' (' . $unit->code . ')') : $unit;
                                @endphp
                                <option value="{{ $uCode }}" @selected(old('unit', $product->unit) == $uCode)>{{ $uLabel }}</option>
                            @endforeach
                        </select>
                        <button type="button" class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#manageUnitsModal" title="Add / Manage Units">
                            <i class="fas fa-plus"></i>
                        </button>
                    </div>
                    @error('unit')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="col-md-6">
                    <label class="form-label">Category</label>
                    <select name="category" class="form-select @error('category') is-invalid @enderror">
                        <option value="">— Select Category —</option>
                        @foreach($categories as $category)
                        <option value="{{ $category }}" @selected(old('category', $product->category) == $category)>{{ $category }}</option>
                        @endforeach
                    </select>
                    @error('category')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label">Sub-Category</label>
                    <select name="sub_category" class="form-select">
                        <option value="">— Select Sub-Category —</option>
                        @foreach($subCategories as $sc)
                        <option value="{{ $sc }}" @selected(old('sub_category', $product->sub_category) == $sc)>{{ $sc }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- ── Pricing ───────────────────────────────────────── --}}
                <div class="col-12 mt-3"><h6 class="border-bottom pb-2 text-muted">Pricing</h6></div>

                <div class="col-md-3">
                    <label class="form-label">Unit Price (ETB)</label>
                    <input type="number" step="0.01" min="0" name="unit_price"
                           class="form-control" value="{{ old('unit_price', $product->unit_price) }}">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Selling Price (ETB)</label>
                    <input type="number" step="0.01" min="0" name="selling_price"
                           class="form-control" value="{{ old('selling_price', $product->selling_price) }}">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Purchase Threshold (%)</label>
                    <input type="number" step="0.01" min="0" max="100" name="purchase_threshold"
                           class="form-control" value="{{ old('purchase_threshold', $product->purchase_threshold) }}">
                </div>

                {{-- ── Inventory ─────────────────────────────────────── --}}
                <div class="col-12 mt-3"><h6 class="border-bottom pb-2 text-muted">Inventory</h6></div>

                <div class="col-md-3">
                    <label class="form-label">Max Stock</label>
                    <input type="number" step="0.01" min="0" name="max_stock"
                           class="form-control" value="{{ old('max_stock', $product->max_stock) }}">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Reorder Level</label>
                    <input type="number" min="0" name="reorder_level"
                           class="form-control" value="{{ old('reorder_level', $product->reorder_level) }}">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Carton / Pack Size</label>
                    <input type="number" min="0" name="carton_size"
                           class="form-control" value="{{ old('carton_size', $product->carton_size) }}">
                </div>

                {{-- ── Dimensions ────────────────────────────────────── --}}
                <div class="col-12 mt-3"><h6 class="border-bottom pb-2 text-muted">Dimensions (optional)</h6></div>

                <div class="col-md-3">
                    <label class="form-label">Standard Length (m)</label>
                    <input type="number" step="0.01" min="0" name="standard_length"
                           class="form-control" value="{{ old('standard_length', $product->standard_length) }}">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Standard Width (m)</label>
                    <input type="number" step="0.001" min="0" name="standard_width"
                           class="form-control" value="{{ old('standard_width', $product->standard_width) }}">
                </div>

                {{-- ── Asset / Equipment ─────────────────────────────── --}}
                <div class="col-12 mt-3"><h6 class="border-bottom pb-2 text-muted">Asset / Equipment Details</h6></div>

                <div class="col-md-4">
                    <label class="form-label">Asset Status</label>
                    <select name="asset_status" class="form-select">
                        @foreach($assetStatuses as $status)
                        <option value="{{ $status }}" @selected(old('asset_status', $product->asset_status) == $status)>{{ $status }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Condition</label>
                    <select name="equipment_condition" class="form-select">
                        @foreach($conditions as $cond)
                        <option value="{{ $cond }}" @selected(old('equipment_condition', $product->equipment_condition) == $cond)>{{ $cond }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Baseline Date</label>
                    <input type="date" name="baseline_date" class="form-control"
                           value="{{ old('baseline_date', $product->baseline_date?->format('Y-m-d')) }}">
                </div>

                <div class="col-md-6">
                    <label class="form-label">Assigned To</label>
                    <input type="text" name="assigned_to" class="form-control"
                           value="{{ old('assigned_to', $product->assigned_to) }}">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Current Location</label>
                    <input type="text" name="current_location" class="form-control"
                           value="{{ old('current_location', $product->current_location) }}">
                </div>

                {{-- ── Actions ───────────────────────────────────────── --}}
                <div class="col-12 d-flex justify-content-end gap-2 mt-4">
                    <a href="{{ route('products.index') }}" class="btn btn-secondary">Cancel</a>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-1"></i> Save Changes
                    </button>
                </div>

            </div>
        </form>
    </div>
</div>

@include('products.partials.manage_units_modal')
@endsection
