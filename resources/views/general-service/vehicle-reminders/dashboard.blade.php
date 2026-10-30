@extends('layouts.app')
@section('title', 'General Service — Vehicle Reminders Dashboard')

@section('content')
<div class="container-fluid px-3 px-md-4 py-3">
    <x-print-header title="Fleet Vehicle Compliance & Safety Reminders" subtitle="Bolo Safety Inspections, Service KM Schedules & Insurance Registry" />

    {{-- Breadcrumbs & Header --}}
    <x-page-header 
        title="Vehicle Reminders Dashboard" 
        subtitle="Track Bolo safety inspections, KM-based service schedules, and third-party/comprehensive insurance policies">
        <x-slot name="actions">
            <a href="{{ route('general-service.vehicle-reminders.create') }}" class="btn btn-primary btn-sm rounded-pill px-3 shadow-xs fw-bold">
                <i class="fa-solid fa-plus me-1"></i>New Reminder
            </a>
            <a href="{{ route('general-service.vehicle-reminders.index') }}" class="btn btn-outline-secondary btn-sm rounded-pill px-3 shadow-xs">
                <i class="fa-solid fa-list me-1"></i>All Reminders
            </a>
            <x-export-menu :csvUrl="route('general-service.vehicle-reminders.export')" :print="true" label="Export / Print" />
        </x-slot>
    </x-page-header>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show rounded-4 shadow-sm border-0 d-flex align-items-center mb-4" role="alert">
            <i class="fa-solid fa-circle-check fs-5 me-2"></i>
            <div>{{ session('success') }}</div>
            <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
        </div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show rounded-4 shadow-sm border-0 d-flex align-items-center mb-4" role="alert">
            <i class="fa-solid fa-triangle-exclamation fs-5 me-2"></i>
            <div>{{ session('error') }}</div>
            <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
        </div>
    @endif

    {{-- KPI Cards --}}
    <div class="row g-3 mb-4">
        <div class="col-xl-3 col-sm-6">
            <x-kpi-card 
                label="Active Monitored Fleet" 
                value="{{ $stats['total_active'] ?? 0 }}" 
                icon="fa-solid fa-car" 
                variant="primary" 
                helpText="Active vehicle tracking records" />
        </div>
        <div class="col-xl-3 col-sm-6">
            <x-kpi-card 
                label="Bolo Inspections Due" 
                value="{{ ($stats['bolo_expiring'] ?? 0) + ($stats['bolo_expired'] ?? 0) }}" 
                icon="fa-solid fa-stamp" 
                variant="{{ ($stats['bolo_expired'] ?? 0) > 0 ? 'danger' : 'warning' }}" 
                helpText="{{ $stats['bolo_expiring'] ?? 0 }} due soon · {{ $stats['bolo_expired'] ?? 0 }} expired" />
        </div>
        <div class="col-xl-3 col-sm-6">
            <x-kpi-card 
                label="Service Schedules by KM" 
                value="{{ $stats['services_due'] ?? 0 }}" 
                icon="fa-solid fa-gauge-high" 
                variant="{{ ($stats['services_due'] ?? 0) > 0 ? 'danger' : 'success' }}" 
                helpText="{{ ($stats['services_due'] ?? 0) > 0 ? 'Maintenance required' : 'All maintenance current' }}" />
        </div>
        <div class="col-xl-3 col-sm-6">
            <x-kpi-card 
                label="Insurance Policies Expiring" 
                value="{{ ($stats['insurance_expiring'] ?? 0) + ($stats['insurance_expired'] ?? 0) }}" 
                icon="fa-solid fa-shield-halved" 
                variant="{{ ($stats['insurance_expired'] ?? 0) > 0 ? 'danger' : 'info' }}" 
                helpText="{{ $stats['insurance_expiring'] ?? 0 }} due soon · {{ $stats['insurance_expired'] ?? 0 }} expired" />
        </div>
    </div>

    {{-- Quick Jump Filter Shortcuts --}}
    <div class="d-flex flex-wrap gap-2 mb-4">
        <a href="{{ route('general-service.vehicle-reminders.index', ['status' => 'due_soon']) }}" class="btn btn-sm btn-outline-warning rounded-pill px-3 fw-semibold">
            <i class="fa-solid fa-triangle-exclamation me-1"></i>Due in &le; 30 Days
        </a>
        <a href="{{ route('general-service.vehicle-reminders.index', ['status' => 'expired']) }}" class="btn btn-sm btn-outline-danger rounded-pill px-3 fw-semibold">
            <i class="fa-solid fa-circle-xmark me-1"></i>Expired / Overdue
        </a>
        <a href="{{ route('general-service.vehicle-reminders.index', ['reminder_type' => 'bolo']) }}" class="btn btn-sm btn-outline-secondary rounded-pill px-3">
            <i class="fa-solid fa-stamp me-1"></i>Bolo Inspections
        </a>
        <a href="{{ route('general-service.vehicle-reminders.index', ['reminder_type' => 'service_km']) }}" class="btn btn-sm btn-outline-secondary rounded-pill px-3">
            <i class="fa-solid fa-gauge-high me-1"></i>Service by KM
        </a>
        <a href="{{ route('general-service.vehicle-reminders.index', ['reminder_type' => 'third_party_insurance']) }}" class="btn btn-sm btn-outline-secondary rounded-pill px-3">
            <i class="fa-solid fa-shield-halved me-1"></i>Third-Party Insurance
        </a>
        <a href="{{ route('general-service.vehicle-reminders.index', ['reminder_type' => 'insurance']) }}" class="btn btn-sm btn-outline-secondary rounded-pill px-3">
            <i class="fa-solid fa-car-burst me-1"></i>Comprehensive Insurance
        </a>
    </div>

    {{-- Urgent Attention Table --}}
    <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
        <div class="card-header bg-white border-bottom py-3 px-4 d-flex justify-content-between align-items-center">
            <div class="d-flex align-items-center gap-2">
                <span class="d-inline-flex align-items-center justify-content-center bg-danger bg-opacity-10 text-danger rounded-circle" style="width: 34px; height: 34px;">
                    <i class="fa-solid fa-bell"></i>
                </span>
                <div>
                    <h6 class="fw-bold mb-0" style="color: var(--brand-900);">Urgent Attention Required</h6>
                    <small class="text-muted">Vehicles with expired documents or services approaching threshold</small>
                </div>
            </div>
            <a href="{{ route('general-service.vehicle-reminders.index') }}" class="btn btn-link btn-sm text-decoration-none fw-semibold">
                View All Reminders <i class="fa-solid fa-arrow-right ms-1"></i>
            </a>
        </div>
        <div class="card-body p-0">
            @if($urgentItems->isEmpty())
                <div class="p-5 text-center">
                    <x-empty-state 
                        icon="fa-solid fa-circle-check" 
                        title="All Vehicles Up to Date!" 
                        message="No vehicle reminders are currently overdue or expiring within 30 days." />
                </div>
            @else
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0 wc-table-mobile-cards">
                        <thead class="table-light text-muted small text-uppercase" style="letter-spacing: 0.5px;">
                            <tr>
                                <th class="ps-4 py-3">Vehicle Details</th>
                                <th class="py-3 text-center">Reminder Type</th>
                                <th class="py-3">Expiry / Target</th>
                                <th class="py-3 text-center">Urgency Status</th>
                                <th class="pe-4 py-3 text-end">Actions</th>
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
                                    <td class="ps-4 py-3" data-label="Vehicle">
                                        <div class="d-flex align-items-center gap-2">
                                            <div class="bg-light text-dark rounded-3 p-2 text-center" style="min-width: 40px;">
                                                <i class="fa-solid fa-truck text-secondary"></i>
                                            </div>
                                            <div>
                                                <a href="{{ route('general-service.vehicle-reminders.vehicle-detail', $item->fixed_asset_unit_id) }}" class="fw-bold text-decoration-none" style="color: var(--brand-900);">
                                                    {{ $item->fixedAsset?->name ?? 'Vehicle' }}
                                                </a>
                                                <div class="d-flex gap-2 align-items-center mt-0.5">
                                                    @if($item->assetUnit?->plate_number)
                                                        <span class="badge bg-dark text-white rounded-pill px-2 font-monospace" style="font-size: 0.7rem;">
                                                            {{ $item->assetUnit->plate_number }}
                                                        </span>
                                                    @endif
                                                    <span class="text-muted small font-monospace">{{ $item->assetUnit?->unit_code }}</span>
                                                </div>
                                            </div>
                                        </div>
                                    </td>

                                    {{-- Reminder Type --}}
                                    <td class="py-3 text-center" data-label="Type">
                                        <span class="badge bg-light text-dark border rounded-pill px-3 py-1 fw-semibold">
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
                                    <td class="py-3 font-tabular" data-label="Target">
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
                                    <td class="py-3 text-center" data-label="Urgency">
                                        @if($item->reminder_type === 'service_km')
                                            @if($kmRemaining !== null)
                                                @if($kmRemaining <= 0)
                                                    <span class="badge bg-danger-subtle text-danger border border-danger-subtle rounded-pill px-3 py-1 fw-bold">
                                                        <i class="fa-solid fa-triangle-exclamation me-1"></i>Overdue by {{ number_format(abs($kmRemaining)) }} KM
                                                    </span>
                                                @else
                                                    <span class="badge bg-warning-subtle text-warning border border-warning-subtle rounded-pill px-3 py-1 fw-bold">
                                                        <i class="fa-solid fa-clock me-1"></i>Due in {{ number_format($kmRemaining) }} KM
                                                    </span>
                                                @endif
                                            @endif
                                        @else
                                            @if($daysRemaining !== null)
                                                @if($daysRemaining < 0)
                                                    <span class="badge bg-danger-subtle text-danger border border-danger-subtle rounded-pill px-3 py-1 fw-bold">
                                                        <i class="fa-solid fa-circle-xmark me-1"></i>Expired {{ abs($daysRemaining) }} {{ abs($daysRemaining) === 1 ? 'day' : 'days' }} ago
                                                    </span>
                                                @elseif($daysRemaining === 0)
                                                    <span class="badge bg-danger text-white rounded-pill px-3 py-1 fw-bold">
                                                        <i class="fa-solid fa-triangle-exclamation me-1"></i>Expires Today!
                                                    </span>
                                                @else
                                                    <span class="badge bg-warning-subtle text-warning border border-warning-subtle rounded-pill px-3 py-1 fw-bold">
                                                        <i class="fa-solid fa-clock me-1"></i>Due in {{ $daysRemaining }} {{ $daysRemaining === 1 ? 'day' : 'days' }}
                                                    </span>
                                                @endif
                                            @endif
                                        @endif
                                    </td>

                                    {{-- Actions --}}
                                    <td class="pe-4 py-3 text-end" data-label="Actions">
                                        <div class="d-flex justify-content-end gap-1">
                                            @if($item->reminder_type === 'service_km')
                                                <button type="button" class="btn btn-sm btn-outline-info rounded-pill px-2.5 shadow-xs"
                                                        data-bs-toggle="modal" data-bs-target="#odometerModal-{{ $item->id }}" title="Update Odometer">
                                                    <i class="fa-solid fa-gauge me-1"></i>Update KM
                                                </button>
                                            @endif
                                            <a href="{{ route('general-service.vehicle-reminders.show', $item) }}" class="btn btn-sm btn-outline-secondary rounded-pill px-2.5 shadow-xs">
                                                <i class="fa-solid fa-eye me-1"></i>View
                                            </a>
                                            <a href="{{ route('general-service.vehicle-reminders.edit', $item) }}" class="btn btn-sm btn-outline-primary rounded-pill px-2 shadow-xs" title="Edit">
                                                <i class="fa-solid fa-pen"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>

                                {{-- Odometer Modal for this item --}}
                                @if($item->reminder_type === 'service_km')
                                    <div class="modal fade" id="odometerModal-{{ $item->id }}" tabindex="-1" aria-hidden="true">
                                        <div class="modal-dialog modal-dialog-centered">
                                            <div class="modal-content rounded-4 border-0 shadow-lg text-start">
                                                <form action="{{ route('general-service.vehicle-reminders.odometer', $item) }}" method="POST">
                                                    @csrf
                                                    <div class="modal-header bg-info text-white border-0 py-3 px-4">
                                                        <h5 class="modal-title fw-bold">
                                                            <i class="fa-solid fa-gauge-high me-2"></i>Update Odometer (KM)
                                                        </h5>
                                                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <div class="modal-body p-4 bg-white">
                                                        <div class="mb-3">
                                                            <label class="form-label text-muted small fw-semibold">Vehicle</label>
                                                            <div class="fw-bold" style="color: var(--brand-900);">{{ $item->fixedAsset?->name }} ({{ $item->assetUnit?->plate_number ?? $item->assetUnit?->unit_code }})</div>
                                                        </div>
                                                        <div class="row g-2 mb-3">
                                                            <div class="col-6">
                                                                <label class="form-label text-muted small">Last Logged KM</label>
                                                                <input type="text" class="form-control font-monospace" value="{{ number_format($item->current_odometer_km) }} KM" readonly>
                                                            </div>
                                                            <div class="col-6">
                                                                <label class="form-label text-muted small">Next Service KM</label>
                                                                <input type="text" class="form-control font-monospace" value="{{ number_format($item->next_service_km) }} KM" readonly>
                                                            </div>
                                                        </div>
                                                        <div class="mb-3">
                                                            <label for="odometer_km_{{ $item->id }}" class="form-label fw-bold">New Odometer Reading (KM) <span class="text-danger">*</span></label>
                                                            <div class="input-group">
                                                                <input type="number" id="odometer_km_{{ $item->id }}" name="current_odometer_km" class="form-control font-monospace"
                                                                       min="{{ $item->current_odometer_km }}" value="{{ $item->current_odometer_km }}" required>
                                                                <span class="input-group-text">KM</span>
                                                            </div>
                                                            <small class="text-muted">Enter the current dashboard odometer reading of the vehicle.</small>
                                                        </div>
                                                        <div class="mb-3">
                                                            <label for="odometer_notes_{{ $item->id }}" class="form-label fw-bold">Notes / Log Reason</label>
                                                            <textarea id="odometer_notes_{{ $item->id }}" name="notes" class="form-control" rows="2" placeholder="e.g. Returned from Hawassa site trip"></textarea>
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer bg-light border-0 py-3 px-4">
                                                        <button type="button" class="btn btn-secondary rounded-pill px-3" data-bs-dismiss="modal">Cancel</button>
                                                        <button type="submit" class="btn btn-info text-white rounded-pill px-4 fw-bold shadow-sm">
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
