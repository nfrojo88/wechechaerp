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
            {{-- High-level Summary Metrics --}}
            <div class="row g-3 mb-4">
                <div class="col-sm-4">
                    <div class="p-3 bg-white rounded-3 border shadow-sm d-flex align-items-center justify-content-between">
                        <div>
                            <span class="text-muted small fw-semibold text-uppercase d-block" style="font-size:0.7rem; letter-spacing:0.5px;">Total on Site</span>
                            <h4 class="fw-bold mb-0 text-primary">{{ $manpowerDailyReport->total_present }}</h4>
                        </div>
                        <div class="rounded-circle p-2 d-flex align-items-center justify-content-center" style="background: rgba(37, 99, 235, 0.1); width: 42px; height: 42px;">
                            <i class="fa-solid fa-users text-primary"></i>
                        </div>
                    </div>
                </div>
                <div class="col-sm-4">
                    <div class="p-3 bg-white rounded-3 border shadow-sm d-flex align-items-center justify-content-between">
                        <div>
                            <span class="text-muted small fw-semibold text-uppercase d-block" style="font-size:0.7rem; letter-spacing:0.5px;">Company Labour</span>
                            <h4 class="fw-bold mb-0 text-warning">{{ $manpowerDailyReport->company_workers_count }}</h4>
                        </div>
                        <div class="rounded-circle p-2 d-flex align-items-center justify-content-center" style="background: rgba(245, 158, 11, 0.1); width: 42px; height: 42px;">
                            <i class="fa-solid fa-hard-hat text-warning"></i>
                        </div>
                    </div>
                </div>
                <div class="col-sm-4">
                    <div class="p-3 bg-white rounded-3 border shadow-sm d-flex align-items-center justify-content-between">
                        <div>
                            <span class="text-muted small fw-semibold text-uppercase d-block" style="font-size:0.7rem; letter-spacing:0.5px;">Subcontractors</span>
                            <h4 class="fw-bold mb-0 text-info">{{ $manpowerDailyReport->subcontractor_workers_count }}</h4>
                        </div>
                        <div class="rounded-circle p-2 d-flex align-items-center justify-content-center" style="background: rgba(6, 182, 212, 0.1); width: 42px; height: 42px;">
                            <i class="fa-solid fa-handshake text-info"></i>
                        </div>
                    </div>
                </div>
            </div>

            {{-- 1. Our Company Labour Breakdown --}}
            <div class="card shadow-sm border-0 rounded-3 mb-4">
                <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center">
                    <h6 class="fw-bold mb-0 text-dark"><i class="fa-solid fa-users-gear text-warning me-2"></i>Our Company Labour (Direct Workforce)</h6>
                    <span class="badge bg-warning bg-opacity-10 text-dark border border-warning border-opacity-25 px-3 py-1.5 fw-bold">
                        {{ $manpowerDailyReport->company_workers_count }} Workers
                    </span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        @if(!empty($manpowerDailyReport->roles_breakdown) && is_array($manpowerDailyReport->roles_breakdown) && count($manpowerDailyReport->roles_breakdown) > 0)
                        <table class="table table-hover table-bordered align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-3" style="width: 50px;">#</th>
                                    <th>Trade / Manpower Role</th>
                                    <th>Category</th>
                                    <th class="text-center" style="width: 150px;">Workers Present</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($manpowerDailyReport->roles_breakdown as $item)
                                <tr>
                                    <td class="ps-3 text-muted small">{{ $loop->iteration }}</td>
                                    <td class="fw-bold text-dark">
                                        <i class="fa-solid fa-user-gear text-primary me-2"></i>{{ $item['role_name'] ?? 'Trade' }}
                                    </td>
                                    <td>
                                        <span class="badge" style="background: #f1f5f9; color: #334155; border: 1px solid #cbd5e1; font-weight: 600;">
                                            {{ $item['category'] ?? 'General' }}
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 px-3 py-1 fw-bold fs-6">
                                            {{ $item['count'] ?? 0 }}
                                        </span>
                                    </td>
                                </tr>
                                @endforeach
                                <tr class="table-light fw-bold">
                                    <td colspan="3" class="ps-3 text-end"><i class="fa-solid fa-sigma me-1.5 text-primary"></i>Company Present:</td>
                                    <td class="text-center"><span class="badge bg-primary px-3 py-1.5 fs-6">{{ $manpowerDailyReport->company_workers_count }}</span></td>
                                </tr>
                                <tr class="table-danger">
                                    <td colspan="3" class="ps-3 text-end fw-semibold"><i class="fa-solid fa-user-xmark text-danger me-1.5"></i>Company Total Absent:</td>
                                    <td class="text-center"><span class="badge bg-danger px-3 py-1.5 fs-6">{{ $manpowerDailyReport->total_absent }}</span></td>
                                </tr>
                            </tbody>
                        </table>
                        @else
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
                                    <td class="ps-4"><i class="fa-solid fa-sigma me-2 text-primary"></i>Company Present</td>
                                    <td class="text-center"><span class="badge bg-primary px-3">{{ $manpowerDailyReport->company_workers_count }}</span></td>
                                </tr>
                                <tr class="table-danger">
                                    <td class="ps-4 fw-semibold"><i class="fa-solid fa-user-xmark text-danger me-2"></i>Total Absent</td>
                                    <td class="text-center"><span class="badge bg-danger px-3">{{ $manpowerDailyReport->total_absent }}</span></td>
                                </tr>
                            </tbody>
                        </table>
                        @endif
                    </div>
                </div>
            </div>

            {{-- 2. Subcontractor Manpower Breakdown --}}
            <div class="card shadow-sm border-0 rounded-3 mb-4">
                <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center">
                    <h6 class="fw-bold mb-0 text-dark"><i class="fa-solid fa-handshake text-info me-2"></i>Subcontractor Manpower (Subcon on Site)</h6>
                    <span class="badge bg-info bg-opacity-10 text-info border border-info border-opacity-25 px-3 py-1.5 fw-bold">
                        {{ $manpowerDailyReport->subcontractor_workers_count }} Subcon Workers
                    </span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        @if(!empty($manpowerDailyReport->subcontractors_breakdown) && is_array($manpowerDailyReport->subcontractors_breakdown) && count($manpowerDailyReport->subcontractors_breakdown) > 0)
                        <table class="table table-hover table-bordered align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-3" style="width: 50px;">#</th>
                                    <th>Subcontractor Name</th>
                                    <th>Role / Trade Designation</th>
                                    <th>Category</th>
                                    <th>Agreement No</th>
                                    <th class="text-center" style="width: 140px;">Workers</th>
                                    <th>Location / Notes</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($manpowerDailyReport->subcontractors_breakdown as $sub)
                                <tr>
                                    <td class="ps-3 text-muted small">{{ $loop->iteration }}</td>
                                    <td class="fw-bold text-dark">
                                        <i class="fa-solid fa-building text-info me-2"></i>{{ $sub['subcontractor_name'] ?? 'Subcontractor' }}
                                    </td>
                                    <td class="fw-semibold text-dark">
                                        <i class="fa-solid fa-user-gear text-primary me-1.5 small"></i>{{ $sub['role_name'] ?? $sub['trade'] ?? 'Trade' }}
                                    </td>
                                    <td>
                                        <span class="badge" style="background: #f1f5f9; color: #334155; border: 1px solid #cbd5e1; font-weight: 600;">
                                            {{ $sub['category'] ?? 'Skilled Labor' }}
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge bg-light text-dark border">
                                            {{ $sub['agreement_no'] ?? '—' }}
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge bg-info bg-opacity-10 text-info border border-info border-opacity-25 px-3 py-1 fw-bold fs-6">
                                            {{ $sub['workers_count'] ?? $sub['count'] ?? 0 }}
                                        </span>
                                    </td>
                                    <td class="small text-muted">{{ $sub['notes'] ?? '—' }}</td>
                                </tr>
                                @endforeach
                                <tr class="table-light fw-bold">
                                    <td colspan="5" class="ps-3 text-end"><i class="fa-solid fa-sigma me-1.5 text-info"></i>Total Subcon Present:</td>
                                    <td class="text-center"><span class="badge bg-info px-3 py-1.5 fs-6">{{ $manpowerDailyReport->subcontractor_workers_count }}</span></td>
                                    <td></td>
                                </tr>
                            </tbody>
                        </table>
                        @elseif($manpowerDailyReport->subcontractor_workers > 0)
                        <div class="p-3 text-center">
                            <span class="badge bg-info bg-opacity-10 text-info border border-info border-opacity-25 px-4 py-2 fs-6 fw-bold">
                                <i class="fa-solid fa-handshake me-1.5"></i>{{ $manpowerDailyReport->subcontractor_workers }} Subcontractor Workers reported
                            </span>
                        </div>
                        @else
                        <div class="p-4 text-center text-muted small">
                            <i class="fa-solid fa-handshake-slash fa-2x mb-2 d-block text-secondary opacity-50"></i>
                            No subcontractor manpower reported on site for this date.
                        </div>
                        @endif
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
