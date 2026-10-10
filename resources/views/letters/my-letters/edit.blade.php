@extends('layouts.app')

@section('title', 'Edit Draft Letter - Wechacha ERP')

@section('content')
<div class="container-fluid py-3" style="max-width: 900px;">

    {{-- Breadcrumb & Header --}}
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-1 small">
                    <li class="breadcrumb-item"><a href="{{ route('letters.my-letters.index') }}" class="text-decoration-none">My Letters</a></li>
                    <li class="breadcrumb-item active">Edit Draft</li>
                </ol>
            </nav>
            <h3 class="fw-bold mb-1">
                <i class="fa-solid fa-pen-to-square text-primary me-2"></i>Edit Draft Letter
            </h3>
            <p class="text-muted small mb-0">Update your draft letter and save or send directly to the Secretary.</p>
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

    {{-- Composition Card --}}
    <div class="card border-0 shadow-sm rounded-3 overflow-hidden">
        <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center">
            <h5 class="fw-bold mb-0 text-dark"><i class="fa-solid fa-feather me-2 text-primary"></i>Draft Content</h5>
            <span class="badge bg-secondary rounded-pill px-2.5 py-1">Draft</span>
        </div>
        <div class="card-body p-4">
            <form action="{{ route('letters.my-letters.update', $letter->id) }}" method="POST" enctype="multipart/form-data" id="letterForm">
                @csrf
                @method('PUT')

                {{-- Subject --}}
                <div class="mb-3">
                    <label for="subject" class="form-label fw-bold">Subject / Title <span class="text-danger">*</span></label>
                    <input type="text" class="form-control @error('subject') is-invalid @enderror" id="subject" name="subject" value="{{ old('subject', $letter->subject) }}" required autofocus>
                    @error('subject')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="row g-3 mb-3">
                    {{-- Priority --}}
                    <div class="col-md-6">
                        <label for="priority" class="form-label fw-bold">Priority</label>
                        <select class="form-select @error('priority') is-invalid @enderror" id="priority" name="priority">
                            <option value="normal" {{ old('priority', $letter->priority) === 'normal' ? 'selected' : '' }}>Normal</option>
                            <option value="urgent" {{ old('priority', $letter->priority) === 'urgent' ? 'selected' : '' }}>Urgent</option>
                        </select>
                        @error('priority')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    {{-- Sender Info --}}
                    <div class="col-md-6">
                        <label class="form-label fw-bold">Author</label>
                        <input type="text" class="form-control bg-light" value="{{ auth()->user()->name }}" readonly>
                    </div>
                </div>

                {{-- Body / Specification --}}
                <div class="mb-3">
                    <label for="specification" class="form-label fw-bold">Letter Body / Content <span class="text-danger">*</span></label>
                    <textarea class="form-control @error('specification') is-invalid @enderror" id="specification" name="specification" rows="10" required>{{ old('specification', $letter->specification) }}</textarea>
                    @error('specification')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                {{-- Existing Attachments --}}
                @if($letter->attachments->isNotEmpty())
                    <div class="mb-3">
                        <label class="form-label fw-bold">Current Attachments</label>
                        <ul class="list-group list-group-flush border rounded-3 p-2 small">
                            @foreach($letter->attachments as $att)
                                <li class="list-group-item d-flex justify-content-between align-items-center py-2 px-3 border-0">
                                    <div>
                                        <i class="fa-solid fa-paperclip text-primary me-2"></i>
                                        <span class="fw-medium">{{ $att->file_name }}</span>
                                        <span class="text-muted ms-2">({{ number_format($att->file_size / 1024, 1) }} KB)</span>
                                    </div>
                                    <a href="{{ route('letters.attachments.download', $att->id) }}" class="btn btn-xs btn-outline-secondary">Download</a>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                {{-- New Attachments --}}
                <div class="mb-4">
                    <label for="attachments" class="form-label fw-bold">Add Additional Attachments</label>
                    <input class="form-control @error('attachments.*') is-invalid @enderror" type="file" id="attachments" name="attachments[]" multiple accept=".pdf,.png,.jpg,.jpeg">
                    <div class="form-text">Max 10MB per file (PDF, PNG, JPG).</div>
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
                            <i class="fa-solid fa-floppy-disk me-1"></i> Update Draft
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
