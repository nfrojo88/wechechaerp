@extends('layouts.app')

@section('title', 'Add Employee')

@section('content')
<div class="d-flex align-items-center mb-4">
    <a href="{{ route('employees.index') }}" class="btn btn-sm btn-outline-secondary me-3">
        <i class="fa-solid fa-arrow-left"></i>
    </a>
    <div>
        <h1 class="h3 mb-0">Add New Employee</h1>
        <small class="text-muted">Register an employee profile with document and certificate attachments</small>
    </div>
</div>

@if(session('success'))
<div class="alert alert-success alert-dismissible fade show d-flex align-items-center gap-3 shadow-sm mb-4" role="alert">
    <i class="fa-solid fa-circle-check fa-2x text-success"></i>
    <div>
        <strong class="d-block fs-6">Registration Successful!</strong>
        {{ session('success') }}
    </div>
    <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
@endif

<!-- Multi-Step Progress Indicator (Client-Side Wizard) -->
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
.img-preview-box {
    max-height: 140px;
    object-fit: contain;
    border-radius: 6px;
    border: 1px solid #dee2e6;
    padding: 3px;
    background: #f8f9fa;
}
</style>

<div class="card border-0 shadow-sm">
    <div class="card-body p-4">
        <form method="POST" action="{{ route('employees.store') }}" id="employeeForm" enctype="multipart/form-data" novalidate>
            @csrf
            
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
                    <span class="badge bg-primary">Step 1 of 6</span>
                </div>
                
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Employee Code <span class="text-danger">*</span></label>
                        <input type="text" name="employee_code" class="form-control @error('employee_code') is-invalid @enderror"
                               value="{{ old('employee_code', 'EMP-'.rand(10000,99999)) }}" required>
                        @error('employee_code')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Full Name <span class="text-danger">*</span></label>
                        <input type="text" name="full_name" class="form-control @error('full_name') is-invalid @enderror"
                               value="{{ old('full_name') }}" placeholder="e.g. Abebe Bikila" required>
                        @error('full_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">
                            <i class="fa-solid fa-fingerprint text-primary me-1"></i>ZKTeco Device User ID
                        </label>
                        <input type="text" name="device_user_id" class="form-control @error('device_user_id') is-invalid @enderror"
                               value="{{ old('device_user_id') }}"
                               placeholder="e.g. 1, 2, 17, 50">
                        <small class="text-muted">Numeric ID assigned in biometric device.</small>
                        @error('device_user_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Primary Phone <span class="text-danger">*</span></label>
                        <input type="text" name="phone" class="form-control @error('phone') is-invalid @enderror"
                               value="{{ old('phone') }}" placeholder="+251 911 234 567" required>
                        @error('phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" class="form-control" value="{{ old('email') }}" placeholder="employee@company.com">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Department <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <select name="department" class="form-select @error('department') is-invalid @enderror" required>
                                <option value="">-- Select Department --</option>
                                @foreach($departments as $dept)
                                    <option value="{{ $dept->name }}" {{ old('department') == $dept->name ? 'selected' : '' }}>
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
                               value="{{ old('role_title') }}" placeholder="e.g. Site Engineer">
                    </div>
                </div>
            </div>

            {{-- STEP 2: Employment Details --}}
            <div class="step-panel" id="step-panel-2">
                <div class="d-flex justify-content-between align-items-center mb-4 pb-2 border-bottom">
                    <h5 class="mb-0"><i class="fa-solid fa-briefcase text-success me-2"></i>Step 2: Employment Details</h5>
                    <span class="badge bg-success">Step 2 of 6</span>
                </div>
                
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Employment Type <span class="text-danger">*</span></label>
                        <select name="employment_type" class="form-select" required>
                            <option value="permanent" {{ old('employment_type', 'permanent') == 'permanent' ? 'selected' : '' }}>Permanent</option>
                            <option value="contract"  {{ old('employment_type') == 'contract' ? 'selected' : '' }}>Contract</option>
                            <option value="daily"     {{ old('employment_type') == 'daily' ? 'selected' : '' }}>Daily Labor</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Contract Start Date <span class="text-danger">*</span></label>
                        <input type="date" name="date_of_joining" class="form-control @error('date_of_joining') is-invalid @enderror"
                               value="{{ old('date_of_joining', date('Y-m-d')) }}" required>
                        @error('date_of_joining')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Status <span class="text-danger">*</span></label>
                        <select name="status" class="form-select" required>
                            <option value="active"     {{ old('status', 'active') == 'active' ? 'selected' : '' }}>Active</option>
                            <option value="suspended"  {{ old('status') == 'suspended' ? 'selected' : '' }}>Suspended</option>
                            <option value="terminated" {{ old('status') == 'terminated' ? 'selected' : '' }}>Terminated</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Assigned Project</label>
                        <select name="project_id" class="form-select">
                            <option value="">-- No Specific Project (HQ) --</option>
                            @foreach($projects as $project)
                                <option value="{{ $project->id }}" {{ old('project_id') == $project->id ? 'selected' : '' }}>
                                    {{ $project->name }} ({{ $project->code }})
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Site Assignment</label>
                        <select name="site_assignment" class="form-select">
                            <option value="Head Office"  {{ old('site_assignment', 'Head Office') == 'Head Office' ? 'selected' : '' }}>Head Office</option>
                            <option value="Project Site" {{ old('site_assignment') == 'Project Site' ? 'selected' : '' }}>Project Site</option>
                            <option value="Workshop"     {{ old('site_assignment') == 'Workshop' ? 'selected' : '' }}>Workshop</option>
                            <option value="Remote"       {{ old('site_assignment') == 'Remote' ? 'selected' : '' }}>Remote</option>
                        </select>
                    </div>
                </div>
            </div>

            {{-- STEP 3: Salary & Guarantee Letter --}}
            <div class="step-panel" id="step-panel-3">
                <div class="d-flex justify-content-between align-items-center mb-4 pb-2 border-bottom">
                    <h5 class="mb-0"><i class="fa-solid fa-money-bill text-warning me-2"></i>Step 3: Salary & Guarantee Letter</h5>
                    <span class="badge bg-warning text-dark">Step 3 of 6</span>
                </div>
                
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Monthly Base Salary (ETB) <span class="text-danger">*</span></label>
                        <input type="number" step="0.01" name="basic_salary" class="form-control @error('basic_salary') is-invalid @enderror"
                               value="{{ old('basic_salary', 0) }}" placeholder="0.00" required>
                        @error('basic_salary')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Contract Type</label>
                        <select name="contract_type" class="form-select">
                            <option value="Full-Time"  {{ old('contract_type', 'Full-Time') == 'Full-Time' ? 'selected' : '' }}>Full-Time</option>
                            <option value="Part-Time"  {{ old('contract_type') == 'Part-Time' ? 'selected' : '' }}>Part-Time</option>
                            <option value="Temporary"  {{ old('contract_type') == 'Temporary' ? 'selected' : '' }}>Temporary</option>
                            <option value="Internship" {{ old('contract_type') == 'Internship' ? 'selected' : '' }}>Internship</option>
                        </select>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Transport Allowance (ETB)</label>
                        <input type="number" step="0.01" name="transport_allowance" class="form-control"
                               value="{{ old('transport_allowance', 0) }}" placeholder="0.00">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">House Allowance (ETB)</label>
                        <input type="number" step="0.01" name="house_allowance" class="form-control"
                               value="{{ old('house_allowance', 0) }}" placeholder="0.00">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Position Allowance (ETB)</label>
                        <input type="number" step="0.01" name="position_allowance" class="form-control"
                               value="{{ old('position_allowance', 0) }}" placeholder="0.00">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Bank Name</label>
                        <input type="text" name="bank_name" class="form-control" value="{{ old('bank_name') }}" placeholder="e.g. Commercial Bank of Ethiopia (CBE)">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Account Number</label>
                        <input type="text" name="account_number" class="form-control" value="{{ old('account_number') }}" placeholder="1000123456789">
                    </div>

                    <div class="col-12">
                        <div class="card border-warning bg-light p-3">
                            <h6 class="fw-bold mb-2 text-dark">
                                <i class="fa-solid fa-shield-halved text-warning me-2"></i>Guarantee Letter (Optional Attachment)
                            </h6>
                            <p class="small text-muted mb-2">
                                <i class="fa-solid fa-info-circle me-1 text-primary"></i>
                                Upload signed guarantee letter document or photo. Supports <strong>JPG, PNG, JPEG, WEBP, PDF</strong> (up to 15MB).
                            </p>
                            <input type="file" name="guarantee_letter" id="guarantee_letter_input" class="form-control" 
                                   accept="application/pdf,image/jpeg,image/png,image/jpg,image/webp"
                                   onchange="previewSingleFile(this, 'guarantee_preview')">
                            <div id="guarantee_preview" class="mt-2 d-none">
                                <img src="" alt="Guarantee Preview" class="img-preview-box">
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- STEP 4: Fixed Assets & Equipment --}}
            <div class="step-panel" id="step-panel-4">
                <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2 pb-2 border-bottom">
                    <div>
                        <h5 class="mb-1"><i class="fa-solid fa-truck-monster text-warning me-2"></i>Step 4: Assign Fixed Assets & Equipment</h5>
                        <p class="text-muted small mb-0">Select equipment from centralized Store inventory (computers, vehicles, tools, etc.) to assign to this employee.</p>
                    </div>
                    <span class="badge bg-primary">Step 4 of 6</span>
                </div>

                {{-- Category quick filter pills --}}
                @php
                    $availableCats = $fixedAssetUnits->pluck('parentAsset.category')->filter()->unique()->values();
                @endphp
                @if($availableCats->count() > 1)
                <div class="d-flex align-items-center gap-1 mb-3 flex-wrap">
                    <small class="text-muted fw-bold me-1">Filter by Category:</small>
                    <button type="button" class="btn btn-sm btn-dark btn-category-filter active" onclick="filterByCategory('ALL', this)">All ({{ $fixedAssetUnits->count() }})</button>
                    @foreach($availableCats as $c)
                        @php $cCount = $fixedAssetUnits->where('parentAsset.category', $c)->count(); @endphp
                        <button type="button" class="btn btn-sm btn-outline-secondary btn-category-filter" onclick="filterByCategory('{{ $c }}', this)">
                            {{ $c }} <span class="badge bg-secondary ms-1">{{ $cCount }}</span>
                        </button>
                    @endforeach
                </div>
                @endif

                <div id="assetsContainer">
                    <div class="asset-entry border rounded p-3 mb-3 bg-light" data-index="0">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <h6 class="mb-0 fw-bold"><i class="fa-solid fa-barcode text-primary me-2"></i>Assigned Asset Unit #1</h6>
                            <button type="button" class="btn btn-sm btn-outline-danger remove-asset" onclick="removeAsset(0)" style="display: none;">
                                <i class="fa-solid fa-trash me-1"></i>Remove
                            </button>
                        </div>

                        {{-- Row search input --}}
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
                                    Select Available Fixed Asset Unit <span class="badge bg-success ms-1">In Store</span>
                                </label>
                                <select name="fixed_asset_units[]" class="form-select asset-select font-monospace" onchange="onAssetUnitSelected(this)">
                                    <option value="">-- Choose an Available Asset Unit (Optional) --</option>
                                    @foreach($fixedAssetUnits as $unit)
                                        @php
                                            $parentName = $unit->parentAsset?->name ?? 'Asset';
                                            $category = $unit->parentAsset?->category ?? 'General';
                                            $details = $unit->plate_number ? "Plate: {$unit->plate_number}" : ($unit->serial_number ? "SN: {$unit->serial_number}" : ($unit->brand ? "{$unit->brand} {$unit->model}" : 'In Store'));
                                            $searchKeywords = strtolower("{$unit->unit_code} {$parentName} {$category} {$details} {$unit->brand} {$unit->model} {$unit->serial_number} {$unit->plate_number}");
                                        @endphp
                                        <option value="{{ $unit->id }}" 
                                                data-category="{{ $category }}"
                                                data-search="{{ $searchKeywords }}"
                                                data-unit-code="{{ $unit->unit_code }}"
                                                data-asset-name="{{ $parentName }}"
                                                data-specs="{{ $details }}">
                                            {{ $unit->unit_code }} — {{ $parentName }} ({{ $details }}) • [{{ $category }}]
                                        </option>
                                    @endforeach
                                </select>
                                <div class="asset-match-count small text-muted mt-1" style="font-size: 0.75rem;"></div>
                            </div>
                        </div>

                        {{-- Details box --}}
                        <div class="selected-asset-details mt-2 p-2 bg-white rounded border d-none small">
                            <div class="d-flex align-items-center justify-content-between">
                                <div>
                                    <span class="badge bg-dark font-monospace fs-6 me-2 selected-unit-code"></span>
                                    <strong class="text-dark selected-asset-name"></strong>
                                    <span class="text-muted ms-2 selected-asset-specs"></span>
                                </div>
                                <div>
                                    <span class="badge bg-success"><i class="fa-solid fa-check me-1"></i>Ready for Assignment</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <button type="button" class="btn btn-outline-warning btn-sm" onclick="addAsset()">
                    <i class="fa-solid fa-plus me-1"></i>Assign Another Asset Unit
                </button>
            </div>

            {{-- STEP 5: Education & Certificates --}}
            <div class="step-panel" id="step-panel-5">
                <div class="d-flex justify-content-between align-items-center mb-4 pb-2 border-bottom">
                    <h5 class="mb-0"><i class="fa-solid fa-graduation-cap text-primary me-2"></i>Step 5: Education & Certificates</h5>
                    <span class="badge bg-primary">Step 5 of 6</span>
                </div>
                
                <div class="alert alert-info py-2 small mb-3">
                    <i class="fa-solid fa-info-circle me-1"></i>
                    <strong>Optional:</strong> You can attach photos/scans of diplomas or degree certificates (PNG, JPG, JPEG, WEBP, PDF). Leave blank to skip.
                </div>

                <div id="educationContainer">
                    <div class="education-entry border rounded p-3 mb-3 bg-light" data-index="0">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h6 class="mb-0 fw-bold"><i class="fa-solid fa-book me-2"></i>Education Record #1</h6>
                            <button type="button" class="btn btn-sm btn-outline-danger remove-education" onclick="removeEducation(0)" style="display: none;">
                                <i class="fa-solid fa-trash me-1"></i>Remove
                            </button>
                        </div>
                        
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Degree Level</label>
                                <select name="education[0][degree_level]" class="form-select">
                                    <option value="">-- Select Degree --</option>
                                    <option value="PhD">PhD / Doctorate</option>
                                    <option value="Master">Master's Degree</option>
                                    <option value="Bachelor" selected>Bachelor's Degree</option>
                                    <option value="Diploma">Diploma</option>
                                    <option value="Certificate">Certificate</option>
                                    <option value="High School">High School</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Field of Study</label>
                                <input type="text" name="education[0][field_of_study]" class="form-control" 
                                       placeholder="e.g., Civil Engineering">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Institution Name</label>
                                <input type="text" name="education[0][institution_name]" class="form-control" 
                                       placeholder="e.g., Addis Ababa University">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Location</label>
                                <input type="text" name="education[0][location]" class="form-control" 
                                       placeholder="e.g., Addis Ababa, Ethiopia">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Start Date</label>
                                <input type="date" name="education[0][start_date]" class="form-control">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">End Date / Graduation</label>
                                <input type="date" name="education[0][end_date]" class="form-control">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Grade / GPA</label>
                                <input type="text" name="education[0][grade_gpa]" class="form-control" 
                                       placeholder="e.g., 3.8/4.0 or Distinction">
                            </div>
                            <div class="col-12">
                                <label class="form-label">Description / Achievements</label>
                                <textarea name="education[0][description]" class="form-control" rows="2" 
                                          placeholder="Optional: Thesis title, honors, relevant coursework, etc."></textarea>
                            </div>
                            <div class="col-12">
                                <label class="form-label fw-bold">
                                    <i class="fa-solid fa-image text-primary me-1"></i>Certificate / Degree Photo or PDF
                                    <small class="text-muted fw-normal">(PNG, JPG, JPEG, WEBP, PDF - Max 15MB)</small>
                                </label>
                                <input type="file" name="education[0][certificate_photo]" class="form-control" 
                                       accept="application/pdf,image/jpeg,image/png,image/jpg,image/webp"
                                       onchange="previewArrayFile(this)">
                                <div class="file-preview-target mt-2 d-none">
                                    <img src="" alt="Certificate Preview" class="img-preview-box">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <button type="button" class="btn btn-outline-primary btn-sm" onclick="addEducation()">
                    <i class="fa-solid fa-plus me-1"></i>Add Another Education Record
                </button>
            </div>

            {{-- STEP 6: Work Experience --}}
            <div class="step-panel" id="step-panel-6">
                <div class="d-flex justify-content-between align-items-center mb-4 pb-2 border-bottom">
                    <h5 class="mb-0"><i class="fa-solid fa-briefcase text-success me-2"></i>Step 6: Work Experience</h5>
                    <span class="badge bg-success">Step 6 of 7</span>
                </div>
                
                <div class="alert alert-info py-2 small mb-3">
                    <i class="fa-solid fa-info-circle me-1"></i>
                    <strong>Optional:</strong> Add previous employment history and experience certificates. If not applicable, click <strong>"Next Step"</strong> to proceed.
                </div>

                <div id="experienceContainer">
                    <div class="experience-entry border rounded p-3 mb-3 bg-light" data-index="0">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h6 class="mb-0 fw-bold"><i class="fa-solid fa-building me-2"></i>Experience Record #1</h6>
                            <button type="button" class="btn btn-sm btn-outline-danger remove-experience" onclick="removeExperience(0)" style="display: none;">
                                <i class="fa-solid fa-trash me-1"></i>Remove
                            </button>
                        </div>
                        
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Job Title</label>
                                <input type="text" name="experience[0][job_title]" class="form-control" 
                                       placeholder="e.g., Site Engineer, Project Manager">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Company Name</label>
                                <input type="text" name="experience[0][company_name]" class="form-control" 
                                       placeholder="e.g., ABC Construction Plc">
                            </div>
                            <div class="col-md-12">
                                <label class="form-label fw-semibold">Location</label>
                                <input type="text" name="experience[0][location]" class="form-control" 
                                       placeholder="e.g., Addis Ababa, Ethiopia">
                            </div>
                            <div class="col-md-5">
                                <label class="form-label fw-semibold">Start Date</label>
                                <input type="date" name="experience[0][start_date]" class="form-control">
                            </div>
                            <div class="col-md-5">
                                <label class="form-label fw-semibold">End Date</label>
                                <input type="date" name="experience[0][end_date]" class="form-control" id="exp_end_date_0">
                            </div>
                            <div class="col-md-2 d-flex align-items-end">
                                <div class="form-check">
                                    <input type="checkbox" name="experience[0][is_current]" class="form-check-input" 
                                           id="is_current_0" value="1" onchange="toggleEndDate(0)">
                                    <label class="form-check-label fw-semibold" for="is_current_0">
                                        Current
                                    </label>
                                </div>
                            </div>
                            <div class="col-12">
                                <label class="form-label fw-semibold">Key Responsibilities</label>
                                <textarea name="experience[0][responsibilities]" class="form-control" rows="2" 
                                          placeholder="Describe main duties, projects handled, and achievements..."></textarea>
                            </div>
                            
                            <!-- Reference Section -->
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Reference Name</label>
                                <input type="text" name="experience[0][reference_name]" class="form-control" 
                                       placeholder="e.g., John Doe (Direct Supervisor)">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Reference Phone</label>
                                <input type="text" name="experience[0][reference_phone]" class="form-control" 
                                       placeholder="+251 911 234 567">
                            </div>

                            <!-- Document Upload -->
                            <div class="col-12">
                                <label class="form-label fw-semibold">
                                    <i class="fa-solid fa-file-lines text-primary me-1"></i>Experience Certificate / Recommendation Letter
                                    <small class="text-muted fw-normal d-block">(PDF, PNG, JPG, WEBP - Max 15MB)</small>
                                </label>
                                <input type="file" name="experience[0][experience_letter]" class="form-control" 
                                       accept="application/pdf,image/jpeg,image/png,image/jpg,image/webp"
                                       onchange="previewArrayFile(this)">
                                <div class="file-preview-target mt-2 d-none">
                                    <img src="" alt="Certificate Preview" class="img-preview-box">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <button type="button" class="btn btn-outline-success btn-sm" onclick="addExperience()">
                    <i class="fa-solid fa-plus me-1"></i>Add Another Experience Record
                </button>
            </div>

            {{-- STEP 7: Professional Licenses & Certifications --}}
            <div class="step-panel" id="step-panel-7">
                <div class="d-flex justify-content-between align-items-center mb-4 pb-2 border-bottom">
                    <h5 class="mb-0"><i class="fa-solid fa-id-card-clip text-warning me-2"></i>Step 7: Professional Licenses & Certifications</h5>
                    <span class="badge bg-warning text-dark">Step 7 of 7</span>
                </div>
                
                <div class="alert alert-warning bg-warning bg-opacity-10 py-2 small mb-3 border-start border-4 border-warning">
                    <i class="fa-solid fa-certificate me-1 text-warning"></i>
                    <strong>Professional Credentials:</strong> Register practicing licenses (e.g. Advocate/Lawyer license, Professional Engineer PE certificate, Commercial Driving License, ACCA, etc.). If none, click <strong>"Complete Registration"</strong> directly.
                </div>

                <div id="licensesContainer">
                    <div class="license-entry border rounded p-3 mb-3 bg-light" data-index="0">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h6 class="mb-0 fw-bold text-dark"><i class="fa-solid fa-award text-warning me-2"></i>License #1</h6>
                            <button type="button" class="btn btn-sm btn-outline-danger remove-license" onclick="removeLicense(0)" style="display: none;">
                                <i class="fa-solid fa-trash me-1"></i>Remove
                            </button>
                        </div>
                        
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">License / Certification Title <span class="text-danger">*</span></label>
                                <input type="text" name="licenses[0][license_name]" class="form-control" 
                                       placeholder="e.g., Practicing Attorney, Professional Engineer PE, Driving License Grade 3">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Issuing Authority / Organization</label>
                                <input type="text" name="licenses[0][issuing_organization]" class="form-control" 
                                       placeholder="e.g., Ministry of Justice, Ethiopian Construction Authority, Transport Authority">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">License / Registration Number</label>
                                <input type="text" name="licenses[0][license_number]" class="form-control font-monospace" 
                                       placeholder="e.g., EFAA-00247, PE-12345">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Issue Date</label>
                                <input type="date" name="licenses[0][issue_date]" class="form-control">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Expiry Date</label>
                                <input type="date" name="licenses[0][expiry_date]" class="form-control">
                            </div>
                            
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">
                                    <i class="fa-solid fa-file-shield text-success me-1"></i>Upload License Document / Certificate / Card Photo
                                    <small class="text-muted fw-normal d-block">(PDF, PNG, JPG, WEBP - Max 15MB)</small>
                                </label>
                                <input type="file" name="licenses[0][license_document]" class="form-control" 
                                       accept="application/pdf,image/jpeg,image/png,image/jpg,image/webp"
                                       onchange="previewArrayFile(this)">
                                <div class="file-preview-target mt-2 d-none">
                                    <img src="" alt="License Preview" class="img-preview-box">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Notes / Specialization / Scope of Practice</label>
                                <textarea name="licenses[0][notes]" class="form-control" rows="2" 
                                          placeholder="e.g., Federal First Instance & High Court jurisdiction, Heavy Duty Machinery..."></textarea>
                            </div>
                        </div>
                    </div>
                </div>

                <button type="button" class="btn btn-outline-warning text-dark btn-sm fw-semibold" onclick="addLicense()">
                    <i class="fa-solid fa-plus me-1"></i>Add Another License
                </button>
            </div>

            {{-- Wizard Navigation Footer --}}
            <div class="d-flex justify-content-between mt-4 pt-3 border-top">
                <div>
                    <button type="button" id="prevStepBtn" class="btn btn-outline-secondary" onclick="prevStep()" style="display: none;">
                        <i class="fa-solid fa-arrow-left me-2"></i>Previous Step
                    </button>
                </div>
                <div class="d-flex gap-2">
                    <button type="button" id="nextStepBtn" class="btn btn-primary fw-semibold px-4" onclick="nextStep()">
                        Next Step <i class="fa-solid fa-arrow-right ms-2"></i>
                    </button>
                    <button type="submit" id="submitBtn" class="btn btn-success fw-bold px-4" style="display: none;">
                        <i class="fa-solid fa-check me-2"></i>Complete Registration
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
const fixedAssetUnitsList = @json($fixedAssetsJson ?? []);
let currentStep = 1;
const totalSteps = 7;
let licenseCount = 1;

function goToStep(step) {
    if (step < 1 || step > totalSteps) return;

    // If advancing, validate current step required fields
    if (step > currentStep) {
        if (!validateStep(currentStep)) {
            return;
        }
    }

    // Hide all panels
    document.querySelectorAll('.step-panel').forEach(p => p.classList.remove('active'));
    
    // Show target panel
    const targetPanel = document.getElementById(`step-panel-${step}`);
    if (targetPanel) {
        targetPanel.classList.add('active');
    }

    // Update step indicators
    for (let i = 1; i <= totalSteps; i++) {
        const ind = document.getElementById(`step-ind-${i}`);
        if (!ind) continue;
        ind.classList.remove('active', 'completed');
        if (i === step) {
            ind.classList.add('active');
        } else if (i < step) {
            ind.classList.add('completed');
        }
    }

    currentStep = step;

    // Update button states
    const prevBtn = document.getElementById('prevStepBtn');
    const nextBtn = document.getElementById('nextStepBtn');
    const subBtn  = document.getElementById('submitBtn');

    if (prevBtn) prevBtn.style.display = currentStep > 1 ? 'inline-block' : 'none';
    if (nextBtn) nextBtn.style.display = currentStep < totalSteps ? 'inline-block' : 'none';
    if (subBtn)  subBtn.style.display  = currentStep === totalSteps ? 'inline-block' : 'none';

    window.scrollTo({ top: 0, behavior: 'smooth' });
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

    requiredInputs.forEach(input => {
        if (!input.value || input.value.trim() === '') {
            input.classList.add('is-invalid');
            isValid = false;
        } else {
            input.classList.remove('is-invalid');
        }

        // Auto remove is-invalid when user starts typing / selecting
        if (!input.dataset.hasValidationListener) {
            input.dataset.hasValidationListener = 'true';
            input.addEventListener('input', () => input.classList.remove('is-invalid'));
            input.addEventListener('change', () => input.classList.remove('is-invalid'));
        }
    });

    if (!isValid) {
        const firstInvalid = panel.querySelector('.is-invalid');
        if (firstInvalid) {
            firstInvalid.focus();
            firstInvalid.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    }

    return isValid;
}

// Single file preview (e.g. Guarantee Letter)
function previewSingleFile(input, previewTargetId) {
    const target = document.getElementById(previewTargetId);
    if (!target) return;

    if (input.files && input.files[0]) {
        const file = input.files[0];
        if (file.type.startsWith('image/')) {
            const reader = new FileReader();
            reader.onload = function(e) {
                const img = target.querySelector('img');
                if (img) {
                    img.src = e.target.result;
                    target.classList.remove('d-none');
                }
            };
            reader.readAsDataURL(file);
        } else {
            target.classList.add('d-none');
        }
    } else {
        target.classList.add('d-none');
    }
}

// Array file preview (Education & Experience entries)
function previewArrayFile(input) {
    const parent = input.closest('.col-12');
    if (!parent) return;
    const previewBox = parent.querySelector('.file-preview-target');
    if (!previewBox) return;

    if (input.files && input.files[0]) {
        const file = input.files[0];
        if (file.type.startsWith('image/')) {
            const reader = new FileReader();
            reader.onload = function(e) {
                const img = previewBox.querySelector('img');
                if (img) {
                    img.src = e.target.result;
                    previewBox.classList.remove('d-none');
                }
            };
            reader.readAsDataURL(file);
        } else {
            previewBox.classList.add('d-none');
        }
    } else {
        previewBox.classList.add('d-none');
    }
}

// Asset Management
let currentActiveCategory = 'ALL';
let assetCount = 1;
let educationCount = 1;
let experienceCount = 1;

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
    
    let optionsHtml = '<option value="">-- Choose an Available Asset Unit (Optional) --</option>';
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
                    <label class="form-label small fw-bold mb-1">
                        Select Available Fixed Asset Unit <span class="badge bg-success ms-1">In Store</span>
                    </label>
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
                        <span class="badge bg-success"><i class="fa-solid fa-check me-1"></i>Ready for Assignment</span>
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
    if (entry) {
        entry.remove();
    }
    updateRemoveButtons();
}

// Education dynamic rows
function addEducation() {
    const container = document.getElementById('educationContainer');
    const index = educationCount;
    
    const html = `
        <div class="education-entry border rounded p-3 mb-3 bg-light" data-index="${index}">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h6 class="mb-0 fw-bold"><i class="fa-solid fa-book me-2"></i>Education Record #${index + 1}</h6>
                <button type="button" class="btn btn-sm btn-outline-danger remove-education" onclick="removeEducation(${index})">
                    <i class="fa-solid fa-trash me-1"></i>Remove
                </button>
            </div>
            
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Degree Level</label>
                    <select name="education[${index}][degree_level]" class="form-select">
                        <option value="">-- Select Degree --</option>
                        <option value="PhD">PhD / Doctorate</option>
                        <option value="Master">Master's Degree</option>
                        <option value="Bachelor" selected>Bachelor's Degree</option>
                        <option value="Diploma">Diploma</option>
                        <option value="Certificate">Certificate</option>
                        <option value="High School">High School</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Field of Study</label>
                    <input type="text" name="education[${index}][field_of_study]" class="form-control" 
                           placeholder="e.g., Civil Engineering">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Institution Name</label>
                    <input type="text" name="education[${index}][institution_name]" class="form-control" 
                           placeholder="e.g., Addis Ababa University">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Location</label>
                    <input type="text" name="education[${index}][location]" class="form-control" 
                           placeholder="e.g., Addis Ababa, Ethiopia">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Start Date</label>
                    <input type="date" name="education[${index}][start_date]" class="form-control">
                </div>
                <div class="col-md-4">
                    <label class="form-label">End Date / Graduation</label>
                    <input type="date" name="education[${index}][end_date]" class="form-control">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Grade / GPA</label>
                    <input type="text" name="education[${index}][grade_gpa]" class="form-control" 
                           placeholder="e.g., 3.8/4.0 or Distinction">
                </div>
                <div class="col-12">
                    <label class="form-label">Description / Achievements</label>
                    <textarea name="education[${index}][description]" class="form-control" rows="2" 
                              placeholder="Optional: Thesis title, honors, relevant coursework, etc."></textarea>
                </div>
                <div class="col-12">
                    <label class="form-label fw-bold">
                        <i class="fa-solid fa-image text-primary me-1"></i>Certificate / Degree Photo or PDF
                        <small class="text-muted fw-normal">(PNG, JPG, JPEG, WEBP, PDF - Max 15MB)</small>
                    </label>
                    <input type="file" name="education[${index}][certificate_photo]" class="form-control" 
                           accept="application/pdf,image/jpeg,image/png,image/jpg,image/webp"
                           onchange="previewArrayFile(this)">
                    <div class="file-preview-target mt-2 d-none">
                        <img src="" alt="Certificate Preview" class="img-preview-box">
                    </div>
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
    if (entry) {
        entry.remove();
    }
    updateRemoveButtons();
}

// Experience dynamic rows
function addExperience() {
    const container = document.getElementById('experienceContainer');
    const index = experienceCount;
    
    const html = `
        <div class="experience-entry border rounded p-3 mb-3 bg-light" data-index="${index}">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h6 class="mb-0 fw-bold"><i class="fa-solid fa-building me-2"></i>Experience Record #${index + 1}</h6>
                <button type="button" class="btn btn-sm btn-outline-danger remove-experience" onclick="removeExperience(${index})">
                    <i class="fa-solid fa-trash me-1"></i>Remove
                </button>
            </div>
            
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Job Title</label>
                    <input type="text" name="experience[${index}][job_title]" class="form-control" 
                           placeholder="e.g., Site Engineer, Project Manager">
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Company Name</label>
                    <input type="text" name="experience[${index}][company_name]" class="form-control" 
                           placeholder="e.g., ABC Construction Plc">
                </div>
                <div class="col-md-12">
                    <label class="form-label fw-semibold">Location</label>
                    <input type="text" name="experience[${index}][location]" class="form-control" 
                           placeholder="e.g., Addis Ababa, Ethiopia">
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
                    <div class="form-check">
                        <input type="checkbox" name="experience[${index}][is_current]" class="form-check-input" 
                               id="is_current_${index}" value="1" onchange="toggleEndDate(${index})">
                        <label class="form-check-label fw-semibold" for="is_current_${index}">
                            Current
                        </label>
                    </div>
                </div>
                <div class="col-12">
                    <label class="form-label fw-semibold">Key Responsibilities</label>
                    <textarea name="experience[${index}][responsibilities]" class="form-control" rows="2" 
                              placeholder="Describe main duties, projects handled, and achievements..."></textarea>
                </div>
                
                <!-- Reference Section -->
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Reference Name</label>
                    <input type="text" name="experience[${index}][reference_name]" class="form-control" 
                           placeholder="e.g., John Doe (Direct Supervisor)">
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Reference Phone</label>
                    <input type="text" name="experience[${index}][reference_phone]" class="form-control" 
                           placeholder="+251 911 234 567">
                </div>

                <!-- Document Upload -->
                <div class="col-12">
                    <label class="form-label fw-semibold">
                        <i class="fa-solid fa-file-lines text-primary me-1"></i>Experience Certificate / Recommendation Letter
                        <small class="text-muted fw-normal d-block">(PDF, PNG, JPG, WEBP - Max 15MB)</small>
                    </label>
                    <input type="file" name="experience[${index}][experience_letter]" class="form-control" 
                           accept="application/pdf,image/jpeg,image/png,image/jpg,image/webp"
                           onchange="previewArrayFile(this)">
                    <div class="file-preview-target mt-2 d-none">
                        <img src="" alt="Certificate Preview" class="img-preview-box">
                    </div>
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
    if (entry) {
        entry.remove();
    }
    updateRemoveButtons();
}

// Professional Licenses dynamic rows
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
                           placeholder="e.g., Ministry of Justice, Ethiopian Construction Authority, Transport Authority">
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
                    <label class="form-label fw-semibold">
                        <i class="fa-solid fa-file-shield text-success me-1"></i>Upload License Document / Certificate / Card Photo
                        <small class="text-muted fw-normal d-block">(PDF, PNG, JPG, WEBP - Max 15MB)</small>
                    </label>
                    <input type="file" name="licenses[${index}][license_document]" class="form-control" 
                           accept="application/pdf,image/jpeg,image/png,image/jpg,image/webp"
                           onchange="previewArrayFile(this)">
                    <div class="file-preview-target mt-2 d-none">
                        <img src="" alt="License Preview" class="img-preview-box">
                    </div>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Notes / Specialization / Scope of Practice</label>
                    <textarea name="licenses[${index}][notes]" class="form-control" rows="2" 
                              placeholder="e.g., Federal First Instance & High Court jurisdiction, Heavy Duty Machinery..."></textarea>
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
    if (entry) {
        entry.remove();
    }
    updateRemoveButtons();
}

