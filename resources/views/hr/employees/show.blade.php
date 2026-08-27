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

@php
    $empTargetUserIds = array_filter([
        $employee->user_id,
        $employee->id,
    ]);
    if ($employee->email) {
        $foundUId = \App\Models\User::where('email', $employee->email)->value('id');
        if ($foundUId) $empTargetUserIds[] = $foundUId;
    }
    $empTargetUserIds = array_unique($empTargetUserIds);

    $allEmpCoas = \App\Models\ChartOfAccount::whereIn('assigned_to', $empTargetUserIds)->get();

    $empPettyCash = $allEmpCoas->filter(function ($coa) {
        $code = (string) ($coa->code ?? '');
        $name = strtolower($coa->name ?? '');
        $subtype = strtolower($coa->subtype ?? '');
        $type = strtolower($coa->type ?? '');

        return str_starts_with($code, '111')
            || str_starts_with($code, '110')
            || str_contains($name, 'petty')
            || str_contains($name, 'cash')
            || str_contains($name, 'fund')
            || str_contains($name, 'ፔቲ')
            || in_array($subtype, ['cash', 'petty_cash', 'cash_equivalent'])
            || ($type === 'asset' && in_array($subtype, ['cash', 'current_asset', 'asset']));
    });

    if ($empPettyCash->isEmpty() && $allEmpCoas->isNotEmpty()) {
        $empPettyCash = $allEmpCoas->filter(fn($c) => in_array(strtolower($c->type ?? ''), ['asset', 'expense']));
    }

    $empPettyCashBal = (float) $empPettyCash->sum('current_balance');
@endphp

@if($empPettyCash->isNotEmpty())
    <!-- Assigned Petty Cash Balance Card -->
    <div class="card border-0 shadow-sm rounded-4 mb-4 bg-white border border-success-subtle overflow-hidden">
        <div class="card-body p-3 p-md-4">
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
                <div class="d-flex align-items-center gap-3">
                    <div class="rounded-4 bg-success bg-opacity-10 p-3 d-flex align-items-center justify-content-center text-success flex-shrink-0" style="width: 54px; height: 54px;">
                        <i class="fa-solid fa-wallet fa-xl"></i>
                    </div>
                    <div>
                        <div class="d-flex align-items-center gap-2">
                            <span class="text-muted small fw-bold text-uppercase tracking-wider" style="font-size: 0.72rem;">Assigned Petty Cash Custodian</span>
                            <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-2 py-0.5" style="font-size: 0.7rem;">
                                <i class="fa-solid fa-shield-halved me-1"></i>Active Petty Cash Fund
                            </span>
                        </div>
                        <div class="d-flex align-items-baseline gap-2 mt-1">
                            <h3 class="fw-bold mb-0 text-dark font-monospace">ETB {{ number_format($empPettyCashBal, 2) }}</h3>
                            <small class="text-success fw-semibold"><i class="fa-solid fa-circle-check me-1"></i>Available Balance</small>
                        </div>
                    </div>
                </div>
                <div class="d-flex flex-wrap align-items-center gap-2">
                    @foreach($empPettyCash as $coa)
                        <div class="d-inline-flex align-items-center gap-2 bg-light border border-secondary-subtle px-3 py-2 rounded-3 shadow-xs">
                            <div class="rounded-circle bg-success bg-opacity-10 p-1.5 d-flex align-items-center justify-content-center text-success" style="width: 28px; height: 28px;">
                                <i class="fa-solid fa-vault small"></i>
                            </div>
                            <div>
                                <div class="text-secondary fw-semibold" style="font-size: 0.75rem; line-height: 1.2;">{{ $coa->name }} <span class="text-muted font-monospace">({{ $coa->code }})</span></div>
                                <div class="fw-bold text-dark font-monospace" style="font-size: 0.88rem;">ETB {{ number_format($coa->current_balance, 2) }}</div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
