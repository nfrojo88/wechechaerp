@php
    $modalStores = \App\Models\Store::orderBy('name')->get(['id', 'name']);
@endphp
<!-- Universal Physical Slip Voiding Modal Component -->
<div class="modal fade" id="voidSlipModal" tabindex="-1" aria-labelledby="voidSlipModalLabel" aria-hidden="true" style="z-index: 1070;">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-3 overflow-hidden">
            <form id="voidSlipModalForm" action="{{ route('api.slip-sequences.void-missing') }}" method="POST" enctype="multipart/form-data">
                @csrf
                <input type="hidden" name="store_id" id="voidModalStoreId" value="">
                <input type="hidden" name="slip_type" id="voidModalSlipType" value="receive">
                <input type="hidden" id="voidModalTargetInput" value="">

                <div class="modal-header py-3 px-4 bg-danger text-white border-0">
                    <h6 class="modal-title fw-bold d-flex align-items-center mb-0" id="voidSlipModalLabel">
                        <i class="fas fa-ban me-2 fs-5"></i>Void Physical Slip Number(s)
                    </h6>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body p-4 bg-light">
                    <!-- Context banner -->
                    <div class="alert alert-warning py-2 px-3 small border-0 mb-3 d-flex align-items-start gap-2 shadow-xs">
                        <i class="fas fa-triangle-exclamation text-warning fs-5 mt-0.5"></i>
                        <div class="lh-sm">
                            <strong class="d-block mb-1">Physical Paper Slip Leaf Control</strong>
                            Voiding marks ruined, torn, or miswritten paper slips as cancelled without moving inventory, maintaining strict sequence integrity.
                        </div>
                    </div>

                    <!-- Store & Slip Type Details -->
                    <div class="row g-2 mb-3">
                        <div class="col-sm-7">
                            <label class="form-label small fw-bold text-muted text-uppercase mb-1">Store / Warehouse <span class="text-danger">*</span></label>
                            <div id="voidModalStoreDisplay" class="form-control form-control-sm bg-white fw-semibold text-truncate border" style="font-size: 0.85rem;">
                                Active Store
                            </div>
                            <select id="voidModalStoreSelect" class="form-select form-select-sm d-none" onchange="onVoidModalStoreOrTypeChange()">
                                @foreach($modalStores as $st)
                                    <option value="{{ $st->id }}">{{ $st->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-sm-5">
                            <label class="form-label small fw-bold text-muted text-uppercase mb-1">Slip Type <span class="text-danger">*</span></label>
                            <div id="voidModalTypeDisplay" class="form-control form-control-sm bg-white font-monospace fw-bold text-primary border" style="font-size: 0.85rem;">
                                RECEIVE (GRN)
                            </div>
                            <select id="voidModalTypeSelect" class="form-select form-select-sm d-none" onchange="onVoidModalStoreOrTypeChange()">
                                <option value="receive">Receive (GRN)</option>
                                <option value="send">Send (Outgoing)</option>
                            </select>
                        </div>
                    </div>

                    <!-- Slip Number(s) to Void -->
                    <div class="mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <label class="form-label small fw-bold text-dark mb-0">
                                Slip Number(s) to Void <span class="text-danger">*</span>
                            </label>
                            <span id="voidModalExpectedBadge" class="badge bg-secondary font-monospace" style="font-size: 0.72rem;">
                                Expected: #...
                            </span>
                        </div>
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-white border-end-0"><i class="fas fa-hashtag text-danger"></i></span>
                            <input type="text" name="slip_number_input" id="voidModalSlipInput" 
                                   class="form-control font-monospace fw-bold border-start-0 text-danger" 
                                   placeholder="e.g. 2402 or 2402, 2403 or 2402-2405" required>
                        </div>
                        <div class="form-text text-muted" style="font-size: 0.73rem;">
                            Enter single slip # (e.g. <code>2402</code>), comma list (<code>2402, 2403</code>), or range (<code>2402-2405</code>).
                        </div>
                    </div>

                    <!-- Reason for Voiding with Fast Chips -->
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-dark mb-1">
                            Reason for Voiding <span class="text-danger">*</span>
                        </label>
                        <!-- Quick Reason Chips -->
                        <div class="d-flex flex-wrap gap-1 mb-2">
                            <button type="button" class="btn btn-xs btn-outline-secondary py-0 px-2 void-reason-chip" data-reason="Paper leaf spoiled / torn from physical book">Torn / Damaged</button>
                            <button type="button" class="btn btn-xs btn-outline-secondary py-0 px-2 void-reason-chip" data-reason="Writing / pen ink error on physical slip">Ink / Writing Error</button>
                            <button type="button" class="btn btn-xs btn-outline-secondary py-0 px-2 void-reason-chip" data-reason="Misprinted or defective paper sheet from printing">Misprinted Leaf</button>
                            <button type="button" class="btn btn-xs btn-outline-secondary py-0 px-2 void-reason-chip" data-reason="Cancelled on-site prior to material dispatch">Cancelled on Site</button>
                        </div>
                        <textarea name="reason" id="voidModalReason" rows="2" class="form-control form-control-sm" placeholder="Explain why this paper leaf cannot be used in the physical book..." required></textarea>
                    </div>

                    <!-- Optional Attachment -->
                    <div class="mb-1">
                        <label class="form-label small fw-bold text-muted mb-1">
                            <i class="fas fa-paperclip me-1"></i>Attach Spoiled Slip Photo / Scan <small class="text-muted fw-normal">(Optional)</small>
                        </label>
                        <input type="file" name="slip_receipt" id="voidModalSlipFile" class="form-control form-control-sm" accept=".pdf,.jpg,.jpeg,.png,.webp">
                        <small class="text-muted" style="font-size: 0.7rem;">Optional photo or scan of the damaged physical paper leaf.</small>
                    </div>

                    <div id="voidModalAlert" class="mt-3 d-none"></div>
                </div>

                <div class="modal-footer py-2 px-4 bg-light border-top d-flex justify-content-between align-items-center">
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" id="voidModalSubmitBtn" class="btn btn-sm btn-danger fw-bold shadow-sm">
                        <i class="fas fa-ban me-1"></i>Void Slip &amp; Use Next Available
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Floating Success Toast Notification for Slip Voiding -->
<div class="position-fixed bottom-0 end-0 p-3" style="z-index: 1080;">
    <div id="voidSlipToast" class="toast align-items-center text-white bg-success border-0 shadow-lg" role="alert" aria-live="assertive" aria-atomic="true">
        <div class="d-flex">
            <div class="toast-body py-2 px-3 small fw-semibold" id="voidSlipToastMessage">
                <i class="fas fa-check-circle me-1.5 fs-6"></i> Slip voided successfully!
            </div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
        </div>
    </div>
</div>

<script>
window.onVoidModalStoreOrTypeChange = function() {
    const storeSelect = document.getElementById('voidModalStoreSelect');
    const typeSelect = document.getElementById('voidModalTypeSelect');
    const storeIdInput = document.getElementById('voidModalStoreId');
    const slipTypeInput = document.getElementById('voidModalSlipType');
    const slipInput = document.getElementById('voidModalSlipInput');
    const expectedBadge = document.getElementById('voidModalExpectedBadge');

    const storeId = storeSelect ? storeSelect.value : storeIdInput.value;
    const slipType = typeSelect ? typeSelect.value : slipTypeInput.value;

    if (storeIdInput) storeIdInput.value = storeId;
    if (slipTypeInput) slipTypeInput.value = slipType;

    if (storeId && slipType) {
        if (expectedBadge) {
            expectedBadge.textContent = 'Checking...';
            expectedBadge.className = 'badge bg-secondary font-monospace';
        }

        fetch(`/api/slip-sequences/${storeId}/${slipType}`)
            .then(res => res.json())
            .then(data => {
                if (data && data.has_sequence) {
                    if (expectedBadge) {
                        expectedBadge.textContent = 'Next: #' + data.formatted_slip;
                        expectedBadge.className = 'badge bg-primary font-monospace';
                    }
                    if (slipInput) {
                        slipInput.value = data.formatted_slip;
                    }
                } else {
                    if (expectedBadge) {
                        expectedBadge.textContent = 'No active book';
                        expectedBadge.className = 'badge bg-warning text-dark font-monospace';
                    }
                }
            })
            .catch(() => {
                if (expectedBadge) expectedBadge.textContent = 'Sequence ready';
            });
    }
};

document.addEventListener('DOMContentLoaded', function () {
    const voidModalEl = document.getElementById('voidSlipModal');
    if (!voidModalEl) return;

    // Quick Reason Chips handler
    document.querySelectorAll('.void-reason-chip').forEach(btn => {
        btn.addEventListener('click', function () {
            const reasonInput = document.getElementById('voidModalReason');
            if (reasonInput) {
                reasonInput.value = this.getAttribute('data-reason');
                reasonInput.focus();
            }
        });
    });

    // Modal show event - initialize context from triggering button or host form
    voidModalEl.addEventListener('show.bs.modal', function (event) {
        const trigger = event.relatedTarget;
        const storeIdInput = document.getElementById('voidModalStoreId');
        const slipTypeInput = document.getElementById('voidModalSlipType');
        const targetInputHolder = document.getElementById('voidModalTargetInput');
        const storeDisplay = document.getElementById('voidModalStoreDisplay');
        const storeSelect = document.getElementById('voidModalStoreSelect');
        const typeDisplay = document.getElementById('voidModalTypeDisplay');
        const typeSelect = document.getElementById('voidModalTypeSelect');
        const slipInput = document.getElementById('voidModalSlipInput');
        const expectedBadge = document.getElementById('voidModalExpectedBadge');
        const alertBox = document.getElementById('voidModalAlert');

        if (alertBox) {
            alertBox.classList.add('d-none');
            alertBox.innerHTML = '';
        }

        let storeId = '';
        let storeName = '';
        let slipType = '';
        let targetSelector = '';
        let currentSlipVal = '';

        if (trigger) {
            storeId = trigger.getAttribute('data-store-id') || '';
            storeName = trigger.getAttribute('data-store-name') || '';
            slipType = trigger.getAttribute('data-slip-type') || '';
            targetSelector = trigger.getAttribute('data-target-input') || '';
            currentSlipVal = trigger.getAttribute('data-current-slip') || '';

            // If a store select element selector is specified, read dynamic selection
            const storeSelectSelector = trigger.getAttribute('data-store-select');
            if (storeSelectSelector) {
                const storeSelectEl = document.querySelector(storeSelectSelector);
                if (storeSelectEl && storeSelectEl.value) {
                    storeId = storeSelectEl.value;
                    const opt = storeSelectEl.options[storeSelectEl.selectedIndex];
                    if (opt) {
                        storeName = opt.text.trim();
                    }
                }
            }
        }

        // Fallback store detection if not provided
        if (!storeId) {
            const fallbackSelect = document.getElementById('intakeStoreSelect') || document.getElementById('store_id');
            if (fallbackSelect && fallbackSelect.value) {
                storeId = fallbackSelect.value;
                const opt = fallbackSelect.options[fallbackSelect.selectedIndex];
                if (opt) storeName = opt.text.trim();
            }
        }

        // If storeId is known, show fixed store display; otherwise show dropdown selector
        if (storeId) {
            if (storeIdInput) storeIdInput.value = storeId;
            if (storeDisplay) {
                storeDisplay.textContent = storeName || ('Store ID #' + storeId);
                storeDisplay.classList.remove('d-none');
            }
            if (storeSelect) {
                storeSelect.value = storeId;
                storeSelect.classList.add('d-none');
            }
        } else {
            if (storeDisplay) storeDisplay.classList.add('d-none');
            if (storeSelect) {
                storeSelect.classList.remove('d-none');
                storeId = storeSelect.value;
            }
            if (storeIdInput) storeIdInput.value = storeId;
        }

        // If slipType is known, show fixed type display; otherwise show dropdown selector
        if (slipType) {
            if (slipTypeInput) slipTypeInput.value = slipType;
            if (typeDisplay) {
                if (slipType === 'send') {
                    typeDisplay.textContent = 'SEND (OUTGOING / DISPATCH)';
                    typeDisplay.className = 'form-control form-control-sm bg-white font-monospace fw-bold text-warning text-uppercase border';
                } else {
                    typeDisplay.textContent = 'RECEIVE (GRN / INTAKE)';
                    typeDisplay.className = 'form-control form-control-sm bg-white font-monospace fw-bold text-success text-uppercase border';
                }
                typeDisplay.classList.remove('d-none');
            }
            if (typeSelect) {
                typeSelect.value = slipType;
                typeSelect.classList.add('d-none');
            }
        } else {
            if (typeDisplay) typeDisplay.classList.add('d-none');
            if (typeSelect) {
                typeSelect.classList.remove('d-none');
                slipType = typeSelect.value || 'receive';
            }
            if (slipTypeInput) slipTypeInput.value = slipType;
        }

        // Detect current value from target input if present
        if (targetSelector) {
            const targetEl = document.querySelector(targetSelector);
            if (targetEl && targetEl.value) {
                currentSlipVal = targetEl.value.trim();
            }
        }

        if (targetInputHolder) targetInputHolder.value = targetSelector;

        if (slipInput) {
            slipInput.value = currentSlipVal || '';
        }

        // Fetch live next expected slip from backend API
        if (storeId && slipType) {
            if (expectedBadge) {
                expectedBadge.textContent = 'Checking...';
                expectedBadge.className = 'badge bg-secondary font-monospace';
            }

            fetch(`/api/slip-sequences/${storeId}/${slipType}`)
                .then(res => res.json())
                .then(data => {
                    if (data && data.has_sequence) {
                        if (expectedBadge) {
                            expectedBadge.textContent = 'Next: #' + data.formatted_slip;
                            expectedBadge.className = 'badge bg-primary font-monospace';
                        }
                        if (slipInput && !slipInput.value) {
                            slipInput.value = data.formatted_slip;
                        }
                    } else {
                        if (expectedBadge) {
                            expectedBadge.textContent = 'No active book';
                            expectedBadge.className = 'badge bg-warning text-dark font-monospace';
                        }
                    }
                })
                .catch(() => {
                    if (expectedBadge) expectedBadge.textContent = 'Sequence ready';
                });
        }
    });

    // Form submission handler via AJAX
    const voidForm = document.getElementById('voidSlipModalForm');
    if (voidForm) {
        voidForm.addEventListener('submit', function (e) {
            e.preventDefault();

            const submitBtn = document.getElementById('voidModalSubmitBtn');
            const alertBox = document.getElementById('voidModalAlert');
            const targetSelector = document.getElementById('voidModalTargetInput')?.value;

            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Voiding...';
            }
            if (alertBox) {
                alertBox.classList.add('d-none');
                alertBox.innerHTML = '';
            }

            const formData = new FormData(voidForm);

            fetch(voidForm.action, {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                },
                body: formData
            })
            .then(async response => {
                const isJson = response.headers.get('content-type')?.includes('application/json');
                const data = isJson ? await response.json() : null;

                if (!response.ok) {
                    const errorMsg = (data && (data.error || data.message)) || `Server error (${response.status})`;
                    throw new Error(errorMsg);
                }
                return data;
            })
            .then(data => {
                // Update target input field if one was associated
                if (targetSelector) {
                    const targetEl = document.querySelector(targetSelector);
                    if (targetEl && data.next_expected) {
                        targetEl.value = data.next_expected;
                        targetEl.dispatchEvent(new Event('input', { bubbles: true }));
                        targetEl.dispatchEvent(new Event('change', { bubbles: true }));
                    }
                }

                // Update any next slip badges on the page
                const nextBadge = document.getElementById('nextSlipBadge') || document.getElementById('pettyNextSlipBadge');
                if (nextBadge && data.next_expected) {
                    nextBadge.textContent = 'Next: #' + data.next_expected;
                }

                // Close modal
                const modalInstance = bootstrap.Modal.getInstance(voidModalEl);
                if (modalInstance) {
                    modalInstance.hide();
                }

                // Reset form fields
                voidForm.reset();

                // Show success toast notification
                const toastEl = document.getElementById('voidSlipToast');
                const toastMsg = document.getElementById('voidSlipToastMessage');
                if (toastEl && toastMsg) {
                    const voidedTxt = data.formatted_numbers ? data.formatted_numbers.join(', ') : (data.voided_numbers ? data.voided_numbers.join(', ') : 'Slip');
                    toastMsg.innerHTML = `<i class="fas fa-check-circle me-1.5 fs-6"></i> Slip <strong>#${voidedTxt}</strong> marked as <strong>VOID</strong>. Next free slip <strong>#${data.next_expected || 'updated'}</strong> applied!`;
                    const toast = new bootstrap.Toast(toastEl, { delay: 6000 });
                    toast.show();
                }

                // If on slip sequence edit page, reload page to refresh leaf map
                if (window.location.pathname.includes('slip-sequences/')) {
                    setTimeout(() => window.location.reload(), 1000);
                }
            })
            .catch(error => {
                if (alertBox) {
                    alertBox.className = 'alert alert-danger py-2 px-3 small border-0 mt-3';
                    alertBox.innerHTML = `<i class="fas fa-exclamation-circle me-1"></i> ${error.message}`;
                    alertBox.classList.remove('d-none');
                }
            })
            .finally(() => {
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = '<i class="fas fa-ban me-1"></i> Void Slip &amp; Use Next Available';
                }
            });
        });
    }
});
</script>
