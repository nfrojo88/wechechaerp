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

                {{-- Workforce Count --}}
                <div class="card shadow-sm border-0 rounded-3 mb-4">
                    <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="fw-bold mb-0 text-dark">
                                <i class="fa-solid fa-hard-hat text-warning me-2"></i>Workforce Count by Category
                            </h6>
                            <small class="text-muted">Enter the number of workers present on site today</small>
                        </div>
                        <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 fw-bold px-3 py-2 fs-6">
                            Total Present: <span id="totalPresent">0</span>
                        </span>
                    </div>
                    <div class="card-body p-4">
                        <div class="row g-3">
                            @php
                                $categories = [
                                    ['name' => 'skilled_workers',       'label' => 'Skilled Workers',       'icon' => 'fa-helmet-safety',      'color' => 'text-primary'],
                                    ['name' => 'unskilled_workers',      'label' => 'Unskilled Workers',      'icon' => 'fa-person-digging',     'color' => 'text-warning'],
                                    ['name' => 'supervisors',            'label' => 'Supervisors',            'icon' => 'fa-user-tie',           'color' => 'text-success'],
                                    ['name' => 'engineers',              'label' => 'Engineers',              'icon' => 'fa-screwdriver-wrench', 'color' => 'text-info'],
                                    ['name' => 'operators',              'label' => 'Equipment Operators',    'icon' => 'fa-truck-monster',      'color' => 'text-danger'],
                                    ['name' => 'daily_laborers',         'label' => 'Daily Laborers',         'icon' => 'fa-hammer',             'color' => 'text-secondary'],
                                    ['name' => 'subcontractor_workers',  'label' => 'Subcontractor Workers',  'icon' => 'fa-handshake',          'color' => 'text-purple'],
                                ];
                            @endphp

                            @foreach($categories as $cat)
                            <div class="col-md-6">
                                <label class="form-label fw-semibold small text-dark">
                                    <i class="fa-solid {{ $cat['icon'] }} {{ $cat['color'] }} me-1"></i>{{ $cat['label'] }}
                                </label>
                                <div class="input-group">
                                    <button type="button" class="btn btn-outline-secondary btn-sm px-3" onclick="adjustCount('{{ $cat['name'] }}', -1)">
                                        <i class="fa-solid fa-minus"></i>
                                    </button>
                                    <input type="number" name="{{ $cat['name'] }}" id="{{ $cat['name'] }}"
                                        class="form-control text-center fw-bold fs-5 worker-count-input @error($cat['name']) is-invalid @enderror"
                                        value="{{ old($cat['name'], 0) }}" min="0" max="999" oninput="updateTotal()">
                                    <button type="button" class="btn btn-outline-secondary btn-sm px-3" onclick="adjustCount('{{ $cat['name'] }}', 1)">
                                        <i class="fa-solid fa-plus"></i>
                                    </button>
                                </div>
                                @error($cat['name'])<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                            </div>
                            @endforeach

                            <div class="col-md-6">
                                <label class="form-label fw-semibold small text-dark">
                                    <i class="fa-solid fa-user-xmark text-danger me-1"></i>Total Absent
                                </label>
                                <div class="input-group">
                                    <button type="button" class="btn btn-outline-secondary btn-sm px-3" onclick="adjustCount('total_absent', -1)">
                                        <i class="fa-solid fa-minus"></i>
                                    </button>
                                    <input type="number" name="total_absent" id="total_absent"
                                        class="form-control text-center fw-bold fs-5 @error('total_absent') is-invalid @enderror"
                                        value="{{ old('total_absent', 0) }}" min="0" max="999">
                                    <button type="button" class="btn btn-outline-secondary btn-sm px-3" onclick="adjustCount('total_absent', 1)">
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

<script>
function adjustCount(fieldName, delta) {
    const inp = document.getElementById(fieldName);
    if (!inp) return;
    const newVal = Math.max(0, Math.min(999, (parseInt(inp.value) || 0) + delta));
    inp.value = newVal;
    updateTotal();
}

function updateTotal() {
    const fields = ['skilled_workers', 'unskilled_workers', 'supervisors', 'engineers', 'operators', 'daily_laborers', 'subcontractor_workers'];
    let total = 0;
    fields.forEach(f => {
        const inp = document.getElementById(f);
        total += inp ? (parseInt(inp.value) || 0) : 0;
    });
    const badge = document.getElementById('totalPresent');
    if (badge) badge.textContent = total;
}

function checkTodayReport(projectId) {
    // Visual hint if project changes - actual check is server-side
}

document.addEventListener('DOMContentLoaded', updateTotal);
</script>
@endsection
