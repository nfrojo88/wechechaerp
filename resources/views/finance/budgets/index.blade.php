@extends('layouts.app')

@section('title', 'Project Budgets & Variance — Finance')

@section('content')
@php
    $totalBudgeted = $budgets->sum('budgeted_amount');
    $totalActual = $budgets->sum('actual_amount');
    $totalVariance = $totalBudgeted - $totalActual;
    $overallUtilization = $totalBudgeted > 0 ? ($totalActual / $totalBudgeted) * 100 : 0;
@endphp

<div class="container-fluid px-0">
    {{-- Page Header --}}
    <x-page-header
        title="Project Budgets &amp; Variance"
        subtitle="Monitor authorized project cost caps relatively against actual disbursements and vendor payments."
        :breadcrumbs="[
            ['label' => 'Dashboard', 'url' => route('dashboard')],
            ['label' => 'Finance', 'url' => route('dashboard.finance')],
            ['label' => 'Project Budgets']
        ]">
        <x-slot name="actions">
            <a href="{{ route('reports.index') }}" class="btn btn-outline-secondary btn-sm rounded-pill px-3 py-2 shadow-xs fw-semibold">
                <i class="fa-solid fa-arrow-left me-1.5"></i>Reports Hub
            </a>
            @hasanyrole('gm|admin|global_admin')
                <a href="{{ route('budgets.create') }}" class="btn btn-primary btn-sm rounded-pill px-3 py-2 shadow-xs fw-semibold">
                    <i class="fa-solid fa-plus me-1.5"></i>New Budget Cap
                </a>
            @endhasanyrole
        </x-slot>
    </x-page-header>

    {{-- Executive KPI Metrics --}}
    <div class="row g-3 mb-4">
        <div class="col-6 col-lg-3">
            <x-kpi-card
                title="Total Budgeted Cap"
                :value="'ETB ' . number_format($totalBudgeted, 2)"
                changeLabel="Approved GM cost allocations"
                icon="fa-solid fa-sack-dollar"
                color="primary" />
        </div>
        <div class="col-6 col-lg-3">
            <x-kpi-card
                title="Actual Disbursements"
                :value="'ETB ' . number_format($totalActual, 2)"
                changeLabel="Disbursed vendor & site payments"
                icon="fa-solid fa-money-bill-transfer"
                color="danger" />
        </div>
        <div class="col-6 col-lg-3">
            <x-kpi-card
                title="Net Remaining Balance"
                :value="'ETB ' . number_format(abs($totalVariance), 2)"
                :changeLabel="$totalVariance >= 0 ? 'Within budget cap' : 'Budget overrun variance'"
                :icon="$totalVariance >= 0 ? 'fa-solid fa-shield-check' : 'fa-solid fa-circle-exclamation'"
                :color="$totalVariance >= 0 ? 'success' : 'danger'" />
        </div>
        <div class="col-6 col-lg-3">
            <x-kpi-card
                title="Overall Utilization"
                :value="number_format($overallUtilization, 1) . '%'"
                changeLabel="Actual spend / Budgeted cap"
                icon="fa-solid fa-chart-pie"
                :color="$overallUtilization > 90 ? 'danger' : ($overallUtilization > 75 ? 'warning' : 'success')" />
        </div>
    </div>

    {{-- Budgets Data Table Card --}}
    <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
        <div class="card-header bg-white py-3 px-4 border-bottom d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div class="d-flex align-items-center gap-2">
                <span class="badge bg-primary bg-opacity-10 text-primary rounded-pill px-2.5 py-1 fw-bold small">
                    <i class="fa-solid fa-list-check me-1"></i>Active Budgets
                </span>
                <span class="text-muted small">Showing {{ $budgets->count() }} active project allocations</span>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 wc-table-mobile-cards">
                <thead class="table-light">
                    <tr>
                        <th class="ps-4 text-secondary text-uppercase small fw-bold">Project</th>
                        <th class="text-secondary text-uppercase small fw-bold">Cost Category</th>
                        <th class="text-secondary text-uppercase small fw-bold">Period Scope</th>
                        <th class="text-end text-secondary text-uppercase small fw-bold">Budgeted (ETB)</th>
                        <th class="text-end text-secondary text-uppercase small fw-bold">Actual Payments (ETB)</th>
                        <th class="text-end text-secondary text-uppercase small fw-bold">Variance (ETB)</th>
                        <th class="text-secondary text-uppercase small fw-bold" style="min-width: 160px;">Utilization</th>
                        @hasanyrole('gm|admin|global_admin')
                            <th class="pe-4 text-center text-secondary text-uppercase small fw-bold" style="width: 100px;">Actions</th>
                        @endhasanyrole
                    </tr>
                </thead>
                <tbody>
                    @forelse($budgets as $b)
                        @php
                            $utilization = $b->budgeted_amount > 0 ? ($b->actual_amount / $b->budgeted_amount) * 100 : 0;
                            $utilizationClamped = min(100, max(0, $utilization));
                            
                            $progressColor = 'bg-success';
                            if($utilization > 75) $progressColor = 'bg-warning';
                            if($utilization > 90) $progressColor = 'bg-danger';
                        @endphp
                        <tr>
                            <td class="ps-4" data-label="Project">
                                <div class="fw-bold text-dark">{{ $b->project->name ?? 'General Enterprise' }}</div>
                                @if($b->project->code)
                                    <span class="badge bg-light text-muted border font-monospace" style="font-size: 0.7rem;">{{ $b->project->code }}</span>
                                @endif
                            </td>
                            <td data-label="Category">
                                <span class="badge bg-secondary bg-opacity-10 text-secondary rounded-pill px-2.5 py-1 fw-semibold small">
                                    {{ $b->category }}
                                </span>
                            </td>
                            <td data-label="Period Scope">
                                <div class="fw-medium text-dark small">{{ ucfirst($b->period_type) }}</div>
                                <span class="text-muted small" style="font-size: 0.75rem;">
                                    {{ $b->period_start ? $b->period_start->format('M Y') : 'Overall Lifecycle' }}
                                </span>
                            </td>
                            <td class="text-end font-tabular fw-semibold text-dark" data-label="Budgeted (ETB)">
                                {{ number_format($b->budgeted_amount, 2) }}
                            </td>
                            <td class="text-end font-tabular fw-semibold text-primary" data-label="Actual (ETB)">
                                {{ number_format($b->actual_amount, 2) }}
                            </td>
                            <td class="text-end font-tabular fw-bold {{ $b->variance < 0 ? 'text-danger' : 'text-success' }}" data-label="Variance (ETB)">
                                {{ $b->variance < 0 ? '-' : '+' }}{{ number_format(abs($b->variance), 2) }}
                            </td>
                            <td data-label="Utilization">
                                <div class="d-flex justify-content-between align-items-center mb-1 small">
                                    <span class="font-tabular fw-bold {{ $utilization > 90 ? 'text-danger' : 'text-dark' }}">
                                        {{ number_format($utilization, 1) }}%
                                    </span>
                                </div>
                                <div class="progress" style="height: 6px; background-color: #f1f5f9;">
                                    <div class="progress-bar {{ $progressColor }}" role="progressbar" style="width: {{ $utilizationClamped }}%"></div>
                                </div>
                            </td>
                            @hasanyrole('gm|admin|global_admin')
                                <td class="pe-4 text-center" data-label="Actions">
                                    <a href="{{ route('budgets.edit', $b) }}" class="btn btn-sm btn-outline-secondary rounded-pill px-2.5 py-1.5 shadow-xs" title="Edit Budget">
                                        <i class="fa-solid fa-pen-to-square text-primary"></i>
                                    </a>
                                </td>
                            @endhasanyrole
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ auth()->user()->hasAnyRole(['gm','admin','global_admin']) ? 8 : 7 }}" class="text-center py-5">
                                <x-empty-state
                                    title="No Project Budgets Found"
                                    description="No authorized expenditure caps have been recorded yet. The General Manager or Admin can create budgets for active construction projects."
                                    icon="fa-solid fa-chart-pie" />
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($budgets->hasPages())
            <div class="card-footer bg-white border-0 py-3 px-4 d-flex justify-content-between align-items-center flex-wrap gap-2">
                <span class="text-muted small">
                    Showing {{ $budgets->firstItem() ?? 0 }} to {{ $budgets->lastItem() ?? 0 }} of {{ $budgets->total() }} budgets
                </span>
                <div>
                    {{ $budgets->links() }}
                </div>
            </div>
        @endif
    </div>
</div>
@endsection
