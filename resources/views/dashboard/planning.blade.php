@extends('layouts.app')
@section('title', 'Planning Dashboard')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-1 fw-bold">
                <i class="fa-solid fa-diagram-project text-primary me-2"></i>Planning Dashboard
            </h1>
            <p class="text-muted mb-0 small">Central hub for schedules, ERP plans, and quantity takeoff</p>
        </div>
    </div>

    {{-- Quick Stats --}}
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card border-0 shadow-sm text-center p-3" style="background: linear-gradient(135deg,#667eea,#764ba2); color:#fff;">
                <div class="mb-2"><i class="fa-solid fa-building fa-2x opacity-75"></i></div>
                <div class="fs-2 fw-bold">{{ $projects->count() }}</div>
                <div class="small opacity-75">Active Projects</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm text-center p-3" style="background: linear-gradient(135deg,#11998e,#38ef7d); color:#fff;">
                <div class="mb-2"><i class="fa-solid fa-diagram-project fa-2x opacity-75"></i></div>
                <div class="fs-2 fw-bold">{{ $erpPlans->count() }}</div>
                <div class="small opacity-75">ERP Plans</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm text-center p-3" style="background: linear-gradient(135deg,#f093fb,#f5576c); color:#fff;">
                <div class="mb-2"><i class="fa-solid fa-calendar-days fa-2x opacity-75"></i></div>
                <div class="fs-2 fw-bold">{{ $schedules->count() }}</div>
                <div class="small opacity-75">Schedules</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm text-center p-3" style="background: linear-gradient(135deg,#4facfe,#00f2fe); color:#fff;">
                <div class="mb-2"><i class="fa-solid fa-ruler-combined fa-2x opacity-75"></i></div>
                <div class="fs-2 fw-bold">{{ $takeoffs->count() }}</div>
                <div class="small opacity-75">Takeoff Sheets</div>
            </div>
        </div>
    </div>

    {{-- Quick Action Cards --}}
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="rounded-circle bg-primary bg-opacity-10 p-3">
                        <i class="fa-solid fa-diagram-project text-primary fa-lg"></i>
                    </div>
                    <div>
                        <div class="fw-bold">ERP Plans</div>
                        <div class="small text-muted">View and manage project ERP plans</div>
                    </div>
                    <a href="{{ route('erp-plans.index') }}" class="btn btn-sm btn-primary ms-auto">Open</a>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="rounded-circle bg-success bg-opacity-10 p-3">
                        <i class="fa-solid fa-calendar-days text-success fa-lg"></i>
                    </div>
                    <div>
                        <div class="fw-bold">Schedules</div>
                        <div class="small text-muted">View and create project schedules</div>
                    </div>
                    <a href="{{ route('schedules.index') }}" class="btn btn-sm btn-success ms-auto">Open</a>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="rounded-circle bg-info bg-opacity-10 p-3">
                        <i class="fa-solid fa-ruler-combined text-info fa-lg"></i>
                    </div>
                    <div>
                        <div class="fw-bold">Quantity Takeoff</div>
                        <div class="small text-muted">Manage material quantity takeoffs</div>
                    </div>
                    <a href="{{ route('takeoff.index') }}" class="btn btn-sm btn-info ms-auto">Open</a>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="rounded-circle bg-warning bg-opacity-10 p-3">
                        <i class="fa-solid fa-ruler-combined text-warning fa-lg"></i>
                    </div>
                    <div>
                        <div class="fw-bold">Work Standards</div>
                        <div class="small text-muted">Manage standard works</div>
                    </div>
                    <a href="{{ route('standard-works.index') }}" class="btn btn-sm btn-warning ms-auto">Open</a>
                </div>
            </div>
        </div>
    </div>

    {{-- ── Project Budget, Cost & Current Balance Section ──────────────────────── --}}
    <div class="card border-0 shadow-sm mb-4 overflow-hidden">
        <div class="card-header bg-white border-bottom py-3 d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div>
                <h5 class="mb-0 fw-bold text-dark">
                    <i class="fa-solid fa-sack-dollar text-warning me-2"></i>Project Budget, Cost &amp; Current Balance
                </h5>
                <small class="text-muted">Track approved project budgets, actual expenses (cost), and remaining funds in real-time.</small>
            </div>
            <a href="{{ route('budgets.index') }}" class="btn btn-sm btn-outline-warning fw-semibold">
                <i class="fa-solid fa-arrow-up-right-from-square me-1"></i> View Detailed Budgets
            </a>
        </div>

        <div class="card-body p-4 bg-light bg-opacity-50">
            {{-- Financial KPI Cards --}}
            <div class="row g-3 mb-4">
                <div class="col-sm-6 col-xl-3">
                    <div class="card border-0 shadow-sm rounded-3 h-100 bg-white border-start border-4 border-primary">
                        <div class="card-body p-3">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <small class="text-muted fw-semibold text-uppercase" style="font-size:0.75rem; letter-spacing:0.05em;">Approved Budget</small>
                                    <h4 class="fw-bold text-primary mb-0 mt-1">Br {{ number_format($totalApprovedBudget, 2) }}</h4>
                                </div>
                                <div class="p-2 rounded-3 bg-primary bg-opacity-10 text-primary">
                                    <i class="fa-solid fa-file-invoice-dollar fa-lg"></i>
                                </div>
                            </div>
                            <small class="text-muted mt-2 d-block" style="font-size:0.75rem;">Total GM &amp; Client Approved Funds</small>
                        </div>
                    </div>
                </div>

                <div class="col-sm-6 col-xl-3">
                    <div class="card border-0 shadow-sm rounded-3 h-100 bg-white border-start border-4 border-danger">
                        <div class="card-body p-3">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <small class="text-muted fw-semibold text-uppercase" style="font-size:0.75rem; letter-spacing:0.05em;">Actual Expenses (Cost)</small>
                                    <h4 class="fw-bold text-danger mb-0 mt-1">Br {{ number_format($totalActualExpense, 2) }}</h4>
                                </div>
                                <div class="p-2 rounded-3 bg-danger bg-opacity-10 text-danger">
                                    <i class="fa-solid fa-money-bill-transfer fa-lg"></i>
                                </div>
                            </div>
                            <small class="text-muted mt-2 d-block" style="font-size:0.75rem;">Total Payments &amp; Direct Costs</small>
                        </div>
                    </div>
                </div>

                <div class="col-sm-6 col-xl-3">
                    <div class="card border-0 shadow-sm rounded-3 h-100 bg-white border-start border-4 border-success">
                        <div class="card-body p-3">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <small class="text-muted fw-semibold text-uppercase" style="font-size:0.75rem; letter-spacing:0.05em;">Remaining Budget</small>
                                    <h4 class="fw-bold text-success mb-0 mt-1">Br {{ number_format($totalRemainingBudget, 2) }}</h4>
                                </div>
                                <div class="p-2 rounded-3 bg-success bg-opacity-10 text-success">
                                    <i class="fa-solid fa-wallet fa-lg"></i>
                                </div>
                            </div>
                            <small class="text-muted mt-2 d-block" style="font-size:0.75rem;">Current Available Project Balance</small>
                        </div>
                    </div>
                </div>

                <div class="col-sm-6 col-xl-3">
                    <div class="card border-0 shadow-sm rounded-3 h-100 bg-white border-start border-4 border-info">
                        <div class="card-body p-3">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <small class="text-muted fw-semibold text-uppercase" style="font-size:0.75rem; letter-spacing:0.05em;">Budget Utilization</small>
                                    <h4 class="fw-bold text-dark mb-0 mt-1">{{ number_format($overallUtilization, 1) }}%</h4>
                                </div>
                                <div class="p-2 rounded-3 bg-info bg-opacity-10 text-info">
                                    <i class="fa-solid fa-chart-pie fa-lg"></i>
                                </div>
                            </div>
                            <div class="progress mt-2" style="height:6px;">
                                <div class="progress-bar {{ $overallUtilization > 90 ? 'bg-danger' : ($overallUtilization > 75 ? 'bg-warning' : 'bg-success') }}" 
                                     style="width: {{ min(100, $overallUtilization) }}%"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Project Financial Status Table --}}
            <div class="card border-0 shadow-sm rounded-3 bg-white overflow-hidden">
                <div class="card-header bg-white py-3 px-3 border-bottom d-flex justify-content-between align-items-center">
                    <h6 class="mb-0 fw-bold text-dark"><i class="fa-solid fa-list-check text-primary me-2"></i>Active Projects Budget &amp; Expense Summary</h6>
                    <span class="badge bg-secondary">{{ count($projectFinancials) }} Project(s)</span>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-3">Project</th>
                                <th>Client</th>
                                <th class="text-end">Approved Budget</th>
                                <th class="text-end">Actual Expenses (Cost)</th>
                                <th class="text-end">Remaining Balance</th>
                                <th style="min-width: 140px;">Utilization</th>
                                <th class="text-center">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($projectFinancials as $pf)
                            <tr>
                                <td class="ps-3">
                                    <span class="fw-bold text-dark d-block">{{ $pf['name'] }}</span>
                                    @if($pf['code'])
                                        <small class="text-muted font-monospace">{{ $pf['code'] }}</small>
                                    @endif
                                </td>
                                <td><span class="small text-muted">{{ $pf['client_name'] ?: 'N/A' }}</span></td>
                                <td class="text-end fw-semibold text-primary">Br {{ number_format($pf['approved_budget'], 2) }}</td>
                                <td class="text-end fw-semibold text-danger">Br {{ number_format($pf['actual_expense'], 2) }}</td>
                                <td class="text-end fw-bold text-success">Br {{ number_format($pf['remaining_budget'], 2) }}</td>
                                <td>
                                    <div class="d-flex justify-content-between align-items-center mb-1" style="font-size:0.75rem;">
                                        <span class="fw-bold">{{ number_format($pf['utilization'], 1) }}%</span>
                                    </div>
                                    <div class="progress" style="height:6px;">
                                        <div class="progress-bar {{ $pf['utilization'] > 90 ? 'bg-danger' : ($pf['utilization'] > 75 ? 'bg-warning' : 'bg-success') }}" 
                                             role="progressbar" 
                                             style="width: {{ min(100, $pf['utilization']) }}%"></div>
                                    </div>
                                </td>
                                <td class="text-center">
                                    @if($pf['status'] === 'Exceeded')
                                        <span class="badge bg-danger">Exceeded</span>
                                    @elseif($pf['status'] === 'Warning')
                                        <span class="badge bg-warning text-dark">Warning (&gt;80%)</span>
                                    @else
                                        <span class="badge bg-success">Healthy</span>
                                    @endif
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="7" class="text-center py-4 text-muted">No active project budget records found.</td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        {{-- Recent ERP Plans --}}
        <div class="col-md-6">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-bottom d-flex justify-content-between align-items-center">
                    <h6 class="mb-0 fw-bold"><i class="fa-solid fa-diagram-project text-primary me-2"></i>Recent ERP Plans</h6>
                    <a href="{{ route('erp-plans.index') }}" class="btn btn-sm btn-outline-primary">View All</a>
                </div>
                <div class="card-body p-0">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr><th>Plan</th><th>Project</th><th>Progress</th><th>Status</th></tr>
                        </thead>
                        <tbody>
                            @forelse($erpPlans as $plan)
                            <tr>
                                <td>
                                    <a href="{{ route('erp-plans.show', $plan) }}" class="text-decoration-none fw-semibold">
                                        {{ $plan->title ?? $plan->plan_name ?? 'Plan #'.$plan->id }}
                                    </a>
                                </td>
                                <td class="small text-muted">{{ $plan->project->name ?? '-' }}</td>
                                <td>
                                    <div class="progress" style="height:6px; width:80px;">
                                        <div class="progress-bar bg-primary" style="{{ 'width:'.($plan->actual_progress ?? 0).'%' }}"></div>
                                    </div>
                                    <small class="text-muted">{{ $plan->actual_progress ?? 0 }}%</small>
                                </td>
                                <td>
                                    <span class="badge bg-{{ $plan->status === 'active' ? 'success' : 'secondary' }}">
                                        {{ ucfirst($plan->status) }}
                                    </span>
                                </td>
                            </tr>
                            @empty
                            <tr><td colspan="4" class="text-center py-3 text-muted">No ERP plans found.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        {{-- Recent Schedules --}}
        <div class="col-md-6">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-bottom d-flex justify-content-between align-items-center">
                    <h6 class="mb-0 fw-bold"><i class="fa-solid fa-calendar-days text-success me-2"></i>Recent Schedules</h6>
                    <a href="{{ route('schedules.index') }}" class="btn btn-sm btn-outline-success">View All</a>
                </div>
                <div class="card-body p-0">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr><th>Title</th><th>Project</th><th>Start</th><th>Status</th></tr>
                        </thead>
                        <tbody>
                            @forelse($schedules as $schedule)
                            <tr>
                                <td class="fw-semibold">{{ $schedule->title }}</td>
                                <td class="small text-muted">{{ $schedule->project->name ?? '-' }}</td>
                                <td class="small">{{ optional($schedule->start_date)->format('d M Y') }}</td>
                                <td>
                                    <span class="badge bg-{{ $schedule->status === 'approved' ? 'success' : 'warning' }} text-{{ $schedule->status !== 'approved' ? 'dark' : '' }}">
                                        {{ ucfirst($schedule->status) }}
                                    </span>
                                </td>
                            </tr>
                            @empty
                            <tr><td colspan="4" class="text-center py-3 text-muted">No schedules found.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        {{-- Recent Takeoffs --}}
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-bottom d-flex justify-content-between align-items-center">
                    <h6 class="mb-0 fw-bold"><i class="fa-solid fa-ruler-combined text-info me-2"></i>Recent Takeoff Sheets</h6>
                    <a href="{{ route('takeoff.index') }}" class="btn btn-sm btn-outline-info">View All</a>
                </div>
                <div class="card-body p-0">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr><th>Title</th><th>Project</th><th>Type</th><th>Prepared By</th><th>Status</th><th>Action</th></tr>
                        </thead>
                        <tbody>
                            @forelse($takeoffs as $t)
                            <tr>
                                <td class="fw-semibold">{{ $t->title }}</td>
                                <td class="small text-muted">{{ $t->project->name ?? '-' }}</td>
                                <td><span class="badge bg-info text-dark">{{ ucfirst($t->sheet_type) }}</span></td>
                                <td class="small">{{ $t->creator->name ?? '-' }}</td>
                                <td>
                                    <span class="badge bg-{{ $t->status === 'approved' ? 'success' : 'secondary' }}">
                                        {{ ucfirst($t->status) }}
                                    </span>
                                </td>
                                <td>
                                    <a href="{{ route('takeoff.show', $t) }}" class="btn btn-sm btn-outline-secondary">
                                        <i class="fa-solid fa-eye"></i>
                                    </a>
                                </td>
                            </tr>
                            @empty
                            <tr><td colspan="6" class="text-center py-3 text-muted">No takeoff sheets found.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
