@extends('layouts.app')
@section('title', 'PR: ' . $purchaseRequest->pr_no)

@php
    $authUser = auth()->user();
    $rawUserRoles = $authUser ? $authUser->roles->pluck('name')->map(fn($r) => strtolower(str_replace([' ', '-'], '_', trim($r))))->toArray() : [];
    $isGlobalAdmin = ($authUser && $authUser->isGlobalAdmin()) || in_array('global_admin', $rawUserRoles) || in_array('admin', $rawUserRoles);
    $isAuditorUser = in_array('auditor', $rawUserRoles) || in_array('audit', $rawUserRoles) || in_array('internal_auditor', $rawUserRoles) || in_array('audit_team', $rawUserRoles) || ($authUser && $authUser->hasAnyRole(['auditor', 'audit', 'internal_auditor', 'Auditor', 'Audit']));
    
    $isStoreReviewStage = ($purchaseRequest->status === \App\Models\PurchaseRequest::STATUS_PENDING_STORE_REVIEW && !($isFinalIntake ?? false));
    $isStoreManagerOnly = in_array('store_manager', $rawUserRoles) && !in_array('purchase_manager', $rawUserRoles) && !in_array('finance_head', $rawUserRoles) && !$isGlobalAdmin;
    $hideMoneyColumns = $isStoreReviewStage || $isStoreManagerOnly;

    // Check whether the logged-in user can execute action controls for the current stage
    $canActOnCurrentStage = false;
@endphp

