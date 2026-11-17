@extends('layouts.app')

@section('title', 'Expenses vs Income by Site / Project — Financial Reports')

@section('content')
<div class="container-fluid px-0">
    <x-print-header title="Expenses vs Income Report by Construction Site" :dateRange="\Carbon\Carbon::parse($startDate)->format('M d, Y') . ' to ' . \Carbon\Carbon::parse($endDate)->format('M d, Y')" />

    {{-- Page Header --}}
    <x-page-header
        title="Expenses vs Income by Site"
        subtitle="Operational expenditures and realized company revenues aggregated across project cost centers from {{ \Carbon\Carbon::parse($startDate)->format('M d, Y') }} to {{ \Carbon\Carbon::parse($endDate)->format('M d, Y') }}."
        :breadcrumbs="[
            ['label' => 'Dashboard', 'url' => route('dashboard')],
            ['label' => 'Finance', 'url' => route('dashboard.finance')],
            ['label' => 'Reports', 'url' => route('reports.index')],
            ['label' => 'Expenses vs Income by Site']
        ]">
        <x-slot name="actions">
            <a href="{{ route('reports.index') }}" class="btn btn-outline-secondary btn-sm rounded-pill px-3 py-2 shadow-xs fw-semibold">
                <i class="fa-solid fa-arrow-left me-1.5"></i>Reports Hub
            </a>
            <a href="{{ route('income.index') }}" class="btn btn-outline-success btn-sm rounded-pill px-3 py-2 shadow-xs fw-semibold">
                <i class="fa-solid fa-arrow-trend-up me-1.5"></i>Company Income
            </a>
            <x-export-menu :print="true" label="Print / Export" />
            <a href="{{ route('finance.expense-hub.index') }}" class="btn btn-primary btn-sm rounded-pill px-3 py-2 shadow-xs fw-semibold">
                <i class="fa-solid fa-receipt me-1.5"></i>Expense Hub
            </a>
        </x-slot>
    </x-page-header>

    {{-- Filter Bar --}}
    <div class="card border-0 shadow-sm rounded-4 mb-4">
        <div class="card-body p-3">
            <form method="GET" action="{{ route('reports.expense-by-site') }}" class="row g-2 align-items-end">
                <div class="col-12 col-sm-6 col-md-3">
                    <label class="form-label text-muted small fw-bold text-uppercase mb-1" style="font-size: 0.72rem;">Start Date</label>
                    <input type="date" name="start_date" class="form-control form-control-sm" value="{{ $startDate }}" required>
                </div>
                <div class="col-12 col-sm-6 col-md-3">
                    <label class="form-label text-muted small fw-bold text-uppercase mb-1" style="font-size: 0.72rem;">End Date</label>
                    <input type="date" name="end_date" class="form-control form-control-sm" value="{{ $endDate }}" required>
                </div>
                <div class="col-12 col-sm-6 col-md-3">
                    <label class="form-label text-muted small fw-bold text-uppercase mb-1" style="font-size: 0.72rem;">
                        <i class="fa-solid fa-location-dot text-danger me-1"></i>Project / Site
                    </label>
                    <select name="project_id" class="form-select form-select-sm">
                        <option value="all">All Project Sites (Consolidated)</option>
                        @foreach($projects as $p)
                            <option value="{{ $p->id }}" {{ (request('project_id') == $p->id || (isset($selectedProject) && $selectedProject->id == $p->id)) ? 'selected' : '' }}>
                                {{ $p->name }} @if(!empty($p->code)) ({{ $p->code }}) @endif @if(!empty($p->location)) • {{ $p->location }} @endif
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12 col-sm-6 col-md-3 d-flex gap-2">
                    <button type="submit" class="btn btn-primary btn-sm rounded-pill px-4 py-2 w-100 fw-semibold shadow-xs">
                        <i class="fa-solid fa-filter me-1.5"></i>Filter
                    </button>
                    <a href="{{ route('reports.expense-by-site') }}" class="btn btn-outline-secondary btn-sm rounded-pill px-3 py-2 shadow-xs" title="Reset Filters">
                        <i class="fa-solid fa-rotate-left"></i>
                    </a>
                </div>
            </form>
        </div>
    </div>

    {{-- Executive KPI Metrics --}}
    <div class="row g-3 mb-4">
        <div class="col-6 col-lg-3">
            <x-kpi-card
                title="Realized Site Income"
                :value="'ETB ' . number_format($grandTotalIncome, 2)"
                :changeLabel="$selectedProject ? 'Deposited for ' . $selectedProject->name : 'All registered project income'"
                icon="fa-solid fa-hand-holding-dollar"
                color="success" />
        </div>
        <div class="col-6 col-lg-3">
            <x-kpi-card
                title="Total Site Expenses"
                :value="'ETB ' . number_format($grandTotalExpense, 2)"
                :changeLabel="$selectedProject ? 'Disbursed for ' . $selectedProject->name : 'Total construction site costs'"
                icon="fa-solid fa-receipt"
                color="danger" />
        </div>
        <div class="col-6 col-lg-3">
            <x-kpi-card
                title="Net Operating Margin"
                :value="'ETB ' . number_format($netOperatingPosition, 2)"
                :changeLabel="$netOperatingPosition >= 0 ? 'Net Operating Surplus (Profit)' : 'Operating Deficit (Cost Exceeds Income)'"
                icon="fa-solid fa-scale-balanced"
                :color="$netOperatingPosition >= 0 ? 'primary' : 'warning'" />
        </div>
        <div class="col-6 col-lg-3">
            <x-kpi-card
                title="Cost Center / Scope"
                :value="$selectedProject ? $selectedProject->name : number_format($projects->count()) . ' Sites'"
                :changeLabel="$selectedProject ? ($selectedProject->code ?: 'Specific Site') : 'All active construction sites'"
                icon="fa-solid fa-city"
                color="info" />
        </div>
    </div>

    {{-- ══════════════════════════════════════════════════════════════════════
         SECTION 1: SITE-BY-SITE INCOME VS EXPENSES LEDGER
         ══════════════════════════════════════════════════════════════════════ --}}
    <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4 wc-printable-area">
        <div class="card-header bg-white py-3 px-4 border-bottom d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div class="d-flex align-items-center gap-2">
                <span class="badge bg-primary bg-opacity-10 text-primary rounded-pill px-2.5 py-1 fw-bold small">
                    <i class="fa-solid fa-chart-line me-1"></i>Site Profitability Matrix
                </span>
                <span class="text-muted small">Income vs Expenses per Project Site</span>
            </div>
            @if($selectedProject)
                <a href="{{ route('reports.expense-by-site', ['start_date' => $startDate, 'end_date' => $endDate, 'project_id' => 'all']) }}" class="btn btn-xs btn-outline-secondary rounded-pill px-2.5 py-1 small">
                    <i class="fa-solid fa-border-all me-1"></i>View All Sites
                </a>
            @endif
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 wc-table-mobile-cards">
                <thead class="table-light">
                    <tr>
                        <th class="ps-4 text-secondary text-uppercase small fw-bold">Project / Site Name</th>
                        <th class="text-secondary text-uppercase small fw-bold">Site Code &amp; Location</th>
                        <th class="text-secondary text-uppercase small fw-bold">Client / Customer</th>
                        <th class="text-end text-success text-uppercase small fw-bold">Realized Income (ETB)</th>
                        <th class="text-end text-danger text-uppercase small fw-bold">Total Expenses (ETB)</th>
                        <th class="text-end text-secondary text-uppercase small fw-bold">Net Margin (ETB)</th>
                        <th class="text-center text-secondary text-uppercase small fw-bold">Status</th>
                        <th class="pe-4 text-end text-secondary text-uppercase small fw-bold">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @php
                        $displaySites = $selectedProject 
                            ? $siteComparison->where('project.id', $selectedProject->id) 
                            : $siteComparison;
                    @endphp
                    @forelse($displaySites as $sc)
                        @php
                            $p = $sc['project'];
                            $inc = (float)$sc['income'];
                            $exp = (float)$sc['expense'];
                            $net = (float)$sc['net_margin'];
                            $isProfitable = $net >= 0;
                        @endphp
                        <tr class="{{ isset($selectedProject) && $selectedProject->id == $p->id ? 'table-primary bg-opacity-10' : '' }}">
                            <td class="ps-4" data-label="Project">
                                <div class="fw-bold text-dark d-flex align-items-center gap-1.5">
                                    <i class="fa-solid fa-location-dot text-danger small"></i>
                                    <span>{{ $p->name }}</span>
                                </div>
                            </td>
                            <td data-label="Code & Location">
                                <div>
                                    @if($p->code)
                                        <span class="badge bg-light text-secondary border font-monospace small px-1.5 py-0.5">{{ $p->code }}</span>
                                    @endif
                                    <span class="text-muted small ms-1">{{ $p->location ?: '—' }}</span>
                                </div>
                            </td>
                            <td data-label="Client">
                                <span class="text-dark small">{{ $p->client_name ?: '—' }}</span>
                            </td>
                            <td class="text-end font-tabular fw-bold text-success" data-label="Realized Income">
                                <div>{{ number_format($inc, 2) }}</div>
                                @if($sc['income_count'] > 0)
                                    <small class="text-muted fw-normal" style="font-size: 0.70rem;">
                                        <i class="fa-solid fa-receipt me-1"></i>{{ $sc['income_count'] }} receipt(s)
                                    </small>
                                @endif
                            </td>
                            <td class="text-end font-tabular fw-bold text-danger" data-label="Total Expenses">
                                {{ number_format($exp, 2) }}
                            </td>
                            <td class="text-end font-tabular fw-bold {{ $isProfitable ? 'text-primary' : 'text-danger' }}" data-label="Net Margin">
                                {{ number_format($net, 2) }}
                            </td>
                            <td class="text-center" data-label="Status">
                                @if($inc == 0 && $exp == 0)
                                    <span class="badge bg-light text-muted border rounded-pill px-2 py-1 small">No Activity</span>
                                @elseif($isProfitable)
                                    <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-2.5 py-1 small fw-semibold">
                                        <i class="fa-solid fa-arrow-trend-up me-1"></i>Profitable @if($sc['margin_pct'] > 0) ({{ $sc['margin_pct'] }}%) @endif
                                    </span>
                                @else
                                    <span class="badge bg-danger-subtle text-danger border border-danger-subtle rounded-pill px-2.5 py-1 small fw-semibold">
                                        <i class="fa-solid fa-arrow-trend-down me-1"></i>Deficit
                                    </span>
                                @endif
                            </td>
                            <td class="pe-4 text-end" data-label="Action">
                                <div class="d-inline-flex gap-1">
                                    <a href="{{ route('reports.expense-by-site', ['start_date' => $startDate, 'end_date' => $endDate, 'project_id' => $p->id]) }}" 
                                       class="btn btn-xs btn-outline-primary rounded-pill px-2 py-0.5 shadow-xs" style="font-size: 0.72rem;" title="Filter this site">
                                        <i class="fa-solid fa-filter me-1"></i>Filter
                                    </a>
                                    <a href="{{ route('projects.show', $p->id) }}" class="btn btn-xs btn-light border rounded-pill px-2 py-0.5" style="font-size: 0.72rem;" title="View Project Details">
                                        <i class="fa-solid fa-arrow-up-right-from-square"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center py-4 text-muted">
                                <i class="fa-solid fa-circle-exclamation me-1"></i>No project sites found.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
                <tfoot class="bg-light border-top border-2">
                    <tr class="fw-bold fs-6">
                        <td colspan="3" class="ps-4 py-3 text-uppercase small text-dark align-middle">
                            Consolidated Totals
                        </td>
                        <td class="text-end font-tabular text-success align-middle">
                            <span class="small text-muted me-1">ETB</span>{{ number_format($displaySites->sum('income'), 2) }}
                        </td>
                        <td class="text-end font-tabular text-danger align-middle">
                            <span class="small text-muted me-1">ETB</span>{{ number_format($displaySites->sum('expense'), 2) }}
                        </td>
                        <td class="text-end font-tabular {{ ($displaySites->sum('income') - $displaySites->sum('expense')) >= 0 ? 'text-primary' : 'text-danger' }} align-middle">
                            <span class="small text-muted me-1">ETB</span>{{ number_format($displaySites->sum('income') - $displaySites->sum('expense'), 2) }}
                        </td>
                        <td colspan="2"></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>

    {{-- ══════════════════════════════════════════════════════════════════════
         SECTION 2: COMPANY INCOME RECEIPTS FOR THIS SITE / SCOPE
         ══════════════════════════════════════════════════════════════════════ --}}
    <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
        <div class="card-header bg-white py-3 px-4 border-bottom d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div class="d-flex align-items-center gap-2">
                <span class="badge bg-success bg-opacity-10 text-success rounded-pill px-2.5 py-1 fw-bold small">
                    <i class="fa-solid fa-receipt me-1"></i>Company Income Receipts
                </span>
                <span class="text-muted small">
                    @if($selectedProject)
                        Receipts logged for <strong>{{ $selectedProject->name }}</strong>
                    @else
                        All verified income receipts across registered sites
                    @endif
                    ({{ $incomeRecords->count() }} records)
                </span>
            </div>
            <a href="{{ route('income.index', $selectedProject ? ['project_id' => $selectedProject->id] : []) }}" class="btn btn-xs btn-outline-success rounded-pill px-2.5 py-1 small fw-semibold">
                <i class="fa-solid fa-plus me-1"></i>Record New Income
            </a>
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light text-secondary" style="font-size: 0.78rem;">
                    <tr>
                        <th class="ps-4">INCOME REF</th>
                        <th>DATE (EC / GC)</th>
                        <th>DIVISION</th>
                        <th>PROJECT / SITE</th>
                        <th>CUSTOMER / BUYER</th>
                        <th>RECEIPT (FS#)</th>
                        <th class="text-end">GROSS AMOUNT</th>
                        <th class="text-end">DEPOSITED ACCOUNT</th>
                        <th class="text-center pe-4">ACTION</th>
                    </tr>
                </thead>
                <tbody style="font-size: 0.85rem;">
                    @forelse($incomeRecords as $inc)
                        <tr>
                            <td class="ps-4 text-nowrap">
                                <span class="fw-bold text-dark font-monospace">#{{ substr($inc->income_no, -5) }}</span>
                                <div class="text-muted small" style="font-size: 0.72rem;">{{ $inc->income_no }}</div>
                            </td>
                            <td class="text-nowrap">
                                <span class="fw-semibold text-dark">{{ $inc->income_date ? \App\Services\EthiopianDate::formatDateEc($inc->income_date, 'DD/MM/YYYY EC') : '—' }}</span>
                                <div class="text-muted small" style="font-size: 0.72rem;">{{ $inc->income_date ? \Carbon\Carbon::parse($inc->income_date)->format('M d, Y') : '' }}</div>
                            </td>
                            <td>
                                <span class="badge bg-light text-dark border rounded-pill px-2 py-0.5 text-capitalize small">
                                    {{ $inc->category }}
                                </span>
                            </td>
                            <td>
                                @if($inc->project)
                                    <div class="fw-semibold text-dark d-flex align-items-center gap-1">
                                        <i class="fa-solid fa-location-dot text-danger small"></i>
                                        <span>{{ $inc->project->name }}</span>
                                    </div>
                                    @if($inc->project->code)
                                        <small class="text-muted font-monospace">{{ $inc->project->code }}</small>
                                    @endif
                                @else
                                    <span class="text-muted small">— Unassigned —</span>
                                @endif
                            </td>
                            <td>
                                <div class="fw-semibold text-dark">{{ $inc->buyer_name ?: ($inc->project->client_name ?? 'Walk-in') }}</div>
                                @if(!empty($inc->formatted_buyer_tin))
                                    <small class="text-muted font-monospace">TIN: {{ $inc->formatted_buyer_tin }}</small>
                                @endif
                            </td>
                            <td>
                                @if(!empty($inc->formatted_receipt_no))
                                    <span class="badge bg-success bg-opacity-10 text-success border border-success-subtle font-monospace fw-bold">
                                        {{ $inc->formatted_receipt_no }}
                                    </span>
                                @else
                                    <span class="text-muted small">No FS#</span>
                                @endif
                            </td>
                            <td class="text-end fw-bold text-success font-tabular">
                                ETB {{ number_format($inc->amount, 2) }}
                            </td>
                            <td class="text-end">
                                <span class="small text-muted" title="{{ $inc->deposit_account_name }}">
                                    <i class="fa-solid fa-building-columns text-primary me-1"></i>{{ \Illuminate\Support\Str::limit($inc->deposit_account_name, 22) }}
                                </span>
                            </td>
                            <td class="text-center pe-4">
                                <a href="{{ route('income.show', $inc->id) }}" class="btn btn-xs btn-outline-primary rounded-pill px-2.5 py-0.5" style="font-size: 0.72rem;" title="View Income Receipt Details">
                                    <i class="fa-solid fa-eye me-1"></i>Details
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="text-center py-4 text-muted">
                                <i class="fa-solid fa-receipt fs-4 mb-2 opacity-50 d-block"></i>
                                <span>No income receipts recorded for this project site in the selected date range.</span>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- ══════════════════════════════════════════════════════════════════════
         SECTION 3: GENERAL LEDGER EXPENSE BREAKDOWN BY CATEGORY
         ══════════════════════════════════════════════════════════════════════ --}}
    <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4 wc-printable-area">
        <div class="card-header bg-white py-3 px-4 border-bottom d-flex align-items-center justify-content-between">
            <div class="d-flex align-items-center gap-2">
                <span class="badge bg-danger bg-opacity-10 text-danger rounded-pill px-2.5 py-1 fw-bold small">
                    <i class="fa-solid fa-list me-1"></i>GL Expense Accounts
                </span>
                <span class="text-muted small">
                    @if($selectedProject)
                        General Ledger debits linked to <strong>{{ $selectedProject->name }}</strong>
                    @else
                        Consolidated General Ledger Expense Distribution
                    @endif
                    ({{ $expenses->count() }} accounts)
                </span>
            </div>
            <span class="text-muted small">Sorted by highest expenditure</span>
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 wc-table-mobile-cards">
                <thead class="table-light">
                    <tr>
                        <th class="ps-4 text-secondary text-uppercase small fw-bold" style="width: 130px;">Account Code</th>
                        <th class="text-secondary text-uppercase small fw-bold">Expense Account Name</th>
                        <th class="text-secondary text-uppercase small fw-bold" style="width: 220px;">Distribution (% Share)</th>
                        <th class="pe-4 text-end text-secondary text-uppercase small fw-bold" style="width: 200px;">Total Expense (ETB)</th>
                    </tr>
                </thead>
                <tbody>
                    @php
                        $glSum = $expenses->sum('total_amount');
                    @endphp
                    @forelse($expenses->sortByDesc('total_amount') as $exp)
                        @php
                            $share = $glSum > 0 ? ($exp->total_amount / $glSum) * 100 : 0;
                        @endphp
                        <tr>
                            <td class="ps-4 font-monospace fw-bold text-danger" data-label="Account Code">
                                {{ $exp->account_code }}
                            </td>
                            <td data-label="Expense Account">
                                <span class="fw-semibold text-dark">{{ $exp->account_name }}</span>
                            </td>
                            <td data-label="Share">
                                <div class="d-flex align-items-center gap-2" style="max-width: 200px;">
                                    <div class="progress flex-grow-1" style="height: 6px; background-color: #f1f5f9;">
                                        <div class="progress-bar bg-danger" role="progressbar" style="width: {{ $share }}%"></div>
                                    </div>
                                    <span class="font-tabular small fw-semibold text-muted text-nowrap" style="font-size: 0.75rem;">
                                        {{ number_format($share, 1) }}%
                                    </span>
                                </div>
                            </td>
                            <td class="pe-4 text-end font-tabular fw-bold text-danger fs-6" data-label="Total Expense (ETB)">
                                {{ number_format($exp->total_amount, 2) }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="text-center py-5">
                                <x-empty-state
                                    title="No Expense Transactions Recorded"
                                    description="No expense lines match the selected criteria in the General Ledger."
                                    icon="fa-solid fa-receipt" />
                            </td>
                        </tr>
                    @endforelse
                </tbody>
                @if($glSum > 0)
                    <tfoot class="bg-light border-top border-2">
                        <tr class="fw-bold fs-6">
                            <td colspan="2" class="ps-4 py-3 text-uppercase small text-dark align-middle">
                                Grand Total GL Expenses
                            </td>
                            <td class="text-muted small align-middle">
                                100.0% of periodic debits
                            </td>
                            <td class="pe-4 py-3 text-end font-tabular text-danger align-middle" style="border-bottom: 3px double #dc2626;">
                                <span class="small text-muted me-1">ETB</span>{{ number_format($glSum, 2) }}
                            </td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
    </div>

    {{-- Official Print Signature Block --}}
    <x-print-signatures 
        preparedTitle="Prepared By" 
        preparedRole="Cost Accountant" 
        checkedTitle="Verified By" 
        checkedRole="Finance Head" 
        approvedTitle="Approved By" 
        approvedRole="General Manager" />
</div>
@endsection
