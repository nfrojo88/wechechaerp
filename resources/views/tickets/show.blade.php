@extends('layouts.app')
@section('title', 'Ticket Details - ' . $ticket->ticket_no)
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
        <div class="d-flex gap-2">
            <a href="{{ route('tickets.print', $ticket) }}" target="_blank" class="btn btn-outline-dark">
                <i class="fa-solid fa-print me-1"></i> Print Official Form
            </a>
            <a href="{{ route('tickets.index') }}" class="btn btn-outline-secondary">
                <i class="fa-solid fa-arrow-left me-1"></i> Back to My Reports
            </a>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show shadow-sm" role="alert">
            <i class="fa-solid fa-circle-check me-2"></i>{{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <div class="row">
        <!-- Main Content Column -->
        <div class="col-lg-8">

            <!-- Section 1 & 2: Submitter & Type Overview -->
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="fa-solid fa-file-lines me-2"></i>Report Overview
                    </h6>
                    <small class="text-muted">Submitted {{ $ticket->created_at->format('M d, Y h:i A') }}</small>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-sm-6 col-md-3">
                            <span class="text-xs text-muted text-uppercase d-block font-weight-bold">Submitter</span>
                            <span class="font-weight-bold text-dark">{{ $ticket->submitter_name ?: ($ticket->user->name ?? 'Staff') }}</span>
                        </div>
                        <div class="col-sm-6 col-md-3">
                            <span class="text-xs text-muted text-uppercase d-block font-weight-bold">Employee ID</span>
                            <span>{{ $ticket->employee_code ?: ($ticket->user->employee->employee_code ?? '—') }}</span>
                        </div>
                        <div class="col-sm-6 col-md-3">
                            <span class="text-xs text-muted text-uppercase d-block font-weight-bold">Department</span>
                            <span>{{ $ticket->department ?: ($ticket->user->employee->department ?? 'General') }}</span>
                        </div>
                        <div class="col-sm-6 col-md-3">
                            <span class="text-xs text-muted text-uppercase d-block font-weight-bold">Contact</span>
                            <span>{{ $ticket->contact_phone ?: '—' }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Section 2: IT Material Request & Dispatch Status (If requested) -->
            @if($ticket->has_material_request)
            <div class="card shadow-sm border-0 mb-4 border-top border-4 border-warning">
                <div class="card-header bg-white py-3 border-bottom d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <div>
                        <h6 class="m-0 font-weight-bold text-dark">
                            <i class="fa-solid fa-boxes-stacked me-2 text-warning"></i>IT Material Requisition
                        </h6>
                        <small class="text-muted">Workflow: GM Approval → Store Dispatch → Head Office Secretary Receipt</small>
                    </div>
                    <div class="d-flex flex-wrap gap-1">
                        {!! $ticket->mr_gm_status_badge !!}
                        {!! $ticket->mr_store_status_badge !!}
                        @if($ticket->mr_secretary_received)
                            <span class="badge bg-success text-white"><i class="fa-solid fa-building-circle-check me-1"></i>Received at Head Office</span>
                        @else
                            <span class="badge bg-secondary text-white"><i class="fa-solid fa-clock me-1"></i>Pending HO Delivery</span>
                        @endif
                    </div>
                </div>
                <div class="card-body">
                    <!-- Request Overview Details -->
                    <div class="row g-3 p-3 bg-light rounded mb-3">
                        <div class="col-md-5">
                            <span class="text-xs text-muted text-uppercase d-block font-weight-bold">Justification</span>
                            <div class="text-dark font-weight-bold mt-1">{{ $ticket->mr_justification ?: 'Standard IT requisition' }}</div>
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
                                    <th class="text-center" style="width: 80px;">Requested</th>
                                    <th style="width: 70px;">Unit</th>
                                    <th>Purpose</th>
                                    <th class="text-center" style="width: 100px;">Dispatched Qty</th>
                                    <th class="text-center" style="width: 120px;">Warehouse Status</th>
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
                                        <td class="text-center font-weight-bold text-success">{{ $mItem->store_dispatch_qty ?: '—' }}</td>
                                        <td class="text-center small">
                                            @if($mItem->store_dispatch_status === 'available')
                                                <span class="badge bg-success">Available</span>
                                            @elseif($mItem->store_dispatch_status === 'partial')
                                                <span class="badge bg-warning text-dark">Partial</span>
                                            @elseif($mItem->store_dispatch_status === 'unavailable')
                                                <span class="badge bg-danger">Unavailable</span>
                                            @else
                                                <span class="badge bg-light text-muted border">Pending Review</span>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="text-center text-muted py-3">No specific line items recorded.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <!-- 3-Stage Progress Timeline Cards -->
                    <div class="row g-3">
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
                                            <div class="p-2 rounded bg-white border small text-dark">
                                                <strong>Note:</strong> {{ $ticket->mr_gm_notes }}
                                            </div>
                                        @endif
                                    @else
                                        <p class="small text-muted mb-0">Under review by General Manager for warehouse release authorization.</p>
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
                                            <div class="p-2 rounded bg-white border small text-dark">
                                                <strong>Note:</strong> {{ $ticket->mr_store_notes }}
                                            </div>
                                        @endif
                                    @else
                                        <p class="small text-muted mb-0">
                                            {{ $ticket->mr_gm_status === 'approved_to_store' ? 'Store Manager is preparing goods for dispatch.' : 'Pending GM approval.' }}
                                        </p>
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
                                            <span class="badge bg-secondary text-white">Pending Delivery</span>
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
                                            <div class="p-2 rounded bg-white border small text-dark">
                                                <strong>Note:</strong> {{ $ticket->mr_secretary_notes }}
                                            </div>
                                        @endif
                                    @else
                                        <p class="small text-muted mb-0">Will be confirmed upon physical arrival and inspection at Head Office.</p>
                                    @endif

                                    @php
                                        $currUser = auth()->user();
                                        $isSecUser = $currUser && $currUser->hasAnyRole(['secretary', 'Secretary', 'admin', 'global_admin']);
                                    @endphp
                                    @if($isSecUser && in_array($ticket->mr_store_status, ['dispatched', 'partially_dispatched']) && !$ticket->mr_secretary_received)
                                        <div class="mt-2 pt-2 border-top">
                                            <button type="button" class="btn btn-sm btn-info text-white w-100 shadow-sm" data-bs-toggle="collapse" data-bs-target="#userSecretaryConfirmCollapse">
                                                <i class="fa-solid fa-stamp me-1"></i> Confirm Receipt
                                            </button>
                                            <div class="collapse mt-2" id="userSecretaryConfirmCollapse">
                                                <form action="{{ route('admin.tickets.secretary-confirm', $ticket) }}" method="POST" class="p-2 border rounded bg-white">
                                                    @csrf
                                                    <label class="form-label small font-weight-bold">Receipt Remarks</label>
                                                    <textarea name="mr_secretary_notes" rows="2" class="form-control form-control-sm mb-2" placeholder="Received at reception..."></textarea>
                                                    <button type="submit" class="btn btn-sm btn-success w-100">Confirm Receipt</button>
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
            @endif

            <!-- Section 3: Problem Report (If Problem or Both) -->
            @if(in_array($ticket->submission_type, ['problem', 'both']) || !empty($ticket->description))
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-white py-3 border-bottom">
                    <h6 class="m-0 font-weight-bold text-danger">
                        <i class="fa-solid fa-triangle-exclamation me-2"></i>Problem Report Details
                    </h6>
                </div>
                <div class="card-body">
                    <div class="row g-3 mb-3 pb-3 border-bottom">
                        <div class="col-md-4">
                            <span class="text-xs text-muted text-uppercase d-block font-weight-bold">Category</span>
                            <span class="badge bg-light text-dark border">{{ $ticket->category }}</span>
                        </div>
                        <div class="col-md-4">
                            <span class="text-xs text-muted text-uppercase d-block font-weight-bold">Affected System / Device</span>
                            <strong>{{ $ticket->affected_system ?: 'General System' }}</strong>
                        </div>
                        <div class="col-md-4">
                            <span class="text-xs text-muted text-uppercase d-block font-weight-bold">Location</span>
                            <span>{{ $ticket->location ?: 'Not specified' }}</span>
                        </div>
                        <div class="col-md-4">
                            <span class="text-xs text-muted text-uppercase d-block font-weight-bold">When it started</span>
                            <span>{{ $ticket->incident_started_at ?: $ticket->created_at->format('M d, Y') }}</span>
                        </div>
                        <div class="col-md-4">
                            <span class="text-xs text-muted text-uppercase d-block font-weight-bold">Frequency</span>
                            <span class="badge bg-secondary">{{ $ticket->frequency ?: 'Once' }}</span>
                        </div>
                        <div class="col-md-4">
                            <span class="text-xs text-muted text-uppercase d-block font-weight-bold">Impact / Urgency</span>
                            {!! $ticket->priority_badge !!}
                        </div>
                    </div>

                    <div class="mb-3">
                        <span class="text-xs text-muted text-uppercase d-block font-weight-bold mb-1">Problem Description</span>
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

            <!-- Section 4: Suggestion / Improvement Idea (If Suggestion or Both) -->
            @if(in_array($ticket->submission_type, ['suggestion', 'both']) || !empty($ticket->suggestion_title))
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-white py-3 border-bottom">
                    <h6 class="m-0 font-weight-bold text-warning text-dark">
                        <i class="fa-solid fa-lightbulb text-warning me-2"></i>Suggestion / Improvement Idea
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
                        <span class="text-xs text-muted text-uppercase d-block font-weight-bold mb-1">Current Situation / Limitation</span>
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

            <!-- Section 5: Attachments -->
            @if($ticket->attachment_path || $ticket->attachments_notes)
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-white py-3 border-bottom">
                    <h6 class="m-0 font-weight-bold text-dark">
                        <i class="fa-solid fa-paperclip me-2 text-secondary"></i>Attachments
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

            <!-- Conversation Replies -->
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-white py-3 border-bottom">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="fa-solid fa-comments me-2"></i>Activity & Discussion ({{ $ticket->replies->count() }})
                    </h6>
                </div>
                <div class="card-body">
                    @if($ticket->replies->count() == 0)
                        <p class="text-muted text-center py-3 mb-0 small">No messages exchanged yet.</p>
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
                                <div class="p-3 rounded text-dark {{ $reply->is_admin_reply ? 'bg-light border' : 'bg-light' }}" style="white-space: pre-wrap;">{{ $reply->message }}</div>
                            </div>
                        </div>
                    @endforeach

                    <!-- Post Reply Form -->
                    <form action="{{ route('tickets.reply', $ticket) }}" method="POST" class="mt-4">
                        @csrf
                        <label class="form-label font-weight-bold text-dark small">Send a Message or Update to IT Team</label>
                        <textarea name="message" rows="3" class="form-control mb-2" placeholder="Write your reply or additional details here..." required></textarea>
                        <div class="d-flex justify-content-end">
                            <button type="submit" class="btn btn-primary">
                                <i class="fa-solid fa-paper-plane me-1"></i> Send Message
                            </button>
                        </div>
                    </form>
                </div>
            </div>

        </div>

        <!-- Sidebar Column -->
        <div class="col-lg-4">

            <!-- Section 6: IT Department Resolution Status -->
            <div class="card shadow-sm border-0 mb-4 border-top border-4 border-primary">
                <div class="card-header bg-white py-3 border-bottom">
                    <h6 class="m-0 font-weight-bold text-dark">
                        <i class="fa-solid fa-clipboard-check text-primary me-2"></i>IT Department Status
                    </h6>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <span class="text-xs text-muted text-uppercase d-block font-weight-bold">Current Status</span>
                        <div class="mt-1">{!! $ticket->status_badge !!}</div>
                    </div>

                    <div class="mb-3">
                        <span class="text-xs text-muted text-uppercase d-block font-weight-bold">Assigned Specialist</span>
                        <strong class="text-dark">{{ $ticket->assignedTo->name ?? 'Unassigned (In Queue)' }}</strong>
                    </div>

                    <div class="mb-3">
                        <span class="text-xs text-muted text-uppercase d-block font-weight-bold">Target Resolution Date</span>
                        <span>{{ $ticket->target_resolution_date ? $ticket->target_resolution_date->format('M d, Y') : 'Per SLA Policy' }}</span>
                    </div>

                    @if($ticket->actions_taken || $ticket->root_cause)
                    <div class="mb-3">
                        <span class="text-xs text-muted text-uppercase d-block font-weight-bold">Actions Taken / Root Cause</span>
                        <div class="p-2 bg-light rounded text-dark small">{{ $ticket->actions_taken ?: $ticket->root_cause }}</div>
                    </div>
                    @endif

                    @if($ticket->resolution_decision)
                    <div class="mb-3">
                        <span class="text-xs text-muted text-uppercase d-block font-weight-bold">Resolution / Decision</span>
                        <div class="p-2 bg-success-subtle text-success border border-success-subtle rounded small">{{ $ticket->resolution_decision }}</div>
                    </div>
                    @endif

                    @if($ticket->resolved_at)
                    <div class="mb-2 text-success">
                        <span class="text-xs text-uppercase d-block font-weight-bold">Resolved On</span>
                        <strong>{{ $ticket->resolved_at->format('M d, Y h:i A') }}</strong>
                    </div>
                    @endif
                </div>
            </div>

            <!-- Escalation & SLA Info Card -->
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-white py-3 border-bottom">
                    <h6 class="m-0 font-weight-bold text-dark">
                        <i class="fa-solid fa-clock me-2 text-warning"></i>SLA Response Time Guide
                    </h6>
                </div>
                <div class="card-body">
                    @php $sla = $ticket->sla_details; @endphp
                    <div class="p-3 rounded bg-light border mb-3">
                        <div class="d-flex justify-content-between mb-2">
                            <span class="small font-weight-bold">First Response Target:</span>
                            <span class="badge bg-primary">{{ $sla['first_response'] }}</span>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span class="small font-weight-bold">Target Resolution:</span>
                            <span class="badge bg-success">{{ $sla['target_resolution'] }}</span>
                        </div>
                        <small class="text-muted d-block mt-2 border-top pt-2">{{ $sla['description'] }}</small>
                    </div>

                    @if($ticket->sms_alert_sent)
                    <div class="alert alert-success p-2 small mb-0 d-flex align-items-center">
                        <i class="fa-solid fa-check-circle me-2 fs-5"></i>
                        <div>
                            <strong>SMS Escalation Dispatched</strong><br>
                            GM & Global Admin notified via SMS.
                        </div>
                    </div>
                    @endif
                </div>
            </div>

        </div>
    </div>
</div>
@endsection
