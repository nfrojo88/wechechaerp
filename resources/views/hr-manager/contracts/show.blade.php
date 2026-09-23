@extends('layouts.app')
@section('title', 'Contract ' . $contract->contract_number)

@section('content')
<div class="container-fluid py-4">
    {{-- Top Navigation & Action Buttons --}}
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2 pb-2 border-bottom">
        <div>
            <div class="d-flex align-items-center gap-2">
                <a href="{{ route('contracts.index') }}" class="btn btn-outline-secondary btn-sm rounded-pill px-3 shadow-xs">
                    <i class="fa-solid fa-arrow-left me-1"></i>All Contracts
                </a>
                <span class="badge bg-light text-dark border font-monospace px-3 py-1 rounded-pill">
                    {{ $contract->contract_number }}
                </span>
                @php
                    $statusBadge = match($contract->status) {
                        'active'            => ['class' => 'bg-success text-white', 'icon' => 'fa-circle-check', 'label' => 'Active'],
                        'approved'          => ['class' => 'bg-info text-white', 'icon' => 'fa-check-double', 'label' => 'Approved'],
                        'pending_approval'  => ['class' => 'bg-warning text-dark', 'icon' => 'fa-clock', 'label' => 'Pending Approval'],
                        'draft'             => ['class' => 'bg-secondary text-white', 'icon' => 'fa-pencil', 'label' => 'Draft'],
                        'expired'           => ['class' => 'bg-danger text-white', 'icon' => 'fa-triangle-exclamation', 'label' => 'Expired'],
                        'terminated'        => ['class' => 'bg-dark text-white', 'icon' => 'fa-ban', 'label' => 'Terminated'],
                        default             => ['class' => 'bg-light text-dark border', 'icon' => 'fa-circle', 'label' => ucfirst($contract->status)],
                    };
                @endphp
                <span class="badge {{ $statusBadge['class'] }} rounded-pill px-3 py-1">
                    <i class="fa-solid {{ $statusBadge['icon'] }} me-1"></i>{{ $statusBadge['label'] }}
                </span>
            </div>
            <h1 class="h3 mb-0 text-dark fw-bold mt-2">
                {{ $contract->employee->full_name ?? ($contract->employee->first_name . ' ' . $contract->employee->last_name) }}
            </h1>
            <p class="text-muted small mb-0">
                {{ $contract->contract_type }} Contract &bull; Started {{ $contract->start_date ? $contract->start_date->format('M d, Y') : '—' }}
            </p>
        </div>
        <div class="d-flex gap-2 flex-wrap align-items-center">
            @if($contract->status === 'draft')
                <form action="{{ route('contracts.submit', $contract) }}" method="POST" class="d-inline" onsubmit="return confirm('Submit this contract for management approval?');">
                    @csrf
                    <button type="submit" class="btn btn-success btn-sm rounded-pill px-3 shadow-xs">
                        <i class="fa-solid fa-paper-plane me-1"></i>Submit for Approval
                    </button>
                </form>
            @endif

            @if($contract->contract_file)
                <a href="{{ asset($contract->contract_file) }}" target="_blank" class="btn btn-outline-primary btn-sm rounded-pill px-3 shadow-xs">
                    <i class="fa-solid fa-file-arrow-down me-1"></i>View Signed File
                </a>
            @endif

            <button type="button" class="btn btn-primary btn-sm rounded-pill px-3 shadow-xs" data-bs-toggle="modal" data-bs-target="#addMilestoneModal">
                <i class="fa-solid fa-flag-checkered me-1"></i>Add Milestone
            </button>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show rounded-4 border-0 shadow-sm mb-4" role="alert">
            <i class="fa-solid fa-circle-check me-2"></i>{{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <div class="row g-4">
        {{-- Left: Details & Terms --}}
        <div class="col-lg-8">
            {{-- Contract Key Terms Card --}}
            <div class="card border-0 shadow-sm rounded-4 mb-4">
                <div class="card-header bg-white border-0 py-3 px-4 d-flex justify-content-between align-items-center">
                    <h5 class="fw-bold text-dark mb-0"><i class="fa-solid fa-file-contract text-primary me-2"></i>Contract Summary</h5>
                    <span class="badge bg-light text-muted border">{{ $contract->contract_type }}</span>
                </div>
                <div class="card-body px-4 pb-4 pt-1">
                    <div class="row g-3">
                        <div class="col-sm-6">
                            <span class="text-uppercase text-secondary fw-bold small" style="font-size:0.75rem;">Contract Number</span>
                            <div class="fw-bold font-monospace text-dark fs-6">{{ $contract->contract_number }}</div>
                        </div>
                        <div class="col-sm-6">
                            <span class="text-uppercase text-secondary fw-bold small" style="font-size:0.75rem;">Project Assignment</span>
                            <div class="fw-bold text-dark fs-6">{{ $contract->project->name ?? 'Head Office / Non-Project' }}</div>
                        </div>
                        <div class="col-sm-6">
                            <span class="text-uppercase text-secondary fw-bold small" style="font-size:0.75rem;">Start Date</span>
                            <div class="fw-bold text-dark">{{ $contract->start_date ? $contract->start_date->format('M d, Y') : '—' }}</div>
                        </div>
                        <div class="col-sm-6">
                            <span class="text-uppercase text-secondary fw-bold small" style="font-size:0.75rem;">End Date</span>
                            <div class="fw-bold text-dark">
                                @if($contract->isProjectBased())
                                    <span class="badge bg-info bg-opacity-25 text-info-emphasis border border-info border-opacity-25">Until Project Completion</span>
                                @elseif($contract->end_date)
                                    {{ $contract->end_date->format('M d, Y') }}
                                    @if($contract->end_date->isPast())
                                        <span class="badge bg-danger ms-1">Expired</span>
                                    @else
                                        <small class="text-muted ms-1">({{ now()->diffInDays($contract->end_date, false) }} days left)</small>
                                    @endif
                                @else
                                    Permanent / Open-Ended
                                @endif
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <span class="text-uppercase text-secondary fw-bold small" style="font-size:0.75rem;">Monthly Basic Salary</span>
                            <div class="fw-bold fs-5 text-success font-monospace">{{ number_format($contract->salary, 2) }} ETB</div>
                        </div>
                        <div class="col-sm-6">
                            <span class="text-uppercase text-secondary fw-bold small" style="font-size:0.75rem;">Allowances &amp; Benefits</span>
                            <div class="fw-bold fs-5 text-dark font-monospace">{{ number_format($contract->benefits_amount ?? 0, 2) }} ETB</div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Contract Terms & Description --}}
            <div class="card border-0 shadow-sm rounded-4 mb-4">
                <div class="card-header bg-white border-0 py-3 px-4">
                    <h5 class="fw-bold text-dark mb-0"><i class="fa-solid fa-file-lines text-primary me-2"></i>Terms &amp; Job Duties</h5>
                </div>
                <div class="card-body px-4 pb-4 pt-1">
                    <div class="p-3 bg-light rounded-3 mb-3">
                        <h6 class="fw-bold text-dark small text-uppercase mb-2">Standard Clauses:</h6>
                        <div class="text-dark small" style="white-space: pre-line;">{{ $contract->terms }}</div>
                    </div>

                    @if($contract->special_terms)
                        <div class="p-3 bg-warning bg-opacity-10 border border-warning border-opacity-25 rounded-3">
                            <h6 class="fw-bold text-dark small text-uppercase mb-1"><i class="fa-solid fa-star text-warning me-1"></i>Special Terms:</h6>
                            <div class="text-dark small" style="white-space: pre-line;">{{ $contract->special_terms }}</div>
                        </div>
                    @endif
                </div>
            </div>

            {{-- Milestones & Deliverables --}}
            <div class="card border-0 shadow-sm rounded-4 mb-4">
                <div class="card-header bg-white border-0 py-3 px-4 d-flex justify-content-between align-items-center">
                    <h5 class="fw-bold text-dark mb-0"><i class="fa-solid fa-flag-checkered text-primary me-2"></i>Milestones &amp; Deliverables</h5>
                    <button type="button" class="btn btn-outline-primary btn-sm rounded-pill" data-bs-toggle="modal" data-bs-target="#addMilestoneModal">
                        <i class="fa-solid fa-plus me-1"></i>Add
                    </button>
                </div>
                <div class="card-body p-0">
                    @if($contract->milestones->isNotEmpty())
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0" style="font-size:0.88rem;">
                                <thead class="table-light">
                                    <tr>
                                        <th class="ps-4">Milestone</th>
                                        <th>Target Date</th>
                                        <th>Description</th>
                                        <th class="pe-4 text-end">Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($contract->milestones as $ms)
                                        <tr>
                                            <td class="ps-4 fw-bold text-dark">{{ $ms->milestone_name }}</td>
                                            <td>{{ $ms->milestone_date ? \Carbon\Carbon::parse($ms->milestone_date)->format('M d, Y') : '—' }}</td>
                                            <td class="text-muted small">{{ $ms->description ?: '—' }}</td>
                                            <td class="pe-4 text-end">
                                                <span class="badge bg-{{ $ms->status === 'completed' ? 'success' : 'secondary' }} rounded-pill">
                                                    {{ ucfirst($ms->status) }}
                                                </span>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="p-4 text-center text-muted small">No contract milestones recorded.</div>
                    @endif
                </div>
            </div>
        </div>

        {{-- Right: Employee Profile & Approvals --}}
        <div class="col-lg-4">
            {{-- Employee Card --}}
            <div class="card border-0 shadow-sm rounded-4 mb-4">
                <div class="card-header bg-white border-0 py-3 px-4">
                    <h5 class="fw-bold text-dark mb-0"><i class="fa-solid fa-id-card text-primary me-2"></i>Employee Profile</h5>
                </div>
                <div class="card-body px-4 pb-4 pt-1">
                    <div class="text-center mb-3">
                        <div class="rounded-circle bg-primary bg-opacity-10 text-primary d-inline-flex align-items-center justify-content-center mb-2" style="width:64px;height:64px;">
                            <i class="fa-solid fa-user-tie fs-2"></i>
                        </div>
                        <h5 class="fw-bold text-dark mb-0">{{ $contract->employee->full_name ?? ($contract->employee->first_name . ' ' . $contract->employee->last_name) }}</h5>
                        <small class="text-muted">{{ $contract->employee->designation ?? 'Staff Member' }}</small>
                    </div>
                    <ul class="list-group list-group-flush small">
                        <li class="list-group-item d-flex justify-content-between px-0">
                            <span class="text-muted">Employee Code:</span>
                            <span class="font-monospace fw-bold">{{ $contract->employee->employee_code ?: ($contract->employee->code ?: '—') }}</span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between px-0">
                            <span class="text-muted">Department:</span>
                            <span class="fw-semibold">{{ $contract->employee->department->name ?? ($contract->employee->department ?: 'General') }}</span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between px-0">
                            <span class="text-muted">Email:</span>
                            <span>{{ $contract->employee->email ?: '—' }}</span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between px-0">
                            <span class="text-muted">Phone:</span>
                            <span>{{ $contract->employee->phone ?: '—' }}</span>
                        </li>
                    </ul>
                </div>
            </div>

            {{-- Approvals Workflow Timeline --}}
            <div class="card border-0 shadow-sm rounded-4 mb-4">
                <div class="card-header bg-white border-0 py-3 px-4">
                    <h5 class="fw-bold text-dark mb-0"><i class="fa-solid fa-network-wired text-primary me-2"></i>Approval Workflow</h5>
                </div>
                <div class="card-body px-4 pb-4 pt-1">
                    @if($contract->approvals->isNotEmpty())
                        <div class="timeline">
                            @foreach($contract->approvals as $appr)
                                <div class="p-3 border rounded-3 bg-light mb-2">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <span class="fw-bold text-dark small">Level {{ $appr->approval_level }}: {{ $appr->approver->name ?? 'Approver' }}</span>
                                        <span class="badge bg-{{ $appr->status === 'approved' ? 'success' : ($appr->status === 'rejected' ? 'danger' : 'warning text-dark') }} rounded-pill">
                                            {{ ucfirst($appr->status) }}
                                        </span>
                                    </div>
                                    @if($appr->comments)
                                        <div class="text-muted small mt-1 fst-italic">"{{ $appr->comments }}"</div>
                                    @endif
                                    @if($appr->responded_at)
                                        <div class="text-muted" style="font-size:0.72rem;">{{ $appr->responded_at->format('M d, Y H:i') }}</div>
                                    @endif

                                    {{-- If logged in user is this approver and pending --}}
                                    @if($appr->status === 'pending' && auth()->id() == $appr->approver_id)
                                        <div class="d-flex gap-2 mt-2">
                                            <form action="{{ route('contracts.approve', $appr) }}" method="POST" class="flex-fill">
                                                @csrf
                                                <button type="submit" class="btn btn-success btn-sm w-100 rounded-pill">
                                                    <i class="fa-solid fa-check me-1"></i>Approve
                                                </button>
                                            </form>
                                            <button type="button" class="btn btn-outline-danger btn-sm rounded-pill px-3" data-bs-toggle="modal" data-bs-target="#rejectModal{{ $appr->id }}">
                                                <i class="fa-solid fa-xmark"></i>
                                            </button>
                                        </div>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="text-muted small p-2 text-center">
                            @if($contract->status === 'draft')
                                Contract is in draft. Submit to start approval workflow.
                            @else
                                No approval stages recorded.
                            @endif
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

