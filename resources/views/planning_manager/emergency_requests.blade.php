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
                        <div class="col-md-7">
                            <div class="d-flex align-items-center gap-3 mb-2">
                                <div class="rounded-circle bg-danger bg-opacity-10 p-2">
                                    <i class="fa-solid fa-cart-flatbed text-danger"></i>
                                </div>
                                <div>
                                    <h6 class="mb-0 fw-bold">{{ $mr->reference_number }}</h6>
                                    <small class="text-muted">
                                        <i class="fa-solid fa-building me-1"></i>{{ $mr->project->name ?? 'N/A' }}
                                        &bull;
                                        <i class="fa-solid fa-user me-1"></i>{{ $mr->creator->name ?? 'N/A' }}
                                        &bull;
                                        <i class="fa-solid fa-calendar me-1"></i>Required by {{ optional($mr->required_date)->format('d M Y') }}
                                    </small>
                                </div>
                            </div>
                            @if($mr->items->isNotEmpty())
                            <div class="ms-5 ps-2 mb-2">
                                @foreach($mr->items as $item)
                                    <span class="badge bg-light text-dark border me-1 mb-1">
                                        <i class="fa-solid fa-cube text-secondary me-1"></i>{{ $item->product->name ?? 'Item' }}: <strong>{{ (float)$item->quantity_requested }} {{ $item->product->unit ?? '' }}</strong>
                                    </span>
                                @endforeach
                            </div>
                            @endif
                            @if($mr->notes)
                            <p class="text-muted small mb-0 ms-5 ps-2"><i class="fa-solid fa-quote-left text-muted me-1"></i>{{ $mr->notes }}</p>
                            @endif
                        </div>
                        <div class="col-md-5 text-md-end mt-3 mt-md-0">
                            <span class="badge bg-warning text-dark me-2 px-3 py-2">
                                <i class="fa-solid fa-clock me-1"></i>Pending Planning
                            </span>
                            <form method="POST" action="{{ route('planning-manager.emergency-requests.material.approve', $mr) }}" class="d-inline">
                                @csrf
                                <input type="hidden" name="action" value="approve">
                                <button type="submit" class="btn btn-success btn-sm px-3 shadow-sm fw-semibold"
                                    onclick="return confirm('Approve Emergency Material Request {{ $mr->reference_number }} and send directly to Coordinator in Procurement Queue?')">
                                    <i class="fa-solid fa-check me-1"></i>Approve &amp; Send to Coordinator
                                </button>
                            </form>
                            <button type="button" class="btn btn-outline-danger btn-sm px-3 ms-1" data-bs-toggle="modal" data-bs-target="#rejectMrModal{{ $mr->id }}">
                                <i class="fa-solid fa-xmark me-1"></i>Reject
                            </button>

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
                        <div class="col-md-7">
                            <div class="d-flex align-items-center gap-3 mb-2">
                                <div class="rounded-circle bg-primary bg-opacity-10 p-2">
                                    <i class="fa-solid fa-users text-primary"></i>
                                </div>
                                <div>
                                    <h6 class="mb-0 fw-bold">{{ ucwords(str_replace('_', ' ', $mp->type)) }} Request</h6>
                                    <small class="text-muted">
                                        <i class="fa-solid fa-building me-1"></i>{{ $mp->project->name ?? 'N/A' }}
                                        &bull;
                                        <i class="fa-solid fa-user me-1"></i>{{ $mp->requestedBy->name ?? 'N/A' }}
                                        &bull;
                                        <i class="fa-solid fa-calendar me-1"></i>Required by {{ optional($mp->required_date)->format('d M Y') }}
                                    </small>
                                </div>
                            </div>
                            @if($mp->items->count() > 0)
                            <div class="ms-5 ps-2">
                                @foreach($mp->items as $item)
                                <span class="badge bg-light text-dark border me-1 mb-1">
                                    {{ $item->role_title }} &times; {{ $item->quantity }}
                                    <span class="text-muted">({{ ucfirst($item->skill_level) }})</span>
                                </span>
                                @endforeach
                            </div>
                            @endif
                        </div>
                        <div class="col-md-5 text-md-end mt-3 mt-md-0">
                            <span class="badge bg-warning text-dark me-2 px-3 py-2">
                                <i class="fa-solid fa-clock me-1"></i>Pending
                            </span>
                            <form method="POST" action="{{ route('planning-manager.emergency-requests.manpower.approve', $mp) }}" class="d-inline">
                                @csrf
                                <input type="hidden" name="action" value="approve">
                                <button type="submit" class="btn btn-success btn-sm px-3"
                                    onclick="return confirm('Approve this manpower request?')">
                                    <i class="fa-solid fa-check me-1"></i>Approve
                                </button>
                            </form>
                            <form method="POST" action="{{ route('planning-manager.emergency-requests.manpower.approve', $mp) }}" class="d-inline ms-1">
                                @csrf
                                <input type="hidden" name="action" value="reject">
                                <button type="submit" class="btn btn-outline-danger btn-sm px-3"
                                    onclick="return confirm('Reject this manpower request?')">
                                    <i class="fa-solid fa-xmark me-1"></i>Reject
                                </button>
                            </form>
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
