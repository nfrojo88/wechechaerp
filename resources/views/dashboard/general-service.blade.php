@extends('layouts.app')
@section('title', 'General Service & Operations Dashboard')

@section('content')
<div class="container-fluid px-4 py-3">

    {{-- Page Header --}}
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
        <div>
            <h1 class="h3 mb-1 text-gray-800 fw-bold">
                <i class="fa-solid fa-screwdriver-wrench text-warning me-2"></i>General Service Dashboard
            </h1>
            <p class="text-muted small mb-0">Manage employee maintenance requests, workshop assets, logistics & store transfers.</p>
        </div>
        <div class="d-flex align-items-center gap-2">
            <a href="{{ route('general-service.maintenance.index') }}" class="btn btn-warning btn-sm fw-bold shadow-sm text-dark">
                <i class="fa-solid fa-list-check me-1"></i>All Maintenance Requests
            </a>
            <span class="badge bg-light text-dark border p-2 shadow-sm">
                <i class="fa-solid fa-calendar-day text-primary me-1"></i>{{ now()->format('l, d M Y') }}
            </span>
        </div>
    </div>

    {{-- KPI Cards Row --}}
    <div class="row g-3 mb-4">
        <!-- Pending Maintenance Requests -->
        <div class="col-xl-2 col-md-4 col-sm-6">
            <div class="card border-0 shadow-sm rounded-4 h-100 p-3" style="background: linear-gradient(135deg, #fffbeb 0%, #fef3c7 100%); border-left: 4px solid #f59e0b !important;">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Pending Requests</div>
                        <div class="h4 mb-0 fw-bold text-gray-800">{{ $kpi['pending_maintenance'] ?? 0 }}</div>
                        <small class="text-muted" style="font-size:0.75rem;">Awaiting response</small>
                    </div>
                    <div class="rounded-circle p-2 bg-warning bg-opacity-25 text-warning">
                        <i class="fa-solid fa-clock fa-xl"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- In Progress Maintenance -->
        <div class="col-xl-2 col-md-4 col-sm-6">
            <div class="card border-0 shadow-sm rounded-4 h-100 p-3" style="background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%); border-left: 4px solid #3b82f6 !important;">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">In Progress</div>
                        <div class="h4 mb-0 fw-bold text-gray-800">{{ $kpi['in_progress_maintenance'] ?? 0 }}</div>
                        <small class="text-muted" style="font-size:0.75rem;">Under active repair</small>
                    </div>
                    <div class="rounded-circle p-2 bg-primary bg-opacity-25 text-primary">
                        <i class="fa-solid fa-wrench fa-xl"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Breakdown / Urgent -->
        <div class="col-xl-2 col-md-4 col-sm-6">
            <div class="card border-0 shadow-sm rounded-4 h-100 p-3" style="background: linear-gradient(135deg, #fef2f2 0%, #fee2e2 100%); border-left: 4px solid #ef4444 !important;">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">Urgent / Breakdowns</div>
                        <div class="h4 mb-0 fw-bold text-danger">{{ $kpi['critical_breakdowns'] ?? 0 }}</div>
                        <small class="text-muted" style="font-size:0.75rem;">High priority</small>
                    </div>
                    <div class="rounded-circle p-2 bg-danger bg-opacity-25 text-danger">
                        <i class="fa-solid fa-triangle-exclamation fa-xl"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Resolved This Month -->
        <div class="col-xl-2 col-md-4 col-sm-6">
            <div class="card border-0 shadow-sm rounded-4 h-100 p-3" style="background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%); border-left: 4px solid #10b981 !important;">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Resolved (Month)</div>
                        <div class="h4 mb-0 fw-bold text-gray-800">{{ $kpi['resolved_this_month'] ?? 0 }}</div>
                        <small class="text-muted" style="font-size:0.75rem;">Completed</small>
                    </div>
                    <div class="rounded-circle p-2 bg-success bg-opacity-25 text-success">
                        <i class="fa-solid fa-circle-check fa-xl"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Store Transfers -->
        <div class="col-xl-2 col-md-4 col-sm-6">
            <div class="card border-0 shadow-sm rounded-4 h-100 p-3" style="background: linear-gradient(135deg, #faf5ff 0%, #f3e8ff 100%); border-left: 4px solid #8b5cf6 !important;">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <div class="text-xs font-weight-bold text-purple text-uppercase mb-1">Store Transfers</div>
                        <div class="h4 mb-0 fw-bold text-gray-800">{{ $kpi['transfers_count'] ?? 0 }}</div>
                        <small class="text-muted" style="font-size:0.75rem;">Logistics & dispatch</small>
                    </div>
                    <div class="rounded-circle p-2 bg-purple-subtle text-purple">
                        <i class="fa-solid fa-dolly fa-xl"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Assets in Maintenance -->
        <div class="col-xl-2 col-md-4 col-sm-6">
            <div class="card border-0 shadow-sm rounded-4 h-100 p-3" style="background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%); border-left: 4px solid #64748b !important;">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <div class="text-xs font-weight-bold text-secondary text-uppercase mb-1">Workshop Assets</div>
                        <div class="h4 mb-0 fw-bold text-gray-800">{{ $kpi['assets_in_maintenance'] ?? 0 }}</div>
                        <small class="text-muted" style="font-size:0.75rem;">Units in repair</small>
                    </div>
                    <div class="rounded-circle p-2 bg-secondary bg-opacity-25 text-secondary">
                        <i class="fa-solid fa-truck-monster fa-xl"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Main Content Row: Maintenance Queue + Store Transfers & Workshop Assets --}}
    <div class="row g-4 mb-4">

        {{-- Left Column: Maintenance Requests Queue & Reply Hub --}}
        <div class="col-xl-8">
            <div class="card border-0 shadow-sm rounded-4 mb-4">
                <div class="card-header bg-white py-3 border-0 rounded-top-4 d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div class="d-flex align-items-center gap-2">
                        <div class="rounded-circle d-flex align-items-center justify-content-center bg-warning bg-opacity-25" style="width:36px;height:36px;">
                            <i class="fa-solid fa-wrench text-warning"></i>
                        </div>
                        <div>
                            <h5 class="mb-0 fw-bold">Incoming Maintenance & Repair Reports</h5>
                            <small class="text-muted">Direct issue reports submitted by employees & equipment operators</small>
                        </div>
                    </div>
                    <a href="{{ route('general-service.maintenance.index') }}" class="btn btn-outline-warning btn-sm rounded-pill px-3">
                        View All ({{ $maintenanceRequests->count() }}) <i class="fa-solid fa-arrow-right ms-1"></i>
                    </a>
                </div>

                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0" style="font-size: 0.88rem;">
                            <thead class="table-light text-muted small text-uppercase">
                                <tr>
                                    <th class="ps-4 py-3">Request #</th>
                                    <th class="py-3">Reported By / Asset</th>
                                    <th class="py-3">Issue Type & Urgency</th>
                                    <th class="py-3">Status</th>
                                    <th class="py-3">Assigned To</th>
                                    <th class="py-3 pe-4 text-end">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($maintenanceRequests as $req)
                                @php
                                    $urgencyBadge = match($req->urgency) {
                                        'critical' => ['class' => 'bg-danger text-white', 'icon' => 'fa-fire', 'label' => 'Critical'],
                                        'urgent'   => ['class' => 'bg-warning text-dark', 'icon' => 'fa-triangle-exclamation', 'label' => 'Urgent'],
                                        'normal'   => ['class' => 'bg-info bg-opacity-25 text-info-emphasis border border-info border-opacity-25', 'icon' => 'fa-info-circle', 'label' => 'Normal'],
                                        default    => ['class' => 'bg-secondary bg-opacity-25 text-secondary', 'icon' => 'fa-circle-down', 'label' => 'Low'],
                                    };
                                    $statusBadge = match($req->status) {
                                        'pending'     => ['class' => 'bg-warning text-dark', 'icon' => 'fa-clock', 'label' => 'Pending'],
                                        'in_progress' => ['class' => 'bg-primary text-white', 'icon' => 'fa-wrench', 'label' => 'In Progress'],
                                        'resolved'    => ['class' => 'bg-success text-white', 'icon' => 'fa-circle-check', 'label' => 'Resolved'],
                                        'closed'      => ['class' => 'bg-secondary text-white', 'icon' => 'fa-lock', 'label' => 'Closed'],
                                        default       => ['class' => 'bg-light text-dark', 'icon' => 'fa-circle', 'label' => $req->status],
                                    };
                                @endphp
                                <tr>
                                    <td class="ps-4 py-3">
                                        <a href="{{ route('general-service.maintenance.show', $req->id) }}" class="fw-bold text-dark font-monospace text-decoration-none">
                                            {{ $req->request_no }}
                                        </a>
                                        <div class="text-muted" style="font-size:0.75rem;">{{ $req->created_at->diffForHumans() }}</div>
                                    </td>
                                    <td class="py-3">
                                        <div class="fw-semibold text-dark">{{ $req->asset_name }}</div>
                                        @if($req->asset_code)
                                            <span class="badge bg-dark font-monospace px-2 py-0 me-1" style="font-size:0.7rem;">{{ $req->asset_code }}</span>
                                        @endif
                                        <span class="text-muted small">
                                            <i class="fa-solid fa-user me-1 text-primary"></i>{{ $req->employee->full_name ?? ($req->reportedBy->name ?? 'Staff') }}
                                        </span>
                                    </td>
                                    <td class="py-3">
                                        <div class="d-flex flex-column gap-1">
                                            <span class="badge bg-light text-dark border px-2 py-1 text-capitalize align-self-start" style="font-size:0.75rem;">
                                                <i class="fa-solid fa-tag me-1 text-warning"></i>{{ str_replace('_', ' ', $req->issue_type) }}
                                            </span>
                                            <span class="badge {{ $urgencyBadge['class'] }} px-2 py-0 align-self-start" style="font-size:0.7rem;">
                                                <i class="fa-solid {{ $urgencyBadge['icon'] }} me-1"></i>{{ $urgencyBadge['label'] }}
                                            </span>
                                        </div>
                                    </td>
                                    <td class="py-3">
                                        <span class="badge {{ $statusBadge['class'] }} px-2 py-1">
                                            <i class="fa-solid {{ $statusBadge['icon'] }} me-1"></i>{{ $statusBadge['label'] }}
                                        </span>
                                    </td>
                                    <td class="py-3 text-muted small">
                                        @if($req->assignedTo)
                                            <div class="d-flex align-items-center gap-1">
                                                <i class="fa-solid fa-user-gear text-primary"></i>
                                                <span>{{ $req->assignedTo->name }}</span>
                                            </div>
                                        @else
                                            <span class="text-muted fst-italic">Unassigned</span>
                                        @endif
                                    </td>
                                    <td class="py-3 pe-4 text-end">
                                        <div class="btn-group btn-group-sm">
                                            <button type="button" class="btn btn-warning btn-sm text-dark fw-bold px-2 py-1"
                                                onclick="openReplyModal({{ json_encode($req) }})"
                                                title="Reply & Update Maintenance Status">
                                                <i class="fa-solid fa-reply me-1"></i>Reply
                                            </button>
                                            <a href="{{ route('general-service.maintenance.show', $req->id) }}" class="btn btn-outline-secondary btn-sm px-2 py-1" title="View Full Report">
                                                <i class="fa-solid fa-eye"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="6" class="text-center py-5 text-muted">
                                        <i class="fa-solid fa-circle-check fa-3x text-success opacity-50 mb-2 d-block"></i>
                                        <strong>No active maintenance reports!</strong>
                                        <div class="small">All company assets and equipment are reported healthy.</div>
                                    </td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            {{-- Store Transfers Section --}}
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-header bg-white py-3 border-0 rounded-top-4 d-flex justify-content-between align-items-center">
                    <div class="d-flex align-items-center gap-2">
                        <div class="rounded-circle d-flex align-items-center justify-content-center bg-purple-subtle" style="width:36px;height:36px;">
                            <i class="fa-solid fa-dolly text-purple"></i>
                        </div>
                        <div>
                            <h5 class="mb-0 fw-bold">Store Manager Inter-Store Transfers & Logistics</h5>
                            <small class="text-muted">Material transfers coming to/from General Service & Workshops</small>
                        </div>
                    </div>
                    <span class="badge bg-purple-subtle text-purple border px-3 py-1">{{ $transfers->count() }} Records</span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0" style="font-size: 0.88rem;">
                            <thead class="table-light text-muted small text-uppercase">
                                <tr>
                                    <th class="ps-4 py-3">Transfer #</th>
                                    <th class="py-3">From Store</th>
                                    <th class="py-3">To Store</th>
                                    <th class="py-3">Items</th>
                                    <th class="py-3">Status</th>
                                    <th class="py-3 pe-4 text-end">Required Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($transfers as $trf)
                                @php
                                    $trfBadge = match($trf->status) {
                                        'approved'   => ['class' => 'bg-success', 'label' => 'Approved'],
                                        'in_transit' => ['class' => 'bg-primary', 'label' => 'In Transit'],
                                        'draft'      => ['class' => 'bg-secondary', 'label' => 'Draft'],
                                        'received'   => ['class' => 'bg-dark', 'label' => 'Received'],
                                        default      => ['class' => 'bg-warning text-dark', 'label' => ucfirst($trf->status)],
                                    };
                                @endphp
                                <tr>
                                    <td class="ps-4 py-3">
                                        <strong class="font-monospace text-dark">{{ $trf->transfer_no }}</strong>
                                        <div class="text-muted small">{{ $trf->reason ?: 'Stock movement' }}</div>
                                    </td>
                                    <td class="py-3">
                                        <i class="fa-solid fa-warehouse text-muted me-1"></i>{{ $trf->fromStore->name ?? 'Main Store' }}
                                    </td>
                                    <td class="py-3">
                                        <i class="fa-solid fa-location-dot text-primary me-1"></i>{{ $trf->toStore->name ?? 'Workshop / Site' }}
                                    </td>
                                    <td class="py-3">
                                        <span class="badge bg-light text-dark border">{{ $trf->items->count() }} items</span>
                                    </td>
                                    <td class="py-3">
                                        <span class="badge {{ $trfBadge['class'] }}">{{ $trfBadge['label'] }}</span>
                                    </td>
                                    <td class="py-3 pe-4 text-end text-muted small">
                                        {{ $trf->required_date ? $trf->required_date->format('d M Y') : 'Immediate' }}
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="6" class="text-center py-4 text-muted small">No recent store transfers recorded.</td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        {{-- Right Column: Workshop Assets & Quick Actions --}}
        <div class="col-xl-4">

            {{-- Workshop Assets / Under Repair --}}
            <div class="card border-0 shadow-sm rounded-4 mb-4">
                <div class="card-header bg-white py-3 border-0 rounded-top-4 d-flex justify-content-between align-items-center">
                    <div class="d-flex align-items-center gap-2">
                        <div class="rounded-circle d-flex align-items-center justify-content-center bg-danger bg-opacity-10" style="width:36px;height:36px;">
                            <i class="fa-solid fa-truck-monster text-danger"></i>
                        </div>
                        <div>
                            <h6 class="mb-0 fw-bold">Assets Under Maintenance</h6>
                            <small class="text-muted">Equipment needing repair / overhaul</small>
                        </div>
                    </div>
                    <span class="badge bg-danger rounded-pill">{{ $maintenanceAssets->count() }}</span>
                </div>
                <div class="card-body p-3">
                    <div class="d-flex flex-column gap-2">
                        @forelse($maintenanceAssets as $mAsset)
                        <div class="p-3 border rounded-3 bg-light bg-opacity-50">
                            <div class="d-flex justify-content-between align-items-start mb-1">
                                <span class="badge bg-dark font-monospace">{{ $mAsset->unit_code }}</span>
                                <span class="badge bg-warning text-dark">{{ ucfirst(str_replace('_', ' ', $mAsset->condition)) }}</span>
                            </div>
                            <strong class="d-block text-dark small mb-1">{{ $mAsset->parentAsset->name ?? 'Fixed Asset' }}</strong>
                            <div class="d-flex justify-content-between align-items-center text-muted" style="font-size:0.75rem;">
                                <span><i class="fa-solid fa-warehouse me-1"></i>{{ $mAsset->current_location ?: 'Workshop' }}</span>
                                <span><i class="fa-solid fa-user me-1"></i>{{ $mAsset->assignedEmployee->full_name ?? 'Unassigned' }}</span>
                            </div>
                        </div>
                        @empty
                        <div class="text-center py-4 text-muted small">
                            <i class="fa-solid fa-check-double fa-2x text-success opacity-50 mb-2 d-block"></i>
                            No assets currently in workshop maintenance.
                        </div>
                        @endforelse
                    </div>
                </div>
            </div>

            {{-- General Service Quick Operations Card --}}
            <div class="card border-0 shadow-sm rounded-4 overflow-hidden" style="background: linear-gradient(135deg, #1e293b 0%, #334155 100%); color:#fff;">
                <div class="p-4">
                    <h6 class="fw-bold mb-2 text-warning"><i class="fa-solid fa-bolt me-2"></i>Quick Actions</h6>
                    <p class="text-white-50 small mb-3">Instant navigation for general service daily duties.</p>
                    <div class="d-grid gap-2">
                        <a href="{{ route('general-service.maintenance.index') }}" class="btn btn-warning btn-sm fw-bold text-dark text-start">
                            <i class="fa-solid fa-list-check me-2"></i>Manage All Maintenance Tickets
                        </a>
                        <a href="{{ route('store-manager.fixed-assets.index') }}" class="btn btn-outline-light btn-sm text-start">
                            <i class="fa-solid fa-truck-pickup me-2"></i>View Company Fixed Assets & Fleet
                        </a>
                        <a href="{{ route('transfers.index') }}" class="btn btn-outline-light btn-sm text-start">
                            <i class="fa-solid fa-truck-ramp-box me-2"></i>Store Material Transfers
                        </a>
                    </div>
                </div>
            </div>

        </div>
    </div>

