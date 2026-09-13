@extends('layouts.app')
@section('title', 'Weekly Progress Report')

@section('content')
<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Weekly Report: {{ $weeklyReport->week_start->format('M d') }} - {{ $weeklyReport->week_end->format('M d, Y') }}</h1>
        <div>
            <a href="{{ route('weekly-reports.index') }}" class="btn btn-sm btn-secondary shadow-sm">
                <i class="fas fa-arrow-left fa-sm text-white-50"></i> Back
            </a>
            <button class="btn btn-sm btn-primary shadow-sm"><i class="fas fa-print"></i> Print Report</button>
        </div>
    </div>

    <div class="row">
        <!-- Project & Status Info -->
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-primary shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Project</div>
                            <div class="h6 mb-0 font-weight-bold text-gray-800">{{ $weeklyReport->project->name }}</div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-building fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-info shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Status</div>
                            <div class="h6 mb-0 font-weight-bold text-gray-800">{{ ucfirst($weeklyReport->status) }}</div>
                            <div class="small text-muted mt-1">By: {{ $weeklyReport->createdBy->name ?? 'System' }}</div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-info-circle fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-success shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Planned Progress</div>
                            <div class="row no-gutters align-items-center">
                                <div class="col-auto">
                                    <div class="h5 mb-0 mr-3 font-weight-bold text-gray-800">{{ number_format($weeklyReport->planned_progress_percent, 1) }}%</div>
                                </div>
                                <div class="col">
                                    <div class="progress progress-sm mr-2">
                                        <div class="progress-bar bg-success" role="progressbar" @style(["width: {$weeklyReport->planned_progress_percent}%"])></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-bullseye fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-warning shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Actual Progress</div>
                            <div class="row no-gutters align-items-center">
                                <div class="col-auto">
                                    <div class="h5 mb-0 mr-3 font-weight-bold text-gray-800">{{ number_format($weeklyReport->actual_progress_percent, 1) }}%</div>
                                </div>
                                <div class="col">
                                    <div class="progress progress-sm mr-2">
                                        <div class="progress-bar bg-warning" role="progressbar" @style(["width: {$weeklyReport->actual_progress_percent}%"])></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-chart-line fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Content Row -->
    <div class="row">
        <div class="col-lg-6">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-file-alt mr-2"></i> Executive Summary</h6>
                </div>
                <div class="card-body">
                    <p class="text-justify">{{ $weeklyReport->executive_summary ?? 'No executive summary provided.' }}</p>
                </div>
            </div>
            
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-info"><i class="fas fa-forward mr-2"></i> Plan for Next Week</h6>
                </div>
                <div class="card-body">
                    <p class="text-justify">{{ $weeklyReport->next_week_plan ?? 'No plan outlined for next week.' }}</p>
                </div>
            </div>
        </div>
        
        <div class="col-lg-6">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-danger"><i class="fas fa-exclamation-triangle mr-2"></i> Critical Issues & Delays</h6>
                </div>
                <div class="card-body">
                    @if($weeklyReport->critical_issues)
                        <div class="alert alert-danger border-left-danger bg-white text-dark shadow-sm">
                            {!! nl2br(e($weeklyReport->critical_issues)) !!}
                        </div>
                    @else
                        <p class="text-muted">No critical issues or delays reported this week.</p>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <!-- Attached Daily Reports Section for Planning Review -->
    <div class="card shadow mb-4">
        <div class="card-header py-3 d-flex flex-wrap align-items-center justify-content-between">
            <div class="d-flex align-items-center flex-wrap gap-2">
                <h6 class="m-0 font-weight-bold text-primary mr-2">
                    <i class="fas fa-calendar-day mr-1"></i> Attached Daily Reports for this Week
                </h6>
                <span class="badge badge-secondary mr-2" style="font-size: 0.75rem;">Read-Only Site Log</span>
                <span class="badge badge-primary mr-1">{{ count($dailyReports) }} Day{{ count($dailyReports) != 1 ? 's' : '' }} Attached</span>
                <span class="badge badge-info"><i class="fas fa-users mr-1"></i>{{ $dailyReports->sum('total_manpower') }} Total Manpower</span>
            </div>
            <div>
                @php
                    $totalTasks = $dailyReports->reduce(fn($c, $r) => $c + $r->items->count(), 0);
                @endphp
                <span class="small font-weight-bold text-gray-700 mr-2">Total Tasks Logged: {{ $totalTasks }}</span>
            </div>
        </div>
        <div class="card-body">
            @forelse($dailyReports as $index => $dr)
                <div class="card mb-3 border-left-primary shadow-sm">
                    <div class="card-header bg-light py-2 px-3 d-flex flex-wrap align-items-center justify-content-between">
                        <div class="d-flex align-items-center flex-wrap gap-2">
                            <span class="font-weight-bold text-gray-800 mr-2">
                                <i class="far fa-calendar-alt text-primary mr-1"></i>{{ $dr->report_date ? $dr->report_date->format('l, M d, Y') : 'N/A' }}
                            </span>
                            @if($dr->status == 'approved')
                                <span class="badge badge-success">Approved</span>
                            @elseif($dr->status == 'submitted')
                                <span class="badge badge-info">Submitted</span>
                            @else
                                <span class="badge badge-secondary">Draft</span>
                            @endif
                            <span class="badge badge-primary ml-1"><i class="fas fa-users mr-1"></i>{{ $dr->total_manpower ?? 0 }} Workers</span>
                            @if($dr->weather_conditions)
                                <span class="badge badge-light border ml-1">
                                    <i class="fas fa-cloud-sun text-warning mr-1"></i>{{ $dr->weather_conditions }} {{ $dr->temperature ? $dr->temperature . '°C' : '' }}
                                </span>
                            @endif
                        </div>
                        <div class="d-flex align-items-center gap-2 small">
                            <span class="text-muted mr-2">
                                <i class="fas fa-user-edit mr-1"></i>{{ $dr->createdBy->name ?? 'Site Engineer' }}
                            </span>
                            <a href="{{ route('daily-reports.show', $dr) }}" class="btn btn-xs btn-outline-primary shadow-sm" target="_blank">
                                <i class="fas fa-external-link-alt mr-1"></i> Full Report
                            </a>
                        </div>
                    </div>
                    <div class="card-body p-3">
                        <div class="table-responsive mb-2">
                            <table class="table table-bordered table-sm mb-0">
                                <thead class="thead-light small">
                                    <tr>
                                        <th class="text-center" style="width: 40px;">#</th>
                                        <th>Work Description</th>
                                        <th class="text-center" style="width: 100px;">Qty Done</th>
                                        <th class="text-center" style="width: 90px;">Workers</th>
                                        <th style="width: 180px;">Equipment Used</th>
                                        <th>Issues / Delays</th>
                                    </tr>
                                </thead>
                                <tbody class="small">
                                    @forelse($dr->items as $itemIdx => $item)
                                    <tr>
                                        <td class="text-center font-weight-bold">{{ $itemIdx + 1 }}</td>
                                        <td>{{ $item->work_description ?? '-' }}</td>
                                        <td class="text-center">{{ $item->qty_completed > 0 ? number_format($item->qty_completed, 2) : '-' }}</td>
                                        <td class="text-center">{{ $item->workers_count > 0 ? $item->workers_count : '-' }}</td>
                                        <td>{{ $item->equipment_used ?? '-' }}</td>
                                        <td class="{{ $item->issues ? 'text-danger font-weight-bold' : 'text-muted' }}">{{ $item->issues ?? '-' }}</td>
                                    </tr>
                                    @empty
                                    <tr>
                                        <td colspan="6" class="text-center text-muted py-2">No task items recorded for this day.</td>
                                    </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>

                        @if($dr->general_notes)
                            <div class="mb-1">
                                <small class="font-weight-bold text-dark">General Notes:</small> 
                                <span class="small text-muted">{{ $dr->general_notes }}</span>
                            </div>
                        @endif
                        @if($dr->safety_incidents)
                            <div class="mb-1 text-danger">
                                <small class="font-weight-bold"><i class="fas fa-exclamation-triangle mr-1"></i>Safety Incidents:</small> 
                                <span class="small">{{ $dr->safety_incidents }}</span>
                            </div>
                        @endif
                        @if($dr->site_diary_remark)
                            <div class="mb-1">
                                <small class="font-weight-bold text-dark">Site Diary Remark:</small> 
                                <span class="small text-muted">{{ $dr->site_diary_remark }}</span>
                            </div>
                        @endif
                        @if($dr->site_book_pic)
                            <div class="mt-2">
                                <a href="{{ uploaded_asset($dr->site_book_pic) }}" target="_blank" class="btn btn-xs btn-outline-info">
                                    <i class="fas fa-image mr-1"></i> View Site Book Photo
                                </a>
                            </div>
                        @endif
                    </div>
                </div>
            @empty
                <div class="alert alert-light border text-center py-4 mb-0 text-muted">
                    <i class="fas fa-info-circle fa-2x mb-2 text-secondary"></i>
                    <p class="mb-0">No daily reports were filed or attached for this week.</p>
                </div>
            @endforelse
        </div>
    </div>
</div>
@endsection
