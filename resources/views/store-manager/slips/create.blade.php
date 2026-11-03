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
                        <label class="form-label d-flex justify-content-between align-items-center">
                            <span>Slip No *</span>
                            <span id="nextSlipBadge" class="badge bg-primary font-monospace">Next: Checking...</span>
                        </label>
                        <input type="text" name="slip_no" class="form-control font-monospace fw-bold @error('slip_no') is-invalid @enderror" id="slip_no" placeholder="Will auto-fetch next slip" value="{{ old('slip_no') }}" oninput="validateSlipSequenceInput()">
                        <div id="slipSeqAlert" class="small mt-1"></div>
                        @error('slip_no')
                            <div class="text-danger small mt-1"><i class="fas fa-exclamation-circle me-1"></i>{{ $message }}</div>
                        @enderror
                    </div>
                </div>

                @if(session('slip_gap_info'))
                @php $gap = session('slip_gap_info'); @endphp
                <div class="alert alert-warning border-start border-4 border-warning shadow-sm mb-4">
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <div>
                            <h6 class="fw-bold mb-1"><i class="fas fa-exclamation-triangle me-2"></i>Missing Slips Detected</h6>
                            <div class="small">
                                Slip <strong>#{{ $gap['given_number'] }}</strong> cannot be recorded yet because earlier numbers are missing: 
                                <strong class="font-monospace text-dark">{{ implode(', ', array_map(fn($f) => '#' . $f, $gap['missing_formatted'])) }}</strong>.
                            </div>
                        </div>
                        <button type="button" class="btn btn-warning btn-sm fw-bold shadow-xs" data-bs-toggle="modal" data-bs-target="#voidMissingModal">
                            <i class="fas fa-ban me-1"></i>Void Missing Slips & Continue
                        </button>
                    </div>
                </div>
                @endif

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
    const slipInput = document.getElementById('slip_no');
    const badge = document.getElementById('nextSlipBadge');
    const alertDiv = document.getElementById('slipSeqAlert');
    
    if (!storeId) {
        slipInput.value = '';
        badge.textContent = 'Select Store';
        badge.className = 'badge bg-secondary font-monospace';
        return;
    }

    badge.textContent = 'Checking...';
    badge.className = 'badge bg-info font-monospace';

    fetch(`/api/slip-sequences/${storeId}/${slipType}`)
        .then(res => res.json())
        .then(data => {
            if (data.has_sequence && data.formatted_slip) {
                badge.textContent = 'Next slip: #' + data.formatted_slip;
                badge.className = 'badge bg-success font-monospace';
                
                slipInput.dataset.expected = data.current_slip_no;
                slipInput.dataset.formatted = data.formatted_slip;
                slipInput.dataset.start = data.book_start_no;
                slipInput.dataset.end = data.book_end_no;
                slipInput.dataset.prefix = data.prefix || '';

                if (!slipInput.value || slipInput.classList.contains('is-invalid')) {
                    slipInput.value = data.formatted_slip;
                    slipInput.classList.remove('is-invalid');
                }

                alertDiv.innerHTML = `<span class="text-success small"><i class="fas fa-book me-1"></i>Active Book: ${data.label} (#${data.book_start_no}–#${data.book_end_no}) &bull; ${data.remaining} slips left</span>`;
                validateSlipSequenceInput();
            } else {
                badge.textContent = 'No Active Book';
                badge.className = 'badge bg-warning text-dark font-monospace';
                alertDiv.innerHTML = `<span class="text-warning small"><i class="fas fa-exclamation-triangle me-1"></i>${data.error || 'No active sequence configured for this store.'}</span>`;
            }
        })
        .catch(err => {
            badge.textContent = 'Error';
            badge.className = 'badge bg-danger font-monospace';
        });
}

