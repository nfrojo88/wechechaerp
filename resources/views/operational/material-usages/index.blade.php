@extends('layouts.app')

@section('title', 'Daily Material Consumption (ዕለታዊ የዕቃዎች ፍጆታ)')

@section('content')
<div class="container-fluid py-3">

    {{-- Page Header --}}
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
        <div>
            <h1 class="h3 fw-bold text-dark mb-1">
                <i class="fa-solid fa-boxes-packing text-primary me-2"></i>Daily Material Consumption (ዕለታዊ ፍጆታ)
            </h1>
            <p class="text-muted small mb-0">
                Track, log, and monitor materials and inventory consumed daily on site and project stores.
            </p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('material-usages.create') }}" class="btn btn-success shadow-sm fw-bold px-3">
                <i class="fa-solid fa-plus-circle me-1"></i> Log Daily Consumption (ዕለታዊ ፍጆታ መዝግብ)
            </a>
        </div>
    </div>

    {{-- Flash Messages --}}
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm" role="alert">
            <i class="fa-solid fa-circle-check me-2"></i>{{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm" role="alert">
            <i class="fa-solid fa-triangle-exclamation me-2"></i>{{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    {{-- KPI Summary Cards --}}
    <div class="row g-3 mb-4">
        <div class="col-sm-6 col-xl-3">
            <div class="card border-0 shadow-sm rounded-4 h-100 border-start border-4 border-primary">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <span class="text-muted small text-uppercase fw-semibold">Today's Logs</span>
                            <h3 class="fw-bold text-dark mb-0 mt-1">{{ $kpi['today_count'] ?? 0 }}</h3>
                            <small class="text-primary fw-semibold"><i class="fa-solid fa-calendar-day me-1"></i>{{ date('M d, Y') }}</small>
                        </div>
                        <div class="rounded-circle bg-primary bg-opacity-10 p-3 text-primary">
                            <i class="fa-solid fa-calendar-check fa-xl"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-sm-6 col-xl-3">
            <div class="card border-0 shadow-sm rounded-4 h-100 border-start border-4 border-info">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <span class="text-muted small text-uppercase fw-semibold">This Month Logs</span>
                            <h3 class="fw-bold text-dark mb-0 mt-1">{{ $kpi['month_count'] ?? 0 }}</h3>
                            <small class="text-info fw-semibold"><i class="fa-solid fa-chart-line me-1"></i>Current Month</small>
                        </div>
                        <div class="rounded-circle bg-info bg-opacity-10 p-3 text-info">
                            <i class="fa-solid fa-boxes-stacked fa-xl"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-sm-6 col-xl-3">
            <div class="card border-0 shadow-sm rounded-4 h-100 border-start border-4 border-success">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <span class="text-muted small text-uppercase fw-semibold">Confirmed &amp; Deducted</span>
                            <h3 class="fw-bold text-success mb-0 mt-1">{{ $kpi['confirmed_count'] ?? 0 }}</h3>
                            <small class="text-success fw-semibold"><i class="fa-solid fa-check-double me-1"></i>Inventory Updated</small>
                        </div>
                        <div class="rounded-circle bg-success bg-opacity-10 p-3 text-success">
                            <i class="fa-solid fa-warehouse fa-xl"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-sm-6 col-xl-3">
            <div class="card border-0 shadow-sm rounded-4 h-100 border-start border-4 border-warning">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <span class="text-muted small text-uppercase fw-semibold">Draft Entries</span>
                            <h3 class="fw-bold text-warning mb-0 mt-1">{{ $kpi['draft_count'] ?? 0 }}</h3>
                            <small class="text-muted fw-semibold">Awaiting Final Confirmation</small>
                        </div>
                        <div class="rounded-circle bg-warning bg-opacity-10 p-3 text-warning">
                            <i class="fa-solid fa-file-pen fa-xl"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Main Filter & Data Table Card --}}
    <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
        {{-- Filter Header --}}
        <div class="card-header bg-white border-bottom p-3">
            <form method="GET" action="{{ route('material-usages.index') }}" class="row g-2 align-items-center">
                <div class="col-md-3">
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-light"><i class="fa-solid fa-magnifying-glass text-muted"></i></span>
                        <input type="text" name="search" class="form-control" placeholder="Search log #, receiver, activity..." value="{{ request('search') }}">
                    </div>
                </div>

                <div class="col-md-2">
                    <select name="store_id" class="form-select form-select-sm">
                        <option value="">-- All Stores --</option>
                        @foreach($stores as $st)
                            <option value="{{ $st->id }}" {{ request('store_id', ($isStoreKeeper ? $userStoreId : '')) == $st->id ? 'selected' : '' }}>
                                🏪 {{ $st->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-2">
                    <select name="project_id" class="form-select form-select-sm">
                        <option value="">-- All Projects --</option>
                        @foreach($projects as $pj)
                            <option value="{{ $pj->id }}" {{ request('project_id') == $pj->id ? 'selected' : '' }}>
                                🏗️ {{ $pj->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-2">
                    <input type="date" name="date_from" class="form-control form-control-sm" title="From Date" value="{{ request('date_from') }}">
                </div>

                <div class="col-md-2">
                    <input type="date" name="date_to" class="form-control form-control-sm" title="To Date" value="{{ request('date_to') }}">
                </div>

                <div class="col-md-1 d-flex gap-1">
                    <button type="submit" class="btn btn-sm btn-primary w-100 fw-semibold"><i class="fa-solid fa-filter"></i></button>
                    @if(request()->anyFilled(['search', 'store_id', 'project_id', 'date_from', 'date_to', 'status']))
                        <a href="{{ route('material-usages.index') }}" class="btn btn-sm btn-outline-danger" title="Clear Filters"><i class="fa-solid fa-xmark"></i></a>
                    @endif
                </div>
            </form>
        </div>

        {{-- Table Content --}}
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light text-secondary small text-uppercase">
                        <tr>
                            <th class="ps-3">Log / Slip #</th>
                            <th>Date</th>
                            <th>Store &amp; Location</th>
                            <th>Project Site</th>
                            <th>Consumed By / Activity</th>
                            <th class="text-center">Items Consumed</th>
                            <th>Status</th>
                            <th>Recorded By</th>
                            <th class="pe-3 text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($usages as $u)
                        <tr>
                            <td class="ps-3 fw-bold">
                                <a href="{{ route('material-usages.show', $u) }}" class="text-primary text-decoration-none font-monospace">
                                    {{ $u->usage_no }}
                                </a>
                                @if($u->slip_number)
                                    <span class="badge bg-light text-dark border ms-1" style="font-size:0.7rem;">Ref: {{ $u->slip_number }}</span>
                                @endif
                            </td>
                            <td class="small fw-semibold text-dark">
                                <i class="fa-regular fa-calendar text-muted me-1"></i>{{ optional($u->usage_date)->format('M d, Y') }}
                            </td>
                            <td>
                                <span class="badge bg-info bg-opacity-10 text-info border border-info border-opacity-25 px-2 py-1">
                                    <i class="fa-solid fa-warehouse me-1"></i>{{ $u->store->name ?? 'Store' }}
                                </span>
                            </td>
                            <td>
                                <span class="fw-semibold text-dark">
                                    <i class="fa-solid fa-building me-1 text-secondary"></i>{{ $u->project->name ?? 'Project' }}
                                </span>
                            </td>
                            <td>
                                @if($u->consumed_by_name)
                                    <div class="fw-bold text-dark small"><i class="fa-solid fa-user-tag text-success me-1"></i>{{ $u->consumed_by_name }}</div>
                                @endif
                                @if($u->activity_type)
                                    <div class="text-muted small text-truncate" style="max-width: 180px;" title="{{ $u->activity_type }}">
                                        <i class="fa-solid fa-trowel-bricks text-warning me-1"></i>{{ $u->activity_type }}
                                    </div>
                                @elseif($u->description)
                                    <div class="text-muted small text-truncate" style="max-width: 180px;" title="{{ $u->description }}">
                                        {{ $u->description }}
                                    </div>
                                @else
                                    <span class="text-muted small">Daily Site Work</span>
                                @endif
                            </td>
                            <td class="text-center">
                                <span class="badge bg-secondary rounded-pill px-2 py-1" title="{{ $u->items->pluck('product.name')->filter()->join(', ') }}">
                                    <i class="fa-solid fa-cubes me-1"></i>{{ $u->items->count() }} {{ Str::plural('item', $u->items->count()) }}
                                </span>
                            </td>
                            <td>
                                @if($u->status === 'confirmed')
                                    <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1">
                                        <i class="fa-solid fa-circle-check me-1"></i>Confirmed
                                    </span>
                                @else
                                    <span class="badge bg-warning-subtle text-warning border border-warning-subtle px-2 py-1">
                                        <i class="fa-solid fa-hourglass-half me-1"></i>Draft
                                    </span>
                                @endif
                            </td>
                            <td class="small text-muted">
                                <i class="fa-solid fa-user-circle me-1"></i>{{ $u->createdBy->name ?? 'Staff' }}
                            </td>
                            <td class="pe-3 text-end">
                                <div class="btn-group btn-group-sm">
                                    <a href="{{ route('material-usages.show', $u) }}" class="btn btn-outline-primary" title="View Consumption Details">
                                        <i class="fa-solid fa-eye"></i>
                                    </a>
                                    <a href="{{ route('material-usages.print', $u) }}" target="_blank" class="btn btn-outline-secondary" title="Print Consumption Slip (SIV)">
                                        <i class="fa-solid fa-print"></i>
                                    </a>
                                    @if($u->status === 'draft')
                                        <form action="{{ route('material-usages.confirm', $u) }}" method="POST" class="d-inline" onsubmit="return confirm('Confirm this daily consumption log and deduct store inventory stock?')">
                                            @csrf
                                            <button type="submit" class="btn btn-success" title="Confirm &amp; Deduct Stock">
                                                <i class="fa-solid fa-check"></i> Confirm
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="9" class="text-center py-5 text-muted">
                                <i class="fa-solid fa-boxes-packing fa-3x mb-3 text-secondary opacity-50"></i>
                                <h6>No daily material consumption logs found.</h6>
                                <p class="small mb-3">Record materials used daily on projects from your store inventory.</p>
                                <a href="{{ route('material-usages.create') }}" class="btn btn-sm btn-success fw-bold px-3">
                                    <i class="fa-solid fa-plus-circle me-1"></i> Log Daily Consumption
                                </a>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($usages->hasPages())
                <div class="p-3 border-top">
                    {{ $usages->links() }}
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
