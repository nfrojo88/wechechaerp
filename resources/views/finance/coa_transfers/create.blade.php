@extends('layouts.app')

@section('title', 'New COA Fund Transfer — Finance Head')

@section('content')
<div class="container-fluid">
    {{-- Header --}}
    <div class="d-flex align-items-center justify-content-between mb-4">
        <div class="d-flex align-items-center">
            <a href="{{ route('coa-transfers.index') }}" class="btn btn-sm btn-outline-secondary me-3">
                <i class="fa-solid fa-arrow-left"></i>
            </a>
            <div>
                <h1 class="h3 mb-0"><i class="fa-solid fa-money-bill-transfer text-primary me-2"></i>Execute COA Money Transfer</h1>
                <small class="text-muted">Transfer funds from one Chart of Account to another with immediate ledger posting</small>
            </div>
        </div>
        <div>
            <a href="{{ route('coa.index') }}" class="btn btn-sm btn-outline-primary">
                <i class="fa-solid fa-sitemap me-1"></i>View Chart of Accounts
            </a>
        </div>
    </div>

    @if($errors->any())
    <div class="alert alert-danger alert-dismissible fade show shadow-sm mb-4" role="alert">
        <h6 class="alert-heading fw-bold mb-2"><i class="fa-solid fa-triangle-exclamation me-2"></i>Please fix the following errors:</h6>
        <ul class="mb-0 small">
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    @endif

    <form method="POST" action="{{ route('coa-transfers.store') }}" enctype="multipart/form-data" id="transferForm">
        @csrf

        <div class="row g-4">
            {{-- Left Column: Transfer Form --}}
            <div class="col-lg-8">
                {{-- Account Selection Card --}}
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-light py-3">
                        <h5 class="mb-0 fw-bold text-dark"><i class="fa-solid fa-arrows-split-up-and-left text-primary me-2"></i>Source & Destination Accounts</h5>
                    </div>
                    <div class="card-body p-4">
                        <div class="row g-3">
                            {{-- Source Account (From) --}}
                            <div class="col-md-6">
                                <label class="form-label fw-bold small text-uppercase text-danger">
                                    <i class="fa-solid fa-arrow-up-from-bracket me-1"></i>From Source Account (Credit) <span class="text-danger">*</span>
                                </label>
                                <select name="from_coa_id" id="fromCoaSelect" class="form-select @error('from_coa_id') is-invalid @enderror" required onchange="updateAccountPreviews()">
                                    <option value="">-- Select Source Account (Where funds leave) --</option>
                                    @foreach($accounts as $acc)
                                        <option value="{{ $acc->id }}" 
                                                data-code="{{ $acc->code }}"
                                                data-name="{{ $acc->name }}"
                                                data-type="{{ ucfirst($acc->type) }}"
                                                data-balance="{{ (float)$acc->current_balance }}"
                                                data-manager="{{ $acc->manager->name ?? 'Unassigned' }}"
                                                @selected(old('from_coa_id', $preselectedFrom) == $acc->id)>
                                            {{ $acc->code }} — {{ $acc->name }} (Bal: {{ number_format($acc->current_balance, 2) }} ETB) • [{{ ucfirst($acc->type) }}]
                                        </option>
                                    @endforeach
                                </select>
                                @error('from_coa_id')<div class="invalid-feedback">{{ $message }}</div>@enderror

                                {{-- Source Preview Box --}}
                                <div id="fromPreviewBox" class="mt-3 p-3 bg-danger bg-opacity-10 border border-danger-subtle rounded d-none">
                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                        <span class="badge bg-danger">SOURCE ACCOUNT</span>
                                        <span class="small text-muted font-monospace" id="fromAccCode"></span>
                                    </div>
                                    <h6 class="fw-bold text-dark mb-1" id="fromAccName"></h6>
                                    <div class="small text-muted mb-2">Manager: <strong id="fromAccManager"></strong></div>
                                    <div class="d-flex justify-content-between align-items-baseline border-top pt-2 mt-2">
                                        <small class="text-muted">Available Balance:</small>
                                        <span class="fw-bold fs-6 font-monospace text-danger" id="fromAccBalance"></span>
                                    </div>
                                </div>
                            </div>

                            {{-- Destination Account (To) --}}
                            <div class="col-md-6">
                                <label class="form-label fw-bold small text-uppercase text-success">
                                    <i class="fa-solid fa-arrow-down-to-bracket me-1"></i>To Destination Account (Debit) <span class="text-danger">*</span>
                                </label>
                                <select name="to_coa_id" id="toCoaSelect" class="form-select @error('to_coa_id') is-invalid @enderror" required onchange="updateAccountPreviews()">
                                    <option value="">-- Select Destination Account (Where funds go) --</option>
                                    @foreach($accounts as $acc)
                                        <option value="{{ $acc->id }}" 
                                                data-code="{{ $acc->code }}"
                                                data-name="{{ $acc->name }}"
                                                data-type="{{ ucfirst($acc->type) }}"
                                                data-balance="{{ (float)$acc->current_balance }}"
                                                data-manager="{{ $acc->manager->name ?? 'Unassigned' }}"
                                                @selected(old('to_coa_id', $preselectedTo) == $acc->id)>
                                            {{ $acc->code }} — {{ $acc->name }} (Bal: {{ number_format($acc->current_balance, 2) }} ETB) • [{{ ucfirst($acc->type) }}]
                                        </option>
                                    @endforeach
                                </select>
                                @error('to_coa_id')<div class="invalid-feedback">{{ $message }}</div>@enderror

                                {{-- Destination Preview Box --}}
                                <div id="toPreviewBox" class="mt-3 p-3 bg-success bg-opacity-10 border border-success-subtle rounded d-none">
                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                        <span class="badge bg-success">DESTINATION ACCOUNT</span>
                                        <span class="small text-muted font-monospace" id="toAccCode"></span>
                                    </div>
                                    <h6 class="fw-bold text-dark mb-1" id="toAccName"></h6>
                                    <div class="small text-muted mb-2">Manager: <strong id="toAccManager"></strong></div>
                                    <div class="d-flex justify-content-between align-items-baseline border-top pt-2 mt-2">
                                        <small class="text-muted">Current Balance:</small>
                                        <span class="fw-bold fs-6 font-monospace text-success" id="toAccBalance"></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Transfer Amount & Details Card --}}
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-light py-3">
                        <h5 class="mb-0 fw-bold text-dark"><i class="fa-solid fa-money-check-dollar text-success me-2"></i>Transfer Amount & Transaction Details</h5>
                    </div>
                    <div class="card-body p-4">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label fw-bold small text-uppercase">Transfer Amount (ETB) <span class="text-danger">*</span></label>
                                <div class="input-group input-group-lg">
                                    <span class="input-group-text bg-light fw-bold text-primary">ETB</span>
                                    <input type="number" step="0.01" min="0.01" name="amount" id="transferAmountInput" 
                                           class="form-control fw-bold fs-5 @error('amount') is-invalid @enderror" 
                                           value="{{ old('amount') }}" placeholder="0.00" required oninput="calculateBalanceAfter()">
                                    @error('amount')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div id="insufficientBalanceWarning" class="alert alert-warning py-1 px-2 mt-2 small d-none">
                                    <i class="fa-solid fa-triangle-exclamation me-1"></i>
                                    <strong>Notice:</strong> Transfer amount exceeds current recorded balance.
                                </div>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fw-bold small text-uppercase">Transfer Date <span class="text-danger">*</span></label>
                                <input type="date" name="transfer_date" class="form-control form-control-lg @error('transfer_date') is-invalid @enderror" 
                                       value="{{ old('transfer_date', date('Y-m-d')) }}" required>
                                @error('transfer_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fw-bold small text-uppercase">Reference / Cheque # / Bank Trans ID</label>
                                <input type="text" name="reference_no" class="form-control" 
                                       value="{{ old('reference_no') }}" placeholder="e.g. FT2608191244, CHQ-10492, Slip #">
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fw-bold small text-uppercase">Attach Slip / Receipt Proof (Optional)</label>
                                <input type="file" name="attachment" class="form-control" 
                                       accept="application/pdf,image/jpeg,image/png,image/jpg,image/webp"
                                       onchange="previewReceiptFile(this)">
                                <div id="receiptPreviewBox" class="mt-2 d-none">
                                    <img src="" alt="Transfer Receipt Preview" style="max-height: 120px; object-fit: contain; border: 1px solid #dee2e6; border-radius: 6px; padding: 2px;">
                                </div>
                            </div>

                            <div class="col-12">
                                <label class="form-label fw-bold small text-uppercase">Transfer Reason / Remarks <span class="text-danger">*</span></label>
                                <textarea name="description" class="form-control @error('description') is-invalid @enderror" rows="3" 
                                          placeholder="Explain the purpose of this fund transfer (e.g. Replenish Site Petty Cash, Inter-account transfer, Emergency fund allocation...)" required>{{ old('description') }}</textarea>
                                @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Right Column: Accounting Impact Summary --}}
            <div class="col-lg-4">
                <div class="card border-0 shadow-sm bg-light sticky-top" style="top: 20px;">
                    <div class="card-header bg-dark text-white py-3">
                        <h6 class="mb-0 fw-bold"><i class="fa-solid fa-scale-balanced me-2"></i>Accounting Impact Summary</h6>
                    </div>
                    <div class="card-body p-4">
                        <div class="d-flex justify-content-between mb-2 small">
                            <span class="text-muted">Transaction Type:</span>
                            <span class="fw-bold">Inter-COA Fund Transfer</span>
                        </div>
                        <div class="d-flex justify-content-between mb-2 small">
                            <span class="text-muted">Posting Method:</span>
                            <span class="badge bg-success">Automated Double-Entry</span>
                        </div>
                        <div class="d-flex justify-content-between mb-3 small">
                            <span class="text-muted">General Ledger:</span>
                            <span class="badge bg-primary">Auto-Posted Journal</span>
                        </div>

                        <hr>

                        <h6 class="fw-bold small text-uppercase text-muted mb-3">Projected Balance Changes:</h6>

                        {{-- Source Account Projected --}}
                        <div class="p-3 bg-white border rounded mb-3">
                            <small class="text-danger fw-bold d-block mb-1">
                                <i class="fa-solid fa-minus-circle me-1"></i>Source (From) Account:
                            </small>
                            <div class="fw-semibold text-dark small" id="projFromName">— Select Source —</div>
                            <div class="d-flex justify-content-between align-items-center mt-2 small font-monospace">
                                <span class="text-muted">New Balance:</span>
                                <strong class="text-dark" id="projFromAfter">—</strong>
                            </div>
                        </div>

                        {{-- Destination Account Projected --}}
                        <div class="p-3 bg-white border rounded mb-3">
                            <small class="text-success fw-bold d-block mb-1">
                                <i class="fa-solid fa-plus-circle me-1"></i>Destination (To) Account:
                            </small>
                            <div class="fw-semibold text-dark small" id="projToName">— Select Destination —</div>
                            <div class="d-flex justify-content-between align-items-center mt-2 small font-monospace">
                                <span class="text-muted">New Balance:</span>
                                <strong class="text-dark" id="projToAfter">—</strong>
                            </div>
                        </div>

                        <div class="d-grid gap-2 mt-4">
                            <button type="submit" class="btn btn-primary btn-lg fw-bold shadow">
                                <i class="fa-solid fa-check-circle me-2"></i>Execute Transfer
                            </button>
                            <a href="{{ route('coa-transfers.index') }}" class="btn btn-outline-secondary">
                                Cancel
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

