<!-- Fulfill Material Intake & Store Reconciliation Modal -->
<div class="modal fade" id="auditFulfillModal" tabindex="-1" aria-labelledby="auditFulfillModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-3 overflow-hidden">
            <form id="auditFulfillForm" method="POST" action="{{ route('audit.fulfillment.fulfill-item') }}">
                @csrf
                <input type="hidden" name="item_id" id="fulfillModalItemId">
                <input type="hidden" name="action_mode" id="fulfillModalActionMode" value="not_accepted">

                <div class="modal-header text-white py-3 px-4" style="background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%); border-bottom: 3px solid #10b981;">
                    <div class="d-flex align-items-center gap-3">
                        <div class="p-2 bg-success bg-opacity-25 rounded-3 text-success">
                            <i class="fa-solid fa-boxes-packing fa-lg"></i>
                        </div>
                        <div>
                            <h5 class="modal-title fw-bold mb-0 text-white fs-6" id="auditFulfillModalLabel">
                                Fulfill Material Intake &amp; Store Reconciliation
                            </h5>
                            <small class="text-white-50" id="fulfillModalSubtitle">
                                Resolve unfulfilled balance, record non-accepted items, or intake to inventory
                            </small>
                        </div>
                    </div>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body p-4 bg-light">
                    <!-- Item Overview Banner -->
                    <div class="card border-0 shadow-xs rounded-3 bg-white mb-3">
                        <div class="card-body p-3">
                            <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-2">
                                <div>
                                    <span class="badge bg-primary font-monospace px-2.5 py-1 mb-1" id="fulfillBannerPrNo">PR #</span>
                                    <h6 class="fw-bold text-dark mb-0 fs-6" id="fulfillBannerItemName">Material Item</h6>
                                    <small class="text-muted font-monospace" id="fulfillBannerSku">SKU: N/A</small>
                                </div>
                                <div class="text-end">
                                    <small class="text-muted d-block">Destination Store</small>
                                    <span class="fw-semibold text-dark" id="fulfillBannerStore">Store</span>
                                </div>
                            </div>
                            <div class="row g-2 text-center pt-2 border-top small">
                                <div class="col-3">
                                    <span class="text-muted d-block" style="font-size: 0.72rem;">TARGET / APPROVED</span>
                                    <strong class="font-monospace text-dark" id="fulfillBannerTarget">0.00</strong>
                                </div>
                                <div class="col-3">
                                    <span class="text-muted d-block" style="font-size: 0.72rem;">ALREADY INTAKED</span>
                                    <strong class="font-monospace text-success" id="fulfillBannerReceived">0.00</strong>
                                </div>
                                <div class="col-3">
                                    <span class="text-muted d-block" style="font-size: 0.72rem;">NOT ACCEPTED</span>
                                    <strong class="font-monospace text-secondary" id="fulfillBannerUnaccepted">0.00</strong>
                                </div>
                                <div class="col-3">
                                    <span class="text-muted d-block" style="font-size: 0.72rem;">REMAINING BALANCE</span>
                                    <strong class="font-monospace text-danger fs-6" id="fulfillBannerRemaining">0.00</strong>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Fulfillment Action Selection -->
                    <label class="form-label small fw-bold text-muted text-uppercase mb-2">Select Fulfillment Action</label>
                    <div class="row g-2 mb-3">
                        <div class="col-md-4">
                            <div class="card h-100 border fulfill-action-card active cursor-pointer p-3 rounded-3" data-mode="not_accepted" style="border-width: 2px !important; border-color: #ef4444 !important; background: #fff5f5; cursor: pointer;">
                                <div class="d-flex align-items-center gap-2 mb-1">
                                    <i class="fa-solid fa-ban text-danger fa-lg"></i>
                                    <span class="fw-bold text-danger">Not Accepted</span>
                                </div>
                                <small class="text-muted lh-sm d-block" style="font-size: 0.75rem;">
                                    Do <strong>NOT</strong> add to inventory. Mark missing/rejected goods as Not Accepted.
                                </small>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card h-100 border fulfill-action-card cursor-pointer p-3 rounded-3" data-mode="accept_inventory" style="border-color: #cbd5e1; background: #ffffff; cursor: pointer;">
                                <div class="d-flex align-items-center gap-2 mb-1">
                                    <i class="fa-solid fa-warehouse text-success fa-lg"></i>
                                    <span class="fw-bold text-success">Accept to Store</span>
                                </div>
                                <small class="text-muted lh-sm d-block" style="font-size: 0.75rem;">
                                    Intake physical material into store inventory balance and increment stock.
                                </small>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card h-100 border fulfill-action-card cursor-pointer p-3 rounded-3" data-mode="partial" style="border-color: #cbd5e1; background: #ffffff; cursor: pointer;">
                                <div class="d-flex align-items-center gap-2 mb-1">
                                    <i class="fa-solid fa-scale-balanced text-primary fa-lg"></i>
                                    <span class="fw-bold text-primary">Partial &amp; Split</span>
                                </div>
                                <small class="text-muted lh-sm d-block" style="font-size: 0.75rem;">
                                    Accept part into inventory and mark remaining balance as Not Accepted.
                                </small>
                            </div>
                        </div>
                    </div>

                    <!-- Dynamic Input Fields Container -->
                    <div class="card border-0 shadow-xs rounded-3 bg-white p-3 mb-3">
                        <!-- Mode 1: Close as Not Accepted -->
                        <div id="sectionNotAccepted" class="fulfillment-section">
                            <div class="alert alert-danger bg-danger bg-opacity-10 border-danger border-opacity-25 text-danger small py-2 px-3 mb-3 rounded-3 d-flex align-items-center gap-2">
                                <i class="fa-solid fa-triangle-exclamation fa-lg flex-shrink-0"></i>
                                <div>
                                    <strong>Strict Inventory Rule:</strong> Material marked as "Not Accepted" will <strong>NEVER</strong> be added to store inventory. It will be recorded as rejected and moved to the Completed audit section.
                                </div>
                            </div>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label small fw-bold text-muted">UNACCEPTED QUANTITY (<span class="unit-label">pcs</span>)</label>
                                    <input type="number" step="any" min="0" name="unaccepted_quantity" id="inputUnacceptedQty" class="form-control font-monospace fw-bold text-danger">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small fw-bold text-muted">PRESET REASON</label>
                                    <select class="form-select" id="selectPresetReason">
                                        <option value="Short delivered by supplier (cancelled balance)">Short delivered by supplier (cancelled balance)</option>
                                        <option value="Defective / damaged / rejected by store">Defective / damaged / rejected by store</option>
                                        <option value="Wrong item / specification delivered">Wrong item / specification delivered</option>
                                        <option value="Purchase cancelled / out of stock">Purchase cancelled / out of stock</option>
                                        <option value="Audit fulfillment reconciliation closure">Audit fulfillment reconciliation closure</option>
                                        <option value="other">Other reason...</option>
                                    </select>
                                </div>
                                <div class="col-12">
                                    <label class="form-label small fw-bold text-muted">REJECTION / NON-ACCEPTANCE REASON</label>
                                    <input type="text" name="rejection_reason" id="inputRejectionReason" class="form-control" placeholder="Specify why material is not accepted..." value="Short delivered by supplier (cancelled balance)">
                                </div>
                            </div>
                        </div>

                        <!-- Mode 2: Accept to Inventory -->
                        <div id="sectionAcceptInventory" class="fulfillment-section d-none">
                            <div class="alert alert-success bg-success bg-opacity-10 border-success border-opacity-25 text-success small py-2 px-3 mb-3 rounded-3 d-flex align-items-center gap-2">
                                <i class="fa-solid fa-check-circle fa-lg flex-shrink-0"></i>
                                <div>
                                    <strong>Inventory Update:</strong> This quantity will be immediately added to the selected store's <code>quantity_on_hand</code> and recorded in the Stock Movement Ledger.
                                </div>
                            </div>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label small fw-bold text-muted">INTAKE QUANTITY TO ADD (<span class="unit-label">pcs</span>)</label>
                                    <input type="number" step="any" min="0" name="intake_quantity" id="inputIntakeQty" class="form-control font-monospace fw-bold text-success">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small fw-bold text-muted">DESTINATION STORE</label>
                                    <select name="store_id" id="fulfillStoreSelect" class="form-select">
                                        @foreach($stores as $st)
                                            <option value="{{ $st->id }}">{{ $st->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                        </div>

                        <!-- Mode 3: Partial Split (both intake and unaccepted) -->
                        <div id="sectionPartialSplit" class="fulfillment-section d-none">
                            <div class="alert alert-primary bg-primary bg-opacity-10 border-primary border-opacity-25 text-primary small py-2 px-3 mb-3 rounded-3 d-flex align-items-center gap-2">
                                <i class="fa-solid fa-scale-balanced fa-lg flex-shrink-0"></i>
                                <div>
                                    <strong>Split Allocation:</strong> Specify the exact amount accepted into store inventory, and the remainder will be marked as Not Accepted (not added to stock).
                                </div>
                            </div>
                            <div class="row g-3 mb-3">
                                <div class="col-md-6">
                                    <label class="form-label small fw-bold text-success">ACCEPTED INTO INVENTORY (<span class="unit-label">pcs</span>)</label>
                                    <input type="number" step="any" min="0" id="inputPartialIntakeQty" class="form-control font-monospace fw-bold text-success" placeholder="0.00">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small fw-bold text-danger">NOT ACCEPTED (<span class="unit-label">pcs</span>)</label>
                                    <input type="number" step="any" min="0" id="inputPartialUnacceptedQty" class="form-control font-monospace fw-bold text-danger" placeholder="0.00">
                                </div>
                            </div>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label small fw-bold text-muted">STORE FOR INTAKE</label>
                                    <select id="fulfillPartialStoreSelect" class="form-select">
                                        @foreach($stores as $st)
                                            <option value="{{ $st->id }}">{{ $st->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small fw-bold text-muted">NON-ACCEPTANCE REASON</label>
                                    <input type="text" id="inputPartialRejectionReason" class="form-control" placeholder="Reason for unaccepted balance..." value="Short delivered / defective balance">
                                </div>
                            </div>
                        </div>

                        <!-- Additional Notes -->
                        <div class="mt-3 pt-3 border-top">
                            <label class="form-label small fw-bold text-muted">AUDIT REMARKS / MODEL 19 SLIP # (OPTIONAL)</label>
                            <input type="text" name="notes" id="fulfillModalNotes" class="form-control" placeholder="e.g. Model 19 slip #8492 or audit reconciliation notes">
                        </div>
                    </div>

                    <!-- Store Queue Closure Switch -->
                    <div class="card border border-success border-opacity-25 bg-success bg-opacity-10 rounded-3 p-3">
                        <div class="form-check form-switch mb-0">
                            <input class="form-check-input" type="checkbox" name="mark_pr_completed" id="checkMarkPrCompleted" value="1" checked style="cursor: pointer; transform: scale(1.2); margin-top: 0.15rem;">
                            <label class="form-check-label ms-2 cursor-pointer" for="checkMarkPrCompleted">
                                <strong class="text-dark d-block">Remove from Store Intake Queue (Intake Complete)</strong>
                                <small class="text-muted">
                                    When checked, this Purchase Request will be marked <code>Intake Complete</code> and will <strong>NOT</strong> show in the Store Keeper's pending intake queue.
                                </small>
                            </label>
                        </div>
                    </div>
                </div>

                <div class="modal-footer bg-white py-3 px-4 d-flex justify-content-between align-items-center">
                    <button type="button" class="btn btn-light rounded-pill px-3 border" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success rounded-pill px-4 fw-bold shadow-xs" id="btnSubmitFulfill">
                        <i class="fa-solid fa-check-double me-1"></i> Confirm Fulfillment
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
