@extends('layouts.app')

@section('title', 'Receipt OCR Studio & ERCA VAT Declaration - Global Admin')

@section('content')
<div class="container-fluid py-3 px-md-4">

    {{-- TOP BAR --}}
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <div>
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <h3 class="mb-0 fw-bold text-dark">
                    <i class="fa-solid fa-receipt text-success me-2"></i>Receipt OCR Studio
                </h3>
                <span class="badge bg-success text-white px-2.5 py-1.5 rounded-pill shadow-xs">
                    <i class="fa-solid fa-shield-halved me-1"></i>ERCA VAT Line 100
                </span>
                <span class="badge bg-primary text-white px-2.5 py-1.5 rounded-pill shadow-xs" id="engine-status-badge">
                    <i class="fa-solid fa-bolt me-1"></i>Dual OCR (Gemini + OCR.Space)
                </span>
            </div>
            <p class="text-muted small mb-0 mt-1">
                Scan multiple physical receipts or PDFs at once. Multi-item receipts create one row per item with exact 15-column ERCA declaration.
            </p>
        </div>

        <div class="d-flex gap-2 flex-wrap align-items-center">
            {{-- AI Key Settings Modal Trigger --}}
            <button type="button" class="btn btn-outline-dark btn-sm shadow-xs fw-bold" data-bs-toggle="modal" data-bs-target="#settingsModal">
                <i class="fa-solid fa-key me-1 text-warning"></i>AI Key &amp; OCR Settings
            </button>

            {{-- Save All Unsaved Rows --}}
            <button type="button" class="btn btn-warning btn-sm shadow-xs fw-bold d-none" id="btn-save-all">
                <i class="fa-solid fa-floppy-disk me-1"></i>Save All (<span id="unsaved-count">0</span>)
            </button>

            {{-- Export Buttons --}}
            <div class="btn-group shadow-xs">
                <button type="button" class="btn btn-success btn-sm fw-bold dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="fa-solid fa-file-excel me-1"></i>Export Excel (.xlsx)
                </button>
                <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                    <li>
                        <a class="dropdown-item small" href="#" onclick="exportData('excel', 'all'); return false;">
                            <i class="fa-solid fa-table-cells me-2 text-success"></i>Export All Rows
                        </a>
                    </li>
                    <li>
                        <a class="dropdown-item small" href="#" onclick="exportData('excel', 'filtered'); return false;">
                            <i class="fa-solid fa-filter me-2 text-primary"></i>Export Current Filtered View
                        </a>
                    </li>
                    <li>
                        <a class="dropdown-item small" href="#" id="export-selected-excel-btn" onclick="exportData('excel', 'selected'); return false;">
                            <i class="fa-solid fa-check-square me-2 text-info"></i>Export Selected Rows (<span class="selected-count-badge">0</span>)
                        </a>
                    </li>
                </ul>
            </div>

            <div class="btn-group shadow-xs">
                <button type="button" class="btn btn-outline-success btn-sm fw-bold dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="fa-solid fa-file-csv me-1"></i>Export CSV
                </button>
                <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                    <li>
                        <a class="dropdown-item small" href="#" onclick="exportData('csv', 'all'); return false;">
                            <i class="fa-solid fa-file-csv me-2 text-success"></i>Export All as CSV (UTF-8 BOM)
                        </a>
                    </li>
                    <li>
                        <a class="dropdown-item small" href="#" onclick="exportData('csv', 'filtered'); return false;">
                            <i class="fa-solid fa-filter me-2 text-primary"></i>Export Filtered as CSV
                        </a>
                    </li>
                    <li>
                        <a class="dropdown-item small" href="#" id="export-selected-csv-btn" onclick="exportData('csv', 'selected'); return false;">
                            <i class="fa-solid fa-check-square me-2 text-info"></i>Export Selected as CSV (<span class="selected-count-badge">0</span>)
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </div>

    {{-- STATS CARDS --}}
    <div class="row g-3 mb-3">
        <div class="col-6 col-lg-3">
            <div class="card border-0 shadow-sm rounded-3 h-100 bg-white">
                <div class="card-body p-3 d-flex align-items-center gap-3">
                    <div class="rounded-circle p-3 d-flex align-items-center justify-content-center bg-primary bg-opacity-10 text-primary">
                        <i class="fa-solid fa-table-list fa-xl"></i>
                    </div>
                    <div>
                        <div class="fs-4 fw-bold text-dark" id="stat-total-items">{{ number_format($stats['total_items']) }}</div>
                        <div class="text-muted small">Total Extracted Item Rows</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card border-0 shadow-sm rounded-3 h-100 bg-white">
                <div class="card-body p-3 d-flex align-items-center gap-3">
                    <div class="rounded-circle p-3 d-flex align-items-center justify-content-center bg-success bg-opacity-10 text-success">
                        <i class="fa-solid fa-coins fa-xl"></i>
                    </div>
                    <div>
                        <div class="fs-4 fw-bold text-success" id="stat-total-value">{{ number_format($stats['total_value'], 2) }} <small class="fs-6 text-muted">ETB</small></div>
                        <div class="text-muted small">Total Taxable Value (Col M)</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card border-0 shadow-sm rounded-3 h-100 bg-white">
                <div class="card-body p-3 d-flex align-items-center gap-3">
                    <div class="rounded-circle p-3 d-flex align-items-center justify-content-center bg-info bg-opacity-10 text-info">
                        <i class="fa-solid fa-percent fa-xl"></i>
                    </div>
                    <div>
                        <div class="fs-4 fw-bold text-info" id="stat-total-vat">{{ number_format($stats['total_vat'], 2) }} <small class="fs-6 text-muted">ETB</small></div>
                        <div class="text-muted small">Total VAT Extracted 15% (Col N)</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card border-0 shadow-sm rounded-3 h-100 bg-white">
                <div class="card-body p-3 d-flex align-items-center gap-3">
                    <div class="rounded-circle p-3 d-flex align-items-center justify-content-center {{ $stats['flagged_count'] > 0 ? 'bg-warning bg-opacity-10 text-warning' : 'bg-light text-muted' }}">
                        <i class="fa-solid fa-triangle-exclamation fa-xl"></i>
                    </div>
                    <div>
                        <div class="fs-4 fw-bold {{ $stats['flagged_count'] > 0 ? 'text-warning' : 'text-dark' }}" id="stat-flagged-count">{{ number_format($stats['flagged_count']) }}</div>
                        <div class="text-muted small">Items Needing Review</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- MAIN SCANNER & UPLOAD STUDIO (WITH LIVE PREVIEW AND ADD BUTTON) --}}
    <div class="card border-0 shadow-sm rounded-3 mb-4 overflow-hidden">
        <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div class="d-flex align-items-center gap-2">
                <span class="p-2 rounded bg-success bg-opacity-10 text-success">
                    <i class="fa-solid fa-camera-viewfinder"></i>
                </span>
                <strong class="text-dark">Live Receipt Upload &amp; Optical Character Recognition (OCR)</strong>
            </div>
            <div class="d-flex align-items-center gap-2">
                <span class="badge bg-light text-muted border small">Accepts Multiple Images (JPG, PNG, WEBP) &amp; PDFs</span>
                <span class="badge bg-primary text-white small" id="ocr-status-badge">
                    <i class="fa-solid fa-circle me-1" style="font-size:0.55rem;"></i>Ready to scan
                </span>
            </div>
        </div>

        <div class="card-body p-3 p-md-4">
            <div class="row g-4">

                {{-- Left Column: Receipt Dropzone & Live Image Preview with Action Buttons --}}
                <div class="col-lg-5">
                    <div class="p-3 border rounded-3 bg-light h-100 d-flex flex-column">

                        {{-- DROP ZONE & PREVIEW BOX (WITH GREEN BORDER) --}}
                        <div id="drop-zone" class="border border-2 border-dashed rounded-3 p-3 text-center position-relative bg-white shadow-xs transition-all"
                             style="border-color:#10b981 !important; min-height: 280px; display: flex; flex-direction: column; justify-content: center; align-items: center;">
                            <input type="file" id="file-input" multiple accept="image/jpeg,image/png,image/webp,application/pdf" class="position-absolute top-0 start-0 w-100 h-100 opacity-0 cursor-pointer" style="z-index:5;">

                            {{-- Drop Prompt when empty --}}
                            <div id="drop-prompt" class="py-3">
                                <div class="rounded-circle bg-success bg-opacity-10 text-success p-3 d-inline-flex mb-3">
                                    <i class="fa-solid fa-cloud-arrow-up fa-2x"></i>
                                </div>
                                <h6 class="fw-bold text-dark mb-1">Drag &amp; Drop Receipt(s) or PDF Here</h6>
                                <p class="text-muted small mb-3">Single receipt or 10+ files at once (JPEG, PNG, WEBP, PDF)</p>
                                
                                <div class="d-flex justify-content-center gap-2">
                                    <button type="button" class="btn btn-sm btn-success px-3 fw-semibold shadow-xs" onclick="document.getElementById('file-input').click()">
                                        <i class="fa-solid fa-folder-open me-1"></i>Browse Files
                                    </button>
                                    <label class="btn btn-sm btn-outline-dark px-3 fw-semibold shadow-xs mb-0 cursor-pointer">
                                        <i class="fa-solid fa-camera me-1"></i>Snap Photo
                                        <input type="file" id="camera-input" accept="image/*" capture="environment" class="d-none">
                                    </label>
                                </div>
                            </div>

                            {{-- PREVIEW AREA WHEN FILE IS SELECTED / SCANNED --}}
                            <div id="preview-area" class="d-none w-100 text-center">
                                <div class="position-relative d-inline-block w-100">
                                    <img id="receipt-preview-img" src="" alt="Receipt Preview" 
                                         class="img-fluid rounded border shadow-sm transition-all" 
                                         style="max-height: 380px; object-fit: contain; width: auto; background:#fff; transform-origin: center center; transform: rotate(0deg);">
                                    <div id="pdf-preview-box" class="d-none py-5">
                                        <i class="fa-solid fa-file-pdf fa-4x text-danger mb-2"></i>
                                        <div class="fw-bold text-dark" id="pdf-filename">PDF Document</div>
                                    </div>
                                </div>

                                {{-- ACTION BUTTONS ROW: ADD BUTTON, ROTATE, RE-SCAN, CLEAR --}}
                                <div class="d-flex justify-content-center align-items-center gap-2 mt-3 flex-wrap" id="preview-actions-bar">
                                    {{-- THE ADD BUTTON REQUESTED BY USER --}}
                                    <button type="button" class="btn btn-success btn-sm fw-bold shadow-xs px-3" id="btn-add-to-table" title="Add this scanned receipt to the table and save to database">
                                        <i class="fa-solid fa-plus-circle me-1"></i>Add to Table
                                    </button>
                                    <button type="button" class="btn btn-outline-secondary btn-sm" id="btn-rotate-img" title="Rotate 90°">
                                        <i class="fa-solid fa-rotate-right me-1"></i>Rotate
                                    </button>
                                    <button type="button" class="btn btn-outline-primary btn-sm" id="btn-reprocess-img" title="Re-scan OCR">
                                        <i class="fa-solid fa-bolt me-1"></i>Re-Scan
                                    </button>
                                    <button type="button" class="btn btn-outline-danger btn-sm" id="btn-clear-img" title="Clear Receipt">
                                        <i class="fa-solid fa-trash me-1"></i>Clear
                                    </button>
                                </div>
                            </div>
                        </div>

                        {{-- OCR PROGRESS INDICATOR (WITH "Scan Complete! 100%") --}}
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

                        {{-- Batch Queue Container if multiple files are selected --}}
                        <div id="batch-progress-container" class="mt-3 d-none">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <small class="fw-bold text-dark" id="batch-progress-label">
                                    <i class="fa-solid fa-spinner fa-spin me-1 text-primary"></i>Parallel Queue: <span id="batch-count-done">0</span>/<span id="batch-count-total">0</span> completed
                                </small>
                                <small class="fw-bold text-primary font-monospace" id="batch-progress-pct">0%</small>
                            </div>
                            <div class="progress" style="height: 6px;">
                                <div id="batch-progress-bar" class="progress-bar progress-bar-striped progress-bar-animated bg-success" style="width: 0%"></div>
                            </div>
                        </div>

                        <div id="queue-container" class="row g-2 mt-2 d-none" style="max-height: 220px; overflow-y: auto;"></div>

                        <div class="mt-auto pt-3">
                            <div class="alert alert-light border small text-muted mb-0 py-2">
                                <i class="fa-solid fa-lightbulb text-warning me-1"></i>
                                <strong>Automatic Dual OCR:</strong> Gemini Multimodal AI extracts all fields and line items. If Gemini is unavailable, OCR.Space automatically takes over.
                            </div>
                        </div>

                    </div>
                </div>

                {{-- Right Column: Extracted Receipt Fields & Direct Add Action --}}
                <div class="col-lg-7">
                    <div class="p-3 border rounded-3 bg-white h-100 d-flex flex-column">

                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <ul class="nav nav-tabs nav-tabs-bordered mb-0" id="ocrTabs" role="tablist">
                                <li class="nav-item">
                                    <button class="nav-link active fw-bold small" id="extracted-tab" data-bs-toggle="tab" data-bs-target="#tab-extracted" type="button">
                                        <i class="fa-solid fa-list-check me-1 text-success"></i>Extracted Receipt Fields
                                    </button>
                                </li>
                                <li class="nav-item">
                                    <button class="nav-link fw-bold small" id="rawtext-tab" data-bs-toggle="tab" data-bs-target="#tab-rawtext" type="button">
                                        <i class="fa-solid fa-font me-1 text-primary"></i>Raw OCR Text
                                    </button>
                                </li>
                            </ul>
                            <button type="button" class="btn btn-sm btn-success fw-bold shadow-xs px-3" id="btn-add-form-to-table">
                                <i class="fa-solid fa-plus-circle me-1"></i>Add to Table
                            </button>
                        </div>

                        <div class="tab-content flex-grow-1" id="ocrTabsContent">

                            {{-- Tab 1: Extracted Fields Form --}}
                            <div class="tab-pane fade show active" id="tab-extracted">
                                <form id="save-receipt-form" onsubmit="event.preventDefault(); document.getElementById('btn-add-to-table').click();">
                                    <input type="hidden" id="stored_file_path" name="file_path" value="">
                                    <input type="hidden" id="ocr_raw_text_hidden" name="ocr_raw_text" value="">
                                    <input type="hidden" id="ocr_engine_hidden" name="engine" value="gemini">
                                    <input type="hidden" id="ocr_confidence_hidden" name="confidence" value="high">

                                    <div class="row g-2">
                                        {{-- Seller Name --}}
                                        <div class="col-md-7">
                                            <label class="form-label small fw-bold text-dark mb-1">
                                                Merchant / Supplier Name *
                                                <span class="badge bg-primary bg-opacity-10 text-primary border ms-1" style="font-size:0.65rem;">Col E</span>
                                            </label>
                                            <div class="input-group input-group-sm">
                                                <span class="input-group-text bg-light"><i class="fa-solid fa-store text-muted"></i></span>
                                                <input type="text" class="form-control fw-semibold" id="field_vendor" name="vendor_name" placeholder="e.g. HAST ENTERPRISE" required>
                                            </div>
                                        </div>

                                        {{-- Supplier TIN --}}
                                        <div class="col-md-5">
                                            <label class="form-label small fw-bold text-dark mb-1">
                                                Supplier TIN *
                                                <span class="badge bg-success bg-opacity-10 text-success border ms-1" style="font-size:0.65rem;">Col D (10 digits)</span>
                                            </label>
                                            <div class="input-group input-group-sm">
                                                <span class="input-group-text bg-light"><i class="fa-solid fa-id-card text-muted"></i></span>
                                                <input type="text" class="form-control font-monospace fw-bold text-primary" id="field_tin" name="vendor_tin" placeholder="e.g. 0000005201" required>
                                            </div>
                                        </div>

                                        {{-- FS No --}}
                                        <div class="col-md-4">
                                            <label class="form-label small fw-bold text-dark mb-1">
                                                FS / Receipt # *
                                                <span class="badge bg-primary bg-opacity-10 text-primary border ms-1" style="font-size:0.65rem;">Col H</span>
                                            </label>
                                            <div class="input-group input-group-sm">
                                                <span class="input-group-text bg-light"><i class="fa-solid fa-hashtag text-muted"></i></span>
                                                <input type="text" class="form-control font-monospace fw-bold text-dark" id="field_fs_no" name="fs_no" placeholder="e.g. FS00005049" required>
                                            </div>
                                        </div>

                                        {{-- MRC No --}}
                                        <div class="col-md-4">
                                            <label class="form-label small fw-bold text-dark mb-1">
                                                Machine / MRC #
                                                <span class="badge bg-light text-muted border ms-1" style="font-size:0.65rem;">Col G</span>
                                            </label>
                                            <div class="input-group input-group-sm">
                                                <span class="input-group-text bg-light"><i class="fa-solid fa-cash-register text-muted"></i></span>
                                                <input type="text" class="form-control font-monospace" id="field_machine_no" name="machine_no" placeholder="e.g. DDB0000032">
                                            </div>
                                        </div>

                                        {{-- Receipt Date --}}
                                        <div class="col-md-4">
                                            <label class="form-label small fw-bold text-dark mb-1">
                                                Receipt Date *
                                                <span class="badge bg-primary bg-opacity-10 text-primary border ms-1" style="font-size:0.65rem;">Col F (DD/MM/YYYY)</span>
                                            </label>
                                            <div class="input-group input-group-sm">
                                                <span class="input-group-text bg-light"><i class="fa-solid fa-calendar text-muted"></i></span>
                                                <input type="text" class="form-control font-monospace" id="field_date" name="receipt_date" placeholder="DD/MM/YYYY" value="{{ date('d/m/Y') }}" required>
                                            </div>
                                        </div>

                                        {{-- Item Description --}}
                                        <div class="col-md-8">
                                            <label class="form-label small fw-bold text-dark mb-1">
                                                Item / Purchased Description *
                                                <span class="badge bg-primary bg-opacity-10 text-primary border ms-1" style="font-size:0.65rem;">Col I</span>
                                            </label>
                                            <div class="input-group input-group-sm">
                                                <span class="input-group-text bg-light"><i class="fa-solid fa-box text-muted"></i></span>
                                                <input type="text" class="form-control fw-semibold" id="field_description" name="description" placeholder="e.g. 32 X 2.0 MM ROUND PIPE" required>
                                            </div>
                                        </div>

                                        {{-- UOM --}}
                                        <div class="col-md-4">
                                            <label class="form-label small fw-bold text-dark mb-1">
                                                UOM (Col J)
                                            </label>
                                            <select class="form-select form-select-sm" id="field_uom" name="uom_id">
                                                <option value="9" selected>9 (OTHER)</option>
                                                <option value="7">7 (PCS)</option>
                                                <option value="2">2 (KG)</option>
                                                <option value="5">5 (LIT)</option>
                                                <option value="10">10 (PC)</option>
                                            </select>
                                        </div>

                                        {{-- Amounts Breakdown --}}
                                        <div class="col-12 mt-2">
                                            <div class="p-3 rounded-3 border bg-light">
                                                <div class="row g-2 align-items-center">
                                                    <div class="col-md-3">
                                                        <label class="form-label small fw-bold text-dark mb-1">Qty (Col K)</label>
                                                        <input type="number" step="0.01" class="form-control form-control-sm font-monospace text-end" id="field_qty" value="1.00" oninput="calcPreviewMath()">
                                                    </div>
                                                    <div class="col-md-3">
                                                        <label class="form-label small fw-bold text-dark mb-1">Unit Price (Col L)</label>
                                                        <input type="number" step="0.01" class="form-control form-control-sm font-monospace text-end" id="field_unit_price" value="0.00" oninput="calcPreviewMath()">
                                                    </div>
                                                    <div class="col-md-3">
                                                        <label class="form-label small fw-bold text-dark mb-1">Total Value (Col M)</label>
                                                        <input type="number" step="0.01" class="form-control form-control-sm font-monospace text-end fw-bold" id="field_subtotal" name="subtotal" value="0.00" oninput="calcPreviewFromTotal()">
                                                    </div>
                                                    <div class="col-md-3">
                                                        <label class="form-label small fw-bold text-dark mb-1">VAT 15% (Col N)</label>
                                                        <input type="number" step="0.01" class="form-control form-control-sm font-monospace text-end text-muted" id="field_vat" name="vat_amount" value="0.00">
                                                    </div>
                                                    <div class="col-12 mt-2">
                                                        <div class="p-2 rounded bg-success bg-opacity-10 text-success d-flex justify-content-between align-items-center">
                                                            <strong class="small">Value After VAT (Col O):</strong>
                                                            <div class="d-flex align-items-center gap-2">
                                                                <input type="number" step="0.01" class="form-control form-control-sm font-monospace text-end fw-bold fs-6 text-success border-success bg-white" style="width:160px;" id="field_total" name="total_amount" value="0.00" required>
                                                                <span class="fw-bold">ETB</span>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        {{-- Big Add Button in Form --}}
                                        <div class="col-12 mt-3">
                                            <button type="button" class="btn btn-success w-100 py-2 fw-bold shadow-sm" id="btn-add-primary-action" onclick="document.getElementById('btn-add-to-table').click()">
                                                <i class="fa-solid fa-plus-circle me-1"></i>Add This Receipt to ERCA Table &amp; Save
                                            </button>
                                        </div>

                                    </div>
                                </form>
                            </div>

                            {{-- Tab 2: Raw OCR Text --}}
                            <div class="tab-pane fade" id="tab-rawtext">
                                <textarea id="raw-ocr-textarea" class="form-control font-monospace small bg-light" rows="12" readonly placeholder="Raw text output will appear here once scanning begins..."></textarea>
                            </div>

                        </div>

                    </div>
                </div>

            </div>
        </div>
    </div>

    {{-- MAIN 15-COLUMN ERCA VAT DECLARATION TABLE (COLUMNS A TO O) --}}
    <div class="card border-0 shadow-sm rounded-3">
        <div class="card-header bg-white border-bottom py-3">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div class="d-flex align-items-center gap-2 flex-wrap">
                    <h5 class="mb-0 fw-bold text-dark">
                        <i class="fa-solid fa-table me-2 text-primary"></i>ERCA VAT Declaration Table (Columns A through O)
                    </h5>
                    <span class="badge bg-secondary text-white small" id="table-row-count">{{ $items->total() }} rows</span>
                    <button type="button" class="btn btn-xs btn-outline-success btn-sm fw-bold shadow-xs px-2.5 ms-2" id="btn-add-manual-row" onclick="addManualBlankRow()">
                        <i class="fa-solid fa-plus me-1"></i>Add Manual Row
                    </button>
                </div>

                {{-- Toolbar Filters --}}
                <div class="d-flex align-items-center gap-2 flex-wrap">
                    {{-- Needs Review Toggle Filter --}}
                    <div class="form-check form-switch mb-0">
                        <input class="form-check-input cursor-pointer" type="checkbox" id="filter-needs-review" {{ request()->boolean('needs_review') ? 'checked' : '' }} onchange="applyFilters()">
                        <label class="form-check-label small fw-bold text-danger cursor-pointer" for="filter-needs-review">
                            <i class="fa-solid fa-filter me-1"></i>Needs Review ({{ $stats['flagged_count'] }})
                        </label>
                    </div>

                    {{-- Search Form --}}
                    <form method="GET" action="{{ url('admin/receipt-ocr') }}" id="filter-form" class="d-flex align-items-center gap-2 flex-wrap">
                        @if(request()->boolean('needs_review'))
                            <input type="hidden" name="needs_review" value="1">
                        @endif
                        <input type="text" name="search" class="form-control form-control-sm" placeholder="Search FS#, TIN, Vendor..." value="{{ request('search') }}" style="width: 190px;">
                        <input type="date" name="date_from" class="form-control form-control-sm" value="{{ request('date_from') }}" title="Date From" style="width: 130px;">
                        <input type="date" name="date_to" class="form-control form-control-sm" value="{{ request('date_to') }}" title="Date To" style="width: 130px;">
                        <button type="submit" class="btn btn-sm btn-primary" title="Search"><i class="fa-solid fa-magnifying-glass"></i></button>
                        @if(request()->hasAny(['search', 'needs_review', 'date_from', 'date_to', 'category', 'project_id']))
                            <a href="{{ url('admin/receipt-ocr') }}" class="btn btn-sm btn-light border" title="Clear Filters"><i class="fa-solid fa-times"></i></a>
                        @endif
                    </form>
                </div>
            </div>
        </div>

        <div class="card-body p-0">
            <div class="table-responsive" style="max-height: 720px; overflow-y: auto;">
                <table class="table table-hover table-bordered table-sm align-middle mb-0 text-nowrap" id="receipt-ocr-table" style="font-size: 0.8rem;">
                    <thead class="table-light sticky-top shadow-xs" style="z-index: 2;">
                        <tr class="text-center align-middle" style="font-size: 0.72rem;">
                            <th style="width: 36px;">
                                <input type="checkbox" class="form-check-input" id="select-all-checkbox" onchange="toggleSelectAll(this)" title="Select All">
                            </th>
                            <th style="width: 70px;">Status</th>
                            <th>Col A<br><span class="text-muted">Cat</span></th>
                            <th>Col B<br><span class="text-muted">Cal</span></th>
                            <th>Col C<br><span class="text-muted">Type</span></th>
                            <th class="text-primary fw-bold">Col D<br><span>Supplier TIN</span></th>
                            <th class="fw-bold">Col E<br><span>Seller Name</span></th>
                            <th>Col F<br><span>Date (DD/MM/YYYY)</span></th>
                            <th>Col G<br><span>MRC No</span></th>
                            <th class="fw-bold text-dark">Col H<br><span>FS No</span></th>
                            <th class="fw-bold text-primary">Col I<br><span>Item / Description</span></th>
                            <th>Col J<br><span>UOM</span></th>
                            <th class="text-end">Col K<br><span>Qty</span></th>
                            <th class="text-end">Col L<br><span>Unit Price</span></th>
                            <th class="text-end fw-bold">Col M<br><span>Total Value</span></th>
                            <th class="text-end text-muted">Col N<br><span>VAT (15%)</span></th>
                            <th class="text-end fw-bold text-success">Col O<br><span>Value After VAT</span></th>
                            <th>Engine</th>
                            <th style="min-width: 130px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="table-body">
                        @forelse($items as $item)
                            @php
                                $r = $item->receipt;
                                $rowErrors = $item->validateRow();
                                $isFlagged = !empty($rowErrors) || ($r && $r->needs_review);
                                $dateFormatted = $r && $r->receipt_date ? $r->receipt_date->format('d/m/Y') : '';
                            @endphp
                            <tr id="row-{{ $item->id }}" data-id="{{ $item->id }}" data-receipt-id="{{ $r ? $r->id : '' }}" class="{{ $isFlagged ? 'table-warning bg-opacity-25' : '' }}">
                                {{-- Checkbox --}}
                                <td class="text-center">
                                    <input type="checkbox" class="form-check-input row-checkbox" value="{{ $item->id }}" onchange="updateSelectedCount()">
                                </td>

                                {{-- Status / Validation --}}
                                <td class="text-center">
                                    @if($isFlagged)
                                        <span class="badge bg-warning text-dark border border-warning" data-bs-toggle="tooltip" data-bs-placement="top" title="{{ implode('; ', $rowErrors) }}">
                                            <i class="fa-solid fa-triangle-exclamation me-1"></i>Review
                                        </span>
                                    @else
                                        <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25" title="All arithmetic checks verified">
                                            <i class="fa-solid fa-check me-1"></i>Valid
                                        </span>
                                    @endif
                                </td>

                                {{-- Col A: Cat --}}
                                <td class="text-center cell-display" data-field="vat_category">
                                    <span class="badge bg-light text-dark border">{{ $item->vat_category ?: 'G' }}</span>
                                </td>

                                {{-- Col B: Cal --}}
                                <td class="text-center cell-display" data-field="calendar_type">
                                    <span class="badge bg-light text-dark border">{{ $item->calendar_type ?: 'G' }}</span>
                                </td>

                                {{-- Col C: Type --}}
                                <td class="text-center cell-display" data-field="purchase_type">
                                    <span class="badge bg-light text-dark border">{{ $item->purchase_type ?: 3 }}</span>
                                </td>

                                {{-- Col D: Supplier TIN --}}
                                <td class="font-monospace fw-bold text-primary cell-display" data-field="supplier_tin">
                                    {{ $r ? $r->vendor_tin : '' }}
                                </td>

                                {{-- Col E: Seller Name --}}
                                <td class="fw-semibold text-dark cell-display" data-field="seller_name" style="max-width: 220px; overflow: hidden; text-overflow: ellipsis;">
                                    {{ $r ? $r->vendor_name : 'General Merchant' }}
                                </td>

                                {{-- Col F: Date (DD/MM/YYYY) --}}
                                <td class="text-center font-monospace cell-display" data-field="receipt_date">
                                    {{ $dateFormatted }}
                                </td>

                                {{-- Col G: MRC No --}}
                                <td class="text-center font-monospace cell-display" data-field="mrc_no">
                                    {{ $r ? $r->mrc_no : '' }}
                                </td>

                                {{-- Col H: FS No --}}
                                <td class="text-center font-monospace fw-bold text-dark cell-display" data-field="fs_no">
                                    {{ $r ? $r->fs_no : '' }}
                                </td>

                                {{-- Col I: Item / Description --}}
                                <td class="fw-semibold text-primary cell-display" data-field="item_description" style="max-width: 260px; overflow: hidden; text-overflow: ellipsis;" title="{{ $item->item_description }}">
                                    {{ $item->item_description }}
                                </td>

                                {{-- Col J: UOM --}}
                                <td class="text-center cell-display" data-field="uom">
                                    {{ $item->uom ?: '9' }}
                                </td>

                                {{-- Col K: Qty --}}
                                <td class="text-end font-monospace cell-display" data-field="qty">
                                    {{ number_format((float)$item->qty, 2) }}
                                </td>

                                {{-- Col L: Unit Price --}}
                                <td class="text-end font-monospace cell-display" data-field="unit_price">
                                    {{ number_format((float)$item->unit_price, 2) }}
                                </td>

                                {{-- Col M: Total Value --}}
                                <td class="text-end font-monospace fw-bold cell-display" data-field="total_value">
                                    {{ number_format((float)$item->total_value, 2) }}
                                </td>

                                {{-- Col N: VAT (15%) --}}
                                <td class="text-end font-monospace text-muted cell-display" data-field="vat_amount">
                                    {{ number_format((float)$item->vat_amount, 2) }}
                                </td>

                                {{-- Col O: Value After VAT --}}
                                <td class="text-end font-monospace fw-bold text-success cell-display" data-field="value_after_vat">
                                    {{ number_format((float)$item->value_after_vat, 2) }}
                                </td>

                                {{-- Engine Badge --}}
                                <td class="text-center">
                                    @if($r && $r->ocr_engine === 'ocr_space')
                                        <span class="badge bg-secondary text-white small" title="Extracted via OCR.Space Fallback Engine">
                                            OCR.Space
                                        </span>
                                    @else
                                        <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 small" title="Extracted via Google Gemini Multimodal AI">
                                            Gemini AI
                                        </span>
                                    @endif
                                </td>

                                {{-- Actions --}}
                                <td class="text-center">
                                    <div class="btn-group btn-group-sm">
                                        {{-- View Scanned Receipt Side-by-Side --}}
                                        @if($r && $r->file_path)
                                            <button type="button" class="btn btn-outline-primary btn-xs py-1 px-2" title="Inspect original scanned receipt side-by-side" onclick="openSideBySide({{ $item->id }})">
                                                <i class="fa-solid fa-eye"></i>
                                            </button>
                                        @endif

                                        {{-- Inline Edit Button --}}
                                        <button type="button" class="btn btn-outline-secondary btn-xs py-1 px-2 btn-edit-row" onclick="startEditRow({{ $item->id }})" title="Edit Row Inline">
                                            <i class="fa-solid fa-pen-to-square"></i>
                                        </button>

                                        {{-- Delete Button --}}
                                        <button type="button" class="btn btn-outline-danger btn-xs py-1 px-2" onclick="confirmDeleteRow({{ $item->id }})" title="Delete Row">
                                            <i class="fa-solid fa-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr id="no-rows-msg">
                                <td colspan="19" class="text-center py-5 text-muted">
                                    <i class="fa-solid fa-inbox fa-3x mb-3 text-secondary opacity-50"></i>
                                    <h6 class="fw-bold text-dark">No receipts scanned yet</h6>
                                    <p class="small mb-0">Drag and drop receipt images or PDFs into the upload box above to begin.</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{-- Pagination --}}
            @if($items->hasPages())
                <div class="p-3 border-top d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <small class="text-muted">Showing {{ $items->firstItem() }} to {{ $items->lastItem() }} of {{ $items->total() }} rows</small>
                    {{ $items->links() }}
                </div>
            @endif
        </div>
    </div>

