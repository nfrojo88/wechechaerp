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
    cursor: pointer; transition: all .18s ease;
    white-space: nowrap; display: inline-flex; align-items: center; gap: 5px;
    box-shadow: 0 1px 3px rgba(0,0,0,.08);
}
.btn-row-save:hover:not(:disabled) {
    background: linear-gradient(135deg, #15803d, #166534);
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(22,163,74,.32);
}
.btn-row-save.pulse-ready {
    background: linear-gradient(135deg, #059669, #10b981);
    box-shadow: 0 0 0 3px rgba(16,185,129,.35);
    animation: btnPulse 1.6s infinite;
}
@keyframes btnPulse {
    0% { box-shadow: 0 0 0 0 rgba(16,185,129,.6); }
    70% { box-shadow: 0 0 0 7px rgba(16,185,129,0); }
    100% { box-shadow: 0 0 0 0 rgba(16,185,129,0); }
}
.btn-row-save.saving {
    background: #64748b !important;
    opacity: .85;
    cursor: wait !important;
    transform: none !important;
}
.btn-row-save.done {
    background: linear-gradient(135deg, #0891b2, #0e7490) !important;
    opacity: 1;
    cursor: default;
    transform: none !important;
}

/* ── Status icon ────────────────────────────────────────────── */
.row-status { font-size: .75rem; font-weight: 600; min-height: 16px; }
.row-status.ok  { color: #16a34a; }
.row-status.err { color: #dc2626; }

/* ── Toast animation ────────────────────────────────────────── */
@keyframes toastIn {
    from { opacity: 0; transform: translateY(-12px); }
    to { opacity: 1; transform: translateY(0); }
}

/* ── Summary footer ─────────────────────────────────────────── */
.adj-footer {
    position: sticky; bottom: 0; background: #fff;
    border-top: 2px solid #e2e8f0; padding: 13px 20px;
    display: flex; align-items: center; justify-content: space-between;
    z-index: 10; box-shadow: 0 -4px 16px rgba(0,0,0,.04);
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
                               value="{{ number_format($currentQty, 3, '.', '') }}"
                               placeholder="{{ number_format($currentQty, 3, '.', '') }}"
                               step="0.001" min="0"
                               data-original="{{ $currentQty }}"
                               oninput="onFieldChange({{ $product->id }})"
                               onkeydown="if(event.key === 'Enter') saveRow({{ $product->id }})">
                    </td>

                    <td class="text-center">
                        <input type="number"
                               class="cost-input"
                               id="cost_{{ $product->id }}"
                               value="{{ $currentCost > 0 ? number_format($currentCost, 2, '.', '') : '' }}"
                               placeholder="0.00"
                               data-original-cost="{{ $currentCost > 0 ? number_format($currentCost, 2, '.', '') : '' }}"
                               step="0.01" min="0"
                               oninput="onFieldChange({{ $product->id }})"
                               onkeydown="if(event.key === 'Enter') saveRow({{ $product->id }})">
                    </td>

                    <td class="text-center">
                        <span class="delta-badge delta-zero" id="delta_{{ $product->id }}">—</span>
                    </td>

                    <td class="text-center">
                        <div class="d-flex flex-column align-items-center gap-1">
                            <button type="button"
                                    class="btn-row-save"
                                    id="saveBtn_{{ $product->id }}"
                                    onclick="saveRow({{ $product->id }})"
                                    title="Save stock adjustment for this item">
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
        <div class="d-flex align-items-center gap-3 flex-wrap">
            <span class="count-pill" id="changedPill">0 pending</span>
            <span class="saved-pill" id="savedPill">0 saved</span>
            <button type="button"
                    class="btn btn-success btn-sm px-3 fw-bold shadow-sm"
                    id="saveAllBtn"
                    onclick="saveAllChanged()"
                    style="display: none;">
                <i class="fa-solid fa-floppy-disk me-1"></i> Save All Pending (<span id="saveAllCount">0</span>)
            </button>
            <span class="text-muted d-none d-md-inline" style="font-size:.8rem;">
                Click <strong>Save</strong> on any row or press <strong>Enter</strong> in the input box to apply.
            </span>
        </div>
        <a href="{{ $backRoute }}" class="btn btn-outline-secondary btn-sm px-4">
            Done
        </a>
    </div>
</div>

{{-- Toast Feedback Container --}}
<div id="toastContainer" style="position: fixed; top: 24px; right: 24px; z-index: 99999; display: flex; flex-direction: column; gap: 8px; pointer-events: none;"></div>

@push('scripts')
<script>
// ── Config ─────────────────────────────────────────────────────
const STORE_ID    = {{ $storeId ? (int)$storeId : 'null' }};
const SAVE_URL    = '{{ route("inventory.save-single") }}';
const CSRF_TOKEN  = document.querySelector('meta[name="csrf-token"]')?.content || '{{ csrf_token() }}';

let pendingCount = 0;
let savedCount   = 0;

function getActiveStoreId() {
    const sel = document.getElementById('storeSelect');
    if (sel && sel.value) return parseInt(sel.value);
    return STORE_ID;
}

// ── Store switcher ──────────────────────────────────────────────
const storeSelectEl = document.getElementById('storeSelect');
if (storeSelectEl) {
    storeSelectEl.addEventListener('change', function () {
        window.location.href = '{{ route("inventory.bulk-adjust") }}?store_id=' + this.value;
    });
}

// ── Field Change Listener (Qty & Cost) ──────────────────────────
function onFieldChange(productId) {
    const qtyInput  = document.getElementById('qty_' + productId);
    const costInput = document.getElementById('cost_' + productId);
    const deltaEl   = document.getElementById('delta_' + productId);
    const saveBtn   = document.getElementById('saveBtn_' + productId);
    const row       = document.getElementById('row_' + productId);

    if (!qtyInput || !costInput || !deltaEl || !saveBtn || !row) return;

    const origQty   = parseFloat(qtyInput.dataset.original || '0');
    const origCost  = parseFloat(costInput.dataset.originalCost || '0');

    const qtyVal    = qtyInput.value.trim();
    const costVal   = costInput.value.trim();

    const newQty    = qtyVal !== '' ? parseFloat(qtyVal) : origQty;
    const newCost   = costVal !== '' ? parseFloat(costVal) : 0;

    const qtyDiff   = newQty - origQty;
    const costDiff  = newCost - origCost;

    const isQtyChanged  = Math.abs(qtyDiff) >= 0.0005;
    const isCostChanged = Math.abs(costDiff) >= 0.005 && (costVal !== '' || origCost > 0);

    if (isQtyChanged) {
        qtyInput.classList.add('changed');
        const sign = qtyDiff > 0 ? '+' : '';
        deltaEl.textContent = sign + qtyDiff.toFixed(3);
        deltaEl.className   = 'delta-badge ' + (qtyDiff > 0 ? 'delta-pos' : 'delta-neg');
    } else {
        qtyInput.classList.remove('changed');
        if (isCostChanged) {
            deltaEl.textContent = 'Cost changed';
            deltaEl.className   = 'delta-badge delta-pos';
        } else {
            deltaEl.textContent = '—';
            deltaEl.className   = 'delta-badge delta-zero';
        }
    }

    if (isCostChanged) {
        costInput.classList.add('changed');
    } else {
        costInput.classList.remove('changed');
    }

    const hasChanged = isQtyChanged || isCostChanged;
    row.dataset.changed = hasChanged ? '1' : '0';

    if (hasChanged) {
        saveBtn.classList.add('pulse-ready');
    } else {
        saveBtn.classList.remove('pulse-ready');
    }

    updateCounts();
}

// ── Per-row Save Promise ────────────────────────────────────────
function saveRowPromise(productId) {
    return new Promise((resolve, reject) => {
        const storeId = getActiveStoreId();
        if (!storeId) {
            showToast('error', 'No store selected. Please select a store first.');
            reject(new Error('No store selected'));
            return;
        }

        const saveBtn   = document.getElementById('saveBtn_'  + productId);
        const statusEl  = document.getElementById('status_'   + productId);
        const qtyInput  = document.getElementById('qty_'      + productId);
        const costInput = document.getElementById('cost_'     + productId);
        const row       = document.getElementById('row_'      + productId);

        let qtyVal = qtyInput.value.trim();
        if (qtyVal === '') {
            qtyVal = qtyInput.dataset.original || '0';
            qtyInput.value = qtyVal;
        }
        const qty = parseFloat(qtyVal);

        if (isNaN(qty) || qty < 0) {
            if (statusEl) {
                statusEl.textContent = '⚠ Enter a valid quantity';
                statusEl.className   = 'row-status err';
            }
            qtyInput.focus();
            reject(new Error('Enter a valid quantity'));
            return;
        }

        const costVal = costInput.value.trim();
        const cost    = costVal !== '' ? parseFloat(costVal) : null;

        // — switch to saving state —
        setBtnState(saveBtn, 'saving');
        if (statusEl) {
            statusEl.textContent = '';
            statusEl.className   = 'row-status';
        }

        const payload = {
            store_id   : storeId,
            product_id : productId,
            quantity   : qty,
        };
        if (cost !== null && !isNaN(cost) && cost >= 0) {
            payload.unit_cost = cost;
        }

        const controller = new AbortController();
        const timeoutId = setTimeout(() => controller.abort(), 15000);

        fetch(SAVE_URL, {
            method  : 'POST',
            signal  : controller.signal,
            headers : {
                'Content-Type'     : 'application/json',
                'Accept'           : 'application/json',
                'X-CSRF-TOKEN'     : CSRF_TOKEN,
                'X-Requested-With' : 'XMLHttpRequest',
            },
            body : JSON.stringify(payload),
        })
        .then(async res => {
            clearTimeout(timeoutId);
            const data = await res.json().catch(() => ({ success: false, message: 'Invalid response from server' }));
            if (!res.ok || !data.success) {
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
            if (statusEl) {
                statusEl.textContent = '✓ Saved';
                statusEl.className   = 'row-status ok';
            }

            // update the "Current Stock" badge with new value
            const badge = document.getElementById('currentBadge_' + productId);
            if (badge) {
                const parts = badge.textContent.trim().split(' ');
                const unit = parts.length > 1 ? parts.slice(-1)[0] : (data.unit || '');
                badge.textContent = parseFloat(data.new_qty).toFixed(2) + (unit ? ' ' + unit : '');
            }

            // reset inputs and state
            qtyInput.classList.remove('changed');
            costInput.classList.remove('changed');
            qtyInput.dataset.original = data.new_qty;
            if (data.unit_cost !== undefined && data.unit_cost !== null) {
                costInput.dataset.originalCost = parseFloat(data.unit_cost).toFixed(2);
            }

            const deltaEl = document.getElementById('delta_' + productId);
            if (deltaEl) {
                deltaEl.textContent = '—';
                deltaEl.className   = 'delta-badge delta-zero';
            }

            row.classList.add('saved-row');
            row.dataset.changed = '0';
            saveBtn.classList.remove('pulse-ready');
            savedCount++;
            updateCounts();

            showToast('success', `${data.product_name || 'Product'} updated: ${parseFloat(data.new_qty).toFixed(2)} ${data.unit || ''}`);

            setTimeout(() => {
                setBtnState(saveBtn, 'normal');
                row.classList.remove('saved-row');
                if (statusEl) statusEl.textContent = '';
            }, 2500);

            resolve(data);
        })
        .catch(err => {
            clearTimeout(timeoutId);
            setBtnState(saveBtn, 'normal');
            const msg = err.name === 'AbortError' ? 'Server took too long to respond. Please try again.' : err.message;
            if (statusEl) {
                statusEl.textContent = '✗ ' + msg;
                statusEl.className   = 'row-status err';
            }
            showToast('error', 'Error: ' + msg);
            reject(err);
        });
    });
}

function saveRow(productId) {
    saveRowPromise(productId).catch(() => {});
}

// ── Save All Changed ────────────────────────────────────────────
async function saveAllChanged() {
    const changedRows = Array.from(document.querySelectorAll('.product-row[data-changed="1"]'));
    if (changedRows.length === 0) {
        showToast('info', 'No pending changes to save.');
        return;
    }

    const btn = document.getElementById('saveAllBtn');
    btn.disabled = true;
    btn.innerHTML = `<i class="fa-solid fa-spinner fa-spin me-1"></i> Saving (${changedRows.length})…`;

    let successCount = 0;
    let failCount = 0;

    for (const row of changedRows) {
        const productId = row.dataset.productId;
        try {
            await saveRowPromise(productId);
            successCount++;
        } catch (e) {
            failCount++;
        }
    }

    btn.disabled = false;
    updateCounts();

    if (successCount > 0 && failCount === 0) {
        showToast('success', `All ${successCount} product adjustments saved successfully!`);
    } else if (failCount > 0) {
        showToast('error', `${failCount} item(s) encountered an error during save.`);
    }
}

function setBtnState(btn, state) {
    if (!btn) return;
    btn.className = 'btn-row-save';
    btn.disabled  = false;

    if (state === 'saving') {
        btn.classList.add('saving');
        btn.disabled  = true;
        btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Saving…';
    } else if (state === 'done') {
        btn.classList.add('done');
        btn.disabled  = true;
        btn.innerHTML = '<i class="fa-solid fa-check"></i> Saved';
    } else {
        btn.innerHTML = '<i class="fa-solid fa-floppy-disk"></i> Save';
    }
}

function updateCounts() {
    const pending = document.querySelectorAll('.product-row[data-changed="1"]').length;
    const changedPill = document.getElementById('changedPill');
    const savedPill   = document.getElementById('savedPill');
    const saveAllBtn  = document.getElementById('saveAllBtn');
    const saveAllCount= document.getElementById('saveAllCount');

    if (changedPill) changedPill.textContent = pending + ' pending';
    if (savedPill)   savedPill.textContent   = savedCount + ' saved';

    if (saveAllBtn && saveAllCount) {
        saveAllCount.textContent = pending;
        saveAllBtn.style.display = pending > 0 ? 'inline-flex' : 'none';
    }
}

// ── Toast Notification ──────────────────────────────────────────
function showToast(type, message) {
    const container = document.getElementById('toastContainer');
    if (!container) return;

    const toast = document.createElement('div');
    const isSuccess = type === 'success';
    const isError   = type === 'error';
    const bg   = isSuccess ? '#10b981' : (isError ? '#ef4444' : '#2563eb');
    const icon = isSuccess ? 'fa-circle-check' : (isError ? 'fa-circle-exclamation' : 'fa-circle-info');

    toast.style.cssText = `
        background: ${bg};
        color: #fff;
        padding: 10px 16px;
        border-radius: 9px;
        font-size: .85rem;
        font-weight: 600;
        box-shadow: 0 8px 24px rgba(0,0,0,.18);
        display: flex;
        align-items: center;
        gap: 8px;
        pointer-events: auto;
        animation: toastIn .22s ease-out;
        max-width: 380px;
    `;
    toast.innerHTML = `<i class="fa-solid ${icon}"></i> <span>${message}</span>`;
    container.appendChild(toast);

    setTimeout(() => {
        toast.style.opacity = '0';
        toast.style.transform = 'translateY(-10px)';
        toast.style.transition = 'opacity .3s ease, transform .3s ease';
        setTimeout(() => toast.remove(), 300);
    }, 3200);
}

// ── Live filter ─────────────────────────────────────────────────
function filterRows() {
    const search      = (document.getElementById('productSearch')?.value || '').toLowerCase().trim();
    const cat         = (document.getElementById('catFilter')?.value || '').toLowerCase().trim();
    const showChanged = document.getElementById('showChangedOnly')?.checked || false;

    document.querySelectorAll('.product-row').forEach(row => {
        const name      = (row.dataset.name || '').toLowerCase();
        const sku       = (row.dataset.sku  || '').toLowerCase();
        const rowCat    = (row.dataset.cat  || '').toLowerCase();
        const isChanged = row.dataset.changed === '1';

        const matchSearch = !search || name.includes(search) || sku.includes(search);
        const matchCat    = !cat    || rowCat === cat;
        const matchFilter = !showChanged || isChanged;

        const ok = matchSearch && matchCat && matchFilter;
        row.classList.toggle('hidden-row', !ok);
    });
}

document.getElementById('productSearch')?.addEventListener('input', filterRows);
document.getElementById('catFilter')?.addEventListener('change', filterRows);
document.getElementById('showChangedOnly')?.addEventListener('change', filterRows);
</script>
@endpush

@endsection
