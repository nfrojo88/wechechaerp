@extends('layouts.app')

@section('title', 'Projects')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0">Projects</h1>
    @can('projects.create')
    <a href="{{ route('projects.create') }}" class="btn btn-primary">
        <i class="fa-solid fa-plus me-1"></i> New Project
    </a>
    @endcan
</div>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <form action="{{ route('projects.index') }}" method="GET" class="row g-3">
            <div class="col-md-5">
                <div class="input-group">
                    <span class="input-group-text bg-light border-end-0"><i class="fa-solid fa-search text-muted"></i></span>
                    <input type="text" name="search" class="form-control border-start-0 ps-0" placeholder="Search by code, name, or client..." value="{{ request('search') }}">
                </div>
            </div>
            <div class="col-md-4">
                <select name="status" class="form-select">
                    <option value="">All Statuses</option>
                    @foreach(['planning', 'bidding', 'active', 'on_hold', 'completed', 'cancelled', 'handover'] as $status)
                        <option value="{{ $status }}" {{ request('status') === $status ? 'selected' : '' }}>
                            {{ ucfirst(str_replace('_', ' ', $status)) }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <button type="submit" class="btn btn-primary w-100"><i class="fa-solid fa-filter me-1"></i> Filter</button>
            </div>
        </form>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Code</th>
                        <th>Name</th>
                        <th>Client</th>
                        <th>Status</th>
                        <th>Start Date</th>
                        <th>Contract Value</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($projects as $project)
                    <tr>
                        <td><code>{{ $project->code }}</code></td>
                        <td>
                            <a href="{{ route('projects.show', $project) }}" class="fw-semibold text-decoration-none">
                                {{ $project->name }}
                            </a>
                            @if($project->location)
                            <div class="text-muted small"><i class="fa-solid fa-location-dot me-1"></i>{{ $project->location }}</div>
                            @endif
                            @php $pca = $project->pettyCashAccount; @endphp
                            @if($pca)
                            <div class="mt-1">
                                <span class="badge bg-success-subtle text-success border border-success-subtle" style="font-size:0.72rem;">
                                    <i class="fa-solid fa-wallet me-1"></i>{{ $pca->name }} [{{ $pca->code }}]
                                </span>
                            </div>
                            @endif
                        </td>
                        <td>{{ $project->client_name ?? '—' }}</td>
                        <td>
                            @php
                                $badge = match($project->status) {
                                    'active'    => 'success',
                                    'planning'  => 'info',
                                    'bidding'   => 'warning',
                                    'on_hold'   => 'secondary',
                                    'completed' => 'primary',
                                    'cancelled' => 'danger',
                                    'handover'  => 'dark',
                                    default     => 'secondary',
                                };

                                // Planning phase sub-badge
                                $phaseLabel = match($project->planning_phase_status ?? 'draft') {
                                    'draft'                      => null,   // don't show if not started
                                    'submitted'                  => ['label' => 'Submitted', 'color' => '#6366f1'],
                                    'planning_manager_approved'  => ['label' => 'Plng. Mgr ✓', 'color' => '#0891b2'],
                                    'coordinator_approved'       => ['label' => 'Coord. ✓', 'color' => '#0891b2'],
                                    'technical_manager_approved' => ['label' => 'Tech. Mgr ✓', 'color' => '#0891b2'],
                                    'gm_approved'                => ['label' => 'GM Approved ✓', 'color' => '#16a34a'],
                                    'rejected'                   => ['label' => 'Rejected', 'color' => '#dc2626'],
                                    default                      => null,
                                };
                            @endphp
                            <span class="badge bg-{{ $badge }}">{{ ucfirst(str_replace('_', ' ', $project->status)) }}</span>
                            @if($phaseLabel)
                                <br>
                                <span class="badge rounded-pill mt-1"
                                      style="background:{{ $phaseLabel['color'] }};font-size:.68rem;font-weight:500;">
                                    {{ $phaseLabel['label'] }}
                                </span>
                            @endif
                        </td>
                        <td>{{ $project->start_date?->format('d M Y') ?? '—' }}</td>
                        <td>{{ number_format($project->contract_value, 2) }}</td>
                        <td class="text-end">
                            @can('projects.edit')
                            <a href="{{ route('projects.edit', $project) }}" class="btn btn-sm btn-outline-primary">
                                <i class="fa-solid fa-pen"></i>
                            </a>
                            @endcan
                            @can('projects.delete')
                            <form method="POST" action="{{ route('projects.destroy', $project) }}" class="d-inline"
                                  onsubmit="return confirm('Archive this project?')">
                                @csrf @method('DELETE')
                                <button class="btn btn-sm btn-outline-danger"><i class="fa-solid fa-archive"></i></button>
                            </form>
                            @endcan
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="7" class="text-center text-muted py-4">No projects found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if($projects->hasPages())
    <div class="card-footer bg-transparent">
        {{ $projects->links() }}
    </div>
    @endif
</div>
@endsection
