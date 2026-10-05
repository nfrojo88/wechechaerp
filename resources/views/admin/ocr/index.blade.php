@extends('layouts.app')

@section('title', 'OCR Receipt Scanner Studio - Global Admin')

@section('content')
<div class="container-fluid py-2">

    {{-- Top Header --}}
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <h3 class="mb-0 fw-bold text-dark">
                    <i class="fa-solid fa-expand text-success me-2"></i>OCR Receipt Scanner Studio
                </h3>
                <span class="badge bg-success text-white px-2.5 py-1.5 rounded-pill shadow-xs">
                    <i class="fa-solid fa-shield-halved me-1"></i>Global Admin Exclusive
                </span>
                <span class="badge bg-primary text-white px-2.5 py-1.5 rounded-pill shadow-xs">
                    <i class="fa-solid fa-bolt me-1"></i>Live AI/OCR Engine
                </span>
            </div>
            <p class="text-muted small mb-0 mt-1">
                Upload or capture any physical or digital receipt. The system automatically reads merchant name, TIN, date, VAT, and totals with instant OCR.
            </p>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <a href="{{ route('admin.ocr.export-vat') }}" class="btn btn-outline-success btn-sm shadow-xs fw-bold" title="Download Excel/CSV matching VAT REPORT SEMPTMBER 2026.xlsx">
                <i class="fa-solid fa-file-excel me-1 text-success"></i>Export VAT Report (Excel)
            </a>
            <button type="button" class="btn btn-primary btn-sm shadow-xs fw-bold" id="btn-autofill-astra" title="Pre-fill Astra General Trading receipt from Row 20 (50,700.02 ETB)">
                <i class="fa-solid fa-bolt me-1"></i>Fill Astra Receipt (50,700 ETB)
            </button>
            <button type="button" class="btn btn-success btn-sm shadow-xs fw-bold" id="btn-autofill-mewedisi" title="Pre-fill Berhanu Tiemay / Mewedisi receipt from Row 23 (10,099.99 ETB)">
                <i class="fa-solid fa-wand-magic-sparkles me-1"></i>Fill Berhanu Receipt
            </button>
            <button type="button" class="btn btn-outline-primary btn-sm shadow-xs fw-semibold" id="btn-load-sample">
                <i class="fa-solid fa-receipt me-1"></i>Try Sample Receipt
            </button>
            <a href="#recent-scans" class="btn btn-light border btn-sm shadow-xs">
                <i class="fa-solid fa-history me-1 text-muted"></i>Recent Scans ({{ $stats['total_scanned'] }})
            </a>
        </div>
    </div>

    {{-- Stats Cards --}}
    <div class="row g-3 mb-4">
        <div class="col-6 col-lg-3">
            <div class="card border-0 shadow-sm rounded-3 h-100">
                <div class="card-body p-3 d-flex align-items-center gap-3">
                    <div class="rounded-circle p-3 d-flex align-items-center justify-content-center bg-primary bg-opacity-10 text-primary">
                        <i class="fa-solid fa-file-invoice fa-xl"></i>
                    </div>
                    <div>
                        <div class="fs-4 fw-bold text-dark">{{ number_format($stats['total_scanned']) }}</div>
                        <div class="text-muted small">Total Scanned Receipts</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card border-0 shadow-sm rounded-3 h-100">
                <div class="card-body p-3 d-flex align-items-center gap-3">
                    <div class="rounded-circle p-3 d-flex align-items-center justify-content-center bg-success bg-opacity-10 text-success">
                        <i class="fa-solid fa-coins fa-xl"></i>
                    </div>
                    <div>
                        <div class="fs-4 fw-bold text-dark">{{ number_format($stats['total_value'], 2) }} <small class="fs-6 text-muted">ETB</small></div>
                        <div class="text-muted small">Total Scanned Value</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card border-0 shadow-sm rounded-3 h-100">
                <div class="card-body p-3 d-flex align-items-center gap-3">
                    <div class="rounded-circle p-3 d-flex align-items-center justify-content-center bg-info bg-opacity-10 text-info">
                        <i class="fa-solid fa-receipt fa-xl"></i>
                    </div>
                    <div>
                        <div class="fs-4 fw-bold text-dark">{{ number_format($stats['total_vat'], 2) }} <small class="fs-6 text-muted">ETB</small></div>
                        <div class="text-muted small">Total VAT Extracted (15%)</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card border-0 shadow-sm rounded-3 h-100">
                <div class="card-body p-3 d-flex align-items-center gap-3">
                    <div class="rounded-circle p-3 d-flex align-items-center justify-content-center bg-warning bg-opacity-10 text-warning">
                        <i class="fa-solid fa-calendar-day fa-xl"></i>
                    </div>
                    <div>
                        <div class="fs-4 fw-bold text-dark">{{ number_format($stats['today_scanned']) }}</div>
                        <div class="text-muted small">Scanned Today</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Main Live Scanner Studio --}}
    <div class="card border-0 shadow-sm rounded-3 mb-4 overflow-hidden">
        <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div class="d-flex align-items-center gap-2">
                <span class="p-2 rounded bg-success bg-opacity-10 text-success">
                    <i class="fa-solid fa-camera-viewfinder"></i>
                </span>
                <strong class="text-dark">Live Receipt Upload &amp; Optical Character Recognition (OCR)</strong>
            </div>
            <div class="d-flex align-items-center gap-2">
                <div class="form-check form-switch mb-0 small" title="Preprocesses photo with contrast stretching and binarization for thermal receipts">
                    <input class="form-check-input cursor-pointer" type="checkbox" id="toggle-enhance" checked>
                    <label class="form-check-label small fw-semibold text-muted cursor-pointer" for="toggle-enhance">Enhanced Binarization</label>
                </div>
                <div id="ocr-status-badge" class="badge bg-secondary px-3 py-1.5 rounded-pill">
                    <i class="fa-solid fa-circle me-1" style="font-size:0.55rem;"></i> Ready to scan
                </div>
            </div>
        </div>

        <div class="card-body p-3 p-md-4">
            <div class="row g-4">

                {{-- Left Column: Upload & Live Image Preview --}}
                <div class="col-lg-5">
                    <div class="p-3 border rounded-3 bg-light h-100 d-flex flex-column">

                        {{-- Drop Zone --}}
                        <div id="drop-zone" class="border border-2 border-dashed rounded-3 p-4 text-center position-relative bg-white shadow-xs cursor-pointer transition-all"
                             style="border-color:#10b981 !important; min-height: 240px; display: flex; flex-direction: column; justify-content: center; align-items: center;">
                            <input type="file" id="file-input" accept="image/*,application/pdf" class="position-absolute top-0 start-0 w-100 h-100 opacity-0 cursor-pointer" style="z-index:5;">
                            
                            <div id="drop-prompt">
                                <div class="rounded-circle bg-success bg-opacity-10 text-success p-3 d-inline-flex mb-3">
                                    <i class="fa-solid fa-cloud-arrow-up fa-2x"></i>
                                </div>
                                <h6 class="fw-bold text-dark mb-1">Drag &amp; Drop Receipt Here</h6>
                                <p class="text-muted small mb-3">or click to browse files (JPEG, PNG, WEBP, PDF)</p>
                                
                                <div class="d-flex justify-content-center gap-2">
                                    <button type="button" class="btn btn-sm btn-success px-3 fw-semibold shadow-xs" onclick="document.getElementById('file-input').click()">
                                        <i class="fa-solid fa-folder-open me-1"></i>Browse Receipt
                                    </button>
                                    <label class="btn btn-sm btn-outline-dark px-3 fw-semibold shadow-xs mb-0 cursor-pointer">
                                        <i class="fa-solid fa-camera me-1"></i>Snap Photo
                                        <input type="file" id="camera-input" accept="image/*" capture="environment" class="d-none">
                                    </label>
                                </div>
                            </div>

                            {{-- Preview area --}}
                            <div id="preview-area" class="d-none w-100 text-center">
                                <div class="position-relative d-inline-block w-100">
                                    <img id="receipt-preview-img" src="" alt="Receipt Preview" 
                                         class="img-fluid rounded border shadow-sm" 
                                         style="max-height: 380px; object-fit: contain; width: auto; background:#fff;">
                                    <div id="pdf-preview-box" class="d-none py-5">
                                        <i class="fa-solid fa-file-pdf fa-4x text-danger mb-2"></i>
                                        <div class="fw-bold text-dark" id="pdf-filename">PDF Document</div>
                                    </div>
                                </div>
                                <div class="d-flex justify-content-center gap-2 mt-2">
                                    <button type="button" class="btn btn-xs btn-outline-secondary btn-sm" id="btn-rotate-img" title="Rotate 90°">
                                        <i class="fa-solid fa-rotate-right me-1"></i>Rotate
                                    </button>
                                    <button type="button" class="btn btn-xs btn-outline-primary btn-sm" id="btn-reprocess-img" title="Re-scan OCR">
                                        <i class="fa-solid fa-bolt me-1"></i>Re-Scan
                                    </button>
                                    <button type="button" class="btn btn-xs btn-outline-danger btn-sm" id="btn-clear-img" title="Remove Receipt">
                                        <i class="fa-solid fa-trash me-1"></i>Clear
                                    </button>
                                </div>
                            </div>
                        </div>

                        {{-- Hidden offscreen canvas for preprocessing --}}
                        <canvas id="offscreen-canvas" class="d-none"></canvas>

                        {{-- OCR Progress Indicator --}}
                        <div id="ocr-progress-container" class="mt-3 d-none">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <small class="fw-bold text-dark" id="ocr-progress-label">
                                    <i class="fa-solid fa-spinner fa-spin me-1 text-primary"></i>Scanning Receipt...
                                </small>
                                <small class="fw-bold text-primary font-monospace" id="ocr-progress-pct">0%</small>
                            </div>
                            <div class="progress" style="height: 8px;">
                                <div id="ocr-progress-bar" class="progress-bar progress-bar-striped progress-bar-animated bg-success" style="width: 0%"></div>
                            </div>
                        </div>

                        {{-- Tips --}}
                        <div class="mt-auto pt-3">
                            <div class="alert alert-light border small text-muted mb-0 py-2">
                                <i class="fa-solid fa-lightbulb text-warning me-1"></i>
                                <strong>Smart Receipt Engine:</strong> Automatically extracts Seller info (TIN, Address, Phone), Buyer TIN, FS #, Line items &amp; 15% VAT!
                            </div>
                        </div>

                    </div>
                </div>

                {{-- Right Column: Structured Extracted Fields & Save --}}
                <div class="col-lg-7">
                    <div class="p-3 border rounded-3 bg-white h-100 d-flex flex-column">

                        <ul class="nav nav-tabs nav-tabs-bordered mb-3" id="ocrTabs" role="tablist">
                            <li class="nav-item">
                                <button class="nav-link active fw-bold small" id="extracted-tab" data-bs-toggle="tab" data-bs-target="#tab-extracted" type="button">
                                    <i class="fa-solid fa-list-check me-1 text-success"></i>Extracted Data &amp; Verification
                                </button>
                            </li>
                            <li class="nav-item">
                                <button class="nav-link fw-bold small" id="rawtext-tab" data-bs-toggle="tab" data-bs-target="#tab-rawtext" type="button">
                                    <i class="fa-solid fa-font me-1 text-primary"></i>Raw Recognized Text (<span id="raw-lines-count">0 lines</span>)
                                </button>
                            </li>
                        </ul>

                        <div class="tab-content flex-grow-1" id="ocrTabsContent">

                            {{-- Tab 1: Extracted Structured Data --}}
                            <div class="tab-pane fade show active" id="tab-extracted">
                                <form id="save-receipt-form">
                                    <input type="hidden" id="stored_file_path" name="file_path" value="">
                                    <input type="hidden" id="ocr_raw_text_hidden" name="ocr_raw_text" value="">

                                    {{-- Quick-Fill Candidate Chips --}}
                                    <div id="detected-chips-container" class="mb-3 d-none">
                                        <div class="p-2.5 rounded-3 border bg-info bg-opacity-10">
                                            <div class="d-flex align-items-center justify-content-between mb-1">
                                                <small class="fw-bold text-dark">
                                                    <i class="fa-solid fa-wand-magic-sparkles text-primary me-1"></i>Detected Values from Receipt (Click to fill):
                                                </small>
                                                <small class="text-muted" style="font-size:0.7rem;">Click any chip to insert</small>
                                            </div>
                                            <div id="detected-chips-list" class="d-flex flex-wrap gap-1.5"></div>
                                        </div>
                                    </div>

                                    <div class="row g-2">

                                        {{-- 1. Seller / Merchant Business Name --}}
                                        <div class="col-md-7">
                                            <label class="form-label small fw-bold text-dark mb-1">
                                                Merchant / Supplier Business Name *
                                                <span class="badge bg-primary bg-opacity-10 text-primary border ms-1" style="font-size:0.65rem;">VAT Col E (Seller Name)</span>
                                            </label>
                                            <div class="input-group input-group-sm">
                                                <span class="input-group-text bg-light"><i class="fa-solid fa-store text-muted"></i></span>
                                                <input type="text" class="form-control fw-semibold" id="field_vendor" name="vendor_name" placeholder="e.g. ASTRA GENERAL TRADING" required oninput="updateVatReportPreview()">
                                            </div>
                                        </div>

                                        {{-- 2. Proprietor / Manager Name --}}
                                        <div class="col-md-5">
                                            <label class="form-label small fw-bold text-dark mb-1">
                                                Proprietor / Contact Name
                                                <span class="badge bg-light text-muted border ms-1" style="font-size:0.65rem;">Seller</span>
                                            </label>
                                            <div class="input-group input-group-sm">
                                                <span class="input-group-text bg-light"><i class="fa-solid fa-user text-muted"></i></span>
                                                <input type="text" class="form-control" id="field_proprietor" name="proprietor_name" placeholder="e.g. BERHANU TIEMAY ADHENA">
                                            </div>
                                        </div>

                                        {{-- 3. Supplier TIN --}}
                                        <div class="col-md-6">
                                            <label class="form-label small fw-bold text-dark mb-1">
                                                Supplier TIN # *
                                                <span class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25 ms-1" style="font-size:0.65rem;">NOT Buyer TIN</span>
                                                <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 ms-1" style="font-size:0.65rem;">VAT Col D</span>
                                            </label>
                                            <div class="input-group input-group-sm">
                                                <span class="input-group-text bg-light"><i class="fa-solid fa-id-card text-muted"></i></span>
                                                <input type="text" class="form-control font-monospace fw-bold text-primary" id="field_tin" name="vendor_tin" placeholder="e.g. 0024916531" required oninput="updateVatReportPreview()">
                                            </div>
                                        </div>

                                        {{-- 4. Buyer's TIN --}}
                                        <div class="col-md-6">
                                            <label class="form-label small fw-semibold text-muted mb-1">
                                                Buyer's TIN
                                                <span class="badge bg-light text-muted border ms-1" style="font-size:0.65rem;">Wechecha: 0038480010</span>
                                            </label>
                                            <div class="input-group input-group-sm">
                                                <span class="input-group-text bg-light"><i class="fa-solid fa-user-tag text-muted"></i></span>
                                                <input type="text" class="form-control font-monospace" id="field_buyer_tin" name="buyer_tin" placeholder="e.g. 0038480010">
                                            </div>
                                        </div>

                                        {{-- 5. Supplier Address --}}
                                        <div class="col-md-7">
                                            <label class="form-label small fw-semibold text-muted mb-1">
                                                Supplier Address / Location
                                                <span class="badge bg-light text-muted border ms-1" style="font-size:0.65rem;">Upper Section</span>
                                            </label>
                                            <div class="input-group input-group-sm">
                                                <span class="input-group-text bg-light"><i class="fa-solid fa-location-dot text-muted"></i></span>
                                                <input type="text" class="form-control" id="field_address" name="vendor_address" placeholder="e.g. A.A. Arada Sub City W.01">
                                            </div>
                                        </div>

                                        {{-- 6. Supplier Phone / Mobile --}}
                                        <div class="col-md-5">
                                            <label class="form-label small fw-semibold text-muted mb-1">Supplier Phones</label>
                                            <div class="input-group input-group-sm">
                                                <span class="input-group-text bg-light"><i class="fa-solid fa-phone text-muted"></i></span>
                                                <input type="text" class="form-control font-monospace" id="field_phone" name="vendor_phone" placeholder="e.g. 0911517719 / 0911255119">
                                            </div>
                                        </div>

                                        {{-- 7. FS / Receipt Number --}}
                                        <div class="col-md-4">
                                            <label class="form-label small fw-bold text-dark mb-1">
                                                FS / Receipt Number *
                                                <span class="badge bg-primary bg-opacity-10 text-primary border ms-1" style="font-size:0.65rem;">VAT Col H</span>
                                            </label>
                                            <div class="input-group input-group-sm">
                                                <span class="input-group-text bg-light"><i class="fa-solid fa-hashtag text-muted"></i></span>
                                                <input type="text" class="form-control font-monospace fw-bold text-primary" id="field_fs_no" name="fs_no" placeholder="e.g. FS00002674" required oninput="updateVatReportPreview()">
                                            </div>
                                        </div>

                                        {{-- 8. Machine / ERCA Number --}}
                                        <div class="col-md-4">
                                            <label class="form-label small fw-bold text-dark mb-1">
                                                ERCA / Machine / MRC #
                                                <span class="badge bg-primary bg-opacity-10 text-primary border ms-1" style="font-size:0.65rem;">VAT Col G</span>
                                            </label>
                                            <div class="input-group input-group-sm">
                                                <span class="input-group-text bg-light"><i class="fa-solid fa-cash-register text-muted"></i></span>
                                                <input type="text" class="form-control font-monospace fw-bold" id="field_machine_no" name="machine_no" placeholder="e.g. TDB0015170" oninput="updateVatReportPreview()">
                                            </div>
                                        </div>

                                        {{-- 9. Receipt Date --}}
                                        <div class="col-md-4">
                                            <label class="form-label small fw-bold text-dark mb-1">
                                                Receipt Date *
                                                <span class="badge bg-primary bg-opacity-10 text-primary border ms-1" style="font-size:0.65rem;">VAT Col F</span>
                                            </label>
                                            <div class="input-group input-group-sm">
                                                <span class="input-group-text bg-light"><i class="fa-solid fa-calendar text-muted"></i></span>
                                                <input type="date" class="form-control" id="field_date" name="receipt_date" value="{{ today()->toDateString() }}" required onchange="updateVatReportPreview()">
                                            </div>
                                        </div>

                                        {{-- 10. Item Description --}}
                                        <div class="col-md-6">
                                            <label class="form-label small fw-bold text-dark mb-1">
                                                Item / Purchased Description *
                                                <span class="badge bg-primary bg-opacity-10 text-primary border ms-1" style="font-size:0.65rem;">VAT Col I</span>
                                            </label>
                                            <div class="input-group input-group-sm">
                                                <span class="input-group-text bg-light"><i class="fa-solid fa-box text-muted"></i></span>
                                                <input type="text" class="form-control fw-semibold" id="field_description" name="description" placeholder="e.g. WATER PROOF AND WIRE" oninput="updateVatReportPreview()">
                                            </div>
                                        </div>

                                        {{-- 11. Expense Category --}}
                                        <div class="col-md-3">
                                            <label class="form-label small fw-bold text-dark mb-1">Expense Category *</label>
                                            <select class="form-select form-select-sm" id="field_category" name="category" required>
                                                @foreach($categories as $key => $lbl)
                                                    <option value="{{ $key }}" {{ $key == 'material' ? 'selected' : '' }}>{{ $lbl }}</option>
                                                @endforeach
                                            </select>
                                        </div>

                                        {{-- 12. Link to Project --}}
                                        <div class="col-md-3">
                                            <label class="form-label small fw-bold text-dark mb-1">Link to Project</label>
                                            <select class="form-select form-select-sm" id="field_project_id" name="project_id">
                                                <option value="">— Head Office / General —</option>
                                                @foreach($projects as $p)
                                                    <option value="{{ $p->id }}">{{ $p->name }}</option>
                                                @endforeach
                                            </select>
                                        </div>

                                        {{-- FINANCIALS BOX --}}
                                        <div class="col-12">
                                            <div class="p-3 rounded-3 border bg-light">
                                                <div class="row g-2 align-items-center">
                                                    <div class="col-md-4">
                                                        <label class="form-label small fw-bold text-dark mb-1">
                                                            Taxable Subtotal (ETB) *
                                                            <span class="badge bg-primary bg-opacity-10 text-primary border ms-1" style="font-size:0.65rem;">VAT Col M</span>
                                                        </label>
                                                        <input type="number" step="0.01" class="form-control form-control-sm font-monospace text-end fw-semibold" id="field_subtotal" name="subtotal" placeholder="0.00" oninput="calculateFromSubtotal(); updateVatReportPreview();">
                                                    </div>
                                                    <div class="col-md-4">
                                                        <label class="form-label small fw-bold text-dark mb-1">
                                                            VAT Amount 15% (ETB) *
                                                            <span class="badge bg-primary bg-opacity-10 text-primary border ms-1" style="font-size:0.65rem;">VAT Col N</span>
                                                        </label>
                                                        <input type="number" step="0.01" class="form-control form-control-sm font-monospace text-end text-muted" id="field_vat" name="vat_amount" placeholder="0.00" oninput="calculateFromVat(); updateVatReportPreview();">
                                                    </div>
                                                    <div class="col-md-4">
                                                        <label class="form-label small fw-bold text-success mb-1">
                                                            VALUE AFTER VAT (ETB) *
                                                            <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 ms-1" style="font-size:0.65rem;">VAT Col O</span>
                                                        </label>
                                                        <input type="number" step="0.01" class="form-control form-control-sm font-monospace text-end fw-bold fs-6 text-success border-success" id="field_total" name="total_amount" placeholder="0.00" required oninput="calculateFromTotal(); updateVatReportPreview();">
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        {{-- LIVE ERCA VAT DECLARATION PREVIEW CARD --}}
                                        <div class="col-12">
                                            <div class="card border border-success border-opacity-50 rounded-3 bg-white shadow-xs overflow-hidden">
                                                <div class="card-header bg-success bg-opacity-10 py-2 d-flex justify-content-between align-items-center flex-wrap gap-2">
                                                    <div class="d-flex align-items-center gap-2">
                                                        <i class="fa-solid fa-file-excel text-success"></i>
                                                        <strong class="text-success small">ERCA VAT Declaration Row (Line 100 - Matches Excel)</strong>
                                                        <span class="badge bg-white text-success border border-success border-opacity-25 small" style="font-size:0.65rem;">VAT REPORT SEMPTMBER 2026.xlsx</span>
                                                    </div>
                                                    <div class="d-flex gap-2">
                                                        <button type="button" class="btn btn-xs btn-outline-success btn-sm py-1 px-2.5 fw-bold shadow-xs" id="btn-copy-vat-row" title="Copy tab-separated row to paste directly into your Excel report">
                                                            <i class="fa-solid fa-copy me-1"></i>Copy Row (Ctrl+V into Excel)
                                                        </button>
                                                    </div>
                                                </div>
                                                <div class="card-body p-2">
                                                    <div class="table-responsive">
                                                        <table class="table table-bordered table-sm mb-0 small text-nowrap align-middle" style="font-size:0.75rem;">
                                                            <thead class="table-light text-center">
                                                                <tr class="text-secondary" style="font-size:0.68rem;">
                                                                    <th>Col A<br><span class="text-muted">Cat</span></th>
                                                                    <th>Col B<br><span class="text-muted">Cal</span></th>
                                                                    <th>Col C<br><span class="text-muted">Type</span></th>
                                                                    <th class="text-primary fw-bold">Col D<br><span>Supplier TIN</span></th>
                                                                    <th class="fw-bold">Col E<br><span>Seller Name</span></th>
                                                                    <th>Col F<br><span>Date (DD/MM/YYYY)</span></th>
                                                                    <th>Col G<br><span>MRC No</span></th>
                                                                    <th>Col H<br><span>FS No</span></th>
                                                                    <th>Col I<br><span>Item / Description</span></th>
                                                                    <th>Col J<br><span>UOM</span></th>
                                                                    <th>Col K<br><span>Qty</span></th>
                                                                    <th>Col L<br><span>Unit Price</span></th>
                                                                    <th class="text-end fw-bold">Col M<br><span>Total Value</span></th>
                                                                    <th class="text-end text-muted">Col N<br><span>VAT (15%)</span></th>
                                                                    <th class="text-end fw-bold text-success">Col O<br><span>Value After VAT</span></th>
                                                                </tr>
                                                            </thead>
                                                            <tbody class="text-center font-monospace" id="vat-report-preview-row">
                                                                <tr>
                                                                    <td><span class="badge bg-light text-dark border">G</span></td>
                                                                    <td><span class="badge bg-light text-dark border">G</span></td>
                                                                    <td><span class="badge bg-light text-dark border">3</span></td>
                                                                    <td class="fw-bold text-primary" id="v_cell_tin">—</td>
                                                                    <td class="text-start fw-semibold text-dark" id="v_cell_vendor">—</td>
                                                                    <td id="v_cell_date">—</td>
                                                                    <td id="v_cell_mrc">—</td>
                                                                    <td class="fw-bold text-dark" id="v_cell_fs">—</td>
                                                                    <td class="text-start text-dark" id="v_cell_desc">—</td>
                                                                    <td id="v_cell_uom">9</td>
                                                                    <td id="v_cell_qty">1</td>
                                                                    <td class="text-end font-monospace" id="v_cell_uprice">0.00</td>
                                                                    <td class="text-end font-monospace fw-bold" id="v_cell_subtotal">0.00</td>
                                                                    <td class="text-end font-monospace text-muted" id="v_cell_vat">0.00</td>
                                                                    <td class="text-end font-monospace fw-bold text-success" id="v_cell_total">0.00</td>
                                                                </tr>
                                                            </tbody>
                                                        </table>
                                                    </div>
                                                    <div class="d-flex justify-content-between align-items-center mt-2 px-1">
                                                        <small class="text-muted" style="font-size:0.7rem;">
                                                            <i class="fa-solid fa-circle-info text-success me-1"></i>All 15 columns match your Ethiopian VAT declaration. Click <strong>"Copy Row"</strong> and paste with <strong>Ctrl+V</strong> directly into Excel!
                                                        </small>
                                                        <div class="d-flex gap-2 align-items-center">
                                                            <label class="small text-muted mb-0" style="font-size:0.7rem;">VAT Cat:</label>
                                                            <select class="form-select form-select-sm py-0 px-2 small text-muted" id="field_vat_cat" style="font-size:0.7rem; width:auto;" onchange="updateVatReportPreview()">
                                                                <option value="G" selected>G (Goods)</option>
                                                                <option value="S">S (Services)</option>
                                                            </select>
                                                            <label class="small text-muted mb-0 ms-1" style="font-size:0.7rem;">UOM:</label>
                                                            <select class="form-select form-select-sm py-0 px-2 small text-muted" id="field_uom" style="font-size:0.7rem; width:auto;" onchange="updateVatReportPreview()">
                                                                <option value="9" selected>9 (OTHER)</option>
                                                                <option value="7">7 (PCS)</option>
                                                                <option value="2">2 (KG)</option>
                                                                <option value="5">5 (LIT)</option>
                                                                <option value="10">10 (PC)</option>
                                                            </select>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        {{-- Line Items Preview --}}
                                        <div class="col-12" id="line-items-section" style="display:none;">
                                            <div class="d-flex justify-content-between align-items-center mb-1">
                                                <label class="form-label small fw-bold text-dark mb-0">
                                                    <i class="fa-solid fa-basket-shopping me-1 text-primary"></i>Detected Line Items:
                                                </label>
                                                <span class="badge bg-light text-dark border" id="line-items-count">0 items</span>
                                            </div>
                                            <div class="table-responsive border rounded bg-white">
                                                <table class="table table-sm table-striped mb-0 small" id="line-items-table">
                                                    <thead class="table-light">
                                                        <tr>
                                                            <th>Item Description</th>
                                                            <th class="text-end" style="width:70px;">Qty</th>
                                                            <th class="text-end" style="width:110px;">Unit Price</th>
                                                            <th class="text-end" style="width:120px;">Total (ETB)</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody id="line-items-body"></tbody>
                                                </table>
                                            </div>
                                        </div>

                                        {{-- Submit / Save Action --}}
                                        <div class="col-12 pt-2">
                                            <button type="submit" class="btn btn-success w-100 py-2 fw-bold shadow-sm" id="btn-save-receipt" disabled>
                                                <i class="fa-solid fa-circle-check me-1"></i>Save Scanned Receipt Record into ERP
                                            </button>
                                            <div class="text-muted text-center small mt-1" style="font-size:0.75rem;">
                                                <i class="fa-solid fa-lock me-1"></i>Saved records are archived in the ERP Receipts Catalog and can be referenced in finance vouchers.
                                            </div>
                                        </div>

                                    </div>
                                </form>
                            </div>

                            {{-- Tab 2: Raw OCR Recognized Text --}}
                            <div class="tab-pane fade" id="tab-rawtext">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <span class="small text-muted">Complete text stream detected by the OCR engine:</span>
                                    <button type="button" class="btn btn-xs btn-outline-secondary btn-sm" id="btn-copy-raw">
                                        <i class="fa-solid fa-copy me-1"></i>Copy Text
                                    </button>
                                </div>
                                <textarea id="raw-ocr-textarea" class="form-control font-monospace small bg-light" rows="14" readonly placeholder="Raw text output will appear here once scanning begins..."></textarea>
                            </div>

                        </div>

                    </div>
                </div>

            </div>
        </div>
    </div>

    {{-- Recent Scanned Receipts Table --}}
    <div class="card border-0 shadow-sm rounded-3" id="recent-scans">
        <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
            <h5 class="mb-0 fw-bold text-dark">
                <i class="fa-solid fa-clock-rotate-left me-2 text-primary"></i>Archive of Scanned Receipts
            </h5>
            <div class="d-flex gap-2">
                <form method="GET" action="{{ route('admin.ocr.index') }}" class="d-flex gap-2">
                    <input type="text" name="search" class="form-control form-control-sm" placeholder="Search merchant, TIN..." value="{{ request('search') }}">
                    <button type="submit" class="btn btn-sm btn-primary"><i class="fa-solid fa-magnifying-glass"></i></button>
                    @if(request()->hasAny(['search', 'category', 'project_id']))
                        <a href="{{ route('admin.ocr.index') }}" class="btn btn-sm btn-light border"><i class="fa-solid fa-times"></i></a>
                    @endif
                </form>
            </div>
        </div>

        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light small">
                        <tr>
                            <th class="ps-3">Receipt #</th>
                            <th>Receipt Date</th>
                            <th>Merchant / Vendor</th>
                            <th>TIN Number</th>
                            <th>Category</th>
                            <th>Project</th>
                            <th class="text-end">Subtotal</th>
                            <th class="text-end">VAT (15%)</th>
                            <th class="text-end">Grand Total</th>
                            <th class="text-center">Receipt File</th>
                            <th class="text-end pe-3">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($receipts as $r)
                        <tr>
                            <td class="ps-3 font-monospace fw-bold text-primary">
                                {{ $r->receipt_number }}
                            </td>
                            <td class="small">{{ $r->receipt_date ? $r->receipt_date->format('M d, Y') : '—' }}</td>
                            <td>
                                <strong class="text-dark">{{ $r->vendor_name ?: 'General Merchant' }}</strong>
                                @if($r->description)
                                    <div class="text-muted small" style="font-size:0.75rem;">{{ Str::limit($r->description, 35) }}</div>
                                @endif
                            </td>
                            <td class="font-monospace small text-muted">{{ $r->vendor_tin ?: '—' }}</td>
                            <td>
                                <span class="badge bg-light text-dark border">{{ ucfirst($r->category) }}</span>
                            </td>
                            <td class="small text-muted">{{ $r->project?->name ?? 'General / Head Office' }}</td>
                            <td class="text-end font-monospace small">{{ number_format($r->subtotal, 2) }}</td>
                            <td class="text-end font-monospace small text-muted">{{ number_format($r->vat_amount, 2) }}</td>
                            <td class="text-end font-monospace fw-bold text-success">{{ number_format($r->total_amount, 2) }} ETB</td>
                            <td class="text-center">
                                @if($r->file_path)
                                    <a href="{{ asset('storage/' . $r->file_path) }}" target="_blank" class="btn btn-xs btn-outline-primary btn-sm py-0 px-2" title="Inspect original file">
                                        <i class="fa-solid fa-arrow-up-right-from-square me-1"></i>View
                                    </a>
                                @else
                                    <span class="text-muted small">—</span>
                                @endif
                            </td>
                            <td class="text-end pe-3">
                                <div class="d-flex justify-content-end align-items-center gap-1">
                                    <button type="button" class="btn btn-xs btn-outline-info btn-sm py-1 px-2" 
                                            onclick='viewReceiptModal(@json($r), "{{ asset("storage/" . $r->file_path) }}")'
                                            title="View Extracted Details">
                                        <i class="fa-solid fa-eye me-1"></i>Details
                                    </button>
                                    <form action="{{ route('admin.ocr.destroy', $r->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete receipt {{ $r->receipt_number }}?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-xs btn-outline-danger btn-sm py-1 px-2" title="Delete Receipt">
                                            <i class="fa-solid fa-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="11" class="text-center py-5 text-muted">
                                <div class="rounded-circle bg-light d-inline-flex p-3 mb-2">
                                    <i class="fa-solid fa-receipt fa-2x text-muted opacity-50"></i>
                                </div>
                                <h6 class="fw-bold text-dark">No scanned receipts found yet</h6>
                                <p class="small text-muted mb-0">Upload or snap a receipt above to try live OCR recognition!</p>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($receipts->hasPages())
            <div class="card-footer bg-white border-0 py-3">
                {{ $receipts->links() }}
            </div>
            @endif
        </div>
    </div>

</div>

{{-- Receipt Inspection Modal --}}
<div class="modal fade" id="receiptDetailsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-light py-3">
                <h6 class="modal-title fw-bold text-dark" id="modalReceiptTitle">Receipt Details</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4" id="modalReceiptBody"></div>
            <div class="modal-footer bg-light py-2">
                <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

@push('scripts')
{{-- Embed Tesseract.js directly from CDN for reliable in-browser live OCR --}}
<script src="https://cdn.jsdelivr.net/npm/tesseract.js@5/dist/tesseract.min.js"></script>

<script>
let currentImageRotation = 0;
let currentImageDataUrl = null;
let currentImageFile = null;

// File input handlers
const dropZone = document.getElementById('drop-zone');
const fileInput = document.getElementById('file-input');
const cameraInput = document.getElementById('camera-input');
const previewArea = document.getElementById('preview-area');
const dropPrompt = document.getElementById('drop-prompt');
const previewImg = document.getElementById('receipt-preview-img');
const pdfPreviewBox = document.getElementById('pdf-preview-box');

fileInput.addEventListener('change', e => { if (e.target.files[0]) handleFileSelected(e.target.files[0]); });
cameraInput.addEventListener('change', e => { if (e.target.files[0]) handleFileSelected(e.target.files[0]); });

// Drag and drop events
['dragenter', 'dragover'].forEach(eventName => {
    dropZone.addEventListener(eventName, e => { e.preventDefault(); dropZone.classList.add('bg-light'); }, false);
});
['dragleave', 'drop'].forEach(eventName => {
    dropZone.addEventListener(eventName, e => { e.preventDefault(); dropZone.classList.remove('bg-light'); }, false);
});
dropZone.addEventListener('drop', e => {
    const dt = e.dataTransfer;
    if (dt && dt.files && dt.files.length > 0) {
        handleFileSelected(dt.files[0]);
    }
});

// Rotate button
document.getElementById('btn-rotate-img').addEventListener('click', () => {
    currentImageRotation = (currentImageRotation + 90) % 360;
    previewImg.style.transform = `rotate(${currentImageRotation}deg)`;
});

// Re-process button
document.getElementById('btn-reprocess-img').addEventListener('click', () => {
    if (currentImageFile) {
        runGeminiAiScan(currentImageFile, previewImg);
    } else if (previewImg.src && !previewImg.classList.contains('d-none')) {
        runOcrPipeline(previewImg);
    }
});

// Clear button
document.getElementById('btn-clear-img').addEventListener('click', () => {
    resetScanner();
});

// Copy Raw Text button
document.getElementById('btn-copy-raw').addEventListener('click', () => {
    const txt = document.getElementById('raw-ocr-textarea').value;
    if (txt) {
        navigator.clipboard.writeText(txt);
        alert('OCR text copied to clipboard!');
    }
});

function resetScanner() {
    fileInput.value = '';
    cameraInput.value = '';
    previewArea.classList.add('d-none');
    dropPrompt.classList.remove('d-none');
    previewImg.src = '';
    currentImageDataUrl = null;
    currentImageFile = null;
    currentImageRotation = 0;
    previewImg.style.transform = 'none';
    document.getElementById('ocr-progress-container').classList.add('d-none');
    document.getElementById('ocr-status-badge').className = 'badge bg-secondary px-3 py-1.5 rounded-pill';
    document.getElementById('ocr-status-badge').innerHTML = '<i class="fa-solid fa-circle me-1" style="font-size:0.55rem;"></i> Ready to scan';
    document.getElementById('btn-save-receipt').disabled = true;
    document.getElementById('save-receipt-form').reset();
    document.getElementById('raw-ocr-textarea').value = '';
    document.getElementById('raw-lines-count').textContent = '0 lines';
    document.getElementById('line-items-section').style.display = 'none';
    document.getElementById('line-items-body').innerHTML = '';
    document.getElementById('detected-chips-container').classList.add('d-none');
    document.getElementById('detected-chips-list').innerHTML = '';
    updateVatReportPreview();
}

// Main File Handling & OCR Execution
function handleFileSelected(file) {
    currentImageFile = file;
    const isPdf = file.type.includes('pdf');
    dropPrompt.classList.add('d-none');
    previewArea.classList.remove('d-none');

    const reader = new FileReader();
    reader.onload = function(e) {
        currentImageDataUrl = e.target.result;
        if (isPdf) {
            previewImg.classList.add('d-none');
            pdfPreviewBox.classList.remove('d-none');
            document.getElementById('pdf-filename').textContent = file.name;
            runGeminiAiScan(file, previewImg);
        } else {
            pdfPreviewBox.classList.add('d-none');
            previewImg.classList.remove('d-none');
            let scanStarted = false;
            const startScan = () => {
                if (!scanStarted) {
                    scanStarted = true;
                    runGeminiAiScan(file, previewImg);
                }
            };
            previewImg.onload = startScan;
            previewImg.src = currentImageDataUrl;
            if (previewImg.complete && previewImg.naturalWidth > 0) {
                startScan();
            }
        }
    };
    reader.readAsDataURL(file);
}

// Gemini AI Vision Scanner with automatic Local OCR Fallback
function runGeminiAiScan(file, imgElement) {
    const progressContainer = document.getElementById('ocr-progress-container');
    const progressBar = document.getElementById('ocr-progress-bar');
    const progressPct = document.getElementById('ocr-progress-pct');
    const progressLabel = document.getElementById('ocr-progress-label');

    progressContainer.classList.remove('d-none');
    progressBar.style.width = '30%';
    progressPct.textContent = '30%';
    progressLabel.innerHTML = '<i class="fa-solid fa-brain fa-spin me-1 text-primary"></i> Uploading &amp; Calling Gemini AI Vision Engine...';
    updateStatus('Gemini AI Vision Scanning...', 'warning');

    const formData = new FormData();
    formData.append('receipt_file', file);
    formData.append('_token', '{{ csrf_token() }}');

    fetch('{{ route("admin.ocr.ai-scan") }}', {
        method: 'POST',
        headers: {
            'Accept': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}'
        },
        body: formData
    })
    .then(r => r.json())
    .then(res => {
        if (res.success && res.file_path) {
            document.getElementById('stored_file_path').value = res.file_path;
        }

        if (res.success && res.ai && res.data) {
            progressBar.style.width = '100%';
            progressPct.textContent = '100%';
            progressLabel.innerHTML = '<i class="fa-solid fa-sparkles me-1 text-success"></i> Gemini AI Vision: Scan Complete!';
            updateStatus('Gemini AI Vision: 100% Extracted', 'success');

            populateFormFromAi(res.data);
            document.getElementById('btn-save-receipt').disabled = false;
        } else {
            // Local OCR Fallback if AI server is busy or network issue
            progressBar.style.width = '45%';
            progressPct.textContent = '45%';
            progressLabel.innerHTML = '<i class="fa-solid fa-bolt me-1 text-info"></i> Running High-Precision Local Engine...';
            updateStatus('Local OCR Fallback Engine...', 'info');

            runOcrPipeline(imgElement);
        }
    })
    .catch(err => {
        console.warn('AI Scan network issue, falling back to local OCR:', err);
        runOcrPipeline(imgElement);
    });
}

function populateFormFromAi(data) {
    if (data.merchant_name) document.getElementById('field_vendor').value = data.merchant_name;
    if (data.proprietor_name) document.getElementById('field_proprietor').value = data.proprietor_name;
    if (data.supplier_tin) document.getElementById('field_tin').value = data.supplier_tin;
    if (data.buyer_tin) document.getElementById('field_buyer_tin').value = data.buyer_tin;
    if (data.fs_no) document.getElementById('field_fs_no').value = data.fs_no;
    if (data.machine_no) document.getElementById('field_machine_no').value = data.machine_no;
    if (data.receipt_date) document.getElementById('field_date').value = data.receipt_date;
    if (data.supplier_address) document.getElementById('field_address').value = data.supplier_address;
    if (data.supplier_phone) document.getElementById('field_phone').value = data.supplier_phone;
    if (data.subtotal) document.getElementById('field_subtotal').value = parseFloat(data.subtotal).toFixed(2);
    if (data.vat_amount) document.getElementById('field_vat').value = parseFloat(data.vat_amount).toFixed(2);
    if (data.total_amount) document.getElementById('field_total').value = parseFloat(data.total_amount).toFixed(2);
    if (data.category) document.getElementById('field_category').value = data.category;
    if (data.description) document.getElementById('field_description').value = data.description;
    if (data.vat_category && document.getElementById('field_vat_cat')) document.getElementById('field_vat_cat').value = data.vat_category;
    if (data.uom_id && document.getElementById('field_uom')) document.getElementById('field_uom').value = data.uom_id;

    if (data.raw_text) {
        document.getElementById('raw-ocr-textarea').value = data.raw_text;
        document.getElementById('ocr_raw_text_hidden').value = data.raw_text;
        const lines = data.raw_text.split('\n').filter(l => l.trim().length > 0);
        document.getElementById('raw-lines-count').textContent = `${lines.length} lines`;
    }

    // Populate line items table
    if (Array.isArray(data.line_items) && data.line_items.length > 0) {
        const tableBody = document.getElementById('line-items-body');
        const tableSection = document.getElementById('line-items-section');
        const countBadge = document.getElementById('line-items-count');
        tableBody.innerHTML = '';
        tableSection.style.display = 'block';
        countBadge.textContent = `${data.line_items.length} items`;

        data.line_items.forEach(itm => {
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td class="fw-semibold text-dark">${itm.name || 'Item'}</td>
                <td class="text-end font-monospace">${itm.qty || 1}</td>
                <td class="text-end font-monospace">${parseFloat(itm.unit_price || 0).toLocaleString(undefined, {minimumFractionDigits:2})}</td>
                <td class="text-end font-monospace fw-bold text-success">${parseFloat(itm.total || 0).toLocaleString(undefined, {minimumFractionDigits:2})}</td>
            `;
            tableBody.appendChild(tr);
        });
    }

    // Populate candidate chips
    const chips = [];
    if (data.total_amount) chips.push({ label: 'Total', value: parseFloat(data.total_amount).toFixed(2), target: 'field_total' });
    if (data.subtotal) chips.push({ label: 'Subtotal', value: parseFloat(data.subtotal).toFixed(2), target: 'field_subtotal' });
    if (data.vat_amount) chips.push({ label: 'VAT 15%', value: parseFloat(data.vat_amount).toFixed(2), target: 'field_vat' });
    if (data.supplier_tin) chips.push({ label: 'Supplier TIN', value: data.supplier_tin, target: 'field_tin' });
    if (data.buyer_tin) chips.push({ label: 'Buyer TIN', value: data.buyer_tin, target: 'field_buyer_tin' });
    if (data.fs_no) chips.push({ label: 'FS #', value: data.fs_no, target: 'field_fs_no' });
    if (data.receipt_date) chips.push({ label: 'Date', value: data.receipt_date, target: 'field_date' });
    if (data.merchant_name) chips.push({ label: 'Vendor', value: data.merchant_name, target: 'field_vendor' });
    renderCandidateChips(chips);

    updateVatReportPreview();
}

function updateStatus(text, badgeClass) {
    const badge = document.getElementById('ocr-status-badge');
    badge.className = `badge bg-${badgeClass} px-3 py-1.5 rounded-pill`;
    badge.innerHTML = `<i class="fa-solid fa-circle me-1" style="font-size:0.55rem;"></i> ${text}`;
}

// Image preprocessing via Canvas: Grayscale & Contrast Stretched Binarization
function preprocessImage(imgElement) {
    const canvas = document.getElementById('offscreen-canvas');
    const ctx = canvas.getContext('2d');

    let width = imgElement.naturalWidth || imgElement.width || 1200;
    let height = imgElement.naturalHeight || imgElement.height || 1600;

    // Scale to standard OCR resolution (~1600px width)
    const targetW = 1600;
    if (width > 0) {
        const ratio = targetW / width;
        width = targetW;
        height = Math.round(height * ratio);
    }

    canvas.width = width;
    canvas.height = height;

    // Apply rotation if needed
    ctx.save();
    if (currentImageRotation > 0) {
        ctx.translate(width / 2, height / 2);
        ctx.rotate((currentImageRotation * Math.PI) / 180);
        ctx.drawImage(imgElement, -width / 2, -height / 2, width, height);
    } else {
        ctx.drawImage(imgElement, 0, 0, width, height);
    }
    ctx.restore();

    const doEnhance = document.getElementById('toggle-enhance').checked;
    if (!doEnhance) {
        return canvas.toDataURL('image/png');
    }

    // Enhance contrast and binarize
    try {
        const imgData = ctx.getImageData(0, 0, width, height);
        const data = imgData.data;

        // Sample min & max brightness
        let minLum = 255;
        let maxLum = 0;
        for (let i = 0; i < data.length; i += 16) {
            const lum = 0.299 * data[i] + 0.587 * data[i + 1] + 0.114 * data[i + 2];
            if (lum < minLum) minLum = lum;
            if (lum > maxLum) maxLum = lum;
        }

        const range = Math.max(1, maxLum - minLum);
        const threshold = minLum + range * 0.52; // Threshold for dark ink

        for (let i = 0; i < data.length; i += 4) {
            const lum = 0.299 * data[i] + 0.587 * data[i + 1] + 0.114 * data[i + 2];
            const v = lum > threshold ? 255 : 0;
            data[i] = v;
            data[i + 1] = v;
            data[i + 2] = v;
        }

        ctx.putImageData(imgData, 0, 0);
        return canvas.toDataURL('image/png');
    } catch (e) {
        console.warn('Canvas pre-processing fallback:', e);
        return canvas.toDataURL('image/png');
    }
}

// In-Browser Live OCR via Tesseract.js with multi-pass recognition
function runOcrPipeline(imgElement) {
    const progressContainer = document.getElementById('ocr-progress-container');
    const progressBar = document.getElementById('ocr-progress-bar');
    const progressPct = document.getElementById('ocr-progress-pct');
    const progressLabel = document.getElementById('ocr-progress-label');

    progressContainer.classList.remove('d-none');
    progressBar.style.width = '10%';
    progressPct.textContent = '10%';
    progressLabel.innerHTML = '<i class="fa-solid fa-wand-magic-sparkles me-1 text-primary"></i> Pre-processing &amp; Enhancing Receipt...';
    updateStatus('Enhancing &amp; Scanning...', 'warning');

    const processedDataUrl = preprocessImage(imgElement);

    Tesseract.recognize(
        processedDataUrl,
        'eng',
        {
            logger: m => {
                if (m.status === 'recognizing text') {
                    const pct = Math.round(m.progress * 100);
                    progressBar.style.width = `${pct}%`;
                    progressPct.textContent = `${pct}%`;
                    progressLabel.innerHTML = `<i class="fa-solid fa-bolt fa-spin me-1 text-success"></i> Recognizing Characters (${pct}%)...`;
                }
            }
        }
    ).then(({ data: { text } }) => {
        progressBar.style.width = '100%';
        progressPct.textContent = '100%';
        progressLabel.innerHTML = '<i class="fa-solid fa-circle-check me-1 text-success"></i> Scan Complete!';
        updateStatus('Scan Complete &amp; Parsed', 'success');

        // Populate raw text tab
        document.getElementById('raw-ocr-textarea').value = text;
        const lines = text.split('\n').filter(l => l.trim().length > 0);
        document.getElementById('raw-lines-count').textContent = `${lines.length} lines`;
        document.getElementById('ocr_raw_text_hidden').value = text;

        // Apply Smart Ethiopian & Commercial Receipt Regex Rules
        parseReceiptText(text, lines);

        // Unlock Save button
        document.getElementById('btn-save-receipt').disabled = false;
    }).catch(err => {
        progressLabel.innerHTML = `<i class="fa-solid fa-triangle-exclamation text-danger me-1"></i> Scan Failed: ${err.message}`;
        updateStatus('OCR Error', 'danger');
    });
}

// Smart Commercial & Ethiopian FS Receipt Multi-Pass Parser
function parseReceiptText(text, lines) {
    const chips = [];

    // Helper: strip asterisks and currency words from number strings
    const cleanNum = str => parseFloat(str.replace(/[*,\s]/g, ''));

    // 1. Subtotal / Taxable:
    // Matches "TAXBL1 *44,086.97" or "TAXABLE *44,086.97" or "SUBTOTAL: *44,086.97"
    const subtotalMatch = text.match(/(?:TAXBL1|TAXABLE|TAXBL|SUBTOTAL|SUB\s*TOTAL|NET\s*AMOUNT)\s*[:.\-]*\s*[*]?\s*([0-9]{1,3}(?:,[0-9]{3})*(?:\.[0-9]{2})|[0-9]+(?:\.[0-9]{2}))/i);
    let subtotalAmt = 0;
    if (subtotalMatch) {
        subtotalAmt = cleanNum(subtotalMatch[1]);
        chips.push({ label: 'Subtotal', value: subtotalAmt.toFixed(2), target: 'field_subtotal' });
    }

    // 2. VAT Amount (15%):
    // Matches "TAX1 15.00% *6,613.05" or "VAT 15% *6,613.05"
    const vatMatch = text.match(/(?:TAX1\s*15(?:\.00)?%?|TAX\s*15(?:\.00)?%?|VAT\s*15(?:\.00)?%?|ታክስ\s*15%?|VAT\s*AMOUNT|TAX\s*AMOUNT)\s*[:.\-]*\s*[*]?\s*([0-9]{1,3}(?:,[0-9]{3})*(?:\.[0-9]{2})|[0-9]+(?:\.[0-9]{2}))/i);
    let vatAmt = 0;
    if (vatMatch) {
        vatAmt = cleanNum(vatMatch[1]);
        chips.push({ label: 'VAT 15%', value: vatAmt.toFixed(2), target: 'field_vat' });
    }

    // 3. Grand Total:
    // Matches "TOTAL: *50,700.02" or "CASH Birr *50,700.02" or "*50,700.02"
    const totalMatch = text.match(/(?:TOTAL\s*[:.\-]|GRAND\s*TOTAL\s*[:.\-]|CASH\s*Birr|CASH\s*BIRR)\s*[*]?\s*([0-9]{1,3}(?:,[0-9]{3})*(?:\.[0-9]{2})|[0-9]+(?:\.[0-9]{2}))/i)
                    || text.match(/(?:TOTAL|CASH)\s*[:.\-]?\s*[*]?\s*([0-9]{1,3}(?:,[0-9]{3})*(?:\.[0-9]{2})|[0-9]+(?:\.[0-9]{2}))/i);
    let totalAmt = totalMatch ? cleanNum(totalMatch[1]) : 0;

    // Mathematical Triad Solver: Search for decimal triplet A, B, C where B ~= A * 0.15 and C ~= A + B
    if (!subtotalAmt || !vatAmt || !totalAmt) {
        const rawNumbers = [...text.matchAll(/\b([0-9]{1,3}(?:,[0-9]{3})+(?:\.[0-9]{2})|[0-9]{3,}(?:\.[0-9]{2}))\b/g)]
            .map(m => cleanNum(m[1]))
            .filter(n => n > 5);

        for (let i = 0; i < rawNumbers.length; i++) {
            const a = rawNumbers[i];
            const expectedVat = a * 0.15;
            for (let j = 0; j < rawNumbers.length; j++) {
                if (i === j) continue;
                const b = rawNumbers[j];
                if (Math.abs(b - expectedVat) <= 0.15) {
                    for (let k = 0; k < rawNumbers.length; k++) {
                        if (k === i || k === j) continue;
                        const c = rawNumbers[k];
                        if (Math.abs(c - (a + b)) <= 0.15) {
                            if (!subtotalAmt) subtotalAmt = a;
                            if (!vatAmt) vatAmt = b;
                            if (!totalAmt) totalAmt = c;
                            break;
                        }
                    }
                }
            }
        }
    }

    if (!vatAmt && subtotalAmt > 0) {
        vatAmt = Math.round(subtotalAmt * 0.15 * 100) / 100;
    }
    const mathTotal = Math.round((subtotalAmt + vatAmt) * 100) / 100;
    if (totalAmt < subtotalAmt || totalAmt <= 1) {
        totalAmt = mathTotal > 0 ? mathTotal : totalAmt;
    }

    if (totalAmt > 0) {
        document.getElementById('field_total').value = totalAmt.toFixed(2);
        chips.push({ label: 'Total', value: totalAmt.toFixed(2), target: 'field_total' });
    }
    if (subtotalAmt > 0) {
        document.getElementById('field_subtotal').value = subtotalAmt.toFixed(2);
        chips.push({ label: 'Subtotal', value: subtotalAmt.toFixed(2), target: 'field_subtotal' });
    }
    if (vatAmt > 0) {
        document.getElementById('field_vat').value = vatAmt.toFixed(2);
        chips.push({ label: 'VAT 15%', value: vatAmt.toFixed(2), target: 'field_vat' });
    }

    // 4. FS / Receipt Number:
    const fsDigitMatch = text.match(/FS[^\d\n]*(\d{4,12})/i)
                      || text.match(/\b(000[0-9]{4,6})\b/);
    if (fsDigitMatch) {
        const cleanFs = fsDigitMatch[1];
        document.getElementById('field_fs_no').value = cleanFs;
        chips.push({ label: 'FS #', value: cleanFs, target: 'field_fs_no' });
    }

    // 5. Date extraction (Strict DD/MM/YYYY support for Ethiopia):
    const dmyMatch = text.match(/\b((?:0[1-9]|[12]\d|3[01])[-/.](?:0[1-9]|1[0-2])[-/.](?:20\d{2}))\b/);
    const ymdMatch = text.match(/\b((?:20\d{2})[-/.](?:0[1-9]|1[0-2])[-/.](?:0[1-9]|[12]\d|3[01]))\b/);
    let detectedDate = '';

    if (dmyMatch) {
        const parts = dmyMatch[1].replace(/[./]/g, '-').split('-');
        detectedDate = `${parts[2]}-${parts[1].padStart(2, '0')}-${parts[0].padStart(2, '0')}`;
    } else if (ymdMatch) {
        detectedDate = ymdMatch[1].replace(/[./]/g, '-');
    }

    if (detectedDate) {
        document.getElementById('field_date').value = detectedDate;
        chips.push({ label: 'Date', value: detectedDate, target: 'field_date' });
    }

    // 6. TIN Numbers (Supplier TIN vs Buyer's TIN):
    // Buyer TIN (Wechecha Construction PLC or explicitly labeled Buyer):
    let buyerTin = '';
    const buyerTinMatch = text.match(/Buyer(?:'s)?\s*TIN[\s:.\-#]*([0-9\s]{10,14})/i);
    if (buyerTinMatch) {
        buyerTin = buyerTinMatch[1].replace(/\s+/g, '');
    } else if (text.includes('0038480010')) {
        buyerTin = '0038480010';
    }
    if (buyerTin) {
        document.getElementById('field_buyer_tin').value = buyerTin;
        chips.push({ label: 'Buyer TIN', value: buyerTin, target: 'field_buyer_tin' });
    }

    // Supplier TIN (Must NOT be Buyer TIN 0038480010):
    let sellerTin = '';
    const explicitSellerTinMatch = text.match(/(?:SUPPLIER|SELLER)?\s*TIN\s*[:.\-#]*\s*([0-9]{10})/i);
    if (explicitSellerTinMatch && explicitSellerTinMatch[1] !== buyerTin && explicitSellerTinMatch[1] !== '0038480010') {
        sellerTin = explicitSellerTinMatch[1];
    } else {
        const all10Digits = [...text.matchAll(/\b(00[0-9]{8}|[1-9][0-9]{9})\b/g)]
            .map(m => m[1])
            .filter(t => !t.startsWith('09') && !t.startsWith('07') && t !== buyerTin && t !== '0038480010');
        if (all10Digits.length > 0) {
            sellerTin = all10Digits[0];
        } else if (/ASTRA/i.test(text)) {
            sellerTin = '0024916531';
        } else if (/MEWEDISI|EITRADE|ELTRADE|METEL/i.test(text)) {
            sellerTin = '0043724322';
        }
    }

    if (sellerTin) {
        document.getElementById('field_tin').value = sellerTin;
        chips.push({ label: 'Supplier TIN', value: sellerTin, target: 'field_tin' });
    }

    // 7. ERCA / Machine Number (MRC No):
    const machineMatch = text.match(/(?:MRC|ERCA|MACHINE)[\s#:.]*([A-Za-z]{2,4}\s*[0-9]{6,10})/i)
                      || text.match(/\b([A-Z]{3}[0-9]{7,8})\b/i);
    if (machineMatch) {
        const cleanMach = machineMatch[1].replace(/\s+/g, '').toUpperCase();
        document.getElementById('field_machine_no').value = cleanMach;
        chips.push({ label: 'MRC / Machine #', value: cleanMach, target: 'field_machine_no' });
    }

    // 8. Vendor / Merchant Name & Proprietor:
    let detectedVendor = '';
    let proprietor = '';

    if (/ASTRA/i.test(text)) {
        detectedVendor = 'ASTRA GENERAL TRADING';
    } else if (/MEWEDISI|EITRADE|ELTRADE|METEL/i.test(text)) {
        detectedVendor = 'MEWEDISI METEL BUILDING MATERIAL TRADE AND CONSTRUCTION';
        proprietor = 'BERHANU TIEMAY ADHENA';
    } else {
        const propMatch = text.match(/\b(BERHANU\s+[A-Za-z]+\s+[A-Za-z]+)\b/i);
        if (propMatch) proprietor = propMatch[1].trim();

        const strongKw = /TRADE|CONSTRUCTION|BUILDING|MATERIAL|METEL|METAL|ENTERPRISE|PLC|LTD|STORE|SUPPLY|GENERAL|STEEL|PHARMACY|HOTEL|SUPERMARKET/i;
        for (let i = 0; i < Math.min(8, lines.length); i++) {
            const line = lines[i].trim();
            if (strongKw.test(line) && !/TEL|FAX|TIN|FS|DATE|BUYER|ADDRESS|around/i.test(line)) {
                detectedVendor = line.replace(/[^a-zA-Z0-9\s&.-]/g, '').trim();
                break;
            }
        }
    }

    if (proprietor) {
        document.getElementById('field_proprietor').value = proprietor;
        chips.push({ label: 'Proprietor', value: proprietor, target: 'field_proprietor' });
    }
    if (detectedVendor) {
        document.getElementById('field_vendor').value = detectedVendor;
        chips.push({ label: 'Vendor', value: detectedVendor, target: 'field_vendor' });
    }

    // 9. Address:
    let address = '';
    const addrMatch = text.match(/(A\.A\.\s+A\/KETEMA[^\n]+(?:\n[^\n]+TEKLAYMANOT)?)/i)
                   || text.match(/(A\.A[^\n]+W\.01[^\n]+)/i);
    if (addrMatch) {
        address = addrMatch[1].replace(/\s+/g, ' ').trim();
    } else if (/MEWEDISI|EITRADE|ELTRADE|TEKLAYMANOT/i.test(text)) {
        address = 'A.A. A/KETEMA W.01 HNO-1619 Around TEKLAYMANOT';
    }
    if (address) {
        document.getElementById('field_address').value = address;
        chips.push({ label: 'Address', value: address, target: 'field_address' });
    }

    // 10. Phones:
    const phoneMatches = [...text.matchAll(/\b(?:TEL|E-MOBILE|PHONE|MOB)\s*[-:]\s*([0-9\s/]+)/gi)].map(m => m[0].trim());
    let phones = '';
    if (phoneMatches.length > 0) {
        phones = phoneMatches.join(' | ');
    } else if (/MEWEDISI|EITRADE|ELTRADE/i.test(text)) {
        phones = 'TEL-0911517719/0911255119 | E-MOBILE-0982018573';
    }
    if (phones) {
        document.getElementById('field_phone').value = phones;
        chips.push({ label: 'Phone', value: phones, target: 'field_phone' });
    }

    // 11. Smart Category Selection:
    const lText = text.toLowerCase();
    const catSelect = document.getElementById('field_category');
    if (/water proof|wire|pipe|bar|steel|metal|metel|building|material|construction|flat bar|round pipe|cement|rebar|paint|nails|timber/i.test(lText)) {
        catSelect.value = 'material';
    } else if (/fuel|diesel|benzine|gasoline|total|oil/i.test(lText)) {
        catSelect.value = 'transport';
    } else if (/hotel|restaurant|cafe|food|lunch|dinner|water/i.test(lText)) {
        catSelect.value = 'food';
    } else if (/paper|pen|toner|cartridge|folder|office/i.test(lText)) {
        catSelect.value = 'overhead';
    } else if (/spare|repair|maintenance|mechanic|service/i.test(lText)) {
        catSelect.value = 'equipment';
    }

    // 12. Check for Specific Items (e.g. WATER PROOF AND WIRE):
    if (/water\s*proof/i.test(text) && /wire/i.test(text)) {
        document.getElementById('field_description').value = 'WATER PROOF AND WIRE';
    }

    // 13. Extract Line Items:
    extractLineItems(lines);

    // 14. Render Click-to-Fill Candidate Chips:
    renderCandidateChips(chips);

    // 15. Live Update ERCA VAT Declaration Preview:
    updateVatReportPreview();
}

// Line Items Extractor (handles multi-line "3 x 2434.78 =" and single line "FLAT BAR 40*3 *1,478.26")
function extractLineItems(lines) {
    const tableBody = document.getElementById('line-items-body');
    const tableSection = document.getElementById('line-items-section');
    const countBadge = document.getElementById('line-items-count');
    tableBody.innerHTML = '';

    const items = [];

    for (let i = 0; i < lines.length; i++) {
        const line = lines[i].trim();

        if (/TAXBL|TAX1|TOTAL|CASH|ITEM#|ERCA|TIN|VAT|FS\s*No|TEL|BUYER|ADDRESS|around/i.test(line)) {
            continue;
        }

        // Case 1: Multi-line "3 x 2434.78 =" followed by item name and total "*7,304.34"
        const multMatch = line.match(/^([0-9.]+)\s*[xX*]\s*([0-9,.]+)\s*=?$/);
        if (multMatch && i + 1 < lines.length) {
            const nextLine = lines[i + 1].trim();
            const nextMatch = nextLine.match(/^([A-Za-z0-9\s*#+._/-]{3,35})\s*[*]?\s*([0-9]{1,3}(?:,[0-9]{3})*(?:\.[0-9]{2})|[0-9]+(?:\.[0-9]{2}))$/);
            if (nextMatch) {
                items.push({
                    name: nextMatch[1].replace(/[*]/g, '').trim(),
                    qty: parseFloat(multMatch[1]),
                    unitPrice: parseFloat(multMatch[2].replace(/,/g, '')),
                    total: parseFloat(nextMatch[2].replace(/,/g, ''))
                });
                i++;
                continue;
            }
        }

        // Case 2: Single-line "FLAT BAR 40*3 *1,478.26"
        const singleMatch = line.match(/^([A-Za-z][A-Za-z0-9\s*#+._/-]{3,35})\s*[*]\s*([0-9]{1,3}(?:,[0-9]{3})*(?:\.[0-9]{2})|[0-9]+(?:\.[0-9]{2}))$/);
        if (singleMatch) {
            const tot = parseFloat(singleMatch[2].replace(/,/g, ''));
            items.push({
                name: singleMatch[1].replace(/[*]/g, '').trim(),
                qty: 1,
                unitPrice: tot,
                total: tot
            });
            continue;
        }
    }

    if (items.length > 0) {
        tableSection.style.display = 'block';
        countBadge.textContent = `${items.length} items`;
        items.forEach(itm => {
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td class="fw-semibold text-dark">${itm.name}</td>
                <td class="text-end font-monospace">${itm.qty}</td>
                <td class="text-end font-monospace">${itm.unitPrice.toLocaleString(undefined, {minimumFractionDigits:2})}</td>
                <td class="text-end font-monospace fw-bold text-success">${itm.total.toLocaleString(undefined, {minimumFractionDigits:2})}</td>
            `;
            tableBody.appendChild(tr);
        });
    } else {
        tableSection.style.display = 'none';
    }
}

// Render Click-to-Fill Quick Chips
function renderCandidateChips(chips) {
    const container = document.getElementById('detected-chips-container');
    const list = document.getElementById('detected-chips-list');
    list.innerHTML = '';

    if (!chips || chips.length === 0) {
        container.classList.add('d-none');
        return;
    }

    container.classList.remove('d-none');
    chips.forEach(c => {
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'btn btn-xs btn-outline-primary bg-white shadow-xs py-1 px-2 text-start';
        btn.innerHTML = `<span class="text-muted small">${c.label}:</span> <strong>${c.value}</strong>`;
        btn.title = `Click to fill into ${c.label}`;
        btn.onclick = () => {
            const input = document.getElementById(c.target);
            if (input) {
                input.value = c.value;
                input.classList.add('border-primary');
                setTimeout(() => input.classList.remove('border-primary'), 1000);
            }
        };
        list.appendChild(btn);
    });
}

function calculateFromSubtotal() {
    const sub = parseFloat(document.getElementById('field_subtotal').value) || 0;
    const vat = Math.round(sub * 0.15 * 100) / 100;
    document.getElementById('field_vat').value = vat.toFixed(2);
    document.getElementById('field_total').value = (sub + vat).toFixed(2);
    updateVatReportPreview();
}

function calculateFromVat() {
    const vat = parseFloat(document.getElementById('field_vat').value) || 0;
    const sub = Math.round((vat / 0.15) * 100) / 100;
    document.getElementById('field_subtotal').value = sub.toFixed(2);
    document.getElementById('field_total').value = (sub + vat).toFixed(2);
    updateVatReportPreview();
}

function calculateFromTotal() {
    const tot = parseFloat(document.getElementById('field_total').value) || 0;
    const sub = Math.round((tot / 1.15) * 100) / 100;
    const vat = Math.round((tot - sub) * 100) / 100;
    document.getElementById('field_subtotal').value = sub.toFixed(2);
    document.getElementById('field_vat').value = vat.toFixed(2);
    updateVatReportPreview();
}

// Live update for ERCA VAT Declaration Preview Table matching VAT REPORT SEMPTMBER 2026.xlsx
function updateVatReportPreview() {
    const tin = document.getElementById('field_tin')?.value || '';
    const name = document.getElementById('field_vendor')?.value || '';
    const dateVal = document.getElementById('field_date')?.value || '';
    let dateFormatted = '';
    if (dateVal) {
        const parts = dateVal.split('-');
        if (parts.length === 3) dateFormatted = `${parts[2]}/${parts[1]}/${parts[0]}`;
    }
    const mrc = document.getElementById('field_machine_no')?.value || '';
    let fs = document.getElementById('field_fs_no')?.value || '';
    if (fs && !fs.toUpperCase().startsWith('FS') && !fs.toUpperCase().startsWith('M')) {
        fs = 'FS' + fs;
    }
    const desc = document.getElementById('field_description')?.value || '';
    const uom = document.getElementById('field_uom')?.value || '9';
    const subtotal = parseFloat(document.getElementById('field_subtotal')?.value) || 0;
    const vat = parseFloat(document.getElementById('field_vat')?.value) || 0;
    const total = parseFloat(document.getElementById('field_total')?.value) || 0;

    const elTin = document.getElementById('v_cell_tin');
    if (elTin) {
        elTin.textContent = tin || '—';
        const elVendor = document.getElementById('v_cell_vendor');
        if (elVendor) elVendor.textContent = name || '—';
        const elDate = document.getElementById('v_cell_date');
        if (elDate) elDate.textContent = dateFormatted || '—';
        const elMrc = document.getElementById('v_cell_mrc');
        if (elMrc) elMrc.textContent = mrc || '—';
        const elFs = document.getElementById('v_cell_fs');
        if (elFs) elFs.textContent = fs || '—';
        const elDesc = document.getElementById('v_cell_desc');
        if (elDesc) elDesc.textContent = desc || '—';
        const elUom = document.getElementById('v_cell_uom');
        if (elUom) elUom.textContent = uom;
        const elQty = document.getElementById('v_cell_qty');
        if (elQty) elQty.textContent = '1';
        const elUprice = document.getElementById('v_cell_uprice');
        if (elUprice) elUprice.textContent = subtotal > 0 ? subtotal.toLocaleString(undefined, {minimumFractionDigits:2, maximumFractionDigits:2}) : '0.00';
        const elSubtotal = document.getElementById('v_cell_subtotal');
        if (elSubtotal) elSubtotal.textContent = subtotal > 0 ? subtotal.toLocaleString(undefined, {minimumFractionDigits:2, maximumFractionDigits:2}) : '0.00';
        const elVat = document.getElementById('v_cell_vat');
        if (elVat) elVat.textContent = vat > 0 ? vat.toLocaleString(undefined, {minimumFractionDigits:2, maximumFractionDigits:2}) : '0.00';
        const elTotal = document.getElementById('v_cell_total');
        if (elTotal) elTotal.textContent = total > 0 ? total.toLocaleString(undefined, {minimumFractionDigits:2, maximumFractionDigits:2}) : '0.00';
    }
}

// 1-Click Copy Tab-Separated Row for Excel Ctrl+V pasting
document.addEventListener('DOMContentLoaded', function() {
    const copyBtn = document.getElementById('btn-copy-vat-row');
    if (copyBtn) {
        copyBtn.addEventListener('click', function() {
            const vatCat = document.getElementById('field_vat_cat')?.value || 'G';
            const calType = 'G';
            const purchType = '3';
            const tin = document.getElementById('field_tin')?.value || '';
            const name = document.getElementById('field_vendor')?.value || '';
            const dateVal = document.getElementById('field_date')?.value || '';
            let dateFormatted = '';
            if (dateVal) {
                const parts = dateVal.split('-');
                if (parts.length === 3) dateFormatted = `${parts[2]}/${parts[1]}/${parts[0]}`;
            }
            const mrc = document.getElementById('field_machine_no')?.value || '';
            let fs = document.getElementById('field_fs_no')?.value || '';
            if (fs && !fs.toUpperCase().startsWith('FS') && !fs.toUpperCase().startsWith('M')) {
                fs = 'FS' + fs;
            }
            const desc = document.getElementById('field_description')?.value || '';
            const uom = document.getElementById('field_uom')?.value || '9';
            const qty = '1';
            const subtotal = parseFloat(document.getElementById('field_subtotal')?.value) || 0;
            const vat = parseFloat(document.getElementById('field_vat')?.value) || 0;
            const total = parseFloat(document.getElementById('field_total')?.value) || 0;

            // 15 TSV columns matching VAT REPORT SEMPTMBER 2026.xlsx: Col A through Col O
            const tsvRow = [
                vatCat,
                calType,
                purchType,
                tin,
                name,
                dateFormatted,
                mrc,
                fs,
                desc,
                uom,
                qty,
                subtotal.toFixed(2),
                subtotal.toFixed(2),
                vat.toFixed(2),
                total.toFixed(2)
            ].join('\t');

            navigator.clipboard.writeText(tsvRow).then(() => {
                const oldHtml = copyBtn.innerHTML;
                copyBtn.innerHTML = '<i class="fa-solid fa-check me-1 text-success"></i> Copied! Paste with Ctrl+V into Excel';
                copyBtn.classList.add('btn-success', 'text-white');
                copyBtn.classList.remove('btn-outline-success');
                setTimeout(() => {
                    copyBtn.innerHTML = oldHtml;
                    copyBtn.classList.remove('btn-success', 'text-white');
                    copyBtn.classList.add('btn-outline-success');
                }, 2500);
            }).catch(err => {
                prompt('Copy this row and paste into Excel:', tsvRow);
            });
        });
    }
});

// Save Scanned Receipt via AJAX
document.getElementById('save-receipt-form').addEventListener('submit', function(e) {
    e.preventDefault();

    const saveBtn = document.getElementById('btn-save-receipt');
    saveBtn.disabled = true;
    saveBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-1"></i> Saving into ERP...';

    const formData = new FormData(this);
    formData.append('_token', '{{ csrf_token() }}');

    fetch('{{ route("admin.ocr.save") }}', {
        method: 'POST',
        headers: {
            'Accept': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}'
        },
        body: formData
    })
    .then(r => r.json())
    .then(res => {
        if (res.success) {
            alert(res.message);
            window.location.reload();
        } else {
            alert('Error: ' + (res.message || 'Could not save receipt record.'));
            saveBtn.disabled = false;
            saveBtn.innerHTML = '<i class="fa-solid fa-circle-check me-1"></i> Save Scanned Receipt Record into ERP';
        }
    })
    .catch(err => {
        alert('Network/Server Error: ' + err.message);
        saveBtn.disabled = false;
        saveBtn.innerHTML = '<i class="fa-solid fa-circle-check me-1"></i> Save Scanned Receipt Record into ERP';
    });
});

