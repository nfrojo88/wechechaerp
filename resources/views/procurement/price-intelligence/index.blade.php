@extends('layouts.app')
@section('title', 'Price Intelligence & Market Trends')

@section('content')
<div class="container-fluid px-3 px-md-4 py-3">

    {{-- Page Header --}}
    <x-page-header 
        title="Price Intelligence & Market Trends" 
        subtitle="Benchmark supplier quotation rates and track commodity price trends across active procurement categories">
        <x-slot name="actions">
            <button class="btn btn-outline-secondary btn-sm rounded-pill px-3 shadow-xs" onclick="window.print()">
                <i class="fa-solid fa-print me-1"></i>Print Report
            </button>
        </x-slot>
    </x-page-header>

    @php
        $totalItems = count($priceData);
        $trendingUp = collect($priceData)->where('trend', 'up')->count();
        $trendingDown = collect($priceData)->where('trend', 'down')->count();
        $stable = $totalItems - $trendingUp - $trendingDown;
    @endphp

    {{-- KPI Row --}}
    <div class="row g-3 mb-4">
        <div class="col-xl-3 col-sm-6">
            <x-kpi-card 
                label="Tracked Commodities" 
                value="{{ $totalItems }}" 
                icon="fa-solid fa-chart-line" 
                variant="primary" 
                helpText="Materials with price history" />
        </div>
        <div class="col-xl-3 col-sm-6">
            <x-kpi-card 
                label="Trending Up (Inflation)" 
                value="{{ $trendingUp }}" 
                icon="fa-solid fa-arrow-trend-up" 
                variant="danger" 
                helpText="Suppliers raising prices" />
        </div>
        <div class="col-xl-3 col-sm-6">
            <x-kpi-card 
                label="Trending Down" 
                value="{{ $trendingDown }}" 
                icon="fa-solid fa-arrow-trend-down" 
                variant="success" 
                helpText="Recent rate decreases" />
        </div>
        <div class="col-xl-3 col-sm-6">
            <x-kpi-card 
                label="Stable Pricing" 
                value="{{ $stable }}" 
                icon="fa-solid fa-minus" 
                variant="secondary" 
                helpText="Unchanged in last cycle" />
        </div>
    </div>

    {{-- Market Price Comparisons Table --}}
    <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
        <div class="card-header bg-white border-bottom py-3 px-4 d-flex justify-content-between align-items-center">
            <h6 class="mb-0 fw-bold" style="color: var(--brand-900);">
                <i class="fa-solid fa-tags text-primary me-2"></i>Market Price Comparisons
            </h6>
            <span class="badge bg-light text-dark border px-3 py-1 font-monospace">
                {{ $totalItems }} Records
            </span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 wc-table-mobile-cards">
                    <thead class="table-light text-uppercase small text-muted" style="letter-spacing: 0.5px;">
                        <tr>
                            <th class="ps-4 py-3">Material / Product</th>
                            <th class="py-3 text-end">Current Avg. Price (ETB)</th>
                            <th class="py-3 text-center">Price Trend</th>
                            <th class="py-3 text-center">Available Suppliers</th>
                            <th class="pe-4 py-3 text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($priceData as $data)
                        <tr>
                            <td class="ps-4 py-3" data-label="Material">
                                <div class="fw-bold" style="color: var(--brand-900);">{{ $data['material'] }}</div>
                                <small class="text-muted">Procurement commodity</small>
                            </td>
                            <td class="py-3 text-end font-tabular fw-bold text-dark fs-6" data-label="Avg. Price">
                                ETB {{ number_format($data['current_price'], 2) }}
                            </td>
                            <td class="py-3 text-center" data-label="Trend">
                                @if($data['trend'] == 'up')
                                    <span class="badge bg-danger-subtle text-danger border border-danger-subtle px-2.5 py-1 rounded-pill">
                                        <i class="fa-solid fa-arrow-up me-1"></i>Trending Up
                                    </span>
                                @elseif($data['trend'] == 'down')
                                    <span class="badge bg-success-subtle text-success border border-success-subtle px-2.5 py-1 rounded-pill">
                                        <i class="fa-solid fa-arrow-down me-1"></i>Trending Down
                                    </span>
                                @else
                                    <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle px-2.5 py-1 rounded-pill">
                                        <i class="fa-solid fa-minus me-1"></i>Stable
                                    </span>
                                @endif
                            </td>
                            <td class="py-3 text-center" data-label="Suppliers">
                                <span class="badge bg-info-subtle text-info border border-info-subtle px-3 py-1 rounded-pill fw-semibold font-tabular">
                                    <i class="fa-solid fa-store me-1"></i>{{ $data['suppliers'] }} Suppliers
                                </span>
                            </td>
                            <td class="pe-4 py-3 text-end" data-label="Action">
                                <button class="btn btn-sm btn-outline-primary rounded-pill px-3 shadow-xs">
                                    <i class="fa-solid fa-magnifying-glass me-1"></i>View Vendors
                                </button>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="5" class="text-center py-5">
                                <x-empty-state 
                                    icon="fa-solid fa-chart-line" 
                                    title="No Price Intelligence Data Available" 
                                    message="Commodity price intelligence and trend curves will populate once purchase orders and vendor quotes are entered." />
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card-footer bg-light border-top py-3 px-4">
            <div class="d-flex align-items-center text-muted small">
                <i class="fa-solid fa-circle-info text-primary me-2 fs-6"></i>
                <span>This market intelligence data is aggregated dynamically from recent Purchase Orders, Proforma invoices, and Supplier Quotations.</span>
            </div>
        </div>
    </div>

</div>
@endsection
