@extends('layouts.app')

@section('title', 'Employee Profile - ' . $employee->full_name)

@section('content')

{{-- GM Rejection Alert for HR Officer --}}
@if($employee->gm_approval_status === 'rejected')
<div class="alert alert-danger border-start border-4 border-danger shadow mb-4 fade show" role="alert">
    <div class="d-flex align-items-start gap-3">
        <div class="rounded-circle bg-danger bg-opacity-15 p-3 flex-shrink-0">
            <i class="fa-solid fa-triangle-exclamation fa-xl text-danger"></i>
        </div>
        <div class="flex-grow-1">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <strong class="fs-6 text-danger d-block mb-1">
                        ⚠️ Returned by General Manager — Correction Required
                    </strong>
                    <p class="mb-2 text-dark">
                        <i class="fa-solid fa-comment-dots me-1 text-danger"></i>
                        <strong>GM's Instructions:</strong> {{ $employee->gm_rejection_reason }}
                    </p>
                    <small class="text-muted">
                        Rejected by
                        <strong>{{ $employee->gmRejectedBy->name ?? 'GM' }}</strong>
                        on {{ optional($employee->gm_rejected_at)->format('d M Y \a\t h:i A') }}
                    </small>
                </div>
                @can('update', $employee)
                <a href="{{ route('employees.edit', $employee) }}" class="btn btn-danger fw-bold ms-3 flex-shrink-0">
                    <i class="fa-solid fa-wrench me-1"></i> Fix & Resubmit to GM
                </a>
                @endcan
            </div>
        </div>
    </div>
</div>
@endif

<div class="d-flex align-items-center justify-content-between mb-4">
    <div class="d-flex align-items-center">
        <a href="{{ route('employees.index') }}" class="btn btn-sm btn-outline-secondary me-3">
            <i class="fa-solid fa-arrow-left"></i>
        </a>
        <div class="me-3">
            <img src="{{ $employee->profile_picture_url }}" alt="{{ $employee->full_name }}"
                 style="width:58px;height:58px;border-radius:50%;object-fit:cover;border:2px solid #0d6efd;"
                 class="shadow-sm">
        </div>
        <div>
            <h1 class="h3 mb-0">{{ $employee->full_name }}</h1>
            <div class="d-flex align-items-center gap-2 mt-1">
                <small class="text-muted">{{ $employee->employee_code }} • {{ $employee->role_title ?? 'Employee' }}</small>
                @if($employee->is_approved_by_gm)
                    <span class="badge bg-success"><i class="fa-solid fa-circle-check me-1"></i>GM Approved</span>
                @elseif($employee->gm_approval_status === 'rejected')
                    <span class="badge bg-danger"><i class="fa-solid fa-triangle-exclamation me-1"></i>Returned by GM</span>
                @else
                    <span class="badge bg-warning text-dark"><i class="fa-solid fa-clock me-1"></i>Awaiting GM Approval</span>
                @endif
            </div>
        </div>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        @role('gm')
            @if(!$employee->is_approved_by_gm && $employee->gm_approval_status !== 'rejected')
                <form action="{{ route('employees.approve', $employee) }}" method="POST" class="d-inline">
                    @csrf
                    @method('PUT')
                    <button type="submit" class="btn btn-sm btn-success fw-bold shadow-sm" onclick="return confirm('Approve {{ addslashes($employee->full_name) }}?')">
                        <i class="fa-solid fa-check me-1"></i>Approve Employee
                    </button>
                </form>
                <button type="button" class="btn btn-sm btn-outline-danger fw-bold" onclick="openRejectModal()">
                    <i class="fa-solid fa-rotate-left me-1"></i>Reject & Return to HR
                </button>
            @elseif($employee->is_approved_by_gm)
                <span class="btn btn-sm btn-success disabled">
                    <i class="fa-solid fa-circle-check me-1"></i>Approved by GM
                </span>
            @elseif($employee->gm_approval_status === 'rejected')
                <span class="btn btn-sm btn-outline-danger disabled">
                    <i class="fa-solid fa-triangle-exclamation me-1"></i>Returned to HR
                </span>
            @endif
        @endrole
        <a href="{{ \Illuminate\Support\Facades\Route::has('employee-letters.create') ? route('employee-letters.create', ['employee_id' => $employee->id]) : url('/employee-letters/create?employee_id='.$employee->id) }}" class="btn btn-sm btn-outline-warning text-dark fw-bold">
            <i class="fa-solid fa-envelope-open-text me-1"></i>Issue Letter
        </a>
        <a href="{{ route('employees.edit', $employee) }}" class="btn btn-sm btn-primary">
            <i class="fa-solid fa-edit me-2"></i>Edit
        </a>
    </div>
</div>

