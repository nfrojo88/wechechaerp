@extends('layouts.app')
@section('title', 'Office Supply Request #' . $office_request->pr_no)

@section('content')
<div class="container-fluid py-3">
    <!-- Header & Action Buttons -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-1">
                    <li class="breadcrumb-item"><a href="{{ \Illuminate\Support\Facades\Route::has('office-requests.index') ? route('office-requests.index') : url('/office-requests') }}" class="text-decoration-none">Office Requests</a></li>
                    <li class="breadcrumb-item active" aria-current="page">{{ $office_request->pr_no }}</li>
                </ol>
            </nav>
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <h1 class="h3 fw-bold text-dark mb-0">
                    <i class="fa-solid fa-file-lines text-primary me-2"></i>{{ $office_request->pr_no }}
                </h1>
                @if($office_request->status === 'pending_hr_approval')
                    <span class="badge bg-warning text-dark px-3 py-2 fs-6">
                        <i class="fa-solid fa-hourglass-half me-1"></i> Pending HR / Coordinator Review
                    </span>
                @elseif($office_request->status === 'approved')
                    <span class="badge bg-success px-3 py-2 fs-6">
                        <i class="fa-solid fa-circle-check me-1"></i> Approved
                    </span>
                @elseif($office_request->status === 'rejected')
                    <span class="badge bg-danger px-3 py-2 fs-6">
                        <i class="fa-solid fa-circle-xmark me-1"></i> Rejected
                    </span>
                @else
                    <span class="badge bg-{{ \App\Models\PurchaseRequest::statusBadgeClass($office_request->status) }} px-3 py-2 fs-6">
                        {{ $office_request->status_label }}
                    </span>
                @endif

                @if($office_request->priority === 'urgent')
                    <span class="badge bg-danger">Urgent</span>
                @elseif($office_request->priority === 'high')
                    <span class="badge bg-warning text-dark">High Priority</span>
                @endif
            </div>
        </div>

        <div class="d-flex gap-2">
            <a href="{{ \Illuminate\Support\Facades\Route::has('office-requests.index') ? route('office-requests.index') : url('/office-requests') }}" class="btn btn-outline-secondary">
                <i class="fa-solid fa-arrow-left me-1"></i> Back to List
            </a>
            <button onclick="window.print()" class="btn btn-outline-dark">
                <i class="fa-solid fa-print me-1"></i> Print Slip
            </button>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show shadow-sm border-0 d-flex align-items-center mb-4" role="alert">
            <i class="fa-solid fa-circle-check fs-5 me-2"></i>
            <div>{{ session('success') }}</div>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <!-- APPROVAL ACTION PANEL FOR HR / COORDINATOR -->
    @if($canApprove && $office_request->status === 'pending_hr_approval')
        <div class="card border-0 shadow-sm rounded-3 border-start border-warning border-4 mb-4 bg-white">
            <div class="card-body p-4">
                <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
                    <div>
                        <h5 class="fw-bold text-dark mb-1">
                            <i class="fa-solid fa-user-shield text-warning me-2"></i>HR & Coordinator Approval Required
                        </h5>
                        <p class="text-muted small mb-0">
                            As HR Manager or Coordinator, please review the requested office items below and approve or reject this request.
                        </p>
                    </div>
                    <div class="d-flex gap-2 flex-wrap">
                        <!-- Reject Button -->
                        <button type="button" class="btn btn-outline-danger px-3 shadow-sm" data-bs-toggle="modal" data-bs-target="#rejectModal">
                            <i class="fa-solid fa-xmark me-1"></i> Reject (ውድቅ አድርግ)
                        </button>
                        <!-- Approve Button -->
                        <button type="button" class="btn btn-success px-4 shadow-sm" data-bs-toggle="modal" data-bs-target="#approveModal">
                            <i class="fa-solid fa-check me-1"></i> Approve Request (አጽድቅ)
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @elseif(in_array($office_request->status, ['approved', 'pending_store_review']))
        <div class="card border-0 shadow-sm rounded-3 border-start border-success border-4 mb-4 bg-white">
            <div class="card-body p-3 d-flex align-items-center justify-content-between flex-wrap gap-3">
                <div class="d-flex align-items-center gap-3">
                    <div class="rounded-circle bg-success bg-opacity-10 p-3 text-success">
                        <i class="fa-solid fa-check-double fa-lg"></i>
                    </div>
                    <div>
                        <div class="fw-bold text-dark">Approved by HR / Coordinator &mdash; <span class="badge bg-warning text-dark">Awaiting Store / PM Action</span></div>
                        <div class="small text-muted">
                            Approved by <strong>{{ $office_request->hrCoordinatorApprovedBy?->name ?? 'HR/Coordinator' }}</strong>
                            on {{ $office_request->hr_coordinator_approved_at ? $office_request->hr_coordinator_approved_at->format('M d, Y h:i A') : $office_request->updated_at->format('M d, Y') }}.
                            @if($office_request->hr_coordinator_notes)
                                <br><span class="text-dark">Note:</span> <em>"{{ $office_request->hr_coordinator_notes }}"</em>
                            @endif
                        </div>
                    </div>
                </div>
                <div class="d-flex gap-2 flex-wrap">
                    @if(auth()->user()->hasRole('store_manager') || auth()->user()->hasRole('admin') || auth()->user()->hasRole('global_admin') || auth()->user()->hasRole('coordinator'))
                        <!-- Send to PM Button -->
                        <button type="button" class="btn btn-primary px-3 shadow-sm" data-bs-toggle="modal" data-bs-target="#sendToPmModal">
                            <i class="fa-solid fa-paper-plane me-1"></i> Send to PM (ለግዢ ክፍል ላክ)
                        </button>
                        <!-- Store Dispatch Button -->
                        <button type="button" class="btn btn-success px-3 shadow-sm" data-bs-toggle="modal" data-bs-target="#storeDispatchModal">
                            <i class="fa-solid fa-boxes-packing me-1"></i> Dispatch &rarr; Send to Finance
                        </button>
                    @endif
                    <a href="{{ route('purchase-requests.show', $office_request) }}" class="btn btn-sm btn-outline-secondary">
                        <i class="fa-solid fa-sitemap me-1"></i> Full Procurement View
                    </a>
                </div>
            </div>
        </div>
    @elseif($office_request->status === 'rejected')
        <div class="card border-0 shadow-sm rounded-3 border-start border-danger border-4 mb-4 bg-white">
            <div class="card-body p-3 d-flex align-items-center gap-3">
                <div class="rounded-circle bg-danger bg-opacity-10 p-3 text-danger">
                    <i class="fa-solid fa-circle-xmark fa-lg"></i>
                </div>
                <div>
                    <div class="fw-bold text-danger">Request Rejected</div>
                    <div class="small text-muted">
                        Reason: <strong>{{ $office_request->rejection_reason ?: 'Not approved.' }}</strong>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <div class="row g-4">
        <!-- Left Column: Details & Items -->
        <div class="col-lg-8">
            <!-- Requisition Summary Card -->
            <div class="card border-0 shadow-sm rounded-3 mb-4">
                <div class="card-header bg-white py-3 border-bottom">
                    <h6 class="mb-0 fw-bold text-dark"><i class="fa-solid fa-circle-info text-primary me-2"></i>Request Details</h6>
                </div>
                <div class="card-body p-4">
                    <div class="row g-3">
                        <div class="col-sm-6 col-md-4">
                            <div class="text-muted small text-uppercase">Purpose / Category</div>
                            <div class="fw-bold text-dark mt-1">{{ $office_request->office_purpose ?: 'General Office Requisition' }}</div>
                        </div>
                        <div class="col-sm-6 col-md-4">
                            <div class="text-muted small text-uppercase">Requested By</div>
                            <div class="fw-bold text-dark mt-1">{{ $office_request->requestedBy?->name ?? 'Secretary' }}</div>
                            <div class="text-muted small">{{ $office_request->requestedBy?->email }}</div>
                        </div>
                        <div class="col-sm-6 col-md-4">
                            <div class="text-muted small text-uppercase">Date Requested</div>
                            <div class="fw-bold text-dark mt-1">{{ $office_request->created_at ? $office_request->created_at->format('M d, Y h:i A') : '-' }}</div>
                        </div>
                        <div class="col-sm-6 col-md-4">
                            <div class="text-muted small text-uppercase">Required By Date</div>
                            <div class="fw-bold text-dark mt-1">{{ $office_request->required_date ? $office_request->required_date->format('M d, Y') : 'Immediate / As Soon As Possible' }}</div>
                        </div>
                        <div class="col-sm-6 col-md-4">
                            <div class="text-muted small text-uppercase">Target Location / Department</div>
                            <div class="fw-bold text-dark mt-1">
                                <i class="fa-solid fa-building me-1 text-primary"></i> Head Office (ዋና ቢሮ)
                            </div>
                        </div>
                        <div class="col-sm-6 col-md-4">
                            <div class="text-muted small text-uppercase">Store (Optional)</div>
                            <div class="fw-bold text-dark mt-1">{{ $office_request->store?->name ?? 'Central / Office Store' }}</div>
                        </div>
                        @if($office_request->justification)
                        <div class="col-12 border-top pt-3">
                            <div class="text-muted small text-uppercase mb-1">Justification / Detailed Reason</div>
                            <div class="p-3 bg-light rounded-2 text-dark">{{ $office_request->justification }}</div>
                        </div>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Items Table -->
            <div class="card border-0 shadow-sm rounded-3 mb-4">
                <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                    <h6 class="mb-0 fw-bold text-dark">
                        <i class="fa-solid fa-boxes-stacked text-primary me-2"></i>Requested Materials & Items ({{ $office_request->items->count() }})
                    </h6>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light text-secondary text-uppercase small" style="font-size: 0.75rem;">
                                <tr>
                                    <th class="ps-3 py-3">#</th>
                                    <th>Material / Item Description</th>
                                    <th class="text-end">Requested Qty</th>
                                    <th>Unit</th>
                                    <th>Specifications</th>
                                    <th>Store Inventory Check</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($office_request->items as $idx => $item)
                                <tr>
                                    <td class="ps-3 text-muted">{{ $idx + 1 }}</td>
                                    <td>
                                        <div class="fw-bold text-dark">{{ $item->product?->name ?? 'Product #' . $item->product_id }}</div>
                                        @if($item->product?->code)
                                            <div class="text-muted small">Code: {{ $item->product->code }}</div>
                                        @endif
                                    </td>
                                    <td class="text-end fw-bold fs-6 text-primary">
                                        {{ number_format((float)$item->quantity, 2) }}
                                    </td>
                                    <td>
                                        <span class="badge bg-light text-dark border">{{ $item->unit ?? 'pcs' }}</span>
                                    </td>
                                    <td>
                                        @if($item->specifications)
                                            <span class="text-dark small">{{ $item->specifications }}</span>
                                        @else
                                            <span class="text-muted small">Standard</span>
                                        @endif
                                    </td>
                                    <td>
                                        @php
                                            $stocks = $stockAvailability[$item->product_id] ?? collect();
                                            $totalStock = $stocks->sum('quantity_on_hand');
                                        @endphp
                                        @if($totalStock > 0)
                                            <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 px-2 py-1">
                                                <i class="fa-solid fa-check me-1"></i>{{ number_format($totalStock, 1) }} {{ $item->unit }} in stock
                                            </span>
                                            <div class="text-muted" style="font-size: 0.7rem;">
                                                @foreach($stocks as $stk)
                                                    {{ $stk->store?->name }}: {{ (float)$stk->quantity_on_hand }} |
                                                @endforeach
                                            </div>
                                        @else
                                            <span class="badge bg-secondary bg-opacity-10 text-secondary border px-2 py-1">
                                                Out of Stock (Needs Purchase)
                                            </span>
                                        @endif
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right Column: Status & Timeline -->
        <div class="col-lg-4">
            <!-- Workflow Status Card -->
            <div class="card border-0 shadow-sm rounded-3 mb-4">
                <div class="card-header bg-white py-3 border-bottom">
                    <h6 class="mb-0 fw-bold text-dark"><i class="fa-solid fa-timeline text-primary me-2"></i>Workflow History</h6>
                </div>
                <div class="card-body p-3">
                    <ul class="list-unstyled mb-0 position-relative">
                        <!-- Step 1: Submission -->
                        <li class="d-flex gap-3 pb-3 border-start border-2 border-primary ps-3 ms-2 position-relative">
                            <span class="position-absolute top-0 start-0 translate-middle bg-primary rounded-circle p-1" style="width: 12px; height: 12px;"></span>
                            <div>
                                <div class="fw-bold small text-dark">Office Request Submitted</div>
                                <div class="text-muted" style="font-size: 0.75rem;">
                                    By {{ $office_request->requestedBy?->name ?? 'Secretary' }}
                                    &bull; {{ $office_request->created_at ? $office_request->created_at->format('M d, Y h:i A') : '' }}
                                </div>
                            </div>
                        </li>

                        <!-- Step 2: HR / Coordinator Review -->
                        @if($office_request->status === 'pending_hr_approval')
                            <li class="d-flex gap-3 pb-3 border-start border-2 border-warning ps-3 ms-2 position-relative">
                                <span class="position-absolute top-0 start-0 translate-middle bg-warning rounded-circle p-1" style="width: 12px; height: 12px;"></span>
                                <div>
                                    <div class="fw-bold small text-warning">Under Review: HR & Coordinator</div>
                                    <div class="text-muted" style="font-size: 0.75rem;">Awaiting approval decision</div>
                                </div>
                            </li>
                        @elseif($office_request->status === 'approved' || $office_request->hr_coordinator_approved_by)
                            <li class="d-flex gap-3 pb-3 border-start border-2 border-success ps-3 ms-2 position-relative">
                                <span class="position-absolute top-0 start-0 translate-middle bg-success rounded-circle p-1" style="width: 12px; height: 12px;"></span>
                                <div>
                                    <div class="fw-bold small text-success">Approved by HR / Coordinator</div>
                                    <div class="text-muted" style="font-size: 0.75rem;">
                                        By {{ $office_request->hrCoordinatorApprovedBy?->name ?? 'Approver' }}
                                        &bull; {{ $office_request->hr_coordinator_approved_at ? $office_request->hr_coordinator_approved_at->format('M d, Y h:i A') : '' }}
                                    </div>
                                    @if($office_request->hr_coordinator_notes)
                                        <div class="p-2 bg-light rounded mt-1 small text-dark">
                                            {{ $office_request->hr_coordinator_notes }}
                                        </div>
                                    @endif
                                </div>
                            </li>
                        @elseif($office_request->status === 'rejected')
                            <li class="d-flex gap-3 pb-3 border-start border-2 border-danger ps-3 ms-2 position-relative">
                                <span class="position-absolute top-0 start-0 translate-middle bg-danger rounded-circle p-1" style="width: 12px; height: 12px;"></span>
                                <div>
                                    <div class="fw-bold small text-danger">Rejected</div>
                                    <div class="text-muted" style="font-size: 0.75rem;">
                                        {{ $office_request->rejection_reason }}
                                    </div>
                                </div>
                            </li>
                        @endif

                        <!-- Additional Workflow Logs if any -->
                        @foreach($office_request->workflowLogs as $log)
                            <li class="d-flex gap-3 pb-2 border-start border-2 border-secondary ps-3 ms-2 position-relative">
                                <span class="position-absolute top-0 start-0 translate-middle bg-secondary rounded-circle p-1" style="width: 10px; height: 10px;"></span>
                                <div>
                                    <div class="fw-semibold small text-dark">{{ ucfirst(str_replace('_', ' ', $log->action)) }}</div>
                                    <div class="text-muted" style="font-size: 0.72rem;">
                                        {{ $log->actor?->name ?? ucfirst($log->actor_role) }} &bull; {{ $log->created_at->format('M d, Y h:i A') }}
                                    </div>
                                    @if($log->notes)
                                        <div class="small text-muted fst-italic">{{ $log->notes }}</div>
                                    @endif
                                </div>
                            </li>
                        @endforeach
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- APPROVAL MODAL -->
@if($canApprove)
<div class="modal fade" id="approveModal" tabindex="-1" aria-labelledby="approveModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <form action="{{ \Illuminate\Support\Facades\Route::has('office-requests.approve') ? route('office-requests.approve', $office_request) : url('/office-requests/' . $office_request->id . '/approve') }}" method="POST">
            @csrf
            <div class="modal-content border-0 shadow">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title fw-bold" id="approveModalLabel">
                        <i class="fa-solid fa-circle-check me-2"></i>Approve Office Supply Request
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label fw-semibold text-dark">Next Action / Fulfillment Route <span class="text-danger">*</span></label>
                        <select name="next_action" class="form-select" required>
                            <option value="send_to_store" selected>Route to Store Manager (ወደ መጋዘን ይተላለፍ - Store checks stock & dispatches or sends to PM)</option>
                            <option value="send_to_pm">Forward directly to PM (Purchase Manager) for Buying (ለግዢ ኃላፊ ይተላለፍ)</option>
                            <option value="approved_direct">Direct Office Fulfillment (ቀጥታ ተፈቅዷል - Secretary/Office buys)</option>
                        </select>
                        <div class="form-text small">Choose how the approved materials should be fulfilled.</div>
                    </div>

                    <div class="mb-0">
                        <label class="form-label fw-semibold text-dark">Approver Instructions / Notes (አስተያየት)</label>
                        <textarea name="notes" rows="3" class="form-control" placeholder="e.g. Approved. Please purchase standard A4 papers from authorized supplier..."></textarea>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success px-4">
                        <i class="fa-solid fa-check me-1"></i> Confirm Approval
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- REJECTION MODAL -->
<div class="modal fade" id="rejectModal" tabindex="-1" aria-labelledby="rejectModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <form action="{{ \Illuminate\Support\Facades\Route::has('office-requests.reject') ? route('office-requests.reject', $office_request) : url('/office-requests/' . $office_request->id . '/reject') }}" method="POST">
            @csrf
            <div class="modal-content border-0 shadow">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title fw-bold" id="rejectModalLabel">
                        <i class="fa-solid fa-circle-xmark me-2"></i>Reject Office Supply Request
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="mb-0">
                        <label class="form-label fw-semibold text-dark">Reason for Rejection <span class="text-danger">*</span></label>
                        <textarea name="rejection_reason" rows="3" class="form-control" placeholder="Explain why this office supply request is rejected..." required></textarea>
                    </div>
                </div>