@section('content')
<div class="container-fluid px-4 py-3">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <h1 class="h3 mb-0 text-gray-800"><i class="fas fa-file-invoice text-primary me-2"></i>{{ $purchaseRequest->pr_no }}</h1>
                <span class="badge bg-light text-muted border px-2 py-1 small" title="Strict Policy: Records and materials are locked after creation. Only Global Admin can delete or add/modify materials.">
                    <i class="fas fa-lock me-1 text-secondary"></i> Locked After Creation
                </span>
                @if($isGlobalAdmin)
                <span class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25 px-2 py-1 small">
                    <i class="fas fa-shield-halved me-1"></i> Admin Override Enabled
                </span>
                @endif
            </div>
            <p class="text-muted small mb-0 mt-1">Project: <strong>{{ $purchaseRequest->project->name ?? 'N/A' }}</strong> | Channel: <strong>{{ $purchaseRequest->materialRequest->source ?? 'Direct PR' }}</strong></p>
        </div>
        <div class="d-flex align-items-center gap-2 flex-wrap">
            @if($purchaseRequest->trashed() || $purchaseRequest->status === \App\Models\PurchaseRequest::STATUS_CANCELLED)
            <span class="badge bg-danger text-white py-2 px-3 fw-semibold">
                <i class="fas fa-lock me-1"></i> Deleted &amp; Locked
            </span>
            @elseif($purchaseRequest->status === \App\Models\PurchaseRequest::STATUS_TRANSFERRED)
            <span class="badge bg-info text-white py-2 px-3 fw-semibold">
                <i class="fas fa-lock me-1"></i> Transferred &amp; Locked
            </span>
            @elseif($isGlobalAdmin)
            <button type="button" class="btn btn-outline-danger shadow-sm fw-semibold" data-bs-toggle="modal" data-bs-target="#deletePrModal">
                <i class="fas fa-trash-can me-1"></i> Delete PR (Admin Only)
            </button>
            @endif
            <a href="{{ route('procurement.my-queue') }}" class="btn btn-outline-primary"><i class="fas fa-tasks me-1"></i>Procurement — My Queue</a>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show shadow-sm">{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    @endif
    @if(session('warning'))
        <div class="alert alert-warning alert-dismissible fade show shadow-sm">{{ session('warning') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show shadow-sm">{{ session('error') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    @endif
    @if($errors->any())
        <div class="alert alert-danger alert-dismissible fade show shadow-sm border-danger">
            <div class="d-flex align-items-center mb-1">
                <i class="fas fa-exclamation-triangle me-2 text-danger"></i>
                <strong class="text-danger">Action Error: Please check the following issue(s):</strong>
            </div>
            <ul class="mb-0 ps-3 small text-danger">
                @foreach($errors->all() as $err)
                    <li>{{ $err }}</li>
                @endforeach
            </ul>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    {{-- Top Status Alerts for Transferred or Deleted Records (Locked in Procurement) --}}
    @if($purchaseRequest->status === \App\Models\PurchaseRequest::STATUS_TRANSFERRED)
        <div class="alert alert-info border-start border-4 border-info shadow-sm mb-4 d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div class="d-flex align-items-center gap-3">
                <div class="p-3 bg-info bg-opacity-25 rounded-circle text-info">
                    <i class="fa-solid fa-truck-moving fa-2x"></i>
                </div>
                <div>
                    <h5 class="fw-bold mb-0 text-dark">Fulfilled via Store Transfer — Locked in Procurement</h5>
                    <p class="text-muted small mb-0">All requested materials have been transferred and fulfilled via Store Transfer. This request is completed and permanently locked in Procurement.</p>
                </div>
            </div>
            <span class="badge bg-info text-white px-3 py-2 fs-6 rounded-pill">
                <i class="fa-solid fa-lock me-1"></i> Transferred &amp; Locked
            </span>
        </div>
    @elseif($purchaseRequest->trashed() || $purchaseRequest->status === \App\Models\PurchaseRequest::STATUS_CANCELLED)
        <div class="alert alert-danger border-start border-4 border-danger shadow-sm mb-4 d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div class="d-flex align-items-center gap-3">
                <div class="p-3 bg-danger bg-opacity-25 rounded-circle text-danger">
                    <i class="fa-solid fa-trash-can fa-2x"></i>
                </div>
                <div>
                    <h5 class="fw-bold mb-0 text-danger">Record Deleted &amp; Locked</h5>
                    <p class="text-muted small mb-0">This purchase request was removed and moved to the Deleted category. All stages, edits, and workflow actions are permanently locked.</p>
                    @if($purchaseRequest->rejection_reason)
                        <small class="text-danger fst-italic"><i class="fas fa-info-circle me-1"></i>{{ $purchaseRequest->rejection_reason }}</small>
                    @endif
                </div>
            </div>
            <span class="badge bg-danger text-white px-3 py-2 fs-6 rounded-pill">
                <i class="fa-solid fa-lock me-1"></i> Deleted &amp; Locked
            </span>
        </div>
    @endif

@php
    $currentRoleName = $purchaseRequest->current_owner_role;

    if ($purchaseRequest->trashed() || in_array($purchaseRequest->status, [\App\Models\PurchaseRequest::STATUS_CANCELLED, \App\Models\PurchaseRequest::STATUS_TRANSFERRED])) {
        $canActOnCurrentStage = false;
    } elseif (!$isAuditorUser) {
        switch ($purchaseRequest->status) {
            case \App\Models\PurchaseRequest::STATUS_DRAFT:
                $canActOnCurrentStage = $isGlobalAdmin || ($authUser && $purchaseRequest->requested_by === $authUser->id) || in_array('coordinator', $rawUserRoles) || in_array('site_engineer', $rawUserRoles);
                break;

            case \App\Models\PurchaseRequest::STATUS_PENDING_PLANNING:
                // Only Planning Manager can approve or reject (Global Admin and staff view only)
                $canActOnCurrentStage = in_array('planning_manager', $rawUserRoles);
                break;

            case \App\Models\PurchaseRequest::STATUS_PENDING_HR_APPROVAL:
                $canActOnCurrentStage = $isGlobalAdmin || in_array('coordinator', $rawUserRoles) || in_array('project_coordinator', $rawUserRoles) || in_array('site_coordinator', $rawUserRoles) || in_array('hr_manager', $rawUserRoles) || in_array('hr', $rawUserRoles);
                break;

            case \App\Models\PurchaseRequest::STATUS_PENDING_STORE_REVIEW:
                if ($isFinalIntake ?? false) {
                    // Final intake must be done by Store Keeper (or Admin), not Store Manager
                    $canActOnCurrentStage = $isGlobalAdmin || in_array('store_keeper', $rawUserRoles) || in_array('storekeeper', $rawUserRoles) || in_array('store_clerk', $rawUserRoles);
                } else {
                    // Initial store review belongs to Store Manager
                    $canActOnCurrentStage = $isGlobalAdmin || in_array('store_manager', $rawUserRoles);
                }
                break;

            case \App\Models\PurchaseRequest::STATUS_PENDING_PROC_MANAGER:
                $canActOnCurrentStage = $isGlobalAdmin || in_array('purchase_manager', $rawUserRoles) || in_array('procurement_manager', $rawUserRoles);
                break;

            case \App\Models\PurchaseRequest::STATUS_PENDING_PROC_TEAM:
                $canActOnCurrentStage = $isGlobalAdmin || in_array('purchase', $rawUserRoles) || in_array('procurement_team', $rawUserRoles) || in_array('purchaser', $rawUserRoles) || in_array('buyer', $rawUserRoles);
                break;

            case \App\Models\PurchaseRequest::STATUS_PENDING_MARKETING:
                $canActOnCurrentStage = $isGlobalAdmin || in_array('purchase_manager', $rawUserRoles) || in_array('procurement_manager', $rawUserRoles) || in_array('marketing', $rawUserRoles) || in_array('market_research', $rawUserRoles);
                break;

            case \App\Models\PurchaseRequest::STATUS_PENDING_PROFORMA_SELECTION:
                $canActOnCurrentStage = $isGlobalAdmin || in_array('purchase_manager', $rawUserRoles) || in_array('procurement_manager', $rawUserRoles);
                break;

            case \App\Models\PurchaseRequest::STATUS_PENDING_GM:
                $canActOnCurrentStage = $isGlobalAdmin || in_array('gm', $rawUserRoles) || in_array('general_manager', $rawUserRoles);
                break;

            case \App\Models\PurchaseRequest::STATUS_PENDING_FINANCE:
                $canActOnCurrentStage = $isGlobalAdmin || in_array('finance_head', $rawUserRoles) || in_array('finance_manager', $rawUserRoles) || in_array('finance', $rawUserRoles);
                break;

            case \App\Models\PurchaseRequest::STATUS_PENDING_PAYMENT:
                if (!$purchaseRequest->payment->assigned_finance_staff_id) {
                    $canActOnCurrentStage = $isGlobalAdmin || in_array('finance_head', $rawUserRoles) || in_array('finance_manager', $rawUserRoles);
                } else {
                    $canActOnCurrentStage = $isGlobalAdmin || in_array('finance_head', $rawUserRoles) || ($authUser && $purchaseRequest->payment->assigned_finance_staff_id === $authUser->id);
                }
                break;

            case \App\Models\PurchaseRequest::STATUS_PENDING_RECEIPT_UPLOAD:
                $canActOnCurrentStage = $isGlobalAdmin || in_array('purchase', $rawUserRoles) || in_array('procurement_team', $rawUserRoles) || in_array('purchaser', $rawUserRoles) || in_array('buyer', $rawUserRoles) || in_array('purchase_manager', $rawUserRoles) || in_array('finance_head', $rawUserRoles) || in_array('finance', $rawUserRoles) || in_array('store_keeper', $rawUserRoles) || in_array('store_manager', $rawUserRoles);
                break;

            case \App\Models\PurchaseRequest::STATUS_PENDING_RECEIPT_VERIFY:
                $canActOnCurrentStage = $isGlobalAdmin || in_array('finance_head', $rawUserRoles) || in_array('finance_manager', $rawUserRoles) || in_array('finance', $rawUserRoles) || in_array('purchase', $rawUserRoles) || in_array('store_keeper', $rawUserRoles);
                break;

            case \App\Models\PurchaseRequest::STATUS_PENDING_DRIVER:
                $canActOnCurrentStage = $isGlobalAdmin || in_array('general_service', $rawUserRoles) || in_array('general_services', $rawUserRoles);
                break;

            default:
                $canActOnCurrentStage = false;
                break;
        }
    }
@endphp

    @if($isAuditorUser)
        <div class="alert alert-info border-start border-4 border-info shadow-sm mb-4 d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div class="d-flex align-items-center gap-2">
                <div class="p-2.5 rounded-circle bg-info bg-opacity-25 text-info">
                    <i class="fa-solid fa-shield-halved fa-lg"></i>
                </div>
                <div>
                    <strong class="d-block text-dark">Internal Audit Oversight — Read-Only Mode</strong>
                    <span class="text-muted small">You have complete read-only visibility into this Purchase Request's entire history, stage logs, vendor proformas, GM approvals, payment receipts, and delivery details.</span>
                </div>
            </div>
            <span class="badge bg-white text-info border border-info px-3 py-2 fw-semibold">
                <i class="fa-solid fa-lock me-1"></i> Read-Only Audit View
            </span>
        </div>
    @endif

    @if($purchaseRequest->current_owner_role === 'global_admin')
        <div class="alert alert-warning border-start border-4 border-warning shadow-sm mb-4 d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div class="d-flex align-items-center gap-2">
                <div class="p-2 rounded-circle bg-warning bg-opacity-25 text-dark">
                    <i class="fa-solid fa-user-shield fa-lg"></i>
                </div>
                <div>
                    <strong class="d-block text-dark">Assigned to Global Admin (No User in Target Role)</strong>
                    <span class="text-muted small">This purchase request stage was automatically routed to Global Admin because no active user is currently assigned to the required role for this lifecycle stage. As Global Admin, you can review and take action directly below.</span>
                </div>
            </div>
            <span class="badge bg-warning text-dark px-3 py-2 fw-semibold">
                <i class="fa-solid fa-bolt me-1"></i> Admin Action Required
            </span>
        </div>
    @endif

    <div class="row g-4">
        <!-- Left Panel: Summary & Details -->
        <div class="col-lg-4">
            <!-- PR Overview Card -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white font-weight-bold py-3 border-0">
                    <i class="fas fa-info-circle text-primary me-2"></i>Lifecycle Summary
                </div>
                <div class="card-body p-0">
                    <table class="table table-sm table-borderless mb-0 align-middle">
                        <tr><th width="40%" class="ps-3 text-muted">Status</th><td><span class="badge bg-{{ \App\Models\PurchaseRequest::statusBadgeClass($purchaseRequest->status) }}">{{ $purchaseRequest->status_label }}</span></td></tr>
                        <tr><th class="ps-3 text-muted">Current Role Owner</th><td>
                            @if($purchaseRequest->current_owner_role === 'global_admin')
                                <span class="badge bg-warning text-dark"><i class="fas fa-user-shield me-1"></i>Global Admin (Unassigned Role)</span>
                            @elseif(($isFinalIntake ?? false) && $purchaseRequest->status === \App\Models\PurchaseRequest::STATUS_PENDING_STORE_REVIEW)
                                <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 fw-bold"><i class="fas fa-boxes-packing me-1"></i>Store Keeper (Final Intake)</span>
                            @elseif($purchaseRequest->current_owner_role === 'coordinator')
                                <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 fw-bold"><i class="fas fa-user-tie me-1"></i>Project Coordinator</span>
                            @elseif($purchaseRequest->current_owner_role === 'planning')
                                <span class="badge bg-warning bg-opacity-10 text-dark border border-warning border-opacity-25 fw-bold"><i class="fas fa-calendar-check me-1"></i>Planning Team</span>
                            @else
                                <span class="badge bg-secondary bg-opacity-10 text-dark"><i class="fas fa-user-tag me-1"></i>{{ ucfirst(str_replace('_', ' ', $purchaseRequest->current_owner_role ?? 'Completed')) }}</span>
                            @endif
                        </td></tr>
                        <tr><th class="ps-3 text-muted">Priority</th><td><span class="badge bg-{{ $purchaseRequest->priority === 'urgent' ? 'danger' : ($purchaseRequest->priority === 'high' ? 'warning' : 'secondary') }}">{{ ucfirst($purchaseRequest->priority) }}</span></td></tr>
                        <tr><th class="ps-3 text-muted">Requested By</th><td>{{ $purchaseRequest->requestedBy->name ?? 'N/A' }}</td></tr>
                        <tr><th class="ps-3 text-muted">Required Date</th><td>{{ optional($purchaseRequest->required_date)->format('M d, Y') ?? '-' }}</td></tr>
                        <tr><th class="ps-3 text-muted">Justification</th><td>{{ $purchaseRequest->justification ?? '-' }}</td></tr>
                    </table>
                </div>
            </div>

            <!-- Lifecycle Action Box (Interactive per Stage when Owned, otherwise Locked Mode) -->
            @if($canActOnCurrentStage)
            <div class="card border-primary shadow-sm mb-4">
                <div class="card-header bg-primary text-white font-weight-bold py-3 d-flex justify-content-between align-items-center">
                    <span><i class="fas fa-cogs me-2"></i>Stage Action Controls</span>
                    <span class="badge bg-light text-primary fw-bold"><i class="fas fa-bolt me-1"></i>Action Required</span>
                </div>
                <div class="card-body">
                    <!-- STAGE 1 / DRAFT: Submit to Store Manager -->
                    @if($purchaseRequest->status === \App\Models\PurchaseRequest::STATUS_DRAFT)
                        <form action="{{ route('purchase-requests.submit', $purchaseRequest) }}" method="POST">
                            @csrf
                            <p class="small text-muted mb-2">Submit draft to Store Manager for stock check.</p>
                            <button class="btn btn-primary btn-sm w-100"><i class="fas fa-paper-plane me-1"></i> Submit to Store Manager</button>
                        </form>

                    <!-- STAGE: Planning Team Review & Approval -->
                    @elseif($purchaseRequest->status === \App\Models\PurchaseRequest::STATUS_PENDING_PLANNING)
                        <div class="mb-3">
                            <h6 class="fw-bold text-dark mb-1">
                                <i class="fas fa-calendar-check text-primary me-1"></i> Planning Team Review & Approval
                            </h6>
                            <p class="small text-muted mb-0">
                                Verify project requirements, schedule alignment, and material quantities. Upon approval, this request will be forwarded to the <strong>Project Coordinator</strong>.
                            </p>
                        </div>
                        @if($purchaseRequest->materialRequest)
                            <div class="alert alert-light border py-2 px-3 small mb-3">
                                <div class="fw-bold text-dark mb-1"><i class="fas fa-link text-primary me-1"></i> Linked Requisition:</div>
                                <div class="text-muted">
                                    <a href="{{ route('material-requests.show', $purchaseRequest->materialRequest) }}" target="_blank" class="fw-bold text-decoration-none">
                                        #{{ $purchaseRequest->materialRequest->reference_number }}
                                    </a> 
                                    ({{ $purchaseRequest->materialRequest->source ?? 'Emergency' }})
                                </div>
                            </div>
                        @endif
                        @if($canActOnCurrentStage)
                        <div class="d-grid gap-2">
                            <form action="{{ route('purchase-requests.planning-approve', $purchaseRequest) }}" method="POST">
                                @csrf
                                <button type="submit" class="btn btn-success btn-sm w-100 fw-bold shadow-sm py-2">
                                    <i class="fas fa-check-circle me-1"></i> Approve & Send to Coordinator
                                </button>
                            </form>
                            <button type="button" class="btn btn-outline-danger btn-sm w-100 py-2" data-bs-toggle="collapse" data-bs-target="#planningRejectCollapse">
                                <i class="fas fa-times-circle me-1"></i> Reject Request
                            </button>
                            <div class="collapse mt-2" id="planningRejectCollapse">
                                <form action="{{ route('purchase-requests.planning-reject', $purchaseRequest) }}" method="POST" class="p-3 border rounded bg-light">
                                    @csrf
                                    <label class="form-label small fw-bold text-danger">Rejection Reason <span class="text-danger">*</span></label>
                                    <textarea name="rejection_reason" class="form-control form-control-sm mb-2" rows="2" placeholder="Explain why this request cannot be approved..." required></textarea>
                                    <button type="submit" class="btn btn-danger btn-sm w-100 fw-bold">
                                        <i class="fas fa-ban me-1"></i> Confirm Rejection
                                    </button>
                                </form>
                            </div>
                        </div>
                        @else
                        <div class="alert alert-secondary border py-2 px-3 small mb-0">
                            <div class="fw-bold text-dark mb-1">
                                <i class="fas fa-user-clock text-primary me-1"></i> Awaiting Planning Manager Decision
                            </div>
                            <div class="text-muted">
                                This request is awaiting approval from the Planning Manager. Global Admin and other staff have view-only access at this stage.
                            </div>
                        </div>
                        @endif

                    <!-- STAGE: Coordinator Review & Dispatch -->
                    @elseif($purchaseRequest->status === \App\Models\PurchaseRequest::STATUS_PENDING_HR_APPROVAL)
                        <div class="mb-3">
                            <h6 class="fw-bold text-dark mb-1">
                                <i class="fas fa-user-tie text-primary me-1"></i> Project Coordinator Review & Dispatch
                            </h6>
                            <p class="small text-muted mb-0">
                                Planning has approved this request. As Coordinator, dispatch to the <strong>Store Manager</strong> for store stock review and transfers, or route directly to <strong>Procurement Manager</strong> if urgent purchasing is required.
                            </p>
                        </div>
                        @if($purchaseRequest->materialRequest)
                            <div class="alert alert-light border py-2 px-3 small mb-3">
                                <div class="fw-bold text-dark mb-1"><i class="fas fa-link text-primary me-1"></i> Linked Requisition:</div>
                                <div class="text-muted">
                                    <a href="{{ route('material-requests.show', $purchaseRequest->materialRequest) }}" target="_blank" class="fw-bold text-decoration-none">
                                        #{{ $purchaseRequest->materialRequest->reference_number }}
                                    </a> 
                                    ({{ $purchaseRequest->materialRequest->source ?? 'Emergency' }})
                                </div>
                            </div>
                        @endif
                        <div class="d-grid gap-2">
                            <form action="{{ route('purchase-requests.coordinator-approve', $purchaseRequest) }}" method="POST">
                                @csrf
                                <button type="submit" class="btn btn-primary btn-sm w-100 fw-bold shadow-sm py-2">
                                    <i class="fas fa-share me-1"></i> Dispatch to Store Manager (Stock Review)
                                </button>
                            </form>
                            <form action="{{ route('purchase-requests.send-to-pm', $purchaseRequest) }}" method="POST">
                                @csrf
                                <button type="submit" class="btn btn-outline-success btn-sm w-100 py-2 fw-semibold" onclick="return confirm('Skip Store Manager and send directly to Procurement Manager for purchase?');">
                                    <i class="fas fa-cart-shopping me-1"></i> Skip Store & Send to Procurement Manager
                                </button>
                            </form>
                            <button type="button" class="btn btn-outline-danger btn-sm w-100 py-2" data-bs-toggle="collapse" data-bs-target="#coordinatorRejectCollapse">
                                <i class="fas fa-times-circle me-1"></i> Reject Request
                            </button>
                            <div class="collapse mt-2" id="coordinatorRejectCollapse">
                                <form action="{{ route('purchase-requests.coordinator-reject', $purchaseRequest) }}" method="POST" class="p-3 border rounded bg-light">
                                    @csrf
                                    <label class="form-label small fw-bold text-danger">Rejection Reason <span class="text-danger">*</span></label>
                                    <textarea name="rejection_reason" class="form-control form-control-sm mb-2" rows="2" placeholder="Explain why this request is being rejected or returned..." required></textarea>
                                    <button type="submit" class="btn btn-danger btn-sm w-100 fw-bold">
                                        <i class="fas fa-ban me-1"></i> Confirm Rejection
                                    </button>
                                </form>
                            </div>
                        </div>

                    <!-- STAGE 2: Store Manager Review (Transfer vs Send to Purchase) -->
                    @elseif($purchaseRequest->status === \App\Models\PurchaseRequest::STATUS_PENDING_STORE_REVIEW && !($isFinalIntake ?? false))
                        <div class="mb-3">
                            <h6 class="fw-bold text-dark mb-1">
                                <i class="fas fa-boxes-stacked text-primary me-1"></i> Store Manager Inventory Decision
                            </h6>
                            <p class="small text-muted mb-0">Review stock availability across stores. Decide which items to fulfill via <strong>Store Transfer</strong> or send to <strong>Purchase</strong>. (No pricing needed at this stage).</p>
                        </div>
                        @if($purchaseRequest->pm_sendback_reason)
                            <div class="alert alert-warning py-2 px-3 small mb-3 border-start border-4 border-warning shadow-xs">
                                <div class="fw-bold text-dark mb-1"><i class="fas fa-undo text-warning me-1"></i> Returned from Purchasing Manager:</div>
                                <div class="text-muted">{{ $purchaseRequest->pm_sendback_reason }}</div>
                            </div>
                        @endif
                        <div class="d-grid gap-2">
                            <button type="button" class="btn btn-outline-info btn-sm w-100 text-start py-2 fw-semibold" onclick="openSelectiveTransferModal()">
                                <i class="fas fa-truck-ramp-box text-info me-1"></i> 🚚 Transfer Selected Items (<span class="selected-items-count fw-bold">0</span>)
                            </button>
                            <button type="button" class="btn btn-outline-success btn-sm w-100 text-start py-2 fw-semibold" onclick="openSelectiveSendPmModal()">
                                <i class="fas fa-cart-shopping text-success me-1"></i> 🛒 Send Selected to Purchase (<span class="selected-items-count fw-bold">0</span>)
                            </button>
                            <button type="button" class="btn btn-primary btn-sm w-100 fw-bold shadow-sm py-2" data-bs-toggle="modal" data-bs-target="#splitAndProcessModal">
                                <i class="fas fa-random me-1"></i> Smart Split All: Transfer + Buy
                            </button>
                            <form action="{{ route('purchase-requests.send-to-pm', $purchaseRequest) }}" method="POST" class="mt-2 pt-2 border-top">
                                @csrf
                                <button class="btn btn-light border btn-sm w-100 text-muted" onclick="return confirm('Send ALL items on this PR directly to Procurement Manager for purchase?');">
                                    <i class="fas fa-share me-1"></i> Send Entire PR to Purchase
                                </button>
                            </form>
                        </div>

                    <!-- STAGE 3: Procurement Manager Triage & Sourcing Decision -->
                    @elseif($purchaseRequest->status === \App\Models\PurchaseRequest::STATUS_PENDING_PROC_MANAGER)
                        <div class="mb-3 p-2 bg-light rounded border">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <span class="small fw-bold text-dark"><i class="fas fa-boxes-stacked text-warning me-1"></i>Cross-Store Sourcing</span>
                                <span class="badge bg-primary rounded-pill"><span class="selected-items-count fw-bold">0</span> Selected</span>
                            </div>
                            <p class="small text-muted mb-2" style="font-size: 11px;">
                                Check items available in store inventory to return them to Store Manager to fulfill or decide how to buy.
                            </p>
                            <button type="button" class="btn btn-warning text-dark btn-sm w-100 fw-bold shadow-sm" onclick="openPmSendBackStoreModal()">
                                <i class="fas fa-undo me-1"></i> Send Selected to Store Manager (<span class="selected-items-count fw-bold">0</span>)
                            </button>
                        </div>

                        <h6 class="font-weight-bold mb-2"><i class="fas fa-route me-1"></i>Procurement Sourcing Path:</h6>
                        <ul class="nav nav-pills nav-justified mb-3" id="sourcingTab" role="tablist">
                            <li class="nav-item">
                                <button class="nav-link active btn-sm" data-bs-toggle="tab" data-bs-target="#tabDirect">
                                    <i class="fas fa-bolt me-1"></i> Direct Buy
                                </button>
                            </li>
                            <li class="nav-item">
                                <button class="nav-link btn-sm" data-bs-toggle="tab" data-bs-target="#tabProforma">
                                    <i class="fas fa-file-invoice-dollar me-1"></i> Proforma
                                </button>
                            </li>
                        </ul>
                        <div class="tab-content mb-3" id="sourcingTabContent">
                            <div class="tab-pane fade show active" id="tabDirect">
                                <form action="{{ route('purchase-requests.send-to-proc-team', $purchaseRequest) }}" method="POST">
                                    @csrf
                                    <input type="hidden" name="sourcing_method" value="direct_buy">
                                    <p class="small text-muted mb-2">Send request to Purchase Team for <strong>Direct Buy</strong> to add purchase prices.</p>
                                    <div class="mb-2">
                                        <label class="form-label small fw-bold text-uppercase">Instructions / Notes for Purchase Team</label>
                                        <textarea name="notes" class="form-control form-control-sm" rows="2" placeholder="Instructions for Purchase Team to add prices..."></textarea>
                                    </div>
                                    <div class="d-grid gap-1">
                                        <button class="btn btn-success btn-sm w-100 fw-bold">
                                            <i class="fas fa-paper-plane me-1"></i> Send Entire PR (Direct Buy)
                                        </button>
                                        <button type="button" class="btn btn-outline-success btn-sm w-100" onclick="openPmDirectBuyModal()">
                                            <i class="fas fa-bolt me-1"></i> Direct Buy Selected (<span class="selected-items-count fw-bold">0</span>)
                                        </button>
                                    </div>
                                </form>
                            </div>
                            <div class="tab-pane fade" id="tabProforma">
                                <form action="{{ route('purchase-requests.send-to-proc-team', $purchaseRequest) }}" method="POST">
                                    @csrf
                                    <input type="hidden" name="sourcing_method" value="proforma">
                                    <p class="small text-muted mb-2">Assign to Purchase Team to <strong>attach & collect proforma quotes</strong> from suppliers.</p>
                                    <div class="mb-2">
                                        <label class="form-label small fw-bold text-uppercase">Instructions / Notes for Purchase Team</label>
                                        <textarea name="notes" class="form-control form-control-sm" rows="2" placeholder="Specific vendor or quote requirements..."></textarea>
                                    </div>
                                    <div class="d-grid gap-1">
                                        <button class="btn btn-primary btn-sm w-100 fw-bold">
                                            <i class="fas fa-paper-plane me-1"></i> Send Entire PR (Proforma)
                                        </button>
                                        <button type="button" class="btn btn-outline-primary btn-sm w-100" onclick="openPmProformaModal()">
                                            <i class="fas fa-file-invoice-dollar me-1"></i> Proforma Selected (<span class="selected-items-count fw-bold">0</span>)
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>

                        <div class="border-top pt-2 mt-2">
                            <button class="btn btn-outline-secondary btn-sm w-100" data-bs-toggle="collapse" data-bs-target="#sendBackStoreForm">
                                <i class="fas fa-undo me-1"></i> Send Entire PR Back to Store
                            </button>
                            <div class="collapse mt-2" id="sendBackStoreForm">
                                <form action="{{ route('purchase-requests.send-back-to-store', $purchaseRequest) }}" method="POST">
                                    @csrf
                                    <textarea name="reason" class="form-control form-control-sm mb-2" placeholder="Reason for sending back entire PR..." required></textarea>
                                    <button class="btn btn-danger btn-sm w-100">Confirm Send Back Entire PR</button>
                                </form>
                            </div>
                        </div>

                    <!-- STAGE 4: Procurement Team Stage (Pricing for Direct Buy or Attaching Proformas) -->
                    @elseif($purchaseRequest->status === \App\Models\PurchaseRequest::STATUS_PENDING_PROC_TEAM)
                        @if($purchaseRequest->sourcing_method === 'direct_buy')
                            <h6 class="font-weight-bold text-success mb-2">
                                <i class="fas fa-bolt me-1"></i>Direct Buy: Add Material Prices
                            </h6>
                            <p class="small text-muted mb-2">Enter the direct purchase unit price for each requested material below.</p>
                            @if($purchaseRequest->procurement_team_notes)
                                <div class="alert alert-warning py-2 px-3 small mb-3 border-start border-4 border-warning shadow-xs">
                                    <div class="fw-bold text-dark mb-1"><i class="fas fa-comment-dots text-warning me-1"></i> PM Instructions / Feedback:</div>
                                    <div class="text-muted">{{ $purchaseRequest->procurement_team_notes }}</div>
                                </div>
                            @endif
                            @php
                                $initialDirectQtySum = (float)$purchaseRequest->items->sum(function($itm) {
                                    $pQty = (float)($itm->purchased_quantity ?? 0);
                                    if ($pQty <= 0) {
                                        $pQty = method_exists($itm, 'getRemainingQty') && $itm->getRemainingQty() > 0 
                                            ? (float)$itm->getRemainingQty() 
                                            : (float)$itm->quantity;
                                    }
                                    return ($pQty > 0) ? $pQty : (float)$itm->quantity;
                                });

                                $initialDirectItemsSum = (float)$purchaseRequest->items->sum(function($itm) {
                                    $u = (float)($itm->estimated_unit_cost ?? 0);
                                    $pQty = (float)($itm->purchased_quantity ?? 0);
                                    if ($pQty <= 0) {
                                        $pQty = method_exists($itm, 'getRemainingQty') && $itm->getRemainingQty() > 0 
                                            ? (float)$itm->getRemainingQty() 
                                            : (float)$itm->quantity;
                                    }
                                    $eff = ($pQty > 0) ? $pQty : (float)$itm->quantity;
                                    return $eff * $u;
                                });
                                $initialDirectTotal = (float)($purchaseRequest->direct_buy_amount ?? 0);
                                if ($initialDirectTotal <= 0) {
                                    $initialDirectTotal = $initialDirectItemsSum;
                                }
                            @endphp
                            <form action="{{ route('purchase-requests.submit-direct-buy', $purchaseRequest) }}" method="POST" id="directBuyPricingForm">
                                @csrf
                                <div class="bg-light p-2 rounded border mb-3" style="max-height: 320px; overflow-y: auto;">
                                    @foreach($purchaseRequest->items as $itm)
                                    @php
                                        $pQty = (float)($itm->purchased_quantity ?? 0);
                                        if ($pQty <= 0) {
                                            $pQty = method_exists($itm, 'getRemainingQty') && $itm->getRemainingQty() > 0 
                                                ? (float)$itm->getRemainingQty() 
                                                : (float)$itm->quantity;
                                        }
                                        if ($pQty <= 0) {
                                            $pQty = (float)$itm->quantity;
                                        }
                                        $uCost = (float)($itm->estimated_unit_cost ?? 0);
                                        $lineTotal = round($pQty * $uCost, 2);
                                        $unitLabel = $itm->unit ?? ($itm->product->unit ?? 'pcs');
                                    @endphp
                                    <div class="mb-2 pb-2 border-bottom">
                                        <div class="d-flex justify-content-between align-items-center mb-1">
                                            <span class="small fw-bold text-dark">{{ $itm->product->name ?? 'Item #' . $itm->product_id }}</span>
                                            <span class="badge bg-secondary-subtle text-dark border" title="Requested quantity">
                                                Req: {{ (float)$itm->quantity }} {{ $unitLabel }}
                                            </span>
                                        </div>
                                        <div class="row g-2 mb-1">
                                            <div class="col-6">
                                                <label class="form-label text-muted mb-0 fw-semibold" style="font-size: 10px;">PURCHASED QTY</label>
                                                <div class="input-group input-group-sm">
                                                    <input type="number" step="0.001" min="0" 
                                                           name="purchased_quantities[{{ $itm->id }}]" 
                                                           id="direct_item_qty_{{ $itm->id }}"
                                                           value="{{ $pQty > 0 ? (float)$pQty : (float)$itm->quantity }}" 
                                                           class="form-control form-control-sm direct-item-qty fw-bold text-dark" 
                                                           data-item-id="{{ $itm->id }}"
                                                           data-unit="{{ $unitLabel }}"
                                                           oninput="recalculateDirectTotal()"
                                                           placeholder="0.000" required>
                                                    <span class="input-group-text py-0 px-1 bg-light text-muted" style="font-size: 10px;">{{ $unitLabel }}</span>
                                                </div>
                                            </div>
                                            <div class="col-6">
                                                <label class="form-label text-muted mb-0 fw-semibold" style="font-size: 10px;">UNIT PRICE (ETB)</label>
                                                <div class="input-group input-group-sm">
                                                    <span class="input-group-text py-0 px-1 bg-light text-muted" style="font-size: 10px;">ETB</span>
                                                    <input type="number" step="0.01" min="0" 
                                                           name="item_prices[{{ $itm->id }}]" 
                                                           id="direct_item_price_{{ $itm->id }}"
                                                           value="{{ $uCost > 0 ? (float)$uCost : '' }}" 
                                                           class="form-control form-control-sm direct-item-cost fw-semibold text-dark" 
                                                           data-item-id="{{ $itm->id }}"
                                                           data-qty="{{ (float)$pQty }}"
                                                           oninput="recalculateDirectTotal()"
                                                           placeholder="0.00" required>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="d-flex justify-content-between align-items-center mt-1 px-1 bg-white rounded border py-1">
                                            <span class="text-muted" style="font-size: 11px;">
                                                <i class="fas fa-coins text-warning me-1"></i>Line Total:
                                                <span class="small font-monospace text-secondary" id="directFormula_{{ $itm->id }}">({{ (float)$pQty }} &times; {{ number_format($uCost, 2) }})</span>
                                            </span>
                                            <span class="fw-bold text-success font-monospace" id="directLineTotal_{{ $itm->id }}" style="font-size: 12px;">
                                                {{ number_format($lineTotal, 2) }} ETB
                                            </span>
                                        </div>
                                    </div>
                                    @endforeach
                                </div>

                                {{-- Calculations Summary Card --}}
                                <div class="card border border-success border-opacity-25 bg-white mb-3 shadow-xs">
                                    <div class="card-body p-2">
                                        <div class="d-flex justify-content-between align-items-center mb-1">
                                            <span class="small text-muted fw-bold text-uppercase" style="font-size: 11px;">
                                                <i class="fas fa-boxes-stacked me-1 text-secondary"></i> Total Purchased Materials:
                                            </span>
                                            <span class="fw-bold text-dark font-monospace" id="directPurchasedQtyTotalDisplay">
                                                {{ number_format($initialDirectQtySum, 2) }}
                                            </span>
                                        </div>
                                        <div class="d-flex justify-content-between align-items-center mb-1">
                                            <span class="small text-muted fw-bold text-uppercase" style="font-size: 11px;">
                                                <i class="fas fa-calculator me-1 text-primary"></i> Purchased Items Subtotal:
                                            </span>
                                            <span class="fw-bold text-dark font-monospace" id="directItemsSubtotalDisplay">
                                                {{ number_format($initialDirectItemsSum, 2) }} ETB
                                            </span>
                                        </div>

                                        {{-- 15% VAT Option --}}
                                        <div class="d-flex justify-content-between align-items-center py-1 border-top border-bottom my-1">
                                            <div class="form-check form-switch mb-0">
                                                <input class="form-check-input cursor-pointer" type="checkbox" id="directVatToggle" name="include_vat" value="1" onchange="recalculateDirectTotal()">
                                                <label class="form-check-label small fw-bold text-dark cursor-pointer" for="directVatToggle" style="font-size: 12px;">
                                                    Add 15% VAT
                                                </label>
                                            </div>
                                            <span class="small font-monospace text-muted fw-semibold" id="directVatAmountDisplay">+0.00 ETB</span>
                                        </div>

                                        {{-- Grand Total Money Box --}}
                                        <div class="p-2 rounded bg-success bg-opacity-10 border border-success border-opacity-25 mt-2">
                                            <div class="d-flex justify-content-between align-items-center mb-1">
                                                <label class="form-label small fw-bold text-uppercase text-success mb-0" style="font-size: 11px;">
                                                    <i class="fas fa-money-bill-wave me-1"></i> Total Purchased Amount Money (ETB)
                                                </label>
                                                <button type="button" class="btn btn-link btn-xs p-0 text-decoration-none text-success fw-bold" onclick="syncDirectTotalWithItems()" title="Recalculate exact items total">
                                                    <i class="fas fa-rotate me-1"></i>Sync
                                                </button>
                                            </div>
                                            <div class="input-group input-group-sm">
                                                <span class="input-group-text fw-bold bg-success text-white">ETB</span>
                                                <input type="number" step="0.01" min="0" name="amount" id="directTotalAmountInput" 
                                                       class="form-control form-control-sm fw-bold bg-white text-success fs-5 font-monospace" 
                                                       required placeholder="0.00" 
                                                       value="{{ $initialDirectTotal > 0 ? number_format($initialDirectTotal, 2, '.', '') : '0.00' }}">
                                            </div>
                                            <div class="d-flex justify-content-between align-items-center mt-1">
                                                <span class="text-muted" style="font-size: 10px;">Total Money to Disburse:</span>
                                                <span class="fw-bold text-success font-monospace" id="directTotalMoneyFormatted" style="font-size: 13px;">
                                                    {{ number_format($initialDirectTotal, 2) }} ETB
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label small fw-bold text-uppercase">Procurement Notes</label>
                                    <textarea name="notes" class="form-control form-control-sm" rows="2" placeholder="Add pricing notes or remarks..."></textarea>
                                </div>
                                <button class="btn btn-success btn-sm w-100 fw-bold shadow-sm">
                                    <i class="fas fa-check-circle me-1"></i> Submit Material Prices
                                </button>
                                <div class="mt-2 text-center pt-2 border-top">
                                    <button type="button" class="btn btn-outline-success btn-xs fw-bold w-100 py-1" onclick="openProcTeamSelectiveDirectBuyModal()">
                                        <i class="fas fa-tasks me-1"></i> Price &amp; Submit Selected Items Only (<span class="selected-items-count">0</span>)
                                    </button>
                                </div>
                                <div class="mt-1 text-center">
                                    <button type="button" class="btn btn-link btn-xs text-muted text-decoration-none" onclick="openProcTeamSendBackPmModal()">
                                        <i class="fas fa-undo me-1 text-warning"></i> Return Selected Items to PM
                                    </button>
                                </div>
                            </form>
                        @else
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <h6 class="font-weight-bold text-primary mb-0">
                                    <i class="fas fa-file-invoice-dollar me-1"></i>Proforma Sourcing
                                </h6>
                                <button type="button" class="btn btn-primary btn-sm py-1 px-2 shadow-sm" data-bs-toggle="modal" data-bs-target="#attachProformaModal">
                                    <i class="fas fa-plus me-1"></i> Attach Quote
                                </button>
                            </div>
                            @if($purchaseRequest->procurement_team_notes)
                                <div class="alert alert-warning py-2 px-3 small mb-3 border-start border-4 border-warning shadow-xs">
                                    <div class="fw-bold text-dark mb-1"><i class="fas fa-comment-dots text-warning me-1"></i> PM Instructions / Feedback:</div>
                                    <div class="text-muted">{{ $purchaseRequest->procurement_team_notes }}</div>
                                </div>
                            @endif

                            @if($purchaseRequest->proformaInvoices->count() > 0)
                                <div class="bg-light p-2 rounded border mb-3 small">
                                    <div class="fw-bold text-dark mb-2 pb-1 border-bottom d-flex justify-content-between align-items-center">
                                        <span>Attached Quotes ({{ $purchaseRequest->proformaInvoices->count() }}):</span>
                                        <button type="button" class="btn btn-link btn-sm p-0 text-decoration-none" data-bs-toggle="modal" data-bs-target="#attachProformaModal">+ Add Another</button>
                                    </div>
                                    @foreach($purchaseRequest->proformaInvoices as $prof)
                                        <div class="d-flex justify-content-between align-items-center py-1 border-bottom">
                                            <div>
                                                <strong>{{ $prof->supplier->name ?? 'Supplier' }}</strong>
                                                <div class="text-muted" style="font-size: 11px;">#{{ $prof->proforma_no }} &bull; <strong class="text-primary">{{ number_format($prof->grand_total, 2) }} ETB</strong></div>
                                            </div>
                                            <div class="d-flex align-items-center gap-1">
                                                @if($prof->file_path)
                                                    <a href="{{ \App\Services\FileUploadService::url($prof->file_path) }}" target="_blank" class="btn btn-outline-primary btn-sm py-0 px-1" title="View Document">
                                                        <i class="fas fa-file"></i>
                                                    </a>
                                                @endif
                                                <form action="{{ \Illuminate\Support\Facades\Route::has('purchase-requests.delete-proforma') ? route('purchase-requests.delete-proforma', [$purchaseRequest, $prof]) : url('/purchase-requests/' . $purchaseRequest->id . '/proformas/' . $prof->id) }}" method="POST" onsubmit="return confirm('Remove this proforma quote?');" class="d-inline">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="btn btn-outline-danger btn-sm py-0 px-1" title="Delete">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </form>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>

                                <form action="{{ route('purchase-requests.submit-proformas', $purchaseRequest) }}" method="POST">
                                    @csrf
                                    <div class="mb-3">
                                        <label class="form-label small fw-bold text-uppercase">Procurement Team Notes</label>
                                        <textarea name="notes" class="form-control form-control-sm" rows="2" placeholder="Optional notes regarding attached proformas..."></textarea>
                                    </div>
                                    <button class="btn btn-primary btn-sm w-100 fw-bold">
                                        <i class="fas fa-paper-plane me-1"></i> Submit {{ $purchaseRequest->proformaInvoices->count() }} Proforma(s) to PM
                                    </button>
                                    <div class="mt-2 text-center pt-2 border-top">
                                        <button type="button" class="btn btn-outline-primary btn-xs fw-bold w-100 py-1" onclick="openProcTeamSelectiveProformaModal()">
                                            <i class="fas fa-tasks me-1"></i> Submit Selected Items Only (<span class="selected-items-count">0</span>)
                                        </button>
                                    </div>
                                    <div class="mt-1 text-center">
                                        <button type="button" class="btn btn-link btn-xs text-muted text-decoration-none" onclick="openProcTeamSendBackPmModal()">
                                            <i class="fas fa-undo me-1 text-warning"></i> Return Selected Items to PM
                                        </button>
                                    </div>
                                </form>
                            @else
                                <div class="alert alert-warning py-2 px-3 small mb-3">
                                    <i class="fas fa-exclamation-circle me-1"></i> No proformas attached yet. Click <strong>Attach Quote</strong> below to upload supplier quotation(s).
                                </div>
                                <button type="button" class="btn btn-primary btn-sm w-100 fw-bold py-2 mb-2 shadow-sm" data-bs-toggle="modal" data-bs-target="#attachProformaModal">
                                    <i class="fas fa-plus-circle me-1"></i> Attach First Proforma Quote
                                </button>
                                <div class="mt-2 text-center">
                                    <button type="button" class="btn btn-link btn-xs text-muted text-decoration-none" onclick="openProcTeamSendBackPmModal()">
                                        <i class="fas fa-undo me-1 text-warning"></i> Return Selected Items to PM
                                    </button>
                                </div>
                            @endif
                        @endif

                    <!-- STAGE 5a: Purchasing Manager Price Review & Decision -->
                    @elseif($purchaseRequest->status === \App\Models\PurchaseRequest::STATUS_PENDING_MARKETING)
                        <div class="mb-3">
                            <h6 class="fw-bold text-dark mb-1">
                                <i class="fas fa-chart-line text-primary me-1"></i> Purchasing Manager Price Review & Decision
                            </h6>
                            <p class="small text-muted mb-2">Review Direct Buy pricing against market benchmarks and monthly surveys to give decision and forward for GM approval.</p>
                        </div>

                        <form action="{{ \Illuminate\Support\Facades\Route::has('purchase-requests.add-marketing-variance') ? route('purchase-requests.add-marketing-variance', $purchaseRequest) : url('/purchase-requests/' . $purchaseRequest->id . '/add-marketing-variance') }}" method="POST">
                            @csrf
                            <div class="mb-3">
                                <label class="form-label small fw-bold text-uppercase text-muted">Direct Amount Submitted</label>
                                <div class="input-group input-group-sm">
                                    <input type="text" class="form-control form-control-sm bg-light fw-bold text-dark fs-6" id="mktDirectAmountDisplay" value="{{ number_format($purchaseRequest->direct_buy_amount, 2) }}" readonly>
                                    <span class="input-group-text fw-bold">ETB</span>
                                </div>
                            </div>

                            {{-- Benchmark Sources Quick Picker --}}
                            <div class="card border border-primary border-opacity-25 bg-light mb-3 shadow-xs">
                                <div class="card-header bg-white py-2 px-3 d-flex justify-content-between align-items-center">
                                    <span class="small fw-bold text-dark">
                                        <i class="fas fa-scale-balanced text-primary me-1"></i> Price Sources Comparison
                                    </span>
                                    <button class="btn btn-link btn-xs p-0 text-decoration-none text-primary" type="button" data-bs-toggle="collapse" data-bs-target="#marketingBenchmarkDetails" aria-expanded="false">
                                        <i class="fas fa-list-ul me-1"></i> Item Breakdown
                                    </button>
                                </div>
                                <div class="card-body p-2">
                                    <div class="d-grid gap-1 mb-2">
                                        {{-- Latest Benchmark Option --}}
                                        <button type="button" class="btn btn-sm btn-outline-primary d-flex justify-content-between align-items-center py-1 px-2 text-start {{ ($pricingBenchmarks['total_latest_benchmark'] > 0) ? 'active' : '' }}" 
                                                id="btnBenchmarkLatest"
                                                onclick="selectBenchmarkAmount({{ (float)$pricingBenchmarks['total_latest_benchmark'] }}, 'latest')">
                                            <span>
                                                <i class="fas fa-star text-warning me-1"></i>
                                                <strong>Latest Benchmark (Auto):</strong>
                                            </span>
                                            <span class="fw-bold">{{ number_format($pricingBenchmarks['total_latest_benchmark'], 2) }} ETB</span>
                                        </button>

                                        {{-- Monthly Survey Option --}}
                                        @if($pricingBenchmarks['has_monthly_data'])
                                        <button type="button" class="btn btn-sm btn-outline-secondary d-flex justify-content-between align-items-center py-1 px-2 text-start" 
                                                id="btnBenchmarkMonthly"
                                                onclick="selectBenchmarkAmount({{ (float)$pricingBenchmarks['total_monthly_market'] }}, 'monthly')">
                                            <span>
                                                <i class="fas fa-calendar-alt text-info me-1"></i>
                                                <strong>Monthly Market Survey:</strong>
                                            </span>
                                            <span class="fw-bold">{{ number_format($pricingBenchmarks['total_monthly_market'], 2) }} ETB</span>
                                        </button>
                                        @endif

                                        {{-- Last Purchase Option --}}
                                        @if($pricingBenchmarks['has_purchase_data'])
                                        <button type="button" class="btn btn-sm btn-outline-secondary d-flex justify-content-between align-items-center py-1 px-2 text-start" 
                                                id="btnBenchmarkPurchase"
                                                onclick="selectBenchmarkAmount({{ (float)$pricingBenchmarks['total_last_purchase'] }}, 'purchase')">
                                            <span>
                                                <i class="fas fa-receipt text-success me-1"></i>
                                                <strong>Last Purchase Price:</strong>
                                            </span>
                                            <span class="fw-bold">{{ number_format($pricingBenchmarks['total_last_purchase'], 2) }} ETB</span>
                                        </button>
                                        @endif
                                    </div>

                                    {{-- Collapsible item-by-item breakdown --}}
                                    <div class="collapse mt-2" id="marketingBenchmarkDetails">
                                        <div class="table-responsive bg-white rounded border" style="max-height: 200px; overflow-y: auto;">
                                            <table class="table table-sm table-hover mb-0" style="font-size: 0.78rem;">
                                                <thead class="table-light sticky-top">
                                                    <tr>
                                                        <th>Item</th>
                                                        <th>Qty</th>
                                                        <th>Monthly Price</th>
                                                        <th>Last Purchase</th>
                                                        <th class="text-end">Latest Benchmark</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    @foreach($pricingBenchmarks['items'] as $bmItem)
                                                    <tr>
                                                        <td><strong>{{ $bmItem['product_name'] }}</strong></td>
                                                        <td>{{ $bmItem['quantity'] }} {{ $bmItem['unit'] }}</td>
                                                        <td>
                                                            @if($bmItem['monthly_price'] !== null)
                                                                <div>{{ number_format($bmItem['monthly_price'], 2) }} ETB</div>
                                                                <span class="text-muted" style="font-size: 10px;">{{ optional($bmItem['monthly_date'])->format('M d, Y') ?? '' }}</span>
                                                                @if($bmItem['chosen_type'] === 'monthly_market')
                                                                    <span class="badge bg-success-subtle text-success ms-1" style="font-size: 9px;">Latest</span>
                                                                @endif
                                                            @else
                                                                <span class="text-muted italic">-</span>
                                                            @endif
                                                        </td>
                                                        <td>
                                                            @if($bmItem['purchase_price'] !== null)
                                                                <div>{{ number_format($bmItem['purchase_price'], 2) }} ETB</div>
                                                                <span class="text-muted" style="font-size: 10px;">{{ $bmItem['purchase_source'] }}</span>
                                                                @if($bmItem['chosen_type'] === 'last_purchase')
                                                                    <span class="badge bg-success-subtle text-success ms-1" style="font-size: 9px;">Latest</span>
                                                                @endif
                                                            @else
                                                                <span class="text-muted italic">-</span>
                                                            @endif
                                                        </td>
                                                        <td class="text-end fw-bold text-primary">
                                                            {{ number_format($bmItem['chosen_total'], 2) }} ETB
                                                        </td>
                                                    </tr>
                                                    @endforeach
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            {{-- Current Market Benchmark Input --}}
                            <div class="mb-3">
                                <label class="form-label small fw-bold text-uppercase text-dark">
                                    Current Market Price Benchmark (ETB) <span class="text-danger">*</span>
                                </label>
                                <div class="input-group input-group-sm">
                                    <input type="number" step="0.01" min="0" name="market_price" id="mktBenchmarkInput" 
                                           class="form-control form-control-sm fw-bold bg-white text-primary fs-6" 
                                           value="{{ $pricingBenchmarks['total_latest_benchmark'] > 0 ? (float)$pricingBenchmarks['total_latest_benchmark'] : '' }}" 
                                           oninput="recalculateMarketingVariance()" required placeholder="0.00">
                                    <span class="input-group-text fw-bold">ETB</span>
                                </div>
                                <div id="mktVarianceBadge" class="mt-2 small p-2 rounded border" style="display: none;">
                                    {{-- Populated dynamically via JS --}}
                                </div>
                            </div>

                            <div class="mb-3">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <label class="form-label small fw-bold text-uppercase mb-0">Variance Notes</label>
                                    <button type="button" class="btn btn-link btn-xs p-0 text-decoration-none" onclick="generateMarketingVarianceNote()">
                                        <i class="fas fa-magic me-1"></i> Auto-Generate Note
                                    </button>
                                </div>
                                <textarea name="variance_notes" id="mktVarianceNotes" class="form-control form-control-sm" rows="2" placeholder="Explain price variance, inflation notes, market conditions, or supplier quotes comparison..."></textarea>
                            </div>

                            <button type="submit" class="btn btn-primary btn-sm w-100 fw-bold shadow-sm py-2">
                                <i class="fas fa-check-circle me-1"></i> Approve Price & Send to GM
                            </button>
                        </form>

                        {{-- STAGE 5a: Send Back / Revision Options --}}
                        <div class="pt-2 mt-3 border-top">
                            <button type="button" class="btn btn-outline-danger btn-sm w-100 fw-semibold" data-bs-toggle="collapse" data-bs-target="#pmPriceReviewSendBackCollapse" aria-expanded="false" aria-controls="pmPriceReviewSendBackCollapse">
                                <i class="fas fa-undo me-1"></i> Send Back / Return Request
                            </button>
                            <div class="collapse mt-2" id="pmPriceReviewSendBackCollapse">
                                <div class="card card-body bg-light border-danger border-opacity-25 p-3 rounded shadow-xs">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <span class="small fw-bold text-danger">
                                            <i class="fas fa-rotate-left me-1"></i> Return Purchase Request
                                        </span>
                                        <span class="badge bg-danger-subtle text-danger border border-danger-subtle small px-2">Revision</span>
                                    </div>
                                    <p class="text-muted small mb-2" style="font-size: 11px;">
                                        Select return destination and provide reason or renegotiation instructions:
                                    </p>

                                    <!-- Destination Selection -->
                                    <div class="mb-2">
                                        <label class="form-label small fw-bold text-muted mb-1">Return Destination:</label>
                                        <div class="btn-group btn-group-sm w-100" role="group">
                                            <input type="radio" class="btn-check" name="pmSendBackDest5a" id="pmSendBackDestProc5a" autocomplete="off" checked onchange="switchSendBackTarget5a('proc_team')">
                                            <label class="btn btn-outline-secondary btn-xs py-1" for="pmSendBackDestProc5a">
                                                <i class="fas fa-user-tag me-1"></i> Procurement Team
                                            </label>

                                            <input type="radio" class="btn-check" name="pmSendBackDest5a" id="pmSendBackDestStore5a" autocomplete="off" onchange="switchSendBackTarget5a('store')">
                                            <label class="btn btn-outline-secondary btn-xs py-1" for="pmSendBackDestStore5a">
                                                <i class="fas fa-warehouse me-1"></i> Store Manager
                                            </label>
                                        </div>
                                    </div>

                                    <!-- Destination A: Return to Procurement Team (Direct Buy pricing renegotiation) -->
                                    <form action="{{ route('purchase-requests.send-to-proc-team', $purchaseRequest) }}" method="POST" id="formPmSendBackToProcTeam5a">
                                        @csrf
                                        <input type="hidden" name="sourcing_method" value="direct_buy">
                                        <div class="mb-2">
                                            <label class="form-label small fw-bold text-dark mb-1">Instructions for Purchaser <span class="text-danger">*</span></label>
                                            <textarea name="notes" id="notesProcTeam5a" class="form-control form-control-sm" rows="2" placeholder="e.g., Direct price exceeds current market benchmark. Please renegotiate with vendor or obtain alternative quotes..." required></textarea>
                                        </div>
                                        <button type="submit" class="btn btn-warning text-dark btn-sm w-100 fw-bold shadow-xs">
                                            <i class="fas fa-paper-plane me-1"></i> Return to Procurement Team for Repricing
                                        </button>
                                    </form>

                                    <!-- Destination B: Return to Store Manager (Inventory review / Return to site) -->
                                    <form action="{{ route('purchase-requests.send-back-to-store', $purchaseRequest) }}" method="POST" id="formPmSendBackToStore5a" style="display: none;">
                                        @csrf
                                        <div class="mb-2">
                                            <label class="form-label small fw-bold text-dark mb-1">Reason for Store Manager <span class="text-danger">*</span></label>
                                            <textarea name="reason" id="reasonStore5a" class="form-control form-control-sm" rows="2" placeholder="e.g., Re-check cross-project store stock or request requester review..."></textarea>
                                        </div>
                                        <button type="submit" class="btn btn-danger btn-sm w-100 fw-bold shadow-xs">
                                            <i class="fas fa-undo me-1"></i> Confirm Send Back to Store Manager
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>

                    <!-- STAGE 5b: PM Proforma Selection -->
                    @elseif($purchaseRequest->status === \App\Models\PurchaseRequest::STATUS_PENDING_PROFORMA_SELECTION)
                        <h6 class="font-weight-bold text-primary mb-2">
                            <i class="fas fa-check-double me-1"></i>Select Proforma(s) for GM
                        </h6>
                        <form action="{{ route('purchase-requests.select-proformas', $purchaseRequest) }}" method="POST">
                            @csrf
                            <p class="small text-muted mb-2">Select which supplier quotation(s) to forward to General Manager:</p>
                            <div class="bg-light p-2 rounded border mb-3 small">
                                @forelse($purchaseRequest->proformaInvoices as $prof)
                                    <div class="form-check py-1 border-bottom">
                                        <input class="form-check-input" type="checkbox" name="proforma_ids[]" value="{{ $prof->id }}" id="profSel{{ $prof->id }}" {{ $loop->first ? 'checked' : '' }}>
                                        <label class="form-check-label d-flex justify-content-between align-items-center" for="profSel{{ $prof->id }}">
                                            <span><strong>{{ $prof->supplier->name }}</strong> (#{{ $prof->proforma_no }})</span>
                                            <span class="text-primary fw-bold">{{ number_format($prof->grand_total, 2) }} ETB</span>
                                        </label>
                                    </div>
                                @empty
                                    <div class="text-muted italic">No attached proformas found.</div>
                                @endforelse
                            </div>
                            <div class="mb-3">
                                <label class="form-label small fw-bold text-uppercase">PM Notes for GM</label>
                                <textarea name="notes" class="form-control form-control-sm" rows="2" placeholder="Remarks or recommendation for GM..."></textarea>
                            </div>
                            <button class="btn btn-primary btn-sm w-100 fw-bold" {{ $purchaseRequest->proformaInvoices->count() === 0 ? 'disabled' : '' }}>
                                <i class="fas fa-paper-plane me-1"></i> Send Selected Proformas to GM
                            </button>
                        </form>

                        {{-- STAGE 5b: Send Back / Revision Options --}}
                        <div class="pt-2 mt-3 border-top">
                            <button type="button" class="btn btn-outline-danger btn-sm w-100 fw-semibold" data-bs-toggle="collapse" data-bs-target="#pmProformaSendBackCollapse" aria-expanded="false" aria-controls="pmProformaSendBackCollapse">
                                <i class="fas fa-undo me-1"></i> Send Back / Return Options
                            </button>
                            <div class="collapse mt-2" id="pmProformaSendBackCollapse">
                                <div class="card card-body bg-light border-danger border-opacity-25 p-3 rounded shadow-xs">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <span class="small fw-bold text-danger">
                                            <i class="fas fa-rotate-left me-1"></i> Return Purchase Request
                                        </span>
                                        <span class="badge bg-danger-subtle text-danger border border-danger-subtle small px-2">Revision</span>
                                    </div>
                                    <p class="text-muted small mb-2" style="font-size: 11px;">
                                        If attached proformas are insufficient, uncompetitive, or need changes:
                                    </p>

                                    <!-- Destination Selection -->
                                    <div class="mb-2">
                                        <label class="form-label small fw-bold text-muted mb-1">Return Destination:</label>
                                        <div class="btn-group btn-group-sm w-100" role="group">
                                            <input type="radio" class="btn-check" name="pmSendBackDest5b" id="pmSendBackDestProc5b" autocomplete="off" checked onchange="switchSendBackTarget5b('proc_team')">
                                            <label class="btn btn-outline-secondary btn-xs py-1" for="pmSendBackDestProc5b">
                                                <i class="fas fa-user-tag me-1"></i> Procurement Team
                                            </label>

                                            <input type="radio" class="btn-check" name="pmSendBackDest5b" id="pmSendBackDestStore5b" autocomplete="off" onchange="switchSendBackTarget5b('store')">
                                            <label class="btn btn-outline-secondary btn-xs py-1" for="pmSendBackDestStore5b">
                                                <i class="fas fa-warehouse me-1"></i> Store Manager
                                            </label>
                                        </div>
                                    </div>

                                    <!-- Destination A: Return to Procurement Team (Proforma sourcing) -->
                                    <form action="{{ route('purchase-requests.send-to-proc-team', $purchaseRequest) }}" method="POST" id="formPmSendBackToProcTeam5b">
                                        @csrf
                                        <input type="hidden" name="sourcing_method" value="proforma">
                                        <div class="mb-2">
                                            <label class="form-label small fw-bold text-dark mb-1">Instructions for Procurement Team <span class="text-danger">*</span></label>
                                            <textarea name="notes" id="notesProcTeam5b" class="form-control form-control-sm" rows="2" placeholder="e.g., Quotations are uncompetitive or incomplete. Please obtain at least 3 competitive quotations..." required></textarea>
                                        </div>
                                        <button type="submit" class="btn btn-warning text-dark btn-sm w-100 fw-bold shadow-xs">
                                            <i class="fas fa-paper-plane me-1"></i> Return to Procurement Team for More Quotes
                                        </button>
                                    </form>

                                    <!-- Destination B: Return to Store Manager -->
                                    <form action="{{ route('purchase-requests.send-back-to-store', $purchaseRequest) }}" method="POST" id="formPmSendBackToStore5b" style="display: none;">
                                        @csrf
                                        <div class="mb-2">
                                            <label class="form-label small fw-bold text-dark mb-1">Reason for Store Manager <span class="text-danger">*</span></label>
                                            <textarea name="reason" id="reasonStore5b" class="form-control form-control-sm" rows="2" placeholder="Reason for returning request to Store Manager..."></textarea>
                                        </div>
                                        <button type="submit" class="btn btn-danger btn-sm w-100 fw-bold shadow-xs">
                                            <i class="fas fa-undo me-1"></i> Confirm Send Back to Store Manager
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>

                    <!-- STAGE 6: GM Decision -->
                    @elseif($purchaseRequest->status === \App\Models\PurchaseRequest::STATUS_PENDING_GM)
                        <div class="mb-3">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <h6 class="fw-bold text-dark mb-0">
                                    <i class="fas fa-gavel text-danger me-1"></i> General Manager Decision
                                </h6>
                                <span class="badge bg-danger-subtle text-danger border border-danger-subtle px-2 py-1 small">
                                    <i class="fas fa-bolt me-1"></i>Action Required
                                </span>
                            </div>
                            <p class="small text-muted mb-0">
                                Select approval type (<strong>Pay to Finance</strong> or <strong>Buy with Credit</strong>), customize per-item payment methods, or send back for revision.
                            </p>
                        </div>

                        {{-- Price Intelligence Panel for GM --}}
                        @php
                            $gmVariance    = $purchaseRequest->marketingVariance;
                            $gmDirectAmt   = (float)($purchaseRequest->direct_buy_amount ?? 0);
                            $gmMarketAmt   = (float)($gmVariance->market_price ?? 0);
                            $gmVarAmt      = (float)($gmVariance->variance_amount ?? ($gmDirectAmt - $gmMarketAmt));
                            $gmVarPct      = (float)($gmVariance->variance_percentage ?? ($gmMarketAmt > 0 ? round(($gmVarAmt / $gmMarketAmt) * 100, 2) : 0));
                            $gmVarNotes    = $gmVariance->variance_notes ?? null;
                            $gmLastPurchase = null;
                            // Try to get last purchase price from pricing benchmarks
                            if(isset($pricingBenchmarks['total_last_purchase']) && $pricingBenchmarks['total_last_purchase'] > 0) {
                                $gmLastPurchase = (float)$pricingBenchmarks['total_last_purchase'];
                            }
                        @endphp
                        @if($gmDirectAmt > 0 || $gmMarketAmt > 0)
                        <div class="card border-0 shadow-sm rounded-3 mb-3" style="border-left: 4px solid #0d6efd !important; border-left-style: solid !important;">
                            <div class="card-header bg-primary bg-opacity-10 border-bottom border-primary border-opacity-25 py-2 px-3">
                                <span class="small fw-bold text-primary">
                                    <i class="fas fa-chart-bar me-1"></i> Price Intelligence — PM Review Summary
                                </span>
                            </div>
                            <div class="card-body p-3">

                                {{-- Direct Amount Submitted --}}
                                <div class="d-flex justify-content-between align-items-center py-1 border-bottom border-light mb-1">
                                    <span class="small text-muted fw-semibold">
                                        <i class="fas fa-file-invoice me-1 text-secondary"></i> Direct Amount Submitted
                                    </span>
                                    <span class="fw-bold text-dark font-monospace small">
                                        {{ number_format($gmDirectAmt, 2) }} ETB
                                    </span>
                                </div>

                                {{-- PM Approved Market Benchmark --}}
                                <div class="d-flex justify-content-between align-items-center py-1 border-bottom border-light mb-1">
                                    <span class="small text-muted fw-semibold">
                                        <i class="fas fa-star text-warning me-1"></i> PM Market Benchmark
                                    </span>
                                    <span class="fw-bold text-primary font-monospace small">
                                        {{ $gmMarketAmt > 0 ? number_format($gmMarketAmt, 2) . ' ETB' : '—' }}
                                    </span>
                                </div>

                                {{-- Last Purchase History --}}
                                @if($gmLastPurchase)
                                <div class="d-flex justify-content-between align-items-center py-1 border-bottom border-light mb-1">
                                    <span class="small text-muted fw-semibold">
                                        <i class="fas fa-receipt text-success me-1"></i> Last Purchase Price
                                    </span>
                                    <span class="fw-bold text-success font-monospace small">
                                        {{ number_format($gmLastPurchase, 2) }} ETB
                                    </span>
                                </div>
                                @endif

                                {{-- Variance --}}
                                @if($gmMarketAmt > 0)
                                @php
                                    $gmOverMarket = $gmDirectAmt > $gmMarketAmt;
                                    $gmVarDisplay = abs($gmVarAmt);
                                    $gmVarColor   = $gmOverMarket ? 'danger' : 'success';
                                    $gmVarIcon    = $gmOverMarket ? 'arrow-up' : 'arrow-down';
                                    $gmVarLabel   = $gmOverMarket ? 'ABOVE market' : 'BELOW market';
                                @endphp
                                <div class="mt-2 p-2 rounded border border-{{ $gmVarColor }} border-opacity-50 bg-{{ $gmVarColor }} bg-opacity-10">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <span class="small fw-bold text-{{ $gmVarColor }}">
                                            <i class="fas fa-{{ $gmVarIcon }} me-1"></i>
                                            {{ number_format($gmVarDisplay, 2) }} ETB ({{ abs($gmVarPct) }}%)
                                            {{ $gmVarLabel }}
                                        </span>
                                        @if(abs($gmVarPct) > 20)
                                        <span class="badge bg-{{ $gmVarColor }} text-white" style="font-size:9px;">HIGH</span>
                                        @endif
                                    </div>
                                </div>
                                @endif

                                {{-- Variance Notes from PM --}}
                                @if($gmVarNotes)
                                <div class="mt-2 p-2 rounded bg-light border" style="font-size: 11px;">
                                    <strong class="text-muted text-uppercase d-block mb-1" style="font-size:10px;">
                                        <i class="fas fa-comment-dots me-1"></i> PM Justification:
                                    </strong>
                                    <span class="text-dark">{{ $gmVarNotes }}</span>
                                </div>
                                @endif

                            </div>
                        </div>
                        @endif

                        <form action="{{ \Illuminate\Support\Facades\Route::has('purchase-requests.gm-decide') ? route('purchase-requests.gm-decide', $purchaseRequest) : url('/purchase-requests/' . $purchaseRequest->id . '/gm-decide') }}" method="POST" id="gmDecisionForm">

                            @csrf
                            <input type="hidden" name="decision" id="gmDecisionHidden" value="pay_and_buy">
                            <input type="hidden" name="payment_method" id="gmPaymentMethodHidden" value="pay_and_buy">
                            <input type="hidden" name="proforma_invoice_id" id="gmSelectedProformaHidden" value="{{ $proformaMatrix['selected_proforma_id'] ?? '' }}">
                            @foreach($purchaseRequest->items as $item)
                                @php
                                    $itemAwardedProf = $proformaMatrix['items'][$item->id]['awarded_proforma_id'] ?? ($proformaMatrix['selected_proforma_id'] ?? '');
                                    $itemAwardedUnitCost = $proformaMatrix['items'][$item->id]['quotes'][$itemAwardedProf]['unit_price'] ?? ($item->estimated_unit_cost ?? 0);
                                @endphp
                                <input type="hidden" name="item_proformas[{{ $item->id }}]" id="gmItemProformaHidden_{{ $item->id }}" value="{{ $itemAwardedProf }}">
                                <input type="hidden" name="item_unit_prices[{{ $item->id }}]" id="gmItemUnitPriceHidden_{{ $item->id }}" value="{{ $itemAwardedUnitCost }}">
                            @endforeach

                            <!-- Stacked Full-Width Approval Options (Fits sidebar perfectly) -->
                            <div class="mb-3">
                                <label class="form-label small fw-bold text-uppercase text-muted d-block mb-2">
                                    Select Approval Decision <span class="text-danger">*</span>
                                </label>
                                <div class="d-flex flex-column gap-2" id="gmApprovalTypeOptions">
                                    <!-- Option 1: Pay to Finance -->
                                    <div class="card border rounded p-2.5 cursor-pointer shadow-xs mb-0 transition-all gm-select-card" 
                                         id="gmCardPayFinance" onclick="selectGmMainDecision('pay_and_buy')"
                                         style="cursor: pointer; border-color: #198754 !important; background-color: rgba(25, 135, 84, 0.06); border-width: 2px;">
                                        <div class="d-flex align-items-center justify-content-between">
                                            <div class="d-flex align-items-center gap-2 min-w-0">
                                                <input class="form-check-input mt-0 gm-decision-radio flex-shrink-0" type="radio" name="gm_mode_selector" id="gmRadioPayFinance" value="pay_and_buy" checked>
                                                <div class="min-w-0">
                                                    <div class="fw-bold text-dark small text-truncate">
                                                        <i class="fas fa-money-bill-wave text-success me-1"></i> Pay to Finance
                                                    </div>
                                                    <div class="text-muted text-truncate" style="font-size: 11px;">Cash / Bank Disbursement</div>
                                                </div>
                                            </div>
                                            <span class="badge bg-success text-white flex-shrink-0 ms-2" style="font-size: 10px;">Finance</span>
                                        </div>
                                    </div>

                                    <!-- Option 2: Buy with Credit -->
                                    <div class="card border rounded p-2.5 cursor-pointer shadow-xs mb-0 transition-all gm-select-card" 
                                         id="gmCardBuyCredit" onclick="selectGmMainDecision('buy_by_credit')"
                                         style="cursor: pointer; border-color: #dee2e6; background-color: #ffffff; border-width: 1px;">
                                        <div class="d-flex align-items-center justify-content-between">
                                            <div class="d-flex align-items-center gap-2 min-w-0">
                                                <input class="form-check-input mt-0 gm-decision-radio flex-shrink-0" type="radio" name="gm_mode_selector" id="gmRadioBuyCredit" value="buy_by_credit">
                                                <div class="min-w-0">
                                                    <div class="fw-bold text-dark small text-truncate">
                                                        <i class="fas fa-credit-card text-primary me-1"></i> Buy with Credit
                                                    </div>
                                                    <div class="text-muted text-truncate" style="font-size: 11px;">Supplier Credit (COA 5110)</div>
                                                </div>
                                            </div>
                                            <span class="badge bg-primary text-white flex-shrink-0 ms-2" style="font-size: 10px;">COA 5110</span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Granular Per-Item Payment Method Allocation (Accordion/Collapsible list) -->
                            @if($purchaseRequest->items && $purchaseRequest->items->count() > 0)
                            <div class="mb-3">
                                <button type="button" class="btn btn-outline-secondary btn-sm w-100 d-flex justify-content-between align-items-center py-2 px-2.5 shadow-2xs" 
                                        data-bs-toggle="collapse" data-bs-target="#gmItemCustomizationCollapse" aria-expanded="false" id="btnToggleItemAlloc">
                                    <span class="small fw-semibold text-dark text-truncate">
                                        <i class="fas fa-sliders text-secondary me-1"></i> Customize Payment per Item
                                    </span>
                                    <span class="badge bg-light text-dark border ms-1 flex-shrink-0" id="gmSplitStatusBadge" style="font-size: 10px;">All Finance</span>
                                </button>

                                <div class="collapse mt-2" id="gmItemCustomizationCollapse">
                                    <div class="border rounded p-2 bg-light shadow-xs">
                                        <div class="d-flex justify-content-between align-items-center mb-2 pb-1 border-bottom">
                                            <span class="text-muted fw-bold text-uppercase" style="font-size: 10px;">Quick Bulk Set:</span>
                                            <div class="btn-group btn-group-sm">
                                                <button type="button" class="btn btn-outline-success btn-xs py-0 px-2 fw-semibold" style="font-size: 10px;" onclick="setAllItemPaymentMethods('pay_and_buy')">All Finance</button>
                                                <button type="button" class="btn btn-outline-primary btn-xs py-0 px-2 fw-semibold" style="font-size: 10px;" onclick="setAllItemPaymentMethods('buy_by_credit')">All Credit</button>
                                            </div>
                                        </div>

                                        <!-- Stacked Card List (Zero horizontal scrollbar!) -->
                                        <div class="d-flex flex-column gap-2" style="max-height: 230px; overflow-y: auto;">
                                            @foreach($purchaseRequest->items as $iIdx => $itm)
                                            @php
                                                $lineTotal = (float)$itm->quantity * (float)($itm->estimated_unit_cost ?? $itm->estimated_unit_price ?? $itm->unit_price ?? 0);
                                                $currentMethod = $itm->payment_method ?? 'pay_and_buy';
                                            @endphp
                                            <div class="bg-white p-2 rounded border gm-item-allocation-row shadow-2xs" data-item-id="{{ $itm->id }}" data-amount="{{ $lineTotal }}">
                                                <div class="d-flex justify-content-between align-items-start mb-1">
                                                    <div class="fw-bold text-dark small text-truncate pe-1" style="max-width: 170px;" title="{{ $itm->item_name ?? $itm->product->name }}">
                                                        {{ $itm->item_name ?? $itm->product->name ?? 'Item #' . ($iIdx + 1) }}
                                                    </div>
                                                    <div class="text-end flex-shrink-0 font-monospace small fw-bold text-dark" style="font-size: 11px;">
                                                        {{ number_format($lineTotal, 2) }} ETB
                                                    </div>
                                                </div>
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <span class="text-muted" style="font-size: 10.5px;">{{ number_format($itm->quantity, 2) }} {{ $itm->unit ?? 'Unit' }}</span>
                                                    
                                                    <div class="btn-group btn-group-sm" role="group">
                                                        <input type="radio" class="btn-check gm-item-radio" 
                                                               name="item_payment_methods[{{ $itm->id }}]" 
                                                               id="item_pay_{{ $itm->id }}" 
                                                               value="pay_and_buy" 
                                                               autocomplete="off" 
                                                               {{ $currentMethod !== 'buy_by_credit' ? 'checked' : '' }}
                                                               onchange="onItemPaymentMethodChanged()">
                                                        <label class="btn btn-outline-success btn-xs py-0 px-2 fw-semibold" for="item_pay_{{ $itm->id }}" style="font-size: 10px;">
                                                            Finance
                                                        </label>

                                                        <input type="radio" class="btn-check gm-item-radio" 
                                                               name="item_payment_methods[{{ $itm->id }}]" 
                                                               id="item_credit_{{ $itm->id }}" 
                                                               value="buy_by_credit" 
                                                               autocomplete="off" 
                                                               {{ $currentMethod === 'buy_by_credit' ? 'checked' : '' }}
                                                               onchange="onItemPaymentMethodChanged()">
                                                        <label class="btn btn-outline-primary btn-xs py-0 px-2 fw-semibold" for="item_credit_{{ $itm->id }}" style="font-size: 10px;">
                                                            Credit
                                                        </label>
                                                    </div>
                                                </div>
                                            </div>
                                            @endforeach
                                        </div>

                                        <!-- Live Total Breakdown -->
                                        <div class="d-flex justify-content-between align-items-center pt-2 mt-2 border-top small" style="font-size: 10.5px;">
                                            <div>Finance: <strong class="text-success font-monospace" id="gmFinancePortionBadge">0.00 ETB</strong></div>
                                            <div>Credit: <strong class="text-primary font-monospace" id="gmCreditPortionBadge">0.00 ETB</strong></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            @endif

                            @if($purchaseRequest->proformaInvoices->count() > 0)
                            <div class="mb-3" id="gmProformaSelectionCard">
                                <label class="form-label small fw-bold text-uppercase text-dark d-flex justify-content-between align-items-center mb-1">
                                    <span><i class="fas fa-file-invoice-dollar text-primary me-1"></i> Winning Proforma / Quote <span class="text-danger">*</span></span>
                                    <a href="#gmBidsMatrixCard" class="btn btn-outline-primary btn-xs text-nowrap py-0 px-1.5" style="font-size: 10px;" title="Jump to Comparative Bids Matrix">
                                        <i class="fas fa-table-cells me-1"></i> Bids Matrix
                                    </a>
                                </label>
                                <p class="text-muted small mb-2" style="font-size: 11px;">
                                    Award <strong>all materials</strong> to one supplier quote below, or award <strong>per material item</strong> in the Bids Matrix:
                                </p>
                                <div class="list-group list-group-flush border rounded overflow-hidden shadow-xs">
                                    @php
                                        $minGrandTotal = $purchaseRequest->proformaInvoices->min('grand_total');
                                    @endphp
                                    @foreach($purchaseRequest->proformaInvoices as $pIdx => $prof)
                                        @php
                                            $isLowest = ((float)$prof->grand_total == (float)$minGrandTotal && (float)$minGrandTotal > 0);
                                            $isProfSelected = $prof->gm_selected || ($pIdx === 0 && !$purchaseRequest->proformaInvoices->where('gm_selected', true)->count());
                                        @endphp
                                        <div class="list-group-item p-2 cursor-pointer gm-prof-item {{ $isProfSelected ? 'bg-light border-primary' : '' }}" 
                                             id="gmProfItemLabel_{{ $prof->id }}"
                                             style="cursor: pointer;" onclick="selectGmProformaForEntirePr({{ $prof->id }})">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <div class="d-flex align-items-center">
                                                    <input class="form-check-input me-2 mt-0 gm-proforma-radio" type="radio" name="sidebar_proforma_radio" value="{{ $prof->id }}" id="gmProfRadio_{{ $prof->id }}" {{ $isProfSelected ? 'checked' : '' }}>
                                                    <div>
                                                        <div class="fw-bold small text-dark">{{ $prof->supplier->name ?? ($prof->supplier_name ?: 'Vendor #' . $prof->supplier_id) }}</div>
                                                        <div class="text-muted font-monospace" style="font-size: 11px;">
                                                            <code>{{ $prof->proforma_no }}</code>
                                                            @if($isLowest)
                                                                <span class="badge bg-success-subtle text-success border border-success-subtle ms-1" style="font-size: 9px;"><i class="fas fa-arrow-down me-1"></i>Lowest</span>
                                                            @endif
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="text-end">
                                                    <div class="fw-bold text-primary small">{{ number_format($prof->grand_total, 2) }} ETB</div>
                                                    @if(!empty($prof->item_prices))
                                                    <a class="text-muted text-decoration-none small" style="font-size: 10px;" data-bs-toggle="collapse" href="#sidebarProfItems_{{ $prof->id }}" role="button" onclick="event.stopPropagation()">
                                                        <i class="fas fa-chevron-down text-primary me-1"></i>{{ count($prof->item_prices) }} items
                                                    </a>
                                                    @else
                                                    <div class="text-muted" style="font-size: 10px;">{{ count($prof->item_prices ?? []) }} items</div>
                                                    @endif
                                                </div>
                                            </div>

                                            @if(!empty($prof->item_prices))
                                            <div class="collapse mt-2 pt-2 border-top" id="sidebarProfItems_{{ $prof->id }}" onclick="event.stopPropagation()">
                                                <div class="table-responsive">
                                                    <table class="table table-sm table-borderless align-middle mb-0 text-muted" style="font-size: 10.5px;">
                                                        <tbody>
                                                            @foreach($prof->item_prices as $pip)
                                                            <tr class="border-bottom border-light">
                                                                <td class="text-dark p-0.5">{{ $pip['product_name'] ?? 'Item' }}</td>
                                                                <td class="text-end text-dark fw-bold p-0.5">{{ number_format($pip['unit_price'] ?? 0, 2) }} ETB</td>
                                                            </tr>
                                                            @endforeach
                                                        </tbody>
                                                    </table>
                                                </div>
                                            </div>
                                            @endif
                                        </div>
                                    @endforeach
                                </div>

                                <div id="gmPerItemAwardNotice" class="alert alert-info py-1 px-2 mt-2 mb-0 small d-none">
                                    <i class="fas fa-layer-group text-primary me-1"></i>
                                    <span id="gmPerItemAwardNoticeText">Custom Per-Material Selection Active</span>
                                </div>

                                <div class="d-flex justify-content-between align-items-center mt-2 p-2 bg-light rounded border">
                                    <span class="small fw-semibold text-muted text-uppercase" style="font-size: 10.5px;">Awarded Total:</span>
                                    <strong class="text-primary fs-6" id="gmDynamicGrandTotalDisplay">
                                        {{ number_format($purchaseRequest->direct_buy_amount ?: ($purchaseRequest->proformaInvoices->first()->grand_total ?? 0), 2) }} ETB
                                    </strong>
                                </div>
                            </div>
                            @endif

                            {{-- Dynamic Explanatory Alerts --}}
                            <div id="gmDecisionHelpPayBuy" class="alert alert-success py-2 px-3 small border-0 shadow-xs mb-3">
                                <div class="d-flex">
                                    <i class="fas fa-money-bill-wave fa-lg me-2 mt-1 text-success"></i>
                                    <div>
                                        <strong>Approve (Send to Pay to Finance):</strong>
                                        <div class="text-muted" style="font-size: 11px;">Routes to <strong>Finance Head</strong> for funding account selection and assigning staff to execute payment disbursement.</div>
                                    </div>
                                </div>
                            </div>

                            <div id="gmDecisionHelpCredit" class="alert alert-info py-2 px-3 small border-0 shadow-xs mb-3" style="display: none;">
                                <div class="d-flex">
                                    <i class="fas fa-credit-card fa-lg me-2 mt-1 text-info"></i>
                                    <div>
                                        <strong>Approve (Buy with Credit):</strong>
                                        <div class="text-muted" style="font-size: 11px;">Auto-books to <strong>COA 5110 (Cost Of Material By Credit)</strong> &amp; <strong>Credit Store Ledger</strong>, then routes directly to <strong>Store Keeper</strong> for material intake.</div>
                                    </div>
                                </div>
                            </div>

                            <div id="gmDecisionHelpSplit" class="alert alert-dark py-2 px-3 small border-0 shadow-xs mb-3" style="display: none;">
                                <div class="d-flex">
                                    <i class="fas fa-layer-group fa-lg me-2 mt-1 text-warning"></i>
                                    <div>
                                        <strong>Approve (Split Allocation):</strong>
                                        <div class="text-muted" style="font-size: 11px;">Credit portion will be booked to <strong>Credit Store Ledger</strong>, and Finance portion will be sent to <strong>Finance</strong> for payment disbursement.</div>
                                    </div>
                                </div>
                            </div>

                            <div id="gmDecisionHelpSendBack" class="alert alert-warning py-2 px-3 small border-0 shadow-xs mb-3" style="display: none;">
                                <div class="d-flex">
                                    <i class="fas fa-undo fa-lg me-2 mt-1 text-warning"></i>
                                    <div>
                                        <strong>Send Back to PM:</strong>
                                        <div class="text-muted" style="font-size: 11px;">Returns the PR to the Procurement Manager for renegotiating supplier quotes or updating material pricing.</div>
                                    </div>
                                </div>
                            </div>

                            <div id="gmDecisionHelpReject" class="alert alert-danger py-2 px-3 small border-0 shadow-xs mb-3" style="display: none;">
                                <div class="d-flex">
                                    <i class="fas fa-ban fa-lg me-2 mt-1 text-danger"></i>
                                    <div>
                                        <strong>Reject Purchase Request:</strong>
                                        <div class="text-muted" style="font-size: 11px;">Closes this Purchase Request and notifies the procurement team of the rejection.</div>
                                    </div>
                                </div>
                            </div>

                            <!-- Secondary Decisions: Revision & Rejection -->
                            <div class="d-flex justify-content-between align-items-center gap-2 mb-3 pt-1">
                                <button type="button" class="btn btn-outline-warning text-dark btn-xs py-1 px-2 w-50 fw-semibold" id="btnGmSecondarySendBack" onclick="selectGmSecondaryDecision('send_back')">
                                    <i class="fas fa-undo me-1"></i> Send Back to PM
                                </button>
                                <button type="button" class="btn btn-outline-danger btn-xs py-1 px-2 w-50 fw-semibold" id="btnGmSecondaryReject" onclick="selectGmSecondaryDecision('reject')">
                                    <i class="fas fa-ban me-1"></i> Reject PR
                                </button>
                            </div>

                            <div class="mb-3">
                                <label class="form-label small fw-bold text-uppercase text-muted">GM Notes / Instructions / Reason</label>
                                <textarea name="notes" id="gmNotes" class="form-control form-control-sm" rows="2" placeholder="Add specific approval conditions, credit terms, or remarks..."></textarea>
                            </div>

                            <button type="submit" id="btnSubmitGmDecision" class="btn btn-success btn-sm w-100 fw-bold shadow-sm py-2">
                                <i class="fas fa-paper-plane me-1"></i> Approve & Send to Finance (Pay & Buy)
                            </button>
                        </form>

                    <!-- STAGE 7a: Finance Credit Authorization (Auto-COA 5110) -->
                    @elseif($purchaseRequest->status === \App\Models\PurchaseRequest::STATUS_PENDING_FINANCE)
                        <form action="{{ route('purchase-requests.finance-credit-approve', $purchaseRequest) }}" method="POST">
                            @csrf
                            <div class="alert alert-info py-2 px-3 small mb-3 border-start border-4 border-info">
                                <div class="fw-bold mb-1"><i class="fas fa-credit-card me-1"></i> Credit Purchase (COA 5110)</div>
                                <div class="text-muted">Account: <strong class="text-dark">Cost Of Material By Credit 5110</strong></div>
                                <div class="text-muted mt-1">This request will be routed directly to the <strong>Store Keeper</strong> for material intake and tracked in the <strong>Credit Store Ledger</strong>.</div>
                            </div>

                            @php
                                $creditVal = (float)($purchaseRequest->direct_buy_amount ?? 0);
                                if ($creditVal <= 0) {
                                    $selProf = $purchaseRequest->proformaInvoices()->where('gm_selected', true)->first() ?? $purchaseRequest->proformaInvoices()->latest()->first();
                                    $creditVal = $selProf && (float)$selProf->grand_total > 0 ? (float)$selProf->grand_total : (float)$purchaseRequest->items->sum(fn($i) => (float)$i->quantity * (float)($i->estimated_unit_cost ?? $i->estimated_unit_price ?? $i->unit_price ?? 0));
                                }
                            @endphp

                            <div class="mb-3">
                                <label class="form-label small fw-bold text-uppercase text-muted">Credit Amount (ETB)</label>
                                <input type="number" step="0.01" name="amount" class="form-control form-control-sm fw-bold bg-light" value="{{ number_format($creditVal, 2, '.', '') }}" readonly>
                            </div>

                            <div class="mb-3">
                                <label class="form-label small fw-bold text-uppercase text-muted">Notes / Credit Terms</label>
                                <textarea name="notes" class="form-control form-control-sm" rows="2" placeholder="Optional notes..."></textarea>
                            </div>

                            <button class="btn btn-info text-white btn-sm w-100 fw-bold shadow-sm py-2">
                                <i class="fas fa-check-circle me-1"></i> Authorize & Send to Store Manager
                            </button>
                        </form>

                    <!-- STAGE 7b: Finance Head Payment Assignment -->
                    @elseif($purchaseRequest->status === \App\Models\PurchaseRequest::STATUS_PENDING_PAYMENT && !$purchaseRequest->payment->assigned_finance_staff_id)
                        <div class="mb-3">
                            <h6 class="fw-bold text-dark mb-1">
                                <i class="fas fa-wallet text-primary me-1"></i> Finance Payment Assignment
                            </h6>
                            <p class="small text-muted mb-0">Select funding account, verify amount, and assign finance staff to disburse funds.</p>
                        </div>
                        <form action="{{ route('purchase-requests.assign-payment', $purchaseRequest) }}" method="POST">
                            @csrf
                            <div class="mb-2">
                                <label class="form-label small fw-bold text-uppercase text-muted">Funding Account (COA) <span class="text-danger">*</span></label>
                                <select name="coa_account_id" id="fundingCoaSelect" class="form-select form-select-sm" required onchange="handleCoaSelectionChange(this)">
                                    <option value="">-- Select Funding COA --</option>
                                    @foreach($coaAccounts as $coa)
                                        <option value="{{ $coa->id }}" data-assigned-user="{{ $coa->assigned_to ?? '' }}" data-balance="{{ (float)$coa->current_balance }}">
                                            {{ $coa->code }} - {{ $coa->name }} (Bal: {{ number_format($coa->current_balance, 2) }} ETB) {{ $coa->manager ? ' — Assigned: ' . $coa->manager->name : '' }}
                                        </option>
                                    @endforeach
                                </select>
                                <div id="coaAutoAssignedBadge" class="form-text text-success small mt-1 d-none"></div>
                            </div>
                            <div class="mb-2">
                                <label class="form-label small fw-bold text-uppercase text-muted">Payment Amount (ETB) <span class="text-danger">*</span></label>
                                <input type="number" step="0.01" name="amount" id="fundingPaymentAmountInput" class="form-control form-control-sm fw-bold" value="{{ (float)($purchaseRequest->payment->amount ?? $purchaseRequest->direct_buy_amount ?? 0) }}" required>
                            </div>
                            <input type="hidden" name="staff_user_id" id="assignFinanceStaffHidden" value="">
                            <div class="mb-3">
                                <label class="form-label small fw-bold text-uppercase text-muted">Payment Instructions / Notes</label>
                                <textarea name="notes" class="form-control form-control-sm" rows="2" placeholder="Cheque number, transfer instructions, or remarks..."></textarea>
                            </div>
                            <button class="btn btn-primary btn-sm w-100 fw-bold py-2 shadow-sm">
                                <i class="fas fa-user-check me-1"></i> Assign Payment & Log Expense
                            </button>
                        </form>

                        <div class="mt-3 pt-3 border-top">
                            <button type="button" class="btn btn-outline-warning text-dark btn-sm w-100 fw-bold shadow-sm" data-bs-toggle="modal" data-bs-target="#financeSendBackGmModal">
                                <i class="fas fa-undo me-1"></i> Send Back to GM
                            </button>
                        </div>

                    <!-- STAGE 7b: Finance Staff Execute Payment -->
                    @elseif($purchaseRequest->status === \App\Models\PurchaseRequest::STATUS_PENDING_PAYMENT && $purchaseRequest->payment->assigned_finance_staff_id)
                        <div class="mb-3">
                            <h6 class="fw-bold text-success mb-1">
                                <i class="fas fa-hand-holding-dollar text-success me-1"></i> Payment In Expense Hub
                            </h6>
                            <p class="small text-muted mb-0">Assigned to finance staff to disburse via <strong>Expense Track & Approve</strong>.</p>
                        </div>

                        <div class="alert alert-info py-2 px-3 small mb-3 border-start border-4 border-info">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <span class="text-muted">Payment Amount:</span>
                                <strong class="text-primary fs-6">{{ number_format($purchaseRequest->payment->amount, 2) }} ETB</strong>
                            </div>
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <span class="text-muted">Funding Account:</span>
                                <strong class="text-dark">{{ $purchaseRequest->payment->coaAccount->name }} ({{ $purchaseRequest->payment->coaAccount->code }})</strong>
                            </div>
                            @if($purchaseRequest->payment->assignedStaff)
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="text-muted">Assigned Person:</span>
                                <strong class="text-dark">{{ $purchaseRequest->payment->assignedStaff->name }}</strong>
                            </div>
                            @endif
                        </div>

                        <a href="{{ url('/expenses?tab=finance_queue&search=' . urlencode($purchaseRequest->pr_no)) }}" class="btn btn-primary btn-sm w-100 fw-bold py-2 shadow-sm mb-2">
                            <i class="fa-solid fa-file-invoice-dollar me-1"></i> Open in Expense Track & Approve
                        </a>

                        @php
                            $user = auth()->user();
                            $canPayHere = $user && (
                                (int)$purchaseRequest->payment->assigned_finance_staff_id === (int)$user->id ||
                                $user->hasAnyRole(['admin', 'global_admin', 'Finance head', 'finance_head', 'finance_manager'])
                            );
                        @endphp

                        @if($canPayHere)
                        <div class="mt-2 pt-2 border-top text-center">
                            <button type="button" class="btn btn-link btn-sm text-muted text-decoration-none p-0" data-bs-toggle="collapse" data-bs-target="#quickPayCollapse">
                                <small><i class="fas fa-bolt me-1"></i> Quick Pay from this page <i class="fas fa-chevron-down ms-1"></i></small>
                            </button>
                            <div class="collapse mt-2 text-start" id="quickPayCollapse">
                                <form action="{{ route('purchase-requests.execute-payment', $purchaseRequest) }}" method="POST">
                                    @csrf
                                    <div class="mb-2">
                                        <label class="form-label small fw-bold text-uppercase text-muted">Bank Transaction No. / Cheque Reference No. <span class="text-danger">*</span></label>
                                        <input type="text" name="transaction_reference" class="form-control form-control-sm" placeholder="e.g. TXN-10928374 or CHQ-004921" required>
                                    </div>
                                    <div class="mb-2">
                                        <label class="form-label small fw-bold text-uppercase text-muted">Remarks / Notes (Optional)</label>
                                        <textarea name="notes" class="form-control form-control-sm" rows="2" placeholder="Optional notes, comments..."></textarea>
                                    </div>
                                    <button class="btn btn-success btn-sm w-100 fw-bold py-2 shadow-sm">
                                        <i class="fas fa-check-double me-1"></i> Confirm & Execute Payment
                                    </button>
                                </form>
                            </div>
                        </div>
                        @endif

                    <!-- STAGE 8: Upload Receipt (Procurement Team) -->
                    @elseif($purchaseRequest->status === \App\Models\PurchaseRequest::STATUS_PENDING_RECEIPT_UPLOAD)
                        <div class="alert alert-success border-2 border-success bg-white shadow-sm p-3 rounded-3 mb-3">
                            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                                <div>
                                    <h6 class="fw-bold text-success mb-1">
                                        <i class="fas fa-forward me-1"></i> Jump Directly to Next Stage (Store Material Intake)
                                    </h6>
                                    <p class="small text-muted mb-0">
                                        Payment of <strong>{{ number_format($purchaseRequest->payment->amount ?? $purchaseRequest->direct_buy_amount, 2) }} ETB</strong> disbursed. Skip receipt upload and move directly to <strong>Store Keeper Material Intake</strong>.
                                    </p>
                                </div>
                                <form action="{{ route('purchase-requests.skip-receipt-to-store', $purchaseRequest) }}" method="POST" class="d-inline">
                                    @csrf
                                    <button type="submit" class="btn btn-success fw-bold px-3 py-2 shadow-sm text-nowrap" onclick="return confirm('Skip receipt upload and advance directly to Store Keeper Material Intake?');">
                                        <i class="fas fa-boxes-packing me-1"></i> Skip &amp; Jump to Store Intake &rarr;
                                    </button>
                                </form>
                            </div>
                        </div>

                        <div class="card border rounded-3 mb-3">
                            <div class="card-header bg-light py-2 d-flex justify-content-between align-items-center" role="button" data-bs-toggle="collapse" data-bs-target="#optionalReceiptUploadCollapse" aria-expanded="true" style="cursor: pointer;">
                                <span class="small fw-bold text-secondary">
                                    <i class="fas fa-file-invoice me-1"></i> Optional: Upload Vendor Receipt (If Available)
                                </span>
                                <span class="badge bg-secondary bg-opacity-25 text-secondary small"><i class="fas fa-chevron-down me-1"></i>Toggle Form</span>
                            </div>
                            <div class="collapse show" id="optionalReceiptUploadCollapse">
                                <div class="card-body p-3">
                                    <form action="{{ route('purchase-requests.upload-receipt', $purchaseRequest) }}" method="POST" enctype="multipart/form-data" id="prReceiptUploadForm">
                                        @csrf
                                        <div class="mb-2">
                                            <label class="form-label small fw-bold text-uppercase text-muted">Official Vendor Receipt (PDF / Image) <span class="text-danger">*</span></label>
                                            <input type="file" name="receipt_file" id="prReceiptFileInput" class="form-control form-control-sm" accept=".pdf,.jpg,.jpeg,.png,.webp" required>
                                        </div>

                            {{-- Dynamic OCR Feedback & Duplicate Alert Area --}}
                            <div id="prOcrFeedbackArea"></div>

                            {{-- OCR Extracted & Editable Fields --}}
                            <div class="p-2.5 rounded-3 border bg-light mb-3">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <span class="small fw-bold text-uppercase text-muted" style="font-size:0.72rem;">
                                        <i class="fas fa-file-invoice text-primary me-1"></i> Receipt Fiscal Details
                                    </span>
                                    <span class="badge bg-white text-secondary border small" style="font-size:0.68rem;">Auto-prefilled by OCR</span>
                                </div>
                                <div class="row g-2 mb-2">
                                    <div class="col-md-7">
                                        <label class="form-label small text-muted mb-1" style="font-size:0.72rem;">Vendor / Supplier Name</label>
                                        <input type="text" name="vendor_name" id="prVendorName" class="form-control form-control-sm bg-white" placeholder="e.g. Astra General Trading">
                                    </div>
                                    <div class="col-md-5">
                                        <label class="form-label small text-muted mb-1" style="font-size:0.72rem;">Supplier TIN (10 digits)</label>
                                        <input type="text" name="supplier_tin" id="prSupplierTin" maxlength="10" class="form-control form-control-sm bg-white font-monospace" placeholder="00xxxxxxxx">
                                    </div>
                                </div>
                                <div class="row g-2 mb-2">
                                    <div class="col-md-4">
                                        <label class="form-label small text-muted mb-1" style="font-size:0.72rem;">FS Receipt # (8 digits)</label>
                                        <input type="text" name="fs_no" id="prFsNo" maxlength="8" class="form-control form-control-sm bg-white font-monospace" placeholder="00xxxxxx">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label small text-muted mb-1" style="font-size:0.72rem;">Receipt Date</label>
                                        <input type="date" name="receipt_date" id="prReceiptDate" class="form-control form-control-sm bg-white" value="{{ date('Y-m-d') }}">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label small text-muted mb-1" style="font-size:0.72rem;">Total Amount (ETB)</label>
                                        <input type="number" step="0.01" name="total_amount" id="prTotalAmount" class="form-control form-control-sm bg-white fw-bold text-success font-monospace" placeholder="0.00">
                                    </div>
                                </div>
                                <div class="row g-2 mb-2">
                                    <div class="col-md-6">
                                        <label class="form-label small text-muted mb-1" style="font-size:0.72rem;">Subtotal / Taxable (ETB)</label>
                                        <input type="number" step="0.01" name="subtotal" id="prSubtotal" class="form-control form-control-sm bg-white font-monospace" placeholder="0.00">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label small text-muted mb-1" style="font-size:0.72rem;">15% VAT (ETB)</label>
                                        <input type="number" step="0.01" name="vat_amount" id="prVatAmount" class="form-control form-control-sm bg-white font-monospace" placeholder="0.00">
                                    </div>
                                </div>
                                <div class="mb-0">
                                    <label class="form-label small text-muted mb-1" style="font-size:0.72rem;">Extracted Materials / Line Items</label>
                                    <input type="text" name="material_description" id="prDescription" class="form-control form-control-sm bg-white" placeholder="Extracted item list...">
                                    <input type="hidden" name="items_json" id="prItemsJson">
                                </div>
                            </div>

                            {{-- Withholding Receipt (Optional / If Available) --}}
                            @php
                                $paymentHasWithholding = (bool)($purchaseRequest->payment->has_withholding || (float)($purchaseRequest->payment->withholding_amount ?? 0) > 0);
                                $defaultWithholdingRate = $purchaseRequest->payment->withholding_rate ?? 2.00;
                                $defaultWithholdingAmount = $purchaseRequest->payment->withholding_amount ?? 0.00;
                                $existingWithholdingReceipt = $purchaseRequest->payment->withholding_receipt ?? $purchaseRequest->receipt->withholding_receipt_path;
                                $existingWithholdingNo = $purchaseRequest->payment->withholding_receipt_number ?? $purchaseRequest->receipt->withholding_receipt_number;
                            @endphp
                            <div class="p-2.5 rounded-3 border bg-warning bg-opacity-10 mb-3 border-warning border-opacity-25">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <span class="small fw-bold text-uppercase text-dark" style="font-size:0.75rem;">
                                        <i class="fa-solid fa-file-shield text-warning me-1"></i> Withholding Tax Receipt (If Available)
                                    </span>
                                    @if($paymentHasWithholding)
                                        <span class="badge bg-warning text-dark border border-warning" style="font-size:0.68rem;">
                                            Withholding Recorded: {{ number_format($defaultWithholdingAmount, 2) }} ETB ({{ $defaultWithholdingRate }}%)
                                        </span>
                                    @else
                                        <span class="badge bg-white text-muted border" style="font-size:0.68rem;">Optional / If applicable</span>
                                    @endif
                                </div>

                                <div class="mb-2">
                                    <label class="form-label small text-muted mb-1" style="font-size:0.72rem;">
                                        Upload Withholding Receipt (PDF / Image)
                                    </label>
                                    <input type="file" name="withholding_receipt" id="prWithholdingReceiptInput" class="form-control form-control-sm bg-white" accept=".pdf,.jpg,.jpeg,.png,.webp">
                                    @if($existingWithholdingReceipt)
                                        <div class="mt-1">
                                            <a href="{{ \App\Services\FileUploadService::url($existingWithholdingReceipt) }}" target="_blank" class="small text-primary text-decoration-none">
                                                <i class="fa-solid fa-paperclip me-1"></i> Current Withholding Receipt Attached
                                            </a>
                                        </div>
                                    @endif
                                </div>

                                <div class="row g-2">
                                    <div class="col-md-5">
                                        <label class="form-label small text-muted mb-1" style="font-size:0.72rem;">Withholding Receipt / Voucher #</label>
                                        <input type="text" name="withholding_receipt_number" id="prWithholdingReceiptNumber" class="form-control form-control-sm bg-white font-monospace" placeholder="e.g. WH-008123" value="{{ old('withholding_receipt_number', $existingWithholdingNo) }}">
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label small text-muted mb-1" style="font-size:0.72rem;">Rate (%)</label>
                                        <input type="number" step="0.01" name="withholding_rate" id="prWithholdingRate" class="form-control form-control-sm bg-white font-monospace" value="{{ old('withholding_rate', $defaultWithholdingRate) }}">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label small text-muted mb-1" style="font-size:0.72rem;">Withholding Amount (ETB)</label>
                                        <input type="number" step="0.01" name="withholding_amount" id="prWithholdingAmount" class="form-control form-control-sm bg-white font-monospace fw-semibold text-danger" placeholder="0.00" value="{{ old('withholding_amount', $defaultWithholdingAmount > 0 ? $defaultWithholdingAmount : '') }}">
                                    </div>
                                </div>
                                <div class="form-text text-muted mt-1" style="font-size:0.68rem;">
                                    <i class="fas fa-info-circle me-1"></i> Attach official ERCA withholding tax slip if withholding was deducted from the vendor's payment.
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label small fw-bold text-uppercase text-muted">Receipt Notes / Reference</label>
                                <textarea name="notes" id="prReceiptNotes" class="form-control form-control-sm" rows="2" placeholder="Cash sales invoice #, receipt date, or purchase notes..."></textarea>
                            </div>
                            <div class="d-flex gap-2">
                                <button type="submit" id="prUploadSubmitBtn" class="btn btn-primary btn-sm flex-grow-1 fw-bold py-2 shadow-sm">
                                    <i class="fas fa-paper-plane me-1"></i> Upload Receipt &amp; Send to Store Keeper
                                </button>
                                <button type="button" class="btn btn-outline-success btn-sm fw-bold px-3 py-2 shadow-sm" onclick="if(confirm('Skip receipt upload and advance directly to Store Keeper Material Intake?')) { document.getElementById('skipReceiptDirectForm').submit(); }">
                                    <i class="fas fa-forward me-1"></i> Skip
                                </button>
                            </div>
                        </form>
                        <form id="skipReceiptDirectForm" action="{{ route('purchase-requests.skip-receipt-to-store', $purchaseRequest) }}" method="POST" class="d-none">
                            @csrf
                        </form>
                                </div>
                            </div>
                        </div>

                        <script src="{{ asset('js/receipt-ocr-scanner.js') }}"></script>
                        <script>
                        document.addEventListener('DOMContentLoaded', function () {
                            if (window.ReceiptOcrScanner) {
                                ReceiptOcrScanner.init({
                                    fileInput: '#prReceiptFileInput',
                                    form: '#prReceiptUploadForm',
                                    submitBtn: '#prUploadSubmitBtn',
                                    statusContainer: '#prOcrFeedbackArea',
                                    fields: {
                                        vendor_name: '#prVendorName',
                                        supplier_tin: '#prSupplierTin',
                                        fs_no: '#prFsNo',
                                        receipt_date: '#prReceiptDate',
                                        total_amount: '#prTotalAmount',
                                        subtotal: '#prSubtotal',
                                        vat_amount: '#prVatAmount',
                                        description: '#prDescription',
                                        notes: '#prReceiptNotes'
                                    },
                                    onItemsExtracted: function (items) {
                                        const hiddenEl = document.getElementById('prItemsJson');
                                        if (hiddenEl) {
                                            hiddenEl.value = JSON.stringify(items);
                                        }
                                        recalculatePrWithholding();
                                    }
                                });
                            }

                            function recalculatePrWithholding() {
                                const totalEl = document.getElementById('prTotalAmount');
                                const subtotalEl = document.getElementById('prSubtotal');
                                const rateEl = document.getElementById('prWithholdingRate');
                                const whtAmtEl = document.getElementById('prWithholdingAmount');

                                if (!rateEl || !whtAmtEl) return;
                                const rate = parseFloat(rateEl.value) || 0;
                                if (rate <= 0) return;

                                const base = parseFloat(subtotalEl?.value) || parseFloat(totalEl?.value) || 0;
                                if (base > 0 && (!whtAmtEl.value || parseFloat(whtAmtEl.value) === 0 || whtAmtEl.dataset.autoCalc === '1')) {
                                    whtAmtEl.value = (base * (rate / 100)).toFixed(2);
                                    whtAmtEl.dataset.autoCalc = '1';
                                }
                            }

                            document.getElementById('prSubtotal')?.addEventListener('input', recalculatePrWithholding);
                            document.getElementById('prTotalAmount')?.addEventListener('input', recalculatePrWithholding);
                            document.getElementById('prWithholdingRate')?.addEventListener('input', function() {
                                const whtAmtEl = document.getElementById('prWithholdingAmount');
                                if (whtAmtEl) whtAmtEl.dataset.autoCalc = '1';
                                recalculatePrWithholding();
                            });
                            document.getElementById('prWithholdingAmount')?.addEventListener('input', function() {
                                this.dataset.autoCalc = '0';
                            });
                        });
                        </script>

                    <!-- STAGE 8: Verify Receipt -->
                    @elseif($purchaseRequest->status === \App\Models\PurchaseRequest::STATUS_PENDING_RECEIPT_VERIFY)
                        <div class="alert alert-success border-2 border-success bg-white shadow-sm p-3 rounded-3 mb-3">
                            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                                <div>
                                    <h6 class="fw-bold text-success mb-1">
                                        <i class="fas fa-forward me-1"></i> Jump Directly to Next Stage (Store Material Intake)
                                    </h6>
                                    <p class="small text-muted mb-0">
                                        Skip waiting for receipt verification and advance directly to <strong>Store Keeper Material Intake</strong>.
                                    </p>
                                </div>
                                <form action="{{ route('purchase-requests.skip-receipt-to-store', $purchaseRequest) }}" method="POST" class="d-inline">
                                    @csrf
                                    <button type="submit" class="btn btn-success fw-bold px-3 py-2 shadow-sm text-nowrap" onclick="return confirm('Skip receipt verification and advance directly to Store Keeper Material Intake?');">
                                        <i class="fas fa-boxes-packing me-1"></i> Skip &amp; Jump to Store Intake &rarr;
                                    </button>
                                </form>
                            </div>
                        </div>
                        <form action="{{ route('purchase-requests.verify-receipt', $purchaseRequest) }}" method="POST">
                            @csrf
                            <div class="mb-2">
                                <label class="form-label small font-weight-bold">Verification Decision</label>
                                <select name="verification_status" class="form-select form-select-sm" required>
                                    <option value="verified">Verify & Approve Receipt</option>
                                    <option value="rejected">Reject Receipt</option>
                                </select>
                            </div>
                            <div class="mb-2">
                                <label class="form-label small">Notes</label>
                                <textarea name="verification_notes" class="form-control form-control-sm" rows="2"></textarea>
                            </div>
                            <button class="btn btn-primary btn-sm w-100">Submit Verification</button>
                        </form>

                    <!-- STAGE 9: Book Driver (General Service) -->
                    @elseif($purchaseRequest->status === \App\Models\PurchaseRequest::STATUS_PENDING_DRIVER)
                        <form action="{{ route('purchase-requests.book-driver', $purchaseRequest) }}" method="POST">
                            @csrf
                            <div class="mb-2">
                                <label class="form-label small font-weight-bold">Select Driver (HR Employee Master)</label>
                                <select name="driver_employee_id" class="form-select form-select-sm" required>
                                    <option value="">-- Select Driver --</option>
                                    @foreach($drivers as $d)
                                        <option value="{{ $d->id }}">{{ $d->full_name }} ({{ $d->phone }})</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="mb-2">
                                <label class="form-label small">Vehicle Plate Number</label>
                                <input type="text" name="vehicle_number" class="form-control form-control-sm">
                            </div>
                            <div class="mb-2">
                                <label class="form-label small">Scheduled Delivery Time</label>
                                <input type="datetime-local" name="scheduled_at" class="form-control form-control-sm">
                            </div>
                            <button class="btn btn-info text-white btn-sm w-100"><i class="fas fa-truck me-1"></i> Book Driver</button>
                        </form>

                    <!-- STAGE 9 Final: Store Keeper Final Intake & Stock In -->
                    @elseif($purchaseRequest->status === \App\Models\PurchaseRequest::STATUS_PENDING_STORE_REVIEW && ($isFinalIntake ?? false))
                        <div class="mb-3">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <h6 class="fw-bold text-success mb-0">
                                    <i class="fas fa-boxes-packing text-success me-1"></i> Store Keeper: Receive Products &amp; Stock In
                                </h6>
                                <span class="badge bg-success text-white"><i class="fas fa-user-check me-1"></i>Store Keeper Action</span>
                            </div>
                            <p class="small text-muted mb-0">
                                Verify received quantities, assign the receiving slip (GRN / Model 19), and intake products directly into store inventory.
                            </p>
                        </div>

                        <form action="{{ route('purchase-requests.store-intake', $purchaseRequest) }}" method="POST" onsubmit="const btn = this.querySelector('button[type=submit]'); if (btn) { btn.disabled = true; btn.innerHTML = '<i class=\'fas fa-spinner fa-spin me-1\'></i> Processing Intake...'; }">
                            @csrf

                            <div class="mb-2">
                                <label class="form-label small fw-bold text-uppercase text-muted">Receiving Store <span class="text-danger">*</span></label>
                                <select name="store_id" id="intakeStoreSelect" class="form-select form-select-sm" required onchange="fetchStoreSlipSequence(this.value, true)">
                                    @foreach($stores as $st)
                                        <option value="{{ $st->id }}" {{ (($purchaseRequest->store_id ?? ($userStoreId ?? null)) == $st->id) ? 'selected' : '' }}>
                                             {{ $st->name }} ({{ $st->location ?? 'Store' }})
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="mb-2">
                                <label class="form-label small fw-bold text-uppercase text-muted">Receiving Slip Number (Model 19 / GRN) <span class="text-danger">*</span></label>
                                <div class="input-group input-group-sm">
                                    <span class="input-group-text bg-light"><i class="fas fa-hashtag text-muted"></i></span>
                                    <input type="text" name="slip_no" id="intakeSlipNoInput" 
                                           class="form-control form-control-sm font-monospace fw-bold @error('slip_no') is-invalid @enderror" 
                                           data-start="{{ $receiveSlipSequence->book_start_no ?? '' }}"
                                           data-end="{{ $receiveSlipSequence->book_end_no ?? '' }}"
                                           data-prefix="{{ $receiveSlipSequence->prefix ?? '' }}"
                                           oninput="validateIntakeSlipRange()"
                                           value="{{ old('slip_no', $nextReceiveSlipNo ?? ('REC-' . date('Ymd') . '-' . str_pad($purchaseRequest->id, 4, '0', STR_PAD_LEFT))) }}" required>
                                </div>
                                @error('slip_no')
                                    <div class="text-danger small fw-bold mt-1">
                                        <i class="fas fa-exclamation-circle me-1"></i>{{ $message }}
                                    </div>
                                @enderror
                                <div id="intakeSlipSequenceBadge">
                                    @if($receiveSlipSequence)
                                        <div class="alert alert-success py-1 px-2 small mb-0 mt-1 d-flex justify-content-between align-items-center border-success">
                                            <div>
                                                <i class="fas fa-book-bookmark text-success me-1"></i>
                                                Active Book: <strong>{{ $receiveSlipSequence->label ?: 'Receiving (GRN)' }}</strong> 
                                                <span class="text-muted">(Range: {{ $receiveSlipSequence->book_start_no }} - {{ $receiveSlipSequence->book_end_no }})</span>
                                            </div>
                                            <span class="badge bg-success">{{ $receiveSlipSequence->getRemainingSlips() }} slips left</span>
                                        </div>
                                    @else
                                        <div class="alert alert-warning py-1 px-2 small mb-0 mt-1 d-flex justify-content-between align-items-center">
                                            <div>
                                                <i class="fas fa-triangle-exclamation text-warning me-1"></i>
                                                No active receiving slip book sequence configured for this store.
                                            </div>
                                            <a href="{{ Route::has('store-manager.slip-sequences.create') ? route('store-manager.slip-sequences.create') : (Route::has('slip-sequences.create') ? route('slip-sequences.create') : url('/store-manager/slip-sequences/create')) }}?store_id={{ $purchaseRequest->store_id }}&slip_type=receive" target="_blank" class="btn btn-xs btn-outline-primary py-0 text-decoration-none">
                                                <i class="fas fa-plus"></i> Configure Book
                                            </a>
                                        </div>
                                    @endif
                                </div>
                                <div id="intakeSlipRangeWarning" class="alert alert-danger py-1 px-2 small mt-1 d-none"></div>
                            </div>

                            <div class="mb-2">
                                <label class="form-label small fw-bold text-uppercase text-muted">Receiving Date</label>
                                <input type="date" name="received_date" class="form-control form-control-sm" value="{{ date('Y-m-d') }}" required>
                            </div>

                            @if(isset($prDeliveryReceipts) && $prDeliveryReceipts->count() > 0)
                                <div class="mb-3">
                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                        <label class="form-label small fw-bold text-uppercase text-muted mb-0">
                                            <i class="fas fa-receipt text-success me-1"></i>Previous Intake Slips ({{ $prDeliveryReceipts->count() }})
                                        </label>
                                        <span class="badge bg-info bg-opacity-10 text-info border border-info border-opacity-25" style="font-size: 10px;">Partial Batches</span>
                                    </div>
                                    <div class="list-group list-group-flush border rounded bg-white shadow-xs" style="max-height: 140px; overflow-y: auto;">
                                        @foreach($prDeliveryReceipts as $dr)
                                            <div class="list-group-item p-2 d-flex justify-content-between align-items-center">
                                                <div>
                                                    <div class="fw-bold font-monospace text-primary small">
                                                        <i class="fas fa-hashtag me-1 text-muted"></i>{{ $dr->dr_no }}
                                                        <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 ms-1 py-0 px-1" style="font-size: 9px;">Stocked</span>
                                                    </div>
                                                    <div class="text-muted" style="font-size: 10px;">
                                                        {{ $dr->received_date->format('M d, Y') ?? date('M d, Y') }}
                                                        @if($dr->store) &bull; {{ $dr->store->name }} @endif
                                                        @if($dr->receivedBy) &bull; By: {{ $dr->receivedBy->name }} @endif
                                                    </div>
                                                </div>
                                                <div class="text-end">
                                                    @php
                                                        $slipTotalAccepted = $dr->items->sum('accepted_quantity');
                                                    @endphp
                                                    <span class="badge bg-secondary font-monospace">+{{ number_format($slipTotalAccepted, 2) }}</span>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                    <div class="alert alert-info py-1 px-2 small mt-1 mb-0 d-flex align-items-center" style="font-size: 11px;">
                                        <i class="fas fa-info-circle me-1 flex-shrink-0"></i>
                                        <span>Partial delivery logged above. Enter the next slip number and quantity below to continue intake.</span>
                                    </div>
                                </div>
                            @endif

                            <div class="mb-3">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <label class="form-label small fw-bold text-uppercase text-muted mb-0">Item Quantities To Receive</label>
                                    <span class="badge bg-light text-dark border" style="font-size: 10px;">Balance Tracking</span>
                                </div>
                                <div class="bg-light p-2 rounded border" style="max-height: 280px; overflow-y: auto;">
                                    @foreach($purchaseRequest->items as $itm)
                                        @php
                                            $targetQty = method_exists($itm, 'getTargetIntakeQty') 
                                                ? $itm->getTargetIntakeQty() 
                                                : ((float)($itm->purchased_quantity ?? 0) > 0 ? (float)$itm->purchased_quantity : (float)$itm->quantity);
                                            $receivedQty = method_exists($itm, 'getReceivedQty') 
                                                ? $itm->getReceivedQty() 
                                                : (float)($itm->received_quantity ?? 0);
                                            $remainingQty = method_exists($itm, 'getRemainingIntakeQty') 
                                                ? $itm->getRemainingIntakeQty() 
                                                : max(0.0, $targetQty - $receivedQty);
                                            $isFulfilled = ($remainingQty <= 0.001 && $targetQty > 0);
                                            $unitLabel = $itm->unit ?? ($itm->product->unit ?? 'pcs');
                                        @endphp
                                        <div class="mb-2 pb-2 border-bottom {{ $isFulfilled ? 'opacity-75' : '' }}">
                                            <div class="d-flex justify-content-between align-items-center mb-1">
                                                <span class="small fw-bold text-dark">{{ $itm->product->name ?? 'Material #' . $itm->product_id }}</span>
                                                @if($isFulfilled)
                                                    <span class="badge bg-success text-white">
                                                        <i class="fas fa-check-circle me-1"></i>Fulfilled ({{ number_format($targetQty, 2) }} {{ $unitLabel }})
                                                    </span>
                                                @else
                                                    <span class="badge bg-primary text-white">
                                                        Target: {{ number_format($targetQty, 2) }} {{ $unitLabel }}
                                                    </span>
                                                @endif
                                            </div>

                                            <!-- Progress and Remaining Breakdown -->
                                            <div class="d-flex justify-content-between align-items-center small mb-2 px-2 py-1 rounded bg-white border">
                                                <span class="text-muted" style="font-size: 10px;">
                                                    Received so far: <strong class="{{ $receivedQty > 0 ? 'text-success' : 'text-secondary' }}">{{ number_format($receivedQty, 2) }}</strong> / {{ number_format($targetQty, 2) }} {{ $unitLabel }}
                                                </span>
                                                <span class="badge {{ $remainingQty > 0 ? 'bg-warning text-dark' : 'bg-success text-white' }}" style="font-size: 10px;">
                                                    {{ $remainingQty > 0 ? 'Remaining: ' . number_format($remainingQty, 2) . ' ' . $unitLabel : '100% Stocked' }}
                                                </span>
                                            </div>

                                            <div class="row g-1">
                                                <div class="col-6">
                                                    <label class="text-muted" style="font-size: 10px;">Qty Received (This Slip)</label>
                                                    <input type="number" step="0.01" min="0" 
                                                           name="items[{{ $itm->id }}][quantity]" 
                                                           value="{{ $isFulfilled ? '0' : number_format($remainingQty, 2, '.', '') }}" 
                                                           class="form-control form-control-sm" required>
                                                </div>
                                                <div class="col-6">
                                                    <label class="text-muted" style="font-size: 10px;">Accepted Qty (This Slip)</label>
                                                    <input type="number" step="0.01" min="0" 
                                                           name="items[{{ $itm->id }}][accepted_quantity]" 
                                                           value="{{ $isFulfilled ? '0' : number_format($remainingQty, 2, '.', '') }}" 
                                                           class="form-control form-control-sm" required>
                                                </div>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label small fw-bold text-uppercase text-muted">Intake Notes / Remarks</label>
                                <textarea name="notes" class="form-control form-control-sm" rows="2" placeholder="Delivery slip notes, batch number, condition..."></textarea>
                            </div>

                            <button type="submit" class="btn btn-success btn-sm w-100 fw-bold shadow-sm py-2">
                                <i class="fas fa-check-double me-1"></i> Confirm Intake & Add to Inventory
                            </button>
                            <small class="text-muted d-block text-center mt-1" style="font-size: 10px;">
                                If accepted quantity is less than target, PR will remain open for the next delivery slip.
                            </small>
                        </form>

                    @else
                        <div class="alert alert-secondary py-2 small mb-0">No immediate control action required at this stage.</div>
                    @endif
                </div>
            </div>
            @else
            <!-- Stage Action Controls: Locked Mode for non-owners -->
            <div class="card border-secondary border-opacity-25 shadow-sm mb-4">
                <div class="card-header bg-secondary bg-opacity-75 text-white font-weight-bold py-3 d-flex justify-content-between align-items-center">
                    <span><i class="fas fa-lock me-2"></i>Stage Action Controls</span>
                    <span class="badge bg-dark text-white"><i class="fas fa-shield-alt me-1"></i>Locked</span>
                </div>
                <div class="card-body p-4 text-center">
                    <div class="mb-3">
                        <span class="d-inline-flex align-items-center justify-content-center bg-secondary bg-opacity-10 text-secondary rounded-circle" style="width: 56px; height: 56px;">
                            <i class="fas fa-lock fa-2x text-muted"></i>
                        </span>
                    </div>
                    @if($purchaseRequest->status === \App\Models\PurchaseRequest::STATUS_TRANSFERRED)
                        <h6 class="fw-bold text-primary mb-1"><i class="fas fa-truck-moving me-1"></i>Fulfilled via Store Transfer</h6>
                        <p class="small text-muted mb-0">This purchase request was fulfilled via store transfer and all lifecycle actions are locked in Procurement.</p>
                    @elseif($purchaseRequest->trashed() || $purchaseRequest->status === \App\Models\PurchaseRequest::STATUS_CANCELLED)
                        <h6 class="fw-bold text-danger mb-1"><i class="fas fa-trash-can me-1"></i>Request Deleted &amp; Locked</h6>
                        <p class="small text-muted mb-0">This purchase request was moved to the Deleted category and all workflow actions are permanently locked.</p>
                    @elseif(in_array($purchaseRequest->status, [\App\Models\PurchaseRequest::STATUS_INTAKE_COMPLETE, \App\Models\PurchaseRequest::STATUS_COMPLETED]))
                        <h6 class="fw-bold text-success mb-1"><i class="fas fa-check-circle me-1"></i>Lifecycle Completed</h6>
                        <p class="small text-muted mb-0">This purchase request has completed all workflow stages.</p>
                    @elseif($purchaseRequest->status === \App\Models\PurchaseRequest::STATUS_REJECTED)
                        <h6 class="fw-bold text-danger mb-1"><i class="fas fa-times-circle me-1"></i>Request Rejected</h6>
                        <p class="small text-muted mb-3">This purchase request was rejected during the review cycle.</p>

                        @if(auth()->user()->hasAnyRole(['gm', 'general_manager', 'admin', 'global_admin']))
                        <div class="alert alert-warning border-start border-4 border-warning text-start small mb-3">
                            <i class="fas fa-shield-alt text-warning me-1"></i>
                            <strong>Admin / GM Override:</strong> You can re-route this PR to Finance without re-running the full cycle.
                        </div>
                        <div class="d-flex flex-column gap-2">
                            {{-- Option 1: Send directly to Finance (quick fix) --}}
                            <form method="POST" action="{{ route('purchase-requests.send-to-finance-direct', $purchaseRequest) }}"
                                  onsubmit="return confirm('Send PR #{{ $purchaseRequest->pr_no }} directly to Finance Head for payment? This will create an Expense Request and show it in the Finance section immediately.')">
                                @csrf
                                <input type="hidden" name="notes" value="Overridden by {{ auth()->user()->name }} — sent to Finance despite prior rejection.">
                                <button type="submit" class="btn btn-success w-100 fw-bold shadow-sm">
                                    <i class="fas fa-paper-plane me-2"></i>Send to Finance Head Now
                                    <div class="small fw-normal opacity-75">
                                        @php
                                            $sp = $purchaseRequest->proformaInvoices()->where('gm_selected', true)->first()
                                                ?? $purchaseRequest->proformaInvoices()->orderBy('grand_total','asc')->first();
                                            $amt = $sp ? $sp->grand_total : ($purchaseRequest->direct_buy_amount ?? 0);
                                            $sup = $sp ? ($sp->supplier->name ?? $sp->supplier_name ?? '—') : ($purchaseRequest->supplier->name ?? '—');
                                        @endphp
                                        ETB {{ number_format($amt, 2) }} &bull; {{ $sup }}
                                    </div>
                                </button>
                            </form>

                            {{-- Option 2: Restore to GM stage to re-decide --}}
                            <form method="POST" action="{{ route('purchase-requests.reactivate', $purchaseRequest) }}"
                                  onsubmit="return confirm('Re-activate PR #{{ $purchaseRequest->pr_no }} and return it to GM Decision stage?')">
                                @csrf
                                <button type="submit" class="btn btn-outline-secondary w-100 btn-sm">
                                    <i class="fas fa-undo me-1"></i>Re-activate (Return to GM Stage)
                                </button>
                            </form>
                        </div>
                        @endif
                    @elseif(($isFinalIntake ?? false) && $purchaseRequest->status === \App\Models\PurchaseRequest::STATUS_PENDING_STORE_REVIEW)
                        <h6 class="fw-bold text-success mb-1">
                            <i class="fas fa-boxes-packing text-success me-1"></i> Awaiting Store Keeper Final Intake
                        </h6>
                        <p class="small text-muted mb-3">
                            The purchase is complete and materials have arrived. The <strong>Store Keeper</strong> (not the Store Manager) is responsible for physically inspecting goods, verifying counts, and entering the receiving slip (GRN / Model 19) into store inventory.
                        </p>
                        <div class="p-3 bg-light rounded border text-start small">
                            <div class="d-flex justify-content-between align-items-center mb-2 pb-2 border-bottom">
                                <span class="text-muted">Workflow Status:</span>
                                <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 fw-bold">Pending Store Keeper Intake</span>
                            </div>
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="text-muted">Action Required By:</span>
                                <span class="fw-bold text-success"><i class="fas fa-user-check me-1"></i>Store Keeper</span>
                            </div>
                        </div>
                    @else
                        <h6 class="fw-bold text-dark mb-1">Stage Controls Locked (Sent)</h6>
                        <p class="small text-muted mb-3">
                            This request has been forwarded and is currently awaiting action from 
                            <strong class="text-primary">{{ ucfirst(str_replace('_', ' ', $purchaseRequest->current_owner_role ?? 'Assigned Role')) }}</strong>.
                        </p>
                        <div class="p-3 bg-light rounded border text-start small">
                            <div class="d-flex justify-content-between align-items-center mb-2 pb-2 border-bottom">
                                <span class="text-muted">Current Workflow Status:</span>
                                <span class="badge bg-{{ \App\Models\PurchaseRequest::statusBadgeClass($purchaseRequest->status) }}">{{ $purchaseRequest->status_label }}</span>
                            </div>
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="text-muted">Stage Assigned To:</span>
                                <span class="fw-bold text-dark"><i class="fas fa-user-tag text-primary me-1"></i>{{ ucfirst(str_replace('_', ' ', $purchaseRequest->current_owner_role ?? 'None')) }}</span>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
            @endif
        </div>

        <!-- Right Panel: Items, Stock, Proformas, Audit Trail -->
        <div class="col-lg-8">
            <!-- Items Table with Selective Routing Controls -->
            @php
                $isSelectableStage = $canActOnCurrentStage && in_array($purchaseRequest->status, [
                    \App\Models\PurchaseRequest::STATUS_PENDING_STORE_REVIEW,
                    \App\Models\PurchaseRequest::STATUS_PENDING_PROC_MANAGER,
                    \App\Models\PurchaseRequest::STATUS_PENDING_PROC_TEAM
                ]);
            @endphp
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white font-weight-bold py-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div class="d-flex align-items-center gap-2">
                        <span class="fw-bold fs-6"><i class="fas fa-boxes text-primary me-2"></i>Requested Items</span>
                        <span class="badge bg-secondary rounded-pill">{{ $purchaseRequest->items->count() }} items</span>
                    </div>

                    <div class="d-flex align-items-center gap-2 flex-wrap">
                        @if($isGlobalAdmin && !$purchaseRequest->trashed() && !in_array($purchaseRequest->status, [\App\Models\PurchaseRequest::STATUS_COMPLETED, \App\Models\PurchaseRequest::STATUS_CANCELLED, \App\Models\PurchaseRequest::STATUS_REJECTED, \App\Models\PurchaseRequest::STATUS_TRANSFERRED]))
                        <button type="button" class="btn btn-sm btn-primary shadow-sm fw-bold" data-bs-toggle="modal" data-bs-target="#addPrItemModal">
                            <i class="fas fa-plus-circle me-1"></i> Add More Items (Admin Only)
                        </button>
                        @elseif(!$isGlobalAdmin || $purchaseRequest->trashed() || in_array($purchaseRequest->status, [\App\Models\PurchaseRequest::STATUS_TRANSFERRED, \App\Models\PurchaseRequest::STATUS_CANCELLED]))
                        <span class="badge bg-light text-muted border py-2 px-3 fw-normal" title="Records are locked after creation. No new materials can be added.">
                            <i class="fas fa-lock me-1 text-secondary"></i> Materials Locked
                        </span>
                        @endif

                        @if($isSelectableStage && !$purchaseRequest->trashed() && !in_array($purchaseRequest->status, [\App\Models\PurchaseRequest::STATUS_TRANSFERRED, \App\Models\PurchaseRequest::STATUS_CANCELLED]))
                            @if($purchaseRequest->status === \App\Models\PurchaseRequest::STATUS_PENDING_STORE_REVIEW)
                            <div class="d-flex align-items-center gap-2 flex-wrap">
                                <span class="small text-muted me-1"><span class="selected-items-count fw-bold text-primary">0</span> selected</span>
                                <button type="button" class="btn btn-sm btn-outline-info shadow-sm" onclick="openSelectiveTransferModal()">
                                    <i class="fas fa-truck-ramp-box me-1"></i> Transfer Selected
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-success shadow-sm" onclick="openSelectiveSendPmModal()">
                                    <i class="fas fa-cart-shopping me-1"></i> Send to Purchase
                                </button>
                                <button type="button" class="btn btn-sm btn-primary shadow-sm fw-bold" data-bs-toggle="modal" data-bs-target="#splitAndProcessModal">
                                    <i class="fas fa-random me-1"></i> Smart Split All
                                </button>
                            </div>
                            @elseif($purchaseRequest->status === \App\Models\PurchaseRequest::STATUS_PENDING_PROC_MANAGER)
                            <div class="d-flex align-items-center gap-2 flex-wrap">
                                <span class="small text-muted me-1"><span class="selected-items-count fw-bold text-primary">0</span> selected</span>
                                <button type="button" class="btn btn-sm btn-warning text-dark shadow-sm fw-bold" onclick="openPmSendBackStoreModal()">
                                    <i class="fas fa-undo me-1"></i> Send to Store Manager (<span class="selected-items-count fw-bold">0</span>)
                                </button>
                                <button type="button" class="btn btn-sm btn-success shadow-sm fw-bold" onclick="openPmDirectBuyModal()">
                                    <i class="fas fa-bolt me-1"></i> Direct Buy Selected
                                </button>
                                <button type="button" class="btn btn-sm btn-primary shadow-sm fw-bold" onclick="openPmProformaModal()">
                                    <i class="fas fa-file-invoice-dollar me-1"></i> Proforma Selected
                                </button>
                            </div>
                            @elseif($purchaseRequest->status === \App\Models\PurchaseRequest::STATUS_PENDING_PROC_TEAM)
                            <div class="d-flex align-items-center gap-2 flex-wrap">
                                <span class="small text-muted me-1"><span class="selected-items-count fw-bold text-primary">0</span> selected</span>
                                @if($purchaseRequest->sourcing_method === 'direct_buy')
                                <button type="button" class="btn btn-sm btn-success shadow-sm fw-bold" onclick="openProcTeamSelectiveDirectBuyModal()">
                                    <i class="fas fa-bolt me-1"></i> Submit Selected Prices (<span class="selected-items-count fw-bold">0</span>)
                                </button>
                                @else
                                <button type="button" class="btn btn-sm btn-primary shadow-sm fw-bold" onclick="openProcTeamSelectiveProformaModal()">
                                    <i class="fas fa-paper-plane me-1"></i> Submit Selected Quotes (<span class="selected-items-count fw-bold">0</span>)
                                </button>
                                @endif
                                <button type="button" class="btn btn-sm btn-outline-warning text-dark shadow-sm fw-bold" onclick="openProcTeamSendBackPmModal()">
                                    <i class="fas fa-undo me-1"></i> Return Selected to PM (<span class="selected-items-count fw-bold">0</span>)
                                </button>
                            </div>
                            @endif
                        @endif
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table align-middle mb-0" id="requestedItemsTable">
                            <thead class="table-light">
                                <tr>
                                    @if($isSelectableStage)
                                    <th width="40" class="ps-3 text-center">
                                        <input type="checkbox" id="selectAllItems" class="form-check-input" title="Select All Items">
                                    </th>
                                    @endif
                                    <th>Product / Material</th>
                                    <th>Store Inventory Status</th>
                                    <th class="text-center bg-light border-start border-end">
                                        Qty Requested<br><span class="badge bg-secondary bg-opacity-25 text-dark fw-normal py-0 px-1" style="font-size:10px;">Asked</span>
                                    </th>
                                    <th class="text-center bg-light border-end">
                                        Qty Purchased<br><span class="badge bg-primary bg-opacity-25 text-primary fw-normal py-0 px-1" style="font-size:10px;">Procured</span>
                                    </th>
                                    <th class="text-center bg-light border-end">
                                        Qty Received<br><span class="badge bg-success bg-opacity-25 text-success fw-normal py-0 px-1" style="font-size:10px;">Stocked</span>
                                    </th>
                                    <th class="text-center">Status</th>
                                    <th>Unit</th>
                                    @if(!$hideMoneyColumns)
                                    <th>Est. Unit Cost</th>
                                    <th>Est. Total</th>
                                    @endif
                                    <th class="pe-3 text-end">{{ $isStoreReviewStage ? 'Store Decision' : 'Quick Action' }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($purchaseRequest->items as $item)
                                @php
                                    $itemStocks = $stockAvailability[$item->product_id] ?? collect();
                                    $totalRawStock = $itemStocks->sum('quantity_on_hand');
                                    $totalInTransfer = $itemStocks->sum('in_transfer_qty');
                                    $totalNetStock = $itemStocks->sum('net_available');
                                    $hasStock = $totalNetStock > 0 || $totalRawStock > 0;

                                    $reqQty = (float)$item->quantity;
                                    $purchQty = method_exists($item, 'getEffectivePurchasedQty') ? $item->getEffectivePurchasedQty() : (float)($item->purchased_quantity ?? 0);
                                    $remQty = max(0, $reqQty - $purchQty);
                                    $isCompleted = ($purchQty >= $reqQty && $reqQty > 0);
                                    $isPartial = ($purchQty > 0 && !$isCompleted);
                                @endphp
                                <tr id="itemRow{{ $item->id }}">
                                    @if($isSelectableStage)
                                    <td class="ps-3 text-center">
                                        <input type="checkbox" class="form-check-input pr-item-checkbox" 
                                               value="{{ $item->id }}"
                                               data-item-id="{{ $item->id }}"
                                               data-product-id="{{ $item->product_id }}"
                                               data-product-name="{{ $item->product->name ?? 'Item #' . $item->product_id }}"
                                               data-quantity="{{ (float)$item->quantity }}"
                                               data-unit="{{ $item->unit }}"
                                               data-estimated-cost="{{ (float)($item->estimated_unit_cost ?? 0) }}"
                                               data-has-stock="{{ ($totalNetStock > 0) ? '1' : '0' }}"
                                               data-stock-qty="{{ $totalNetStock }}"
                                               onchange="updateSelectionToolbar()">
                                    </td>
                                    @endif
                                    <td>
                                        <strong class="text-dark">{{ $item->product->name ?? 'Item #' . $item->product_id }}</strong>
                                        @if($item->product->code)
                                             <br><code class="small text-muted">{{ $item->product->code }}</code>
                                        @endif
                                        @if($item->specifications)
                                            <br><small class="text-muted fst-italic"><i class="fas fa-info-circle me-1"></i>{{ $item->specifications }}</small>
                                        @endif

                                        @if(!empty($proformaMatrix['has_proformas']))
                                            <div class="mt-2 pt-1 border-top border-light-subtle item-proforma-bid-block" id="itemProformaBidBlock_{{ $item->id }}">
                                                <div class="d-flex align-items-center justify-content-between gap-1 mb-1">
                                                    <span class="small text-muted fw-semibold" style="font-size: 11px;">
                                                        <i class="fas fa-tags text-primary me-1"></i> Quoted Bid:
                                                    </span>
                                                    @if(isset($proformaMatrix['items'][$item->id]['lowest_unit_price']))
                                                        <span class="badge bg-success-subtle text-success border border-success-subtle" style="font-size: 9px;" title="Best quoted price across all proformas">
                                                            <i class="fas fa-arrow-down me-1"></i>Best: {{ number_format($proformaMatrix['items'][$item->id]['lowest_unit_price'], 2) }} ETB
                                                        </span>
                                                    @endif
                                                </div>

                                                @if($canActOnCurrentStage && $purchaseRequest->status === \App\Models\PurchaseRequest::STATUS_PENDING_GM)
                                                    {{-- GM Interactive Award Option Per Material --}}
                                                    @php
                                                        $curAwardedProfId = $proformaMatrix['items'][$item->id]['awarded_proforma_id'] ?? '';
                                                    @endphp
                                                    <div class="input-group input-group-sm">
                                                        <span class="input-group-text bg-white text-muted px-1.5" style="font-size: 11px;" title="Award proforma for this item">
                                                            <i class="fas fa-hand-holding-dollar text-primary"></i>
                                                        </span>
                                                        <select class="form-select form-select-sm gm-item-proforma-select fw-semibold" 
                                                                id="itemBidSelect_{{ $item->id }}" 
                                                                data-item-id="{{ $item->id }}"
                                                                data-quantity="{{ (float)$item->quantity }}"
                                                                onchange="handleItemBidChange({{ $item->id }}, this.value)"
                                                                style="font-size: 11px; border-color: #0d6efd;">
                                                            <option value="">-- Choose Vendor Proforma --</option>
                                                            @foreach($proformaMatrix['items'][$item->id]['quotes'] ?? [] as $pId => $quote)
                                                                @if($quote['unit_price'] !== null)
                                                                    <option value="{{ $pId }}" 
                                                                            data-unit-price="{{ $quote['unit_price'] }}" 
                                                                            data-total="{{ $quote['line_total'] }}"
                                                                            data-supplier-name="{{ $quote['supplier_name'] }}"
                                                                            data-is-lowest="{{ $quote['is_lowest'] ? '1' : '0' }}"
                                                                            {{ (string)$curAwardedProfId === (string)$pId ? 'selected' : '' }}>
                                                                        {{ $quote['supplier_name'] }}: {{ number_format($quote['unit_price'], 2) }} ETB (Total: {{ number_format($quote['line_total'], 2) }}) {{ $quote['is_lowest'] ? '★ LOWEST' : '' }}
                                                                    </option>
                                                                @else
                                                                    <option value="{{ $pId }}" data-unit-price="0" data-total="0" disabled>
                                                                        {{ $quote['supplier_name'] }}: [Not Quoted]
                                                                    </option>
                                                                @endif
                                                            @endforeach
                                                        </select>
                                                    </div>
                                                @else
                                                    {{-- Read-only awarded quote badge --}}
                                                    @php
                                                        $awardedProfId = $item->proforma_invoice_id ?: ($proformaMatrix['items'][$item->id]['awarded_proforma_id'] ?? null);
                                                        $awardedQuote = $awardedProfId ? ($proformaMatrix['items'][$item->id]['quotes'][$awardedProfId] ?? null) : null;
                                                    @endphp
                                                    <div id="itemAwardedBadge_{{ $item->id }}">
                                                        @if($awardedQuote && $awardedQuote['unit_price'] !== null)
                                                            <span class="badge bg-primary-subtle text-primary border border-primary-subtle d-inline-flex align-items-center gap-1" style="font-size: 10.5px;">
                                                                <i class="fas fa-check-circle text-primary"></i>
                                                                <span><strong>{{ $awardedQuote['supplier_name'] }}</strong> &bull; {{ number_format($awardedQuote['unit_price'], 2) }} ETB/{{ $item->unit }}</span>
                                                            </span>
                                                        @elseif($item->supplier)
                                                            <span class="badge bg-light text-dark border" style="font-size: 10px;">
                                                                <i class="fas fa-building me-1"></i> {{ $item->supplier->name }}
                                                            </span>
                                                        @else
                                                            <span class="text-muted" style="font-size: 11px;">Pending GM selection</span>
                                                        @endif
                                                    </div>
                                                @endif
                                            </div>
                                        @endif
                                    </td>
                                    <td>
                                        @if($totalInTransfer > 0)
                                            <div class="mb-1">
                                                <span class="badge bg-info text-dark border border-info border-opacity-25 py-1 px-2 shadow-xs">
                                                    <i class="fas fa-truck-moving me-1"></i> In Transfer: {{ number_format($totalInTransfer, 1) }} {{ $item->unit }}
                                                </span>
                                            </div>
                                        @endif

                                        @if($totalNetStock > 0)
                                            <div>
                                                <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 py-1 px-2">
                                                    <i class="fas fa-warehouse me-1"></i> In Stock: {{ number_format($totalNetStock, 1) }} {{ $item->unit }}
                                                </span>
                                                <div class="small text-muted mt-1" style="font-size: 11px;">
                                                    @foreach($itemStocks as $st)
                                                        @if(($st->net_available ?? 0) > 0)
                                                            {{ $st->store->name }}: {{ number_format($st->net_available, 1) }} | 
                                                        @endif
                                                    @endforeach
                                                </div>
                                            </div>
                                        @elseif($totalInTransfer <= 0)
                                            <span class="badge bg-light text-muted border py-1 px-2">
                                                <i class="fas fa-circle-xmark me-1 text-secondary"></i> Out of Stock
                                            </span>
                                        @endif
                                    </td>
                                    <td class="text-center fw-bold text-dark fs-6 bg-light border-start border-end">
                                        {{ number_format($reqQty, 3) }}
                                    </td>
                                    <td class="text-center fw-bold fs-6 bg-light border-end">
                                        @if($isCompleted)
                                            <span class="badge bg-success bg-opacity-10 text-success border border-success py-1 px-2">
                                                <i class="fas fa-check-circle me-1"></i>{{ number_format($purchQty, 3) }}
                                            </span>
                                        @elseif($isPartial)
                                            <span class="badge bg-warning bg-opacity-15 text-warning-emphasis border border-warning py-1 px-2">
                                                <i class="fas fa-chart-pie me-1"></i>{{ number_format($purchQty, 3) }}
                                            </span>
                                        @else
                                            <span class="badge bg-light text-muted border py-1 px-2">
                                                <i class="fas fa-clock me-1 text-secondary"></i>0.000
                                            </span>
                                        @endif
                                    </td>
                                    @php
                                        $itemRecQty = method_exists($item, 'getReceivedQty') ? $item->getReceivedQty() : (float)($item->received_quantity ?? 0);
                                        $itemTargetQty = method_exists($item, 'getTargetIntakeQty') ? $item->getTargetIntakeQty() : ($purchQty > 0 ? $purchQty : $reqQty);
                                        $itemRemIntake = max(0.0, $itemTargetQty - $itemRecQty);
                                        $itemIntakeDone = ($itemTargetQty > 0 && $itemRecQty >= $itemTargetQty);
                                    @endphp
                                    <td class="text-center fw-bold fs-6 bg-light border-end">
                                        @if($itemIntakeDone)
                                            <span class="badge bg-success py-1 px-2 text-white">
                                                <i class="fas fa-check-double me-1"></i>{{ number_format($itemRecQty, 2) }}
                                            </span>
                                        @elseif($itemRecQty > 0)
                                            <span class="badge bg-info text-dark py-1 px-2">
                                                <i class="fas fa-box-open me-1"></i>{{ number_format($itemRecQty, 2) }}
                                            </span>
                                            <div class="small text-danger fw-bold mt-1" style="font-size: 10px;">Rem: {{ number_format($itemRemIntake, 2) }}</div>
                                        @else
                                            <span class="badge bg-light text-muted border py-1 px-2">0.00</span>
                                        @endif
                                    </td>
                                    <td class="text-center">
                                        @if($itemIntakeDone)
                                            <span class="badge rounded-pill bg-success"><i class="fas fa-check-double me-1"></i>Stocked</span>
                                        @elseif($itemRecQty > 0)
                                            <span class="badge rounded-pill bg-info text-dark">Partial ({{ $itemTargetQty > 0 ? round(($itemRecQty / $itemTargetQty) * 100) : 0 }}%)</span>
                                            <div class="small text-muted mt-1" style="font-size: 10px;">Awaiting next slip</div>
                                        @elseif($isCompleted)
                                            <span class="badge rounded-pill bg-primary"><i class="fas fa-check me-1"></i>Purchased</span>
                                        @elseif($isPartial)
                                            <span class="badge rounded-pill bg-warning text-dark">Partial Procured</span>
                                            <div class="small text-muted mt-1" style="font-size: 10px;">Rem: {{ number_format($remQty, 2) }} {{ $item->unit }}</div>
                                        @else
                                            <span class="badge rounded-pill bg-secondary bg-opacity-25 text-dark">Pending</span>
                                        @endif
                                    </td>
                                    <td><span class="badge bg-light text-dark border">{{ $item->unit }}</span></td>
                                    @if(!$hideMoneyColumns)
                                    <td><span id="itemUnitCostDisplay_{{ $item->id }}">{{ number_format($item->estimated_unit_cost ?? 0, 2) }}</span> ETB</td>
                                    @php
                                        $effectiveQtyForTotal = ($purchQty > 0) ? $purchQty : $reqQty;
                                        $unitCostVal = (float)($item->estimated_unit_cost ?? 0);
                                        $itemCalculatedTotal = $effectiveQtyForTotal * $unitCostVal;
                                    @endphp
                                    <td class="fw-bold text-primary">
                                        <span id="itemCalculatedTotalDisplay_{{ $item->id }}">{{ number_format($itemCalculatedTotal, 2) }}</span> ETB
                                        @if($purchQty > 0)
                                            <div class="small text-muted" style="font-size: 10px;">
                                                ({{ number_format($purchQty, 2) }} &times; {{ number_format($unitCostVal, 2) }})
                                            </div>
                                        @endif
                                    </td>
                                    @endif
                                    <td class="pe-3 text-end">
                                        <div class="d-flex justify-content-end align-items-center gap-1 flex-wrap">
                                            @if($isSelectableStage)
                                                @if($purchaseRequest->status === \App\Models\PurchaseRequest::STATUS_PENDING_STORE_REVIEW)
                                                    <button type="button" class="btn btn-outline-info btn-sm fw-semibold" title="Transfer this item from another store" onclick="quickTransferSingleItem({{ $item->id }})">
                                                        <i class="fas fa-truck-ramp-box me-1"></i> Transfer
                                                    </button>
                                                    <button type="button" class="btn btn-outline-success btn-sm fw-semibold" title="Send this item to purchase" onclick="quickPurchaseSingleItem({{ $item->id }})">
                                                        <i class="fas fa-cart-shopping me-1"></i> Purchase
                                                    </button>
                                                @elseif($purchaseRequest->status === \App\Models\PurchaseRequest::STATUS_PENDING_PROC_MANAGER)
                                                    @if($hasStock)
                                                    <button type="button" class="btn btn-warning text-dark fw-semibold" title="Send to Store Manager (Stock Available in other stores)" onclick="quickPmSendBackSingleItem({{ $item->id }})">
                                                        <i class="fas fa-undo me-1"></i>To Store
                                                    </button>
                                                    @else
                                                    <button type="button" class="btn btn-outline-secondary" title="Send back to Store Manager" onclick="quickPmSendBackSingleItem({{ $item->id }})">
                                                        <i class="fas fa-undo me-1"></i>To Store
                                                    </button>
                                                    @endif
                                                    <button type="button" class="btn btn-outline-success" title="Direct Buy this item" onclick="quickPmDirectBuySingleItem({{ $item->id }})">
                                                        <i class="fas fa-bolt"></i>
                                                    </button>
                                                    <button type="button" class="btn btn-outline-primary" title="Proforma quote for this item" onclick="quickPmProformaSingleItem({{ $item->id }})">
                                                        <i class="fas fa-file-invoice-dollar"></i>
                                                    </button>
                                                @elseif($purchaseRequest->status === \App\Models\PurchaseRequest::STATUS_PENDING_PROC_TEAM)
                                                    @if($purchaseRequest->sourcing_method === 'direct_buy')
                                                    <button type="button" class="btn btn-outline-success btn-sm fw-semibold" title="Submit price for this item separately" onclick="quickProcTeamDirectBuySingleItem({{ $item->id }})">
                                                        <i class="fas fa-bolt me-1"></i> Price Item
                                                    </button>
                                                    @else
                                                    <button type="button" class="btn btn-outline-primary btn-sm fw-semibold" title="Submit this item separately with quotes" onclick="quickProcTeamProformaSingleItem({{ $item->id }})">
                                                        <i class="fas fa-paper-plane me-1"></i> Submit Item
                                                    </button>
                                                    @endif
                                                    <button type="button" class="btn btn-outline-warning text-dark btn-sm" title="Return this item back to Procurement Manager" onclick="quickProcTeamSendBackSingleItem({{ $item->id }})">
                                                        <i class="fas fa-undo me-1"></i> Return
                                                    </button>
                                                @endif
                                            @endif

                                            @if($isGlobalAdmin && !$isStoreReviewStage && !$isStoreManagerOnly)
                                            <!-- Global Admin Only: Edit / Update Item / Purchased Qty -->
                                            <button type="button" class="btn btn-outline-secondary btn-sm" title="Edit Item Details (Global Admin Only)"
                                                    onclick="openEditPrItemModal({{ $item->id }}, '{{ addslashes($item->product->name ?? 'Item #' . $item->product_id) }}', {{ $reqQty }}, {{ $purchQty }}, '{{ addslashes($item->unit) }}', {{ (float)($item->estimated_unit_cost ?? 0) }}, '{{ addslashes($item->specifications ?? '') }}')">
                                                <i class="fas fa-pen-to-square"></i>
                                            </button>
                                            @if($purchaseRequest->items->count() > 1 && !in_array($purchaseRequest->status, [\App\Models\PurchaseRequest::STATUS_COMPLETED, \App\Models\PurchaseRequest::STATUS_CANCELLED]))
                                            <button type="button" class="btn btn-outline-danger btn-sm" title="Remove Item (Global Admin Only)"
                                                    onclick="confirmDeletePrItem({{ $item->id }}, '{{ addslashes($item->product->name ?? 'Item #' . $item->product_id) }}')">
                                                <i class="fas fa-trash-can"></i>
                                            </button>
                                            @endif
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                                @endforeach

                                @if($purchaseRequest->items->isEmpty())
                                    @php
                                        $linkedTr = $prTransfers ?? $purchaseRequest->linked_transfers ?? collect();
                                    @endphp
                                    @if($linkedTr->isNotEmpty())
                                        <tr>
                                            <td colspan="10" class="p-0">
                                                <div class="p-3 bg-light border-0">
                                                    <div class="alert alert-info border-info-subtle d-flex align-items-center gap-2 mb-3">
                                                        <i class="fa-solid fa-truck-moving fa-lg text-primary"></i>
                                                        <div>
                                                            <strong class="d-block">All Line Items Transferred via Store Transfer</strong>
                                                            <span class="small">This purchase request was processed and fulfilled through store transfer(s) below rather than external purchase.</span>
                                                        </div>
                                                    </div>
                                                    <div class="table-responsive">
                                                        <table class="table table-sm table-bordered align-middle mb-0 bg-white shadow-xs">
                                                            <thead class="table-light text-uppercase small text-muted" style="font-size: 11px;">
                                                                <tr>
                                                                    <th>Transfer Slip #</th>
                                                                    <th>Material / Product</th>
                                                                    <th class="text-center">Transferred Quantity</th>
                                                                    <th>From Store</th>
                                                                    <th>To Store</th>
                                                                    <th>Status</th>
                                                                    <th class="text-end pe-2">Action</th>
                                                                </tr>
                                                            </thead>
                                                            <tbody>
                                                                @foreach($linkedTr as $tr)
                                                                    @foreach($tr->items as $ti)
                                                                    <tr>
                                                                        <td class="fw-bold font-monospace">
                                                                            <a href="{{ route('store-manager.transfers.show', $tr) }}" class="text-decoration-none text-primary">
                                                                                #{{ $tr->transfer_no ?? ('TR-' . $tr->id) }}
                                                                            </a>
                                                                        </td>
                                                                        <td class="fw-bold">{{ $ti->product->name ?? ('Item #' . $ti->product_id) }}</td>
                                                                        <td class="text-center fw-bold">{{ (float)($ti->requested_quantity ?? $ti->approved_quantity ?? 0) }} {{ $ti->unit ?? ($ti->product->unit ?? 'pcs') }}</td>
                                                                        <td>{{ $tr->fromStore->name ?? 'Source Store' }}</td>
                                                                        <td>{{ $tr->toStore->name ?? 'Destination Store' }}</td>
                                                                        <td><span class="badge bg-success-subtle text-success">{{ ucfirst($tr->status) }}</span></td>
                                                                        <td class="text-end pe-2">
                                                                            @if($tr->isDeductedFromOrigin() && $tr->isAddedToDestination())
                                                                                <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 me-1" style="font-size:0.75rem;" title="Inventory deducted from origin and added to destination"><i class="fas fa-check-double me-1"></i>Stock Synced</span>
                                                                            @elseif(!in_array($tr->status, ['rejected', 'cancelled']))
                                                                                <form action="{{ route('store-manager.transfers.sync-inventory', $tr) }}" method="POST" class="d-inline me-1">
                                                                                    @csrf
                                                                                    <button type="submit" class="btn btn-xs btn-outline-warning py-0 px-1 shadow-xs" title="Click to auto-adjust inventory">
                                                                                        <i class="fas fa-rotate me-1"></i>Adjust Stock
                                                                                    </button>
                                                                                </form>
                                                                            @endif
                                                                            <a href="{{ route('store-manager.transfers.show', $tr) }}" class="btn btn-xs btn-outline-primary shadow-xs">
                                                                                <i class="fa-solid fa-arrow-up-right-from-square me-1"></i> View Transfer
                                                                            </a>
                                                                        </td>
                                                                    </tr>
                                                                    @endforeach
                                                                @endforeach
                                                            </tbody>
                                                        </table>
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>
                                    @else
                                        <tr>
                                            <td colspan="10" class="text-center py-4 text-muted">
                                                <i class="fa-solid fa-circle-exclamation fa-2x mb-2 opacity-50"></i>
                                                <p class="mb-1 fw-semibold">No direct purchase items recorded on this request.</p>
                                                <small class="text-muted">Check the workflow history below for transfer, merge, or fulfillment records.</small>
                                            </td>
                                        </tr>
                                    @endif
                                @endif
                            </tbody>
                            @if(!$hideMoneyColumns && $purchaseRequest->items->isNotEmpty())
                            @php
                                $totalPrSpentOrEst = $purchaseRequest->items->sum(function($itm) {
                                    $pQty = method_exists($itm, 'getEffectivePurchasedQty') ? $itm->getEffectivePurchasedQty() : (float)($itm->purchased_quantity ?? 0);
                                    $q = ($pQty > 0) ? $pQty : (float)$itm->quantity;
                                    return $q * (float)($itm->estimated_unit_cost ?? 0);
                                });
                                $hasAnyPurchased = $purchaseRequest->items->contains(function($itm) {
                                    $pQty = method_exists($itm, 'getEffectivePurchasedQty') ? $itm->getEffectivePurchasedQty() : (float)($itm->purchased_quantity ?? 0);
                                    return $pQty > 0;
                                });
                            @endphp
                            <tfoot class="table-light border-top">
                                <tr>
                                    <th colspan="{{ $isSelectableStage ? 8 : 7 }}" class="text-end fw-bold text-uppercase small text-muted">
                                        Total ({{ $hasAnyPurchased ? 'Purchased Actual / Est.' : 'Est. Total' }}):
                                    </th>
                                    <th class="fw-bold text-primary fs-6 text-nowrap">
                                        {{ number_format($totalPrSpentOrEst, 2) }} ETB
                                    </th>
                                    <th></th>
                                </tr>
                            </tfoot>
                            @endif
                        </table>
                    </div>
                </div>
            </div>

            {{-- Comparative Material Bids Matrix (Side-by-Side Vendor Quotation Comparison) --}}
            @if(!empty($proformaMatrix['has_proformas']) && count($proformaMatrix['proformas']) > 0)
            <div class="card border-0 shadow-sm mb-4 border-start border-4 border-info" id="gmBidsMatrixCard">
                <div class="card-header bg-white py-3">
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <div>
                            <div class="d-flex align-items-center gap-2">
                                <span class="badge bg-info-subtle text-info border border-info-subtle text-uppercase fw-bold" style="font-size: 10px;">GM Comparative Analysis</span>
                                <h6 class="fw-bold mb-0 text-dark fs-6"><i class="fas fa-table-cells text-primary me-2"></i>Vendor Quotations & Material Bids Comparison Matrix</h6>
                            </div>
                            <small class="text-muted d-block mt-1" style="font-size: 12px;">
                                Side-by-side analysis of all material bids across competing suppliers. Choose winning quotes for <strong>all materials</strong> or <strong>per material item</strong>.
                            </small>
                        </div>
                        <div class="d-flex align-items-center gap-2 flex-wrap">
                            @if($canActOnCurrentStage && $purchaseRequest->status === \App\Models\PurchaseRequest::STATUS_PENDING_GM)
                            <button type="button" class="btn btn-sm btn-success shadow-xs fw-bold" onclick="autoSelectLowestBids()" title="Automatically select lowest quoted price for each material item">
                                <i class="fas fa-bolt me-1"></i> Auto-Select Best (Lowest) Bid per Item
                            </button>
                            <a href="#gmDecisionForm" class="btn btn-sm btn-primary shadow-xs fw-semibold">
                                <i class="fas fa-check-double me-1"></i> Go to GM Decision
                            </a>
                            @endif
                        </div>
                    </div>
                </div>

                {{-- Summary highlight bar --}}
                <div class="bg-light px-3 py-2 border-bottom d-flex align-items-center justify-content-between flex-wrap gap-2 small">
                    <div class="d-flex align-items-center gap-3">
                        <div><i class="fas fa-boxes-stacked text-secondary me-1"></i> <strong>{{ count($proformaMatrix['items']) }}</strong> Materials</div>
                        <div><i class="fas fa-file-invoice-dollar text-primary me-1"></i> <strong>{{ count($proformaMatrix['proformas']) }}</strong> Vendor Proformas</div>
                        @if(($proformaMatrix['best_overall_total'] ?? 0) > 0)
                        <div><i class="fas fa-trophy text-warning me-1"></i> Combined Lowest Total: <strong class="text-success">{{ number_format($proformaMatrix['best_overall_total'], 2) }} ETB</strong></div>
                        @endif
                    </div>
                    <div class="text-end">
                        <span class="text-muted">Selected Total Award:</span> 
                        <strong class="text-primary fs-6 ms-1" id="matrixAwardedGrandTotal">
                            {{ number_format($purchaseRequest->direct_buy_amount ?: ($purchaseRequest->proformaInvoices->first()->grand_total ?? 0), 2) }} ETB
                        </strong>
                    </div>
                </div>

                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-bordered align-middle mb-0 matrix-table">
                            <thead class="table-light">
                                <tr>
                                    <th style="min-width: 240px; width: 28%;" class="ps-3 bg-light">
                                        Material / Requested Item
                                    </th>
                                    <th style="width: 110px;" class="text-center bg-light">
                                        Qty & Unit
                                    </th>
                                    @foreach($proformaMatrix['proformas'] as $profId => $profMeta)
                                    <th style="min-width: 220px;" class="p-2 matrix-vendor-col-header {{ !empty($profMeta['is_selected']) ? 'bg-primary-subtle border-primary' : '' }}" id="matrixVendorCol_{{ $profId }}">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div>
                                                <div class="fw-bold text-dark fs-6">{{ $profMeta['supplier_name'] ?? 'Vendor' }}</div>
                                                <div class="small font-monospace text-muted" style="font-size: 11px;">
                                                    <code>{{ $profMeta['proforma_no'] ?? '-' }}</code>
                                                    @if(!empty($profMeta['is_lowest_overall']))
                                                        <span class="badge bg-success-subtle text-success border border-success-subtle ms-1" style="font-size: 9px;"><i class="fas fa-award me-1"></i>Lowest Total</span>
                                                    @endif
                                                </div>
                                                @if(!empty($profMeta['valid_until']))
                                                    <div class="text-muted" style="font-size: 10px;">Valid: {{ \Carbon\Carbon::parse($profMeta['valid_until'])->format('M d, Y') }}</div>
                                                @endif
                                            </div>
                                            @if(!empty($profMeta['file_url']))
                                                <a href="{{ $profMeta['file_url'] }}" target="_blank" class="btn btn-xs btn-outline-secondary py-0 px-1" title="View attached document">
                                                    <i class="fas fa-paperclip"></i>
                                                </a>
                                            @endif
                                        </div>
                                        <div class="mt-2 pt-1 border-top d-flex justify-content-between align-items-center">
                                            <div>
                                                <div class="fw-bold text-primary">{{ number_format($profMeta['grand_total'] ?? 0, 2) }} ETB</div>
                                                <div class="text-muted" style="font-size: 10px;">Quote Total</div>
                                            </div>
                                            @if($canActOnCurrentStage && $purchaseRequest->status === \App\Models\PurchaseRequest::STATUS_PENDING_GM)
                                            <button type="button" class="btn btn-xs btn-outline-primary fw-bold shadow-xs" onclick="selectGmProformaForEntirePr({{ $profId }})" title="Award ALL materials to this vendor">
                                                <i class="fas fa-check-double me-1"></i> Award All
                                            </button>
                                            @endif
                                        </div>
                                    </th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($proformaMatrix['items'] as $itemId => $itemRow)
                                <tr>
                                    <td class="ps-3">
                                        <div class="fw-bold text-dark">{{ $itemRow['product_name'] }}</div>
                                        @if($itemRow['product_code'])
                                            <code class="small text-muted">{{ $itemRow['product_code'] }}</code>
                                        @endif
                                        @if($itemRow['specifications'])
                                            <div class="small text-muted fst-italic">{{ $itemRow['specifications'] }}</div>
                                        @endif
                                    </td>
                                    <td class="text-center fw-bold bg-light">
                                        {{ number_format($itemRow['quantity'], 2) }} <span class="small fw-normal text-muted">{{ $itemRow['unit'] }}</span>
                                    </td>
                                    @foreach($proformaMatrix['proformas'] as $profId => $profMeta)
                                        @php
                                            $quote = $itemRow['quotes'][$profId] ?? null;
                                            $isAwarded = ((string)($itemRow['awarded_proforma_id'] ?? '') === (string)$profId);
                                            $isLowest = !empty($quote['is_lowest']);
                                            $hasPrice = ($quote && $quote['unit_price'] !== null && (float)$quote['unit_price'] > 0);
                                        @endphp
                                        <td class="text-center p-2 matrix-cell {{ $isAwarded ? 'bg-primary-subtle border-primary' : ($isLowest ? 'bg-success-subtle bg-opacity-25' : '') }}" 
                                            id="matrixCell_{{ $itemId }}_{{ $profId }}"
                                            @if($canActOnCurrentStage && $purchaseRequest->status === \App\Models\PurchaseRequest::STATUS_PENDING_GM && $hasPrice)
                                                onclick="selectGmProformaForItem({{ $itemId }}, {{ $profId }})"
                                                style="cursor: pointer;"
                                            @endif
                                        >
                                            @if($hasPrice)
                                                <div class="fw-bold text-dark fs-6">{{ number_format($quote['unit_price'], 2) }} <small class="text-muted fw-normal" style="font-size: 10px;">ETB/{{ $itemRow['unit'] }}</small></div>
                                                <div class="text-muted small" style="font-size: 11px;">Total: <strong class="text-dark">{{ number_format($quote['line_total'], 2) }} ETB</strong></div>
                                                
                                                <div class="d-flex justify-content-center align-items-center gap-1 mt-1">
                                                    @if($isLowest)
                                                        <span class="badge bg-success text-white py-0.5 px-1.5" style="font-size: 9px;"><i class="fas fa-star me-1"></i>Lowest</span>
                                                    @endif
                                                    
                                                    @if($canActOnCurrentStage && $purchaseRequest->status === \App\Models\PurchaseRequest::STATUS_PENDING_GM)
                                                        <div class="form-check m-0">
                                                            <input class="form-check-input gm-matrix-radio" type="radio" 
                                                                   name="matrix_item_{{ $itemId }}" 
                                                                   id="matrixRadio_{{ $itemId }}_{{ $profId }}" 
                                                                   value="{{ $profId }}" 
                                                                   {{ $isAwarded ? 'checked' : '' }}
                                                                   onchange="selectGmProformaForItem({{ $itemId }}, {{ $profId }})"
                                                                   onclick="event.stopPropagation()">
                                                            <label class="form-check-label small fw-semibold cursor-pointer" for="matrixRadio_{{ $itemId }}_{{ $profId }}" style="font-size: 10px;">
                                                                Award
                                                            </label>
                                                        </div>
                                                    @elseif($isAwarded)
                                                        <span class="badge bg-primary text-white py-0.5 px-1.5" style="font-size: 9px;"><i class="fas fa-check me-1"></i>Awarded</span>
                                                    @endif
                                                </div>
                                            @else
                                                <span class="text-muted fst-italic small">[No quote provided]</span>
                                            @endif
                                        </td>
                                    @endforeach
                                </tr>
                                @endforeach
                            </tbody>
                            <tfoot class="table-light border-top">
                                <tr>
                                    <th colspan="2" class="text-end fw-bold text-uppercase small text-muted ps-3">
                                        Proforma Quotation Total:
                                    </th>
                                    @foreach($proformaMatrix['proformas'] as $profId => $profMeta)
                                    <th class="text-center p-2">
                                        <div class="fw-bold text-primary fs-6">{{ number_format($profMeta['grand_total'] ?? 0, 2) }} ETB</div>
                                        @if($canActOnCurrentStage && $purchaseRequest->status === \App\Models\PurchaseRequest::STATUS_PENDING_GM)
                                        <button type="button" class="btn btn-xs btn-outline-primary w-100 mt-1 shadow-xs" onclick="selectGmProformaForEntirePr({{ $profId }})">
                                            <i class="fas fa-check me-1"></i> Choose All
                                        </button>
                                        @endif
                                    </th>
                                    @endforeach
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
            @endif

            <!-- Proforma Invoices / Supplier Quotations Section -->
            @if($purchaseRequest->sourcing_method === 'proforma' || $purchaseRequest->proformaInvoices->count() > 0 || in_array($purchaseRequest->status, [\App\Models\PurchaseRequest::STATUS_PENDING_PROC_TEAM, \App\Models\PurchaseRequest::STATUS_PENDING_PROFORMA_SELECTION, \App\Models\PurchaseRequest::STATUS_PENDING_GM]))
            <div class="card border-0 shadow-sm mb-4 border-start border-4 border-primary">
                <div class="card-header bg-white font-weight-bold py-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div class="d-flex align-items-center gap-2">
                        <span class="fw-bold fs-6"><i class="fas fa-file-invoice-dollar text-primary me-2"></i>Vendor Proforma Invoices & Quotes</span>
                        <span class="badge bg-primary rounded-pill">{{ $purchaseRequest->proformaInvoices->count() }} Attached</span>
                    </div>
                    @if($canActOnCurrentStage && in_array($purchaseRequest->status, [\App\Models\PurchaseRequest::STATUS_PENDING_PROC_TEAM, \App\Models\PurchaseRequest::STATUS_PENDING_PROC_MANAGER, \App\Models\PurchaseRequest::STATUS_PENDING_PROFORMA_SELECTION]))
                    <button type="button" class="btn btn-sm btn-primary shadow-sm fw-bold" data-bs-toggle="modal" data-bs-target="#attachProformaModal">
                        <i class="fas fa-plus me-1"></i> Attach Proforma Quote
                    </button>
                    @endif
                </div>
                <div class="card-body p-0">
                    @if($purchaseRequest->proformaInvoices->count() > 0)
                    <div class="table-responsive">
                        <table class="table align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Supplier</th>
                                    <th>Proforma #</th>
                                    <th>Date / Validity</th>
                                    <th>Grand Total (ETB)</th>
                                    <th>Document</th>
                                    <th>Status</th>
                                    @if($canActOnCurrentStage && $purchaseRequest->status === \App\Models\PurchaseRequest::STATUS_PENDING_PROC_TEAM)
                                    <th class="pe-3 text-end">Action</th>
                                    @elseif($purchaseRequest->status === \App\Models\PurchaseRequest::STATUS_PENDING_GM)
                                    <th class="pe-3 text-end">GM Selection</th>
                                    @endif
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($purchaseRequest->proformaInvoices as $prof)
                                @php
                                    $profUrl = \App\Services\FileUploadService::url($prof->file_path);
                                    $pExt = strtolower(pathinfo($prof->file_path ?? '', PATHINFO_EXTENSION));
                                    $isPdf = $pExt === 'pdf';
                                @endphp
                                <tr>
                                    <td>
                                        <strong class="text-dark">{{ $prof->supplier->name ?? 'N/A' }}</strong>
                                        @if($prof->supplier->phone)
                                            <div class="small text-muted"><i class="fas fa-phone me-1"></i>{{ $prof->supplier->phone }}</div>
                                        @endif
                                    </td>
                                    <td><code>{{ $prof->proforma_no }}</code></td>
                                    <td>
                                        <div class="small">{{ optional($prof->proforma_date)->format('M d, Y') ?? '-' }}</div>
                                        @if($prof->valid_until)
                                            <div class="text-muted" style="font-size: 11px;">Valid till: {{ $prof->valid_until->format('M d, Y') }}</div>
                                        @endif
                                    </td>
                                    <td class="fw-bold text-primary fs-6">
                                        {{ number_format($prof->grand_total, 2) }} ETB
                                        @if(!empty($prof->item_prices))
                                            <br>
                                            <button class="btn btn-link btn-xs p-0 text-decoration-none small text-muted" type="button" data-bs-toggle="collapse" data-bs-target="#profItems_{{ $prof->id }}" aria-expanded="false">
                                                <i class="fas fa-list-check me-1 text-primary"></i> {{ count($prof->item_prices) }} items
                                            </button>
                                        @endif
                                    </td>
                                    <td>
                                        @if($profUrl)
                                            <a href="{{ $profUrl }}" target="_blank" class="btn btn-sm btn-outline-primary py-1 px-2 shadow-sm" title="View attached proforma document">
                                                <i class="fas {{ $isPdf ? 'fa-file-pdf text-danger' : 'fa-file-image text-info' }} me-1"></i> View File
                                            </a>
                                        @else
                                            <span class="text-muted small italic">No file</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($prof->gm_selected)
                                            <span class="badge bg-success"><i class="fas fa-check-circle me-1"></i>Selected Choice</span>
                                        @elseif($purchaseRequest->status === \App\Models\PurchaseRequest::STATUS_PENDING_PROFORMA_SELECTION)
                                            <span class="badge bg-warning text-dark"><i class="fas fa-clock me-1"></i>Pending PM Selection</span>
                                        @else
                                            <span class="badge bg-secondary">Attached</span>
                                        @endif
                                    </td>
                                    @if($canActOnCurrentStage && $purchaseRequest->status === \App\Models\PurchaseRequest::STATUS_PENDING_PROC_TEAM)
                                    <td class="pe-3 text-end">
                                        <form action="{{ \Illuminate\Support\Facades\Route::has('purchase-requests.delete-proforma') ? route('purchase-requests.delete-proforma', [$purchaseRequest, $prof]) : url('/purchase-requests/' . $purchaseRequest->id . '/proformas/' . $prof->id) }}" method="POST" onsubmit="return confirm('Delete this proforma quote?');" class="d-inline">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-outline-danger btn-sm py-1 px-2" title="Delete Proforma">
                                                <i class="fas fa-trash me-1"></i> Delete
                                            </button>
                                        </form>
                                    </td>
                                    @elseif($purchaseRequest->status === \App\Models\PurchaseRequest::STATUS_PENDING_GM)
                                    <td class="pe-3 text-end">
                                        <button type="button" class="btn btn-sm btn-outline-primary py-1 px-2 fw-semibold shadow-xs" onclick="syncGmProformaSelect({{ $prof->id }})" title="Select this vendor quote for GM approval">
                                            <i class="fas fa-hand-pointer me-1"></i> Choose This Quote
                                        </button>
                                    </td>
                                    @endif
                                </tr>
                                @if(!empty($prof->item_prices))
                                <tr class="collapse bg-light" id="profItems_{{ $prof->id }}">
                                    <td colspan="7" class="p-3">
                                        <div class="card card-body bg-white border shadow-sm p-3">
                                            <div class="d-flex justify-content-between align-items-center mb-2">
                                                <h6 class="fw-bold small text-dark mb-0">
                                                    <i class="fas fa-file-invoice me-1 text-primary"></i> Item Price Breakdown: <strong>{{ $prof->supplier->name }}</strong> (Quote #{{ $prof->proforma_no }})
                                                </h6>
                                                <span class="badge bg-light text-dark border">Grand Total: {{ number_format($prof->grand_total, 2) }} ETB</span>
                                            </div>
                                            <div class="table-responsive">
                                                <table class="table table-sm table-hover align-middle mb-0 small">
                                                    <thead class="table-light">
                                                        <tr>
                                                            <th>Material / Product</th>
                                                            <th>Quantity</th>
                                                            <th>Quoted Unit Price (ETB)</th>
                                                            <th class="text-end">Line Total (ETB)</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        @foreach($prof->item_prices as $itmData)
                                                        <tr>
                                                            <td>
                                                                <strong class="text-dark">{{ $itmData['product_name'] ?? 'Item' }}</strong>
                                                                @if(!empty($itmData['product_code']))
                                                                    <code class="small text-muted ms-1">{{ $itmData['product_code'] }}</code>
                                                                @endif
                                                            </td>
                                                            <td><span class="badge bg-secondary">{{ $itmData['quantity'] ?? '-' }} {{ $itmData['unit'] ?? '' }}</span></td>
                                                            <td class="fw-semibold text-dark">{{ number_format($itmData['unit_price'] ?? 0, 2) }} ETB</td>
                                                            <td class="text-end fw-bold text-primary">{{ number_format($itmData['total'] ?? 0, 2) }} ETB</td>
                                                        </tr>
                                                        @endforeach
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                                @endif
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @else
                    <div class="p-4 text-center text-muted">
                        <i class="fas fa-file-invoice-dollar fa-3x text-secondary mb-2 opacity-50"></i>
                        <p class="mb-2">No vendor proformas have been attached yet.</p>
                        @if($canActOnCurrentStage && in_array($purchaseRequest->status, [\App\Models\PurchaseRequest::STATUS_PENDING_PROC_TEAM, \App\Models\PurchaseRequest::STATUS_PENDING_PROC_MANAGER]))
                        <button type="button" class="btn btn-sm btn-primary shadow-sm" data-bs-toggle="modal" data-bs-target="#attachProformaModal">
                            <i class="fas fa-plus me-1"></i> Attach First Proforma Quote
                        </button>
                        @endif
                    </div>
                    @endif
                </div>
            </div>
            @endif

            <!-- Uploaded Purchase Receipt & Withholding Voucher (Visible to Requester & All Hierarchy Roles) -->
            @if($purchaseRequest->receipt || $purchaseRequest->withholding_receipt_url)
                @php
                    $receiptUrl = $purchaseRequest->receipt ? \App\Services\FileUploadService::url($purchaseRequest->receipt->file_path) : null;
                    $ext = $purchaseRequest->receipt ? strtolower(pathinfo($purchaseRequest->receipt->file_path, PATHINFO_EXTENSION)) : '';
                    $isImage = in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif']);
                    $isPdf = $ext === 'pdf';
                    $vStatus = $purchaseRequest->receipt->verification_status ?? 'pending';
                    $vBadge = match($vStatus) {
                        'verified' => ['class' => 'bg-success', 'label' => 'Verified & Approved by Finance', 'icon' => 'fa-circle-check'],
                        'rejected' => ['class' => 'bg-danger', 'label' => 'Receipt Rejected', 'icon' => 'fa-circle-xmark'],
                        default    => ['class' => 'bg-warning text-dark', 'label' => 'Pending Finance Verification', 'icon' => 'fa-clock'],
                    };

                    $withholdingUrl = $purchaseRequest->withholding_receipt_url;
                    $wPath = $purchaseRequest->receipt->withholding_receipt_path ?? $purchaseRequest->payment->withholding_receipt ?? '';
                    $wExt = strtolower(pathinfo($wPath, PATHINFO_EXTENSION));
                    $wIsImage = in_array($wExt, ['jpg', 'jpeg', 'png', 'webp', 'gif']);
                    $wIsPdf = $wExt === 'pdf';
                    $withholdingReceiptNo = $purchaseRequest->effective_withholding_receipt_number;
                    $withholdingAmount = $purchaseRequest->effective_withholding_amount;
                    $withholdingRate = $purchaseRequest->effective_withholding_rate;
                    $hasWithholding = $purchaseRequest->has_effective_withholding;
                @endphp
                <div class="card border-0 shadow-sm mb-4 border-start border-4 border-success">
                    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <div>
                            <h6 class="font-weight-bold text-dark mb-0">
                                <i class="fas fa-receipt text-success me-2"></i>Official Purchase Receipt / Payment Proof
                            </h6>
                            @if($purchaseRequest->receipt)
                            <small class="text-muted">
                                Uploaded by <strong>{{ $purchaseRequest->receipt->uploadedBy->name ?? 'Procurement Officer' }}</strong>
                                on {{ $purchaseRequest->receipt->created_at->format('M d, Y H:i') }}
                            </small>
                            @endif
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            @if($purchaseRequest->receipt)
                            <span class="badge {{ $vBadge['class'] }} px-2 py-1">
                                <i class="fas {{ $vBadge['icon'] }} me-1"></i>{{ $vBadge['label'] }}
                            </span>
                            @endif
                            @if($receiptUrl)
                                <a href="{{ $receiptUrl }}" class="btn btn-sm btn-outline-primary shadow-sm" target="_blank" download>
                                    <i class="fas fa-download me-1"></i> Download Receipt
                                </a>
                                <a href="{{ $receiptUrl }}" class="btn btn-sm btn-primary shadow-sm" target="_blank">
                                    <i class="fas fa-external-link-alt me-1"></i> Full View
                                </a>
                            @endif
                        </div>
                    </div>
                    <div class="card-body p-3">
                        @if($purchaseRequest->receipt->verification_notes)
                            <div class="alert alert-light border small py-2 px-3 mb-3">
                                <strong>Verification Notes:</strong> {{ $purchaseRequest->receipt->verification_notes }}
                                @if($purchaseRequest->receipt->verifiedBy)
                                    <span class="text-muted">(by {{ $purchaseRequest->receipt->verifiedBy->name }} on {{ $purchaseRequest->receipt->verified_at->format('M d, Y H:i') }})</span>
                                @endif
                            </div>
                        @endif

                        {{-- Inline Receipt Preview --}}
                        @if($receiptUrl)
                            @if($isImage)
                                <div class="text-center p-2 bg-light rounded border">
                                    <a href="{{ $receiptUrl }}" target="_blank" title="Click to view full image">
                                        <img src="{{ $receiptUrl }}" alt="Purchase Receipt" class="img-fluid rounded shadow-sm" style="max-height: 420px; object-fit: contain; cursor: zoom-in;">
                                    </a>
                                    <div class="text-muted small mt-2"><i class="fas fa-search-plus me-1"></i> Click image to open high-resolution view</div>
                                </div>
                            @elseif($isPdf)
                                <div class="border rounded bg-light overflow-hidden" style="height: 480px;">
                                    <iframe src="{{ $receiptUrl }}" width="100%" height="100%" style="border: none;">
                                        <div class="p-3 text-center text-muted">
                                            PDF preview not supported in browser. <a href="{{ $receiptUrl }}" target="_blank" class="btn btn-sm btn-primary mt-2">Download PDF</a>
                                        </div>
                                    </iframe>
                                </div>
                            @else
                                <div class="p-3 bg-light rounded border text-center">
                                    <i class="fas fa-file-alt fa-3x text-secondary mb-2"></i>
                                    <div class="fw-bold">{{ basename($purchaseRequest->receipt->file_path) }}</div>
                                    <a href="{{ $receiptUrl }}" class="btn btn-sm btn-primary mt-2" target="_blank">View / Download Document</a>
                                </div>
                            @endif
                        @elseif($purchaseRequest->receipt)
                            <div class="text-muted small italic p-2 bg-light rounded">Receipt file path recorded, but media link is currently unavailable.</div>
                        @endif

                        {{-- Official ERCA Withholding Receipt Section --}}
                        @if($withholdingUrl)
                            <div class="border-top pt-3 mt-3">
                                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-2">
                                    <div class="d-flex align-items-center flex-wrap gap-2">
                                        <span class="badge bg-warning text-dark px-2 py-1 fw-bold">
                                            <i class="fas fa-file-invoice-dollar me-1"></i> ERCA Withholding Tax Receipt (የቀረጥ ቅነሳ ደረሰኝ)
                                        </span>
                                        @if($withholdingReceiptNo)
                                            <span class="badge bg-white text-dark border font-monospace">Voucher: {{ $withholdingReceiptNo }}</span>
                                        @endif
                                        @if($withholdingRate)
                                            <span class="badge bg-light text-secondary border">{{ number_format($withholdingRate, 1) }}% Rate</span>
                                        @endif
                                        @if($withholdingAmount)
                                            <span class="badge bg-danger-subtle text-danger border border-danger-subtle fw-bold">
                                                -{{ number_format($withholdingAmount, 2) }} ETB Deducted
                                            </span>
                                        @endif
                                    </div>
                                    <div class="d-flex align-items-center gap-2">
                                        <a href="{{ $withholdingUrl }}" class="btn btn-sm btn-outline-secondary shadow-sm" target="_blank" download>
                                            <i class="fas fa-download me-1"></i> Download Voucher
                                        </a>
                                        <a href="{{ $withholdingUrl }}" class="btn btn-sm btn-outline-primary shadow-sm" target="_blank">
                                            <i class="fas fa-external-link-alt me-1"></i> View Full Slip
                                        </a>
                                        <button type="button" class="btn btn-sm btn-outline-dark shadow-sm" data-bs-toggle="collapse" data-bs-target="#updateWithholdingCollapse" aria-expanded="false">
                                            <i class="fas fa-pen-to-square me-1"></i> Update Voucher
                                        </button>
                                    </div>
                                </div>

                                {{-- Inline Withholding Preview --}}
                                @if($wIsImage)
                                    <div class="text-center p-2 bg-light rounded border">
                                        <a href="{{ $withholdingUrl }}" target="_blank" title="Click to view full withholding voucher">
                                            <img src="{{ $withholdingUrl }}" alt="Withholding Tax Receipt Voucher" class="img-fluid rounded shadow-sm" style="max-height: 380px; object-fit: contain; cursor: zoom-in;">
                                        </a>
                                        <div class="text-muted small mt-1"><i class="fas fa-search-plus me-1"></i> Click voucher image to open high-resolution view</div>
                                    </div>
                                @elseif($wIsPdf)
                                    <div class="border rounded bg-light overflow-hidden" style="height: 420px;">
                                        <iframe src="{{ $withholdingUrl }}" width="100%" height="100%" style="border: none;">
                                            <div class="p-3 text-center text-muted">
                                                PDF preview not supported in browser. <a href="{{ $withholdingUrl }}" target="_blank" class="btn btn-sm btn-primary mt-2">Download PDF</a>
                                            </div>
                                        </iframe>
                                    </div>
                                @else
                                    <div class="p-3 bg-light rounded border text-center">
                                        <i class="fas fa-file-invoice text-warning fa-2x mb-2"></i>
                                        <div class="fw-bold">{{ basename($wPath) }}</div>
                                        <a href="{{ $withholdingUrl }}" class="btn btn-sm btn-primary mt-2" target="_blank">View / Download Withholding Voucher</a>
                                    </div>
                                @endif

                                {{-- Update Withholding Receipt Collapse --}}
                                <div class="collapse mt-2" id="updateWithholdingCollapse">
                                    <div class="p-3 border rounded bg-white shadow-xs">
                                        <h6 class="fw-bold small text-dark mb-2">
                                            <i class="fas fa-upload me-1 text-primary"></i> Replace / Update Withholding Tax Receipt
                                        </h6>
                                        <form action="{{ route('purchase-requests.upload-withholding-receipt', $purchaseRequest) }}" method="POST" enctype="multipart/form-data">
                                            @csrf
                                            <div class="row g-2 align-items-end">
                                                <div class="col-md-4">
                                                    <label class="form-label small text-muted mb-1">New Voucher File (.pdf, .jpg, .png) <span class="text-danger">*</span></label>
                                                    <input type="file" name="withholding_receipt" class="form-control form-control-sm" accept=".pdf,.jpg,.jpeg,.png,.webp" required>
                                                </div>
                                                <div class="col-md-3">
                                                    <label class="form-label small text-muted mb-1">Voucher Number</label>
                                                    <input type="text" name="withholding_receipt_number" class="form-control form-control-sm font-monospace" value="{{ $withholdingReceiptNo }}" placeholder="e.g. WH-008123">
                                                </div>
                                                <div class="col-md-2">
                                                    <label class="form-label small text-muted mb-1">Rate (%)</label>
                                                    <input type="number" step="0.01" name="withholding_rate" class="form-control form-control-sm font-monospace" value="{{ $withholdingRate ?? 2.0 }}">
                                                </div>
                                                <div class="col-md-3">
                                                    <label class="form-label small text-muted mb-1">Amount (ETB)</label>
                                                    <div class="input-group input-group-sm">
                                                        <input type="number" step="0.01" name="withholding_amount" class="form-control form-control-sm font-monospace text-danger fw-bold" value="{{ $withholdingAmount > 0 ? $withholdingAmount : '' }}" placeholder="0.00">
                                                        <button type="submit" class="btn btn-sm btn-primary">
                                                            <i class="fas fa-save me-1"></i> Update
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        @else
                            {{-- Withholding Receipt Not Yet Attached: Provide Quick Attach Option --}}
                            <div class="border-top pt-3 mt-3">
                                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 p-3 bg-light rounded border">
                                    <div>
                                        <div class="fw-bold small text-dark">
                                            <i class="fas fa-file-invoice-dollar text-warning me-1"></i> ERCA Withholding Tax Receipt (የቀረጥ ቅነሳ ደረሰኝ)
                                        </div>
                                        <div class="text-muted small mt-1">
                                            @if($hasWithholding)
                                                <span class="text-danger fw-semibold">Withholding is applicable</span>: Tax of {{ number_format($withholdingAmount ?? 0, 2) }} ETB ({{ number_format($withholdingRate ?? 2, 1) }}%) was deducted during payment disbursement.
                                            @else
                                                Attach official withholding tax slip if withholding was deducted by or from the vendor.
                                            @endif
                                        </div>
                                    </div>
                                    <div>
                                        <button type="button" class="btn btn-sm btn-outline-primary shadow-sm" data-bs-toggle="collapse" data-bs-target="#attachWithholdingCollapse" aria-expanded="false">
                                            <i class="fas fa-plus me-1"></i> Attach Withholding Slip If Available
                                        </button>
                                    </div>
                                </div>

                                {{-- Attach Form Collapse --}}
                                <div class="collapse mt-2" id="attachWithholdingCollapse">
                                    <div class="p-3 border rounded bg-white shadow-xs">
                                        <div class="mb-2">
                                            <h6 class="fw-bold small text-dark mb-0">
                                                <i class="fas fa-file-arrow-up text-primary me-1"></i> Upload ERCA Withholding Voucher
                                            </h6>
                                            <small class="text-muted">Attach official tax office withholding receipt for this purchase transaction.</small>
                                        </div>
                                        <form action="{{ route('purchase-requests.upload-withholding-receipt', $purchaseRequest) }}" method="POST" enctype="multipart/form-data">
                                            @csrf
                                            <div class="row g-2 align-items-end">
                                                <div class="col-md-4">
                                                    <label class="form-label small text-muted mb-1">Withholding File (.pdf, .jpg, .png) <span class="text-danger">*</span></label>
                                                    <input type="file" name="withholding_receipt" class="form-control form-control-sm" accept=".pdf,.jpg,.jpeg,.png,.webp" required>
                                                </div>
                                                <div class="col-md-3">
                                                    <label class="form-label small text-muted mb-1">Voucher Number</label>
                                                    <input type="text" name="withholding_receipt_number" class="form-control form-control-sm font-monospace" value="{{ $withholdingReceiptNo }}" placeholder="e.g. WH-008123">
                                                </div>
                                                <div class="col-md-2">
                                                    <label class="form-label small text-muted mb-1">Rate (%)</label>
                                                    <input type="number" step="0.01" name="withholding_rate" class="form-control form-control-sm font-monospace" value="{{ $withholdingRate ?? 2.0 }}">
                                                </div>
                                                <div class="col-md-3">
                                                    <label class="form-label small text-muted mb-1">Withheld Amount (ETB)</label>
                                                    <div class="input-group input-group-sm">
                                                        <input type="number" step="0.01" name="withholding_amount" class="form-control form-control-sm font-monospace text-danger fw-bold" value="{{ $withholdingAmount > 0 ? $withholdingAmount : '' }}" placeholder="0.00">
                                                        <button type="submit" class="btn btn-sm btn-success">
                                                            <i class="fas fa-cloud-arrow-up me-1"></i> Upload Slip
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            @endif

            <!-- Cross-Store Stock Availability View -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white font-weight-bold py-3 border-0 d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <span class="fw-bold fs-6">
                        <i class="fas fa-warehouse text-warning me-2"></i>Real-time Cross-Store Inventory View
                    </span>
                    <span class="badge bg-light text-dark border small">Real-time Stock vs In-Transfer Breakdown</span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Product</th>
                                    <th>Store Name</th>
                                    <th>Total on Hand</th>
                                    <th>In Transfer</th>
                                    <th>Net Available</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($purchaseRequest->items as $item)
                                    @php $stocks = $stockAvailability[$item->product_id] ?? collect(); @endphp
                                    @forelse($stocks as $st)
                                    <tr>
                                        <td><strong>{{ $item->product->name ?? 'Item #' . $item->product_id }}</strong></td>
                                        <td>{{ $st->store->name ?? 'N/A' }}</td>
                                        <td>{{ number_format($st->quantity_on_hand, 2) }} {{ $item->unit }}</td>
                                        <td>
                                            @if(($st->in_transfer_qty ?? 0) > 0)
                                                <span class="badge bg-info text-dark font-monospace shadow-xs">
                                                    <i class="fas fa-truck-moving me-1"></i>{{ number_format($st->in_transfer_qty, 2) }} {{ $item->unit }}
                                                </span>
                                            @else
                                                <span class="text-muted small">0.00</span>
                                            @endif
                                        </td>
                                        <td>
                                            @if(($st->net_available ?? 0) > 0)
                                                <strong class="text-success font-monospace">{{ number_format($st->net_available, 2) }} {{ $item->unit }}</strong>
                                            @else
                                                <span class="text-muted small fw-semibold">0.00 {{ $item->unit }}</span>
                                            @endif
                                        </td>
                                        <td>
                                            @if(($st->net_available ?? 0) <= 0 && ($st->in_transfer_qty ?? 0) > 0)
                                                <span class="badge bg-info text-dark shadow-xs"><i class="fas fa-truck-moving me-1"></i>In Transfer</span>
                                            @elseif(($st->net_available ?? 0) > 0 && ($st->in_transfer_qty ?? 0) > 0)
                                                <span class="badge bg-success me-1">In Stock</span>
                                                <span class="badge bg-info text-dark">In Transfer</span>
                                            @elseif(($st->net_available ?? 0) > 0)
                                                <span class="badge bg-success">In Stock</span>
                                            @else
                                                <span class="badge bg-secondary">Out of Stock</span>
                                            @endif
                                        </td>
                                    </tr>
                                    @empty
                                    <tr>
                                        <td><strong>{{ $item->product->name ?? 'Item #' . $item->product_id }}</strong></td>
                                        <td colspan="5" class="text-muted italic">No stock available across any stores.</td>
                                    </tr>
                                    @endforelse
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Linked Store Transfers Created from this PR -->
            @if(isset($prTransfers) && $prTransfers->count() > 0)
            <div class="card border-0 shadow-sm mb-4 border-start border-4 border-info">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div>
                        <h6 class="font-weight-bold text-dark mb-0">
                            <i class="fas fa-truck-ramp-box text-info me-2"></i>Store Transfers Created for this PR
                        </h6>
                        <small class="text-muted">Live transfer movements and dispatched items from PR #{{ $purchaseRequest->pr_no }}</small>
                    </div>
                    <span class="badge bg-info text-dark rounded-pill">{{ $prTransfers->count() }} Transfers</span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Transfer #</th>
                                    <th>From Store</th>
                                    <th>To Store</th>
                                    <th>Items Transferred</th>
                                    <th>Status</th>
                                    <th class="text-end">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($prTransfers as $tr)
                                <tr>
                                    <td>
                                        <strong class="font-monospace text-primary">{{ $tr->transfer_no }}</strong>
                                        <br><small class="text-muted">{{ $tr->created_at->format('M d, Y H:i') }}</small>
                                    </td>
                                    <td><span class="badge bg-light text-dark border">{{ $tr->fromStore->name ?? 'N/A' }}</span></td>
                                    <td><span class="badge bg-light text-dark border">{{ $tr->toStore->name ?? 'N/A' }}</span></td>
                                    <td>
                                        @foreach($tr->items as $tItem)
                                            <div class="small">
                                                <strong>{{ $tItem->product->name ?? 'Item' }}</strong>: 
                                                <span class="badge bg-info bg-opacity-10 text-info fw-bold">{{ number_format($tItem->requested_quantity, 1) }} {{ $tItem->unit }}</span>
                                            </div>
                                        @endforeach
                                    </td>
                                    <td>
                                        @php
                                            $trStatusClass = match($tr->status) {
                                                'completed' => 'bg-success',
                                                'in_transit', 'dispatched' => 'bg-primary text-white',
                                                'approved' => 'bg-warning text-dark',
                                                'rejected', 'cancelled' => 'bg-danger',
                                                default => 'bg-secondary',
                                            };
                                        @endphp
                                        <span class="badge {{ $trStatusClass }}">
                                            <i class="fas fa-truck me-1"></i>{{ ucfirst(str_replace('_', ' ', $tr->status)) }}
                                        </span>
                                    </td>
                                    <td class="text-end">
                                        <a href="{{ route('transfers.show', $tr->id) }}" class="btn btn-sm btn-outline-primary shadow-xs" target="_blank">
                                            <i class="fas fa-external-link-alt me-1"></i> View Transfer
                                        </a>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            @endif


            <!-- Audit Trail / Workflow History Logs -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white font-weight-bold py-3 border-0">
                    <i class="fas fa-history text-info me-2"></i>Workflow Audit Trail (Full Hand-off Log)
                </div>
                <div class="card-body p-0">
                    <div class="list-group list-group-flush">
                        @forelse($purchaseRequest->workflowLogs as $log)
                        <div class="list-group-item p-3">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <span class="badge bg-secondary">{{ ucfirst(str_replace('_', ' ', $log->action)) }}</span>
                                <small class="text-muted">{{ $log->created_at->format('M d, Y H:i:s') }}</small>
                            </div>
                            <div class="small">
                                <strong>{{ $log->actor->name }}</strong> ({{ ucfirst(str_replace('_', ' ', $log->actor_role)) }}) moved stage to 
                                <span class="badge bg-info text-dark">{{ \App\Models\PurchaseRequest::statusLabels()[$log->to_stage] ?? $log->to_stage }}</span>
                            </div>
                            @if($log->notes)
                            <div class="small text-muted mt-1 bg-light p-2 rounded">"{{ $log->notes }}"</div>
                            @endif
                        </div>
                        @empty
                        <div class="p-3 text-muted text-center small">No workflow logs recorded yet.</div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- ═════════════════════════════════════════════════════════════════════════ --}}
{{-- MODAL 1: SMART SPLIT ASSISTANT (TRANSFER SOME + PURCHASE SOME)           --}}
{{-- ═════════════════════════════════════════════════════════════════════════ --}}
<div class="modal fade" id="splitAndProcessModal" tabindex="-1" aria-labelledby="splitAndProcessModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-0 shadow">
            <form action="{{ route('purchase-requests.split-and-process', $purchaseRequest) }}" method="POST">
                @csrf
                <div class="modal-header bg-primary text-white py-3">
                    <h5 class="modal-title fw-bold" id="splitAndProcessModalLabel">
                        <i class="fas fa-random me-2"></i>Smart Split: Transfer Some Items & Purchase Some Items
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="alert alert-info border-0 shadow-sm py-2 px-3 mb-3 small d-flex align-items-center">
                        <i class="fas fa-info-circle fa-lg text-info me-2"></i>
                        <div>
                            Review all requested items below. Choose <strong>Transfer</strong> for items in stock at another store, and <strong>Purchase</strong> for items that must be sourced by the Procurement Team.
                        </div>
                    </div>

                    {{-- Destination Store Configuration --}}
                    <div class="row g-3 bg-light p-3 rounded border mb-4">
                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-uppercase text-muted">Destination Store (Project Store) <span class="text-danger">*</span></label>
                            <select name="to_store_id" class="form-select form-select-sm" required>
                                @foreach($stores as $st)
                                    <option value="{{ $st->id }}" {{ $purchaseRequest->store_id == $st->id ? 'selected' : '' }}>
                                        {{ $st->name }} ({{ $st->code }}) @if(isset($st->project) && $st->project) — Project: {{ $st->project->name }} @endif
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-uppercase text-muted">Split Notes / Instructions</label>
                            <input type="text" name="notes" class="form-control form-control-sm" placeholder="e.g., Sourced 4 items from Main Store, 7 items sent for direct purchasing">
                        </div>
                    </div>

                    {{-- Items Allocation Grid --}}
                    <div class="table-responsive">
                        <table class="table table-bordered align-middle mb-0">
                            <thead class="table-light small text-uppercase">
                                <tr>
                                    <th width="30%">Product / Material</th>
                                    <th width="12%" class="text-center">Requested</th>
                                    <th width="18%">Routing Decision</th>
                                    <th width="22%">Transfer Source & Qty</th>
                                    <th width="18%">Purchase Qty</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($purchaseRequest->items as $index => $item)
                                @php
                                    $itemStocks = $stockAvailability[$item->product_id] ?? collect();
                                    $totalStock = $itemStocks->sum('quantity_on_hand');
                                    $bestStore = $itemStocks->where('quantity_on_hand', '>', 0)->sortByDesc('quantity_on_hand')->first();
                                    $defaultAction = ($totalStock >= $item->quantity) ? 'transfer' : 'purchase';
                                @endphp
                                <tr>
                                    <td>
                                        <input type="hidden" name="allocations[{{ $index }}][item_id]" value="{{ $item->id }}">
                                        <strong>{{ $item->product->name ?? 'Item #' . $item->product_id }}</strong>
                                        <div class="small mt-1">
                                            @if($totalStock > 0)
                                                <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25">
                                                    <i class="fas fa-check-circle me-1"></i>Available: {{ number_format($totalStock, 1) }} {{ $item->unit }}
                                                </span>
                                            @else
                                                <span class="badge bg-light text-muted border">No Stock in Stores</span>
                                            @endif
                                        </div>
                                    </td>
                                    <td class="text-center">
                                        <span class="fw-bold text-dark fs-6">{{ number_format($item->quantity, 2) }}</span>
                                        <span class="small text-muted d-block">{{ $item->unit }}</span>
                                    </td>
                                    <td>
                                        <select name="allocations[{{ $index }}][action]" class="form-select form-select-sm split-action-select" 
                                                data-index="{{ $index }}" onchange="toggleSplitRowFields(this, {{ $index }})">
                                            <option value="transfer" {{ $defaultAction === 'transfer' ? 'selected' : '' }}>🚚 Store Transfer</option>
                                            <option value="purchase" {{ $defaultAction === 'purchase' ? 'selected' : '' }}>🛒 Send to Purchase</option>
                                            <option value="keep">⏸ Keep on PR</option>
                                        </select>
                                    </td>
                                    <td>
                                        <div id="transferFieldGroup_{{ $index }}" style="{{ $defaultAction === 'transfer' ? '' : 'display:none;' }}">
                                            <select name="allocations[{{ $index }}][from_store_id]" class="form-select form-select-sm mb-1">
                                                <option value="">-- Choose Source Store --</option>
                                                @foreach($stores as $st)
                                                    @php
                                                        $stStock = $itemStocks->where('store_id', $st->id)->first()->quantity_on_hand ?? 0;
                                                    @endphp
                                                    <option value="{{ $st->id }}" {{ ($bestStore && $bestStore->store_id == $st->id) ? 'selected' : '' }}>
                                                        {{ $st->name }} (Stock: {{ number_format($stStock, 1) }})
                                                    </option>
                                                @endforeach
                                            </select>
                                            <div class="input-group input-group-sm">
                                                <span class="input-group-text">Qty</span>
                                                <input type="number" step="0.001" min="0.001" max="{{ $item->quantity }}" 
                                                       name="allocations[{{ $index }}][transfer_qty]" 
                                                       value="{{ min((float)$item->quantity, $totalStock > 0 ? (float)$totalStock : (float)$item->quantity) }}" 
                                                       class="form-control form-control-sm">
                                                <span class="input-group-text">{{ $item->unit }}</span>
                                            </div>
                                        </div>
                                        <div id="transferDisabledNotice_{{ $index }}" class="text-muted small italic" style="{{ $defaultAction === 'transfer' ? 'display:none;' : '' }}">
                                            —
                                        </div>
                                    </td>
                                    <td>
                                        <div id="purchaseFieldGroup_{{ $index }}" style="{{ $defaultAction === 'purchase' ? '' : 'display:none;' }}">
                                            <div class="input-group input-group-sm">
                                                <input type="number" step="0.001" min="0.001" max="{{ $item->quantity }}" 
                                                       name="allocations[{{ $index }}][purchase_qty]" 
                                                       value="{{ (float)$item->quantity }}" 
                                                       class="form-control form-control-sm">
                                                <span class="input-group-text">{{ $item->unit }}</span>
                                            </div>
                                        </div>
                                        <div id="purchaseDisabledNotice_{{ $index }}" class="text-muted small italic" style="{{ $defaultAction === 'purchase' ? 'display:none;' : '' }}">
                                            —
                                        </div>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer bg-light border-top d-flex justify-content-between">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm fw-bold px-4">
                        <i class="fas fa-check-circle me-1"></i> Execute Split: Create Transfer & Route Purchase
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- ═════════════════════════════════════════════════════════════════════════ --}}
{{-- MODAL 2: INITIATE STORE TRANSFER FOR SELECTED ITEMS                       --}}
{{-- ═════════════════════════════════════════════════════════════════════════ --}}
<div class="modal fade" id="selectiveTransferModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <form action="{{ \Illuminate\Support\Facades\Route::has('purchase-requests.selective-transfer') ? route('purchase-requests.selective-transfer', $purchaseRequest) : url('/purchase-requests/' . $purchaseRequest->id . '/selective-transfer') }}" method="POST">
                @csrf
                <div class="modal-header bg-info text-white py-3">
                    <h5 class="modal-title fw-bold">
                        <i class="fas fa-truck-ramp-box me-2"></i>Create Store Transfer for Selected Items
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-uppercase">From Source Store <span class="text-danger">*</span></label>
                            <select name="from_store_id" id="selTransferFromStore" class="form-select form-select-sm" required>
                                <option value="">-- Choose Source Store (Where stock is located) --</option>
                                @foreach($stores as $st)
                                    <option value="{{ $st->id }}">{{ $st->name }} ({{ $st->code }})</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-uppercase">To Destination Store <span class="text-danger">*</span></label>
                            <select name="to_store_id" id="selTransferToStore" class="form-select form-select-sm" required>
                                @foreach($stores as $st)
                                    <option value="{{ $st->id }}" {{ $purchaseRequest->store_id == $st->id ? 'selected' : '' }}>
                                        {{ $st->name }} ({{ $st->code }})
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-uppercase">Required Delivery Date</label>
                            <input type="date" name="required_date" class="form-control form-control-sm" value="{{ optional($purchaseRequest->required_date)->format('Y-m-d') ?? date('Y-m-d') }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-uppercase">Transfer Reason / Remarks</label>
                            <input type="text" name="reason" class="form-control form-control-sm" value="Store Transfer from PR #{{ $purchaseRequest->pr_no }} ({{ $purchaseRequest->project->name ?? 'Project' }})">
                        </div>
                    </div>

                    <h6 class="fw-bold border-bottom pb-2 mb-2">Selected Items to Transfer:</h6>
                    <div class="table-responsive border rounded">
                        <table class="table table-sm align-middle mb-0" id="selTransferItemsTable">
                            <thead class="table-light small">
                                <tr>
                                    <th>Product</th>
                                    <th width="30%">Transfer Qty</th>
                                    <th>Unit</th>
                                </tr>
                            </thead>
                            <tbody id="selTransferItemsTbody">
                                {{-- Dynamically populated via JS --}}
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer bg-light border-top d-flex justify-content-between">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-info text-white btn-sm fw-bold px-4">
                        <i class="fas fa-exchange-alt me-1"></i> Confirm & Create Store Transfer
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- ═════════════════════════════════════════════════════════════════════════ --}}
{{-- MODAL 3: SEND SELECTED ITEMS TO PROCUREMENT MANAGER                       --}}
{{-- ═════════════════════════════════════════════════════════════════════════ --}}
<div class="modal fade" id="selectiveSendPmModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-md modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <form action="{{ \Illuminate\Support\Facades\Route::has('purchase-requests.selective-send-to-pm') ? route('purchase-requests.selective-send-to-pm', $purchaseRequest) : url('/purchase-requests/' . $purchaseRequest->id . '/selective-send-to-pm') }}" method="POST">
                @csrf
                <div class="modal-header bg-success text-white py-3">
                    <h5 class="modal-title fw-bold">
                        <i class="fas fa-cart-shopping me-2"></i>Send Selected Items to Purchase Team
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <p class="small text-muted mb-3">
                        The items selected below will be forwarded to the <strong>Procurement Manager</strong> for market pricing, sourcing, and purchasing.
                    </p>

                    <h6 class="fw-bold border-bottom pb-2 mb-2">Selected Items:</h6>
                    <div id="selSendPmItemsList" class="bg-light p-2 rounded border mb-3 small" style="max-height: 180px; overflow-y: auto;">
                        {{-- Dynamically populated via JS --}}
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold small text-uppercase">Notes / Instructions for Purchase Team</label>
                        <textarea name="notes" class="form-control form-control-sm" rows="3" placeholder="Add specific procurement instructions, delivery urgency, or remarks..."></textarea>
                    </div>
                </div>
                <div class="modal-footer bg-light border-top d-flex justify-content-between">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success btn-sm fw-bold px-4">
                        <i class="fas fa-paper-plane me-1"></i> Send to Procurement Manager
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- ═════════════════════════════════════════════════════════════════════════ --}}
{{-- MODAL 3B: PM SEND SELECTED ITEMS BACK TO STORE MANAGER                     --}}
{{-- ═════════════════════════════════════════════════════════════════════════ --}}
<div class="modal fade" id="pmSendBackStoreModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <form action="{{ \Illuminate\Support\Facades\Route::has('purchase-requests.selective-send-back-to-store') ? route('purchase-requests.selective-send-back-to-store', $purchaseRequest) : url('/purchase-requests/' . $purchaseRequest->id . '/selective-send-back-to-store') }}" method="POST">
                @csrf
                <div class="modal-header bg-warning text-dark py-3">
                    <h5 class="modal-title fw-bold">
                        <i class="fas fa-undo me-2"></i>Send Selected Items to Store Manager
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="alert alert-info border-0 shadow-sm d-flex align-items-center mb-3">
                        <i class="fas fa-info-circle fa-2x me-3 flex-shrink-0 text-info"></i>
                        <div class="small">
                            The selected items will be forwarded/returned to the <strong>Store Manager</strong> with status <strong>Pending Store Review</strong>. 
                            The Store Manager will review cross-store inventory to issue Store Transfers or decide how to fulfill/purchase these items.
                            <em>Unselected items will remain with you in PR #{{ $purchaseRequest->pr_no }}.</em>
                        </div>
                    </div>

                    <h6 class="fw-bold border-bottom pb-2 mb-2">Selected Items:</h6>
                    <div id="pmSendBackItemsList" class="bg-light p-2 rounded border mb-3 small" style="max-height: 200px; overflow-y: auto;">
                        {{-- Dynamically populated via JS --}}
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold small text-uppercase">
                            Reason / Instructions for Store Manager <span class="text-danger">*</span>
                        </label>
                        <textarea name="reason" class="form-control form-control-sm" rows="3" 
                                  placeholder="e.g. Items are available in Chafe / Main Store. Please fulfill via Store Transfer or review stock..." required></textarea>
                    </div>
                </div>
                <div class="modal-footer bg-light border-top d-flex justify-content-between">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning text-dark btn-sm fw-bold px-4 shadow-sm">
                        <i class="fas fa-paper-plane me-1"></i> Confirm & Send to Store Manager
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- ═════════════════════════════════════════════════════════════════════════ --}}
{{-- MODAL 3C: PM SEND SELECTED ITEMS FOR DIRECT BUY                            --}}
{{-- ═════════════════════════════════════════════════════════════════════════ --}}
<div class="modal fade" id="pmSelectiveDirectBuyModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-md modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <form action="{{ \Illuminate\Support\Facades\Route::has('purchase-requests.selective-send-to-proc-team') ? route('purchase-requests.selective-send-to-proc-team', $purchaseRequest) : url('/purchase-requests/' . $purchaseRequest->id . '/selective-send-to-proc-team') }}" method="POST">
                @csrf
                <input type="hidden" name="sourcing_method" value="direct_buy">
                <div class="modal-header bg-success text-white py-3">
                    <h5 class="modal-title fw-bold">
                        <i class="fas fa-bolt me-2"></i>Send Selected Items for Direct Buy
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <p class="small text-muted mb-3">
                        The selected items will be assigned to the Purchase Team for <strong>Direct Buy</strong> to input immediate material prices.
                    </p>

                    <h6 class="fw-bold border-bottom pb-2 mb-2">Selected Items:</h6>
                    <div id="pmDirectBuyItemsList" class="bg-light p-2 rounded border mb-3 small" style="max-height: 180px; overflow-y: auto;">
                        {{-- Dynamically populated via JS --}}
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold small text-uppercase">Instructions / Notes for Purchase Team</label>
                        <textarea name="notes" class="form-control form-control-sm" rows="3" placeholder="Add specific procurement instructions, urgency, or supplier notes..."></textarea>
                    </div>
                </div>
                <div class="modal-footer bg-light border-top d-flex justify-content-between">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success btn-sm fw-bold px-4 shadow-sm">
                        <i class="fas fa-paper-plane me-1"></i> Send for Direct Buy
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- ═════════════════════════════════════════════════════════════════════════ --}}
{{-- MODAL 3D: PM SEND SELECTED ITEMS FOR PROFORMA SOURCING                    --}}
{{-- ═════════════════════════════════════════════════════════════════════════ --}}
<div class="modal fade" id="pmSelectiveProformaModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-md modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <form action="{{ \Illuminate\Support\Facades\Route::has('purchase-requests.selective-send-to-proc-team') ? route('purchase-requests.selective-send-to-proc-team', $purchaseRequest) : url('/purchase-requests/' . $purchaseRequest->id . '/selective-send-to-proc-team') }}" method="POST">
                @csrf
                <input type="hidden" name="sourcing_method" value="proforma">
                <div class="modal-header bg-primary text-white py-3">
                    <h5 class="modal-title fw-bold">
                        <i class="fas fa-file-invoice-dollar me-2"></i>Send Selected Items for Proforma Quotes
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <p class="small text-muted mb-3">
                        The selected items will be assigned to the Purchase Team to collect and attach formal <strong>Proforma Invoices / Quotes</strong> from vendors.
                    </p>

                    <h6 class="fw-bold border-bottom pb-2 mb-2">Selected Items:</h6>
                    <div id="pmProformaItemsList" class="bg-light p-2 rounded border mb-3 small" style="max-height: 180px; overflow-y: auto;">
                        {{-- Dynamically populated via JS --}}
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold small text-uppercase">Instructions / Notes for Purchase Team</label>
                        <textarea name="notes" class="form-control form-control-sm" rows="3" placeholder="Add specific requirements, minimum quotes needed, etc..."></textarea>
                    </div>
                </div>
                <div class="modal-footer bg-light border-top d-flex justify-content-between">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm fw-bold px-4 shadow-sm">
                        <i class="fas fa-paper-plane me-1"></i> Send for Proforma Sourcing
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- ═════════════════════════════════════════════════════════════════════════ --}}
{{-- MODAL 3E: PROCUREMENT TEAM SELECTIVE DIRECT BUY PRICING                   --}}
{{-- ═════════════════════════════════════════════════════════════════════════ --}}
<div class="modal fade" id="procTeamSelectiveDirectBuyModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <form action="{{ route('purchase-requests.proc-team-selective-submit', $purchaseRequest) }}" method="POST">
                @csrf
                <input type="hidden" name="sourcing_method" value="direct_buy">
                <div class="modal-header bg-success text-white py-3">
                    <h5 class="modal-title fw-bold">
                        <i class="fas fa-bolt me-2"></i>Direct Buy: Submit Selected Material Prices
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="alert alert-info py-2 px-3 small mb-3 border-start border-4 border-info">
                        <i class="fas fa-info-circle me-1"></i>
                        The selected materials will be submitted for price review. If only some items are selected, they will be split into a new PR and advanced forward, while remaining items stay with the Procurement Team.
                    </div>

                    <h6 class="fw-bold border-bottom pb-2 mb-2">Selected Items &amp; Prices:</h6>
                    <div class="table-responsive bg-light p-2 rounded border mb-3" style="max-height: 260px; overflow-y: auto;">
                        <table class="table table-sm align-middle mb-0" style="font-size: 0.88rem;">
                            <thead class="table-light sticky-top">
                                <tr>
                                    <th class="ps-2">Material / Product</th>
                                    <th width="24%">Purchased Qty</th>
                                    <th width="28%">Unit Price (ETB)</th>
                                    <th width="26%" class="pe-2 text-end">Line Total</th>
                                </tr>
                            </thead>
                            <tbody id="procTeamDirectBuyItemsContainer">
                                {{-- Populated via JS --}}
                            </tbody>
                        </table>
                    </div>

                    {{-- Modal Calculations Summary Card --}}
                    <div class="card border border-success border-opacity-25 bg-white mb-3 shadow-xs">
                        <div class="card-body p-2">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <span class="small text-muted fw-bold text-uppercase" style="font-size: 11px;">Selected Items Subtotal:</span>
                                <span class="fw-bold text-dark font-monospace" id="procTeamDirectBuySubtotalDisplay">0.00 ETB</span>
                            </div>

                            {{-- 15% VAT Option --}}
                            <div class="d-flex justify-content-between align-items-center py-1 border-top border-bottom my-1">
                                <div class="form-check form-switch mb-0">
                                    <input class="form-check-input cursor-pointer" type="checkbox" id="procTeamDirectBuyVatToggle" name="include_vat" value="1" onchange="recalculateProcTeamDirectBuyTotal()">
                                    <label class="form-check-label small fw-bold text-dark cursor-pointer" for="procTeamDirectBuyVatToggle" style="font-size: 12px;">
                                        Add 15% VAT
                                    </label>
                                </div>
                                <span class="small font-monospace text-muted" id="procTeamDirectBuyVatDisplay">+0.00 ETB</span>
                            </div>

                            {{-- Grand Total Money Box --}}
                            <div class="p-2 rounded bg-success bg-opacity-10 border border-success border-opacity-25 mt-2">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <label class="form-label small fw-bold text-uppercase text-success mb-0" style="font-size: 11px;">
                                        <i class="fas fa-money-bill-wave me-1"></i> Total Purchased Amount Money (ETB)
                                    </label>
                                    <button type="button" class="btn btn-link btn-xs p-0 text-decoration-none text-success fw-bold" onclick="recalculateProcTeamDirectBuyTotal()" title="Recalculate exact total">
                                        <i class="fas fa-rotate me-1"></i>Sync
                                    </button>
                                </div>
                                <div class="input-group input-group-sm">
                                    <span class="input-group-text fw-bold bg-success text-white">ETB</span>
                                    <input type="number" step="0.01" min="0" name="amount" id="procTeamDirectBuyTotalAmount" class="form-control form-control-sm fw-bold bg-white text-success fs-5 font-monospace" required placeholder="0.00">
                                </div>
                                <div class="d-flex justify-content-between align-items-center mt-1">
                                    <span class="text-muted" style="font-size: 10px;">Total Money to Disburse:</span>
                                    <span class="fw-bold text-success font-monospace" id="procTeamDirectBuyTotalMoneyDisplay" style="font-size: 13px;">0.00 ETB</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-uppercase">Procurement Notes / Remarks</label>
                        <textarea name="notes" class="form-control form-control-sm" rows="2" placeholder="Add remarks or vendor pricing notes..."></textarea>
                    </div>
                </div>
                <div class="modal-footer bg-light border-top d-flex justify-content-between">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success btn-sm fw-bold px-4 shadow-sm">
                        <i class="fas fa-check-circle me-1"></i> Submit Selected Prices
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- ═════════════════════════════════════════════════════════════════════════ --}}
{{-- MODAL 3F: PROCUREMENT TEAM SELECTIVE PROFORMA SUBMIT                      --}}
{{-- ═════════════════════════════════════════════════════════════════════════ --}}
<div class="modal fade" id="procTeamSelectiveProformaModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-md modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <form action="{{ route('purchase-requests.proc-team-selective-submit', $purchaseRequest) }}" method="POST">
                @csrf
                <input type="hidden" name="sourcing_method" value="proforma">
                <div class="modal-header bg-primary text-white py-3">
                    <h5 class="modal-title fw-bold">
                        <i class="fas fa-paper-plane me-2"></i>Submit Selected Proforma Quotes to PM
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="alert alert-info py-2 px-3 small mb-3 border-start border-4 border-info">
                        <i class="fas fa-info-circle me-1"></i>
                        The selected materials and attached proformas will be submitted to the Procurement Manager for review. Any unselected items will remain with the Procurement Team.
                    </div>

                    <h6 class="fw-bold border-bottom pb-2 mb-2">Selected Items (<span class="selected-items-count">0</span>):</h6>
                    <div id="procTeamProformaItemsContainer" class="bg-light p-2 rounded border mb-3 small" style="max-height: 180px; overflow-y: auto;">
                        {{-- Populated via JS --}}
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold text-uppercase">Procurement Team Notes</label>
                        <textarea name="notes" class="form-control form-control-sm" rows="3" placeholder="Optional notes regarding attached proformas for selected items..."></textarea>
                    </div>
                </div>
                <div class="modal-footer bg-light border-top d-flex justify-content-between">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm fw-bold px-4 shadow-sm">
                        <i class="fas fa-paper-plane me-1"></i> Submit Selected to PM
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- ═════════════════════════════════════════════════════════════════════════ --}}
{{-- MODAL 3G: PROCUREMENT TEAM SEND SELECTED BACK TO PM                       --}}
{{-- ═════════════════════════════════════════════════════════════════════════ --}}
<div class="modal fade" id="procTeamSendBackPmModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-md modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <form action="{{ route('purchase-requests.proc-team-selective-send-back-pm', $purchaseRequest) }}" method="POST">
                @csrf
                <div class="modal-header bg-warning text-dark py-3">
                    <h5 class="modal-title fw-bold">
                        <i class="fas fa-undo me-2"></i>Return Selected Items to Procurement Manager
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="alert alert-warning py-2 px-3 small mb-3 border-start border-4 border-warning">
                        <i class="fas fa-exclamation-triangle me-1"></i>
                        The selected item(s) will be returned to the Procurement Manager (e.g. for method re-evaluation, missing specs, or un-sourceable materials).
                    </div>

                    <h6 class="fw-bold border-bottom pb-2 mb-2">Items to Return (<span class="selected-items-count">0</span>):</h6>
                    <div id="procTeamSendBackItemsContainer" class="bg-light p-2 rounded border mb-3 small" style="max-height: 180px; overflow-y: auto;">
                        {{-- Populated via JS --}}
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold small text-uppercase">Reason for Returning to PM <span class="text-danger">*</span></label>
                        <textarea name="reason" class="form-control form-control-sm" rows="3" placeholder="Explain why these items are being returned (e.g. out of market stock, supplier unavailable, method change needed)..." required></textarea>
                    </div>
                </div>
                <div class="modal-footer bg-light border-top d-flex justify-content-between">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning text-dark btn-sm fw-bold px-4 shadow-sm">
                        <i class="fas fa-undo me-1"></i> Return Selected to PM
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- ═════════════════════════════════════════════════════════════════════════ --}}
{{-- MODAL 4: ATTACH PROFORMA INVOICE QUOTE                                    --}}
{{-- ═════════════════════════════════════════════════════════════════════════ --}}
<div class="modal fade" id="attachProformaModal" tabindex="-1" aria-labelledby="attachProformaModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <form action="{{ \Illuminate\Support\Facades\Route::has('purchase-requests.attach-proforma') ? route('purchase-requests.attach-proforma', $purchaseRequest) : url('/purchase-requests/' . $purchaseRequest->id . '/attach-proforma') }}" method="POST" enctype="multipart/form-data">
                @csrf
                <div class="modal-header bg-primary text-white py-3">
                    <h5 class="modal-title fw-bold" id="attachProformaModalLabel">
                        <i class="fas fa-file-invoice-dollar me-2"></i>Attach Supplier Proforma Quote
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    {{-- Quick Supplier Add / Edit Panel --}}
                    <div id="supplierQuickPanel" class="card border border-primary border-opacity-25 bg-light mb-3 shadow-sm" style="display: none;">
                        <div class="card-header bg-primary bg-opacity-10 py-2 px-3 d-flex justify-content-between align-items-center">
                            <span class="fw-bold small text-primary" id="supplierQuickPanelTitle">
                                <i class="fas fa-building-circle-check me-1"></i> Add New Supplier / Vendor
                            </span>
                            <button type="button" class="btn-close btn-sm" onclick="toggleSupplierQuickForm(false)" aria-label="Close"></button>
                        </div>
                        <div class="card-body p-3">
                            <input type="hidden" id="quickSupId" value="">
                            <div class="row g-2">
                                <div class="col-md-6">
                                    <label class="form-label small fw-bold mb-1">Company / Supplier Name <span class="text-danger">*</span></label>
                                    <input type="text" id="quickSupName" class="form-control form-control-sm" placeholder="e.g. ABC Building Materials Ltd.">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small fw-bold mb-1">TIN / Tax Identification No.</label>
                                    <input type="text" id="quickSupTaxId" class="form-control form-control-sm" placeholder="e.g. 0012345678">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label small fw-bold mb-1">Phone Number</label>
                                    <input type="text" id="quickSupPhone" class="form-control form-control-sm" placeholder="e.g. +251 911 000 000">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label small fw-bold mb-1">Email Address</label>
                                    <input type="email" id="quickSupEmail" class="form-control form-control-sm" placeholder="supplier@example.com">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label small fw-bold mb-1">Contact Person</label>
                                    <input type="text" id="quickSupContact" class="form-control form-control-sm" placeholder="Representative name">
                                </div>
                                <div class="col-12">
                                    <label class="form-label small fw-bold mb-1">Address / Location</label>
                                    <input type="text" id="quickSupAddress" class="form-control form-control-sm" placeholder="City, Subcity, Street / Warehouse location">
                                </div>
                            </div>
                            <div class="mt-3 d-flex justify-content-between align-items-center">
                                <div id="quickSupErrorMsg" class="text-danger small fw-bold"></div>
                                <div class="d-flex gap-2">
                                    <button type="button" class="btn btn-sm btn-secondary" onclick="toggleSupplierQuickForm(false)">Cancel</button>
                                    <button type="button" class="btn btn-sm btn-success fw-bold shadow-sm" id="btnSaveQuickSupplier" onclick="submitQuickSupplier()">
                                        <i class="fas fa-check me-1"></i> Save & Select Supplier
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row g-3">
                        <div class="col-12">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <label class="form-label fw-bold small text-uppercase mb-0">
                                    Select Supplier / Vendor <span class="text-danger">*</span>
                                </label>
                                <div class="btn-group btn-group-sm">
                                    <button type="button" class="btn btn-sm btn-outline-primary py-0 px-2 fw-bold" onclick="toggleSupplierQuickForm('create')">
                                        <i class="fas fa-plus-circle me-1"></i> + New Supplier
                                    </button>
                                    <button type="button" class="btn btn-sm btn-outline-secondary py-0 px-2" id="btnEditSelectedSupplier" style="display: none;" onclick="toggleSupplierQuickForm('edit')">
                                        <i class="fas fa-pen me-1"></i> Edit Supplier
                                    </button>
                                </div>
                            </div>
                            <select name="supplier_id" id="proformaSupplierSelect" class="form-select form-select-sm" required onchange="handleSupplierSelectChange(this)">
                                <option value="">-- Choose Supplier --</option>
                                @foreach($suppliers as $sup)
                                    <option value="{{ $sup->id }}"
                                            data-name="{{ $sup->name }}"
                                            data-tax-id="{{ $sup->tax_id }}"
                                            data-phone="{{ $sup->phone }}"
                                            data-email="{{ $sup->email }}"
                                            data-contact="{{ $sup->contact_person }}"
                                            data-address="{{ $sup->address }}">{{ $sup->name }} @if($sup->tax_id) (TIN: {{ $sup->tax_id }}) @elseif($sup->phone) (Tel: {{ $sup->phone }}) @endif</option>
                                @endforeach
                            </select>
                            <div id="supplierInfoPreview" class="small text-muted mt-1" style="display: none;"></div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-uppercase">Proforma / Quote Ref No.</label>
                            <input type="text" name="proforma_no" class="form-control form-control-sm" placeholder="e.g. PI-2026-088 (auto-generated if blank)">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-uppercase">Proforma Date <span class="text-danger">*</span></label>
                            <input type="date" name="proforma_date" class="form-control form-control-sm" value="{{ date('Y-m-d') }}" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-uppercase">Quote Validity Until</label>
                            <input type="date" name="valid_until" class="form-control form-control-sm" value="{{ date('Y-m-d', strtotime('+15 days')) }}">
                        </div>

                        {{-- Item-by-item material price inputs --}}
                        <div class="col-12">
                            <div class="card border border-primary border-opacity-25 bg-light shadow-sm">
                                <div class="card-header bg-primary bg-opacity-10 py-2 px-3 d-flex justify-content-between align-items-center">
                                    <span class="fw-bold small text-primary">
                                        <i class="fas fa-boxes-stacked me-1"></i> Quoted Material Prices (Item-by-Item Breakdown)
                                    </span>
                                    <span class="badge bg-primary rounded-pill">{{ $purchaseRequest->items->count() }} items</span>
                                </div>
                                <div class="card-body p-0">
                                    <div class="table-responsive" style="max-height: 260px; overflow-y: auto;">
                                        <table class="table table-sm align-middle mb-0" style="font-size: 0.86rem;">
                                            <thead class="table-light sticky-top">
                                                <tr>
                                                    <th class="ps-3">Material / Product</th>
                                                    <th width="18%">Quantity</th>
                                                    <th width="30%">Quoted Unit Price (ETB)</th>
                                                    <th width="24%" class="pe-3 text-end">Line Total (ETB)</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach($purchaseRequest->items as $itm)
                                                @php
                                                    $initPrice = $itm->estimated_unit_cost > 0 ? (float)$itm->estimated_unit_cost : 0;
                                                    $initTotal = $initPrice * (float)$itm->quantity;
                                                @endphp
                                                <tr>
                                                    <td class="ps-3">
                                                        <div class="fw-bold text-dark">{{ $itm->product->name ?? ('Item #' . $itm->product_id) }}</div>
                                                        @if($itm->product->code)
                                                            <code class="small text-muted" style="font-size: 11px;">{{ $itm->product->code }}</code>
                                                        @endif
                                                    </td>
                                                    <td>
                                                        <span class="badge bg-secondary">{{ number_format($itm->quantity, 2) }} {{ $itm->unit }}</span>
                                                    </td>
                                                    <td>
                                                        <div class="input-group input-group-sm">
                                                            <input type="number" step="0.01" min="0" 
                                                                   name="item_prices[{{ $itm->id }}]" 
                                                                   class="form-control form-control-sm proforma-line-price" 
                                                                   data-qty="{{ (float)$itm->quantity }}" 
                                                                   data-item-id="{{ $itm->id }}" 
                                                                   value="{{ $initPrice > 0 ? $initPrice : '' }}"
                                                                   oninput="recalculateProformaModalTotal()" 
                                                                   placeholder="0.00">
                                                            <span class="input-group-text py-0">ETB</span>
                                                        </div>
                                                    </td>
                                                    <td class="pe-3 text-end">
                                                        <span class="fw-bold text-primary proforma-line-total" id="profLineTotal_{{ $itm->id }}">
                                                            {{ number_format($initTotal, 2) }} ETB
                                                        </span>
                                                    </td>
                                                </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                    <div class="p-3 bg-white border-top">
                                        <div class="row g-2 align-items-center">
                                            <div class="col-md-4">
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <span class="small text-muted text-uppercase fw-semibold">Materials Subtotal:</span>
                                                    <input type="hidden" name="subtotal" id="profModalSubtotalInput" value="0.00">
                                                    <span id="profModalSubtotal" class="fw-bold text-dark">0.00 ETB</span>
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="input-group input-group-sm">
                                                    <span class="input-group-text">VAT / Tax</span>
                                                    <input type="number" step="0.01" min="0" name="tax_amount" id="profModalTaxAmount" class="form-control form-control-sm text-end" value="0.00" oninput="recalculateProformaModalTotal()">
                                                    <button type="button" class="btn btn-outline-secondary btn-sm" onclick="applyProformaVat(15)" title="Apply 15% VAT">+15%</button>
                                                    <button type="button" class="btn btn-outline-secondary btn-sm" onclick="applyProformaVat(0)" title="0% / No VAT">0%</button>
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="input-group input-group-sm">
                                                    <span class="input-group-text fw-bold bg-primary text-white">Grand Total</span>
                                                    <input type="number" step="0.01" min="0.01" name="grand_total" id="profModalGrandTotal" class="form-control form-control-sm fw-bold text-primary bg-white text-end fs-6" required placeholder="0.00">
                                                    <span class="input-group-text">ETB</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-bold small text-uppercase">Proforma Document (PDF / Image) <span class="text-danger">*</span></label>
                            <input type="file" name="proforma_file" class="form-control form-control-sm" accept=".pdf,.jpg,.jpeg,.png,.webp" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-bold small text-uppercase">Notes / Terms (Optional)</label>
                            <textarea name="notes" class="form-control form-control-sm" rows="2" placeholder="Payment terms, delivery timeframe, warranty or quotation notes..."></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light border-top d-flex justify-content-between">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm fw-bold px-4 shadow-sm">
                        <i class="fas fa-check-circle me-1"></i> Attach Proforma Quote
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function selectGmMainDecision(mode) {
    const cardPay = document.getElementById('gmCardPayFinance');
    const cardCredit = document.getElementById('gmCardBuyCredit');
    const radioPay = document.getElementById('gmRadioPayFinance');
    const radioCredit = document.getElementById('gmRadioBuyCredit');
    const hiddenDecision = document.getElementById('gmDecisionHidden');
    const hiddenPayment = document.getElementById('gmPaymentMethodHidden');
    const btnSendBack = document.getElementById('btnGmSecondarySendBack');
    const btnReject = document.getElementById('btnGmSecondaryReject');

    if (btnSendBack) {
        btnSendBack.classList.remove('active', 'btn-warning');
        btnSendBack.classList.add('btn-outline-warning');
    }
    if (btnReject) {
        btnReject.classList.remove('active', 'btn-danger');
        btnReject.classList.add('btn-outline-danger');
    }

    if (mode === 'buy_by_credit') {
        if (hiddenDecision) hiddenDecision.value = 'buy_by_credit';
        if (hiddenPayment) hiddenPayment.value = 'buy_by_credit';
        if (radioCredit) radioCredit.checked = true;
        if (radioPay) radioPay.checked = false;

        if (cardCredit) {
            cardCredit.style.borderColor = '#0d6efd';
            cardCredit.style.backgroundColor = 'rgba(13, 110, 253, 0.08)';
            cardCredit.classList.add('border-2');
        }
        if (cardPay) {
            cardPay.style.borderColor = '#dee2e6';
            cardPay.style.backgroundColor = '#ffffff';
            cardPay.classList.remove('border-2');
        }

        // Set all item radios to credit
        setAllItemPaymentMethods('buy_by_credit', false);
    } else {
        if (hiddenDecision) hiddenDecision.value = 'pay_and_buy';
        if (hiddenPayment) hiddenPayment.value = 'pay_and_buy';
        if (radioPay) radioPay.checked = true;
        if (radioCredit) radioCredit.checked = false;

        if (cardPay) {
            cardPay.style.borderColor = '#198754';
            cardPay.style.backgroundColor = 'rgba(25, 135, 84, 0.08)';
            cardPay.classList.add('border-2');
        }
        if (cardCredit) {
            cardCredit.style.borderColor = '#dee2e6';
            cardCredit.style.backgroundColor = '#ffffff';
            cardCredit.classList.remove('border-2');
        }

        // Set all item radios to pay_and_buy
        setAllItemPaymentMethods('pay_and_buy', false);
    }

    updateGmDecisionAlertsAndSubmit();
}

