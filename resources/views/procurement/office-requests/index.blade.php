@extends('layouts.app')
@section('title', 'Office Supply Requests')

@section('content')
<div class="container-fluid py-3">
    <!-- Header Banner -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
        <div>
            <h1 class="h3 fw-bold text-dark mb-1">
                <i class="fa-solid fa-boxes-stacked text-primary me-2"></i>Office Supply Requests
                <span class="fs-6 fw-normal text-muted ms-2">(የቢሮ እቃዎች መጠየቂያ)</span>
            </h1>
            <p class="text-muted small mb-0">
                Secretary material & stationery requisition workflow with direct HR & Coordinator review.
            </p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('office-requests.create') }}" class="btn btn-primary shadow-sm px-3">
                <i class="fa-solid fa-plus me-1"></i> New Office Request (አዲስ ጥያቄ)
            </a>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show shadow-sm border-0 d-flex align-items-center" role="alert">
            <i class="fa-solid fa-circle-check fs-5 me-2"></i>
            <div>{{ session('success') }}</div>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <!-- Metric Cards -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm rounded-3 h-100 bg-light">
                <div class="card-body p-3 d-flex align-items-center justify-content-between">
                    <div>
                        <div class="text-muted small text-uppercase fw-bold">Total Requests</div>
                        <div class="fs-4 fw-bold text-dark mt-1">{{ number_format($stats['total']) }}</div>
                    </div>
                    <div class="rounded-circle bg-primary bg-opacity-10 p-3 text-primary">
                        <i class="fa-solid fa-list-check fa-lg"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm rounded-3 h-100 border-start border-warning border-4 bg-white">
                <div class="card-body p-3 d-flex align-items-center justify-content-between">
                    <div>
                        <div class="text-warning small text-uppercase fw-bold">Pending HR/Coordinator</div>
                        <div class="fs-4 fw-bold text-warning mt-1">{{ number_format($stats['pending']) }}</div>
                    </div>
                    <div class="rounded-circle bg-warning bg-opacity-10 p-3 text-warning">
                        <i class="fa-solid fa-clock-rotate-left fa-lg"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm rounded-3 h-100 border-start border-success border-4 bg-white">
                <div class="card-body p-3 d-flex align-items-center justify-content-between">
                    <div>
                        <div class="text-success small text-uppercase fw-bold">Approved</div>
                        <div class="fs-4 fw-bold text-success mt-1">{{ number_format($stats['approved']) }}</div>
                    </div>
                    <div class="rounded-circle bg-success bg-opacity-10 p-3 text-success">
                        <i class="fa-solid fa-check-circle fa-lg"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm rounded-3 h-100 border-start border-danger border-4 bg-white">
                <div class="card-body p-3 d-flex align-items-center justify-content-between">
                    <div>
                        <div class="text-danger small text-uppercase fw-bold">Rejected</div>
                        <div class="fs-4 fw-bold text-danger mt-1">{{ number_format($stats['rejected']) }}</div>
                    </div>
                    <div class="rounded-circle bg-danger bg-opacity-10 p-3 text-danger">
                        <i class="fa-solid fa-circle-xmark fa-lg"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters & Table Card -->
    <div class="card border-0 shadow-sm rounded-3">
        <div class="card-header bg-white py-3 border-bottom d-flex flex-wrap align-items-center justify-content-between gap-3">
            <!-- Filter Pills -->
            <ul class="nav nav-pills card-header-pills small">
                <li class="nav-item">
                    <a class="nav-link {{ !request('status') ? 'active' : '' }}" href="{{ route('office-requests.index') }}">
                        All ({{ $stats['total'] }})
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link {{ request('status') === 'pending_hr_approval' ? 'active' : '' }}" href="{{ route('office-requests.index', ['status' => 'pending_hr_approval']) }}">
                        Pending Approval <span class="badge bg-warning text-dark ms-1">{{ $stats['pending'] }}</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link {{ request('status') === 'approved' ? 'active' : '' }}" href="{{ route('office-requests.index', ['status' => 'approved']) }}">
                        Approved ({{ $stats['approved'] }})
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link {{ request('status') === 'rejected' ? 'active' : '' }}" href="{{ route('office-requests.index', ['status' => 'rejected']) }}">
                        Rejected ({{ $stats['rejected'] }})
                    </a>
                </li>
            </ul>

            <!-- Search Form -->
            <form action="{{ route('office-requests.index') }}" method="GET" class="d-flex gap-2" style="max-width: 320px;">
                @if(request('status'))
                    <input type="hidden" name="status" value="{{ request('status') }}">
                @endif
                <div class="input-group input-group-sm">
                    <input type="text" name="search" class="form-control" placeholder="Search PR / purpose..." value="{{ request('search') }}">
                    <button class="btn btn-outline-secondary" type="submit"><i class="fa-solid fa-magnifying-glass"></i></button>
                    @if(request('search'))
                        <a href="{{ route('office-requests.index', request()->only('status')) }}" class="btn btn-outline-secondary"><i class="fa-solid fa-xmark"></i></a>
                    @endif
                </div>
            </form>
        </div>

        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light text-secondary text-uppercase small" style="font-size: 0.75rem;">
                        <tr>
                            <th class="ps-3 py-3">Request No</th>
                            <th>Purpose / Category</th>
                            <th>Items</th>
                            <th>Requested By</th>
                            <th>Required Date</th>
                            <th>Priority</th>
                            <th>Status</th>
                            <th>Reviewer Note</th>
                            <th class="text-end pe-3">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        @forelse($requests as $req)
                        <tr>
                            <td class="ps-3">
                                <a href="{{ route('office-requests.show', $req) }}" class="fw-bold text-decoration-none text-primary">
                                    {{ $req->pr_no }}
                                </a>
                                <div class="text-muted" style="font-size: 0.75rem;">
                                    {{ $req->created_at ? $req->created_at->format('M d, Y') : '-' }}
                                </div>
                            </td>
                            <td>
                                <div class="fw-semibold text-dark">{{ $req->office_purpose ?: 'Office Material Requisition' }}</div>
                                @if($req->justification)
                                    <div class="text-muted text-truncate" style="max-width: 220px; font-size: 0.8rem;" title="{{ $req->justification }}">
                                        {{ $req->justification }}
                                    </div>
                                @endif
                            </td>
                            <td>
                                <span class="badge bg-secondary rounded-pill">
                                    {{ $req->items->count() }} {{ \Illuminate\Support\Str::plural('item', $req->items->count()) }}
                                </span>
                                <div class="text-muted text-truncate" style="max-width: 180px; font-size: 0.78rem;">
                                    {{ $req->items->take(2)->map(fn($i) => ($i->product?->name ?? 'Item') . ' (' . (float)$i->quantity . ' ' . $i->unit . ')')->implode(', ') }}
                                    @if($req->items->count() > 2) ... @endif
                                </div>
                            </td>
                            <td>
                                <div class="fw-medium text-dark">{{ $req->requestedBy?->name ?? 'Secretary' }}</div>
                                <div class="text-muted" style="font-size: 0.75rem;">{{ $req->requestedBy?->email }}</div>
                            </td>
                            <td>
                                @if($req->required_date)
                                    <span class="text-dark">{{ $req->required_date->format('M d, Y') }}</span>
                                @else
                                    <span class="text-muted">-</span>
                                @endif
                            </td>
                            <td>
                                @if($req->priority === 'urgent')
                                    <span class="badge bg-danger">Urgent</span>
                                @elseif($req->priority === 'high')
                                    <span class="badge bg-warning text-dark">High</span>
                                @else
                                    <span class="badge bg-light text-dark border">Normal</span>
                                @endif
                            </td>
                            <td>
                                @if($req->status === 'pending_hr_approval')
                                    <span class="badge bg-warning text-dark border border-warning">
                                        <i class="fa-solid fa-hourglass-half me-1"></i> Pending HR/Coordinator
                                    </span>
                                @elseif($req->status === 'approved')
                                    <span class="badge bg-success">
                                        <i class="fa-solid fa-circle-check me-1"></i> Approved
                                    </span>
                                @elseif($req->status === 'rejected')
                                    <span class="badge bg-danger">
                                        <i class="fa-solid fa-circle-xmark me-1"></i> Rejected
                                    </span>
                                @elseif($req->status === 'pending_procurement_team')
                                    <span class="badge bg-info text-dark">
                                        <i class="fa-solid fa-cart-shopping me-1"></i> In Sourcing
                                    </span>
                                @elseif($req->status === 'pending_store_review')
                                    <span class="badge bg-primary">
                                        <i class="fa-solid fa-warehouse me-1"></i> Store Dispatch
                                    </span>
                                @else
                                    <span class="badge bg-{{ \App\Models\PurchaseRequest::statusBadgeClass($req->status) }}">
                                        {{ $req->status_label }}
                                    </span>
                                @endif
                            </td>
                            <td>
                                @if($req->hr_coordinator_approved_by)
                                    <div class="text-success small fw-semibold">
                                        <i class="fa-solid fa-check me-1"></i>{{ $req->hrCoordinatorApprovedBy?->name }}
                                    </div>
                                    @if($req->hr_coordinator_notes)
                                        <div class="text-muted text-truncate" style="max-width: 180px; font-size: 0.75rem;" title="{{ $req->hr_coordinator_notes }}">
                                            {{ $req->hr_coordinator_notes }}
                                        </div>
                                    @endif
                                @elseif($req->rejection_reason)
                                    <div class="text-danger small text-truncate" style="max-width: 180px; font-size: 0.75rem;" title="{{ $req->rejection_reason }}">
                                        {{ $req->rejection_reason }}
                                    </div>
                                @else
                                    <span class="text-muted small">-</span>
                                @endif
                            </td>
                            <td class="text-end pe-3">
                                <a href="{{ route('office-requests.show', $req) }}" class="btn btn-sm btn-outline-primary px-2 py-1">
                                    <i class="fa-solid fa-arrow-right me-1"></i> View
                                </a>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="9" class="text-center py-5">
                                <div class="text-muted mb-3">
                                    <i class="fa-solid fa-folder-open fa-3x text-secondary opacity-50"></i>
                                </div>
                                <h6 class="text-dark fw-bold mb-1">No Office Supply Requests Found</h6>
                                <p class="text-muted small mb-3">There are no material requests matching your current filter.</p>
                                <a href="{{ route('office-requests.create') }}" class="btn btn-sm btn-primary">
                                    <i class="fa-solid fa-plus me-1"></i> Create Office Request
                                </a>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        @if($requests->hasPages())
        <div class="card-footer bg-white border-top py-2">
            <div class="d-flex justify-content-end">
                {{ $requests->links() }}
            </div>
        </div>
        @endif
    </div>
</div>
@endsection
