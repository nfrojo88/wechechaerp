@extends('layouts.app')
@section('title', 'Maintenance — ' . $maintenanceRequest->request_no)
@section('content')
<div class="container-fluid py-3">

    {{-- Header --}}
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
        <div class="d-flex align-items-center gap-3">
            <a href="{{ route('general-service.maintenance.index') }}" class="btn btn-sm btn-outline-secondary rounded-pill px-3">
                <i class="fa-solid fa-arrow-left me-1"></i>Back to List
            </a>
            <div>
                <h1 class="h4 mb-0 fw-bold d-flex align-items-center gap-2">
                    <i class="fa-solid fa-screwdriver-wrench text-warning"></i>
                    <span>{{ $maintenanceRequest->request_no }}</span>
                </h1>
                <div class="text-muted small">Submitted {{ $maintenanceRequest->created_at->format('d M Y, H:i') }}</div>
            </div>
        </div>

        @php 
            $sb = $maintenanceRequest->status_badge; 
            $ub = $maintenanceRequest->urgency_badge; 
            $expenses = $maintenanceRequest->expenseRequests;
            $materialRequests = $maintenanceRequest->materialRequests;
            $totalExpenseRequested = $expenses->sum('amount');
            $totalExpensePaid = $expenses->where('status', \App\Models\ExpenseRequest::STATUS_PAID)->sum('amount');
        @endphp

        <div class="d-flex flex-wrap align-items-center gap-2">
            <span class="badge {{ $ub['class'] }} rounded-pill px-3 fs-6">{{ $ub['label'] }}</span>
            <span class="badge {{ $sb['class'] }} rounded-pill px-3 fs-6">
                <i class="fa-solid {{ $sb['icon'] }} me-1"></i>{{ $sb['label'] }}
            </span>
            <a href="{{ Route::has('general-service.maintenance.report') ? route('general-service.maintenance.report', $maintenanceRequest) : url('/general-service/maintenance/' . $maintenanceRequest->id . '/report') }}" target="_blank" class="btn btn-outline-dark fw-bold rounded-pill px-3 shadow-xs">
                <i class="fa-solid fa-print me-1"></i>Print Report
            </a>
            <button type="button" class="btn btn-primary fw-bold rounded-pill px-3 shadow-sm" data-bs-toggle="modal" data-bs-target="#askMaterialModal">
                <i class="fa-solid fa-boxes-stacked me-1"></i>Ask Material / Purchase
            </button>
            <button type="button" class="btn btn-success fw-bold rounded-pill px-3 shadow-sm" data-bs-toggle="modal" data-bs-target="#askMoneyModal">
                <i class="fa-solid fa-hand-holding-dollar me-1"></i>Ask Money for Repair
            </button>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm rounded-4 p-3 mb-4">
            <i class="fa-solid fa-circle-check me-2 fs-5 align-middle text-success"></i>
            <strong>{{ session('success') }}</strong>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    @if($errors->any())
        <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm rounded-4 p-3 mb-4">
            <div class="fw-bold mb-1"><i class="fa-solid fa-triangle-exclamation me-1"></i>Please correct the errors below:</div>
            <ul class="mb-0 ps-3">
                @foreach($errors->all() as $err)
                    <li>{{ $err }}</li>
                @endforeach
            </ul>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <div class="row g-4">

        {{-- Left Column: Request Details & Ask Money & Ask Material & Update Form --}}
        <div class="col-lg-8">

            {{-- ── 0. EXECUTIVE 7-STAGE WORKFLOW CONTROLLER CARD ────────────────── --}}
            <div class="card border-0 shadow-sm rounded-4 mb-4 overflow-hidden border-top border-4 {{ in_array($maintenanceRequest->gs_status, ['rejected', 'gs_rejected']) ? 'border-danger' : (in_array($maintenanceRequest->gs_status, ['gm_on_hold', 'gm_on_hold_final']) ? 'border-secondary' : ($maintenanceRequest->gs_status === 'pending_gm_final' ? 'border-warning' : ($maintenanceRequest->gs_status === 'approved' ? 'border-success' : 'border-primary'))) }}">
                <div class="card-header bg-white py-3 px-4 d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div class="d-flex align-items-center gap-2">
                        <div class="rounded-circle p-2 text-warning d-flex align-items-center justify-content-center" style="width:36px;height:36px;background:rgba(245,158,11,0.12);">
                            <i class="fa-solid fa-arrows-split-up-and-left fs-5"></i>
                        </div>
                        <div>
                            <h5 class="mb-0 fw-bold text-dark">Maintenance Request Workflow Status</h5>
                            <small class="text-muted">7-Stage Lifecycle: Creation &rarr; GS Inspection &rarr; GM First Review &rarr; GS Details &rarr; Lock &rarr; GM Final &rarr; Routing</small>
                        </div>
                    </div>
                    @php $gsBadge = $maintenanceRequest->gs_status_badge; @endphp
                    <span class="badge {{ $gsBadge['class'] }} rounded-pill px-3 py-1.5 fs-6">
                        <i class="fa-solid {{ $gsBadge['icon'] }} me-1"></i>{{ $gsBadge['label'] }}
                    </span>
                </div>
                <div class="card-body p-4 bg-light bg-opacity-50">

                    {{-- ── STAGE 2: GS Inspection and Verification (pending_gs) ── --}}
                    @if(in_array($maintenanceRequest->gs_status, ['pending_gs', null]) && $maintenanceRequest->status !== 'rejected')
                        <div class="alert alert-warning border-0 shadow-xs rounded-3 p-3 mb-3">
                            <div class="d-flex align-items-start gap-2">
                                <i class="fa-solid fa-clipboard-check fs-4 text-warning mt-1"></i>
                                <div>
                                    <strong class="d-block text-dark">Stage 2: General Service Inspection &amp; Verification</strong>
                                    <p class="small text-muted mb-0">
                                        Inspect the asset to verify the fault. At this stage, <strong>no materials, money, or technician bank accounts</strong> can be entered. Confirm if maintenance is real and required (ACCEPT) or decline it (REJECT).
                                    </p>
                                </div>
                            </div>
                        </div>

                        <form method="POST" action="{{ route('general-service.maintenance.verify', $maintenanceRequest) }}" enctype="multipart/form-data" class="p-4 bg-white rounded-3 border mb-3">
                            @csrf
                            <div class="mb-3">
                                <label class="form-label fw-bold small text-uppercase text-secondary mb-1">
                                    Diagnosis &amp; Technical Findings <span class="text-danger">*</span>
                                </label>
                                <textarea name="diagnosis" class="form-control rounded-3" rows="3" placeholder="Provide detailed fault diagnosis, dismantled components, cause of breakdown, and technical findings..." required>{{ old('diagnosis', $maintenanceRequest->diagnosis) }}</textarea>
                            </div>
                            <div class="row g-3 mb-3">
                                <div class="col-md-6">
                                    <label class="form-label fw-bold small text-uppercase text-secondary mb-1">Odometer / Mileage / Operating Hours</label>
                                    <input type="text" name="odometer" value="{{ old('odometer', $maintenanceRequest->odometer) }}" class="form-control rounded-3" placeholder="e.g. 142,500 km / 2,400 hrs">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-bold small text-uppercase text-secondary mb-1">Photos / Inspection Attachments</label>
                                    <input type="file" name="attachments[]" class="form-control rounded-3" multiple accept="image/*,application/pdf">
                                </div>
                            </div>
                            <div class="form-check mb-3">
                                <input class="form-check-input" type="checkbox" name="send_to_gm" id="send_to_gm_chk" value="1" checked>
                                <label class="form-check-label small fw-semibold text-dark" for="send_to_gm_chk">
                                    Automatically submit to General Manager for First Review upon verification
                                </label>
                            </div>
                            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                                <button type="submit" class="btn btn-success fw-bold rounded-pill px-4 shadow-sm">
                                    <i class="fa-solid fa-check-double me-2"></i>Accept &amp; Verify Maintenance
                                </button>
                                <button type="button" class="btn btn-outline-danger fw-bold rounded-pill px-4" data-bs-toggle="modal" data-bs-target="#gsRejectModal">
                                    <i class="fa-solid fa-circle-xmark me-2"></i>Reject Maintenance
                                </button>
                            </div>
                        </form>

                    {{-- ── STAGE 2 VERIFIED: Ready to Send to GM ── --}}
                    @elseif($maintenanceRequest->gs_status === 'gs_verified')
                        <div class="alert alert-info border-0 shadow-xs rounded-3 p-3 mb-3">
                            <div class="d-flex align-items-start gap-2">
                                <i class="fa-solid fa-check-circle fs-4 text-info mt-1"></i>
                                <div>
                                    <strong class="d-block text-dark">Stage 2 Completed: Asset Verified!</strong>
                                    <p class="small text-muted mb-0">
                                        The maintenance is confirmed. This unit now appears under <strong>"Under Maintenance"</strong> in that site's inventory. Click below to submit to the General Manager for First Review.
                                    </p>
                                </div>
                            </div>
                        </div>
                        @if($maintenanceRequest->diagnosis)
                            <div class="p-3 bg-white rounded-3 border mb-3">
                                <small class="text-muted fw-bold text-uppercase d-block mb-1">GS Diagnosis &amp; Findings:</small>
                                <div class="small text-dark" style="white-space: pre-wrap;">{{ $maintenanceRequest->diagnosis }}</div>
                            </div>
                        @endif
                        <form method="POST" action="{{ route('general-service.maintenance.submit-to-gm', $maintenanceRequest) }}" class="p-3 bg-white rounded-3 border">
                            @csrf
                            <button type="submit" class="btn btn-warning text-dark fw-bold rounded-pill px-4 shadow-sm">
                                <i class="fa-solid fa-paper-plane me-2"></i>Send to GM for First Review
                            </button>
                        </form>

                    {{-- ── STAGE 3: Submitted to GM for First Review ── --}}
                    @elseif($maintenanceRequest->gs_status === 'submitted_to_gm')
                        <div class="alert alert-info border-0 shadow-xs rounded-3 p-3 mb-0">
                            <div class="d-flex align-items-center gap-3">
                                <div class="spinner-border spinner-border-sm text-info" role="status"></div>
                                <div>
                                    <strong class="d-block text-dark">Stage 3: Awaiting General Manager's First Review</strong>
                                    <p class="small text-muted mb-0">
                                        The General Manager is reviewing the asset details, fault diagnosis, and full maintenance history to grant initial permission ("Approve"), place on hold, or reject.
                                    </p>
                                </div>
                            </div>
                        </div>

                    {{-- ── STAGE 3: GM Placed on Hold ── --}}
                    @elseif($maintenanceRequest->gs_status === 'gm_on_hold')
                        <div class="alert alert-secondary border-0 shadow-xs rounded-3 p-3 mb-0">
                            <div class="d-flex align-items-start gap-2">
                                <i class="fa-solid fa-pause-circle fs-4 text-secondary mt-1"></i>
                                <div>
                                    <strong class="d-block text-dark">Stage 3: Placed On Hold by General Manager</strong>
                                    <p class="small text-muted mb-1">
                                        The ticket is paused. <strong>Reason:</strong> {{ $maintenanceRequest->hold_reason ?? 'Awaiting administrative review' }}
                                    </p>
                                    @if($maintenanceRequest->hold_review_date)
                                        <small class="text-muted"><i class="fa-regular fa-calendar me-1"></i>Scheduled Review Date: <strong>{{ $maintenanceRequest->hold_review_date->format('M d, Y') }}</strong></small>
                                    @endif
                                </div>
                            </div>
                        </div>

                    {{-- ── STAGE 4: GM Approved Initial -> GS Fills Costs & Details ── --}}
                    @elseif(in_array($maintenanceRequest->gs_status, ['gm_approved_initial', 'gm_returned_to_gs']))
                        <div class="alert alert-success border-0 shadow-xs rounded-3 p-3 mb-3">
                            <div class="d-flex align-items-start gap-2">
                                <i class="fa-solid fa-circle-check fs-4 text-success mt-1"></i>
                                <div>
                                    <strong class="d-block text-dark">Stage 4: GM Initial Approval Granted — Add Repair &amp; Cost Details</strong>
                                    <p class="small text-muted mb-0">
                                        The General Manager authorized proceeding. Please enter itemized spare parts, labor cost, technician/workshop details, condition rating, and proformas before submitting for final approval.
                                    </p>
                                    @if($maintenanceRequest->gm_initial_notes)
                                        <div class="small text-dark fw-semibold mt-1"><i class="fa-solid fa-quote-left text-muted me-1"></i>GM Directives: {{ $maintenanceRequest->gm_initial_notes }}</div>
                                    @endif
                                </div>
                            </div>
                        </div>

                        <form method="POST" action="{{ route('general-service.maintenance.submit-details', $maintenanceRequest) }}" enctype="multipart/form-data" class="p-4 bg-white rounded-3 border">
                            @csrf
                            <h6 class="fw-bold text-dark mb-3"><i class="fa-solid fa-boxes-stacked me-2 text-primary"></i>1. Required Spare Parts &amp; Materials</h6>
                            
                            <div class="table-responsive mb-3">
                                <table class="table table-sm table-bordered align-middle" id="sparePartsTable">
                                    <thead class="table-light small text-uppercase">
                                        <tr>
                                            <th>Item / Spare Part Name <span class="text-danger">*</span></th>
                                            <th style="width: 120px;">Qty <span class="text-danger">*</span></th>
                                            <th style="width: 110px;">Unit</th>
                                            <th style="width: 160px;">Est. Unit Cost (ETB)</th>
                                            <th style="width: 160px;">Total (ETB)</th>
                                            <th style="width: 50px;"></th>
                                        </tr>
                                    </thead>
                                    <tbody id="sparePartsTbody">
                                        @php
                                            $existingParts = is_array($maintenanceRequest->materials_data) ? $maintenanceRequest->materials_data : [];
                                            if (empty($existingParts)) {
                                                $existingParts = [['name' => '', 'quantity' => 1, 'unit' => 'pcs', 'estimated_unit_cost' => 0, 'total_cost' => 0]];
                                            }
                                        @endphp
                                        @foreach($existingParts as $idx => $part)
                                        <tr class="part-row">
                                            <td>
                                                <input type="text" name="materials[{{ $idx }}][name]" class="form-control form-control-sm part-name" placeholder="e.g. Brake pad, Engine oil 15W40..." value="{{ $part['name'] ?? '' }}" required>
                                            </td>
                                            <td>
                                                <input type="number" step="0.01" min="0.01" name="materials[{{ $idx }}][quantity]" class="form-control form-control-sm part-qty" value="{{ $part['quantity'] ?? 1 }}" oninput="calcPartRow(this)" required>
                                            </td>
                                            <td>
                                                <input type="text" name="materials[{{ $idx }}][unit]" class="form-control form-control-sm" placeholder="pcs" value="{{ $part['unit'] ?? 'pcs' }}">
                                            </td>
                                            <td>
                                                <input type="number" step="0.01" min="0" name="materials[{{ $idx }}][estimated_unit_cost]" class="form-control form-control-sm part-unit-cost" placeholder="0.00" value="{{ $part['estimated_unit_cost'] ?? 0 }}" oninput="calcPartRow(this)">
                                            </td>
                                            <td>
                                                <input type="text" class="form-control form-control-sm part-total-cost fw-bold bg-light" value="{{ number_format($part['total_cost'] ?? 0, 2) }}" readonly>
                                            </td>
                                            <td class="text-center">
                                                <button type="button" class="btn btn-sm btn-outline-danger border-0" onclick="removePartRow(this)" title="Remove row">
                                                    <i class="fa-solid fa-trash-can"></i>
                                                </button>
                                            </td>
                                        </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                                <button type="button" class="btn btn-sm btn-outline-primary rounded-pill px-3" onclick="addPartRow()">
                                    <i class="fa-solid fa-plus me-1"></i>Add Another Spare Part
                                </button>
                            </div>

                            <hr class="my-4 text-muted">

                            <h6 class="fw-bold text-dark mb-3"><i class="fa-solid fa-user-wrench me-2 text-primary"></i>2. Labor Cost &amp; Technician / Workshop Account Details</h6>
                            <div class="row g-3 mb-3">
                                <div class="col-md-6">
                                    <label class="form-label small fw-bold text-secondary text-uppercase">Labor Cost (ETB) <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <span class="input-group-text fw-bold bg-light">ETB</span>
                                        <input type="number" step="0.01" min="0" name="labor_cost" id="laborCostInput" value="{{ old('labor_cost', $maintenanceRequest->labor_cost ?? 0) }}" class="form-control rounded-end-3 fw-bold text-success" placeholder="0.00" oninput="calcTotalCost()" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small fw-bold text-secondary text-uppercase">Total Estimated Cost (Auto-Calculated)</label>
                                    <div class="input-group">
                                        <span class="input-group-text fw-bold bg-success text-white">TOTAL</span>
                                        <input type="text" id="totalCostDisplay" class="form-control rounded-end-3 fw-bold bg-light text-dark fs-6 font-monospace" value="0.00 ETB" readonly>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small fw-bold text-secondary text-uppercase">Technician / Workshop Name <span class="text-danger">*</span></label>
                                    <input type="text" name="technician_name" value="{{ old('technician_name', $maintenanceRequest->technician_name ?? $maintenanceRequest->maintenance_person_name) }}" class="form-control rounded-3" placeholder="Full name of mechanic / workshop" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small fw-bold text-secondary text-uppercase">Technician Phone Number <span class="text-danger">*</span></label>
                                    <input type="text" name="technician_phone" value="{{ old('technician_phone', $maintenanceRequest->technician_phone ?? $maintenanceRequest->maintenance_person_phone) }}" class="form-control rounded-3" placeholder="+251 9... / 09..." required>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label small fw-bold text-secondary text-uppercase">Bank Name <span class="text-danger">*</span></label>
                                    <input type="text" list="bankList" name="technician_bank_name" value="{{ old('technician_bank_name', $maintenanceRequest->technician_bank_name ?? 'Commercial Bank of Ethiopia (CBE)') }}" class="form-control rounded-3" placeholder="e.g. CBE, Awash, Telebirr..." required>
                                    <datalist id="bankList">
                                        <option value="Commercial Bank of Ethiopia (CBE)">
                                        <option value="Telebirr">
                                        <option value="Awash Bank">
                                        <option value="Bank of Abyssinia">
                                        <option value="Dashen Bank">
                                        <option value="Cooperative Bank of Oromia (Coop)">
                                        <option value="Hibret Bank">
                                        <option value="Nib International Bank">
                                        <option value="Amhara Bank">
                                    </datalist>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label small fw-bold text-secondary text-uppercase">Account Name <span class="text-danger">*</span></label>
                                    <input type="text" name="technician_account_name" value="{{ old('technician_account_name', $maintenanceRequest->technician_account_name) }}" class="form-control rounded-3" placeholder="Account holder name" required>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label small fw-bold text-secondary text-uppercase">Account Number <span class="text-danger">*</span></label>
                                    <input type="text" name="technician_account_number" value="{{ old('technician_account_number', $maintenanceRequest->technician_account_number ?? $maintenanceRequest->maintenance_person_account) }}" class="form-control rounded-3 font-monospace fw-bold" placeholder="e.g. 1000..." required>
                                </div>
                            </div>

                            <hr class="my-4 text-muted">

                            <h6 class="fw-bold text-dark mb-3"><i class="fa-solid fa-clipboard-check me-2 text-primary"></i>3. Asset Condition Rating &amp; Proformas</h6>
                            <div class="row g-3 mb-4">
                                <div class="col-md-4">
                                    <label class="form-label small fw-bold text-secondary text-uppercase">Asset Condition Rating <span class="text-danger">*</span></label>
                                    <select name="condition_rating" class="form-select rounded-3" required>
                                        <option value="repairable" {{ old('condition_rating', $maintenanceRequest->condition_rating) === 'repairable' ? 'selected' : '' }}>🟢 Repairable (ተጠግኖ የሚሰራ)</option>
                                        <option value="needs_replacement" {{ old('condition_rating', $maintenanceRequest->condition_rating) === 'needs_replacement' ? 'selected' : '' }}>🟠 Needs Major Overhaul / Parts Replacement</option>
                                        <option value="scrapped" {{ old('condition_rating', $maintenanceRequest->condition_rating) === 'scrapped' ? 'selected' : '' }}>🔴 Unrepairable / Recommend Scrapping (ከጥቅም ውጪ)</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label small fw-bold text-secondary text-uppercase">Estimated Time</label>
                                    <input type="text" name="estimated_time" value="{{ old('estimated_time', $maintenanceRequest->estimated_time) }}" class="form-control rounded-3" placeholder="e.g. 3 days / 6 hours">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label small fw-bold text-secondary text-uppercase">Proforma / Quotation Slips (Upload)</label>
                                    <input type="file" name="quotations[]" class="form-control rounded-3" multiple accept="image/*,application/pdf">
                                </div>
                                <div class="col-12">
                                    <label class="form-label small fw-bold text-secondary text-uppercase">Remarks &amp; Proforma Notes</label>
                                    <textarea name="remarks" class="form-control rounded-3" rows="2" placeholder="Quotations obtained, warranty notes, garage recommendation...">{{ old('remarks', $maintenanceRequest->remarks) }}</textarea>
                                </div>
                            </div>

                            <div class="p-3 bg-light rounded-3 border mb-3">
                                <div class="d-flex align-items-center gap-2">
                                    <i class="fa-solid fa-lock text-warning fs-5"></i>
                                    <div>
                                        <strong class="text-dark small d-block">Stage 5 Lock Notice:</strong>
                                        <span class="text-muted small">Submitting this form will immediately <strong>LOCK</strong> the ticket. No one (including GS, Admin, or API) can edit any field until the General Manager gives a final decision.</span>
                                    </div>
                                </div>
                            </div>

                            <button type="submit" class="btn btn-warning text-dark fw-bold rounded-pill px-4 shadow-sm">
                                <i class="fa-solid fa-lock me-2"></i>Submit to GM for Final Approval (Locks Ticket)
                            </button>
                        </form>

                    {{-- ── STAGE 5: Pending GM Final Approval (LOCKED) ── --}}
                    @elseif($maintenanceRequest->gs_status === 'pending_gm_final')
                        <div class="alert alert-warning border border-warning shadow-xs rounded-3 p-3 mb-3">
                            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                                <div class="d-flex align-items-center gap-3">
                                    <i class="fa-solid fa-lock fs-3 text-warning"></i>
                                    <div>
                                        <strong class="d-block text-dark fs-6">Locked: Awaiting GM Final Decision</strong>
                                        <p class="small text-muted mb-0">
                                            Stage 5 complete. All costs, materials, and technician payment accounts are locked and awaiting the General Manager's final approval.
                                        </p>
                                    </div>
                                </div>
                                <span class="badge bg-warning text-dark border border-warning px-3 py-1.5 rounded-pill fw-bold">
                                    <i class="fa-solid fa-lock me-1"></i>Edit Locked (403)
                                </span>
                            </div>
                        </div>

                        {{-- Locked Read-Only Summary of Submitted Details --}}
                        <div class="p-4 bg-white rounded-3 border mb-3">
                            <h6 class="fw-bold text-dark mb-3"><i class="fa-solid fa-lock text-warning me-2"></i>Locked Ticket Specifications</h6>
                            
                            <div class="row g-3 mb-3">
                                <div class="col-md-4">
                                    <div class="p-3 bg-light rounded-3 border">
                                        <small class="text-muted text-uppercase fw-bold d-block mb-1" style="font-size:0.75rem;">Labor Cost</small>
                                        <span class="fs-5 fw-bold text-success font-monospace">{{ number_format($maintenanceRequest->labor_cost ?? 0, 2) }} ETB</span>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="p-3 bg-light rounded-3 border">
                                        <small class="text-muted text-uppercase fw-bold d-block mb-1" style="font-size:0.75rem;">Materials Cost</small>
                                        <span class="fs-5 fw-bold text-primary font-monospace">{{ number_format($maintenanceRequest->material_cost ?? 0, 2) }} ETB</span>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="p-3 bg-light rounded-3 border border-success border-opacity-50">
                                        <small class="text-success text-uppercase fw-bold d-block mb-1" style="font-size:0.75rem;">Total Estimated Cost</small>
                                        <span class="fs-5 fw-bold text-dark font-monospace">{{ number_format($maintenanceRequest->total_cost ?? 0, 2) }} ETB</span>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="p-3 bg-light rounded-3 border">
                                        <small class="text-muted text-uppercase fw-bold d-block mb-1" style="font-size:0.75rem;">Technician / Workshop</small>
                                        <strong class="text-dark">{{ $maintenanceRequest->technician_name ?: ($maintenanceRequest->maintenance_person_name ?: 'N/A') }}</strong>
                                        <div class="small text-muted mt-1"><i class="fa-solid fa-phone me-1 text-info"></i>{{ $maintenanceRequest->technician_phone ?: ($maintenanceRequest->maintenance_person_phone ?: 'N/A') }}</div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="p-3 bg-success bg-opacity-10 rounded-3 border border-success border-opacity-25">
                                        <small class="text-success text-uppercase fw-bold d-block mb-1" style="font-size:0.75rem;">Bank Account for Payout</small>
                                        <strong class="text-dark">{{ $maintenanceRequest->technician_bank_name ?: 'Bank Account' }}</strong>
                                        <div class="font-monospace fw-bold text-success mt-1">Acc: {{ $maintenanceRequest->technician_account_number ?: ($maintenanceRequest->maintenance_person_account ?: 'N/A') }}</div>
                                        <small class="text-muted">Name: {{ $maintenanceRequest->technician_account_name ?: 'N/A' }}</small>
                                    </div>
                                </div>
                            </div>

                            @if(!empty($maintenanceRequest->materials_data))
                                <div class="mb-3">
                                    <small class="text-muted text-uppercase fw-bold d-block mb-2" style="font-size:0.75rem;">Itemized Spare Parts List:</small>
                                    <div class="table-responsive">
                                        <table class="table table-sm table-bordered align-middle mb-0" style="font-size: 0.85rem;">
                                            <thead class="table-light">
                                                <tr>
                                                    <th>Part Name</th>
                                                    <th>Quantity</th>
                                                    <th>Unit</th>
                                                    <th>Est. Unit Cost</th>
                                                    <th>Total Cost</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach($maintenanceRequest->materials_data as $p)
                                                    <tr>
                                                        <td><strong>{{ $p['name'] ?? '—' }}</strong></td>
                                                        <td>{{ $p['quantity'] ?? 1 }}</td>
                                                        <td>{{ $p['unit'] ?? 'pcs' }}</td>
                                                        <td class="font-monospace">{{ number_format($p['estimated_unit_cost'] ?? 0, 2) }} ETB</td>
                                                        <td class="font-monospace fw-bold text-success">{{ number_format($p['total_cost'] ?? 0, 2) }} ETB</td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            @endif

                            {{-- GM Controls if logged in as GM --}}
                            @if(auth()->check() && auth()->user()->hasAnyRole(['gm', 'general_manager', 'admin', 'global_admin']))
                                <div class="border-top pt-3 mt-3">
                                    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                                        <span class="badge bg-primary text-white rounded-pill px-3 py-1 fw-bold">
                                            <i class="fa-solid fa-crown me-1"></i>GM Executive Decision
                                        </span>
                                        <div class="d-flex gap-2">
                                            <button type="button" class="btn btn-outline-secondary btn-sm rounded-pill px-3 fw-bold" data-bs-toggle="collapse" data-bs-target="#gmHoldFinalSection">
                                                <i class="fa-solid fa-pause me-1"></i>Hold
                                            </button>
                                            <button type="button" class="btn btn-outline-danger btn-sm rounded-pill px-3 fw-bold" data-bs-toggle="collapse" data-bs-target="#gmRejectFinalSection">
                                                <i class="fa-solid fa-ban me-1"></i>Reject
                                            </button>
                                            <form action="{{ route('gm.maintenance-approvals.final-approval', $maintenanceRequest) }}" method="POST" class="d-inline">
                                                @csrf
                                                <input type="hidden" name="action" value="approve">
                                                <button type="submit" class="btn btn-success btn-sm rounded-pill px-4 fw-bold shadow-sm" onclick="return confirm('Authorize and route labor to Finance Head & parts to Store?')">
                                                    <i class="fa-solid fa-check-circle me-1"></i>Approve &amp; Route
                                                </button>
                                            </form>
                                        </div>
                                    </div>

                                    <div class="collapse mt-3" id="gmHoldFinalSection">
                                        <form action="{{ route('gm.maintenance-approvals.final-approval', $maintenanceRequest) }}" method="POST" class="p-3 bg-light rounded-3 border">
                                            @csrf
                                            <input type="hidden" name="action" value="hold">
                                            <label class="form-label small fw-bold text-dark">Hold Reason <span class="text-danger">*</span></label>
                                            <textarea name="hold_reason" class="form-control form-control-sm mb-2" rows="2" placeholder="Specify why the final decision is on hold..." required></textarea>
                                            <label class="form-label small fw-bold text-dark">Scheduled Review Date (Optional)</label>
                                            <input type="date" name="hold_review_date" class="form-control form-control-sm mb-2">
                                            <button type="submit" class="btn btn-secondary btn-sm fw-bold">Confirm Hold</button>
                                        </form>
                                    </div>

                                    <div class="collapse mt-3" id="gmRejectFinalSection">
                                        <form action="{{ route('gm.maintenance-approvals.final-approval', $maintenanceRequest) }}" method="POST" class="p-3 bg-light rounded-3 border border-danger">
                                            @csrf
                                            <input type="hidden" name="action" value="reject">
                                            <label class="form-label small fw-bold text-danger">Rejection Reason <span class="text-danger">*</span></label>
                                            <textarea name="rejection_reason" class="form-control form-control-sm mb-2" rows="2" placeholder="Specify mandatory rejection reason..." required></textarea>
                                            <button type="submit" class="btn btn-danger btn-sm fw-bold">Confirm Rejection</button>
                                        </form>
                                    </div>
                                </div>
                            @endif
                        </div>

                    {{-- ── STAGE 6: On Hold Final ── --}}
                    @elseif($maintenanceRequest->gs_status === 'gm_on_hold_final')
                        <div class="alert alert-secondary border-0 shadow-xs rounded-3 p-3 mb-0">
                            <div class="d-flex align-items-start gap-2">
                                <i class="fa-solid fa-lock fs-4 text-secondary mt-1"></i>
                                <div>
                                    <strong class="d-block text-dark">Locked: On Hold (GM Final Decision)</strong>
                                    <p class="small text-muted mb-1">
                                        GM placed the final decision on hold. <strong>Reason:</strong> {{ $maintenanceRequest->hold_reason }}
                                    </p>
                                    @if($maintenanceRequest->hold_review_date)
                                        <small class="text-muted"><i class="fa-regular fa-calendar me-1"></i>Scheduled Review Date: <strong>{{ $maintenanceRequest->hold_review_date->format('M d, Y') }}</strong></small>
                                    @endif
                                </div>
                            </div>
                        </div>

                    {{-- ── STAGE 7: GM Approved & Active (Routing Complete) ── --}}
                    @elseif($maintenanceRequest->gs_status === 'approved' || $maintenanceRequest->gm_final_approved_at)
                        <div class="alert alert-success border-0 shadow-xs rounded-3 p-3 mb-3">
                            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-2">
                                <div class="d-flex align-items-center gap-2">
                                    <i class="fa-solid fa-circle-check fs-3 text-success"></i>
                                    <div>
                                        <strong class="text-dark">Fully Authorized by General Manager!</strong>
                                        <div class="small text-muted">
                                            Labor Cost: <strong>{{ number_format($maintenanceRequest->labor_cost ?? $maintenanceRequest->money_amount ?? 0, 2) }} ETB</strong> (routed to Finance Head) |
                                            Parts: <strong>{{ number_format($maintenanceRequest->material_cost ?? 0, 2) }} ETB</strong> (routed to Store)
                                        </div>
                                    </div>
                                </div>
                                <div class="d-flex gap-2">
                                    <a href="{{ route('expenses.index') }}" class="btn btn-sm btn-outline-success rounded-pill px-3">
                                        <i class="fa-solid fa-money-bill-transfer me-1"></i>Finance Head Queue
                                    </a>
                                    <a href="{{ route('general-service.maintenance.report', $maintenanceRequest) }}" target="_blank" class="btn btn-sm btn-outline-primary rounded-pill px-3">
                                        <i class="fa-solid fa-print me-1"></i>Print Report Voucher
                                    </a>
                                </div>
                            </div>
                            <div class="row g-2 pt-2 border-top border-success border-opacity-25 small">
                                <div class="col-md-4">
                                    <span class="text-muted">Technician:</span>
                                    <strong class="text-dark ms-1">{{ $maintenanceRequest->technician_name ?: ($maintenanceRequest->maintenance_person_name ?? 'N/A') }}</strong>
                                </div>
                                <div class="col-md-4">
                                    <span class="text-muted">Bank &amp; Account:</span>
                                    <strong class="text-success font-monospace ms-1">{{ $maintenanceRequest->technician_bank_name ?: 'Bank' }} &bull; {{ $maintenanceRequest->technician_account_number ?: ($maintenanceRequest->maintenance_person_account ?? 'N/A') }}</strong>
                                </div>
                                <div class="col-md-4">
                                    <span class="text-muted">Phone:</span>
                                    <strong class="text-dark ms-1">{{ $maintenanceRequest->technician_phone ?: ($maintenanceRequest->maintenance_person_phone ?? 'N/A') }}</strong>
                                </div>
                            </div>
                        </div>

                    {{-- ── REJECTED (Stage 2 GS or Stage 3/6 GM) ── --}}
                    @elseif(in_array($maintenanceRequest->gs_status, ['rejected', 'gs_rejected']) || $maintenanceRequest->status === 'rejected')
                        <div class="alert alert-danger border-0 shadow-xs rounded-3 p-3 mb-0">
                            <div class="d-flex align-items-start gap-2">
                                <i class="fa-solid fa-circle-xmark fs-4 text-danger mt-1"></i>
                                <div>
                                    <strong class="d-block text-danger">Maintenance Request Rejected</strong>
                                    <p class="small text-muted mb-1">
                                        This request was rejected. The associated fixed asset unit has been released back to its previous operational status.
                                    </p>
                                    @if($maintenanceRequest->rejection_reason)
                                        <div class="p-2 rounded bg-white border border-danger-subtle small text-danger fw-semibold mt-1">
                                            <i class="fa-solid fa-circle-exclamation me-1"></i>Reason: {{ $maintenanceRequest->rejection_reason }}
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endif

                </div>
            </div>

                </div>
            </div>

            {{-- 1. Request Details Card --}}
            @php
                // Creator of the report/request
                $reporterUser = $maintenanceRequest->reportedBy;
                $reportedByName = $reporterUser->name ?? 'Requester / Staff';
                $reporterEmp = $reporterUser->employee;
                $reportedByCode = $reporterEmp->employee_code;
                $reportedByRole = $reporterEmp->role_title 
                    ?? $reporterEmp->department 
                    ?? ($reporterUser->roles->pluck('name')->map(fn($r) => ucwords(str_replace('_', ' ', $r)))->join(', ') ?: ($reporterUser->email ?: 'Authorized Requester'));

                // Decision Maker / General Service Evaluator (Wondimagnhu Siyum)
                $decisionMakerUser = $maintenanceRequest->assignedTo;
                $decisionMakerName = $decisionMakerUser->name ?? 'Wondimagnhu Siyum';
                $decisionMakerEmp = $decisionMakerUser->employee ?? ($decisionMakerName === 'Wondimagnhu Siyum' ? \App\Models\Employee::where('full_name', 'like', '%Wondimag%')->first() : null);
                $decisionMakerCode = $decisionMakerEmp->employee_code ?? 'EMP-01';
                $decisionMakerDept = $decisionMakerEmp->department ?? 'Administration / General Service';

                // Asset Operator / Custodian (if assigned to the equipment)
                $assetUnit = $maintenanceRequest->fixedAssetUnit;
                $operatorEmp = $assetUnit->assignedEmployee ?? ($maintenanceRequest->employee && $maintenanceRequest->employee->full_name !== $decisionMakerName ? $maintenanceRequest->employee : null);
            @endphp
            <div class="card border-0 shadow-sm rounded-4 mb-4">
                <div class="card-header bg-white border-0 py-3 px-4 rounded-top-4 d-flex justify-content-between align-items-center">
                    <h5 class="mb-0 fw-bold text-dark"><i class="fa-solid fa-file-lines me-2 text-muted"></i>Request Details</h5>
                    <span class="text-muted small">Asset ID: {{ $maintenanceRequest->asset_code ?? 'General Item' }}</span>
                </div>
                <div class="card-body p-4">
                    <div class="row g-3 mb-4">
                        {{-- Reported By (The Creator of the Request) --}}
                        <div class="col-md-6">
                            <div class="p-3 rounded-3 border bg-light bg-opacity-50 h-100">
                                <div class="d-flex align-items-center justify-content-between mb-1">
                                    <span class="small text-muted text-uppercase fw-semibold" style="font-size:0.75rem;">Reported By (Report Creator)</span>
                                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle" style="font-size:0.7rem;">
                                        <i class="fa-solid fa-user-pen me-1"></i>Report Creator
                                    </span>
                                </div>
                                <strong class="d-block text-dark fs-6">{{ $reportedByName }}</strong>
                                @if($reportedByCode)
                                    <span class="badge bg-dark font-monospace mt-1">{{ $reportedByCode }}</span>
                                @endif
                                <div class="text-muted small mt-1">{{ $reportedByRole }}</div>
                                <div class="text-muted mt-1" style="font-size:0.75rem;">
                                    <i class="fa-regular fa-clock me-1"></i>Submitted: {{ $maintenanceRequest->created_at->format('d M Y, h:i A') }}
                                </div>
                            </div>
                        </div>

                        {{-- Reported To (Decision Maker / General Service Evaluator) --}}
                        <div class="col-md-6">
                            <div class="p-3 rounded-3 border bg-light bg-opacity-50 h-100 border-start border-4 border-warning">
                                <div class="d-flex align-items-center justify-content-between mb-1">
                                    <span class="small text-muted text-uppercase fw-semibold" style="font-size:0.75rem;">Reported To (Decision Maker)</span>
                                    <span class="badge bg-warning-subtle text-dark border border-warning-subtle" style="font-size:0.7rem;">
                                        <i class="fa-solid fa-gavel me-1"></i>Decision Maker
                                    </span>
                                </div>
                                <strong class="d-block text-dark fs-6">{{ $decisionMakerName }}</strong>
                                @if($decisionMakerCode)
                                    <span class="badge bg-dark font-monospace mt-1">{{ $decisionMakerCode }}</span>
                                @endif
                                <div class="text-muted small mt-1">{{ $decisionMakerDept }}</div>
                                <div class="text-secondary small mt-1" style="font-size:0.75rem; line-height: 1.4;">
                                    <i class="fa-solid fa-circle-check text-success me-1"></i>Evaluates and decides on this maintenance request (Not the creator)
                                </div>
                            </div>
                        </div>

                        {{-- Asset / Equipment --}}
                        <div class="col-md-6">
                            <div class="p-3 rounded-3 border bg-light bg-opacity-50 h-100">
                                <div class="small text-muted mb-1 text-uppercase fw-semibold" style="font-size:0.75rem;">Asset / Equipment</div>
                                <strong class="d-block text-dark fs-6">{{ $maintenanceRequest->asset_name }}</strong>
                                @if($maintenanceRequest->asset_code)
                                    <span class="badge bg-primary font-monospace mt-1">{{ $maintenanceRequest->asset_code }}</span>
                                @endif
                                @if($maintenanceRequest->fixedAssetUnit && $maintenanceRequest->fixedAssetUnit->parentAsset)
                                    <div class="small text-muted mt-1">{{ $maintenanceRequest->fixedAssetUnit->parentAsset->name }}</div>
                                @endif
                                @if($operatorEmp)
                                    <div class="small text-muted mt-1 pt-1 border-top">
                                        <i class="fa-solid fa-id-badge me-1 text-secondary"></i>Asset Custodian / Operator: <strong class="text-dark">{{ $operatorEmp->full_name }}</strong> ({{ $operatorEmp->employee_code ?? 'EMP' }})
                                    </div>
                                @endif
                            </div>
                        </div>

                        {{-- Issue Category --}}
                        <div class="col-md-3">
                            <div class="p-3 rounded-3 border bg-light bg-opacity-50 h-100">
                                <div class="small text-muted mb-1 text-uppercase fw-semibold" style="font-size:0.75rem;">Issue Category</div>
                                <strong class="text-dark d-block mb-1">{{ $maintenanceRequest->issue_type_label }}</strong>
                                <span class="badge bg-light text-secondary border" style="font-size:0.72rem;">{{ ucwords(str_replace('_', ' ', $maintenanceRequest->issue_type)) }}</span>
                            </div>
                        </div>

                        {{-- Urgency Level --}}
                        <div class="col-md-3">
                            <div class="p-3 rounded-3 border bg-light bg-opacity-50 h-100">
                                <div class="small text-muted mb-1 text-uppercase fw-semibold" style="font-size:0.75rem;">Urgency Level</div>
                                <span class="badge {{ $ub['class'] }} rounded-pill px-3">{{ $ub['label'] }}</span>
                            </div>
                        </div>
                    </div>

                    <div class="mb-0">
                        <div class="fw-semibold small text-muted mb-2 text-uppercase" style="font-size:0.75rem;">Description from Requester</div>
                        <div class="p-3 bg-light rounded-3" style="white-space: pre-wrap; font-size: 0.95rem; border-left: 4px solid #f59e0b; line-height: 1.6;">
                            {{ $maintenanceRequest->description }}
                        </div>
                    </div>

                    @if($maintenanceRequest->maintenance_person_name || $maintenanceRequest->maintenance_person_account || $maintenanceRequest->maintenance_person_phone || $maintenanceRequest->petty_cash_owner_id)
                        <div class="mt-4 pt-3 border-top">
                            <h6 class="fw-bold text-dark mb-3"><i class="fa-solid fa-address-card text-primary me-2"></i>Technician &amp; Financial Payment Details</h6>
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <div class="p-2.5 rounded bg-light border">
                                        <small class="text-muted text-uppercase fw-semibold d-block" style="font-size:0.72rem;">Technician Name</small>
                                        <strong class="text-dark fs-6">{{ $maintenanceRequest->maintenance_person_name ?: 'N/A' }}</strong>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="p-2.5 rounded bg-success bg-opacity-10 border border-success border-opacity-25">
                                        <small class="text-success text-uppercase fw-semibold d-block" style="font-size:0.72rem;">Account No / Bank Account</small>
                                        <strong class="text-success font-monospace fs-6">{{ $maintenanceRequest->maintenance_person_account ?: 'N/A' }}</strong>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="p-2.5 rounded bg-light border">
                                        <small class="text-muted text-uppercase fw-semibold d-block" style="font-size:0.72rem;">Phone Number</small>
                                        @if($maintenanceRequest->maintenance_person_phone)
                                            <a href="tel:{{ $maintenanceRequest->maintenance_person_phone }}" class="text-dark fw-bold text-decoration-none fs-6">
                                                <i class="fa-solid fa-phone text-info me-1"></i>{{ $maintenanceRequest->maintenance_person_phone }}
                                            </a>
                                        @else
                                            <strong class="text-muted fs-6">N/A</strong>
                                        @endif
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="p-2.5 rounded bg-light border">
                                        <small class="text-muted text-uppercase fw-semibold d-block" style="font-size:0.72rem;">Petty Cash Custodian</small>
                                        <strong class="text-primary fs-6">{{ $maintenanceRequest->pettyCashOwner->name ?? 'N/A' }}</strong>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="p-2.5 rounded bg-light border">
                                        <small class="text-muted text-uppercase fw-semibold d-block" style="font-size:0.72rem;">Money Amount</small>
                                        <strong class="text-dark font-monospace fs-6">{{ number_format($maintenanceRequest->money_amount ?? 0, 2) }} ETB</strong>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="p-2.5 rounded bg-light border">
                                        <small class="text-muted text-uppercase fw-semibold d-block" style="font-size:0.72rem;">Est. Repair Time</small>
                                        <strong class="text-dark fs-6">{{ $maintenanceRequest->estimated_time ?: 'Standard' }}</strong>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endif
                </div>
            </div>

            {{-- 2. Linked Material Requests (Ask Material / Procurement from Store Manager) --}}
            <div class="card border-0 shadow-sm rounded-4 mb-4">
                <div class="card-header bg-white border-0 py-3 px-4 rounded-top-4 d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div class="d-flex align-items-center gap-2">
                        <div class="p-2 rounded-3 bg-primary bg-opacity-10 text-primary">
                            <i class="fa-solid fa-boxes-stacked fs-5"></i>
                        </div>
                        <div>
                            <h5 class="mb-0 fw-bold text-dark">Linked Material Requests (Store & Procurement)</h5>
                            <small class="text-muted">Spare parts & materials requested from Store Manager or Procurement.</small>
                        </div>
                    </div>
                    <button type="button" class="btn btn-sm btn-outline-primary fw-semibold rounded-pill px-3" data-bs-toggle="modal" data-bs-target="#askMaterialModal">
                        <i class="fa-solid fa-plus me-1"></i>Ask Material
                    </button>
                </div>
                <div class="card-body p-4 pt-2">
                    @if($materialRequests->isNotEmpty())
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light text-secondary small text-uppercase" style="font-size:0.78rem;">
                                    <tr>
                                        <th>Request #</th>
                                        <th>Destination Store</th>
                                        <th>Requested Items</th>
                                        <th>Status</th>
                                        <th>Required Date</th>
                                        <th class="text-end">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($materialRequests as $mr)
                                    <tr>
                                        <td>
                                            <strong class="font-monospace text-primary">{{ $mr->reference_number }}</strong>
                                            <small class="text-muted d-block">{{ $mr->created_at->format('d M Y, H:i') }}</small>
                                        </td>
                                        <td>
                                            <span class="fw-semibold text-dark">{{ $mr->store->name ?? 'General Store' }}</span>
                                            @if($mr->store && $mr->store->code)
                                                <span class="badge bg-light text-muted border small">{{ $mr->store->code }}</span>
                                            @endif
                                        </td>
                                        <td>
                                            <div class="d-flex flex-column gap-1">
                                                @foreach($mr->items as $item)
                                                    <div class="small">
                                                        <span class="fw-semibold text-dark">{{ $item->product->name ?? 'Item' }}</span>:
                                                        <span class="badge bg-secondary bg-opacity-10 text-dark fw-bold font-monospace">{{ (float)$item->quantity_requested }} {{ $item->product->unit ?? '' }}</span>
                                                        @if($item->notes)
                                                            <span class="text-muted" style="font-size: 0.76rem;">({{ $item->notes }})</span>
                                                        @endif
                                                    </div>
                                                @endforeach
                                            </div>
                                        </td>
                                        <td>
                                            @php
                                                $mrBadge = match($mr->status) {
                                                    'pending_gm'            => ['class' => 'bg-warning text-dark', 'label' => 'Awaiting GM Approval'],
                                                    'sent_to_store_manager' => ['class' => 'bg-info text-white',   'label' => 'GM Approved → Sent to Store Manager'],
                                                    'pending', 'pending_planning' => ['class' => 'bg-warning text-dark', 'label' => 'Pending'],
                                                    'planning_approved'     => ['class' => 'bg-info text-dark',    'label' => 'Planning Approved'],
                                                    'issued'                => ['class' => 'bg-success',           'label' => 'Issued from Store'],
                                                    'processed'             => ['class' => 'bg-info text-dark',    'label' => 'Processed by Store'],
                                                    'needs_purchase', 'sent_to_pr' => ['class' => 'bg-primary',   'label' => 'GM Approved → In Procurement (PR)'],
                                                    'rejected'              => ['class' => 'bg-danger',            'label' => 'Rejected by GM'],
                                                    default                 => ['class' => 'bg-secondary',         'label' => ucfirst(str_replace('_', ' ', $mr->status))],
                                                };
                                            @endphp
                                            <span class="badge {{ $mrBadge['class'] }} rounded-pill px-2 py-1">
                                                {{ $mrBadge['label'] }}
                                            </span>

                                            @if($mr->purchaseRequests && $mr->purchaseRequests->isNotEmpty())
                                                <div class="mt-1">
                                                    @foreach($mr->purchaseRequests as $linkedPr)
                                                        <a href="{{ route('purchase-requests.show', $linkedPr) }}" class="badge bg-primary text-decoration-none border shadow-xs" title="Linked Purchase Request">
                                                            <i class="fa-solid fa-file-invoice me-1"></i>{{ $linkedPr->pr_no }} ({{ $linkedPr->status_label }})
                                                        </a>
                                                    @endforeach
                                                </div>
                                            @endif
                                        </td>
                                        <td>
                                            <span class="small text-muted">{{ optional($mr->required_date)->format('d M Y') ?? '—' }}</span>
                                        </td>
                                        <td class="text-end">
                                            @if(in_array($mr->status, ['pending_gm', 'pending']) && auth()->check() && (auth()->user()->hasAnyRole(['gm', 'general_manager', 'admin', 'global_admin'])))
                                                <a href="{{ route('gm.maintenance-approvals.index', ['tab' => 'materials', 'search' => $mr->reference_number]) }}" class="btn btn-sm btn-warning text-dark fw-bold rounded-pill px-3 me-1">
                                                    <i class="fa-solid fa-gavel me-1"></i>Review as GM
                                                </a>
                                            @endif
                                            <a href="{{ route('material-requests.show', $mr) }}" class="btn btn-sm btn-outline-primary rounded-pill px-3">
                                                <i class="fa-solid fa-arrow-up-right-from-square me-1"></i>View
                                            </a>
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="p-4 bg-light rounded-4 text-center">
                            <i class="fa-solid fa-boxes-packing text-muted mb-2 fs-3"></i>
                            <h6 class="fw-bold text-dark mb-1">No Material Requests Created Yet</h6>
                            <p class="text-muted small mb-3">If you need spare parts, consumables, or replacement components from the store or procurement, request materials directly linked to this ticket.</p>
                            <button type="button" class="btn btn-sm btn-primary fw-bold rounded-pill px-4" data-bs-toggle="modal" data-bs-target="#askMaterialModal">
                                <i class="fa-solid fa-boxes-stacked me-1"></i>Ask Material from Store Manager
                            </button>
                        </div>
                    @endif
                </div>
            </div>

            {{-- 3. Linked Expense Requests (Ask Money for Maintenance) --}}
            <div class="card border-0 shadow-sm rounded-4 mb-4">
                <div class="card-header bg-white border-0 py-3 px-4 rounded-top-4 d-flex justify-content-between align-items-center">
                    <div class="d-flex align-items-center gap-2">
                        <div class="p-2 rounded-3 bg-success bg-opacity-10 text-success">
                            <i class="fa-solid fa-hand-holding-dollar fs-5"></i>
                        </div>
                        <div>
                            <h5 class="mb-0 fw-bold text-dark">Linked Expense Requests (Ask Money)</h5>
                            <small class="text-muted">Funding requested for service fees, contractor labour, or direct local purchases.</small>
                        </div>
                    </div>
                    <button type="button" class="btn btn-sm btn-outline-success fw-semibold rounded-pill px-3" data-bs-toggle="modal" data-bs-target="#askMoneyModal">
                        <i class="fa-solid fa-plus me-1"></i>Ask Money
                    </button>
                </div>
                <div class="card-body p-4 pt-2">
                    @if($expenses->isNotEmpty())
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light text-secondary small text-uppercase" style="font-size:0.78rem;">
                                    <tr>
                                        <th>Request #</th>
                                        <th>Category & Details</th>
                                        <th>Amount</th>
                                        <th>Status</th>
                                        <th>Date</th>
                                        <th class="text-end">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($expenses as $exp)
                                    <tr>
                                        <td>
                                            <strong class="font-monospace text-primary">{{ $exp->request_number }}</strong>
                                            @if($exp->attachment)
                                                <div>
                                                    <a href="{{ $exp->attachment_url }}" target="_blank" class="badge bg-light text-muted border text-decoration-none small">
                                                        <i class="fa-solid fa-paperclip me-1"></i>Receipt/Quote
                                                    </a>
                                                </div>
                                            @endif
                                        </td>
                                        <td>
                                            <div class="fw-semibold text-dark">{{ $exp->category }}</div>
                                            <div class="text-muted small text-truncate" style="max-width: 220px;" title="{{ $exp->description }}">
                                                {{ $exp->description }}
                                            </div>
                                        </td>
                                        <td>
                                            <strong class="text-success fs-6">{{ number_format($exp->amount, 2) }}</strong>
                                            <small class="text-muted d-block">ETB</small>
                                        </td>
                                        <td>
                                            @if($exp->status === \App\Models\ExpenseRequest::STATUS_PENDING_GM)
                                                <span class="badge bg-warning text-dark"><i class="fa-solid fa-user-shield me-1"></i>Awaiting GM Approval</span>
                                            @elseif(in_array($exp->status, [\App\Models\ExpenseRequest::STATUS_APPROVED_ASSIGNED, \App\Models\ExpenseRequest::STATUS_ASSIGNED]))
                                                <span class="badge bg-primary text-white"><i class="fa-solid fa-file-invoice-dollar me-1"></i>GM Approved → Assigned to Finance</span>
                                            @else
                                                {!! $exp->status_badge !!}
                                            @endif

                                            @if($exp->status === \App\Models\ExpenseRequest::STATUS_PAID && $exp->paid_at)
                                                <small class="text-muted d-block" style="font-size:0.75rem;">Paid {{ $exp->paid_at->format('d M') }}</small>
                                            @elseif($exp->status === \App\Models\ExpenseRequest::STATUS_SENT_TO_STORE)
                                                <small class="text-info text-dark d-block mt-1 fw-semibold" style="font-size:0.75rem;">
                                                    <i class="fa-solid fa-boxes-stacked me-1"></i>Routed to Store Manager
                                                </small>
                                            @elseif($exp->status === \App\Models\ExpenseRequest::STATUS_REJECTED)
                                                <small class="text-danger d-block mt-1" style="font-size:0.74rem;">
                                                    <i class="fa-solid fa-circle-xmark me-1"></i>{{ $exp->rejection_reason ?? 'Rejected' }}
                                                    @if($exp->rejected_by_user)
                                                        <span class="text-muted">by {{ $exp->rejected_by_user->name }}</span>
                                                    @endif
                                                </small>
                                            @endif
                                        </td>
                                        <td>
                                            <span class="small text-muted">{{ $exp->created_at->format('d M Y') }}</span>
                                        </td>
                                        <td class="text-end">
                                            @if(in_array($exp->status, [\App\Models\ExpenseRequest::STATUS_PENDING_GM, 'Pending (GM Review)', 'pending_gm']) && auth()->check() && (auth()->user()->hasAnyRole(['gm', 'general_manager', 'admin', 'global_admin'])))
                                                <a href="{{ route('gm.maintenance-approvals.index', ['tab' => 'expenses', 'search' => $exp->request_number]) }}" class="btn btn-sm btn-warning text-dark fw-bold rounded-pill px-3 me-1">
                                                    <i class="fa-solid fa-gavel me-1"></i>Review as GM
                                                </a>
                                            @endif
                                            <a href="{{ route('expense-requests.index', ['tab' => 'my_requests', 'search' => $exp->request_number]) }}" class="btn btn-sm btn-outline-primary rounded-pill px-3">
                                                <i class="fa-solid fa-arrow-up-right-from-square me-1"></i>View
                                            </a>
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="p-4 bg-light rounded-4 text-center">
                            <i class="fa-solid fa-receipt text-muted mb-2 fs-3"></i>
                            <h6 class="fw-bold text-dark mb-1">No Expense Requests Created Yet</h6>
                            <p class="text-muted small mb-3">If you need budget to hire external technicians or pay service fees, request money directly linked to this ticket.</p>
                            <button type="button" class="btn btn-sm btn-success fw-bold rounded-pill px-4" data-bs-toggle="modal" data-bs-target="#askMoneyModal">
                                <i class="fa-solid fa-hand-holding-dollar me-1"></i>Ask Money for this Maintenance
                            </button>
                        </div>
                    @endif
                </div>
            </div>

            {{-- 4. Update Status Card --}}
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-header bg-white border-0 py-3 px-4 rounded-top-4">
                    <h5 class="mb-0 fw-bold text-dark"><i class="fa-solid fa-pen-to-square me-2 text-primary"></i>Update Request Status & Response</h5>
                </div>
                <div class="card-body p-4">
                    <form method="POST" action="{{ route('general-service.maintenance.status', $maintenanceRequest) }}">
                        @csrf
                        @method('PUT')
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold text-secondary small text-uppercase" style="letter-spacing:0.5px;">
                                    Status <span class="text-danger">*</span>
                                </label>
                                <select name="status" id="maintenance_status_select" class="form-select form-select-lg rounded-3" required onchange="toggleReplacementOptions(this)">
                                    <option value="pending" {{ $maintenanceRequest->status === 'pending' ? 'selected' : '' }}>⏳ Pending Review</option>
                                    <option value="in_progress" {{ $maintenanceRequest->status === 'in_progress' ? 'selected' : '' }}>🔧 In Progress / Under Repair</option>
                                    <option value="sent_to_store_manager" {{ $maintenanceRequest->status === 'sent_to_store_manager' ? 'selected' : '' }}>📦 Send to Store Manager (Request Replacement)</option>
                                    <option value="resolved" {{ $maintenanceRequest->status === 'resolved' ? 'selected' : '' }}>✅ Resolved / Repaired</option>
                                    <option value="closed" {{ $maintenanceRequest->status === 'closed' ? 'selected' : '' }}>🔒 Closed</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold text-secondary small text-uppercase" style="letter-spacing:0.5px;">
                                    Assign Technician / Staff (Optional)
                                </label>
                                <select name="assigned_to_user_id" class="form-select form-select-lg rounded-3">
                                    <option value="">— Unassigned —</option>
                                    @foreach($staff as $s)
                                        <option value="{{ $s->id }}" {{ $maintenanceRequest->assigned_to_user_id == $s->id ? 'selected' : '' }}>
                                            {{ $s->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            {{-- Replacement Options when Sent to Store Manager --}}
                            <div class="col-12" id="replacement_options_container" style="{{ $maintenanceRequest->status === 'sent_to_store_manager' ? '' : 'display: none;' }}">
                                <div class="card border-info bg-info bg-opacity-10 p-3 rounded-3">
                                    <label class="form-label fw-bold text-info-emphasis mb-2">
                                        <i class="fa-solid fa-boxes-packing me-2"></i>Select Replacement Reason / Asset Condition for Store Manager:
                                    </label>
                                    <div class="d-flex flex-column flex-sm-row gap-3">
                                        <div class="form-check bg-white p-3 rounded-3 border flex-fill shadow-xs">
                                            <input class="form-check-input ms-0 me-2" type="radio" name="replacement_condition" id="cond_in_maintenance" value="in_maintenance" {{ old('replacement_condition', $maintenanceRequest->replacement_condition ?? 'in_maintenance') === 'in_maintenance' ? 'checked' : '' }}>
                                            <label class="form-check-input-label fw-bold text-dark d-block" for="cond_in_maintenance">
                                                🛠️ In Maintenance (Temporarily Needs Replacement)
                                            </label>
                                            <small class="text-muted d-block mt-1">Asset is under active repair; requesting temporary store unit allocation.</small>
                                        </div>
                                        <div class="form-check bg-white p-3 rounded-3 border flex-fill shadow-xs">
                                            <input class="form-check-input ms-0 me-2" type="radio" name="replacement_condition" id="cond_unrepairable" value="unrepairable_damage" {{ old('replacement_condition', $maintenanceRequest->replacement_condition) === 'unrepairable_damage' ? 'checked' : '' }}>
                                            <label class="form-check-input-label fw-bold text-danger d-block" for="cond_unrepairable">
                                                💥 Complete Damage (Unrepairable / Total Loss)
                                            </label>
                                            <small class="text-muted d-block mt-1">Asset cannot be repaired; requesting permanent store replacement unit.</small>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="col-12">
                                <label class="form-label fw-semibold text-secondary small text-uppercase" style="letter-spacing:0.5px;">
                                    Admin Notes / Feedback to Employee
                                </label>
                                <textarea name="admin_notes" class="form-control rounded-3" rows="4"
                                    placeholder="Add notes about repair actions taken, ETA for replacement parts, technician diagnosis, etc.">{{ old('admin_notes', $maintenanceRequest->admin_notes) }}</textarea>
                                <div class="form-text text-muted"><i class="fa-solid fa-circle-info me-1"></i>This note is visible to the employee on their profile/request history.</div>
                            </div>
                            <div class="col-12 d-flex justify-content-end pt-2">
                                <button type="submit" class="btn btn-primary fw-bold px-5 rounded-pill shadow-sm">
                                    <i class="fa-solid fa-floppy-disk me-2"></i>Save Updates
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

        </div>

        {{-- Right Column: Financial & Progress Overview --}}
        <div class="col-lg-4">

            {{-- Financial & Material Summary Card --}}
            <div class="card border-0 shadow-sm rounded-4 mb-4 bg-light bg-opacity-75">
                <div class="card-body p-4">
                    <h6 class="fw-bold mb-3 text-dark"><i class="fa-solid fa-coins me-2 text-warning"></i>Maintenance Financials</h6>
                    <div class="d-flex justify-content-between align-items-center mb-2 pb-2 border-bottom">
                        <span class="text-muted small">Total Expense Asked:</span>
                        <strong class="text-dark fs-6">{{ number_format($totalExpenseRequested, 2) }} ETB</strong>
                    </div>
                    <div class="d-flex justify-content-between align-items-center mb-2 pb-2 border-bottom">
                        <span class="text-muted small">Paid / Disbursed:</span>
                        <strong class="text-success fs-6">{{ number_format($totalExpensePaid, 2) }} ETB</strong>
                    </div>
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <span class="text-muted small">Material Requests:</span>
                        <strong class="text-primary fs-6">{{ $materialRequests->count() }} request{{ $materialRequests->count() == 1 ? '' : 's' }}</strong>
                    </div>
                    <div class="d-flex flex-column gap-2">
                        <button type="button" class="btn btn-primary btn-sm w-100 fw-bold rounded-pill" data-bs-toggle="modal" data-bs-target="#askMaterialModal">
                            <i class="fa-solid fa-boxes-stacked me-1"></i>New Material Request
                        </button>
                        <button type="button" class="btn btn-warning btn-sm w-100 fw-bold rounded-pill text-dark" data-bs-toggle="modal" data-bs-target="#askMoneyModal">
                            <i class="fa-solid fa-plus-circle me-1"></i>New Expense Request
                        </button>
                    </div>
                </div>
            </div>

            {{-- Quick Info Card --}}
            <div class="card border-0 shadow-sm rounded-4 mb-4">
                <div class="card-body p-4">
                    <h6 class="fw-bold mb-3 text-dark"><i class="fa-solid fa-circle-info me-2 text-primary"></i>Request Metadata</h6>
                    <div class="d-flex flex-column gap-2" style="font-size: 0.88rem;">
                        <div class="d-flex justify-content-between">
                            <span class="text-muted">Request No.</span>
                            <span class="fw-semibold font-monospace text-primary">{{ $maintenanceRequest->request_no }}</span>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span class="text-muted">Reported By (Creator)</span>
                            <span class="fw-semibold text-dark">{{ $reportedByName }}</span>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span class="text-muted">Reported To (Decision Maker)</span>
                            <span class="fw-semibold text-primary">{{ $decisionMakerName }}</span>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span class="text-muted">Submitted Date</span>
                            <span>{{ $maintenanceRequest->created_at->format('d M Y, H:i') }}</span>
                        </div>
                        @if($maintenanceRequest->resolved_at)
                        <div class="d-flex justify-content-between">
                            <span class="text-muted">Resolved Date</span>
                            <span class="text-success fw-semibold">{{ $maintenanceRequest->resolved_at->format('d M Y') }}</span>
                        </div>
                        @endif
                        <hr class="my-1">
                        <div class="d-flex justify-content-between">
                            <span class="text-muted">Days Open</span>
                            <span class="fw-semibold {{ $maintenanceRequest->created_at->diffInDays() > 3 ? 'text-danger' : 'text-dark' }}">
                                {{ $maintenanceRequest->created_at->diffInDays() }} days
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Status Timeline --}}
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-body p-4">
                    <h6 class="fw-bold mb-3 text-dark"><i class="fa-solid fa-timeline me-2 text-warning"></i>Workflow Status</h6>
                    <div class="d-flex flex-column gap-3" style="font-size: 0.85rem;">
                        @foreach(['pending' => ['Submitted / Pending', 'fa-clock', 'warning'], 'in_progress' => ['In Progress', 'fa-wrench', 'primary'], 'resolved' => ['Resolved', 'fa-circle-check', 'success'], 'closed' => ['Closed', 'fa-xmark-circle', 'secondary']] as $step => [$label, $icon, $color])
                        @php
                            $statuses = ['pending', 'in_progress', 'resolved', 'closed'];
                            $currentIdx = array_search($maintenanceRequest->status, $statuses);
                            $stepIdx = array_search($step, $statuses);
                            $isDone = $stepIdx <= $currentIdx;
                        @endphp
                        <div class="d-flex align-items-center gap-3">
                            <div class="rounded-circle d-flex align-items-center justify-content-center flex-shrink-0"
                                 style="width:34px;height:34px;background:{{ $isDone ? "var(--bs-$color)" : '#e5e7eb' }};">
                                <i class="fa-solid {{ $icon }} {{ $isDone ? 'text-white' : 'text-secondary' }}" style="font-size:0.75rem;"></i>
                            </div>
                            <span class="{{ $isDone ? 'fw-semibold text-dark' : 'text-muted' }}">{{ $label }}</span>
                            @if($step === $maintenanceRequest->status)
                                <span class="badge bg-{{ $color }} ms-auto" style="font-size:0.65rem;">Current</span>
                            @endif
                        </div>
                        @endforeach
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>

