@extends('layouts.app')

@section('title', 'Material Request Details')

@section('content')
<div class="d-flex align-items-center mb-4">
    <a href="{{ route('material-requests.index') }}" class="btn btn-sm btn-outline-secondary me-3">
        <i class="fa-solid fa-arrow-left"></i>
    </a>
    <h1 class="h3 mb-0 me-3">MR: {{ $materialRequest->reference_number }}</h1>
    
    @php
        $authUser = auth()->user();
        $rawUserRoles = $authUser ? $authUser->roles->pluck('name')->map(fn($r) => strtolower(str_replace([' ', '-'], '_', trim($r))))->toArray() : [];
        $isGlobalAdmin = ($authUser && $authUser->isGlobalAdmin()) || in_array('global_admin', $rawUserRoles) || in_array('admin', $rawUserRoles);

        $badge = match($materialRequest->status) {
            'draft' => 'secondary',
            'pending_planning', 'submitted' => 'warning',
            'planning_approved' => 'info',
            'sent_to_store_manager' => 'primary',
            'transferred', 'transfer_created' => 'info',
            'sent_to_pr', 'fulfilled', 'approved' => 'success',
            'rejected', 'cancelled' => 'danger',
            default => 'secondary'
        };
        $statusLabel = match($materialRequest->status) {
            'draft' => 'DRAFT',
            'pending_planning', 'submitted' => 'PENDING PLANNING',
            'planning_approved' => 'PLANNING APPROVED',
            'sent_to_store_manager' => 'SENT TO STORE MANAGER',
            'sent_to_pr' => 'SENT TO PR',
            'transferred', 'transfer_created' => 'TRANSFERRED (LOCKED IN PROCUREMENT)',
            'fulfilled' => 'FULFILLED',
            'approved' => 'APPROVED',
            'rejected' => 'REJECTED',
            'cancelled' => 'DELETED & LOCKED',
            default => strtoupper($materialRequest->status)
        };
    @endphp
    <span class="badge bg-{{ $badge }} me-3">{{ $statusLabel }}</span>
    <span class="badge bg-light text-muted border px-2 py-1 small me-3" title="Records are locked after creation. Only Global Admin can delete or add materials.">
        <i class="fa-solid fa-lock me-1 text-secondary"></i> Locked After Creation
    </span>
    
    <div class="ms-auto d-flex align-items-center gap-2 flex-wrap">
        @if($materialRequest->status === 'cancelled')
        <span class="badge bg-danger text-white py-2 px-3 fw-semibold">
            <i class="fa-solid fa-lock me-1"></i> Deleted &amp; Locked
        </span>
        @elseif(in_array($materialRequest->status, ['transferred', 'transfer_created']))
        <span class="badge bg-info text-white py-2 px-3 fw-semibold">
            <i class="fa-solid fa-lock me-1"></i> Transferred &amp; Locked
        </span>
        @endif

        @if($isGlobalAdmin && $materialRequest->status !== 'cancelled')
        <button type="button" class="btn btn-outline-primary shadow-sm fw-semibold" data-bs-toggle="modal" data-bs-target="#adminMergeMrModal" title="Merge other Material Requests into this MR">
            <i class="fa-solid fa-code-merge me-1"></i> Merge MR (Admin)
        </button>
        <button type="button" class="btn btn-outline-warning shadow-sm fw-semibold" data-bs-toggle="modal" data-bs-target="#adminSeparateMrModal" title="Separate / split items from this MR into a new MR">
            <i class="fa-solid fa-scissors me-1"></i> Separate MR (Admin)
        </button>
        <button type="button" class="btn btn-outline-danger shadow-sm fw-semibold" data-bs-toggle="modal" data-bs-target="#deleteMrModal">
            <i class="fa-solid fa-trash-can me-1"></i> Delete MR (Admin Only)
        </button>
        @endif

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
        
        {{-- Planning Manager Action --}}
        @if(in_array($materialRequest->status, ['pending_planning', 'submitted']))
            @php
                $userRoles = auth()->user() ? auth()->user()->roles->pluck('name')->map(fn($r) => strtolower(str_replace([' ', '-'], '_', trim($r))))->toArray() : [];
                $isPlanMgr = in_array('planning_manager', $userRoles);
            @endphp
            @if($isPlanMgr)
            <form method="POST" action="{{ route('material-requests.planning-approve', $materialRequest) }}" class="d-inline">
                @csrf
                <button type="submit" class="btn btn-success" onclick="return confirm('Approve request and send to Coordinator?');">
                    <i class="fa-solid fa-check me-1"></i> Approve & Send to Coordinator
                </button>
            </form>
            <button class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#rejectPlanningModal">
                <i class="fa-solid fa-xmark me-1"></i> Reject
            </button>
            @else
            <span class="badge bg-secondary-subtle text-secondary border px-3 py-2 fw-semibold">
                <i class="fas fa-user-clock me-1"></i> Awaiting Planning Manager Decision (View Only)
            </span>
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

        @if(!in_array($materialRequest->status, ['transferred', 'transfer_created', 'cancelled']))
            @php
                $linkedPr = $materialRequest->purchaseRequests->first();
            @endphp
            @if($linkedPr)
                <a href="{{ route('purchase-requests.show', $linkedPr->id) }}" class="btn btn-primary shadow-sm fw-semibold">
                    <i class="fas fa-route me-1"></i> Track PR Flow ({{ $linkedPr->pr_no }})
                </a>
            @else
                <form action="{{ route('material-requests.convert-to-pr', $materialRequest) }}" method="POST" class="d-inline">
                    @csrf
                    <button type="submit" class="btn btn-primary shadow-sm fw-semibold">
                        <i class="fas fa-play me-1"></i> Launch PR Procurement Flow
                    </button>
                </form>
            @endif
        @endif
    </div>