function validateSlipSequenceInput() {
    const slipInput = document.getElementById('slip_no');
    const alertDiv = document.getElementById('slipSeqAlert');
    const val = slipInput.value.trim();
    const expected = parseInt(slipInput.dataset.expected);
    const start = parseInt(slipInput.dataset.start);
    const end = parseInt(slipInput.dataset.end);

    if (!val || !expected) return;

    // Extract digits
    const digits = val.match(/\d+/);
    if (!digits) {
        slipInput.classList.add('is-invalid');
        alertDiv.innerHTML = '<span class="text-danger small"><i class="fas fa-times-circle me-1"></i>Slip number must contain digits.</span>';
        return;
    }

    const num = parseInt(digits[0]);

    if (num < start || num > end) {
        slipInput.classList.add('is-invalid');
        alertDiv.innerHTML = `<span class="text-danger small"><i class="fas fa-times-circle me-1"></i>Number #${num} is outside book range (#${start}–#${end}).</span>`;
    } else if (num < expected) {
        slipInput.classList.add('is-invalid');
        alertDiv.innerHTML = `<span class="text-danger small"><i class="fas fa-times-circle me-1"></i>Duplicate: #${num} was already used. Next expected is #${slipInput.dataset.formatted || expected}.</span>`;
    } else if (num > expected) {
        slipInput.classList.add('is-invalid');
        const missingCount = num - expected;
        alertDiv.innerHTML = `<span class="text-danger small"><i class="fas fa-exclamation-triangle me-1"></i>Cannot skip ${missingCount} slip(s)! Next expected is #${slipInput.dataset.formatted || expected}. Record or void earlier slips first.</span>`;
    } else {
        slipInput.classList.remove('is-invalid');
        alertDiv.innerHTML = `<span class="text-success small"><i class="fas fa-check-circle me-1"></i>Matches expected sequence leaf (#${slipInput.dataset.formatted || expected}).</span>`;
    }
}

// Auto-run on load if store is pre-selected
document.addEventListener('DOMContentLoaded', function() {
    if (document.querySelector('select[name="store_id"]').value) {
        updateSlipNo();
    }
});

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

@if(session('slip_gap_info'))
@php $gap = session('slip_gap_info'); @endphp
<!-- Void Missing Slips Modal -->
<div class="modal fade" id="voidMissingModal" tabindex="-1" aria-labelledby="voidMissingModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content shadow border-0">
            <form action="{{ route('store-manager.slip-sequences.void-missing') }}" method="POST">
                @csrf
                <input type="hidden" name="store_id" value="{{ $gap['store_id'] }}">
                <input type="hidden" name="slip_type" value="{{ $gap['slip_type'] }}">
                @foreach($gap['missing_numbers'] as $num)
                    <input type="hidden" name="numbers[]" value="{{ $num }}">
                @endforeach

                <div class="modal-header bg-warning text-dark">
                    <h6 class="modal-title fw-bold" id="voidMissingModalLabel">
                        <i class="fas fa-ban me-2"></i>Void Missing Slips ({{ count($gap['missing_numbers']) }})
                    </h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="small text-muted mb-2">
                        You are skipping straight to <strong>#{{ $gap['given_number'] }}</strong>. To maintain sequence continuity, the missing slips must be declared as void:
                    </p>
                    <div class="p-2 bg-light rounded border mb-3 font-monospace fw-bold text-danger text-center">
                        {{ implode(', ', array_map(fn($f) => '#' . $f, $gap['missing_formatted'])) }}
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold small">Reason for Voiding *</label>
                        <textarea name="reason" class="form-control form-control-sm" rows="3" placeholder="E.g., Damaged, spoiled, paper torn, or issued outside ERP" required></textarea>
                    </div>
                    <div class="alert alert-info py-2 small mb-0">
                        <i class="fas fa-info-circle me-1"></i>Voiding creates no stock movement, advances the sequence counter to #{{ $gap['given_number'] }}, and allows your transaction to proceed.
                    </div>
                </div>
                <div class="modal-footer bg-light py-2">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-sm btn-warning fw-bold">
                        <i class="fas fa-check me-1"></i>Void Slips & Advance
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif
@endpush
@endsection

