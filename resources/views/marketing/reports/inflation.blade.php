@extends('layouts.app')

@section('title', 'Monthly Market Inflation Report')

@push('styles')
<style>
    @media print {
        .wc-no-print { display: none !important; }
        .card { border: 1px solid #e2e8f0 !important; box-shadow: none !important; }
    }
</style>
@endpush

@section('content')
<div class="container-fluid px-3 px-md-4 py-3">

    {{-- Page Header --}}
    <x-page-header 
        title="Monthly Market Inflation Report" 
        subtitle="Cumulative price changes and inflation volatility across tracked construction commodities">
        <x-slot name="actions">
            <a href="{{ route('marketing.dashboard') }}" class="btn btn-outline-secondary btn-sm rounded-pill px-3 shadow-xs">
                <i class="fa-solid fa-arrow-left me-1"></i>Back to Intelligence
            </a>
            <button class="btn btn-outline-secondary btn-sm rounded-pill px-3 shadow-xs wc-no-print" onclick="window.print()">
                <i class="fa-solid fa-print me-1"></i>Print Report
            </button>
        </x-slot>
    </x-page-header>

    @php
        $totalItems = count($reportData);
        $inflationItems = collect($reportData)->where('diff_amount', '>', 0)->count();
        $deflationItems = collect($reportData)->where('diff_amount', '<', 0)->count();
    @endphp

    {{-- KPI Cards Row --}}
    <div class="row g-3 mb-4">
        <div class="col-xl-3 col-sm-6">
            <x-kpi-card 
                label="Portfolio Avg Inflation" 
                value="{{ $avgInflationRate > 0 ? '+' : '' }}{{ $avgInflationRate }}%" 
                icon="fa-solid fa-chart-line" 
                variant="{{ $avgInflationRate > 5 ? 'danger' : ($avgInflationRate > 0 ? 'warning' : 'success') }}" 
                helpText="{{ $avgInflationRate > 5 ? 'High market volatility' : 'Moderate price movement' }}" />
        </div>
        <div class="col-xl-3 col-sm-6">
            <x-kpi-card 
                label="Tracked Commodities" 
                value="{{ $totalItems }}" 
                icon="fa-solid fa-boxes-stacked" 
                variant="primary" 
                helpText="Materials with price history" />
        </div>
        <div class="col-xl-3 col-sm-6">
            <x-kpi-card 
                label="Price Spikes / Increased" 
                value="{{ $inflationItems }}" 
                icon="fa-solid fa-arrow-trend-up" 
                variant="danger" 
                helpText="Surpassing initial baseline rates" />
        </div>
        <div class="col-xl-3 col-sm-6">
            <x-kpi-card 
                label="Price Decreases" 
                value="{{ $deflationItems }}" 
                icon="fa-solid fa-arrow-trend-down" 
                variant="success" 
                helpText="Supplying below baseline costs" />
        </div>
    </div>

    {{-- Inflation Breakdown Table --}}
    <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
        <div class="card-header bg-white border-bottom py-3 px-4 d-flex justify-content-between align-items-center">
            <h6 class="mb-0 fw-bold" style="color: var(--brand-900);">
                <i class="fa-solid fa-percent text-danger me-2"></i>Material-by-Material Inflation Breakdown
            </h6>
            <span class="badge bg-light text-dark border px-3 py-1 font-monospace">{{ $totalItems }} Records</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 wc-table-mobile-cards">
                    <thead class="table-light text-uppercase small text-muted" style="letter-spacing: 0.5px;">
                        <tr>
                            <th class="ps-4 py-3" style="width: 50px;">#</th>
                            <th class="py-3">Material Name</th>
                            <th class="py-3">Category</th>
                            <th class="py-3 text-center">Unit</th>
                            <th class="py-3 text-end">Earliest Price</th>
                            <th class="py-3 text-end">Latest Market Price</th>
                            <th class="py-3 text-end">Price Difference</th>
                            <th class="py-3 text-center">Cumulative Inflation %</th>
                            <th class="pe-4 py-3 text-end">Entries Tracked</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($reportData as $idx => $row)
                        <tr>
                            <td class="ps-4 py-3 text-muted font-tabular" data-label="#">{{ $idx + 1 }}</td>
                            <td class="py-3 fw-bold text-dark" data-label="Material">{{ $row['product_name'] }}</td>
                            <td class="py-3" data-label="Category">
                                <span class="badge bg-light text-dark border rounded-pill px-2.5 py-1">{{ $row['category'] }}</span>
                            </td>
                            <td class="py-3 text-center" data-label="Unit">
                                <code class="text-secondary">{{ $row['unit'] }}</code>
                            </td>
                            <td class="py-3 text-end font-tabular text-muted" data-label="Earliest">
                                ETB {{ number_format($row['initial_price'], 2) }}
                            </td>
                            <td class="py-3 text-end font-tabular fw-bold text-dark fs-6" data-label="Latest">
                                ETB {{ number_format($row['latest_price'], 2) }}
                            </td>
                            <td class="py-3 text-end font-tabular fw-semibold {{ $row['diff_amount'] > 0 ? 'text-danger' : ($row['diff_amount'] < 0 ? 'text-success' : 'text-muted') }}" data-label="Diff">
                                {{ $row['diff_amount'] > 0 ? '+' : '' }}ETB {{ number_format($row['diff_amount'], 2) }}
                            </td>
                            <td class="py-3 text-center font-tabular" data-label="Inflation %">
                                @if($row['pct_increase'] > 0)
                                    <span class="badge bg-danger-subtle text-danger border border-danger-subtle rounded-pill px-2.5 py-1 fw-bold">
                                        +{{ $row['pct_increase'] }}%
                                    </span>
                                @elseif($row['pct_increase'] < 0)
                                    <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-2.5 py-1 fw-bold">
                                        {{ $row['pct_increase'] }}%
                                    </span>
                                @else
                                    <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle rounded-pill px-2.5 py-1">
                                        0.00%
                                    </span>
                                @endif
                            </td>
                            <td class="pe-4 py-3 text-end" data-label="Entries">
                                <span class="badge bg-info-subtle text-info border border-info-subtle rounded-pill px-2.5 py-1 font-tabular">
                                    {{ $row['records_count'] }} updates
                                </span>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="9" class="text-center py-5">
                                <x-empty-state 
                                    icon="fa-solid fa-chart-line" 
                                    title="No Price History Records Available" 
                                    message="Commodity price logs are needed to compute cumulative market inflation rates." />
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
