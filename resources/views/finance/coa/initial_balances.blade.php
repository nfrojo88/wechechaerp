@extends('layouts.app')
@section('title', 'One-Time COA Balance & Ledger Setup')
@section('content')
<div class="container-fluid py-3">
    {{-- Header & Breadcrumb --}}
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
        <div>
            <div class="d-flex align-items-center gap-2 mb-1">
                <a href="{{ route('coa.index') }}" class="text-decoration-none text-muted small">
                    <i class="fa-solid fa-arrow-left me-1"></i>Chart of Accounts
                </a>
                <span class="text-muted small">/</span>
                <span class="text-primary small fw-semibold">One-Time Balance &amp; Ledger Setup</span>
            </div>
            <h1 class="h3 mb-0 fw-bold text-dark">
                <i class="fa-solid fa-sliders text-warning me-2"></i>One-Time Account Ledger &amp; Balance Preparation
            </h1>
            <p class="text-muted small mb-0">
                Bulk configure baseline balances for all existing accounts and automatically post a balanced opening General Ledger journal entry.
            </p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('coa.index') }}" class="btn btn-outline-secondary btn-sm rounded-pill px-3">
                <i class="fa-solid fa-xmark me-1"></i>Cancel
            </a>
            <button type="button" class="btn btn-primary btn-sm rounded-pill px-4 fw-bold shadow-sm" onclick="confirmAndSubmit();">
                <i class="fa-solid fa-save me-1"></i>Save &amp; Post Ledger
            </button>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show shadow-sm rounded-3 mb-4">
            <i class="fa-solid fa-circle-check me-2"></i>{{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show shadow-sm rounded-3 mb-4">
            <i class="fa-solid fa-triangle-exclamation me-2"></i>{{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <form id="initialBalancesForm" action="{{ route('coa.initial-balances.save') }}" method="POST">
        @csrf

        {{-- Configuration Controls Card --}}
        <div class="card border-0 shadow-sm rounded-4 mb-4">
            <div class="card-header bg-white border-bottom py-3 px-4">
                <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                    <h6 class="fw-bold mb-0 text-dark">
                        <i class="fa-solid fa-gears text-primary me-2"></i>1. Setup Parameters &amp; Double-Entry Rules
                    </h6>
                    <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle rounded-pill px-3 py-1 font-monospace" style="font-size: 0.75rem;">
                        One-Time Preparation Tool
                    </span>
                </div>
            </div>
            <div class="card-body p-4">
                <div class="row g-3">
                    {{-- Operation Mode --}}
                    <div class="col-12 col-lg-4">
                        <label class="form-label fw-bold text-dark small mb-2">
                            Balance Operation Mode <span class="text-danger">*</span>
                        </label>
                        <div class="d-flex flex-column gap-2">
                            <label class="border rounded-3 p-2.5 d-flex align-items-start gap-2 cursor-pointer mode-option-card active" id="modeCardSet">
                                <input class="form-check-input mt-1" type="radio" name="mode" id="modeSet" value="set" checked onchange="updateModeUI();">
                                <div>
                                    <div class="fw-bold text-dark small">Set New Baseline Balance (Recommended)</div>
                                    <small class="text-muted" style="font-size: 0.75rem;">Directly set each account's target opening and current balance.</small>
                                </div>
                            </label>
                            <label class="border rounded-3 p-2.5 d-flex align-items-start gap-2 cursor-pointer mode-option-card" id="modeCardAdd">
                                <input class="form-check-input mt-1" type="radio" name="mode" id="modeAdd" value="add" onchange="updateModeUI();">
                                <div>
                                    <div class="fw-bold text-dark small">Add / Adjust to Existing Balance</div>
                                    <small class="text-muted" style="font-size: 0.75rem;">Add (+) or subtract (-) an adjustment amount to current balances.</small>
                                </div>
                            </label>
                        </div>
                    </div>

                    {{-- Dates & Notes --}}
                    <div class="col-12 col-md-6 col-lg-4">
                        <div class="mb-3">
                            <label class="form-label fw-bold text-dark small mb-1">
                                Effective Date <span class="text-danger">*</span>
                            </label>
                            <input type="date" name="effective_date" class="form-control form-control-sm" value="{{ old('effective_date', date('Y-m-d')) }}" required>
                            <small class="text-muted" style="font-size: 0.72rem;">Accounting entry date assigned to the General Ledger voucher.</small>
                        </div>

                        <div>
                            <label class="form-label fw-bold text-dark small mb-1">
                                Reference / Description Note
                            </label>
                            <input type="text" name="reference_note" class="form-control form-control-sm" placeholder="e.g. FY Opening Balance Setup / Initial Ledger Baseline" value="{{ old('reference_note', 'FY Opening Balance Setup / Initial Ledger Baseline') }}">
                            <small class="text-muted" style="font-size: 0.72rem;">Voucher description displayed on the General Ledger report.</small>
                        </div>
                    </div>

                    {{-- Equity Balancing Account & Options --}}
                    <div class="col-12 col-md-6 col-lg-4">
                        <div class="mb-3">
                            <label class="form-label fw-bold text-dark small mb-1">
                                Balancing Equity Account <span class="text-danger">*</span>
                            </label>
                            <select name="balancing_equity_id" id="balancingEquitySelect" class="form-select form-select-sm" required>
                                @foreach($equityAccounts as $eq)
                                    <option value="{{ $eq->id }}" {{ (old('balancing_equity_id', $defaultEquity?->id) == $eq->id) ? 'selected' : '' }}>
                                        {{ $eq->code }} - {{ $eq->name }} (Current: {{ number_format($eq->current_balance, 2) }} ETB)
                                    </option>
                                @endforeach
                            </select>
                            <small class="text-muted" style="font-size: 0.72rem;">Any net debit/credit imbalance will be automatically reconciled here.</small>
                        </div>

                        <div class="bg-light p-2.5 rounded-3 border">
                            <div class="form-check form-switch mb-1.5">
                                <input class="form-check-input" type="checkbox" name="post_to_ledger" id="postToLedger" value="1" checked>
                                <label class="form-check-label small fw-semibold text-dark" for="postToLedger">
                                    Post baseline Journal Entry to General Ledger
                                </label>
                            </div>
                            <div class="form-check form-switch mb-1.5">
                                <input class="form-check-input" type="checkbox" name="auto_balance_equity" id="autoBalanceEquity" value="1" checked>
                                <label class="form-check-label small fw-semibold text-dark" for="autoBalanceEquity">
                                    Auto-balance variance against Beginning Equity
                                </label>
                            </div>
                            <div class="form-check form-switch mb-0">
                                <input class="form-check-input" type="checkbox" name="sync_bank_accounts" id="syncBankAccounts" value="1" checked>
                                <label class="form-check-label small fw-semibold text-dark" for="syncBankAccounts">
                                    Synchronize linked Bank Accounts
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Live Equilibrium & Balance Preview Bar --}}
        <div class="card border-0 shadow-sm rounded-4 mb-4 bg-dark text-white sticky-top" style="top: 15px; z-index: 1020;">
            <div class="card-body py-2.5 px-4">
                <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
                    <div class="d-flex align-items-center gap-3 flex-wrap">
                        <div>
                            <span class="text-light opacity-75 small text-uppercase fw-semibold" style="font-size: 0.7rem;">Total Debits (Assets + Expenses)</span>
                            <div class="font-monospace fw-bold fs-5 text-success" id="liveTotalDebits">0.00 ETB</div>
                        </div>
                        <div class="border-start border-secondary ps-3">
                            <span class="text-light opacity-75 small text-uppercase fw-semibold" style="font-size: 0.7rem;">Total Credits (Liab + Equity + Rev)</span>
                            <div class="font-monospace fw-bold fs-5 text-info" id="liveTotalCredits">0.00 ETB</div>
                        </div>
                        <div class="border-start border-secondary ps-3">
                            <span class="text-light opacity-75 small text-uppercase fw-semibold" style="font-size: 0.7rem;">Net Variance (Imbalance)</span>
                            <div class="font-monospace fw-bold fs-5 text-warning" id="liveNetVariance">0.00 ETB</div>
                        </div>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <span id="liveEquilibriumBadge" class="badge bg-success rounded-pill px-3 py-2 fw-semibold">
                            <i class="fa-solid fa-scale-balanced me-1"></i>In Balance
                        </span>
                        <button type="button" class="btn btn-sm btn-success fw-bold rounded-pill px-4 shadow-sm" onclick="confirmAndSubmit();">
                            <i class="fa-solid fa-check me-1"></i>Commit &amp; Post Ledger
                        </button>
                    </div>
                </div>
            </div>
        </div>

        {{-- Accounts Table Card --}}
        <div class="card border-0 shadow-sm rounded-4 mb-4">
            <div class="card-header bg-white border-bottom py-3 px-4">
                <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
                    <div>
                        <h6 class="fw-bold mb-0 text-dark">
                            <i class="fa-solid fa-list-check text-primary me-2"></i>2. Account Balances Table ({{ $accounts->count() }} Accounts)
                        </h6>
                        <small class="text-muted">Enter desired balances or adjustments for each active account below.</small>
                    </div>

                    {{-- Quick Action Buttons --}}
                    <div class="d-flex align-items-center gap-2 flex-wrap">
                        <button type="button" class="btn btn-xs btn-outline-primary py-1 px-2.5 rounded-pill" onclick="copyCurrentBalances();">
                            <i class="fa-solid fa-copy me-1"></i>Copy Current to Inputs
                        </button>
                        <button type="button" class="btn btn-xs btn-outline-secondary py-1 px-2.5 rounded-pill" onclick="fillZeros();">
                            <i class="fa-solid fa-hashtag me-1"></i>Fill Blank with 0
                        </button>
                        <button type="button" class="btn btn-xs btn-outline-danger py-1 px-2.5 rounded-pill" onclick="clearAllInputs();">
                            <i class="fa-solid fa-rotate-left me-1"></i>Clear Inputs
                        </button>
                        <div class="ms-2">
                            <input type="text" id="tableSearchInput" class="form-control form-control-sm rounded-pill" placeholder="🔍 Search code or name..." style="width: 220px;" onkeyup="filterTable();">
                        </div>
                    </div>
                </div>

                {{-- Category Filter Tabs --}}
                <div class="d-flex flex-wrap gap-1 mt-3" id="categoryFilterButtons">
                    <button type="button" class="btn btn-sm btn-primary rounded-pill px-3 py-1 active filter-btn" data-filter="all" onclick="applyCategoryFilter('all', this);">
                        All ({{ $accounts->count() }})
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-primary rounded-pill px-3 py-1 filter-btn" data-filter="asset" onclick="applyCategoryFilter('asset', this);">
                        Assets ({{ $accounts->where('type', 'asset')->count() }})
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-danger rounded-pill px-3 py-1 filter-btn" data-filter="liability" onclick="applyCategoryFilter('liability', this);">
                        Liabilities ({{ $accounts->where('type', 'liability')->count() }})
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-success rounded-pill px-3 py-1 filter-btn" data-filter="equity" onclick="applyCategoryFilter('equity', this);">
                        Equity ({{ $accounts->where('type', 'equity')->count() }})
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-info rounded-pill px-3 py-1 filter-btn" data-filter="revenue" onclick="applyCategoryFilter('revenue', this);">
                        Revenue ({{ $accounts->where('type', 'revenue')->count() }})
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-warning rounded-pill px-3 py-1 filter-btn" data-filter="expense" onclick="applyCategoryFilter('expense', this);">
                        Expenses ({{ $accounts->where('type', 'expense')->count() }})
                    </button>
                </div>
            </div>

            <div class="card-body p-0">
                <div class="table-responsive" style="max-height: 650px; overflow-y: auto;">
                    <table class="table table-hover align-middle mb-0" id="accountsTable">
                        <thead class="table-light text-uppercase small sticky-top" style="z-index: 10;">
                            <tr>
                                <th style="width: 110px;">Code</th>
                                <th>Account Name &amp; Category</th>
                                <th style="width: 110px;">Type</th>
                                <th style="width: 140px;" class="text-end">Current Bal. (ETB)</th>
                                <th style="width: 220px;" class="text-center" id="colHeaderInput">Target Balance (ETB)</th>
                                <th style="width: 150px;" class="text-end">Resulting Preview</th>
                                <th style="width: 50px;" class="text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @php
                                $typeColors = [
                                    'asset'     => 'primary',
                                    'liability' => 'danger',
                                    'equity'    => 'success',
                                    'revenue'   => 'info',
                                    'expense'   => 'warning',
                                ];
                            @endphp
                            @foreach($accounts as $acc)
                                <tr class="account-row" data-type="{{ $acc->type }}" data-code="{{ strtolower($acc->code) }}" data-name="{{ strtolower($acc->name) }}">
                                    <td class="font-monospace fw-bold text-primary">
                                        {{ $acc->code }}
                                    </td>
                                    <td>
                                        <div class="fw-semibold text-dark">{{ $acc->name }}</div>
                                        <div class="d-flex align-items-center gap-1.5 small text-muted" style="font-size: 0.72rem;">
                                            <span>Subtype: {{ ucfirst(str_replace('_', ' ', $acc->subtype ?? $acc->type)) }}</span>
                                            @if($acc->manager)
                                                <span>&bull; Mgr: {{ $acc->manager->name }}</span>
                                            @endif
                                            @if($acc->bankAccounts->isNotEmpty())
                                                <span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill px-1.5" style="font-size: 0.65rem;">
                                                    <i class="fa-solid fa-building-columns me-0.5"></i>{{ $acc->bankAccounts->count() }} Bank Acc
                                                </span>
                                            @endif
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge bg-{{ $typeColors[$acc->type] ?? 'secondary' }} rounded-pill px-2.5 py-1">
                                            {{ ucfirst($acc->type) }}
                                        </span>
                                    </td>
                                    <td class="text-end font-monospace fw-semibold {{ $acc->current_balance < 0 ? 'text-danger' : 'text-dark' }}">
                                        <span class="current-balance-val" data-val="{{ (float)$acc->current_balance }}">
                                            {{ number_format($acc->current_balance, 2) }}
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <div class="input-group input-group-sm">
                                            <span class="input-group-text bg-light text-muted font-monospace" style="font-size: 0.75rem;">ETB</span>
                                            <input type="number" step="0.01" 
                                                   name="balances[{{ $acc->id }}]" 
                                                   class="form-control form-control-sm font-monospace fw-bold text-end balance-input" 
                                                   data-acc-id="{{ $acc->id }}" 
                                                   data-type="{{ $acc->type }}" 
                                                   data-current="{{ (float)$acc->current_balance }}"
                                                   placeholder="0.00" 
                                                   value="{{ old('balances.' . $acc->id, '') }}"
                                                   oninput="calculateLiveTotals();">
                                        </div>
                                    </td>
                                    <td class="text-end font-monospace fw-bold preview-balance-cell" id="preview-{{ $acc->id }}">
                                        {{ number_format($acc->current_balance, 2) }}
                                    </td>
                                    <td class="text-center">
                                        <button type="button" class="btn btn-link btn-xs text-muted p-0" title="Reset this row" onclick="resetRow({{ $acc->id }});">
                                            <i class="fa-solid fa-xmark"></i>
                                        </button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card-footer bg-white border-top py-3 px-4 d-flex justify-content-between align-items-center flex-wrap gap-2">
                <span class="text-muted small" id="visibleRowCount">Showing all {{ $accounts->count() }} accounts</span>
                <div class="d-flex gap-2">
                    <a href="{{ route('coa.index') }}" class="btn btn-secondary btn-sm rounded-pill px-3">
                        <i class="fa-solid fa-arrow-left me-1"></i>Back to Chart of Accounts
                    </a>
                    <button type="button" class="btn btn-primary btn-sm rounded-pill px-4 fw-bold shadow-sm" onclick="confirmAndSubmit();">
                        <i class="fa-solid fa-save me-1"></i>Save &amp; Post Ledger
                    </button>
                </div>
            </div>
        </div>
    </form>
</div>

{{-- Confirmation Modal --}}
<div class="modal fade" id="confirmSubmitModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header bg-primary text-white py-3">
                <h6 class="modal-title fw-bold">
                    <i class="fa-solid fa-stamp me-2"></i>Confirm One-Time Balance &amp; Ledger Setup
                </h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <p class="mb-3 text-dark">
                    You are about to prepare and update account balances for the Chart of Accounts.
                </p>

                <div class="bg-light p-3 rounded-3 border mb-3 small">
                    <div class="d-flex justify-content-between py-1 border-bottom">
                        <span class="text-muted">Selected Mode:</span>
                        <strong class="text-dark" id="modalModeLabel">Set New Baseline Balance</strong>
                    </div>
                    <div class="d-flex justify-content-between py-1 border-bottom">
                        <span class="text-muted">Accounts with Input:</span>
                        <strong class="text-primary" id="modalAccountsCount">0 accounts</strong>
                    </div>
                    <div class="d-flex justify-content-between py-1 border-bottom">
                        <span class="text-muted">Total Debits:</span>
                        <strong class="text-success font-monospace" id="modalDebits">0.00 ETB</strong>
                    </div>
                    <div class="d-flex justify-content-between py-1 border-bottom">
                        <span class="text-muted">Total Credits:</span>
                        <strong class="text-info font-monospace" id="modalCredits">0.00 ETB</strong>
                    </div>
                    <div class="d-flex justify-content-between py-1 border-bottom">
                        <span class="text-muted">Reconciliation Equity:</span>
                        <strong class="text-dark font-monospace" id="modalEquityAcc">3151 Beginning Balance Equity</strong>
                    </div>
                    <div class="d-flex justify-content-between py-1">
                        <span class="text-muted">Post to General Ledger:</span>
                        <strong class="text-success" id="modalPostLedger">Yes (Automatic Journal Entry)</strong>
                    </div>
                </div>

                <div class="alert alert-warning border-0 small mb-0">
                    <i class="fa-solid fa-circle-exclamation me-1"></i>
                    This action will update account balances and post a formal General Ledger baseline journal entry. Please confirm you want to proceed.
                </div>
            </div>
            <div class="modal-footer bg-light py-2">
                <button type="button" class="btn btn-sm btn-secondary rounded-pill px-3" data-bs-dismiss="modal">Go Back &amp; Review</button>
                <button type="button" class="btn btn-sm btn-success fw-bold rounded-pill px-4" onclick="document.getElementById('initialBalancesForm').submit();">
                    <i class="fa-solid fa-check me-1"></i>Confirm &amp; Execute Setup
                </button>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
let currentMode = 'set';

function updateModeUI() {
    currentMode = document.querySelector('input[name="mode"]:checked').value;
    
    // Update card styling
    if (currentMode === 'set') {
        document.getElementById('modeCardSet').classList.add('active', 'border-primary', 'bg-primary-subtle');
        document.getElementById('modeCardAdd').classList.remove('active', 'border-primary', 'bg-primary-subtle');
        document.getElementById('colHeaderInput').innerText = 'Target Balance (ETB)';
    } else {
        document.getElementById('modeCardAdd').classList.add('active', 'border-primary', 'bg-primary-subtle');
        document.getElementById('modeCardSet').classList.remove('active', 'border-primary', 'bg-primary-subtle');
        document.getElementById('colHeaderInput').innerText = 'Adjustment (+ / -) (ETB)';
    }

    calculateLiveTotals();
}

function calculateLiveTotals() {
    let totalDebits = 0;
    let totalCredits = 0;
    let inputsCount = 0;

    const inputs = document.querySelectorAll('.balance-input');
    inputs.forEach(input => {
        const accId = input.dataset.accId;
        const type = input.dataset.type;
        const currentBal = parseFloat(input.dataset.current) || 0;
        const rawVal = input.value.trim();
        const inputVal = parseFloat(rawVal);
        const previewCell = document.getElementById('preview-' + accId);

        let finalBal = currentBal;
        if (!isNaN(inputVal)) {
            inputsCount++;
            if (currentMode === 'set') {
                finalBal = inputVal;
            } else {
                finalBal = currentBal + inputVal;
            }
        }

        // Update preview cell
        if (previewCell) {
            previewCell.innerText = formatNumber(finalBal);
            if (finalBal < 0) {
                previewCell.classList.add('text-danger');
                previewCell.classList.remove('text-dark', 'text-success');
            } else {
                previewCell.classList.remove('text-danger');
            }
        }

        // Calculate debit/credit totals based on final balance
        const isDebitNormal = (type === 'asset' || type === 'expense');
        if (isDebitNormal) {
            if (finalBal > 0) {
                totalDebits += finalBal;
            } else if (finalBal < 0) {
                totalCredits += Math.abs(finalBal);
            }
        } else {
            // Liability, Equity, Revenue normal credit
            if (finalBal > 0) {
                totalCredits += finalBal;
            } else if (finalBal < 0) {
                totalDebits += Math.abs(finalBal);
            }
        }
    });

    const variance = totalDebits - totalCredits;

    document.getElementById('liveTotalDebits').innerText = formatNumber(totalDebits) + ' ETB';
    document.getElementById('liveTotalCredits').innerText = formatNumber(totalCredits) + ' ETB';
    document.getElementById('liveNetVariance').innerText = formatNumber(Math.abs(variance)) + ' ETB';

    const badge = document.getElementById('liveEquilibriumBadge');
    if (Math.abs(variance) < 0.01) {
        badge.className = 'badge bg-success rounded-pill px-3 py-2 fw-semibold';
        badge.innerHTML = '<i class="fa-solid fa-scale-balanced me-1"></i>In Balance (Diff: 0.00 ETB)';
    } else {
        badge.className = 'badge bg-warning text-dark rounded-pill px-3 py-2 fw-semibold';
        badge.innerHTML = '<i class="fa-solid fa-triangle-exclamation me-1"></i>Diff: ' + formatNumber(Math.abs(variance)) + ' ETB (Auto-balanced to Equity)';
    }

    return { totalDebits, totalCredits, variance, inputsCount };
}

function copyCurrentBalances() {
    document.getElementById('modeSet').checked = true;
    updateModeUI();

    document.querySelectorAll('.balance-input').forEach(input => {
        const cur = parseFloat(input.dataset.current) || 0;
        input.value = cur.toFixed(2);
    });

    calculateLiveTotals();
}

function fillZeros() {
    document.querySelectorAll('.balance-input').forEach(input => {
        if (!input.value.trim()) {
            input.value = '0.00';
        }
    });
    calculateLiveTotals();
}

function clearAllInputs() {
    document.querySelectorAll('.balance-input').forEach(input => {
        input.value = '';
    });
    calculateLiveTotals();
}

function resetRow(accId) {
    const input = document.querySelector('.balance-input[data-acc-id="' + accId + '"]');
    if (input) {
        input.value = '';
        calculateLiveTotals();
    }
}

function filterTable() {
    const query = document.getElementById('tableSearchInput').value.toLowerCase().trim();
    const rows = document.querySelectorAll('.account-row');
    let visible = 0;

    rows.forEach(row => {
        const code = row.dataset.code || '';
        const name = row.dataset.name || '';
        const activeCategory = document.querySelector('.filter-btn.active').dataset.filter;
        const matchesCategory = (activeCategory === 'all' || row.dataset.type === activeCategory);
        const matchesQuery = (!query || code.includes(query) || name.includes(query));

        if (matchesCategory && matchesQuery) {
            row.style.display = '';
            visible++;
        } else {
            row.style.display = 'none';
        }
    });

    document.getElementById('visibleRowCount').innerText = 'Showing ' + visible + ' accounts';
}

function applyCategoryFilter(cat, btn) {
    document.querySelectorAll('.filter-btn').forEach(b => {
        b.classList.remove('active', 'btn-primary', 'btn-danger', 'btn-success', 'btn-info', 'btn-warning');
        if (!b.classList.contains('btn-outline-primary') && !b.classList.contains('btn-outline-danger') && !b.classList.contains('btn-outline-success') && !b.classList.contains('btn-outline-info') && !b.classList.contains('btn-outline-warning')) {
            b.classList.add('btn-outline-primary');
        }
    });

    btn.classList.add('active');
    if (cat === 'all') btn.className = 'btn btn-sm btn-primary rounded-pill px-3 py-1 active filter-btn';
    else if (cat === 'asset') btn.className = 'btn btn-sm btn-primary rounded-pill px-3 py-1 active filter-btn';
    else if (cat === 'liability') btn.className = 'btn btn-sm btn-danger rounded-pill px-3 py-1 active filter-btn';
    else if (cat === 'equity') btn.className = 'btn btn-sm btn-success rounded-pill px-3 py-1 active filter-btn';
    else if (cat === 'revenue') btn.className = 'btn btn-sm btn-info rounded-pill px-3 py-1 active filter-btn';
    else if (cat === 'expense') btn.className = 'btn btn-sm btn-warning rounded-pill px-3 py-1 active filter-btn';

    filterTable();
}

function confirmAndSubmit() {
    const totals = calculateLiveTotals();
    
    document.getElementById('modalModeLabel').innerText = (currentMode === 'set') ? 'Set New Baseline Balance' : 'Add / Adjust to Existing Balance';
    document.getElementById('modalAccountsCount').innerText = totals.inputsCount + ' accounts';
    document.getElementById('modalDebits').innerText = formatNumber(totals.totalDebits) + ' ETB';
    document.getElementById('modalCredits').innerText = formatNumber(totals.totalCredits) + ' ETB';

    const eqSelect = document.getElementById('balancingEquitySelect');
    document.getElementById('modalEquityAcc').innerText = eqSelect.options[eqSelect.selectedIndex].text;

    const postLedger = document.getElementById('postToLedger').checked;
    document.getElementById('modalPostLedger').innerText = postLedger ? 'Yes (Automatic Journal Entry)' : 'No (Update Balances Only)';

    const modal = new bootstrap.Modal(document.getElementById('confirmSubmitModal'));
    modal.show();
}

function formatNumber(num) {
    return (num || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

document.addEventListener('DOMContentLoaded', function() {
    updateModeUI();
});
</script>

<style>
.cursor-pointer { cursor: pointer; }
.mode-option-card { transition: all 0.2s ease; }
.mode-option-card:hover { border-color: #3b82f6 !important; }
.mode-option-card.active { border-color: #3b82f6 !important; background-color: #eff6ff !important; }
</style>
@endpush
@endsection
