@extends('layouts.app')

@section('title', 'Slip History & Archives')

@section('content')
@php
    $procurementSlipsCount = $allSlips->where('source_type', 'delivery_receipt')->count();
    $transferSlipsCount = $allSlips->where('source_type', 'transfer')->count();
    $unassignedSlipsCount = $allSlips->filter(fn($s) => empty($s['book_id']) || ($s['book_range'] ?? '') === 'Manual / Other')->count();
    $activeBooksCount = $sequences->where('status', 'active')->count();
    $completedBooksCount = $sequences->where('status', 'full')->count();
@endphp
<div class="container-fluid">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h4 class="mb-0 text-dark fw-bold">
                <i class="fas fa-history me-2 text-primary"></i>Slip History & Book Archives
            </h4>
            <small class="text-muted">
                Permanent, immutable transaction record of all GRN and SIN slips issued across all store sequence books
            </small>
        </div>
        <div class="d-flex align-items-center gap-2 flex-wrap">
            @if($unassignedSlipsCount > 0)
            <form action="{{ route('store-manager.slip-history.delete-unassigned') }}" method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete all {{ $unassignedSlipsCount }} slip(s) with NO sequence book added? This will remove orphaned/unassigned slip records.');">
                @csrf
                <button type="submit" class="btn btn-sm btn-outline-danger shadow-sm fw-semibold" title="Delete all slips with no sequence book assigned">
                    <i class="fas fa-trash-alt me-1"></i>Delete Slips with No Sequence ({{ $unassignedSlipsCount }})
                </button>
            </form>
            @endif
            <button type="button" class="btn btn-sm btn-outline-secondary shadow-sm" onclick="window.print()">
                <i class="fas fa-print me-1"></i>Print Report
            </button>
            <a href="{{ route('store-manager.slip-sequences.index') }}" class="btn btn-sm btn-secondary shadow-sm">
                <i class="fas fa-arrow-left me-1"></i>Slip Sequences
            </a>
            <a href="{{ route('store-manager.slip-sequences.create') }}" class="btn btn-sm btn-primary shadow-sm">
                <i class="fas fa-plus me-1"></i>Configure New Book
            </a>
        </div>
    </div>

    <!-- Permanent Guarantee Callout -->
    <div class="alert alert-info border-start border-4 mb-4 py-2 shadow-sm d-flex align-items-center justify-content-between flex-wrap gap-2">
        <div class="d-flex align-items-center">
            <i class="fas fa-shield-alt fa-2x text-primary me-3"></i>
            <div>
                <strong>Zero History Loss Policy:</strong> When a sequence book fills up and the next sequence is activated, 
                all previous slips remain permanently stored, linked, and searchable in this audit history. 
                Records are never deleted or overwritten.
            </div>
        </div>
        <span class="badge bg-primary fs-6 px-3 py-2 font-monospace">
            {{ $allSlips->count() }} Total Slips Preserved
        </span>
    </div>

    <!-- Metric Stat Cards -->
    <div class="row g-3 mb-4">
        <div class="col-xl-3 col-sm-6">
            <div class="card shadow-sm border-start border-4 border-primary h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <span class="text-muted small text-uppercase fw-bold">Total Slips Recorded</span>
                            <div class="fs-4 fw-bold text-primary mt-1">{{ $allSlips->count() }}</div>
                            <small class="text-muted">Across all stores & books</small>
                        </div>
                        <div class="bg-primary-subtle text-primary p-3 rounded-circle">
                            <i class="fas fa-receipt fa-lg"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-sm-6">
            <div class="card shadow-sm border-start border-4 border-success h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <span class="text-muted small text-uppercase fw-bold">Procurement GRN Slips</span>
                            <div class="fs-4 fw-bold text-success mt-1">{{ $procurementSlipsCount }}</div>
                            <small class="text-muted">Goods Received from Vendors</small>
                        </div>
                        <div class="bg-success-subtle text-success p-3 rounded-circle">
                            <i class="fas fa-box fa-lg"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-sm-6">
            <div class="card shadow-sm border-start border-4 h-100" style="border-left-color: #4f46e5 !important;">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <span class="text-muted small text-uppercase fw-bold">Store Transfer Slips</span>
                            <div class="fs-4 fw-bold mt-1" style="color: #4f46e5;">{{ $transferSlipsCount }}</div>
                            <small class="text-muted">Inbound & Outbound Transfers</small>
                        </div>
                        <div class="p-3 rounded-circle" style="background-color: #e0e7ff; color: #4338ca;">
                            <i class="fas fa-truck fa-lg"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-sm-6">
            <div class="card shadow-sm border-start border-4 border-info h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <span class="text-muted small text-uppercase fw-bold">Sequence Books</span>
                            <div class="fs-4 fw-bold text-info mt-1">{{ $sequences->count() }}</div>
                            <small class="text-muted">
                                <span class="text-success fw-bold">{{ $activeBooksCount }} active</span> &bull; 
                                <span class="text-danger fw-bold">{{ $completedBooksCount }} completed</span>
                            </small>
                        </div>
                        <div class="bg-info-subtle text-info p-3 rounded-circle">
                            <i class="fas fa-book-bookmark fa-lg"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Sequence Books Health & Reconciliation Cross-Check Section -->
    @if(isset($reconciliationReports['results']) && count($reconciliationReports['results']) > 0)
    <div class="card shadow-sm mb-4 border-0">
        <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
            <h6 class="mb-0 fw-bold text-dark">
                <i class="fas fa-stethoscope text-primary me-2"></i>Sequence Books Cross-Check & Continuity Audit
            </h6>
            <div class="d-flex align-items-center gap-2">
                @if(($reconciliationReports['problem_count'] ?? 0) === 0)
                    <span class="badge bg-success px-3 py-2"><i class="fas fa-check-circle me-1"></i>All Books OK & Continuous</span>
                @else
                    <span class="badge bg-danger px-3 py-2"><i class="fas fa-exclamation-triangle me-1"></i>{{ $reconciliationReports['problem_count'] }} Book(s) with Problems</span>
                @endif
                <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#reconcileCollapse" aria-expanded="false" aria-controls="reconcileCollapse">
                    <i class="fas fa-chevron-down"></i>
                </button>
            </div>
        </div>
        <div class="collapse {{ ($reconciliationReports['problem_count'] ?? 0) > 0 ? 'show' : '' }}" id="reconcileCollapse">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover table-sm align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-3">Store</th>
                                <th>Slip Type</th>
                                <th>Book Range</th>
                                <th>Next Slip</th>
                                <th>Used / Expected</th>
                                <th>Void Slips</th>
                                <th>Gaps</th>
                                <th>Duplicates</th>
                                <th class="text-end pe-3">Continuity Health</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($reconciliationReports['results'] as $rep)
                            <tr class="{{ $rep['status'] === 'problems' ? 'table-warning' : '' }}">
                                <td class="ps-3 fw-semibold">{{ $rep['store_name'] }}</td>
                                <td>
                                    @if($rep['slip_type'] === 'receive')
                                        <span class="badge bg-success">GRN</span>
                                    @else
                                        <span class="badge bg-info">SIN</span>
                                    @endif
                                </td>
                                <td class="font-monospace fw-bold">#{{ $rep['book_range'] }}</td>
                                <td class="font-monospace text-primary">#{{ $rep['current_slip_no'] }}</td>
                                <td>
                                    {{ $rep['actual_used_count'] }} / {{ $rep['expected_used_count'] }}
                                    @if(!$rep['used_count_matches'])
                                        <span class="badge bg-danger ms-1" title="Used count counter mismatch">Mismatch</span>
                                    @endif
                                </td>
                                <td>
                                    @if($rep['void_count'] > 0)
                                        <span class="badge bg-danger">{{ $rep['void_count'] }} void</span>
                                    @else
                                        <span class="text-muted">0</span>
                                    @endif
                                </td>
                                <td>
                                    @if($rep['gaps_count'] > 0)
                                        <span class="badge bg-warning text-dark fw-bold" title="{{ implode(', ', array_column($rep['gaps'], 'formatted')) }}">
                                            {{ $rep['gaps_count'] }} gap(s)
                                        </span>
                                    @else
                                        <span class="text-success"><i class="fas fa-check"></i></span>
                                    @endif
                                </td>
                                <td>
                                    @if($rep['duplicates_count'] > 0)
                                        <span class="badge bg-danger fw-bold" title="{{ implode(', ', array_column($rep['duplicates'], 'formatted')) }}">
                                            {{ $rep['duplicates_count'] }} duplicate(s)
                                        </span>
                                    @else
                                        <span class="text-success"><i class="fas fa-check"></i></span>
                                    @endif
                                </td>
                                <td class="text-end pe-3">
                                    @if($rep['status'] === 'ok')
                                        <span class="badge bg-success-subtle text-success border border-success px-2 py-1">
                                            <i class="fas fa-check-circle me-1"></i>OK
                                        </span>
                                    @else
                                        <span class="badge bg-danger px-2 py-1">
                                            <i class="fas fa-exclamation-circle me-1"></i>Problems
                                        </span>
                                    @endif
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    @endif

    <!-- Filter & Search Bar Card -->
    <div class="card shadow-sm mb-4">
        <div class="card-body p-3">
            <form method="GET" action="{{ route('store-manager.slip-history.index') }}" class="row g-2 align-items-end">
                <!-- Store Filter -->
                <div class="col-md-2 col-sm-6">
                    <label class="form-label small fw-bold text-muted text-uppercase mb-1">Store</label>
                    <select name="store_id" class="form-select form-select-sm">
                        <option value="">All Stores</option>
                        @foreach($stores as $st)
                        <option value="{{ $st->id }}" {{ (string)($filters['store_id'] ?? '') === (string)$st->id ? 'selected' : '' }}>
                            {{ $st->name }}
                        </option>
                        @endforeach
                    </select>
                </div>

                <!-- Slip Type Filter -->
                <div class="col-md-2 col-sm-6">
                    <label class="form-label small fw-bold text-muted text-uppercase mb-1">Slip Type</label>
                    <select name="slip_type" class="form-select form-select-sm">
                        <option value="">All Slip Types</option>
                        <option value="receive" {{ ($filters['slip_type'] ?? '') === 'receive' ? 'selected' : '' }}>Receiving (GRN)</option>
                        <option value="send" {{ ($filters['slip_type'] ?? '') === 'send' ? 'selected' : '' }}>Outgoing (SIN)</option>
                    </select>
                </div>

                <!-- Sequence Book Filter -->
                <div class="col-md-2 col-sm-6">
                    <label class="form-label small fw-bold text-muted text-uppercase mb-1">Sequence Book</label>
                    <select name="sequence_id" class="form-select form-select-sm">
                        <option value="">All Books</option>
                        <option value="none" {{ ($filters['sequence_id'] ?? '') === 'none' ? 'selected' : '' }}>No Sequence Added (Manual/Other)</option>
                        @foreach($sequences as $sq)
                        <option value="{{ $sq->id }}" {{ (string)($filters['sequence_id'] ?? '') === (string)$sq->id ? 'selected' : '' }}>
                            #{{ $sq->book_start_no }}–#{{ $sq->book_end_no }} ({{ $sq->store->name ?? 'Store' }})
                        </option>
                        @endforeach
                    </select>
                </div>

                <!-- Source Type Filter -->
                <div class="col-md-2 col-sm-6">
                    <label class="form-label small fw-bold text-muted text-uppercase mb-1">Source</label>
                    <select name="source_type" class="form-select form-select-sm">
                        <option value="">All Sources</option>
                        <option value="delivery_receipt" {{ ($filters['source_type'] ?? '') === 'delivery_receipt' ? 'selected' : '' }}>Procurement GRN</option>
                        <option value="transfer" {{ ($filters['source_type'] ?? '') === 'transfer' ? 'selected' : '' }}>Store Transfer</option>
                    </select>
                </div>

                <!-- Search Input -->
                <div class="col-md-2 col-sm-6">
                    <label class="form-label small fw-bold text-muted text-uppercase mb-1">Keywords</label>
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-light"><i class="fas fa-search text-muted"></i></span>
                        <input type="text" name="search" class="form-control form-control-sm" 
                               value="{{ $filters['search'] ?? '' }}" 
                               placeholder="Slip, PR, TR...">
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="col-md-2 col-sm-12 d-flex gap-2">
                    <button type="submit" class="btn btn-sm btn-primary w-100 fw-semibold">
                        <i class="fas fa-filter me-1"></i>Filter
                    </button>
                    <a href="{{ route('store-manager.slip-history.index') }}" class="btn btn-sm btn-outline-secondary" title="Reset Filters">
                        <i class="fas fa-redo"></i>
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Slips History Table Card -->
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div>
                <h6 class="m-0 fw-bold text-primary">
                    <i class="fas fa-list-check me-2"></i>Recorded Transaction Slips
                </h6>
                <small class="text-muted">Showing {{ $allSlips->count() }} slips matching your active filters</small>
            </div>
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <span class="badge bg-success">{{ $procurementSlipsCount }} Procurement</span>
                <span class="badge text-white" style="background-color: #4f46e5;">{{ $transferSlipsCount }} Transfers</span>
                @if($unassignedSlipsCount > 0)
                <span class="badge bg-warning text-dark"><i class="fas fa-exclamation-circle me-1"></i>{{ $unassignedSlipsCount }} No Sequence Added</span>
                @endif
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-hover table-striped mb-0 align-middle">
                <thead class="table-light">
                    <tr>
                        <th style="width: 140px;">Slip # & Book</th>
                        <th style="width: 130px;">Store</th>
                        <th style="width: 95px;">Source / Type</th>
                        <th style="min-width: 175px;">Linked Document</th>
                        <th style="min-width: 250px;">Purchase Request / Transfer Route</th>
                        <th style="min-width: 180px;">Supplier / Transfer Partner</th>
                        <th style="width: 125px;">Items</th>
                        <th style="width: 135px;">Handled By & Date</th>
                        <th style="width: 95px;">Status</th>
                        <th style="width: 110px;" class="text-end pe-3">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($allSlips as $slip)
                    <tr class="{{ $slip['is_void'] ? 'table-danger' : '' }}">
                        <!-- Slip # & Book Badge -->
                        <td>
                            <span class="badge bg-primary font-monospace fs-6">
                                #{{ $slip['slip_no'] }}
                            </span>
                            @if($slip['is_void'])
                            <br><span class="badge bg-danger mt-1">VOID</span>
                            @endif
                            <div class="mt-1">
                                @if(empty($slip['book_id']))
                                <span class="badge bg-warning text-dark border" style="font-size: 0.68rem;" title="No Sequence Book Assigned (Manual / Other)">
                                    <i class="fas fa-exclamation-circle me-1"></i>No Sequence Added
                                </span>
                                @else
                                <span class="badge {{ $slip['book_status'] === 'active' ? 'bg-success' : 'bg-secondary' }} text-white border" style="font-size: 0.68rem;" title="Sequence Book Range">
                                    <i class="fas fa-book me-1"></i>{{ $slip['book_range'] }}
                                </span>
                                @endif
                            </div>
                            <div class="small text-muted mt-1">Leaf: {{ $slip['numeric_no'] }}</div>
                        </td>

                        <!-- Store -->
                        <td>
                            <strong class="text-dark small">{{ $slip['store_name'] }}</strong>
                        </td>

                        <!-- Source / Type -->
                        <td>
                            @if($slip['source_type'] === 'transfer')
                                <div>
                                    <span class="badge text-white px-2 py-1" style="background-color: #4f46e5; font-size: 0.72rem;">
                                        <i class="fas fa-exchange-alt me-1"></i>TRANSFER
                                    </span>
                                </div>
                                @if($slip['slip_type'] === 'receive')
                                <span class="badge bg-success mt-1" title="Inbound Transfer Receipt"><i class="fas fa-arrow-down me-1"></i>TR-IN</span>
                                @else
                                <span class="badge bg-info mt-1" title="Outbound Transfer Dispatch"><i class="fas fa-arrow-up me-1"></i>TR-OUT</span>
                                @endif
                                @if(!empty($slip['transfer_role']))
                                <div class="small text-muted" style="font-size: 0.68rem;">{{ $slip['transfer_role'] }}</div>
                                @endif
                            @elseif($slip['source_type'] === 'delivery_receipt')
                                <div>
                                    <span class="badge bg-success px-2 py-1" style="font-size: 0.72rem;">
                                        <i class="fas fa-box me-1"></i>PROCURE
                                    </span>
                                </div>
                                <span class="badge bg-info mt-1"><i class="fas fa-arrow-down me-1"></i>GRN</span>
                            @else
                                <div>
                                    <span class="badge bg-secondary px-2 py-1" style="font-size: 0.72rem;">
                                        <i class="fas fa-warehouse me-1"></i>STOCK
                                    </span>
                                </div>
                            @endif
                        </td>

                        <!-- Linked Document -->
                        <td>
                            <div>
                                <a href="{{ $slip['document_url'] }}" class="fw-bold text-primary text-decoration-none" target="_blank">
                                    @if($slip['source_type'] === 'transfer')
                                        <i class="fas fa-truck-ramp-box me-1 text-primary"></i>{{ $slip['document_ref'] }}
                                    @else
                                        <i class="fas fa-file-invoice me-1"></i>{{ $slip['document_ref'] }}
                                    @endif
                                </a>
                            </div>
                            @if(!empty($slip['slip_file_url']))
                            <a href="{{ $slip['slip_file_url'] }}" target="_blank" class="badge bg-light text-primary border mt-1 text-decoration-none" style="font-size: 0.72rem;">
                                <i class="fas fa-paperclip me-1"></i>Attached Slip File
                            </a>
                            @endif
                        </td>

                        <!-- Linked Purchase Request / Store Transfer -->
                        <td>
                            @if($slip['source_type'] === 'transfer')
                                <div class="p-2 rounded border" style="background: #f8fafc;">
                                    <div class="d-flex align-items-center justify-content-between gap-1 flex-wrap">
                                        <a href="{{ $slip['document_url'] }}" target="_blank" class="fw-bold text-dark text-decoration-none" style="font-size: 0.82rem;">
                                            <i class="fas fa-truck me-1" style="color: #4f46e5;"></i>{{ $slip['transfer_no'] ?: $slip['document_ref'] }}
                                        </a>
                                        @if(!empty($slip['transfer_status']))
                                            <span class="badge bg-light text-dark border" style="font-size: 0.68rem;">{{ ucfirst($slip['transfer_status']) }}</span>
                                        @endif
                                    </div>
                                    <div class="small mt-1 text-truncate" style="max-width: 250px;">
                                        <span class="text-secondary fw-semibold">{{ $slip['from_store_name'] ?? 'Origin Store' }}</span>
                                        <i class="fas fa-arrow-right text-muted mx-1" style="font-size: 0.7rem;"></i>
                                        <span class="text-primary fw-semibold">{{ $slip['to_store_name'] ?? 'Destination Store' }}</span>
                                    </div>
                                    @if(!empty($slip['driver_name']))
                                    <div class="text-muted mt-1" style="font-size: 0.72rem;">
                                        <i class="fas fa-id-badge me-1 text-secondary"></i>Driver: <strong>{{ $slip['driver_name'] }}</strong>
                                        @if(!empty($slip['vehicle_plate_no'])) &bull; {{ $slip['vehicle_plate_no'] }} @endif
                                    </div>
                                    @endif
                                    @if(!empty($slip['pr_no']))
                                    <div class="mt-1">
                                        <a href="{{ $slip['pr_url'] }}" class="badge bg-warning text-dark border text-decoration-none" target="_blank" style="font-size: 0.7rem;">
                                            <i class="fas fa-shopping-cart me-1"></i>Linked PR #{{ $slip['pr_no'] }}
                                        </a>
                                    </div>
                                    @endif
                                </div>
                            @elseif($slip['purchase_request_id'])
                                <div>
                                    <a href="{{ $slip['pr_url'] }}" class="fw-bold text-dark text-decoration-none" target="_blank">
                                        <i class="fas fa-shopping-cart text-secondary me-1"></i>PR #{{ $slip['pr_no'] ?: $slip['purchase_request_id'] }}
                                    </a>
                                </div>
                                @if($slip['pr_title'])
                                <small class="text-muted d-block text-truncate" style="max-width: 250px;">{{ $slip['pr_title'] }}</small>
                                @endif
                                @if($slip['project_name'] && $slip['project_name'] !== 'N/A')
                                <span class="badge bg-light text-dark border mt-1" style="font-size: 0.72rem;">
                                    <i class="fas fa-project-diagram text-secondary me-1"></i>{{ $slip['project_name'] }}
                                </span>
                                @endif
                            @else
                                <span class="text-muted small">&ndash; Direct / Internal &ndash;</span>
                                @if($slip['project_name'] && $slip['project_name'] !== 'N/A')
                                <div><span class="badge bg-light text-dark border mt-1" style="font-size: 0.72rem;">{{ $slip['project_name'] }}</span></div>
                                @endif
                            @endif
                        </td>

                        <!-- Supplier / Route -->
                        <td>
                            @if($slip['source_type'] === 'transfer')
                                <div class="fw-semibold text-dark small">
                                    <i class="fas fa-route me-1" style="color: #4f46e5;"></i>{{ $slip['from_store_name'] ?? 'Origin' }} ➔ {{ $slip['to_store_name'] ?? 'Destination' }}
                                </div>
                                @if(!empty($slip['notes']))
                                <small class="text-muted d-block text-truncate mt-1" style="max-width: 200px;" title="{{ $slip['notes'] }}">
                                    <i class="fas fa-comment-dots text-secondary me-1"></i>{{ $slip['notes'] }}
                                </small>
                                @endif
                            @else
                                <div class="fw-semibold text-dark text-truncate" style="max-width: 180px;" title="{{ $slip['supplier_name'] }}">
                                    {{ $slip['supplier_name'] }}
                                </div>
                                @if($slip['purchase_order_ref'])
                                <small class="text-muted font-monospace d-block">PO: {{ $slip['purchase_order_ref'] }}</small>
                                @endif
                            @endif
                        </td>

                        <!-- Items -->
                        <td>
                            <span class="badge bg-secondary mb-1">
                                {{ $slip['items_count'] }} {{ \Illuminate\Support\Str::plural('item', $slip['items_count']) }}
                            </span>
                            @if(!empty($slip['items']) && count($slip['items']) > 0)
                            <div class="small text-muted text-truncate" style="max-width: 140px;" title="{{ collect($slip['items'])->pluck('name')->implode(', ') }}">
                                {{ $slip['items'][0]['name'] }}
                                @if($slip['items'][0]['quantity'])
                                ({{ $slip['items'][0]['quantity'] }} {{ $slip['items'][0]['unit'] }})
                                @endif
                            </div>
                            @if(count($slip['items']) > 1)
                            <button type="button" class="btn btn-link btn-sm p-0 text-primary small text-decoration-none" 
                                    data-bs-toggle="modal" data-bs-target="#histItemsModal-{{ $slip['numeric_no'] }}">
                                +{{ count($slip['items']) - 1 }} more
                            </button>
                            @endif
                            @endif
                        </td>

                        <!-- Handled By & Date -->
                        <td>
                            <div class="small fw-semibold text-dark">{{ $slip['handled_by'] }}</div>
                            <small class="text-muted d-block">
                                {{ $slip['date'] ? \Carbon\Carbon::parse($slip['date'])->format('M d, Y') : '-' }}
                            </small>
                        </td>

                        <!-- Status -->
                        <td>
                            @if($slip['is_void'])
                            <span class="badge bg-danger">Void</span>
                            @elseif($slip['status'] === 'verified' || $slip['status'] === 'approved' || $slip['status'] === 'completed')
                            <span class="badge bg-success">{{ ucfirst($slip['status']) }}</span>
                            @elseif($slip['status'] === 'in_transit')
                            <span class="badge bg-warning text-dark"><i class="fas fa-truck me-1"></i>In Transit</span>
                            @elseif($slip['status'] === 'draft')
                            <span class="badge bg-secondary">Draft</span>
                            @else
                            <span class="badge bg-warning text-dark">{{ ucfirst($slip['status']) }}</span>
                            @endif
                        </td>

                        <!-- Actions -->
                        <td class="text-end pe-3">
                            <div class="d-flex justify-content-end gap-1">
                                <a href="{{ $slip['document_url'] }}" class="btn btn-sm btn-outline-primary" target="_blank" title="View Document">
                                    <i class="fas fa-eye"></i>
                                </a>
                                @if(!empty($slip['book_id']))
                                <a href="{{ route('store-manager.slip-sequences.edit', $slip['book_id']) }}" class="btn btn-sm btn-outline-secondary" target="_blank" title="Open Sequence Book">
                                    <i class="fas fa-book"></i>
                                </a>
                                @endif
                                <form action="{{ route('store-manager.slip-history.destroy') }}" method="POST" class="d-inline" onsubmit="return confirm('Delete this slip (#{{ $slip['slip_no'] }}) from slip history?');">
                                    @csrf
                                    @method('DELETE')
                                    <input type="hidden" name="source_type" value="{{ $slip['source_type'] }}">
                                    <input type="hidden" name="document_id" value="{{ $slip['document_id'] }}">
                                    <input type="hidden" name="slip_no" value="{{ $slip['slip_no'] }}">
                                    <input type="hidden" name="numeric_no" value="{{ $slip['numeric_no'] }}">
                                    <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete Slip Record">
                                        <i class="fas fa-trash-alt"></i>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="10" class="text-center py-5 text-muted">
                            <i class="fas fa-inbox fa-3x mb-3 text-muted d-block"></i>
                            No slip history matches your selected filter criteria.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Item Modals -->
