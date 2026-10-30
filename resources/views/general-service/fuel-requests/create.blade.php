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
                        The previous fuel request <strong>#{{ $unreceiptedReq->request_no }}</strong> for this vehicle ({{ $selectedVehicle->plate_number ?: $selectedVehicle->unit_code }}) was paid on {{ $unreceiptedReq->created_at->format('d M Y') }} but has <strong>no station receipt attached yet</strong>.
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
            @if(!empty($isGeneralAdmin))
                {{-- Mode Switcher for General Admin --}}
                <div class="card border-0 shadow-sm rounded-4 mb-3 bg-light p-2">
                    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 px-2 py-1">
                        <div class="d-flex align-items-center gap-2">
                            <span class="badge bg-warning text-dark fw-bold px-2.5 py-1.5"><i class="fa-solid fa-user-shield me-1"></i>General Admin</span>
                            <span class="small fw-bold text-dark">Requesting Option:</span>
                        </div>
                        <div class="btn-group rounded-pill p-1 bg-white border" role="group">
                            <button type="button" class="btn btn-sm rounded-pill px-3 fw-semibold active" id="btnModeStandard" onclick="setAdminRequestMode('standard')">
                                <i class="fa-solid fa-gauge-high me-1"></i>Standard (With Odometer KM)
                            </button>
                            <button type="button" class="btn btn-sm rounded-pill px-3 fw-bold text-dark" id="btnModeAdminQuick" onclick="setAdminRequestMode('admin_quick')">
                                <i class="fa-solid fa-bolt text-danger me-1"></i>Admin Quick (Money &amp; Price/L — Skip KM)
                            </button>
                        </div>
                    </div>
                </div>
            @endif

            <div class="card border-0 shadow-sm rounded-4 p-4 bg-white">
                <form action="{{ route('vehicle-fuel-requests.store') }}" method="POST" enctype="multipart/form-data" id="fuelRequestForm">
                    @csrf
                    <input type="hidden" name="skip_km" id="skipKmInput" value="{{ old('skip_km', request('mode') === 'admin_direct' ? '1' : '0') }}">

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
                                        {{ $v->plate_number ? 'Plate: ' . $v->plate_number : $v->unit_code }} — {{ $v->parentAsset->name }} ({{ $v->unit_code }})
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
                                    <option value="{{ $d->id }}" {{ (old('driver_employee_id', $selectedVehicle->assigned_to_employee_id) == $d->id) ? 'selected' : '' }}>
                                        {{ $d->full_name }} ({{ $d->role_title ?: 'Driver' }})
                                    </option>
                                @endforeach
                            </select>
                            @error('driver_employee_id')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        {{-- 3. Current Odometer Reading (Hidden in Admin Skip-KM mode) --}}
                        <div class="col-md-6" id="odometerInputContainer">
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

                        @if(!empty($isGeneralAdmin))
                        {{-- Admin Skip-KM Notice Banner --}}
                        <div class="col-md-6 d-none" id="adminSkipKmBanner">
                            <label class="form-label fw-semibold text-muted">Dashboard Odometer (KM)</label>
                            <div class="p-2.5 rounded-3 border border-warning bg-warning bg-opacity-10 d-flex align-items-center gap-2">
                                <i class="fa-solid fa-forward text-warning fs-4"></i>
                                <div>
                                    <div class="fw-bold text-dark small"><i class="fa-solid fa-circle-check text-success me-1"></i>KM Logging is SKIPPED</div>
                                    <small class="text-muted text-xxs">No dashboard odometer reading or photo required for General Admin request.</small>
                                </div>
                            </div>
                        </div>
                        @endif

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

                        {{-- 5. Total Estimated Amount (ETB) --}}
                        <div class="col-md-6" id="estimatedAmountContainer">
                            <label for="estimated_amount" class="form-label fw-semibold text-dark">
                                <span id="estimatedAmountLabel">Estimated Cost (ETB)</span> <span class="text-danger">*</span>
                            </label>
                            <div class="input-group">
                                <span class="input-group-text bg-success text-white fw-bold">ETB</span>
                                <input type="number" step="0.01" name="estimated_amount" id="estimated_amount"
                                       class="form-control font-monospace fw-bold fs-5 text-success @error('estimated_amount') is-invalid @enderror"
                                       value="{{ old('estimated_amount') }}"
                                       placeholder="e.g. 5500.00" required oninput="handleMoneyInput()">
                            </div>
                            <small class="text-muted text-xxs" id="moneyHelpText">Total money requested for this fuel disbursement.</small>
                            @error('estimated_amount')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        @if(!empty($isGeneralAdmin))
                        {{-- 6. Price per 1 Liter (Admin Mode) --}}
                        <div class="col-md-6 d-none" id="pricePerLiterContainer">
                            <label for="price_per_liter" class="form-label fw-semibold text-dark">
                                Price per 1 Liter (How Much?) <span class="text-danger">*</span>
                            </label>
                            <div class="input-group">
                                <input type="number" step="0.01" min="0.01" name="price_per_liter" id="price_per_liter"
                                       class="form-control font-monospace fw-bold fs-5 text-dark @error('price_per_liter') is-invalid @enderror"
                                       value="{{ old('price_per_liter') }}"
                                       placeholder="e.g. 110.50" oninput="handlePriceInput()">
                                <span class="input-group-text bg-light text-muted fw-semibold">ETB / L</span>
                            </div>
                            <small class="text-muted text-xxs">Station pump unit price per 1 liter to calculate fulfill volume.</small>
                            @error('price_per_liter')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        @endif

                        {{-- 7. Liters Requested --}}
                        <div class="col-md-6" id="litersRequestedContainer">
                            <label for="liters_requested" class="form-label fw-semibold text-dark">
                                <span id="litersRequestedLabel">Volume Needed (Liters)</span> <span class="text-danger">*</span>
                            </label>
                            <div class="input-group">
                                <span class="input-group-text bg-light"><i class="fa-solid fa-oil-can"></i></span>
                                <input type="number" step="0.01" min="0.01" name="liters_requested" id="liters_requested"
                                       class="form-control font-monospace fw-bold @error('liters_requested') is-invalid @enderror"
                                       value="{{ old('liters_requested') }}"
                                       placeholder="e.g. 50" required oninput="handleLitersInput()">
                                <span class="input-group-text">Liters</span>
                            </div>
                            <small class="text-muted text-xxs" id="litersHelpText">Calculated or estimated liters.</small>
                            @error('liters_requested')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        @if(!empty($isGeneralAdmin))
                        {{-- Live Calculation Preview Card (Admin Mode) --}}
                        <div class="col-12 d-none" id="adminFulfillLiveCard">
                            <div class="card border border-warning border-opacity-50 bg-warning bg-opacity-10 rounded-3 p-3 shadow-none">
                                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                                    <div>
                                        <span class="text-xxs text-uppercase fw-bold text-muted d-block">
                                            <i class="fa-solid fa-calculator text-warning me-1"></i>Automatic Volume Calculation
                                        </span>
                                        <div class="d-flex align-items-baseline gap-2 mt-1">
                                            <span class="fs-4 fw-bold text-dark font-monospace" id="liveCalculatedLiters">0.00</span>
                                            <span class="fs-6 fw-bold text-muted">Liters</span>
                                        </div>
                                        <small class="text-muted text-xxs" id="liveCalcFormula">
                                            Formula: [Total Money] ÷ [Price per 1 Liter] = Fulfill Liters
                                        </small>
                                    </div>
                                    <div class="text-end">
                                        <span class="badge bg-white text-dark border px-2.5 py-1 text-xxs font-monospace d-block mb-1">
                                            KM Logging: <strong>SKIPPED</strong>
                                        </span>
                                        <span class="badge bg-success text-white px-2.5 py-1 text-xxs font-monospace" id="liveNetPayable">
                                            Payable: ETB 0.00
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        @endif

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
                                        PR #{{ $b->purchaseRequest->request_number ?: $b->purchase_request_id }} — {{ $b->scheduled_at->format('d M') }} ({{ $b->vehicle_number ?: 'Assigned' }})
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
let currentAdminMode = 'standard';

