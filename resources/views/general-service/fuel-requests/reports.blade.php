@extends('layouts.app')
@section('title', 'Fuel Control & Fleet Consumption Reports')

@section('content')
<div class="container-fluid px-3 px-md-4 py-3">

    {{-- Breadcrumbs & Header --}}
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-1 text-muted small">
                    <li class="breadcrumb-item"><a href="{{ route('dashboard.general_service') }}" class="text-decoration-none">General Service</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('vehicle-fuel-requests.index') }}" class="text-decoration-none">Fuel Requests</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Control &amp; Reports</li>
                </ol>
            </nav>
            <h1 class="h3 fw-bold mb-0 text-dark">
                <i class="fa-solid fa-chart-line text-primary me-2"></i>Fleet Fuel Consumption &amp; Control Reports
            </h1>
            <p class="text-muted small mb-0">Vehicle efficiency metrics: KM per Liter, cost per KM, monthly consumption, and abnormal consumption anomaly flags.</p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a href="{{ route('vehicle-fuel-requests.create') }}" class="btn btn-warning text-dark rounded-pill px-3 shadow-sm fw-semibold">
                <i class="fa-solid fa-plus me-1"></i>New Fuel Request
            </a>
            <a href="{{ route('vehicle-fuel-requests.index') }}" class="btn btn-outline-secondary rounded-pill px-3 shadow-sm">
                <i class="fa-solid fa-arrow-left me-1"></i>All Requests
            </a>
        </div>
    </div>

    {{-- KPI Cards Row --}}
    <div class="row g-3 mb-4">
        {{-- Total Fuel Expense (Month) --}}
        <div class="col-xl-3 col-md-6">
            <div class="card border-0 shadow-sm rounded-4 h-100 p-3" style="background: linear-gradient(135deg, #ecfdf5 0%, #d1fae5 100%); border-left: 4px solid #10b981 !important;">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Fuel Expense (This Month)</div>
                        <div class="h4 mb-0 fw-bold text-dark font-monospace">ETB {{ number_format($totalMonthlyExpense, 2) }}</div>
                        <small class="text-muted">Total fuel disbursed</small>
                    </div>
                    <div class="rounded-circle p-2 bg-success bg-opacity-25 text-success">
                        <i class="fa-solid fa-money-bill-wave fa-xl"></i>
                    </div>
                </div>
            </div>
        </div>

        {{-- Total Volume (Month) --}}
        <div class="col-xl-3 col-md-6">
            <div class="card border-0 shadow-sm rounded-4 h-100 p-3" style="background: linear-gradient(135deg, #fffbeb 0%, #fef3c7 100%); border-left: 4px solid #f59e0b !important;">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Total Volume (This Month)</div>
                        <div class="h4 mb-0 fw-bold text-dark font-monospace">{{ number_format($totalMonthlyLiters, 1) }} L</div>
                        <small class="text-muted">Diesel &amp; Benzine</small>
                    </div>
                    <div class="rounded-circle p-2 bg-warning bg-opacity-25 text-warning">
                        <i class="fa-solid fa-gas-pump fa-xl"></i>
                    </div>
                </div>
            </div>
        </div>

        {{-- Total Fleet Tracked --}}
        <div class="col-xl-3 col-md-6">
            <div class="card border-0 shadow-sm rounded-4 h-100 p-3" style="background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%); border-left: 4px solid #3b82f6 !important;">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Fleet Monitored</div>
                        <div class="h4 mb-0 fw-bold text-dark">{{ count($reportData) }} Units</div>
                        <small class="text-muted">Active company vehicles</small>
                    </div>
                    <div class="rounded-circle p-2 bg-primary bg-opacity-25 text-primary">
                        <i class="fa-solid fa-truck-pickup fa-xl"></i>
                    </div>
                </div>
            </div>
        </div>

        {{-- Flagged Consumption Anomalies --}}
        <div class="col-xl-3 col-md-6">
            <div class="card border-0 shadow-sm rounded-4 h-100 p-3" style="background: linear-gradient(135deg, #fef2f2 0%, #fee2e2 100%); border-left: 4px solid #ef4444 !important;">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">Consumption Flags</div>
                        <div class="h4 mb-0 fw-bold text-danger">{{ $flaggedVehiclesCount }} Flagged</div>
                        <small class="text-muted">> 25% drop vs own average</small>
                    </div>
                    <div class="rounded-circle p-2 bg-danger bg-opacity-25 text-danger">
                        <i class="fa-solid fa-triangle-exclamation fa-xl"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Main Control Table --}}
    <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
        <div class="card-header bg-white py-3 border-0 d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div>
                <h5 class="fw-bold mb-0 text-dark">
                    <i class="fa-solid fa-gauge text-primary me-2"></i>Vehicle Consumption &amp; Efficiency Matrix
                </h5>
                <small class="text-muted">Calculated from closed fuel station receipts and recorded odometer intervals</small>
            </div>
            <span class="badge bg-light text-dark border px-3 py-1">Auto-calculated</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" style="font-size: 0.88rem;">
                    <thead class="table-light text-muted small text-uppercase">
                        <tr>
                            <th class="ps-4 py-3">Vehicle</th>
                            <th class="py-3">Driver / Custodian</th>
                            <th class="py-3">Avg Mileage (KM/L)</th>
                            <th class="py-3">Avg Cost / KM</th>
                            <th class="py-3">Month Spend</th>
                            <th class="py-3">Efficiency Audit</th>
                            <th class="py-3">Routine Service</th>
                            <th class="py-3 pe-4 text-end">Action</th>
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
                            <td class="ps-4 py-3">
                                <a href="{{ route('general-service.vehicle-reminders.vehicle-detail', $v->id) }}" class="fw-bold text-dark text-decoration-none">
                                    {{ $v->plate_number ?: $v->unit_code }}
                                </a>
                                <div class="text-muted small">{{ $v->parentAsset?->name }} ({{ $v->brand }} {{ $v->model }})</div>
                            </td>
                            <td class="py-3">
                                @if($v->assignedEmployee)
                                    <span class="fw-semibold text-dark"><i class="fa-solid fa-user me-1 text-primary"></i>{{ $v->assignedEmployee->full_name }}</span>
                                @else
                                    <span class="text-muted fst-italic">Unassigned</span>
                                @endif
                            </td>
                            <td class="py-3">
                                @if($m['avg_km_per_liter'])
                                    <strong class="font-monospace text-dark fs-6">{{ $m['avg_km_per_liter'] }}</strong>
                                    <span class="text-muted small">KM/L</span>
                                @else
                                    <span class="text-muted small">— (Need receipts)</span>
                                @endif
                            </td>
                            <td class="py-3">
                                @if($m['avg_cost_per_km'])
                                    <strong class="font-monospace text-dark">ETB {{ number_format($m['avg_cost_per_km'], 2) }}</strong>
                                    <span class="text-muted small">/ KM</span>
                                @else
                                    <span class="text-muted small">—</span>
                                @endif
                            </td>
                            <td class="py-3">
                                <strong class="font-monospace text-success">ETB {{ number_format($m['monthly_fuel_cost'], 2) }}</strong>
                                <div class="text-muted small">{{ number_format($row['monthly_liters'], 1) }} Liters</div>
                            </td>
                            <td class="py-3">
                                @if($m['is_efficiency_flag'])
                                    <span class="badge bg-danger rounded-pill px-2 py-1">
                                        <i class="fa-solid fa-triangle-exclamation me-1"></i>Drop -{{ $m['efficiency_drop'] }}%
                                    </span>
                                    <div class="text-danger small" style="font-size:0.75rem;">Check for fuel leaks or tuning</div>
                                @elseif($m['has_data'])
                                    <span class="badge bg-success bg-opacity-25 text-success-emphasis border border-success border-opacity-25 px-2 py-1">
                                        <i class="fa-solid fa-circle-check me-1"></i>Normal Range
                                    </span>
                                @else
                                    <span class="badge bg-light text-muted border px-2 py-1">No Data</span>
                                @endif
                            </td>
                            <td class="py-3">
                                <span class="badge bg-{{ $s['badge']['class'] }} px-2 py-1">
                                    <i class="fa-solid {{ $s['badge']['icon'] }} me-1"></i>{{ $s['badge']['label'] }}
                                </span>
                            </td>
                            <td class="py-3 pe-4 text-end">
                                <div class="btn-group btn-group-sm">
                                    <a href="{{ route('general-service.vehicle-reminders.vehicle-detail', $v->id) }}" class="btn btn-outline-secondary px-2 py-1" title="Vehicle Details Hub">
                                        <i class="fa-solid fa-eye"></i>
                                    </a>
                                    <a href="{{ route('vehicle-fuel-requests.create', ['vehicle_id' => $v->id]) }}" class="btn btn-warning btn-sm text-dark px-2 py-1" title="Request Fuel">
                                        <i class="fa-solid fa-gas-pump"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="8" class="text-center py-5 text-muted">
                                <i class="fa-solid fa-car-side fa-3x text-secondary opacity-50 mb-2 d-block"></i>
                                <strong>No vehicles registered in fleet catalog.</strong>
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
