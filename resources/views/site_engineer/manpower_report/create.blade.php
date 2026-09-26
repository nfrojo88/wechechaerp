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

                {{-- Real-time Workforce Summary Banner --}}
                <div class="row g-3 mb-4">
                    <div class="col-md-4">
                        <div class="p-3 bg-white rounded-3 border shadow-sm d-flex align-items-center justify-content-between">
                            <div>
                                <span class="text-muted small fw-semibold text-uppercase d-block" style="font-size:0.72rem; letter-spacing: 0.5px;">Total Present on Site</span>
                                <h3 class="fw-bold mb-0 text-primary" id="summaryGrandTotal">0</h3>
                            </div>
                            <div class="rounded-circle p-2 d-flex align-items-center justify-content-center shadow-sm" style="background: rgba(37, 99, 235, 0.1); width: 44px; height: 44px;">
                                <i class="fa-solid fa-users text-primary fs-5"></i>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="p-3 bg-white rounded-3 border shadow-sm d-flex align-items-center justify-content-between">
                            <div>
                                <span class="text-muted small fw-semibold text-uppercase d-block" style="font-size:0.72rem; letter-spacing: 0.5px;">Our Company Labour</span>
                                <h3 class="fw-bold mb-0 text-warning" id="summaryCompanyTotal">0</h3>
                            </div>
                            <div class="rounded-circle p-2 d-flex align-items-center justify-content-center shadow-sm" style="background: rgba(245, 158, 11, 0.1); width: 44px; height: 44px;">
                                <i class="fa-solid fa-hard-hat text-warning fs-5"></i>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="p-3 bg-white rounded-3 border shadow-sm d-flex align-items-center justify-content-between">
                            <div>
                                <span class="text-muted small fw-semibold text-uppercase d-block" style="font-size:0.72rem; letter-spacing: 0.5px;">Subcontractor Workforce</span>
                                <h3 class="fw-bold mb-0 text-info" id="summarySubconTotal">0</h3>
                            </div>
                            <div class="rounded-circle p-2 d-flex align-items-center justify-content-center shadow-sm" style="background: rgba(6, 182, 212, 0.1); width: 44px; height: 44px;">
                                <i class="fa-solid fa-handshake text-info fs-5"></i>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- SECTION 1: Our Company Labour (Direct Workforce) --}}
                <div class="card shadow-sm border-0 rounded-3 mb-4">
                    <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <div>
                            <h6 class="fw-bold mb-0 text-dark">
                                <i class="fa-solid fa-users-gear text-warning me-2"></i>Our Company Labour (Direct Workforce)
                            </h6>
                            <small class="text-muted">Select company manpower roles present on site today and specify headcount</small>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <button type="button" class="btn btn-outline-success btn-sm shadow-sm fw-semibold" data-bs-toggle="modal" data-bs-target="#addNewRoleModal">
                                <i class="fa-solid fa-user-plus me-1"></i>Add New Role
                            </button>
                            <span class="badge bg-warning bg-opacity-10 text-dark border border-warning border-opacity-25 fw-bold px-3 py-2 fs-6">
                                Company Labour: <span id="companyPresentBadge">0</span>
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
                                    <i class="fa-solid fa-user-xmark text-danger me-1"></i>Company Total Absent:
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

                {{-- SECTION 2: Subcontractor Manpower (Subcon on Site) --}}
                <div class="card shadow-sm border-0 rounded-3 mb-4">
                    <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <div>
                            <h6 class="fw-bold mb-0 text-dark">
                                <i class="fa-solid fa-handshake text-info me-2"></i>Subcontractor Manpower (Subcon on Site)
                            </h6>
                            <small class="text-muted">Select registered project subcontractors from agreements and record deployed headcount</small>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <button type="button" class="btn btn-outline-info btn-sm shadow-sm fw-semibold" onclick="addSubconRow(null, true)">
                                <i class="fa-solid fa-plus me-1"></i>Add Custom Subcon
                            </button>
                            <span class="badge bg-info bg-opacity-10 text-info border border-info border-opacity-25 fw-bold px-3 py-2 fs-6">
                                Subcon Total: <span id="subconPresent">0</span>
                            </span>
                        </div>
                    </div>
                    <div class="card-body p-4">
                        <div class="table-responsive mb-3">
                            <table class="table table-sm align-middle table-hover mb-0" id="subconTable">
                                <thead class="table-light">
                                    <tr>
                                        <th style="min-width: 240px;">Subcontractor Name <span class="text-danger">*</span></th>
                                        <th style="width: 200px;">Agreement & Trade / Scope</th>
                                        <th style="width: 170px;" class="text-center">Workers Present <span class="text-danger">*</span></th>
                                        <th style="min-width: 180px;">Scope / Location Notes</th>
                                        <th style="width: 50px;" class="text-center"></th>
                                    </tr>
                                </thead>
                                <tbody id="subconTableBody">
                                    {{-- Dynamically populated subcon rows --}}
                                </tbody>
                            </table>
                        </div>

                        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 pt-2 border-top">
                            <button type="button" class="btn btn-outline-info btn-sm fw-semibold shadow-sm" id="btnAddSubconRow" onclick="addSubconRow()">
                                <i class="fa-solid fa-plus me-1"></i>Add Subcontractor
                            </button>
                            <small class="text-muted">
                                <i class="fa-solid fa-link me-1 text-primary"></i>Subcons link to active project subcon agreements automatically.
                            </small>
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
let ALL_SUBCONS = @json($subconAgreements ?? []);
let CURRENT_PROJECT_ID = parseInt('{{ $selectedProjectId ?? 0 }}') || 0;

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
let subconRowIndex = 0;

