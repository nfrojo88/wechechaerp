@extends('layouts.app')

@section('title', 'Edit Employee — ' . $employee->full_name)

@section('content')
<div class="d-flex align-items-center justify-content-between mb-4">
    <div class="d-flex align-items-center">
        <a href="{{ route('employees.show', $employee) }}" class="btn btn-sm btn-outline-secondary me-3">
            <i class="fa-solid fa-arrow-left"></i>
        </a>
        <div>
            <h1 class="h3 mb-0">Edit Employee: {{ $employee->full_name }}</h1>
            <small class="text-muted"><i class="fa-solid fa-id-badge me-1"></i>{{ $employee->employee_code }} • {{ $employee->role_title ?? 'Employee' }} ({{ $employee->department ?? 'General' }})</small>
        </div>
    </div>
    <div>
        <a href="{{ route('employees.show', $employee) }}" class="btn btn-sm btn-outline-primary">
            <i class="fa-solid fa-eye me-1"></i>View Profile
        </a>
    </div>
</div>

@if(session('success'))
<div class="alert alert-success alert-dismissible fade show d-flex align-items-center gap-3 shadow-sm mb-4" role="alert">
    <i class="fa-solid fa-circle-check fa-2x text-success"></i>
    <div>
        <strong class="d-block fs-6">Update Successful!</strong>
        {{ session('success') }}
    </div>
    <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
@endif

<!-- Multi-Step Progress Indicator -->
<div class="row mb-4">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center">
            <div class="text-center flex-grow-1 cursor-pointer" onclick="goToStep(1)">
                <div class="step-indicator active" id="step-ind-1">
                    <span class="step-number">1</span>
                </div>
                <small class="text-muted d-block mt-1 fw-semibold">Basic Info</small>
            </div>
            <div class="flex-grow-1 step-line" style="height: 2px; background: #dee2e6; margin: 0 10px; margin-top: -15px;"></div>
            <div class="text-center flex-grow-1 cursor-pointer" onclick="goToStep(2)">
                <div class="step-indicator" id="step-ind-2">
                    <span class="step-number">2</span>
                </div>
                <small class="text-muted d-block mt-1 fw-semibold">Employment</small>
            </div>
            <div class="flex-grow-1 step-line" style="height: 2px; background: #dee2e6; margin: 0 10px; margin-top: -15px;"></div>
            <div class="text-center flex-grow-1 cursor-pointer" onclick="goToStep(3)">
                <div class="step-indicator" id="step-ind-3">
                    <span class="step-number">3</span>
                </div>
                <small class="text-muted d-block mt-1 fw-semibold">Salary</small>
            </div>
            <div class="flex-grow-1 step-line" style="height: 2px; background: #dee2e6; margin: 0 10px; margin-top: -15px;"></div>
            <div class="text-center flex-grow-1 cursor-pointer" onclick="goToStep(4)">
                <div class="step-indicator" id="step-ind-4">
                    <span class="step-number">4</span>
                </div>
                <small class="text-muted d-block mt-1 fw-semibold">Assets</small>
            </div>
            <div class="flex-grow-1 step-line" style="height: 2px; background: #dee2e6; margin: 0 10px; margin-top: -15px;"></div>
            <div class="text-center flex-grow-1 cursor-pointer" onclick="goToStep(5)">
                <div class="step-indicator" id="step-ind-5">
                    <span class="step-number">5</span>
                </div>
                <small class="text-muted d-block mt-1 fw-semibold">Education</small>
            </div>
            <div class="flex-grow-1 step-line" style="height: 2px; background: #dee2e6; margin: 0 10px; margin-top: -15px;"></div>
            <div class="text-center flex-grow-1 cursor-pointer" onclick="goToStep(6)">
                <div class="step-indicator" id="step-ind-6">
                    <span class="step-number">6</span>
                </div>
                <small class="text-muted d-block mt-1 fw-semibold">Experience</small>
            </div>
            <div class="flex-grow-1 step-line" style="height: 2px; background: #dee2e6; margin: 0 10px; margin-top: -15px;"></div>
            <div class="text-center flex-grow-1 cursor-pointer" onclick="goToStep(7)">
                <div class="step-indicator" id="step-ind-7">
                    <span class="step-number">7</span>
                </div>
                <small class="text-muted d-block mt-1 fw-semibold">Licenses</small>
            </div>
            <div class="flex-grow-1 step-line" style="height: 2px; background: #dee2e6; margin: 0 10px; margin-top: -15px;"></div>
            <div class="text-center flex-grow-1 cursor-pointer" onclick="goToStep(8)">
                <div class="step-indicator" id="step-ind-8">
                    <span class="step-number">8</span>
                </div>
                <small class="text-muted d-block mt-1 fw-semibold">Documents</small>
            </div>
        </div>
    </div>
</div>

<style>
.step-indicator {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: #e9ecef;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
    color: #6c757d;
    transition: all 0.3s ease;
    cursor: pointer;
}
.step-indicator.active {
    background: #0d6efd;
    color: white;
    box-shadow: 0 0 0 4px rgba(13,110,253,0.2);
}
.step-indicator.completed {
    background: #198754;
    color: white;
}
.cursor-pointer { cursor: pointer; }
.step-panel { display: none; }
.step-panel.active { display: block; }
</style>