</div>

{{-- ────────────────────────────────────────────────────────────────────────── --}}
{{-- MODAL 1: SIDE-BY-SIDE VERIFICATION MODAL --}}
{{-- ────────────────────────────────────────────────────────────────────────── --}}
<div class="modal fade" id="sideBySideModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable" style="max-width: 95vw;">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-dark text-white py-2 px-3">
                <div class="d-flex align-items-center gap-2">
                    <i class="fa-solid fa-columns text-success"></i>
                    <h6 class="modal-title fw-bold mb-0">Side-by-Side Scanned Receipt Verification</h6>
                    <span class="badge bg-secondary" id="sbs-fs-badge">FS: —</span>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-0">
                <div class="row g-0 h-100" style="min-height: 580px;">
                    {{-- Left Pane: Scanned Receipt Image / PDF with Zoom Controls --}}
                    <div class="col-lg-6 border-end bg-light d-flex flex-column">
                        <div class="p-2 border-bottom bg-white d-flex justify-content-between align-items-center">
                            <span class="small fw-bold text-muted"><i class="fa-solid fa-image me-1"></i>Original Document</span>
                            <div class="btn-group btn-group-sm">
                                <button type="button" class="btn btn-light border py-1" onclick="zoomImage(1.2)" title="Zoom In"><i class="fa-solid fa-magnifying-glass-plus"></i></button>
                                <button type="button" class="btn btn-light border py-1" onclick="zoomImage(0.8)" title="Zoom Out"><i class="fa-solid fa-magnifying-glass-minus"></i></button>
                                <button type="button" class="btn btn-light border py-1" onclick="rotateImage()" title="Rotate 90°"><i class="fa-solid fa-rotate-right"></i></button>
                                <button type="button" class="btn btn-light border py-1" onclick="resetZoom()" title="Reset"><i class="fa-solid fa-arrows-rotate"></i></button>
                            </div>
                        </div>
                        <div class="flex-grow-1 p-3 d-flex justify-content-center align-items-center position-relative overflow-auto" style="max-height: 600px; background: #333;">
                            <img id="sbs-preview-img" src="" alt="Scanned Receipt" class="img-fluid rounded shadow transition-all" style="max-height: 540px; transform-origin: center center; transform: scale(1) rotate(0deg);">
                            <iframe id="sbs-preview-pdf" src="" class="w-100 h-100 d-none" style="min-height: 520px; border: none;"></iframe>
                        </div>
                    </div>

                    {{-- Right Pane: Extracted Details & Math Verification Checklist --}}
                    <div class="col-lg-6 p-3 p-md-4 overflow-auto" style="max-height: 660px;">
                        <h6 class="fw-bold text-dark border-bottom pb-2 mb-3">
                            <i class="fa-solid fa-clipboard-check text-success me-1"></i>Extracted Data &amp; Verification Breakdown
                        </h6>

                        {{-- Validation Checklist Alert --}}
                        <div id="sbs-validation-box" class="p-3 rounded-3 mb-3 border bg-light"></div>

                        {{-- Fields Breakdown --}}
                        <div class="row g-2 small">
                            <div class="col-6">
                                <label class="text-muted fw-bold">Supplier TIN (Col D):</label>
                                <div class="font-monospace fw-bold text-primary fs-6" id="sbs-field-tin">—</div>
                            </div>
                            <div class="col-6">
                                <label class="text-muted fw-bold">Seller Name (Col E):</label>
                                <div class="fw-bold text-dark" id="sbs-field-seller">—</div>
                            </div>
                            <div class="col-4">
                                <label class="text-muted fw-bold">Date (Col F):</label>
                                <div class="font-monospace" id="sbs-field-date">—</div>
                            </div>
                            <div class="col-4">
                                <label class="text-muted fw-bold">MRC No (Col G):</label>
                                <div class="font-monospace" id="sbs-field-mrc">—</div>
                            </div>
                            <div class="col-4">
                                <label class="text-muted fw-bold">FS No (Col H):</label>
                                <div class="font-monospace fw-bold text-dark" id="sbs-field-fs">—</div>
                            </div>
                            <div class="col-12 mt-2">
                                <label class="text-muted fw-bold">Item Description (Col I):</label>
                                <div class="p-2 border rounded bg-white fw-semibold text-primary" id="sbs-field-desc">—</div>
                            </div>
                            <div class="col-3">
                                <label class="text-muted fw-bold">Qty (Col K):</label>
                                <div class="font-monospace fw-bold" id="sbs-field-qty">—</div>
                            </div>
                            <div class="col-3">
                                <label class="text-muted fw-bold">Unit Price (Col L):</label>
                                <div class="font-monospace" id="sbs-field-uprice">—</div>
                            </div>
                            <div class="col-3">
                                <label class="text-muted fw-bold">Total Value (Col M):</label>
                                <div class="font-monospace fw-bold" id="sbs-field-subtotal">—</div>
                            </div>
                            <div class="col-3">
                                <label class="text-muted fw-bold">VAT 15% (Col N):</label>
                                <div class="font-monospace text-muted" id="sbs-field-vat">—</div>
                            </div>
                            <div class="col-12 mt-2">
                                <div class="p-2 rounded bg-success bg-opacity-10 text-success d-flex justify-content-between align-items-center">
                                    <strong class="small">Value After VAT (Col O):</strong>
                                    <span class="fs-5 fw-bold font-monospace" id="sbs-field-total">—</span>
                                </div>
                            </div>
                        </div>

                        {{-- Raw Text Collapsible --}}
                        <div class="mt-3">
                            <button class="btn btn-sm btn-outline-secondary w-100 py-1" type="button" data-bs-toggle="collapse" data-bs-target="#collapseRawText">
                                <i class="fa-solid fa-code me-1"></i>View Raw OCR Text Stream
                            </button>
                            <div class="collapse mt-2" id="collapseRawText">
                                <textarea class="form-control font-monospace small bg-light" rows="6" readonly id="sbs-raw-text"></textarea>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer py-2 bg-light">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

