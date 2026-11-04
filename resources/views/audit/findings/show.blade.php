@extends('layouts.app')
@section('title', 'Audit Finding #' . $finding->finding_number)

@section('content')
<div class="wc-container py-3 py-md-4">

    <!-- Page Header -->
    <x-page-header
        title="Audit Observation #{{ $finding->finding_number }}"
        subtitle="Detailed audit trail, evidence files, assignee remediation response, and resolution clearance"
        badge="Audit Query #{{ $finding->finding_number }}"
    >
        <x-slot name="actions">
            <div class="d-flex gap-2 flex-wrap align-items-center">
                <a href="{{ route('audit.findings.index') }}" class="btn btn-outline-secondary btn-sm rounded-pill px-3 shadow-xs fw-semibold" style="min-height: 38px; display: inline-flex; align-items: center;">
                    <i class="fa-solid fa-arrow-left me-1"></i> Back to Findings Hub
                </a>
            </div>
        </x-slot>
    </x-page-header>

    @if($finding->is_escalated)
        <div class="alert alert-danger shadow-sm rounded-3 d-flex align-items-center gap-3 mb-4 p-3 border-danger">
            <div class="p-2 bg-danger text-white rounded-circle">
                <i class="fa-solid fa-bell fa-lg"></i>
            </div>
            <div>
                <strong class="d-block fs-6">🚨 OVERDUE ESCALATION TO GENERAL MANAGER</strong>
                <span>This audit finding remained unresolved past the due date ({{ $finding->due_date ? $finding->due_date->format('M d, Y') : 'N/A' }}) and has been automatically escalated to the General Manager.</span>
            </div>
        </div>
    @endif

    <div class="row g-4 mb-4">
        <!-- Finding Details (Left Column) -->
        <div class="col-lg-7">
            <div class="card border-0 shadow-sm rounded-3 bg-white mb-4">
                <div class="card-header bg-white border-bottom py-3 px-3 px-md-4 d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div>
                        <h6 class="fw-bold mb-0 text-dark">
                            <i class="fa-solid fa-clipboard-question text-primary me-2"></i>Observation Details
                        </h6>
                    </div>
                    <div class="d-flex gap-2 align-items-center">
                        @php
                            $sevBadge = match($finding->severity) {
                                'critical' => 'danger',
                                'high'     => 'warning',
                                'medium'   => 'info',
                                default    => 'secondary',
                            };
                        @endphp
                        <span class="badge bg-{{ $sevBadge }} text-uppercase font-monospace px-3 py-1.5 rounded-pill">
                            Severity: {{ $finding->severity }}
                        </span>
                        <span class="badge bg-light text-dark border font-monospace px-3 py-1.5 rounded-pill">
                            Status: {{ strtoupper($finding->status) }}
                        </span>
                    </div>
                </div>

                <div class="card-body p-4">
                    <h5 class="fw-bold text-dark mb-3">{{ $finding->title }}</h5>

                    <div class="bg-light p-3 rounded-3 border mb-4">
                        <label class="form-label text-muted small fw-bold text-uppercase mb-1">AUDIT OBSERVATION &amp; QUERY</label>
                        <p class="mb-0 text-dark" style="white-space: pre-line; line-height: 1.6;">{{ $finding->observation }}</p>
                    </div>

                    <div class="row g-3">
                        <div class="col-sm-6">
                            <div class="p-2.5 bg-light rounded border">
                                <small class="text-muted d-block font-monospace">SOURCE MODULE</small>
                                <strong class="text-dark">{{ strtoupper(str_replace('_', ' ', $finding->source_type)) }}</strong>
                                @if($finding->source_id)
                                    <span class="text-muted small">#{{ $finding->source_id }}</span>
                                @endif
                            </div>
                        </div>

                        <div class="col-sm-6">
                            <div class="p-2.5 bg-light rounded border">
                                <small class="text-muted d-block font-monospace">STORE / PROJECT</small>
                                <strong class="text-dark">{{ $finding->store?->name ?? 'General Store' }}</strong>
                                <small class="text-muted d-block">{{ $finding->project?->name ?? 'Site' }}</small>
                            </div>
                        </div>

                        <div class="col-sm-6">
                            <div class="p-2.5 bg-light rounded border">
                                <small class="text-muted d-block font-monospace">ASSIGNED TO</small>
                                <strong class="text-dark"><i class="fa-solid fa-user me-1 text-primary"></i>{{ $finding->assignedTo?->name ?? 'Unassigned' }}</strong>
                            </div>
                        </div>

                        <div class="col-sm-6">
                            <div class="p-2.5 bg-light rounded border">
                                <small class="text-muted d-block font-monospace">RESPONSE DUE DATE</small>
                                @if($finding->is_overdue)
                                    <strong class="text-danger"><i class="fa-solid fa-clock me-1"></i>{{ $finding->due_date ? $finding->due_date->format('M d, Y') : 'N/A' }} (OVERDUE)</strong>
                                @else
                                    <strong class="text-dark">{{ $finding->due_date ? $finding->due_date->format('M d, Y') : 'No deadline' }}</strong>
                                @endif
                            </div>
                        </div>

                        <div class="col-sm-6">
                            <div class="p-2.5 bg-light rounded border">
                                <small class="text-muted d-block font-monospace">ISSUED BY</small>
                                <strong class="text-dark">{{ $finding->createdBy?->name ?? 'Internal Auditor' }}</strong>
                                <small class="text-muted d-block">{{ $finding->created_at?->format('M d, Y H:i') }}</small>
                            </div>
                        </div>

                        @if($finding->attachment_url)
                            <div class="col-sm-6">
                                <div class="p-2.5 bg-light rounded border">
                                    <small class="text-muted d-block font-monospace">AUDITOR EVIDENCE ATTACHMENT</small>
                                    <a href="{{ $finding->attachment_url }}" target="_blank" class="btn btn-sm btn-outline-primary rounded-pill px-3 mt-1">
                                        <i class="fa-solid fa-paperclip me-1"></i> View Supporting File
                                    </a>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <!-- Workflow / Responses / Resolution (Right Column) -->
        <div class="col-lg-5">
            <!-- 1. Assignee Remediation Response -->
            <div class="card border-0 shadow-sm rounded-3 bg-white mb-4">
                <div class="card-header bg-white border-bottom py-3 px-3 px-md-4">
                    <h6 class="fw-bold mb-0 text-dark">
                        <i class="fa-solid fa-reply text-info me-2"></i>Assignee Response &amp; Clarification
                    </h6>
                </div>
                <div class="card-body p-4">
                    @if($finding->response_notes)
                        <div class="bg-info bg-opacity-10 p-3 rounded-3 border border-info-subtle mb-3">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <strong class="text-dark"><i class="fa-solid fa-user-check me-1 text-success"></i>{{ $finding->respondedBy?->name ?? 'Assignee' }}</strong>
                                <small class="text-muted">{{ $finding->responded_at?->format('M d, Y H:i') }}</small>
                            </div>
                            <p class="mb-0 text-dark" style="white-space: pre-line; line-height: 1.5;">{{ $finding->response_notes }}</p>
                        </div>

                        @if($finding->response_attachment_url)
                            <div class="mt-2">
                                <a href="{{ $finding->response_attachment_url }}" target="_blank" class="btn btn-outline-info btn-sm rounded-pill px-3">
                                    <i class="fa-solid fa-file me-1"></i> View Attached Response File
                                </a>
                            </div>
                        @endif
                    @else
                        <div class="alert alert-warning py-2.5 px-3 small mb-3">
                            <i class="fa-solid fa-clock me-1"></i> Awaiting response from assigned staff (<strong>{{ $finding->assignedTo?->name ?? 'Staff' }}</strong>).
                        </div>

                        @php
                            $canRespond = (Auth::id() == $finding->assigned_to) || Auth::user()->hasAnyRole(['admin', 'global_admin', 'super_admin', 'auditor']);
                        @endphp

                        @if($canRespond)
                            <form method="POST" action="{{ route('audit.findings.respond', $finding->id) }}" enctype="multipart/form-data">
                                @csrf
                                <div class="mb-3">
                                    <label class="form-label small fw-bold text-muted text-uppercase mb-1">YOUR EXPLANATION / REMEDIATION <span class="text-danger">*</span></label>
                                    <textarea name="response_notes" class="form-control" rows="4" placeholder="Detail corrective action taken, physical slip references, or clarification..." required></textarea>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label small fw-bold text-muted text-uppercase mb-1">SUPPORTING ATTACHMENT</label>
                                    <input type="file" name="response_attachment" class="form-control" accept="image/*,.pdf,.doc,.docx,.xls,.xlsx">
                                </div>
                                <button type="submit" class="btn btn-info text-white fw-bold rounded-pill px-4 shadow-sm w-100">
                                    <i class="fa-solid fa-paper-plane me-1"></i> Submit Clarification
                                </button>
                            </form>
                        @endif
                    @endif
                </div>
            </div>

            <!-- 2. Auditor Resolution Clearance -->
            <div class="card border-0 shadow-sm rounded-3 bg-white mb-4">
                <div class="card-header bg-white border-bottom py-3 px-3 px-md-4">
                    <h6 class="fw-bold mb-0 text-dark">
                        <i class="fa-solid fa-circle-check text-success me-2"></i>Auditor Resolution &amp; Closure
                    </h6>
                </div>
                <div class="card-body p-4">
                    @if($finding->status === 'resolved')
                        <div class="alert alert-success d-flex align-items-center gap-2 mb-3">
                            <i class="fa-solid fa-check-circle fa-lg text-success"></i>
                            <div>
                                <strong class="d-block">QUERY RESOLVED &amp; CLOSED</strong>
                                <small>Closed by {{ $finding->resolvedBy?->name ?? 'Auditor' }} on {{ $finding->resolved_at?->format('M d, Y H:i') }}</small>
                            </div>
                        </div>
                        <div class="bg-light p-3 rounded border">
                            <label class="form-label text-muted small fw-bold text-uppercase mb-1">AUDITOR CLOSING REMARKS</label>
                            <p class="mb-0 text-dark" style="white-space: pre-line;">{{ $finding->resolution_notes }}</p>
                        </div>
                    @else
                        @php
                            $isAuditorOrAdmin = Auth::user()->hasAnyRole(['auditor', 'audit', 'internal_auditor', 'audit_team', 'Auditor', 'admin', 'global_admin', 'super_admin']);
                        @endphp

                        @if($isAuditorOrAdmin)
                            <form method="POST" action="{{ route('audit.findings.resolve', $finding->id) }}">
                                @csrf
                                <div class="mb-3">
                                    <label class="form-label small fw-bold text-muted text-uppercase mb-1">RESOLUTION / CLOSURE REMARKS <span class="text-danger">*</span></label>
                                    <textarea name="resolution_notes" class="form-control" rows="3" placeholder="State reason for closing finding (e.g. physical count verified, delivery slip uploaded, stock reconciled)..." required></textarea>
                                </div>
                                <button type="submit" class="btn btn-success fw-bold rounded-pill px-4 shadow-sm w-100">
                                    <i class="fa-solid fa-circle-check me-1"></i> Resolve &amp; Close Finding
                                </button>
                            </form>
                        @else
                            <p class="text-muted small mb-0">Only Internal Auditors and Administrators can officially resolve and close this audit query.</p>
                        @endif
                    @endif
                </div>
            </div>
        </div>
    </div>

</div>
@endsection
