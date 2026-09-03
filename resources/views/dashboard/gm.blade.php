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
    <div class="row g-3 mb-3">
        <div class="col-xl-3 col-md-6">
            <div class="card border-0 shadow-sm h-100" style="border-left: 4px solid #6f42c1 !important;">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <div class="text-xs fw-bold text-uppercase mb-1" style="font-size:0.75rem;letter-spacing:0.5px;color:#6f42c1">Active Employees</div>
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
            <!-- Total Cash Expense -->
            <div class="card border-0 shadow-sm h-100" style="border-left: 4px solid #fd7e14 !important;">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <div class="text-xs fw-bold text-uppercase mb-1" style="font-size:0.75rem;letter-spacing:0.5px;color:#fd7e14">Total Cash Expenses</div>
                            <div class="h5 mb-0 fw-bold text-dark">{{ number_format($kpi['total_cash_expense'] ?? 0, 0) }} <small class="text-muted fs-6">ETB</small></div>
                        </div>
                        <div class="rounded-circle p-3" style="background:rgba(253,126,20,0.1)">
                            <i class="fas fa-money-bill-wave fa-lg" style="color:#fd7e14"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <!-- Total Material Consumption Cost -->
            <div class="card border-0 shadow-sm h-100" style="border-left: 4px solid #20c997 !important;">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <div class="text-xs fw-bold text-uppercase mb-1" style="font-size:0.75rem;letter-spacing:0.5px;color:#20c997">Material Consumption Cost</div>
                            <div class="h5 mb-0 fw-bold text-dark">{{ number_format($kpi['total_material_cost'] ?? 0, 0) }} <small class="text-muted fs-6">ETB</small></div>
                        </div>
                        <div class="rounded-circle p-3" style="background:rgba(32,201,151,0.1)">
                            <i class="fas fa-cubes fa-lg" style="color:#20c997"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Charts Row -->
    <div class="row g-3 mb-4">
        <!-- Project Status Chart -->
        <div class="col-xl-3">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center">
                    <h6 class="mb-0 fw-bold"><i class="fas fa-chart-pie me-2 text-primary"></i>Project Status</h6>
                </div>
                <div class="card-body">
                    <div id="gm-chart-data" data-status="@json($projectStatus->pluck('status'))" data-total="@json($projectStatus->pluck('total'))" class="d-none"></div>
                    <div style="position:relative;height:180px">
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

        <!-- Monthly Expense Trend Chart -->
        <div class="col-xl-5">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center">
                    <h6 class="mb-0 fw-bold"><i class="fas fa-chart-bar me-2 text-warning"></i>Monthly Expense Trend <small class="text-muted fw-normal">(Last 6 Months)</small></h6>
                </div>
                <div class="card-body">
                    <div id="gm-trend-data"
                        data-labels="@json(collect($monthlyExpenseTrend)->pluck('label'))"
                        data-cash="@json(collect($monthlyExpenseTrend)->pluck('cash'))"
                        data-material="@json(collect($monthlyExpenseTrend)->pluck('material'))"
                        class="d-none"></div>
                    <div style="position:relative;height:220px">
                        <canvas id="expenseTrendChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Expense Category Breakdown -->
        <div class="col-xl-4">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header bg-white border-bottom py-3">
                    <h6 class="mb-0 fw-bold"><i class="fas fa-tags me-2 text-info"></i>Expense by Category</h6>
                </div>
                <div class="card-body p-0">
                    @if($expenseCategoryBreakdown->isEmpty())
                        <div class="text-center text-muted py-4">
                            <i class="fas fa-inbox fa-2x mb-2 d-block"></i>No expense data
                        </div>
                    @else
                    <ul class="list-group list-group-flush">
                        @foreach($expenseCategoryBreakdown as $cat)
                        @php
                            $catTotal = $expenseCategoryBreakdown->sum('total');
                            $pct = $catTotal > 0 ? round(($cat->total / $catTotal) * 100, 1) : 0;
                            $colors = ['#4e73df','#1cc88a','#36b9cc','#f6c23e','#e74a3b','#fd7e14','#6f42c1','#20c997'];
                            $colorIdx = $loop->index % count($colors);
                        @endphp
                        <li class="list-group-item border-0 py-2 px-3">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <span class="fw-semibold text-capitalize" style="font-size:0.85rem;">{{ $cat->category ?? 'Uncategorized' }}</span>
                                <span class="text-muted small">{{ number_format($cat->total, 0) }} ETB</span>
                            </div>
                            <div class="progress" style="height:5px">
                                <div class="progress-bar" style="width:{{ $pct }}%;background:{{ $colors[$colorIdx] }}"></div>
                            </div>
                            <small class="text-muted">{{ $pct }}% · {{ $cat->count }} requests</small>
                        </li>
                        @endforeach
                    </ul>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <!-- ═══ PROJECT EXPENSES TABLE ═══════════════════════════════════════════════ -->
    <div id="project-expenses" class="row g-3 mb-4">
        <div class="col-12">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <h6 class="mb-0 fw-bold"><i class="fas fa-chart-pie me-2 text-danger"></i>Project Expenses Tracker
                        <span class="badge bg-danger bg-opacity-10 text-danger ms-2 fw-normal" style="font-size:0.75rem;">Cash + Material Consumption</span>
                    </h6>
                    <a href="{{ route('projects.index') }}" class="btn btn-sm btn-outline-primary rounded-pill px-3">
                        <i class="fas fa-external-link-alt me-1"></i>All Projects
                    </a>
                </div>
                <div class="card-body p-0">
                    @if($projectExpenses->isEmpty())
                        <div class="text-center text-muted py-5">
                            <i class="fas fa-folder-open fa-3x mb-3 text-muted"></i>
                            <p>No project expense data available.</p>
                        </div>
                    @else
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-3">Project</th>
                                    <th class="text-center">Status</th>
                                    <th class="text-end">Contract Value</th>
                                    <th class="text-end">Budget Allocated</th>
                                    <th class="text-end">
                                        <i class="fas fa-receipt me-1 text-warning"></i>Cash Expenses
                                    </th>
                                    <th class="text-end">
                                        <i class="fas fa-cubes me-1 text-success"></i>Material Cost
                                    </th>
                                    <th class="text-end fw-bold">Total Expense</th>
                                    <th class="text-end">Remaining</th>
                                    <th style="min-width:120px;">Budget Usage</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($projectExpenses as $pe)
                                <tr>
                                    <td class="ps-3">
                                        <div class="fw-semibold">{{ $pe['name'] }}</div>
                                        <small class="text-muted badge bg-light text-dark">{{ $pe['code'] }}</small>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge rounded-pill
                                            @if($pe['status'] == 'active') bg-success
                                            @elseif($pe['status'] == 'completed') bg-primary
                                            @elseif($pe['status'] == 'cancelled') bg-danger
                                            @elseif($pe['status'] == 'planning') bg-info
                                            @else bg-secondary @endif">
                                            {{ ucfirst($pe['status']) }}
                                        </span>
                                    </td>
                                    <td class="text-end text-muted">{{ number_format($pe['contract_value'], 0) }}</td>
                                    <td class="text-end text-muted">
                                        @if($pe['budget_allocated'] > 0)
                                            {{ number_format($pe['budget_allocated'], 0) }}
                                        @else
                                            <span class="text-muted fst-italic">Not set</span>
                                        @endif
                                    </td>
                                    <td class="text-end">
                                        <span class="text-warning fw-semibold">{{ number_format($pe['cash_expenses'], 0) }}</span>
                                    </td>
                                    <td class="text-end">
                                        <span class="text-success fw-semibold">{{ number_format($pe['material_cost'], 0) }}</span>
                                    </td>
                                    <td class="text-end fw-bold text-dark">
                                        {{ number_format($pe['total_expense'], 0) }} <small class="text-muted fw-normal">ETB</small>
                                    </td>
                                    <td class="text-end">
                                        @if($pe['budget_allocated'] > 0)
                                            <span class="fw-semibold text-{{ $pe['budget_status'] }}">
                                                {{ number_format($pe['remaining_budget'], 0) }}
                                            </span>
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                    </td>
                                    <td style="min-width:120px">
                                        @if($pe['budget_allocated'] > 0)
                                            <div class="d-flex align-items-center gap-2">
                                                <div class="progress flex-grow-1" style="height:8px;border-radius:4px">
                                                    <div class="progress-bar bg-{{ $pe['budget_status'] }}"
                                                        style="width:{{ min($pe['utilization'], 100) }}%"
                                                        title="{{ $pe['utilization'] }}%"></div>
                                                </div>
                                                <small class="text-{{ $pe['budget_status'] }} fw-semibold" style="min-width:38px">{{ $pe['utilization'] }}%</small>
                                            </div>
                                        @else
                                            <span class="text-muted small fst-italic">No budget</span>
                                        @endif
                                    </td>
                                    <td>
                                        <a href="{{ route('projects.show', $pe['id']) }}" class="btn btn-sm btn-outline-primary rounded-pill px-2">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                            <tfoot class="table-light">
                                <tr class="fw-bold">
                                    <td class="ps-3" colspan="4">Totals</td>
                                    <td class="text-end text-warning">{{ number_format($projectExpenses->sum('cash_expenses'), 0) }} ETB</td>
                                    <td class="text-end text-success">{{ number_format($projectExpenses->sum('material_cost'), 0) }} ETB</td>
                                    <td class="text-end text-dark">{{ number_format($projectExpenses->sum('total_expense'), 0) }} ETB</td>
                                    <td colspan="3"></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <!-- ═══ MATERIAL CONSUMPTION REPORT ══════════════════════════════════════════ -->
    <div class="row g-3 mb-4">
        <div class="col-12">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <h6 class="mb-0 fw-bold"><i class="fas fa-boxes me-2 text-success"></i>Material Consumption Report
                        <span class="badge bg-success bg-opacity-10 text-success ms-2 fw-normal" style="font-size:0.75rem;">Priced by Unit Cost</span>
                    </h6>
                    <div class="d-flex gap-2 align-items-center">
                        <small class="text-muted">Top 20 by cost · Confirmed usages only</small>
                        <a href="{{ route('material-usages.index') }}" class="btn btn-sm btn-outline-success rounded-pill px-3">
                            <i class="fas fa-external-link-alt me-1"></i>All Usages
                        </a>
                    </div>
                </div>
                <div class="card-body p-0">
                    @if($materialConsumptionReport->isEmpty())
                        <div class="text-center text-muted py-5">
                            <i class="fas fa-box-open fa-3x mb-3 text-muted"></i>
                            <p>No material consumption data available yet.</p>
                        </div>
                    @else
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-3">#</th>
                                    <th>Material / Product</th>
                                    <th>Project</th>
                                    <th class="text-end">Total Qty Used</th>
                                    <th class="text-end">Unit</th>
                                    <th class="text-end">Avg Unit Cost (ETB)</th>
                                    <th class="text-end fw-bold">Total Cost (ETB)</th>
                                    <th>Cost Share</th>
                                </tr>
                            </thead>
                            <tbody>
                                @php $grandTotal = $materialConsumptionReport->sum('total_cost'); @endphp
                                @foreach($materialConsumptionReport as $idx => $row)
                                @php
                                    $pct = $grandTotal > 0 ? round(($row->total_cost / $grandTotal) * 100, 1) : 0;
                                    $barColors = ['#4e73df','#1cc88a','#36b9cc','#f6c23e','#e74a3b','#fd7e14','#6f42c1','#20c997'];
                                    $barColor = $barColors[$idx % count($barColors)];
                                @endphp
                                <tr>
                                    <td class="ps-3 text-muted">{{ $idx + 1 }}</td>
                                    <td>
                                        <div class="fw-semibold">{{ $row->product_name }}</div>
                                    </td>
                                    <td>
                                        <span class="badge bg-light text-dark border">{{ $row->project_code }}</span>
                                        <small class="text-muted d-block" style="font-size:0.75rem">{{ Str::limit($row->project_name, 30) }}</small>
                                    </td>
                                    <td class="text-end fw-semibold">{{ number_format($row->total_qty, 2) }}</td>
                                    <td class="text-end text-muted">{{ $row->product_unit }}</td>
                                    <td class="text-end">
                                        @if($row->avg_unit_cost > 0)
                                            {{ number_format($row->avg_unit_cost, 2) }}
                                        @else
                                            <span class="text-muted fst-italic small">—</span>
                                        @endif
                                    </td>
                                    <td class="text-end fw-bold text-dark">
                                        @if($row->total_cost > 0)
                                            {{ number_format($row->total_cost, 0) }}
                                        @else
                                            <span class="text-muted fst-italic small">No price</span>
                                        @endif
                                    </td>
                                    <td style="min-width:120px">
                                        <div class="d-flex align-items-center gap-2">
                                            <div class="progress flex-grow-1" style="height:8px;border-radius:4px">
                                                <div class="progress-bar" style="width:{{ $pct }}%;background:{{ $barColor }}"></div>
                                            </div>
                                            <small style="color:{{ $barColor }};min-width:38px;font-weight:600">{{ $pct }}%</small>
                                        </div>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                            <tfoot class="table-light">
                                <tr class="fw-bold">
                                    <td class="ps-3" colspan="6">Grand Total Material Cost</td>
                                    <td class="text-end text-dark">{{ number_format($grandTotal, 0) }} ETB</td>
                                    <td></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <!-- ═══ BOTTOM ROW ═══════════════════════════════════════════════════════════ -->
    <div class="row g-3">
        <!-- Employees Awaiting Approval -->
        <div class="col-xl-7">
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

        <!-- Recent Projects -->
        <div class="col-xl-5">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center">
                    <h6 class="mb-0 fw-bold"><i class="fas fa-building me-2 text-success"></i>Recent Projects</h6>
                    <a href="{{ route('projects.index') }}" class="btn btn-sm btn-outline-success">View All</a>
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
                                    <th></th>
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
// ── Project Status Donut Chart ────────────────────────────────────────────────
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
            plugins: { legend: { position: 'bottom', labels: { boxWidth: 12, font: { size: 11 } } } },
            cutout: '70%',
        }
    });
}

