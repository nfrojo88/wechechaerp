@extends('layouts.app')
@section('title', 'Record Income & Sales Receipt')
@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-1 text-gray-800"><i class="fas fa-plus-circle text-success me-2"></i>Record New Income &amp; Receipt</h1>
            <p class="text-muted small mb-0">Record company revenue with ERCA eTax Sales Receipt specifications</p>
        </div>
        <a href="{{ route('income.index') }}" class="btn btn-outline-secondary shadow-sm rounded-pill px-3"><i class="fas fa-arrow-left me-1"></i>Back to Income List</a>
    </div>

    @if($errors->any())
        <div class="alert alert-danger shadow-sm rounded-3">
            <ul class="mb-0">
                @foreach($errors->all() as $err)
                    <li>{{ $err }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="card shadow border-0 rounded-4 mb-4" style="max-width: 960px;">
        <div class="card-body p-4 p-md-5">
            <form action="{{ route('income.store') }}" method="POST" enctype="multipart/form-data">
                @csrf
                <div class="row g-4">
                    {{-- ── SECTION 1: Income Information ── --}}
                    <div class="col-12">
                        <h6 class="fw-bold text-dark border-bottom pb-2">
                            <i class="fa-solid fa-wallet text-success me-1"></i>General Income Details
                        </h6>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Income Division <span class="text-danger">*</span></label>
                        <select name="category" class="form-select" required>
                            <option value="transportation">Transportation</option>
                            <option value="rental">Rental</option>
                            <option value="construction">Construction</option>
                            <option value="other">Other Income</option>
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Income Date (GC or EC) <span class="text-danger">*</span></label>
                        <input type="date" name="income_date" class="form-control" value="{{ date('Y-m-d') }}">
                        <input type="text" name="income_date_ec" class="form-control font-monospace mt-1" placeholder="DD/MM/YYYY EC" value="{{ \App\Services\EthiopianDate::formatDateEc(now(), 'DD/MM/YYYY EC') }}">
                        <small class="text-muted">Today (EC): {{ \App\Services\EthiopianDate::formatDateEc(now()) }}</small>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Project / Site (Optional)</label>
                        <select name="project_id" class="form-select">
                            <option value="">-- No Project --</option>
                            @foreach($projects ?? [] as $p)
                                <option value="{{ $p->id }}">{{ $p->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Gross Amount (ETB) <span class="text-danger">*</span></label>
                        <input type="number" step="0.01" name="amount" id="createAmount" class="form-control fw-bold" required placeholder="0.00">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Payment Method <span class="text-danger">*</span></label>
                        <select name="payment_method" class="form-select" required>
                            <option value="bank_transfer" selected>Bank Transfer</option>
                            <option value="cash">Cash</option>
                            <option value="cheque">Cheque</option>
                        </select>
                    </div>

                    <!-- Real-Time Computed Tax Box -->
                    <div class="col-12">
                        <div class="p-3 bg-light border rounded-3">
                            <div class="row g-2 text-center">
                                <div class="col-3 border-end">
                                    <small class="text-muted d-block" style="font-size: 0.72rem;">GROSS</small>
                                    <strong class="text-dark small" id="lblCreateGross">0.00</strong>
                                </div>
                                <div class="col-3 border-end">
                                    <small class="text-muted d-block" style="font-size: 0.72rem;">VAT (+15%)</small>
                                    <strong class="text-success small" id="lblCreateVat">+0.00</strong>
                                </div>
                                <div class="col-3 border-end">
                                    <small class="text-muted d-block" style="font-size: 0.72rem;" id="lblCreateWhtTitle">WHT (0%)</small>
                                    <strong class="text-danger small" id="lblCreateWht">0.00</strong>
                                </div>
                                <div class="col-3">
                                    <small class="text-muted d-block" style="font-size: 0.72rem;">NET TOTAL</small>
                                    <strong class="text-primary small" id="lblCreateNet">0.00</strong>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ── WITHHOLDING TAX & RECEIPT UNLOCK SECTION ── -->
                    <div class="col-12">
                        <div class="card border border-warning border-opacity-50 bg-warning bg-opacity-10 rounded-3 p-3 shadow-2xs">
                            <div class="d-flex align-items-center justify-content-between">
                                <div class="d-flex align-items-center gap-2">
                                    <span class="badge bg-warning text-dark p-2 rounded-circle">
                                        <i class="fa-solid fa-file-invoice-dollar fs-6"></i>
                                    </span>
                                    <div>
                                        <label class="form-check-label fw-bold text-dark small mb-0 cursor-pointer" for="createWithholdingToggle">
                                            Withholding Tax Receipt (የቅድመ ግብር 3% ደረሰኝ)
                                        </label>
                                        <div class="text-muted small" style="font-size: 0.72rem;">
                                            Unlock to attach client's withholding tax receipt &amp; upload both receipts
                                        </div>
                                    </div>
                                </div>
                                <div class="form-check form-switch m-0">
                                    <input class="form-check-input fs-5 cursor-pointer" type="checkbox" role="switch" name="has_withholding" id="createWithholdingToggle" value="1" onchange="toggleCreateWithholding()">
                                </div>
                            </div>

                            <!-- Unlocked Withholding Receipt & Details Section -->
                            <div id="createWithholdingSection" class="mt-3 pt-3 border-top border-warning border-opacity-25" style="display: none;">
                                <div class="row g-3">
                                    <div class="col-md-7">
                                        <label class="form-label small fw-semibold text-dark mb-1">
                                            <i class="fa-solid fa-paperclip text-danger me-1"></i>Attach Withholding Receipt Slip <span class="text-danger">*</span>
                                        </label>
                                        <input type="file" name="withholding_receipt" id="createWithholdingReceipt" class="form-control bg-white border" accept="image/jpeg,image/png,image/webp,application/pdf" onchange="if(this.files.length){ document.getElementById('createWithholdingToggle').checked = true; updateCreateCalculations(); }">
                                        <small class="text-muted" style="font-size: 0.70rem;">Official WHT receipt slip or voucher (JPG, PNG, PDF up to 25MB)</small>
                                    </div>
                                    <div class="col-md-5">
                                        <label class="form-label small fw-semibold text-dark mb-1">Withholding Receipt # / Voucher</label>
                                        <input type="text" name="withholding_receipt_number" id="createWithholdingReceiptNo" class="form-control bg-white border font-monospace" placeholder="e.g. WHT-00984">
                                        <small class="text-muted" style="font-size: 0.70rem;">Serial # on client's withholding slip</small>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label small fw-semibold text-dark mb-1">Withholding Rate (%)</label>
                                        <div class="input-group">
                                            <input type="number" step="0.01" name="withholding_rate" id="createWithholdingRate" class="form-control bg-white border font-monospace fw-bold" value="3.00" oninput="updateCreateCalculations()">
                                            <span class="input-group-text">%</span>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label small fw-semibold text-dark mb-1">Deducted WHT Amount</label>
                                        <div class="input-group">
                                            <span class="input-group-text">ETB</span>
                                            <input type="number" step="0.01" name="withholding_amount" id="createWithholdingAmount" class="form-control bg-white border font-monospace fw-bold text-danger" placeholder="0.00" readonly>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <label class="form-label fw-semibold mb-0">
                                <i class="fa-solid fa-building-columns text-primary me-1"></i>Deposit to Bank / Cash Account (COA) <span class="text-danger">*</span>
                            </label>
                            <span class="badge bg-primary bg-opacity-10 text-primary border border-primary-subtle rounded-pill small" style="font-size: 0.70rem;">
                                From Chart of Accounts
                            </span>
                        </div>
                        <select name="chart_of_account_id" id="createDepositAccount" class="form-select" required onchange="const opt = this.options[this.selectedIndex]; document.getElementById('createBankAccountId').value = opt ? opt.getAttribute('data-bank-id') : '';">
                            <option value="">-- Select Account from COA --</option>
                            @foreach($coaAccounts ?? [] as $coa)
                                @php
                                    $linkedBank = $coa->bankAccounts->first();
                                    $bankDetail = $linkedBank ? " • A/C: {$linkedBank->account_number} ({$linkedBank->bank_name})" : "";
                                @endphp
                                <option value="{{ $coa->id }}" 
                                        data-bank-id="{{ $linkedBank->id ?? '' }}"
                                        data-balance="{{ $coa->current_balance }}">
                                    [{{ $coa->code }}] {{ $coa->name }}{{ $bankDetail }} — Available: ETB {{ number_format($coa->current_balance, 2) }}
                                </option>
                            @endforeach
                        </select>
                        <input type="hidden" name="bank_account_id" id="createBankAccountId" value="">
                        <div class="form-text small text-muted">
                            <i class="fa-solid fa-circle-check text-success me-1"></i>Amount will be credited directly to this account balance.
                        </div>
                    </div>

                    <div class="col-12">
                        <label class="form-label fw-semibold">Description</label>
                        <input type="text" name="description" class="form-control" placeholder="Brief description of this income or contract...">
                    </div>

                    {{-- ── SECTION 2: Receipt & ERCA eTax Fields ── --}}
                    <div class="col-12 mt-4">
                        <h6 class="fw-bold text-primary border-bottom pb-2">
                            <i class="fa-solid fa-file-invoice text-primary me-1"></i>Sales Receipt &amp; ERCA eTax Details (13-Col Format)
                        </h6>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Attach Receipt Slip / Document</label>
                        <input type="file" name="receipt_file" class="form-control" accept="image/jpeg,image/png,image/webp,application/pdf">
                        <small class="text-muted">Fiscal invoice image or PDF document</small>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label fw-semibold">Seller TIN (Company)</label>
                        <input type="text" name="seller_tin" class="form-control font-monospace" value="38480010" readonly>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label fw-semibold">Buyer / Client TIN</label>
                        <input type="text" name="buyer_tin" class="form-control font-monospace" placeholder="e.g. 5201, 6870105">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Buyer / Client Trade Name</label>
                        <input type="text" name="buyer_name" class="form-control" placeholder="Client business name">
                    </div>

                    <div class="col-md-3">
                        <label class="form-label fw-semibold">Receipt No (FS#)</label>
                        <input type="text" name="receipt_no" class="form-control font-monospace fw-bold text-success" placeholder="FS00003898">
                    </div>

                    <div class="col-md-3">
                        <label class="form-label fw-semibold">MRC (Machine Code)</label>
                        <input type="text" name="mrc_no" class="form-control font-monospace" placeholder="DDB0000032">
                    </div>

                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Transaction</label>
                        <select name="transaction_type" class="form-select">
                            <option value="Cash Sales" selected>Cash Sales</option>
                            <option value="Credit Sales">Credit Sales</option>
                            <option value="Local Sales">Local Sales</option>
                            <option value="Local Sales with VAT">Local Sales with VAT</option>
                            <option value="Advance Payment">Advance Payment</option>
                        </select>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Receipt Category</label>
                        <select name="receipt_category" class="form-select">
                            <option value="Cash Sales Fiscal Receipt" selected>Cash Sales Fiscal Receipt</option>
                            <option value="VAT Invoice">VAT Invoice</option>
                            <option value="Manual Receipt">Manual Receipt</option>
                        </select>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Receipt Used</label>
                        <select name="receipt_used" class="form-select">
                            <option value="Fiscal Cash Register" selected>Fiscal Cash Register</option>
                            <option value="Electronic Cash Register">Electronic Cash Register</option>
                            <option value="Sales Register Machine">Sales Register Machine</option>
                            <option value="Manual Receipt">Manual Receipt</option>
                        </select>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Commodity Type</label>
                        <select name="commodity_type" class="form-select">
                            <option value="Services" selected>Services</option>
                            <option value="Goods">Goods</option>
                            <option value="Transportation">Transportation</option>
                            <option value="Rental">Rental</option>
                            <option value="Construction">Construction</option>
                        </select>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Quantity</label>
                        <input type="number" step="0.01" name="quantity" class="form-control" value="1.00">
                    </div>

                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Receipt Date</label>
                        <input type="date" name="receipt_date" class="form-control" value="{{ date('Y-m-d') }}">
                        <input type="text" name="receipt_date_ec" class="form-control font-monospace mt-1" placeholder="DD/MM/YYYY EC" value="{{ \App\Services\EthiopianDate::formatDateEc(now(), 'DD/MM/YYYY EC') }}">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Encoded By</label>
                        <input type="text" name="encoded_by" class="form-control font-monospace" value="wechacha">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Additional Notes</label>
                        <textarea name="notes" class="form-control" rows="2"></textarea>
                    </div>
                </div>

                <div class="mt-4 pt-3 border-top d-flex gap-2">
                    <button type="submit" class="btn btn-primary rounded-pill px-4 fw-bold shadow-sm"><i class="fas fa-save me-1"></i>Save Income &amp; Receipt</button>
                    <a href="{{ route('income.index') }}" class="btn btn-outline-secondary rounded-pill px-4">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function toggleCreateWithholding() {
    const toggle = document.getElementById('createWithholdingToggle');
    const sec = document.getElementById('createWithholdingSection');
    if (!toggle || !sec) return;
    if (toggle.checked) {
        sec.style.display = 'block';
    } else {
        sec.style.display = 'none';
        const fileInput = document.getElementById('createWithholdingReceipt');
        if (fileInput) fileInput.value = '';
        const noInput = document.getElementById('createWithholdingReceiptNo');
        if (noInput) noInput.value = '';
    }
    updateCreateCalculations();
}

function updateCreateCalculations() {
    const amountInput = document.getElementById('createAmount');
    const lblGross = document.getElementById('lblCreateGross');
    const lblVat = document.getElementById('lblCreateVat');
    const lblWht = document.getElementById('lblCreateWht');
    const lblWhtTitle = document.getElementById('lblCreateWhtTitle');
    const lblNet = document.getElementById('lblCreateNet');
    const toggle = document.getElementById('createWithholdingToggle');
    const rateInput = document.getElementById('createWithholdingRate');
    const amtInput = document.getElementById('createWithholdingAmount');

    const gross = parseFloat(amountInput ? amountInput.value : 0) || 0;
    const vat = gross * 0.15;
    const hasWht = toggle ? toggle.checked : false;
    const rate = parseFloat(rateInput ? rateInput.value : 3) || 3;
    const wht = hasWht ? (gross * (rate / 100)) : 0;
    const net = gross + vat - wht;

    if (amtInput) {
        amtInput.value = wht.toFixed(2);
    }

    if (lblWhtTitle) {
        lblWhtTitle.textContent = hasWht ? `WHT (-${rate}%)` : 'WHT (0%)';
    }
    if (lblGross) lblGross.textContent = gross.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    if (lblVat) lblVat.textContent = '+' + vat.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    if (lblWht) {
        lblWht.textContent = hasWht ? ('-' + wht.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})) : '0.00';
    }
    if (lblNet) lblNet.textContent = net.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
}

document.addEventListener('DOMContentLoaded', function() {
    const amountInput = document.getElementById('createAmount');
    if (amountInput) {
        amountInput.addEventListener('input', updateCreateCalculations);
    }
    const rateInput = document.getElementById('createWithholdingRate');
    if (rateInput) {
        rateInput.addEventListener('input', updateCreateCalculations);
    }
});
</script>
@endsection
