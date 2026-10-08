@extends('layouts.app')
@section('title', 'General Service — New Vehicle Reminder')

@section('content')
<div class="container-fluid px-3 px-md-4 py-3">

    {{-- Breadcrumbs & Header --}}
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-1 text-muted small">
                    <li class="breadcrumb-item"><a href="{{ route('dashboard.general_service') }}" class="text-decoration-none">General Service</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('general-service.vehicle-reminders.dashboard') }}" class="text-decoration-none">Vehicle Reminders</a></li>
                    <li class="breadcrumb-item active" aria-current="page">New Reminder</li>
                </ol>
            </nav>
            <h1 class="h3 fw-bold mb-0 text-dark">
                <i class="fa-solid fa-plus-circle text-primary me-2"></i>Create Vehicle Reminder
            </h1>
            <p class="text-muted small mb-0">Set up a new expiration or service milestone tracking for a company vehicle</p>
        </div>
        <div>
            <a href="{{ route('general-service.vehicle-reminders.index') }}" class="btn btn-outline-secondary rounded-pill px-3">
                <i class="fa-solid fa-arrow-left me-1"></i>Back to Reminders
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
        {{-- Form Column --}}
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-body p-4">
                    <form action="{{ route('general-service.vehicle-reminders.store') }}" method="POST" enctype="multipart/form-data" id="reminderForm">
                        @csrf

                        {{-- Hidden fixed_asset_id populated via JS --}}
                        <input type="hidden" name="fixed_asset_id" id="fixed_asset_id" value="{{ old('fixed_asset_id') }}">

                        {{-- ── 1. Vehicle Selection ── --}}
                        <h5 class="fw-bold text-dark border-bottom pb-2 mb-3">
                            <span class="badge bg-primary rounded-circle me-2" style="width:24px;height:24px;display:inline-flex;align-items:center;justify-content:center;">1</span>
                            Vehicle Information
                        </h5>

                        <div class="mb-4">
                            <label for="fixed_asset_unit_id" class="form-label fw-bold">Select Vehicle (Fixed Asset) <span class="text-danger">*</span></label>
                            <select name="fixed_asset_unit_id" id="fixed_asset_unit_id" class="form-select rounded-3 shadow-none @error('fixed_asset_unit_id') is-invalid @enderror" required>
                                <option value="">-- Choose a Vehicle (Plate Number / Asset Code / Model) --</option>
                                @foreach($vehicleUnits as $unit)
                                    <option value="{{ $unit->id }}"
                                            data-asset-id="{{ $unit->fixed_asset_id }}"
                                            data-name="{{ $unit->parentAsset?->name }}"
                                            data-plate="{{ $unit->plate_number ?: 'N/A' }}"
                                            data-code="{{ $unit->unit_code }}"
                                            data-model="{{ trim(($unit->brand ?? '') . ' ' . ($unit->model ?? '')) ?: 'N/A' }}"
                                            data-year="{{ $unit->year ?: 'N/A' }}"
                                            {{ old('fixed_asset_unit_id') == $unit->id ? 'selected' : '' }}>
                                        {{ $unit->unit_code }} — {{ $unit->parentAsset?->name }} (Plate: {{ $unit->plate_number ?: 'No Plate' }})
                                    </option>
                                @endforeach
                            </select>
                            <small class="text-muted">Filtered automatically from Fixed Assets category Vehicles/Cars.</small>
                        </div>

                        {{-- ── 2. Reminder Type ── --}}
                        <h5 class="fw-bold text-dark border-bottom pb-2 mb-3 mt-4">
                            <span class="badge bg-primary rounded-circle me-2" style="width:24px;height:24px;display:inline-flex;align-items:center;justify-content:center;">2</span>
                            Reminder Type
                        </h5>

                        <div class="row g-3 mb-4">
                            <div class="col-md-6">
                                <label class="card h-100 p-3 rounded-4 border type-card cursor-pointer" id="card_bolo">
                                    <div class="d-flex align-items-center gap-3">
                                        <input type="radio" name="reminder_type" value="bolo" class="form-check-input" {{ old('reminder_type', 'bolo') === 'bolo' ? 'checked' : '' }}>
                                        <div>
                                            <div class="fw-bold text-dark"><i class="fa-solid fa-stamp text-warning me-2"></i>Bolo (Annual Inspection)</div>
                                            <small class="text-muted">Annual roadworthiness and safety inspection due date</small>
                                        </div>
                                    </div>
                                </label>
                            </div>
                            <div class="col-md-6">
                                <label class="card h-100 p-3 rounded-4 border type-card cursor-pointer" id="card_service_km">
                                    <div class="d-flex align-items-center gap-3">
                                        <input type="radio" name="reminder_type" value="service_km" class="form-check-input" {{ old('reminder_type') === 'service_km' ? 'checked' : '' }}>
                                        <div>
                                            <div class="fw-bold text-dark"><i class="fa-solid fa-gauge-high text-success me-2"></i>Service by KM</div>
                                            <small class="text-muted">Oil change, periodic service based on odometer mileage</small>
                                        </div>
                                    </div>
                                </label>
                            </div>
                            <div class="col-md-6">
                                <label class="card h-100 p-3 rounded-4 border type-card cursor-pointer" id="card_third_party">
                                    <div class="d-flex align-items-center gap-3">
                                        <input type="radio" name="reminder_type" value="third_party_insurance" class="form-check-input" {{ old('reminder_type') === 'third_party_insurance' ? 'checked' : '' }}>
                                        <div>
                                            <div class="fw-bold text-dark"><i class="fa-solid fa-shield-halved text-info me-2"></i>Third-Party Insurance</div>
                                            <small class="text-muted">Mandatory third-party liability insurance policy</small>
                                        </div>
                                    </div>
                                </label>
                            </div>
                            <div class="col-md-6">
                                <label class="card h-100 p-3 rounded-4 border type-card cursor-pointer" id="card_insurance">
                                    <div class="d-flex align-items-center gap-3">
                                        <input type="radio" name="reminder_type" value="insurance" class="form-check-input" {{ old('reminder_type') === 'insurance' ? 'checked' : '' }}>
                                        <div>
                                            <div class="fw-bold text-dark"><i class="fa-solid fa-car-burst text-primary me-2"></i>Comprehensive Insurance</div>
                                            <small class="text-muted">Full vehicle comprehensive insurance coverage</small>
                                        </div>
                                    </div>
                                </label>
                            </div>
                        </div>

                        {{-- ── 3. Dynamic Fields Section ── --}}
                        <h5 class="fw-bold text-dark border-bottom pb-2 mb-3 mt-4">
                            <span class="badge bg-primary rounded-circle me-2" style="width:24px;height:24px;display:inline-flex;align-items:center;justify-content:center;">3</span>
                            Schedule &amp; Tracking Details
                        </h5>

                        {{-- 3A. Bolo Section --}}
                        <div id="section_bolo" class="type-section">
                            <div class="row g-3 mb-3">
                                <div class="col-md-6">
                                    <label for="bolo_last_date" class="form-label">Last Bolo Inspection Date</label>
                                    <input type="date" id="bolo_last_date" name="bolo_last_date" class="form-control rounded-3" value="{{ old('bolo_last_date') }}">
                                    <small class="text-muted">Date the previous Bolo was issued (optional)</small>
                                </div>
                                <div class="col-md-6">
                                    <label for="bolo_expiry_date" class="form-label fw-bold">Bolo Expiry / Next Due Date <span class="text-danger">*</span></label>
                                    <input type="date" id="bolo_expiry_date" name="bolo_expiry_date" class="form-control rounded-3" value="{{ old('bolo_expiry_date') }}">
                                    <small class="text-muted">The expiration date printed on the sticker</small>
                                </div>
                            </div>
                        </div>

                        {{-- 3B. Service by KM Section --}}
                        <div id="section_service_km" class="type-section d-none">
                            <div class="row g-3 mb-3">
                                <div class="col-md-6">
                                    <label for="current_odometer_km" class="form-label fw-bold">Current Odometer (KM) <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <input type="number" id="current_odometer_km" name="current_odometer_km" class="form-control rounded-start-3"
                                               value="{{ old('current_odometer_km', 0) }}" min="0" placeholder="e.g. 45200">
                                        <span class="input-group-text">KM</span>
                                    </div>
                                    <small class="text-muted">Current vehicle dashboard mileage</small>
                                </div>
                                <div class="col-md-6">
                                    <label for="last_service_km" class="form-label fw-bold">Last Service KM <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <input type="number" id="last_service_km" name="last_service_km" class="form-control rounded-start-3"
                                               value="{{ old('last_service_km', 0) }}" min="0" placeholder="e.g. 40000">
                                        <span class="input-group-text">KM</span>
                                    </div>
                                    <small class="text-muted">Mileage when the last service was performed</small>
                                </div>
                                <div class="col-md-6">
                                    <label for="service_interval_km" class="form-label fw-bold">Service Interval (KM) <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <input type="number" id="service_interval_km" name="service_interval_km" class="form-control rounded-start-3"
                                               value="{{ old('service_interval_km', 5000) }}" min="1" placeholder="e.g. 5000">
                                        <span class="input-group-text">KM</span>
                                    </div>
                                    <small class="text-muted">Standard interval (e.g. 5,000 KM or 10,000 KM)</small>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-bold">Calculated Next Service KM</label>
                                    <div class="input-group">
                                        <input type="text" id="calculated_next_km" class="form-control bg-light fw-bold text-primary" readonly value="—">
                                        <span class="input-group-text">KM</span>
                                    </div>
                                    <small class="text-muted">Auto-calculated: Last Service KM + Interval</small>
                                </div>
                                <div class="col-md-6">
                                    <label for="last_service_date" class="form-label">Last Service Date</label>
                                    <input type="date" id="last_service_date" name="last_service_date" class="form-control rounded-3" value="{{ old('last_service_date') }}">
                                </div>
                                <div class="col-md-6">
                                    <label for="reminder_threshold_km" class="form-label">Alert Threshold (KM)</label>
                                    <div class="input-group">
                                        <input type="number" id="reminder_threshold_km" name="reminder_threshold_km" class="form-control rounded-start-3"
                                               value="{{ old('reminder_threshold_km', 500) }}" min="0">
                                        <span class="input-group-text">KM</span>
                                    </div>
                                    <small class="text-muted">Trigger "Due Soon" alert when this many KM remain (default: 500 KM)</small>
                                </div>
                            </div>
                        </div>

                        {{-- 3C. Insurance Section (shared by Third-Party & Comprehensive) --}}
                        <div id="section_insurance" class="type-section d-none">
                            <div class="row g-3 mb-3">
                                <div class="col-md-6">
                                    <label for="insurance_company" class="form-label fw-bold">Insurance Company <span class="text-danger">*</span></label>
                                    <input type="text" id="insurance_company" name="insurance_company" class="form-control rounded-3"
                                           value="{{ old('insurance_company') }}" placeholder="e.g. Nyala Insurance, Awash, Nile...">
                                </div>
                                <div class="col-md-6">
                                    <label for="policy_number" class="form-label">Policy Number</label>
                                    <input type="text" id="policy_number" name="policy_number" class="form-control rounded-3"
                                           value="{{ old('policy_number') }}" placeholder="e.g. POL-2026-9812">
                                </div>
                                <div class="col-md-6">
                                    <label for="insurance_start_date" class="form-label fw-bold">Policy Start Date <span class="text-danger">*</span></label>
                                    <input type="date" id="insurance_start_date" name="insurance_start_date" class="form-control rounded-3"
                                           value="{{ old('insurance_start_date') }}">
                                </div>
                                <div class="col-md-6">
                                    <label for="insurance_expiry_date" class="form-label fw-bold">Policy Expiry Date <span class="text-danger">*</span></label>
                                    <input type="date" id="insurance_expiry_date" name="insurance_expiry_date" class="form-control rounded-3"
                                           value="{{ old('insurance_expiry_date') }}">
                                </div>
                                <div class="col-md-6">
                                    <label for="premium_amount" class="form-label">Premium Amount (ETB)</label>
                                    <div class="input-group">
                                        <input type="number" step="0.01" id="premium_amount" name="premium_amount" class="form-control rounded-start-3"
                                               value="{{ old('premium_amount') }}" placeholder="0.00">
                                        <span class="input-group-text">ETB</span>
                                    </div>
                                </div>
                                <div class="col-md-6" id="field_coverage_type">
                                    <label for="coverage_type" class="form-label">Coverage Type</label>
                                    <input type="text" id="coverage_type" name="coverage_type" class="form-control rounded-3"
                                           value="{{ old('coverage_type') }}" placeholder="e.g. Full Comprehensive, Collision Only">
                                </div>
                            </div>
                        </div>

                        {{-- ── 4. Shared Fields: Attachments, Alert Days, Notes ── --}}
                        <h5 class="fw-bold text-dark border-bottom pb-2 mb-3 mt-4">
                            <span class="badge bg-primary rounded-circle me-2" style="width:24px;height:24px;display:inline-flex;align-items:center;justify-content:center;">4</span>
                            Attachment &amp; Notes
                        </h5>

                        <div class="row g-3 mb-4">
                            <div class="col-md-6">
                                <label for="attachment_file" class="form-label">Supporting Document / Sticker Photo (PDF or Image)</label>
                                <input type="file" id="attachment_file" name="attachment_file" class="form-control rounded-3" accept="image/*,.pdf">
                                <small class="text-muted">Upload scan of Bolo sticker, insurance slip, or maintenance receipt (max 10MB)</small>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Advance Warning Alerts</label>
                                <div class="d-flex flex-wrap gap-3 mt-1">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="alert_days_before[]" value="30" id="alert_30" checked>
                                        <label class="form-check-label small" for="alert_30">30 Days</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="alert_days_before[]" value="15" id="alert_15" checked>
                                        <label class="form-check-label small" for="alert_15">15 Days</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="alert_days_before[]" value="7" id="alert_7" checked>
                                        <label class="form-check-label small" for="alert_7">7 Days</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="alert_days_before[]" value="1" id="alert_1" checked>
                                        <label class="form-check-label small" for="alert_1">1 Day</label>
                                    </div>
                                </div>
                                <small class="text-muted">System will flag the record as "Due Soon" according to these intervals.</small>
                            </div>

                            <div class="col-12">
                                <label for="notes" class="form-label">Notes &amp; Additional Information</label>
                                <textarea id="notes" name="notes" class="form-control rounded-3" rows="3" placeholder="Enter any specific instructions, issuing office, contact person, or notes...">{{ old('notes') }}</textarea>
                            </div>
                        </div>

                        {{-- Submit Buttons --}}
                        <div class="d-flex justify-content-end gap-2 border-top pt-3">
                            <a href="{{ route('general-service.vehicle-reminders.index') }}" class="btn btn-light rounded-pill px-4">Cancel</a>
                            <button type="submit" class="btn btn-primary rounded-pill px-5 fw-bold shadow-sm">
                                <i class="fa-solid fa-check me-1"></i>Save Reminder
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        {{-- Preview & Helper Column --}}
        <div class="col-lg-4">
            {{-- Vehicle Details Preview Card --}}
            <div class="card border-0 shadow-sm rounded-4 mb-4" id="vehiclePreviewCard">
                <div class="card-header bg-white border-bottom border-light py-3">
                    <h6 class="fw-bold mb-0 text-dark">
                        <i class="fa-solid fa-truck text-primary me-2"></i>Selected Vehicle Details
                    </h6>
                </div>
                <div class="card-body p-3">
                    <div id="noVehicleSelected" class="text-center py-4 text-muted">
                        <i class="fa-solid fa-car-side fs-1 mb-2 opacity-25"></i>
                        <p class="small mb-0">Select a vehicle from the dropdown to see its specifications here.</p>
                    </div>
                    <div id="vehicleDetails" class="d-none">
                        <div class="d-flex align-items-center gap-2 mb-3">
                            <span class="badge bg-dark text-white rounded-pill px-3 py-2 fs-6" id="previewPlate">Plate: —</span>
                            <span class="badge bg-light text-secondary border rounded-pill px-2 py-1" id="previewCode">Code: —</span>
                        </div>
                        <ul class="list-group list-group-flush small">
                            <li class="list-group-item d-flex justify-content-between px-0 py-2">
                                <span class="text-muted">Vehicle Name:</span>
                                <span class="fw-bold text-dark text-end" id="previewName">—</span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between px-0 py-2">
                                <span class="text-muted">Model / Brand:</span>
                                <span class="fw-bold text-dark text-end" id="previewModel">—</span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between px-0 py-2">
                                <span class="text-muted">Manufacturing Year:</span>
                                <span class="fw-bold text-dark text-end" id="previewYear">—</span>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>

            {{-- Policy / Guidelines Helper Card --}}
            <div class="card border-0 shadow-sm rounded-4 bg-light">
                <div class="card-body p-3">
                    <h6 class="fw-bold text-dark mb-2"><i class="fa-solid fa-circle-info text-info me-2"></i>Reminder Rules</h6>
                    <ul class="text-muted small ps-3 mb-0" style="line-height: 1.7;">
                        <li><strong>Bolo:</strong> Annual vehicle roadworthiness inspection. Warnings start 30 days before expiration.</li>
                        <li><strong>Service by KM:</strong> Oil changes and preventative servicing based on mileage. Due Soon trigger is within 500 KM of the interval.</li>
                        <li><strong>Third-Party:</strong> Required liability insurance for all operating vehicles.</li>
                        <li><strong>Renewal:</strong> Once renewed, click "Renew" to archive the current record to history and roll over to the new period.</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

