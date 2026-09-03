@extends('layouts.app')
@section('title', 'PR: ' . $purchaseRequest->pr_no)

@section('content')
<div class="container-fluid px-4 py-3">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0 text-gray-800"><i class="fas fa-file-invoice text-primary me-2"></i>{{ $purchaseRequest->pr_no }}</h1>
            <p class="text-muted small mb-0">Project: <strong>{{ $purchaseRequest->project?->name ?? 'N/A' }}</strong> | Channel: <strong>{{ $purchaseRequest->materialRequest?->source ?? 'Direct PR' }}</strong></p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('procurement.my-queue') }}" class="btn btn-outline-primary"><i class="fas fa-tasks me-1"></i>My Queue</a>
            <a href="{{ route('purchase-requests.index') }}" class="btn btn-outline-secondary"><i class="fas fa-arrow-left me-1"></i>Back List</a>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show shadow-sm">{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show shadow-sm">{{ session('error') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    @endif

@php
    $authUser = auth()->user();
    $rawUserRoles = $authUser ? $authUser->roles->pluck('name')->map(fn($r) => strtolower(str_replace([' ', '-'], '_', trim($r))))->toArray() : [];
    $isGlobalAdmin = in_array('global_admin', $rawUserRoles) || in_array('admin', $rawUserRoles);
    $isAuditorUser = in_array('auditor', $rawUserRoles) || in_array('audit', $rawUserRoles) || in_array('internal_auditor', $rawUserRoles) || in_array('audit_team', $rawUserRoles) || ($authUser && $authUser->hasAnyRole(['auditor', 'audit', 'internal_auditor', 'Auditor', 'Audit']));
    
    // Check whether the logged-in user can execute action controls for the current stage
    $canActOnCurrentStage = false;
    $currentRoleName = $purchaseRequest->current_owner_role;

    if (!$isAuditorUser) {
        switch ($purchaseRequest->status) {
            case \App\Models\PurchaseRequest::STATUS_DRAFT:
                $canActOnCurrentStage = $isGlobalAdmin || ($authUser && $purchaseRequest->requested_by === $authUser->id) || in_array('coordinator', $rawUserRoles) || in_array('site_engineer', $rawUserRoles);
                break;

            case \App\Models\PurchaseRequest::STATUS_PENDING_PLANNING:
                $canActOnCurrentStage = $isGlobalAdmin || in_array('planning', $rawUserRoles) || in_array('planning_manager', $rawUserRoles);
                break;

            case \App\Models\PurchaseRequest::STATUS_PENDING_HR_APPROVAL:
                $canActOnCurrentStage = $isGlobalAdmin || in_array('coordinator', $rawUserRoles) || in_array('hr_manager', $rawUserRoles) || in_array('hr', $rawUserRoles);
                break;

            case \App\Models\PurchaseRequest::STATUS_PENDING_STORE_REVIEW:
                $canActOnCurrentStage = $isGlobalAdmin || in_array('store_manager', $rawUserRoles);
                break;

            case \App\Models\PurchaseRequest::STATUS_PENDING_PROC_MANAGER:
                $canActOnCurrentStage = $isGlobalAdmin || in_array('purchase_manager', $rawUserRoles) || in_array('procurement_manager', $rawUserRoles);
                break;

            case \App\Models\PurchaseRequest::STATUS_PENDING_PROC_TEAM:
                $canActOnCurrentStage = $isGlobalAdmin || in_array('purchase', $rawUserRoles) || in_array('procurement_team', $rawUserRoles) || in_array('purchaser', $rawUserRoles) || in_array('buyer', $rawUserRoles);
                break;

            case \App\Models\PurchaseRequest::STATUS_PENDING_MARKETING:
                $canActOnCurrentStage = $isGlobalAdmin || in_array('marketing', $rawUserRoles) || in_array('market_research', $rawUserRoles);
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
                if (!$purchaseRequest->payment?->assigned_finance_staff_id) {
                    $canActOnCurrentStage = $isGlobalAdmin || in_array('finance_head', $rawUserRoles) || in_array('finance_manager', $rawUserRoles);
                } else {
                    $canActOnCurrentStage = $isGlobalAdmin || in_array('finance_head', $rawUserRoles) || ($authUser && $purchaseRequest->payment->assigned_finance_staff_id === $authUser->id);
                }
                break;

            case \App\Models\PurchaseRequest::STATUS_PENDING_RECEIPT_UPLOAD:
                $canActOnCurrentStage = $isGlobalAdmin || in_array('purchase', $rawUserRoles) || in_array('procurement_team', $rawUserRoles) || in_array('purchaser', $rawUserRoles) || in_array('buyer', $rawUserRoles);
                break;

            case \App\Models\PurchaseRequest::STATUS_PENDING_RECEIPT_VERIFY:
                $canActOnCurrentStage = $isGlobalAdmin || in_array('finance_head', $rawUserRoles) || in_array('finance_manager', $rawUserRoles) || in_array('finance', $rawUserRoles);
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
                            @else
                                <span class="badge bg-secondary bg-opacity-10 text-dark"><i class="fas fa-user-tag me-1"></i>{{ ucfirst(str_replace('_', ' ', $purchaseRequest->current_owner_role ?? 'Completed')) }}</span>
                            @endif
                        </td></tr>
                        <tr><th class="ps-3 text-muted">Priority</th><td><span class="badge bg-{{ $purchaseRequest->priority === 'urgent' ? 'danger' : ($purchaseRequest->priority === 'high' ? 'warning' : 'secondary') }}">{{ ucfirst($purchaseRequest->priority) }}</span></td></tr>
                        <tr><th class="ps-3 text-muted">Requested By</th><td>{{ $purchaseRequest->requestedBy?->name ?? 'N/A' }}</td></tr>
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

                    <!-- STAGE 2: Store Manager Review (Transfer vs Send to PR) -->
                    @elseif($purchaseRequest->status === \App\Models\PurchaseRequest::STATUS_PENDING_STORE_REVIEW && !($isFinalIntake ?? false))
                        <div class="d-grid gap-2">
                            <button type="button" class="btn btn-primary btn-sm w-100 fw-bold shadow-sm py-2" data-bs-toggle="modal" data-bs-target="#splitAndProcessModal">
                                <i class="fas fa-random me-1"></i> Smart Split: Transfer + Buy
                            </button>
                            <button type="button" class="btn btn-outline-info btn-sm w-100 text-start py-2" onclick="openSelectiveTransferModal()">
                                <i class="fas fa-truck-ramp-box text-info me-1"></i> Transfer Selected Items (<span class="selected-items-count fw-bold">0</span>)
                            </button>
                            <button type="button" class="btn btn-outline-success btn-sm w-100 text-start py-2" onclick="openSelectiveSendPmModal()">
                                <i class="fas fa-cart-shopping text-success me-1"></i> Send Selected to Purchase (<span class="selected-items-count fw-bold">0</span>)
                            </button>
                            <form action="{{ route('purchase-requests.send-to-pm', $purchaseRequest) }}" method="POST" class="mt-2 pt-2 border-top">
                                @csrf
                                <button class="btn btn-light border btn-sm w-100 text-muted" onclick="return confirm('Send ALL items on this PR directly to Procurement Manager?');">
                                    <i class="fas fa-share me-1"></i> Send Entire PR to PM
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
                            <form action="{{ route('purchase-requests.submit-direct-buy', $purchaseRequest) }}" method="POST">
                                @csrf
                                <div class="bg-light p-2 rounded border mb-3" style="max-height: 250px; overflow-y: auto;">
                                    @foreach($purchaseRequest->items as $itm)
                                    <div class="mb-2 pb-2 border-bottom">
                                        <div class="d-flex justify-content-between align-items-center mb-1">
                                            <span class="small fw-bold text-dark">{{ $itm->product?->name ?? 'Item #' . $itm->product_id }}</span>
                                            <span class="badge bg-secondary">{{ (float)$itm->quantity }} {{ $itm->unit }}</span>
                                        </div>
                                        <div class="input-group input-group-sm">
                                            <span class="input-group-text">Unit Price (ETB)</span>
                                            <input type="number" step="0.01" min="0" 
                                                   name="item_prices[{{ $itm->id }}]" 
                                                   value="{{ $itm->estimated_unit_cost > 0 ? (float)$itm->estimated_unit_cost : '' }}" 
                                                   class="form-control form-control-sm direct-item-cost" 
                                                   data-qty="{{ (float)$itm->quantity }}"
                                                   oninput="recalculateDirectTotal()"
                                                   placeholder="0.00" required>
                                        </div>
                                    </div>
                                    @endforeach
                                </div>

                                <div class="mb-2">
                                    <label class="form-label small fw-bold text-uppercase">Total Direct Purchase Amount (ETB)</label>
                                    <input type="number" step="0.01" name="amount" id="directTotalAmountInput" class="form-control form-control-sm fw-bold bg-white" required placeholder="0.00" value="{{ $purchaseRequest->direct_buy_amount > 0 ? (float)$purchaseRequest->direct_buy_amount : (float)$purchaseRequest->estimated_total }}">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label small fw-bold text-uppercase">Procurement Notes</label>
                                    <textarea name="notes" class="form-control form-control-sm" rows="2" placeholder="Add pricing notes or remarks..."></textarea>
                                </div>
                                <button class="btn btn-success btn-sm w-100 fw-bold">
                                    <i class="fas fa-check-circle me-1"></i> Submit Material Prices
                                </button>
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

                            @if($purchaseRequest->proformaInvoices->count() > 0)
                                <div class="bg-light p-2 rounded border mb-3 small">
                                    <div class="fw-bold text-dark mb-2 pb-1 border-bottom d-flex justify-content-between align-items-center">
                                        <span>Attached Quotes ({{ $purchaseRequest->proformaInvoices->count() }}):</span>
                                        <button type="button" class="btn btn-link btn-sm p-0 text-decoration-none" data-bs-toggle="modal" data-bs-target="#attachProformaModal">+ Add Another</button>
                                    </div>
                                    @foreach($purchaseRequest->proformaInvoices as $prof)
                                        <div class="d-flex justify-content-between align-items-center py-1 border-bottom">
                                            <div>
                                                <strong>{{ $prof->supplier?->name ?? 'Supplier' }}</strong>
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
                                </form>
                            @else
                                <div class="alert alert-warning py-2 px-3 small mb-3">
                                    <i class="fas fa-exclamation-circle me-1"></i> No proformas attached yet. Click <strong>Attach Quote</strong> below to upload supplier quotation(s).
                                </div>
                                <button type="button" class="btn btn-primary btn-sm w-100 fw-bold py-2 mb-2 shadow-sm" data-bs-toggle="modal" data-bs-target="#attachProformaModal">
                                    <i class="fas fa-plus-circle me-1"></i> Attach First Proforma Quote
                                </button>
                            @endif
                        @endif

                    <!-- STAGE 5a: Marketing Review -->
                    @elseif($purchaseRequest->status === \App\Models\PurchaseRequest::STATUS_PENDING_MARKETING)
                        <div class="mb-3">
                            <h6 class="fw-bold text-dark mb-1">
                                <i class="fas fa-chart-line text-primary me-1"></i> Market Price Intelligence Review
                            </h6>
                            <p class="small text-muted mb-2">Compare Direct Buy pricing against Monthly Marketing Surveys and historical purchase prices to establish the benchmark for GM approval.</p>
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
                                <i class="fas fa-check-circle me-1"></i> Record Variance & Send to GM
                            </button>
                        </form>

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
                                            <span><strong>{{ $prof->supplier?->name }}</strong> (#{{ $prof->proforma_no }})</span>
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

                    <!-- STAGE 6: GM Decision -->
                    @elseif($purchaseRequest->status === \App\Models\PurchaseRequest::STATUS_PENDING_GM)
                        <div class="mb-3">
                            <h6 class="fw-bold text-dark mb-1">
                                <i class="fas fa-gavel text-danger me-1"></i> General Manager Decision
                            </h6>
                            <p class="small text-muted mb-2">Select approval type (Pay & Buy or Buy with Credit), send back for revision, or reject this Purchase Request.</p>
                        </div>

                        <form action="{{ \Illuminate\Support\Facades\Route::has('purchase-requests.gm-decide') ? route('purchase-requests.gm-decide', $purchaseRequest) : url('/purchase-requests/' . $purchaseRequest->id . '/gm-decide') }}" method="POST">
                            @csrf
                            <input type="hidden" name="payment_method" id="gmPaymentMethodHidden" value="pay_and_buy">

                            <div class="mb-3">
                                <label class="form-label small fw-bold text-uppercase text-dark">
                                    Select Decision / Approval Type <span class="text-danger">*</span>
                                </label>
                                <select name="decision" class="form-select form-select-sm fw-semibold" id="gmDecisionSelect" required onchange="handleGmDecisionChange(this)">
                                    <option value="">-- Choose Decision --</option>
                                    <option value="pay_and_buy" class="text-success fw-bold">✓ Approve: Pay & Buy (Cash / Bank Disbursement)</option>
                                    <option value="buy_by_credit" class="text-primary fw-bold">💳 Approve: Buy with Credit (Supplier Credit Line)</option>
                                    <option value="send_back" class="text-warning">↩ Send Back to PM (Need Revision)</option>
                                    <option value="reject" class="text-danger">✗ Reject Purchase Request</option>
                                </select>
                            </div>

                            @if($purchaseRequest->proformaInvoices->count() > 0)
                            <div class="mb-3" id="gmProformaSelectionCard">
                                <label class="form-label small fw-bold text-uppercase text-dark d-flex justify-content-between align-items-center mb-1">
                                    <span><i class="fas fa-file-invoice-dollar text-primary me-1"></i> Select Winning Proforma / Quote <span class="text-danger">*</span></span>
                                    <span class="badge bg-primary rounded-pill">{{ $purchaseRequest->proformaInvoices->count() }} Available</span>
                                </label>
                                <p class="text-muted small mb-2" style="font-size: 11px;">
                                    Choose which supplier quotation to approve and pay for:
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
                                        <label class="list-group-item list-group-item-action d-flex justify-content-between align-items-center p-2 cursor-pointer gm-prof-item {{ $isProfSelected ? 'bg-light border-primary' : '' }}" 
                                               id="gmProfItemLabel_{{ $prof->id }}"
                                               style="cursor: pointer;" onclick="syncGmProformaSelect({{ $prof->id }})">
                                            <div class="d-flex align-items-center">
                                                <input class="form-check-input me-2 mt-0 gm-proforma-radio" type="radio" name="proforma_invoice_id" value="{{ $prof->id }}" id="gmProfRadio_{{ $prof->id }}" {{ $isProfSelected ? 'checked' : '' }}>
                                                <div>
                                                    <div class="fw-bold small text-dark">{{ $prof->supplier?->name ?? ($prof->supplier_name ?: 'Vendor #' . $prof->supplier_id) }}</div>
                                                    <div class="text-muted font-monospace" style="font-size: 11px;">
                                                        <code>{{ $prof->proforma_no }}</code>
                                                        @if($isLowest)
                                                            <span class="badge bg-success-subtle text-success border border-success-subtle ms-1" style="font-size: 9px;"><i class="fas fa-arrow-down me-1"></i>Lowest Price</span>
                                                        @endif
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="text-end">
                                                <div class="fw-bold text-primary small">{{ number_format($prof->grand_total, 2) }} ETB</div>
                                                <div class="text-muted" style="font-size: 10px;">{{ count($prof->item_prices ?? []) }} items</div>
                                            </div>
                                        </label>
                                    @endforeach
                                </div>
                            </div>
                            @endif

                            {{-- Dynamic Explanatory Alerts --}}
                            <div id="gmDecisionHelpPayBuy" class="alert alert-success py-2 px-3 small border-0 shadow-xs mb-3" style="display: none;">
                                <div class="d-flex">
                                    <i class="fas fa-money-bill-wave fa-lg me-2 mt-1 text-success"></i>
                                    <div>
                                        <strong>Approve (Pay & Buy):</strong>
                                        <div class="text-muted" style="font-size: 11px;">Routes to <strong>Finance</strong> for payment disbursement and assigning staff to execute payment.</div>
                                    </div>
                                </div>
                            </div>

                            <div id="gmDecisionHelpCredit" class="alert alert-info py-2 px-3 small border-0 shadow-xs mb-3" style="display: none;">
                                <div class="d-flex">
                                    <i class="fas fa-credit-card fa-lg me-2 mt-1 text-info"></i>
                                    <div>
                                        <strong>Approve (Buy with Credit):</strong>
                                        <div class="text-muted" style="font-size: 11px;">Routes to <strong>Finance Head</strong> to authorize the Supplier Credit Line and select the Chart of Account (COA).</div>
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

                            <div class="mb-3">
                                <label class="form-label small fw-bold text-uppercase text-muted">GM Notes / Instructions / Reason</label>
                                <textarea name="notes" id="gmNotes" class="form-control form-control-sm" rows="2" placeholder="Add specific approval conditions, credit terms, or remarks..."></textarea>
                            </div>

                            <button type="submit" id="btnSubmitGmDecision" class="btn btn-secondary btn-sm w-100 fw-bold shadow-sm py-2">
                                <i class="fas fa-paper-plane me-1"></i> Submit GM Decision
                            </button>
                        </form>

                    <!-- STAGE 7a: Finance Credit Authorization (Auto-COA 5110) -->
                    @elseif($purchaseRequest->status === \App\Models\PurchaseRequest::STATUS_PENDING_FINANCE)
                        <form action="{{ route('purchase-requests.finance-credit-approve', $purchaseRequest) }}" method="POST">
                            @csrf
                            <div class="alert alert-info py-2 px-3 small mb-3 border-start border-4 border-info">
                                <div class="fw-bold mb-1"><i class="fas fa-credit-card me-1"></i> Credit Purchase (COA 5110)</div>
                                <div class="text-muted">Account: <strong class="text-dark">Cost Of Material By Credit 5110</strong></div>
                                <div class="text-muted mt-1">This request will be routed directly to the <strong>Store Manager</strong> for material intake and tracked in the <strong>Credit Store Ledger</strong>.</div>
                            </div>

                            @php
                                $creditVal = (float)($purchaseRequest->direct_buy_amount ?? 0);
                                if ($creditVal <= 0) {
                                    $selProf = $purchaseRequest->proformaInvoices()->where('gm_selected', true)->first() ?? $purchaseRequest->proformaInvoices()->latest()->first();
                                    $creditVal = $selProf && (float)$selProf->grand_total > 0 ? (float)$selProf->grand_total : (float)$purchaseRequest->items->sum(fn($i) => (float)$i->quantity * (float)($i->estimated_unit_price ?? $i->unit_price ?? 0));
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
                    @elseif($purchaseRequest->status === \App\Models\PurchaseRequest::STATUS_PENDING_PAYMENT && !$purchaseRequest->payment?->assigned_finance_staff_id)
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
                                <input type="number" step="0.01" name="amount" id="fundingPaymentAmountInput" class="form-control form-control-sm fw-bold" value="{{ (float)($purchaseRequest->payment?->amount ?? $purchaseRequest->direct_buy_amount ?? 0) }}" required>
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

                    <!-- STAGE 7b: Finance Staff Execute Payment -->
                    @elseif($purchaseRequest->status === \App\Models\PurchaseRequest::STATUS_PENDING_PAYMENT && $purchaseRequest->payment?->assigned_finance_staff_id)
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
                                <strong class="text-dark">{{ $purchaseRequest->payment->coaAccount?->name }} ({{ $purchaseRequest->payment->coaAccount?->code }})</strong>
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
                        <div class="mb-3">
                            <h6 class="fw-bold text-primary mb-1">
                                <i class="fas fa-file-invoice me-1"></i> Upload Purchase Receipt
                            </h6>
                            <p class="small text-muted mb-0">
                                Payment of <strong>{{ number_format($purchaseRequest->payment?->amount ?? $purchaseRequest->direct_buy_amount, 2) }} ETB</strong> disbursed. Upload vendor receipt and send directly to Store Manager for material intake.
                            </p>
                        </div>
                        <form action="{{ route('purchase-requests.upload-receipt', $purchaseRequest) }}" method="POST" enctype="multipart/form-data">
                            @csrf
                            <div class="mb-2">
                                <label class="form-label small fw-bold text-uppercase text-muted">Official Vendor Receipt (PDF / Image) <span class="text-danger">*</span></label>
                                <input type="file" name="receipt_file" class="form-control form-control-sm" accept=".pdf,.jpg,.jpeg,.png,.webp" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label small fw-bold text-uppercase text-muted">Receipt Notes / Reference</label>
                                <textarea name="notes" class="form-control form-control-sm" rows="2" placeholder="Cash sales invoice #, receipt date, or purchase notes..."></textarea>
                            </div>
                            <button class="btn btn-primary btn-sm w-100 fw-bold py-2 shadow-sm">
                                <i class="fas fa-paper-plane me-1"></i> Upload Receipt & Send to Store Manager
                            </button>
                        </form>

                    <!-- STAGE 8: Verify Receipt -->
                    @elseif($purchaseRequest->status === \App\Models\PurchaseRequest::STATUS_PENDING_RECEIPT_VERIFY)
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
                            <button class="btn btn-success btn-sm w-100">Submit Verification</button>
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

                    <!-- STAGE 9 Final: Store Manager Final Intake & Stock In -->
                    @elseif($purchaseRequest->status === \App\Models\PurchaseRequest::STATUS_PENDING_STORE_REVIEW && ($isFinalIntake ?? false))
                        <div class="mb-3">
                            <h6 class="fw-bold text-success mb-1">
                                <i class="fas fa-boxes-packing text-success me-1"></i> Receive Products & Stock In
                            </h6>
                            <p class="small text-muted mb-0">
                                Verify received quantities, assign the receiving slip (GRN / Model 19), and intake products directly into store inventory.
                            </p>
                        </div>

                        <form action="{{ route('purchase-requests.store-intake', $purchaseRequest) }}" method="POST">
                            @csrf

                            <div class="mb-2">
                                <label class="form-label small fw-bold text-uppercase text-muted">Receiving Store <span class="text-danger">*</span></label>
                                <select name="store_id" id="intakeStoreSelect" class="form-select form-select-sm" required onchange="fetchStoreSlipSequence(this.value)">
                                    @foreach($stores as $st)
                                        <option value="{{ $st->id }}" {{ ($purchaseRequest->store_id == $st->id) ? 'selected' : '' }}>
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
                                           class="form-control form-control-sm font-monospace fw-bold" 
                                           data-start="{{ $receiveSlipSequence?->book_start_no ?? '' }}"
                                           data-end="{{ $receiveSlipSequence?->book_end_no ?? '' }}"
                                           data-prefix="{{ $receiveSlipSequence?->prefix ?? '' }}"
                                           oninput="validateIntakeSlipRange()"
                                           value="{{ $nextReceiveSlipNo ?? ('REC-' . date('Ymd') . '-' . str_pad($purchaseRequest->id, 4, '0', STR_PAD_LEFT)) }}" required>
                                </div>
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

                            <div class="mb-3">
                                <label class="form-label small fw-bold text-uppercase text-muted mb-1">Item Quantities To Receive</label>
                                <div class="bg-light p-2 rounded border" style="max-height: 220px; overflow-y: auto;">
                                    @foreach($purchaseRequest->items as $itm)
                                        <div class="mb-2 pb-2 border-bottom">
                                            <div class="d-flex justify-content-between align-items-center mb-1">
                                                <span class="small fw-bold text-dark">{{ $itm->product?->name ?? 'Material #' . $itm->product_id }}</span>
                                                <span class="badge bg-primary text-white">{{ (float)$itm->quantity }} {{ $itm->unit ?? ($itm->product?->unit ?? 'pcs') }}</span>
                                            </div>
                                            <div class="row g-1">
                                                <div class="col-6">
                                                    <label class="text-muted" style="font-size: 10px;">Qty Received</label>
                                                    <input type="number" step="0.01" min="0" 
                                                           name="items[{{ $itm->id }}][quantity]" 
                                                           value="{{ (float)$itm->quantity }}" 
                                                           class="form-control form-control-sm" required>
                                                </div>
                                                <div class="col-6">
                                                    <label class="text-muted" style="font-size: 10px;">Accepted Qty</label>
                                                    <input type="number" step="0.01" min="0" 
                                                           name="items[{{ $itm->id }}][accepted_quantity]" 
                                                           value="{{ (float)$itm->quantity }}" 
                                                           class="form-control form-control-sm" required>
                                                </div>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label small fw-bold text-uppercase text-muted">Intake Notes / Remarks</label>
                                <textarea name="notes" class="form-control form-control-sm" rows="2" placeholder="Package condition, delivery verification notes..."></textarea>
                            </div>

                            <button type="submit" class="btn btn-success btn-sm w-100 fw-bold shadow-sm py-2">
                                <i class="fas fa-check-double me-1"></i> Confirm Intake & Add to Inventory
                            </button>
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
                    @if(in_array($purchaseRequest->status, [\App\Models\PurchaseRequest::STATUS_INTAKE_COMPLETE, \App\Models\PurchaseRequest::STATUS_COMPLETED, \App\Models\PurchaseRequest::STATUS_TRANSFERRED]))
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
                    \App\Models\PurchaseRequest::STATUS_PENDING_PROC_MANAGER
                ]);
            @endphp
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white font-weight-bold py-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div class="d-flex align-items-center gap-2">
                        <span class="fw-bold fs-6"><i class="fas fa-boxes text-primary me-2"></i>Requested Items</span>
                        <span class="badge bg-secondary rounded-pill">{{ $purchaseRequest->items->count() }} items</span>
                    </div>

                    @if($isSelectableStage)
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
                        @endif
                    @endif
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
                                    <th>Qty Requested</th>
                                    <th>Unit</th>
                                    <th>Est. Unit Cost</th>
                                    <th>Est. Total</th>
                                    @if($isSelectableStage)
                                    <th class="pe-3 text-end">Quick Action</th>
                                    @endif
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
                                @endphp
                                <tr id="itemRow{{ $item->id }}">
                                    @if($isSelectableStage)
                                    <td class="ps-3 text-center">
                                        <input type="checkbox" class="form-check-input pr-item-checkbox" 
                                               value="{{ $item->id }}"
                                               data-item-id="{{ $item->id }}"
                                               data-product-id="{{ $item->product_id }}"
                                               data-product-name="{{ $item->product?->name ?? 'Item #' . $item->product_id }}"
                                               data-quantity="{{ (float)$item->quantity }}"
                                               data-unit="{{ $item->unit }}"
                                               data-has-stock="{{ ($totalNetStock > 0) ? '1' : '0' }}"
                                               data-stock-qty="{{ $totalNetStock }}"
                                               onchange="updateSelectionToolbar()">
                                    </td>
                                    @endif
                                    <td>
                                        <strong class="text-dark">{{ $item->product?->name ?? 'Item #' . $item->product_id }}</strong>
                                        @if($item->product?->code)
                                             <br><code class="small text-muted">{{ $item->product->code }}</code>
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
                                                            {{ $st->store?->name }}: {{ number_format($st->net_available, 1) }} | 
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
                                    <td class="fw-bold text-dark fs-6">{{ number_format($item->quantity, 3) }}</td>
                                    <td><span class="badge bg-light text-dark border">{{ $item->unit }}</span></td>
                                    <td>{{ number_format($item->estimated_unit_cost ?? 0, 2) }} ETB</td>
                                    <td class="fw-bold text-primary">{{ number_format($item->estimated_total ?? 0, 2) }} ETB</td>
                                    @if($isSelectableStage)
                                    <td class="pe-3 text-end">
                                        @if($purchaseRequest->status === \App\Models\PurchaseRequest::STATUS_PENDING_STORE_REVIEW)
                                        <div class="btn-group btn-group-sm">
                                            @if($totalNetStock > 0 || $totalRawStock > 0)
                                            <button type="button" class="btn btn-outline-info" title="Quick Transfer this Item" onclick="quickTransferSingleItem({{ $item->id }})">
                                                <i class="fas fa-truck-ramp-box"></i> Transfer
                                            </button>
                                            @endif

                                            <button type="button" class="btn btn-outline-success" title="Quick Purchase this Item" onclick="quickPurchaseSingleItem({{ $item->id }})">
                                                <i class="fas fa-cart-shopping"></i> Purchase
                                            </button>
                                        </div>
                                        @elseif($purchaseRequest->status === \App\Models\PurchaseRequest::STATUS_PENDING_PROC_MANAGER)
                                        <div class="btn-group btn-group-sm">
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
                                        </div>
                                        @endif
                                    </td>
                                    @endif
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

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
                                        <strong class="text-dark">{{ $prof->supplier?->name ?? 'N/A' }}</strong>
                                        @if($prof->supplier?->phone)
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
                                                    <i class="fas fa-file-invoice me-1 text-primary"></i> Item Price Breakdown: <strong>{{ $prof->supplier?->name }}</strong> (Quote #{{ $prof->proforma_no }})
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

            <!-- Uploaded Purchase Receipt (Visible to Requester & All Hierarchy Roles) -->
            @if($purchaseRequest->receipt)
                @php
                    $receiptUrl = \App\Services\FileUploadService::url($purchaseRequest->receipt->file_path);
                    $ext = strtolower(pathinfo($purchaseRequest->receipt->file_path, PATHINFO_EXTENSION));
                    $isImage = in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif']);
                    $isPdf = $ext === 'pdf';
                    $vStatus = $purchaseRequest->receipt->verification_status ?? 'pending';
                    $vBadge = match($vStatus) {
                        'verified' => ['class' => 'bg-success', 'label' => 'Verified & Approved by Finance', 'icon' => 'fa-circle-check'],
                        'rejected' => ['class' => 'bg-danger', 'label' => 'Receipt Rejected', 'icon' => 'fa-circle-xmark'],
                        default    => ['class' => 'bg-warning text-dark', 'label' => 'Pending Finance Verification', 'icon' => 'fa-clock'],
                    };
                @endphp
                <div class="card border-0 shadow-sm mb-4 border-start border-4 border-success">
                    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <div>
                            <h6 class="font-weight-bold text-dark mb-0">
                                <i class="fas fa-receipt text-success me-2"></i>Official Purchase Receipt / Payment Proof
                            </h6>
                            <small class="text-muted">
                                Uploaded by <strong>{{ $purchaseRequest->receipt->uploadedBy->name ?? 'Procurement Officer' }}</strong>
                                on {{ $purchaseRequest->receipt->created_at->format('M d, Y H:i') }}
                            </small>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <span class="badge {{ $vBadge['class'] }} px-2 py-1">
                                <i class="fas {{ $vBadge['icon'] }} me-1"></i>{{ $vBadge['label'] }}
                            </span>
                            @if($receiptUrl)
                                <a href="{{ $receiptUrl }}" class="btn btn-sm btn-outline-primary shadow-sm" target="_blank" download>
                                    <i class="fas fa-download me-1"></i> Download
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
                                    <span class="text-muted">(by {{ $purchaseRequest->receipt->verifiedBy->name }} on {{ $purchaseRequest->receipt->verified_at?->format('M d, Y H:i') }})</span>
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
                        @else
                            <div class="text-muted small italic p-2 bg-light rounded">Receipt file path recorded, but media link is currently unavailable.</div>
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
                                        <td><strong>{{ $item->product?->name ?? 'Item #' . $item->product_id }}</strong></td>
                                        <td>{{ $st->store?->name ?? 'N/A' }}</td>
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
                                        <td><strong>{{ $item->product?->name ?? 'Item #' . $item->product_id }}</strong></td>
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
                                        <br><small class="text-muted">{{ $tr->created_at?->format('M d, Y H:i') }}</small>
                                    </td>
                                    <td><span class="badge bg-light text-dark border">{{ $tr->fromStore?->name ?? 'N/A' }}</span></td>
                                    <td><span class="badge bg-light text-dark border">{{ $tr->toStore?->name ?? 'N/A' }}</span></td>
                                    <td>
                                        @foreach($tr->items as $tItem)
                                            <div class="small">
                                                <strong>{{ $tItem->product?->name ?? 'Item' }}</strong>: 
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
                                <strong>{{ $log->actor?->name }}</strong> ({{ ucfirst(str_replace('_', ' ', $log->actor_role)) }}) moved stage to 
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
                                        <strong>{{ $item->product?->name ?? 'Item #' . $item->product_id }}</strong>
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
                                                        $stStock = $itemStocks->where('store_id', $st->id)->first()?->quantity_on_hand ?? 0;
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
                            <input type="text" name="reason" class="form-control form-control-sm" value="Store Transfer from PR #{{ $purchaseRequest->pr_no }} ({{ $purchaseRequest->project?->name ?? 'Project' }})">
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
                                                        <div class="fw-bold text-dark">{{ $itm->product?->name ?? ('Item #' . $itm->product_id) }}</div>
                                                        @if($itm->product?->code)
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
function handleGmDecisionChange(select) {
    if (!select) return;
    const val = select.value;
    const hiddenPay = document.getElementById('gmPaymentMethodHidden');
    const helpPay = document.getElementById('gmDecisionHelpPayBuy');
    const helpCredit = document.getElementById('gmDecisionHelpCredit');
    const helpSendBack = document.getElementById('gmDecisionHelpSendBack');
    const helpReject = document.getElementById('gmDecisionHelpReject');
    const btn = document.getElementById('btnSubmitGmDecision');

    // Hide all help alerts
    if (helpPay) helpPay.style.display = 'none';
    if (helpCredit) helpCredit.style.display = 'none';
    if (helpSendBack) helpSendBack.style.display = 'none';
    if (helpReject) helpReject.style.display = 'none';

    if (val === 'buy_by_credit' || val === 'approve_credit') {
        if (hiddenPay) hiddenPay.value = 'buy_by_credit';
        if (helpCredit) helpCredit.style.display = 'block';
        if (btn) {
            btn.className = 'btn btn-info text-white btn-sm w-100 fw-bold shadow-sm py-2';
            btn.innerHTML = '<i class="fas fa-credit-card me-1"></i> Approve (Buy with Credit)';
        }
    } else if (val === 'pay_and_buy' || val === 'approve') {
        if (hiddenPay) hiddenPay.value = 'pay_and_buy';
        if (helpPay) helpPay.style.display = 'block';
        if (btn) {
            btn.className = 'btn btn-success btn-sm w-100 fw-bold shadow-sm py-2';
            btn.innerHTML = '<i class="fas fa-check-circle me-1"></i> Approve (Pay & Buy)';
        }
    } else if (val === 'send_back') {
        if (hiddenPay) hiddenPay.value = '';
        if (helpSendBack) helpSendBack.style.display = 'block';
        if (btn) {
            btn.className = 'btn btn-warning text-dark btn-sm w-100 fw-bold shadow-sm py-2';
            btn.innerHTML = '<i class="fas fa-undo me-1"></i> Send Back to PM';
        }
    } else if (val === 'reject') {
        if (hiddenPay) hiddenPay.value = '';
        if (helpReject) helpReject.style.display = 'block';
        if (btn) {
            btn.className = 'btn btn-danger btn-sm w-100 fw-bold shadow-sm py-2';
            btn.innerHTML = '<i class="fas fa-ban me-1"></i> Reject Purchase Request';
        }
    } else {
        if (btn) {
            btn.className = 'btn btn-secondary btn-sm w-100 fw-bold shadow-sm py-2';
            btn.innerHTML = '<i class="fas fa-paper-plane me-1"></i> Submit GM Decision';
        }
    }
}

function syncGmProformaSelect(profId) {
    if (!profId) return;
    const radio = document.getElementById('gmProfRadio_' + profId);
    if (radio) {
        radio.checked = true;
    }
    document.querySelectorAll('.gm-prof-item').forEach(el => {
        el.classList.remove('bg-light', 'border-primary');
    });
    const label = document.getElementById('gmProfItemLabel_' + profId);
    if (label) {
        label.classList.add('bg-light', 'border-primary');
    }
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

function fetchStoreSlipSequence(storeId) {
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
            slipInput.value = data.formatted_slip;
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
                    <span class="badge bg-success">${data.remaining} slips left</span>
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
    let total = 0;
    document.querySelectorAll('.direct-item-cost').forEach(input => {
        const val = parseFloat(input.value) || 0;
        const qty = parseFloat(input.dataset.qty) || 0;
        total += (val * qty);
    });
    const totalInput = document.getElementById('directTotalAmountInput');
    if (totalInput && total > 0) {
        totalInput.value = total.toFixed(2);
    }
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
</script>
@endsection
