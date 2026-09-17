@extends('layouts.app')

@section('title', 'Edit Slip Sequence - ' . $slipSequence->label)

@section('content')
<div class="container-fluid py-3">
    <!-- Breadcrumb & Header -->
    <div class="row mb-3 align-items-center">
        <div class="col-md-8">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-1 small">
                    <li class="breadcrumb-item"><a href="{{ route('store-manager.dashboard') }}">Store Manager</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('store-manager.slip-sequences.index') }}">Slip Sequences</a></li>
                    <li class="breadcrumb-item active" aria-current="page">{{ $slipSequence->label }} ({{ $slipSequence->store->name }})</li>
                </ol>
            </nav>
            <h4 class="mb-0 text-dark fw-bold">
                <i class="fas fa-book-open text-primary me-2"></i>Slip Sequence Book: {{ $slipSequence->label }}
            </h4>
        </div>
        <div class="col-md-4 text-md-end mt-2 mt-md-0">
            <a href="{{ route('store-manager.slip-sequences.index') }}" class="btn btn-outline-secondary btn-sm rounded-pill px-3">
                <i class="fas fa-arrow-left me-1"></i>Back to Sequences
            </a>
        </div>
    </div>

    <!-- Top Cards: Config Form + Status Sidebar -->
    <div class="row g-3">
        <div class="col-lg-8">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header bg-white py-3 border-bottom">
                    <h6 class="mb-0 fw-bold text-dark">
                        <i class="fas fa-sliders-h text-primary me-2"></i>Sequence Configuration
                    </h6>
                </div>
                <div class="card-body">
                    <form id="sequenceUpdateForm" action="{{ route('store-manager.slip-sequences.update', $slipSequence) }}" method="POST">
                        @csrf
                        @method('PUT')

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label text-muted small text-uppercase fw-semibold mb-1">Store</label>
                                <div class="fs-6 fw-bold text-dark">{{ $slipSequence->store->name }}</div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label text-muted small text-uppercase fw-semibold mb-1">Type</label>
                                <div>
                                    @if($slipSequence->slip_type === 'receive')
                                    <span class="badge bg-success-subtle text-success border border-success-subtle px-3 py-2 rounded-pill fs-7">
                                        <i class="fas fa-arrow-down me-1"></i>GRN (Goods Receiving)
                                    </span>
                                    @else
                                    <span class="badge bg-info-subtle text-info border border-info-subtle px-3 py-2 rounded-pill fs-7">
                                        <i class="fas fa-arrow-up me-1"></i>SIN (Store Issue Note)
                                    </span>
                                    @endif
                                </div>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Label *</label>
                                <input type="text" name="label" class="form-control" 
                                       value="{{ old('label', $slipSequence->label) }}" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Prefix</label>
                                <input type="text" name="prefix" class="form-control font-monospace" 
                                       value="{{ old('prefix', $slipSequence->prefix) }}" maxlength="50"
                                       placeholder="e.g. REC, SIN, or empty for numeric">
                            </div>
                        </div>

                        <hr class="my-3">
                        <h6 class="fw-bold text-secondary mb-3">
                            <i class="fas fa-info-circle me-1"></i>Book Information (Read-Only)
                        </h6>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label text-muted small text-uppercase fw-semibold mb-1">Book Range</label>
                                <div class="fs-5 fw-bold text-dark font-monospace">
                                    {{ $slipSequence->book_start_no }} &ndash; {{ $slipSequence->book_end_no }}
                                </div>
                                <small class="text-muted">Total capacity: <strong>{{ $slipSequence->book_end_no - $slipSequence->book_start_no + 1 }}</strong> slips</small>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label text-muted small text-uppercase fw-semibold mb-1">Next Available Slip</label>
                                <div>
                                    <span class="badge bg-primary fs-6 px-3 py-2 rounded-pill font-monospace">
                                        #{{ $slipSequence->getNextSlipNumber() }}
                                    </span>
                                </div>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label text-muted small text-uppercase fw-semibold mb-1">Usage Progress</label>
                                <div>
                                    <strong class="fs-6">{{ $slipSequence->used_count }}</strong> / {{ $slipSequence->book_end_no - $slipSequence->book_start_no + 1 }} used
                                    <div class="progress mt-2" style="height: 8px;">
                                        <div class="progress-bar {{ $slipSequence->getPercentageUsed() > 85 ? 'bg-danger' : 'bg-primary' }}" 
                                             role="progressbar" 
                                             style="width: {{ min(100, $slipSequence->getPercentageUsed()) }}%;"
                                             aria-valuenow="{{ $slipSequence->getPercentageUsed() }}" 
                                             aria-valuemin="0" aria-valuemax="100"></div>
                                    </div>
                                    <small class="text-muted">{{ $slipSequence->getPercentageUsed() }}% used &bull; {{ $slipSequence->getRemainingSlips() }} remaining</small>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label text-muted small text-uppercase fw-semibold mb-1">Status</label>
                                <div>
                                    @if($slipSequence->status === 'active')
                                    <span class="badge bg-success px-3 py-2 rounded-pill"><i class="fas fa-check me-1"></i>Active</span>
                                    @elseif($slipSequence->status === 'full')
                                    <span class="badge bg-danger px-3 py-2 rounded-pill"><i class="fas fa-exclamation me-1"></i>Full</span>
                                    @else
                                    <span class="badge bg-secondary px-3 py-2 rounded-pill">Inactive</span>
                                    @endif
                                </div>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-12">
                                <label class="form-label fw-semibold">Notes</label>
                                <textarea name="notes" class="form-control" rows="2" placeholder="Optional notes regarding this physical book sequence...">{{ old('notes', $slipSequence->notes) }}</textarea>
                            </div>
                        </div>

                        <hr class="my-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                @if($slipSequence->status === 'active')
                                <button type="submit" form="deactivateForm" class="btn btn-outline-warning btn-sm">
                                    <i class="fas fa-pause me-1"></i>Deactivate
                                </button>
                                @elseif($slipSequence->status !== 'full')
                                <button type="submit" form="reactivateForm" class="btn btn-outline-success btn-sm">
                                    <i class="fas fa-play me-1"></i>Reactivate
                                </button>
                                @endif
                            </div>
                            <div class="d-flex gap-2">
                                <a href="{{ route('store-manager.slip-sequences.index') }}" class="btn btn-secondary btn-sm px-3">Cancel</a>
                                <button type="submit" form="sequenceUpdateForm" class="btn btn-primary btn-sm px-4">
                                    <i class="fas fa-save me-1"></i>Save Changes
                                </button>
                            </div>
                        </div>
                    </form>

                    <!-- Separate forms for deactivate & reactivate to prevent HTML nested form conflicts -->
                    @if($slipSequence->status === 'active')
                    <form id="deactivateForm" action="{{ route('store-manager.slip-sequences.deactivate', $slipSequence) }}" method="POST" class="d-none">
                        @csrf
                    </form>
                    @elseif($slipSequence->status !== 'full')
                    <form id="reactivateForm" action="{{ route('store-manager.slip-sequences.reactivate', $slipSequence) }}" method="POST" class="d-none">
                        @csrf
                    </form>
                    @endif
                </div>
            </div>
        </div>

        <!-- Sequence Status Card -->
        <div class="col-lg-4">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header bg-white py-3 border-bottom">
                    <h6 class="mb-0 fw-bold text-dark">
                        <i class="fas fa-clipboard-check text-primary me-2"></i>Sequence Status
                    </h6>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <div class="text-muted small fw-semibold text-uppercase mb-1">Current Status</div>
                        @if($slipSequence->status === 'active')
                        <div class="badge bg-success-subtle text-success border border-success-subtle p-2 text-wrap text-start w-100 fs-7">
                            <i class="fas fa-check-circle me-1"></i>Active &ndash; Slips will be assigned from this sequence
                        </div>
                        @elseif($slipSequence->status === 'full')
                        <div class="badge bg-danger-subtle text-danger border border-danger-subtle p-2 text-wrap text-start w-100 fs-7">
                            <i class="fas fa-times-circle me-1"></i>Full &ndash; All slips in this book have been used
                        </div>
                        @else
                        <div class="badge bg-secondary-subtle text-secondary border border-secondary-subtle p-2 text-wrap text-start w-100 fs-7">
                            <i class="fas fa-pause-circle me-1"></i>Inactive &ndash; Slips will not be assigned from this sequence
                        </div>
                        @endif
                    </div>

                    <div class="p-3 bg-light rounded-3 mb-3 border">
                        <div class="text-muted small fw-semibold text-uppercase">Next Slip to Assign</div>
                        <div class="fs-4 fw-bold text-primary font-monospace">
                            #{{ $slipSequence->getNextSlipNumber() }}
                        </div>
                        <small class="text-muted">Will be automatically allocated on next store transaction</small>
                    </div>

                    <div class="p-3 bg-light rounded-3 mb-3 border">
                        <div class="text-muted small fw-semibold text-uppercase">Remaining Slips</div>
                        <div class="fs-4 fw-bold text-dark font-monospace">
                            {{ $slipSequence->getRemainingSlips() }}
                        </div>
                        <small class="text-muted">Unissued leaves in range {{ $slipSequence->book_start_no }} &ndash; {{ $slipSequence->book_end_no }}</small>
                    </div>

                    <div class="p-3 bg-light rounded-3 border">
                        <div class="text-muted small fw-semibold text-uppercase">Assigned & Linked</div>
                        <div class="fs-4 fw-bold text-success font-monospace">
                            {{ $assignedSlips->count() }}
                        </div>
                        <small class="text-muted">Recorded transactions linked to this book</small>
                    </div>

                    @if($slipSequence->getRemainingSlips() < 10 && $slipSequence->getRemainingSlips() > 0)
                    <div class="alert alert-warning border-0 shadow-xs mt-3 mb-0 py-2">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <strong>Low on slips!</strong> Only {{ $slipSequence->getRemainingSlips() }} left in this book.
                    </div>
                    @elseif($slipSequence->getRemainingSlips() <= 0)
                    <div class="alert alert-danger border-0 shadow-xs mt-3 mb-0 py-2">
                        <i class="fas fa-ban me-2"></i>
                        <strong>Book Exhausted!</strong> Please configure the next sequential book.
                    </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <!-- Visual Book Leaf Range Map (Interactive Matrix) -->
    <div class="row mt-4">
        <div class="col-12">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div>
                        <h6 class="mb-0 fw-bold text-dark">
                            <i class="fas fa-th text-primary me-2"></i>Physical Book Range Visualizer ({{ $slipSequence->book_start_no }} &ndash; {{ $slipSequence->book_end_no }})
                        </h6>
                        <small class="text-muted">Click any assigned slip to filter or view linked record details below</small>
                    </div>
                    <div class="d-flex align-items-center gap-2 flex-wrap">
                        <span class="badge bg-success-subtle text-success border px-2 py-1 small">
                            <i class="fas fa-square text-success me-1"></i>Assigned & Linked ({{ $assignedSlips->count() }})
                        </span>
                        <span class="badge bg-primary-subtle text-primary border px-2 py-1 small">
                            <i class="fas fa-square text-primary me-1"></i>Next to Assign (#{{ $slipSequence->getNextSlipNumber() }})
                        </span>
                        <span class="badge bg-light text-muted border px-2 py-1 small">
                            <i class="fas fa-square text-secondary me-1"></i>Available ({{ $slipSequence->getRemainingSlips() }})
                        </span>
                        @php
                            $unrecordedCount = collect($bookMap)->where('status', 'unrecorded')->count();
                        @endphp
                        @if($unrecordedCount > 0)
                        <span class="badge bg-warning-subtle text-warning border px-2 py-1 small">
                            <i class="fas fa-square text-warning me-1"></i>Unrecorded / Gap ({{ $unrecordedCount }})
                        </span>
                        @endif
                    </div>
                </div>
                <div class="card-body">
                    <div class="d-flex flex-wrap gap-2" style="max-height: 220px; overflow-y: auto;">
                        @foreach($bookMap as $leaf)
                            @if($leaf['status'] === 'assigned')
                                @php $assigned = $leaf['assigned_data']; @endphp
                                <button type="button" 
                                        class="btn btn-sm btn-success px-2 py-1 font-monospace fw-semibold rounded-2 slip-filter-pill"
                                        style="font-size: 0.8rem;"
                                        data-slip-no="{{ $leaf['number'] }}"
                                        data-bs-toggle="tooltip"
                                        data-bs-html="true"
                                        title="<strong>Slip #{{ $leaf['formatted'] }}</strong><br>Linked to: {{ $assigned['document_ref'] ?? 'Receipt' }}<br>PR: {{ $assigned['pr_no'] ?? 'N/A' }}<br>Supplier: {{ $assigned['supplier_name'] ?? 'Store' }}<br>Status: {{ ucfirst($assigned['status'] ?? 'verified') }}"
                                        onclick="highlightSlipInTable('{{ $leaf['number'] }}')">
                                    <i class="fas fa-check me-1" style="font-size: 0.7rem;"></i>{{ $leaf['formatted'] }}
                                </button>
                            @elseif($leaf['status'] === 'next')
                                <button type="button" 
                                        class="btn btn-sm btn-primary px-2 py-1 font-monospace fw-bold rounded-2 position-relative slip-filter-pill"
                                        style="font-size: 0.8rem; box-shadow: 0 0 8px rgba(13, 110, 253, 0.5);"
                                        data-bs-toggle="tooltip"
                                        title="Next Slip to be assigned (#{{ $leaf['formatted'] }})">
                                    <i class="fas fa-arrow-right me-1"></i>{{ $leaf['formatted'] }}
                                </button>
                            @elseif($leaf['status'] === 'unrecorded')
                                <button type="button" 
                                        class="btn btn-sm btn-outline-warning text-dark px-2 py-1 font-monospace rounded-2 slip-filter-pill"
                                        style="font-size: 0.8rem; background-color: #fff9e6;"
                                        data-slip-no="{{ $leaf['number'] }}"
                                        data-bs-toggle="tooltip"
                                        title="Slip #{{ $leaf['formatted'] }}: Passed or unrecorded gap"
                                        onclick="highlightSlipInTable('{{ $leaf['number'] }}')">
                                    {{ $leaf['formatted'] }}
                                </button>
                            @else
                                <span class="btn btn-sm btn-light border text-muted px-2 py-1 font-monospace rounded-2"
                                      style="font-size: 0.8rem;"
                                      data-bs-toggle="tooltip"
                                      title="Slip #{{ $leaf['formatted'] }}: Available in book">
                                    {{ $leaf['formatted'] }}
                                </span>
                            @endif
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Assigned Slips & Linked Records Detailed Table -->
    <div class="row mt-4" id="assignedSlipsSection">
        <div class="col-12">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-3">
                    <div>
                        <h5 class="mb-1 text-dark fw-bold">
                            <i class="fas fa-receipt text-primary me-2"></i>Assigned Slips & Linked Transactions
                        </h5>
                        <p class="text-muted mb-0 small">
                            Detailed audit of all slips issued from Book Range <strong>{{ $slipSequence->book_start_no }} &ndash; {{ $slipSequence->book_end_no }}</strong> and which document, purchase request, and items they link with.
                        </p>
                    </div>
                    <div class="d-flex align-items-center gap-2 flex-wrap">
                        <div class="input-group input-group-sm" style="width: 260px;">
                            <span class="input-group-text bg-light border-end-0"><i class="fas fa-search text-muted"></i></span>
                            <input type="text" id="slipSearchInput" class="form-control border-start-0" placeholder="Filter slip, PR, supplier, item...">
                        </div>
                        <button type="button" id="resetFilterBtn" class="btn btn-outline-secondary btn-sm" onclick="resetSlipTableFilter()">
                            <i class="fas fa-undo me-1"></i>Reset
                        </button>
                    </div>
                </div>

                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0" id="assignedSlipsTable">
                            <thead class="table-light text-uppercase small" style="letter-spacing: 0.5px;">
                                <tr>
                                    <th class="ps-3">Slip #</th>
                                    <th>Type</th>
                                    <th>Linked Document</th>
                                    <th>Linked Purchase Request (PR)</th>
                                    <th>Supplier / Source</th>
                                    <th>Items / Materials</th>
                                    <th>Handled By & Date</th>
                                    <th>Status</th>
                                    <th class="text-end pe-3">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($assignedSlips as $slip)
                                <tr class="slip-row {{ $slip['is_void'] ? 'table-danger' : '' }}" 
                                    id="slip-row-{{ $slip['numeric_no'] }}"
                                    data-slip-no="{{ $slip['slip_no'] }}"
                                    data-numeric-no="{{ $slip['numeric_no'] }}">
                                    <!-- Slip # -->
                                    <td class="ps-3 font-monospace">
                                        <div class="d-flex align-items-center gap-2">
                                            <span class="badge bg-primary px-2 py-1 fs-6 font-monospace">
                                                #{{ $slip['slip_no'] }}
                                            </span>
                                            @if($slip['is_void'])
                                            <span class="badge bg-danger" title="Marked as Void">VOID</span>
                                            @endif
                                        </div>
                                        <small class="text-muted">Leaf: {{ $slip['numeric_no'] }}</small>
                                    </td>

                                    <!-- Slip Type -->
                                    <td>
                                        @if($slip['slip_type'] === 'receive')
                                        <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-2 py-1">
                                            <i class="fas fa-arrow-down me-1"></i>GRN
                                        </span>
                                        @else
                                        <span class="badge bg-info-subtle text-info border border-info-subtle rounded-pill px-2 py-1">
                                            <i class="fas fa-arrow-up me-1"></i>SIN
                                        </span>
                                        @endif
                                    </td>

                                    <!-- Linked Document (DR or Transfer) -->
                                    <td>
                                        <div>
                                            <a href="{{ $slip['document_url'] }}" class="fw-bold text-decoration-none text-primary" target="_blank">
                                                <i class="fas fa-file-invoice text-primary me-1"></i>{{ $slip['document_ref'] }}
                                            </a>
                                        </div>
                                        <small class="text-muted">
                                            Store: <strong>{{ $slip['store_name'] }}</strong>
                                        </small>
                                    </td>

                                    <!-- Linked Purchase Request -->
                                    <td>
                                        @if($slip['purchase_request_id'])
                                        <div>
                                            <a href="{{ $slip['pr_url'] }}" class="fw-bold text-decoration-none text-dark" target="_blank">
                                                <i class="fas fa-shopping-cart text-secondary me-1"></i>PR #{{ $slip['pr_no'] ?: $slip['purchase_request_id'] }}
                                            </a>
                                        </div>
                                        <div class="small text-muted text-truncate" style="max-width: 220px;" title="{{ $slip['pr_title'] }}">
                                            {{ $slip['pr_title'] ?: 'Material Requisition' }}
                                        </div>
                                        <div>
                                            <span class="badge bg-light text-secondary border px-2 py-0" style="font-size: 0.75rem;">
                                                <i class="fas fa-project-diagram me-1"></i>{{ $slip['project_name'] }}
                                            </span>
                                        </div>
                                        @else
                                        <span class="text-muted small">
                                            <i class="fas fa-minus me-1"></i>Direct / Transfer
                                        </span>
                                        @if($slip['project_name'] && $slip['project_name'] !== 'N/A')
                                        <div>
                                            <span class="badge bg-light text-secondary border px-2 py-0" style="font-size: 0.75rem;">
                                                {{ $slip['project_name'] }}
                                            </span>
                                        </div>
                                        @endif
                                        @endif
                                    </td>

                                    <!-- Supplier / Source -->
                                    <td>
                                        <div class="fw-semibold text-dark text-truncate" style="max-width: 200px;" title="{{ $slip['supplier_name'] }}">
                                            <i class="fas fa-truck text-muted me-1"></i>{{ $slip['supplier_name'] }}
                                        </div>
                                        @if($slip['purchase_order_ref'])
                                        <small class="text-muted font-monospace">PO: {{ $slip['purchase_order_ref'] }}</small>
                                        @endif
                                    </td>

                                    <!-- Items / Materials -->
                                    <td>
                                        <div class="d-flex align-items-center gap-1">
                                            <span class="badge bg-secondary-subtle text-dark border rounded-pill px-2">
                                                {{ $slip['items_count'] }} {{ \Illuminate\Support\Str::plural('item', $slip['items_count']) }}
                                            </span>
                                            @if($slip['items_count'] > 0)
                                            <button type="button" class="btn btn-link btn-sm p-0 text-decoration-none" 
                                                    data-bs-toggle="modal" 
                                                    data-bs-target="#itemsModal-{{ $slip['numeric_no'] }}"
                                                    title="View items received under this slip">
                                                <i class="fas fa-list-ul text-primary"></i>
                                            </button>
                                            @endif
                                        </div>
                                        @if(!empty($slip['items']) && count($slip['items']) > 0)
                                        <div class="small text-muted text-truncate mt-1" style="max-width: 220px;" title="{{ collect($slip['items'])->pluck('name')->implode(', ') }}">
                                            {{ $slip['items'][0]['name'] }} ({{ $slip['items'][0]['quantity'] }} {{ $slip['items'][0]['unit'] }})
                                            @if(count($slip['items']) > 1)
                                            <span class="text-secondary">+{{ count($slip['items']) - 1 }} more</span>
                                            @endif
                                        </div>
                                        @endif
                                    </td>

                                    <!-- Handled By & Date -->
                                    <td>
                                        <div class="small text-dark fw-semibold">
                                            <i class="fas fa-user-check text-muted me-1"></i>{{ $slip['handled_by'] }}
                                        </div>
                                        <small class="text-muted">
                                            {{ $slip['date'] ? \Carbon\Carbon::parse($slip['date'])->format('M d, Y') : '-' }}
                                        </small>
                                    </td>

                                    <!-- Status -->
                                    <td>
                                        @if($slip['is_void'])
                                        <span class="badge bg-danger">Void</span>
                                        @elseif($slip['status'] === 'verified' || $slip['status'] === 'approved' || $slip['status'] === 'completed')
                                        <span class="badge bg-success">
                                            <i class="fas fa-check-circle me-1"></i>{{ ucfirst($slip['status']) }}
                                        </span>
                                        @elseif($slip['status'] === 'draft')
                                        <span class="badge bg-secondary">Draft</span>
                                        @else
                                        <span class="badge bg-warning text-dark">{{ ucfirst($slip['status']) }}</span>
                                        @endif
                                    </td>

                                    <!-- Actions -->
                                    <td class="text-end pe-3">
                                        <div class="btn-group btn-group-sm">
                                            <a href="{{ $slip['document_url'] }}" class="btn btn-outline-primary" target="_blank" title="View Document Details">
                                                <i class="fas fa-eye me-1"></i>View
                                            </a>
                                            @if($slip['pr_url'])
                                            <a href="{{ $slip['pr_url'] }}" class="btn btn-outline-secondary" target="_blank" title="View Purchase Request">
                                                <i class="fas fa-external-link-alt"></i>
                                            </a>
                                            @endif
                                        </div>
                                    </td>
                                </tr>

                                <!-- Items Modal for this slip -->
                                @if($slip['items_count'] > 0)
                                <div class="modal fade" id="itemsModal-{{ $slip['numeric_no'] }}" tabindex="-1" aria-hidden="true">
                                    <div class="modal-dialog modal-dialog-centered">
                                        <div class="modal-content border-0 shadow">
                                            <div class="modal-header bg-light py-2">
                                                <h6 class="modal-title fw-bold text-dark">
                                                    <i class="fas fa-boxes text-primary me-2"></i>Items on Slip #{{ $slip['slip_no'] }}
                                                </h6>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                            </div>
                                            <div class="modal-body p-0">
                                                <div class="p-3 bg-light border-bottom small">
                                                    <div><strong>Document:</strong> {{ $slip['document_ref'] }}</div>
                                                    @if($slip['pr_no'])
                                                    <div><strong>PR:</strong> #{{ $slip['pr_no'] }} &bull; <strong>Project:</strong> {{ $slip['project_name'] }}</div>
                                                    @endif
                                                    <div><strong>Supplier / Source:</strong> {{ $slip['supplier_name'] }}</div>
                                                    <div><strong>Date:</strong> {{ $slip['date'] ? \Carbon\Carbon::parse($slip['date'])->format('M d, Y') : '-' }}</div>
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
                                                            <td class="ps-3 fw-semibold">{{ $it['name'] }}</td>
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
                                                    <i class="fas fa-external-link-alt me-1"></i>Open Full Document
                                                </a>
                                                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                @endif
                                @empty
                                <tr>
                                    <td colspan="9" class="text-center py-5 text-muted">
                                        <div class="mb-2"><i class="fas fa-inbox fa-3x text-secondary opacity-50"></i></div>
                                        <div class="fw-bold fs-6">No slips assigned from this book yet</div>
                                        <small>The next transaction for this store and slip type will automatically allocate slip #<strong>{{ $slipSequence->getNextSlipNumber() }}</strong>.</small>
                                    </td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="card-footer bg-white py-2 border-top d-flex justify-content-between align-items-center small text-muted">
                    <div>
                        Showing <strong>{{ $assignedSlips->count() }}</strong> assigned slip records for this book sequence
                    </div>
                    <div>
                        Next slip counter: <strong>#{{ $slipSequence->getNextSlipNumber() }}</strong>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
    // Initialize tooltips
    document.addEventListener('DOMContentLoaded', function() {
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });

        // Search Filter for Slips Table
        var searchInput = document.getElementById('slipSearchInput');
        if (searchInput) {
            searchInput.addEventListener('input', function() {
                var query = this.value.toLowerCase().trim();
                var rows = document.querySelectorAll('#assignedSlipsTable tbody tr.slip-row');
                rows.forEach(function(row) {
                    var text = row.innerText.toLowerCase();
                    row.style.display = text.indexOf(query) > -1 ? '' : 'none';
                });
            });
        }
    });

    // Highlight and scroll to specific slip from the book visualizer
    function highlightSlipInTable(slipNum) {
        var searchInput = document.getElementById('slipSearchInput');
        if (searchInput) {
            searchInput.value = slipNum;
            var event = new Event('input');
            searchInput.dispatchEvent(event);
        }

        var targetRow = document.getElementById('slip-row-' + slipNum);
        if (targetRow) {
            targetRow.scrollIntoView({ behavior: 'smooth', block: 'center' });
            targetRow.classList.add('table-primary');
            setTimeout(function() {
                targetRow.classList.remove('table-primary');
            }, 2500);
        } else {
            var section = document.getElementById('assignedSlipsSection');
            if (section) {
                section.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        }
    }

    function resetSlipTableFilter() {
        var searchInput = document.getElementById('slipSearchInput');
        if (searchInput) {
            searchInput.value = '';
            var event = new Event('input');
            searchInput.dispatchEvent(event);
        }
    }
</script>
@endpush
@endsection
