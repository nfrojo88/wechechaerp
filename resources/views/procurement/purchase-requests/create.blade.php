@extends('layouts.app')
@section('title', 'Create Purchase Request')
@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0"><i class="fas fa-file-invoice me-2"></i>New Purchase Request</h1>
        <a href="{{ route('procurement.my-queue') }}" class="btn btn-outline-secondary"><i class="fas fa-arrow-left me-1"></i>Back</a>
    </div>
    <form action="{{ route('purchase-requests.store') }}" method="POST">
        @csrf
        @if($isSecretary ?? false)
        <div class="alert alert-info border-info bg-info bg-opacity-10 d-flex align-items-center gap-2 mb-3 shadow-sm rounded-3">
            <i class="fa-solid fa-building-circle-check text-info fs-4"></i>
            <div>
                <strong>Head Office Requisition:</strong> As Secretary, this purchase request is automatically configured for <strong>Head Office Project</strong> and <strong>Head Office Store</strong>.
            </div>
        </div>
        @endif

        <div class="row g-3">
            <div class="col-md-8">
                <div class="card shadow-sm">
                    <div class="card-header fw-semibold d-flex justify-content-between align-items-center">
                        <span>Request Details</span>
                        @if($isSecretary ?? false)
                            <span class="badge bg-primary-subtle text-primary border border-primary"><i class="fa-solid fa-shield-halved me-1"></i> Secretary Requisition</span>
                        @endif
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">
                                    Project <span class="text-danger">*</span>
                                    @if($isSecretary ?? false)
                                        <span class="badge bg-primary-subtle text-primary border border-primary ms-1"><i class="fa-solid fa-building me-1"></i> Head Office Only</span>
                                    @endif
                                </label>
                                <select name="project_id" id="project_select" class="form-select {{ ($isSecretary ?? false) ? 'bg-light' : '' }}" required>
                                    @if(!($isSecretary ?? false))
                                        <option value="">-- Select Project --</option>
                                    @endif
                                    @foreach($projects as $p)
                                        <option value="{{ $p->id }}" {{ (old('project_id') == $p->id || ($isSecretary ?? false) || $loop->count == 1) ? 'selected' : '' }}>
                                            {{ $p->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">
                                    Store
                                    @if($isSecretary ?? false)
                                        <span class="badge bg-info-subtle text-info border border-info ms-1"><i class="fa-solid fa-warehouse me-1"></i> Head Office Store</span>
                                    @endif
                                </label>
                                <select name="store_id" id="store_select" class="form-select {{ ($isSecretary ?? false) ? 'bg-light' : '' }}">
                                    @if(!($isSecretary ?? false))
                                        <option value="">-- Select Store --</option>
                                    @endif
                                    @foreach($stores as $s)
                                        <option value="{{ $s->id }}" data-project-id="{{ $s->project_id }}" {{ (old('store_id') == $s->id || ($isSecretary ?? false) || $loop->count == 1) ? 'selected' : '' }}>
                                            {{ $s->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Priority</label>
                                <select name="priority" class="form-select">
                                    <option value="normal">Normal</option><option value="high">High</option><option value="urgent">Urgent</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Type</label>
                                <select name="type" class="form-select">
                                    <option value="normal">Normal</option><option value="emergency">Emergency</option><option value="direct">Direct</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Required Date</label>
                                <input type="date" name="required_date" class="form-control" value="{{ old('required_date') }}">
                            </div>
                            <div class="col-12">
                                <label class="form-label fw-semibold">Justification</label>
                                <textarea name="justification" class="form-control" rows="2">{{ old('justification') }}</textarea>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card shadow-sm mt-3">
                    <div class="card-header d-flex justify-content-between fw-semibold">
                        <span>Items</span>
                        <button type="button" class="btn btn-sm btn-success" id="addItem"><i class="fas fa-plus me-1"></i>Add Item</button>
                    </div>
                    <div class="card-body p-0">
                        <table class="table align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Product <span class="text-danger">*</span></th>
                                    <th>Qty <span class="text-danger">*</span></th>
                                    <th>Unit (Auto)</th>
                                    <th>Est. Cost/Unit (Marketing Team)</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody id="itemsBody">
                                <tr>
                                    <td>
                                        <select name="items[0][product_id]" class="form-select product-select" required onchange="handleProductChange(this)">
                                            <option value="">-- Product --</option>
                                            @foreach($products as $p)
                                                <option value="{{ $p->id }}" data-unit="{{ $p->unit ?? 'pcs' }}" data-price="{{ $p->latest_marketing_price ?? 0 }}">
                                                    {{ $p->name }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td><input type="number" name="items[0][quantity]" class="form-control" min="0.001" step="0.001" placeholder="0.00" required></td>
                                    <td><input type="text" name="items[0][unit]" class="form-control item-unit bg-light" placeholder="Auto" readonly required></td>
                                    <td>
                                        <div class="input-group">
                                            <span class="input-group-text bg-light">ETB</span>
                                            <input type="number" name="items[0][estimated_unit_cost]" class="form-control item-cost bg-light fw-bold text-dark" step="0.01" placeholder="0.00" readonly>
                                        </div>
                                    </td>
                                    <td><button type="button" class="btn btn-sm btn-outline-danger remove-row"><i class="fas fa-times"></i></button></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card shadow-sm">
                    <div class="card-body">
                        <button type="submit" class="btn btn-primary w-100"><i class="fas fa-save me-1"></i>Save Purchase Request</button>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>
@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const projectSelect = document.getElementById('project_select');
    const storeSelect = document.getElementById('store_select');

    if (projectSelect && storeSelect) {
        function autoSelectProjectStore() {
            const selectedProjectId = projectSelect.value;
            if (!selectedProjectId) return;

            let matchedStoreId = '';
            for (let i = 0; i < storeSelect.options.length; i++) {
                const opt = storeSelect.options[i];
                if (opt.getAttribute('data-project-id') == selectedProjectId) {
                    matchedStoreId = opt.value;
                    break;
                }
            }

            if (matchedStoreId) {
                storeSelect.value = matchedStoreId;
            }
        }

        projectSelect.addEventListener('change', autoSelectProjectStore);

        if (projectSelect.value && !storeSelect.value) {
            autoSelectProjectStore();
        }
    }
});

let idx = 1;
const productOptions = `@foreach($products as $p)<option value="{{ $p->id }}" data-unit="{{ $p->unit ?? 'pcs' }}" data-price="{{ $p->latest_marketing_price ?? 0 }}">{{ $p->name }}</option>@endforeach`;

function handleProductChange(selectElem) {
    const selectedOption = selectElem.options[selectElem.selectedIndex];
    const row = selectElem.closest('tr');
    if (!row) return;

    const unitInput = row.querySelector('.item-unit');
    const costInput = row.querySelector('.item-cost');

    if (selectedOption && selectedOption.value) {
        const unit = selectedOption.getAttribute('data-unit') || 'pcs';
        const price = selectedOption.getAttribute('data-price') || '0.00';
        
        if (unitInput) unitInput.value = unit;
        if (costInput) costInput.value = parseFloat(price).toFixed(2);
    } else {
        if (unitInput) unitInput.value = '';
        if (costInput) costInput.value = '';
    }
}

document.getElementById('addItem').addEventListener('click', function() {
    const r = `<tr>
        <td>
            <select name="items[${idx}][product_id]" class="form-select product-select" required onchange="handleProductChange(this)">
                <option value="">-- Product --</option>
                ${productOptions}
            </select>
        </td>
        <td><input type="number" name="items[${idx}][quantity]" class="form-control" min="0.001" step="0.001" placeholder="0.00" required></td>
        <td><input type="text" name="items[${idx}][unit]" class="form-control item-unit bg-light" placeholder="Auto" readonly required></td>
        <td>
            <div class="input-group">
                <span class="input-group-text bg-light">ETB</span>
                <input type="number" name="items[${idx}][estimated_unit_cost]" class="form-control item-cost bg-light fw-bold text-dark" step="0.01" placeholder="0.00" readonly>
            </div>
        </td>
        <td><button type="button" class="btn btn-sm btn-outline-danger remove-row"><i class="fas fa-times"></i></button></td>
    </tr>`;
    document.getElementById('itemsBody').insertAdjacentHTML('beforeend', r);
    idx++;
});
document.getElementById('itemsBody').addEventListener('click', e => { if(e.target.closest('.remove-row')) e.target.closest('tr').remove(); });
</script>
@endpush
@endsection
