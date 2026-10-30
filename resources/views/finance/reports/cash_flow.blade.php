@extends('layouts.app')

@section('title', 'Cash Flow Statement (Indirect Method) — Financial Reports')

@section('content')
@php
    $netChangeInCash = $netCashOperating + $totalInvesting + $totalFinancing;
    $isCashPositive = $netChangeInCash >= 0;
@endphp

<div class="container-fluid px-0">
    <x-print-header title="Cash Flow Statement (Indirect Method)" :dateRange="\Carbon\Carbon::parse($startDate)->format('M d, Y') . ' to ' . \Carbon\Carbon::parse($endDate)->format('M d, Y')" />

    {{-- Page Header --}}
    <x-page-header
        title="Cash Flow Statement (Indirect Method)"
        subtitle="Analysis of operational cash flows, capital investment disbursements, and financing activities from {{ \Carbon\Carbon::parse($startDate)->format('M d, Y') }} to {{ \Carbon\Carbon::parse($endDate)->format('M d, Y') }}."
        :breadcrumbs="[
            ['label' => 'Dashboard', 'url' => route('dashboard')],
            ['label' => 'Finance', 'url' => route('dashboard.finance')],
            ['label' => 'Reports', 'url' => route('reports.index')],
            ['label' => 'Cash Flow']
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
            <form method="GET" action="{{ route('reports.cash-flow') }}" class="row g-2 align-items-end">
                <div class="col-12 col-sm-6 col-md-4 col-lg-3">
                    <label class="form-label text-muted small fw-bold text-uppercase mb-1" style="font-size: 0.72rem;">From Date</label>
                    <input type="date" name="start_date" class="form-control form-control-sm" value="{{ $startDate }}" required>
                </div>
                <div class="col-12 col-sm-6 col-md-4 col-lg-3">
                    <label class="form-label text-muted small fw-bold text-uppercase mb-1" style="font-size: 0.72rem;">To Date</label>
                    <input type="date" name="end_date" class="form-control form-control-sm" value="{{ $endDate }}" required>
                </div>
                <div class="col-12 col-md-4 col-lg-3 d-flex gap-2">
                    <button type="submit" class="btn btn-primary btn-sm rounded-pill px-4 py-2 w-100 fw-semibold shadow-xs">
                        <i class="fa-solid fa-filter me-1.5"></i>Generate Statement
                    </button>
                    <a href="{{ route('reports.cash-flow') }}" class="btn btn-outline-secondary btn-sm rounded-pill px-3 py-2 shadow-xs" title="Reset to YTD">
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
                title="Operating Cash Flow"
                :value="'ETB ' . number_format($netCashOperating, 2)"
                changeLabel="Net operations + working capital"
                icon="fa-solid fa-gears"
                :color="$netCashOperating >= 0 ? 'success' : 'danger'" />
        </div>
        <div class="col-6 col-lg-3">
            <x-kpi-card
                title="Investing Activities"
                :value="'ETB ' . number_format($totalInvesting, 2)"
                changeLabel="Fixed assets & equipment capex"
                icon="fa-solid fa-chart-line"
                color="warning" />
        </div>
        <div class="col-6 col-lg-3">
            <x-kpi-card
                title="Financing Activities"
                :value="'ETB ' . number_format($totalFinancing, 2)"
                changeLabel="Equity & long-term liabilities"
                icon="fa-solid fa-landmark"
                color="info" />
        </div>
        <div class="col-6 col-lg-3">
            <x-kpi-card
                :title="$isCashPositive ? 'Net Cash Increase' : 'Net Cash Decrease'"
                :value="'ETB ' . number_format(abs($netChangeInCash), 2)"
                :changeLabel="$isCashPositive ? 'Overall positive cash inflow' : 'Overall cash reduction'"
                :icon="$isCashPositive ? 'fa-solid fa-arrow-trend-up' : 'fa-solid fa-arrow-trend-down'"
                :color="$isCashPositive ? 'primary' : 'danger'" />
        </div>
    </div>

    {{-- Cash Flow Statement Master Table --}}
    <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4 wc-printable-area">
        {{-- Formal Print Header (Only visible on print) --}}
        <div class="d-none d-print-block p-4 border-bottom text-center">
            <h2 class="fw-bold mb-1" style="color: #0F2944;">WECHECHA CONSTRUCTION ERP</h2>
            <h4 class="text-secondary fw-semibold mb-1">Official Statement of Cash Flows (Indirect Method)</h4>
            <p class="text-muted small mb-0">
                Period: {{ \Carbon\Carbon::parse($startDate)->format('F j, Y') }} — {{ \Carbon\Carbon::parse($endDate)->format('F j, Y') }} | Currency: ETB
            </p>
        </div>

        <div class="table-responsive">
            <table class="table align-middle mb-0 wc-table-mobile-cards">
                <thead class="table-light">
                    <tr>
                        <th class="ps-4 py-3 text-secondary text-uppercase small fw-bold">Cash Flows from Operational &amp; Capital Activities</th>
                        <th class="pe-4 py-3 text-end text-secondary text-uppercase small fw-bold" style="width: 240px;">Amount (ETB)</th>
                    </tr>
                </thead>
                <tbody>
                    {{-- ── 1. OPERATING ACTIVITIES ── --}}
                    <tr class="table-light">
                        <td colspan="2" class="ps-4 py-3 fw-bold text-primary text-uppercase" style="font-size: 0.85rem; letter-spacing: 0.5px;">
                            <i class="fa-solid fa-gears me-2 text-primary"></i>1. Cash Flows from Operating Activities
                        </td>
                    </tr>
                    <tr>
                        <td class="ps-5" data-label="Activity">
                            <span class="fw-semibold text-dark">Net Profit / (Loss) for the Period</span>
                            <span class="text-muted small d-block">Operating result from Income Statement</span>
                        </td>
                        <td class="pe-4 text-end font-tabular fw-semibold {{ $netProfit >= 0 ? 'text-success' : 'text-danger' }}" data-label="Amount (ETB)">
                            {{ number_format($netProfit, 2) }}
                        </td>
                    </tr>

                    @if(count($operatingAdjustments) > 0)
                        <tr>
                            <td colspan="2" class="ps-5 text-muted fst-italic small py-2 bg-light bg-opacity-50">
                                Adjustments for changes in working capital accounts:
                            </td>
                        </tr>
                        @foreach($operatingAdjustments as $adj)
                            <tr>
                                <td class="ps-5" data-label="Activity">
                                    <span class="badge bg-secondary bg-opacity-10 text-secondary me-2 font-monospace">{{ $adj['code'] }}</span>
                                    <span class="text-dark">{{ $adj['name'] }}</span>
                                </td>
                                <td class="pe-4 text-end font-tabular fw-medium {{ $adj['amount'] >= 0 ? 'text-dark' : 'text-danger' }}" data-label="Amount (ETB)">
                                    {{ number_format($adj['amount'], 2) }}
                                </td>
                            </tr>
                        @endforeach
                    @endif

                    <tr class="table-success bg-opacity-10 fw-bold border-top border-bottom">
                        <td class="ps-4 py-3 text-uppercase small text-dark">
                            Net Cash Generated from / (Used in) Operating Activities
                        </td>
                        <td class="pe-4 py-3 text-end font-tabular fs-6 text-primary">
                            <span class="small text-muted me-1">ETB</span>{{ number_format($netCashOperating, 2) }}
                        </td>
                    </tr>

                    {{-- ── 2. INVESTING ACTIVITIES ── --}}
                    <tr class="table-light">
                        <td colspan="2" class="ps-4 py-3 fw-bold text-warning text-uppercase" style="font-size: 0.85rem; letter-spacing: 0.5px;">
                            <i class="fa-solid fa-chart-line me-2 text-warning"></i>2. Cash Flows from Investing Activities
                        </td>
                    </tr>
                    @forelse($investingAdjustments as $adj)
                        <tr>
                            <td class="ps-5" data-label="Activity">
                                <span class="badge bg-secondary bg-opacity-10 text-secondary me-2 font-monospace">{{ $adj['code'] }}</span>
                                <span class="text-dark">{{ $adj['name'] }}</span>
                            </td>
                            <td class="pe-4 text-end font-tabular fw-medium {{ $adj['amount'] >= 0 ? 'text-dark' : 'text-danger' }}" data-label="Amount (ETB)">
                                {{ number_format($adj['amount'], 2) }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="2" class="ps-5 text-muted fst-italic py-3">
                                No capital expenditures or fixed asset transactions during this period.
                            </td>
                        </tr>
                    @endforelse

                    <tr class="table-warning bg-opacity-10 fw-bold border-top border-bottom">
                        <td class="ps-4 py-3 text-uppercase small text-dark">
                            Net Cash Used in / (From) Investing Activities
                        </td>
                        <td class="pe-4 py-3 text-end font-tabular fs-6 text-dark">
                            <span class="small text-muted me-1">ETB</span>{{ number_format($totalInvesting, 2) }}
                        </td>
                    </tr>

                    {{-- ── 3. FINANCING ACTIVITIES ── --}}
                    <tr class="table-light">
                        <td colspan="2" class="ps-4 py-3 fw-bold text-info text-uppercase" style="font-size: 0.85rem; letter-spacing: 0.5px;">
                            <i class="fa-solid fa-landmark me-2 text-info"></i>3. Cash Flows from Financing Activities
                        </td>
                    </tr>
                    @forelse($financingAdjustments as $adj)
                        <tr>
                            <td class="ps-5" data-label="Activity">
                                <span class="badge bg-secondary bg-opacity-10 text-secondary me-2 font-monospace">{{ $adj['code'] }}</span>
                                <span class="text-dark">{{ $adj['name'] }}</span>
                            </td>
                            <td class="pe-4 text-end font-tabular fw-medium {{ $adj['amount'] >= 0 ? 'text-dark' : 'text-danger' }}" data-label="Amount (ETB)">
                                {{ number_format($adj['amount'], 2) }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="2" class="ps-5 text-muted fst-italic py-3">
                                No external financing, dividend distributions, or equity transfers during this period.
                            </td>
                        </tr>
                    @endforelse

                    <tr class="table-info bg-opacity-10 fw-bold border-top border-bottom">
                        <td class="ps-4 py-3 text-uppercase small text-dark">
                            Net Cash Provided by / (Used in) Financing Activities
                        </td>
                        <td class="pe-4 py-3 text-end font-tabular fs-6 text-dark">
                            <span class="small text-muted me-1">ETB</span>{{ number_format($totalFinancing, 2) }}
                        </td>
                    </tr>

                    {{-- ── 4. NET CHANGE IN CASH SUMMARY ── --}}
                    <tr class="bg-dark text-white border-top border-3">
                        <td class="ps-4 py-4">
                            <div class="d-flex align-items-center gap-2">
                                <span class="fs-5 fw-bold text-uppercase" style="letter-spacing: 1px;">
                                    NET {{ $isCashPositive ? 'INCREASE' : 'DECREASE' }} IN CASH &amp; CASH EQUIVALENTS
                                </span>
                                <span class="badge {{ $isCashPositive ? 'bg-success' : 'bg-danger' }} rounded-pill px-3 py-1.5 small">
                                    {{ $isCashPositive ? '+ Inflow' : '- Outflow' }}
                                </span>
                            </div>
                            <span class="text-white-50 small">Net sum of operating, investing, and financing cash flows</span>
                        </td>
                        <td class="pe-4 py-4 text-end font-tabular fs-4 fw-bold {{ $isCashPositive ? 'text-success' : 'text-danger' }}" style="border-bottom: 4px double #ffffff;">
                            <span class="fs-6 me-1 text-white-50">ETB</span>{{ number_format(abs($netChangeInCash), 2) }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

    </div>

    {{-- Official Print Signature Block --}}
    <x-print-signatures 
        preparedTitle="Prepared By" 
        preparedRole="Treasury & Cash Accountant" 
        checkedTitle="Verified By" 
        checkedRole="Finance Head" 
        approvedTitle="Approved By" 
        approvedRole="General Manager" />
</div>
@endsection
