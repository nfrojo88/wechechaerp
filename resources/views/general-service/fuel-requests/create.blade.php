@extends('layouts.app')
@section('title', 'Request Vehicle Fuel')

@section('content')
<div class="container-fluid px-3 px-md-4 py-3">

    {{-- Breadcrumbs & Header --}}
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-1 text-muted small">
                    <li class="breadcrumb-item"><a href="{{ route('dashboard.general_service') }}" class="text-decoration-none">General Service</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('vehicle-fuel-requests.index') }}" class="text-decoration-none">Fuel Requests</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Request Fuel</li>
                </ol>
            </nav>
            <h1 class="h3 fw-bold mb-0 text-dark">
                <i class="fa-solid fa-gas-pump text-warning me-2"></i>New Vehicle Fuel Request
            </h1>
            <p class="text-muted small mb-0">Submit fuel replenishment for company vehicles. Odometer reading will update service records, and the payment request routes directly to the Finance Head.</p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('vehicle-fuel-requests.index') }}" class="btn btn-outline-secondary rounded-pill px-3 shadow-sm">
                <i class="fa-solid fa-arrow-left me-1"></i>Back to Requests
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

    {{-- Unreceipted Previous Request Alert --}}
    @if($unreceiptedReq)
        <div class="alert {{ $policy === 'block' ? 'alert-danger' : 'alert-warning' }} border-0 shadow-sm rounded-4 mb-4 p-3" role="alert">
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                <div>
                    <h6 class="fw-bold mb-1">
                        <i class="fa-solid fa-receipt me-2"></i>Outstanding Fuel Station Receipt Required
                    </h6>
                    <div class="small">
                        The previous fuel request <strong>#{{ $unreceiptedReq->request_no }}</strong> for this vehicle ({{ $selectedVehicle?->plate_number ?: $selectedVehicle?->unit_code }}) was paid on {{ $unreceiptedReq->created_at?->format('d M Y') }} but has <strong>no station receipt attached yet</strong>.
                        @if($policy === 'block')
                            Under company fuel policy, new requests are <strong>blocked</strong> until the previous receipt is uploaded.
                        @else
                            Under company policy, please attach previous receipts promptly.
                        @endif
                    </div>
                </div>
                <div>
                    <a href="{{ route('vehicle-fuel-requests.show', $unreceiptedReq->id) }}" class="btn btn-sm btn-dark rounded-pill px-3">
                        <i class="fa-solid fa-upload me-1"></i>Upload Receipt for #{{ $unreceiptedReq->request_no }}
                    </a>
                </div>
            </div>
        </div>
    @endif

    <div class="row g-4">
        {{-- Main Form Column --}}
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm rounded-4 p-4 bg-white">
                <form action="{{ route('vehicle-fuel-requests.store') }}" method="POST" enctype="multipart/form-data" id="fuelRequestForm">
                    @csrf

                    <div class="row g-3">
                        {{-- 1. Vehicle Selection --}}
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

                        {{-- 2. Driver / Custodian --}}
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

                        {{-- 3. Current Odometer Reading --}}
                        <div class="col-md-6">
                            <label for="current_odometer_km" class="form-label fw-semibold text-dark">
                                Current Dashboard Odometer (KM) <span class="text-danger">*</span>
                            </label>
                            <div class="input-group">
                                <span class="input-group-text bg-light"><i class="fa-solid fa-gauge-high"></i></span>
                                <input type="number" name="current_odometer_km" id="current_odometer_km"
                                       class="form-control font-monospace fw-bold @error('current_odometer_km') is-invalid @enderror"
                                       min="{{ $lastOdometer }}"
                                       value="{{ old('current_odometer_km', $lastOdometer ?: '') }}"
                                       placeholder="e.g. 45000" required>
                                <span class="input-group-text">KM</span>
                            </div>
                            <small class="text-muted">Last recorded: <strong>{{ number_format($lastOdometer) }} KM</strong>. Cannot be lower.</small>
                            @error('current_odometer_km')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        {{-- 4. Fuel Type --}}
                        <div class="col-md-6">
                            <label for="fuel_type" class="form-label fw-semibold text-dark">
                                Fuel Type <span class="text-danger">*</span>
                            </label>
                            <select name="fuel_type" id="fuel_type" class="form-select @error('fuel_type') is-invalid @enderror" required>
                                <option value="diesel" {{ old('fuel_type') === 'diesel' ? 'selected' : '' }}>Diesel (ናፍጣ)</option>
                                <option value="benzine" {{ old('fuel_type') === 'benzine' ? 'selected' : '' }}>Benzine / Petrol (ቤንዚን)</option>
                            </select>
                            @error('fuel_type')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        {{-- 5. Liters Requested --}}
                        <div class="col-md-6">
                            <label for="liters_requested" class="form-label fw-semibold text-dark">
                                Volume Needed (Liters) <span class="text-danger">*</span>
                            </label>
                            <div class="input-group">
                                <span class="input-group-text bg-light"><i class="fa-solid fa-oil-can"></i></span>
                                <input type="number" step="0.5" name="liters_requested" id="liters_requested"
                                       class="form-control font-monospace fw-bold @error('liters_requested') is-invalid @enderror"
                                       value="{{ old('liters_requested') }}"
                                       placeholder="e.g. 50" required>
                                <span class="input-group-text">Liters</span>
                            </div>
                            @error('liters_requested')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        {{-- 6. Estimated Amount (ETB) --}}
                        <div class="col-md-6">
                            <label for="estimated_amount" class="form-label fw-semibold text-dark">
                                Estimated Cost (ETB) <span class="text-danger">*</span>
                            </label>
                            <div class="input-group">
                                <span class="input-group-text bg-success text-white fw-bold">ETB</span>
                                <input type="number" step="0.01" name="estimated_amount" id="estimated_amount"
                                       class="form-control font-monospace fw-bold fs-5 text-success @error('estimated_amount') is-invalid @enderror"
                                       value="{{ old('estimated_amount') }}"
                                       placeholder="e.g. 5500.00" required>
                            </div>
                            @error('estimated_amount')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        {{-- 7. Project Context (Optional) --}}
                        <div class="col-md-6">
                            <label for="project_id" class="form-label fw-semibold text-dark">
                                Project / Cost Center (Optional)
                            </label>
                            <select name="project_id" id="project_id" class="form-select">
                                <option value="">— Head Office / General Operations —</option>
                                @foreach($projects as $p)
                                    <option value="{{ $p->id }}" {{ old('project_id') == $p->id ? 'selected' : '' }}>
                                        {{ $p->name }} ({{ $p->code ?? 'Site' }})
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        {{-- 8. Linked Driver Booking (Optional) --}}
                        <div class="col-md-6">
                            <label for="driver_booking_id" class="form-label fw-semibold text-dark">
                                Linked Logistics Dispatch (Optional)
                            </label>
                            <select name="driver_booking_id" id="driver_booking_id" class="form-select">
                                <option value="">— Standalone Fuel Request —</option>
                                @foreach($recentBookings as $b)
                                    <option value="{{ $b->id }}" {{ old('driver_booking_id') == $b->id ? 'selected' : '' }}>
                                        PR #{{ $b->purchaseRequest?->request_number ?: $b->purchase_request_id }} — {{ $b->scheduled_at?->format('d M') }} ({{ $b->vehicle_number ?: 'Assigned' }})
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        {{-- 9. Meter Photo (Optional) --}}
                        <div class="col-12">
                            <label for="odometer_photo" class="form-label fw-semibold text-dark">
                                Dashboard Odometer Photo (Optional)
                            </label>
                            <input type="file" name="odometer_photo" id="odometer_photo" accept="image/*,application/pdf" class="form-control">
                            <small class="text-muted">Take a photo of the vehicle dashboard odometer before refueling.</small>
                        </div>

                        {{-- 10. Trip Destination & Purpose --}}
                        <div class="col-12">
                            <label for="reason" class="form-label fw-semibold text-dark">
                                Trip Route &amp; Fuel Purpose <span class="text-danger">*</span>
                            </label>
                            <textarea name="reason" id="reason" rows="3" class="form-control @error('reason') is-invalid @enderror"
                                      placeholder="e.g. Field dispatch to Bole site, material delivery from Kality store, weekly site supervision round trip..." required>{{ old('reason') }}</textarea>
                            @error('reason')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        @if($unreceiptedReq && $policy !== 'block')
                            <div class="col-12">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="override_unreceipted" value="1" id="overrideUnreceipted">
                                    <label class="form-check-label small text-muted" for="overrideUnreceipted">
                                        I acknowledge the previous fuel request #{{ $unreceiptedReq->request_no }} is missing a receipt and will upload both receipts promptly.
                                    </label>
                                </div>
                            </div>
                        @endif
                    </div>

                    <div class="d-flex justify-content-between align-items-center mt-4 pt-3 border-top flex-wrap gap-2">
                        <span class="text-muted small">
                            <i class="fa-solid fa-paper-plane me-1 text-primary"></i>Direct routing to Finance Head with priority SMS dispatch.
                        </span>
                        <div class="d-flex gap-2">
                            <a href="{{ route('vehicle-fuel-requests.index') }}" class="btn btn-light rounded-pill px-4">Cancel</a>
                            <button type="submit" class="btn btn-warning text-dark rounded-pill px-4 fw-bold shadow-sm" {{ ($unreceiptedReq && $policy === 'block') ? 'disabled' : '' }}>
                                <i class="fa-solid fa-check me-1"></i>Submit Fuel Request
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        {{-- Sidebar Info Column --}}
        <div class="col-lg-4">
            {{-- Service Due Proximity Banner (Feature C2) --}}
            <div class="card border-0 shadow-sm rounded-4 mb-4 overflow-hidden">
                <div class="card-header bg-white py-3 border-0">
                    <h6 class="fw-bold mb-0 text-dark">
                        <i class="fa-solid fa-wrench text-warning me-2"></i>Routine Service Due Status
                    </h6>
                    <small class="text-muted">Calculated from KM service intervals</small>
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

                        @if($serviceStatus['is_overdue'])
                            <div class="alert alert-danger border-0 small rounded-3 mb-0">
                                <i class="fa-solid fa-triangle-exclamation me-1"></i>
                                <strong>Vehicle Service Overdue:</strong> This vehicle has exceeded its scheduled service interval by {{ abs($serviceStatus['remaining_km']) }} KM. Please schedule maintenance with General Service.
                            </div>
                        @elseif($serviceStatus['is_due_soon'])
                            <div class="alert alert-warning border-0 small rounded-3 mb-0">
                                <i class="fa-solid fa-bell me-1"></i>
                                <strong>Service Due Soon:</strong> This vehicle is within {{ $serviceStatus['remaining_km'] }} KM of its scheduled service. A maintenance inspection should be arranged soon.
                            </div>
                        @endif
                    @else
                        <div class="text-center py-4 text-muted small">
                            <i class="fa-solid fa-car-side fa-2x text-secondary opacity-50 mb-2 d-block"></i>
                            Select a vehicle above to preview its scheduled service status.
                        </div>
                    @endif
                </div>
            </div>

            {{-- Automated Workflow Guarantee Card --}}
            <div class="card border-0 shadow-sm rounded-4" style="background: linear-gradient(135deg, #1e293b 0%, #334155 100%); color:#fff;">
                <div class="card-body p-4">
                    <h6 class="fw-bold mb-2 text-warning"><i class="fa-solid fa-shield-halved me-2"></i>How Payment Works</h6>
                    <ul class="text-white-50 small ps-3 mb-0">
                        <li class="mb-2"><strong>Auto-created Expense:</strong> An official Expense Request is instantly generated under Category <em>Transport</em> with status <em>Approved - Assigned to Finance</em>.</li>
                        <li class="mb-2"><strong>Finance Head Notification:</strong> SMS dispatch is sent immediately to the Finance Head's verified phone.</li>
                        <li class="mb-2"><strong>Cashier Disbursement:</strong> Finance Head assigns the account, and the Cashier disburses payment via the existing payment flow.</li>
                        <li><strong>Receipt Reconciliation:</strong> After fueling, upload the station receipt to close the request and feed into the Expense Hub for monthly VAT records.</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

</div>

<script>
function handleVehicleChange(unitId) {
    if (!unitId) return;
    window.location.href = "{{ route('vehicle-fuel-requests.create') }}?vehicle_id=" + unitId;
}
</script>
@endsection
