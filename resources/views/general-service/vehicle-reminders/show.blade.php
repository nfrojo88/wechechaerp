@extends('layouts.app')
@section('title', 'General Service — Vehicle Reminder Details')

@section('content')
<div class="container-fluid px-3 px-md-4 py-3">

    {{-- Breadcrumbs & Header --}}
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-1 text-muted small">
                    <li class="breadcrumb-item"><a href="{{ route('dashboard.general_service') }}" class="text-decoration-none">General Service</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('general-service.vehicle-reminders.dashboard') }}" class="text-decoration-none">Vehicle Reminders</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('general-service.vehicle-reminders.index') }}" class="text-decoration-none">All Reminders</a></li>
                    <li class="breadcrumb-item active" aria-current="page">{{ $vehicleReminder->reminder_type_label }}</li>
                </ol>
            </nav>
            <div class="d-flex align-items-center gap-2">
                <h1 class="h3 fw-bold mb-0 text-dark">{{ $vehicleReminder->reminder_type_label }}</h1>
                @php $status = $vehicleReminder->computed_status; @endphp
                <span class="badge bg-{{ $status['class'] }} rounded-pill px-3 py-2 fw-semibold">
                    <i class="fa-solid {{ $status['icon'] }} me-1"></i>{{ $status['label'] }}
                </span>
            </div>
            <p class="text-muted small mb-0">For {{ $vehicleReminder->vehicle_display_name }}</p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            @if($vehicleReminder->reminder_type === 'service_km')
                <button type="button" class="btn btn-outline-info rounded-pill px-3 shadow-sm"
                        data-bs-toggle="modal" data-bs-target="#odometerModal">
                    <i class="fa-solid fa-gauge-high me-1"></i>Update Odometer
                </button>
            @endif
            <button type="button" class="btn btn-success rounded-pill px-3 shadow-sm fw-semibold"
                    data-bs-toggle="modal" data-bs-target="#renewModal">
                <i class="fa-solid fa-rotate me-1"></i>Renew / Mark Serviced
            </button>
            <a href="{{ route('general-service.vehicle-reminders.edit', $vehicleReminder) }}" class="btn btn-outline-primary rounded-pill px-3 shadow-sm">
                <i class="fa-solid fa-pen me-1"></i>Edit
            </a>
            <a href="{{ route('general-service.vehicle-reminders.vehicle-detail', $vehicleReminder->fixed_asset_unit_id) }}" class="btn btn-outline-secondary rounded-pill px-3 shadow-sm">
                <i class="fa-solid fa-car me-1"></i>Vehicle Hub
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

    <div class="row g-4">
        {{-- Left Column: Main Reminder Details --}}
        <div class="col-lg-8">

            {{-- Vehicle Header Card --}}
            <div class="card border-0 shadow-sm rounded-4 mb-4 overflow-hidden">
                <div class="card-body p-4 bg-gradient" style="background: linear-gradient(135deg, #1e293b 0%, #334155 100%); color: white;">
                    <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
                        <div>
                            <span class="badge bg-warning text-dark rounded-pill px-3 py-1 mb-2 fw-semibold">
                                <i class="fa-solid fa-truck me-1"></i>Company Vehicle
                            </span>
                            <h3 class="fw-bold mb-1 text-white">{{ $vehicleReminder->fixedAsset?->name ?? 'Vehicle' }}</h3>
                            <div class="d-flex flex-wrap gap-2 align-items-center opacity-75 small">
                                <span>Unit Code: <strong>{{ $vehicleReminder->assetUnit?->unit_code }}</strong></span>
                                <span>&bull;</span>
                                <span>Category: {{ $vehicleReminder->fixedAsset?->category }}</span>
                                @if($vehicleReminder->assetUnit?->brand || $vehicleReminder->assetUnit?->model)
                                    <span>&bull;</span>
                                    <span>{{ trim(($vehicleReminder->assetUnit?->brand ?? '') . ' ' . ($vehicleReminder->assetUnit?->model ?? '')) }}</span>
                                @endif
                            </div>
                        </div>
                        <div class="text-end">
                            @if($vehicleReminder->assetUnit?->plate_number)
                                <div class="bg-white text-dark rounded-3 px-3 py-2 fw-bold fs-5 shadow-sm border border-2 border-warning" style="letter-spacing: 1px;">
                                    {{ $vehicleReminder->assetUnit->plate_number }}
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            {{-- Reminder Specific Milestones Card --}}
            <div class="card border-0 shadow-sm rounded-4 mb-4">
                <div class="card-header bg-white border-bottom border-light py-3">
                    <h5 class="fw-bold mb-0 text-dark">
                        <i class="fa-solid fa-calendar-check text-primary me-2"></i>Reminder Details &amp; Milestones
                    </h5>
                </div>
                <div class="card-body p-4">

                    @if($vehicleReminder->reminder_type === 'bolo')
                        @php
                            $expiry = $vehicleReminder->bolo_expiry_date;
                            $daysLeft = $vehicleReminder->days_until_expiry;
                        @endphp
                        <div class="row g-4 mb-3">
                            <div class="col-md-6">
                                <div class="p-3 rounded-4 bg-light">
                                    <span class="text-muted small fw-semibold text-uppercase d-block">Last Inspection Date</span>
                                    <div class="fs-5 fw-bold text-dark mt-1">
                                        {{ $vehicleReminder->bolo_last_date ? $vehicleReminder->bolo_last_date->format('d M Y') : 'Not Recorded' }}
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="p-3 rounded-4 {{ $daysLeft !== null && $daysLeft <= 0 ? 'bg-danger bg-opacity-10' : ($daysLeft <= 30 ? 'bg-warning bg-opacity-10' : 'bg-success bg-opacity-10') }}">
                                    <span class="text-muted small fw-semibold text-uppercase d-block">Expiry / Next Due Date</span>
                                    <div class="fs-5 fw-bold {{ $daysLeft !== null && $daysLeft <= 0 ? 'text-danger' : ($daysLeft <= 30 ? 'text-warning' : 'text-success') }} mt-1">
                                        {{ $expiry ? $expiry->format('d M Y') : '—' }}
                                    </div>
                                    <small class="fw-semibold">
                                        @if($daysLeft !== null)
                                            @if($daysLeft < 0)
                                                Expired {{ abs($daysLeft) }} days ago
                                            @elseif($daysLeft === 0)
                                                Expires today!
                                            @else
                                                {{ $daysLeft }} days remaining
                                            @endif
                                        @endif
                                    </small>
                                </div>
                            </div>
                        </div>

                    @elseif($vehicleReminder->reminder_type === 'service_km')
                        @php
                            $curr = $vehicleReminder->current_odometer_km ?? 0;
                            $last = $vehicleReminder->last_service_km ?? 0;
                            $next = $vehicleReminder->next_service_km ?? 0;
                            $interval = $vehicleReminder->service_interval_km ?? 5000;
                            $kmLeft = $vehicleReminder->km_remaining;
                            $progressPct = $interval > 0 ? min(100, max(0, round((($curr - $last) / $interval) * 100))) : 0;
                        @endphp
                        <div class="row g-3 mb-4">
                            <div class="col-md-3 col-6">
                                <div class="p-3 rounded-4 bg-light text-center">
                                    <span class="text-muted small text-uppercase d-block">Current KM</span>
                                    <div class="fs-5 fw-bold text-dark mt-1">{{ number_format($curr) }}</div>
                                </div>
                            </div>
                            <div class="col-md-3 col-6">
                                <div class="p-3 rounded-4 bg-light text-center">
                                    <span class="text-muted small text-uppercase d-block">Last Service KM</span>
                                    <div class="fs-5 fw-bold text-dark mt-1">{{ number_format($last) }}</div>
                                </div>
                            </div>
                            <div class="col-md-3 col-6">
                                <div class="p-3 rounded-4 bg-light text-center">
                                    <span class="text-muted small text-uppercase d-block">Interval</span>
                                    <div class="fs-5 fw-bold text-dark mt-1">{{ number_format($interval) }} KM</div>
                                </div>
                            </div>
                            <div class="col-md-3 col-6">
                                <div class="p-3 rounded-4 {{ $kmLeft !== null && $kmLeft <= 0 ? 'bg-danger bg-opacity-10 text-danger' : ($kmLeft <= 500 ? 'bg-warning bg-opacity-10 text-warning' : 'bg-primary bg-opacity-10 text-primary') }} text-center">
                                    <span class="text-muted small text-uppercase d-block">Next Service KM</span>
                                    <div class="fs-5 fw-bold mt-1">{{ number_format($next) }}</div>
                                </div>
                            </div>
                        </div>

                        {{-- Service Interval Progress Bar --}}
                        <div class="mb-4">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <span class="fw-semibold small text-muted">Interval Mileage Progress</span>
                                <span class="fw-bold small {{ $progressPct >= 100 ? 'text-danger' : ($progressPct >= 90 ? 'text-warning' : 'text-success') }}">
                                    {{ $progressPct }}% ({{ $kmLeft !== null && $kmLeft <= 0 ? 'Overdue by ' . number_format(abs($kmLeft)) . ' KM' : number_format($kmLeft) . ' KM remaining' }})
                                </span>
                            </div>
                            <div class="progress" style="height: 12px; border-radius: 6px;">
                                <div class="progress-bar {{ $progressPct >= 100 ? 'bg-danger' : ($progressPct >= 90 ? 'bg-warning' : 'bg-success') }}"
                                     role="progressbar" style="width: {{ $progressPct }}%"></div>
                            </div>
                        </div>

                        @if($vehicleReminder->last_service_date)
                            <div class="small text-muted mb-3">
                                <i class="fa-solid fa-clock me-1"></i>Last service completed on: <strong>{{ $vehicleReminder->last_service_date->format('d M Y') }}</strong>
                            </div>
                        @endif

                    @else
                        {{-- Insurance Policy --}}
                        @php
                            $exp = $vehicleReminder->insurance_expiry_date;
                            $daysLeft = $vehicleReminder->days_until_expiry;
                        @endphp
                        <div class="row g-3 mb-4">
                            <div class="col-md-6">
                                <div class="p-3 rounded-4 bg-light">
                                    <span class="text-muted small text-uppercase d-block">Insurance Company</span>
                                    <div class="fs-5 fw-bold text-dark mt-1">{{ $vehicleReminder->insurance_company }}</div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="p-3 rounded-4 bg-light">
                                    <span class="text-muted small text-uppercase d-block">Policy Number</span>
                                    <div class="fs-5 fw-bold text-dark mt-1">{{ $vehicleReminder->policy_number ?: 'Not specified' }}</div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="p-3 rounded-4 bg-light">
                                    <span class="text-muted small text-uppercase d-block">Policy Start</span>
                                    <div class="fw-bold text-dark mt-1">{{ $vehicleReminder->insurance_start_date ? $vehicleReminder->insurance_start_date->format('d M Y') : '—' }}</div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="p-3 rounded-4 {{ $daysLeft !== null && $daysLeft <= 0 ? 'bg-danger bg-opacity-10' : ($daysLeft <= 30 ? 'bg-warning bg-opacity-10' : 'bg-success bg-opacity-10') }}">
                                    <span class="text-muted small text-uppercase d-block">Policy Expiry</span>
                                    <div class="fw-bold {{ $daysLeft !== null && $daysLeft <= 0 ? 'text-danger' : ($daysLeft <= 30 ? 'text-warning' : 'text-success') }} mt-1">
                                        {{ $exp ? $exp->format('d M Y') : '—' }}
                                    </div>
                                    <small class="fw-semibold">
                                        @if($daysLeft !== null)
                                            @if($daysLeft < 0)
                                                Expired {{ abs($daysLeft) }} days ago
                                            @elseif($daysLeft === 0)
                                                Expires today!
                                            @else
                                                {{ $daysLeft }} days left
                                            @endif
                                        @endif
                                    </small>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="p-3 rounded-4 bg-light">
                                    <span class="text-muted small text-uppercase d-block">Premium Amount</span>
                                    <div class="fw-bold text-dark mt-1">
                                        {{ $vehicleReminder->premium_amount ? number_format($vehicleReminder->premium_amount, 2) . ' ETB' : '—' }}
                                    </div>
                                </div>
                            </div>
                            @if($vehicleReminder->coverage_type)
                                <div class="col-12">
                                    <div class="p-3 rounded-4 bg-light">
                                        <span class="text-muted small text-uppercase d-block">Coverage Type</span>
                                        <div class="fw-bold text-dark mt-1">{{ $vehicleReminder->coverage_type }}</div>
                                    </div>
                                </div>
                            @endif
                        </div>
                    @endif

                    {{-- Attachment & Notes --}}
                    <div class="border-top pt-3">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="text-muted small fw-semibold text-uppercase d-block">Attached Document / Sticker</label>
                                @if($vehicleReminder->attachment)
                                    <div class="mt-2">
                                        <a href="{{ asset('storage/' . $vehicleReminder->attachment) }}" target="_blank" class="btn btn-outline-primary btn-sm rounded-pill px-3">
                                            <i class="fa-solid fa-file-arrow-down me-1"></i>View / Download File
                                        </a>
                                    </div>
                                @else
                                    <span class="text-muted small">No file attached.</span>
                                @endif
                            </div>
                            <div class="col-md-6">
                                <label class="text-muted small fw-semibold text-uppercase d-block">Notes</label>
                                <p class="text-dark small mb-0 mt-1">{{ $vehicleReminder->notes ?: 'No notes provided.' }}</p>
                            </div>
                        </div>
                    </div>

                </div>
            </div>

            {{-- Audit & Renewal History Log --}}
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-header bg-white border-bottom border-light py-3 d-flex justify-content-between align-items-center">
                    <h5 class="fw-bold mb-0 text-dark">
                        <i class="fa-solid fa-timeline text-secondary me-2"></i>Renewal &amp; Activity History
                    </h5>
                    <span class="badge bg-secondary rounded-pill">{{ $vehicleReminder->history->count() }} records</span>
                </div>
                <div class="card-body p-0">
                    @if($vehicleReminder->history->isEmpty())
                        <div class="text-center py-4 text-muted small">
                            No past renewals or updates recorded yet.
                        </div>
                    @else
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0 small">
                                <thead class="table-light text-muted text-uppercase" style="font-size: 0.7rem;">
                                    <tr>
                                        <th class="ps-3">Date &amp; Time</th>
                                        <th>Action</th>
                                        <th>Details / Notes</th>
                                        <th class="pe-3">Performed By</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($vehicleReminder->history as $hist)
                                        <tr>
                                            <td class="ps-3 text-muted">{{ $hist->performed_at?->format('d M Y H:i') }}</td>
                                            <td>
                                                <span class="badge {{ $hist->action === 'renewed' ? 'bg-success' : ($hist->action === 'odometer_updated' ? 'bg-info' : 'bg-secondary') }} rounded-pill px-2">
                                                    {{ ucfirst(str_replace('_', ' ', $hist->action)) }}
                                                </span>
                                            </td>
                                            <td class="text-dark">{{ $hist->notes ?: '—' }}</td>
                                            <td class="pe-3 text-muted">{{ $hist->performer?->name ?? 'System' }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>

        </div>

        {{-- Right Column: Other Reminders for Same Vehicle & Meta --}}
        <div class="col-lg-4">

            {{-- Other Reminders for this Vehicle --}}
            <div class="card border-0 shadow-sm rounded-4 mb-4">
                <div class="card-header bg-white border-bottom border-light py-3">
                    <h6 class="fw-bold mb-0 text-dark">
                        <i class="fa-solid fa-list-check text-warning me-2"></i>Other Reminders for this Vehicle
                    </h6>
                </div>
                <div class="card-body p-3">
                    @if($otherReminders->isEmpty())
                        <div class="text-center py-3 text-muted small">
                            <p class="mb-2">No other reminders set up for this vehicle.</p>
                            <a href="{{ route('general-service.vehicle-reminders.create', ['fixed_asset_unit_id' => $vehicleReminder->fixed_asset_unit_id]) }}" class="btn btn-outline-primary btn-sm rounded-pill px-3">
                                <i class="fa-solid fa-plus me-1"></i>Add Another Type
                            </a>
                        </div>
                    @else
                        <div class="d-flex flex-column gap-2">
                            @foreach($otherReminders as $other)
                                @php $otherStat = $other->computed_status; @endphp
                                <a href="{{ route('general-service.vehicle-reminders.show', $other) }}" class="card text-decoration-none border rounded-3 p-2 text-dark hover-light">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <span class="fw-bold small">{{ $other->reminder_type_label }}</span>
                                        <span class="badge bg-{{ $otherStat['class'] }} rounded-pill" style="font-size:0.68rem;">{{ $otherStat['label'] }}</span>
                                    </div>
                                    <div class="text-muted small mt-1">
                                        @if($other->reminder_type === 'service_km')
                                            Target: {{ number_format($other->next_service_km) }} KM
                                        @else
                                            Expiry: {{ $other->getExpiryDate() ? $other->getExpiryDate()->format('d M Y') : '—' }}
                                        @endif
                                    </div>
                                </a>
                            @endforeach
                        </div>
                        <div class="mt-3 text-center">
                            <a href="{{ route('general-service.vehicle-reminders.create') }}" class="btn btn-outline-primary btn-sm rounded-pill px-3">
                                <i class="fa-solid fa-plus me-1"></i>Add Reminder
                            </a>
                        </div>
                    @endif
                </div>
            </div>

            {{-- Audit Meta Box --}}
            <div class="card border-0 shadow-sm rounded-4 bg-light">
                <div class="card-body p-3">
                    <h6 class="fw-bold text-dark mb-2 small"><i class="fa-solid fa-circle-info text-info me-1"></i>Record Information</h6>
                    <ul class="list-group list-group-flush bg-transparent small">
                        <li class="list-group-item bg-transparent d-flex justify-content-between px-0 py-1">
                            <span class="text-muted">Status:</span>
                            <span class="fw-bold">{{ ucfirst($vehicleReminder->status) }}</span>
                        </li>
                        <li class="list-group-item bg-transparent d-flex justify-content-between px-0 py-1">
                            <span class="text-muted">Created:</span>
                            <span>{{ $vehicleReminder->created_at?->format('d M Y') }}</span>
                        </li>
                        <li class="list-group-item bg-transparent d-flex justify-content-between px-0 py-1">
                            <span class="text-muted">Created By:</span>
                            <span>{{ $vehicleReminder->creator?->name ?? 'System' }}</span>
                        </li>
                    </ul>
                </div>
            </div>

        </div>
    </div>

</div>

{{-- Renew Modal --}}
<div class="modal fade" id="renewModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-4 border-0 shadow">
            <form action="{{ route('general-service.vehicle-reminders.renew', $vehicleReminder) }}" method="POST" enctype="multipart/form-data">
                @csrf
                <div class="modal-header border-bottom border-light">
                    <h5 class="modal-title fw-bold">
                        <i class="fa-solid fa-rotate text-success me-2"></i>Renew {{ $vehicleReminder->reminder_type_label }}
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <p class="text-muted small">
                        Archiving current record to history and creating the next active schedule.
                    </p>

                    @if($vehicleReminder->reminder_type === 'bolo')
                        <div class="mb-3">
                            <label class="form-label fw-bold">New Inspection Date</label>
                            <input type="date" name="bolo_last_date" class="form-control" value="{{ date('Y-m-d') }}" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">New Expiry Date <span class="text-danger">*</span></label>
                            <input type="date" name="bolo_expiry_date" class="form-control" value="{{ date('Y-m-d', strtotime('+1 year')) }}" required>
                        </div>
                    @elseif($vehicleReminder->reminder_type === 'service_km')
                        <div class="mb-3">
                            <label class="form-label fw-bold">Service Completed at KM <span class="text-danger">*</span></label>
                            <input type="number" name="last_service_km" class="form-control" value="{{ $vehicleReminder->current_odometer_km }}" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">Current Odometer (KM) <span class="text-danger">*</span></label>
                            <input type="number" name="current_odometer_km" class="form-control" value="{{ $vehicleReminder->current_odometer_km }}" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">Service Interval (KM) <span class="text-danger">*</span></label>
                            <input type="number" name="service_interval_km" class="form-control" value="{{ $vehicleReminder->service_interval_km ?? 5000 }}" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">Service Date</label>
                            <input type="date" name="last_service_date" class="form-control" value="{{ date('Y-m-d') }}">
                        </div>
                    @else
                        <div class="mb-3">
                            <label class="form-label fw-bold">Insurance Company <span class="text-danger">*</span></label>
                            <input type="text" name="insurance_company" class="form-control" value="{{ $vehicleReminder->insurance_company }}" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">New Policy Number</label>
                            <input type="text" name="policy_number" class="form-control" placeholder="Policy number">
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
                            <input type="number" step="0.01" name="premium_amount" class="form-control" value="{{ $vehicleReminder->premium_amount }}">
                        </div>
                        @if($vehicleReminder->reminder_type === 'insurance')
                            <div class="mb-3">
                                <label class="form-label">Coverage Type</label>
                                <input type="text" name="coverage_type" class="form-control" value="{{ $vehicleReminder->coverage_type }}">
                            </div>
                        @endif
                    @endif

                    <div class="mb-3">
                        <label class="form-label">New Document / Receipt (PDF/Image)</label>
                        <input type="file" name="attachment_file" class="form-control" accept="image/*,.pdf">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Notes</label>
                        <textarea name="notes" class="form-control" rows="2" placeholder="e.g. Completed inspection and renewed sticker"></textarea>
                    </div>
                </div>
                <div class="modal-footer border-top border-light">
                    <button type="button" class="btn btn-light rounded-pill px-3" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success rounded-pill px-4 fw-semibold">Confirm Renewal</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Odometer Modal --}}
@if($vehicleReminder->reminder_type === 'service_km')
<div class="modal fade" id="odometerModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-4 border-0 shadow">
            <form action="{{ route('general-service.vehicle-reminders.odometer', $vehicleReminder) }}" method="POST">
                @csrf
                <div class="modal-header border-bottom border-light">
                    <h5 class="modal-title fw-bold">
                        <i class="fa-solid fa-gauge-high text-info me-2"></i>Update Odometer (KM)
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label text-muted small">Current Logged</label>
                            <input type="text" class="form-control" value="{{ number_format($vehicleReminder->current_odometer_km) }} KM" readonly>
                        </div>
                        <div class="col-6">
                            <label class="form-label text-muted small">Next Service At</label>
                            <input type="text" class="form-control" value="{{ number_format($vehicleReminder->next_service_km) }} KM" readonly>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="show_odometer_km" class="form-label fw-bold">New Reading (KM) <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <input type="number" id="show_odometer_km" name="current_odometer_km" class="form-control"
                                   min="{{ $vehicleReminder->current_odometer_km }}" value="{{ $vehicleReminder->current_odometer_km }}" required>
                            <span class="input-group-text">KM</span>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="show_notes" class="form-label">Notes</label>
                        <textarea id="show_notes" name="notes" class="form-control" rows="2" placeholder="e.g. End of month meter check"></textarea>
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
