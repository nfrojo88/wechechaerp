@extends('layouts.app')

@section('title', 'Edit ERP Plan')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="page-title mb-1">
            <i class="fa-solid fa-pen me-2 text-primary"></i>Edit ERP Plan
        </h1>
        <p class="text-muted small mb-0">{{ $erp_plan->name }}</p>
    </div>
    <a href="{{ route('erp-plans.show', $erp_plan) }}" class="btn btn-outline-secondary">
        <i class="fa-solid fa-arrow-left me-1"></i> Back
    </a>
</div>

<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm">
            <div class="card-body p-4">
                <form action="{{ route('erp-plans.update', $erp_plan) }}" method="POST">
                    @csrf @method('PUT')

                    @if($errors->any())
                        <div class="alert alert-danger">
                            <ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
                        </div>
                    @endif

                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label fw-semibold">Plan Name <span class="text-danger">*</span></label>
                            <input type="text" name="name" value="{{ old('name', $erp_plan->name) }}"
                                   class="form-control @error('name') is-invalid @enderror" required>
                            @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-12">
                            <label class="form-label fw-semibold">Project <span class="text-danger">*</span></label>
                            <select name="project_id" class="form-select @error('project_id') is-invalid @enderror" required>
                                <option value="">-- Select Project --</option>
                                @foreach($projects as $project)
                                    <option value="{{ $project->id }}" {{ old('project_id', $erp_plan->project_id) == $project->id ? 'selected' : '' }}>
                                        {{ $project->name }} @if($project->code) ({{ $project->code }}) @endif
                                    </option>
                                @endforeach
                            </select>
                            @error('project_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Start Date <span class="text-danger">*</span></label>
                            <input type="date" name="plan_start_date" id="start_date"
                                   value="{{ old('plan_start_date', $erp_plan->plan_start_date->format('Y-m-d')) }}"
                                   class="form-control @error('plan_start_date') is-invalid @enderror" required>
                            @error('plan_start_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold">End Date <span class="text-danger">*</span></label>
                            <input type="date" name="plan_end_date" id="end_date"
                                   value="{{ old('plan_end_date', $erp_plan->plan_end_date->format('Y-m-d')) }}"
                                   class="form-control @error('plan_end_date') is-invalid @enderror" required>
                            @error('plan_end_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Total Budget (ETB)</label>
                            <input type="number" step="0.01" name="total_budget"
                                   value="{{ old('total_budget', $erp_plan->total_budget) }}"
                                   class="form-control @error('total_budget') is-invalid @enderror">
                            @error('total_budget')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Status</label>
                            <select name="status" class="form-select @error('status') is-invalid @enderror" required>
                                @foreach(['draft','active','on_hold','completed','cancelled'] as $s)
                                    <option value="{{ $s }}" {{ old('status', $erp_plan->status) == $s ? 'selected' : '' }}>
                                        {{ ucfirst(str_replace('_', ' ', $s)) }}
                                    </option>
                                @endforeach
                            </select>
                            @error('status')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-12">
                            <label class="form-label fw-semibold">Description</label>
                            <textarea name="description" rows="3" class="form-control @error('description') is-invalid @enderror">{{ old('description', $erp_plan->description) }}</textarea>
                            @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>

                    <hr class="my-4">
                    <div class="d-flex justify-content-end gap-2">
                        <a href="{{ route('erp-plans.show', $erp_plan) }}" class="btn btn-secondary">Cancel</a>
                        <button type="submit" class="btn btn-primary px-4">
                            <i class="fa-solid fa-floppy-disk me-1"></i> Update Plan
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