</div>

{{-- Dynamic JavaScript for Form Interactivity --}}
<script>
document.addEventListener('DOMContentLoaded', function() {
    const vehicleSelect = document.getElementById('fixed_asset_unit_id');
    const assetIdInput = document.getElementById('fixed_asset_id');
    const vehicleDetails = document.getElementById('vehicleDetails');
    const noVehicleSelected = document.getElementById('noVehicleSelected');
    const previewPlate = document.getElementById('previewPlate');
    const previewCode = document.getElementById('previewCode');
    const previewName = document.getElementById('previewName');
    const previewModel = document.getElementById('previewModel');
    const previewYear = document.getElementById('previewYear');

    function updateVehiclePreview() {
        const selected = vehicleSelect.options[vehicleSelect.selectedIndex];
        if (selected && selected.value) {
            assetIdInput.value = selected.getAttribute('data-asset-id') || '';
            previewPlate.textContent = 'Plate: ' + (selected.getAttribute('data-plate') || 'N/A');
            previewCode.textContent = 'Code: ' + (selected.getAttribute('data-code') || 'N/A');
            previewName.textContent = selected.getAttribute('data-name') || '—';
            previewModel.textContent = selected.getAttribute('data-model') || '—';
            previewYear.textContent = selected.getAttribute('data-year') || '—';

            vehicleDetails.classList.remove('d-none');
            noVehicleSelected.classList.add('d-none');
        } else {
            assetIdInput.value = '';
            vehicleDetails.classList.add('d-none');
            noVehicleSelected.classList.remove('d-none');
        }
    }

    vehicleSelect.addEventListener('change', updateVehiclePreview);
    updateVehiclePreview();

    // Type switching
    const typeRadios = document.querySelectorAll('input[name="reminder_type"]');
    const secBolo = document.getElementById('section_bolo');
    const secService = document.getElementById('section_service_km');
    const secInsurance = document.getElementById('section_insurance');
    const fieldCoverage = document.getElementById('field_coverage_type');

    function updateTypeSections() {
        const selectedType = document.querySelector('input[name="reminder_type"]:checked')?.value || 'bolo';

        secBolo.classList.add('d-none');
        secService.classList.add('d-none');
        secInsurance.classList.add('d-none');

        if (selectedType === 'bolo') {
            secBolo.classList.remove('d-none');
        } else if (selectedType === 'service_km') {
            secService.classList.remove('d-none');
            calculateNextKm();
        } else if (selectedType === 'third_party_insurance') {
            secInsurance.classList.remove('d-none');
            fieldCoverage.classList.add('d-none');
        } else if (selectedType === 'insurance') {
            secInsurance.classList.remove('d-none');
            fieldCoverage.classList.remove('d-none');
        }
    }

    typeRadios.forEach(radio => radio.addEventListener('change', updateTypeSections));
    updateTypeSections();

    // Next KM auto-calculator
    const lastKmInput = document.getElementById('last_service_km');
    const intervalKmInput = document.getElementById('service_interval_km');
    const calcNextKm = document.getElementById('calculated_next_km');

    function calculateNextKm() {
        const lastKm = parseInt(lastKmInput.value) || 0;
        const interval = parseInt(intervalKmInput.value) || 0;
        if (lastKm > 0 || interval > 0) {
            calcNextKm.value = (lastKm + interval).toLocaleString();
        } else {
            calcNextKm.value = '—';
        }
    }

    if (lastKmInput && intervalKmInput) {
        lastKmInput.addEventListener('input', calculateNextKm);
        intervalKmInput.addEventListener('input', calculateNextKm);
        calculateNextKm();
    }
});
</script>

<style>
.cursor-pointer { cursor: pointer; }
.type-card { transition: all 0.2s ease-in-out; }
.type-card:hover { border-color: #3b82f6 !important; background-color: #f8fafc; }
</style>
@endsection
