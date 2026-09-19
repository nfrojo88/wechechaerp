@extends('layouts.app')
@section('title', 'Buy Material with Petty Cash - Store Keeper')

@section('content')
<div class="container-fluid px-4 py-3">

    {{-- ── Header ────────────────────────────────────────────────────────────── --}}
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
        <div class="d-flex align-items-center gap-3">
            <a href="{{ route('store-keeper.petty-cash-purchases.index') }}" class="btn btn-outline-secondary btn-sm shadow-sm">
                <i class="fa-solid fa-arrow-left"></i>
            </a>
            <div>
                <div class="d-flex align-items-center gap-2 mb-1">
                    <h3 class="fw-bold text-dark mb-0">
                        <i class="fa-solid fa-cart-shopping text-success me-2"></i>Buy Material (Petty Cash)
                    </h3>
                    <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 px-2 py-1">
                        Immediate Stock Inflow
                    </span>
                </div>
                <p class="text-muted small mb-0">
                    Purchase urgent site materials using spot Petty Cash and have them added directly into store inventory on hand.
                </p>
            </div>
        </div>
        <div class="d-flex align-items-center gap-2">
            <div class="card border-0 shadow-sm bg-white px-3 py-2 rounded-3 border-start border-4 border-warning">
                <div class="d-flex align-items-center gap-2">
                    <i class="fa-solid fa-wallet text-warning fa-lg"></i>
                    <div>
                        <small class="text-muted text-uppercase fw-bold d-block" style="font-size:0.68rem;">Available Petty Cash</small>
                        <strong class="text-dark font-monospace" id="currentPettyCashDisplay">
                            ETB {{ number_format($pettyCashAccount->current_balance ?? 0, 2) }}
                        </strong>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @if($errors->any())
    <div class="alert alert-danger border-0 shadow-sm rounded-3 mb-4 p-3 alert-dismissible fade show" role="alert">
        <div class="d-flex align-items-center gap-2">
            <i class="fa-solid fa-circle-exclamation fa-lg text-danger"></i>
            <div>
                <strong class="d-block">Please correct the following errors:</strong>
                <ul class="mb-0 small ps-3">
                    @foreach($errors->all() as $err)
                        <li>{{ $err }}</li>
                    @endforeach
                </ul>
            </div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    @endif

    {{-- ── Main Purchase Form ────────────────────────────────────────────────── --}}
    <form action="{{ route('store-keeper.petty-cash-purchases.store') }}" method="POST" enctype="multipart/form-data" id="pettyCashPurchaseForm">
        @csrf

        <div class="row g-4 mb-4">

            {{-- Left Column: Store & Receipt Details --}}
            <div class="col-lg-4">
                <div class="card border-0 shadow-sm rounded-3 h-100 bg-white">
                    <div class="card-header bg-white border-bottom py-3">
                        <h6 class="mb-0 fw-bold text-dark">
                            <i class="fa-solid fa-file-invoice-dollar text-primary me-2"></i>Purchase &amp; Receipt Details
                        </h6>
                    </div>
                    <div class="card-body p-3 p-md-4">

                        {{-- Store Selection --}}
                        <div class="mb-3">
                            <label class="form-label fw-semibold text-dark small">
                                Destination Store <span class="text-danger">*</span>
                            </label>
                            @if($isStoreKeeper && $assignedStore)
                                <div class="p-2 rounded bg-light border d-flex align-items-center justify-content-between">
                                    <div class="d-flex align-items-center gap-2">
                                        <i class="fa-solid fa-warehouse text-primary"></i>
                                        <div>
                                            <strong class="d-block small text-dark">{{ $assignedStore->name }}</strong>
                                            <small class="text-muted font-monospace">{{ $assignedStore->code ?? 'STORE' }}</small>
                                        </div>
                                    </div>
                                    <span class="badge bg-success small">Assigned</span>
                                </div>
                                <input type="hidden" name="store_id" value="{{ $assignedStore->id }}">
                            @else
                                <select name="store_id" class="form-select form-select-sm" required>
                                    <option value="">-- Select Store --</option>
                                    @foreach($stores as $st)
                                        <option value="{{ $st->id }}" {{ (old('store_id', $assignedStore->id ?? null) == $st->id) ? 'selected' : '' }}>
                                            {{ $st->name }} ({{ $st->code ?? 'N/A' }})
                                        </option>
                                    @endforeach
                                </select>
                            @endif
                            <small class="text-muted d-block mt-1" style="font-size:0.75rem;">Materials will be added to this store's stock.</small>
                        </div>

                        {{-- Petty Cash Account --}}
                        <div class="mb-3">
                            <label class="form-label fw-semibold text-dark small">
                                Paying Petty Cash Account <span class="text-danger">*</span>
                            </label>
                            @if($pettyCashAccount && $isStoreKeeper)
                                <div class="p-2 rounded bg-light border d-flex align-items-center justify-content-between">
                                    <div class="d-flex align-items-center gap-2">
                                        <i class="fa-solid fa-coins text-warning"></i>
                                        <div>
                                            <strong class="d-block small text-dark">{{ $pettyCashAccount->name }}</strong>
                                            <small class="text-muted font-monospace">[{{ $pettyCashAccount->code }}]</small>
                                        </div>
                                    </div>
                                    <span class="badge bg-warning text-dark font-monospace">ETB {{ number_format($pettyCashAccount->current_balance, 2) }}</span>
                                </div>
                                <input type="hidden" name="chart_of_account_id" value="{{ $pettyCashAccount->id }}">
                            @else
                                <select name="chart_of_account_id" class="form-select form-select-sm" required>
                                    @foreach($pettyCashAccounts as $pca)
                                        <option value="{{ $pca->id }}" {{ (old('chart_of_account_id', $pettyCashAccount->id ?? null) == $pca->id) ? 'selected' : '' }}>
                                            [{{ $pca->code }}] {{ $pca->name }} (Balance: ETB {{ number_format($pca->current_balance, 2) }})
                                        </option>
                                    @endforeach
                                </select>
                            @endif
                        </div>

                        {{-- Purchase Date --}}
                        <div class="mb-3">
                            <label class="form-label fw-semibold text-dark small">
                                Purchase Date <span class="text-danger">*</span>
                            </label>
                            <input type="date" name="purchase_date" class="form-control form-control-sm" value="{{ old('purchase_date', date('Y-m-d')) }}" required>
                        </div>

                        {{-- Supplier / Merchant Name --}}
                        <div class="mb-3">
                            <label class="form-label fw-semibold text-dark small">
                                Supplier / Merchant / Shop <span class="text-danger">*</span>
                            </label>
                            <input type="text" name="supplier_name" class="form-control form-control-sm" placeholder="e.g. Merkato Hardware, TotalEnergies, Site Vendor" value="{{ old('supplier_name') }}" required>
                        </div>

                        {{-- Receipt / Invoice Number --}}
                        <div class="mb-3">
                            <label class="form-label fw-semibold text-dark small">
                                Cash Receipt / Voucher # <span class="text-danger">*</span>
                            </label>
                            <input type="text" name="receipt_no" class="form-control form-control-sm font-monospace" placeholder="e.g. CR-89412, INV-0042" value="{{ old('receipt_no') }}" required>
                        </div>

                        {{-- Receipt Attachment --}}
                        <div class="mb-3">
                            <label class="form-label fw-semibold text-dark small">
                                Attach Receipt Image / PDF
                            </label>
                            <input type="file" name="attachment" class="form-control form-control-sm" accept="image/*,.pdf">
                            <small class="text-muted d-block mt-1" style="font-size:0.73rem;">Photo of paper receipt or physical voucher.</small>
                        </div>

                        {{-- Notes --}}
                        <div class="mb-0">
                            <label class="form-label fw-semibold text-dark small">Notes / Purpose</label>
                            <textarea name="notes" rows="2" class="form-control form-control-sm" placeholder="Purpose or site usage note...">{{ old('notes') }}</textarea>
                        </div>

                    </div>
                </div>
            </div>

            {{-- Right Column: Materials Line Items --}}
            <div class="col-lg-8">
                <div class="card border-0 shadow-sm rounded-3 h-100 bg-white d-flex flex-column">
                    <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <div>
                            <h6 class="mb-0 fw-bold text-dark">
                                <i class="fa-solid fa-boxes-stacked text-success me-2"></i>Materials to Buy &amp; Add to Inventory
                            </h6>
                            <small class="text-muted">Specify products, quantities, and price paid</small>
                        </div>
                        <div class="d-flex gap-2">
                            <button type="button" class="btn btn-sm btn-outline-primary shadow-xs" data-bs-toggle="modal" data-bs-target="#quickNewProductModal">
                                <i class="fa-solid fa-plus me-1"></i> New Product Catalog Item
                            </button>
                            <button type="button" class="btn btn-sm btn-success shadow-xs" id="addRowBtn">
                                <i class="fa-solid fa-plus me-1"></i> Add Material Row
                            </button>
                        </div>
                    </div>

                    <div class="card-body p-0 flex-grow-1">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0" id="materialsTable">
                                <thead class="table-light">
                                    <tr>
                                        <th style="width: 40%;" class="ps-3">Material / Product <span class="text-danger">*</span></th>
                                        <th style="width: 15%;">Quantity <span class="text-danger">*</span></th>
                                        <th style="width: 15%;">Unit</th>
                                        <th style="width: 15%;">Unit Price (ETB) <span class="text-danger">*</span></th>
                                        <th style="width: 15%;" class="text-end">Total (ETB)</th>
                                        <th style="width: 5%;" class="text-center pe-3"></th>
                                    </tr>
                                </thead>
                                <tbody id="itemsTbody">
                                    {{-- Row 1 --}}
                                    <tr class="item-row">
                                        <td class="ps-3">
                                            <select name="items[0][product_id]" class="form-select form-select-sm product-select" required>
                                                <option value="">-- Choose Material --</option>
                                                @foreach($products as $p)
                                                    <option value="{{ $p->id }}" data-unit="{{ $p->unit_of_measure ?? $p->unit ?? 'pcs' }}" data-price="{{ $p->unit_price ?? $p->standard_cost ?? 0 }}">
                                                        {{ $p->name }} [{{ $p->code ?? $p->sku ?? 'PRD' }}]
                                                    </option>
                                                @endforeach
                                            </select>
                                        </td>
                                        <td>
                                            <input type="number" step="0.001" min="0.001" name="items[0][quantity]" class="form-control form-control-sm qty-input text-center fw-bold" placeholder="0.00" required>
                                        </td>
                                        <td>
                                            <input type="text" name="items[0][unit]" class="form-control form-control-sm unit-input bg-light" placeholder="pcs" readonly>
                                        </td>
                                        <td>
                                            <input type="number" step="0.01" min="0" name="items[0][unit_price]" class="form-control form-control-sm price-input text-end fw-bold" placeholder="0.00" required>
                                        </td>
                                        <td class="text-end">
                                            <strong class="row-total-display font-monospace text-dark">0.00</strong>
                                        </td>
                                        <td class="text-center pe-3">
                                            <button type="button" class="btn btn-sm btn-link text-danger p-0 remove-row-btn" title="Remove Row" disabled>
                                                <i class="fa-solid fa-trash-can"></i>
                                            </button>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    {{-- Summary Footer --}}
                    <div class="card-footer bg-light border-top p-3">
                        <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                            <div class="d-flex align-items-center gap-2">
                                <span class="badge bg-secondary" id="totalItemsBadge">1 Material</span>
                                <small class="text-muted" id="inventoryPreviewNotice">
                                    <i class="fa-solid fa-circle-check text-success me-1"></i>Stock will be credited to inventory upon submission.
                                </small>
                            </div>
                            <div class="text-end">
                                <span class="text-muted small text-uppercase fw-bold d-block" style="font-size:0.7rem;">Total Purchase Amount</span>
                                <h3 class="fw-bold text-success font-monospace mb-0" id="grandTotalDisplay">ETB 0.00</h3>
                            </div>
                        </div>

                        <div class="alert alert-danger py-2 px-3 small border-0 mt-3 d-none" id="insufficientBalanceAlert">
                            <i class="fa-solid fa-triangle-exclamation me-1"></i>
                            <strong>Warning:</strong> The purchase total exceeds your active Petty Cash balance.
                        </div>

                        <div class="mt-3 text-end">
                            <a href="{{ route('store-keeper.petty-cash-purchases.index') }}" class="btn btn-outline-secondary btn-sm me-2">
                                Cancel
                            </a>
                            <button type="submit" class="btn btn-success btn-sm px-4 fw-bold shadow-sm" id="submitPurchaseBtn">
                                <i class="fa-solid fa-circle-check me-1"></i> Confirm Purchase &amp; Add Stock
                            </button>
                        </div>
                    </div>

                </div>
            </div>

        </div>
    </form>