{{-- ────────────────────────────────────────────────────────────────────────── --}}
{{-- MODAL 2: AI KEY & OCR SETTINGS MODAL --}}
{{-- ────────────────────────────────────────────────────────────────────────── --}}
<div class="modal fade" id="settingsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-dark text-white py-2.5 px-3">
                <div class="d-flex align-items-center gap-2">
                    <i class="fa-solid fa-key text-warning"></i>
                    <h6 class="modal-title fw-bold mb-0">OCR Engine Keys &amp; Configuration</h6>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="settings-form" onsubmit="saveSettings(event)">
                <div class="modal-body p-3">
                    {{-- Gemini Primary Option --}}
                    <div class="border rounded-3 p-3 mb-3 bg-light">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <label class="form-label fw-bold text-dark mb-0 small">
                                <i class="fa-solid fa-gem text-primary me-1"></i>Option A (Primary): Google Gemini Vision AI Key
                            </label>
                            <span class="badge bg-primary">High Accuracy</span>
                        </div>
                        <p class="text-muted small mb-2" style="font-size:0.75rem;">
                            Obtain from <a href="https://aistudio.google.com" target="_blank" class="fw-bold text-decoration-none">aistudio.google.com</a> ("Get API key"). Multimodal AI extracts all receipts and splits line items with 100% precision.
                        </p>
                        <div class="input-group input-group-sm mb-2">
                            <span class="input-group-text bg-white"><i class="fa-solid fa-key text-muted"></i></span>
                            <input type="password" class="form-control font-monospace" id="input_gemini_key" placeholder="Paste Gemini API Key (e.g. AIzaSy...)" value="{{ $geminiKey }}">
                            <button class="btn btn-outline-secondary" type="button" onclick="togglePasswordVisibility('input_gemini_key')">
                                <i class="fa-solid fa-eye"></i>
                            </button>
                        </div>
                        <div class="d-flex justify-content-between align-items-center">
                            <small class="text-muted" style="font-size:0.7rem;">Currently: <code>{{ $maskedGemini ?: 'Not configured' }}</code></small>
                            <button type="button" class="btn btn-xs btn-outline-primary btn-sm py-1 px-2 fw-semibold" onclick="testApiKey('gemini')">
                                <i class="fa-solid fa-vial me-1"></i>Test Gemini Key
                            </button>
                        </div>
                        <div id="gemini-test-result" class="mt-2 small d-none"></div>
                    </div>

                    {{-- OCR.Space Fallback Option --}}
                    <div class="border rounded-3 p-3 bg-light">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <label class="form-label fw-bold text-dark mb-0 small">
                                <i class="fa-solid fa-camera text-secondary me-1"></i>Option B (Fallback): OCR.Space API Key
                            </label>
                            <span class="badge bg-secondary">Automatic Fallback</span>
                        </div>
                        <p class="text-muted small mb-2" style="font-size:0.75rem;">
                            Used automatically if Gemini is offline, rate-limited, or key is missing. Free default key is provided, or get your dedicated key at <a href="https://ocr.space/ocrapi" target="_blank" class="fw-bold text-decoration-none">ocr.space/ocrapi</a>.
                        </p>
                        <div class="input-group input-group-sm mb-2">
                            <span class="input-group-text bg-white"><i class="fa-solid fa-key text-muted"></i></span>
                            <input type="password" class="form-control font-monospace" id="input_ocr_space_key" placeholder="OCR.Space Key (default: helloworld)" value="{{ $ocrSpaceKey }}">
                            <button class="btn btn-outline-secondary" type="button" onclick="togglePasswordVisibility('input_ocr_space_key')">
                                <i class="fa-solid fa-eye"></i>
                            </button>
                        </div>
                        <div class="d-flex justify-content-between align-items-center">
                            <small class="text-muted" style="font-size:0.7rem;">Currently: <code>{{ $maskedOcrSpace ?: 'helloworld (free tier)' }}</code></small>
                            <button type="button" class="btn btn-xs btn-outline-secondary btn-sm py-1 px-2 fw-semibold" onclick="testApiKey('ocr_space')">
                                <i class="fa-solid fa-vial me-1"></i>Test OCR.Space Key
                            </button>
                        </div>
                        <div id="ocrspace-test-result" class="mt-2 small d-none"></div>
                    </div>
                </div>
                <div class="modal-footer py-2 bg-light d-flex justify-content-between">
                    <small class="text-muted" style="font-size:0.75rem;"><i class="fa-solid fa-shield-halved me-1"></i>Keys stored encrypted on server.</small>
                    <div>
                        <button type="button" class="btn btn-light border btn-sm" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary btn-sm fw-bold shadow-xs">
                            <i class="fa-solid fa-save me-1"></i>Save Keys
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script>
/**
 * Global CSRF and endpoints
 */
