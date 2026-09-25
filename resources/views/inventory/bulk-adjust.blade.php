@extends('layouts.app')

@section('title', 'Manual Stock Adjustment')

@section('content')
<style>
/* ── Page Shell ─────────────────────────────────────────────── */
.adj-header {
    background: linear-gradient(135deg, #1e3a5f 0%, #2d6a9f 100%);
    border-radius: 14px; padding: 22px 28px; color: white; margin-bottom: 22px;
}
.adj-header h1 { font-size: 1.45rem; font-weight: 700; margin: 0; }
.adj-header p  { margin: 3px 0 0; opacity: .78; font-size: .88rem; }

/* ── Filter bar ─────────────────────────────────────────────── */
.filter-card { border-radius: 12px; border: 1px solid #e8edf3; background: #fff; padding: 14px 18px; margin-bottom: 16px; }
.filter-label { font-size: .72rem; text-transform: uppercase; letter-spacing: .6px; color: #64748b; font-weight: 700; margin-bottom: 4px; }
.search-wrap { position: relative; }
.search-wrap .si { position: absolute; left: 10px; top: 50%; transform: translateY(-50%); color: #94a3b8; pointer-events: none; }
.search-bar  {
    border: 1.5px solid #e2e8f0; border-radius: 8px;
    padding: 8px 12px 8px 32px; font-size: .88rem; width: 100%; background: #fff;
    transition: border-color .18s;
}
.search-bar:focus { outline: none; border-color: #2d6a9f; box-shadow: 0 0 0 3px rgba(45,106,159,.1); }

/* ── Table ──────────────────────────────────────────────────── */
.adj-table { border-collapse: separate; border-spacing: 0; }
.adj-table thead th {
    font-size: .72rem; text-transform: uppercase; letter-spacing: .55px;
    color: #64748b; font-weight: 700; background: #f8fafc;
    border-bottom: 2px solid #e2e8f0; padding: 11px 12px;
    position: sticky; top: 0; z-index: 2;
}
.adj-table tbody td { vertical-align: middle; padding: 10px 12px; border-bottom: 1px solid #f1f5f9; }
.adj-table tbody tr:hover td { background: #f8fbff; }
.adj-table tbody tr.saved-row td { background: #f0fdf4 !important; }

/* ── Inputs ─────────────────────────────────────────────────── */
.qty-input, .cost-input {
    border: 1.5px solid #e2e8f0; border-radius: 8px;
    padding: 7px 10px; font-size: .88rem; text-align: right;
    width: 108px; transition: border-color .18s, background .18s; background: #fff;
}
.qty-input  { font-weight: 600; color: #1e3a5f; }
.cost-input { color: #475569; }
.qty-input:focus, .cost-input:focus { outline: none; border-color: #2d6a9f; box-shadow: 0 0 0 3px rgba(45,106,159,.1); }
.qty-input.changed  { border-color: #f59e0b; background: #fffbeb; }

/* ── Badges ─────────────────────────────────────────────────── */
.current-badge { display: inline-block; background: #f1f5f9; color: #475569; border-radius: 20px; padding: 3px 10px; font-size: .78rem; font-weight: 600; }
.delta-badge   { font-size: .78rem; font-weight: 700; }
.delta-pos  { color: #16a34a; }
.delta-neg  { color: #dc2626; }
.delta-zero { color: #94a3b8; }
.cat-dot { width: 8px; height: 8px; border-radius: 50%; display: inline-block; margin-right: 4px; }
.cat-consumable { background: #3b82f6; }
.cat-fixed      { background: #f59e0b; }

/* ── Per-row Save button ────────────────────────────────────── */
.btn-row-save {
    background: linear-gradient(135deg, #16a34a, #15803d);
    color: #fff; border: none; border-radius: 8px;
    padding: 6px 14px; font-size: .78rem; font-weight: 700;
    cursor: pointer; transition: opacity .15s, transform .1s;
    white-space: nowrap; display: inline-flex; align-items: center; gap: 5px;
    opacity: .45; pointer-events: none;          /* disabled until changed */
}
.btn-row-save.active { opacity: 1; pointer-events: auto; }
.btn-row-save.active:hover { transform: translateY(-1px); box-shadow: 0 4px 12px rgba(22,163,74,.3); }
.btn-row-save.saving { background: #64748b; opacity: .7; pointer-events: none; }
.btn-row-save.done   { background: linear-gradient(135deg, #0891b2, #0e7490); opacity: 1; pointer-events: none; }

/* ── Status icon ────────────────────────────────────────────── */
.row-status { font-size: .75rem; font-weight: 600; }
.row-status.ok  { color: #16a34a; }
.row-status.err { color: #dc2626; }

/* ── Summary footer ─────────────────────────────────────────── */
.adj-footer {
    position: sticky; bottom: 0; background: #fff;
    border-top: 2px solid #e2e8f0; padding: 13px 20px;
    display: flex; align-items: center; justify-content: space-between;
    z-index: 10;
}
.count-pill { background: #dbeafe; color: #1e40af; border-radius: 20px; padding: 4px 12px; font-size: .8rem; font-weight: 700; }
.saved-pill { background: #dcfce7; color: #166534; border-radius: 20px; padding: 4px 12px; font-size: .8rem; font-weight: 700; }

.hidden-row { display: none; }
</style>

@php
    $backRoute = auth()->user() && auth()->user()->hasAnyRole(['store_manager', 'store_keeper']) && Route::has('store-manager.inventory.all')
        ? route('store-manager.inventory.all')
        : route('inventory.index');
@endphp

{{-- ── Header ──────────────────────────────────────────────── --}}
<div class="adj-header">
    <div class="d-flex align-items-start justify-content-between flex-wrap gap-3">
        <div>
            <h1><i class="fa-solid fa-warehouse me-2"></i>Manual Stock Adjustment</h1>
            <p>Enter the actual quantity for any product and click <strong>Save</strong> on that row — changes are applied immediately, one product at a time.</p>
        </div>
        <a href="{{ $backRoute }}" class="btn btn-light btn-sm px-3 align-self-start">
            <i class="fa-solid fa-arrow-left me-1"></i> Back
        </a>
    </div>
</div>

{{-- ── Alerts ───────────────────────────────────────────────── --}}
@if(session('success'))
<div class="alert alert-success border-0 rounded-3 mb-3 d-flex align-items-center gap-2">
    <i class="fa-solid fa-circle-check"></i> {{ session('success') }}
</div>
@endif

{{-- ── Filter bar ───────────────────────────────────────────── --}}
<div class="filter-card">
    <div class="row g-3 align-items-end">
        {{-- Store --}}
        <div class="col-12 col-md-3">
            <div class="filter-label">Store</div>
            <select id="storeSelect" class="form-select form-select-sm">
                @foreach($stores as $s)
                <option value="{{ $s->id }}" {{ $s->id == $storeId ? 'selected' : '' }}>{{ $s->name }}</option>
                @endforeach
            </select>
        </div>

        {{-- Search --}}
        <div class="col-12 col-md-4">
            <div class="filter-label">Search Product</div>
            <div class="search-wrap">
                <i class="fa-solid fa-magnifying-glass si"></i>
                <input type="text" id="productSearch" class="search-bar" placeholder="Name or SKU…">
            </div>
        </div>

        {{-- Category --}}
        <div class="col-12 col-md-2">
            <div class="filter-label">Category</div>
            <select id="catFilter" class="form-select form-select-sm">
                <option value="">All</option>
                <option value="Consumable">Consumable</option>
                <option value="Fixed Asset">Fixed Asset</option>
            </select>
        </div>

        {{-- Toggle --}}
        <div class="col-12 col-md-3 d-flex align-items-end">
            <label class="form-check d-flex align-items-center gap-2 mb-0" style="cursor:pointer;">
                <input class="form-check-input" type="checkbox" id="showChangedOnly">
                <span class="form-check-label" style="font-size:.85rem;font-weight:600;">Show changed only</span>
            </label>
        </div>
    </div>
</div>

{{-- ── Table ─────────────────────────────────────────────────── --}}
<div class="card border-0 shadow-sm" style="border-radius:14px; overflow:hidden;">
    <div class="table-responsive" style="max-height: calc(100vh - 320px); overflow-y: auto;">
        <table class="table adj-table mb-0" id="adjTable">
            <thead>
                <tr>
                    <th style="width:36px;">#</th>
                    <th>Product</th>
                    <th>SKU</th>
                    <th>Category</th>
                    <th class="text-end">Current Stock</th>
                    <th class="text-center">New Qty <span class="text-primary">*</span></th>
                    <th class="text-center">Unit Cost</th>
                    <th class="text-center">Δ Change</th>
                    <th class="text-center" style="width:110px;">Action</th>
                </tr>
            </thead>
            <tbody id="tableBody">
                @foreach($products as $idx => $product)
                @php
                    $inv        = $existingStock[$product->id] ?? null;
                    $currentQty = $inv ? (float)$inv->quantity_on_hand : 0;
                    $currentCost= $inv ? (float)$inv->unit_cost : 0;
                    $catClass   = strtolower($product->category ?? '') === 'fixed asset' ? 'cat-fixed' : 'cat-consumable';
                @endphp
                <tr class="product-row"
                    id="row_{{ $product->id }}"
                    data-product-id="{{ $product->id }}"
                    data-name="{{ strtolower($product->name) }}"
                    data-sku="{{ strtolower($product->sku ?? $product->code ?? '') }}"
                    data-cat="{{ $product->category ?? '' }}"
                    data-original="{{ $currentQty }}">

                    <td class="text-muted" style="font-size:.78rem;">{{ $idx + 1 }}</td>

                    <td>
                        <div class="fw-semibold" style="font-size:.875rem;">{{ $product->name }}</div>
                        @if(!empty($product->sub_category))
                        <div class="text-muted" style="font-size:.74rem;">{{ $product->sub_category }}</div>
                        @endif
                    </td>

                    <td><code style="font-size:.76rem;color:#64748b;">{{ $product->sku ?? $product->code }}</code></td>

                    <td>
                        <span class="cat-dot {{ $catClass }}"></span>
                        <span style="font-size:.8rem;">{{ $product->category ?? '—' }}</span>
                    </td>

                    <td class="text-end">
                        <span class="current-badge" id="currentBadge_{{ $product->id }}">
                            {{ number_format($currentQty, 2) }} {{ $product->unit }}
                        </span>
                    </td>

                    <td class="text-center">
                        <input type="number"
                               class="qty-input"
                               id="qty_{{ $product->id }}"
                               placeholder="{{ number_format($currentQty, 3, '.', '') }}"
                               step="0.001" min="0"
                               data-original="{{ $currentQty }}"
                               oninput="onQtyChange({{ $product->id }}, {{ $currentQty }})">
                    </td>

                    <td class="text-center">
                        <input type="number"
                               class="cost-input"
                               id="cost_{{ $product->id }}"
                               value="{{ $currentCost > 0 ? number_format($currentCost, 2, '.', '') : '' }}"
                               placeholder="0.00"
                               step="0.01" min="0">
                    </td>

                    <td class="text-center">
                        <span class="delta-badge delta-zero" id="delta_{{ $product->id }}">—</span>
                    </td>

                    <td class="text-center">
                        <div class="d-flex flex-column align-items-center gap-1">
                            <button type="button"
                                    class="btn-row-save"
                                    id="saveBtn_{{ $product->id }}"
                                    onclick="saveRow({{ $product->id }})">
                                <i class="fa-solid fa-floppy-disk"></i> Save
                            </button>
                            <span class="row-status" id="status_{{ $product->id }}"></span>
                        </div>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    {{-- Sticky footer summary --}}
    <div class="adj-footer">
        <div class="d-flex align-items-center gap-3">
            <span class="count-pill" id="changedPill">0 pending</span>
            <span class="saved-pill" id="savedPill">0 saved</span>
            <span class="text-muted" style="font-size:.8rem;">Click the green Save button on each row to apply</span>
        </div>
        <a href="{{ $backRoute }}" class="btn btn-outline-secondary btn-sm px-4">
            Done
        </a>
    </div>
</div>

@push('scripts')
<script>
// ── Config ─────────────────────────────────────────────────────
const STORE_ID    = {{ $storeId ?? 'null' }};
const SAVE_URL    = '{{ route("inventory.save-single") }}';
const CSRF_TOKEN  = document.querySelector('meta[name="csrf-token"]')?.content || '{{ csrf_token() }}';

let pendingCount = 0;
let savedCount   = 0;

// ── Store switcher ──────────────────────────────────────────────
document.getElementById('storeSelect').addEventListener('change', function () {
    window.location.href = '{{ route("inventory.bulk-adjust") }}?store_id=' + this.value;
});

// ── Qty change ──────────────────────────────────────────────────
function onQtyChange(productId, original) {
    const input   = document.getElementById('qty_' + productId);
    const deltaEl = document.getElementById('delta_' + productId);
    const saveBtn = document.getElementById('saveBtn_' + productId);
    const row     = document.getElementById('row_' + productId);

    const val     = input.value;
    const newQty  = parseFloat(val);

    if (val === '' || isNaN(newQty)) {
        input.classList.remove('changed');
        deltaEl.textContent = '—';
        deltaEl.className   = 'delta-badge delta-zero';
        setBtnState(saveBtn, 'disabled');
        row.dataset.changed = '0';
        updateCounts();
        return;
    }

    const diff = newQty - original;

    if (Math.abs(diff) < 0.0005) {
        input.classList.remove('changed');
        deltaEl.textContent = '—';
        deltaEl.className   = 'delta-badge delta-zero';
        setBtnState(saveBtn, 'disabled');
        row.dataset.changed = '0';
    } else {
        input.classList.add('changed');
        const sign = diff > 0 ? '+' : '';
        deltaEl.textContent = sign + diff.toFixed(3);
        deltaEl.className   = 'delta-badge ' + (diff > 0 ? 'delta-pos' : 'delta-neg');
        setBtnState(saveBtn, 'active');
        row.dataset.changed = '1';
    }

    updateCounts();
}

// ── Per-row AJAX save ───────────────────────────────────────────
function saveRow(productId) {
    if (!STORE_ID) {
        alert('No store selected. Please pick a store first.');
        return;
    }

    const saveBtn  = document.getElementById('saveBtn_'  + productId);
    const statusEl = document.getElementById('status_'   + productId);
    const qtyInput = document.getElementById('qty_'      + productId);
    const costInput = document.getElementById('cost_'    + productId);
    const row      = document.getElementById('row_'      + productId);

    const qtyVal = qtyInput.value.trim();
    const qty    = parseFloat(qtyVal);

    if (qtyVal === '' || isNaN(qty) || qty < 0) {
        statusEl.textContent = '⚠ Enter a valid quantity';
        statusEl.className   = 'row-status err';
        qtyInput.focus();
        return;
    }

    const costVal = costInput.value.trim();
    const cost    = costVal !== '' ? parseFloat(costVal) : null;

    // — switch to saving state —
    setBtnState(saveBtn, 'saving');
    statusEl.textContent = '';
    statusEl.className   = 'row-status';

    // — send JSON payload —
    const payload = {
        store_id   : STORE_ID,
        product_id : productId,
        quantity   : qty,
    };
    if (cost !== null && !isNaN(cost)) {
        payload.unit_cost = cost;
    }

    fetch(SAVE_URL, {
        method  : 'POST',
        headers : {
            'Content-Type'     : 'application/json',
            'Accept'           : 'application/json',
            'X-CSRF-TOKEN'     : CSRF_TOKEN,
            'X-Requested-With' : 'XMLHttpRequest',
        },
        body : JSON.stringify(payload),
    })
    .then(async res => {
        const data = await res.json().catch(() => ({ success: false, message: 'Invalid server response' }));
        if (!res.ok || !data.success) {
            // Laravel validation returns 422 with { errors: {...} }
            if (data.errors) {
                const msgs = Object.values(data.errors).flat().join('; ');
                throw new Error(msgs);
            }
            throw new Error(data.message || ('HTTP ' + res.status));
        }
        return data;
    })
    .then(data => {
        // ✅ Success
        setBtnState(saveBtn, 'done');
        saveBtn.innerHTML = '<i class="fa-solid fa-check"></i> Saved';

        statusEl.textContent = '✓ Saved';
        statusEl.className   = 'row-status ok';

        // update the "Current Stock" badge with new value
        const badge = document.getElementById('currentBadge_' + productId);
        if (badge) {
            const unit = badge.textContent.trim().split(' ').slice(-1)[0];
            badge.textContent = parseFloat(data.new_qty).toFixed(2) + ' ' + unit;
        }

        // reset input styling
        qtyInput.classList.remove('changed');
        qtyInput.dataset.original = qty;
        const deltaEl = document.getElementById('delta_' + productId);
        if (deltaEl) { deltaEl.textContent = '—'; deltaEl.className = 'delta-badge delta-zero'; }

        row.classList.add('saved-row');
        row.dataset.changed = '0';
        savedCount++;
        updateCounts();

        // after 3 s, re-enable so they can adjust again
        setTimeout(() => {
            setBtnState(saveBtn, 'disabled');
            saveBtn.innerHTML = '<i class="fa-solid fa-floppy-disk"></i> Save';
            row.classList.remove('saved-row');
            statusEl.textContent = '';
        }, 3000);
    })
    .catch(err => {
        // ❌ Error — re-enable button and show message
        setBtnState(saveBtn, 'active');
        statusEl.textContent = '✗ ' + err.message;
        statusEl.className   = 'row-status err';
    });
}

function setBtnState(btn, state) {
    btn.className = 'btn-row-save';
    btn.disabled  = false;

    if (state === 'active') {
        btn.classList.add('active');
        btn.innerHTML = '<i class="fa-solid fa-floppy-disk"></i> Save';
    }
    if (state === 'saving') {
        btn.classList.add('saving');
        btn.disabled  = true;
        btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Saving…';
    }
    if (state === 'done') {
        btn.classList.add('done');
        btn.disabled  = true;
        // innerHTML set by caller after this
    }
    if (state === 'disabled') {
        btn.disabled  = true;
        btn.innerHTML = '<i class="fa-solid fa-floppy-disk"></i> Save';
    }
}

function updateCounts() {
    const pending = document.querySelectorAll('.product-row[data-changed="1"]').length;
    document.getElementById('changedPill').textContent = pending  + ' pending';
    document.getElementById('savedPill').textContent   = savedCount + ' saved';
}

// ── Live filter ─────────────────────────────────────────────────
function filterRows() {
    const search      = document.getElementById('productSearch').value.toLowerCase();
    const cat         = document.getElementById('catFilter').value.toLowerCase();
    const showChanged = document.getElementById('showChangedOnly').checked;

    document.querySelectorAll('.product-row').forEach(row => {
        const name      = row.dataset.name || '';
        const sku       = row.dataset.sku  || '';
        const rowCat    = row.dataset.cat  || '';
        const isChanged = row.dataset.changed === '1';

        const ok = (name.includes(search) || sku.includes(search))
                && (!cat         || rowCat.toLowerCase() === cat)
                && (!showChanged || isChanged);

        row.classList.toggle('hidden-row', !ok);
    });
}

document.getElementById('productSearch').addEventListener('input', filterRows);
document.getElementById('catFilter').addEventListener('change', filterRows);
document.getElementById('showChangedOnly').addEventListener('change', filterRows);
</script>
@endpush

@endsection
