@extends('layouts.app')
@section('title', 'Audit Observations & Queries')

@section('content')
<div class="wc-container py-3 py-md-4">

    <!-- Page Header -->
    <x-page-header
        title="Audit Observations &amp; Queries (Findings)"
        subtitle="Formal compliance queries issued by Internal Audit for storekeeper and procurement response, escalation tracking, and resolution"
        badge="Audit Compliance"
    >
        <x-slot name="actions">
            <div class="d-flex gap-2 flex-wrap align-items-center">
                <a href="{{ request()->fullUrlWithQuery(['export' => 'csv']) }}" class="btn btn-outline-success btn-sm rounded-pill px-3 shadow-xs fw-semibold" style="min-height: 38px; display: inline-flex; align-items: center;">
                    <i class="fa-solid fa-file-excel me-1"></i> Export to Excel / CSV
                </a>
                <button type="button" class="btn btn-warning text-dark btn-sm rounded-pill px-3 shadow-xs fw-bold" style="min-height: 38px; display: inline-flex; align-items: center;" data-bs-toggle="modal" data-bs-target="#raiseFindingModal" data-source-type="general">
                    <i class="fa-solid fa-plus-circle me-1"></i> Issue New Finding
                </button>
            </div>
        </x-slot>
    </x-page-header>

    <!-- Audit Navigation Pills -->
    <div class="card border-0 shadow-sm rounded-3 mb-4 bg-white">
        <div class="card-body p-2.5">
            <ul class="nav nav-pills gap-1 flex-wrap">
                <li class="nav-item">
                    <a class="nav-link rounded-pill fw-semibold px-3 py-1.5 text-dark" href="{{ route('audit.inventory') }}">
                        <i class="fa-solid fa-warehouse me-1 text-info"></i> Inventory Overview
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link rounded-pill fw-semibold px-3 py-1.5 text-dark" href="{{ route('audit.stock-movements') }}">
                        <i class="fa-solid fa-arrows-split-up-and-left me-1 text-success"></i> Movement Ledger
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link rounded-pill fw-semibold px-3 py-1.5 text-dark" href="{{ route('audit.daily-usage') }}">
                        <i class="fa-solid fa-clipboard-list me-1 text-primary"></i> Daily Usage
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link rounded-pill fw-semibold px-3 py-1.5 text-dark" href="{{ route('audit.transfers') }}">
                        <i class="fa-solid fa-truck-moving me-1 text-warning"></i> Material Transfers
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link rounded-pill fw-semibold px-3 py-1.5 text-dark" href="{{ route('audit.fulfillment') }}">
                        <i class="fa-solid fa-boxes-packing me-1 text-danger"></i> Intake Fulfillment
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link active rounded-pill fw-semibold px-3 py-1.5" href="{{ route('audit.findings.index') }}">
                        <i class="fa-solid fa-clipboard-question me-1 text-secondary"></i> Findings &amp; Queries
                    </a>
                </li>
            </ul>
        </div>
    </div>

    <!-- Top KPI Row -->
    <div class="row g-3 mb-4">
        <div class="col-12 col-sm-6 col-xl-2">
            <x-kpi-card
                title="Open Queries"
                :value="$kpiOpen . ' Open'"
                subtitle="Awaiting response / action"
                icon="fa-solid fa-clipboard-question"
                tone="warning"
            />
        </div>
        <div class="col-12 col-sm-6 col-xl-3">
            <x-kpi-card
                title="Responded (Awaiting Review)"
                :value="$kpiResponded . ' Responded'"
                subtitle="Assignee submitted clarification"
                icon="fa-solid fa-reply-all"
                tone="info"
            />
        </div>
        <div class="col-12 col-sm-6 col-xl-2">
            <x-kpi-card
                title="🚨 Overdue (to GM)"
                :value="$kpiOverdue . ' Overdue'"
                subtitle="Escalated to General Manager"
                icon="fa-solid fa-bell"
                :tone="$kpiOverdue > 0 ? 'danger' : 'success'"
            />
        </div>
        <div class="col-12 col-sm-6 col-xl-3">
            <x-kpi-card
                title="Resolved Queries"
                :value="$kpiResolved . ' Resolved'"
                subtitle="Reviewed and closed by auditor"
                icon="fa-solid fa-circle-check"
                tone="success"
            />
        </div>
        <div class="col-12 col-sm-6 col-xl-2">
            <x-kpi-card
                title="Total Findings"
                :value="$kpiTotal . ' Total'"
                subtitle="Full audit registry"
                icon="fa-solid fa-folder-closed"
                tone="primary"
            />
        </div>
    </div>

    <!-- Status Tabs -->
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
        <div class="btn-group shadow-xs rounded-pill p-1 bg-white border">
            <a href="{{ request()->fullUrlWithQuery(['tab' => 'open', 'page' => 1]) }}" class="btn btn-sm rounded-pill px-3 fw-bold {{ $tab === 'open' ? 'btn-warning text-dark' : 'btn-light text-muted border-0' }}">
                <i class="fa-solid fa-clipboard-question me-1"></i> Open Findings
                <span class="badge bg-dark text-white rounded-pill ms-1">{{ $kpiOpen }}</span>
            </a>
            <a href="{{ request()->fullUrlWithQuery(['tab' => 'responded', 'page' => 1]) }}" class="btn btn-sm rounded-pill px-3 fw-bold {{ $tab === 'responded' ? 'btn-info text-white' : 'btn-light text-muted border-0' }}">
                <i class="fa-solid fa-reply me-1"></i> Responded
                <span class="badge bg-white text-info rounded-pill ms-1">{{ $kpiResponded }}</span>
            </a>
            <a href="{{ request()->fullUrlWithQuery(['tab' => 'overdue', 'page' => 1]) }}" class="btn btn-sm rounded-pill px-3 fw-bold {{ $tab === 'overdue' ? 'btn-danger text-white' : 'btn-light text-danger border-0' }}">
                <i class="fa-solid fa-bell me-1"></i> Overdue / Escalated
                <span class="badge bg-white text-danger rounded-pill ms-1 fw-bold">{{ $kpiOverdue }}</span>
            </a>
            <a href="{{ request()->fullUrlWithQuery(['tab' => 'resolved', 'page' => 1]) }}" class="btn btn-sm rounded-pill px-3 fw-bold {{ $tab === 'resolved' ? 'btn-success text-white' : 'btn-light text-muted border-0' }}">
                <i class="fa-solid fa-circle-check me-1"></i> Resolved
                <span class="badge bg-white text-success rounded-pill ms-1">{{ $kpiResolved }}</span>
            </a>
            <a href="{{ request()->fullUrlWithQuery(['tab' => 'all', 'page' => 1]) }}" class="btn btn-sm rounded-pill px-3 fw-bold {{ $tab === 'all' ? 'btn-primary' : 'btn-light text-muted border-0' }}">
                <i class="fa-solid fa-list me-1"></i> All Queries
                <span class="badge bg-secondary rounded-pill ms-1">{{ $kpiTotal }}</span>
            </a>
        </div>
    </div>

    <!-- Filter Bar -->
    <div class="card border-0 shadow-sm rounded-3 mb-4 bg-white">
        <div class="card-body p-3">
            <form method="GET" action="{{ route('audit.findings.index') }}" class="row g-2 align-items-end">
                <input type="hidden" name="tab" value="{{ $tab }}">

                <div class="col-md-2">
                    <label class="form-label small fw-bold text-muted mb-1">SEVERITY</label>
                    <select name="severity" class="form-select form-select-sm">
                        <option value="">All Severities</option>
                        <option value="critical" {{ request('severity') == 'critical' ? 'selected' : '' }}>Critical</option>
                        <option value="high" {{ request('severity') == 'high' ? 'selected' : '' }}>High</option>
                        <option value="medium" {{ request('severity') == 'medium' ? 'selected' : '' }}>Medium</option>
                        <option value="low" {{ request('severity') == 'low' ? 'selected' : '' }}>Low</option>
                    </select>
                </div>

                <div class="col-md-3">
                    <label class="form-label small fw-bold text-muted mb-1">STORE / WAREHOUSE</label>
                    <select name="store_id" class="form-select form-select-sm">
                        <option value="">All Stores</option>
                        @foreach($stores as $st)
                            <option value="{{ $st->id }}" {{ request('store_id') == $st->id ? 'selected' : '' }}>
                                {{ $st->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-3">
                    <label class="form-label small fw-bold text-muted mb-1">ASSIGNED PERSON</label>
                    <select name="assigned_to" class="form-select form-select-sm">
                        <option value="">All Assignees</option>
                        @foreach($assignees as $u)
                            <option value="{{ $u->id }}" {{ request('assigned_to') == $u->id ? 'selected' : '' }}>
                                {{ $u->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-3">
                    <label class="form-label small fw-bold text-muted mb-1">SEARCH</label>
                    <input type="text" name="search" class="form-control form-select-sm" placeholder="Finding #, Title, text..." value="{{ request('search') }}">
                </div>

                <div class="col-md-1 d-flex gap-1">
                    <button type="submit" class="btn btn-primary btn-sm rounded-pill w-100 fw-semibold" style="height: 31px;">
                        <i class="fa-solid fa-filter me-1"></i> Filter
                    </button>
                    @if(request()->hasAny(['severity', 'store_id', 'assigned_to', 'search']))
                        <a href="{{ route('audit.findings.index', ['tab' => $tab]) }}" class="btn btn-light btn-sm border rounded-pill" title="Reset Filters" style="height: 31px;">
                            <i class="fa-solid fa-rotate-left"></i>
                        </a>
                    @endif
                </div>
            </form>
        </div>
    </div>

    <!-- Findings Table -->
    <div class="card border-0 shadow-sm rounded-3 bg-white mb-4">
        <div class="card-header bg-white border-bottom py-3 px-3 px-md-4 d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div>
                <h6 class="fw-bold mb-0 text-dark">
                    <i class="fa-solid fa-clipboard-question text-primary me-2"></i>Audit Findings Registry
                </h6>
                <small class="text-muted">Showing {{ $findings->firstItem() ?? 0 }} to {{ $findings->lastItem() ?? 0 }} of {{ $findings->total() }} recorded findings</small>
            </div>
            <span class="badge bg-light text-dark border font-monospace px-3 py-1.5 rounded-pill">
                Page {{ $findings->currentPage() }} of {{ $findings->lastPage() }}
            </span>
        </div>

        <div class="card-body p-0">
            <div class="table-responsive wc-table-mobile-cards">
                <table class="table table-hover align-middle mb-0 small">
                    <thead class="bg-light text-muted text-uppercase" style="font-size: 0.75rem;">
                        <tr>
                            <th class="ps-4 py-2.5">Finding # &amp; Date</th>
                            <th class="py-2.5">Title &amp; Observation</th>
                            <th class="py-2.5 text-center">Severity</th>
                            <th class="py-2.5">Source Module</th>
                            <th class="py-2.5">Store / Project</th>
                            <th class="py-2.5">Assigned To</th>
                            <th class="py-2.5">Due Date</th>
                            <th class="py-2.5 text-center">Status</th>
                            <th class="pe-4 py-2.5 text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($findings as $f)
                            @php
                                $sevBadge = match($f->severity) {
                                    'critical' => 'danger',
                                    'high'     => 'warning',
                                    'medium'   => 'info',
                                    default    => 'secondary',
                                };
                            @endphp
                            <tr class="{{ $f->is_overdue ? 'table-danger bg-opacity-10' : ($f->status === 'responded' ? 'table-info bg-opacity-10' : '') }}">
                                <td data-label="Finding #" class="ps-4 py-3">
                                    <span class="fw-bold font-monospace text-primary d-block">{{ $f->finding_number }}</span>
                                    <small class="text-muted">{{ $f->created_at?->format('M d, Y') }}</small>
                                </td>
                                <td data-label="Title" class="py-3">
                                    <strong class="text-dark d-block">{{ $f->title }}</strong>
                                    <small class="text-muted">{{ Str::limit($f->observation, 65) }}</small>
                                </td>
                                <td data-label="Severity" class="py-3 text-center">
                                    <span class="badge bg-{{ $sevBadge }} text-uppercase font-monospace px-2.5 py-1">
                                        {{ $f->severity }}
                                    </span>
                                </td>
                                <td data-label="Source" class="py-3">
                                    <span class="badge bg-light text-dark border font-monospace">
                                        {{ strtoupper(str_replace('_', ' ', $f->source_type)) }}
                                    </span>
                                </td>
                                <td data-label="Location" class="py-3">
                                    <span class="d-block text-dark fw-semibold">{{ $f->store?->name ?? 'General Store' }}</span>
                                    <small class="text-muted">{{ $f->project?->name ?? 'Site' }}</small>
                                </td>
                                <td data-label="Assignee" class="py-3">
                                    <span class="text-dark fw-semibold d-block"><i class="fa-solid fa-user me-1 text-secondary"></i>{{ $f->assignedTo?->name ?? 'Unassigned' }}</span>
                                </td>
                                <td data-label="Due Date" class="py-3">
                                    @if($f->is_overdue)
                                        <span class="badge bg-danger rounded-pill px-2.5 py-1 font-monospace">
                                            <i class="fa-solid fa-clock me-1"></i>OVERDUE
                                        </span>
                                        <small class="text-danger d-block">{{ $f->due_date ? $f->due_date->format('M d, Y') : '' }}</small>
                                    @else
                                        <span class="text-dark">{{ $f->due_date ? $f->due_date->format('M d, Y') : 'N/A' }}</span>
                                    @endif
                                </td>
                                <td data-label="Status" class="py-3 text-center">
                                    @if($f->status === 'resolved')
                                        <span class="badge bg-success bg-opacity-25 text-success border border-success-subtle rounded-pill px-2.5 py-1">
                                            <i class="fa-solid fa-check me-1"></i>Resolved
                                        </span>
                                    @elseif($f->status === 'responded')
                                        <span class="badge bg-info text-white rounded-pill px-2.5 py-1">
                                            Responded
                                        </span>
                                    @elseif($f->is_escalated)
                                        <span class="badge bg-danger rounded-pill px-2.5 py-1">
                                            Escalated to GM
                                        </span>
                                    @else
                                        <span class="badge bg-warning text-dark rounded-pill px-2.5 py-1">
                                            Open
                                        </span>
                                    @endif
                                </td>
                                <td data-label="Action" class="pe-4 py-3 text-end">
                                    <a href="{{ route('audit.findings.show', $f->id) }}" class="btn btn-sm btn-outline-primary rounded-pill px-3 shadow-xs fw-semibold" style="min-height: 32px; display: inline-flex; align-items: center;">
                                        <i class="fa-solid fa-eye me-1"></i> View / Review
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="text-center py-5 text-muted">
                                    <i class="fa-solid fa-clipboard-check fa-2x mb-2 d-block text-secondary"></i>
                                    No audit findings recorded under this tab.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($findings->hasPages())
                <div class="p-3 border-top d-flex justify-content-center">
                    {{ $findings->links() }}
                </div>
            @endif
        </div>
    </div>

</div>

@include('audit._raise_finding_modal')
@endsection
