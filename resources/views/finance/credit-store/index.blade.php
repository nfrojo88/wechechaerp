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
        <div class="d-flex flex-wrap gap-2">
            <button type="button" class="btn btn-success btn-sm shadow-sm fw-bold" id="headerBatchPayBtn" onclick="openBatchPaymentModal(false)" disabled>
                <i class="fas fa-receipt me-1"></i> Settle with Receipt (<span id="headerBatchCount">0</span>)
            </button>
            <button type="button" class="btn btn-warning btn-sm shadow-sm text-dark fw-bold" id="headerBatchNoReceiptBtn" onclick="openBatchPaymentModal(true)" disabled>
                <i class="fa-solid fa-ban me-1"></i> Pay Without Receipt (<span id="headerBatchCountNoRec">0</span>)
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
                        <span>Disburse from selected account with or without receipt proof.</span>
                    </div>
                </div>
            </div>
            <div class="d-flex align-items-center gap-2">
                <button type="button" class="btn btn-sm btn-outline-secondary bg-white shadow-sm" onclick="clearAllSelections()">
                    <i class="fas fa-times me-1"></i> Deselect All
                </button>
                <button type="button" class="btn btn-sm btn-success fw-bold shadow px-3 py-2 d-flex align-items-center gap-2" onclick="openBatchPaymentModal(false)">
                    <i class="fa-solid fa-receipt"></i>
                    <span>Settle with Receipt</span>
                </button>
                <button type="button" class="btn btn-sm btn-warning text-dark fw-bold shadow px-3 py-2 d-flex align-items-center gap-2" onclick="openBatchPaymentModal(true)">
                    <i class="fa-solid fa-ban"></i>
                    <span>Pay Without Receipt</span>
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
                        <th>Materials</th>
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
                            $prItems = $ledger->purchaseRequest?->items ?? collect();
                            $prItemsCount = $prItems->count();
                            $calculatedItemsTotal = 0.0;
                            foreach ($prItems as $it) {
                                $uPrice = (float)($it->estimated_unit_cost ?? $it->estimated_unit_price ?? $it->unit_price ?? 0);
                                $calculatedItemsTotal += (float)($it->estimated_total > 0 ? $it->estimated_total : ((float)$it->quantity * $uPrice));
                            }
                            $calculatedItemsWithVat = round($calculatedItemsTotal * 1.15, 2);
                            $itemsJson = $prItems->map(function($it) {
                                $uPrice = (float)($it->estimated_unit_cost ?? $it->estimated_unit_price ?? $it->unit_price ?? 0);
                                $lTotal = (float)($it->estimated_total > 0 ? $it->estimated_total : ((float)$it->quantity * $uPrice));
                                return [
                                    'id'         => $it->id,
                                    'name'       => optional($it->product)->name ?? ($it->item_name ?? 'Material Item'),
                                    'code'       => optional($it->product)->item_code ?? (optional($it->product)->sku ?? ''),
                                    'quantity'   => (float)$it->quantity,
                                    'unit'       => $it->unit ?? (optional($it->product)->unit ?? 'pcs'),
                                    'unit_cost'  => $uPrice,
                                    'line_total' => $lTotal,
                                ];
                            })->values();
                        @endphp
                        <tr class="{{ $isPayable ? 'table-row-selectable' : 'table-light' }}"
                            id="ledger-row-{{ $ledger->id }}"
                            data-id="{{ $ledger->id }}"
                            data-pr="{{ $ledger->pr_no ?? ($ledger->purchaseRequest->pr_no ?? 'PR-'.$ledger->id) }}"
                            data-project="{{ $ledger->project->name ?? '—' }}"
                            data-supplier="{{ $ledger->supplier_name ?: 'Credit Supplier' }}"
                            data-credit="{{ (float)$ledger->credit_amount }}"
                            data-paid="{{ (float)$ledger->paid_amount }}"
                            data-calc-sum="{{ (float)$calculatedItemsTotal }}"
                            data-calc-vat="{{ (float)$calculatedItemsWithVat }}"
                            data-gm-amount="{{ (float)($ledger->purchaseRequest->direct_buy_amount ?? 0) }}"
                            data-sync-url="{{ route('finance.credit-store.sync-amount', $ledger) }}"
                            data-coa="{{ optional($ledger->coaAccount)->code ?? '5110' }} - {{ optional($ledger->coaAccount)->name ?? 'Cost Of Material By Credit' }}"
                            data-items='@json($itemsJson)'>
                            <td class="ps-3">
                                @if($isPayable)
                                    <input type="checkbox" class="form-check-input credit-checkbox"
                                        value="{{ $ledger->id }}"
                                        data-id="{{ $ledger->id }}"
                                        data-pr="{{ $ledger->pr_no ?? ($ledger->purchaseRequest->pr_no ?? 'PR-'.$ledger->id) }}"
                                        data-project="{{ $ledger->project->name ?? '—' }}"
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
                                    #{{ $ledger->pr_no ?? ($ledger->purchaseRequest->pr_no ?? 'PR-'.$ledger->id) }}
                                </a>
                                @if($ledger->purchaseRequest)
                                    <div class="small text-muted font-monospace" style="font-size: 0.75rem;">
                                        Stage: {{ $ledger->purchaseRequest->status_label }}
                                    </div>
                                @endif
                            </td>
                            <td>
                                <div class="fw-semibold text-dark">{{ $ledger->project->name ?? '—' }}</div>
                            </td>
                            <td>
                                <div><i class="fas fa-building text-muted me-1 small"></i> {{ $ledger->supplier_name ?: 'Credit Supplier' }}</div>
                                <div class="small text-muted font-monospace" style="font-size: 0.75rem;">
                                    COA: {{ $ledger->coaAccount->code ?? '5110' }} - {{ $ledger->coaAccount->name ?? 'Cost Of Material By Credit' }}
                                </div>
                            </td>
                            <td>
                                @if($prItemsCount > 0)
                                    <button type="button" class="btn btn-xs btn-outline-primary py-0.5 px-2 rounded-pill fw-semibold shadow-2xs d-inline-flex align-items-center gap-1"
                                        onclick="openMaterialMoneyModal({{ $ledger->id }}, 'materials')"
                                        title="Click to view material list">
                                        <i class="fa-solid fa-boxes-stacked text-primary"></i>
                                        <span>{{ $prItemsCount }} Material{{ $prItemsCount > 1 ? 's' : '' }}</span>
                                    </button>
                                    <div class="text-muted small text-truncate mt-1" style="max-width: 200px; font-size: 0.74rem;" title="{{ $prItems->map(fn($i) => (optional($i->product)->name ?? $i->item_name) . ' (' . number_format($i->quantity, 2) . ' ' . ($i->unit ?? optional($i->product)->unit) . ')')->implode(', ') }}">
                                        {{ $prItems->map(fn($i) => (optional($i->product)->name ?? $i->item_name))->take(2)->implode(', ') }}{{ $prItemsCount > 2 ? '...' : '' }}
                                    </div>
                                @else
                                    <span class="text-muted small fst-italic">Direct Amount</span>
                                @endif
                            </td>
                            <td class="text-muted">
                                {{ $ledger->authorized_at ? $ledger->authorized_at->format('M d, Y') : ($ledger->created_at ? $ledger->created_at->format('M d, Y') : '—') }}
                                <div class="small text-muted" style="font-size: 0.75rem;">
                                    by {{ $ledger->authorizedByUser->name ?? 'GM' }}
                                </div>
                            </td>
                            <td class="text-end fw-bold text-dark">
                                <div class="d-flex align-items-center justify-content-end gap-1">
                                    <span>{{ number_format($ledger->credit_amount, 2) }}</span>
                                    <button type="button" class="btn btn-link btn-sm p-0 text-primary border-0 shadow-none"
                                        onclick="openMaterialMoneyModal({{ $ledger->id }}, 'money')"
                                        title="Update Credit Money / Purchase Amount">
                                        <i class="fa-solid fa-pen-to-square"></i>
                                    </button>
                                </div>
                                <div class="small text-muted font-monospace" style="font-size: 0.72rem;">ETB</div>
                                @if($calculatedItemsTotal > 0 && abs($calculatedItemsTotal - (float)$ledger->credit_amount) > 0.01)
                                    <div class="badge bg-warning-subtle text-warning border border-warning font-monospace" style="font-size: 0.68rem;" title="Items line sum: {{ number_format($calculatedItemsTotal, 2) }} ETB">
                                        Items: {{ number_format($calculatedItemsTotal, 2) }}
                                    </div>
                                @endif
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
                                    <button type="button" class="btn btn-info btn-sm text-white fw-bold shadow-sm"
                                        onclick="openMaterialMoneyModal({{ $ledger->id }}, 'both')"
                                        title="View Material List & Update Purchase Money">
                                        <i class="fa-solid fa-coins me-1"></i> Update Money
                                    </button>
                                    @if($hasReceipt)
                                        <a href="{{ asset($hasReceipt->receipt_path) }}" target="_blank" class="btn btn-outline-success btn-sm shadow-sm" title="View Uploaded Receipt Proof">
                                            <i class="fas fa-paperclip"></i> Receipt
                                        </a>
                                    @endif
                                    @if($isPayable)
                                        <button type="button" class="btn btn-warning btn-sm text-dark fw-bold shadow-sm" onclick="paySingleCredit({{ $ledger->id }}, true)" title="Quickly pay this credit without uploading receipt">
                                            <i class="fa-solid fa-ban me-1"></i> Pay No Receipt
                                        </button>
                                    @endif
                                    <a href="{{ route('finance.credit-store.show', $ledger) }}" class="btn btn-outline-primary btn-sm shadow-sm" title="View Details & Record Payment">
                                        <i class="fas fa-credit-card me-1"></i> Manage
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
                            <td colspan="11" class="text-center py-5 text-muted">
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
<!-- MATERIAL ITEMS & CREDIT MONEY SYNC MODAL                        -->
<!-- =============================================================== -->
<div class="modal fade" id="materialMoneyModal" tabindex="-1" aria-labelledby="materialMoneyModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable" style="max-height: 94vh;">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden d-flex flex-column" style="max-height: 92vh;">
            <form action="" method="POST" id="materialMoneyForm" class="d-flex flex-column h-100 overflow-hidden m-0">
                @csrf
                <!-- Header -->
                <div class="modal-header py-3 px-4 flex-shrink-0 text-white" style="background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);">
                    <div class="d-flex align-items-center gap-3">
                        <div class="rounded-circle p-2 d-flex align-items-center justify-content-center shadow-sm" style="width: 44px; height: 44px; background: rgba(59, 130, 246, 0.2);">
                            <i class="fa-solid fa-boxes-stacked text-white fs-5"></i>
                        </div>
                        <div>
                            <div class="d-flex align-items-center gap-2">
                                <h5 class="modal-title fw-bold mb-0 text-white" id="materialMoneyModalLabel">PR Materials &amp; Credit Value</h5>
                                <span class="badge bg-primary text-white rounded-pill px-2.5 py-1 font-monospace" id="mmPrBadge">PR #</span>
                            </div>
                            <small class="text-white-50" id="mmSubTitle">Project: — &bull; Supplier: —</small>
                        </div>
                    </div>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <!-- Navigation Tabs -->
                <div class="bg-light border-bottom px-4 pt-2 flex-shrink-0">
                    <ul class="nav nav-tabs border-0" id="mmTabList" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active fw-bold px-3 py-2" id="tab-materials-btn" data-bs-toggle="tab" data-bs-target="#tab-materials-pane" type="button" role="tab" aria-controls="tab-materials-pane" aria-selected="true">
                                <i class="fa-solid fa-boxes-stacked me-1.5 text-primary"></i> 1. Material Items List (<span id="mmItemCountBadge">0</span>)
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link fw-bold px-3 py-2" id="tab-money-btn" data-bs-toggle="tab" data-bs-target="#tab-money-pane" type="button" role="tab" aria-controls="tab-money-pane" aria-selected="false">
                                <i class="fa-solid fa-coins me-1.5 text-success"></i> 2. Update Money / Credit Value
                            </button>
                        </li>
                    </ul>
                </div>

                <!-- Scrollable Body -->
                <div class="modal-body p-4 flex-grow-1 overflow-auto">
                    <div class="tab-content" id="mmTabContent">
                        <!-- TAB 1: MATERIAL ITEMS -->
                        <div class="tab-pane fade show active" id="tab-materials-pane" role="tabpanel" aria-labelledby="tab-materials-btn">
                            
                            <!-- Financial Summary Cards -->
                            <div class="row g-2 mb-3">
                                <div class="col-md-4">
                                    <div class="p-2.5 rounded-3 bg-light border text-center">
                                        <div class="text-muted small text-uppercase fw-semibold" style="font-size:0.7rem;">Items Base Sum</div>
                                        <div class="fs-6 fw-bold text-primary font-monospace" id="mmHeaderBaseSum">0.00 ETB</div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="p-2.5 rounded-3 bg-light border text-center">
                                        <div class="text-muted small text-uppercase fw-semibold" style="font-size:0.7rem;">Items Sum + 15% VAT</div>
                                        <div class="fs-6 fw-bold text-success font-monospace" id="mmHeaderVatSum">0.00 ETB</div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="p-2.5 rounded-3 bg-light border text-center">
                                        <div class="text-muted small text-uppercase fw-semibold" style="font-size:0.7rem;">Current Ledger Money</div>
                                        <div class="fs-6 fw-bold text-dark font-monospace" id="mmHeaderCurrentCredit">0.00 ETB</div>
                                    </div>
                                </div>
                            </div>

                            <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">
                                <div class="small text-muted">
                                    <i class="fa-solid fa-circle-info text-primary me-1"></i>
                                    Adjust quantity or unit cost below if the supplier invoice differs. Totals recalculate in real-time.
                                </div>
                                <span class="badge bg-light text-dark border px-2 py-1" id="mmItemsSummaryBadge">0 items</span>
                            </div>

                            <!-- Materials Table -->
                            <div class="table-responsive border rounded-3 shadow-2xs mb-3">
                                <table class="table table-sm table-hover align-middle mb-0" style="font-size: 0.85rem;">
                                    <thead class="bg-light text-muted small text-uppercase">
                                        <tr>
                                            <th class="ps-3" style="width: 40px;">#</th>
                                            <th>Item / Material</th>
                                            <th style="width: 140px;">Quantity</th>
                                            <th style="width: 80px;">Unit</th>
                                            <th style="width: 170px;">Unit Cost (ETB)</th>
                                            <th class="text-end pe-3" style="width: 170px;">Line Total (ETB)</th>
                                        </tr>
                                    </thead>
                                    <tbody id="mmItemsTableBody">
                                        <!-- Dynamically generated rows -->
                                    </tbody>
                                    <tfoot class="bg-light border-top fw-bold">
                                        <tr>
                                            <td colspan="4" class="text-end ps-3 text-uppercase small text-muted">Calculated Items Subtotal:</td>
                                            <td colspan="2" class="text-end pe-3 font-monospace text-primary fs-6" id="mmTableFootSubtotal">0.00 ETB</td>
                                        </tr>
                                        <tr>
                                            <td colspan="4" class="text-end ps-3 text-uppercase small text-muted">+ 15% VAT Value:</td>
                                            <td colspan="2" class="text-end pe-3 font-monospace text-secondary small" id="mmTableFootVat">+0.00 ETB</td>
                                        </tr>
                                        <tr class="table-success bg-opacity-25">
                                            <td colspan="4" class="text-end ps-3 text-uppercase small text-dark">Total Payable with VAT (15%):</td>
                                            <td colspan="2" class="text-end pe-3 font-monospace text-success fs-6 fw-bold" id="mmTableFootTotalVat">0.00 ETB</td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>

                            <div class="d-flex justify-content-end">
                                <button type="button" class="btn btn-sm btn-outline-primary fw-semibold" onclick="switchToMoneyTab('items_sum')">
                                    Continue to Update Money &rarr;
                                </button>
                            </div>
                        </div>

                        <!-- TAB 2: UPDATE MONEY -->
                        <div class="tab-pane fade" id="tab-money-pane" role="tabpanel" aria-labelledby="tab-money-btn">
                            <div class="alert alert-light border shadow-sm rounded-3 mb-3 p-3">
                                <div class="d-flex align-items-center gap-2 mb-1">
                                    <i class="fa-solid fa-scale-balanced text-primary fs-5"></i>
                                    <strong class="text-dark">Select How to Update Credit Money</strong>
                                </div>
                                <div class="small text-muted">
                                    Select the target amount you wish to set as the total credit liability for this store record. This will update the credit purchase amount, the remaining liability, and status.
                                </div>
                            </div>

                            <div class="list-group mb-3 shadow-2xs">
                                <!-- Option 1: Items Calculated Sum -->
                                <label class="list-group-item list-group-item-action d-flex align-items-center gap-3 p-3 cursor-pointer">
                                    <input class="form-check-input flex-shrink-0 mt-0" type="radio" name="sync_mode" value="items_sum" id="mmSyncModeItemsSum" checked onchange="toggleMmCustomAmount(false)">
                                    <div class="w-100">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <strong class="text-dark small">Procured Items Calculated Sum</strong>
                                            <span class="badge bg-primary font-monospace fs-6" id="mmRadioBaseSumBadge">0.00 ETB</span>
                                        </div>
                                        <div class="text-muted small" style="font-size:0.75rem;">Calculated directly from the quantities and unit prices of items listed in Tab 1.</div>
                                    </div>
                                </label>

                                <!-- Option 2: Items Calculated Sum + 15% VAT -->
                                <label class="list-group-item list-group-item-action d-flex align-items-center gap-3 p-3 cursor-pointer">
                                    <input class="form-check-input flex-shrink-0 mt-0" type="radio" name="sync_mode" value="items_sum_with_vat" id="mmSyncModeItemsVat" onchange="toggleMmCustomAmount(false)">
                                    <div class="w-100">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <strong class="text-dark small">Items Sum + 15% VAT (Exclusive Mode)</strong>
                                            <span class="badge bg-success font-monospace fs-6" id="mmRadioVatSumBadge">0.00 ETB</span>
                                        </div>
                                        <div class="text-muted small" style="font-size:0.75rem;">Adds 15% Ethiopian VAT to the base items total. Recommended if vendor invoiced VAT on top.</div>
                                    </div>
                                </label>

                                <!-- Option 3: GM Approved PR Direct Buy Amount (if available) -->
                                <label class="list-group-item list-group-item-action d-flex align-items-center gap-3 p-3 cursor-pointer" id="mmGmOptionWrapper">
                                    <input class="form-check-input flex-shrink-0 mt-0" type="radio" name="sync_mode" value="pr_direct" id="mmSyncModeGm" onchange="toggleMmCustomAmount(false)">
                                    <div class="w-100">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <strong class="text-dark small">GM Approved Request Total</strong>
                                            <span class="badge bg-secondary font-monospace" id="mmRadioGmBadge">0.00 ETB</span>
                                        </div>
                                        <div class="text-muted small" style="font-size:0.75rem;">Keep or revert to the initial lump-sum amount entered during GM direct-buy approval.</div>
                                    </div>
                                </label>

                                <!-- Option 4: Custom Amount Entry -->
                                <label class="list-group-item list-group-item-action d-flex align-items-center gap-3 p-3 cursor-pointer">
                                    <input class="form-check-input flex-shrink-0 mt-0" type="radio" name="sync_mode" value="custom" id="mmSyncModeCustom" onchange="toggleMmCustomAmount(true)">
                                    <div class="w-100">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <strong class="text-dark small">Custom Purchase / Invoice Value</strong>
                                            <span class="badge bg-dark font-monospace">Manual Entry</span>
                                        </div>
                                        <div class="text-muted small" style="font-size:0.75rem;">Specify an exact agreed amount if the vendor's physical invoice has discounts or charges.</div>
                                    </div>
                                </label>
                            </div>

                            <!-- Custom Amount Input Group -->
                            <div id="mmCustomAmountWrapper" class="card border rounded-3 p-3 mb-3 bg-light d-none">
                                <label class="form-label small fw-bold text-uppercase text-dark mb-1">Enter Custom Purchase Amount (ETB) <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text bg-white fw-bold text-dark">ETB</span>
                                    <input type="number" step="0.01" min="0.01" name="custom_amount" id="mmCustomAmountInput" class="form-control fw-bold" placeholder="e.g. 1582.50">
                                </div>
                                <div class="form-text small text-muted">This exact amount will be recorded as the credit ledger purchase total.</div>
                            </div>

                            <!-- Synchronize PR checkbox -->
                            <div class="form-check form-switch p-2 bg-light rounded-3 border">
                                <input class="form-check-input ms-0 me-2" type="checkbox" name="sync_pr" value="1" id="mmSyncPrToggle" checked>
                                <label class="form-check-label small text-dark fw-semibold" for="mmSyncPrToggle">
                                    Also update Purchase Request total value (<span class="text-primary font-monospace">direct_buy_amount</span>) to stay synchronized.
                                </label>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Footer -->
                <div class="modal-footer bg-light border-top py-3 px-4 d-flex align-items-center justify-content-between flex-shrink-0">
                    <button type="button" class="btn btn-outline-secondary btn-sm px-3 fw-semibold" data-bs-dismiss="modal">
                        <i class="fas fa-times me-1"></i> Close
                    </button>
                    <div class="d-flex align-items-center gap-2">
                        <span class="text-muted small me-2 d-none d-sm-inline" id="mmFooterSummaryHint"></span>
                        <button type="submit" class="btn btn-primary fw-bold shadow-sm px-4 py-2" id="mmSubmitBtn">
                            <i class="fa-solid fa-floppy-disk me-1"></i> Save &amp; Update Money
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- =============================================================== -->
<!-- BATCH SETTLEMENT & MULTI-CREDIT SINGLE RECEIPT MODAL            -->
<!-- =============================================================== -->
<div class="modal fade" id="batchPaymentModal" tabindex="-1" aria-labelledby="batchPaymentModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable" style="max-height: 94vh;">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden d-flex flex-column" style="max-height: 92vh;">
            <form action="{{ route('finance.credit-store.batch-payment') }}" method="POST" enctype="multipart/form-data" id="batchPaymentForm" class="d-flex flex-column h-100 overflow-hidden m-0">
                @csrf
                <!-- Sticky Header -->
                <div class="modal-header py-3 px-4 flex-shrink-0 text-white" id="batchModalHeader" style="background: linear-gradient(135deg, #059669 0%, #047857 100%); transition: all 0.3s ease;">
                    <div class="d-flex align-items-center gap-2">
                        <div class="rounded-circle bg-white text-success p-2 d-flex align-items-center justify-content-center shadow-sm" style="width: 40px; height: 40px;" id="batchModalIconBox">
                            <i class="fa-solid fa-receipt fs-5" id="batchModalIcon"></i>
                        </div>
                        <div>
                            <h5 class="modal-title fw-bold mb-0" id="batchPaymentModalLabel">Batch Credit Settlement (Upload 1 Receipt)</h5>
                            <small class="text-white-50" id="batchModalSubtitle">Settle multiple selected credit purchases using one payment receipt &amp; voucher proof</small>
                        </div>
                    </div>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <!-- Fully Scrollable Modal Body -->
                <div class="modal-body p-4 flex-grow-1" style="overflow-y: auto; max-height: calc(92vh - 140px);">
                    <!-- Instruction alert -->
                    <div class="alert alert-light border shadow-sm rounded-3 mb-4 d-flex align-items-start gap-3" id="batchInstructionAlert">
                        <i class="fa-solid fa-circle-info text-info fs-4 mt-1"></i>
                        <div class="small">
                            <strong>How this works:</strong> The payment will be automatically distributed to each selected purchase request. Separate double-entry journal entries and company expense records will be generated for each credit item, fully updating their remaining balances.
                        </div>
                    </div>

                    <!-- Step 1: Selected PRs & Payment Allocation -->
                    <div class="card border shadow-sm rounded-3 mb-4">
                        <div class="card-header bg-light py-2 px-3 d-flex align-items-center justify-content-between">
                            <span class="fw-bold text-dark small text-uppercase">
                                <i class="fas fa-list-check text-primary me-1"></i> 1. Selected Credits &amp; Payment Amounts
                            </span>
                            <span class="badge bg-primary rounded-pill" id="modalSelectedCountBadge">0 Selected</span>
                        </div>
                        <div class="table-responsive" style="max-height: 220px; overflow-y: auto;">
                            <table class="table table-hover table-sm align-middle mb-0" style="font-size: 0.85rem;">
                                <thead class="bg-light text-muted small text-uppercase sticky-top" style="z-index: 1;">
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
                                <tfoot class="bg-light border-top sticky-bottom" style="z-index: 1;">
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

                    <!-- Step 2: Disbursing Account & Receipt Option -->
                    <div class="card border shadow-sm rounded-3">
                        <div class="card-header bg-light py-2 px-3 d-flex align-items-center justify-content-between">
                            <span class="fw-bold text-dark small text-uppercase">
                                <i class="fas fa-money-check-dollar text-success me-1"></i> 2. Disbursing Account &amp; Receipt Option
                            </span>
                            <span id="modalReceiptModeBadge" class="badge bg-success-subtle text-success border border-success px-2 py-1 small">
                                With Receipt Proof
                            </span>
                        </div>
                        <div class="card-body p-3">
                            <div class="row g-3">
                                <!-- Unified Paying Account Selector -->
                                <div class="col-12 col-md-6">
                                    <label class="form-label small fw-bold text-uppercase text-dark">
                                        Select Paying / Disbursing Account <span class="text-danger">*</span>
                                    </label>
                                    <select name="account_source" id="paymentAccountSelect" class="form-select form-select-sm" required onchange="onAccountSelectChange(this)">
                                        <option value="">-- Choose Bank or Cash Account * --</option>
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
                                                        [{{ $ca->code }}] {{ $ca->name }} (Bal: ETB {{ number_format($ca->current_balance, 2) }})
                                                    </option>
                                                @endforeach
                                            </optgroup>
                                        @endif
                                        @if(isset($coaAccounts))
                                            <optgroup label="📂 Other Asset / Funding Accounts">
                                                @foreach($coaAccounts->whereNotIn('id', ($cashAccounts ?? collect())->pluck('id')) as $ca)
                                                    <option value="coa:{{ $ca->id }}" data-method="other" data-balance="{{ (float)$ca->current_balance }}">
                                                        [{{ $ca->code }}] {{ $ca->name }} (Bal: ETB {{ number_format($ca->current_balance, 2) }})
                                                    </option>
                                                @endforeach
                                            </optgroup>
                                        @endif
                                    </select>
                                    <div class="small text-muted mt-1" id="accountBalanceDisplay">
                                        <i class="fa-solid fa-coins text-warning me-1"></i>Funds will be disbursed from this selected account.
                                    </div>
                                </div>

                                <!-- Payment Method -->
                                <div class="col-12 col-md-3">
                                    <label class="form-label small fw-bold text-uppercase text-dark">
                                        Payment Method <span class="text-danger">*</span>
                                    </label>
                                    <select name="payment_method" id="paymentMethodSelect" class="form-select form-select-sm" required>
                                        <option value="bank_transfer" selected>Bank Transfer (Disbursement)</option>
                                        <option value="cheque">Cheque</option>
                                        <option value="cash">Cash Payment</option>
                                        <option value="other">Other Method</option>
                                    </select>
                                </div>

                                <!-- Payment Date -->
                                <div class="col-12 col-md-3">
                                    <label class="form-label small fw-bold text-uppercase text-dark">
                                        Payment Date <span class="text-danger">*</span>
                                    </label>
                                    <input type="date" name="payment_date" class="form-control form-control-sm" value="{{ date('Y-m-d') }}" required>
                                </div>

                                <!-- Receipt Option Switch Card (Pay With vs Pay Without Receipt) -->
                                <div class="col-12">
                                    <div class="card border rounded-3 overflow-hidden shadow-xs bg-light">
                                        <div class="card-header bg-white py-2 px-3 d-flex flex-wrap align-items-center justify-content-between gap-2 border-bottom">
                                            <span class="small fw-bold text-dark text-uppercase">
                                                <i class="fa-solid fa-receipt text-primary me-1"></i> Receipt / Voucher Proof
                                            </span>
                                            <div class="form-check form-switch mb-0">
                                                <input class="form-check-input" type="checkbox" role="switch" id="noReceiptToggle" name="no_receipt" value="1" onchange="toggleReceiptMode(this.checked)">
                                                <label class="form-check-label small fw-bold text-dark" for="noReceiptToggle">
                                                    <span class="badge bg-warning text-dark border border-warning px-2.5 py-1">
                                                        <i class="fa-solid fa-ban me-1"></i> Pay Without Receipt
                                                    </span>
                                                </label>
                                            </div>
                                        </div>
                                        <div class="card-body p-3">
                                            <!-- With Receipt View (Default) -->
                                            <div id="withReceiptSection">
                                                <!-- Receipt OCR Studio Interactive Dropzone -->
                                                <div id="batchReceiptDropZone" class="border border-2 border-dashed rounded-3 p-3 text-center bg-white shadow-2xs mb-2 transition-all cursor-pointer"
                                                     style="border-color:#10b981 !important; min-height: 110px; display:flex; flex-direction:column; justify-content:center; align-items:center;"
                                                     onclick="if(event.target.tagName !== 'BUTTON' && event.target.tagName !== 'INPUT') document.getElementById('receiptFileInput').click()">
                                                    <input type="file" name="receipt_file" id="receiptFileInput" class="d-none" accept=".pdf,.jpg,.jpeg,.png,.webp">
                                                    <div class="rounded-circle bg-success bg-opacity-10 text-success p-2 d-inline-flex mb-1">
                                                        <i class="fa-solid fa-cloud-arrow-up fa-lg"></i>
                                                    </div>
                                                    <div class="fw-bold text-dark small mb-0">Drag &amp; Drop Batch Payment Receipt or Click to Browse</div>
                                                    <div class="text-muted" style="font-size:0.72rem;">Accepts JPG, PNG, WEBP, PDF up to 10MB (Single shared receipt applied to all selected credits)</div>
                                                    <div class="mt-2 d-flex gap-2">
                                                        <button type="button" class="btn btn-xs btn-success fw-semibold px-2.5 py-1 shadow-2xs" style="font-size:0.72rem;" onclick="event.stopPropagation(); document.getElementById('receiptFileInput').click()">
                                                            <i class="fa-solid fa-folder-open me-1"></i> Browse Receipt
                                                        </button>
                                                        <span class="badge bg-primary bg-opacity-10 text-primary border d-flex align-items-center" style="font-size:0.68rem;">
                                                            <i class="fa-solid fa-wand-magic-sparkles me-1"></i> AI OCR Studio
                                                        </span>
                                                    </div>
                                                </div>

                                                <div id="creditBatchOcrFeedbackArea"></div>
                                                <input type="hidden" name="purchaser_tin" id="creditBatchPurchaserTin" value="0038480010">
                                                <input type="hidden" name="seller_tin" id="creditBatchSellerTin">
                                                <input type="hidden" name="supplier_tin" id="creditBatchSupplierTin">
                                                <input type="hidden" name="receipt_no" id="creditBatchReceiptNo">
                                                <input type="hidden" name="fs_no" id="creditBatchFsNo">
                                                <input type="hidden" name="vendor_name" id="creditBatchVendorName">
                                                <input type="hidden" name="mrc" id="creditBatchMrc">
                                                <input type="hidden" name="mrc_no" id="creditBatchMrcNo">
                                                <input type="hidden" name="receipt_date" id="creditBatchReceiptDate">
                                                <input type="hidden" name="encoding_date" id="creditBatchEncodingDate" value="{{ date('Y-m-d') }}">
                                                <input type="hidden" name="purc_type" id="creditBatchPurcType" value="6">
                                                <input type="hidden" name="purchase_type" id="creditBatchPurchaseType" value="6">
                                                <input type="hidden" name="encoded_by" id="creditBatchEncodedBy" value="{{ strtolower(preg_replace('/[^a-zA-Z0-9_]/', '', Auth::user()->name ?? 'wechacha')) }}">
                                                <input type="hidden" name="subtotal" id="creditBatchSubtotal">
                                                <input type="hidden" name="vat_amount" id="creditBatchVatAmount">
                                                <input type="hidden" name="total_amount" id="creditBatchTotalAmount">
                                                <input type="hidden" name="vat_included" id="creditBatchVatIncluded" value="0">
                                                <input type="hidden" name="is_verified" id="creditBatchIsVerified" value="1">
                                                <input type="hidden" name="ocr_items_json" id="creditBatchOcrItemsJson">
                                                <div class="form-text small text-muted" style="font-size:0.72rem;">Attach bank slip, cheque scan, or signed vendor voucher. The Multi-Engine OCR Studio verifies ERCA fiscal rules and automatically routes VAT vs TAS declaration.</div>
                                            </div>

                                            <!-- Without Receipt View -->
                                            <div id="withoutReceiptSection" class="p-3 rounded-2 border border-warning bg-warning bg-opacity-10 d-none">
                                                <div class="d-flex align-items-center gap-2 text-dark small mb-1">
                                                    <i class="fa-solid fa-circle-check text-warning fa-lg"></i>
                                                    <strong>Pay Without Receipt Option Active:</strong>
                                                </div>
                                                <p class="small text-dark mb-2">
                                                    You are choosing to pay and liquidate these supplier credits directly without uploading a receipt document. Funds will be deducted from your selected account and logged into Company Expenses immediately.
                                                </p>
                                                <div class="row g-2">
                                                    <div class="col-12">
                                                        <input type="text" name="no_receipt_reason" class="form-control form-control-sm bg-white" placeholder="Optional explanation (e.g., Direct bank debit, Trust credit settlement, Cash payout, Verbal approval)">
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
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

                <!-- Pinned Sticky Footer Always in View -->
                <div class="modal-footer bg-white border-top py-3 px-4 d-flex align-items-center justify-content-between flex-shrink-0 shadow-sm" style="z-index: 10;">
                    <button type="button" class="btn btn-outline-secondary btn-sm px-3 fw-semibold" data-bs-dismiss="modal">
                        <i class="fas fa-times me-1"></i> Cancel
                    </button>
                    <button type="submit" class="btn btn-success fw-bold shadow-sm px-4 py-2" id="submitBatchPaymentBtn">
                        <i class="fas fa-check-circle me-1"></i> <span id="modalSubmitBtnText">Record Batch Payment &amp; Liquidate</span> (<span id="modalBtnTotal">0.00 ETB</span>)
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    // ===============================================================
    // MATERIAL LIST & CREDIT VALUE / MONEY SYNC MODAL
    // ===============================================================
    let currentMmRowData = null;

    function openMaterialMoneyModal(ledgerId, targetTab = 'both') {
        const row = document.getElementById('ledger-row-' + ledgerId);
        if (!row) {
            console.error('Ledger row not found for ID: ' + ledgerId);
            return;
        }

        const id = row.dataset.id;
        const pr = row.dataset.pr;
        const project = row.dataset.project;
        const supplier = row.dataset.supplier;
        const credit = parseFloat(row.dataset.credit) || 0;
        const paid = parseFloat(row.dataset.paid) || 0;
        const calcSum = parseFloat(row.dataset.calcSum) || 0;
        const calcVat = parseFloat(row.dataset.calcVat) || 0;
        const gmAmount = parseFloat(row.dataset.gmAmount) || 0;
        const syncUrl = row.dataset.syncUrl;
        const coa = row.dataset.coa;
        let items = [];

        try {
            items = JSON.parse(row.dataset.items || '[]');
        } catch (e) {
            console.error('Failed to parse items JSON', e);
            items = [];
        }

        currentMmRowData = {
            id, pr, project, supplier, credit, paid, calcSum, calcVat, gmAmount, syncUrl, coa, items
        };

        // Populate Form Action & Header
        const form = document.getElementById('materialMoneyForm');
        if (form) {
            form.action = syncUrl;
        }

        document.getElementById('mmPrBadge').textContent = 'PR #' + pr;
        document.getElementById('mmSubTitle').innerHTML = `Project: <strong>${escapeHtml(project)}</strong> &bull; Supplier: <strong>${escapeHtml(supplier)}</strong>`;
        document.getElementById('mmHeaderCurrentCredit').textContent = credit.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' ETB';

        // GM Option in Tab 2
        const gmWrapper = document.getElementById('mmGmOptionWrapper');
        const gmRadioBadge = document.getElementById('mmRadioGmBadge');
        if (gmAmount > 0) {
            gmWrapper.classList.remove('d-none');
            gmRadioBadge.textContent = gmAmount.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' ETB';
        } else {
            gmWrapper.classList.add('d-none');
        }

        // Render Table & Recalculate
        renderMmItemsTable(items);
        recalcMmTotals();

        // Switch to appropriate tab
        if (targetTab === 'money' || (targetTab === 'both' && items.length === 0)) {
            const moneyTabBtn = document.getElementById('tab-money-btn');
            if (moneyTabBtn) {
                bootstrap.Tab.getOrCreateInstance(moneyTabBtn).show();
            }
        } else {
            const materialsTabBtn = document.getElementById('tab-materials-btn');
            if (materialsTabBtn) {
                bootstrap.Tab.getOrCreateInstance(materialsTabBtn).show();
            }
        }

        // Reset radio to items_sum (or custom if no items)
        if (items.length > 0) {
            const radioSum = document.getElementById('mmSyncModeItemsSum');
            if (radioSum) {
                radioSum.checked = true;
                toggleMmCustomAmount(false);
            }
        } else {
            const radioCustom = document.getElementById('mmSyncModeCustom');
            if (radioCustom) {
                radioCustom.checked = true;
                toggleMmCustomAmount(true);
                const customInput = document.getElementById('mmCustomAmountInput');
                if (customInput) customInput.value = credit > 0 ? credit.toFixed(2) : '';
            }
        }

        const modalEl = document.getElementById('materialMoneyModal');
        const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
        modal.show();
    }

    function renderMmItemsTable(items) {
        const tbody = document.getElementById('mmItemsTableBody');
        tbody.innerHTML = '';

        const countBadge = document.getElementById('mmItemCountBadge');
        const summaryBadge = document.getElementById('mmItemsSummaryBadge');

        countBadge.textContent = items.length;
        summaryBadge.textContent = `${items.length} Material Item${items.length === 1 ? '' : 's'}`;

        if (!items || items.length === 0) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="6" class="text-center py-4 text-muted">
                        <i class="fa-solid fa-box-open fa-2x mb-2 text-secondary opacity-50 d-block"></i>
                        <p class="small mb-1 fw-bold text-dark">No Procured Material Items Found</p>
                        <span class="small text-muted">This PR was approved without specific item line records or as a lump-sum. You can update the purchase money directly on the <strong>Update Money</strong> tab.</span>
                    </td>
                </tr>
            `;
            return;
        }

        items.forEach((item, index) => {
            const tr = document.createElement('tr');
            tr.className = 'align-middle';
            tr.innerHTML = `
                <td class="ps-3 text-muted small">${index + 1}</td>
                <td>
                    <div class="fw-bold text-dark">${escapeHtml(item.name || 'Material Item')}</div>
                    ${item.code ? `<div class="font-monospace text-muted small" style="font-size:0.75rem;">${escapeHtml(item.code)}</div>` : ''}
                </td>
                <td>
                    <div class="input-group input-group-sm" style="max-width: 130px;">
                        <input type="number" step="0.001" min="0" 
                            name="items[${item.id}][quantity]" 
                            class="form-control form-control-sm font-monospace text-end mm-item-qty" 
                            value="${item.quantity}" 
                            data-item-id="${item.id}" 
                            oninput="recalcMmTotals()">
                    </div>
                </td>
                <td class="small text-muted fw-semibold">${escapeHtml(item.unit || 'pcs')}</td>
                <td>
                    <div class="input-group input-group-sm" style="max-width: 160px;">
                        <span class="input-group-text py-0 px-1 text-muted" style="font-size:0.72rem;">ETB</span>
                        <input type="number" step="0.01" min="0" 
                            name="items[${item.id}][unit_cost]" 
                            class="form-control form-control-sm font-monospace text-end mm-item-cost" 
                            value="${(parseFloat(item.unit_cost) || 0).toFixed(2)}" 
                            data-item-id="${item.id}" 
                            oninput="recalcMmTotals()">
                    </div>
                </td>
                <td class="text-end pe-3">
                    <span class="fw-bold font-monospace text-dark mm-item-total" id="mm-total-${item.id}">
                        ${(parseFloat(item.line_total) || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}
                    </span>
                    <span class="small text-muted">ETB</span>
                </td>
            `;
            tbody.appendChild(tr);
        });
    }

    function recalcMmTotals() {
        let subtotal = 0.0;
        const qtyInputs = document.querySelectorAll('.mm-item-qty');

        qtyInputs.forEach(qtyInput => {
            const itemId = qtyInput.dataset.itemId;
            const costInput = document.querySelector(`.mm-item-cost[data-item-id="${itemId}"]`);
            const totalEl = document.getElementById(`mm-total-${itemId}`);

            const qty = parseFloat(qtyInput.value) || 0;
            const cost = parseFloat(costInput ? costInput.value : 0) || 0;
            const lineTotal = Math.round(qty * cost * 100) / 100;

            if (totalEl) {
                totalEl.textContent = lineTotal.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            }
            subtotal += lineTotal;
        });

        subtotal = Math.round(subtotal * 100) / 100;
        const vatTotal = Math.round(subtotal * 1.15 * 100) / 100;
        const vatOnly = Math.round((vatTotal - subtotal) * 100) / 100;

        const subtotalFormatted = subtotal.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' ETB';
        const vatTotalFormatted = vatTotal.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' ETB';
        const vatOnlyFormatted = '+' + vatOnly.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' ETB';

        const tfSubtotal = document.getElementById('mmTableFootSubtotal');
        const tfVat = document.getElementById('mmTableFootVat');
        const tfTotalVat = document.getElementById('mmTableFootTotalVat');

        if (tfSubtotal) tfSubtotal.textContent = subtotalFormatted;
        if (tfVat) tfVat.textContent = vatOnlyFormatted;
        if (tfTotalVat) tfTotalVat.textContent = vatTotalFormatted;

        const hBase = document.getElementById('mmHeaderBaseSum');
        const hVat = document.getElementById('mmHeaderVatSum');
        if (hBase) hBase.textContent = subtotalFormatted;
        if (hVat) hVat.textContent = vatTotalFormatted;

        const rBase = document.getElementById('mmRadioBaseSumBadge');
        const rVat = document.getElementById('mmRadioVatSumBadge');
        if (rBase) rBase.textContent = subtotalFormatted;
        if (rVat) rVat.textContent = vatTotalFormatted;

        const fSummary = document.getElementById('mmFooterSummaryHint');
        if (fSummary) {
            fSummary.innerHTML = `Items Subtotal: <strong class="text-primary font-monospace">${subtotalFormatted}</strong> | With 15% VAT: <strong class="text-success font-monospace">${vatTotalFormatted}</strong>`;
        }
    }

    function switchToMoneyTab(targetSyncMode = null) {
        const moneyTabBtn = document.getElementById('tab-money-btn');
        if (moneyTabBtn) {
            bootstrap.Tab.getOrCreateInstance(moneyTabBtn).show();
        }

        if (targetSyncMode === 'items_sum') {
            const r = document.getElementById('mmSyncModeItemsSum');
            if (r) { r.checked = true; toggleMmCustomAmount(false); }
        } else if (targetSyncMode === 'items_sum_with_vat') {
            const r = document.getElementById('mmSyncModeItemsVat');
            if (r) { r.checked = true; toggleMmCustomAmount(false); }
        } else if (targetSyncMode === 'custom') {
            const r = document.getElementById('mmSyncModeCustom');
            if (r) { r.checked = true; toggleMmCustomAmount(true); }
        }
    }

    function toggleMmCustomAmount(show) {
        const wrapper = document.getElementById('mmCustomAmountWrapper');
        const input = document.getElementById('mmCustomAmountInput');
        if (!wrapper || !input) return;

        if (show) {
            wrapper.classList.remove('d-none');
            input.setAttribute('required', 'required');
            if (!input.value && currentMmRowData) {
                input.value = currentMmRowData.credit > 0 ? currentMmRowData.credit.toFixed(2) : '';
            }
            input.focus();
        } else {
            wrapper.classList.add('d-none');
            input.removeAttribute('required');
        }
    }

    function escapeHtml(text) {
        if (!text) return '';
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return text.toString().replace(/[&<>"']/g, function(m) { return map[m]; });
    }

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
        const headerNoRecBtn = document.getElementById('headerBatchNoReceiptBtn');
        const headerNoRecCount = document.getElementById('headerBatchCountNoRec');
        const masterCb = document.getElementById('masterCheckbox');

        // Update counts and amounts
        if (countBadge) countBadge.textContent = count;
        if (headerCount) headerCount.textContent = count;
        if (headerNoRecCount) headerNoRecCount.textContent = count;
        if (totalRemainingEl) totalRemainingEl.textContent = totalRemaining.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' ETB';

        // Toggle UI visibility
        if (count > 0) {
            batchBar.classList.remove('d-none');
            batchBar.classList.add('d-flex');
            if (headerBtn) headerBtn.removeAttribute('disabled');
            if (headerNoRecBtn) headerNoRecBtn.removeAttribute('disabled');
        } else {
            batchBar.classList.add('d-none');
            batchBar.classList.remove('d-flex');
            if (headerBtn) headerBtn.setAttribute('disabled', 'disabled');
            if (headerNoRecBtn) headerNoRecBtn.setAttribute('disabled', 'disabled');
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

    function paySingleCredit(ledgerId, isNoReceipt = false) {
        clearAllSelections();
        const cb = document.querySelector(`.credit-checkbox[value="${ledgerId}"]`);
        if (cb) {
            cb.checked = true;
            updateSelectionState();
            openBatchPaymentModal(isNoReceipt);
        }
    }

    function toggleReceiptMode(isNoReceipt) {
        const withSection = document.getElementById('withReceiptSection');
        const withoutSection = document.getElementById('withoutReceiptSection');
        const modeBadge = document.getElementById('modalReceiptModeBadge');
        const submitBtnText = document.getElementById('modalSubmitBtnText');
        const submitBtn = document.getElementById('submitBatchPaymentBtn');
        const fileInput = document.getElementById('receiptFileInput');
        const header = document.getElementById('batchModalHeader');
        const modalTitle = document.getElementById('batchPaymentModalLabel');
        const modalSub = document.getElementById('batchModalSubtitle');
        const icon = document.getElementById('batchModalIcon');
        const iconBox = document.getElementById('batchModalIconBox');

        if (isNoReceipt) {
            if (withSection) withSection.classList.add('d-none');
            if (withoutSection) withoutSection.classList.remove('d-none');
            if (modeBadge) {
                modeBadge.className = 'badge bg-warning text-dark border border-warning px-2.5 py-1 small fw-bold shadow-xs';
                modeBadge.innerHTML = '<i class="fa-solid fa-ban me-1"></i> Pay Without Receipt';
            }
            if (submitBtnText) submitBtnText.textContent = 'Pay Without Receipt & Liquidate';
            if (submitBtn) {
                submitBtn.classList.remove('btn-success');
                submitBtn.classList.add('btn-warning', 'text-dark');
                submitBtn.innerHTML = '<i class="fa-solid fa-ban me-1"></i> <span id="modalSubmitBtnText">Pay Without Receipt & Liquidate</span> (<span id="modalBtnTotal">' + (document.getElementById('modalBtnTotal')?.textContent || '0.00 ETB') + '</span>)';
            }
            if (fileInput) fileInput.value = '';
            if (header) header.style.background = 'linear-gradient(135deg, #1e293b 0%, #334155 100%)';
            if (modalTitle) modalTitle.textContent = 'Batch Credit Settlement (Pay Without Receipt)';
            if (modalSub) modalSub.textContent = 'Directly liquidate supplier liabilities from chosen account without receipt slip';
            if (icon) icon.className = 'fa-solid fa-ban text-warning fs-5';
            if (iconBox) iconBox.className = 'rounded-circle bg-dark text-warning p-2 d-flex align-items-center justify-content-center shadow-sm border border-warning';
        } else {
            if (withSection) withSection.classList.remove('d-none');
            if (withoutSection) withoutSection.classList.add('d-none');
            if (modeBadge) {
                modeBadge.className = 'badge bg-success-subtle text-success border border-success px-2.5 py-1 small fw-bold';
                modeBadge.innerHTML = '<i class="fas fa-file-invoice me-1"></i> With Receipt Proof';
            }
            if (submitBtnText) submitBtnText.textContent = 'Record Batch Payment & Liquidate';
            if (submitBtn) {
                submitBtn.classList.remove('btn-warning', 'text-dark');
                submitBtn.classList.add('btn-success');
                submitBtn.innerHTML = '<i class="fas fa-check-circle me-1"></i> <span id="modalSubmitBtnText">Record Batch Payment & Liquidate</span> (<span id="modalBtnTotal">' + (document.getElementById('modalBtnTotal')?.textContent || '0.00 ETB') + '</span>)';
            }
            if (header) header.style.background = 'linear-gradient(135deg, #059669 0%, #047857 100%)';
            if (modalTitle) modalTitle.textContent = 'Batch Credit Settlement (Upload 1 Receipt)';
            if (modalSub) modalSub.textContent = 'Settle multiple selected credit purchases using one payment receipt & voucher proof';
            if (icon) icon.className = 'fa-solid fa-receipt text-success fs-5';
            if (iconBox) iconBox.className = 'rounded-circle bg-white text-success p-2 d-flex align-items-center justify-content-center shadow-sm';
        }
    }

    function onAccountSelectChange(selectEl) {
        const selectedOpt = selectEl.options[selectEl.selectedIndex];
        const methodSelect = document.getElementById('paymentMethodSelect');
        const balDisplay = document.getElementById('accountBalanceDisplay');

        if (selectedOpt && selectedOpt.value) {
            const method = selectedOpt.getAttribute('data-method') || 'bank_transfer';
            const bal = parseFloat(selectedOpt.getAttribute('data-balance')) || 0;

            if (methodSelect) {
                methodSelect.value = method;
            }
            if (balDisplay) {
                balDisplay.innerHTML = `<i class="fa-solid fa-wallet text-success me-1"></i> Selected Account Balance: <strong class="text-dark font-monospace">ETB ${bal.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</strong>`;
            }
        } else {
            if (balDisplay) {
                balDisplay.innerHTML = `<i class="fa-solid fa-coins text-warning me-1"></i> Funds will be disbursed from this selected account.`;
            }
        }
    }

    function openBatchPaymentModal(isNoReceipt = false) {
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

        // Configure receipt mode
        const toggle = document.getElementById('noReceiptToggle');
        if (toggle) {
            toggle.checked = isNoReceipt;
            toggleReceiptMode(isNoReceipt);
        }

        // Trigger balance update on default account
        const accSelect = document.getElementById('paymentAccountSelect');
        if (accSelect) {
            onAccountSelectChange(accSelect);
        }

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

                const accSelect = document.getElementById('paymentAccountSelect');
                if (!accSelect || !accSelect.value) {
                    e.preventDefault();
                    alert('Please select the paying / disbursing bank or cash account.');
                    if (accSelect) accSelect.focus();
                    return false;
                }
            });
        }

        // Initialize AI Receipt OCR Scanner for Batch Settlement
        if (window.ReceiptOcrScanner) {
            ReceiptOcrScanner.init({
                fileInput: '#receiptFileInput',
                form: '#batchPaymentModal form',
                submitBtn: '#submitBatchPaymentBtn',
                statusContainer: '#creditBatchOcrFeedbackArea',
                studioMode: true,
                purchaserTin: '0038480010',
                encodedBy: '{{ strtolower(preg_replace("/[^a-zA-Z0-9_]/", "", Auth::user()->name ?? "wechacha")) }}',
                fields: {
                    purchaser_tin: '#creditBatchPurchaserTin',
                    seller_tin: '#creditBatchSellerTin',
                    supplier_tin: '#creditBatchSupplierTin',
                    receipt_no: '#creditBatchReceiptNo',
                    fs_no: '#creditBatchFsNo',
                    receipt_date: 'input[name="payment_date"]',
                    encoding_date: '#creditBatchEncodingDate',
                    mrc: '#creditBatchMrc',
                    purc_type: '#creditBatchPurcType',
                    encoded_by: '#creditBatchEncodedBy',
                    notes: 'input[name="notes"]'
                },
                onScanComplete: function(scanData) {
                    const refInput = document.querySelector('#batchPaymentModal input[name="reference_no"]');
                    if (refInput && !refInput.value && scanData.data && scanData.data.fs_no) {
                        refInput.value = 'FS-' + scanData.data.fs_no;
                    }
                }
            });
        }
    });
</script>
<script src="{{ asset('js/receipt-ocr-scanner.js') }}"></script>

<style>
    /* Unlocked Smooth Modal & Table Scrollbars */
    #batchPaymentModal .modal-body::-webkit-scrollbar,
    #batchPaymentModal .table-responsive::-webkit-scrollbar,
    #materialMoneyModal .modal-body::-webkit-scrollbar,
    #materialMoneyModal .table-responsive::-webkit-scrollbar {
        width: 7px;
        height: 7px;
    }
    #batchPaymentModal .modal-body::-webkit-scrollbar-track,
    #batchPaymentModal .table-responsive::-webkit-scrollbar-track,
    #materialMoneyModal .modal-body::-webkit-scrollbar-track,
    #materialMoneyModal .table-responsive::-webkit-scrollbar-track {
        background: #f1f5f9;
        border-radius: 4px;
    }
    #batchPaymentModal .modal-body::-webkit-scrollbar-thumb,
    #batchPaymentModal .table-responsive::-webkit-scrollbar-thumb,
    #materialMoneyModal .modal-body::-webkit-scrollbar-thumb,
    #materialMoneyModal .table-responsive::-webkit-scrollbar-thumb {
        background: #94a3b8;
        border-radius: 4px;
    }
    #batchPaymentModal .modal-body::-webkit-scrollbar-thumb:hover,
    #batchPaymentModal .table-responsive::-webkit-scrollbar-thumb:hover,
    #materialMoneyModal .modal-body::-webkit-scrollbar-thumb:hover,
    #materialMoneyModal .table-responsive::-webkit-scrollbar-thumb:hover {
        background: #64748b;
    }

    #mmTabList .nav-link.active {
        color: #2563eb !important;
        border-bottom: 3px solid #2563eb !important;
        background-color: transparent !important;
    }
    #mmTabList .nav-link {
        border: none;
        border-bottom: 3px solid transparent;
        color: #64748b;
        transition: all 0.2s ease;
    }
    #mmTabList .nav-link:hover {
        color: #1e293b;
    }
</style>
@endsection