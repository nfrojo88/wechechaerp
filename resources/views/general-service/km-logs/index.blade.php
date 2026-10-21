@extends('layouts.app')
@section('title', 'Vehicle KM Logs — Odometer Tracking')

@section('content')
<div class="container-fluid px-3 px-md-4 py-3">

    {{-- Breadcrumbs & Header --}}
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-1 text-muted small">
                    <li class="breadcrumb-item"><a href="{{ route('dashboard.general_service') }}" class="text-decoration-none">General Service</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('general-service.vehicle-reminders.dashboard') }}" class="text-decoration-none">Vehicles</a></li>
                    <li class="breadcrumb-item active" aria-current="page">KM Logs</li>
                </ol>
            </nav>
            <h1 class="h3 fw-bold mb-0 text-dark">
                <i class="fa-solid fa-gauge-high text-primary me-2"></i>Vehicle Odometer &amp; KM Logs
            </h1>
            <p class="text-muted small mb-0">Record daily meter readings, track KM jumps, and automatically synchronize routine service intervals.</p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a href="{{ route('vehicle-km-logs.create') }}" class="btn btn-primary rounded-pill px-3 shadow-sm fw-semibold">
                <i class="fa-solid fa-plus me-1"></i>Record KM Log
            </a>
            <a href="{{ route('vehicle-fuel-requests.index') }}" class="btn btn-outline-warning text-dark rounded-pill px-3 shadow-sm fw-semibold">
                <i class="fa-solid fa-gas-pump me-1"></i>Fuel Requests
            </a>
            <a href="{{ route('general-service.vehicle-reminders.dashboard') }}" class="btn btn-outline-secondary rounded-pill px-3 shadow-sm">
                <i class="fa-solid fa-arrow-left me-1"></i>Reminders Hub
            </a>
        </div>
    </div>

    {{-- Alerts --}}
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm rounded-4 mb-4" role="alert">
            <i class="fa-solid fa-circle-check me-2"></i>{{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm rounded-4 mb-4" role="alert">
            <i class="fa-solid fa-triangle-exclamation me-2"></i>{{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    {{-- Filter Card --}}
    <div class="card border-0 shadow-sm rounded-4 mb-4">
        <div class="card-body p-3">
            <form method="GET" action="{{ route('vehicle-km-logs.index') }}" class="row g-2 align-items-end">
                <div class="col-md-3">
                    <label class="form-label text-muted small mb-1">Select Vehicle</label>
                    <select name="vehicle_id" class="form-select form-select-sm">
                        <option value="">— All Accessible Vehicles —</option>
                        @foreach($vehicles as $v)
                            <option value="{{ $v->id }}" {{ request('vehicle_id') == $v->id ? 'selected' : '' }}>
                                {{ $v->plate_number ? $v->plate_number . ' (' . $v->unit_code . ')' : $v->unit_code }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label text-muted small mb-1">Source</label>
                    <select name="source" class="form-select form-select-sm">
                        <option value="">— All Sources —</option>
                        <option value="direct_log" {{ request('source') === 'direct_log' ? 'selected' : '' }}>Direct KM Log</option>
                        <option value="fuel_request" {{ request('source') === 'fuel_request' ? 'selected' : '' }}>Fuel Request</option>
                        <option value="maintenance" {{ request('source') === 'maintenance' ? 'selected' : '' }}>Maintenance</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label text-muted small mb-1">Date From</label>
                    <input type="date" name="date_from" value="{{ request('date_from') }}" class="form-control form-select-sm">
                </div>
                <div class="col-md-2">
                    <label class="form-label text-muted small mb-1">Date To</label>
                    <input type="date" name="date_to" value="{{ request('date_to') }}" class="form-control form-select-sm">
                </div>
                <div class="col-md-2">
                    <label class="form-label text-muted small mb-1">Search</label>
                    <input type="text" name="search" value="{{ request('search') }}" placeholder="Plate, driver, notes..." class="form-control form-select-sm">
                </div>
                <div class="col-md-1 d-flex gap-1">
                    <button type="submit" class="btn btn-sm btn-primary w-100 rounded-pill"><i class="fa-solid fa-filter"></i></button>
                    @if(request()->hasAny(['vehicle_id', 'source', 'date_from', 'date_to', 'search']))
                        <a href="{{ route('vehicle-km-logs.index') }}" class="btn btn-sm btn-outline-secondary rounded-pill" title="Clear Filters"><i class="fa-solid fa-xmark"></i></a>
                    @endif
                </div>
            </form>
        </div>
    </div>

    {{-- KM Logs Table Card --}}
    <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
        <div class="card-header bg-white py-3 border-0 d-flex justify-content-between align-items-center">
            <h5 class="fw-bold mb-0 text-dark">
                <i class="fa-solid fa-list text-primary me-2"></i>Odometer Reading History
            </h5>
            <span class="badge bg-light text-dark border px-3 py-1">{{ $logs->total() }} Log Entries</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" style="font-size: 0.88rem;">
                    <thead class="table-light text-muted small text-uppercase">
                        <tr>
                            <th class="ps-4 py-3">Log Date</th>
                            <th class="py-3">Vehicle</th>
                            <th class="py-3">Odometer Reading</th>
                            <th class="py-3">KM Jump</th>
                            <th class="py-3">Source</th>
                            <th class="py-3">Logged By / Driver</th>
                            <th class="py-3">Photo / Receipt</th>
                            <th class="py-3 pe-4">Notes</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($logs as $log)
                        <tr>
                            <td class="ps-4 py-3">
                                <strong class="text-dark">{{ $log->km_date?->format('d M Y') }}</strong>
                                <div class="text-muted small" style="font-size: 0.75rem;">{{ $log->created_at?->diffForHumans() }}</div>
                            </td>
                            <td class="py-3">
                                <a href="{{ route('general-service.vehicle-reminders.vehicle-detail', $log->fixed_asset_unit_id) }}" class="fw-bold text-dark text-decoration-none">
                                    {{ $log->assetUnit?->plate_number ?: $log->assetUnit?->unit_code }}
                                </a>
                                <div class="text-muted small">{{ $log->assetUnit?->parentAsset?->name }}</div>
                            </td>
                            <td class="py-3">
                                <span class="badge bg-dark font-monospace fs-6 px-3 py-1">
                                    {{ number_format($log->odometer_km) }} KM
                                </span>
                            </td>
                            <td class="py-3">
                                @if($log->km_difference !== null && $log->km_difference > 0)
                                    <span class="badge {{ $log->is_large_jump ? 'bg-danger' : 'bg-info bg-opacity-25 text-info-emphasis border border-info border-opacity-25' }}">
                                        +{{ number_format($log->km_difference) }} KM
                                        @if($log->is_large_jump)
                                            <i class="fa-solid fa-triangle-exclamation ms-1" title="Unusually large single jump (>1,000 KM)"></i>
                                        @endif
                                    </span>
                                @else
                                    <span class="text-muted small">—</span>
                                @endif
                            </td>
                            <td class="py-3">
                                @php
                                    $srcBadge = match($log->source) {
                                        'fuel_request' => ['class' => 'bg-warning text-dark', 'icon' => 'fa-gas-pump', 'label' => 'Fuel Request'],
                                        'maintenance'  => ['class' => 'bg-danger text-white',  'icon' => 'fa-wrench',   'label' => 'Maintenance'],
                                        default        => ['class' => 'bg-secondary bg-opacity-25 text-secondary', 'icon' => 'fa-pen-to-square', 'label' => 'Direct Log'],
                                    };
                                @endphp
                                <span class="badge {{ $srcBadge['class'] }} px-2 py-1">
                                    <i class="fa-solid {{ $srcBadge['icon'] }} me-1"></i>{{ $srcBadge['label'] }}
                                </span>
                            </td>
                            <td class="py-3 text-muted small">
                                <div><i class="fa-solid fa-user me-1 text-primary"></i>{{ $log->loggedBy?->name ?? 'System' }}</div>
                                @if($log->driver)
                                    <div class="text-muted" style="font-size: 0.75rem;">Driver: {{ $log->driver->full_name }}</div>
                                @endif
                            </td>
                            <td class="py-3">
                                @if($log->photo_url)
                                    <a href="{{ $log->photo_url }}" target="_blank" class="btn btn-sm btn-outline-info rounded-pill px-2 py-0" style="font-size:0.75rem;">
                                        <i class="fa-solid fa-image me-1"></i>View Photo
                                    </a>
                                @else
                                    <span class="text-muted small">—</span>
                                @endif
                            </td>
                            <td class="py-3 pe-4 text-muted small">
                                {{ $log->notes ?: '—' }}
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="8" class="text-center py-5 text-muted">
                                <i class="fa-solid fa-gauge-high fa-3x text-secondary opacity-50 mb-2 d-block"></i>
                                <strong>No KM logs found.</strong>
                                <div class="small">Use the button above to record your first vehicle odometer reading.</div>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($logs->hasPages())
                <div class="p-3 border-top d-flex justify-content-end">
                    {{ $logs->links() }}
                </div>
            @endif
        </div>
    </div>

</div>
@endsection
