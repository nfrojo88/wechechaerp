@extends('layouts.app')
@section('title', 'Site Engineer Dashboard')

@section('content')
<div class="wc-container py-3 py-md-4">

    {{-- Top Header --}}
    <x-page-header
        title="Site Engineer Dashboard"
        subtitle="Site operations, morning workforce reporting, schedules, and material requests"
        badge="Site Operations"
    >
        <x-slot name="actions">
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <span class="badge bg-white text-secondary border px-3 py-2 shadow-sm rounded-pill d-none d-sm-inline-flex align-items-center">
                    <i class="fa-solid fa-calendar-day text-primary me-2"></i>{{ now()->format('l, F j, Y') }}
                </span>
                <a href="{{ route('manpower-daily-report.create') }}" class="btn btn-primary btn-sm shadow-sm d-inline-flex align-items-center" style="min-height: 40px;">
                    <i class="fa-solid fa-users-line me-1"></i> Submit Manpower
                </a>
                <a href="{{ route('material-requests.create', ['source' => 'Emergency']) }}" class="btn btn-danger btn-sm shadow-sm d-inline-flex align-items-center" style="min-height: 40px;">
                    <i class="fa-solid fa-bolt me-1"></i> Emergency MR
                </a>
            </div>
        </x-slot>
    </x-page-header>

    {{-- Morning Manpower Report Status Hero Banner --}}
    @if($todayManpowerReport)
        @if($todayManpowerReport->status === 'approved')
            <div class="card border-0 shadow-sm rounded-3 mb-4 text-white" style="background: linear-gradient(135deg, var(--wc-success, #059669) 0%, #10b981 100%);">
                <div class="card-body p-3 p-md-4 d-flex justify-content-between align-items-center flex-wrap gap-3">
                    <div class="d-flex align-items-center gap-3">
                        <div class="rounded-circle bg-white bg-opacity-25 d-flex align-items-center justify-content-center flex-shrink-0" style="width: 48px; height: 48px;">
                            <i class="fa-solid fa-circle-check fa-xl text-white"></i>
                        </div>
                        <div>
                            <h6 class="fw-bold mb-1 fs-6">Today's Morning Manpower Report Approved</h6>
                            <p class="mb-0 small text-white text-opacity-90">
                                <strong>{{ $todayManpowerReport->total_present }}</strong> workers logged for <strong>{{ $todayManpowerReport->project->name ?? 'Site' }}</strong> &bull; Reviewed by <strong>{{ $todayManpowerReport->reviewer->name ?? 'Planning Manager' }}</strong>
                                @if($todayManpowerReport->review_notes) — <em>"{{ $todayManpowerReport->review_notes }}"</em>@endif
                            </p>
                        </div>
                    </div>
                    <a href="{{ route('manpower-daily-report.show', $todayManpowerReport) }}" class="btn btn-light btn-sm text-success fw-bold shadow-sm px-3" style="min-height: 40px; display: inline-flex; align-items: center;">
                        <i class="fa-solid fa-eye me-1"></i> View Report
                    </a>
                </div>
            </div>
        @elseif($todayManpowerReport->status === 'rejected')
            <div class="card border-0 shadow-sm rounded-3 mb-4 text-white" style="background: linear-gradient(135deg, var(--wc-danger, #dc2626) 0%, #ef4444 100%);">
                <div class="card-body p-3 p-md-4 d-flex justify-content-between align-items-center flex-wrap gap-3">
                    <div class="d-flex align-items-center gap-3">
                        <div class="rounded-circle bg-white bg-opacity-25 d-flex align-items-center justify-content-center flex-shrink-0" style="width: 48px; height: 48px;">
                            <i class="fa-solid fa-triangle-exclamation fa-xl text-white"></i>
                        </div>
                        <div>
                            <h6 class="fw-bold mb-1 fs-6">Manpower Report Needs Revision</h6>
                            <p class="mb-0 small text-white text-opacity-90">
                                Planning Manager rejected today's submission.
                                @if($todayManpowerReport->review_notes) Reason: <strong>"{{ $todayManpowerReport->review_notes }}"</strong>@endif
                            </p>
                        </div>
                    </div>
                    <a href="{{ route('manpower-daily-report.create') }}" class="btn btn-light btn-sm text-danger fw-bold shadow-sm px-3" style="min-height: 40px; display: inline-flex; align-items: center;">
                        <i class="fa-solid fa-pen-to-square me-1"></i> Resubmit Report
                    </a>
                </div>
            </div>
        @else
            <div class="card border-0 shadow-sm rounded-3 mb-4 text-dark" style="background: #FFFBEB; border-left: 4px solid var(--wc-warning, #D97706) !important;">
                <div class="card-body p-3 p-md-4 d-flex justify-content-between align-items-center flex-wrap gap-3">
                    <div class="d-flex align-items-center gap-3">
                        <div class="rounded-circle bg-warning bg-opacity-25 d-flex align-items-center justify-content-center flex-shrink-0" style="width: 48px; height: 48px;">
                            <i class="fa-solid fa-hourglass-half fa-xl text-warning"></i>
                        </div>
                        <div>
                            <h6 class="fw-bold mb-1 text-dark fs-6">Today's Manpower Report Submitted — Awaiting Review</h6>
                            <p class="mb-0 small text-muted">
                                Submitted at {{ $todayManpowerReport->created_at->format('h:i A') }} with <strong>{{ $todayManpowerReport->total_present }} workers</strong> for <strong>{{ $todayManpowerReport->project->name ?? 'Site' }}</strong>. Sent to Planning Manager.
                            </p>
                        </div>
                    </div>
                    <a href="{{ route('manpower-daily-report.show', $todayManpowerReport) }}" class="btn btn-warning btn-sm text-dark fw-bold shadow-sm px-3" style="min-height: 40px; display: inline-flex; align-items: center;">
                        <i class="fa-solid fa-eye me-1"></i> View Submission
                    </a>
                </div>
            </div>
        @endif
    @else
        <div class="card border-0 shadow-sm rounded-3 mb-4 text-white" style="background: linear-gradient(135deg, var(--wc-primary, #0F2944) 0%, #1e3a8a 100%);">
            <div class="card-body p-3 p-md-4 d-flex justify-content-between align-items-center flex-wrap gap-3">
                <div class="d-flex align-items-center gap-3">
                    <div class="rounded-circle bg-white bg-opacity-20 d-flex align-items-center justify-content-center flex-shrink-0" style="width: 48px; height: 48px;">
                        <i class="fa-solid fa-users-rays fa-xl text-warning"></i>
                    </div>
                    <div>
                        <h6 class="fw-bold mb-1 fs-6">Morning Manpower Report Not Submitted Yet</h6>
                        <p class="mb-0 small text-white text-opacity-90">
                            Please log today's site workforce count (skilled, unskilled, operators, subcontractors) for Planning Manager visibility.
                        </p>
                    </div>
                </div>
                <a href="{{ route('manpower-daily-report.create') }}" class="btn btn-warning btn-sm text-dark fw-bold shadow-sm px-4 py-2" style="min-height: 40px; display: inline-flex; align-items: center;">
                    <i class="fa-solid fa-paper-plane me-1"></i> Submit Morning Report
                </a>
            </div>
        </div>
    @endif

    {{-- Site Stores & Inventory Summary Banner --}}
    @if(isset($assignedStores) && $assignedStores->isNotEmpty())
    <div class="card border-0 shadow-sm rounded-3 mb-4 bg-white border-start border-4 border-info">
        <div class="card-body p-3 d-flex flex-wrap align-items-center justify-content-between gap-3">
            <div class="d-flex align-items-center gap-3">
                <div class="rounded-circle bg-info bg-opacity-10 p-2 text-info d-flex align-items-center justify-content-center flex-shrink-0" style="width: 44px; height: 44px;">
                    <i class="fa-solid fa-warehouse fa-lg"></i>
                </div>
                <div>
                    <div class="fw-bold text-dark">
                        Assigned Site Store(s):
                        <span class="text-primary">{{ $assignedStores->pluck('name')->implode(', ') }}</span>
                    </div>
                    <div class="text-muted small">
                        <strong>{{ $kpi['site_inventory_count'] ?? 0 }}</strong> material types on hand &bull;
                        @if(($kpi['site_low_stock_count'] ?? 0) > 0)
                            <span class="text-danger fw-semibold"><i class="fa-solid fa-triangle-exclamation me-1"></i>{{ $kpi['site_low_stock_count'] }} low stock</span> &bull;
                        @else
                            <span class="text-success"><i class="fa-solid fa-check me-1"></i>Stock levels healthy</span> &bull;
                        @endif
                        <span><strong>{{ $kpi['site_movements_7d'] ?? 0 }}</strong> movements logged in last 7 days</span>
                    </div>
                </div>
            </div>
            <div class="d-flex gap-2">
                <a href="{{ route('inventory.index') }}" class="btn btn-sm btn-outline-primary fw-semibold" style="min-height: 38px; display: inline-flex; align-items: center;">
                    <i class="fa-solid fa-boxes-stacked me-1"></i> Site Inventory
                </a>
                <a href="{{ route('inventory.all-movements') }}" class="btn btn-sm btn-outline-info fw-semibold" style="min-height: 38px; display: inline-flex; align-items: center;">
                    <i class="fa-solid fa-arrow-right-arrow-left me-1"></i> Movements
                </a>
            </div>
        </div>
    </div>
    @endif

    {{-- KPI Cards Row --}}
    <div class="row g-3 mb-4">
        <!-- Today Workforce -->
        <div class="col-12 col-sm-6 col-xl-3">
            <x-kpi-card
                title="Total Site Workforce Today"
                :value="$kpi['today_manpower_count'] ?? 0"
                :subtitle="$todayManpowerReport ? 'Logged in Morning Report' : 'Site Attendance'"
                icon="fa-solid fa-users"
                tone="primary"
            />
        </div>

        <!-- Assigned Work Orders -->
        <div class="col-12 col-sm-6 col-xl-3">
            <x-kpi-card
                title="Active Work Orders"
                :value="$kpi['assigned_work_orders_count'] ?? 0"
                subtitle="Assigned from Planning"
                icon="fa-solid fa-calendar-check"
                tone="info"
            />
        </div>

        <!-- Material Requests -->
        <div class="col-12 col-sm-6 col-xl-3">
            <x-kpi-card
                title="Pending Material Requests"
                :value="$kpi['pending_mr_count'] ?? 0"
                :subtitle="'/ ' . ($kpi['my_material_requests'] ?? 0) . ' total requests'"
                icon="fa-solid fa-cart-flatbed"
                tone="warning"
            />
        </div>

        <!-- Open Site Issues -->
        <div class="col-12 col-sm-6 col-xl-3">
            <x-kpi-card
                title="Open Site Issues"
                :value="$kpi['open_issues_count'] ?? 0"
                subtitle="Delays & site obstacles"
                icon="fa-solid fa-triangle-exclamation"
                tone="danger"
            />
        </div>
    </div>

    {{-- Main Content Grid --}}
    <div class="row g-4">
        {{-- Left Column (8 cols) --}}
        <div class="col-lg-8">

            {{-- Assigned Work Orders / Schedule --}}
            <div class="card border-0 shadow-sm rounded-3 mb-4 bg-white">
                <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                    <h6 class="m-0 fw-bold text-dark">
                        <i class="fa-solid fa-calendar-days text-info me-2"></i>Assigned Work Orders & Dispatches
                    </h6>
                    <a href="{{ route('eng-schedule.my') }}" class="btn btn-sm btn-outline-primary" style="min-height: 36px; display: inline-flex; align-items: center;">
                        View Schedule <i class="fa-solid fa-arrow-right ms-1"></i>
                    </a>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive wc-table-mobile-cards">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Work Order</th>
                                    <th>Project / Location</th>
                                    <th>Schedule</th>
                                    <th>Priority</th>
                                    <th>Status</th>
                                    <th class="text-end">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($assignedWorkOrders as $order)
                                <tr>
                                    <td data-label="Work Order">
                                        <div class="fw-bold text-dark">{{ $order->title }}</div>
                                        <small class="text-muted">{{ $order->category ?? 'General' }}</small>
                                    </td>
                                    <td data-label="Project / Location">
                                        <div class="fw-semibold small">{{ $order->project->name ?? 'N/A' }}</div>
                                        <small class="text-muted">{{ $order->location ?? 'Site' }}</small>
                                    </td>
                                    <td data-label="Schedule" class="small">
                                        <div><i class="fa-regular fa-clock text-muted me-1"></i>{{ $order->start_datetime->format('M d, H:i') }}</div>
                                        <div class="text-muted">to {{ $order->end_datetime->format('M d, H:i') }}</div>
                                    </td>
                                    <td data-label="Priority">
                                        @php
                                            $pTones = [
                                                'urgent' => 'danger',
                                                'high'   => 'warning',
                                                'medium' => 'info',
                                                'low'    => 'neutral',
                                            ];
                                        @endphp
                                        <x-status-badge :status="$order->priority" :tone="$pTones[$order->priority] ?? 'neutral'" />
                                    </td>
                                    <td data-label="Status">
                                        <x-status-badge :status="$order->status" />
                                    </td>
                                    <td data-label="Action" class="text-end">
                                        <a href="{{ route('eng-schedule.show', $order->id) }}" class="btn btn-sm btn-light border" title="View Details" style="min-width: 36px; min-height: 36px; display: inline-flex; align-items: center; justify-content: center;">
                                            <i class="fa-solid fa-eye"></i>
                                        </a>
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="6" class="text-center text-muted py-4">
                                        <i class="fa-solid fa-calendar-xmark fa-2x mb-2 d-block text-muted"></i>
                                        No active work orders assigned to you today.
                                    </td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            {{-- Site Store Inventory & Recent Movements (Assigned Site Only) --}}
            <div class="card border-0 shadow-sm rounded-3 mb-4 bg-white">
                <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div>
                        <h6 class="m-0 fw-bold text-dark">
                            <i class="fa-solid fa-warehouse text-primary me-2"></i>Site Store Inventory & Movements
                        </h6>
                        <small class="text-muted">Strictly scoped to your assigned site store(s)</small>
                    </div>
                    <ul class="nav nav-pills card-header-pills" id="siteInvTab" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active btn-sm py-1 px-3" id="stock-tab" data-bs-toggle="pill" data-bs-target="#stock-panel" type="button" role="tab" style="min-height: 36px;">
                                <i class="fa-solid fa-boxes-stacked me-1"></i> Stock on Hand ({{ isset($siteInventoryItems) ? $siteInventoryItems->count() : 0 }})
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link btn-sm py-1 px-3" id="movements-tab" data-bs-toggle="pill" data-bs-target="#movements-panel" type="button" role="tab" style="min-height: 36px;">
                                <i class="fa-solid fa-arrow-right-arrow-left me-1"></i> Recent Movements
                            </button>
                        </li>
                    </ul>
                </div>
                <div class="card-body p-0">
                    <div class="tab-content" id="siteInvTabContent">
                        {{-- Stock on Hand Tab --}}
                        <div class="tab-pane fade show active" id="stock-panel" role="tabpanel">
                            <div class="table-responsive wc-table-mobile-cards">
                                <table class="table table-hover align-middle mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Material / Product</th>
                                            <th>Store</th>
                                            <th>Category</th>
                                            <th class="text-end">On Hand</th>
                                            <th class="text-end">Available</th>
                                            <th class="text-center">Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse($siteInventoryItems ?? [] as $item)
                                        @php
                                            $isLow = $item->quantity_on_hand <= $item->min_stock && $item->min_stock > 0;
                                            $skuCode = $item->product?->sku ?: ($item->product?->code ?: null);
                                        @endphp
                                        <tr class="{{ $isLow ? 'table-warning' : '' }}">
                                            <td data-label="Material">
                                                <div class="fw-semibold text-dark">{{ $item->product?->name ?? 'N/A' }}</div>
                                                @if($skuCode)
                                                    <small class="text-muted font-monospace"><i class="fa-solid fa-barcode me-1"></i>{{ $skuCode }}</small>
                                                @endif
                                            </td>
                                            <td data-label="Store">
                                                <span class="badge bg-light text-dark border">{{ $item->store?->name ?? 'Site Store' }}</span>
                                            </td>
                                            <td data-label="Category" class="small text-muted">{{ $item->product?->category ?? '—' }}</td>
                                            <td data-label="On Hand" class="text-end fw-bold font-tabular">
                                                {{ number_format($item->quantity_on_hand, 2) }}
                                                <small class="text-muted fw-normal">{{ $item->product?->unit ?? '' }}</small>
                                            </td>
                                            <td data-label="Available" class="text-end font-tabular {{ $isLow ? 'text-danger fw-bold' : '' }}">
                                                {{ number_format($item->quantity_available, 2) }}
                                            </td>
                                            <td data-label="Status" class="text-center">
                                                @if($isLow)
                                                    <x-status-badge status="Low Stock" tone="danger" icon="fa-solid fa-triangle-exclamation" />
                                                @else
                                                    <x-status-badge status="Healthy" tone="success" icon="fa-solid fa-check" />
                                                @endif
                                            </td>
                                        </tr>
                                        @empty
                                        <tr>
                                            <td colspan="6" class="text-center text-muted py-4">
                                                <i class="fa-solid fa-boxes-stacked fa-2x mb-2 d-block text-secondary"></i>
                                                No inventory records found for your assigned site store(s).
                                            </td>
                                        </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                            @if(isset($siteInventoryItems) && $siteInventoryItems->isNotEmpty())
                            <div class="p-2 text-center border-top bg-light bg-opacity-50">
                                <a href="{{ route('inventory.index') }}" class="small fw-semibold text-primary text-decoration-none">
                                    View Complete Site Inventory ({{ $kpi['site_inventory_count'] ?? 0 }} items) <i class="fa-solid fa-arrow-right ms-1"></i>
                                </a>
                            </div>
                            @endif
                        </div>

                        {{-- Recent Movements Tab --}}
                        <div class="tab-pane fade" id="movements-panel" role="tabpanel">
                            <div class="table-responsive wc-table-mobile-cards">
                                <table class="table table-hover align-middle mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Date</th>
                                            <th>Product</th>
                                            <th>Type</th>
                                            <th class="text-end">Quantity</th>
                                            <th>Performed By</th>
                                            <th>Remarks</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse($siteRecentMovements ?? [] as $mov)
                                        @php
                                            $movTone = match(strtolower($mov->type ?? '')) {
                                                'in', 'purchase_intake', 'grn' => 'success',
                                                'out', 'dispatch', 'issue' => 'danger',
                                                'transfer', 'transfer_in', 'transfer_out' => 'info',
                                                'adjustment' => 'warning',
                                                'reserve' => 'neutral',
                                                'release' => 'primary',
                                                default => 'neutral'
                                            };
                                            $isPositive = in_array(strtolower($mov->type ?? ''), ['in', 'purchase_intake', 'grn', 'adjustment', 'release']);
                                            $qty = (float) $mov->quantity;
                                        @endphp
                                        <tr>
                                            <td data-label="Date" class="small">
                                                <div class="fw-semibold text-dark">{{ $mov->created_at ? $mov->created_at->format('M d, H:i') : '—' }}</div>
                                            </td>
                                            <td data-label="Product">
                                                <div class="fw-semibold small text-dark">{{ $mov->inventory?->product?->name ?? 'N/A' }}</div>
                                                <small class="text-muted">{{ $mov->inventory?->store?->name ?? '' }}</small>
                                            </td>
                                            <td data-label="Type">
                                                <x-status-badge :status="$mov->type" :tone="$movTone" />
                                            </td>
                                            <td data-label="Quantity" class="text-end font-tabular fw-bold {{ $isPositive ? 'text-success' : 'text-danger' }}">
                                                {{ $isPositive ? '+' : '-' }}{{ number_format(abs($qty), 2) }}
                                                <small class="text-muted fw-normal">{{ $mov->inventory?->product?->unit ?? '' }}</small>
                                            </td>
                                            <td data-label="Performed By" class="small">{{ $mov->performer?->name ?? 'System' }}</td>
                                            <td data-label="Remarks" class="small text-muted text-truncate" style="max-width: 180px;">{{ $mov->remarks ?: '—' }}</td>
                                        </tr>
                                        @empty
                                        <tr>
                                            <td colspan="6" class="text-center text-muted py-4">
                                                <i class="fa-solid fa-arrow-right-arrow-left fa-2x mb-2 d-block text-secondary"></i>
                                                No recent stock movements recorded for your assigned site store(s).
                                            </td>
                                        </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                            @if(isset($siteRecentMovements) && $siteRecentMovements->isNotEmpty())
                            <div class="p-2 text-center border-top bg-light bg-opacity-50">
                                <a href="{{ route('inventory.all-movements') }}" class="small fw-semibold text-primary text-decoration-none">
                                    View All Site Movements History <i class="fa-solid fa-arrow-right ms-1"></i>
                                </a>
                            </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            {{-- Recent Morning Manpower Reports --}}
            <div class="card border-0 shadow-sm rounded-3 mb-4 bg-white">
                <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                    <h6 class="m-0 fw-bold text-dark">
                        <i class="fa-solid fa-users-line text-primary me-2"></i>Recent Morning Manpower Reports
                    </h6>
                    <a href="{{ route('manpower-daily-report.index') }}" class="btn btn-sm btn-outline-primary" style="min-height: 36px; display: inline-flex; align-items: center;">
                        View All History <i class="fa-solid fa-arrow-right ms-1"></i>
                    </a>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive wc-table-mobile-cards">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Date</th>
                                    <th>Project</th>
                                    <th class="text-center">Total Present</th>
                                    <th>Work Area</th>
                                    <th class="text-center">Review Status</th>
                                    <th class="text-end">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($recentManpowerReports as $mp)
                                <tr>
                                    <td data-label="Date" class="fw-semibold small">
                                        {{ $mp->report_date->format('D, M d, Y') }}
                                        @if($mp->report_date->isToday())
                                            <span class="badge bg-primary ms-1">Today</span>
                                        @endif
                                    </td>
                                    <td data-label="Project" class="small">{{ $mp->project->name ?? 'N/A' }}</td>
                                    <td data-label="Total Present" class="text-center">
                                        <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 px-2 py-1 fw-bold rounded-pill">
                                            <i class="fa-solid fa-users me-1"></i>{{ $mp->total_present }}
                                        </span>
                                    </td>
                                    <td data-label="Work Area" class="small text-muted">{{ $mp->work_area ?: '—' }}</td>
                                    <td data-label="Review Status" class="text-center">
                                        <span class="badge {{ $mp->status_badge_class }}">{{ $mp->status_label }}</span>
                                        @if($mp->review_notes)
                                            <div class="text-muted small mt-1" style="font-size: 0.75rem;"><i class="fa-solid fa-comment me-1"></i>{{ \Illuminate\Support\Str::limit($mp->review_notes, 30) }}</div>
                                        @endif
                                    </td>
                                    <td data-label="Action" class="text-end">
                                        <a href="{{ route('manpower-daily-report.show', $mp) }}" class="btn btn-sm btn-outline-primary" title="View Report" style="min-width: 36px; min-height: 36px; display: inline-flex; align-items: center; justify-content: center;">
                                            <i class="fa-solid fa-eye"></i>
                                        </a>
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="6" class="text-center text-muted py-4">
                                        <i class="fa-solid fa-inbox fa-2x mb-2 d-block text-muted"></i>
                                        No manpower reports submitted yet.
                                        <div class="mt-2">
                                            <a href="{{ route('manpower-daily-report.create') }}" class="btn btn-sm btn-primary">Submit Morning Report</a>
                                        </div>
                                    </td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            {{-- Recent Material Requests --}}
            <div class="card border-0 shadow-sm rounded-3 mb-4 bg-white">
                <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                    <h6 class="m-0 fw-bold text-dark">
                        <i class="fa-solid fa-cart-flatbed text-warning me-2"></i>My Recent Material Requests
                    </h6>
                    <a href="{{ route('material-requests.index') }}" class="btn btn-sm btn-outline-primary" style="min-height: 36px; display: inline-flex; align-items: center;">
                        All Requests <i class="fa-solid fa-arrow-right ms-1"></i>
                    </a>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive wc-table-mobile-cards">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Request #</th>
                                    <th>Project</th>
                                    <th>Date</th>
                                    <th>Status</th>
                                    <th class="text-end">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($recentMR as $mr)
                                <tr>
                                    <td data-label="Request #" class="fw-bold">#{{ $mr->id }}</td>
                                    <td data-label="Project">{{ $mr->project->name ?? 'N/A' }}</td>
                                    <td data-label="Date" class="small text-muted">{{ $mr->created_at->format('M d, Y') }}</td>
                                    <td data-label="Status">
                                        <x-status-badge :status="$mr->status" />
                                    </td>
                                    <td data-label="Action" class="text-end">
                                        <a href="{{ route('material-requests.show', $mr->id) }}" class="btn btn-sm btn-light border" title="View Request" style="min-width: 36px; min-height: 36px; display: inline-flex; align-items: center; justify-content: center;">
                                            <i class="fa-solid fa-eye"></i>
                                        </a>
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="5" class="text-center text-muted py-4">No recent material requests found.</td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        </div>

        {{-- Right Column (4 cols) --}}
        <div class="col-lg-4">

            {{-- Quick Site Actions --}}
            <div class="card border-0 shadow-sm rounded-3 mb-4 bg-white">
                <div class="card-header bg-white py-3 border-bottom">
                    <h6 class="m-0 fw-bold text-dark">
                        <i class="fa-solid fa-bolt text-warning me-2"></i>Quick Site Actions
                    </h6>
                </div>
                <div class="card-body d-flex flex-column gap-2 p-3">
                    <a href="{{ route('manpower-daily-report.create') }}" class="btn btn-primary text-start fw-semibold py-2 d-flex align-items-center" style="min-height: 44px;">
                        <i class="fa-solid fa-users-line me-2"></i> Submit Morning Manpower Report
                    </a>
                    <a href="{{ route('inventory.index') }}" class="btn btn-outline-primary text-start fw-semibold py-2 d-flex align-items-center" style="min-height: 44px;">
                        <i class="fa-solid fa-boxes-stacked me-2"></i> Site Store Inventory (Stock on Hand)
                    </a>
                    <a href="{{ route('inventory.all-movements') }}" class="btn btn-outline-info text-start fw-semibold py-2 d-flex align-items-center" style="min-height: 44px;">
                        <i class="fa-solid fa-arrow-right-arrow-left me-2"></i> Site Stock Movements
                    </a>
                    <a href="{{ route('material-requests.create', ['source' => 'Emergency']) }}" class="btn btn-danger text-start fw-semibold py-2 d-flex align-items-center" style="min-height: 44px;">
                        <i class="fa-solid fa-bolt me-2"></i> Ask Emergency Material Request
                    </a>
                    <a href="{{ route('eng-schedule.my') }}" class="btn btn-info text-dark text-start fw-semibold py-2 d-flex align-items-center" style="min-height: 44px;">
                        <i class="fa-solid fa-calendar-check me-2"></i> My Assigned Work Orders
                    </a>
                    <a href="{{ route('daily-reports.create') }}" class="btn btn-success text-start fw-semibold py-2 d-flex align-items-center" style="min-height: 44px;">
                        <i class="fa-solid fa-file-signature me-2"></i> Create Daily Site Log
                    </a>
                    <a href="{{ route('issues.create') }}" class="btn btn-outline-danger text-start fw-semibold py-2 d-flex align-items-center" style="min-height: 44px;">
                        <i class="fa-solid fa-triangle-exclamation me-2"></i> Report Site Issue / Obstacle
                    </a>
                    <a href="{{ route('attendance.index') }}" class="btn btn-outline-secondary text-start fw-semibold py-2 d-flex align-items-center" style="min-height: 44px;">
                        <i class="fa-solid fa-user-check me-2"></i> Mark Site Attendance
                    </a>
                    <a href="{{ route('manpower-daily-report.index') }}" class="btn btn-outline-primary text-start fw-semibold py-2 d-flex align-items-center" style="min-height: 44px;">
                        <i class="fa-solid fa-clock-rotate-left me-2"></i> My Manpower History
                    </a>
                </div>
            </div>

            {{-- Today's Manpower Breakdown Card (if submitted) --}}
            @if($todayManpowerReport)
            <div class="card border-0 shadow-sm rounded-3 mb-4 bg-white">
                <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                    <h6 class="m-0 fw-bold text-dark">
                        <i class="fa-solid fa-chart-pie text-success me-2"></i>Today's Site Workforce
                    </h6>
                    <span class="badge bg-success">{{ $todayManpowerReport->total_present }} Total</span>
                </div>
                <div class="card-body p-3">
                    <div class="d-flex justify-content-between align-items-center mb-2 small">
                        <span><i class="fa-solid fa-helmet-safety text-primary me-2"></i>Skilled Workers</span>
                        <strong class="badge bg-light text-dark border">{{ $todayManpowerReport->skilled_workers }}</strong>
                    </div>
                    <div class="d-flex justify-content-between align-items-center mb-2 small">
                        <span><i class="fa-solid fa-person-digging text-warning me-2"></i>Unskilled Workers</span>
                        <strong class="badge bg-light text-dark border">{{ $todayManpowerReport->unskilled_workers }}</strong>
                    </div>
                    <div class="d-flex justify-content-between align-items-center mb-2 small">
                        <span><i class="fa-solid fa-user-tie text-success me-2"></i>Supervisors</span>
                        <strong class="badge bg-light text-dark border">{{ $todayManpowerReport->supervisors }}</strong>
                    </div>
                    <div class="d-flex justify-content-between align-items-center mb-2 small">
                        <span><i class="fa-solid fa-screwdriver-wrench text-info me-2"></i>Engineers</span>
                        <strong class="badge bg-light text-dark border">{{ $todayManpowerReport->engineers }}</strong>
                    </div>
                    <div class="d-flex justify-content-between align-items-center mb-2 small">
                        <span><i class="fa-solid fa-truck-monster text-danger me-2"></i>Equipment Operators</span>
                        <strong class="badge bg-light text-dark border">{{ $todayManpowerReport->operators }}</strong>
                    </div>
                    <div class="d-flex justify-content-between align-items-center mb-2 small">
                        <span><i class="fa-solid fa-hammer text-secondary me-2"></i>Daily Laborers</span>
                        <strong class="badge bg-light text-dark border">{{ $todayManpowerReport->daily_laborers }}</strong>
                    </div>
                    <div class="d-flex justify-content-between align-items-center small">
                        <span><i class="fa-solid fa-handshake text-purple me-2" style="color: #8b5cf6;"></i>Subcontractor Workers</span>
                        <strong class="badge bg-light text-dark border">{{ $todayManpowerReport->subcontractor_workers }}</strong>
                    </div>
                </div>
            </div>
            @endif

            {{-- Recent Site Issues --}}
            <div class="card border-0 shadow-sm rounded-3 mb-4 bg-white">
                <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                    <h6 class="m-0 fw-bold text-dark">
                        <i class="fa-solid fa-triangle-exclamation text-danger me-2"></i>Site Issues & Delays
                    </h6>
                    <a href="{{ route('issues.index') }}" class="btn btn-sm btn-outline-danger" style="min-height: 36px; display: inline-flex; align-items: center;">All Issues</a>
                </div>
                <div class="card-body p-0">
                    <ul class="list-group list-group-flush">
                        @forelse($recentIssues as $issue)
                        <li class="list-group-item p-3">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <div class="fw-semibold small text-dark">{{ $issue->title ?? 'Site Issue #'.$issue->id }}</div>
                                    <small class="text-muted">{{ $issue->project->name ?? 'Site' }} &bull; {{ $issue->created_at->diffForHumans() }}</small>
                                </div>
                                <x-status-badge :status="$issue->status" :tone="$issue->status === 'open' ? 'danger' : 'neutral'" />
                            </div>
                        </li>
                        @empty
                        <li class="list-group-item p-3 text-center text-muted small">
                            <i class="fa-solid fa-circle-check text-success me-1"></i> No active site issues reported.
                        </li>
                        @endforelse
                    </ul>
                </div>
            </div>

        </div>
    </div>
</div>
@endsection
