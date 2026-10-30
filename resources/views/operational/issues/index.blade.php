@extends('layouts.app')
@section('title', 'Site Issues & Bottlenecks — Site Operations')

@section('content')
@php
    $totalIssues = $issues->count();
    $criticalCount = $issues->whereIn('priority', ['critical', 'high'])->count();
    $openCount = $issues->where('status', 'open')->count();
    $resolvedCount = $issues->where('status', 'resolved')->count();
@endphp

<div class="container-fluid px-0" x-data="{ search: '', categoryFilter: '', priorityFilter: '' }">
    {{-- Page Header --}}
    <x-page-header
        title="Site Issues &amp; Bottlenecks Tracking"
        subtitle="Operational issue variance logs, technical drawing roadblocks, safety hazards, equipment downtime, and critical path delays."
        :breadcrumbs="[
            ['label' => 'Dashboard', 'url' => route('dashboard')],
            ['label' => 'Site Operations', 'url' => route('dashboard.site-engineer')],
            ['label' => 'Issues Tracking']
        ]">
        <x-slot name="actions">
            <x-export-menu :print="true" label="Print" />
            <a href="{{ route('issues.create') }}" class="btn btn-primary btn-sm rounded-pill px-3 py-2 shadow-xs fw-semibold">
                <i class="fa-solid fa-plus me-1.5"></i>Report New Issue
            </a>
        </x-slot>
    </x-page-header>

    {{-- System Alerts --}}
    @if(session('success'))
        <div class="alert alert-success border-0 shadow-sm rounded-4 mb-4 d-flex align-items-center gap-3 p-3 bg-success bg-opacity-10 border-start border-4 border-success">
            <div class="p-2 bg-success text-white rounded-circle fs-5 flex-shrink-0">
                <i class="fa-solid fa-circle-check"></i>
            </div>
            <div class="flex-grow-1 text-dark fw-medium">{{ session('success') }}</div>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    {{-- Executive Summary KPI Cards --}}
    <div class="row g-3 mb-4">
        <div class="col-6 col-lg-3">
            <x-kpi-card
                title="Total Issues Logged"
                :value="number_format($totalIssues)"
                changeLabel="All field roadblock reports"
                icon="fa-solid fa-triangle-exclamation"
                color="primary" />
        </div>
        <div class="col-6 col-lg-3">
            <x-kpi-card
                title="Critical & High"
                :value="number_format($criticalCount)"
                changeLabel="Urgent attention required"
                icon="fa-solid fa-fire text-danger"
                color="danger" />
        </div>
        <div class="col-6 col-lg-3">
            <x-kpi-card
                title="Open Bottlenecks"
                :value="number_format($openCount)"
                changeLabel="Active unmitigated blockers"
                icon="fa-solid fa-circle-exclamation"
                color="warning" />
        </div>
        <div class="col-6 col-lg-3">
            <x-kpi-card
                title="Resolved Issues"
                :value="number_format($resolvedCount)"
                changeLabel="Successfully closed out"
                icon="fa-solid fa-circle-check"
                color="success" />
        </div>
    </div>

    {{-- Client-side Filter Toolbar --}}
    <div class="card border-0 shadow-sm rounded-4 mb-4">
        <div class="card-body p-3">
            <div class="row g-2 align-items-center">
                <div class="col-12 col-md-5">
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-white border-end-0 text-muted">
                            <i class="fa-solid fa-magnifying-glass"></i>
                        </span>
                        <input
                            type="search"
                            class="form-control border-start-0 ps-0"
                            placeholder="Search by issue title or project name..."
                            x-model="search"
                            aria-label="Search issues">
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <select class="form-select form-select-sm" x-model="categoryFilter">
                        <option value="">All Categories</option>
                        <option value="safety">Safety</option>
                        <option value="quality">Quality</option>
                        <option value="schedule">Schedule</option>
                        <option value="material">Material</option>
                        <option value="equipment">Equipment</option>
                        <option value="other">Other</option>
                    </select>
                </div>
                <div class="col-6 col-md-2">
                    <select class="form-select form-select-sm" x-model="priorityFilter">
                        <option value="">All Priorities</option>
                        <option value="critical">Critical</option>
                        <option value="high">High</option>
                        <option value="medium">Medium</option>
                        <option value="low">Low</option>
                    </select>
                </div>
                <div class="col-12 col-md-2 text-end">
                    <button
                        type="button"
                        class="btn btn-sm btn-outline-secondary rounded-pill w-100"
                        @click="search = ''; categoryFilter = ''; priorityFilter = ''"
                        x-show="search || categoryFilter || priorityFilter">
                        <i class="fa-solid fa-rotate-left me-1"></i>Reset
                    </button>
                    <span class="text-muted small d-inline-block py-1" x-show="!search && !categoryFilter && !priorityFilter">
                        {{ $totalIssues }} issues
                    </span>
                </div>
            </div>
        </div>
    </div>

    {{-- Issues Tracking Table Card --}}
    <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
        <div class="card-header bg-white py-3 px-4 border-bottom d-flex align-items-center justify-content-between">
            <div class="d-flex align-items-center gap-2">
                <span class="badge bg-danger bg-opacity-10 text-danger rounded-pill px-2.5 py-1 fw-bold small">
                    <i class="fa-solid fa-list-check me-1"></i>Issue Register
                </span>
                <h6 class="fw-bold mb-0 text-dark">Active Field Roadblocks &amp; Tasks</h6>
            </div>
            <span class="text-muted small">{{ $totalIssues }} Issues</span>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 wc-table-mobile-cards">
                <thead class="table-light">
                    <tr>
                        <th class="ps-4 text-secondary text-uppercase small fw-bold">Issue ID</th>
                        <th class="text-secondary text-uppercase small fw-bold">Title &amp; Category</th>
                        <th class="text-secondary text-uppercase small fw-bold">Project</th>
                        <th class="text-center text-secondary text-uppercase small fw-bold">Priority</th>
                        <th class="text-center text-secondary text-uppercase small fw-bold">Status</th>
                        <th class="text-secondary text-uppercase small fw-bold">Reported By</th>
                        <th class="pe-4 text-end text-secondary text-uppercase small fw-bold">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($issues as $i)
                        @php
                            $pBadge = match(strtolower($i->priority)) {
                                'critical' => 'bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25',
                                'high' => 'bg-warning bg-opacity-10 text-dark border border-warning border-opacity-50',
                                'medium' => 'bg-info bg-opacity-10 text-info',
                                default => 'bg-secondary bg-opacity-10 text-secondary'
                            };
                            $sBadge = match(strtolower($i->status)) {
                                'resolved' => 'bg-success bg-opacity-10 text-success',
                                'open' => 'bg-danger bg-opacity-10 text-danger',
                                default => 'bg-secondary bg-opacity-10 text-secondary'
                            };
                        @endphp
                        <tr
                            x-show="(
                                !search || 
                                '{{ strtolower(addslashes($i->title)) }}'.includes(search.toLowerCase()) || 
                                '{{ strtolower(addslashes($i->project?->name ?? '')) }}'.includes(search.toLowerCase())
                            ) && (
                                !categoryFilter || 
                                '{{ strtolower($i->category) }}' === categoryFilter.toLowerCase()
                            ) && (
                                !priorityFilter || 
                                '{{ strtolower($i->priority) }}' === priorityFilter.toLowerCase()
                            )">
                            <td class="ps-4 font-monospace fw-bold text-primary" data-label="Issue ID">
                                #ISS-{{ str_pad($i->id, 4, '0', STR_PAD_LEFT) }}
                            </td>
                            <td data-label="Title">
                                <div class="fw-bold text-dark">{{ $i->title }}</div>
                                <span class="badge bg-light text-muted border text-uppercase" style="font-size: 0.68rem; letter-spacing: 0.5px;">
                                    {{ ucfirst($i->category) }}
                                </span>
                            </td>
                            <td data-label="Project">
                                <span class="fw-semibold text-dark">{{ $i->project->name ?? 'N/A' }}</span>
                            </td>
                            <td class="text-center" data-label="Priority">
                                <span class="badge {{ $pBadge }} rounded-pill px-2.5 py-1 text-uppercase fw-bold" style="font-size: 0.7rem;">
                                    {{ ucfirst($i->priority) }}
                                </span>
                            </td>
                            <td class="text-center" data-label="Status">
                                <span class="badge {{ $sBadge }} rounded-pill px-2.5 py-1 fw-semibold">
                                    {{ ucfirst($i->status) }}
                                </span>
                            </td>
                            <td data-label="Reported By">
                                <small class="fw-semibold text-dark">{{ $i->reportedBy->name ?? 'Site Staff' }}</small>
                            </td>
                            <td class="pe-4 text-end" data-label="Action">
                                <a href="{{ route('issues.show', $i) }}" class="btn btn-sm btn-outline-primary rounded-pill px-3 py-1 shadow-xs fw-semibold">
                                    <i class="fa-solid fa-eye me-1"></i>View
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center py-5 text-muted">
                                <x-empty-state
                                    title="No Site Issues Reported"
                                    description="No operational bottlenecks or field issues have been filed. Great work on site safety and progress!"
                                    icon="fa-solid fa-circle-check" />
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
