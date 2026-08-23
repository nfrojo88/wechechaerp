@extends('layouts.app')
@section('title', $isFinanceHead ? 'Finance Head Dashboard' : 'Finance Dashboard')

@section('content')
<div class="container-fluid px-4 py-4">

    {{-- Top Header --}}
    <div class="d-flex align-items-center justify-content-between mb-4">
        <div>
            <h1 class="h3 fw-bold text-dark mb-1">
                <i class="fa-solid fa-chart-pie text-primary me-2"></i>
                {{ $isFinanceHead ? 'Finance Head Dashboard' : 'Finance Dashboard' }}
            </h1>
            <p class="text-muted small mb-0">
                {{ $isFinanceHead ? 'Full financial overview — all bank accounts, plan vs actual, revenue & expenses.' : 'Your assigned accounts and financial activity.' }}
            </p>
        </div>
        <div class="d-flex align-items-center gap-2">
            <a href="{{ route('finance.dashboard') }}" class="btn btn-sm btn-outline-secondary rounded-pill px-3">
                <i class="fa-solid fa-rotate me-1"></i>Refresh
            </a>
        </div>
    </div>

    {{-- KPI Cards — Finance Head only --}}
    @if($isFinanceHead)
    <div class="row g-3 mb-4">
        <div class="col-xl-3 col-md-6">
            <div class="card border-0 shadow-sm rounded-4 h-100 p-3 bg-white">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <div class="text-uppercase small fw-bold text-muted mb-1">Total Revenue / Income</div>
                        <h3 class="fw-bold text-success mb-0">ETB {{ number_format($kpi['total_income'] ?? 0, 2) }}</h3>
                        <div class="small text-muted mt-1"><i class="fa-solid fa-circle-check text-success me-1"></i>Payments &amp; Certified IPCs</div>
                    </div>
                    <div class="bg-success-subtle text-success rounded-4 p-3 fs-3 d-flex align-items-center justify-content-center" style="width:54px;height:54px">
                        <i class="fa-solid fa-arrow-trend-up"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card border-0 shadow-sm rounded-4 h-100 p-3 bg-white">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <div class="text-uppercase small fw-bold text-muted mb-1">Total Expenses</div>
                        <h3 class="fw-bold text-danger mb-0">ETB {{ number_format($kpi['total_expense'] ?? 0, 2) }}</h3>
                        <div class="small text-muted mt-1"><i class="fa-solid fa-wallet text-danger me-1"></i>Site Expenses &amp; Payroll</div>
                    </div>
                    <div class="bg-danger-subtle text-danger rounded-4 p-3 fs-3 d-flex align-items-center justify-content-center" style="width:54px;height:54px">
                        <i class="fa-solid fa-arrow-trend-down"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card border-0 shadow-sm rounded-4 h-100 p-3 bg-white">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <div class="text-uppercase small fw-bold text-muted mb-1">Net Cash Position</div>
                        @php $net = ($kpi['total_income'] ?? 0) - ($kpi['total_expense'] ?? 0); @endphp
                        <h3 class="fw-bold {{ $net >= 0 ? 'text-primary' : 'text-danger' }} mb-0">ETB {{ number_format($net, 2) }}</h3>
                        <div class="small text-muted mt-1"><i class="fa-solid fa-scale-balanced me-1"></i>Net Operating Margin</div>
                    </div>
                    <div class="bg-primary-subtle text-primary rounded-4 p-3 fs-3 d-flex align-items-center justify-content-center" style="width:54px;height:54px">
                        <i class="fa-solid fa-vault"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card border-0 shadow-sm rounded-4 h-100 p-3 bg-white">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <div class="text-uppercase small fw-bold text-muted mb-1">Total Bank Balance</div>
                        <h3 class="fw-bold text-info mb-0">ETB {{ number_format($kpi['cash_balance'] ?? 0, 2) }}</h3>
                        <div class="small text-muted mt-1"><i class="fa-solid fa-building-columns text-info me-1"></i>All active accounts</div>
                    </div>
                    <div class="bg-info-subtle text-info rounded-4 p-3 fs-3 d-flex align-items-center justify-content-center" style="width:54px;height:54px">
                        <i class="fa-solid fa-coins"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    @endif

    {{-- ═══ BANK ACCOUNTS SECTION ═══════════════════════════════════════════════ --}}
    @if($isFinanceHead && $bankAccounts->count() > 0)
    {{-- Finance Head: ALL bank accounts --}}
    <div class="card border-0 shadow-sm rounded-4 bg-white overflow-hidden mb-4">
        <div class="card-header bg-white py-3 px-4 d-flex align-items-center justify-content-between border-bottom">
            <h6 class="fw-bold text-dark mb-0">
                <i class="fa-solid fa-building-columns text-primary me-2"></i>All Bank Accounts
                <span class="badge bg-primary-subtle text-primary ms-2">{{ $bankAccounts->count() }} Accounts</span>
            </h6>
            <span class="badge bg-success-subtle text-success fw-bold">Finance Head View — Full Access</span>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="bg-light">
                    <tr>
                        <th class="ps-4">Bank Name</th>
                        <th>Account Name</th>
                        <th>Account No.</th>
                        <th>Type</th>
                        <th>Currency</th>
                        <th class="text-end pe-4">Current Balance</th>
                        <th class="text-center">Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($bankAccounts as $bank)
                    <tr>
                        <td class="ps-4">
                            <div class="d-flex align-items-center gap-2">
                                <div class="bg-primary-subtle rounded-3 d-flex align-items-center justify-content-center" style="width:36px;height:36px">
                                    <i class="fa-solid fa-building-columns text-primary small"></i>
                                </div>
                                <span class="fw-semibold small">{{ $bank->bank_name }}</span>
                            </div>
                        </td>
                        <td class="small">{{ $bank->account_name }}</td>
                        <td><code class="small">{{ $bank->account_number }}</code></td>
                        <td><span class="badge bg-secondary-subtle text-secondary text-capitalize">{{ str_replace('_', ' ', $bank->account_type ?? 'General') }}</span></td>
                        <td class="small fw-semibold">{{ $bank->currency ?? 'ETB' }}</td>
                        <td class="text-end pe-4 fw-bold {{ $bank->current_balance >= 0 ? 'text-success' : 'text-danger' }}">
                            {{ $bank->currency ?? 'ETB' }} {{ number_format($bank->current_balance ?? 0, 2) }}
                        </td>
                        <td class="text-center">
                            @if($bank->is_default)
                                <span class="badge bg-warning-subtle text-warning"><i class="fa-solid fa-star me-1"></i>Default</span>
                            @else
                                <span class="badge bg-success-subtle text-success"><i class="fa-solid fa-check me-1"></i>Active</span>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="7" class="text-center py-4 text-muted small">No bank accounts found.</td></tr>
                    @endforelse
                </tbody>
                @if($bankAccounts->count() > 0)
                <tfoot class="bg-light">
                    <tr>
                        <td colspan="5" class="ps-4 fw-bold small text-muted">Total Balance Across All Accounts</td>
                        <td class="text-end pe-4 fw-bold text-success">ETB {{ number_format($bankAccounts->sum('current_balance'), 2) }}</td>
                        <td></td>
                    </tr>
                </tfoot>
                @endif
            </table>
        </div>
    </div>

    @elseif(!$isFinanceHead && $assignedAccounts->count() > 0)
    {{-- Regular Finance: ONLY assigned Chart of Account records --}}
    <div class="card border-0 shadow-sm rounded-4 bg-white overflow-hidden mb-4">
        <div class="card-header bg-white py-3 px-4 d-flex align-items-center justify-content-between border-bottom">
            <h6 class="fw-bold text-dark mb-0">
                <i class="fa-solid fa-briefcase text-primary me-2"></i>My Assigned Accounts
                <span class="badge bg-primary-subtle text-primary ms-2">{{ $assignedAccounts->count() }} Accounts</span>
            </h6>
            <span class="badge bg-info-subtle text-info fw-bold">Your Access Only</span>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="bg-light">
                    <tr>
                        <th class="ps-4">Account Code</th>
                        <th>Account Name</th>
                        <th>Type</th>
                        <th class="text-end pe-4">Balance</th>
                        <th class="text-center">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($assignedAccounts as $acct)
                    <tr>
                        <td class="ps-4"><code class="small">{{ $acct->code }}</code></td>
                        <td class="fw-semibold small">{{ $acct->name }}</td>
                        <td><span class="badge bg-secondary-subtle text-secondary text-capitalize">{{ $acct->type }}</span></td>
                        <td class="text-end pe-4 fw-bold {{ ($acct->current_balance ?? 0) >= 0 ? 'text-success' : 'text-danger' }}">
                            ETB {{ number_format($acct->current_balance ?? 0, 2) }}
                        </td>
                        <td class="text-center">
                            <a href="{{ route('assigned-accounts.show', $acct->id) }}" class="btn btn-sm btn-outline-primary rounded-pill px-3">
                                <i class="fa-solid fa-eye me-1"></i>View
                            </a>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="5" class="text-center py-4 text-muted small">No accounts assigned to you yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @elseif(!$isFinanceHead && $assignedAccounts->count() === 0)
    <div class="alert alert-info rounded-4 border-0 shadow-sm mb-4 d-flex align-items-center gap-3">
        <i class="fa-solid fa-circle-info fs-4 text-info"></i>
        <div>
            <div class="fw-semibold">No Accounts Assigned Yet</div>
            <div class="small text-muted">Contact your Finance Head to assign accounts to your profile.</div>
        </div>
    </div>
    @endif

    {{-- ═══ PLAN VS ACTUAL — Finance Head Only ══════════════════════════════════ --}}
    @if($isFinanceHead)
    <div class="card border-0 shadow-sm rounded-4 bg-white overflow-hidden mb-4">
        <div class="card-header bg-white py-3 px-4 d-flex align-items-center justify-content-between border-bottom">
            <h6 class="fw-bold text-dark mb-0">
                <i class="fa-solid fa-chart-bar text-warning me-2"></i>Plan vs Actual — Project Budget Performance
            </h6>
            <div class="d-flex align-items-center gap-2">
                <span class="badge bg-success-subtle text-success"><i class="fa-solid fa-circle me-1"></i>Under Budget</span>
                <span class="badge bg-danger-subtle text-danger"><i class="fa-solid fa-circle me-1"></i>Over Budget</span>
                <a href="{{ route('budgets.index') }}" class="btn btn-sm btn-outline-secondary rounded-pill px-3 ms-2">
                    <i class="fa-solid fa-sliders me-1"></i>Manage Budgets
                </a>
            </div>
        </div>
        <div class="p-4">
            @forelse($planVsActual as $pva)
            <div class="mb-4">
                <div class="d-flex align-items-center justify-content-between mb-1">
                    <div class="d-flex align-items-center gap-2">
                        <span class="fw-semibold small">{{ $pva->name }}</span>
                        @if($pva->over_budget)
                            <span class="badge bg-danger-subtle text-danger"><i class="fa-solid fa-triangle-exclamation me-1"></i>Over Budget</span>
                        @else
                            <span class="badge bg-success-subtle text-success"><i class="fa-solid fa-check me-1"></i>On Track</span>
                        @endif
                    </div>
                    <div class="text-end small">
                        <span class="text-muted">Budget: </span><span class="fw-bold text-dark">ETB {{ number_format($pva->budget, 0) }}</span>
                        <span class="mx-2 text-muted">|</span>
                        <span class="text-muted">Actual: </span><span class="fw-bold {{ $pva->over_budget ? 'text-danger' : 'text-success' }}">ETB {{ number_format($pva->actual, 0) }}</span>
                        <span class="mx-2 text-muted">|</span>
                        <span class="text-muted">Variance: </span>
                        <span class="fw-bold {{ $pva->variance >= 0 ? 'text-success' : 'text-danger' }}">
                            {{ $pva->variance >= 0 ? '+' : '' }}ETB {{ number_format($pva->variance, 0) }}
                        </span>
                    </div>
                </div>
                <div class="progress rounded-pill" style="height: 12px; background: #e9ecef;">
                    <div class="progress-bar {{ $pva->over_budget ? 'bg-danger' : ($pva->percentage > 80 ? 'bg-warning' : 'bg-success') }} rounded-pill"
                         role="progressbar"
                         style="width: {{ min($pva->percentage, 100) }}%;"
                         aria-valuenow="{{ $pva->percentage }}"
                         aria-valuemin="0"
                         aria-valuemax="100">
                    </div>
                </div>
                <div class="d-flex justify-content-between mt-1">
                    <small class="text-muted">0%</small>
                    <small class="fw-semibold {{ $pva->over_budget ? 'text-danger' : ($pva->percentage > 80 ? 'text-warning' : 'text-success') }}">
                        {{ $pva->percentage }}% spent
                    </small>
                    <small class="text-muted">100%</small>
                </div>
            </div>
            @empty
            <div class="text-center py-5 text-muted">
                <i class="fa-solid fa-chart-bar fa-2x mb-3 opacity-25"></i>
                <div class="small">No project budgets set yet. <a href="{{ route('budgets.create') }}" class="text-primary">Set a budget</a> to see plan vs actual data.</div>
            </div>
            @endforelse
        </div>
    </div>
    @endif

    {{-- Charts Row — Finance Head only --}}
    @if($isFinanceHead)
    <div class="row g-4 mb-4">
        <div class="col-xl-8 col-lg-7">
            <div class="card border-0 shadow-sm rounded-4 p-4 bg-white h-100">
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <h6 class="fw-bold text-dark mb-0"><i class="fa-solid fa-chart-column text-primary me-2"></i>Monthly Cash Flow (Income vs Expenses)</h6>
                    <span class="badge bg-light text-muted border">Live Data</span>
                </div>
                <div style="height: 290px;"><canvas id="liveIncomeExpenseChart"></canvas></div>
            </div>
        </div>
        <div class="col-xl-4 col-lg-5">
            <div class="card border-0 shadow-sm rounded-4 p-4 bg-white h-100">
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <h6 class="fw-bold text-dark mb-0"><i class="fa-solid fa-chart-pie text-danger me-2"></i>Expense Distribution</h6>
                    <span class="badge bg-light text-muted border">By Category</span>
                </div>
                <div style="height: 290px;"><canvas id="liveExpensePieChart"></canvas></div>
            </div>
        </div>
    </div>
    @endif

    {{-- Transactions Tables --}}
    <div class="row g-4 mb-4">
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm rounded-4 bg-white overflow-hidden">
                <div class="card-header bg-white py-3 px-4 d-flex align-items-center justify-content-between border-bottom">
                    <h6 class="fw-bold text-dark mb-0"><i class="fa-solid fa-receipt text-success me-2"></i>Recent Client Payments</h6>
                    <span class="badge bg-success-subtle text-success fw-bold">Income Receipts</span>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="bg-light">
                            <tr><th>Date</th><th>Ref #</th><th>Project</th><th>Amount (ETB)</th></tr>
                        </thead>
                        <tbody>
                            @forelse($recentTransactions ?? [] as $pmt)
                            <tr>
                                <td class="small">{{ $pmt->payment_date ? $pmt->payment_date->format('M d, Y') : 'N/A' }}</td>
                                <td><span class="fw-semibold text-dark">{{ $pmt->reference_number ?: 'PMT-' . $pmt->id }}</span></td>
                                <td class="small">{{ $pmt->project->name ?? 'General' }}</td>
                                <td class="fw-bold text-success">ETB {{ number_format($pmt->amount, 2) }}</td>
                            </tr>
                            @empty
                            <tr><td colspan="4" class="text-center py-4 text-muted small">No payment receipts recorded yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm rounded-4 bg-white overflow-hidden">
                <div class="card-header bg-white py-3 px-4 d-flex align-items-center justify-content-between border-bottom">
                    <h6 class="fw-bold text-dark mb-0"><i class="fa-solid fa-file-invoice-dollar text-danger me-2"></i>Recent Site Expenses</h6>
                    <span class="badge bg-danger-subtle text-danger fw-bold">Outflow Entries</span>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="bg-light">
                            <tr><th>Date</th><th>Category</th><th>Notes</th><th>Amount (ETB)</th></tr>
                        </thead>
                        <tbody>
                            @forelse($recentExpenses ?? [] as $exp)
                            <tr>
                                <td class="small">{{ isset($exp->expense_date) ? \Carbon\Carbon::parse($exp->expense_date)->format('M d, Y') : 'N/A' }}</td>
                                <td><span class="badge bg-secondary-subtle text-secondary">{{ $exp->category ?? 'Expense' }}</span></td>
                                <td class="small text-muted text-truncate" style="max-width:160px">{{ $exp->title ?? $exp->description ?? 'Site Expense' }}</td>
                                <td class="fw-bold text-danger">ETB {{ number_format($exp->amount ?? 0, 2) }}</td>
                            </tr>
                            @empty
                            <tr><td colspan="4" class="text-center py-4 text-muted small">No site expense records found.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

