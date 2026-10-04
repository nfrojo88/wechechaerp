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
        <div class="d-flex gap-2">
            <button type="button" class="btn btn-outline-primary btn-sm shadow-xs fw-semibold" id="btn-load-sample">
                <i class="fa-solid fa-wand-magic-sparkles me-1 text-warning"></i>Try Sample Receipt
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
                                <strong>Pro-Tip:</strong> The OCR engine automatically strips asterisks (<code>*</code>), currency symbols, parses Ethiopian 15% VAT, and separates Supplier and Buyer TINs!
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

                                        {{-- Vendor / Merchant --}}
                                        <div class="col-md-7">
                                            <label class="form-label small fw-bold text-dark mb-1">
                                                Merchant / Supplier Name *
                                                <span class="badge bg-light text-muted border ms-1" style="font-size:0.65rem;">Auto-Extracted</span>
                                            </label>
                                            <div class="input-group input-group-sm">
                                                <span class="input-group-text bg-light"><i class="fa-solid fa-store text-muted"></i></span>
                                                <input type="text" class="form-control" id="field_vendor" name="vendor_name" placeholder="e.g. MEWEDISI METEL BUILDING MATERIAL" required>
                                            </div>
                                        </div>

                                        {{-- Supplier TIN --}}
                                        <div class="col-md-5">
                                            <label class="form-label small fw-bold text-dark mb-1">
                                                Supplier TIN # *
                                                <span class="badge bg-light text-muted border ms-1" style="font-size:0.65rem;">Seller</span>
                                            </label>
                                            <div class="input-group input-group-sm">
                                                <span class="input-group-text bg-light"><i class="fa-solid fa-id-card text-muted"></i></span>
                                                <input type="text" class="form-control font-monospace" id="field_tin" name="vendor_tin" placeholder="e.g. 00810724322">
                                            </div>
                                        </div>

                                        {{-- Buyer TIN --}}
                                        <div class="col-md-4">
                                            <label class="form-label small fw-semibold text-muted mb-1">Buyer's TIN</label>
                                            <div class="input-group input-group-sm">
                                                <span class="input-group-text bg-light"><i class="fa-solid fa-user-tag text-muted"></i></span>
                                                <input type="text" class="form-control font-monospace" id="field_buyer_tin" name="buyer_tin" placeholder="e.g. 0038480010">
                                            </div>
                                        </div>

                                        {{-- Receipt / FS Number --}}
                                        <div class="col-md-4">
                                            <label class="form-label small fw-bold text-dark mb-1">FS / Receipt Number *</label>
                                            <div class="input-group input-group-sm">
                                                <span class="input-group-text bg-light"><i class="fa-solid fa-hashtag text-muted"></i></span>
                                                <input type="text" class="form-control font-monospace fw-bold" id="field_fs_no" name="fs_no" placeholder="e.g. 00002564">
                                            </div>
                                        </div>

                                        {{-- Machine / ERCA Number --}}
                                        <div class="col-md-4">
                                            <label class="form-label small fw-semibold text-muted mb-1">ERCA / Machine #</label>
                                            <div class="input-group input-group-sm">
                                                <span class="input-group-text bg-light"><i class="fa-solid fa-cash-register text-muted"></i></span>
                                                <input type="text" class="form-control font-monospace" id="field_machine_no" name="machine_no" placeholder="e.g. MFE0097690">
                                            </div>
                                        </div>

                                        {{-- Receipt Date --}}
                                        <div class="col-md-4">
                                            <label class="form-label small fw-bold text-dark mb-1">Receipt Date *</label>
                                            <div class="input-group input-group-sm">
                                                <span class="input-group-text bg-light"><i class="fa-solid fa-calendar text-muted"></i></span>
                                                <input type="date" class="form-control" id="field_date" name="receipt_date" value="{{ today()->toDateString() }}" required>
                                            </div>
                                        </div>

                                        {{-- Expense Category --}}
                                        <div class="col-md-4">
                                            <label class="form-label small fw-bold text-dark mb-1">Expense Category *</label>
                                            <select class="form-select form-select-sm" id="field_category" name="category" required>
                                                @foreach($categories as $key => $lbl)
                                                    <option value="{{ $key }}" {{ $key == 'material' ? 'selected' : '' }}>{{ $lbl }}</option>
                                                @endforeach
                                            </select>
                                        </div>

                                        {{-- Link to Project --}}
                                        <div class="col-md-4">
                                            <label class="form-label small fw-bold text-dark mb-1">Link to Project</label>
                                            <select class="form-select form-select-sm" id="field_project_id" name="project_id">
                                                <option value="">— Head Office / General —</option>
                                                @foreach($projects as $p)
                                                    <option value="{{ $p->id }}">{{ $p->name }}</option>
                                                @endforeach
                                            </select>
                                        </div>

                                        {{-- Description / Purpose --}}
                                        <div class="col-12">
                                            <label class="form-label small fw-semibold text-muted mb-1">Purpose / Notes</label>
                                            <input type="text" class="form-control form-control-sm" id="field_description" name="description" placeholder="e.g. Metal building materials (Flat bar, Round pipe)">
                                        </div>

                                        {{-- FINANCIALS BOX --}}
                                        <div class="col-12">
                                            <div class="p-3 rounded-3 border bg-light">
                                                <div class="row g-2 align-items-center">
                                                    <div class="col-md-4">
                                                        <label class="form-label small fw-semibold text-muted mb-1">Taxable Subtotal (ETB)</label>
                                                        <input type="number" step="0.01" class="form-control form-control-sm font-monospace text-end" id="field_subtotal" name="subtotal" placeholder="0.00" oninput="calculateFromSubtotal()">
                                                    </div>
                                                    <div class="col-md-4">
                                                        <label class="form-label small fw-semibold text-muted mb-1">VAT Amount 15% (ETB)</label>
                                                        <input type="number" step="0.01" class="form-control form-control-sm font-monospace text-end" id="field_vat" name="vat_amount" placeholder="0.00">
                                                    </div>
                                                    <div class="col-md-4">
                                                        <label class="form-label small fw-bold text-success mb-1">GRAND TOTAL (ETB) *</label>
                                                        <input type="number" step="0.01" class="form-control form-control-sm font-monospace text-end fw-bold fs-6 text-success border-success" id="field_total" name="total_amount" placeholder="0.00" required>
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
    if (previewImg.src && !previewImg.classList.contains('d-none')) {
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
        } else {
            pdfPreviewBox.classList.add('d-none');
            previewImg.classList.remove('d-none');
            previewImg.src = currentImageDataUrl;
        }

        // 1. Upload to server to get permanent storage path
        uploadFileToServer(file);

        // 2. Run OCR Pipeline with image enhancement
        if (!isPdf) {
            previewImg.onload = function() {
                runOcrPipeline(previewImg);
            };
        } else {
            updateStatus('PDF Uploaded - Select receipt image for live character extraction', 'info');
        }
    };
    reader.readAsDataURL(file);
}

