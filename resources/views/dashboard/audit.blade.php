@extends('layouts.app')
@section('title', 'Audit & Compliance Dashboard')

@section('content')
<div class="container-fluid px-4 py-3">

    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <div class="d-flex align-items-center gap-2">
                <div class="p-2.5 rounded-3 text-white shadow-sm" style="background: linear-gradient(135deg, #0284c7, #0369a1);">
                    <i class="fa-solid fa-shield-halved fa-lg"></i>
                </div>
                <div>
                    <h1 class="h3 mb-0 fw-bold" style="color:var(--brand-800)">Audit &amp; Compliance Dashboard</h1>
                    <p class="text-muted small mb-0">Real-time oversight of petty cash replenishment cycles, money movements, expense vouchers, and system audit trails</p>
                </div>
            </div>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('expense-requests.index') }}" class="btn btn-outline-success btn-sm rounded-pill px-3 shadow-xs fw-semibold">
                <i class="fa-solid fa-hand-holding-dollar me-1"></i> Ask Money
            </a>
            <a href="{{ route('leave-requests.create') }}" class="btn btn-outline-info btn-sm rounded-pill px-3 shadow-xs fw-semibold">
                <i class="fa-solid fa-calendar-plus me-1"></i> Ask Leave
            </a>
            <a href="{{ route('finance.replenishments.index') }}" class="btn btn-warning text-dark btn-sm rounded-pill px-3 shadow-xs fw-bold">
                <i class="fa-solid fa-hand-holding-dollar me-1"></i> Petty Cash Approvals
            </a>
            <a href="{{ route('admin.activity-logs') }}" class="btn btn-outline-primary btn-sm rounded-pill px-3 shadow-xs">
                <i class="fa-solid fa-list-ol me-1"></i> Full Activity Trail
            </a>
        </div>
    </div>

    <!-- Quick Employee Self-Service & Navigation Bar -->
    <div class="card border-0 shadow-sm rounded-4 mb-4 bg-white overflow-hidden">
        <div class="card-body p-3">
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                <div class="d-flex align-items-center gap-2">
                    <span class="badge bg-primary-subtle text-primary fw-bold px-2.5 py-1.5 rounded-3">
                        <i class="fa-solid fa-user-check me-1"></i> Employee &amp; Audit Quick Actions
                    </span>
                    <small class="text-muted">Direct access to staff requests and financial audits</small>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    <a href="{{ route('expense-requests.index') }}" class="btn btn-light border btn-sm rounded-pill px-3 text-dark fw-semibold">
                        <i class="fa-solid fa-wallet text-success me-1"></i> Ask Money (Expenses)
                    </a>
                    <a href="{{ route('leave-requests.create') }}" class="btn btn-light border btn-sm rounded-pill px-3 text-dark fw-semibold">
                        <i class="fa-solid fa-calendar-check text-info me-1"></i> Request Leave
                    </a>
                    <a href="{{ route('letters.index') }}" class="btn btn-light border btn-sm rounded-pill px-3 text-dark fw-semibold">
                        <i class="fa-solid fa-envelope text-primary me-1"></i> Letters &amp; Correspondence
                    </a>
                    <a href="{{ route('expenses.index') }}" class="btn btn-light border btn-sm rounded-pill px-3 text-dark fw-semibold">
                        <i class="fa-solid fa-file-invoice text-danger me-1"></i> Expense Audit Hub
                    </a>
                    <a href="{{ route('coa.index') }}" class="btn btn-light border btn-sm rounded-pill px-3 text-dark fw-semibold">
                        <i class="fa-solid fa-sitemap text-secondary me-1"></i> COA Ledger
                    </a>
                </div>
            </div>
        </div>
    </div>


    <!-- KPI Summary Row -->
    <div class="row g-3 mb-4">
        <div class="col-xl-3 col-md-6">
            <div class="card border-0 shadow-sm rounded-4 h-100 p-3 bg-white" style="border-left: 5px solid #10b981 !important;">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <span class="text-xs fw-bold text-uppercase text-success" style="font-size:0.72rem; letter-spacing:0.5px;">Fulfilled Top-Ups (This Month)</span>
                        <h4 class="fw-bold text-dark mb-0 mt-1 font-monospace">ETB {{ number_format($kpi['fulfilled_replenishments_month'], 2) }}</h4>
                        <small class="text-success fw-semibold"><i class="fa-solid fa-circle-check me-1"></i>Disbursed &amp; Reconciled</small>
                    </div>
                    <div class="p-3 rounded-circle" style="background: rgba(16, 185, 129, 0.1);">
                        <i class="fa-solid fa-money-bill-transfer fa-2x text-success"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6">
            <div class="card border-0 shadow-sm rounded-4 h-100 p-3 bg-white" style="border-left: 5px solid #f59e0b !important;">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <span class="text-xs fw-bold text-uppercase text-warning" style="font-size:0.72rem; letter-spacing:0.5px;">Pending Replenishment Review</span>
                        <h4 class="fw-bold text-dark mb-0 mt-1 font-monospace">{{ $kpi['pending_replenishments_count'] }} <span class="fs-6 text-muted fw-normal">Requests</span></h4>
                        <small class="text-danger fw-semibold">ETB {{ number_format($kpi['pending_replenishments_amount'], 2) }}</small>
                    </div>
                    <div class="p-3 rounded-circle" style="background: rgba(245, 158, 11, 0.1);">
                        <i class="fa-solid fa-clock fa-2x text-warning"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6">
            <div class="card border-0 shadow-sm rounded-4 h-100 p-3 bg-white" style="border-left: 5px solid #6366f1 !important;">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <span class="text-xs fw-bold text-uppercase text-primary" style="font-size:0.72rem; letter-spacing:0.5px;">Reconciled Expense Vouchers</span>
                        <h4 class="fw-bold text-dark mb-0 mt-1 font-monospace">{{ number_format($kpi['total_reconciled_vouchers']) }} <span class="fs-6 text-muted fw-normal">Vouchers</span></h4>
                        <small class="text-primary fw-semibold">Total: ETB {{ number_format($kpi['total_reconciled_amount'], 2) }}</small>
                    </div>
                    <div class="p-3 rounded-circle" style="background: rgba(99, 102, 241, 0.1);">
                        <i class="fa-solid fa-receipt fa-2x text-primary"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6">
            <div class="card border-0 shadow-sm rounded-4 h-100 p-3 bg-white" style="border-left: 5px solid #0284c7 !important;">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <span class="text-xs fw-bold text-uppercase text-info" style="font-size:0.72rem; letter-spacing:0.5px;">Total Audit Logs Logged</span>
                        <h4 class="fw-bold text-dark mb-0 mt-1 font-monospace">{{ number_format($kpi['total_activity_logs']) }} <span class="fs-6 text-muted fw-normal">Events</span></h4>
                        <small class="text-muted">GL Journal Entries: {{ $kpi['total_journal_entries'] }}</small>
                    </div>
                    <div class="p-3 rounded-circle" style="background: rgba(2, 132, 199, 0.1);">
                        <i class="fa-solid fa-clipboard-check fa-2x text-info"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content Grid -->
    <div class="row g-4 mb-4">
        
        <!-- Petty Cash Replenishments & Money Movements -->
        <div class="col-xl-8">
            <div class="card border-0 shadow-sm rounded-4 h-100 overflow-hidden">
                <div class="card-header bg-white border-0 py-3 px-4 d-flex justify-content-between align-items-center">
                    <div>
                        <h5 class="fw-bold mb-0 text-dark">
                            <i class="fa-solid fa-hand-holding-dollar text-warning me-2"></i>Petty Cash Replenishment &amp; Fund Movements
                        </h5>
                        <small class="text-muted">Audit records of money requests, approvals, source account disbursements, and attached expenses</small>
                    </div>
                    <a href="{{ route('finance.replenishments.index') }}" class="btn btn-light btn-sm border rounded-pill px-3">
                        View All <i class="fa-solid fa-arrow-right ms-1"></i>
                    </a>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0 small">
                            <thead class="bg-light text-muted text-uppercase" style="font-size: 0.75rem;">
                                <tr>
                                    <th class="ps-4 py-2.5">Request # &amp; Date</th>
                                    <th class="py-2.5">Custodian</th>
                                    <th class="py-2.5">Petty Cash Account</th>
                                    <th class="py-2.5 text-end">Disbursed Top-Up</th>
                                    <th class="py-2.5">Source Account</th>
                                    <th class="py-2.5 text-center">Vouchers</th>
                                    <th class="py-2.5 text-center">Status</th>
                                    <th class="pe-4 py-2.5 text-end">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($recentReplenishments as $rep)
                                    <tr>
                                        <td class="ps-4 py-3">
                                            <span class="fw-bold text-dark font-monospace d-block">{{ $rep->request_no }}</span>
                                            <small class="text-muted">{{ $rep->created_at->format('M d, Y H:i') }}</small>
                                        </td>
                                        <td class="py-3">
                                            <strong>{{ $rep->requester->name ?? 'Staff' }}</strong>
                                        </td>
                                        <td class="py-3">
                                            <span class="d-block text-dark fw-semibold">[{{ $rep->chartOfAccount->code ?? 'N/A' }}] {{ $rep->chartOfAccount->name ?? 'Petty Cash' }}</span>
                                            <small class="text-muted font-monospace">Bal: ETB {{ number_format($rep->current_balance_at_request, 2) }}</small>
                                        </td>
                                        <td class="py-3 text-end">
                                            <strong class="text-primary font-monospace fs-6">
                                                ETB {{ number_format($rep->fulfilled_amount ?? $rep->requested_amount, 2) }}
                                            </strong>
                                        </td>
                                        <td class="py-3">
                                            @if($rep->sourceCoa)
                                                <span class="badge bg-light text-dark border">
                                                    [{{ $rep->sourceCoa->code }}] {{ $rep->sourceCoa->name }}
                                                </span>
                                            @else
                                                <span class="text-muted fst-italic">Pending selection</span>
                                            @endif
                                        </td>
                                        <td class="py-3 text-center">
                                            <span class="badge bg-danger bg-opacity-10 text-danger border border-danger-subtle font-monospace px-2 py-1">
                                                {{ $rep->items->count() }} (ETB {{ number_format($rep->total_expenses_amount, 2) }})
                                            </span>
                                        </td>
                                        <td class="py-3 text-center">
                                            @if($rep->status === 'pending')
                                                <span class="badge bg-warning text-dark rounded-pill px-2.5 py-1">Pending Approval</span>
                                            @elseif($rep->status === 'fulfilled')
                                                <span class="badge bg-success text-white rounded-pill px-2.5 py-1">Fulfilled</span>
                                            @elseif($rep->status === 'rejected')
                                                <span class="badge bg-danger text-white rounded-pill px-2.5 py-1">Rejected</span>
                                            @endif
                                        </td>
                                        <td class="pe-4 py-3 text-end">
                                            <a href="{{ route('finance.replenishments.index', ['search' => $rep->request_no]) }}" class="btn btn-outline-primary btn-sm rounded-pill px-3">
                                                <i class="fa-solid fa-eye me-1"></i> Audit Details
                                            </a>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="8" class="text-center py-4 text-muted">
                                            No replenishment cycles recorded yet.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Cash & Bank Account Balances Oversight -->
        <div class="col-xl-4">
            <div class="card border-0 shadow-sm rounded-4 h-100 overflow-hidden">
                <div class="card-header bg-white border-0 py-3 px-4">
                    <h5 class="fw-bold mb-0 text-dark">
                        <i class="fa-solid fa-building-columns text-primary me-2"></i>Cash &amp; Bank Liquidity Audit
                    </h5>
                    <small class="text-muted">Active asset accounts monitored for imprest funding</small>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive" style="max-height: 380px; overflow-y: auto;">
                        <table class="table table-hover align-middle mb-0 small">
                            <thead class="bg-light text-muted text-uppercase" style="font-size: 0.75rem;">
                                <tr>
                                    <th class="ps-4 py-2.5">Account</th>
                                    <th class="py-2.5">Custodian</th>
                                    <th class="pe-4 py-2.5 text-end">Balance (ETB)</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($cashAndBankAccounts as $acc)
                                    <tr>
                                        <td class="ps-4 py-2.5">
                                            <strong class="text-dark d-block">[{{ $acc->code }}] {{ $acc->name }}</strong>
                                            <small class="text-muted">{{ $acc->subtype ?? ucfirst($acc->type) }}</small>
                                        </td>
                                        <td class="py-2.5">
                                            <small class="text-dark fw-semibold">{{ $acc->manager->name ?? 'Company General' }}</small>
                                        </td>
                                        <td class="pe-4 py-2.5 text-end font-monospace fw-bold {{ $acc->current_balance < 0 ? 'text-danger' : 'text-success' }}">
                                            ETB {{ number_format($acc->current_balance, 2) }}
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="3" class="text-center py-3 text-muted">No accounts found.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

    </div>

    <!-- Live System Audit & Activity Trail -->
    <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
        <div class="card-header bg-white border-0 py-3 px-4 d-flex justify-content-between align-items-center">
            <div>
                <h5 class="fw-bold mb-0 text-dark">
                    <i class="fa-solid fa-list-check text-info me-2"></i>Live System Audit Trail &amp; Money Movement Logs
                </h5>
                <small class="text-muted">Automated chronological audit stream of financial transactions, approvals, disbursements, and user changes</small>
            </div>
            <a href="{{ route('admin.activity-logs') }}" class="btn btn-outline-primary btn-sm rounded-pill px-3 shadow-xs">
                <i class="fa-solid fa-filter me-1"></i> Filter Full Trail
            </a>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 small">
                    <thead class="bg-light text-muted text-uppercase" style="font-size: 0.75rem;">
                        <tr>
                            <th class="ps-4 py-2.5">Timestamp</th>
                            <th class="py-2.5">User</th>
                            <th class="py-2.5">Action</th>
                            <th class="py-2.5">Module</th>
                            <th class="py-2.5">Audit Description &amp; Details</th>
                            <th class="pe-4 py-2.5 text-end">IP Address</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($recentActivityLogs as $log)
                            <tr>
                                <td class="ps-4 py-3 text-muted" style="white-space:nowrap;">
                                    <strong class="text-dark d-block">{{ $log->created_at->format('M d, Y') }}</strong>
                                    <small>{{ $log->created_at->format('H:i:s') }} ({{ $log->created_at->diffForHumans() }})</small>
                                </td>
                                <td class="py-3">
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="bg-primary bg-opacity-10 text-primary rounded-circle d-flex align-items-center justify-content-center fw-bold" style="width:30px; height:30px; font-size:0.8rem;">
                                            {{ substr($log->user->name ?? 'S', 0, 1) }}
                                        </div>
                                        <div>
                                            <strong class="text-dark d-block">{{ $log->user->name ?? 'System' }}</strong>
                                        </div>
                                    </div>
                                </td>
                                <td class="py-3">
                                    <span class="badge bg-{{ $log->action_color }} rounded-pill px-2.5 py-1 font-monospace text-uppercase" style="font-size:0.7rem;">
                                        {{ $log->action }}
                                    </span>
                                </td>
                                <td class="py-3">
                                    <span class="badge bg-secondary-subtle text-dark border">
                                        {{ $log->module ?? 'General' }}
                                    </span>
                                </td>
                                <td class="py-3">
                                    <div class="text-dark">{{ $log->description }}</div>
                                    @if($log->changes && is_array($log->changes))
                                        <div class="mt-1 small text-muted font-monospace bg-light p-1.5 rounded border">
                                            @foreach($log->changes as $key => $val)
                                                <span class="me-2"><strong>{{ $key }}:</strong> {{ is_array($val) ? json_encode($val) : $val }}</span>
                                            @endforeach
                                        </div>
                                    @endif
                                </td>
                                <td class="pe-4 py-3 text-end text-muted font-monospace" style="font-size:0.75rem;">
                                    {{ $log->ip_address ?? 'N/A' }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center py-4 text-muted">
                                    No audit logs recorded yet.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div>
@endsection
