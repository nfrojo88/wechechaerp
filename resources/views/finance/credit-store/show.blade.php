@extends('layouts.app')

@section('title', 'Credit Purchase Details - #' . ($ledger->pr_no ?? $ledger->id))

@section('content')
@php
    $authUser = Auth::user();
    $rawUserRoles = $authUser ? $authUser->roles->pluck('name')->map(function($r) { return strtolower(trim($r)); })->toArray() : [];
    $isGlobalAdmin = ($authUser && (method_exists($authUser, 'isGlobalAdmin') ? $authUser->isGlobalAdmin() : false))
        || in_array('global_admin', $rawUserRoles)
        || in_array('global admin', $rawUserRoles)
        || in_array('admin', $rawUserRoles);
@endphp
<div class="container-fluid py-3">
    <!-- Breadcrumb & Header -->
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-1 small">
                    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}" class="text-decoration-none">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('finance.credit-store.index') }}" class="text-decoration-none">Credit Store Ledger</a></li>
                    <li class="breadcrumb-item active fw-bold" aria-current="page">PR #{{ $ledger->pr_no ?? $ledger->id }}</li>
                </ol>
            </nav>
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <h1 class="h4 mb-0 fw-bold text-dark">
                    <i class="fa-solid fa-credit-card text-info me-1"></i>
                    Credit Purchase #{{ $ledger->pr_no ?? $ledger->id }}
                </h1>
                @if($ledger->status === 'fully_paid')
                    <span class="badge bg-success-subtle text-success border border-success px-3 py-1 rounded-pill">
                        <i class="fas fa-check-circle me-1"></i> Fully Paid & Liquidated
                    </span>
                @elseif($ledger->status === 'partially_paid')
                    <span class="badge bg-warning-subtle text-warning border border-warning px-3 py-1 rounded-pill">
                        <i class="fas fa-clock me-1"></i> Partially Paid
                    </span>
                @else
                    <span class="badge bg-danger-subtle text-danger border border-danger px-3 py-1 rounded-pill">
                        <i class="fas fa-exclamation-circle me-1"></i> Outstanding Liability
                    </span>
                @endif
            </div>
            <p class="text-muted small mb-0">Project: <strong>{{ optional($ledger->project)->name ?? '—' }}</strong> | Supplier: <strong>{{ $ledger->supplier_name ?: 'Credit Supplier' }}</strong></p>
        </div>

        <div class="d-flex gap-2 flex-wrap">
            @if($ledger->remaining_amount > 0)
                <a href="#disbursePaymentSection" class="btn btn-success btn-sm shadow-sm fw-bold">
                    <i class="fa-solid fa-receipt me-1"></i> Disburse Payment &amp; OCR
                </a>
            @endif
            <a href="{{ route('finance.credit-store.index') }}" class="btn btn-outline-secondary btn-sm shadow-sm">
                <i class="fas fa-arrow-left me-1"></i> Back to Ledger
            </a>
            @if($ledger->purchase_request_id)
                <a href="{{ route('purchase-requests.show', $ledger->purchase_request_id) }}" class="btn btn-outline-primary btn-sm shadow-sm" target="_blank">
                    <i class="fas fa-external-link-alt me-1"></i> View Purchase Request
                </a>
            @endif
        </div>
    </div>

    <!-- Alert Messages -->
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show shadow-sm" role="alert">
            <i class="fas fa-check-circle me-1"></i> {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    @if($errors->any())
        <div class="alert alert-danger alert-dismissible fade show shadow-sm" role="alert">
            <i class="fas fa-exclamation-circle me-1"></i> <strong>Please resolve the following errors:</strong>
            <ul class="mb-0 mt-1 small">
                @foreach($errors->all() as $err)
                    <li>{{ $err }}</li>
                @endforeach
            </ul>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <!-- Balance Status Progress Bar -->
    @php
        $pctPaid = $ledger->credit_amount > 0 ? min(100, round(($ledger->paid_amount / $ledger->credit_amount) * 100, 1)) : 0;
    @endphp
    <div class="card border-0 shadow-sm rounded-3 mb-4">
        <div class="card-body p-3">
            <div class="row g-3 text-center align-items-center mb-3">
                <div class="col-12 col-md-4 border-end-md">
                    <span class="text-muted small text-uppercase fw-bold">Total Credit Amount</span>
                    <div class="fs-4 fw-bold text-dark mt-1">{{ number_format($ledger->credit_amount, 2) }} <small class="fs-6 text-muted">ETB</small></div>
                </div>
                <div class="col-12 col-md-4 border-end-md">
                    <span class="text-muted small text-uppercase fw-bold">Paid / Liquidated</span>
                    <div class="fs-4 fw-bold text-success mt-1">{{ number_format($ledger->paid_amount, 2) }} <small class="fs-6 text-muted">ETB</small></div>
                </div>
                <div class="col-12 col-md-4">
                    <span class="text-muted small text-uppercase fw-bold">Remaining Payable Balance</span>
                    <div class="fs-4 fw-bold {{ $ledger->remaining_amount > 0 ? 'text-danger' : 'text-muted' }} mt-1">
                        {{ number_format($ledger->remaining_amount, 2) }} <small class="fs-6 text-muted">ETB</small>
                    </div>
                </div>
            </div>

            <div class="progress" style="height: 12px; border-radius: 6px;">
                <div class="progress-bar bg-success progress-bar-striped progress-bar-animated" role="progressbar" style="width: {{ $pctPaid }}%;" aria-valuenow="{{ $pctPaid }}" aria-valuemin="0" aria-valuemax="100">
                    {{ $pctPaid }}%
                </div>
            </div>
            <div class="d-flex justify-content-between small text-muted mt-1 font-monospace" style="font-size: 0.75rem;">
                <span>0.00 ETB (0%)</span>
                <span>{{ $pctPaid }}% Paid</span>
                <span>{{ number_format($ledger->credit_amount, 2) }} ETB</span>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <!-- Left Side: Details & History -->
        <div class="col-12 col-lg-8">
            <!-- Material Items Card -->
            <div class="card border-0 shadow-sm rounded-3 mb-4">
                <div class="card-header bg-white py-3 px-3 border-bottom d-flex align-items-center justify-content-between flex-wrap gap-2">
                    <div class="d-flex align-items-center gap-2">
                        <h6 class="mb-0 fw-bold text-dark"><i class="fas fa-boxes-stacked me-2 text-primary"></i> Procured Material Items</h6>
                        @if($ledger->purchaseRequest)
                            <span class="badge bg-primary-subtle text-primary border border-primary font-monospace">PR #{{ $ledger->purchaseRequest->pr_no }}</span>
                        @endif
                    </div>
                    <span class="badge bg-light text-muted border">COA: {{ optional($ledger->coaAccount)->code ?? '5110' }} - {{ optional($ledger->coaAccount)->name ?? 'Cost Of Material By Credit' }}</span>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" style="font-size: 0.875rem;">
                        <thead class="bg-light text-muted small text-uppercase">
                            <tr>
                                <th class="ps-3">#</th>
                                <th>Item / Material</th>
                                <th class="text-center">Quantity</th>
                                <th class="text-end">Unit Price</th>
                                <th class="text-end pe-3">Line Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            @php
                                $calculatedItemsTotal = 0.0;
                            @endphp
                            @if($ledger->purchaseRequest && $ledger->purchaseRequest->items->count() > 0)
                                @foreach($ledger->purchaseRequest->items as $idx => $item)
                                    @php
                                        $uPrice = (float)($item->estimated_unit_cost ?? $item->estimated_unit_price ?? $item->unit_price ?? 0);
                                        $lTotal = (float)($item->estimated_total > 0 ? $item->estimated_total : ((float)$item->quantity * $uPrice));
                                        $calculatedItemsTotal += $lTotal;
                                    @endphp
                                    <tr>
                                        <td class="ps-3 text-muted">{{ $idx + 1 }}</td>
                                        <td>
                                            <div class="fw-bold text-dark">{{ optional($item->product)->name ?? ($item->item_name ?? 'Material Item') }}</div>
                                            <div class="d-flex align-items-center gap-2 flex-wrap">
                                                @if(optional($item->product)->item_code)
                                                    <small class="text-muted font-monospace">{{ optional($item->product)->item_code }}</small>
                                                @endif
                                                @if($item->payment_method)
                                                    <span class="badge {{ $item->payment_method === 'buy_by_credit' ? 'bg-primary-subtle text-primary border border-primary' : 'bg-success-subtle text-success border border-success' }}" style="font-size: 10px;">
                                                        <i class="fas {{ $item->payment_method === 'buy_by_credit' ? 'fa-credit-card' : 'fa-money-bill-wave' }} me-0.5"></i>
                                                        {{ $item->payment_method === 'buy_by_credit' ? 'Buy with Credit' : 'Finance (Pay & Buy)' }}
                                                    </span>
                                                @endif
                                            </div>
                                        </td>
                                        <td class="text-center fw-semibold">{{ number_format($item->quantity, 2) }} {{ $item->unit ?? optional($item->product)->unit }}</td>
                                        <td class="text-end font-monospace">{{ number_format($uPrice, 2) }} ETB</td>
                                        <td class="text-end pe-3 fw-bold font-monospace">{{ number_format($lTotal, 2) }} ETB</td>
                                    </tr>
                                @endforeach
                            @else
                                <tr>
                                    <td colspan="5" class="text-center py-3 text-muted">
                                        Direct Purchase Amount: <strong>{{ number_format($ledger->credit_amount, 2) }} ETB</strong>
                                    </td>
                                </tr>
                            @endif
                        </tbody>
                        <tfoot class="bg-light border-top">
                            @php
                                $gmTotal = (float)($ledger->purchaseRequest?->direct_buy_amount ?? 0);
                            @endphp
                            @if($calculatedItemsTotal > 0 && abs($calculatedItemsTotal - (float)$ledger->credit_amount) > 0.009)
                                <tr>
                                    <th colspan="4" class="text-end ps-3 text-muted small text-uppercase">Items Calculated Sum:</th>
                                    <th class="text-end pe-3 text-muted font-monospace">{{ number_format($calculatedItemsTotal, 2) }} ETB</th>
                                </tr>
                            @endif
                            @if($gmTotal > 0 && abs($gmTotal - (float)$ledger->credit_amount) > 0.009)
                                <tr>
                                    <th colspan="4" class="text-end ps-3 text-primary small text-uppercase">
                                        <i class="fas fa-user-check me-1"></i> GM Approved Request Total:
                                    </th>
                                    <th class="text-end pe-3 fw-bold text-primary font-monospace">{{ number_format($gmTotal, 2) }} ETB</th>
                                </tr>
                            @endif
                            <tr class="table-active">
                                <th colspan="4" class="text-end ps-3 text-uppercase fw-bold text-dark">
                                    <i class="fas fa-credit-card text-info me-1"></i> Total Credit Purchase Value:
                                </th>
                                <th class="text-end pe-3 fw-bold text-dark font-monospace fs-6">{{ number_format($ledger->credit_amount, 2) }} ETB</th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>

            <!-- Active Expense Assignments & Tax Deductions Card -->
            <div class="card border-0 shadow-sm rounded-3 mb-4">
                <div class="card-header bg-white py-3 px-3 border-bottom d-flex align-items-center justify-content-between flex-wrap gap-2">
                    <div class="d-flex align-items-center gap-2">
                        <span class="badge bg-primary text-white rounded-circle p-2 d-flex align-items-center justify-content-center" style="width: 32px; height: 32px;">
                            <i class="fa-solid fa-file-invoice-dollar"></i>
                        </span>
                        <div>
                            <h6 class="mb-0 fw-bold text-dark">Active Expense Assignments &amp; Tax Deductions</h6>
                            <small class="text-muted">Settlement requests assigned to Finance Staff with VAT &amp; 3% Withholding Tax tracking</small>
                        </div>
                    </div>
                    <span class="badge bg-primary-subtle text-primary border border-primary px-2.5 py-1">
                        {{ isset($linkedExpenseRequests) ? $linkedExpenseRequests->count() : 0 }} Request(s)
                    </span>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" style="font-size: 0.875rem;">
                        <thead class="bg-light text-muted small text-uppercase">
                            <tr>
                                <th class="ps-3">Request #</th>
                                <th>Assigned Staff</th>
                                <th class="text-end">Gross Amount</th>
                                <th>VAT / Withholding Tax</th>
                                <th class="text-end">Net Disbursed</th>
                                <th class="text-center">Status</th>
                                <th class="text-center pe-3">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($linkedExpenseRequests ?? [] as $exp)
                                <tr>
                                    <td class="ps-3">
                                        <div class="fw-bold font-monospace text-primary">{{ $exp->request_number }}</div>
                                        <small class="text-muted">{{ $exp->created_at->format('M d, Y') }}</small>
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-center gap-2">
                                            @php
                                                $staffName = optional($exp->assignedFinanceStaff)->name ?? (optional($exp->financeStaff)->name ?? 'Finance Staff');
                                                $staffEmail = optional($exp->assignedFinanceStaff)->email ?? '';
                                            @endphp
                                            <div class="avatar-circle-sm bg-info-subtle text-info fw-bold rounded-circle d-flex align-items-center justify-content-center" style="width: 28px; height: 28px; font-size: 0.75rem;">
                                                {{ substr($staffName, 0, 1) }}
                                            </div>
                                            <div>
                                                <div class="fw-semibold text-dark">{{ $staffName }}</div>
                                                <small class="text-muted" style="font-size: 0.72rem;">{{ $staffEmail }}</small>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="text-end fw-bold font-monospace text-dark">
                                        {{ number_format($exp->amount, 2) }} ETB
                                    </td>
                                    <td>
                                        @if($exp->status === \App\Models\ExpenseRequest::STATUS_PAID)
                                            <div class="d-flex flex-column gap-1">
                                                @if($exp->vat_amount > 0)
                                                    <span class="badge bg-info-subtle text-info border border-info" style="font-size: 0.72rem;">
                                                        <i class="fa-solid fa-receipt me-1"></i> VAT ({{ $exp->vat_type }}): +ETB {{ number_format($exp->vat_amount, 2) }}
                                                    </span>
                                                @else
                                                    <span class="badge bg-light text-muted border" style="font-size: 0.72rem;">No VAT</span>
                                                @endif
                                                @if($exp->has_withholding && $exp->withholding_tax > 0)
                                                    <span class="badge bg-danger-subtle text-danger border border-danger" style="font-size: 0.72rem;">
                                                        <i class="fa-solid fa-shield-halved me-1"></i> WHT ({{ $exp->withholding_rate ?? 3 }}%): -ETB {{ number_format($exp->withholding_tax, 2) }}
                                                    </span>
                                                @endif
                                            </div>
                                        @else
                                            <span class="badge bg-warning-subtle text-warning border border-warning" style="font-size: 0.72rem;">
                                                <i class="fa-solid fa-hourglass-half me-1"></i> VAT &amp; 3% WHT applied at payment
                                            </span>
                                        @endif
                                    </td>
                                    <td class="text-end fw-bold font-monospace {{ $exp->status === \App\Models\ExpenseRequest::STATUS_PAID ? 'text-success' : 'text-muted' }}">
                                        {{ number_format($exp->net_amount ?? $exp->amount, 2) }} ETB
                                    </td>
                                    <td class="text-center">
                                        @if($exp->status === \App\Models\ExpenseRequest::STATUS_PAID)
                                            <span class="badge bg-success-subtle text-success border border-success px-2 py-1">
                                                <i class="fa-solid fa-check-circle me-1"></i> Paid &amp; Liquidated
                                            </span>
                                        @elseif($exp->status === \App\Models\ExpenseRequest::STATUS_ASSIGNED || $exp->status === \App\Models\ExpenseRequest::STATUS_APPROVED_ASSIGNED)
                                            <span class="badge bg-primary-subtle text-primary border border-primary px-2 py-1">
                                                <i class="fa-solid fa-clock me-1"></i> Assigned to Finance
                                            </span>
                                        @else
                                            <span class="badge bg-secondary-subtle text-secondary border px-2 py-1">
                                                {{ $exp->status }}
                                            </span>
                                        @endif
                                    </td>
                                    <td class="text-center pe-3">
                                        <a href="{{ route('expense-requests.index') }}" class="btn btn-sm btn-outline-primary py-1 px-2 shadow-xs" title="Open in Expenses Section">
                                            <i class="fa-solid fa-arrow-up-right-from-square me-1"></i> Open in Expenses
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="text-center py-4 text-muted">
                                        <i class="fa-solid fa-hand-holding-dollar fa-2x text-secondary opacity-25 mb-2"></i>
                                        <p class="mb-0 small">No expense assignments created yet. Use the <strong>"Assign to Finance"</strong> tab on the right to assign payments with VAT &amp; Withholding Tax.</p>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Payment & Receipt History -->
            <div class="card border-0 shadow-sm rounded-3">
                <div class="card-header bg-white py-3 px-3 border-bottom d-flex align-items-center justify-content-between">
                    <h6 class="mb-0 fw-bold text-dark"><i class="fas fa-receipt me-2 text-success"></i> Payment History &amp; Uploaded Receipts</h6>
                    <span class="badge bg-success-subtle text-success border border-success">{{ $ledger->payments->count() }} Payment(s)</span>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" style="font-size: 0.875rem;">
                        <thead class="bg-light text-muted small text-uppercase">
                            <tr>
                                <th class="ps-3">Date</th>
                                <th class="text-end">Amount Paid</th>
                                <th>Method / Ref</th>
                                <th>Funding Account</th>
                                <th class="text-center">Receipt</th>
                                <th>Recorded By</th>
                                @if($isGlobalAdmin)
                                    <th class="text-center pe-3">Admin Action</th>
                                @endif
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($ledger->payments as $pmt)
                                <tr>
                                    <td class="ps-3 fw-semibold">
                                        {{ $pmt->payment_date ? $pmt->payment_date->format('M d, Y') : $pmt->created_at->format('M d, Y') }}
                                    </td>
                                    <td class="text-end fw-bold text-success font-monospace">
                                        {{ number_format($pmt->amount, 2) }} ETB
                                        @if(($pmt->vat_amount ?? 0) > 0)
                                            <span class="badge bg-info-subtle text-info border border-info-subtle d-block mt-1 font-monospace" style="font-size:0.68rem;">
                                                + VAT ({{ $pmt->vat_type }}): {{ number_format($pmt->vat_amount, 2) }}
                                            </span>
                                        @endif
                                        @if(($pmt->withholding_amount ?? 0) > 0)
                                            <span class="badge bg-danger-subtle text-danger border border-danger-subtle d-block mt-1 font-monospace" style="font-size:0.68rem;">
                                                - WHT (3%): {{ number_format($pmt->withholding_amount, 2) }}
                                            </span>
                                        @endif
                                        @if($pmt->net_amount && $pmt->net_amount != $pmt->amount)
                                            <span class="text-muted d-block mt-1" style="font-size:0.7rem;">
                                                Disbursed: <strong class="text-dark">{{ number_format($pmt->net_amount, 2) }} ETB</strong>
                                            </span>
                                        @endif
                                    </td>
                                    <td>
                                        <span class="badge bg-light text-dark border text-uppercase" style="font-size: 0.75rem;">
                                            {{ str_replace('_', ' ', $pmt->payment_method) }}
                                        </span>
                                        @if($pmt->reference_no)
                                            <div class="small text-muted font-monospace mt-1">Ref: {{ $pmt->reference_no }}</div>
                                        @endif
                                    </td>
                                    <td>
                                        @if($pmt->bankAccount)
                                            <div class="small fw-semibold"><i class="fas fa-building-columns text-primary me-1"></i> {{ $pmt->bankAccount->bank_name }}</div>
                                            <div class="small text-muted font-monospace">{{ $pmt->bankAccount->account_number }}</div>
                                        @elseif($pmt->coaAccount)
                                            <div class="small fw-semibold"><i class="fas fa-wallet text-secondary me-1"></i> {{ $pmt->coaAccount->name }}</div>
                                            <div class="small text-muted font-monospace">{{ $pmt->coaAccount->code }}</div>
                                        @else
                                            <span class="text-muted small">Direct Cash / Bank</span>
                                        @endif
                                    </td>
                                    <td class="text-center">
                                        @if($pmt->receipt_path)
                                            <a href="{{ asset($pmt->receipt_path) }}" target="_blank" class="btn btn-sm btn-outline-success py-1 px-2 shadow-sm d-inline-block mb-1" title="View / Download Receipt">
                                                <i class="fas fa-paperclip me-1"></i> Receipt
                                            </a>
                                        @else
                                            <span class="text-muted small font-italic d-block mb-1">No receipt</span>
                                            <button type="button" class="btn btn-xs btn-outline-primary py-0 px-2 shadow-2xs d-inline-block" style="font-size:0.7rem;" data-bs-toggle="modal" data-bs-target="#attachReceiptModal_{{ $pmt->id }}" title="Attach Receipt Proof">
                                                <i class="fa-solid fa-cloud-arrow-up me-1"></i> Add Receipt
                                            </button>
                                        @endif
                                        @if($pmt->withholding_receipt)
                                            <a href="{{ asset($pmt->withholding_receipt) }}" target="_blank" class="btn btn-sm btn-outline-danger py-0 px-2 shadow-xs d-inline-block" style="font-size:0.7rem;" title="View Withholding Slip">
                                                <i class="fa-solid fa-file-invoice-dollar me-1"></i> WHT Slip
                                            </a>
                                        @endif
                                    </td>
                                    <td class="small text-muted">
                                        {{ optional($pmt->recordedByUser)->name ?? 'Finance Head' }}
                                        <div class="text-muted font-monospace" style="font-size: 0.7rem;">{{ $pmt->created_at->format('h:i A') }}</div>
                                    </td>
                                    @if($isGlobalAdmin)
                                        <td class="text-center pe-3">
                                            <button type="button" class="btn btn-sm btn-outline-warning text-dark py-1 px-2.5 fw-semibold shadow-2xs rounded-2 d-inline-flex align-items-center gap-1" data-bs-toggle="modal" data-bs-target="#editPaymentVatModal_{{ $pmt->id }}" title="Correct VAT / Tax Breakdown (Global Admin)">
                                                <i class="fa-solid fa-pen-to-square text-warning"></i>
                                                <span style="font-size: 0.75rem;">Edit VAT</span>
                                            </button>
                                        </td>
                                    @endif
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="{{ $isGlobalAdmin ? 7 : 6 }}" class="text-center py-4 text-muted">
                                        <i class="fas fa-receipt fa-2x text-secondary opacity-25 mb-2"></i>
                                        <p class="mb-0 small">No payments recorded yet. Use the settlement form on the right to record a payment or assign to finance.</p>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Right Side: Delegate to Finance & Guidance -->
        <div class="col-12 col-lg-4">
            @if($ledger->remaining_amount > 0)
                <!-- Quick Link Banner to Full-Width Disburse & OCR Studio Below -->
                <div class="card border-0 shadow-sm rounded-3 mb-3 text-white overflow-hidden" style="background: linear-gradient(135deg, #059669 0%, #10b981 100%);">
                    <div class="card-body p-3 d-flex align-items-center justify-content-between">
                        <div>
                            <span class="text-white-50 small text-uppercase fw-semibold d-block" style="font-size:0.7rem;">Direct Disbursement</span>
                            <h6 class="fw-bold mb-0 text-white">Receipt OCR Studio</h6>
                            <small class="text-white-50" style="font-size:0.72rem;">Full-width Studio active below</small>
                        </div>
                        <a href="#disbursePaymentSection" class="btn btn-light btn-sm fw-bold text-success shadow-xs px-3">
                            Pay Below <i class="fa-solid fa-arrow-down ms-1"></i>
                        </a>
                    </div>
                </div>

                <!-- Delegate to Finance Staff Card (Assign to Finance) -->
                <div class="card border-0 shadow-sm rounded-3 mb-4 border-top border-4 border-primary">
                    <div class="card-header bg-white py-2.5 px-3 border-bottom d-flex align-items-center justify-content-between">
                        <h6 class="mb-0 fw-bold text-dark">
                            <i class="fa-solid fa-user-tag text-primary me-1"></i> Delegate to Finance Staff
                        </h6>
                        <span class="badge bg-primary-subtle text-primary border border-primary-subtle" style="font-size:0.68rem;">Expense Route</span>
                    </div>
                    <div class="card-body p-3">
                        <div class="alert alert-primary bg-primary bg-opacity-10 border-0 p-2.5 rounded-3 mb-3 small">
                            <div class="fw-bold text-primary mb-1">
                                <i class="fa-solid fa-wand-magic-sparkles me-1"></i> Process via Expenses with Tax
                            </div>
                            <div class="text-muted" style="font-size: 0.76rem; line-height: 1.45;">
                                Assign this payment to a Finance staff member. They will see it in their <strong>Expenses section</strong>, apply <strong>15% VAT</strong> and <strong>3% Withholding Tax</strong>, upload the WHT slip, and disburse payment. This credit ledger will auto-liquidate upon payment!
                            </div>
                        </div>

                        <form action="{{ route('finance.credit-store.assign-expense', $ledger) }}" method="POST">
                            @csrf

                            <div class="mb-3">
                                <label class="form-label small fw-bold text-uppercase text-muted">Payment Amount (ETB) <span class="text-danger">*</span></label>
                                <div class="input-group input-group-sm">
                                    <input type="number" step="0.01" min="0.01" max="{{ $ledger->remaining_amount }}" name="amount" class="form-control fw-bold fs-6" value="{{ number_format($ledger->remaining_amount, 2, '.', '') }}" required>
                                    <span class="input-group-text bg-light fw-bold text-primary">ETB</span>
                                </div>
                                <div class="form-text small text-muted">Max payable: <strong>{{ number_format($ledger->remaining_amount, 2) }} ETB</strong></div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label small fw-bold text-uppercase text-dark">
                                    Select Funding / COA Account <span class="text-danger">*</span>
                                </label>
                                <select name="account_source" id="assignAccountSelect" class="form-select form-select-sm" required onchange="onAssignAccountChange(this)">
                                    <option value="">-- Choose Bank or Cash Account * --</option>
                                    @if(isset($bankAccounts) && $bankAccounts->count() > 0)
                                        <optgroup label="🏦 Company Bank Accounts">
                                            @foreach($bankAccounts as $bk)
                                                @php
                                                    $bkStaffId = $bk->assigned_to ?? (optional($bk->coa)->assigned_to ?? '');
                                                    $bkStaffName = optional($bk->assignedStaff)->name ?? (optional(optional($bk->coa)->manager)->name ?? '');
                                                @endphp
                                                <option value="bank:{{ $bk->id }}" 
                                                        data-balance="{{ (float)($bk->current_balance ?? 0) }}"
                                                        data-staff-id="{{ $bkStaffId }}"
                                                        data-staff-name="{{ $bkStaffName }}"
                                                        {{ $loop->first ? 'selected' : '' }}>
                                                    {{ $bk->bank_name }} - {{ $bk->account_number }} (Bal: ETB {{ number_format($bk->current_balance ?? 0, 2) }})
                                                </option>
                                            @endforeach
                                        </optgroup>
                                    @endif
                                    @if(isset($cashAccounts) && $cashAccounts->count() > 0)
                                        <optgroup label="💵 Cash on Hand &amp; Site Petty Cash">
                                            @foreach($cashAccounts as $ca)
                                                <option value="coa:{{ $ca->id }}" 
                                                        data-balance="{{ (float)$ca->current_balance }}"
                                                        data-staff-id="{{ $ca->assigned_to }}"
                                                        data-staff-name="{{ optional($ca->manager)->name ?? '' }}">
                                                    {{ $ca->code }} - {{ $ca->name }} (Bal: ETB {{ number_format($ca->current_balance, 2) }})
                                                </option>
                                            @endforeach
                                        </optgroup>
                                    @endif
                                </select>
                                <div class="small text-muted mt-1" id="assignAccountBalanceDisplay">
                                    <i class="fa-solid fa-coins text-warning me-1"></i>Funds will be disbursed from this account.
                                </div>
                                <div id="assignStaffAutoAlert" class="mt-2" style="display:none;"></div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label small fw-bold text-uppercase text-dark d-flex align-items-center justify-content-between">
                                    <span>Assign to Finance Staff <span class="text-danger">*</span></span>
                                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle" style="font-size:0.68rem;">
                                        <i class="fa-solid fa-wand-magic-sparkles me-1"></i>Auto-assigned from COA
                                    </span>
                                </label>
                                <select name="assigned_finance_staff_id" id="assignedFinanceStaffSelect" class="form-select form-select-sm" required>
                                    <option value="">-- Choose Finance Staff Member * --</option>
                                    @foreach($financeStaff as $staff)
                                        <option value="{{ $staff->id }}" {{ (old('assigned_finance_staff_id') == $staff->id || (Auth::id() == $staff->id && $loop->count == 1)) ? 'selected' : '' }}>
                                            👤 {{ $staff->name }} ({{ $staff->email }})
                                        </option>
                                    @endforeach
                                </select>
                                <div class="form-text small text-muted" style="font-size:0.72rem;">Automatically selected when you choose a COA / Bank account. You may also change it manually if needed.</div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label small fw-bold text-uppercase text-muted">Expense Category</label>
                                <select name="category" class="form-select form-select-sm">
                                    <option value="Material (Credit Settlement)" selected>Material (Credit Settlement)</option>
                                    <option value="Service">Service</option>
                                    <option value="Contract Work">Contract Work</option>
                                    <option value="Other">Other</option>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label class="form-label small fw-bold text-uppercase text-muted">Notes / Instructions</label>
                                <textarea name="notes" class="form-control form-control-sm" rows="2" placeholder="e.g. Please process payment with 15% VAT and deduct 3% Withholding Tax..."></textarea>
                            </div>

                            <button type="submit" class="btn btn-primary btn-sm w-100 fw-bold shadow-sm py-2">
                                <i class="fa-solid fa-paper-plane me-1"></i> Assign to Finance &amp; Send to Expenses
                            </button>
                        </form>
                    </div>
                </div>
            @else
                <div class="card border-0 shadow-sm rounded-3 mb-4 border-top border-4 border-success">
                    <div class="card-header bg-white py-2 px-3 border-bottom">
                        <h6 class="mb-0 fw-bold text-success py-1">
                            <i class="fas fa-check-circle me-1"></i> Credit Fully Settled
                        </h6>
                    </div>
                    <div class="card-body p-3">
                        <div class="text-center py-4">
                            <div class="text-success mb-2">
                                <i class="fas fa-circle-check fa-3x"></i>
                            </div>
                            <h6 class="fw-bold text-dark">This Credit Purchase is 100% Paid</h6>
                            <p class="text-muted small mb-0">Total of <strong>{{ number_format($ledger->paid_amount, 2) }} ETB</strong> has been fully paid, recorded in double-entry journals, and posted into Company Expenses.</p>
                        </div>

                        @if($isGlobalAdmin && $ledger->payments->count() > 0)
                            <div class="card border border-warning-subtle bg-warning bg-opacity-10 rounded-3 mt-3 shadow-none">
                                <div class="card-body p-3">
                                    <div class="d-flex align-items-center justify-content-between mb-2">
                                        <span class="badge bg-warning text-dark fw-bold px-2 py-1">
                                            <i class="fa-solid fa-user-shield me-1"></i> Global Admin Only
                                        </span>
                                        <span class="text-muted small font-monospace" style="font-size:0.72rem;">VAT Correction</span>
                                    </div>
                                    <h6 class="fw-bold text-dark mb-1 small">
                                        <i class="fa-solid fa-sliders text-warning me-1"></i> Post-Settlement Tax Audit &amp; VAT Correction
                                    </h6>
                                    <p class="text-muted small mb-3" style="font-size:0.76rem; line-height: 1.45;">
                                        If tax recording errors occurred during settlement (e.g. wrong VAT rate, omitted 15% VAT, or missing 3% withholding), correct the payment tax breakdown below. It will automatically update the payment, linked fiscal receipt, and ERCA declaration route (Line 100 vs Purchase TAS Excel).
                                    </p>
                                    <div class="d-grid gap-2">
                                        @foreach($ledger->payments as $adminPmt)
                                            <button type="button" class="btn btn-sm btn-white bg-white border border-secondary border-opacity-25 text-start d-flex justify-content-between align-items-center py-2 px-2.5 shadow-2xs rounded-2 hover-shadow" data-bs-toggle="modal" data-bs-target="#editPaymentVatModal_{{ $adminPmt->id }}">
                                                <div>
                                                    <span class="fw-bold text-dark" style="font-size: 0.8rem;">Payment #{{ $adminPmt->id }}</span>
                                                    <span class="text-muted font-monospace ms-1" style="font-size: 0.75rem;">({{ number_format($adminPmt->amount, 2) }} ETB)</span>
                                                    <div class="small text-muted" style="font-size: 0.7rem;">
                                                        VAT: <strong>{{ strtoupper($adminPmt->vat_type ?? 'none') }}</strong> ({{ number_format($adminPmt->vat_amount ?? 0, 2) }} ETB) &bull; WHT: {{ number_format($adminPmt->withholding_amount ?? 0, 2) }} ETB
                                                    </div>
                                                </div>
                                                <span class="badge bg-warning text-dark fw-bold" style="font-size:0.7rem;">
                                                    <i class="fa-solid fa-pen-to-square me-1"></i> Edit VAT
                                                </span>
                                            </button>
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            @endif

            <!-- Accounting Guidance Box -->
            <div class="card border-0 shadow-sm rounded-3 bg-light">
                <div class="card-body p-3">
                    <h6 class="fw-bold text-dark mb-2 small text-uppercase"><i class="fas fa-circle-info text-info me-1"></i> Automated Accounting Impact</h6>
                    <ul class="small text-muted ps-3 mb-0" style="line-height: 1.6;">
                        <li><strong>Credit Procurement:</strong> Booked to <span class="badge bg-white text-dark border">COA 5110 - Cost Of Material By Credit</span>.</li>
                        <li><strong>Store Intake:</strong> Goods are received into store inventory immediately without cash delay.</li>
                        <li><strong>Payment Settlement:</strong> When you record a payment here, it automatically creates a journal entry:
                            <div class="mt-1 font-monospace bg-white p-2 rounded border" style="font-size: 0.75rem;">
                                <div><strong>Debit:</strong> COA 5110 (Cost Of Material)</div>
                                <div><strong>Credit:</strong> Bank / Cash Funding Account</div>
                            </div>
                        </li>
                        <li class="mt-1"><strong>Expenses:</strong> Automatically logged in <strong>Company Expenses</strong> for accurate financial reporting.</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    @if($ledger->remaining_amount > 0)
        <!-- Full-Width Disburse Payment & Receipt OCR Studio Section -->
        <div id="disbursePaymentSection" class="card border-0 shadow-sm rounded-4 mt-4 overflow-hidden border-top border-4 border-success">
            <!-- Studio Card Header -->
            <div class="card-header bg-white py-3 px-4 border-bottom d-flex align-items-center justify-content-between flex-wrap gap-3">
                <div class="d-flex align-items-center gap-3">
                    <span class="badge bg-success text-white p-2.5 rounded-3 shadow-xs">
                        <i class="fa-solid fa-cash-register fs-5"></i>
                    </span>
                    <div>
                        <div class="d-flex align-items-center gap-2 flex-wrap">
                            <h5 class="fw-bold text-dark mb-0">Disburse Payment &amp; Receipt OCR Studio</h5>
                            <span class="badge bg-success-subtle text-success border border-success fw-semibold px-2 py-1" style="font-size:0.75rem;">
                                <i class="fa-solid fa-wand-magic-sparkles me-1"></i> AI Vision + NIM QC
                            </span>
                            <span class="badge bg-primary-subtle text-primary border border-primary fw-semibold px-2 py-1" style="font-size:0.75rem;">
                                <i class="fa-solid fa-scale-balanced me-1"></i> ERCA Line 100 &amp; TAS
                            </span>
                        </div>
                        <small class="text-muted">Direct credit ledger settlement with instant double-entry journal posting, Ethiopian tax computation, and live fiscal receipt scanner.</small>
                    </div>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <div class="text-end d-none d-md-block">
                        <span class="text-muted small text-uppercase fw-semibold d-block" style="font-size:0.7rem;">Remaining Balance</span>
                        <span class="fw-bold text-danger font-monospace fs-6">ETB {{ number_format($ledger->remaining_amount, 2) }}</span>
                    </div>
                    <button type="button" class="btn btn-outline-success btn-sm fw-bold px-3 py-1.5 shadow-2xs" onclick="setQuickPayFullAmount()">
                        <i class="fa-solid fa-bolt me-1 text-warning"></i> Pay Full Balance
                    </button>
                </div>
            </div>

            <!-- Studio Card Body -->
            <div class="card-body p-4 bg-light bg-opacity-25">
                <form action="{{ route('finance.credit-store.record-payment', $ledger) }}" method="POST" enctype="multipart/form-data">
                    @csrf

                    <!-- Hidden Fields for Tax & Fiscal Declaration Sync -->
                    <input type="hidden" name="vat_amount" id="quickPayVatAmount" value="0.00">
                    <input type="hidden" name="withholding_amount" id="quickPayWithholdingAmount" value="0.00">
                    <input type="hidden" name="net_amount" id="quickPayNetAmount" value="{{ number_format($ledger->remaining_amount, 2, '.', '') }}">
                    
                    <input type="hidden" name="subtotal" id="creditSingleSubtotal" value="{{ number_format($ledger->remaining_amount, 2, '.', '') }}">
                    <input type="hidden" name="total_amount" id="creditSingleTotalAmount" value="{{ number_format($ledger->remaining_amount, 2, '.', '') }}">
                    <input type="hidden" name="vat_included" id="creditSingleVatIncluded" value="0">
                    <input type="hidden" name="vat_amount" id="creditSingleVatAmount" value="0.00">
                    <input type="hidden" name="purc_type" id="creditSinglePurcType" value="6">
                    <input type="hidden" name="purchaser_tin" id="creditSinglePurchaserTin" value="0038480010">
                    <input type="hidden" name="seller_tin" id="creditSingleSellerTin" value="{{ optional($ledger->supplier)->tin ?? '' }}">
                    <input type="hidden" name="supplier_tin" id="creditSingleSupplierTin" value="{{ optional($ledger->supplier)->tin ?? '' }}">
                    <input type="hidden" name="receipt_no" id="creditSingleReceiptNo" value="">
                    <input type="hidden" name="fs_no" id="creditSingleFsNo" value="">
                    <input type="hidden" name="encoding_date" id="creditSingleEncodingDate" value="{{ date('Y-m-d') }}">
                    <input type="hidden" name="mrc" id="creditSingleMrc" value="">
                    <input type="hidden" name="encoded_by" id="creditSingleEncodedBy" value="{{ strtolower(preg_replace('/[^a-zA-Z0-9_]/', '', Auth::user()->name ?? 'wechacha')) }}">

                    <div class="row g-4">
                        <!-- Left Sub-Column: Payment & Tax Parameters -->
                        <div class="col-12 col-xl-5">
                            <div class="card border-0 shadow-sm rounded-3 bg-white h-100">
                                <div class="card-header bg-white py-2.5 px-3 border-bottom d-flex align-items-center justify-content-between">
                                    <h6 class="fw-bold text-dark mb-0 small text-uppercase">
                                        <i class="fa-solid fa-coins text-warning me-1.5"></i> 1. Payment &amp; Tax Parameters
                                    </h6>
                                    <span class="badge bg-light text-muted border font-monospace" style="font-size:0.7rem;">Step 1 of 2</span>
                                </div>
                                <div class="card-body p-3">
                                    <!-- Payment Date & Method -->
                                    <div class="row g-2 mb-3">
                                        <div class="col-sm-6">
                                            <label class="form-label small fw-bold text-uppercase text-muted">Payment Date <span class="text-danger">*</span></label>
                                            <input type="date" name="payment_date" class="form-control form-control-sm" value="{{ date('Y-m-d') }}" required>
                                        </div>
                                        <div class="col-sm-6">
                                            <label class="form-label small fw-bold text-uppercase text-muted">Payment Method <span class="text-danger">*</span></label>
                                            <select name="payment_method" id="singlePaymentMethodSelect" class="form-select form-select-sm" required>
                                                <option value="bank_transfer" selected>Bank Transfer (CBE, Awash, etc.)</option>
                                                <option value="cash">Cash / Site Petty Cash</option>
                                                <option value="cheque">Cheque</option>
                                                <option value="telebirr">Telebirr / Mobile Money</option>
                                                <option value="other">Other</option>
                                            </select>
                                        </div>
                                    </div>

                                    <!-- Payment Amount (ETB) -->
                                    <div class="mb-3">
                                        <div class="d-flex align-items-center justify-content-between mb-1">
                                            <label class="form-label small fw-bold text-uppercase text-dark mb-0">Disbursed Amount (ETB) <span class="text-danger">*</span></label>
                                            <button type="button" class="btn btn-link btn-sm p-0 text-success text-decoration-none fw-bold" style="font-size: 0.75rem;" onclick="setQuickPayFullAmount()">
                                                <i class="fa-solid fa-bolt me-0.5 text-warning"></i> Pay Max ({{ number_format($ledger->remaining_amount, 2) }} ETB)
                                            </button>
                                        </div>
                                        <div class="input-group">
                                            <span class="input-group-text bg-light fw-bold text-dark">ETB</span>
                                            <input type="number" step="0.01" min="0.01" max="{{ $ledger->remaining_amount }}" name="amount" id="quickPayAmountInput" class="form-control fw-bold fs-5 text-dark" value="{{ number_format($ledger->remaining_amount, 2, '.', '') }}" required oninput="recalculateQuickPayTax()">
                                        </div>
                                        <div class="d-flex justify-content-between small text-muted mt-1" style="font-size:0.73rem;">
                                            <span>Payable Ceiling: <strong class="text-dark">{{ number_format($ledger->remaining_amount, 2) }} ETB</strong></span>
                                            <span>Already Paid: <strong class="text-success">{{ number_format($ledger->paid_amount, 2) }} ETB</strong></span>
                                        </div>
                                    </div>

                                    <!-- Funding Account (COA / Bank) -->
                                    <div class="mb-3">
                                        <label class="form-label small fw-bold text-uppercase text-dark">
                                            Paying Bank / Cash Account <span class="text-danger">*</span>
                                        </label>
                                        <select name="account_source" id="singlePaymentAccountSelect" class="form-select form-select-sm" required onchange="onSingleAccountChange(this)">
                                            <option value="">-- Choose Paying Account * --</option>
                                            @if(isset($bankAccounts) && $bankAccounts->count() > 0)
                                                <optgroup label="🏦 Company Bank Accounts">
                                                    @foreach($bankAccounts as $bk)
                                                        <option value="bank:{{ $bk->id }}" data-method="bank_transfer" data-balance="{{ (float)($bk->current_balance ?? 0) }}" {{ $loop->first ? 'selected' : '' }}>
                                                            {{ $bk->bank_name }} - {{ $bk->account_number }} (Bal: ETB {{ number_format($bk->current_balance ?? 0, 2) }})
                                                        </option>
                                                    @endforeach
                                                </optgroup>
                                            @endif
                                            @if(isset($cashAccounts) && $cashAccounts->count() > 0)
                                                <optgroup label="💵 Cash on Hand &amp; Site Petty Cash">
                                                    @foreach($cashAccounts as $ca)
                                                        <option value="coa:{{ $ca->id }}" data-method="cash" data-balance="{{ (float)$ca->current_balance }}">
                                                            {{ $ca->code }} - {{ $ca->name }} (Bal: ETB {{ number_format($ca->current_balance, 2) }})
                                                        </option>
                                                    @endforeach
                                                </optgroup>
                                            @endif
                                        </select>
                                        <div class="small text-muted mt-1" id="singleAccountBalanceDisplay">
                                            <i class="fa-solid fa-coins text-warning me-1"></i>Funds will be credited directly from this account.
                                        </div>
                                    </div>

                                    <!-- Tax Treatment Section (Ethiopian ERCA / VAT / WHT) -->
                                    <div class="card border border-secondary border-opacity-25 rounded-3 mb-3 bg-light bg-opacity-50">
                                        <div class="card-body p-2.5">
                                            <div class="d-flex align-items-center justify-content-between mb-2">
                                                <span class="fw-bold small text-dark"><i class="fa-solid fa-calculator text-primary me-1"></i> Tax &amp; Withholding Computation</span>
                                                <span class="badge bg-secondary-subtle text-secondary" style="font-size:0.68rem;">ERCA Rules</span>
                                            </div>

                                            <div class="row g-2 mb-2">
                                                <div class="col-sm-7">
                                                    <label class="form-label small text-muted mb-1" style="font-size:0.72rem;">VAT Option</label>
                                                    <select name="vat_type" id="quickPayVatType" class="form-select form-select-sm" onchange="recalculateQuickPayTax()">
                                                        <option value="none" selected>No VAT / Exempt (PurcType 6 / TAS Excel)</option>
                                                        <option value="inclusive">15% VAT Inclusive (PurcType 3 / Line 100)</option>
                                                        <option value="exclusive">15% VAT Exclusive (Add 15% to Base)</option>
                                                        <option value="vat_b">15% VAT-B (Standard Ethiopian Rate)</option>
                                                    </select>
                                                </div>
                                                <div class="col-sm-5 d-flex align-items-end">
                                                    <div class="form-check form-switch mb-1">
                                                        <input class="form-check-input" type="checkbox" id="quickPayWithholdingToggle" checked onchange="recalculateQuickPayTax()">
                                                        <label class="form-check-label small fw-bold text-dark" for="quickPayWithholdingToggle" style="font-size:0.72rem;">
                                                            Deduct 3% WHT
                                                        </label>
                                                    </div>
                                                </div>
                                            </div>

                                            <div id="quickPayWithholdingSection" class="p-2 rounded bg-warning bg-opacity-10 border border-warning border-opacity-25 small text-dark mb-2" style="font-size: 0.72rem;">
                                                <div class="d-flex align-items-center justify-content-between">
                                                    <span><i class="fa-solid fa-stamp text-warning me-1"></i> <strong>3% Withholding Tax</strong> deducted from vendor net payment.</span>
                                                    <span class="badge bg-warning text-dark font-monospace">Rate: 3.00%</span>
                                                </div>
                                            </div>

                                            <!-- Live Calculation Summary Breakdown -->
                                            <div class="p-2.5 rounded bg-white border font-monospace small" style="font-size: 0.74rem;">
                                                <div class="d-flex justify-content-between text-muted mb-1">
                                                    <span>Base / Subtotal:</span>
                                                    <span id="quickDisplayBaseAmount" class="fw-bold text-dark">ETB 0.00</span>
                                                </div>
                                                <div class="d-flex justify-content-between text-muted mb-1">
                                                    <span>VAT (15%):</span>
                                                    <span id="quickDisplayVatAmount" class="fw-bold text-info">+ ETB 0.00</span>
                                                </div>
                                                <div class="d-flex justify-content-between text-muted mb-1">
                                                    <span>Withholding Tax (3%):</span>
                                                    <span id="quickDisplayWhtAmount" class="fw-bold text-danger">- ETB 0.00</span>
                                                </div>
                                                <div class="d-flex justify-content-between pt-1 border-top fw-bold fs-6">
                                                    <span class="text-dark">Net Cash Disbursed:</span>
                                                    <span id="quickDisplayNetAmount" class="text-success">ETB 0.00</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Reference & Notes -->
                                    <div class="row g-2 mb-3">
                                        <div class="col-sm-6">
                                            <label class="form-label small fw-bold text-uppercase text-muted">Reference / Check / Tx #</label>
                                            <input type="text" name="reference_no" class="form-control form-control-sm font-monospace" placeholder="e.g. CBE-TX-998231 or FS-0012">
                                        </div>
                                        <div class="col-sm-6">
                                            <label class="form-label small fw-bold text-uppercase text-muted">Notes / Memo</label>
                                            <input type="text" name="notes" class="form-control form-control-sm" placeholder="e.g. Settlement for delivery batch 1">
                                        </div>
                                    </div>

                                    <!-- No Receipt Toggle Option -->
                                    <div class="form-check form-switch mb-3 p-2 rounded bg-light border">
                                        <input class="form-check-input ms-0 me-2" type="checkbox" id="singleNoReceiptToggle" onchange="toggleSingleReceiptMode(this.checked)">
                                        <label class="form-check-label small fw-bold text-dark" for="singleNoReceiptToggle">
                                            Pay Without Vendor Fiscal Receipt (TAS / Non-VAT Proof Only)
                                        </label>
                                    </div>

                                    <!-- Submit Button -->
                                    <button type="submit" id="singleSubmitBtn" class="btn btn-success fw-bold shadow-sm w-100 py-2.5">
                                        <i class="fas fa-check-circle me-1"></i> Record Payment &amp; Liquidate Credit
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Right Sub-Column: Receipt OCR Studio & Proof -->
                        <div class="col-12 col-xl-7">
                            <div class="card border-0 shadow-sm rounded-3 bg-white h-100">
                                <div class="card-header bg-white py-2.5 px-3 border-bottom d-flex align-items-center justify-content-between flex-wrap gap-2">
                                    <h6 class="fw-bold text-dark mb-0 small text-uppercase">
                                        <i class="fa-solid fa-wand-magic-sparkles text-success me-1.5"></i> 2. Receipt OCR Studio &amp; Fiscal Proof
                                    </h6>
                                    <span class="badge bg-success-subtle text-success border border-success font-monospace" style="font-size:0.7rem;">
                                        Interactive AI Studio
                                    </span>
                                </div>
                                <div class="card-body p-3">
                                    <!-- Normal Mode: With Receipt Dropzone & Live Studio -->
                                    <div id="singleWithReceiptDiv">
                                        <div class="alert alert-light border small text-muted mb-3 d-flex align-items-center gap-2">
                                            <i class="fa-solid fa-lightbulb text-warning fs-5"></i>
                                            <div>
                                                <strong>Multi-Engine OCR Active:</strong> Upload the supplier receipt or bank transfer slip. AI automatically extracts <strong>FS No, MRC, Supplier TIN, Receipt Date &amp; Total Amount</strong>, verifies ERCA compliance, and updates payment fields in real-time.
                                            </div>
                                        </div>

                                        <!-- Upload / Dropzone -->
                                        <div class="border border-2 border-dashed rounded-3 p-4 text-center bg-light cursor-pointer mb-3 position-relative"
                                             style="border-color:#10b981 !important; transition: all 0.2s ease-in-out;"
                                             onclick="document.getElementById('creditSingleReceiptInput').click()">
                                            <input type="file" name="receipt_file" id="creditSingleReceiptInput" class="d-none" accept=".pdf,.jpg,.jpeg,.png,.webp">
                                            <div class="d-inline-flex align-items-center justify-content-center p-3 rounded-circle bg-success bg-opacity-10 text-success mb-2">
                                                <i class="fa-solid fa-cloud-arrow-up fa-2x"></i>
                                            </div>
                                            <h6 class="fw-bold text-dark mb-1">Click to Upload or Drag &amp; Drop Receipt Proof</h6>
                                            <p class="text-muted small mb-2" style="font-size:0.76rem;">Supports high-res JPG, PNG, WEBP, or multi-page PDF up to 10MB</p>
                                            <button type="button" class="btn btn-sm btn-white border shadow-2xs fw-semibold px-3 py-1">
                                                <i class="fa-solid fa-folder-open me-1 text-primary"></i> Browse Receipt File
                                            </button>
                                        </div>

                                        <!-- Live OCR Studio Feedback Area (Injected by ReceiptOcrScanner) -->
                                        <div id="creditSingleOcrFeedbackArea" class="ocr-scanner-status-area"></div>
                                    </div>

                                    <!-- Fallback Mode: Pay Without Receipt Banner -->
                                    <div id="singleWithoutReceiptDiv" class="d-none">
                                        <div class="alert alert-warning border border-warning border-opacity-50 rounded-3 p-3">
                                            <div class="d-flex align-items-start gap-2">
                                                <i class="fa-solid fa-triangle-exclamation text-warning fs-4 mt-0.5"></i>
                                                <div>
                                                    <h6 class="fw-bold text-dark mb-1">Proceeding Without Fiscal Receipt Proof</h6>
                                                    <p class="small text-muted mb-2">
                                                        You have toggled payment without a physical/electronic fiscal receipt. This payment will be settled in the credit ledger, and registered for standard accounting and <strong>ERCA Purchase TAS Excel reporting (PurcType 6)</strong> without Input VAT claim.
                                                    </p>
                                                    <div class="small fw-semibold text-dark">
                                                        <i class="fa-solid fa-circle-check text-success me-1"></i> You can still attach a scanned receipt or bank slip later at any time from the Payment History table.
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    @endif

</div> <!-- closes container-fluid py-3 -->

<script>
    function onAssignAccountChange(selectEl) {
        const selectedOpt = selectEl.options[selectEl.selectedIndex];
        const balDisplay = document.getElementById('assignAccountBalanceDisplay');
        const staffSelect = document.getElementById('assignedFinanceStaffSelect');
        const staffAlert = document.getElementById('assignStaffAutoAlert');

        if (selectedOpt && selectedOpt.value) {
            const bal = parseFloat(selectedOpt.getAttribute('data-balance')) || 0;
            if (balDisplay) {
                balDisplay.innerHTML = `<i class="fa-solid fa-wallet text-success me-1"></i> Selected Account Balance: <strong class="text-dark font-monospace">ETB ${bal.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</strong>`;
            }

            const staffId = selectedOpt.getAttribute('data-staff-id');
            const staffName = selectedOpt.getAttribute('data-staff-name');

            if (staffSelect) {
                if (staffId) {
                    let existingOpt = staffSelect.querySelector(`option[value="${staffId}"]`);
                    if (!existingOpt) {
                        existingOpt = new Option(`👤 ${staffName} (COA Custodian)`, staffId, true, true);
                        staffSelect.add(existingOpt);
                    }
                    staffSelect.value = staffId;
                    if (staffAlert) {
                        staffAlert.style.display = 'block';
                        staffAlert.innerHTML = `<div class="p-2 rounded bg-success bg-opacity-10 border border-success border-opacity-25 text-success small d-flex align-items-center"><i class="fa-solid fa-circle-check fs-6 me-2"></i><div>Auto-assigned to <strong>${staffName}</strong> (COA Custodian).</div></div>`;
                    }
                } else {
                    if (staffAlert) {
                        staffAlert.style.display = 'block';
                        staffAlert.innerHTML = `<div class="p-2 rounded bg-light border text-muted small d-flex align-items-center"><i class="fa-solid fa-circle-info text-info me-2"></i><div>No custodian assigned to this COA in Chart of Accounts. Please choose a Finance staff member or leave default.</div></div>`;
                    }
                }
            }
        } else {
            if (staffAlert) staffAlert.style.display = 'none';
            if (balDisplay) balDisplay.innerHTML = `<i class="fa-solid fa-coins text-warning me-1"></i>Funds will be disbursed from this account.`;
        }
    }

    function onSingleAccountChange(selectEl) {
        const selectedOpt = selectEl.options[selectEl.selectedIndex];
        const methodSelect = document.getElementById('singlePaymentMethodSelect');
        const balDisplay = document.getElementById('singleAccountBalanceDisplay');
        if (selectedOpt && selectedOpt.value) {
            const method = selectedOpt.getAttribute('data-method') || 'bank_transfer';
            const bal = parseFloat(selectedOpt.getAttribute('data-balance')) || 0;
            if (methodSelect) methodSelect.value = method;
            if (balDisplay) {
                balDisplay.innerHTML = `<i class="fa-solid fa-wallet text-success me-1"></i> Selected Account Balance: <strong class="text-dark font-monospace">ETB ${bal.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</strong>`;
            }
        }
    }

    function toggleSingleReceiptMode(isNoReceipt) {
        const withDiv = document.getElementById('singleWithReceiptDiv');
        const withoutDiv = document.getElementById('singleWithoutReceiptDiv');
        const btn = document.getElementById('singleSubmitBtn');
        if (isNoReceipt) {
            if (withDiv) withDiv.classList.add('d-none');
            if (withoutDiv) withoutDiv.classList.remove('d-none');
            if (btn) {
                btn.classList.remove('btn-success');
                btn.classList.add('btn-warning', 'text-dark');
            }
        } else {
            if (withDiv) withDiv.classList.remove('d-none');
            if (withoutDiv) withoutDiv.classList.add('d-none');
            if (btn) {
                btn.classList.remove('btn-warning', 'text-dark');
                btn.classList.add('btn-success');
            }
        }
        recalculateQuickPayTax();
    }

    function setQuickPayFullAmount() {
        const amtInput = document.getElementById('quickPayAmountInput');
        if (amtInput) {
            amtInput.value = '{{ number_format($ledger->remaining_amount, 2, ".", "") }}';
            recalculateQuickPayTax();
        }
    }

    function recalculateQuickPayTax() {
        const grossInput = document.getElementById('quickPayAmountInput');
        const vatTypeSelect = document.getElementById('quickPayVatType');
        const whtToggle = document.getElementById('quickPayWithholdingToggle');
        const whtSection = document.getElementById('quickPayWithholdingSection');

        if (!grossInput) return;
        const gross = parseFloat(grossInput.value) || 0;
        const vatType = vatTypeSelect ? vatTypeSelect.value : 'none';
        const vatRate = 15.00;
        const hasWht = whtToggle ? whtToggle.checked : false;
        const whtRate = 3.00;

        if (whtSection) {
            whtSection.style.display = hasWht ? 'block' : 'none';
        }

        let vatAmount = 0.0;
        let baseAmount = gross;
        let whtAmount = 0.0;
        let netAmount = gross;

        if (vatType === 'exclusive') {
            vatAmount = Math.round(gross * (vatRate / 100) * 100) / 100;
            baseAmount = gross;
            const totalGrossWithVat = gross + vatAmount;
            if (hasWht) {
                whtAmount = Math.round(baseAmount * (whtRate / 100) * 100) / 100;
            }
            netAmount = Math.round((totalGrossWithVat - whtAmount) * 100) / 100;
        } else if (vatType === 'inclusive' || vatType === 'vat_b') {
            baseAmount = Math.round((gross / (1 + (vatRate / 100))) * 100) / 100;
            vatAmount = Math.round((gross - baseAmount) * 100) / 100;
            if (hasWht) {
                whtAmount = Math.round(baseAmount * (whtRate / 100) * 100) / 100;
            }
            netAmount = Math.round((gross - whtAmount) * 100) / 100;
        } else {
            baseAmount = gross;
            vatAmount = 0.0;
            if (hasWht) {
                whtAmount = Math.round(baseAmount * (whtRate / 100) * 100) / 100;
            }
            netAmount = Math.round((gross - whtAmount) * 100) / 100;
        }

        // Set hidden inputs
        const hiddenVat = document.getElementById('quickPayVatAmount');
        const singleVat = document.getElementById('creditSingleVatAmount');
        const hiddenWht = document.getElementById('quickPayWithholdingAmount');
        const hiddenNet = document.getElementById('quickPayNetAmount');
        const singleSubtotal = document.getElementById('creditSingleSubtotal');
        const singleTotal = document.getElementById('creditSingleTotalAmount');
        const singleVatInc = document.getElementById('creditSingleVatIncluded');
        const singlePurcType = document.getElementById('creditSinglePurcType');

        if (hiddenVat) hiddenVat.value = vatAmount.toFixed(2);
        if (singleVat) singleVat.value = vatAmount.toFixed(2);
        if (hiddenWht) hiddenWht.value = whtAmount.toFixed(2);
        if (hiddenNet) hiddenNet.value = netAmount.toFixed(2);
        if (singleSubtotal) singleSubtotal.value = baseAmount.toFixed(2);
        if (singleTotal) singleTotal.value = (vatType === 'exclusive' ? (baseAmount + vatAmount) : gross).toFixed(2);
        if (singleVatInc) singleVatInc.value = (vatType !== 'none' && vatAmount > 0) ? '1' : '0';
        if (singlePurcType) singlePurcType.value = (vatType !== 'none' && vatAmount > 0) ? '3' : '6';

        // Update display labels
        const dispBase = document.getElementById('quickDisplayBaseAmount');
        const dispVat = document.getElementById('quickDisplayVatAmount');
        const dispWht = document.getElementById('quickDisplayWhtAmount');
        const dispNet = document.getElementById('quickDisplayNetAmount');
        const submitBtn = document.getElementById('singleSubmitBtn');

        const fmt = num => 'ETB ' + num.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

        if (dispBase) dispBase.innerText = fmt(baseAmount);
        if (dispVat) dispVat.innerText = '+ ' + fmt(vatAmount);
        if (dispWht) dispWht.innerText = '- ' + fmt(whtAmount);
        if (dispNet) dispNet.innerText = fmt(netAmount);

        if (submitBtn) {
            const isNoReceipt = document.getElementById('singleNoReceiptToggle')?.checked;
            const icon = isNoReceipt ? '<i class="fa-solid fa-ban me-1"></i>' : '<i class="fas fa-check-circle me-1"></i>';
            const receiptTxt = isNoReceipt ? 'Pay Without Receipt' : 'Record Payment';
            submitBtn.innerHTML = `${icon} ${receiptTxt} & Liquidate (Disburse: ${fmt(netAmount)})`;
        }
    }

    document.addEventListener('DOMContentLoaded', () => {
        window.recalculateQuickPayTax = recalculateQuickPayTax;
        window.setQuickPayFullAmount = setQuickPayFullAmount;
        const selSingle = document.getElementById('singlePaymentAccountSelect');
        if (selSingle) onSingleAccountChange(selSingle);
        const selAssign = document.getElementById('assignAccountSelect');
        if (selAssign) onAssignAccountChange(selAssign);
        recalculateQuickPayTax();

        // Initialize AI Receipt OCR Scanner for the full-width studio
        if (window.ReceiptOcrScanner) {
            ReceiptOcrScanner.init({
                fileInput: '#creditSingleReceiptInput',
                form: 'form[action*="record-payment"]',
                submitBtn: '#singleSubmitBtn',
                statusContainer: '#creditSingleOcrFeedbackArea',
                studioMode: true,
                purchaserTin: '0038480010',
                encodedBy: '{{ strtolower(preg_replace("/[^a-zA-Z0-9_]/", "", Auth::user()->name ?? "wechacha")) }}',
                fields: {
                    purchaser_tin: '#creditSinglePurchaserTin',
                    seller_tin: '#creditSingleSellerTin',
                    supplier_tin: '#creditSingleSupplierTin',
                    receipt_no: '#creditSingleReceiptNo',
                    fs_no: '#creditSingleFsNo',
                    receipt_date: 'input[name="payment_date"]',
                    encoding_date: '#creditSingleEncodingDate',
                    mrc: '#creditSingleMrc',
                    purc_type: '#creditSinglePurcType',
                    encoded_by: '#creditSingleEncodedBy',
                    notes: 'input[name="notes"]'
                },
                onScanComplete: function(scanData) {
                    const amtInput = document.getElementById('quickPayAmountInput');
                    if (amtInput && scanData.data && scanData.data.total_amount) {
                        const maxPayable = parseFloat(amtInput.getAttribute('max')) || Infinity;
                        const scannedAmt = parseFloat(scanData.data.total_amount) || 0;
                        if (scannedAmt > 0 && scannedAmt <= maxPayable) {
                            amtInput.value = scannedAmt.toFixed(2);
                            recalculateQuickPayTax();
                        }
                    }
                    const refInput = document.querySelector('form[action*="record-payment"] input[name="reference_no"]');
                    if (refInput && !refInput.value && scanData.data && scanData.data.fs_no) {
                        refInput.value = 'FS-' + scanData.data.fs_no;
                    }
                }
            });
        }
    });
</script>
<script src="{{ asset('js/receipt-ocr-scanner.js') }}"></script>

@foreach($ledger->payments as $pmt)
    @if(!$pmt->receipt_path)
        <div class="modal fade" id="attachReceiptModal_{{ $pmt->id }}" tabindex="-1" aria-labelledby="attachReceiptModalLabel_{{ $pmt->id }}" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-centered">
                <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
                    <form action="{{ route('finance.credit-store.attach-receipt', [$ledger, $pmt]) }}" method="POST" enctype="multipart/form-data">
                        @csrf
                        <div class="modal-header bg-dark text-white py-3 px-4">
                            <div class="d-flex align-items-center gap-2">
                                <span class="badge bg-success text-white p-2 rounded-circle">
                                    <i class="fa-solid fa-receipt fs-6"></i>
                                </span>
                                <div>
                                    <h6 class="modal-title fw-bold mb-0">Attach Receipt Proof via OCR Studio</h6>
                                    <small class="text-white-50">Payment #{{ $pmt->id }} &bull; {{ number_format($pmt->amount, 2) }} ETB &bull; {{ $pmt->payment_date }}</small>
                                </div>
                            </div>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body p-4">
                            <div class="alert alert-light border small text-muted mb-3">
                                <i class="fa-solid fa-wand-magic-sparkles text-success me-1"></i>
                                Upload vendor receipt or bank slip. The Multi-Engine AI will extract fiscal fields and let you route to the <strong>ERCA Line 100 VAT Section</strong> (if VAT included) or declare in <strong>Purchase TAS Excel</strong> (if no VAT).
                            </div>
                            <div class="mb-3">
                                <div class="border border-2 border-dashed rounded-3 p-3 text-center bg-light cursor-pointer"
                                     style="border-color:#10b981 !important;"
                                     onclick="document.getElementById('attachReceiptInput_{{ $pmt->id }}').click()">
                                    <input type="file" name="receipt_file" id="attachReceiptInput_{{ $pmt->id }}" class="d-none" accept=".pdf,.jpg,.jpeg,.png,.webp" required>
                                    <i class="fa-solid fa-cloud-arrow-up fa-2x text-success mb-2"></i>
                                    <div class="fw-bold small text-dark">Click to Browse or Drag &amp; Drop Receipt</div>
                                    <div class="text-muted" style="font-size:0.72rem;">JPG, PNG, WEBP, PDF up to 10MB</div>
                                </div>
                                <div id="attachReceiptFeedback_{{ $pmt->id }}" class="mt-2"></div>
                            </div>
                        </div>
                        <div class="modal-footer bg-light py-2.5 px-4 d-flex justify-content-between">
                            <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-success btn-sm fw-bold px-3" id="attachSubmitBtn_{{ $pmt->id }}">
                                <i class="fa-solid fa-check-circle me-1"></i> Save &amp; Attach Receipt
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                if (window.ReceiptOcrScanner) {
                    ReceiptOcrScanner.init({
                        fileInput: '#attachReceiptInput_{{ $pmt->id }}',
                        form: '#attachReceiptModal_{{ $pmt->id }} form',
                        submitBtn: '#attachSubmitBtn_{{ $pmt->id }}',
                        statusContainer: '#attachReceiptFeedback_{{ $pmt->id }}',
                        studioMode: true,
                        purchaserTin: '0038480010',
                        encodedBy: '{{ strtolower(preg_replace("/[^a-zA-Z0-9_]/", "", Auth::user()->name ?? "wechacha")) }}'
                    });
                }
            });
        </script>
    @endif