<div class="row g-3">
    {{-- Left Column: Employee Info --}}
    <div class="col-lg-8">
        {{-- Employment Info Card --}}
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-light">
                <h5 class="mb-0"><i class="fa-solid fa-briefcase text-primary me-2"></i>Employment Information</h5>
            </div>
            <div class="card-body">
                <div class="row g-4">
                    <div class="col-md-6">
                        <small class="text-muted d-block mb-1">Department</small>
                        <h6 class="mb-0">{{ $employee->department ?? 'N/A' }}</h6>
                    </div>
                    <div class="col-md-6">
                        <small class="text-muted d-block mb-1">Employment Type</small>
                        <h6 class="mb-0">
                            @php
                                $types = ['permanent' => 'Permanent', 'contract' => 'Contract', 'daily' => 'Daily Worker'];
                            @endphp
                            {{ $types[$employee->employment_type] ?? $employee->employment_type }}
                        </h6>
                    </div>
                    <div class="col-md-6">
                        <small class="text-muted d-block mb-1">Date of Joining</small>
                        <h6 class="mb-0">{{ optional($employee->date_of_joining)->format('d M Y') ?? 'N/A' }}</h6>
                    </div>
                    <div class="col-md-6">
                        <small class="text-muted d-block mb-1">Status</small>
                        <h6 class="mb-0">
                            @if($employee->status === 'active')
                                <span class="badge bg-success">Active</span>
                            @elseif($employee->status === 'suspended')
                                <span class="badge bg-warning">Suspended</span>
                            @else
                                <span class="badge bg-danger">Terminated</span>
                            @endif

                            @if($employee->is_approved_by_gm)
                                <span class="badge bg-success ms-1"><i class="fa-solid fa-check-circle me-1"></i>Approved by GM</span>
                            @else
                                <span class="badge bg-warning ms-1 text-dark"><i class="fa-solid fa-clock me-1"></i>Pending GM Approval</span>
                            @endif
                        </h6>
                    </div>
                    @if($employee->project)
                    <div class="col-md-6">
                        <small class="text-muted d-block mb-1">Assigned Project</small>
                        <h6 class="mb-0">{{ $employee->project->name }}</h6>
                    </div>
                    @endif
                    @if($employee->site_assignment)
                    <div class="col-md-6">
                        <small class="text-muted d-block mb-1">Site Assignment</small>
                        <h6 class="mb-0">{{ $employee->site_assignment }}</h6>
                    </div>
                    @endif
                </div>
            </div>
        </div>

        {{-- Contact & Identity Information Card --}}
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-light d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fa-solid fa-id-card text-primary me-2"></i>Contact & Identity Information</h5>
                @if($employee->national_id_card)
                    <a href="{{ $employee->national_id_card_url }}" target="_blank" class="btn btn-sm btn-outline-primary">
                        <i class="fa-solid fa-eye me-1"></i>View National ID Card
                    </a>
                @endif
            </div>
            <div class="card-body">
                <div class="row g-4">
                    <div class="col-md-4">
                        <small class="text-muted d-block mb-1">Phone</small>
                        <h6 class="mb-0">{{ $employee->phone ?? 'N/A' }}</h6>
                    </div>
                    <div class="col-md-4">
                        <small class="text-muted d-block mb-1">Email</small>
                        <h6 class="mb-0">{{ $employee->email ?? 'N/A' }}</h6>
                    </div>
                    <div class="col-md-4">
                        <small class="text-muted d-block mb-1">National ID / Fayda No.</small>
                        <h6 class="mb-0 font-monospace">
                            {{ $employee->national_id_number ?? 'Not Provided' }}
                            @if($employee->national_id_card)
                                <span class="badge bg-success ms-1"><i class="fa-solid fa-check me-1"></i>Card on File</span>
                            @endif
                        </h6>
                    </div>
                </div>
            </div>
        </div>

        {{-- Official Documents & Contracts Card --}}
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-light">
                <h5 class="mb-0"><i class="fa-solid fa-folder-open text-info me-2"></i>Official Registration & Documents</h5>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-4">
                        <div class="border rounded p-3 text-center bg-light h-100">
                            <i class="fa-solid fa-file-contract fa-2x text-primary mb-2"></i>
                            <h6 class="fw-bold mb-1 small">Registration / Contract</h6>
                            @if($employee->registration_letter)
                                <a href="{{ $employee->registration_letter_url }}" target="_blank" class="btn btn-sm btn-primary mt-2">
                                    <i class="fa-solid fa-eye me-1"></i>View Contract
                                </a>
                            @else
                                <span class="badge bg-secondary mt-2">Not Uploaded</span>
                            @endif
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="border rounded p-3 text-center bg-light h-100">
                            <i class="fa-solid fa-id-card fa-2x text-success mb-2"></i>
                            <h6 class="fw-bold mb-1 small">National ID Card</h6>
                            @if($employee->national_id_card)
                                <a href="{{ $employee->national_id_card_url }}" target="_blank" class="btn btn-sm btn-success mt-2">
                                    <i class="fa-solid fa-eye me-1"></i>View ID Card
                                </a>
                            @else
                                <span class="badge bg-secondary mt-2">Not Uploaded</span>
                            @endif
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="border rounded p-3 text-center bg-light h-100">
                            <i class="fa-solid fa-file-signature fa-2x text-warning mb-2"></i>
                            <h6 class="fw-bold mb-1 small">Asset Handover Receipt</h6>
                            @if($employee->asset_handover_document)
                                <a href="{{ $employee->asset_handover_document_url }}" target="_blank" class="btn btn-sm btn-warning text-dark mt-2">
                                    <i class="fa-solid fa-eye me-1"></i>View Receipt
                                </a>
                            @else
                                <span class="badge bg-secondary mt-2">Not Uploaded</span>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Salary Information Card --}}
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-light">
                <h5 class="mb-0"><i class="fa-solid fa-money-bill text-success me-2"></i>Salary Information</h5>
            </div>
            <div class="card-body">
                <div class="row g-4">
                    <div class="col-md-6">
                        <small class="text-muted d-block mb-1">Monthly Base Salary</small>
                        <h6 class="mb-0">Br {{ number_format($employee->basic_salary, 2) }}</h6>
                    </div>
                    @if($employee->bank_name)
                    <div class="col-md-6">
                        <small class="text-muted d-block mb-1">Bank Account</small>
                        <h6 class="mb-0">{{ $employee->bank_name }} - {{ $employee->account_number }}</h6>
                    </div>
                    @endif
                </div>
            </div>
        </div>

        {{-- ── Card 1: Guarantee Letter ──────────────────────────────────────── --}}
        <div class="card border-0 shadow-sm mb-3
            @if($employee->is_guarantee_overdue) border-danger @elseif($employee->show_guarantee_warning) border-warning @endif">
            <div class="card-header @if($employee->is_guarantee_overdue) bg-danger text-white @elseif($employee->show_guarantee_warning) bg-warning text-dark @else bg-light @endif">
                <h5 class="mb-0">
                    <i class="fa-solid fa-file-shield me-2"></i>Guarantee Letter
                </h5>
            </div>
            <div class="card-body">
                @if($employee->guarantee_letter)
                    {{-- Guarantee letter is on file --}}
                    <div class="d-flex align-items-center gap-4 p-3 border rounded bg-white shadow-xs">
                        <div class="text-center" style="min-width:80px;">
                            <i class="fa-solid fa-file-pdf fa-3x text-danger"></i>
                        </div>
                        <div class="flex-grow-1">
                            <h6 class="fw-bold mb-1 text-dark">Guarantee Letter Document</h6>
                            <small class="text-muted d-block" style="font-size:0.78rem;">
                                <i class="fa-regular fa-calendar-check me-1 text-success"></i>
                                Submitted: {{ $employee->guarantee_letter_submitted_at ? $employee->guarantee_letter_submitted_at->format('d M Y') : 'On File' }}
                            </small>
                            <span class="badge bg-success mt-1"><i class="fa-solid fa-check me-1"></i>Submitted</span>
                        </div>
                        <div>
                            <a href="{{ $employee->guarantee_letter_url }}" target="_blank"
                               class="btn btn-outline-danger btn-sm px-3">
                                <i class="fa-solid fa-file-pdf me-1"></i>View Guarantee Letter
                            </a>
                        </div>
                    </div>
                @else
                    {{-- No guarantee letter yet --}}
                    @if($employee->is_guarantee_overdue)
                        <div class="alert alert-danger mb-3">
                            <i class="fa-solid fa-exclamation-circle me-2"></i>
                            <strong>OVERDUE!</strong> Guarantee letter was due {{ abs($employee->days_until_guarantee_deadline) }} days ago.
                            <br><small>Login access has been blocked until submission.</small>
                        </div>
                        <p class="text-muted mb-3">
                            <i class="fa-solid fa-calendar me-2"></i>
                            Joined: {{ optional($employee->date_of_joining)->format('d M Y') ?? 'N/A' }}
                            <br>
                            <i class="fa-solid fa-clock me-2"></i>
                            Deadline was: {{ $employee->date_of_joining ? $employee->date_of_joining->addDays(30)->format('d M Y') : 'N/A' }}
                        </p>
                        <form action="{{ route('employees.upload-guarantee', $employee) }}" method="POST" enctype="multipart/form-data">
                            @csrf
                            <div class="mb-3">
                                <label class="form-label">Upload Guarantee Letter <span class="text-danger">*</span></label>
                                <input type="file" name="guarantee_letter" class="form-control" required accept="application/pdf,image/jpeg,image/png,image/jpg">
                                <small class="text-muted">PDF or Image (Max 10MB)</small>
                            </div>
                            <button type="submit" class="btn btn-danger">
                                <i class="fa-solid fa-upload me-2"></i>Submit Now to Restore Access
                            </button>
                        </form>
                    @elseif($employee->show_guarantee_warning)
                        <div class="alert alert-warning mb-3">
                            <i class="fa-solid fa-exclamation-triangle me-2"></i>
                            <strong>Warning!</strong> Guarantee letter must be submitted within {{ $employee->days_until_guarantee_deadline }} days.
                            <br><small>Login will be blocked after {{ $employee->date_of_joining ? $employee->date_of_joining->addDays(30)->format('d M Y') : 'N/A' }}</small>
                        </div>
                        <p class="text-muted mb-3">
                            <i class="fa-solid fa-calendar me-2"></i>
                            Joined: {{ optional($employee->date_of_joining)->format('d M Y') ?? 'N/A' }}
                            <br>
                            <i class="fa-solid fa-clock me-2"></i>
                            Deadline: {{ $employee->date_of_joining ? $employee->date_of_joining->addDays(30)->format('d M Y') : 'N/A' }}
                        </p>
                        <form action="{{ route('employees.upload-guarantee', $employee) }}" method="POST" enctype="multipart/form-data">
                            @csrf
                            <div class="mb-3">
                                <label class="form-label">Upload Guarantee Letter</label>
                                <input type="file" name="guarantee_letter" class="form-control" accept="application/pdf,image/jpeg,image/png,image/jpg">
                                <small class="text-muted">PDF or Image (Max 10MB)</small>
                            </div>
                            <button type="submit" class="btn btn-warning">
                                <i class="fa-solid fa-upload me-2"></i>Submit Now
                            </button>
                        </form>
                    @else
                        <div class="alert alert-info mb-3">
                            <i class="fa-solid fa-info-circle me-2"></i>
                            Guarantee letter due in {{ $employee->days_until_guarantee_deadline }} days.
                        </div>
                        <p class="text-muted mb-3">
                            <i class="fa-solid fa-calendar me-2"></i>
                            Joined: {{ optional($employee->date_of_joining)->format('d M Y') ?? 'N/A' }}
                            <br>
                            <i class="fa-solid fa-clock me-2"></i>
                            Deadline: {{ $employee->date_of_joining ? $employee->date_of_joining->addDays(30)->format('d M Y') : 'N/A' }}
                        </p>
                        <form action="{{ route('employees.upload-guarantee', $employee) }}" method="POST" enctype="multipart/form-data">
                            @csrf
                            <div class="mb-3">
                                <label class="form-label">Upload Guarantee Letter (Optional - can submit anytime)</label>
                                <input type="file" name="guarantee_letter" class="form-control" accept="application/pdf,image/jpeg,image/png,image/jpg">
                                <small class="text-muted">PDF or Image (Max 10MB)</small>
                            </div>
                            <button type="submit" class="btn btn-primary">
                                <i class="fa-solid fa-upload me-2"></i>Submit Early
                            </button>
                        </form>
                    @endif
                @endif
            </div>
        </div>

        {{-- ── Card 2: Guarantor Person Information ─────────────────────────── --}}
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-light">
                <h5 class="mb-0">
                    <i class="fa-solid fa-user-shield me-2 text-primary"></i>Guarantor Person Information
                </h5>
            </div>
            <div class="card-body">
                {{-- Person Details Row --}}
                <div class="row g-3 mb-4">
                    <div class="col-md-4">
                        <div class="p-3 bg-light rounded border h-100">
                            <small class="text-muted d-block mb-1 fw-semibold" style="font-size:0.75rem; text-transform:uppercase; letter-spacing:.04em;">
                                <i class="fa-solid fa-user-tie text-primary me-1"></i>Guarantor Full Name
                            </small>
                            <h6 class="mb-0 fw-bold text-dark">{{ $employee->guarantor_name ?: '—' }}</h6>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="p-3 bg-light rounded border h-100">
                            <small class="text-muted d-block mb-1 fw-semibold" style="font-size:0.75rem; text-transform:uppercase; letter-spacing:.04em;">
                                <i class="fa-solid fa-id-card text-success me-1"></i>National ID Number
                            </small>
                            <h6 class="mb-0 fw-bold text-dark font-monospace">{{ $employee->guarantor_id_number ?: '—' }}</h6>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="p-3 bg-light rounded border h-100">
                            <small class="text-muted d-block mb-1 fw-semibold" style="font-size:0.75rem; text-transform:uppercase; letter-spacing:.04em;">
                                <i class="fa-solid fa-phone text-info me-1"></i>Phone Number
                            </small>
                            <h6 class="mb-0 fw-bold text-dark">{{ $employee->guarantor_phone ?: '—' }}</h6>
                        </div>
                    </div>
                </div>

                {{-- Guarantor ID Card Document --}}
                @if($employee->guarantor_id_card)
                    <div class="d-flex align-items-center gap-4 p-3 border rounded bg-white shadow-xs">
                        <div class="text-center" style="min-width:80px;">
                            <i class="fa-solid fa-id-card fa-3x text-primary"></i>
                        </div>
                        <div class="flex-grow-1">
                            <h6 class="fw-bold mb-1 text-dark">Guarantor National ID Document</h6>
                            <small class="text-muted d-block" style="font-size:0.78rem;">
                                ID card / photo uploaded for verification
                            </small>
                            <span class="badge bg-success mt-1"><i class="fa-solid fa-check me-1"></i>On File</span>
                        </div>
                        <div>
                            <a href="{{ $employee->guarantor_id_card_url }}" target="_blank"
                               class="btn btn-outline-primary btn-sm px-3">
                                <i class="fa-solid fa-eye me-1"></i>View Guarantor ID
                            </a>
                        </div>
                    </div>
                @else
                    <div class="text-center py-3 text-muted border rounded bg-light">
                        <i class="fa-solid fa-id-card fa-2x mb-2 opacity-25 d-block"></i>
                        <small>No Guarantor ID document uploaded yet.</small>
                    </div>
                @endif

                @if(!$employee->guarantor_name && !$employee->guarantor_id_number && !$employee->guarantor_phone && !$employee->guarantor_id_card)
                    <div class="alert alert-secondary mt-3 mb-0">
                        <i class="fa-solid fa-circle-info me-2"></i>
                        No guarantor person information has been recorded for this employee.
                        <a href="{{ route('employees.edit', $employee) }}" class="alert-link ms-1">Add Guarantor →</a>
                    </div>
                @endif
            </div>
        </div>

        {{-- Educational Background Card --}}
        @if($employee->education()->count() > 0)
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-light d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fa-solid fa-graduation-cap text-primary me-2"></i>Educational Background</h5>
                <span class="badge bg-primary">{{ $employee->education()->count() }} Record(s)</span>
            </div>
            <div class="card-body">
                @foreach($employee->education as $edu)
                <div class="border-start border-4 border-primary ps-3 mb-4 pb-3 {{ !$loop->last ? 'border-bottom' : '' }}">
                    <div class="row">
                        <div class="col-md-8">
                            <h6 class="mb-1">
                                <i class="fa-solid fa-award text-warning me-2"></i>
                                <strong>{{ $edu->degree_level }}</strong> in {{ $edu->field_of_study }}
                                @if($edu->is_verified)
                                    <span class="badge bg-success ms-2"><i class="fa-solid fa-check"></i> Verified</span>
                                @endif
                            </h6>
                            <p class="text-muted mb-2">
                                <i class="fa-solid fa-building me-2"></i>{{ $edu->institution_name }}
                                @if($edu->location)
                                    <br><i class="fa-solid fa-map-marker-alt me-2"></i>{{ $edu->location }}
                                @endif
                            </p>
                            <small class="text-muted">
                                <i class="fa-solid fa-calendar me-2"></i>{{ $edu->duration }}
                            </small>
                            @if($edu->grade_gpa)
                                <br><small class="text-muted">
                                    <i class="fa-solid fa-star me-2"></i>Grade: <strong>{{ $edu->grade_gpa }}</strong>
                                </small>
                            @endif
                            @if($edu->description)
                                <p class="mt-2 mb-0 small text-secondary">{{ $edu->description }}</p>
                            @endif
                        </div>
                        <div class="col-md-4 text-end">
                            @if($edu->certificate_photo)
                                <a href="{{ $edu->certificate_url }}" target="_blank" class="btn btn-sm btn-outline-primary">
                                    <i class="fa-solid fa-image me-1"></i>View Certificate
                                </a>
                            @else
                                <small class="text-muted">No certificate uploaded</small>
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
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-light d-flex justify-content-between align-items-center">
                <h5 class="mb-0">
                    <i class="fa-solid fa-id-card-clip text-warning me-2"></i>Professional Licenses
                </h5>
                <span class="badge bg-warning text-dark">{{ $allLicensesList->count() }} License(s)</span>
            </div>
            <div class="card-body">
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
                                        <span class="badge bg-danger">Expired</span>
                                    @elseif($daysLeft <= 90)
                                        <span class="badge bg-warning text-dark">Expiring Soon</span>
                                    @else
                                        <span class="badge bg-success">Valid</span>
                                    @endif
                                </div>
                                @endif

                                @if(!empty($lic->notes))
                                <div class="mt-1 text-secondary" style="font-size:0.8rem;">
                                    <i class="fa-solid fa-circle-info me-1 text-warning"></i>{{ $lic->notes }}
                                </div>
                                @endif
                            </div>

                            {{-- View License Button --}}
                            @if($lic->license_document)
                            <div class="mt-3">
                                <a href="{{ $lic->license_url }}" target="_blank" class="btn btn-sm btn-outline-warning text-dark fw-semibold">
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
        @if($employee->experience()->count() > 0)
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-light d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fa-solid fa-briefcase text-success me-2"></i>Work Experience</h5>
                <span class="badge bg-success">{{ $employee->experience()->count() }} Position(s)</span>
            </div>
            <div class="card-body">
                @foreach($employee->experience as $exp)
                <div class="border-start border-4 border-success ps-3 mb-4 pb-3 {{ !$loop->last ? 'border-bottom' : '' }}">
                    <div class="row">
                        <div class="col-md-8">
                            <h6 class="mb-1">
                                <i class="fa-solid fa-user-tie text-info me-2"></i>
                                <strong>{{ $exp->job_title }}</strong>
                                @if($exp->is_current)
                                    <span class="badge bg-info ms-2">Current</span>
                                @endif
                            </h6>
                            <p class="text-muted mb-2">
                                <i class="fa-solid fa-building me-2"></i>{{ $exp->company_name }}
                                @if($exp->location)
                                    <br><i class="fa-solid fa-map-marker-alt me-2"></i>{{ $exp->location }}
                                @endif
                            </p>
                            <small class="text-muted">
                                <i class="fa-solid fa-calendar me-2"></i>{{ $exp->period }}
                                <span class="badge bg-secondary ms-2">{{ $exp->duration }}</span>
                            </small>
                            
                            @if($exp->responsibilities)
                                <p class="mt-2 mb-2 small text-secondary">{{ Str::limit($exp->responsibilities, 200) }}</p>
                            @endif

                            {{-- Reference Info --}}
                            @if($exp->reference_name)
                                <div class="mt-2">
                                    <small class="text-muted">
                                        <i class="fa-solid fa-user-check me-2"></i><strong>Reference:</strong> 
                                        {{ $exp->reference_name }}
                                        @if($exp->reference_phone)
                                            ({{ $exp->reference_phone }})
                                        @endif
                                    </small>
                                </div>
                            @endif
                        </div>
                        <div class="col-md-4 text-end d-flex flex-column align-items-end gap-2">
                            @if($exp->experience_letter)
                                <a href="{{ $exp->experience_letter_url }}" target="_blank" class="btn btn-sm btn-outline-primary">
                                    <i class="fa-solid fa-file-lines me-1"></i>View Experience Letter
                                </a>
                            @else
                                <small class="text-muted">No experience letter uploaded</small>
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
            $totalActiveCount = $activeFixedUnits->count() + $legacyActiveAssets->count();
        @endphp
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white py-3 d-flex flex-wrap justify-content-between align-items-center gap-2">
                <div class="d-flex align-items-center gap-2">
                    <h5 class="mb-0 fw-bold text-dark">
                        <i class="fa-solid fa-truck-monster text-warning me-2"></i>Assigned Fixed Assets & Equipment
                    </h5>
                    <span class="badge bg-primary fs-6">{{ $totalActiveCount }} Active Assigned</span>
                </div>
                @if($totalActiveCount > 5)
                <div class="d-flex align-items-center">
                    <div class="input-group input-group-sm" style="max-width: 220px;">
                        <span class="input-group-text bg-light border-end-0"><i class="fa-solid fa-magnifying-glass text-muted"></i></span>
                        <input type="text" id="assetSearchInput" class="form-control border-start-0 ps-0" placeholder="Search assets..." onkeyup="filterEmployeeAssets()">
                    </div>
                </div>
                @endif
            </div>
            <div class="card-body p-0">
                @if($totalActiveCount > 0)
                <div style="max-height: 480px; overflow-y: auto; overflow-x: hidden;">
                    <table class="table table-hover align-middle mb-0" id="employeeAssetsTable" style="width: 100%; font-size: 0.88rem;">
                        <thead class="table-light text-muted small text-uppercase" style="position: sticky; top: 0; z-index: 2; box-shadow: 0 1px 2px rgba(0,0,0,0.05);">
                            <tr>
                                <th class="ps-3 py-2 bg-light">Asset / Code</th>
                                <th class="py-2 bg-light">Price / Value</th>
                                <th class="py-2 bg-light">Condition</th>
                                <th class="py-2 bg-light">Assigned Date</th>
                                <th class="text-end pe-3 py-2 bg-light" style="width: 85px;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            {{-- Centralized Fixed Asset Units --}}
                            @foreach($activeFixedUnits as $fUnit)
                            @php
                                $condBadge = $fUnit->condition_badge;
                                $unitPrice = $fUnit->purchase_price ?? $fUnit->parentAsset->unit_cost ?? 0;
                            @endphp
                            <tr class="asset-row" data-search="{{ strtolower(($fUnit->unit_code ?? '') . ' ' . ($fUnit->parentAsset->name ?? '') . ' ' . ($fUnit->parentAsset->category ?? '') . ' ' . ($fUnit->plate_number ?? '') . ' ' . ($fUnit->serial_number ?? '')) }}">
                                <td class="ps-3 py-2">
                                    <div class="d-flex align-items-start gap-2">
                                        <span class="badge bg-dark font-monospace px-2 py-1 flex-shrink-0" style="margin-top: 2px;">{{ $fUnit->unit_code }}</span>
                                        <div class="min-w-0">
                                            <strong class="text-dark d-block text-truncate" style="max-width: 260px;" title="{{ $fUnit->parentAsset->name ?? 'Fixed Asset' }}">
                                                {{ $fUnit->parentAsset->name ?? 'Fixed Asset' }}
                                            </strong>
                                            <div class="d-flex flex-wrap align-items-center gap-1 mt-1">
                                                <span class="badge bg-light text-secondary border px-1 py-0" style="font-size: 0.72rem;">{{ $fUnit->parentAsset->category ?? 'General' }}</span>
                                                @if($fUnit->plate_number)
                                                    <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 px-1 py-0" style="font-size: 0.72rem;">
                                                        <i class="fa-solid fa-car-side me-1"></i>{{ $fUnit->plate_number }}
                                                    </span>
                                                @endif
                                                @if($fUnit->serial_number)
                                                    <span class="badge bg-secondary bg-opacity-10 text-secondary border px-1 py-0 font-monospace" style="font-size: 0.72rem;">
                                                        S/N: {{ $fUnit->serial_number }}
                                                    </span>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td class="py-2 text-nowrap">
                                    <strong class="text-success small">Br {{ number_format($unitPrice, 2) }}</strong>
                                </td>
                                <td class="py-2 text-nowrap">
                                    <span class="badge {{ $condBadge['class'] }}">{{ $condBadge['label'] }}</span>
                                </td>
                                <td class="py-2 text-nowrap">
                                    <div class="small fw-semibold">{{ $fUnit->assigned_date ? $fUnit->assigned_date->format('d M Y') : 'N/A' }}</div>
                                    <div class="text-muted" style="font-size:0.72rem;">{{ $fUnit->assigned_date ? $fUnit->assigned_date->diffForHumans() : '' }}</div>
                                </td>
                                <td class="text-end pe-3 py-2 text-nowrap">
                                    <div class="btn-group btn-group-sm">
                                        <button type="button" class="btn btn-outline-info px-2" data-bs-toggle="modal" data-bs-target="#viewAssetUnitModal_{{ $fUnit->id }}" title="View Full Specifications">
                                            <i class="fa-solid fa-eye"></i>
                                        </button>
                                        <button type="button" class="btn btn-outline-warning px-2" data-bs-toggle="modal" data-bs-target="#returnFixedUnitModal_{{ $fUnit->id }}" title="Return Asset">
                                            <i class="fa-solid fa-arrow-rotate-left"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            @endforeach

                            {{-- Legacy Assigned Assets (if any exist) --}}
                            @foreach($legacyActiveAssets as $asset)
                            @php
                                $legacyPrice = $asset->product->unit_price ?? $asset->product->purchase_price ?? 0;
                            @endphp
                            <tr class="asset-row" data-search="{{ strtolower(($asset->product->name ?? '') . ' ' . ($asset->product->type ?? '') . ' ' . ($asset->notes ?? '')) }}">
                                <td class="ps-3 py-2">
                                    <strong class="text-dark d-block">{{ $asset->product->name ?? 'Unknown Asset' }}</strong>
                                    <div class="d-flex align-items-center gap-1 mt-1">
                                        <span class="badge bg-light text-secondary border px-1 py-0" style="font-size: 0.72rem;">{{ $asset->product->type ?? 'General' }}</span>
                                        @if($asset->notes)
                                            <small class="text-muted text-truncate" style="max-width: 200px;">{{ $asset->notes }}</small>
                                        @endif
                                    </div>
                                </td>
                                <td class="py-2 text-nowrap">
                                    <strong class="text-success small">Br {{ number_format($legacyPrice, 2) }}</strong>
                                </td>
                                <td class="py-2 text-nowrap">
                                    <span class="badge bg-success">In Use</span>
                                </td>
                                <td class="py-2 text-nowrap">
                                    <div class="small fw-semibold">{{ $asset->assigned_date ? $asset->assigned_date->format('d M Y') : 'N/A' }}</div>
                                </td>
                                <td class="text-end pe-3 py-2 text-nowrap">
                                    <div class="btn-group btn-group-sm">
                                        <a href="{{ route('employee-assets.return', $asset) }}" class="btn btn-outline-warning px-2" title="Return Asset">
                                            <i class="fa-solid fa-arrow-rotate-left"></i>
                                        </a>
                                        <a href="{{ route('employee-assets.damage', $asset) }}" class="btn btn-outline-danger px-2" title="Report Damage">
                                            <i class="fa-solid fa-exclamation-triangle"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @else
                <div class="text-center py-5 text-muted">
                    <i class="fa-solid fa-box-open fa-3x mb-3 opacity-25 d-block"></i>
                    <p class="mb-0 fw-semibold">No assets currently assigned</p>
                    <small>Assets will appear here once assigned from the inventory.</small>
                </div>
                @endif
            </div>
        </div>



        {{-- Recently Returned Assets --}}
        @php
            $returnedAssets = $employee->assets()->where('status', 'returned')->latest('returned_date')->limit(5)->get();
            $damagedAssets = $employee->assets()->where('status', 'damaged')->latest('updated_at')->limit(5)->get();
        @endphp
        
        @if($returnedAssets->count() > 0 || $damagedAssets->count() > 0)
        <div class="card border-0 shadow-sm mt-3">
            <div class="card-header bg-light">
                <h5 class="mb-0"><i class="fa-solid fa-history text-secondary me-2"></i>Asset History</h5>
            </div>
            <div class="card-body">
                <ul class="nav nav-tabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="returned-tab" data-bs-toggle="tab" data-bs-target="#returned" type="button" role="tab">
                            Returned <span class="badge bg-secondary ms-2">{{ $returnedAssets->count() }}</span>
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="damaged-tab" data-bs-toggle="tab" data-bs-target="#damaged" type="button" role="tab">
                            Damaged <span class="badge bg-danger ms-2">{{ $damagedAssets->count() }}</span>
                        </button>
                    </li>
                </ul>
                <div class="tab-content mt-3">
                    <div class="tab-pane fade show active" id="returned" role="tabpanel">
                        @if($returnedAssets->count() > 0)
                        <div class="table-responsive">
                            <table class="table table-sm mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Asset</th>
                                        <th>Assigned</th>
                                        <th>Returned</th>
                                        <th>Notes</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($returnedAssets as $asset)
                                    <tr>
                                        <td>{{ $asset->product->name ?? 'Unknown' }}</td>
                                        <td>{{ optional($asset->assigned_date)->format('d M Y') ?? 'N/A' }}</td>
                                        <td>{{ optional($asset->returned_date)->format('d M Y') ?? 'N/A' }}</td>
                                        <td>{{ $asset->notes ?? '-' }}</td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        @endif
                    </div>
                    <div class="tab-pane fade" id="damaged" role="tabpanel">
                        @if($damagedAssets->count() > 0)
                        <div class="table-responsive">
                            <table class="table table-sm mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Asset</th>
                                        <th>Assigned Date</th>
                                        <th>Damage Reported</th>
                                        <th>Notes</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($damagedAssets as $asset)
                                    <tr>
                                        <td>{{ $asset->product->name ?? 'Unknown' }}</td>
                                        <td>{{ optional($asset->assigned_date)->format('d M Y') ?? 'N/A' }}</td>
                                        <td>{{ optional($asset->updated_at)->format('d M Y') ?? 'N/A' }}</td>
                                        <td>{{ $asset->notes ?? '-' }}</td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
        @endif
        {{-- ============================
             Official Letters & Disciplinary / Recognition History
        ============================= --}}
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center">
                <div>
                    <h5 class="mb-0 fw-bold text-dark"><i class="fa-solid fa-envelope-open-text text-primary me-2"></i>Official Letters &amp; Recognition / Warning History</h5>
                    <small class="text-muted">Archived appreciation letters, written warnings, and disciplinary notices for this employee</small>
                </div>
                <a href="{{ \Illuminate\Support\Facades\Route::has('employee-letters.create') ? route('employee-letters.create', ['employee_id' => $employee->id]) : url('/employee-letters/create?employee_id='.$employee->id) }}" class="btn btn-sm btn-primary shadow-sm">
                    <i class="fa-solid fa-plus me-1"></i> Issue Official Letter
                </a>
            </div>
            <div class="card-body p-0">
                @php
                    $empLetters = $employee->letters ?? collect();
                @endphp
                @if($empLetters->count() > 0)
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-3">Ref #</th>
                                <th>Letter Type</th>
                                <th>Subject / Title</th>
                                <th>Issued Date</th>
                                <th>Status</th>
                                <th class="text-end pe-3">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($empLetters as $ltr)
                            <tr>
                                <td class="ps-3">
                                    <a href="{{ \Illuminate\Support\Facades\Route::has('employee-letters.show') ? route('employee-letters.show', $ltr) : url('/employee-letters/'.$ltr->id) }}" class="fw-bold font-monospace text-primary text-decoration-none">
                                        {{ $ltr->reference_number ?: 'LTR-#'.$ltr->id }}
                                    </a>
                                </td>
                                <td>
                                    <span class="badge {{ $ltr->badge_class }} px-2 py-1">
                                        <i class="{{ $ltr->icon }} me-1"></i>{{ $ltr->type_label }}
                                    </span>
                                </td>
                                <td>
                                    <strong class="text-dark small d-block">{{ $ltr->title }}</strong>
                                    <small class="text-muted">{{ Str::limit(strip_tags($ltr->content), 60) }}</small>
                                </td>
                                <td>
                                    <small class="text-dark">{{ optional($ltr->issued_date)->format('d M Y') }}</small>
                                </td>
                                <td>
                                    @if($ltr->acknowledgement_status === 'acknowledged')
                                        <span class="badge bg-success small"><i class="fa-solid fa-check me-1"></i>Signed</span>
                                    @elseif($ltr->acknowledgement_status === 'pending')
                                        <span class="badge bg-warning text-dark small">Pending</span>
                                    @else
                                        <span class="badge bg-danger small">Refused</span>
                                    @endif
                                </td>
                                <td class="text-end pe-3">
                                    <div class="btn-group btn-group-sm">
                                        <a href="{{ \Illuminate\Support\Facades\Route::has('employee-letters.show') ? route('employee-letters.show', $ltr) : url('/employee-letters/'.$ltr->id) }}" class="btn btn-outline-primary" title="View">
                                            <i class="fa-solid fa-eye"></i>
                                        </a>
                                        <a href="{{ \Illuminate\Support\Facades\Route::has('employee-letters.print') ? route('employee-letters.print', $ltr) : url('/employee-letters/'.$ltr->id.'/print') }}" target="_blank" class="btn btn-outline-secondary" title="Print Letterhead">
                                            <i class="fa-solid fa-print"></i>
                                        </a>
                                        @if($ltr->attachment_path)
                                        <a href="{{ asset('storage/' . $ltr->attachment_path) }}" target="_blank" class="btn btn-outline-success" title="Attachment">
                                            <i class="fa-solid fa-paperclip"></i>
                                        </a>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @else
                <div class="text-center py-4 text-muted">
                    <i class="fa-solid fa-envelope-open-text fa-2x mb-2 d-block opacity-25"></i>
                    <p class="small mb-2">No official letters or warning notices issued to this employee yet.</p>
                    <a href="{{ \Illuminate\Support\Facades\Route::has('employee-letters.create') ? route('employee-letters.create', ['employee_id' => $employee->id]) : url('/employee-letters/create?employee_id='.$employee->id) }}" class="btn btn-sm btn-outline-primary">
                        <i class="fa-solid fa-plus me-1"></i> Issue Thanks / Warning Letter
                    </a>
                </div>
                @endif
            </div>
        </div>
    </div>

    {{-- ============================
         Right Column: Profile Sidebar
    ============================= --}}
    <div class="col-lg-4">

        {{-- Employee Photo & Identity Card --}}
        <div class="card border-0 shadow-sm mb-3 overflow-hidden">
            <div style="background: linear-gradient(135deg, #1e40af 0%, #0ea5e9 100%); height: 80px;"></div>
            <div class="card-body pt-0 text-center">
                <div class="mb-2" style="margin-top:-45px;">
                    @if($employee->photo)
                        <img src="{{ uploaded_asset($employee->photo) }}" alt="{{ $employee->full_name }}"
                             class="rounded-circle border border-4 border-white shadow"
                             style="width:90px;height:90px;object-fit:cover;">
                    @else
                        <div class="rounded-circle border border-4 border-white shadow d-inline-flex align-items-center justify-content-center bg-primary text-white fw-bold"
                             style="width:90px;height:90px;font-size:2rem;">
                            {{ strtoupper(substr($employee->first_name, 0, 1)) }}{{ strtoupper(substr($employee->last_name ?? '', 0, 1)) }}
                        </div>
                    @endif
                </div>
                <h6 class="mb-0 fw-bold">{{ $employee->full_name }}</h6>
                <small class="text-muted">{{ $employee->role_title }}</small>
                <div class="mt-2">
                    @if($employee->is_approved_by_gm)
                        <span class="badge bg-success"><i class="fa-solid fa-circle-check me-1"></i>GM Approved</span>
                    @elseif($employee->gm_approval_status === 'rejected')
                        <span class="badge bg-danger"><i class="fa-solid fa-triangle-exclamation me-1"></i>Returned by GM</span>
                    @else
                        <span class="badge bg-warning text-dark"><i class="fa-solid fa-clock me-1"></i>Pending GM</span>
                    @endif
                    @if($employee->status === 'active')
                        <span class="badge bg-success">Active</span>
                    @elseif($employee->status === 'suspended')
                        <span class="badge bg-warning text-dark">Suspended</span>
                    @else
                        <span class="badge bg-danger">Terminated</span>
                    @endif
                </div>
                <hr class="my-3">
                <div class="row g-2 text-start small">
                    <div class="col-12 d-flex align-items-center gap-2 py-1">
                        <i class="fa-solid fa-id-card text-primary" style="width:20px;"></i>
                        <span class="text-muted">ID:</span>
                        <span class="font-monospace fw-bold">{{ $employee->employee_code }}</span>
                    </div>
                    @if($employee->phone)
                    <div class="col-12 d-flex align-items-center gap-2 py-1">
                        <i class="fa-solid fa-phone text-success" style="width:20px;"></i>
                        <span class="text-muted">Phone:</span>
                        <span>{{ $employee->phone }}</span>
                    </div>
                    @endif
                    @if($employee->email)
                    <div class="col-12 d-flex align-items-center gap-2 py-1">
                        <i class="fa-solid fa-envelope text-info" style="width:20px;"></i>
                        <span class="text-muted">Email:</span>
                        <span class="text-truncate">{{ $employee->email }}</span>
                    </div>
                    @endif
                    <div class="col-12 d-flex align-items-center gap-2 py-1">
                        <i class="fa-solid fa-calendar text-warning" style="width:20px;"></i>
                        <span class="text-muted">Joined:</span>
                        <span>{{ optional($employee->date_of_joining)->format('d M Y') ?? 'N/A' }}</span>
                    </div>
                    @if($employee->department)
                    <div class="col-12 d-flex align-items-center gap-2 py-1">
                        <i class="fa-solid fa-building text-secondary" style="width:20px;"></i>
                        <span class="text-muted">Dept:</span>
                        <span>{{ $employee->department }}</span>
                    </div>
                    @endif
                </div>
            </div>
        </div>

        {{-- Quick Stats Tiles --}}
        <div class="row g-2 mb-3">
            <div class="col-6">
                <div class="card border-0 shadow-sm text-center h-100" style="background:linear-gradient(135deg,#dbeafe,#eff6ff);">
                    <div class="card-body py-3">
                        <i class="fa-solid fa-computer fa-2x text-blue-600 mb-2" style="color:#2563eb;"></i>
                        <div class="h4 fw-bold mb-0" style="color:#1e40af;">{{ $totalActiveCount }}</div>
                        <small class="text-muted">Assets Assigned</small>
                    </div>
                </div>
            </div>
            <div class="col-6">
                <div class="card border-0 shadow-sm text-center h-100" style="background:linear-gradient(135deg,#dcfce7,#f0fdf4);">
                    <div class="card-body py-3">
                        <i class="fa-solid fa-graduation-cap fa-2x mb-2" style="color:#16a34a;"></i>
                        <div class="h4 fw-bold mb-0" style="color:#15803d;">{{ $employee->education()->count() }}</div>
                        <small class="text-muted">Education Records</small>
                    </div>
                </div>
            </div>
            <div class="col-6">
                <div class="card border-0 shadow-sm text-center h-100" style="background:linear-gradient(135deg,#fef9c3,#fefce8);">
                    <div class="card-body py-3">
                        <i class="fa-solid fa-briefcase fa-2x mb-2" style="color:#ca8a04;"></i>
                        <div class="h4 fw-bold mb-0" style="color:#a16207;">{{ $employee->experience()->count() }}</div>
                        <small class="text-muted">Work Positions</small>
                    </div>
                </div>
            </div>
            <div class="col-6">
                @php
                    // External work experience months
                    $totalExpMonthsRaw = 0;
                    foreach($employee->experience as $exp) {
                        if ($exp->start_date) {
                            $end = $exp->end_date ?? now();
                            $totalExpMonthsRaw += $exp->start_date->diffInMonths($end);
                        }
                    }
                    // Add tenure at THIS company from date_of_joining
                    $companyTenureMonthsRaw = 0;
                    if ($employee->date_of_joining) {
                        $companyTenureMonthsRaw = $employee->date_of_joining->diffInMonths(now());
                        $totalExpMonthsRaw += $companyTenureMonthsRaw;
                    }
                    $totalYears       = intdiv($totalExpMonthsRaw, 12);
                    $totalMonths      = $totalExpMonthsRaw % 12;
                    $compTenureYears  = intdiv($companyTenureMonthsRaw, 12);
                    $compTenureMonths = $companyTenureMonthsRaw % 12;
                @endphp
                <div class="card border-0 shadow-sm text-center h-100" style="background:linear-gradient(135deg,#fce7f3,#fdf2f8);">
                    <div class="card-body py-3">
                        <i class="fa-solid fa-clock fa-2x mb-2" style="color:#db2777;"></i>
                        <div class="h5 fw-bold mb-0" style="color:#be185d;">{{ $totalYears }}y {{ $totalMonths }}m</div>
                        <small class="text-muted">Total Experience</small>
                        @if($companyTenureMonthsRaw > 0)
                        <div class="mt-1 pt-1 border-top">
                            <small class="text-muted d-block" style="font-size:0.72rem; line-height:1.3;">
                                <i class="fa-solid fa-building me-1 text-primary"></i>
                                <strong>This Company:</strong><br>
                                {{ $compTenureYears > 0 ? "{$compTenureYears}y " : '' }}{{ $compTenureMonths }}m
                            </small>
                        </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        {{-- Salary Card --}}
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-body">
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <h6 class="mb-0 fw-bold"><i class="fa-solid fa-money-bill-wave text-success me-2"></i>Compensation</h6>
                    @if($employee->bank_name)
                    <span class="badge bg-light text-dark border">{{ $employee->bank_name }}</span>
                    @endif
                </div>
                <div class="p-3 rounded-3" style="background:linear-gradient(135deg,#f0fdf4,#dcfce7);">
                    <small class="text-muted d-block">Monthly Base Salary</small>
                    <div class="h4 fw-bold text-success mb-0">Br {{ number_format($employee->basic_salary, 2) }}</div>
                    @if($employee->account_number)
                    <small class="text-muted">Account: {{ $employee->account_number }}</small>
                    @endif
                </div>
                @if($employee->payrolls()->count() > 0)
                @php $latestPayroll = $employee->payrolls()->latest()->first(); @endphp
                <hr class="my-3">
                <small class="text-muted d-block mb-2 fw-semibold">Latest Payroll — {{ $latestPayroll->payroll_month ?? '' }}</small>
                <div class="row g-2 text-center">
                    <div class="col-6">
                        <div class="p-2 rounded bg-light">
                            <small class="text-muted d-block">Gross</small>
                            <strong class="text-success">Br {{ number_format($latestPayroll->gross_salary ?? 0, 2) }}</strong>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="p-2 rounded bg-light">
                            <small class="text-muted d-block">Deduction</small>
                            <strong class="text-danger">Br {{ number_format($latestPayroll->total_deduction ?? 0, 2) }}</strong>
                        </div>
                    </div>
                </div>
                @endif
            </div>
        </div>

        {{-- Leave Balance Card --}}
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-bottom-0 pt-3 pb-0">
                <h6 class="mb-0 fw-bold"><i class="fa-solid fa-umbrella-beach text-info me-2"></i>Leave Balance {{ date('Y') }}</h6>
            </div>
            <div class="card-body">
                @php
                    $leaveBalance = $employee->leaveBalances()->where('year', date('Y'))->latest()->first();
                @endphp
                @if($leaveBalance)
                @php
                    $casualUsed  = $leaveBalance->casual_used ?? 0;
                    $casualTotal = $leaveBalance->casual_allotted ?? 0;
                    $casualPct   = $casualTotal > 0 ? round(($casualUsed / $casualTotal) * 100) : 0;
                    $annualUsed  = $leaveBalance->annual_used ?? 0;
                    $annualTotal = $leaveBalance->annual_allotted ?? 0;
                    $annualPct   = $annualTotal > 0 ? round(($annualUsed / $annualTotal) * 100) : 0;
                @endphp
                <div class="mb-4">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <span class="small fw-semibold"><i class="fa-solid fa-mug-hot text-success me-1"></i>Casual Leave</span>
                        <span class="small text-muted">{{ $casualUsed }} used / {{ $casualTotal }} total</span>
                    </div>
                    <div class="progress rounded-pill" style="height:10px;">
                        <div class="progress-bar bg-success" style="width:{{ $casualPct }}%;"></div>
                    </div>
                    <div class="d-flex justify-content-between mt-1">
                        <small class="text-muted">{{ $casualTotal - $casualUsed }} days remaining</small>
                        <small class="{{ $casualPct > 80 ? 'text-danger' : 'text-muted' }}">{{ $casualPct }}%</small>
                    </div>
                </div>
                <div>
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <span class="small fw-semibold"><i class="fa-solid fa-plane-departure text-info me-1"></i>Annual Leave</span>
                        <span class="small text-muted">{{ $annualUsed }} used / {{ $annualTotal }} total</span>
                    </div>
                    <div class="progress rounded-pill" style="height:10px;">
                        <div class="progress-bar bg-info" style="width:{{ $annualPct }}%;"></div>
                    </div>
                    <div class="d-flex justify-content-between mt-1">
                        <small class="text-muted">{{ $annualTotal - $annualUsed }} days remaining</small>
                        <small class="{{ $annualPct > 80 ? 'text-danger' : 'text-muted' }}">{{ $annualPct }}%</small>
                    </div>
                </div>
                @else
                <div class="text-center py-4 text-muted">
                    <i class="fa-solid fa-calendar-xmark fa-2x mb-2 d-block opacity-25"></i>
                    <p class="mb-0 small">No leave balance for {{ date('Y') }}</p>
                </div>
                @endif
            </div>
        </div>

    </div>
