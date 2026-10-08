@extends('layouts.app')
@section('title', 'General Service — Edit Vehicle Reminder')

@section('content')
<div class="container-fluid px-3 px-md-4 py-3">

    {{-- Breadcrumbs & Header --}}
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-1 text-muted small">
                    <li class="breadcrumb-item"><a href="{{ route('dashboard.general_service') }}" class="text-decoration-none">General Service</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('general-service.vehicle-reminders.dashboard') }}" class="text-decoration-none">Vehicle Reminders</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Edit Reminder</li>
                </ol>
            </nav>
            <h1 class="h3 fw-bold mb-0 text-dark">
                <i class="fa-solid fa-pen-to-square text-primary me-2"></i>Edit Vehicle Reminder
            </h1>
            <p class="text-muted small mb-0">{{ $vehicleReminder->reminder_type_label }} for {{ $vehicleReminder->vehicle_display_name }}</p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('general-service.vehicle-reminders.show', $vehicleReminder) }}" class="btn btn-outline-secondary rounded-pill px-3">
                <i class="fa-solid fa-arrow-left me-1"></i>View Details
            </a>
            <a href="{{ route('general-service.vehicle-reminders.index') }}" class="btn btn-outline-secondary rounded-pill px-3">
                <i class="fa-solid fa-list me-1"></i>All Reminders
            </a>
        </div>
    </div>

    @if($errors->any())
        <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm rounded-4 mb-4" role="alert">
            <h6 class="fw-bold mb-2"><i class="fa-solid fa-triangle-exclamation me-1"></i>Please correct the following errors:</h6>
            <ul class="mb-0 small ps-3">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <div class="row g-4">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-body p-4">
                    <form action="{{ route('general-service.vehicle-reminders.update', $vehicleReminder) }}" method="POST" enctype="multipart/form-data">
                        @csrf
                        @method('PUT')

                        <input type="hidden" name="fixed_asset_unit_id" value="{{ $vehicleReminder->fixed_asset_unit_id }}">
                        <input type="hidden" name="fixed_asset_id" value="{{ $vehicleReminder->fixed_asset_id }}">
                        <input type="hidden" name="reminder_type" value="{{ $vehicleReminder->reminder_type }}">

                        {{-- Vehicle Info Readonly Display --}}
                        <div class="bg-light rounded-4 p-3 mb-4 d-flex justify-content-between align-items-center">
                            <div>
                                <span class="text-muted small text-uppercase fw-semibold d-block">Vehicle</span>
                                <span class="fw-bold fs-5 text-dark">{{ $vehicleReminder->fixedAsset?->name }}</span>
                                <div class="d-flex gap-2 align-items-center mt-1">
                                    <span class="badge bg-dark text-white rounded-pill px-2">
                                        Plate: {{ $vehicleReminder->assetUnit?->plate_number ?: 'N/A' }}
                                    </span>
                                    <span class="text-muted small">{{ $vehicleReminder->assetUnit?->unit_code }}</span>
                                </div>
                            </div>
                            <span class="badge bg-primary rounded-pill px-3 py-2 fw-semibold fs-6">
                                {{ $vehicleReminder->reminder_type_label }}
                            </span>
                        </div>

                        {{-- Conditional Form Fields Based on Reminder Type --}}
                        @if($vehicleReminder->reminder_type === 'bolo')
                            <h5 class="fw-bold text-dark border-bottom pb-2 mb-3">Bolo Inspection Schedule</h5>
                            <div class="row g-3 mb-4">
                                <div class="col-md-6">
                                    <label for="bolo_last_date" class="form-label">Last Bolo Inspection Date</label>
                                    <input type="date" id="bolo_last_date" name="bolo_last_date" class="form-control rounded-3"
                                           value="{{ old('bolo_last_date', $vehicleReminder->bolo_last_date?->format('Y-m-d')) }}">
                                </div>
                                <div class="col-md-6">
                                    <label for="bolo_expiry_date" class="form-label fw-bold">Bolo Expiry Date <span class="text-danger">*</span></label>
                                    <input type="date" id="bolo_expiry_date" name="bolo_expiry_date" class="form-control rounded-3"
                                           value="{{ old('bolo_expiry_date', $vehicleReminder->bolo_expiry_date?->format('Y-m-d')) }}" required>
                                </div>
                            </div>

                        @elseif($vehicleReminder->reminder_type === 'service_km')
                            <h5 class="fw-bold text-dark border-bottom pb-2 mb-3">Service by KM Schedule</h5>
                            <div class="row g-3 mb-4">
                                <div class="col-md-6">
                                    <label for="current_odometer_km" class="form-label fw-bold">Current Odometer (KM) <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <input type="number" id="current_odometer_km" name="current_odometer_km" class="form-control rounded-start-3"
                                               value="{{ old('current_odometer_km', $vehicleReminder->current_odometer_km) }}" min="0" required>
                                        <span class="input-group-text">KM</span>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <label for="last_service_km" class="form-label fw-bold">Last Service KM <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <input type="number" id="last_service_km" name="last_service_km" class="form-control rounded-start-3"
                                               value="{{ old('last_service_km', $vehicleReminder->last_service_km) }}" min="0" required>
                                        <span class="input-group-text">KM</span>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <label for="service_interval_km" class="form-label fw-bold">Service Interval (KM) <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <input type="number" id="service_interval_km" name="service_interval_km" class="form-control rounded-start-3"
                                               value="{{ old('service_interval_km', $vehicleReminder->service_interval_km) }}" min="1" required>
                                        <span class="input-group-text">KM</span>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <label for="reminder_threshold_km" class="form-label">Alert Threshold (KM)</label>
                                    <div class="input-group">
                                        <input type="number" id="reminder_threshold_km" name="reminder_threshold_km" class="form-control rounded-start-3"
                                               value="{{ old('reminder_threshold_km', $vehicleReminder->reminder_threshold_km) }}" min="0">
                                        <span class="input-group-text">KM</span>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <label for="last_service_date" class="form-label">Last Service Date</label>
                                    <input type="date" id="last_service_date" name="last_service_date" class="form-control rounded-3"
                                           value="{{ old('last_service_date', $vehicleReminder->last_service_date?->format('Y-m-d')) }}">
                                </div>
                            </div>

                        @else
                            {{-- Insurance --}}
                            <h5 class="fw-bold text-dark border-bottom pb-2 mb-3">Insurance Policy Details</h5>
                            <div class="row g-3 mb-4">
                                <div class="col-md-6">
                                    <label for="insurance_company" class="form-label fw-bold">Insurance Company <span class="text-danger">*</span></label>
                                    <input type="text" id="insurance_company" name="insurance_company" class="form-control rounded-3"
                                           value="{{ old('insurance_company', $vehicleReminder->insurance_company) }}" required>
                                </div>
                                <div class="col-md-6">
                                    <label for="policy_number" class="form-label">Policy Number</label>
                                    <input type="text" id="policy_number" name="policy_number" class="form-control rounded-3"
                                           value="{{ old('policy_number', $vehicleReminder->policy_number) }}">
                                </div>
                                <div class="col-md-6">
                                    <label for="insurance_start_date" class="form-label fw-bold">Policy Start Date <span class="text-danger">*</span></label>
                                    <input type="date" id="insurance_start_date" name="insurance_start_date" class="form-control rounded-3"
                                           value="{{ old('insurance_start_date', $vehicleReminder->insurance_start_date?->format('Y-m-d')) }}" required>
                                </div>
                                <div class="col-md-6">
                                    <label for="insurance_expiry_date" class="form-label fw-bold">Policy Expiry Date <span class="text-danger">*</span></label>
                                    <input type="date" id="insurance_expiry_date" name="insurance_expiry_date" class="form-control rounded-3"
                                           value="{{ old('insurance_expiry_date', $vehicleReminder->insurance_expiry_date?->format('Y-m-d')) }}" required>
                                </div>
                                <div class="col-md-6">
                                    <label for="premium_amount" class="form-label">Premium Amount (ETB)</label>
                                    <div class="input-group">
                                        <input type="number" step="0.01" id="premium_amount" name="premium_amount" class="form-control rounded-start-3"
                                               value="{{ old('premium_amount', $vehicleReminder->premium_amount) }}">
                                        <span class="input-group-text">ETB</span>
                                    </div>
                                </div>
                                @if($vehicleReminder->reminder_type === 'insurance')
                                    <div class="col-md-6">
                                        <label for="coverage_type" class="form-label">Coverage Type</label>
                                        <input type="text" id="coverage_type" name="coverage_type" class="form-control rounded-3"
                                               value="{{ old('coverage_type', $vehicleReminder->coverage_type) }}">
                                    </div>
                                @endif
                            </div>
                        @endif

                        {{-- Attachment & Notes --}}
                        <h5 class="fw-bold text-dark border-bottom pb-2 mb-3 mt-4">Attachment &amp; Notes</h5>
                        <div class="row g-3 mb-4">
                            <div class="col-md-6">
                                <label for="attachment_file" class="form-label">Update Attachment File (PDF/Image)</label>
                                <input type="file" id="attachment_file" name="attachment_file" class="form-control rounded-3" accept="image/*,.pdf">
                                @if($vehicleReminder->attachment)
                                    <div class="mt-2 small text-muted">
                                        Current: <a href="{{ asset('storage/' . $vehicleReminder->attachment) }}" target="_blank" class="fw-semibold text-primary">View Current File</a>
                                    </div>
                                @endif
                            </div>
                            <div class="col-md-6">
                                <label for="status" class="form-label fw-bold">Status</label>
                                <select id="status" name="status" class="form-select rounded-3">
                                    <option value="active" {{ old('status', $vehicleReminder->status) === 'active' ? 'selected' : '' }}>Active</option>
                                    <option value="due_soon" {{ old('status', $vehicleReminder->status) === 'due_soon' ? 'selected' : '' }}>Due Soon</option>
                                    <option value="expired" {{ old('status', $vehicleReminder->status) === 'expired' ? 'selected' : '' }}>Expired</option>
                                    <option value="renewed" {{ old('status', $vehicleReminder->status) === 'renewed' ? 'selected' : '' }}>Renewed</option>
                                </select>
                            </div>
                            <div class="col-12">
                                <label for="notes" class="form-label">Notes &amp; Remarks</label>
                                <textarea id="notes" name="notes" class="form-control rounded-3" rows="3">{{ old('notes', $vehicleReminder->notes) }}</textarea>
                            </div>
                        </div>

                        {{-- Action Buttons --}}
                        <div class="d-flex justify-content-end gap-2 border-top pt-3">
                            <a href="{{ route('general-service.vehicle-reminders.show', $vehicleReminder) }}" class="btn btn-light rounded-pill px-4">Cancel</a>
                            <button type="submit" class="btn btn-primary rounded-pill px-5 fw-bold shadow-sm">
                                <i class="fa-solid fa-check me-1"></i>Save Changes
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        {{-- Side Column Info --}}
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm rounded-4 mb-4">
                <div class="card-header bg-white border-bottom border-light py-3">
                    <h6 class="fw-bold mb-0 text-dark">
                        <i class="fa-solid fa-clock-rotate-left text-secondary me-2"></i>Audit Information
                    </h6>
                </div>
                <div class="card-body p-3">
                    <ul class="list-group list-group-flush small">
                        <li class="list-group-item d-flex justify-content-between px-0 py-2">
                            <span class="text-muted">Created Date:</span>
                            <span class="fw-bold text-dark">{{ $vehicleReminder->created_at?->format('d M Y H:i') }}</span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between px-0 py-2">
                            <span class="text-muted">Created By:</span>
                            <span class="fw-bold text-dark">{{ $vehicleReminder->creator?->name ?? 'System' }}</span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between px-0 py-2">
                            <span class="text-muted">Last Updated:</span>
                            <span class="fw-bold text-dark">{{ $vehicleReminder->updated_at?->format('d M Y H:i') }}</span>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

</div>
@endsection
