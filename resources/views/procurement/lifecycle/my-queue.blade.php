@extends('layouts.app')

@php
    $authUser = auth()->user();
    $rawUserRoles = $authUser ? $authUser->roles->pluck('name')->map(fn($r) => strtolower(str_replace([' ', '-'], '_', trim($r))))->toArray() : [];
    $isGlobalAdmin = in_array('global_admin', $rawUserRoles) || in_array('admin', $rawUserRoles);
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
<div class="container-fluid px-4 py-3">
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
    </div>

    <!-- Filter Bar -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body p-3">
            <form method="GET" action="{{ route('procurement.my-queue') }}" class="row g-2 align-items-center">
                <input type="hidden" name="tab" value="{{ $currentTab }}">
                <div class="col-md-4">
                    <select name="project_id" class="form-select form-select-sm">
                        <option value="">-- All Projects --</option>
                        @foreach($projects as $p)
                            <option value="{{ $p->id }}" {{ request('project_id') == $p->id ? 'selected' : '' }}>{{ $p->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4">
                    <select name="status" class="form-select form-select-sm">
                        <option value="">-- All Statuses --</option>
                        @foreach(\App\Models\PurchaseRequest::statusLabels() as $key => $lbl)
                            <option value="{{ $key }}" {{ request('status') == $key ? 'selected' : '' }}>{{ $lbl }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4 d-flex gap-2">
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
                            <th>Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($emergencyMrs as $mr)
                        <tr>
                            <td><strong>{{ $mr->reference_number }}</strong></td>
                            <td>{{ $mr->project?->name }}</td>
                            <td>{{ $mr->requestedBy?->name ?? $mr->creator?->name ?? 'N/A' }}</td>
                            <td>{{ $mr->created_at->format('M d, Y H:i') }}</td>
                            <td>
                                <div class="d-flex gap-1">
                                    <form method="POST" action="{{ route('material-requests.planning-approve', $mr->id) }}">
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-success"><i class="fas fa-check me-1"></i> Approve</button>
                                    </form>
                                    <button type="button" class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#rejectMrModal{{ $mr->id }}">
                                        <i class="fas fa-times me-1"></i> Reject
                                    </button>
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
                <!-- Tab 4: All Company PR History (Auditor / Admin / GM) -->
                @if($isAdmin || $isAuditorUser || $isGmUser || $isCoordinatorUser || $isPurchaseManagerUser)
                <li class="nav-item">
                    <a class="nav-link {{ $currentTab === 'all' ? 'active shadow-sm' : '' }} fw-bold" 
                       href="{{ route('procurement.my-queue', array_merge(request()->query(), ['tab' => 'all'])) }}">
                        <i class="fas fa-list-check me-1"></i> All Company PR History
                        <span class="badge {{ $currentTab === 'all' ? 'bg-white text-secondary' : 'bg-secondary text-white' }} ms-1">{{ $kpi['all_prs'] }}</span>
                    </a>
                </li>
                @endif
            </ul>

            <div class="text-muted small">
                @if($currentTab === 'my_created')
                    <span class="badge bg-light text-dark border"><i class="fas fa-clock-rotate-left me-1 text-primary"></i> Created Requisition History</span>
                @elseif($currentTab === 'completed')
                    <span class="badge bg-light text-dark border"><i class="fas fa-check-circle me-1 text-success"></i> Stocked &amp; Intake Completed Archives</span>
                @elseif($currentTab === 'all')
                    <span class="badge bg-light text-dark border"><i class="fas fa-globe me-1 text-secondary"></i> Organization-wide Procurement</span>
                @else
                    <span class="badge bg-light text-dark border"><i class="fas fa-inbox me-1 text-warning"></i> Role Pending Action Feed</span>
                @endif
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
                                <th>Project / Purpose / Store</th>
                                <th>Channel Source</th>
                                <th>Priority</th>
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
                            @endphp
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
                                <td><span class="badge bg-info text-dark border">Material Request</span></td>
                                <td><span class="badge bg-secondary">Normal</span></td>
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
                                    @if($linkedPr)
                                        <a href="{{ route('purchase-requests.show', $linkedPr->id) }}" class="btn btn-sm btn-outline-primary">
                                            <i class="fas fa-eye me-1"></i> View PR
                                        </a>
                                    @else
                                        <a href="{{ route('material-requests.show', $mr) }}" class="btn btn-sm btn-outline-secondary">
                                            <i class="fas fa-eye me-1"></i> View MR
                                        </a>
                                    @endif
                                </td>
                            </tr>
                            @endforeach

                            {{-- Pending Purchase Requests --}}
                            @foreach($myPrs as $pr)
                            @php
                                $canActOnThisPr = false;
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
                                        elseif ($pr->status === 'draft' && ($authUser && $pr->requested_by === $authUser->id || $isCoordinatorUser)) $canActOnThisPr = true;
                                    }
                                }
                            @endphp
                            <tr class="{{ $pr->is_office_request && $pr->status === 'pending_hr_approval' ? 'table-warning bg-opacity-25' : '' }}">
                                <td>
                                    <a href="{{ route('purchase-requests.show', $pr->id) }}" class="fw-bold text-decoration-none">
                                        {{ $pr->pr_no }}
                                    </a>
                                </td>
                                <td>
                                    <span class="fw-medium text-dark">{{ $pr->project?->name ?? 'N/A' }}</span>
                                    @php
                                        $destStore = $pr->store ?? $pr->materialRequest?->destinationStore ?? $pr->materialRequest?->store;
                                    @endphp
                                    @if($destStore)
                                        <small class="text-muted d-block"><i class="fas fa-warehouse me-1"></i>Store: {{ $destStore->name }}</small>
                                    @endif
                                    <small class="text-muted d-block">{{ $pr->items->count() }} item(s)</small>
                                </td>
                                <td>
                                    @if($pr->materialRequest)
                                        <span class="badge bg-light text-dark border">From Requisition</span>
                                    @else
                                        <span class="badge bg-light text-dark border">Direct PR</span>
                                    @endif
                                </td>
                                <td>
                                    <span class="badge bg-{{ $pr->priority === 'urgent' ? 'danger' : ($pr->priority === 'high' ? 'warning' : 'secondary') }}">
                                        {{ ucfirst($pr->priority) }}
                                    </span>
                                </td>
                                <td>
                                    <span class="badge bg-{{ \App\Models\PurchaseRequest::statusBadgeClass($pr->status) }}">
                                        {{ $pr->status_label }}
                                    </span>
                                </td>
                                <td>
                                    @if(!empty($isFinalIntakeStage))
                                        <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 fw-semibold">
                                            <i class="fas fa-boxes-packing me-1"></i> Store Keeper
                                        </span>
                                    @else
                                        <span class="badge bg-secondary bg-opacity-10 text-dark">
                                            <i class="fas fa-user-tag me-1"></i> {{ ucfirst(str_replace('_', ' ', $pr->current_owner_role ?? 'None')) }}
                                        </span>
                                    @endif
                                </td>
                                <td>{{ $pr->created_at->format('M d, Y') }}</td>
                                <td class="text-end">
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
                                    @else
                                        <a href="{{ route('purchase-requests.show', $pr->id) }}" class="btn btn-sm btn-outline-secondary">
                                            <i class="fas fa-eye me-1"></i> View Details
                                        </a>
                                    @endif
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
                                <td>
                                    <div class="fw-bold small text-dark mb-1">
                                        <i class="fas fa-boxes-stacked me-1 text-secondary"></i>{{ $cPr->items->count() }} Material Item(s)
                                    </div>
                                    <div class="text-muted small" style="font-size:11px;">
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
                                    <a href="{{ route('purchase-requests.show', $cPr->id) }}" class="btn btn-sm btn-outline-primary shadow-xs fw-semibold">
                                        <i class="fas fa-route me-1"></i> Track Lifecycle
                                    </a>
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
                                <td>
                                    <span class="badge bg-light text-dark border">{{ $cMr->items->count() }} Items</span>
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
                                    <a href="{{ route('material-requests.show', $cMr) }}" class="btn btn-sm btn-outline-info shadow-xs">
                                        <i class="fas fa-eye me-1"></i> View Requisition
                                    </a>
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
                                <td>
                                    <div class="small">
                                        @foreach($compPr->items as $cItm)
                                            <span class="badge bg-light text-dark border me-1 mb-1">
                                                {{ $cItm->product?->name ?? 'Material' }}: {{ (float)$cItm->quantity }} {{ $cItm->unit }}
                                            </span>
                                        @endforeach
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
                                    <a href="{{ route('purchase-requests.show', $compPr->id) }}" class="btn btn-sm btn-outline-success shadow-xs fw-semibold">
                                        <i class="fas fa-file-invoice me-1"></i> View Record
                                    </a>
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
                                <th>Items</th>
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
                                </td>
                                <td>
                                    <strong>{{ $aPr->project?->name ?? 'Head Office' }}</strong>
                                    <small class="text-muted d-block">{{ $aPr->store?->name ?? 'Store Unassigned' }}</small>
                                </td>
                                <td>{{ $aPr->requestedBy?->name ?? 'N/A' }}</td>
                                <td><span class="badge bg-light text-dark border">{{ $aPr->items->count() }} items</span></td>
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
                                    <a href="{{ route('purchase-requests.show', $aPr->id) }}" class="btn btn-sm btn-outline-primary">
                                        <i class="fas fa-eye me-1"></i> View
                                    </a>
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
            @endif

        </div>
    </div>
</div>
@endsection