function toggleEndDate(index) {
    const checkbox = document.getElementById(`is_current_${index}`);
    const endDateField = document.getElementById(`exp_end_date_${index}`);
    
    if (checkbox && endDateField) {
        endDateField.disabled = checkbox.checked;
        if (checkbox.checked) {
            endDateField.value = '';
        }
    }
}

function updateRemoveButtons() {
    const educationEntries = document.querySelectorAll('.education-entry');
    educationEntries.forEach((entry) => {
        const removeBtn = entry.querySelector('.remove-education');
        if (removeBtn) {
            removeBtn.style.display = educationEntries.length > 1 ? 'inline-block' : 'none';
        }
    });
    
    const experienceEntries = document.querySelectorAll('.experience-entry');
    experienceEntries.forEach((entry) => {
        const removeBtn = entry.querySelector('.remove-experience');
        if (removeBtn) {
            removeBtn.style.display = experienceEntries.length > 1 ? 'inline-block' : 'none';
        }
    });

    const licenseEntries = document.querySelectorAll('.license-entry');
    licenseEntries.forEach((entry) => {
        const removeBtn = entry.querySelector('.remove-license');
        if (removeBtn) {
            removeBtn.style.display = licenseEntries.length > 1 ? 'inline-block' : 'none';
        }
    });
    
    const assetEntries = document.querySelectorAll('.asset-entry');
    assetEntries.forEach((entry) => {
        const removeBtn = entry.querySelector('.remove-asset');
        if (removeBtn) {
            removeBtn.style.display = assetEntries.length > 1 ? 'inline-block' : 'none';
        }
    });
}

// ── Multi-Step Form Submit Handler ───────────────────────────────────────────
document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('employeeForm');
    if (!form) return;

    form.addEventListener('submit', function (e) {
        // Validate all steps in sequence; jump to first failing step
        for (let s = 1; s <= totalSteps; s++) {
            if (!validateStep(s)) {
                e.preventDefault();
                goToStep(s);
                return false;
            }
        }
        // All valid – allow native submit
    });
});
</script>
@endsection