// ── Monthly Expense Trend Bar Chart ─────────────────────────────────────────
var trendEl = document.getElementById("gm-trend-data");
var trendLabels   = JSON.parse(trendEl.dataset.labels   || '[]');
var trendCash     = JSON.parse(trendEl.dataset.cash     || '[]');
var trendMaterial = JSON.parse(trendEl.dataset.material || '[]');

if (trendLabels.length > 0) {
    var ctxTrend = document.getElementById("expenseTrendChart");
    new Chart(ctxTrend, {
        type: 'bar',
        data: {
            labels: trendLabels,
            datasets: [
                {
                    label: 'Cash Expenses',
                    data: trendCash,
                    backgroundColor: 'rgba(253,126,20,0.75)',
                    borderRadius: 4,
                },
                {
                    label: 'Material Consumption',
                    data: trendMaterial,
                    backgroundColor: 'rgba(32,201,151,0.75)',
                    borderRadius: 4,
                }
            ],
        },
        options: {
            maintainAspectRatio: false,
            responsive: true,
            plugins: {
                legend: { position: 'bottom', labels: { boxWidth: 12, font: { size: 11 } } },
                tooltip: {
                    callbacks: {
                        label: function(ctx) {
                            return ctx.dataset.label + ': ' + new Intl.NumberFormat().format(ctx.parsed.y) + ' ETB';
                        }
                    }
                }
            },
            scales: {
                x: { stacked: true, grid: { display: false } },
                y: {
                    stacked: true,
                    ticks: {
                        callback: function(val) {
                            if (val >= 1000000) return (val/1000000).toFixed(1) + 'M';
                            if (val >= 1000) return (val/1000).toFixed(0) + 'K';
                            return val;
                        }
                    }
                }
            }
        }
    });
}
</script>
@endsection
