@extends('layouts.app')
@section('title', 'Global Admin Dashboard')
@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <h1 class="h3 mb-0 text-gray-800"><i class="fas fa-gauge-high me-2"></i>Global Admin Dashboard</h1>
        <div class="d-flex align-items-center gap-2 flex-wrap">
            <a href="#vat-withholding-report" class="btn btn-outline-danger btn-sm rounded-pill px-3 shadow-xs">
                <i class="fa-solid fa-receipt me-1"></i>VAT &amp; WHT Report
            </a>
            <a href="{{ route('finance.tax-deductions.index') }}" class="btn btn-outline-primary btn-sm rounded-pill px-3 shadow-xs">
                <i class="fa-solid fa-file-invoice-dollar me-1"></i>Tax Compliance Ledger
            </a>
        </div>
    </div>

    <!-- KPI Row -->
    <div class="row">
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-primary shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Total Active Projects</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">{{ $kpi['total_projects'] ?? '0' }}</div>
                        </div>
                        <div class="col-auto"><i class="fas fa-building fa-2x text-gray-300"></i></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-success shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Total Employees</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">{{ $kpi['total_employees'] ?? '0' }}</div>
                        </div>
                        <div class="col-auto"><i class="fas fa-users fa-2x text-gray-300"></i></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-danger shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">Monthly Expenses</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">{{ number_format($kpi['monthly_expenses'] ?? 0, 2) }}</div>
                        </div>
                        <div class="col-auto"><i class="fas fa-money-bill-trend-up fa-2x text-gray-300"></i></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-info shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Inventory Value</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">{{ number_format($kpi['inventory_value'] ?? 0, 2) }}</div>
                        </div>
                        <div class="col-auto"><i class="fas fa-warehouse fa-2x text-gray-300"></i></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Charts Row -->
    <div id="admin-chart-data" class="d-none" 
         data-budget-names="@json(isset($projectBudgets) ? $projectBudgets->pluck('name') : [])" 
         data-budget-budgeted="@json(isset($projectBudgets) ? $projectBudgets->pluck('budgeted_total') : [])" 
         data-budget-actual="@json(isset($projectBudgets) ? $projectBudgets->pluck('actual_total') : [])"
         data-role-names="@json(isset($usersByRole) ? $usersByRole->pluck('name') : [])"
         data-role-totals="@json(isset($usersByRole) ? $usersByRole->pluck('total') : [])">
    </div>
    <div class="row">
        <!-- Project Budgets -->
        <div class="col-xl-8 col-lg-7">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Project Budget Overview</h6>
                </div>
                <div class="card-body">
                    <div class="chart-bar">
                        <canvas id="budgetBarChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
        <!-- Users by Role -->
        <div class="col-xl-4 col-lg-5">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">System Users by Role</h6>
                </div>
                <div class="card-body">
                    <div class="chart-pie pt-4 pb-2">
                        <canvas id="rolesPieChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ═══ VAT & WITHHOLDING TAX COMPLIANCE REPORT ═══════════════════════════════ --}}
    @include('dashboard.partials.tax_compliance_report')

    <!-- New Feature Panels Row -->
    <div class="row mt-4">
        
        <!-- Activity Log Feed -->
        <div class="col-lg-4 mb-4">
            <div class="card shadow h-100">
                <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                    <h6 class="m-0 font-weight-bold text-primary"><i class="fa-solid fa-list-ol me-2"></i>Live Activity Feed</h6>
                    <a href="{{ route('admin.activity-logs') }}" class="text-xs text-decoration-none">View All</a>
                </div>
                <div class="card-body" style="max-height: 400px; overflow-y: auto;">
                    @if(isset($activityLogs) && $activityLogs->count() > 0)
                        <div class="list-group list-group-flush">
                            @foreach($activityLogs as $log)
                                <div class="list-group-item px-0 py-2 border-bottom-0">
                                    <div class="d-flex w-100 justify-content-between">
                                        <h6 class="mb-1 text-sm font-weight-bold" style="font-size: 13px;">
                                            <i class="fa-solid {{ $log->action_icon ?? 'fa-circle-dot' }} text-{{ $log->action_color ?? 'secondary' }} me-1"></i>
                                            {{ $log->user->name ?? 'System' }}
                                        </h6>
                                        <small class="text-muted" style="font-size: 11px;">{{ $log->created_at->diffForHumans() }}</small>
                                    </div>
                                    <p class="mb-1 text-xs text-gray-700" style="font-size: 12px; margin-left: 20px;">
                                        {{ $log->description }}
                                    </p>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="text-center py-4 text-muted">
                            <i class="fa-solid fa-ghost fs-3 mb-2 opacity-50"></i>
                            <p class="mb-0 text-sm">No recent activity.</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <!-- Role Assignment (Unassigned Users) -->
        <div class="col-lg-4 mb-4">
            <div class="card shadow h-100">
                <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                    <h6 class="m-0 font-weight-bold text-info"><i class="fa-solid fa-user-tag me-2"></i>Unassigned Roles</h6>
                    <a href="{{ route('admin.role-assignment.index') }}" class="text-xs text-decoration-none">Manage Roles</a>
                </div>
                <div class="card-body" style="max-height: 400px; overflow-y: auto;">
                    <p class="text-xs text-muted mb-3">Employees registered by HR who have created credentials but need a system role.</p>
                    
                    @if(isset($unassignedUsers) && $unassignedUsers->count() > 0)
                        @foreach($unassignedUsers as $uUser)
                            <div class="card mb-2 border-left-warning shadow-sm">
                                <div class="card-body p-2">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <div class="text-sm font-weight-bold text-gray-800">{{ $uUser->name }}</div>
                                            <div class="text-xs text-muted">{{ $uUser->employee->department ?? 'No Dept' }} - {{ $uUser->employee->role_title ?? 'No Title' }}</div>
                                        </div>
                                        <a href="{{ route('admin.role-assignment.index') }}" class="btn btn-sm btn-outline-info text-xs">Assign</a>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    @else
                        <div class="text-center py-4 text-muted">
                            <i class="fa-solid fa-check-circle fs-3 mb-2 opacity-50 text-success"></i>
                            <p class="mb-0 text-sm">All users have roles assigned.</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <!-- Support Tickets Overview -->
        <div class="col-lg-4 mb-4">
            <div class="card shadow h-100">
                <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                    <h6 class="m-0 font-weight-bold text-danger"><i class="fa-solid fa-ticket me-2"></i>Support Tickets</h6>
                    <a href="{{ route('admin.tickets.index') }}" class="text-xs text-decoration-none">View All</a>
                </div>
                <div class="card-body">
                    <div class="row text-center mb-4">
                        <div class="col-4">
                            <div class="h4 mb-0 font-weight-bold text-danger">{{ $ticketStats['open'] ?? 0 }}</div>
                            <div class="text-xs text-muted text-uppercase">Open</div>
                        </div>
                        <div class="col-4 border-left border-right">
                            <div class="h4 mb-0 font-weight-bold text-warning">{{ $ticketStats['in_progress'] ?? 0 }}</div>
                            <div class="text-xs text-muted text-uppercase">In Progress</div>
                        </div>
                        <div class="col-4">
                            <div class="h4 mb-0 font-weight-bold text-success">{{ $ticketStats['resolved'] ?? 0 }}</div>
                            <div class="text-xs text-muted text-uppercase">Resolved</div>
                        </div>
                    </div>
                    
                    <hr>
                    <h6 class="text-xs font-weight-bold text-uppercase mb-2">Recent Tickets</h6>
                    
                    @if(isset($recentTickets) && $recentTickets->count() > 0)
                        <div class="list-group list-group-flush">
                            @foreach($recentTickets as $ticket)
                                <a href="{{ route('admin.tickets.show', $ticket) }}" class="list-group-item list-group-item-action px-0 py-2 border-bottom-0">
                                    <div class="d-flex w-100 justify-content-between">
                                        <h6 class="mb-1 text-sm font-weight-bold text-gray-800 text-truncate" style="max-width: 70%;">{{ $ticket->subject }}</h6>
                                        <small>{!! $ticket->status_badge ?? '' !!}</small>
                                    </div>
                                    <small class="text-muted text-xs">By {{ $ticket->user->name ?? 'Unknown' }} • {{ $ticket->created_at->diffForHumans() }}</small>
                                </a>
                            @endforeach
                        </div>
                    @else
                        <div class="text-center py-2 text-muted">
                            <p class="mb-0 text-sm">No tickets found.</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>

    </div>