function checkTodayReport(projectId) {
    if (projectId) {
        window.location.href = "{{ route('manpower-daily-report.create') }}?project_id=" + encodeURIComponent(projectId);
    }
}

function escapeHtml(text) {
    if (!text) return '';
    return String(text).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}

// ─── COMPANY LABOUR (Trade / Role) LOGIC ──────────────────────────────────────
function buildRoleOptions(selectedName = '') {
    let html = '<option value="">— Select Trade / Role —</option>';
    MANPOWER_ROLES.forEach(r => {
        const isSel = (r.name.toLowerCase() === selectedName.toLowerCase()) ? 'selected' : '';
        html += `<option value="${escapeHtml(r.name)}" data-id="${r.id || ''}" data-category="${escapeHtml(r.category || 'Skilled Labor')}" ${isSel}>${escapeHtml(r.name)} (${escapeHtml(r.category || 'General')})</option>`;
    });
    return html;
}

function addRoleRow(data = null) {
    const tbody = document.getElementById('rolesTableBody');
    if (!tbody) return;

    const idx = roleRowIndex++;
    const initialName = data?.role_name || '';
    const initialCategory = data?.category || 'Skilled Labor';
    const initialCount = data?.count !== undefined ? data.count : 0;
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
        const sel = tr.querySelector('.role-select');
        const cnt = tr.querySelector('.role-count-input');
        if (sel) sel.selectedIndex = 0;
        if (cnt) cnt.value = 0;
        onRoleChanged(sel, 0);
    }
    updateTotal();
}

// ─── SUBCONTRACTOR MANPOWER LOGIC ─────────────────────────────────────────────
function getSubconsForCurrentProject() {
    if (!CURRENT_PROJECT_ID) return ALL_SUBCONS;
    const filtered = ALL_SUBCONS.filter(s => parseInt(s.project_id) === CURRENT_PROJECT_ID);
    return filtered.length > 0 ? filtered : ALL_SUBCONS;
}

function buildSubconOptions(selectedAgreementId = '', selectedName = '') {
    const list = getSubconsForCurrentProject();
    let html = '<option value="">— Select Subcontractor / Agreement —</option>';

    list.forEach(s => {
        const isSel = (selectedAgreementId && String(s.id) === String(selectedAgreementId)) ||
                      (!selectedAgreementId && selectedName && s.subcontractor_name.toLowerCase() === selectedName.toLowerCase()) ? 'selected' : '';
        html += `<option value="${s.id}" data-name="${escapeHtml(s.subcontractor_name)}" data-agreement-no="${escapeHtml(s.agreement_no)}" data-trade="${escapeHtml(s.trade)}" ${isSel}>${escapeHtml(s.subcontractor_name)} (${escapeHtml(s.agreement_no)} - ${escapeHtml(s.trade)})</option>`;
    });

    const isCustom = selectedAgreementId === 'custom' || (!selectedAgreementId && selectedName && !list.some(s => s.subcontractor_name.toLowerCase() === selectedName.toLowerCase()));
    html += `<option value="custom" ${isCustom ? 'selected' : ''}>+ Custom / Other Subcontractor</option>`;
    return html;
}