{{-- 📦 Ask Material / Purchase Modal for this Maintenance Request --}}
<div class="modal fade" id="askMaterialModal" tabindex="-1" aria-labelledby="askMaterialModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl">
        <div class="modal-content border-0 shadow rounded-4 overflow-hidden">
            <div class="modal-header bg-primary text-white py-3 px-4">
                <div class="d-flex align-items-center gap-2">
                    <i class="fa-solid fa-boxes-stacked fs-4"></i>
                    <div>
                        <h5 class="modal-title fw-bold mb-0" id="askMaterialModalLabel">Ask Material / Spare Parts Purchase</h5>
                        <small class="text-white-50">Ticket {{ $maintenanceRequest->request_no }} — {{ $maintenanceRequest->asset_name }} ({{ $maintenanceRequest->asset_code ?? 'Asset' }})</small>
                    </div>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <form action="{{ route('general-service.maintenance.ask-material', $maintenanceRequest) }}" method="POST">
                @csrf
                <div class="modal-body p-4">

                    <div class="alert alert-light border border-start border-4 border-primary p-3 rounded-3 mb-4">
                        <div class="d-flex align-items-center gap-2">
                            <i class="fa-solid fa-link text-primary fs-5"></i>
                            <div>
                                <strong class="text-dark">Auto-Routed to Store Manager & Procurement:</strong>
                                <span class="text-muted small d-block">This material request is linked to <span class="badge bg-warning text-dark font-monospace">{{ $maintenanceRequest->request_no }}</span> for {{ $maintenanceRequest->asset_name }}. The Store Manager can issue items from existing store stock or route directly to Procurement / Purchase Request.</span>
                            </div>
                        </div>
                    </div>

                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label class="form-label fw-bold text-secondary small text-uppercase" style="letter-spacing:0.5px;">
                                Destination Store <span class="text-danger">*</span>
                            </label>
                            <select name="destination_store_id" class="form-select rounded-3" required>
                                @foreach($stores as $st)
                                    <option value="{{ $st->id }}" {{ $loop->first ? 'selected' : '' }}>
                                        {{ $st->name }} ({{ $st->code ?? 'Store' }})
                                    </option>
                                @endforeach
                            </select>
                            <small class="text-muted">Select the store where materials will be collected or issued.</small>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-bold text-secondary small text-uppercase" style="letter-spacing:0.5px;">
                                Required By Date <span class="text-danger">*</span>
                            </label>
                            <input type="date" name="required_date" class="form-control rounded-3" value="{{ now()->addDays(2)->format('Y-m-d') }}" required>
                            <small class="text-muted">Target date when materials/spare parts are needed.</small>
                        </div>
                    </div>

                    {{-- Dynamic Material Items Table --}}
                    <div class="card border rounded-3 mb-4 shadow-xs">
                        <div class="card-header bg-light py-2 px-3 d-flex justify-content-between align-items-center">
                            <strong class="text-dark small text-uppercase"><i class="fa-solid fa-list-check me-1 text-primary"></i>Needed Materials & Spare Parts List</strong>
                            <button type="button" class="btn btn-sm btn-primary rounded-pill px-3" onclick="addMaterialRow()">
                                <i class="fa-solid fa-plus me-1"></i>Add Material Item
                            </button>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table align-middle mb-0" id="material_items_table">
                                    <thead class="table-light text-secondary small text-uppercase" style="font-size:0.75rem;">
                                        <tr>
                                            <th style="min-width: 260px;">Product / Material <span class="text-danger">*</span></th>
                                            <th style="width: 140px;">Quantity <span class="text-danger">*</span></th>
                                            <th style="width: 120px;">Unit</th>
                                            <th>Specifications / Remarks</th>
                                            <th style="width: 45px;"></th>
                                        </tr>
                                    </thead>
                                    <tbody id="material_items_tbody">
                                        {{-- Row 0 --}}
                                        <tr class="material-row" data-index="0">
                                            <td>
                                                <select name="items[0][product_id]" class="form-select form-select-sm product-select mb-1" onchange="handleProductChange(this, 0)">
                                                    <option value="">— Select Catalog Product —</option>
                                                    <option value="custom">✏️ + Custom / Unlisted Spare Part</option>
                                                    @foreach($products as $p)
                                                        <option value="{{ $p->id }}" data-unit="{{ $p->unit ?? 'pcs' }}">
                                                            {{ $p->name }} {{ $p->sku ? "({$p->sku})" : '' }} [{{ $p->category ?? 'General' }}]
                                                        </option>
                                                    @endforeach
                                                </select>
                                                <input type="text" name="items[0][custom_name]" class="form-control form-control-sm custom-name-input mt-1 d-none" placeholder="Enter custom spare part / material name...">
                                            </td>
                                            <td>
                                                <input type="number" step="0.01" min="0.01" name="items[0][quantity]" class="form-control form-control-sm fw-bold" placeholder="1.00" value="1" required>
                                            </td>
                                            <td>
                                                <input type="text" name="items[0][unit]" class="form-control form-control-sm unit-input" placeholder="pcs" value="pcs">
                                            </td>
                                            <td>
                                                <input type="text" name="items[0][notes]" class="form-control form-control-sm" placeholder="e.g. Size, model, brand, repair position...">
                                            </td>
                                            <td class="text-center">
                                                <button type="button" class="btn btn-sm btn-outline-danger border-0 remove-row-btn" onclick="removeMaterialRow(this)" title="Remove item">
                                                    <i class="fa-solid fa-trash-can"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label fw-bold text-secondary small text-uppercase" style="letter-spacing:0.5px;">
                                Purpose & Maintenance Requirements
                            </label>
                            @php
                                $defaultMatNotes = "Materials required for maintenance ticket #" . $maintenanceRequest->request_no . " — " . $maintenanceRequest->asset_name . ($maintenanceRequest->asset_code ? " (" . $maintenanceRequest->asset_code . ")" : "") . ".\nIssue: " . $maintenanceRequest->description . "\nTechnician Notes: ";
                            @endphp
                            <textarea name="notes" class="form-control rounded-3" rows="3" placeholder="Provide additional details or specifications for the Store Manager / Procurement team...">{{ old('notes', $defaultMatNotes) }}</textarea>
                        </div>
                    </div>

                </div>

                <div class="modal-footer bg-light border-top-0 py-3 px-4">
                    <button type="button" class="btn btn-secondary rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary fw-bold rounded-pill px-4 shadow-sm">
                        <i class="fa-solid fa-paper-plane me-1"></i>Send to Store Manager
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- 💰 Ask Money Modal for this Maintenance Request --}}
<div class="modal fade" id="askMoneyModal" tabindex="-1" aria-labelledby="askMoneyModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow rounded-4 overflow-hidden">
            <div class="modal-header bg-success text-white py-3 px-4">
                <div class="d-flex align-items-center gap-2">
                    <i class="fa-solid fa-hand-holding-dollar fs-4"></i>
                    <div>
                        <h5 class="modal-title fw-bold mb-0" id="askMoneyModalLabel">Ask Money for Maintenance</h5>
                        <small class="text-white-50">Ticket {{ $maintenanceRequest->request_no }} — {{ $maintenanceRequest->asset_name }}</small>
                    </div>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <form action="{{ route('general-service.maintenance.ask-money', $maintenanceRequest) }}" method="POST" enctype="multipart/form-data">
                @csrf
                <div class="modal-body p-4">

                    <div class="alert alert-light border border-start border-4 border-success p-3 rounded-3 mb-4">
                        <div class="d-flex align-items-center gap-2">
                            <i class="fa-solid fa-link text-success fs-5"></i>
                            <div>
                                <strong class="text-dark">Auto-Linking to Maintenance:</strong>
                                <span class="text-muted small d-block">This expense request will be permanently attached to ticket <span class="badge bg-warning text-dark font-monospace">{{ $maintenanceRequest->request_no }}</span> for {{ $maintenanceRequest->asset_name }} ({{ $maintenanceRequest->asset_code ?? 'Asset' }}).</span>
                            </div>
                        </div>
                    </div>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold text-secondary small text-uppercase" style="letter-spacing:0.5px;">
                                Expense Category
                            </label>
                            <input type="text" class="form-control bg-light" value="Maintenance & Repairs" readonly>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-bold text-secondary small text-uppercase" style="letter-spacing:0.5px;">
                                Requested Amount (ETB) <span class="text-danger">*</span>
                            </label>
                            <div class="input-group">
                                <span class="input-group-text fw-bold bg-light">ETB</span>
                                <input type="number" step="0.01" min="1" name="amount" class="form-control fw-bold fs-5 text-success" placeholder="0.00" required>
                            </div>
                        </div>

                        <div class="col-12">
                            <label class="form-label fw-bold text-secondary small text-uppercase" style="letter-spacing:0.5px;">
                                Purpose & Repair Details <span class="text-danger">*</span>
                            </label>
                            @php
                                $defaultReason = "Maintenance for Request #" . $maintenanceRequest->request_no . " — " . $maintenanceRequest->asset_name . ($maintenanceRequest->asset_code ? " (" . $maintenanceRequest->asset_code . ")" : "") . ".\nIssue: " . $maintenanceRequest->description . "\nRepair Requirements: ";
                            @endphp
                            <textarea name="description" class="form-control rounded-3" rows="4" placeholder="Describe parts needed, technician quotation details..." required>{{ old('description', $defaultReason) }}</textarea>
                        </div>

                        <div class="col-12">
                            <label class="form-label fw-bold text-secondary small text-uppercase" style="letter-spacing:0.5px;">
                                Proforma Invoice / Quotation / Receipt <small class="text-muted fw-normal">(Optional — Max 10MB)</small>
                            </label>
                            <input type="file" name="attachment" class="form-control rounded-3" accept="image/jpeg,image/png,image/jpg,application/pdf,image/webp">
                            <small class="text-muted">Upload vendor proforma invoice, spare parts receipt, or photo of damaged part.</small>
                        </div>

                        {{-- Bank Payment Destination Section (ክፍያ የሚፈጸምበት የባንክ ሂሳብ) --}}
                        <div class="col-12">
                            <div class="card border border-primary-subtle bg-light rounded-3 p-3 shadow-xs">
                                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                                    <div class="form-check form-switch mb-0">
                                        <input class="form-check-input" type="checkbox" role="switch" name="is_bank_payment" id="maintAskMoneyBankToggle" value="1" onchange="toggleMaintBankOptions(this)">
                                        <label class="form-check-label fw-bold text-dark fs-6" for="maintAskMoneyBankToggle">
                                            <i class="fa-solid fa-building-columns text-primary me-1"></i>Pay to Bank Account / Electronic Transfer (በባንክ ሂሳብ ይከፈል / ወደዚህ ሂሳብ ይተላለፍ)
                                        </label>
                                    </div>
                                    <span class="badge bg-secondary-subtle text-secondary border px-2 py-1 rounded-pill font-monospace" id="maintBankPaymentStatusBadge">
                                        <i class="fa-solid fa-lock me-1"></i>Locked / Optional
                                    </span>
                                </div>
                                <small class="text-muted d-block mt-1">
                                    Unlock to specify recipient technician, garage, or vendor bank account number and recipient name.
                                </small>

                                <div id="maintBankPaymentFields" class="mt-3 pt-3 border-top" style="display: none;">
                                    <div class="row g-2">
                                        <div class="col-md-4">
                                            <label class="form-label small fw-bold text-dark mb-1">
                                                <i class="fa-solid fa-building-columns text-primary me-1"></i>Bank Name (የባንክ ስም)
                                            </label>
                                            <input type="text" list="maintEthiopianBanks" name="bank_name" class="form-control form-control-sm" placeholder="e.g., CBE, Telebirr...">
                                            <datalist id="maintEthiopianBanks">
                                                <option value="Commercial Bank of Ethiopia (CBE)">
                                                <option value="Telebirr">
                                                <option value="Awash Bank">
                                                <option value="Bank of Abyssinia">
                                                <option value="Dashen Bank">
                                                <option value="Cooperative Bank of Oromia (Coop)">
                                                <option value="Hibret Bank (United)">
                                                <option value="Nib International Bank">
                                                <option value="Wegagen Bank">
                                                <option value="Zemen Bank">
                                                <option value="Oromia Bank">
                                                <option value="Sinqee Bank">
                                                <option value="Amhara Bank">
                                                <option value="CBE Birr">
                                            </datalist>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label small fw-bold text-dark mb-1">
                                                <i class="fa-solid fa-credit-card text-success me-1"></i>Account Number (የሂሳብ ቁጥር)
                                            </label>
                                            <input type="text" name="account_number" id="maintAccountNumber" class="form-control form-control-sm font-monospace fw-bold" placeholder="e.g. 1000123456789">
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label small fw-bold text-dark mb-1">
                                                <i class="fa-solid fa-user-tag text-info me-1"></i>Account Holder Name (የባለቤቱ ስም)
                                            </label>
                                            <input type="text" name="account_name" id="maintAccountName" class="form-control form-control-sm" placeholder="e.g. Garage / Technician Name">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="alert alert-light border mt-3 mb-0 small text-muted">
                        <i class="fa-solid fa-circle-info text-primary me-1"></i>
                        <strong>Direct GM Routing:</strong> Maintenance expense requests are routed directly to the <strong>General Manager (GM)</strong> for evaluation. The GM determines whether to approve &amp; send to <strong>Finance</strong> for disbursement or route to the <strong>Store Manager</strong> for material fulfillment from warehouse stock.
                    </div>
                </div>

                <div class="modal-footer bg-light border-top-0 py-3 px-4">
                    <button type="button" class="btn btn-secondary rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success fw-bold rounded-pill px-4 shadow-sm">
                        <i class="fa-solid fa-paper-plane me-1"></i>Submit Expense Request
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- ❌ General Service Reject Modal (Stage 2) --}}
<div class="modal fade" id="gsRejectModal" tabindex="-1" aria-labelledby="gsRejectModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow rounded-4 overflow-hidden">
            <div class="modal-header bg-danger text-white py-3 px-4">
                <div class="d-flex align-items-center gap-2">
                    <i class="fa-solid fa-circle-xmark fs-4"></i>
                    <div>
                        <h5 class="modal-title fw-bold mb-0" id="gsRejectModalLabel">Reject Maintenance Request</h5>
                        <small class="text-white-50">{{ $maintenanceRequest->request_no }} — {{ $maintenanceRequest->asset_name }}</small>
                    </div>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="{{ route('general-service.maintenance.reject', $maintenanceRequest) }}" method="POST">
                @csrf
                <div class="modal-body p-4">
                    <div class="alert alert-warning border-0 small mb-3">
                        <i class="fa-solid fa-triangle-exclamation me-1"></i>
                        Rejecting will close the ticket and release the asset back to its previous operational status. The requester will be notified.
                    </div>
                    <label class="form-label small fw-bold text-danger text-uppercase mb-1">
                        Mandatory Rejection Reason <span class="text-danger">*</span>
                    </label>
                    <textarea name="rejection_reason" class="form-control rounded-3" rows="3" placeholder="Provide specific inspection findings explaining why maintenance is declined (e.g. operator misuse, routine cleaning sufficient, asset obsolete)..." required></textarea>
                </div>
                <div class="modal-footer bg-light border-top-0 py-3 px-4">
                    <button type="button" class="btn btn-secondary rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger fw-bold rounded-pill px-4">
                        <i class="fa-solid fa-ban me-1"></i>Confirm Rejection
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