<div class="card border-0 shadow-sm">
    <div class="card-body p-4">
        <form method="POST" action="{{ route('employees.update', $employee) }}" id="editEmployeeForm" enctype="multipart/form-data" novalidate>
            @csrf
            @method('PUT')

            {{-- Display Validation Errors --}}
            @if($errors->any())
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <h6 class="alert-heading"><i class="fa-solid fa-exclamation-triangle me-2"></i>Please fix the following errors:</h6>
                <ul class="mb-0">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            @endif

            {{-- STEP 1: Basic Information --}}
            <div class="step-panel active" id="step-panel-1">
                <div class="d-flex justify-content-between align-items-center mb-4 pb-2 border-bottom">
                    <h5 class="mb-0"><i class="fa-solid fa-user-circle text-primary me-2"></i>Step 1: Basic Information</h5>
                    <span class="badge bg-primary">Step 1 of 8</span>
                </div>
                
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Employee Code <span class="text-danger">*</span></label>
                        <input type="text" name="employee_code" class="form-control @error('employee_code') is-invalid @enderror"
                               value="{{ old('employee_code', $employee->employee_code) }}" required>
                        @error('employee_code')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Full Name <span class="text-danger">*</span></label>
                        <input type="text" name="full_name" class="form-control @error('full_name') is-invalid @enderror"
                               value="{{ old('full_name', $employee->full_name) }}" required>
                        @error('full_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">
                            <i class="fa-solid fa-fingerprint text-primary me-1"></i>ZKTeco Device User ID
                        </label>
                        <input type="text" name="device_user_id" class="form-control @error('device_user_id') is-invalid @enderror"
                               value="{{ old('device_user_id', $employee->device_user_id) }}"
                               placeholder="e.g. 1, 2, 17, 50">
                        <small class="text-muted">Numeric ID assigned to this employee in fingerprint machine.</small>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Primary Phone <span class="text-danger">*</span></label>
                        <input type="text" name="phone" class="form-control @error('phone') is-invalid @enderror"
                               value="{{ old('phone', $employee->phone) }}" placeholder="+251 911 234 567" required>
                        @error('phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" class="form-control" 
                               value="{{ old('email', $employee->email) }}" placeholder="employee@company.com">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Department <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <select name="department" class="form-select @error('department') is-invalid @enderror" required>
                                <option value="">-- Select Department --</option>
                                @foreach($departments as $dept)
                                    <option value="{{ $dept->name }}" {{ old('department', $employee->department) == $dept->name ? 'selected' : '' }}>
                                        {{ $dept->name }}
                                    </option>
                                @endforeach
                            </select>
                            <a href="{{ route('departments.index') }}" target="_blank" class="btn btn-outline-secondary">
                                <i class="fa-solid fa-cog me-1"></i>Manage
                            </a>
                        </div>
                        @error('department')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Role / Job Title</label>
                        <input type="text" name="role_title" class="form-control" 
                               value="{{ old('role_title', $employee->role_title) }}" placeholder="e.g. Site Engineer">
                    </div>

                    {{-- National ID Fields --}}
                    <div class="col-12"><hr class="my-2"><small class="text-muted fw-bold"><i class="fa-solid fa-id-card me-1"></i>National / Government ID</small></div>
                    <div class="col-md-6">
                        <label class="form-label">National ID Number <small class="text-muted">(Kebele ID / Fayda ID / Passport)</small></label>
                        <input type="text" name="national_id_number" class="form-control @error('national_id_number') is-invalid @enderror"
                               value="{{ old('national_id_number', $employee->national_id_number) }}" placeholder="e.g. 1234/12/5678 or ETH-00000001">
                        @error('national_id_number')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">
                            <i class="fa-solid fa-camera text-primary me-1"></i>National ID Card / Scan / Photo
                            <small class="text-muted fw-normal d-block">(PDF, PNG, JPG, WEBP – Max 10MB)</small>
                        </label>
                        @if($employee->national_id_card)
                            <div class="d-flex align-items-center gap-2 mb-2 p-2 bg-light rounded border">
                                <i class="fa-solid fa-id-card text-success"></i>
                                <small class="text-muted">National ID on file</small>
                                <a href="{{ $employee->national_id_card_url }}" target="_blank" class="btn btn-xs btn-outline-primary ms-auto">
                                    <i class="fa-solid fa-eye me-1"></i>View Current Card
                                </a>
                            </div>
                        @endif
                        <input type="file" name="national_id_card" id="national_id_card_input"
                               class="form-control @error('national_id_card') is-invalid @enderror"
                               accept="application/pdf,image/jpeg,image/png,image/jpg,image/webp">
                        @error('national_id_card')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
            </div>

            {{-- STEP 2: Employment Details --}}
            <div class="step-panel" id="step-panel-2">
                <div class="d-flex justify-content-between align-items-center mb-4 pb-2 border-bottom">
                    <h5 class="mb-0"><i class="fa-solid fa-briefcase text-success me-2"></i>Step 2: Employment Details</h5>
                    <span class="badge bg-success">Step 2 of 8</span>
                </div>
                
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Employment Type <span class="text-danger">*</span></label>
                        @php $empType = old('employment_type', $employee->employment_type); @endphp
                        <select name="employment_type" class="form-select" required>
                            <option value="permanent" {{ $empType == 'permanent' ? 'selected' : '' }}>Permanent</option>
                            <option value="contract"  {{ $empType == 'contract'  ? 'selected' : '' }}>Contract</option>
                            <option value="daily"     {{ $empType == 'daily'     ? 'selected' : '' }}>Daily Worker</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Contract Start Date <span class="text-danger">*</span></label>
                        <input type="date" name="date_of_joining" class="form-control @error('date_of_joining') is-invalid @enderror"
                               value="{{ old('date_of_joining', $employee->date_of_joining ? $employee->date_of_joining->format('Y-m-d') : '') }}" required>
                        @error('date_of_joining')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Status <span class="text-danger">*</span></label>
                        @php $empStatus = old('status', $employee->status); @endphp
                        <select name="status" class="form-select" required>
                            <option value="active"     {{ $empStatus == 'active'     ? 'selected' : '' }}>Active</option>
                            <option value="suspended"  {{ $empStatus == 'suspended'  ? 'selected' : '' }}>Suspended</option>
                            <option value="terminated" {{ $empStatus == 'terminated' ? 'selected' : '' }}>Terminated</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Assigned Project</label>
                        <select name="project_id" class="form-select">
                            <option value="">— HQ / Unassigned —</option>
                            @foreach($projects as $p)
                                <option value="{{ $p->id }}" {{ old('project_id', $employee->project_id) == $p->id ? 'selected' : '' }}>
                                    {{ $p->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-6">
                        @php $site = old('site_assignment', $employee->site_assignment ?? ''); @endphp
                        <label class="form-label">
                            Site Assignment
                            <span class="badge bg-info text-dark ms-1" id="remote-edit-attendance-badge" style="{{ ($site ?? '') === 'Remote' ? '' : 'display:none;' }}">
                                <i class="fa-solid fa-house-laptop me-1"></i>No Attendance Tracking
                            </span>
                        </label>
                        <select name="site_assignment" class="form-select" onchange="document.getElementById('remote-edit-attendance-badge').style.display = (this.value === 'Remote') ? 'inline-block' : 'none';">
                            <option value="">-- No Specific Site --</option>
                            <option value="Head Office"  {{ in_array($site ?? '', ['Head Office', 'HQ']) ? 'selected' : '' }}>Head Office</option>
                            <option value="Project Site" {{ in_array($site ?? '', ['Project Site', 'Site_A', 'Site_B']) ? 'selected' : '' }}>Project Site</option>
                            <option value="Workshop"     {{ ($site ?? '') === 'Workshop' ? 'selected' : '' }}>Workshop</option>
                            <option value="Remote"       {{ ($site ?? '') === 'Remote' ? 'selected' : '' }}>Remote (Exempt from Attendance Tracking)</option>
                        </select>
                        <div class="form-text text-muted">
                            Remote workers are exempt from daily biometric / attendance logging.
                        </div>
                    </div>
                </div>
            </div>

            {{-- STEP 3: Salary & Bank Info --}}
            <div class="step-panel" id="step-panel-3">
                <div class="d-flex justify-content-between align-items-center mb-4 pb-2 border-bottom">
                    <h5 class="mb-0"><i class="fa-solid fa-money-bill-wave text-warning me-2"></i>Step 3: Salary & Bank Information</h5>
                    <span class="badge bg-warning text-dark">Step 3 of 6</span>
                </div>
                
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Monthly Base Salary (ETB) <span class="text-danger">*</span></label>
                        <input type="number" step="0.01" min="0" name="basic_salary" class="form-control @error('basic_salary') is-invalid @enderror"
                               value="{{ old('basic_salary', $employee->basic_salary) }}" required>
                        @error('basic_salary')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Contract Type</label>
                        @php $ct = old('contract_type', $employee->contract_type ?? 'Full-Time'); @endphp
                        <select name="contract_type" class="form-select">
                            <option value="Full-Time"  {{ $ct == 'Full-Time'  ? 'selected' : '' }}>Full-Time</option>
                            <option value="Part-Time"  {{ $ct == 'Part-Time'  ? 'selected' : '' }}>Part-Time</option>
                            <option value="Temporary"  {{ $ct == 'Temporary'  ? 'selected' : '' }}>Temporary</option>
                        </select>
                    </div>

                    {{-- Allowances --}}
                    <div class="col-12">
                        <div class="alert alert-light border mb-0">
                            <h6 class="mb-3 fw-bold"><i class="fa-solid fa-coins text-success me-2"></i>Monthly Allowances</h6>
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label class="form-label">Transport Allowance (ETB)</label>
                                    <input type="number" step="0.01" min="0" name="transport_allowance" class="form-control"
                                           value="{{ old('transport_allowance', $employee->transport_allowance ?? 0) }}">
                                    <small class="text-muted">&lt; 2,200 ETB is tax-free</small>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">House Allowance (ETB)</label>
                                    <input type="number" step="0.01" min="0" name="house_allowance" class="form-control"
                                           value="{{ old('house_allowance', $employee->house_allowance ?? 0) }}">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Position Allowance (ETB)</label>
                                    <input type="number" step="0.01" min="0" name="position_allowance" class="form-control"
                                           value="{{ old('position_allowance', $employee->position_allowance ?? 0) }}">
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Bank Details --}}
                    <div class="col-12">
                        <div class="alert alert-light border mb-0">
                            <h6 class="mb-3 fw-bold"><i class="fa-solid fa-building-columns text-info me-2"></i>Bank Account Information</h6>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Bank Name</label>
                                    <input type="text" name="bank_name" class="form-control"
                                           value="{{ old('bank_name', $employee->bank_name ?? '') }}" placeholder="Commercial Bank of Ethiopia">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Account Number</label>
                                    <input type="text" name="account_number" class="form-control"
                                           value="{{ old('account_number', $employee->account_number ?? '') }}" placeholder="1000123456789">
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Guarantee & Guarantor Person Details (Accepts Two Guarantors) --}}
                    <div class="col-12">
                        <div class="card border border-warning bg-light shadow-sm">
                            <div class="card-header bg-warning bg-opacity-10 border-warning-subtle py-2 px-3 d-flex justify-content-between align-items-center">
                                <h6 class="fw-bold mb-0 text-dark">
                                    <i class="fa-solid fa-shield-halved text-warning me-2"></i>Guarantee & Guarantor Person Information (Accepts 2 Guarantors)
                                </h6>
                                <span class="badge bg-warning text-dark"><i class="fa-solid fa-users me-1"></i>2 Guarantor Profiles</span>
                            </div>
                            <div class="card-body p-3">
                                {{-- ── GUARANTOR 1 ────────────────────────────── --}}
                                <div class="p-3 bg-white border rounded mb-3">
                                    <div class="d-flex align-items-center mb-2 pb-1 border-bottom">
                                        <span class="badge bg-primary me-2">Guarantor #1</span>
                                        <h6 class="fw-bold mb-0 text-dark">Primary Guarantor Details</h6>
                                    </div>
                                    <div class="row g-3">
                                        <div class="col-md-4">
                                            <label class="form-label fw-semibold small">Guarantor 1 Full Name</label>
                                            <input type="text" name="guarantor_name" class="form-control form-control-sm"
                                                   value="{{ old('guarantor_name', $employee->guarantor_name ?? '') }}" placeholder="e.g. Abebe Kebede">
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label fw-semibold small">Guarantor 1 National / Kebele ID</label>
                                            <input type="text" name="guarantor_id_number" class="form-control form-control-sm"
                                                   value="{{ old('guarantor_id_number', $employee->guarantor_id_number ?? '') }}" placeholder="e.g. ET-NAT-987654">
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label fw-semibold small">Guarantor 1 Phone Number</label>
                                            <input type="text" name="guarantor_phone" class="form-control form-control-sm"
                                                   value="{{ old('guarantor_phone', $employee->guarantor_phone ?? '') }}" placeholder="e.g. +251 91 123 4567">
                                        </div>

                                        {{-- Guarantor 1 National ID Card Upload --}}
                                        <div class="col-md-6">
                                            <div class="p-2 bg-light border rounded">
                                                <label class="form-label fw-semibold mb-1 d-block small">
                                                    <i class="fa-solid fa-id-card text-primary me-1"></i>Guarantor 1 ID Document / Photo
                                                </label>
                                                @if($employee->guarantor_id_card)
                                                    <div class="d-flex align-items-center gap-2 mb-2 p-2 bg-white rounded border">
                                                        <i class="fa-solid fa-file-image text-primary"></i>
                                                        <small class="text-muted text-truncate">Guarantor 1 ID on file</small>
                                                        <a href="{{ $employee->guarantor_id_card_url }}" target="_blank" class="btn btn-xs btn-outline-primary ms-auto">
                                                            <i class="fa-solid fa-eye me-1"></i>View Current
                                                        </a>
                                                    </div>
                                                @endif
                                                <small class="text-muted d-block mb-1" style="font-size:0.75rem;">{{ $employee->guarantor_id_card ? 'Upload New to Replace Existing:' : 'Upload Guarantor 1 ID (PDF, PNG, JPG - Max 15MB):' }}</small>
                                                <input type="file" name="guarantor_id_card" class="form-control form-control-sm" accept="application/pdf,image/jpeg,image/png,image/jpg,image/webp">
                                            </div>
                                        </div>

                                        {{-- Guarantee Letter 1 Upload --}}
                                        <div class="col-md-6">
                                            <div class="p-2 bg-light border rounded">
                                                <label class="form-label fw-semibold mb-1 d-block small">
                                                    <i class="fa-solid fa-file-shield text-warning me-1"></i>Official Guarantee Letter #1
                                                </label>
                                                @if($employee->guarantee_letter)
                                                    <div class="d-flex align-items-center gap-2 mb-2 p-2 bg-white rounded border">
                                                        <i class="fa-solid fa-file-pdf text-danger"></i>
                                                        <div>
                                                            <small class="fw-bold d-block">Guarantee Letter #1 on file</small>
                                                            <small class="text-muted" style="font-size:0.72rem;">Submitted: {{ $employee->guarantee_letter_submitted_at ? $employee->guarantee_letter_submitted_at->format('d M Y') : 'Active' }}</small>
                                                        </div>
                                                        <a href="{{ $employee->guarantee_letter_url }}" target="_blank" class="btn btn-xs btn-outline-warning ms-auto">
                                                            <i class="fa-solid fa-external-link me-1"></i>View
                                                        </a>
                                                    </div>
                                                @endif
                                                <small class="text-muted d-block mb-1" style="font-size:0.75rem;">{{ $employee->guarantee_letter ? 'Upload New to Replace Existing:' : 'Upload Guarantee Letter #1 (PDF, PNG, JPG - Max 15MB):' }}</small>
                                                <input type="file" name="guarantee_letter" class="form-control form-control-sm" accept="application/pdf,image/jpeg,image/png,image/jpg,image/webp">
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                {{-- ── GUARANTOR 2 ────────────────────────────── --}}
                                <div class="p-3 bg-white border rounded">
                                    <div class="d-flex align-items-center mb-2 pb-1 border-bottom">
                                        <span class="badge bg-secondary me-2">Guarantor #2</span>
                                        <h6 class="fw-bold mb-0 text-dark">Second Guarantor Details (Optional)</h6>
                                    </div>
                                    <div class="row g-3">
                                        <div class="col-md-4">
                                            <label class="form-label fw-semibold small">Guarantor 2 Full Name</label>
                                            <input type="text" name="guarantor_2_name" class="form-control form-control-sm"
                                                   value="{{ old('guarantor_2_name', $employee->guarantor_2_name ?? '') }}" placeholder="e.g. Almaz Tesfaye">
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label fw-semibold small">Guarantor 2 National / Kebele ID</label>
                                            <input type="text" name="guarantor_2_id_number" class="form-control form-control-sm"
                                                   value="{{ old('guarantor_2_id_number', $employee->guarantor_2_id_number ?? '') }}" placeholder="e.g. ET-NAT-123456">
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label fw-semibold small">Guarantor 2 Phone Number</label>
                                            <input type="text" name="guarantor_2_phone" class="form-control form-control-sm"
                                                   value="{{ old('guarantor_2_phone', $employee->guarantor_2_phone ?? '') }}" placeholder="e.g. +251 92 987 6543">
                                        </div>

                                        {{-- Guarantor 2 National ID Card Upload --}}
                                        <div class="col-md-6">
                                            <div class="p-2 bg-light border rounded">
                                                <label class="form-label fw-semibold mb-1 d-block small">
                                                    <i class="fa-solid fa-id-card text-primary me-1"></i>Guarantor 2 ID Document / Photo
                                                </label>
                                                @if($employee->guarantor_2_id_card)
                                                    <div class="d-flex align-items-center gap-2 mb-2 p-2 bg-white rounded border">
                                                        <i class="fa-solid fa-file-image text-primary"></i>
                                                        <small class="text-muted text-truncate">Guarantor 2 ID on file</small>
                                                        <a href="{{ $employee->guarantor_2_id_card_url }}" target="_blank" class="btn btn-xs btn-outline-primary ms-auto">
                                                            <i class="fa-solid fa-eye me-1"></i>View Current
                                                        </a>
                                                    </div>
                                                @endif
                                                <small class="text-muted d-block mb-1" style="font-size:0.75rem;">{{ $employee->guarantor_2_id_card ? 'Upload New to Replace Existing:' : 'Upload Guarantor 2 ID (PDF, PNG, JPG - Max 15MB):' }}</small>
                                                <input type="file" name="guarantor_2_id_card" class="form-control form-control-sm" accept="application/pdf,image/jpeg,image/png,image/jpg,image/webp">
                                            </div>
                                        </div>

                                        {{-- Guarantee Letter 2 Upload --}}
                                        <div class="col-md-6">
                                            <div class="p-2 bg-light border rounded">
                                                <label class="form-label fw-semibold mb-1 d-block small">
                                                    <i class="fa-solid fa-file-shield text-warning me-1"></i>Official Guarantee Letter #2
                                                </label>
                                                @if($employee->guarantee_letter_2)
                                                    <div class="d-flex align-items-center gap-2 mb-2 p-2 bg-white rounded border">
                                                        <i class="fa-solid fa-file-pdf text-danger"></i>
                                                        <div>
                                                            <small class="fw-bold d-block">Guarantee Letter #2 on file</small>
                                                            <small class="text-muted" style="font-size:0.72rem;">Uploaded Document</small>
                                                        </div>
                                                        <a href="{{ $employee->guarantee_letter_2_url }}" target="_blank" class="btn btn-xs btn-outline-warning ms-auto">
                                                            <i class="fa-solid fa-external-link me-1"></i>View
                                                        </a>
                                                    </div>
                                                @endif
                                                <small class="text-muted d-block mb-1" style="font-size:0.75rem;">{{ $employee->guarantee_letter_2 ? 'Upload New to Replace Existing:' : 'Upload Guarantee Letter #2 (PDF, PNG, JPG - Max 15MB):' }}</small>
                                                <input type="file" name="guarantee_letter_2" class="form-control form-control-sm" accept="application/pdf,image/jpeg,image/png,image/jpg,image/webp">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- STEP 4: Fixed Assets & Equipment --}}
            <div class="step-panel" id="step-panel-4">
                <div class="d-flex justify-content-between align-items-center mb-3 pb-2 border-bottom">
                    <div>
                        <h5 class="mb-1"><i class="fa-solid fa-truck-monster text-warning me-2"></i>Step 4: Assigned Fixed Assets & Equipment</h5>
                        <p class="text-muted small mb-0">Select equipment from Store inventory or update currently assigned units for this employee.</p>
                    </div>
                    <span class="badge bg-warning text-dark">Step 4 of 8</span>
                </div>

                {{-- Category Filter Pills --}}
                @php
                    $availableCategories = $fixedAssetUnits->map(function($u) {
                        return $u->parentAsset->category ?? 'General';
                    })->unique()->values();
                @endphp
                <div class="card border-0 bg-light p-2 mb-3 shadow-sm">
                    <div class="d-flex flex-wrap align-items-center gap-2">
                        <small class="text-muted fw-bold me-1"><i class="fa-solid fa-filter me-1"></i>Filter by Category:</small>
                        <button type="button" class="btn btn-xs btn-dark rounded-pill px-3 py-1 btn-category-filter active" data-category="ALL" onclick="filterByCategory('ALL', this)">
                            All ({{ $fixedAssetUnits->count() }})
                        </button>
                        @foreach($availableCategories as $cat)
                            @php
                                $catCount = $fixedAssetUnits->filter(fn($u) => ($u->parentAsset->category ?? 'General') === $cat)->count();
                            @endphp
                            <button type="button" class="btn btn-xs btn-outline-secondary rounded-pill px-3 py-1 btn-category-filter" data-category="{{ $cat }}" onclick="filterByCategory('{{ $cat }}', this)">
                                {{ $cat }} ({{ $catCount }})
                            </button>
                        @endforeach
                    </div>
                </div>

                <div id="assetsContainer">
                    @php
                        $assignedUnitIds = old('fixed_asset_units', $employee->assignedFixedAssets->pluck('id')->toArray());
                        if (empty($assignedUnitIds)) {
                            $assignedUnitIds = [''];
                        }
                    @endphp
                    @foreach($assignedUnitIds as $index => $selectedUnitId)
                    <div class="asset-entry border rounded p-3 mb-3 bg-light" data-index="{{ $index }}">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <h6 class="mb-0 fw-bold"><i class="fa-solid fa-barcode text-primary me-2"></i>Assigned Asset Unit #{{ $index + 1 }}</h6>
                            <button type="button" class="btn btn-sm btn-outline-danger remove-asset {{ count($assignedUnitIds) > 1 ? '' : 'd-none' }}" onclick="removeAsset({{ $index }})">
                                <i class="fa-solid fa-trash me-1"></i>Remove
                            </button>
                        </div>

                        {{-- Per-Row Live Search Input --}}
                        <div class="mb-2">
                            <div class="input-group input-group-sm">
                                <span class="input-group-text bg-white border-end-0"><i class="fa-solid fa-magnifying-glass text-primary"></i></span>
                                <input type="text" class="form-control form-control-sm border-start-0 asset-row-search" 
                                       placeholder="🔍 Type unit code (e.g. COMP-1), serial, plate, or brand to search..." 
                                       oninput="filterAssetOptions(this)">
                                <button type="button" class="btn btn-outline-secondary btn-sm" onclick="clearRowSearch(this)" title="Clear Search">
                                    <i class="fa-solid fa-xmark"></i>
                                </button>
                            </div>
                        </div>

                        <div class="row g-2">
                            <div class="col-12">
                                <label class="form-label small fw-bold mb-1">
                                    Select Fixed Asset Unit <span class="badge bg-success ms-1">Available / Assigned</span>
                                </label>
                                <select name="fixed_asset_units[]" class="form-select asset-select font-monospace" onchange="onAssetUnitSelected(this)">
                                    <option value="">-- Choose an Available Asset Unit --</option>
                                    @foreach($fixedAssetUnits as $unit)
                                        @php
                                            $detailStr = $unit->plate_number ? 'Plate: ' . $unit->plate_number : ($unit->serial_number ? 'SN: ' . $unit->serial_number : ($unit->brand ? $unit->brand . ' ' . $unit->model : 'In Store'));
                                            $pName = $unit->parentAsset->name ?? 'Asset';
                                            $pCat = $unit->parentAsset->category ?? 'General';
                                            $searchKeywords = strtolower("{$unit->unit_code} {$pName} {$pCat} {$detailStr} {$unit->brand} {$unit->model} {$unit->serial_number} {$unit->plate_number}");
                                            $isCurrentlyAssigned = ($unit->assigned_to_employee_id == $employee->id);
                                        @endphp
                                        <option value="{{ $unit->id }}" 
                                                data-category="{{ $pCat }}" 
                                                data-search="{{ $searchKeywords }}"
                                                data-unit-code="{{ $unit->unit_code }}"
                                                data-asset-name="{{ $pName }}"
                                                data-specs="{{ $detailStr }}"
                                                data-condition="{{ $unit->condition }}"
                                                {{ (string)$selectedUnitId === (string)$unit->id ? 'selected' : '' }}>
                                            {{ $unit->unit_code }} — {{ $pName }} ({{ $detailStr }}) • [{{ $pCat }}] {{ $isCurrentlyAssigned ? '★ (Currently Assigned)' : '' }}
                                        </option>
                                    @endforeach
                                </select>
                                <div class="asset-match-count small text-muted mt-1" style="font-size: 0.75rem;"></div>
                            </div>
                        </div>

                        {{-- Selected Unit Live Details Badge --}}
                        <div class="selected-asset-details mt-2 p-2 bg-white rounded border d-none small">
                            <div class="d-flex align-items-center justify-content-between">
                                <div>
                                    <span class="badge bg-dark font-monospace fs-6 me-2 selected-unit-code"></span>
                                    <strong class="text-dark selected-asset-name"></strong>
                                    <span class="text-muted ms-2 selected-asset-specs"></span>
                                </div>
                                <div>
                                    <span class="badge bg-success"><i class="fa-solid fa-check me-1"></i>Assigned</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    @endforeach
                </div>

                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-outline-primary btn-sm fw-semibold" onclick="addAsset()">
                        <i class="fa-solid fa-plus me-1"></i> Assign Another Asset
                    </button>
                </div>

                {{-- Asset Handover Document Upload --}}
                <div class="mt-4 pt-3 border-top">
                    <label class="form-label fw-semibold">
                        <i class="fa-solid fa-file-signature text-success me-1"></i>Asset Handover Receipt / Condition Photo
                        <small class="text-muted fw-normal d-block">(PDF, PNG, JPG, WEBP – Max 10MB) — Upload signed handover receipt or condition inspection photo.</small>
                    </label>
                    @if($employee->asset_handover_document)
                        <div class="d-flex align-items-center gap-2 mb-2 p-2 bg-white rounded border">
                            <i class="fa-solid fa-file-signature text-success"></i>
                            <small class="text-muted">Handover document on file</small>
                            <a href="{{ $employee->asset_handover_document_url }}" target="_blank" class="btn btn-xs btn-outline-success ms-auto">
                                <i class="fa-solid fa-eye me-1"></i>View Current Handover Receipt
                            </a>
                        </div>
                    @endif
                    <input type="file" name="asset_handover_document" id="asset_handover_input"
                           class="form-control @error('asset_handover_document') is-invalid @enderror"
                           accept="application/pdf,image/jpeg,image/png,image/jpg,image/webp">
                    @error('asset_handover_document')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
            </div>

            {{-- STEP 5: Educational Background --}}
            <div class="step-panel" id="step-panel-5">
                <div class="d-flex justify-content-between align-items-center mb-3 pb-2 border-bottom">
                    <div>
                        <h5 class="mb-1"><i class="fa-solid fa-graduation-cap text-primary me-2"></i>Step 5: Educational Background</h5>
                        <p class="text-muted small mb-0">Update educational degrees, diplomas, certificates, and qualification photos.</p>
                    </div>
                    <span class="badge bg-primary">Step 5 of 8</span>
                </div>

                <div id="educationContainer">
                    @php
                        $educations = old('education', $employee->education->toArray());
                        if (empty($educations)) {
                            $educations = [['degree_level' => '', 'field_of_study' => '', 'institution_name' => '']];
                        }
                    @endphp
                    @foreach($educations as $index => $edu)
                    <div class="education-entry border rounded p-3 mb-3 bg-light" data-index="{{ $index }}">
                        @if(!empty($edu['id']))
                            <input type="hidden" name="education[{{ $index }}][id]" value="{{ $edu['id'] }}">
                        @endif
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h6 class="mb-0 fw-bold"><i class="fa-solid fa-book me-2 text-primary"></i>Education Record #{{ $index + 1 }}</h6>
                            <button type="button" class="btn btn-sm btn-outline-danger remove-education {{ count($educations) > 1 ? '' : 'd-none' }}" onclick="removeEducation({{ $index }})">
                                <i class="fa-solid fa-trash me-1"></i>Remove
                            </button>
                        </div>
                        
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Degree Level</label>
                                @php $deg = $edu['degree_level'] ?? ''; @endphp
                                <select name="education[{{ $index }}][degree_level]" class="form-select">
                                    <option value="">Select Degree</option>
                                    <option value="PhD"         {{ $deg == 'PhD' ? 'selected' : '' }}>PhD / Doctorate</option>
                                    <option value="Master"      {{ $deg == 'Master' ? 'selected' : '' }}>Master's Degree</option>
                                    <option value="Bachelor"    {{ $deg == 'Bachelor' ? 'selected' : '' }}>Bachelor's Degree</option>
                                    <option value="Diploma"     {{ $deg == 'Diploma' ? 'selected' : '' }}>Diploma</option>
                                    <option value="Certificate" {{ $deg == 'Certificate' ? 'selected' : '' }}>Certificate</option>
                                    <option value="High School" {{ $deg == 'High School' ? 'selected' : '' }}>High School</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Field of Study</label>
                                <input type="text" name="education[{{ $index }}][field_of_study]" class="form-control" 
                                       value="{{ $edu['field_of_study'] ?? '' }}" placeholder="e.g., Civil Engineering">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Institution Name</label>
                                <input type="text" name="education[{{ $index }}][institution_name]" class="form-control" 
                                       value="{{ $edu['institution_name'] ?? '' }}" placeholder="e.g., Addis Ababa University">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Location</label>
                                <input type="text" name="education[{{ $index }}][location]" class="form-control" 
                                       value="{{ $edu['location'] ?? '' }}" placeholder="e.g., Addis Ababa, Ethiopia">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Start Date</label>
                                <input type="date" name="education[{{ $index }}][start_date]" class="form-control"
                                       value="{{ isset($edu['start_date']) ? (is_string($edu['start_date']) ? substr($edu['start_date'], 0, 10) : $edu['start_date']) : '' }}">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">End Date / Expected</label>
                                <input type="date" name="education[{{ $index }}][end_date]" class="form-control"
                                       value="{{ isset($edu['end_date']) ? (is_string($edu['end_date']) ? substr($edu['end_date'], 0, 10) : $edu['end_date']) : '' }}">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Grade / GPA</label>
                                <input type="text" name="education[{{ $index }}][grade_gpa]" class="form-control" 
                                       value="{{ $edu['grade_gpa'] ?? '' }}" placeholder="e.g., 3.8/4.0">
                            </div>
                            <div class="col-12">
                                <label class="form-label">Description / Achievements</label>
                                <textarea name="education[{{ $index }}][description]" class="form-control" rows="2" 
                                          placeholder="Optional: honors, thesis title, etc.">{{ $edu['description'] ?? '' }}</textarea>
                            </div>
                            <div class="col-12">
                                @if(!empty($edu['certificate_photo']))
                                    <div class="d-flex align-items-center gap-2 mb-2 p-2 bg-white rounded border">
                                        <i class="fa-solid fa-file-image text-primary"></i>
                                        <small class="text-muted">Certificate on file</small>
                                        <a href="{{ uploaded_asset($edu['certificate_photo']) }}" target="_blank" class="btn btn-xs btn-outline-primary ms-auto">
                                            <i class="fa-solid fa-eye me-1"></i>View Document
                                        </a>
                                    </div>
                                @endif
                                <label class="form-label small fw-semibold">Upload / Replace Certificate Photo <small class="text-muted">(Max 10MB)</small></label>
                                <input type="file" name="education[{{ $index }}][certificate_photo]" class="form-control" accept="image/jpeg,image/png,image/jpg,application/pdf">
                            </div>
                        </div>
                    </div>
                    @endforeach
                </div>

                <button type="button" class="btn btn-outline-primary btn-sm fw-semibold" onclick="addEducation()">
                    <i class="fa-solid fa-plus me-1"></i> Add Another Education Record
                </button>
            </div>

            {{-- STEP 6: Work Experience --}}
            <div class="step-panel" id="step-panel-6">
                <div class="d-flex justify-content-between align-items-center mb-3 pb-2 border-bottom">
                    <div>
                        <h5 class="mb-1"><i class="fa-solid fa-briefcase text-success me-2"></i>Step 6: Work Experience</h5>
                        <p class="text-muted small mb-0">Update previous jobs, employment history, and recommendation letters.</p>
                    </div>
                    <span class="badge bg-success">Step 6 of 8</span>
                </div>

                <div id="experienceContainer">
                    @php
                        $experiences = old('experience', $employee->experience->toArray());
                        if (empty($experiences)) {
                            $experiences = [['job_title' => '', 'company_name' => '', 'start_date' => '']];
                        }
                    @endphp
                    @foreach($experiences as $index => $exp)
                    <div class="experience-entry border rounded p-3 mb-3 bg-light" data-index="{{ $index }}">
                        @if(!empty($exp['id']))
                            <input type="hidden" name="experience[{{ $index }}][id]" value="{{ $exp['id'] }}">
                        @endif
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h6 class="mb-0 fw-bold"><i class="fa-solid fa-building text-success me-2"></i>Experience Record #{{ $index + 1 }}</h6>
                            <button type="button" class="btn btn-sm btn-outline-danger remove-experience {{ count($experiences) > 1 ? '' : 'd-none' }}" onclick="removeExperience({{ $index }})">
                                <i class="fa-solid fa-trash me-1"></i>Remove
                            </button>
                        </div>
                        
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Job Title</label>
                                <input type="text" name="experience[{{ $index }}][job_title]" class="form-control" 
                                       value="{{ $exp['job_title'] ?? '' }}" placeholder="e.g., Site Engineer, Project Manager">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Company Name</label>
                                <input type="text" name="experience[{{ $index }}][company_name]" class="form-control" 
                                       value="{{ $exp['company_name'] ?? '' }}" placeholder="e.g., ABC Construction Plc">
                            </div>
                            <div class="col-md-12">
                                <label class="form-label fw-semibold">Location</label>
                                <input type="text" name="experience[{{ $index }}][location]" class="form-control" 
                                       value="{{ $exp['location'] ?? '' }}" placeholder="e.g., Addis Ababa, Ethiopia">
                            </div>
                            <div class="col-md-5">
                                <label class="form-label fw-semibold">Start Date</label>
                                <input type="date" name="experience[{{ $index }}][start_date]" class="form-control"
                                       value="{{ isset($exp['start_date']) ? (is_string($exp['start_date']) ? substr($exp['start_date'], 0, 10) : $exp['start_date']) : '' }}">
                            </div>
                            <div class="col-md-5">
                                <label class="form-label fw-semibold">End Date</label>
                                <input type="date" name="experience[{{ $index }}][end_date]" class="form-control" id="exp_end_date_{{ $index }}"
                                       value="{{ isset($exp['end_date']) ? (is_string($exp['end_date']) ? substr($exp['end_date'], 0, 10) : $exp['end_date']) : '' }}"
                                       {{ !empty($exp['is_current']) ? 'disabled' : '' }}>
                            </div>
                            <div class="col-md-2 d-flex align-items-end">
                                <div class="form-check mb-2">
                                    <input type="checkbox" name="experience[{{ $index }}][is_current]" class="form-check-input" 
                                           id="is_current_{{ $index }}" value="1" {{ !empty($exp['is_current']) ? 'checked' : '' }} onchange="toggleEndDate({{ $index }})">
                                    <label class="form-check-label small fw-semibold" for="is_current_{{ $index }}">Current</label>
                                </div>
                            </div>
                            <div class="col-12">
                                <label class="form-label fw-semibold">Key Responsibilities</label>
                                <textarea name="experience[{{ $index }}][responsibilities]" class="form-control" rows="3" 
                                          placeholder="Describe main duties, projects handled, and achievements...">{{ $exp['responsibilities'] ?? '' }}</textarea>
                            </div>
                            
                            <div class="col-12">
                                @if(!empty($exp['experience_letter']))
                                    <div class="d-flex align-items-center gap-2 mb-2 p-2 bg-white rounded border">
                                        <i class="fa-solid fa-file-lines text-primary"></i>
                                        <small class="text-muted text-truncate">Experience Certificate on file</small>
                                        <a href="{{ uploaded_asset($exp['experience_letter']) }}" target="_blank" class="btn btn-xs btn-outline-primary ms-auto">
                                            <i class="fa-solid fa-eye me-1"></i>View Document
                                        </a>
                                    </div>
                                @endif
                                <label class="form-label small fw-semibold">
                                    <i class="fa-solid fa-file-lines text-primary me-1"></i>Upload / Replace Experience Certificate
                                    <small class="text-muted fw-normal d-block">(PDF, PNG, JPG, WEBP - Max 15MB)</small>
                                </label>
                                <input type="file" name="experience[{{ $index }}][experience_letter]" class="form-control" accept="application/pdf,image/jpeg,image/png,image/jpg,image/webp">
                            </div>
                        </div>
                    </div>
                    @endforeach
                </div>

                <button type="button" class="btn btn-outline-success btn-sm fw-semibold" onclick="addExperience()">
                    <i class="fa-solid fa-plus me-1"></i> Add Another Experience Record
                </button>
            </div>

            {{-- STEP 7: Professional Licenses & Certifications --}}
            <div class="step-panel" id="step-panel-7">
                <div class="d-flex justify-content-between align-items-center mb-3 pb-2 border-bottom">
                    <div>
                        <h5 class="mb-1"><i class="fa-solid fa-id-card-clip text-warning me-2"></i>Step 7: Professional Licenses & Certifications</h5>
                        <p class="text-muted small mb-0">Manage practicing licenses, engineering certifications, professional registrations, and driving licenses.</p>
                    </div>
                    <span class="badge bg-warning text-dark">Step 7 of 8</span>
                </div>

                <div id="licensesContainer">
                    @php
                        $licenses = old('licenses', $employee->licenses ? $employee->licenses->toArray() : []);
                        if (empty($licenses)) {
                            // Check fallback legacy licenses in experience
                            $legacyLics = $employee->experience->filter(fn($e) => !empty($e->license_number) || !empty($e->license_document));
                            if ($legacyLics->isNotEmpty()) {
                                $licenses = $legacyLics->map(fn($e) => [
                                    'license_name'         => 'Professional License (' . $e->job_title . ')',
                                    'issuing_organization' => $e->company_name ?? '',
                                    'license_number'       => $e->license_number ?? '',
                                    'expiry_date'          => $e->license_expiry ? (is_string($e->license_expiry) ? substr($e->license_expiry, 0, 10) : $e->license_expiry) : null,
                                    'license_document'     => $e->license_document ?? null,
                                ])->values()->toArray();
                            }
                        }
                    @endphp

                    @if(!empty($licenses))
                        @foreach($licenses as $index => $lic)
                        <div class="license-entry border rounded p-3 mb-3 bg-light" data-index="{{ $index }}">
                            @if(!empty($lic['id']))
                                <input type="hidden" name="licenses[{{ $index }}][id]" value="{{ $lic['id'] }}">
                            @endif
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h6 class="mb-0 fw-bold text-dark"><i class="fa-solid fa-award text-warning me-2"></i>License #{{ $index + 1 }}</h6>
                                <button type="button" class="btn btn-sm btn-outline-danger remove-license" onclick="removeLicense({{ $index }})">
                                    <i class="fa-solid fa-trash me-1"></i>Remove
                                </button>
                            </div>
                            
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">License / Certification Title <span class="text-danger">*</span></label>
                                    <input type="text" name="licenses[{{ $index }}][license_name]" class="form-control" 
                                           value="{{ $lic['license_name'] ?? '' }}" placeholder="e.g., Practicing Attorney, Professional Engineer PE, Driving License Grade 3">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">Issuing Authority / Organization</label>
                                    <input type="text" name="licenses[{{ $index }}][issuing_organization]" class="form-control" 
                                           value="{{ $lic['issuing_organization'] ?? '' }}" placeholder="e.g., Ministry of Justice, Ethiopian Construction Authority">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold">License / Registration Number</label>
                                    <input type="text" name="licenses[{{ $index }}][license_number]" class="form-control font-monospace" 
                                           value="{{ $lic['license_number'] ?? '' }}" placeholder="e.g., EFAA-00247, PE-12345">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold">Issue Date</label>
                                    <input type="date" name="licenses[{{ $index }}][issue_date]" class="form-control"
                                           value="{{ isset($lic['issue_date']) ? (is_string($lic['issue_date']) ? substr($lic['issue_date'], 0, 10) : $lic['issue_date']) : '' }}">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold">Expiry Date</label>
                                    <input type="date" name="licenses[{{ $index }}][expiry_date]" class="form-control"
                                           value="{{ isset($lic['expiry_date']) ? (is_string($lic['expiry_date']) ? substr($lic['expiry_date'], 0, 10) : $lic['expiry_date']) : '' }}">
                                </div>
                                
                                <div class="col-md-6">
                                    @if(!empty($lic['license_document']))
                                        <div class="d-flex align-items-center gap-2 mb-2 p-2 bg-white rounded border">
                                            <i class="fa-solid fa-file-shield text-success"></i>
                                            <small class="text-muted text-truncate">License Document on file</small>
                                            <a href="{{ uploaded_asset($lic['license_document']) }}" target="_blank" class="btn btn-xs btn-outline-success ms-auto">
                                                <i class="fa-solid fa-eye me-1"></i>View Document
                                            </a>
                                        </div>
                                    @endif
                                    <label class="form-label small fw-semibold">
                                        <i class="fa-solid fa-file-shield text-success me-1"></i>Upload / Replace License Document
                                        <small class="text-muted fw-normal d-block">(PDF, PNG, JPG, WEBP - Max 15MB)</small>
                                    </label>
                                    <input type="file" name="licenses[{{ $index }}][license_document]" class="form-control" accept="application/pdf,image/jpeg,image/png,image/jpg,image/webp">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">Notes / Specialization / Scope of Practice</label>
                                    <textarea name="licenses[{{ $index }}][notes]" class="form-control" rows="2" 
                                              placeholder="e.g., Federal High Court jurisdiction, Heavy Machinery operation...">{{ $lic['notes'] ?? '' }}</textarea>
                                </div>
                            </div>
                        </div>
                        @endforeach
                    @endif
                </div>

                <button type="button" class="btn btn-outline-warning text-dark btn-sm fw-semibold" onclick="addLicense()">
                    <i class="fa-solid fa-plus me-1"></i> Add Another License Record
                </button>
            </div>

            {{-- STEP 8: Profile Photo & Registration Documents --}}
            <div class="step-panel" id="step-panel-8">
                <div class="d-flex justify-content-between align-items-center mb-4 pb-2 border-bottom">
                    <div>
                        <h5 class="mb-1"><i class="fa-solid fa-camera-retro text-info me-2"></i>Step 8: Profile Photo & Registration Documents</h5>
                        <p class="text-muted small mb-0">Upload or replace employee profile photo, official employment/registration contract, and guarantee letter.</p>
                    </div>
                    <span class="badge bg-info text-dark">Step 8 of 8</span>
                </div>

                <div class="row g-4">
                    {{-- Profile Picture --}}
                    <div class="col-md-4">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-body text-center">
                                <div class="mb-3">
                                    <img src="{{ $employee->profile_picture_url }}" id="profile_pic_edit_preview"
                                         style="width:120px;height:120px;object-fit:cover;border-radius:50%;border:3px solid #0d6efd;"
                                         alt="{{ $employee->full_name }}">
                                </div>
                                <h6 class="fw-bold mb-1"><i class="fa-solid fa-camera text-primary me-1"></i>Profile Photo</h6>
                                <p class="text-muted small mb-3">PNG, JPG, WEBP — Max 5MB.</p>
                                <input type="file" name="profile_picture" id="profile_picture_input"
                                       class="form-control form-control-sm @error('profile_picture') is-invalid @enderror"
                                       accept="image/jpeg,image/png,image/jpg,image/webp"
                                       onchange="previewProfilePicEdit(this)">
                                @error('profile_picture')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                        </div>
                    </div>

                    {{-- Registration / Employment Letter (Multiple Photos/Pages Allowed) --}}
                    <div class="col-md-4">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-body">
                                <h6 class="fw-bold mb-1"><i class="fa-solid fa-file-contract text-success me-1"></i>Employment & Registration Documents</h6>
                                <p class="text-muted small mb-2">Upload pictures/pages of the registration letter or employment contract (Multiple pictures allowed, PDF/PNG/JPG - Max 15MB each).</p>
                                
                                {{-- Display Existing Documents --}}
                                @php $regUrls = $employee->registration_letter_urls; @endphp
                                @if(!empty($regUrls))
                                    <div class="mb-2 p-2 bg-light rounded border">
                                        <div class="small fw-bold text-success mb-1"><i class="fa-solid fa-folder-open me-1"></i>Current Documents ({{ count($regUrls) }} on file):</div>
                                        <div class="d-flex flex-wrap gap-1">
                                            @foreach($regUrls as $idx => $url)
                                                <a href="{{ $url }}" target="_blank" class="btn btn-xs btn-outline-success">
                                                    <i class="fa-solid fa-eye me-1"></i>Doc #{{ $idx + 1 }}
                                                </a>
                                            @endforeach
                                        </div>
                                    </div>
                                @endif

                                <label class="form-label small fw-semibold">{{ !empty($regUrls) ? 'Upload New to Replace / Add More:' : 'Upload Registration Documents:' }}</label>
                                <input type="file" name="registration_letters[]" id="registration_letters_edit_input" multiple
                                       class="form-control form-control-sm @error('registration_letters') is-invalid @enderror @error('registration_letter') is-invalid @enderror"
                                       accept="application/pdf,image/jpeg,image/png,image/jpg,image/webp"
                                       onchange="previewMultiFilesEdit(this, 'reg_letters_edit_preview')">
                                @error('registration_letters')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                @error('registration_letter')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                <div id="reg_letters_edit_preview" class="mt-2 d-flex flex-wrap gap-2"></div>
                            </div>
                        </div>
                    </div>

                    {{-- Guarantee Letter & Guarantor Documents (Guarantor 1 & Guarantor 2) --}}
                    <div class="col-md-4">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-body">
                                <h6 class="fw-bold mb-1"><i class="fa-solid fa-shield-halved text-warning me-1"></i>Guarantee & Guarantor Documents</h6>
                                <p class="text-muted small mb-2">Guarantee letters and guarantor ID documents for both Guarantor 1 & 2.</p>

                                {{-- Guarantor 1 Documents --}}
                                <div class="p-2 border rounded bg-light mb-2">
                                    <div class="d-flex align-items-center justify-content-between mb-1">
                                        <span class="small fw-bold text-primary"><i class="fa-solid fa-user-shield me-1"></i>Guarantor #1</span>
                                        @if($employee->guarantee_letter || $employee->guarantor_id_card)
                                            <span class="badge bg-success-subtle text-success border border-success-subtle" style="font-size:0.65rem;">On File</span>
                                        @endif
                                    </div>
                                    @if($employee->guarantee_letter)
                                        <div class="d-flex align-items-center gap-1 mb-1">
                                            <small class="text-truncate text-muted" style="font-size:0.75rem;">Guarantee Letter #1:</small>
                                            <a href="{{ $employee->guarantee_letter_url }}" target="_blank" class="btn btn-xs btn-outline-warning ms-auto"><i class="fa-solid fa-eye me-1"></i>View</a>
                                        </div>
                                    @endif
                                    <input type="file" name="guarantee_letter" class="form-control form-control-sm mb-2" accept="application/pdf,image/jpeg,image/png,image/jpg,image/webp">
                                    
                                    @if($employee->guarantor_id_card)
                                        <div class="d-flex align-items-center gap-1 mb-1">
                                            <small class="text-truncate text-muted" style="font-size:0.75rem;">Guarantor 1 ID:</small>
                                            <a href="{{ $employee->guarantor_id_card_url }}" target="_blank" class="btn btn-xs btn-outline-primary ms-auto"><i class="fa-solid fa-eye me-1"></i>View</a>
                                        </div>
                                    @endif
                                    <input type="file" name="guarantor_id_card" class="form-control form-control-sm" accept="application/pdf,image/jpeg,image/png,image/jpg,image/webp">
                                </div>

                                {{-- Guarantor 2 Documents --}}
                                <div class="p-2 border rounded bg-light">
                                    <div class="d-flex align-items-center justify-content-between mb-1">
                                        <span class="small fw-bold text-secondary"><i class="fa-solid fa-user-shield me-1"></i>Guarantor #2</span>
                                        @if($employee->guarantee_letter_2 || $employee->guarantor_2_id_card)
                                            <span class="badge bg-success-subtle text-success border border-success-subtle" style="font-size:0.65rem;">On File</span>
                                        @endif
                                    </div>
                                    @if($employee->guarantee_letter_2)
                                        <div class="d-flex align-items-center gap-1 mb-1">
                                            <small class="text-truncate text-muted" style="font-size:0.75rem;">Guarantee Letter #2:</small>
                                            <a href="{{ $employee->guarantee_letter_2_url }}" target="_blank" class="btn btn-xs btn-outline-warning ms-auto"><i class="fa-solid fa-eye me-1"></i>View</a>
                                        </div>
                                    @endif
                                    <input type="file" name="guarantee_letter_2" class="form-control form-control-sm mb-2" accept="application/pdf,image/jpeg,image/png,image/jpg,image/webp">
                                    
                                    @if($employee->guarantor_2_id_card)
                                        <div class="d-flex align-items-center gap-1 mb-1">
                                            <small class="text-truncate text-muted" style="font-size:0.75rem;">Guarantor 2 ID:</small>
                                            <a href="{{ $employee->guarantor_2_id_card_url }}" target="_blank" class="btn btn-xs btn-outline-primary ms-auto"><i class="fa-solid fa-eye me-1"></i>View</a>
                                        </div>
                                    @endif
                                    <input type="file" name="guarantor_2_id_card" class="form-control form-control-sm" accept="application/pdf,image/jpeg,image/png,image/jpg,image/webp">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Navigation Action Buttons --}}
            <div class="d-flex justify-content-between mt-4 pt-3 border-top">
                <div>
                    <button type="button" class="btn btn-outline-secondary" id="btnPrevStep" onclick="prevStep()">
                        <i class="fa-solid fa-arrow-left me-2"></i>Previous Step
                    </button>
                    <a href="{{ route('employees.show', $employee) }}" class="btn btn-outline-danger ms-2">
                        <i class="fa-solid fa-xmark me-1"></i>Cancel
                    </a>
                </div>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-primary px-4 fw-semibold" id="btnNextStep" onclick="nextStep()">
                        Next Step <i class="fa-solid fa-arrow-right ms-2"></i>
                    </button>
                    <button type="submit" class="btn btn-success px-4 fw-bold shadow-sm" id="btnSaveAll">
                        <i class="fa-solid fa-floppy-disk me-2"></i>Save All Changes
                    </button>
                </div>
            </div>

        </form>
    </div>