<script>
function updateAccountPreviews() {
    const fromSel = document.getElementById('fromCoaSelect');
    const toSel   = document.getElementById('toCoaSelect');

    const fromOpt = fromSel.options[fromSel.selectedIndex];
    const toOpt   = toSel.options[toSel.selectedIndex];

    // Update From Preview
    const fromBox = document.getElementById('fromPreviewBox');
    if (fromOpt && fromOpt.value) {
        document.getElementById('fromAccCode').textContent = fromOpt.dataset.code;
        document.getElementById('fromAccName').textContent = fromOpt.dataset.name;
        document.getElementById('fromAccManager').textContent = fromOpt.dataset.manager;
        document.getElementById('fromAccBalance').textContent = Number(fromOpt.dataset.balance).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' ETB';
        document.getElementById('projFromName').textContent = `[${fromOpt.dataset.code}] ${fromOpt.dataset.name}`;
        fromBox.classList.remove('d-none');
    } else {
        fromBox.classList.add('d-none');
        document.getElementById('projFromName').textContent = '— Select Source —';
    }

    // Update To Preview
    const toBox = document.getElementById('toPreviewBox');
    if (toOpt && toOpt.value) {
        document.getElementById('toAccCode').textContent = toOpt.dataset.code;
        document.getElementById('toAccName').textContent = toOpt.dataset.name;
        document.getElementById('toAccManager').textContent = toOpt.dataset.manager;
        document.getElementById('toAccBalance').textContent = Number(toOpt.dataset.balance).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' ETB';
        document.getElementById('projToName').textContent = `[${toOpt.dataset.code}] ${toOpt.dataset.name}`;
        toBox.classList.remove('d-none');
    } else {
        toBox.classList.add('d-none');
        document.getElementById('projToName').textContent = '— Select Destination —';
    }

    calculateBalanceAfter();
}