@push('scripts')
<script>
let partRowIdx = {{ !empty($maintenanceRequest->materials_data) ? count($maintenanceRequest->materials_data) : 1 }};

function addPartRow() {
    const tbody = document.getElementById('sparePartsTbody');
    if (!tbody) return;
    const idx = partRowIdx++;
    const tr = document.createElement('tr');
    tr.className = 'part-row';
    tr.innerHTML = `
        <td>
            <input type="text" name="materials[${idx}][name]" class="form-control form-control-sm part-name" placeholder="Spare part name..." required>
        </td>
        <td>
            <input type="number" step="0.01" min="0.01" name="materials[${idx}][quantity]" class="form-control form-control-sm part-qty" value="1" oninput="calcPartRow(this)" required>
        </td>
        <td>
            <input type="text" name="materials[${idx}][unit]" class="form-control form-control-sm" placeholder="pcs" value="pcs">
        </td>
        <td>
            <input type="number" step="0.01" min="0" name="materials[${idx}][estimated_unit_cost]" class="form-control form-control-sm part-unit-cost" placeholder="0.00" value="0" oninput="calcPartRow(this)">
        </td>
        <td>
            <input type="text" class="form-control form-control-sm part-total-cost fw-bold bg-light" value="0.00" readonly>
        </td>
        <td class="text-center">
            <button type="button" class="btn btn-sm btn-outline-danger border-0" onclick="removePartRow(this)" title="Remove row">
                <i class="fa-solid fa-trash-can"></i>
            </button>
        </td>
    `;
    tbody.appendChild(tr);
}