</div>

<script>
const fixedAssetUnitsList = @json($fixedAssetsJson ?? []);
let currentStep = 1;
const totalSteps = 8;

function previewProfilePicEdit(input) {
    if (input.files && input.files[0]) {
        const file = input.files[0];
        if (file.type.startsWith('image/')) {
            const reader = new FileReader();
            reader.onload = function(e) {
                const img = document.getElementById('profile_pic_edit_preview');
                if (img) img.src = e.target.result;
            };
            reader.readAsDataURL(file);
        }
    }
}

function previewMultiFilesEdit(input, previewTargetId) {
    const target = document.getElementById(previewTargetId);
    if (!target) return;
    target.innerHTML = '';

    if (input.files && input.files.length > 0) {
        Array.from(input.files).forEach((file, idx) => {
            if (file.type.startsWith('image/')) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    const card = document.createElement('div');
                    card.className = 'border rounded p-1 text-center bg-white shadow-xs position-relative';
                    card.style.width = '85px';
                    card.innerHTML = `
                        <img src="${e.target.result}" class="rounded" style="width:75px;height:75px;object-fit:cover;" alt="Photo ${idx+1}">
                        <div class="small text-truncate text-muted mt-1" style="font-size:0.65rem;">New #${idx+1}</div>
                    `;
                    target.appendChild(card);
                };
                reader.readAsDataURL(file);
            } else {
                const card = document.createElement('div');
                card.className = 'border rounded p-2 text-center bg-light shadow-xs';
                card.style.width = '85px';
                card.innerHTML = `
                    <i class="fa-solid fa-file-pdf text-danger fa-2x"></i>
                    <div class="small text-truncate text-muted mt-1" style="font-size:0.65rem;">${file.name}</div>
                `;
                target.appendChild(card);
            }
        });
    }
}