const CSRF_TOKEN = '{{ csrf_token() }}';
const PROCESS_FILE_URL = '{{ url("admin/receipt-ocr/process-file") }}';
const AI_SCAN_URL = '{{ url("admin/receipt-ocr/ai-scan") }}';
const SAVE_RECEIPT_URL = '{{ url("admin/receipt-ocr/save") }}';
const SAVE_ITEM_URL = '{{ url("admin/receipt-ocr/save-item") }}';
const SAVE_ALL_URL = '{{ url("admin/receipt-ocr/save-all") }}';
const REPLACE_DUP_URL = '{{ url("admin/receipt-ocr/replace-duplicate") }}';
const CREATE_MANUAL_ROW_URL = '{{ url("admin/receipt-ocr/create-manual-row") }}';
const DESTROY_ITEM_BASE = '{{ url("admin/receipt-ocr/item") }}';
const SHOW_RECEIPT_BASE = '{{ url("admin/receipt-ocr") }}';
const SAVE_SETTINGS_URL = '{{ url("admin/receipt-ocr/settings") }}';
const TEST_KEY_URL = '{{ url("admin/receipt-ocr/test-key") }}';
const EXPORT_EXCEL_URL = '{{ url("admin/receipt-ocr/export-excel") }}';
const EXPORT_CSV_URL = '{{ url("admin/receipt-ocr/export-csv") }}';

// State management
let uploadQueue = [];
let activeWorkers = 0;
const MAX_CONCURRENCY = 3;
let editedRows = new Map(); // id -> editedData
let currentZoom = 1.0;
let currentRotation = 0;
let currentPreviewRotation = 0;
let currentActiveFile = null;
let currentExtractedData = null;
let currentUploadedFilePath = null;
let currentEngineUsed = 'gemini';
let currentConfidence = 'high';

// Initialize
document.addEventListener('DOMContentLoaded', () => {
    setupDragAndDrop();
    setupCameraInput();
    setupPreviewButtons();

    // Prevent accidental navigation if unsaved edits exist
    window.addEventListener('beforeunload', (e) => {
        if (editedRows.size > 0) {
            e.preventDefault();
            e.returnValue = 'You have unsaved receipt changes. Are you sure you want to leave?';
        }
    });
});

/**
 * Setup Buttons inside Preview Area (Add to Table, Rotate, Re-Scan, Clear)
 */
function setupPreviewButtons() {
    const addBtn = document.getElementById('btn-add-to-table');
    const addFormBtn = document.getElementById('btn-add-form-to-table');
    const rotateBtn = document.getElementById('btn-rotate-img');
    const rescanBtn = document.getElementById('btn-reprocess-img');
    const clearBtn = document.getElementById('btn-clear-img');

    // ADD BUTTON IN THE SECTION (Main user request)
    const handleAddToTable = () => {
        if (!currentExtractedData && !currentUploadedFilePath) {
            showToast('Please upload or scan a receipt first.', 'warning');
            return;
        }

        const subtotal = parseFloat(document.getElementById('field_subtotal').value) || 0;
        const vat = parseFloat(document.getElementById('field_vat').value) || 0;
        const total = parseFloat(document.getElementById('field_total').value) || 0;

        const payload = {
            file_path: currentUploadedFilePath || (currentExtractedData ? currentExtractedData.file_path : ''),
            vendor_name: document.getElementById('field_vendor').value.trim() || 'General Merchant',
            vendor_tin: document.getElementById('field_tin').value.trim(),
            buyer_tin: '0038480010',
            fs_no: document.getElementById('field_fs_no').value.trim(),
            machine_no: document.getElementById('field_machine_no').value.trim(),
            receipt_date: document.getElementById('field_date').value.trim(),
            description: document.getElementById('field_description').value.trim() || 'Purchased Material',
            uom_id: document.getElementById('field_uom').value,
            subtotal: subtotal,
            vat_amount: vat,
            total_amount: total,
            engine: currentEngineUsed,
            confidence: currentConfidence,
            line_items: (currentExtractedData && currentExtractedData.items && currentExtractedData.items.length > 1) 
                ? currentExtractedData.items 
                : [
                    {
                        item_description: document.getElementById('field_description').value.trim() || (currentExtractedData && currentExtractedData.items && currentExtractedData.items[0] ? currentExtractedData.items[0].item_description : 'Purchased Material'),
                        uom: document.getElementById('field_uom').value,
                        qty: parseFloat(document.getElementById('field_qty').value) || 1,
                        unit_price: parseFloat(document.getElementById('field_unit_price').value) || subtotal,
                        total_value: subtotal,
                        vat_amount: vat,
                        value_after_vat: total
                    }
                ]
        };

        addBtn.disabled = true;
        addBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-1"></i>Adding...';

        fetch(SAVE_RECEIPT_URL, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': CSRF_TOKEN,
                'Accept': 'application/json'
            },
            body: JSON.stringify(payload)
        })
        .then(r => r.json())
        .then(res => {
            addBtn.disabled = false;
            addBtn.innerHTML = '<i class="fa-solid fa-plus-circle me-1"></i>Add to Table';

            if (res.is_duplicate) {
                if (confirm(`${res.duplicate_message}\n\nWould you like to Replace the existing receipt with this new scan?`)) {
                    replaceDuplicateReceiptDirect(res.existing_receipt.id, payload);
                }
                return;
            }

            if (res.success) {
                showToast(res.message, 'success');
                if (res.items && res.items.length > 0) {
                    res.items.forEach(it => appendRowToTable(it, res.receipt));
                }
                updateStatsDisplay();

                // Highlight newly added row
                if (res.items && res.items[0]) {
                    const tr = document.getElementById('row-' + res.items[0].id);
                    if (tr) {
                        tr.scrollIntoView({ behavior: 'smooth', block: 'center' });
                        tr.classList.add('table-success');
                        setTimeout(() => tr.classList.remove('table-success'), 2000);
                    }
                }
            } else {
                showToast(res.message || 'Error saving receipt', 'danger');
            }
        })
        .catch(err => {
            addBtn.disabled = false;
            addBtn.innerHTML = '<i class="fa-solid fa-plus-circle me-1"></i>Add to Table';
            showToast('Network error adding receipt to table', 'danger');
        });
    };

    if (addBtn) addBtn.addEventListener('click', handleAddToTable);
    if (addFormBtn) addFormBtn.addEventListener('click', handleAddToTable);

    // ROTATE BUTTON
    if (rotateBtn) {
        rotateBtn.addEventListener('click', () => {
            currentPreviewRotation = (currentPreviewRotation + 90) % 360;
            const img = document.getElementById('receipt-preview-img');
            if (img) img.style.transform = `rotate(${currentPreviewRotation}deg)`;
        });
    }

    // RE-SCAN BUTTON
    if (rescanBtn) {
        rescanBtn.addEventListener('click', () => {
            if (currentActiveFile) {
                scanSingleFile(currentActiveFile);
            } else if (currentUploadedFilePath) {
                scanExistingPath(currentUploadedFilePath);
            } else {
                showToast('No receipt file loaded to re-scan.', 'info');
            }
        });
    }

    // CLEAR BUTTON
    if (clearBtn) {
        clearBtn.addEventListener('click', () => {
            resetPreviewArea();
        });
    }
}

function resetPreviewArea() {
    currentActiveFile = null;
    currentExtractedData = null;
    currentUploadedFilePath = null;
    currentPreviewRotation = 0;

    const previewArea = document.getElementById('preview-area');
    const dropPrompt = document.getElementById('drop-prompt');
    const pbar = document.getElementById('ocr-progress-container');

    if (previewArea) previewArea.classList.add('d-none');
    if (dropPrompt) dropPrompt.classList.remove('d-none');
    if (pbar) pbar.classList.add('d-none');

    const img = document.getElementById('receipt-preview-img');
    if (img) {
        img.src = '';
        img.style.transform = 'rotate(0deg)';
    }

    // Reset Form Fields
    document.getElementById('field_vendor').value = '';
    document.getElementById('field_tin').value = '';
    document.getElementById('field_fs_no').value = '';
    document.getElementById('field_machine_no').value = '';
    document.getElementById('field_description').value = '';
    document.getElementById('field_qty').value = '1.00';
    document.getElementById('field_unit_price').value = '0.00';
    document.getElementById('field_subtotal').value = '0.00';
    document.getElementById('field_vat').value = '0.00';
    document.getElementById('field_total').value = '0.00';
    document.getElementById('raw-ocr-textarea').value = '';

    const badge = document.getElementById('ocr-status-badge');
    if (badge) {
        badge.className = 'badge bg-primary text-white small';
        badge.innerHTML = '<i class="fa-solid fa-circle me-1" style="font-size:0.55rem;"></i>Ready to scan';
    }
}

/**
 * Drag and Drop & Multiple File Picker Handling
 */
function setupDragAndDrop() {
    const dropZone = document.getElementById('drop-zone');
    const fileInput = document.getElementById('file-input');

    ['dragenter', 'dragover'].forEach(eventName => {
        dropZone.addEventListener(eventName, (e) => {
            e.preventDefault();
            e.stopPropagation();
            dropZone.classList.add('bg-white', 'shadow-sm');
            dropZone.style.borderColor = '#059669';
        }, false);
    });

    ['dragleave', 'drop'].forEach(eventName => {
        dropZone.addEventListener(eventName, (e) => {
            e.preventDefault();
            e.stopPropagation();
            dropZone.classList.remove('bg-white', 'shadow-sm');
            dropZone.style.borderColor = '#10b981';
        }, false);
    });

    dropZone.addEventListener('drop', (e) => {
        const dt = e.dataTransfer;
        const files = dt.files;
        if (files && files.length > 0) {
            handleSelectedFiles(files);
        }
    });

    fileInput.addEventListener('change', (e) => {
        if (e.target.files && e.target.files.length > 0) {
            handleSelectedFiles(e.target.files);
            e.target.value = '';
        }
    });
}

function setupCameraInput() {
    const cam = document.getElementById('camera-input');
    if (cam) {
        cam.addEventListener('change', (e) => {
            if (e.target.files && e.target.files.length > 0) {
                handleSelectedFiles(e.target.files);
                e.target.value = '';
            }
        });
    }
}

/**
 * Handle Selected Files (Single Receipt or Batch)
 */
function handleSelectedFiles(fileList) {
    const files = Array.from(fileList);
    if (!files.length) return;

    // Load first file into the Live Image Preview Box immediately
    const firstFile = files[0];
    currentActiveFile = firstFile;
    showFileInPreview(firstFile);

    // If only 1 file: direct scan with live progress
    if (files.length === 1) {
        scanSingleFile(firstFile);
        return;
    }

    // If multiple files: run parallel worker batch queue
    const queueContainer = document.getElementById('queue-container');
    const progressContainer = document.getElementById('batch-progress-container');
    queueContainer.classList.remove('d-none');
    progressContainer.classList.remove('d-none');

    const seenNames = new Set();
    files.forEach(file => {
        const queueId = 'q_' + Math.random().toString(36).substr(2, 9);
        const isBatchDuplicate = seenNames.has(file.name + '_' + file.size);
        seenNames.add(file.name + '_' + file.size);

        const task = {
            id: queueId,
            file: file,
            status: isBatchDuplicate ? 'duplicate_batch' : 'queued',
            progress: 0,
            extractedData: null,
            filePath: null,
            existingReceiptId: null,
            errorMessage: isBatchDuplicate ? 'Duplicate file uploaded twice in the same batch.' : null,
        };

        uploadQueue.push(task);
        renderQueueCard(task);
    });

    updateBatchProgress();
    processNextQueueItem();
}

