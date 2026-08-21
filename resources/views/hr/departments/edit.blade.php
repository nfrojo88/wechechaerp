@extends('layouts.app')
@section('title', 'Edit Department - ' . $department->name)

@section('content')
<div class="container-fluid py-4" style="max-width: 800px;">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-1">
                    <li class="breadcrumb-item"><a href="{{ route('departments.index') }}" class="text-decoration-none text-muted">Departments</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Edit Department</li>
                </ol>
            </nav>
            <h1 class="h3 fw-bold mb-0 text-dark">
                <i class="fas fa-building text-primary me-2"></i>Edit Department
            </h1>
        </div>
        <a href="{{ route('departments.index') }}" class="btn btn-outline-secondary rounded-pill px-3">
            <i class="fas fa-arrow-left me-1"></i>Back to List
        </a>
    </div>

    @if($errors->any())
        <div class="alert alert-danger alert-dismissible fade show rounded-4 border-0 shadow-sm" role="alert">
            <div class="d-flex align-items-center mb-2">
                <i class="fas fa-exclamation-triangle me-2 fs-5"></i>
                <strong class="fs-6">Please check the errors below:</strong>
            </div>
            <ul class="mb-0 ps-3">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
        <div class="card-header bg-white border-0 pt-4 px-4 pb-0">
            <h5 class="fw-bold text-dark mb-1">Department Details</h5>
            <p class="text-muted small mb-0">Update information and leadership for this department.</p>
        </div>
        <div class="card-body p-4">
            <form action="{{ route('departments.update', $department) }}" method="POST">
                @csrf
                @method('PUT')

                <div class="row g-3">
                    <div class="col-md-7">
                        <label class="form-label fw-semibold text-secondary small text-uppercase" style="letter-spacing: 0.5px;">
                            Department Name <span class="text-danger">*</span>
                        </label>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-end-0"><i class="fas fa-building text-muted"></i></span>
                            <input type="text" name="name" class="form-control border-start-0 @error('name') is-invalid @enderror" 
                                   value="{{ old('name', $department->name) }}" placeholder="e.g. Finance & Accounting" required>
                        </div>
                    </div>

                    <div class="col-md-5">
                        <label class="form-label fw-semibold text-secondary small text-uppercase" style="letter-spacing: 0.5px;">
                            Department Code <span class="text-danger">*</span>
                        </label>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-end-0"><i class="fas fa-hashtag text-muted"></i></span>
                            <input type="text" name="code" class="form-control border-start-0 text-uppercase @error('code') is-invalid @enderror" 
                                   value="{{ old('code', $department->code) }}" placeholder="e.g. FIN" required>
                        </div>
                    </div>

                    <div class="col-12">
                        <label class="form-label fw-semibold text-secondary small text-uppercase" style="letter-spacing: 0.5px;">
                            Department Head / Manager
                        </label>
                        <select name="head_id" class="form-select @error('head_id') is-invalid @enderror">
                            <option value="">-- Select Department Head (Optional) --</option>
                            @foreach($employees as $emp)
                                <option value="{{ $emp->id }}" {{ (old('head_id', $department->head_id) == $emp->id) ? 'selected' : '' }}>
                                    {{ $emp->full_name ?? ($emp->first_name . ' ' . $emp->last_name) }} ({{ $emp->role_title ?? 'Employee' }})
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-12">
                        <label class="form-label fw-semibold text-secondary small text-uppercase" style="letter-spacing: 0.5px;">
                            Description
                        </label>
                        <textarea name="description" class="form-control @error('description') is-invalid @enderror" rows="3" 
                                  placeholder="Describe the department's core responsibilities...">{{ old('description', $department->description) }}</textarea>
                    </div>

                    <div class="col-12">
                        <div class="form-check form-switch p-3 bg-light rounded-3 d-flex align-items-center justify-content-between">
                            <div>
                                <label class="form-check-label fw-semibold text-dark mb-0 cursor-pointer" for="isActiveSwitch">
                                    Department Status
                                </label>
                                <div class="text-muted small">Enable or disable this department for role assignments and employee grouping.</div>
                            </div>
                            <input class="form-check-input ms-3" type="checkbox" name="is_active" value="1" id="isActiveSwitch" 
                                   style="width: 3rem; height: 1.5rem;" {{ old('is_active', $department->is_active) ? 'checked' : '' }}>
                        </div>
                    </div>
                </div>

                <div class="mt-4 pt-3 border-top d-flex gap-2 justify-content-end">
                    <a href="{{ route('departments.index') }}" class="btn btn-light px-4 rounded-pill">Cancel</a>
                    <button type="submit" class="btn btn-primary px-4 rounded-pill shadow-sm">
                        <i class="fas fa-save me-1"></i>Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
