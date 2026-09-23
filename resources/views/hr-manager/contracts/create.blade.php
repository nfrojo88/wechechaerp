@extends('layouts.app')
@section('title', 'Create Employee Contract')

@section('content')
<div class="container-fluid py-4">
    {{-- Header --}}
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2 pb-2 border-bottom">
        <div>
            <div class="d-flex align-items-center gap-2">
                <a href="{{ route('contracts.index') }}" class="btn btn-outline-secondary btn-sm rounded-pill px-3 shadow-xs">
                    <i class="fa-solid fa-arrow-left me-1"></i>Back to Contracts
                </a>
                <span class="badge bg-primary bg-opacity-10 text-primary px-3 py-1 rounded-pill fw-semibold">
                    <i class="fa-solid fa-file-signature me-1"></i>HR Contract Management
                </span>
            </div>
            <h1 class="h3 mb-0 text-dark fw-bold mt-2">
                <i class="fa-solid fa-file-contract text-primary me-2"></i>Create New Employee Contract
            </h1>
            <p class="text-muted small mb-0 mt-1">
                Draft an employment agreement, specify salary, timeline, project terms, and upload signed documents.
            </p>
        </div>
        <div>
            <a href="{{ route('contracts.index') }}" class="btn btn-light border rounded-pill px-3">
                <i class="fa-solid fa-list me-1"></i>View All Contracts
            </a>
        </div>
    </div>

    {{-- Validation Error Alerts --}}
    @if ($errors->any())
        <div class="alert alert-danger alert-dismissible fade show rounded-4 border-0 shadow-sm mb-4" role="alert">
            <div class="d-flex align-items-start gap-2">
                <i class="fa-solid fa-triangle-exclamation fs-5 text-danger mt-1"></i>
                <div>
                    <h6 class="fw-bold mb-1">Please correct the following errors:</h6>
                    <ul class="mb-0 small ps-3">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <form action="{{ route('contracts.store') }}" method="POST" enctype="multipart/form-data">
        @csrf

        <div class="row g-4">
            {{-- Main Form Column --}}
            <div class="col-lg-8">
                {{-- 1. Employee & Contract Classification --}}
                <div class="card border-0 shadow-sm rounded-4 mb-4">
                    <div class="card-header bg-white border-0 py-3 px-4">
                        <h5 class="fw-bold text-dark mb-0">
                            <i class="fa-solid fa-user-check text-primary me-2"></i>Employee &amp; Classification
                        </h5>
                    </div>
                    <div class="card-body px-4 pb-4 pt-2">
                        <div class="row g-3">
                            {{-- Employee Selection --}}
                            <div class="col-md-12">
                                <label class="form-label fw-bold small text-uppercase text-secondary">
                                    Select Employee <span class="text-danger">*</span>
                                </label>
                                <select name="employee_id" class="form-select form-select-lg rounded-3 @error('employee_id') is-invalid @enderror" required>
                                    <option value="">— Choose an Employee —</option>
                                    @foreach($employees as $emp)
                                        <option value="{{ $emp->id }}" {{ (old('employee_id', request('employee_id')) == $emp->id) ? 'selected' : '' }}>
                                            {{ $emp->full_name ?? ($emp->first_name . ' ' . $emp->last_name) }}
                                            @if($emp->employee_code || $emp->code)
                                                ({{ $emp->employee_code ?: $emp->code }})
                                            @endif
                                            @if($emp->designation || $emp->position)
                                                — {{ $emp->designation ?? $emp->position }}
                                            @endif
                                        </option>
                                    @endforeach
                                </select>
                                @error('employee_id')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            {{-- Contract Type --}}
                            <div class="col-md-6">
                                <label class="form-label fw-bold small text-uppercase text-secondary">
                                    Contract Type <span class="text-danger">*</span>
                                </label>
                                <select name="contract_type" class="form-select rounded-3 @error('contract_type') is-invalid @enderror" required>
                                    <option value="">— Select Type —</option>
                                    @foreach($contractTypes as $type)
                                        <option value="{{ $type }}" {{ old('contract_type') == $type ? 'selected' : '' }}>
                                            {{ $type }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('contract_type')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            {{-- Project Assignment --}}
                            <div class="col-md-6">
                                <label class="form-label fw-bold small text-uppercase text-secondary">
                                    Assigned Project (Optional)
                                </label>
                                <select name="project_id" class="form-select rounded-3 @error('project_id') is-invalid @enderror">
                                    <option value="">— Head Office / General Staff —</option>
                                    @if(isset($projects))
                                        @foreach($projects as $p)
                                            <option value="{{ $p->id }}" {{ old('project_id') == $p->id ? 'selected' : '' }}>
                                                {{ $p->name }} ({{ $p->code ?? 'Site' }})
                                            </option>
                                        @endforeach
                                    @endif
                                </select>
                                @error('project_id')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                    </div>
                </div>

                {{-- 2. Duration & Timeline --}}
                <div class="card border-0 shadow-sm rounded-4 mb-4">
                    <div class="card-header bg-white border-0 py-3 px-4">
                        <h5 class="fw-bold text-dark mb-0">
                            <i class="fa-solid fa-calendar-days text-primary me-2"></i>Duration &amp; Timeline
                        </h5>
                    </div>
                    <div class="card-body px-4 pb-4 pt-2">
                        <div class="row g-3">
                            {{-- Duration Type --}}
                            <div class="col-12">
                                <label class="form-label fw-bold small text-uppercase text-secondary">Duration Policy</label>
                                <div class="d-flex gap-3 flex-wrap">
                                    <div class="form-check border p-3 rounded-3 bg-light flex-fill ps-4">
                                        <input class="form-check-input" type="radio" name="duration_type" id="dur_fixed" value="fixed_date" 
                                               {{ old('duration_type', 'fixed_date') === 'fixed_date' ? 'checked' : '' }}
                                               onchange="document.getElementById('end_date_wrapper').style.display = 'block';">
                                        <label class="form-check-label fw-bold text-dark small" for="dur_fixed">
                                            Fixed End Date
                                            <span class="d-block text-muted fw-normal" style="font-size:0.75rem;">Contract expires on a specific calendar date</span>
                                        </label>
                                    </div>
                                    <div class="form-check border p-3 rounded-3 bg-light flex-fill ps-4">
                                        <input class="form-check-input" type="radio" name="duration_type" id="dur_proj" value="until_project_completion"
                                               {{ old('duration_type') === 'until_project_completion' ? 'checked' : '' }}
                                               onchange="document.getElementById('end_date_wrapper').style.display = 'none';">
                                        <label class="form-check-label fw-bold text-dark small" for="dur_proj">
                                            Until Project Completion
                                            <span class="d-block text-muted fw-normal" style="font-size:0.75rem;">Contract remains active until the assigned project is completed</span>
                                        </label>
                                    </div>
                                </div>
                            </div>

                            {{-- Start Date --}}
                            <div class="col-md-6">
                                <label class="form-label fw-bold small text-uppercase text-secondary">
                                    Start Date <span class="text-danger">*</span>
                                </label>
                                <input type="date" name="start_date" class="form-control rounded-3 @error('start_date') is-invalid @enderror" value="{{ old('start_date', now()->format('Y-m-d')) }}" required>
                                @error('start_date')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            {{-- End Date --}}
                            <div class="col-md-6" id="end_date_wrapper" style="{{ old('duration_type') === 'until_project_completion' ? 'display:none;' : '' }}">
                                <label class="form-label fw-bold small text-uppercase text-secondary">
                                    End Date
                                </label>
                                <input type="date" name="end_date" class="form-control rounded-3 @error('end_date') is-invalid @enderror" value="{{ old('end_date', now()->addYear()->format('Y-m-d')) }}">
                                <div class="form-text small">Required for fixed date contracts. Must be after start date.</div>
                                @error('end_date')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                    </div>
                </div>

                {{-- 3. Terms & Special Conditions --}}
                <div class="card border-0 shadow-sm rounded-4 mb-4">
                    <div class="card-header bg-white border-0 py-3 px-4">
                        <h5 class="fw-bold text-dark mb-0">
                            <i class="fa-solid fa-file-lines text-primary me-2"></i>Contract Terms &amp; Scope
                        </h5>
                    </div>
                    <div class="card-body px-4 pb-4 pt-2">
                        <div class="mb-3">
                            <label class="form-label fw-bold small text-uppercase text-secondary">
                                Standard Terms &amp; Conditions <span class="text-danger">*</span>
                            </label>
                            <textarea name="terms" rows="6" class="form-control rounded-3 @error('terms') is-invalid @enderror" required placeholder="Enter standard employment obligations, work hours, probation terms, duties, and termination clauses...">{{ old('terms', "1. The Employee agrees to perform duties faithfully and conform to company policies.\n2. Normal working hours shall be 48 hours per week as per Ethiopian labor law.\n3. Either party may terminate this agreement with 30 days written notice or compensation in lieu.\n4. Confidentiality regarding company projects and operations must be maintained at all times.") }}</textarea>
                            <div class="form-text small">Minimum 20 characters. Outline duties, working hours, and termination rules.</div>
                            @error('terms')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div>
                            <label class="form-label fw-bold small text-uppercase text-secondary">Special Terms / Additional Clauses (Optional)</label>
                            <textarea name="special_terms" rows="3" class="form-control rounded-3 @error('special_terms') is-invalid @enderror" placeholder="Specific site allowances, vehicle provision, performance bonuses, or non-compete clauses...">{{ old('special_terms') }}</textarea>
                            @error('special_terms')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                </div>
            </div>

            {{-- Side Column: Compensation & Document Upload --}}
            <div class="col-lg-4">
                {{-- Compensation Card --}}
                <div class="card border-0 shadow-sm rounded-4 mb-4" style="border-top: 4px solid #10b981 !important;">
                    <div class="card-header bg-white border-0 py-3 px-4">
                        <h5 class="fw-bold text-dark mb-0">
                            <i class="fa-solid fa-money-bill-wave text-success me-2"></i>Remuneration
                        </h5>
                    </div>
                    <div class="card-body px-4 pb-4 pt-2">
                        {{-- Basic Salary --}}
                        <div class="mb-3">
                            <label class="form-label fw-bold small text-uppercase text-secondary">
                                Basic Monthly Salary <span class="text-danger">*</span>
                            </label>
                            <div class="input-group">
                                <span class="input-group-text bg-light border-end-0 fw-bold text-muted">ETB</span>
                                <input type="number" step="0.01" min="0" name="salary" class="form-control form-control-lg border-start-0 font-monospace fw-bold text-success @error('salary') is-invalid @enderror" value="{{ old('salary', '0.00') }}" required placeholder="0.00">
                                @error('salary')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        {{-- Benefits Amount --}}
                        <div class="mb-3">
                            <label class="form-label fw-bold small text-uppercase text-secondary">
                                Allowances &amp; Benefits (Monthly)
                            </label>
                            <div class="input-group">
                                <span class="input-group-text bg-light border-end-0 fw-bold text-muted">ETB</span>
                                <input type="number" step="0.01" min="0" name="benefits_amount" class="form-control border-start-0 font-monospace @error('benefits_amount') is-invalid @enderror" value="{{ old('benefits_amount', '0.00') }}" placeholder="0.00">
                                @error('benefits_amount')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="form-text small">Transport, housing, site, or communication allowances.</div>
                        </div>
                    </div>
                </div>

                {{-- Attachment Card --}}
                <div class="card border-0 shadow-sm rounded-4 mb-4">
                    <div class="card-header bg-white border-0 py-3 px-4">
                        <h5 class="fw-bold text-dark mb-0">
                            <i class="fa-solid fa-paperclip text-primary me-2"></i>Signed Document
                        </h5>
                    </div>
                    <div class="card-body px-4 pb-4 pt-2">
                        <div class="mb-3">
                            <label class="form-label fw-bold small text-uppercase text-secondary">Upload Contract File</label>
                            <input type="file" name="contract_file" class="form-control rounded-3 @error('contract_file') is-invalid @enderror" accept=".pdf,.doc,.docx">
                            <div class="form-text small">Accepted formats: PDF, DOC, DOCX. Max file size: 5MB.</div>
                            @error('contract_file')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                </div>

                {{-- Submit Action Box --}}
                <div class="card border-0 shadow-sm rounded-4 bg-light p-3">
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary btn-lg fw-bold rounded-pill shadow-sm">
                            <i class="fa-solid fa-check me-2"></i>Create Contract &amp; Save Draft
                        </button>
                        <a href="{{ route('contracts.index') }}" class="btn btn-outline-secondary rounded-pill">
                            Cancel
                        </a>
                    </div>
                    <div class="text-center mt-3">
                        <small class="text-muted">
                            <i class="fa-solid fa-circle-info me-1"></i>New contracts are created as <strong>Draft</strong>. You can review and submit them for multi-level management approval.
                        </small>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>
@endsection