function addSubconRow(data = null, forceCustom = false) {
    const tbody = document.getElementById('subconTableBody');
    if (!tbody) return;

    const idx = subconRowIndex++;
    const initialAgreementId = forceCustom ? 'custom' : (data?.agreement_id || '');
    const initialName = data?.subcontractor_name || '';
    const initialAgreementNo = data?.agreement_no || '';
    const initialTrade = data?.trade || '';
    const initialCount = data?.workers_count !== undefined ? data.workers_count : 0;
    const initialNotes = data?.notes || '';
    const isCustom = initialAgreementId === 'custom' || forceCustom;

    const tr = document.createElement('tr');
    tr.id = `subconRow_${idx}`;
    tr.innerHTML = `
        <td style="min-width: 240px;">
            <select class="form-select form-select-sm subcon-agreement-select fw-semibold" onchange="onSubconSelected(this, ${idx})">
                ${buildSubconOptions(initialAgreementId, initialName)}
            </select>
            <input type="text" name="subcontractors[${idx}][subcontractor_name]"
                   class="form-control form-control-sm subcon-name-input mt-1.5 ${isCustom ? '' : 'd-none'}"
                   placeholder="Enter subcontractor name..."
                   value="${escapeHtml(initialName)}"
                   oninput="updateTotal()">
            <input type="hidden" name="subcontractors[${idx}][agreement_id]" class="subcon-agreement-id-input" value="${escapeHtml(initialAgreementId === 'custom' ? '' : initialAgreementId)}">
        </td>
        <td style="min-width: 190px;">
            <div class="subcon-trade-display small text-dark fw-semibold">
                ${escapeHtml(initialTrade || '—')}
            </div>
            <div class="subcon-agreement-no small text-muted" style="font-size:0.75rem;">
                ${initialAgreementNo ? escapeHtml(initialAgreementNo) : ''}
            </div>
            <input type="text" name="subcontractors[${idx}][trade]" class="form-control form-control-sm subcon-trade-input mt-1.5 ${isCustom ? '' : 'd-none'}" placeholder="Trade / Scope of work..." value="${escapeHtml(initialTrade)}">
            <input type="hidden" name="subcontractors[${idx}][agreement_no]" class="subcon-agreement-no-input" value="${escapeHtml(initialAgreementNo)}">
        </td>
        <td class="text-center" style="width: 150px;">
            <div class="input-group input-group-sm mx-auto" style="max-width: 130px;">
                <button type="button" class="btn btn-outline-secondary" onclick="stepSubconRow(this, -1)">
                    <i class="fa-solid fa-minus"></i>
                </button>
                <input type="number" name="subcontractors[${idx}][workers_count]" class="form-control text-center fw-bold fs-6 subcon-count-input"
                       value="${initialCount}" min="0" max="999" oninput="updateTotal()">
                <button type="button" class="btn btn-outline-secondary" onclick="stepSubconRow(this, 1)">
                    <i class="fa-solid fa-plus"></i>
                </button>
            </div>
        </td>
        <td style="min-width: 180px;">
            <input type="text" name="subcontractors[${idx}][notes]" class="form-control form-control-sm" placeholder="e.g. 3rd floor block work..." value="${escapeHtml(initialNotes)}">
        </td>
        <td class="text-center" style="width: 50px;">
            <button type="button" class="btn btn-outline-danger btn-sm py-1 px-2 shadow-sm" onclick="removeSubconRow(this)" title="Remove subcontractor">
                <i class="fa-solid fa-trash-can"></i>
            </button>
        </td>
    `;
    tbody.appendChild(tr);

    const sel = tr.querySelector('.subcon-agreement-select');
    if (sel && sel.value && !isCustom) {
        onSubconSelected(sel, idx);
    } else if (isCustom && initialName) {
        const nameInput = tr.querySelector('.subcon-name-input');
        if (nameInput) nameInput.value = initialName;
    }
    updateTotal();
}

function onSubconSelected(selectEl, idx) {
    const tr = selectEl.closest('tr');
    if (!tr) return;

    const opt = selectEl.selectedOptions[0];
    const val = selectEl.value;
    const nameInput = tr.querySelector('.subcon-name-input');
    const idInput = tr.querySelector('.subcon-agreement-id-input');
    const tradeDisplay = tr.querySelector('.subcon-trade-display');
    const tradeInput = tr.querySelector('.subcon-trade-input');
    const agreementDisplay = tr.querySelector('.subcon-agreement-no');
    const agreementInput = tr.querySelector('.subcon-agreement-no-input');

    if (val === 'custom') {
        nameInput.classList.remove('d-none');
        tradeInput.classList.remove('d-none');
        tradeDisplay.textContent = '';
        agreementDisplay.textContent = 'Custom Subcontractor';
        idInput.value = '';
        agreementInput.value = '';
    } else if (opt && val) {
        nameInput.classList.add('d-none');
        tradeInput.classList.add('d-none');
        nameInput.value = opt.dataset.name || '';
        idInput.value = val;
        tradeDisplay.textContent = opt.dataset.trade || '—';
        tradeInput.value = opt.dataset.trade || '';
        agreementDisplay.textContent = opt.dataset.agreementNo || '';
        agreementInput.value = opt.dataset.agreementNo || '';
    } else {
        nameInput.classList.add('d-none');
        tradeInput.classList.add('d-none');
        nameInput.value = '';
        idInput.value = '';
        tradeDisplay.textContent = '—';
        tradeInput.value = '';
        agreementDisplay.textContent = '';
        agreementInput.value = '';
    }
    updateTotal();
}

