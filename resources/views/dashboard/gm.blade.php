@extends('layouts.app')
@section('title', 'GM Dashboard')
@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h1 class="h3 mb-0 text-gray-800"><i class="fas fa-chart-line me-2 text-primary"></i>General Manager Dashboard</h1>
            <small class="text-muted">{{ now()->format('l, F j, Y') }}</small>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('gm.hr-reports') }}" class="btn btn-outline-primary btn-sm rounded-pill px-3 shadow-sm">
                <i class="fa-solid fa-file-signature me-1"></i>Submitted HR Reports
            </a>
            <a href="{{ route('reports.attendance') }}" class="btn btn-primary btn-sm rounded-pill px-3 shadow-sm">
                <i class="fa-solid fa-calendar-check me-1"></i>HR Reports
            </a>
        </div>
    </div>

    <!-- KPI Row 1 -->
    <div class="row g-3 mb-3">
        <div class="col-xl-3 col-md-6">
            <div class="card border-0 shadow-sm h-100" style="border-left: 4px solid #4e73df !important;">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <div class="text-xs fw-bold text-primary text-uppercase mb-1" style="font-size:0.75rem;letter-spacing:0.5px;">Active Projects</div>
                            <div class="h4 mb-0 fw-bold text-dark">{{ $kpi['active_projects'] }}</div>
                        </div>
                        <div class="bg-primary bg-opacity-10 rounded-circle p-3">
                            <i class="fas fa-building fa-lg text-primary"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card border-0 shadow-sm h-100" style="border-left: 4px solid #1cc88a !important;">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <div class="text-xs fw-bold text-success text-uppercase mb-1" style="font-size:0.75rem;letter-spacing:0.5px;">Total Contract Value</div>
                            <div class="h4 mb-0 fw-bold text-dark">{{ number_format($kpi['total_contract_value'], 0) }} ETB</div>
                        </div>
                        <div class="bg-success bg-opacity-10 rounded-circle p-3">
                            <i class="fas fa-file-contract fa-lg text-success"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card border-0 shadow-sm h-100" style="border-left: 4px solid #36b9cc !important;">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <div class="text-xs fw-bold text-info text-uppercase mb-1" style="font-size:0.75rem;letter-spacing:0.5px;">Budget Utilization</div>
                            <div class="h4 mb-0 fw-bold text-dark">{{ $kpi['budget_utilization'] }}%</div>
                            <div class="progress mt-2" style="height:4px">
                                <div class="progress-bar bg-info" style="width:{{ min($kpi['budget_utilization'], 100) }}%"></div>
                            </div>
                        </div>
                        <div class="bg-info bg-opacity-10 rounded-circle p-3">
                            <i class="fas fa-percent fa-lg text-info"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card border-0 shadow-sm h-100" style="border-left: 4px solid #f6c23e !important;">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <div class="text-xs fw-bold text-warning text-uppercase mb-1" style="font-size:0.75rem;letter-spacing:0.5px;">Pending Employee Approvals</div>
                            <div class="h4 mb-0 fw-bold text-dark">{{ $kpi['pending_approvals'] }}</div>
                        </div>
                        <div class="bg-warning bg-opacity-10 rounded-circle p-3">
                            <i class="fas fa-user-clock fa-lg text-warning"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- KPI Row 2 -->
    <div class="row g-3 mb-4">
        <div class="col-xl-3 col-md-6">
            <div class="card border-0 shadow-sm h-100" style="border-left: 4px solid #6f42c1 !important;">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <div class="text-xs fw-bold text-purple text-uppercase mb-1" style="font-size:0.75rem;letter-spacing:0.5px;color:#6f42c1">Active Employees</div>
                            <div class="h4 mb-0 fw-bold text-dark">{{ $kpi['total_employees'] }}</div>
                        </div>
                        <div class="rounded-circle p-3" style="background:rgba(111,66,193,0.1)">
                            <i class="fas fa-users fa-lg" style="color:#6f42c1"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card border-0 shadow-sm h-100" style="border-left: 4px solid #e74a3b !important;">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <div class="text-xs fw-bold text-danger text-uppercase mb-1" style="font-size:0.75rem;letter-spacing:0.5px;">Pending Expenses</div>
                            <div class="h4 mb-0 fw-bold text-dark">{{ $kpi['pending_expenses'] }}</div>
                        </div>
                        <div class="bg-danger bg-opacity-10 rounded-circle p-3">
                            <i class="fas fa-receipt fa-lg text-danger"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card border-0 shadow-sm h-100" style="border-left: 4px solid #fd7e14 !important;">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <div class="text-xs fw-bold text-uppercase mb-1" style="font-size:0.75rem;letter-spacing:0.5px;color:#fd7e14">Pending Payroll</div>
                            <div class="h4 mb-0 fw-bold text-dark">{{ $kpi['pending_payroll'] }}</div>
                        </div>
                        <div class="rounded-circle p-3" style="background:rgba(253,126,20,0.1)">
                            <i class="fas fa-money-bill-wave fa-lg" style="color:#fd7e14"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card border-0 shadow-sm h-100" style="border-left: 4px solid #e74a3b !important;">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <div class="text-xs fw-bold text-danger text-uppercase mb-1" style="font-size:0.75rem;letter-spacing:0.5px;">Open Issues</div>
                            <div class="h4 mb-0 fw-bold text-dark">{{ $kpi['open_issues'] }}</div>
                        </div>
                        <div class="bg-danger bg-opacity-10 rounded-circle p-3">
                            <i class="fas fa-triangle-exclamation fa-lg text-danger"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Charts & Tables Row -->
    <div class="row g-3 mb-4">
        <!-- Project Status Chart -->
        <div class="col-xl-4">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center">
                    <h6 class="mb-0 fw-bold"><i class="fas fa-chart-pie me-2 text-primary"></i>Project Status</h6>
                </div>
                <div class="card-body">
                    <div id="gm-chart-data" data-status="@json($projectStatus->pluck('status'))" data-total="@json($projectStatus->pluck('total'))" class="d-none"></div>
                    <div style="position:relative;height:220px">
                        <canvas id="statusDonutChart"></canvas>
                    </div>
                    <div class="mt-3">
                        @foreach($projectStatus as $ps)
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <span class="badge rounded-pill
                                @if($ps->status == 'active') bg-success
                                @elseif($ps->status == 'completed') bg-primary
                                @elseif($ps->status == 'cancelled') bg-danger
                                @else bg-secondary @endif">
                                {{ ucfirst($ps->status) }}
                            </span>
                            <strong>{{ $ps->total }}</strong>
                        </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>

        <!-- Employees Awaiting Approval -->
        <div class="col-xl-8">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center">
                    <h6 class="mb-0 fw-bold"><i class="fas fa-user-clock me-2 text-warning"></i>Employees Awaiting GM Approval</h6>
                    <a href="{{ route('employees.pending-approval') }}" class="btn btn-sm btn-outline-primary fw-semibold px-3 rounded-pill">View All</a>
                </div>
                <div class="card-body p-0">
                    @if($pendingEmployees->isEmpty())
                        <div class="text-center text-muted py-5">
                            <i class="fas fa-check-circle fa-3x text-success mb-3"></i>
                            <p>All employees have been approved!</p>
                        </div>
                    @else
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Employee</th>
                                    <th>Department</th>
                                    <th>Joined</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($pendingEmployees as $emp)
                                <tr>
                                    <td>
                                        <div class="fw-semibold">{{ $emp->full_name }}</div>
                                        <small class="text-muted">{{ $emp->employee_code }}</small>
                                    </td>
                                    <td><span class="badge bg-light text-dark">{{ $emp->department ?? 'N/A' }}</span></td>
                                    <td>{{ optional($emp->created_at)->format('d M Y') }}</td>
                                    <td>
                                        <form action="{{ route('employees.approve', $emp) }}" method="POST" class="d-inline">
                                            @csrf @method('PUT')
                                            <button type="submit" class="btn btn-sm btn-success">
                                                <i class="fas fa-check me-1"></i>Approve
                                            </button>
                                        </form>
                                        <a href="{{ route('employees.show', $emp) }}" class="btn btn-sm btn-outline-secondary ms-1">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Projects Table -->
    <div class="row g-3">
        <div class="col-12">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center">
                    <h6 class="mb-0 fw-bold"><i class="fas fa-building me-2 text-success"></i>Recent Projects</h6>
                    <a href="{{ route('projects.index') }}" class="btn btn-sm btn-outline-success">View All Projects</a>
                </div>
                <div class="card-body p-0">
                    @if($recentProjects->isEmpty())
                        <div class="text-center text-muted py-4"><p>No projects found.</p></div>
                    @else
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Project</th>
                                    <th>Status</th>
                                    <th>Contract Value</th>
                                    <th>Start Date</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($recentProjects as $proj)
                                <tr>
                                    <td class="fw-semibold">{{ $proj->name }}</td>
                                    <td>
                                        <span class="badge rounded-pill
                                            @if($proj->status == 'active') bg-success
                                            @elseif($proj->status == 'completed') bg-primary
                                            @elseif($proj->status == 'cancelled') bg-danger
                                            @else bg-secondary @endif">
                                            {{ ucfirst($proj->status) }}
                                        </span>
                                    </td>
                                    <td>{{ number_format($proj->contract_value ?? 0, 0) }} ETB</td>
                                    <td>{{ optional($proj->start_date)->format('d M Y') ?? 'N/A' }}</td>
                                    <td>
                                        <a href="{{ route('projects.show', $proj) }}" class="btn btn-sm btn-outline-primary">
                                            <i class="fas fa-eye me-1"></i>View
                                        </a>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    var chartElement = document.getElementById("gm-chart-data");
    var statusLabels = JSON.parse(chartElement.dataset.status || '[]');
    var statusTotals = JSON.parse(chartElement.dataset.total || '[]');

    if (statusLabels.length > 0) {
        var ctxDonut = document.getElementById("statusDonutChart");
        new Chart(ctxDonut, {
            type: 'doughnut',
            data: {
                labels: statusLabels,
                datasets: [{
                    data: statusTotals,
                    backgroundColor: ['#4e73df', '#1cc88a', '#36b9cc', '#f6c23e', '#e74a3b'],
                    borderWidth: 2,
                }],
            },
            options: {
                maintainAspectRatio: false,
                plugins: { legend: { position: 'bottom' } },
                cutout: '70%',
            }
        });
    }
</script>
@endsection