</div>

{{-- Top Alerts for Transferred or Deleted Records (Locked in Procurement) --}}
@if(in_array($materialRequest->status, ['transferred', 'transfer_created']))
    <div class="alert alert-info border-start border-4 border-info shadow-sm mb-4 d-flex align-items-center justify-content-between flex-wrap gap-2">
        <div class="d-flex align-items-center gap-3">
            <div class="p-3 bg-info bg-opacity-25 rounded-circle text-info">
                <i class="fa-solid fa-truck-moving fa-2x"></i>
            </div>
            <div>
                <h5 class="fw-bold mb-0 text-dark">Fulfilled via Store Transfer — Locked in Procurement</h5>
                <p class="text-muted small mb-0">This Material Request was routed to and fulfilled via Store Transfer. It is completed and permanently locked in Procurement.</p>
            </div>
        </div>
        <span class="badge bg-info text-white px-3 py-2 fs-6 rounded-pill">
            <i class="fa-solid fa-lock me-1"></i> Transferred &amp; Locked
        </span>
    </div>
@elseif($materialRequest->status === 'cancelled')
    <div class="alert alert-danger border-start border-4 border-danger shadow-sm mb-4 d-flex align-items-center justify-content-between flex-wrap gap-2">
        <div class="d-flex align-items-center gap-3">
            <div class="p-3 bg-danger bg-opacity-25 rounded-circle text-danger">
                <i class="fa-solid fa-trash-can fa-2x"></i>
            </div>
            <div>
                <h5 class="fw-bold mb-0 text-danger">Record Deleted &amp; Locked</h5>
                <p class="text-muted small mb-0">This Material Request was removed and moved to the Deleted category. All stages, edits, and actions are permanently locked.</p>
            </div>
        </div>
        <span class="badge bg-danger text-white px-3 py-2 fs-6 rounded-pill">
            <i class="fa-solid fa-lock me-1"></i> Deleted &amp; Locked
        </span>
    </div>
@endif

