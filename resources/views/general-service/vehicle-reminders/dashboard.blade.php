@extends('layouts.app')
@section('title', 'General Service — Vehicle Reminders Dashboard')

@section('content')
<div class="container-fluid px-3 px-md-4 py-3">

    {{-- Breadcrumbs & Header --}}
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-1 text-muted small">
                    <li class="breadcrumb-item"><a href="{{ route('dashboard.general_service') }}" class="text-decoration-none">General Service</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Vehicle Reminders</li>
                </ol>
            </nav>
            <h1 class="h3 fw-bold mb-0 text-dark">
                <i class="fa-solid fa-clock-rotate-left text-warning me-2"></i>Vehicle Reminders Dashboard
            </h1>
            <p class="text-muted small mb-0">Track Bolo inspections, KM-based service schedules, and insurance policies for company vehicles</p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a href="{{ route('general-service.vehicle-reminders.create') }}" class="btn btn-primary rounded-pill px-3 shadow-sm fw-semibold">
                <i class="fa-solid fa-plus me-1"></i>New Reminder
            </a>
            <a href="{{ route('general-service.vehicle-reminders.index') }}" class="btn btn-outline-secondary rounded-pill px-3 shadow-sm">
                <i class="fa-solid fa-list me-1"></i>All Reminders
            </a>
            <a href="{{ route('general-service.vehicle-reminders.export') }}" class="btn btn-outline-success rounded-pill px-3 shadow-sm">
                <i class="fa-solid fa-file-excel me-1"></i>Export CSV
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

    {{-- KPI Cards --}}
    <div class="row g-3 mb-4">
        {{-- Card 1: Total Active Monitored --}}
        <div class="col-xl-3 col-md-6">
            <div class="card border-0 shadow-sm rounded-4 h-100 bg-white" style="border-left: 4px solid #3b82f6 !important;">
                <div class="card-body p-3">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="text-muted small fw-semibold text-uppercase" style="letter-spacing: 0.5px; font-size: 0.72rem;">Active Monitored</span>
                        <div class="bg-primary bg-opacity-10 text-primary rounded-circle d-flex align-items-center justify-content-center" style="width: 36px; height: 36px;">
                            <i class="fa-solid fa-car"></i>
                        </div>
                    </div>
                    <div class="d-flex align-items-baseline gap-2">
                        <span class="fs-2 fw-bold text-dark">{{ $stats['total_active'] ?? 0 }}</span>
                        <span class="text-muted small">records</span>
                    </div>
                    <div class="mt-2 text-muted small">
                        Active vehicle tracking records
                    </div>
                </div>
            </div>
        </div>

        {{-- Card 2: Bolo Inspection --}}
        <div class="col-xl-3 col-md-6">
            <div class="card border-0 shadow-sm rounded-4 h-100 bg-white" style="border-left: 4px solid #f59e0b !important;">
                <div class="card-body p-3">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="text-muted small fw-semibold text-uppercase" style="letter-spacing: 0.5px; font-size: 0.72rem;">Bolo Inspection</span>
                        <div class="bg-warning bg-opacity-10 text-warning rounded-circle d-flex align-items-center justify-content-center" style="width: 36px; height: 36px;">
                            <i class="fa-solid fa-stamp"></i>
                        </div>
                    </div>
                    <div class="d-flex align-items-baseline gap-2">
                        <span class="fs-2 fw-bold text-dark">{{ ($stats['bolo_expiring'] ?? 0) + ($stats['bolo_expired'] ?? 0) }}</span>
                        <span class="text-muted small">requiring action</span>
                    </div>
                    <div class="mt-2 d-flex gap-2">
                        <span class="badge bg-warning text-dark rounded-pill fw-semibold">
                            <i class="fa-solid fa-clock me-1"></i>{{ $stats['bolo_expiring'] ?? 0 }} Due Soon
                        </span>
                        <span class="badge bg-danger rounded-pill fw-semibold">
                            <i class="fa-solid fa-circle-xmark me-1"></i>{{ $stats['bolo_expired'] ?? 0 }} Expired
                        </span>
                    </div>
                </div>
            </div>
        </div>

        {{-- Card 3: Service by KM --}}
        <div class="col-xl-3 col-md-6">
            <div class="card border-0 shadow-sm rounded-4 h-100 bg-white" style="border-left: 4px solid #10b981 !important;">
                <div class="card-body p-3">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="text-muted small fw-semibold text-uppercase" style="letter-spacing: 0.5px; font-size: 0.72rem;">Service by KM</span>
                        <div class="bg-success bg-opacity-10 text-success rounded-circle d-flex align-items-center justify-content-center" style="width: 36px; height: 36px;">
                            <i class="fa-solid fa-gauge-high"></i>
                        </div>
                    </div>
                    <div class="d-flex align-items-baseline gap-2">
                        <span class="fs-2 fw-bold text-dark">{{ $stats['services_due'] ?? 0 }}</span>
                        <span class="text-muted small">due / overdue</span>
                    </div>
                    <div class="mt-2">
                        <span class="badge {{ ($stats['services_due'] ?? 0) > 0 ? 'bg-danger' : 'bg-success' }} rounded-pill fw-semibold">
                            <i class="fa-solid {{ ($stats['services_due'] ?? 0) > 0 ? 'fa-triangle-exclamation' : 'fa-circle-check' }} me-1"></i>
                            {{ ($stats['services_due'] ?? 0) > 0 ? ($stats['services_due'] . ' Services Needed') : 'All Up to Date' }}
                        </span>
                    </div>
                </div>
            </div>
        </div>

        {{-- Card 4: Insurance Policies --}}
        <div class="col-xl-3 col-md-6">
            <div class="card border-0 shadow-sm rounded-4 h-100 bg-white" style="border-left: 4px solid #8b5cf6 !important;">
                <div class="card-body p-3">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="text-muted small fw-semibold text-uppercase" style="letter-spacing: 0.5px; font-size: 0.72rem;">Insurance Policies</span>
                        <div class="bg-purple bg-opacity-10 text-primary rounded-circle d-flex align-items-center justify-content-center" style="width: 36px; height: 36px; background-color: rgba(139, 92, 246, 0.1);">
                            <i class="fa-solid fa-shield-halved" style="color: #8b5cf6;"></i>
                        </div>
                    </div>
                    <div class="d-flex align-items-baseline gap-2">
                        <span class="fs-2 fw-bold text-dark">{{ ($stats['insurance_expiring'] ?? 0) + ($stats['insurance_expired'] ?? 0) }}</span>
                        <span class="text-muted small">policies expiring</span>
                    </div>
                    <div class="mt-2 d-flex gap-2">
                        <span class="badge bg-warning text-dark rounded-pill fw-semibold">
                            <i class="fa-solid fa-clock me-1"></i>{{ $stats['insurance_expiring'] ?? 0 }} Due Soon
                        </span>
                        <span class="badge bg-danger rounded-pill fw-semibold">
                            <i class="fa-solid fa-circle-xmark me-1"></i>{{ $stats['insurance_expired'] ?? 0 }} Expired
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Quick Jump Filter Shortcuts --}}
    <div class="row g-2 mb-4">
        <div class="col-auto">
            <a href="{{ route('general-service.vehicle-reminders.index', ['status' => 'due_soon']) }}" class="btn btn-sm btn-outline-warning rounded-pill px-3 fw-semibold">
                <i class="fa-solid fa-triangle-exclamation me-1"></i>Due in &le; 30 Days
            </a>
        </div>
        <div class="col-auto">
            <a href="{{ route('general-service.vehicle-reminders.index', ['status' => 'expired']) }}" class="btn btn-sm btn-outline-danger rounded-pill px-3 fw-semibold">
                <i class="fa-solid fa-circle-xmark me-1"></i>Expired / Overdue
            </a>
        </div>
        <div class="col-auto">
            <a href="{{ route('general-service.vehicle-reminders.index', ['reminder_type' => 'bolo']) }}" class="btn btn-sm btn-outline-secondary rounded-pill px-3">
                <i class="fa-solid fa-stamp me-1"></i>Bolo Inspections
            </a>
        </div>
        <div class="col-auto">
            <a href="{{ route('general-service.vehicle-reminders.index', ['reminder_type' => 'service_km']) }}" class="btn btn-sm btn-outline-secondary rounded-pill px-3">
                <i class="fa-solid fa-gauge-high me-1"></i>Service by KM
            </a>
        </div>
        <div class="col-auto">
            <a href="{{ route('general-service.vehicle-reminders.index', ['reminder_type' => 'third_party_insurance']) }}" class="btn btn-sm btn-outline-secondary rounded-pill px-3">
                <i class="fa-solid fa-shield-halved me-1"></i>Third-Party Insurance
            </a>
        </div>
        <div class="col-auto">
            <a href="{{ route('general-service.vehicle-reminders.index', ['reminder_type' => 'insurance']) }}" class="btn btn-sm btn-outline-secondary rounded-pill px-3">
                <i class="fa-solid fa-car-burst me-1"></i>Comprehensive Insurance
            </a>
        </div>
    </div>

    {{-- Urgent Attention Table --}}
    <div class="card border-0 shadow-sm rounded-4 mb-4">
        <div class="card-header bg-white border-bottom border-light py-3 d-flex justify-content-between align-items-center">
            <div class="d-flex align-items-center gap-2">
                <span class="d-inline-flex align-items-center justify-content-center bg-danger bg-opacity-10 text-danger rounded-circle" style="width: 32px; height: 32px;">
                    <i class="fa-solid fa-bell"></i>
                </span>
                <div>
                    <h5 class="card-title fw-bold mb-0 text-dark">Urgent Attention Required</h5>
                    <span class="text-muted small">Vehicles with expired documents or services approaching threshold</span>
                </div>
            </div>
            <a href="{{ route('general-service.vehicle-reminders.index') }}" class="btn btn-link btn-sm text-decoration-none fw-semibold">
                View All Reminders <i class="fa-solid fa-arrow-right ms-1"></i>
            </a>
        </div>
        <div class="card-body p-0">
            @if($urgentItems->isEmpty())
                <div class="text-center py-5">
                    <div class="bg-success bg-opacity-10 text-success rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width: 60px; height: 60px;">
                        <i class="fa-solid fa-check fs-3"></i>
                    </div>
                    <h6 class="fw-bold text-dark">All Vehicles Up to Date!</h6>
                    <p class="text-muted small mb-3">No vehicle reminders are currently overdue or expiring within 30 days.</p>
                    <a href="{{ route('general-service.vehicle-reminders.create') }}" class="btn btn-sm btn-outline-primary rounded-pill px-3">
                        <i class="fa-solid fa-plus me-1"></i>Create a Reminder
                    </a>
                </div>
            @else
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light text-muted small text-uppercase" style="font-size: 0.72rem;">
                            <tr>
                                <th class="ps-3">Vehicle Details</th>
                                <th>Reminder Type</th>
                                <th>Expiry / Target</th>
                                <th>Urgency Status</th>
                                <th class="text-end pe-3">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($urgentItems as $item)
                                @php
                                    $computed = $item->computed_status;
                                    $daysRemaining = $item->days_until_expiry;
                                    $kmRemaining = $item->km_remaining;
                                @endphp
                                <tr>
                                    {{-- Vehicle details --}}
                                    <td class="ps-3 py-3">
                                        <div class="d-flex align-items-center gap-2">
                                            <div class="bg-light text-dark rounded-3 p-2 text-center" style="min-width: 44px;">
                                                <i class="fa-solid fa-truck text-secondary"></i>
                                            </div>
                                            <div>
                                                <a href="{{ route('general-service.vehicle-reminders.vehicle-detail', $item->fixed_asset_unit_id) }}" class="fw-bold text-dark text-decoration-none hover-primary">
                                                    {{ $item->fixedAsset?->name ?? 'Vehicle' }}
                                                </a>
                                                <div class="d-flex gap-2 align-items-center mt-1">
                                                    @if($item->assetUnit?->plate_number)
                                                        <span class="badge bg-dark text-white rounded-pill px-2" style="font-size: 0.7rem;">
                                                            <i class="fa-solid fa-hashtag me-1"></i>{{ $item->assetUnit->plate_number }}
                                                        </span>
                                                    @endif
                                                    <span class="text-muted small">{{ $item->assetUnit?->unit_code }}</span>
                                                </div>
                                            </div>
                                        </div>
                                    </td>

                                    {{-- Reminder Type --}}
                                    <td>
                                        <span class="badge bg-light text-dark border rounded-pill px-3 py-2 fw-semibold">
                                            @if($item->reminder_type === 'bolo')
                                                <i class="fa-solid fa-stamp text-warning me-1"></i>Bolo
                                            @elseif($item->reminder_type === 'service_km')
                                                <i class="fa-solid fa-gauge-high text-success me-1"></i>Service (KM)
                                            @elseif($item->reminder_type === 'third_party_insurance')
                                                <i class="fa-solid fa-shield-halved text-info me-1"></i>3rd-Party Ins.
                                            @else
                                                <i class="fa-solid fa-car-burst text-primary me-1"></i>Comp. Ins.
                                            @endif
                                        </span>
                                    </td>

                                    {{-- Expiry / Target --}}
                                    <td>
                                        @if($item->reminder_type === 'service_km')
                                            <div>
                                                <span class="fw-bold text-dark">{{ number_format($item->next_service_km) }} KM</span>
                                                <small class="text-muted d-block">Current: {{ number_format($item->current_odometer_km) }} KM</small>
                                            </div>
                                        @else
                                            @php $exp = $item->getExpiryDate(); @endphp
                                            <div>
                                                <span class="fw-bold text-dark">{{ $exp ? $exp->format('d M Y') : '—' }}</span>
                                                <small class="text-muted d-block">Due date</small>
                                            </div>
                                        @endif
                                    </td>

                                    {{-- Urgency Badge --}}
                                    <td>
                                        @if($item->reminder_type === 'service_km')
                                            @if($kmRemaining !== null)
                                                @if($kmRemaining <= 0)
                                                    <span class="badge bg-danger rounded-pill px-3 py-1 fw-bold">
                                                        <i class="fa-solid fa-triangle-exclamation me-1"></i>Overdue by {{ number_format(abs($kmRemaining)) }} KM
                                                    </span>
                                                @else
                                                    <span class="badge bg-warning text-dark rounded-pill px-3 py-1 fw-bold">
                                                        <i class="fa-solid fa-clock me-1"></i>Due in {{ number_format($kmRemaining) }} KM
                                                    </span>
                                                @endif
                                            @endif
                                        @else
                                            @if($daysRemaining !== null)
                                                @if($daysRemaining < 0)
                                                    <span class="badge bg-danger rounded-pill px-3 py-1 fw-bold">
                                                        <i class="fa-solid fa-circle-xmark me-1"></i>Expired {{ abs($daysRemaining) }} {{ abs($daysRemaining) === 1 ? 'day' : 'days' }} ago
                                                    </span>
                                                @elseif($daysRemaining === 0)
                                                    <span class="badge bg-danger rounded-pill px-3 py-1 fw-bold">
                                                        <i class="fa-solid fa-triangle-exclamation me-1"></i>Expires Today!
                                                    </span>
                                                @else
                                                    <span class="badge bg-warning text-dark rounded-pill px-3 py-1 fw-bold">
                                                        <i class="fa-solid fa-clock me-1"></i>Due in {{ $daysRemaining }} {{ $daysRemaining === 1 ? 'day' : 'days' }}
                                                    </span>
                                                @endif
                                            @endif
                                        @endif
                                    </td>

                                    {{-- Actions --}}
                                    <td class="text-end pe-3">
                                        <div class="d-flex justify-content-end gap-1">
                                            @if($item->reminder_type === 'service_km')
                                                <button type="button" class="btn btn-sm btn-outline-info rounded-pill px-2"
                                                        data-bs-toggle="modal" data-bs-target="#odometerModal-{{ $item->id }}" title="Update Odometer">
                                                    <i class="fa-solid fa-gauge me-1"></i>Update KM
                                                </button>
                                            @endif
                                            <a href="{{ route('general-service.vehicle-reminders.show', $item) }}" class="btn btn-sm btn-outline-secondary rounded-pill px-3">
                                                <i class="fa-solid fa-eye me-1"></i>View
                                            </a>
                                            <a href="{{ route('general-service.vehicle-reminders.edit', $item) }}" class="btn btn-sm btn-outline-primary rounded-pill px-2" title="Edit">
                                                <i class="fa-solid fa-pen"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>

                                {{-- Odometer Modal for this item --}}
                                @if($item->reminder_type === 'service_km')
                                    <div class="modal fade" id="odometerModal-{{ $item->id }}" tabindex="-1" aria-hidden="true">
                                        <div class="modal-dialog modal-dialog-centered">
                                            <div class="modal-content rounded-4 border-0 shadow">
                                                <form action="{{ route('general-service.vehicle-reminders.odometer', $item) }}" method="POST">
                                                    @csrf
                                                    <div class="modal-header border-bottom border-light">
                                                        <h5 class="modal-title fw-bold">
                                                            <i class="fa-solid fa-gauge-high text-info me-2"></i>Update Odometer (KM)
                                                        </h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <div class="modal-body p-4">
                                                        <div class="mb-3">
                                                            <label class="form-label text-muted small fw-semibold">Vehicle</label>
                                                            <div class="fw-bold text-dark">{{ $item->fixedAsset?->name }} ({{ $item->assetUnit?->plate_number ?? $item->assetUnit?->unit_code }})</div>
                                                        </div>
                                                        <div class="row g-2 mb-3">
                                                            <div class="col-6">
                                                                <label class="form-label text-muted small">Last Logged KM</label>
                                                                <input type="text" class="form-control" value="{{ number_format($item->current_odometer_km) }} KM" readonly>
                                                            </div>
                                                            <div class="col-6">
                                                                <label class="form-label text-muted small">Next Service KM</label>
                                                                <input type="text" class="form-control" value="{{ number_format($item->next_service_km) }} KM" readonly>
                                                            </div>
                                                        </div>
                                                        <div class="mb-3">
                                                            <label for="odometer_km_{{ $item->id }}" class="form-label fw-bold">New Odometer Reading (KM) <span class="text-danger">*</span></label>
                                                            <div class="input-group">
                                                                <input type="number" id="odometer_km_{{ $item->id }}" name="current_odometer_km" class="form-control"
                                                                       min="{{ $item->current_odometer_km }}" value="{{ $item->current_odometer_km }}" required>
                                                                <span class="input-group-text">KM</span>
                                                            </div>
                                                            <small class="text-muted">Enter the current dashboard odometer reading of the vehicle.</small>
                                                        </div>
                                                        <div class="mb-3">
                                                            <label for="odometer_notes_{{ $item->id }}" class="form-label">Notes / Log Reason</label>
                                                            <textarea id="odometer_notes_{{ $item->id }}" name="notes" class="form-control" rows="2" placeholder="e.g. Returned from Hawassa trip"></textarea>
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer border-top border-light">
                                                        <button type="button" class="btn btn-light rounded-pill px-3" data-bs-dismiss="modal">Cancel</button>
                                                        <button type="submit" class="btn btn-info text-white rounded-pill px-4 fw-semibold">
                                                            <i class="fa-solid fa-check me-1"></i>Save Odometer
                                                        </button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                @endif
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>

</div>
@endsection
