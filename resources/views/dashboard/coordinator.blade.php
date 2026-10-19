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
        <!-- Emergency Requisitions Approved by Planning -->
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-danger shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">Emergency Requests (From Planning)</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">{{ $kpi['approved_emergency_requests'] ?? 0 }}</div>
                        </div>
                        <div class="col-auto"><i class="fas fa-bolt fa-2x text-danger"></i></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-8">
            <!-- Emergency Requests Approved by Planning (Awaiting Coordinator Review & Dispatch) -->
            <div class="card shadow mb-4 border-left-danger" id="emergencySection">
                <div class="card-header py-3 d-flex justify-content-between align-items-center bg-white flex-wrap gap-2">
                    <div>
                        <h6 class="m-0 font-weight-bold text-danger">
                            <i class="fas fa-bolt me-2"></i>Emergency Requisitions Approved by Planning (Awaiting Your Dispatch)
                        </h6>
                        <small class="text-muted">High-priority requests approved by Planning Manager requiring your dispatch to Store or PR buy.</small>
                    </div>
                    @php
                        $totalCoordEmergency = ($approvedEmergencyRequests->count() ?? 0) + ($pendingCoordinatorPrs->count() ?? 0);
                    @endphp
                    @if($totalCoordEmergency > 0)
                        <span class="badge bg-danger rounded-pill px-3 py-2">{{ $totalCoordEmergency }} Action Needed</span>
                    @endif
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Ref / PR No</th>
                                    <th>Project &amp; Store</th>
                                    <th>Items Summary</th>
                                    <th>Status</th>
                                    <th>Approved Date</th>
                                    <th class="text-end">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @php $renderedItems = 0; @endphp
                                @foreach($approvedEmergencyRequests as $emr)
                                    @php
                                        $renderedItems++;
                                        $cPr = $emr->purchaseRequests->first();
                                    @endphp
                                    <tr class="table-danger bg-opacity-10">
                                        <td>
                                            <strong class="text-dark">{{ $emr->reference_number }}</strong>
                                            @if($cPr)
                                                <div class="small">
                                                    <a href="{{ route('purchase-requests.show', $cPr->id) }}" class="badge bg-light text-primary border text-decoration-none">
                                                        PR #{{ $cPr->pr_no }}
                                                    </a>
                                                </div>
                                            @endif
                                        </td>
                                        <td>
                                            <strong>{{ $emr->project?->name ?? 'General Project' }}</strong>
                                            <small class="text-muted d-block"><i class="fas fa-warehouse me-1"></i>Store: {{ $emr->store?->name ?? 'N/A' }}</small>
                                        </td>
                                        <td>
                                            <span class="badge bg-light text-dark border">{{ $emr->items->count() }} item(s)</span>
                                            <small class="text-muted d-block">{{ Str::limit($emr->items->map(fn($it) => $it->product?->name)->filter()->join(', '), 25, '...') }}</small>
                                        </td>
                                        <td>
                                            <span class="badge bg-success-subtle text-success border border-success-subtle">
                                                <i class="fas fa-check-circle me-1"></i>Planning Approved
                                            </span>
                                        </td>
                                        <td><small class="text-muted">{{ optional($emr->planning_approved_at)->format('M d, Y') ?? $emr->created_at->format('M d, Y') }}</small></td>
                                        <td class="text-end">
                                            @if($cPr)
                                                <form method="POST" action="{{ route('purchase-requests.coordinator-approve', $cPr) }}" class="d-inline">
                                                    @csrf
                                                    <button type="submit" class="btn btn-sm btn-primary fw-bold shadow-xs">
                                                        <i class="fas fa-share me-1"></i> Dispatch
                                                    </button>
                                                </form>
                                                <a href="{{ route('purchase-requests.show', $cPr->id) }}" class="btn btn-sm btn-outline-info">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                            @else
                                                <form method="POST" action="{{ route('material-requests.coordinator-dispatch', $emr) }}" class="d-inline">
                                                    @csrf
                                                    <button type="submit" class="btn btn-sm btn-primary fw-bold shadow-xs">
                                                        <i class="fas fa-share me-1"></i> Dispatch
                                                    </button>
                                                </form>
                                                <a href="{{ route('material-requests.show', $emr) }}" class="btn btn-sm btn-outline-info">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach

                                @foreach($pendingCoordinatorPrs as $cPr)
                                    @if($approvedEmergencyRequests->contains('id', $cPr->material_request_id))
                                        @continue
                                    @endif
                                    @php $renderedItems++; @endphp
                                    <tr class="table-warning bg-opacity-10">
                                        <td>
                                            <a href="{{ route('purchase-requests.show', $cPr->id) }}" class="fw-bold text-decoration-none">
                                                {{ $cPr->pr_no }}
                                            </a>
                                            @if($cPr->materialRequest)
                                                <div class="small">
                                                    <span class="badge bg-light text-secondary border">#{{ $cPr->materialRequest->reference_number }}</span>
                                                </div>
                                            @endif
                                        </td>
                                        <td>
                                            <strong>{{ $cPr->project?->name ?? 'General Project' }}</strong>
                                            <small class="text-muted d-block"><i class="fas fa-warehouse me-1"></i>Store: {{ $cPr->store?->name ?? 'N/A' }}</small>
                                        </td>
                                        <td>
                                            <span class="badge bg-light text-dark border">{{ $cPr->items->count() }} item(s)</span>
                                            <small class="text-muted d-block">{{ Str::limit($cPr->items->map(fn($it) => $it->product?->name)->filter()->join(', '), 25, '...') }}</small>
                                        </td>
                                        <td>
                                            <span class="badge bg-warning text-dark"><i class="fas fa-hourglass-half me-1"></i>Awaiting Dispatch</span>
                                        </td>
                                        <td><small class="text-muted">{{ $cPr->created_at->format('M d, Y') }}</small></td>
                                        <td class="text-end">
                                            <form method="POST" action="{{ route('purchase-requests.coordinator-approve', $cPr) }}" class="d-inline">
                                                @csrf
                                                <button type="submit" class="btn btn-sm btn-primary fw-bold shadow-xs">
                                                    <i class="fas fa-share me-1"></i> Dispatch
                                                </button>
                                            </form>
                                            <a href="{{ route('purchase-requests.show', $cPr->id) }}" class="btn btn-sm btn-outline-info">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                        </td>
                                    </tr>
                                @endforeach

                                @if($renderedItems === 0)
                                    <tr>
                                        <td colspan="6" class="text-center text-muted py-4">
                                            <i class="fas fa-circle-check text-success fa-2x mb-2 d-block"></i>
                                            All planning-approved emergency requisitions have been dispatched.
                                        </td>
                                    </tr>
                                @endif
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

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
                        <a href="{{ route('procurement.my-queue') }}" class="list-group-item list-group-item-action d-flex align-items-center justify-content-between">
                            <div class="d-flex align-items-center">
                                <i class="fas fa-bolt text-danger me-3"></i>
                                <div>
                                    <h6 class="mb-0 fw-bold">Procurement Queue</h6>
                                    <small class="text-muted">Emergency & site requisitions awaiting dispatch</small>
                                </div>
                            </div>
                            @if(($kpi['approved_emergency_requests'] ?? 0) > 0)
                                <span class="badge bg-danger rounded-pill">{{ $kpi['approved_emergency_requests'] }} Urgent</span>
                            @endif
                        </a>
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
