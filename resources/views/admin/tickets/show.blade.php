@extends('layouts.app')
@section('title', 'Admin - IT Ticket ' . $ticket->ticket_no)
@section('content')
<div class="container-fluid py-3">
    <!-- Header -->
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
        <div>
            <div class="d-flex align-items-center gap-2 mb-1">
                <span class="badge bg-dark fs-6">{{ $ticket->ticket_no }}</span>
                {!! $ticket->submission_type_badge !!}
                {!! $ticket->priority_badge !!}
                {!! $ticket->status_badge !!}
            </div>
            <h1 class="h3 mb-0 text-gray-800 font-weight-bold">{{ $ticket->subject }}</h1>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <form action="{{ route('admin.tickets.resend-sms', $ticket) }}" method="POST" onsubmit="return confirm('Send an immediate SMS alert for this ticket to GM and Global Admin?');">
                @csrf
                <button type="submit" class="btn btn-outline-danger shadow-sm">
                    <i class="fa-solid fa-tower-broadcast me-1"></i> Resend SMS to GM / Admin
                </button>
            </form>
            <a href="{{ route('admin.tickets.print', $ticket) }}" target="_blank" class="btn btn-outline-dark shadow-sm">
                <i class="fa-solid fa-print me-1"></i> Print Official Form
            </a>
            <a href="{{ route('admin.tickets.index') }}" class="btn btn-outline-secondary shadow-sm">
                <i class="fa-solid fa-arrow-left me-1"></i> Back to List
            </a>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show shadow-sm" role="alert">
            <i class="fa-solid fa-circle-check me-2"></i>{{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show shadow-sm" role="alert">
            <i class="fa-solid fa-triangle-exclamation me-2"></i>{{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <div class="row">
        <!-- Main Column: Report Content & Conversation -->
        <div class="col-lg-8">

            <!-- Submitter Information -->
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="fa-solid fa-user-tag me-2"></i>1. Submitter Information
                    </h6>
                    <small class="text-muted">Submitted: {{ $ticket->submitted_date ? $ticket->submitted_date->format('M d, Y') : $ticket->created_at->format('M d, Y') }}</small>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-3">
                            <span class="text-xs text-muted text-uppercase d-block font-weight-bold">Full Name</span>
                            <strong class="text-dark">{{ $ticket->submitter_name ?: ($ticket->user->name ?? 'Staff') }}</strong>
                        </div>
                        <div class="col-md-3">
                            <span class="text-xs text-muted text-uppercase d-block font-weight-bold">Employee ID</span>
                            <span>{{ $ticket->employee_code ?: ($ticket->user->employee->employee_code ?? 'N/A') }}</span>
                        </div>
                        <div class="col-md-3">
                            <span class="text-xs text-muted text-uppercase d-block font-weight-bold">Department</span>
                            <span class="badge bg-secondary">{{ $ticket->department ?: ($ticket->user->employee->department ?? 'General') }}</span>
                        </div>
                        <div class="col-md-3">
                            <span class="text-xs text-muted text-uppercase d-block font-weight-bold">Contact Phone / Email</span>
                            <span class="text-dark">{{ $ticket->contact_phone ?: ($ticket->user->employee->phone ?? $ticket->user->email) }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 2. IT Material Requisition & Warehouse Dispatch (If Requested) -->
            @if($ticket->has_material_request)
            <div class="card shadow-sm border-0 mb-4 border-top border-4 border-warning">
                <div class="card-header bg-white py-3 border-bottom d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <div>
                        <h6 class="m-0 font-weight-bold text-dark">
                            <i class="fa-solid fa-boxes-stacked me-2 text-warning"></i>2. IT Material Requisition & Store Dispatch
                        </h6>
                        <small class="text-muted">General Manager Approval → Store Manager Dispatch → Head Office Secretary Receipt</small>
                    </div>
                    <div class="d-flex flex-wrap gap-1">
                        {!! $ticket->mr_gm_status_badge !!}
                        {!! $ticket->mr_store_status_badge !!}
                        @if($ticket->mr_secretary_received)
                            <span class="badge bg-success text-white"><i class="fa-solid fa-building-circle-check me-1"></i>Received @ HO</span>
                        @else
                            <span class="badge bg-secondary text-white"><i class="fa-solid fa-clock me-1"></i>Pending HO Receipt</span>
                        @endif
                    </div>
                </div>
                <div class="card-body">
                    <!-- Request Overview Details -->
                    <div class="row g-3 p-3 bg-light rounded mb-3">
                        <div class="col-md-5">
                            <span class="text-xs text-muted text-uppercase d-block font-weight-bold">Business Justification / Urgency</span>
                            <div class="text-dark font-weight-bold mt-1">{{ $ticket->mr_justification ?: 'No justification specified' }}</div>
                        </div>
                        <div class="col-md-4">
                            <span class="text-xs text-muted text-uppercase d-block font-weight-bold">Target Location / Project</span>
                            <div class="text-dark mt-1"><i class="fa-solid fa-location-dot text-danger me-1"></i>{{ $ticket->mr_project_location ?: 'Head Office / IT Department' }}</div>
                        </div>
                        <div class="col-md-3">
                            <span class="text-xs text-muted text-uppercase d-block font-weight-bold">Urgency Level</span>
                            <div class="mt-1">
                                <span class="badge {{ $ticket->mr_urgency === 'critical' ? 'bg-danger' : ($ticket->mr_urgency === 'high' ? 'bg-warning text-dark' : 'bg-primary') }} text-uppercase">
                                    {{ $ticket->mr_urgency ?: 'Medium' }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Items List Table -->
                    <h6 class="font-weight-bold text-dark small text-uppercase mb-2">Requested Material Items ({{ $ticket->materialRequestItems->count() }})</h6>
                    <div class="table-responsive mb-4">
                        <table class="table table-sm table-bordered align-middle mb-0">
                            <thead class="table-light text-muted small text-uppercase">
                                <tr>
                                    <th style="width: 40px;" class="text-center">#</th>
                                    <th>Item Description</th>
                                    <th class="text-center" style="width: 80px;">Req. Qty</th>
                                    <th style="width: 70px;">Unit</th>
                                    <th>Purpose</th>
                                    <th style="width: 90px;" class="text-center">Urgency</th>
                                    <th class="text-center" style="width: 100px;">Store Qty</th>
                                    <th class="text-center" style="width: 120px;">Store Status</th>
                                    <th>Store Notes</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($ticket->materialRequestItems as $idx => $mItem)
                                    <tr>
                                        <td class="text-center text-muted small">{{ $idx + 1 }}</td>
                                        <td><strong class="text-dark">{{ $mItem->item_name }}</strong></td>
                                        <td class="text-center font-weight-bold text-primary">{{ $mItem->quantity }}</td>
                                        <td class="small">{{ $mItem->unit ?: 'pcs' }}</td>
                                        <td class="small text-muted">{{ $mItem->purpose ?: '—' }}</td>
                                        <td class="text-center small">
                                            <span class="badge {{ $mItem->urgency_level === 'critical' ? 'bg-danger' : ($mItem->urgency_level === 'high' ? 'bg-warning text-dark' : 'bg-secondary') }}">
                                                {{ ucfirst($mItem->urgency_level ?: 'medium') }}
                                            </span>
                                        </td>
                                        <td class="text-center font-weight-bold text-success">{{ $mItem->store_dispatch_qty ?: '—' }}</td>
                                        <td class="text-center small">
                                            @if($mItem->store_dispatch_status === 'available')
                                                <span class="badge bg-success">Available</span>
                                            @elseif($mItem->store_dispatch_status === 'partial')
                                                <span class="badge bg-warning text-dark">Partial</span>
                                            @elseif($mItem->store_dispatch_status === 'unavailable')
                                                <span class="badge bg-danger">Unavailable</span>
                                            @else
                                                <span class="badge bg-light text-muted border">Pending</span>
                                            @endif
                                        </td>
                                        <td class="small text-muted">{{ $mItem->store_notes ?: '—' }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="9" class="text-center text-muted py-3">No specific line items recorded.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    @php
                        $user = auth()->user();
                        $isGM = $user && ($user->hasAnyRole(['General Manager', 'general_manager', 'gm']) || str_contains(strtolower($user->roles->pluck('name')->implode(',')), 'gm'));
                        $isAdmin = $user && $user->hasAnyRole(['global_admin', 'admin']);
                        $isStoreManager = $user && $user->hasAnyRole(['store_manager', 'Store Manager']);
                        $isSecretary = $user && $user->hasAnyRole(['secretary', 'Secretary']);
                    @endphp

                    <!-- 3-Stage Progress Timeline Cards -->
                    <div class="row g-3 mb-3">
                        <!-- Stage 1: GM Review -->
                        <div class="col-md-4">
                            <div class="card h-100 border {{ $ticket->mr_gm_status === 'approved_to_store' ? 'border-success bg-light' : ($ticket->mr_gm_status === 'rejected_by_gm' ? 'border-danger bg-light' : 'border-warning') }}">
                                <div class="card-body p-3">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <h6 class="font-weight-bold m-0 small text-uppercase">1. GM Approval</h6>
                                        {!! $ticket->mr_gm_status_badge !!}
                                    </div>
                                    @if($ticket->mr_gm_decided_at)
                                        <div class="small text-muted mb-1">
                                            By: <strong class="text-dark">{{ $ticket->mrGmDecidedBy->name ?? 'General Manager' }}</strong>
                                        </div>
                                        <div class="small text-muted mb-2">
                                            Date: {{ $ticket->mr_gm_decided_at->format('M d, Y h:i A') }}
                                        </div>
                                        @if($ticket->mr_gm_notes)
                                            <div class="p-2 rounded bg-white border small text-dark mb-2">
                                                <strong>Note:</strong> {{ $ticket->mr_gm_notes }}
                                            </div>
                                        @endif
                                    @else
                                        <p class="small text-muted mb-2">Pending General Manager review & decision.</p>
                                    @endif

                                    <!-- GM Actions (Visible to GM & Admins if pending) -->
                                    @if(($isGM || $isAdmin) && ($ticket->mr_gm_status === 'pending_gm' || empty($ticket->mr_gm_status)))
                                        <div class="mt-2 pt-2 border-top">
                                            <button type="button" class="btn btn-sm btn-success w-100 mb-2 shadow-sm" data-bs-toggle="collapse" data-bs-target="#gmApproveCollapse">
                                                <i class="fa-solid fa-check-circle me-1"></i> Approve & Send to Store
                                            </button>
                                            <div class="collapse mb-2" id="gmApproveCollapse">
                                                <form action="{{ route('admin.tickets.gm-approve', $ticket) }}" method="POST" class="p-2 border rounded bg-white">
                                                    @csrf
                                                    <label class="form-label small font-weight-bold">Approval Comments (Optional)</label>
                                                    <textarea name="mr_gm_notes" rows="2" class="form-control form-control-sm mb-2" placeholder="e.g. Approved. Please issue from central warehouse."></textarea>
                                                    <button type="submit" class="btn btn-sm btn-success w-100">Confirm Approval & Send SMS to Store</button>
                                                </form>
                                            </div>

                                            <button type="button" class="btn btn-sm btn-outline-danger w-100" data-bs-toggle="collapse" data-bs-target="#gmRejectCollapse">
                                                <i class="fa-solid fa-ban me-1"></i> Reject Request
                                            </button>
                                            <div class="collapse mt-2" id="gmRejectCollapse">
                                                <form action="{{ route('admin.tickets.gm-reject', $ticket) }}" method="POST" class="p-2 border rounded bg-white">
                                                    @csrf
                                                    <label class="form-label small font-weight-bold text-danger">Rejection Reason *</label>
                                                    <textarea name="mr_gm_notes" rows="2" class="form-control form-control-sm mb-2" placeholder="State reason for rejecting..." required></textarea>
                                                    <button type="submit" class="btn btn-sm btn-danger w-100">Confirm Rejection</button>
                                                </form>
                                            </div>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </div>

                        <!-- Stage 2: Store Manager Dispatch -->
                        <div class="col-md-4">
                            <div class="card h-100 border {{ in_array($ticket->mr_store_status, ['dispatched', 'partially_dispatched']) ? 'border-success bg-light' : ($ticket->mr_store_status === 'unavailable' ? 'border-danger bg-light' : 'border-secondary') }}">
                                <div class="card-body p-3">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <h6 class="font-weight-bold m-0 small text-uppercase">2. Store Dispatch</h6>
                                        {!! $ticket->mr_store_status_badge !!}
                                    </div>
                                    @if($ticket->mr_store_dispatched_at)
                                        <div class="small text-muted mb-1">
                                            By: <strong class="text-dark">{{ $ticket->mrStoreManagedBy->name ?? 'Store Manager' }}</strong>
                                        </div>
                                        <div class="small text-muted mb-2">
                                            Date: {{ $ticket->mr_store_dispatched_at->format('M d, Y h:i A') }}
                                        </div>
                                        @if($ticket->mr_store_notes)
                                            <div class="p-2 rounded bg-white border small text-dark mb-2">
                                                <strong>Note:</strong> {{ $ticket->mr_store_notes }}
                                            </div>
                                        @endif
                                    @else
                                        <p class="small text-muted mb-2">
                                            {{ $ticket->mr_gm_status === 'approved_to_store' ? 'Awaiting Store Manager processing & dispatch.' : 'Pending GM approval prior to store dispatch.' }}
                                        </p>
                                    @endif

                                    <!-- Store Manager Action Button -->
                                    @if(($isStoreManager || $isAdmin) && $ticket->mr_gm_status === 'approved_to_store')
                                        <div class="mt-2 pt-2 border-top">
                                            <button type="button" class="btn btn-sm btn-primary w-100 shadow-sm" data-bs-toggle="modal" data-bs-target="#storeDispatchModal">
                                                <i class="fa-solid fa-truck-ramp-box me-1"></i> Update Store Dispatch
                                            </button>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </div>

                        <!-- Stage 3: Head Office Secretary Receipt -->
                        <div class="col-md-4">
                            <div class="card h-100 border {{ $ticket->mr_secretary_received ? 'border-success bg-light' : 'border-secondary' }}">
                                <div class="card-body p-3">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <h6 class="font-weight-bold m-0 small text-uppercase">3. HO Receipt</h6>
                                        @if($ticket->mr_secretary_received)
                                            <span class="badge bg-success text-white"><i class="fa-solid fa-circle-check me-1"></i>Received</span>
                                        @else
                                            <span class="badge bg-secondary text-white">Pending</span>
                                        @endif
                                    </div>
                                    @if($ticket->mr_secretary_received)
                                        <div class="small text-muted mb-1">
                                            By: <strong class="text-dark">{{ $ticket->mrSecretaryReceivedBy->name ?? 'Head Office Secretary' }}</strong>
                                        </div>
                                        <div class="small text-muted mb-2">
                                            Date: {{ $ticket->mr_secretary_received_at ? $ticket->mr_secretary_received_at->format('M d, Y h:i A') : 'Recorded' }}
                                        </div>
                                        @if($ticket->mr_secretary_notes)
                                            <div class="p-2 rounded bg-white border small text-dark mb-2">
                                                <strong>Note:</strong> {{ $ticket->mr_secretary_notes }}
                                            </div>
                                        @endif
                                    @else
                                        <p class="small text-muted mb-2">Awaiting physical delivery and secretary confirmation at Head Office.</p>
                                    @endif

                                    <!-- Secretary Action (Visible to Secretary & Admins once dispatched) -->
                                    @if(($isSecretary || $isAdmin) && in_array($ticket->mr_store_status, ['dispatched', 'partially_dispatched']) && !$ticket->mr_secretary_received)
                                        <div class="mt-2 pt-2 border-top">
                                            <button type="button" class="btn btn-sm btn-info text-white w-100 shadow-sm" data-bs-toggle="collapse" data-bs-target="#secretaryConfirmCollapse">
                                                <i class="fa-solid fa-stamp me-1"></i> Confirm Receipt at HO
                                            </button>
                                            <div class="collapse mt-2" id="secretaryConfirmCollapse">
                                                <form action="{{ route('admin.tickets.secretary-confirm', $ticket) }}" method="POST" class="p-2 border rounded bg-white">
                                                    @csrf
                                                    <label class="form-label small font-weight-bold">Receipt Notes / Condition</label>
                                                    <textarea name="mr_secretary_notes" rows="2" class="form-control form-control-sm mb-2" placeholder="e.g. Received in good order and handed over to IT technician."></textarea>
                                                    <button type="submit" class="btn btn-sm btn-success w-100">Confirm Items Received</button>
                                                </form>
                                            </div>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>

                </div>
            </div>

            <!-- Modal for Store Manager Dispatch Processing -->
            @if(($isStoreManager || $isAdmin) && $ticket->mr_gm_status === 'approved_to_store')
            <div class="modal fade" id="storeDispatchModal" tabindex="-1" aria-labelledby="storeDispatchModalLabel" aria-hidden="true">
                <div class="modal-dialog modal-lg">
                    <form action="{{ route('admin.tickets.store-dispatch', $ticket) }}" method="POST">
                        @csrf
                        <div class="modal-content">
                            <div class="modal-header bg-dark text-white">
                                <h5 class="modal-title" id="storeDispatchModalLabel">
                                    <i class="fa-solid fa-warehouse me-2 text-warning"></i>Store Manager - Process Material Dispatch
                                </h5>
                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <div class="alert alert-info py-2 small mb-3">
                                    <i class="fa-solid fa-info-circle me-1"></i> Check each item's warehouse availability and enter dispatched quantity before finalizing.
                                </div>

                                <div class="table-responsive mb-3">
                                    <table class="table table-sm table-bordered align-middle">
                                        <thead class="table-light text-muted small">
                                            <tr>
                                                <th>Item Name</th>
                                                <th class="text-center" style="width: 80px;">Req. Qty</th>
                                                <th style="width: 120px;">Dispatch Qty</th>
                                                <th style="width: 150px;">Item Status</th>
                                                <th>Item Notes / Remarks</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($ticket->materialRequestItems as $idx => $item)
                                                <tr>
                                                    <td>
                                                        <strong class="text-dark">{{ $item->item_name }}</strong>
                                                        <input type="hidden" name="items[{{ $idx }}][id]" value="{{ $item->id }}">
                                                    </td>
                                                    <td class="text-center font-weight-bold">{{ $item->quantity }} {{ $item->unit }}</td>
                                                    <td>
                                                        <input type="text" name="items[{{ $idx }}][store_dispatch_qty]" class="form-control form-control-sm" value="{{ old("items.$idx.store_dispatch_qty", $item->store_dispatch_qty ?: $item->quantity) }}">
                                                    </td>
                                                    <td>
                                                        <select name="items[{{ $idx }}][store_dispatch_status]" class="form-select form-select-sm">
                                                            <option value="available" {{ ($item->store_dispatch_status ?: 'available') === 'available' ? 'selected' : '' }}>Available</option>
                                                            <option value="partial" {{ $item->store_dispatch_status === 'partial' ? 'selected' : '' }}>Partial</option>
                                                            <option value="unavailable" {{ $item->store_dispatch_status === 'unavailable' ? 'selected' : '' }}>Unavailable</option>
                                                        </select>
                                                    </td>
                                                    <td>
                                                        <input type="text" name="items[{{ $idx }}][store_notes]" class="form-control form-control-sm" placeholder="Bin / batch / status..." value="{{ old("items.$idx.store_notes", $item->store_notes) }}">
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>

                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label font-weight-bold small text-muted text-uppercase">Overall Dispatch Status <span class="text-danger">*</span></label>
                                        <select name="mr_store_status" class="form-select" required>
                                            <option value="dispatched" {{ ($ticket->mr_store_status ?: 'dispatched') === 'dispatched' ? 'selected' : '' }}>Dispatched (Ready / Shipped to HO)</option>
                                            <option value="partially_dispatched" {{ $ticket->mr_store_status === 'partially_dispatched' ? 'selected' : '' }}>Partially Dispatched</option>
                                            <option value="unavailable" {{ $ticket->mr_store_status === 'unavailable' ? 'selected' : '' }}>Unavailable (Requires Purchasing PR)</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label font-weight-bold small text-muted text-uppercase">Overall Store Notes</label>
                                        <textarea name="mr_store_notes" rows="2" class="form-control form-control-sm" placeholder="Waybill number, driver name, or purchase recommendation...">{{ old('mr_store_notes', $ticket->mr_store_notes) }}</textarea>
                                    </div>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                <button type="submit" class="btn btn-primary font-weight-bold">
                                    <i class="fa-solid fa-floppy-disk me-1"></i> Save & Confirm Dispatch
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
            @endif
            @endif

            <!-- Problem Report Details (If Problem or Both) -->
            @if(in_array($ticket->submission_type, ['problem', 'both']) || !empty($ticket->description))
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                    <h6 class="m-0 font-weight-bold text-danger">
                        <i class="fa-solid fa-triangle-exclamation me-2"></i>3. Problem Report Details
                    </h6>
                    <span class="badge bg-light text-dark border">{{ $ticket->category }}</span>
                </div>
                <div class="card-body">
                    <div class="row g-3 mb-3 pb-3 border-bottom">
                        <div class="col-md-4">
                            <span class="text-xs text-muted text-uppercase d-block font-weight-bold">Affected System / Device</span>
                            <strong>{{ $ticket->affected_system ?: 'General System' }}</strong>
                        </div>
                        <div class="col-md-4">
                            <span class="text-xs text-muted text-uppercase d-block font-weight-bold">Location</span>
                            <span>{{ $ticket->location ?: 'Not specified' }}</span>
                        </div>
                        <div class="col-md-4">
                            <span class="text-xs text-muted text-uppercase d-block font-weight-bold">Started At & Frequency</span>
                            <span>{{ $ticket->incident_started_at ?: $ticket->created_at->format('M d, Y') }} ({{ $ticket->frequency ?: 'Once' }})</span>
                        </div>
                    </div>

                    <div class="mb-3">
                        <span class="text-xs text-muted text-uppercase d-block font-weight-bold mb-1">Description of the Problem</span>
                        <div class="p-3 bg-light rounded text-dark" style="white-space: pre-wrap;">{{ $ticket->description }}</div>
                    </div>

                    @if($ticket->steps_to_reproduce)
                    <div class="mb-3">
                        <span class="text-xs text-muted text-uppercase d-block font-weight-bold mb-1">Steps to Reproduce</span>
                        <div class="p-3 bg-light rounded text-dark" style="white-space: pre-wrap;">{{ $ticket->steps_to_reproduce }}</div>
                    </div>
                    @endif

                    @if($ticket->error_message)
                    <div class="mb-3">
                        <span class="text-xs text-muted text-uppercase d-block font-weight-bold mb-1">Error Message</span>
                        <pre class="p-3 bg-dark text-white rounded small" style="white-space: pre-wrap;">{{ $ticket->error_message }}</pre>
                    </div>
                    @endif

                    @if($ticket->already_tried)
                    <div class="mb-2">
                        <span class="text-xs text-muted text-uppercase d-block font-weight-bold mb-1">What Was Already Tried</span>
                        <div class="p-2 border rounded text-muted small">{{ $ticket->already_tried }}</div>
                    </div>
                    @endif
                </div>
            </div>
            @endif

            <!-- Suggestion / Improvement Idea (If Suggestion or Both) -->
            @if(in_array($ticket->submission_type, ['suggestion', 'both']) || !empty($ticket->suggestion_title))
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-white py-3 border-bottom">
                    <h6 class="m-0 font-weight-bold text-dark">
                        <i class="fa-solid fa-lightbulb text-warning me-2"></i>4. Suggestion / Improvement Idea
                    </h6>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <span class="text-xs text-muted text-uppercase d-block font-weight-bold">Suggestion Title</span>
                        <h5 class="font-weight-bold text-dark mt-1">{{ $ticket->suggestion_title ?: $ticket->subject }}</h5>
                        <span class="badge bg-info text-dark">Area: {{ $ticket->suggestion_area ?: 'Tools & Software' }}</span>
                    </div>

                    @if($ticket->current_situation)
                    <div class="mb-3">
                        <span class="text-xs text-muted text-uppercase d-block font-weight-bold mb-1">Current Situation</span>
                        <div class="p-3 bg-light rounded text-dark" style="white-space: pre-wrap;">{{ $ticket->current_situation }}</div>
                    </div>
                    @endif

                    @if($ticket->suggested_change)
                    <div class="mb-3">
                        <span class="text-xs text-muted text-uppercase d-block font-weight-bold mb-1">Proposed Suggestion / Change</span>
                        <div class="p-3 bg-light rounded text-dark" style="white-space: pre-wrap;">{{ $ticket->suggested_change }}</div>
                    </div>
                    @endif

                    @php
                        $bens = is_array($ticket->expected_benefits) ? $ticket->expected_benefits : [];
                    @endphp
                    @if(!empty($bens) || $ticket->expected_benefits_other)
                    <div>
                        <span class="text-xs text-muted text-uppercase d-block font-weight-bold mb-2">Expected Benefits</span>
                        <div class="d-flex flex-wrap gap-2">
                            @foreach($bens as $b)
                                <span class="badge bg-success-subtle text-success border border-success-subtle px-3 py-2">✓ {{ $b }}</span>
                            @endforeach
                            @if($ticket->expected_benefits_other)
                                <span class="badge bg-info-subtle text-info border border-info-subtle px-3 py-2">Other: {{ $ticket->expected_benefits_other }}</span>
                            @endif
                        </div>
                    </div>
                    @endif
                </div>
            </div>
            @endif

            <!-- Attachments -->
            @if($ticket->attachment_path || $ticket->attachments_notes)
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-white py-3 border-bottom">
                    <h6 class="m-0 font-weight-bold text-dark">
                        <i class="fa-solid fa-paperclip me-2 text-secondary"></i>5. Attachments
                    </h6>
                </div>
                <div class="card-body">
                    @if($ticket->attachment_path)
                        <div class="d-flex align-items-center justify-content-between p-3 border rounded bg-light mb-2">
                            <div class="d-flex align-items-center">
                                <i class="fa-solid fa-file text-primary fs-3 me-3"></i>
                                <div>
                                    <div class="font-weight-bold text-dark">{{ $ticket->attachment_name ?: basename($ticket->attachment_path) }}</div>
                                    <small class="text-muted">Uploaded Attachment</small>
                                </div>
                            </div>
                            <a href="{{ asset('storage/' . $ticket->attachment_path) }}" target="_blank" class="btn btn-sm btn-primary">
                                <i class="fa-solid fa-download me-1"></i> View / Download
                            </a>
                        </div>
                    @endif
                    @if($ticket->attachments_notes)
                        <div class="text-muted small mt-2">
                            <strong>Notes:</strong> {{ $ticket->attachments_notes }}
                        </div>
                    @endif
                </div>
            </div>
            @endif

            <!-- Conversation & Admin Replies -->
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-white py-3 border-bottom">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="fa-solid fa-comments me-2"></i>Ticket Conversation History ({{ $ticket->replies->count() }})
                    </h6>
                </div>
                <div class="card-body">
                    @if($ticket->replies->count() == 0)
                        <p class="text-muted text-center py-3 mb-0 small">No messages in discussion yet.</p>
                    @endif

                    @foreach($ticket->replies as $reply)
                        <div class="d-flex mb-3 pb-3 border-bottom">
                            <div class="flex-shrink-0">
                                <div class="{{ $reply->is_admin_reply ? 'bg-dark' : 'bg-primary' }} text-white rounded-circle d-flex align-items-center justify-content-center shadow-sm" style="width: 40px; height: 40px;">
                                    <i class="fa-solid {{ $reply->is_admin_reply ? 'fa-user-shield' : 'fa-user' }}"></i>
                                </div>
                            </div>
                            <div class="flex-grow-1 ms-3">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <strong class="text-dark">
                                        {{ $reply->user->name ?? 'User' }}
                                        @if($reply->is_admin_reply)
                                            <span class="badge bg-secondary ms-1">IT Admin</span>
                                        @endif
                                    </strong>
                                    <small class="text-muted">{{ $reply->created_at->format('M d, Y h:i A') }}</small>
                                </div>
                                <div class="p-3 rounded text-dark {{ $reply->is_admin_reply ? 'bg-white border' : 'bg-light' }}" style="white-space: pre-wrap;">{{ $reply->message }}</div>
                            </div>
                        </div>
                    @endforeach

                    <!-- Post Admin Reply -->
                    <form action="{{ route('admin.tickets.reply', $ticket) }}" method="POST" class="mt-4">
                        @csrf
                        <label class="form-label font-weight-bold text-dark small">Send Official Admin Reply to Submitter</label>
                        <textarea name="message" rows="3" class="form-control mb-2" placeholder="Write message to employee..." required></textarea>
                        <div class="d-flex justify-content-end">
                            <button type="submit" class="btn btn-primary">
                                <i class="fa-solid fa-paper-plane me-1"></i> Send Reply
                            </button>
                        </div>
                    </form>
                </div>
            </div>

        </div>

        <!-- Right Column: Section 6 "For IT Department Use Only" -->
        <div class="col-lg-4">

            <!-- 6. For IT Department Use Only (Editable Form) -->
            <div class="card shadow-sm border-0 mb-4 border-top border-4 border-primary">
                <div class="card-header bg-white py-3 border-bottom">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="fa-solid fa-screwdriver-wrench me-2"></i>6. For IT Department Use Only
                    </h6>
                    <small class="text-muted">Official processing and resolution panel</small>
                </div>
                <div class="card-body">
                    <form action="{{ route('admin.tickets.it-section', $ticket) }}" method="POST">
                        @csrf

                        <!-- Status -->
                        <div class="mb-3">
                            <label class="form-label font-weight-bold small text-muted text-uppercase">Status <span class="text-danger">*</span></label>
                            <select name="status" class="form-select">
                                <option value="new" {{ $ticket->status == 'new' ? 'selected' : '' }}>New</option>
                                <option value="open" {{ $ticket->status == 'open' ? 'selected' : '' }}>Open</option>
                                <option value="in_progress" {{ $ticket->status == 'in_progress' ? 'selected' : '' }}>In Progress</option>
                                <option value="waiting_for_user" {{ $ticket->status == 'waiting_for_user' ? 'selected' : '' }}>Waiting for User</option>
                                <option value="resolved" {{ $ticket->status == 'resolved' ? 'selected' : '' }}>Resolved</option>
                                <option value="planned" {{ $ticket->status == 'planned' ? 'selected' : '' }}>Planned (for suggestions)</option>
                                <option value="rejected" {{ $ticket->status == 'rejected' ? 'selected' : '' }}>Rejected</option>
                                <option value="closed" {{ $ticket->status == 'closed' ? 'selected' : '' }}>Closed</option>
                            </select>
                        </div>

                        <!-- Priority -->
                        <div class="mb-3">
                            <label class="form-label font-weight-bold small text-muted text-uppercase">Priority <span class="text-danger">*</span></label>
                            <select name="priority" class="form-select">
                                <option value="critical" {{ $ticket->priority == 'critical' ? 'selected' : '' }}>Critical (1h / 4h)</option>
                                <option value="urgent" {{ $ticket->priority == 'urgent' ? 'selected' : '' }}>Urgent</option>
                                <option value="high" {{ $ticket->priority == 'high' ? 'selected' : '' }}>High (4h / 1 Day)</option>
                                <option value="medium" {{ $ticket->priority == 'medium' ? 'selected' : '' }}>Medium (1 Day / 3 Days)</option>
                                <option value="low" {{ $ticket->priority == 'low' ? 'selected' : '' }}>Low (2 Days / 5 Days)</option>
                            </select>
                        </div>

                        <!-- Received By & Date Received -->
                        <div class="row g-2 mb-3">
                            <div class="col-6">
                                <label class="form-label font-weight-bold small text-muted text-uppercase">Received By</label>
                                <select name="received_by_id" class="form-select form-select-sm">
                                    <option value="">-- Select --</option>
                                    @foreach($admins as $admin)
                                        <option value="{{ $admin->id }}" {{ ($ticket->received_by_id ?: auth()->id()) == $admin->id ? 'selected' : '' }}>
                                            {{ $admin->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-6">
                                <label class="form-label font-weight-bold small text-muted text-uppercase">Date Received</label>
                                <input type="date" name="date_received" class="form-control form-control-sm" value="{{ $ticket->date_received ? $ticket->date_received->format('Y-m-d') : now()->format('Y-m-d') }}">
                            </div>
                        </div>

                        <!-- Assigned To -->
                        <div class="mb-3">
                            <label class="form-label font-weight-bold small text-muted text-uppercase">Assigned To Specialist</label>
                            <select name="assigned_to" class="form-select">
                                <option value="">-- Unassigned --</option>
                                @foreach($admins as $admin)
                                    <option value="{{ $admin->id }}" {{ $ticket->assigned_to == $admin->id ? 'selected' : '' }}>
                                        {{ $admin->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <!-- Target Resolution Date -->
                        <div class="mb-3">
                            <label class="form-label font-weight-bold small text-muted text-uppercase">Target Resolution Date</label>
                            <input type="date" name="target_resolution_date" class="form-control" value="{{ $ticket->target_resolution_date ? $ticket->target_resolution_date->format('Y-m-d') : '' }}">
                        </div>

                        <!-- Actions Taken / Root Cause -->
                        <div class="mb-3">
                            <label class="form-label font-weight-bold small text-muted text-uppercase">Actions Taken / Root Cause</label>
                            <textarea name="actions_taken" rows="3" class="form-control" placeholder="Describe root cause and diagnostic actions taken...">{{ old('actions_taken', $ticket->actions_taken ?: $ticket->root_cause) }}</textarea>
                        </div>

                        <!-- Resolution or Decision -->
                        <div class="mb-3">
                            <label class="form-label font-weight-bold small text-muted text-uppercase">Resolution or Decision</label>
                            <textarea name="resolution_decision" rows="3" class="form-control" placeholder="Approved, planned, declined, or technical resolution details...">{{ old('resolution_decision', $ticket->resolution_decision) }}</textarea>
                        </div>

                        <!-- Date Closed & User Confirmed -->
                        <div class="row g-2 mb-3">
                            <div class="col-6">
                                <label class="form-label font-weight-bold small text-muted text-uppercase">Date Closed</label>
                                <input type="date" name="date_closed" class="form-control form-control-sm" value="{{ $ticket->date_closed ? $ticket->date_closed->format('Y-m-d') : '' }}">
                            </div>
                            <div class="col-6">
                                <label class="form-label font-weight-bold small text-muted text-uppercase">User Confirmed</label>
                                <select name="user_confirmed_resolved" class="form-select form-select-sm">
                                    <option value="pending" {{ $ticket->user_confirmed_resolved == 'pending' ? 'selected' : '' }}>Pending</option>
                                    <option value="yes" {{ $ticket->user_confirmed_resolved == 'yes' ? 'selected' : '' }}>Yes</option>
                                    <option value="no" {{ $ticket->user_confirmed_resolved == 'no' ? 'selected' : '' }}>No</option>
                                </select>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary w-100 shadow-sm font-weight-bold">
                            <i class="fa-solid fa-floppy-disk me-1"></i> Save IT Department Records
                        </button>
                    </form>
                </div>
            </div>

            <!-- SMS Escalation & Audit Log Card -->
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                    <h6 class="m-0 font-weight-bold text-dark">
                        <i class="fa-solid fa-tower-broadcast text-danger me-2"></i>GM / Admin SMS Escalation
                    </h6>
                    @if($ticket->sms_alert_sent)
                        <span class="badge bg-success">Alert Sent</span>
                    @else
                        <span class="badge bg-secondary">Pending</span>
                    @endif
                </div>
                <div class="card-body">
                    <p class="text-muted small mb-2">
                        Automatic SMS alerts are triggered to the General Manager (GM) and Global Admin whenever critical issues or problem reports occur.
                    </p>
                    @if($ticket->sms_alert_log)
                        <div class="p-2 bg-light border rounded small font-monospace text-muted mb-3" style="max-height: 140px; overflow-y: auto; white-space: pre-wrap;">{{ $ticket->sms_alert_log }}</div>
                    @endif

                    <form action="{{ route('admin.tickets.resend-sms', $ticket) }}" method="POST">
                        @csrf
                        <button type="submit" class="btn btn-sm btn-outline-danger w-100">
                            <i class="fa-solid fa-paper-plane me-1"></i> Trigger Immediate SMS Alert Now
                        </button>
                    </form>
                </div>
            </div>

            <!-- SLA Reference Guide -->
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-white py-3 border-bottom">
                    <h6 class="m-0 font-weight-bold text-dark">
                        <i class="fa-solid fa-stopwatch me-2 text-warning"></i>Response Time Guide (SLA)
                    </h6>
                </div>
                <div class="card-body p-0">
                    <table class="table table-sm table-bordered mb-0 small text-center align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Priority</th>
                                <th>First Response</th>
                                <th>Target Fix</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr class="{{ in_array($ticket->priority, ['critical', 'urgent']) ? 'table-danger font-weight-bold' : '' }}">
                                <td class="text-danger">Critical</td>
                                <td>1 hour</td>
                                <td>4 hours</td>
                            </tr>
                            <tr class="{{ $ticket->priority == 'high' ? 'table-warning font-weight-bold' : '' }}">
                                <td class="text-warning">High</td>
                                <td>4 hours</td>
                                <td>1 bus. day</td>
                            </tr>
                            <tr class="{{ $ticket->priority == 'medium' ? 'table-primary font-weight-bold' : '' }}">
                                <td class="text-primary">Medium</td>
                                <td>1 bus. day</td>
                                <td>3 bus. days</td>
                            </tr>
                            <tr class="{{ $ticket->priority == 'low' ? 'table-success font-weight-bold' : '' }}">
                                <td class="text-success">Low</td>
                                <td>2 bus. days</td>
                                <td>5 bus. days</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </div>
</div>
@endsection
