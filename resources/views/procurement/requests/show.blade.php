@extends('layouts.app')

@section('title', 'Material Request Details')

@section('content')
<div class="d-flex align-items-center mb-4">
    <a href="{{ route('material-requests.index') }}" class="btn btn-sm btn-outline-secondary me-3">
        <i class="fa-solid fa-arrow-left"></i>
    </a>
    <h1 class="h3 mb-0 me-3">MR: {{ $materialRequest->reference_number }}</h1>
    
    @php
        $badge = match($materialRequest->status) {
            'draft' => 'secondary',
            'pending_planning', 'submitted' => 'warning',
            'planning_approved' => 'info',
            'sent_to_store_manager' => 'primary',
            'sent_to_pr', 'transfer_created', 'fulfilled', 'approved' => 'success',
            'rejected' => 'danger',
            default => 'secondary'
        };
        $statusLabel = match($materialRequest->status) {
            'draft' => 'DRAFT',
            'pending_planning', 'submitted' => 'PENDING PLANNING',
            'planning_approved' => 'PLANNING APPROVED',
            'sent_to_store_manager' => 'SENT TO STORE MANAGER',
            'sent_to_pr' => 'SENT TO PR',
            'transfer_created' => 'TRANSFER CREATED',
            'fulfilled' => 'FULFILLED',
            'approved' => 'APPROVED',
            'rejected' => 'REJECTED',
            default => strtoupper($materialRequest->status)
        };
    @endphp
    <span class="badge bg-{{ $badge }} me-3">{{ $statusLabel }}</span>
    
    <div class="ms-auto d-flex gap-2">
        {{-- Site Engineer Action --}}
        @if($materialRequest->status === 'draft')
            <form method="POST" action="{{ route('material-requests.updateStatus', $materialRequest) }}">
                @csrf
                <input type="hidden" name="status" value="pending_planning">
                <button type="submit" class="btn btn-primary" onclick="return confirm('Send this request to Planning Manager for approval?');">
                    <i class="fa-solid fa-paper-plane me-1"></i> Send to Planning Manager
                </button>
            </form>
        @endif
        
        {{-- Planning Manager / Team Action --}}
        @if(in_array($materialRequest->status, ['pending_planning', 'submitted']))
            @if(auth()->user()->hasAnyRole(['planning_manager', 'Planning Manager', 'planning_team', 'admin', 'global_admin']) || auth()->user()->can('material_requests.planning_approve'))
            <form method="POST" action="{{ route('material-requests.planning-approve', $materialRequest) }}" class="d-inline">
                @csrf
                <button type="submit" class="btn btn-success" onclick="return confirm('Approve request and send to Coordinator?');">
                    <i class="fa-solid fa-check me-1"></i> Approve & Send to Coordinator
                </button>
            </form>
            <button class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#rejectPlanningModal">
                <i class="fa-solid fa-xmark me-1"></i> Reject
            </button>
            @endif
        @endif

        {{-- Coordinator Action --}}
        @if($materialRequest->status === 'planning_approved')
            @if(auth()->user()->hasAnyRole(['coordinator', 'Coordinator', 'admin', 'global_admin']) || auth()->user()->can('material_requests.coordinator_dispatch'))
            <form method="POST" action="{{ route('material-requests.coordinator-dispatch', $materialRequest) }}" class="d-inline">
                @csrf
                <button type="submit" class="btn btn-primary" onclick="return confirm('Send this request to Store Manager?');">
                    <i class="fa-solid fa-share me-1"></i> Send to Store Manager
                </button>
            </form>
            @endif
        @endif

        {{-- Store Manager Action --}}
        @if(in_array($materialRequest->status, ['sent_to_store_manager', 'approved']))
            @if(auth()->user()->hasAnyRole(['store_manager', 'Store Manager', 'admin', 'global_admin']) || auth()->user()->can('material_requests.approve'))
            <form method="POST" action="{{ route('material-requests.send-to-pr', $materialRequest) }}" class="d-inline">
                @csrf
                <button type="submit" class="btn btn-warning fw-semibold" onclick="return confirm('Convert this request to Purchase Request (PR)?');">
                    <i class="fa-solid fa-cart-shopping me-1"></i> Send to PR
                </button>
            </form>
            <form method="POST" action="{{ route('material-requests.create-transfer', $materialRequest) }}" class="d-inline">
                @csrf
                <button type="submit" class="btn btn-info text-white fw-semibold" onclick="return confirm('Convert this request to Store Transfer?');">
                    <i class="fa-solid fa-truck-ramp-box me-1"></i> Create Transfer
                </button>
            </form>
            @endif
        @endif
    </div>
