@extends('layouts.app')
@section('title', $employeeLetter->title . ' - ' . ($employeeLetter->employee->full_name ?? 'Letter'))

@section('content')
<div class="container-fluid px-4 py-3">

    {{-- Header --}}
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h1 class="h3 mb-0 fw-bold" style="color:var(--brand-800)">
                <i class="fa-solid fa-file-invoice text-primary me-2"></i>Official Letter Record
            </h1>
            <p class="text-muted small mb-0 font-monospace">Ref: {{ $employeeLetter->reference_number ?: 'LTR-#'.$employeeLetter->id }}</p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('employee-letters.print', $employeeLetter) }}" target="_blank" class="btn btn-primary btn-sm shadow-sm">
                <i class="fa-solid fa-print me-1"></i> Print on Letterhead
            </a>
            @if($employeeLetter->attachment_path)
            <a href="{{ asset('storage/' . $employeeLetter->attachment_path) }}" target="_blank" class="btn btn-outline-success btn-sm shadow-sm">
                <i class="fa-solid fa-paperclip me-1"></i> View Signed Document
            </a>
            @endif
            <a href="{{ route('employee-letters.edit', $employeeLetter) }}" class="btn btn-outline-warning btn-sm">
                <i class="fa-solid fa-pen me-1"></i> Edit
            </a>
            <a href="{{ route('employee-letters.index') }}" class="btn btn-outline-secondary btn-sm">
                <i class="fa-solid fa-arrow-left me-1"></i> All Letters
            </a>
        </div>
    </div>

    <div class="row g-4">
        {{-- Formal Letter Presentation --}}
        <div class="col-lg-8">
            <div class="card border-0 shadow rounded-3 bg-white p-4 p-md-5">

                {{-- Letterhead Header --}}
                <div class="d-flex justify-content-between align-items-center border-bottom pb-4 mb-4">
                    <div>
                        <h3 class="fw-bold mb-0 text-dark" style="letter-spacing:-0.02em;">WECHECHA CONSTRUCTION</h3>
                        <p class="text-muted small mb-0">Human Resources &amp; Personnel Department</p>
                        <small class="text-muted">Addis Ababa, Ethiopia · info@wechechaconstruction.com</small>
                    </div>
                    <div class="text-end">
                        <span class="badge {{ $employeeLetter->badge_class }} px-3 py-2 fs-6">
                            <i class="{{ $employeeLetter->icon }} me-1"></i>{{ $employeeLetter->type_label }}
                        </span>
                        <div class="mt-2 text-muted small font-monospace">Ref: <strong>{{ $employeeLetter->reference_number }}</strong></div>
                        <div class="text-muted small">Date: <strong>{{ optional($employeeLetter->issued_date)->format('F d, Y') }}</strong></div>
                    </div>
                </div>

                {{-- Recipient Info Box --}}
                <div class="bg-light rounded-3 p-3 mb-4 border">
                    <div class="row g-2 small">
                        <div class="col-sm-6">
                            <span class="text-muted d-block">TO (EMPLOYEE):</span>
                            <h6 class="fw-bold text-dark mb-0">{{ $employeeLetter->employee->full_name ?? 'N/A' }}</h6>
                            <span class="text-muted font-monospace">{{ $employeeLetter->employee->employee_code ?? '' }}</span>
                        </div>
                        <div class="col-sm-6">
                            <span class="text-muted d-block">POSITION / DEPARTMENT:</span>
                            <div class="fw-semibold text-dark">{{ $employeeLetter->employee->role_title ?? 'Employee' }} ({{ $employeeLetter->employee->department ?? 'General' }})</div>
                            <small class="text-muted">Project: {{ $employeeLetter->employee->project->name ?? 'Head Office / Assigned Site' }}</small>
                        </div>
                    </div>
                </div>

                {{-- Subject --}}
                <div class="mb-4">
                    <h5 class="fw-bold text-dark border-bottom pb-2">
                        SUBJECT: {{ strtoupper($employeeLetter->title) }}
                    </h5>
                </div>

                {{-- Letter Body --}}
                <div class="mb-5" style="line-height:1.8; font-size:1.05rem; white-space:pre-line; color:#1e293b;">
                    {{ $employeeLetter->content }}
                </div>

                {{-- Action / Measures if present --}}
                @if($employeeLetter->action_required)
                <div class="alert alert-secondary border-0 rounded-3 p-3 mb-5">
                    <strong class="d-block mb-1 text-dark"><i class="fa-solid fa-circle-info me-1"></i>Action / Follow-up Note:</strong>
                    <div class="small text-muted">{{ $employeeLetter->action_required }}</div>
                </div>
                @endif

                {{-- Signatures Section --}}
                <div class="row g-4 pt-4 border-top mt-auto">
                    <div class="col-6">
                        <div class="text-muted small mb-4">ISSUED BY (FOR MANAGEMENT):</div>
                        <div style="border-bottom: 1px dashed #94a3b8; height: 35px; width: 80%;"></div>
                        <div class="mt-2 fw-bold text-dark small">{{ $employeeLetter->issuer->name ?? 'HR Manager / Officer' }}</div>
                        <div class="text-muted" style="font-size:0.75rem;">Human Resources Department</div>
                    </div>
                    <div class="col-6">
                        <div class="text-muted small mb-4">EMPLOYEE ACKNOWLEDGEMENT:</div>
                        <div style="border-bottom: 1px dashed #94a3b8; height: 35px; width: 80%;"></div>
                        <div class="mt-2 fw-bold text-dark small">{{ $employeeLetter->employee->full_name ?? 'Employee Signature' }}</div>
                        <div class="text-muted" style="font-size:0.75rem;">Date: ________________________</div>
                    </div>
                </div>

            </div>
        </div>

        {{-- Right Side: Summary & Actions --}}
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm rounded-3 mb-4">
                <div class="card-header bg-white border-bottom py-3">
                    <h6 class="mb-0 fw-bold text-dark"><i class="fa-solid fa-clock-rotate-left text-primary me-2"></i>Record Details</h6>
                </div>
                <div class="card-body p-3">
                    <table class="table table-borderless table-sm mb-0">
                        <tr>
                            <td class="text-muted fw-semibold" style="width:45%;">Letter Type:</td>
                            <td><span class="badge {{ $employeeLetter->badge_class }}">{{ $employeeLetter->type_label }}</span></td>
                        </tr>
                        <tr>
                            <td class="text-muted fw-semibold">Status:</td>
                            <td>
                                @if($employeeLetter->acknowledgement_status === 'acknowledged')
                                    <span class="badge bg-success"><i class="fa-solid fa-check me-1"></i>Acknowledged</span>
                                @elseif($employeeLetter->acknowledgement_status === 'pending')
                                    <span class="badge bg-warning text-dark"><i class="fa-solid fa-clock me-1"></i>Pending</span>
                                @else
                                    <span class="badge bg-danger"><i class="fa-solid fa-xmark me-1"></i>Refused to Sign</span>
                                @endif
                            </td>
                        </tr>
                        <tr>
                            <td class="text-muted fw-semibold">Issued Date:</td>
                            <td><strong>{{ optional($employeeLetter->issued_date)->format('d M Y') }}</strong></td>
                        </tr>
                        @if($employeeLetter->effective_date)
                        <tr>
                            <td class="text-muted fw-semibold">Effective Date:</td>
                            <td>{{ optional($employeeLetter->effective_date)->format('d M Y') }}</td>
                        </tr>
                        @endif
                        <tr>
                            <td class="text-muted fw-semibold">Created By:</td>
                            <td>{{ $employeeLetter->issuer->name ?? 'HR Officer' }}</td>
                        </tr>
                        <tr>
                            <td class="text-muted fw-semibold">Archived On:</td>
                            <td><small class="text-muted">{{ optional($employeeLetter->created_at)->format('d M Y, H:i') }}</small></td>
                        </tr>
                    </table>

                    @if($employeeLetter->employee)
                    <hr class="my-3">
                    <a href="{{ route('employees.show', $employeeLetter->employee) }}" class="btn btn-outline-primary btn-sm w-100">
                        <i class="fa-solid fa-user me-1"></i> View Full Employee Profile
                    </a>
                    @endif
                </div>
            </div>

            {{-- Uploaded Attachment Card --}}
            @if($employeeLetter->attachment_path)
            <div class="card border-0 shadow-sm rounded-3 mb-4">
                <div class="card-header bg-white border-bottom py-3">
                    <h6 class="mb-0 fw-bold text-dark"><i class="fa-solid fa-paperclip text-success me-2"></i>Signed Document Attachment</h6>
                </div>
                <div class="card-body p-3 text-center">
                    <div class="p-3 bg-light rounded-3 mb-3">
                        <i class="fa-solid fa-file-pdf fa-3x text-danger mb-2"></i>
                        <div class="small fw-semibold text-dark text-truncate">{{ basename($employeeLetter->attachment_path) }}</div>
                    </div>
                    <a href="{{ asset('storage/' . $employeeLetter->attachment_path) }}" target="_blank" class="btn btn-success btn-sm w-100">
                        <i class="fa-solid fa-download me-1"></i> View / Download Document
                    </a>
                </div>
            </div>
            @endif

            {{-- Delete Action --}}
            <div class="card border-0 shadow-sm rounded-3">
                <div class="card-body p-3 text-center">
                    <form action="{{ route('employee-letters.destroy', $employeeLetter) }}" method="POST" onsubmit="return confirm('Are you sure you want to delete this official letter record?');">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-outline-danger btn-sm w-100">
                            <i class="fa-solid fa-trash me-1"></i> Delete Letter Record
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

</div>
@endsection
