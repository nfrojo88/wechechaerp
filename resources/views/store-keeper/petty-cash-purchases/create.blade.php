@extends('layouts.app')
@section('title', 'Complete Material Purchase (Petty Cash) - Store Keeper')

@section('content')
@push('styles')
@include('layouts._store_mobile')
@endpush

<div class="container-fluid">

    {{-- ── Top Navigation & Header ────────────────────────────────────────────── --}}
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
        <div class="d-flex align-items-center gap-3">
            <a href="{{ route('store-keeper.petty-cash-purchases.index') }}" class="btn btn-outline-secondary btn-sm shadow-sm" title="Back to Petty Cash Purchases">
                <i class="fa-solid fa-arrow-left"></i>
            </a>
            <div>
                <div class="d-flex align-items-center gap-2 mb-1">
                    <h3 class="fw-bold text-dark mb-0">
                        <i class="fa-solid fa-cart-shopping text-success me-2"></i>Complete Material Purchase
                    </h3>
                    <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 px-2 py-1">
                        <i class="fa-solid fa-lock me-1"></i>Planning Approved
                    </span>
                </div>
                <p class="text-muted small mb-0">
                    Record spot purchase details for approved material from Request 
                    <strong class="font-monospace text-primary">#{{ $mr->reference_number }}</strong>.
                </p>
            </div>
        </div>
        <div class="d-flex align-items-center gap-2">
            <div class="card border-0 shadow-sm bg-white px-3 py-2 rounded-3 border-start border-4 border-warning">
                <div class="d-flex align-items-center gap-2">
                    <i class="fa-solid fa-wallet text-warning fa-lg"></i>
                    <div>
                        <small class="text-muted text-uppercase fw-bold d-block" style="font-size:0.68rem;">Available Site Petty Cash</small>
                        <strong class="text-dark font-monospace" id="currentPettyCashDisplay">
                            ETB {{ number_format($pettyCashAccount->current_balance ?? 0, 2) }}
                        </strong>
                    </div>
                </div>
            </div>
            <button type="button" class="btn btn-primary btn-sm fw-bold shadow-sm px-3 py-2 text-white" data-bs-toggle="modal" data-bs-target="#askReplenishmentModal" title="Ask for replacement money & submit directly to Internal Audit">
                <i class="fa-solid fa-hand-holding-dollar me-1"></i> Ask for Replenishment (Audit)
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

    @if(session('slip_gap_info'))
    @php $gap = session('slip_gap_info'); @endphp
    <div class="alert alert-warning border-start border-4 border-warning shadow-sm rounded-3 mb-4 p-3">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div>
                <h6 class="fw-bold mb-1 text-dark"><i class="fa-solid fa-triangle-exclamation text-warning me-2"></i>Missing Slips Detected</h6>
                <div class="small text-dark">
                    Slip <strong>#{{ $gap['given_number'] }}</strong> cannot be recorded yet because earlier sequence numbers are unrecorded: 
                    <strong class="font-monospace text-danger">{{ implode(', ', array_map(fn($f) => '#' . $f, $gap['missing_formatted'])) }}</strong>.
                </div>
            </div>
            <button type="button" class="btn btn-warning btn-sm fw-bold shadow-xs text-dark" data-bs-toggle="modal" data-bs-target="#pettyVoidMissingModal">
                <i class="fa-solid fa-ban me-1"></i>Void Missing Slips &amp; Continue
            </button>
        </div>
    </div>
    @endif

    {{-- ── Approved Material Specification Card (STRICTLY READ-ONLY) ───────────── --}}
    <div class="card border-0 shadow-sm rounded-3 mb-4 bg-white border-start border-4 border-success">
        <div class="card-header bg-success bg-opacity-10 py-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div class="d-flex align-items-center gap-2">
                <span class="badge bg-success text-white px-2.5 py-1.5"><i class="fa-solid fa-lock me-1"></i>READ-ONLY SPECIFICATION</span>
                <h6 class="mb-0 fw-bold text-success">
                    Planning Manager Approved Material Line
                </h6>
            </div>
            <span class="badge bg-light text-dark border">
                Decision: <strong class="text-success ms-1">Buy with Petty Cash</strong>
            </span>
        </div>
        <div class="card-body p-3 p-md-4">
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label text-muted text-uppercase fw-bold small" style="font-size:0.7rem;">Material / Item Name</label>
                    <div class="p-2.5 rounded bg-light border">
                        <strong class="text-dark fs-6 d-block">{{ $mrItem->product->name ?? 'Material Item' }}</strong>
                        <span class="badge bg-secondary font-monospace" style="font-size:0.75rem;">
                            {{ $mrItem->product->code ?? $mrItem->product->sku ?? 'CODE' }}
                        </span>
                        @if($mrItem->product->category)
                            <span class="badge bg-light text-muted border ms-1">{{ $mrItem->product->category }}</span>
                        @endif
                    </div>
                </div>

                <div class="col-md-2 col-6">
                    <label class="form-label text-muted text-uppercase fw-bold small" style="font-size:0.7rem;">Quantity</label>
                    <div class="p-2.5 rounded bg-light border text-center">
                        <span class="fs-5 fw-bold font-monospace text-dark">{{ (float)$mrItem->quantity }}</span>
                    </div>
                </div>

                <div class="col-md-2 col-6">
                    <label class="form-label text-muted text-uppercase fw-bold small" style="font-size:0.7rem;">Unit</label>
                    <div class="p-2.5 rounded bg-light border text-center">
                        <span class="fs-6 fw-bold text-dark">{{ $mrItem->product->unit_of_measure ?? $mrItem->product->unit ?? 'pcs' }}</span>
                    </div>
                </div>

                <div class="col-md-4">
                    <label class="form-label text-muted text-uppercase fw-bold small" style="font-size:0.7rem;">Request &amp; Project</label>
                    <div class="p-2.5 rounded bg-light border">
                        <div class="fw-bold font-monospace text-primary">#{{ $mr->reference_number }}</div>
                        <small class="text-muted d-block">
                            Project: <strong>{{ $mr->project->name ?? 'Site Project' }}</strong> &bull; Store: <strong>{{ $targetStore->name }}</strong>
                        </small>
                    </div>
                </div>

                <div class="col-md-6">
                    <label class="form-label text-muted text-uppercase fw-bold small" style="font-size:0.7rem;">Planning Decision Audit</label>
                    <div class="p-2 rounded bg-light border small">
                        <i class="fa-solid fa-user-check text-success me-1"></i>
                        Approved by: <strong>{{ $mr->planningApprover->name ?? 'Planning Manager' }}</strong>
                        on <strong>{{ optional($mr->planning_approved_at)->format('d M, Y (H:i)') ?? 'Approved' }}</strong>
                    </div>
                </div>

                <div class="col-md-6">
                    <label class="form-label text-muted text-uppercase fw-bold small" style="font-size:0.7rem;">Requester &amp; Notes</label>
                    <div class="p-2 rounded bg-light border small">
                        <i class="fa-solid fa-clipboard-question text-primary me-1"></i>
                        Requested by: <strong>{{ $mr->requestedBy->name ?? $mr->creator->name ?? 'Site Team' }}</strong>
                        @if($mrItem->notes)
                            &mdash; <em>"{{ $mrItem->notes }}"</em>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ── Main Form: Storekeeper Fill-Only Purchase Details ──────────────────── --}}
    <form action="{{ route('store-keeper.petty-cash-purchases.store') }}" method="POST" enctype="multipart/form-data" id="pettyCashPurchaseForm">
        @csrf
        <input type="hidden" name="material_request_item_id" value="{{ $mrItem->id }}">
        <input type="hidden" name="store_id" value="{{ $targetStore->id }}">
        <input type="hidden" name="chart_of_account_id" id="pettyCashAccountIdInput" value="{{ $pettyCashAccount->id ?? '' }}">

        <div class="row g-4 mb-4">

            {{-- Left Column: Expenditure & Vendor Receipt --}}
            <div class="col-lg-6">
                <div class="card border-0 shadow-sm rounded-3 bg-white h-100">
                    <div class="card-header bg-white border-bottom py-3">
                        <h6 class="mb-0 fw-bold text-dark">
                            <i class="fa-solid fa-receipt text-primary me-2"></i>Purchase Money &amp; Vendor Receipt
                        </h6>
                    </div>
                    <div class="card-body p-3 p-md-4">

                        {{-- Money Spent (ETB) --}}
                        <div class="mb-3">
                            <label class="form-label fw-semibold text-dark small d-flex justify-content-between align-items-center">
                                <span>Amount Spent from Petty Cash (ETB) <span class="text-danger">*</span></span>
                                <span class="badge bg-warning text-dark font-monospace" id="formPettyCashBalanceBadge">
                                    Available: ETB {{ number_format($pettyCashAccount->current_balance ?? 0, 2) }}
                                </span>
                            </label>
                            <div class="input-group input-group-lg">
                                <span class="input-group-text fw-bold text-success">ETB</span>
                                <input type="number" step="0.01" min="0.01" name="total_amount" id="pettyTotalAmount" 
                                       class="form-control font-monospace fw-bold fs-4 text-dark @error('total_amount') is-invalid @enderror" 
                                       placeholder="0.00" value="{{ old('total_amount') }}" required autofocus>
                            </div>
                            <small class="text-muted d-block mt-1" style="font-size:0.75rem;">
                                Enter the exact money spent for this purchase. Amount cannot exceed available site petty cash balance.
                            </small>
                            <div class="alert alert-danger py-2 px-3 small border-0 mt-2 d-none" id="insufficientBalanceAlert">
                                <i class="fa-solid fa-triangle-exclamation me-1"></i>
                                <strong>Warning:</strong> The entered amount exceeds your available site petty cash balance (ETB {{ number_format($pettyCashAccount->current_balance ?? 0, 2) }}).
                            </div>
                            @error('total_amount')
                                <div class="text-danger small mt-1"><i class="fa-solid fa-circle-exclamation me-1"></i>{{ $message }}</div>
                            @enderror
                        </div>

                        {{-- Supplier / Merchant Name --}}
                        <div class="mb-3">
                            <label class="form-label fw-semibold text-dark small">
                                Supplier / Merchant / Shop Name <span class="text-danger">*</span>
                            </label>
                            <input type="text" name="supplier_name" id="pettySupplierName" 
                                   class="form-control form-control-sm @error('supplier_name') is-invalid @enderror" 
                                   placeholder="e.g. Merkato Hardware, TotalEnergies, Local Shop" 
                                   value="{{ old('supplier_name') }}" required>
                            @error('supplier_name')
                                <div class="text-danger small mt-1"><i class="fa-solid fa-circle-exclamation me-1"></i>{{ $message }}</div>
                            @enderror
                        </div>

                        {{-- Cash Receipt / FS Number --}}
                        <div class="mb-3">
                            <label class="form-label fw-semibold text-dark small">
                                Receipt / FS Number (Serial Number) <span class="text-danger">*</span>
                            </label>
                            <input type="text" name="receipt_no" id="pettyReceiptNo" 
                                   class="form-control form-control-sm font-monospace fw-bold @error('receipt_no') is-invalid @enderror" 
                                   placeholder="e.g. CR-89412, FS-0042, INV-1092" 
                                   value="{{ old('receipt_no') }}" required>
                            <small class="text-muted d-block mt-1" style="font-size:0.73rem;">
                                Official serial number printed on vendor's paper cash receipt. Must be unique.
                            </small>
                            @error('receipt_no')
                                <div class="text-danger small mt-1"><i class="fa-solid fa-circle-exclamation me-1"></i>{{ $message }}</div>
                            @enderror
                        </div>

                        {{-- Supplier TIN & FS Number (Optional / Fiscal) --}}
                        <div class="row g-2 mb-3">
                            <div class="col-6">
                                <label class="form-label fw-semibold text-dark small">
                                    Supplier TIN (Optional)
                                </label>
                                <input type="text" name="supplier_tin" id="pettySupplierTin" maxlength="10" 
                                       class="form-control form-control-sm font-monospace" 
                                       placeholder="10-digit TIN" value="{{ old('supplier_tin') }}">
                            </div>
                            <div class="col-6">
                                <label class="form-label fw-semibold text-dark small">
                                    FS Number (Optional)
                                </label>
                                <input type="text" name="fs_no" id="pettyFsNo" maxlength="8" 
                                       class="form-control form-control-sm font-monospace" 
                                       placeholder="8-digit FS" value="{{ old('fs_no') }}">
                            </div>
                        </div>

                        {{-- Cash Sales Receipt Photo Upload --}}
                        <div class="mb-0">
                            <label class="form-label fw-semibold text-dark small">
                                Upload Photo of Cash Sales Receipt <span class="text-danger">*</span>
                            </label>
                            <input type="file" name="attachment" id="pettyAttachmentInput" 
                                   class="form-control form-control-sm @error('attachment') is-invalid @enderror" 
                                   accept="image/*,.pdf" required>
                            <small class="text-muted d-block mt-1" style="font-size:0.73rem;">
                                Clear photo or PDF of the stamped physical cash sales receipt.
                            </small>
                            @error('attachment')
                                <div class="text-danger small mt-1"><i class="fa-solid fa-circle-exclamation me-1"></i>{{ $message }}</div>
                            @enderror
                        </div>

                        {{-- Dynamic OCR Feedback & Duplicate Alert Area --}}
                        <div id="pettyOcrFeedbackArea" class="mt-2"></div>

                    </div>
                </div>
            </div>

            {{-- Right Column: Store Intake (Model 19 GRN), Date & Notes --}}
            <div class="col-lg-6">
                <div class="card border-0 shadow-sm rounded-3 bg-white h-100 d-flex flex-column">
                    <div class="card-header bg-white border-bottom py-3">
                        <h6 class="mb-0 fw-bold text-dark">
                            <i class="fa-solid fa-warehouse text-success me-2"></i>Goods Intake &amp; Model 19 (GRN)
                        </h6>
                    </div>
                    <div class="card-body p-3 p-md-4 flex-grow-1">

                        {{-- Store Location & Petty Cash Account Information --}}
                        <div class="p-3 rounded-3 bg-light border mb-3">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <div>
                                    <small class="text-muted text-uppercase fw-bold d-block" style="font-size:0.68rem;">Receiving Store Warehouse</small>
                                    <strong class="text-dark small"><i class="fa-solid fa-warehouse text-primary me-1"></i>{{ $targetStore->name }}</strong>
                                    <span class="badge bg-secondary font-monospace ms-1">{{ $targetStore->code ?? 'STORE' }}</span>
                                </div>
                                <span class="badge bg-success small"><i class="fa-solid fa-check me-1"></i>Stock Credited</span>
                            </div>
                            <div class="border-top pt-2 mt-2 d-flex justify-content-between align-items-center">
                                <div>
                                    <small class="text-muted text-uppercase fw-bold d-block" style="font-size:0.68rem;">Paying Petty Cash Fund</small>
                                    <span class="text-dark small font-monospace">[{{ $pettyCashAccount->code ?? 'FUND' }}] {{ $pettyCashAccount->name ?? 'Site Petty Cash' }}</span>
                                </div>
                                <span class="badge bg-warning text-dark font-monospace">
                                    ETB {{ number_format($pettyCashAccount->current_balance ?? 0, 2) }}
                                </span>
                            </div>
                        </div>

                        {{-- Purchase Date --}}
                        <div class="mb-3">
                            <label class="form-label fw-semibold text-dark small">
                                Purchase Date <span class="text-danger">*</span>
                            </label>
                            <input type="date" name="purchase_date" id="pettyPurchaseDate" 
                                   class="form-control form-control-sm @error('purchase_date') is-invalid @enderror" 
                                   value="{{ old('purchase_date', date('Y-m-d')) }}" required>
                            @error('purchase_date')
                                <div class="text-danger small mt-1"><i class="fa-solid fa-circle-exclamation me-1"></i>{{ $message }}</div>
                            @enderror
                        </div>

                        {{-- Store GRN Slip Number (Model 19) --}}
                        <div class="mb-3">
                            <label class="form-label fw-semibold text-dark small d-flex justify-content-between align-items-center">
                                <span>Model 19 (GRN) Receiving Slip #</span>
                                <div class="d-flex align-items-center gap-1">
                                    @if(!empty($nextReceivingSlip) && $nextReceivingSlip['has_sequence'])
                                        <span id="pettyNextSlipBadge" class="badge text-white font-monospace" style="background-color: #0d9488;">
                                            Next: #{{ $nextReceivingSlip['formatted_slip'] }}
                                        </span>
                                    @endif
                                    <button type="button" class="btn btn-xs btn-outline-danger py-0 px-2 fw-semibold" 
                                            data-bs-toggle="modal" data-bs-target="#voidSlipModal"
                                            data-target-input="#pettySlipNo"
                                            data-store-id="{{ $targetStore->id }}"
                                            data-slip-type="receive"
                                            title="Void spoiled paper slip">
                                        <i class="fas fa-ban me-1 text-danger"></i>Void Slip #
                                    </button>
                                </div>
                            </label>
                            <div class="input-group input-group-sm">
                                <span class="input-group-text bg-light"><i class="fas fa-hashtag text-muted"></i></span>
                                <input type="text" name="slip_no" id="pettySlipNo" 
                                       class="form-control form-control-sm font-monospace fw-bold @error('slip_no') is-invalid @enderror" 
                                       placeholder="{{ !empty($nextReceivingSlip) && $nextReceivingSlip['has_sequence'] ? 'Auto: #' . $nextReceivingSlip['formatted_slip'] : 'Auto from store sequence' }}" 
                                       value="{{ old('slip_no') }}">
                                <button type="button" class="btn btn-outline-danger btn-sm" 
                                        data-bs-toggle="modal" data-bs-target="#voidSlipModal"
                                        data-target-input="#pettySlipNo"
                                        data-store-id="{{ $targetStore->id }}"
                                        data-slip-type="receive"
                                        title="Void spoiled slip and use next">
                                    <i class="fas fa-ban me-1"></i>Void
                                </button>
                            </div>
                            <small class="text-muted d-block mt-1" style="font-size:0.73rem;">
                                Leave blank to automatically consume the next Model 19 receiving slip (#{{ $nextReceivingSlip['formatted_slip'] ?? '...' }}).
                            </small>
                            @error('slip_no')
                                <div class="text-danger small mt-1"><i class="fas fa-exclamation-circle me-1"></i>{{ $message }}</div>
                            @enderror
                        </div>

                        {{-- Physical Store Slip (Non-VAT) Option --}}
                        <div class="mb-3 p-2 bg-light border rounded">
                            <div class="form-check form-switch mb-0">
                                <input class="form-check-input" type="checkbox" name="is_physical_store_only" id="isPhysicalStoreOnlyPetty" value="1" {{ old('is_physical_store_only') ? 'checked' : '' }}>
                                <label class="form-check-label small fw-bold text-dark" for="isPhysicalStoreOnlyPetty">
                                    <i class="fa-solid fa-warehouse text-warning me-1"></i>Physical Slip (Store Only — Excluded from VAT)
                                </label>
                            </div>
                            <small class="text-muted d-block mt-1 ps-4" style="font-size:0.73rem;">
                                Internal physical slip for site store inventory intake only. Excluded from tax declarations.
                            </small>
                        </div>

                        {{-- Remarks / Notes --}}
                        <div class="mb-0">
                            <label class="form-label fw-semibold text-dark small">Remarks / Storekeeper Notes</label>
                            <textarea name="notes" id="pettyNotes" rows="2" class="form-control form-control-sm" placeholder="Optional notes regarding purchase or merchant...">{{ old('notes') }}</textarea>
                        </div>

                    </div>

                    {{-- Form Footer --}}
                    <div class="card-footer bg-light border-top p-3 d-flex justify-content-between align-items-center">
                        <a href="{{ route('store-keeper.petty-cash-purchases.index') }}" class="btn btn-outline-secondary btn-sm">
                            Cancel
                        </a>
                        <button type="submit" class="btn btn-success btn-sm px-4 fw-bold shadow-sm" id="submitPurchaseBtn">
                            <i class="fa-solid fa-circle-check me-1"></i> Confirm &amp; Complete Purchase
                        </button>
                    </div>

                </div>
            </div>

        </div>
    </form>

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
                            <span class="badge bg-primary px-2 py-1 me-2 font-monospace">
                                {{ $targetStore->name }}
                            </span>
                            <strong class="text-dark small">
                                {{ $pettyCashAccount->name ?? 'Site Petty Cash' }} [{{ $pettyCashAccount->code ?? 'N/A' }}]
                            </strong>
                        </div>
                        <div>
                            <span class="badge bg-warning text-dark font-monospace px-2.5 py-1.5">
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
                                <i class="fa-solid fa-receipt text-primary me-1"></i> Spot Purchases to Replenish ({{ $unreplenishedPurchases->count() }})
                            </span>
                            <strong class="text-danger font-monospace small">
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
                                    <tbody>
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
                            <div class="p-3 text-center text-muted small">
                                <i class="fa-solid fa-circle-check text-success me-1"></i>All previous purchases have been audited &amp; replenished.
                            </div>
                            @endif
                        </div>
                    </div>

                    {{-- Form Fields --}}
                    <input type="hidden" name="store_id" value="{{ $targetStore->id }}">

                    <div class="row g-3">
                        <div class="col-md-7">
                            <label class="form-label fw-semibold small text-dark">
                                Requested Replenishment Amount (ETB) <span class="text-danger">*</span>
                            </label>
                            <div class="input-group input-group-sm">
                                <span class="input-group-text fw-bold">ETB</span>
                                <input type="number" step="0.01" min="1" name="requested_amount" class="form-control font-monospace fw-bold fs-6" 
                                       value="{{ $unreplenishedTotal > 0 ? number_format($unreplenishedTotal, 2, '.', '') : '' }}" 
                                       placeholder="0.00" required>
                            </div>
                        </div>

                        <div class="col-md-5">
                            <label class="form-label fw-semibold small text-dark">Urgency Level</label>
                            <select name="urgency" class="form-select form-select-sm">
                                <option value="Normal">Normal</option>
                                <option value="Urgent" selected>Urgent (Low Site Fund)</option>
                                <option value="Critical">Critical (Zero Fund)</option>
                            </select>
                        </div>

                        <div class="col-12">
                            <label class="form-label fw-semibold small text-dark">Notes / Explanation for Internal Audit</label>
                            <textarea name="notes" class="form-control form-control-sm" rows="2" placeholder="Explain the site situation or urgency...">Replenishment request for verified spot material purchases on site</textarea>
                        </div>

                        <div class="col-12">
                            <label class="form-label fw-semibold small text-dark">Supporting Audit Summary / Document (Optional)</label>
                            <input type="file" name="attachment" class="form-control form-control-sm" accept=".pdf,.jpg,.jpeg,.png,.webp">
                        </div>
                    </div>

                </div>
                <div class="modal-footer bg-light py-2">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm fw-bold px-4">
                        <i class="fa-solid fa-paper-plane me-1"></i> Submit to Internal Audit
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- ── Modal: Send Replacement Money to Store Keeper ─────────────────────── --}}
@canany(['admin', 'global_admin', 'finance_head', 'finance_officer', 'store_manager'])
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

                    <input type="hidden" name="store_id" value="{{ $targetStore->id }}">

                    <div class="row g-2 g-md-3 filter-form">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small text-dark">
                                Recipient Store Keeper <span class="text-danger">*</span>
                            </label>
                            <select name="recipient_id" class="form-select form-select-sm" required>
                                @foreach($storeKeepers as $k)
                                    @php
                                        $kPhone = $k->phone ?? $k->employee->phone ?? $k->employee->mobile_phone ?? '';
                                    @endphp
                                    <option value="{{ $k->id }}" {{ (optional($assignedStore)->manager_id == $k->id || Auth::id() == $k->id) ? 'selected' : '' }}>
                                        {{ $k->name }} ({{ $k->email }}) {{ $kPhone ? '— ' . $kPhone : '' }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold small text-dark">
                                Source Funding Account (Bank / Cash) <span class="text-danger">*</span>
                            </label>
                            <select name="source_coa_id" class="form-select form-select-sm" required>
                                <option value="">-- Choose Paying Bank / Cash Account --</option>
                                @foreach($sourceAccounts as $sa)
                                    <option value="{{ $sa->id }}" {{ $loop->first ? 'selected' : '' }}>
                                        [{{ $sa->code }}] {{ $sa->name }} (ETB {{ number_format($sa->current_balance, 2) }})
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold small text-dark">
                                Replacement Amount (ETB) <span class="text-danger">*</span>
                            </label>
                            <div class="input-group input-group-sm">
                                <span class="input-group-text fw-bold">ETB</span>
                                <input type="number" step="0.01" min="1" name="amount" id="modalRepAmount" class="form-control font-monospace fw-bold fs-6" placeholder="0.00" required>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold small text-dark">
                                Transfer / Disbursement Date <span class="text-danger">*</span>
                            </label>
                            <input type="date" name="transfer_date" class="form-control form-control-sm" value="{{ date('Y-m-d') }}" required>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold small text-dark">
                                Payment Method <span class="text-danger">*</span>
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

                        <div class="col-md-6">
                            <label class="form-label fw-semibold small text-dark">
                                Transfer / Voucher Ref # <span class="text-danger">*</span>
                            </label>
                            <input type="text" name="reference_no" class="form-control form-control-sm font-monospace" placeholder="e.g. TRX-94821, VCH-001" required>
                        </div>

                        <div class="col-12">
                            <label class="form-label fw-semibold small text-dark">Disbursement Purpose</label>
                            <textarea name="notes" class="form-control form-control-sm" rows="2">Site Petty Cash replacement fund for spot store materials</textarea>
                        </div>

                        <div class="col-12">
                            <label class="form-label fw-semibold small text-dark">Attachment (Optional)</label>
                            <input type="file" name="attachment" class="form-control form-control-sm" accept=".pdf,.jpg,.jpeg,.png,.webp">
                        </div>
                    </div>

                </div>
                <div class="modal-footer bg-light py-2">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success btn-sm fw-bold px-4">
                        <i class="fa-solid fa-paper-plane me-1"></i> Confirm &amp; Send Replacement
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endcanany

@push('scripts')
<script src="{{ asset('js/receipt-ocr-scanner.js') }}"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const availablePettyCash = parseFloat("{{ $pettyCashAccount->current_balance ?? 0 }}") || 0;
    const amountInput = document.getElementById('pettyTotalAmount');
    const alertDiv = document.getElementById('insufficientBalanceAlert');
    const submitBtn = document.getElementById('submitPurchaseBtn');

    function validateAmount() {
        if (!amountInput) return;
        const entered = parseFloat(amountInput.value) || 0;
        if (entered > availablePettyCash && availablePettyCash > 0) {
            alertDiv.classList.remove('d-none');
        } else {
            alertDiv.classList.add('d-none');
        }
    }

    if (amountInput) {
        amountInput.addEventListener('input', validateAmount);
    }

    // Initialize Receipt OCR Scanner if available
    if (window.ReceiptOcrScanner) {
        ReceiptOcrScanner.init({
            fileInput: '#pettyAttachmentInput',
            form: '#pettyCashPurchaseForm',
            submitBtn: '#submitPurchaseBtn',
            statusContainer: '#pettyOcrFeedbackArea',
            fields: {
                vendor_name: '#pettySupplierName',
                supplier_tin: '#pettySupplierTin',
                fs_no: '#pettyFsNo',
                receipt_date: '#pettyPurchaseDate',
                notes: '#pettyNotes'
            },
            onScanComplete: function(scanData) {
                const rNo = document.getElementById('pettyReceiptNo');
                if (rNo && !rNo.value && scanData.data && scanData.data.fs_no) {
                    rNo.value = scanData.data.fs_no;
                }
                if (amountInput && !amountInput.value && scanData.data && scanData.data.total_amount) {
                    amountInput.value = parseFloat(scanData.data.total_amount).toFixed(2);
                    validateAmount();
                }
            }
        });
    }
});
</script>
@endpush