function handleVehicleChange(unitId) {
    if (!unitId) return;
    const modeParam = currentAdminMode === 'admin_quick' ? '&mode=admin_direct' : '';
    window.location.href = "{{ route('vehicle-fuel-requests.create') }}?vehicle_id=" + unitId + modeParam;
}

function setAdminRequestMode(mode) {
    currentAdminMode = mode;
    const isQuick = (mode === 'admin_quick');

    const btnStandard = document.getElementById('btnModeStandard');
    const btnQuick = document.getElementById('btnModeAdminQuick');
    const skipKmInput = document.getElementById('skipKmInput');
    const odoContainer = document.getElementById('odometerInputContainer');
    const odoInput = document.getElementById('current_odometer_km');
    const skipBanner = document.getElementById('adminSkipKmBanner');
    const priceContainer = document.getElementById('pricePerLiterContainer');
    const priceInput = document.getElementById('price_per_liter');
    const liveCard = document.getElementById('adminFulfillLiveCard');
    const estAmountLabel = document.getElementById('estimatedAmountLabel');
    const moneyHelpText = document.getElementById('moneyHelpText');
    const litersLabel = document.getElementById('litersRequestedLabel');
    const litersHelpText = document.getElementById('litersHelpText');

    if (isQuick) {
        btnStandard?.classList.remove('active', 'bg-dark', 'text-white');
        btnStandard?.classList.add('text-muted');
        btnQuick?.classList.add('active', 'bg-warning', 'text-dark', 'fw-bold');
        btnQuick?.classList.remove('text-muted');

        if (skipKmInput) skipKmInput.value = '1';

        // Hide odometer input and remove required
        odoContainer?.classList.add('d-none');
        odoInput?.removeAttribute('required');
        skipBanner?.classList.remove('d-none');

        // Show price per liter and live calculation card
        priceContainer?.classList.remove('d-none');
        if (priceInput) priceInput.setAttribute('required', 'required');
        liveCard?.classList.remove('d-none');

        if (estAmountLabel) estAmountLabel.textContent = "Total Amount of Money Asked (ETB)";
        if (moneyHelpText) moneyHelpText.textContent = "Total money asked for this vehicle fuel allocation.";
        if (litersLabel) litersLabel.textContent = "Calculated Fulfill Volume (Liters)";
        if (litersHelpText) litersHelpText.textContent = "Auto-calculated from Total Money ÷ Price per 1 Liter.";

        recalcFromInputs();
    } else {
        btnQuick?.classList.remove('active', 'bg-warning', 'text-dark', 'fw-bold');
        btnQuick?.classList.add('text-muted');
        btnStandard?.classList.add('active', 'bg-dark', 'text-white', 'fw-semibold');
        btnStandard?.classList.remove('text-muted');

        if (skipKmInput) skipKmInput.value = '0';

        // Restore odometer input and make required
        odoContainer?.classList.remove('d-none');
        odoInput?.setAttribute('required', 'required');
        skipBanner?.classList.add('d-none');

        // Hide price per liter and live calculation card
        priceContainer?.classList.add('d-none');
        priceInput?.removeAttribute('required');
        liveCard?.classList.add('d-none');

        if (estAmountLabel) estAmountLabel.textContent = "Estimated Cost (ETB)";
        if (moneyHelpText) moneyHelpText.textContent = "Total estimated refueling cost.";
        if (litersLabel) litersLabel.textContent = "Volume Needed (Liters)";
        if (litersHelpText) litersHelpText.textContent = "Fuel volume needed.";
    }
}

