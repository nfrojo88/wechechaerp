@extends('layouts.app')

@section('title', 'Planning vs Actual Cost Comparison Report')

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
        title="Planning vs Actual Cost Comparison" 
        subtitle="Two-mode variance audit: Planning Mode (Market Rates) vs. Actual Cost Mode (Purchase Invoices & Store Issues)">
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
        $totalPlans = count($comparisonData);
        $totalPlanned = collect($comparisonData)->sum('planned_material_cost');
        $totalActual = collect($comparisonData)->sum('actual_material_cost');
        $netVariance = $totalActual - $totalPlanned;
    @endphp

    {{-- Portfolio Variance KPIs --}}
    <div class="row g-3 mb-4">
        <div class="col-xl-3 col-sm-6">
            <x-kpi-card 
                label="Total Planned Cost" 
                value="ETB {{ number_format($totalPlanned, 2) }}" 
                icon="fa-solid fa-calculator" 
                variant="primary" 
                helpText="Based on projected market rates" />
        </div>
        <div class="col-xl-3 col-sm-6">
            <x-kpi-card 
                label="Total Actual Incurred" 
                value="ETB {{ number_format($totalActual, 2) }}" 
                icon="fa-solid fa-file-invoice-dollar" 
                variant="success" 
                helpText="Real paid invoices & store dispatches" />
        </div>
        <div class="col-xl-3 col-sm-6">
            <x-kpi-card 
                label="Net Portfolio Variance" 
                value="{{ ($netVariance > 0 ? '+' : '') . 'ETB ' . number_format($netVariance, 2) }}" 
                icon="fa-solid fa-scale-balanced" 
                variant="{{ $netVariance > 0 ? 'danger' : 'success' }}" 
                helpText="{{ $netVariance > 0 ? 'Actual exceeding planned budget' : 'Net savings achieved across plans' }}" />
        </div>
        <div class="col-xl-3 col-sm-6">
            <x-kpi-card 
                label="Audited Execution Plans" 
                value="{{ $totalPlans }}" 
                icon="fa-solid fa-list-check" 
                variant="info" 
                helpText="Active construction projects" />
        </div>
    </div>

    {{-- Mode Explanation Banner --}}
    <div class="row g-3 mb-4 wc-no-print">
        <div class="col-md-6">
            <div class="card border-0 shadow-sm rounded-4 border-start border-4 border-primary h-100 p-3 bg-white">
                <h6 class="fw-bold text-primary mb-1"><i class="fa-solid fa-calculator me-2"></i>A) Planning Mode (Market Rate)</h6>
                <p class="text-muted small mb-0">Estimates are calculated as <code>Material Standard × Quantity</code> using the <strong>latest market price</strong> from market research logs (<code>material_prices</code>), capturing current rate projections for new budgets.</p>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card border-0 shadow-sm rounded-4 border-start border-4 border-success h-100 p-3 bg-white">
                <h6 class="fw-bold text-success mb-1"><i class="fa-solid fa-file-invoice-dollar me-2"></i>B) Actual Project Cost Mode (Real Cost)</h6>
                <p class="text-muted small mb-0">Actual costs pull from real purchase invoice records (<code>purchase_order_items</code>) or store stock issue average costs (<code>inventory_movements</code>), ensuring store inventory items do <strong>not</strong> get artificially inflated.</p>
            </div>
        </div>
    </div>

    {{-- Comparison Report Table --}}
    <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
        <div class="card-header bg-white border-bottom py-3 px-4 d-flex justify-content-between align-items-center">
            <h6 class="mb-0 fw-bold" style="color: var(--brand-900);">
                <i class="fa-solid fa-list-check me-2 text-primary"></i>ERP Execution Plans Cost Variance
            </h6>
            <span class="badge bg-light text-dark border px-3 py-1 font-monospace">{{ $totalPlans }} Plans</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 wc-table-mobile-cards">
                    <thead class="table-light text-muted small text-uppercase" style="letter-spacing: 0.5px;">
                        <tr>
                            <th class="ps-4 py-3">Plan Name</th>
                            <th class="py-3">Project</th>
                            <th class="py-3 text-end" style="background:#f8faff;">Planning Cost (Market Rates)</th>
                            <th class="py-3 text-end" style="background:#f8fdf9;">Actual Cost (Real Paid/Store)</th>
                            <th class="py-3 text-end">Variance (ETB)</th>
                            <th class="py-3 text-center">Variance %</th>
                            <th class="pe-4 py-3 text-center">Costing Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($comparisonData as $row)
                        <tr>
                            <td class="ps-4 py-3" data-label="Plan">
                                <span class="fw-bold" style="color: var(--brand-900);">{{ $row['plan_name'] }}</span>
                                <small class="text-muted d-block font-monospace">Plan #{{ $row['plan_id'] }}</small>
                            </td>
                            <td class="py-3 fw-semibold text-secondary" data-label="Project">
                                {{ $row['project_name'] }}
                            </td>
                            <td class="py-3 text-end font-tabular fw-bold text-primary" style="background:#f8faff;" data-label="Planning Cost">
                                ETB {{ number_format($row['planned_material_cost'], 2) }}
                            </td>
                            <td class="py-3 text-end font-tabular fw-bold text-success" style="background:#f8fdf9;" data-label="Actual Cost">
                                ETB {{ number_format($row['actual_material_cost'], 2) }}
                            </td>
                            <td class="py-3 text-end font-tabular fw-bold {{ $row['variance'] > 0 ? 'text-danger' : ($row['variance'] < 0 ? 'text-success' : 'text-muted') }}" data-label="Variance">
                                {{ $row['variance'] > 0 ? '+' : '' }}ETB {{ number_format($row['variance'], 2) }}
                            </td>
                            <td class="py-3 text-center font-tabular fw-bold" data-label="Variance %">
                                {{ $row['variance_pct'] > 0 ? '+' : '' }}{{ $row['variance_pct'] }}%
                            </td>
                            <td class="pe-4 py-3 text-center" data-label="Status">
                                @if($row['variance'] < 0)
                                    <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-2.5 py-1 fw-bold">
                                        <i class="fa-solid fa-circle-check me-1"></i>Savings (Under Budget)
                                    </span>
                                @elseif($row['variance'] > 0)
                                    <span class="badge bg-danger-subtle text-danger border border-danger-subtle rounded-pill px-2.5 py-1 fw-bold">
                                        <i class="fa-solid fa-triangle-exclamation me-1"></i>Cost Overrun
                                    </span>
                                @else
                                    <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle rounded-pill px-2.5 py-1">
                                        Matched
                                    </span>
                                @endif
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="7" class="text-center py-5">
                                <x-empty-state 
                                    icon="fa-solid fa-scale-balanced" 
                                    title="No ERP Execution Plans Available" 
                                    message="ERP execution plans will appear here once projects have active BoQs and procurement allocations." />
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
