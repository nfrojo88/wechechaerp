@extends('layouts.app')

@section('title', 'My Profile - ' . ($employee ? $employee->full_name : $user->name))

@section('content')
<div class="container-fluid py-4">

    <!-- Alert Messages -->
    @if (session('success'))
        <div class="alert alert-success alert-dismissible fade show shadow-sm border-0 mb-4" role="alert">
            <div class="d-flex align-items-center">
                <i class="fa-solid fa-circle-check fa-lg me-2"></i>
                <div>{{ session('success') }}</div>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    @if (isset($errors) && $errors->any())
        <div class="alert alert-danger alert-dismissible fade show shadow-sm border-0 mb-4" role="alert">
            <div class="d-flex align-items-center">
                <i class="fa-solid fa-circle-exclamation fa-lg me-2"></i>
                <div>
                    <strong>Please correct the following errors:</strong>
                    <ul class="mb-0 mt-1 ps-3">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    @if($employee)
        {{-- GM Rejection Alert for Employee if applicable --}}
        @if($employee->gm_approval_status === 'rejected')
        <div class="alert alert-danger border-start border-4 border-danger shadow mb-4 fade show" role="alert">
            <div class="d-flex align-items-start gap-3">
                <div class="rounded-circle bg-danger bg-opacity-15 p-3 flex-shrink-0">
                    <i class="fa-solid fa-triangle-exclamation fa-xl text-danger"></i>
                </div>
                <div class="flex-grow-1">
                    <strong class="fs-6 text-danger d-block mb-1">
                        ⚠️ Profile Returned by General Manager — Correction Under Review
                    </strong>
                    <p class="mb-2 text-dark">
                        <i class="fa-solid fa-comment-dots me-1 text-danger"></i>
                        <strong>Instructions:</strong> {{ $employee->gm_rejection_reason }}
                    </p>
                    <small class="text-muted">
                        Please contact HR to update and resubmit your profile details.
                    </small>
                </div>
            </div>
        </div>
        @endif

        <!-- Header & Breadcrumbs -->
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
            <div class="d-flex align-items-center">
                <a href="{{ url('/') }}" class="btn btn-sm btn-outline-secondary me-3 shadow-sm rounded-circle p-2 d-flex align-items-center justify-content-center" style="width: 36px; height: 36px;">
                    <i class="fa-solid fa-arrow-left"></i>
                </a>
                <div class="me-3">
                    <img src="{{ $employee->profile_picture_url }}" alt="{{ $employee->full_name }}"
                         style="width:58px;height:58px;border-radius:50%;object-fit:cover;border:2px solid #0d6efd;"
                         class="shadow-sm">
                </div>
                <div>
                    <h1 class="h3 mb-0 fw-bold text-gray-800">{{ $employee->full_name }}</h1>
                    <div class="d-flex flex-wrap align-items-center gap-2 mt-1">
                        <span class="text-muted fw-semibold">{{ $employee->employee_code }} • {{ $employee->role_title ?? 'Employee' }}</span>
                        @if($employee->is_approved_by_gm)
                            <span class="badge bg-success"><i class="fa-solid fa-circle-check me-1"></i>GM Approved</span>
                        @elseif($employee->gm_approval_status === 'rejected')
                            <span class="badge bg-danger"><i class="fa-solid fa-triangle-exclamation me-1"></i>Returned by GM</span>
                        @else
                            <span class="badge bg-warning text-dark"><i class="fa-solid fa-clock me-1"></i>Awaiting GM Approval</span>
                        @endif
                        @if(isset($assignedPettyCash) && $assignedPettyCash->isNotEmpty())
                            <span class="badge bg-success bg-gradient"><i class="fa-solid fa-wallet me-1"></i>Petty Cash Custodian</span>
                        @endif
                    </div>
                </div>
            </div>
            @if(isset($assignedPettyCash) && $assignedPettyCash->isNotEmpty())
            <div class="d-flex align-items-center gap-2">
                <div class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 px-3 py-2 rounded-pill font-monospace fs-6">
                    <i class="fa-solid fa-wallet me-1"></i> Available: <strong>ETB {{ number_format($pettyCashBalance, 2) }}</strong>
                </div>
            </div>
            @endif
        </div>

        @if(isset($assignedPettyCash) && $assignedPettyCash->isNotEmpty())
        <!-- Assigned Petty Cash Available Amount Card (Only for Petty Cash) -->
        <div class="card border-0 shadow-sm rounded-4 mb-4 overflow-hidden" style="background: linear-gradient(135deg, #065f46 0%, #047857 50%, #059669 100%);">
            <div class="card-body p-4 text-white">
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
                    <div class="d-flex align-items-center gap-3">
                        <div class="rounded-circle bg-white bg-opacity-20 p-3 d-flex align-items-center justify-content-center shadow-sm" style="width: 60px; height: 60px;">
                            <i class="fa-solid fa-wallet fa-2xl text-warning"></i>
                        </div>
                        <div>
                            <div class="text-white-50 small fw-bold text-uppercase tracking-wider">
                                <i class="fa-solid fa-shield-halved me-1"></i> Assigned Petty Cash Custodian
                            </div>
                            <h2 class="fw-bold mb-0 text-white font-monospace">ETB {{ number_format($pettyCashBalance, 2) }}</h2>
                            <div class="text-white small mt-1 opacity-90">
                                <i class="fa-solid fa-circle-check text-warning me-1"></i> Available Petty Cash Balance allocated to your profile
                            </div>
                        </div>
                    </div>
                    <div class="d-flex flex-column align-items-md-end gap-2">
                        @foreach($assignedPettyCash as $coa)
                            <span class="badge bg-white bg-opacity-20 text-white border border-white border-opacity-25 px-3 py-2 rounded-pill font-monospace shadow-xs">
                                <i class="fa-solid fa-vault me-1 text-warning"></i> {{ $coa->name }} ({{ $coa->code }}): <strong>ETB {{ number_format($coa->current_balance, 2) }}</strong>
                            </span>
                        @endforeach
                        <a href="{{ url('/expenses') }}" class="btn btn-sm btn-light rounded-pill px-3 fw-bold text-success mt-1 shadow-sm">
                            <i class="fa-solid fa-receipt me-1"></i> View Petty Cash Expenses
                        </a>
                    </div>
                </div>
            </div>
        </div>
        @endif

        <div class="row g-4">
            {{-- Left Column: Detailed Employee Information (Read-Only) --}}
            <div class="col-lg-8">

                {{-- Employment Information Card --}}
                <div class="card border-0 shadow-sm rounded-4 mb-4">
                    <div class="card-header bg-white py-3 border-0 rounded-top-4">
                        <h5 class="mb-0 fw-bold"><i class="fa-solid fa-briefcase text-primary me-2"></i>Employment Information</h5>
                    </div>
                    <div class="card-body p-4 pt-1">
                        <div class="row g-4">
                            <div class="col-md-6">
                                <small class="text-muted d-block mb-1">Department</small>
                                <h6 class="mb-0 fw-bold text-dark">{{ $employee->department ?? 'N/A' }}</h6>
                            </div>
                            <div class="col-md-6">
                                <small class="text-muted d-block mb-1">Employment Type</small>
                                <h6 class="mb-0 fw-bold text-dark">
                                    @php
                                        $types = ['permanent' => 'Permanent', 'contract' => 'Contract', 'daily' => 'Daily Worker'];
                                    @endphp
                                    {{ $types[$employee->employment_type] ?? ucfirst($employee->employment_type ?? 'Permanent') }}
                                </h6>
                            </div>
                            <div class="col-md-6">
                                <small class="text-muted d-block mb-1">Date of Joining</small>
                                <h6 class="mb-0 fw-bold text-dark">{{ $employee->date_of_joining ? $employee->date_of_joining->format('d M Y') : 'N/A' }}</h6>
                            </div>
                            <div class="col-md-6">
                                <small class="text-muted d-block mb-1">Status</small>
                                <div class="d-flex align-items-center gap-1">
                                    @if($employee->status === 'active')
                                        <span class="badge bg-success px-2 py-1">Active</span>
                                    @elseif($employee->status === 'suspended')
                                        <span class="badge bg-warning px-2 py-1">Suspended</span>
                                    @else
                                        <span class="badge bg-danger px-2 py-1">Terminated</span>
                                    @endif

                                    @if($employee->is_approved_by_gm)
                                        <span class="badge bg-success"><i class="fa-solid fa-check-circle me-1"></i>Approved by GM</span>
                                    @else
                                        <span class="badge bg-warning text-dark"><i class="fa-solid fa-clock me-1"></i>Pending GM Approval</span>
                                    @endif
                                </div>
                            </div>
                            @if($employee->project)
                            <div class="col-md-6">
                                <small class="text-muted d-block mb-1">Assigned Project</small>
                                <h6 class="mb-0 fw-bold text-primary"><i class="fa-solid fa-city me-1"></i>{{ $employee->project->name }}</h6>
                            </div>
                            @endif
                            @if($employee->site_assignment)
                            <div class="col-md-6">
                                <small class="text-muted d-block mb-1">Site Assignment</small>
                                <h6 class="mb-0 fw-bold text-dark">{{ $employee->site_assignment }}</h6>
                            </div>
                            @endif
                        </div>
                    </div>
                </div>

                {{-- Contact Information Card --}}
                <div class="card border-0 shadow-sm rounded-4 mb-4">
                    <div class="card-header bg-white py-3 border-0 rounded-top-4">
                        <h5 class="mb-0 fw-bold"><i class="fa-solid fa-phone text-primary me-2"></i>Contact Information</h5>
                    </div>
                    <div class="card-body p-4 pt-1">
                        <div class="row g-4">
                            <div class="col-md-6">
                                <small class="text-muted d-block mb-1">Phone</small>
                                <h6 class="mb-0 fw-bold text-dark">{{ $employee->phone ?? 'N/A' }}</h6>
                            </div>
                            <div class="col-md-6">
                                <small class="text-muted d-block mb-1">Email</small>
                                <h6 class="mb-0 fw-bold text-dark">{{ $employee->email ?? ($user->email ?? 'N/A') }}</h6>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Salary Information Card --}}
                <div class="card border-0 shadow-sm rounded-4 mb-4">
                    <div class="card-header bg-white py-3 border-0 rounded-top-4">
                        <h5 class="mb-0 fw-bold"><i class="fa-solid fa-money-bill-wave text-success me-2"></i>Salary Information</h5>
                    </div>
                    <div class="card-body p-4 pt-1">
                        <div class="row g-4">
                            <div class="col-md-6">
                                <small class="text-muted d-block mb-1">Monthly Base Salary</small>
                                <h6 class="mb-0 fw-bold text-success fs-5">Br {{ number_format($employee->basic_salary, 2) }}</h6>
                            </div>
                            @if($employee->bank_name || $employee->account_number)
                            <div class="col-md-6">
                                <small class="text-muted d-block mb-1">Bank Account</small>
                                <h6 class="mb-0 fw-bold text-dark">{{ $employee->bank_name ?? 'CBE' }} - {{ $employee->account_number ?? 'N/A' }}</h6>
                            </div>
                            @endif

                            @if($employee->transport_allowance > 0 || $employee->house_allowance > 0 || $employee->position_allowance > 0)
                            <div class="col-12 mt-2">
                                <small class="text-muted d-block mb-2">Monthly Allowances</small>
                                <div class="d-flex flex-wrap gap-2">
                                    @if($employee->transport_allowance > 0)
                                        <span class="badge bg-light text-dark border p-2"><i class="fa-solid fa-car me-1 text-primary"></i>Transport: <strong>Br {{ number_format($employee->transport_allowance, 2) }}</strong></span>
                                    @endif
                                    @if($employee->house_allowance > 0)
                                        <span class="badge bg-light text-dark border p-2"><i class="fa-solid fa-house me-1 text-info"></i>Housing: <strong>Br {{ number_format($employee->house_allowance, 2) }}</strong></span>
                                    @endif
                                    @if($employee->position_allowance > 0)
                                        <span class="badge bg-light text-dark border p-2"><i class="fa-solid fa-briefcase me-1 text-warning"></i>Position: <strong>Br {{ number_format($employee->position_allowance, 2) }}</strong></span>
                                    @endif
                                </div>
                            </div>
                            @endif
                        </div>
                    </div>
                </div>

                {{-- Guarantee Letter Status Card --}}
                @if($employee->guarantee_letter_required)
                <div class="card border-0 shadow-sm rounded-4 mb-4 @if($employee->is_guarantee_overdue) border-start border-4 border-danger @elseif($employee->show_guarantee_warning) border-start border-4 border-warning @endif">
                    <div class="card-header bg-white py-3 border-0 rounded-top-4">
                        <h5 class="mb-0 fw-bold"><i class="fa-solid fa-shield-halved text-secondary me-2"></i>Guarantee Letter Status</h5>
                    </div>
                    <div class="card-body p-4 pt-1">
                        @if($employee->guarantee_letter)
                            <div class="alert alert-success border-0 rounded-3 mb-3 d-flex align-items-center">
                                <i class="fa-solid fa-check-circle fa-lg me-3 text-success"></i>
                                <div>
                                    <strong>Guarantee Letter Submitted & Verified</strong>
                                    <div class="small text-muted">Submitted on: {{ $employee->guarantee_letter_submitted_at ? $employee->guarantee_letter_submitted_at->format('d M Y') : 'Verified' }}</div>
                                </div>
                            </div>
                            <a href="{{ $employee->guarantee_letter_url }}" target="_blank" class="btn btn-sm btn-outline-primary shadow-sm rounded-3 px-3">
                                <i class="fa-solid fa-file-pdf me-1"></i>View Guarantee Document
                            </a>
                        @elseif($employee->is_guarantee_overdue)
                            <div class="alert alert-danger border-0 rounded-3 mb-3">
                                <i class="fa-solid fa-exclamation-circle me-2"></i>
                                <strong>OVERDUE!</strong> Guarantee letter was due {{ abs($employee->days_until_guarantee_deadline) }} days ago. Login access has been flagged until submission.
                            </div>
                            <p class="text-muted small mb-3">
                                <i class="fa-solid fa-calendar me-1"></i> Joined: {{ $employee->date_of_joining ? $employee->date_of_joining->format('d M Y') : 'N/A' }} | 
                                <i class="fa-solid fa-clock me-1"></i> Deadline was: {{ $employee->date_of_joining ? $employee->date_of_joining->addDays(30)->format('d M Y') : 'N/A' }}
                            </p>
                            @can('hr.manage')
                            <form action="{{ route('employees.upload-guarantee', $employee) }}" method="POST" enctype="multipart/form-data">
                                @csrf
                                <div class="mb-3">
                                    <label class="form-label fw-semibold">Upload Guarantee Letter <span class="text-danger">*</span></label>
                                    <input type="file" name="guarantee_letter" class="form-control" required accept="application/pdf,image/jpeg,image/png,image/jpg">
                                    <small class="text-muted">PDF or Image (Max 10MB)</small>
                                </div>
                                <button type="submit" class="btn btn-danger btn-sm px-3">
                                    <i class="fa-solid fa-upload me-1"></i>Upload Document
                                </button>
                            </form>
                            @else
                            <div class="small text-muted"><i class="fa-solid fa-circle-info me-1"></i>Please submit your physical guarantee letter document to the HR department.</div>
                            @endcan
                        @else
                            <div class="alert alert-info border-0 rounded-3 mb-2">
                                <i class="fa-solid fa-info-circle me-2"></i>Guarantee letter due in <strong>{{ $employee->days_until_guarantee_deadline ?? 30 }} days</strong>.
                            </div>
                        @endif
                    </div>
                </div>
                @endif

                {{-- Educational Background Card --}}
                @if($employee->education && $employee->education->count() > 0)
                <div class="card border-0 shadow-sm rounded-4 mb-4">
                    <div class="card-header bg-white py-3 border-0 rounded-top-4 d-flex justify-content-between align-items-center">
                        <h5 class="mb-0 fw-bold"><i class="fa-solid fa-graduation-cap text-primary me-2"></i>Educational Background</h5>
                        <span class="badge bg-primary rounded-pill px-3">{{ $employee->education->count() }} Record(s)</span>
                    </div>
                    <div class="card-body p-4 pt-1">
                        @foreach($employee->education as $edu)
                        <div class="border-start border-4 border-primary ps-3 mb-4 pb-3 {{ !$loop->last ? 'border-bottom' : '' }}">
                            <div class="row align-items-start">
                                <div class="col-md-8">
                                    <h6 class="mb-1 fw-bold text-dark">
                                        <i class="fa-solid fa-award text-warning me-2"></i>
                                        {{ $edu->degree_level }} in {{ $edu->field_of_study }}
                                        @if($edu->is_verified)
                                            <span class="badge bg-success ms-2"><i class="fa-solid fa-check me-1"></i>Verified</span>
                                        @endif
                                    </h6>
                                    <p class="text-muted mb-2 small">
                                        <i class="fa-solid fa-building me-1"></i>{{ $edu->institution_name }}
                                        @if($edu->location)
                                            • <i class="fa-solid fa-map-marker-alt me-1"></i>{{ $edu->location }}
                                        @endif
                                    </p>
                                    <small class="text-muted d-block">
                                        <i class="fa-solid fa-calendar me-1"></i>{{ $edu->duration }}
                                        @if($edu->grade_gpa)
                                            • Grade/GPA: <strong>{{ $edu->grade_gpa }}</strong>
                                        @endif
                                    </small>
                                    @if($edu->description)
                                        <p class="mt-2 mb-0 small text-secondary">{{ $edu->description }}</p>
                                    @endif
                                </div>
                                <div class="col-md-4 text-md-end mt-2 mt-md-0">
                                    @if($edu->certificate_photo)
                                        <a href="{{ $edu->certificate_url }}" target="_blank" class="btn btn-sm btn-outline-primary shadow-sm rounded-3">
                                            <i class="fa-solid fa-image me-1"></i>View Certificate
                                        </a>
                                    @endif
                                </div>
                            </div>
                        </div>
                        @endforeach
                    </div>
                </div>
                @endif
                {{-- Professional Licenses Card (Standalone) --}}
                @php
                    $dedicatedLicenses = $employee->licenses ?? collect();
                    $legacyLicenses = $employee->experience ? $employee->experience->filter(fn($exp) => $exp->license_number || $exp->license_document)->map(function($exp) {
                        return (object)[
                            'id'                   => $exp->id,
                            'license_name'         => 'Professional License (' . $exp->job_title . ')',
                            'issuing_organization' => $exp->company_name,
                            'license_number'       => $exp->license_number,
                            'issue_date'           => null,
                            'expiry_date'          => $exp->license_expiry,
                            'is_expired'           => $exp->is_license_expired,
                            'license_url'          => $exp->license_url,
                            'license_document'     => $exp->license_document,
                            'notes'                => null,
                        ];
                    }) : collect();
                    $allLicensesList = $dedicatedLicenses->isNotEmpty() ? $dedicatedLicenses : $legacyLicenses;
                @endphp
                @if($allLicensesList->count() > 0)
                <div class="card border-0 shadow-sm rounded-4 mb-4">
                    <div class="card-header bg-white py-3 border-0 rounded-top-4 d-flex justify-content-between align-items-center">
                        <h5 class="mb-0 fw-bold">
                            <i class="fa-solid fa-id-card-clip text-warning me-2"></i>Professional Licenses
                        </h5>
                        <span class="badge bg-warning text-dark rounded-pill px-3">{{ $allLicensesList->count() }} License(s)</span>
                    </div>
                    <div class="card-body p-4 pt-2">
                        <div class="row g-3">
                            @foreach($allLicensesList as $lic)
                            <div class="col-md-6">
                                <div class="border rounded-3 p-3 h-100 border-start border-4 border-warning bg-warning bg-opacity-10">
                                    {{-- License Header --}}
                                    <div class="d-flex align-items-center gap-2 mb-2">
                                        <div class="rounded-circle bg-warning bg-opacity-25 d-flex align-items-center justify-content-center flex-shrink-0" style="width:38px;height:38px;">
                                            <i class="fa-solid fa-certificate text-warning fa-lg"></i>
                                        </div>
                                        <div class="overflow-hidden">
                                            <div class="fw-bold text-dark text-truncate">{{ $lic->license_name }}</div>
                                            @if($lic->issuing_organization)
                                                <div class="text-muted small text-truncate"><i class="fa-solid fa-building me-1"></i>{{ $lic->issuing_organization }}</div>
                                            @endif
                                        </div>
                                    </div>

                                    {{-- License Details --}}
                                    <div class="d-flex flex-column gap-1 small mt-2">
                                        @if($lic->license_number)
                                        <div class="d-flex align-items-center gap-2">
                                            <span class="text-muted fw-semibold" style="min-width:80px;">License #:</span>
                                            <span class="badge bg-dark font-monospace">{{ $lic->license_number }}</span>
                                        </div>
                                        @endif

                                        @if(!empty($lic->issue_date))
                                        <div class="d-flex align-items-center gap-2">
                                            <span class="text-muted fw-semibold" style="min-width:80px;">Issued:</span>
                                            <span class="text-secondary">{{ is_object($lic->issue_date) ? $lic->issue_date->format('d M Y') : $lic->issue_date }}</span>
                                        </div>
                                        @endif

                                        @if($lic->expiry_date)
                                        <div class="d-flex align-items-center gap-2">
                                            <span class="text-muted fw-semibold" style="min-width:80px;">Expiry:</span>
                                            <span class="fw-semibold text-dark">{{ is_object($lic->expiry_date) ? $lic->expiry_date->format('d M Y') : \Carbon\Carbon::parse($lic->expiry_date)->format('d M Y') }}</span>
                                            @php
                                                $expDate = is_object($lic->expiry_date) ? $lic->expiry_date : \Carbon\Carbon::parse($lic->expiry_date);
                                                $isExp = $expDate->isPast();
                                                $daysLeft = now()->diffInDays($expDate, false);
                                            @endphp
                                            @if($isExp)
                                                <span class="badge bg-danger ms-1">Expired</span>
                                            @elseif($daysLeft <= 90)
                                                <span class="badge bg-warning text-dark ms-1">Expiring Soon</span>
                                            @else
                                                <span class="badge bg-success ms-1">Valid</span>
                                            @endif
                                        </div>
                                        @endif

                                        @if(!empty($lic->notes))
                                        <div class="mt-1 text-secondary" style="font-size:0.8rem;">
                                            <i class="fa-solid fa-circle-info me-1 text-warning"></i>{{ $lic->notes }}
                                        </div>
                                        @endif
                                    </div>

                                    {{-- View Document Button --}}
                                    @if($lic->license_document)
                                    <div class="mt-3">
                                        <a href="{{ $lic->license_url }}" target="_blank" class="btn btn-sm btn-outline-warning text-dark fw-semibold rounded-3">
                                            <i class="fa-solid fa-file-shield me-1"></i>View License Document
                                        </a>
                                    </div>
                                    @endif
                                </div>
                            </div>
                            @endforeach
                        </div>
                    </div>
                </div>
                @endif

                {{-- Work Experience Card (Clean: Experience only) --}}
                @if($employee->experience && $employee->experience->count() > 0)
                <div class="card border-0 shadow-sm rounded-4 mb-4">
                    <div class="card-header bg-white py-3 border-0 rounded-top-4 d-flex justify-content-between align-items-center">
                        <h5 class="mb-0 fw-bold"><i class="fa-solid fa-briefcase text-success me-2"></i>Work Experience</h5>
                        <span class="badge bg-success rounded-pill px-3">{{ $employee->experience->count() }} Position(s)</span>
                    </div>
                    <div class="card-body p-4 pt-1">
                        @foreach($employee->experience as $exp)
                        <div class="border-start border-4 border-success ps-3 mb-4 pb-3 {{ !$loop->last ? 'border-bottom' : '' }}">
                            <div class="row align-items-start">
                                <div class="col-md-8">
                                    <h6 class="mb-1 fw-bold text-dark">
                                        <i class="fa-solid fa-user-tie text-info me-2"></i>
                                        {{ $exp->job_title }}
                                        @if($exp->is_current)
                                            <span class="badge bg-info ms-2">Current</span>
                                        @endif
                                    </h6>
                                    <p class="text-muted mb-2 small">
                                        <i class="fa-solid fa-building me-1"></i>{{ $exp->company_name }}
                                        @if($exp->location)
                                            • <i class="fa-solid fa-map-marker-alt me-1"></i>{{ $exp->location }}
                                        @endif
                                    </p>
                                    <small class="text-muted d-block mb-2">
                                        <i class="fa-solid fa-calendar me-1"></i>{{ $exp->period }}
                                        <span class="badge bg-secondary ms-2">{{ $exp->duration }}</span>
                                    </small>

                                    @if($exp->responsibilities)
                                        <p class="mb-2 small text-secondary">{{ Str::limit($exp->responsibilities, 250) }}</p>
                                    @endif
                                </div>
                                <div class="col-md-4 text-md-end mt-2 mt-md-0">
                                    @if($exp->experience_letter)
                                        <a href="{{ $exp->experience_letter_url }}" target="_blank" class="btn btn-sm btn-outline-primary shadow-sm rounded-3">
                                            <i class="fa-solid fa-file-lines me-1"></i>View Experience Letter
                                        </a>
                                    @endif
                                </div>
                            </div>
                        </div>
                        @endforeach
                    </div>
                </div>
                @endif

                {{-- Assigned Fixed Assets & Equipment Card --}}
                @php
                    $activeFixedUnits = $employee->assignedFixedAssets ?? collect();
                    $legacyActiveAssets = $employee->activeAssets() ?? collect();
                    $totalActiveAssetsCount = $activeFixedUnits->count() + $legacyActiveAssets->count();
                @endphp
                <div class="card border-0 shadow-sm rounded-4 mb-4">
                    <div class="card-header bg-white py-3 border-0 rounded-top-4 d-flex justify-content-between align-items-center">
                        <h5 class="mb-0 fw-bold"><i class="fa-solid fa-truck-monster text-warning me-2"></i>Assigned Fixed Assets & Equipment</h5>
                        <span class="badge bg-primary rounded-pill px-3">{{ $totalActiveAssetsCount }} Assigned</span>
                    </div>
                    <div class="card-body p-0">
                        @if($totalActiveAssetsCount > 0)
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0" style="font-size: 0.88rem;">
                                <thead class="table-light text-muted small text-uppercase">
                                    <tr>
                                        <th class="ps-4 py-3">Asset / Unit Code</th>
                                        <th class="py-3">Value</th>
                                        <th class="py-3">Condition</th>
                                        <th class="py-3">Assigned Date</th>
                                        <th class="py-3 pe-4 text-center">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($activeFixedUnits as $fUnit)
                                    @php
                                        $condBadge = $fUnit->condition_badge;
                                        $unitPrice = $fUnit->purchase_price ?? $fUnit->parentAsset->unit_cost ?? 0;
                                    @endphp
                                    <tr>
                                        <td class="ps-4 py-3">
                                            <div class="d-flex align-items-start gap-2">
                                                <span class="badge bg-dark font-monospace px-2 py-1">{{ $fUnit->unit_code }}</span>
                                                <div>
                                                    <strong class="text-dark d-block">{{ $fUnit->parentAsset->name ?? 'Fixed Asset' }}</strong>
                                                    <div class="d-flex flex-wrap align-items-center gap-1 mt-1">
                                                        <span class="badge bg-light text-secondary border px-1 py-0">{{ $fUnit->parentAsset->category ?? 'General' }}</span>
                                                        @if($fUnit->plate_number)
                                                            <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 px-1 py-0"><i class="fa-solid fa-car-side me-1"></i>{{ $fUnit->plate_number }}</span>
                                                        @endif
                                                        @if($fUnit->serial_number)
                                                            <span class="badge bg-secondary bg-opacity-10 text-secondary border px-1 py-0 font-monospace">S/N: {{ $fUnit->serial_number }}</span>
                                                        @endif
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="py-3 text-success fw-bold">
                                            Br {{ number_format($unitPrice, 2) }}
                                        </td>
                                        <td class="py-3">
                                            <span class="badge {{ $condBadge['class'] }}">{{ $condBadge['label'] }}</span>
                                        </td>
                                        <td class="py-3 text-muted small">
                                            {{ $fUnit->assigned_date ? $fUnit->assigned_date->format('d M Y') : 'N/A' }}
                                                                         <td class="py-3 pe-4 text-center">
                                            <button type="button"
                                                class="btn btn-sm btn-outline-warning rounded-pill px-2 py-1 btn-report-issue"
                                                style="font-size:0.75rem;"
                                                data-unit-id="{{ $fUnit->id }}"
                                                data-legacy-id=""
                                                data-asset-name="{{ $fUnit->parentAsset->name ?? 'Fixed Asset' }}"
                                                data-asset-code="{{ $fUnit->unit_code }}"
                                                title="Report maintenance issue for this asset">
                                                <i class="fa-solid fa-wrench me-1"></i>Report Issue
                                            </button>
                                        </td>
                                    </tr>
                                    @endforeach

                                    @foreach($legacyActiveAssets as $asset)
                                    @php
                                        $legacyPrice = $asset->product->unit_price ?? $asset->product->purchase_price ?? 0;
                                    @endphp
                                    <tr>
                                        <td class="ps-4 py-3">
                                            <strong class="text-dark d-block">{{ $asset->product->name ?? 'Asset' }}</strong>
                                            <span class="badge bg-light text-secondary border px-1 py-0">{{ $asset->product->type ?? 'General' }}</span>
                                        </td>
                                        <td class="py-3 text-success fw-bold">
                                            Br {{ number_format($legacyPrice, 2) }}
                                        </td>
                                        <td class="py-3">
                                            <span class="badge bg-success">In Use</span>
                                        </td>
                                        <td class="py-3 text-muted small">
                                            {{ $asset->assigned_date ? $asset->assigned_date->format('d M Y') : 'N/A' }}
                                        </td>
                                        <td class="py-3 pe-4 text-center">
                                            <button type="button"
                                                class="btn btn-sm btn-outline-warning rounded-pill px-2 py-1 btn-report-issue"
                                                style="font-size:0.75rem;"
                                                data-unit-id=""
                                                data-legacy-id="{{ $asset->id }}"
                                                data-asset-name="{{ $asset->product->name ?? 'Asset' }}"
                                                data-asset-code="{{ $asset->product->code ?? '' }}"
                                                title="Report maintenance issue">
                                                <i class="fa-solid fa-wrench me-1"></i>Report Issue
                                            </button>
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        @else
                        <div class="text-center py-4 text-muted">
                            <i class="fa-solid fa-box-open fa-2x mb-2 opacity-25 d-block"></i>
                            <span class="small">No equipment or company assets currently assigned.</span>
                        </div>
                        @endif

                        {{-- My Maintenance Requests Summary --}}
                        @if($maintenanceRequests->count() > 0)
                        <div class="border-top px-4 py-3">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <small class="fw-bold text-muted"><i class="fa-solid fa-wrench me-1 text-warning"></i>My Maintenance Requests</small>
                                <span class="badge bg-warning text-dark rounded-pill">{{ $maintenanceRequests->count() }}</span>
                            </div>
                            <div class="d-flex flex-column gap-2">
                                @foreach($maintenanceRequests as $mr)
                                @php $sb = $mr->status_badge; $ub = $mr->urgency_badge; @endphp
                                <div class="d-flex align-items-center justify-content-between rounded-3 border px-3 py-2" style="background: #fafafa; font-size:0.82rem;">
                                    <div class="d-flex align-items-center gap-2">
                                        <span class="badge {{ $sb['class'] }} rounded-pill"><i class="fa-solid {{ $sb['icon'] }} me-1"></i>{{ $sb['label'] }}</span>
                                        <span class="fw-semibold text-dark">{{ $mr->asset_name }}</span>
                                        @if($mr->asset_code)
                                            <span class="badge bg-dark font-monospace">{{ $mr->asset_code }}</span>
                                        @endif
                                        <span class="text-muted">— {{ $mr->issue_type_label }}</span>
                                    </div>
                                    <div class="d-flex align-items-center gap-2">
                                        <span class="badge {{ $ub['class'] }} rounded-pill" style="font-size:0.7rem;">{{ $ub['label'] }}</span>
                                        <small class="text-muted">{{ $mr->created_at->format('d M Y') }}</small>
                                        <a href="{{ route('maintenance.show', $mr) }}" class="btn btn-xs btn-outline-secondary py-0 px-2" style="font-size:0.72rem;">View</a>
                                    </div>
                                </div>
                                @endforeach
                            </div>
                        </div>
                        @endif
                    </div>
                </div>

            </div>

            {{-- Right Column: Profile Sidebar Card, Summary Stat Cards & Password Security --}}
            <div class="col-lg-4">

                {{-- Profile Identity Card --}}
                <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
                    <div class="p-4 text-center" style="background: linear-gradient(135deg, #1e3a8a 0%, #3b82f6 100%); color: #fff;">
                        <div class="rounded-circle mx-auto d-flex align-items-center justify-content-center shadow-lg border border-3 border-white mb-3"
                             style="width: 85px; height: 85px; font-size: 2.2rem; background: #0f172a; color: #fff;">
                            {{ strtoupper(substr($employee->full_name ?? 'U', 0, 1)) }}
                        </div>
                        <h4 class="fw-bold mb-1 text-white">{{ $employee->full_name }}</h4>
                        <div class="d-flex justify-content-center gap-1 mt-2">
                            @if($employee->is_approved_by_gm)
                                <span class="badge bg-success"><i class="fa-solid fa-circle-check me-1"></i>Approved GM</span>
                            @else
                                <span class="badge bg-warning text-dark"><i class="fa-solid fa-clock me-1"></i>Pending GM</span>
                            @endif
                            <span class="badge bg-success bg-opacity-75">Active</span>
                        </div>
                    </div>
                    <div class="card-body p-4 bg-white">
                        <ul class="list-unstyled mb-0 d-flex flex-column gap-3 small">
                            <li class="d-flex align-items-center">
                                <i class="fa-solid fa-id-card text-muted me-3 fs-6"></i>
                                <span class="text-muted me-2">ID:</span>
                                <strong class="text-dark font-monospace">{{ $employee->employee_code }}</strong>
                            </li>
                            <li class="d-flex align-items-center">
                                <i class="fa-solid fa-phone text-muted me-3 fs-6"></i>
                                <span class="text-muted me-2">Phone:</span>
                                <strong class="text-dark">{{ $employee->phone ?? 'N/A' }}</strong>
                            </li>
                            <li class="d-flex align-items-center">
                                <i class="fa-solid fa-calendar text-muted me-3 fs-6"></i>
                                <span class="text-muted me-2">Joined:</span>
                                <strong class="text-dark">{{ $employee->date_of_joining ? $employee->date_of_joining->format('d M Y') : 'N/A' }}</strong>
                            </li>
                            <li class="d-flex align-items-center">
                                <i class="fa-solid fa-building text-muted me-3 fs-6"></i>
                                <span class="text-muted me-2">Dept:</span>
                                <strong class="text-dark">{{ $employee->department ?? 'N/A' }}</strong>
                            </li>
                        </ul>
                    </div>
                @if(isset($assignedPettyCash) && $assignedPettyCash->isNotEmpty())
                {{-- Petty Cash Sidebar Widget (Only for Petty Cash) --}}
                <div class="card border-0 shadow-sm rounded-4 mb-4 overflow-hidden border-start border-4 border-success">
                    <div class="card-body p-3">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <div class="d-flex align-items-center gap-2">
                                <div class="rounded-circle bg-success bg-opacity-10 p-2 text-success">
                                    <i class="fa-solid fa-wallet fa-lg"></i>
                                </div>
                                <span class="fw-bold text-dark small">Petty Cash Available</span>
                            </div>
                            <span class="badge bg-success bg-opacity-10 text-success rounded-pill font-monospace" style="font-size: 0.72rem;">Assigned</span>
                        </div>
                        <h4 class="fw-bold text-success font-monospace mb-1">ETB {{ number_format($pettyCashBalance, 2) }}</h4>
                        <div class="text-muted small" style="font-size: 0.75rem;">
                            {{ $assignedPettyCash->pluck('name')->implode(', ') }}
                        </div>
                    </div>
                </div>
                @endif

                {{-- 4 Stat Cards in 2x2 Grid --}}
                <div class="row g-3 mb-4">
                    <div class="col-6">
                        <div class="card border-0 shadow-sm rounded-4 text-center p-3 h-100" style="background: #eef2ff;">
                            <div class="text-primary fs-3 mb-1"><i class="fa-solid fa-desktop"></i></div>
                            <h3 class="fw-bold mb-0 text-primary">{{ $totalActiveAssetsCount }}</h3>
                            <small class="text-muted fw-semibold">Assets Assigned</small>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="card border-0 shadow-sm rounded-4 text-center p-3 h-100" style="background: #f0fdf4;">
                            <div class="text-success fs-3 mb-1"><i class="fa-solid fa-graduation-cap"></i></div>
                            <h3 class="fw-bold mb-0 text-success">{{ $employee->education ? $employee->education->count() : 0 }}</h3>
                            <small class="text-muted fw-semibold">Education Records</small>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="card border-0 shadow-sm rounded-4 text-center p-3 h-100" style="background: #fffbeb;">
                            <div class="text-warning fs-3 mb-1"><i class="fa-solid fa-briefcase"></i></div>
                            <h3 class="fw-bold mb-0 text-warning">{{ $employee->experience ? $employee->experience->count() : 0 }}</h3>
                            <small class="text-muted fw-semibold">Work Positions</small>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="card border-0 shadow-sm rounded-4 text-center p-3 h-100" style="background: #fdf2f8;">
                            <div class="text-danger fs-3 mb-1"><i class="fa-solid fa-clock"></i></div>
                            <h4 class="fw-bold mb-0 text-danger" style="font-size: 1.3rem;">
                                {{ $totalExperienceYears > 0 ? "{$totalExperienceYears}y " : '' }}{{ $totalExperienceMonths }}m
                            </h4>
                            <small class="text-muted fw-semibold">Total Experience</small>
                            @if(isset($companyTenureYears) && ($companyTenureYears > 0 || $companyTenureRem > 0))
                            <div class="mt-1 pt-1 border-top">
                                <small class="text-muted d-block" style="font-size:0.72rem; line-height:1.3;">
                                    <i class="fa-solid fa-building me-1 text-primary"></i>
                                    <strong>This Company:</strong><br>
                                    {{ $companyTenureYears > 0 ? "{$companyTenureYears}y " : '' }}{{ $companyTenureRem }}m
                                </small>
                            </div>
                            @endif
                        </div>
                    </div>
                </div>

                {{-- Change Password Security Card (Interactive Form) --}}
                <div class="card border-0 shadow-sm rounded-4 mb-4">
                    <div class="card-header bg-white py-3 border-0 rounded-top-4">
                        <div class="d-flex align-items-center">
                            <div class="rounded-3 p-2 bg-warning bg-opacity-15 text-warning-emphasis me-3">
                                <i class="fa-solid fa-key fa-lg text-warning"></i>
                            </div>
                            <div>
                                <h5 class="card-title fw-bold mb-0">Security & Password</h5>
                                <small class="text-muted">Update your login credentials</small>
                            </div>
                        </div>
                    </div>
                    <div class="card-body p-4 pt-2">
                        <form action="{{ route('profile.password.update') }}" method="POST">
                            @csrf
                            @method('PUT')

                            <!-- Current Password -->
                            <div class="mb-3">
                                <label for="current_password" class="form-label fw-semibold small">Current Password <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light border-end-0"><i class="fa-solid fa-lock text-muted"></i></span>
                                    <input type="password" 
                                           class="form-control border-start-0 border-end-0 ps-0 {{ (isset($errors) && $errors->has('current_password')) ? 'is-invalid' : '' }}" 
                                           id="current_password" 
                                           name="current_password" 
                                           placeholder="Current password"
                                           required>
                                    <button class="btn btn-outline-secondary border-start-0 toggle-password" type="button" data-target="current_password">
                                        <i class="fa-regular fa-eye"></i>
                                    </button>
                                </div>
                                @if(isset($errors) && $errors->has('current_password'))
                                    <div class="text-danger small mt-1">{{ $errors->first('current_password') }}</div>
                                @endif
                            </div>

                            <!-- New Password -->
                            <div class="mb-3">
                                <label for="password" class="form-label fw-semibold small">New Password <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light border-end-0"><i class="fa-solid fa-shield-halved text-muted"></i></span>
                                    <input type="password" 
                                           class="form-control border-start-0 border-end-0 ps-0 {{ (isset($errors) && $errors->has('password')) ? 'is-invalid' : '' }}" 
                                           id="password" 
                                           name="password" 
                                           placeholder="Minimum 8 characters"
                                           required>
                                    <button class="btn btn-outline-secondary border-start-0 toggle-password" type="button" data-target="password">
                                        <i class="fa-regular fa-eye"></i>
                                    </button>
                                </div>
                                @if(isset($errors) && $errors->has('password'))
                                    <div class="text-danger small mt-1">{{ $errors->first('password') }}</div>
                                @endif
                            </div>

                            <!-- Confirm Password -->
                            <div class="mb-4">
                                <label for="password_confirmation" class="form-label fw-semibold small">Confirm New Password <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light border-end-0"><i class="fa-solid fa-check-double text-muted"></i></span>
                                    <input type="password" 
                                           class="form-control border-start-0 border-end-0 ps-0" 
                                           id="password_confirmation" 
                                           name="password_confirmation" 
                                           placeholder="Confirm password"
                                           required>
                                    <button class="btn btn-outline-secondary border-start-0 toggle-password" type="button" data-target="password_confirmation">
                                        <i class="fa-regular fa-eye"></i>
                                    </button>
                                </div>
                            </div>

                            <button type="submit" class="btn btn-warning w-100 py-2 rounded-3 shadow-sm fw-bold text-dark">
                                <i class="fa-solid fa-key me-2"></i>Update Password
                            </button>
                        </form>
                    </div>
                </div>

            </div>
        </div>

    @else
        {{-- Fallback View for Users Without an Employee Record (e.g. Super Admin) --}}
        <div class="card border-0 shadow-sm rounded-4 mb-4 overflow-hidden" style="background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);">
            <div class="card-body p-4 text-white">
                <div class="d-flex align-items-center gap-4">
                    <div class="rounded-circle d-flex align-items-center justify-content-center fw-bold shadow-lg border border-3 border-white-50"
                         style="width: 80px; height: 80px; font-size: 2rem; background: #3b82f6; color: #fff;">
                        {{ strtoupper(substr($user->name ?? 'U', 0, 1)) }}
                    </div>
                    <div>
                        <h2 class="h4 mb-1 fw-bold text-white">{{ $user->name }}</h2>
                        <p class="mb-0 text-white-50"><i class="fa-regular fa-envelope me-1"></i>{{ $user->email }}</p>
                        <div class="mt-2">
                            @foreach($user->roles as $role)
                                <span class="badge bg-warning text-dark font-monospace text-uppercase me-1">
                                    <i class="fa-solid fa-shield-halved me-1"></i>{{ str_replace('_', ' ', $role->name) }}
                                </span>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row justify-content-center">
            <div class="col-lg-6">
                <div class="card border-0 shadow-sm rounded-4">
                    <div class="card-header bg-white py-3 border-0">
                        <h5 class="card-title fw-bold mb-0"><i class="fa-solid fa-key text-warning me-2"></i>Security & Password</h5>
                    </div>
                    <div class="card-body p-4">
                        <form action="{{ route('profile.password.update') }}" method="POST">
                            @csrf
                            @method('PUT')

                            <div class="mb-3">
                                <label for="current_password" class="form-label fw-semibold">Current Password <span class="text-danger">*</span></label>
                                <input type="password" class="form-control" id="current_password" name="current_password" required>
                            </div>

                            <div class="mb-3">
                                <label for="password" class="form-label fw-semibold">New Password <span class="text-danger">*</span></label>
                                <input type="password" class="form-control" id="password" name="password" required>
                            </div>

                            <div class="mb-4">
                                <label for="password_confirmation" class="form-label fw-semibold">Confirm New Password <span class="text-danger">*</span></label>
                                <input type="password" class="form-control" id="password_confirmation" name="password_confirmation" required>
                            </div>

                            <button type="submit" class="btn btn-warning w-100 py-2 fw-bold text-dark">
                                <i class="fa-solid fa-key me-2"></i>Update Password
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    @endif

