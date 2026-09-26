@extends('layouts.app')
@section('title', 'Emergency Requests')

@section('content')
<div class="container-fluid">
    {{-- Page Header --}}
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-1 fw-bold">
                <i class="fa-solid fa-bell-exclamation text-danger me-2"></i>Emergency Requests
            </h1>
            <p class="text-muted mb-0 small">Approve or reject urgent site material & manpower requests</p>
        </div>
        <div class="d-flex gap-2">
            <span class="badge bg-danger fs-6 px-3 py-2">
                {{ $materialRequests->count() + $manpowerRequests->count() }} Pending
            </span>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm" role="alert">
            <i class="fa-solid fa-circle-check me-2"></i>{{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    {{-- Tab Navigation --}}
    <ul class="nav nav-pills mb-4 gap-2" id="requestTabs">
        <li class="nav-item">
            <a class="nav-link active px-4" data-bs-toggle="pill" href="#materialTab">
                <i class="fa-solid fa-cart-flatbed me-2"></i>Material Requests
                <span class="badge bg-white text-danger ms-1">{{ $materialRequests->count() }}</span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link px-4" data-bs-toggle="pill" href="#manpowerTab">
                <i class="fa-solid fa-users me-2"></i>Manpower Requests
                <span class="badge bg-white text-primary ms-1">{{ $manpowerRequests->count() }}</span>
            </a>
        </li>
    </ul>

    <div class="tab-content">
        {{-- Material Requests --}}
        <div class="tab-pane fade show active" id="materialTab">
            @forelse($materialRequests as $mr)
            <div class="card shadow-sm mb-3 border-0 border-start border-danger border-3">
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col-lg-6 col-md-12">
                            <div class="d-flex align-items-center gap-3 mb-2">
                                <div class="rounded-circle bg-danger bg-opacity-10 p-2 flex-shrink-0">
                                    <i class="fa-solid fa-cart-flatbed text-danger fa-lg"></i>
                                </div>
                                <div>
                                    <div class="d-flex align-items-center gap-2">
                                        <h6 class="mb-0 fw-bold text-dark">{{ $mr->reference_number }}</h6>
                                        <span class="badge bg-danger-subtle text-danger border border-danger-subtle px-2 py-0 small fw-semibold">
                                            <i class="fa-solid fa-triangle-exclamation me-1"></i>Urgent
                                        </span>
                                    </div>
                                    <small class="text-muted d-block mt-1">
                                        <i class="fa-solid fa-building me-1 text-secondary"></i>{{ $mr->project->name ?? 'N/A' }}
                                        &bull;
                                        <i class="fa-solid fa-user me-1 text-secondary"></i>{{ $mr->creator->name ?? 'N/A' }}
                                        &bull;
                                        <i class="fa-solid fa-calendar me-1 text-danger"></i>Required by <strong>{{ optional($mr->required_date)->format('d M Y') ?? 'N/A' }}</strong>
                                    </small>
                                </div>
                            </div>
                            @if($mr->items->isNotEmpty())
                            <div class="ms-md-5 ps-md-2 mb-2">
                                @foreach($mr->items as $item)
                                    <span class="badge bg-light text-dark border me-1 mb-1">
                                        <i class="fa-solid fa-cube text-secondary me-1"></i>{{ $item->product->name ?? 'Item' }}: <strong>{{ (float)$item->quantity_requested }} {{ $item->product->unit ?? '' }}</strong>
                                    </span>
                                @endforeach
                            </div>
                            @endif
                            @if($mr->notes)
                            <p class="text-muted small mb-0 ms-md-5 ps-md-2 fst-italic">
                                <i class="fa-solid fa-quote-left text-muted me-1"></i>{{ $mr->notes }}
                            </p>
                            @endif
                        </div>
                        <div class="col-lg-6 col-md-12 text-lg-end mt-3 mt-lg-0 d-flex flex-wrap align-items-center justify-content-lg-end gap-2">
                            <span class="badge bg-warning text-dark px-3 py-2">
                                <i class="fa-solid fa-clock me-1"></i>Pending Planning
                            </span>
                            
                            {{-- View Detail Button --}}
                            <button type="button" class="btn btn-outline-primary btn-sm px-3 shadow-sm fw-semibold" data-bs-toggle="modal" data-bs-target="#viewMrModal{{ $mr->id }}">
                                <i class="fa-solid fa-eye me-1"></i>View Detail
                            </button>

                            <form method="POST" action="{{ route('planning-manager.emergency-requests.material.approve', $mr) }}" class="d-inline m-0">
                                @csrf
                                <input type="hidden" name="action" value="approve">
                                <button type="submit" class="btn btn-success btn-sm px-3 shadow-sm fw-semibold"
                                    onclick="return confirm('Approve Emergency Material Request {{ $mr->reference_number }} and send directly to Coordinator in Procurement Queue?')">
                                    <i class="fa-solid fa-check me-1"></i>Approve &amp; Send to Coordinator
                                </button>
                            </form>
                            
                            <button type="button" class="btn btn-outline-danger btn-sm px-3 shadow-sm fw-semibold" data-bs-toggle="modal" data-bs-target="#rejectMrModal{{ $mr->id }}">
                                <i class="fa-solid fa-xmark me-1"></i>Reject
                            </button>

                            <!-- View MR Details Modal -->
                            <div class="modal fade text-start" id="viewMrModal{{ $mr->id }}" tabindex="-1" aria-labelledby="viewMrModalLabel{{ $mr->id }}" aria-hidden="true">
                                <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
                                    <div class="modal-content border-0 shadow">
                                        <div class="modal-header bg-primary text-white">
                                            <div class="d-flex align-items-center gap-2">
                                                <i class="fa-solid fa-cart-flatbed fa-lg"></i>
                                                <h6 class="modal-title fw-bold mb-0" id="viewMrModalLabel{{ $mr->id }}">
                                                    Emergency Material Request: {{ $mr->reference_number }}
                                                </h6>
                                            </div>
                                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                        </div>
                                        <div class="modal-body p-4">
                                            <!-- Key Details Grid -->
                                            <div class="row g-3 mb-4 p-3 bg-light rounded border">
                                                <div class="col-sm-6 col-md-3">
                                                    <span class="text-muted small text-uppercase fw-semibold d-block">Project</span>
                                                    <span class="fw-bold text-dark">{{ $mr->project->name ?? 'N/A' }}</span>
                                                </div>
                                                <div class="col-sm-6 col-md-3">
                                                    <span class="text-muted small text-uppercase fw-semibold d-block">Destination Store</span>
                                                    <span class="fw-bold text-dark">{{ $mr->store->name ?? 'Site General Store' }}</span>
                                                </div>
                                                <div class="col-sm-6 col-md-3">
                                                    <span class="text-muted small text-uppercase fw-semibold d-block">Requested By</span>
                                                    <span class="fw-bold text-dark">{{ $mr->creator->name ?? 'N/A' }}</span>
                                                </div>
                                                <div class="col-sm-6 col-md-3">
                                                    <span class="text-muted small text-uppercase fw-semibold d-block">Required By Date</span>
                                                    <span class="fw-bold text-danger">
                                                        <i class="fa-regular fa-calendar me-1"></i>{{ optional($mr->required_date)->format('d M Y') ?? 'Immediate' }}
                                                    </span>
                                                </div>
                                                <div class="col-sm-6 col-md-3">
                                                    <span class="text-muted small text-uppercase fw-semibold d-block">Current Status</span>
                                                    <span class="badge bg-warning text-dark mt-1"><i class="fa-solid fa-clock me-1"></i>Pending Planning</span>
                                                </div>
                                                <div class="col-sm-6 col-md-3">
                                                    <span class="text-muted small text-uppercase fw-semibold d-block">Submission Time</span>
                                                    <span class="fw-semibold text-secondary mt-1 d-block">{{ optional($mr->created_at)->format('d M Y, h:i A') }}</span>
                                                </div>
                                                <div class="col-sm-12 col-md-6">
                                                    <span class="text-muted small text-uppercase fw-semibold d-block">Urgency Justification</span>
                                                    <span class="text-dark mt-1 fst-italic d-block">{{ $mr->notes ?: 'No justification remarks specified.' }}</span>
                                                </div>
                                            </div>

                                            <!-- Materials Requested Table -->
                                            <div class="d-flex justify-content-between align-items-center mb-2">
                                                <h6 class="fw-bold text-dark mb-0">
                                                    <i class="fa-solid fa-boxes-stacked text-secondary me-2"></i>Requested Materials
                                                </h6>
                                                <span class="badge bg-secondary">{{ $mr->items->count() }} items requested</span>
                                            </div>
                                            <div class="table-responsive border rounded mb-2">
                                                <table class="table table-hover align-middle mb-0">
                                                    <thead class="table-light small text-uppercase text-secondary">
                                                        <tr>
                                                            <th style="width: 45px;">#</th>
                                                            <th>Material / Item</th>
                                                            <th>Item Code / Category</th>
                                                            <th class="text-end">Requested Quantity</th>
                                                            <th>Remarks</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        @forelse($mr->items as $idx => $item)
                                                        <tr>
                                                            <td>{{ $idx + 1 }}</td>
                                                            <td>
                                                                <div class="fw-bold text-dark">{{ $item->product->name ?? 'Item' }}</div>
                                                                @if(optional($item->product)->description)
                                                                    <small class="text-muted">{{ Str::limit($item->product->description, 60) }}</small>
                                                                @endif
                                                            </td>
                                                            <td>
                                                                <span class="badge bg-light text-dark border">
                                                                    {{ $item->product->item_code ?? 'Standard' }}
                                                                </span>
                                                            </td>
                                                            <td class="text-end">
                                                                <span class="fw-bold text-primary fs-6">{{ (float)$item->quantity_requested }}</span>
                                                                <span class="text-muted small ms-1">{{ $item->product->unit ?? '' }}</span>
                                                            </td>
                                                            <td>
                                                                <span class="text-muted small">{{ $item->notes ?: '—' }}</span>
                                                            </td>
                                                        </tr>
                                                        @empty
                                                        <tr>
                                                            <td colspan="5" class="text-center py-3 text-muted">No items recorded in this material request.</td>
                                                        </tr>
                                                        @endforelse
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                        <div class="modal-footer bg-light d-flex justify-content-between">
                                            <a href="{{ route('material-requests.show', $mr) }}" class="btn btn-outline-secondary btn-sm" target="_blank">
                                                <i class="fa-solid fa-up-right-from-square me-1"></i>Open Full Page
                                            </a>
                                            <div class="d-flex gap-2">
                                                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
                                                <form method="POST" action="{{ route('planning-manager.emergency-requests.material.approve', $mr) }}" class="d-inline">
                                                    @csrf
                                                    <input type="hidden" name="action" value="approve">
                                                    <button type="submit" class="btn btn-success btn-sm px-3 fw-semibold"
                                                        onclick="return confirm('Approve Emergency Material Request {{ $mr->reference_number }} and send directly to Coordinator in Procurement Queue?')">
                                                        <i class="fa-solid fa-check me-1"></i>Approve &amp; Send to Coordinator
                                                    </button>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Reject Reason Modal -->
                            <div class="modal fade text-start" id="rejectMrModal{{ $mr->id }}" tabindex="-1" aria-hidden="true">
                                <div class="modal-dialog modal-dialog-centered">
                                    <form method="POST" action="{{ route('planning-manager.emergency-requests.material.approve', $mr) }}" class="modal-content border-0 shadow">
                                        @csrf
                                        <input type="hidden" name="action" value="reject">
                                        <div class="modal-header bg-danger text-white">
                                            <h6 class="modal-title fw-bold">
                                                <i class="fa-solid fa-ban me-2"></i>Reject Material Request {{ $mr->reference_number }}
                                            </h6>
                                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                        </div>
                                        <div class="modal-body p-4">
                                            <label class="form-label fw-semibold text-dark">Rejection Reason <span class="text-danger">*</span></label>
                                            <textarea name="rejection_reason" class="form-control" rows="3" placeholder="Provide reason for rejecting this urgent request..." required></textarea>
                                        </div>
                                        <div class="modal-footer bg-light">
                                            <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                                            <button type="submit" class="btn btn-danger btn-sm">Confirm Rejection</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            @empty
            <div class="card border-0 shadow-sm">
                <div class="card-body text-center py-5">
                    <i class="fa-solid fa-circle-check fa-3x text-success mb-3 d-block"></i>
                    <h5 class="text-muted">No pending material requests</h5>
                    <p class="small text-muted mb-0">All site material requests have been processed.</p>
                </div>
            </div>
            @endforelse
        </div>

        {{-- Manpower Requests --}}
        <div class="tab-pane fade" id="manpowerTab">
            @forelse($manpowerRequests as $mp)
            <div class="card shadow-sm mb-3 border-0 border-start border-primary border-3">
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col-lg-6 col-md-12">
                            <div class="d-flex align-items-center gap-3 mb-2">
                                <div class="rounded-circle bg-primary bg-opacity-10 p-2 flex-shrink-0">
                                    <i class="fa-solid fa-users text-primary fa-lg"></i>
                                </div>
                                <div>
                                    <div class="d-flex align-items-center gap-2">
                                        <h6 class="mb-0 fw-bold text-dark">{{ ucwords(str_replace('_', ' ', $mp->type)) }} Request</h6>
                                        <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-2 py-0 small fw-semibold">
                                            #{{ $mp->id }}
                                        </span>
                                    </div>
                                    <small class="text-muted d-block mt-1">
                                        <i class="fa-solid fa-building me-1 text-secondary"></i>{{ $mp->project->name ?? 'N/A' }}
                                        &bull;
                                        <i class="fa-solid fa-user me-1 text-secondary"></i>{{ $mp->requestedBy->name ?? 'N/A' }}
                                        &bull;
                                        <i class="fa-solid fa-calendar me-1 text-primary"></i>Required by <strong>{{ optional($mp->required_date)->format('d M Y') ?? 'N/A' }}</strong>
                                    </small>
                                </div>
                            </div>
                            @if($mp->items->count() > 0)
                            <div class="ms-md-5 ps-md-2 mb-2">
                                @foreach($mp->items as $item)
                                <span class="badge bg-light text-dark border me-1 mb-1">
                                    {{ $item->role_title }} &times; <strong>{{ $item->quantity }}</strong>
                                    <span class="text-muted">({{ ucfirst($item->skill_level) }})</span>
                                </span>
                                @endforeach
                            </div>
                            @endif
                            @if($mp->notes)
                            <p class="text-muted small mb-0 ms-md-5 ps-md-2 fst-italic">
                                <i class="fa-solid fa-quote-left text-muted me-1"></i>{{ $mp->notes }}
                            </p>
                            @endif
                        </div>
                        <div class="col-lg-6 col-md-12 text-lg-end mt-3 mt-lg-0 d-flex flex-wrap align-items-center justify-content-lg-end gap-2">
                            <span class="badge bg-warning text-dark px-3 py-2">
                                <i class="fa-solid fa-clock me-1"></i>Pending
                            </span>

                            {{-- View Detail Button --}}
                            <button type="button" class="btn btn-outline-primary btn-sm px-3 shadow-sm fw-semibold" data-bs-toggle="modal" data-bs-target="#viewMpModal{{ $mp->id }}">
                                <i class="fa-solid fa-eye me-1"></i>View Detail
                            </button>

                            <form method="POST" action="{{ route('planning-manager.emergency-requests.manpower.approve', $mp) }}" class="d-inline m-0">
                                @csrf
                                <input type="hidden" name="action" value="approve">
                                <button type="submit" class="btn btn-success btn-sm px-3 shadow-sm fw-semibold"
                                    onclick="return confirm('Approve this manpower request?')">
                                    <i class="fa-solid fa-check me-1"></i>Approve
                                </button>
                            </form>
                            <form method="POST" action="{{ route('planning-manager.emergency-requests.manpower.approve', $mp) }}" class="d-inline m-0">
                                @csrf
                                <input type="hidden" name="action" value="reject">
                                <button type="submit" class="btn btn-outline-danger btn-sm px-3 shadow-sm fw-semibold"
                                    onclick="return confirm('Reject this manpower request?')">
                                    <i class="fa-solid fa-xmark me-1"></i>Reject
                                </button>
                            </form>

                            <!-- View MP Details Modal -->
                            <div class="modal fade text-start" id="viewMpModal{{ $mp->id }}" tabindex="-1" aria-labelledby="viewMpModalLabel{{ $mp->id }}" aria-hidden="true">
                                <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
                                    <div class="modal-content border-0 shadow">
                                        <div class="modal-header bg-primary text-white">
                                            <div class="d-flex align-items-center gap-2">
                                                <i class="fa-solid fa-users fa-lg"></i>
                                                <h6 class="modal-title fw-bold mb-0" id="viewMpModalLabel{{ $mp->id }}">
                                                    Emergency Manpower Request #{{ $mp->id }}
                                                </h6>
                                            </div>
                                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                        </div>
                                        <div class="modal-body p-4">
                                            <!-- Key Details Grid -->
                                            <div class="row g-3 mb-4 p-3 bg-light rounded border">
                                                <div class="col-sm-6 col-md-3">
                                                    <span class="text-muted small text-uppercase fw-semibold d-block">Request Type</span>
                                                    <span class="fw-bold text-dark">{{ ucwords(str_replace('_', ' ', $mp->type)) }}</span>
                                                </div>
                                                <div class="col-sm-6 col-md-3">
                                                    <span class="text-muted small text-uppercase fw-semibold d-block">Project</span>
                                                    <span class="fw-bold text-dark">{{ $mp->project->name ?? 'N/A' }}</span>
                                                </div>
                                                <div class="col-sm-6 col-md-3">
                                                    <span class="text-muted small text-uppercase fw-semibold d-block">Requested By</span>
                                                    <span class="fw-bold text-dark">{{ $mp->requestedBy->name ?? 'N/A' }}</span>
                                                </div>
                                                <div class="col-sm-6 col-md-3">
                                                    <span class="text-muted small text-uppercase fw-semibold d-block">Required Date</span>
                                                    <span class="fw-bold text-danger">
                                                        <i class="fa-regular fa-calendar me-1"></i>{{ optional($mp->required_date)->format('d M Y') ?? 'Immediate' }}
                                                    </span>
                                                </div>
                                                <div class="col-sm-6 col-md-3">
                                                    <span class="text-muted small text-uppercase fw-semibold d-block">Current Status</span>
                                                    <span class="badge bg-warning text-dark mt-1"><i class="fa-solid fa-clock me-1"></i>Pending</span>
                                                </div>
                                                <div class="col-sm-6 col-md-3">
                                                    <span class="text-muted small text-uppercase fw-semibold d-block">Submission Time</span>
                                                    <span class="fw-semibold text-secondary mt-1 d-block">{{ optional($mp->created_at)->format('d M Y, h:i A') }}</span>
                                                </div>
                                                <div class="col-sm-12 col-md-6">
                                                    <span class="text-muted small text-uppercase fw-semibold d-block">Notes / Justification</span>
                                                    <span class="text-dark mt-1 fst-italic d-block">{{ $mp->notes ?: 'No justification remarks specified.' }}</span>
                                                </div>
                                            </div>

                                            <!-- Roles Table -->
                                            <div class="d-flex justify-content-between align-items-center mb-2">
                                                <h6 class="fw-bold text-dark mb-0">
                                                    <i class="fa-solid fa-user-gear text-secondary me-2"></i>Requested Roles &amp; Manpower
                                                </h6>
                                                <span class="badge bg-secondary">{{ $mp->items->count() }} roles requested</span>
                                            </div>
                                            <div class="table-responsive border rounded mb-2">
                                                <table class="table table-hover align-middle mb-0">
                                                    <thead class="table-light small text-uppercase text-secondary">
                                                        <tr>
                                                            <th style="width: 45px;">#</th>
                                                            <th>Role / Trade</th>
                                                            <th class="text-center">Skill Level</th>
                                                            <th class="text-center">Count</th>
                                                            <th class="text-center">Duration</th>
                                                            <th>Remarks</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        @forelse($mp->items as $idx => $item)
                                                        <tr>
                                                            <td>{{ $idx + 1 }}</td>
                                                            <td class="fw-bold text-dark">{{ $item->role_title }}</td>
                                                            <td class="text-center">
                                                                <span class="badge bg-info-subtle text-primary border border-info-subtle">
                                                                    {{ ucfirst($item->skill_level ?? 'Standard') }}
                                                                </span>
                                                            </td>
                                                            <td class="text-center">
                                                                <span class="fw-bold text-primary fs-6">{{ $item->quantity }}</span>
                                                                <span class="text-muted small">person(s)</span>
                                                            </td>
                                                            <td class="text-center">
                                                                {{ $item->duration_days ? $item->duration_days . ' days' : '—' }}
                                                            </td>
                                                            <td>
                                                                <span class="text-muted small">{{ $item->notes ?: '—' }}</span>
                                                            </td>
                                                        </tr>
                                                        @empty
                                                        <tr>
                                                            <td colspan="6" class="text-center py-3 text-muted">No manpower roles recorded.</td>
                                                        </tr>
                                                        @endforelse
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                        <div class="modal-footer bg-light d-flex justify-content-between">
                                            @if(Route::has('manpower-requests.show'))
                                            <a href="{{ route('manpower-requests.show', $mp) }}" class="btn btn-outline-secondary btn-sm" target="_blank">
                                                <i class="fa-solid fa-up-right-from-square me-1"></i>Open Full Page
                                            </a>
                                            @else
                                            <div></div>
                                            @endif
                                            <div class="d-flex gap-2">
                                                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
                                                <form method="POST" action="{{ route('planning-manager.emergency-requests.manpower.approve', $mp) }}" class="d-inline">
                                                    @csrf
                                                    <input type="hidden" name="action" value="approve">
                                                    <button type="submit" class="btn btn-success btn-sm px-3 fw-semibold"
                                                        onclick="return confirm('Approve this manpower request?')">
                                                        <i class="fa-solid fa-check me-1"></i>Approve
                                                    </button>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            @empty
            <div class="card border-0 shadow-sm">
                <div class="card-body text-center py-5">
                    <i class="fa-solid fa-circle-check fa-3x text-success mb-3 d-block"></i>
                    <h5 class="text-muted">No pending manpower requests</h5>
                    <p class="small text-muted mb-0">All site manpower requests have been processed.</p>
                </div>
            </div>
            @endforelse
        </div>
    </div>
</div>
@endsection
