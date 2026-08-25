@extends('layouts.app')
@section('title', 'General Service — Maintenance Requests')
@section('content')
<div class="container-fluid">

    {{-- Page Header --}}
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-1 fw-bold"><i class="fa-solid fa-screwdriver-wrench me-2 text-warning"></i>General Service — Maintenance</h1>
            <p class="text-muted mb-0 small">Asset maintenance requests reported by employees</p>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm rounded-3">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    {{-- Stats Cards --}}
    <div class="row g-3 mb-4">
        <div class="col-xl-3 col-md-6">
            <div class="card border-0 shadow-sm rounded-4 h-100" style="border-left: 4px solid #f59e0b !important;">
                <div class="card-body d-flex align-items-center gap-3 py-3">
                    <div class="rounded-circle d-flex align-items-center justify-content-center flex-shrink-0"
                         style="width:48px;height:48px;background:#fef3c7;">
                        <i class="fa-solid fa-clock text-warning fa-lg"></i>
                    </div>
                    <div>
                        <div class="text-muted small text-uppercase fw-semibold">Pending</div>
                        <div class="fs-4 fw-bold text-dark">{{ $stats['pending'] }}</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card border-0 shadow-sm rounded-4 h-100" style="border-left: 4px solid #3b82f6 !important;">
                <div class="card-body d-flex align-items-center gap-3 py-3">
                    <div class="rounded-circle d-flex align-items-center justify-content-center flex-shrink-0"
                         style="width:48px;height:48px;background:#dbeafe;">
                        <i class="fa-solid fa-wrench text-primary fa-lg"></i>
                    </div>
                    <div>
                        <div class="text-muted small text-uppercase fw-semibold">In Progress</div>
                        <div class="fs-4 fw-bold text-dark">{{ $stats['in_progress'] }}</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card border-0 shadow-sm rounded-4 h-100" style="border-left: 4px solid #10b981 !important;">
                <div class="card-body d-flex align-items-center gap-3 py-3">
                    <div class="rounded-circle d-flex align-items-center justify-content-center flex-shrink-0"
                         style="width:48px;height:48px;background:#d1fae5;">
                        <i class="fa-solid fa-circle-check text-success fa-lg"></i>
                    </div>
                    <div>
                        <div class="text-muted small text-uppercase fw-semibold">Resolved Today</div>
                        <div class="fs-4 fw-bold text-dark">{{ $stats['resolved'] }}</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card border-0 shadow-sm rounded-4 h-100" style="border-left: 4px solid #6366f1 !important;">
                <div class="card-body d-flex align-items-center gap-3 py-3">
                    <div class="rounded-circle d-flex align-items-center justify-content-center flex-shrink-0"
                         style="width:48px;height:48px;background:#e0e7ff;">
                        <i class="fa-solid fa-list-check" style="color:#6366f1; font-size:1.3rem;"></i>
                    </div>
                    <div>
                        <div class="text-muted small text-uppercase fw-semibold">Total</div>
                        <div class="fs-4 fw-bold text-dark">{{ $stats['total'] }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Filter Bar --}}
    <div class="card border-0 shadow-sm rounded-4 mb-4">
        <div class="card-body py-3 px-4">
            <form method="GET" class="row g-2 align-items-end">
                <div class="col-md-4">
                    <label class="form-label small fw-semibold mb-1">Search</label>
                    <input type="text" name="search" class="form-control form-control-sm rounded-3"
                           placeholder="Request no., asset name, employee..." value="{{ request('search') }}">
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
                    @if(request()->hasAny(['search','status','urgency']))
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
                            <th class="py-3">Issue Type</th>
                            <th class="py-3">Urgency</th>
                            <th class="py-3">Status</th>
                            <th class="py-3">Submitted</th>
                            <th class="py-3 pe-4 text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($requests as $req)
                        @php $sb = $req->status_badge; $ub = $req->urgency_badge; @endphp
                        <tr class="{{ $req->urgency === 'critical' ? 'table-danger' : ($req->urgency === 'urgent' ? 'table-warning' : '') }}">
                            <td class="ps-4 py-3">
                                <span class="font-monospace fw-semibold text-primary">{{ $req->request_no }}</span>
                                @if($req->expenseRequests->count() > 0)
                                    <span class="badge bg-success bg-opacity-10 text-success border border-success ms-1" style="font-size:0.68rem;" title="{{ $req->expenseRequests->count() }} expense request(s)">
                                        <i class="fa-solid fa-hand-holding-dollar"></i> {{ $req->expenseRequests->count() }}
                                    </span>
                                @endif
                                @if($req->materialRequests->count() > 0)
                                    <span class="badge bg-primary bg-opacity-10 text-primary border border-primary ms-1" style="font-size:0.68rem;" title="{{ $req->materialRequests->count() }} material request(s)">
                                        <i class="fa-solid fa-boxes-stacked"></i> {{ $req->materialRequests->count() }}
                                    </span>
                                @endif
                            </td>
                            <td class="py-3">
                                <div class="fw-semibold text-dark">{{ $req->employee->full_name ?? 'N/A' }}</div>
                                <div class="text-muted small">{{ $req->employee->employee_code ?? '' }}</div>
                            </td>
                            <td class="py-3">
                                <div class="fw-semibold">{{ $req->asset_name }}</div>
                                @if($req->asset_code)
                                    <span class="badge bg-dark font-monospace" style="font-size:0.68rem;">{{ $req->asset_code }}</span>
                                @endif
                            </td>
                            <td class="py-3">{{ $req->issue_type_label }}</td>
                            <td class="py-3">
                                <span class="badge {{ $ub['class'] }} rounded-pill">{{ $ub['label'] }}</span>
                            </td>
                            <td class="py-3">
                                <span class="badge {{ $sb['class'] }} rounded-pill">
                                    <i class="fa-solid {{ $sb['icon'] }} me-1"></i>{{ $sb['label'] }}
                                </span>
                            </td>
                            <td class="py-3 text-muted small">{{ $req->created_at->format('d M Y') }}</td>
                            <td class="py-3 pe-4 text-center">
                                <a href="{{ route('general-service.maintenance.show', $req) }}"
                                   class="btn btn-xs btn-outline-primary rounded-pill px-3">
                                    <i class="fa-solid fa-eye me-1"></i>Manage
                                </a>
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
