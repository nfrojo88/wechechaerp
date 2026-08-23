@extends('layouts.app')

@section('title', 'Marketing & Price Intelligence Dashboard')

@push('styles')
<style>
.stat-card {
    border: none;
    border-radius: 14px;
    box-shadow: 0 4px 18px rgba(0,0,0,0.05);
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}
.stat-card:hover { transform: translateY(-2px); box-shadow: 0 8px 24px rgba(0,0,0,0.08); }
.stat-icon {
    width: 48px; height: 48px;
    border-radius: 12px;
    display: inline-flex; align-items: center; justify-content: center;
    font-size: 20px;
}
.badge-indicator-increase { background: #fee2e2; color: #dc2626; font-weight: 700; }
.badge-indicator-decrease { background: #dcfce7; color: #16a34a; font-weight: 700; }
.badge-indicator-no_change { background: #f1f5f9; color: #64748b; font-weight: 600; }
</style>
@endpush

@section('content')
<div class="container-fluid px-4 py-3">

    {{-- Page Header --}}
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h1 class="h3 fw-bold text-dark mb-1">
                <i class="fa-solid fa-bullhorn text-primary me-2"></i>Marketing &amp; Price Intelligence Dashboard
            </h1>
            <p class="text-muted small mb-0">Track market price trends, monthly inflation metrics, and cost intelligence.</p>
        </div>
        <div class="d-flex gap-2">
            @if(auth()->check() && (auth()->user()->hasRole('marketing') || auth()->user()->hasRole('admin') || auth()->user()->hasRole('global_admin')))
            <a href="{{ route('marketing.prices.create') }}" class="btn btn-primary btn-sm fw-semibold">
                <i class="fa-solid fa-plus-circle me-1"></i>New Price Update
            </a>
            @endif
            <a href="{{ route('marketing.prices.history') }}" class="btn btn-outline-secondary btn-sm fw-semibold">
                <i class="fa-solid fa-history me-1"></i>Price History
            </a>
        </div>
    </div>

    {{-- Summary Cards --}}
    <div class="row g-3 mb-4">
        {{-- Total Materials Tracked --}}
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card stat-card bg-white p-3">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <span class="text-muted small fw-semibold text-uppercase d-block mb-1">Tracked Materials</span>
                        <h3 class="fw-bold mb-0 text-dark">{{ number_format($totalTracked) }}</h3>
                    </div>
                    <span class="stat-icon bg-primary bg-opacity-10 text-primary">
                        <i class="fa-solid fa-boxes-stacked"></i>
                    </span>
                </div>
            </div>
        </div>

        {{-- Materials Increased This Month --}}
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card stat-card bg-white p-3">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <span class="text-muted small fw-semibold text-uppercase d-block mb-1">Price Increases</span>
                        <h3 class="fw-bold mb-0 text-danger">{{ number_format($materialsIncreased) }}</h3>
                    </div>
                    <span class="stat-icon bg-danger bg-opacity-10 text-danger">
                        <i class="fa-solid fa-arrow-trend-up"></i>
                    </span>
                </div>
            </div>
        </div>

        {{-- Avg Inflation % This Month --}}
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card stat-card bg-white p-3">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <span class="text-muted small fw-semibold text-uppercase d-block mb-1">Avg Inflation Rate</span>
                        <h3 class="fw-bold mb-0 {{ $avgInflation > 0 ? 'text-danger' : 'text-success' }}">
                            {{ $avgInflation > 0 ? '+' : '' }}{{ $avgInflation }}%
                        </h3>
                    </div>
                    <span class="stat-icon bg-warning bg-opacity-10 text-warning">
                        <i class="fa-solid fa-chart-line"></i>
                    </span>
                </div>
            </div>
        </div>

        {{-- Top Spike Material --}}
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card stat-card bg-white p-3">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <span class="text-muted small fw-semibold text-uppercase d-block mb-1">Top Spike Material</span>
                        <h5 class="fw-bold mb-0 text-dark text-truncate" style="max-width: 140px;">
                            {{ $topIncreases->first()['name'] ?? 'None' }}
                        </h5>
                        @if($topIncreases->first())
                        <small class="text-danger fw-bold">+{{ $topIncreases->first()['pct_change'] }}% MoM</small>
                        @endif
                    </div>
                    <span class="stat-icon bg-info bg-opacity-10 text-info">
                        <i class="fa-solid fa-fire text-danger"></i>
                    </span>
                </div>
            </div>
        </div>
    </div>

    {{-- Top Price Increases & Trend Chart Row --}}
    <div class="row g-3 mb-4">
        {{-- Top Increases List --}}
        <div class="col-lg-5">
            <div class="card border-0 shadow-sm rounded-3 h-100">
                <div class="card-header bg-transparent py-3 d-flex align-items-center justify-content-between">
                    <h6 class="mb-0 fw-bold text-dark">
                        <i class="fa-solid fa-fire text-danger me-2"></i>Top Price Increases (This Month)
                    </h6>
                    <a href="{{ route('marketing.reports.inflation') }}" class="small fw-semibold text-primary">View All</a>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table align-middle mb-0">
                            <thead class="bg-light text-muted small">
                                <tr>
                                    <th>Material</th>
                                    <th>Latest Price</th>
                                    <th class="text-end">Change</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($topIncreases as $inc)
                                <tr>
                                    <td>
                                        <span class="fw-semibold text-dark d-block">{{ $inc['name'] }}</span>
                                        <small class="text-muted">{{ $inc['unit'] }} · {{ $inc['category'] }}</small>
                                    </td>
                                    <td>
                                        <span class="fw-bold">ETB {{ number_format($inc['latest_price'], 2) }}</span>
                                    </td>
                                    <td class="text-end">
                                        <span class="badge badge-indicator-increase">
                                            🔺 +{{ $inc['pct_change'] }}%
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
            <div class="card border-0 shadow-sm rounded-3 h-100">
                <div class="card-header bg-transparent py-3">
                    <h6 class="mb-0 fw-bold text-dark">
                        <i class="fa-solid fa-chart-area text-primary me-2"></i>6-Month Market Price Trends
                    </h6>
                </div>
                <div class="card-body">
                    <canvas id="priceTrendChart" height="220"></canvas>
                </div>
            </div>
        </div>
    </div>

    {{-- Material Market Price Overview Table --}}
    <div class="card border-0 shadow-sm rounded-3">
        <div class="card-header bg-transparent py-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
            <h6 class="mb-0 fw-bold text-dark">
                <i class="fa-solid fa-table-list me-2 text-primary"></i>Material Market Prices &amp; Inflation Comparison
            </h6>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light text-muted small">
                        <tr>
                            <th>Material Name</th>
                            <th>Category</th>
                            <th>UM</th>
                            <th class="text-end">Previous Price</th>
                            <th class="text-end">Latest Market Price</th>
                            <th class="text-center">MoM Change</th>
                            <th class="text-center">Indicator</th>
                            <th>Last Updated</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($materialSummaries as $mat)
                        <tr>
                            <td class="fw-bold text-dark">{{ $mat['name'] }}</td>
                            <td><span class="badge bg-secondary bg-opacity-10 text-secondary">{{ $mat['category'] }}</span></td>
                            <td><code>{{ $mat['unit'] }}</code></td>
                            <td class="text-end text-muted">ETB {{ number_format($mat['prev_price'], 2) }}</td>
                            <td class="text-end fw-bold text-primary">ETB {{ number_format($mat['latest_price'], 2) }}</td>
                            <td class="text-center fw-bold">
                                @if($mat['pct_change'] > 0)
                                    <span class="text-danger">+{{ $mat['pct_change'] }}%</span>
                                @elseif($mat['pct_change'] < 0)
                                    <span class="text-success">{{ $mat['pct_change'] }}%</span>
                                @else
                                    <span class="text-muted">0.00%</span>
                                @endif
                            </td>
                            <td class="text-center">
                                @if($mat['indicator'] === 'increase')
                                    <span class="badge badge-indicator-increase">🔺 Inflation Spike</span>
                                @elseif($mat['indicator'] === 'decrease')
                                    <span class="badge badge-indicator-decrease">🔻 Price Drop</span>
                                @else
                                    <span class="badge badge-indicator-no_change">➖ Stable</span>
                                @endif
                            </td>
                            <td class="small text-muted">{{ $mat['last_updated'] }}</td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="8" class="text-center text-muted py-4">No materials found.</td>
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
<script>
document.addEventListener('DOMContentLoaded', function() {
    const ctx = document.getElementById('priceTrendChart').getContext('2d');
    const months = {!! json_encode($months ?? []) !!};
    const datasetsRaw = {!! json_encode($chartDatasets ?? []) !!};

    const colors = ['#2563eb', '#dc2626', '#16a34a', '#d97706'];
    const datasets = datasetsRaw.map((ds, idx) => ({
        label: ds.label,
        data: ds.data,
        borderColor: colors[idx % colors.length],
        backgroundColor: colors[idx % colors.length] + '1a',
        tension: 0.3,
        fill: true
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
                        callback: function(val) { return 'ETB ' + val; }
                    }
                }
            }
        }
    });
});
</script>
@endpush
