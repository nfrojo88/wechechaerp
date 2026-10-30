@extends('layouts.app')
@section('title', 'GM Executive Dashboard')
@section('content')
<div class="wc-container py-3 py-md-4">
    {{-- Top Executive Header --}}
    @php
        $gmPendingMaintApprovals = 0;
        try {
            $gmPendingMaintApprovals = \App\Models\ExpenseRequest::where(function($q) {
                $q->whereNotNull('maintenance_request_id')->orWhere('category', 'Maintenance');
            })->whereIn('status', [\App\Models\ExpenseRequest::STATUS_PENDING_GM, 'Pending (GM Review)', 'pending_gm'])->count()
            + \App\Models\MaterialRequest::where(function($q) {
                $q->whereNotNull('maintenance_request_id')->orWhere('source', 'like', 'Maintenance%');
            })->whereIn('status', ['pending_gm', 'pending', 'pending_approval'])->count();
        } catch (\Throwable $e) {}
    @endphp

    <x-page-header
        title="General Manager Executive Dashboard"
        subtitle="Executive management, strategic decisions, financial oversight &amp; approvals"
        badge="Executive Management"
    >
        <x-slot name="actions">
            <div class="d-flex gap-2 flex-wrap align-items-center">
                <span class="badge bg-light text-muted border px-2 py-1 rounded-pill d-none d-sm-inline-flex align-items-center" style="font-size:0.75rem;">
                    <i class="fa-regular fa-clock me-1"></i>{{ now()->format('l, F j, Y') }}
                </span>
                <a href="{{ route('gm.maintenance-approvals.index') }}" class="btn btn-outline-warning btn-sm rounded-pill px-3 shadow-xs position-relative" style="min-height: 38px; display: inline-flex; align-items: center;">
                    <i class="fa-solid fa-wrench me-1"></i>Maintenance Approvals
                    @if(($gmPendingMaintApprovals ?? 0) > 0)
                        <span class="badge bg-warning text-dark rounded-pill ms-1">{{ $gmPendingMaintApprovals }}</span>
                    @endif
                </a>
                <a href="{{ route('procurement.my-queue', ['status' => 'pending_gm']) }}" class="btn btn-outline-danger btn-sm rounded-pill px-3 shadow-xs position-relative" style="min-height: 38px; display: inline-flex; align-items: center;">
                    <i class="fa-solid fa-cart-arrow-down me-1"></i>PR Decisions
                    @if(($kpi['pending_gm_prs'] ?? 0) > 0)
                        <span class="badge bg-danger rounded-pill ms-1">{{ $kpi['pending_gm_prs'] }}</span>
                    @endif
                </a>
                <a href="{{ route('expenses.index', ['tab' => 'pending_gm']) }}" class="btn btn-outline-warning btn-sm rounded-pill px-3 shadow-xs position-relative" style="min-height: 38px; display: inline-flex; align-items: center;">
                    <i class="fa-solid fa-file-invoice-dollar me-1"></i>Expense Approvals
                    @if(($kpi['pending_gm_expenses'] ?? 0) > 0)
                        <span class="badge bg-warning text-dark rounded-pill ms-1">{{ $kpi['pending_gm_expenses'] }}</span>
                    @endif
                </a>
                <a href="{{ route('employees.pending-approval') }}" class="btn btn-outline-primary btn-sm rounded-pill px-3 shadow-xs" style="min-height: 38px; display: inline-flex; align-items: center;">
                    <i class="fa-solid fa-user-check me-1"></i>Employees
                    @if(($kpi['pending_approvals'] ?? 0) > 0)
                        <span class="badge bg-primary rounded-pill ms-1">{{ $kpi['pending_approvals'] }}</span>
                    @endif
                </a>
                <a href="{{ route('finance.payroll.gm') }}" class="btn btn-outline-info btn-sm rounded-pill px-3 shadow-xs" style="min-height: 38px; display: inline-flex; align-items: center;">
                    <i class="fa-solid fa-file-signature me-1"></i>Payroll
                </a>
                <a href="{{ route('gm.hr-reports') }}" class="btn btn-primary btn-sm rounded-pill px-3 shadow-xs" style="min-height: 38px; display: inline-flex; align-items: center;">
                    <i class="fa-solid fa-file-waveform me-1"></i>HR Reports
                </a>
                <a href="#vat-withholding-report" class="btn btn-outline-danger btn-sm rounded-pill px-3 shadow-xs" style="min-height: 38px; display: inline-flex; align-items: center;">
                    <i class="fa-solid fa-receipt me-1"></i>VAT / WHT
                </a>
            </div>
        </x-slot>
    </x-page-header>

    @if(($gmPendingMaintApprovals ?? 0) > 0)
        <div class="alert alert-warning border-0 shadow-sm rounded-3 mb-4 p-3 d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div class="d-flex align-items-center gap-3">
                <div class="rounded-circle bg-warning bg-opacity-25 p-2 text-warning-emphasis d-flex align-items-center justify-content-center flex-shrink-0" style="width:42px;height:42px;">
                    <i class="fa-solid fa-wrench fs-5"></i>
                </div>
                <div>
                    <h6 class="fw-bold mb-0 text-dark">Pending Maintenance Approvals (Ask Money &amp; Ask Material)</h6>
                    <small class="text-secondary">You have <strong>{{ $gmPendingMaintApprovals }}</strong> repair funding/material request(s) awaiting your decision before passing to Finance or Store.</small>
                </div>
            </div>
            <a href="{{ route('gm.maintenance-approvals.index') }}" class="btn btn-sm btn-warning text-dark fw-bold rounded-pill px-4 shadow-xs" style="min-height: 38px; display: inline-flex; align-items: center;">
                <i class="fa-solid fa-gavel me-1"></i>Review Approvals
            </a>
        </div>
    @endif

    {{-- ═══ EXECUTIVE KPI CARDS ════════════════════════════════════════════════ --}}
    <div class="row g-3 mb-4">
        {{-- Card 1: PR Decisions Pending GM --}}
        <div class="col-12 col-sm-6 col-xl-4">
            <x-kpi-card
                title="Procurement GM Decisions"
                :value="$kpi['pending_gm_prs'] ?? 0"
                :subtitle="(($kpi['pending_gm_prs'] ?? 0) > 0) ? 'Est. Value: ' . number_format($kpi['pending_gm_prs_amount'] ?? 0, 2) . ' ETB' : 'All caught up'"
                icon="fa-solid fa-cart-arrow-down"
                tone="danger"
            />
        </div>

        {{-- Card 2: Pending Expenses & Financial Authorizations --}}
        <div class="col-12 col-sm-6 col-xl-4">
            <x-kpi-card
                title="Expense Authorizations"
                :value="$kpi['pending_gm_expenses'] ?? 0"
                :subtitle="'Total: ' . number_format($kpi['pending_gm_expenses_amount'] ?? 0, 2) . ' ETB'"
                icon="fa-solid fa-file-invoice-dollar"
                tone="warning"
            />
        </div>

        {{-- Card 3: Active Projects & Total Portfolio Value --}}
        <div class="col-12 col-sm-6 col-xl-4">
            <x-kpi-card
                title="Projects Portfolio"
                :value="($kpi['active_projects'] ?? 0) . ' Active'"
                :subtitle="'Contract: ' . number_format($kpi['total_contract_value'] ?? 0, 0) . ' ETB'"
                icon="fa-solid fa-building"
                tone="primary"
            />
        </div>

        {{-- Card 4: Budget Utilization --}}
        <div class="col-12 col-sm-6 col-xl-4">
            <x-kpi-card
                title="Budget Utilization"
                :value="($kpi['budget_utilization'] ?? 0) . '%'"
                :subtitle="(($kpi['budget_utilization'] ?? 0) > 85 ? 'High Usage' : 'Healthy Usage')"
                icon="fa-solid fa-percent"
                tone="info"
            />
        </div>

        {{-- Card 5: Workforce & Pending Approvals --}}
        <div class="col-12 col-sm-6 col-xl-4">
            <x-kpi-card
                title="Workforce & Approvals"
                :value="($kpi['total_employees'] ?? 0) . ' Active Staff'"
                :subtitle="'Pending Hires: ' . ($kpi['pending_approvals'] ?? 0)"
                icon="fa-solid fa-user-clock"
                tone="purple"
            />
        </div>

        {{-- Card 6: Material Consumption This Month --}}
        <div class="col-12 col-sm-6 col-xl-4">
            <x-kpi-card
                title="Material Consumption (This Mo.)"
                :value="number_format($kpi['material_cost_this_month'] ?? 0, 0) . ' ETB'"
                subtitle="Top materials across all sites"
                icon="fa-solid fa-boxes-packing"
                tone="success"
            />
        </div>
    </div>

    {{-- ═══ UNASSIGNED ROLE ESCALATIONS ALERT (OVERSIGHT) ════════════ --}}
    @if(isset($unassignedStagePrs) && $unassignedStagePrs->isNotEmpty())
    <div class="card border-0 shadow-sm mb-4 bg-white border-start border-4 border-warning">
        <div class="card-body p-3">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-2">
                <div class="d-flex align-items-center gap-2">
                    <span class="badge bg-warning text-dark p-2 rounded-circle"><i class="fa-solid fa-triangle-exclamation"></i></span>
                    <div>
                        <h6 class="mb-0 fw-bold text-dark">Unassigned Stage Escalations Alert ({{ $unassignedStagePrs->count() }})</h6>
                        <small class="text-muted">These purchase requests are routed to Global Admin / GM because no team member is assigned to the stage's role.</small>
                    </div>
                </div>
                <a href="{{ route('procurement.my-queue') }}" class="btn btn-sm btn-outline-warning text-dark rounded-pill px-3" style="min-height: 36px; display: inline-flex; align-items: center;">Manage Sourcing</a>
            </div>
            <div class="table-responsive bg-white rounded-3 border wc-table-mobile-cards">
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
                            <td data-label="PR Number" class="ps-3 fw-bold text-primary">{{ $upr->pr_number }}</td>
                            <td data-label="Project">{{ $upr->project->name ?? 'N/A' }}</td>
                            <td data-label="Current Stage">
                                <span class="badge bg-warning text-dark rounded-pill px-2 py-1">
                                    {{ ucfirst(str_replace('_', ' ', $upr->status)) }}
                                </span>
                            </td>
                            <td data-label="Requested By">{{ $upr->requestedBy->name ?? 'System' }}</td>
                            <td data-label="Date">{{ $upr->created_at->format('M d, Y') }}</td>
                            <td data-label="Action" class="text-end pe-3">
                                <a href="{{ route('purchase-requests.show', $upr->id) }}" class="btn btn-sm btn-primary rounded-pill px-3" style="min-height: 34px; display: inline-flex; align-items: center;">
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

    {{-- ═══ PROCUREMENT GM DECISION HUB (CRITICAL OPERATION) ═════════ --}}
    <div id="gm-procurement-hub" class="card shadow-sm border-0 mb-4 bg-white">
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
                <a href="{{ route('procurement.my-queue', ['status' => 'pending_gm']) }}" class="btn btn-sm btn-danger rounded-pill px-3" style="min-height: 36px; display: inline-flex; align-items: center;">
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
                <div class="table-responsive wc-table-mobile-cards">
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
                                <td data-label="PR Code" class="ps-3">
                                    <div class="fw-bold text-dark">{{ $pr->pr_number }}</div>
                                    <small class="text-muted">{{ $pr->created_at->format('M d, Y') }}</small>
                                </td>
                                <td data-label="Project">
                                    <div class="fw-semibold">{{ $pr->project->name ?? 'General Store' }}</div>
                                    <small class="badge bg-light text-dark border">{{ $pr->project->code ?? 'N/A' }}</small>
                                </td>
                                <td data-label="Sourcing Channel">
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
                                <td data-label="Proposed Supplier">
                                    @if($pr->is_direct_buy)
                                        <span class="fw-semibold text-dark">{{ $pr->supplier->name ?? 'Direct Vendor' }}</span>
                                    @elseif($selectedProforma)
                                        <span class="fw-semibold text-dark">{{ $selectedProforma->supplier_name }}</span>
                                        <small class="text-muted d-block">{{ $selectedProforma->supplier_phone ?? '' }}</small>
                                    @else
                                        <span class="text-muted fst-italic">Pending vendor selection</span>
                                    @endif
                                </td>
                                <td data-label="Estimated Total" class="text-end fw-bold text-dark font-tabular">
                                    {{ number_format((float)$amount, 2) }} <small class="text-muted fw-normal">ETB</small>
                                </td>
                                <td data-label="Priority" class="text-center">
                                    <span class="badge rounded-pill
                                        @if($pr->priority == 'urgent') bg-danger
                                        @elseif($pr->priority == 'high') bg-warning text-dark
                                        @elseif($pr->priority == 'medium') bg-info text-dark
                                        @else bg-secondary @endif">
                                        {{ ucfirst($pr->priority) }}
                                    </span>
                                </td>
                                <td data-label="Action" class="text-end pe-3">
                                    <a href="{{ route('purchase-requests.show', $pr->id) }}" class="btn btn-sm btn-danger rounded-pill px-3 fw-semibold shadow-xs" style="min-height: 36px; display: inline-flex; align-items: center;">
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

    {{-- ═══ TWO-COLUMN ACTIONABLE QUEUES: EXPENSES & EMPLOYEES ═══════════════════ --}}
    <div class="row g-4 mb-4">
        {{-- Pending Expenses for GM Authorization --}}
        <div class="col-xl-7">
            <div class="card shadow-sm border-0 h-100 bg-white">
                <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div class="d-flex align-items-center gap-2">
                        <span class="rounded-circle p-2 bg-warning bg-opacity-10 text-warning">
                            <i class="fa-solid fa-file-invoice-dollar"></i>
                        </span>
                        <div>
                            <h6 class="mb-0 fw-bold text-dark">Expense Authorizations Awaiting GM</h6>
                            <small class="text-muted">Cash disbursements awaiting final executive clearance</small>
                        </div>
                    </div>
                    <a href="{{ route('expenses.index', ['tab' => 'pending_gm']) }}" class="btn btn-sm btn-outline-warning text-dark rounded-pill px-3" style="min-height: 34px; display: inline-flex; align-items: center;">
                        View All ({{ $pendingExpenses->count() }})
                    </a>
                </div>
                <div class="card-body p-0">
                    @if($pendingExpenses->isEmpty())
                        <div class="text-center py-5">
                            <i class="fa-solid fa-circle-check fa-2x text-success mb-2"></i>
                            <h6 class="fw-bold text-dark mb-1">No Pending Expenses for GM</h6>
                            <p class="text-muted small mb-0">All expense requests have been authorized or reviewed.</p>
                        </div>
                    @else
                        <div class="table-responsive wc-table-mobile-cards">
                            <table class="table table-hover align-middle mb-0" style="font-size: 0.85rem;">
                                <thead class="table-light">
                                    <tr>
                                        <th class="ps-3">Req #</th>
                                        <th>Project</th>
                                        <th>Category</th>
                                        <th class="text-end">Amount (ETB)</th>
                                        <th class="text-end pe-3">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($pendingExpenses as $exp)
                                    <tr>
                                        <td data-label="Req #" class="ps-3 fw-bold text-primary">#{{ $exp->request_number ?? $exp->id }}</td>
                                        <td data-label="Project">
                                            <span class="fw-semibold">{{ $exp->project->name ?? 'General' }}</span>
                                            <small class="text-muted d-block">{{ $exp->created_at->format('M d, Y') }}</small>
                                        </td>
                                        <td data-label="Category"><span class="badge bg-light text-dark border">{{ $exp->category ?? 'General' }}</span></td>
                                        <td data-label="Amount" class="text-end fw-bold font-tabular text-success">{{ number_format($exp->amount, 2) }}</td>
                                        <td data-label="Action" class="text-end pe-3">
                                            <a href="{{ route('expenses.index', ['tab' => 'pending_gm', 'search' => $exp->request_number ?? $exp->id]) }}" class="btn btn-sm btn-warning text-dark rounded-pill px-3 fw-semibold shadow-xs" style="min-height: 34px; display: inline-flex; align-items: center;">
                                                <i class="fa-solid fa-user-shield me-1"></i>Review
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
            <div class="card shadow-sm border-0 h-100 bg-white">
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
                    <a href="{{ route('employees.pending-approval') }}" class="btn btn-sm btn-outline-primary rounded-pill px-3" style="min-height: 34px; display: inline-flex; align-items: center;">
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
                        <div class="table-responsive wc-table-mobile-cards">
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
                                        <td data-label="Employee" class="ps-3">
                                            <div class="fw-semibold text-dark">{{ $emp->full_name }}</div>
                                            <small class="text-muted font-monospace">{{ $emp->employee_code }}</small>
                                        </td>
                                        <td data-label="Department"><span class="badge bg-light text-dark border">{{ $emp->department ?? 'General' }}</span></td>
                                        <td data-label="Action" class="text-end pe-3">
                                            <div class="d-inline-flex gap-1">
                                                <form action="{{ route('employees.approve', $emp) }}" method="POST" class="d-inline">
                                                    @csrf @method('PUT')
                                                    <button type="submit" class="btn btn-sm btn-success rounded-pill px-2 shadow-xs" style="min-width: 36px; min-height: 36px; display: inline-flex; align-items: center; justify-content: center;" title="Approve Employee" onclick="return confirm('Approve employee {{ $emp->full_name }}?')">
                                                        <i class="fas fa-check"></i>
                                                    </button>
                                                </form>
                                                <a href="{{ route('employees.show', $emp) }}" class="btn btn-sm btn-outline-secondary rounded-pill px-2" style="min-width: 36px; min-height: 36px; display: inline-flex; align-items: center; justify-content: center;" title="View Profile">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                            </div>
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

    {{-- ═══ VAT & WITHHOLDING TAX COMPLIANCE REPORT ═══════════════════════════════ --}}
    @include('dashboard.partials.tax_compliance_report')

    {{-- ═══ CHARTS & ANALYTICS ROW ═══════════════════════════════════════════════ --}}
    <div class="row g-3 mb-4">
        <!-- Project Status Chart -->
        <div class="col-xl-3 col-md-6">
            <div class="card shadow-sm border-0 h-100 bg-white">
                <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center">
                    <h6 class="mb-0 fw-bold"><i class="fas fa-chart-pie me-2 text-primary"></i>Project Status</h6>
                    <span class="badge bg-primary-subtle text-primary rounded-pill px-2" style="font-size:0.72rem;">{{ $projectStatus->sum('total') }} Total</span>
                </div>
                <div class="card-body p-3 d-flex flex-column justify-content-between">
                    <div style="position:relative; height:170px; width:100%;">
                        <canvas id="statusDonutChart"></canvas>
                    </div>
                    <div class="mt-3 pt-2 border-top">
                        @foreach($projectStatus as $ps)
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <span class="badge rounded-pill
                                @if($ps->status == 'active') bg-success
                                @elseif($ps->status == 'completed') bg-primary
                                @elseif($ps->status == 'cancelled') bg-danger
                                @elseif($ps->status == 'planning') bg-info text-dark
                                @else bg-secondary @endif">
                                {{ ucfirst($ps->status) }}
                            </span>
                            <strong class="text-dark">{{ $ps->total }}</strong>
                        </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>

        <!-- Monthly Expense Trend Chart -->
        <div class="col-xl-5 col-md-6">
            <div class="card shadow-sm border-0 h-100 bg-white">
                <div class="card-header bg-white border-bottom py-2 px-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div>
                        <h6 class="mb-0 fw-bold"><i class="fas fa-chart-bar me-2 text-warning"></i>Monthly Expense &amp; Material Trend</h6>
                        <small class="text-muted" style="font-size:0.73rem;">Cash Disbursed vs Daily Material Consumption (Last 6 Mo.)</small>
                    </div>
                    <div class="btn-group btn-group-sm" role="group" aria-label="Trend display mode">
                        <button type="button" class="btn btn-xs btn-outline-secondary active py-0 px-2" id="gmTrendStackedBtn" title="Stacked Bars" style="font-size:0.73rem; min-height: 28px;">
                            <i class="fa-solid fa-layer-group me-1"></i>Stacked
                        </button>
                        <button type="button" class="btn btn-xs btn-outline-secondary py-0 px-2" id="gmTrendGroupedBtn" title="Side-by-side Bars" style="font-size:0.73rem; min-height: 28px;">
                            <i class="fa-solid fa-chart-column me-1"></i>Grouped
                        </button>
                    </div>
                </div>
                <div class="card-body p-3 d-flex flex-column justify-content-between">
                    <div style="position:relative; height:220px; width:100%;">
                        <canvas id="expenseTrendChart"></canvas>
                    </div>
                    <div class="d-flex justify-content-around align-items-center pt-2 mt-2 border-top text-center" style="font-size:0.75rem;">
                        <div>
                            <span class="text-muted d-block" style="font-size:0.68rem;"><i class="fa-solid fa-square text-warning me-1"></i>6-Mo Cash</span>
                            <strong class="text-warning">ETB {{ number_format(collect($monthlyExpenseTrend)->sum('cash'), 0) }}</strong>
                        </div>
                        <div class="border-start ps-3">
                            <span class="text-muted d-block" style="font-size:0.68rem;"><i class="fa-solid fa-square text-success me-1"></i>6-Mo Material</span>
                            <strong class="text-success">ETB {{ number_format(collect($monthlyExpenseTrend)->sum('material'), 0) }}</strong>
                        </div>
                        <div class="border-start ps-3">
                            <span class="text-muted d-block" style="font-size:0.68rem;"><i class="fa-solid fa-calculator text-primary me-1"></i>Combined Total</span>
                            <strong class="text-primary">ETB {{ number_format(collect($monthlyExpenseTrend)->sum('combined'), 0) }}</strong>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Expense by Category (Interactive Donut + Month Chooser) -->
        <div class="col-xl-4 col-md-12">
            <div class="card shadow-sm border-0 h-100 bg-white">
                <div class="card-header bg-white border-bottom py-2 px-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <h6 class="mb-0 fw-bold"><i class="fas fa-tags me-2 text-info"></i>Expense by Category</h6>
                    <div style="min-width: 130px;">
                        <select id="expenseCategoryPeriodSelect" class="form-select form-select-sm py-1 px-2 border-secondary-subtle rounded-pill shadow-xs" style="font-size:0.75rem; font-weight:600;">
                            <option value="this_month" selected>This Month</option>
                            <option value="last_month">Last Month</option>
                            <option value="this_quarter">This Quarter</option>
                            <option value="this_year">This Year</option>
                            <option value="all">All Time</option>
                        </select>
                    </div>
                </div>
                <div class="card-body p-3 d-flex flex-column justify-content-between">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="text-muted small" style="font-size:0.75rem;" id="categoryPeriodTitle">
                            Showing: <strong class="text-dark">This Month</strong>
                        </span>
                        <span class="badge bg-info-subtle text-info border border-info-subtle rounded-pill px-2 py-1" id="categoryPeriodTotalBadge" style="font-size:0.75rem;">
                            Total: 0 ETB
                        </span>
                    </div>

                    <!-- Donut Canvas -->
                    <div id="categoryChartWrapper" style="position:relative; height:130px; width:100%;" class="mb-2">
                        <canvas id="expenseCategoryChart"></canvas>
                    </div>

                    <!-- Empty State for Periods with No Expenses -->
                    <div id="categoryEmptyState" class="text-center py-4 d-none">
                        <i class="fas fa-tag fa-2x text-muted mb-2"></i>
                        <p class="text-muted small mb-0">No approved cash expenses recorded for this period.</p>
                    </div>

                    <!-- Category Breakdown Progress List -->
                    <div id="categoryListContainer" style="max-height: 180px; overflow-y: auto; padding-right: 4px;">
                        <!-- Dynamically filled by JS -->
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ═══ PROJECT EXPENSES TRACKER ════════════════════════════════════════════ --}}
    <div id="project-expenses" class="row g-3 mb-4">
        <div class="col-12">
            <div class="card shadow-sm border-0 bg-white">
                <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <h6 class="mb-0 fw-bold"><i class="fas fa-chart-pie me-2 text-danger"></i>Project Expenses Tracker
                        <span class="badge bg-danger bg-opacity-10 text-danger ms-2 fw-normal" style="font-size:0.75rem;">Comprehensive Cash &amp; Material Breakdown</span>
                    </h6>
                    <a href="{{ route('projects.index') }}" class="btn btn-sm btn-outline-primary rounded-pill px-3" style="min-height: 36px; display: inline-flex; align-items: center;">
                        <i class="fas fa-external-link-alt me-1"></i>All Projects
                    </a>
                </div>
                <div class="card-body p-0">
                    @if($projectExpenses->isEmpty())
                        <div class="text-center text-muted py-5">
                            <i class="fas fa-chart-bar fa-3x mb-3 text-muted"></i>
                            <p>No project expense data available yet.</p>
                        </div>
                    @else
                    <div class="table-responsive wc-table-mobile-cards">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-3">Code</th>
                                    <th>Project</th>
                                    <th>Status</th>
                                    <th class="text-end">Contract Value (ETB)</th>
                                    <th class="text-end">Budget (ETB)</th>
                                    <th class="text-end text-warning">Cash Disbursed (ETB)</th>
                                    <th class="text-end text-success">Material Used (ETB)</th>
                                    <th class="text-end fw-bold">Total Expense (ETB)</th>
                                    <th class="text-end">Remaining (ETB)</th>
                                    <th>Utilization</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($projectExpenses as $pe)
                                <tr>
                                    <td data-label="Code" class="ps-3"><span class="badge bg-light text-dark border">{{ $pe['code'] }}</span></td>
                                    <td data-label="Project">
                                        <a href="{{ route('projects.show', $pe['id']) }}" class="fw-semibold text-dark text-decoration-none">
                                            {{ $pe['name'] }}
                                        </a>
                                    </td>
                                    <td data-label="Status">
                                        <span class="badge rounded-pill
                                            @if($pe['status'] == 'active') bg-success
                                            @elseif($pe['status'] == 'completed') bg-primary
                                            @elseif($pe['status'] == 'cancelled') bg-danger
                                            @elseif($pe['status'] == 'planning') bg-info text-dark
                                            @else bg-secondary @endif">
                                            {{ ucfirst($pe['status']) }}
                                        </span>
                                    </td>
                                    <td data-label="Contract Value" class="text-end font-tabular">{{ number_format($pe['contract_value'], 0) }}</td>
                                    <td data-label="Budget" class="text-end text-muted font-tabular">
                                        @if($pe['budget_allocated'] > 0)
                                            {{ number_format($pe['budget_allocated'], 0) }}
                                        @else
                                            <span class="text-muted fst-italic">Not set</span>
                                        @endif
                                    </td>
                                    <td data-label="Cash Disbursed" class="text-end font-tabular">
                                        <span class="text-warning fw-semibold">{{ number_format($pe['cash_expenses'], 0) }}</span>
                                    </td>
                                    <td data-label="Material Used" class="text-end font-tabular">
                                        @if($pe['material_cost'] > 0)
                                            <a href="{{ route('material-usages.index', ['project_id' => $pe['id']]) }}" class="text-success fw-bold text-decoration-none" title="View Daily Material Consumptions logged for {{ $pe['name'] }}">
                                                {{ number_format($pe['material_cost'], 0) }} <i class="fas fa-arrow-up-right-from-square ms-1" style="font-size:0.65rem;"></i>
                                            </a>
                                        @else
                                            <span class="text-muted">0</span>
                                        @endif
                                    </td>
                                    <td data-label="Total Expense" class="text-end fw-bold text-dark font-tabular">
                                        {{ number_format($pe['total_expense'], 0) }} <small class="text-muted fw-normal">ETB</small>
                                    </td>
                                    <td data-label="Remaining Budget" class="text-end font-tabular">
                                        @if($pe['budget_allocated'] > 0)
                                            <span class="fw-semibold text-{{ $pe['budget_status'] }}">
                                                {{ number_format($pe['remaining_budget'], 0) }}
                                            </span>
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                    </td>
                                    <td data-label="Utilization" style="min-width:120px">
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
                                    <td data-label="Action">
                                        <a href="{{ route('projects.show', $pe['id']) }}" class="btn btn-sm btn-outline-primary rounded-pill px-2" style="min-width: 34px; min-height: 34px; display: inline-flex; align-items: center; justify-content: center;" title="View Details">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                            <tfoot class="table-light">
                                <tr class="fw-bold font-tabular">
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
            <div class="card shadow-sm border-0 bg-white">
                <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <h6 class="mb-0 fw-bold"><i class="fas fa-boxes me-2 text-success"></i>Material Consumption Report
                        <span class="badge bg-success bg-opacity-10 text-success ms-2 fw-normal" style="font-size:0.75rem;"><i class="fas fa-boxes-packing me-1"></i>Daily Consumption @ Latest Price</span>
                    </h6>
                    <div class="d-flex gap-2 align-items-center">
                        <small class="text-muted d-none d-sm-inline">Top 20 by cost · Store daily consumption logs</small>
                        <a href="{{ route('material-usages.index') }}" class="btn btn-sm btn-outline-success rounded-pill px-3" style="min-height: 36px; display: inline-flex; align-items: center;">
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
                    <div class="table-responsive wc-table-mobile-cards">
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
                                    <td data-label="#" class="ps-3 text-muted">{{ $idx + 1 }}</td>
                                    <td data-label="Material">
                                        <div class="fw-semibold">{{ $row->product_name }}</div>
                                    </td>
                                    <td data-label="Project">
                                        <span class="badge bg-light text-dark border">{{ $row->project_code }}</span>
                                        <small class="text-muted d-block" style="font-size:0.75rem">{{ Str::limit($row->project_name, 30) }}</small>
                                    </td>
                                    <td data-label="Total Qty" class="text-end fw-semibold font-tabular">{{ number_format($row->total_qty, 2) }}</td>
                                    <td data-label="Unit" class="text-end text-muted">{{ $row->product_unit }}</td>
                                    <td data-label="Latest Price" class="text-end font-tabular">
                                        @if($row->avg_unit_cost > 0)
                                            {{ number_format($row->avg_unit_cost, 2) }}
                                        @else
                                            <span class="text-muted fst-italic small">—</span>
                                        @endif
                                    </td>
                                    <td data-label="Total Cost" class="text-end fw-bold text-dark font-tabular">
                                        @if($row->total_cost > 0)
                                            {{ number_format($row->total_cost, 0) }}
                                        @else
                                            <span class="text-muted fst-italic small">No price</span>
                                        @endif
                                    </td>
                                    <td data-label="Cost Share" style="min-width:120px">
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
                                <tr class="fw-bold font-tabular">
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

