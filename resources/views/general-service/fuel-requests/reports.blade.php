@extends('layouts.app')
@section('title', 'Fuel Control & Fleet Consumption Reports')

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

    {{-- Breadcrumbs & Header --}}
    <x-page-header 
        title="Fleet Fuel Consumption & Control Reports" 
        subtitle="Vehicle efficiency metrics: KM per Liter, cost per KM, monthly consumption, and abnormal consumption anomaly flags">
        <x-slot name="actions">
            <a href="{{ route('vehicle-fuel-requests.create') }}" class="btn btn-warning text-dark btn-sm rounded-pill px-3 shadow-xs fw-bold">
                <i class="fa-solid fa-plus me-1"></i>New Fuel Request
            </a>
            <a href="{{ route('vehicle-fuel-requests.index') }}" class="btn btn-outline-secondary btn-sm rounded-pill px-3 shadow-xs">
                <i class="fa-solid fa-arrow-left me-1"></i>All Requests
            </a>
            <button class="btn btn-outline-secondary btn-sm rounded-pill px-3 shadow-xs wc-no-print" onclick="window.print()">
                <i class="fa-solid fa-print me-1"></i>Print
            </button>
        </x-slot>
    </x-page-header>

    {{-- KPI Cards Row --}}
    <div class="row g-3 mb-4">
        <div class="col-xl-3 col-sm-6">
            <x-kpi-card 
                label="Fuel Expense (This Month)" 
                value="ETB {{ number_format($totalMonthlyExpense, 2) }}" 
                icon="fa-solid fa-money-bill-wave" 
                variant="success" 
                helpText="Total fuel disbursed to fleet" />
        </div>
        <div class="col-xl-3 col-sm-6">
            <x-kpi-card 
                label="Total Volume (This Month)" 
                value="{{ number_format($totalMonthlyLiters, 1) }} L" 
                icon="fa-solid fa-gas-pump" 
                variant="warning" 
                helpText="Diesel &amp; Regular Benzine" />
        </div>
        <div class="col-xl-3 col-sm-6">
            <x-kpi-card 
                label="Fleet Monitored" 
                value="{{ count($reportData) }} Units" 
                icon="fa-solid fa-truck-pickup" 
                variant="primary" 
                helpText="Active company vehicles" />
        </div>
        <div class="col-xl-3 col-sm-6">
            <x-kpi-card 
                label="Consumption Flags" 
                value="{{ $flaggedVehiclesCount }} Flagged" 
                icon="fa-solid fa-triangle-exclamation" 
                variant="{{ $flaggedVehiclesCount > 0 ? 'danger' : 'success' }}" 
                helpText="> 25% drop vs own average" />
        </div>
    </div>

    {{-- Main Control Table --}}
    <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
        <div class="card-header bg-white py-3 px-4 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div>
                <h6 class="fw-bold mb-0" style="color: var(--brand-900);">
                    <i class="fa-solid fa-gauge text-primary me-2"></i>Vehicle Consumption &amp; Efficiency Matrix
                </h6>
                <small class="text-muted">Calculated from closed fuel station receipts and recorded odometer intervals</small>
            </div>
            <span class="badge bg-light text-dark border px-3 py-1 font-monospace">Auto-calculated</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 wc-table-mobile-cards">
                    <thead class="table-light text-muted small text-uppercase" style="letter-spacing: 0.5px;">
                        <tr>
                            <th class="ps-4 py-3">Vehicle</th>
                            <th class="py-3">Driver / Custodian</th>
                            <th class="py-3 text-end">Avg Mileage (KM/L)</th>
                            <th class="py-3 text-end">Avg Cost / KM</th>
                            <th class="py-3 text-end">Month Spend</th>
                            <th class="py-3 text-center">Efficiency Audit</th>
                            <th class="py-3 text-center">Routine Service</th>
                            <th class="pe-4 py-3 text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($reportData as $row)
                        @php
                            $v = $row['vehicle'];
                            $m = $row['metrics'];
                            $s = $row['service'];
                        @endphp
                        <tr class="{{ $m['is_efficiency_flag'] ? 'table-warning bg-opacity-25' : '' }}">
                            <td class="ps-4 py-3" data-label="Vehicle">
                                <a href="{{ route('general-service.vehicle-reminders.vehicle-detail', $v->id) }}" class="fw-bold text-decoration-none" style="color: var(--brand-900);">
                                    {{ $v->plate_number ?: $v->unit_code }}
                                </a>
                                <div class="text-muted small">{{ $v->parentAsset->name }} ({{ $v->brand }} {{ $v->model }})</div>
                            </td>
                            <td class="py-3" data-label="Custodian">
                                @if($v->assignedEmployee)
                                    <span class="fw-semibold text-dark"><i class="fa-solid fa-user me-1 text-primary"></i>{{ $v->assignedEmployee->full_name }}</span>
                                @else
                                    <span class="text-muted fst-italic">Unassigned</span>
                                @endif
                            </td>
                            <td class="py-3 text-end font-tabular" data-label="KM/L">
                                @if($m['avg_km_per_liter'])
                                    <strong class="text-dark fs-6">{{ $m['avg_km_per_liter'] }}</strong>
                                    <span class="text-muted small">KM/L</span>
                                @else
                                    <span class="text-muted small">—</span>
                                @endif
                            </td>
                            <td class="py-3 text-end font-tabular" data-label="Cost/KM">
                                @if($m['avg_cost_per_km'])
                                    <strong class="text-dark">ETB {{ number_format($m['avg_cost_per_km'], 2) }}</strong>
                                    <span class="text-muted small">/ KM</span>
                                @else
                                    <span class="text-muted small">—</span>
                                @endif
                            </td>
                            <td class="py-3 text-end font-tabular" data-label="Spend">
                                <strong class="text-success">ETB {{ number_format($m['monthly_fuel_cost'], 2) }}</strong>
                                <div class="text-muted small">{{ number_format($row['monthly_liters'], 1) }} L</div>
                            </td>
                            <td class="py-3 text-center" data-label="Efficiency">
                                @if($m['is_efficiency_flag'])
                                    <span class="badge bg-danger-subtle text-danger border border-danger-subtle rounded-pill px-2.5 py-1 fw-bold">
                                        <i class="fa-solid fa-triangle-exclamation me-1"></i>Drop -{{ $m['efficiency_drop'] }}%
                                    </span>
                                    <div class="text-danger small" style="font-size:0.75rem;">Check for fuel leaks or tuning</div>
                                @elseif($m['has_data'])
                                    <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-2.5 py-1">
                                        <i class="fa-solid fa-circle-check me-1"></i>Normal Range
                                    </span>
                                @else
                                    <span class="badge bg-light text-muted border rounded-pill px-2.5 py-1">No Data</span>
                                @endif
                            </td>
                            <td class="py-3 text-center" data-label="Service">
                                <span class="badge bg-{{ $s['badge']['class'] }}-subtle text-{{ $s['badge']['class'] }} border border-{{ $s['badge']['class'] }}-subtle rounded-pill px-2.5 py-1">
                                    <i class="fa-solid {{ $s['badge']['icon'] }} me-1"></i>{{ $s['badge']['label'] }}
                                </span>
                            </td>
                            <td class="pe-4 py-3 text-end" data-label="Action">
                                <div class="btn-group btn-group-sm">
                                    <a href="{{ route('general-service.vehicle-reminders.vehicle-detail', $v->id) }}" class="btn btn-outline-secondary rounded-pill px-2.5 py-1 me-1 shadow-xs" title="Vehicle Details Hub">
                                        <i class="fa-solid fa-eye"></i>
                                    </a>
                                    <a href="{{ route('vehicle-fuel-requests.create', ['vehicle_id' => $v->id]) }}" class="btn btn-warning btn-sm text-dark rounded-pill px-2.5 py-1 shadow-xs" title="Request Fuel">
                                        <i class="fa-solid fa-gas-pump"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="8" class="text-center py-5">
                                <x-empty-state 
                                    icon="fa-solid fa-car-side" 
                                    title="No Vehicles Registered in Fleet Catalog" 
                                    message="Fleet vehicles and equipment will appear here once added to General Service inventory." />
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