function selectGmSecondaryDecision(type) {
    const cardPay = document.getElementById('gmCardPayFinance');
    const cardCredit = document.getElementById('gmCardBuyCredit');
    const radioPay = document.getElementById('gmRadioPayFinance');
    const radioCredit = document.getElementById('gmRadioBuyCredit');
    const hiddenDecision = document.getElementById('gmDecisionHidden');
    const hiddenPayment = document.getElementById('gmPaymentMethodHidden');
    const btnSendBack = document.getElementById('btnGmSecondarySendBack');
    const btnReject = document.getElementById('btnGmSecondaryReject');

    if (radioPay) radioPay.checked = false;
    if (radioCredit) radioCredit.checked = false;
    if (cardPay) {
        cardPay.style.borderColor = '#dee2e6';
        cardPay.style.backgroundColor = '#ffffff';
        cardPay.classList.remove('border-2');
    }
    if (cardCredit) {
        cardCredit.style.borderColor = '#dee2e6';
        cardCredit.style.backgroundColor = '#ffffff';
        cardCredit.classList.remove('border-2');
    }

    if (type === 'send_back') {
        if (hiddenDecision) hiddenDecision.value = 'send_back';
        if (hiddenPayment) hiddenPayment.value = '';
        if (btnSendBack) {
            btnSendBack.classList.add('active', 'btn-warning');
            btnSendBack.classList.remove('btn-outline-warning');
        }
        if (btnReject) {
            btnReject.classList.remove('active', 'btn-danger');
            btnReject.classList.add('btn-outline-danger');
        }
    } else if (type === 'reject') {
        if (hiddenDecision) hiddenDecision.value = 'reject';
        if (hiddenPayment) hiddenPayment.value = '';
        if (btnReject) {
            btnReject.classList.add('active', 'btn-danger');
            btnReject.classList.remove('btn-outline-danger');
        }
        if (btnSendBack) {
            btnSendBack.classList.remove('active', 'btn-warning');
            btnSendBack.classList.add('btn-outline-warning');
        }
    }

    updateGmDecisionAlertsAndSubmit();
}