@if($materialRequest->merged_into_mr_id)
    <div class="alert alert-info border-info d-flex align-items-center gap-3 shadow-sm mb-4">
        <i class="fa-solid fa-code-merge fa-2x text-info"></i>
        <div>
            <h5 class="mb-1 fw-bold text-dark">This Material Request was Merged</h5>
            <p class="mb-0 text-secondary">
                All items from this request have been merged into 
                <a href="{{ route('material-requests.show', $materialRequest->merged_into_mr_id) }}" class="fw-bold text-primary text-decoration-underline">
                    MR #{{ $materialRequest->mergedInto?->reference_number ?? $materialRequest->merged_into_mr_id }}
                </a>.
                @if($materialRequest->merge_notes)
                    <span class="d-block small mt-1 text-muted">{{ $materialRequest->merge_notes }}</span>
                @endif
            </p>
        </div>
    </div>
@endif
@if($materialRequest->mergedRequests && $materialRequest->mergedRequests->isNotEmpty())
    <div class="alert alert-success border-success d-flex align-items-center gap-3 shadow-sm mb-4">
        <i class="fa-solid fa-boxes-packing fa-2x text-success"></i>
        <div>
            <h5 class="mb-1 fw-bold text-dark">Merged Requests Incorporated</h5>
            <p class="mb-0 text-secondary">
                This request incorporates items merged from:
                @foreach($materialRequest->mergedRequests as $mReq)
                    <a href="{{ route('material-requests.show', $mReq->id) }}" class="badge bg-light text-dark border me-1 text-decoration-none">
                        #{{ $mReq->reference_number }}
                    </a>
                @endforeach
            </p>
        </div>
    </div>
@endif

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

@if($linkedPr)
<div class="alert alert-primary bg-primary bg-opacity-10 border-primary border-opacity-25 d-flex align-items-center justify-content-between p-3 rounded-3 shadow-sm mb-4 flex-wrap gap-2">
    <div class="d-flex align-items-center gap-3">
        <div class="bg-primary text-white rounded-circle p-2 d-flex align-items-center justify-content-center" style="width:42px;height:42px;">
            <i class="fas fa-route fs-5"></i>
        </div>
        <div>
            <div class="fw-bold text-dark mb-0">Active in Complete Procurement Lifecycle as <span class="text-primary font-monospace">PR #{{ $linkedPr->pr_no }}</span></div>
            <div class="small text-muted">
                Current Stage: <span class="badge bg-{{ \App\Models\PurchaseRequest::statusBadgeClass($linkedPr->status) }}">{{ $linkedPr->status_label }}</span>
                &bull; Current Owner: <strong>{{ ucfirst(str_replace('_', ' ', $linkedPr->current_owner_role ?? 'Store Review')) }}</strong>
                &bull; Items: <strong>{{ $linkedPr->items->count() }} materials</strong>
            </div>
        </div>
    </div>
    <a href="{{ route('purchase-requests.show', $linkedPr->id) }}" class="btn btn-sm btn-primary fw-semibold shadow-xs">
        <i class="fas fa-arrow-up-right-from-square me-1"></i> View &amp; Action in PR Flow
    </a>
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
                    <tr><td class="text-muted">Project</td><td class="fw-semibold">{{ $materialRequest->project->name ?? 'Central / HQ' }}</td></tr>
                    <tr><td class="text-muted">Deliver To</td><td class="fw-semibold">{{ $materialRequest->store->name ?? 'General Store' }} ({{ $materialRequest->store->code ?? '-' }})</td></tr>
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
                    <div class="fw-semibold text-dark">{{ $materialRequest->creator->name ?? 'Staff' }}</div>
                    <div>{{ optional($materialRequest->created_at)->format('d M Y H:i') ?? 'â€”' }}</div>
                </div>
                <div>
                    <div class="text-uppercase fw-bold mb-1">Approved By</div>
                    <div class="fw-semibold text-dark">{{ $materialRequest->approver->name ?? 'â€”' }}</div>
                    <div>{{ $materialRequest->approved_at->format('d M Y H:i') ?? 'â€”' }}</div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-header bg-transparent py-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
        <h5 class="mb-0">Requested Materials</h5>
        @if($isGlobalAdmin && $materialRequest->status === 'draft')
        <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addMrItemModal">
            <i class="fa-solid fa-plus me-1"></i> Add Item (Admin Only)
        </button>
        @elseif(!$isGlobalAdmin)
        <span class="badge bg-light text-muted border py-2 px-3 fw-normal" title="Records are locked after creation. Only Global Admin can add or modify materials.">
            <i class="fa-solid fa-lock me-1 text-secondary"></i> Materials Locked
        </span>
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
                        @if($isGlobalAdmin && $materialRequest->status === 'draft')
                        <td class="text-end">
                            <form method="POST" action="{{ route('mr-items.destroy', $item) }}"
                                  class="d-inline" onsubmit="return confirm('Remove this item?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-outline-danger" title="Remove Item (Admin Only)">
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