</div>

{{-- Rejection Modal --}}
@if(in_array($materialRequest->status, ['pending_planning', 'submitted']))
<div class="modal fade" id="rejectPlanningModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="{{ route('material-requests.planning-reject', $materialRequest) }}">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Reject Material Request</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Reason for Rejection <span class="text-danger">*</span></label>
                        <textarea name="rejection_reason" class="form-control" rows="3" required placeholder="State why this request is being rejected..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Confirm Rejection</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif

<div class="row mb-4">
    <div class="col-md-8">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <h5 class="card-title text-muted mb-4">Request Details</h5>
                <table class="table table-borderless table-sm mb-0">
                    <tr>
                        <td class="text-muted w-25">Source</td>
                        <td>
                            <span class="badge bg-light text-dark border fw-bold"><i class="fa-solid fa-code-branch text-primary me-1"></i>{{ $materialRequest->source ?? 'Manual Creation' }}</span>
                            @if($materialRequest->maintenance_request_id && $materialRequest->maintenanceRequest)
                                <a href="{{ route('general-service.maintenance.show', $materialRequest->maintenanceRequest) }}" class="badge bg-warning text-dark text-decoration-none border ms-1" title="View linked maintenance ticket">
                                    <i class="fa-solid fa-screwdriver-wrench me-1"></i>{{ $materialRequest->maintenanceRequest->request_no }}
                                </a>
                            @endif
                        </td>
                    </tr>
                    <tr><td class="text-muted">Project</td><td class="fw-semibold">{{ $materialRequest->project?->name ?? 'Central / HQ' }}</td></tr>
                    <tr><td class="text-muted">Deliver To</td><td class="fw-semibold">{{ $materialRequest->store?->name ?? 'General Store' }} ({{ $materialRequest->store?->code ?? '-' }})</td></tr>
                    <tr><td class="text-muted">Required By</td><td class="fw-semibold {{ optional($materialRequest->required_date)->isPast() ? 'text-danger' : '' }}">{{ optional($materialRequest->required_date)->format('d M Y') ?? '-' }}</td></tr>
                </table>
                @if($materialRequest->notes)
                <div class="mt-3 pt-3 border-top text-muted small">
                    <strong>Notes:</strong> {{ $materialRequest->notes }}
                </div>
                @endif
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-0 shadow-sm bg-light h-100">
            <div class="card-body text-muted small">
                <div class="mb-3">
                    <div class="text-uppercase fw-bold mb-1">Created By</div>
                    <div class="fw-semibold text-dark">{{ $materialRequest->creator?->name ?? 'Staff' }}</div>
                    <div>{{ optional($materialRequest->created_at)->format('d M Y H:i') ?? '—' }}</div>
                </div>
                <div>
                    <div class="text-uppercase fw-bold mb-1">Approved By</div>
                    <div class="fw-semibold text-dark">{{ $materialRequest->approver?->name ?? '—' }}</div>
                    <div>{{ $materialRequest->approved_at?->format('d M Y H:i') ?? '—' }}</div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-header bg-transparent py-3 d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Requested Materials</h5>
        @if($materialRequest->status === 'draft')
        <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addMrItemModal">
            <i class="fa-solid fa-plus me-1"></i> Add Item
        </button>
        @endif
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Product / Material</th>
                        <th>Category</th>
                        <th class="text-end">Qty Requested</th>
                        <th class="text-end">Qty Fulfilled</th>
                        <th>Notes</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($materialRequest->items as $item)
                    <tr>
                        <td class="fw-semibold">{{ $item->product->name }} <br><code class="small text-muted">{{ $item->product->code }}</code></td>
                        <td>{{ $item->product->category }}</td>
                        <td class="text-end fw-bold">{{ number_format($item->quantity_requested, 3) }} <small class="text-muted">{{ $item->product->unit }}</small></td>
                        <td class="text-end text-success">{{ number_format($item->quantity_fulfilled, 3) }}</td>
                        <td class="small text-muted">{{ $item->notes }}</td>
                        @if($materialRequest->status === 'draft')
                        <td class="text-end">
                            <form method="POST" action="{{ route('mr-items.destroy', $item) }}"
                                  class="d-inline" onsubmit="return confirm('Remove this item?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-outline-danger">
                                    <i class="fa-solid fa-trash"></i>
                                </button>
                            </form>
                        </td>
                        @endif
                    </tr>
                    @empty
                    <tr>
                        <td colspan="{{ $materialRequest->status === 'draft' ? 6 : 5 }}" class="text-center py-4 text-muted">No items added yet.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