function calculateBalanceAfter() {
    const fromSel = document.getElementById('fromCoaSelect');
    const toSel   = document.getElementById('toCoaSelect');
    const amtInput = document.getElementById('transferAmountInput');

    const fromOpt = fromSel.options[fromSel.selectedIndex];
    const toOpt   = toSel.options[toSel.selectedIndex];
    const amount  = parseFloat(amtInput.value) || 0;

    const warnEl  = document.getElementById('insufficientBalanceWarning');

    if (fromOpt && fromOpt.value) {
        const curBal = parseFloat(fromOpt.dataset.balance) || 0;
        const newBal = curBal - amount;
        document.getElementById('projFromAfter').textContent = newBal.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' ETB';

        if (amount > curBal && amount > 0) {
            warnEl.classList.remove('d-none');
        } else {
            warnEl.classList.add('d-none');
        }
    } else {
        document.getElementById('projFromAfter').textContent = '—';
        warnEl.classList.add('d-none');
    }

    if (toOpt && toOpt.value) {
        const curBal = parseFloat(toOpt.dataset.balance) || 0;
        const newBal = curBal + amount;
        document.getElementById('projToAfter').textContent = newBal.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' ETB';
    } else {
        document.getElementById('projToAfter').textContent = '—';
    }
}

function previewReceiptFile(input) {
    const box = document.getElementById('receiptPreviewBox');
    if (input.files && input.files[0]) {
        const file = input.files[0];
        if (file.type.startsWith('image/')) {
            const reader = new FileReader();
            reader.onload = function(e) {
                const img = box.querySelector('img');
                if (img) {
                    img.src = e.target.result;
                    box.classList.remove('d-none');
                }
            };
            reader.readAsDataURL(file);
        } else {
            box.classList.add('d-none');
        }
    } else {
        box.classList.add('d-none');
    }
}

document.addEventListener('DOMContentLoaded', function() {
    updateAccountPreviews();
});
</script>
@endsection
