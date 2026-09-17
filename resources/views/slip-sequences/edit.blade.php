@extends('layouts.app')

@section('title', 'Edit Slip Sequence')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-0"><i class="fas fa-edit me-2 text-primary"></i>Edit Slip Sequence: {{ $slipSequence->label }}</h4>
            <small class="text-muted">Manage sequence configuration, monitor book usage, and inspect linked slips</small>
        </div>
        <div>
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
                        Low on slips! Only {{ $slipSequence->getRemainingSlips() }} left in this book.
                    </div>
                    @elseif($slipSequence->getRemainingSlips() <= 0)
                    <div class="alert alert-danger mt-3 mb-0">
                        <i class="fas fa-ban me-1"></i>
                        Book is full! Please create or activate the next sequence book.
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
                    <i class="fas fa-receipt me-2"></i>Assigned Slips & Linked Records
                </h6>
                <small class="text-muted">
                    Slips issued from book range <strong>{{ $slipSequence->book_start_no }} &ndash; {{ $slipSequence->book_end_no }}</strong> for <strong>{{ $slipSequence->store->name }}</strong>
                </small>
            </div>
            <div class="d-flex align-items-center gap-2">
                <span class="badge bg-success">{{ $assignedSlips->count() }} Assigned</span>
                <span class="badge bg-primary">Next: #{{ $slipSequence->getNextSlipNumber() }}</span>
                <span class="badge bg-secondary">{{ $slipSequence->getRemainingSlips() }} Remaining</span>
                <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#bookMapCollapse" aria-expanded="false" aria-controls="bookMapCollapse">
                    <i class="fas fa-th me-1"></i>Book Range Map
                </button>
            </div>
        </div>

        <!-- Collapsible Book Leaves Map -->
        <div class="collapse border-bottom bg-light p-3" id="bookMapCollapse">
            <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">
                <span class="small fw-bold text-muted text-uppercase">
                    <i class="fas fa-bookmark me-1"></i>Book Range Leaves ({{ $slipSequence->book_start_no }} &ndash; {{ $slipSequence->book_end_no }})
                </span>
                <div class="small d-flex gap-3 text-muted">
                    <span><span class="badge bg-success px-2 py-0">&nbsp;</span> Assigned</span>
                    <span><span class="badge bg-primary px-2 py-0">&nbsp;</span> Next Available</span>
                    <span><span class="badge bg-warning text-dark px-2 py-0">&nbsp;</span> Unrecorded / Gap</span>
                    <span><span class="badge bg-light border text-muted px-2 py-0">&nbsp;</span> Available</span>
                </div>
            </div>
            <div class="d-flex flex-wrap gap-1" style="max-height: 180px; overflow-y: auto;">
                @foreach($bookMap as $leaf)
                    @if($leaf['status'] === 'assigned')
                        <button type="button" 
                                class="btn btn-sm btn-success px-2 py-0 font-monospace"
                                style="font-size: 0.75rem;"
                                title="Slip #{{ $leaf['formatted'] }} - Click to filter row"
                                onclick="filterSlipRow('{{ $leaf['number'] }}')">
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

        <!-- Filter Bar -->
        <div class="p-3 border-bottom bg-white d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div class="d-flex align-items-center gap-2 flex-grow-1" style="max-width: 420px;">
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-light"><i class="fas fa-search text-muted"></i></span>
                    <input type="text" id="slipSearchInput" class="form-control" placeholder="Search slip #, PR #, project, supplier, material...">
                </div>
                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="resetSlipSearch()">
                    Reset
                </button>
            </div>
            <div class="small text-muted">
                Showing <strong>{{ $assignedSlips->count() }}</strong> assigned records
            </div>
        </div>

        <!-- Table View -->
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-striped mb-0" id="assignedSlipsTable">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 110px;">Slip #</th>
                            <th style="width: 80px;">Type</th>
                            <th style="min-width: 170px;">Linked Document</th>
                            <th style="min-width: 220px;">Purchase Request (PR)</th>
                            <th style="min-width: 160px;">Supplier / Source</th>
                            <th style="width: 120px;">Items</th>
                            <th style="width: 130px;">Handled By & Date</th>
                            <th style="width: 95px;">Status</th>
                            <th style="width: 110px;" class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($assignedSlips as $slip)
                        <tr class="slip-row {{ $slip['is_void'] ? 'table-danger' : '' }}" 
                            id="slip-row-{{ $slip['numeric_no'] }}"
                            data-slip-no="{{ $slip['slip_no'] }}"
                            data-numeric-no="{{ $slip['numeric_no'] }}">
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

                            <!-- Type -->
                            <td>
                                @if($slip['slip_type'] === 'receive')
                                <span class="badge bg-success"><i class="fas fa-arrow-down me-1"></i>GRN</span>
                                @else
                                <span class="badge bg-info"><i class="fas fa-arrow-up me-1"></i>SIN</span>
                                @endif
                            </td>

                            <!-- Linked Document -->
                            <td>
                                <div>
                                    <a href="{{ $slip['document_url'] }}" class="fw-bold text-primary text-decoration-none" target="_blank">
                                        <i class="fas fa-file-invoice me-1"></i>{{ $slip['document_ref'] }}
                                    </a>
                                </div>
                                <small class="text-muted d-block">Store: {{ $slip['store_name'] }}</small>
                            </td>

                            <!-- Linked Purchase Request -->
                            <td>
                                @if($slip['purchase_request_id'])
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
                                <span class="text-muted small">&ndash; Direct / Store Transfer &ndash;</span>
                                @if($slip['project_name'] && $slip['project_name'] !== 'N/A')
                                <div><span class="badge bg-light text-dark border mt-1" style="font-size: 0.72rem;">{{ $slip['project_name'] }}</span></div>
                                @endif
                                @endif
                            </td>

                            <!-- Supplier / Source -->
                            <td>
                                <div class="fw-semibold text-dark text-truncate" style="max-width: 180px;" title="{{ $slip['supplier_name'] }}">
                                    {{ $slip['supplier_name'] }}
                                </div>
                                @if($slip['purchase_order_ref'])
                                <small class="text-muted font-monospace d-block">PO: {{ $slip['purchase_order_ref'] }}</small>
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
    </div>
</div>

<!-- Modals placed OUTSIDE of the table structure to ensure valid HTML and prevent styling breaks -->
@foreach($assignedSlips as $slip)
    @if($slip['items_count'] > 0)
    <div class="modal fade" id="itemsModal-{{ $slip['numeric_no'] }}" tabindex="-1" aria-labelledby="itemsModalLabel-{{ $slip['numeric_no'] }}" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content shadow">
                <div class="modal-header py-2">
                    <h6 class="modal-title fw-bold" id="itemsModalLabel-{{ $slip['numeric_no'] }}">
                        <i class="fas fa-boxes me-2 text-primary"></i>Items on Slip #{{ $slip['slip_no'] }}
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
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
    @endif
@endforeach

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {
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

    function filterSlipRow(slipNum) {
        var searchInput = document.getElementById('slipSearchInput');
        if (searchInput) {
            searchInput.value = slipNum;
            var event = new Event('input');
            searchInput.dispatchEvent(event);
        }

        var targetRow = document.getElementById('slip-row-' + slipNum);
        if (targetRow) {
            targetRow.scrollIntoView({ behavior: 'smooth', block: 'center' });
            targetRow.classList.add('table-warning');
            setTimeout(function() {
                targetRow.classList.remove('table-warning');
            }, 2000);
        }
    }

    function resetSlipSearch() {
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