</div>

@push('scripts')
@if($isFinanceHead)
@php
    $expLabels = []; $expTotals = [];
    foreach($expenseCategories ?? [] as $ec) {
        $expLabels[] = $ec->category ?: 'General';
        $expTotals[] = (float) $ec->total;
    }
    if(empty($expLabels)) { $expLabels = ['Materials','Payroll','Overhead']; $expTotals = [1,1,1]; }
@endphp
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const labels   = {!! json_encode($monthlyAnalytics['labels']   ?? []) !!};
    const incomes  = {!! json_encode($monthlyAnalytics['incomes']  ?? []) !!};
    const expenses = {!! json_encode($monthlyAnalytics['expenses'] ?? []) !!};

    const ctxIE = document.getElementById('liveIncomeExpenseChart').getContext('2d');
    new Chart(ctxIE, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [
                { label: 'Income (ETB)',   data: incomes,  backgroundColor: '#10b981', borderRadius: 6 },
                { label: 'Expenses (ETB)', data: expenses, backgroundColor: '#ef4444', borderRadius: 6 }
            ]
        },
        options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'top' } }, scales: { y: { beginAtZero: true } } }
    });

    const expLabels = {!! json_encode($expLabels) !!};
    const expTotals = {!! json_encode($expTotals) !!};

    const ctxPie = document.getElementById('liveExpensePieChart').getContext('2d');
    new Chart(ctxPie, {
        type: 'doughnut',
        data: {
            labels: expLabels,
            datasets: [{ data: expTotals, backgroundColor: ['#3b82f6','#8b5cf6','#10b981','#f59e0b','#ef4444','#64748b'] }]
        },
        options: { responsive: true, maintainAspectRatio: false, cutout: '70%', plugins: { legend: { position: 'bottom' } } }
    });
});
</script>
@endif
@endpush
@endsection