function stepSubconRow(btn, delta) {
    const input = btn.closest('.input-group')?.querySelector('.subcon-count-input');
    if (!input) return;
    const current = parseInt(input.value) || 0;
    input.value = Math.max(0, Math.min(999, current + delta));
    updateTotal();
}

function removeSubconRow(btn) {
    const tbody = document.getElementById('subconTableBody');
    const tr = btn.closest('tr');
    if (!tbody || !tr) return;

    if (tbody.querySelectorAll('tr').length > 1) {
        tr.remove();
    } else {
        const sel = tr.querySelector('.subcon-agreement-select');
        const cnt = tr.querySelector('.subcon-count-input');
        const name = tr.querySelector('.subcon-name-input');
        if (sel) sel.selectedIndex = 0;
        if (cnt) cnt.value = 0;
        if (name) name.value = '';
        onSubconSelected(sel, 0);
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
    let companyTotal = 0;
    document.querySelectorAll('.role-count-input').forEach(inp => {
        const row = inp.closest('tr');
        const sel = row ? row.querySelector('.role-select') : null;
        if (sel && sel.value) {
            companyTotal += (parseInt(inp.value) || 0);
        }
    });

    let subconTotal = 0;
    document.querySelectorAll('.subcon-count-input').forEach(inp => {
        const row = inp.closest('tr');
        const sel = row ? row.querySelector('.subcon-agreement-select') : null;
        const nameInp = row ? row.querySelector('.subcon-name-input') : null;
        const hasSelection = sel && ((sel.value && sel.value !== 'custom') || (sel.value === 'custom' && nameInp && nameInp.value.trim()));
        if (hasSelection) {
            subconTotal += (parseInt(inp.value) || 0);
        }
    });

    const grandTotal = companyTotal + subconTotal;

    // Update Company Present badges
    const companyPresentBadge = document.getElementById('companyPresentBadge');
    if (companyPresentBadge) companyPresentBadge.textContent = companyTotal;

    // Update Subcon Present badge
    const subconPresent = document.getElementById('subconPresent');
    if (subconPresent) subconPresent.textContent = subconTotal;

    // Update Top Summary Banner
    const summaryGrand = document.getElementById('summaryGrandTotal');
    if (summaryGrand) summaryGrand.textContent = grandTotal;

    const summaryCompany = document.getElementById('summaryCompanyTotal');
    if (summaryCompany) summaryCompany.textContent = companyTotal;

    const summarySubcon = document.getElementById('summarySubconTotal');
    if (summarySubcon) summarySubcon.textContent = subconTotal;
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
    // 1. Initialize Company Labour
    const oldRoles = @json(old('roles', []));
    if (oldRoles && oldRoles.length > 0) {
        oldRoles.forEach(r => addRoleRow(r));
    } else {
        addRoleRow({ role_name: 'Senior Mason', category: 'Skilled Labor', count: 0 });
        addRoleRow({ role_name: 'Helper / Assistant', category: 'Unskilled Labor', count: 0 });
    }

    // 2. Initialize Subcontractor Workforce
    const oldSubcons = @json(old('subcontractors', []));
    if (oldSubcons && oldSubcons.length > 0) {
        oldSubcons.forEach(s => addSubconRow(s));
    } else {
        const projectSubcons = getSubconsForCurrentProject();
        if (projectSubcons && projectSubcons.length > 0 && CURRENT_PROJECT_ID) {
            // Prepopulate up to 3 active agreements for quick input
            projectSubcons.slice(0, 3).forEach(s => {
                addSubconRow({
                    agreement_id: s.id,
                    subcontractor_name: s.subcontractor_name,
                    agreement_no: s.agreement_no,
                    trade: s.trade,
                    workers_count: 0
                });
            });
        } else {
            addSubconRow();
        }
    }

    updateTotal();
});
</script>
@endsection
