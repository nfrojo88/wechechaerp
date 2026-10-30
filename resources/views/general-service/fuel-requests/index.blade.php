@extends('layouts.app')
@section('title', 'Vehicle Fuel Requests — General Service & Drivers')

@section('content')
<div class="container-fluid px-3 px-md-4 py-3">

    {{-- Breadcrumbs & Header --}}
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-1 text-muted small">
                    <li class="breadcrumb-item"><a href="{{ route('dashboard.general_service') }}" class="text-decoration-none">General Service</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('general-service.vehicle-reminders.dashboard') }}" class="text-decoration-none">Vehicles</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Fuel Requests</li>
                </ol>
            </nav>
            <h1 class="h3 fw-bold mb-0 text-dark">
                <i class="fa-solid fa-gas-pump text-warning me-2"></i>Vehicle Gasoline &amp; Fuel Requests
            </h1>
            <p class="text-muted small mb-0">Driver fuel requests routed directly to Finance Head for disbursement, with odometer logging &amp; station receipt reconciliation.</p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            @if(!empty($isGeneralAdmin))
                <button type="button" class="btn btn-warning text-dark rounded-pill px-3 shadow-sm fw-bold border border-warning" data-bs-toggle="modal" data-bs-target="#adminQuickFuelModal">
                    <i class="fa-solid fa-bolt me-1 text-danger"></i>Admin Quick Fuel (Skip KM)
                </button>
            @endif
            <a href="{{ route('vehicle-fuel-requests.create') }}" class="btn btn-dark rounded-pill px-3 shadow-sm fw-semibold">
                <i class="fa-solid fa-plus me-1"></i>New Fuel Request
            </a>
            <a href="{{ route('vehicle-fuel-requests.reports') }}" class="btn btn-outline-primary rounded-pill px-3 shadow-sm fw-semibold">
                <i class="fa-solid fa-chart-line me-1"></i>Fuel Control &amp; Reports
            </a>
            <a href="{{ route('vehicle-km-logs.index') }}" class="btn btn-outline-secondary rounded-pill px-3 shadow-sm">
                <i class="fa-solid fa-gauge-high me-1"></i>KM Logs
            </a>
        </div>
    </div>

    {{-- Alerts --}}
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
            <form method="GET" action="{{ route('vehicle-fuel-requests.index') }}" class="row g-2 align-items-end">
                <div class="col-md-3">
                    <label class="form-label text-muted small mb-1">Select Vehicle</label>
                    <select name="vehicle_id" class="form-select form-select-sm">
                        <option value="">— All Accessible Vehicles —</option>
                        @foreach($vehicles as $v)
                            <option value="{{ $v->id }}" {{ request('vehicle_id') == $v->id ? 'selected' : '' }}>
                                {{ $v->plate_number ? $v->plate_number . ' (' . $v->unit_code . ')' : $v->unit_code }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label text-muted small mb-1">Status</label>
                    <select name="status" class="form-select form-select-sm">
                        <option value="">— All Statuses —</option>
                        <option value="pending_finance" {{ request('status') === 'pending_finance' ? 'selected' : '' }}>Pending Finance Head</option>
                        <option value="assigned" {{ request('status') === 'assigned' ? 'selected' : '' }}>Assigned to Cashier</option>
                        <option value="paid" {{ request('status') === 'paid' ? 'selected' : '' }}>Paid (Needs Receipt)</option>
                        <option value="closed" {{ request('status') === 'closed' ? 'selected' : '' }}>Closed (Receipt Attached)</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label text-muted small mb-1">Fuel Type</label>
                    <select name="fuel_type" class="form-select form-select-sm">
                        <option value="">— All Fuel Types —</option>
                        <option value="diesel" {{ request('fuel_type') === 'diesel' ? 'selected' : '' }}>Diesel (ናፍጣ)</option>
                        <option value="benzine" {{ request('fuel_type') === 'benzine' ? 'selected' : '' }}>Benzine (ቤንዚን)</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label text-muted small mb-1">Search</label>
                    <input type="text" name="search" value="{{ request('search') }}" placeholder="Request #, plate, driver, station..." class="form-control form-select-sm">
                </div>
                <div class="col-md-2 d-flex gap-1">
                    <button type="submit" class="btn btn-sm btn-primary w-100 rounded-pill"><i class="fa-solid fa-filter me-1"></i>Filter</button>
                    @if(request()->hasAny(['vehicle_id', 'status', 'fuel_type', 'search']))
                        <a href="{{ route('vehicle-fuel-requests.index') }}" class="btn btn-sm btn-outline-secondary rounded-pill" title="Clear Filters"><i class="fa-solid fa-xmark"></i></a>
                    @endif
                </div>
            </form>
        </div>
    </div>

    {{-- Fuel Requests Table Card --}}
    <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
        <div class="card-header bg-white py-3 border-0 d-flex justify-content-between align-items-center">
            <h5 class="fw-bold mb-0 text-dark">
                <i class="fa-solid fa-list-check text-warning me-2"></i>Fuel Requests Ledger
            </h5>
            <span class="badge bg-light text-dark border px-3 py-1">{{ $requests->total() }} Requests</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" style="font-size: 0.88rem;">
                    <thead class="table-light text-muted small text-uppercase">
                        <tr>
                            <th class="ps-4 py-3">Request #</th>
                            <th class="py-3">Vehicle</th>
                            <th class="py-3">Odometer</th>
                            <th class="py-3">Fuel Type &amp; Volume</th>
                            <th class="py-3">Estimated Cost</th>
                            <th class="py-3">Workflow Status</th>
                            <th class="py-3">Station Receipt</th>
                            <th class="py-3 pe-4 text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($requests as $req)
                        @php
                            $badge = $req->status_badge;
                        @endphp
                        <tr>
                            <td class="ps-4 py-3">
                                <a href="{{ route('vehicle-fuel-requests.show', $req->id) }}" class="fw-bold text-dark font-monospace text-decoration-none">
                                    {{ $req->request_no }}
                                </a>
                                <div class="text-muted small" style="font-size: 0.75rem;">{{ $req->created_at->format('d M Y') }}</div>
                            </td>
                            <td class="py-3">
                                <div class="fw-semibold text-dark">{{ $req->assetUnit->plate_number ?: $req->assetUnit->unit_code }}</div>
                                <span class="text-muted small">
                                    <i class="fa-solid fa-user me-1 text-primary"></i>{{ $req->driver->full_name ?? ($req->requestedBy->name ?? 'Driver') }}
                                </span>
                            </td>
                            <td class="py-3">
                                @if(!empty($req->is_km_skipped))
                                    <span class="badge bg-light text-secondary border font-monospace" title="Odometer reading skipped by General Admin">
                                        <i class="fa-solid fa-forward me-1 text-warning"></i>KM Skipped
                                    </span>
                                @else
                                    <span class="badge bg-light text-dark border font-monospace">
                                        {{ number_format($req->current_odometer_km) }} KM
                                    </span>
                                    @if($req->service_due_warning)
                                        <span class="badge bg-warning text-dark ms-1" title="Service was due soon or overdue when requested">
                                            <i class="fa-solid fa-wrench"></i>
                                        </span>
                                    @endif
                                @endif
                            </td>
                            <td class="py-3">
                                <span class="fw-bold text-dark">{{ number_format($req->liters_requested, 1) }} L</span>
                                <span class="badge {{ $req->fuel_type === 'diesel' ? 'bg-secondary' : 'bg-primary' }} text-capitalize ms-1" style="font-size: 0.7rem;">
                                    {{ $req->fuel_type }}
                                </span>
                                @if(!empty($req->price_per_liter))
                                    <div class="text-muted small" style="font-size: 0.72rem;">@ {{ number_format($req->price_per_liter, 2) }} ETB/L</div>
                                @endif
                            </td>
                            <td class="py-3">
                                <strong class="text-success font-monospace">ETB {{ number_format($req->estimated_amount, 2) }}</strong>
                                @if($req->actual_amount && $req->actual_amount != $req->estimated_amount)
                                    <div class="text-muted small" style="font-size: 0.75rem;">Actual: ETB {{ number_format($req->actual_amount, 2) }}</div>
                                @endif
                            </td>
                            <td class="py-3">
                                <span class="badge bg-{{ $badge['class'] }} px-2 py-1">
                                    <i class="fa-solid {{ $badge['icon'] }} me-1"></i>{{ $badge['label'] }}
                                </span>
                                @if($req->expenseRequest)
                                    <div class="mt-1">
                                        <a href="{{ route('expense-requests.show', $req->expenseRequest->id) }}" class="text-decoration-none small text-muted font-monospace" style="font-size: 0.72rem;">
                                            <i class="fa-solid fa-file-invoice-dollar me-1"></i>{{ $req->expenseRequest->request_number }}
                                        </a>
                                    </div>
                                @endif
                            </td>
                            <td class="py-3">
                                @if($req->receipt_photo)
                                    <span class="badge bg-success bg-opacity-25 text-success-emphasis border border-success border-opacity-25 px-2 py-1">
                                        <i class="fa-solid fa-circle-check me-1"></i>Attached
                                    </span>
                                    @if($req->fuel_station_name)
                                        <div class="text-muted small text-truncate" style="max-width: 130px; font-size: 0.72rem;">{{ $req->fuel_station_name }}</div>
                                    @endif
                                @else
                                    <span class="badge bg-warning bg-opacity-25 text-warning-emphasis border border-warning border-opacity-25 px-2 py-1">
                                        <i class="fa-solid fa-clock me-1"></i>Pending Upload
                                    </span>
                                @endif
                            </td>
                            <td class="py-3 pe-4 text-end">
                                <div class="btn-group btn-group-sm">
                                    <a href="{{ route('vehicle-fuel-requests.show', $req->id) }}" class="btn btn-outline-secondary px-2 py-1" title="View Request Details">
                                        <i class="fa-solid fa-eye"></i>
                                    </a>
                                    @if(!$req->receipt_photo)
                                        <a href="{{ route('vehicle-fuel-requests.show', $req->id) }}#receiptCard" class="btn btn-warning btn-sm text-dark px-2 py-1 fw-semibold" title="Attach Fuel Station Receipt">
                                            <i class="fa-solid fa-receipt me-1"></i>Attach Receipt
                                        </a>
                                    @endif
                                    @if($isGeneralAdmin || (auth()->id() === $req->requested_by_user_id && $req->status === \App\Models\VehicleFuelRequest::STATUS_PENDING_FINANCE))
                                        <form action="{{ route('vehicle-fuel-requests.destroy', $req->id) }}" method="POST" onsubmit="return confirm('Delete fuel request {{ $req->request_no }}?');" class="d-inline">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-outline-danger px-2 py-1" title="Delete Fuel Request">
                                                <i class="fa-solid fa-trash"></i>
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="8" class="text-center py-5 text-muted">
                                <i class="fa-solid fa-gas-pump fa-3x text-secondary opacity-50 mb-2 d-block"></i>
                                <strong>No fuel requests found.</strong>
                                <div class="small">Drivers or General Service can submit a new fuel replenishment request anytime.</div>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($requests->hasPages())
                <div class="p-3 border-top d-flex justify-content-end">
                    {{ $requests->links() }}
                </div>
            @endif
        </div>
    </div>

    @if(!empty($isGeneralAdmin))
    {{-- Modal: Quick Admin Fuel Request (Skip KM & Auto-Calculate Fulfill Liters) --}}
    <div class="modal fade" id="adminQuickFuelModal" tabindex="-1" aria-labelledby="adminQuickFuelModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content border-0 shadow rounded-4">
                <div class="modal-header bg-warning bg-opacity-15 border-0 py-3">
                    <div>
                        <h5 class="modal-title fw-bold text-dark mb-0" id="adminQuickFuelModalLabel">
                            <i class="fa-solid fa-bolt text-danger me-2"></i>Quick Admin Fuel Request
                        </h5>
                        <small class="text-muted">General Admin Direct Allocation — Calculates fulfill liters from money &amp; rate. Skips odometer KM.</small>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <form action="{{ route('vehicle-fuel-requests.store') }}" method="POST" id="quickAdminFuelForm">
                    @csrf
                    <input type="hidden" name="skip_km" value="1">
                    <input type="hidden" name="override_unreceipted" value="1">

                    <div class="modal-body p-4">
                        {{-- Policy & Role Banner --}}
                        <div class="alert alert-light border border-warning border-opacity-50 rounded-3 p-3 mb-3 d-flex align-items-center justify-content-between flex-wrap gap-2">
                            <div class="d-flex align-items-center gap-2">
                                <span class="badge bg-warning text-dark px-2.5 py-1.5 fw-bold">
                                    <i class="fa-solid fa-user-shield me-1"></i>General Admin
                                </span>
                                <span class="small text-dark fw-semibold">
                                    <i class="fa-solid fa-forward text-warning me-1"></i>Dashboard Odometer (KM) is Skipped.
                                </span>
                            </div>
                            <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 text-xxs">
                                Direct to Finance Head
                            </span>
                        </div>

                        <div class="row g-3">
                            {{-- 1. Select Vehicle --}}
                            <div class="col-md-7">
                                <label for="mQuickVehicleId" class="form-label fw-bold text-dark text-xs mb-1">
                                    Select Vehicle <span class="text-danger">*</span>
                                </label>
                                <select name="fixed_asset_unit_id" id="mQuickVehicleId" class="form-select" required>
                                    <option value="">— Select Vehicle (Plate / Code) —</option>
                                    @foreach($vehicles as $v)
                                        <option value="{{ $v->id }}">
                                            {{ $v->plate_number ? 'Plate: ' . $v->plate_number : $v->unit_code }} — {{ $v->parentAsset->name }} ({{ $v->unit_code }})
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            {{-- 2. Fuel Type --}}
                            <div class="col-md-5">
                                <label for="mQuickFuelType" class="form-label fw-bold text-dark text-xs mb-1">
                                    Fuel Type <span class="text-danger">*</span>
                                </label>
                                <select name="fuel_type" id="mQuickFuelType" class="form-select" required>
                                    <option value="diesel">Diesel (ናፍጣ)</option>
                                    <option value="benzine">Benzine / Petrol (ቤንዚን)</option>
                                </select>
                            </div>

                            {{-- 3. Total Amount of Money Asked (ETB) --}}
                            <div class="col-md-6">
                                <label for="mQuickTotalMoney" class="form-label fw-bold text-dark text-xs mb-1">
                                    <i class="fa-solid fa-money-bill-wave text-success me-1"></i>Total Amount of Money Asked (ETB) <span class="text-danger">*</span>
                                </label>
                                <div class="input-group">
                                    <span class="input-group-text bg-success text-white fw-bold">ETB</span>
                                    <input type="number" step="0.01" min="1" name="estimated_amount" id="mQuickTotalMoney"
                                           class="form-control form-control-lg font-monospace fw-bold text-success fs-5"
                                           placeholder="e.g. 5000.00" required oninput="recalcQuickAdminLiters()">
                                </div>
                                <small class="text-muted text-xxs">Total money requested for this fuel replenishment.</small>
                            </div>

                            {{-- 4. Price for 1 Liter (How Much is 1 Liter?) --}}
                            <div class="col-md-6">
                                <label for="mQuickPricePerLiter" class="form-label fw-bold text-dark text-xs mb-1">
                                    <i class="fa-solid fa-gas-pump text-primary me-1"></i>Price per 1 Liter (How Much?) <span class="text-danger">*</span>
                                </label>
                                <div class="input-group">
                                    <input type="number" step="0.01" min="0.01" name="price_per_liter" id="mQuickPricePerLiter"
                                           class="form-control form-control-lg font-monospace fw-bold text-dark fs-5"
                                           placeholder="e.g. 110.50" required oninput="recalcQuickAdminLiters()">
                                    <span class="input-group-text bg-light text-muted fw-semibold">ETB / L</span>
                                </div>
                                <small class="text-muted text-xxs">Station pump rate per liter to calculate fulfill volume.</small>
                            </div>

                            {{-- 5. Live Calculation Preview Card (Fulfill Liters) --}}
                            <div class="col-12">
                                <div class="card border border-warning border-opacity-50 bg-warning bg-opacity-10 rounded-3 p-3 shadow-none">
                                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                                        <div>
                                            <span class="text-xxs text-uppercase fw-bold text-muted d-block">
                                                <i class="fa-solid fa-calculator text-warning me-1"></i>Calculated Fulfill Volume
                                            </span>
                                            <div class="d-flex align-items-baseline gap-2 mt-1">
                                                <span class="fs-3 fw-bold text-dark font-monospace" id="mQuickLitersDisplay">0.00</span>
                                                <span class="fs-6 fw-bold text-muted">Liters</span>
                                            </div>
                                            <small class="text-muted text-xxs" id="mQuickFormulaBreakdown">
                                                Formula: [Total Money] ÷ [Price per 1 Liter] = Fulfill Liters
                                            </small>
                                        </div>
                                        <div class="text-end">
                                            <span class="badge bg-white text-dark border px-2.5 py-1 text-xxs font-monospace d-block mb-1">
                                                KM Logging: <strong>SKIPPED</strong>
                                            </span>
                                            <span class="badge bg-success text-white px-2.5 py-1 text-xxs font-monospace" id="mQuickNetPayableBadge">
                                                Payable: ETB 0.00
                                            </span>
                                        </div>
                                    </div>
                                    <input type="hidden" name="liters_requested" id="mQuickLitersInput" value="0">
                                </div>
                            </div>

                            {{-- 6. Trip Purpose / Allocation Reason --}}
                            <div class="col-12">
                                <label for="mQuickReason" class="form-label fw-semibold text-dark text-xs mb-1">
                                    Fuel Purpose / Allocation Memo
                                </label>
                                <input type="text" name="reason" id="mQuickReason" class="form-control form-control-sm"
                                       value="General Admin fuel allocation (Direct money requested, KM skipped)"
                                       placeholder="e.g. Field supervision, material procurement dispatch...">
                            </div>
                        </div>
                    </div>

                    <div class="modal-footer bg-light border-0 py-2.5 px-4 d-flex justify-content-between align-items-center">
                        <span class="text-muted text-xxs">
                            <i class="fa-solid fa-bolt text-warning me-1"></i>Expense Request # routes directly to Finance Head.
                        </span>
                        <div class="d-flex gap-2">
                            <button type="button" class="btn btn-sm btn-secondary rounded-pill px-3" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-sm btn-warning text-dark rounded-pill px-4 fw-bold shadow-sm" id="btnSubmitQuickAdmin">
                                <i class="fa-solid fa-check me-1"></i>Submit Fuel Request
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
    function recalcQuickAdminLiters() {
        const totalMoneyInput = document.getElementById('mQuickTotalMoney');
        const priceInput = document.getElementById('mQuickPricePerLiter');
        const litersDisplay = document.getElementById('mQuickLitersDisplay');
        const litersInput = document.getElementById('mQuickLitersInput');
        const formulaBreakdown = document.getElementById('mQuickFormulaBreakdown');
        const netPayableBadge = document.getElementById('mQuickNetPayableBadge');

        const money = parseFloat(totalMoneyInput?.value) || 0;
        const rate = parseFloat(priceInput?.value) || 0;

        let liters = 0;
        if (rate > 0 && money > 0) {
            liters = Math.round((money / rate) * 100) / 100;
            if (formulaBreakdown) {
                formulaBreakdown.innerHTML = `Formula: <strong>ETB ${money.toLocaleString('en-US', {minimumFractionDigits: 2})}</strong> ÷ <strong>${rate.toFixed(2)} ETB/L</strong> = <span class="text-success fw-bold">${liters.toFixed(2)} Liters</span>`;
            }
        } else {
            if (formulaBreakdown) {
                formulaBreakdown.textContent = "Enter Total Amount and Price per 1 Liter to calculate volume.";
            }
        }

        if (litersDisplay) litersDisplay.textContent = liters.toFixed(2);
        if (litersInput) litersInput.value = liters.toFixed(2);
        if (netPayableBadge) netPayableBadge.textContent = 'Payable: ETB ' + money.toLocaleString('en-US', {minimumFractionDigits: 2});
    }
    </script>
    @endif

</div>
@endsection