let educationCount = {{ count($educations) }};
let experienceCount = {{ count($experiences) }};
let licenseCount = {{ count($licenses ?? []) }};
let assetCount = {{ count($assignedUnitIds) }};
let currentActiveCategory = 'ALL';

function goToStep(step) {
    if (step < 1 || step > totalSteps) return;
    currentStep = step;

    // Show active step panel
    document.querySelectorAll('.step-panel').forEach(p => p.classList.remove('active'));
    const activePanel = document.getElementById(`step-panel-${step}`);
    if (activePanel) activePanel.classList.add('active');

    // Update step indicators
    for (let i = 1; i <= totalSteps; i++) {
        const ind = document.getElementById(`step-ind-${i}`);
        if (!ind) continue;
        ind.classList.remove('active', 'completed');
        if (i === currentStep) {
            ind.classList.add('active');
        } else if (i < currentStep) {
            ind.classList.add('completed');
        }
    }

    // Toggle Previous Button
    const btnPrev = document.getElementById('btnPrevStep');
    if (btnPrev) {
        btnPrev.style.display = currentStep > 1 ? 'inline-block' : 'none';
    }

    // Toggle Next Button
    const btnNext = document.getElementById('btnNextStep');
    if (btnNext) {
        btnNext.style.display = currentStep < totalSteps ? 'inline-block' : 'none';
    }

    window.scrollTo({ top: 100, behavior: 'smooth' });
}