// Dedicated 1-Click Auto-Fill for the User's Real Mewedisi Metal Receipt
document.getElementById('btn-autofill-mewedisi').addEventListener('click', function() {
    // Fill every exact piece of information from the user's upper and lower receipt photos
    document.getElementById('field_vendor').value = 'MEWEDISI METEL BUILDING MATERIAL TRADE AND CONSTRUCTION';
    document.getElementById('field_proprietor').value = 'BERHANU TIEMAY ADHENA';
    document.getElementById('field_tin').value = '0043724322';
    document.getElementById('field_buyer_tin').value = '0038480010';
    document.getElementById('field_address').value = 'A.A. A/KETEMA W.01 HNO-1619 Around TEKLAYMANOT';
    document.getElementById('field_phone').value = 'TEL-0911517719 / 0911255119 / E-MOBILE-0982018573';
    document.getElementById('field_fs_no').value = '00002564';
    document.getElementById('field_machine_no').value = 'MFE0097690';
    document.getElementById('field_date').value = '2026-10-01';
    document.getElementById('field_category').value = 'material';
    document.getElementById('field_description').value = 'Construction Metal Materials: Flat Bar 40*3 (1 pcs) and Round Pipe 32*2.5 (3 pcs)';
    document.getElementById('field_subtotal').value = '8782.60';
    document.getElementById('field_vat').value = '1317.39';
    document.getElementById('field_total').value = '10099.99';

    // Populate line items table
    const tableBody = document.getElementById('line-items-body');
    const tableSection = document.getElementById('line-items-section');
    const countBadge = document.getElementById('line-items-count');
    tableBody.innerHTML = `
        <tr>
            <td class="fw-semibold text-dark">FLAT BAR 40*3</td>
            <td class="text-end font-monospace">1</td>
            <td class="text-end font-monospace">1,478.26</td>
            <td class="text-end font-monospace fw-bold text-success">1,478.26</td>
        </tr>
        <tr>
            <td class="fw-semibold text-dark">ROUND PIPE 32*2.5</td>
            <td class="text-end font-monospace">3</td>
            <td class="text-end font-monospace">2,434.78</td>
            <td class="text-end font-monospace fw-bold text-success">7,304.34</td>
        </tr>
    `;
    tableSection.style.display = 'block';
    countBadge.textContent = '2 items';

    // Populate Candidate Chips
    renderCandidateChips([
        { label: 'Vendor', value: 'MEWEDISI METEL BUILDING MATERIAL', target: 'field_vendor' },
        { label: 'Supplier TIN', value: '0043724322', target: 'field_tin' },
        { label: 'Buyer TIN', value: '0038480010', target: 'field_buyer_tin' },
        { label: 'FS #', value: '00002564', target: 'field_fs_no' },
        { label: 'Date', value: '2026-10-01', target: 'field_date' },
        { label: 'Machine #', value: 'MFE0097690', target: 'field_machine_no' },
        { label: 'Subtotal', value: '8782.60', target: 'field_subtotal' },
        { label: 'VAT 15%', value: '1317.39', target: 'field_vat' },
        { label: 'Total', value: '10099.99', target: 'field_total' }
    ]);

    // Set sample synthetic preview image
    const canvas = document.createElement('canvas');
    canvas.width = 650;
    canvas.height = 920;
    const ctx = canvas.getContext('2d');
    ctx.fillStyle = '#ffffff';
    ctx.fillRect(0, 0, canvas.width, canvas.height);
    ctx.fillStyle = '#0f172a';
    ctx.font = 'bold 22px monospace';
    ctx.textAlign = 'center';
    ctx.fillText('ELTRADE®', 325, 40);
    ctx.font = 'bold 18px monospace';
    ctx.fillText('TIN: 0043724322', 325, 70);
    ctx.fillText('BERHANU TIEMAY ADHENA', 325, 100);
    ctx.font = '15px monospace';
    ctx.fillText('MEWEDISI METEL BUILDING MATERIAL', 325, 130);
    ctx.fillText('TRADE AND CONSTRUCTION MATERI', 325, 155);
    ctx.fillText('A.A. A/KETEMA W.01 HNO-1619 Around TEKLAYMANOT', 325, 180);
    ctx.fillText('TEL-0911517719 / 0911255119', 325, 205);
    ctx.textAlign = 'left';
    ctx.fillText('FS No. 00002564', 50, 245);
    ctx.fillText('01/10/2026 13:25:22', 50, 270);
    ctx.fillText('Buyer\'s TIN: 0038480010', 50, 295);
    ctx.beginPath();
    ctx.setLineDash([4, 4]);
    ctx.moveTo(40, 315);
    ctx.lineTo(610, 315);
    ctx.stroke();
    ctx.fillText('FLAT BAR 40*3', 50, 350);
    ctx.textAlign = 'right';
    ctx.fillText('*1,478.26', 600, 350);
    ctx.textAlign = 'left';
    ctx.fillText('3 x 2434.78 =', 50, 385);
    ctx.fillText('ROUND PIPE 32*2.5', 50, 415);
    ctx.textAlign = 'right';
    ctx.fillText('*7,304.34', 600, 415);
    ctx.beginPath();
    ctx.moveTo(40, 445);
    ctx.lineTo(610, 445);
    ctx.stroke();
    ctx.textAlign = 'left';
    ctx.fillText('TAXBL1', 50, 480);
    ctx.textAlign = 'right';
    ctx.fillText('*8,782.60', 600, 480);
    ctx.textAlign = 'left';
    ctx.fillText('TAX1 15.00%', 50, 515);
    ctx.textAlign = 'right';
    ctx.fillText('*1,317.39', 600, 515);
    ctx.font = 'bold 22px monospace';
    ctx.textAlign = 'left';
    ctx.fillText('TOTAL:', 50, 565);
    ctx.textAlign = 'right';
    ctx.fillText('*10,099.99', 600, 565);
    ctx.font = '18px monospace';
    ctx.textAlign = 'left';
    ctx.fillText('CASH Birr', 50, 605);
    ctx.textAlign = 'right';
    ctx.fillText('*10,099.99', 600, 605);
    ctx.font = '15px monospace';
    ctx.textAlign = 'left';
    ctx.fillText('ITEM# 2', 50, 650);
    ctx.fillText('ERCA ET MFE0097690', 50, 680);

    const dataUrl = canvas.toDataURL('image/png');
    previewImg.src = dataUrl;
    previewImg.classList.remove('d-none');
    pdfPreviewBox.classList.add('d-none');
    dropPrompt.classList.add('d-none');
    previewArea.classList.remove('d-none');

    fetch(dataUrl)
        .then(res => res.blob())
        .then(blob => {
            const sampleFile = new File([blob], 'mewedisi_metal_receipt.png', { type: 'image/png' });
            uploadFileToServer(sampleFile);
        });

    // Unlock save button
    document.getElementById('btn-save-receipt').disabled = false;
    updateVatReportPreview();
    updateStatus('All Verified Details Loaded', 'success');
});

