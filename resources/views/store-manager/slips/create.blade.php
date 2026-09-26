@extends('layouts.app')

@section('title', 'Create Slip - Store Manager')

@section('content')
@push('styles')
@include('layouts._store_mobile')
@endpush

<div class="container-fluid">
    <div class="row mb-4">
        <div class="col-12">
            <h4><i class="fas fa-receipt me-2"></i>Create New Slip</h4>
            <p class="text-muted">Create a Receive or Send slip with automatic slip sequence</p>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-body">
            <form action="{{ route('store-manager.slips.store') }}" method="POST">
                @csrf
                
                <!-- Slip Type Selection -->
                <div class="row mb-4">
                    <div class="col-12">
                        <label class="form-label fw-bold">Slip Type *</label>
                        <div class="btn-group w-100" role="group">
                            <input type="radio" class="btn-check" name="slip_type" id="receive" value="receive" checked onchange="toggleSlipType()">
                            <label class="btn btn-outline-success w-50" for="receive">
                                <i class="fas fa-arrow-down me-2"></i>Receive Slip
                            </label>

                            <input type="radio" class="btn-check" name="slip_type" id="send" value="send" onchange="toggleSlipType()">
                            <label class="btn btn-outline-info w-50" for="send">
                                <i class="fas fa-arrow-up me-2"></i>Send Slip
                            </label>
                        </div>
                    </div>
                </div>

                <hr>

                <!-- Slip Details -->
                <div class="row mb-3">
                    <div class="col-md-4">
                        <label class="form-label">Store *</label>
                        <select name="store_id" class="form-select" id="store_id" required onchange="updateSlipNo()">
                            <option value="">Select Store</option>
                            @foreach($stores as $store)
                            <option value="{{ $store->id }}">{{ $store->name }} ({{ $store->type }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Slip Date *</label>
                        <input type="date" name="slip_date" class="form-control" value="{{ date('Y-m-d') }}" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Slip No * <small class="text-muted">(Auto-generated)</small></label>
                        <input type="text" name="slip_no" class="form-control" id="slip_no" placeholder="Will auto-generate" readonly style="background-color: #f5f5f5;">
                    </div>
                </div>

                <!-- Receive Slip Fields -->
                <div id="receive-fields">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Supplier Name</label>
                            <input type="text" name="supplier_name" class="form-control" placeholder="Supplier/Vendor name">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Reference No</label>
                            <input type="text" name="reference_no" class="form-control" placeholder="Invoice #, PO #, etc.">
                        </div>
                    </div>
                </div>

                <!-- Send Slip Fields -->
                <div id="send-fields" style="display: none;">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">To Store *</label>
                            <select name="to_store_id" class="form-select">
                                <option value="">Select Destination Store</option>
                                @foreach($stores as $store)
                                <option value="{{ $store->id }}">{{ $store->name }} ({{ $store->type }})</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Reference</label>
                            <input type="text" name="reference_no" class="form-control" placeholder="Transfer reference">
                        </div>
                    </div>
                </div>

                <hr>
                <h5>Slip Items</h5>
                <div id="items-container">
                    <div class="row item-row mb-2">
                        <div class="col-md-5">
                            <select name="items[0][product_id]" class="form-select product-select" required>
                                <option value="">Select Product</option>
                                @foreach($products as $product)
                                <option value="{{ $product->id }}" data-unit="{{ $product->unit }}">{{ $product->name }} ({{ $product->code }})</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3">
                            <input type="number" name="items[0][quantity]" class="form-control" placeholder="Quantity" step="0.001" min="0.001" required>
                        </div>
                        <div class="col-md-2 unit-column">
                            <input type="text" class="form-control" placeholder="Unit" disabled style="background-color: #f5f5f5;">
                        </div>
                        <div class="col-md-2 cost-column" style="display: none;">
                            <input type="number" name="items[0][unit_cost]" class="form-control" placeholder="Cost" step="0.01" min="0">
                        </div>
                        <div class="col-md-2">
                            <button type="button" class="btn btn-danger remove-item"><i class="fas fa-times"></i></button>
                        </div>
                    </div>
                </div>
                <button type="button" id="add-item" class="btn btn-sm btn-outline-primary mt-2">
                    <i class="fas fa-plus me-1"></i>Add Item
                </button>

                <hr>
                <div class="d-flex justify-content-end">
                    <a href="{{ route('store-manager.slips.index') }}" class="btn btn-secondary me-2">Cancel</a>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-paper-plane me-1"></i>Create Slip
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

@push('scripts')
<script>
let itemIndex = 1;

function toggleSlipType() {
    const slipType = document.querySelector('input[name="slip_type"]:checked').value;
    const receiveFields = document.getElementById('receive-fields');
    const sendFields = document.getElementById('send-fields');
    const costColumns = document.querySelectorAll('.cost-column');
    const toStoreSelect = document.querySelector('select[name="to_store_id"]');
    
    if (slipType === 'receive') {
        receiveFields.style.display = 'block';
        sendFields.style.display = 'none';
        costColumns.forEach(col => col.style.display = 'block');
        if (toStoreSelect) toStoreSelect.removeAttribute('required');
    } else {
        receiveFields.style.display = 'none';
        sendFields.style.display = 'block';
        costColumns.forEach(col => col.style.display = 'none');
        if (toStoreSelect) toStoreSelect.setAttribute('required', 'required');
    }
    updateSlipNo();
}

function updateSlipNo() {
    const storeId = document.querySelector('select[name="store_id"]').value;
    const slipType = document.querySelector('input[name="slip_type"]:checked').value;
    
    if (!storeId) {
        document.getElementById('slip_no').value = '';
        return;
    }

    const typePrefix = slipType === 'receive' ? 'RS' : 'SS';
    const date = new Date().toISOString().slice(0, 10).replace(/-/g, '');
    document.getElementById('slip_no').value = `${typePrefix}-${date}-[SEQ]`;
}

$('#add-item').click(function() {
    const slipType = document.querySelector('input[name="slip_type"]:checked').value;
    const costVisible = slipType === 'receive' ? 'block' : 'none';
    
    let row = `<div class="row item-row mb-2">
        <div class="col-md-5">
            <select name="items[${itemIndex}][product_id]" class="form-select product-select" required>
                <option value="">Select Product</option>
                @foreach($products as $product)
                <option value="{{ $product->id }}" data-unit="{{ $product->unit }}">{{ $product->name }} ({{ $product->code }})</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-3">
            <input type="number" name="items[${itemIndex}][quantity]" class="form-control" placeholder="Quantity" step="0.001" min="0.001" required>
        </div>
        <div class="col-md-2 unit-column">
            <input type="text" class="form-control" placeholder="Unit" disabled style="background-color: #f5f5f5;">
        </div>
        <div class="col-md-2 cost-column" style="display: ${costVisible};">
            <input type="number" name="items[${itemIndex}][unit_cost]" class="form-control" placeholder="Cost" step="0.01" min="0">
        </div>
        <div class="col-md-2">
            <button type="button" class="btn btn-danger remove-item"><i class="fas fa-times"></i></button>
        </div>
    </div>`;
    $('#items-container').append(row);
    itemIndex++;
});

$(document).on('click', '.remove-item', function() {
    if ($('.item-row').length > 1) {
        $(this).closest('.item-row').remove();
    }
});

$(document).on('change', '.product-select', function() {
    let unit = $(this).find('option:selected').data('unit');
    $(this).closest('.item-row').find('.unit-column input').val(unit);
});
</script>
@endpush
@endsection

