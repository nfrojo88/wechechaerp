@extends('layouts.app')
@section('title', 'Edit Official Employee Letter - ' . ($employeeLetter->reference_number ?? ''))

@section('content')
<div class="container-fluid px-4 py-3">

    {{-- Header --}}
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h1 class="h3 mb-0 fw-bold" style="color:var(--brand-800)">
                <i class="fa-solid fa-pen-to-square text-primary me-2"></i>Edit Letter Record
            </h1>
            <p class="text-muted small mb-0 font-monospace">Ref: {{ $employeeLetter->reference_number ?: 'LTR-#'.$employeeLetter->id }}</p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('employee-letters.show', $employeeLetter) }}" class="btn btn-outline-secondary btn-sm">
                <i class="fa-solid fa-eye me-1"></i> View Letter
            </a>
            <a href="{{ route('employee-letters.index') }}" class="btn btn-outline-secondary btn-sm">
                <i class="fa-solid fa-arrow-left me-1"></i> Back to List
            </a>
        </div>
    </div>

    <form action="{{ route('employee-letters.update', $employeeLetter) }}" method="POST" enctype="multipart/form-data">
        @csrf
        @method('PUT')

        <div class="row g-4">
            <div class="col-lg-8">
                <div class="card border-0 shadow-sm rounded-3 mb-4">
                    <div class="card-header bg-white border-bottom py-3">
                        <h6 class="mb-0 fw-bold text-dark"><i class="fa-solid fa-file-lines text-primary me-2"></i>Letter Information</h6>
                    </div>
                    <div class="card-body p-4">
                        <div class="mb-3">
                            <label class="form-label fw-bold">Letter Type <span class="text-danger">*</span></label>
                            <select name="letter_type" class="form-select" required>
                                <option value="thanks_letter" {{ old('letter_type', $employeeLetter->letter_type) == 'thanks_letter' ? 'selected' : '' }}>Thanks / Appreciation Letter</option>
                                <option value="appreciation" {{ old('letter_type', $employeeLetter->letter_type) == 'appreciation' ? 'selected' : '' }}>Letter of Recognition</option>
                                <option value="power_of_attorney" {{ old('letter_type', $employeeLetter->letter_type) == 'power_of_attorney' ? 'selected' : '' }}>Power of Attorney / Representation (ውክልና)</option>
                                <option value="application_letter" {{ old('letter_type', $employeeLetter->letter_type) == 'application_letter' ? 'selected' : '' }}>Application Letter / Request (ማመልከቻ)</option>
                                <option value="first_warning" {{ old('letter_type', $employeeLetter->letter_type) == 'first_warning' ? 'selected' : '' }}>First Written Warning</option>
                                <option value="second_warning" {{ old('letter_type', $employeeLetter->letter_type) == 'second_warning' ? 'selected' : '' }}>Second Written Warning</option>
                                <option value="final_warning" {{ old('letter_type', $employeeLetter->letter_type) == 'final_warning' ? 'selected' : '' }}>Final Warning Letter</option>
                                <option value="show_cause" {{ old('letter_type', $employeeLetter->letter_type) == 'show_cause' ? 'selected' : '' }}>Show Cause / Query</option>
                                <option value="suspension" {{ old('letter_type', $employeeLetter->letter_type) == 'suspension' ? 'selected' : '' }}>Suspension Letter</option>
                                <option value="promotion" {{ old('letter_type', $employeeLetter->letter_type) == 'promotion' ? 'selected' : '' }}>Promotion Letter</option>
                                <option value="termination" {{ old('letter_type', $employeeLetter->letter_type) == 'termination' ? 'selected' : '' }}>Termination Letter</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold">Letter Subject / Title <span class="text-danger">*</span></label>
                            <input type="text" name="title" class="form-control" value="{{ old('title', $employeeLetter->title) }}" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold">Letter Content <span class="text-danger">*</span></label>
                            <textarea name="content" rows="12" class="form-control font-monospace" required>{{ old('content', $employeeLetter->content) }}</textarea>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold">Action Required / Corrective Expectation</label>
                            <textarea name="action_required" rows="3" class="form-control">{{ old('action_required', $employeeLetter->action_required) }}</textarea>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card border-0 shadow-sm rounded-3 mb-4">
                    <div class="card-header bg-white border-bottom py-3">
                        <h6 class="mb-0 fw-bold text-dark"><i class="fa-solid fa-user-check text-primary me-2"></i>Recipient &amp; File</h6>
                    </div>
                    <div class="card-body p-4">
                        <div class="mb-3">
                            <label class="form-label fw-bold">Employee <span class="text-danger">*</span></label>
                            <select name="employee_id" class="form-select select2" required>
                                @foreach($employees as $emp)
                                <option value="{{ $emp->id }}" {{ old('employee_id', $employeeLetter->employee_id) == $emp->id ? 'selected' : '' }}>
                                    {{ $emp->full_name }} ({{ $emp->employee_code }})
                                </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold">Reference Number</label>
                            <input type="text" name="reference_number" class="form-control font-monospace" value="{{ old('reference_number', $employeeLetter->reference_number) }}" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold">Issue Date <span class="text-danger">*</span></label>
                            <input type="date" name="issued_date" class="form-control" value="{{ old('issued_date', optional($employeeLetter->issued_date)->format('Y-m-d')) }}" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold">Effective Date</label>
                            <input type="date" name="effective_date" class="form-control" value="{{ old('effective_date', optional($employeeLetter->effective_date)->format('Y-m-d')) }}">
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold">Replace Signed Document Attachment</label>
                            <input type="file" name="attachment" class="form-control" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx">
                            @if($employeeLetter->attachment_path)
                            <small class="text-success mt-1 d-block">
                                <i class="fa-solid fa-paperclip me-1"></i>Current: {{ basename($employeeLetter->attachment_path) }}
                            </small>
                            @endif
                        </div>

                        <div class="mb-4">
                            <label class="form-label fw-semibold">Acknowledgement Status</label>
                            <select name="acknowledgement_status" class="form-select">
                                <option value="acknowledged" {{ old('acknowledgement_status', $employeeLetter->acknowledgement_status) == 'acknowledged' ? 'selected' : '' }}>Acknowledged</option>
                                <option value="pending" {{ old('acknowledgement_status', $employeeLetter->acknowledgement_status) == 'pending' ? 'selected' : '' }}>Pending Signature</option>
                                <option value="refused_to_sign" {{ old('acknowledgement_status', $employeeLetter->acknowledgement_status) == 'refused_to_sign' ? 'selected' : '' }}>Refused to Sign</option>
                            </select>
                        </div>

                        <button type="submit" class="btn btn-primary w-100 py-2 fw-bold shadow-sm">
                            <i class="fa-solid fa-save me-1"></i> Update Letter Record
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </form>

</div>
@endsection
