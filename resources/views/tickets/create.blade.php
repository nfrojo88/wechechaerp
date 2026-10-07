@extends('layouts.app')
@section('title', 'IT Department - Problem Report & Suggestion Form')
@section('content')
<div class="container-fluid py-3">
    <!-- Header Breadcrumb & Back -->
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h1 class="h3 mb-0 text-gray-800 font-weight-bold">
                <i class="fa-solid fa-laptop-medical text-primary me-2"></i>IT Department Problem Report & Suggestion Form
            </h1>
            <p class="text-muted small mb-0">Use this form to report an IT problem or share an idea for improvement. Fill in the sections that apply to you.</p>
        </div>
        <a href="{{ route('tickets.index') }}" class="btn btn-sm btn-outline-secondary">
            <i class="fa-solid fa-arrow-left me-1"></i> Back to My Reports
        </a>
    </div>

    <!-- GM / Admin Instant SMS Alert Banner -->
    <div class="alert alert-primary border-left-primary shadow-sm mb-4">
        <div class="d-flex align-items-center">
            <div class="me-3 fs-2 text-primary">
                <i class="fa-solid fa-tower-broadcast fa-fade"></i>
            </div>
            <div>
                <strong class="d-block text-dark"><i class="fa-solid fa-bell me-1 text-warning"></i> Automated Escalation to GM & Global Admin</strong>
                <span class="text-muted small">
                    When you submit a Problem Report, an instant SMS alert is dispatched automatically to the <strong>General Manager (GM)</strong> and <strong>Global Admin</strong> for rapid investigation and resolution.
                </span>
            </div>
        </div>
    </div>

    @if ($errors->any())
        <div class="alert alert-danger alert-dismissible fade show shadow-sm mb-4" role="alert">
            <h6 class="font-weight-bold mb-1"><i class="fa-solid fa-triangle-exclamation me-1"></i> Please check the required fields:</h6>
            <ul class="mb-0 small ps-3">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <form action="{{ route('tickets.store') }}" method="POST" enctype="multipart/form-data" id="itReportForm">
        @csrf

        <div class="row justify-content-center">
            <div class="col-xl-10">

                <!-- 1. Submitter Information -->
                <div class="card shadow-sm border-0 mb-4">
                    <div class="card-header bg-light py-3 border-bottom d-flex align-items-center">
                        <span class="badge bg-primary rounded-circle me-2 px-2 py-1">1</span>
                        <h6 class="m-0 font-weight-bold text-dark">Submitter Information</h6>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label font-weight-bold small text-muted text-uppercase">Full Name <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light"><i class="fa-solid fa-user text-muted"></i></span>
                                    <input type="text" name="submitter_name" class="form-control @error('submitter_name') is-invalid @enderror" value="{{ old('submitter_name', $submitterName) }}" required placeholder="e.g. Fromsis Yohannes">
                                </div>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label font-weight-bold small text-muted text-uppercase">Employee ID</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light"><i class="fa-solid fa-id-badge text-muted"></i></span>
                                    <input type="text" name="employee_code" class="form-control" value="{{ old('employee_code', $employeeCode) }}" placeholder="e.g. EMP-0042">
                                </div>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label font-weight-bold small text-muted text-uppercase">Department <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light"><i class="fa-solid fa-building text-muted"></i></span>
                                    <input type="text" name="department" class="form-control" value="{{ old('department', $department) }}" required placeholder="e.g. ICT, Finance, Site Office">
                                </div>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label font-weight-bold small text-muted text-uppercase">Email / Phone <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light"><i class="fa-solid fa-phone text-muted"></i></span>
                                    <input type="text" name="contact_phone" class="form-control" value="{{ old('contact_phone', $contactPhone) }}" required placeholder="e.g. 0924898428">
                                </div>
                                <input type="hidden" name="contact_email" value="{{ old('contact_email', $contactEmail) }}">
                            </div>

                            <div class="col-md-4">
                                <label class="form-label font-weight-bold small text-muted text-uppercase">Date Submitted <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light"><i class="fa-solid fa-calendar text-muted"></i></span>
                                    <input type="date" name="submitted_date" class="form-control" value="{{ old('submitted_date', $submittedDate) }}" required>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 2. Type of Submission -->
                <div class="card shadow-sm border-0 mb-4">
                    <div class="card-header bg-light py-3 border-bottom d-flex align-items-center">
                        <span class="badge bg-primary rounded-circle me-2 px-2 py-1">2</span>
                        <h6 class="m-0 font-weight-bold text-dark">Type of Submission</h6>
                    </div>
                    <div class="card-body">
                        <p class="text-muted small mb-3">Please select one option. The relevant sections will activate automatically:</p>
                        @php $st = old('submission_type', 'problem'); @endphp
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="card h-100 p-3 border cursor-pointer submission-type-card {{ $st == 'problem' ? 'border-danger bg-danger-light' : '' }}" style="cursor: pointer;">
                                    <div class="d-flex align-items-center">
                                        <input class="form-check-input me-3" type="radio" name="submission_type" id="type_problem" value="problem" {{ $st == 'problem' ? 'checked' : '' }} onchange="toggleSections()">
                                        <div>
                                            <div class="font-weight-bold text-dark"><i class="fa-solid fa-bug text-danger me-1"></i> Problem / Issue</div>
                                            <div class="text-muted small">Report a technical error, device fault, or outage.</div>
                                        </div>
                                    </div>
                                </label>
                            </div>

                            <div class="col-md-4">
                                <label class="card h-100 p-3 border cursor-pointer submission-type-card {{ $st == 'suggestion' ? 'border-primary bg-primary-light' : '' }}" style="cursor: pointer;">
                                    <div class="d-flex align-items-center">
                                        <input class="form-check-input me-3" type="radio" name="submission_type" id="type_suggestion" value="suggestion" {{ $st == 'suggestion' ? 'checked' : '' }} onchange="toggleSections()">
                                        <div>
                                            <div class="font-weight-bold text-dark"><i class="fa-solid fa-lightbulb text-warning me-1"></i> Suggestion / Improvement Idea</div>
                                            <div class="text-muted small">Share an idea to enhance systems or workflows.</div>
                                        </div>
                                    </div>
                                </label>
                            </div>

                            <div class="col-md-4">
                                <label class="card h-100 p-3 border cursor-pointer submission-type-card {{ $st == 'both' ? 'border-purple bg-purple-light' : '' }}" style="cursor: pointer;">
                                    <div class="d-flex align-items-center">
                                        <input class="form-check-input me-3" type="radio" name="submission_type" id="type_both" value="both" {{ $st == 'both' ? 'checked' : '' }} onchange="toggleSections()">
                                        <div>
                                            <div class="font-weight-bold text-dark"><i class="fa-solid fa-layer-group text-info me-1"></i> Both</div>
                                            <div class="text-muted small">Report an existing issue and suggest a new solution.</div>
                                        </div>
                                    </div>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 3. Problem Report -->
                <div class="card shadow-sm border-0 mb-4" id="section_problem">
                    <div class="card-header bg-light py-3 border-bottom d-flex justify-content-between align-items-center">
                        <div class="d-flex align-items-center">
                            <span class="badge bg-danger rounded-circle me-2 px-2 py-1">3</span>
                            <h6 class="m-0 font-weight-bold text-dark">Problem Report</h6>
                        </div>
                        <span class="badge bg-danger-subtle text-danger border border-danger-subtle small">Sends SMS to GM & Admin</span>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-8">
                                <label class="form-label font-weight-bold small text-muted text-uppercase">Problem Title <span class="text-danger">*</span></label>
                                <input type="text" name="subject" class="form-control form-control-lg @error('subject') is-invalid @enderror" value="{{ old('subject') }}" placeholder="Brief summary (e.g. Cannot connect to ERP Database / Printer offline)">
                            </div>

                            <div class="col-md-4">
                                <label class="form-label font-weight-bold small text-muted text-uppercase">Category <span class="text-danger">*</span></label>
                                <select name="category" class="form-select form-select-lg">
                                    <option value="">Select Category...</option>
                                    @php
                                        $cats = ['Hardware', 'Software', 'Network / Internet', 'Email / Account', 'Printer / Scanner', 'Phone / VoIP', 'ERP / Company System', 'Other'];
                                    @endphp
                                    @foreach($cats as $cat)
                                        <option value="{{ $cat }}" {{ old('category') == $cat ? 'selected' : '' }}>{{ $cat }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label font-weight-bold small text-muted text-uppercase">Affected System or Device</label>
                                <input type="text" name="affected_system" class="form-control" value="{{ old('affected_system') }}" placeholder="e.g. HP Laptop, ERP Attendance, Store PC, Site Router">
                            </div>

                            <div class="col-md-6">
                                <label class="form-label font-weight-bold small text-muted text-uppercase">Location (Building, Floor, Room)</label>
                                <input type="text" name="location" class="form-control" value="{{ old('location') }}" placeholder="e.g. Head Office - 2nd Floor, Room 204">
                            </div>

                            <div class="col-md-6">
                                <label class="form-label font-weight-bold small text-muted text-uppercase">When did it start?</label>
                                <input type="text" name="incident_started_at" class="form-control" value="{{ old('incident_started_at') }}" placeholder="e.g. Today 9:30 AM, Yesterday afternoon">
                            </div>

                            <div class="col-md-6">
                                <label class="form-label font-weight-bold small text-muted text-uppercase">How often does it happen?</label>
                                <div class="d-flex gap-4 pt-2">
                                    @php $freq = old('frequency', 'Sometimes'); @endphp
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="frequency" id="freq_once" value="Once" {{ $freq == 'Once' ? 'checked' : '' }}>
                                        <label class="form-check-label" for="freq_once">Once</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="frequency" id="freq_sometimes" value="Sometimes" {{ $freq == 'Sometimes' ? 'checked' : '' }}>
                                        <label class="form-check-label" for="freq_sometimes">Sometimes</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="frequency" id="freq_always" value="Always" {{ $freq == 'Always' ? 'checked' : '' }}>
                                        <label class="form-check-label" for="freq_always">Always</label>
                                    </div>
                                </div>
                            </div>

                            <div class="col-12">
                                <label class="form-label font-weight-bold small text-muted text-uppercase">Description of the problem <span class="text-danger">*</span></label>
                                <p class="text-muted small mb-1">Describe what is happening and what you expected to happen.</p>
                                <textarea name="description" rows="4" class="form-control" placeholder="Provide full details of the issue...">{{ old('description') }}</textarea>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label font-weight-bold small text-muted text-uppercase">Steps to reproduce</label>
                                <textarea name="steps_to_reproduce" rows="3" class="form-control" placeholder="1. Open page... 2. Click button... 3. See error...">{{ old('steps_to_reproduce') }}</textarea>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label font-weight-bold small text-muted text-uppercase">Error message (if any)</label>
                                <textarea name="error_message" rows="3" class="form-control font-monospace" placeholder="Copy exact text or error code...">{{ old('error_message') }}</textarea>
                            </div>

                            <!-- Impact and Urgency with SLA Guide -->
                            <div class="col-12">
                                <label class="form-label font-weight-bold small text-muted text-uppercase mb-2">Impact and Urgency <span class="text-danger">*</span></label>
                                @php $urg = old('impact_urgency', 'medium'); @endphp
                                <div class="row g-2">
                                    <div class="col-md-3">
                                        <label class="card h-100 p-3 border cursor-pointer urgency-card {{ $urg == 'critical' ? 'border-danger bg-danger-light' : '' }}" style="cursor:pointer;">
                                            <div class="form-check">
                                                <input class="form-check-input text-danger" type="radio" name="impact_urgency" id="urg_critical" value="critical" {{ $urg == 'critical' ? 'checked' : '' }}>
                                                <strong class="d-block text-danger">🔴 Critical</strong>
                                                <small class="text-muted d-block mt-1">I cannot work at all / many people affected.</small>
                                                <span class="badge bg-danger mt-2">SLA: 1h First / 4h Fix</span>
                                            </div>
                                        </label>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="card h-100 p-3 border cursor-pointer urgency-card {{ $urg == 'high' ? 'border-warning bg-warning-light' : '' }}" style="cursor:pointer;">
                                            <div class="form-check">
                                                <input class="form-check-input text-warning" type="radio" name="impact_urgency" id="urg_high" value="high" {{ $urg == 'high' ? 'checked' : '' }}>
                                                <strong class="d-block text-warning">🟠 High</strong>
                                                <small class="text-muted d-block mt-1">Major part of my work is blocked.</small>
                                                <span class="badge bg-warning text-dark mt-2">SLA: 4h First / 1 Day Fix</span>
                                            </div>
                                        </label>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="card h-100 p-3 border cursor-pointer urgency-card {{ $urg == 'medium' ? 'border-primary bg-primary-light' : '' }}" style="cursor:pointer;">
                                            <div class="form-check">
                                                <input class="form-check-input text-primary" type="radio" name="impact_urgency" id="urg_medium" value="medium" {{ $urg == 'medium' ? 'checked' : '' }}>
                                                <strong class="d-block text-primary">🔵 Medium</strong>
                                                <small class="text-muted d-block mt-1">I can work, but with difficulty.</small>
                                                <span class="badge bg-primary mt-2">SLA: 1 Day First / 3 Days Fix</span>
                                            </div>
                                        </label>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="card h-100 p-3 border cursor-pointer urgency-card {{ $urg == 'low' ? 'border-success bg-success-light' : '' }}" style="cursor:pointer;">
                                            <div class="form-check">
                                                <input class="form-check-input text-success" type="radio" name="impact_urgency" id="urg_low" value="low" {{ $urg == 'low' ? 'checked' : '' }}>
                                                <strong class="d-block text-success">🟢 Low</strong>
                                                <small class="text-muted d-block mt-1">Minor inconvenience.</small>
                                                <span class="badge bg-success mt-2">SLA: 2 Days First / 5 Days Fix</span>
                                            </div>
                                        </label>
                                    </div>
                                </div>
                            </div>

                            <div class="col-12">
                                <label class="form-label font-weight-bold small text-muted text-uppercase">What have you already tried?</label>
                                <textarea name="already_tried" rows="2" class="form-control" placeholder="e.g. Restarted PC, rebooted router, re-logged in, tried another browser...">{{ old('already_tried') }}</textarea>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 4. Suggestion / Improvement Idea -->
                <div class="card shadow-sm border-0 mb-4" id="section_suggestion">
                    <div class="card-header bg-light py-3 border-bottom d-flex align-items-center">
                        <span class="badge bg-warning text-dark rounded-circle me-2 px-2 py-1">4</span>
                        <h6 class="m-0 font-weight-bold text-dark">Suggestion / Improvement Idea</h6>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-8">
                                <label class="form-label font-weight-bold small text-muted text-uppercase">Suggestion Title <span class="text-danger">*</span></label>
                                <input type="text" name="suggestion_title" class="form-control form-control-lg" value="{{ old('suggestion_title') }}" placeholder="e.g. Implement automated attendance report export / Cloud backup">
                            </div>

                            <div class="col-md-4">
                                <label class="form-label font-weight-bold small text-muted text-uppercase">Area</label>
                                <select name="suggestion_area" class="form-select form-select-lg">
                                    <option value="">Select Area...</option>
                                    @php
                                        $areas = ['Tools & software', 'Process', 'Security', 'Training', 'Hardware', 'Support service', 'Other'];
                                    @endphp
                                    @foreach($areas as $area)
                                        <option value="{{ $area }}" {{ old('suggestion_area') == $area ? 'selected' : '' }}>{{ $area }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-12">
                                <label class="form-label font-weight-bold small text-muted text-uppercase">Current Situation</label>
                                <p class="text-muted small mb-1">What is the issue or limitation today?</p>
                                <textarea name="current_situation" rows="3" class="form-control" placeholder="Describe the current workflow bottlenecks or shortcomings...">{{ old('current_situation') }}</textarea>
                            </div>

                            <div class="col-12">
                                <label class="form-label font-weight-bold small text-muted text-uppercase">Your Suggestion</label>
                                <p class="text-muted small mb-1">What would you change or add?</p>
                                <textarea name="suggested_change" rows="3" class="form-control" placeholder="Describe the proposed enhancement and how it should work...">{{ old('suggested_change') }}</textarea>
                            </div>

                            <div class="col-12">
                                <label class="form-label font-weight-bold small text-muted text-uppercase mb-2">Expected Benefit</label>
                                @php
                                    $selectedBenefits = old('expected_benefits', []);
                                    $benefitOpts = [
                                        'Saves time' => '⏱ Saves time',
                                        'Reduces cost' => '💰 Reduces cost',
                                        'Improves security' => '🛡 Improves security',
                                        'Improves user experience' => '✨ Improves user experience',
                                    ];
                                @endphp
                                <div class="row g-2 mb-2">
                                    @foreach($benefitOpts as $val => $label)
                                        <div class="col-md-3">
                                            <div class="form-check p-2 border rounded bg-light">
                                                <input class="form-check-input ms-1 me-2" type="checkbox" name="expected_benefits[]" value="{{ $val }}" id="ben_{{ Str::slug($val) }}" {{ in_array($val, $selectedBenefits) ? 'checked' : '' }}>
                                                <label class="form-check-label font-weight-bold small" for="ben_{{ Str::slug($val) }}">
                                                    {{ $label }}
                                                </label>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                                <div class="mt-2">
                                    <div class="input-group">
                                        <span class="input-group-text bg-light small">Other Benefit:</span>
                                        <input type="text" name="expected_benefits_other" class="form-control" value="{{ old('expected_benefits_other') }}" placeholder="Specify other anticipated benefits...">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 5. Material Request -->
                <div class="card shadow-sm border-0 mb-4 border-start border-4" style="border-left-color:#f59e0b !important;">
                    <div class="card-header py-3 border-bottom d-flex align-items-center" style="background:linear-gradient(135deg,#fffbeb,#fef3c7);">
                        <span class="badge me-2 px-2 py-1" style="background:#f59e0b;">5</span>
                        <h6 class="m-0 font-weight-bold text-dark"><i class="fa-solid fa-boxes-stacked me-2 text-warning"></i>Material Request (IT Equipment / Supplies)</h6>
                        <span class="badge bg-light text-secondary ms-2 small">Optional</span>
                    </div>
                    <div class="card-body">
                        <div class="form-check form-switch mb-3">
                            <input class="form-check-input" type="checkbox" id="hasMaterialRequest" name="has_material_request" value="1"
                                {{ old('has_material_request') ? 'checked' : '' }}
                                onchange="toggleMaterialRequest()">
                            <label class="form-check-label font-weight-bold" for="hasMaterialRequest">
                                <i class="fa-solid fa-cart-plus me-1 text-warning"></i>
                                I need to request materials / equipment for this issue
                            </label>
                        </div>

                        <div id="materialRequestBody" @if(!old('has_material_request')) style="display: none;" @endif>

                            {{-- Info banner --}}
                            <div class="alert alert-warning border-0 py-2 mb-3 small">
                                <i class="fa-solid fa-info-circle me-1"></i>
                                Your request will be sent to the <strong>General Manager (GM)</strong> for approval. The GM will then forward it directly to the <strong>Store Manager</strong> who will check availability and dispatch items. Finally, the Head Office Secretary will confirm receipt.
                            </div>

                            {{-- Request Header --}}
                            <div class="row g-3 mb-3">
                                <div class="col-md-4">
                                    <label class="form-label small text-muted text-uppercase fw-bold">Urgency Level</label>
                                    <select name="mr_urgency" class="form-select">
                                        <option value="">— Select Urgency —</option>
                                        <option value="low" {{ old('mr_urgency') == 'low' ? 'selected' : '' }}>🟢 Low – Not blocking work</option>
                                        <option value="medium" {{ old('mr_urgency') == 'medium' ? 'selected' : '' }}>🔵 Medium – Slowing work</option>
                                        <option value="high" {{ old('mr_urgency') == 'high' ? 'selected' : '' }}>🟠 High – Work blocked</option>
                                        <option value="critical" {{ old('mr_urgency') == 'critical' ? 'selected' : '' }}>🔴 Critical – Completely stopped</option>
                                    </select>
                                </div>
                                <div class="col-md-8">
                                    <label class="form-label small text-muted text-uppercase fw-bold">Project / Location / Department</label>
                                    <input type="text" name="mr_project_location" class="form-control" value="{{ old('mr_project_location') }}" placeholder="e.g. Head Office ICT Room, Site A Server Room">
                                </div>
                                <div class="col-12">
                                    <label class="form-label small text-muted text-uppercase fw-bold">Justification / Purpose <span class="text-danger">*</span></label>
                                    <textarea name="mr_justification" rows="2" class="form-control" placeholder="Why are these materials needed? How will they solve the issue?">{{ old('mr_justification') }}</textarea>
                                </div>
                            </div>

                            {{-- Item Rows Table --}}
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <h6 class="m-0 text-dark"><i class="fa-solid fa-list me-1 text-warning"></i> Requested Items</h6>
                                <button type="button" class="btn btn-sm btn-outline-warning" onclick="addMrItem()">
                                    <i class="fa-solid fa-plus me-1"></i> Add Item
                                </button>
                            </div>

                            <div class="table-responsive">
                                <table class="table table-bordered table-sm align-middle" id="mrItemsTable">
                                    <thead class="table-warning">
                                        <tr>
                                            <th style="width:35%">Item Name / Description <span class="text-danger">*</span></th>
                                            <th style="width:10%">Qty <span class="text-danger">*</span></th>
                                            <th style="width:12%">Unit</th>
                                            <th style="width:25%">Purpose / Use</th>
                                            <th style="width:10%">Urgency</th>
                                            <th style="width:8%"></th>
                                        </tr>
                                    </thead>
                                    <tbody id="mrItemsBody">
                                        @if(old('mr_items'))
                                            @foreach(old('mr_items') as $i => $item)
                                            <tr class="mr-item-row">
                                                <td><input type="text" name="mr_items[{{ $i }}][item_name]" class="form-control form-control-sm" value="{{ $item['item_name'] ?? '' }}" required placeholder="e.g. UPS Battery"></td>
                                                <td><input type="number" name="mr_items[{{ $i }}][quantity]" class="form-control form-control-sm" value="{{ $item['quantity'] ?? 1 }}" min="1" step="0.5" required></td>
                                                <td><input type="text" name="mr_items[{{ $i }}][unit]" class="form-control form-control-sm" value="{{ $item['unit'] ?? '' }}" placeholder="pcs / box / m"></td>
                                                <td><input type="text" name="mr_items[{{ $i }}][purpose]" class="form-control form-control-sm" value="{{ $item['purpose'] ?? '' }}" placeholder="What it solves"></td>
                                                <td>
                                                    <select name="mr_items[{{ $i }}][urgency_level]" class="form-select form-select-sm">
                                                        <option value="medium" {{ ($item['urgency_level'] ?? '') == 'medium' ? 'selected' : '' }}>Medium</option>
                                                        <option value="low" {{ ($item['urgency_level'] ?? '') == 'low' ? 'selected' : '' }}>Low</option>
                                                        <option value="high" {{ ($item['urgency_level'] ?? '') == 'high' ? 'selected' : '' }}>High</option>
                                                        <option value="critical" {{ ($item['urgency_level'] ?? '') == 'critical' ? 'selected' : '' }}>Critical</option>
                                                    </select>
                                                </td>
                                                <td class="text-center"><button type="button" class="btn btn-sm btn-outline-danger" onclick="this.closest('tr').remove()"><i class="fa-solid fa-trash"></i></button></td>
                                            </tr>
                                            @endforeach
                                        @else
                                            <tr class="mr-item-row" id="mrRow0">
                                                <td><input type="text" name="mr_items[0][item_name]" class="form-control form-control-sm" placeholder="e.g. UPS Battery, Ethernet Cable" required></td>
                                                <td><input type="number" name="mr_items[0][quantity]" class="form-control form-control-sm" value="1" min="1" step="0.5" required></td>
                                                <td><input type="text" name="mr_items[0][unit]" class="form-control form-control-sm" placeholder="pcs"></td>
                                                <td><input type="text" name="mr_items[0][purpose]" class="form-control form-control-sm" placeholder="e.g. Replace dead UPS"></td>
                                                <td>
                                                    <select name="mr_items[0][urgency_level]" class="form-select form-select-sm">
                                                        <option value="medium" selected>Medium</option>
                                                        <option value="low">Low</option>
                                                        <option value="high">High</option>
                                                        <option value="critical">Critical</option>
                                                    </select>
                                                </td>
                                                <td class="text-center"><button type="button" class="btn btn-sm btn-outline-danger" onclick="this.closest('tr').remove()"><i class="fa-solid fa-trash"></i></button></td>
                                            </tr>
                                        @endif
                                    </tbody>
                                </table>
                            </div>

                            {{-- Lifecycle Info --}}
                            <div class="mt-3">
                                <p class="text-muted small mb-1"><i class="fa-solid fa-timeline me-1"></i><strong>Procurement Lifecycle:</strong></p>
                                <div class="d-flex flex-wrap gap-2 align-items-center">
                                    <span class="badge bg-secondary py-2 px-3"><i class="fa-solid fa-file-circle-plus me-1"></i>IT Submits Request</span>
                                    <i class="fa-solid fa-arrow-right text-muted"></i>
                                    <span class="badge bg-warning text-dark py-2 px-3"><i class="fa-solid fa-user-tie me-1"></i>GM Reviews &amp; Approves</span>
                                    <i class="fa-solid fa-arrow-right text-muted"></i>
                                    <span class="badge bg-info text-dark py-2 px-3"><i class="fa-solid fa-warehouse me-1"></i>Store Manager Dispatches</span>
                                    <i class="fa-solid fa-arrow-right text-muted"></i>
                                    <span class="badge py-2 px-3" style="background:#8b5cf6;color:white;"><i class="fa-solid fa-cart-flatbed me-1"></i>Procurement (if needed)</span>
                                    <i class="fa-solid fa-arrow-right text-muted"></i>
                                    <span class="badge bg-success py-2 px-3"><i class="fa-solid fa-file-signature me-1"></i>Secretary Confirms Receipt</span>
                                </div>
                            </div>

                        </div>{{-- end materialRequestBody --}}
                    </div>
                </div>

                <!-- 6. Attachments -->
                <div class="card shadow-sm border-0 mb-4">
                    <div class="card-header bg-light py-3 border-bottom d-flex align-items-center">
                        <span class="badge bg-secondary rounded-circle me-2 px-2 py-1">6</span>
                        <h6 class="m-0 font-weight-bold text-dark">Attachments</h6>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label font-weight-bold small text-muted text-uppercase">Upload File (Screenshots, Logs, Documents)</label>
                                <input type="file" name="attachment" class="form-control @error('attachment') is-invalid @enderror" accept=".jpg,.jpeg,.png,.pdf,.docx,.doc,.txt,.log,.zip">
                                <div class="form-text">Supported: JPG, PNG, PDF, DOCX, TXT, LOG, ZIP (Max: 10MB)</div>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label font-weight-bold small text-muted text-uppercase">List of attachments / Notes</label>
                                <textarea name="attachments_notes" rows="2" class="form-control" placeholder="e.g. Screenshot of error modal, server log snippet...">{{ old('attachments_notes') }}</textarea>
                            </div>
                        </div>
                    </div>
                </div>


                <!-- Submission Bar -->
                <div class="card shadow-sm border-0 mb-5 bg-white">
                    <div class="card-body p-4 d-flex flex-wrap justify-content-between align-items-center gap-3">
                        <div>
                            <div class="font-weight-bold text-dark"><i class="fa-solid fa-shield-halved text-success me-1"></i> Ready to Submit</div>
                            <div class="text-muted small">Your submission reference number (e.g. IT-{{ date('Y') }}-0001) will be generated automatically.</div>
                        </div>
                        <div class="d-flex gap-2">
                            <a href="{{ route('tickets.index') }}" class="btn btn-outline-secondary px-4">Cancel</a>
                            <button type="submit" class="btn btn-primary btn-lg px-4 shadow-sm" id="submitBtn">
                                <i class="fa-solid fa-paper-plane me-2"></i> Submit & Alert GM/Admin
                            </button>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </form>
</div>

<style>
.bg-danger-light { background-color: #fee2e2 !important; }
.bg-warning-light { background-color: #fef3c7 !important; }
.bg-primary-light { background-color: #e0f2fe !important; }
.bg-success-light { background-color: #d1fae5 !important; }
.bg-purple-light { background-color: #ede9fe !important; }
.border-purple { border-color: #8b5cf6 !important; }
.cursor-pointer { cursor: pointer; }
</style>

<script>
let mrRowIndex = 1;

function toggleMaterialRequest() {
    const cb = document.getElementById('hasMaterialRequest');
    document.getElementById('materialRequestBody').style.display = cb.checked ? 'block' : 'none';
    // Toggle required on items
    document.querySelectorAll('#mrItemsBody input[required]').forEach(el => {
        el.required = cb.checked;
    });
}

function addMrItem() {
    const tbody = document.getElementById('mrItemsBody');
    const row = document.createElement('tr');
    row.className = 'mr-item-row';
    row.innerHTML = `
        <td><input type="text" name="mr_items[${mrRowIndex}][item_name]" class="form-control form-control-sm" placeholder="Item name" required></td>
        <td><input type="number" name="mr_items[${mrRowIndex}][quantity]" class="form-control form-control-sm" value="1" min="1" step="0.5" required></td>
        <td><input type="text" name="mr_items[${mrRowIndex}][unit]" class="form-control form-control-sm" placeholder="pcs"></td>
        <td><input type="text" name="mr_items[${mrRowIndex}][purpose]" class="form-control form-control-sm" placeholder="Purpose"></td>
        <td>
            <select name="mr_items[${mrRowIndex}][urgency_level]" class="form-select form-select-sm">
                <option value="medium" selected>Medium</option>
                <option value="low">Low</option>
                <option value="high">High</option>
                <option value="critical">Critical</option>
            </select>
        </td>
        <td class="text-center"><button type="button" class="btn btn-sm btn-outline-danger" onclick="this.closest('tr').remove()"><i class="fa-solid fa-trash"></i></button></td>
    `;
    tbody.appendChild(row);
    mrRowIndex++;
}

function toggleSections() {
    const isProblem = document.getElementById('type_problem').checked;
    const isSuggestion = document.getElementById('type_suggestion').checked;
    const isBoth = document.getElementById('type_both').checked;

    const probSec = document.getElementById('section_problem');
    const suggSec = document.getElementById('section_suggestion');

    if (isProblem) {
        probSec.style.display = 'block';
        suggSec.style.display = 'none';
    } else if (isSuggestion) {
        probSec.style.display = 'none';
        suggSec.style.display = 'block';
    } else if (isBoth) {
        probSec.style.display = 'block';
        suggSec.style.display = 'block';
    }

    // Update active card styling
    document.querySelectorAll('.submission-type-card').forEach(card => {
        card.classList.remove('border-danger', 'bg-danger-light', 'border-primary', 'bg-primary-light', 'border-purple', 'bg-purple-light');
    });
    if (isProblem) {
        document.getElementById('type_problem').closest('.submission-type-card').classList.add('border-danger', 'bg-danger-light');
    } else if (isSuggestion) {
        document.getElementById('type_suggestion').closest('.submission-type-card').classList.add('border-primary', 'bg-primary-light');
    } else if (isBoth) {
        document.getElementById('type_both').closest('.submission-type-card').classList.add('border-purple', 'bg-purple-light');
    }
}

document.addEventListener('DOMContentLoaded', function() {
    toggleSections();
    toggleMaterialRequest();

    // Prevent double submission
    const form = document.getElementById('itReportForm');
    form.addEventListener('submit', function() {
        const btn = document.getElementById('submitBtn');
        btn.disabled = true;
        btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-2"></i> Submitting & Alerting...';
    });
});
</script>
@endsection
