@extends('layouts.app')

@section('title', 'Write Correspondence Letter - Wechacha ERP')

@section('content')
<div class="container-fluid py-3" style="max-width: 900px;">

    {{-- Breadcrumb & Header --}}
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-1 small">
                    <li class="breadcrumb-item"><a href="{{ route('letters.my-letters.index') }}" class="text-decoration-none">My Letters</a></li>
                    <li class="breadcrumb-item active">New Letter</li>
                </ol>
            </nav>
            <h3 class="fw-bold mb-1">
                <i class="fa-solid fa-file-pen text-primary me-2"></i>Write Correspondence Letter
            </h3>
            <p class="text-muted small mb-0">Write your letter, save as a draft to finish later, or send directly to the Secretary.</p>
        </div>
        <a href="{{ route('letters.my-letters.index') }}" class="btn btn-outline-secondary shadow-sm">
            <i class="fa-solid fa-arrow-left me-1"></i> Back to My Letters
        </a>
    </div>

    {{-- Errors --}}
    @if($errors->any())
        <div class="alert alert-danger alert-dismissible fade show border-start border-4 border-danger shadow-xs" role="alert">
            <div class="fw-bold mb-1"><i class="fa-solid fa-triangle-exclamation me-1"></i> Please correct the following issues:</div>
            <ul class="mb-0 ps-3 small">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    {{-- Info Card --}}
    <div class="alert alert-info border-0 shadow-xs rounded-3 mb-4 d-flex align-items-center">
        <i class="fa-solid fa-circle-info fs-4 text-info me-3"></i>
        <div class="small">
            <strong>Direct to Secretary Workflow:</strong> You do not need to choose a category, reference number, or person. When sent, your letter arrives in the Secretary Inbox where the Secretary will assign a locked reference number, select the category, and assign the handling person.
        </div>
    </div>

    {{-- Composition Card --}}
    <div class="card border-0 shadow-sm rounded-3 overflow-hidden">
        <div class="card-header bg-white border-bottom py-3">
            <h5 class="fw-bold mb-0 text-dark"><i class="fa-solid fa-feather me-2 text-primary"></i>Letter Composition</h5>
        </div>
        <div class="card-body p-4">
            <form action="{{ route('letters.my-letters.store') }}" method="POST" enctype="multipart/form-data" id="letterForm">
                @csrf

                {{-- Subject --}}
                <div class="mb-3">
                    <label for="subject" class="form-label fw-bold">Subject / Title <span class="text-danger">*</span></label>
                    <input type="text" class="form-control @error('subject') is-invalid @enderror" id="subject" name="subject" value="{{ old('subject') }}" placeholder="e.g. Annual Leave Request / Advance Salary Request / Tax Query" required autofocus>
                    @error('subject')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="row g-3 mb-3">
                    {{-- Priority --}}
                    <div class="col-md-6">
                        <label for="priority" class="form-label fw-bold">Priority</label>
                        <select class="form-select @error('priority') is-invalid @enderror" id="priority" name="priority">
                            <option value="normal" {{ old('priority', 'normal') === 'normal' ? 'selected' : '' }}>Normal</option>
                            <option value="urgent" {{ old('priority') === 'urgent' ? 'selected' : '' }}>Urgent</option>
                        </select>
                        @error('priority')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    {{-- Sender Info (Auto-filled) --}}
                    <div class="col-md-6">
                        <label class="form-label fw-bold">Author / Sender</label>
                        <input type="text" class="form-control bg-light" value="{{ auth()->user()->name }} ({{ auth()->user()->email }})" readonly>
                    </div>
                </div>

                {{-- Body / Specification --}}
                <div class="mb-3">
                    <label for="specification" class="form-label fw-bold">Letter Body / Content <span class="text-danger">*</span></label>
                    <textarea class="form-control @error('specification') is-invalid @enderror" id="specification" name="specification" rows="10" placeholder="Write the full content of your letter here..." required>{{ old('specification') }}</textarea>
                    <div class="form-text">Provide full details, justification, and context.</div>
                    @error('specification')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                {{-- Attachments --}}
                <div class="mb-4">
                    <label for="attachments" class="form-label fw-bold">Attachments (Optional)</label>
                    <input class="form-control @error('attachments.*') is-invalid @enderror" type="file" id="attachments" name="attachments[]" multiple accept=".pdf,.png,.jpg,.jpeg">
                    <div class="form-text">Upload supporting documents (PDF, JPG, PNG). Max 10MB per file. Multiple files allowed.</div>
                    @error('attachments.*')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <hr class="my-4">

                {{-- Actions --}}
                <div class="d-flex justify-content-between align-items-center">
                    <a href="{{ route('letters.my-letters.index') }}" class="btn btn-outline-secondary">
                        <i class="fa-solid fa-times me-1"></i> Cancel
                    </a>
                    <div class="d-flex gap-2">
                        <button type="submit" name="submit_action" value="save_draft" class="btn btn-outline-primary px-4 fw-medium">
                            <i class="fa-solid fa-floppy-disk me-1"></i> Save as Draft
                        </button>
                        <button type="submit" name="submit_action" value="send_secretary" class="btn btn-primary px-4 fw-semibold" onclick="return confirm('Send this letter directly to the Secretary Inbox?');">
                            <i class="fa-solid fa-paper-plane me-1"></i> Send to Secretary
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
