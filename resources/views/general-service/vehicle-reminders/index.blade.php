@extends('layouts.app')
@section('title', 'General Service — Vehicle Reminders List')

@section('content')
<div class="container-fluid px-3 px-md-4 py-3">

    {{-- Header --}}
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-1 text-muted small">
                    <li class="breadcrumb-item"><a href="{{ route('dashboard.general_service') }}" class="text-decoration-none">General Service</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('general-service.vehicle-reminders.dashboard') }}" class="text-decoration-none">Vehicle Reminders</a></li>
                    <li class="breadcrumb-item active" aria-current="page">All Reminders</li>
                </ol>
            </nav>
            <h1 class="h3 fw-bold mb-0 text-dark">
                <i class="fa-solid fa-list-check text-warning me-2"></i>Vehicle Reminders
            </h1>
            <p class="text-muted small mb-0">Manage vehicle inspection expirations, insurance renewals, and service odometer intervals</p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a href="{{ route('general-service.vehicle-reminders.create') }}" class="btn btn-primary rounded-pill px-3 shadow-sm fw-semibold">
                <i class="fa-solid fa-plus me-1"></i>New Reminder
            </a>
            <a href="{{ route('general-service.vehicle-reminders.dashboard') }}" class="btn btn-outline-secondary rounded-pill px-3 shadow-sm">
                <i class="fa-solid fa-chart-pie me-1"></i>Dashboard
            </a>
            <a href="{{ route('general-service.vehicle-reminders.export', request()->query()) }}" class="btn btn-outline-success rounded-pill px-3 shadow-sm">
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

    {{-- Filter Card --}}
    <div class="card border-0 shadow-sm rounded-4 mb-4">
        <div class="card-body p-3">
            <form action="{{ route('general-service.vehicle-reminders.index') }}" method="GET" class="row g-2 align-items-end">
                {{-- Vehicle filter --}}
                <div class="col-md-3 col-sm-6">
                    <label class="form-label text-muted small fw-semibold mb-1">Vehicle</label>
                    <select name="vehicle_id" class="form-select form-select-sm rounded-3">
                        <option value="">All Vehicles</option>
                        @foreach($vehicleUnits as $unit)
                            <option value="{{ $unit->id }}" {{ request('vehicle_id') == $unit->id ? 'selected' : '' }}>
                                {{ $unit->unit_code }} — {{ $unit->parentAsset?->name }} ({{ $unit->plate_number ?: 'No Plate' }})
                            </option>
                        @endforeach
                    </select>
                </div>

                {{-- Reminder Type --}}
                <div class="col-md-2 col-sm-6">
                    <label class="form-label text-muted small fw-semibold mb-1">Reminder Type</label>
                    <select name="reminder_type" class="form-select form-select-sm rounded-3">
                        <option value="">All Types</option>
                        <option value="bolo" {{ request('reminder_type') === 'bolo' ? 'selected' : '' }}>Bolo</option>
                        <option value="service_km" {{ request('reminder_type') === 'service_km' ? 'selected' : '' }}>Service by KM</option>
                        <option value="third_party_insurance" {{ request('reminder_type') === 'third_party_insurance' ? 'selected' : '' }}>3rd-Party Insurance</option>
                        <option value="insurance" {{ request('reminder_type') === 'insurance' ? 'selected' : '' }}>Comprehensive Insurance</option>
                    </select>
                </div>

                {{-- Status --}}
                <div class="col-md-2 col-sm-6">
                    <label class="form-label text-muted small fw-semibold mb-1">Status</label>
                    <select name="status" class="form-select form-select-sm rounded-3">
                        <option value="">All Statuses</option>
                        <option value="due_soon" {{ request('status') === 'due_soon' ? 'selected' : '' }}>Due Soon (&le;30d / &le;500km)</option>
                        <option value="expired" {{ request('status') === 'expired' ? 'selected' : '' }}>Expired / Overdue</option>
                        <option value="active" {{ request('status') === 'active' ? 'selected' : '' }}>Active / OK</option>
                        <option value="renewed" {{ request('status') === 'renewed' ? 'selected' : '' }}>Renewed</option>
                    </select>
                </div>

                {{-- Search query --}}
                <div class="col-md-3 col-sm-6">
                    <label class="form-label text-muted small fw-semibold mb-1">Search</label>
                    <input type="text" name="search" class="form-control form-control-sm rounded-3"
                           placeholder="Plate, code, vehicle, policy..." value="{{ request('search') }}">
                </div>

                {{-- Filter Actions --}}
                <div class="col-md-2 col-sm-12 d-flex gap-1">
                    <button type="submit" class="btn btn-sm btn-primary rounded-pill px-3 flex-grow-1">
                        <i class="fa-solid fa-filter me-1"></i>Filter
                    </button>
                    @if(request()->hasAny(['vehicle_id', 'reminder_type', 'status', 'search', 'date_from', 'date_to']))
                        <a href="{{ route('general-service.vehicle-reminders.index') }}" class="btn btn-sm btn-outline-secondary rounded-pill px-2" title="Clear Filters">
                            <i class="fa-solid fa-xmark"></i>
                        </a>
                    @endif
                </div>
            </form>
        </div>
    </div>

    {{-- Reminders Table Card --}}
    <div class="card border-0 shadow-sm rounded-4">
        <div class="card-header bg-white border-bottom border-light py-3 d-flex justify-content-between align-items-center">
            <h5 class="card-title fw-bold mb-0 text-dark">
                Vehicle Reminders <span class="badge bg-secondary rounded-pill ms-1">{{ $reminders->total() }}</span>
            </h5>
            <span class="text-muted small">Showing {{ $reminders->firstItem() ?? 0 }} to {{ $reminders->lastItem() ?? 0 }} of {{ $reminders->total() }} records</span>
        </div>
        <div class="card-body p-0">
            @if($reminders->isEmpty())
                <div class="text-center py-5">
                    <div class="bg-light rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width: 60px; height: 60px;">
                        <i class="fa-solid fa-bell-slash text-muted fs-3"></i>
                    </div>
                    <h6 class="fw-bold text-dark">No Vehicle Reminders Found</h6>
                    <p class="text-muted small mb-3">No reminder records match your selected filter criteria.</p>
                    <a href="{{ route('general-service.vehicle-reminders.create') }}" class="btn btn-sm btn-primary rounded-pill px-3">
                        <i class="fa-solid fa-plus me-1"></i>Create First Reminder
                    </a>
                </div>
            @else
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light text-muted small text-uppercase" style="font-size: 0.72rem;">
                            <tr>
                                <th class="ps-3">Vehicle</th>
                                <th>Reminder Type</th>
                                <th>Status</th>
                                <th>Target / Expiry</th>
                                <th>Remaining</th>
                                <th>Details</th>
                                <th class="text-end pe-3">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($reminders as $reminder)
                                @php
                                    $computed = $reminder->computed_status;
                                    $daysRemaining = $reminder->days_until_expiry;
                                    $kmRemaining = $reminder->km_remaining;
                                @endphp
                                <tr>
                                    {{-- Vehicle --}}
                                    <td class="ps-3 py-3">
                                        <div class="d-flex align-items-center gap-2">
                                            <div class="bg-light text-dark rounded-3 p-2 text-center" style="min-width: 40px;">
                                                <i class="fa-solid fa-car text-secondary"></i>
                                            </div>
                                            <div>
                                                <a href="{{ route('general-service.vehicle-reminders.vehicle-detail', $reminder->fixed_asset_unit_id) }}" class="fw-bold text-dark text-decoration-none">
                                                    {{ $reminder->fixedAsset?->name ?? 'Vehicle' }}
                                                </a>
                                                <div class="d-flex gap-2 align-items-center mt-1">
                                                    @if($reminder->assetUnit?->plate_number)
                                                        <span class="badge bg-dark text-white rounded-pill px-2" style="font-size: 0.7rem;">
                                                            {{ $reminder->assetUnit->plate_number }}
                                                        </span>
                                                    @endif
                                                    <span class="text-muted small">{{ $reminder->assetUnit?->unit_code }}</span>
                                                    @if($reminder->assetUnit?->model)
                                                        <span class="text-muted small">· {{ $reminder->assetUnit->model }}</span>
                                                    @endif
                                                </div>
                                            </div>
                                        </div>
                                    </td>

                                    {{-- Type --}}
                                    <td>
                                        <span class="badge bg-light text-dark border rounded-pill px-3 py-2 fw-semibold">
                                            @if($reminder->reminder_type === 'bolo')
                                                <i class="fa-solid fa-stamp text-warning me-1"></i>Bolo
                                            @elseif($reminder->reminder_type === 'service_km')
                                                <i class="fa-solid fa-gauge-high text-success me-1"></i>Service (KM)
                                            @elseif($reminder->reminder_type === 'third_party_insurance')
                                                <i class="fa-solid fa-shield-halved text-info me-1"></i>3rd-Party Ins.
                                            @else
                                                <i class="fa-solid fa-car-burst text-primary me-1"></i>Comp. Ins.
                                            @endif
                                        </span>
                                    </td>

                                    {{-- Status Badge --}}
                                    <td>
                                        <span class="badge bg-{{ $computed['class'] }} rounded-pill px-3 py-2 fw-semibold">
                                            <i class="fa-solid {{ $computed['icon'] }} me-1"></i>{{ $computed['label'] }}
                                        </span>
                                    </td>

                                    {{-- Target / Expiry --}}
                                    <td>
                                        @if($reminder->reminder_type === 'service_km')
                                            <div>
                                                <span class="fw-bold text-dark">{{ number_format($reminder->next_service_km) }} KM</span>
                                                <small class="text-muted d-block">Current: {{ number_format($reminder->current_odometer_km) }} KM</small>
                                            </div>
                                        @else
                                            @php $exp = $reminder->getExpiryDate(); @endphp
                                            <div>
                                                <span class="fw-bold text-dark">{{ $exp ? $exp->format('d M Y') : '—' }}</span>
                                                @if($reminder->bolo_last_date || $reminder->insurance_start_date)
                                                    <small class="text-muted d-block">
                                                        Started: {{ ($reminder->bolo_last_date ?? $reminder->insurance_start_date)->format('d M Y') }}
                                                    </small>
                                                @endif
                                            </div>
                                        @endif
                                    </td>

                                    {{-- Remaining --}}
                                    <td>
                                        @if($reminder->reminder_type === 'service_km')
                                            @if($kmRemaining !== null)
                                                @if($kmRemaining <= 0)
                                                    <span class="text-danger fw-bold">
                                                        <i class="fa-solid fa-circle-exclamation me-1"></i>Overdue {{ number_format(abs($kmRemaining)) }} KM
                                                    </span>
                                                @else
                                                    <span class="text-{{ $kmRemaining <= ($reminder->reminder_threshold_km ?? 500) ? 'warning' : 'success' }} fw-semibold">
                                                        {{ number_format($kmRemaining) }} KM left
                                                    </span>
                                                @endif
                                            @else
                                                <span class="text-muted">—</span>
                                            @endif
                                        @else
                                            @if($daysRemaining !== null)
                                                @if($daysRemaining < 0)
                                                    <span class="text-danger fw-bold">
                                                        <i class="fa-solid fa-circle-exclamation me-1"></i>Expired {{ abs($daysRemaining) }}d ago
                                                    </span>
                                                @elseif($daysRemaining <= 30)
                                                    <span class="text-warning fw-bold">
                                                        <i class="fa-solid fa-clock me-1"></i>{{ $daysRemaining }} days left
                                                    </span>
                                                @else
                                                    <span class="text-success fw-semibold">
                                                        {{ $daysRemaining }} days left
                                                    </span>
                                                @endif
                                            @else
                                                <span class="text-muted">—</span>
                                            @endif
                                        @endif
                                    </td>

                                    {{-- Details/Notes --}}
                                    <td>
                                        @if($reminder->policy_number)
                                            <div class="small fw-semibold text-dark">Policy: {{ $reminder->policy_number }}</div>
                                            <div class="text-muted small">{{ $reminder->insurance_company }}</div>
                                        @elseif($reminder->notes)
                                            <span class="text-muted small text-truncate d-inline-block" style="max-width: 150px;">{{ $reminder->notes }}</span>
                                        @else
                                            <span class="text-muted small">—</span>
                                        @endif
                                        @if($reminder->attachment)
                                            <a href="{{ asset('storage/' . $reminder->attachment) }}" target="_blank" class="badge bg-light text-primary border text-decoration-none d-inline-block mt-1">
                                                <i class="fa-solid fa-paperclip me-1"></i>File
                                            </a>
                                        @endif
                                    </td>

                                    {{-- Actions --}}
                                    <td class="text-end pe-3">
                                        <div class="btn-group btn-group-sm">
                                            @if($reminder->reminder_type === 'service_km')
                                                <button type="button" class="btn btn-outline-info rounded-start-pill px-2"
                                                        data-bs-toggle="modal" data-bs-target="#odometerModal-{{ $reminder->id }}" title="Update Odometer">
                                                    <i class="fa-solid fa-gauge"></i>
                                                </button>
                                            @endif
                                            <button type="button" class="btn btn-outline-success px-2"
                                                    data-bs-toggle="modal" data-bs-target="#renewModal-{{ $reminder->id }}" title="Renew / Mark Serviced">
                                                <i class="fa-solid fa-rotate"></i> Renew
                                            </button>
                                            <form action="{{ route('general-service.vehicle-reminders.send-sms', $reminder) }}" method="POST" class="d-inline"
                                                  onsubmit="return confirm('Dispatch SMS reminder to General Service team and General Manager (GM) for this vehicle?');">
                                                @csrf
                                                <button type="submit" class="btn btn-outline-success px-2" title="Send SMS Alert to GS & GM">
                                                    <i class="fa-solid fa-comment-sms"></i>
                                                </button>
                                            </form>
                                            <a href="{{ route('general-service.vehicle-reminders.show', $reminder) }}" class="btn btn-outline-secondary px-2" title="View Detail">
                                                <i class="fa-solid fa-eye"></i>
                                            </a>
                                            <a href="{{ route('general-service.vehicle-reminders.edit', $reminder) }}" class="btn btn-outline-primary px-2" title="Edit">
                                                <i class="fa-solid fa-pen"></i>
                                            </a>
                                            <button type="button" class="btn btn-outline-danger rounded-end-pill px-2"
                                                    data-bs-toggle="modal" data-bs-target="#deleteModal-{{ $reminder->id }}" title="Delete">
                                                <i class="fa-solid fa-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>

                                {{-- Renew Modal --}}
                                <div class="modal fade" id="renewModal-{{ $reminder->id }}" tabindex="-1" aria-hidden="true">
                                    <div class="modal-dialog modal-dialog-centered">
                                        <div class="modal-content rounded-4 border-0 shadow">
                                            <form action="{{ route('general-service.vehicle-reminders.renew', $reminder) }}" method="POST" enctype="multipart/form-data">
                                                @csrf
                                                <div class="modal-header border-bottom border-light">
                                                    <h5 class="modal-title fw-bold">
                                                        <i class="fa-solid fa-rotate text-success me-2"></i>Renew Reminder: {{ $reminder->reminder_type_label }}
                                                    </h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                </div>
                                                <div class="modal-body p-4">
                                                    <p class="text-muted small">
                                                        This will mark the current record as <strong>Renewed</strong>, save it in the audit history, and create the new active schedule.
                                                    </p>

                                                    {{-- Conditional inputs based on type --}}
                                                    @if($reminder->reminder_type === 'bolo')
                                                        <div class="mb-3">
                                                            <label class="form-label fw-bold">New Bolo Inspection Date</label>
                                                            <input type="date" name="bolo_last_date" class="form-control" value="{{ date('Y-m-d') }}" required>
                                                        </div>
                                                        <div class="mb-3">
                                                            <label class="form-label fw-bold">New Bolo Expiry Date <span class="text-danger">*</span></label>
                                                            <input type="date" name="bolo_expiry_date" class="form-control" value="{{ date('Y-m-d', strtotime('+1 year')) }}" required>
                                                        </div>
                                                    @elseif($reminder->reminder_type === 'service_km')
                                                        <div class="mb-3">
                                                            <label class="form-label fw-bold">Service Completed at KM <span class="text-danger">*</span></label>
                                                            <input type="number" name="last_service_km" class="form-control" value="{{ $reminder->current_odometer_km }}" required>
                                                        </div>
                                                        <div class="mb-3">
                                                            <label class="form-label fw-bold">Current Odometer (KM) <span class="text-danger">*</span></label>
                                                            <input type="number" name="current_odometer_km" class="form-control" value="{{ $reminder->current_odometer_km }}" required>
                                                        </div>
                                                        <div class="mb-3">
                                                            <label class="form-label fw-bold">Service Interval (KM) <span class="text-danger">*</span></label>
                                                            <input type="number" name="service_interval_km" class="form-control" value="{{ $reminder->service_interval_km ?? 5000 }}" required>
                                                            <small class="text-muted">Next service will be calculated as Last Service KM + Interval.</small>
                                                        </div>
                                                        <div class="mb-3">
                                                            <label class="form-label fw-bold">Service Date</label>
                                                            <input type="date" name="last_service_date" class="form-control" value="{{ date('Y-m-d') }}">
                                                        </div>
                                                    @else
                                                        {{-- Insurance --}}
                                                        <div class="mb-3">
                                                            <label class="form-label fw-bold">Insurance Company <span class="text-danger">*</span></label>
                                                            <input type="text" name="insurance_company" class="form-control" value="{{ $reminder->insurance_company }}" required>
                                                        </div>
                                                        <div class="mb-3">
                                                            <label class="form-label">New Policy Number</label>
                                                            <input type="text" name="policy_number" class="form-control" placeholder="New policy number">
                                                        </div>
                                                        <div class="row g-2 mb-3">
                                                            <div class="col-6">
                                                                <label class="form-label fw-bold">Start Date <span class="text-danger">*</span></label>
                                                                <input type="date" name="insurance_start_date" class="form-control" value="{{ date('Y-m-d') }}" required>
                                                            </div>
                                                            <div class="col-6">
                                                                <label class="form-label fw-bold">Expiry Date <span class="text-danger">*</span></label>
                                                                <input type="date" name="insurance_expiry_date" class="form-control" value="{{ date('Y-m-d', strtotime('+1 year')) }}" required>
                                                            </div>
                                                        </div>
                                                        <div class="mb-3">
                                                            <label class="form-label">Premium Amount (ETB)</label>
                                                            <input type="number" step="0.01" name="premium_amount" class="form-control" value="{{ $reminder->premium_amount }}">
                                                        </div>
                                                        @if($reminder->reminder_type === 'insurance')
                                                            <div class="mb-3">
                                                                <label class="form-label">Coverage Type</label>
                                                                <input type="text" name="coverage_type" class="form-control" value="{{ $reminder->coverage_type }}">
                                                            </div>
                                                        @endif
                                                    @endif

                                                    {{-- Shared: Attachment & Notes --}}
                                                    <div class="mb-3">
                                                        <label class="form-label">New Attachment (Certificate / Receipt)</label>
                                                        <input type="file" name="attachment_file" class="form-control" accept="image/*,.pdf">
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label">Renewal Notes</label>
                                                        <textarea name="notes" class="form-control" rows="2" placeholder="e.g. Renewed at Addis Ababa transport bureau"></textarea>
                                                    </div>
                                                </div>
                                                <div class="modal-footer border-top border-light">
                                                    <button type="button" class="btn btn-light rounded-pill px-3" data-bs-dismiss="modal">Cancel</button>
                                                    <button type="submit" class="btn btn-success rounded-pill px-4 fw-semibold">
                                                        <i class="fa-solid fa-check me-1"></i>Confirm Renewal
                                                    </button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>

                                {{-- Odometer Modal --}}
                                @if($reminder->reminder_type === 'service_km')
                                    <div class="modal fade" id="odometerModal-{{ $reminder->id }}" tabindex="-1" aria-hidden="true">
                                        <div class="modal-dialog modal-dialog-centered">
                                            <div class="modal-content rounded-4 border-0 shadow">
                                                <form action="{{ route('general-service.vehicle-reminders.odometer', $reminder) }}" method="POST">
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
                                                            <div class="fw-bold text-dark">{{ $reminder->fixedAsset?->name }} ({{ $reminder->assetUnit?->plate_number ?? $reminder->assetUnit?->unit_code }})</div>
                                                        </div>
                                                        <div class="row g-2 mb-3">
                                                            <div class="col-6">
                                                                <label class="form-label text-muted small">Current Logged</label>
                                                                <input type="text" class="form-control" value="{{ number_format($reminder->current_odometer_km) }} KM" readonly>
                                                            </div>
                                                            <div class="col-6">
                                                                <label class="form-label text-muted small">Next Service At</label>
                                                                <input type="text" class="form-control" value="{{ number_format($reminder->next_service_km) }} KM" readonly>
                                                            </div>
                                                        </div>
                                                        <div class="mb-3">
                                                            <label for="list_odometer_{{ $reminder->id }}" class="form-label fw-bold">New Odometer Reading (KM) <span class="text-danger">*</span></label>
                                                            <div class="input-group">
                                                                <input type="number" id="list_odometer_{{ $reminder->id }}" name="current_odometer_km" class="form-control"
                                                                       min="{{ $reminder->current_odometer_km }}" value="{{ $reminder->current_odometer_km }}" required>
                                                                <span class="input-group-text">KM</span>
                                                            </div>
                                                        </div>
                                                        <div class="mb-3">
                                                            <label for="list_notes_{{ $reminder->id }}" class="form-label">Notes</label>
                                                            <textarea id="list_notes_{{ $reminder->id }}" name="notes" class="form-control" rows="2" placeholder="e.g. Regular weekly meter check"></textarea>
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

                                {{-- Delete Modal --}}
                                <div class="modal fade" id="deleteModal-{{ $reminder->id }}" tabindex="-1" aria-hidden="true">
                                    <div class="modal-dialog modal-dialog-centered">
                                        <div class="modal-content rounded-4 border-0 shadow">
                                            <form action="{{ route('general-service.vehicle-reminders.destroy', $reminder) }}" method="POST">
                                                @csrf
                                                @method('DELETE')
                                                <div class="modal-header border-bottom border-light">
                                                    <h5 class="modal-title fw-bold text-danger">
                                                        <i class="fa-solid fa-trash me-2"></i>Delete Reminder
                                                    </h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                </div>
                                                <div class="modal-body p-4 text-center">
                                                    <div class="bg-danger bg-opacity-10 text-danger rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width: 50px; height: 50px;">
                                                        <i class="fa-solid fa-triangle-exclamation fs-4"></i>
                                                    </div>
                                                    <h6>Are you sure you want to delete this reminder?</h6>
                                                    <p class="text-muted small mb-0">
                                                        <strong>{{ $reminder->reminder_type_label }}</strong> for <strong>{{ $reminder->vehicle_display_name }}</strong>. This action can be audited in history.
                                                    </p>
                                                </div>
                                                <div class="modal-footer border-top border-light">
                                                    <button type="button" class="btn btn-light rounded-pill px-3" data-bs-dismiss="modal">Cancel</button>
                                                    <button type="submit" class="btn btn-danger rounded-pill px-4 fw-semibold">
                                                        Yes, Delete
                                                    </button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                {{-- Pagination --}}
                <div class="p-3 border-top border-light d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <span class="text-muted small">Showing {{ $reminders->firstItem() ?? 0 }} to {{ $reminders->lastItem() ?? 0 }} of {{ $reminders->total() }}</span>
                    <div>
                        {{ $reminders->links() }}
                    </div>
                </div>
            @endif
        </div>
    </div>

</div>
@endsection
