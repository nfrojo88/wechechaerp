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
            <a href="{{ route('vehicle-fuel-requests.create') }}" class="btn btn-warning text-dark rounded-pill px-3 shadow-sm fw-semibold">
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
                                <div class="text-muted small" style="font-size: 0.75rem;">{{ $req->created_at?->format('d M Y') }}</div>
                            </td>
                            <td class="py-3">
                                <div class="fw-semibold text-dark">{{ $req->assetUnit?->plate_number ?: $req->assetUnit?->unit_code }}</div>
                                <span class="text-muted small">
                                    <i class="fa-solid fa-user me-1 text-primary"></i>{{ $req->driver?->full_name ?? ($req->requestedBy?->name ?? 'Driver') }}
                                </span>
                            </td>
                            <td class="py-3">
                                <span class="badge bg-light text-dark border font-monospace">
                                    {{ number_format($req->current_odometer_km) }} KM
                                </span>
                                @if($req->service_due_warning)
                                    <span class="badge bg-warning text-dark ms-1" title="Service was due soon or overdue when requested">
                                        <i class="fa-solid fa-wrench"></i>
                                    </span>
                                @endif
                            </td>
                            <td class="py-3">
                                <span class="fw-bold text-dark">{{ number_format($req->liters_requested, 1) }} L</span>
                                <span class="badge {{ $req->fuel_type === 'diesel' ? 'bg-secondary' : 'bg-primary' }} text-capitalize ms-1" style="font-size: 0.7rem;">
                                    {{ $req->fuel_type }}
                                </span>
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

</div>
@endsection