@if($isGlobalAdmin && $materialRequest->status === 'draft')
<div class="modal fade" id="addMrItemModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="{{ route('mr-items.store', $materialRequest) }}">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Add Requested Item (Global Admin)</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label">Product / Material <span class="text-danger">*</span></label>
                            <select name="product_id" class="form-select" required>
                                <option value="">â€” Select Product â€”</option>
                                @foreach($products as $p)
                                <option value="{{ $p->id }}">{{ $p->name }} ({{ $p->code }}) â€“ {{ $p->unit }}</option>
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

@if($isGlobalAdmin)
<!-- Modal: Merge Material Requests into this MR (General Admin Only) -->
<div class="modal fade" id="adminMergeMrModal" tabindex="-1" aria-labelledby="adminMergeMrModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <form action="{{ route('material-requests.admin-merge', $materialRequest) }}" method="POST">
                @csrf
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title fs-6 fw-bold" id="adminMergeMrModalLabel">
                        <i class="fa-solid fa-code-merge me-2"></i>Merge Material Requests into #{{ $materialRequest->reference_number }}
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4 text-start">
                    <div class="alert alert-info small mb-3">
                        <i class="fa-solid fa-info-circle me-1"></i>
                        Select one or more material requests to merge into <strong>#{{ $materialRequest->reference_number }}</strong>.
                        The items from the selected requests will be combined into this MR, and the source requests will be marked as cancelled/merged with full audit logs.
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold small text-muted text-uppercase">Select Material Requests to Merge <span class="text-danger">*</span></label>
                        @if(isset($mergeableMrs) && $mergeableMrs->isNotEmpty())
                            <div class="border rounded p-2" style="max-height: 220px; overflow-y: auto;">
                                @foreach($mergeableMrs as $mMr)
                                    <div class="form-check py-1 border-bottom border-light">
                                        <input class="form-check-input" type="checkbox" name="source_mr_ids[]" value="{{ $mMr->id }}" id="mergeMr_{{ $mMr->id }}">
                                        <label class="form-check-label d-flex justify-content-between align-items-center w-100 pe-2" for="mergeMr_{{ $mMr->id }}">
                                            <span>
                                                <strong>#{{ $mMr->reference_number }}</strong>
                                                <span class="badge bg-secondary ms-1 small">{{ strtoupper($mMr->status) }}</span>
                                            </span>
                                            <span class="text-muted small">{{ $mMr->created_at?->format('M d, Y') }}</span>
                                        </label>
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <p class="text-muted small mb-0 fst-italic">No other active material requests found to merge.</p>
                        @endif
                    </div>

                    <div class="mb-3">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="consolidate_duplicates" id="consolidateDuplicatesMr" value="1" checked>
                            <label class="form-check-label fw-semibold" for="consolidateDuplicatesMr">
                                Consolidate duplicate products (sum requested quantities together)
                            </label>
                        </div>
                    </div>

                    <div class="mb-2">
                        <label class="form-label fw-bold small text-muted text-uppercase">Merge Justification / Reason</label>
                        <textarea name="merge_notes" class="form-control form-control-sm" rows="2" placeholder="Explain the reason for merging these material requests..."></textarea>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm fw-bold"><i class="fa-solid fa-code-merge me-1"></i> Confirm &amp; Merge</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Separate / Split MR into a New MR (General Admin Only) -->