function setAllItemPaymentMethods(method, shouldUpdateMain = true) {
    const radios = document.querySelectorAll('.gm-item-radio[value="' + method + '"]');
    radios.forEach(r => {
        r.checked = true;
    });
    recalculateItemAllocations(false);
    if (shouldUpdateMain) {
        selectGmMainDecision(method);
    }
}

function onItemPaymentMethodChanged() {
    recalculateItemAllocations(true);
}

function recalculateItemAllocations(allowSplitDetect = true) {
    const rows = document.querySelectorAll('.gm-item-allocation-row');
    let financeSum = 0;
    let creditSum = 0;
    let financeCount = 0;
    let creditCount = 0;

    rows.forEach(row => {
        const amount = parseFloat(row.getAttribute('data-amount') || 0);
        const radio = row.querySelector('.gm-item-radio:checked');
        const val = radio ? radio.value : 'pay_and_buy';

        if (val === 'buy_by_credit') {
            creditSum += amount;
            creditCount++;
        } else {
            financeSum += amount;
            financeCount++;
        }
    });

    const badgeFinance = document.getElementById('gmFinancePortionBadge');
    const badgeCredit = document.getElementById('gmCreditPortionBadge');
    const badgeType = document.getElementById('gmSplitStatusBadge') || document.getElementById('gmAllocationTypeBadge');

    if (badgeFinance) badgeFinance.textContent = financeSum.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' ETB';
    if (badgeCredit) badgeCredit.textContent = creditSum.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' ETB';

    const hiddenDecision = document.getElementById('gmDecisionHidden');
    const hiddenPayment = document.getElementById('gmPaymentMethodHidden');
    const cardPay = document.getElementById('gmCardPayFinance');
    const cardCredit = document.getElementById('gmCardBuyCredit');
    const radioPay = document.getElementById('gmRadioPayFinance');
    const radioCredit = document.getElementById('gmRadioBuyCredit');

    // Only auto-update decision if not in send_back or reject mode
    if (hiddenDecision && hiddenDecision.value !== 'send_back' && hiddenDecision.value !== 'reject') {
        if (financeCount > 0 && creditCount > 0) {
            if (badgeType) {
                badgeType.className = 'badge bg-warning-subtle text-dark border border-warning';
                badgeType.textContent = 'Split (' + financeCount + ' Fin / ' + creditCount + ' Cred)';
            }
            if (hiddenDecision) hiddenDecision.value = 'split';
            if (hiddenPayment) hiddenPayment.value = 'split';

            if (radioPay) radioPay.checked = false;
            if (radioCredit) radioCredit.checked = false;
            if (cardPay) {
                cardPay.style.borderColor = '#198754';
                cardPay.style.backgroundColor = 'rgba(25, 135, 84, 0.04)';
                cardPay.classList.add('border-2');
            }
            if (cardCredit) {
                cardCredit.style.borderColor = '#0d6efd';
                cardCredit.style.backgroundColor = 'rgba(13, 110, 253, 0.04)';
                cardCredit.classList.add('border-2');
            }
        } else if (creditCount > 0 && financeCount === 0) {
            if (badgeType) {
                badgeType.className = 'badge bg-primary-subtle text-primary border border-primary';
                badgeType.textContent = 'All Credit (' + creditCount + ' Items)';
            }
            if (hiddenDecision) hiddenDecision.value = 'buy_by_credit';
            if (hiddenPayment) hiddenPayment.value = 'buy_by_credit';

            if (radioCredit) radioCredit.checked = true;
            if (radioPay) radioPay.checked = false;
            if (cardCredit) {
                cardCredit.style.borderColor = '#0d6efd';
                cardCredit.style.backgroundColor = 'rgba(13, 110, 253, 0.08)';
                cardCredit.classList.add('border-2');
            }
            if (cardPay) {
                cardPay.style.borderColor = '#dee2e6';
                cardPay.style.backgroundColor = '#ffffff';
                cardPay.classList.remove('border-2');
            }
        } else {
            if (badgeType) {
                badgeType.className = 'badge bg-success-subtle text-success border border-success';
                badgeType.textContent = 'All Finance (' + financeCount + ' Items)';
            }
            if (hiddenDecision) hiddenDecision.value = 'pay_and_buy';
            if (hiddenPayment) hiddenPayment.value = 'pay_and_buy';

            if (radioPay) radioPay.checked = true;
            if (radioCredit) radioCredit.checked = false;
            if (cardPay) {
                cardPay.style.borderColor = '#198754';
                cardPay.style.backgroundColor = 'rgba(25, 135, 84, 0.08)';
                cardPay.classList.add('border-2');
            }
            if (cardCredit) {
                cardCredit.style.borderColor = '#dee2e6';
                cardCredit.style.backgroundColor = '#ffffff';
                cardCredit.classList.remove('border-2');
            }
        }
        updateGmDecisionAlertsAndSubmit();
    }
}

