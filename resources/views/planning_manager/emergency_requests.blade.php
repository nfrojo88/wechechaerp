@extends('layouts.app')
@section('title', 'Emergency Requests')

@push('styles')
<style>
/* Emergency Requests - Mobile Styles */
@media (max-width: 768px) {
  /* Page header: stack on mobile */
  .er-page-header { flex-direction: column; align-items: flex-start !important; gap: 8px; }
  .er-page-header .pending-badge { align-self: flex-start; }

  /* Tab pills: scroll horizontally */
  #requestTabs { flex-wrap: nowrap; overflow-x: auto; -webkit-overflow-scrolling: touch; padding-bottom: 4px; }
  #requestTabs .nav-link { white-space: nowrap; padding: 7px 16px; font-size: 12.5px; }

  /* Card action buttons: full row on mobile */
  .er-actions-col {
    text-align: left !important;
    justify-content: flex-start !important;
    margin-top: 12px;
    padding-top: 12px;
    border-top: 1px solid #f1f5f9;
    width: 100%;
  }
  .er-actions-col form,
  .er-actions-col button {
    flex: 1 1 auto;
    min-width: 0;
  }
  .er-actions-col .btn { width: 100%; justify-content: center; }

  /* Meta badges wrap neatly */
  .er-meta-badges { display: flex; flex-wrap: wrap; gap: 4px; }
}

@media (max-width: 400px) {
  .er-actions-col .d-flex { flex-direction: column; }
}
</style>
@endpush