@endif

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
                            @if($employee->contract_end_date)
                                <span class="badge bg-info-subtle text-info border border-info-subtle ms-1">
                                    Valid Upto: {{ $employee->contract_end_date->format('d M Y') }}
                                </span>
                            @endif
                        </h6>
                    </div>
                    <div class="col-md-6">
                        <small class="text-muted d-block mb-1">Date of Joining</small>
                        <h6 class="mb-0">{{ optional($employee->date_of_joining)->format('d M Y') ?? 'N/A' }}</h6>
                    </div>
                    <div class="col-md-6">
                        <small class="text-muted d-block mb-1">45-Day Test Period (Probation)</small>
                        <h6 class="mb-0">
                            @if($employee->probation_completed)
                                <span class="badge bg-success"><i class="fa-solid fa-circle-check me-1"></i>Completed / Renewed</span>
                            @elseif($employee->is_test_period_expired)
                                <span class="badge bg-danger"><i class="fa-solid fa-lock me-1"></i>Expired (Lockable)</span>
                            @elseif($employee->show_probation_alert)
                                <span class="badge bg-warning text-dark"><i class="fa-solid fa-clock me-1"></i>Day {{ $employee->days_since_joining }} of 45 ({{ $employee->days_until_probation_end }}d left)</span>
                            @else
                                <span class="badge bg-info text-dark"><i class="fa-solid fa-shield-halved me-1"></i>Day {{ $employee->days_since_joining }} of 45 (Active)</span>
                            @endif
                        </h6>
                    </div>
                    <div class="col-md-6">
                        <small class="text-muted d-block mb-1">Status</small>
                        <h6 class="mb-0">
                            @if($employee->status === 'active')
                                <span class="badge bg-success">Active</span>
                            @elseif($employee->status === 'suspended')
                                <span class="badge bg-warning text-dark">Suspended</span>
                            @else
                                <span class="badge bg-danger">Terminated / Locked</span>
                            @endif

                            @if($employee->is_approved_by_gm)
                                <span class="badge bg-success ms-1"><i class="fa-solid fa-check-circle me-1"></i>Approved by GM</span>
                            @else
                                <span class="badge bg-warning ms-1 text-dark"><i class="fa-solid fa-clock me-1"></i>Pending GM Approval</span>
                            @endif
                        </h6>
                    </div>
                    @if($employee->lock_reason)
                    <div class="col-12">
                        <div class="alert alert-danger py-2 px-3 mb-0 small">
                            <i class="fa-solid fa-lock me-1"></i><strong>Account Lock Reason:</strong> {{ $employee->lock_reason }}
                        </div>
                    </div>
                    @endif
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
                <h5 class="mb-0"><i class="fa-solid fa-id-card text-primary me-2"></i>Contact &amp; Identity Information</h5>
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
                        <small class="text-muted d-block mb-1">TIN Number</small>
                        <h6 class="mb-0 font-monospace">
                            @if($employee->tin_number)
                                {{ $employee->tin_number }}
                            @else
                                <span class="text-danger small fst-italic"><i class="fa-solid fa-xmark me-1"></i>Not Provided</span>
                            @endif
                        </h6>
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
                            <h6 class="fw-bold mb-1 small">Registration / Contract Docs</h6>
                            @php $regDocUrls = $employee->registration_letter_urls; @endphp
                            @if(!empty($regDocUrls))
                                <div class="d-flex flex-wrap justify-content-center gap-1 mt-2">
                                    @foreach($regDocUrls as $idx => $docUrl)
                                        <a href="{{ $docUrl }}" target="_blank" class="btn btn-xs btn-primary">
                                            <i class="fa-solid fa-eye me-1"></i>Doc #{{ $idx + 1 }}
                                        </a>
                                    @endforeach
                                </div>
                            @elseif($employee->registration_letter)
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
                            <strong>45-DAY TEST PERIOD EXPIRED &amp; ACCOUNT LOCKED!</strong> The 45-day test period has elapsed without renewal or Guarantee Letter submission.
                            <br><small>Login access is locked. Submit document or renew below to restore access.</small>
                        </div>
                        <p class="text-muted mb-3">
                            <i class="fa-solid fa-calendar me-2"></i>
                            Joined: {{ optional($employee->date_of_joining)->format('d M Y') ?? 'N/A' }}
                            <br>
                            <i class="fa-solid fa-clock me-2"></i>
                            45-Day Deadline was: {{ $employee->probation_end_date ? $employee->probation_end_date->format('d M Y') : 'N/A' }}
                        </p>
                        <form action="{{ route('employees.upload-guarantee', $employee) }}" method="POST" enctype="multipart/form-data">
                            @csrf
                            <div class="mb-3">
                                <label class="form-label">Upload Guarantee Letter <span class="text-danger">*</span></label>
                                <input type="file" name="guarantee_letter" class="form-control" required accept="application/pdf,image/jpeg,image/png,image/jpg">
                                <small class="text-muted">PDF or Image (Max 10MB)</small>
                            </div>
                            <button type="submit" class="btn btn-danger">
                                <i class="fa-solid fa-upload me-2"></i>Submit Document to Restore Access
                            </button>
                        </form>
                    @elseif($employee->show_guarantee_warning)
                        <div class="alert alert-warning mb-3">
                            <i class="fa-solid fa-exclamation-triangle me-2"></i>
                            <strong>Test Period Notice (Day {{ $employee->days_since_joining }} of 45):</strong> Guarantee letter must be submitted within {{ $employee->days_until_probation_end }} days.
                            <br><small>Account will be locked after {{ $employee->probation_end_date ? $employee->probation_end_date->format('d M Y') : 'N/A' }} if not submitted or renewed.</small>
                        </div>
                        <p class="text-muted mb-3">
                            <i class="fa-solid fa-calendar me-2"></i>
                            Joined: {{ optional($employee->date_of_joining)->format('d M Y') ?? 'N/A' }}
                            <br>
                            <i class="fa-solid fa-clock me-2"></i>
                            45-Day Deadline: {{ $employee->probation_end_date ? $employee->probation_end_date->format('d M Y') : 'N/A' }}
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
                            <strong>45-Day Test Period Active (Day {{ $employee->days_since_joining }} of 45):</strong> Guarantee letter is waived during this test period.
                            <br><small>Due date: {{ $employee->probation_end_date ? $employee->probation_end_date->format('d M Y') : 'N/A' }} ({{ $employee->days_until_probation_end }} days remaining)</small>
                        </div>
                        <p class="text-muted mb-3">
                            <i class="fa-solid fa-calendar me-2"></i>
                            Joined: {{ optional($employee->date_of_joining)->format('d M Y') ?? 'N/A' }}
                            <br>
                            <i class="fa-solid fa-clock me-2"></i>
                            Test Period Ends: {{ $employee->probation_end_date ? $employee->probation_end_date->format('d M Y') : 'N/A' }}
                        </p>
                        <form action="{{ route('employees.upload-guarantee', $employee) }}" method="POST" enctype="multipart/form-data">
                            @csrf
                            <div class="mb-3">
                                <label class="form-label">Upload Guarantee Letter (Optional during test period — can submit anytime)</label>
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

        {{-- ── Card 2: Guarantor Person Information (Guarantor 1 & Guarantor 2) ─────────────────────────── --}}
        @php
            $hasGuarantor2 = !empty($employee->guarantor_2_name) || !empty($employee->guarantor_2_id_number) || !empty($employee->guarantor_2_phone) || !empty($employee->guarantor_2_id_card) || !empty($employee->guarantee_letter_2);
        @endphp
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-light d-flex justify-content-between align-items-center">
                <h5 class="mb-0">
                    <i class="fa-solid fa-user-shield me-2 text-primary"></i>Guarantor Person Information
                </h5>
                <div class="d-flex align-items-center gap-2">
                    @if($hasGuarantor2)
                        <span class="badge bg-primary-subtle text-primary border border-primary-subtle">
                            <i class="fa-solid fa-users me-1"></i>2 Guarantors Recorded
                        </span>
                    @else
                        <span class="badge bg-success-subtle text-success border border-success-subtle">
                            <i class="fa-solid fa-user-shield me-1"></i>Primary Guarantor
                        </span>
                    @endif
                    <a href="{{ route('employees.edit', $employee) }}" class="btn btn-xs btn-outline-secondary">
                        <i class="fa-solid fa-pen-to-square me-1"></i>Edit Guarantor Info
                    </a>
                </div>
            </div>
            <div class="card-body">
                <div class="row g-4">
                    {{-- ── Guarantor 1 Column ─────────────────────────── --}}
                    <div class="{{ $hasGuarantor2 ? 'col-lg-6' : 'col-12' }}">
                        <div class="p-3 border rounded-3 bg-white shadow-xs h-100 position-relative">
                            <div class="d-flex align-items-center justify-content-between mb-3 pb-2 border-bottom">
                                <h6 class="fw-bold mb-0 text-dark">
                                    <span class="badge bg-primary me-2">#1</span>Primary Guarantor
                                </h6>
                                @if($employee->guarantor_name || $employee->guarantor_id_number || $employee->guarantor_id_card)
                                    <span class="badge bg-success-subtle text-success border border-success-subtle"><i class="fa-solid fa-check me-1"></i>Recorded</span>
                                @else
                                    <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle">Not Provided</span>
                                @endif
                            </div>

                            <div class="row g-2 mb-3">
                                <div class="{{ $hasGuarantor2 ? 'col-12' : 'col-md-4 col-12' }}">
                                    <small class="text-muted d-block fw-semibold" style="font-size:0.72rem; letter-spacing:0.04em;">FULL NAME</small>
                                    <div class="fw-bold text-dark fs-6">{{ $employee->guarantor_name ?: '—' }}</div>
                                </div>
                                <div class="{{ $hasGuarantor2 ? 'col-sm-6' : 'col-md-4 col-sm-6' }}">
                                    <small class="text-muted d-block fw-semibold" style="font-size:0.72rem; letter-spacing:0.04em;">NATIONAL / KEBELE ID</small>
                                    <div class="fw-bold text-dark font-monospace">{{ $employee->guarantor_id_number ?: '—' }}</div>
                                </div>
                                <div class="{{ $hasGuarantor2 ? 'col-sm-6' : 'col-md-4 col-sm-6' }}">
                                    <small class="text-muted d-block fw-semibold" style="font-size:0.72rem; letter-spacing:0.04em;">PHONE NUMBER</small>
                                    <div class="fw-bold text-dark">{{ $employee->guarantor_phone ?: '—' }}</div>
                                </div>
                            </div>

                            {{-- Attached Documents Grid for Guarantor 1 --}}
                            <div class="p-2 rounded-3 bg-light border">
                                <small class="text-muted fw-bold d-block mb-2" style="font-size:0.73rem; text-transform:uppercase; letter-spacing:0.05em;">
                                    <i class="fa-solid fa-paperclip me-1 text-primary"></i>Guarantor #1 Documents
                                </small>
                                <div class="d-flex flex-column gap-2">
                                    {{-- Guarantor 1 ID Card --}}
                                    <div class="d-flex justify-content-between align-items-center p-2 rounded bg-white border">
                                        <div class="d-flex align-items-center gap-2">
                                            <div class="p-1 rounded bg-primary bg-opacity-10 text-primary">
                                                <i class="fa-solid fa-id-card fa-sm"></i>
                                            </div>
                                            <div>
                                                <span class="d-block fw-bold text-dark small">National / Kebele ID Card</span>
                                                @if($employee->guarantor_id_card)
                                                    <span class="badge bg-success-subtle text-success border border-success-subtle py-0" style="font-size:0.68rem;">Uploaded &amp; Available</span>
                                                @else
                                                    <span class="badge bg-danger-subtle text-danger border border-danger-subtle py-0" style="font-size:0.68rem;">ID Not Uploaded</span>
                                                @endif
                                            </div>
                                        </div>
                                        <div class="d-flex gap-1">
                                            @if($employee->guarantor_id_card)
                                                <a href="{{ $employee->guarantor_id_card_url }}" target="_blank" class="btn btn-xs btn-primary shadow-xs">
                                                    <i class="fa-solid fa-eye me-1"></i>View ID
                                                </a>
                                                <button type="button" class="btn btn-xs btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#uploadGuarantorIdModal1">
                                                    <i class="fa-solid fa-arrows-rotate"></i>
                                                </button>
                                            @else
                                                <button type="button" class="btn btn-xs btn-outline-primary fw-bold" data-bs-toggle="modal" data-bs-target="#uploadGuarantorIdModal1">
                                                    <i class="fa-solid fa-cloud-arrow-up me-1"></i>Upload ID
                                                </button>
                                            @endif
                                        </div>
                                    </div>

                                    {{-- Guarantee Letter 1 --}}
                                    <div class="d-flex justify-content-between align-items-center p-2 rounded bg-white border">
                                        <div class="d-flex align-items-center gap-2">
                                            <div class="p-1 rounded bg-warning bg-opacity-10 text-warning">
                                                <i class="fa-solid fa-file-shield fa-sm"></i>
                                            </div>
                                            <div>
                                                <span class="d-block fw-bold text-dark small">Guarantee Letter #1</span>
                                                @if($employee->guarantee_letter)
                                                    <span class="badge bg-success-subtle text-success border border-success-subtle py-0" style="font-size:0.68rem;">Uploaded &amp; On File</span>
                                                @else
                                                    <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle py-0" style="font-size:0.68rem;">Letter Not Uploaded</span>
                                                @endif
                                            </div>
                                        </div>
                                        <div class="d-flex gap-1">
                                            @if($employee->guarantee_letter)
                                                <a href="{{ $employee->guarantee_letter_url }}" target="_blank" class="btn btn-xs btn-warning text-dark fw-bold shadow-xs">
                                                    <i class="fa-solid fa-file-shield me-1"></i>View Letter
                                                </a>
                                                <button type="button" class="btn btn-xs btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#uploadGuaranteeLetterModal1">
                                                    <i class="fa-solid fa-arrows-rotate"></i>
                                                </button>
                                            @else
                                                <button type="button" class="btn btn-xs btn-outline-warning text-dark fw-bold" data-bs-toggle="modal" data-bs-target="#uploadGuaranteeLetterModal1">
                                                    <i class="fa-solid fa-cloud-arrow-up me-1"></i>Upload Letter
                                                </button>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    @if($hasGuarantor2)
                    {{-- ── Guarantor 2 Column (Only shown if second guarantor added) ─────────────────────────── --}}
                    <div class="col-lg-6">
                        <div class="p-3 border rounded-3 bg-white shadow-xs h-100 position-relative">
                            <div class="d-flex align-items-center justify-content-between mb-3 pb-2 border-bottom">
                                <h6 class="fw-bold mb-0 text-dark">
                                    <span class="badge bg-secondary me-2">#2</span>Second Guarantor
                                </h6>
                                @if($employee->guarantor_2_name || $employee->guarantor_2_id_number || $employee->guarantor_2_id_card)
                                    <span class="badge bg-success-subtle text-success border border-success-subtle"><i class="fa-solid fa-check me-1"></i>Recorded</span>
                                @else
                                    <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle">Optional / Not Provided</span>
                                @endif
                            </div>

                            <div class="row g-2 mb-3">
                                <div class="col-12">
                                    <small class="text-muted d-block fw-semibold" style="font-size:0.72rem; letter-spacing:0.04em;">FULL NAME</small>
                                    <div class="fw-bold text-dark fs-6">{{ $employee->guarantor_2_name ?: '—' }}</div>
                                </div>
                                <div class="col-sm-6">
                                    <small class="text-muted d-block fw-semibold" style="font-size:0.72rem; letter-spacing:0.04em;">NATIONAL / KEBELE ID</small>
                                    <div class="fw-bold text-dark font-monospace">{{ $employee->guarantor_2_id_number ?: '—' }}</div>
                                </div>
                                <div class="col-sm-6">
                                    <small class="text-muted d-block fw-semibold" style="font-size:0.72rem; letter-spacing:0.04em;">PHONE NUMBER</small>
                                    <div class="fw-bold text-dark">{{ $employee->guarantor_2_phone ?: '—' }}</div>
                                </div>
                            </div>

                            {{-- Attached Documents Grid for Guarantor 2 --}}
                            <div class="p-2 rounded-3 bg-light border">
                                <small class="text-muted fw-bold d-block mb-2" style="font-size:0.73rem; text-transform:uppercase; letter-spacing:0.05em;">
                                    <i class="fa-solid fa-paperclip me-1 text-secondary"></i>Guarantor #2 Documents
                                </small>
                                <div class="d-flex flex-column gap-2">
                                    {{-- Guarantor 2 ID Card --}}
                                    <div class="d-flex justify-content-between align-items-center p-2 rounded bg-white border">
                                        <div class="d-flex align-items-center gap-2">
                                            <div class="p-1 rounded bg-primary bg-opacity-10 text-primary">
                                                <i class="fa-solid fa-id-card fa-sm"></i>
                                            </div>
                                            <div>
                                                <span class="d-block fw-bold text-dark small">National / Kebele ID Card</span>
                                                @if($employee->guarantor_2_id_card)
                                                    <span class="badge bg-success-subtle text-success border border-success-subtle py-0" style="font-size:0.68rem;">Uploaded &amp; Available</span>
                                                @else
                                                    <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle py-0" style="font-size:0.68rem;">Not Uploaded</span>
                                                @endif
                                            </div>
                                        </div>
                                        <div class="d-flex gap-1">
                                            @if($employee->guarantor_2_id_card)
                                                <a href="{{ $employee->guarantor_2_id_card_url }}" target="_blank" class="btn btn-xs btn-primary shadow-xs">
                                                    <i class="fa-solid fa-eye me-1"></i>View ID
                                                </a>
                                                <button type="button" class="btn btn-xs btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#uploadGuarantorIdModal2">
                                                    <i class="fa-solid fa-arrows-rotate"></i>
                                                </button>
                                            @else
                                                <button type="button" class="btn btn-xs btn-outline-primary fw-bold" data-bs-toggle="modal" data-bs-target="#uploadGuarantorIdModal2">
                                                    <i class="fa-solid fa-cloud-arrow-up me-1"></i>Upload ID
                                                </button>
                                            @endif
                                        </div>
                                    </div>

                                    {{-- Guarantee Letter 2 --}}
                                    <div class="d-flex justify-content-between align-items-center p-2 rounded bg-white border">
                                        <div class="d-flex align-items-center gap-2">
                                            <div class="p-1 rounded bg-warning bg-opacity-10 text-warning">
                                                <i class="fa-solid fa-file-shield fa-sm"></i>
                                            </div>
                                            <div>
                                                <span class="d-block fw-bold text-dark small">Guarantee Letter #2</span>
                                                @if($employee->guarantee_letter_2)
                                                    <span class="badge bg-success-subtle text-success border border-success-subtle py-0" style="font-size:0.68rem;">Uploaded &amp; On File</span>
                                                @else
                                                    <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle py-0" style="font-size:0.68rem;">Not Uploaded</span>
                                                @endif
                                            </div>
                                        </div>
                                        <div class="d-flex gap-1">
                                            @if($employee->guarantee_letter_2)
                                                <a href="{{ $employee->guarantee_letter_2_url }}" target="_blank" class="btn btn-xs btn-warning text-dark fw-bold shadow-xs">
                                                    <i class="fa-solid fa-file-shield me-1"></i>View Letter
                                                </a>
                                                <button type="button" class="btn btn-xs btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#uploadGuaranteeLetterModal2">
                                                    <i class="fa-solid fa-arrows-rotate"></i>
                                                </button>
                                            @else
                                                <button type="button" class="btn btn-xs btn-outline-warning text-dark fw-bold" data-bs-toggle="modal" data-bs-target="#uploadGuaranteeLetterModal2">
                                                    <i class="fa-solid fa-cloud-arrow-up me-1"></i>Upload Letter
                                                </button>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    @endif
                </div>

                @if(!$employee->guarantor_name && !$employee->guarantor_2_name && !$employee->guarantee_letter && !$employee->guarantee_letter_2)
                    <div class="alert alert-secondary mt-3 mb-0">
                        <i class="fa-solid fa-circle-info me-2"></i>
                        No guarantor person information has been recorded for this employee.
                        <a href="{{ route('employees.edit', $employee) }}" class="alert-link ms-1">Add Guarantor Information →</a>
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
        {{-- =============================
        13. OFFICIAL LETTERS & WARNINGS (CARD GRID VIEW)
        ============================= --}}
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div>
                    <h5 class="mb-0 fw-bold text-dark"><i class="fa-solid fa-envelope-open-text text-primary me-2"></i>Official Letters &amp; Letter History</h5>
                    <small class="text-muted">Guarantee letters, appreciation, written warnings, power of attorney, and official employee records</small>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <a href="{{ \Illuminate\Support\Facades\Route::has('employee-letters.create') ? route('employee-letters.create', ['employee_id' => $employee->id]) : url('/employee-letters/create?employee_id='.$employee->id) }}" class="btn btn-sm btn-primary shadow-sm">
                        <i class="fa-solid fa-plus me-1"></i> Issue Official Letter
                    </a>
                </div>
            </div>
            <div class="card-body p-3">
                @php
                    $empLetters = $employee->letters ?? collect();
                @endphp
                @if($empLetters->count() > 0)
                <div class="row g-3">
                    @foreach($empLetters as $ltr)
                    @php
                        $borderAccent = match($ltr->letter_type) {
                            'guarantee_letter'   => '#0d9488',
                            'power_of_attorney'  => '#4f46e5',
                            'application_letter' => '#6366f1',
                            'thanks_letter', 'appreciation', 'promotion' => '#10b981',
                            'first_warning'      => '#f59e0b',
                            'second_warning'     => '#f97316',
                            'final_warning', 'termination' => '#ef4444',
                            'show_cause'         => '#06b6d4',
                            'suspension'         => '#8b5cf6',
                            default              => '#64748b'
                        };
                    @endphp
                    <div class="col-md-6 col-12">
                        <div class="card border h-100 shadow-sm rounded-3 overflow-hidden position-relative" style="border-left: 5px solid {{ $borderAccent }} !important; background: #ffffff;">
                            <div class="card-body p-3 d-flex flex-column justify-content-between">
                                <div>
                                    {{-- Header: Type Badge & Reference Number --}}
                                    <div class="d-flex justify-content-between align-items-start mb-2 gap-2 flex-wrap">
                                        <span class="badge {{ $ltr->badge_class }} py-1 px-2.5 rounded-pill shadow-xs" style="font-size: 0.78rem;">
                                            <i class="{{ $ltr->icon }} me-1"></i>{{ $ltr->type_label }}
                                        </span>
                                        <span class="badge bg-light text-dark font-monospace border px-2 py-1" style="font-size: 0.75rem;">
                                            {{ $ltr->reference_number ?: 'LTR-#'.$ltr->id }}
                                        </span>
                                    </div>

                                    {{-- Subject / Title --}}
                                    <h6 class="fw-bold text-dark mb-1" style="line-height: 1.35;">
                                        {{ $ltr->title }}
                                    </h6>

                                    {{-- Content Snippet --}}
                                    <div class="p-2 rounded-2 bg-light border border-light-subtle my-2" style="font-size: 0.82rem; color: #475569; max-height: 72px; overflow: hidden; line-height: 1.45;">
                                        {{ Str::limit(strip_tags($ltr->content), 120) }}
                                    </div>
                                </div>

                                <div>
                                    {{-- Meta row: Issued Date & Acknowledgement --}}
                                    <div class="d-flex justify-content-between align-items-center pt-2 mb-3 border-top" style="font-size: 0.78rem;">
                                        <span class="text-muted">
                                            <i class="fa-regular fa-calendar me-1 text-primary"></i><strong>{{ optional($ltr->issued_date)->format('d M Y') }}</strong>
                                        </span>
                                        <div>
                                            @if($ltr->acknowledgement_status === 'acknowledged')
                                                <span class="badge bg-success bg-opacity-10 text-success border border-success-subtle px-2 py-0.5">
                                                    <i class="fa-solid fa-check me-1"></i>Signed / Acknowledged
                                                </span>
                                            @elseif($ltr->acknowledgement_status === 'pending')
                                                <span class="badge bg-warning bg-opacity-10 text-dark border border-warning-subtle px-2 py-0.5">
                                                    <i class="fa-solid fa-clock me-1"></i>Pending Signature
                                                </span>
                                            @else
                                                <span class="badge bg-danger bg-opacity-10 text-danger border border-danger-subtle px-2 py-0.5">
                                                    <i class="fa-solid fa-ban me-1"></i>Refused
                                                </span>
                                            @endif
                                        </div>
                                    </div>

                                    {{-- Action Buttons --}}
                                    <div class="d-flex gap-2">
                                        <button type="button" class="btn btn-sm btn-outline-primary flex-grow-1 fw-semibold py-1.5 shadow-xs" data-bs-toggle="modal" data-bs-target="#empLetterModal{{ $ltr->id }}">
                                            <i class="fa-solid fa-eye me-1"></i> View Letter Details
                                        </button>
                                        <a href="{{ \Illuminate\Support\Facades\Route::has('employee-letters.print') ? route('employee-letters.print', $ltr) : url('/employee-letters/'.$ltr->id.'/print') }}" target="_blank" class="btn btn-sm btn-light border py-1.5 px-2.5" title="Print Letterhead">
                                            <i class="fa-solid fa-print text-secondary"></i>
                                        </a>
                                        <a href="{{ \Illuminate\Support\Facades\Route::has('employee-letters.edit') ? route('employee-letters.edit', $ltr) : url('/employee-letters/'.$ltr->id.'/edit') }}" class="btn btn-sm btn-light border py-1.5 px-2.5" title="Edit Letter">
                                            <i class="fa-solid fa-pen-to-square text-warning"></i>
                                        </a>
                                        @if($ltr->attachment_path)
                                        <a href="{{ asset('storage/' . $ltr->attachment_path) }}" target="_blank" class="btn btn-sm btn-light border py-1.5 px-2.5" title="View Signed Attachment">
                                            <i class="fa-solid fa-paperclip text-success"></i>
                                        </a>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- DETAIL MODAL FOR THIS LETTER --}}
                    <div class="modal fade" id="empLetterModal{{ $ltr->id }}" tabindex="-1" aria-labelledby="empLetterModalLabel{{ $ltr->id }}" aria-hidden="true">
                        <div class="modal-dialog modal-dialog-centered modal-lg">
                            <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
                                {{-- Modal Header --}}
                                <div class="modal-header text-white py-3 px-4" style="background: {{ $borderAccent }};">
                                    <div class="d-flex align-items-center gap-2">
                                        <span class="badge bg-white bg-opacity-20 text-white p-2 rounded-3 fs-6">
                                            <i class="{{ $ltr->icon }}"></i>
                                        </span>
                                        <div>
                                            <h5 class="modal-title fw-bold mb-0 text-white" id="empLetterModalLabel{{ $ltr->id }}">
                                                {{ $ltr->type_label }}
                                            </h5>
                                            <span class="text-white-50 small font-monospace">Ref: {{ $ltr->reference_number ?: 'LTR-#'.$ltr->id }} &bull; Issued: {{ optional($ltr->issued_date)->format('d M Y') }}</span>
                                        </div>
                                    </div>
                                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>

                                {{-- Modal Body: Form / Letterhead Preview --}}
                                <div class="modal-body p-4 bg-white">
                                    {{-- Mini Letterhead Header --}}
                                    <div class="text-center border-bottom pb-3 mb-3">
                                        <h5 class="fw-bold text-dark text-uppercase mb-0" style="letter-spacing: 1px;">Wechecha Construction PLC</h5>
                                        <div class="text-muted small text-uppercase">Human Resources &amp; Personnel Administration</div>
                                    </div>

                                    {{-- Reference & Date Bar --}}
                                    <div class="d-flex justify-content-between align-items-center p-2 rounded bg-light border mb-3 small">
                                        <div><strong>Reference:</strong> <span class="font-monospace text-primary">{{ $ltr->reference_number ?: 'LTR-#'.$ltr->id }}</span></div>
                                        <div><strong>Date:</strong> {{ optional($ltr->issued_date)->format('d F Y') }}</div>
                                    </div>

                                    {{-- Employee Info Summary --}}
                                    <div class="row g-2 p-3 bg-light rounded-3 border mb-3 small">
                                        <div class="col-md-6">
                                            <span class="text-muted d-block">Employee Name:</span>
                                            <strong class="text-dark">{{ $employee->full_name }}</strong> ({{ $employee->employee_code }})
                                        </div>
                                        <div class="col-md-6">
                                            <span class="text-muted d-block">Department &amp; Role:</span>
                                            <strong>{{ $employee->department }} &bull; {{ $employee->role_title ?? 'Employee' }}</strong>
                                        </div>
                                    </div>

                                    {{-- Subject --}}
                                    <div class="mb-3">
                                        <div class="small fw-bold text-uppercase text-muted">Subject:</div>
                                        <h6 class="fw-bold text-dark border-bottom pb-1 mb-0">{{ $ltr->title }}</h6>
                                    </div>

                                    {{-- Full Letter Content --}}
                                    <div class="mb-3">
                                        <div class="small fw-bold text-uppercase text-muted mb-1">Letter Body / Content:</div>
                                        <div class="p-3 bg-light rounded-3 border font-monospace text-dark" style="white-space: pre-wrap; font-size: 0.88rem; line-height: 1.6; max-height: 320px; overflow-y: auto;">{{ $ltr->content }}</div>
                                    </div>

                                    {{-- Action Required / Notes --}}
                                    @if($ltr->action_required)
                                    <div class="p-3 rounded-3 border-start border-4 border-warning bg-warning bg-opacity-10 mb-3 small">
                                        <strong class="text-dark d-block mb-1"><i class="fa-solid fa-triangle-exclamation text-warning me-1"></i>Follow-up / Action Required:</strong>
                                        <div>{{ $ltr->action_required }}</div>
                                    </div>
                                    @endif

                                    {{-- Attachment Info --}}
                                    @if($ltr->attachment_path)
                                    <div class="p-2.5 rounded bg-success bg-opacity-10 border border-success-subtle d-flex align-items-center justify-content-between">
                                        <div class="small">
                                            <i class="fa-solid fa-file-pdf text-success me-1"></i> <strong>Signed Copy Attached:</strong> {{ basename($ltr->attachment_path) }}
                                        </div>
                                        <a href="{{ asset('storage/' . $ltr->attachment_path) }}" target="_blank" class="btn btn-sm btn-success py-1 px-3">
                                            <i class="fa-solid fa-download me-1"></i> View / Download Attachment
                                        </a>
                                    </div>
                                    @endif
                                </div>

                                {{-- Modal Footer --}}
                                <div class="modal-footer bg-light border-0 py-3 px-4 justify-content-between">
                                    <button type="button" class="btn btn-light border rounded-pill px-3" data-bs-dismiss="modal">Close</button>
                                    <div class="d-flex gap-2">
                                        <a href="{{ \Illuminate\Support\Facades\Route::has('employee-letters.edit') ? route('employee-letters.edit', $ltr) : url('/employee-letters/'.$ltr->id.'/edit') }}" class="btn btn-outline-warning rounded-pill px-3 fw-semibold">
                                            <i class="fa-solid fa-pen-to-square me-1"></i> Edit Letter
                                        </a>
                                        <a href="{{ \Illuminate\Support\Facades\Route::has('employee-letters.print') ? route('employee-letters.print', $ltr) : url('/employee-letters/'.$ltr->id.'/print') }}" target="_blank" class="btn btn-primary rounded-pill px-4 fw-bold shadow-sm">
                                            <i class="fa-solid fa-print me-1"></i> Print Letterhead
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    @endforeach
                </div>
                @else
                <div class="text-center py-4 text-muted">
                    <i class="fa-solid fa-envelope-open-text fa-2x mb-2 d-block opacity-25"></i>
                    <p class="small mb-2">No official letters or warning notices issued to this employee yet.</p>
                    <a href="{{ \Illuminate\Support\Facades\Route::has('employee-letters.create') ? route('employee-letters.create', ['employee_id' => $employee->id]) : url('/employee-letters/create?employee_id='.$employee->id) }}" class="btn btn-sm btn-outline-primary">
                        <i class="fa-solid fa-plus me-1"></i> Issue Thanks / Guarantee / Warning Letter
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
                    <img src="{{ $employee->profile_picture_url }}" alt="{{ $employee->full_name }}"
                         class="rounded-circle border border-4 border-white shadow"
                         style="width:90px;height:90px;object-fit:cover;">
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
        @php
            $currentYear = (int) date('Y');
            $joinDate = $employee->date_of_joining ? \Carbon\Carbon::parse($employee->date_of_joining) : null;
            
            // Calculate months active in current year for monthly accrual calculation (16 days / 12 months = 1.333 days/mo)
            if ($joinDate && $joinDate->year < $currentYear) {
                $monthsActive = (int) date('n');
            } elseif ($joinDate && $joinDate->year == $currentYear) {
                $monthsActive = max(1, (int) date('n') - (int) $joinDate->month + 1);
            } else {
                $monthsActive = (int) date('n');
            }
            $accruedDays = min(16.0, round($monthsActive * (16.0 / 12.0), 2));

            $leaveBalance = $employee->leaveBalances()->where('year', $currentYear)->first();
        @endphp

        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center">
                <div>
                    <h6 class="mb-0 fw-bold text-dark"><i class="fa-solid fa-umbrella-beach text-info me-2"></i>Leave Balance {{ $currentYear }}</h6>
                    <small class="text-muted" style="font-size:0.75rem;">16 Days/Year (1.33 Days/Month)</small>
                </div>
                @if($leaveBalance)
                <button type="button" class="btn btn-xs btn-outline-danger" data-bs-toggle="modal" data-bs-target="#deductLeaveModal" title="Deduct Leave Days">
                    <i class="fa-solid fa-minus me-1"></i>Deduct Leave
                </button>
                @endif
            </div>
            <div class="card-body p-3">
                @if($leaveBalance)
                @php
                    $totalDays = (float) $leaveBalance->total_days;
                    $usedDays  = (float) $leaveBalance->used_days;
                    $remaining = (float) $leaveBalance->remaining_days;
                    $usedPct   = $totalDays > 0 ? min(100, round(($usedDays / $totalDays) * 100)) : 0;
                @endphp
                {{-- Active Balance Display --}}
                <div class="bg-light rounded-3 p-3 text-center mb-3 border">
                    <span class="text-muted small fw-semibold text-uppercase d-block" style="letter-spacing:0.05em; font-size:0.72rem;">Available Balance</span>
                    <h2 class="fw-bold text-primary mb-0 font-monospace">{{ number_format($remaining, 2) }} <span class="fs-6 text-muted fw-normal">Days</span></h2>
                    <div class="badge bg-info bg-opacity-10 text-info mt-1 font-monospace" style="font-size:0.75rem;">
                        Accrued to Date: {{ number_format($accruedDays, 2) }} Days ({{ $monthsActive }} Mos)
                    </div>
                </div>

                {{-- Progress Bar --}}
                <div class="mb-3">
                    <div class="d-flex justify-content-between align-items-center mb-1 small">
                        <span class="text-muted fw-semibold"><i class="fa-solid fa-plane-departure text-info me-1"></i>Annual Leave</span>
                        <span class="fw-bold text-dark">{{ number_format($usedDays, 1) }} used / {{ number_format($totalDays, 1) }} total</span>
                    </div>
                    <div class="progress rounded-pill" style="height:10px;">
                        <div class="progress-bar {{ $usedPct > 80 ? 'bg-danger' : ($usedPct > 50 ? 'bg-warning' : 'bg-info') }}" style="width:{{ $usedPct }}%;"></div>
                    </div>
                    <div class="d-flex justify-content-between mt-1" style="font-size:0.75rem;">
                        <span class="text-success fw-semibold">{{ number_format($remaining, 1) }} days remaining</span>
                        <span class="text-muted">{{ $usedPct }}% taken</span>
                    </div>
                </div>

                {{-- Calculation Metadata --}}
                <div class="p-2 bg-light rounded-2 small text-muted" style="font-size:0.75rem;">
                    <div class="d-flex justify-content-between py-1 border-bottom border-light">
                        <span>Standard Quota:</span>
                        <strong class="text-dark">16.0 Days / Year</strong>
                    </div>
                    <div class="d-flex justify-content-between py-1 border-bottom border-light">
                        <span>Monthly Accrual:</span>
                        <strong class="text-dark">1.33 Days / Month</strong>
                    </div>
                    <div class="d-flex justify-content-between py-1">
                        <span>Service Since:</span>
                        <strong class="text-dark">{{ optional($joinDate)->format('d M Y') ?? 'N/A' }}</strong>
                    </div>
                </div>

                <div class="d-flex gap-2 mt-3">
                    <button type="button" class="btn btn-sm btn-outline-primary w-100" data-bs-toggle="modal" data-bs-target="#deductLeaveModal">
                        <i class="fa-solid fa-minus me-1"></i> Record Leave Taken
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#initLeaveModal" title="Adjust / Re-allocate Quota">
                        <i class="fa-solid fa-sliders"></i>
                    </button>
                </div>

                @else
                {{-- One-Time Initialization Card --}}
                <div class="text-center py-2">
                    <div class="p-3 bg-light rounded-3 mb-3 border border-dashed">
                        <i class="fa-solid fa-umbrella-beach fa-2x text-info mb-2 d-block opacity-75"></i>
                        <h6 class="fw-bold text-dark mb-1">Annual Leave (16 Days / Year)</h6>
                        <p class="text-muted small mb-2" style="font-size:0.8rem;">
                            Standard statutory annual leave is <strong>16 days per year</strong> (accruing at <strong>1.33 days per month</strong>).
                        </p>
                        <div class="bg-white rounded-2 p-2 border small font-monospace text-start mb-2">
                            <div class="d-flex justify-content-between">
                                <span class="text-muted">Monthly Rate:</span>
                                <strong>1.33 Days/Mo</strong>
                            </div>
                            <div class="d-flex justify-content-between">
                                <span class="text-muted">Active in {{ $currentYear }}:</span>
                                <strong>{{ $monthsActive }} Months</strong>
                            </div>
                            <div class="d-flex justify-content-between border-top pt-1 text-primary">
                                <span>Earned to Date:</span>
                                <strong>{{ number_format($accruedDays, 2) }} Days</strong>
                            </div>
                        </div>
                    </div>

                    <form action="{{ \Illuminate\Support\Facades\Route::has('employees.initialize-leave-balance') ? route('employees.initialize-leave-balance', $employee) : url('/employees/'.$employee->id.'/initialize-leave-balance') }}" method="POST">
                        @csrf
                        <input type="hidden" name="year" value="{{ $currentYear }}">
                        <input type="hidden" name="total_days" value="16.0">
                        <button type="submit" class="btn btn-primary btn-sm w-100 py-2 fw-bold shadow-sm" onclick="return confirm('Initialize 16 Days annual leave balance for {{ addslashes($employee->full_name) }} for {{ $currentYear }}?')">
                            <i class="fa-solid fa-plus-circle me-1"></i> Allocate 16 Days Leave (One-Time)
                        </button>
                    </form>
                    <button type="button" class="btn btn-link btn-sm text-decoration-none mt-1" data-bs-toggle="modal" data-bs-target="#initLeaveModal" style="font-size:0.75rem;">
                        <i class="fa-solid fa-gear me-1"></i>Custom Quota / Carried-Over
                    </button>
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
            <div class="card-header bg-white py-3 px-4 border-bottom d-flex flex-wrap align-items-center justify-content-between gap-2">
                <div class="d-flex align-items-center gap-2">
                    <h5 class="mb-0 fw-bold text-dark"><i class="fa-solid fa-clock text-primary me-2"></i>Attendance History</h5>
                    <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 px-2 py-1 small">
                        <i class="fa-solid fa-fingerprint me-1"></i>Biometric ADMS
                    </span>
                </div>
                <div class="d-flex align-items-center gap-2 flex-wrap">
                    @if($employee->device_user_id)
                        <span class="badge bg-success py-2 px-3 fw-semibold shadow-xs" style="font-size:0.82rem;">
                            <i class="fa-solid fa-link me-1"></i>Device User ID: <strong>{{ $employee->device_user_id }}</strong>
                        </span>
                        <form action="{{ \Illuminate\Support\Facades\Route::has('employees.sync-device-attendance') ? route('employees.sync-device-attendance', $employee) : url('/employees/'.$employee->id.'/sync-device-attendance') }}" method="POST" class="d-inline mb-0">
                            @csrf
                            <button type="submit" class="btn btn-sm btn-outline-success fw-bold" title="Scan and sync attendance punches from ZKTeco MB460 device for this employee">
                                <i class="fa-solid fa-rotate me-1"></i>Sync Punches Now
                            </button>
                        </form>
                        <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#linkDeviceIdModal">
                            <i class="fa-solid fa-pen-to-square me-1"></i>Change ID
                        </button>
                    @else
                        <span class="badge bg-warning text-dark py-2 px-3 fw-semibold shadow-xs" style="font-size:0.82rem;">
                            <i class="fa-solid fa-triangle-exclamation me-1"></i>No Device ID Linked
                        </span>
                        <button type="button" class="btn btn-sm btn-primary fw-bold shadow-sm" data-bs-toggle="modal" data-bs-target="#linkDeviceIdModal">
                            <i class="fa-solid fa-link me-1"></i>Link Device User ID
                        </button>
                    @endif
                </div>
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

