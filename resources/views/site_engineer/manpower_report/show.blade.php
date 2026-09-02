@extends('layouts.app')
@section('title', 'Manpower Report Detail')

@section('content')
<div class="container-fluid px-4 py-3">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div class="d-flex align-items-center">
            <a href="{{ route('manpower-daily-report.index') }}" class="btn btn-sm btn-outline-secondary me-3 shadow-sm">
                <i class="fa-solid fa-arrow-left"></i>
            </a>
            <div>
                <h4 class="fw-bold mb-1" style="color:#1e3a5f;">Manpower Report Detail</h4>
                <p class="text-muted small mb-0">
                    {{ $manpowerDailyReport->report_date->format('l, d M Y') }}
                    &bull; {{ $manpowerDailyReport->project->name ?? 'N/A' }}
                </p>
            </div>
        </div>
        <span class="badge {{ $manpowerDailyReport->status_badge_class }} px-3 py-2 fs-6 rounded-pill">
            {{ $manpowerDailyReport->status_label }}
        </span>
    </div>

    <div class="row g-4">
        <div class="col-lg-8">
            {{-- Workforce Summary --}}
            <div class="card shadow-sm border-0 rounded-3 mb-4">
                <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between">
                    <h6 class="fw-bold mb-0 text-dark"><i class="fa-solid fa-users text-primary me-2"></i>Workforce Breakdown</h6>
                    <span class="badge bg-primary px-3 py-2">Total: {{ $manpowerDailyReport->total_present }} Present</span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-bordered mb-0">
                            <tbody>
                                @php
                                    $rows = [
                                        ['label' => 'Skilled Workers',      'icon' => 'fa-helmet-safety',      'color' => 'text-primary',   'value' => $manpowerDailyReport->skilled_workers],
                                        ['label' => 'Unskilled Workers',     'icon' => 'fa-person-digging',     'color' => 'text-warning',   'value' => $manpowerDailyReport->unskilled_workers],
                                        ['label' => 'Supervisors',           'icon' => 'fa-user-tie',           'color' => 'text-success',   'value' => $manpowerDailyReport->supervisors],
                                        ['label' => 'Engineers',             'icon' => 'fa-screwdriver-wrench', 'color' => 'text-info',      'value' => $manpowerDailyReport->engineers],
                                        ['label' => 'Equipment Operators',   'icon' => 'fa-truck-monster',      'color' => 'text-danger',    'value' => $manpowerDailyReport->operators],
                                        ['label' => 'Daily Laborers',        'icon' => 'fa-hammer',             'color' => 'text-secondary', 'value' => $manpowerDailyReport->daily_laborers],
                                        ['label' => 'Subcontractor Workers', 'icon' => 'fa-handshake',          'color' => 'text-purple',    'value' => $manpowerDailyReport->subcontractor_workers],
                                    ];
                                @endphp
                                @foreach($rows as $row)
                                <tr>
                                    <td class="ps-4 py-2 fw-semibold small">
                                        <i class="fa-solid {{ $row['icon'] }} {{ $row['color'] }} me-2"></i>{{ $row['label'] }}
                                    </td>
                                    <td class="text-center fw-bold">
                                        <span class="badge bg-light text-dark border px-3">{{ $row['value'] }}</span>
                                    </td>
                                </tr>
                                @endforeach
                                <tr class="table-light fw-bold">
                                    <td class="ps-4"><i class="fa-solid fa-sigma me-2 text-primary"></i>Total Present</td>
                                    <td class="text-center"><span class="badge bg-primary px-3">{{ $manpowerDailyReport->total_present }}</span></td>
                                </tr>
                                <tr class="table-danger">
                                    <td class="ps-4 fw-semibold"><i class="fa-solid fa-user-xmark text-danger me-2"></i>Total Absent</td>
                                    <td class="text-center"><span class="badge bg-danger px-3">{{ $manpowerDailyReport->total_absent }}</span></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            {{-- Activities --}}
            <div class="card shadow-sm border-0 rounded-3 mb-4">
                <div class="card-header bg-white border-bottom py-3">
                    <h6 class="fw-bold mb-0 text-dark"><i class="fa-solid fa-clipboard-list text-success me-2"></i>Activities & Notes</h6>
                </div>
                <div class="card-body p-4">
                    @if($manpowerDailyReport->work_area)
                    <div class="mb-3">
                        <div class="text-muted small fw-semibold mb-1"><i class="fa-solid fa-location-dot me-1"></i>Work Area</div>
                        <p class="mb-0">{{ $manpowerDailyReport->work_area }}</p>
                    </div>
                    @endif
                    @if($manpowerDailyReport->planned_activities)
                    <div class="mb-3">
                        <div class="text-muted small fw-semibold mb-1"><i class="fa-solid fa-list-check me-1 text-primary"></i>Planned Activities</div>
                        <p class="mb-0">{{ $manpowerDailyReport->planned_activities }}</p>
                    </div>
                    @endif
                    @if($manpowerDailyReport->completed_activities)
                    <div class="mb-3">
                        <div class="text-muted small fw-semibold mb-1"><i class="fa-solid fa-square-check me-1 text-success"></i>Completed (Yesterday)</div>
                        <p class="mb-0">{{ $manpowerDailyReport->completed_activities }}</p>
                    </div>
                    @endif
                    @if($manpowerDailyReport->challenges)
                    <div class="mb-3">
                        <div class="text-muted small fw-semibold mb-1"><i class="fa-solid fa-triangle-exclamation me-1 text-warning"></i>Challenges</div>
                        <p class="mb-0">{{ $manpowerDailyReport->challenges }}</p>
                    </div>
                    @endif
                    @if($manpowerDailyReport->notes)
                    <div>
                        <div class="text-muted small fw-semibold mb-1"><i class="fa-solid fa-comment me-1"></i>Notes</div>
                        <p class="mb-0">{{ $manpowerDailyReport->notes }}</p>
                    </div>
                    @endif
                    @if(!$manpowerDailyReport->work_area && !$manpowerDailyReport->planned_activities && !$manpowerDailyReport->notes)
                    <p class="text-muted mb-0"><em>No activities or notes recorded.</em></p>
                    @endif
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            {{-- Submission Info --}}
            <div class="card shadow-sm border-0 rounded-3 mb-4">
                <div class="card-header bg-white border-bottom py-3">
                    <h6 class="fw-bold mb-0 text-dark"><i class="fa-solid fa-info-circle text-primary me-2"></i>Report Info</h6>
                </div>
                <div class="card-body p-4">
                    <div class="mb-3 d-flex justify-content-between">
                        <span class="text-muted small">Submitted By</span>
                        <span class="fw-semibold small">{{ $manpowerDailyReport->submittedBy->name ?? '—' }}</span>
                    </div>
                    <div class="mb-3 d-flex justify-content-between">
                        <span class="text-muted small">Submitted At</span>
                        <span class="fw-semibold small">{{ $manpowerDailyReport->created_at->format('h:i A, d M Y') }}</span>
                    </div>
                    <div class="mb-3 d-flex justify-content-between">
                        <span class="text-muted small">Project</span>
                        <span class="fw-semibold small">{{ $manpowerDailyReport->project->name ?? '—' }}</span>
                    </div>
                    <div class="d-flex justify-content-between">
                        <span class="text-muted small">Status</span>
                        <span class="badge {{ $manpowerDailyReport->status_badge_class }}">{{ $manpowerDailyReport->status_label }}</span>
                    </div>
                </div>
            </div>

            {{-- Planning Manager Review Block --}}
            @if($manpowerDailyReport->reviewer)
            <div class="card shadow-sm border-0 rounded-3 {{ $manpowerDailyReport->status === 'approved' ? 'border-success' : 'border-danger' }} border-opacity-50 mb-4">
                <div class="card-header py-3 {{ $manpowerDailyReport->status === 'approved' ? 'bg-success' : 'bg-danger' }} text-white">
                    <h6 class="fw-bold mb-0">
                        <i class="fa-solid fa-{{ $manpowerDailyReport->status === 'approved' ? 'circle-check' : 'circle-xmark' }} me-2"></i>
                        Planning Manager {{ $manpowerDailyReport->status === 'approved' ? 'Approved' : 'Rejected' }}
                    </h6>
                </div>
                <div class="card-body p-4">
                    <div class="mb-2 d-flex justify-content-between">
                        <span class="text-muted small">Reviewed By</span>
                        <span class="fw-semibold small">{{ $manpowerDailyReport->reviewer->name ?? '—' }}</span>
                    </div>
                    <div class="mb-3 d-flex justify-content-between">
                        <span class="text-muted small">Reviewed At</span>
                        <span class="fw-semibold small">{{ $manpowerDailyReport->reviewed_at?->format('h:i A, d M Y') ?? '—' }}</span>
                    </div>
                    @if($manpowerDailyReport->review_notes)
                    <div class="bg-light rounded p-3 mt-2">
                        <div class="text-muted small fw-semibold mb-1"><i class="fa-solid fa-comment-dots me-1"></i>Review Notes</div>
                        <p class="mb-0 small">{{ $manpowerDailyReport->review_notes }}</p>
                    </div>
                    @endif
                </div>
            </div>
            @endif
        </div>
    </div>
</div>
@endsection
