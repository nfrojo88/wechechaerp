@extends('layouts.app')
@section('title', 'General Service — Maintenance Requests')
@section('content')
<div class="container-fluid">

    {{-- Page Header --}}
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h1 class="h3 mb-1 fw-bold"><i class="fa-solid fa-screwdriver-wrench me-2 text-warning"></i>General Service — Maintenance</h1>
            <p class="text-muted mb-0 small">Asset maintenance requests reported by employees &amp; workshop service orders</p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ Route::has('general-service.maintenance.create') ? route('general-service.maintenance.create') : url('/general-service/maintenance/create') }}" class="btn btn-primary btn-sm rounded-pill px-3 shadow-sm fw-bold">
                <i class="fa-solid fa-plus me-1"></i>New Service Request
            </a>
            <a href="{{ Route::has('general-service.dashboard') ? route('general-service.dashboard') : url('/general-service') }}" class="btn btn-outline-secondary btn-sm rounded-pill px-3">
                <i class="fa-solid fa-gauge me-1"></i>Dashboard
            </a>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm rounded-3">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    {{-- Stats Cards --}}
    {{-- Stats Cards for 6-Step Workflow --}}
    <div class="row g-3 mb-4">
        <div class="col-xl-2 col-md-4 col-sm-6">
            <div class="card border-0 shadow-sm rounded-4 h-100" style="border-left: 4px solid #f59e0b !important;">
                <div class="card-body p-3">
                    <span class="text-muted small text-uppercase fw-semibold d-block" style="font-size:0.7rem;">Step 1: Awaiting GS</span>
                    <div class="fs-4 fw-bold text-dark mt-1">{{ $stats['pending_gs'] ?? 0 }}</div>
                    <small class="text-muted">Awaiting initial review</small>
                </div>
            </div>
        </div>
        <div class="col-xl-2 col-md-4 col-sm-6">
            <div class="card border-0 shadow-sm rounded-4 h-100" style="border-left: 4px solid #3b82f6 !important;">
                <div class="card-body p-3">
                    <span class="text-muted small text-uppercase fw-semibold d-block" style="font-size:0.7rem;">Step 2: Sent to GM</span>
                    <div class="fs-4 fw-bold text-primary mt-1">{{ $stats['submitted_to_gm'] ?? 0 }}</div>
                    <small class="text-muted">Awaiting permission</small>
                </div>
            </div>
        </div>
        <div class="col-xl-2 col-md-4 col-sm-6">
            <div class="card border-0 shadow-sm rounded-4 h-100" style="border-left: 4px solid #06b6d4 !important;">
                <div class="card-body p-3">
                    <span class="text-muted small text-uppercase fw-semibold d-block" style="font-size:0.7rem;">Step 3: GM Approved</span>
                    <div class="fs-4 fw-bold text-info mt-1">{{ $stats['gm_approved_initial'] ?? 0 }}</div>
                    <small class="text-muted">Assign Petty Cash</small>
                </div>
            </div>
        </div>
        <div class="col-xl-2 col-md-4 col-sm-6">
            <div class="card border-0 shadow-sm rounded-4 h-100" style="border-left: 4px solid #ef4444 !important;">
                <div class="card-body p-3">
                    <span class="text-muted small text-uppercase fw-semibold d-block" style="font-size:0.7rem;">Step 5: GM Returned</span>
                    <div class="fs-4 fw-bold text-danger mt-1">{{ $stats['gm_returned_to_gs'] ?? 0 }}</div>
                    <small class="text-muted">Provide Money/Time/Parts</small>
                </div>
            </div>
        </div>
        <div class="col-xl-2 col-md-4 col-sm-6">
            <div class="card border-0 shadow-sm rounded-4 h-100" style="border-left: 4px solid #8b5cf6 !important;">
                <div class="card-body p-3">
                    <span class="text-muted small text-uppercase fw-semibold d-block" style="font-size:0.7rem;">Step 4/5: Final GM Review</span>
                    <div class="fs-4 fw-bold text-purple mt-1" style="color:#8b5cf6;">{{ $stats['pending_gm_final'] ?? 0 }}</div>
                    <small class="text-muted">Finance &amp; PR sign-off</small>
                </div>
            </div>
        </div>
        <div class="col-xl-2 col-md-4 col-sm-6">
            <div class="card border-0 shadow-sm rounded-4 h-100" style="border-left: 4px solid #10b981 !important;">
                <div class="card-body p-3">
                    <span class="text-muted small text-uppercase fw-semibold d-block" style="font-size:0.7rem;">Active &amp; In Progress</span>
                    <div class="fs-4 fw-bold text-success mt-1">{{ $stats['in_progress'] ?? 0 }}</div>
                    <small class="text-muted">Total: {{ $stats['total'] ?? 0 }}</small>
                </div>
            </div>
        </div>
    </div>

    {{-- Filter Bar --}}
    <div class="card border-0 shadow-sm rounded-4 mb-4">
        <div class="card-body py-3 px-4">
            <form method="GET" class="row g-2 align-items-end">
                <div class="col-md-3">
                    <label class="form-label small fw-semibold mb-1">Search</label>
                    <input type="text" name="search" class="form-control form-control-sm rounded-3"
                           placeholder="Request no., asset name, employee..." value="{{ request('search') }}">
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-semibold mb-1">Workflow Stage</label>
                    <select name="gs_status" class="form-select form-select-sm rounded-3">
                        <option value="">All Workflow Stages</option>
                        <option value="pending_gs" {{ request('gs_status') == 'pending_gs' ? 'selected' : '' }}>Step 1: Awaiting GS Review</option>
                        <option value="submitted_to_gm" {{ request('gs_status') == 'submitted_to_gm' ? 'selected' : '' }}>Step 2: Sent to GM for Permission</option>
                        <option value="gm_approved_initial" {{ request('gs_status') == 'gm_approved_initial' ? 'selected' : '' }}>Step 3: GM Approved (Assign Petty Cash)</option>
                        <option value="gm_returned_to_gs" {{ request('gs_status') == 'gm_returned_to_gs' ? 'selected' : '' }}>Step 5: Returned by GM (Needs Details)</option>
                        <option value="pending_gm_final" {{ request('gs_status') == 'pending_gm_final' ? 'selected' : '' }}>Step 4/5: Pending GM Final Approval</option>
                        <option value="approved" {{ request('gs_status') == 'approved' ? 'selected' : '' }}>Approved &amp; Active</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small fw-semibold mb-1">Status</label>
                    <select name="status" class="form-select form-select-sm rounded-3">
                        <option value="">All Statuses</option>
                        <option value="pending" {{ request('status') == 'pending' ? 'selected' : '' }}>Pending</option>
                        <option value="in_progress" {{ request('status') == 'in_progress' ? 'selected' : '' }}>In Progress</option>
                        <option value="resolved" {{ request('status') == 'resolved' ? 'selected' : '' }}>Resolved</option>
                        <option value="closed" {{ request('status') == 'closed' ? 'selected' : '' }}>Closed</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small fw-semibold mb-1">Urgency</label>
                    <select name="urgency" class="form-select form-select-sm rounded-3">
                        <option value="">All Urgency</option>
                        <option value="critical" {{ request('urgency') == 'critical' ? 'selected' : '' }}>🔴 Critical</option>
                        <option value="urgent" {{ request('urgency') == 'urgent' ? 'selected' : '' }}>🟠 Urgent</option>
                        <option value="normal" {{ request('urgency') == 'normal' ? 'selected' : '' }}>🔵 Normal</option>
                        <option value="low" {{ request('urgency') == 'low' ? 'selected' : '' }}>🟢 Low</option>
                    </select>
                </div>
                <div class="col-md-auto">
                    <button type="submit" class="btn btn-sm btn-primary rounded-3 px-3">
                        <i class="fa-solid fa-magnifying-glass me-1"></i>Filter
                    </button>
                    @if(request()->hasAny(['search','status','urgency','gs_status']))
                        <a href="{{ route('general-service.maintenance.index') }}" class="btn btn-sm btn-outline-secondary rounded-3 px-3 ms-1">Clear</a>
                    @endif
                </div>
            </form>
        </div>
    </div>

    {{-- Requests Table --}}
    <div class="card border-0 shadow-sm rounded-4">
        <div class="card-body p-0">
            @if($requests->count() > 0)
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" style="font-size: 0.88rem;">
                    <thead class="table-light text-muted text-uppercase small">
                        <tr>
                            <th class="ps-4 py-3">Request No.</th>
                            <th class="py-3">Employee</th>
                            <th class="py-3">Asset</th>
                            <th class="py-3">Workflow Lifecycle</th>
                            <th class="py-3">Status &amp; Urgency</th>
                            <th class="py-3">Petty Cash &amp; Tech</th>
                            <th class="py-3 pe-4 text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($requests as $req)
                        @php 
                            $sb = $req->status_badge; 
                            $ub = $req->urgency_badge; 
                            $gsb = $req->gs_status_badge;
                        @endphp
                        <tr class="{{ $req->gs_status === 'gm_returned_to_gs' ? 'table-danger' : ($req->gs_status === 'gm_approved_initial' ? 'table-info' : '') }}">
                            <td class="ps-4 py-3">
                                <a href="{{ route('general-service.maintenance.show', $req) }}" class="font-monospace fw-bold text-primary text-decoration-none">
                                    {{ $req->request_no }}
                                </a>
                                <div class="text-muted small" style="font-size:0.75rem;">{{ $req->created_at->format('d M Y') }}</div>
                            </td>
                            <td class="py-3">
                                <div class="fw-semibold text-dark">{{ $req->employee->full_name ?? 'N/A' }}</div>
                                <div class="text-muted small">{{ $req->employee->employee_code ?? '' }}</div>
                            </td>
                            <td class="py-3">
                                <div class="fw-semibold text-dark">{{ $req->asset_name }}</div>
                                @if($req->asset_code)
                                    <span class="badge bg-dark font-monospace" style="font-size:0.68rem;">{{ $req->asset_code }}</span>
                                @endif
                            </td>
                            <td class="py-3">
                                <span class="badge {{ $gsb['class'] }} rounded-pill px-2.5 py-1">
                                    <i class="fa-solid {{ $gsb['icon'] }} me-1"></i>{{ $gsb['label'] }}
                                </span>
                            </td>
                            <td class="py-3">
                                <div class="d-flex flex-column gap-1">
                                    <span class="badge {{ $sb['class'] }} rounded-pill align-self-start" style="font-size:0.72rem;">
                                        <i class="fa-solid {{ $sb['icon'] }} me-1"></i>{{ $sb['label'] }}
                                    </span>
                                    <span class="badge {{ $ub['class'] }} rounded-pill align-self-start" style="font-size:0.68rem;">{{ $ub['label'] }}</span>
                                </div>
                            </td>
                            <td class="py-3">
                                @if($req->pettyCashOwner || $req->maintenance_person_name)
                                    <div class="small">
                                        @if($req->pettyCashOwner)
                                            <div class="text-success fw-semibold"><i class="fa-solid fa-wallet me-1"></i>{{ $req->pettyCashOwner->name }}</div>
                                        @endif
                                        @if($req->maintenance_person_name)
                                            <div class="text-muted" style="font-size:0.75rem;"><i class="fa-solid fa-wrench me-1"></i>{{ $req->maintenance_person_name }}</div>
                                        @endif
                                    </div>
                                @else
                                    <span class="text-muted small fst-italic">—</span>
                                @endif
                            </td>
                            <td class="py-3 pe-4 text-center">
                                <div class="btn-group btn-group-sm">
                                    <a href="{{ route('general-service.maintenance.show', $req) }}"
                                       class="btn btn-sm btn-primary rounded-pill px-3 me-1 shadow-xs fw-semibold">
                                        @if(in_array($req->gs_status, ['pending_gs', null]))
                                            <i class="fa-solid fa-paper-plane me-1"></i>Review &amp; Send to GM
                                        @elseif($req->gs_status === 'gm_approved_initial')
                                            <i class="fa-solid fa-user-plus me-1"></i>Assign Petty Cash
                                        @elseif($req->gs_status === 'gm_returned_to_gs')
                                            <i class="fa-solid fa-rotate-left me-1"></i>Provide Details
                                        @else
                                            <i class="fa-solid fa-eye me-1"></i>Manage
                                        @endif
                                    </a>
                                    <a href="{{ Route::has('general-service.maintenance.report') ? route('general-service.maintenance.report', $req) : url('/general-service/maintenance/' . $req->id . '/report') }}"
                                       class="btn btn-sm btn-outline-secondary rounded-pill px-2" title="Print Maintenance Report" target="_blank">
                                        <i class="fa-solid fa-print"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if($requests->hasPages())
            <div class="px-4 py-3 border-top">
                {{ $requests->withQueryString()->links('pagination::bootstrap-4') }}
            </div>
            @endif
            @else
            <div class="text-center py-5 text-muted">
                <i class="fa-solid fa-wrench fa-3x mb-3 opacity-25"></i>
                <h5>No Maintenance Requests Found</h5>
                <p class="mb-0 small">No requests match the current filters.</p>
            </div>
            @endif
        </div>
    </div>

</div>
@endsection