{{-- ===================== LEAVE MANAGEMENT MODALS (ROOT LEVEL) ===================== --}}

{{-- Modal: Initialize / Custom Quota --}}
<div class="modal fade" id="initLeaveModal" tabindex="-1" aria-labelledby="initLeaveModalLabel" aria-hidden="true" style="z-index: 1060;">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 12px; overflow: hidden;">
            <form action="{{ \Illuminate\Support\Facades\Route::has('employees.initialize-leave-balance') ? route('employees.initialize-leave-balance', $employee) : url('/employees/'.$employee->id.'/initialize-leave-balance') }}" method="POST">
                @csrf
                <div class="modal-header py-3 px-4" style="background: #0f172a !important; color: #ffffff !important;">
                    <h5 class="modal-title fs-6 fw-bold text-white mb-0" id="initLeaveModalLabel">
                        <i class="fa-solid fa-umbrella-beach text-info me-2"></i>Allocate Annual Leave Balance
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4 bg-white">
                    <div class="mb-3 p-3 bg-light rounded-3 border">
                        <span class="text-muted small fw-semibold text-uppercase d-block" style="font-size:0.72rem;">Employee Recipient</span>
                        <div class="fw-bold text-dark fs-6">{{ $employee->full_name }} <span class="text-muted font-monospace fw-normal">({{ $employee->employee_code }})</span></div>
                        <small class="text-muted">Date of Joining: <strong>{{ optional($employee->date_of_joining)->format('d M Y') ?? 'N/A' }}</strong></small>
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-6">
                            <label class="form-label fw-bold small text-dark">Year <span class="text-danger">*</span></label>
                            <input type="number" name="year" class="form-control" value="{{ $currentYear ?? date('Y') }}" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-bold small text-dark">Annual Quota (Days) <span class="text-danger">*</span></label>
                            <input type="number" step="0.5" name="total_days" class="form-control font-monospace fw-bold" value="{{ $leaveBalance->total_days ?? 16.0 }}" required>
                            <small class="text-muted" style="font-size:0.75rem;">Standard: 16.0 days/year</small>
                        </div>
                    </div>
                    <div class="alert alert-info border-0 rounded-3 p-3 small mb-0">
                        <div class="fw-bold mb-1 text-dark"><i class="fa-solid fa-calculator me-1 text-info"></i>Monthly Accrual Formula:</div>
                        <div>16 days ÷ 12 months = <strong>1.33 days per month</strong>. Approved leave days are automatically deducted from this allocated balance.</div>
                    </div>
                </div>
                <div class="modal-footer bg-light py-2 px-4 border-top">
                    <button type="button" class="btn btn-outline-secondary btn-sm px-3" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm px-4 fw-bold shadow-sm">
                        <i class="fa-solid fa-check me-1"></i> Save &amp; Allocate Balance
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Modal: Record Leave Deduction --}}
<div class="modal fade" id="deductLeaveModal" tabindex="-1" aria-labelledby="deductLeaveModalLabel" aria-hidden="true" style="z-index: 1060;">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 12px; overflow: hidden;">
            <form action="{{ \Illuminate\Support\Facades\Route::has('employees.record-leave-deduction') ? route('employees.record-leave-deduction', $employee) : url('/employees/'.$employee->id.'/record-leave-deduction') }}" method="POST">
                @csrf
                <div class="modal-header py-3 px-4" style="background: #991b1b !important; color: #ffffff !important;">
                    <h5 class="modal-title fs-6 fw-bold text-white mb-0" id="deductLeaveModalLabel">
                        <i class="fa-solid fa-minus-circle me-2"></i>Record Leave Taken &amp; Deduct Days
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4 bg-white">
                    <div class="mb-3 p-3 bg-light rounded-3 border">
                        <span class="text-muted small fw-semibold text-uppercase d-block" style="font-size:0.72rem;">Employee</span>
                        <div class="fw-bold text-dark">{{ $employee->full_name }} <span class="text-muted font-monospace fw-normal">({{ $employee->employee_code }})</span></div>
                        <div class="small text-success fw-bold mt-1">
                            <i class="fa-solid fa-circle-check me-1"></i>Available Balance: {{ number_format($leaveBalance->remaining_days ?? 16, 2) }} Days
                        </div>
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-6">
                            <label class="form-label fw-bold small text-dark">Days to Deduct <span class="text-danger">*</span></label>
                            <input type="number" step="0.5" name="days" class="form-control font-monospace fw-bold" placeholder="e.g. 2.0" required max="{{ $leaveBalance->remaining_days ?? 16 }}" min="0.5">
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-bold small text-dark">Start Date <span class="text-danger">*</span></label>
                            <input type="date" name="start_date" class="form-control" value="{{ date('Y-m-d') }}" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold small text-dark">End Date (Optional)</label>
                        <input type="date" name="end_date" class="form-control" value="{{ date('Y-m-d') }}">
                    </div>
                    <div class="mb-0">
                        <label class="form-label fw-bold small text-dark">Reason / Leave Notes <span class="text-danger">*</span></label>
                        <textarea name="reason" rows="3" class="form-control" placeholder="e.g. Approved annual vacation leave" required></textarea>
                    </div>
                </div>
                <div class="modal-footer bg-light py-2 px-4 border-top">
                    <button type="button" class="btn btn-outline-secondary btn-sm px-3" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger btn-sm px-4 fw-bold shadow-sm">
                        <i class="fa-solid fa-minus me-1"></i> Confirm Deduction
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Modal: Link / Edit Biometric Device User ID --}}
<div class="modal fade" id="linkDeviceIdModal" tabindex="-1" aria-labelledby="linkDeviceIdModalLabel" aria-hidden="true" style="z-index: 1060;">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 12px; overflow: hidden;">
            <form action="{{ \Illuminate\Support\Facades\Route::has('employees.update-device-id') ? route('employees.update-device-id', $employee) : url('/employees/'.$employee->id.'/update-device-id') }}" method="POST">
                @csrf
                <div class="modal-header py-3 px-4" style="background: #0f172a !important; color: #ffffff !important;">
                    <h5 class="modal-title fs-6 fw-bold text-white mb-0" id="linkDeviceIdModalLabel">
                        <i class="fa-solid fa-fingerprint text-success me-2"></i>Link Biometric Device User ID
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4 bg-white">
                    <div class="mb-3 p-3 bg-light rounded-3 border">
                        <span class="text-muted small fw-semibold text-uppercase d-block" style="font-size:0.72rem;">Target Employee</span>
                        <div class="fw-bold text-dark fs-6">{{ $employee->full_name }} <span class="text-muted font-monospace fw-normal">({{ $employee->employee_code }})</span></div>
                        <small class="text-muted">Role: <strong>{{ $employee->role_title ?: 'N/A' }}</strong> • Site: <strong>{{ $employee->project->name ?? 'Head Office' }}</strong></small>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold small text-dark">
                            Device User ID / PIN <span class="text-danger">*</span>
                        </label>
                        <div class="input-group">
                            <span class="input-group-text bg-light"><i class="fa-solid fa-id-card text-muted"></i></span>
                            <input type="text" name="device_user_id" class="form-control font-monospace fw-bold fs-6" 
                                   value="{{ old('device_user_id', $employee->device_user_id) }}" 
                                   placeholder="e.g. 1, 2, 11, or 101" required>
                        </div>
                        <small class="text-muted mt-1 d-block" style="font-size:0.75rem;">
                            Enter the numeric <strong>User ID / PIN</strong> registered for this employee on the <strong>ZKTeco MB460</strong> device (e.g., <code>11</code> for EMP-11).
                        </small>
                    </div>

                    <div class="alert alert-success border-0 rounded-3 p-3 small mb-0">
                        <div class="fw-bold mb-1"><i class="fa-solid fa-bolt me-1 text-success"></i>Real-Time Automatic Sync:</div>
                        <div>Once linked, whenever the employee punches in/out using Face or Fingerprint on the terminal, the ERP will automatically record their <strong>Check-In</strong>, <strong>Check-Out</strong>, and calculate <strong>Hours Worked</strong> without any manual work.</div>
                    </div>
                </div>
                <div class="modal-footer bg-light py-2 px-4 border-top">
                    <button type="button" class="btn btn-outline-secondary btn-sm px-3" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success btn-sm px-4 fw-bold shadow-sm">
                        <i class="fa-solid fa-check me-1"></i> Save &amp; Auto-Sync Punches
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- ── Modal: Upload Guarantor #1 ID Card ────────────────────────────── --}}
<div class="modal fade" id="uploadGuarantorIdModal1" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow rounded-4 overflow-hidden">
            <form action="{{ route('employees.upload-guarantor-doc', $employee) }}" method="POST" enctype="multipart/form-data">
                @csrf
                <input type="hidden" name="doc_type" value="guarantor_id_card">
                <div class="modal-header py-3 px-4 bg-primary text-white">
                    <h5 class="modal-title fs-6 fw-bold mb-0">
                        <i class="fa-solid fa-id-card me-2"></i>Upload Guarantor #1 National / Kebele ID Card
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4 bg-white">
                    <div class="mb-3">
                        <label class="form-label fw-bold small text-dark">Guarantor #1 Full Name</label>
                        <input type="text" name="guarantor_name" class="form-control" value="{{ old('guarantor_name', $employee->guarantor_name) }}" placeholder="Full legal name of guarantor">
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label fw-bold small text-dark">National / Kebele ID No.</label>
                            <input type="text" name="guarantor_id_number" class="form-control font-monospace" value="{{ old('guarantor_id_number', $employee->guarantor_id_number) }}" placeholder="e.g. AA/12345/2016">
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-bold small text-dark">Phone Number</label>
                            <input type="text" name="guarantor_phone" class="form-control" value="{{ old('guarantor_phone', $employee->guarantor_phone) }}" placeholder="09xxxxxxxx">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold small text-dark">Select ID Card File (Image or PDF) <span class="text-danger">*</span></label>
                        <input type="file" name="document" class="form-control" accept="image/*,application/pdf" required>
                        <small class="text-muted">Accepts PDF, JPG, PNG, WEBP (Max 15MB)</small>
                    </div>
                </div>
                <div class="modal-footer bg-light py-2 px-4 border-top">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm px-4 fw-bold">
                        <i class="fa-solid fa-cloud-arrow-up me-1"></i> Upload ID Card
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- ── Modal: Upload Guarantee Letter #1 ──────────────────────────────── --}}
<div class="modal fade" id="uploadGuaranteeLetterModal1" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow rounded-4 overflow-hidden">
            <form action="{{ route('employees.upload-guarantor-doc', $employee) }}" method="POST" enctype="multipart/form-data">
                @csrf
                <input type="hidden" name="doc_type" value="guarantee_letter">
                <div class="modal-header py-3 px-4 bg-warning text-dark">
                    <h5 class="modal-title fs-6 fw-bold mb-0">
                        <i class="fa-solid fa-file-shield me-2"></i>Upload Guarantee Letter #1 (የዋስትና ደብዳቤ)
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4 bg-white">
                    <div class="mb-3">
                        <label class="form-label fw-bold small text-dark">Guarantor #1 Full Name</label>
                        <input type="text" name="guarantor_name" class="form-control" value="{{ old('guarantor_name', $employee->guarantor_name) }}" placeholder="Full legal name of guarantor">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold small text-dark">Select Signed Guarantee Letter Document <span class="text-danger">*</span></label>
                        <input type="file" name="document" class="form-control" accept="image/*,application/pdf" required>
                        <small class="text-muted">Upload stamped/signed guarantee letter (PDF, JPG, PNG - Max 15MB)</small>
                    </div>
                </div>
                <div class="modal-footer bg-light py-2 px-4 border-top">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning text-dark btn-sm px-4 fw-bold">
                        <i class="fa-solid fa-cloud-arrow-up me-1"></i> Upload Guarantee Letter
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- ── Modal: Upload Guarantor #2 ID Card ────────────────────────────── --}}
<div class="modal fade" id="uploadGuarantorIdModal2" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow rounded-4 overflow-hidden">
            <form action="{{ route('employees.upload-guarantor-doc', $employee) }}" method="POST" enctype="multipart/form-data">
                @csrf
                <input type="hidden" name="doc_type" value="guarantor_2_id_card">
                <div class="modal-header py-3 px-4 bg-secondary text-white">
                    <h5 class="modal-title fs-6 fw-bold mb-0">
                        <i class="fa-solid fa-id-card me-2"></i>Upload Guarantor #2 National / Kebele ID Card
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4 bg-white">
                    <div class="mb-3">
                        <label class="form-label fw-bold small text-dark">Guarantor #2 Full Name</label>
                        <input type="text" name="guarantor_2_name" class="form-control" value="{{ old('guarantor_2_name', $employee->guarantor_2_name) }}" placeholder="Full legal name of second guarantor">
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label fw-bold small text-dark">National / Kebele ID No.</label>
                            <input type="text" name="guarantor_2_id_number" class="form-control font-monospace" value="{{ old('guarantor_2_id_number', $employee->guarantor_2_id_number) }}" placeholder="e.g. AA/54321/2016">
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-bold small text-dark">Phone Number</label>
                            <input type="text" name="guarantor_2_phone" class="form-control" value="{{ old('guarantor_2_phone', $employee->guarantor_2_phone) }}" placeholder="09xxxxxxxx">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold small text-dark">Select ID Card File (Image or PDF) <span class="text-danger">*</span></label>
                        <input type="file" name="document" class="form-control" accept="image/*,application/pdf" required>
                        <small class="text-muted">Accepts PDF, JPG, PNG, WEBP (Max 15MB)</small>
                    </div>
                </div>
                <div class="modal-footer bg-light py-2 px-4 border-top">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-secondary btn-sm px-4 fw-bold">
                        <i class="fa-solid fa-cloud-arrow-up me-1"></i> Upload ID Card
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- ── Modal: Upload Guarantee Letter #2 ──────────────────────────────── --}}
<div class="modal fade" id="uploadGuaranteeLetterModal2" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow rounded-4 overflow-hidden">
            <form action="{{ route('employees.upload-guarantor-doc', $employee) }}" method="POST" enctype="multipart/form-data">
                @csrf
                <input type="hidden" name="doc_type" value="guarantee_letter_2">
                <div class="modal-header py-3 px-4 bg-warning text-dark">
                    <h5 class="modal-title fs-6 fw-bold mb-0">
                        <i class="fa-solid fa-file-shield me-2"></i>Upload Guarantee Letter #2
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4 bg-white">
                    <div class="mb-3">
                        <label class="form-label fw-bold small text-dark">Guarantor #2 Full Name</label>
                        <input type="text" name="guarantor_2_name" class="form-control" value="{{ old('guarantor_2_name', $employee->guarantor_2_name) }}" placeholder="Full legal name of second guarantor">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold small text-dark">Select Signed Guarantee Letter Document <span class="text-danger">*</span></label>
                        <input type="file" name="document" class="form-control" accept="image/*,application/pdf" required>
                        <small class="text-muted">Upload stamped/signed guarantee letter (PDF, JPG, PNG - Max 15MB)</small>
                    </div>
                </div>
                <div class="modal-footer bg-light py-2 px-4 border-top">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning text-dark btn-sm px-4 fw-bold">
                        <i class="fa-solid fa-cloud-arrow-up me-1"></i> Upload Guarantee Letter
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

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