<script>
(function() {
    // ── Direct Data from Controller (No HTML attribute parsing issues) ────────
    var rawStatusLabels = @json($projectStatus->pluck('status')->map(fn($s) => ucfirst($s))->values());
    var rawStatusTotals = @json($projectStatus->pluck('total')->map(fn($t) => (int)$t)->values());

    var statusLabels = Array.isArray(rawStatusLabels) ? rawStatusLabels : Object.values(rawStatusLabels || {});
    var statusTotals = Array.isArray(rawStatusTotals) ? rawStatusTotals : Object.values(rawStatusTotals || {});

    var rawTrendLabels   = @json(collect($monthlyExpenseTrend)->pluck('label')->values());
    var rawTrendCash     = @json(collect($monthlyExpenseTrend)->pluck('cash')->values());
    var rawTrendMaterial = @json(collect($monthlyExpenseTrend)->pluck('material')->values());

    var trendLabels   = Array.isArray(rawTrendLabels) ? rawTrendLabels : Object.values(rawTrendLabels || {});
    var trendCash     = Array.isArray(rawTrendCash) ? rawTrendCash : Object.values(rawTrendCash || {});
    var trendMaterial = Array.isArray(rawTrendMaterial) ? rawTrendMaterial : Object.values(rawTrendMaterial || {});

    var categoryDataByPeriod = @json($categoryDataByPeriod ?? []);

    var donutChartInstance = null;
    var trendChartInstance = null;
    var categoryChartInstance = null;
    var isTrendStacked = true;

    function ensureChart(callback) {
        if (typeof Chart !== 'undefined') {
            callback();
            return;
        }
        var checkCount = 0;
        var timer = setInterval(function() {
            checkCount++;
            if (typeof Chart !== 'undefined') {
                clearInterval(timer);
                callback();
            } else if (checkCount > 40) {
                clearInterval(timer);
                // Fallback load if needed
                var s = document.createElement('script');
                s.src = 'https://cdn.jsdelivr.net/npm/chart.js';
                s.onload = callback;
                document.head.appendChild(s);
            }
        }, 100);
    }

    // ── 1. Project Status Donut Chart ──────────────────────────────────────────
    function renderStatusChart() {
        var canvas = document.getElementById("statusDonutChart");
        if (!canvas || statusLabels.length === 0) return;

        if (donutChartInstance) {
            donutChartInstance.destroy();
        }

        var colorMap = {
            'Active': '#10b981',
            'Completed': '#3b82f6',
            'Cancelled': '#ef4444',
            'Planning': '#0ea5e9',
            'On_hold': '#f59e0b',
            'Pending': '#6366f1'
        };

        var bgColors = statusLabels.map(function(lbl, idx) {
            return colorMap[lbl] || ['#4e73df', '#1cc88a', '#36b9cc', '#f6c23e', '#e74a3b'][idx % 5];
        });

        donutChartInstance = new Chart(canvas, {
            type: 'doughnut',
            data: {
                labels: statusLabels,
                datasets: [{
                    data: statusTotals,
                    backgroundColor: bgColors,
                    borderWidth: 2,
                    borderColor: '#ffffff',
                    hoverOffset: 4
                }]
            },
            options: {
                maintainAspectRatio: false,
                responsive: true,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: { boxWidth: 10, boxHeight: 10, usePointStyle: true, font: { size: 10, weight: '600' }, padding: 8 }
                    },
                    tooltip: {
                        callbacks: {
                            label: function(ctx) {
                                return ' ' + ctx.label + ': ' + ctx.parsed + ' projects';
                            }
                        }
                    }
                },
                cutout: '68%'
            }
        });
    }

    // ── 2. Monthly Expense & Material Trend Chart ───────────────────────────────
    function renderTrendChart() {
        var canvas = document.getElementById("expenseTrendChart");
        if (!canvas || trendLabels.length === 0) return;

        if (trendChartInstance) {
            trendChartInstance.destroy();
        }

        trendChartInstance = new Chart(canvas, {
            type: 'bar',
            data: {
                labels: trendLabels,
                datasets: [
                    {
                        label: 'Cash Expenses',
                        data: trendCash,
                        backgroundColor: 'rgba(245, 158, 11, 0.85)',
                        borderColor: '#f59e0b',
                        borderWidth: 1,
                        borderRadius: 5,
                        maxBarThickness: 38
                    },
                    {
                        label: 'Material Consumption',
                        data: trendMaterial,
                        backgroundColor: 'rgba(16, 185, 129, 0.85)',
                        borderColor: '#10b981',
                        borderWidth: 1,
                        borderRadius: 5,
                        maxBarThickness: 38
                    }
                ]
            },
            options: {
                maintainAspectRatio: false,
                responsive: true,
                animation: { duration: 650, easing: 'easeOutQuart' },
                plugins: {
                    legend: {
                        position: 'top',
                        align: 'end',
                        labels: { boxWidth: 10, boxHeight: 10, usePointStyle: true, font: { size: 11, weight: '600' }, padding: 10 }
                    },
                    tooltip: {
                        backgroundColor: 'rgba(15, 23, 42, 0.92)',
                        padding: 10,
                        cornerRadius: 8,
                        callbacks: {
                            label: function(ctx) {
                                var val = Number(ctx.parsed.y || 0).toLocaleString('en-US', { minimumFractionDigits: 0, maximumFractionDigits: 2 });
                                return ' ' + ctx.dataset.label + ': ' + val + ' ETB';
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        stacked: isTrendStacked,
                        grid: { display: false },
                        ticks: { font: { size: 11, weight: '500' }, color: '#64748b' }
                    },
                    y: {
                        stacked: isTrendStacked,
                        beginAtZero: true,
                        grid: { color: 'rgba(226, 232, 240, 0.65)' },
                        ticks: {
                            font: { size: 10 },
                            color: '#94a3b8',
                            callback: function(val) {
                                if (val >= 1000000) return (val/1000000).toFixed(1) + 'M';
                                if (val >= 1000) return (val/1000).toFixed(0) + 'K';
                                return Number(val).toLocaleString();
                            }
                        }
                    }
                }
            }
        });
    }

    // ── 3. Expense by Category (Interactive Month Chooser) ──────────────────────
    function updateCategoryView(periodKey) {
        var periodData = categoryDataByPeriod[periodKey];

        // Fallback to 'all' if selected period data is missing
        if (!periodData) {
            periodData = categoryDataByPeriod['all'] || { grand_total: 0, categories: [], labels: [], totals: [], colors: [] };
        }

        var titleEl = document.getElementById("categoryPeriodTitle");
        var badgeEl = document.getElementById("categoryPeriodTotalBadge");
        var chartWrapper = document.getElementById("categoryChartWrapper");
        var emptyState = document.getElementById("categoryEmptyState");
        var listContainer = document.getElementById("categoryListContainer");

        var selectEl = document.getElementById("expenseCategoryPeriodSelect");
        var selectedText = selectEl ? selectEl.options[selectEl.selectedIndex].text : 'Selected Period';

        if (titleEl) {
            titleEl.innerHTML = 'Showing: <strong class="text-dark">' + selectedText + '</strong>';
        }

        var formattedTotal = Number(periodData.grand_total || 0).toLocaleString('en-US', { minimumFractionDigits: 0, maximumFractionDigits: 2 });
        if (badgeEl) {
            badgeEl.innerText = 'Total: ' + formattedTotal + ' ETB';
        }

        var categories = periodData.categories || [];

        if (categories.length === 0 || periodData.grand_total <= 0) {
            if (chartWrapper) chartWrapper.classList.add("d-none");
            if (listContainer) listContainer.classList.add("d-none");
            if (emptyState) emptyState.classList.remove("d-none");
            if (categoryChartInstance) {
                categoryChartInstance.destroy();
                categoryChartInstance = null;
            }
            return;
        }

        if (chartWrapper) chartWrapper.classList.remove("d-none");
        if (listContainer) listContainer.classList.remove("d-none");
        if (emptyState) emptyState.classList.add("d-none");

        // Render / Update Donut Chart
        var canvas = document.getElementById("expenseCategoryChart");
        if (canvas) {
            if (categoryChartInstance) {
                categoryChartInstance.destroy();
            }

            categoryChartInstance = new Chart(canvas, {
                type: 'doughnut',
                data: {
                    labels: periodData.labels || [],
                    datasets: [{
                        data: periodData.totals || [],
                        backgroundColor: periodData.colors || [],
                        borderWidth: 2,
                        borderColor: '#ffffff',
                        hoverOffset: 4
                    }]
                },
                options: {
                    maintainAspectRatio: false,
                    responsive: true,
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            callbacks: {
                                label: function(ctx) {
                                    var val = Number(ctx.parsed || 0).toLocaleString('en-US', { minimumFractionDigits: 0 });
                                    var pct = periodData.grand_total > 0 ? ((ctx.parsed / periodData.grand_total) * 100).toFixed(1) : 0;
                                    return ' ' + ctx.label + ': ' + val + ' ETB (' + pct + '%)';
                                }
                            }
                        }
                    },
                    cutout: '65%'
                }
            });
        }

        // Render Category Progress List
        if (listContainer) {
            var html = '<ul class="list-group list-group-flush">';
            categories.forEach(function(cat) {
                var amt = Number(cat.total).toLocaleString('en-US', { minimumFractionDigits: 0, maximumFractionDigits: 2 });
                html += '<li class="list-group-item border-0 py-2 px-1 bg-transparent">';
                html += '  <div class="d-flex justify-content-between align-items-center mb-1">';
                html += '    <span class="fw-semibold text-dark text-truncate" style="font-size:0.82rem;">';
                html += '      <i class="fa-solid fa-circle me-1" style="color:' + cat.color + '; font-size:0.6rem;"></i>' + cat.category;
                html += '    </span>';
                html += '    <span class="text-dark small fw-bold" style="font-size:0.8rem;">' + amt + ' ETB</span>';
                html += '  </div>';
                html += '  <div class="progress" style="height:6px; border-radius:3px; background-color:#e2e8f0;">';
                html += '    <div class="progress-bar rounded-pill" style="width:' + cat.pct + '%; background-color:' + cat.color + '"></div>';
                html += '  </div>';
                html += '  <div class="d-flex justify-content-between align-items-center text-muted mt-1" style="font-size:0.72rem;">';
                html += '    <span>' + cat.pct + '% share</span>';
                html += '    <span>' + cat.count + ' request' + (cat.count > 1 ? 's' : '') + '</span>';
                html += '  </div>';
                html += '</li>';
            });
            html += '</ul>';
            listContainer.innerHTML = html;
        }
    }

    function initAll() {
        ensureChart(function() {
            renderStatusChart();
            renderTrendChart();

            // Setup Trend Mode Buttons (Stacked vs Grouped)
            var btnStacked = document.getElementById("gmTrendStackedBtn");
            var btnGrouped = document.getElementById("gmTrendGroupedBtn");
            if (btnStacked && btnGrouped) {
                btnStacked.addEventListener("click", function() {
                    if (isTrendStacked) return;
                    isTrendStacked = true;
                    btnStacked.classList.add("active");
                    btnGrouped.classList.remove("active");
                    renderTrendChart();
                });
                btnGrouped.addEventListener("click", function() {
                    if (!isTrendStacked) return;
                    isTrendStacked = false;
                    btnGrouped.classList.add("active");
                    btnStacked.classList.remove("active");
                    renderTrendChart();
                });
            }

            // Setup Category Period Dropdown Selector
            var selectEl = document.getElementById("expenseCategoryPeriodSelect");
            if (selectEl) {
                // If this_month has no data, check if all-time has data and fallback gracefully
                var thisMonthData = categoryDataByPeriod['this_month'];
                var initialPeriod = (thisMonthData && thisMonthData.grand_total > 0) ? 'this_month' : 'all';
                selectEl.value = initialPeriod;
                updateCategoryView(initialPeriod);

                selectEl.addEventListener("change", function() {
                    updateCategoryView(this.value);
                });
            }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener("DOMContentLoaded", initAll);
    } else {
        initAll();
    }
    window.addEventListener("load", initAll);
})();
</script>
@endsection