</div>

{{-- ===================== ATTENDANCE HISTORY ===================== --}}
<div class="row g-3 mt-1">
    <div class="col-12">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-light d-flex align-items-center justify-content-between">
                <h5 class="mb-0"><i class="fa-solid fa-clock text-primary me-2"></i>Attendance History</h5>
                @if($employee->device_user_id)
                    <span class="badge bg-success"><i class="fa-solid fa-link me-1"></i>Device ID: {{ $employee->device_user_id }}</span>
                @else
                    <span class="badge bg-warning text-dark"><i class="fa-solid fa-unlink me-1"></i>No Device Linked — Set Device User ID in Edit</span>
                @endif
            </div>
            <div class="card-body p-0">
                {{-- Summary Stats --}}
                @php
                    $thisMonth = $employee->attendances()->whereMonth('attendance_date', now()->month)->whereYear('attendance_date', now()->year)->get();
                    $presentCount = $thisMonth->where('status', 'present')->count();
                    $absentCount  = $thisMonth->where('status', 'absent')->count();
                    $lateCount    = $thisMonth->where('status', 'half_day')->count();
                    $totalHours   = $thisMonth->sum('hours_worked');
                @endphp
                <div class="row g-0 border-bottom">
                    <div class="col-3 text-center py-3 border-end">
                        <div class="h4 mb-0 text-success fw-bold">{{ $presentCount }}</div>
                        <small class="text-muted">Present (This Month)</small>
                    </div>
                    <div class="col-3 text-center py-3 border-end">
                        <div class="h4 mb-0 text-danger fw-bold">{{ $absentCount }}</div>
                        <small class="text-muted">Absent (This Month)</small>
                    </div>
                    <div class="col-3 text-center py-3 border-end">
                        <div class="h4 mb-0 text-warning fw-bold">{{ $lateCount }}</div>
                        <small class="text-muted">Half Day (This Month)</small>
                    </div>
                    <div class="col-3 text-center py-3">
                        <div class="h4 mb-0 text-info fw-bold">{{ number_format($totalHours, 1) }}</div>
                        <small class="text-muted">Hours Worked (This Month)</small>
                    </div>
                </div>

                {{-- Filter Form --}}
                <div class="p-3 border-bottom bg-light d-flex gap-2 flex-wrap align-items-end">
                    <form method="GET" action="{{ route('employees.show', $employee) }}" class="d-flex gap-2 flex-wrap align-items-end mb-0">
                        <div>
                            <label class="form-label form-label-sm mb-1">From</label>
                            <input type="date" name="att_from" value="{{ request('att_from', now()->startOfMonth()->format('Y-m-d')) }}" class="form-control form-control-sm">
                        </div>
                        <div>
                            <label class="form-label form-label-sm mb-1">To</label>
                            <input type="date" name="att_to" value="{{ request('att_to', now()->format('Y-m-d')) }}" class="form-control form-control-sm">
                        </div>
                        <button type="submit" class="btn btn-sm btn-primary">Filter</button>
                        <a href="{{ route('employees.show', $employee) }}" class="btn btn-sm btn-outline-secondary">Reset</a>
                    </form>
                </div>

                {{-- Attendance Table --}}
                @php
                    $attFrom = request('att_from', now()->startOfMonth()->format('Y-m-d'));
                    $attTo   = request('att_to', now()->format('Y-m-d'));
                    $attendances = $employee->attendances()
                        ->whereBetween('attendance_date', [$attFrom, $attTo])
                        ->orderBy('attendance_date', 'desc')
                        ->paginate(20, ['*'], 'att_page');
                @endphp
                <div class="table-responsive">
                    <table class="table table-sm table-hover mb-0 align-middle">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-3">Date</th>
                                <th>Status</th>
                                <th>Check In</th>
                                <th>Check Out</th>
                                <th>Hours Worked</th>
                                <th>Source</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($attendances as $att)
                            <tr>
                                <td class="ps-3 fw-semibold">{{ $att->attendance_date->format('D, d M Y') }}</td>
                                <td>
                                    @php
                                        $statusMap = [
                                            'present'  => ['bg-success',  'Present'],
                                            'absent'   => ['bg-danger',   'Absent'],
                                            'half_day' => ['bg-warning text-dark', 'Half Day'],
                                            'leave'    => ['bg-info',     'On Leave'],
                                            'holiday'  => ['bg-secondary','Holiday'],
                                            'weekend'  => ['bg-light text-dark border','Weekend'],
                                        ];
                                        [$cls, $lbl] = $statusMap[$att->status] ?? ['bg-secondary', $att->status];
                                    @endphp
                                    <span class="badge {{ $cls }}">{{ $lbl }}</span>
                                </td>
                                <td>{{ $att->check_in  ? \Carbon\Carbon::parse($att->check_in)->format('H:i') : '—' }}</td>
                                <td>{{ $att->check_out ? \Carbon\Carbon::parse($att->check_out)->format('H:i') : '—' }}</td>
                                <td>
                                    @if($att->hours_worked)
                                        <span class="text-{{ $att->hours_worked >= 8 ? 'success' : 'warning' }}">
                                            {{ number_format($att->hours_worked, 1) }} hrs
                                        </span>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td>
                                    <span class="badge bg-light text-dark border">
                                        <i class="fa-solid fa-{{ $att->source === 'device' ? 'fingerprint' : 'keyboard' }} me-1"></i>
                                        {{ ucfirst(str_replace('_', ' ', $att->source ?? 'manual')) }}
                                    </span>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="6" class="text-center text-muted py-4">
                                    <i class="fa-solid fa-calendar-xmark fa-2x mb-2 d-block opacity-25"></i>
                                    No attendance records found for this period.
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @if($attendances->hasPages())
                <div class="p-3">{{ $attendances->appends(request()->except('att_page'))->links() }}</div>
                @endif
            </div>
        </div>
    </div>