/**
 * Show File in Preview Area
 */
function showFileInPreview(file) {
    const dropPrompt = document.getElementById('drop-prompt');
    const previewArea = document.getElementById('preview-area');
    const imgEl = document.getElementById('receipt-preview-img');
    const pdfEl = document.getElementById('pdf-preview-box');

    dropPrompt.classList.add('d-none');
    previewArea.classList.remove('d-none');

    const isPdf = file.type.includes('pdf') || file.name.toLowerCase().endsWith('.pdf');
    if (isPdf) {
        imgEl.classList.add('d-none');
        pdfEl.classList.remove('d-none');
        document.getElementById('pdf-filename').textContent = file.name;
    } else {
        pdfEl.classList.add('d-none');
        imgEl.classList.remove('d-none');
        const reader = new FileReader();
        reader.onload = (e) => {
            imgEl.src = e.target.result;
            imgEl.style.transform = 'rotate(0deg)';
            currentPreviewRotation = 0;
        };
        reader.readAsDataURL(file);
    }
}

/**
 * Scan a Single File with Progress
 */
function scanSingleFile(file) {
    const pbarContainer = document.getElementById('ocr-progress-container');
    const pbar = document.getElementById('ocr-progress-bar');
    const pctEl = document.getElementById('ocr-progress-pct');
    const labelEl = document.getElementById('ocr-progress-label');
    const badge = document.getElementById('ocr-status-badge');

    pbarContainer.classList.remove('d-none');
    pbar.style.width = '25%';
    pctEl.textContent = '25%';
    labelEl.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-1 text-primary"></i>Uploading &amp; Scanning...';
    badge.className = 'badge bg-warning text-dark small';
    badge.innerHTML = '<i class="fa-solid fa-bolt me-1"></i>Scanning';

    const formData = new FormData();
    formData.append('receipt_file', file);
    formData.append('_token', CSRF_TOKEN);

    const xhr = new XMLHttpRequest();
    xhr.open('POST', PROCESS_FILE_URL, true);

    xhr.upload.onprogress = (e) => {
        if (e.lengthComputable) {
            const p = Math.round((e.loaded / e.total) * 40);
            pbar.style.width = p + '%';
            pctEl.textContent = p + '%';
        }
    };

    xhr.onload = function() {
        if (xhr.status >= 200 && xhr.status < 300) {
            try {
                const res = JSON.parse(xhr.responseText);
                pbar.style.width = '100%';
                pctEl.textContent = '100%';
                labelEl.innerHTML = '<i class="fa-solid fa-circle-check text-success me-1"></i>Scan Complete!';
                pbar.className = 'progress-bar bg-success';

                badge.className = 'badge bg-success text-white small';
                badge.innerHTML = '<i class="fa-solid fa-check me-1"></i>Scan Complete';

                if (res.is_duplicate) {
                    showToast(res.duplicate_message, 'warning');
                    populateFormWithExtracted(res.extracted, res.file_path, res.engine, res.confidence);
                    currentExtractedData = res.extracted;
                    currentUploadedFilePath = res.file_path;
                    currentEngineUsed = res.engine || 'gemini';
                    currentConfidence = res.confidence || 'high';

                    if (confirm(`${res.duplicate_message}\n\nWould you like to Replace the existing receipt with this new scan?`)) {
                        replaceDuplicateReceiptDirect(res.existing_receipt.id, {
                            file_path: res.file_path,
                            extracted_data: res.extracted,
                            engine: res.engine
                        });
                    }
                } else if (res.success) {
                    currentExtractedData = res.receipt.parsed_data || {};
                    currentUploadedFilePath = res.receipt.file_path;
                    currentEngineUsed = res.engine || 'gemini';
                    currentConfidence = res.confidence || 'high';

                    populateFormWithExtracted(res.receipt.parsed_data, res.receipt.file_path, res.engine, res.confidence);

                    // Add items into table automatically
                    if (res.items && res.items.length > 0) {
                        res.items.forEach(it => appendRowToTable(it, res.receipt));
                    }
                    updateStatsDisplay();
                    showToast(res.message, 'success');
                }
            } catch (err) {
                handleSingleScanError('Error parsing server response');
            }
        } else {
            handleSingleScanError(`Server error HTTP ${xhr.status}`);
        }
    };

    xhr.onerror = function() {
        handleSingleScanError('Network connection error');
    };

    xhr.send(formData);
}

function handleSingleScanError(msg) {
    const labelEl = document.getElementById('ocr-progress-label');
    const pbar = document.getElementById('ocr-progress-bar');
    const badge = document.getElementById('ocr-status-badge');

    if (labelEl) labelEl.innerHTML = `<i class="fa-solid fa-times text-danger me-1"></i>Scan failed: ${msg}`;
    if (pbar) pbar.className = 'progress-bar bg-danger';
    if (badge) {
        badge.className = 'badge bg-danger text-white small';
        badge.innerHTML = '<i class="fa-solid fa-times me-1"></i>Failed';
    }
    showToast(msg, 'danger');
}

/**
 * Populate Form Fields with Extracted Data
 */
function populateFormWithExtracted(data, filePath, engine, confidence) {
    if (!data) return;

    if (filePath) document.getElementById('stored_file_path').value = filePath;
    if (engine) document.getElementById('ocr_engine_hidden').value = engine;
    if (confidence) document.getElementById('ocr_confidence_hidden').value = confidence;

    document.getElementById('field_vendor').value = data.merchant_name || '';
    document.getElementById('field_tin').value = data.supplier_tin || '';
    document.getElementById('field_fs_no').value = data.fs_no || '';
    document.getElementById('field_machine_no').value = data.machine_no || '';
    document.getElementById('field_date').value = data.receipt_date || '';

    const firstItem = (data.items && data.items[0]) ? data.items[0] : null;
    if (firstItem) {
        document.getElementById('field_description').value = firstItem.item_description || data.description || '';
        document.getElementById('field_qty').value = Number(firstItem.qty || 1).toFixed(2);
        document.getElementById('field_unit_price').value = Number(firstItem.unit_price || 0).toFixed(2);
        document.getElementById('field_subtotal').value = Number(firstItem.total_value || data.subtotal || 0).toFixed(2);
        document.getElementById('field_vat').value = Number(firstItem.vat || data.vat_amount || 0).toFixed(2);
        document.getElementById('field_total').value = Number(firstItem.value_after_vat || data.total_amount || 0).toFixed(2);
    } else {
        document.getElementById('field_description').value = data.description || 'Purchased Material';
        document.getElementById('field_subtotal').value = Number(data.subtotal || 0).toFixed(2);
        document.getElementById('field_vat').value = Number(data.vat_amount || 0).toFixed(2);
        document.getElementById('field_total').value = Number(data.total_amount || 0).toFixed(2);
    }

    if (data.raw_text) {
        document.getElementById('raw-ocr-textarea').value = data.raw_text;
    }
}

/**
 * Math Calculations on Form
 */
function calcPreviewMath() {
    const qty = parseFloat(document.getElementById('field_qty').value) || 0;
    const uprice = parseFloat(document.getElementById('field_unit_price').value) || 0;
    const total = Math.round(qty * uprice * 100) / 100;
    const vat = Math.round(total * 0.15 * 100) / 100;
    const afterVat = Math.round((total + vat) * 100) / 100;

    document.getElementById('field_subtotal').value = total.toFixed(2);
    document.getElementById('field_vat').value = vat.toFixed(2);
    document.getElementById('field_total').value = afterVat.toFixed(2);
}

function calcPreviewFromTotal() {
    const total = parseFloat(document.getElementById('field_subtotal').value) || 0;
    const vat = Math.round(total * 0.15 * 100) / 100;
    const afterVat = Math.round((total + vat) * 100) / 100;

    document.getElementById('field_vat').value = vat.toFixed(2);
    document.getElementById('field_total').value = afterVat.toFixed(2);
}

/**
 * Replace Duplicate Direct Helper
 */
function replaceDuplicateReceiptDirect(existingId, payload) {
    fetch(REPLACE_DUP_URL, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': CSRF_TOKEN,
            'Accept': 'application/json'
        },
        body: JSON.stringify({
            existing_id: existingId,
            file_path: payload.file_path,
            extracted_data: payload.extracted_data || payload,
        })
    })
    .then(r => r.json())
    .then(res => {
        if (res.success) {
            showToast(res.message, 'success');
            setTimeout(() => window.location.reload(), 800);
        } else {
            showToast(res.message || 'Replace failed', 'danger');
        }
    })
    .catch(err => {
        showToast('Network error replacing duplicate', 'danger');
    });
}

/**
 * Add Manual Blank Row directly to table
 */
function addManualBlankRow() {
    fetch(CREATE_MANUAL_ROW_URL, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': CSRF_TOKEN,
            'Accept': 'application/json'
        }
    })
    .then(r => r.json())
    .then(res => {
        if (res.success) {
            appendRowToTable(res.item, res.receipt);
            updateStatsDisplay();
            startEditRow(res.item.id);
            showToast('New blank row added. Enter details and click Save.', 'info');
        }
    })
    .catch(err => {
        showToast('Error creating manual row', 'danger');
    });
}

/**
 * Concurrency Worker Loop for Batch Upload
 */
function processNextQueueItem() {
    if (activeWorkers >= MAX_CONCURRENCY) return;

    const nextTask = uploadQueue.find(t => t.status === 'queued');
    if (!nextTask) return;

    activeWorkers++;
    nextTask.status = 'scanning';
    updateTaskCard(nextTask, 'Scanning receipt with AI...', 40, 'bg-primary', '<span class="badge bg-primary"><i class="fa-solid fa-spinner fa-spin me-1"></i>Scanning</span>');

    const formData = new FormData();
    formData.append('receipt_file', nextTask.file);
    formData.append('_token', CSRF_TOKEN);

    const xhr = new XMLHttpRequest();
    xhr.open('POST', PROCESS_FILE_URL, true);

    xhr.upload.onprogress = (e) => {
        if (e.lengthComputable) {
            const pct = Math.round((e.loaded / e.total) * 40);
            updateTaskProgress(nextTask.id, pct);
        }
    };

    xhr.onload = function() {
        activeWorkers--;
        if (xhr.status >= 200 && xhr.status < 300) {
            try {
                const res = JSON.parse(xhr.responseText);
                if (res.is_duplicate) {
                    nextTask.status = 'duplicate';
                    nextTask.extractedData = res.extracted;
                    nextTask.filePath = res.file_path;
                    nextTask.existingReceiptId = res.existing_receipt.id;

                    const fs = res.extracted.fs_no || 'Unknown';
                    const dupHtml = `
                        <div class="d-flex gap-1 mt-1">
                            <button type="button" class="btn btn-xs btn-outline-secondary py-0 px-1" onclick="dismissQueueCard('${nextTask.id}')" title="Skip (Keep existing)">Skip</button>
                            <button type="button" class="btn btn-xs btn-warning py-0 px-1 fw-bold" onclick="replaceDuplicateReceipt('${nextTask.id}')" title="Replace existing receipt with this new scan">Replace</button>
                        </div>
                    `;
                    updateTaskCard(nextTask, `Duplicate FS# ${fs} matches existing receipt.`, 100, 'bg-warning', '<span class="badge bg-warning text-dark"><i class="fa-solid fa-copy me-1"></i>Duplicate</span>', dupHtml);
                } else if (res.success) {
                    nextTask.status = 'done';
                    updateTaskCard(nextTask, `Done! Extracted ${res.items ? res.items.length : 1} item row(s).`, 100, 'bg-success', '<span class="badge bg-success"><i class="fa-solid fa-check me-1"></i>Done</span>');

                    if (res.items && res.items.length > 0) {
                        res.items.forEach(it => appendRowToTable(it, res.receipt));
                    }
                    updateStatsDisplay();
                } else {
                    handleTaskFailure(nextTask, res.message || 'Extraction failed');
                }
            } catch (err) {
                handleTaskFailure(nextTask, 'Invalid server response');
            }
        } else {
            handleTaskFailure(nextTask, `Server error HTTP ${xhr.status}`);
        }

        updateBatchProgress();
        processNextQueueItem();
    };

    xhr.onerror = function() {
        activeWorkers--;
        handleTaskFailure(nextTask, 'Network error reaching server');
        updateBatchProgress();
        processNextQueueItem();
    };

    xhr.send(formData);
}

function handleTaskFailure(task, errMsg) {
    task.status = 'failed';
    const retryBtn = `<button type="button" class="btn btn-xs btn-outline-danger py-0 px-1.5 fw-bold" onclick="retryQueueItem('${task.id}')"><i class="fa-solid fa-rotate-right me-1"></i>Retry</button>`;
    updateTaskCard(task, errMsg, 100, 'bg-danger', '<span class="badge bg-danger"><i class="fa-solid fa-times me-1"></i>Failed</span>', retryBtn);
}

function retryQueueItem(taskId) {
    const task = uploadQueue.find(t => t.id === taskId);
    if (!task) return;
    task.status = 'queued';
    updateTaskCard(task, 'Re-queued for scan...', 0, 'bg-secondary', '<span class="badge bg-secondary">Queued</span>', '');
    processNextQueueItem();
}

