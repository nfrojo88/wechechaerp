@extends('layouts.app')

@php
    $authUser = auth()->user();
    $rawUserRoles = $authUser ? $authUser->roles->pluck('name')->map(fn($r) => strtolower(str_replace([' ', '-'], '_', trim($r))))->toArray() : [];
    $isGlobalAdmin = ($authUser && $authUser->isGlobalAdmin()) || in_array('global_admin', $rawUserRoles) || in_array('admin', $rawUserRoles) || in_array('super_admin', $rawUserRoles);
    $isAuditorUser = !empty($isAuditor) || in_array('auditor', $rawUserRoles) || in_array('audit', $rawUserRoles) || in_array('internal_auditor', $rawUserRoles) || in_array('audit_team', $rawUserRoles) || ($authUser && $authUser->hasAnyRole(['auditor', 'audit', 'internal_auditor', 'Auditor', 'Audit']));
    $isStoreManagerUser = in_array('store_manager', $rawUserRoles);
    $isStoreKeeperUser = !empty($isStoreKeeper) || in_array('store_keeper', $rawUserRoles) || in_array('storekeeper', $rawUserRoles) || in_array('store_clerk', $rawUserRoles);
    $isCoordinatorUser = in_array('coordinator', $rawUserRoles);
    $isHrUser = in_array('hr_manager', $rawUserRoles) || in_array('hr_officer', $rawUserRoles) || in_array('hr', $rawUserRoles);
    $isPurchaseManagerUser = in_array('purchase_manager', $rawUserRoles) || in_array('procurement_manager', $rawUserRoles);
    $isProcurementTeamUser = in_array('purchase', $rawUserRoles) || in_array('procurement_team', $rawUserRoles) || in_array('purchaser', $rawUserRoles) || in_array('buyer', $rawUserRoles);
    $isMarketingUser = in_array('marketing', $rawUserRoles) || in_array('market_research', $rawUserRoles);
    $isGmUser = in_array('gm', $rawUserRoles) || in_array('general_manager', $rawUserRoles);
    $isFinanceHeadUser = in_array('finance_head', $rawUserRoles) || in_array('finance_manager', $rawUserRoles) || in_array('finance', $rawUserRoles) || in_array('accountant', $rawUserRoles);
    $isGeneralServiceUser = in_array('general_service', $rawUserRoles) || in_array('general_services', $rawUserRoles);
    $currentTab = $activeTab ?? 'pending';
@endphp

@section('title', $isAuditorUser ? 'Procurement Status & Compliance (Read-Only)' : 'Procurement — My Queue & History')