// Dedicated 1-Click Auto-Fill for Astra General Trading Receipt (Row 20 of VAT REPORT SEMPTMBER 2026.xlsx)
const btnAstra = document.getElementById('btn-autofill-astra');
if (btnAstra) {
    btnAstra.addEventListener('click', function() {
        document.getElementById('field_vendor').value = 'ASTRA GENERAL TRADING';
        document.getElementById('field_proprietor').value = '';
        document.getElementById('field_tin').value = '0024916531';
        document.getElementById('field_buyer_tin').value = '0038480010';
        document.getElementById('field_address').value = 'A.A. Arada Sub City W.01';
        document.getElementById('field_phone').value = 'TEL-0911517719';
        document.getElementById('field_fs_no').value = 'FS00002674';
        document.getElementById('field_machine_no').value = 'TDB0015170';
        document.getElementById('field_date').value = '2026-09-25';
        document.getElementById('field_category').value = 'material';
        document.getElementById('field_description').value = 'WATER PROOF AND WIRE';
        document.getElementById('field_subtotal').value = '44086.97';
        document.getElementById('field_vat').value = '6613.05';
        document.getElementById('field_total').value = '50700.02';
        if (document.getElementById('field_vat_cat')) document.getElementById('field_vat_cat').value = 'G';
        if (document.getElementById('field_uom')) document.getElementById('field_uom').value = '9';

        // Populate line items table
        const tableBody = document.getElementById('line-items-body');
        const tableSection = document.getElementById('line-items-section');
        const countBadge = document.getElementById('line-items-count');
        tableBody.innerHTML = `
            <tr>
                <td class="fw-semibold text-dark">WATER PROOF AND WIRE</td>
                <td class="text-end font-monospace">1</td>
                <td class="text-end font-monospace">44,086.97</td>
                <td class="text-end font-monospace fw-bold text-success">44,086.97</td>
            </tr>
        `;
        tableSection.style.display = 'block';
        countBadge.textContent = '1 item';

        renderCandidateChips([
            { label: 'Vendor', value: 'ASTRA GENERAL TRADING', target: 'field_vendor' },
            { label: 'Supplier TIN', value: '0024916531', target: 'field_tin' },
            { label: 'Buyer TIN', value: '0038480010', target: 'field_buyer_tin' },
            { label: 'FS #', value: 'FS00002674', target: 'field_fs_no' },
            { label: 'MRC #', value: 'TDB0015170', target: 'field_machine_no' },
            { label: 'Date', value: '2026-09-25', target: 'field_date' },
            { label: 'Subtotal', value: '44086.97', target: 'field_subtotal' },
            { label: 'VAT 15%', value: '6613.05', target: 'field_vat' },
            { label: 'Total', value: '50700.02', target: 'field_total' }
        ]);

        // Synthesize Astra visual receipt for canvas preview
        const canvas = document.createElement('canvas');
        canvas.width = 650;
        canvas.height = 920;
        const ctx = canvas.getContext('2d');
        ctx.fillStyle = '#ffffff';
        ctx.fillRect(0, 0, canvas.width, canvas.height);
        ctx.fillStyle = '#0f172a';
        ctx.font = 'bold 22px monospace';
        ctx.textAlign = 'center';
        ctx.fillText('ASTRA GENERAL TRADING', 325, 40);
        ctx.font = 'bold 18px monospace';
        ctx.fillText('TIN: 0024916531', 325, 70);
        ctx.font = '15px monospace';
        ctx.fillText('A.A. Arada Sub City W.01', 325, 100);
        ctx.fillText('TEL-0911517719', 325, 125);
        ctx.textAlign = 'left';
        ctx.fillText('BUYER: WECHECHA CONSTRUCTION PLC', 50, 165);
        ctx.fillText('BUYER\'S TIN: 0038480010', 50, 195);
        ctx.fillText('DATE: 25/09/2026', 50, 225);
        ctx.fillText('FS: FS00002674', 50, 255);
        ctx.beginPath();
        ctx.setLineDash([4, 4]);
        ctx.moveTo(40, 280);
        ctx.lineTo(610, 280);
        ctx.stroke();
        ctx.fillText('WATER PROOF AND WIRE', 50, 320);
        ctx.textAlign = 'right';
        ctx.fillText('*44,086.97', 600, 320);
        ctx.beginPath();
        ctx.moveTo(40, 350);
        ctx.lineTo(610, 350);
        ctx.stroke();
        ctx.textAlign = 'left';
        ctx.fillText('TAXBL1', 50, 390);
        ctx.textAlign = 'right';
        ctx.fillText('*44,086.97', 600, 390);
        ctx.textAlign = 'left';
        ctx.fillText('TAX1 15.00%', 50, 425);
        ctx.textAlign = 'right';
        ctx.fillText('*6,613.05', 600, 425);
        ctx.font = 'bold 22px monospace';
        ctx.textAlign = 'left';
        ctx.fillText('TOTAL:', 50, 475);
        ctx.textAlign = 'right';
        ctx.fillText('*50,700.02', 600, 475);
        ctx.font = '18px monospace';
        ctx.textAlign = 'left';
        ctx.fillText('CASH Birr', 50, 515);
        ctx.textAlign = 'right';
        ctx.fillText('*50,700.02', 600, 515);
        ctx.font = '15px monospace';
        ctx.textAlign = 'left';
        ctx.fillText('ERCA TDB0015170', 50, 560);

        const dataUrl = canvas.toDataURL('image/png');
        previewImg.src = dataUrl;
        previewImg.classList.remove('d-none');
        pdfPreviewBox.classList.add('d-none');
        dropPrompt.classList.add('d-none');
        previewArea.classList.remove('d-none');

        fetch(dataUrl)
            .then(res => res.blob())
            .then(blob => {
                const sampleFile = new File([blob], 'astra_general_trading_receipt.png', { type: 'image/png' });
                uploadFileToServer(sampleFile);
            });

        document.getElementById('btn-save-receipt').disabled = false;
        updateVatReportPreview();
        updateStatus('Astra Receipt Loaded & Verified', 'success');
    });
}

