@extends('layouts.app')
@section('title', 'Buy Material with Petty Cash - Store Keeper')

@section('content')
@push('styles')
@include('layouts._store_mobile')
@endpush

<div class="container-fluid">

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
            <button type="button" class="btn btn-primary btn-sm fw-bold shadow-sm px-3 py-2 text-white" data-bs-toggle="modal" data-bs-target="#askReplenishmentModal" title="Ask for replacement money & submit directly to Internal Audit">
                <i class="fa-solid fa-hand-holding-dollar me-1"></i> Ask for Replenishment (Send to Audit)
            </button>
            @canany(['admin', 'global_admin', 'finance_head', 'finance_officer', 'store_manager'])
            <button type="button" class="btn btn-outline-warning btn-sm fw-bold shadow-sm px-2.5 py-2 text-dark" data-bs-toggle="modal" data-bs-target="#sendReplacementModal" title="Direct spot disbursement voucher (Admin / Finance override)">
                <i class="fa-solid fa-money-bill-transfer me-1"></i> Direct Disburse
            </button>
            @endcanany
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
                                <select name="store_id" id="storeSelect" class="form-select form-select-sm" required>
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

                        {{-- Dedicated Site Petty Cash Account --}}
                        <div class="mb-3">
                            <label class="form-label fw-semibold text-dark small">
                                Paying Petty Cash Account <span class="text-danger">*</span>
                            </label>
                            <div class="p-2 rounded bg-light border d-flex align-items-center justify-content-between" id="pettyCashAccountCard">
                                <div class="d-flex align-items-center gap-2">
                                    <i class="fa-solid fa-coins text-warning"></i>
                                    <div>
                                        <strong class="d-block small text-dark" id="pettyCashNameDisplay">{{ $pettyCashAccount->name ?? 'Site Petty Cash' }}</strong>
                                        <small class="text-muted font-monospace" id="pettyCashCodeDisplay">[{{ $pettyCashAccount->code ?? 'N/A' }}]</small>
                                    </div>
                                </div>
                                <span class="badge bg-warning text-dark font-monospace" id="pettyCashBalanceDisplay">
                                    ETB {{ number_format($pettyCashAccount->current_balance ?? 0, 2) }}
                                </span>
                            </div>
                            <input type="hidden" name="chart_of_account_id" id="pettyCashAccountIdInput" value="{{ $pettyCashAccount->id ?? '' }}">
                            <small class="text-muted d-block mt-1" style="font-size:0.75rem;">
                                <i class="fa-solid fa-shield-halved text-success me-1"></i>Dedicated site petty cash fund for this store (isolated from corporate 1010).
                            </small>

                            {{-- Zero / Low Balance Warning & Quick Replenish Request Trigger --}}
                            <div class="p-2.5 rounded-3 border border-warning bg-warning bg-opacity-10 mt-2 {{ ($pettyCashAccount->current_balance ?? 0) <= 0 ? '' : 'd-none' }}" id="zeroBalanceAlert">
                                <div class="d-flex align-items-center gap-2 mb-2">
                                    <i class="fa-solid fa-triangle-exclamation text-warning fa-lg"></i>
                                    <small class="text-dark">
                                        <strong>Fund balance is ETB 0.00:</strong> Store Keeper needs replacement funds. Submit unreplenished purchases directly to Internal Audit for clearance.
                                    </small>
                                </div>
                                <button type="button" class="btn btn-primary btn-sm w-100 fw-bold shadow-xs py-1.5" data-bs-toggle="modal" data-bs-target="#askReplenishmentModal">
                                    <i class="fa-solid fa-hand-holding-dollar me-1"></i> Ask for Replenishment (Send to Audit)
                                </button>
                            </div>

                            <div class="mt-2.5 d-flex justify-content-between align-items-center">
                                <a href="javascript:void(0)" class="text-primary small fw-semibold text-decoration-none" data-bs-toggle="modal" data-bs-target="#askReplenishmentModal">
                                    <i class="fa-solid fa-hand-holding-dollar me-1"></i>Ask for Replenishment
                                </a>
                                @canany(['admin', 'global_admin', 'finance_head', 'store_manager'])
                                <a href="javascript:void(0)" class="text-muted small text-decoration-none" data-bs-toggle="modal" data-bs-target="#sendReplacementModal">
                                    <i class="fa-solid fa-money-bill-transfer me-1"></i>Direct Disburse
                                </a>
                                @endcanany
                            </div>
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