@section('content')
<div class="container-fluid px-4 py-3" id="procurementQueueContainer">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h1 class="h3 mb-0 text-gray-800">
                <i class="fas {{ $isAuditorUser ? 'fa-shield-halved text-info' : 'fa-tasks text-primary' }} me-2"></i>
                {{ $isAuditorUser ? 'Procurement Status & Compliance Trail' : 'Procurement — My Queue' }}
            </h1>
            <p class="text-muted small mb-0">
                {{ $isAuditorUser ? 'Complete internal audit view of all 14 procurement lifecycle stages, proforma quotes, GM approvals, and payment disbursement statuses.' : 'Track pending action queues and monitor full lifecycle history of created requests & requisitions.' }}
            </p>
        </div>
        <div class="d-flex align-items-center gap-2 flex-wrap">
            {{-- Material Visibility Option Toggle (View without saying See Details) --}}
            <div class="btn-group btn-group-sm shadow-xs rounded-pill p-0.5 bg-white border" role="group" id="queueMaterialsOptionGroup" title="Option: View materials directly in the queue without clicking See Details">
                <button type="button" class="btn btn-sm rounded-pill px-3 py-1 fw-semibold btn-primary text-white shadow-xs" id="btnQueueMaterialsExpanded" onclick="setQueueMaterialsMode('expanded')" title="Show full requested materials inline directly in each row">
                    <i class="fa-solid fa-boxes-stacked me-1.5"></i>Show Materials (Inline)
                </button>
                <button type="button" class="btn btn-sm rounded-pill px-3 py-1 fw-semibold text-muted" id="btnQueueMaterialsCompact" onclick="setQueueMaterialsMode('compact')" title="Compact row view">
                    <i class="fa-solid fa-list me-1.5"></i>Compact View
                </button>
            </div>

            <span class="badge bg-light text-muted border px-2 py-2" title="Policy: Once created, entries cannot be deleted and no new materials can be added by standard users.">
                <i class="fas fa-lock me-1 text-secondary"></i> Records Locked After Creation
            </span>
            @if($isGlobalAdmin)
            <span class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25 px-2 py-2 fw-semibold">
                <i class="fas fa-shield-halved me-1"></i> Global Admin Override
            </span>
            @endif
            @if($isGlobalAdmin || $isAdmin || $isPurchaseManagerUser || $isStoreManagerUser)
            <form action="{{ route('procurement.purge-zero-items') }}" method="POST" class="d-inline" onsubmit="return confirm('Clean and purge all empty requisitions that have zero (0) items added?');">
                @csrf
                <button type="submit" class="btn btn-outline-warning shadow-sm fw-bold" title="Purge zero-item empty requisitions">
                    <i class="fas fa-broom me-1"></i> Clean Zero-Item Requests
                </button>
            </form>
            @endif
            @if(!$isAuditorUser)
            <a href="{{ route('purchase-requests.create') }}" class="btn btn-primary shadow-sm fw-bold">
                <i class="fas fa-plus me-1"></i> New Purchase Request
            </a>
            @else
            <span class="badge bg-info text-dark px-3 py-2 fs-6 rounded-pill fw-bold">
                <i class="fa-solid fa-eye me-1"></i> Read-Only Audit Stream
            </span>
            @endif
        </div>
    </div>

    @if($isAuditorUser)
    <div class="alert alert-info border-start border-4 border-info shadow-sm mb-4 d-flex align-items-center justify-content-between flex-wrap gap-2">
        <div class="d-flex align-items-center gap-2">
            <div class="p-2 rounded-circle bg-info bg-opacity-25 text-info">
                <i class="fa-solid fa-shield-halved fa-lg"></i>
            </div>
            <div>
                <strong class="d-block text-dark">Auditor Oversight Mode</strong>
                <span class="text-muted small">You have full read-only inspection visibility across all procurement stages, material requests, quotes, finance releases, and delivery verification.</span>
            </div>
        </div>
        <span class="badge bg-white text-info border border-info px-3 py-2 fw-semibold"><i class="fa-solid fa-lock me-1"></i>Read-Only</span>
    </div>
    @endif

    @if($isStoreKeeperUser && !empty($assignedStore))
    <div class="alert alert-success border-start border-4 border-success shadow-sm mb-4 d-flex align-items-center justify-content-between flex-wrap gap-2">
        <div class="d-flex align-items-center gap-2">
            <div class="p-2 rounded-circle bg-success bg-opacity-25 text-success">
                <i class="fa-solid fa-warehouse fa-lg"></i>
            </div>
            <div>
                <strong class="d-block text-dark">Strict Store Isolation Active: {{ $assignedStore->name }} (Store ID: {{ $assignedStore->id }})</strong>
                <span class="text-muted small">You are viewing purchase requisitions and material intake queues strictly destined for your assigned store location. Requisitions for other stores are restricted.</span>
            </div>
        </div>
        <span class="badge bg-white text-success border border-success px-3 py-2 fw-semibold">
            <i class="fa-solid fa-store me-1"></i>Destination Scoped: {{ $assignedStore->name }}
        </span>
    </div>
    @endif

    <!-- KPI Summary Cards (Clickable Shortcuts) -->
    <div class="row g-3 mb-4">
        <!-- Card 1: Awaiting Your Action -->
        <div class="col-12 col-sm-6 col-xl">
            <a href="{{ route('procurement.my-queue', array_merge(request()->query(), ['tab' => 'pending'])) }}" class="text-decoration-none">
                <div class="card border-0 shadow-sm bg-primary text-white h-100 {{ $currentTab === 'pending' ? 'border border-2 border-white' : '' }}" style="cursor: pointer; transition: transform 0.15s ease;">
                    <div class="card-body p-3 d-flex align-items-center justify-content-between">
                        <div>
                            <div class="text-white-50 small font-weight-bold">{{ $isAuditorUser ? 'Active Cycles' : 'Awaiting Your Action' }}</div>
                            <div class="h2 mb-0 font-weight-bold">{{ $kpi['my_pending'] }}</div>
                        </div>
                        <i class="fas fa-hourglass-half fa-2x text-white-50"></i>
                    </div>
                </div>
            </a>
        </div>

        <!-- Card 2: My Created Requests (Question / Requisition History) -->
        <div class="col-12 col-sm-6 col-xl">
            <a href="{{ route('procurement.my-queue', array_merge(request()->query(), ['tab' => 'my_created'])) }}" class="text-decoration-none">
                <div class="card border-0 shadow-sm text-white h-100 {{ $currentTab === 'my_created' ? 'border border-2 border-white' : '' }}" style="background: linear-gradient(135deg, #0284c7, #0369a1); cursor: pointer; transition: transform 0.15s ease;">
                    <div class="card-body p-3 d-flex align-items-center justify-content-between">
                        <div>
                            <div class="text-white-50 small font-weight-bold">My Created Requests</div>
                            <div class="h2 mb-0 font-weight-bold">{{ $kpi['my_created'] }}</div>
                        </div>
                        <i class="fas fa-clock-rotate-left fa-2x text-white-50"></i>
                    </div>
                </div>
            </a>
        </div>

        <!-- Card 3: Emergency MRs (Planning) -->
        <div class="col-12 col-sm-6 col-xl">
            <div class="card border-0 shadow-sm bg-danger text-white h-100">
                <div class="card-body p-3 d-flex align-items-center justify-content-between">
                    <div>
                        <div class="text-white-50 small font-weight-bold">Emergency MRs (Planning)</div>
                        <div class="h2 mb-0 font-weight-bold">{{ $kpi['emergency_mrs'] }}</div>
                    </div>
                    <i class="fas fa-bolt fa-2x text-white-50"></i>
                </div>
            </div>
        </div>

        <!-- Card 4: Intake Completed -->
        <div class="col-12 col-sm-6 col-xl">
            <a href="{{ route('procurement.my-queue', array_merge(request()->query(), ['tab' => 'completed'])) }}" class="text-decoration-none">
                <div class="card border-0 shadow-sm bg-success text-white h-100 {{ $currentTab === 'completed' ? 'border border-2 border-white' : '' }}" style="cursor: pointer; transition: transform 0.15s ease;">
                    <div class="card-body p-3 d-flex align-items-center justify-content-between">
                        <div>
                            <div class="text-white-50 small font-weight-bold">Intake Completed</div>
                            <div class="h2 mb-0 font-weight-bold">{{ $kpi['completed'] }}</div>
                        </div>
                        <i class="fas fa-check-circle fa-2x text-white-50"></i>
                    </div>
                </div>
            </a>
        </div>

        <!-- Card 5: Deleted & Cancelled (Requisition Archive) -->
        <div class="col-12 col-sm-6 col-xl">
            <a href="{{ route('procurement.my-queue', array_merge(request()->query(), ['tab' => 'deleted'])) }}" class="text-decoration-none">
                <div class="card border-0 shadow-sm text-white h-100 {{ $currentTab === 'deleted' ? 'border border-2 border-white' : '' }}" style="background: linear-gradient(135deg, #64748b, #475569) !important; cursor: pointer; transition: transform 0.15s ease;">
                    <div class="card-body p-3 d-flex align-items-center justify-content-between">
                        <div>
                            <div class="text-white-50 small font-weight-bold">Deleted &amp; Cancelled</div>
                            <div class="h2 mb-0 font-weight-bold">{{ $kpi['deleted'] ?? 0 }}</div>
                        </div>
                        <i class="fas fa-trash-can fa-2x text-white-50"></i>
                    </div>
                </div>
            </a>
        </div>
    </div>

    <!-- Filter Bar -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body p-3">
            <form method="GET" action="{{ route('procurement.my-queue') }}" class="row g-2 align-items-center">
                <input type="hidden" name="tab" value="{{ $currentTab }}">
                <div class="col-md-3">
                    <select name="project_id" class="form-select form-select-sm">
                        <option value="">-- All Projects --</option>
                        @foreach($projects as $p)
                            <option value="{{ $p->id }}" {{ request('project_id') == $p->id ? 'selected' : '' }}>{{ $p->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <select name="status" class="form-select form-select-sm">
                        <option value="">-- All Statuses --</option>
                        @foreach(\App\Models\PurchaseRequest::statusLabels() as $key => $lbl)
                            <option value="{{ $key }}" {{ request('status') == $key ? 'selected' : '' }}>{{ $lbl }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <select name="request_type" class="form-select form-select-sm">
                        <option value="">-- All Request Channels --</option>
                        <option value="mr" {{ request('request_type') == 'mr' ? 'selected' : '' }}>📋 Site Requisitions (MR Flow)</option>
                        <option value="direct_pr" {{ request('request_type') == 'direct_pr' ? 'selected' : '' }}>🏢 Direct Purchase Requests</option>
                    </select>
                </div>
                <div class="col-md-3 d-flex gap-2">
                    <button type="submit" class="btn btn-sm btn-secondary flex-grow-1"><i class="fas fa-filter me-1"></i> Filter</button>
                    <a href="{{ route('procurement.my-queue', ['tab' => $currentTab]) }}" class="btn btn-sm btn-outline-secondary"><i class="fas fa-undo me-1"></i> Reset</a>
                </div>
            </form>
        </div>
    </div>

    <!-- Emergency MRs Section (Visible to Planning Team) -->
    @if($emergencyMrs->count() > 0)
    <div class="card border-warning shadow-sm mb-4">
        <div class="card-header bg-warning bg-opacity-25 border-warning d-flex justify-content-between align-items-center">
            <h5 class="mb-0 text-dark font-weight-bold"><i class="fas fa-bolt text-danger me-2"></i>Emergency Material Requests (Awaiting Planning Approval)</h5>
            <span class="badge bg-danger">{{ $emergencyMrs->count() }} Pending</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Ref No</th>
                            <th>Project</th>
                            <th>Requested By</th>
                            <th>Requested Materials</th>
                            <th>Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($emergencyMrs as $mr)
                        @php
                            $eMrMaterialsJson = $mr->items->map(function($itm) {
                                return [
                                    'name' => $itm->product?->name ?? 'Material',
                                    'code' => $itm->product?->item_code ?? '-',
                                    'quantity' => (float)$itm->quantity,
                                    'unit' => $itm->product?->unit ?? 'Units',
                                    'estimated_unit_cost' => 0,
                                    'estimated_total' => 0,
                                    'specifications' => $itm->notes ?? '',
                                ];
                            });
                        @endphp
                        <tr>
                            <td><strong>{{ $mr->reference_number }}</strong></td>
                            <td>{{ $mr->project?->name }}</td>
                            <td>{{ $mr->requestedBy?->name ?? $mr->creator?->name ?? 'N/A' }}</td>
                            <td class="col-procurement-materials">
                                <div class="d-flex align-items-center justify-content-between mb-1">
                                    <span class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25 px-2 py-0.5 fw-semibold" style="font-size:0.75rem;">
                                        <i class="fa-solid fa-bolt me-1"></i>{{ $mr->items->count() }} Urgent Material(s)
                                    </span>
                                    <button type="button" class="btn btn-xs btn-link text-muted p-0 text-decoration-none btn-toggle-single-materials" onclick="toggleSingleMaterials(this)" title="Toggle items display">
                                        <i class="fa-solid fa-chevron-up"></i>
                                    </button>
                                </div>
                                <div class="procurement-materials-list d-flex flex-column gap-1" style="max-height: 160px; overflow-y: auto;">
                                    @foreach($mr->items as $itm)
                                        <div class="d-flex align-items-center justify-content-between p-1 bg-white rounded border text-nowrap shadow-2xs" style="font-size:0.78rem;">
                                            <span class="fw-semibold text-dark text-truncate me-2" style="max-width: 200px;" title="{{ $itm->product?->name }}">
                                                <i class="fa-solid fa-cube text-danger me-1"></i>{{ $itm->product?->name ?? 'Material' }}
                                            </span>
                                            <span class="badge bg-light text-dark border font-monospace px-1.5 py-0.5 fw-bold">
                                                {{ (float)$itm->quantity }} {{ $itm->product?->unit ?? 'Units' }}
                                            </span>
                                        </div>
                                    @endforeach
                                </div>
                                <div class="procurement-materials-compact-preview d-none text-muted small" style="font-size:11px;">
                                    {{ $mr->items->count() }} item(s): {{ Str::limit($mr->items->map(fn($it) => $it->product?->name)->filter()->join(', '), 30) }}
                                </div>
                            </td>
                            <td>{{ $mr->created_at->format('M d, Y H:i') }}</td>
                            <td>
                                <div class="d-flex gap-1 align-items-center">
                                    <form method="POST" action="{{ route('material-requests.planning-approve', $mr->id) }}">
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-success"><i class="fas fa-check me-1"></i> Approve</button>
                                    </form>
                                    <button type="button" class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#rejectMrModal{{ $mr->id }}">
                                        <i class="fas fa-times me-1"></i> Reject
                                    </button>
                                    @if($isGlobalAdmin)
                                    <button type="button" class="btn btn-sm btn-outline-danger" title="Delete MR (Admin Only)" onclick="openDeleteQueueMrModal({{ $mr->id }}, '{{ addslashes($mr->reference_number) }}')">
                                        <i class="fas fa-trash-can"></i>
                                    </button>
                                    @endif
                                </div>

                                <!-- Reject Modal -->
                                <div class="modal fade" id="rejectMrModal{{ $mr->id }}" tabindex="-1">
                                    <div class="modal-dialog">
                                        <form method="POST" action="{{ route('material-requests.planning-reject', $mr->id) }}" class="modal-content">
                                            @csrf
                                             <div class="modal-header">
                                                <h5 class="modal-title">Reject Emergency Request</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                            </div>
                                            <div class="modal-body">
                                                <label class="form-label">Rejection Reason</label>
                                                <textarea name="rejection_reason" class="form-control" rows="3" required></textarea>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                <button type="submit" class="btn btn-danger">Confirm Rejection</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    @endif

    <!-- Emergency MRs Section Approved by Planning (Awaiting Coordinator Review & Dispatch) -->
    @if(!empty($coordinatorEmergencyMrs) && $coordinatorEmergencyMrs->count() > 0)
    <div class="card border-primary shadow-sm mb-4">
        <div class="card-header bg-primary bg-opacity-10 border-primary d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div>
                <h5 class="mb-0 text-primary font-weight-bold">
                    <i class="fas fa-bolt text-danger me-2"></i>Emergency Material Requests (Approved by Planning — Awaiting Coordinator Action)
                </h5>
                <small class="text-muted">These emergency requests have been approved by the Planning Manager and require your review and dispatch to the Store Manager.</small>
            </div>
            <span class="badge bg-danger rounded-pill px-3 py-2">{{ $coordinatorEmergencyMrs->count() }} Urgent</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Ref No</th>
                            <th>Project &amp; Store</th>
                            <th>Requested By</th>
                            <th>Planning Approval</th>
                            <th>Items</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($coordinatorEmergencyMrs as $cmr)
                        @php
                            $compPr = $cmr->purchaseRequests->first();
                        @endphp
                        <tr>
                            <td>
                                <strong>{{ $cmr->reference_number }}</strong>
                                @if($compPr)
                                    <div class="small">
                                        <a href="{{ route('purchase-requests.show', $compPr->id) }}" class="badge bg-light text-primary border text-decoration-none">
                                            PR #{{ $compPr->pr_no }}
                                        </a>
                                    </div>
                                @endif
                            </td>
                            <td>
                                <strong>{{ $cmr->project?->name ?? 'General Project' }}</strong>
                                <small class="text-muted d-block"><i class="fas fa-warehouse me-1"></i>Store: {{ $cmr->store?->name ?? $cmr->destinationStore?->name ?? 'N/A' }}</small>
                            </td>
                            <td>{{ $cmr->requestedBy?->name ?? $cmr->creator?->name ?? 'Site Engineer' }}</td>
                            <td>
                                <span class="badge bg-success-subtle text-success border border-success-subtle">
                                    <i class="fas fa-check-circle me-1"></i>Planning Approved
                                </span>
                                <small class="text-muted d-block">{{ optional($cmr->planning_approved_at)->format('M d, Y H:i') ?? $cmr->updated_at->format('M d, Y') }}</small>
                            </td>
                            @php
                                $cmrMaterialsJson = $cmr->items->map(function($itm) {
                                    return [
                                        'name' => $itm->product?->name ?? 'Material',
                                        'code' => $itm->product?->item_code ?? '-',
                                        'quantity' => (float)$itm->quantity,
                                        'unit' => $itm->product?->unit ?? 'Units',
                                        'estimated_unit_cost' => 0,
                                        'estimated_total' => 0,
                                        'specifications' => $itm->notes ?? '',
                                    ];
                                });
                            @endphp
                            <td class="col-procurement-materials">
                                <div class="d-flex align-items-center justify-content-between mb-1">
                                    <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 px-2 py-0.5 fw-semibold" style="font-size:0.75rem;">
                                        <i class="fa-solid fa-boxes-stacked me-1"></i>{{ $cmr->items->count() }} Material(s)
                                    </span>
                                    <button type="button" class="btn btn-xs btn-link text-muted p-0 text-decoration-none btn-toggle-single-materials" onclick="toggleSingleMaterials(this)" title="Toggle items display">
                                        <i class="fa-solid fa-chevron-up"></i>
                                    </button>
                                </div>
                                <div class="procurement-materials-list d-flex flex-column gap-1" style="max-height: 160px; overflow-y: auto;">
                                    @foreach($cmr->items as $it)
                                        <div class="d-flex align-items-center justify-content-between p-1 bg-white rounded border text-nowrap shadow-2xs" style="font-size:0.78rem;">
                                            <span class="fw-semibold text-dark text-truncate me-2" style="max-width: 200px;" title="{{ $it->product?->name }}">
                                                <i class="fa-solid fa-cube text-primary me-1"></i>{{ $it->product?->name ?? 'Material' }}
                                            </span>
                                            <span class="badge bg-light text-dark border font-monospace px-1.5 py-0.5 fw-bold">
                                                {{ (float)$it->quantity }} {{ $it->product?->unit ?? 'Units' }}
                                            </span>
                                        </div>
                                    @endforeach
                                </div>
                                <div class="procurement-materials-compact-preview d-none text-muted small" style="font-size:11px;">
                                    {{ $cmr->items->count() }} item(s): {{ Str::limit($cmr->items->map(fn($it) => $it->product?->name)->filter()->join(', '), 30) }}
                                </div>
                            </td>
                            <td class="text-end">
                                <div class="d-flex justify-content-end align-items-center gap-1">
                                    <button type="button" class="btn btn-sm btn-outline-primary shadow-xs fw-semibold btn-queue-quick-materials"
                                            data-ref="{{ $cmr->reference_number }}"
                                            data-project="{{ $cmr->project?->name ?? 'General Project' }}"
                                            data-store="{{ $cmr->store?->name ?? $cmr->destinationStore?->name ?? 'N/A' }}"
                                            data-url="{{ $compPr ? route('purchase-requests.show', $compPr->id) : route('material-requests.show', $cmr) }}"
                                            data-items="{{ json_encode($cmrMaterialsJson) }}"
                                            onclick="openQuickMaterialsModalFromBtn(this)"
                                            title="View materials without saying See Details">
                                        <i class="fa-solid fa-boxes-stacked me-1"></i>Materials ({{ $cmr->items->count() }})
                                    </button>
                                    @if($compPr)
                                        <form method="POST" action="{{ route('purchase-requests.coordinator-approve', $compPr) }}" class="d-inline">
                                            @csrf
                                            <button type="submit" class="btn btn-sm btn-primary fw-bold shadow-xs">
                                                <i class="fas fa-share me-1"></i> Dispatch to Store
                                            </button>
                                        </form>
                                        <a href="{{ route('purchase-requests.show', $compPr->id) }}" class="btn btn-sm btn-outline-info shadow-xs">
                                            <i class="fas fa-eye me-1"></i> Review
                                        </a>
                                    @else
                                        <form method="POST" action="{{ route('material-requests.coordinator-dispatch', $cmr) }}" class="d-inline">
                                            @csrf
                                            <button type="submit" class="btn btn-sm btn-primary fw-bold shadow-xs">
                                                <i class="fas fa-share me-1"></i> Dispatch to Store
                                            </button>
                                        </form>
                                        <a href="{{ route('material-requests.show', $cmr) }}" class="btn btn-sm btn-outline-info shadow-xs">
                                            <i class="fas fa-eye me-1"></i> Review
                                        </a>
                                    @endif
                                </div>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    @endif

    <!-- Main Navigation Card with Queue & History Tabs -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
            <ul class="nav nav-pills card-header-pills" id="procurementQueueTabs">
                <!-- Tab 1: Awaiting Role Action -->
                <li class="nav-item">
                    <a class="nav-link {{ $currentTab === 'pending' ? 'active shadow-sm' : '' }} fw-bold" 
                       href="{{ route('procurement.my-queue', array_merge(request()->query(), ['tab' => 'pending'])) }}">
                        <i class="fas fa-hourglass-half me-1"></i> Awaiting Role Action
                        <span class="badge {{ $currentTab === 'pending' ? 'bg-white text-primary' : 'bg-primary text-white' }} ms-1">{{ $kpi['my_pending'] }}</span>
                    </a>
                </li>
                <!-- Tab 2: My Created Requests History -->
                <li class="nav-item">
                    <a class="nav-link {{ $currentTab === 'my_created' ? 'active shadow-sm' : '' }} fw-bold" 
                       href="{{ route('procurement.my-queue', array_merge(request()->query(), ['tab' => 'my_created'])) }}">
                        <i class="fas fa-history me-1"></i> My Created Requests History
                        <span class="badge {{ $currentTab === 'my_created' ? 'bg-white text-primary' : 'bg-info text-dark' }} ms-1">{{ $kpi['my_created'] }}</span>
                    </a>
                </li>
                <!-- Tab 3: Completed History -->
                <li class="nav-item">
                    <a class="nav-link {{ $currentTab === 'completed' ? 'active shadow-sm' : '' }} fw-bold" 
                       href="{{ route('procurement.my-queue', array_merge(request()->query(), ['tab' => 'completed'])) }}">
                        <i class="fas fa-check-double me-1"></i> Completed History
                        <span class="badge {{ $currentTab === 'completed' ? 'bg-white text-success' : 'bg-success text-white' }} ms-1">{{ $kpi['completed'] }}</span>
                    </a>
                </li>
                <!-- Tab 4: All Company Requests & PR History (Auditor / Admin / GM) -->
                @if($isAdmin || $isAuditorUser || $isGmUser || $isCoordinatorUser || $isPurchaseManagerUser)
                <li class="nav-item">
                    <a class="nav-link {{ $currentTab === 'all' ? 'active shadow-sm' : '' }} fw-bold" 
                       href="{{ route('procurement.my-queue', array_merge(request()->query(), ['tab' => 'all'])) }}">
                        <i class="fas fa-list-check me-1"></i> All Company Requests &amp; PRs
                        <span class="badge {{ $currentTab === 'all' ? 'bg-white text-secondary' : 'bg-secondary text-white' }} ms-1">{{ $kpi['all_prs'] }}</span>
                    </a>
                </li>
                @endif
                <!-- Tab 5: Deleted & Cancelled Requisitions ("Deleted Questions") -->
                <li class="nav-item">
                    <a class="nav-link {{ $currentTab === 'deleted' ? 'active shadow-sm' : '' }} fw-bold" 
                       href="{{ route('procurement.my-queue', array_merge(request()->query(), ['tab' => 'deleted'])) }}">
                        <i class="fas fa-trash-can me-1 text-danger"></i> Deleted &amp; Cancelled
                        <span class="badge {{ $currentTab === 'deleted' ? 'bg-white text-danger' : 'bg-danger text-white' }} ms-1">{{ $kpi['deleted'] ?? 0 }}</span>
                    </a>
                </li>
            </ul>

            <div class="d-flex align-items-center gap-2 flex-wrap">
                {{-- Quick Materials View Toggle Option --}}
                <div class="d-flex align-items-center gap-1.5 bg-light px-2.5 py-1 rounded-pill border shadow-2xs">
                    <small class="text-muted fw-bold text-uppercase" style="font-size:0.68rem;"><i class="fa-solid fa-boxes-stacked text-primary me-1"></i>Materials:</small>
                    <button type="button" class="btn btn-xs rounded-pill px-2.5 py-0.5 fw-semibold btn-primary text-white shadow-2xs" id="btnTabMaterialsExpanded" onclick="setQueueMaterialsMode('expanded')" title="Display all materials inline without clicking See Details">
                        <i class="fa-solid fa-eye me-1"></i>Show Materials
                    </button>
                    <button type="button" class="btn btn-xs rounded-pill px-2.5 py-0.5 fw-semibold text-muted" id="btnTabMaterialsCompact" onclick="setQueueMaterialsMode('compact')" title="Compact view">
                        <i class="fa-solid fa-eye-slash me-1"></i>Compact
                    </button>
                </div>

                <div class="text-muted small">
                    @if($currentTab === 'my_created')
                        <span class="badge bg-light text-dark border"><i class="fas fa-clock-rotate-left me-1 text-primary"></i> Created Requisition History</span>
                    @elseif($currentTab === 'completed')
                        <span class="badge bg-light text-dark border"><i class="fas fa-check-circle me-1 text-success"></i> Stocked &amp; Intake Completed Archives</span>
                    @elseif($currentTab === 'all')
                        <span class="badge bg-light text-dark border"><i class="fas fa-globe me-1 text-secondary"></i> Organization-wide Procurement</span>
                    @elseif($currentTab === 'deleted')
                        <span class="badge bg-light text-dark border"><i class="fas fa-trash-can me-1 text-danger"></i> Deleted &amp; Cancelled Questions Category</span>
                    @else
                        <span class="badge bg-light text-dark border"><i class="fas fa-inbox me-1 text-warning"></i> Role Pending Action Feed</span>
                    @endif
                </div>
            </div>
        </div>

        <div class="card-body p-0">

            {{-- ════════════════════════════════════════════════════════════════ --}}
            {{-- TAB 1: PENDING ROLE ACTION                                        --}}
            {{-- ════════════════════════════════════════════════════════════════ --}}
            @if($currentTab === 'pending')
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>PR / Ref Number</th>
                                <th>Project &amp; Store</th>
                                <th>Requested Materials</th>
                                <th>Channel Source</th>
                                <th>Stage / Status</th>
                                <th>Current Owner</th>
                                <th>Created Date</th>
                                <th class="text-end">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            {{-- Material Requests Queue --}}
                            @foreach($materialRequestsQueue as $mr)
                            @php
                                $linkedPr = $mr->purchaseRequests->first();
                                $mrMaterialsJson = $mr->items->map(function($itm) {
                                    return [
                                        'name' => $itm->product?->name ?? 'Material',
                                        'code' => $itm->product?->item_code ?? '-',
                                        'quantity' => (float)$itm->quantity,
                                        'unit' => $itm->product?->unit ?? 'Units',
                                        'estimated_unit_cost' => 0,
                                        'estimated_total' => 0,
                                        'specifications' => $itm->notes ?? '',
                                    ];
                                });
                            @endphp
                            {{-- Never show requests already converted/sent to PR in this queue: they show strictly in PR tables --}}
                            @if($linkedPr || $mr->status === 'sent_to_pr')
                                @continue
                            @endif
                            <tr class="table-light bg-opacity-50">
                                <td>
                                    <strong class="font-monospace text-primary">{{ $mr->reference_number }}</strong>
                                    <div class="small text-muted">
                                        <i class="fa-solid fa-layer-group me-1"></i>MR
                                        @if($linkedPr) &bull; <span class="badge bg-light text-primary border">PR #{{ $linkedPr->pr_no }}</span> @endif
                                    </div>
                                </td>
                                <td>
                                    <strong>{{ $mr->project?->name ?? 'Head Office' }}</strong>
                                    <small class="text-muted d-block">Store: {{ $mr->store?->name ?? 'N/A' }}</small>
                                </td>
                                <td class="col-procurement-materials">
                                    <div class="d-flex align-items-center justify-content-between mb-1">
                                        <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 px-2 py-0.5 fw-semibold" style="font-size:0.75rem;">
                                            <i class="fa-solid fa-boxes-stacked me-1"></i>{{ $mr->items->count() }} Material(s)
                                        </span>
                                        <button type="button" class="btn btn-xs btn-link text-muted p-0 text-decoration-none btn-toggle-single-materials" onclick="toggleSingleMaterials(this)" title="Toggle items display">
                                            <i class="fa-solid fa-chevron-up"></i>
                                        </button>
                                    </div>
                                    <div class="procurement-materials-list d-flex flex-column gap-1" style="max-height: 180px; overflow-y: auto;">
                                        @foreach($mr->items as $mItm)
                                            <div class="d-flex align-items-center justify-content-between p-1.5 bg-white rounded border text-nowrap shadow-2xs" style="font-size:0.78rem;">
                                                <div class="d-flex align-items-center gap-1.5 text-truncate me-2" style="max-width: 220px;">
                                                    <i class="fa-solid fa-cube text-primary" style="font-size:0.7rem;"></i>
                                                    <strong class="text-dark text-truncate" title="{{ $mItm->product?->name }}">{{ $mItm->product?->name ?? 'Material' }}</strong>
                                                </div>
                                                <span class="badge bg-light text-dark border font-monospace px-1.5 py-0.5 fw-bold">
                                                    {{ (float)$mItm->quantity }} {{ $mItm->product?->unit ?? 'Units' }}
                                                </span>
                                            </div>
                                            @if(!empty($mItm->notes))
                                                <small class="text-muted d-block ps-2 fst-italic" style="font-size:0.68rem;">{{ Str::limit($mItm->notes, 35) }}</small>
                                            @endif
                                        @endforeach
                                    </div>
                                    <div class="procurement-materials-compact-preview d-none text-muted small" style="font-size:11px;">
                                        {{ $mr->items->count() }} item(s): {{ Str::limit($mr->items->map(fn($it) => $it->product?->name)->filter()->join(', '), 30) }}
                                    </div>
                                </td>
                                <td><span class="badge bg-info text-dark border">Material Request</span></td>
                                <td>
                                    <span class="badge bg-warning text-dark">{{ ucfirst(str_replace('_', ' ', $mr->status)) }}</span>
                                </td>
                                <td>
                                    <span class="badge bg-secondary bg-opacity-10 text-dark">
                                        {{ ucfirst(str_replace('_', ' ', $linkedPr?->current_owner_role ?? 'Store Manager')) }}
                                    </span>
                                </td>
                                <td>{{ $mr->created_at->format('M d, Y') }}</td>
                                <td class="text-end">
                                    <div class="d-flex justify-content-end align-items-center gap-1 flex-wrap">
                                        <button type="button" class="btn btn-sm btn-outline-primary shadow-xs fw-semibold btn-queue-quick-materials"
                                                data-ref="{{ $mr->reference_number }}"
                                                data-project="{{ $mr->project?->name ?? 'Head Office' }}"
                                                data-store="{{ $mr->store?->name ?? 'N/A' }}"
                                                data-url="{{ route('material-requests.show', $mr) }}"
                                                data-items="{{ json_encode($mrMaterialsJson) }}"
                                                onclick="openQuickMaterialsModalFromBtn(this)"
                                                title="View full materials without saying See Details">
                                            <i class="fa-solid fa-boxes-stacked me-1"></i>Materials ({{ $mr->items->count() }})
                                        </button>
                                        <a href="{{ route('material-requests.show', $mr) }}" class="btn btn-sm btn-outline-info shadow-xs fw-semibold" title="See full Material Request details">
                                            <i class="fas fa-eye me-1"></i> See Details
                                        </a>
                                        @if(!$linkedPr)
                                            <form action="{{ route('material-requests.convert-to-pr', $mr) }}" method="POST" class="d-inline">
                                                @csrf
                                                <button type="submit" class="btn btn-sm btn-primary shadow-xs">
                                                    <i class="fas fa-play me-1"></i> Follow PR Flow
                                                </button>
                                            </form>
                                        @endif
                                        @if($isGlobalAdmin)
                                        <button type="button" class="btn btn-sm btn-outline-danger" title="Delete MR (Admin Only)" onclick="openDeleteQueueMrModal({{ $mr->id }}, '{{ addslashes($mr->reference_number) }}')">
                                            <i class="fas fa-trash-can"></i>
                                        </button>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                            @endforeach

                            {{-- Pending Purchase Requests --}}
                            @foreach($myPrs as $pr)
                            @php
                                $canActOnThisPr = false;
                                $prMaterialsJson = $pr->items->map(function($itm) {
                                    return [
                                        'name' => $itm->product?->name ?? 'Material',
                                        'code' => $itm->product?->item_code ?? '-',
                                        'quantity' => (float)$itm->quantity,
                                        'unit' => $itm->unit ?? ($itm->product?->unit ?? 'Units'),
                                        'estimated_unit_cost' => (float)($itm->estimated_unit_cost ?? 0),
                                        'estimated_total' => (float)($itm->estimated_total ?? 0),
                                        'specifications' => $itm->specifications ?? '',
                                    ];
                                });
                                if (!$isAuditorUser) {
                                    if ($isGlobalAdmin) {
                                        $canActOnThisPr = true;
                                    } elseif ($pr->is_office_request) {
                                        if ($pr->status === 'pending_hr_approval' && ($isHrUser || $isCoordinatorUser)) $canActOnThisPr = true;
                                        elseif (in_array($pr->status, ['approved', 'pending_store_review']) && $isStoreManagerUser) $canActOnThisPr = true;
                                        elseif ($pr->status === 'pending_finance' && $isFinanceHeadUser) $canActOnThisPr = true;
                                    } else {
                                        $owner = $pr->current_owner_role;
                                        $isFinalIntakeStage = ($pr->status === \App\Models\PurchaseRequest::STATUS_PENDING_STORE_REVIEW) && (
                                            $owner === 'store_keeper' ||
                                            $pr->payment !== null || 
                                            $pr->creditLedger !== null || 
                                            $pr->driverBooking !== null || 
                                            $pr->receipt !== null ||
                                            $pr->workflowLogs()->whereIn('action', [
                                                'gm_approve_buy_by_credit',
                                                'gm_approve_credit_direct_store',
                                                'finance_credit_approved',
                                                'finance_credit_approved_direct_intake',
                                                'driver_booked',
                                                'receipt_verified',
                                                'partial_store_intake'
                                            ])->exists()
                                        );

                                        if ($isFinalIntakeStage) {
                                            if ($isStoreKeeperUser) $canActOnThisPr = true;
                                        } elseif ($owner === 'store_manager' && $isStoreManagerUser) {
                                            $canActOnThisPr = true;
                                        } elseif ($owner === 'store_keeper' && $isStoreKeeperUser) {
                                            $canActOnThisPr = true;
                                        } elseif (in_array($owner, ['purchase_manager', 'procurement_manager']) && $isPurchaseManagerUser) {
                                            $canActOnThisPr = true;
                                        } elseif ($pr->status === \App\Models\PurchaseRequest::STATUS_PENDING_MARKETING && $isPurchaseManagerUser) {
                                            $canActOnThisPr = true;
                                        } elseif (in_array($owner, ['purchase', 'procurement_team', 'purchaser', 'buyer']) && $isProcurementTeamUser) {
                                            $canActOnThisPr = true;
                                        } elseif (in_array($owner, ['marketing', 'market_research']) && $isMarketingUser) {
                                            $canActOnThisPr = true;
                                        }
                                        elseif (in_array($owner, ['gm', 'general_manager']) && $isGmUser) $canActOnThisPr = true;
                                        elseif (in_array($owner, ['finance_head', 'finance', 'finance_manager']) && $isFinanceHeadUser) $canActOnThisPr = true;
                                        elseif (in_array($owner, ['general_service', 'general_services']) && $isGeneralServiceUser) $canActOnThisPr = true;
                                        elseif ((in_array($owner, ['coordinator', 'project_coordinator', 'site_coordinator']) || $pr->status === \App\Models\PurchaseRequest::STATUS_PENDING_HR_APPROVAL) && $isCoordinatorUser) $canActOnThisPr = true;
                                        elseif ($pr->status === 'draft' && ($authUser && $pr->requested_by === $authUser->id || $isCoordinatorUser)) $canActOnThisPr = true;
                                    }
                                }
                            @endphp
                            <tr class="{{ $pr->is_office_request && $pr->status === 'pending_hr_approval' ? 'table-warning bg-opacity-25' : '' }}">
                                <td>
                                    <a href="{{ route('purchase-requests.show', $pr->id) }}" class="fw-bold text-decoration-none">
                                        {{ $pr->pr_no }}
                                    </a>
                                    @if($pr->materialRequest)
                                        <div class="mt-1">
                                            <a href="{{ route('material-requests.show', $pr->materialRequest) }}" class="badge bg-info bg-opacity-10 text-primary border border-info border-opacity-25 font-monospace text-decoration-none" title="Linked Material Request">
                                                <i class="fa-solid fa-clipboard-list me-1"></i>{{ $pr->materialRequest->reference_number }}
                                            </a>
                                        </div>
                                    @endif
                                </td>
                                <td>
                                    <span class="fw-medium text-dark">{{ $pr->project?->name ?? 'N/A' }}</span>
                                    @php
                                        $destStore = $pr->store ?? $pr->materialRequest?->destinationStore ?? $pr->materialRequest?->store;
                                    @endphp
                                    @if($destStore)
                                        <small class="text-muted d-block"><i class="fas fa-warehouse me-1"></i>Store: {{ $destStore->name }}</small>
                                    @endif
                                </td>
                                <td class="col-procurement-materials">
                                    <div class="d-flex align-items-center justify-content-between mb-1">
                                        <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 px-2 py-0.5 fw-semibold" style="font-size:0.75rem;">
                                            <i class="fa-solid fa-boxes-stacked me-1"></i>{{ $pr->items->count() }} Material(s)
                                        </span>
                                        @if((float)($pr->estimated_total ?? 0) > 0 || (float)($pr->direct_buy_amount ?? 0) > 0)
                                            <span class="font-monospace text-muted small" style="font-size:0.72rem;">
                                                {{ number_format($pr->direct_buy_amount > 0 ? $pr->direct_buy_amount : ($pr->estimated_total ?? 0), 2) }} ETB
                                            </span>
                                        @endif
                                        <button type="button" class="btn btn-xs btn-link text-muted p-0 text-decoration-none btn-toggle-single-materials" onclick="toggleSingleMaterials(this)" title="Toggle items display">
                                            <i class="fa-solid fa-chevron-up"></i>
                                        </button>
                                    </div>
                                    <div class="procurement-materials-list d-flex flex-column gap-1" style="max-height: 180px; overflow-y: auto;">
                                        @foreach($pr->items as $pItm)
                                            <div class="d-flex align-items-center justify-content-between p-1.5 bg-white rounded border text-nowrap shadow-2xs" style="font-size:0.78rem;">
                                                <div class="d-flex align-items-center gap-1.5 text-truncate me-2" style="max-width: 220px;">
                                                    <i class="fa-solid fa-cube text-primary" style="font-size:0.7rem;"></i>
                                                    <strong class="text-dark text-truncate" title="{{ $pItm->product?->name }}">{{ $pItm->product?->name ?? 'Material' }}</strong>
                                                </div>
                                                <div class="font-monospace text-end">
                                                    <span class="badge bg-light text-dark border px-1.5 py-0.5 fw-bold">
                                                        {{ (float)$pItm->quantity }} {{ $pItm->unit ?? ($pItm->product?->unit ?? 'Units') }}
                                                    </span>
                                                    @if((float)($pItm->estimated_unit_cost ?? 0) > 0)
                                                        <small class="text-muted d-block" style="font-size:0.68rem;">@ {{ number_format($pItm->estimated_unit_cost, 2) }}</small>
                                                    @endif
                                                </div>
                                            </div>
                                            @if(!empty($pItm->specifications))
                                                <small class="text-muted d-block ps-2 fst-italic" style="font-size:0.68rem;">{{ Str::limit($pItm->specifications, 35) }}</small>
                                            @endif
                                        @endforeach
                                    </div>
                                    <div class="procurement-materials-compact-preview d-none text-muted small" style="font-size:11px;">
                                        {{ $pr->items->count() }} item(s): {{ Str::limit($pr->items->map(fn($it) => $it->product?->name)->filter()->join(', '), 30) }}
                                    </div>
                                </td>
                                <td>
                                    @if($pr->materialRequest)
                                        <span class="badge bg-light text-dark border">From Requisition</span>
                                    @else
                                        <span class="badge bg-light text-dark border">Direct PR</span>
                                    @endif
                                </td>
                                <td>
                                    <span class="badge bg-{{ \App\Models\PurchaseRequest::statusBadgeClass($pr->status) }}">
                                        {{ $pr->status_label }}
                                    </span>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center gap-1 flex-wrap">
                                        @if(!empty($isFinalIntakeStage))
                                            <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 fw-semibold">
                                                <i class="fas fa-boxes-packing me-1"></i> Store Keeper
                                            </span>
                                        @else
                                            <span class="badge bg-secondary bg-opacity-10 text-dark">
                                                <i class="fas fa-user-tag me-1"></i> {{ ucfirst(str_replace('_', ' ', $pr->current_owner_role ?? 'None')) }}
                                            </span>
                                        @endif
                                    </div>
                                </td>
                                <td>{{ $pr->created_at->format('M d, Y') }}</td>
                                <td class="text-end">
                                    <div class="d-flex justify-content-end align-items-center gap-1 flex-wrap">
                                        <button type="button" class="btn btn-sm btn-outline-primary shadow-xs fw-semibold btn-queue-quick-materials"
                                                data-ref="{{ $pr->pr_no }}"
                                                data-project="{{ $pr->project?->name ?? 'N/A' }}"
                                                data-store="{{ $destStore?->name ?? 'Store Unassigned' }}"
                                                data-url="{{ route('purchase-requests.show', $pr->id) }}"
                                                data-items="{{ json_encode($prMaterialsJson) }}"
                                                onclick="openQuickMaterialsModalFromBtn(this)"
                                                title="View full materials without saying See Details">
                                            <i class="fa-solid fa-boxes-stacked me-1"></i>Materials ({{ $pr->items->count() }})
                                        </button>
                                        <a href="{{ route('purchase-requests.show', $pr->id) }}" class="btn btn-sm btn-outline-info shadow-xs fw-semibold" title="See full Purchase Request details">
                                            <i class="fas fa-eye me-1"></i> See Details
                                        </a>
                                        @if($canActOnThisPr)
                                            @if(!empty($isFinalIntakeStage))
                                                <a href="{{ route('purchase-requests.show', $pr->id) }}" class="btn btn-sm btn-success fw-bold shadow-sm">
                                                    <i class="fas fa-boxes-packing me-1"></i> Fulfill Intake
                                                </a>
                                            @else
                                                <a href="{{ route('purchase-requests.show', $pr->id) }}" class="btn btn-sm btn-primary fw-bold shadow-sm">
                                                    <i class="fas fa-bolt me-1"></i> Take Action
                                                </a>
                                            @endif
                                        @endif
                                        @if($isGlobalAdmin)
                                        <button type="button" class="btn btn-sm btn-outline-danger" title="Delete PR (Admin Only)" onclick="openDeleteQueuePrModal({{ $pr->id }}, '{{ addslashes($pr->pr_no) }}')">
                                            <i class="fas fa-trash-can"></i>
                                        </button>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                            @endforeach

                            @if(($materialRequestsQueue->isEmpty() ?? true) && $myPrs->isEmpty())
                            <tr>
                                <td colspan="8" class="text-center py-5 text-muted">
                                    <i class="fas fa-check-circle fa-3x mb-3 text-success d-block"></i>
                                    <h6 class="fw-bold text-dark">No pending procurement or requisition items awaiting your action right now!</h6>
                                    @if($kpi['my_created'] > 0)
                                        <p class="small text-muted mb-3">You have <strong>{{ $kpi['my_created'] }}</strong> created request(s) in your history.</p>
                                        <a href="{{ route('procurement.my-queue', array_merge(request()->query(), ['tab' => 'my_created'])) }}" class="btn btn-sm btn-outline-primary fw-bold">
                                            <i class="fas fa-history me-1"></i> View My Created Requests History
                                        </a>
                                    @endif
                                </td>
                            </tr>
                            @endif
                        </tbody>
                    </table>
                </div>

                @if($myPrs->hasPages())
                <div class="card-footer bg-white border-0 py-3">
                    {{ $myPrs->links() }}
                </div>
                @endif

            {{-- ════════════════════════════════════════════════════════════════ --}}
            {{-- TAB 2: MY CREATED REQUESTS HISTORY ("they created question history") --}}
            {{-- ════════════════════════════════════════════════════════════════ --}}
            @elseif($currentTab === 'my_created')
                <div class="p-3 bg-light border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div>
                        <h6 class="fw-bold text-dark mb-0">
                            <i class="fas fa-history text-primary me-2"></i>My Created Requisitions &amp; Requests History
                        </h6>
                        <small class="text-muted">Showing all procurement and material requests created by you with live lifecycle stage &amp; owner tracking.</small>
                    </div>
                    <span class="badge bg-primary rounded-pill px-3 py-2">{{ $myCreatedPrs->total() + $myCreatedMrs->count() }} Total Created</span>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Ref / PR Number</th>
                                <th>Project &amp; Store</th>
                                <th>Requested Items Summary</th>
                                <th>Budget / Est. Amount</th>
                                <th>Current Lifecycle Stage</th>
                                <th>Current Owner</th>
                                <th>Created Date</th>
                                <th class="text-end">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            {{-- Created Purchase Requests --}}
                            @forelse($myCreatedPrs as $cPr)
                            <tr>
                                <td>
                                    <a href="{{ route('purchase-requests.show', $cPr->id) }}" class="fw-bold text-primary font-monospace text-decoration-none">
                                        {{ $cPr->pr_no }}
                                    </a>
                                    <div class="small">
                                        @if($cPr->material_request_id)
                                            <span class="badge bg-info bg-opacity-10 text-info border border-info border-opacity-25" style="font-size:9px;">Requisition (MR)</span>
                                        @else
                                            <span class="badge bg-secondary bg-opacity-10 text-secondary border" style="font-size:9px;">Direct PR</span>
                                        @endif
                                    </div>
                                </td>
                                <td>
                                    <div class="fw-semibold text-dark">{{ $cPr->project?->name ?? 'N/A' }}</div>
                                    <small class="text-muted"><i class="fas fa-warehouse me-1"></i>{{ $cPr->store?->name ?? 'Store Unassigned' }}</small>
                                </td>
                                @php
                                    $cPrMaterialsJson = $cPr->items->map(function($itm) {
                                        return [
                                            'name' => $itm->product?->name ?? 'Material',
                                            'code' => $itm->product?->item_code ?? '-',
                                            'quantity' => (float)$itm->quantity,
                                            'unit' => $itm->unit ?? ($itm->product?->unit ?? 'Units'),
                                            'estimated_unit_cost' => (float)($itm->estimated_unit_cost ?? 0),
                                            'estimated_total' => (float)($itm->estimated_total ?? 0),
                                            'specifications' => $itm->specifications ?? '',
                                        ];
                                    });
                                @endphp
                                <td class="col-procurement-materials">
                                    <div class="d-flex align-items-center justify-content-between mb-1">
                                        <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 px-2 py-0.5 fw-semibold" style="font-size:0.75rem;">
                                            <i class="fa-solid fa-boxes-stacked me-1"></i>{{ $cPr->items->count() }} Material(s)
                                        </span>
                                        <button type="button" class="btn btn-xs btn-link text-muted p-0 text-decoration-none btn-toggle-single-materials" onclick="toggleSingleMaterials(this)" title="Toggle items display">
                                            <i class="fa-solid fa-chevron-up"></i>
                                        </button>
                                    </div>
                                    <div class="procurement-materials-list d-flex flex-column gap-1" style="max-height: 180px; overflow-y: auto;">
                                        @foreach($cPr->items as $itm)
                                            <div class="d-flex align-items-center justify-content-between p-1.5 bg-white rounded border text-nowrap shadow-2xs" style="font-size:0.78rem;">
                                                <div class="d-flex align-items-center gap-1.5 text-truncate me-2" style="max-width: 220px;">
                                                    <i class="fa-solid fa-cube text-primary" style="font-size:0.7rem;"></i>
                                                    <strong class="text-dark text-truncate" title="{{ $itm->product?->name }}">{{ $itm->product?->name ?? 'Material' }}</strong>
                                                </div>
                                                <div class="font-monospace text-end">
                                                    <span class="badge bg-light text-dark border px-1.5 py-0.5 fw-bold">
                                                        {{ (float)$itm->quantity }} {{ $itm->unit }}
                                                    </span>
                                                    @if((float)($itm->estimated_unit_cost ?? 0) > 0)
                                                        <small class="text-muted d-block" style="font-size:0.68rem;">@ {{ number_format($itm->estimated_unit_cost, 2) }}</small>
                                                    @endif
                                                </div>
                                            </div>
                                            @if(!empty($itm->specifications))
                                                <small class="text-muted d-block ps-2 fst-italic" style="font-size:0.68rem;">{{ Str::limit($itm->specifications, 35) }}</small>
                                            @endif
                                        @endforeach
                                    </div>
                                    <div class="procurement-materials-compact-preview d-none text-muted small" style="font-size:11px;">
                                        @foreach($cPr->items->take(2) as $itm)
                                            <span class="badge bg-light text-dark border me-1">
                                                {{ $itm->product?->name ?? 'Item' }}: {{ (float)$itm->quantity }} {{ $itm->unit }}
                                            </span>
                                        @endforeach
                                        @if($cPr->items->count() > 2)
                                            <span class="text-muted">+{{ $cPr->items->count() - 2 }} more</span>
                                        @endif
                                    </div>
                                </td>
                                <td>
                                    @php
                                        $dispCost = $cPr->direct_buy_amount > 0 ? (float)$cPr->direct_buy_amount : (float)($cPr->estimated_total ?? 0);
                                    @endphp
                                    <strong class="text-dark font-monospace">{{ number_format($dispCost, 2) }} ETB</strong>
                                    @if($cPr->direct_buy_amount > 0)
                                        <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 d-block mt-1" style="font-size:9px;">Direct Buy</span>
                                    @endif
                                </td>
                                <td>
                                    <span class="badge bg-{{ \App\Models\PurchaseRequest::statusBadgeClass($cPr->status) }} py-1.5 px-2">
                                        {{ $cPr->status_label }}
                                    </span>
                                </td>
                                <td>
                                    @if($cPr->status === \App\Models\PurchaseRequest::STATUS_INTAKE_COMPLETE)
                                        <span class="badge bg-success py-1 px-2"><i class="fas fa-check-circle me-1"></i>Completed</span>
                                    @else
                                        <span class="badge bg-secondary bg-opacity-10 text-dark border py-1 px-2">
                                            <i class="fas fa-user-tag me-1 text-primary"></i>
                                            {{ ucfirst(str_replace('_', ' ', $cPr->current_owner_role ?? 'Store Review')) }}
                                        </span>
                                    @endif
                                </td>
                                <td>
                                    <div>{{ $cPr->created_at->format('M d, Y') }}</div>
                                    <small class="text-muted">{{ $cPr->created_at->diffForHumans() }}</small>
                                </td>
                                <td class="text-end">
                                    <div class="d-flex justify-content-end align-items-center gap-1 flex-wrap">
                                        <button type="button" class="btn btn-sm btn-outline-primary shadow-xs fw-semibold btn-queue-quick-materials"
                                                data-ref="{{ $cPr->pr_no }}"
                                                data-project="{{ $cPr->project?->name ?? 'N/A' }}"
                                                data-store="{{ $cPr->store?->name ?? 'Store Unassigned' }}"
                                                data-url="{{ route('purchase-requests.show', $cPr->id) }}"
                                                data-items="{{ json_encode($cPrMaterialsJson) }}"
                                                onclick="openQuickMaterialsModalFromBtn(this)"
                                                title="View full materials without saying See Details">
                                            <i class="fa-solid fa-boxes-stacked me-1"></i>Materials ({{ $cPr->items->count() }})
                                        </button>
                                        <a href="{{ route('purchase-requests.show', $cPr->id) }}" class="btn btn-sm btn-outline-info shadow-xs fw-semibold">
                                            <i class="fas fa-eye me-1"></i> See Details
                                        </a>
                                        @if($isGlobalAdmin)
                                        <button type="button" class="btn btn-sm btn-outline-danger" title="Delete PR (Admin Only)" onclick="openDeleteQueuePrModal({{ $cPr->id }}, '{{ addslashes($cPr->pr_no) }}')">
                                            <i class="fas fa-trash-can"></i>
                                        </button>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="8" class="text-center py-5 text-muted">
                                    <i class="fas fa-folder-open fa-3x mb-3 text-secondary opacity-50 d-block"></i>
                                    <h6 class="fw-bold text-dark">No created purchase requests found in your history.</h6>
                                    <p class="small text-muted mb-3">When you create new purchase or material requests, they will be tracked here through every stage.</p>
                                    <a href="{{ route('purchase-requests.create') }}" class="btn btn-sm btn-primary">
                                        <i class="fas fa-plus me-1"></i> Create First Purchase Request
                                    </a>
                                </td>
                            </tr>
                            @endforelse

                            {{-- Created Material Requests (Requisitions) not yet converted to PR --}}
                            @foreach($myCreatedMrs as $cMr)
                            @if(!$cMr->purchaseRequests()->exists())
                            <tr class="table-light">
                                <td>
                                    <a href="{{ route('material-requests.show', $cMr) }}" class="fw-bold text-info font-monospace text-decoration-none">
                                        {{ $cMr->reference_number }}
                                    </a>
                                    <div class="small"><span class="badge bg-info bg-opacity-25 text-dark" style="font-size:9px;">Material Requisition</span></div>
                                </td>
                                <td>
                                    <div class="fw-semibold text-dark">{{ $cMr->project?->name ?? 'Head Office' }}</div>
                                    <small class="text-muted"><i class="fas fa-warehouse me-1"></i>{{ $cMr->store?->name ?? 'Store' }}</small>
                                </td>
                                @php
                                    $cMrMaterialsJson = $cMr->items->map(function($itm) {
                                        return [
                                            'name' => $itm->product?->name ?? 'Material',
                                            'code' => $itm->product?->item_code ?? '-',
                                            'quantity' => (float)$itm->quantity,
                                            'unit' => $itm->product?->unit ?? 'Units',
                                            'estimated_unit_cost' => 0,
                                            'estimated_total' => 0,
                                            'specifications' => $itm->notes ?? '',
                                        ];
                                    });
                                @endphp
                                <td class="col-procurement-materials">
                                    <div class="d-flex align-items-center justify-content-between mb-1">
                                        <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 px-2 py-0.5 fw-semibold" style="font-size:0.75rem;">
                                            <i class="fa-solid fa-boxes-stacked me-1"></i>{{ $cMr->items->count() }} Material(s)
                                        </span>
                                        <button type="button" class="btn btn-xs btn-link text-muted p-0 text-decoration-none btn-toggle-single-materials" onclick="toggleSingleMaterials(this)" title="Toggle items display">
                                            <i class="fa-solid fa-chevron-up"></i>
                                        </button>
                                    </div>
                                    <div class="procurement-materials-list d-flex flex-column gap-1" style="max-height: 180px; overflow-y: auto;">
                                        @foreach($cMr->items as $mItm)
                                            <div class="d-flex align-items-center justify-content-between p-1.5 bg-white rounded border text-nowrap shadow-2xs" style="font-size:0.78rem;">
                                                <div class="d-flex align-items-center gap-1.5 text-truncate me-2" style="max-width: 200px;">
                                                    <i class="fa-solid fa-cube text-primary" style="font-size:0.7rem;"></i>
                                                    <strong class="text-dark text-truncate" title="{{ $mItm->product?->name }}">{{ $mItm->product?->name ?? 'Material' }}</strong>
                                                </div>
                                                <span class="badge bg-light text-dark border font-monospace px-1.5 py-0.5 fw-bold">
                                                    {{ (float)$mItm->quantity }} {{ $mItm->product?->unit ?? 'Units' }}
                                                </span>
                                            </div>
                                            @if(!empty($mItm->notes))
                                                <small class="text-muted d-block ps-2 fst-italic" style="font-size:0.68rem;">{{ Str::limit($mItm->notes, 35) }}</small>
                                            @endif
                                        @endforeach
                                    </div>
                                    <div class="procurement-materials-compact-preview d-none text-muted small" style="font-size:11px;">
                                        {{ $cMr->items->count() }} item(s): {{ Str::limit($cMr->items->map(fn($it) => $it->product?->name)->filter()->join(', '), 30) }}
                                    </div>
                                </td>
                                <td><span class="text-muted small">Pending PR Pricing</span></td>
                                <td>
                                    <span class="badge bg-warning text-dark">{{ ucfirst(str_replace('_', ' ', $cMr->status)) }}</span>
                                </td>
                                <td>
                                    <span class="badge bg-secondary bg-opacity-10 text-dark">Store / Coordinator</span>
                                </td>
                                <td>
                                    <div>{{ $cMr->created_at->format('M d, Y') }}</div>
                                    <small class="text-muted">{{ $cMr->created_at->diffForHumans() }}</small>
                                </td>
                                <td class="text-end">
                                    <div class="d-flex justify-content-end align-items-center gap-1 flex-wrap">
                                        <button type="button" class="btn btn-sm btn-outline-primary shadow-xs fw-semibold btn-queue-quick-materials"
                                                data-ref="{{ $cMr->reference_number }}"
                                                data-project="{{ $cMr->project?->name ?? 'Head Office' }}"
                                                data-store="{{ $cMr->store?->name ?? 'Store' }}"
                                                data-url="{{ route('material-requests.show', $cMr) }}"
                                                data-items="{{ json_encode($cMrMaterialsJson) }}"
                                                onclick="openQuickMaterialsModalFromBtn(this)"
                                                title="View full materials without saying See Details">
                                            <i class="fa-solid fa-boxes-stacked me-1"></i>Materials ({{ $cMr->items->count() }})
                                        </button>
                                        <a href="{{ route('material-requests.show', $cMr) }}" class="btn btn-sm btn-outline-info shadow-xs fw-semibold">
                                            <i class="fas fa-eye me-1"></i> See Details
                                        </a>
                                        @if($isGlobalAdmin)
                                        <button type="button" class="btn btn-sm btn-outline-danger" title="Delete MR (Admin Only)" onclick="openDeleteQueueMrModal({{ $cMr->id }}, '{{ addslashes($cMr->reference_number) }}')">
                                            <i class="fas fa-trash-can"></i>
                                        </button>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                            @endif
                            @endforeach
                        </tbody>
                    </table>
                </div>

                @if($myCreatedPrs->hasPages())
                <div class="card-footer bg-white border-0 py-3">
                    {{ $myCreatedPrs->links() }}
                </div>
                @endif

            {{-- ════════════════════════════════════════════════════════════════ --}}
            {{-- TAB 3: COMPLETED INTAKE HISTORY                                  --}}
            {{-- ════════════════════════════════════════════════════════════════ --}}
            @elseif($currentTab === 'completed')
                <div class="p-3 bg-light border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div>
                        <h6 class="fw-bold text-success mb-0">
                            <i class="fas fa-check-circle me-2"></i>Completed Store Intakes &amp; Delivered PR Archives
                        </h6>
                        <small class="text-muted">Purchase requests that have been completely verified, delivered, and stocked into store inventory.</small>
                    </div>
                    <span class="badge bg-success rounded-pill px-3 py-2">{{ $completedPrs->total() }} Completed</span>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>PR Number</th>
                                <th>Project &amp; Receiving Store</th>
                                <th>Requested By</th>
                                <th>Stocked Materials</th>
                                <th>Receiving Slips (Model 19 / GRN)</th>
                                <th>Completed Date</th>
                                <th class="text-end">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($completedPrs as $compPr)
                            <tr>
                                <td>
                                    <a href="{{ route('purchase-requests.show', $compPr->id) }}" class="fw-bold text-success font-monospace text-decoration-none">
                                        {{ $compPr->pr_no }}
                                    </a>
                                    <div class="small"><span class="badge bg-success text-white" style="font-size:9px;">Intake Complete</span></div>
                                </td>
                                <td>
                                    <div class="fw-bold text-dark">{{ $compPr->project?->name ?? 'N/A' }}</div>
                                    <small class="text-muted"><i class="fas fa-warehouse me-1 text-success"></i>{{ $compPr->store?->name ?? 'Store' }}</small>
                                </td>
                                <td>
                                    <span class="small fw-semibold text-dark">{{ $compPr->requestedBy?->name ?? 'N/A' }}</span>
                                </td>
                                @php
                                    $compMaterialsJson = $compPr->items->map(function($item) {
                                        return [
                                            'product_name' => $item->product?->name ?? 'Material Item',
                                            'product_code' => $item->product?->item_code ?? '',
                                            'quantity' => (float)$item->quantity,
                                            'unit' => $item->unit ?? ($item->product?->unit ?? 'Units'),
                                            'unit_cost' => (float)($item->estimated_unit_cost ?? 0),
                                            'specs' => $item->specifications ?? '',
                                        ];
                                    })->values()->toArray();
                                @endphp
                                <td class="col-procurement-materials" style="min-width: 250px;">
                                    <div class="d-flex align-items-center justify-content-between mb-1">
                                        <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 px-2 py-0.5 fw-semibold" style="font-size:0.75rem;">
                                            <i class="fa-solid fa-boxes-packing me-1"></i>{{ $compPr->items->count() }} Stocked Material(s)
                                        </span>
                                        <button type="button" class="btn btn-xs btn-link text-muted p-0 text-decoration-none btn-toggle-single-materials" onclick="toggleSingleMaterials(this)" title="Toggle items display">
                                            <i class="fa-solid fa-chevron-up"></i>
                                        </button>
                                    </div>
                                    <div class="procurement-materials-list d-flex flex-column gap-1" style="max-height: 180px; overflow-y: auto;">
                                        @foreach($compPr->items as $cItm)
                                            <div class="d-flex align-items-center justify-content-between p-1.5 bg-white rounded border text-nowrap shadow-2xs" style="font-size:0.78rem;">
                                                <div class="d-flex align-items-center gap-1.5 text-truncate me-2" style="max-width: 190px;">
                                                    <i class="fa-solid fa-cube text-success" style="font-size:0.7rem;"></i>
                                                    <strong class="text-dark text-truncate" title="{{ $cItm->product?->name }}">{{ $cItm->product?->name ?? 'Material' }}</strong>
                                                </div>
                                                <span class="badge bg-light text-dark border px-1.5 py-0.5 font-monospace fw-bold">
                                                    {{ (float)$cItm->quantity }} {{ $cItm->unit ?? ($cItm->product?->unit ?? 'Units') }}
                                                </span>
                                            </div>
                                        @endforeach
                                    </div>
                                    <div class="procurement-materials-compact-preview d-none text-muted small" style="font-size:11px;">
                                        {{ $compPr->items->count() }} item(s): {{ Str::limit($compPr->items->map(fn($it) => $it->product?->name)->filter()->join(', '), 30) }}
                                    </div>
                                </td>
                                <td>
                                    @php
                                        $drSlips = $compPr->deliveryReceipts;
                                    @endphp
                                    @if($drSlips->count() > 0)
                                        @foreach($drSlips as $dr)
                                            <span class="badge bg-info bg-opacity-10 text-info border border-info border-opacity-25 font-monospace me-1 mb-1">
                                                <i class="fas fa-hashtag me-1"></i>{{ $dr->dr_no }}
                                            </span>
                                        @endforeach
                                    @else
                                        <span class="text-muted small">Slip Logged</span>
                                    @endif
                                </td>
                                <td>
                                    <div>{{ $compPr->updated_at->format('M d, Y') }}</div>
                                    <small class="text-muted">{{ $compPr->updated_at->diffForHumans() }}</small>
                                </td>
                                <td class="text-end">
                                    <div class="d-flex justify-content-end align-items-center gap-1 flex-wrap">
                                        <button type="button" class="btn btn-sm btn-outline-primary shadow-xs fw-semibold btn-queue-quick-materials"
                                                data-ref="{{ $compPr->pr_no }}"
                                                data-project="{{ $compPr->project?->name ?? 'N/A' }}"
                                                data-store="{{ $compPr->store?->name ?? 'Store' }}"
                                                data-url="{{ route('purchase-requests.show', $compPr->id) }}"
                                                data-items="{{ json_encode($compMaterialsJson) }}"
                                                onclick="openQuickMaterialsModalFromBtn(this)"
                                                title="View full materials without saying See Details">
                                            <i class="fa-solid fa-boxes-stacked me-1"></i>Materials ({{ $compPr->items->count() }})
                                        </button>
                                        <a href="{{ route('purchase-requests.show', $compPr->id) }}" class="btn btn-sm btn-outline-success shadow-xs fw-semibold">
                                            <i class="fas fa-eye me-1"></i> See Details
                                        </a>
                                        @if($isGlobalAdmin)
                                        <button type="button" class="btn btn-sm btn-outline-danger" title="Delete PR (Admin Only)" onclick="openDeleteQueuePrModal({{ $compPr->id }}, '{{ addslashes($compPr->pr_no) }}')">
                                            <i class="fas fa-trash-can"></i>
                                        </button>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="7" class="text-center py-5 text-muted">
                                    <i class="fas fa-check-double fa-3x mb-3 text-secondary opacity-50 d-block"></i>
                                    <h6 class="fw-bold text-dark">No completed store intake records found.</h6>
                                    <p class="small text-muted mb-0">Once materials are received at the store and verified under receiving slips, they will be archived here.</p>
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if($completedPrs->hasPages())
                <div class="card-footer bg-white border-0 py-3">
                    {{ $completedPrs->links() }}
                </div>
                @endif

            {{-- ════════════════════════════════════════════════════════════════ --}}
            {{-- TAB 4: ALL COMPANY PR HISTORY (AUDIT & MANAGEMENT)              --}}
            {{-- ════════════════════════════════════════════════════════════════ --}}
            @elseif($currentTab === 'all')
                <div class="p-3 bg-light border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div>
                        <h6 class="fw-bold text-dark mb-0">
                            <i class="fas fa-globe text-primary me-2"></i>Organization-Wide Purchase Requests History
                        </h6>
                        <small class="text-muted">Master log of all purchase requests across all stages and projects.</small>
                    </div>
                    <span class="badge bg-secondary rounded-pill px-3 py-2">{{ $allPrs->total() }} Records</span>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>PR Number</th>
                                <th>Project &amp; Store</th>
                                <th>Requester</th>
                                <th>Requested Materials</th>
                                <th>Total Budget</th>
                                <th>Status</th>
                                <th>Current Owner</th>
                                <th>Created</th>
                                <th class="text-end">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($allPrs as $aPr)
                            <tr>
                                <td>
                                    <a href="{{ route('purchase-requests.show', $aPr->id) }}" class="fw-bold text-primary font-monospace text-decoration-none">
                                        {{ $aPr->pr_no }}
                                    </a>
                                    @if($aPr->materialRequest)
                                        <div class="mt-1">
                                            <a href="{{ route('material-requests.show', $aPr->materialRequest) }}" class="badge bg-info bg-opacity-10 text-primary border border-info border-opacity-25 font-monospace text-decoration-none" title="Linked Material Request">
                                                <i class="fa-solid fa-clipboard-list me-1"></i>{{ $aPr->materialRequest->reference_number }}
                                            </a>
                                        </div>
                                    @endif
                                </td>
                                <td>
                                    <strong>{{ $aPr->project?->name ?? 'Head Office' }}</strong>
                                    <small class="text-muted d-block">{{ $aPr->store?->name ?? 'Store Unassigned' }}</small>
                                </td>
                                <td>{{ $aPr->requestedBy?->name ?? 'N/A' }}</td>
                                @php
                                    $aPrMaterialsJson = $aPr->items->map(function($item) {
                                        return [
                                            'product_name' => $item->product?->name ?? 'Material Item',
                                            'product_code' => $item->product?->item_code ?? '',
                                            'quantity' => (float)$item->quantity,
                                            'unit' => $item->unit ?? ($item->product?->unit ?? 'Units'),
                                            'unit_cost' => (float)($item->estimated_unit_cost ?? 0),
                                            'specs' => $item->specifications ?? '',
                                        ];
                                    })->values()->toArray();
                                @endphp
                                <td class="col-procurement-materials" style="min-width: 250px;">
                                    <div class="d-flex align-items-center justify-content-between mb-1">
                                        <span class="badge bg-secondary bg-opacity-10 text-dark border border-secondary border-opacity-25 px-2 py-0.5 fw-semibold" style="font-size:0.75rem;">
                                            <i class="fa-solid fa-boxes-stacked me-1"></i>{{ $aPr->items->count() }} Material(s)
                                        </span>
                                        <button type="button" class="btn btn-xs btn-link text-muted p-0 text-decoration-none btn-toggle-single-materials" onclick="toggleSingleMaterials(this)" title="Toggle items display">
                                            <i class="fa-solid fa-chevron-up"></i>
                                        </button>
                                    </div>
                                    <div class="procurement-materials-list d-flex flex-column gap-1" style="max-height: 180px; overflow-y: auto;">
                                        @foreach($aPr->items as $aItm)
                                            <div class="d-flex align-items-center justify-content-between p-1.5 bg-white rounded border text-nowrap shadow-2xs" style="font-size:0.78rem;">
                                                <div class="d-flex align-items-center gap-1.5 text-truncate me-2" style="max-width: 190px;">
                                                    <i class="fa-solid fa-cube text-primary" style="font-size:0.7rem;"></i>
                                                    <strong class="text-dark text-truncate" title="{{ $aItm->product?->name }}">{{ $aItm->product?->name ?? 'Material' }}</strong>
                                                </div>
                                                <div class="font-monospace text-end">
                                                    <span class="badge bg-light text-dark border px-1.5 py-0.5 fw-bold">
                                                        {{ (float)$aItm->quantity }} {{ $aItm->unit ?? ($aItm->product?->unit ?? 'Units') }}
                                                    </span>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                    <div class="procurement-materials-compact-preview d-none text-muted small" style="font-size:11px;">
                                        {{ $aPr->items->count() }} item(s): {{ Str::limit($aPr->items->map(fn($it) => $it->product?->name)->filter()->join(', '), 30) }}
                                    </div>
                                </td>
                                <td>
                                    <span class="font-monospace fw-bold">{{ number_format($aPr->direct_buy_amount > 0 ? $aPr->direct_buy_amount : ($aPr->estimated_total ?? 0), 2) }} ETB</span>
                                </td>
                                <td>
                                    <span class="badge bg-{{ \App\Models\PurchaseRequest::statusBadgeClass($aPr->status) }}">
                                        {{ $aPr->status_label }}
                                    </span>
                                </td>
                                <td>
                                    <span class="badge bg-secondary bg-opacity-10 text-dark">
                                        {{ ucfirst(str_replace('_', ' ', $aPr->current_owner_role ?? 'None')) }}
                                    </span>
                                </td>
                                <td>{{ $aPr->created_at->format('M d, Y') }}</td>
                                <td class="text-end">
                                    <div class="d-flex justify-content-end align-items-center gap-1 flex-wrap">
                                        <button type="button" class="btn btn-sm btn-outline-primary shadow-xs fw-semibold btn-queue-quick-materials"
                                                data-ref="{{ $aPr->pr_no }}"
                                                data-project="{{ $aPr->project?->name ?? 'Head Office' }}"
                                                data-store="{{ $aPr->store?->name ?? 'Store Unassigned' }}"
                                                data-url="{{ route('purchase-requests.show', $aPr->id) }}"
                                                data-items="{{ json_encode($aPrMaterialsJson) }}"
                                                onclick="openQuickMaterialsModalFromBtn(this)"
                                                title="View full materials without saying See Details">
                                            <i class="fa-solid fa-boxes-stacked me-1"></i>Materials ({{ $aPr->items->count() }})
                                        </button>
                                        <a href="{{ route('purchase-requests.show', $aPr->id) }}" class="btn btn-sm btn-outline-info shadow-xs fw-semibold">
                                            <i class="fas fa-eye me-1"></i> See Details
                                        </a>
                                        @if($isGlobalAdmin)
                                        <button type="button" class="btn btn-sm btn-outline-danger" title="Delete PR (Admin Only)" onclick="openDeleteQueuePrModal({{ $aPr->id }}, '{{ addslashes($aPr->pr_no) }}')">
                                            <i class="fas fa-trash-can"></i>
                                        </button>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="9" class="text-center py-5 text-muted">No purchase requests found.</td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if($allPrs->hasPages())
                <div class="card-footer bg-white border-0 py-3">
                    {{ $allPrs->links() }}
                </div>
                @endif

            {{-- ════════════════════════════════════════════════════════════════ --}}
            {{-- TAB 5: DELETED & CANCELLED REQUISITIONS (QUESTION ARCHIVE)       --}}
            {{-- ════════════════════════════════════════════════════════════════ --}}
            @elseif($currentTab === 'deleted')
                <div class="p-3 bg-light border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div>
                        <h6 class="fw-bold text-danger mb-0">
                            <i class="fas fa-trash-can me-2"></i>Deleted &amp; Cancelled Requisitions ("Deleted Questions")
                        </h6>
                        <small class="text-muted">Dedicated category for soft-deleted requests, cancelled PRs, and rejected requisitions.</small>
                    </div>
                    <span class="badge bg-danger rounded-pill px-3 py-2">{{ ($deletedPrs->total() ?? 0) + ($deletedMrs->count() ?? 0) }} Total In Category</span>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Category / Ref #</th>
                                <th>Project &amp; Store</th>
                                <th>Requester</th>
                                <th>Requested Items Summary</th>
                                <th>Status / Archive Reason</th>
                                <th>Archived Date</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            {{-- Soft-deleted or cancelled/rejected PRs --}}
                            @forelse($deletedPrs as $dPr)
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="p-2 rounded bg-danger bg-opacity-10 text-danger">
                                            <i class="fas fa-file-invoice"></i>
                                        </div>
                                        <div>
                                            <span class="fw-bold font-monospace text-dark">{{ $dPr->pr_no }}</span>
                                            <div>
                                                @if($dPr->trashed())
                                                    <span class="badge bg-danger text-white" style="font-size:9px;">Soft-Deleted PR</span>
                                                @elseif($dPr->status === \App\Models\PurchaseRequest::STATUS_CANCELLED)
                                                    <span class="badge bg-dark text-white" style="font-size:9px;">Cancelled PR</span>
                                                @elseif($dPr->status === \App\Models\PurchaseRequest::STATUS_REJECTED)
                                                    <span class="badge bg-danger bg-opacity-75 text-white" style="font-size:9px;">Rejected PR</span>
                                                @else
                                                    <span class="badge bg-secondary text-white" style="font-size:9px;">{{ $dPr->status_label }}</span>
                                                @endif

                                                @if($dPr->material_request_id)
                                                    <span class="badge bg-info bg-opacity-10 text-info border border-info border-opacity-25" style="font-size:9px;">From MR</span>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="fw-semibold text-dark">{{ $dPr->project?->name ?? 'Head Office / Unassigned' }}</div>
                                    <small class="text-muted"><i class="fas fa-warehouse me-1"></i>{{ $dPr->store?->name ?? 'Store Unassigned' }}</small>
                                </td>
                                <td>
                                    <div class="fw-semibold text-dark">{{ $dPr->requestedBy?->name ?? 'System' }}</div>
                                    <small class="text-muted">{{ $dPr->requestedBy?->email ?? '' }}</small>
                                </td>
                                @php
                                    $dPrMaterialsJson = $dPr->items->map(function($item) {
                                        return [
                                            'product_name' => $item->product?->name ?? 'Material Item',
                                            'product_code' => $item->product?->item_code ?? '',
                                            'quantity' => (float)$item->quantity,
                                            'unit' => $item->unit ?? ($item->product?->unit ?? 'Units'),
                                            'unit_cost' => (float)($item->estimated_unit_cost ?? 0),
                                            'specs' => $item->specifications ?? '',
                                        ];
                                    })->values()->toArray();
                                @endphp
                                <td class="col-procurement-materials" style="min-width: 250px;">
                                    <div class="d-flex align-items-center justify-content-between mb-1">
                                        <span class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25 px-2 py-0.5 fw-semibold" style="font-size:0.75rem;">
                                            <i class="fas fa-boxes-stacked me-1"></i>{{ $dPr->items->count() }} Material Item(s)
                                        </span>
                                        <button type="button" class="btn btn-xs btn-link text-muted p-0 text-decoration-none btn-toggle-single-materials" onclick="toggleSingleMaterials(this)" title="Toggle items display">
                                            <i class="fa-solid fa-chevron-up"></i>
                                        </button>
                                    </div>
                                    <div class="procurement-materials-list d-flex flex-column gap-1" style="max-height: 180px; overflow-y: auto;">
                                        @foreach($dPr->items as $itm)
                                            <div class="d-flex align-items-center justify-content-between p-1.5 bg-white rounded border text-nowrap shadow-2xs" style="font-size:0.78rem;">
                                                <div class="d-flex align-items-center gap-1.5 text-truncate me-2" style="max-width: 190px;">
                                                    <i class="fa-solid fa-cube text-danger" style="font-size:0.7rem;"></i>
                                                    <strong class="text-dark text-truncate" title="{{ $itm->product?->name }}">{{ $itm->product?->name ?? 'Item' }}</strong>
                                                </div>
                                                <span class="badge bg-light text-dark border px-1.5 py-0.5 font-monospace fw-bold">
                                                    {{ (float)$itm->quantity }} {{ $itm->unit ?? ($itm->product?->unit ?? 'Units') }}
                                                </span>
                                            </div>
                                        @endforeach
                                    </div>
                                    <div class="procurement-materials-compact-preview d-none text-muted small" style="font-size:11px;">
                                        {{ $dPr->items->count() }} item(s): {{ Str::limit($dPr->items->map(fn($it) => $it->product?->name)->filter()->join(', '), 30) }}
                                    </div>
                                </td>
                                <td>
                                    @if($dPr->trashed())
                                        <span class="badge bg-danger py-1 px-2 mb-1"><i class="fas fa-trash me-1"></i>Deleted From Active Queue</span>
                                    @elseif($dPr->status === \App\Models\PurchaseRequest::STATUS_REJECTED)
                                        <span class="badge bg-danger py-1 px-2 mb-1"><i class="fas fa-ban me-1"></i>Rejected</span>
                                    @elseif($dPr->status === \App\Models\PurchaseRequest::STATUS_CANCELLED)
                                        <span class="badge bg-dark py-1 px-2 mb-1"><i class="fas fa-xmark me-1"></i>Cancelled</span>
                                    @endif

                                    @if($dPr->rejection_reason)
                                        <div class="text-danger small fst-italic mt-1" style="max-width: 260px;">
                                            <i class="fas fa-comment-dots me-1"></i>Reason: {{ Str::limit($dPr->rejection_reason, 90) }}
                                        </div>
                                    @elseif($dPr->pm_sendback_reason)
                                        <div class="text-warning small fst-italic mt-1" style="max-width: 260px;">
                                            <i class="fas fa-reply me-1"></i>Sendback: {{ Str::limit($dPr->pm_sendback_reason, 90) }}
                                        </div>
                                    @endif
                                </td>
                                <td>
                                    <div class="fw-semibold text-dark">
                                        {{ ($dPr->deleted_at ?? $dPr->updated_at)->format('M d, Y') }}
                                    </div>
                                    <small class="text-muted">
                                        {{ ($dPr->deleted_at ?? $dPr->updated_at)->diffForHumans() }}
                                    </small>
                                </td>
                                <td class="text-end">
                                    <div class="d-flex justify-content-end align-items-center gap-1 flex-wrap">
                                        <button type="button" class="btn btn-sm btn-outline-primary shadow-xs fw-semibold btn-queue-quick-materials"
                                                data-ref="{{ $dPr->pr_no }}"
                                                data-project="{{ $dPr->project?->name ?? 'Head Office / Unassigned' }}"
                                                data-store="{{ $dPr->store?->name ?? 'Store Unassigned' }}"
                                                data-url="{{ !$dPr->trashed() ? route('purchase-requests.show', $dPr->id) : '' }}"
                                                data-items="{{ json_encode($dPrMaterialsJson) }}"
                                                onclick="openQuickMaterialsModalFromBtn(this)"
                                                title="View full materials without saying See Details">
                                            <i class="fa-solid fa-boxes-stacked me-1"></i>Materials ({{ $dPr->items->count() }})
                                        </button>
                                        @if(!$dPr->trashed())
                                        <a href="{{ route('purchase-requests.show', $dPr->id) }}" class="btn btn-sm btn-outline-info shadow-xs fw-semibold">
                                            <i class="fas fa-eye me-1"></i> Details
                                        </a>
                                        @endif

                                        @if($dPr->trashed() && ($isGlobalAdmin || $isAdmin))
                                        <form action="{{ route('purchase-requests.restore', $dPr->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Restore Purchase Request {{ $dPr->pr_no }} back to active queue?');">
                                            @csrf
                                            <button type="submit" class="btn btn-sm btn-outline-success shadow-xs fw-bold" title="Restore back to active queue">
                                                <i class="fas fa-trash-arrow-up me-1"></i> Restore
                                            </button>
                                        </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                            @empty
                                @if($deletedMrs->isEmpty())
                                <tr>
                                    <td colspan="7" class="text-center py-5 text-muted">
                                        <i class="fas fa-trash-can fa-3x mb-3 text-secondary opacity-50 d-block"></i>
                                        <h6 class="fw-bold text-dark">No deleted or cancelled requisitions found.</h6>
                                        <p class="small text-muted mb-0">Any requisitions or purchase requests that get cancelled, rejected, or soft-deleted are kept in this category.</p>
                                    </td>
                                </tr>
                                @endif
                            @endforelse

                            {{-- Also show rejected or cancelled Material Requests (Requisitions) --}}
                            @foreach($deletedMrs as $dMr)
                            <tr class="table-light">
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="p-2 rounded bg-secondary bg-opacity-10 text-secondary">
                                            <i class="fas fa-clipboard-question"></i>
                                        </div>
                                        <div>
                                            <span class="fw-bold font-monospace text-dark">{{ $dMr->reference_number }}</span>
                                            <div>
                                                <span class="badge bg-warning bg-opacity-25 text-dark" style="font-size:9px;">Material Requisition</span>
                                                <span class="badge bg-danger text-white" style="font-size:9px;">{{ ucfirst($dMr->status) }}</span>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="fw-semibold text-dark">{{ $dMr->project?->name ?? 'Head Office / General' }}</div>
                                    <small class="text-muted"><i class="fas fa-warehouse me-1"></i>{{ $dMr->store?->name ?? 'Store Unassigned' }}</small>
                                </td>
                                <td>
                                    <div class="fw-semibold text-dark">{{ $dMr->creator?->name ?? $dMr->requestedBy?->name ?? 'Unknown' }}</div>
                                    <small class="text-muted">{{ $dMr->creator?->email ?? '' }}</small>
                                </td>
                                @php
                                    $dMrMaterialsJson = $dMr->items->map(function($item) {
                                        return [
                                            'product_name' => $item->product?->name ?? 'Material Item',
                                            'product_code' => $item->product?->item_code ?? '',
                                            'quantity' => (float)($item->quantity_requested ?? $item->quantity ?? 0),
                                            'unit' => $item->unit ?? ($item->product?->unit ?? 'Units'),
                                            'unit_cost' => 0,
                                            'specs' => $item->notes ?? '',
                                        ];
                                    })->values()->toArray();
                                @endphp
                                <td class="col-procurement-materials" style="min-width: 250px;">
                                    <div class="d-flex align-items-center justify-content-between mb-1">
                                        <span class="badge bg-secondary bg-opacity-10 text-dark border border-secondary border-opacity-25 px-2 py-0.5 fw-semibold" style="font-size:0.75rem;">
                                            <i class="fas fa-boxes-stacked me-1"></i>{{ $dMr->items->count() }} Material Item(s)
                                        </span>
                                        <button type="button" class="btn btn-xs btn-link text-muted p-0 text-decoration-none btn-toggle-single-materials" onclick="toggleSingleMaterials(this)" title="Toggle items display">
                                            <i class="fa-solid fa-chevron-up"></i>
                                        </button>
                                    </div>
                                    <div class="procurement-materials-list d-flex flex-column gap-1" style="max-height: 180px; overflow-y: auto;">
                                        @foreach($dMr->items as $itm)
                                            <div class="d-flex align-items-center justify-content-between p-1.5 bg-white rounded border text-nowrap shadow-2xs" style="font-size:0.78rem;">
                                                <div class="d-flex align-items-center gap-1.5 text-truncate me-2" style="max-width: 190px;">
                                                    <i class="fa-solid fa-cube text-secondary" style="font-size:0.7rem;"></i>
                                                    <strong class="text-dark text-truncate" title="{{ $itm->product?->name }}">{{ $itm->product?->name ?? 'Item' }}</strong>
                                                </div>
                                                <span class="badge bg-light text-dark border px-1.5 py-0.5 font-monospace fw-bold">
                                                    {{ (float)($itm->quantity_requested ?? $itm->quantity ?? 0) }} {{ $itm->unit ?? ($itm->product?->unit ?? 'Units') }}
                                                </span>
                                            </div>
                                        @endforeach
                                    </div>
                                    <div class="procurement-materials-compact-preview d-none text-muted small" style="font-size:11px;">
                                        {{ $dMr->items->count() }} item(s): {{ Str::limit($dMr->items->map(fn($it) => $it->product?->name)->filter()->join(', '), 30) }}
                                    </div>
                                </td>
                                <td>
                                    <span class="badge bg-{{ $dMr->status === 'rejected' ? 'danger' : 'dark' }} py-1 px-2 mb-1">
                                        <i class="fas {{ $dMr->status === 'rejected' ? 'fa-ban' : 'fa-xmark' }} me-1"></i>{{ ucfirst($dMr->status) }}
                                    </span>
                                    @if(!empty($dMr->planning_rejection_reason))
                                        <div class="text-danger small fst-italic mt-1" style="max-width: 260px;">
                                            <i class="fas fa-comment-dots me-1"></i>Reason: {{ Str::limit($dMr->planning_rejection_reason, 90) }}
                                        </div>
                                    @elseif(!empty($dMr->rejection_reason))
                                        <div class="text-danger small fst-italic mt-1" style="max-width: 260px;">
                                            <i class="fas fa-comment-dots me-1"></i>Reason: {{ Str::limit($dMr->rejection_reason, 90) }}
                                        </div>
                                    @endif
                                </td>
                                <td>
                                    <div class="fw-semibold text-dark">{{ $dMr->updated_at->format('M d, Y') }}</div>
                                    <small class="text-muted">{{ $dMr->updated_at->diffForHumans() }}</small>
                                </td>
                                <td class="text-end">
                                    <div class="d-flex justify-content-end align-items-center gap-1 flex-wrap">
                                        <button type="button" class="btn btn-sm btn-outline-primary shadow-xs fw-semibold btn-queue-quick-materials"
                                                data-ref="{{ $dMr->reference_number }}"
                                                data-project="{{ $dMr->project?->name ?? 'Head Office / General' }}"
                                                data-store="{{ $dMr->store?->name ?? 'Store Unassigned' }}"
                                                data-url="{{ route('material-requests.show', $dMr->id) }}"
                                                data-items="{{ json_encode($dMrMaterialsJson) }}"
                                                onclick="openQuickMaterialsModalFromBtn(this)"
                                                title="View full materials without saying See Details">
                                            <i class="fa-solid fa-boxes-stacked me-1"></i>Materials ({{ $dMr->items->count() }})
                                        </button>
                                        <a href="{{ route('material-requests.show', $dMr->id) }}" class="btn btn-sm btn-outline-info shadow-xs fw-semibold">
                                            <i class="fas fa-eye me-1"></i> Details
                                        </a>
                                    </div>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                @if($deletedPrs->hasPages())
                <div class="card-footer bg-white border-0 py-3">
                    {{ $deletedPrs->links() }}
                </div>
                @endif
            @endif

        </div>
    </div>
</div>

@if($isGlobalAdmin)
<!-- Modal: Delete Queue Purchase Request (Global Admin Only) -->
<div class="modal fade" id="deleteQueuePrModal" tabindex="-1" aria-labelledby="deleteQueuePrModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <form id="deleteQueuePrForm" method="POST">
                @csrf
                @method('DELETE')
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title fs-6 fw-bold" id="deleteQueuePrModalLabel">
                        <i class="fas fa-triangle-exclamation me-2"></i>Permanently Delete Purchase Request
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4 text-start">
                    <p class="mb-2">Are you sure you want to permanently delete <strong id="deleteQueuePrNo"></strong>?</p>
                    <div class="alert alert-warning small mb-0">
                        <i class="fas fa-shield-halved me-1"></i> <strong>Strict Lock Policy:</strong> Non-admin users cannot delete records or add materials after creation. As Global Admin, this action will remove this PR and all associated line items permanently.
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger btn-sm fw-bold"><i class="fas fa-trash-can me-1"></i> Confirm Delete</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Delete Queue Material Request (Global Admin Only) -->
<div class="modal fade" id="deleteQueueMrModal" tabindex="-1" aria-labelledby="deleteQueueMrModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <form id="deleteQueueMrForm" method="POST">
                @csrf
                @method('DELETE')
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title fs-6 fw-bold" id="deleteQueueMrModalLabel">
                        <i class="fas fa-triangle-exclamation me-2"></i>Permanently Delete Material Request
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4 text-start">
                    <p class="mb-2">Are you sure you want to permanently delete <strong id="deleteQueueMrNo"></strong>?</p>
                    <div class="alert alert-warning small mb-0">
                        <i class="fas fa-shield-halved me-1"></i> <strong>Strict Lock Policy:</strong> Non-admin users cannot delete records or add materials after creation. As Global Admin, this action will remove this Material Request and all associated line items permanently.
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger btn-sm fw-bold"><i class="fas fa-trash-can me-1"></i> Confirm Delete</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function openDeleteQueuePrModal(prId, prNo) {
    const form = document.getElementById('deleteQueuePrForm');
    form.action = "{{ url('purchase-requests') }}/" + prId;
    document.getElementById('deleteQueuePrNo').textContent = 'PR #' + prNo;
    const modal = new bootstrap.Modal(document.getElementById('deleteQueuePrModal'));
    modal.show();
}

function openDeleteQueueMrModal(mrId, mrRef) {
    const form = document.getElementById('deleteQueueMrForm');
    form.action = "{{ url('material-requests') }}/" + mrId;
    document.getElementById('deleteQueueMrNo').textContent = 'MR: ' + mrRef;
    const modal = new bootstrap.Modal(document.getElementById('deleteQueueMrModal'));
    modal.show();
}
</script>
@endif

<!-- ========================================================================= -->
<!-- Quick Materials Preview Modal (Accessible by all roles without leaving queue) -->
<!-- ========================================================================= -->
<div class="modal fade" id="quickMaterialsModal" tabindex="-1" aria-labelledby="quickMaterialsModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-light border-bottom py-3">
                <div class="d-flex align-items-center gap-2 flex-wrap">
                    <span class="p-2 rounded-circle bg-primary bg-opacity-10 text-primary">
                        <i class="fa-solid fa-boxes-stacked fa-lg"></i>
                    </span>
                    <div>
                        <h5 class="modal-title fw-bold text-dark mb-0" id="quickMaterialsModalTitle">Requested Materials</h5>
                        <div class="d-flex align-items-center gap-2 mt-1 flex-wrap">
                            <span class="badge bg-primary font-monospace" id="quickMaterialsModalRef">PR #</span>
                            <span class="badge bg-light text-dark border"><i class="fas fa-building me-1 text-secondary"></i><span id="quickMaterialsModalProject">Project</span></span>
                            <span class="badge bg-light text-dark border"><i class="fas fa-warehouse me-1 text-success"></i><span id="quickMaterialsModalStore">Store</span></span>
                        </div>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-3">
                <div class="d-flex justify-content-between align-items-center mb-2 px-1">
                    <span class="badge bg-secondary bg-opacity-10 text-dark border px-2.5 py-1 fw-bold" id="quickMaterialsModalCount">0 Items</span>
                    <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 font-monospace px-2.5 py-1 fw-bold d-none" id="quickMaterialsModalTotal">Total Est: 0.00 ETB</span>
                </div>
                <div class="table-responsive rounded border bg-white shadow-2xs">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr class="text-secondary small">
                                <th style="width: 40px;" class="text-center">#</th>
                                <th>Material / Item Description</th>
                                <th style="width: 140px;">Quantity</th>
                                <th style="width: 130px;" class="text-end">Est. Unit Cost</th>
                                <th style="width: 130px;" class="text-end">Subtotal</th>
                                <th>Specifications / Notes</th>
                            </tr>
                        </thead>
                        <tbody id="quickMaterialsModalTableBody">
                            <!-- Injected dynamically via JS -->
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer bg-light border-top d-flex justify-content-between align-items-center py-2.5">
                <small class="text-muted fst-italic">
                    <i class="fas fa-info-circle me-1 text-primary"></i>Inspecting materials directly from My Queue.
                </small>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-outline-secondary btn-sm px-3" data-bs-dismiss="modal">Close</button>
                    <a id="quickMaterialsModalDetailsBtn" href="#" class="btn btn-primary btn-sm px-3 fw-bold shadow-xs">
                        <i class="fa-solid fa-arrow-up-right-from-square me-1"></i>Open Full Details Page
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
/* Materials column and list display */
.col-procurement-materials {
    min-width: 250px;
    max-width: 380px;
}
.procurement-materials-list {
    transition: all 0.2s ease-in-out;
}
.procurement-materials-list::-webkit-scrollbar {
    width: 4px;
}
.procurement-materials-list::-webkit-scrollbar-thumb {
    background: #cbd5e1;
    border-radius: 4px;
}
/* Compact view mode toggled globally */
#procurementQueueContainer.queue-materials-compact .procurement-materials-list {
    display: none !important;
}
#procurementQueueContainer.queue-materials-compact .procurement-materials-compact-preview {
    display: block !important;
}
#procurementQueueContainer.queue-materials-compact .btn-toggle-single-materials i {
    transform: rotate(180deg);
}
.shadow-2xs {
    box-shadow: 0 1px 2px rgba(0,0,0,0.04);
}
.btn-xs {
    padding: 0.15rem 0.45rem;
    font-size: 0.72rem;
    line-height: 1.2;
}
</style>

