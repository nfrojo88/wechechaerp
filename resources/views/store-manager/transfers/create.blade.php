@extends('layouts.app')

@section('title', 'Create Transfer - Store Manager')

@section('content')
@push('styles')
@include('layouts._store_mobile')
@endpush

<div class="container-fluid">
    <div class="row mb-4">
        <div class="col-12">
            <h4><i class="fas fa-exchange-alt me-2"></i>Create Transfer</h4>
            <p class="text-muted">Create a transfer request and send to General Service for scheduling</p>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-body">
            <form action="{{ route('store-manager.transfers.store') }}" method="POST">
                @csrf
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label class="form-label">From Store *</label>
                        <select name="from_store_id" class="form-select" required>
                            <option value="">Select Source Store</option>
                            @foreach($stores as $store)
                            <option value="{{ $store->id }}">{{ $store->name }} ({{ $store->type }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">To Store *</label>
                        <select name="to_store_id" class="form-select" required>
                            <option value="">Select Destination Store</option>
                            @foreach($stores as $store)
                            <option value="{{ $store->id }}">{{ $store->name }} ({{ $store->type }})</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label class="form-label">Required Date</label>
                        <input type="date" name="required_date" class="form-control">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Reason</label>
                        <input type="text" name="reason" class="form-control" placeholder="Transfer reason">
                    </div>
                </div>

                <hr>
                <h5>Transfer Items</h5>
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
                        <div class="col-md-2">
                            <input type="text" name="items[0][unit]" class="form-control unit-input" placeholder="Unit">
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
                    <a href="{{ route('store-manager.transfers.index') }}" class="btn btn-secondary me-2">Cancel</a>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-paper-plane me-1"></i>Create Transfer & Send to General Service
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

@push('scripts')
<script>
let itemIndex = 1;
$('#add-item').click(function() {
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
        <div class="col-md-2">
            <input type="text" name="items[${itemIndex}][unit]" class="form-control unit-input" placeholder="Unit">
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
    $(this).closest('.item-row').find('.unit-input').val(unit);
});
</script>
@endpush
@endsection

