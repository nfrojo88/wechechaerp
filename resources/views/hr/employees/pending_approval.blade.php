@extends('layouts.app')

@section('title', 'Employees Awaiting GM Approval')

@section('content')
<div class="container-fluid py-3">

    {{-- Page Header --}}
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
        <div>
            <h3 class="fw-bold mb-1">
                <i class="fa-solid fa-user-clock text-warning me-2"></i>Employees Awaiting GM Approval
            </h3>
            <p class="text-muted small mb-0">Review, approve, or return new employee registrations to the HR Officer with correction instructions.</p>
        </div>
        <div>
            <a href="{{ route('dashboard.gm') }}" class="btn btn-outline-secondary btn-sm me-2">
                <i class="fa-solid fa-arrow-left me-1"></i> Back to Dashboard
            </a>
            <a href="{{ route('employees.index') }}" class="btn btn-outline-primary btn-sm">
                <i class="fa-solid fa-users me-1"></i> All Employees
            </a>
        </div>
    </div>

    {{-- Session Alerts --}}
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show border-start border-4 border-success shadow-sm" role="alert">
            <i class="fa-solid fa-circle-check me-2"></i>{{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show border-start border-4 border-danger shadow-sm" role="alert">
            <i class="fa-solid fa-circle-exclamation me-2"></i>{{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    {{-- Search & Department Filter --}}
    <div class="card border-0 shadow-sm mb-4 rounded-3">
        <div class="card-body p-3">
            <form method="GET" action="{{ route('employees.pending-approval') }}" class="d-flex flex-wrap gap-2 align-items-center">
                <div class="input-group input-group-sm" style="max-width: 320px;">
                    <span class="input-group-text bg-white border-end-0"><i class="fa-solid fa-magnifying-glass text-muted"></i></span>
                    <input type="text" name="search" class="form-control border-start-0" placeholder="Search employee name, code, role..." value="{{ request('search') }}">
                </div>
                <select name="department" class="form-select form-select-sm" style="max-width: 200px;" onchange="this.form.submit()">
                    <option value="">All Departments</option>
                    @foreach($departments as $dept)
                        <option value="{{ $dept }}" {{ request('department') == $dept ? 'selected' : '' }}>{{ $dept }}</option>
                    @endforeach
                </select>
                <button type="submit" class="btn btn-sm btn-secondary fw-semibold">Filter</button>
                @if(request('search') || request('department'))
                    <a href="{{ route('employees.pending-approval') }}" class="btn btn-sm btn-outline-danger">
                        <i class="fa-solid fa-xmark me-1"></i>Clear
                    </a>
                @endif
                <span class="ms-auto text-muted small fw-semibold">
                    <span class="badge bg-warning text-dark me-1">{{ $pendingEmployees->total() }}</span> Awaiting Approval
                </span>
            </form>
        </div>
    </div>

    {{-- Main Approval Form / Table --}}
    <div class="card border-0 shadow-sm rounded-3 overflow-hidden">
        <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center">
            <h6 class="mb-0 fw-bold text-dark">
                <i class="fa-solid fa-clipboard-check text-warning me-2"></i>Pending Approval Queue
            </h6>
            @if($pendingEmployees->isNotEmpty())
            <div class="d-flex gap-2">
                <button type="button" class="btn btn-sm btn-outline-danger fw-semibold px-3" id="bulkRejectBtn" disabled onclick="openBulkRejectModal()">
                    <i class="fa-solid fa-rotate-left me-1"></i> Reject Selected (<span class="selectedCount">0</span>)
                </button>
                <button type="button" class="btn btn-sm btn-success fw-bold px-3" id="bulkApproveBtn" disabled onclick="submitBulkApprove()">
                    <i class="fa-solid fa-check-double me-1"></i> Approve Selected (<span class="selectedCount">0</span>)
                </button>
            </div>
            @endif
        </div>

        <div class="card-body p-0">
            @if($pendingEmployees->isEmpty())
                <div class="text-center text-muted py-5">
                    <div class="rounded-circle bg-success bg-opacity-10 d-inline-flex p-4 mb-3">
                        <i class="fa-solid fa-check-circle fa-3x text-success"></i>
                    </div>
                    <h5 class="fw-bold text-dark">All Employees Approved!</h5>
                    <p class="text-muted small">There are currently no employee registrations waiting for GM approval.</p>
                </div>
            @else
            <form method="POST" action="{{ route('employees.bulk-approve') }}" id="bulkApproveForm">
                @csrf
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="bg-light text-muted text-uppercase small fw-semibold">
                            <tr>
                                <th style="width: 40px;" class="ps-3">
                                    <input type="checkbox" class="form-check-input" id="selectAll">
                                </th>
                                <th>EMPLOYEE</th>
                                <th>DEPARTMENT</th>
                                <th>JOINED</th>
                                <th>SALARY & SITE</th>
                                <th class="pe-3 text-end">ACTION</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($pendingEmployees as $emp)
                            <tr>
                                <td class="ps-3">
                                    <input type="checkbox" name="employee_ids[]" value="{{ $emp->id }}" class="form-check-input emp-checkbox">
                                </td>
                                <td>
                                    <div class="fw-bold text-dark fs-6">{{ $emp->full_name }}</div>
                                    <small class="text-muted font-monospace">{{ $emp->employee_code }}</small>
                                </td>
                                <td>
                                    <span class="badge bg-light text-dark border px-2 py-1 rounded-pill fw-normal">
                                        {{ $emp->department ?? 'N/A' }}
                                    </span>
                                    @if($emp->role_title)
                                        <small class="text-muted d-block mt-1">{{ $emp->role_title }}</small>
                                    @endif
                                </td>
                                <td>
                                    <span class="text-dark">{{ optional($emp->date_of_joining ?? $emp->created_at)->format('d M Y') }}</span>
                                    <small class="text-muted d-block">{{ optional($emp->created_at)->diffForHumans() }}</small>
                                </td>
                                <td>
                                    <span class="fw-semibold text-dark">{{ number_format($emp->basic_salary, 2) }} ETB</span>
                                    <small class="text-muted d-block">{{ $emp->project ? $emp->project->name : 'HQ / Unassigned' }}</small>
                                </td>
                                <td class="pe-3 text-end">
                                    <div class="d-inline-flex align-items-center gap-1">
                                        {{-- Direct Approve Form --}}
                                        <form action="{{ route('employees.approve', $emp) }}" method="POST" class="d-inline">
                                            @csrf @method('PUT')
                                            <button type="submit" class="btn btn-sm btn-success fw-semibold rounded-3 px-3" onclick="return confirm('Approve employee {{ addslashes($emp->full_name) }}?')">
                                                <i class="fa-solid fa-check me-1"></i>Approve
                                            </button>
                                        </form>

                                        {{-- Reject / Send Back Modal Trigger --}}
                                        <button type="button" class="btn btn-sm btn-outline-danger fw-semibold rounded-3 px-2"
                                                onclick="openRejectModal({{ $emp->id }}, '{{ addslashes($emp->full_name) }}', '{{ $emp->employee_code }}')">
                                            <i class="fa-solid fa-rotate-left me-1"></i>Reject
                                        </button>

                                        {{-- Eye Icon View Link --}}
                                        <a href="{{ route('employees.show', $emp) }}" class="btn btn-sm btn-outline-secondary rounded-3 px-2" title="View Employee Details">
                                            <i class="fa-solid fa-eye"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </form>
            @endif
        </div>

        @if($pendingEmployees->hasPages())
        <div class="card-footer bg-white border-top py-3 d-flex justify-content-between align-items-center">
            <span class="text-muted small">Showing {{ $pendingEmployees->firstItem() }} to {{ $pendingEmployees->lastItem() }} of {{ $pendingEmployees->total() }} pending approvals</span>
            <div>{{ $pendingEmployees->links('pagination::bootstrap-4') }}</div>
        </div>
        @endif
    </div>
</div>

{{-- Single Employee Rejection Modal --}}
<div class="modal fade" id="rejectEmployeeModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <form method="POST" id="singleRejectForm" action="">
                @csrf
                @method('PUT')
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title fs-6">
                        <i class="fa-solid fa-triangle-exclamation me-2"></i>Reject & Send Back to HR Officer
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="alert alert-light border mb-3">
                        <div class="small text-muted">Employee:</div>
                        <strong class="fs-6 text-dark" id="modalEmployeeName"></strong>
                        <div class="small text-muted font-monospace" id="modalEmployeeCode"></div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Reason for Rejection / Correction Instructions <span class="text-danger">*</span></label>
                        <textarea name="rejection_reason" id="singleRejectionReason" class="form-control" rows="3" required placeholder="State clearly what needs to be fixed by HR (e.g. salary exceeds budget, missing guarantee letter, wrong site assigned)..."></textarea>
                        <div class="form-text">This feedback will be clearly displayed to the HR Officer to guide their revisions.</div>
                    </div>

                    {{-- Quick Reason Tags --}}
                    <div>
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <small class="text-muted fw-bold"><i class="fa-solid fa-tags me-1 text-primary"></i>Quick Reason Templates <span class="badge bg-light text-secondary border fw-normal">Select Multiple</span>:</small>
                            <button type="button" class="btn btn-link btn-xs text-muted p-0 text-decoration-none" onclick="clearReasonTemplates(this)" style="font-size: 0.75rem;">
                                <i class="fa-solid fa-rotate-left me-1"></i>Reset Selection
                            </button>
                        </div>
                        <div class="d-flex flex-wrap gap-1">
                            <button type="button" class="btn btn-xs btn-outline-secondary py-1 px-2.5 rounded-pill quick-template-btn d-inline-flex align-items-center gap-1"
                                    data-text="Basic salary or allowances exceed approved scale. Please adjust."
                                    onclick="toggleReasonTemplate(this)">
                                <i class="fa-solid fa-check d-none template-check-icon text-white me-1"></i>
                                <span>💰 Salary Discrepancy</span>
                            </button>
                            <button type="button" class="btn btn-xs btn-outline-secondary py-1 px-2.5 rounded-pill quick-template-btn d-inline-flex align-items-center gap-1"
                                    data-text="Valid guarantee letter is mandatory for this role before approval."
                                    onclick="toggleReasonTemplate(this)">
                                <i class="fa-solid fa-check d-none template-check-icon text-white me-1"></i>
                                <span>📜 Guarantee Letter Missing</span>
                            </button>
                            <button type="button" class="btn btn-xs btn-outline-secondary py-1 px-2.5 rounded-pill quick-template-btn d-inline-flex align-items-center gap-1"
                                    data-text="Incorrect department or project site assigned. Please re-assign."
                                    onclick="toggleReasonTemplate(this)">
                                <i class="fa-solid fa-check d-none template-check-icon text-white me-1"></i>
                                <span>📍 Wrong Site/Dept</span>
                            </button>
                            <button type="button" class="btn btn-xs btn-outline-secondary py-1 px-2.5 rounded-pill quick-template-btn d-inline-flex align-items-center gap-1"
                                    data-text="Educational certificate or professional license photo is missing/illegible."
                                    onclick="toggleReasonTemplate(this)">
                                <i class="fa-solid fa-check d-none template-check-icon text-white me-1"></i>
                                <span>📁 Missing Documents</span>
                            </button>
                            <button type="button" class="btn btn-xs btn-outline-secondary py-1 px-2.5 rounded-pill quick-template-btn d-inline-flex align-items-center gap-1"
                                    data-text="Contract type, job title, or date of joining needs correction."
                                    onclick="toggleReasonTemplate(this)">
                                <i class="fa-solid fa-check d-none template-check-icon text-white me-1"></i>
                                <span>📋 Contract / Role Details</span>
                            </button>
                            <button type="button" class="btn btn-xs btn-outline-secondary py-1 px-2.5 rounded-pill quick-template-btn d-inline-flex align-items-center gap-1"
                                    data-text="Bank account number or bank name is missing or invalid."
                                    onclick="toggleReasonTemplate(this)">
                                <i class="fa-solid fa-check d-none template-check-icon text-white me-1"></i>
                                <span>🏦 Bank Account Details</span>
                            </button>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger btn-sm fw-bold">
                        <i class="fa-solid fa-paper-plane me-1"></i> Send Back to HR
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Bulk Rejection Modal --}}
<div class="modal fade" id="bulkRejectModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <form method="POST" action="{{ route('employees.bulk-reject') }}" id="bulkRejectForm">
                @csrf
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title fs-6">
                        <i class="fa-solid fa-triangle-exclamation me-2"></i>Reject Selected Employees
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <p class="mb-3">You are about to reject <strong id="bulkRejectCount">0</strong> employee registration(s) and send them back to the HR Officer.</p>
                    
                    <div id="bulkEmployeeHiddenInputs"></div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Rejection Reason / Notes <span class="text-danger">*</span></label>
                        <textarea name="rejection_reason" id="bulkRejectionReason" class="form-control" rows="3" required placeholder="Enter reasons for rejecting selected employees..."></textarea>
                    </div>

                    {{-- Bulk Quick Reason Tags --}}
                    <div>
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <small class="text-muted fw-bold"><i class="fa-solid fa-tags me-1 text-primary"></i>Quick Reason Templates <span class="badge bg-light text-secondary border fw-normal">Select Multiple</span>:</small>
                            <button type="button" class="btn btn-link btn-xs text-muted p-0 text-decoration-none" onclick="clearReasonTemplates(this)" style="font-size: 0.75rem;">
                                <i class="fa-solid fa-rotate-left me-1"></i>Reset Selection
                            </button>
                        </div>
                        <div class="d-flex flex-wrap gap-1">
                            <button type="button" class="btn btn-xs btn-outline-secondary py-1 px-2.5 rounded-pill quick-template-btn d-inline-flex align-items-center gap-1"
                                    data-text="Basic salary or allowances exceed approved scale. Please adjust."
                                    onclick="toggleReasonTemplate(this)">
                                <i class="fa-solid fa-check d-none template-check-icon text-white me-1"></i>
                                <span>💰 Salary Discrepancy</span>
                            </button>
                            <button type="button" class="btn btn-xs btn-outline-secondary py-1 px-2.5 rounded-pill quick-template-btn d-inline-flex align-items-center gap-1"
                                    data-text="Valid guarantee letter is mandatory for this role before approval."
                                    onclick="toggleReasonTemplate(this)">
                                <i class="fa-solid fa-check d-none template-check-icon text-white me-1"></i>
                                <span>📜 Guarantee Letter Missing</span>
                            </button>
                            <button type="button" class="btn btn-xs btn-outline-secondary py-1 px-2.5 rounded-pill quick-template-btn d-inline-flex align-items-center gap-1"
                                    data-text="Incorrect department or project site assigned. Please re-assign."
                                    onclick="toggleReasonTemplate(this)">
                                <i class="fa-solid fa-check d-none template-check-icon text-white me-1"></i>
                                <span>📍 Wrong Site/Dept</span>
                            </button>
                            <button type="button" class="btn btn-xs btn-outline-secondary py-1 px-2.5 rounded-pill quick-template-btn d-inline-flex align-items-center gap-1"
                                    data-text="Educational certificate or professional license photo is missing/illegible."
                                    onclick="toggleReasonTemplate(this)">
                                <i class="fa-solid fa-check d-none template-check-icon text-white me-1"></i>
                                <span>📁 Missing Documents</span>
                            </button>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger btn-sm fw-bold">
                        <i class="fa-solid fa-paper-plane me-1"></i> Reject & Return Selected
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const selectAll = document.getElementById('selectAll');
    const checkboxes = document.querySelectorAll('.emp-checkbox');
    const bulkApproveBtn = document.getElementById('bulkApproveBtn');
    const bulkRejectBtn = document.getElementById('bulkRejectBtn');
    const countSpans = document.querySelectorAll('.selectedCount');

    function updateBulkBtns() {
        const checkedCount = document.querySelectorAll('.emp-checkbox:checked').length;
        countSpans.forEach(span => span.textContent = checkedCount);
        if (bulkApproveBtn) bulkApproveBtn.disabled = checkedCount === 0;
        if (bulkRejectBtn) bulkRejectBtn.disabled = checkedCount === 0;
    }

    if (selectAll) {
        selectAll.addEventListener('change', function() {
            checkboxes.forEach(cb => cb.checked = selectAll.checked);
            updateBulkBtns();
        });
    }

    checkboxes.forEach(cb => {
        cb.addEventListener('change', updateBulkBtns);
    });
});

