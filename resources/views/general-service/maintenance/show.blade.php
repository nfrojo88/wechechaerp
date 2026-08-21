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
            $totalExpenseRequested = $expenses->sum('amount');
            $totalExpensePaid = $expenses->where('status', \App\Models\ExpenseRequest::STATUS_PAID)->sum('amount');
        @endphp

        <div class="d-flex flex-wrap align-items-center gap-2">
            <span class="badge {{ $ub['class'] }} rounded-pill px-3 fs-6">{{ $ub['label'] }}</span>
            <span class="badge {{ $sb['class'] }} rounded-pill px-3 fs-6">
                <i class="fa-solid {{ $sb['icon'] }} me-1"></i>{{ $sb['label'] }}
            </span>
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

        {{-- Left Column: Request Details & Ask Money & Update Form --}}
        <div class="col-lg-8">

            {{-- 1. Request Details Card --}}
            <div class="card border-0 shadow-sm rounded-4 mb-4">
                <div class="card-header bg-white border-0 py-3 px-4 rounded-top-4 d-flex justify-content-between align-items-center">
                    <h5 class="mb-0 fw-bold text-dark"><i class="fa-solid fa-file-lines me-2 text-muted"></i>Request Details</h5>
                    <span class="text-muted small">Asset ID: {{ $maintenanceRequest->asset_code ?? 'General Item' }}</span>
                </div>
                <div class="card-body p-4">
                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <div class="p-3 rounded-3 border bg-light bg-opacity-50 h-100">
                                <div class="small text-muted mb-1 text-uppercase fw-semibold" style="font-size:0.75rem;">Reported By Employee</div>
                                <strong class="d-block text-dark fs-6">{{ $maintenanceRequest->employee->full_name ?? 'N/A' }}</strong>
                                @if($maintenanceRequest->employee->employee_code ?? null)
                                    <span class="badge bg-dark font-monospace mt-1">{{ $maintenanceRequest->employee->employee_code }}</span>
                                @endif
                                <div class="text-muted small mt-1">{{ $maintenanceRequest->employee->role_title ?? $maintenanceRequest->employee->department ?? '' }}</div>
                            </div>
                        </div>
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
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="p-3 rounded-3 border bg-light bg-opacity-50">
                                <div class="small text-muted mb-1 text-uppercase fw-semibold" style="font-size:0.75rem;">Issue Category</div>
                                <strong class="text-dark">{{ $maintenanceRequest->issue_type_label }}</strong>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="p-3 rounded-3 border bg-light bg-opacity-50">
                                <div class="small text-muted mb-1 text-uppercase fw-semibold" style="font-size:0.75rem;">Urgency Level</div>
                                <span class="badge {{ $ub['class'] }} rounded-pill px-3">{{ $ub['label'] }}</span>
                            </div>
                        </div>
                    </div>

                    <div class="mb-0">
                        <div class="fw-semibold small text-muted mb-2 text-uppercase" style="font-size:0.75rem;">Description from Employee</div>
                        <div class="p-3 bg-light rounded-3" style="white-space: pre-wrap; font-size: 0.95rem; border-left: 4px solid #f59e0b; line-height: 1.6;">
                            {{ $maintenanceRequest->description }}
                        </div>
                    </div>
                </div>
            </div>

            {{-- 2. Linked Expense Requests (Ask Money for Maintenance) --}}
            <div class="card border-0 shadow-sm rounded-4 mb-4">
                <div class="card-header bg-white border-0 py-3 px-4 rounded-top-4 d-flex justify-content-between align-items-center">
                    <div class="d-flex align-items-center gap-2">
                        <div class="p-2 rounded-3 bg-success bg-opacity-10 text-success">
                            <i class="fa-solid fa-hand-holding-dollar fs-5"></i>
                        </div>
                        <div>
                            <h5 class="mb-0 fw-bold text-dark">Linked Expense Requests (Ask Money)</h5>
                            <small class="text-muted">Funding requested for spare parts, service fees, or repairs for this ticket.</small>
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
                                            {!! $exp->status_badge !!}
                                            @if($exp->status === \App\Models\ExpenseRequest::STATUS_PAID && $exp->paid_at)
                                                <small class="text-muted d-block" style="font-size:0.75rem;">Paid {{ $exp->paid_at->format('d M') }}</small>
                                            @endif
                                        </td>
                                        <td>
                                            <span class="small text-muted">{{ $exp->created_at->format('d M Y') }}</span>
                                        </td>
                                        <td class="text-end">
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
                            <p class="text-muted small mb-3">If you need budget to purchase replacement parts or hire external technicians, request money directly linked to this ticket.</p>
                            <button type="button" class="btn btn-sm btn-success fw-bold rounded-pill px-4" data-bs-toggle="modal" data-bs-target="#askMoneyModal">
                                <i class="fa-solid fa-hand-holding-dollar me-1"></i>Ask Money for this Maintenance
                            </button>
                        </div>
                    @endif
                </div>
            </div>

            {{-- 3. Update Status Card --}}
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
                                <select name="status" class="form-select form-select-lg rounded-3" required>
                                    <option value="pending" {{ $maintenanceRequest->status === 'pending' ? 'selected' : '' }}>⏳ Pending Review</option>
                                    <option value="in_progress" {{ $maintenanceRequest->status === 'in_progress' ? 'selected' : '' }}>🔧 In Progress / Under Repair</option>
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

            {{-- Financial Summary Card --}}
            <div class="card border-0 shadow-sm rounded-4 mb-4 bg-light bg-opacity-75">
                <div class="card-body p-4">
                    <h6 class="fw-bold mb-3 text-dark"><i class="fa-solid fa-coins me-2 text-warning"></i>Maintenance Financials</h6>
                    <div class="d-flex justify-content-between align-items-center mb-2 pb-2 border-bottom">
                        <span class="text-muted small">Total Expense Asked:</span>
                        <strong class="text-dark fs-6">{{ number_format($totalExpenseRequested, 2) }} ETB</strong>
                    </div>
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <span class="text-muted small">Paid / Disbursed:</span>
                        <strong class="text-success fs-6">{{ number_format($totalExpensePaid, 2) }} ETB</strong>
                    </div>
                    <button type="button" class="btn btn-warning btn-sm w-100 fw-bold rounded-pill text-dark" data-bs-toggle="modal" data-bs-target="#askMoneyModal">
                        <i class="fa-solid fa-plus-circle me-1"></i>New Expense Request
                    </button>
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
                            <span class="text-muted">Reported By</span>
                            <span class="fw-semibold text-dark">{{ $maintenanceRequest->reportedBy->name ?? ($maintenanceRequest->employee->full_name ?? 'N/A') }}</span>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span class="text-muted">Submitted Date</span>
                            <span>{{ $maintenanceRequest->created_at->format('d M Y, H:i') }}</span>
                        </div>
                        @if($maintenanceRequest->assignedTo)
                        <div class="d-flex justify-content-between">
                            <span class="text-muted">Assigned Handler</span>
                            <span class="fw-semibold text-primary">{{ $maintenanceRequest->assignedTo->name }}</span>
                        </div>
                        @endif
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
                    </div>

                    <div class="alert alert-light border mt-3 mb-0 small text-muted">
                        <i class="fa-solid fa-circle-info text-info me-1"></i>
                        <strong>Approval Routing:</strong> Requests up to 5,000 ETB will be directly routed to HR. Requests above 5,000 ETB will automatically require General Manager (GM) approval before reaching Finance.
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
@endsection
