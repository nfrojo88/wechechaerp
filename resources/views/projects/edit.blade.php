@extends('layouts.app')

@section('title', 'Edit Project')

@section('content')
<div class="d-flex align-items-center mb-4">
    <a href="{{ route('projects.index') }}" class="btn btn-sm btn-outline-secondary me-3">
        <i class="fa-solid fa-arrow-left"></i>
    </a>
    <h1 class="h3 mb-0">Edit Project: {{ $project->code }}</h1>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body">
        <form method="POST" action="{{ route('projects.update', $project) }}">
            @csrf
            @method('PUT')
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Project Name <span class="text-danger">*</span></label>
                    <input type="text" name="name" class="form-control @error('name') is-invalid @enderror"
                           value="{{ old('name', $project->name) }}" required>
                    @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-3">
                    <label class="form-label">Project Code <span class="text-danger">*</span></label>
                    <input type="text" name="code" class="form-control @error('code') is-invalid @enderror"
                           value="{{ old('code', $project->code) }}" required>
                    @error('code')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-3">
                    <label class="form-label">Status</label>
                    @php
                        $phaseStatus  = $project->planning_phase_status ?? 'draft';
                        $mainBadge    = match($project->status) {
                            'active'    => 'success',
                            'planning'  => 'info',
                            'on_hold'   => 'secondary',
                            'completed' => 'primary',
                            'cancelled' => 'danger',
                            'handover'  => 'dark',
                            'bidding'   => 'warning',
                            default     => 'secondary',
                        };
                        $phaseLabel = match($phaseStatus) {
                            'draft'                      => ['text' => 'Draft — awaiting submission',       'color' => '#94a3b8'],
                            'submitted'                  => ['text' => 'Submitted for review',              'color' => '#6366f1'],
                            'planning_manager_approved'  => ['text' => 'Planning Manager approved',         'color' => '#0891b2'],
                            'coordinator_approved'       => ['text' => 'Coordinator approved',              'color' => '#0891b2'],
                            'technical_manager_approved' => ['text' => 'Technical Manager approved',        'color' => '#0891b2'],
                            'gm_approved'                => ['text' => 'GM approved — budget allocated ✔',  'color' => '#16a34a'],
                            'rejected'                   => ['text' => 'Plan rejected',                     'color' => '#dc2626'],
                            default                      => ['text' => ucfirst($phaseStatus),               'color' => '#94a3b8'],
                        };
                    @endphp

                    {{-- Always read-only: status is driven by the workflow --}}
                    <input type="hidden" name="status" value="{{ $project->status }}">
                    <div class="form-control bg-light d-flex align-items-center gap-2" style="cursor:not-allowed; min-height:2.5rem;">
                        <span class="badge bg-{{ $mainBadge }}">{{ ucfirst(str_replace('_', ' ', $project->status)) }}</span>
                        <i class="fa-solid fa-lock text-muted" style="font-size:.75rem;"></i>
                    </div>
                    <div class="mt-1 px-1">
                        <span class="badge rounded-pill px-2 py-1"
                              style="background:{{ $phaseLabel['color'] }};font-size:.68rem;font-weight:500;">
                            {{ $phaseLabel['text'] }}
                        </span>
                    </div>
                    <div class="form-text">
                        <i class="fa-solid fa-robot me-1 text-info"></i>
                        Auto-detected from the planning workflow.
                    </div>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Client Name</label>
                    <input type="text" name="client_name" class="form-control" value="{{ old('client_name', $project->client_name) }}">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Client Contact</label>
                    <input type="text" name="client_contact" class="form-control" value="{{ old('client_contact', $project->client_contact) }}">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Location</label>
                    <input type="text" name="location" class="form-control" value="{{ old('location', $project->location) }}">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Start Date</label>
                    <input type="date" name="start_date" class="form-control" value="{{ old('start_date', $project->start_date->format('Y-m-d')) }}">
                </div>
                <div class="col-md-3">
                    <label class="form-label">End Date</label>
                    <input type="date" name="end_date" class="form-control" value="{{ old('end_date', $project->end_date->format('Y-m-d')) }}">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Contract Value (ETB)</label>
                    <div class="input-group">
                        <div class="form-control bg-light text-muted d-flex align-items-center justify-content-between" style="cursor:not-allowed;">
                            <span>{{ number_format($project->contract_value, 2) }}</span>
                            <span class="badge bg-secondary ms-2" style="font-size:.65rem;"><i class="fa-solid fa-lock me-1"></i>BOQ</span>
                        </div>
                    </div>
                    <div class="form-text"><i class="fa-solid fa-circle-info me-1 text-info"></i>Auto-updated from the project BOQ total.</div>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Budget Allocated (ETB)</label>
                    <div class="input-group">
                        <div class="form-control bg-light text-muted d-flex align-items-center justify-content-between" style="cursor:not-allowed;">
                            <span>{{ number_format($project->budget_allocated, 2) }}</span>
                            <span class="badge bg-secondary ms-2" style="font-size:.65rem;"><i class="fa-solid fa-lock me-1"></i>GM</span>
                        </div>
                    </div>
                    <div class="form-text"><i class="fa-solid fa-circle-info me-1 text-info"></i>Set by the GM during workflow approval.</div>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Default Store</label>
                    <select name="default_store_id" class="form-select">
                        <option value="">— None —</option>
                        @foreach($stores as $store)
                        <option value="{{ $store->id }}" @selected(old('default_store_id', $project->default_store_id) == $store->id)>
                            {{ $store->name }} ({{ $store->code }})
                        </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12">
                    <label class="form-label">Description</label>
                    <textarea name="description" rows="3" class="form-control">{{ old('description', $project->description) }}</textarea>
                </div>
                <div class="col-12 d-flex justify-content-end gap-2">
                    <a href="{{ route('projects.index') }}" class="btn btn-secondary">Cancel</a>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </div>
        </form>
    </div>
</div>
@endsection
