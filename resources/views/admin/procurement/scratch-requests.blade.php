@extends('layouts.app')

@section('title', 'Material Procurement (Created from Scratch) — Global Admin')

@section('content')
<div class="container-fluid px-4 py-3">

    <!-- Page Header & Breadcrumbs -->
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-1 small">
                    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}" class="text-decoration-none text-muted">Dashboard</a></li>
                    <li class="breadcrumb-item text-muted">Global Admin</li>
                    <li class="breadcrumb-item active text-primary fw-semibold" aria-current="page">Scratch Material Procurement</li>
                </ol>
            </nav>
            <div class="d-flex align-items-center gap-2">
                <div class="p-2.5 rounded-3 text-white shadow-sm" style="background: linear-gradient(135deg, #f59e0b, #d97706); width: 44px; height: 44px; display: flex; align-items: center; justify-content: center;">
                    <i class="fa-solid fa-file-circle-question fa-lg"></i>
                </div>
                <div>
                    <div class="d-flex align-items-center gap-2">
                        <h1 class="h3 mb-0 fw-bold" style="color:var(--brand-800, #1e293b)">Material Procurement (Created from Scratch)</h1>
                        <span class="badge bg-warning text-dark rounded-pill px-2.5 py-1" style="font-size: 0.65rem; font-weight: 700; letter-spacing: 0.05em;">GLOBAL ADMIN ONLY</span>
                    </div>
                    <p class="text-muted small mb-0">Filtered view showing strictly manual, emergency, and direct requisitions created from scratch (excluding schedule takeoff &amp; maintenance generated items).</p>
                </div>
            </div>
        </div>
        <div class="d-flex gap-2 align-items-center">
            <a href="{{ route('admin.scratch-material-requests.export', request()->query()) }}" class="btn btn-outline-secondary btn-sm shadow-sm">
                <i class="fa-solid fa-file-csv me-1 text-success"></i> Export CSV
            </a>
            @can('material_requests.create')
            <a href="{{ route('material-requests.create', ['source' => 'Manual Creation']) }}" class="btn btn-warning btn-sm text-dark fw-bold shadow-sm">
                <i class="fa-solid fa-plus me-1"></i> New Scratch Request
            </a>
            @endcan
        </div>
    </div>

    <!-- KPI Summary Cards -->
    <div class="row g-3 mb-4">
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card border-0 shadow-sm rounded-4 h-100 bg-white">
                <div class="card-body p-3">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <span class="text-muted small text-uppercase fw-bold" style="font-size: 11px;">Scratch Material Requests</span>
                            <h3 class="fw-bold mb-0 mt-1 text-dark">{{ number_format($kpi['total_scratch_mr']) }}</h3>
                            <small class="text-muted">Total manual / scratch MRs</small>
                        </div>
                        <div class="rounded-3 p-2.5 bg-amber-50 text-warning" style="background: rgba(245, 158, 11, 0.12);">
                            <i class="fa-solid fa-boxes-packing fa-lg"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card border-0 shadow-sm rounded-4 h-100 bg-white">
                <div class="card-body p-3">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <span class="text-muted small text-uppercase fw-bold" style="font-size: 11px;">Pending MR Planning / Review</span>
                            <h3 class="fw-bold mb-0 mt-1 text-danger">{{ number_format($kpi['pending_scratch_mr']) }}</h3>
                            <small class="text-danger small fw-semibold">Awaiting planning or store manager</small>
                        </div>
                        <div class="rounded-3 p-2.5 bg-danger-subtle text-danger">
                            <i class="fa-solid fa-hourglass-half fa-lg"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card border-0 shadow-sm rounded-4 h-100 bg-white">
                <div class="card-body p-3">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <span class="text-muted small text-uppercase fw-bold" style="font-size: 11px;">Approved &amp; In Action</span>
                            <h3 class="fw-bold mb-0 mt-1 text-success">{{ number_format($kpi['approved_scratch_mr']) }}</h3>
                            <small class="text-success small fw-semibold">Approved / Sent to PR / Fulfilled</small>
                        </div>
                        <div class="rounded-3 p-2.5 bg-success-subtle text-success">
                            <i class="fa-solid fa-check-double fa-lg"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card border-0 shadow-sm rounded-4 h-100 bg-white">
                <div class="card-body p-3">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <span class="text-muted small text-uppercase fw-bold" style="font-size: 11px;">Direct Purchase Requests</span>
                            <h3 class="fw-bold mb-0 mt-1 text-primary">{{ number_format($kpi['total_scratch_pr']) }}</h3>
                            <small class="text-muted">PRs created directly from scratch</small>
                        </div>
                        <div class="rounded-3 p-2.5 bg-primary-subtle text-primary">
                            <i class="fa-solid fa-cart-shopping fa-lg"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filter & Search Bar -->
    <div class="card border-0 shadow-sm rounded-4 mb-4 bg-white">
        <div class="card-body p-3">
            <form action="{{ route('admin.scratch-material-requests.index') }}" method="GET" class="row g-2 align-items-end">
                <input type="hidden" name="tab" value="{{ $activeTab }}">

                <div class="col-md-3">
                    <label class="form-label small text-muted mb-1 fw-bold text-uppercase" style="font-size: 11px;">Search</label>
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-light border-end-0"><i class="fa-solid fa-search text-muted"></i></span>
                        <input type="text" name="search" class="form-control border-start-0" placeholder="Ref #, PR #, item, requester..." value="{{ request('search') }}">
                    </div>
                </div>

                <div class="col-md-3">
                    <label class="form-label small text-muted mb-1 fw-bold text-uppercase" style="font-size: 11px;">Project</label>
                    <select name="project_id" class="form-select form-select-sm">
                        <option value="">All Projects</option>
                        @foreach($projects as $proj)
                            <option value="{{ $proj->id }}" {{ request('project_id') == $proj->id ? 'selected' : '' }}>{{ $proj->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-2">
                    <label class="form-label small text-muted mb-1 fw-bold text-uppercase" style="font-size: 11px;">Status</label>
                    <select name="status" class="form-select form-select-sm">
                        <option value="">All Statuses</option>
                        <option value="pending_planning" {{ request('status') == 'pending_planning' ? 'selected' : '' }}>Pending Planning</option>
                        <option value="planning_approved" {{ request('status') == 'planning_approved' ? 'selected' : '' }}>Planning Approved</option>
                        <option value="sent_to_store_manager" {{ request('status') == 'sent_to_store_manager' ? 'selected' : '' }}>Sent to Store</option>
                        <option value="sent_to_pr" {{ request('status') == 'sent_to_pr' ? 'selected' : '' }}>Sent to PR</option>
                        <option value="transfer_created" {{ request('status') == 'transfer_created' ? 'selected' : '' }}>Transfer Created</option>
                        <option value="fulfilled" {{ request('status') == 'fulfilled' ? 'selected' : '' }}>Fulfilled</option>
                        <option value="approved" {{ request('status') == 'approved' ? 'selected' : '' }}>Approved</option>
                        <option value="rejected" {{ request('status') == 'rejected' ? 'selected' : '' }}>Rejected</option>
                    </select>
                </div>

                <div class="col-md-2">
                    <label class="form-label small text-muted mb-1 fw-bold text-uppercase" style="font-size: 11px;">Required Date</label>
                    <div class="d-flex gap-1">
                        <input type="date" name="date_from" class="form-control form-control-sm" title="From" value="{{ request('date_from') }}">
                        <input type="date" name="date_to" class="form-control form-control-sm" title="To" value="{{ request('date_to') }}">
                    </div>
                </div>

                <div class="col-md-2 d-flex gap-1">
                    <button type="submit" class="btn btn-primary btn-sm flex-grow-1 shadow-sm">
                        <i class="fa-solid fa-filter me-1"></i> Filter
                    </button>
                    <a href="{{ route('admin.scratch-material-requests.index', ['tab' => $activeTab]) }}" class="btn btn-outline-secondary btn-sm" title="Reset Filters">
                        <i class="fa-solid fa-rotate-left"></i>
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Navigation Tabs -->
    <ul class="nav nav-tabs nav-fill mb-3 bg-white p-1 rounded-3 shadow-sm border-0">
        <li class="nav-item">
            <a class="nav-link {{ $activeTab === 'all' ? 'active fw-bold text-warning border-warning' : 'text-muted' }}" 
               href="{{ route('admin.scratch-material-requests.index', array_merge(request()->query(), ['tab' => 'all'])) }}">
                <i class="fa-solid fa-layer-group me-1"></i> All Scratch Requests
                <span class="badge bg-secondary-subtle text-dark ms-1 rounded-pill">{{ $kpi['total_scratch_mr'] + $kpi['total_scratch_pr'] }}</span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link {{ $activeTab === 'material' ? 'active fw-bold text-warning border-warning' : 'text-muted' }}" 
               href="{{ route('admin.scratch-material-requests.index', array_merge(request()->query(), ['tab' => 'material'])) }}">
                <i class="fa-solid fa-boxes-packing me-1 text-warning"></i> Material Requests (MR From Scratch)
                <span class="badge bg-warning text-dark ms-1 rounded-pill">{{ $kpi['total_scratch_mr'] }}</span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link {{ $activeTab === 'purchase' ? 'active fw-bold text-primary border-primary' : 'text-muted' }}" 
               href="{{ route('admin.scratch-material-requests.index', array_merge(request()->query(), ['tab' => 'purchase'])) }}">
                <i class="fa-solid fa-cart-shopping me-1 text-primary"></i> Direct Purchase Requests (PR From Scratch)
                <span class="badge bg-primary-subtle text-primary ms-1 rounded-pill">{{ $kpi['total_scratch_pr'] }}</span>
            </a>
        </li>
    </ul>

    <!-- Tab Content: Material Requests Section -->
    @if($activeTab === 'all' || $activeTab === 'material')
    <div class="card border-0 shadow-sm rounded-4 mb-4 bg-white overflow-hidden">
        <div class="card-header bg-white py-3 px-3 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div class="d-flex align-items-center gap-2">
                <span class="badge bg-warning text-dark p-2 rounded-3"><i class="fa-solid fa-boxes-packing"></i></span>
                <div>
                    <h5 class="mb-0 fw-bold">Scratch Material Requests (MR)</h5>
                    <small class="text-muted">Requests directly initiated by Site Engineers, Store Keepers, or Global Admins</small>
                </div>
            </div>
            <span class="badge bg-light text-dark border px-3 py-1.5 rounded-pill">
                {{ $materialRequests instanceof \Illuminate\Pagination\LengthAwarePaginator ? $materialRequests->total() : $materialRequests->count() }} Records
            </span>
        </div>

        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light text-uppercase small text-muted" style="font-size: 11px;">
                        <tr>
                            <th class="ps-3">Reference #</th>
                            <th>Creation Tag</th>
                            <th>Project</th>
                            <th>Destination Store</th>
                            <th>Requested Items</th>
                            <th>Required Date</th>
                            <th>Status</th>
                            <th>Requester</th>
                            <th>Linked PR</th>
                            <th class="text-end pe-3">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($materialRequests as $req)
                        <tr>
                            <td class="ps-3 fw-bold">
                                <a href="{{ route('material-requests.show', $req) }}" class="text-decoration-none text-primary font-monospace">
                                    {{ $req->reference_number }}
                                </a>
                            </td>
                            <td>
                                @php
                                    $src = $req->source ?? 'Manual Creation';
                                    $srcColor = match(strtolower($src)) {
                                        'emergency' => 'danger',
                                        'manual creation' => 'warning',
                                        default => 'info'
                                    };
                                @endphp
                                <span class="badge bg-{{ $srcColor }}-subtle text-{{ $srcColor }} border border-{{ $srcColor }}-subtle rounded-pill px-2.5">
                                    <i class="fa-solid fa-tag me-1"></i> {{ $src }}
                                </span>
                            </td>
                            <td>
                                @if($req->project)
                                    <a href="{{ route('projects.show', $req->project) }}" class="text-decoration-none fw-semibold text-dark">
                                        {{ $req->project->name }}
                                    </a>
                                @else
                                    <span class="badge bg-light text-muted border">Central / HQ</span>
                                @endif
                            </td>
                            <td>
                                <div class="d-flex align-items-center gap-1.5">
                                    <i class="fa-solid fa-warehouse text-muted small"></i>
                                    <span>{{ $req->store?->name ?? 'General Store' }}</span>
                                </div>
                            </td>
                            <td>
                                @php $itemCount = $req->items->count(); @endphp
                                <div class="d-flex flex-wrap gap-1 align-items-center">
                                    <span class="badge bg-light text-dark border rounded-pill">{{ $itemCount }} {{ Str::plural('item', $itemCount) }}</span>
                                    @foreach($req->items->take(2) as $it)
                                        <span class="badge bg-secondary-subtle text-secondary rounded-pill fw-normal" style="font-size: 0.72rem;">
                                            {{ $it->product?->name ?? 'Item' }} ({{ (float)$it->quantity_requested }} {{ $it->product?->unit ?? '' }})
                                        </span>
                                    @endforeach
                                    @if($itemCount > 2)
                                        <span class="text-muted small" style="font-size: 0.7rem;">+{{ $itemCount - 2 }} more</span>
                                    @endif
                                </div>
                            </td>
                            <td>
                                @if($req->required_date)
                                    <span class="{{ $req->required_date->isPast() && !in_array($req->status, ['fulfilled', 'approved', 'sent_to_pr']) ? 'text-danger fw-bold' : 'text-dark' }}">
                                        {{ $req->required_date->format('M d, Y') }}
                                    </span>
                                @else
                                    <span class="text-muted">-</span>
                                @endif
                            </td>
                            <td>
                                @php
                                    $badge = match($req->status) {
                                        'draft' => 'secondary',
                                        'pending_planning', 'submitted' => 'warning',
                                        'planning_approved' => 'info',
                                        'sent_to_store_manager' => 'primary',
                                        'sent_to_pr', 'transfer_created', 'fulfilled', 'approved' => 'success',
                                        'rejected' => 'danger',
                                        default => 'secondary'
                                    };
                                    $statusText = match($req->status) {
                                        'pending_planning', 'submitted' => 'Pending Planning',
                                        'planning_approved' => 'Planning Approved',
                                        'sent_to_store_manager' => 'Sent to Store',
                                        'sent_to_pr' => 'Sent to PR',
                                        'transfer_created' => 'Transfer Created',
                                        'fulfilled' => 'Fulfilled',
                                        'approved' => 'Approved',
                                        'rejected' => 'Rejected',
                                        default => ucfirst(str_replace('_', ' ', $req->status ?? 'pending'))
                                    };
                                @endphp
                                <span class="badge bg-{{ $badge }}-subtle text-{{ $badge }} border border-{{ $badge }}-subtle rounded-pill px-2.5">
                                    {{ $statusText }}
                                </span>
                            </td>
                            <td>
                                <div class="d-flex align-items-center gap-1.5">
                                    <div class="rounded-circle bg-light text-muted d-flex align-items-center justify-content-center" style="width: 24px; height: 24px; font-size: 0.65rem; font-weight: 700;">
                                        {{ strtoupper(substr($req->creator?->name ?? 'S', 0, 1)) }}
                                    </div>
                                    <span class="small">{{ $req->creator?->name ?? 'Staff' }}</span>
                                </div>
                            </td>
                            <td>
                                @if($req->purchaseRequests && $req->purchaseRequests->isNotEmpty())
                                    @foreach($req->purchaseRequests as $linkedPr)
                                        <a href="{{ route('purchase-requests.show', $linkedPr) }}" class="badge bg-primary text-white text-decoration-none rounded-pill" title="View Linked PR">
                                            {{ $linkedPr->pr_no }}
                                        </a>
                                    @endforeach
                                @else
                                    <span class="text-muted small">None</span>
                                @endif
                            </td>
                            <td class="text-end pe-3">
                                <a href="{{ route('material-requests.show', $req) }}" class="btn btn-sm btn-outline-primary shadow-xs px-2.5">
                                    <i class="fa-solid fa-arrow-up-right-from-square me-1"></i> View
                                </a>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="10" class="text-center py-5 text-muted">
                                <i class="fa-solid fa-boxes-packing fa-3x mb-3 opacity-25"></i>
                                <p class="mb-0 fw-semibold">No scratch material requests found matching your filter criteria.</p>
                                <small class="text-muted">Requests will appear here when created manually or marked as emergency/from scratch.</small>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        @if($materialRequests instanceof \Illuminate\Pagination\LengthAwarePaginator && $materialRequests->hasPages())
        <div class="card-footer bg-white border-top py-2 px-3">
            {{ $materialRequests->appends(array_merge(request()->query(), ['tab' => $activeTab]))->links() }}
        </div>
        @endif
    </div>
    @endif

    <!-- Tab Content: Purchase Requests Section -->
    @if($activeTab === 'all' || $activeTab === 'purchase')
    <div class="card border-0 shadow-sm rounded-4 mb-4 bg-white overflow-hidden">
        <div class="card-header bg-white py-3 px-3 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div class="d-flex align-items-center gap-2">
                <span class="badge bg-primary text-white p-2 rounded-3"><i class="fa-solid fa-cart-shopping"></i></span>
                <div>
                    <h5 class="mb-0 fw-bold">Direct Purchase Requests (PR from Scratch)</h5>
                    <small class="text-muted">Procurement purchase requisitions initiated directly without a prior material request</small>
                </div>
            </div>
            <span class="badge bg-light text-dark border px-3 py-1.5 rounded-pill">
                {{ $purchaseRequests instanceof \Illuminate\Pagination\LengthAwarePaginator ? $purchaseRequests->total() : $purchaseRequests->count() }} Records
            </span>
        </div>

        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light text-uppercase small text-muted" style="font-size: 11px;">
                        <tr>
                            <th class="ps-3">PR Number</th>
                            <th>Project</th>
                            <th>Store</th>
                            <th>Priority / Type</th>
                            <th>Items Preview</th>
                            <th>Required Date</th>
                            <th>Status</th>
                            <th>Requester</th>
                            <th class="text-end pe-3">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($purchaseRequests as $pr)
                        <tr>
                            <td class="ps-3 fw-bold">
                                <a href="{{ route('purchase-requests.show', $pr) }}" class="text-decoration-none text-primary font-monospace">
                                    {{ $pr->pr_no }}
                                </a>
                            </td>
                            <td>
                                @if($pr->project)
                                    <a href="{{ route('projects.show', $pr->project) }}" class="text-decoration-none fw-semibold text-dark">
                                        {{ $pr->project->name }}
                                    </a>
                                @else
                                    <span class="badge bg-light text-muted border">Central / HQ</span>
                                @endif
                            </td>
                            <td>
                                <div class="d-flex align-items-center gap-1.5">
                                    <i class="fa-solid fa-warehouse text-muted small"></i>
                                    <span>{{ $pr->store?->name ?? 'General Store' }}</span>
                                </div>
                            </td>
                            <td>
                                @php
                                    $prioClass = match(strtolower($pr->priority ?? 'medium')) {
                                        'urgent', 'critical' => 'danger',
                                        'high' => 'warning',
                                        default => 'info'
                                    };
                                @endphp
                                <span class="badge bg-{{ $prioClass }}-subtle text-{{ $prioClass }} border border-{{ $prioClass }}-subtle rounded-pill px-2.5">
                                    {{ ucfirst($pr->priority ?? 'Medium') }}
                                </span>
                            </td>
                            <td>
                                @php
                                    $directItems = $pr->items;
                                    $linkedTr = $pr->matched_transfers ?? $pr->linked_transfers ?? collect();
                                    $transferredItems = $linkedTr->flatMap->items;
                                    $totalItemCount = $directItems->count() + $transferredItems->count();
                                    $lastLog = $pr->workflowLogs ? $pr->workflowLogs->last() : null;
                                @endphp

                                @if($transferredItems->isNotEmpty())
                                    <div class="d-flex flex-column gap-1">
                                        <div class="d-flex flex-wrap gap-1 align-items-center">
                                            <span class="badge bg-info-subtle text-primary border border-info-subtle rounded-pill" style="font-size: 0.72rem;">
                                                <i class="fa-solid fa-truck-moving me-1"></i> {{ $transferredItems->count() }} {{ Str::plural('item', $transferredItems->count()) }} (Transferred)
                                            </span>
                                            @foreach($linkedTr->take(1) as $tr)
                                                <a href="{{ route('store-manager.transfers.show', $tr) }}" class="badge bg-warning text-dark text-decoration-none rounded-pill" style="font-size: 0.68rem;" title="View Store Transfer #{{ $tr->transfer_no }}">
                                                    <i class="fa-solid fa-arrow-up-right-from-square me-0.5"></i> #{{ $tr->transfer_no }}
                                                </a>
                                            @endforeach
                                        </div>
                                        <div class="d-flex flex-wrap gap-1 align-items-center">
                                            @foreach($transferredItems->take(2) as $ti)
                                                <span class="badge bg-light text-dark border rounded-pill fw-normal" style="font-size: 0.72rem;">
                                                    {{ $ti->product?->name ?? 'Material' }} ({{ (float)($ti->requested_quantity ?? $ti->approved_quantity ?? 0) }} {{ $ti->unit ?? 'pcs' }})
                                                </span>
                                            @endforeach
                                            @if($transferredItems->count() > 2)
                                                <span class="text-muted small" style="font-size: 0.7rem;">+{{ $transferredItems->count() - 2 }} more</span>
                                            @endif
                                            <button type="button" class="btn btn-link btn-sm p-0 text-decoration-none text-primary fw-semibold" style="font-size: 0.72rem;" data-bs-toggle="modal" data-bs-target="#prItemDetailModal{{ $pr->id }}">
                                                [Details]
                                            </button>
                                        </div>
                                    </div>
                                @elseif($directItems->isNotEmpty())
                                    <div class="d-flex flex-wrap gap-1 align-items-center">
                                        <span class="badge bg-light text-dark border rounded-pill">{{ $directItems->count() }} {{ Str::plural('item', $directItems->count()) }}</span>
                                        @foreach($directItems->take(2) as $it)
                                            <span class="badge bg-secondary-subtle text-secondary rounded-pill fw-normal" style="font-size: 0.72rem;">
                                                {{ $it->product?->name ?? 'Item' }} ({{ (float)$it->quantity }} {{ $it->unit ?? '' }})
                                            </span>
                                        @endforeach
                                        @if($directItems->count() > 2)
                                            <span class="text-muted small" style="font-size: 0.7rem;">+{{ $directItems->count() - 2 }} more</span>
                                        @endif
                                    </div>
                                @else
                                    <div class="d-flex flex-column gap-1">
                                        <div class="d-flex align-items-center gap-1.5">
                                            <span class="badge bg-secondary-subtle text-muted border rounded-pill" style="font-size: 0.72rem;">0 Items in Queue</span>
                                            <button type="button" class="btn btn-xs btn-outline-info rounded-pill py-0 px-2" style="font-size: 0.68rem;" data-bs-toggle="modal" data-bs-target="#prItemDetailModal{{ $pr->id }}">
                                                <i class="fa-solid fa-eye me-0.5"></i> Inspect
                                            </button>
                                        </div>
                                        @if(!empty($pr->justification))
                                            <div class="text-muted small text-truncate" style="max-width: 200px; font-size: 11px;" title="{{ $pr->justification }}">
                                                {{ $pr->justification }}
                                            </div>
                                        @elseif($lastLog && !empty($lastLog->notes))
                                            <div class="text-muted small text-truncate" style="max-width: 200px; font-size: 11px;" title="{{ $lastLog->notes }}">
                                                {{ $lastLog->notes }}
                                            </div>
                                        @endif
                                    </div>
                                @endif
                            </td>
                            <td>
                                @if($pr->required_date)
                                    <span class="{{ $pr->required_date->isPast() && !in_array($pr->status, ['completed', 'intake_complete']) ? 'text-danger fw-bold' : 'text-dark' }}">
                                        {{ $pr->required_date->format('M d, Y') }}
                                    </span>
                                @else
                                    <span class="text-muted">-</span>
                                @endif
                            </td>
                            <td>
                                @php
                                    $prBadge = \App\Models\PurchaseRequest::statusBadgeClass($pr->status);
                                @endphp
                                <span class="badge bg-{{ $prBadge }}-subtle text-{{ $prBadge }} border border-{{ $prBadge }}-subtle rounded-pill px-2.5">
                                    {{ $pr->status_label }}
                                </span>
                            </td>
                            <td>
                                <div class="d-flex align-items-center gap-1.5">
                                    <div class="rounded-circle bg-light text-muted d-flex align-items-center justify-content-center" style="width: 24px; height: 24px; font-size: 0.65rem; font-weight: 700;">
                                        {{ strtoupper(substr($pr->requestedBy?->name ?? 'S', 0, 1)) }}
                                    </div>
                                    <span class="small">{{ $pr->requestedBy?->name ?? 'Staff' }}</span>
                                </div>
                            </td>
                            <td class="text-end pe-3">
                                <div class="d-flex gap-1 justify-content-end align-items-center">
                                    <button type="button" class="btn btn-sm btn-outline-info shadow-xs px-2" title="Inspect Full Item Breakdown & Transfer Slips" data-bs-toggle="modal" data-bs-target="#prItemDetailModal{{ $pr->id }}">
                                        <i class="fa-solid fa-circle-info"></i>
                                    </button>
                                    <a href="{{ route('purchase-requests.show', $pr) }}" class="btn btn-sm btn-outline-primary shadow-xs px-2.5">
                                        <i class="fa-solid fa-arrow-up-right-from-square me-1"></i> View PR
                                    </a>
                                </div>

                                <!-- PR Item Detail Modal -->
                                <div class="modal fade text-start" id="prItemDetailModal{{ $pr->id }}" tabindex="-1" aria-labelledby="prModalLabel{{ $pr->id }}" aria-hidden="true">
                                    <div class="modal-dialog modal-lg modal-dialog-centered">
                                        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
                                            <div class="modal-header bg-light border-bottom py-3">
                                                <div class="d-flex align-items-center gap-2">
                                                    <span class="badge bg-primary p-2 rounded-3 text-white"><i class="fa-solid fa-cart-shopping"></i></span>
                                                    <div>
                                                        <h5 class="modal-title fw-bold text-dark mb-0" id="prModalLabel{{ $pr->id }}">
                                                            Requisition Details &bull; {{ $pr->pr_no }}
                                                        </h5>
                                                        <small class="text-muted">{{ $pr->project?->name ?? 'Central HQ' }} &bull; Store: {{ $pr->store?->name ?? 'General Store' }}</small>
                                                    </div>
                                                </div>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                            </div>
                                            <div class="modal-body p-4">
                                                <!-- Info Grid -->
                                                <div class="row g-3 mb-4 bg-light rounded-3 p-3 border">
                                                    <div class="col-sm-4">
                                                        <small class="text-muted text-uppercase fw-bold" style="font-size: 11px;">Status</small>
                                                        <div><span class="badge bg-{{ $prBadge }}-subtle text-{{ $prBadge }} border border-{{ $prBadge }}-subtle rounded-pill px-2.5">{{ $pr->status_label }}</span></div>
                                                    </div>
                                                    <div class="col-sm-4">
                                                        <small class="text-muted text-uppercase fw-bold" style="font-size: 11px;">Priority</small>
                                                        <div class="fw-semibold text-dark">{{ ucfirst($pr->priority ?? 'Medium') }}</div>
                                                    </div>
                                                    <div class="col-sm-4">
                                                        <small class="text-muted text-uppercase fw-bold" style="font-size: 11px;">Requester</small>
                                                        <div class="fw-semibold text-dark">{{ $pr->requestedBy?->name ?? 'Staff' }}</div>
                                                    </div>
                                                    <div class="col-12">
                                                        <small class="text-muted text-uppercase fw-bold" style="font-size: 11px;">Justification / Request Notes</small>
                                                        <div class="text-dark bg-white p-2 rounded border small">{{ $pr->justification ?: 'No specific justification recorded.' }}</div>
                                                    </div>
                                                </div>

                                                <!-- Transferred Items Section -->
                                                @if($transferredItems->isNotEmpty())
                                                    <div class="alert alert-info border-info-subtle d-flex align-items-center gap-2 py-2 px-3 mb-3">
                                                        <i class="fa-solid fa-truck-moving fa-lg text-primary"></i>
                                                        <div>
                                                            <strong class="d-block">Fulfilled via Store Transfer</strong>
                                                            <span class="small">This purchase request items were transferred from another store rather than purchased externally.</span>
                                                        </div>
                                                    </div>

                                                    <h6 class="fw-bold mb-2 text-dark"><i class="fa-solid fa-boxes-packing me-1 text-primary"></i>Transferred Items Breakdown</h6>
                                                    <div class="table-responsive mb-4">
                                                        <table class="table table-sm table-bordered align-middle mb-0 bg-white">
                                                            <thead class="table-light text-uppercase small text-muted" style="font-size: 11px;">
                                                                <tr>
                                                                    <th>Material / Product</th>
                                                                    <th class="text-center">Transferred Qty</th>
                                                                    <th>From Store</th>
                                                                    <th>To Store</th>
                                                                    <th>Transfer Slip #</th>
                                                                    <th>Status</th>
                                                                </tr>
                                                            </thead>
                                                            <tbody>
                                                                @foreach($linkedTr as $tr)
                                                                    @foreach($tr->items as $ti)
                                                                    <tr>
                                                                        <td class="fw-bold">{{ $ti->product?->name ?? ('Item #' . $ti->product_id) }}</td>
                                                                        <td class="text-center fw-bold">{{ (float)($ti->requested_quantity ?? $ti->approved_quantity ?? 0) }} {{ $ti->unit ?? ($ti->product?->unit ?? 'pcs') }}</td>
                                                                        <td>{{ $tr->fromStore?->name ?? 'Source Store' }}</td>
                                                                        <td>{{ $tr->toStore?->name ?? 'Destination Store' }}</td>
                                                                        <td>
                                                                            <a href="{{ route('store-manager.transfers.show', $tr) }}" class="badge bg-warning text-dark text-decoration-none">
                                                                                #{{ $tr->transfer_no }}
                                                                            </a>
                                                                        </td>
                                                                        <td><span class="badge bg-success-subtle text-success">{{ ucfirst($tr->status) }}</span></td>
                                                                    </tr>
                                                                    @endforeach
                                                                @endforeach
                                                            </tbody>
                                                        </table>
                                                    </div>
                                                @endif

                                                <!-- Direct Items Section -->
                                                @if($directItems->isNotEmpty())
                                                    <h6 class="fw-bold mb-2 text-dark"><i class="fa-solid fa-boxes-stacked me-1 text-primary"></i>Direct Items in PR Queue</h6>
                                                    <div class="table-responsive mb-4">
                                                        <table class="table table-sm table-bordered align-middle mb-0 bg-white">
                                                            <thead class="table-light text-uppercase small text-muted" style="font-size: 11px;">
                                                                <tr>
                                                                    <th>Material / Product</th>
                                                                    <th class="text-center">Requested Qty</th>
                                                                    <th class="text-center">Purchased Qty</th>
                                                                    <th class="text-center">Received Qty</th>
                                                                    <th>Unit</th>
                                                                    <th>Est. Unit Cost</th>
                                                                </tr>
                                                            </thead>
                                                            <tbody>
                                                                @foreach($directItems as $it)
                                                                <tr>
                                                                    <td class="fw-bold">{{ $it->product?->name ?? ('Item #' . $it->product_id) }}</td>
                                                                    <td class="text-center fw-bold">{{ (float)$it->quantity }}</td>
                                                                    <td class="text-center">{{ (float)($it->purchased_quantity ?? 0) }}</td>
                                                                    <td class="text-center">{{ (float)($it->received_quantity ?? 0) }}</td>
                                                                    <td>{{ $it->unit ?? ($it->product?->unit ?? 'pcs') }}</td>
                                                                    <td>{{ number_format($it->estimated_unit_cost ?? 0, 2) }} ETB</td>
                                                                </tr>
                                                                @endforeach
                                                            </tbody>
                                                        </table>
                                                    </div>
                                                @endif

                                                @if($totalItemCount === 0)
                                                    <div class="alert alert-warning py-3 px-3 mb-3">
                                                        <i class="fa-solid fa-circle-exclamation me-1"></i>
                                                        <strong>No active material items currently attached in database.</strong>
                                                        <p class="small mb-0 mt-1">Check the workflow history below to see all actions, approvals, and previous notes taken on this request.</p>
                                                    </div>
                                                @endif

                                                <!-- Workflow & History Timeline -->
                                                @if($pr->workflowLogs && $pr->workflowLogs->isNotEmpty())
                                                    <h6 class="fw-bold mb-2 text-dark"><i class="fa-solid fa-clock-rotate-left me-1 text-secondary"></i>Workflow &amp; Audit Trail</h6>
                                                    <div class="list-group list-group-flush border rounded-3 p-2 bg-light small" style="max-height: 220px; overflow-y: auto;">
                                                        @foreach($pr->workflowLogs as $log)
                                                        <div class="list-group-item bg-transparent border-bottom py-2">
                                                            <div class="d-flex justify-content-between align-items-center">
                                                                <span class="fw-bold text-dark">{{ ucfirst(str_replace('_', ' ', $log->action)) }}</span>
                                                                <small class="text-muted">{{ optional($log->created_at)->format('M d, Y H:i') }}</small>
                                                            </div>
                                                            <div class="text-muted" style="font-size: 11px;">
                                                                Actor: <strong>{{ $log->actor?->name ?? ($log->actor_role ?? 'System') }}</strong>
                                                                @if(!empty($log->from_stage) || !empty($log->to_stage))
                                                                    &bull; <span>{{ ucfirst(str_replace('_', ' ', $log->from_stage)) }} &rarr; {{ ucfirst(str_replace('_', ' ', $log->to_stage)) }}</span>
                                                                @endif
                                                            </div>
                                                            @if(!empty($log->notes))
                                                                <div class="mt-1 text-dark bg-white p-1.5 rounded border" style="font-size: 11.5px;">
                                                                    <i class="fa-solid fa-message text-muted me-1"></i> {{ $log->notes }}
                                                                </div>
                                                            @endif
                                                        </div>
                                                        @endforeach
                                                    </div>
                                                @endif
                                            </div>
                                            <div class="modal-footer bg-light border-top py-2 px-3">
                                                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
                                                <a href="{{ route('purchase-requests.show', $pr) }}" class="btn btn-primary btn-sm">
                                                    <i class="fa-solid fa-arrow-up-right-from-square me-1"></i> Open PR Workspace
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="9" class="text-center py-5 text-muted">
                                <i class="fa-solid fa-cart-shopping fa-3x mb-3 opacity-25"></i>
                                <p class="mb-0 fw-semibold">No direct scratch purchase requests found matching your filter criteria.</p>
                                <small class="text-muted">Purchase requests created directly from scratch without a prior MR will appear here.</small>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        @if($purchaseRequests instanceof \Illuminate\Pagination\LengthAwarePaginator && $purchaseRequests->hasPages())
        <div class="card-footer bg-white border-top py-2 px-3">
            {{ $purchaseRequests->appends(array_merge(request()->query(), ['tab' => $activeTab]))->links() }}
        </div>
        @endif
    </div>
    @endif

</div>
@endsection
