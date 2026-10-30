@extends('layouts.app')

@section('title', 'Expense Report by Site / Project — Financial Reports')

@section('content')
@php
    $grandTotal = $expenses->sum('total_amount');
    $topExpense = $expenses->sortByDesc('total_amount')->first();
    $avgExpense = $expenses->count() > 0 ? $grandTotal / $expenses->count() : 0;
@endphp

<div class="container-fluid px-0">
    <x-print-header title="Expense Report by Site / Category" :dateRange="\Carbon\Carbon::parse($startDate)->format('M d, Y') . ' to ' . \Carbon\Carbon::parse($endDate)->format('M d, Y')" />

    {{-- Page Header --}}
    <x-page-header
        title="Expense Report by Site / Category"
        subtitle="Operational expenditures aggregated across Chart of Accounts categories and project activities from {{ \Carbon\Carbon::parse($startDate)->format('M d, Y') }} to {{ \Carbon\Carbon::parse($endDate)->format('M d, Y') }}."
        :breadcrumbs="[
            ['label' => 'Dashboard', 'url' => route('dashboard')],
            ['label' => 'Finance', 'url' => route('dashboard.finance')],
            ['label' => 'Reports', 'url' => route('reports.index')],
            ['label' => 'Expense by Site']
        ]">
        <x-slot name="actions">
            <a href="{{ route('reports.index') }}" class="btn btn-outline-secondary btn-sm rounded-pill px-3 py-2 shadow-xs fw-semibold">
                <i class="fa-solid fa-arrow-left me-1.5"></i>Reports Hub
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
                <div class="col-12 col-sm-6 col-md-4 col-lg-3">
                    <label class="form-label text-muted small fw-bold text-uppercase mb-1" style="font-size: 0.72rem;">Start Date</label>
                    <input type="date" name="start_date" class="form-control form-control-sm" value="{{ $startDate }}" required>
                </div>
                <div class="col-12 col-sm-6 col-md-4 col-lg-3">
                    <label class="form-label text-muted small fw-bold text-uppercase mb-1" style="font-size: 0.72rem;">End Date</label>
                    <input type="date" name="end_date" class="form-control form-control-sm" value="{{ $endDate }}" required>
                </div>
                <div class="col-12 col-md-4 col-lg-3 d-flex gap-2">
                    <button type="submit" class="btn btn-primary btn-sm rounded-pill px-4 py-2 w-100 fw-semibold shadow-xs">
                        <i class="fa-solid fa-filter me-1.5"></i>Filter Expenses
                    </button>
                    <a href="{{ route('reports.expense-by-site') }}" class="btn btn-outline-secondary btn-sm rounded-pill px-3 py-2 shadow-xs" title="Reset to YTD">
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
                title="Grand Total Expenses"
                :value="'ETB ' . number_format($grandTotal, 2)"
                changeLabel="All verified journal debits"
                icon="fa-solid fa-receipt"
                color="danger" />
        </div>
        <div class="col-6 col-lg-3">
            <x-kpi-card
                title="Expense Categories"
                :value="number_format($expenses->count())"
                changeLabel="Active GL expense accounts"
                icon="fa-solid fa-tags"
                color="primary" />
        </div>
        <div class="col-6 col-lg-3">
            <x-kpi-card
                title="Top Spending Category"
                :value="$topExpense ? 'ETB ' . number_format($topExpense->total_amount, 2) : '—'"
                :changeLabel="$topExpense ? \Illuminate\Support\Str::limit($topExpense->account_name, 26) : 'None recorded'"
                icon="fa-solid fa-fire text-danger"
                color="warning" />
        </div>
        <div class="col-6 col-lg-3">
            <x-kpi-card
                title="Average Spend / Category"
                :value="'ETB ' . number_format($avgExpense, 2)"
                changeLabel="Mean distribution per account"
                icon="fa-solid fa-chart-pie"
                color="info" />
        </div>
    </div>

    {{-- Expenses Master Table --}}
    <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4 wc-printable-area">
        {{-- Formal Print Header (Only visible on print) --}}
        <div class="d-none d-print-block p-4 border-bottom text-center">
            <h2 class="fw-bold mb-1" style="color: #0F2944;">WECHECHA CONSTRUCTION ERP</h2>
            <h4 class="text-secondary fw-semibold mb-1">Official Expense Breakdown by Category &amp; Site</h4>
            <p class="text-muted small mb-0">
                Period: {{ \Carbon\Carbon::parse($startDate)->format('F j, Y') }} — {{ \Carbon\Carbon::parse($endDate)->format('F j, Y') }} | Currency: ETB
            </p>
        </div>

        <div class="card-header bg-white py-3 px-4 border-bottom d-flex align-items-center justify-content-between">
            <div class="d-flex align-items-center gap-2">
                <span class="badge bg-danger bg-opacity-10 text-danger rounded-pill px-2.5 py-1 fw-bold small">
                    <i class="fa-solid fa-list me-1"></i>Expense Distribution
                </span>
                <span class="text-muted small">{{ $expenses->count() }} line items</span>
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
                    @forelse($expenses->sortByDesc('total_amount') as $exp)
                        @php
                            $share = $grandTotal > 0 ? ($exp->total_amount / $grandTotal) * 100 : 0;
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
                                    description="No expense lines match the selected time period in the General Ledger."
                                    icon="fa-solid fa-receipt" />
                            </td>
                        </tr>
                    @endforelse
                </tbody>
                @if($grandTotal > 0)
                    <tfoot class="bg-light border-top border-2">
                        <tr class="fw-bold fs-6">
                            <td colspan="2" class="ps-4 py-3 text-uppercase small text-dark align-middle">
                                Grand Total Expenses
                            </td>
                            <td class="text-muted small align-middle">
                                100.0% of periodic debits
                            </td>
                            <td class="pe-4 py-3 text-end font-tabular text-danger align-middle" style="border-bottom: 3px double #dc2626;">
                                <span class="small text-muted me-1">ETB</span>{{ number_format($grandTotal, 2) }}
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