<script>
// State management for Queue Materials Option (expanded vs compact)
function setQueueMaterialsMode(mode) {
    const container = document.getElementById('procurementQueueContainer');
    const btnExp = document.getElementById('btnQueueMaterialsExpanded');
    const btnCmp = document.getElementById('btnQueueMaterialsCompact');
    const tabExp = document.getElementById('btnTabMaterialsExpanded');
    const tabCmp = document.getElementById('btnTabMaterialsCompact');

    if (mode === 'compact') {
        container?.classList.add('queue-materials-compact');
        localStorage.setItem('wechecha_procurement_materials_mode', 'compact');

        // Update header buttons
        if (btnExp) {
            btnExp.className = 'btn btn-sm rounded-pill px-3 py-1 fw-semibold text-muted';
        }
        if (btnCmp) {
            btnCmp.className = 'btn btn-sm rounded-pill px-3 py-1 fw-semibold btn-primary text-white shadow-xs';
        }
        // Update tab bar buttons
        if (tabExp) {
            tabExp.className = 'btn btn-xs rounded-pill px-2.5 py-0.5 fw-semibold text-muted';
        }
        if (tabCmp) {
            tabCmp.className = 'btn btn-xs rounded-pill px-2.5 py-0.5 fw-semibold btn-primary text-white shadow-2xs';
        }
    } else {
        container?.classList.remove('queue-materials-compact');
        localStorage.setItem('wechecha_procurement_materials_mode', 'expanded');

        // Update header buttons
        if (btnExp) {
            btnExp.className = 'btn btn-sm rounded-pill px-3 py-1 fw-semibold btn-primary text-white shadow-xs';
        }
        if (btnCmp) {
            btnCmp.className = 'btn btn-sm rounded-pill px-3 py-1 fw-semibold text-muted';
        }
        // Update tab bar buttons
        if (tabExp) {
            tabExp.className = 'btn btn-xs rounded-pill px-2.5 py-0.5 fw-semibold btn-primary text-white shadow-2xs';
        }
        if (tabCmp) {
            tabCmp.className = 'btn btn-xs rounded-pill px-2.5 py-0.5 fw-semibold text-muted';
        }
    }
}

