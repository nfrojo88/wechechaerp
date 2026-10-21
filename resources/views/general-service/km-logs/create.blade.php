@extends('layouts.app')
@section('title', 'Record Vehicle KM Log')

@section('content')
<div class="container-fluid px-3 px-md-4 py-3">

    {{-- Breadcrumbs & Header --}}
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-1 text-muted small">
                    <li class="breadcrumb-item"><a href="{{ route('dashboard.general_service') }}" class="text-decoration-none">General Service</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('vehicle-km-logs.index') }}" class="text-decoration-none">KM Logs</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Record Reading</li>
                </ol>
            </nav>
            <h1 class="h3 fw-bold mb-0 text-dark">
                <i class="fa-solid fa-gauge-high text-primary me-2"></i>Record Vehicle Odometer Reading
            </h1>
            <p class="text-muted small mb-0">Record the current dashboard kilometer reading for routine maintenance tracking.</p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('vehicle-km-logs.index') }}" class="btn btn-outline-secondary rounded-pill px-3 shadow-sm">
                <i class="fa-solid fa-arrow-left me-1"></i>Back to KM Logs
            </a>
        </div>
    </div>

    {{-- Alerts --}}
    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm rounded-4 mb-4" role="alert">
            <i class="fa-solid fa-triangle-exclamation me-2"></i>{{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif
    @if($errors->any())
        <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm rounded-4 mb-4" role="alert">
            <div class="fw-bold mb-1"><i class="fa-solid fa-triangle-exclamation me-2"></i>Please resolve the following errors:</div>
            <ul class="mb-0 small ps-3">
                @foreach($errors->all() as $err)
                    <li>{{ $err }}</li>
                @endforeach
            </ul>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <div class="row g-4">
        {{-- Form Column --}}
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm rounded-4 p-4 bg-white">
                <form action="{{ route('vehicle-km-logs.store') }}" method="POST" enctype="multipart/form-data" id="kmLogForm">
                    @csrf

                    <div class="row g-3">
                        {{-- 1. Select Vehicle --}}
                        <div class="col-md-6">
                            <label for="fixed_asset_unit_id" class="form-label fw-semibold text-dark">
                                Vehicle (Plate / Code) <span class="text-danger">*</span>
                            </label>
                            <select name="fixed_asset_unit_id" id="fixed_asset_unit_id" class="form-select @error('fixed_asset_unit_id') is-invalid @enderror" required onchange="handleVehicleChange(this.value)">
                                <option value="">— Select Vehicle —</option>
                                @foreach($vehicles as $v)
                                    <option value="{{ $v->id }}" {{ (old('fixed_asset_unit_id', $selectedUnitId) == $v->id) ? 'selected' : '' }}>
                                        {{ $v->plate_number ? 'Plate: ' . $v->plate_number : $v->unit_code }} — {{ $v->parentAsset?->name }} ({{ $v->unit_code }})
                                    </option>
                                @endforeach
                            </select>
                            @error('fixed_asset_unit_id')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        {{-- 2. Log Date --}}
                        <div class="col-md-6">
                            <label for="km_date" class="form-label fw-semibold text-dark">
                                Reading Date <span class="text-danger">*</span>
                            </label>
                            <input type="date" name="km_date" id="km_date" class="form-control @error('km_date') is-invalid @enderror"
                                   value="{{ old('km_date', date('Y-m-d')) }}" max="{{ date('Y-m-d') }}" required>
                            @error('km_date')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        {{-- 3. Previous Odometer Reference --}}
                        <div class="col-md-6">
                            <label class="form-label fw-semibold text-muted small">
                                Last Recorded Odometer
                            </label>
                            <div class="input-group">
                                <span class="input-group-text bg-light"><i class="fa-solid fa-clock-rotate-left"></i></span>
                                <input type="text" id="display_last_odometer" class="form-control bg-light font-monospace"
                                       value="{{ number_format($lastOdometer) }} KM" readonly>
                            </div>
                            <small class="text-muted">New reading must be greater than or equal to this value.</small>
                        </div>

                        {{-- 4. New Odometer Reading --}}
                        <div class="col-md-6">
                            <label for="odometer_km" class="form-label fw-semibold text-dark">
                                Current Odometer (KM) <span class="text-danger">*</span>
                            </label>
                            <div class="input-group">
                                <span class="input-group-text bg-primary text-white"><i class="fa-solid fa-gauge-high"></i></span>
                                <input type="number" name="odometer_km" id="odometer_km"
                                       class="form-control font-monospace fw-bold fs-5 @error('odometer_km') is-invalid @enderror"
                                       min="{{ $lastOdometer }}"
                                       value="{{ old('odometer_km', $lastOdometer ?: '') }}"
                                       placeholder="e.g. 45000" required>
                                <span class="input-group-text">KM</span>
                            </div>
                            @error('odometer_km')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <div id="kmJumpWarning" class="alert alert-warning py-1 px-2 mt-2 small d-none">
                                <i class="fa-solid fa-triangle-exclamation me-1"></i>Large jump detected (> 1,000 KM). Please double check the reading.
                            </div>
                        </div>

                        {{-- 5. Driver / Employee --}}
                        <div class="col-md-6">
                            <label for="driver_employee_id" class="form-label fw-semibold text-dark">
                                Driver / Custodian
                            </label>
                            <select name="driver_employee_id" id="driver_employee_id" class="form-select @error('driver_employee_id') is-invalid @enderror">
                                <option value="">— Auto-detect or Unassigned —</option>
                                @foreach($drivers as $d)
                                    <option value="{{ $d->id }}" {{ (old('driver_employee_id', $selectedVehicle?->assigned_to_employee_id) == $d->id) ? 'selected' : '' }}>
                                        {{ $d->full_name }} ({{ $d->role_title ?: 'Driver' }})
                                    </option>
                                @endforeach
                            </select>
                            @error('driver_employee_id')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        {{-- 6. Dashboard Photo --}}
                        <div class="col-md-6">
                            <label for="odometer_photo" class="form-label fw-semibold text-dark">
                                Meter Photo (Optional)
                            </label>
                            <input type="file" name="odometer_photo" id="odometer_photo" accept="image/*,application/pdf"
                                   class="form-control @error('odometer_photo') is-invalid @enderror">
                            <small class="text-muted">Upload a clear photo of the dashboard odometer (JPEG, PNG, max 10MB).</small>
                            @error('odometer_photo')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        {{-- 7. Notes --}}
                        <div class="col-12">
                            <label for="notes" class="form-label fw-semibold text-dark">
                                Notes / Purpose
                            </label>
                            <textarea name="notes" id="notes" rows="2" class="form-control"
                                      placeholder="e.g. End of day return, routine trip meter check, route to Adama site...">{{ old('notes') }}</textarea>
                        </div>
                    </div>

                    <div class="d-flex justify-content-end gap-2 mt-4 pt-3 border-top">
                        <a href="{{ route('vehicle-km-logs.index') }}" class="btn btn-light rounded-pill px-4">Cancel</a>
                        <button type="submit" class="btn btn-primary rounded-pill px-4 fw-semibold shadow-sm">
                            <i class="fa-solid fa-save me-1"></i>Save Odometer Reading
                        </button>
                    </div>
                </form>
            </div>
        </div>

        {{-- Info & Service Proximity Sidebar --}}
        <div class="col-lg-4">
            {{-- Service Due Proximity Card --}}
            <div class="card border-0 shadow-sm rounded-4 mb-4 overflow-hidden">
                <div class="card-header bg-white py-3 border-0">
                    <h6 class="fw-bold mb-0 text-dark">
                        <i class="fa-solid fa-wrench text-warning me-2"></i>Service Proximity Alert
                    </h6>
                    <small class="text-muted">Calculated automatically from this reading</small>
                </div>
                <div class="card-body p-3">
                    @if($serviceStatus && $serviceStatus['has_service_reminder'])
                        <div class="p-3 rounded-3 mb-3 bg-light border">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span class="text-muted small">Next Scheduled Service</span>
                                <strong class="font-monospace text-dark">{{ number_format($serviceStatus['next_service_km']) }} KM</strong>
                            </div>
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span class="text-muted small">Remaining Distance</span>
                                <strong class="font-monospace {{ $serviceStatus['is_overdue'] ? 'text-danger' : ($serviceStatus['is_due_soon'] ? 'text-warning' : 'text-success') }}">
                                    {{ $serviceStatus['remaining_km'] !== null ? number_format($serviceStatus['remaining_km']) . ' KM' : '—' }}
                                </strong>
                            </div>
                            <div class="mt-2 text-center">
                                <span class="badge bg-{{ $serviceStatus['badge']['class'] }} fs-6 rounded-pill px-3 py-1">
                                    <i class="fa-solid {{ $serviceStatus['badge']['icon'] }} me-1"></i>{{ $serviceStatus['badge']['label'] }}
                                </span>
                            </div>
                        </div>

                        @if($serviceStatus['is_due_soon'] || $serviceStatus['is_overdue'])
                            <div class="alert alert-warning border-0 small rounded-3 mb-0">
                                <i class="fa-solid fa-bell me-1"></i>
                                <strong>General Service Notice:</strong> Saving this reading will trigger an alert for workshop inspection.
                            </div>
                        @endif
                    @else
                        <div class="text-center py-4 text-muted small">
                            <i class="fa-solid fa-circle-info fa-2x text-secondary opacity-50 mb-2 d-block"></i>
                            Select a vehicle above to preview its service intervals and status.
                        </div>
                    @endif
                </div>
            </div>

            {{-- Validation Rules Card --}}
            <div class="card border-0 shadow-sm rounded-4 bg-light">
                <div class="card-body p-3">
                    <h6 class="fw-bold mb-2 text-dark small text-uppercase">Odometer Rules</h6>
                    <ul class="text-muted small ps-3 mb-0">
                        <li class="mb-1">Odometer reading <strong>cannot go backward</strong> (must be $\ge$ last reading).</li>
                        <li class="mb-1">Jumps greater than 1,000 KM in one entry are automatically flagged for review.</li>
                        <li>Updates the vehicle's KM-based service reminder in real time.</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

</div>

<script>
let lastKnownKm = {{ $lastOdometer ?: 0 }};

function handleVehicleChange(unitId) {
    if (!unitId) return;
    window.location.href = "{{ route('vehicle-km-logs.create') }}?vehicle_id=" + unitId;
}

document.getElementById('odometer_km')?.addEventListener('input', function(e) {
    const val = parseInt(e.target.value) || 0;
    const warning = document.getElementById('kmJumpWarning');
    if (lastKnownKm > 0 && (val - lastKnownKm) >= 1000) {
        warning?.classList.remove('d-none');
    } else {
        warning?.classList.add('d-none');
    }
});
</script>
@endsection
