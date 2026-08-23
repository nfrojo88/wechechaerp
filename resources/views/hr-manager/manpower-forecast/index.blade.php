@extends('layouts.app')
@section('title', 'Manpower Forecast & Planning - HR')
@section('content')
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h2 class="h3 mb-1 fw-bold">
                <i class="fas fa-chart-line me-2 text-primary"></i>Manpower Forecast &amp; Planning
            </h2>
            <p class="text-muted small mb-0">Projected manpower demand from approved ERP engineering plans &amp; site forecasts</p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('manpower-forecast.create') }}" class="btn btn-primary btn-sm rounded-pill px-3 shadow-sm">
                <i class="fas fa-plus me-1"></i>New Manual Forecast
            </a>
            <a href="{{ route('manpower-forecast.export') }}" class="btn btn-outline-primary btn-sm rounded-pill px-3">
                <i class="fas fa-download me-1"></i>Export CSV
            </a>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="row g-3 mb-4">
        <div class="col-xl-3 col-md-6">
            <div class="card border-0 shadow-sm rounded-4 h-100" style="border-left: 4px solid #3b82f6 !important;">
                <div class="card-body py-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="text-muted small text-uppercase fw-semibold">Next Week Demand</div>
                            <div class="h3 mb-0 fw-bold text-primary">{{ $totalErpNextWeekHeadcount }} <span class="fs-6 text-muted fw-normal">workers</span></div>
                        </div>
                        <div class="rounded-circle d-flex align-items-center justify-content-center" style="width:48px;height:48px;background:#dbeafe;">
                            <i class="fas fa-users-gear text-primary fa-lg"></i>
                        </div>
                    </div>
                    <div class="small text-muted mt-2"><i class="fas fa-calendar-week me-1"></i>{{ $nextWeekStart->format('d M') }} – {{ $nextWeekEnd->format('d M Y') }}</div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card border-0 shadow-sm rounded-4 h-100" style="border-left: 4px solid #10b981 !important;">
                <div class="card-body py-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="text-muted small text-uppercase fw-semibold">Active Projects (ERP)</div>
                            <div class="h3 mb-0 fw-bold text-success">{{ count($projectManpowerForecasts) }}</div>
                        </div>
                        <div class="rounded-circle d-flex align-items-center justify-content-center" style="width:48px;height:48px;background:#d1fae5;">
                            <i class="fas fa-city text-success fa-lg"></i>
                        </div>
                    </div>
                    <div class="small text-muted mt-2">Projects with approved manpower plans</div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card border-0 shadow-sm rounded-4 h-100" style="border-left: 4px solid #f59e0b !important;">
                <div class="card-body py-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="text-muted small text-uppercase fw-semibold">Pending HR Approval</div>
                            <div class="h3 mb-0 fw-bold text-warning">{{ $stats['pending_approval'] }}</div>
                        </div>
                        <div class="rounded-circle d-flex align-items-center justify-content-center" style="width:48px;height:48px;background:#fef3c7;">
                            <i class="fas fa-clock text-warning fa-lg"></i>
                        </div>
                    </div>
                    <div class="small text-muted mt-2">Manual forecast submissions</div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card border-0 shadow-sm rounded-4 h-100" style="border-left: 4px solid #6366f1 !important;">
                <div class="card-body py-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="text-muted small text-uppercase fw-semibold">Custom Forecasts</div>
                            <div class="h3 mb-0 fw-bold text-dark">{{ $stats['total_forecasts'] }}</div>
                        </div>
                        <div class="rounded-circle d-flex align-items-center justify-content-center" style="width:48px;height:48px;background:#e0e7ff;">
                            <i class="fas fa-clipboard-list text-indigo fa-lg"></i>
                        </div>
                    </div>
                    <div class="small text-muted mt-2">{{ $stats['total_headcount_forecast'] }} total assigned positions</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Navigation Tabs -->
    <ul class="nav nav-pills mb-4" id="forecastTabs" role="tablist">
        <li class="nav-item">
            <button class="nav-link active fw-semibold rounded-pill px-4" id="erp-tab" data-bs-toggle="tab" data-bs-target="#erp-forecast" type="button">
                <i class="fas fa-hard-hat me-2"></i>Next Week Manpower Plan (Approved ERP Plans)
                @if($totalErpNextWeekHeadcount > 0)
                    <span class="badge bg-primary rounded-pill ms-2">{{ $totalErpNextWeekHeadcount }}</span>
                @endif
            </button>
        </li>
        <li class="nav-item">
            <button class="nav-link fw-semibold rounded-pill px-4" id="custom-tab" data-bs-toggle="tab" data-bs-target="#custom-forecast" type="button">
                <i class="fas fa-user-clock me-2"></i>Manual Forecasts &amp; Assignments ({{ $forecasts->total() }})
            </button>
        </li>
    </ul>

    <div class="tab-content" id="forecastTabsContent">
        <!-- TAB 1: Approved ERP Plan Next Week Forecast -->
        <div class="tab-pane fade show active" id="erp-forecast" role="tabpanel">
            <div class="card border-0 shadow-sm rounded-4 mb-4">
                <div class="card-header bg-light py-3 border-0 rounded-top-4 d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div>
                        <h5 class="fw-bold mb-0 text-dark">
                            <i class="fas fa-calendar-alt me-2 text-primary"></i>Next Week Projected Manpower by Project
                        </h5>
                        <span class="text-muted small">Period: <strong>{{ $nextWeekStart->format('d M Y') }}</strong> to <strong>{{ $nextWeekEnd->format('d M Y') }}</strong> (Week {{ $nextWeekStart->weekOfYear }})</span>
                    </div>
                    <span class="badge bg-success rounded-pill px-3 py-2 fs-6">
                        <i class="fas fa-check-double me-1"></i>Approved ERP Source
                    </span>
                </div>
                <div class="card-body p-0">
                    @if(count($projectManpowerForecasts) > 0)
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th style="width: 22%;">Project</th>
                                        <th style="width: 20%;">Approved Plan</th>
                                        <th style="width: 38%;">Next Week Manpower Breakdown (Roles &amp; Quantity)</th>
                                        <th class="text-center" style="width: 10%;">Total Workers</th>
                                        <th class="text-end" style="width: 10%;">Est. Cost</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($projectManpowerForecasts as $pForecast)
                                    <tr>
                                        <td>
                                            <div class="fw-bold text-dark fs-6">{{ $pForecast['project']->name }}</div>
                                            <small class="text-muted"><i class="fas fa-location-dot me-1"></i>{{ $pForecast['project']->location ?? 'Main Site' }}</small>
                                        </td>
                                        <td>
                                            <div class="fw-semibold text-primary">{{ $pForecast['plan_title'] }}</div>
                                            <small class="text-muted d-block">
                                                <i class="fas fa-user-check me-1 text-success"></i>By: {{ $pForecast['approved_by'] }}
                                            </small>
                                            @if($pForecast['approved_at'])
                                                <small class="text-muted">{{ \Carbon\Carbon::parse($pForecast['approved_at'])->format('d M Y') }}</small>
                                            @endif
                                        </td>
                                        <td>
                                            @if(!empty($pForecast['manpower_roles']))
                                                <div class="d-flex flex-wrap gap-1">
                                                    @foreach($pForecast['manpower_roles'] as $roleName => $roleData)
                                                        <span class="badge bg-light text-dark border px-2 py-1" style="font-size: 0.85rem;">
                                                            <span class="fw-bold text-primary">{{ $roleName }}:</span> {{ $roleData['count'] }} {{ $roleData['unit'] }}
                                                        </span>
                                                    @endforeach
                                                </div>
                                                @if(!empty($pForecast['tasks']))
                                                    <div class="mt-2 text-muted small">
                                                        <i class="fas fa-list-check me-1 text-secondary"></i><strong>Scheduled Tasks:</strong>
                                                        @foreach(array_slice($pForecast['tasks'], 0, 3) as $t)
                                                            <span class="badge bg-secondary bg-opacity-10 text-secondary border me-1">{{ $t['name'] }}</span>
                                                        @endforeach
                                                        @if(count($pForecast['tasks']) > 3)
                                                            <span class="text-muted">+{{ count($pForecast['tasks']) - 3 }} more</span>
                                                        @endif
                                                    </div>
                                                @endif
                                            @else
                                                <span class="text-muted small">No specific trade resources allocated in plan tasks.</span>
                                            @endif
                                        </td>
                                        <td class="text-center">
                                            <span class="badge bg-primary fs-6 px-3 py-2 rounded-pill">
                                                {{ $pForecast['total_headcount'] }} Workers
                                            </span>
                                        </td>
                                        <td class="text-end fw-semibold text-dark">
                                            @if($pForecast['total_cost'] > 0)
                                                {{ number_format($pForecast['total_cost'], 2) }} <small class="text-muted">ETB</small>
                                            @else
                                                <span class="text-muted">—</span>
                                            @endif
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                                <tfoot class="table-light fw-bold">
                                    <tr>
                                        <td colspan="3" class="text-end">Total Next Week Manpower Demand:</td>
                                        <td class="text-center">
                                            <span class="badge bg-success fs-6 px-3 py-2 rounded-pill">
                                                {{ $totalErpNextWeekHeadcount }} Workers
                                            </span>
                                        </td>
                                        <td class="text-end">
                                            @if($totalErpNextWeekCost > 0)
                                                {{ number_format($totalErpNextWeekCost, 2) }} ETB
                                            @else
                                                —
                                            @endif
                                        </td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    @else
                        <div class="text-center py-5 text-muted">
                            <i class="fas fa-clipboard-check fa-3x mb-3 text-secondary opacity-50"></i>
                            <h6 class="fw-bold">No Approved ERP Plans Found</h6>
                            <p class="small text-muted mb-0">Once the Planning Manager and GM approve ERP engineering schedules, their next-week manpower forecasts will appear here automatically.</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <!-- TAB 2: Manual Forecasts & Assignments -->
        <div class="tab-pane fade" id="custom-forecast" role="tabpanel">
            <!-- Filters -->
            <div class="card border-0 shadow-sm rounded-4 mb-4">
                <div class="card-body p-3">
                    <form method="GET" class="row g-2 align-items-center">
                        <div class="col-md-3">
                            <label class="form-label small fw-bold text-secondary mb-1">Project</label>
                            <select name="project_id" class="form-select form-select-sm rounded-3">
                                <option value="">All Projects</option>
                                @foreach ($projects as $project)
                                    <option value="{{ $project->id }}" {{ request('project_id') == $project->id ? 'selected' : '' }}>
                                        {{ $project->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-bold text-secondary mb-1">Week Starting</label>
                            <input type="date" name="week_starting" class="form-control form-control-sm rounded-3" value="{{ request('week_starting') }}">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-bold text-secondary mb-1">Status</label>
                            <select name="status" class="form-select form-select-sm rounded-3">
                                <option value="">All Status</option>
                                <option value="draft" {{ request('status') === 'draft' ? 'selected' : '' }}>Draft</option>
                                <option value="submitted" {{ request('status') === 'submitted' ? 'selected' : '' }}>Submitted</option>
                                <option value="approved" {{ request('status') === 'approved' ? 'selected' : '' }}>Approved</option>
                                <option value="rejected" {{ request('status') === 'rejected' ? 'selected' : '' }}>Rejected</option>
                            </select>
                        </div>
                        <div class="col-md-3 d-flex gap-2 pt-3">
                            <button type="submit" class="btn btn-primary btn-sm rounded-pill flex-grow-1">
                                <i class="fas fa-filter me-1"></i>Filter
                            </button>
                            <a href="{{ route('manpower-forecast.index') }}" class="btn btn-outline-secondary btn-sm rounded-pill">
                                Reset
                            </a>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Forecasts Table -->
            <div class="card border-0 shadow-sm rounded-4">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Project</th>
                                <th>Week Starting</th>
                                <th>Designation / Role</th>
                                <th class="text-center">Forecasted</th>
                                <th class="text-center">Assigned</th>
                                <th class="text-center">Hours</th>
                                <th>Status</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($forecasts as $forecast)
                                <tr>
                                    <td>
                                        <strong>{{ $forecast->project->name ?? 'N/A' }}</strong>
                                    </td>
                                    <td>
                                        {{ $forecast->week_starting ? $forecast->week_starting->format('M d, Y') : '-' }}
                                    </td>
                                    <td>
                                        <span class="badge bg-secondary">{{ $forecast->designation->name ?? 'Staff' }}</span>
                                    </td>
                                    <td class="text-center">
                                        <strong class="text-primary">{{ $forecast->forecasted_headcount }}</strong>
                                    </td>
                                    <td class="text-center">
                                        <strong class="text-info">{{ $forecast->assignments()->count() }}</strong>
                                    </td>
                                    <td class="text-center">
                                        {{ $forecast->forecasted_hours }}
                                    </td>
                                    <td>
                                        @if ($forecast->status === 'draft')
                                            <span class="badge bg-secondary">Draft</span>
                                        @elseif ($forecast->status === 'submitted')
                                            <span class="badge bg-warning text-dark">Submitted</span>
                                        @elseif ($forecast->status === 'approved')
                                            <span class="badge bg-success">Approved</span>
                                        @else
                                            <span class="badge bg-danger">Rejected</span>
                                        @endif
                                    </td>
                                    <td class="text-end">
                                        <a href="{{ route('manpower-forecast.show', $forecast->id) }}" class="btn btn-sm btn-outline-info rounded-circle" title="View Details">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        @if ($forecast->status === 'draft')
                                            <form method="POST" action="{{ route('manpower-forecast.submit', $forecast->id) }}" style="display: inline;">
                                                @csrf
                                                <button type="submit" class="btn btn-sm btn-outline-primary rounded-circle" title="Submit for approval">
                                                    <i class="fas fa-paper-plane"></i>
                                                </button>
                                            </form>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="text-center py-4 text-muted">
                                        <i class="fas fa-inbox fa-2x mb-2"></i>
                                        <p>No manual forecasts found</p>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @if($forecasts->hasPages())
                    <div class="card-footer bg-light border-0">
                        {{ $forecasts->links() }}
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