function removePartRow(btn) {
    const tbody = document.getElementById('sparePartsTbody');
    const rows = tbody.querySelectorAll('.part-row');
    if (rows.length > 1) {
        btn.closest('tr').remove();
        calcTotalCost();
    } else {
        alert('At least one spare part row is required, or leave fields blank if none.');
    }
}

function calcPartRow(el) {
    const row = el.closest('tr');
    const qty = parseFloat(row.querySelector('.part-qty')?.value) || 0;
    const unitCost = parseFloat(row.querySelector('.part-unit-cost')?.value) || 0;
    const totalInput = row.querySelector('.part-total-cost');
    const total = qty * unitCost;
    if (totalInput) totalInput.value = total.toFixed(2);
    calcTotalCost();
}

function calcTotalCost() {
    let materialTotal = 0;
    document.querySelectorAll('#sparePartsTbody .part-row').forEach(row => {
        const qty = parseFloat(row.querySelector('.part-qty')?.value) || 0;
        const unitCost = parseFloat(row.querySelector('.part-unit-cost')?.value) || 0;
        materialTotal += (qty * unitCost);
    });

    const laborCost = parseFloat(document.getElementById('laborCostInput')?.value) || 0;
    const totalCost = materialTotal + laborCost;
    const display = document.getElementById('totalCostDisplay');
    if (display) {
        display.value = totalCost.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2}) + ' ETB';
    }
}