function updateGmDecisionAlertsAndSubmit() {
    const hiddenDecision = document.getElementById('gmDecisionHidden');
    const val = hiddenDecision ? hiddenDecision.value : 'pay_and_buy';

    const helpPay = document.getElementById('gmDecisionHelpPayBuy');
    const helpCredit = document.getElementById('gmDecisionHelpCredit');
    const helpSplit = document.getElementById('gmDecisionHelpSplit');
    const helpSendBack = document.getElementById('gmDecisionHelpSendBack');
    const helpReject = document.getElementById('gmDecisionHelpReject');
    const btn = document.getElementById('btnSubmitGmDecision');

    if (helpPay) helpPay.style.display = 'none';
    if (helpCredit) helpCredit.style.display = 'none';
    if (helpSplit) helpSplit.style.display = 'none';
    if (helpSendBack) helpSendBack.style.display = 'none';
    if (helpReject) helpReject.style.display = 'none';

    if (val === 'buy_by_credit') {
        if (helpCredit) helpCredit.style.display = 'block';
        if (btn) {
            btn.className = 'btn btn-primary btn-sm w-100 fw-bold shadow-sm py-2';
            btn.innerHTML = '<i class="fas fa-credit-card me-1"></i> Approve & Buy with Credit (COA 5110)';
        }
    } else if (val === 'split') {
        if (helpSplit) helpSplit.style.display = 'block';
        if (btn) {
            btn.className = 'btn btn-dark btn-sm w-100 fw-bold shadow-sm py-2';
            btn.innerHTML = '<i class="fas fa-layer-group me-1"></i> Approve Split (Finance & Credit)';
        }
    } else if (val === 'send_back') {
        if (helpSendBack) helpSendBack.style.display = 'block';
        if (btn) {
            btn.className = 'btn btn-warning text-dark btn-sm w-100 fw-bold shadow-sm py-2';
            btn.innerHTML = '<i class="fas fa-undo me-1"></i> Send Back to PM for Revision';
        }
    } else if (val === 'reject') {
        if (helpReject) helpReject.style.display = 'block';
        if (btn) {
            btn.className = 'btn btn-danger btn-sm w-100 fw-bold shadow-sm py-2';
            btn.innerHTML = '<i class="fas fa-ban me-1"></i> Confirm Rejection of PR';
        }
    } else {
        if (helpPay) helpPay.style.display = 'block';
        if (btn) {
            btn.className = 'btn btn-success btn-sm w-100 fw-bold shadow-sm py-2';
            btn.innerHTML = '<i class="fas fa-paper-plane me-1"></i> Approve & Send to Finance (Pay & Buy)';
        }
    }
}

