@extends('layouts.app')

@section('title', 'Material Transfers - ' . ($isStoreKeeper ? 'Site Store' : 'Store Hub'))

@section('content')
<div class="container-fluid px-4 py-3">

    {{-- Header --}}
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h4 class="fw-bold mb-1" style="color:var(--brand-800)">
                <i class="fas fa-truck-moving me-2 text-primary"></i>{{ $isStoreKeeper ? 'Store Keeper - Material Transfers' : 'Inter-Store Material Transfers' }}
            </h4>
            <p class="text-muted small mb-0">
                {{ $isStoreKeeper ? 'Manage material dispatch and receiving with driver waybill slips for ' . ($assignedStore->name ?? 'your store') : 'Track and control inter-site material dispatches, driver logistics, and verified stock receipts' }}
            </p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('store-manager.transfers.create') }}" class="btn btn-primary btn-sm shadow-sm">
                <i class="fas fa-plus me-1"></i>New Transfer Request
            </a>
            @if($isStoreKeeper)
            <a href="{{ route('dashboard.store-keeper') }}" class="btn btn-outline-secondary btn-sm">
                <i class="fa-solid fa-arrow-left me-1"></i>Store Dashboard
            </a>
            @endif
        </div>
    </div>

    {{-- KPI Summary Cards --}}
    <div class="row g-3 mb-4">
        @if($isStoreKeeper)
            {{-- Storekeeper Specific KPIs: Strict Incoming vs Outgoing for Assigned Store --}}
            <div class="col-md-6 col-lg-3">
                <a href="{{ route('store-manager.transfers.index', ['tab' => 'incoming']) }}" class="text-decoration-none">
                    <div class="card border-0 shadow-sm rounded-3 h-100 {{ ($tab ?? 'incoming') === 'incoming' ? 'border-success border-2' : '' }}" style="background: linear-gradient(135deg, #ffffff 0%, #f0fdf4 100%);">
                        <div class="card-body p-3">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <div class="text-muted small fw-semibold text-uppercase">Total Incoming (መቀበያ)</div>
                                    <div class="fs-3 fw-bold text-success mt-1">{{ number_format($incomingCount ?? 0) }}</div>
                                    <small class="text-muted">Materials arriving at {{ $assignedStore->name ?? 'your store' }}</small>
                                </div>
                                <div class="p-3 rounded-3 bg-success bg-opacity-10 text-success">
                                    <i class="fas fa-arrow-down fa-xl"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </a>
            </div>

            <div class="col-md-6 col-lg-3">
                <a href="{{ route('store-manager.transfers.index', ['tab' => 'incoming', 'status' => 'in_transit']) }}" class="text-decoration-none">
                    <div class="card border-0 shadow-sm rounded-3 h-100" style="background: linear-gradient(135deg, #ffffff 0%, #eff6ff 100%);">
                        <div class="card-body p-3">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <div class="text-muted small fw-semibold text-uppercase">To Receive (In Transit)</div>
                                    <div class="fs-3 fw-bold text-info mt-1">{{ number_format($pendingIncomingCount ?? 0) }}</div>
                                    <small class="text-muted">On the road from other stores</small>
                                </div>
                                <div class="p-3 rounded-3 bg-info bg-opacity-10 text-info">
                                    <i class="fas fa-truck-moving fa-xl"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </a>
            </div>

            <div class="col-md-6 col-lg-3">
                <a href="{{ route('store-manager.transfers.index', ['tab' => 'outgoing']) }}" class="text-decoration-none">
                    <div class="card border-0 shadow-sm rounded-3 h-100 {{ ($tab ?? '') === 'outgoing' ? 'border-primary border-2' : '' }}" style="background: linear-gradient(135deg, #ffffff 0%, #eff6ff 100%);">
                        <div class="card-body p-3">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <div class="text-muted small fw-semibold text-uppercase">Total Outgoing (መላኪያ)</div>
                                    <div class="fs-3 fw-bold text-primary mt-1">{{ number_format($outgoingCount ?? 0) }}</div>
                                    <small class="text-muted">Materials dispatched from {{ $assignedStore->name ?? 'your store' }}</small>
                                </div>
                                <div class="p-3 rounded-3 bg-primary bg-opacity-10 text-primary">
                                    <i class="fas fa-arrow-up fa-xl"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </a>
            </div>

            <div class="col-md-6 col-lg-3">
                <a href="{{ route('store-manager.transfers.index', ['tab' => 'outgoing', 'status' => 'approved']) }}" class="text-decoration-none">
                    <div class="card border-0 shadow-sm rounded-3 h-100" style="background: linear-gradient(135deg, #ffffff 0%, #fffbeb 100%);">
                        <div class="card-body p-3">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <div class="text-muted small fw-semibold text-uppercase">Ready for Dispatch</div>
                                    <div class="fs-3 fw-bold text-warning mt-1">{{ number_format($pendingOutgoingCount ?? 0) }}</div>
                                    <small class="text-muted">Pending outgoing waybill / slip</small>
                                </div>
                                <div class="p-3 rounded-3 bg-warning bg-opacity-10 text-warning">
                                    <i class="fas fa-dolly fa-xl"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </a>
            </div>
        @else
            {{-- Central / Store Manager / General Service KPIs --}}
            <div class="col-xl-2 col-md-4 col-sm-6">
                <a href="{{ route('store-manager.transfers.index', ['tab' => 'all']) }}" class="text-decoration-none">
                    <div class="card border-0 shadow-sm rounded-3 h-100 {{ ($tab ?? 'all') === 'all' ? 'border-primary border-2' : '' }}" style="background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);">
                        <div class="card-body p-3">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <div class="text-muted small fw-semibold">Total Transfers</div>
                                    <div class="fs-4 fw-bold text-dark mt-1">{{ number_format($totalCount ?? 0) }}</div>
                                </div>
                                <div class="p-2 rounded bg-primary bg-opacity-10 text-primary">
                                    <i class="fas fa-boxes-stacked fa-lg"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </a>
            </div>

            <div class="col-xl-2 col-md-4 col-sm-6">
                <a href="{{ route('store-manager.transfers.index', ['tab' => 'pending_driver']) }}" class="text-decoration-none">
                    <div class="card border-0 shadow-sm rounded-3 h-100 {{ ($tab ?? '') === 'pending_driver' ? 'border-warning border-2' : '' }}" style="background: linear-gradient(135deg, #ffffff 0%, #fffbeb 100%);">
                        <div class="card-body p-3">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <div class="text-muted small fw-semibold">Need Driver</div>
                                    <div class="fs-4 fw-bold text-warning mt-1">{{ number_format($pendingDriverCount ?? 0) }}</div>
                                </div>
                                <div class="p-2 rounded bg-warning bg-opacity-10 text-warning">
                                    <i class="fas fa-user-clock fa-lg"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </a>
            </div>

            <div class="col-xl-2 col-md-4 col-sm-6">
                <a href="{{ route('store-manager.transfers.index', ['tab' => 'assigned_drivers']) }}" class="text-decoration-none">
                    <div class="card border-0 shadow-sm rounded-3 h-100 {{ ($tab ?? '') === 'assigned_drivers' ? 'border-primary border-2' : '' }}" style="background: linear-gradient(135deg, #ffffff 0%, #f5f3ff 100%);">
                        <div class="card-body p-3">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <div class="text-muted small fw-semibold">Driver Assigned</div>
                                    <div class="fs-4 fw-bold text-primary mt-1">{{ number_format($assignedDriverCount ?? 0) }}</div>
                                </div>
                                <div class="p-2 rounded bg-primary bg-opacity-10 text-primary">
                                    <i class="fas fa-id-badge fa-lg"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </a>
            </div>

            <div class="col-xl-2 col-md-4 col-sm-6">
                <a href="{{ route('store-manager.transfers.index', ['tab' => 'pending_dispatch']) }}" class="text-decoration-none">
                    <div class="card border-0 shadow-sm rounded-3 h-100 {{ ($tab ?? '') === 'pending_dispatch' ? 'border-info border-2' : '' }}" style="background: linear-gradient(135deg, #ffffff 0%, #eff6ff 100%);">
                        <div class="card-body p-3">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <div class="text-muted small fw-semibold">Ready to Dispatch</div>
                                    <div class="fs-4 fw-bold text-info mt-1">{{ number_format($readyToDispatchCount ?? 0) }}</div>
                                </div>
                                <div class="p-2 rounded bg-info bg-opacity-10 text-info">
                                    <i class="fas fa-dolly fa-lg"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </a>
            </div>

            <div class="col-xl-2 col-md-4 col-sm-6">
                <a href="{{ route('store-manager.transfers.index', ['tab' => 'in_transit']) }}" class="text-decoration-none">
                    <div class="card border-0 shadow-sm rounded-3 h-100 {{ ($tab ?? '') === 'in_transit' ? 'border-info border-2' : '' }}" style="background: linear-gradient(135deg, #ffffff 0%, #f0fdfa 100%);">
                        <div class="card-body p-3">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <div class="text-muted small fw-semibold">In Transit</div>
                                    <div class="fs-4 fw-bold text-info mt-1">{{ number_format($inTransitCount ?? 0) }}</div>
                                </div>
                                <div class="p-2 rounded bg-info bg-opacity-10 text-info">
                                    <i class="fas fa-truck-fast fa-lg"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </a>
            </div>

            <div class="col-xl-2 col-md-4 col-sm-6">
                <a href="{{ route('store-manager.transfers.index', ['tab' => 'completed']) }}" class="text-decoration-none">
                    <div class="card border-0 shadow-sm rounded-3 h-100 {{ ($tab ?? '') === 'completed' ? 'border-success border-2' : '' }}" style="background: linear-gradient(135deg, #ffffff 0%, #f0fdf4 100%);">
                        <div class="card-body p-3">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <div class="text-muted small fw-semibold">Received &amp; Completed</div>
                                    <div class="fs-4 fw-bold text-success mt-1">{{ number_format($completedCount ?? 0) }}</div>
                                </div>
                                <div class="p-2 rounded bg-success bg-opacity-10 text-success">
                                    <i class="fas fa-check-double fa-lg"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </a>
            </div>
        @endif
    </div>

    {{-- Tabs & Filters Bar --}}
    <div class="card shadow-sm border-0 rounded-3 mb-4">
        <div class="card-body p-3">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 border-bottom pb-3 mb-3">
                <ul class="nav nav-pills gap-2">
                    @if($isStoreKeeper)
                        {{-- Store Keeper Tabs: Strictly Only Incoming and Outgoing --}}
                        <li class="nav-item">
                            <a class="nav-link py-2 px-4 fw-semibold {{ ($tab ?? 'incoming') === 'incoming' ? 'active bg-success text-white shadow-sm' : 'bg-light text-dark' }}" href="{{ route('store-manager.transfers.index', ['tab' => 'incoming']) }}">
                                <i class="fas fa-arrow-down me-2"></i>Incoming Materials (መቀበያ)
                                <span class="badge {{ ($tab ?? 'incoming') === 'incoming' ? 'bg-white text-success' : 'bg-success text-white' }} ms-2">{{ $incomingCount ?? 0 }}</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link py-2 px-4 fw-semibold {{ ($tab ?? '') === 'outgoing' ? 'active bg-primary text-white shadow-sm' : 'bg-light text-dark' }}" href="{{ route('store-manager.transfers.index', ['tab' => 'outgoing']) }}">
                                <i class="fas fa-arrow-up me-2"></i>Outgoing Materials (መላኪያ)
                                <span class="badge {{ ($tab ?? '') === 'outgoing' ? 'bg-white text-primary' : 'bg-primary text-white' }} ms-2">{{ $outgoingCount ?? 0 }}</span>
                            </a>
                        </li>
                    @else
                        {{-- Central Store Manager / Admin / General Service Tabs --}}
                        <li class="nav-item">
                            <a class="nav-link py-1 px-3 small {{ ($tab ?? 'all') === 'all' ? 'active' : '' }}" href="{{ route('store-manager.transfers.index', array_merge(request()->except('tab'), ['tab' => 'all'])) }}">
                                All Transfers
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link py-1 px-3 small {{ ($tab ?? '') === 'outgoing' ? 'active' : '' }}" href="{{ route('store-manager.transfers.index', array_merge(request()->except('tab'), ['tab' => 'outgoing'])) }}">
                                <i class="fas fa-arrow-up text-primary me-1"></i>Outgoing (መላኪያ)
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link py-1 px-3 small {{ ($tab ?? '') === 'incoming' ? 'active' : '' }}" href="{{ route('store-manager.transfers.index', array_merge(request()->except('tab'), ['tab' => 'incoming'])) }}">
                                <i class="fas fa-arrow-down text-success me-1"></i>Incoming (መቀበያ)
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link py-1 px-3 small {{ ($tab ?? '') === 'pending_driver' ? 'active' : '' }}" href="{{ route('store-manager.transfers.index', array_merge(request()->except('tab'), ['tab' => 'pending_driver'])) }}">
                                Needs Driver <span class="badge bg-warning text-dark ms-1">{{ $pendingDriverCount ?? 0 }}</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link py-1 px-3 small {{ ($tab ?? '') === 'assigned_drivers' ? 'active' : '' }}" href="{{ route('store-manager.transfers.index', array_merge(request()->except('tab'), ['tab' => 'assigned_drivers'])) }}">
                                <i class="fas fa-id-badge text-primary me-1"></i>Driver Assigned <span class="badge bg-primary text-white ms-1">{{ $assignedDriverCount ?? 0 }}</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link py-1 px-3 small {{ ($tab ?? '') === 'in_transit' ? 'active' : '' }}" href="{{ route('store-manager.transfers.index', array_merge(request()->except('tab'), ['tab' => 'in_transit'])) }}">
                                <i class="fas fa-truck-fast text-info me-1"></i>In-Transit <span class="badge bg-info text-dark ms-1">{{ $inTransitCount ?? 0 }}</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link py-1 px-3 small {{ ($tab ?? '') === 'completed' ? 'active' : '' }}" href="{{ route('store-manager.transfers.index', array_merge(request()->except('tab'), ['tab' => 'completed'])) }}">
                                Completed
                            </a>
                        </li>
                    @endif
                </ul>
            </div>

            <form method="GET" class="row g-2 align-items-center">
                <input type="hidden" name="tab" value="{{ $tab ?? 'all' }}">
                
                <div class="col-md-4">
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-white"><i class="fas fa-search text-muted"></i></span>
                        <input type="text" name="search" class="form-control" placeholder="Search Transfer #, Slip #, Driver, Plate..." value="{{ request('search') }}">
                    </div>
                </div>

                @if(!$isStoreKeeper)
                <div class="col-md-3">
                    <select name="store_id" class="form-select form-select-sm">
                        <option value="">All Stores &amp; Warehouses</option>
                        @foreach($stores as $store)
                        <option value="{{ $store->id }}" {{ request('store_id') == $store->id ? 'selected' : '' }}>
                            {{ $store->name }}
                        </option>
                        @endforeach
                    </select>
                </div>
                @endif

                <div class="col-md-3">
                    <select name="status" class="form-select form-select-sm">
                        <option value="">All Statuses</option>
                        <option value="draft" {{ request('status') == 'draft' ? 'selected' : '' }}>Draft / Pending Driver</option>
                        <option value="approved" {{ request('status') == 'approved' ? 'selected' : '' }}>Approved / Ready to Dispatch</option>
                        <option value="in_transit" {{ request('status') == 'in_transit' ? 'selected' : '' }}>In Transit with Driver</option>
                        <option value="completed" {{ request('status') == 'completed' ? 'selected' : '' }}>Completed / Received</option>
                        <option value="rejected" {{ request('status') == 'rejected' ? 'selected' : '' }}>Rejected</option>
                    </select>
                </div>

                <div class="col-md-2 d-flex gap-2">
                    <button type="submit" class="btn btn-primary btn-sm flex-grow-1"><i class="fas fa-filter me-1"></i>Filter</button>
                    <a href="{{ route('store-manager.transfers.index', ['tab' => $tab ?? 'all']) }}" class="btn btn-outline-secondary btn-sm" title="Clear search">
                        <i class="fas fa-rotate-left"></i>
                    </a>
                </div>
            </form>
        </div>
    </div>

    {{-- Transfers Table --}}
    <div class="card shadow-sm border-0 rounded-3">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-3">Transfer #</th>
                            <th>Origin (From)</th>
                            <th>Destination (To)</th>
                            <th>Assigned Driver &amp; Vehicle</th>
                            <th>Outgoing Slip</th>
                            <th>Receiving Status</th>
                            <th>Status</th>
                            <th class="text-end pe-3">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($transfers as $transfer)
                        @php
                            $userStoreId = $assignedStore?->id ?? auth()->user()->store_id;
                            $isIncoming = $userStoreId && ($transfer->to_store_id == $userStoreId);
                            $isOutgoing = $userStoreId && ($transfer->from_store_id == $userStoreId);
                        @endphp
                        <tr>
                            <td class="ps-3">
                                <a href="{{ route('store-manager.transfers.show', $transfer) }}" class="fw-bold font-monospace text-primary text-decoration-none">
                                    {{ $transfer->transfer_no }}
                                </a>
                                <div>
                                    <small class="text-muted">{{ $transfer->created_at ? $transfer->created_at->format('M d, Y') : '' }}</small>
                                    @if($isIncoming)
                                        <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 ms-1" style="font-size:0.68rem;">Incoming</span>
                                    @elseif($isOutgoing)
                                        <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 ms-1" style="font-size:0.68rem;">Outgoing</span>
                                    @endif
                                </div>
                            </td>

                            {{-- Origin Store --}}
                            <td>
                                <div class="fw-semibold text-dark">{{ $transfer->fromStore->name ?? 'N/A' }}</div>
                                <small class="text-muted"><i class="fas fa-boxes-stacked me-1"></i>{{ $transfer->items->count() }} item(s)</small>
                            </td>

                            {{-- Destination Store --}}
                            <td>
                                <div class="fw-semibold text-dark">{{ $transfer->toStore->name ?? 'N/A' }}</div>
                                <small class="text-muted"><i class="fas fa-user me-1"></i>Req: {{ $transfer->requestedBy->name ?? 'N/A' }}</small>
                            </td>

                            {{-- Driver & Vehicle --}}
                            <td>
                                @if($transfer->driver)
                                    <div class="fw-semibold text-dark">
                                        <i class="fas fa-id-badge text-primary me-1"></i>{{ $transfer->driver->full_name }}
                                    </div>
                                    <small class="text-muted">
                                        <i class="fas fa-truck text-secondary me-1"></i>{{ $transfer->vehicle_plate_no ?: 'No plate' }}
                                        @if($transfer->driver->phone) &bull; {{ $transfer->driver->phone }} @endif
                                    </small>
                                @else
                                    <span class="badge bg-warning bg-opacity-10 text-dark border border-warning">
                                        <i class="fas fa-clock me-1"></i>No Driver Assigned
                                    </span>
                                @endif
                            </td>

                            {{-- Outgoing Slip --}}
                            <td>
                                @if($transfer->outgoing_slip_file || $transfer->outgoing_slip_no || $transfer->physical_slip_no)
                                    <div>
                                        <span class="badge bg-light text-dark font-monospace border">
                                            <i class="fas fa-receipt text-primary me-1"></i>{{ $transfer->outgoing_slip_no ?: $transfer->physical_slip_no ?: 'Uploaded' }}
                                        </span>
                                    </div>
                                    @if($transfer->outgoing_slip_url)
                                        <a href="{{ $transfer->outgoing_slip_url }}" target="_blank" class="badge bg-primary bg-opacity-10 text-primary text-decoration-none mt-1 d-inline-block">
                                            <i class="fas fa-paperclip me-1"></i>View Slip Attachment
                                        </a>
                                    @endif
                                @else
                                    <span class="text-muted small">—</span>
                                @endif
                            </td>

                            {{-- Receiving Status --}}
                            <td>
                                @if($transfer->status === 'completed')
                                    <span class="badge bg-success bg-opacity-10 text-success border border-success">
                                        <i class="fas fa-check-circle me-1"></i>Received into Store
                                    </span>
                                    @if($transfer->receiving_slip_url)
                                        <div>
                                            <a href="{{ $transfer->receiving_slip_url }}" target="_blank" class="badge bg-light text-success text-decoration-none mt-1 d-inline-block">
                                                <i class="fas fa-file-check me-1"></i>Signed Slip
                                            </a>
                                        </div>
                                    @endif
                                @elseif($transfer->status === 'in_transit')
                                    <span class="badge bg-info bg-opacity-10 text-info border border-info">
                                        <i class="fas fa-truck-moving me-1"></i>On Road to Destination
                                    </span>
                                @else
                                    <span class="text-muted small">Pending Dispatch</span>
                                @endif
                            </td>

                            {{-- Status Badge --}}
                            <td>
                                @php
                                    $sBadge = match($transfer->status) {
                                        'completed'  => 'bg-success',
                                        'in_transit' => 'bg-info text-dark',
                                        'approved'   => 'bg-primary',
                                        'rejected'   => 'bg-danger',
                                        default      => 'bg-secondary',
                                    };
                                    $sLabel = match($transfer->status) {
                                        'completed'  => 'Completed',
                                        'in_transit' => 'In Transit',
                                        'approved'   => 'Ready to Dispatch',
                                        'rejected'   => 'Rejected',
                                        default      => 'Draft / Pending',
                                    };
                                @endphp
                                <span class="badge {{ $sBadge }} px-2 py-1 small">
                                    {{ $sLabel }}
                                </span>
                            </td>

                            {{-- Actions --}}
                            <td class="text-end pe-3">
                                <div class="d-flex justify-content-end gap-1">
                                    @if($transfer->status === 'in_transit' && ($isIncoming || auth()->user()->hasAnyRole(['admin', 'global_admin', 'store_manager'])))
                                        <a href="{{ route('store-manager.transfers.show', $transfer) }}" class="btn btn-sm btn-success shadow-sm" title="Inspect & Receive Materials">
                                            <i class="fas fa-box-open me-1"></i>Receive
                                        </a>
                                    @elseif(in_array($transfer->status, ['draft', 'approved']) && ($isOutgoing || auth()->user()->hasAnyRole(['admin', 'global_admin', 'store_manager'])))
                                        <a href="{{ route('store-manager.transfers.show', $transfer) }}" class="btn btn-sm btn-primary shadow-sm" title="Dispatch & Upload Outgoing Slip">
                                            <i class="fas fa-truck-fast me-1"></i>Dispatch
                                        </a>
                                    @else
                                        <a href="{{ route('store-manager.transfers.show', $transfer) }}" class="btn btn-sm btn-outline-primary">
                                            <i class="fas fa-eye me-1"></i>View
                                        </a>
                                    @endif
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="8" class="text-center py-5 text-muted">
                                <i class="fa-solid fa-truck-moving fa-3x mb-2 d-block opacity-25"></i>
                                <h6 class="fw-bold mb-1">No Material Transfers Found</h6>
                                <p class="small mb-3">No transfer records match your current filter selection.</p>
                                <a href="{{ route('store-manager.transfers.create') }}" class="btn btn-sm btn-primary">
                                    <i class="fas fa-plus me-1"></i>Create New Transfer
                                </a>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if($transfers->hasPages())
        <div class="card-footer bg-white border-top py-2">
            {{ $transfers->links() }}
        </div>
        @endif
    </div>

</div>
@endsection
