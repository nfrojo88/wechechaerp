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
    
    // Check whether the logged-in user can execute action controls for the current stage
    $canActOnCurrentStage = false;
    $currentRoleName = $purchaseRequest->current_owner_role;

    switch ($purchaseRequest->status) {
        case \App\Models\PurchaseRequest::STATUS_DRAFT:
            $canActOnCurrentStage = $isGlobalAdmin || ($authUser && $purchaseRequest->requested_by === $authUser->id) || in_array('coordinator', $rawUserRoles) || in_array('site_engineer', $rawUserRoles);
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
@endphp

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
                        <tr><th class="ps-3 text-muted">Current Role Owner</th><td><span class="badge bg-secondary bg-opacity-10 text-dark"><i class="fas fa-user-tag me-1"></i>{{ ucfirst(str_replace('_', ' ', $purchaseRequest->current_owner_role ?? 'Completed')) }}</span></td></tr>
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
                    @elseif($purchaseRequest->status === \App\Models\PurchaseRequest::STATUS_PENDING_STORE_REVIEW && !$purchaseRequest->driverBooking)
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

                    <!-- STAGE 3: Procurement Manager Triage -->
                    @elseif($purchaseRequest->status === \App\Models\PurchaseRequest::STATUS_PENDING_PROC_MANAGER)
                        <div class="d-grid gap-2">
                            <form action="{{ route('purchase-requests.send-to-proc-team', $purchaseRequest) }}" method="POST">
                                @csrf
                                <button class="btn btn-primary btn-sm w-100 mb-2"><i class="fas fa-user-check me-1"></i> Send to Procurement Team for Sourcing</button>
                            </form>
                            <button class="btn btn-outline-danger btn-sm w-100" data-bs-toggle="collapse" data-bs-target="#sendBackStoreForm">
                                <i class="fas fa-undo me-1"></i> Send Back to Store Manager
                            </button>
                            <div class="collapse mt-2" id="sendBackStoreForm">
                                <form action="{{ route('purchase-requests.send-back-to-store', $purchaseRequest) }}" method="POST">
                                    @csrf
                                    <textarea name="reason" class="form-control form-control-sm mb-2" placeholder="Reason for sending back..." required></textarea>
                                    <button class="btn btn-danger btn-sm w-100">Confirm Send Back</button>
                                </form>
                            </div>
                        </div>

                    <!-- STAGE 4: Procurement Team Sourcing (Direct Buy vs Proforma) -->
                    @elseif($purchaseRequest->status === \App\Models\PurchaseRequest::STATUS_PENDING_PROC_TEAM)
                        <h6 class="font-weight-bold">Select Sourcing Path:</h6>
                        <ul class="nav nav-pills nav-justified mb-3" id="sourcingTab" role="tablist">
                            <li class="nav-item"><button class="nav-link active btn-sm" data-bs-toggle="tab" data-bs-target="#tabDirect">Direct Buy</button></li>
                            <li class="nav-item"><button class="nav-link btn-sm" data-bs-toggle="tab" data-bs-target="#tabProforma">Proforma</button></li>
                        </ul>
                        <div class="tab-content" id="sourcingTabContent">
                            <div class="tab-pane fade show active" id="tabDirect">
                                <form action="{{ route('purchase-requests.submit-direct-buy', $purchaseRequest) }}" method="POST">
                                    @csrf
                                    <div class="mb-2">
                                        <label class="form-label small">Direct Purchase Amount (ETB)</label>
                                        <input type="number" step="0.01" name="amount" class="form-control form-control-sm" required>
                                    </div>
                                    <div class="mb-2">
                                        <label class="form-label small">Notes</label>
                                        <textarea name="notes" class="form-control form-control-sm" rows="2"></textarea>
                                    </div>
                                    <button class="btn btn-success btn-sm w-100">Submit Direct Buy</button>
                                </form>
                            </div>
                            <div class="tab-pane fade" id="tabProforma">
                                <form action="{{ route('purchase-requests.submit-proformas', $purchaseRequest) }}" method="POST">
                                    @csrf
                                    <p class="small text-muted mb-2">Upload quotes in the Proforma Invoices tab on the right, then click submit.</p>
                                    <button class="btn btn-primary btn-sm w-100">Submit Proformas to PM</button>
                                </form>
                            </div>
                        </div>

                    <!-- STAGE 5a: Marketing Review -->
                    @elseif($purchaseRequest->status === \App\Models\PurchaseRequest::STATUS_PENDING_MARKETING)
                        <form action="{{ route('purchase-requests.add-marketing-variance', $purchaseRequest) }}" method="POST">
                            @csrf
                            <div class="mb-2">
                                <label class="form-label small">Direct Amount Submitted</label>
                                <input type="text" class="form-control form-control-sm bg-light" value="{{ number_format($purchaseRequest->direct_buy_amount, 2) }} ETB" readonly>
                            </div>
                            <div class="mb-2">
                                <label class="form-label small">Current Market Price Benchmark (ETB)</label>
                                <input type="number" step="0.01" name="market_price" class="form-control form-control-sm" required>
                            </div>
                            <div class="mb-2">
                                <label class="form-label small">Variance Notes</label>
                                <textarea name="variance_notes" class="form-control form-control-sm" rows="2"></textarea>
                            </div>
                            <button class="btn btn-primary btn-sm w-100">Record Variance & Send to GM</button>
                        </form>

                    <!-- STAGE 5b: PM Proforma Selection -->
                    @elseif($purchaseRequest->status === \App\Models\PurchaseRequest::STATUS_PENDING_PROFORMA_SELECTION)
                        <form action="{{ route('purchase-requests.select-proformas', $purchaseRequest) }}" method="POST">
                            @csrf
                            <p class="small text-muted mb-2">Select proformas from the table on the right to forward to GM.</p>
                            <button class="btn btn-primary btn-sm w-100">Send Selected Proformas to GM</button>
                        </form>

                    <!-- STAGE 6: GM Decision -->
                    @elseif($purchaseRequest->status === \App\Models\PurchaseRequest::STATUS_PENDING_GM)
                        <form action="{{ route('purchase-requests.gm-decide', $purchaseRequest) }}" method="POST">
                            @csrf
                            <div class="mb-2">
                                <label class="form-label small font-weight-bold">Decision</label>
                                <select name="decision" class="form-select form-select-sm" id="gmDecisionSelect" required>
                                    <option value="">-- Choose Decision --</option>
                                    <option value="approve">Approve</option>
                                    <option value="reject">Reject</option>
                                    <option value="send_back">Send Back to PM</option>
                                </select>
                            </div>
                            <div class="mb-2 d-none" id="paymentMethodDiv">
                                <label class="form-label small font-weight-bold">Payment Route</label>
                                <select name="payment_method" class="form-select form-select-sm">
                                    <option value="pay_and_buy">Pay & Buy (Cash/Bank)</option>
                                    <option value="buy_by_credit">Buy by Credit</option>
                                </select>
                            </div>
                            <div class="mb-2">
                                <label class="form-label small">Notes / Reason</label>
                                <textarea name="notes" class="form-control form-control-sm" rows="2"></textarea>
                            </div>
                            <button class="btn btn-danger btn-sm w-100">Submit GM Decision</button>
                        </form>

                    <!-- STAGE 7a: Finance Credit Authorization -->
                    @elseif($purchaseRequest->status === \App\Models\PurchaseRequest::STATUS_PENDING_FINANCE)
                        <form action="{{ route('purchase-requests.finance-credit-approve', $purchaseRequest) }}" method="POST">
                            @csrf
                            <div class="mb-2">
                                <label class="form-label small font-weight-bold">Select Chart of Account (COA)</label>
                                <select name="coa_account_id" class="form-select form-select-sm" required>
                                    <option value="">-- Select COA --</option>
                                    @foreach($coaAccounts as $coa)
                                        <option value="{{ $coa->id }}">{{ $coa->code }} - {{ $coa->name }} (Bal: {{ number_format($coa->current_balance, 2) }})</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="mb-2">
                                <label class="form-label small font-weight-bold">Credit Amount</label>
                                <input type="number" step="0.01" name="amount" class="form-control form-control-sm" value="{{ $purchaseRequest->direct_buy_amount }}" required>
                            </div>
                            <button class="btn btn-info text-white btn-sm w-100">Authorize Credit Line</button>
                        </form>

                    <!-- STAGE 7b: Finance Head Payment Assignment -->
                    @elseif($purchaseRequest->status === \App\Models\PurchaseRequest::STATUS_PENDING_PAYMENT && !$purchaseRequest->payment?->assigned_finance_staff_id)
                        <form action="{{ route('purchase-requests.assign-payment', $purchaseRequest) }}" method="POST">
                            @csrf
                            <div class="mb-2">
                                <label class="form-label small font-weight-bold">Select Funding Account (COA)</label>
                                <select name="coa_account_id" class="form-select form-select-sm" required>
                                    <option value="">-- Select COA --</option>
                                    @foreach($coaAccounts as $coa)
                                        <option value="{{ $coa->id }}">{{ $coa->code }} - {{ $coa->name }} (Bal: {{ number_format($coa->current_balance, 2) }})</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="mb-2">
                                <label class="form-label small font-weight-bold">Payment Amount</label>
                                <input type="number" step="0.01" name="amount" class="form-control form-control-sm" value="{{ $purchaseRequest->direct_buy_amount }}" required>
                            </div>
                            <div class="mb-2">
                                <label class="form-label small font-weight-bold">Assign Finance Staff</label>
                                <select name="staff_user_id" class="form-select form-select-sm" required>
                                    <option value="">-- Select Staff Member --</option>
                                    @foreach($financeStaff as $st)
                                        <option value="{{ $st->id }}">{{ $st->name }} ({{ $st->email }})</option>
                                    @endforeach
                                </select>
                            </div>
                            <button class="btn btn-primary btn-sm w-100">Assign Payment Task</button>
                        </form>

                    <!-- STAGE 7b: Finance Staff Execute Payment -->
                    @elseif($purchaseRequest->status === \App\Models\PurchaseRequest::STATUS_PENDING_PAYMENT && $purchaseRequest->payment?->assigned_finance_staff_id)
                        <form action="{{ route('purchase-requests.execute-payment', $purchaseRequest) }}" method="POST">
                            @csrf
                            <div class="alert alert-info py-2 small mb-2">
                                Amount: <strong>{{ number_format($purchaseRequest->payment->amount, 2) }} ETB</strong><br>
                                Account: <strong>{{ $purchaseRequest->payment->coaAccount?->name }}</strong>
                            </div>
                            <button class="btn btn-success btn-sm w-100"><i class="fas fa-check-double me-1"></i> Confirm & Execute Payment</button>
                        </form>

                    <!-- STAGE 8: Upload Receipt -->
                    @elseif($purchaseRequest->status === \App\Models\PurchaseRequest::STATUS_PENDING_RECEIPT_UPLOAD)
                        <form action="{{ route('purchase-requests.upload-receipt', $purchaseRequest) }}" method="POST" enctype="multipart/form-data">
                            @csrf
                            <div class="mb-2">
                                <label class="form-label small font-weight-bold">Upload Purchase Receipt (PDF/Image)</label>
                                <input type="file" name="receipt_file" class="form-control form-control-sm" required>
                            </div>
                            <button class="btn btn-primary btn-sm w-100"><i class="fas fa-upload me-1"></i> Upload Receipt</button>
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

                    <!-- STAGE 9 Final: Store Manager Final Intake -->
                    @elseif($purchaseRequest->status === \App\Models\PurchaseRequest::STATUS_PENDING_STORE_REVIEW && $purchaseRequest->driverBooking)
                        <form action="{{ route('purchase-requests.store-intake', $purchaseRequest) }}" method="POST">
                            @csrf
                            <p class="small text-muted mb-2">Driver <strong>{{ $purchaseRequest->driverBooking->driver?->full_name }}</strong> has arrived. Perform final intake.</p>
                            <button class="btn btn-success btn-sm w-100"><i class="fas fa-box-open me-1"></i> Complete Final Intake</button>
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
                        <p class="small text-muted mb-0">This purchase request was rejected during the review cycle.</p>
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
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white font-weight-bold py-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div class="d-flex align-items-center gap-2">
                        <span class="fw-bold fs-6"><i class="fas fa-boxes text-primary me-2"></i>Requested Items</span>
                        <span class="badge bg-secondary rounded-pill">{{ $purchaseRequest->items->count() }} items</span>
                    </div>

                    @if($canActOnCurrentStage && $purchaseRequest->status === \App\Models\PurchaseRequest::STATUS_PENDING_STORE_REVIEW)
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
                    @endif
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table align-middle mb-0" id="requestedItemsTable">
                            <thead class="table-light">
                                <tr>
                                    @if($canActOnCurrentStage && $purchaseRequest->status === \App\Models\PurchaseRequest::STATUS_PENDING_STORE_REVIEW)
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
                                    @if($canActOnCurrentStage && $purchaseRequest->status === \App\Models\PurchaseRequest::STATUS_PENDING_STORE_REVIEW)
                                    <th class="pe-3 text-end">Quick Action</th>
                                    @endif
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($purchaseRequest->items as $item)
                                @php
                                    $itemStocks = $stockAvailability[$item->product_id] ?? collect();
                                    $totalStock = $itemStocks->sum('quantity_on_hand');
                                    $hasStock = $totalStock > 0;
                                @endphp
                                <tr id="itemRow{{ $item->id }}">
                                    @if($canActOnCurrentStage && $purchaseRequest->status === \App\Models\PurchaseRequest::STATUS_PENDING_STORE_REVIEW)
                                    <td class="ps-3 text-center">
                                        <input type="checkbox" class="form-check-input pr-item-checkbox" 
                                               value="{{ $item->id }}"
                                               data-item-id="{{ $item->id }}"
                                               data-product-id="{{ $item->product_id }}"
                                               data-product-name="{{ $item->product?->name ?? 'Item #' . $item->product_id }}"
                                               data-quantity="{{ (float)$item->quantity }}"
                                               data-unit="{{ $item->unit }}"
                                               data-has-stock="{{ $hasStock ? '1' : '0' }}"
                                               data-stock-qty="{{ $totalStock }}"
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
                                        @if($hasStock)
                                            <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 py-1 px-2">
                                                <i class="fas fa-warehouse me-1"></i> In Stock: {{ number_format($totalStock, 1) }} {{ $item->unit }}
                                            </span>
                                            <div class="small text-muted mt-1" style="font-size: 11px;">
                                                @foreach($itemStocks as $st)
                                                    {{ $st->store?->name }}: {{ number_format($st->quantity_on_hand, 1) }} | 
                                                @endforeach
                                            </div>
                                        @else
                                            <span class="badge bg-light text-muted border py-1 px-2">
                                                <i class="fas fa-circle-xmark me-1 text-secondary"></i> Out of Stock
                                            </span>
                                        @endif
                                    </td>
                                    <td class="fw-bold text-dark fs-6">{{ number_format($item->quantity, 3) }}</td>
                                    <td><span class="badge bg-light text-dark border">{{ $item->unit }}</span></td>
                                    <td>{{ number_format($item->estimated_unit_cost ?? 0, 2) }} ETB</td>
                                    <td class="fw-bold text-primary">{{ number_format($item->estimated_total ?? 0, 2) }} ETB</td>
                                    @if($canActOnCurrentStage && $purchaseRequest->status === \App\Models\PurchaseRequest::STATUS_PENDING_STORE_REVIEW)
                                    <td class="pe-3 text-end">
                                        <div class="btn-group btn-group-sm">
                                            @if($hasStock)
                                            <button type="button" class="btn btn-outline-info" title="Quick Transfer this Item" onclick="quickTransferSingleItem({{ $item->id }})">
                                                <i class="fas fa-truck-ramp-box"></i> Transfer
                                            </button>
                                            @endif
                                            <button type="button" class="btn btn-outline-success" title="Quick Purchase this Item" onclick="quickPurchaseSingleItem({{ $item->id }})">
                                                <i class="fas fa-cart-shopping"></i> Purchase
                                            </button>
                                        </div>
                                    </td>
                                    @endif
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

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
                <div class="card-header bg-white font-weight-bold py-3 border-0">
                    <i class="fas fa-warehouse text-warning me-2"></i>Real-time Cross-Store Inventory View
                </div>
                <div class="card-body p-0">
                    <table class="table table-sm align-middle mb-0">
                        <thead class="table-light"><tr><th>Product</th><th>Store Name</th><th>Qty Available</th><th>Status</th></tr></thead>
                        <tbody>
                            @foreach($purchaseRequest->items as $item)
                                @php $stocks = $stockAvailability[$item->product_id] ?? collect(); @endphp
                                @forelse($stocks as $st)
                                <tr>
                                    <td>{{ $item->product?->name ?? 'Item #' . $item->product_id }}</td>
                                    <td>{{ $st->store?->name ?? 'N/A' }}</td>
                                    <td><strong class="text-success">{{ $st->quantity_on_hand }}</strong> {{ $item->unit }}</td>
                                    <td><span class="badge bg-success">In Stock</span></td>
                                </tr>
                                @empty
                                <tr>
                                    <td>{{ $item->product?->name ?? 'Item #' . $item->product_id }}</td>
                                    <td colspan="3" class="text-muted italic">No stock available across any stores.</td>
                                </tr>
                                @endforelse
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

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
            <form action="{{ route('purchase-requests.selective-transfer', $purchaseRequest) }}" method="POST">
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
            <form action="{{ route('purchase-requests.selective-send-to-pm', $purchaseRequest) }}" method="POST">
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

<script>
document.addEventListener('DOMContentLoaded', function() {
    // GM Decision script
    const gmSelect = document.getElementById('gmDecisionSelect');
    const payDiv   = document.getElementById('paymentMethodDiv');
    if (gmSelect && payDiv) {
        gmSelect.addEventListener('change', function() {
            if (this.value === 'approve') {
                payDiv.classList.remove('d-none');
            } else {
                payDiv.classList.add('d-none');
            }
        });
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
</script>
@endsection