function updateTaskCard(task, msg, pct, pbarClass, badgeHtml, actionsHtml = '') {
    const msgEl = document.getElementById('msg-' + task.id);
    const pbarEl = document.getElementById('pbar-' + task.id);
    const badgeEl = document.getElementById('badge-' + task.id);
    const actionsEl = document.getElementById('actions-' + task.id);

    if (msgEl) msgEl.textContent = msg;
    if (pbarEl) {
        pbarEl.style.width = pct + '%';
        pbarEl.className = 'progress-bar progress-bar-striped progress-bar-animated ' + pbarClass;
    }
    if (badgeEl) badgeEl.innerHTML = badgeHtml;
    if (actionsEl) actionsEl.innerHTML = actionsHtml;
}

function updateTaskProgress(taskId, pct) {
    const pbarEl = document.getElementById('pbar-' + taskId);
    if (pbarEl) pbarEl.style.width = pct + '%';
}

function dismissQueueCard(taskId) {
    const card = document.getElementById('card-' + taskId);
    if (card) card.remove();
}

function updateBatchProgress() {
    const total = uploadQueue.length;
    if (total === 0) return;
    const completed = uploadQueue.filter(t => t.status === 'done' || t.status === 'duplicate' || t.status === 'failed' || t.status === 'duplicate_batch').length;
    const pct = Math.round((completed / total) * 100);

    const bar = document.getElementById('batch-progress-bar');
    const pctEl = document.getElementById('batch-progress-pct');
    const doneEl = document.getElementById('batch-count-done');
    const totEl = document.getElementById('batch-count-total');

    if (bar) bar.style.width = pct + '%';
    if (pctEl) pctEl.textContent = pct + '%';
    if (doneEl) doneEl.textContent = completed;
    if (totEl) totEl.textContent = total;
}

function renderQueueCard(task) {
    const container = document.getElementById('queue-container');
    const card = document.createElement('div');
    card.className = 'col-md-6';
    card.id = 'card-' + task.id;

    const isPdf = task.file.type.includes('pdf') || task.file.name.toLowerCase().endsWith('.pdf');
    const icon = isPdf ? 'fa-file-pdf text-danger' : 'fa-file-image text-primary';
    const sizeKb = (task.file.size / 1024).toFixed(1) + ' KB';

    card.innerHTML = `
        <div class="card border rounded-3 p-2 bg-white shadow-xs h-100">
            <div class="d-flex align-items-center gap-2 mb-1">
                <i class="fa-solid ${icon}"></i>
                <div class="overflow-hidden flex-grow-1" style="line-height:1.2;">
                    <div class="fw-bold text-dark text-truncate small" title="${task.file.name}">${task.file.name}</div>
                    <small class="text-muted" style="font-size:0.65rem;">${sizeKb}</small>
                </div>
                <div id="badge-${task.id}">
                    <span class="badge bg-secondary">Queued</span>
                </div>
            </div>
            <div class="progress mb-1" style="height: 5px;">
                <div id="pbar-${task.id}" class="progress-bar progress-bar-striped progress-bar-animated bg-primary" style="width: 0%"></div>
            </div>
            <div class="d-flex justify-content-between align-items-center" style="font-size:0.7rem;">
                <span class="text-muted text-truncate" id="msg-${task.id}">${task.errorMessage || 'Queued...'}</span>
                <div id="actions-${task.id}"></div>
            </div>
        </div>
    `;
    container.appendChild(card);
}

/**
 * Append New Row to Table Dynamically (keeps all previous rows!)
 */
