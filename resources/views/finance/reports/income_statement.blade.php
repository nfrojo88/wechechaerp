@extends('layouts.app')

@section('title', 'Income Statement (Profit & Loss) — Financial Reports')

@section('content')
@php
    $margin = $totalRevenue > 0 ? ($netIncome / $totalRevenue) * 100 : 0;
    $isProfitable = $netIncome >= 0;
@endphp

<div class="container-fluid px-0">
    <x-print-header title="Income Statement (Profit & Loss)" :dateRange="\Carbon\Carbon::parse($startDate)->format('M d, Y') . ' to ' . \Carbon\Carbon::parse($endDate)->format('M d, Y')" />

    {{-- Page Header --}}
    <x-page-header
        title="Income Statement (Profit & Loss)"
        subtitle="Operational performance, gross revenue, direct costs, and computed net income from {{ \Carbon\Carbon::parse($startDate)->format('M d, Y') }} to {{ \Carbon\Carbon::parse($endDate)->format('M d, Y') }}."
        :breadcrumbs="[
            ['label' => 'Dashboard', 'url' => route('dashboard')],
            ['label' => 'Finance', 'url' => route('dashboard.finance')],
            ['label' => 'Reports', 'url' => route('reports.index')],
            ['label' => 'Income Statement']
        ]">
        <x-slot name="actions">
            <a href="{{ route('reports.index') }}" class="btn btn-outline-secondary btn-sm rounded-pill px-3 py-2 shadow-xs fw-semibold">
                <i class="fa-solid fa-arrow-left me-1.5"></i>Reports Hub
            </a>
            <x-export-menu :print="true" label="Print / Export" />
            <a href="{{ route('reports.balance-sheet') }}" class="btn btn-primary btn-sm rounded-pill px-3 py-2 shadow-xs fw-semibold">
                <i class="fa-solid fa-building-columns me-1.5"></i>Balance Sheet
            </a>
        </x-slot>
    </x-page-header>

    {{-- Filter Bar --}}
    <div class="card border-0 shadow-sm rounded-4 mb-4">
        <div class="card-body p-3">
            <form method="GET" action="{{ route('reports.income-statement') }}" class="row g-2 align-items-end">
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
                        <i class="fa-solid fa-filter me-1.5"></i>Apply Filter
                    </button>
                    <a href="{{ route('reports.income-statement') }}" class="btn btn-outline-secondary btn-sm rounded-pill px-3 py-2 shadow-xs" title="Reset to current month">
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
                title="Operating Revenue"
                :value="'ETB ' . number_format($totalRevenue, 2)"
                :changeLabel="$revenues->count() . ' active revenue accounts'"
                icon="fa-solid fa-arrow-trend-up"
                color="success" />
        </div>
        <div class="col-6 col-lg-3">
            <x-kpi-card
                title="Operating Expenses"
                :value="'ETB ' . number_format($totalExpense, 2)"
                :changeLabel="$expenses->count() . ' expense line items'"
                icon="fa-solid fa-arrow-trend-down"
                color="danger" />
        </div>
        <div class="col-6 col-lg-3">
            <x-kpi-card
                :title="$isProfitable ? 'Net Operating Profit' : 'Net Operating Loss'"
                :value="'ETB ' . number_format(abs($netIncome), 2)"
                :changeLabel="$isProfitable ? 'Positive bottom line' : 'Net deficit for period'"
                :icon="$isProfitable ? 'fa-solid fa-circle-check' : 'fa-solid fa-circle-exclamation'"
                :color="$isProfitable ? 'success' : 'danger'" />
        </div>
        <div class="col-6 col-lg-3">
            <x-kpi-card
                title="Net Profit Margin"
                :value="number_format($margin, 1) . '%'"
                changeLabel="Net income / Total revenue"
                icon="fa-solid fa-percent"
                :color="$margin >= 0 ? 'primary' : 'warning'" />
        </div>
    </div>

    {{-- Statement Content Card --}}
    <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4 wc-printable-area">
        {{-- Formal Print Header (Only visible on print) --}}
        <div class="d-none d-print-block p-4 border-bottom text-center">
            <h2 class="fw-bold mb-1" style="color: #0F2944;">WECHECHA CONSTRUCTION ERP</h2>
            <h4 class="text-secondary fw-semibold mb-1">Official Income Statement (Profit &amp; Loss)</h4>
            <p class="text-muted small mb-0">
                Period: {{ \Carbon\Carbon::parse($startDate)->format('F j, Y') }} — {{ \Carbon\Carbon::parse($endDate)->format('F j, Y') }} | Currency: ETB
            </p>
        </div>

        <div class="table-responsive">
            <table class="table align-middle mb-0 wc-table-mobile-cards">
                {{-- ── SECTION 1: OPERATING REVENUES ── --}}
                <thead class="table-light">
                    <tr>
                        <th colspan="3" class="ps-4 py-3 text-uppercase fw-bold text-success" style="font-size: 0.85rem; letter-spacing: 0.5px;">
                            <i class="fa-solid fa-circle-dollar-to-slot me-2"></i>1. Operating Revenues &amp; Income
                        </th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($revenues as $acc)
                        <tr>
                            <td class="ps-4 font-monospace fw-bold text-primary" style="width: 140px;" data-label="Account Code">
                                {{ $acc->code }}
                            </td>
                            <td data-label="Account Name">
                                <span class="fw-semibold text-dark">{{ $acc->name }}</span>
                                @if(!empty($acc->subtype))
                                    <span class="badge bg-light text-muted border ms-2" style="font-size: 0.72rem;">{{ str_replace('_', ' ', ucfirst($acc->subtype)) }}</span>
                                @endif
                            </td>
                            <td class="pe-4 text-end font-tabular fw-semibold text-success" style="width: 220px;" data-label="Balance (ETB)">
                                {{ number_format($acc->computed_balance, 2) }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" class="text-center py-4 text-muted">
                                <i class="fa-solid fa-circle-info me-1 text-muted"></i> No revenue transactions recorded in this period.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
                <tfoot class="border-bottom border-2">
                    <tr class="table-success bg-opacity-25 fw-bold">
                        <td colspan="2" class="ps-4 py-3 text-uppercase small text-dark">
                            Total Operating Revenue
                        </td>
                        <td class="pe-4 py-3 text-end font-tabular fs-6 text-success">
                            <span class="small text-muted me-1">ETB</span>{{ number_format($totalRevenue, 2) }}
                        </td>
                    </tr>
                </tfoot>

                {{-- ── SECTION 2: OPERATING EXPENSES & COSTS ── --}}
                <thead class="table-light">
                    <tr>
                        <th colspan="3" class="ps-4 py-3 text-uppercase fw-bold text-danger" style="font-size: 0.85rem; letter-spacing: 0.5px;">
                            <i class="fa-solid fa-receipt me-2"></i>2. Operating Expenses &amp; Cost of Sales
                        </th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($expenses as $acc)
                        <tr>
                            <td class="ps-4 font-monospace fw-bold text-danger" style="width: 140px;" data-label="Account Code">
                                {{ $acc->code }}
                            </td>
                            <td data-label="Account Name">
                                <span class="fw-semibold text-dark">{{ $acc->name }}</span>
                                @if(!empty($acc->subtype))
                                    <span class="badge bg-light text-muted border ms-2" style="font-size: 0.72rem;">{{ str_replace('_', ' ', ucfirst($acc->subtype)) }}</span>
                                @endif
                            </td>
                            <td class="pe-4 text-end font-tabular fw-semibold text-danger" style="width: 220px;" data-label="Balance (ETB)">
                                {{ number_format($acc->computed_balance, 2) }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" class="text-center py-4 text-muted">
                                <i class="fa-solid fa-circle-info me-1 text-muted"></i> No expenses recorded in this period.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
                <tfoot class="border-bottom border-2">
                    <tr class="table-danger bg-opacity-25 fw-bold">
                        <td colspan="2" class="ps-4 py-3 text-uppercase small text-dark">
                            Total Operating Expenses
                        </td>
                        <td class="pe-4 py-3 text-end font-tabular fs-6 text-danger">
                            <span class="small text-muted me-1">ETB</span>{{ number_format($totalExpense, 2) }}
                        </td>
                    </tr>
                </tfoot>

                {{-- ── SECTION 3: NET BOTTOM LINE ── --}}
                <tfoot class="bg-dark text-white border-top border-3">
                    <tr class="align-middle">
                        <td colspan="2" class="ps-4 py-4">
                            <div class="d-flex align-items-center gap-2">
                                <span class="fs-5 fw-bold text-uppercase" style="letter-spacing: 1px;">
                                    {{ $isProfitable ? 'NET INCOME' : 'NET LOSS' }}
                                </span>
                                <span class="badge {{ $isProfitable ? 'bg-success' : 'bg-danger' }} rounded-pill px-3 py-1.5 small">
                                    {{ $isProfitable ? 'Surplus' : 'Deficit' }}
                                </span>
                            </div>
                            <span class="text-white-50 small">Total Revenue minus Total Cost of Sales and Operating Expenses</span>
                        </td>
                        <td class="pe-4 py-4 text-end font-tabular fs-4 fw-bold {{ $isProfitable ? 'text-success' : 'text-danger' }}" style="border-bottom: 4px double #ffffff;">
                            <span class="fs-6 me-1 text-white-50">ETB</span>{{ number_format(abs($netIncome), 2) }}
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>

    </div>

    {{-- Official Print Signature Block --}}
    <x-print-signatures 
        preparedTitle="Prepared By" 
        preparedRole="Financial Accountant" 
        checkedTitle="Verified By" 
        checkedRole="Finance Head" 
        approvedTitle="Approved By" 
        approvedRole="General Manager" />
</div>
@endsection
