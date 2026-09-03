@extends('layouts.app')

@section('title', 'Expense Receipt Audit Center')

@section('content')
<div class="container-fluid py-4 px-4">

    <!-- Header Section -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
        <div>
            <div class="d-flex align-items-center gap-2">
                <div class="rounded-circle p-2 d-flex align-items-center justify-content-center shadow-sm" style="width: 46px; height: 46px; background: linear-gradient(135deg, #f59e0b, #d97706); color: white;">
                    <i class="fa-solid fa-receipt fa-lg"></i>
                </div>
                <div>
                    <h3 class="fw-bold text-dark mb-0">Expense Receipt Audit Center</h3>
                    <p class="text-muted small mb-0">Single-pane audit compliance: inspect all company expenses, identify missing receipts, issue inquiries, and attach valid vouchers.</p>
                </div>
            </div>
        </div>
        <div class="d-flex align-items-center gap-2">
            <a href="{{ \Illuminate\Support\Facades\Route::has('dashboard.audit') ? route('dashboard.audit') : url('/dashboard/audit') }}" class="btn btn-outline-secondary rounded-pill px-3 shadow-xs">
                <i class="fa-solid fa-chart-pie me-1"></i> Audit Dashboard
            </a>
            <a href="{{ route('finance.replenishments.index', ['tab' => 'under_audit']) }}" class="btn btn-outline-warning text-dark rounded-pill px-3 shadow-xs">
                <i class="fa-solid fa-hand-holding-dollar me-1"></i> Petty Cash Audits
            </a>
        </div>
    </div>

    <!-- Alerts -->
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show rounded-4 shadow-sm border-0 d-flex align-items-center" role="alert">
            <i class="fa-solid fa-circle-check fs-5 me-2"></i>
            <div>{{ session('success') }}</div>
            <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
        </div>
    @endif

    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show rounded-4 shadow-sm border-0 d-flex align-items-center" role="alert">
            <i class="fa-solid fa-circle-exclamation fs-5 me-2"></i>
            <div>{{ session('error') }}</div>
            <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <!-- Metric Cards -->
    <div class="row g-3 mb-4">
        <!-- 1. Total Expenses Audited -->
        <div class="col-xl-3 col-md-6">
            <div class="card shadow-sm border-0 rounded-4 h-100 bg-white">
                <div class="card-body p-4 d-flex justify-content-between align-items-center">
                    <div>
                        <span class="text-uppercase small fw-bold text-muted">Total Expenses Audited</span>
                        <h3 class="fw-bold text-dark mb-0 mt-1">ETB {{ number_format($totalExpensesAmount, 2) }}</h3>
                        <small class="text-muted"><i class="fa-solid fa-list-check me-1"></i>{{ number_format($totalExpensesCount) }} total transactions</small>
                    </div>
                    <div class="rounded-circle d-flex align-items-center justify-content-center shadow-xs" style="width: 52px; height: 52px; background:#eff6ff; color:#2563eb;">
                        <i class="fa-solid fa-file-invoice-dollar fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- 2. Missing Receipts (Risk Highlight) -->
        <div class="col-xl-3 col-md-6">
            <div class="card shadow-sm border-0 rounded-4 h-100 bg-white border-start border-danger border-4">
                <div class="card-body p-4 d-flex justify-content-between align-items-center">
                    <div>
                        <span class="text-uppercase small fw-bold text-danger">Missing Receipts (Action Required)</span>
                        <h3 class="fw-bold text-danger mb-0 mt-1">ETB {{ number_format($missingReceiptAmount, 2) }}</h3>
                        <small class="text-danger fw-semibold"><i class="fa-solid fa-triangle-exclamation me-1"></i>{{ $missingReceiptCount }} expenses without receipt</small>
                    </div>
                    <div class="rounded-circle d-flex align-items-center justify-content-center shadow-xs" style="width: 52px; height: 52px; background:#fee2e2; color:#dc2626;">
                        <i class="fa-solid fa-receipt fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- 3. Receipts Inquired / Pending Upload -->
        <div class="col-xl-3 col-md-6">
            <div class="card shadow-sm border-0 rounded-4 h-100 bg-white">
                <div class="card-body p-4 d-flex justify-content-between align-items-center">
                    <div>
                        <span class="text-uppercase small fw-bold text-warning">Receipt Inquiries Sent</span>
                        <h3 class="fw-bold text-warning mb-0 mt-1">ETB {{ number_format($requestedReceiptAmount, 2) }}</h3>
                        <small class="text-muted"><i class="fa-solid fa-clock-rotate-left me-1"></i>{{ $requestedReceiptCount }} awaiting staff upload</small>
                    </div>
                    <div class="rounded-circle d-flex align-items-center justify-content-center shadow-xs" style="width: 52px; height: 52px; background:#fef3c7; color:#d97706;">
                        <i class="fa-solid fa-paper-plane fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- 4. Verified Receipts Attached -->
        <div class="col-xl-3 col-md-6">
            <div class="card shadow-sm border-0 rounded-4 h-100 bg-white">
                <div class="card-body p-4 d-flex justify-content-between align-items-center">
                    <div>
                        <span class="text-uppercase small fw-bold text-success">Receipts Attached</span>
                        <h3 class="fw-bold text-success mb-0 mt-1">ETB {{ number_format($attachedReceiptAmount, 2) }}</h3>
                        <small class="text-success"><i class="fa-solid fa-shield-check me-1"></i>{{ $attachedReceiptCount }} verified / voucher present</small>
                    </div>
                    <div class="rounded-circle d-flex align-items-center justify-content-center shadow-xs" style="width: 52px; height: 52px; background:#dcfce7; color:#16a34a;">
                        <i class="fa-solid fa-circle-check fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content Card -->
    <div class="card shadow-sm border-0 rounded-4 overflow-hidden mb-4 bg-white">
        <!-- Navigation Tabs -->
        <div class="card-header bg-white border-bottom pt-3 px-4 pb-0">
            <ul class="nav nav-tabs card-header-tabs gap-2 border-0">
                <li class="nav-item">
                    <a class="nav-link fw-semibold rounded-top-3 px-3 py-2 {{ $tab === 'all' ? 'active bg-light border-bottom-0 text-primary fw-bold' : 'text-secondary' }}" 
                       href="{{ request()->fullUrlWithQuery(['tab' => 'all', 'page' => 1]) }}">
                        <i class="fa-solid fa-list me-1"></i> All Audited Expenses 
                        <span class="badge bg-secondary ms-1 rounded-pill">{{ $totalExpensesCount }}</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link fw-semibold rounded-top-3 px-3 py-2 {{ $tab === 'missing' ? 'active bg-light border-bottom-0 text-danger fw-bold' : 'text-danger' }}" 
                       href="{{ request()->fullUrlWithQuery(['tab' => 'missing', 'page' => 1]) }}">
                        <i class="fa-solid fa-triangle-exclamation me-1 text-danger"></i> Missing Receipts 
                        <span class="badge bg-danger ms-1 rounded-pill">{{ $missingReceiptCount }}</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link fw-semibold rounded-top-3 px-3 py-2 {{ $tab === 'requested' ? 'active bg-light border-bottom-0 text-warning fw-bold' : 'text-secondary' }}" 
                       href="{{ request()->fullUrlWithQuery(['tab' => 'requested', 'page' => 1]) }}">
                        <i class="fa-solid fa-paper-plane me-1 text-warning"></i> Inquiries Sent 
                        <span class="badge bg-warning text-dark ms-1 rounded-pill">{{ $requestedReceiptCount }}</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link fw-semibold rounded-top-3 px-3 py-2 {{ $tab === 'attached' ? 'active bg-light border-bottom-0 text-success fw-bold' : 'text-secondary' }}" 
                       href="{{ request()->fullUrlWithQuery(['tab' => 'attached', 'page' => 1]) }}">
                        <i class="fa-solid fa-circle-check me-1 text-success"></i> Receipts Attached 
                        <span class="badge bg-success ms-1 rounded-pill">{{ $attachedReceiptCount }}</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link fw-semibold rounded-top-3 px-3 py-2 {{ $tab === 'verified_no_receipt' ? 'active bg-light border-bottom-0 text-info fw-bold' : 'text-secondary' }}" 
                       href="{{ request()->fullUrlWithQuery(['tab' => 'verified_no_receipt', 'page' => 1]) }}">
                        <i class="fa-solid fa-check-double me-1 text-info"></i> Verified (No Receipt) 
                        <span class="badge bg-info ms-1 rounded-pill">{{ $verifiedNoReceiptCount ?? 0 }}</span>
                    </a>
                </li>
            </ul>
        </div>

        <!-- Filter & Search Bar -->
        <div class="card-body p-3 bg-light border-bottom">
            <form method="GET" action="{{ route('audit.expense-receipts.index') }}" class="row g-2 align-items-center">
                <input type="hidden" name="tab" value="{{ $tab }}">

                <div class="col-md-3">
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-white border-end-0"><i class="fa-solid fa-magnifying-glass text-muted"></i></span>
                        <input type="text" name="search" class="form-control form-control-sm border-start-0 ps-0" placeholder="Search ref #, requester, details..." value="{{ request('search') }}">
                    </div>
                </div>

                <div class="col-md-2">
                    <select name="type" class="form-select form-select-sm" onchange="this.form.submit()">
                        <option value="all">All Modules / Types</option>
                        <option value="expense_request" {{ request('type') === 'expense_request' ? 'selected' : '' }}>Employee Expense Requests</option>
                        <option value="purchase_request" {{ request('type') === 'purchase_request' ? 'selected' : '' }}>Purchase Request Payments</option>
                        <option value="office_material_request" {{ request('type') === 'office_material_request' ? 'selected' : '' }}>Office Material Requests</option>
                        <option value="expense" {{ request('type') === 'expense' ? 'selected' : '' }}>Direct Expenses</option>
                    </select>
                </div>

                <div class="col-md-2">
                    <input type="date" name="start_date" class="form-control form-control-sm" placeholder="Start Date" value="{{ request('start_date') }}" title="Start Date">
                </div>

                <div class="col-md-2">
                    <input type="date" name="end_date" class="form-control form-control-sm" placeholder="End Date" value="{{ request('end_date') }}" title="End Date">
                </div>

                <div class="col-md-3 d-flex gap-2">
                    <button type="submit" class="btn btn-sm btn-primary px-3 rounded-pill">
                        <i class="fa-solid fa-filter me-1"></i> Filter
                    </button>
                    @if(request('search') || request('type') || request('start_date') || request('end_date'))
                        <a href="{{ route('audit.expense-receipts.index', ['tab' => $tab]) }}" class="btn btn-sm btn-outline-danger px-3 rounded-pill">
                            <i class="fa-solid fa-xmark me-1"></i> Clear
                        </a>
                    @endif
                </div>
            </form>
        </div>

        <!-- Table View -->
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="bg-light text-muted small text-uppercase">
                    <tr>
                        <th class="ps-4 py-3">Ref # / Date</th>
                        <th class="py-3">Requester / Dept</th>
                        <th class="py-3">Category &amp; Description</th>
                        <th class="py-3 text-end">Amount</th>
                        <th class="py-3 text-center">Receipt Status</th>
                        <th class="pe-4 py-3 text-end">Audit Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($items as $item)
                        <tr>
                            <!-- Ref & Date -->
                            <td class="ps-4 py-3" style="white-space: nowrap;">
                                <div class="fw-bold text-dark font-monospace">{{ $item->reference_no }}</div>
                                <div class="text-muted small">
                                    {{ \Carbon\Carbon::parse($item->date)->format('M d, Y') }}
                                </div>
                                <div>
                                    @if($item->source_type === 'expense_request')
                                        <span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill" style="font-size:0.65rem;">Expense Request</span>
                                    @elseif($item->source_type === 'purchase_request')
                                        <span class="badge bg-info-subtle text-info border border-info-subtle rounded-pill" style="font-size:0.65rem;">PR Payment</span>
                                    @elseif($item->source_type === 'office_material_request')
                                        <span class="badge bg-purple-subtle text-dark border rounded-pill" style="font-size:0.65rem; background:#f3e8ff;">Office Material</span>
                                    @else
                                        <span class="badge bg-secondary-subtle text-secondary border rounded-pill" style="font-size:0.65rem;">Direct Expense</span>
                                    @endif
                                </div>
                            </td>

                            <!-- Requester / Dept -->
                            <td class="py-3">
                                <div class="fw-semibold text-dark">{{ $item->requester }}</div>
                                <div class="text-muted small">{{ $item->department }}</div>
                                @if($item->account_name)
                                    <div class="text-muted" style="font-size:0.72rem;"><i class="fa-solid fa-wallet me-1"></i>{{ $item->account_name }}</div>
                                @endif
                            </td>

                            <!-- Category & Description -->
                            <td class="py-3" style="max-width: 320px;">
                                <span class="badge bg-light text-dark border mb-1">{{ $item->category }}</span>
                                <div class="text-dark small text-truncate" title="{{ $item->description }}">{{ $item->description }}</div>
                                @if($item->audit_notes)
                                    <div class="text-warning small mt-1 font-monospace" style="font-size:0.72rem;">
                                        <i class="fa-solid fa-comment-dots me-1"></i>Audit Note: {{ Str::limit($item->audit_notes, 50) }}
                                    </div>
                                @endif
                            </td>

                            <!-- Amount -->
                            <td class="py-3 text-end fw-bold" style="white-space: nowrap;">
                                <div class="fs-6 text-dark">ETB {{ number_format($item->amount, 2) }}</div>
                                <span class="badge bg-light text-muted border" style="font-size:0.7rem;">{{ $item->payment_status }}</span>
                            </td>

                            <!-- Receipt Status -->
                            <td class="py-3 text-center" style="white-space: nowrap;">
                                @if($item->audit_status === 'verified_no_receipt')
                                    <div class="d-inline-flex flex-column align-items-center">
                                        <span class="badge bg-info-subtle text-info border border-info-subtle px-3 py-1 rounded-pill">
                                            <i class="fa-solid fa-check-double me-1"></i> Verified (No Receipt)
                                        </span>
                                        <small class="text-muted mt-1" style="font-size:0.68rem;" title="{{ $item->audit_notes }}"><i class="fa-solid fa-shield-check me-1"></i>Waived / Approved</small>
                                    </div>
                                @elseif($item->has_receipt)
                                    <div class="d-inline-flex flex-column align-items-center">
                                        <span class="badge bg-success-subtle text-success border border-success-subtle px-3 py-1 rounded-pill">
                                            <i class="fa-solid fa-check-circle me-1"></i> Receipt Attached
                                        </span>
                                        @if($item->audit_verified_at)
                                            <small class="text-success fw-bold mt-1" style="font-size:0.68rem;"><i class="fa-solid fa-stamp me-1"></i>Verified</small>
                                        @endif
                                    </div>
                                @elseif($item->audit_status === 'requested')
                                    <span class="badge bg-warning-subtle text-warning border border-warning-subtle px-3 py-1 rounded-pill">
                                        <i class="fa-solid fa-paper-plane me-1"></i> Receipt Inquired
                                    </span>
                                @else
                                    <span class="badge bg-danger-subtle text-danger border border-danger-subtle px-3 py-1 rounded-pill">
                                        <i class="fa-solid fa-triangle-exclamation me-1"></i> Missing Receipt
                                    </span>
                                @endif
                            </td>

                            <!-- Audit Actions -->
                            <td class="pe-4 py-3 text-end" style="white-space: nowrap;">
                                <div class="btn-group btn-group-sm">
                                    <!-- 1. View Attached Receipt -->
                                    @if($item->has_receipt && $item->receipt_url)
                                        <a href="{{ $item->receipt_url }}" target="_blank" class="btn btn-outline-success" title="View attached receipt document">
                                            <i class="fa-solid fa-eye me-1"></i> Receipt
                                        </a>
                                    @endif

                                    <!-- 2. Ask For Receipt Modal Trigger -->
                                    <button type="button" class="btn btn-outline-warning text-dark" data-bs-toggle="modal" data-bs-target="#askReceiptModal_{{ $item->unique_key }}" title="Request missing receipt / send inquiry">
                                        <i class="fa-solid fa-envelope-open-text me-1"></i> Ask Receipt
                                    </button>

                                    <!-- 3. Add / Upload Receipt Modal Trigger -->
                                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#attachReceiptModal_{{ $item->unique_key }}" title="Directly upload and attach receipt voucher">
                                        <i class="fa-solid fa-upload me-1"></i> Add Receipt
                                    </button>

                                    <!-- 4. Verify Without Receipt Modal Trigger -->
                                    @if(!$item->has_receipt && $item->audit_status !== 'verified_no_receipt')
                                        <button type="button" class="btn btn-outline-info text-dark" data-bs-toggle="modal" data-bs-target="#verifyNoReceiptModal_{{ $item->unique_key }}" title="Verify without receipt (e.g. taxi, loading/unloading, parking, petty cash)">
                                            <i class="fa-solid fa-check-double me-1 text-info"></i> No Receipt
                                        </button>
                                    @endif

                                    <!-- 5. Verify Receipt Stamp -->
                                    @if($item->has_receipt && !$item->audit_verified_at)
                                        <form method="POST" action="{{ route('audit.expense-receipts.verify') }}" class="d-inline" onsubmit="return confirm('Verify this receipt as audit-compliant?')">
                                            @csrf
                                            <input type="hidden" name="source_type" value="{{ $item->source_type }}">
                                            <input type="hidden" name="source_id" value="{{ $item->source_id }}">
                                            <button type="submit" class="btn btn-outline-secondary" title="Mark as Audit-Verified">
                                                <i class="fa-solid fa-stamp"></i>
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>

                        <!-- Modal: Ask for Receipt -->
                        <div class="modal fade" id="askReceiptModal_{{ $item->unique_key }}" tabindex="-1" aria-hidden="true">
                            <div class="modal-dialog modal-dialog-centered">
                                <div class="modal-content rounded-4 border-0 shadow-lg">
                                    <form method="POST" action="{{ route('audit.expense-receipts.ask') }}">
                                        @csrf
                                        <input type="hidden" name="source_type" value="{{ $item->source_type }}">
                                        <input type="hidden" name="source_id" value="{{ $item->source_id }}">

                                        <div class="modal-header bg-warning text-dark border-0 py-3 px-4">
                                            <h5 class="modal-title fw-bold">
                                                <i class="fa-solid fa-envelope-open-text me-2"></i>Request Receipt: {{ $item->reference_no }}
                                            </h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                        </div>
                                        <div class="modal-body p-4 bg-white">
                                            <div class="p-3 bg-light rounded-3 mb-3 border">
                                                <div class="d-flex justify-content-between text-muted small mb-1">
                                                    <span>Requester / Department:</span>
                                                    <strong class="text-dark">{{ $item->requester }} ({{ $item->department }})</strong>
                                                </div>
                                                <div class="d-flex justify-content-between text-muted small">
                                                    <span>Disbursed Amount:</span>
                                                    <strong class="text-danger">ETB {{ number_format($item->amount, 2) }}</strong>
                                                </div>
                                            </div>

                                            <div class="mb-3">
                                                <label class="form-label fw-bold text-dark small text-uppercase">Audit Receipt Inquiry / Instructions <span class="text-danger">*</span></label>
                                                <textarea name="inquiry_note" class="form-control" rows="3" required placeholder="State required receipt documentation (e.g. Please attach the official VAT sales invoice or signed voucher for this payment)...">{{ $item->audit_notes ?: "Official VAT receipt / sales invoice required for audit verification of this ETB " . number_format($item->amount, 2) . " expense." }}</textarea>
                                            </div>
                                        </div>
                                        <div class="modal-footer bg-light border-0 py-3 px-4">
                                            <button type="button" class="btn btn-secondary rounded-pill px-3" data-bs-dismiss="modal">Cancel</button>
                                            <button type="submit" class="btn btn-warning rounded-pill px-4 fw-bold shadow-sm">
                                                <i class="fa-solid fa-paper-plane me-1"></i> Send Request
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>

                        <!-- Modal: Add / Upload Receipt -->
                        <div class="modal fade" id="attachReceiptModal_{{ $item->unique_key }}" tabindex="-1" aria-hidden="true">
                            <div class="modal-dialog modal-dialog-centered">
                                <div class="modal-content rounded-4 border-0 shadow-lg">
                                    <form method="POST" action="{{ route('audit.expense-receipts.attach') }}" enctype="multipart/form-data">
                                        @csrf
                                        <input type="hidden" name="source_type" value="{{ $item->source_type }}">
                                        <input type="hidden" name="source_id" value="{{ $item->source_id }}">

                                        <div class="modal-header bg-primary text-white border-0 py-3 px-4">
                                            <h5 class="modal-title fw-bold">
                                                <i class="fa-solid fa-upload me-2"></i>Attach Receipt: {{ $item->reference_no }}
                                            </h5>
                                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                        </div>
                                        <div class="modal-body p-4 bg-white">
                                            <div class="p-3 bg-light rounded-3 mb-3 border">
                                                <div class="d-flex justify-content-between text-muted small mb-1">
                                                    <span>Expense Description:</span>
                                                    <strong class="text-dark">{{ Str::limit($item->description, 35) }}</strong>
                                                </div>
                                                <div class="d-flex justify-content-between text-muted small">
                                                    <span>Amount:</span>
                                                    <strong class="text-success fs-6">ETB {{ number_format($item->amount, 2) }}</strong>
                                                </div>
                                            </div>

                                            <div class="mb-3">
                                                <label class="form-label fw-bold text-dark small text-uppercase">Upload Receipt Document (PDF / Image) <span class="text-danger">*</span></label>
                                                <input type="file" name="receipt_file" class="form-control" accept=".pdf,.png,.jpg,.jpeg,.webp" required>
                                                <small class="text-muted">Accepted formats: PDF, PNG, JPG, JPEG, WEBP (Max: 10MB)</small>
                                            </div>

                                            <div class="mb-3">
                                                <label class="form-label fw-bold text-dark small text-uppercase">Audit Verification Notes (Optional)</label>
                                                <input type="text" name="notes" class="form-control" placeholder="e.g. Verified official cash receipt #REC-8891 attached">
                                            </div>
                                        </div>
                                        <div class="modal-footer bg-light border-0 py-3 px-4">
                                            <button type="button" class="btn btn-secondary rounded-pill px-3" data-bs-dismiss="modal">Cancel</button>
                                            <button type="submit" class="btn btn-primary rounded-pill px-4 fw-bold shadow-sm">
                                                <i class="fa-solid fa-cloud-arrow-up me-1"></i> Upload &amp; Verify Receipt
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>

                        <!-- Modal: Verify Without Receipt -->
                        <div class="modal fade" id="verifyNoReceiptModal_{{ $item->unique_key }}" tabindex="-1" aria-hidden="true">
                            <div class="modal-dialog modal-dialog-centered">
                                <div class="modal-content rounded-4 border-0 shadow-lg">
                                    <form method="POST" action="{{ route('audit.expense-receipts.verify-no-receipt') }}">
                                        @csrf
                                        <input type="hidden" name="source_type" value="{{ $item->source_type }}">
                                        <input type="hidden" name="source_id" value="{{ $item->source_id }}">

                                        <div class="modal-header bg-info text-white border-0 py-3 px-4">
                                            <h5 class="modal-title fw-bold">
                                                <i class="fa-solid fa-check-double me-2"></i>Verify Without Receipt: {{ $item->reference_no }}
                                            </h5>
                                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                        </div>
                                        <div class="modal-body p-4 bg-white">
                                            <div class="p-3 bg-light rounded-3 mb-3 border">
                                                <div class="d-flex justify-content-between text-muted small mb-1">
                                                    <span>Category / Description:</span>
                                                    <strong class="text-dark">{{ $item->category }} &bull; {{ Str::limit($item->description, 35) }}</strong>
                                                </div>
                                                <div class="d-flex justify-content-between text-muted small">
                                                    <span>Expense Amount:</span>
                                                    <strong class="text-success fs-6">ETB {{ number_format($item->amount, 2) }}</strong>
                                                </div>
                                            </div>

                                            <div class="alert alert-info border-0 rounded-3 small py-2 px-3 mb-3">
                                                <i class="fa-solid fa-circle-info me-1"></i> Use this option for operational expenses where a formal receipt cannot be obtained (e.g. taxi/transport, parking, loading/unloading, casual day labor).
                                            </div>

                                            <div class="mb-3">
                                                <label class="form-label fw-bold text-dark small text-uppercase">Verification Reason / Waiver Note</label>
                                                <input type="text" name="justification" class="form-control" placeholder="e.g. Minor transport / parking fee verified without formal receipt" value="{{ str_contains(strtolower($item->category), 'transport') ? 'Transport / travel cash fare verified without physical receipt' : (str_contains(strtolower($item->category), 'loading') ? 'Loading & unloading casual labor verified without receipt' : (str_contains(strtolower($item->category), 'parking') ? 'Parking fee verified without receipt' : 'Operational cash expense verified and approved without receipt')) }}">
                                            </div>
                                        </div>
                                        <div class="modal-footer bg-light border-0 py-3 px-4">
                                            <button type="button" class="btn btn-secondary rounded-pill px-3" data-bs-dismiss="modal">Cancel</button>
                                            <button type="submit" class="btn btn-info text-white rounded-pill px-4 fw-bold shadow-sm">
                                                <i class="fa-solid fa-check-double me-1"></i> Confirm Verification
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>

                    @empty
                        <tr>
                            <td colspan="6" class="text-center py-5 text-muted">
                                <div class="d-flex flex-column align-items-center">
                                    <div class="rounded-circle bg-light p-3 mb-3 text-secondary">
                                        <i class="fa-solid fa-receipt fa-3x opacity-50"></i>
                                    </div>
                                    <h6 class="fw-bold">No audited expenses match the selected filters.</h6>
                                    <p class="small text-muted mb-0">Try clearing filters or switching between tabs above.</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        @if($items->hasPages())
            <div class="card-footer bg-white border-top py-3 px-4 d-flex justify-content-between align-items-center">
                <div class="small text-muted">
                    Showing {{ $items->firstItem() }} to {{ $items->lastItem() }} of {{ $items->total() }} records
                </div>
                <div>
                    {{ $items->links() }}
                </div>
            </div>
        @endif
    </div>
</div>
@endsection
