@extends('layouts.app')
@section('title', 'General Service & Operations Dashboard')

@section('content')
<div class="wc-container py-3 py-md-4">

    {{-- Page Header --}}
    <x-page-header
        title="General Service & Operations Dashboard"
        subtitle="Manage employee maintenance requests, workshop assets, logistics &amp; store transfers"
        badge="General Service"
    >
        <x-slot:actions>
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <span class="badge bg-white text-secondary border px-3 py-2 shadow-xs rounded-pill d-none d-sm-inline-flex align-items-center">
                    <i class="fa-regular fa-calendar text-primary me-2"></i>{{ now()->format('l, d M Y') }}
                </span>
                <a href="{{ Route::has('general-service.maintenance.create') ? route('general-service.maintenance.create') : url('/general-service/maintenance/create') }}" class="btn btn-primary btn-sm shadow-xs" style="min-height: 38px; display: inline-flex; align-items: center;">
                    <i class="fa-solid fa-plus me-1"></i> New Service Request
                </a>
                <a href="{{ Route::has('general-service.maintenance.index') ? route('general-service.maintenance.index') : url('/general-service/maintenance') }}" class="btn btn-outline-warning text-dark btn-sm shadow-xs" style="min-height: 38px; display: inline-flex; align-items: center;">
                    <i class="fa-solid fa-list-check me-1"></i> All Requests
                </a>
            </div>
        </x-slot:actions>
    </x-page-header>

    {{-- KPI Cards Row --}}
    <div class="row g-3 mb-4">
        <!-- Pending Maintenance Requests -->
        <div class="col-12 col-sm-6 col-xl-2">
            <x-kpi-card
                title="Pending Requests"
                :value="$kpi['pending_maintenance'] ?? 0"
                subtitle="Awaiting response"
                icon="fa-solid fa-clock"
                tone="warning"
            />
        </div>

        <!-- In Progress Maintenance -->
        <div class="col-12 col-sm-6 col-xl-2">
            <x-kpi-card
                title="In Progress"
                :value="$kpi['in_progress_maintenance'] ?? 0"
                subtitle="Under active repair"
                icon="fa-solid fa-wrench"
                tone="primary"
            />
        </div>

        <!-- Breakdown / Urgent -->
        <div class="col-12 col-sm-6 col-xl-2">
            <x-kpi-card
                title="Urgent / Breakdowns"
                :value="$kpi['critical_breakdowns'] ?? 0"
                subtitle="High priority"
                icon="fa-solid fa-triangle-exclamation"
                tone="danger"
            />
        </div>

        <!-- Resolved This Month -->
        <div class="col-12 col-sm-6 col-xl-2">
            <x-kpi-card
                title="Resolved (Month)"
                :value="$kpi['resolved_this_month'] ?? 0"
                subtitle="Completed repairs"
                icon="fa-solid fa-circle-check"
                tone="success"
            />
        </div>

        <!-- Store Transfers -->
        <div class="col-12 col-sm-6 col-xl-2">
            <x-kpi-card
                title="Store Transfers"
                :value="$kpi['transfers_count'] ?? 0"
                subtitle="Logistics & dispatch"
                icon="fa-solid fa-dolly"
                tone="purple"
            />
        </div>

        <!-- Assets in Maintenance -->
        <div class="col-12 col-sm-6 col-xl-2">
            <x-kpi-card
                title="Workshop Assets"
                :value="$kpi['assets_in_maintenance'] ?? 0"
                subtitle="Units in repair"
                icon="fa-solid fa-truck-monster"
                tone="neutral"
            />
        </div>
    </div>

    {{-- Main Content Row: Maintenance Queue + Store Transfers & Workshop Assets --}}
    <div class="row g-4 mb-4">

        {{-- Left Column: Maintenance Requests Queue & Reply Hub --}}
        <div class="col-xl-8">
            <div class="card border-0 shadow-sm rounded-3 mb-4 bg-white">
                <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div class="d-flex align-items-center gap-2">
                        <div class="rounded-circle d-flex align-items-center justify-content-center bg-warning bg-opacity-25" style="width:36px;height:36px;">
                            <i class="fa-solid fa-wrench text-warning"></i>
                        </div>
                        <div>
                            <h6 class="mb-0 fw-bold text-dark">Incoming Maintenance &amp; Repair Reports</h6>
                            <small class="text-muted">Direct issue reports submitted by employees &amp; equipment operators</small>
                        </div>
                    </div>
                    <a href="{{ route('general-service.maintenance.index') }}" class="btn btn-outline-warning text-dark btn-sm rounded-pill px-3" style="min-height: 34px; display: inline-flex; align-items: center;">
                        View All ({{ $maintenanceRequests->count() }}) <i class="fa-solid fa-arrow-right ms-1"></i>
                    </a>
                </div>

                <div class="card-body p-0">
                    <div class="table-responsive wc-table-mobile-cards">
                        <table class="table table-hover align-middle mb-0" style="font-size: 0.88rem;">
                            <thead class="table-light text-muted small text-uppercase">
                                <tr>
                                    <th class="ps-4 py-3">Request #</th>
                                    <th class="py-3">Reported By / Asset</th>
                                    <th class="py-3">Issue Type &amp; Urgency</th>
                                    <th class="py-3">Status</th>
                                    <th class="py-3">Assigned To</th>
                                    <th class="py-3 pe-4 text-end">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($maintenanceRequests as $req)
                                <tr>
                                    <td data-label="Request #" class="ps-4 py-3">
                                        <a href="{{ route('general-service.maintenance.show', $req->id) }}" class="fw-bold text-dark font-monospace text-decoration-none">
                                            {{ $req->request_no }}
                                        </a>
                                        <div class="text-muted" style="font-size:0.75rem;">{{ $req->created_at->diffForHumans() }}</div>
                                    </td>
                                    <td data-label="Asset & Reporter" class="py-3">
                                        <div class="fw-semibold text-dark">{{ $req->asset_name }}</div>
                                        @if($req->asset_code)
                                            <span class="badge bg-dark font-monospace px-2 py-0 me-1" style="font-size:0.7rem;">{{ $req->asset_code }}</span>
                                        @endif
                                        <span class="text-muted small">
                                            <i class="fa-solid fa-user-pen me-1 text-primary"></i>{{ $req->reportedBy?->name ?? 'Requester' }} &bull; <i class="fa-solid fa-gavel me-1 text-warning"></i>To: {{ $req->assignedTo?->name ?? 'Wondimagnhu Siyum' }}
                                        </span>
                                    </td>
                                    <td data-label="Issue & Urgency" class="py-3">
                                        <div class="d-flex flex-column gap-1">
                                            <span class="badge bg-light text-dark border px-2 py-1 text-capitalize align-self-start" style="font-size:0.75rem;">
                                                <i class="fa-solid fa-tag me-1 text-warning"></i>{{ str_replace('_', ' ', $req->issue_type) }}
                                            </span>
                                            @php
                                                $uTone = match($req->urgency) {
                                                    'critical' => 'danger',
                                                    'urgent' => 'warning',
                                                    'normal' => 'info',
                                                    default => 'neutral'
                                                };
                                            @endphp
                                            <x-status-badge :status="$req->urgency" :tone="$uTone" />
                                        </div>
                                    </td>
                                    <td data-label="Status" class="py-3">
                                        <x-status-badge :status="$req->status" />
                                    </td>
                                    <td data-label="Assigned To" class="py-3 text-muted small">
                                        @if($req->assignedTo)
                                            <div class="d-flex align-items-center gap-1">
                                                <i class="fa-solid fa-user-gear text-primary"></i>
                                                <span>{{ $req->assignedTo->name }}</span>
                                            </div>
                                        @else
                                            <span class="text-muted fst-italic">Unassigned</span>
                                        @endif
                                    </td>
                                    <td data-label="Action" class="py-3 pe-4 text-end">
                                        <div class="btn-group btn-group-sm">
                                            <button type="button" class="btn btn-warning btn-sm text-dark fw-bold px-2 py-1"
                                                onclick="openReplyModal({{ json_encode($req) }})"
                                                title="Reply & Update Maintenance Status"
                                                style="min-height: 34px;">
                                                <i class="fa-solid fa-reply me-1"></i>Reply
                                            </button>
                                            <a href="{{ route('general-service.maintenance.show', $req->id) }}" class="btn btn-outline-secondary btn-sm px-2 py-1" title="View Full Report" style="min-height: 34px; display: inline-flex; align-items: center;">
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
            <div class="card border-0 shadow-sm rounded-3 bg-white">
                <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div class="d-flex align-items-center gap-2">
                        <div class="rounded-circle d-flex align-items-center justify-content-center bg-purple bg-opacity-10" style="width:36px;height:36px;">
                            <i class="fa-solid fa-dolly text-purple"></i>
                        </div>
                        <div>
                            <h6 class="mb-0 fw-bold text-dark">Store Manager Inter-Store Transfers &amp; Logistics</h6>
                            <small class="text-muted">Material transfers coming to/from General Service &amp; Workshops</small>
                        </div>
                    </div>
                    <span class="badge bg-purple bg-opacity-10 text-purple border px-3 py-1 rounded-pill">{{ $transfers->count() }} Records</span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive wc-table-mobile-cards">
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
                                <tr>
                                    <td data-label="Transfer #" class="ps-4 py-3">
                                        <strong class="font-monospace text-dark">{{ $trf->transfer_no }}</strong>
                                        <div class="text-muted small">{{ $trf->reason ?: 'Stock movement' }}</div>
                                    </td>
                                    <td data-label="From Store" class="py-3">
                                        <i class="fa-solid fa-warehouse text-muted me-1"></i>{{ $trf->fromStore->name ?? 'Main Store' }}
                                    </td>
                                    <td data-label="To Store" class="py-3">
                                        <i class="fa-solid fa-location-dot text-primary me-1"></i>{{ $trf->toStore->name ?? 'Workshop / Site' }}
                                    </td>
                                    <td data-label="Items" class="py-3">
                                        <span class="badge bg-light text-dark border">{{ $trf->items->count() }} items</span>
                                    </td>
                                    <td data-label="Status" class="py-3">
                                        <x-status-badge :status="$trf->status" />
                                    </td>
                                    <td data-label="Required Date" class="py-3 pe-4 text-end text-muted small">
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
            <div class="card border-0 shadow-sm rounded-3 mb-4 bg-white">
                <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                    <div class="d-flex align-items-center gap-2">
                        <div class="rounded-circle d-flex align-items-center justify-content-center bg-danger bg-opacity-10" style="width:36px;height:36px;">
                            <i class="fa-solid fa-truck-monster text-danger"></i>
                        </div>
                        <div>
                            <h6 class="mb-0 fw-bold text-dark">Assets Under Maintenance</h6>
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
            <div class="card border-0 shadow-sm rounded-3 bg-white">
                <div class="card-header bg-white py-3 border-bottom">
                    <h6 class="m-0 fw-bold text-dark"><i class="fa-solid fa-bolt text-warning me-2"></i>Quick Actions</h6>
                </div>
                <div class="card-body d-flex flex-column gap-2 p-3">
                    <a href="{{ route('general-service.vehicle-reminders.dashboard') }}" class="btn btn-warning text-dark text-start fw-semibold py-2 d-flex align-items-center" style="min-height: 44px;">
                        <i class="fa-solid fa-clock-rotate-left me-2"></i> Vehicle Reminders (Bolo &amp; Service)
                    </a>
                    <a href="{{ route('general-service.maintenance.index') }}" class="btn btn-outline-primary text-start fw-semibold py-2 d-flex align-items-center" style="min-height: 44px;">
                        <i class="fa-solid fa-list-check me-2"></i> Manage All Maintenance Tickets
                    </a>
                    <a href="{{ route('store-manager.fixed-assets.index') }}" class="btn btn-outline-secondary text-start fw-semibold py-2 d-flex align-items-center" style="min-height: 44px;">
                        <i class="fa-solid fa-truck-pickup me-2"></i> Company Fixed Assets &amp; Fleet
                    </a>
                    <a href="{{ route('transfers.index') }}" class="btn btn-outline-info text-start fw-semibold py-2 d-flex align-items-center" style="min-height: 44px;">
                        <i class="fa-solid fa-truck-ramp-box me-2"></i> Store Material Transfers
                    </a>
                </div>
            </div>

        </div>
    </div>