function handleMoneyInput() {
    if (currentAdminMode === 'admin_quick') {
        const money = parseFloat(document.getElementById('estimated_amount')?.value) || 0;
        const rate = parseFloat(document.getElementById('price_per_liter')?.value) || 0;
        if (rate > 0 && money > 0) {
            const liters = (money / rate).toFixed(2);
            const litersInput = document.getElementById('liters_requested');
            if (litersInput) litersInput.value = liters;
        }
        recalcFromInputs();
    }
}

function handlePriceInput() {
    if (currentAdminMode === 'admin_quick') {
        const money = parseFloat(document.getElementById('estimated_amount')?.value) || 0;
        const rate = parseFloat(document.getElementById('price_per_liter')?.value) || 0;
        if (rate > 0 && money > 0) {
            const liters = (money / rate).toFixed(2);
            const litersInput = document.getElementById('liters_requested');
            if (litersInput) litersInput.value = liters;
        }
        recalcFromInputs();
    }
}

function handleLitersInput() {
    if (currentAdminMode === 'admin_quick') {
        const liters = parseFloat(document.getElementById('liters_requested')?.value) || 0;
        const rate = parseFloat(document.getElementById('price_per_liter')?.value) || 0;
        if (rate > 0 && liters > 0) {
            const money = (liters * rate).toFixed(2);
            const moneyInput = document.getElementById('estimated_amount');
            if (moneyInput) moneyInput.value = money;
        }
        recalcFromInputs();
    }
}

function recalcFromInputs() {
    const money = parseFloat(document.getElementById('estimated_amount')?.value) || 0;
    const rate = parseFloat(document.getElementById('price_per_liter')?.value) || 0;
    const liveLiters = document.getElementById('liveCalculatedLiters');
    const formulaText = document.getElementById('liveCalcFormula');
    const netBadge = document.getElementById('liveNetPayable');

    let liters = 0;
    if (rate > 0 && money > 0) {
        liters = Math.round((money / rate) * 100) / 100;
        if (formulaText) {
            formulaText.innerHTML = `Formula: <strong>ETB ${money.toLocaleString('en-US', {minimumFractionDigits: 2})}</strong> ÷ <strong>${rate.toFixed(2)} ETB/L</strong> = <span class="text-success fw-bold">${liters.toFixed(2)} Liters</span>`;
        }
    } else {
        if (formulaText) {
            formulaText.textContent = "Enter Total Amount and Price per 1 Liter to calculate fulfill volume.";
        }
    }

    if (liveLiters) liveLiters.textContent = liters.toFixed(2);
    if (netBadge) netBadge.textContent = 'Payable: ETB ' + money.toLocaleString('en-US', {minimumFractionDigits: 2});
}

document.addEventListener('DOMContentLoaded', function() {
    const urlParams = new URLSearchParams(window.location.search);
    const initialSkipKm = document.getElementById('skipKmInput')?.value;
    if (urlParams.get('mode') === 'admin_direct' || initialSkipKm === '1') {
        setAdminRequestMode('admin_quick');
    }
});
</script>
@endsection