function appendRowToTable(item, receipt) {
    const tbody = document.getElementById('table-body');
    const noRows = document.getElementById('no-rows-msg');
    if (noRows) noRows.remove();

    const dateFormatted = receipt && receipt.receipt_date ? formatDateDisplay(receipt.receipt_date) : '';
    const isFlagged = item.is_flagged || (receipt && receipt.needs_review);

    const tr = document.createElement('tr');
    tr.id = 'row-' + item.id;
    tr.setAttribute('data-id', item.id);
    tr.setAttribute('data-receipt-id', receipt ? receipt.id : '');
    tr.className = isFlagged ? 'table-warning bg-opacity-25' : '';

    tr.innerHTML = `
        <td class="text-center">
            <input type="checkbox" class="form-check-input row-checkbox" value="${item.id}" onchange="updateSelectedCount()">
        </td>
        <td class="text-center">
            ${isFlagged 
                ? `<span class="badge bg-warning text-dark border border-warning" title="${(item.flag_reasons || []).join('; ')}"><i class="fa-solid fa-triangle-exclamation me-1"></i>Review</span>`
                : `<span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25"><i class="fa-solid fa-check me-1"></i>Valid</span>`
            }
        </td>
        <td class="text-center cell-display" data-field="vat_category"><span class="badge bg-light text-dark border">${item.vat_category || 'G'}</span></td>
        <td class="text-center cell-display" data-field="calendar_type"><span class="badge bg-light text-dark border">${item.calendar_type || 'G'}</span></td>
        <td class="text-center cell-display" data-field="purchase_type"><span class="badge bg-light text-dark border">${item.purchase_type || 3}</span></td>
        <td class="font-monospace fw-bold text-primary cell-display" data-field="supplier_tin">${receipt ? receipt.vendor_tin : ''}</td>
        <td class="fw-semibold text-dark cell-display" data-field="seller_name">${receipt ? receipt.vendor_name : 'General Merchant'}</td>
        <td class="text-center font-monospace cell-display" data-field="receipt_date">${dateFormatted}</td>
        <td class="text-center font-monospace cell-display" data-field="mrc_no">${receipt ? (receipt.mrc_no || '') : ''}</td>
        <td class="text-center font-monospace fw-bold text-dark cell-display" data-field="fs_no">${receipt ? (receipt.fs_no || '') : ''}</td>
        <td class="fw-semibold text-primary cell-display" data-field="item_description" title="${item.item_description}">${item.item_description}</td>
        <td class="text-center cell-display" data-field="uom">${item.uom || '9'}</td>
        <td class="text-end font-monospace cell-display" data-field="qty">${Number(item.qty).toFixed(2)}</td>
        <td class="text-end font-monospace cell-display" data-field="unit_price">${Number(item.unit_price).toFixed(2)}</td>
        <td class="text-end font-monospace fw-bold cell-display" data-field="total_value">${Number(item.total_value).toFixed(2)}</td>
        <td class="text-end font-monospace text-muted cell-display" data-field="vat_amount">${Number(item.vat_amount).toFixed(2)}</td>
        <td class="text-end font-monospace fw-bold text-success cell-display" data-field="value_after_vat">${Number(item.value_after_vat).toFixed(2)}</td>
        <td class="text-center">
            <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 small">${receipt && receipt.ocr_engine === 'ocr_space' ? 'OCR.Space' : 'Gemini AI'}</span>
        </td>
        <td class="text-center">
            <div class="btn-group btn-group-sm">
                ${receipt && receipt.file_path ? `<button type="button" class="btn btn-outline-primary btn-xs py-1 px-2" onclick="openSideBySide(${item.id})"><i class="fa-solid fa-eye"></i></button>` : ''}
                <button type="button" class="btn btn-outline-secondary btn-xs py-1 px-2 btn-edit-row" onclick="startEditRow(${item.id})"><i class="fa-solid fa-pen-to-square"></i></button>
                <button type="button" class="btn btn-outline-danger btn-xs py-1 px-2" onclick="confirmDeleteRow(${item.id})"><i class="fa-solid fa-trash"></i></button>
            </div>
        </td>
    `;
    tbody.insertBefore(tr, tbody.firstChild);
}

function formatDateDisplay(d) {
    if (!d) return '';
    if (d.includes('/')) return d;
    const parts = d.split('-');
    if (parts.length === 3) {
        return `${parts[2]}/${parts[1]}/${parts[0]}`;
    }
    return d;
}

/**
 * Inline Row Editing
 */
function startEditRow(itemId) {
    const tr = document.getElementById('row-' + itemId);
    if (!tr || tr.classList.contains('row-editing')) return;

    tr.classList.add('row-editing', 'table-info');

    const originalValues = {};
    tr.querySelectorAll('.cell-display').forEach(td => {
        const field = td.getAttribute('data-field');
        originalValues[field] = td.innerText.trim();
    });
    editedRows.set(itemId, originalValues);
    updateUnsavedCounter();

    const cat = originalValues.vat_category || 'G';
    const cal = originalValues.calendar_type || 'G';
    const type = originalValues.purchase_type || '3';
    const tin = originalValues.supplier_tin || '';
    const seller = originalValues.seller_name || '';
    const date = originalValues.receipt_date || '';
    const mrc = originalValues.mrc_no || '';
    const fs = originalValues.fs_no || '';
    const desc = originalValues.item_description || '';
    const uom = originalValues.uom || '9';
    const qty = parseFloat(originalValues.qty.replace(/,/g, '')) || 1.0;
    const uprice = parseFloat(originalValues.unit_price.replace(/,/g, '')) || 0.0;
    const total = parseFloat(originalValues.total_value.replace(/,/g, '')) || 0.0;
    const vat = parseFloat(originalValues.vat_amount.replace(/,/g, '')) || 0.0;
    const afterVat = parseFloat(originalValues.value_after_vat.replace(/,/g, '')) || 0.0;

    tr.querySelector('[data-field="vat_category"]').innerHTML = `
        <select class="form-select form-select-sm p-0 text-center" id="edit-cat-${itemId}" style="width:50px;">
            <option value="G" ${cat === 'G' ? 'selected' : ''}>G</option>
            <option value="S" ${cat === 'S' ? 'selected' : ''}>S</option>
        </select>
    `;

    tr.querySelector('[data-field="calendar_type"]').innerHTML = `
        <select class="form-select form-select-sm p-0 text-center" id="edit-cal-${itemId}" style="width:50px;">
            <option value="G" ${cal === 'G' ? 'selected' : ''}>G</option>
            <option value="E" ${cal === 'E' ? 'selected' : ''}>E</option>
        </select>
    `;

    tr.querySelector('[data-field="purchase_type"]').innerHTML = `
        <input type="number" class="form-control form-control-sm p-1 text-center" id="edit-type-${itemId}" value="${type}" style="width:45px;">
    `;

    tr.querySelector('[data-field="supplier_tin"]').innerHTML = `
        <input type="text" class="form-control form-control-sm p-1 font-monospace fw-bold" id="edit-tin-${itemId}" value="${tin}" style="width:110px;" oninput="validateEditRow(${itemId})">
    `;

    tr.querySelector('[data-field="seller_name"]').innerHTML = `
        <input type="text" class="form-control form-control-sm p-1" id="edit-seller-${itemId}" value="${seller}" style="width:160px;">
    `;

    tr.querySelector('[data-field="receipt_date"]').innerHTML = `
        <input type="text" class="form-control form-control-sm p-1 text-center font-monospace" id="edit-date-${itemId}" value="${date}" placeholder="DD/MM/YYYY" style="width:95px;" oninput="validateEditRow(${itemId})">
    `;

    tr.querySelector('[data-field="mrc_no"]').innerHTML = `
        <input type="text" class="form-control form-control-sm p-1 text-center font-monospace" id="edit-mrc-${itemId}" value="${mrc}" style="width:105px;">
    `;

    tr.querySelector('[data-field="fs_no"]').innerHTML = `
        <input type="text" class="form-control form-control-sm p-1 text-center font-monospace fw-bold" id="edit-fs-${itemId}" value="${fs}" style="width:115px;" oninput="validateEditRow(${itemId})">
    `;

    tr.querySelector('[data-field="item_description"]').innerHTML = `
        <input type="text" class="form-control form-control-sm p-1 fw-semibold text-primary" id="edit-desc-${itemId}" value="${desc}" style="width:180px;">
    `;

    tr.querySelector('[data-field="uom"]').innerHTML = `
        <select class="form-select form-select-sm p-0 text-center" id="edit-uom-${itemId}" style="width:55px;">
            <option value="9" ${uom === '9' ? 'selected' : ''}>9</option>
            <option value="7" ${uom === '7' ? 'selected' : ''}>7</option>
            <option value="2" ${uom === '2' ? 'selected' : ''}>2</option>
            <option value="5" ${uom === '5' ? 'selected' : ''}>5</option>
            <option value="10" ${uom === '10' ? 'selected' : ''}>10</option>
        </select>
    `;

    tr.querySelector('[data-field="qty"]').innerHTML = `
        <input type="number" step="0.01" class="form-control form-control-sm p-1 text-end font-monospace" id="edit-qty-${itemId}" value="${qty}" style="width:75px;" oninput="recalcEditRow(${itemId})">
    `;

    tr.querySelector('[data-field="unit_price"]').innerHTML = `
        <input type="number" step="0.01" class="form-control form-control-sm p-1 text-end font-monospace" id="edit-uprice-${itemId}" value="${uprice}" style="width:90px;" oninput="recalcEditRow(${itemId})">
    `;

    tr.querySelector('[data-field="total_value"]').innerHTML = `
        <input type="number" step="0.01" class="form-control form-control-sm p-1 text-end font-monospace fw-bold" id="edit-total-${itemId}" value="${total}" style="width:100px;" oninput="recalcFromTotal(${itemId})">
    `;

    tr.querySelector('[data-field="vat_amount"]').innerHTML = `
        <input type="number" step="0.01" class="form-control form-control-sm p-1 text-end font-monospace text-muted" id="edit-vat-${itemId}" value="${vat}" style="width:85px;" oninput="validateEditRow(${itemId})">
    `;

    tr.querySelector('[data-field="value_after_vat"]').innerHTML = `
        <input type="number" step="0.01" class="form-control form-control-sm p-1 text-end font-monospace fw-bold text-success" id="edit-aftervat-${itemId}" value="${afterVat}" style="width:105px;" oninput="validateEditRow(${itemId})">
    `;

    const actionsTd = tr.querySelector('td:last-child');
    actionsTd.innerHTML = `
        <div class="btn-group btn-group-sm">
            <button type="button" class="btn btn-success btn-xs py-1 px-2 fw-bold" onclick="saveEditRow(${itemId})" title="Save only this row">
                <i class="fa-solid fa-check me-1"></i>Save
            </button>
            <button type="button" class="btn btn-secondary btn-xs py-1 px-2" onclick="cancelEditRow(${itemId})" title="Cancel editing">
                <i class="fa-solid fa-times"></i>
            </button>
        </div>
    `;

    validateEditRow(itemId);
}

function recalcEditRow(itemId) {
    const qty = parseFloat(document.getElementById(`edit-qty-${itemId}`).value) || 0;
    const uprice = parseFloat(document.getElementById(`edit-uprice-${itemId}`).value) || 0;
    const total = Math.round(qty * uprice * 100) / 100;
    const vat = Math.round(total * 0.15 * 100) / 100;
    const afterVat = Math.round((total + vat) * 100) / 100;

    document.getElementById(`edit-total-${itemId}`).value = total.toFixed(2);
    document.getElementById(`edit-vat-${itemId}`).value = vat.toFixed(2);
    document.getElementById(`edit-aftervat-${itemId}`).value = afterVat.toFixed(2);

    validateEditRow(itemId);
}

function recalcFromTotal(itemId) {
    const total = parseFloat(document.getElementById(`edit-total-${itemId}`).value) || 0;
    const vat = Math.round(total * 0.15 * 100) / 100;
    const afterVat = Math.round((total + vat) * 100) / 100;

    document.getElementById(`edit-vat-${itemId}`).value = vat.toFixed(2);
    document.getElementById(`edit-aftervat-${itemId}`).value = afterVat.toFixed(2);

    validateEditRow(itemId);
}

function validateEditRow(itemId) {
    const qty = parseFloat(document.getElementById(`edit-qty-${itemId}`).value) || 0;
    const uprice = parseFloat(document.getElementById(`edit-uprice-${itemId}`).value) || 0;
    const total = parseFloat(document.getElementById(`edit-total-${itemId}`).value) || 0;
    const vat = parseFloat(document.getElementById(`edit-vat-${itemId}`).value) || 0;
    const afterVat = parseFloat(document.getElementById(`edit-aftervat-${itemId}`).value) || 0;
    const tin = (document.getElementById(`edit-tin-${itemId}`).value || '').replace(/\D/g, '');
    const fs = document.getElementById(`edit-fs-${itemId}`).value || '';

    const errors = [];
    if (qty > 0 && uprice > 0 && Math.abs(Math.round(qty * uprice * 100)/100 - total) > 0.05) {
        errors.push('Qty x Unit Price ≠ Total Value');
    }
    if (total > 0 && Math.abs(Math.round(total * 0.15 * 100)/100 - vat) > 0.05) {
        errors.push('Total Value x 15% ≠ VAT');
    }
    if ((total > 0 || vat > 0) && Math.abs(Math.round((total + vat) * 100)/100 - afterVat) > 0.05) {
        errors.push('Total Value + VAT ≠ Value After VAT');
    }
    if (!tin || tin.length !== 10) {
        errors.push('Supplier TIN must be 10 digits');
    }
    if (!fs) {
        errors.push('FS Number is missing');
    }

    const tr = document.getElementById('row-' + itemId);
    const statusTd = tr.querySelector('td:nth-child(2)');
    if (errors.length > 0) {
        statusTd.innerHTML = `<span class="badge bg-warning text-dark border border-warning" title="${errors.join('; ')}"><i class="fa-solid fa-triangle-exclamation"></i></span>`;
    } else {
        statusTd.innerHTML = `<span class="badge bg-success bg-opacity-10 text-success border border-success"><i class="fa-solid fa-check"></i></span>`;
    }
}

function saveEditRow(itemId) {
    const payload = {
        id: itemId,
        vat_category: document.getElementById(`edit-cat-${itemId}`).value,
        calendar_type: document.getElementById(`edit-cal-${itemId}`).value,
        purchase_type: parseInt(document.getElementById(`edit-type-${itemId}`).value) || 3,
        supplier_tin: document.getElementById(`edit-tin-${itemId}`).value,
        seller_name: document.getElementById(`edit-seller-${itemId}`).value,
        receipt_date: document.getElementById(`edit-date-${itemId}`).value,
        mrc_no: document.getElementById(`edit-mrc-${itemId}`).value,
        fs_no: document.getElementById(`edit-fs-${itemId}`).value,
        item_description: document.getElementById(`edit-desc-${itemId}`).value,
        uom: document.getElementById(`edit-uom-${itemId}`).value,
        qty: parseFloat(document.getElementById(`edit-qty-${itemId}`).value) || 0,
        unit_price: parseFloat(document.getElementById(`edit-uprice-${itemId}`).value) || 0,
        total_value: parseFloat(document.getElementById(`edit-total-${itemId}`).value) || 0,
        vat_amount: parseFloat(document.getElementById(`edit-vat-${itemId}`).value) || 0,
        value_after_vat: parseFloat(document.getElementById(`edit-aftervat-${itemId}`).value) || 0,
    };

    fetch(SAVE_ITEM_URL, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': CSRF_TOKEN,
            'Accept': 'application/json',
        },
        body: JSON.stringify(payload)
    })
    .then(r => r.json())
    .then(res => {
        if (res.success) {
            editedRows.delete(itemId);
            updateUnsavedCounter();
            renderRowDisplay(itemId, res.item, res.errors);
            showToast('Row updated successfully!', 'success');
        } else {
            showToast(res.message || 'Error saving row', 'danger');
        }
    })
    .catch(err => {
        showToast('Network error saving row', 'danger');
    });
}

function cancelEditRow(itemId) {
    const tr = document.getElementById('row-' + itemId);
    const original = editedRows.get(itemId);
    if (!original) return;

    tr.querySelector('[data-field="vat_category"]').innerHTML = `<span class="badge bg-light text-dark border">${original.vat_category}</span>`;
    tr.querySelector('[data-field="calendar_type"]').innerHTML = `<span class="badge bg-light text-dark border">${original.calendar_type}</span>`;
    tr.querySelector('[data-field="purchase_type"]').innerHTML = `<span class="badge bg-light text-dark border">${original.purchase_type}</span>`;
    tr.querySelector('[data-field="supplier_tin"]').textContent = original.supplier_tin;
    tr.querySelector('[data-field="seller_name"]').textContent = original.seller_name;
    tr.querySelector('[data-field="receipt_date"]').textContent = original.receipt_date;
    tr.querySelector('[data-field="mrc_no"]').textContent = original.mrc_no;
    tr.querySelector('[data-field="fs_no"]').textContent = original.fs_no;
    tr.querySelector('[data-field="item_description"]').textContent = original.item_description;
    tr.querySelector('[data-field="uom"]').textContent = original.uom;
    tr.querySelector('[data-field="qty"]').textContent = original.qty;
    tr.querySelector('[data-field="unit_price"]').textContent = original.unit_price;
    tr.querySelector('[data-field="total_value"]').textContent = original.total_value;
    tr.querySelector('[data-field="vat_amount"]').textContent = original.vat_amount;
    tr.querySelector('[data-field="value_after_vat"]').textContent = original.value_after_vat;

    tr.classList.remove('row-editing', 'table-info');

    const receiptId = tr.getAttribute('data-receipt-id');
    tr.querySelector('td:last-child').innerHTML = `
        <div class="btn-group btn-group-sm">
            ${receiptId ? `<button type="button" class="btn btn-outline-primary btn-xs py-1 px-2" onclick="openSideBySide(${itemId})"><i class="fa-solid fa-eye"></i></button>` : ''}
            <button type="button" class="btn btn-outline-secondary btn-xs py-1 px-2 btn-edit-row" onclick="startEditRow(${itemId})"><i class="fa-solid fa-pen-to-square"></i></button>
            <button type="button" class="btn btn-outline-danger btn-xs py-1 px-2" onclick="confirmDeleteRow(${itemId})"><i class="fa-solid fa-trash"></i></button>
        </div>
    `;

    editedRows.delete(itemId);
    updateUnsavedCounter();
}

function renderRowDisplay(itemId, item, errors = []) {
    const tr = document.getElementById('row-' + itemId);
    const r = item.receipt;
    const isFlagged = errors.length > 0 || (r && r.needs_review);

    tr.className = isFlagged ? 'table-warning bg-opacity-25' : '';
    tr.classList.remove('row-editing', 'table-info');

    tr.querySelector('td:nth-child(2)').innerHTML = isFlagged
        ? `<span class="badge bg-warning text-dark border border-warning" title="${errors.join('; ')}"><i class="fa-solid fa-triangle-exclamation me-1"></i>Review</span>`
        : `<span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25"><i class="fa-solid fa-check me-1"></i>Valid</span>`;

    tr.querySelector('[data-field="vat_category"]').innerHTML = `<span class="badge bg-light text-dark border">${item.vat_category || 'G'}</span>`;
    tr.querySelector('[data-field="calendar_type"]').innerHTML = `<span class="badge bg-light text-dark border">${item.calendar_type || 'G'}</span>`;
    tr.querySelector('[data-field="purchase_type"]').innerHTML = `<span class="badge bg-light text-dark border">${item.purchase_type || 3}</span>`;
    tr.querySelector('[data-field="supplier_tin"]').textContent = r ? r.vendor_tin : '';
    tr.querySelector('[data-field="seller_name"]').textContent = r ? r.vendor_name : 'General Merchant';
    tr.querySelector('[data-field="receipt_date"]').textContent = r && r.receipt_date ? formatDateDisplay(r.receipt_date) : '';
    tr.querySelector('[data-field="mrc_no"]').textContent = r ? (r.mrc_no || '') : '';
    tr.querySelector('[data-field="fs_no"]').textContent = r ? (r.fs_no || '') : '';
    tr.querySelector('[data-field="item_description"]').textContent = item.item_description;
    tr.querySelector('[data-field="uom"]').textContent = item.uom || '9';
    tr.querySelector('[data-field="qty"]').textContent = Number(item.qty).toFixed(2);
    tr.querySelector('[data-field="unit_price"]').textContent = Number(item.unit_price).toFixed(2);
    tr.querySelector('[data-field="total_value"]').textContent = Number(item.total_value).toFixed(2);
    tr.querySelector('[data-field="vat_amount"]').textContent = Number(item.vat_amount).toFixed(2);
    tr.querySelector('[data-field="value_after_vat"]').textContent = Number(item.value_after_vat).toFixed(2);

    tr.querySelector('td:last-child').innerHTML = `
        <div class="btn-group btn-group-sm">
            ${r && r.file_path ? `<button type="button" class="btn btn-outline-primary btn-xs py-1 px-2" onclick="openSideBySide(${item.id})"><i class="fa-solid fa-eye"></i></button>` : ''}
            <button type="button" class="btn btn-outline-secondary btn-xs py-1 px-2 btn-edit-row" onclick="startEditRow(${item.id})"><i class="fa-solid fa-pen-to-square"></i></button>
            <button type="button" class="btn btn-outline-danger btn-xs py-1 px-2" onclick="confirmDeleteRow(${item.id})"><i class="fa-solid fa-trash"></i></button>
        </div>
    `;
}

function confirmDeleteRow(itemId) {
    if (!confirm('Are you sure you want to delete this row? This action cannot be undone.')) return;

    fetch(`${DESTROY_ITEM_BASE}/${itemId}`, {
        method: 'DELETE',
        headers: {
            'X-CSRF-TOKEN': CSRF_TOKEN,
            'Accept': 'application/json',
        }
    })
    .then(r => r.json())
    .then(res => {
        if (res.success) {
            const tr = document.getElementById('row-' + itemId);
            if (tr) tr.remove();
            editedRows.delete(itemId);
            updateUnsavedCounter();
            updateStatsDisplay();
            showToast('Row deleted successfully.', 'info');
        } else {
            showToast(res.message || 'Error deleting row', 'danger');
        }
    })
    .catch(err => {
        showToast('Network error deleting row', 'danger');
    });
}

/**
 * Save All Dirty Rows
 */