</div> {{-- Close .row.g-3 --}}

{{-- =========================================================================
     MODALS SECTION (Placed at bottom outside all grid containers)
========================================================================= --}}

{{-- GM Rejection Modal (Only for GM Role) --}}
@role('gm')
@if(!$employee->is_approved_by_gm)
<div class="modal fade" id="rejectEmployeeModal" tabindex="-1" aria-labelledby="rejectEmployeeModalLabel" aria-hidden="true" style="z-index: 1060;">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 14px; overflow: hidden;">
            <form method="POST" action="{{ route('employees.reject', $employee) }}">
                @csrf
                @method('PUT')
                <div class="modal-header bg-danger text-white py-3">
                    <h5 class="modal-title fs-6 fw-bold" id="rejectEmployeeModalLabel">
                        <i class="fa-solid fa-triangle-exclamation me-2"></i>Reject & Return to HR Officer
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="alert alert-light border mb-3">
                        <div class="small text-muted">Employee Under Review:</div>
                        <strong class="fs-6 text-dark">{{ $employee->full_name }}</strong>
                        <div class="small text-muted font-monospace">{{ $employee->employee_code }} • {{ $employee->role_title }}</div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Correction Instructions for HR Officer <span class="text-danger">*</span></label>
                        <textarea name="rejection_reason" class="form-control" rows="4" required
                                  placeholder="State clearly what the HR Officer needs to fix before resubmitting (e.g. salary exceeds scale, guarantee letter missing, wrong project assignment)..."></textarea>
                        <div class="form-text">This message will be shown to the HR Officer on their employee dashboard and profile.</div>
                    </div>

                    {{-- Quick Reason Templates --}}
                    <div>
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <small class="text-muted fw-bold"><i class="fa-solid fa-tags me-1 text-primary"></i>Quick Reason Templates <span class="badge bg-light text-secondary border fw-normal">Select Multiple</span>:</small>
                            <button type="button" class="btn-link btn-xs text-muted p-0 text-decoration-none" onclick="clearReasonTemplates(this)" style="font-size: 0.75rem;">
                                <i class="fa-solid fa-rotate-left me-1"></i>Reset Selection
                            </button>
                        </div>
                        <div class="d-flex flex-wrap gap-1">
                            <button type="button" class="btn btn-xs btn-outline-secondary py-1 px-2.5 rounded-pill quick-template-btn d-inline-flex align-items-center gap-1"
                                    data-text="Basic salary or allowances exceed the approved compensation scale. Please adjust."
                                    onclick="toggleReasonTemplate(this)">
                                <i class="fa-solid fa-check d-none template-check-icon text-white me-1"></i>
                                <span>💰 Salary Discrepancy</span>
                            </button>
                            <button type="button" class="btn btn-xs btn-outline-secondary py-1 px-2.5 rounded-pill quick-template-btn d-inline-flex align-items-center gap-1"
                                    data-text="A valid guarantee letter is mandatory for this role before approval."
                                    onclick="toggleReasonTemplate(this)">
                                <i class="fa-solid fa-check d-none template-check-icon text-white me-1"></i>
                                <span>📜 Guarantee Letter Missing</span>
                            </button>
                            <button type="button" class="btn btn-xs btn-outline-secondary py-1 px-2.5 rounded-pill quick-template-btn d-inline-flex align-items-center gap-1"
                                    data-text="Incorrect department or project site assigned. Please re-assign."
                                    onclick="toggleReasonTemplate(this)">
                                <i class="fa-solid fa-check d-none template-check-icon text-white me-1"></i>
                                <span>📍 Wrong Site / Dept</span>
                            </button>
                            <button type="button" class="btn btn-xs btn-outline-secondary py-1 px-2.5 rounded-pill quick-template-btn d-inline-flex align-items-center gap-1"
                                    data-text="Educational certificate or professional license photo is missing/illegible."
                                    onclick="toggleReasonTemplate(this)">
                                <i class="fa-solid fa-check d-none template-check-icon text-white me-1"></i>
                                <span>📁 Missing Documents</span>
                            </button>
                            <button type="button" class="btn btn-xs btn-outline-secondary py-1 px-2.5 rounded-pill quick-template-btn d-inline-flex align-items-center gap-1"
                                    data-text="Contract type, job title, or date of joining needs correction."
                                    onclick="toggleReasonTemplate(this)">
                                <i class="fa-solid fa-check d-none template-check-icon text-white me-1"></i>
                                <span>📋 Contract / Role Details</span>
                            </button>
                            <button type="button" class="btn btn-xs btn-outline-secondary py-1 px-2.5 rounded-pill quick-template-btn d-inline-flex align-items-center gap-1"
                                    data-text="Bank account number or bank name is missing or invalid."
                                    onclick="toggleReasonTemplate(this)">
                                <i class="fa-solid fa-check d-none template-check-icon text-white me-1"></i>
                                <span>🏦 Bank Account Details</span>
                            </button>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light py-3">
                    <button type="button" class="btn btn-secondary btn-sm px-3" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger btn-sm fw-bold px-4">
                        <i class="fa-solid fa-paper-plane me-1"></i> Send Back to HR Officer
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif
@endrole

