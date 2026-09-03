@extends('layouts.app')

@section('title', 'New Product')

@section('content')
<div class="d-flex align-items-center mb-4">
    <a href="{{ route('products.index') }}" class="btn btn-sm btn-outline-secondary me-3">
        <i class="fas fa-arrow-left"></i>
    </a>
    <h1 class="h3 mb-0">New Product / Material</h1>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body">
        <form method="POST" action="{{ route('products.store') }}">
            @csrf
            <div class="row g-3">

                {{-- ── Basic Info ────────────────────────────────────── --}}
                <div class="col-12"><h6 class="border-bottom pb-2 text-muted">Basic Information</h6></div>

                <div class="col-md-6">
                    <label class="form-label">Name <span class="text-danger">*</span></label>
                    <input type="text" name="name" id="product_name_main" class="form-control @error('name') is-invalid @enderror"
                           value="{{ old('name') }}" required placeholder="e.g. Reinforcing Steel Bar 12mm" oninput="generateMainSku(this.value)">
                    @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-3">
                    <label class="form-label">SKU / Code (Auto) <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <input type="text" name="sku" id="product_sku_main" class="form-control bg-light @error('sku') is-invalid @enderror"
                               value="{{ old('sku') }}" required placeholder="Auto-generated" readonly>
                        <button type="button" class="btn btn-outline-secondary btn-sm" onclick="generateMainSku(document.getElementById('product_name_main').value)" title="Regenerate">
                            <i class="fas fa-sync-alt"></i>
                        </button>
                    </div>
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
                                <option value="{{ $uCode }}" @selected(old('unit') == $uCode)>{{ $uLabel }}</option>
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
                        <option value="{{ $category }}" @selected(old('category') == $category)>{{ $category }}</option>
                        @endforeach
                    </select>
                    @error('category')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label">Sub-Category</label>
                    <select name="sub_category" class="form-select">
                        <option value="">— Select Sub-Category —</option>
                        @foreach($subCategories as $sc)
                        <option value="{{ $sc }}" @selected(old('sub_category') == $sc)>{{ $sc }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- ── Pricing ───────────────────────────────────────── --}}
                <div class="col-12 mt-3"><h6 class="border-bottom pb-2 text-muted">Pricing</h6></div>

                <div class="col-md-3">
                    <label class="form-label">Unit Price (ETB)</label>
                    <input type="number" step="0.01" min="0" name="unit_price"
                           class="form-control" value="{{ old('unit_price', 0) }}">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Selling Price (ETB)</label>
                    <input type="number" step="0.01" min="0" name="selling_price"
                           class="form-control" value="{{ old('selling_price', 0) }}">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Purchase Threshold (%)</label>
                    <input type="number" step="0.01" min="0" max="100" name="purchase_threshold"
                           class="form-control" value="{{ old('purchase_threshold', 5) }}">
                </div>

                {{-- ── Inventory ─────────────────────────────────────── --}}
                <div class="col-12 mt-3"><h6 class="border-bottom pb-2 text-muted">Inventory</h6></div>

                <div class="col-md-3">
                    <label class="form-label">Max Stock</label>
                    <input type="number" step="0.01" min="0" name="max_stock"
                           class="form-control" value="{{ old('max_stock', 100) }}">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Reorder Level</label>
                    <input type="number" min="0" name="reorder_level"
                           class="form-control" value="{{ old('reorder_level', 20) }}">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Carton / Pack Size</label>
                    <input type="number" min="0" name="carton_size"
                           class="form-control" value="{{ old('carton_size') }}">
                </div>

                {{-- ── Dimensions ────────────────────────────────────── --}}
                <div class="col-12 mt-3"><h6 class="border-bottom pb-2 text-muted">Dimensions (optional)</h6></div>

                <div class="col-md-3">
                    <label class="form-label">Standard Length (m)</label>
                    <input type="number" step="0.01" min="0" name="standard_length"
                           class="form-control" value="{{ old('standard_length', 0) }}">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Standard Width (m)</label>
                    <input type="number" step="0.001" min="0" name="standard_width"
                           class="form-control" value="{{ old('standard_width', 0) }}">
                </div>

                {{-- ── Asset / Equipment ─────────────────────────────── --}}
                <div class="col-12 mt-3"><h6 class="border-bottom pb-2 text-muted">Asset / Equipment Details</h6></div>

                <div class="col-md-4">
                    <label class="form-label">Asset Status</label>
                    <select name="asset_status" class="form-select">
                        @foreach($assetStatuses as $status)
                        <option value="{{ $status }}" @selected(old('asset_status', 'Available') == $status)>{{ $status }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Condition</label>
                    <select name="equipment_condition" class="form-select">
                        @foreach($conditions as $cond)
                        <option value="{{ $cond }}" @selected(old('equipment_condition', 'Good') == $cond)>{{ $cond }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Baseline Date</label>
                    <input type="date" name="baseline_date" class="form-control" value="{{ old('baseline_date') }}">
                </div>

                <div class="col-md-6">
                    <label class="form-label">Assigned To</label>
                    <input type="text" name="assigned_to" class="form-control"
                           value="{{ old('assigned_to', 'Unassigned') }}" placeholder="Person or department">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Current Location</label>
                    <input type="text" name="current_location" class="form-control"
                           value="{{ old('current_location', 'Main Store') }}" placeholder="e.g. Site A, Warehouse 2">
                </div>

                {{-- ── Actions ───────────────────────────────────────── --}}
                <div class="col-12 d-flex justify-content-end gap-2 mt-4">
                    <a href="{{ route('products.index') }}" class="btn btn-secondary">Cancel</a>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-1"></i> Create Product
                    </button>
                </div>

            </div>
        </form>
    </div>
</div>

<script>
function generateMainSku(name) {
    const skuInput = document.getElementById('product_sku_main');
    if (!skuInput) return;

    const trimmed = (name || '').trim();
    if (!trimmed) {
        skuInput.value = '';
        return;
    }

    const words = trimmed.split(/\s+/).filter(w => w.length > 0);
    let prefix = '';

    if (words.length >= 2) {
        prefix = (words[0].substring(0, 3) + '-' + words[1].substring(0, 3)).toUpperCase();
    } else {
        prefix = words[0].substring(0, 4).toUpperCase();
    }

    prefix = prefix.replace(/[^A-Z0-9\-]/g, '');
    if (prefix.length < 2) prefix = 'PRD';

    let hash = 0;
    for (let i = 0; i < trimmed.length; i++) {
        hash = ((hash << 5) - hash) + trimmed.charCodeAt(i);
        hash |= 0;
    }
    const num = Math.abs(hash % 900) + 100;

    skuInput.value = prefix + '-' + num;
}
</script>

@include('products.partials.manage_units_modal')
@endsection