</div>

{{-- Quick Reply / Update Maintenance Status Modal --}}
<div class="modal fade" id="quickReplyMaintenanceModal" tabindex="-1" aria-hidden="true" style="z-index: 1060;">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <form id="quickReplyForm" method="POST" action="">
                @csrf
                @method('PUT')
                <div class="modal-header border-0 px-4 py-3" style="background: linear-gradient(135deg, #f59e0b, #d97706); color:#fff;">
                    <div class="d-flex align-items-center gap-2">
                        <div class="rounded-circle d-flex align-items-center justify-content-center bg-white bg-opacity-25" style="width:36px;height:36px;">
                            <i class="fa-solid fa-reply text-white"></i>
                        </div>
                        <div>
                            <h5 class="modal-title fs-6 fw-bold mb-0">Reply & Update Maintenance Request</h5>
                            <small class="text-white-50" id="qr_request_no_sub"></small>
                        </div>
                    </div>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body p-4 bg-white">
                    {{-- Asset & Reporter Info Banner --}}
                    <div class="p-3 border rounded-3 bg-light mb-3">
                        <div class="row g-2 small">
                            <div class="col-sm-6">
                                <span class="text-muted">Asset:</span>
                                <strong id="qr_asset_name" class="text-dark"></strong>
                                <span class="badge bg-dark font-monospace ms-1" id="qr_asset_code"></span>
                            </div>
                            <div class="col-sm-6">
                                <span class="text-muted">Reported By:</span>
                                <strong id="qr_employee_name" class="text-dark"></strong>
                            </div>
                            <div class="col-12 mt-2">
                                <span class="text-muted">Issue Description:</span>
                                <div id="qr_issue_desc" class="p-2 bg-white border rounded text-dark mt-1 font-monospace" style="font-size:0.82rem;"></div>
                            </div>
                        </div>
                    </div>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">Update Status <span class="text-danger">*</span></label>
                            <select name="status" id="qr_status" class="form-select" required>
                                <option value="pending">🟡 Pending (Received)</option>
                                <option value="in_progress">🔵 In Progress (Under Repair)</option>
                                <option value="resolved">🟢 Resolved (Fixed & Ready)</option>
                                <option value="closed">⚪ Closed (Archived)</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">Assign Technician / Handler</label>
                            <select name="assigned_to_user_id" id="qr_assigned_to" class="form-select">
                                <option value="">— Unassigned —</option>
                                @foreach($staff as $st)
                                    <option value="{{ $st->id }}">{{ $st->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold small">General Service Reply & Action Notes</label>
                            <textarea name="admin_notes" id="qr_admin_notes" class="form-control" rows="4"
                                placeholder="Explain the diagnosis, action taken, replacement parts used, or schedule for the employee..."></textarea>
                            <div class="form-text">This response will be visible to the reporting employee on their profile.</div>
                        </div>
                    </div>
                </div>

                <div class="modal-footer bg-light border-0 py-3 px-4 d-flex justify-content-between">
                    <a href="#" id="qr_full_view_btn" class="btn btn-outline-secondary btn-sm">
                        <i class="fa-solid fa-up-right-from-square me-1"></i>Full Details Page
                    </a>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-sm btn-secondary px-3" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-sm btn-warning fw-bold px-4 text-dark shadow-sm">
                            <i class="fa-solid fa-floppy-disk me-1"></i>Save & Send Reply
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script>
function openReplyModal(req) {
    document.getElementById('qr_request_no_sub').textContent = req.request_no;
    document.getElementById('qr_asset_name').textContent = req.asset_name || 'Fixed Asset';
    document.getElementById('qr_asset_code').textContent = req.asset_code || '';
    document.getElementById('qr_employee_name').textContent = (req.employee ? req.employee.full_name : (req.reported_by ? req.reported_by.name : 'Staff'));
    document.getElementById('qr_issue_desc').textContent = req.description || 'No description provided';
    document.getElementById('qr_status').value = req.status || 'pending';
    document.getElementById('qr_assigned_to').value = req.assigned_to_user_id || '';
    document.getElementById('qr_admin_notes').value = req.admin_notes || '';

    const form = document.getElementById('quickReplyForm');
    form.action = "{{ url('general-service/maintenance') }}/" + req.id + "/status";

    document.getElementById('qr_full_view_btn').href = "{{ url('general-service/maintenance') }}/" + req.id;

    bootstrap.Modal.getOrCreateInstance(document.getElementById('quickReplyMaintenanceModal')).show();
}
</script>
@endpush