{{-- Fixed Asset Modals (Specs & Return) --}}
@php
    $modalAssetUnits = $employee->assignedFixedAssets ?? collect();
@endphp

@foreach($modalAssetUnits as $fUnit)
@php
    $condBadge = $fUnit->condition_badge;
    $assetCategory = strtolower($fUnit->parentAsset->category ?? '');
    $assetIcon = match(true) {
        str_contains($assetCategory, 'vehicle') || str_contains($assetCategory, 'car') => 'fa-car-side',
        str_contains($assetCategory, 'machinery') || str_contains($assetCategory, 'heavy') => 'fa-truck-monster',
        str_contains($assetCategory, 'furniture') || str_contains($assetCategory, 'chair') || str_contains($assetCategory, 'table') => 'fa-chair',
        str_contains($assetCategory, 'computer') || str_contains($assetCategory, 'it') || str_contains($assetCategory, 'electronics') => 'fa-laptop',
        str_contains($assetCategory, 'tool') => 'fa-screwdriver-wrench',
        str_contains($assetCategory, 'office') => 'fa-paperclip',
        default => 'fa-box-open',
    };
@endphp

{{-- 1. Asset Specifications Modal --}}
@php
    $unitPrice = $fUnit->purchase_price ?? $fUnit->parentAsset->unit_cost ?? 0;