</div>

{{-- Global Maintenance Report Modal --}}
<div class="modal fade" id="reportMaintenanceModal" tabindex="-1" aria-labelledby="reportMaintenanceModalLabel" aria-hidden="true" style="z-index: 1055;">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <form method="POST" action="{{ route('maintenance.store') }}">
                @csrf
                <input type="hidden" name="fixed_asset_unit_id" id="rm_unit_id" value="">
                <input type="hidden" name="employee_asset_id" id="rm_legacy_id" value="">
                <input type="hidden" name="asset_name" id="rm_asset_name_input" value="">
                <input type="hidden" name="asset_code" id="rm_asset_code_input" value="">
                
                <div class="modal-header border-0 px-4 py-3" style="background: linear-gradient(135deg, #f59e0b, #d97706); color:#fff;">
                    <h5 class="modal-title fs-6 fw-bold mb-0" id="reportMaintenanceModalLabel">
                        <i class="fa-solid fa-wrench me-2"></i>Report Maintenance Issue
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4 bg-white">
                    <div class="alert alert-light border mb-3 py-2 px-3 rounded-3 d-flex align-items-center justify-content-between">
                        <div>
                            <div class="text-muted" style="font-size:0.75rem;">Selected Asset:</div>
                            <strong id="rm_asset_name_display" class="text-dark">Fixed Asset</strong>
                        </div>
                        <span id="rm_asset_code_display" class="badge bg-dark font-monospace px-2 py-1"></span>
                    </div>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">Issue Type <span class="text-danger">*</span></label>
                            <select name="issue_type" class="form-select form-select-sm" required>
                                <option value="breakdown">⚡ Breakdown</option>
                                <option value="damage">💥 Physical Damage</option>
                                <option value="service_due">🔧 Service Due</option>
                                <option value="malfunction">⚠️ Malfunction</option>
                                <option value="needs_repair">🛠️ Needs Repair</option>
                                <option value="other">📋 Other</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">Urgency <span class="text-danger">*</span></label>
                            <select name="urgency" class="form-select form-select-sm" required>
                                <option value="low">🟢 Low — Not urgent</option>
                                <option value="normal" selected>🔵 Normal</option>
                                <option value="urgent">🟠 Urgent</option>
                                <option value="critical">🔴 Critical — Blocking work</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold small">Description <span class="text-danger">*</span></label>
                            <textarea name="description" class="form-control form-control-sm" rows="4" required
                                placeholder="Describe the issue clearly: what happened, when it started, what you observed..."></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light border-0 py-3 px-4 d-flex justify-content-end gap-2">
                    <button type="button" class="btn btn-sm btn-secondary px-3" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-sm btn-warning fw-bold px-4 text-dark shadow-sm">
                        <i class="fa-solid fa-paper-plane me-1"></i>Submit Report
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Interactive password visibility toggler
    document.querySelectorAll('.toggle-password').forEach(function(button) {
        button.addEventListener('click', function() {
            const targetId = this.getAttribute('data-target');
            const input = document.getElementById(targetId);
            const icon = this.querySelector('i');
            
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        });
    });

    // Report Maintenance Issue modal opener
    document.addEventListener('click', function(e) {
        const btn = e.target.closest('.btn-report-issue');
        if (!btn) return;
        
        const d = btn.dataset;
        document.getElementById('rm_unit_id').value = d.unitId || '';
        document.getElementById('rm_legacy_id').value = d.legacyId || '';
        document.getElementById('rm_asset_name_input').value = d.assetName || 'Fixed Asset';
        document.getElementById('rm_asset_code_input').value = d.assetCode || '';
        document.getElementById('rm_asset_name_display').textContent = d.assetName || 'Fixed Asset';
        document.getElementById('rm_asset_code_display').textContent = d.assetCode || '';
        
        const modalEl = document.getElementById('reportMaintenanceModal');
        const modalInstance = bootstrap.Modal.getOrCreateInstance(modalEl);
        modalInstance.show();
    });
});
</script>
@endsection
