<!-- Raise Audit Finding Modal (Shared across Store Audit pages) -->
<div class="modal fade" id="raiseFindingModal" tabindex="-1" aria-labelledby="raiseFindingModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <form method="POST" action="{{ route('audit.findings.store') }}" enctype="multipart/form-data" class="modal-content border-0 shadow-lg rounded-3 overflow-hidden">
            @csrf
            <div class="modal-header text-white py-3 px-4" style="background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%); border-bottom: 3px solid #f59e0b;">
                <div class="d-flex align-items-center gap-3">
                    <div class="p-2 bg-warning bg-opacity-25 rounded-3 text-warning">
                        <i class="fa-solid fa-clipboard-question fa-lg"></i>
                    </div>
                    <div>
                        <h5 class="modal-title fw-bold mb-0 text-white fs-6" id="raiseFindingModalLabel">
                            Raise Audit Observation / Query
                        </h5>
                        <small class="text-white-50">Issue a formal query to storekeeper or procurement team</small>
                    </div>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body p-4 bg-light">
                <input type="hidden" name="source_type" id="modal_source_type" value="inventory">
                <input type="hidden" name="source_id" id="modal_source_id" value="">
                <input type="hidden" name="store_id" id="modal_store_id" value="">
                <input type="hidden" name="project_id" id="modal_project_id" value="">

                <div class="row g-3">
                    <div class="col-12">
                        <div class="bg-white p-3 rounded-3 border">
                            <label class="form-label fw-bold text-dark mb-1 small text-uppercase">Query Title / Subject <span class="text-danger">*</span></label>
                            <input type="text" name="title" id="modal_title" class="form-control" placeholder="e.g. Negative Stock on Rebar 12mm / Unfulfilled PR #228" required>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="bg-white p-3 rounded-3 border h-100">
                            <label class="form-label fw-bold text-dark mb-1 small text-uppercase">Severity Level <span class="text-danger">*</span></label>
                            <select name="severity" id="modal_severity" class="form-select" required>
                                <option value="low">Low (Minor discrepancy / Advisory)</option>
                                <option value="medium" selected>Medium (Standard finding)</option>
                                <option value="high">High (Urgent / Material deviation)</option>
                                <option value="critical">Critical (Immediate GM escalation / Paid unreceived)</option>
                            </select>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="bg-white p-3 rounded-3 border h-100">
                            <label class="form-label fw-bold text-dark mb-1 small text-uppercase">Assign To (Storekeeper / Procurement) <span class="text-danger">*</span></label>
                            <select name="assigned_to" id="modal_assigned_to" class="form-select" required>
                                <option value="">Select Assignee...</option>
                                @foreach($assignees ?? [] as $user)
                                    <option value="{{ $user->id }}">{{ $user->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="bg-white p-3 rounded-3 border h-100">
                            <label class="form-label fw-bold text-dark mb-1 small text-uppercase">Response Due Date</label>
                            <input type="date" name="due_date" id="modal_due_date" class="form-control" value="{{ now()->addDays(3)->format('Y-m-d') }}">
                            <small class="text-muted" style="font-size:0.75rem;">Findings overdue beyond this date automatically escalate to the General Manager.</small>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="bg-white p-3 rounded-3 border h-100">
                            <label class="form-label fw-bold text-dark mb-1 small text-uppercase">Supporting Evidence / Attachment</label>
                            <input type="file" name="attachment" class="form-control" accept="image/*,.pdf,.doc,.docx,.xls,.xlsx">
                            <small class="text-muted" style="font-size:0.75rem;">Max 10MB (PDF, image, Excel spreadsheet)</small>
                        </div>
                    </div>

                    <div class="col-12">
                        <div class="bg-white p-3 rounded-3 border">
                            <label class="form-label fw-bold text-dark mb-1 small text-uppercase">Audit Observation &amp; Required Remediation <span class="text-danger">*</span></label>
                            <textarea name="observation" id="modal_observation" class="form-control" rows="4" placeholder="Detail the discrepancy, physical slip mismatch, missing intake record, or requested clarification..." required></textarea>
                        </div>
                    </div>
                </div>
            </div>

            <div class="modal-footer bg-white py-3 px-4 d-flex justify-content-between align-items-center">
                <button type="button" class="btn btn-outline-secondary rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-warning text-dark fw-bold rounded-pill px-4 shadow-sm">
                    <i class="fa-solid fa-paper-plane me-1"></i> Issue Audit Query
                </button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const raiseModal = document.getElementById('raiseFindingModal');
    if (!raiseModal) return;

    raiseModal.addEventListener('show.bs.modal', function (event) {
        const button = event.relatedTarget;
        if (!button) return;

        const sourceType = button.getAttribute('data-source-type') || 'inventory';
        const sourceId   = button.getAttribute('data-source-id') || '';
        const storeId    = button.getAttribute('data-store-id') || '';
        const projectId  = button.getAttribute('data-project-id') || '';
        const title      = button.getAttribute('data-title') || '';
        const severity   = button.getAttribute('data-severity') || 'medium';
        const obs        = button.getAttribute('data-observation') || '';

        document.getElementById('modal_source_type').value = sourceType;
        document.getElementById('modal_source_id').value = sourceId;
        document.getElementById('modal_store_id').value = storeId;
        document.getElementById('modal_project_id').value = projectId;
        if (title) document.getElementById('modal_title').value = title;
        if (severity) document.getElementById('modal_severity').value = severity;
        if (obs) document.getElementById('modal_observation').value = obs;
    });
});
</script>