// Fallback for any legacy calls
function handleGmDecisionChange(select) {
    if (!select) return;
    if (select.value === 'buy_by_credit') {
        selectGmMainDecision('buy_by_credit');
    } else if (select.value === 'pay_and_buy') {
        selectGmMainDecision('pay_and_buy');
    } else if (select.value === 'send_back') {
        selectGmSecondaryDecision('send_back');
    } else if (select.value === 'reject') {
        selectGmSecondaryDecision('reject');
    }
}

window.proformaMatrix = @json($proformaMatrix ?? []);

document.addEventListener('DOMContentLoaded', function() {
    if (document.getElementById('gmDecisionForm')) {
        recalculateItemAllocations(false);
        if (window.proformaMatrix && window.proformaMatrix.has_proformas) {
            syncSidebarSelectionStatus();
            recomputeOverallAwardedTotal();
        }
    }
});

function syncGmProformaSelect(profId) {
    selectGmProformaForEntirePr(profId);
}

function selectGmProformaForEntirePr(profId) {
    if (!profId || !window.proformaMatrix || !window.proformaMatrix.proformas) return;
    
    // 1. Update hidden selected proforma input
    const hiddenProf = document.getElementById('gmSelectedProformaHidden');
    if (hiddenProf) hiddenProf.value = profId;

    // 2. Update sidebar radio & active styling
    const sidebarRadio = document.getElementById('gmProfRadio_' + profId);
    if (sidebarRadio) sidebarRadio.checked = true;
    document.querySelectorAll('.gm-prof-item').forEach(el => {
        el.classList.remove('bg-light', 'border-primary');
    });
    const label = document.getElementById('gmProfItemLabel_' + profId);
    if (label) label.classList.add('bg-light', 'border-primary');

    // Hide custom per-item notice
    const customNotice = document.getElementById('gmPerItemAwardNotice');
    if (customNotice) customNotice.classList.add('d-none');

    // Highlight selected vendor column in matrix
    document.querySelectorAll('.matrix-vendor-col-header').forEach(el => {
        el.classList.remove('bg-primary-subtle', 'border-primary');
    });
    const colHeader = document.getElementById('matrixVendorCol_' + profId);
    if (colHeader) colHeader.classList.add('bg-primary-subtle', 'border-primary');

    // 3. Update all items to this proforma
    const items = window.proformaMatrix.items || {};
    let totalAwarded = 0;

    Object.keys(items).forEach(itemId => {
        const itemData = items[itemId];
        const quote = (itemData.quotes && itemData.quotes[profId]) ? itemData.quotes[profId] : null;
        
        const unitPrice = (quote && quote.unit_price !== null) ? parseFloat(quote.unit_price) : parseFloat(itemData.current_unit_cost || 0);
        const lineTotal = (quote && quote.line_total !== null) ? parseFloat(quote.line_total) : (unitPrice * parseFloat(itemData.quantity || 0));
        totalAwarded += lineTotal;

        // Update hidden inputs for item
        const hiddenItemProf = document.getElementById('gmItemProformaHidden_' + itemId);
        if (hiddenItemProf) hiddenItemProf.value = profId;

        const hiddenItemPrice = document.getElementById('gmItemUnitPriceHidden_' + itemId);
        if (hiddenItemPrice) hiddenItemPrice.value = unitPrice;

        // Update Material Table inline select
        const matSelect = document.getElementById('itemBidSelect_' + itemId);
        if (matSelect) {
            matSelect.value = profId;
            matSelect.classList.remove('border-danger');
        }

        // Update Material Table unit cost & total displays
        const unitCostDisplay = document.getElementById('itemUnitCostDisplay_' + itemId);
        if (unitCostDisplay) {
            unitCostDisplay.textContent = unitPrice.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        }
        const totalDisplay = document.getElementById('itemCalculatedTotalDisplay_' + itemId);
        if (totalDisplay) {
            totalDisplay.textContent = lineTotal.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        }

        // Update Matrix radio & cell styles
        document.querySelectorAll('input[name="matrix_item_' + itemId + '"]').forEach(r => {
            r.checked = (r.value == profId);
        });
        document.querySelectorAll('[id^="matrixCell_' + itemId + '_"]').forEach(td => {
            td.classList.remove('bg-primary-subtle', 'border-primary');
        });
        const awardedCell = document.getElementById('matrixCell_' + itemId + '_' + profId);
        if (awardedCell) {
            awardedCell.classList.add('bg-primary-subtle', 'border-primary');
        }

        // Update gm-item-allocation-row for sidebar split calculator
        const allocRow = document.querySelector('.gm-item-allocation-row[data-item-id="' + itemId + '"]');
        if (allocRow) {
            allocRow.setAttribute('data-amount', lineTotal);
            const lineTotalDisplay = allocRow.querySelector('.text-end.font-monospace');
            if (lineTotalDisplay) {
                lineTotalDisplay.textContent = lineTotal.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' ETB';
            }
        }
    });

    const profObj = (window.proformaMatrix && window.proformaMatrix.proformas) ? window.proformaMatrix.proformas[profId] : null;
    if (profObj && parseFloat(profObj.grand_total) > 0) {
        totalAwarded = parseFloat(profObj.grand_total);
    }

    // 4. Update grand total displays
    updateAwardedGrandTotalDisplay(totalAwarded);

    // Recalculate sidebar item allocations (split finance/credit)
    if (typeof recalculateItemAllocations === 'function') {
        recalculateItemAllocations(false);
    }
}

