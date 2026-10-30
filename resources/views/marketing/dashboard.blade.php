@extends('layouts.app')

@section('title', 'Marketing & Price Intelligence Dashboard')

@section('content')
<div class="container-fluid px-3 px-md-4 py-3">

    {{-- Page Header --}}
    <x-page-header 
        title="Marketing & Price Intelligence Dashboard" 
        subtitle="Track construction commodity market price trends, monthly inflation metrics, and cost intelligence">
        <x-slot name="actions">
            @if(auth()->check() && (auth()->user()->hasRole('marketing') || auth()->user()->hasRole('admin') || auth()->user()->hasRole('global_admin')))
            <a href="{{ route('marketing.prices.create') }}" class="btn btn-primary btn-sm rounded-pill px-3 shadow-xs fw-bold">
                <i class="fa-solid fa-plus-circle me-1"></i>New Price Update
            </a>
            @endif
            <a href="{{ route('marketing.prices.history') }}" class="btn btn-outline-secondary btn-sm rounded-pill px-3 shadow-xs">
                <i class="fa-solid fa-clock-rotate-left me-1"></i>Price History
            </a>
            <button class="btn btn-outline-secondary btn-sm rounded-pill px-3 shadow-xs" onclick="window.print()">
                <i class="fa-solid fa-print me-1"></i>Print
            </button>
        </x-slot>
    </x-page-header>

    {{-- Summary KPIs --}}
    <div class="row g-3 mb-4">
        <div class="col-xl-3 col-sm-6">
            <x-kpi-card 
                label="Tracked Materials" 
                value="{{ number_format($totalTracked) }}" 
                icon="fa-solid fa-boxes-stacked" 
                variant="primary" 
                helpText="Active commodities monitored" />
        </div>
        <div class="col-xl-3 col-sm-6">
            <x-kpi-card 
                label="Price Increases (Month)" 
                value="{{ number_format($materialsIncreased) }}" 
                icon="fa-solid fa-arrow-trend-up" 
                variant="danger" 
                helpText="Materials experiencing price hikes" />
        </div>
        <div class="col-xl-3 col-sm-6">
            <x-kpi-card 
                label="Avg Inflation Rate" 
                value="{{ $avgInflation > 0 ? '+' : '' }}{{ $avgInflation }}%" 
                icon="fa-solid fa-chart-line" 
                variant="{{ $avgInflation > 0 ? 'warning' : 'success' }}" 
                helpText="MoM portfolio weighted shift" />
        </div>
        <div class="col-xl-3 col-sm-6">
            @php $topSpike = $topIncreases->first(); @endphp
            <x-kpi-card 
                label="Top Spike Material" 
                value="{{ $topSpike['name'] ?? 'None' }}" 
                icon="fa-solid fa-fire" 
                variant="danger" 
                helpText="{{ $topSpike ? '+' . $topSpike['pct_change'] . '% MoM surge' : 'No spike detected' }}" />
        </div>
    </div>

    {{-- Top Price Increases & Trend Chart Row --}}
    <div class="row g-3 mb-4">
        {{-- Top Increases List --}}
        <div class="col-lg-5">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-header bg-white border-bottom py-3 px-4 d-flex align-items-center justify-content-between">
                    <h6 class="mb-0 fw-bold" style="color: var(--brand-900);">
                        <i class="fa-solid fa-fire text-danger me-2"></i>Top Price Increases (This Month)
                    </h6>
                    <a href="{{ route('marketing.reports.inflation') }}" class="small fw-semibold text-primary text-decoration-none">
                        View Full Report <i class="fa-solid fa-arrow-right ms-1"></i>
                    </a>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light text-muted small text-uppercase" style="letter-spacing: 0.5px;">
                                <tr>
                                    <th class="ps-4 py-2.5">Material</th>
                                    <th class="py-2.5 text-end">Latest Price</th>
                                    <th class="pe-4 py-2.5 text-end">Change</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($topIncreases as $inc)
                                <tr>
                                    <td class="ps-4 py-2.5">
                                        <span class="fw-semibold text-dark d-block">{{ $inc['name'] }}</span>
                                        <small class="text-muted">{{ $inc['unit'] }} · {{ $inc['category'] }}</small>
                                    </td>
                                    <td class="py-2.5 text-end font-tabular fw-bold text-dark">
                                        ETB {{ number_format($inc['latest_price'], 2) }}
                                    </td>
                                    <td class="pe-4 py-2.5 text-end font-tabular">
                                        <span class="badge bg-danger-subtle text-danger border border-danger-subtle px-2.5 py-1 rounded-pill fw-bold">
                                            +{{ $inc['pct_change'] }}%
                                        </span>
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="3" class="text-center text-muted py-4 small">No price increases recorded this month.</td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        {{-- 6-Month Price Trend Chart --}}
        <div class="col-lg-7">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-header bg-white border-bottom py-3 px-4">
                    <h6 class="mb-0 fw-bold" style="color: var(--brand-900);">
                        <i class="fa-solid fa-chart-area text-primary me-2"></i>6-Month Market Price Trends
                    </h6>
                </div>
                <div class="card-body p-4">
                    <canvas id="priceTrendChart" height="220"></canvas>
                </div>
            </div>
        </div>
    </div>

    {{-- Material Market Price Overview Table --}}
    <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
        <div class="card-header bg-white border-bottom py-3 px-4 d-flex justify-content-between align-items-center flex-wrap gap-2">
            <h6 class="mb-0 fw-bold" style="color: var(--brand-900);">
                <i class="fa-solid fa-table-list me-2 text-primary"></i>Material Market Prices &amp; Inflation Comparison
            </h6>
            <span class="badge bg-light text-dark border px-3 py-1 font-monospace">{{ count($materialSummaries) }} Commodities</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 wc-table-mobile-cards">
                    <thead class="table-light text-muted small text-uppercase" style="letter-spacing: 0.5px;">
                        <tr>
                            <th class="ps-4 py-3">Material Name</th>
                            <th class="py-3">Category</th>
                            <th class="py-3 text-center">Unit</th>
                            <th class="py-3 text-end">Previous Price</th>
                            <th class="py-3 text-end">Latest Market Price</th>
                            <th class="py-3 text-center">MoM Change</th>
                            <th class="py-3 text-center">Indicator</th>
                            <th class="pe-4 py-3 text-end">Last Updated</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($materialSummaries as $mat)
                        <tr>
                            <td class="ps-4 py-3" data-label="Material">
                                <span class="fw-bold" style="color: var(--brand-900);">{{ $mat['name'] }}</span>
                            </td>
                            <td class="py-3" data-label="Category">
                                <span class="badge bg-light text-dark border rounded-pill px-2.5 py-1">{{ $mat['category'] }}</span>
                            </td>
                            <td class="py-3 text-center" data-label="Unit">
                                <code class="text-secondary">{{ $mat['unit'] }}</code>
                            </td>
                            <td class="py-3 text-end font-tabular text-muted" data-label="Prev Price">
                                ETB {{ number_format($mat['prev_price'], 2) }}
                            </td>
                            <td class="py-3 text-end font-tabular fw-bold text-dark fs-6" data-label="Latest Price">
                                ETB {{ number_format($mat['latest_price'], 2) }}
                            </td>
                            <td class="py-3 text-center font-tabular fw-bold" data-label="MoM Change">
                                @if($mat['pct_change'] > 0)
                                    <span class="text-danger">+{{ $mat['pct_change'] }}%</span>
                                @elseif($mat['pct_change'] < 0)
                                    <span class="text-success">{{ $mat['pct_change'] }}%</span>
                                @else
                                    <span class="text-muted">0.00%</span>
                                @endif
                            </td>
                            <td class="py-3 text-center" data-label="Indicator">
                                @if($mat['indicator'] === 'increase')
                                    <span class="badge bg-danger-subtle text-danger border border-danger-subtle rounded-pill px-2.5 py-1 fw-bold">
                                        <i class="fa-solid fa-arrow-up me-1"></i>Spike
                                    </span>
                                @elseif($mat['indicator'] === 'decrease')
                                    <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-2.5 py-1 fw-bold">
                                        <i class="fa-solid fa-arrow-down me-1"></i>Drop
                                    </span>
                                @else
                                    <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle rounded-pill px-2.5 py-1">
                                        <i class="fa-solid fa-minus me-1"></i>Stable
                                    </span>
                                @endif
                            </td>
                            <td class="pe-4 py-3 text-end font-tabular small text-muted" data-label="Updated">
                                {{ $mat['last_updated'] }}
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="8" class="text-center py-5">
                                <x-empty-state 
                                    icon="fa-solid fa-bullhorn" 
                                    title="No Material Price Summaries Found" 
                                    message="Log commodity market surveys to track price shifts over time." />
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

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script type="application/json" id="chart-months-data">{!! json_encode($months ?? []) !!}</script>
<script type="application/json" id="chart-datasets-data">{!! json_encode($chartDatasets ?? []) !!}</script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const canvas = document.getElementById('priceTrendChart');
    if (!canvas) return;
    const ctx = canvas.getContext('2d');
    const months = JSON.parse(document.getElementById('chart-months-data')?.textContent || '[]');
    const datasetsRaw = JSON.parse(document.getElementById('chart-datasets-data')?.textContent || '[]');

    const colors = ['#0F2944', '#dc2626', '#16a34a', '#d97706', '#2563eb'];
    const datasets = datasetsRaw.map((ds, idx) => ({
        label: ds.label,
        data: ds.data,
        borderColor: colors[idx % colors.length],
        backgroundColor: colors[idx % colors.length] + '15',
        tension: 0.3,
        fill: true,
        borderWidth: 2
    }));

    new Chart(ctx, {
        type: 'line',
        data: {
            labels: months,
            datasets: datasets
        },
        options: {
            responsive: true,
            plugins: {
                legend: { position: 'bottom' }
            },
            scales: {
                y: {
                    beginAtZero: false,
                    ticks: {
                        callback: function(val) { return 'ETB ' + val.toLocaleString(); }
                    }
                }
            }
        }
    });
});
</script>
@endpush