@if(session('slip_gap_info'))
@php $gap = session('slip_gap_info'); @endphp
<div class="modal fade" id="pettyVoidMissingModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-warning text-dark py-3">
                <h6 class="modal-title fw-bold">
                    <i class="fa-solid fa-ban me-2"></i>Void Missing Receiving Slips
                </h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="{{ route('api.slip-sequences.void-missing') }}" method="POST" id="pettyVoidMissingForm">
                @csrf
                <input type="hidden" name="store_id" value="{{ $gap['store_id'] }}">
                <input type="hidden" name="slip_type" value="{{ $gap['slip_type'] }}">
                <input type="hidden" name="target_slip_no" value="{{ $gap['given_number'] }}">

                <div class="modal-body p-4">
                    <div class="alert alert-warning py-2 px-3 small border-0 mb-3">
                        <i class="fa-solid fa-triangle-exclamation me-1"></i>
                        Missing slip numbers marked as <strong>VOID</strong> in sequential book:
                    </div>
                    
                    <div class="p-3 bg-light rounded border text-center font-monospace fw-bold text-danger mb-3" style="letter-spacing: 1px;">
                        {{ implode(', ', array_map(fn($f) => '#' . $f, $gap['missing_formatted'])) }}
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold small text-dark">Reason for Voiding Slips <span class="text-danger">*</span></label>
                        <textarea name="reason" class="form-control form-control-sm" rows="3" required placeholder="e.g. Spoiled physical leaves..."></textarea>
                    </div>
                </div>
                <div class="modal-footer bg-light py-2">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger btn-sm fw-bold">
                        <i class="fa-solid fa-ban me-1"></i>Confirm Void &amp; Proceed
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif

@include('partials._void_slip_modal')

@endsection