function nextStep() {
    if (currentStep < totalSteps) {
        goToStep(currentStep + 1);
    }
}

function prevStep() {
    if (currentStep > 1) {
        goToStep(currentStep - 1);
    }
}

function validateStep(step) {
    const panel = document.getElementById(`step-panel-${step}`);
    if (!panel) return true;

    let isValid = true;
    const requiredInputs = panel.querySelectorAll('input[required], select[required], textarea[required]');

    requiredInputs.forEach(function(input) {
        if (!input.value || input.value.trim() === '') {
            input.classList.add('is-invalid');
            isValid = false;
        } else {
            input.classList.remove('is-invalid');
        }
    });

    if (!isValid) {
        const firstInvalid = panel.querySelector('.is-invalid');
        if (firstInvalid) firstInvalid.focus();
    }
    return isValid;
}

// Live Search per row
function filterAssetOptions(input) {
    const entry = input.closest('.asset-entry');
    if (!entry) return;

    const query = (input.value || '').trim().toLowerCase();
    const select = entry.querySelector('.asset-select');
    const countBadge = entry.querySelector('.asset-match-count');
    if (!select) return;

    let matchCount = 0;
    const options = select.querySelectorAll('option');

    options.forEach(opt => {
        if (!opt.value) {
            opt.hidden = false;
            return;
        }

        const searchKeywords = opt.dataset.search || opt.textContent.toLowerCase();
        const optCat = opt.dataset.category || 'General';

        const matchesQuery = query === '' || searchKeywords.includes(query);
        const matchesCategory = currentActiveCategory === 'ALL' || optCat === currentActiveCategory;

        if (matchesQuery && matchesCategory) {
            opt.hidden = false;
            matchCount++;
        } else {
            opt.hidden = true;
        }
    });

    if (countBadge) {
        if (query || currentActiveCategory !== 'ALL') {
            countBadge.innerHTML = `<i class="fa-solid fa-filter me-1"></i>Found <strong>${matchCount}</strong> matching units`;
        } else {
            countBadge.innerHTML = '';
        }
    }
}