</div>

{{-- Quick Reply / Update Maintenance Status Modal --}}
<div class="modal fade" id="quickReplyMaintenanceModal" tabindex="-1" aria-hidden="true" style="z-index: 1060;">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow-lg rounded-3 overflow-hidden">
            <form id="quickReplyForm" method="POST" action="">
                @csrf
                @method('PUT')
                <div class="modal-header border-0 px-4 py-3" style="background: linear-gradient(135deg, #f59e0b, #d97706); color:#fff;">
                    <div class="d-flex align-items-center gap-2">
                        <div class="rounded-circle d-flex align-items-center justify-content-center bg-white bg-opacity-25" style="width:36px;height:36px;">
                            <i class="fa-solid fa-reply text-white"></i>
                        </div>
                        <div>
                            <h5 class="modal-title fs-6 fw-bold mb-0">Reply &amp; Update Maintenance Request</h5>
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
                                <option value="resolved">🟢 Resolved (Fixed &amp; Ready)</option>
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
                            <label class="form-label fw-semibold small">General Service Reply &amp; Action Notes</label>
                            <textarea name="admin_notes" id="qr_admin_notes" class="form-control" rows="4"
                                placeholder="Explain the diagnosis, action taken, replacement parts used, or schedule for the employee..."></textarea>
                            <div class="form-text">This response will be visible to the reporting employee on their profile.</div>
                        </div>
                    </div>
                </div>

                <div class="modal-footer bg-light border-0 py-3 px-4 d-flex justify-content-between">
                    <a href="#" id="qr_full_view_btn" class="btn btn-outline-secondary btn-sm rounded-pill px-3" style="min-height: 36px; display: inline-flex; align-items: center;">
                        <i class="fa-solid fa-up-right-from-square me-1"></i>Full Details Page
                    </a>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-sm btn-secondary rounded-pill px-3" data-bs-dismiss="modal" style="min-height: 36px;">Cancel</button>
                        <button type="submit" class="btn btn-sm btn-warning fw-bold px-4 text-dark shadow-xs rounded-pill" style="min-height: 36px;">
                            <i class="fa-solid fa-floppy-disk me-1"></i>Save &amp; Send Reply
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