@foreach($allSlips as $slip)
    @if($slip['items_count'] > 0)
    <div class="modal fade" id="histItemsModal-{{ $slip['numeric_no'] }}" tabindex="-1" aria-labelledby="histItemsModalLabel-{{ $slip['numeric_no'] }}" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content shadow">
                <div class="modal-header py-2" style="{{ $slip['source_type'] === 'transfer' ? 'background: linear-gradient(135deg, #1e1b4b 0%, #312e81 100%); color: #fff;' : '' }}">
                    <h6 class="modal-title fw-bold mb-0 {{ $slip['source_type'] === 'transfer' ? 'text-white' : '' }}" id="histItemsModalLabel-{{ $slip['numeric_no'] }}">
                        @if($slip['source_type'] === 'transfer')
                            <i class="fas fa-truck me-2 text-warning"></i>Transfer Items on Slip #{{ $slip['slip_no'] }}
                        @else
                            <i class="fas fa-boxes me-2 text-primary"></i>Items on Slip #{{ $slip['slip_no'] }}
                        @endif
                    </h6>
                    <button type="button" class="btn-close {{ $slip['source_type'] === 'transfer' ? 'btn-close-white' : '' }}" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-0">
                    <div class="p-3 bg-light border-bottom small">
                        <div><strong>Document:</strong> {{ $slip['document_ref'] }}</div>
                        <div><strong>Store:</strong> {{ $slip['store_name'] }} &bull; <strong>Book Range:</strong> {{ $slip['book_range'] }}</div>
                        @if($slip['source_type'] === 'transfer')
                            <div><strong>Route:</strong> {{ $slip['from_store_name'] ?? 'Origin' }} <i class="fas fa-arrow-right text-muted mx-1"></i> {{ $slip['to_store_name'] ?? 'Destination' }}</div>
                            @if(!empty($slip['driver_name']))
                            <div><strong>Driver:</strong> {{ $slip['driver_name'] }} @if(!empty($slip['vehicle_plate_no'])) ({{ $slip['vehicle_plate_no'] }}) @endif</div>
                            @endif
                        @else
                            @if($slip['pr_no'])
                            <div><strong>PR:</strong> #{{ $slip['pr_no'] }} &bull; <strong>Project:</strong> {{ $slip['project_name'] }}</div>
                            @endif
                            <div><strong>Supplier / Source:</strong> {{ $slip['supplier_name'] }}</div>
                        @endif
                        <div><strong>Handled By:</strong> {{ $slip['handled_by'] }} &bull; <strong>Date:</strong> {{ $slip['date'] ? \Carbon\Carbon::parse($slip['date'])->format('M d, Y') : '-' }}</div>
                    </div>
                    <table class="table table-sm table-striped mb-0">
                        <thead class="table-light small">
                            <tr>
                                <th class="ps-3">Item / Material</th>
                                <th class="text-end">Quantity</th>
                                <th class="text-end pe-3">Unit</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($slip['items'] as $it)
                            <tr>
                                <td class="ps-3">{{ $it['name'] }}</td>
                                <td class="text-end font-monospace">{{ number_format($it['quantity'], 2) }}</td>
                                <td class="text-end pe-3 text-muted">{{ $it['unit'] ?: 'units' }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                    @if($slip['notes'])
                    <div class="p-3 bg-light small border-top text-muted">
                        <strong>Notes:</strong> {{ $slip['notes'] }}
                    </div>
                    @endif
                </div>
                <div class="modal-footer py-2">
                    <a href="{{ $slip['document_url'] }}" class="btn btn-primary btn-sm" target="_blank">
                        <i class="fas fa-external-link-alt me-1"></i>Open Document
                    </a>
                    @if(!empty($slip['slip_file_url']))
                    <a href="{{ $slip['slip_file_url'] }}" class="btn btn-outline-secondary btn-sm" target="_blank">
                        <i class="fas fa-paperclip me-1"></i>Attached File
                    </a>
                    @endif
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
    @endif
@endforeach
@endsection
