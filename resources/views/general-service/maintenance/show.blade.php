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
                                                    'sent_to_store_manager' => ['class' => 'bg-warning text-dark', 'label' => 'Sent to Store Manager'],
                                                    'pending', 'pending_planning' => ['class' => 'bg-warning text-dark', 'label' => 'Pending'],
                                                    'planning_approved'     => ['class' => 'bg-info text-dark',    'label' => 'Planning Approved'],
                                                    'issued'                => ['class' => 'bg-success',           'label' => 'Issued from Store'],
                                                    'processed'             => ['class' => 'bg-info text-dark',    'label' => 'Processed by Store'],
                                                    'needs_purchase', 'sent_to_pr' => ['class' => 'bg-primary',   'label' => 'In Procurement (PR)'],
                                                    'rejected'              => ['class' => 'bg-danger',            'label' => 'Rejected'],
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
                                            {!! $exp->status_badge !!}
                                            @if($exp->status === \App\Models\ExpenseRequest::STATUS_PAID && $exp->paid_at)
                                                <small class="text-muted d-block" style="font-size:0.75rem;">Paid {{ $exp->paid_at->format('d M') }}</small>
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

@push('scripts')
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
</script>
@endpush
@endsection