function clearRowSearch(btn) {
    const entry = btn.closest('.asset-entry');
    if (!entry) return;
    const input = entry.querySelector('.asset-row-search');
    if (input) {
        input.value = '';
        filterAssetOptions(input);
    }
}

// Global Category Filter
function filterByCategory(cat, btn) {
    currentActiveCategory = cat;

    document.querySelectorAll('.btn-category-filter').forEach(b => {
        b.classList.remove('active', 'btn-dark');
        b.classList.add('btn-outline-secondary');
    });
    if (btn) {
        btn.classList.remove('btn-outline-secondary');
        btn.classList.add('active', 'btn-dark');
    }

    document.querySelectorAll('.asset-row-search').forEach(input => {
        filterAssetOptions(input);
    });
}

function onAssetUnitSelected(select) {
    const entry = select.closest('.asset-entry');
    if (!entry) return;

    const detailsBox = entry.querySelector('.selected-asset-details');
    const selectedOpt = select.options[select.selectedIndex];

    if (!select.value || !selectedOpt) {
        if (detailsBox) detailsBox.classList.add('d-none');
        return;
    }

    if (detailsBox) {
        const codeEl = detailsBox.querySelector('.selected-unit-code');
        const nameEl = detailsBox.querySelector('.selected-asset-name');
        const specsEl = detailsBox.querySelector('.selected-asset-specs');

        if (codeEl) codeEl.textContent = selectedOpt.dataset.unitCode || selectedOpt.textContent.split('—')[0].trim();
        if (nameEl) nameEl.textContent = selectedOpt.dataset.assetName || '';
        if (specsEl) specsEl.textContent = selectedOpt.dataset.specs ? `(${selectedOpt.dataset.specs})` : '';

        detailsBox.classList.remove('d-none');
    }
}

