@extends('layouts.app')
@section('title', 'GM Executive Dashboard')
@section('content')
<div class="container-fluid py-2">
    {{-- Top Executive Header --}}
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2 pb-2 border-bottom">
        <div>
            <div class="d-flex align-items-center gap-2">
                <span class="badge bg-primary bg-opacity-10 text-primary px-3 py-1 rounded-pill fw-semibold" style="font-size:0.8rem;">Executive Management</span>
                <span class="badge bg-light text-muted border px-2 py-1 rounded-pill" style="font-size:0.75rem;"><i class="fa-regular fa-clock me-1"></i>{{ now()->format('l, F j, Y') }}</span>
            </div>
            <h1 class="h3 mb-0 text-gray-800 fw-bold mt-1">
                <i class="fas fa-chart-line text-primary me-2"></i>General Manager Executive Dashboard
            </h1>
        </div>
        <div class="d-flex gap-2 flex-wrap align-items-center">
            <a href="{{ route('purchase-requests.index', ['status' => 'pending_gm']) }}" class="btn btn-outline-danger btn-sm rounded-pill px-3 shadow-sm position-relative">
                <i class="fa-solid fa-cart-arrow-down me-1"></i>PR Decisions
                @if(($kpi['pending_gm_prs'] ?? 0) > 0)
                    <span class="badge bg-danger rounded-pill ms-1">{{ $kpi['pending_gm_prs'] }}</span>
                @endif
            </a>
            <a href="{{ route('expenses.index', ['tab' => 'pending_gm']) }}" class="btn btn-outline-warning btn-sm rounded-pill px-3 shadow-sm position-relative">
                <i class="fa-solid fa-file-invoice-dollar me-1"></i>Expense Approvals
                @if(($kpi['pending_gm_expenses'] ?? 0) > 0)
                    <span class="badge bg-warning text-dark rounded-pill ms-1">{{ $kpi['pending_gm_expenses'] }}</span>
                @endif
            </a>
            <a href="{{ route('employees.pending-approval') }}" class="btn btn-outline-primary btn-sm rounded-pill px-3 shadow-sm">
                <i class="fa-solid fa-user-check me-1"></i>Employee Approvals
                @if(($kpi['pending_approvals'] ?? 0) > 0)
                    <span class="badge bg-primary rounded-pill ms-1">{{ $kpi['pending_approvals'] }}</span>
                @endif
            </a>
            <a href="{{ route('finance.payroll.gm') }}" class="btn btn-outline-info btn-sm rounded-pill px-3 shadow-sm">
                <i class="fa-solid fa-file-signature me-1"></i>Payroll Approvals
            </a>
            <a href="{{ route('gm.hr-reports') }}" class="btn btn-primary btn-sm rounded-pill px-3 shadow-sm">
                <i class="fa-solid fa-file-waveform me-1"></i>HR Reports
            </a>
        </div>
    </div>

    {{-- ═══ EXECUTIVE KPI CARDS ════════════════════════════════════════════════ --}}
    <div class="row g-3 mb-4">
        {{-- Card 1: PR Decisions Pending GM --}}
        <div class="col-xl-4 col-md-6">
            <div class="card border-0 shadow-sm h-100 position-relative overflow-hidden" style="border-left: 4px solid #e74a3b !important; border-radius: 12px;">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <div class="text-xs fw-bold text-danger text-uppercase mb-1" style="font-size:0.75rem;letter-spacing:0.5px;">
                                <i class="fa-solid fa-gavel me-1"></i>Procurement GM Decisions
                            </div>
                            <div class="h3 mb-0 fw-bold text-dark">{{ $kpi['pending_gm_prs'] ?? 0 }}</div>
                            <small class="text-muted">
                                @if(($kpi['pending_gm_prs'] ?? 0) > 0)
                                    Est. Value: <strong class="text-danger">{{ number_format($kpi['pending_gm_prs_amount'] ?? 0, 2) }} ETB</strong>
                                @else
                                    <span class="text-success"><i class="fa-solid fa-check-circle me-1"></i>All caught up</span>
                                @endif
                            </small>
                        </div>
                        <div class="rounded-circle p-3 d-flex align-items-center justify-content-center" style="background: rgba(231,74,59,0.12); width:54px; height:54px;">
                            <i class="fa-solid fa-cart-arrow-down fa-xl text-danger"></i>
                        </div>
                    </div>
                    @if(($kpi['pending_gm_prs'] ?? 0) > 0)
                        <div class="mt-2 pt-2 border-top d-flex justify-content-between align-items-center">
                            <span class="badge bg-danger bg-opacity-10 text-danger rounded-pill px-2 py-0" style="font-size:0.7rem;">Action Required</span>
                            <a href="#gm-procurement-hub" class="text-danger fw-semibold small text-decoration-none">Review Requests <i class="fa-solid fa-arrow-down ms-1"></i></a>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        {{-- Card 2: Pending Expenses & Financial Authorizations --}}
        <div class="col-xl-4 col-md-6">
            <div class="card border-0 shadow-sm h-100 position-relative overflow-hidden" style="border-left: 4px solid #f6c23e !important; border-radius: 12px;">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <div class="text-xs fw-bold text-warning text-uppercase mb-1" style="font-size:0.75rem;letter-spacing:0.5px;">
                                <i class="fa-solid fa-file-invoice-dollar me-1"></i>Expense Authorizations
                            </div>
                            <div class="h3 mb-0 fw-bold text-dark">{{ $kpi['pending_gm_expenses'] ?? 0 }}</div>
                            <small class="text-muted">
                                Total: <strong class="text-dark">{{ number_format($kpi['pending_gm_expenses_amount'] ?? 0, 2) }} ETB</strong>
                            </small>
                        </div>
                        <div class="rounded-circle p-3 d-flex align-items-center justify-content-center" style="background: rgba(246,194,62,0.15); width:54px; height:54px;">
                            <i class="fa-solid fa-receipt fa-xl text-warning"></i>
                        </div>
                    </div>
                    <div class="mt-2 pt-2 border-top d-flex justify-content-between align-items-center">
                        <small class="text-muted">Loans: <strong>{{ $kpi['pending_loans'] ?? 0 }}</strong> · Payroll: <strong>{{ $kpi['pending_payroll'] ?? 0 }}</strong></small>
                        <a href="{{ route('expenses.index', ['tab' => 'pending_gm']) }}" class="text-warning fw-semibold small text-decoration-none">View Expenses <i class="fa-solid fa-arrow-right ms-1"></i></a>
                    </div>
                </div>
            </div>
        </div>

        {{-- Card 3: Active Projects & Total Portfolio Value --}}
        <div class="col-xl-4 col-md-6">
            <div class="card border-0 shadow-sm h-100 position-relative overflow-hidden" style="border-left: 4px solid #4e73df !important; border-radius: 12px;">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <div class="text-xs fw-bold text-primary text-uppercase mb-1" style="font-size:0.75rem;letter-spacing:0.5px;">
                                <i class="fa-solid fa-building me-1"></i>Projects Portfolio
                            </div>
                            <div class="h3 mb-0 fw-bold text-dark">{{ $kpi['active_projects'] ?? 0 }} <span class="fs-6 fw-normal text-muted">Active</span></div>
                            <small class="text-muted">
                                Contract Value: <strong class="text-primary">{{ number_format($kpi['total_contract_value'] ?? 0, 0) }} ETB</strong>
                            </small>
                        </div>
                        <div class="rounded-circle p-3 d-flex align-items-center justify-content-center" style="background: rgba(78,115,223,0.12); width:54px; height:54px;">
                            <i class="fa-solid fa-city fa-xl text-primary"></i>
                        </div>
                    </div>
                    <div class="mt-2 pt-2 border-top d-flex justify-content-between align-items-center">
                        <small class="text-muted">Open Issues: <strong class="{{ ($kpi['open_issues'] ?? 0) > 0 ? 'text-danger' : 'text-success' }}">{{ $kpi['open_issues'] ?? 0 }}</strong></small>
                        <a href="{{ route('projects.index') }}" class="text-primary fw-semibold small text-decoration-none">Projects Directory <i class="fa-solid fa-arrow-right ms-1"></i></a>
                    </div>
                </div>
            </div>
        </div>

        {{-- Card 4: Budget Utilization --}}
        <div class="col-xl-4 col-md-6">
            <div class="card border-0 shadow-sm h-100 position-relative overflow-hidden" style="border-left: 4px solid #36b9cc !important; border-radius: 12px;">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center justify-content-between">
                        <div class="flex-grow-1 me-2">
                            <div class="text-xs fw-bold text-info text-uppercase mb-1" style="font-size:0.75rem;letter-spacing:0.5px;">
                                <i class="fa-solid fa-chart-pie me-1"></i>Budget Utilization
                            </div>
                            <div class="h3 mb-0 fw-bold text-dark">{{ $kpi['budget_utilization'] ?? 0 }}%</div>
                            <div class="progress mt-2" style="height:6px; border-radius:3px;">
                                <div class="progress-bar bg-info" style="width:{{ min($kpi['budget_utilization'] ?? 0, 100) }}%"></div>
                            </div>
                        </div>
                        <div class="rounded-circle p-3 d-flex align-items-center justify-content-center" style="background: rgba(54,185,204,0.12); width:54px; height:54px;">
                            <i class="fa-solid fa-percent fa-xl text-info"></i>
                        </div>
                    </div>
                    <div class="mt-2 pt-2 border-top d-flex justify-content-between align-items-center">
                        <small class="text-muted">Health: <strong>{{ ($kpi['budget_utilization'] ?? 0) > 85 ? 'High Usage' : 'Healthy' }}</strong></small>
                        <a href="{{ route('budgets.index') }}" class="text-info fw-semibold small text-decoration-none">Budget Tracker <i class="fa-solid fa-arrow-right ms-1"></i></a>
                    </div>
                </div>
            </div>
        </div>

        {{-- Card 5: Workforce & Pending Approvals --}}
        <div class="col-xl-4 col-md-6">
            <div class="card border-0 shadow-sm h-100 position-relative overflow-hidden" style="border-left: 4px solid #6f42c1 !important; border-radius: 12px;">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <div class="text-xs fw-bold text-uppercase mb-1" style="font-size:0.75rem;letter-spacing:0.5px; color:#6f42c1;">
                                <i class="fa-solid fa-users me-1"></i>Workforce & Approvals
                            </div>
                            <div class="h3 mb-0 fw-bold text-dark">{{ $kpi['total_employees'] ?? 0 }} <span class="fs-6 fw-normal text-muted">Active Staff</span></div>
                            <small class="text-muted">
                                Pending Approvals: <strong class="{{ ($kpi['pending_approvals'] ?? 0) > 0 ? 'text-warning' : 'text-success' }}">{{ $kpi['pending_approvals'] ?? 0 }} hires</strong>
                            </small>
                        </div>
                        <div class="rounded-circle p-3 d-flex align-items-center justify-content-center" style="background: rgba(111,66,193,0.12); width:54px; height:54px;">
                            <i class="fa-solid fa-user-clock fa-xl" style="color:#6f42c1;"></i>
                        </div>
                    </div>
                    <div class="mt-2 pt-2 border-top d-flex justify-content-between align-items-center">
                        <small class="text-muted">Pending Leaves: <strong>{{ $kpi['pending_leaves'] ?? 0 }}</strong></small>
                        <a href="{{ route('employees.pending-approval') }}" class="fw-semibold small text-decoration-none" style="color:#6f42c1;">Approve Hires <i class="fa-solid fa-arrow-right ms-1"></i></a>
                    </div>
                </div>
            </div>
        </div>

        {{-- Card 6: Cumulative Spend (Cash + Material) --}}
        <div class="col-xl-4 col-md-6">
            <div class="card border-0 shadow-sm h-100 position-relative overflow-hidden" style="border-left: 4px solid #20c997 !important; border-radius: 12px;">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <div class="text-xs fw-bold text-uppercase mb-1" style="font-size:0.75rem;letter-spacing:0.5px; color:#20c997;">
                                <i class="fa-solid fa-coins me-1"></i>Cash vs Material Cost
                            </div>
                            <div class="h5 mb-0 fw-bold text-dark">
                                {{ number_format(($kpi['total_cash_expense'] ?? 0) + ($kpi['total_material_cost'] ?? 0), 0) }} <small class="text-muted fs-6">ETB</small>
                            </div>
                            <small class="text-muted">
                                Cash: <strong class="text-warning">{{ number_format($kpi['total_cash_expense'] ?? 0, 0) }}</strong> · Mat: <strong class="text-success">{{ number_format($kpi['total_material_cost'] ?? 0, 0) }}</strong>
                            </small>
                        </div>
                        <div class="rounded-circle p-3 d-flex align-items-center justify-content-center" style="background: rgba(32,201,151,0.12); width:54px; height:54px;">
                            <i class="fa-solid fa-layer-group fa-xl" style="color:#20c997;"></i>
                        </div>
                    </div>
                    <div class="mt-2 pt-2 border-top d-flex justify-content-between align-items-center">
                        <small class="text-muted">Top Materials: <strong>20 tracked</strong></small>
                        <a href="#project-expenses" class="fw-semibold small text-decoration-none" style="color:#20c997;">Cost Breakdown <i class="fa-solid fa-arrow-down ms-1"></i></a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ═══ NEW FEATURE: UNASSIGNED ROLE ESCALATIONS ALERT (OVERSIGHT) ════════════ --}}
    @if(isset($unassignedStagePrs) && $unassignedStagePrs->isNotEmpty())
    <div class="card border-0 shadow-sm mb-4" style="background: linear-gradient(135deg, #fffbeb, #fef3c7); border-left: 4px solid #f59e0b !important; border-radius: 12px;">
        <div class="card-body p-3">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-2">
                <div class="d-flex align-items-center gap-2">
                    <span class="badge bg-warning text-dark p-2 rounded-circle"><i class="fa-solid fa-triangle-exclamation"></i></span>
                    <div>
                        <h6 class="mb-0 fw-bold text-dark">Unassigned Stage Escalations Alert ({{ $unassignedStagePrs->count() }})</h6>
                        <small class="text-muted">These purchase requests are routed to Global Admin / GM because no team member is assigned to the stage's role.</small>
                    </div>
                </div>
                <a href="{{ route('purchase-requests.index') }}" class="btn btn-sm btn-outline-warning text-dark rounded-pill px-3">Manage Sourcing</a>
            </div>
            <div class="table-responsive bg-white rounded-3 border">
                <table class="table table-hover align-middle mb-0" style="font-size: 0.85rem;">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-3">PR Number</th>
                            <th>Project</th>
                            <th>Current Stage</th>
                            <th>Requested By</th>
                            <th>Date</th>
                            <th class="text-end pe-3">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($unassignedStagePrs as $upr)
                        <tr>
                            <td class="ps-3 fw-bold text-primary">{{ $upr->pr_number }}</td>
                            <td>{{ $upr->project->name ?? 'N/A' }}</td>
                            <td>
                                <span class="badge bg-warning text-dark rounded-pill px-2 py-1">
                                    {{ ucfirst(str_replace('_', ' ', $upr->status)) }}
                                </span>
                            </td>
                            <td>{{ $upr->requestedBy->name ?? 'System' }}</td>
                            <td>{{ $upr->created_at->format('M d, Y') }}</td>
                            <td class="text-end pe-3">
                                <a href="{{ route('purchase-requests.show', $upr->id) }}" class="btn btn-sm btn-primary rounded-pill px-3">
                                    <i class="fa-solid fa-arrow-right me-1"></i>Open PR
                                </a>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    @endif

    {{-- ═══ NEW FEATURE: PROCUREMENT GM DECISION HUB (CRITICAL OPERATION) ═════════ --}}
    <div id="gm-procurement-hub" class="card shadow-sm border-0 mb-4" style="border-radius: 12px;">
        <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div class="d-flex align-items-center gap-2">
                <span class="rounded-circle p-2 bg-danger bg-opacity-10 text-danger">
                    <i class="fa-solid fa-gavel"></i>
                </span>
                <div>
                    <h6 class="mb-0 fw-bold text-dark">Procurement Purchase Requests Awaiting GM Decision</h6>
                    <small class="text-muted">Review proforma evaluations, marketing variance, direct buy requests, and authorize payments.</small>
                </div>
            </div>
            <div class="d-flex gap-2">
                <a href="{{ route('purchase-requests.index', ['status' => 'pending_gm']) }}" class="btn btn-sm btn-danger rounded-pill px-3">
                    <i class="fa-solid fa-list-check me-1"></i>View All ({{ $pendingGmPrs->count() }})
                </a>
            </div>
        </div>
        <div class="card-body p-0">
            @if($pendingGmPrs->isEmpty())
                <div class="text-center py-5">
                    <div class="rounded-circle bg-success bg-opacity-10 text-success d-inline-flex p-3 mb-2">
                        <i class="fa-solid fa-circle-check fa-2x"></i>
                    </div>
                    <h6 class="fw-bold text-dark mb-1">No Purchase Requests Awaiting GM Decision</h6>
                    <p class="text-muted small mb-0">All submitted proformas and direct-buy requests have been triaged or approved.</p>
                </div>
            @else
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-3">PR Code</th>
                                <th>Project</th>
                                <th>Sourcing Channel</th>
                                <th>Proposed Supplier / Quote</th>
                                <th class="text-end">Estimated / Quoted Total</th>
                                <th class="text-center">Priority</th>
                                <th class="text-end pe-3">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($pendingGmPrs as $pr)
                            @php
                                $selectedProforma = $pr->proformaInvoices->firstWhere('gm_selected', true)
                                    ?? $pr->proformaInvoices->firstWhere('is_selected', true)
                                    ?? $pr->proformaInvoices->first();
                                $amount = $pr->direct_buy_amount ?: ($selectedProforma ? $selectedProforma->total_amount : $pr->items->sum('estimated_total'));
                            @endphp
                            <tr>
                                <td class="ps-3">
                                    <div class="fw-bold text-dark">{{ $pr->pr_number }}</div>
                                    <small class="text-muted">{{ $pr->created_at->format('M d, Y') }}</small>
                                </td>
                                <td>
                                    <div class="fw-semibold">{{ $pr->project->name ?? 'General Store' }}</div>
                                    <small class="badge bg-light text-dark border">{{ $pr->project->code ?? 'N/A' }}</small>
                                </td>
                                <td>
                                    @if($pr->is_direct_buy)
                                        <span class="badge bg-info bg-opacity-10 text-info border border-info rounded-pill px-2 py-1">
                                            <i class="fa-solid fa-bolt me-1"></i>Direct Buy
                                        </span>
                                        @if($pr->marketingVariance)
                                            <span class="badge bg-warning bg-opacity-25 text-dark rounded-pill px-2 py-1 ms-1">
                                                <i class="fa-solid fa-chart-simple me-1"></i>Variance Checked
                                            </span>
                                        @endif
                                    @else
                                        <span class="badge bg-primary bg-opacity-10 text-primary border border-primary rounded-pill px-2 py-1">
                                            <i class="fa-solid fa-table-list me-1"></i>Proforma Quotes ({{ $pr->proformaInvoices->count() }})
                                        </span>
                                    @endif
                                </td>
                                <td>
                                    @if($pr->is_direct_buy)
                                        <span class="fw-semibold text-dark">{{ $pr->supplier->name ?? 'Direct Vendor' }}</span>
                                    @elseif($selectedProforma)
                                        <span class="fw-semibold text-dark">{{ $selectedProforma->supplier_name }}</span>
                                        <small class="text-muted d-block">{{ $selectedProforma->supplier_phone ?? '' }}</small>
                                    @else
                                        <span class="text-muted fst-italic">Pending vendor selection</span>
                                    @endif
                                </td>
                                <td class="text-end fw-bold text-dark">
                                    {{ number_format((float)$amount, 2) }} <small class="text-muted fw-normal">ETB</small>
                                </td>
                                <td class="text-center">
                                    <span class="badge rounded-pill
                                        @if($pr->priority == 'urgent') bg-danger
                                        @elseif($pr->priority == 'high') bg-warning text-dark
                                        @else bg-secondary @endif">
                                        {{ ucfirst($pr->priority ?? 'Normal') }}
                                    </span>
                                </td>
                                <td class="text-end pe-3">
                                    <a href="{{ route('purchase-requests.show', $pr->id) }}" class="btn btn-sm btn-danger rounded-pill px-3 shadow-sm">
                                        <i class="fa-solid fa-gavel me-1"></i>Make Decision
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

    {{-- ═══ EXPENSE & FINANCIAL AUTHORIZATIONS QUEUE ════════════════════════════ --}}
    <div class="row g-3 mb-4">
        {{-- Pending Expenses Queue --}}
        <div class="col-xl-7">
            <div class="card shadow-sm border-0 h-100" style="border-radius: 12px;">
                <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div class="d-flex align-items-center gap-2">
                        <span class="rounded-circle p-2 bg-warning bg-opacity-15 text-warning">
                            <i class="fa-solid fa-file-invoice-dollar"></i>
                        </span>
                        <div>
                            <h6 class="mb-0 fw-bold text-dark">Expense Authorizations Awaiting GM</h6>
                            <small class="text-muted">Cash disbursements, emergency project spend, and advances</small>
                        </div>
                    </div>
                    <a href="{{ route('expenses.index', ['tab' => 'pending_gm']) }}" class="btn btn-sm btn-outline-warning text-dark rounded-pill px-3">
                        View All ({{ $pendingGmExpenses->count() }})
                    </a>
                </div>
                <div class="card-body p-0">
                    @if($pendingGmExpenses->isEmpty())
                        <div class="text-center py-5">
                            <i class="fa-solid fa-check-circle fa-2x text-success mb-2"></i>
                            <h6 class="fw-bold text-dark mb-1">No Pending Expenses for GM</h6>
                            <p class="text-muted small mb-0">All expense requests have been reviewed.</p>
                        </div>
                    @else
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0" style="font-size: 0.85rem;">
                                <thead class="table-light">
                                    <tr>
                                        <th class="ps-3">Req #</th>
                                        <th>Project</th>
                                        <th>Category</th>
                                        <th>Requester</th>
                                        <th class="text-end">Amount (ETB)</th>
                                        <th class="text-end pe-3">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($pendingGmExpenses as $exp)
                                    <tr>
                                        <td class="ps-3 fw-bold text-dark">#{{ $exp->id }}</td>
                                        <td>{{ $exp->project->name ?? 'General' }}</td>
                                        <td><span class="badge bg-light text-dark border">{{ ucfirst($exp->category ?? 'Expense') }}</span></td>
                                        <td>{{ $exp->requester->name ?? 'Staff' }}</td>
                                        <td class="text-end fw-bold text-warning">{{ number_format($exp->amount, 2) }}</td>
                                        <td class="text-end pe-3">
                                            <a href="{{ route('expense-requests.show', $exp->id) }}" class="btn btn-sm btn-outline-warning text-dark rounded-pill px-2">
                                                Review
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

        {{-- Pending Employee Approvals --}}
        <div class="col-xl-5">
            <div class="card shadow-sm border-0 h-100" style="border-radius: 12px;">
                <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div class="d-flex align-items-center gap-2">
                        <span class="rounded-circle p-2 bg-primary bg-opacity-10 text-primary">
                            <i class="fa-solid fa-user-clock"></i>
                        </span>
                        <div>
                            <h6 class="mb-0 fw-bold text-dark">New Hires Awaiting GM Approval</h6>
                            <small class="text-muted">Strict GM approval requirement</small>
                        </div>
                    </div>
                    <a href="{{ route('employees.pending-approval') }}" class="btn btn-sm btn-outline-primary rounded-pill px-3">
                        View All ({{ $pendingEmployees->count() }})
                    </a>
                </div>
                <div class="card-body p-0">
                    @if($pendingEmployees->isEmpty())
                        <div class="text-center py-5">
                            <i class="fa-solid fa-user-check fa-2x text-success mb-2"></i>
                            <h6 class="fw-bold text-dark mb-1">All Employees Approved</h6>
                            <p class="text-muted small mb-0">No onboarding approvals pending.</p>
                        </div>
                    @else
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0" style="font-size: 0.85rem;">
                                <thead class="table-light">
                                    <tr>
                                        <th class="ps-3">Employee</th>
                                        <th>Department</th>
                                        <th class="text-end pe-3">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($pendingEmployees as $emp)
                                    <tr>
                                        <td class="ps-3">
                                            <div class="fw-semibold text-dark">{{ $emp->full_name }}</div>
                                            <small class="text-muted">{{ $emp->employee_code }}</small>
                                        </td>
                                        <td><span class="badge bg-light text-dark border">{{ $emp->department ?? 'General' }}</span></td>
                                        <td class="text-end pe-3">
                                            <form action="{{ route('employees.approve', $emp) }}" method="POST" class="d-inline">
                                                @csrf @method('PUT')
                                                <button type="submit" class="btn btn-sm btn-success rounded-pill px-2 shadow-sm" onclick="return confirm('Approve employee {{ $emp->full_name }}?')">
                                                    <i class="fas fa-check"></i>
                                                </button>
                                            </form>
                                            <a href="{{ route('employees.show', $emp) }}" class="btn btn-sm btn-outline-secondary rounded-pill px-2 ms-1">
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

    {{-- ═══ CHARTS & ANALYTICS ROW ═══════════════════════════════════════════════ --}}
    <div class="row g-3 mb-4">
        <!-- Project Status Chart -->
        <div class="col-xl-3">
            <div class="card shadow-sm border-0 h-100" style="border-radius: 12px;">
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
            <div class="card shadow-sm border-0 h-100" style="border-radius: 12px;">
                <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center">
                    <h6 class="mb-0 fw-bold"><i class="fas fa-chart-bar me-2 text-warning"></i>Monthly Expense &amp; Material Trend <small class="text-muted fw-normal">(Last 6 Mo.)</small></h6>
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
            <div class="card shadow-sm border-0 h-100" style="border-radius: 12px;">
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

    {{-- ═══ PROJECT EXPENSES TRACKER (CASH + MATERIAL) ═══════════════════════════ --}}
    <div id="project-expenses" class="row g-3 mb-4">
        <div class="col-12">
            <div class="card shadow-sm border-0" style="border-radius: 12px;">
                <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <h6 class="mb-0 fw-bold"><i class="fas fa-chart-pie me-2 text-danger"></i>Project Expenses Tracker
                        <span class="badge bg-success bg-opacity-10 text-success ms-2 fw-normal" style="font-size:0.75rem;"><i class="fas fa-boxes-packing me-1"></i>Cash + Daily Material Consumption (Latest Price)</span>
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
                                    <th class="text-end" title="Calculated live from daily material consumptions logged by store keepers, priced at latest product/market prices">
                                        <i class="fas fa-cubes me-1 text-success"></i>Material Cost
                                        <span class="d-block text-muted" style="font-size:0.65rem; font-weight:normal;">Daily Usage @ Latest Price</span>
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
                                        @if($pe['material_cost'] > 0)
                                            <a href="{{ route('material-usages.index', ['project_id' => $pe['id']]) }}" class="text-success fw-bold text-decoration-none" title="View Daily Material Consumptions logged for {{ $pe['name'] }}">
                                                {{ number_format($pe['material_cost'], 0) }} <i class="fas fa-arrow-up-right-from-square ms-1" style="font-size:0.65rem;"></i>
                                            </a>
                                        @else
                                            <span class="text-muted">0</span>
                                        @endif
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

    {{-- ═══ MATERIAL CONSUMPTION REPORT ══════════════════════════════════════════ --}}
    <div class="row g-3 mb-4">
        <div class="col-12">
            <div class="card shadow-sm border-0" style="border-radius: 12px;">
                <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <h6 class="mb-0 fw-bold"><i class="fas fa-boxes me-2 text-success"></i>Material Consumption Report
                        <span class="badge bg-success bg-opacity-10 text-success ms-2 fw-normal" style="font-size:0.75rem;"><i class="fas fa-boxes-packing me-1"></i>Daily Consumption @ Latest Price</span>
                    </h6>
                    <div class="d-flex gap-2 align-items-center">
                        <small class="text-muted">Top 20 by cost · Store daily consumption logs</small>
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
                                    <th class="text-end">Latest Price (ETB)</th>
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
