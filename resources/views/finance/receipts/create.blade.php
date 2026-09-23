@extends('layouts.app')

@section('title', 'Upload Receipt')

@section('content')
<div class="d-flex align-items-center gap-3 mb-4">
    <a href="{{ route('receipts.index') }}" class="btn btn-sm btn-outline-secondary">
        <i class="fa-solid fa-arrow-left"></i>
    </a>
    <div>
        <h1 class="h3 mb-0 fw-bold">
            <i class="fa-solid fa-upload text-primary me-2"></i>Upload Receipt
        </h1>
        <p class="text-muted small mb-0">Supports JPEG, PNG, WEBP, PDF · Max 10 MB · OCR runs automatically</p>
    </div>
</div>

<div class="row g-4 justify-content-center">
    <div class="col-lg-8">
        <form action="{{ route('receipts.store') }}" method="POST" enctype="multipart/form-data" id="receipt-upload-form">
            @csrf

            {{-- ── Drop Zone ──────────────────────────────────────────── --}}
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-transparent border-bottom py-3">
                    <h6 class="mb-0 fw-semibold"><i class="fa-solid fa-file-image text-primary me-2"></i>Receipt File</h6>
                </div>
                <div class="card-body">
                    <div id="drop-zone"
                         class="border border-2 border-dashed rounded-3 text-center p-5 position-relative"
                         style="border-color:#6366f1!important;background:rgba(99,102,241,0.04);cursor:pointer;transition:background 0.2s;">
                        <input type="file" name="receipt_file" id="receipt_file"
                               accept=".jpg,.jpeg,.png,.webp,.pdf"
                               class="position-absolute top-0 start-0 w-100 h-100 opacity-0"
                               style="cursor:pointer;" required>
                        <div id="drop-placeholder">
                            <i class="fa-solid fa-cloud-arrow-up fa-3x mb-3 text-primary opacity-75"></i>
                            <p class="fw-semibold mb-1">Drag & Drop your receipt here</p>
                            <p class="text-muted small mb-0">or click to browse · JPEG, PNG, WEBP, PDF · max 10 MB</p>
                        </div>
                        <div id="drop-preview" class="d-none">
                            <img id="preview-img" src="" alt="preview"
                                 class="img-fluid rounded shadow-sm mb-2" style="max-height:200px;">
                            <div id="preview-pdf" class="d-none">
                                <i class="fa-solid fa-file-pdf fa-3x text-danger mb-2"></i>
                                <p class="mb-0 fw-semibold" id="preview-filename"></p>
                            </div>
                            <p class="text-muted small mb-0" id="preview-size"></p>
                            <button type="button" class="btn btn-sm btn-outline-danger mt-2" id="btn-remove-file">
                                <i class="fa-solid fa-trash me-1"></i>Remove
                            </button>
                        </div>
                    </div>
                    @error('receipt_file')
                        <div class="text-danger small mt-1">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            {{-- ── Optional Fields ─────────────────────────────────────── --}}
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-transparent border-bottom py-3">
                    <h6 class="mb-0 fw-semibold"><i class="fa-solid fa-sliders text-secondary me-2"></i>Optional Details</h6>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="project_id" class="form-label form-label-sm">Link to Project <span class="text-muted">(optional)</span></label>
                            <select name="project_id" id="project_id" class="form-select form-select-sm">
                                <option value="">— No Project —</option>
                                @foreach($projects as $p)
                                    <option value="{{ $p->id }}" @selected(old('project_id') == $p->id)>{{ $p->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="category" class="form-label form-label-sm">Override Category <span class="text-muted">(auto-detected if blank)</span></label>
                            <select name="category" id="category" class="form-select form-select-sm">
                                <option value="">Auto-detect from receipt</option>
                                @foreach($categories as $key => $label)
                                    <option value="{{ $key }}" @selected(old('category') == $key)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-12">
                            <label for="notes" class="form-label form-label-sm">Notes <span class="text-muted">(optional)</span></label>
                            <textarea name="notes" id="notes" rows="2"
                                      class="form-control form-control-sm"
                                      placeholder="Any additional context about this receipt...">{{ old('notes') }}</textarea>
                        </div>
                    </div>
                </div>
            </div>

            {{-- ── OCR Notice ───────────────────────────────────────────── --}}
            <div class="alert alert-info border-0 d-flex align-items-start gap-2 mb-4">
                <i class="fa-solid fa-circle-info mt-1 flex-shrink-0"></i>
                <div>
                    <strong>Automatic OCR Parsing</strong> — Tesseract will scan your receipt and extract
                    vendor, date, and amounts. You can review and correct the results after upload.
                    Works best on clear, printed receipts. Handwritten or very crumpled receipts
                    may need manual correction.
                </div>
            </div>

            {{-- ── Submit ───────────────────────────────────────────────── --}}
            <div class="d-flex gap-2">
                <button type="submit" id="btn-analyze" class="btn btn-primary btn-lg flex-fill">
                    <span id="btn-analyze-idle">
                        <i class="fa-solid fa-magnifying-glass me-2"></i>Analyze Receipt
                    </span>
                    <span id="btn-analyze-loading" class="d-none">
                        <span class="spinner-border spinner-border-sm me-2"></span>Analyzing… Please wait
                    </span>
                </button>
                <a href="{{ route('receipts.index') }}" class="btn btn-outline-secondary btn-lg">Cancel</a>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    const dropZone   = document.getElementById('drop-zone');
    const fileInput  = document.getElementById('receipt_file');
    const placeholder = document.getElementById('drop-placeholder');
    const previewDiv  = document.getElementById('drop-preview');
    const previewImg  = document.getElementById('preview-img');
    const previewPdf  = document.getElementById('preview-pdf');
    const previewFn   = document.getElementById('preview-filename');
    const previewSz   = document.getElementById('preview-size');
    const btnRemove   = document.getElementById('btn-remove-file');
    const form        = document.getElementById('receipt-upload-form');
    const btnAnalyze  = document.getElementById('btn-analyze');
    const btnIdle     = document.getElementById('btn-analyze-idle');
    const btnLoading  = document.getElementById('btn-analyze-loading');

    // Drag-over visual
    dropZone.addEventListener('dragover',  e => { e.preventDefault(); dropZone.style.background = 'rgba(99,102,241,0.10)'; });
    dropZone.addEventListener('dragleave', () => { dropZone.style.background = 'rgba(99,102,241,0.04)'; });
    dropZone.addEventListener('drop',      e => { e.preventDefault(); dropZone.style.background = 'rgba(99,102,241,0.04)'; handleFile(e.dataTransfer.files[0]); });
    fileInput.addEventListener('change',   () => handleFile(fileInput.files[0]));

    function handleFile(file) {
        if (!file) return;
        placeholder.classList.add('d-none');
        previewDiv.classList.remove('d-none');

        const sizeMb = (file.size / 1024 / 1024).toFixed(2);
        previewSz.textContent = file.name + ' — ' + sizeMb + ' MB';

        if (file.type === 'application/pdf') {
            previewImg.classList.add('d-none');
            previewPdf.classList.remove('d-none');
            previewFn.textContent = file.name;
        } else {
            previewPdf.classList.add('d-none');
            previewImg.classList.remove('d-none');
            const reader = new FileReader();
            reader.onload = e => { previewImg.src = e.target.result; };
            reader.readAsDataURL(file);
        }
    }

    btnRemove.addEventListener('click', () => {
        fileInput.value = '';
        previewDiv.classList.add('d-none');
        placeholder.classList.remove('d-none');
        previewImg.src = '';
    });

    // Spinner on submit
    form.addEventListener('submit', () => {
        btnIdle.classList.add('d-none');
        btnLoading.classList.remove('d-none');
        btnAnalyze.disabled = true;
    });
})();
</script>
@endpush
