@extends('layouts.app')
@section('title', 'Planning Dashboard')

@section('content')
<div class="wc-container py-3 py-md-4">
    <x-page-header
        title="Planning Dashboard"
        subtitle="Central hub for project master schedules, ERP plans, quantity takeoffs and budget tracking"
        badge="Project Planning"
    >
        <x-slot:actions>
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <a href="{{ route('schedules.create') }}" class="btn btn-outline-primary btn-sm shadow-xs" style="min-height: 38px; display: inline-flex; align-items: center;">
                    <i class="fa-solid fa-calendar-plus me-1"></i> New Schedule
                </a>
                <a href="{{ route('erp-plans.create') }}" class="btn btn-primary btn-sm shadow-xs" style="min-height: 38px; display: inline-flex; align-items: center;">
                    <i class="fa-solid fa-plus me-1"></i> New ERP Plan
                </a>
            </div>
        </x-slot:actions>
    </x-page-header>

    {{-- Quick Stats Row --}}
    <div class="row g-3 mb-4">
        <div class="col-12 col-sm-6 col-xl-3">
            <x-kpi-card
                title="Active Projects"
                :value="$projects->count()"
                subtitle="Currently in progress"
                icon="fa-solid fa-building"
                tone="primary"
            />
        </div>
        <div class="col-12 col-sm-6 col-xl-3">
            <x-kpi-card
                title="ERP Plans"
                :value="$erpPlans->count()"
                subtitle="Active operational plans"
                icon="fa-solid fa-diagram-project"
                tone="success"
            />
        </div>
        <div class="col-12 col-sm-6 col-xl-3">
            <x-kpi-card
                title="Master Schedules"
                :value="$schedules->count()"
                subtitle="WBS timelines"
                icon="fa-solid fa-calendar-days"
                tone="purple"
            />
        </div>
        <div class="col-12 col-sm-6 col-xl-3">
            <x-kpi-card
                title="Takeoff Sheets"
                :value="$takeoffs->count()"
                subtitle="Material quantity sheets"
                icon="fa-solid fa-ruler-combined"
                tone="info"
            />
        </div>
    </div>

    {{-- Quick Action Cards --}}
    <div class="row g-3 mb-4">
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card border-0 shadow-sm h-100 bg-white">
                <div class="card-body p-3 d-flex align-items-center justify-content-between">
                    <div class="d-flex align-items-center gap-3">
                        <div class="rounded-circle bg-primary bg-opacity-10 p-2 text-primary d-flex align-items-center justify-content-center flex-shrink-0" style="width: 44px; height: 44px;">
                            <i class="fa-solid fa-diagram-project fa-lg"></i>
                        </div>
                        <div>
                            <div class="fw-bold text-dark fs-6">ERP Plans</div>
                            <div class="small text-muted">Project ERP plans</div>
                        </div>
                    </div>
                    <a href="{{ route('erp-plans.index') }}" class="btn btn-sm btn-outline-primary rounded-pill px-3" style="min-height: 36px; display: inline-flex; align-items: center;">Open</a>
                </div>
            </div>
        </div>
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card border-0 shadow-sm h-100 bg-white">
                <div class="card-body p-3 d-flex align-items-center justify-content-between">
                    <div class="d-flex align-items-center gap-3">
                        <div class="rounded-circle bg-success bg-opacity-10 p-2 text-success d-flex align-items-center justify-content-center flex-shrink-0" style="width: 44px; height: 44px;">
                            <i class="fa-solid fa-calendar-days fa-lg"></i>
                        </div>
                        <div>
                            <div class="fw-bold text-dark fs-6">Schedules</div>
                            <div class="small text-muted">Project timelines</div>
                        </div>
                    </div>
                    <a href="{{ route('schedules.index') }}" class="btn btn-sm btn-outline-success rounded-pill px-3" style="min-height: 36px; display: inline-flex; align-items: center;">Open</a>
                </div>
            </div>
        </div>
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card border-0 shadow-sm h-100 bg-white">
                <div class="card-body p-3 d-flex align-items-center justify-content-between">
                    <div class="d-flex align-items-center gap-3">
                        <div class="rounded-circle bg-info bg-opacity-10 p-2 text-info d-flex align-items-center justify-content-center flex-shrink-0" style="width: 44px; height: 44px;">
                            <i class="fa-solid fa-ruler-combined fa-lg"></i>
                        </div>
                        <div>
                            <div class="fw-bold text-dark fs-6">Takeoff</div>
                            <div class="small text-muted">Material takeoffs</div>
                        </div>
                    </div>
                    <a href="{{ route('takeoff.index') }}" class="btn btn-sm btn-outline-info rounded-pill px-3" style="min-height: 36px; display: inline-flex; align-items: center;">Open</a>
                </div>
            </div>
        </div>
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card border-0 shadow-sm h-100 bg-white">
                <div class="card-body p-3 d-flex align-items-center justify-content-between">
                    <div class="d-flex align-items-center gap-3">
                        <div class="rounded-circle bg-warning bg-opacity-10 p-2 text-warning d-flex align-items-center justify-content-center flex-shrink-0" style="width: 44px; height: 44px;">
                            <i class="fa-solid fa-cubes-stacked fa-lg"></i>
                        </div>
                        <div>
                            <div class="fw-bold text-dark fs-6">Standards</div>
                            <div class="small text-muted">Standard works</div>
                        </div>
                    </div>
                    <a href="{{ route('standard-works.index') }}" class="btn btn-sm btn-outline-warning text-dark rounded-pill px-3" style="min-height: 36px; display: inline-flex; align-items: center;">Open</a>
                </div>
            </div>
        </div>
    </div>

    {{-- Project Budget, Cost & Current Balance Section --}}
    <div class="card border-0 shadow-sm mb-4 bg-white">
        <div class="card-header bg-white border-bottom py-3 d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div>
                <h6 class="mb-0 fw-bold text-dark">
                    <i class="fa-solid fa-sack-dollar text-warning me-2"></i>Project Budget, Cost &amp; Current Balance
                </h6>
                <small class="text-muted">Track approved project budgets, actual expenses (cost), and remaining funds in real-time.</small>
            </div>
            <a href="{{ route('budgets.index') }}" class="btn btn-sm btn-outline-warning text-dark fw-semibold rounded-pill px-3" style="min-height: 36px; display: inline-flex; align-items: center;">
                <i class="fa-solid fa-arrow-up-right-from-square me-1"></i> View Detailed Budgets
            </a>
        </div>

        <div class="card-body p-3 p-md-4">
            {{-- Financial KPI Cards --}}
            <div class="row g-3 mb-4">
                <div class="col-12 col-sm-6 col-xl-3">
                    <x-kpi-card
                        title="Approved Budget"
                        :value="'Br ' . number_format($totalApprovedBudget, 2)"
                        subtitle="GM & Client Approved Funds"
                        icon="fa-solid fa-file-invoice-dollar"
                        tone="primary"
                    />
                </div>
                <div class="col-12 col-sm-6 col-xl-3">
                    <x-kpi-card
                        title="Actual Expenses (Cost)"
                        :value="'Br ' . number_format($totalActualExpense, 2)"
                        subtitle="Payments & Direct Costs"
                        icon="fa-solid fa-money-bill-transfer"
                        tone="danger"
                    />
                </div>
                <div class="col-12 col-sm-6 col-xl-3">
                    <x-kpi-card
                        title="Remaining Budget"
                        :value="'Br ' . number_format($totalRemainingBudget, 2)"
                        subtitle="Current Available Balance"
                        icon="fa-solid fa-wallet"
                        tone="success"
                    />
                </div>
                <div class="col-12 col-sm-6 col-xl-3">
                    <x-kpi-card
                        title="Budget Utilization"
                        :value="number_format($overallUtilization, 1) . '%'"
                        :subtitle="$overallUtilization > 90 ? 'High Utilization' : 'Healthy Status'"
                        icon="fa-solid fa-chart-pie"
                        tone="info"
                    />
                </div>
            </div>

            {{-- Project Financial Status Table --}}
            <div class="card border-0 shadow-sm rounded-3 bg-white">
                <div class="card-header bg-white py-3 px-3 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <h6 class="mb-0 fw-bold text-dark"><i class="fa-solid fa-list-check text-primary me-2"></i>Active Projects Budget &amp; Expense Summary</h6>
                    <span class="badge bg-secondary rounded-pill">{{ count($projectFinancials) }} Project(s)</span>
                </div>
                <div class="table-responsive wc-table-mobile-cards">
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
                                <td data-label="Project" class="ps-3">
                                    <span class="fw-bold text-dark d-block">{{ $pf['name'] }}</span>
                                    @if($pf['code'])
                                        <small class="text-muted font-monospace">{{ $pf['code'] }}</small>
                                    @endif
                                </td>
                                <td data-label="Client"><span class="small text-muted">{{ $pf['client_name'] ?: 'N/A' }}</span></td>
                                <td data-label="Approved Budget" class="text-end fw-semibold text-primary font-tabular">Br {{ number_format($pf['approved_budget'], 2) }}</td>
                                <td data-label="Actual Expenses" class="text-end fw-semibold text-danger font-tabular">Br {{ number_format($pf['actual_expense'], 2) }}</td>
                                <td data-label="Remaining Balance" class="text-end fw-bold text-success font-tabular">Br {{ number_format($pf['remaining_budget'], 2) }}</td>
                                <td data-label="Utilization">
                                    <div class="d-flex justify-content-between align-items-center mb-1" style="font-size:0.75rem;">
                                        <span class="fw-bold font-tabular">{{ number_format($pf['utilization'], 1) }}%</span>
                                    </div>
                                    <div class="progress" style="height:6px; border-radius:3px;">
                                        <div class="progress-bar {{ $pf['utilization'] > 90 ? 'bg-danger' : ($pf['utilization'] > 75 ? 'bg-warning' : 'bg-success') }}" 
                                             role="progressbar" 
                                             style="width: {{ min(100, $pf['utilization']) }}%"></div>
                                    </div>
                                </td>
                                <td data-label="Status" class="text-center">
                                    @if($pf['status'] === 'Exceeded')
                                        <x-status-badge status="Exceeded" tone="danger" />
                                    @elseif($pf['status'] === 'Warning')
                                        <x-status-badge status="Warning (>80%)" tone="warning" />
                                    @else
                                        <x-status-badge status="Healthy" tone="success" />
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
        <div class="col-12 col-lg-6">
            <div class="card border-0 shadow-sm bg-white h-100">
                <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <h6 class="mb-0 fw-bold text-dark"><i class="fa-solid fa-diagram-project text-primary me-2"></i>Recent ERP Plans</h6>
                    <a href="{{ route('erp-plans.index') }}" class="btn btn-sm btn-outline-primary rounded-pill px-3" style="min-height: 34px; display: inline-flex; align-items: center;">View All</a>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive wc-table-mobile-cards">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr><th>Plan</th><th>Project</th><th>Progress</th><th>Status</th></tr>
                            </thead>
                            <tbody>
                                @forelse($erpPlans as $plan)
                                <tr>
                                    <td data-label="Plan">
                                        <a href="{{ route('erp-plans.show', $plan) }}" class="text-decoration-none fw-semibold text-primary">
                                            {{ $plan->title ?? $plan->plan_name ?? 'Plan #'.$plan->id }}
                                        </a>
                                    </td>
                                    <td data-label="Project" class="small text-muted">{{ $plan->project->name ?? '-' }}</td>
                                    <td data-label="Progress">
                                        <div class="progress" style="height:6px; width:80px; border-radius:3px;">
                                            <div class="progress-bar bg-primary" style="{{ 'width:'.($plan->actual_progress ?? 0).'%' }}"></div>
                                        </div>
                                        <small class="text-muted font-tabular">{{ $plan->actual_progress ?? 0 }}%</small>
                                    </td>
                                    <td data-label="Status">
                                        <x-status-badge :status="$plan->status" />
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
        </div>

        {{-- Recent Schedules --}}
        <div class="col-12 col-lg-6">
            <div class="card border-0 shadow-sm bg-white h-100">
                <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <h6 class="mb-0 fw-bold text-dark"><i class="fa-solid fa-calendar-days text-success me-2"></i>Recent Schedules</h6>
                    <a href="{{ route('schedules.index') }}" class="btn btn-sm btn-outline-success rounded-pill px-3" style="min-height: 34px; display: inline-flex; align-items: center;">View All</a>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive wc-table-mobile-cards">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr><th>Title</th><th>Project</th><th>Start</th><th>Status</th></tr>
                            </thead>
                            <tbody>
                                @forelse($schedules as $schedule)
                                <tr>
                                    <td data-label="Title" class="fw-semibold text-dark">{{ $schedule->title }}</td>
                                    <td data-label="Project" class="small text-muted">{{ $schedule->project->name ?? '-' }}</td>
                                    <td data-label="Start" class="small">{{ optional($schedule->start_date)->format('d M Y') }}</td>
                                    <td data-label="Status">
                                        <x-status-badge :status="$schedule->status" />
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
        </div>

        {{-- Recent Takeoffs --}}
        <div class="col-12">
            <div class="card border-0 shadow-sm bg-white">
                <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <h6 class="mb-0 fw-bold text-dark"><i class="fa-solid fa-ruler-combined text-info me-2"></i>Recent Takeoff Sheets</h6>
                    <a href="{{ route('takeoff.index') }}" class="btn btn-sm btn-outline-info rounded-pill px-3" style="min-height: 34px; display: inline-flex; align-items: center;">View All</a>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive wc-table-mobile-cards">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr><th>Title</th><th>Project</th><th>Type</th><th>Prepared By</th><th>Status</th><th>Action</th></tr>
                            </thead>
                            <tbody>
                                @forelse($takeoffs as $t)
                                <tr>
                                    <td data-label="Title" class="fw-semibold text-dark">{{ $t->title }}</td>
                                    <td data-label="Project" class="small text-muted">{{ $t->project->name ?? '-' }}</td>
                                    <td data-label="Type"><span class="badge bg-light text-dark border">{{ ucfirst($t->sheet_type) }}</span></td>
                                    <td data-label="Prepared By" class="small">{{ $t->creator->name ?? '-' }}</td>
                                    <td data-label="Status">
                                        <x-status-badge :status="$t->status" />
                                    </td>
                                    <td data-label="Action">
                                        <a href="{{ route('takeoff.show', $t) }}" class="btn btn-sm btn-outline-secondary rounded-pill px-2" style="min-width: 34px; min-height: 34px; display: inline-flex; align-items: center; justify-content: center;" title="View Details">
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
</div>
@endsection
