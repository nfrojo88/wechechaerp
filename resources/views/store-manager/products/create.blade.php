@extends('layouts.app')

@section('title', 'Add Product - Store Manager')

@section('content')
<script>window.location.replace("{{ route('products.create') }}");</script>
<div class="container-fluid">
    <div class="row mb-4">
        <div class="col-12">
            <h4><i class="fas fa-plus me-2"></i>Add New Product to Catalog</h4>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-body">
            <form action="{{ route('store-manager.products.store') }}" method="POST">
                @csrf
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Product Name *</label>
                        <input type="text" name="name" id="product_name_input" class="form-control" placeholder="e.g., Reinforcing Steel Bar 12mm, Cement 50kg" required oninput="generateProductCode(this.value)">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Product Code (Auto-Generated) *</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light"><i class="fas fa-barcode text-primary"></i></span>
                            <input type="text" name="code" id="product_code_input" class="form-control bg-light fw-bold text-uppercase" placeholder="Auto-generated (e.g. MAT-001)" readonly required>
                            <button type="button" class="btn btn-outline-secondary" onclick="generateProductCode(document.getElementById('product_name_input').value)" title="Regenerate Code">
                                <i class="fas fa-sync-alt"></i>
                            </button>
                        </div>
                        <small class="text-muted">Unique product code auto-generated from product name.</small>
                    </div>
                </div>
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label class="form-label">Category</label>
                        <input type="text" name="category" class="form-control" placeholder="e.g., Steel, Cement, Tools">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Unit *</label>
                        <select name="unit" class="form-select" required>
                            <option value="pcs">Pieces (pcs)</option>
                            <option value="kg">Kilograms (kg)</option>
                            <option value="tons">Tons</option>
                            <option value="m">Meters (m)</option>
                            <option value="m2">Square Meters (m²)</option>
                            <option value="m3">Cubic Meters (m³)</option>
                            <option value="bags">Bags</option>
                            <option value="liters">Liters</option>
                            <option value="units">Units</option>
                            <option value="sets">Sets</option>
                        </select>
                    </div>
                </div>
                <div class="row mb-3">
                    <div class="col-md-12">
                        <label class="form-label">Description</label>
                        <textarea name="description" class="form-control" rows="2"></textarea>
                    </div>
                </div>
                <div class="row mb-3">
                    <div class="col-md-12">
                        <label class="form-label">Specification</label>
                        <textarea name="specification" class="form-control" rows="2"></textarea>
                    </div>
                </div>
                <div class="row mb-3">
                    <div class="col-md-4">
                        <label class="form-label">Standard Cost</label>
                        <input type="number" name="standard_cost" class="form-control" step="0.01" min="0">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Min Stock Level</label>
                        <input type="number" name="min_stock_level" class="form-control" step="0.001" min="0">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Status</label>
                        <div class="form-check mt-2">
                            <input type="checkbox" name="is_active" value="1" class="form-check-input" checked>
                            <label class="form-check-label">Active</label>
                        </div>
                    </div>
                </div>

                <hr>
                <div class="d-flex justify-content-end">
                    <a href="{{ route('store-manager.products.index') }}" class="btn btn-secondary me-2">Cancel</a>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-1"></i>Save Product
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function generateProductCode(name) {
    const codeInput = document.getElementById('product_code_input');
    if (!codeInput) return;

    const trimmed = (name || '').trim();
    if (!trimmed) {
        codeInput.value = '';
        return;
    }

    // Extract uppercase alphanumeric words
    const words = trimmed.split(/\s+/).filter(w => w.length > 0);
    let prefix = '';

    if (words.length >= 2) {
        // Take first 2-3 letters of first word + first 2-3 letters of second word
        prefix = (words[0].substring(0, 3) + '-' + words[1].substring(0, 3)).toUpperCase();
    } else {
        prefix = words[0].substring(0, 4).toUpperCase();
    }

    prefix = prefix.replace(/[^A-Z0-9\-]/g, '');
    if (prefix.length < 2) prefix = 'PRD';

    // Append a 3-digit hash or random counter for collision avoidance
    let hash = 0;
    for (let i = 0; i < trimmed.length; i++) {
        hash = ((hash << 5) - hash) + trimmed.charCodeAt(i);
        hash |= 0;
    }
    const num = Math.abs(hash % 900) + 100;

    codeInput.value = prefix + '-' + num;
}
</script>
@endsection
