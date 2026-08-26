@extends('layouts.app')
@section('title', 'Coordinator Dashboard')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0 text-gray-800"><i class="fas fa-users-viewfinder text-primary me-2"></i> Coordinator Dashboard</h1>
        <span class="badge bg-secondary p-2">{{ now()->format('l, F j Y') }}</span>
    </div>

    <div class="row">
        <!-- Inventory Value -->
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-primary shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Global Inventory Value</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">ETB {{ number_format($kpi['total_inventory_value'] ?? 0, 2) }}</div>
                        </div>
                        <div class="col-auto"><i class="fas fa-boxes fa-2x text-gray-300"></i></div>
                    </div>
                </div>
            </div>
        </div>
        <!-- Active Schedules -->
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-success shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Active Schedules Today</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">{{ $kpi['schedules_today'] ?? 0 }}</div>
                        </div>
                        <div class="col-auto"><i class="fas fa-calendar-check fa-2x text-gray-300"></i></div>
                    </div>
                </div>
            </div>
        </div>
        <!-- Plans Awaiting Review -->
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-warning shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Plans Awaiting Approval</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">{{ $kpi['pending_plans'] ?? 0 }}</div>
                        </div>
                        <div class="col-auto"><i class="fas fa-tasks fa-2x text-gray-300"></i></div>
                    </div>
                </div>
            </div>
        </div>
        <!-- Pending MRs -->
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-danger shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">Pending Material Requests</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">{{ $kpi['material_requests'] ?? 0 }}</div>
                        </div>
                        <div class="col-auto"><i class="fas fa-cart-flatbed fa-2x text-gray-300"></i></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-8">
            <!-- Plans & Schedules Sent by Planning Manager -->
            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex justify-content-between align-items-center bg-white">
                    <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-clipboard-check me-2"></i> ERP Plans & Workflows Sent by Planning Manager</h6>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Project</th>
                                    <th>Plan Type</th>
                                    <th>Workflow Stage</th>
                                    <th>Planning Manager Note</th>
                                    <th class="text-end">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($pendingPlansFromPlanning ?? [] as $workflow)
                                <tr>
                                    <td class="fw-bold">{{ optional($workflow->project)->name ?? 'N/A' }}</td>
                                    <td><span class="badge bg-secondary text-uppercase">{{ $workflow->plan_type }}</span></td>
                                    <td>
                                        @if($workflow->status === 'planning_manager_approved')
                                            <span class="badge bg-warning text-dark"><i class="fas fa-hourglass-half me-1"></i> Awaiting Your Review</span>
                                        @else
                                            <span class="badge bg-info text-dark">{{ $workflow->nextStepLabel() }}</span>
                                        @endif
                                    </td>
                                    <td><small class="text-muted">{{ Str::limit($workflow->planning_manager_note ?? 'No notes provided', 30) }}</small></td>
                                    <td class="text-end">
                                        <a href="{{ route('plan-workflow.show', $workflow->project_id) }}" class="btn btn-sm btn-primary">
                                            <i class="fas fa-eye me-1"></i> Review Plan
                                        </a>
                                    </td>
                                </tr>
                                @empty
                                <tr><td colspan="5" class="text-center text-muted py-3">No ERP plan workflows currently submitted by Planning Manager.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Recent Daily Reports -->
            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex justify-content-between align-items-center bg-white">
                    <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-file-lines me-2"></i> Recent Daily Reports (From Sites)</h6>
                    <a href="{{ route('daily-reports.index') }}" class="btn btn-sm btn-outline-primary">View All</a>
                </div>
                <div class="card-body p-0">
                    <table class="table table-hover mb-0">
                        <thead class="table-light"><tr><th>Date</th><th>Project</th><th>Submitted By</th><th>Status</th></tr></thead>
                        <tbody>
                            @forelse($recentDailyReports ?? [] as $report)
                            <tr>
                                <td>{{ \Carbon\Carbon::parse($report->report_date)->format('M d, Y') }}</td>
                                <td>{{ optional($report->project)->name ?? 'N/A' }}</td>
                                <td>{{ optional($report->creator)->name ?? 'N/A' }}</td>
                                <td><span class="badge bg-{{ $report->status == 'approved' ? 'success' : 'warning' }}">{{ ucfirst($report->status ?? 'pending') }}</span></td>
                            </tr>
                            @empty
                            <tr><td colspan="4" class="text-center text-muted py-3">No recent daily reports found.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Recent Site Schedules -->
            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex justify-content-between align-items-center bg-white">
                    <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-calendar-days me-2"></i> Active Site Schedules</h6>
                    <a href="{{ route('schedules.index') }}" class="btn btn-sm btn-outline-primary">View All</a>
                </div>
                <div class="card-body p-0">
                    <table class="table table-hover mb-0">
                        <thead class="table-light"><tr><th>Schedule Title</th><th>Project</th><th>Duration</th><th>Status</th><th class="text-end">Action</th></tr></thead>
                        <tbody>
                            @forelse($recentSchedules ?? [] as $sched)
                            <tr>
                                <td class="fw-semibold">{{ $sched->name ?? $sched->title ?? 'Project Schedule' }}</td>
                                <td>{{ optional($sched->project)->name ?? 'N/A' }}</td>
                                <td><small>{{ optional($sched->start_date)->format('M d') }} - {{ optional($sched->end_date)->format('M d, Y') }}</small></td>
                                <td><span class="badge bg-{{ $sched->status == 'active' || $sched->status == 'approved' ? 'success' : 'secondary' }}">{{ ucfirst($sched->status ?? 'N/A') }}</span></td>
                                <td class="text-end">
                                    <a href="{{ route('schedules.show', $sched) }}" class="btn btn-sm btn-outline-info"><i class="fas fa-chart-gantt me-1"></i> View</a>
                                </td>
                            </tr>
                            @empty
                            <tr><td colspan="5" class="text-center text-muted py-3">No active site schedules found.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card shadow mb-4">
                <div class="card-header py-3 bg-white">
                    <h6 class="m-0 font-weight-bold text-primary">Coordinator Action Center</h6>
                </div>
                <div class="card-body p-0">
                    <div class="list-group list-group-flush">
                        <a href="{{ route('schedules.index') }}" class="list-group-item list-group-item-action d-flex align-items-center">
                            <i class="fas fa-calendar-alt text-success me-3"></i>
                            <div>
                                <h6 class="mb-0 fw-bold">Site Schedules</h6>
                                <small class="text-muted">Manage & view project timelines</small>
                            </div>
                        </a>
                        <a href="{{ route('inventory.index') }}" class="list-group-item list-group-item-action d-flex align-items-center">
                            <i class="fas fa-boxes text-info me-3"></i>
                            <div>
                                <h6 class="mb-0 fw-bold">All Site Inventory</h6>
                                <small class="text-muted">Track materials across all sites & main store</small>
                            </div>
                        </a>
                        <a href="{{ route('coordinator.forecast') }}" class="list-group-item list-group-item-action d-flex align-items-center">
                            <i class="fas fa-chart-pie text-warning me-3"></i>
                            <div>
                                <h6 class="mb-0 fw-bold">Forecast Demand</h6>
                                <small class="text-muted">Analyze Material, Equipment, Manpower</small>
                            </div>
                        </a>
                        <a href="{{ \Illuminate\Support\Facades\Route::has('office-requests.index') ? route('office-requests.index') : url('/office-requests') }}" class="list-group-item list-group-item-action d-flex align-items-center justify-content-between">
                            <div class="d-flex align-items-center">
                                <i class="fas fa-boxes-stacked text-warning me-3"></i>
                                <div>
                                    <h6 class="mb-0 fw-bold">Office Supply Requisitions</h6>
                                    <small class="text-muted">Secretary requests for HR & Coordinator review</small>
                                </div>
                            </div>
                            @if(($kpi['pending_office_reqs'] ?? 0) > 0)
                                <span class="badge bg-warning text-dark rounded-pill">{{ $kpi['pending_office_reqs'] }}</span>
                            @endif
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
