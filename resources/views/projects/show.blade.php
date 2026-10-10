@extends('layouts.app')

@section('title', 'Project Details')

@section('content')
<div class="d-flex align-items-center mb-4 flex-wrap gap-2">
    <a href="{{ route('projects.index') }}" class="btn btn-sm btn-outline-secondary me-2">
        <i class="fa-solid fa-arrow-left"></i>
    </a>
    <h1 class="h3 mb-0 me-2">{{ $project->name }}</h1>

    @php
        $mainBadgeColor = match($project->status) {
            'active'    => 'success',
            'planning'  => 'info',
            'bidding'   => 'warning',
            'on_hold'   => 'secondary',
            'completed' => 'primary',
            'cancelled' => 'danger',
            'handover'  => 'dark',
            default     => 'secondary',
        };
        $phaseStatus = $project->planning_phase_status ?? 'draft';
        $phaseInfo = match($phaseStatus) {
            'submitted'                  => ['label' => 'Submitted for Review', 'color' => '#6366f1'],
            'planning_manager_approved'  => ['label' => 'Plng. Mgr Approved', 'color' => '#0891b2'],
            'coordinator_approved'       => ['label' => 'Coordinator Approved', 'color' => '#0891b2'],
            'technical_manager_approved' => ['label' => 'Tech. Mgr Approved', 'color' => '#0891b2'],
            'gm_approved'               => ['label' => 'GM Approved ✔', 'color' => '#16a34a'],
            'rejected'                  => ['label' => 'Plan Rejected', 'color' => '#dc2626'],
            default                     => null,
        };
    @endphp

    <span class="badge bg-{{ $mainBadgeColor }} fs-6">{{ ucfirst(str_replace('_', ' ', $project->status)) }}</span>
    @if($phaseInfo)
        <span class="badge rounded-pill px-3 py-2"
              style="background:{{ $phaseInfo['color'] }};font-size:.8rem;">
            {{ $phaseInfo['label'] }}
        </span>
    @endif
    <code class="ms-1">{{ $project->code }}</code>

    <div class="ms-auto">
        @can('projects.edit')
        <a href="{{ route('projects.edit', $project) }}" class="btn btn-primary">
            <i class="fa-solid fa-pen me-1"></i> Edit Project
        </a>
        @endcan
    </div>
</div>

{{-- Auto-status notice when workflow is driving the status --}}
@if(in_array($phaseStatus, ['submitted','planning_manager_approved','coordinator_approved','technical_manager_approved']))
<div class="alert alert-primary border-0 shadow-sm mb-4 py-2 px-3 d-flex align-items-center gap-2">
    <i class="fa-solid fa-robot text-primary"></i>
    <span class="small">
        <strong>Auto-status active:</strong> Project status is automatically managed by the planning approval workflow.
        It will become <strong>Active</strong> once the GM approves and allocates the budget.
    </span>
</div>
@elseif($phaseStatus === 'gm_approved')
<div class="alert alert-success border-0 shadow-sm mb-4 py-2 px-3 d-flex align-items-center gap-2">
    <i class="fa-solid fa-circle-check text-success"></i>
    <span class="small">
        <strong>Workflow complete:</strong> The GM has approved the plan and allocated the budget.
        This project status was automatically set to <strong>Active</strong>.
    </span>
</div>
@elseif($phaseStatus === 'rejected')
<div class="alert alert-danger border-0 shadow-sm mb-4 py-2 px-3 d-flex align-items-center gap-2">
    <i class="fa-solid fa-circle-xmark text-danger"></i>
    <span class="small">
        <strong>Plan rejected:</strong> The planning workflow was rejected. The planning team can submit a new plan.
    </span>
</div>
@endif

<div class="alert alert-info border-0 shadow-sm mb-4 d-flex align-items-center justify-content-between flex-wrap gap-3">
    <div class="d-flex align-items-center gap-3">
        <i class="fa-solid fa-circle-info fa-2x text-info"></i>
        <div>
            <h6 class="mb-1 fw-bold text-info-dark">No ERP Plan Setup Yet</h6>
            <p class="mb-0 small text-muted">To view the interactive Gantt chart, task schedules, and budgets, convert a Quantity Takeoff Sheet into an ERP Plan.</p>
        </div>
    </div>
    <a href="{{ route('takeoff.index') }}" class="btn btn-info btn-sm text-white fw-bold">
        <i class="fa-solid fa-ruler-combined me-1"></i> Go to Quantity Takeoff
    </a>
</div>