</div>

{{-- ── Quick New Product Modal ──────────────────────────────────────────────── --}}
<div class="modal fade" id="quickNewProductModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-primary text-white py-3">
                <h6 class="modal-title fw-bold">
                    <i class="fa-solid fa-box-open me-2"></i>Quick Add Product to Catalog
                </h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="quickProductForm" action="{{ route('store-manager.products.store') }}" method="POST">
                @csrf
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label fw-semibold small text-dark">Material / Product Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" id="newProdName" class="form-control form-control-sm" placeholder="e.g. Cement 42.5N, 4-inch Nails, PVC Pipe" required>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label fw-semibold small text-dark">Unit of Measure <span class="text-danger">*</span></label>
                            <select name="unit" id="newProdUnit" class="form-select form-select-sm" required>
                                <option value="pcs">Pieces (pcs)</option>
                                <option value="kg">Kilograms (kg)</option>
                                <option value="bags">Bags</option>
                                <option value="liters">Liters</option>
                                <option value="meters">Meters (m)</option>
                                <option value="rolls">Rolls</option>
                                <option value="boxes">Boxes</option>
                                <option value="sets">Sets</option>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-semibold small text-dark">Category</label>
                            <input type="text" name="category" class="form-control form-control-sm" placeholder="e.g. Hardware, Building, Electrical">
                        </div>
                    </div>
                    <div class="mb-0">
                        <label class="form-label fw-semibold small text-dark">Standard Cost (ETB)</label>
                        <input type="number" step="0.01" name="standard_cost" class="form-control form-control-sm" placeholder="0.00">
                    </div>
                </div>
                <div class="modal-footer bg-light py-2">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm fw-bold">Save &amp; Add</button>
                </div>
            </form>
        </div>
    </div>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    let rowIndex = 1;
    const availablePettyCash = parseFloat("{{ $pettyCashAccount->current_balance ?? 0 }}") || 0;

    const tbody = document.getElementById('itemsTbody');
    const addRowBtn = document.getElementById('addRowBtn');
    const grandTotalDisplay = document.getElementById('grandTotalDisplay');
    const totalItemsBadge = document.getElementById('totalItemsBadge');
    const alertDiv = document.getElementById('insufficientBalanceAlert');

    // Product template options from existing select
    const productOptionsHtml = document.querySelector('.product-select').innerHTML;

    function recalculate() {
        let grandTotal = 0;
        let count = 0;
        const rows = document.querySelectorAll('.item-row');

        rows.forEach(function(row) {
            count++;
            const qty = parseFloat(row.querySelector('.qty-input').value) || 0;
            const price = parseFloat(row.querySelector('.price-input').value) || 0;
            const lineTotal = qty * price;

            row.querySelector('.row-total-display').textContent = lineTotal.toLocaleString('en-US', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });

            grandTotal += lineTotal;
        });

        grandTotalDisplay.textContent = 'ETB ' + grandTotal.toLocaleString('en-US', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });

        totalItemsBadge.textContent = count + (count === 1 ? ' Material' : ' Materials');

        // Check if balance warning needed
        if (availablePettyCash > 0 && grandTotal > availablePettyCash) {
            alertDiv.classList.remove('d-none');
        } else {
            alertDiv.classList.add('d-none');
        }

        // Enable/disable remove button
        const removeBtns = document.querySelectorAll('.remove-row-btn');
        removeBtns.forEach(btn => {
            btn.disabled = rows.length <= 1;
        });
    }

    function attachRowListeners(row) {
        const prodSelect = row.querySelector('.product-select');
        const unitInput = row.querySelector('.unit-input');
        const qtyInput = row.querySelector('.qty-input');
        const priceInput = row.querySelector('.price-input');
        const removeBtn = row.querySelector('.remove-row-btn');

        prodSelect.addEventListener('change', function() {
            const selectedOpt = this.options[this.selectedIndex];
            if (selectedOpt) {
                const unit = selectedOpt.getAttribute('data-unit') || 'pcs';
                const price = parseFloat(selectedOpt.getAttribute('data-price')) || 0;
                unitInput.value = unit;
                if (!priceInput.value && price > 0) {
                    priceInput.value = price;
                }
            }
            recalculate();
        });

        qtyInput.addEventListener('input', recalculate);
        priceInput.addEventListener('input', recalculate);

        removeBtn.addEventListener('click', function() {
            if (document.querySelectorAll('.item-row').length > 1) {
                row.remove();
                recalculate();
            }
        });
    }

    // Attach listeners to initial row
    document.querySelectorAll('.item-row').forEach(attachRowListeners);

    // Add row button
    addRowBtn.addEventListener('click', function() {
        const tr = document.createElement('tr');
        tr.className = 'item-row';
        tr.innerHTML = `
            <td class="ps-3">
                <select name="items[${rowIndex}][product_id]" class="form-select form-select-sm product-select" required>
                    ${productOptionsHtml}
                </select>
            </td>
            <td>
                <input type="number" step="0.001" min="0.001" name="items[${rowIndex}][quantity]" class="form-control form-control-sm qty-input text-center fw-bold" placeholder="0.00" required>
            </td>
            <td>
                <input type="text" name="items[${rowIndex}][unit]" class="form-control form-control-sm unit-input bg-light" placeholder="pcs" readonly>
            </td>
            <td>
                <input type="number" step="0.01" min="0" name="items[${rowIndex}][unit_price]" class="form-control form-control-sm price-input text-end fw-bold" placeholder="0.00" required>
            </td>
            <td class="text-end">
                <strong class="row-total-display font-monospace text-dark">0.00</strong>
            </td>
            <td class="text-center pe-3">
                <button type="button" class="btn btn-sm btn-link text-danger p-0 remove-row-btn" title="Remove Row">
                    <i class="fa-solid fa-trash-can"></i>
                </button>
            </td>
        `;

        tbody.appendChild(tr);
        attachRowListeners(tr);
        rowIndex++;
        recalculate();
    });

    // Form confirmation
    document.getElementById('pettyCashPurchaseForm').addEventListener('submit', function(e) {
        const rows = document.querySelectorAll('.item-row');
        if (rows.length === 0) {
            e.preventDefault();
            alert('Please add at least one material item.');
            return false;
        }
        return true;
    });
});
</script>
@endpush
@endsection
