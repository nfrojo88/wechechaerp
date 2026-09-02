@extends('layouts.app')
@section('title', 'Manpower Reports Review')

@section('content')
<div class="container-fluid px-4 py-3">

    {{-- Header --}}
    <div class="d-flex justify-content-between align-items-start mb-4 flex-wrap gap-2">
        <div>
            <h4 class="fw-bold mb-1" style="color:#1e3a5f;">
                <i class="fa-solid fa-users-line text-primary me-2"></i>Morning Manpower Reports
            </h4>
            <p class="text-muted small mb-0">Review and approve or reject site engineer daily workforce reports</p>
        </div>
        <div class="d-flex gap-2">
            <span class="badge bg-warning text-dark px-3 py-2 rounded-pill fs-6 fw-bold">
                <i class="fa-solid fa-hourglass-half me-1"></i>{{ $pendingCount }} Pending
            </span>
            <span class="badge bg-info text-dark px-3 py-2 rounded-pill fs-6 fw-bold">
                <i class="fa-solid fa-calendar-day me-1"></i>{{ $todayCount }} Today
            </span>
        </div>
    </div>

    {{-- Alerts --}}
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm mb-4">
            <i class="fa-solid fa-circle-check me-2"></i>{{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    {{-- Filters --}}
    <div class="card shadow-sm border-0 rounded-3 mb-4">
        <div class="card-body py-3 px-4">
            <form method="GET" class="row g-2 align-items-end">
                <div class="col-md-3">
                    <label class="form-label small fw-semibold text-dark mb-1">Status</label>
                    <select name="status" class="form-select form-select-sm">
                        <option value="">All Statuses</option>
                        <option value="pending"  @selected(request('status') === 'pending')>⏳ Pending</option>
                        <option value="approved" @selected(request('status') === 'approved')>✅ Approved</option>
                        <option value="rejected" @selected(request('status') === 'rejected')>❌ Rejected</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-semibold text-dark mb-1">Project</label>
                    <select name="project_id" class="form-select form-select-sm">
                        <option value="">All Projects</option>
                        @foreach($projects as $proj)
                            <option value="{{ $proj->id }}" @selected(request('project_id') == $proj->id)>{{ $proj->name ?? $proj->project_name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-semibold text-dark mb-1">Date</label>
                    <input type="date" name="date" class="form-control form-control-sm" value="{{ request('date', date('Y-m-d')) }}">
                </div>
                <div class="col-md-3">
                    <button type="submit" class="btn btn-primary btn-sm px-4 w-100">
                        <i class="fa-solid fa-filter me-1"></i>Filter
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- Reports List --}}
    @forelse($reports as $report)
    <div class="card shadow-sm border-0 rounded-3 mb-3 {{ $report->status === 'pending' ? 'border-start border-4 border-warning' : ($report->status === 'approved' ? 'border-start border-4 border-success' : 'border-start border-4 border-danger') }}">
        <div class="card-body px-4 py-4">
            <div class="row align-items-start">
                {{-- Reporter Info --}}
                <div class="col-lg-4 mb-3 mb-lg-0">
                    <div class="d-flex align-items-center gap-3 mb-2">
                        <div class="rounded-circle bg-primary bg-opacity-10 d-flex align-items-center justify-content-center" style="width:48px;height:48px;">
                            <i class="fa-solid fa-hard-hat text-primary fs-5"></i>
                        </div>
                        <div>
                            <h6 class="fw-bold mb-0">{{ $report->submittedBy->name ?? 'Unknown' }}</h6>
                            <small class="text-muted">
                                <i class="fa-solid fa-building me-1"></i>{{ $report->project->name ?? 'N/A' }}
                            </small>
                        </div>
                    </div>
                    <div class="d-flex gap-3 flex-wrap">
                        <div class="text-muted small">
                            <i class="fa-solid fa-calendar me-1"></i>
                            <strong class="text-dark">{{ $report->report_date->format('D, d M Y') }}</strong>
                        </div>
                        <div class="text-muted small">
                            <i class="fa-solid fa-clock me-1"></i>Submitted {{ $report->created_at->format('h:i A') }}
                        </div>
                    </div>
                    @if($report->work_area)
                    <div class="mt-1 text-muted small">
                        <i class="fa-solid fa-location-dot me-1"></i>{{ $report->work_area }}
                    </div>
                    @endif
                </div>

                {{-- Workforce Counts --}}
                <div class="col-lg-4 mb-3 mb-lg-0">
                    <div class="row g-1 text-center">
                        @php
                            $cats = [
                                ['Skilled',     $report->skilled_workers,       'bg-primary'],
                                ['Unskilled',   $report->unskilled_workers,     'bg-warning text-dark'],
                                ['Supervisors', $report->supervisors,           'bg-success'],
                                ['Engineers',   $report->engineers,             'bg-info text-dark'],
                                ['Operators',   $report->operators,             'bg-danger'],
                                ['Laborers',    $report->daily_laborers,        'bg-secondary'],
                                ['Subcon',      $report->subcontractor_workers, 'bg-dark'],
                            ];
                        @endphp
                        @foreach($cats as [$label, $count, $bg])
                        <div class="col-4 col-sm-3">
                            <div class="bg-light rounded p-1 text-center">
                                <div class="fw-bold text-dark" style="font-size:1.1rem;">{{ $count }}</div>
                                <div class="text-muted" style="font-size:0.65rem;">{{ $label }}</div>
                            </div>
                        </div>
                        @endforeach
                        <div class="col-12 mt-1">
                            <span class="badge bg-primary px-4 py-2 fw-bold">
                                <i class="fa-solid fa-sigma me-1"></i>{{ $report->total_present }} Total Present
                            </span>
                        </div>
                    </div>
                </div>

                {{-- Actions --}}
                <div class="col-lg-4 text-lg-end">
                    @if($report->status === 'pending')
                    <div class="d-flex gap-2 justify-content-lg-end flex-wrap">
                        {{-- Approve --}}
                        <form method="POST" action="{{ route('planning-manager.manpower-report.review', $report) }}" class="d-inline">
                            @csrf
                            <input type="hidden" name="action" value="approve">
                            <button type="submit" class="btn btn-success px-3 fw-semibold shadow-sm"
                                onclick="return confirm('Approve this morning manpower report from {{ $report->submittedBy->name ?? 'Site Engineer' }}?')">
                                <i class="fa-solid fa-circle-check me-1"></i>Approve
                            </button>
                        </form>

                        {{-- Reject with reason --}}
                        <button type="button" class="btn btn-outline-danger px-3 fw-semibold" 
                            data-bs-toggle="modal" data-bs-target="#rejectModal{{ $report->id }}">
                            <i class="fa-solid fa-circle-xmark me-1"></i>Reject
                        </button>

                        {{-- View --}}
                        <a href="{{ route('manpower-daily-report.show', $report) }}" class="btn btn-light border px-3">
                            <i class="fa-solid fa-eye me-1"></i>View
                        </a>
                    </div>
                    @else
                    <div class="d-flex flex-column align-items-lg-end gap-1">
                        <span class="badge {{ $report->status_badge_class }} px-3 py-2 fs-6 rounded-pill">
                            {{ $report->status_label }}
                        </span>
                        @if($report->reviewed_at)
                        <small class="text-muted">By {{ $report->reviewer->name ?? '—' }} at {{ $report->reviewed_at->format('h:i A, d M') }}</small>
                        @endif
                        @if($report->review_notes)
                        <small class="text-muted fst-italic">"{{ \Illuminate\Support\Str::limit($report->review_notes, 60) }}"</small>
                        @endif
                        <a href="{{ route('manpower-daily-report.show', $report) }}" class="btn btn-sm btn-light border mt-1">
                            <i class="fa-solid fa-eye me-1"></i>View
                        </a>
                    </div>
                    @endif
                </div>
            </div>

            {{-- Notes/Challenges preview --}}
            @if($report->challenges || $report->planned_activities)
            <div class="mt-3 pt-3 border-top row g-2">
                @if($report->planned_activities)
                <div class="col-md-6">
                    <small class="fw-semibold text-muted d-block mb-1"><i class="fa-solid fa-list-check text-primary me-1"></i>Planned Activities</small>
                    <p class="small text-dark mb-0">{{ \Illuminate\Support\Str::limit($report->planned_activities, 120) }}</p>
                </div>
                @endif
                @if($report->challenges)
                <div class="col-md-6">
                    <small class="fw-semibold text-muted d-block mb-1"><i class="fa-solid fa-triangle-exclamation text-warning me-1"></i>Challenges</small>
                    <p class="small text-dark mb-0">{{ \Illuminate\Support\Str::limit($report->challenges, 120) }}</p>
                </div>
                @endif
            </div>
            @endif
        </div>
    </div>

    {{-- Reject Modal --}}
    @if($report->status === 'pending')
    <div class="modal fade" id="rejectModal{{ $report->id }}" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form method="POST" action="{{ route('planning-manager.manpower-report.review', $report) }}" class="modal-content border-0 shadow">
                @csrf
                <input type="hidden" name="action" value="reject">
                <div class="modal-header bg-danger text-white">
                    <h6 class="modal-title fw-bold">
                        <i class="fa-solid fa-circle-xmark me-2"></i>Reject Manpower Report
                    </h6>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <p class="text-muted small">Report by <strong>{{ $report->submittedBy->name ?? 'Site Engineer' }}</strong> for {{ $report->report_date->format('d M Y') }}</p>
                    <label class="form-label fw-semibold">Reason for Rejection</label>
                    <textarea name="review_notes" class="form-control" rows="3" placeholder="Provide reason for rejection..." required></textarea>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger btn-sm px-4">Confirm Rejection</button>
                </div>
            </form>
        </div>
    </div>
    @endif

    @empty
    <div class="card border-0 shadow-sm">
        <div class="card-body text-center py-5">
            <i class="fa-solid fa-inbox fa-3x text-success mb-3 d-block"></i>
            <h5 class="text-muted">No reports found</h5>
            <p class="text-muted small">{{ request('status') === 'pending' ? 'No pending manpower reports to review.' : 'No reports match your filters.' }}</p>
            @if(request('status'))
            <a href="{{ route('planning-manager.manpower-reports') }}" class="btn btn-sm btn-outline-primary mt-2">View All</a>
            @endif
        </div>
    </div>
    @endforelse

    {{-- Pagination --}}
    @if($reports->hasPages())
    <div class="mt-4">
        {{ $reports->links() }}
    </div>
    @endif
</div>
@endsection
