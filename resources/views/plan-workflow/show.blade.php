@extends('layouts.app')
@section('title', 'Planning Workflow — ' . $project->name)

@push('styles')
<style>
/* ── Workflow Timeline ───────────────────────────────────────────── */
.wf-timeline { position: relative; padding-left: 2.5rem; }
.wf-timeline::before {
    content: '';
    position: absolute;
    left: 1rem;
    top: 0;
    bottom: 0;
    width: 2px;
    background: #e5e7eb;
}
.wf-step { position: relative; margin-bottom: 1.75rem; }
.wf-step:last-child { margin-bottom: 0; }
.wf-dot {
    position: absolute;
    left: -2.15rem;
    top: 0.15rem;
    width: 2rem;
    height: 2rem;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: .8rem;
    border: 2px solid #e5e7eb;
    background: #fff;
    z-index: 1;
}
.wf-dot.done   { background: #dcfce7; border-color: #16a34a; color: #16a34a; }
.wf-dot.active { background: #dbeafe; border-color: #2563eb; color: #2563eb; }
.wf-dot.reject { background: #fee2e2; border-color: #dc2626; color: #dc2626; }
.wf-dot.pending{ background: #f9fafb; border-color: #d1d5db; color: #9ca3af; }

/* ── Budget Bar ─────────────────────────────────────────────────── */
.budget-bar-wrap { height: 12px; border-radius: 8px; background: #f3f4f6; overflow: hidden; }
.budget-bar-fill { height: 100%; border-radius: 8px; transition: width .5s ease; }
.budget-bar-fill.safe     { background: linear-gradient(90deg, #4ade80, #16a34a); }
.budget-bar-fill.at_risk  { background: linear-gradient(90deg, #fb923c, #ea580c); }
.budget-bar-fill.blocked  { background: linear-gradient(90deg, #f87171, #dc2626); }

/* ── Approval form card ─────────────────────────────────────────── */
.approve-card { border: 1px solid #e5e7eb; border-radius: 12px; overflow: hidden; }
.approve-card .card-header { background: #f8fafc; border-bottom: 1px solid #e5e7eb; }
</style>
@endpush

@section('content')
<div class="d-flex align-items-center gap-3 mb-4">
    <a href="{{ route('projects.show', $project) }}" class="btn btn-sm btn-outline-secondary">
        <i class="fas fa-arrow-left me-1"></i> Back to Project
    </a>
    <div>
        <h1 class="h4 mb-0 fw-bold">Planning Workflow</h1>
        <div class="text-muted small">{{ $project->name }} &bull; {{ $project->code }}</div>
    </div>
</div>

{{-- Alerts --}}
@if(session('success'))
<div class="alert alert-success alert-dismissible fade show mb-4" role="alert">
    <i class="fas fa-check-circle me-2"></i> {{ session('success') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
@endif

<div class="row g-4">

    {{-- ── LEFT: Timeline ──────────────────────────────────────────────── --}}
    <div class="col-lg-7">
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
                <span class="fw-semibold">Approval Progress</span>
                @if($workflow)
                    <span class="badge rounded-pill fs-7 px-3
                        {{ match($workflow->status) {
                            'gm_approved' => 'bg-success',
                            'rejected'    => 'bg-danger',
                            default       => 'bg-primary'
                        } }}">
                        {{ \App\Models\ProjectPlanWorkflow::STATUS_LABELS[$workflow->status] ?? $workflow->status }}
                    </span>
                @else
                    <span class="badge bg-secondary rounded-pill px-3">No Workflow Yet</span>
                @endif
            </div>
            <div class="card-body py-4">

                @if($workflow)
                {{-- Progress bar --}}
                <div class="mb-4">
                    <div class="d-flex justify-content-between small text-muted mb-1">
                        <span>Step {{ intdiv($workflow->progressPercent(), 20) }} of 5</span>
                        <span>{{ $workflow->progressPercent() }}% complete</span>
                    </div>
                    <div class="progress" style="height:8px;">
                        <div class="progress-bar bg-primary" style="width:{{ $workflow->progressPercent() }}%"></div>
                    </div>
                </div>

                <div class="wf-timeline">
                    {{-- Step 1: Submitted --}}
                    @php
                        $submitDone = in_array($workflow->status, ['planning_manager_approved','coordinator_approved','technical_manager_approved','gm_approved']);
                        $submitActive = $workflow->status === 'submitted';
                        $submitReject = $workflow->status === 'rejected' && $workflow->rejected_at_step === 'submitted';
                    @endphp
                    <div class="wf-step">
                        <div class="wf-dot {{ $submitDone ? 'done' : ($submitActive ? 'active' : ($submitReject ? 'reject' : 'pending')) }}">
                            <i class="fas {{ $submitDone ? 'fa-check' : ($submitActive ? 'fa-clock' : 'fa-circle') }}"></i>
                        </div>
                        <div class="ps-1">
                            <div class="fw-semibold small text-dark">1. Submitted by Planning Team</div>
                            @if($workflow->submitted_at)
                                <div class="text-muted" style="font-size:.78rem;">{{ $workflow->creator->name ?? '—' }} &bull; {{ $workflow->submitted_at->format('M d, Y H:i') }}</div>
                            @else
                                <div class="text-muted" style="font-size:.78rem;">Pending submission</div>
                            @endif
                        </div>
                    </div>

                    {{-- Step 2: Planning Manager --}}
                    @php
                        $pmDone = in_array($workflow->status, ['coordinator_approved','technical_manager_approved','gm_approved']);
                        $pmActive = $workflow->status === 'planning_manager_approved';
                        $pmReject = $workflow->status === 'rejected' && str_contains($workflow->rejected_at_step ?? '', 'submitted');
                    @endphp
                    <div class="wf-step">
                        <div class="wf-dot {{ $pmDone ? 'done' : ($workflow->planning_manager_id ? 'done' : ($submitDone || $submitActive ? 'active' : 'pending')) }}">
                            <i class="fas {{ $workflow->planning_manager_id ? 'fa-check' : 'fa-circle' }}"></i>
                        </div>
                        <div class="ps-1">
                            <div class="fw-semibold small text-dark">2. Planning Manager Review</div>
                            @if($workflow->planning_manager_id)
                                <div class="text-muted" style="font-size:.78rem;">
                                    ✅ {{ $workflow->planningManager->name ?? '—' }} &bull; {{ $workflow->planning_manager_at->format('M d, Y H:i') }}
                                    @if($workflow->planning_manager_note) <br><em class="text-secondary">{{ $workflow->planning_manager_note }}</em> @endif
                                </div>
                            @else
                                <div class="text-muted" style="font-size:.78rem;">Awaiting review</div>
                            @endif
                        </div>
                    </div>

                    {{-- Step 3: Coordinator --}}
                    <div class="wf-step">
                        <div class="wf-dot {{ $workflow->coordinator_id ? 'done' : 'pending' }}">
                            <i class="fas {{ $workflow->coordinator_id ? 'fa-check' : 'fa-circle' }}"></i>
                        </div>
                        <div class="ps-1">
                            <div class="fw-semibold small text-dark">3. Coordinator Review</div>
                            @if($workflow->coordinator_id)
                                <div class="text-muted" style="font-size:.78rem;">
                                    ✅ {{ $workflow->coordinator->name ?? '—' }} &bull; {{ $workflow->coordinator_at->format('M d, Y H:i') }}
                                    @if($workflow->coordinator_note) <br><em class="text-secondary">{{ $workflow->coordinator_note }}</em> @endif
                                </div>
                            @else
                                <div class="text-muted" style="font-size:.78rem;">Awaiting review</div>
                            @endif
                        </div>
                    </div>

                    {{-- Step 4: Technical Manager --}}
                    <div class="wf-step">
                        <div class="wf-dot {{ $workflow->tech_manager_id ? 'done' : 'pending' }}">
                            <i class="fas {{ $workflow->tech_manager_id ? 'fa-check' : 'fa-circle' }}"></i>
                        </div>
                        <div class="ps-1">
                            <div class="fw-semibold small text-dark">4. Technical Manager Review</div>
                            @if($workflow->tech_manager_id)
                                <div class="text-muted" style="font-size:.78rem;">
                                    ✅ {{ $workflow->techManager->name ?? '—' }} &bull; {{ $workflow->tech_manager_at->format('M d, Y H:i') }}
                                    @if($workflow->tech_manager_note) <br><em class="text-secondary">{{ $workflow->tech_manager_note }}</em> @endif
                                </div>
                            @else
                                <div class="text-muted" style="font-size:.78rem;">Awaiting review</div>
                            @endif
                        </div>
                    </div>

                    {{-- Step 5: GM --}}
                    <div class="wf-step">
                        <div class="wf-dot {{ $workflow->gm_id ? 'done' : 'pending' }}">
                            <i class="fas {{ $workflow->gm_id ? 'fa-check' : 'fa-circle' }}"></i>
                        </div>
                        <div class="ps-1">
                            <div class="fw-semibold small text-dark">5. GM Approval & Budget Allocation</div>
                            @if($workflow->gm_id)
                                <div class="text-muted" style="font-size:.78rem;">
                                    ✅ {{ $workflow->gm->name ?? '—' }} &bull; {{ $workflow->gm_at->format('M d, Y H:i') }}<br>
                                    Budget Allocated: <strong class="text-success">{{ number_format($workflow->budget_allocated, 2) }} ETB</strong>
                                    @if($workflow->gm_note) <br><em class="text-secondary">{{ $workflow->gm_note }}</em> @endif
                                </div>
                            @else
                                <div class="text-muted" style="font-size:.78rem;">Awaiting GM approval</div>
                            @endif
                        </div>
                    </div>

                    {{-- Rejection --}}
                    @if($workflow->status === 'rejected')
                    <div class="wf-step">
                        <div class="wf-dot reject"><i class="fas fa-times"></i></div>
                        <div class="ps-1">
                            <div class="fw-semibold small text-danger">Rejected</div>
                            <div class="text-muted" style="font-size:.78rem;">
                                By {{ $workflow->rejector->name ?? '—' }} &bull; {{ $workflow->rejected_at->format('M d, Y H:i') }}<br>
                                <em>{{ $workflow->rejection_reason }}</em>
                            </div>
                        </div>
                    </div>
                    @endif
                </div>

                @else
                <div class="text-center py-5 text-muted">
                    <i class="fas fa-sitemap fa-2x mb-3" style="opacity:.3;"></i>
                    <div class="fw-semibold">No workflow started yet</div>
                    <div class="small">The planning team must submit the plan to begin.</div>
                </div>
                @endif
            </div>
        </div>
    </div>

    {{-- ── RIGHT: Actions + Budget ─────────────────────────────────────── --}}
    <div class="col-lg-5">

        {{-- Budget Card --}}
        @can('budget.view')
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white py-3">
                <span class="fw-semibold"><i class="fas fa-wallet text-primary me-2"></i>Budget Utilization</span>
            </div>
            <div class="card-body">
                @if($project->hasBudgetAllocated())
                    @php
                        $pct    = $project->budgetUtilizationPercent();
                        $status = $project->budgetStatus();
                    @endphp
                    <div class="d-flex justify-content-between mb-1 small fw-semibold">
                        <span>{{ number_format($project->budget_consumed, 2) }} ETB spent</span>
                        <span class="{{ $status === 'blocked' ? 'text-danger' : ($status === 'at_risk' ? 'text-warning' : 'text-success') }}">
                            {{ $pct }}%
                        </span>
                    </div>
                    <div class="budget-bar-wrap mb-2">
                        <div class="budget-bar-fill {{ $status }}" style="width: {{ min($pct, 100) }}%"></div>
                    </div>
                    <div class="d-flex justify-content-between small text-muted">
                        <span>Total: {{ number_format($project->budget_allocated, 2) }} ETB</span>
                        <span>
                            @if($status === 'blocked')
                                <span class="badge bg-danger">🔴 Blocked</span>
                            @elseif($status === 'at_risk')
                                <span class="badge bg-warning text-dark">🟡 At Risk</span>
                            @else
                                <span class="badge bg-success">🟢 Safe</span>
                            @endif
                        </span>
                    </div>
                    <div class="text-muted small mt-2">Remaining: <strong>{{ number_format($project->budgetRemaining(), 2) }} ETB</strong></div>
                @else
                    <p class="text-muted small mb-0">No budget allocated yet. GM must approve the plan.</p>
                @endif
            </div>
        </div>
        @endcan

        {{-- ── Action Card ─────────────────────────────────────────────── --}}
        @if($workflow && $workflow->isActive())
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white py-3">
                <span class="fw-semibold"><i class="fas fa-gavel text-primary me-2"></i>Your Action</span>
            </div>
            <div class="card-body">
                <p class="text-muted small mb-3">
                    <strong>Next step:</strong> {{ $workflow->nextStepLabel() }}
                </p>

                {{-- Planning Manager Approve --}}
                @if($workflow->status === 'submitted')
                @can('plan_workflow.approve_planning')
                <form method="POST" action="{{ route('plan-workflow.approve-planning', $workflow) }}">
                    @csrf
                    <div class="mb-2">
                        <label class="form-label small fw-semibold">Review Note (optional)</label>
                        <textarea name="note" rows="2" class="form-control form-control-sm"></textarea>
                    </div>
                    <button class="btn btn-success btn-sm w-100 mb-2"><i class="fas fa-check me-1"></i> Approve as Planning Manager</button>
                </form>
                @endcan
                @endif

                {{-- Coordinator Approve --}}
                @if($workflow->status === 'planning_manager_approved')
                @can('plan_workflow.approve_coord')
                <form method="POST" action="{{ route('plan-workflow.approve-coordinator', $workflow) }}">
                    @csrf
                    <div class="mb-2">
                        <label class="form-label small fw-semibold">Review Note (optional)</label>
                        <textarea name="note" rows="2" class="form-control form-control-sm"></textarea>
                    </div>
                    <button class="btn btn-success btn-sm w-100 mb-2"><i class="fas fa-check me-1"></i> Approve as Coordinator</button>
                </form>
                @endcan
                @endif

                {{-- Technical Manager Approve --}}
                @if($workflow->status === 'coordinator_approved')
                @can('plan_workflow.approve_tech')
                <form method="POST" action="{{ route('plan-workflow.approve-technical', $workflow) }}">
                    @csrf
                    <div class="mb-2">
                        <label class="form-label small fw-semibold">Review Note (optional)</label>
                        <textarea name="note" rows="2" class="form-control form-control-sm"></textarea>
                    </div>
                    <button class="btn btn-success btn-sm w-100 mb-2"><i class="fas fa-check me-1"></i> Approve as Technical Manager</button>
                </form>
                @endcan
                @endif

                {{-- GM Approve + Budget --}}
                @if($workflow->status === 'technical_manager_approved')
                @can('plan_workflow.approve_gm')
                <form method="POST" action="{{ route('plan-workflow.approve-gm', $workflow) }}">
                    @csrf
                    <div class="mb-2">
                        <label class="form-label small fw-semibold">Budget to Allocate (ETB) <span class="text-danger">*</span></label>
                        <input type="number" name="budget_allocated" class="form-control form-control-sm @error('budget_allocated') is-invalid @enderror" min="1" step="0.01" required>
                        @error('budget_allocated')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">GM Note (optional)</label>
                        <textarea name="note" rows="2" class="form-control form-control-sm"></textarea>
                    </div>
                    <button class="btn btn-primary btn-sm w-100 mb-2"><i class="fas fa-check-double me-1"></i> Final Approve & Allocate Budget</button>
                </form>
                @endcan
                @endif

                {{-- Reject (any approver) --}}
                @can('plan_workflow.reject')
                <hr class="my-3">
                <form method="POST" action="{{ route('plan-workflow.reject', $workflow) }}">
                    @csrf
                    <div class="mb-2">
                        <label class="form-label small fw-semibold text-danger">Reason for Rejection <span class="text-danger">*</span></label>
                        <textarea name="rejection_reason" rows="2" class="form-control form-control-sm @error('rejection_reason') is-invalid @enderror" required></textarea>
                        @error('rejection_reason')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <button class="btn btn-outline-danger btn-sm w-100"><i class="fas fa-times me-1"></i> Reject Plan</button>
                </form>
                @endcan
            </div>
        </div>
        @endif

        {{-- Planning Team: Submit --}}
        @if(!$workflow || $workflow->status === 'rejected')
        @can('plan_workflow.submit')
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white py-3">
                <span class="fw-semibold"><i class="fas fa-paper-plane text-primary me-2"></i>Submit Plan for Review</span>
            </div>
            <div class="card-body">
                <p class="text-muted small">Submit the project plan to begin the approval process.</p>
                <form method="POST" action="{{ route('plan-workflow.submit', $project) }}">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Submission Notes (optional)</label>
                        <textarea name="notes" rows="2" class="form-control form-control-sm"></textarea>
                    </div>
                    <button class="btn btn-primary btn-sm w-100"><i class="fas fa-paper-plane me-1"></i>
                        {{ $workflow->status === 'rejected' ? 'Resubmit for Review' : 'Submit for Review' }}
                    </button>
                </form>
            </div>
        </div>
        @endcan
        @endif

        {{-- GM: Supplement Budget --}}
        @if($project->hasBudgetAllocated())
        @can('budget.allocate')
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white py-3">
                <span class="fw-semibold"><i class="fas fa-plus-circle text-success me-2"></i>Supplement Budget</span>
            </div>
            <div class="card-body">
                <form method="POST" action="{{ route('plan-workflow.supplement', $project) }}">
                    @csrf
                    <div class="mb-2">
                        <label class="form-label small fw-semibold">Additional Amount (ETB)</label>
                        <input type="number" name="amount" class="form-control form-control-sm" min="1" step="0.01" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Reason <span class="text-danger">*</span></label>
                        <textarea name="reason" rows="2" class="form-control form-control-sm" required></textarea>
                    </div>
                    <button class="btn btn-success btn-sm w-100"><i class="fas fa-plus me-1"></i> Add Budget</button>
                </form>
            </div>
        </div>
        @endcan
        @endif

    </div>
</div>
@endsection
