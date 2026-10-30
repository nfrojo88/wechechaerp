@extends('layouts.app')
@section('title', $isFinanceHead ? 'Finance Head Dashboard' : 'Finance Dashboard')

@section('content')
<div class="wc-container py-3">

    {{-- Top Header --}}
    <x-page-header 
        :title="$isFinanceHead ? 'Finance Head Command Center' : 'Finance Activity Dashboard'" 
        :subtitle="$isFinanceHead ? 'Corporate cash positions, bank account ledgers, plan vs actual budgets, and live revenue & expenditure flows.' : 'Your assigned chart of account balances and daily financial journal logs.'"
        :breadcrumbs="['Dashboard' => url('/dashboard'), 'Finance' => '']">
        <x-slot name="actions">
            <a href="{{ route('reports.index') }}" class="wc-btn-touch wc-btn-outline" title="Financial Statements Hub">
                <i class="fa-solid fa-file-invoice-dollar text-primary"></i>
                <span class="d-none d-sm-inline">Financial Reports</span>
            </a>
            <a href="{{ route('finance.dashboard') }}" class="wc-btn-touch wc-btn-outline" title="Refresh Live Data">
                <i class="fa-solid fa-rotate"></i>
                <span class="d-none d-sm-inline">Refresh</span>
            </a>
        </x-slot>
    </x-page-header>

    {{-- KPI Cards Row (Finance Head Only) --}}
    @if($isFinanceHead)
        @php
            $incomeVal = (float)($kpi['total_income'] ?? 0);
            $expenseVal = (float)($kpi['total_expense'] ?? 0);
            $netCash = $incomeVal - $expenseVal;
            $bankBal = (float)($kpi['cash_balance'] ?? 0);
        @endphp
        <div class="row g-3 mb-4">
            <div class="col-6 col-xl-3">
                <x-kpi-card 
                    label="Total Revenue" 
                    :value="'ETB ' . number_format($incomeVal, 2)" 
                    delta="Income" 
                    trend="up" 
                    icon="fa-solid fa-arrow-trend-up" 
                    theme="success" 
                    subtext="Certified IPCs & Receipts" />
            </div>

            <div class="col-6 col-xl-3">
                <x-kpi-card 
                    label="Total Expenses" 
                    :value="'ETB ' . number_format($expenseVal, 2)" 
                    delta="Disbursements" 
                    trend="down" 
                    icon="fa-solid fa-wallet" 
                    theme="danger" 
                    subtext="Site Operations & Payroll" />
            </div>

            <div class="col-6 col-xl-3">
                <x-kpi-card 
                    label="Net Position" 
                    :value="'ETB ' . number_format($netCash, 2)" 
                    :delta="$netCash >= 0 ? '+Surplus' : '-Deficit'" 
                    :trend="$netCash >= 0 ? 'up' : 'down'" 
                    icon="fa-solid fa-scale-balanced" 
                    :theme="$netCash >= 0 ? 'primary' : 'danger'" 
                    subtext="Operating Cash Margin" />
            </div>

            <div class="col-6 col-xl-3">
                <x-kpi-card 
                    label="Bank Balance" 
                    :value="'ETB ' . number_format($bankBal, 2)" 
                    delta="COA" 
                    trend="neutral" 
                    icon="fa-solid fa-building-columns" 
                    theme="neutral" 
                    subtext="All Cash & Bank Accounts" />
            </div>
        </div>
    @endif

    {{-- ═══ BANK & CASH ACCOUNTS SECTION ═══════════════════════════════════════════════ --}}
    @if($isFinanceHead && isset($cashAndBankCoas) && $cashAndBankCoas->count() > 0)
        <div class="wc-card mb-4">
            <div class="card-header bg-white py-3 px-3 px-md-4 d-flex align-items-center justify-content-between border-bottom flex-wrap gap-2">
                <div>
                    <h6 class="fw-bold text-dark mb-0">
                        <i class="fa-solid fa-building-columns text-primary me-2"></i>All Bank &amp; Cash Accounts (COA)
                    </h6>
                    <small class="text-muted">Master cash balances and active account managers</small>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <span class="badge bg-primary text-white rounded-pill px-2.5 py-1">{{ $cashAndBankCoas->count() }} Accounts</span>
                    <a href="{{ route('coa.index') }}" class="wc-btn-touch wc-btn-outline p-1 px-2.5 text-decoration-none" style="font-size: var(--wc-text-xs);">
                        <i class="fa-solid fa-list-check me-1"></i> Chart of Accounts
                    </a>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 wc-table-mobile-cards">
                    <thead class="bg-light text-uppercase text-muted" style="font-size: var(--wc-text-xs); letter-spacing: 0.05em;">
                        <tr>
                            <th class="ps-3 ps-md-4">Code</th>
                            <th>Account Name</th>
                            <th>Subtype</th>
                            <th>Assigned Manager</th>
                            <th class="text-end pe-3 pe-md-4">Current Balance (ETB)</th>
                            <th class="text-center">Status</th>
                        </tr>
                    </thead>
                    <tbody style="font-size: var(--wc-text-sm);">
                        @forelse($cashAndBankCoas as $bank)
                        <tr>
                            <td data-label="Code" class="ps-3 ps-md-4">
                                <span class="badge bg-dark font-monospace px-2 py-1">{{ $bank->code }}</span>
                            </td>
                            <td data-label="Account Name">
                                <span class="fw-bold text-dark">{{ $bank->name }}</span>
                            </td>
                            <td data-label="Subtype">
                                <span class="badge bg-light text-dark border">{{ $bank->subtype ?? 'Cash & Bank' }}</span>
                            </td>
                            <td data-label="Manager">
                                @if($bank->manager)
                                    <span class="small text-dark fw-medium"><i class="fa-solid fa-user-tie me-1 text-primary"></i>{{ $bank->manager->name }}</span>
                                @else
                                    <span class="text-muted small">&mdash; Unassigned &mdash;</span>
                                @endif
                            </td>
                            <td data-label="Balance" class="text-end pe-3 pe-md-4 wc-num font-monospace fw-bold {{ (float)$bank->current_balance >= 0 ? 'text-success' : 'text-danger' }}">
                                ETB {{ number_format($bank->current_balance ?? 0, 2) }}
                            </td>
                            <td data-label="Status" class="text-center">
                                <x-status-badge :status="$bank->is_active ? 'active' : 'draft'" :label="$bank->is_active ? 'Active' : 'Inactive'" />
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="6" class="text-center py-4 text-muted small">No Cash and Bank accounts found in Chart of Accounts.</td>
                        </tr>
                        @endforelse
                    </tbody>
                    @if($cashAndBankCoas->count() > 0)
                    <tfoot class="bg-light">
                        <tr>
                            <td colspan="4" class="ps-3 ps-md-4 fw-bold small text-muted">Total Liquidity Across All Cash &amp; Bank Accounts</td>
                            <td class="text-end pe-3 pe-md-4 fw-bold text-success font-monospace fs-6">
                                ETB {{ number_format($totalCashBankBalance ?? $cashAndBankCoas->sum('current_balance'), 2) }}
                            </td>
                            <td></td>
                        </tr>
                    </tfoot>
                    @endif
                </table>
            </div>
        </div>

    @elseif(!$isFinanceHead && $assignedAccounts->count() > 0)
        {{-- Regular Finance: ONLY assigned Chart of Account records --}}
        <div class="wc-card mb-4">
            <div class="card-header bg-white py-3 px-3 px-md-4 d-flex align-items-center justify-content-between border-bottom">
                <div>
                    <h6 class="fw-bold text-dark mb-0">
                        <i class="fa-solid fa-briefcase text-primary me-2"></i>My Assigned Accounts
                    </h6>
                    <small class="text-muted">Directly assigned to your financial custody</small>
                </div>
                <span class="badge bg-primary text-white rounded-pill px-2.5 py-1">{{ $assignedAccounts->count() }} Accounts</span>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 wc-table-mobile-cards">
                    <thead class="bg-light text-uppercase text-muted" style="font-size: var(--wc-text-xs);">
                        <tr>
                            <th class="ps-3 ps-md-4">Account Code</th>
                            <th>Account Name</th>
                            <th>Type</th>
                            <th class="text-end pe-3 pe-md-4">Balance</th>
                            <th class="text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody style="font-size: var(--wc-text-sm);">
                        @forelse($assignedAccounts as $acct)
                        <tr>
                            <td data-label="Code" class="ps-3 ps-md-4"><code class="small fw-bold text-primary">{{ $acct->code }}</code></td>
                            <td data-label="Name" class="fw-semibold text-dark">{{ $acct->name }}</td>
                            <td data-label="Type"><span class="badge bg-light text-dark border text-capitalize">{{ $acct->type }}</span></td>
                            <td data-label="Balance" class="text-end pe-3 pe-md-4 wc-num font-monospace fw-bold {{ ($acct->current_balance ?? 0) >= 0 ? 'text-success' : 'text-danger' }}">
                                ETB {{ number_format($acct->current_balance ?? 0, 2) }}
                            </td>
                            <td data-label="Action" class="text-center">
                                <a href="{{ route('assigned-accounts.show', $acct->id) }}" class="wc-btn-touch wc-btn-outline p-1 px-3">
                                    <i class="fa-solid fa-eye me-1"></i> View Ledger
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
        <div class="alert alert-info border-0 shadow-sm rounded-3 mb-4 d-flex align-items-center gap-3 p-3">
            <i class="fa-solid fa-circle-info fs-4 text-info"></i>
            <div>
                <div class="fw-semibold text-dark">No Accounts Assigned Yet</div>
                <div class="small text-muted">Contact your Finance Head to assign specific Chart of Account ledgers to your profile.</div>
            </div>
        </div>
    @endif

    {{-- ═══ PLAN VS ACTUAL — Finance Head Only ══════════════════════════════════ --}}
    @if($isFinanceHead)
        <div class="wc-card mb-4">
            <div class="card-header bg-white py-3 px-3 px-md-4 d-flex align-items-center justify-content-between border-bottom flex-wrap gap-2">
                <div>
                    <h6 class="fw-bold text-dark mb-0">
                        <i class="fa-solid fa-chart-bar text-warning me-2"></i>Project Budget Performance (Plan vs Actual)
                    </h6>
                    <small class="text-muted">Burn rates against authorized engineering estimates</small>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <a href="{{ route('budgets.index') }}" class="wc-btn-touch wc-btn-outline p-1 px-3">
                        <i class="fa-solid fa-sliders me-1"></i> Manage Budgets
                    </a>
                </div>
            </div>
            <div class="p-3 p-md-4">
                @forelse($planVsActual as $pva)
                <div class="mb-4 pb-3 border-bottom last:border-bottom-0">
                    <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between mb-2 gap-1">
                        <div class="d-flex align-items-center gap-2">
                            <span class="fw-bold text-dark">{{ $pva->name }}</span>
                            @if($pva->over_budget)
                                <x-status-badge status="critical" label="Over Budget" />
                            @else
                                <x-status-badge status="approved" label="On Track" />
                            @endif
                        </div>
                        <div class="text-start text-md-end font-monospace small">
                            <span class="text-muted">Budget: </span><strong class="text-dark">ETB {{ number_format($pva->budget, 0) }}</strong>
                            <span class="mx-1 text-muted">|</span>
                            <span class="text-muted">Actual: </span><strong class="{{ $pva->over_budget ? 'text-danger' : 'text-success' }}">ETB {{ number_format($pva->actual, 0) }}</strong>
                            <span class="mx-1 text-muted">|</span>
                            <span class="text-muted">Variance: </span>
                            <strong class="{{ $pva->variance >= 0 ? 'text-success' : 'text-danger' }}">
                                {{ $pva->variance >= 0 ? '+' : '' }}ETB {{ number_format($pva->variance, 0) }}
                            </strong>
                        </div>
                    </div>
                    <div class="progress rounded" style="height: 8px; background-color: var(--wc-surface-subtle);">
                        <div class="progress-bar {{ $pva->over_budget ? 'bg-danger' : ($pva->percentage > 80 ? 'bg-warning' : 'bg-success') }}"
                             role="progressbar"
                             style="width: {{ min($pva->percentage, 100) }}%;"
                             aria-valuenow="{{ $pva->percentage }}"
                             aria-valuemin="0"
                             aria-valuemax="100">
                        </div>
                    </div>
                    <div class="d-flex justify-content-between mt-1 text-muted" style="font-size: var(--wc-text-xs);">
                        <span>0%</span>
                        <span class="fw-semibold {{ $pva->over_budget ? 'text-danger' : 'text-dark' }}">{{ $pva->percentage }}% utilized</span>
                        <span>100%</span>
                    </div>
                </div>
                @empty
                <div class="text-center py-4 text-muted small">
                    <i class="fa-solid fa-chart-bar fa-2x mb-2 opacity-50 d-block"></i>
                    No project budgets recorded yet. <a href="{{ route('budgets.create') }}" class="text-primary fw-medium">Set a budget</a> to see live variance metrics.
                </div>
                @endforelse
            </div>
        </div>
    @endif

    {{-- Charts Row (Finance Head Only) --}}
    @if($isFinanceHead)
        <div class="row g-3 mb-4">
            <div class="col-xl-8 col-lg-7">
                <x-chart-card 
                    title="Monthly Cash Flow (Income vs Expenses)" 
                    chartId="liveIncomeExpenseChart" 
                    subtitle="12-Month historical trend across all active projects"
                    height="280px">
                    <x-slot name="actions">
                        <span class="badge bg-light text-dark border">Live Ledger</span>
                    </x-slot>
                </x-chart-card>
            </div>

            <div class="col-xl-4 col-lg-5">
                <x-chart-card 
                    title="Expense Distribution" 
                    chartId="liveExpensePieChart" 
                    subtitle="Disbursements by primary category"
                    height="280px">
                </x-chart-card>
            </div>
        </div>
    @endif

    {{-- Recent Transactions Row --}}
    <div class="row g-3 mb-4">
        {{-- Recent Client Receipts --}}
        <div class="col-lg-6">
            <div class="wc-card h-100">
                <div class="card-header bg-white py-3 px-3 px-md-4 d-flex align-items-center justify-content-between border-bottom">
                    <div>
                        <h6 class="fw-bold text-dark mb-0"><i class="fa-solid fa-receipt text-success me-2"></i>Recent Client Payments</h6>
                        <small class="text-muted">Verified revenue entries</small>
                    </div>
                    <a href="{{ route('payments.index') }}" class="text-primary small text-decoration-none fw-semibold">View All</a>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0 wc-table-mobile-cards">
                        <thead class="bg-light text-uppercase text-muted" style="font-size: var(--wc-text-xs);">
                            <tr>
                                <th class="ps-3 ps-md-4">Date</th>
                                <th>Ref #</th>
                                <th>Project</th>
                                <th class="text-end pe-3 pe-md-4">Amount (ETB)</th>
                            </tr>
                        </thead>
                        <tbody style="font-size: var(--wc-text-sm);">
                            @forelse($recentTransactions ?? [] as $pmt)
                            <tr>
                                <td data-label="Date" class="ps-3 ps-md-4 small text-muted">{{ $pmt->payment_date ? $pmt->payment_date->format('d M Y') : 'N/A' }}</td>
                                <td data-label="Ref #"><span class="fw-bold text-primary font-monospace">{{ $pmt->reference_number ?: 'PMT-' . $pmt->id }}</span></td>
                                <td data-label="Project" class="small">{{ $pmt->project->name ?? 'General' }}</td>
                                <td data-label="Amount" class="text-end pe-3 pe-md-4 wc-num font-monospace fw-bold text-success">ETB {{ number_format($pmt->amount, 2) }}</td>
                            </tr>
                            @empty
                            <tr><td colspan="4" class="text-center py-4 text-muted small">No payment receipts recorded yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        {{-- Recent Site Expenses --}}
        <div class="col-lg-6">
            <div class="wc-card h-100">
                <div class="card-header bg-white py-3 px-3 px-md-4 d-flex align-items-center justify-content-between border-bottom">
                    <div>
                        <h6 class="fw-bold text-dark mb-0"><i class="fa-solid fa-file-invoice-dollar text-danger me-2"></i>Recent Site Expenses</h6>
                        <small class="text-muted">Field disbursements & vouchers</small>
                    </div>
                    <a href="{{ route('expenses.index') }}" class="text-primary small text-decoration-none fw-semibold">View All</a>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0 wc-table-mobile-cards">
                        <thead class="bg-light text-uppercase text-muted" style="font-size: var(--wc-text-xs);">
                            <tr>
                                <th class="ps-3 ps-md-4">Date</th>
                                <th>Category</th>
                                <th>Description</th>
                                <th class="text-end pe-3 pe-md-4">Amount (ETB)</th>
                            </tr>
                        </thead>
                        <tbody style="font-size: var(--wc-text-sm);">
                            @forelse($recentExpenses ?? [] as $exp)
                            <tr>
                                <td data-label="Date" class="ps-3 ps-md-4 small text-muted">{{ isset($exp->expense_date) ? \Carbon\Carbon::parse($exp->expense_date)->format('d M Y') : 'N/A' }}</td>
                                <td data-label="Category"><span class="badge bg-light text-dark border">{{ $exp->category ?? 'Expense' }}</span></td>
                                <td data-label="Description" class="small text-muted text-truncate" style="max-width:160px">{{ $exp->title ?? $exp->description ?? 'Site Expense' }}</td>
                                <td data-label="Amount" class="text-end pe-3 pe-md-4 wc-num font-monospace fw-bold text-danger">ETB {{ number_format($exp->amount ?? 0, 2) }}</td>
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
<script>
document.addEventListener('DOMContentLoaded', function() {
    const labels   = {!! json_encode($monthlyAnalytics['labels']   ?? []) !!};
    const incomes  = {!! json_encode($monthlyAnalytics['incomes']  ?? []) !!};
    const expenses = {!! json_encode($monthlyAnalytics['expenses'] ?? []) !!};

    const ctxIE = document.getElementById('liveIncomeExpenseChart');
    if (ctxIE) {
        new Chart(ctxIE.getContext('2d'), {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [
                    { label: 'Income (ETB)',   data: incomes,  backgroundColor: '#10b981', borderRadius: 4 },
                    { label: 'Expenses (ETB)', data: expenses, backgroundColor: '#ef4444', borderRadius: 4 }
                ]
            },
            options: { 
                responsive: true, 
                maintainAspectRatio: false, 
                plugins: { legend: { position: 'top' } }, 
                scales: { y: { beginAtZero: true } } 
            }
        });
    }

    const expLabels = {!! json_encode($expLabels) !!};
    const expTotals = {!! json_encode($expTotals) !!};

    const ctxPie = document.getElementById('liveExpensePieChart');
    if (ctxPie) {
        new Chart(ctxPie.getContext('2d'), {
            type: 'doughnut',
            data: {
                labels: expLabels,
                datasets: [{ data: expTotals, backgroundColor: ['#0f2944','#1e40af','#10b981','#f59e0b','#ef4444','#64748b'] }]
            },
            options: { 
                responsive: true, 
                maintainAspectRatio: false, 
                cutout: '72%', 
                plugins: { legend: { position: 'bottom' } } 
            }
        });
    }
});
</script>
@endif
@endpush
@endsection