@section('content')
<div class="container-fluid">
    {{-- Page Header --}}
    <div class="d-flex justify-content-between align-items-center mb-3 mb-md-4 er-page-header">
        <div>
            <h1 class="h3 mb-1 fw-bold">
                <i class="fa-solid fa-bell-exclamation text-danger me-2"></i>Emergency Requests
            </h1>
            <p class="text-muted mb-0 small d-none d-sm-block">Approve or reject urgent site material &amp; manpower requests</p>
        </div>
        <div class="pending-badge">
            <span class="badge bg-danger px-3 py-2" style="font-size: 13px;">
                <i class="fa-solid fa-triangle-exclamation me-1"></i>
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
    <ul class="nav nav-pills mb-3 mb-md-4 gap-1" id="requestTabs" style="min-width: 0;">
        <li class="nav-item">
            <a class="nav-link active px-3 px-md-4" data-bs-toggle="pill" href="#materialTab">
                <i class="fa-solid fa-cart-flatbed me-1 me-md-2"></i><span>Material</span>
                <span class="badge bg-white text-danger ms-1">{{ $materialRequests->count() }}</span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link px-3 px-md-4" data-bs-toggle="pill" href="#manpowerTab">
                <i class="fa-solid fa-users me-1 me-md-2"></i><span>Manpower</span>
                <span class="badge bg-white text-primary ms-1">{{ $manpowerRequests->count() }}</span>
            </a>
        </li>
    </ul>

    <div class="tab-content">
        {{-- Material Requests --}}
        <div class="tab-pane fade show active" id="materialTab">
            @forelse($materialRequests as $mr)
            <div class="card shadow-sm mb-3 border-0 border-start border-danger border-3">
                <div class="card-body p-3 p-md-4">
                    <div class="d-flex flex-column flex-md-row align-items-md-start gap-3">
                        {{-- Left: info --}}
                        <div class="flex-grow-1">
                            <div class="d-flex align-items-center gap-2 mb-2">
                                <div class="rounded-circle bg-danger bg-opacity-10 p-2 flex-shrink-0">
                                    <i class="fa-solid fa-cart-flatbed text-danger"></i>
                                </div>
                                <div>
                                    <div class="d-flex align-items-center gap-2 flex-wrap">
                                        <h6 class="mb-0 fw-bold text-dark" style="font-size: 0.95rem;">{{ $mr->reference_number }}</h6>
                                        <span class="badge bg-danger-subtle text-danger border border-danger-subtle px-2 py-0 small fw-semibold">
                                            <i class="fa-solid fa-triangle-exclamation me-1"></i>Urgent
                                        </span>
                                    </div>
                                    <div class="text-muted mt-1 er-meta-badges" style="font-size: 11.5px;">
                                        <span><i class="fa-solid fa-building me-1 text-secondary"></i>{{ $mr->project->name ?? 'N/A' }}</span>
                                        <span class="d-none d-sm-inline text-muted mx-1">&bull;</span>
                                        <span><i class="fa-solid fa-user me-1 text-secondary"></i>{{ $mr->creator->name ?? 'N/A' }}</span>
                                        <span class="d-none d-sm-inline text-muted mx-1">&bull;</span>
                                        <span><i class="fa-solid fa-calendar me-1 text-danger"></i>Due: <strong>{{ optional($mr->required_date)->format('d M Y') ?? 'N/A' }}</strong></span>
                                    </div>
                                </div>
                            </div>

                            @if($mr->items->isNotEmpty())
                            <div class="ms-0 ms-md-5 ps-0 ps-md-1 mb-1">
                                @foreach($mr->items as $item)
                                    <span class="badge bg-light text-dark border me-1 mb-1" style="font-size: 11px;">
                                        <i class="fa-solid fa-cube text-secondary me-1"></i>{{ $item->product->name ?? 'Item' }}: <strong>{{ (float)$item->quantity_requested }} {{ $item->product->unit ?? '' }}</strong>
                                    </span>
                                @endforeach
                            </div>
                            @endif

                            @if($mr->notes)
                            <p class="text-muted small mb-0 ms-0 ms-md-5 fst-italic">
                                <i class="fa-solid fa-quote-left text-muted me-1"></i>{{ Str::limit($mr->notes, 100) }}
                            </p>
                            @endif
                        </div>

                        {{-- Right: actions (stacks below on mobile) --}}
                        <div class="d-flex flex-wrap align-items-center gap-2 er-actions-col">
                            <span class="badge bg-warning text-dark px-2 py-1">
                                <i class="fa-solid fa-clock me-1"></i>Pending
                            </span>

                            <button type="button" class="btn btn-outline-primary btn-sm px-2 px-md-3 fw-semibold" data-bs-toggle="modal" data-bs-target="#viewMrModal{{ $mr->id }}">
                                <i class="fa-solid fa-eye me-1"></i>View
                            </button>

                            <form method="POST" action="{{ route('planning-manager.emergency-requests.material.approve', $mr) }}" class="d-inline m-0">
                                @csrf
                                <input type="hidden" name="action" value="approve">
                                <button type="submit" class="btn btn-success btn-sm px-2 px-md-3 fw-semibold"
                                    onclick="return confirm('Approve {{ $mr->reference_number }} and send to Coordinator?')">
                                    <i class="fa-solid fa-check me-1"></i><span class="d-none d-sm-inline">Approve &amp; Send</span><span class="d-sm-none">Approve</span>
                                </button>
                            </form>

                            <button type="button" class="btn btn-outline-danger btn-sm px-2 px-md-3 fw-semibold" data-bs-toggle="modal" data-bs-target="#rejectMrModal{{ $mr->id }}">
                                <i class="fa-solid fa-xmark me-1"></i>Reject
                            </button>
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
                <div class="card-body p-3 p-md-4">
                    <div class="d-flex flex-column flex-md-row align-items-md-start gap-3">
                        {{-- Left: info --}}
                        <div class="flex-grow-1">
                            <div class="d-flex align-items-center gap-2 mb-2">
                                <div class="rounded-circle bg-primary bg-opacity-10 p-2 flex-shrink-0">
                                    <i class="fa-solid fa-users text-primary"></i>
                                </div>
                                <div>
                                    <div class="d-flex align-items-center gap-2 flex-wrap">
                                        <h6 class="mb-0 fw-bold text-dark" style="font-size: 0.95rem;">{{ ucwords(str_replace('_', ' ', $mp->type)) }} Request</h6>
                                        <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-2 py-0 small fw-semibold">#{{ $mp->id }}</span>
                                    </div>
                                    <div class="text-muted mt-1 er-meta-badges" style="font-size: 11.5px;">
                                        <span><i class="fa-solid fa-building me-1 text-secondary"></i>{{ $mp->project->name ?? 'N/A' }}</span>
                                        <span class="d-none d-sm-inline text-muted mx-1">&bull;</span>
                                        <span><i class="fa-solid fa-user me-1 text-secondary"></i>{{ $mp->requestedBy->name ?? 'N/A' }}</span>
                                        <span class="d-none d-sm-inline text-muted mx-1">&bull;</span>
                                        <span><i class="fa-solid fa-calendar me-1 text-primary"></i>Due: <strong>{{ optional($mp->required_date)->format('d M Y') ?? 'N/A' }}</strong></span>
                                    </div>
                                </div>
                            </div>

                            @if($mp->items->count() > 0)
                            <div class="ms-0 ms-md-5 mb-1">
                                @foreach($mp->items as $item)
                                <span class="badge bg-light text-dark border me-1 mb-1" style="font-size: 11px;">
                                    {{ $item->role_title }} &times; <strong>{{ $item->quantity }}</strong>
                                    <span class="text-muted d-none d-sm-inline">({{ ucfirst($item->skill_level) }})</span>
                                </span>
                                @endforeach
                            </div>
                            @endif

                            @if($mp->notes)
                            <p class="text-muted small mb-0 ms-0 ms-md-5 fst-italic">
                                <i class="fa-solid fa-quote-left text-muted me-1"></i>{{ Str::limit($mp->notes, 100) }}
                            </p>
                            @endif
                        </div>

                        {{-- Right: actions (stacks below on mobile) --}}
                        <div class="d-flex flex-wrap align-items-center gap-2 er-actions-col">
                            <span class="badge bg-warning text-dark px-2 py-1">
                                <i class="fa-solid fa-clock me-1"></i>Pending
                            </span>

                            <button type="button" class="btn btn-outline-primary btn-sm px-2 px-md-3 fw-semibold" data-bs-toggle="modal" data-bs-target="#viewMpModal{{ $mp->id }}">
                                <i class="fa-solid fa-eye me-1"></i>View
                            </button>

                            <form method="POST" action="{{ route('planning-manager.emergency-requests.manpower.approve', $mp) }}" class="d-inline m-0">
                                @csrf
                                <input type="hidden" name="action" value="approve">
                                <button type="submit" class="btn btn-success btn-sm px-2 px-md-3 fw-semibold"
                                    onclick="return confirm('Approve this manpower request?')">
                                    <i class="fa-solid fa-check me-1"></i>Approve
                                </button>
                            </form>
                            <form method="POST" action="{{ route('planning-manager.emergency-requests.manpower.approve', $mp) }}" class="d-inline m-0">
                                @csrf
                                <input type="hidden" name="action" value="reject">
                                <button type="submit" class="btn btn-outline-danger btn-sm px-2 px-md-3 fw-semibold"
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

