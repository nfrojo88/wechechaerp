@extends('layouts.app')

@section('title', 'Credit Store Ledger')

@section('content')
<div class="container-fluid py-3">
    <!-- Header -->
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-1 small">
                    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}" class="text-decoration-none">Dashboard</a></li>
                    <li class="breadcrumb-item text-muted">Finance</li>
                    <li class="breadcrumb-item active fw-bold" aria-current="page">Credit Store Ledger</li>
                </ol>
            </nav>
            <h1 class="h4 mb-0 fw-bold text-dark d-flex align-items-center gap-2">
                <i class="fa-solid fa-credit-card text-info"></i>
                Credit Store Ledger & Payables
            </h1>
            <p class="text-muted small mb-0">Track materials purchased on credit, liquidate supplier liabilities, upload receipts, and record expenses.</p>
        </div>
        <div class="d-flex gap-2">
            <button type="button" class="btn btn-success btn-sm shadow-sm" id="headerBatchPayBtn" onclick="openBatchPaymentModal()" disabled>
                <i class="fas fa-receipt me-1"></i> Settle Selected with 1 Receipt (<span id="headerBatchCount">0</span>)
            </button>
            <a href="{{ route('purchase-requests.index') }}" class="btn btn-outline-secondary btn-sm shadow-sm">
                <i class="fas fa-list me-1"></i> All Purchase Requests
            </a>
            <a href="{{ route('expenses.index') }}" class="btn btn-outline-danger btn-sm shadow-sm">
                <i class="fas fa-arrow-trend-down me-1"></i> Company Expenses
            </a>
        </div>
    </div>

    <!-- Alert Banner -->
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show shadow-sm" role="alert">
            <i class="fas fa-check-circle me-1"></i> {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show shadow-sm" role="alert">
            <i class="fas fa-exclamation-triangle me-1"></i> {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif
    @if($errors->any())
        <div class="alert alert-danger alert-dismissible fade show shadow-sm" role="alert">
            <i class="fas fa-exclamation-circle me-1"></i>
            <strong>Please correct the errors below:</strong>
            <ul class="mb-0 mt-1 small">
                @foreach($errors->all() as $err)
                    <li>{{ $err }}</li>
                @endforeach
            </ul>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <!-- Metric KPI Cards -->
    <div class="row g-3 mb-4">
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card border-0 shadow-sm h-100 rounded-3 border-start border-4 border-primary">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center justify-content-between mb-1">
                        <span class="text-muted small text-uppercase fw-bold">Total Credit Procured</span>
                        <div class="rounded-circle bg-primary bg-opacity-10 p-2 text-primary">
                            <i class="fa-solid fa-file-invoice-dollar fs-6"></i>
                        </div>
                    </div>
                    <div class="fs-4 fw-bold text-dark mb-0">{{ number_format($totalCredit, 2) }} <small class="text-muted fs-6">ETB</small></div>
                    <small class="text-muted">Direct material credits (COA 5110)</small>
                </div>
            </div>
        </div>

        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card border-0 shadow-sm h-100 rounded-3 border-start border-4 border-danger">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center justify-content-between mb-1">
                        <span class="text-muted small text-uppercase fw-bold">Outstanding Liability</span>
                        <div class="rounded-circle bg-danger bg-opacity-10 p-2 text-danger">
                            <i class="fa-solid fa-clock-rotate-left fs-6"></i>
                        </div>
                    </div>
                    <div class="fs-4 fw-bold text-danger mb-0">{{ number_format($totalOutstanding, 2) }} <small class="text-muted fs-6">ETB</small></div>
                    <small class="text-muted">{{ $countOutstanding }} open credit purchase(s)</small>
                </div>
            </div>
        </div>

        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card border-0 shadow-sm h-100 rounded-3 border-start border-4 border-success">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center justify-content-between mb-1">
                        <span class="text-muted small text-uppercase fw-bold">Total Liquidated (Paid)</span>
                        <div class="rounded-circle bg-success bg-opacity-10 p-2 text-success">
                            <i class="fa-solid fa-money-check-dollar fs-6"></i>
                        </div>
                    </div>
                    <div class="fs-4 fw-bold text-success mb-0">{{ number_format($totalPaid, 2) }} <small class="text-muted fs-6">ETB</small></div>
                    <small class="text-muted">Recorded into company expenses</small>
                </div>
            </div>
        </div>

        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card border-0 shadow-sm h-100 rounded-3 border-start border-4 border-info">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center justify-content-between mb-1">
                        <span class="text-muted small text-uppercase fw-bold">Fully Liquidated</span>
                        <div class="rounded-circle bg-info bg-opacity-10 p-2 text-info">
                            <i class="fa-solid fa-circle-check fs-6"></i>
                        </div>
                    </div>
                    <div class="fs-4 fw-bold text-info mb-0">{{ $countFullyPaid }} <small class="text-muted fs-6">PRs</small></div>
                    <small class="text-muted">100% paid with receipts</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Filter Form -->
    <div class="card border-0 shadow-sm rounded-3 mb-4">
        <div class="card-body p-3">
            <form action="{{ route('finance.credit-store.index') }}" method="GET" class="row g-2 align-items-end">
                <div class="col-12 col-md-3">
                    <label class="form-label small fw-bold text-muted mb-1">Search</label>
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-light"><i class="fas fa-search text-muted"></i></span>
                        <input type="text" name="search" class="form-control" placeholder="PR #, supplier, project..." value="{{ request('search') }}">
                    </div>
                </div>

                <div class="col-12 col-md-3">
                    <label class="form-label small fw-bold text-muted mb-1">Status</label>
                    <select name="status" class="form-select form-select-sm">
                        <option value="">All Statuses</option>
                        <option value="outstanding" {{ request('status') === 'outstanding' ? 'selected' : '' }}>Outstanding (Unpaid)</option>
                        <option value="partially_paid" {{ request('status') === 'partially_paid' ? 'selected' : '' }}>Partially Paid</option>
                        <option value="fully_paid" {{ request('status') === 'fully_paid' ? 'selected' : '' }}>Fully Paid</option>
                    </select>
                </div>

                <div class="col-12 col-md-3">
                    <label class="form-label small fw-bold text-muted mb-1">Project</label>
                    <select name="project_id" class="form-select form-select-sm">
                        <option value="">All Projects</option>
                        @foreach($projects as $p)
                            <option value="{{ $p->id }}" {{ request('project_id') == $p->id ? 'selected' : '' }}>{{ $p->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="col-12 col-md-3 d-flex gap-2">
                    <button type="submit" class="btn btn-primary btn-sm flex-grow-1">
                        <i class="fas fa-filter me-1"></i> Filter
                    </button>
                    <a href="{{ route('finance.credit-store.index') }}" class="btn btn-outline-secondary btn-sm">
                        <i class="fas fa-redo"></i> Reset
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Batch Selection Floating Action Bar -->
    <div id="batchActionBar" class="card border-0 shadow-lg rounded-3 mb-3 border-start border-4 border-success d-none" style="background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%);">
        <div class="card-body p-3 d-flex flex-wrap align-items-center justify-content-between gap-3">
            <div class="d-flex align-items-center gap-3">
                <div class="rounded-circle bg-success text-white p-2 d-flex align-items-center justify-content-center shadow-sm" style="width: 42px; height: 42px;">
                    <i class="fa-solid fa-receipt fs-5"></i>
                </div>
                <div>
                    <div class="fw-bold text-dark fs-6 d-flex align-items-center gap-2">
                        <span id="batchCountBadge" class="badge bg-success fs-6">0</span>
                        <span>Credit Purchase(s) Selected</span>
                    </div>
                    <div class="small text-muted">
                        Total Remaining Balance: <strong class="text-danger font-monospace fs-6" id="batchTotalRemaining">0.00 ETB</strong>
                        <span class="mx-2">•</span>
                        <span>Upload 1 shared receipt to liquidate all selected credits at once.</span>
                    </div>
                </div>
            </div>
            <div class="d-flex align-items-center gap-2">
                <button type="button" class="btn btn-sm btn-outline-secondary bg-white shadow-sm" onclick="clearAllSelections()">
                    <i class="fas fa-times me-1"></i> Deselect All
                </button>
                <button type="button" class="btn btn-sm btn-success fw-bold shadow px-3 py-2 d-flex align-items-center gap-2" onclick="openBatchPaymentModal()">
                    <i class="fa-solid fa-upload"></i>
                    <span>Settle Selected with 1 Receipt</span>
                </button>
            </div>
        </div>
    </div>

    <!-- Credit Ledgers Table -->
    <div class="card border-0 shadow-sm rounded-3 overflow-hidden">
        <div class="card-header bg-white py-3 px-3 border-bottom d-flex flex-wrap align-items-center justify-content-between gap-2">
            <div class="d-flex align-items-center gap-2">
                <h6 class="mb-0 fw-bold text-dark"><i class="fas fa-book me-2 text-info"></i> Credit Purchases Ledger</h6>
                <span class="badge bg-light text-dark border">{{ $ledgers->total() }} records found</span>
            </div>
            <div class="d-flex align-items-center gap-2">
                <span class="small text-muted d-none d-md-inline"><i class="fas fa-info-circle text-primary me-1"></i> Select multiple credits below to upload one receipt</span>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" style="font-size: 0.875rem;">
                <thead class="bg-light text-muted small text-uppercase">
                    <tr>
                        <th class="ps-3" style="width: 42px;">
                            <input type="checkbox" class="form-check-input" id="masterCheckbox" title="Select / Deselect all on this page" onchange="toggleSelectAll(this)">
                        </th>
                        <th>PR Number</th>
                        <th>Project</th>
                        <th>Supplier / Sourcing</th>
                        <th>Authorized Date</th>
                        <th class="text-end">Credit Amount</th>
                        <th class="text-end">Paid Amount</th>
                        <th class="text-end">Remaining Balance</th>
                        <th class="text-center">Status</th>
                        <th class="text-end pe-3">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($ledgers as $ledger)
                        @php
                            $isPayable = (float)$ledger->remaining_amount > 0.001;
                            $hasReceipt = $ledger->payments->whereNotNull('receipt_path')->first();
                        @endphp
                        <tr class="{{ $isPayable ? 'table-row-selectable' : 'table-light' }}">
                            <td class="ps-3">
                                @if($isPayable)
                                    <input type="checkbox" class="form-check-input credit-checkbox"
                                        value="{{ $ledger->id }}"
                                        data-id="{{ $ledger->id }}"
                                        data-pr="{{ $ledger->pr_no ?? ($ledger->purchaseRequest?->pr_no ?? 'PR-'.$ledger->id) }}"
                                        data-project="{{ $ledger->project?->name ?? '—' }}"
                                        data-supplier="{{ $ledger->supplier_name ?: 'Credit Supplier' }}"
                                        data-credit="{{ (float)$ledger->credit_amount }}"
                                        data-paid="{{ (float)$ledger->paid_amount }}"
                                        data-remaining="{{ (float)$ledger->remaining_amount }}"
                                        onchange="updateSelectionState()">
                                @else
                                    <input type="checkbox" class="form-check-input" disabled title="Credit is 100% paid and fully liquidated">
                                @endif
                            </td>
                            <td class="fw-bold">
                                <a href="{{ route('finance.credit-store.show', $ledger) }}" class="text-decoration-none text-primary">
                                    #{{ $ledger->pr_no ?? ($ledger->purchaseRequest?->pr_no ?? 'PR-'.$ledger->id) }}
                                </a>
                                @if($ledger->purchaseRequest)
                                    <div class="small text-muted font-monospace" style="font-size: 0.75rem;">
                                        Stage: {{ $ledger->purchaseRequest->status_label }}
                                    </div>
                                @endif
                            </td>
                            <td>
                                <div class="fw-semibold text-dark">{{ $ledger->project?->name ?? '—' }}</div>
                            </td>
                            <td>
                                <div><i class="fas fa-building text-muted me-1 small"></i> {{ $ledger->supplier_name ?: 'Credit Supplier' }}</div>
                                <div class="small text-muted font-monospace" style="font-size: 0.75rem;">
                                    COA: {{ $ledger->coaAccount?->code ?? '5110' }} - {{ $ledger->coaAccount?->name ?? 'Cost Of Material By Credit' }}
                                </div>
                            </td>
                            <td class="text-muted">
                                {{ $ledger->authorized_at ? $ledger->authorized_at->format('M d, Y') : ($ledger->created_at ? $ledger->created_at->format('M d, Y') : '—') }}
                                <div class="small text-muted" style="font-size: 0.75rem;">
                                    by {{ $ledger->authorizedByUser?->name ?? 'GM' }}
                                </div>
                            </td>
                            <td class="text-end fw-bold text-dark">
                                {{ number_format($ledger->credit_amount, 2) }} <span class="small text-muted">ETB</span>
                            </td>
                            <td class="text-end text-success fw-bold">
                                {{ number_format($ledger->paid_amount, 2) }} <span class="small text-muted">ETB</span>
                                @if($ledger->payments->count() > 0)
                                    <div class="small text-muted font-monospace" style="font-size: 0.75rem;">
                                        {{ $ledger->payments->count() }} payment(s)
                                    </div>
                                @endif
                            </td>
                            <td class="text-end fw-bold {{ $ledger->remaining_amount > 0 ? 'text-danger' : 'text-muted' }}">
                                {{ number_format($ledger->remaining_amount, 2) }} <span class="small text-muted">ETB</span>
                            </td>
                            <td class="text-center">
                                @if($ledger->status === 'fully_paid')
                                    <span class="badge bg-success-subtle text-success border border-success px-2 py-1 rounded-pill">
                                        <i class="fas fa-check-circle me-1"></i> Fully Paid
                                    </span>
                                @elseif($ledger->status === 'partially_paid')
                                    <span class="badge bg-warning-subtle text-warning border border-warning px-2 py-1 rounded-pill">
                                        <i class="fas fa-clock me-1"></i> Partially Paid
                                    </span>
                                @else
                                    <span class="badge bg-danger-subtle text-danger border border-danger px-2 py-1 rounded-pill">
                                        <i class="fas fa-exclamation-circle me-1"></i> Outstanding
                                    </span>
                                @endif
                            </td>
                            <td class="text-end pe-3">
                                <div class="btn-group btn-group-sm">
                                    @if($hasReceipt)
                                        <a href="{{ asset($hasReceipt->receipt_path) }}" target="_blank" class="btn btn-outline-success btn-sm shadow-sm" title="View Uploaded Receipt Proof">
                                            <i class="fas fa-paperclip"></i> Receipt
                                        </a>
                                    @endif
                                    <a href="{{ route('finance.credit-store.show', $ledger) }}" class="btn btn-outline-primary btn-sm shadow-sm" title="View Details & Record Payment">
                                        <i class="fas fa-credit-card me-1"></i> Manage & Pay
                                    </a>
                                    @if($ledger->purchase_request_id)
                                        <a href="{{ route('purchase-requests.show', $ledger->purchase_request_id) }}" class="btn btn-light btn-sm text-secondary border shadow-sm" title="View Purchase Request">
                                            <i class="fas fa-external-link-alt"></i>
                                        </a>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10" class="text-center py-5 text-muted">
                                <div class="mb-3">
                                    <i class="fa-solid fa-credit-card fa-3x text-secondary opacity-25"></i>
                                </div>
                                <h6 class="fw-bold">No Credit Purchases Recorded</h6>
                                <p class="small mb-0">When the General Manager approves a Purchase Request using <strong>Buy with Credit</strong>, it will automatically appear in this ledger.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($ledgers->hasPages())
            <div class="card-footer bg-white py-3 px-3 border-top">
                {{ $ledgers->links() }}
            </div>
        @endif
    </div>
</div>

<!-- =============================================================== -->
<!-- BATCH SETTLEMENT & MULTI-CREDIT SINGLE RECEIPT MODAL            -->
<!-- =============================================================== -->
<div class="modal fade" id="batchPaymentModal" tabindex="-1" aria-labelledby="batchPaymentModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-0 shadow-lg rounded-3">
            <div class="modal-header bg-success text-white py-3">
                <div class="d-flex align-items-center gap-2">
                    <div class="rounded-circle bg-white text-success p-2 d-flex align-items-center justify-content-center" style="width: 38px; height: 38px;">
                        <i class="fa-solid fa-receipt fs-5"></i>
                    </div>
                    <div>
                        <h5 class="modal-title fw-bold mb-0" id="batchPaymentModalLabel">Batch Credit Settlement (Upload 1 Receipt)</h5>
                        <small class="text-white-50">Settle multiple selected credit purchases using one payment receipt & voucher proof</small>
                    </div>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <form action="{{ route('finance.credit-store.batch-payment') }}" method="POST" enctype="multipart/form-data" id="batchPaymentForm">
                @csrf
                <div class="modal-body p-4">
                    <!-- Instruction alert -->
                    <div class="alert alert-light border shadow-sm rounded-3 mb-4 d-flex align-items-start gap-3">
                        <i class="fa-solid fa-circle-info text-info fs-4 mt-1"></i>
                        <div class="small">
                            <strong>How this works:</strong> The single uploaded receipt (bank slip, cheque copy, or supplier voucher) will be automatically linked to all selected purchase requests. Separate double-entry journal entries and company expense records will be generated for each credit item, fully updating their remaining balances.
                        </div>
                    </div>

                    <!-- Step 1: Selected PRs & Payment Allocation -->
                    <div class="card border shadow-sm rounded-3 mb-4">
                        <div class="card-header bg-light py-2 px-3 d-flex align-items-center justify-content-between">
                            <span class="fw-bold text-dark small text-uppercase">
                                <i class="fas fa-list-check text-primary me-1"></i> 1. Selected Credits & Payment Amounts
                            </span>
                            <span class="badge bg-primary rounded-pill" id="modalSelectedCountBadge">0 Selected</span>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-hover table-sm align-middle mb-0" style="font-size: 0.85rem;">
                                <thead class="bg-light text-muted small text-uppercase">
                                    <tr>
                                        <th class="ps-3">PR #</th>
                                        <th>Project</th>
                                        <th>Supplier</th>
                                        <th class="text-end">Credit Total</th>
                                        <th class="text-end">Remaining</th>
                                        <th class="text-end pe-3" style="width: 220px;">Amount to Pay (ETB)</th>
                                    </tr>
                                </thead>
                                <tbody id="batchModalItemsBody">
                                    <!-- Populated via JavaScript -->
                                </tbody>
                                <tfoot class="bg-light border-top">
                                    <tr>
                                        <th colspan="4" class="text-end ps-3 text-uppercase small text-muted">Total Settlement Amount:</th>
                                        <th class="text-end text-muted font-monospace" id="modalSumRemaining">0.00 ETB</th>
                                        <th class="text-end pe-3">
                                            <span class="fs-5 fw-bold text-success font-monospace" id="modalSumToPay">0.00</span>
                                            <span class="small text-muted">ETB</span>
                                        </th>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>

                    <!-- Step 2: Shared Receipt & Payment Details -->
                    <div class="card border shadow-sm rounded-3">
                        <div class="card-header bg-light py-2 px-3">
                            <span class="fw-bold text-dark small text-uppercase">
                                <i class="fas fa-money-check-dollar text-success me-1"></i> 2. Shared Receipt & Payment Proof
                            </span>
                        </div>
                        <div class="card-body p-3">
                            <div class="row g-3">
                                <!-- Upload Receipt File -->
                                <div class="col-12 col-md-6">
                                    <label class="form-label small fw-bold text-uppercase text-dark">
                                        Upload Payment Receipt / Slip <span class="text-danger">*</span>
                                    </label>
                                    <div class="input-group input-group-sm">
                                        <span class="input-group-text bg-light"><i class="fas fa-file-upload text-success"></i></span>
                                        <input type="file" name="receipt_file" class="form-control form-control-sm" accept=".pdf,.jpg,.jpeg,.png,.webp" required>
                                    </div>
                                    <div class="form-text small text-muted">Attach bank slip, cheque scan, or signed vendor voucher (PDF, JPG, PNG up to 10MB). Applied to all selected items.</div>
                                </div>

                                <!-- Payment Date -->
                                <div class="col-12 col-md-3">
                                    <label class="form-label small fw-bold text-uppercase text-dark">
                                        Payment Date <span class="text-danger">*</span>
                                    </label>
                                    <input type="date" name="payment_date" class="form-control form-control-sm" value="{{ date('Y-m-d') }}" required>
                                </div>

                                <!-- Payment Method -->
                                <div class="col-12 col-md-3">
                                    <label class="form-label small fw-bold text-uppercase text-dark">
                                        Payment Method <span class="text-danger">*</span>
                                    </label>
                                    <select name="payment_method" class="form-select form-select-sm" required>
                                        <option value="bank_transfer" selected>Bank Transfer (Disbursement)</option>
                                        <option value="cheque">Cheque</option>
                                        <option value="cash">Cash Payment</option>
                                        <option value="other">Other Method</option>
                                    </select>
                                </div>

                                <!-- Funding Bank Account -->
                                <div class="col-12 col-md-6">
                                    <label class="form-label small fw-bold text-uppercase text-muted">Funding Bank Account</label>
                                    <select name="bank_account_id" class="form-select form-select-sm">
                                        <option value="">-- Select Bank Account (Optional) --</option>
                                        @foreach($bankAccounts ?? [] as $bk)
                                            <option value="{{ $bk->id }}">{{ $bk->bank_name }} - {{ $bk->account_number }} (Bal: {{ number_format($bk->current_balance ?? 0, 2) }})</option>
                                        @endforeach
                                    </select>
                                </div>

                                <!-- Funding Chart of Account -->
                                <div class="col-12 col-md-6">
                                    <label class="form-label small fw-bold text-uppercase text-muted">Funding Chart of Account (COA)</label>
                                    <select name="coa_account_id" class="form-select form-select-sm">
                                        <option value="">-- Select COA Source (Optional) --</option>
                                        @foreach($coaAccounts ?? [] as $ca)
                                            <option value="{{ $ca->id }}">{{ $ca->code }} - {{ $ca->name }} (Bal: {{ number_format($ca->current_balance ?? 0, 2) }})</option>
                                        @endforeach
                                    </select>
                                </div>

                                <!-- Reference No -->
                                <div class="col-12 col-md-6">
                                    <label class="form-label small fw-bold text-uppercase text-muted">Reference / Cheque / Transaction No</label>
                                    <input type="text" name="reference_no" class="form-control form-control-sm" placeholder="e.g. TXN-892341 / CHQ-00432">
                                </div>

                                <!-- Payment Notes -->
                                <div class="col-12 col-md-6">
                                    <label class="form-label small fw-bold text-uppercase text-muted">Payment Remarks / Notes</label>
                                    <input type="text" name="notes" class="form-control form-control-sm" placeholder="e.g. Bulk supplier settlement via Commercial Bank">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="modal-footer bg-light py-3 px-4 d-flex align-items-center justify-content-between">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">
                        <i class="fas fa-times me-1"></i> Cancel
                    </button>
                    <button type="submit" class="btn btn-success fw-bold shadow-sm px-4 py-2" id="submitBatchPaymentBtn">
                        <i class="fas fa-check-circle me-1"></i> Record Batch Payment & Liquidate (<span id="modalBtnTotal">0.00 ETB</span>)
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    // State of selected credits
    let selectedCredits = new Map();

    function updateSelectionState() {
        const checkboxes = document.querySelectorAll('.credit-checkbox');
        selectedCredits.clear();

        let totalRemaining = 0;

        checkboxes.forEach(cb => {
            if (cb.checked) {
                const id = cb.dataset.id;
                const pr = cb.dataset.pr;
                const project = cb.dataset.project;
                const supplier = cb.dataset.supplier;
                const credit = parseFloat(cb.dataset.credit) || 0;
                const paid = parseFloat(cb.dataset.paid) || 0;
                const remaining = parseFloat(cb.dataset.remaining) || 0;

                selectedCredits.set(id, {
                    id,
                    pr,
                    project,
                    supplier,
                    credit,
                    paid,
                    remaining,
                    amountToPay: remaining
                });

                totalRemaining += remaining;
            }
        });

        const count = selectedCredits.size;
        const batchBar = document.getElementById('batchActionBar');
        const countBadge = document.getElementById('batchCountBadge');
        const totalRemainingEl = document.getElementById('batchTotalRemaining');
        const headerBtn = document.getElementById('headerBatchPayBtn');
        const headerCount = document.getElementById('headerBatchCount');
        const masterCb = document.getElementById('masterCheckbox');

        // Update counts and amounts
        if (countBadge) countBadge.textContent = count;
        if (headerCount) headerCount.textContent = count;
        if (totalRemainingEl) totalRemainingEl.textContent = totalRemaining.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' ETB';

        // Toggle UI visibility
        if (count > 0) {
            batchBar.classList.remove('d-none');
            batchBar.classList.add('d-flex');
            headerBtn.removeAttribute('disabled');
        } else {
            batchBar.classList.add('d-none');
            batchBar.classList.remove('d-flex');
            headerBtn.setAttribute('disabled', 'disabled');
        }

        // Check if all selectable are checked
        const enabledCheckboxes = Array.from(checkboxes).filter(cb => !cb.disabled);
        if (masterCb && enabledCheckboxes.length > 0) {
            masterCb.checked = enabledCheckboxes.every(cb => cb.checked);
            masterCb.indeterminate = count > 0 && count < enabledCheckboxes.length;
        }
    }

    function toggleSelectAll(masterCb) {
        const checkboxes = document.querySelectorAll('.credit-checkbox:not(:disabled)');
        checkboxes.forEach(cb => {
            cb.checked = masterCb.checked;
        });
        updateSelectionState();
    }

    function clearAllSelections() {
        const checkboxes = document.querySelectorAll('.credit-checkbox');
        checkboxes.forEach(cb => cb.checked = false);
        const masterCb = document.getElementById('masterCheckbox');
        if (masterCb) {
            masterCb.checked = false;
            masterCb.indeterminate = false;
        }
        updateSelectionState();
    }

    function openBatchPaymentModal() {
        if (selectedCredits.size === 0) {
            alert('Please select at least one credit purchase with an outstanding balance first.');
            return;
        }

        const tbody = document.getElementById('batchModalItemsBody');
        tbody.innerHTML = '';

        let sumRemaining = 0;
        let sumToPay = 0;

        selectedCredits.forEach(item => {
            sumRemaining += item.remaining;
            sumToPay += item.amountToPay;

            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td class="ps-3 fw-bold text-primary font-monospace">
                    #${item.pr}
                    <input type="hidden" name="selected_ids[]" value="${item.id}">
                </td>
                <td><span class="text-dark fw-semibold">${item.project}</span></td>
                <td><span class="text-muted"><i class="fas fa-building small me-1"></i>${item.supplier}</span></td>
                <td class="text-end font-monospace text-muted">${item.credit.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</td>
                <td class="text-end font-monospace text-danger fw-bold">${item.remaining.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</td>
                <td class="text-end pe-3">
                    <div class="input-group input-group-sm">
                        <input type="number" step="0.01" min="0.01" max="${item.remaining.toFixed(2)}"
                            name="amounts[${item.id}]"
                            value="${item.amountToPay.toFixed(2)}"
                            class="form-control text-end font-monospace fw-bold batch-amount-input"
                            data-id="${item.id}"
                            data-max="${item.remaining}"
                            required
                            oninput="recalcBatchModalTotal()">
                        <span class="input-group-text bg-light text-muted small">ETB</span>
                    </div>
                </td>
            `;
            tbody.appendChild(tr);
        });

        document.getElementById('modalSelectedCountBadge').textContent = `${selectedCredits.size} Credits Selected`;
        document.getElementById('modalSumRemaining').textContent = sumRemaining.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2}) + ' ETB';

        recalcBatchModalTotal();

        const modalEl = document.getElementById('batchPaymentModal');
        const modal = new bootstrap.Modal(modalEl);
        modal.show();
    }

    function recalcBatchModalTotal() {
        let total = 0;
        const inputs = document.querySelectorAll('.batch-amount-input');
        inputs.forEach(input => {
            const val = parseFloat(input.value) || 0;
            total += val;
            const id = input.dataset.id;
            if (selectedCredits.has(id)) {
                selectedCredits.get(id).amountToPay = val;
            }
        });

        const formatted = total.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
        document.getElementById('modalSumToPay').textContent = formatted;
        document.getElementById('modalBtnTotal').textContent = formatted + ' ETB';
    }

    // Form validation before submit
    document.addEventListener('DOMContentLoaded', () => {
        const form = document.getElementById('batchPaymentForm');
        if (form) {
            form.addEventListener('submit', (e) => {
                const inputs = document.querySelectorAll('.batch-amount-input');
                let valid = true;
                let total = 0;

                inputs.forEach(input => {
                    const val = parseFloat(input.value) || 0;
                    const max = parseFloat(input.dataset.max) || 0;
                    if (val <= 0 || val > max + 0.01) {
                        valid = false;
                        input.classList.add('is-invalid');
                    } else {
                        input.classList.remove('is-invalid');
                    }
                    total += val;
                });

                if (!valid || total <= 0) {
                    e.preventDefault();
                    alert('Please enter a valid payment amount for each selected credit item.');
                    return false;
                }

                const fileInput = form.querySelector('input[type="file"][name="receipt_file"]');
                if (fileInput && fileInput.files.length === 0) {
                    e.preventDefault();
                    alert('Please upload a receipt file (proof of payment) to complete the multi-credit settlement.');
                    fileInput.focus();
                    return false;
                }
            });
        }
    });
</script>
@endsection