function uploadFileToServer(file) {
    const formData = new FormData();
    formData.append('receipt_file', file);
    formData.append('_token', '{{ csrf_token() }}');

    fetch('{{ route("admin.ocr.upload") }}', {
        method: 'POST',
        body: formData,
    })
    .then(r => r.json())
    .then(res => {
        if (res.success) {
            document.getElementById('stored_file_path').value = res.file_path;
        }
    })
    .catch(err => {
        console.warn('Server storage background upload error:', err);
    });
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

    // 1. Grand Total:
    // Matches "TOTAL: *10,099.99" or "CASH Birr *10,099.99" or "TOTAL * 10,099.99" or "GRAND TOTAL *10099.99"
    const totalMatch = text.match(/(?:TOTAL|GRAND\s*TOTAL|CASH\s*(?:Birr|BIRR)?|NET\s*TOTAL|AMOUNT\s*PAID|ጠቅላላ)\s*[:.\-]*\s*[*]?\s*([0-9]{1,3}(?:,[0-9]{3})*(?:\.[0-9]{2})|[0-9]+(?:\.[0-9]{2}))/i);
    let totalAmt = 0;
    if (totalMatch) {
        totalAmt = cleanNum(totalMatch[1]);
        chips.push({ label: 'Total', value: totalAmt.toFixed(2), target: 'field_total' });
    } else {
        // Fallback: search for numbers with decimals and take highest sensible amount
        const allAmounts = [...text.matchAll(/[*]?\s*([0-9]{1,3}(?:,[0-9]{3})*\.[0-9]{2})\b/g)]
            .map(m => cleanNum(m[1]))
            .filter(n => n > 10 && n < 50000000);
        if (allAmounts.length > 0) {
            totalAmt = Math.max(...allAmounts);
            chips.push({ label: 'Total (Estimated)', value: totalAmt.toFixed(2), target: 'field_total' });
        }
    }
    if (totalAmt > 0) {
        document.getElementById('field_total').value = totalAmt.toFixed(2);
    }

    // 2. Subtotal / Taxable:
    // Matches "TAXBL1 *8,782.60" or "TAXABLE *8,782.60" or "SUBTOTAL: *8,782.60"
    const subtotalMatch = text.match(/(?:TAXBL1|TAXABLE|TAXBL|SUBTOTAL|SUB\s*TOTAL|NET\s*AMOUNT)\s*[:.\-]*\s*[*]?\s*([0-9]{1,3}(?:,[0-9]{3})*(?:\.[0-9]{2})|[0-9]+(?:\.[0-9]{2}))/i);
    let subtotalAmt = 0;
    if (subtotalMatch) {
        subtotalAmt = cleanNum(subtotalMatch[1]);
        chips.push({ label: 'Subtotal', value: subtotalAmt.toFixed(2), target: 'field_subtotal' });
    } else if (totalAmt > 0) {
        subtotalAmt = Math.round((totalAmt / 1.15) * 100) / 100;
    }
    if (subtotalAmt > 0) {
        document.getElementById('field_subtotal').value = subtotalAmt.toFixed(2);
    }

    // 3. VAT Amount (15%):
    // Matches "TAX1 15.00% *1,317.39" or "VAT 15% *1,317.39" or "TAX 15.00% *1317.39"
    const vatMatch = text.match(/(?:TAX1\s*15(?:\.00)?%?|TAX\s*15(?:\.00)?%?|VAT\s*15(?:\.00)?%?|ታክስ\s*15%?|VAT\s*AMOUNT|TAX\s*AMOUNT)\s*[:.\-]*\s*[*]?\s*([0-9]{1,3}(?:,[0-9]{3})*(?:\.[0-9]{2})|[0-9]+(?:\.[0-9]{2}))/i);
    let vatAmt = 0;
    if (vatMatch) {
        vatAmt = cleanNum(vatMatch[1]);
        chips.push({ label: 'VAT 15%', value: vatAmt.toFixed(2), target: 'field_vat' });
    } else if (totalAmt > 0 && subtotalAmt > 0) {
        vatAmt = Math.round((totalAmt - subtotalAmt) * 100) / 100;
    }
    if (vatAmt > 0) {
        document.getElementById('field_vat').value = vatAmt.toFixed(2);
    }

    // 4. FS / Receipt Number:
    // Matches "FS No. 00002564", "FS NO: 00002564", "FS #00002564", "Invoice No: 12345"
    const fsMatch = text.match(/(?:FS|INVOICE|RECEIPT|REC|BILL|REF|MEMO)\s*(?:NO\.?|NUMBER|#)?[:.\-\s]*([A-Za-z0-9\-_/]{3,20})/i);
    if (fsMatch) {
        const cleanFs = fsMatch[1].replace(/[^A-Za-z0-9\-_/]/g, '').trim();
        document.getElementById('field_fs_no').value = cleanFs;
        chips.push({ label: 'FS #', value: cleanFs, target: 'field_fs_no' });
    } else {
        // Fallback: 6 to 8 digit number starting with 0000
        const zeroSeqMatch = text.match(/\b(000[0-9]{4,6})\b/);
        if (zeroSeqMatch) {
            document.getElementById('field_fs_no').value = zeroSeqMatch[1];
            chips.push({ label: 'FS #', value: zeroSeqMatch[1], target: 'field_fs_no' });
        }
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

    // 6. TIN Numbers (Distinguish Supplier TIN vs Buyer TIN):
    const buyerTinMatch = text.match(/Buyer(?:'s)?\s*TIN[\s:.\-#]*([0-9]{10})/i);
    let buyerTin = '';
    if (buyerTinMatch) {
        buyerTin = buyerTinMatch[1];
        document.getElementById('field_buyer_tin').value = buyerTin;
        chips.push({ label: 'Buyer TIN', value: buyerTin, target: 'field_buyer_tin' });
    }

    // Supplier TIN: look for explicit TIN or non-phone 10-digit number
    const explicitSellerTinMatch = text.match(/(?:TIN\s*(?:NO\.?|NUMBER)?|የታክስ\s*ከፋይ\s*መለያ\s*ቁ\.?)[\s:.\-#]*([0-9]{10})/i);
    let sellerTin = '';

    if (explicitSellerTinMatch && explicitSellerTinMatch[1] !== buyerTin) {
        sellerTin = explicitSellerTinMatch[1];
    } else {
        // Find 10-digit number that does not start with 09 or 07 (which are mobile phone numbers in Ethiopia)
        const all10Digits = [...text.matchAll(/\b(00[0-9]{8}|[1-9][0-9]{9})\b/g)]
            .map(m => m[1])
            .filter(t => !t.startsWith('09') && !t.startsWith('07') && t !== buyerTin);
        if (all10Digits.length > 0) {
            sellerTin = all10Digits[0];
        }
    }

    if (sellerTin) {
        document.getElementById('field_tin').value = sellerTin;
        chips.push({ label: 'Supplier TIN', value: sellerTin, target: 'field_tin' });
    }

    // 7. ERCA / Machine Number:
    const machineMatch = text.match(/(?:ERCA|MFE)[\s:.\-#]*([A-Za-z0-9]{6,15})/i);
    if (machineMatch) {
        document.getElementById('field_machine_no').value = machineMatch[1];
        chips.push({ label: 'Machine #', value: machineMatch[1], target: 'field_machine_no' });
    }

    // 8. Vendor / Merchant Name:
    const strongKw = /TRADE|CONSTRUCTION|BUILDING|MATERIAL|METEL|METAL|ENTERPRISE|PLC|LTD|STORE|SUPPLY|GENERAL|STEEL|PHARMACY|HOTEL|SUPERMARKET/i;
    let detectedVendor = '';

    for (let i = 0; i < Math.min(8, lines.length); i++) {
        const line = lines[i].trim();
        if (strongKw.test(line) && !/TEL|FAX|TIN|FS|DATE|BUYER|ADDRESS|around/i.test(line)) {
            detectedVendor = line.replace(/[^a-zA-Z0-9\s&.-]/g, '').trim();
            break;
        }
    }
    if (!detectedVendor) {
        for (let i = 0; i < Math.min(4, lines.length); i++) {
            const line = lines[i].trim();
            if (line.length > 4 && !/TEL|TAX|INVOICE|FS|RECEIPT|DATE|CASH|TIN/i.test(line)) {
                detectedVendor = line.replace(/[^a-zA-Z0-9\s&.-]/g, '').trim();
                break;
            }
        }
    }
    if (detectedVendor) {
        document.getElementById('field_vendor').value = detectedVendor;
        chips.push({ label: 'Vendor', value: detectedVendor, target: 'field_vendor' });
    }

    // 9. Smart Category Selection:
    const lText = text.toLowerCase();
    const catSelect = document.getElementById('field_category');
    if (/pipe|bar|steel|metal|metel|building|material|construction|flat bar|round pipe|cement|rebar|paint|nails|timber/i.test(lText)) {
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

    // 10. Extract Line Items:
    extractLineItems(lines);

    // 11. Render Click-to-Fill Candidate Chips:
    renderCandidateChips(chips);
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
}

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

// Demo / Sample Receipt Generator matching the real Ethiopian fiscal format
document.getElementById('btn-load-sample').addEventListener('click', function() {
    resetScanner();
    dropPrompt.classList.add('d-none');
    previewArea.classList.remove('d-none');

    // Create high-resolution synthetic fiscal receipt
    const canvas = document.createElement('canvas');
    canvas.width = 650;
    canvas.height = 920;
    const ctx = canvas.getContext('2d');

    ctx.fillStyle = '#ffffff';
    ctx.fillRect(0, 0, canvas.width, canvas.height);

    ctx.fillStyle = '#0f172a';
    ctx.font = 'bold 22px monospace';
    ctx.textAlign = 'center';
    ctx.fillText('BERHANU TEKLEHAY ADHENA', 325, 50);

    ctx.font = '16px monospace';
    ctx.fillText('MEWEDISI METEL BUILDING MATERIAL', 325, 80);
    ctx.fillText('TRADE AND CONSTRUCTION MATERI', 325, 105);
    ctx.fillText('A.A. A/KETEMA W.01 HNO-1619 Around TEKLAYMANOT', 325, 130);
    ctx.fillText('TEL-0911517719 / 0911255119', 325, 155);

    ctx.textAlign = 'left';
    ctx.fillText('TIN No. : 00810724322', 50, 195);
    ctx.fillText('VAT Reg. No. 8432120', 50, 220);
    ctx.fillText('Buyer\'s TIN: 0038480010', 50, 245);

    ctx.fillText('FS No. 00002564', 50, 280);
    ctx.fillText('01/10/2026 13:25:22', 50, 305);

    ctx.beginPath();
    ctx.setLineDash([4, 4]);
    ctx.moveTo(40, 325);
    ctx.lineTo(610, 325);
    ctx.stroke();

    ctx.fillText('FLAT BAR 40*3', 50, 360);
    ctx.textAlign = 'right';
    ctx.fillText('*1,478.26', 600, 360);

    ctx.textAlign = 'left';
    ctx.fillText('3 x 2434.78 =', 50, 395);
    ctx.fillText('ROUND PIPE 32*2.5', 50, 425);
    ctx.textAlign = 'right';
    ctx.fillText('*7,304.34', 600, 425);

    ctx.beginPath();
    ctx.moveTo(40, 455);
    ctx.lineTo(610, 455);
    ctx.stroke();

    ctx.textAlign = 'left';
    ctx.fillText('TAXBL1', 50, 490);
    ctx.textAlign = 'right';
    ctx.fillText('*8,782.60', 600, 490);

    ctx.textAlign = 'left';
    ctx.fillText('TAX1 15.00%', 50, 525);
    ctx.textAlign = 'right';
    ctx.fillText('*1,317.39', 600, 525);

    ctx.font = 'bold 22px monospace';
    ctx.textAlign = 'left';
    ctx.fillText('TOTAL:', 50, 575);
    ctx.textAlign = 'right';
    ctx.fillText('*10,099.99', 600, 575);

    ctx.font = '18px monospace';
    ctx.textAlign = 'left';
    ctx.fillText('CASH Birr', 50, 615);
    ctx.textAlign = 'right';
    ctx.fillText('*10,099.99', 600, 615);

    ctx.font = '15px monospace';
    ctx.textAlign = 'left';
    ctx.fillText('ITEM# 2', 50, 660);
    ctx.fillText('ERCA MFE0097690', 50, 690);

    ctx.textAlign = 'center';
    ctx.fillText('THANK YOU FOR YOUR BUSINESS!', 325, 760);

    const dataUrl = canvas.toDataURL('image/png');
    previewImg.src = dataUrl;
    previewImg.classList.remove('d-none');
    pdfPreviewBox.classList.add('d-none');

    fetch(dataUrl)
        .then(res => res.blob())
        .then(blob => {
            const sampleFile = new File([blob], 'mewedisi_fiscal_sample.png', { type: 'image/png' });
            uploadFileToServer(sampleFile);
            runOcrPipeline(previewImg);
        });
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

    modalBody.innerHTML = `
        <div class="row g-3">
            <div class="col-md-6 text-center border-end">
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
            <div class="col-md-6">
                <h6 class="small fw-bold text-muted mb-2">Extracted Financial Breakdown</h6>
                <table class="table table-sm table-borderless small mb-3">
                    <tr><td class="text-muted" style="width:40%;">Merchant:</td><td><strong class="text-dark">${receipt.vendor_name || '—'}</strong></td></tr>
                    <tr><td class="text-muted">Supplier TIN:</td><td><span class="font-monospace">${receipt.vendor_tin || '—'}</span></td></tr>
                    <tr><td class="text-muted">Buyer's TIN:</td><td><span class="font-monospace">${buyerTin}</span></td></tr>
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
                <textarea class="form-control font-monospace small bg-light" rows="6" readonly>${receipt.ocr_raw_text || 'No raw text stored.'}</textarea>
            </div>
        </div>
    `;

    const bsModal = new bootstrap.Modal(document.getElementById('receiptDetailsModal'));
    bsModal.show();
}
</script>
@endpush
@endsection
