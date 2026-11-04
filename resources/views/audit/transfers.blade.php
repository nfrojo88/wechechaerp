@extends('layouts.app')
@section('title', 'Material Transfers Audit')

@section('content')
<div class="wc-container py-3 py-md-4">

    <!-- Page Header -->
    <x-page-header
        title="Inter-Store Material Transfers Audit"
        subtitle="Oversight of warehouse-to-site shipments, driver custody, in-transit transit duration alerts, and destination intake discrepancies"
        badge="Audit &bull; Strictly Read-Only"
    >
        <x-slot name="actions">
            <div class="d-flex gap-2 flex-wrap align-items-center">
                <a href="{{ request()->fullUrlWithQuery(['export' => 'csv']) }}" class="btn btn-outline-success btn-sm rounded-pill px-3 shadow-xs fw-semibold" style="min-height: 38px; display: inline-flex; align-items: center;">
                    <i class="fa-solid fa-file-excel me-1"></i> Export to Excel / CSV
                </a>
                <button type="button" class="btn btn-warning text-dark btn-sm rounded-pill px-3 shadow-xs fw-bold" style="min-height: 38px; display: inline-flex; align-items: center;" data-bs-toggle="modal" data-bs-target="#raiseFindingModal" data-source-type="transfer">
                    <i class="fa-solid fa-plus-circle me-1"></i> Raise Audit Finding
                </button>
            </div>
        </x-slot>
    </x-page-header>

    <!-- Audit Navigation Pills -->
    <div class="card border-0 shadow-sm rounded-3 mb-4 bg-white">
        <div class="card-body p-2.5">
            <ul class="nav nav-pills gap-1 flex-wrap">
                <li class="nav-item">
                    <a class="nav-link rounded-pill fw-semibold px-3 py-1.5 text-dark" href="{{ route('audit.inventory') }}">
                        <i class="fa-solid fa-warehouse me-1 text-info"></i> Inventory Overview
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link rounded-pill fw-semibold px-3 py-1.5 text-dark" href="{{ route('audit.stock-movements') }}">
                        <i class="fa-solid fa-arrows-split-up-and-left me-1 text-success"></i> Movement Ledger
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link rounded-pill fw-semibold px-3 py-1.5 text-dark" href="{{ route('audit.daily-usage') }}">
                        <i class="fa-solid fa-clipboard-list me-1 text-primary"></i> Daily Usage
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link active rounded-pill fw-semibold px-3 py-1.5" href="{{ route('audit.transfers') }}">
                        <i class="fa-solid fa-truck-moving me-1 text-warning"></i> Material Transfers
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link rounded-pill fw-semibold px-3 py-1.5 text-dark" href="{{ route('audit.fulfillment') }}">
                        <i class="fa-solid fa-boxes-packing me-1 text-danger"></i> Intake Fulfillment
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link rounded-pill fw-semibold px-3 py-1.5 text-dark" href="{{ route('audit.findings.index') }}">
                        <i class="fa-solid fa-clipboard-question me-1 text-secondary"></i> Findings &amp; Queries
                    </a>
                </li>
            </ul>
        </div>
    </div>

    <!-- Top KPI Row -->
    <div class="row g-3 mb-4">
        <div class="col-12 col-sm-6 col-xl">
            <x-kpi-card
                title="Total Transfers"
                :value="number_format($totalTransfers) . ' Shipments'"
                subtitle="All logged transfer dispatches"
                icon="fa-solid fa-truck-moving"
                tone="primary"
            />
        </div>
        <div class="col-12 col-sm-6 col-xl">
            <x-kpi-card
                title="Slips Documented"
                :value="number_format($withSlipsCount ?? 0) . ' / ' . number_format($totalTransfers)"
                :subtitle="(number_format($withAttachedFilesCount ?? 0)) . ' with Scanned Slip Files'"
                icon="fa-solid fa-receipt"
                tone="success"
            />
        </div>
        <div class="col-12 col-sm-6 col-xl">
            <x-kpi-card
                title="Active In-Transit"
                :value="number_format($inTransitCount) . ' En Route'"
                subtitle="Dispatched and currently en route"
                icon="fa-solid fa-truck-fast"
                tone="info"
            />
        </div>
        <div class="col-12 col-sm-6 col-xl">
            <x-kpi-card
                title="🚨 In-Transit &gt; 48 Hours"
                :value="$over48HoursCount . ' Overdue'"
                subtitle="High audit risk: potential loss or diversion"
                icon="fa-solid fa-truck-clock"
                :tone="$over48HoursCount > 0 ? 'danger' : 'success'"
            />
        </div>
        <div class="col-12 col-sm-6 col-xl">
            <x-kpi-card
                title="Completed Shipments"
                :value="number_format($completedCount) . ' Delivered'"
                subtitle="Verified destination intake"
                icon="fa-solid fa-circle-check"
                tone="success"
            />
        </div>
    </div>

    <!-- Filter Bar -->
    <div class="card border-0 shadow-sm rounded-3 mb-4 bg-white">
        <div class="card-body p-3">
            <form method="GET" action="{{ route('audit.transfers') }}" class="row g-2 align-items-end">
                <div class="col-md-3">
                    <label class="form-label small fw-bold text-muted mb-1">SEARCH</label>
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-light"><i class="fa-solid fa-magnifying-glass text-muted"></i></span>
                        <input type="text" name="search" class="form-control form-control-sm" placeholder="Transfer #, Slip #, Plate..." value="{{ request('search') }}">
                    </div>
                </div>

                <div class="col-md-2">
                    <label class="form-label small fw-bold text-muted mb-1">ORIGIN (FROM)</label>
                    <select name="from_store_id" class="form-select form-select-sm">
                        <option value="">All Origins</option>
                        @foreach($stores as $st)
                            <option value="{{ $st->id }}" {{ request('from_store_id') == $st->id ? 'selected' : '' }}>
                                {{ $st->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-2">
                    <label class="form-label small fw-bold text-muted mb-1">DESTINATION (TO)</label>
                    <select name="to_store_id" class="form-select form-select-sm">
                        <option value="">All Destinations</option>
                        @foreach($stores as $st)
                            <option value="{{ $st->id }}" {{ request('to_store_id') == $st->id ? 'selected' : '' }}>
                                {{ $st->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-2">
                    <label class="form-label small fw-bold text-muted mb-1">SLIP AUDIT FILTER</label>
                    <select name="slip_filter" class="form-select form-select-sm">
                        <option value="">All Slip Records</option>
                        <option value="has_outgoing" {{ request('slip_filter') == 'has_outgoing' ? 'selected' : '' }}>Has Outgoing Slip #</option>
                        <option value="missing_outgoing" {{ request('slip_filter') == 'missing_outgoing' ? 'selected' : '' }}>⚠️ Missing Outgoing Slip #</option>
                        <option value="has_receiving" {{ request('slip_filter') == 'has_receiving' ? 'selected' : '' }}>Has Receiving Slip #</option>
                        <option value="missing_receiving" {{ request('slip_filter') == 'missing_receiving' ? 'selected' : '' }}>⚠️ Missing Receiving Slip #</option>
                        <option value="has_file" {{ request('slip_filter') == 'has_file' ? 'selected' : '' }}>Has Attached Slip Scan / File</option>
                        <option value="missing_file" {{ request('slip_filter') == 'missing_file' ? 'selected' : '' }}>Missing Scanned Slip File</option>
                    </select>
                </div>

                <div class="col-md-2 d-flex gap-1">
                    <div class="w-50">
                        <label class="form-label small fw-bold text-muted mb-1">DATE FROM</label>
                        <input type="date" name="date_from" class="form-control form-control-sm" value="{{ request('date_from') }}">
                    </div>
                    <div class="w-50">
                        <label class="form-label small fw-bold text-muted mb-1">DATE TO</label>
                        <input type="date" name="date_to" class="form-control form-control-sm" value="{{ request('date_to') }}">
                    </div>
                </div>

                <div class="col-md-1 d-flex gap-1">
                    <button type="submit" class="btn btn-primary btn-sm rounded-pill w-100 fw-semibold" style="height: 31px;">
                        <i class="fa-solid fa-filter me-1"></i> Filter
                    </button>
                    @if(request()->hasAny(['search', 'from_store_id', 'to_store_id', 'flag', 'slip_filter', 'date_from', 'date_to']))
                        <a href="{{ route('audit.transfers') }}" class="btn btn-light btn-sm border rounded-pill" title="Reset Filters" style="height: 31px;">
                            <i class="fa-solid fa-rotate-left"></i>
                        </a>
                    @endif
                </div>
            </form>
        </div>
    </div>

    <!-- Transfers Table -->
    <div class="card border-0 shadow-sm rounded-3 bg-white mb-4">
        <div class="card-header bg-white border-bottom py-3 px-3 px-md-4 d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div>
                <h6 class="fw-bold mb-0 text-dark">
                    <i class="fa-solid fa-truck-moving text-warning me-2"></i>Material Transfers Audit Ledger
                </h6>
                <small class="text-muted">Showing {{ $transfers->firstItem() ?? 0 }} to {{ $transfers->lastItem() ?? 0 }} of {{ $transfers->total() }} recorded transfers</small>
            </div>
            <span class="badge bg-light text-dark border font-monospace px-3 py-1.5 rounded-pill">
                Page {{ $transfers->currentPage() }} of {{ $transfers->lastPage() }}
            </span>
        </div>

        <div class="card-body p-0">
            <div class="table-responsive wc-table-mobile-cards">
                <table class="table table-hover align-middle mb-0 small">
                    <thead class="bg-light text-muted text-uppercase" style="font-size: 0.75rem;">
                        <tr>
                            <th class="ps-4 py-2.5">Transfer #</th>
                            <th class="py-2.5" style="min-width: 220px;">Physical Slips (Dispatched &amp; Received)</th>
                            <th class="py-2.5">Route (From &rarr; To)</th>
                            <th class="py-2.5">Dispatched / Received</th>
                            <th class="py-2.5">Driver &amp; Vehicle</th>
                            <th class="py-2.5 text-center">Sent / Received Qty</th>
                            <th class="py-2.5 text-center">Audit Flag</th>
                            <th class="pe-4 py-2.5 text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($transfers as $tr)
                            @php
                                $outgoingSlip = $tr->outgoing_slip_no ?: $tr->physical_slip_no;
                                $sent = (float) $tr->items->sum('sent_quantity');
                                $recv = (float) $tr->items->sum('received_quantity');
                                $diff = $recv - $sent;

                                $isOver48h = ($tr->status === \App\Models\Transfer::STATUS_IN_TRANSIT)
                                    && $tr->dispatched_at
                                    && $tr->dispatched_at->lte(now()->subHours(48));

                                $hasShortage = ($tr->status === \App\Models\Transfer::STATUS_COMPLETED) && ($diff < 0);
                                $hasExcess = ($tr->status === \App\Models\Transfer::STATUS_COMPLETED) && ($diff > 0);
                            @endphp
                            <tr class="{{ $isOver48h ? 'table-danger bg-opacity-10' : ($hasShortage ? 'table-warning bg-opacity-10' : '') }}">
                                <td data-label="Transfer #" class="ps-4 py-3">
                                    <a href="javascript:void(0)" class="fw-bold font-monospace text-primary text-decoration-none d-block fs-6" data-bs-toggle="modal" data-bs-target="#transferDetailModal-{{ $tr->id }}" title="Inspect transfer slips and materials">
                                        <i class="fa-solid fa-truck me-1"></i>{{ $tr->transfer_no }}
                                    </a>
                                    <span class="badge bg-light text-dark border font-monospace mt-1" style="font-size: 0.68rem;">
                                        {{ $tr->items->count() }} line items
                                    </span>
                                </td>

                                <td data-label="Physical Slips" class="py-3">
                                    <!-- Outgoing Physical Slip / Waybill -->
                                    <div class="d-flex align-items-center gap-1.5 mb-1.5 flex-wrap">
                                        <span class="badge bg-primary bg-opacity-10 text-primary border border-primary-subtle font-monospace py-1 px-2" style="font-size: 0.74rem;" title="Origin Store Dispatch Physical Slip">
                                            <i class="fa-solid fa-arrow-up-from-bracket me-1 text-primary"></i>Out: <strong>{{ $outgoingSlip ? '#' . $outgoingSlip : 'Not Recorded' }}</strong>
                                        </span>
                                        @if($tr->outgoing_slip_url)
                                            <a href="{{ $tr->outgoing_slip_url }}" target="_blank" class="btn btn-xs btn-outline-primary py-0 px-1.5 rounded fw-semibold text-decoration-none shadow-xs" style="font-size: 0.68rem;" title="Open Attached Outgoing Paper Slip Scan / PDF">
                                                <i class="fa-solid fa-file-arrow-down me-1"></i>Slip File
                                            </a>
                                        @elseif($outgoingSlip)
                                            <span class="badge bg-light text-muted border py-0.5 px-1.5" style="font-size: 0.65rem;" title="Slip number recorded, but no scanned image was attached">
                                                <i class="fa-solid fa-paperclip me-1 text-muted"></i>No file
                                            </span>
                                        @else
                                            <span class="badge bg-danger bg-opacity-10 text-danger border border-danger-subtle py-0.5 px-1.5" style="font-size: 0.65rem;" title="No outgoing dispatch slip was assigned">
                                                <i class="fa-solid fa-triangle-exclamation me-1"></i>Missing Out Slip
                                            </span>
                                        @endif
                                    </div>

                                    <!-- Receiving Intake Slip / GRN -->
                                    <div class="d-flex align-items-center gap-1.5 flex-wrap">
                                        @if($tr->receiving_slip_no)
                                            <span class="badge bg-success bg-opacity-10 text-success border border-success-subtle font-monospace py-1 px-2" style="font-size: 0.74rem;" title="Destination Store Receiving Intake GRN Slip">
                                                <i class="fa-solid fa-arrow-down-to-bracket me-1 text-success"></i>In: <strong>#{{ $tr->receiving_slip_no }}</strong>
                                            </span>
                                            @if($tr->receiving_slip_url)
                                                <a href="{{ $tr->receiving_slip_url }}" target="_blank" class="btn btn-xs btn-outline-success py-0 px-1.5 rounded fw-semibold text-decoration-none shadow-xs" style="font-size: 0.68rem;" title="Open Attached Signed Receiving Document Scan / PDF">
                                                    <i class="fa-solid fa-file-arrow-down me-1"></i>Slip File
                                                </a>
                                            @else
                                                <span class="badge bg-light text-muted border py-0.5 px-1.5" style="font-size: 0.65rem;">
                                                    <i class="fa-solid fa-paperclip me-1 text-muted"></i>No file
                                                </span>
                                            @endif
                                        @elseif($tr->status === \App\Models\Transfer::STATUS_COMPLETED)
                                            <span class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary-subtle py-0.5 px-1.5" style="font-size: 0.68rem;" title="Completed without specific GRN slip #">
                                                <i class="fa-solid fa-check me-1"></i>Verified (No In Slip #)
                                            </span>
                                        @else
                                            <span class="badge bg-light text-muted border py-0.5 px-1.5" style="font-size: 0.68rem;" title="Transfer still en-route or pending receipt intake">
                                                <i class="fa-solid fa-clock me-1 text-secondary"></i>Awaiting Receipt Slip
                                            </span>
                                        @endif
                                    </div>
                                </td>

                                <td data-label="Route" class="py-3">
                                    <div class="fw-bold text-dark d-flex align-items-center gap-1">
                                        <i class="fa-solid fa-warehouse text-primary small"></i>
                                        <span>{{ $tr->fromStore?->name ?? 'Origin Store' }}</span>
                                    </div>
                                    <div class="text-muted small d-flex align-items-center gap-1 mt-0.5">
                                        <i class="fa-solid fa-arrow-right text-secondary small"></i>
                                        <i class="fa-solid fa-location-dot text-success small"></i>
                                        <span class="fw-semibold text-dark">{{ $tr->toStore?->name ?? 'Destination Store' }}</span>
                                    </div>
                                </td>

                                <td data-label="Dates" class="py-3">
                                    <span class="d-block text-dark fw-semibold">
                                        <i class="fa-solid fa-arrow-up-from-bracket text-warning me-1"></i>{{ $tr->dispatched_at ? $tr->dispatched_at->format('M d, Y H:i') : 'Not Dispatched' }}
                                    </span>
                                    <small class="text-muted">
                                        <i class="fa-solid fa-arrow-down-to-bracket text-success me-1"></i>{{ $tr->received_at ? $tr->received_at->format('M d, Y H:i') : 'Pending Receipt' }}
                                    </small>
                                </td>

                                <td data-label="Driver" class="py-3">
                                    <span class="d-block text-dark fw-semibold font-monospace">{{ $tr->vehicle_plate_no ?: 'No Plate' }}</span>
                                    <small class="text-muted">{{ $tr->driver_employee_id ?: 'Awaiting driver' }}</small>
                                </td>

                                <td data-label="Quantities" class="py-3 text-center font-monospace font-tabular">
                                    <span class="d-block fw-bold text-dark">{{ number_format($sent, 2) }} / {{ number_format($recv, 2) }}</span>
                                    @if($diff != 0 && $tr->status === \App\Models\Transfer::STATUS_COMPLETED)
                                        <small class="{{ $diff < 0 ? 'text-danger' : 'text-success' }} fw-bold">
                                            ({{ $diff < 0 ? '-' : '+' }}{{ number_format(abs($diff), 2) }})
                                        </small>
                                    @endif
                                </td>

                                <td data-label="Audit Flag" class="py-3 text-center">
                                    @if($isOver48h)
                                        <span class="badge bg-danger rounded-pill px-2.5 py-1 font-monospace">
                                            <i class="fa-solid fa-truck-clock me-1"></i>IN TRANSIT &gt; 48H
                                        </span>
                                    @elseif($hasShortage)
                                        <span class="badge bg-danger bg-opacity-25 text-danger border border-danger-subtle rounded-pill px-2.5 py-1 font-monospace">
                                            SHORTAGE ({{ number_format(abs($diff), 2) }})
                                        </span>
                                    @elseif($hasExcess)
                                        <span class="badge bg-info bg-opacity-25 text-info border border-info-subtle rounded-pill px-2.5 py-1 font-monospace">
                                            EXCESS (+{{ number_format($diff, 2) }})
                                        </span>
                                    @elseif($tr->status === \App\Models\Transfer::STATUS_COMPLETED)
                                        <span class="badge bg-success bg-opacity-25 text-success border border-success-subtle rounded-pill px-2.5 py-1">
                                            Verified Received
                                        </span>
                                    @else
                                        <span class="badge bg-secondary rounded-pill px-2.5 py-1">
                                            {{ ucfirst(str_replace('_', ' ', $tr->status)) }}
                                        </span>
                                    @endif
                                </td>

                                <td data-label="Action" class="pe-4 py-3 text-end">
                                    <div class="d-inline-flex gap-1.5">
                                        <button type="button" class="btn btn-sm btn-outline-primary rounded-pill px-2.5 shadow-xs fw-semibold" style="min-height: 30px; font-size: 0.75rem;"
                                            data-bs-toggle="modal"
                                            data-bs-target="#transferDetailModal-{{ $tr->id }}"
                                            title="Inspect transfer slips, attached paper scans, and line items">
                                            <i class="fa-solid fa-receipt me-1"></i>Slips &amp; Items
                                        </button>
                                        <button type="button" class="btn btn-sm btn-outline-warning text-dark rounded-pill px-2.5 shadow-xs fw-semibold" style="min-height: 30px; font-size: 0.75rem;"
                                            data-bs-toggle="modal"
                                            data-bs-target="#raiseFindingModal"
                                            data-source-type="transfer"
                                            data-source-id="{{ $tr->id }}"
                                            data-store-id="{{ $tr->from_store_id }}"
                                            data-title="Query on Transfer {{ $tr->transfer_no }} ({{ $tr->fromStore?->name }} to {{ $tr->toStore?->name }})"
                                            data-severity="{{ $isOver48h ? 'critical' : ($hasShortage ? 'high' : 'medium') }}"
                                            data-observation="{{ $isOver48h ? 'Transfer ' . $tr->transfer_no . ' was dispatched on ' . ($tr->dispatched_at ? $tr->dispatched_at->format('Y-m-d H:i') : '') . ' and has been in transit for over 48 hours without receipt. Driver: ' . ($tr->driver_employee_id ?? 'N/A') . ', Vehicle: ' . ($tr->vehicle_plate_no ?? 'N/A') . '. Outgoing Slip: ' . ($outgoingSlip ?: 'Missing') . '. Urgent investigation required.' : ($hasShortage ? 'Shortage of ' . abs($diff) . ' units detected upon destination store receipt. Outgoing Slip #' . ($outgoingSlip ?: 'None') . ', Receiving Slip #' . ($tr->receiving_slip_no ?: 'None') . '.' : 'Audit query regarding transfer ' . $tr->transfer_no . ' (Slip #' . ($outgoingSlip ?: 'None') . ')') }}"
                                        >
                                            <i class="fa-solid fa-clipboard-question me-1"></i> Query
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center py-5 text-muted">
                                    <i class="fa-solid fa-truck-moving fa-2x mb-2 d-block text-secondary"></i>
                                    No material transfers recorded matching your filters.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($transfers->hasPages())
                <div class="p-3 border-top d-flex justify-content-center">
                    {{ $transfers->links() }}
                </div>
            @endif
        </div>
    </div>

</div>

<!-- Transfer Details & Physical Slips Inspection Modals -->
@foreach($transfers as $tr)
    @php
        $outgoingSlip = $tr->outgoing_slip_no ?: $tr->physical_slip_no;
        $sent = (float) $tr->items->sum('sent_quantity');
        $recv = (float) $tr->items->sum('received_quantity');
        $diff = $recv - $sent;
        $isOver48h = ($tr->status === \App\Models\Transfer::STATUS_IN_TRANSIT) && $tr->dispatched_at && $tr->dispatched_at->lte(now()->subHours(48));
    @endphp
    <div class="modal fade" id="transferDetailModal-{{ $tr->id }}" tabindex="-1" aria-labelledby="transferDetailModalLabel-{{ $tr->id }}" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content border-0 shadow-lg rounded-3 overflow-hidden">
                <div class="modal-header py-3 px-4 bg-dark text-white border-0">
                    <div>
                        <h6 class="modal-title fw-bold mb-0" id="transferDetailModalLabel-{{ $tr->id }}">
                            <i class="fa-solid fa-truck-moving me-2 text-warning"></i>Transfer Audit &amp; Physical Slips: <span class="font-monospace text-warning">{{ $tr->transfer_no }}</span>
                        </h6>
                        <small class="text-white-50">
                            {{ $tr->fromStore?->name }} &rarr; {{ $tr->toStore?->name }} &bull; Status: <span class="badge bg-secondary">{{ ucfirst(str_replace('_', ' ', $tr->status)) }}</span>
                        </small>
                    </div>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body p-4 bg-light">
                    <!-- Physical Slips Comparison Box -->
                    <div class="row g-3 mb-4">
                        <!-- Outgoing Slip Card -->
                        <div class="col-md-6">
                            <div class="card h-100 border border-primary-subtle shadow-xs rounded-3 overflow-hidden">
                                <div class="card-header bg-primary bg-opacity-10 border-bottom border-primary-subtle py-2.5 px-3">
                                    <span class="fw-bold text-primary small d-flex align-items-center justify-content-between">
                                        <span><i class="fa-solid fa-arrow-up-from-bracket me-1.5"></i>Origin Dispatch Physical Slip</span>
                                        <span class="badge bg-primary font-monospace">{{ $outgoingSlip ? '#' . $outgoingSlip : 'Not Recorded' }}</span>
                                    </span>
                                </div>
                                <div class="card-body p-3 bg-white">
                                    <div class="mb-2">
                                        <small class="text-muted d-block" style="font-size: 0.72rem;">ORIGIN STORE</small>
                                        <strong class="text-dark small">{{ $tr->fromStore?->name ?? 'Origin Store' }}</strong>
                                    </div>
                                    <div class="mb-2">
                                        <small class="text-muted d-block" style="font-size: 0.72rem;">DISPATCHED BY &amp; TIME</small>
                                        <span class="text-dark small">
                                            {{ $tr->dispatchedBy?->name ?? 'Store Keeper' }} &bull; {{ $tr->dispatched_at ? $tr->dispatched_at->format('M d, Y H:i') : 'Pending Dispatch' }}
                                        </span>
                                    </div>
                                    <div class="mt-3 pt-2 border-top">
                                        <small class="text-muted fw-bold d-block mb-1" style="font-size: 0.72rem;">ATTACHED DISPATCH PAPER SLIP SCAN</small>
                                        @if($tr->outgoing_slip_url)
                                            <div class="d-flex align-items-center justify-content-between bg-light p-2 rounded border">
                                                <span class="small text-truncate font-monospace" style="max-width: 180px;">
                                                    <i class="fa-solid fa-file-image me-1 text-primary"></i>Slip File Attached
                                                </span>
                                                <a href="{{ $tr->outgoing_slip_url }}" target="_blank" class="btn btn-xs btn-primary fw-semibold px-2">
                                                    <i class="fa-solid fa-arrow-up-right-from-square me-1"></i>View Document
                                                </a>
                                            </div>
                                        @else
                                            <div class="alert alert-warning py-1.5 px-2 small mb-0 d-flex align-items-center gap-1.5 border-warning-subtle">
                                                <i class="fa-solid fa-triangle-exclamation text-warning"></i>
                                                <span style="font-size: 0.73rem;">No scanned physical paper slip was attached at dispatch.</span>
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Receiving Slip Card -->
                        <div class="col-md-6">
                            <div class="card h-100 border border-success-subtle shadow-xs rounded-3 overflow-hidden">
                                <div class="card-header bg-success bg-opacity-10 border-bottom border-success-subtle py-2.5 px-3">
                                    <span class="fw-bold text-success small d-flex align-items-center justify-content-between">
                                        <span><i class="fa-solid fa-arrow-down-to-bracket me-1.5"></i>Destination Receiving Slip / GRN</span>
                                        <span class="badge bg-success font-monospace">{{ $tr->receiving_slip_no ? '#' . $tr->receiving_slip_no : 'Pending / None' }}</span>
                                    </span>
                                </div>
                                <div class="card-body p-3 bg-white">
                                    <div class="mb-2">
                                        <small class="text-muted d-block" style="font-size: 0.72rem;">DESTINATION STORE</small>
                                        <strong class="text-dark small">{{ $tr->toStore?->name ?? 'Destination Store' }}</strong>
                                    </div>
                                    <div class="mb-2">
                                        <small class="text-muted d-block" style="font-size: 0.72rem;">RECEIVED BY &amp; TIME</small>
                                        <span class="text-dark small">
                                            {{ $tr->receivedBy?->name ?? 'Store Keeper' }} &bull; {{ $tr->received_at ? $tr->received_at->format('M d, Y H:i') : 'Pending Intake' }}
                                        </span>
                                    </div>
                                    @if($tr->receiving_notes)
                                        <div class="mb-2">
                                            <small class="text-muted d-block" style="font-size: 0.72rem;">INTAKE REMARKS</small>
                                            <span class="text-dark small fst-italic">"{{ $tr->receiving_notes }}"</span>
                                        </div>
                                    @endif
                                    <div class="mt-3 pt-2 border-top">
                                        <small class="text-muted fw-bold d-block mb-1" style="font-size: 0.72rem;">ATTACHED SIGNED RECEIVING DOCUMENT</small>
                                        @if($tr->receiving_slip_url)
                                            <div class="d-flex align-items-center justify-content-between bg-light p-2 rounded border">
                                                <span class="small text-truncate font-monospace" style="max-width: 180px;">
                                                    <i class="fa-solid fa-file-image me-1 text-success"></i>Signed Slip Attached
                                                </span>
                                                <a href="{{ $tr->receiving_slip_url }}" target="_blank" class="btn btn-xs btn-success fw-semibold px-2">
                                                    <i class="fa-solid fa-arrow-up-right-from-square me-1"></i>View Document
                                                </a>
                                            </div>
                                        @else
                                            <div class="alert alert-light text-muted border py-1.5 px-2 small mb-0 d-flex align-items-center gap-1.5">
                                                <i class="fa-solid fa-info-circle text-muted"></i>
                                                <span style="font-size: 0.73rem;">No signed receiving document uploaded yet.</span>
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Vehicle & Custody Summary -->
                    <div class="bg-white p-3 rounded-3 border mb-4">
                        <h6 class="fw-bold small text-uppercase text-muted mb-2"><i class="fa-solid fa-truck text-secondary me-1.5"></i>Logistics &amp; Custody Details</h6>
                        <div class="row g-2 small">
                            <div class="col-sm-4">
                                <span class="text-muted d-block">Vehicle Plate:</span>
                                <strong class="text-dark font-monospace">{{ $tr->vehicle_plate_no ?: 'Not recorded' }}</strong>
                            </div>
                            <div class="col-sm-4">
                                <span class="text-muted d-block">Driver ID / Custody:</span>
                                <strong class="text-dark">{{ $tr->driver_employee_id ?: 'Awaiting assignment' }}</strong>
                            </div>
                            <div class="col-sm-4">
                                <span class="text-muted d-block">Transit Duration:</span>
                                @if($tr->dispatched_at && $tr->received_at)
                                    <strong class="text-success">{{ $tr->dispatched_at->diffForHumans($tr->received_at, true) }}</strong>
                                @elseif($tr->dispatched_at)
                                    <strong class="{{ $isOver48h ? 'text-danger' : 'text-primary' }}">{{ $tr->dispatched_at->diffForHumans(now(), true) }} (in transit)</strong>
                                @else
                                    <span class="text-muted">Not dispatched</span>
                                @endif
                            </div>
                        </div>
                    </div>

                    <!-- Transferred Items Table -->
                    <div class="bg-white rounded-3 border overflow-hidden">
                        <div class="p-3 bg-light border-bottom d-flex justify-content-between align-items-center">
                            <h6 class="fw-bold small text-uppercase text-dark mb-0">
                                <i class="fa-solid fa-boxes-packing me-1.5 text-primary"></i>Materials Transferred ({{ $tr->items->count() }} items)
                            </h6>
                            <span class="badge bg-white text-dark border font-monospace">
                                Sent: {{ number_format($sent, 2) }} | Recv: {{ number_format($recv, 2) }}
                            </span>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-sm table-hover align-middle mb-0 small">
                                <thead class="table-light text-muted text-uppercase" style="font-size: 0.72rem;">
                                    <tr>
                                        <th class="ps-3 py-2">Material / Product</th>
                                        <th class="text-center py-2">Sent Qty</th>
                                        <th class="text-center py-2">Received Qty</th>
                                        <th class="text-center py-2">Discrepancy</th>
                                        <th class="pe-3 text-end py-2">Unit</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($tr->items as $item)
                                        @php
                                            $iSent = (float) $item->sent_quantity;
                                            $iRecv = (float) $item->received_quantity;
                                            $iDiff = $iRecv - $iSent;
                                        @endphp
                                        <tr>
                                            <td class="ps-3 py-2">
                                                <strong class="text-dark d-block">{{ $item->product?->name ?? 'Material Item' }}</strong>
                                                <small class="text-muted font-monospace">{{ $item->product?->code ?? $item->product?->sku ?? '' }}</small>
                                            </td>
                                            <td class="text-center py-2 font-monospace">{{ number_format($iSent, 2) }}</td>
                                            <td class="text-center py-2 font-monospace">{{ number_format($iRecv, 2) }}</td>
                                            <td class="text-center py-2 font-monospace">
                                                @if($tr->status === \App\Models\Transfer::STATUS_COMPLETED && $iDiff != 0)
                                                    <span class="badge {{ $iDiff < 0 ? 'bg-danger' : 'bg-info' }}">
                                                        {{ $iDiff < 0 ? '-' : '+' }}{{ number_format(abs($iDiff), 2) }}
                                                    </span>
                                                @elseif($tr->status === \App\Models\Transfer::STATUS_COMPLETED)
                                                    <span class="badge bg-success bg-opacity-25 text-success border border-success-subtle">Match (0)</span>
                                                @else
                                                    <span class="text-muted">—</span>
                                                @endif
                                            </td>
                                            <td class="pe-3 text-end py-2 text-muted">{{ $item->unit ?? $item->product?->unit ?? 'pcs' }}</td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="5" class="text-center py-3 text-muted">No line items in this transfer.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="modal-footer py-2.5 px-4 bg-light border-top d-flex justify-content-between align-items-center">
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-sm btn-warning text-dark fw-bold"
                        data-bs-toggle="modal"
                        data-bs-target="#raiseFindingModal"
                        data-source-type="transfer"
                        data-source-id="{{ $tr->id }}"
                        data-store-id="{{ $tr->from_store_id }}"
                        data-title="Query on Transfer {{ $tr->transfer_no }} ({{ $tr->fromStore?->name }} to {{ $tr->toStore?->name }})"
                        data-severity="{{ $isOver48h ? 'critical' : ($diff < 0 ? 'high' : 'medium') }}"
                        data-observation="Audit query on Transfer {{ $tr->transfer_no }}. Outgoing Slip #{{ $outgoingSlip ?: 'Missing' }}, Receiving Slip #{{ $tr->receiving_slip_no ?: 'Missing' }}. Variance: {{ $diff }} units."
                    >
                        <i class="fa-solid fa-clipboard-question me-1"></i> Raise Audit Finding on this Transfer
                    </button>
                </div>
            </div>
        </div>
    </div>
@endforeach

@include('audit._raise_finding_modal')
@endsection