{{-- ── Modal: Send Replacement Money to Store Keeper ─────────────────────── --}}
<div class="modal fade" id="sendReplacementModal" tabindex="-1" aria-labelledby="sendReplacementModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <form action="{{ route('store-keeper.petty-cash-purchases.send-replacement') }}" method="POST" enctype="multipart/form-data" id="sendReplacementForm">
                @csrf
                <div class="modal-header bg-warning text-dark py-3">
                    <div>
                        <h5 class="modal-title fw-bold mb-0" id="sendReplacementModalLabel">
                            <i class="fa-solid fa-money-bill-transfer me-2"></i>Send Replacement Money to Store Keeper
                        </h5>
                        <small class="text-dark-50" style="font-size:0.75rem;">Disburse spot replenishment funds into this Store's Site Petty Cash account</small>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">

                    {{-- Store & Fund Summary Ribbon --}}
                    <div class="p-3 rounded-3 bg-light border mb-3 d-flex flex-wrap justify-content-between align-items-center gap-2">
                        <div>
                            <small class="text-muted text-uppercase fw-bold d-block" style="font-size:0.68rem;">Destination Store &amp; Site Fund</small>
                            <span class="badge bg-primary px-2 py-1 me-2 font-monospace" id="modalStoreNameBadge">
                                {{ $assignedStore->name ?? ($stores->first()->name ?? 'Site Store') }}
                            </span>
                            <strong class="text-dark small" id="modalSitePettyAccountDisplay">
                                {{ $pettyCashAccount->name ?? 'Site Petty Cash' }} [{{ $pettyCashAccount->code ?? 'N/A' }}]
                            </strong>
                        </div>
                        <div>
                            <span class="badge bg-warning text-dark font-monospace px-2.5 py-1.5" id="modalCurrentBalanceDisplay">
                                Current Balance: ETB {{ number_format($pettyCashAccount->current_balance ?? 0, 2) }}
                            </span>
                        </div>
                    </div>

                    <input type="hidden" name="store_id" id="modalRepStoreId" value="{{ $assignedStore->id ?? ($stores->first()->id ?? '') }}">

                    <div class="row g-2 g-md-3 filter-form">
                        {{-- Recipient Store Keeper --}}
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small text-dark">
                                Recipient Store Keeper <span class="text-danger">*</span>
                            </label>
                            <select name="recipient_id" id="modalRecipientSelect" class="form-select form-select-sm" required>
                                @foreach($storeKeepers as $k)
                                    @php
                                        $kPhone = $k->phone ?? $k->employee?->phone ?? $k->employee?->mobile_phone ?? '';
                                    @endphp
                                    <option value="{{ $k->id }}" data-store="{{ $k->store_id }}" data-phone="{{ $kPhone }}"
                                        {{ (optional($assignedStore)->manager_id == $k->id || Auth::id() == $k->id) ? 'selected' : '' }}>
                                        {{ $k->name }} ({{ $k->email }}) {{ $kPhone ? '— ' . $kPhone : '' }}
                                    </option>
                                @endforeach
                            </select>
                            <small class="text-muted d-block mt-1" style="font-size:0.72rem;">Custodian responsible for this site fund</small>
                        </div>

                        {{-- Disbursing / Source Account --}}
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small text-dark">
                                Source Funding Account (Bank / Cash) <span class="text-danger">*</span>
                            </label>
                            <select name="source_coa_id" id="modalSourceCoaSelect" class="form-select form-select-sm" required>
                                <option value="">-- Choose Paying Bank / Cash Account --</option>
                                @foreach($sourceAccounts as $sa)
                                    <option value="{{ $sa->id }}" {{ $loop->first ? 'selected' : '' }}>
                                        [{{ $sa->code }}] {{ $sa->name }} (ETB {{ number_format($sa->current_balance, 2) }})
                                    </option>
                                @endforeach
                            </select>
                            <small class="text-muted d-block mt-1" style="font-size:0.72rem;">Account credited for this cash transfer</small>
                        </div>

                        {{-- Replacement Amount (ETB) --}}
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small text-dark">
                                Replacement Amount (ETB) <span class="text-danger">*</span>
                            </label>
                            <div class="input-group input-group-sm">
                                <span class="input-group-text fw-bold">ETB</span>
                                <input type="number" step="0.01" min="1" name="amount" id="modalRepAmount" class="form-control font-monospace fw-bold fs-6" placeholder="0.00" required>
                            </div>
                            <div class="d-flex gap-1 mt-1.5 flex-wrap">
                                <span class="text-muted small me-1" style="font-size:0.72rem;">Quick:</span>
                                <button type="button" class="btn btn-xs btn-outline-secondary py-0 px-1.5 quick-amt-btn" data-amt="5000">+5,000</button>
                                <button type="button" class="btn btn-xs btn-outline-secondary py-0 px-1.5 quick-amt-btn" data-amt="10000">+10,000</button>
                                <button type="button" class="btn btn-xs btn-outline-secondary py-0 px-1.5 quick-amt-btn" data-amt="25000">+25,000</button>
                                <button type="button" class="btn btn-xs btn-outline-secondary py-0 px-1.5 quick-amt-btn" data-amt="50000">+50,000</button>
                            </div>
                        </div>

                        {{-- Transfer Date --}}
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small text-dark">
                                Transfer / Disbursement Date <span class="text-danger">*</span>
                            </label>
                            <input type="date" name="transfer_date" class="form-control form-control-sm" value="{{ date('Y-m-d') }}" required>
                        </div>

                        {{-- Payment Method --}}
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small text-dark">
                                Payment Method / Mode <span class="text-danger">*</span>
                            </label>
                            <select name="payment_method" class="form-select form-select-sm" required>
                                <option value="Bank Transfer / CBE Birr" selected>Bank Transfer (CBE / Commercial)</option>
                                <option value="Cash in Hand">Cash on Hand (Direct Handover)</option>
                                <option value="Telebirr / Mobile Money">Telebirr / Mobile Money</option>
                                <option value="Awash Bank Transfer">Awash Bank Transfer</option>
                                <option value="Cheque">Bank Cheque</option>
                                <option value="Other">Other Mode</option>
                            </select>
                        </div>

                        {{-- Reference / Voucher # --}}
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small text-dark">
                                Transfer / Voucher Ref # <span class="text-danger">*</span>
                            </label>
                            <input type="text" name="reference_no" class="form-control form-control-sm font-monospace" placeholder="e.g. TRX-94821, VCH-2026-001, Cheque #" required>
                        </div>

                        {{-- Purpose / Reason --}}
                        <div class="col-12">
                            <label class="form-label fw-semibold small text-dark">Disbursement Purpose &amp; Information</label>
                            <textarea name="notes" class="form-control form-control-sm" rows="2" placeholder="Describe the reason or urgent site requirements...">Site Petty Cash replacement fund for spot store materials and site consumables</textarea>
                        </div>

                        {{-- Attachment / Proof --}}
                        <div class="col-md-7">
                            <label class="form-label fw-semibold small text-dark">Bank Transfer Slip / Voucher Proof (Optional)</label>
                            <input type="file" name="attachment" class="form-control form-control-sm" accept=".pdf,.jpg,.jpeg,.png,.webp">
                        </div>

                        {{-- SMS Notification Checkbox --}}
                        <div class="col-md-5 d-flex align-items-center">
                            <div class="form-check form-switch mt-3">
                                <input class="form-check-input" type="checkbox" name="send_sms" value="1" id="modalSendSmsCheck" checked>
                                <label class="form-check-label small fw-semibold text-dark" for="modalSendSmsCheck">
                                    <i class="fa-solid fa-comment-sms text-success me-1"></i>Send SMS Alert to Keeper
                                </label>
                            </div>
                        </div>
                    </div>

                </div>
                <div class="modal-footer bg-light py-2">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success btn-sm fw-bold px-4" id="submitRepBtn">
                        <i class="fa-solid fa-paper-plane me-1"></i> Confirm &amp; Send Replacement
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- ── Modal: Ask for Replenishment (Send to Internal Audit) ─────────────────── --}}
<div class="modal fade" id="askReplenishmentModal" tabindex="-1" aria-labelledby="askReplenishmentModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <form action="{{ route('store-keeper.petty-cash-purchases.request-replacement') }}" method="POST" enctype="multipart/form-data" id="askReplenishmentForm">
                @csrf
                <div class="modal-header py-3 text-white" style="background: linear-gradient(135deg, #1e3a8a, #2563eb);">
                    <div>
                        <h5 class="modal-title fw-bold mb-0" id="askReplenishmentModalLabel">
                            <i class="fa-solid fa-hand-holding-dollar me-2"></i>Ask for Replenishment (Send to Audit)
                        </h5>
                        <small class="text-white-50" style="font-size:0.75rem;">
                            Route spot purchase vouchers directly to Internal Audit for clearance &amp; fund replenishment
                        </small>
                    </div>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">

                    {{-- Store & Account Summary Ribbon --}}
                    <div class="p-3 rounded-3 bg-light border mb-3 d-flex flex-wrap justify-content-between align-items-center gap-2">
                        <div>
                            <small class="text-muted text-uppercase fw-bold d-block" style="font-size:0.68rem;">Destination Store &amp; Site Fund</small>
                            <span class="badge bg-primary px-2 py-1 me-2 font-monospace" id="modalAskStoreBadge">
                                {{ $assignedStore->name ?? ($stores->first()->name ?? 'Site Store') }}
                            </span>
                            <strong class="text-dark small" id="modalAskAccountDisplay">
                                {{ $pettyCashAccount->name ?? 'Site Petty Cash' }} [{{ $pettyCashAccount->code ?? 'N/A' }}]
                            </strong>
                        </div>
                        <div>
                            <span class="badge bg-warning text-dark font-monospace px-2.5 py-1.5" id="modalAskBalanceDisplay">
                                Current Balance: ETB {{ number_format($pettyCashAccount->current_balance ?? 0, 2) }}
                            </span>
                        </div>
                    </div>

                    {{-- Active Pending / Under-Audit Alert --}}
                    @if($pendingReplenishment)
                    <div class="alert alert-info border-0 shadow-xs rounded-3 p-2.5 mb-3 d-flex align-items-center gap-2" role="alert">
                        <i class="fa-solid fa-clock-rotate-left fa-lg text-info"></i>
                        <small class="text-dark">
                            <strong>Active Request In Progress:</strong> Replenishment <strong>#{{ $pendingReplenishment->request_no }}</strong> (ETB {{ number_format($pendingReplenishment->requested_amount, 2) }}) is currently 
                            <span class="badge bg-primary text-uppercase">{{ str_replace('_', ' ', $pendingReplenishment->status) }}</span>. 
                            <a href="{{ route('finance.replenishments.index', ['tab' => 'under_audit']) }}" target="_blank" class="fw-bold text-decoration-underline ms-1">View in Audit Hub &rarr;</a>
                        </small>
                    </div>
                    @endif

                    {{-- Unreplenished Purchases List / Breakdown --}}
                    <div class="card border rounded-3 mb-3 bg-light bg-opacity-50">
                        <div class="card-header bg-white py-2 px-3 d-flex justify-content-between align-items-center border-bottom">
                            <span class="small fw-bold text-dark">
                                <i class="fa-solid fa-receipt text-primary me-1"></i> Spot Purchases to Replenish (<span id="modalUnreplCount">{{ $unreplenishedPurchases->count() }}</span>)
                            </span>
                            <strong class="text-danger font-monospace small" id="modalUnreplenishedTotalDisplay">
                                Total Spent: ETB {{ number_format($unreplenishedTotal, 2) }}
                            </strong>
                        </div>
                        <div class="card-body p-0">
                            @if($unreplenishedPurchases->count() > 0)
                            <div class="table-responsive" style="max-height: 150px; overflow-y: auto;">
                                <table class="table table-sm table-hover mb-0 align-middle" style="font-size: 0.78rem;">
                                    <thead class="table-light sticky-top">
                                        <tr>
                                            <th class="ps-3">Purchase #</th>
                                            <th>Date</th>
                                            <th>Supplier</th>
                                            <th>Receipt #</th>
                                            <th class="text-end pe-3">Amount (ETB)</th>
                                        </tr>
                                    </thead>
                                    <tbody id="modalUnreplenishedItemsList">
                                        @foreach($unreplenishedPurchases as $p)
                                        <tr>
                                            <td class="ps-3 font-monospace fw-bold text-primary">{{ $p->purchase_no }}</td>
                                            <td>{{ \Carbon\Carbon::parse($p->purchase_date)->format('M d, Y') }}</td>
                                            <td>{{ $p->supplier_name }}</td>
                                            <td class="font-monospace text-muted">{{ $p->receipt_no }}</td>
                                            <td class="text-end pe-3 font-monospace fw-bold text-dark">{{ number_format($p->total_amount, 2) }}</td>
                                        </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                            @else
                            <div class="p-3 text-center text-muted small" id="modalNoUnreplenishedNote">
                                <i class="fa-solid fa-circle-check text-success me-1"></i> All previous spot purchases have been audited and cleared. You can submit an advance replenishment request.
                            </div>
                            @endif
                        </div>
                    </div>

                    <input type="hidden" name="store_id" id="modalReqStoreId" value="{{ $assignedStore->id ?? ($stores->first()->id ?? '') }}">

                    <div class="row g-2 g-md-3 filter-form">
                        {{-- Requested Amount --}}
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small text-dark">
                                Requested Amount (ETB) <span class="text-danger">*</span>
                            </label>
                            <div class="input-group input-group-sm">
                                <span class="input-group-text fw-bold">ETB</span>
                                <input type="number" step="0.01" min="1" name="requested_amount" id="modalAskAmount" class="form-control font-monospace fw-bold fs-6" 
                                    value="{{ $unreplenishedTotal > 0 ? number_format($unreplenishedTotal, 2, '.', '') : '' }}" placeholder="0.00" required>
                            </div>
                            <div class="d-flex gap-1 mt-1.5 flex-wrap">
                                @if($unreplenishedTotal > 0)
                                <button type="button" class="btn btn-xs btn-outline-primary py-0 px-1.5 quick-ask-btn" data-amt="{{ number_format($unreplenishedTotal, 2, '.', '') }}">
                                    Exact Spent ({{ number_format($unreplenishedTotal, 2) }})
                                </button>
                                @endif
                                <button type="button" class="btn btn-xs btn-outline-secondary py-0 px-1.5 quick-ask-btn" data-amt="5000">+5,000</button>
                                <button type="button" class="btn btn-xs btn-outline-secondary py-0 px-1.5 quick-ask-btn" data-amt="10000">+10,000</button>
                                <button type="button" class="btn btn-xs btn-outline-secondary py-0 px-1.5 quick-ask-btn" data-amt="25000">+25,000</button>
                                <button type="button" class="btn btn-xs btn-outline-secondary py-0 px-1.5 quick-ask-btn" data-amt="50000">+50,000</button>
                            </div>
                        </div>

                        {{-- Urgency Level --}}
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small text-dark">Urgency Level <span class="text-danger">*</span></label>
                            <select name="urgency" class="form-select form-select-sm">
                                <option value="Normal">Normal Clearance</option>
                                <option value="Urgent" selected>Urgent (Site Procurement Required)</option>
                                <option value="Emergency">Emergency (Site Halt Risk)</option>
                            </select>
                            <small class="text-muted d-block mt-1" style="font-size:0.72rem;">Priority flag for Internal Audit inspection queue</small>
                        </div>

                        {{-- Purpose & Justification --}}
                        <div class="col-12">
                            <label class="form-label fw-semibold small text-dark">Reason / Notes for Internal Audit</label>
                            <textarea name="notes" class="form-control form-control-sm" rows="2" placeholder="Describe the reason or urgent site requirements...">Site Petty Cash replenishment for spot store material purchases and site consumables. Vouchers attached for audit clearance.</textarea>
                        </div>

                        {{-- Supporting Receipts / Summary PDF --}}
                        <div class="col-12">
                            <label class="form-label fw-semibold small text-dark">Supporting Receipts / Batch Bills (Optional)</label>
                            <input type="file" name="attachment" class="form-control form-control-sm" accept=".pdf,.jpg,.jpeg,.png,.webp">
                            <small class="text-muted" style="font-size:0.72rem;">Upload combined receipts PDF or invoice scans if needed.</small>
                        </div>
                    </div>

                    {{-- Company Standard Audit System Callout --}}
                    <div class="p-2.5 rounded-3 bg-primary bg-opacity-10 border border-primary border-opacity-25 mt-3 d-flex align-items-center gap-2 text-dark small">
                        <i class="fa-solid fa-scale-balanced fa-lg text-primary"></i>
                        <div>
                            <strong>Standard Audit Clearance Workflow:</strong> This request and all individual spot vouchers will be routed directly to the <strong>Internal Audit Team queue</strong>. Once audited and cleared, Finance will disburse the replacement funds to this Site Petty Cash account.
                        </div>
                    </div>

                </div>
                <div class="modal-footer bg-light py-2">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm fw-bold px-4" id="submitAskReplenishBtn">
                        <i class="fa-solid fa-paper-plane me-1"></i> Submit to Internal Audit
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    let rowIndex = 1;
    let availablePettyCash = parseFloat("{{ $pettyCashAccount->current_balance ?? 0 }}") || 0;
    const storesData = @json($storesData ?? []);

    const tbody = document.getElementById('itemsTbody');
    const addRowBtn = document.getElementById('addRowBtn');
    const grandTotalDisplay = document.getElementById('grandTotalDisplay');
    const totalItemsBadge = document.getElementById('totalItemsBadge');
    const alertDiv = document.getElementById('insufficientBalanceAlert');
    const storeSelect = document.getElementById('storeSelect');
    const zeroBalanceAlert = document.getElementById('zeroBalanceAlert');

    // Quick Amount Buttons in Replacement & Audit Modals
    document.querySelectorAll('.quick-amt-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const amt = this.getAttribute('data-amt');
            const amtInput = document.getElementById('modalRepAmount');
            if (amtInput) {
                amtInput.value = parseFloat(amt).toFixed(2);
            }
        });
    });

    document.querySelectorAll('.quick-ask-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const amt = this.getAttribute('data-amt');
            const amtInput = document.getElementById('modalAskAmount');
            if (amtInput) {
                amtInput.value = parseFloat(amt).toFixed(2);
            }
        });
    });

    // Synchronize Store and Site Petty Cash info
    function syncStoreInfo(stId) {
        if (!stId || !storesData[stId]) return;
        const acc = storesData[stId];

        // Main Form updates
        const pettyName = document.getElementById('pettyCashNameDisplay');
        const pettyCode = document.getElementById('pettyCashCodeDisplay');
        const pettyBadge = document.getElementById('pettyCashBalanceDisplay');
        const topBalance = document.getElementById('currentPettyCashDisplay');
        const accInput = document.getElementById('pettyCashAccountIdInput');

        if (pettyName) pettyName.textContent = acc.account_name;
        if (pettyCode) pettyCode.textContent = '[' + acc.account_code + ']';
        const formatted = 'ETB ' + acc.balance.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        if (pettyBadge) pettyBadge.textContent = formatted;
        if (topBalance) topBalance.textContent = formatted;
        if (accInput) accInput.value = acc.account_id;

        availablePettyCash = parseFloat(acc.balance) || 0;

        // Toggle zero balance alert
        if (zeroBalanceAlert) {
            if (availablePettyCash <= 0) {
                zeroBalanceAlert.classList.remove('d-none');
            } else {
                zeroBalanceAlert.classList.add('d-none');
            }
        }

        // Direct Disburse Modal updates
        const modalStoreId = document.getElementById('modalRepStoreId');
        const modalBadge = document.getElementById('modalStoreNameBadge');
        const modalAccDisplay = document.getElementById('modalSitePettyAccountDisplay');
        const modalBalDisplay = document.getElementById('modalCurrentBalanceDisplay');

        if (modalStoreId) modalStoreId.value = stId;
        if (modalBadge) modalBadge.textContent = acc.store_name;
        if (modalAccDisplay) modalAccDisplay.textContent = acc.account_name + ' [' + acc.account_code + ']';
        if (modalBalDisplay) modalBalDisplay.textContent = 'Current Balance: ' + formatted;

        // Ask Replenishment (Send to Audit) Modal updates
        const askStoreId = document.getElementById('modalReqStoreId');
        const askBadge = document.getElementById('modalAskStoreBadge');
        const askAccDisplay = document.getElementById('modalAskAccountDisplay');
        const askBalDisplay = document.getElementById('modalAskBalanceDisplay');
        const askAmtInput = document.getElementById('modalAskAmount');
        const unreplTotalDisplay = document.getElementById('modalUnreplenishedTotalDisplay');

        if (askStoreId) askStoreId.value = stId;
        if (askBadge) askBadge.textContent = acc.store_name;
        if (askAccDisplay) askAccDisplay.textContent = acc.account_name + ' [' + acc.account_code + ']';
        if (askBalDisplay) askBalDisplay.textContent = 'Current Balance: ' + formatted;

        if (acc.unreplenished_total !== undefined) {
            if (unreplTotalDisplay) {
                unreplTotalDisplay.textContent = 'Total Spent: ETB ' + acc.unreplenished_total.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            }
            if (askAmtInput && acc.unreplenished_total > 0) {
                askAmtInput.value = acc.unreplenished_total.toFixed(2);
            }
        }

        // Auto-select keeper if matched
        const recipientSelect = document.getElementById('modalRecipientSelect');
        if (recipientSelect && acc.keeper_id) {
            recipientSelect.value = acc.keeper_id;
        }

        recalculate();
    }

    // Dynamic Site Petty Cash Switcher when store changes
    if (storeSelect) {
        storeSelect.addEventListener('change', function() {
            syncStoreInfo(this.value);
        });
    }

    // AJAX Handler for Ask Replenishment (Send to Audit) Form
    const askRepForm = document.getElementById('askReplenishmentForm');
    if (askRepForm) {
        askRepForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const submitBtn = document.getElementById('submitAskReplenishBtn');
            const originalBtnHtml = submitBtn.innerHTML;
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-1"></i> Submitting to Internal Audit...';

            const formData = new FormData(this);

            fetch(this.action, {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                }
            })
            .then(res => res.json())
            .then(data => {
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalBtnHtml;

                if (data.success) {
                    // Hide modal
                    const modalEl = document.getElementById('askReplenishmentModal');
                    const modalInst = bootstrap.Modal.getInstance(modalEl);
                    if (modalInst) modalInst.hide();

                    askRepForm.reset();

                    // Show success banner at top of form
                    const banner = document.createElement('div');
                    banner.className = 'alert alert-success border-0 shadow-sm rounded-3 p-3 mb-4 d-flex flex-wrap justify-content-between align-items-center gap-3';
                    banner.innerHTML = `
                        <div class="d-flex align-items-center gap-3">
                            <i class="fa-solid fa-circle-check fa-2x text-success"></i>
                            <div>
                                <strong class="d-block text-dark fs-6">Replenishment Request #${data.request_no} Submitted Directly to Internal Audit!</strong>
                                <span class="small text-muted">${data.message} (${data.vouchers_count || 0} purchase vouchers bundled).</span>
                            </div>
                        </div>
                        <div class="d-flex gap-2">
                            <a href="${data.audit_url}" class="btn btn-sm btn-primary fw-bold px-3">
                                <i class="fa-solid fa-scale-balanced me-1"></i> Track in Audit &amp; Replenishments Hub
                            </a>
                        </div>
                    `;
                    const formEl = document.getElementById('pettyCashPurchaseForm');
                    formEl.parentNode.insertBefore(banner, formEl);
                    window.scrollTo({ top: 0, behavior: 'smooth' });
                } else {
                    alert(data.message || 'Error submitting replenishment request.');
                }
            })
            .catch(err => {
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalBtnHtml;
                console.error(err);
                askRepForm.submit();
            });
        });
    }

    // AJAX Handler for Send Replacement Form
    const sendRepForm = document.getElementById('sendReplacementForm');
    if (sendRepForm) {
        sendRepForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const submitBtn = document.getElementById('submitRepBtn');
            const originalBtnHtml = submitBtn.innerHTML;
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-1"></i> Disbursing Money...';

            const formData = new FormData(this);

            fetch(this.action, {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                }
            })
            .then(res => res.json())
            .then(data => {
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalBtnHtml;

                if (data.success) {
                    // Update state
                    const stId = formData.get('store_id');
                    if (storesData[stId]) {
                        storesData[stId].balance = data.new_balance;
                    }
                    availablePettyCash = parseFloat(data.new_balance) || 0;

                    // Update UI balances
                    const formatted = data.formatted_balance || ('ETB ' + availablePettyCash.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }));
                    const topBal = document.getElementById('currentPettyCashDisplay');
                    const badgeBal = document.getElementById('pettyCashBalanceDisplay');
                    if (topBal) topBal.textContent = formatted;
                    if (badgeBal) badgeBal.textContent = formatted;

                    if (zeroBalanceAlert) {
                        zeroBalanceAlert.classList.add('d-none');
                    }

                    // Hide modal
                    const modalEl = document.getElementById('sendReplacementModal');
                    const modalInst = bootstrap.Modal.getInstance(modalEl);
                    if (modalInst) modalInst.hide();

                    sendRepForm.reset();

                    // Show success banner at top of form
                    const banner = document.createElement('div');
                    banner.className = 'alert alert-success border-0 shadow-sm rounded-3 p-3 mb-4 d-flex justify-content-between align-items-center';
                    banner.innerHTML = `
                        <div class="d-flex align-items-center gap-2">
                            <i class="fa-solid fa-circle-check fa-lg text-success"></i>
                            <div>
                                <strong>Replacement Money Sent Successfully!</strong>
                                <span class="d-block small text-dark">${data.message} Voucher #: <strong>${data.voucher_no}</strong></span>
                            </div>
                        </div>
                        <a href="${data.voucher_url}" target="_blank" class="btn btn-sm btn-outline-success fw-bold">
                            <i class="fa-solid fa-print me-1"></i> View / Print Voucher
                        </a>
                    `;
                    const formEl = document.getElementById('pettyCashPurchaseForm');
                    formEl.parentNode.insertBefore(banner, formEl);

                    recalculate();
                } else {
                    alert(data.message || 'Error sending replacement money.');
                }
            })
            .catch(err => {
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalBtnHtml;
                console.error(err);
                // Fallback to regular form submission if AJAX fails
                sendRepForm.submit();
            });
        });
    }

    // Product template options from existing select
    const productOptionsHtml = document.querySelector('.product-select') ? document.querySelector('.product-select').innerHTML : '';

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
    if (addRowBtn) {
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
    }

    // Form confirmation
    const purchaseForm = document.getElementById('pettyCashPurchaseForm');
    if (purchaseForm) {
        purchaseForm.addEventListener('submit', function(e) {
            const rows = document.querySelectorAll('.item-row');
            if (rows.length === 0) {
                e.preventDefault();
                alert('Please add at least one material item.');
                return false;
            }
            return true;
        });
    }
});
</script>
@endpush
@endsection