function selectGmProformaForItem(itemId, profId) {
    if (!itemId || !profId || !window.proformaMatrix) return;
    
    const items = window.proformaMatrix.items || {};
    const itemData = items[itemId];
    if (!itemData) return;

    const quote = (itemData.quotes && itemData.quotes[profId]) ? itemData.quotes[profId] : null;
    const unitPrice = (quote && quote.unit_price !== null) ? parseFloat(quote.unit_price) : parseFloat(itemData.current_unit_cost || 0);
    const lineTotal = (quote && quote.line_total !== null) ? parseFloat(quote.line_total) : (unitPrice * parseFloat(itemData.quantity || 0));

    // 1. Update hidden inputs
    const hiddenItemProf = document.getElementById('gmItemProformaHidden_' + itemId);
    if (hiddenItemProf) hiddenItemProf.value = profId;

    const hiddenItemPrice = document.getElementById('gmItemUnitPriceHidden_' + itemId);
    if (hiddenItemPrice) hiddenItemPrice.value = unitPrice;

    // 2. Sync Material Table inline select
    const matSelect = document.getElementById('itemBidSelect_' + itemId);
    if (matSelect) {
        matSelect.value = profId;
        matSelect.classList.remove('border-danger');
    }

    // 3. Update Material Table displays
    const unitCostDisplay = document.getElementById('itemUnitCostDisplay_' + itemId);
    if (unitCostDisplay) {
        unitCostDisplay.textContent = unitPrice.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }
    const totalDisplay = document.getElementById('itemCalculatedTotalDisplay_' + itemId);
    if (totalDisplay) {
        totalDisplay.textContent = lineTotal.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    // 4. Update Matrix radio & cell styles
    const matrixRadio = document.getElementById('matrixRadio_' + itemId + '_' + profId);
    if (matrixRadio) matrixRadio.checked = true;

    document.querySelectorAll('[id^="matrixCell_' + itemId + '_"]').forEach(td => {
        td.classList.remove('bg-primary-subtle', 'border-primary');
    });
    const awardedCell = document.getElementById('matrixCell_' + itemId + '_' + profId);
    if (awardedCell) {
        awardedCell.classList.add('bg-primary-subtle', 'border-primary');
    }

    // 5. Update gm-item-allocation-row for sidebar split calculator
    const allocRow = document.querySelector('.gm-item-allocation-row[data-item-id="' + itemId + '"]');
    if (allocRow) {
        allocRow.setAttribute('data-amount', lineTotal);
        const lineTotalDisplay = allocRow.querySelector('.text-end.font-monospace');
        if (lineTotalDisplay) {
            lineTotalDisplay.textContent = lineTotal.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' ETB';
        }
    }

    // 6. Check if all items now share the same proforma or if it's mixed
    syncSidebarSelectionStatus();

    // 7. Recalculate totals
    recomputeOverallAwardedTotal();
}

function handleItemBidChange(itemId, profId) {
    if (!profId) return;
    selectGmProformaForItem(itemId, profId);
}

function autoSelectLowestBids() {
    if (!window.proformaMatrix || !window.proformaMatrix.items) return;
    const items = window.proformaMatrix.items;
    let selectedCount = 0;

    Object.keys(items).forEach(itemId => {
        const item = items[itemId];
        let bestQuote = null;

        if (item.quotes) {
            Object.values(item.quotes).forEach(q => {
                if (q.unit_price !== null && parseFloat(q.unit_price) > 0) {
                    if (!bestQuote || parseFloat(q.unit_price) < parseFloat(bestQuote.unit_price)) {
                        bestQuote = q;
                    }
                }
            });
        }

        if (bestQuote && bestQuote.proforma_id) {
            selectGmProformaForItem(itemId, bestQuote.proforma_id);
            selectedCount++;
        }
    });

    if (selectedCount > 0) {
        const matrixCard = document.getElementById('gmBidsMatrixCard');
        if (matrixCard) {
            matrixCard.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }
    }
}

function syncSidebarSelectionStatus() {
    if (!window.proformaMatrix || !window.proformaMatrix.items) return;
    const items = window.proformaMatrix.items || {};
    const itemIds = Object.keys(items);
    if (itemIds.length === 0) return;

    let firstProfId = null;
    let allSame = true;
    let distinctVendors = new Set();

    itemIds.forEach(itemId => {
        const hiddenInput = document.getElementById('gmItemProformaHidden_' + itemId);
        const profId = hiddenInput ? hiddenInput.value : null;
        if (profId) distinctVendors.add(profId);

        if (firstProfId === null) {
            firstProfId = profId;
        } else if (profId !== firstProfId) {
            allSame = false;
        }
    });

    const hiddenOverallProf = document.getElementById('gmSelectedProformaHidden');
    const customNotice = document.getElementById('gmPerItemAwardNotice');
    const customNoticeText = document.getElementById('gmPerItemAwardNoticeText');

    if (allSame && firstProfId) {
        if (hiddenOverallProf) hiddenOverallProf.value = firstProfId;
        const radio = document.getElementById('gmProfRadio_' + firstProfId);
        if (radio) radio.checked = true;
        document.querySelectorAll('.gm-prof-item').forEach(el => {
            el.classList.remove('bg-light', 'border-primary');
        });
        const label = document.getElementById('gmProfItemLabel_' + firstProfId);
        if (label) label.classList.add('bg-light', 'border-primary');

        if (customNotice) customNotice.classList.add('d-none');
    } else {
        // Mixed per-item selection
        if (hiddenOverallProf) hiddenOverallProf.value = '';
        document.querySelectorAll('.gm-proforma-radio').forEach(r => r.checked = false);
        document.querySelectorAll('.gm-prof-item').forEach(el => {
            el.classList.remove('bg-light', 'border-primary');
        });

        if (customNotice) {
            customNotice.classList.remove('d-none');
            if (customNoticeText) {
                customNoticeText.textContent = 'Custom Split Active (' + distinctVendors.size + ' vendors awarded)';
            }
        }
    }
}

function recomputeOverallAwardedTotal() {
    if (!window.proformaMatrix || !window.proformaMatrix.items) return;
    let grandTotal = 0;
    const items = window.proformaMatrix.items || {};
    
    Object.keys(items).forEach(itemId => {
        const item = items[itemId];
        const hiddenPrice = document.getElementById('gmItemUnitPriceHidden_' + itemId);
        const unitPrice = hiddenPrice ? parseFloat(hiddenPrice.value || 0) : 0;
        const qty = parseFloat(item.quantity || 0);
        grandTotal += (unitPrice * qty);
    });

    updateAwardedGrandTotalDisplay(grandTotal);

    if (typeof recalculateItemAllocations === 'function') {
        recalculateItemAllocations(false);
    }
}

function updateAwardedGrandTotalDisplay(total) {
    const formatted = total.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' ETB';
    const disp1 = document.getElementById('gmDynamicGrandTotalDisplay');
    if (disp1) disp1.textContent = formatted;

    const disp2 = document.getElementById('matrixAwardedGrandTotal');
    if (disp2) disp2.textContent = formatted;
}

function handleCoaSelectionChange(selectEl) {
    if (!selectEl) return;
    const selectedOpt = selectEl.options[selectEl.selectedIndex];
    const staffHidden = document.getElementById('assignFinanceStaffHidden');
    const badgeEl = document.getElementById('coaAutoAssignedBadge');
    
    if (!selectedOpt || !selectedOpt.value) {
        if (badgeEl) badgeEl.classList.add('d-none');
        if (staffHidden) staffHidden.value = '';
        return;
    }

    const assignedUserId = selectedOpt.dataset.assignedUser;
    if (staffHidden) {
        staffHidden.value = assignedUserId || '';
    }

    const assignedName = selectedOpt.text.includes('— Assigned:') 
        ? selectedOpt.text.split('— Assigned:')[1].trim() 
        : '';

    if (assignedUserId && assignedName) {
        if (badgeEl) {
            badgeEl.classList.remove('d-none');
            badgeEl.innerHTML = `<i class="fas fa-user-check text-success me-1"></i> Account Holder: <strong>${assignedName}</strong> (Auto-assigned)`;
        }
    } else {
        if (badgeEl) {
            badgeEl.classList.remove('d-none');
            badgeEl.innerHTML = `<span class="text-muted"><i class="fas fa-info-circle me-1"></i> Payment managed by Finance.</span>`;
        }
    }
}

function fetchStoreSlipSequence(storeId, forceOverwrite = false) {
    if (!storeId) return;
    const slipInput = document.getElementById('intakeSlipNoInput');
    const badgeContainer = document.getElementById('intakeSlipSequenceBadge');
    if (!slipInput || !badgeContainer) return;

    badgeContainer.innerHTML = '<span class="text-muted small"><i class="fas fa-spinner fa-spin me-1"></i> Checking slip sequence for selected store...</span>';

    fetch('/api/slip-sequences/' + storeId + '/receive', {
        headers: {
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(res => res.json())
    .then(data => {
        if (data.has_sequence && data.formatted_slip) {
            if (forceOverwrite || (!slipInput.classList.contains('is-invalid') && (!slipInput.value || slipInput.value.startsWith('REC-')))) {
                slipInput.value = data.formatted_slip;
            }
            slipInput.dataset.start = data.book_start_no || '';
            slipInput.dataset.end = data.book_end_no || '';
            slipInput.dataset.prefix = data.prefix || '';

            badgeContainer.innerHTML = `
                <div class="alert alert-success py-1 px-2 small mb-0 mt-1 d-flex justify-content-between align-items-center border-success">
                    <div>
                        <i class="fas fa-book-bookmark text-success me-1"></i>
                        Active Book: <strong>${data.label || 'Receiving (GRN)'}</strong> 
                        <span class="text-muted">(Range: ${data.book_start_no} - ${data.book_end_no})</span>
                    </div>
                    <div class="d-flex align-items-center">
                        <span class="badge bg-success">${data.remaining} slips left</span>
                        ${slipInput.classList.contains('is-invalid') ? `<button type="button" class="btn btn-xs btn-outline-success ms-1 py-0 px-2 fw-bold" onclick="document.getElementById('intakeSlipNoInput').value='${data.formatted_slip}'; document.getElementById('intakeSlipNoInput').classList.remove('is-invalid');">Use Next: #${data.formatted_slip}</button>` : ''}
                    </div>
                </div>
            `;
            validateIntakeSlipRange();
        } else {
            slipInput.dataset.start = '';
            slipInput.dataset.end = '';
            badgeContainer.innerHTML = `
                <div class="alert alert-warning py-1 px-2 small mb-0 mt-1 d-flex justify-content-between align-items-center">
                    <div>
                        <i class="fas fa-triangle-exclamation text-warning me-1"></i>
                        No active receiving slip book sequence configured for this store.
                    </div>
                    <a href="/store-manager/slip-sequences/create?store_id=${storeId}&slip_type=receive" target="_blank" class="btn btn-xs btn-outline-primary py-0 text-decoration-none">
                        <i class="fas fa-plus"></i> Configure Book
                    </a>
                </div>
            `;
        }
    })
    .catch(err => {
        console.error('Slip sequence check error:', err);
    });
}

function validateIntakeSlipRange() {
    const slipInput = document.getElementById('intakeSlipNoInput');
    const warningEl = document.getElementById('intakeSlipRangeWarning');
    if (!slipInput || !warningEl) return;

    const start = parseInt(slipInput.dataset.start);
    const end = parseInt(slipInput.dataset.end);
    if (!start || !end) {
        warningEl.classList.add('d-none');
        return;
    }

    const val = slipInput.value.replace(/\D/g, '');
    const num = parseInt(val);

    if (num && (num < start || num > end)) {
        warningEl.classList.remove('d-none');
        warningEl.innerHTML = `<i class="fas fa-exclamation-triangle me-1"></i> Warning: Slip #${slipInput.value} is outside active book range (${start} - ${end}).`;
    } else {
        warningEl.classList.add('d-none');
    }
}

document.addEventListener('DOMContentLoaded', function() {
    const gmSelect = document.getElementById('gmDecisionSelect');
    if (gmSelect) {
        handleGmDecisionChange(gmSelect);
    }

    const intakeStoreSelect = document.getElementById('intakeStoreSelect');
    if (intakeStoreSelect && intakeStoreSelect.value) {
        fetchStoreSlipSequence(intakeStoreSelect.value);
    }

    // Select All Items Checkbox
    const selectAllCheckbox = document.getElementById('selectAllItems');
    if (selectAllCheckbox) {
        selectAllCheckbox.addEventListener('change', function() {
            const checkboxes = document.querySelectorAll('.pr-item-checkbox');
            checkboxes.forEach(cb => {
                cb.checked = selectAllCheckbox.checked;
            });
            updateSelectionToolbar();
        });
    }
});

function updateSelectionToolbar() {
    const checked = document.querySelectorAll('.pr-item-checkbox:checked');
    const countSpans = document.querySelectorAll('.selected-items-count');
    countSpans.forEach(span => {
        span.textContent = checked.length;
    });

    const allCheckboxes = document.querySelectorAll('.pr-item-checkbox');
    const selectAllCheckbox = document.getElementById('selectAllItems');
    if (selectAllCheckbox && allCheckboxes.length > 0) {
        selectAllCheckbox.checked = (checked.length === allCheckboxes.length);
    }
}

function getSelectedPrItems() {
    const checked = document.querySelectorAll('.pr-item-checkbox:checked');
    const items = [];
    checked.forEach(cb => {
        items.push({
            id: cb.dataset.itemId,
            productId: cb.dataset.productId,
            name: cb.dataset.productName,
            quantity: parseFloat(cb.dataset.quantity),
            unit: cb.dataset.unit,
            estimatedCost: parseFloat(cb.dataset.estimatedCost || '0'),
            hasStock: cb.dataset.hasStock === '1',
            stockQty: parseFloat(cb.dataset.stockQty || '0'),
        });
    });
    return items;
}

function openSelectiveTransferModal() {
    let items = getSelectedPrItems();
    if (items.length === 0) {
        // If none selected, automatically select all and proceed
        document.querySelectorAll('.pr-item-checkbox').forEach(cb => cb.checked = true);
        updateSelectionToolbar();
        items = getSelectedPrItems();
    }

    const tbody = document.getElementById('selTransferItemsTbody');
    tbody.innerHTML = '';

    items.forEach((item, idx) => {
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td>
                <input type="hidden" name="items[${idx}][item_id]" value="${item.id}">
                <strong>${item.name}</strong>
                ${item.hasStock ? '<span class="badge bg-success ms-1 small">Stock Avail: ' + item.stockQty + '</span>' : '<span class="badge bg-secondary ms-1 small">0 in Stock</span>'}
            </td>
            <td>
                <input type="number" step="0.001" min="0.001" max="${item.quantity}" 
                       name="items[${idx}][quantity]" value="${item.quantity}" 
                       class="form-control form-control-sm fw-bold" required>
            </td>
            <td><span class="badge bg-light text-dark border">${item.unit}</span></td>
        `;
        tbody.appendChild(tr);
    });

    const modal = new bootstrap.Modal(document.getElementById('selectiveTransferModal'));
    modal.show();
}

function openSelectiveSendPmModal() {
    let items = getSelectedPrItems();
    if (items.length === 0) {
        // If none selected, automatically select all and proceed
        document.querySelectorAll('.pr-item-checkbox').forEach(cb => cb.checked = true);
        updateSelectionToolbar();
        items = getSelectedPrItems();
    }

    const container = document.getElementById('selSendPmItemsList');
    container.innerHTML = '';

    items.forEach(item => {
        const div = document.createElement('div');
        div.className = 'd-flex justify-content-between align-items-center py-1 border-bottom';
        div.innerHTML = `
            <input type="hidden" name="item_ids[]" value="${item.id}">
            <span><strong>${item.name}</strong></span>
            <span class="badge bg-primary">${item.quantity} ${item.unit}</span>
        `;
        container.appendChild(div);
    });

    const modal = new bootstrap.Modal(document.getElementById('selectiveSendPmModal'));
    modal.show();
}

function quickTransferSingleItem(itemId) {
    document.querySelectorAll('.pr-item-checkbox').forEach(cb => cb.checked = false);
    const targetCb = document.querySelector(`.pr-item-checkbox[data-item-id="${itemId}"]`);
    if (targetCb) {
        targetCb.checked = true;
    }
    updateSelectionToolbar();
    openSelectiveTransferModal();
}

function quickPurchaseSingleItem(itemId) {
    document.querySelectorAll('.pr-item-checkbox').forEach(cb => cb.checked = false);
    const targetCb = document.querySelector(`.pr-item-checkbox[data-item-id="${itemId}"]`);
    if (targetCb) {
        targetCb.checked = true;
    }
    updateSelectionToolbar();
    openSelectiveSendPmModal();
}

// ── PM Selective Actions ──
function openPmSendBackStoreModal() {
    let items = getSelectedPrItems();
    if (items.length === 0) {
        document.querySelectorAll('.pr-item-checkbox').forEach(cb => cb.checked = true);
        updateSelectionToolbar();
        items = getSelectedPrItems();
    }

    const container = document.getElementById('pmSendBackItemsList');
    if (!container) return;
    container.innerHTML = '';

    items.forEach(item => {
        const div = document.createElement('div');
        div.className = 'd-flex justify-content-between align-items-center py-2 border-bottom';
        div.innerHTML = `
            <input type="hidden" name="item_ids[]" value="${item.id}">
            <div>
                <strong>${item.name}</strong>
                ${item.hasStock ? '<span class="badge bg-success bg-opacity-10 text-success border border-success ms-2 small"><i class="fas fa-warehouse me-1"></i>In Stock (' + item.stockQty + ' ' + item.unit + ')</span>' : '<span class="badge bg-light text-muted border ms-2 small">Out of Stock</span>'}
            </div>
            <span class="badge bg-primary fs-6">${item.quantity} ${item.unit}</span>
        `;
        container.appendChild(div);
    });

    const modal = new bootstrap.Modal(document.getElementById('pmSendBackStoreModal'));
    modal.show();
}

function openPmDirectBuyModal() {
    let items = getSelectedPrItems();
    if (items.length === 0) {
        document.querySelectorAll('.pr-item-checkbox').forEach(cb => cb.checked = true);
        updateSelectionToolbar();
        items = getSelectedPrItems();
    }

    const container = document.getElementById('pmDirectBuyItemsList');
    if (!container) return;
    container.innerHTML = '';

    items.forEach(item => {
        const div = document.createElement('div');
        div.className = 'd-flex justify-content-between align-items-center py-2 border-bottom';
        div.innerHTML = `
            <input type="hidden" name="item_ids[]" value="${item.id}">
            <span><strong>${item.name}</strong></span>
            <span class="badge bg-success">${item.quantity} ${item.unit}</span>
        `;
        container.appendChild(div);
    });

    const modal = new bootstrap.Modal(document.getElementById('pmSelectiveDirectBuyModal'));
    modal.show();
}

function openPmProformaModal() {
    let items = getSelectedPrItems();
    if (items.length === 0) {
        document.querySelectorAll('.pr-item-checkbox').forEach(cb => cb.checked = true);
        updateSelectionToolbar();
        items = getSelectedPrItems();
    }

    const container = document.getElementById('pmProformaItemsList');
    if (!container) return;
    container.innerHTML = '';

    items.forEach(item => {
        const div = document.createElement('div');
        div.className = 'd-flex justify-content-between align-items-center py-2 border-bottom';
        div.innerHTML = `
            <input type="hidden" name="item_ids[]" value="${item.id}">
            <span><strong>${item.name}</strong></span>
            <span class="badge bg-primary">${item.quantity} ${item.unit}</span>
        `;
        container.appendChild(div);
    });

    const modal = new bootstrap.Modal(document.getElementById('pmSelectiveProformaModal'));
    modal.show();
}

function quickPmSendBackSingleItem(itemId) {
    document.querySelectorAll('.pr-item-checkbox').forEach(cb => cb.checked = false);
    const targetCb = document.querySelector(`.pr-item-checkbox[data-item-id="${itemId}"]`);
    if (targetCb) {
        targetCb.checked = true;
    }
    updateSelectionToolbar();
    openPmSendBackStoreModal();
}

function quickPmDirectBuySingleItem(itemId) {
    document.querySelectorAll('.pr-item-checkbox').forEach(cb => cb.checked = false);
    const targetCb = document.querySelector(`.pr-item-checkbox[data-item-id="${itemId}"]`);
    if (targetCb) {
        targetCb.checked = true;
    }
    updateSelectionToolbar();
    openPmDirectBuyModal();
}

function quickPmProformaSingleItem(itemId) {
    document.querySelectorAll('.pr-item-checkbox').forEach(cb => cb.checked = false);
    const targetCb = document.querySelector(`.pr-item-checkbox[data-item-id="${itemId}"]`);
    if (targetCb) {
        targetCb.checked = true;
    }
    updateSelectionToolbar();
    openPmProformaModal();
}

// ── Procurement Team Selective Actions ──
function openProcTeamSelectiveDirectBuyModal() {
    let items = getSelectedPrItems();
    if (items.length === 0) {
        document.querySelectorAll('.pr-item-checkbox').forEach(cb => cb.checked = true);
        updateSelectionToolbar();
        items = getSelectedPrItems();
    }

    const tbody = document.getElementById('procTeamDirectBuyItemsContainer');
    if (!tbody) return;
    tbody.innerHTML = '';

    items.forEach((item, idx) => {
        const tr = document.createElement('tr');
        const unitCost = item.estimatedCost > 0 ? item.estimatedCost : 0;
        const lineTotal = (unitCost * item.quantity).toFixed(2);

        tr.innerHTML = `
            <td class="ps-2">
                <input type="hidden" name="item_ids[]" value="${item.id}">
                <strong class="text-dark">${item.name}</strong>
            </td>
            <td>
                <div class="input-group input-group-sm">
                    <input type="number" step="0.001" min="0" 
                           name="purchased_quantities[${item.id}]" 
                           id="proc_team_item_qty_${item.id}"
                           value="${item.quantity}" 
                           class="form-control form-control-sm proc-team-direct-item-qty fw-bold text-dark" 
                           data-item-id="${item.id}"
                           data-unit="${item.unit}"
                           oninput="recalculateProcTeamDirectBuyTotal()"
                           placeholder="0.000" required>
                    <span class="input-group-text py-0 px-1 bg-light text-muted" style="font-size: 11px;">${item.unit}</span>
                </div>
                <div class="small text-muted" style="font-size: 10px;">Req: ${item.quantity} ${item.unit}</div>
            </td>
            <td>
                <div class="input-group input-group-sm">
                    <span class="input-group-text py-0 px-1 bg-light text-muted" style="font-size: 11px;">ETB</span>
                    <input type="number" step="0.01" min="0" 
                           name="item_prices[${item.id}]" 
                           id="proc_team_item_price_${item.id}"
                           value="${unitCost > 0 ? unitCost : ''}" 
                           class="form-control form-control-sm proc-team-direct-item-cost fw-semibold text-dark" 
                           data-item-id="${item.id}"
                           data-qty="${item.quantity}"
                           oninput="recalculateProcTeamDirectBuyTotal()"
                           placeholder="0.00" required>
                </div>
            </td>
            <td class="pe-2 text-end fw-bold text-success font-monospace" id="procTeamLineTotal_${item.id}">
                ${Number(lineTotal).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})} ETB
            </td>
        `;
        tbody.appendChild(tr);
    });

    recalculateProcTeamDirectBuyTotal();

    const modal = new bootstrap.Modal(document.getElementById('procTeamSelectiveDirectBuyModal'));
    modal.show();
}

function recalculateProcTeamDirectBuyTotal() {
    let subtotal = 0;
    let totalQty = 0;
    document.querySelectorAll('.proc-team-direct-item-cost').forEach(input => {
        const itemId = input.dataset.itemId;
        const val = parseFloat(input.value) || 0;
        const qtyInput = document.getElementById('proc_team_item_qty_' + itemId);
        const qty = qtyInput ? (parseFloat(qtyInput.value) || 0) : (parseFloat(input.dataset.qty) || 0);
        const lineTot = Math.round((val * qty) * 100) / 100;
        subtotal += lineTot;
        totalQty += qty;

        const lineSpan = document.getElementById('procTeamLineTotal_' + itemId);
        if (lineSpan) {
            lineSpan.textContent = Number(lineTot).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2}) + ' ETB';
        }
    });

    subtotal = Math.round(subtotal * 100) / 100;
    const subtotalDisplay = document.getElementById('procTeamDirectBuySubtotalDisplay');
    if (subtotalDisplay) {
        subtotalDisplay.textContent = Number(subtotal).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2}) + ' ETB';
    }

    const vatToggle = document.getElementById('procTeamDirectBuyVatToggle');
    const isVat = vatToggle ? vatToggle.checked : false;
    const vatAmount = isVat ? Math.round((subtotal * 0.15) * 100) / 100 : 0;
    const vatDisplay = document.getElementById('procTeamDirectBuyVatDisplay');
    if (vatDisplay) {
        vatDisplay.textContent = '+' + Number(vatAmount).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2}) + ' ETB';
    }

    const grandTotal = Math.round((subtotal + vatAmount) * 100) / 100;
    const totalInput = document.getElementById('procTeamDirectBuyTotalAmount');
    if (totalInput) {
        totalInput.value = grandTotal > 0 ? grandTotal.toFixed(2) : (subtotal > 0 ? subtotal.toFixed(2) : '0.00');
    }

    const totalMoneyDisplay = document.getElementById('procTeamDirectBuyTotalMoneyDisplay');
    if (totalMoneyDisplay) {
        totalMoneyDisplay.textContent = Number(grandTotal > 0 ? grandTotal : subtotal).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2}) + ' ETB';
    }
}

function openProcTeamSelectiveProformaModal() {
    let items = getSelectedPrItems();
    if (items.length === 0) {
        document.querySelectorAll('.pr-item-checkbox').forEach(cb => cb.checked = true);
        updateSelectionToolbar();
        items = getSelectedPrItems();
    }

    const container = document.getElementById('procTeamProformaItemsContainer');
    if (!container) return;
    container.innerHTML = '';

    items.forEach(item => {
        const div = document.createElement('div');
        div.className = 'd-flex justify-content-between align-items-center py-2 border-bottom';
        div.innerHTML = `
            <input type="hidden" name="item_ids[]" value="${item.id}">
            <span><strong>${item.name}</strong></span>
            <span class="badge bg-primary">${item.quantity} ${item.unit}</span>
        `;
        container.appendChild(div);
    });

    const modal = new bootstrap.Modal(document.getElementById('procTeamSelectiveProformaModal'));
    modal.show();
}

function openProcTeamSendBackPmModal() {
    let items = getSelectedPrItems();
    if (items.length === 0) {
        document.querySelectorAll('.pr-item-checkbox').forEach(cb => cb.checked = true);
        updateSelectionToolbar();
        items = getSelectedPrItems();
    }

    const container = document.getElementById('procTeamSendBackItemsContainer');
    if (!container) return;
    container.innerHTML = '';

    items.forEach(item => {
        const div = document.createElement('div');
        div.className = 'd-flex justify-content-between align-items-center py-2 border-bottom';
        div.innerHTML = `
            <input type="hidden" name="item_ids[]" value="${item.id}">
            <span><strong>${item.name}</strong></span>
            <span class="badge bg-secondary">${item.quantity} ${item.unit}</span>
        `;
        container.appendChild(div);
    });

    const modal = new bootstrap.Modal(document.getElementById('procTeamSendBackPmModal'));
    modal.show();
}

function quickProcTeamDirectBuySingleItem(itemId) {
    document.querySelectorAll('.pr-item-checkbox').forEach(cb => cb.checked = false);
    const targetCb = document.querySelector(`.pr-item-checkbox[data-item-id="${itemId}"]`);
    if (targetCb) {
        targetCb.checked = true;
    }
    updateSelectionToolbar();
    openProcTeamSelectiveDirectBuyModal();
}

function quickProcTeamProformaSingleItem(itemId) {
    document.querySelectorAll('.pr-item-checkbox').forEach(cb => cb.checked = false);
    const targetCb = document.querySelector(`.pr-item-checkbox[data-item-id="${itemId}"]`);
    if (targetCb) {
        targetCb.checked = true;
    }
    updateSelectionToolbar();
    openProcTeamSelectiveProformaModal();
}

function quickProcTeamSendBackSingleItem(itemId) {
    document.querySelectorAll('.pr-item-checkbox').forEach(cb => cb.checked = false);
    const targetCb = document.querySelector(`.pr-item-checkbox[data-item-id="${itemId}"]`);
    if (targetCb) {
        targetCb.checked = true;
    }
    updateSelectionToolbar();
    openProcTeamSendBackPmModal();
}

function toggleSplitRowFields(selectEl, index) {
    const action = selectEl.value;
    const transferGroup = document.getElementById(`transferFieldGroup_${index}`);
    const transferNotice = document.getElementById(`transferDisabledNotice_${index}`);
    const purchaseGroup = document.getElementById(`purchaseFieldGroup_${index}`);
    const purchaseNotice = document.getElementById(`purchaseDisabledNotice_${index}`);

    if (action === 'transfer') {
        if (transferGroup) transferGroup.style.display = '';
        if (transferNotice) transferNotice.style.display = 'none';
        if (purchaseGroup) purchaseGroup.style.display = 'none';
        if (purchaseNotice) purchaseNotice.style.display = '';
    } else if (action === 'purchase') {
        if (transferGroup) transferGroup.style.display = 'none';
        if (transferNotice) transferNotice.style.display = '';
        if (purchaseGroup) purchaseGroup.style.display = '';
        if (purchaseNotice) purchaseNotice.style.display = 'none';
    } else {
        if (transferGroup) transferGroup.style.display = 'none';
        if (transferNotice) transferNotice.style.display = '';
        if (purchaseGroup) purchaseGroup.style.display = 'none';
        if (purchaseNotice) purchaseNotice.style.display = '';
    }
}

function recalculateDirectTotal() {
    let subtotal = 0;
    let totalQty = 0;
    document.querySelectorAll('.direct-item-cost').forEach(input => {
        const itemId = input.dataset.itemId;
        const val = parseFloat(input.value) || 0;
        const qtyInput = document.getElementById('direct_item_qty_' + itemId);
        const qty = qtyInput ? (parseFloat(qtyInput.value) || 0) : (parseFloat(input.dataset.qty) || 0);
        const lineTot = Math.round((val * qty) * 100) / 100;
        subtotal += lineTot;
        totalQty += qty;

        if (itemId) {
            const lineSpan = document.getElementById('directLineTotal_' + itemId);
            if (lineSpan) {
                lineSpan.textContent = Number(lineTot).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2}) + ' ETB';
            }
            const formulaSpan = document.getElementById('directFormula_' + itemId);
            if (formulaSpan) {
                formulaSpan.textContent = '(' + qty + ' × ' + Number(val).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2}) + ')';
            }
        }
    });

    subtotal = Math.round(subtotal * 100) / 100;

    const qtyDisplay = document.getElementById('directPurchasedQtyTotalDisplay');
    if (qtyDisplay) {
        qtyDisplay.textContent = Number(totalQty).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 3});
    }

    const subtotalDisplay = document.getElementById('directItemsSubtotalDisplay');
    if (subtotalDisplay) {
        subtotalDisplay.textContent = Number(subtotal).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2}) + ' ETB';
    }

    const vatToggle = document.getElementById('directVatToggle');
    const isVat = vatToggle ? vatToggle.checked : false;
    const vatAmount = isVat ? Math.round((subtotal * 0.15) * 100) / 100 : 0;

    const vatDisplay = document.getElementById('directVatAmountDisplay');
    if (vatDisplay) {
        vatDisplay.textContent = '+' + Number(vatAmount).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2}) + ' ETB';
    }

    const grandTotal = Math.round((subtotal + vatAmount) * 100) / 100;
    const totalInput = document.getElementById('directTotalAmountInput');
    if (totalInput) {
        totalInput.value = grandTotal > 0 ? grandTotal.toFixed(2) : (subtotal > 0 ? subtotal.toFixed(2) : '0.00');
    }

    const formattedSpan = document.getElementById('directTotalMoneyFormatted');
    if (formattedSpan) {
        formattedSpan.textContent = Number(grandTotal > 0 ? grandTotal : subtotal).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2}) + ' ETB';
    }
}

function syncDirectTotalWithItems() {
    recalculateDirectTotal();
}

function recalculateProformaModalTotal() {
    let subtotal = 0;
    document.querySelectorAll('.proforma-line-price').forEach(input => {
        const price = parseFloat(input.value) || 0;
        const qty = parseFloat(input.dataset.qty) || 0;
        const lineTotal = price * qty;
        subtotal += lineTotal;
        
        const lineTotalSpan = document.getElementById('profLineTotal_' + input.dataset.itemId);
        if (lineTotalSpan) {
            lineTotalSpan.textContent = lineTotal.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2}) + ' ETB';
        }
    });

    const subtotalSpan = document.getElementById('profModalSubtotal');
    const subtotalInput = document.getElementById('profModalSubtotalInput');
    if (subtotalSpan) subtotalSpan.textContent = subtotal.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2}) + ' ETB';
    if (subtotalInput) subtotalInput.value = subtotal.toFixed(2);

    const taxInput = document.getElementById('profModalTaxAmount');
    const tax = taxInput ? (parseFloat(taxInput.value) || 0) : 0;

    const grandTotal = subtotal + tax;
    const grandTotalInput = document.getElementById('profModalGrandTotal');
    if (grandTotalInput && (subtotal > 0 || tax > 0)) {
        grandTotalInput.value = grandTotal.toFixed(2);
    }
}

function applyProformaVat(percentage) {
    const subtotalInput = document.getElementById('profModalSubtotalInput');
    const subtotal = subtotalInput ? (parseFloat(subtotalInput.value) || 0) : 0;
    const taxInput = document.getElementById('profModalTaxAmount');
    if (taxInput) {
        const tax = (subtotal * percentage) / 100;
        taxInput.value = tax.toFixed(2);
        recalculateProformaModalTotal();
    }
}

// ── Marketing Price Benchmark Intelligence ──
const directBuySubmittedAmount = {{ (float)$purchaseRequest->direct_buy_amount }};

function selectBenchmarkAmount(amount, type) {
    const input = document.getElementById('mktBenchmarkInput');
    if (input) {
        input.value = parseFloat(amount).toFixed(2);
        recalculateMarketingVariance();
    }
    const btnL = document.getElementById('btnBenchmarkLatest');
    const btnM = document.getElementById('btnBenchmarkMonthly');
    const btnP = document.getElementById('btnBenchmarkPurchase');
    [btnL, btnM, btnP].forEach(btn => {
        if (btn) {
            btn.classList.remove('btn-outline-primary', 'active');
            btn.classList.add('btn-outline-secondary');
        }
    });

    if (type === 'latest' && btnL) {
        btnL.classList.remove('btn-outline-secondary');
        btnL.classList.add('btn-outline-primary', 'active');
    } else if (type === 'monthly' && btnM) {
        btnM.classList.remove('btn-outline-secondary');
        btnM.classList.add('btn-outline-primary', 'active');
    } else if (type === 'purchase' && btnP) {
        btnP.classList.remove('btn-outline-secondary');
        btnP.classList.add('btn-outline-primary', 'active');
    }
}

function recalculateMarketingVariance() {
    const input = document.getElementById('mktBenchmarkInput');
    const badge = document.getElementById('mktVarianceBadge');
    if (!input || !badge) return;

    const benchmark = parseFloat(input.value) || 0;
    if (benchmark <= 0 || directBuySubmittedAmount <= 0) {
        badge.style.display = 'none';
        return;
    }

    const diff = directBuySubmittedAmount - benchmark;
    const diffAbs = Math.abs(diff).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    const pct = ((diff / benchmark) * 100).toFixed(2);

    badge.style.display = 'block';
    if (diff > 0) {
        badge.className = 'mt-2 small p-2 rounded border bg-warning bg-opacity-10 border-warning text-dark';
        badge.innerHTML = `<i class="fas fa-arrow-trend-up text-warning me-1"></i> Direct buy is <strong>${diffAbs} ETB (+${pct}%)</strong> higher than market benchmark.`;
    } else if (diff < 0) {
        badge.className = 'mt-2 small p-2 rounded border bg-success bg-opacity-10 border-success text-success';
        badge.innerHTML = `<i class="fas fa-arrow-trend-down text-success me-1"></i> Direct buy is <strong>${diffAbs} ETB (${pct}%)</strong> lower than market benchmark (Savings).`;
    } else {
        badge.className = 'mt-2 small p-2 rounded border bg-info bg-opacity-10 border-info text-info';
        badge.innerHTML = `<i class="fas fa-equals text-info me-1"></i> Direct buy exactly matches the market benchmark.`;
    }
}

function generateMarketingVarianceNote() {
    const input = document.getElementById('mktBenchmarkInput');
    const notes = document.getElementById('mktVarianceNotes');
    if (!input || !notes) return;

    const benchmark = parseFloat(input.value) || 0;
    const diff = directBuySubmittedAmount - benchmark;
    const diffStr = Math.abs(diff).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    const pct = benchmark > 0 ? ((diff / benchmark) * 100).toFixed(2) : '0';

    if (diff > 0) {
        notes.value = `Market Benchmark evaluated at ${benchmark.toLocaleString('en-US', {minimumFractionDigits: 2})} ETB based on latest monthly market prices and purchase records. Direct buy price is higher by ${diffStr} ETB (+${pct}%). Recommended for GM review.`;
    } else if (diff < 0) {
        notes.value = `Market Benchmark evaluated at ${benchmark.toLocaleString('en-US', {minimumFractionDigits: 2})} ETB. Direct buy is favorable by ${diffStr} ETB (${pct}% savings). Recommended for GM approval.`;
    } else {
        notes.value = `Market Benchmark evaluated at ${benchmark.toLocaleString('en-US', {minimumFractionDigits: 2})} ETB. Direct buy matches prevailing market benchmark.`;
    }
}

// Auto-run variance calculation on page load if on marketing stage
document.addEventListener('DOMContentLoaded', function() {
    recalculateMarketingVariance();
});

// ── Purchasing Manager Send Back Destination Switchers (Stage 5a & 5b) ──
function switchSendBackTarget5a(target) {
    const procForm = document.getElementById('formPmSendBackToProcTeam5a');
    const storeForm = document.getElementById('formPmSendBackToStore5a');
    const notesEl = document.getElementById('notesProcTeam5a');
    const reasonEl = document.getElementById('reasonStore5a');
    if (!procForm || !storeForm) return;

    if (target === 'proc_team') {
        procForm.style.display = 'block';
        storeForm.style.display = 'none';
        if (notesEl) notesEl.setAttribute('required', 'required');
        if (reasonEl) reasonEl.removeAttribute('required');
    } else {
        procForm.style.display = 'none';
        storeForm.style.display = 'block';
        if (reasonEl) reasonEl.setAttribute('required', 'required');
        if (notesEl) notesEl.removeAttribute('required');
    }
}

function switchSendBackTarget5b(target) {
    const procForm = document.getElementById('formPmSendBackToProcTeam5b');
    const storeForm = document.getElementById('formPmSendBackToStore5b');
    const notesEl = document.getElementById('notesProcTeam5b');
    const reasonEl = document.getElementById('reasonStore5b');
    if (!procForm || !storeForm) return;

    if (target === 'proc_team') {
        procForm.style.display = 'block';
        storeForm.style.display = 'none';
        if (notesEl) notesEl.setAttribute('required', 'required');
        if (reasonEl) reasonEl.removeAttribute('required');
    } else {
        procForm.style.display = 'none';
        storeForm.style.display = 'block';
        if (reasonEl) reasonEl.setAttribute('required', 'required');
        if (notesEl) notesEl.removeAttribute('required');
    }
}


// ── Quick Supplier Management (Add & Edit within Proforma Modal) ──
function toggleSupplierQuickForm(mode) {
    const panel = document.getElementById('supplierQuickPanel');
    const title = document.getElementById('supplierQuickPanelTitle');
    const errorDiv = document.getElementById('quickSupErrorMsg');
    const btnSave = document.getElementById('btnSaveQuickSupplier');
    if (errorDiv) errorDiv.innerText = '';

    if (!mode || mode === false) {
        if (panel) panel.style.display = 'none';
        return;
    }

    if (mode === 'create') {
        document.getElementById('quickSupId').value = '';
        document.getElementById('quickSupName').value = '';
        document.getElementById('quickSupTaxId').value = '';
        document.getElementById('quickSupPhone').value = '';
        document.getElementById('quickSupEmail').value = '';
        document.getElementById('quickSupContact').value = '';
        document.getElementById('quickSupAddress').value = '';
        title.innerHTML = '<i class="fas fa-building-circle-check me-1"></i> Add New Supplier / Vendor';
        btnSave.innerHTML = '<i class="fas fa-check me-1"></i> Save & Select Supplier';
        panel.style.display = '';
        document.getElementById('quickSupName').focus();
    } else if (mode === 'edit') {
        const select = document.getElementById('proformaSupplierSelect');
        const selectedOpt = select.options[select.selectedIndex];
        if (!selectedOpt || !selectedOpt.value) return;

        document.getElementById('quickSupId').value = selectedOpt.value;
        document.getElementById('quickSupName').value = selectedOpt.dataset.name || '';
        document.getElementById('quickSupTaxId').value = selectedOpt.dataset.taxId || '';
        document.getElementById('quickSupPhone').value = selectedOpt.dataset.phone || '';
        document.getElementById('quickSupEmail').value = selectedOpt.dataset.email || '';
        document.getElementById('quickSupContact').value = selectedOpt.dataset.contact || '';
        document.getElementById('quickSupAddress').value = selectedOpt.dataset.address || '';
        title.innerHTML = `<i class="fas fa-pen-to-square me-1"></i> Edit Supplier: <strong>${selectedOpt.dataset.name}</strong>`;
        btnSave.innerHTML = '<i class="fas fa-save me-1"></i> Update Supplier Info';
        panel.style.display = '';
        document.getElementById('quickSupName').focus();
    }
}

function handleSupplierSelectChange(select) {
    const editBtn = document.getElementById('btnEditSelectedSupplier');
    const preview = document.getElementById('supplierInfoPreview');
    const opt = select.options[select.selectedIndex];

    if (opt && opt.value) {
        if (editBtn) editBtn.style.display = '';
        let info = [];
        if (opt.dataset.taxId) info.push(`<strong>TIN:</strong> ${opt.dataset.taxId}`);
        if (opt.dataset.phone) info.push(`<strong>Phone:</strong> ${opt.dataset.phone}`);
        if (opt.dataset.contact) info.push(`<strong>Contact:</strong> ${opt.dataset.contact}`);
        if (opt.dataset.address) info.push(`<strong>Address:</strong> ${opt.dataset.address}`);
        if (preview) {
            preview.innerHTML = info.join(' &bull; ') || '<span class="text-muted">No additional contact details registered. Click "Edit Supplier" to add TIN/Phone.</span>';
            preview.style.display = '';
        }
    } else {
        if (editBtn) editBtn.style.display = 'none';
        if (preview) preview.style.display = 'none';
    }
}

function submitQuickSupplier() {
    const id = document.getElementById('quickSupId').value;
    const name = document.getElementById('quickSupName').value.trim();
    const taxId = document.getElementById('quickSupTaxId').value.trim();
    const phone = document.getElementById('quickSupPhone').value.trim();
    const email = document.getElementById('quickSupEmail').value.trim();
    const contact = document.getElementById('quickSupContact').value.trim();
    const address = document.getElementById('quickSupAddress').value.trim();
    const errorDiv = document.getElementById('quickSupErrorMsg');
    const btnSave = document.getElementById('btnSaveQuickSupplier');

    if (errorDiv) errorDiv.innerText = '';
    if (!name) {
        if (errorDiv) errorDiv.innerText = 'Supplier Name is required.';
        return;
    }

    btnSave.disabled = true;
    btnSave.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Saving...';

    const baseUrl = "{{ url('suppliers') }}";
    const url = id 
        ? (baseUrl + "/" + id + "/quick-update")
        : (baseUrl + "/quick-store");

    const payload = {
        name: name,
        tax_id: taxId,
        phone: phone,
        email: email,
        contact_person: contact,
        address: address,
        _token: '{{ csrf_token() }}'
    };

    fetch(url, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}'
        },
        body: JSON.stringify(payload)
    })
    .then(r => r.json())
    .then(res => {
        btnSave.disabled = false;
        if (res.success && res.supplier) {
            const s = res.supplier;
            const select = document.getElementById('proformaSupplierSelect');
            const displayText = `${s.name}` + (s.tax_id ? ` (TIN: ${s.tax_id})` : (s.phone ? ` (Tel: ${s.phone})` : ''));

            if (id) {
                const opt = select.querySelector(`option[value="${id}"]`);
                if (opt) {
                    opt.text = displayText;
                    opt.dataset.name = s.name;
                    opt.dataset.taxId = s.tax_id || '';
                    opt.dataset.phone = s.phone || '';
                    opt.dataset.email = s.email || '';
                    opt.dataset.contact = s.contact_person || '';
                    opt.dataset.address = s.address || '';
                }
            } else {
                const opt = document.createElement('option');
                opt.value = s.id;
                opt.text = displayText;
                opt.dataset.name = s.name;
                opt.dataset.taxId = s.tax_id || '';
                opt.dataset.phone = s.phone || '';
                opt.dataset.email = s.email || '';
                opt.dataset.contact = s.contact_person || '';
                opt.dataset.address = s.address || '';
                select.appendChild(opt);
                select.value = s.id;
            }

            handleSupplierSelectChange(select);
            toggleSupplierQuickForm(false);
        } else {
            if (errorDiv) errorDiv.innerText = res.message || 'Error saving supplier.';
            btnSave.innerHTML = '<i class="fas fa-check me-1"></i> Save & Select Supplier';
        }
    })
    .catch(err => {
        btnSave.disabled = false;
        btnSave.innerHTML = '<i class="fas fa-check me-1"></i> Save & Select Supplier';
        if (errorDiv) errorDiv.innerText = 'Failed to save supplier. Please check fields and try again.';
    });
}

// Initialize Direct Buy live calculations on page load and bind responsive input events
document.addEventListener('DOMContentLoaded', function () {
    if (document.querySelector('.direct-item-cost') || document.querySelector('.direct-item-qty')) {
        recalculateDirectTotal();
        document.querySelectorAll('.direct-item-cost, .direct-item-qty').forEach(input => {
            input.addEventListener('change', recalculateDirectTotal);
            input.addEventListener('keyup', recalculateDirectTotal);
            input.addEventListener('input', recalculateDirectTotal);
        });
    }

    // When switching tabs to Direct Buy, ensure recalculation
    const directTabBtn = document.querySelector('button[data-bs-target="#tabDirect"]');
    if (directTabBtn) {
        directTabBtn.addEventListener('shown.bs.tab', function () {
            recalculateDirectTotal();
        });
    }
});
</script>

@if($isGlobalAdmin)
<!-- Modal: Add Item to Purchase Request (Global Admin Only) -->
<div class="modal fade" id="addPrItemModal" tabindex="-1" aria-labelledby="addPrItemModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <form action="{{ \Illuminate\Support\Facades\Route::has('purchase-requests.add-item') ? route('purchase-requests.add-item', $purchaseRequest) : url('/purchase-requests/' . $purchaseRequest->id . '/items') }}" method="POST">
                @csrf
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title fs-6 fw-bold" id="addPrItemModalLabel">
                        <i class="fas fa-plus-circle me-2"></i>Add More Items to PR #{{ $purchaseRequest->pr_no }}
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label fw-bold small text-muted text-uppercase">Select Material / Product <span class="text-danger">*</span></label>
                        <select class="form-select" name="product_id" id="modalAddProductId" required onchange="onModalProductChange(this)">
                            <option value="">-- Choose Product / Material --</option>
                            @php
                                $allProducts = $availableProducts ?? \App\Models\Product::where('is_active', true)->orderBy('name')->get();
                            @endphp
                            @foreach($allProducts as $p)
                                <option value="{{ $p->id }}" 
                                        data-unit="{{ $p->unit ?? 'pcs' }}" 
                                        data-cost="{{ (float)($p->unit_price ?? 0) }}"
                                        data-name="{{ $p->name }}">
                                    {{ $p->name }} {{ $p->code ? '('.$p->code.')' : '' }} [Unit: {{ $p->unit ?? 'pcs' }}]
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-muted text-uppercase">Qty Requested (Asked) <span class="text-danger">*</span></label>
                            <input type="number" step="0.001" min="0.001" class="form-control fw-bold" name="quantity" id="modalAddQuantity" placeholder="0.000" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-muted text-uppercase">Qty Purchased</label>
                            <input type="number" step="0.001" min="0" class="form-control" name="purchased_quantity" id="modalAddPurchasedQuantity" placeholder="0.000" value="0">
                            <small class="text-muted" style="font-size: 11px;">Already procured amount (optional)</small>
                        </div>
                    </div>

                    @if(!$hideMoneyColumns)
                    <div class="row g-2 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-muted text-uppercase">Unit</label>
                            <input type="text" class="form-control" name="unit" id="modalAddUnit" placeholder="e.g. Quintal, Pcs, Bag">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-muted text-uppercase">Est. Unit Cost (ETB)</label>
                            <input type="number" step="0.01" min="0" class="form-control" name="estimated_unit_cost" id="modalAddUnitCost" placeholder="0.00">
                        </div>
                    </div>
                    @else
                    <div class="mb-3">
                        <label class="form-label fw-bold small text-muted text-uppercase">Unit</label>
                        <input type="text" class="form-control" name="unit" id="modalAddUnit" placeholder="e.g. Quintal, Pcs, Bag">
                        <input type="hidden" name="estimated_unit_cost" id="modalAddUnitCost" value="0">
                    </div>
                    @endif

                    <div class="mb-2">
                        <label class="form-label fw-bold small text-muted text-uppercase">Specifications / Remarks</label>
                        <textarea class="form-control" name="specifications" rows="2" placeholder="Optional notes, grade, brand preference..."></textarea>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary fw-bold shadow-sm">
                        <i class="fas fa-plus me-1"></i> Add Item
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Edit Item / Purchased Quantity -->
<div class="modal fade" id="editPrItemModal" tabindex="-1" aria-labelledby="editPrItemModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <form id="editPrItemForm" method="POST">
                @csrf
                @method('PUT')
                <div class="modal-header bg-dark text-white">
                    <h5 class="modal-title fs-6 fw-bold" id="editPrItemModalLabel">
                        <i class="fas fa-pen-to-square me-2"></i>Update Item / Purchased Quantity
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label fw-bold small text-muted text-uppercase">Material / Product</label>
                        <input type="text" class="form-control bg-light" id="modalEditProductName" readonly>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-muted text-uppercase">Qty Requested (Asked) <span class="text-danger">*</span></label>
                            <input type="number" step="0.001" min="0.001" class="form-control fw-bold" name="quantity" id="modalEditQuantity" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-muted text-uppercase text-success">Qty Purchased</label>
                            <input type="number" step="0.001" min="0" class="form-control fw-bold border-success" name="purchased_quantity" id="modalEditPurchasedQuantity" required>
                        </div>
                    </div>

                    @if(!$hideMoneyColumns)
                    <div class="row g-2 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-muted text-uppercase">Unit</label>
                            <input type="text" class="form-control" name="unit" id="modalEditUnit">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-muted text-uppercase">Est. Unit Cost (ETB)</label>
                            <input type="number" step="0.01" min="0" class="form-control" name="estimated_unit_cost" id="modalEditUnitCost">
                        </div>
                    </div>
                    @else
                    <div class="mb-3">
                        <label class="form-label fw-bold small text-muted text-uppercase">Unit</label>
                        <input type="text" class="form-control" name="unit" id="modalEditUnit">
                        <input type="hidden" name="estimated_unit_cost" id="modalEditUnitCost" value="0">
                    </div>
                    @endif

                    <div class="mb-2">
                        <label class="form-label fw-bold small text-muted text-uppercase">Specifications</label>
                        <textarea class="form-control" name="specifications" id="modalEditSpecifications" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success fw-bold shadow-sm">
                        <i class="fas fa-check me-1"></i> Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Delete Purchase Request (Global Admin Only) -->
<div class="modal fade" id="deletePrModal" tabindex="-1" aria-labelledby="deletePrModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <form action="{{ route('purchase-requests.destroy', $purchaseRequest) }}" method="POST">
                @csrf
                @method('DELETE')
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title fs-6 fw-bold" id="deletePrModalLabel">
                        <i class="fas fa-lock me-2"></i>Move Purchase Request to Deleted &amp; Lock
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4 text-start">
                    <p class="mb-2">Are you sure you want to delete <strong>PR #{{ $purchaseRequest->pr_no }}</strong>?</p>
                    <div class="alert alert-warning small mb-3">
                        <i class="fas fa-shield-halved me-1"></i> <strong>Strict Lock Policy:</strong> Non-admin users cannot delete records or alter materials. As Global Admin, this action will move this PR to the <strong>Deleted &amp; Cancelled</strong> category with all requested materials preserved and permanently locked.
                    </div>
                    <label class="form-label small fw-bold text-muted text-uppercase">Deletion Reason / Remarks</label>
                    <textarea name="reason" class="form-control form-control-sm" rows="2" placeholder="Explain why this purchase request is being removed..."></textarea>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger btn-sm fw-bold"><i class="fas fa-trash-can me-1"></i> Move to Deleted &amp; Lock</button>
                </div>
            </form>
        </div>
    </div>
</div>

<form id="deletePrItemForm" method="POST" style="display:none;">
    @csrf
    @method('DELETE')
</form>
@endif

<script>
function onModalProductChange(select) {
    const selected = select.options[select.selectedIndex];
    if (selected && selected.value) {
        const unit = selected.getAttribute('data-unit') || 'pcs';
        const cost = selected.getAttribute('data-cost') || '0';
        const unitInput = document.getElementById('modalAddUnit');
        const costInput = document.getElementById('modalAddUnitCost');
        if (unitInput) unitInput.value = unit;
        if (costInput && parseFloat(cost) > 0) {
            costInput.value = parseFloat(cost).toFixed(2);
        }
    }
}

function openEditPrItemModal(itemId, productName, quantity, purchasedQty, unit, unitCost, specs) {
    const form = document.getElementById('editPrItemForm');
    form.action = "{{ url('purchase-requests/' . $purchaseRequest->id . '/items') }}/" + itemId;
    document.getElementById('modalEditProductName').value = productName;
    document.getElementById('modalEditQuantity').value = quantity;
    document.getElementById('modalEditPurchasedQuantity').value = purchasedQty;
    const costEl = document.getElementById('modalEditUnitCost');
    if (costEl) costEl.value = unitCost > 0 ? unitCost.toFixed(2) : '0';
    document.getElementById('modalEditSpecifications').value = specs || '';
    
    const modal = new bootstrap.Modal(document.getElementById('editPrItemModal'));
    modal.show();
}

function confirmDeletePrItem(itemId, productName) {
    if (confirm('Are you sure you want to remove "' + productName + '" from this Purchase Request?')) {
        const form = document.getElementById('deletePrItemForm');
        form.action = "{{ url('purchase-requests/' . $purchaseRequest->id . '/items') }}/" + itemId;
        form.submit();
    }
}
</script>


<!-- Modal: Finance Head Send Back to GM -->
<div class="modal fade" id="financeSendBackGmModal" tabindex="-1" aria-labelledby="financeSendBackGmModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-3">
            <form action="{{ \Illuminate\Support\Facades\Route::has('purchase-requests.finance-send-back-to-gm') ? route('purchase-requests.finance-send-back-to-gm', $purchaseRequest) : url('/purchase-requests/' . $purchaseRequest->id . '/finance-send-back-to-gm') }}" method="POST">
                @csrf
                <div class="modal-header bg-warning text-dark py-3 px-4">
                    <h5 class="modal-title fs-6 fw-bold mb-0" id="financeSendBackGmModalLabel">
                        <i class="fas fa-undo me-2"></i>Send Back to General Manager
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="alert alert-warning py-2 px-3 small mb-3 border-start border-4 border-warning">
                        <i class="fas fa-exclamation-triangle me-1"></i> Returning this Purchase Request will revert the status back to <strong>Pending GM Decision</strong> and notify the General Manager for review.
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold small text-muted text-uppercase">Reason for Returning to GM <span class="text-danger">*</span></label>
                        <textarea name="reason" class="form-control" rows="3" placeholder="Please provide details (e.g. funding account insufficient, credit terms renegotiation, payment amount clarification...)" required></textarea>
                    </div>
                </div>
                <div class="modal-footer bg-light py-2 px-4">
                    <button type="button" class="btn btn-outline-secondary btn-sm px-3" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning text-dark btn-sm px-3 fw-bold shadow-sm">
                        <i class="fas fa-undo me-1"></i> Confirm &amp; Send Back to GM
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

@endsection