@endphp
<div class="modal fade" id="viewAssetUnitModal_{{ $fUnit->id }}" tabindex="-1" aria-labelledby="viewAssetUnitModalLabel_{{ $fUnit->id }}" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable" style="max-width: 680px;">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 16px; overflow: hidden;">
            {{-- Modal Header --}}
            <div class="modal-header py-3 px-4" style="background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%); color: #fff;">
                <div class="d-flex align-items-center gap-3">
                    <div class="rounded-3 p-2 text-warning d-flex align-items-center justify-content-center" style="background: rgba(245, 158, 11, 0.15); width: 44px; height: 44px;">
                        <i class="fa-solid {{ $assetIcon }} fa-xl"></i>
                    </div>
                    <div>
                        <h5 class="modal-title fw-bold mb-0 text-white" id="viewAssetUnitModalLabel_{{ $fUnit->id }}">
                            {{ $fUnit->parentAsset->name ?? 'Fixed Asset' }}
                        </h5>
                        <div class="d-flex align-items-center gap-2 mt-1">
                            <span class="badge bg-warning text-dark font-monospace fw-bold px-2 py-1">{{ $fUnit->unit_code }}</span>
                            <span class="badge bg-secondary text-light">{{ $fUnit->parentAsset->category ?? 'Fixed Asset' }}</span>
                        </div>
                    </div>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            {{-- Modal Body --}}
            <div class="modal-body p-4" style="background: #fafafa;">
                {{-- Quick Summary Row --}}
                <div class="row g-2 mb-3">
                    <div class="col-6 col-sm-3">
                        <div class="p-3 bg-white border rounded-3 text-center shadow-xs h-100">
                            <small class="text-muted d-block fw-semibold" style="font-size: 0.7rem; letter-spacing: 0.5px;">UNIT PRICE / VALUE</small>
                            <span class="fw-bold text-success fs-6">Br {{ number_format($unitPrice, 2) }}</span>
                        </div>
                    </div>
                    <div class="col-6 col-sm-3">
                        <div class="p-3 bg-white border rounded-3 text-center shadow-xs h-100">
                            <small class="text-muted d-block fw-semibold" style="font-size: 0.7rem; letter-spacing: 0.5px;">CONDITION</small>
                            <span class="badge {{ $condBadge['class'] }} mt-1">{{ $condBadge['label'] }}</span>
                        </div>
                    </div>
                    <div class="col-6 col-sm-3">
                        <div class="p-3 bg-white border rounded-3 text-center shadow-xs h-100">
                            <small class="text-muted d-block fw-semibold" style="font-size: 0.7rem; letter-spacing: 0.5px;">ASSIGNED SINCE</small>
                            <span class="fw-bold text-dark small mt-1 d-block">{{ $fUnit->assigned_date ? $fUnit->assigned_date->format('M d, Y') : 'N/A' }}</span>
                        </div>
                    </div>
                    <div class="col-6 col-sm-3">
                        <div class="p-3 bg-white border rounded-3 text-center shadow-xs h-100">
                            <small class="text-muted d-block fw-semibold" style="font-size: 0.7rem; letter-spacing: 0.5px;">STORE LOCATION</small>
                            <span class="fw-semibold text-secondary small text-truncate d-block mt-1">{{ $fUnit->parentAsset->store->name ?? 'Main Central Store' }}</span>
                        </div>
                    </div>
                </div>

                {{-- Full Technical & Physical Specs Grid (Only shown if unit has physical specs) --}}
                @php
                    $hasPhysicalSpecs = $fUnit->plate_number || $fUnit->serial_number || $fUnit->brand || $fUnit->model || $fUnit->year || $fUnit->chassis_number || $fUnit->engine_number || $fUnit->warranty_expiry;
                @endphp

                @if($hasPhysicalSpecs)
                <div class="card border-0 shadow-xs mb-3 bg-white" style="border-radius: 10px; border: 1px solid #e5e7eb !important;">
                    <div class="card-header bg-light py-2 px-3 border-bottom">
                        <h6 class="mb-0 small fw-bold text-uppercase text-secondary">
                            <i class="fa-solid fa-list-check me-2 text-primary"></i>Equipment Identification & Details
                        </h6>
                    </div>
                    <div class="card-body p-3">
                        <div class="row g-2">
                            @if($fUnit->plate_number)
                            <div class="col-sm-6">
                                <div class="p-2 border rounded bg-light bg-opacity-50">
                                    <small class="text-muted d-block" style="font-size: 0.72rem;"><i class="fa-solid fa-car-side me-1 text-primary"></i>Plate Number</small>
                                    <strong class="text-primary font-monospace fs-6">{{ $fUnit->plate_number }}</strong>
                                </div>
                            </div>
                            @endif

                            @if($fUnit->serial_number)
                            <div class="col-sm-6">
                                <div class="p-2 border rounded bg-light bg-opacity-50">
                                    <small class="text-muted d-block" style="font-size: 0.72rem;"><i class="fa-solid fa-barcode me-1 text-dark"></i>Serial Number</small>
                                    <strong class="text-dark font-monospace fs-6">{{ $fUnit->serial_number }}</strong>
                                </div>
                            </div>
                            @endif

                            @if($fUnit->brand || $fUnit->model)
                            <div class="col-sm-6">
                                <div class="p-2 border rounded bg-light bg-opacity-50">
                                    <small class="text-muted d-block" style="font-size: 0.72rem;"><i class="fa-solid fa-tag me-1 text-secondary"></i>Brand & Model</small>
                                    <strong class="text-dark">{{ trim("{$fUnit->brand} {$fUnit->model}") }}</strong>
                                </div>
                            </div>
                            @endif

                            @if($fUnit->year)
                            <div class="col-sm-6">
                                <div class="p-2 border rounded bg-light bg-opacity-50">
                                    <small class="text-muted d-block" style="font-size: 0.72rem;"><i class="fa-solid fa-calendar me-1 text-info"></i>Year of Make</small>
                                    <strong class="text-dark">{{ $fUnit->year }}</strong>
                                </div>
                            </div>
                            @endif

                            @if($fUnit->chassis_number)
                            <div class="col-sm-6">
                                <div class="p-2 border rounded bg-light bg-opacity-50">
                                    <small class="text-muted d-block" style="font-size: 0.72rem;"><i class="fa-solid fa-gears me-1 text-warning"></i>Chassis / VIN</small>
                                    <strong class="text-dark font-monospace">{{ $fUnit->chassis_number }}</strong>
                                </div>
                            </div>
                            @endif

                            @if($fUnit->engine_number)
                            <div class="col-sm-6">
                                <div class="p-2 border rounded bg-light bg-opacity-50">
                                    <small class="text-muted d-block" style="font-size: 0.72rem;"><i class="fa-solid fa-wrench me-1 text-danger"></i>Engine Number</small>
                                    <strong class="text-dark font-monospace">{{ $fUnit->engine_number }}</strong>
                                </div>
                            </div>
                            @endif

                            @if($fUnit->warranty_expiry)
                            <div class="col-sm-6">
                                <div class="p-2 border rounded bg-light bg-opacity-50">
                                    <small class="text-muted d-block" style="font-size: 0.72rem;"><i class="fa-solid fa-shield-halved me-1 text-success"></i>Warranty Expiry</small>
                                    <strong class="text-dark">{{ optional($fUnit->warranty_expiry)->format('d M Y') ?? 'N/A' }}</strong>
                                </div>
                            </div>
                            @endif
                        </div>
                    </div>
                </div>
                @endif

                {{-- Location & Department Details --}}
                <div class="card border-0 shadow-xs mb-3 bg-white" style="border-radius: 10px; border: 1px solid #e5e7eb !important;">
                    <div class="card-header bg-light py-2 px-3 border-bottom">
                        <h6 class="mb-0 small fw-bold text-uppercase text-secondary">
                            <i class="fa-solid fa-location-dot me-2 text-danger"></i>Assignment Location & User
                        </h6>
                    </div>
                    <div class="card-body p-3">
                        <div class="row g-2">
                            <div class="col-sm-6">
                                <small class="text-muted d-block" style="font-size: 0.75rem;">Assigned User</small>
                                <strong class="text-dark">{{ $employee->full_name }}</strong>
                                <div class="small text-muted">{{ $employee->employee_code }} • {{ $employee->role_title }}</div>
                            </div>
                            <div class="col-sm-6">
                                <small class="text-muted d-block" style="font-size: 0.75rem;">Department & Location</small>
                                <strong class="text-dark">{{ $fUnit->current_location ?: ($employee->department . ' (' . ($employee->project->name ?? 'Head Office') . ')') }}</strong>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Technical Specifications --}}
                @if($fUnit->specifications || $fUnit->parentAsset->description)
                <div class="card border-0 shadow-xs mb-3 bg-white" style="border-radius: 10px; border: 1px solid #e5e7eb !important;">
                    <div class="card-header bg-light py-2 px-3 border-bottom">
                        <h6 class="mb-0 small fw-bold text-uppercase text-secondary">
                            <i class="fa-solid fa-file-lines me-2 text-primary"></i>Technical Specifications & Description
                        </h6>
                    </div>
                    <div class="card-body p-3">
                        <p class="mb-0 text-dark small" style="white-space: pre-line;">{{ $fUnit->specifications ?: $fUnit->parentAsset->description }}</p>
                    </div>
                </div>
                @endif

                {{-- Assignment Notes --}}
                @if($fUnit->notes)
                <div class="card border-0 shadow-xs bg-white" style="border-radius: 10px; border: 1px solid #e5e7eb !important;">
                    <div class="card-header bg-light py-2 px-3 border-bottom">
                        <h6 class="mb-0 small fw-bold text-uppercase text-secondary">
                            <i class="fa-solid fa-note-sticky me-2 text-warning"></i>Assignment Notes & History
                        </h6>
                    </div>
                    <div class="card-body p-3">
                        <p class="mb-0 text-dark small">{{ $fUnit->notes }}</p>
                    </div>
                </div>
                @endif
            </div>

            {{-- Modal Footer --}}
            <div class="modal-footer bg-light py-2 px-4 d-flex justify-content-between">
                <span class="small text-muted"><i class="fa-solid fa-user me-1"></i>Assigned to: <strong>{{ $employee->full_name }}</strong></span>
                <button type="button" class="btn btn-sm btn-secondary px-4 fw-semibold" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