function addAsset() {
    const container = document.getElementById('assetsContainer');
    const index = assetCount;
    
    let optionsHtml = '<option value="">-- Choose an Available Asset Unit --</option>';
    fixedAssetUnitsList.forEach(u => {
        const pName = u.parent_asset ? u.parent_asset.name : 'Asset';
        const pCat = u.parent_asset ? u.parent_asset.category : 'General';
        const detailStr = u.plate_number ? `Plate: ${u.plate_number}` : (u.serial_number ? `SN: ${u.serial_number}` : (u.brand ? `${u.brand} ${u.model || ''}` : 'In Store'));
        const searchKeywords = `${u.unit_code} ${pName} ${pCat} ${detailStr} ${u.brand || ''} ${u.model || ''} ${u.serial_number || ''} ${u.plate_number || ''}`.toLowerCase();
        optionsHtml += `<option value="${u.id}" data-category="${pCat}" data-search="${searchKeywords}" data-unit-code="${u.unit_code}" data-asset-name="${pName}" data-specs="${detailStr}">${u.unit_code} — ${pName} (${detailStr}) • [${pCat}]</option>`;
    });

    const html = `
        <div class="asset-entry border rounded p-3 mb-3 bg-light" data-index="${index}">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <h6 class="mb-0 fw-bold"><i class="fa-solid fa-barcode text-primary me-2"></i>Assigned Asset Unit #${index + 1}</h6>
                <button type="button" class="btn btn-sm btn-outline-danger remove-asset" onclick="removeAsset(${index})">
                    <i class="fa-solid fa-trash me-1"></i>Remove
                </button>
            </div>

            <div class="mb-2">
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-white border-end-0"><i class="fa-solid fa-magnifying-glass text-primary"></i></span>
                    <input type="text" class="form-control form-control-sm border-start-0 asset-row-search" 
                           placeholder="🔍 Type unit code (e.g. COMP-1), serial, plate, or brand to search..." 
                           oninput="filterAssetOptions(this)">
                    <button type="button" class="btn btn-outline-secondary btn-sm" onclick="clearRowSearch(this)" title="Clear Search">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>
            </div>

            <div class="row g-2">
                <div class="col-12">
                    <label class="form-label small fw-bold mb-1">Select Fixed Asset Unit <span class="badge bg-success ms-1">Available</span></label>
                    <select name="fixed_asset_units[]" class="form-select asset-select font-monospace" onchange="onAssetUnitSelected(this)">
                        ${optionsHtml}
                    </select>
                    <div class="asset-match-count small text-muted mt-1" style="font-size: 0.75rem;"></div>
                </div>
            </div>

            <div class="selected-asset-details mt-2 p-2 bg-white rounded border d-none small">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <span class="badge bg-dark font-monospace fs-6 me-2 selected-unit-code"></span>
                        <strong class="text-dark selected-asset-name"></strong>
                        <span class="text-muted ms-2 selected-asset-specs"></span>
                    </div>
                    <div>
                        <span class="badge bg-success"><i class="fa-solid fa-check me-1"></i>Assigned</span>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    container.insertAdjacentHTML('beforeend', html);
    assetCount++;
    updateRemoveButtons();
}

function removeAsset(index) {
    const entry = document.querySelector(`.asset-entry[data-index="${index}"]`);
    if (entry) entry.remove();
    updateRemoveButtons();
}

// Education Functions
function addEducation() {
    const container = document.getElementById('educationContainer');
    const index = educationCount;
    
    const html = `
        <div class="education-entry border rounded p-3 mb-3 bg-light" data-index="${index}">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h6 class="mb-0 fw-bold"><i class="fa-solid fa-book me-2 text-primary"></i>Education Record #${index + 1}</h6>
                <button type="button" class="btn btn-sm btn-outline-danger remove-education" onclick="removeEducation(${index})">
                    <i class="fa-solid fa-trash me-1"></i>Remove
                </button>
            </div>
            
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Degree Level</label>
                    <select name="education[${index}][degree_level]" class="form-select">
                        <option value="">Select Degree</option>
                        <option value="PhD">PhD / Doctorate</option>
                        <option value="Master">Master's Degree</option>
                        <option value="Bachelor">Bachelor's Degree</option>
                        <option value="Diploma">Diploma</option>
                        <option value="Certificate">Certificate</option>
                        <option value="High School">High School</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Field of Study</label>
                    <input type="text" name="education[${index}][field_of_study]" class="form-control" placeholder="e.g., Civil Engineering">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Institution Name</label>
                    <input type="text" name="education[${index}][institution_name]" class="form-control" placeholder="e.g., Addis Ababa University">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Location</label>
                    <input type="text" name="education[${index}][location]" class="form-control" placeholder="e.g., Addis Ababa, Ethiopia">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Start Date</label>
                    <input type="date" name="education[${index}][start_date]" class="form-control">
                </div>
                <div class="col-md-4">
                    <label class="form-label">End Date / Expected</label>
                    <input type="date" name="education[${index}][end_date]" class="form-control">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Grade / GPA</label>
                    <input type="text" name="education[${index}][grade_gpa]" class="form-control" placeholder="e.g., 3.8/4.0">
                </div>
                <div class="col-12">
                    <label class="form-label">Description / Achievements</label>
                    <textarea name="education[${index}][description]" class="form-control" rows="2" placeholder="Optional: honors, thesis, etc."></textarea>
                </div>
                <div class="col-12">
                    <label class="form-label small fw-semibold">Certificate Photo <small class="text-muted">(Max 10MB)</small></label>
                    <input type="file" name="education[${index}][certificate_photo]" class="form-control" accept="image/jpeg,image/png,image/jpg,application/pdf">
                </div>
            </div>
        </div>
    `;
    
    container.insertAdjacentHTML('beforeend', html);
    educationCount++;
    updateRemoveButtons();
}

function removeEducation(index) {
    const entry = document.querySelector(`.education-entry[data-index="${index}"]`);
    if (entry) entry.remove();
    updateRemoveButtons();
}

// Experience Functions
function addExperience() {
    const container = document.getElementById('experienceContainer');
    const index = experienceCount;
    
    const html = `
        <div class="experience-entry border rounded p-3 mb-3 bg-light" data-index="${index}">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h6 class="mb-0 fw-bold"><i class="fa-solid fa-building text-success me-2"></i>Experience Record #${index + 1}</h6>
                <button type="button" class="btn btn-sm btn-outline-danger remove-experience" onclick="removeExperience(${index})">
                    <i class="fa-solid fa-trash me-1"></i>Remove
                </button>
            </div>
            
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Job Title</label>
                    <input type="text" name="experience[${index}][job_title]" class="form-control" placeholder="e.g., Site Engineer, Project Manager">
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Company Name</label>
                    <input type="text" name="experience[${index}][company_name]" class="form-control" placeholder="e.g., ABC Construction Plc">
                </div>
                <div class="col-md-12">
                    <label class="form-label fw-semibold">Location</label>
                    <input type="text" name="experience[${index}][location]" class="form-control" placeholder="e.g., Addis Ababa, Ethiopia">
                </div>
                <div class="col-md-5">
                    <label class="form-label fw-semibold">Start Date</label>
                    <input type="date" name="experience[${index}][start_date]" class="form-control">
                </div>
                <div class="col-md-5">
                    <label class="form-label fw-semibold">End Date</label>
                    <input type="date" name="experience[${index}][end_date]" class="form-control" id="exp_end_date_${index}">
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <div class="form-check mb-2">
                        <input type="checkbox" name="experience[${index}][is_current]" class="form-check-input" 
                               id="is_current_${index}" value="1" onchange="toggleEndDate(${index})">
                        <label class="form-check-label small fw-semibold" for="is_current_${index}">Current</label>
                    </div>
                </div>
                <div class="col-12">
                    <label class="form-label fw-semibold">Key Responsibilities</label>
                    <textarea name="experience[${index}][responsibilities]" class="form-control" rows="3" placeholder="Describe main duties, projects handled, and achievements..."></textarea>
                </div>
                
                <div class="col-12">
                    <label class="form-label small fw-semibold">
                        <i class="fa-solid fa-file-lines text-primary me-1"></i>Upload / Replace Experience Certificate
                        <small class="text-muted fw-normal d-block">(PDF, PNG, JPG, WEBP - Max 15MB)</small>
                    </label>
                    <input type="file" name="experience[${index}][experience_letter]" class="form-control" accept="application/pdf,image/jpeg,image/png,image/jpg,image/webp">
                </div>
            </div>
        </div>
    `;
    
    container.insertAdjacentHTML('beforeend', html);
    experienceCount++;
    updateRemoveButtons();
}

function removeExperience(index) {
    const entry = document.querySelector(`.experience-entry[data-index="${index}"]`);
    if (entry) entry.remove();
    updateRemoveButtons();
}

// Professional License Functions
function addLicense() {
    const container = document.getElementById('licensesContainer');
    const index = licenseCount;
    
    const html = `
        <div class="license-entry border rounded p-3 mb-3 bg-light" data-index="${index}">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h6 class="mb-0 fw-bold text-dark"><i class="fa-solid fa-award text-warning me-2"></i>License #${index + 1}</h6>
                <button type="button" class="btn btn-sm btn-outline-danger remove-license" onclick="removeLicense(${index})">
                    <i class="fa-solid fa-trash me-1"></i>Remove
                </button>
            </div>
            
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label fw-semibold">License / Certification Title <span class="text-danger">*</span></label>
                    <input type="text" name="licenses[${index}][license_name]" class="form-control" 
                           placeholder="e.g., Practicing Attorney, Professional Engineer PE, Driving License Grade 3">
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Issuing Authority / Organization</label>
                    <input type="text" name="licenses[${index}][issuing_organization]" class="form-control" 
                           placeholder="e.g., Ministry of Justice, Ethiopian Construction Authority">
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold">License / Registration Number</label>
                    <input type="text" name="licenses[${index}][license_number]" class="form-control font-monospace" 
                           placeholder="e.g., EFAA-00247, PE-12345">
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Issue Date</label>
                    <input type="date" name="licenses[${index}][issue_date]" class="form-control">
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Expiry Date</label>
                    <input type="date" name="licenses[${index}][expiry_date]" class="form-control">
                </div>
                
                <div class="col-md-6">
                    <label class="form-label small fw-semibold">
                        <i class="fa-solid fa-file-shield text-success me-1"></i>Upload / Replace License Document
                        <small class="text-muted fw-normal d-block">(PDF, PNG, JPG, WEBP - Max 15MB)</small>
                    </label>
                    <input type="file" name="licenses[${index}][license_document]" class="form-control" accept="application/pdf,image/jpeg,image/png,image/jpg,image/webp">
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Notes / Specialization / Scope of Practice</label>
                    <textarea name="licenses[${index}][notes]" class="form-control" rows="2" 
                              placeholder="e.g., Federal High Court jurisdiction, Heavy Machinery operation..."></textarea>
                </div>
            </div>
        </div>
    `;
    
    container.insertAdjacentHTML('beforeend', html);
    licenseCount++;
    updateRemoveButtons();
}

function removeLicense(index) {
    const entry = document.querySelector(`.license-entry[data-index="${index}"]`);
    if (entry) entry.remove();
    updateRemoveButtons();
}

function toggleEndDate(index) {
    const checkbox = document.getElementById(`is_current_${index}`);
    const endDateField = document.getElementById(`exp_end_date_${index}`);
    if (checkbox && endDateField) {
        endDateField.disabled = checkbox.checked;
        if (checkbox.checked) endDateField.value = '';
    }
}

function updateRemoveButtons() {
    const educationEntries = document.querySelectorAll('.education-entry');
    educationEntries.forEach(entry => {
        const removeBtn = entry.querySelector('.remove-education');
        if (removeBtn) removeBtn.style.display = educationEntries.length > 1 ? 'inline-block' : 'none';
    });
    
    const experienceEntries = document.querySelectorAll('.experience-entry');
    experienceEntries.forEach(entry => {
        const removeBtn = entry.querySelector('.remove-experience');
        if (removeBtn) removeBtn.style.display = experienceEntries.length > 1 ? 'inline-block' : 'none';
    });

    const licenseEntries = document.querySelectorAll('.license-entry');
    licenseEntries.forEach(entry => {
        const removeBtn = entry.querySelector('.remove-license');
        if (removeBtn) removeBtn.style.display = licenseEntries.length > 1 ? 'inline-block' : 'none';
    });
    
    const assetEntries = document.querySelectorAll('.asset-entry');
    assetEntries.forEach(entry => {
        const removeBtn = entry.querySelector('.remove-asset');
        if (removeBtn) removeBtn.style.display = assetEntries.length > 1 ? 'inline-block' : 'none';
    });
}

document.addEventListener('DOMContentLoaded', function() {
    goToStep(1);
    document.querySelectorAll('.asset-select').forEach(sel => {
        if (sel.value) onAssetUnitSelected(sel);
    });

    // ── Multi-Step Submit Guard ───────────────────────────────────────────
    const form = document.getElementById('editEmployeeForm');
    if (form) {
        form.addEventListener('submit', function(e) {
            for (let s = 1; s <= totalSteps; s++) {
                if (!validateStep(s)) {
                    e.preventDefault();
                    goToStep(s);
                    return false;
                }
            }
        });
    }
});
</script>

@endsection