</div>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    var chartData = document.getElementById("admin-chart-data");
    var budgetNames = JSON.parse(chartData.dataset.budgetNames);
    var budgetBudgeted = JSON.parse(chartData.dataset.budgetBudgeted);
    var budgetActual = JSON.parse(chartData.dataset.budgetActual);
    var roleNames = JSON.parse(chartData.dataset.roleNames);
    var roleTotals = JSON.parse(chartData.dataset.roleTotals);

    // Budget Bar Chart
    var ctxBar = document.getElementById("budgetBarChart");
    var myBarChart = new Chart(ctxBar, {
        type: 'bar',
        data: {
            labels: budgetNames,
            datasets: [{
                label: "Budgeted",
                backgroundColor: "#4e73df",
                data: budgetBudgeted,
            }, {
                label: "Actual",
                backgroundColor: "#1cc88a",
                data: budgetActual,
            }],
        },
        options: { maintainAspectRatio: false }
    });

    // Roles Pie Chart
    var ctxPie = document.getElementById("rolesPieChart");
    var myPieChart = new Chart(ctxPie, {
        type: 'pie',
        data: {
            labels: roleNames,
            datasets: [{
                data: roleTotals,
                backgroundColor: ['#4e73df', '#1cc88a', '#36b9cc', '#f6c23e', '#e74a3b'],
            }],
        },
        options: { maintainAspectRatio: false }
    });
</script>
@endsection