{{-- =========================================================================
     MODALS SECTION (Rendered outside tabs to prevent layout & z-index glitches)
     ========================================================================= --}}

{{-- Material Requests Modals --}}
@foreach($materialRequests as $mr)
    {{-- View Detail Modal --}}
    <div class="modal fade" id="viewMrModal{{ $mr->id }}" tabindex="-1" aria-labelledby="viewMrModalLabel{{ $mr->id }}" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
                <div class="modal-header py-3 px-4" style="background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%); border-bottom: 1px solid rgba(255,255,255,0.1);">
                    <div class="d-flex align-items-center gap-3">
                        <div class="rounded-3 p-2 d-flex align-items-center justify-content-center shadow-sm" style="background: rgba(239, 68, 68, 0.18); border: 1px solid rgba(239, 68, 68, 0.3); width: 42px; height: 42px;">
                            <i class="fa-solid fa-cart-flatbed text-danger fa-lg"></i>
                        </div>
                        <div>
                            <h5 class="modal-title fw-bold mb-0 text-white" id="viewMrModalLabel{{ $mr->id }}" style="font-size: 1.15rem;">
                                Emergency Material Request: {{ $mr->reference_number }}
                            </h5>
                            <span class="small" style="color: #94a3b8; font-size: 0.8rem;">Site Urgent Requisition Breakdown</span>
                        </div>
                    </div>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                
                <div class="modal-body p-4 bg-white">
                    {{-- Key Metadata Grid --}}
                    <div class="p-3 mb-4 rounded-3 border" style="background: #f8fafc; border-color: #e2e8f0 !important;">
                        <div class="row g-3">
                            <div class="col-md-6 col-sm-6">
                                <small class="text-uppercase fw-bold d-block" style="color: #64748b; font-size: 0.72rem; letter-spacing: 0.05em;">Project</small>
                                <span class="fw-bold" style="color: #0f172a; font-size: 0.95rem;">{{ $mr->project->name ?? 'N/A' }}</span>
                            </div>
                            <div class="col-md-6 col-sm-6">
                                <small class="text-uppercase fw-bold d-block" style="color: #64748b; font-size: 0.72rem; letter-spacing: 0.05em;">Destination Store</small>
                                <span class="fw-bold" style="color: #0f172a; font-size: 0.95rem;">{{ $mr->store->name ?? 'Site General Store' }}</span>
                            </div>
                            <div class="col-md-4 col-sm-6">
                                <small class="text-uppercase fw-bold d-block" style="color: #64748b; font-size: 0.72rem; letter-spacing: 0.05em;">Requested By</small>
                                <span class="fw-semibold text-dark">{{ $mr->creator->name ?? 'N/A' }}</span>
                            </div>
                            <div class="col-md-4 col-sm-6">
                                <small class="text-uppercase fw-bold d-block" style="color: #64748b; font-size: 0.72rem; letter-spacing: 0.05em;">Required By Date</small>
                                <span class="badge rounded-pill px-2.5 py-1.5" style="background: #fef2f2; color: #dc2626; border: 1px solid #fecaca; font-weight: 600;">
                                    <i class="fa-regular fa-calendar-xmark me-1"></i>{{ optional($mr->required_date)->format('d M Y') ?? 'Immediate' }}
                                </span>
                            </div>
                            <div class="col-md-4 col-sm-6">
                                <small class="text-uppercase fw-bold d-block" style="color: #64748b; font-size: 0.72rem; letter-spacing: 0.05em;">Status</small>
                                <span class="badge rounded-pill px-2.5 py-1.5" style="background: #fefce8; color: #a16207; border: 1px solid #fef08a; font-weight: 600;">
                                    <i class="fa-solid fa-clock me-1"></i>Pending Planning
                                </span>
                            </div>
                            @if($mr->notes)
                            <div class="col-12 pt-2 border-top" style="border-color: #e2e8f0 !important;">
                                <small class="text-uppercase fw-bold d-block" style="color: #64748b; font-size: 0.72rem; letter-spacing: 0.05em;">Urgency Justification</small>
                                <span class="text-dark fst-italic">{{ $mr->notes }}</span>
                            </div>
                            @endif
                        </div>
                    </div>

                    {{-- Materials Table --}}
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <label class="form-label fw-bold small text-uppercase mb-0" style="color: #334155; letter-spacing: 0.04em;">
                            <i class="fa-solid fa-boxes-stacked text-secondary me-1"></i>Requested Items List
                        </label>
                        <span class="badge rounded-pill px-2.5 py-1" style="background: #f1f5f9; color: #475569; border: 1px solid #cbd5e1; font-weight: 600;">
                            {{ $mr->items->count() }} line items
                        </span>
                    </div>

                    <div class="table-responsive border rounded-3 overflow-hidden">
                        <table class="table table-hover align-middle mb-0">
                            <thead style="background: #f8fafc; border-bottom: 1px solid #e2e8f0;">
                                <tr class="small text-uppercase text-secondary" style="font-size: 0.75rem; letter-spacing: 0.04em;">
                                    <th style="width: 45px;" class="py-2.5 px-3">#</th>
                                    <th class="py-2.5">Material / Item</th>
                                    <th class="py-2.5">Item Code / Category</th>
                                    <th class="text-end py-2.5">Requested Qty</th>
                                    <th class="py-2.5 px-3">Remarks</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($mr->items as $idx => $item)
                                <tr>
                                    <td class="text-muted px-3">{{ $idx + 1 }}</td>
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
                                    <td class="px-3">
                                        <span class="text-muted small">{{ $item->notes ?: '—' }}</span>
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="5" class="text-center py-4 text-muted">No items recorded in this material request.</td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="modal-footer py-3 px-4 bg-light border-top d-flex justify-content-between align-items-center">
                    <a href="{{ route('material-requests.show', $mr) }}" class="btn btn-outline-secondary btn-sm px-3 rounded-2" target="_blank">
                        <i class="fa-solid fa-arrow-up-right-from-square me-1"></i>Open Full Page
                    </a>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-secondary btn-sm px-3 rounded-2" data-bs-dismiss="modal">Close</button>
                        <form method="POST" action="{{ route('planning-manager.emergency-requests.material.approve', $mr) }}" class="d-inline m-0">
                            @csrf
                            <input type="hidden" name="action" value="approve">
                            <button type="submit" class="btn btn-success btn-sm px-3 rounded-2 fw-semibold"
                                onclick="return confirm('Approve Emergency Material Request {{ $mr->reference_number }} and send directly to Coordinator in Procurement Queue?')">
                                <i class="fa-solid fa-check me-1"></i>Approve &amp; Send to Coordinator
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Reject Modal --}}
    <div class="modal fade" id="rejectMrModal{{ $mr->id }}" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form method="POST" action="{{ route('planning-manager.emergency-requests.material.approve', $mr) }}" class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
                @csrf
                <input type="hidden" name="action" value="reject">
                <div class="modal-header py-3 px-4" style="background: linear-gradient(135deg, #991b1b 0%, #7f1d1d 100%); border-bottom: 1px solid rgba(255,255,255,0.1);">
                    <div class="d-flex align-items-center gap-3">
                        <div class="rounded-3 p-2 d-flex align-items-center justify-content-center shadow-sm" style="background: rgba(255, 255, 255, 0.2); width: 38px; height: 38px;">
                            <i class="fa-solid fa-ban text-white"></i>
                        </div>
                        <div>
                            <h6 class="modal-title fw-bold mb-0 text-white">Reject Request {{ $mr->reference_number }}</h6>
                            <span class="small" style="color: #fca5a5; font-size: 0.8rem;">Provide rejection explanation</span>
                        </div>
                    </div>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4 bg-white">
                    <label class="form-label fw-bold small text-uppercase text-dark" style="letter-spacing: 0.04em;">Rejection Reason <span class="text-danger">*</span></label>
                    <textarea name="rejection_reason" class="form-control rounded-3" rows="3" placeholder="Provide reason for rejecting this urgent request..." required></textarea>
                </div>
                <div class="modal-footer py-3 px-4 bg-light border-top">
                    <button type="button" class="btn btn-secondary btn-sm px-3 rounded-2" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger btn-sm px-3 rounded-2 fw-semibold">Confirm Rejection</button>
                </div>
            </form>
        </div>
    </div>