document.getElementById('btn-save-all').addEventListener('click', () => {
    if (editedRows.size === 0) return;

    const rowsPayload = [];
    editedRows.forEach((orig, itemId) => {
        rowsPayload.push({
            id: itemId,
            vat_category: document.getElementById(`edit-cat-${itemId}`) ? document.getElementById(`edit-cat-${itemId}`).value : orig.vat_category,
            calendar_type: document.getElementById(`edit-cal-${itemId}`) ? document.getElementById(`edit-cal-${itemId}`).value : orig.calendar_type,
            purchase_type: document.getElementById(`edit-type-${itemId}`) ? parseInt(document.getElementById(`edit-type-${itemId}`).value) : orig.purchase_type,
            supplier_tin: document.getElementById(`edit-tin-${itemId}`) ? document.getElementById(`edit-tin-${itemId}`).value : orig.supplier_tin,
            seller_name: document.getElementById(`edit-seller-${itemId}`) ? document.getElementById(`edit-seller-${itemId}`).value : orig.seller_name,
            receipt_date: document.getElementById(`edit-date-${itemId}`) ? document.getElementById(`edit-date-${itemId}`).value : orig.receipt_date,
            mrc_no: document.getElementById(`edit-mrc-${itemId}`) ? document.getElementById(`edit-mrc-${itemId}`).value : orig.mrc_no,
            fs_no: document.getElementById(`edit-fs-${itemId}`) ? document.getElementById(`edit-fs-${itemId}`).value : orig.fs_no,
            item_description: document.getElementById(`edit-desc-${itemId}`) ? document.getElementById(`edit-desc-${itemId}`).value : orig.item_description,
            uom: document.getElementById(`edit-uom-${itemId}`) ? document.getElementById(`edit-uom-${itemId}`).value : orig.uom,
            qty: document.getElementById(`edit-qty-${itemId}`) ? parseFloat(document.getElementById(`edit-qty-${itemId}`).value) : parseFloat(orig.qty),
            unit_price: document.getElementById(`edit-uprice-${itemId}`) ? parseFloat(document.getElementById(`edit-uprice-${itemId}`).value) : parseFloat(orig.unit_price),
            total_value: document.getElementById(`edit-total-${itemId}`) ? parseFloat(document.getElementById(`edit-total-${itemId}`).value) : parseFloat(orig.total_value),
            vat_amount: document.getElementById(`edit-vat-${itemId}`) ? parseFloat(document.getElementById(`edit-vat-${itemId}`).value) : parseFloat(orig.vat_amount),
            value_after_vat: document.getElementById(`edit-aftervat-${itemId}`) ? parseFloat(document.getElementById(`edit-aftervat-${itemId}`).value) : parseFloat(orig.value_after_vat),
        });
    });

    fetch(SAVE_ALL_URL, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': CSRF_TOKEN,
            'Accept': 'application/json',
        },
        body: JSON.stringify({ rows: rowsPayload })
    })
    .then(r => r.json())
    .then(res => {
        if (res.success) {
            editedRows.clear();
            updateUnsavedCounter();
            showToast(res.message, 'success');
            setTimeout(() => window.location.reload(), 600);
        } else {
            showToast(res.message || 'Error saving rows', 'danger');
        }
    })
    .catch(err => {
        showToast('Network error saving rows', 'danger');
    });
});

function updateUnsavedCounter() {
    const count = editedRows.size;
    const btn = document.getElementById('btn-save-all');
    const badge = document.getElementById('unsaved-count');
    if (count > 0) {
        btn.classList.remove('d-none');
        badge.textContent = count;
    } else {
        btn.classList.add('d-none');
    }
}

/**
 * Side-by-Side Modal Preview
 */
function openSideBySide(itemId) {
    const tr = document.getElementById('row-' + itemId);
    const receiptId = tr ? tr.getAttribute('data-receipt-id') : null;
    if (!receiptId) return;

    fetch(`${SHOW_RECEIPT_BASE}/${receiptId}`, {
        headers: { 'Accept': 'application/json' }
    })
    .then(r => r.json())
    .then(res => {
        if (!res.success) return;
        const r = res.receipt;
        const items = res.items || [];
        const thisItem = items.find(it => it.id === itemId) || items[0] || {};

        document.getElementById('sbs-fs-badge').textContent = 'FS: ' + (r.fs_no || 'None');
        document.getElementById('sbs-field-tin').textContent = r.vendor_tin || '—';
        document.getElementById('sbs-field-seller').textContent = r.vendor_name || '—';
        document.getElementById('sbs-field-date').textContent = r.receipt_date ? formatDateDisplay(r.receipt_date) : '—';
        document.getElementById('sbs-field-mrc').textContent = r.mrc_no || '—';
        document.getElementById('sbs-field-fs').textContent = r.fs_no || '—';
        document.getElementById('sbs-field-desc').textContent = thisItem.item_description || r.description || '—';
        document.getElementById('sbs-field-qty').textContent = Number(thisItem.qty || 1).toFixed(2);
        document.getElementById('sbs-field-uprice').textContent = Number(thisItem.unit_price || 0).toFixed(2);
        document.getElementById('sbs-field-subtotal').textContent = Number(thisItem.total_value || r.subtotal || 0).toFixed(2) + ' ETB';
        document.getElementById('sbs-field-vat').textContent = Number(thisItem.vat_amount || r.vat_amount || 0).toFixed(2) + ' ETB';
        document.getElementById('sbs-field-total').textContent = Number(thisItem.value_after_vat || r.total_amount || 0).toFixed(2) + ' ETB';
        document.getElementById('sbs-raw-text').value = r.ocr_raw_text || 'No transcribed text available.';

        const errors = [];
        const total = parseFloat(thisItem.total_value || r.subtotal || 0);
        const vat = parseFloat(thisItem.vat_amount || r.vat_amount || 0);
        const totalAfter = parseFloat(thisItem.value_after_vat || r.total_amount || 0);
        const qty = parseFloat(thisItem.qty || 1);
        const uprice = parseFloat(thisItem.unit_price || 0);

        if (qty > 0 && uprice > 0 && Math.abs(Math.round(qty * uprice * 100)/100 - total) > 0.05) {
            errors.push('Qty x Unit Price does not equal Total Value');
        }
        if (total > 0 && Math.abs(Math.round(total * 0.15 * 100)/100 - vat) > 0.05) {
            errors.push('Total Value x 15% does not equal VAT amount');
        }
        if ((total > 0 || vat > 0) && Math.abs(Math.round((total + vat) * 100)/100 - totalAfter) > 0.05) {
            errors.push('Total Value + VAT does not equal Value After VAT');
        }
        if (!r.vendor_tin || r.vendor_tin.length !== 10) {
            errors.push('Supplier TIN is not 10 digits');
        }

        const vBox = document.getElementById('sbs-validation-box');
        if (errors.length > 0) {
            vBox.className = 'p-3 rounded-3 mb-3 border border-warning bg-warning bg-opacity-10 text-dark';
            vBox.innerHTML = `
                <div class="fw-bold text-danger mb-1"><i class="fa-solid fa-triangle-exclamation me-1"></i>Validation Warnings Detected:</div>
                <ul class="mb-0 small ps-3">
                    ${errors.map(e => `<li>${e}</li>`).join('')}
                </ul>
            `;
        } else {
            vBox.className = 'p-3 rounded-3 mb-3 border border-success bg-success bg-opacity-10 text-success';
            vBox.innerHTML = `<i class="fa-solid fa-circle-check me-1"></i><strong>All Checks Passed:</strong> Math and fiscal fields verified.`;
        }

        const imgEl = document.getElementById('sbs-preview-img');
        const pdfEl = document.getElementById('sbs-preview-pdf');
        resetZoom();

        if (res.file_url.toLowerCase().endsWith('.pdf') || (r.file_type === 'pdf')) {
            imgEl.classList.add('d-none');
            pdfEl.classList.remove('d-none');
            pdfEl.src = res.file_url;
        } else {
            pdfEl.classList.add('d-none');
            imgEl.classList.remove('d-none');
            imgEl.src = res.file_url;
        }

        const modal = new bootstrap.Modal(document.getElementById('sideBySideModal'));
        modal.show();
    });
}

function zoomImage(factor) {
    currentZoom = Math.max(0.4, Math.min(4.0, currentZoom * factor));
    applyImageTransform();
}

function rotateImage() {
    currentRotation = (currentRotation + 90) % 360;
    applyImageTransform();
}

function resetZoom() {
    currentZoom = 1.0;
    currentRotation = 0;
    applyImageTransform();
}

function applyImageTransform() {
    const img = document.getElementById('sbs-preview-img');
    if (img) {
        img.style.transform = `scale(${currentZoom}) rotate(${currentRotation}deg)`;
    }
}

/**
 * Export Helper (Excel / CSV)
 */
function exportData(format, scope) {
    const baseUrl = (format === 'excel') ? EXPORT_EXCEL_URL : EXPORT_CSV_URL;
    const params = new URLSearchParams();

    if (scope === 'selected') {
        const selectedIds = Array.from(document.querySelectorAll('.row-checkbox:checked')).map(cb => cb.value);
        if (selectedIds.length === 0) {
            alert('Please select at least one row using the checkboxes.');
            return;
        }
        params.append('selected_ids', selectedIds.join(','));
    } else if (scope === 'filtered') {
        const form = document.getElementById('filter-form');
        const formData = new FormData(form);
        for (let [k, v] of formData.entries()) {
            if (v) params.append(k, v);
        }
        if (document.getElementById('filter-needs-review').checked) {
            params.append('needs_review', '1');
        }
    }

    window.location.href = baseUrl + (params.toString() ? '?' + params.toString() : '');
}

function toggleSelectAll(masterCb) {
    document.querySelectorAll('.row-checkbox').forEach(cb => {
        cb.checked = masterCb.checked;
    });
    updateSelectedCount();
}

function updateSelectedCount() {
    const checked = document.querySelectorAll('.row-checkbox:checked').length;
    document.querySelectorAll('.selected-count-badge').forEach(el => el.textContent = checked);
}

function applyFilters() {
    const form = document.getElementById('filter-form');
    if (document.getElementById('filter-needs-review').checked) {
        let hidden = form.querySelector('input[name="needs_review"]');
        if (!hidden) {
            hidden = document.createElement('input');
            hidden.type = 'hidden';
            hidden.name = 'needs_review';
            form.appendChild(hidden);
        }
        hidden.value = '1';
    } else {
        const hidden = form.querySelector('input[name="needs_review"]');
        if (hidden) hidden.remove();
    }
    form.submit();
}

function togglePasswordVisibility(inputId) {
    const input = document.getElementById(inputId);
    input.type = input.type === 'password' ? 'text' : 'password';
}

function testApiKey(engine) {
    const inputId = engine === 'gemini' ? 'input_gemini_key' : 'input_ocr_space_key';
    const resultId = engine === 'gemini' ? 'gemini-test-result' : 'ocrspace-test-result';
    const key = document.getElementById(inputId).value.trim();
    const resBox = document.getElementById(resultId);

    resBox.classList.remove('d-none', 'text-success', 'text-danger');
    resBox.className = 'mt-2 small text-primary';
    resBox.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-1"></i>Testing connectivity...';

    fetch(TEST_KEY_URL, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': CSRF_TOKEN,
            'Accept': 'application/json'
        },
        body: JSON.stringify({ engine: engine, key: key })
    })
    .then(r => r.json())
    .then(res => {
        if (res.success) {
            resBox.className = 'mt-2 small text-success fw-bold';
            resBox.innerHTML = '<i class="fa-solid fa-check-circle me-1"></i>' + res.message;
        } else {
            resBox.className = 'mt-2 small text-danger fw-bold';
            resBox.innerHTML = '<i class="fa-solid fa-times-circle me-1"></i>' + res.message;
        }
    })
    .catch(err => {
        resBox.className = 'mt-2 small text-danger fw-bold';
        resBox.innerHTML = '<i class="fa-solid fa-times-circle me-1"></i>Connection error testing key';
    });
}

function saveSettings(e) {
    e.preventDefault();
    const geminiKey = document.getElementById('input_gemini_key').value.trim();
    const ocrSpaceKey = document.getElementById('input_ocr_space_key').value.trim();

    fetch(SAVE_SETTINGS_URL, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': CSRF_TOKEN,
            'Accept': 'application/json'
        },
        body: JSON.stringify({
            gemini_api_key: geminiKey,
            ocr_space_api_key: ocrSpaceKey,
        })
    })
    .then(r => r.json())
    .then(res => {
        if (res.success) {
            showToast('API keys saved securely.', 'success');
            const modal = bootstrap.Modal.getInstance(document.getElementById('settingsModal'));
            if (modal) modal.hide();
        } else {
            showToast(res.message || 'Error saving keys', 'danger');
        }
    })
    .catch(err => {
        showToast('Network error saving settings', 'danger');
    });
}

function showToast(message, type = 'info') {
    let container = document.getElementById('toast-container');
    if (!container) {
        container = document.createElement('div');
        container.id = 'toast-container';
        container.className = 'position-fixed bottom-0 end-0 p-3';
        container.style.zIndex = '9999';
        document.body.appendChild(container);
    }

    const toastEl = document.createElement('div');
    toastEl.className = `toast align-items-center text-white bg-${type} border-0 show shadow-sm mb-2`;
    toastEl.setAttribute('role', 'alert');
    toastEl.innerHTML = `
        <div class="d-flex">
            <div class="toast-body small fw-semibold">
                ${message}
            </div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" onclick="this.closest('.toast').remove()"></button>
        </div>
    `;
    container.appendChild(toastEl);
    setTimeout(() => toastEl.remove(), 4000);
}

function updateStatsDisplay() {
    const rows = document.querySelectorAll('#table-body tr[id^="row-"]');
    const countEl = document.getElementById('stat-total-items');
    const tableCountEl = document.getElementById('table-row-count');
    if (countEl) countEl.textContent = rows.length;
    if (tableCountEl) tableCountEl.textContent = rows.length + ' rows';

    let totalVal = 0;
    let totalVat = 0;
    let flagged = 0;

    rows.forEach(tr => {
        const valTd = tr.querySelector('[data-field="total_value"]');
        const vatTd = tr.querySelector('[data-field="vat_amount"]');
        if (valTd) totalVal += parseFloat(valTd.textContent.replace(/,/g, '')) || 0;
        if (vatTd) totalVat += parseFloat(vatTd.textContent.replace(/,/g, '')) || 0;
        if (tr.classList.contains('table-warning')) flagged++;
    });

    const statValEl = document.getElementById('stat-total-value');
    const statVatEl = document.getElementById('stat-total-vat');
    const statFlagEl = document.getElementById('stat-flagged-count');

    if (statValEl) statValEl.innerHTML = totalVal.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' <small class="fs-6 text-muted">ETB</small>';
    if (statVatEl) statVatEl.innerHTML = totalVat.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' <small class="fs-6 text-muted">ETB</small>';
    if (statFlagEl) statFlagEl.textContent = flagged;
}
</script>
@endpush