<div class="modal fade" id="adminSeparateMrModal" tabindex="-1" aria-labelledby="adminSeparateMrModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <form action="{{ route('material-requests.admin-separate', $materialRequest) }}" method="POST">
                @csrf
                <div class="modal-header bg-warning text-dark">
                    <h5 class="modal-title fs-6 fw-bold" id="adminSeparateMrModalLabel">
                        <i class="fa-solid fa-scissors me-2"></i>Separate / Split Items from #{{ $materialRequest->reference_number }}
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4 text-start">
                    <div class="alert alert-warning small mb-3">
                        <i class="fa-solid fa-info-circle me-1"></i>
                        Select items and specify the split quantity to move to a brand new Material Request.
                        Original items will be reduced or transferred, and a new MR will be created with its own unique reference number.
                    </div>

                    <div class="table-responsive mb-3 border rounded">
                        <table class="table table-sm table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 40px;" class="text-center">Select</th>
                                    <th>Material / Item</th>
                                    <th style="width: 100px;">Current Qty</th>
                                    <th style="width: 140px;">Split Qty</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($materialRequest->items as $mItem)
                                    <tr>
                                        <td class="text-center">
                                            <input type="checkbox" name="items[{{ $mItem->id }}][selected]" value="1" class="form-check-input mr-split-chk" id="mr_split_chk_{{ $mItem->id }}" onchange="toggleMrSplitInput({{ $mItem->id }})">
                                        </td>
                                        <td>
                                            <label for="mr_split_chk_{{ $mItem->id }}" class="mb-0 fw-semibold cursor-pointer">
                                                {{ $mItem->product->name ?? ('Item #' . $mItem->product_id) }}
                                            </label>
                                            @if($mItem->notes)
                                                <small class="text-muted d-block">{{ Str::limit($mItem->notes, 40) }}</small>
                                            @endif
                                        </td>
                                        <td><span class="badge bg-light text-dark border">{{ (float)$mItem->quantity_requested }}</span></td>
                                        <td>
                                            <input type="number" step="0.001" min="0.001" max="{{ (float)$mItem->quantity_requested }}" name="items[{{ $mItem->id }}][quantity]" value="{{ (float)$mItem->quantity_requested }}" class="form-control form-control-sm mr-split-input" id="mr_split_qty_{{ $mItem->id }}" disabled>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="mb-2">
                        <label class="form-label fw-bold small text-muted text-uppercase">Separation Justification / Reason</label>
                        <textarea name="separate_notes" class="form-control form-control-sm" rows="2" placeholder="Explain the reason for separating these items..."></textarea>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning btn-sm fw-bold"><i class="fa-solid fa-scissors me-1"></i> Confirm &amp; Separate</button>
                </div>
            </form>
        </div>
    </div>
</div>
<script>
function toggleMrSplitInput(itemId) {
    var chk = document.getElementById('mr_split_chk_' + itemId);
    var input = document.getElementById('mr_split_qty_' + itemId);
    if (chk && input) {
        input.disabled = !chk.checked;
    }
}
</script>

<!-- Modal: Delete Material Request (Global Admin Only) -->
<div class="modal fade" id="deleteMrModal" tabindex="-1" aria-labelledby="deleteMrModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <form action="{{ route('material-requests.destroy', $materialRequest) }}" method="POST">
                @csrf
                @method('DELETE')
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title fs-6 fw-bold" id="deleteMrModalLabel">
                        <i class="fa-solid fa-lock me-2"></i>Move Material Request to Deleted &amp; Lock
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4 text-start">
                    <p class="mb-2">Are you sure you want to delete <strong>MR: {{ $materialRequest->reference_number }}</strong>?</p>
                    <div class="alert alert-warning small mb-3">
                        <i class="fa-solid fa-shield-halved me-1"></i> <strong>Strict Lock Policy:</strong> Non-admin users cannot delete records. As Global Admin, this action will move this Material Request to the <strong>Deleted &amp; Cancelled</strong> category with all requested materials preserved and permanently locked.
                    </div>
                    <label class="form-label small fw-bold text-muted text-uppercase">Deletion Reason / Remarks</label>
                    <textarea name="reason" class="form-control form-control-sm" rows="2" placeholder="Explain why this requisition is being removed..."></textarea>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger btn-sm fw-bold"><i class="fa-solid fa-trash-can me-1"></i> Move to Deleted &amp; Lock</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif
@endsection