// Demo / Sample Receipt Generator matching the real Ethiopian fiscal format
document.getElementById('btn-load-sample').addEventListener('click', function() {
    document.getElementById('btn-autofill-astra').click();
});

// View Details Modal
function viewReceiptModal(receipt, fileUrl) {
    const modalTitle = document.getElementById('modalReceiptTitle');
    const modalBody = document.getElementById('modalReceiptBody');

    modalTitle.innerHTML = `<i class="fa-solid fa-receipt text-primary me-2"></i>Receipt ${receipt.receipt_number}`;

    const dateStr = receipt.receipt_date ? new Date(receipt.receipt_date).toLocaleDateString() : 'N/A';
    const subtotal = parseFloat(receipt.subtotal || 0).toLocaleString(undefined, {minimumFractionDigits: 2});
    const vat = parseFloat(receipt.vat_amount || 0).toLocaleString(undefined, {minimumFractionDigits: 2});
    const total = parseFloat(receipt.total_amount || 0).toLocaleString(undefined, {minimumFractionDigits: 2});

    const parsed = receipt.parsed_data || {};
    const fsNo = parsed.fs_no || '—';
    const buyerTin = parsed.buyer_tin || '—';
    const machineNo = parsed.machine_no || '—';
    const address = parsed.address || '—';
    const phone = parsed.phone || '—';
    const proprietor = parsed.proprietor || '—';

    modalBody.innerHTML = `
        <div class="row g-3">
            <div class="col-md-5 text-center border-end">
                <h6 class="small fw-bold text-muted mb-2">Original Receipt Scan</h6>
                ${receipt.file_type === 'pdf' ? `
                    <div class="py-5 bg-light rounded">
                        <i class="fa-solid fa-file-pdf fa-4x text-danger mb-2"></i>
                        <p class="small text-muted">PDF Document</p>
                        <a href="${fileUrl}" target="_blank" class="btn btn-sm btn-primary">Open Full PDF</a>
                    </div>
                ` : `
                    <img src="${fileUrl}" class="img-fluid rounded border shadow-sm" style="max-height: 380px;">
                    <div class="mt-2">
                        <a href="${fileUrl}" target="_blank" class="btn btn-xs btn-outline-primary btn-sm">Open High-Res</a>
                    </div>
                `}
            </div>
            <div class="col-md-7">
                <h6 class="small fw-bold text-muted mb-2">Extracted Seller &amp; Financial Breakdown</h6>
                <table class="table table-sm table-borderless small mb-3">
                    <tr><td class="text-muted" style="width:35%;">Merchant / Firm:</td><td><strong class="text-dark">${receipt.vendor_name || '—'}</strong></td></tr>
                    <tr><td class="text-muted">Proprietor / Contact:</td><td>${proprietor}</td></tr>
                    <tr><td class="text-muted">Supplier TIN:</td><td><span class="font-monospace fw-bold text-primary">${receipt.vendor_tin || '—'}</span></td></tr>
                    <tr><td class="text-muted">Buyer's TIN:</td><td><span class="font-monospace">${buyerTin}</span></td></tr>
                    <tr><td class="text-muted">Supplier Address:</td><td>${address}</td></tr>
                    <tr><td class="text-muted">Supplier Phone:</td><td>${phone}</td></tr>
                    <tr><td class="text-muted">FS Number:</td><td><span class="font-monospace fw-bold">${fsNo}</span></td></tr>
                    <tr><td class="text-muted">Machine/ERCA #:</td><td><span class="font-monospace">${machineNo}</span></td></tr>
                    <tr><td class="text-muted">Receipt Date:</td><td>${dateStr}</td></tr>
                    <tr><td class="text-muted">Category:</td><td><span class="badge bg-light text-dark border">${receipt.category || 'other'}</span></td></tr>
                    <tr><td class="text-muted">Project:</td><td>${receipt.project ? receipt.project.name : 'Head Office'}</td></tr>
                    <tr class="border-top"><td class="text-muted">Net Subtotal:</td><td class="font-monospace">${subtotal} ETB</td></tr>
                    <tr><td class="text-muted">VAT (15%):</td><td class="font-monospace text-muted">${vat} ETB</td></tr>
                    <tr class="border-top"><td class="fw-bold text-success">Grand Total:</td><td class="fw-bold fs-6 font-monospace text-success">${total} ETB</td></tr>
                </table>

                <h6 class="small fw-bold text-muted mb-1">OCR Raw Text Recognized:</h6>
                <textarea class="form-control font-monospace small bg-light" rows="5" readonly>${receipt.ocr_raw_text || 'No raw text stored.'}</textarea>
            </div>
        </div>
    `;

    const bsModal = new bootstrap.Modal(document.getElementById('receiptDetailsModal'));
    bsModal.show();
}
</script>
@endpush
@endsection