<div class="row g-4">
    <div class="col-md-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-transparent py-3">
                <h5 class="mb-0">Project Information</h5>
            </div>
            <div class="card-body">
                <table class="table table-borderless table-sm mb-0">
                    <tr><td class="text-muted w-50">Client Name</td><td class="fw-semibold">{{ $project->client_name ?? '—' }}</td></tr>
                    <tr><td class="text-muted">Client Contact</td><td class="fw-semibold">{{ $project->client_contact ?? '—' }}</td></tr>
                    <tr><td class="text-muted">Location</td><td class="fw-semibold">{{ $project->location ?? '—' }}</td></tr>
                    <tr><td class="text-muted">Start Date</td><td class="fw-semibold">{{ $project->start_date?->format('d M Y') ?? '—' }}</td></tr>
                    <tr><td class="text-muted">End Date</td><td class="fw-semibold">{{ $project->end_date?->format('d M Y') ?? '—' }}</td></tr>
                    <tr><td class="text-muted">Created By</td><td class="fw-semibold">{{ $project->creator->name ?? '—' }}</td></tr>
                </table>
                @if($project->description)
                <div class="mt-3 pt-3 border-top">
                    <div class="text-muted small mb-1">Description</div>
                    <div>{{ $project->description }}</div>
                </div>
                @endif
            </div>
        </div>
    </div>
    
    <div class="col-md-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-transparent py-3 d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Financials &amp; Stores</h5>
                @can('plan_workflow.view')
                <a href="{{ route('plan-workflow.show', $project) }}" class="btn btn-sm btn-outline-primary">
                    <i class="fas fa-sitemap me-1"></i> Workflow
                </a>
                @endcan
            </div>
            <div class="card-body">
                <table class="table table-borderless table-sm mb-3">
                    <tr><td class="text-muted w-50">Contract Value</td><td class="fw-semibold">{{ number_format($project->contract_value, 2) }} ETB</td></tr>
                    <tr>
                        <td class="text-muted">Budget Allocated</td>
                        <td class="fw-semibold">
                            @can('budget.view')
                                {{ number_format($project->budget_allocated, 2) }} ETB
                            @else
                                <span class="text-muted">—</span>
                            @endcan
                        </td>
                    </tr>
                    <tr>
                        <td class="text-muted">Budget Consumed</td>
                        <td class="fw-semibold text-danger">
                            @can('budget.view')
                                {{ number_format($project->budget_consumed, 2) }} ETB
                            @else
                                <span class="text-muted">—</span>
                            @endcan
                        </td>
                    </tr>
                    @php $pca = $project->pettyCashAccount; @endphp
                    @if($pca)
                    <tr>
                        <td class="text-muted">Site Petty Cash</td>
                        <td class="fw-semibold">
                            <span class="badge bg-success-subtle text-success border border-success-subtle">
                                <i class="fa-solid fa-wallet me-1"></i>{{ $pca->name }} [{{ $pca->code }}]
                            </span>
                            <div class="small text-muted mt-1">Balance: Br {{ number_format($pca->current_balance, 2) }}</div>
                        </td>
                    </tr>
                    @endif
                </table>

                {{-- Budget Utilization Bar (visible only to authorized roles) --}}
                @can('budget.view')
                @if($project->hasBudgetAllocated())
                @php
                    $pct    = $project->budgetUtilizationPercent();
                    $bstatus = $project->budgetStatus();
                    $barColor = match($bstatus) {
                        'blocked' => '#dc2626',
                        'at_risk' => '#f97316',
                        default   => '#16a34a',
                    };
                @endphp
                <div class="mb-3">
                    <div class="d-flex justify-content-between mb-1" style="font-size:.8rem;">
                        <span class="text-muted">Budget Utilization</span>
                        <span class="fw-semibold" style="color:{{ $barColor }}">{{ $pct }}%</span>
                    </div>
                    <div style="height:10px;border-radius:8px;background:#f3f4f6;overflow:hidden;">
                        <div style="height:100%;width:{{ min($pct,100) }}%;background:{{ $barColor }};border-radius:8px;transition:width .4s;"></div>
                    </div>
                    <div class="d-flex justify-content-between mt-1" style="font-size:.75rem;color:#9ca3af;">
                        <span>{{ number_format($project->budgetRemaining(), 2) }} ETB remaining</span>
                        @if($bstatus === 'blocked')
                            <span class="text-danger fw-semibold">🔴 BLOCKED — GM Action Required</span>
                        @elseif($bstatus === 'at_risk')
                            <span class="text-warning fw-semibold">🟡 AT RISK (&gt;80%)</span>
                        @else
                            <span class="text-success">🟢 Safe</span>
                        @endif
                    </div>
                </div>
                @endif
                @endcan

                {{-- Workflow Status Badge --}}
                @can('plan_workflow.view')
                <div class="mb-3 p-3 rounded-2" style="background:#f8fafc;border:1px solid #e5e7eb;">
                    <div class="small text-muted mb-1 fw-semibold">Planning Workflow</div>
                    @php $wf = $project->activeWorkflow; @endphp
                    @if($wf)
                        <span class="badge rounded-pill px-3 {{ $wf->status === 'gm_approved' ? 'bg-success' : ($wf->status === 'rejected' ? 'bg-danger' : 'bg-primary') }}">
                            {{ \App\Models\ProjectPlanWorkflow::STATUS_LABELS[$wf->status] ?? $wf->status }}
                        </span>
                        <div class="small text-muted mt-1">{{ $wf->nextStepLabel() }}</div>
                    @else
                        <span class="badge bg-secondary rounded-pill px-3">Not Started</span>
                    @endif
                </div>
                @endcan

                <h6 class="mb-3">Associated Stores &amp; Site Petty Cash</h6>
                @if($project->stores->count() > 0)
                    <div class="list-group list-group-flush">
                    @foreach($project->stores as $store)
                        <div class="list-group-item px-0 border-0 d-flex justify-content-between align-items-center flex-wrap gap-2">
                            <div>
                                <a href="{{ route('stores.show', $store) }}" class="text-decoration-none fw-semibold">
                                    <i class="fas fa-warehouse text-muted me-2"></i> {{ $store->name }}
                                </a>
                                @if($project->default_store_id == $store->id)
                                    <span class="badge bg-secondary ms-1">Default Store</span>
                                @endif
                            </div>
                            @if($store->pettyCashAccount)
                                <span class="badge bg-success-subtle text-success border border-success-subtle" style="font-size: 0.75rem;">
                                    <i class="fa-solid fa-wallet me-1"></i>{{ $store->pettyCashAccount->name }} [{{ $store->pettyCashAccount->code }}]
                                </span>
                            @endif
                        </div>
                    @endforeach
                    </div>
                @else
                    <div class="text-muted small">No stores currently associated.</div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