{{-- 2. Asset Return Modal --}}
<div class="modal fade" id="returnFixedUnitModal_{{ $fUnit->id }}" tabindex="-1" aria-labelledby="returnFixedUnitModalLabel_{{ $fUnit->id }}" aria-hidden="true" style="z-index: 1060;">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 14px; overflow: hidden;">
            <form action="{{ route('hr.fixed-assets.return', $fUnit->id) }}" method="POST">
                @csrf
                <div class="modal-header bg-warning text-dark py-3">
                    <h6 class="modal-title fw-bold" id="returnFixedUnitModalLabel_{{ $fUnit->id }}">
                        <i class="fa-solid fa-arrow-rotate-left me-1"></i> Return {{ $fUnit->unit_code }}
                    </h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-3">
                    <p class="small text-muted mb-3">
                        Returning <strong>{{ $fUnit->unit_code }}</strong> ({{ $fUnit->parentAsset->name ?? 'Asset' }}) back to store. It will immediately become available for other assignments.
                    </p>
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Return Condition <span class="text-danger">*</span></label>
                        <select name="condition" class="form-select form-select-sm" required>
                            <option value="good" @selected($fUnit->condition==='good')>Good Condition</option>
                            <option value="fair" @selected($fUnit->condition==='fair')>Fair</option>
                            <option value="needs_repair" @selected($fUnit->condition==='needs_repair')>Needs Repair / Maintenance</option>
                            <option value="damaged" @selected($fUnit->condition==='damaged')>Damaged</option>
                        </select>
                    </div>
                    <div>
                        <label class="form-label small fw-bold">Return Notes</label>
                        <input type="text" name="notes" class="form-control form-control-sm" placeholder="Reason for return...">
                    </div>
                </div>
                <div class="modal-footer bg-light py-2">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-sm btn-warning fw-bold px-3">Confirm Return</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endforeach