{{-- MODAL: Add Milestone --}}
<div class="modal fade" id="addMilestoneModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 rounded-4 shadow">
            <div class="modal-header bg-primary text-white py-3 px-4 rounded-top-4">
                <h5 class="modal-title fw-bold"><i class="fa-solid fa-flag-checkered me-2"></i>Add Contract Milestone</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="{{ route('contracts.milestone', $contract) }}" method="POST">
                @csrf
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label fw-bold small text-uppercase text-secondary">Milestone Title <span class="text-danger">*</span></label>
                        <input type="text" name="milestone_name" class="form-control rounded-3" required placeholder="e.g. End of 3-Month Probation, Structural Phase Handover">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold small text-uppercase text-secondary">Target Date <span class="text-danger">*</span></label>
                        <input type="date" name="milestone_date" class="form-control rounded-3" required value="{{ now()->addMonths(3)->format('Y-m-d') }}">
                    </div>
                    <div class="mb-2">
                        <label class="form-label fw-bold small text-uppercase text-secondary">Description / Performance Criteria</label>
                        <textarea name="description" class="form-control rounded-3" rows="3" placeholder="Performance review criteria or deliverables..."></textarea>
                    </div>
                </div>
                <div class="modal-footer bg-light border-0 py-3 px-4 rounded-bottom-4">
                    <button type="button" class="btn btn-secondary rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary fw-bold rounded-pill px-4">Save Milestone</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
