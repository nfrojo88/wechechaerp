@extends('layouts.app')
@section('title', 'Weekly Material Demand - Store Keeper')

@section('content')
<div class="container-fluid px-4 py-3">

    {{-- Header --}}
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h1 class="h3 mb-0 fw-bold" style="color:var(--brand-800)">
                <i class="fa-solid fa-calendar-check me-2 text-primary"></i>Weekly Material Demand
            </h1>
            <p class="text-muted small mb-0">
                Site material requirements generated from the Planning team's weekly work schedule dispatches
            </p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('store-manager.transfers.create') }}" class="btn btn-primary btn-sm shadow-sm">
                <i class="fa-solid fa-plus me-1"></i> Request Restock Transfer
            </a>
            <a href="{{ route('dashboard.store-keeper') }}" class="btn btn-outline-secondary btn-sm shadow-sm">
                <i class="fa-solid fa-arrow-left me-1"></i> Store Dashboard
            </a>
        </div>
    </div>

    {{-- ── Store & Site Context Banner ──────────────────────────────────────── --}}
    @if($assignedStore)
    <div class="card border-0 shadow-sm rounded-3 mb-4 bg-white" style="border-left: 5px solid #0284c7 !important;">
        <div class="card-body p-3">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
                <div class="d-flex align-items-center gap-3">
                    <div class="p-2 rounded-3 bg-primary bg-opacity-10 text-primary">
                        <i class="fa-solid fa-warehouse fa-lg"></i>
                    </div>
                    <div>
                        <h6 class="mb-0 fw-bold text-dark">{{ $assignedStore->name }} ({{ $assignedStore->code ?? 'STORE' }})</h6>
                        <small class="text-muted">
                            <i class="fa-solid fa-building me-1 text-secondary"></i>Project: <strong>{{ $project->name ?? ($assignedStore->project->name ?? 'All Assigned Projects') }}</strong>
                            <span class="mx-2">·</span>
                            <i class="fa-solid fa-location-dot me-1 text-danger"></i>{{ $assignedStore->address ?: 'Site Location' }}
                        </small>
                    </div>
                </div>
                <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 px-3 py-2">
                    <i class="fa-solid fa-shield-halved me-1"></i>Site Store Scoped
                </span>
            </div>
        </div>
    </div>
    @endif

    {{-- ── Weekly Material Demand Table ────────────────────────────────────── --}}
    <div class="card border-0 shadow-sm rounded-3 mb-4">
        <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center">
            <div>
                <h6 class="mb-0 fw-bold text-dark">
                    <i class="fa-solid fa-clipboard-list text-primary me-2"></i>Weekly Material Schedule &amp; Stock Availability
                </h6>
                <small class="text-muted">Compares weekly work schedule material demand against current on-hand site store stock</small>
            </div>
            <a href="{{ route('store-manager.inventory.all') }}" class="btn btn-sm btn-outline-secondary">
                <i class="fa-solid fa-boxes-stacked me-1"></i>View Full Stock
            </a>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3">Material Item</th>
                        <th>Project / Task Area</th>
                        <th>Week Period</th>
                        <th class="text-center">Weekly Demand Qty</th>
                        <th class="text-center">On-Hand in Store</th>
                        <th class="text-center">Stock Balance</th>
                        <th class="text-center">Status</th>
                        <th class="text-end pe-3">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @php $hasDemands = false; @endphp
                    @foreach($materialPlans as $plan)
                        @foreach($plan->items as $item)
                            @php
                                $hasDemands = true;
                                $onHand = $storeInventoryMap[$item->product_id] ?? 0;
                                $demandQty = (float) $item->quantity;
                                $balance = $onHand - $demandQty;
                                $isShortage = $balance < 0;
                                $isLow = $onHand <= ($demandQty * 1.2) && !$isShortage;
                            @endphp
                            <tr>
                                <td class="ps-3">
                                    <span class="fw-bold text-dark d-block">{{ $item->product->name ?? 'Material #'.$item->product_id }}</span>
                                    <small class="text-muted font-monospace">{{ $item->product->code ?? $item->product->sku ?? '' }}</small>
                                </td>
                                <td>
                                    <span class="fw-semibold text-dark small">{{ $plan->project->name ?? 'Site Project' }}</span>
                                </td>
                                <td>
                                    <span class="badge bg-light text-dark border">
                                        Week {{ $item->week_number ?? '1' }}
                                    </span>
                                </td>
                                <td class="text-center">
                                    <span class="fw-bold text-primary" style="font-size:0.95rem;">
                                        {{ number_format($demandQty, 2) }} {{ $item->product->unit_of_measure ?? 'pcs' }}
                                    </span>
                                </td>
                                <td class="text-center">
                                    <span class="fw-bold {{ $onHand <= 0 ? 'text-danger' : 'text-dark' }}" style="font-size:0.95rem;">
                                        {{ number_format($onHand, 2) }} {{ $item->product->unit_of_measure ?? 'pcs' }}
                                    </span>
                                </td>
                                <td class="text-center">
                                    <span class="fw-bold {{ $isShortage ? 'text-danger' : 'text-success' }}">
                                        {{ $balance >= 0 ? '+' : '' }}{{ number_format($balance, 2) }}
                                    </span>
                                </td>
                                <td class="text-center">
                                    @if($isShortage)
                                        <span class="badge bg-danger">
                                            <i class="fa-solid fa-triangle-exclamation me-1"></i>Shortage
                                        </span>
                                    @elseif($isLow)
                                        <span class="badge bg-warning text-dark">
                                            <i class="fa-solid fa-circle-exclamation me-1"></i>Tight Stock
                                        </span>
                                    @else
                                        <span class="badge bg-success">
                                            <i class="fa-solid fa-circle-check me-1"></i>Sufficient
                                        </span>
                                    @endif
                                </td>
                                <td class="text-end pe-3">
                                    @if($isShortage)
                                        <a href="{{ route('store-manager.transfers.create') }}" class="btn btn-sm btn-danger shadow-sm">
                                            <i class="fa-solid fa-truck-moving me-1"></i>Request Transfer
                                        </a>
                                    @else
                                        <a href="{{ route('store-manager.transfers.create') }}" class="btn btn-sm btn-outline-primary">
                                            <i class="fa-solid fa-plus me-1"></i>Transfer
                                        </a>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    @endforeach

                    @if(!$hasDemands)
                    <tr>
                        <td colspan="8" class="text-center py-5 text-muted">
                            <i class="fa-solid fa-calendar-xmark fa-3x mb-3 d-block opacity-25"></i>
                            <h6 class="fw-bold text-dark">No Weekly Material Demands Scheduled Yet</h6>
                            <p class="small text-muted mb-2">When the planning team creates and dispatches the weekly work schedule for this site, material requirements will automatically appear here.</p>
                            <a href="{{ route('store-manager.transfers.create') }}" class="btn btn-sm btn-primary">Create Store Transfer →</a>
                        </td>
                    </tr>
                    @endif
                </tbody>
            </table>
        </div>
    </div>

    {{-- ── Active Weekly Dispatches from Planning Team ─────────────────────── --}}
    <div class="card border-0 shadow-sm rounded-3">
        <div class="card-header bg-white border-bottom py-3">
            <h6 class="mb-0 fw-bold text-dark">
                <i class="fa-solid fa-calendar-week text-success me-2"></i>Weekly Work Schedule Dispatches
            </h6>
            <small class="text-muted">Work schedules dispatched to site engineers</small>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3">Schedule Ref #</th>
                        <th>Project</th>
                        <th>Dispatched To (Engineer)</th>
                        <th>Date Range</th>
                        <th>Scheduled Tasks</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($weeklyDispatches as $dispatch)
                    <tr>
                        <td class="ps-3">
                            <span class="fw-bold font-monospace text-primary">
                                {{ $dispatch->reference_number ?? 'DISP-'.$dispatch->id }}
                            </span>
                        </td>
                        <td>
                            <span class="fw-semibold text-dark">{{ $dispatch->project->name ?? 'Site Project' }}</span>
                        </td>
                        <td>
                            <span class="small">{{ $dispatch->dispatchedTo->name ?? 'Site Engineer' }}</span>
                        </td>
                        <td>
                            <small class="text-muted">
                                {{ optional($dispatch->start_date)->format('d M Y') }} – {{ optional($dispatch->end_date)->format('d M Y') }}
                            </small>
                        </td>
                        <td>
                            <span class="badge bg-info text-dark">{{ $dispatch->tasks->count() }} Tasks Dispatched</span>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5" class="text-center py-4 text-muted small">
                            No weekly work schedule dispatches on record for this site.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

</div>
@endsection