{{-- Linked Purchase Requests & Uploaded Receipts (Proof of Purchase) --}}
@if($materialRequest->purchaseRequests && $materialRequest->purchaseRequests->isNotEmpty())
<div class="card border-0 shadow-sm mt-4 border-start border-4 border-success">
    <div class="card-header bg-white py-3">
        <h5 class="mb-0 fw-bold text-dark">
            <i class="fa-solid fa-receipt text-success me-2"></i>Procurement Status & Uploaded Purchase Receipts
        </h5>
    </div>
    <div class="card-body p-3">
        @foreach($materialRequest->purchaseRequests as $pr)
            <div class="p-3 border rounded bg-light mb-3">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-2">
                    <div>
                        <strong class="text-dark">PR: {{ $pr->pr_no }}</strong>
                        <span class="badge bg-{{ \App\Models\PurchaseRequest::statusBadgeClass($pr->status) }} ms-2">
                            {{ $pr->status_label }}
                        </span>
                    </div>
                    <a href="{{ route('purchase-requests.show', $pr->id) }}" class="btn btn-sm btn-outline-primary shadow-sm">
                        <i class="fa-solid fa-arrow-up-right-from-square me-1"></i> View Full PR Details
                    </a>
                </div>

                @if($pr->receipt)
                    @php
                        $rcUrl = \App\Services\FileUploadService::url($pr->receipt->file_path);
                        $ext = strtolower(pathinfo($pr->receipt->file_path, PATHINFO_EXTENSION));
                        $isImg = in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif']);
                        $isPdf = $ext === 'pdf';
                    @endphp
                    <div class="bg-white p-3 rounded border mt-2">
                        <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">
                            <div>
                                <span class="badge bg-success"><i class="fa-solid fa-circle-check me-1"></i>Official Receipt Uploaded</span>
                                <small class="text-muted ms-2">Uploaded by {{ $pr->receipt->uploadedBy->name ?? 'Procurement' }} on {{ $pr->receipt->created_at->format('M d, Y H:i') }}</small>
                            </div>
                            @if($rcUrl)
                            <div class="d-flex gap-2">
                                <a href="{{ $rcUrl }}" class="btn btn-sm btn-outline-primary" target="_blank" download>
                                    <i class="fa-solid fa-download me-1"></i> Download Receipt
                                </a>
                                <a href="{{ $rcUrl }}" class="btn btn-sm btn-primary" target="_blank">
                                    <i class="fa-solid fa-eye me-1"></i> View Receipt
                                </a>
                            </div>
                            @endif
                        </div>

                        @if($rcUrl && $isImg)
                            <div class="text-center p-2 bg-light rounded border mt-2">
                                <a href="{{ $rcUrl }}" target="_blank">
                                    <img src="{{ $rcUrl }}" alt="Purchase Receipt" class="img-fluid rounded shadow-sm" style="max-height: 250px; object-fit: contain;">
                                </a>
                            </div>
                        @elseif($rcUrl && $isPdf)
                            <div class="alert alert-info py-2 px-3 small mb-0 mt-2">
                                <i class="fa-solid fa-file-pdf text-danger me-1"></i> PDF Document Attached: <strong>{{ basename($pr->receipt->file_path) }}</strong>
                                <a href="{{ $rcUrl }}" target="_blank" class="ms-2 fw-bold text-primary">Open PDF</a>
                            </div>
                        @endif
                    </div>
                @else
                    <div class="text-muted small italic mt-2">
                        <i class="fa-solid fa-clock me-1"></i> No purchase receipt uploaded yet for this PR (Current Stage: {{ $pr->status_label }}).
                    </div>
                @endif
            </div>
        @endforeach
    </div>
</div>
@endif

@if($materialRequest->status === 'draft')
<div class="modal fade" id="addMrItemModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="{{ route('mr-items.store', $materialRequest) }}">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Add Requested Item</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label">Product / Material <span class="text-danger">*</span></label>
                            <select name="product_id" class="form-select" required>
                                <option value="">— Select Product —</option>
                                @foreach($products as $p)
                                <option value="{{ $p->id }}">{{ $p->name }} ({{ $p->code }}) – {{ $p->unit }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label">Quantity Required <span class="text-danger">*</span></label>
                            <input type="number" step="0.001" min="0.001" name="quantity_requested"
                                   class="form-control" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Notes (optional)</label>
                            <input type="text" name="notes" class="form-control" placeholder="Specification or remarks">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Item</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif
@endsection
