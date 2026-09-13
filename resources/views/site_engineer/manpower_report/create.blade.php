@extends('layouts.app')
@section('title', 'Morning Manpower Report')

@section('content')
<div class="container-fluid px-4 py-3">

    {{-- Header --}}
    <div class="d-flex justify-content-between align-items-start mb-4 flex-wrap gap-2">
        <div>
            <h4 class="fw-bold mb-1" style="color:#1e3a5f;">
                <i class="fa-solid fa-users-line text-primary me-2"></i>Morning Manpower Report
            </h4>
            <p class="text-muted small mb-0">Submit your site workforce count every morning for Planning Manager review</p>
        </div>
        <div class="d-flex gap-2 align-items-center">
            <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 px-3 py-2 fw-semibold rounded-pill">
                <i class="fa-solid fa-calendar-day me-1"></i>{{ now()->format('l, d M Y') }}
            </span>
            <a href="{{ route('manpower-daily-report.index') }}" class="btn btn-sm btn-outline-secondary">
                <i class="fa-solid fa-clock-rotate-left me-1"></i> My Reports
            </a>
        </div>
    </div>

    {{-- Alerts --}}
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm mb-4">
            <i class="fa-solid fa-circle-check me-2"></i>{{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm mb-4">
            <i class="fa-solid fa-circle-exclamation me-2"></i>{{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    {{-- Already submitted notice --}}
    @if($todayReport)
    <div class="alert border-0 shadow-sm mb-4 {{ $todayReport->status === 'approved' ? 'alert-success' : ($todayReport->status === 'rejected' ? 'alert-danger' : 'alert-info') }}">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div>
                <i class="fa-solid fa-{{ $todayReport->status === 'approved' ? 'circle-check' : ($todayReport->status === 'rejected' ? 'circle-xmark' : 'hourglass-half') }} me-2"></i>
                <strong>Today's report already submitted at {{ $todayReport->created_at->format('h:i A') }}</strong>
                — Status: <span class="badge {{ $todayReport->status_badge_class }} ms-1">{{ $todayReport->status_label }}</span>
                @if($todayReport->review_notes)
                    <br><small class="text-muted mt-1 d-block"><i class="fa-solid fa-comment-dots me-1"></i>{{ $todayReport->review_notes }}</small>
                @endif
            </div>
            <a href="{{ route('manpower-daily-report.show', $todayReport) }}" class="btn btn-sm btn-outline-primary">
                <i class="fa-solid fa-eye me-1"></i>View Report
            </a>
        </div>
    </div>
    @endif

    @if($errors->any())
        <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm mb-4">
            <strong><i class="fa-solid fa-circle-exclamation me-2"></i>Please fix these errors:</strong>
            <ul class="mb-0 mt-1 small">
                @foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach
            </ul>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <div class="row g-4">
        {{-- Form Card --}}
        <div class="col-lg-8">
            <form method="POST" action="{{ route('manpower-daily-report.store') }}">
                @csrf

                {{-- Project & Date --}}
                <div class="card shadow-sm border-0 rounded-3 mb-4">
                    <div class="card-header bg-white border-bottom py-3">
                        <h6 class="fw-bold mb-0 text-dark">
                            <i class="fa-solid fa-building-user text-primary me-2"></i>Project & Report Date
                        </h6>
                    </div>
                    <div class="card-body p-4">
                        <div class="row g-3">
                            <div class="col-md-7">
                                <label class="form-label fw-semibold text-dark small">Project <span class="text-danger">*</span></label>
                                <select name="project_id" class="form-select @error('project_id') is-invalid @enderror" required onchange="checkTodayReport(this.value)">
                                    <option value="">— Select Project —</option>
                                    @foreach($projects as $project)
                                        <option value="{{ $project->id }}" @selected(old('project_id', $selectedProjectId) == $project->id)>
                                            {{ $project->name ?? $project->project_name }} ({{ $project->code ?? 'PRJ-'.$project->id }})
                                        </option>
                                    @endforeach
                                </select>
                                @error('project_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-5">
                                <label class="form-label fw-semibold text-dark small">Report Date <span class="text-danger">*</span></label>
                                <input type="date" name="report_date" class="form-control @error('report_date') is-invalid @enderror"
                                    value="{{ old('report_date', date('Y-m-d')) }}" max="{{ date('Y-m-d') }}" required>
                                @error('report_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Workforce Count by Selected Trades & Roles --}}
                <div class="card shadow-sm border-0 rounded-3 mb-4">
                    <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <div>
                            <h6 class="fw-bold mb-0 text-dark">
                                <i class="fa-solid fa-users-gear text-warning me-2"></i>Workforce Count by Trade / Role
                            </h6>
                            <small class="text-muted">Select manpower roles present on site today and specify headcount</small>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <button type="button" class="btn btn-outline-success btn-sm shadow-sm fw-semibold" data-bs-toggle="modal" data-bs-target="#addNewRoleModal">
                                <i class="fa-solid fa-user-plus me-1"></i>Add New Role
                            </button>
                            <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 fw-bold px-3 py-2 fs-6">
                                Total Present: <span id="totalPresent">0</span>
                            </span>
                        </div>
                    </div>
                    <div class="card-body p-4">
                        <div class="table-responsive mb-3">
                            <table class="table table-sm align-middle table-hover mb-0" id="rolesTable">
                                <thead class="table-light">
                                    <tr>
                                        <th style="min-width: 240px;">Role / Trade Designation <span class="text-danger">*</span></th>
                                        <th style="width: 170px;">Category</th>
                                        <th style="width: 180px;" class="text-center">Workers Present <span class="text-danger">*</span></th>
                                        <th style="width: 60px;" class="text-center"></th>
                                    </tr>
                                </thead>
                                <tbody id="rolesTableBody">
                                    {{-- Dynamically populated rows --}}
                                </tbody>
                            </table>
                        </div>

                        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 pt-2 border-top">
                            <button type="button" class="btn btn-outline-primary btn-sm fw-semibold shadow-sm" id="btnAddRoleRow" onclick="addRoleRow()">
                                <i class="fa-solid fa-plus me-1"></i>Add Another Trade / Role
                            </button>

                            <div class="d-flex align-items-center gap-2">
                                <label class="form-label fw-semibold small text-dark mb-0">
                                    <i class="fa-solid fa-user-xmark text-danger me-1"></i>Total Absent:
                                </label>
                                <div class="input-group input-group-sm" style="width: 130px;">
                                    <button type="button" class="btn btn-outline-secondary" onclick="adjustCount('total_absent', -1)">
                                        <i class="fa-solid fa-minus"></i>
                                    </button>
                                    <input type="number" name="total_absent" id="total_absent"
                                        class="form-control text-center fw-bold fs-6 @error('total_absent') is-invalid @enderror"
                                        value="{{ old('total_absent', 0) }}" min="0" max="999">
                                    <button type="button" class="btn btn-outline-secondary" onclick="adjustCount('total_absent', 1)">
                                        <i class="fa-solid fa-plus"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Work Area & Activities --}}
                <div class="card shadow-sm border-0 rounded-3 mb-4">
                    <div class="card-header bg-white border-bottom py-3">
                        <h6 class="fw-bold mb-0 text-dark">
                            <i class="fa-solid fa-clipboard-list text-success me-2"></i>Work Area & Today's Activities
                        </h6>
                    </div>
                    <div class="card-body p-4">
                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label fw-semibold small text-dark">Work Area / Zone</label>
                                <input type="text" name="work_area" class="form-control @error('work_area') is-invalid @enderror"
                                    placeholder="e.g. 3rd Floor Slab, Foundation Zone A, Block B Plastering..."
                                    value="{{ old('work_area') }}">
                                @error('work_area')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold small text-dark">
                                    <i class="fa-solid fa-list-check text-primary me-1"></i>Planned Activities Today
                                </label>
                                <textarea name="planned_activities" class="form-control" rows="3"
                                    placeholder="What tasks are planned for today?">{{ old('planned_activities') }}</textarea>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold small text-dark">
                                    <i class="fa-solid fa-square-check text-success me-1"></i>Completed Activities (Yesterday)
                                </label>
                                <textarea name="completed_activities" class="form-control" rows="3"
                                    placeholder="What was accomplished yesterday?">{{ old('completed_activities') }}</textarea>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold small text-dark">
                                    <i class="fa-solid fa-triangle-exclamation text-warning me-1"></i>Challenges / Blockers
                                </label>
                                <textarea name="challenges" class="form-control" rows="2"
                                    placeholder="Any obstacles blocking progress?">{{ old('challenges') }}</textarea>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold small text-dark">
                                    <i class="fa-solid fa-comment me-1 text-muted"></i>Additional Notes
                                </label>
                                <textarea name="notes" class="form-control" rows="2"
                                    placeholder="Any other important notes...">{{ old('notes') }}</textarea>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Submit --}}
                <div class="d-flex justify-content-end gap-2">
                    <a href="{{ route('dashboard') }}" class="btn btn-outline-secondary px-4">Cancel</a>
                    <button type="submit" class="btn btn-primary px-5 py-2 fw-bold shadow-sm" {{ $todayReport ? 'disabled' : '' }}>
                        <i class="fa-solid fa-paper-plane me-2"></i>Send to Planning Manager
                    </button>
                </div>
            </form>
        </div>

        {{-- Right Column: Recent Reports --}}
        <div class="col-lg-4">
            <div class="card shadow-sm border-0 rounded-3 sticky-top" style="top:20px;">
                <div class="card-header bg-white border-bottom py-3">
                    <h6 class="fw-bold mb-0 text-dark">
                        <i class="fa-solid fa-clock-rotate-left text-primary me-2"></i>Recent Reports
                    </h6>
                </div>
                <div class="list-group list-group-flush">
                    @forelse($recentReports as $report)
                    <a href="{{ route('manpower-daily-report.show', $report) }}"
                       class="list-group-item list-group-item-action px-4 py-3 d-flex justify-content-between align-items-center {{ $report->report_date->isToday() ? 'bg-primary bg-opacity-5' : '' }}">
                        <div>
                            <div class="fw-semibold small text-dark">{{ $report->report_date->format('D, d M Y') }}</div>
                            <div class="text-muted" style="font-size: 0.75rem;">
                                <i class="fa-solid fa-users me-1"></i>{{ $report->total_present }} present
                                &bull; {{ $report->project->name ?? 'N/A' }}
                            </div>
                        </div>
                        <span class="badge {{ $report->status_badge_class }} rounded-pill">{{ $report->status_label }}</span>
                    </a>
                    @empty
                    <div class="list-group-item text-center text-muted py-4">
                        <i class="fa-solid fa-inbox fa-2x mb-2 d-block"></i>
                        No reports yet
                    </div>
                    @endforelse
                </div>
                @if($recentReports->count() >= 10)
                <div class="card-footer bg-white text-center py-2">
                    <a href="{{ route('manpower-daily-report.index') }}" class="text-primary small fw-semibold text-decoration-none">
                        View All Reports →
                    </a>
                </div>
                @endif
            </div>
        </div>
    </div>
</div>

{{-- ── Quick Add Manpower Role Modal (Image 2 design) ── --}}
<div class="modal fade" id="addNewRoleModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <form id="newRoleForm" onsubmit="submitNewRole(event)">
                @csrf
                <div class="modal-header py-3 px-4 bg-white border-bottom">
                    <div class="d-flex align-items-center gap-2">
                        <div class="rounded-circle p-2 d-flex align-items-center justify-content-center shadow-sm" style="background: rgba(16, 185, 129, 0.12); width: 38px; height: 38px;">
                            <i class="fa-solid fa-user-plus text-success" style="font-size: 1rem;"></i>
                        </div>
                        <div>
                            <h5 class="modal-title fw-bold text-dark mb-0">Add New Manpower Role</h5>
                            <span class="small text-muted" style="font-size: 0.78rem;">Create a custom trade/designation for workforce reporting</span>
                        </div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4 bg-white">
                    <div id="newRoleErrorAlert" class="alert alert-danger py-2 small d-none"></div>
                    <div class="mb-3">
                        <label class="form-label fw-bold small text-uppercase" style="color: #334155;">Role / Designation Title <span class="text-danger">*</span></label>
                        <input type="text" id="modalRoleName" name="name" class="form-control rounded-3" placeholder="e.g. Senior Mason, Electrician, Helper, Steel Fixer" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold small text-uppercase" style="color: #334155;">Unit of Measure</label>
                        <select id="modalRoleUnit" name="default_unit" class="form-select rounded-3">
                            <option value="day" selected>day (man-day)</option>
                            <option value="hr">hr (man-hour)</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold small text-uppercase" style="color: #334155;">Category</label>
                        <input type="text" id="modalRoleCategory" name="category" class="form-control rounded-3" placeholder="e.g. Skilled Labor, Technical, Unskilled, Equipment Operator" list="roleCategorySuggestions">
                        <datalist id="roleCategorySuggestions">
                            <option value="Skilled Labor">
                            <option value="Unskilled Labor">
                            <option value="Daily Laborer">
                            <option value="Supervisory">
                            <option value="Equipment Operator">
                            <option value="Subcontractor">
                            <option value="Technical">
                        </datalist>
                    </div>
                </div>
                <div class="modal-footer py-3 px-4" style="background: #f8fafc; border-top: 1px solid #e2e8f0;">
                    <button type="button" class="btn btn-light border px-4 fw-semibold" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" id="btnSaveNewRole" class="btn btn-success fw-bold px-4 shadow-sm" style="background: linear-gradient(135deg, #059669 0%, #10b981 100%); border: none;">
                        <i class="fa-solid fa-check me-1.5"></i>Create Role
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
let MANPOWER_ROLES = @json($manpowerRoles ?? []);

// Default seed roles if system list is empty
if (!MANPOWER_ROLES || MANPOWER_ROLES.length === 0) {
    MANPOWER_ROLES = [
        { id: 1, name: 'Senior Mason', category: 'Skilled Labor' },
        { id: 2, name: 'Mason', category: 'Skilled Labor' },
        { id: 3, name: 'Carpenter', category: 'Skilled Labor' },
        { id: 4, name: 'Bar Bender / Steel Fixer', category: 'Skilled Labor' },
        { id: 5, name: 'Electrician', category: 'Skilled Labor' },
        { id: 6, name: 'Plumber', category: 'Skilled Labor' },
        { id: 7, name: 'Painter', category: 'Skilled Labor' },
        { id: 8, name: 'Helper / Assistant', category: 'Unskilled Labor' },
        { id: 9, name: 'Daily Laborer', category: 'Daily Laborer' },
        { id: 10, name: 'Equipment Operator', category: 'Equipment Operator' },
        { id: 11, name: 'Site Supervisor / Foreman', category: 'Supervisory' },
        { id: 12, name: 'Subcontractor Worker', category: 'Subcontractor' }
    ];
}

let roleRowIndex = 0;

function buildRoleOptions(selectedName = '') {
    let html = '<option value="">— Select Trade / Role —</option>';
    MANPOWER_ROLES.forEach(r => {
        const isSel = (r.name.toLowerCase() === selectedName.toLowerCase()) ? 'selected' : '';
        html += `<option value="${escapeHtml(r.name)}" data-id="${r.id || ''}" data-category="${escapeHtml(r.category || 'Skilled Labor')}" ${isSel}>${escapeHtml(r.name)} (${escapeHtml(r.category || 'General')})</option>`;
    });
    return html;
}

function escapeHtml(text) {
    if (!text) return '';
    return String(text).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}

function addRoleRow(data = null) {
    const tbody = document.getElementById('rolesTableBody');
    if (!tbody) return;

    const idx = roleRowIndex++;
    const initialName = data?.role_name || '';
    const initialCategory = data?.category || 'Skilled Labor';
    const initialCount = data?.count !== undefined ? data.count : 1;
    const initialRoleId = data?.role_id || '';

    const tr = document.createElement('tr');
    tr.id = `roleRow_${idx}`;
    tr.innerHTML = `
        <td>
            <select name="roles[${idx}][role_name]" class="form-select form-select-sm role-select fw-semibold" onchange="onRoleChanged(this, ${idx})" required>
                ${buildRoleOptions(initialName)}
            </select>
            <input type="hidden" name="roles[${idx}][role_id]" class="role-id-input" value="${initialRoleId}">
        </td>
        <td>
            <span class="badge role-category-badge" style="background: #f1f5f9; color: #334155; border: 1px solid #cbd5e1; font-weight: 600;">
                ${escapeHtml(initialCategory)}
            </span>
            <input type="hidden" name="roles[${idx}][category]" class="role-category-input" value="${escapeHtml(initialCategory)}">
        </td>
        <td class="text-center">
            <div class="input-group input-group-sm mx-auto" style="max-width: 140px;">
                <button type="button" class="btn btn-outline-secondary" onclick="stepRoleRow(this, -1)">
                    <i class="fa-solid fa-minus"></i>
                </button>
                <input type="number" name="roles[${idx}][count]" class="form-control text-center fw-bold fs-6 role-count-input"
                       value="${initialCount}" min="0" max="999" oninput="updateTotal()">
                <button type="button" class="btn btn-outline-secondary" onclick="stepRoleRow(this, 1)">
                    <i class="fa-solid fa-plus"></i>
                </button>
            </div>
        </td>
        <td class="text-center">
            <button type="button" class="btn btn-outline-danger btn-sm py-1 px-2 shadow-sm" onclick="removeRoleRow(this)" title="Remove trade">
                <i class="fa-solid fa-trash-can"></i>
            </button>
        </td>
    `;
    tbody.appendChild(tr);

    // Trigger category update if preselected
    const sel = tr.querySelector('.role-select');
    if (sel && sel.value) {
        onRoleChanged(sel, idx);
    }

    updateTotal();
}

function onRoleChanged(selectEl, idx) {
    const tr = selectEl.closest('tr');
    if (!tr) return;

    const opt = selectEl.selectedOptions[0];
    const category = opt ? opt.dataset.category || 'Skilled Labor' : 'Skilled Labor';
    const roleId = opt ? opt.dataset.id || '' : '';

    const badge = tr.querySelector('.role-category-badge');
    const catInput = tr.querySelector('.role-category-input');
    const idInput = tr.querySelector('.role-id-input');

    if (badge) badge.textContent = category;
    if (catInput) catInput.value = category;
    if (idInput) idInput.value = roleId;

    updateTotal();
}

function stepRoleRow(btn, delta) {
    const input = btn.closest('.input-group')?.querySelector('.role-count-input');
    if (!input) return;
    const current = parseInt(input.value) || 0;
    input.value = Math.max(0, Math.min(999, current + delta));
    updateTotal();
}

function removeRoleRow(btn) {
    const tbody = document.getElementById('rolesTableBody');
    const tr = btn.closest('tr');
    if (!tbody || !tr) return;

    if (tbody.querySelectorAll('tr').length > 1) {
        tr.remove();
    } else {
        // Keep at least 1 row, clear selection
        const sel = tr.querySelector('.role-select');
        const cnt = tr.querySelector('.role-count-input');
        if (sel) sel.selectedIndex = 0;
        if (cnt) cnt.value = 1;
        onRoleChanged(sel, 0);
    }
    updateTotal();
}

function adjustCount(fieldName, delta) {
    const inp = document.getElementById(fieldName);
    if (!inp) return;
    const newVal = Math.max(0, Math.min(999, (parseInt(inp.value) || 0) + delta));
    inp.value = newVal;
}

function updateTotal() {
    let total = 0;
    document.querySelectorAll('.role-count-input').forEach(inp => {
        const row = inp.closest('tr');
        const sel = row ? row.querySelector('.role-select') : null;
        if (sel && sel.value) {
            total += (parseInt(inp.value) || 0);
        }
    });

    const badge = document.getElementById('totalPresent');
    if (badge) badge.textContent = total;
}

function submitNewRole(e) {
    e.preventDefault();
    const btn = document.getElementById('btnSaveNewRole');
    const errAlert = document.getElementById('newRoleErrorAlert');
    const nameInp = document.getElementById('modalRoleName');
    const unitInp = document.getElementById('modalRoleUnit');
    const catInp = document.getElementById('modalRoleCategory');

    errAlert.classList.add('d-none');
    errAlert.textContent = '';

    const name = nameInp.value.trim();
    if (!name) {
        errAlert.textContent = 'Please enter a role designation title.';
        errAlert.classList.remove('d-none');
        return;
    }

    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Creating...';

    fetch("{{ route('manpower-roles.store') }}", {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
            "Accept": "application/json",
            "X-CSRF-TOKEN": "{{ csrf_token() }}"
        },
        body: JSON.stringify({
            name: name,
            default_unit: unitInp.value,
            category: catInp.value || 'Skilled Labor'
        })
    })
    .then(res => res.json())
    .then(data => {
        btn.disabled = false;
        btn.innerHTML = '<i class="fa-solid fa-check me-1.5"></i>Create Role';

        if (data.success && data.role) {
            const newRole = data.role;
            MANPOWER_ROLES.push(newRole);
            MANPOWER_ROLES.sort((a, b) => a.name.localeCompare(b.name));

            // Refresh all dropdown options
            document.querySelectorAll('.role-select').forEach(sel => {
                const currentVal = sel.value;
                sel.innerHTML = buildRoleOptions(currentVal);
            });

            // Find an empty row or add a new one with this new role selected
            let targetRow = null;
            document.querySelectorAll('#rolesTableBody tr').forEach(tr => {
                const sel = tr.querySelector('.role-select');
                if (sel && !sel.value && !targetRow) {
                    targetRow = tr;
                }
            });

            if (targetRow) {
                const sel = targetRow.querySelector('.role-select');
                sel.value = newRole.name;
                onRoleChanged(sel, 0);
            } else {
                addRoleRow({ role_id: newRole.id, role_name: newRole.name, category: newRole.category || 'Skilled Labor', count: 1 });
            }

            // Close modal
            const modalEl = document.getElementById('addNewRoleModal');
            const modal = bootstrap.Modal.getInstance(modalEl);
            if (modal) modal.hide();

            // Clear inputs
            nameInp.value = '';
            catInp.value = '';

            updateTotal();
        } else {
            errAlert.textContent = data.message || 'Unable to create role.';
            errAlert.classList.remove('d-none');
        }
    })
    .catch(err => {
        btn.disabled = false;
        btn.innerHTML = '<i class="fa-solid fa-check me-1.5"></i>Create Role';
        errAlert.textContent = 'Server error occurred while creating role.';
        errAlert.classList.remove('d-none');
    });
}

document.addEventListener('DOMContentLoaded', function() {
    const oldRoles = @json(old('roles', []));
    if (oldRoles && oldRoles.length > 0) {
        oldRoles.forEach(r => addRoleRow(r));
    } else {
        // Start with 2 initial trade rows for fast entry
        addRoleRow({ role_name: 'Senior Mason', category: 'Skilled Labor', count: 0 });
        addRoleRow({ role_name: 'Helper / Assistant', category: 'Unskilled Labor', count: 0 });
    }
    updateTotal();
});
</script>
@endsection