function openRejectModal(id, name, code) {
    const form = document.getElementById('singleRejectForm');
    form.action = `/employees/${id}/reject`;
    document.getElementById('modalEmployeeName').textContent = name;
    document.getElementById('modalEmployeeCode').textContent = code;

    const modalEl = document.getElementById('rejectEmployeeModal');
    clearReasonTemplates(modalEl);

    const modal = new bootstrap.Modal(modalEl);
    modal.show();
}

function toggleReasonTemplate(btn) {
    const modal = btn.closest('.modal');
    const textarea = modal ? modal.querySelector('textarea[name="rejection_reason"]') : null;
    if (!textarea) return;

    const templateText = (btn.dataset.text || btn.textContent).trim();
    const isActive = btn.classList.contains('active');
    const checkIcon = btn.querySelector('.template-check-icon');

    let currentLines = textarea.value.split('\n').map(l => l.trim()).filter(l => l.length > 0);

    if (isActive) {
        // Deselect
        btn.classList.remove('active', 'btn-danger', 'text-white');
        btn.classList.add('btn-outline-secondary');
        if (checkIcon) checkIcon.classList.add('d-none');

        // Remove matching line (ignoring leading bullets)
        currentLines = currentLines.filter(line => {
            const cleanLine = line.replace(/^[•\-\*\d\.]+\s*/, '').trim();
            return cleanLine !== templateText;
        });
    } else {
        // Select
        btn.classList.add('active', 'btn-danger', 'text-white');
        btn.classList.remove('btn-outline-secondary');
        if (checkIcon) checkIcon.classList.remove('d-none');

        const exists = currentLines.some(line => {
            const cleanLine = line.replace(/^[•\-\*\d\.]+\s*/, '').trim();
            return cleanLine === templateText;
        });

        if (!exists) {
            currentLines.push('• ' + templateText);
        }
    }

    // Format lines: ensure bullets if multiple lines
    if (currentLines.length > 1) {
        currentLines = currentLines.map(line => {
            if (!line.startsWith('• ') && !line.startsWith('- ') && !/^\d+\./.test(line)) {
                return '• ' + line;
            }
            return line;
        });
    }

    textarea.value = currentLines.join('\n');
    textarea.focus();
}

