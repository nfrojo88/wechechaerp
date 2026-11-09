{{-- Global Admin Modal: Add Forgotten Material to Transfer --}}
<div class="modal fade" id="adminAddMaterialModal" tabindex="-1" aria-labelledby="adminAddMaterialModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <form action="{{ Route::has('transfers.admin-add-item') ? route('transfers.admin-add-item', $transfer) : url('transfers/'.$transfer->id.'/admin-add-item') }}" method="POST" id="adminAddMaterialForm">
                @csrf

                {{-- Header --}}
                <div class="modal-header py-3 px-4" style="background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%); border-bottom: 1px solid rgba(255,255,255,0.1);">
                    <div class="d-flex align-items-center gap-3">
                        <div class="rounded-3 p-2 d-flex align-items-center justify-content-center shadow-sm" style="background: rgba(245, 158, 11, 0.2); border: 1px solid rgba(245, 158, 11, 0.4); width: 42px; height: 42px;">
                            <i class="fas fa-shield-halved text-warning fs-5"></i>
                        </div>
                        <div>
                            <div class="d-flex align-items-center gap-2">
                                <h5 class="modal-title fw-bold mb-0 text-white" id="adminAddMaterialModalLabel">Add Forgotten Material</h5>
                                <span class="badge bg-warning text-dark fw-bold px-2 py-0.5" style="font-size: 0.72rem; letter-spacing: 0.5px;">GLOBAL ADMIN ONLY</span>
                            </div>
                            <span class="small text-muted" style="color: #cbd5e1 !important; font-size: 0.82rem;">
                                Inject a forgotten material into Transfer <span class="font-monospace fw-bold text-white">#{{ $transfer->transfer_no }}</span>
                            </span>
                        </div>
                    </div>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body p-4 bg-white">
                    {{-- Transfer Status & Inventory Context Banner --}}
                    @php
                        $tStatus = strtolower($transfer->status ?? 'draft');
                        $statusBadgeClass = match($tStatus) {
                            'completed' => 'bg-success',
                            'in_transit', 'dispatched' => 'bg-primary',
                            'approved' => 'bg-info text-dark',
                            'rejected', 'cancelled' => 'bg-danger',
                            default => 'bg-warning text-dark',
                        };
                    @endphp
                    <div class="p-3 mb-3 rounded-3 border" style="background-color: #f8fafc;">
                        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-2">
                            <div class="small">
                                <span class="text-muted">Origin:</span> <strong class="text-dark">{{ $transfer->fromStore->name ?? 'Origin Store' }}</strong>
                                <i class="fas fa-arrow-right mx-1 text-muted"></i>
                                <span class="text-muted">Destination:</span> <strong class="text-dark">{{ $transfer->toStore->name ?? 'Destination Store' }}</strong>
                            </div>
                            <div>
                                <span class="badge {{ $statusBadgeClass }} px-2.5 py-1 text-uppercase fw-bold" style="font-size: 0.75rem;">
                                    Status: {{ str_replace('_', ' ', $transfer->status) }}
                                </span>
                            </div>
                        </div>

                        <div class="small text-secondary mb-0">
                            @if($tStatus === 'completed')
                                <i class="fas fa-info-circle text-success me-1"></i>
                                <strong>Smart Auto-Sync:</strong> Because this transfer is marked <strong>Completed</strong>, adding this forgotten material will automatically record it as <em>Requested, Sent, and Received</em>, and will immediately deduct it from <strong>{{ $transfer->fromStore->name ?? 'Origin Store' }}</strong> and add it to <strong>{{ $transfer->toStore->name ?? 'Destination Store' }}</strong>.
                            @elseif(in_array($tStatus, ['in_transit', 'dispatched']))
                                <i class="fas fa-info-circle text-primary me-1"></i>
                                <strong>Smart Auto-Sync:</strong> Because this transfer is <strong>In Transit</strong>, adding this forgotten material will record it as <em>Requested and Sent</em>, and will immediately deduct it from <strong>{{ $transfer->fromStore->name ?? 'Origin Store' }}</strong>.
                            @elseif($tStatus === 'approved')
                                <i class="fas fa-info-circle text-info me-1"></i>
                                <strong>Smart Auto-Sync:</strong> This transfer is <strong>Approved</strong>. Adding this material will approve it for dispatch.
                            @else
                                <i class="fas fa-info-circle text-warning me-1"></i>
                                <strong>Smart Auto-Sync:</strong> This material will be appended to the transfer request manifest.
                            @endif
                        </div>
                    </div>

                    {{-- Material Selection with quick filter --}}
                    <div class="mb-3">
                        <label class="form-label fw-bold text-dark small text-uppercase mb-1">
                            Select Product / Material <span class="text-danger">*</span>
                        </label>
                        <div class="input-group input-group-sm mb-1.5">
                            <span class="input-group-text bg-light text-muted"><i class="fas fa-search"></i></span>
                            <input type="text" class="form-control form-control-sm" id="adminProductFilter" placeholder="Type to filter material list (e.g., Cement, Rebar, Paint)..." autocomplete="off">
                            <button class="btn btn-outline-secondary" type="button" id="adminClearFilterBtn" title="Clear filter"><i class="fas fa-times"></i></button>
                        </div>
                        <select name="product_id" id="adminProductId" class="form-select form-select-sm" size="6" required style="font-family: inherit;">
                            <option value="" disabled class="text-muted py-1">-- Select Material from Inventory Catalog --</option>
                            @foreach($products as $p)
                                <option value="{{ $p->id }}" data-unit="{{ $p->unit ?? 'pcs' }}" data-name="{{ strtolower($p->name . ' ' . ($p->code ?? '') . ' ' . ($p->category ?? '')) }}">
                                    {{ $p->name }} {{ $p->code ? '('.$p->code.')' : '' }} — [Unit: {{ $p->unit ?? 'pcs' }}]
                                </option>
                            @endforeach
                        </select>
                        <div class="form-text small text-muted" id="adminProductCountHelp">Showing {{ count($products) }} materials. Click one to select.</div>
                    </div>

                    {{-- Quantity & Unit Row --}}
                    <div class="row g-3 mb-3">
                        <div class="col-md-7">
                            <label class="form-label fw-bold text-dark small text-uppercase mb-1">
                                Quantity Forgotten <span class="text-danger">*</span>
                            </label>
                            <div class="input-group">
                                <span class="input-group-text bg-light text-primary"><i class="fas fa-hashtag"></i></span>
                                <input type="number" step="0.001" min="0.001" name="quantity" id="adminQuantityInput" class="form-control fw-bold fs-6 text-end" placeholder="e.g., 25" required>
                            </div>
                            <span class="form-text small text-muted">Amount physically forgotten that belongs to this transfer</span>
                        </div>
                        <div class="col-md-5">
                            <label class="form-label fw-bold text-dark small text-uppercase mb-1">
                                Unit of Measure <span class="text-danger">*</span>
                            </label>
                            <input type="text" name="unit" id="adminUnitInput" class="form-control text-center fw-bold" placeholder="e.g., pcs, bags, kg" required>
                            <span class="form-text small text-muted">Auto-filled from catalog</span>
                        </div>
                    </div>

                    {{-- Merge if exists checkbox --}}
                    <div class="form-check form-switch p-3 rounded-3 border bg-light mb-3">
                        <input class="form-check-input ms-0 me-2" type="checkbox" name="merge_if_exists" id="adminMergeIfExists" value="1" checked>
                        <label class="form-check-label fw-semibold text-dark" for="adminMergeIfExists">
                            Consolidate with existing line item if already in this transfer
                        </label>
                        <div class="small text-muted ms-4 ps-1">
                            If this material already exists in the transfer items, the forgotten quantity will be added directly to the existing line item instead of creating a duplicate line.
                        </div>
                    </div>

                    {{-- Advanced Custom Stage Quantities Toggle --}}
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="adminCustomQtyToggle">
                            <label class="form-check-label fw-semibold text-secondary small" for="adminCustomQtyToggle">
                                <i class="fas fa-sliders me-1"></i>Override stage quantities manually (Advanced custom breakdown)
                            </label>
                        </div>
                        <input type="hidden" name="fulfillment_mode" id="adminFulfillmentMode" value="auto">

                        <div id="adminCustomQtySection" class="p-3 mt-2 rounded-3 border bg-light d-none">
                            <div class="small text-muted mb-2">
                                <i class="fas fa-exclamation-triangle text-warning me-1"></i>
                                Custom override mode: Specify exact numbers per workflow stage if different from the default auto-alignment.
                            </div>
                            <div class="row g-2">
                                <div class="col-md-4">
                                    <label class="form-label small fw-bold text-muted">Requested Qty</label>
                                    <input type="number" step="0.001" min="0" name="requested_quantity" id="adminCustomRequested" class="form-control form-control-sm text-end" placeholder="Requested">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label small fw-bold text-muted">Sent Qty (Origin)</label>
                                    <input type="number" step="0.001" min="0" name="sent_quantity" id="adminCustomSent" class="form-control form-control-sm text-end" placeholder="Sent">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label small fw-bold text-muted">Received Qty (Dest)</label>
                                    <input type="number" step="0.001" min="0" name="received_quantity" id="adminCustomReceived" class="form-control form-control-sm text-end" placeholder="Received">
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Reason / Admin Note --}}
                    <div class="mb-2">
                        <label class="form-label fw-bold text-dark small text-uppercase mb-1">
                            Reason / Audit Note (Optional)
                        </label>
                        <textarea name="admin_note" class="form-control form-control-sm" rows="2" placeholder="e.g., Material was physically loaded onto the truck but omitted from initial system transfer entry. Verified by Store Keeper."></textarea>
                    </div>
                </div>

                {{-- Footer --}}
                <div class="modal-footer bg-light py-2.5 px-4 d-flex justify-content-between align-items-center">
                    <span class="small text-muted">
                        <i class="fas fa-lock me-1 text-warning"></i>Action logged to ERP Audit Trail
                    </span>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary btn-sm fw-bold shadow-sm" id="adminSubmitBtn">
                            <i class="fas fa-plus-circle me-1"></i>Add Material &amp; Sync Inventory
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const filterInput = document.getElementById('adminProductFilter');
    const selectElem = document.getElementById('adminProductId');
    const unitInput = document.getElementById('adminUnitInput');
    const qtyInput = document.getElementById('adminQuantityInput');
    const clearBtn = document.getElementById('adminClearFilterBtn');
    const countHelp = document.getElementById('adminProductCountHelp');
    const customToggle = document.getElementById('adminCustomQtyToggle');
    const customSection = document.getElementById('adminCustomQtySection');
    const modeInput = document.getElementById('adminFulfillmentMode');

    if (!selectElem) return;

    // Cache original options
    const allOptions = Array.from(selectElem.querySelectorAll('option')).filter(opt => opt.value !== '');

    // Live filter
    if (filterInput) {
        filterInput.addEventListener('input', function () {
            const query = this.value.trim().toLowerCase();
            let visibleCount = 0;

            allOptions.forEach(opt => {
                const searchStr = (opt.getAttribute('data-name') || opt.textContent).toLowerCase();
                if (!query || searchStr.includes(query)) {
                    opt.style.display = '';
                    visibleCount++;
                } else {
                    opt.style.display = 'none';
                }
            });

            if (countHelp) {
                countHelp.textContent = query 
                    ? `Found ${visibleCount} matching material(s). Click one to select.` 
                    : `Showing ${allOptions.length} materials. Click one to select.`;
            }
        });
    }

    if (clearBtn && filterInput) {
        clearBtn.addEventListener('click', function () {
            filterInput.value = '';
            filterInput.dispatchEvent(new Event('input'));
            filterInput.focus();
        });
    }

    // Auto-fill unit when product selected
    selectElem.addEventListener('change', function () {
        const selected = selectElem.options[selectElem.selectedIndex];
        if (selected && selected.dataset.unit) {
            unitInput.value = selected.dataset.unit;
        }
        if (qtyInput && !qtyInput.value) {
            qtyInput.focus();
        }
    });

    // Toggle advanced custom breakdown
    if (customToggle && customSection && modeInput) {
        customToggle.addEventListener('change', function () {
            if (this.checked) {
                customSection.classList.remove('d-none');
                modeInput.value = 'custom';
                // Pre-populate with current qty if set
                const baseQty = parseFloat(qtyInput.value) || 0;
                const reqField = document.getElementById('adminCustomRequested');
                const sentField = document.getElementById('adminCustomSent');
                const recField = document.getElementById('adminCustomReceived');
                if (reqField && !reqField.value && baseQty > 0) reqField.value = baseQty;
                if (sentField && !sentField.value && baseQty > 0) sentField.value = baseQty;
                if (recField && !recField.value && baseQty > 0) recField.value = baseQty;
            } else {
                customSection.classList.add('d-none');
                modeInput.value = 'auto';
            }
        });
    }

    // Form submit confirmation
    const form = document.getElementById('adminAddMaterialForm');
    if (form) {
        form.addEventListener('submit', function (e) {
            const sel = selectElem.options[selectElem.selectedIndex];
            const pName = sel ? sel.text.split('—')[0].trim() : 'Material';
            const qty = qtyInput.value;
            const unit = unitInput.value;
            const ok = confirm(`Global Admin Confirmation:\n\nAre you sure you want to add ${qty} ${unit} of "${pName}" to Transfer #{{ $transfer->transfer_no }}?\n\nThis will immediately adjust physical inventory and log an audit trail.`);
            if (!ok) {
                e.preventDefault();
            }
        });
    }
});
</script>