<script>
function openRejectModal() {
    const modalEl = document.getElementById('rejectEmployeeModal');
    clearReasonTemplates(modalEl);
    new bootstrap.Modal(modalEl).show();
}

function toggleReasonTemplate(btn) {
    const modal = btn.closest('.modal');
    const textarea = modal ? modal.querySelector('textarea[name="rejection_reason"]') : null;
    if (!textarea) return;

    const templateText = (btn.dataset.text || btn.textContent).trim();
    const isActive = btn.classList.contains('active');
    const checkIcon = btn.querySelector('.template-check-icon');

    let currentLines = textarea.value.split('\n').map(l => l.trim()).filter(l => l.length > 0);

    if (isActive) {
        // Deselect
        btn.classList.remove('active', 'btn-danger', 'text-white');
        btn.classList.add('btn-outline-secondary');
        if (checkIcon) checkIcon.classList.add('d-none');

        // Remove matching line (ignoring leading bullets)
        currentLines = currentLines.filter(line => {
            const cleanLine = line.replace(/^[•\-\*\d\.]+\s*/, '').trim();
            return cleanLine !== templateText;
        });
    } else {
        // Select
        btn.classList.add('active', 'btn-danger', 'text-white');
        btn.classList.remove('btn-outline-secondary');
        if (checkIcon) checkIcon.classList.remove('d-none');

        const exists = currentLines.some(line => {
            const cleanLine = line.replace(/^[•\-\*\d\.]+\s*/, '').trim();
            return cleanLine === templateText;
        });

        if (!exists) {
            currentLines.push('• ' + templateText);
        }
    }

    // Format lines: ensure bullets if multiple lines
    if (currentLines.length > 1) {
        currentLines = currentLines.map(line => {
            if (!line.startsWith('• ') && !line.startsWith('- ') && !/^\d+\./.test(line)) {
                return '• ' + line;
            }
            return line;
        });
    }

    textarea.value = currentLines.join('\n');
    textarea.focus();
}

function clearReasonTemplates(btnOrModal) {
    const modal = btnOrModal.closest ? btnOrModal.closest('.modal') : btnOrModal;
    if (!modal) return;
    const textarea = modal.querySelector('textarea[name="rejection_reason"]');
    if (textarea) textarea.value = '';

    modal.querySelectorAll('.quick-template-btn').forEach(btn => {
        btn.classList.remove('active', 'btn-danger', 'text-white');
        btn.classList.add('btn-outline-secondary');
        const checkIcon = btn.querySelector('.template-check-icon');
        if (checkIcon) checkIcon.classList.add('d-none');
    });
}

function filterEmployeeAssets() {
    const input = document.getElementById('assetSearchInput');
    if (!input) return;
    const filter = input.value.toLowerCase().trim();
    const rows = document.querySelectorAll('#employeeAssetsTable .asset-row');
    rows.forEach(row => {
        const text = row.getAttribute('data-search') || '';
        row.style.display = (filter === '' || text.includes(filter)) ? '' : 'none';
    });
}
</script>

@endsection