@endforeach

{{-- Manpower Requests Modals --}}
@foreach($manpowerRequests as $mp)
    {{-- View Detail Modal --}}
    <div class="modal fade" id="viewMpModal{{ $mp->id }}" tabindex="-1" aria-labelledby="viewMpModalLabel{{ $mp->id }}" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
                <div class="modal-header py-3 px-4" style="background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%); border-bottom: 1px solid rgba(255,255,255,0.1);">
                    <div class="d-flex align-items-center gap-3">
                        <div class="rounded-3 p-2 d-flex align-items-center justify-content-center shadow-sm" style="background: rgba(59, 130, 246, 0.18); border: 1px solid rgba(59, 130, 246, 0.3); width: 42px; height: 42px;">
                            <i class="fa-solid fa-users text-primary fa-lg"></i>
                        </div>
                        <div>
                            <h5 class="modal-title fw-bold mb-0 text-white" id="viewMpModalLabel{{ $mp->id }}" style="font-size: 1.15rem;">
                                Emergency Manpower Request #{{ $mp->id }}
                            </h5>
                            <span class="small" style="color: #94a3b8; font-size: 0.8rem;">Site Urgent Manpower Allocation</span>
                        </div>
                    </div>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                
                <div class="modal-body p-4 bg-white">
                    {{-- Key Metadata Grid --}}
                    <div class="p-3 mb-4 rounded-3 border" style="background: #f8fafc; border-color: #e2e8f0 !important;">
                        <div class="row g-3">
                            <div class="col-md-6 col-sm-6">
                                <small class="text-uppercase fw-bold d-block" style="color: #64748b; font-size: 0.72rem; letter-spacing: 0.05em;">Request Type</small>
                                <span class="fw-bold" style="color: #0f172a; font-size: 0.95rem;">{{ ucwords(str_replace('_', ' ', $mp->type)) }}</span>
                            </div>
                            <div class="col-md-6 col-sm-6">
                                <small class="text-uppercase fw-bold d-block" style="color: #64748b; font-size: 0.72rem; letter-spacing: 0.05em;">Project</small>
                                <span class="fw-bold" style="color: #0f172a; font-size: 0.95rem;">{{ $mp->project->name ?? 'N/A' }}</span>
                            </div>
                            <div class="col-md-4 col-sm-6">
                                <small class="text-uppercase fw-bold d-block" style="color: #64748b; font-size: 0.72rem; letter-spacing: 0.05em;">Requested By</small>
                                <span class="fw-semibold text-dark">{{ $mp->requestedBy->name ?? 'N/A' }}</span>
                            </div>
                            <div class="col-md-4 col-sm-6">
                                <small class="text-uppercase fw-bold d-block" style="color: #64748b; font-size: 0.72rem; letter-spacing: 0.05em;">Required By Date</small>
                                <span class="badge rounded-pill px-2.5 py-1.5" style="background: #eff6ff; color: #2563eb; border: 1px solid #bfdbfe; font-weight: 600;">
                                    <i class="fa-regular fa-calendar me-1"></i>{{ optional($mp->required_date)->format('d M Y') ?? 'Immediate' }}
                                </span>
                            </div>
                            <div class="col-md-4 col-sm-6">
                                <small class="text-uppercase fw-bold d-block" style="color: #64748b; font-size: 0.72rem; letter-spacing: 0.05em;">Status</small>
                                <span class="badge rounded-pill px-2.5 py-1.5" style="background: #fefce8; color: #a16207; border: 1px solid #fef08a; font-weight: 600;">
                                    <i class="fa-solid fa-clock me-1"></i>Pending
                                </span>
                            </div>
                            @if($mp->notes)
                            <div class="col-12 pt-2 border-top" style="border-color: #e2e8f0 !important;">
                                <small class="text-uppercase fw-bold d-block" style="color: #64748b; font-size: 0.72rem; letter-spacing: 0.05em;">Notes / Justification</small>
                                <span class="text-dark fst-italic">{{ $mp->notes }}</span>
                            </div>
                            @endif
                        </div>
                    </div>

                    {{-- Roles Table --}}
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <label class="form-label fw-bold small text-uppercase mb-0" style="color: #334155; letter-spacing: 0.04em;">
                            <i class="fa-solid fa-user-gear text-secondary me-1"></i>Requested Roles &amp; Trades
                        </label>
                        <span class="badge rounded-pill px-2.5 py-1" style="background: #f1f5f9; color: #475569; border: 1px solid #cbd5e1; font-weight: 600;">
                            {{ $mp->items->count() }} roles
                        </span>
                    </div>

                    <div class="table-responsive border rounded-3 overflow-hidden">
                        <table class="table table-hover align-middle mb-0">
                            <thead style="background: #f8fafc; border-bottom: 1px solid #e2e8f0;">
                                <tr class="small text-uppercase text-secondary" style="font-size: 0.75rem; letter-spacing: 0.04em;">
                                    <th style="width: 45px;" class="py-2.5 px-3">#</th>
                                    <th class="py-2.5">Role / Trade</th>
                                    <th class="text-center py-2.5">Skill Level</th>
                                    <th class="text-center py-2.5">Count</th>
                                    <th class="text-center py-2.5">Duration</th>
                                    <th class="py-2.5 px-3">Remarks</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($mp->items as $idx => $item)
                                <tr>
                                    <td class="text-muted px-3">{{ $idx + 1 }}</td>
                                    <td class="fw-bold text-dark">{{ $item->role_title }}</td>
                                    <td class="text-center">
                                        <span class="badge rounded-pill px-2.5 py-1" style="background: #f0fdfa; color: #0d9488; border: 1px solid #99f6e4; font-weight: 600;">
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
                                    <td class="px-3">
                                        <span class="text-muted small">{{ $item->notes ?: '—' }}</span>
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="6" class="text-center py-4 text-muted">No manpower roles recorded.</td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="modal-footer py-3 px-4 bg-light border-top d-flex justify-content-between align-items-center">
                    @if(Route::has('manpower-requests.show'))
                    <a href="{{ route('manpower-requests.show', $mp) }}" class="btn btn-outline-secondary btn-sm px-3 rounded-2" target="_blank">
                        <i class="fa-solid fa-arrow-up-right-from-square me-1"></i>Open Full Page
                    </a>
                    @else
                    <div></div>
                    @endif
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-secondary btn-sm px-3 rounded-2" data-bs-dismiss="modal">Close</button>
                        <form method="POST" action="{{ route('planning-manager.emergency-requests.manpower.approve', $mp) }}" class="d-inline m-0">
                            @csrf
                            <input type="hidden" name="action" value="approve">
                            <button type="submit" class="btn btn-success btn-sm px-3 rounded-2 fw-semibold"
                                onclick="return confirm('Approve this manpower request?')">
                                <i class="fa-solid fa-check me-1"></i>Approve
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endforeach
@endsection
