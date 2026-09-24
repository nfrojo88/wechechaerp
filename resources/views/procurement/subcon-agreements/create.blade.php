@extends('layouts.app')
@section('title', 'Create Subcontractor Agreement & Upload Document')
@section('content')

<div class="container-fluid px-4 py-3">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h4 class="fw-bold mb-1" style="color:var(--brand-800)">
                <i class="fa-solid fa-file-signature text-primary me-2"></i>Create Subcontractor Agreement
            </h4>
            <p class="text-muted small mb-0">Record subcontractor agreement details, define scope of work, and upload signed contract documents.</p>
        </div>
        <a href="{{ route('subcon-agreements.index') }}" class="btn btn-outline-secondary btn-sm shadow-sm">
            <i class="fa-solid fa-arrow-left me-1"></i>Back to Agreements
        </a>
    </div>

    @if($errors->any())
        <div class="alert alert-danger alert-dismissible fade show mb-4 shadow-sm" role="alert">
            <div class="d-flex align-items-center">
                <i class="fa-solid fa-circle-exclamation fs-5 me-2"></i>
                <div>
                    <strong>Please check the following errors:</strong>
                    <ul class="mb-0 mt-1 small">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <form action="{{ route('subcon-agreements.store') }}" method="POST" enctype="multipart/form-data" id="subconForm">
        @csrf

        <div class="row g-4">
            <!-- Left Column: Main Agreement Details & File Upload -->
            <div class="col-lg-8">
                <!-- Agreement Core Details -->
                <div class="card shadow-sm border-0 rounded-3 mb-4">
                    <div class="card-header bg-white py-3 border-bottom d-flex align-items-center">
                        <i class="fa-solid fa-building-circle-check text-primary me-2"></i>
                        <h6 class="fw-bold mb-0 text-dark">Agreement Core Details</h6>
                    </div>
                    <div class="card-body p-4">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold text-dark">Project <span class="text-danger">*</span></label>
                                <select name="project_id" class="form-select @error('project_id') is-invalid @enderror" required>
                                    <option value="">-- Select Project --</option>
                                    @foreach($projects as $project)
                                    <option value="{{ $project->id }}" {{ old('project_id') == $project->id ? 'selected' : '' }}>
                                        {{ $project->project_name ?? $project->name }} ({{ $project->code ?? 'PRJ-' . $project->id }}){{ $project->status && strtolower($project->status) !== 'active' ? ' [' . ucfirst(str_replace('_', ' ', $project->status)) . ']' : '' }}
                                    </option>
                                    @endforeach
                                </select>
                                @error('project_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fw-semibold text-dark">Subcontractor / Supplier</label>
                                <select name="supplier_id" id="supplierSelect" class="form-select @error('supplier_id') is-invalid @enderror">
                                    <option value="">-- Select from Registered Suppliers / Subcontractors --</option>
                                    @foreach($suppliers as $supplier)
                                    <option value="{{ $supplier->id }}" {{ old('supplier_id') == $supplier->id ? 'selected' : '' }} data-contact="{{ $supplier->phone ?? $supplier->email ?? '' }}" data-person="{{ $supplier->contact_person ?? '' }}">
                                        {{ $supplier->name }} {{ $supplier->contact_person ? '(' . $supplier->contact_person . ')' : '' }}
                                    </option>
                                    @endforeach
                                </select>
                                <small class="text-muted">Or type new subcontractor below if not in supplier directory.</small>
                                @error('supplier_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fw-semibold text-dark">Subcontractor Business / Legal Name</label>
                                <input type="text" name="subcontractor_name" id="subcontractorNameInput" class="form-control @error('subcontractor_name') is-invalid @enderror" 
                                       placeholder="e.g. ABC Finishing & Electromechanical Works" value="{{ old('subcontractor_name') }}">
                                @error('subcontractor_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fw-semibold text-dark">Contact Person / Phone / Email</label>
                                <input type="text" name="subcontractor_contact" id="subcontractorContactInput" class="form-control @error('subcontractor_contact') is-invalid @enderror" 
                                       placeholder="e.g. Ato Abebe (+251 911 000 000)" value="{{ old('subcontractor_contact') }}">
                                @error('subcontractor_contact')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>

                            <div class="col-12">
                                <label class="form-label fw-semibold text-dark">Scope of Work &amp; Description <span class="text-danger">*</span></label>
                                <textarea name="work_description" class="form-control @error('work_description') is-invalid @enderror" 
                                          rows="3" placeholder="Describe the specific subcontracting tasks, milestones, deliverables, site area..." required>{{ old('work_description') }}</textarea>
                                @error('work_description')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fw-semibold text-dark">Start Date <span class="text-danger">*</span></label>
                                <input type="date" name="start_date" class="form-control @error('start_date') is-invalid @enderror" 
                                       value="{{ old('start_date', date('Y-m-d')) }}" required>
                                @error('start_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fw-semibold text-dark">End / Target Completion Date</label>
                                <input type="date" name="end_date" class="form-control @error('end_date') is-invalid @enderror" 
                                       value="{{ old('end_date') }}">
                                @error('end_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Signed Agreement Document Upload -->
                <div class="card shadow-sm border-0 rounded-3 mb-4">
                    <div class="card-header bg-white py-3 border-bottom d-flex align-items-center justify-content-between">
                        <div class="d-flex align-items-center">
                            <i class="fa-solid fa-cloud-arrow-up text-success me-2"></i>
                            <h6 class="fw-bold mb-0 text-dark">Signed Agreement / Contract Document</h6>
                        </div>
                        <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 px-2 py-1">Upload Document</span>
                    </div>
                    <div class="card-body p-4">
                        <div class="border border-2 border-dashed rounded-3 p-4 text-center bg-light" id="dropZone">
                            <i class="fa-solid fa-file-pdf text-danger fa-3x mb-3"></i>
                            <h6 class="fw-bold text-dark mb-1">Upload Scanned Agreement / Signed Contract</h6>
                            <p class="text-muted small mb-3">Supported formats: PDF, DOCX, DOC, JPG, JPEG, PNG, WEBP (Max 20MB)</p>
                            
                            <input type="file" name="agreement_file" id="agreementFileInput" class="form-control d-none @error('agreement_file') is-invalid @enderror" 
                                   accept=".pdf,.doc,.docx,.jpg,.jpeg,.png,.webp" onchange="handleFileSelected(this)">
                            
                            <button type="button" class="btn btn-outline-primary btn-sm px-4 py-2" onclick="document.getElementById('agreementFileInput').click()">
                                <i class="fa-solid fa-folder-open me-1"></i> Browse &amp; Select File
                            </button>

                            <div id="fileSelectedInfo" class="mt-3 p-2 bg-white rounded border d-none">
                                <div class="d-flex align-items-center justify-content-between">
                                    <div class="d-flex align-items-center text-start">
                                        <i class="fa-solid fa-file text-primary fs-4 me-2" id="fileIcon"></i>
                                        <div>
                                            <strong class="d-block text-dark small" id="selectedFileName">filename.pdf</strong>
                                            <span class="text-muted small" id="selectedFileSize">0 KB</span>
                                        </div>
                                    </div>
                                    <button type="button" class="btn btn-sm btn-link text-danger text-decoration-none" onclick="clearSelectedFile()">
                                        <i class="fa-solid fa-xmark"></i> Remove
                                    </button>
                                </div>
                            </div>
                            @error('agreement_file')<div class="invalid-feedback d-block mt-2">{{ $message }}</div>@enderror
                        </div>
                    </div>
                </div>

                <!-- Work Items / BOQ Breakdown (Optional) -->
                <div class="card shadow-sm border-0 rounded-3 mb-4">
                    <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <div class="d-flex align-items-center">
                            <i class="fa-solid fa-list-check text-info me-2"></i>
                            <h6 class="fw-bold mb-0 text-dark">BOQ / Task Items Breakdown <small class="text-muted fw-normal">(Optional)</small></h6>
                        </div>
                        <button type="button" class="btn btn-sm btn-outline-primary" onclick="addManualRow()">
                            <i class="fa-solid fa-plus me-1"></i>Add Task Line
                        </button>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0" id="itemsTable">
                                <thead class="table-light">
                                    <tr>
                                        <th style="min-width:200px;">Task Description</th>
                                        <th style="width:120px;">Qty</th>
                                        <th style="width:110px;">Unit</th>
                                        <th style="width:140px;">Unit Rate (ETB)</th>
                                        <th style="width:140px;" class="text-end">Total (ETB)</th>
                                        <th style="width:50px;"></th>
                                    </tr>
                                </thead>
                                <tbody id="itemsBody">
                                    <!-- Dynamic rows -->
                                </tbody>
                                <tfoot class="table-light">
                                    <tr>
                                        <td colspan="4" class="text-end fw-bold">Items Subtotal:</td>
                                        <td class="text-end fw-bold text-primary" id="itemsSubtotal">0.00 ETB</td>
                                        <td></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                        <div class="p-3 bg-light border-top text-muted small">
                            <i class="fa-solid fa-circle-info me-1 text-primary"></i> If line items are added, their total will calculate the agreement value automatically. If left empty, the Total Contract Value on the right will be used.
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Column: Financials, Retention & Settings -->
            <div class="col-lg-4">
                <!-- Financials & Retention -->
                <div class="card shadow-sm border-0 rounded-3 mb-4 sticky-top" style="top: 20px; z-index: 10;">
                    <div class="card-header bg-white py-3 border-bottom">
                        <h6 class="fw-bold mb-0 text-dark">
                            <i class="fa-solid fa-calculator text-primary me-2"></i>Financials &amp; Terms
                        </h6>
                    </div>
                    <div class="card-body p-4">
                        <!-- m² Rate & Estimation (Optional) -->
                        <div class="p-3 bg-light rounded-3 mb-3 border">
                            <div class="d-flex align-items-center justify-content-between mb-2">
                                <label class="form-label fw-bold text-dark small mb-0">
                                    <i class="fa-solid fa-ruler-combined text-primary me-1"></i>m² Rate &amp; Area Estimation <span class="text-muted fw-normal">(Optional)</span>
                                </label>
                                <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25" style="font-size:0.68rem;">Area-Based</span>
                            </div>
                            <div class="row g-2">
                                <div class="col-6">
                                    <label class="form-label text-muted small mb-1 fw-semibold" style="font-size:0.75rem;">Price per m² (ETB)</label>
                                    <div class="input-group input-group-sm">
                                        <span class="input-group-text bg-white px-2 text-muted">ETB</span>
                                        <input type="number" step="0.01" min="0" name="unit_price_per_m2" id="unitPriceM2Input" 
                                               class="form-control @error('unit_price_per_m2') is-invalid @enderror" 
                                               placeholder="0.00" value="{{ old('unit_price_per_m2') }}" oninput="calculateFromM2()">
                                    </div>
                                    @error('unit_price_per_m2')<div class="invalid-feedback d-block" style="font-size:0.7rem;">{{ $message }}</div>@enderror
                                </div>
                                <div class="col-6">
                                    <label class="form-label text-muted small mb-1 fw-semibold" style="font-size:0.75rem;">Estimated Total m²</label>
                                    <div class="input-group input-group-sm">
                                        <input type="number" step="0.01" min="0" name="estimated_total_m2" id="estimatedTotalM2Input" 
                                               class="form-control @error('estimated_total_m2') is-invalid @enderror" 
                                               placeholder="0.00" value="{{ old('estimated_total_m2') }}" oninput="calculateFromM2()">
                                        <span class="input-group-text bg-white px-2 text-muted">m²</span>
                                    </div>
                                    @error('estimated_total_m2')<div class="invalid-feedback d-block" style="font-size:0.7rem;">{{ $message }}</div>@enderror
                                </div>
                            </div>
                            <div id="m2CalculationBadge" class="mt-2 text-primary small d-none" style="font-size:0.72rem;">
                                <i class="fa-solid fa-calculator me-1"></i>
                                <span id="m2CalculationText"></span>
                            </div>
                        </div>

                        <!-- Base Amount (Before VAT) -->
                        <div class="mb-3">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <label class="form-label fw-semibold text-dark mb-0">Base Amount (Before VAT)</label>
                                <span class="badge bg-light text-muted border small" style="font-size:0.68rem;" id="baseAmountBadge">Excl. VAT</span>
                            </div>
                            <div class="input-group">
                                <span class="input-group-text bg-light fw-bold text-muted">ETB</span>
                                <input type="number" step="0.01" min="0" name="base_amount" id="baseAmountInput" 
                                       class="form-control fw-bold text-dark @error('base_amount') is-invalid @enderror" 
                                       placeholder="0.00" value="{{ old('base_amount', 0) }}" oninput="updateTaxAndTotals('from_base')">
                            </div>
                            <small class="text-muted">Net agreed work amount (or auto-calculated from m² above).</small>
                            @error('base_amount')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <!-- VAT Treatment & Rate (ቫት) -->
                        <div class="p-3 bg-light rounded-3 mb-3 border">
                            <div class="d-flex align-items-center justify-content-between mb-2">
                                <label class="form-label fw-bold text-dark small mb-0">
                                    <i class="fa-solid fa-receipt text-success me-1"></i>VAT (ተጨማሪ እሴት ታክስ)
                                </label>
                                <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25" style="font-size:0.68rem;" id="vatBadge">+15% VAT Added</span>
                            </div>
                            
                            <div class="mb-2">
                                <label class="form-label text-muted small mb-1 fw-semibold" style="font-size:0.75rem;">VAT Mode</label>
                                <select name="vat_type" id="vatTypeSelect" class="form-select form-select-sm" onchange="updateTaxAndTotals('from_base')">
                                    <option value="exclusive" {{ old('vat_type', 'exclusive') === 'exclusive' ? 'selected' : '' }}>+15% VAT Added (Exclusive / ተጨማሪ ቫት)</option>
                                    <option value="inclusive" {{ old('vat_type') === 'inclusive' ? 'selected' : '' }}>15% VAT Included (Inclusive / VAT B / ከቫት ጋር)</option>
                                    <option value="none" {{ old('vat_type') === 'none' ? 'selected' : '' }}>No VAT (0% / ያለ ቫት / Exempt)</option>
                                </select>
                            </div>

                            <div class="row g-2">
                                <div class="col-5">
                                    <label class="form-label text-muted small mb-1 fw-semibold" style="font-size:0.75rem;">Rate (%)</label>
                                    <div class="input-group input-group-sm">
                                        <input type="number" step="0.1" min="0" max="100" name="vat_rate" id="vatRateInput" 
                                               class="form-control" value="{{ old('vat_rate', 15) }}" oninput="updateTaxAndTotals('from_base')">
                                        <span class="input-group-text bg-white px-2 text-muted">%</span>
                                    </div>
                                </div>
                                <div class="col-7">
                                    <label class="form-label text-muted small mb-1 fw-semibold" style="font-size:0.75rem;">VAT Amount (ETB)</label>
                                    <div class="input-group input-group-sm">
                                        <span class="input-group-text bg-white px-2 text-muted">ETB</span>
                                        <input type="number" step="0.01" min="0" name="vat_amount" id="vatAmountInput" 
                                               class="form-control fw-semibold text-success" placeholder="0.00" value="{{ old('vat_amount', 0) }}" oninput="updateTaxAndTotals('from_vat')">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Total Gross Contract Value -->
                        <div class="mb-3">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <label class="form-label fw-bold text-dark mb-0">Total Contract Value (With VAT)</label>
                                <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 small" style="font-size:0.68rem;" id="contractValSourceBadge">Gross Ceiling</span>
                            </div>
                            <div class="input-group">
                                <span class="input-group-text bg-light fw-bold">ETB</span>
                                <input type="number" step="0.01" min="0" name="contract_value" id="contractValueInput" 
                                       class="form-control form-control-lg fw-bold text-primary @error('contract_value') is-invalid @enderror" 
                                       placeholder="0.00" value="{{ old('contract_value', 0) }}" oninput="updateTaxAndTotals('from_gross')">
                            </div>
                            <small class="text-muted">Total agreed contract ceiling amount (Gross including VAT).</small>
                            @error('contract_value')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <!-- Retention Rate (%) -->
                        <div class="mb-3">
                            <label class="form-label fw-semibold text-dark">Retention Rate (%)</label>
                            <div class="input-group">
                                <input type="number" step="0.1" min="0" max="100" name="retention_percent" id="retentionPercentInput" 
                                       class="form-control @error('retention_percent') is-invalid @enderror" 
                                       value="{{ old('retention_percent', 10) }}" oninput="updateTaxAndTotals('from_base')">
                                <span class="input-group-text bg-light">%</span>
                            </div>
                            <small class="text-muted">Standard 10% guarantee retention.</small>
                        </div>

                        <!-- Detailed Financial Summary Breakdown -->
                        <div class="p-3 bg-light rounded-3 mb-3 border shadow-sm">
                            <h6 class="fw-bold text-dark border-bottom pb-2 mb-2 d-flex align-items-center justify-content-between" style="font-size:0.85rem;">
                                <span><i class="fa-solid fa-list-check text-primary me-1"></i>Detailed Financial Summary</span>
                                <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25" style="font-size:0.68rem;" id="vatSummaryModeBadge">+15% VAT Added</span>
                            </h6>
                            <div class="d-flex justify-content-between mb-1.5 small">
                                <span class="text-muted">1. Work Base (Excl. VAT):</span>
                                <strong class="text-dark" id="displayBaseAmount">0.00 ETB</strong>
                            </div>
                            <div class="d-flex justify-content-between mb-1.5 small">
                                <span class="text-muted">2. VAT Amount (<span id="displayVatRateLabel">15%</span>):</span>
                                <strong class="text-success" id="displayVatAmount">+ 0.00 ETB</strong>
                            </div>
                            <div class="d-flex justify-content-between mb-2 pb-2 border-bottom">
                                <span class="fw-bold text-dark">3. Gross Contract Value:</span>
                                <strong class="text-primary fs-6" id="displayGrossContract">0.00 ETB</strong>
                            </div>
                            <div class="d-flex justify-content-between mb-1.5 small">
                                <span class="text-muted">4. Guarantee Retention (<span id="displayRetentionPctLabel">10%</span>):</span>
                                <strong class="text-warning" id="displayRetentionAmount">- 0.00 ETB</strong>
                            </div>
                            <div class="d-flex justify-content-between pt-1">
                                <span class="fw-bold text-dark">5. Net Payable to Subcontractor:</span>
                                <strong class="text-success fs-6" id="displayNetPayable">0.00 ETB</strong>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold text-dark">Link to Takeoff Sheet <small class="text-muted fw-normal">(Optional)</small></label>
                            <select name="takeoff_sheet_id" class="form-select">
                                <option value="">-- No Takeoff Linked --</option>
                                @foreach($takeoffs as $takeoff)
                                <option value="{{ $takeoff->id }}" {{ old('takeoff_sheet_id') == $takeoff->id ? 'selected' : '' }}>
                                    {{ $takeoff->project->name ?? 'Takeoff #' . $takeoff->id }} ({{ $takeoff->created_at->format('M d, Y') }})
                                </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold text-dark">Payment Milestones &amp; Terms</label>
                            <textarea name="terms_conditions" class="form-control" rows="3" 
                                      placeholder="e.g. 20% advance against guarantee, 70% against certified IPCs, 10% retention upon final acceptance.">{{ old('terms_conditions') }}</textarea>
                        </div>

                        <hr class="my-4">

                        <button type="submit" class="btn btn-primary w-100 py-2.5 fw-bold shadow-sm">
                            <i class="fa-solid fa-check-circle me-1"></i> Save Subcon Agreement
                        </button>
                        <a href="{{ route('subcon-agreements.index') }}" class="btn btn-outline-secondary w-100 mt-2 py-2">
                            Cancel
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

@push('scripts')
<script>
    let rowIndex = 0;

    function handleFileSelected(input) {
        if (input.files && input.files[0]) {
            const file = input.files[0];
            document.getElementById('selectedFileName').textContent = file.name;
            document.getElementById('selectedFileSize').textContent = (file.size / 1024 / 1024).toFixed(2) + ' MB';
            
            const fileIcon = document.getElementById('fileIcon');
            if (file.name.endsWith('.pdf')) {
                fileIcon.className = 'fa-solid fa-file-pdf text-danger fs-4 me-2';
            } else if (file.name.match(/\.(jpg|jpeg|png|webp)$/i)) {
                fileIcon.className = 'fa-solid fa-file-image text-info fs-4 me-2';
            } else if (file.name.match(/\.(doc|docx)$/i)) {
                fileIcon.className = 'fa-solid fa-file-word text-primary fs-4 me-2';
            } else {
                fileIcon.className = 'fa-solid fa-file text-secondary fs-4 me-2';
            }
            
            document.getElementById('fileSelectedInfo').classList.remove('d-none');
        }
    }

    function clearSelectedFile() {
        document.getElementById('agreementFileInput').value = '';
        document.getElementById('fileSelectedInfo').classList.add('d-none');
    }

    // Auto-fill subcontractor details on supplier selection
    document.getElementById('supplierSelect')?.addEventListener('change', function() {
        const selected = this.options[this.selectedIndex];
        if (this.value) {
            const name = selected.text.split('(')[0].trim();
            const contact = selected.dataset.contact || selected.dataset.person || '';
            const nameInput = document.getElementById('subcontractorNameInput');
            const contactInput = document.getElementById('subcontractorContactInput');
            if (nameInput && !nameInput.value) nameInput.value = name;
            if (contactInput && !contactInput.value) contactInput.value = contact;
        }
    });

    function addManualRow(desc = '', qty = '', unit = 'pcs', rate = '') {
        const tbody = document.getElementById('itemsBody');
        const tr = document.createElement('tr');
        tr.id = `row_${rowIndex}`;
        tr.innerHTML = `
            <td>
                <input type="text" name="items[${rowIndex}][task_description]" class="form-control form-control-sm" placeholder="Task description..." value="${desc}" required>
            </td>
            <td>
                <input type="number" step="0.001" min="0.001" name="items[${rowIndex}][quantity]" class="form-control form-control-sm item-qty" placeholder="Qty" value="${qty}" oninput="calcRow(${rowIndex})">
            </td>
            <td>
                <select name="items[${rowIndex}][unit]" class="form-select form-select-sm">
                    <option value="pcs" ${unit==='pcs'?'selected':''}>pcs</option>
                    <option value="m2" ${unit==='m2'?'selected':''}>m²</option>
                    <option value="m3" ${unit==='m3'?'selected':''}>m³</option>
                    <option value="ml" ${unit==='ml'?'selected':''}>ml</option>
                    <option value="kg" ${unit==='kg'?'selected':''}>kg</option>
                    <option value="ls" ${unit==='ls'?'selected':''}>ls (lump sum)</option>
                    <option value="hrs" ${unit==='hrs'?'selected':''}>hrs</option>
                    <option value="day" ${unit==='day'?'selected':''}>day</option>
                </select>
            </td>
            <td>
                <input type="number" step="0.01" min="0" name="items[${rowIndex}][unit_rate]" class="form-control form-control-sm item-rate" placeholder="Rate" value="${rate}" oninput="calcRow(${rowIndex})">
            </td>
            <td class="text-end fw-bold text-dark item-row-total" id="row_total_${rowIndex}">
                0.00
            </td>
            <td class="text-center">
                <button type="button" class="btn btn-sm text-danger p-0" onclick="removeRow(${rowIndex})">
                    <i class="fa-solid fa-trash-can"></i>
                </button>
            </td>
        `;
        tbody.appendChild(tr);
        calcRow(rowIndex);
        rowIndex++;
    }

    function removeRow(idx) {
        const row = document.getElementById(`row_${idx}`);
        if (row) row.remove();
        updateCalculations();
    }

    function calcRow(idx) {
        const row = document.getElementById(`row_${idx}`);
        if (!row) return;
        const qty = parseFloat(row.querySelector('.item-qty')?.value) || 0;
        const rate = parseFloat(row.querySelector('.item-rate')?.value) || 0;
        const total = (qty * rate).toFixed(2);
        const display = document.getElementById(`row_total_${idx}`);
        if (display) display.textContent = Number(total).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
        updateCalculations();
    }

    function calculateFromM2() {
        const rate = parseFloat(document.getElementById('unitPriceM2Input')?.value) || 0;
        const qty = parseFloat(document.getElementById('estimatedTotalM2Input')?.value) || 0;
        const badge = document.getElementById('m2CalculationBadge');
        const badgeText = document.getElementById('m2CalculationText');
        
        if (rate > 0 && qty > 0) {
            const calculatedTotal = (rate * qty).toFixed(2);
            const baseInput = document.getElementById('baseAmountInput');
            if (baseInput) {
                baseInput.value = calculatedTotal;
            }
            if (badge && badgeText) {
                badge.classList.remove('d-none');
                badgeText.innerHTML = `Auto-calculated: <strong>${rate.toLocaleString('en-US', {minimumFractionDigits: 2})} ETB/m²</strong> &times; <strong>${qty.toLocaleString('en-US', {minimumFractionDigits: 2})} m²</strong> = <strong>${Number(calculatedTotal).toLocaleString('en-US', {minimumFractionDigits: 2})} ETB</strong>`;
            }
            const sourceBadge = document.getElementById('contractValSourceBadge');
            if (sourceBadge) {
                sourceBadge.textContent = 'm² Product';
                sourceBadge.className = 'badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 small';
            }
        } else {
            if (badge) badge.classList.add('d-none');
        }
        updateTaxAndTotals('from_base');
    }

    function updateTaxAndTotals(origin = 'from_base') {
        const vatType = document.getElementById('vatTypeSelect')?.value || 'exclusive';
        const vatRate = parseFloat(document.getElementById('vatRateInput')?.value) || 0;
        const baseInput = document.getElementById('baseAmountInput');
        const vatInput = document.getElementById('vatAmountInput');
        const grossInput = document.getElementById('contractValueInput');
        const retentionInput = document.getElementById('retentionPercentInput');

        let base = parseFloat(baseInput?.value) || 0;
        let vat = parseFloat(vatInput?.value) || 0;
        let gross = parseFloat(grossInput?.value) || 0;

        // Check if BOQ breakdown items are present
        let itemsSum = 0;
        document.querySelectorAll('.item-row-total').forEach(el => {
            const val = parseFloat(el.textContent.replace(/,/g, '')) || 0;
            itemsSum += val;
        });

        const subtotalEl = document.getElementById('itemsSubtotal');
        if (subtotalEl) {
            subtotalEl.textContent = itemsSum.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2}) + ' ETB';
        }

        if (itemsSum > 0) {
            base = itemsSum;
            if (baseInput) baseInput.value = base.toFixed(2);
            origin = 'from_base';
            const sourceBadge = document.getElementById('contractValSourceBadge');
            if (sourceBadge) {
                sourceBadge.textContent = 'BOQ Breakdown';
                sourceBadge.className = 'badge bg-info bg-opacity-10 text-info border border-info border-opacity-25 small';
            }
        }

        if (origin === 'from_gross') {
            if (vatType === 'exclusive' || vatType === 'inclusive') {
                if (vatRate > 0) {
                    base = gross / (1 + (vatRate / 100));
                    vat = gross - base;
                } else {
                    base = gross;
                    vat = 0;
                }
            } else {
                base = gross;
                vat = 0;
            }
            if (baseInput) baseInput.value = base > 0 ? base.toFixed(2) : '0.00';
            if (vatInput) vatInput.value = vat > 0 ? vat.toFixed(2) : '0.00';
        } else if (origin === 'from_vat') {
            gross = base + vat;
            if (grossInput) grossInput.value = gross > 0 ? gross.toFixed(2) : '0.00';
        } else {
            // from_base (default)
            if (vatType === 'exclusive') {
                vat = base * (vatRate / 100);
                gross = base + vat;
            } else if (vatType === 'inclusive') {
                vat = base * (vatRate / 100);
                gross = base + vat;
            } else {
                // none
                vat = 0;
                gross = base;
            }
            if (vatInput) vatInput.value = vat > 0 ? vat.toFixed(2) : '0.00';
            if (grossInput) grossInput.value = gross > 0 ? gross.toFixed(2) : '0.00';
        }

        // Update VAT badges
        const vatBadge = document.getElementById('vatBadge');
        const vatSummaryModeBadge = document.getElementById('vatSummaryModeBadge');
        const vatRateLabel = document.getElementById('displayVatRateLabel');
        if (vatRateLabel) vatRateLabel.textContent = `${vatRate}%`;

        if (vatType === 'exclusive') {
            if (vatBadge) { vatBadge.textContent = `+${vatRate}% VAT Added`; vatBadge.className = 'badge bg-success bg-opacity-10 text-success border border-success border-opacity-25'; }
            if (vatSummaryModeBadge) { vatSummaryModeBadge.textContent = `+${vatRate}% VAT Added`; vatSummaryModeBadge.className = 'badge bg-success bg-opacity-10 text-success border border-success border-opacity-25'; }
        } else if (vatType === 'inclusive') {
            if (vatBadge) { vatBadge.textContent = `${vatRate}% VAT Included`; vatBadge.className = 'badge bg-info bg-opacity-10 text-info border border-info border-opacity-25'; }
            if (vatSummaryModeBadge) { vatSummaryModeBadge.textContent = `${vatRate}% VAT Included`; vatSummaryModeBadge.className = 'badge bg-info bg-opacity-10 text-info border border-info border-opacity-25'; }
        } else {
            if (vatBadge) { vatBadge.textContent = `0% No VAT`; vatBadge.className = 'badge bg-secondary bg-opacity-10 text-secondary border'; }
            if (vatSummaryModeBadge) { vatSummaryModeBadge.textContent = `0% No VAT`; vatSummaryModeBadge.className = 'badge bg-secondary bg-opacity-10 text-secondary border'; }
        }

        // Retention calculation
        const retentionPct = parseFloat(retentionInput?.value) || 0;
        const retentionAmt = gross * (retentionPct / 100);
        const netPayable = gross - retentionAmt;

        // Render summary displays
        const fmt = (n) => Number(n || 0).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2}) + ' ETB';
        const displayBase = document.getElementById('displayBaseAmount');
        const displayVat = document.getElementById('displayVatAmount');
        const displayGross = document.getElementById('displayGrossContract');
        const displayRet = document.getElementById('displayRetentionAmount');
        const displayNet = document.getElementById('displayNetPayable');
        const displayRetPct = document.getElementById('displayRetentionPctLabel');

        if (displayBase) displayBase.textContent = fmt(base);
        if (displayVat) displayVat.textContent = (vat > 0 ? '+ ' : '') + fmt(vat);
        if (displayGross) displayGross.textContent = fmt(gross);
        if (displayRet) displayRet.textContent = (retentionAmt > 0 ? '- ' : '') + fmt(retentionAmt);
        if (displayNet) displayNet.textContent = fmt(netPayable);
        if (displayRetPct) displayRetPct.textContent = `${retentionPct}%`;
    }

    function updateCalculations() {
        updateTaxAndTotals('from_base');
    }

    // Initial calculation on page load
    document.addEventListener('DOMContentLoaded', function() {
        const rate = parseFloat(document.getElementById('unitPriceM2Input')?.value) || 0;
        const qty = parseFloat(document.getElementById('estimatedTotalM2Input')?.value) || 0;
        if (rate > 0 && qty > 0) {
            calculateFromM2();
        } else {
            updateTaxAndTotals('from_base');
        }
    });
</script>
@endpush
@endsection
