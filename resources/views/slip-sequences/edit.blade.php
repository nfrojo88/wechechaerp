@extends('layouts.app')

@section('title', 'Edit Slip Sequence')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-0"><i class="fas fa-edit me-2 text-primary"></i>Edit Slip Sequence: {{ $slipSequence->label }}</h4>
            <small class="text-muted">Manage sequence configuration, monitor book usage, and inspect all recorded slips</small>
        </div>
        <div class="d-flex align-items-center gap-2 flex-wrap">
            @if(isset($allStoreSequences) && $allStoreSequences->count() > 1)
            <div class="dropdown">
                <button class="btn btn-sm btn-outline-primary dropdown-toggle" type="button" id="bookSwitcherDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="fas fa-book me-1"></i>Book #{{ $slipSequence->book_start_no }}–#{{ $slipSequence->book_end_no }}
                </button>
                <ul class="dropdown-menu dropdown-menu-end shadow" aria-labelledby="bookSwitcherDropdown">
                    <li><h6 class="dropdown-header">Sequence Books for this Store & Type</h6></li>
                    @foreach($allStoreSequences as $bSeq)
                    <li>
                        <a class="dropdown-item d-flex justify-content-between align-items-center {{ $bSeq->id === $slipSequence->id ? 'active' : '' }}" 
                           href="{{ route('store-manager.slip-sequences.edit', $bSeq) }}">
                            <span>
                                <strong>#{{ $bSeq->book_start_no }} – #{{ $bSeq->book_end_no }}</strong>
                                <small class="d-block text-muted">{{ $bSeq->label }}</small>
                            </span>
                            @if($bSeq->status === 'active')
                            <span class="badge bg-success ms-2">Active</span>
                            @elseif($bSeq->status === 'full')
                            <span class="badge bg-danger ms-2">Full</span>
                            @else
                            <span class="badge bg-secondary ms-2">{{ ucfirst($bSeq->status) }}</span>
                            @endif
                        </a>
                    </li>
                    @endforeach
                </ul>
            </div>
            @endif
            <button type="button" class="btn btn-sm btn-primary shadow-sm" data-bs-toggle="modal" data-bs-target="#nextBookModal">
                <i class="fas fa-plus-circle me-1"></i>Next Book
            </button>
            <a href="{{ route('store-manager.slip-history.index', ['store_id' => $slipSequence->store_id, 'slip_type' => $slipSequence->slip_type]) }}" class="btn btn-sm btn-outline-secondary shadow-sm">
                <i class="fas fa-history me-1"></i>Slip History
            </a>
            <a href="{{ route('store-manager.slip-sequences.index') }}" class="btn btn-sm btn-secondary shadow-sm">
                <i class="fas fa-arrow-left me-1"></i>Back to Sequences
            </a>
        </div>
    </div>

    <!-- Top Configuration Row -->
    <div class="row mb-4">
        <div class="col-lg-8">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-white py-3">
                    <h6 class="m-0 fw-bold text-primary"><i class="fas fa-sliders-h me-1"></i>Sequence Details</h6>
                </div>
                <div class="card-body">
                    <form id="slipSequenceForm" action="{{ route('store-manager.slip-sequences.update', $slipSequence) }}" method="POST">
                        @csrf
                        @method('PUT')

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label fw-bold small text-muted text-uppercase">Store</label>
                                <div class="fs-6 fw-semibold text-dark">{{ $slipSequence->store->name }}</div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold small text-muted text-uppercase">Type</label>
                                <div>
                                    @if($slipSequence->slip_type === 'receive')
                                    <span class="badge bg-success"><i class="fas fa-arrow-down me-1"></i>GRN</span>
                                    <span class="text-muted small ms-1">(Goods Receiving)</span>
                                    @else
                                    <span class="badge bg-info"><i class="fas fa-arrow-up me-1"></i>SIN</span>
                                    <span class="text-muted small ms-1">(Store Issue Note)</span>
                                    @endif
                                </div>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label fw-bold small text-muted text-uppercase">Label *</label>
                                <input type="text" name="label" class="form-control" 
                                       value="{{ old('label', $slipSequence->label) }}" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold small text-muted text-uppercase">Prefix</label>
                                <input type="text" name="prefix" class="form-control font-monospace" 
                                       value="{{ old('prefix', $slipSequence->prefix) }}" maxlength="50"
                                       placeholder="(Optional prefix, e.g. REC)">
                            </div>
                        </div>

                        <hr>
                        <h6 class="fw-bold text-secondary mb-3"><i class="fas fa-book me-1"></i>Book Information (Read-Only)</h6>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label fw-bold small text-muted text-uppercase">Book Range</label>
                                <div class="fs-6 font-monospace fw-bold text-dark">
                                    {{ $slipSequence->book_start_no }} &ndash; {{ $slipSequence->book_end_no }}
                                </div>
                                <small class="text-muted">Total: {{ $slipSequence->book_end_no - $slipSequence->book_start_no + 1 }} slips</small>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold small text-muted text-uppercase">Next Available Slip</label>
                                <div>
                                    <span class="badge bg-primary fs-6 px-3 py-1 font-monospace">#{{ $slipSequence->getNextSlipNumber() }}</span>
                                </div>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label fw-bold small text-muted text-uppercase">Usage Progress</label>
                                <div>
                                    <strong>{{ $slipSequence->used_count }}</strong> / {{ $slipSequence->book_end_no - $slipSequence->book_start_no + 1 }} used
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
                                <label class="form-label fw-bold small text-muted text-uppercase">Status</label>
                                <div>
                                    @if($slipSequence->status === 'active')
                                    <span class="badge bg-success"><i class="fas fa-check me-1"></i>Active</span>
                                    @elseif($slipSequence->status === 'full')
                                    <span class="badge bg-danger"><i class="fas fa-exclamation me-1"></i>Full</span>
                                    @else
                                    <span class="badge bg-secondary">Inactive</span>
                                    @endif
                                </div>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-12">
                                <label class="form-label fw-bold small text-muted text-uppercase">Notes</label>
                                <textarea name="notes" class="form-control" rows="2">{{ old('notes', $slipSequence->notes) }}</textarea>
                            </div>
                        </div>

                        <hr>
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                @if($slipSequence->status === 'active')
                                <button type="submit" form="deactivateForm" class="btn btn-sm btn-warning">
                                    <i class="fas fa-pause me-1"></i>Deactivate
                                </button>
                                @elseif($slipSequence->status !== 'full')
                                <button type="submit" form="reactivateForm" class="btn btn-sm btn-success">
                                    <i class="fas fa-play me-1"></i>Reactivate
                                </button>
                                @endif
                            </div>
                            <div>
                                <a href="{{ route('store-manager.slip-sequences.index') }}" class="btn btn-sm btn-secondary me-1">Cancel</a>
                                <button type="submit" form="slipSequenceForm" class="btn btn-sm btn-primary">
                                    <i class="fas fa-save me-1"></i>Save Changes
                                </button>
                            </div>
                        </div>
                    </form>

                    <!-- Deactivate / Reactivate separate forms to prevent HTML form nesting -->
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

        <div class="col-lg-4">
            <div class="card shadow-sm bg-light h-100">
                <div class="card-header bg-white py-3">
                    <h6 class="m-0 fw-bold text-dark">Sequence Status</h6>
                </div>
                <div class="card-body small">
                    <div class="mb-3">
                        <div class="fw-bold text-muted text-uppercase mb-1">Current Status:</div>
                        @if($slipSequence->status === 'active')
                        <span class="badge bg-success">Active - Slips will be assigned from this sequence</span>
                        @elseif($slipSequence->status === 'full')
                        <span class="badge bg-danger">Full - All slips in this book have been used</span>
                        @else
                        <span class="badge bg-secondary">Inactive - Slips will not be assigned from this sequence</span>
                        @endif
                    </div>

                    <hr>
                    <div class="fw-bold text-muted text-uppercase">Next Slip to Assign:</div>
                    <div class="fs-5 fw-bold text-primary font-monospace">#{{ $slipSequence->getNextSlipNumber() }}</div>

                    <hr>
                    <div class="fw-bold text-muted text-uppercase">Remaining Slips in Book:</div>
                    <div class="fs-5 fw-bold text-dark">{{ $slipSequence->getRemainingSlips() }}</div>

                    <hr>
                    <div class="fw-bold text-muted text-uppercase">Assigned & Linked Records:</div>
                    <div class="fs-5 fw-bold text-success">{{ $assignedSlips->count() }} slips</div>

                    @if($slipSequence->getRemainingSlips() < 10 && $slipSequence->getRemainingSlips() > 0)
                    <div class="alert alert-warning mt-3 mb-0">
                        <i class="fas fa-exclamation-triangle me-1"></i>
                        <strong>Low on slips!</strong> Only {{ $slipSequence->getRemainingSlips() }} left in this book.
                        <div class="mt-2">
                            <button type="button" class="btn btn-sm btn-outline-dark w-100 shadow-sm" data-bs-toggle="modal" data-bs-target="#nextBookModal">
                                <i class="fas fa-plus-circle me-1"></i>Prepare Next Book
                            </button>
                        </div>
                    </div>
                    @elseif($slipSequence->getRemainingSlips() <= 0)
                    <div class="alert alert-danger mt-3 mb-0">
                        <i class="fas fa-ban me-1"></i>
                        <strong>Book is full!</strong> Slips for this sequence range are exhausted.
                        <div class="mt-2">
                            <button type="button" class="btn btn-sm btn-danger text-white w-100 fw-bold shadow-sm" data-bs-toggle="modal" data-bs-target="#nextBookModal">
                                <i class="fas fa-plus-circle me-1"></i>Create & Activate Next Book
                            </button>
                        </div>
                    </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <!-- Assigned Slips & Linked Records Section -->
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div>
                <h6 class="m-0 fw-bold text-primary">
                    <i class="fas fa-receipt me-2"></i>Slips & Book Records ({{ $slipSequence->book_start_no }} &ndash; {{ $slipSequence->book_end_no }})
                </h6>
                <small class="text-muted">
                    Store: <strong>{{ $slipSequence->store->name }}</strong> &bull; 
                    Capacity: <strong>{{ $slipSequence->book_end_no - $slipSequence->book_start_no + 1 }} slips</strong>
                </small>
            </div>
            @php
                $procurementSlipsCount = $assignedSlips->where('source_type', 'delivery_receipt')->count();
                $transferSlipsCount = $assignedSlips->where('source_type', 'transfer')->count();
                $unrecordedCount = collect($bookMap)->where('status', 'unrecorded')->count();
            @endphp
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <span class="badge bg-success" title="{{ $procurementSlipsCount }} Procurement, {{ $transferSlipsCount }} Transfers">
                    {{ $assignedSlips->count() }} Total Recorded
                </span>
                @if($transferSlipsCount > 0)
                <span class="badge text-white" style="background-color: #4f46e5;">
                    <i class="fas fa-truck me-1"></i>{{ $transferSlipsCount }} Transfers
                </span>
                @endif
                <span class="badge bg-primary">Next: #{{ $slipSequence->getNextSlipNumber() }}</span>
                <span class="badge bg-secondary">{{ $slipSequence->getRemainingSlips() }} Remaining</span>
                <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#bookMapCollapse" aria-expanded="true" aria-controls="bookMapCollapse">
                    <i class="fas fa-th me-1"></i>Book Map
                </button>
            </div>
        </div>

        <!-- Book Leaves Map -->
        <div class="collapse show border-bottom bg-light p-3" id="bookMapCollapse">
            <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">
                <span class="small fw-bold text-muted text-uppercase">
                    <i class="fas fa-bookmark me-1"></i>Book Pad Leaves ({{ $slipSequence->book_start_no }} &ndash; {{ $slipSequence->book_end_no }})
                </span>
                <div class="small d-flex gap-3 text-muted flex-wrap">
                    <span><span class="badge bg-success px-2 py-0">&nbsp;</span> Procurement GRN ({{ $procurementSlipsCount }})</span>
                    <span><span class="badge px-2 py-0 text-white" style="background-color: #4f46e5;">&nbsp;</span> Store Transfers ({{ $transferSlipsCount }})</span>
                    <span><span class="badge bg-primary px-2 py-0">&nbsp;</span> Next Slip (#{{ $slipSequence->getNextSlipNumber() }})</span>
                    @if($unrecordedCount > 0)
                    <span><span class="badge bg-warning text-dark px-2 py-0">&nbsp;</span> Unrecorded / Gap ({{ $unrecordedCount }})</span>
                    @endif
                    <span><span class="badge bg-light border text-muted px-2 py-0">&nbsp;</span> Available ({{ $slipSequence->getRemainingSlips() }})</span>
                </div>
            </div>
            <div class="d-flex flex-wrap gap-1" style="max-height: 180px; overflow-y: auto;">
                @foreach($bookMap as $leaf)
                    @if($leaf['status'] === 'assigned')
                        @php
                            $isTransferLeaf = ($leaf['assigned_data']['source_type'] ?? '') === 'transfer';
                            $leafTooltip = 'Slip #' . $leaf['formatted'] . ' - ' . ($isTransferLeaf ? ('Store Transfer #' . ($leaf['assigned_data']['transfer_no'] ?? '')) : 'Procurement GRN') . ' - Click to filter';
                        @endphp
                        <button type="button" 
                                class="btn btn-sm px-2 py-0 font-monospace shadow-sm {{ $isTransferLeaf ? 'text-white' : 'btn-success' }}"
                                style="font-size: 0.75rem; {{ $isTransferLeaf ? 'background-color: #4f46e5; border-color: #4338ca;' : '' }}"
                                title="{{ $leafTooltip }}"
                                onclick="filterSlipRow('{{ $leaf['number'] }}')">
                            @if($isTransferLeaf)
                                <i class="fas fa-exchange-alt me-1" style="font-size: 0.65rem;"></i>
                            @endif
                            {{ $leaf['formatted'] }}
                        </button>
                    @elseif($leaf['status'] === 'next')
                        <span class="badge bg-primary px-2 py-1 font-monospace" style="font-size: 0.75rem;" title="Next Slip to Assign">
                            #{{ $leaf['formatted'] }}
                        </span>
                    @elseif($leaf['status'] === 'unrecorded')
                        <button type="button" 
                                class="btn btn-sm btn-warning text-dark px-2 py-0 font-monospace"
                                style="font-size: 0.75rem;"
                                title="Slip #{{ $leaf['formatted'] }} - Unrecorded gap"
                                onclick="filterSlipRow('{{ $leaf['number'] }}')">
                            {{ $leaf['formatted'] }}
                        </button>
                    @else
                        <span class="badge bg-light text-muted border px-2 py-1 font-monospace" style="font-size: 0.75rem;">
                            {{ $leaf['formatted'] }}
                        </span>
                    @endif
                @endforeach
            </div>
        </div>

        <!-- History Guarantee Alert -->
        <div class="px-3 pt-3">
            <div class="alert alert-info border-start border-4 mb-0 py-2 d-flex align-items-center justify-content-between flex-wrap gap-2">
                <div>
                    <i class="fas fa-shield-alt text-primary me-2"></i>
                    <strong>Permanent Slip History:</strong> Activating the next sequence book never deletes previous records. All <strong>{{ $allStoreSlips->count() }} slips</strong> recorded for this store are permanently archived and accessible below across all books.
                </div>
                <a href="{{ route('store-manager.slip-history.index', ['store_id' => $slipSequence->store_id, 'slip_type' => $slipSequence->slip_type]) }}" class="btn btn-xs btn-outline-primary py-1 px-2" style="font-size: 0.78rem;">
                    <i class="fas fa-external-link-alt me-1"></i>Company Slip History Hub
                </a>
            </div>
        </div>

        <!-- Navigation Tabs, Source Filters & Search -->
        <div class="p-3 border-bottom bg-white d-flex justify-content-between align-items-center flex-wrap gap-3">
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <ul class="nav nav-pills" id="slipViewTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active py-1 px-3 small fw-semibold" id="recorded-tab" data-bs-toggle="tab" data-bs-target="#recorded-view" type="button" role="tab" aria-controls="recorded-view" aria-selected="true">
                            <i class="fas fa-bookmark me-1 text-success"></i>Current Book ({{ $assignedSlips->count() }})
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link py-1 px-3 small fw-semibold" id="all-history-tab" data-bs-toggle="tab" data-bs-target="#all-history-view" type="button" role="tab" aria-controls="all-history-view" aria-selected="false">
                            <i class="fas fa-history me-1 text-primary"></i>All Books History ({{ $allStoreSlips->count() }})
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link py-1 px-3 small fw-semibold" id="all-leaves-tab" data-bs-toggle="tab" data-bs-target="#all-leaves-view" type="button" role="tab" aria-controls="all-leaves-view" aria-selected="false">
                            <i class="fas fa-th me-1 text-secondary"></i>Book Leaves Map ({{ count($bookMap) }})
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link py-1 px-3 small fw-semibold" id="books-archive-tab" data-bs-toggle="tab" data-bs-target="#books-archive-view" type="button" role="tab" aria-controls="books-archive-view" aria-selected="false">
                            <i class="fas fa-layer-group me-1 text-info"></i>All Books Archive ({{ $allStoreSequences->count() }})
                        </button>
                    </li>
                </ul>

                <!-- Source Quick Filter Group -->
                <div class="btn-group btn-group-sm ms-md-2" role="group" id="slipSourceFilterGroup">
                    <button type="button" class="btn btn-secondary active fw-semibold" id="filter-all-btn" onclick="filterSlipCategory('all')">
                        All ({{ $assignedSlips->count() }})
                    </button>
                    <button type="button" class="btn btn-outline-primary fw-semibold" id="filter-transfer-btn" onclick="filterSlipCategory('transfer')" style="color: #4338ca; border-color: #6366f1;">
                        <i class="fas fa-truck me-1"></i>Transfers ({{ $transferSlipsCount }})
                    </button>
                    <button type="button" class="btn btn-outline-success fw-semibold" id="filter-procure-btn" onclick="filterSlipCategory('delivery_receipt')">
                        <i class="fas fa-box me-1"></i>Procurement ({{ $procurementSlipsCount }})
                    </button>
                </div>
            </div>

            <div class="d-flex align-items-center gap-2 flex-grow-1 justify-content-md-end" style="max-width: 420px;">
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-light"><i class="fas fa-search text-muted"></i></span>
                    <input type="text" id="slipSearchInput" class="form-control" placeholder="Search slip #, transfer #, PR #, project...">
                </div>
                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="resetSlipSearch()">
                    Reset
                </button>
            </div>
        </div>

        <div class="tab-content" id="slipViewTabsContent">
            <!-- TAB 1: Recorded & Linked Transactions -->
            <div class="tab-pane fade show active" id="recorded-view" role="tabpanel" aria-labelledby="recorded-tab">
                <div class="table-responsive">
                    <table class="table table-hover table-striped mb-0 align-middle" id="assignedSlipsTable">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 105px;">Slip #</th>
                                <th style="width: 95px;">Source / Type</th>
                                <th style="min-width: 175px;">Linked Document</th>
                                <th style="min-width: 250px;">Purchase Request / Store Transfer</th>
                                <th style="min-width: 180px;">Supplier / Transfer Route</th>
                                <th style="width: 125px;">Items</th>
                                <th style="width: 135px;">Handled By & Date</th>
                                <th style="width: 95px;">Status</th>
                                <th style="width: 110px;" class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($assignedSlips as $slip)
                            <tr class="slip-row {{ $slip['is_void'] ? 'table-danger' : '' }}" 
                                id="slip-row-{{ $slip['numeric_no'] }}"
                                data-slip-no="{{ $slip['slip_no'] }}"
                                data-numeric-no="{{ $slip['numeric_no'] }}"
                                data-source-type="{{ $slip['source_type'] }}"
                                data-slip-type="{{ $slip['slip_type'] }}">
                                <!-- Slip # -->
                                <td>
                                    <span class="badge bg-primary font-monospace fs-6">
                                        #{{ $slip['slip_no'] }}
                                    </span>
                                    @if($slip['is_void'])
                                    <br><span class="badge bg-danger mt-1">VOID</span>
                                    @endif
                                    <div class="small text-muted mt-1">Leaf: {{ $slip['numeric_no'] }}</div>
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
                                    <small class="text-muted d-block">Store: {{ $slip['store_name'] }}</small>
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

                                <!-- Supplier / Source & Route -->
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
                                            data-bs-toggle="modal" data-bs-target="#itemsModal-{{ $slip['numeric_no'] }}">
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
                                <td class="text-end pe-2">
                                    <a href="{{ $slip['document_url'] }}" class="btn btn-sm btn-outline-primary" target="_blank" title="View Document">
                                        <i class="fas fa-eye me-1"></i>View
                                    </a>
                                    @if($slip['pr_url'])
                                    <a href="{{ $slip['pr_url'] }}" class="btn btn-sm btn-outline-secondary" target="_blank" title="View Purchase Request">
                                        <i class="fas fa-external-link-alt"></i>
                                    </a>
                                    @endif
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="9" class="text-center py-4 text-muted">
                                    <i class="fas fa-inbox fa-2x mb-2 text-muted d-block"></i>
                                    No slip records assigned from this sequence book yet.
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- TAB 2: All Books History / Complete Store Archive -->
            <div class="tab-pane fade" id="all-history-view" role="tabpanel" aria-labelledby="all-history-tab">
                <div class="table-responsive">
                    <table class="table table-hover table-striped mb-0 align-middle" id="allHistorySlipsTable">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 135px;">Slip # & Book</th>
                                <th style="width: 95px;">Source / Type</th>
                                <th style="min-width: 175px;">Linked Document</th>
                                <th style="min-width: 250px;">Purchase Request / Store Transfer</th>
                                <th style="min-width: 180px;">Supplier / Transfer Route</th>
                                <th style="width: 125px;">Items</th>
                                <th style="width: 135px;">Handled By & Date</th>
                                <th style="width: 95px;">Status</th>
                                <th style="width: 110px;" class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($allStoreSlips as $slip)
                            <tr class="slip-row {{ $slip['is_void'] ? 'table-danger' : '' }}" 
                                id="all-slip-row-{{ $slip['numeric_no'] }}"
                                data-slip-no="{{ $slip['slip_no'] }}"
                                data-numeric-no="{{ $slip['numeric_no'] }}"
                                data-source-type="{{ $slip['source_type'] }}"
                                data-slip-type="{{ $slip['slip_type'] }}">
                                <!-- Slip # & Book Badge -->
                                <td>
                                    <span class="badge bg-primary font-monospace fs-6">
                                        #{{ $slip['slip_no'] }}
                                    </span>
                                    @if($slip['is_void'])
                                    <br><span class="badge bg-danger mt-1">VOID</span>
                                    @endif
                                    <div class="mt-1">
                                        @if($slip['is_current_book'])
                                        <span class="badge bg-success text-white border" style="font-size: 0.68rem;" title="Belongs to currently viewed book">
                                            <i class="fas fa-bookmark me-1"></i>Book: {{ $slip['book_range'] }}
                                        </span>
                                        @else
                                        <span class="badge bg-secondary text-white border" style="font-size: 0.68rem;" title="Archived sequence book">
                                            <i class="fas fa-archive me-1"></i>Book: {{ $slip['book_range'] }}
                                        </span>
                                        @endif
                                    </div>
                                    <div class="small text-muted mt-1">Leaf: {{ $slip['numeric_no'] }}</div>
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
                                    <small class="text-muted d-block">Store: {{ $slip['store_name'] }}</small>
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

                                <!-- Supplier / Source & Route -->
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
                                            data-bs-toggle="modal" data-bs-target="#itemsModal-{{ $slip['numeric_no'] }}">
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
                                <td class="text-end pe-2">
                                    <a href="{{ $slip['document_url'] }}" class="btn btn-sm btn-outline-primary" target="_blank" title="View Document">
                                        <i class="fas fa-eye me-1"></i>View
                                    </a>
                                    @if($slip['pr_url'])
                                    <a href="{{ $slip['pr_url'] }}" class="btn btn-sm btn-outline-secondary" target="_blank" title="View Purchase Request">
                                        <i class="fas fa-external-link-alt"></i>
                                    </a>
                                    @endif
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="9" class="text-center py-4 text-muted">
                                    <i class="fas fa-inbox fa-2x mb-2 text-muted d-block"></i>
                                    No recorded slips found across any books for this store.
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- TAB 3: Complete Book Leaves Audit (All 50 Leaves) -->
            <div class="tab-pane fade" id="all-leaves-view" role="tabpanel" aria-labelledby="all-leaves-tab">
                <div class="table-responsive">
                    <table class="table table-hover table-striped mb-0 align-middle">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 120px;">Leaf Number</th>
                                <th style="width: 140px;">Book Status</th>
                                <th>Linked Transaction / Document</th>
                                <th>Project / Supplier / Route</th>
                                <th>Handled Date</th>
                                <th class="text-end" style="width: 110px;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($bookMap as $leaf)
                            <tr>
                                <td class="font-monospace fw-bold">
                                    #{{ $leaf['formatted'] }}
                                </td>
                                <td>
                                    @if($leaf['status'] === 'assigned')
                                        @if(($leaf['assigned_data']['source_type'] ?? '') === 'transfer')
                                        <span class="badge text-white" style="background-color: #4f46e5;"><i class="fas fa-truck me-1"></i>Transfer</span>
                                        @else
                                        <span class="badge bg-success"><i class="fas fa-check me-1"></i>Procurement</span>
                                        @endif
                                    @elseif($leaf['status'] === 'next')
                                    <span class="badge bg-primary"><i class="fas fa-arrow-right me-1"></i>Next to Issue</span>
                                    @elseif($leaf['status'] === 'unrecorded')
                                    <span class="badge bg-warning text-dark"><i class="fas fa-exclamation me-1"></i>Unrecorded Gap</span>
                                    @else
                                    <span class="badge bg-secondary">Available</span>
                                    @endif
                                </td>
                                <td>
                                    @if($leaf['status'] === 'assigned' && !empty($leaf['assigned_data']))
                                        @php $ad = $leaf['assigned_data']; @endphp
                                        <a href="{{ $ad['document_url'] }}" class="fw-bold text-primary text-decoration-none" target="_blank">
                                            {{ $ad['document_ref'] }}
                                        </a>
                                        @if($ad['pr_no'])
                                        <span class="text-muted ms-2">(PR #{{ $ad['pr_no'] }})</span>
                                        @endif
                                    @elseif($leaf['status'] === 'next')
                                        <span class="text-primary small fw-semibold">Next physical slip to be generated</span>
                                    @elseif($leaf['status'] === 'unrecorded')
                                        <span class="text-muted small">Counter advanced past this number (gap)</span>
                                    @else
                                        <span class="text-muted small">Unused blank slip in physical pad</span>
                                    @endif
                                </td>
                                <td>
                                    @if($leaf['status'] === 'assigned' && !empty($leaf['assigned_data']))
                                        @php $ad = $leaf['assigned_data']; @endphp
                                        <div class="small fw-semibold">{{ $ad['supplier_name'] }}</div>
                                        <small class="text-muted">{{ $ad['project_name'] }}</small>
                                    @else
                                        <span class="text-muted">&ndash;</span>
                                    @endif
                                </td>
                                <td>
                                    @if($leaf['status'] === 'assigned' && !empty($leaf['assigned_data']))
                                        @php $ad = $leaf['assigned_data']; @endphp
                                        <small class="text-muted">{{ $ad['date'] ? \Carbon\Carbon::parse($ad['date'])->format('M d, Y') : '-' }}</small>
                                    @else
                                        <span class="text-muted">&ndash;</span>
                                    @endif
                                </td>
                                <td class="text-end">
                                    @if($leaf['status'] === 'assigned' && !empty($leaf['assigned_data']))
                                        @php $ad = $leaf['assigned_data']; @endphp
                                        <a href="{{ $ad['document_url'] }}" class="btn btn-sm btn-outline-primary" target="_blank">
                                            <i class="fas fa-eye me-1"></i>View
                                        </a>
                                    @else
                                        <span class="text-muted">&ndash;</span>
                                    @endif
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- TAB 4: All Sequence Books Archive for this Store -->
            <div class="tab-pane fade" id="books-archive-view" role="tabpanel" aria-labelledby="books-archive-tab">
                <div class="table-responsive">
                    <table class="table table-hover table-striped mb-0 align-middle">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 160px;">Book Range</th>
                                <th>Label / Title</th>
                                <th style="width: 110px;">Type</th>
                                <th style="width: 140px;">Current / Next</th>
                                <th style="min-width: 180px;">Usage Progress</th>
                                <th style="width: 110px;">Status</th>
                                <th style="width: 180px;">Notes</th>
                                <th class="text-end" style="width: 140px;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($allStoreSequences as $bSeq)
                            <tr class="{{ $bSeq->id === $slipSequence->id ? 'table-primary-subtle border-start border-4 border-primary' : '' }}">
                                <td>
                                    <span class="font-monospace fw-bold fs-6">
                                        #{{ $bSeq->book_start_no }} &ndash; #{{ $bSeq->book_end_no }}
                                    </span>
                                    <small class="text-muted d-block">Capacity: {{ $bSeq->book_end_no - $bSeq->book_start_no + 1 }} slips</small>
                                </td>
                                <td>
                                    <strong>{{ $bSeq->label }}</strong>
                                    @if($bSeq->prefix)
                                    <div class="small font-monospace text-muted">Prefix: <code>{{ $bSeq->prefix }}</code></div>
                                    @endif
                                </td>
                                <td>
                                    @if($bSeq->slip_type === 'receive')
                                    <span class="badge bg-success"><i class="fas fa-arrow-down me-1"></i>GRN</span>
                                    @else
                                    <span class="badge bg-info"><i class="fas fa-arrow-up me-1"></i>SIN</span>
                                    @endif
                                </td>
                                <td>
                                    <span class="badge bg-primary font-monospace fs-6">#{{ $bSeq->getNextSlipNumber() }}</span>
                                </td>
                                <td>
                                    <div class="small">
                                        <strong>{{ $bSeq->used_count }} / {{ $bSeq->book_end_no - $bSeq->book_start_no + 1 }}</strong> used
                                        <div class="progress mt-1" style="height: 6px;">
                                            <div class="progress-bar {{ $bSeq->getPercentageUsed() > 85 ? 'bg-danger' : 'bg-primary' }}" 
                                                 role="progressbar" 
                                                 style="width: {{ min(100, $bSeq->getPercentageUsed()) }}%;"></div>
                                        </div>
                                        <span class="text-muted" style="font-size: 0.72rem;">{{ $bSeq->getPercentageUsed() }}% used &bull; {{ $bSeq->getRemainingSlips() }} remaining</span>
                                    </div>
                                </td>
                                <td>
                                    @if($bSeq->status === 'active')
                                    <span class="badge bg-success"><i class="fas fa-check me-1"></i>Active</span>
                                    @elseif($bSeq->status === 'full')
                                    <span class="badge bg-danger"><i class="fas fa-ban me-1"></i>Full / Completed</span>
                                    @else
                                    <span class="badge bg-secondary">Inactive</span>
                                    @endif
                                </td>
                                <td>
                                    <small class="text-muted">{{ $bSeq->notes ?: '-' }}</small>
                                </td>
                                <td class="text-end">
                                    @if($bSeq->id === $slipSequence->id)
                                    <span class="badge bg-primary text-white py-1 px-2">Currently Viewing</span>
                                    @else
                                    <a href="{{ route('store-manager.slip-sequences.edit', $bSeq) }}" class="btn btn-sm btn-outline-primary">
                                        <i class="fas fa-folder-open me-1"></i>Open Book
                                    </a>
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
</div>

<!-- Create & Activate Next Sequence Book Modal -->
<div class="modal fade" id="nextBookModal" tabindex="-1" aria-labelledby="nextBookModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content shadow-lg border-0">
            <div class="modal-header bg-primary text-white py-3">
                <h5 class="modal-title fw-bold mb-0" id="nextBookModalLabel">
                    <i class="fas fa-plus-circle me-2"></i>Create & Activate Next Sequence Book
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="{{ route('store-manager.slip-sequences.next-book', $slipSequence) }}" method="POST">
                @csrf
                <div class="modal-body p-4">
                    <!-- Historical Preservation Callout -->
                    <div class="alert alert-success border-start border-4 mb-4">
                        <div class="d-flex align-items-start">
                            <i class="fas fa-shield-alt fa-2x text-success me-3 mt-1"></i>
                            <div>
                                <h6 class="fw-bold text-success mb-1">Zero Data Loss Guarantee</h6>
                                <p class="small mb-0 text-dark">
                                    When you activate this next sequence book, all <strong>{{ $allStoreSlips->count() }} slips</strong> from Book #{{ $slipSequence->book_start_no }}–#{{ $slipSequence->book_end_no }} (and any previous books) will remain <strong>permanently stored and intact</strong>. All linked documents, purchase requests, store transfers, and items will always remain accessible in the Slip History tab.
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-muted text-uppercase">Store</label>
                            <div class="form-control-plaintext fw-bold text-dark fs-6">{{ $slipSequence->store->name }}</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-muted text-uppercase">Slip Type</label>
                            <div>
                                @if($slipSequence->slip_type === 'receive')
                                <span class="badge bg-success fs-6"><i class="fas fa-arrow-down me-1"></i>GRN (Goods Receiving)</span>
                                @else
                                <span class="badge bg-info fs-6"><i class="fas fa-arrow-up me-1"></i>SIN (Store Issue Note)</span>
                                @endif
                            </div>
                        </div>

                        <div class="col-md-8">
                            <label class="form-label fw-bold small text-muted text-uppercase">Next Book Label *</label>
                            <input type="text" name="label" class="form-control" 
                                   value="{{ $slipSequence->slip_type === 'receive' ? 'Receiving (GRN)' : 'Outgoing (SIN)' }} - Book {{ $allStoreSequences->count() + 1 }}" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold small text-muted text-uppercase">Prefix</label>
                            <input type="text" name="prefix" class="form-control font-monospace" 
                                   value="{{ $slipSequence->prefix }}" maxlength="50">
                        </div>

                        @php
                            $nextStart = $slipSequence->book_end_no + 1;
                            $capacity = max(1, $slipSequence->book_end_no - $slipSequence->book_start_no + 1);
                            $nextEnd = $nextStart + $capacity - 1;
                        @endphp

                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-muted text-uppercase">Book Start Number *</label>
                            <input type="number" name="book_start_no" id="modal_book_start_no" class="form-control font-monospace fw-bold" 
                                   value="{{ $nextStart }}" min="1" required oninput="calculateModalCapacity()">
                            <small class="text-muted">Continuously follows Book #{{ $slipSequence->book_end_no }}</small>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-muted text-uppercase">Book End Number *</label>
                            <input type="number" name="book_end_no" id="modal_book_end_no" class="form-control font-monospace fw-bold" 
                                   value="{{ $nextEnd }}" min="2" required oninput="calculateModalCapacity()">
                            <small class="text-muted" id="modalCapacityText">Total capacity: {{ $capacity }} slips</small>
                        </div>

                        <div class="col-12">
                            <div class="form-check form-switch bg-light p-3 rounded border">
                                <input class="form-check-input ms-0 me-2" type="checkbox" name="archive_previous" id="archivePreviousSwitch" value="1" checked>
                                <label class="form-check-label fw-semibold text-dark" for="archivePreviousSwitch">
                                    Archive current book (#{{ $slipSequence->book_start_no }} &ndash; #{{ $slipSequence->book_end_no }}) and activate this new book
                                </label>
                                <div class="small text-muted ms-4 ps-1">
                                    Current book will be marked as Completed/Full. New transaction slips will seamlessly be assigned starting at #{{ $nextStart }}.
                                </div>
                            </div>
                        </div>

                        <div class="col-12">
                            <label class="form-label fw-bold small text-muted text-uppercase">Notes (Optional)</label>
                            <textarea name="notes" class="form-control" rows="2" placeholder="e.g. Physical Book #{{ $allStoreSequences->count() + 1 }}, issued by central store"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light py-2">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary fw-semibold">
                        <i class="fas fa-check-circle me-1"></i>Save & Activate Next Book
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modals placed OUTSIDE of the table structure to ensure valid HTML and prevent styling breaks -->
@php
    $allModalSlips = $allStoreSlips->merge($assignedSlips)->unique('numeric_no');
@endphp
@foreach($allModalSlips as $slip)
    @if($slip['items_count'] > 0)
    <div class="modal fade" id="itemsModal-{{ $slip['numeric_no'] }}" tabindex="-1" aria-labelledby="itemsModalLabel-{{ $slip['numeric_no'] }}" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content shadow">
                <div class="modal-header py-2" style="{{ $slip['source_type'] === 'transfer' ? 'background: linear-gradient(135deg, #1e1b4b 0%, #312e81 100%); color: #fff;' : '' }}">
                    <h6 class="modal-title fw-bold mb-0 {{ $slip['source_type'] === 'transfer' ? 'text-white' : '' }}" id="itemsModalLabel-{{ $slip['numeric_no'] }}">
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

@push('scripts')
<script>
    var currentCategoryFilter = 'all';

    document.addEventListener('DOMContentLoaded', function() {
        var searchInput = document.getElementById('slipSearchInput');
        if (searchInput) {
            searchInput.addEventListener('input', function() {
                applySlipFilters();
            });
        }
    });

    function filterSlipCategory(category) {
        currentCategoryFilter = category;

        // Update button active state
        var allBtn = document.getElementById('filter-all-btn');
        var trBtn = document.getElementById('filter-transfer-btn');
        var prBtn = document.getElementById('filter-procure-btn');

        if (allBtn) allBtn.className = category === 'all' ? 'btn btn-secondary active fw-semibold' : 'btn btn-outline-secondary fw-semibold';
        if (trBtn) trBtn.className = category === 'transfer' ? 'btn btn-primary active fw-semibold' : 'btn btn-outline-primary fw-semibold';
        if (prBtn) prBtn.className = category === 'delivery_receipt' ? 'btn btn-success active fw-semibold' : 'btn btn-outline-success fw-semibold';

        applySlipFilters();
    }

    function calculateModalCapacity() {
        var startInput = document.getElementById('modal_book_start_no');
        var endInput = document.getElementById('modal_book_end_no');
        var capText = document.getElementById('modalCapacityText');
        if (startInput && endInput && capText) {
            var s = parseInt(startInput.value) || 0;
            var e = parseInt(endInput.value) || 0;
            if (e >= s && s > 0) {
                var total = e - s + 1;
                capText.textContent = 'Total capacity: ' + total + ' slips';
                capText.className = 'text-success fw-bold';
            } else {
                capText.textContent = 'End number must be greater than start number';
                capText.className = 'text-danger fw-bold';
            }
        }
    }

    function applySlipFilters() {
        var searchInput = document.getElementById('slipSearchInput');
        var query = searchInput ? searchInput.value.toLowerCase().trim() : '';
        var rows = document.querySelectorAll('#assignedSlipsTable tbody tr.slip-row, #allHistorySlipsTable tbody tr.slip-row');

        rows.forEach(function(row) {
            var rowSource = row.getAttribute('data-source-type');
            var text = row.innerText.toLowerCase();

            var matchesCategory = (currentCategoryFilter === 'all') || (rowSource === currentCategoryFilter);
            var matchesSearch = (query === '') || (text.indexOf(query) > -1);

            row.style.display = (matchesCategory && matchesSearch) ? '' : 'none';
        });
    }

    function filterSlipRow(slipNum) {
        // Switch to recorded tab
        var recordedTabBtn = document.getElementById('recorded-tab');
        if (recordedTabBtn) {
            var tab = new bootstrap.Tab(recordedTabBtn);
            tab.show();
        }

        // Reset category filter to all
        filterSlipCategory('all');

        var searchInput = document.getElementById('slipSearchInput');
        if (searchInput) {
            searchInput.value = slipNum;
            applySlipFilters();
        }

        var targetRow = document.getElementById('slip-row-' + slipNum) || document.getElementById('all-slip-row-' + slipNum);
        if (targetRow) {
            targetRow.scrollIntoView({ behavior: 'smooth', block: 'center' });
            targetRow.classList.add('table-warning');
            setTimeout(function() {
                targetRow.classList.remove('table-warning');
            }, 2500);
        }
    }

    function resetSlipSearch() {
        var searchInput = document.getElementById('slipSearchInput');
        if (searchInput) {
            searchInput.value = '';
        }
        filterSlipCategory('all');
    }
</script>
@endpush
@endsection
