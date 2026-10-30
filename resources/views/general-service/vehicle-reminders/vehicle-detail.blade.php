@extends('layouts.app')
@section('title', 'General Service — Vehicle Reminders Hub: ' . ($unit->plate_number ?: $unit->unit_code))

@section('content')
<div class="container-fluid px-3 px-md-4 py-3">

    {{-- Breadcrumbs & Header --}}
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-1 text-muted small">
                    <li class="breadcrumb-item"><a href="{{ route('dashboard.general_service') }}" class="text-decoration-none">General Service</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('general-service.vehicle-reminders.dashboard') }}" class="text-decoration-none">Vehicle Reminders</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Vehicle Hub: {{ $unit->plate_number ?: $unit->unit_code }}</li>
                </ol>
            </nav>
            <div class="d-flex align-items-center gap-2">
                <h1 class="h3 fw-bold mb-0 text-dark">{{ $unit->parentAsset->name ?? 'Vehicle' }}</h1>
                @if($unit->plate_number)
                    <span class="badge bg-dark text-white rounded-pill px-3 py-2 fs-6">
                        Plate: {{ $unit->plate_number }}
                    </span>
                @endif
            </div>
            <p class="text-muted small mb-0">Unit Code: <strong>{{ $unit->unit_code }}</strong> &bull; Complete compliance &amp; service reminders hub</p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a href="{{ route('vehicle-km-logs.create', ['vehicle_id' => $unit->id]) }}" class="btn btn-primary rounded-pill px-3 shadow-sm fw-semibold">
                <i class="fa-solid fa-gauge-high me-1"></i>Log KM
            </a>
            <a href="{{ route('vehicle-fuel-requests.create', ['vehicle_id' => $unit->id]) }}" class="btn btn-warning text-dark rounded-pill px-3 shadow-sm fw-semibold">
                <i class="fa-solid fa-gas-pump me-1"></i>Request Fuel
            </a>
            <a href="{{ route('general-service.vehicle-reminders.create', ['fixed_asset_unit_id' => $unit->id]) }}" class="btn btn-outline-primary rounded-pill px-3 shadow-sm fw-semibold">
                <i class="fa-solid fa-plus me-1"></i>Add Reminder
            </a>
            <a href="{{ route('general-service.vehicle-reminders.index') }}" class="btn btn-outline-secondary rounded-pill px-3 shadow-sm">
                <i class="fa-solid fa-arrow-left me-1"></i>All Reminders
            </a>
            <a href="{{ route('general-service.vehicle-reminders.dashboard') }}" class="btn btn-outline-secondary rounded-pill px-3 shadow-sm">
                <i class="fa-solid fa-chart-pie me-1"></i>Dashboard
            </a>
        </div>
    </div>

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

    {{-- Vehicle Overview Card --}}
    <div class="card border-0 shadow-sm rounded-4 mb-4 overflow-hidden">
        <div class="card-body p-4 bg-gradient" style="background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%); color: white;">
            <div class="row align-items-center g-3">
                <div class="col-md-8">
                    <span class="badge bg-warning text-dark rounded-pill px-3 py-1 mb-2 fw-semibold">
                        <i class="fa-solid fa-truck-pickup me-1"></i>Fleet Asset
                    </span>
                    <h2 class="fw-bold mb-1 text-white">{{ $unit->parentAsset->name }}</h2>
                    <div class="d-flex flex-wrap gap-3 align-items-center opacity-75 small mt-2">
                        <span><i class="fa-solid fa-barcode me-1"></i>Code: <strong>{{ $unit->unit_code }}</strong></span>
                        @if($unit->brand || $unit->model)
                            <span><i class="fa-solid fa-car-side me-1"></i>Model: <strong>{{ trim(($unit->brand ?? '') . ' ' . ($unit->model ?? '')) }}</strong></span>
                        @endif
                        @if($unit->year)
                            <span><i class="fa-solid fa-calendar me-1"></i>Year: <strong>{{ $unit->year }}</strong></span>
                        @endif
                        @if($unit->assignedEmployee)
                            <span><i class="fa-solid fa-user me-1"></i>Assigned: <strong>{{ $unit->assignedEmployee->name }}</strong></span>
                        @endif
                        @if($unit->current_location)
                            <span><i class="fa-solid fa-location-dot me-1"></i>Location: <strong>{{ $unit->current_location }}</strong></span>
                        @endif
                    </div>
                </div>
                <div class="col-md-4 text-md-end">
                    @if($unit->plate_number)
                        <div class="d-inline-block bg-white text-dark rounded-3 px-4 py-2 fw-bold fs-4 shadow-sm border border-2 border-warning" style="letter-spacing: 2px;">
                            {{ $unit->plate_number }}
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- 4 Reminder Category Cards for this Vehicle --}}
    @php
        $boloReminder = $reminders->firstWhere('reminder_type', 'bolo');
        $serviceReminder = $reminders->firstWhere('reminder_type', 'service_km');
        $thirdPartyReminder = $reminders->firstWhere('reminder_type', 'third_party_insurance');
        $compReminder = $reminders->firstWhere('reminder_type', 'insurance');
    @endphp

    <div class="row g-3 mb-4">
        {{-- 1. Bolo --}}
        <div class="col-xl-3 col-md-6">
            <div class="card border-0 shadow-sm rounded-4 h-100 bg-white">
                <div class="card-body p-3">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="text-muted small fw-semibold text-uppercase">Bolo Inspection</span>
                        <div class="bg-warning bg-opacity-10 text-warning rounded-circle d-flex align-items-center justify-content-center" style="width: 34px; height: 34px;">
                            <i class="fa-solid fa-stamp"></i>
                        </div>
                    </div>
                    @if($boloReminder)
                        @php
                            $bStatus = $boloReminder->computed_status;
                            $bExp = $boloReminder->bolo_expiry_date;
                            $bDays = $boloReminder->days_until_expiry;
                        @endphp
                        <div class="d-flex align-items-baseline gap-2">
                            <span class="fs-5 fw-bold text-dark">{{ $bExp ? $bExp->format('d M Y') : '—' }}</span>
                        </div>
                        <div class="mt-2 d-flex justify-content-between align-items-center">
                            <span class="badge bg-{{ $bStatus['class'] }} rounded-pill">{{ $bStatus['label'] }}</span>
                            <a href="{{ route('general-service.vehicle-reminders.show', $boloReminder) }}" class="btn btn-sm btn-link text-decoration-none p-0">Details &rarr;</a>
                        </div>
                    @else
                        <div class="py-2 text-muted small">No Bolo reminder set up.</div>
                        <a href="{{ route('general-service.vehicle-reminders.create', ['fixed_asset_unit_id' => $unit->id, 'reminder_type' => 'bolo']) }}" class="btn btn-sm btn-outline-primary rounded-pill px-3 mt-1">
                            + Set Up Bolo
                        </a>
                    @endif
                </div>
            </div>
        </div>

        {{-- 2. Service by KM --}}
        <div class="col-xl-3 col-md-6">
            <div class="card border-0 shadow-sm rounded-4 h-100 bg-white">
                <div class="card-body p-3">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="text-muted small fw-semibold text-uppercase">Service by KM</span>
                        <div class="bg-success bg-opacity-10 text-success rounded-circle d-flex align-items-center justify-content-center" style="width: 34px; height: 34px;">
                            <i class="fa-solid fa-gauge-high"></i>
                        </div>
                    </div>
                    @if($serviceReminder)
                        @php
                            $sStatus = $serviceReminder->computed_status;
                            $sKm = $serviceReminder->km_remaining;
                        @endphp
                        <div class="d-flex align-items-baseline gap-2">
                            <span class="fs-5 fw-bold text-dark">{{ number_format($serviceReminder->next_service_km) }} KM</span>
                        </div>
                        <small class="text-muted d-block">Current: {{ number_format($serviceReminder->current_odometer_km) }} KM</small>
                        <div class="mt-2 d-flex justify-content-between align-items-center">
                            <span class="badge bg-{{ $sStatus['class'] }} rounded-pill">{{ $sStatus['label'] }}</span>
                            <div class="d-flex gap-1">
                                <button type="button" class="btn btn-sm btn-outline-info rounded-pill px-2 py-0" style="font-size: 0.75rem;"
                                        data-bs-toggle="modal" data-bs-target="#unitOdometerModal">
                                    KM
                                </button>
                                <a href="{{ route('general-service.vehicle-reminders.show', $serviceReminder) }}" class="btn btn-sm btn-link text-decoration-none p-0">Details &rarr;</a>
                            </div>
                        </div>
                    @else
                        <div class="py-2 text-muted small">No Service schedule set up.</div>
                        <a href="{{ route('general-service.vehicle-reminders.create', ['fixed_asset_unit_id' => $unit->id, 'reminder_type' => 'service_km']) }}" class="btn btn-sm btn-outline-primary rounded-pill px-3 mt-1">
                            + Set Up Service
                        </a>
                    @endif
                </div>
            </div>
        </div>

        {{-- 3. Third-Party Insurance --}}
        <div class="col-xl-3 col-md-6">
            <div class="card border-0 shadow-sm rounded-4 h-100 bg-white">
                <div class="card-body p-3">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="text-muted small fw-semibold text-uppercase">Third-Party Insurance</span>
                        <div class="bg-info bg-opacity-10 text-info rounded-circle d-flex align-items-center justify-content-center" style="width: 34px; height: 34px;">
                            <i class="fa-solid fa-shield-halved"></i>
                        </div>
                    </div>
                    @if($thirdPartyReminder)
                        @php
                            $tpStatus = $thirdPartyReminder->computed_status;
                            $tpExp = $thirdPartyReminder->insurance_expiry_date;
                        @endphp
                        <div class="d-flex align-items-baseline gap-2">
                            <span class="fs-5 fw-bold text-dark">{{ $tpExp ? $tpExp->format('d M Y') : '—' }}</span>
                        </div>
                        <small class="text-muted d-block text-truncate">{{ $thirdPartyReminder->insurance_company }}</small>
                        <div class="mt-2 d-flex justify-content-between align-items-center">
                            <span class="badge bg-{{ $tpStatus['class'] }} rounded-pill">{{ $tpStatus['label'] }}</span>
                            <a href="{{ route('general-service.vehicle-reminders.show', $thirdPartyReminder) }}" class="btn btn-sm btn-link text-decoration-none p-0">Details &rarr;</a>
                        </div>
                    @else
                        <div class="py-2 text-muted small">No 3rd-party policy set up.</div>
                        <a href="{{ route('general-service.vehicle-reminders.create', ['fixed_asset_unit_id' => $unit->id, 'reminder_type' => 'third_party_insurance']) }}" class="btn btn-sm btn-outline-primary rounded-pill px-3 mt-1">
                            + Set Up Policy
                        </a>
                    @endif
                </div>
            </div>
        </div>

        {{-- 4. Comprehensive Insurance --}}
        <div class="col-xl-3 col-md-6">
            <div class="card border-0 shadow-sm rounded-4 h-100 bg-white">
                <div class="card-body p-3">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="text-muted small fw-semibold text-uppercase">Comprehensive Insurance</span>
                        <div class="bg-primary bg-opacity-10 text-primary rounded-circle d-flex align-items-center justify-content-center" style="width: 34px; height: 34px;">
                            <i class="fa-solid fa-car-burst"></i>
                        </div>
                    </div>
                    @if($compReminder)
                        @php
                            $cpStatus = $compReminder->computed_status;
                            $cpExp = $compReminder->insurance_expiry_date;
                        @endphp
                        <div class="d-flex align-items-baseline gap-2">
                            <span class="fs-5 fw-bold text-dark">{{ $cpExp ? $cpExp->format('d M Y') : '—' }}</span>
                        </div>
                        <small class="text-muted d-block text-truncate">{{ $compReminder->insurance_company }}</small>
                        <div class="mt-2 d-flex justify-content-between align-items-center">
                            <span class="badge bg-{{ $cpStatus['class'] }} rounded-pill">{{ $cpStatus['label'] }}</span>
                            <a href="{{ route('general-service.vehicle-reminders.show', $compReminder) }}" class="btn btn-sm btn-link text-decoration-none p-0">Details &rarr;</a>
                        </div>
                    @else
                        <div class="py-2 text-muted small">No comprehensive policy set up.</div>
                        <a href="{{ route('general-service.vehicle-reminders.create', ['fixed_asset_unit_id' => $unit->id, 'reminder_type' => 'insurance']) }}" class="btn btn-sm btn-outline-primary rounded-pill px-3 mt-1">
                            + Set Up Policy
                        </a>
                    @endif
                </div>
            </div>
        </div>
    </div>

    @php
        $unitKmLogs = collect();
        $unitFuelRequests = collect();
        $unitServiceStatus = null;
        $unitMetrics = null;

        try {
            if (\Illuminate\Support\Facades\Schema::hasTable('vehicle_km_logs')) {
                $unitKmLogs = \App\Models\VehicleKmLog::with(['loggedBy', 'driver'])->where('fixed_asset_unit_id', $unit->id)->latestFirst()->take(30)->get();
            }
            if (\Illuminate\Support\Facades\Schema::hasTable('vehicle_fuel_requests')) {
                $unitFuelRequests = \App\Models\VehicleFuelRequest::with(['driver', 'expenseRequest'])->where('fixed_asset_unit_id', $unit->id)->latest('id')->take(30)->get();
            }
            $unitServiceStatus = app(\App\Services\VehicleKmService::class)->getServiceStatus($unit->id);
            $unitMetrics = app(\App\Services\VehicleFuelService::class)->getVehicleConsumptionMetrics($unit->id);
        } catch (\Throwable $e) {}
    @endphp

    {{-- Consolidated Multi-Tab Hub (Reminders, KM History, Fuel Requests, Service Health) --}}
    <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
        <div class="card-header bg-white border-bottom border-light p-3">
            <ul class="nav nav-pills card-header-pills gap-2" id="vehicleDetailTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active rounded-pill px-3 py-2 fw-semibold" id="compliance-tab" data-bs-toggle="tab" data-bs-target="#compliance-pane" type="button" role="tab">
                        <i class="fa-solid fa-clock-rotate-left me-1"></i>Compliance Reminders &amp; History
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link rounded-pill px-3 py-2 fw-semibold" id="km-tab" data-bs-toggle="tab" data-bs-target="#km-pane" type="button" role="tab">
                        <i class="fa-solid fa-gauge-high me-1 text-info"></i>KM History ({{ $unitKmLogs->count() }})
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link rounded-pill px-3 py-2 fw-semibold" id="fuel-tab" data-bs-toggle="tab" data-bs-target="#fuel-pane" type="button" role="tab">
                        <i class="fa-solid fa-gas-pump me-1 text-warning"></i>Fuel History ({{ $unitFuelRequests->count() }})
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link rounded-pill px-3 py-2 fw-semibold" id="service-tab" data-bs-toggle="tab" data-bs-target="#service-pane" type="button" role="tab">
                        <i class="fa-solid fa-wrench me-1 text-danger"></i>Next Service Due &amp; Efficiency
                        @if($unitServiceStatus && ($unitServiceStatus['is_due_soon'] || $unitServiceStatus['is_overdue']))
                            <span class="badge bg-danger rounded-pill ms-1">!</span>
                        @endif
                    </button>
                </li>
            </ul>
        </div>

        <div class="card-body p-0 tab-content" id="vehicleDetailTabsContent">
            {{-- Tab 1: Compliance Reminders & History --}}
            <div class="tab-pane fade show active" id="compliance-pane" role="tabpanel">
                @php
                    $allHistory = collect();
                    foreach($reminders as $r) {
                        foreach($r->history as $h) {
                            $allHistory->push($h);
                        }
                    }
                    $allHistory = $allHistory->sortByDesc('performed_at');
                @endphp

                @if($allHistory->isEmpty())
                    <div class="text-center py-5 text-muted">
                        <i class="fa-solid fa-history fs-2 mb-2 opacity-25"></i>
                        <p class="small mb-0">No past renewals or service records logged yet for this vehicle.</p>
                    </div>
                @else
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0 small">
                            <thead class="table-light text-muted text-uppercase" style="font-size: 0.72rem;">
                                <tr>
                                    <th class="ps-3">Date</th>
                                    <th>Type</th>
                                    <th>Action</th>
                                    <th>Notes / Description</th>
                                    <th class="pe-3">Recorded By</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($allHistory as $h)
                                    <tr>
                                        <td class="ps-3 text-muted">{{ $h->performed_at->format('d M Y H:i') }}</td>
                                        <td>
                                            <span class="fw-semibold text-dark">{{ ucfirst(str_replace('_', ' ', $h->reminder_type)) }}</span>
                                        </td>
                                        <td>
                                            <span class="badge {{ $h->action === 'renewed' ? 'bg-success' : ($h->action === 'odometer_updated' ? 'bg-info' : 'bg-secondary') }} rounded-pill px-2">
                                                {{ ucfirst(str_replace('_', ' ', $h->action)) }}
                                            </span>
                                        </td>
                                        <td class="text-dark">{{ $h->notes ?: '—' }}</td>
                                        <td class="pe-3 text-muted">{{ $h->performer->name ?? 'System' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>

            {{-- Tab 2: KM History --}}
            <div class="tab-pane fade" id="km-pane" role="tabpanel">
                <div class="p-3 bg-light border-bottom d-flex justify-content-between align-items-center">
                    <span class="small text-muted">Logged dashboard odometer readings</span>
                    <a href="{{ route('vehicle-km-logs.create', ['vehicle_id' => $unit->id]) }}" class="btn btn-primary btn-sm rounded-pill px-3 fw-semibold">
                        <i class="fa-solid fa-plus me-1"></i>Record New KM
                    </a>
                </div>
                @if($unitKmLogs->isEmpty())
                    <div class="text-center py-5 text-muted">
                        <i class="fa-solid fa-gauge-high fs-2 mb-2 opacity-25"></i>
                        <p class="small mb-0">No KM logs recorded for this vehicle yet.</p>
                    </div>
                @else
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0 small">
                            <thead class="table-light text-muted text-uppercase" style="font-size: 0.72rem;">
                                <tr>
                                    <th class="ps-3">Date</th>
                                    <th>Odometer Reading</th>
                                    <th>KM Jump</th>
                                    <th>Source</th>
                                    <th>Logged By</th>
                                    <th>Notes</th>
                                    <th class="pe-3">Photo</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($unitKmLogs as $kLog)
                                    <tr>
                                        <td class="ps-3 text-muted">{{ $kLog->km_date->format('d M Y') }}</td>
                                        <td>
                                            <span class="badge bg-dark font-monospace fs-6 px-2 py-1">{{ number_format($kLog->odometer_km) }} KM</span>
                                        </td>
                                        <td>
                                            @if($kLog->km_difference > 0)
                                                <span class="badge {{ $kLog->is_large_jump ? 'bg-danger' : 'bg-info bg-opacity-25 text-info-emphasis' }}">
                                                    +{{ number_format($kLog->km_difference) }} KM
                                                </span>
                                            @else
                                                <span class="text-muted">—</span>
                                            @endif
                                        </td>
                                        <td><span class="badge bg-light text-dark border">{{ ucfirst(str_replace('_', ' ', $kLog->source)) }}</span></td>
                                        <td class="text-muted">{{ $kLog->loggedBy->name ?? 'System' }}</td>
                                        <td class="text-dark">{{ $kLog->notes ?: '—' }}</td>
                                        <td class="pe-3">
                                            @if($kLog->photo_url)
                                                <a href="{{ $kLog->photo_url }}" target="_blank" class="btn btn-xs btn-outline-info rounded-pill px-2 py-0"><i class="fa-solid fa-image me-1"></i>View</a>
                                            @else
                                                <span class="text-muted">—</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>

            {{-- Tab 3: Fuel History --}}
            <div class="tab-pane fade" id="fuel-pane" role="tabpanel">
                <div class="p-3 bg-light border-bottom d-flex justify-content-between align-items-center">
                    <span class="small text-muted">Past gasoline &amp; diesel requests for this vehicle</span>
                    <a href="{{ route('vehicle-fuel-requests.create', ['vehicle_id' => $unit->id]) }}" class="btn btn-warning text-dark btn-sm rounded-pill px-3 fw-bold">
                        <i class="fa-solid fa-plus me-1"></i>New Fuel Request
                    </a>
                </div>
                @if($unitFuelRequests->isEmpty())
                    <div class="text-center py-5 text-muted">
                        <i class="fa-solid fa-gas-pump fs-2 mb-2 opacity-25"></i>
                        <p class="small mb-0">No fuel requests submitted for this vehicle yet.</p>
                    </div>
                @else
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0 small">
                            <thead class="table-light text-muted text-uppercase" style="font-size: 0.72rem;">
                                <tr>
                                    <th class="ps-3">Request #</th>
                                    <th>Date</th>
                                    <th>Type &amp; Liters</th>
                                    <th>Estimated Cost</th>
                                    <th>Status</th>
                                    <th>Station Receipt</th>
                                    <th class="pe-3 text-end">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($unitFuelRequests as $fReq)
                                    @php $fBadge = $fReq->status_badge; @endphp
                                    <tr>
                                        <td class="ps-3">
                                            <a href="{{ route('vehicle-fuel-requests.show', $fReq->id) }}" class="fw-bold font-monospace text-dark text-decoration-none">
                                                {{ $fReq->request_no }}
                                            </a>
                                        </td>
                                        <td class="text-muted">{{ $fReq->created_at->format('d M Y') }}</td>
                                        <td>
                                            <strong>{{ number_format($fReq->liters_requested, 1) }} L</strong>
                                            <span class="badge {{ $fReq->fuel_type === 'diesel' ? 'bg-secondary' : 'bg-primary' }} text-capitalize ms-1">{{ $fReq->fuel_type }}</span>
                                        </td>
                                        <td class="text-success font-monospace fw-bold">ETB {{ number_format($fReq->actual_amount ?: $fReq->estimated_amount, 2) }}</td>
                                        <td>
                                            <span class="badge bg-{{ $fBadge['class'] }}">{{ $fBadge['label'] }}</span>
                                        </td>
                                        <td>
                                            @if($fReq->receipt_photo)
                                                <span class="badge bg-success bg-opacity-25 text-success-emphasis border border-success border-opacity-25"><i class="fa-solid fa-check me-1"></i>Attached</span>
                                            @else
                                                <span class="badge bg-warning bg-opacity-25 text-warning-emphasis border border-warning border-opacity-25"><i class="fa-solid fa-clock me-1"></i>Pending</span>
                                            @endif
                                        </td>
                                        <td class="pe-3 text-end">
                                            <a href="{{ route('vehicle-fuel-requests.show', $fReq->id) }}" class="btn btn-sm btn-outline-secondary px-2 py-1"><i class="fa-solid fa-eye"></i></a>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>

            {{-- Tab 4: Next Service Due & Efficiency --}}
            <div class="tab-pane fade p-4" id="service-pane" role="tabpanel">
                <div class="row g-4">
                    <div class="col-md-6">
                        <div class="p-3 rounded-4 bg-light border">
                            <h6 class="fw-bold mb-3 text-dark"><i class="fa-solid fa-wrench text-warning me-2"></i>Next Routine Service Interval</h6>
                            @if($unitServiceStatus && $unitServiceStatus['has_service_reminder'])
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <span class="text-muted">Current Odometer:</span>
                                    <strong class="font-monospace fs-6">{{ number_format($unitServiceStatus['current_odometer_km']) }} KM</strong>
                                </div>
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <span class="text-muted">Service Due At:</span>
                                    <strong class="font-monospace fs-6">{{ number_format($unitServiceStatus['next_service_km']) }} KM</strong>
                                </div>
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <span class="text-muted">Remaining Distance:</span>
                                    <strong class="font-monospace fs-5 {{ $unitServiceStatus['is_overdue'] ? 'text-danger' : ($unitServiceStatus['is_due_soon'] ? 'text-warning' : 'text-success') }}">
                                        {{ $unitServiceStatus['remaining_km'] !== null ? number_format($unitServiceStatus['remaining_km']) . ' KM' : '—' }}
                                    </strong>
                                </div>
                                <div class="text-center">
                                    <span class="badge bg-{{ $unitServiceStatus['badge']['class'] }} fs-6 rounded-pill px-3 py-1">
                                        <i class="fa-solid {{ $unitServiceStatus['badge']['icon'] }} me-1"></i>{{ $unitServiceStatus['badge']['label'] }}
                                    </span>
                                </div>
                            @else
                                <div class="text-muted small">No KM-based service reminder set up for this vehicle.</div>
                            @endif
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="p-3 rounded-4 bg-light border">
                            <h6 class="fw-bold mb-3 text-dark"><i class="fa-solid fa-gauge text-primary me-2"></i>Fuel Economy &amp; Efficiency</h6>
                            @if($unitMetrics && $unitMetrics['has_data'])
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <span class="text-muted">Average Mileage:</span>
                                    <strong class="font-monospace text-dark fs-6">{{ $unitMetrics['avg_km_per_liter'] }} KM / Liter</strong>
                                </div>
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <span class="text-muted">Average Running Cost:</span>
                                    <strong class="font-monospace text-dark fs-6">ETB {{ number_format($unitMetrics['avg_cost_per_km'], 2) }} / KM</strong>
                                </div>
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <span class="text-muted">Fuel Spent This Month:</span>
                                    <strong class="font-monospace text-success fs-6">ETB {{ number_format($unitMetrics['monthly_fuel_cost'], 2) }}</strong>
                                </div>
                                @if($unitMetrics['is_efficiency_flag'])
                                    <div class="alert alert-danger border-0 p-2 small mb-0 rounded-3">
                                        <i class="fa-solid fa-triangle-exclamation me-1"></i><strong>Consumption Anomaly:</strong> Latest mileage dropped by {{ $unitMetrics['efficiency_drop'] }}% compared to its baseline.
                                    </div>
                                @else
                                    <div class="alert alert-success border-0 p-2 small mb-0 rounded-3 text-center">
                                        <i class="fa-solid fa-circle-check me-1"></i>Fuel consumption is in normal operating range.
                                    </div>
                                @endif
                            @else
                                <div class="text-muted small">Requires at least one closed fuel receipt with post-fueling odometer to calculate mileage.</div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

</div>

{{-- Odometer Modal if service reminder exists --}}
@if($serviceReminder)
<div class="modal fade" id="unitOdometerModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-4 border-0 shadow">
            <form action="{{ route('general-service.vehicle-reminders.odometer', $serviceReminder) }}" method="POST">
                @csrf
                <div class="modal-header border-bottom border-light">
                    <h5 class="modal-title fw-bold">
                        <i class="fa-solid fa-gauge-high text-info me-2"></i>Update Odometer: {{ $unit->plate_number ?: $unit->unit_code }}
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label text-muted small">Current Logged</label>
                            <input type="text" class="form-control" value="{{ number_format($serviceReminder->current_odometer_km) }} KM" readonly>
                        </div>
                        <div class="col-6">
                            <label class="form-label text-muted small">Next Service At</label>
                            <input type="text" class="form-control" value="{{ number_format($serviceReminder->next_service_km) }} KM" readonly>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="hub_odometer_km" class="form-label fw-bold">New Odometer (KM) <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <input type="number" id="hub_odometer_km" name="current_odometer_km" class="form-control"
                                   min="{{ $serviceReminder->current_odometer_km }}" value="{{ $serviceReminder->current_odometer_km }}" required>
                            <span class="input-group-text">KM</span>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="hub_notes" class="form-label">Notes</label>
                        <textarea id="hub_notes" name="notes" class="form-control" rows="2" placeholder="e.g. Regular meter update"></textarea>
                    </div>
                </div>
                <div class="modal-footer border-top border-light">
                    <button type="button" class="btn btn-light rounded-pill px-3" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-info text-white rounded-pill px-4 fw-semibold">Save Reading</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif

@endsection
