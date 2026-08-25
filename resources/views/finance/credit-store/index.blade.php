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

    <!-- Credit Ledgers Table -->
    <div class="card border-0 shadow-sm rounded-3 overflow-hidden">
        <div class="card-header bg-white py-3 px-3 border-bottom d-flex align-items-center justify-content-between">
            <h6 class="mb-0 fw-bold text-dark"><i class="fas fa-book me-2 text-info"></i> Credit Purchases Ledger</h6>
            <span class="badge bg-light text-dark border">{{ $ledgers->total() }} records found</span>
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" style="font-size: 0.875rem;">
                <thead class="bg-light text-muted small text-uppercase">
                    <tr>
                        <th class="ps-3">PR Number</th>
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
                        <tr>
                            <td class="ps-3 fw-bold">
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
                                        {{ $ledger->payments->count() }} installment(s)
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
                            <td colspan="9" class="text-center py-5 text-muted">
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
@endsection