<!-- SEND TO PM MODAL -->
<div class="modal fade" id="sendToPmModal" tabindex="-1" aria-labelledby="sendToPmModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <form action="{{ \Illuminate\Support\Facades\Route::has('office-requests.send-to-pm') ? route('office-requests.send-to-pm', $office_request) : url('/office-requests/' . $office_request->id . '/send-to-pm') }}" method="POST">
            @csrf
            <div class="modal-content border-0 shadow">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title fw-bold" id="sendToPmModalLabel">
                        <i class="fa-solid fa-paper-plane me-2"></i>Send to PM (Purchase Manager)
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <p class="text-muted small mb-3">
                        Forward <strong>{{ $office_request->pr_no }}</strong> to the Purchase Manager (PM) for supplier price sourcing & purchasing.
                    </p>
                    <div class="mb-0">
                        <label class="form-label fw-semibold text-dark">Store Manager Remarks / Notes (አስተያየት)</label>
                        <textarea name="notes" rows="3" class="form-control" placeholder="e.g. Items are out of stock in office store. Forwarding to PM to purchase..."></textarea>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary px-4">
                        <i class="fa-solid fa-paper-plane me-1"></i> Confirm & Send to PM
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- STORE DISPATCH MODAL (routes to Finance Head) -->
<div class="modal fade" id="storeDispatchModal" tabindex="-1" aria-labelledby="storeDispatchModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <form action="{{ \Illuminate\Support\Facades\Route::has('office-requests.store-dispatch') ? route('office-requests.store-dispatch', $office_request) : url('/office-requests/' . $office_request->id . '/store-dispatch') }}" method="POST">
            @csrf
            <div class="modal-content border-0 shadow">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title fw-bold" id="storeDispatchModalLabel">
                        <i class="fa-solid fa-boxes-packing me-2"></i>Dispatch from Store &amp; Send to Finance Head
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="alert alert-success border-success mb-3 py-2">
                        <i class="fas fa-arrow-right me-1"></i>
                        After confirming, <strong>{{ $office_request->pr_no }}</strong> will be marked as <strong>issued from store</strong> and automatically forwarded to <strong>Finance Head</strong> for expense recording &amp; payment tracking.
                    </div>
                    <div class="mb-0">
                        <label class="form-label fw-semibold text-dark">Store Manager Dispatch Note (የመዝገብ ማስታወሻ)</label>
                        <textarea name="notes" rows="3" class="form-control" placeholder="e.g. All 3 items issued from Main Store to Office Secretary on Aug 26..."></textarea>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success px-4 fw-bold">
                        <i class="fa-solid fa-check me-1"></i> Confirm Dispatch &amp; Send to Finance
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>
@endif

@endsection