document.addEventListener('DOMContentLoaded', function() {
    calcTotalCost();
});
</script>
@endpush
<script>
function toggleReplacementOptions(selectEl) {
    const container = document.getElementById('replacement_options_container');
    if (container) {
        container.style.display = (selectEl.value === 'sent_to_store_manager') ? 'block' : 'none';
    }
}

let materialRowIndex = 1;

function handleProductChange(selectEl, idx) {
    const row = selectEl.closest('tr');
    const customInput = row.querySelector('.custom-name-input');
    const unitInput = row.querySelector('.unit-input');

    if (selectEl.value === 'custom') {
        customInput.classList.remove('d-none');
        customInput.required = true;
        customInput.focus();
    } else {
        customInput.classList.add('d-none');
        customInput.required = false;
        
        const selectedOption = selectEl.options[selectEl.selectedIndex];
        const unit = selectedOption.getAttribute('data-unit');
        if (unit && unitInput) {
            unitInput.value = unit;
        }
    }
}

function addMaterialRow() {
    const tbody = document.getElementById('material_items_tbody');
    const idx = materialRowIndex++;

    const tr = document.createElement('tr');
    tr.className = 'material-row';
    tr.setAttribute('data-index', idx);

    tr.innerHTML = `
        <td>
            <select name="items[${idx}][product_id]" class="form-select form-select-sm product-select mb-1" onchange="handleProductChange(this, ${idx})">
                <option value="">— Select Catalog Product —</option>
                <option value="custom">✏️ + Custom / Unlisted Spare Part</option>
                @foreach($products as $p)
                    <option value="{{ $p->id }}" data-unit="{{ $p->unit ?? 'pcs' }}">
                        {{ $p->name }} {{ $p->sku ? "({$p->sku})" : '' }} [{{ $p->category ?? 'General' }}]
                    </option>
                @endforeach
            </select>
            <input type="text" name="items[${idx}][custom_name]" class="form-control form-control-sm custom-name-input mt-1 d-none" placeholder="Enter custom spare part / material name...">
        </td>
        <td>
            <input type="number" step="0.01" min="0.01" name="items[${idx}][quantity]" class="form-control form-control-sm fw-bold" placeholder="1.00" value="1" required>
        </td>
        <td>
            <input type="text" name="items[${idx}][unit]" class="form-control form-control-sm unit-input" placeholder="pcs" value="pcs">
        </td>
        <td>
            <input type="text" name="items[${idx}][notes]" class="form-control form-control-sm" placeholder="e.g. Size, model, brand, repair position...">
        </td>
        <td class="text-center">
            <button type="button" class="btn btn-sm btn-outline-danger border-0 remove-row-btn" onclick="removeMaterialRow(this)" title="Remove item">
                <i class="fa-solid fa-trash-can"></i>
            </button>
        </td>
    `;

    tbody.appendChild(tr);
}

function removeMaterialRow(buttonEl) {
    const tbody = document.getElementById('material_items_tbody');
    const rows = tbody.querySelectorAll('.material-row');
    if (rows.length > 1) {
        buttonEl.closest('tr').remove();
    } else {
        alert('At least one material item is required.');
    }
}

function toggleMaintBankOptions(toggle) {
    const fields = document.getElementById('maintBankPaymentFields');
    const badge = document.getElementById('maintBankPaymentStatusBadge');
    if (fields) fields.style.display = toggle.checked ? 'block' : 'none';
    if (badge) {
        if (toggle.checked) {
            badge.className = 'badge bg-success text-white border border-success px-2 py-1 rounded-pill font-monospace';
            badge.innerHTML = '<i class="fa-solid fa-lock-open me-1"></i>Unlocked — Direct Bank Transfer';
        } else {
            badge.className = 'badge bg-secondary-subtle text-secondary border px-2 py-1 rounded-pill font-monospace';
            badge.innerHTML = '<i class="fa-solid fa-lock me-1"></i>Locked / Optional';
        }
    }
}
</script>
@endpush
@endsection