// Single row toggle button
function toggleSingleMaterials(btn) {
    const col = btn.closest('.col-procurement-materials');
    if (!col) return;
    const list = col.querySelector('.procurement-materials-list');
    const preview = col.querySelector('.procurement-materials-compact-preview');
    const icon = btn.querySelector('i');

    if (!list) return;

    const isListHidden = window.getComputedStyle(list).display === 'none';

    if (isListHidden) {
        list.style.setProperty('display', 'flex', 'important');
        if (preview) preview.style.setProperty('display', 'none', 'important');
        if (icon) {
            icon.className = 'fa-solid fa-chevron-up';
        }
    } else {
        list.style.setProperty('display', 'none', 'important');
        if (preview) preview.style.setProperty('display', 'block', 'important');
        if (icon) {
            icon.className = 'fa-solid fa-chevron-down';
        }
    }
}

// Quick Materials Preview Modal
function openQuickMaterialsModalFromBtn(btn) {
    const ref = btn.dataset.ref || 'Requisition';
    const project = btn.dataset.project || 'N/A';
    const store = btn.dataset.store || 'Store Unassigned';
    const url = btn.dataset.url || '';
    let items = [];

    try {
        items = JSON.parse(btn.dataset.items || '[]');
    } catch (e) {
        items = [];
    }

    document.getElementById('quickMaterialsModalTitle').textContent = 'Materials for ' + ref;
    document.getElementById('quickMaterialsModalRef').textContent = ref;
    document.getElementById('quickMaterialsModalProject').textContent = project;
    document.getElementById('quickMaterialsModalStore').textContent = store;
    document.getElementById('quickMaterialsModalCount').textContent = items.length + ' Item(s)';

    const tbody = document.getElementById('quickMaterialsModalTableBody');
    tbody.innerHTML = '';

    let grandTotal = 0;
    let hasCosts = false;

    if (items.length === 0) {
        tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-4"><i class="fa-solid fa-box-open fa-2x mb-2 d-block opacity-50"></i>No material items recorded for this request.</td></tr>';
    } else {
        items.forEach((item, index) => {
            const qty = Number(item.quantity || 0);
            const unit = item.unit || 'Units';
            const cost = Number(item.unit_cost || 0);
            const lineTotal = qty * cost;
            if (cost > 0) hasCosts = true;
            grandTotal += lineTotal;

            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td class="text-center text-muted small fw-bold">${index + 1}</td>
                <td>
                    <div class="fw-bold text-dark">${escapeHtml(item.product_name || 'Material Item')}</div>
                    ${item.product_code ? `<span class="badge bg-light text-secondary border font-monospace" style="font-size:0.7rem;">Code: ${escapeHtml(item.product_code)}</span>` : ''}
                </td>
                <td>
                    <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 font-monospace fw-bold px-2 py-1">
                        ${qty.toLocaleString(undefined, {minimumFractionDigits: 0, maximumFractionDigits: 3})} ${escapeHtml(unit)}
                    </span>
                </td>
                <td class="font-monospace text-end">
                    ${cost > 0 ? cost.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2}) + ' ETB' : '<span class="text-muted">—</span>'}
                </td>
                <td class="font-monospace text-end fw-bold text-dark">
                    ${lineTotal > 0 ? lineTotal.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2}) + ' ETB' : '<span class="text-muted">—</span>'}
                </td>
                <td class="small text-muted fst-italic">
                    ${item.specs ? escapeHtml(item.specs) : '<span class="text-muted opacity-50">—</span>'}
                </td>
            `;
            tbody.appendChild(tr);
        });
    }

    const totalEl = document.getElementById('quickMaterialsModalTotal');
    if (hasCosts && grandTotal > 0) {
        totalEl.textContent = 'Total Est: ' + grandTotal.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2}) + ' ETB';
        totalEl.classList.remove('d-none');
    } else {
        totalEl.classList.add('d-none');
    }

    const detailsBtn = document.getElementById('quickMaterialsModalDetailsBtn');
    if (url && url.length > 5) {
        detailsBtn.href = url;
        detailsBtn.classList.remove('d-none');
    } else {
        detailsBtn.classList.add('d-none');
    }

    const modal = new bootstrap.Modal(document.getElementById('quickMaterialsModal'));
    modal.show();
}

function escapeHtml(str) {
    if (!str) return '';
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

// Restore saved preference on load
document.addEventListener('DOMContentLoaded', function() {
    const savedMode = localStorage.getItem('wechecha_procurement_materials_mode') || 'expanded';
    setQueueMaterialsMode(savedMode);
});
</script>
@endsection