@extends('layouts.app')
@section('title', 'Fuel Request Details: ' . $fuelRequest->request_no)

@section('content')
<div class="container-fluid px-3 px-md-4 py-3">

    {{-- Breadcrumbs & Header --}}
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-1 text-muted small">
                    <li class="breadcrumb-item"><a href="{{ route('dashboard.general_service') }}" class="text-decoration-none">General Service</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('vehicle-fuel-requests.index') }}" class="text-decoration-none">Fuel Requests</a></li>
                    <li class="breadcrumb-item active" aria-current="page">{{ $fuelRequest->request_no }}</li>
                </ol>
            </nav>
            <div class="d-flex align-items-center gap-2">
                <h1 class="h3 fw-bold mb-0 text-dark">
                    Fuel Request <span class="font-monospace text-primary">{{ $fuelRequest->request_no }}</span>
                </h1>
                @php $badge = $fuelRequest->status_badge; @endphp
                <span class="badge bg-{{ $badge['class'] }} rounded-pill px-3 py-2 fs-6">
                    <i class="fa-solid {{ $badge['icon'] }} me-1"></i>{{ $badge['label'] }}
                </span>
            </div>
            <p class="text-muted small mb-0">Vehicle: <strong>{{ $fuelRequest->assetUnit?->plate_number ?: $fuelRequest->assetUnit?->unit_code }}</strong> &bull; Submitted {{ $fuelRequest->created_at?->format('l, d M Y - h:i A') }}</p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a href="{{ route('vehicle-fuel-requests.index') }}" class="btn btn-outline-secondary rounded-pill px-3 shadow-sm">
                <i class="fa-solid fa-arrow-left me-1"></i>All Requests
            </a>
            <a href="{{ route('general-service.vehicle-reminders.vehicle-detail', $fuelRequest->fixed_asset_unit_id) }}" class="btn btn-outline-primary rounded-pill px-3 shadow-sm">
                <i class="fa-solid fa-truck-pickup me-1"></i>Vehicle Hub
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

    <div class="row g-4">
        {{-- Left Column: Fuel & Financial Details --}}
        <div class="col-lg-7">
            {{-- Fuel Request Overview Card --}}
            <div class="card border-0 shadow-sm rounded-4 mb-4 overflow-hidden">
                <div class="card-header bg-white py-3 border-0 d-flex justify-content-between align-items-center">
                    <h5 class="fw-bold mb-0 text-dark">
                        <i class="fa-solid fa-gas-pump text-warning me-2"></i>Fuel Request Specification
                    </h5>
                    <span class="badge bg-light text-dark border">{{ ucfirst($fuelRequest->fuel_type) }}</span>
                </div>
                <div class="card-body p-4">
                    <div class="row g-3">
                        <div class="col-sm-6">
                            <span class="text-muted small d-block">Vehicle Asset</span>
                            <strong class="text-dark fs-6">{{ $fuelRequest->assetUnit?->parentAsset?->name ?? 'Vehicle' }}</strong>
                            <div class="font-monospace text-muted small">Plate: {{ $fuelRequest->assetUnit?->plate_number ?: 'N/A' }} | Code: {{ $fuelRequest->assetUnit?->unit_code }}</div>
                        </div>
                        <div class="col-sm-6">
                            <span class="text-muted small d-block">Driver / Requester</span>
                            <strong class="text-dark fs-6">{{ $fuelRequest->driver?->full_name ?? ($fuelRequest->requestedBy?->name ?? 'Driver') }}</strong>
                            <div class="text-muted small">Submitted by: {{ $fuelRequest->requestedBy?->name ?? 'System' }}</div>
                        </div>

                        <div class="col-sm-4">
                            <div class="p-3 bg-light rounded-3 text-center">
                                <span class="text-muted small d-block mb-1">Volume Requested</span>
                                <strong class="fs-4 text-dark font-monospace">{{ number_format($fuelRequest->liters_requested, 1) }}</strong>
                                <small class="text-muted d-block">Liters @if($fuelRequest->price_per_liter) (@ {{ number_format($fuelRequest->price_per_liter, 2) }} ETB/L) @endif</small>
                            </div>
                        </div>
                        <div class="col-sm-4">
                            <div class="p-3 bg-light rounded-3 text-center">
                                <span class="text-muted small d-block mb-1">Estimated Cost</span>
                                <strong class="fs-4 text-success font-monospace">{{ number_format($fuelRequest->estimated_amount, 2) }}</strong>
                                <small class="text-muted d-block">ETB</small>
                            </div>
                        </div>
                        <div class="col-sm-4">
                            <div class="p-3 bg-light rounded-3 text-center">
                                <span class="text-muted small d-block mb-1">Odometer at Request</span>
                                @if(!empty($fuelRequest->is_km_skipped))
                                    <strong class="fs-5 text-secondary font-monospace d-block my-1"><i class="fa-solid fa-forward text-warning me-1"></i>Skipped</strong>
                                    <small class="text-muted d-block">General Admin Request</small>
                                @else
                                    <strong class="fs-4 text-primary font-monospace">{{ number_format($fuelRequest->current_odometer_km) }}</strong>
                                    <small class="text-muted d-block">KM</small>
                                @endif
                            </div>
                        </div>

                        <div class="col-12 mt-3">
                            <span class="text-muted small d-block mb-1">Trip Destination &amp; Purpose</span>
                            <div class="p-3 bg-light rounded-3 text-dark font-monospace" style="font-size: 0.88rem;">
                                {{ $fuelRequest->reason ?: 'No trip description provided.' }}
                            </div>
                        </div>

                        @if($fuelRequest->project)
                            <div class="col-sm-6">
                                <span class="text-muted small d-block">Cost Center / Project</span>
                                <strong class="text-dark">{{ $fuelRequest->project->name }}</strong>
                            </div>
                        @endif

                        @if($fuelRequest->driverBooking)
                            <div class="col-sm-6">
                                <span class="text-muted small d-block">Linked Logistics Booking</span>
                                <strong class="text-dark">PR #{{ $fuelRequest->driverBooking->purchaseRequest?->request_number ?: $fuelRequest->driverBooking->purchase_request_id }}</strong>
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            {{-- Financial Lifecycle Integration Card (Existing Expense Flow) --}}
            <div class="card border-0 shadow-sm rounded-4 mb-4 overflow-hidden">
                <div class="card-header bg-white py-3 border-0 d-flex justify-content-between align-items-center">
                    <h5 class="fw-bold mb-0 text-dark">
                        <i class="fa-solid fa-file-invoice-dollar text-success me-2"></i>Finance Payment Lifecycle
                    </h5>
                    @if($fuelRequest->expenseRequest)
                        <span class="badge bg-dark font-monospace">{{ $fuelRequest->expenseRequest->request_number }}</span>
                    @endif
                </div>
                <div class="card-body p-4">
                    @if($fuelRequest->expenseRequest)
                        @php $exp = $fuelRequest->expenseRequest; @endphp
                        <div class="p-3 border rounded-3 bg-light mb-3">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <div>
                                    <span class="badge bg-secondary me-1">Category: {{ $exp->category }}</span>
                                    <span class="badge bg-info text-dark">{{ $exp->service_type }}</span>
                                </div>
                                <span class="badge bg-primary fs-6">{{ $exp->status }}</span>
                            </div>
                            <div class="row g-2 small mt-2">
                                <div class="col-sm-6">
                                    <span class="text-muted">Approved for Payment:</span>
                                    <strong class="text-success font-monospace ms-1">ETB {{ number_format($exp->amount, 2) }}</strong>
                                </div>
                                <div class="col-sm-6">
                                    <span class="text-muted">Assigned Cashier:</span>
                                    <strong class="text-dark ms-1">{{ $exp->assignedFinanceStaff?->name ?? 'Pending Assignment' }}</strong>
                                </div>
                                @if($exp->paid_at)
                                    <div class="col-sm-6">
                                        <span class="text-muted">Paid On:</span>
                                        <strong class="text-dark ms-1">{{ $exp->paid_at->format('d M Y, H:i') }}</strong>
                                    </div>
                                    <div class="col-sm-6">
                                        <span class="text-muted">Disbursed By:</span>
                                        <strong class="text-dark ms-1">{{ $exp->paidBy?->name ?? 'Cashier' }}</strong>
                                    </div>
                                    @if($exp->payment_reference)
                                        <div class="col-12">
                                            <span class="text-muted">Payment Reference:</span>
                                            <span class="font-monospace fw-bold text-dark ms-1">{{ $exp->payment_reference }}</span>
                                        </div>
                                    @endif
                                @endif
                            </div>
                        </div>

                        <div class="d-flex justify-content-end">
                            <a href="{{ route('expense-requests.show', $exp->id) }}" class="btn btn-outline-success btn-sm rounded-pill px-3">
                                <i class="fa-solid fa-arrow-up-right-from-square me-1"></i>Open in Expense Request Module
                            </a>
                        </div>
                    @else
                        <div class="text-muted small">No linked expense request recorded.</div>
                    @endif
                </div>
            </div>
        </div>

        {{-- Right Column: Station Receipt & Reconciliation --}}
        <div class="col-lg-5">
            {{-- Station Receipt Card --}}
            <div class="card border-0 shadow-sm rounded-4 mb-4 overflow-hidden" id="receiptCard">
                <div class="card-header bg-white py-3 border-0 d-flex justify-content-between align-items-center">
                    <h5 class="fw-bold mb-0 text-dark">
                        <i class="fa-solid fa-receipt text-primary me-2"></i>Fuel Station Receipt &amp; Close
                    </h5>
                    @if($fuelRequest->receipt_photo)
                        <span class="badge bg-success"><i class="fa-solid fa-check me-1"></i>Verified &amp; Closed</span>
                    @else
                        <span class="badge bg-warning text-dark"><i class="fa-solid fa-clock me-1"></i>Awaiting Receipt</span>
                    @endif
                </div>
                <div class="card-body p-4">
                    @if($fuelRequest->receipt_photo)
                        {{-- Verified Receipt Summary --}}
                        <div class="mb-3 text-center">
                            <a href="{{ $fuelRequest->receipt_photo_url }}" target="_blank" class="d-inline-block position-relative">
                                <img src="{{ $fuelRequest->receipt_photo_url }}" alt="Station Receipt" class="img-fluid rounded-3 border shadow-sm" style="max-height: 220px; object-fit: cover;">
                                <span class="badge bg-dark bg-opacity-75 text-white position-absolute bottom-0 end-0 m-2">
                                    <i class="fa-solid fa-magnifying-glass me-1"></i>Click to Enlarge
                                </span>
                            </a>
                        </div>

                        <div class="p-3 bg-light rounded-3 small">
                            <div class="row g-2">
                                <div class="col-6">
                                    <span class="text-muted">Gas Station:</span>
                                    <strong class="d-block text-dark">{{ $fuelRequest->fuel_station_name ?: 'Station Receipt' }}</strong>
                                </div>
                                <div class="col-6">
                                    <span class="text-muted">Station TIN:</span>
                                    <strong class="d-block font-monospace text-dark">{{ $fuelRequest->fuel_station_tin ?: '—' }}</strong>
                                </div>
                                <div class="col-6">
                                    <span class="text-muted">Actual Liters:</span>
                                    <strong class="d-block font-monospace text-dark">{{ number_format($fuelRequest->actual_liters, 2) }} L</strong>
                                </div>
                                <div class="col-6">
                                    <span class="text-muted">Actual Cost:</span>
                                    <strong class="d-block font-monospace text-success">ETB {{ number_format($fuelRequest->actual_amount, 2) }}</strong>
                                </div>
                                <div class="col-6">
                                    <span class="text-muted">Post-Fueling Odometer:</span>
                                    <strong class="d-block font-monospace text-primary">{{ number_format($fuelRequest->receipt_odometer_km) }} KM</strong>
                                </div>
                                @if($fuelRequest->km_per_liter)
                                    <div class="col-6">
                                        <span class="text-muted">Calculated Mileage:</span>
                                        <strong class="d-block font-monospace text-dark">{{ $fuelRequest->km_per_liter }} KM/L</strong>
                                    </div>
                                @endif
                                <div class="col-12 mt-2 pt-2 border-top text-muted" style="font-size:0.75rem;">
                                    Verified &amp; closed on {{ $fuelRequest->closed_at?->format('d M Y, H:i') }} by {{ $fuelRequest->closedByUser?->name ?? 'System' }}.
                                </div>
                            </div>
                        </div>
                    @else
                        {{-- Upload Receipt Form --}}
                        <p class="text-muted small mb-3">
                            After fueling, the driver or General Service must upload the station receipt to verify liters and finalize the request.
                        </p>

                        <form action="{{ route('vehicle-fuel-requests.upload-receipt', $fuelRequest->id) }}" method="POST" enctype="multipart/form-data">
                            @csrf
                            <div class="row g-3">
                                <div class="col-12">
                                    <label class="form-label fw-semibold small">Station Receipt Photo <span class="text-danger">*</span></label>
                                    <input type="file" name="receipt_photo" accept="image/*,application/pdf" class="form-control form-control-sm" required>
                                </div>

                                <div class="col-sm-6">
                                    <label class="form-label fw-semibold small">Gas Station Name</label>
                                    <input type="text" name="fuel_station_name" placeholder="e.g. TotalEnergies, NOC, Oilibya" class="form-control form-control-sm">
                                </div>

                                <div class="col-sm-6">
                                    <label class="form-label fw-semibold small">Station TIN</label>
                                    <input type="text" name="fuel_station_tin" placeholder="e.g. 0001234567" class="form-control form-control-sm font-monospace">
                                </div>

                                <div class="col-sm-6">
                                    <label class="form-label fw-semibold small">Actual Liters Pumped</label>
                                    <input type="number" step="0.1" name="actual_liters" value="{{ $fuelRequest->liters_requested }}" class="form-control form-control-sm font-monospace" required>
                                </div>

                                <div class="col-sm-6">
                                    <label class="form-label fw-semibold small">Actual Amount (ETB)</label>
                                    <input type="number" step="0.01" name="actual_amount" value="{{ $fuelRequest->estimated_amount }}" class="form-control form-control-sm font-monospace" required>
                                </div>

                                <div class="col-12">
                                    <label class="form-label fw-semibold small">New Odometer Reading (KM)</label>
                                    <input type="number" name="receipt_odometer_km" min="{{ $fuelRequest->current_odometer_km }}" placeholder="e.g. {{ $fuelRequest->current_odometer_km }}" class="form-control form-control-sm font-monospace">
                                    <small class="text-muted" style="font-size:0.75rem;">If entered, updates the vehicle odometer log automatically.</small>
                                </div>

                                <div class="col-12 mt-3">
                                    <button type="submit" class="btn btn-warning text-dark btn-sm rounded-pill w-100 fw-bold shadow-sm">
                                        <i class="fa-solid fa-receipt me-1"></i>Verify &amp; Close Fuel Request
                                    </button>
                                </div>
                            </div>
                        </form>
                    @endif
                </div>
            </div>

            {{-- Vehicle Service Health Alert --}}
            <div class="card border-0 shadow-sm rounded-4 p-3 bg-light">
                <div class="d-flex align-items-center gap-2 mb-2">
                    <i class="fa-solid fa-wrench text-warning fs-5"></i>
                    <h6 class="fw-bold mb-0 text-dark small text-uppercase">Service Schedule Health</h6>
                </div>
                @if($serviceStatus && $serviceStatus['has_service_reminder'])
                    <div class="small text-muted mb-2">
                        Next scheduled maintenance is at <strong>{{ number_format($serviceStatus['next_service_km']) }} KM</strong>.
                    </div>
                    <span class="badge bg-{{ $serviceStatus['badge']['class'] }} px-2 py-1">
                        <i class="fa-solid {{ $serviceStatus['badge']['icon'] }} me-1"></i>{{ $serviceStatus['badge']['label'] }}
                    </span>
                @else
                    <div class="small text-muted">No scheduled routine service interval currently defined.</div>
                @endif
            </div>
        </div>
    </div>

</div>
@endsection