function clearReasonTemplates(btnOrModal) {
    const modal = btnOrModal.closest ? btnOrModal.closest('.modal') : btnOrModal;
    if (!modal) return;
    const textarea = modal.querySelector('textarea[name="rejection_reason"]');
    if (textarea) textarea.value = '';

    modal.querySelectorAll('.quick-template-btn').forEach(btn => {
        btn.classList.remove('active', 'btn-danger', 'text-white');
        btn.classList.add('btn-outline-secondary');
        const checkIcon = btn.querySelector('.template-check-icon');
        if (checkIcon) checkIcon.classList.add('d-none');
    });
}

function submitBulkApprove() {
    if (confirm('Approve all selected employees?')) {
        document.getElementById('bulkApproveForm').submit();
    }
}

function openBulkRejectModal() {
    const checkedBoxes = document.querySelectorAll('.emp-checkbox:checked');
    if (checkedBoxes.length === 0) return;

    document.getElementById('bulkRejectCount').textContent = checkedBoxes.length;
    const container = document.getElementById('bulkEmployeeHiddenInputs');
    container.innerHTML = '';

    checkedBoxes.forEach(cb => {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'employee_ids[]';
        input.value = cb.value;
        container.appendChild(input);
    });

    const modalEl = document.getElementById('bulkRejectModal');
    clearReasonTemplates(modalEl);

    const modal = new bootstrap.Modal(modalEl);
    modal.show();
}
</script>
@endsection