@endforeach

@if($isGlobalAdmin)
    @foreach($ledger->payments as $pmt)
        @php
            $linkedReceipt = \App\Models\Receipt::where(function($q) use ($pmt) {
                $q->where('purchasable_type', \App\Models\CreditStorePayment::class)
                  ->where('purchasable_id', $pmt->id);
            })->orWhere(function($q) use ($pmt) {
                if ($pmt->receipt_path) {
                    $q->where('file_path', $pmt->receipt_path);
                }
            })->first();

            $curVatType = $pmt->vat_type ?: 'none';
            $curVatRate = (float)($pmt->vat_rate ?: 15.00);
            $curVatAmount = (float)($pmt->vat_amount ?: 0.00);
            $curHasWht = (bool)($pmt->has_withholding || ($pmt->withholding_amount ?? 0) > 0);
            $curWhtRate = (float)($pmt->withholding_rate ?: 3.00);
            $curWhtAmount = (float)($pmt->withholding_amount ?: 0.00);
            $curNet = (float)($pmt->net_amount ?: $pmt->amount);
            $curBase = (float)($pmt->gross_amount ?: $pmt->amount);
            if ($curVatType === 'inclusive' || $curVatType === 'vat_b') {
                $curBase = round($pmt->amount / 1.15, 2);
            }

            $sellerTin = optional($linkedReceipt)->vendor_tin ?? (optional($ledger->supplier)->tin ?? '');
            $fsNo = optional($linkedReceipt)->fs_no ?? ($pmt->reference_no ?? '');
            $mrcNo = optional($linkedReceipt)->mrc_no ?? '';
            $rcptDate = ($linkedReceipt && $linkedReceipt->receipt_date) 
                ? \Carbon\Carbon::parse($linkedReceipt->receipt_date)->format('Y-m-d') 
                : ($pmt->payment_date ? $pmt->payment_date->format('Y-m-d') : now()->format('Y-m-d'));
            $purcType = ($curVatType !== 'none' && $curVatAmount > 0) ? '3' : '6';
        @endphp

        <!-- Edit Payment VAT Modal for Payment #{{ $pmt->id }} (Global Admin Only) -->
        <div class="modal fade" id="editPaymentVatModal_{{ $pmt->id }}" tabindex="-1" aria-labelledby="editPaymentVatModalLabel_{{ $pmt->id }}" aria-hidden="true" data-bs-backdrop="static">
            <div class="modal-dialog modal-lg modal-dialog-scrollable my-3">
                <form action="{{ route('finance.credit-store.correct-vat', [$ledger, $pmt]) }}" method="POST" enctype="multipart/form-data" class="modal-content border-0 shadow-lg rounded-4 overflow-hidden" style="max-height: calc(100vh - 3.5rem); display: flex; flex-direction: column;">
                    @csrf
                    <div class="modal-header bg-dark text-white py-3 px-4 flex-shrink-0">
                        <div class="d-flex align-items-center gap-2">
                            <span class="badge bg-warning text-dark p-2 rounded-circle">
                                <i class="fa-solid fa-user-shield fs-6"></i>
                            </span>
                            <div>
                                <div class="d-flex align-items-center gap-2">
                                    <h6 class="modal-title fw-bold mb-0">Global Admin: Correct VAT &amp; Tax Declaration</h6>
                                    <span class="badge bg-warning text-dark" style="font-size:0.68rem;">Privileged Mode</span>
                                </div>
                                <small class="text-white-50">
                                    Payment #{{ $pmt->id }} &bull; Amount: {{ number_format($pmt->amount, 2) }} ETB &bull; PR #{{ $ledger->pr_no }} &bull; {{ $ledger->supplier_name ?: 'Supplier' }}
                                </small>
                            </div>
                        </div>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    
                    <div class="modal-body p-4 flex-grow-1" id="editPaymentVatModalBody_{{ $pmt->id }}" style="overflow-y: auto; overscroll-behavior: contain; background-color: #fbfbfb;">
                            <!-- Informational alert -->
                            <div class="alert alert-warning border border-warning-subtle bg-white rounded-3 p-3 mb-3 shadow-2xs">
                                <div class="d-flex align-items-start gap-2">
                                    <i class="fa-solid fa-triangle-exclamation text-warning fs-5 mt-0.5"></i>
                                    <div class="small">
                                        <strong class="text-dark">Post-Payment Tax Adjustment Console:</strong>
                                        <div class="text-muted" style="line-height:1.45;">
                                            This tool allows <strong>Global Admin ONLY</strong> to correct recorded VAT, Withholding Tax, and ERCA Declaration routes even when this Credit Ledger is <strong>100% Fully Paid</strong>. Correcting VAT will synchronize the payment record, recalculate gross/net amounts, update the linked central Receipt and items, and route to either <strong>ERCA Line 100 VAT Section</strong> or <strong>Purchase TAS Excel</strong>.
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="row g-3">
                                <!-- Payment Amount -->
                                <div class="col-12 col-md-6">
                                    <label class="form-label small fw-bold text-dark text-uppercase">
                                        Settlement Amount (Gross) <span class="text-danger">*</span>
                                    </label>
                                    <div class="input-group input-group-sm">
                                        <input type="number" step="0.01" min="0.01" name="amount" id="editVatAmount_{{ $pmt->id }}" 
                                               class="form-control fw-bold font-monospace fs-6" 
                                               value="{{ number_format($pmt->amount, 2, '.', '') }}" 
                                               required oninput="recalculateEditVatTax({{ $pmt->id }})">
                                        <span class="input-group-text bg-white fw-bold text-muted">ETB</span>
                                    </div>
                                    <span class="form-text text-muted small" style="font-size:0.7rem;">Ledger balance liquidated by this payment.</span>
                                </div>

                                <!-- VAT Treatment -->
                                <div class="col-12 col-md-6">
                                    <label class="form-label small fw-bold text-dark text-uppercase">
                                        VAT Treatment Mode <span class="text-danger">*</span>
                                    </label>
                                    <select name="vat_type" id="editVatType_{{ $pmt->id }}" class="form-select form-select-sm fw-semibold" required onchange="recalculateEditVatTax({{ $pmt->id }})">
                                        <option value="none" {{ $curVatType === 'none' ? 'selected' : '' }}>No VAT (0% / Exempt / Non-VAT) &rarr; TAS Excel</option>
                                        <option value="exclusive" {{ $curVatType === 'exclusive' ? 'selected' : '' }}>15% VAT Exclusive (+15% on Base) &rarr; Line 100</option>
                                        <option value="vat_b" {{ in_array($curVatType, ['vat_b', 'inclusive']) ? 'selected' : '' }}>15% VAT Inclusive (Extract 15% from Gross) &rarr; Line 100</option>
                                    </select>
                                    <span class="form-text text-muted small" style="font-size:0.7rem;">Determines whether tax is included in receipt total or added on top.</span>
                                </div>

                                <!-- VAT Rate & VAT Amount Override -->
                                <div class="col-12 col-md-6">
                                    <div class="row g-2">
                                        <div class="col-6">
                                            <label class="form-label small fw-semibold text-muted">VAT Rate (%)</label>
                                            <div class="input-group input-group-sm">
                                                <input type="number" step="0.01" min="0" name="vat_rate" id="editVatRate_{{ $pmt->id }}" 
                                                       class="form-control font-monospace" 
                                                       value="{{ number_format($curVatRate, 2, '.', '') }}" 
                                                       oninput="recalculateEditVatTax({{ $pmt->id }})">
                                                <span class="input-group-text bg-light text-muted small">%</span>
                                            </div>
                                        </div>
                                        <div class="col-6">
                                            <label class="form-label small fw-semibold text-muted">VAT Amount (ETB)</label>
                                            <div class="input-group input-group-sm">
                                                <input type="number" step="0.01" min="0" name="vat_amount" id="editVatAmountVal_{{ $pmt->id }}" 
                                                       class="form-control font-monospace fw-bold text-success" 
                                                       value="{{ number_format($curVatAmount, 2, '.', '') }}">
                                                <span class="input-group-text bg-light text-success small">ETB</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- ERCA Purchase Type Route -->
                                <div class="col-12 col-md-6">
                                    <label class="form-label small fw-bold text-dark text-uppercase">
                                        ERCA Declaration Route <span class="text-danger">*</span>
                                    </label>
                                    <select name="purc_type" id="editPurcType_{{ $pmt->id }}" class="form-select form-select-sm fw-semibold">
                                        <option value="3" {{ $purcType === '3' ? 'selected' : '' }}>Type 3 - Goods (ERCA Line 100 VAT Section)</option>
                                        <option value="6" {{ $purcType === '6' ? 'selected' : '' }}>Type 6 - Exempt / Non-VAT (Purchase TAS Excel)</option>
                                        <option value="1" {{ $purcType === '1' ? 'selected' : '' }}>Type 1 - Capital Assets</option>
                                        <option value="2" {{ $purcType === '2' ? 'selected' : '' }}>Type 2 - Raw Materials</option>
                                        <option value="4" {{ $purcType === '4' ? 'selected' : '' }}>Type 4 - Services</option>
                                    </select>
                                    <span class="form-text text-muted small" style="font-size:0.7rem;">Controls which ERCA export tab this receipt populates.</span>
                                </div>

                                <!-- Withholding Tax Checkbox -->
                                <div class="col-12">
                                    <div class="card border rounded-3 p-3 bg-white shadow-2xs">
                                        <div class="form-check form-switch mb-2">
                                            <input class="form-check-input" type="checkbox" name="has_withholding" value="1" 
                                                   id="editHasWithholding_{{ $pmt->id }}" 
                                                   {{ $curHasWht ? 'checked' : '' }} 
                                                   onchange="recalculateEditVatTax({{ $pmt->id }})">
                                            <label class="form-check-label fw-bold small text-dark" for="editHasWithholding_{{ $pmt->id }}">
                                                <i class="fa-solid fa-file-invoice-dollar text-danger me-1"></i> Apply 3% Withholding Tax (WHT) Deduction
                                            </label>
                                        </div>

                                        <div id="editWhtSection_{{ $pmt->id }}" style="display: {{ $curHasWht ? 'block' : 'none' }};">
                                            <div class="row g-2 pt-2 border-top">
                                                <div class="col-12 col-md-3">
                                                    <label class="form-label small text-muted mb-1">WHT Rate (%)</label>
                                                    <div class="input-group input-group-sm">
                                                        <input type="number" step="0.01" min="0" name="withholding_rate" id="editWhtRate_{{ $pmt->id }}" 
                                                               class="form-control font-monospace" 
                                                               value="{{ number_format($curWhtRate, 2, '.', '') }}" 
                                                               oninput="recalculateEditVatTax({{ $pmt->id }})">
                                                        <span class="input-group-text bg-light text-muted small">%</span>
                                                    </div>
                                                </div>
                                                <div class="col-12 col-md-3">
                                                    <label class="form-label small text-muted mb-1">WHT Deducted (ETB)</label>
                                                    <div class="input-group input-group-sm">
                                                        <input type="number" step="0.01" min="0" name="withholding_amount" id="editWhtAmountVal_{{ $pmt->id }}" 
                                                               class="form-control font-monospace fw-bold text-danger" 
                                                               value="{{ number_format($curWhtAmount, 2, '.', '') }}">
                                                        <span class="input-group-text bg-light text-danger small">ETB</span>
                                                    </div>
                                                </div>
                                                <div class="col-12 col-md-6">
                                                    <label class="form-label small text-muted mb-1">Withholding Receipt #</label>
                                                    <input type="text" name="withholding_receipt_number" class="form-control form-control-sm font-monospace" 
                                                           value="{{ $pmt->withholding_receipt_number }}" placeholder="e.g. WHT-2026-0045">
                                                </div>
                                                <div class="col-12">
                                                    <label class="form-label small text-muted mb-1">Replace / Upload Withholding Slip File</label>
                                                    <input type="file" name="withholding_receipt" class="form-control form-control-sm" accept=".pdf,.jpg,.jpeg,.png,.webp">
                                                    @if($pmt->withholding_receipt)
                                                        <div class="mt-1 small">
                                                            <a href="{{ asset($pmt->withholding_receipt) }}" target="_blank" class="text-primary text-decoration-none">
                                                                <i class="fa-solid fa-paperclip me-1"></i> Current WHT Slip Attached
                                                            </a>
                                                        </div>
                                                    @endif
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Fiscal Metadata for Central Receipt & ERCA Export -->
                                <div class="col-12">
                                    <div class="card border rounded-3 p-3 bg-white shadow-2xs">
                                        <h6 class="fw-bold small text-dark mb-2 text-uppercase">
                                            <i class="fa-solid fa-receipt text-primary me-1"></i> Fiscal Document &amp; Supplier Metadata
                                        </h6>
                                        <div class="row g-2">
                                            <div class="col-12 col-md-4">
                                                <label class="form-label small text-muted mb-1">Supplier TIN</label>
                                                <input type="text" name="supplier_tin" maxlength="30" class="form-control form-control-sm font-monospace" 
                                                       value="{{ $sellerTin }}" placeholder="10 digits (e.g. 0001234567)">
                                            </div>
                                            <div class="col-12 col-md-4">
                                                <label class="form-label small text-muted mb-1">FS # / Receipt No</label>
                                                <input type="text" name="fs_no" maxlength="50" class="form-control form-control-sm font-monospace" 
                                                       value="{{ $fsNo }}" placeholder="8 digits (e.g. 12345678)">
                                            </div>
                                            <div class="col-12 col-md-4">
                                                <label class="form-label small text-muted mb-1">MRC Machine No</label>
                                                <input type="text" name="mrc_no" maxlength="100" class="form-control form-control-sm font-monospace" 
                                                       value="{{ $mrcNo }}" placeholder="e.g. MOR-12345">
                                            </div>
                                            <div class="col-12 col-md-4">
                                                <label class="form-label small text-muted mb-1">Receipt Date</label>
                                                <input type="date" name="receipt_date" class="form-control form-control-sm font-monospace" 
                                                       value="{{ $rcptDate }}">
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Live Recalculated Summary Card -->
                                <div class="col-12">
                                    <div class="card border-0 bg-dark text-white rounded-3 p-3 shadow-sm">
                                        <div class="d-flex align-items-center justify-content-between mb-2">
                                            <span class="small text-white-50 text-uppercase fw-semibold">Live Recalculated Breakdown</span>
                                            <span id="editRouteBadge_{{ $pmt->id }}" class="badge {{ ($curVatType !== 'none' && $curVatAmount > 0) ? 'bg-success-subtle text-success border border-success' : 'bg-secondary-subtle text-secondary border border-secondary' }} px-2 py-1">
                                                @if($curVatType !== 'none' && $curVatAmount > 0)
                                                    <i class="fa-solid fa-file-invoice-dollar me-1"></i> Sent to ERCA Line 100 VAT Section
                                                @else
                                                    <i class="fa-solid fa-file-excel me-1"></i> Sent to Purchase TAS Excel (Zero VAT / PurcType 6)
                                                @endif
                                            </span>
                                        </div>
                                        <div class="row g-2 text-center font-monospace">
                                            <div class="col-3 border-end border-secondary border-opacity-50">
                                                <div class="small text-white-50" style="font-size: 0.7rem;">Base Value</div>
                                                <div class="fw-bold text-white fs-6" id="editDisplayBase_{{ $pmt->id }}">
                                                    ETB {{ number_format($curBase, 2) }}
                                                </div>
                                            </div>
                                            <div class="col-3 border-end border-secondary border-opacity-50">
                                                <div class="small text-white-50" style="font-size: 0.7rem;">15% VAT</div>
                                                <div class="fw-bold text-success fs-6" id="editDisplayVat_{{ $pmt->id }}">
                                                    {{ $curVatAmount > 0 ? '+ ' : '' }}ETB {{ number_format($curVatAmount, 2) }}
                                                </div>
                                            </div>
                                            <div class="col-3 border-end border-secondary border-opacity-50">
                                                <div class="small text-white-50" style="font-size: 0.7rem;">3% WHT</div>
                                                <div class="fw-bold text-danger fs-6" id="editDisplayWht_{{ $pmt->id }}">
                                                    {{ $curWhtAmount > 0 ? '- ' : '' }}ETB {{ number_format($curWhtAmount, 2) }}
                                                </div>
                                            </div>
                                            <div class="col-3">
                                                <div class="small text-white-50" style="font-size: 0.7rem;">Net Disbursed</div>
                                                <div class="fw-bold text-warning fs-6" id="editDisplayNet_{{ $pmt->id }}">
                                                    ETB {{ number_format($curNet, 2) }}
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Mandatory Correction Reason -->
                                <div class="col-12">
                                    <label class="form-label small fw-bold text-dark text-uppercase">
                                        Reason for VAT Correction <span class="text-danger">*</span>
                                    </label>
                                    <textarea name="correction_reason" class="form-control form-control-sm rounded-2" rows="2" 
                                              placeholder="Provide specific justification (e.g., Supplier provided fiscal invoice with 15% VAT FS-12345678 that was mistakenly recorded as No VAT during settlement; withholding slip verified)." required></textarea>
                                    <span class="form-text text-muted small" style="font-size:0.7rem;">
                                        <i class="fa-solid fa-clock-rotate-left me-1"></i> A permanent audit entry with your admin name ({{ Auth::user()->name }}) and timestamp will be logged.
                                    </span>
                                </div>
                            </div>
                        </div>

                    <div class="modal-footer bg-light py-2.5 px-4 d-flex justify-content-between flex-shrink-0">
                        <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-warning text-dark fw-bold btn-sm px-3 shadow-sm">
                            <i class="fa-solid fa-check-double me-1"></i> Save Correction &amp; Resync Records
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endforeach

    <!-- JavaScript for recalculating VAT in Edit Modal -->
    <script>
        function recalculateEditVatTax(pmtId) {
            const grossInput = document.getElementById('editVatAmount_' + pmtId);
            const vatTypeSelect = document.getElementById('editVatType_' + pmtId);
            const vatRateInput = document.getElementById('editVatRate_' + pmtId);
            const vatAmountInput = document.getElementById('editVatAmountVal_' + pmtId);
            const whtToggle = document.getElementById('editHasWithholding_' + pmtId);
            const whtRateInput = document.getElementById('editWhtRate_' + pmtId);
            const whtAmountInput = document.getElementById('editWhtAmountVal_' + pmtId);
            const purcTypeSelect = document.getElementById('editPurcType_' + pmtId);

            if (!grossInput) return;
            const gross = parseFloat(grossInput.value) || 0;
            const vatType = vatTypeSelect ? vatTypeSelect.value : 'none';
            const vatRate = vatRateInput ? (parseFloat(vatRateInput.value) || 15.0) : 15.0;
            const hasWht = whtToggle ? whtToggle.checked : false;
            const whtRate = whtRateInput ? (parseFloat(whtRateInput.value) || 3.0) : 3.0;

            const whtSection = document.getElementById('editWhtSection_' + pmtId);
            if (whtSection) {
                whtSection.style.display = hasWht ? 'block' : 'none';
            }

            let vatAmount = 0.0;
            let baseAmount = gross;
            let whtAmount = 0.0;
            let netAmount = gross;

            if (vatType === 'exclusive') {
                vatAmount = Math.round(gross * (vatRate / 100) * 100) / 100;
                baseAmount = gross;
                const totalGrossWithVat = gross + vatAmount;
                if (hasWht) {
                    whtAmount = Math.round(baseAmount * (whtRate / 100) * 100) / 100;
                }
                netAmount = Math.round((totalGrossWithVat - whtAmount) * 100) / 100;
                if (purcTypeSelect && purcTypeSelect.value === '6') {
                    purcTypeSelect.value = '3';
                }
            } else if (vatType === 'inclusive' || vatType === 'vat_b') {
                baseAmount = Math.round((gross / (1 + (vatRate / 100))) * 100) / 100;
                vatAmount = Math.round((gross - baseAmount) * 100) / 100;
                if (hasWht) {
                    whtAmount = Math.round(baseAmount * (whtRate / 100) * 100) / 100;
                }
                netAmount = Math.round((gross - whtAmount) * 100) / 100;
                if (purcTypeSelect && purcTypeSelect.value === '6') {
                    purcTypeSelect.value = '3';
                }
            } else {
                baseAmount = gross;
                vatAmount = 0.0;
                if (hasWht) {
                    whtAmount = Math.round(baseAmount * (whtRate / 100) * 100) / 100;
                }
                netAmount = Math.round((gross - whtAmount) * 100) / 100;
                if (purcTypeSelect && purcTypeSelect.value === '3') {
                    purcTypeSelect.value = '6';
                }
            }

            if (vatAmountInput) {
                vatAmountInput.value = vatAmount.toFixed(2);
            }
            if (whtAmountInput) {
                whtAmountInput.value = whtAmount.toFixed(2);
            }

            const fmt = num => 'ETB ' + num.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

            const dispBase = document.getElementById('editDisplayBase_' + pmtId);
            const dispVat = document.getElementById('editDisplayVat_' + pmtId);
            const dispWht = document.getElementById('editDisplayWht_' + pmtId);
            const dispNet = document.getElementById('editDisplayNet_' + pmtId);
            const routeBadge = document.getElementById('editRouteBadge_' + pmtId);

            if (dispBase) dispBase.innerText = fmt(baseAmount);
            if (dispVat) dispVat.innerText = (vatAmount > 0 ? '+ ' : '') + fmt(vatAmount);
            if (dispWht) dispWht.innerText = (whtAmount > 0 ? '- ' : '') + fmt(whtAmount);
            if (dispNet) dispNet.innerText = fmt(netAmount);

            if (routeBadge) {
                if (vatType !== 'none' && vatAmount > 0) {
                    routeBadge.className = 'badge bg-success-subtle text-success border border-success px-2 py-1';
                    routeBadge.innerHTML = '<i class="fa-solid fa-file-invoice-dollar me-1"></i> Sent to ERCA Line 100 VAT Section (Input VAT Claimable)';
                } else {
                    routeBadge.className = 'badge bg-secondary-subtle text-secondary border border-secondary px-2 py-1';
                    routeBadge.innerHTML = '<i class="fa-solid fa-file-excel me-1"></i> Sent to Purchase TAS Excel (Zero VAT / PurcType 6)';
                }
            }
        }

        document.addEventListener('DOMContentLoaded', () => {
            document.querySelectorAll('[id^="editPaymentVatModal_"]').forEach(modalEl => {
                modalEl.addEventListener('shown.bs.modal', function () {
                    const bodyEl = this.querySelector('.modal-body');
                    if (bodyEl) {
                        bodyEl.scrollTop = 0;
                    }
                });
            });
        });
    </script>
@endif

@endsection
