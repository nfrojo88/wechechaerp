@extends('layouts.app')

@section('title', 'Transfer Details - ' . ($transfer->transfer_no ?? 'Transfer'))

@section('content')
<div class="container-fluid px-4 py-3">

    {{-- User Permissions & Roles Check --}}
    @php
        $user = auth()->user();
        $rawUserRoles = $user ? $user->roles->pluck('name')->map(fn($r) => strtolower(str_replace([' ', '-'], '_', trim($r))))->toArray() : [];
        $isAuditorUser = in_array('auditor', $rawUserRoles) || in_array('audit', $rawUserRoles) || in_array('internal_auditor', $rawUserRoles) || in_array('audit_team', $rawUserRoles) || ($user && $user->hasAnyRole(['auditor', 'audit', 'internal_auditor', 'Auditor', 'Audit']));

        $userStoreId = $assignedStore?->id ?? $user->store_id;
        $isSenderStore = !$isAuditorUser && $userStoreId && ($transfer->from_store_id == $userStoreId);
        $isReceiverStore = !$isAuditorUser && $userStoreId && ($transfer->to_store_id == $userStoreId);
        $isAdmin = !$isAuditorUser && $user->hasAnyRole(['admin', 'global_admin', 'store_manager', 'general_service', 'coordinator']);

        // Workflow step flags
        $step1Completed = true;
        $step2Completed = !empty($transfer->driver_employee_id) || in_array($transfer->status, ['approved', 'in_transit', 'completed']);
        $step3Completed = in_array($transfer->status, ['in_transit', 'completed']);
        $step4Completed = $transfer->status === 'completed';

        // Defensive fallbacks (in case controller response is cached on production)
        $products = $products ?? \App\Models\Product::where('is_active', true)->orderBy('name')->get();
        $drivers = $drivers ?? \App\Models\Employee::where('status', 'active')->orderBy('full_name')->get();
        $compatibleTransfers = $compatibleTransfers ?? \App\Models\Transfer::with(['items.product', 'requestedBy'])
            ->where('id', '!=', $transfer->id)
            ->where('from_store_id', $transfer->from_store_id)
            ->where('to_store_id', $transfer->to_store_id)
            ->whereIn('status', ['draft', 'pending_approval', 'approved'])
            ->latest()
            ->get();
    @endphp

    @if($isAuditorUser)
        <div class="alert alert-info border-start border-4 border-info shadow-sm mb-4 d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div class="d-flex align-items-center gap-2">
                <div class="p-2.5 rounded-circle bg-info bg-opacity-25 text-info">
                    <i class="fa-solid fa-shield-halved fa-lg"></i>
                </div>
                <div>
                    <strong class="d-block text-dark">Internal Audit Oversight — Read-Only Mode</strong>
                    <span class="text-muted small">You have complete read-only inspection visibility into this material transfer's waybill, origin store, destination store, driver assignment, and received quantities.</span>
                </div>
            </div>
            <span class="badge bg-white text-info border border-info px-3 py-2 fw-semibold">
                <i class="fa-solid fa-lock me-1"></i> Read-Only Audit View
            </span>
        </div>
    @endif

    {{-- Top Header --}}
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <h4 class="fw-bold mb-0" style="color:var(--brand-800)">
                    <i class="fas fa-truck-moving me-2 text-primary"></i>Transfer {{ $transfer->transfer_no }}
                </h4>
                @php
                    $statusBadge = match($transfer->status) {
                        'completed'  => 'bg-success',
                        'in_transit' => 'bg-info text-dark',
                        'approved'   => 'bg-primary',
                        'rejected'   => 'bg-danger',
                        default      => 'bg-secondary',
                    };
                    $statusLabel = match($transfer->status) {
                        'completed'  => 'Completed & Verified',
                        'in_transit' => 'In Transit with Driver',
                        'approved'   => 'Approved / Ready to Dispatch',
                        'rejected'   => 'Rejected',
                        default      => 'Draft / Needs Driver',
                    };
                @endphp
                <span class="badge {{ $statusBadge }} px-3 py-2 fs-6 rounded-pill">{{ $statusLabel }}</span>
                @if($isSenderStore)
                    <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 px-2 py-1">Outgoing Store (Sender)</span>
                @elseif($isReceiverStore)
                    <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 px-2 py-1">Incoming Store (Receiver)</span>
                @endif
            </div>
            <p class="text-muted small mb-0 mt-1">
                Requested on {{ $transfer->created_at ? $transfer->created_at->format('M d, Y H:i') : 'N/A' }} 
                by <strong>{{ $transfer->requestedBy->name ?? 'System' }}</strong>
            </p>
        </div>

        <div class="d-flex gap-2 flex-wrap">
            {{-- Material Work Adjustment & Merge (Editable Statuses) --}}
            @if(in_array($transfer->status, ['draft', 'pending_approval', 'approved']) && $isAdmin)
                <button type="button" class="btn btn-outline-primary btn-sm shadow-sm fw-semibold" data-bs-toggle="modal" data-bs-target="#adjustMaterialsModal">
                    <i class="fas fa-sliders me-1"></i>Work Adjustment
                </button>
                @if(isset($compatibleTransfers) && $compatibleTransfers->isNotEmpty())
                    <button type="button" class="btn btn-warning btn-sm text-dark shadow-sm fw-bold" data-bs-toggle="modal" data-bs-target="#mergeIntoModal">
                        <i class="fas fa-code-merge me-1"></i>Merge Transfers ({{ $compatibleTransfers->count() }})
                    </button>
                @endif
            @endif

            {{-- Quick action buttons in header --}}
            @if(in_array($transfer->status, ['draft', 'pending_approval']) && $isAdmin)
                <button type="button" class="btn btn-warning btn-sm shadow-sm text-dark fw-semibold" data-bs-toggle="modal" data-bs-target="#assignDriverModal">
                    <i class="fas fa-id-badge me-1"></i>Assign Driver
                </button>
            @endif

            @if(in_array($transfer->status, ['draft', 'approved']) && ($isSenderStore || $isAdmin))
                <button type="button" class="btn btn-primary btn-sm shadow-sm" data-bs-toggle="modal" data-bs-target="#dispatchModal">
                    <i class="fas fa-truck-fast me-1"></i>Dispatch &amp; Upload Slip
                </button>
            @endif

            @if($transfer->status === 'in_transit' && ($isReceiverStore || $isAdmin))
                <button type="button" class="btn btn-success btn-sm shadow-sm" data-bs-toggle="modal" data-bs-target="#receiveModal">
                    <i class="fas fa-box-open me-1"></i>Inspect &amp; Receive Materials
                </button>
            @endif

            @if(!in_array($transfer->status, ['completed', 'rejected']) && $isAdmin)
                <button type="button" class="btn btn-outline-danger btn-sm" data-bs-toggle="modal" data-bs-target="#rejectModal">
                    <i class="fas fa-ban me-1"></i>Reject
                </button>
            @endif

            <a href="{{ route('store-manager.transfers.index') }}" class="btn btn-outline-secondary btn-sm">
                <i class="fas fa-arrow-left me-1"></i>Back to Transfers
            </a>
        </div>
    </div>

    {{-- 4-Step Interactive Visual Workflow --}}
    <div class="card shadow-sm border-0 rounded-3 mb-4">
        <div class="card-body p-4">
            <h6 class="fw-bold text-muted small text-uppercase mb-3">Transfer Lifecycle &amp; Custody Trail</h6>
            <div class="row g-3 text-center">
                {{-- Step 1 --}}
                <div class="col-md-3">
                    <div class="p-3 rounded-3 h-100 {{ $step1Completed ? 'bg-success bg-opacity-10 border border-success border-opacity-25' : 'bg-light' }}">
                        <div class="d-inline-flex p-2 rounded-circle mb-2 {{ $step1Completed ? 'bg-success text-white' : 'bg-secondary text-white' }}">
                            <i class="fas fa-file-lines fa-lg"></i>
                        </div>
                        <div class="fw-bold text-dark small">1. Request Created</div>
                        <div class="text-muted" style="font-size: 0.75rem;">
                            {{ $transfer->fromStore->name ?? 'Origin' }} &rarr; {{ $transfer->toStore->name ?? 'Destination' }}
                        </div>
                        <div class="text-success fw-semibold mt-1" style="font-size: 0.72rem;">
                            <i class="fas fa-check-circle me-1"></i>Initiated
                        </div>
                    </div>
                </div>

                {{-- Step 2 --}}
                <div class="col-md-3">
                    <div class="p-3 rounded-3 h-100 {{ $step2Completed ? 'bg-success bg-opacity-10 border border-success border-opacity-25' : 'bg-warning bg-opacity-10 border border-warning border-opacity-25' }}">
                        <div class="d-inline-flex p-2 rounded-circle mb-2 {{ $step2Completed ? 'bg-success text-white' : 'bg-warning text-dark' }}">
                            <i class="fas fa-id-card fa-lg"></i>
                        </div>
                        <div class="fw-bold text-dark small">2. Driver Assignment</div>
                        <div class="text-muted" style="font-size: 0.75rem;">
                            @if($transfer->driver)
                                <strong>{{ $transfer->driver->full_name }}</strong> ({{ $transfer->vehicle_plate_no ?: 'No plate' }})
                            @else
                                Awaiting General Service
                            @endif
                        </div>
                        @if($step2Completed)
                            <div class="text-success fw-semibold mt-1" style="font-size: 0.72rem;">
                                <i class="fas fa-check-circle me-1"></i>Driver Assigned
                            </div>
                        @else
                            <div class="text-warning fw-semibold mt-1" style="font-size: 0.72rem;">
                                <i class="fas fa-clock me-1"></i>Pending Assignment
                            </div>
                        @endif
                    </div>
                </div>

                {{-- Step 3 --}}
                <div class="col-md-3">
                    <div class="p-3 rounded-3 h-100 {{ $step3Completed ? 'bg-success bg-opacity-10 border border-success border-opacity-25' : ($step2Completed ? 'bg-primary bg-opacity-10 border border-primary border-opacity-25' : 'bg-light') }}">
                        <div class="d-inline-flex p-2 rounded-circle mb-2 {{ $step3Completed ? 'bg-success text-white' : ($step2Completed ? 'bg-primary text-white' : 'bg-secondary text-white') }}">
                            <i class="fas fa-truck-ramp-box fa-lg"></i>
                        </div>
                        <div class="fw-bold text-dark small">3. Outgoing Dispatch</div>
                        <div class="text-muted" style="font-size: 0.75rem;">
                            @if($transfer->outgoing_slip_no || $transfer->physical_slip_no)
                                Slip: <span class="font-monospace fw-bold">{{ $transfer->outgoing_slip_no ?: $transfer->physical_slip_no }}</span>
                            @else
                                Waybill Slip &amp; Stock Deduction
                            @endif
                        </div>
                        @if($step3Completed)
                            <div class="text-success fw-semibold mt-1" style="font-size: 0.72rem;">
                                <i class="fas fa-check-circle me-1"></i>Dispatched &amp; Stock Deducted
                            </div>
                        @else
                            <div class="text-primary fw-semibold mt-1" style="font-size: 0.72rem;">
                                <i class="fas fa-hourglass-half me-1"></i>Ready for Dispatch
                            </div>
                        @endif
                    </div>
                </div>

                {{-- Step 4 --}}
                <div class="col-md-3">
                    <div class="p-3 rounded-3 h-100 {{ $step4Completed ? 'bg-success bg-opacity-10 border border-success border-opacity-25' : ($step3Completed ? 'bg-info bg-opacity-10 border border-info border-opacity-25' : 'bg-light') }}">
                        <div class="d-inline-flex p-2 rounded-circle mb-2 {{ $step4Completed ? 'bg-success text-white' : ($step3Completed ? 'bg-info text-white' : 'bg-secondary text-white') }}">
                            <i class="fas fa-box-open fa-lg"></i>
                        </div>
                        <div class="fw-bold text-dark small">4. Incoming Receipt</div>
                        <div class="text-muted" style="font-size: 0.75rem;">
                            @if($step4Completed)
                                Received into {{ $transfer->toStore->name ?? 'Destination' }}
                            @else
                                Physical Verification &amp; Stock Added
                            @endif
                        </div>
                        @if($step4Completed)
                            <div class="text-success fw-semibold mt-1" style="font-size: 0.72rem;">
                                <i class="fas fa-check-circle me-1"></i>Verified &amp; Inventory Added
                            </div>
                        @elseif($step3Completed)
                            <div class="text-info fw-semibold mt-1" style="font-size: 0.72rem;">
                                <i class="fas fa-truck-moving me-1"></i>In Transit / Ready to Receive
                            </div>
                        @else
                            <div class="text-muted mt-1" style="font-size: 0.72rem;">Awaiting Dispatch</div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Details Grid --}}
    <div class="row g-4 mb-4">

        {{-- Transfer & Location Info --}}
        <div class="col-lg-4 col-md-6">
            <div class="card shadow-sm border-0 rounded-3 h-100">
                <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                    <h6 class="mb-0 fw-bold text-dark"><i class="fas fa-warehouse me-2 text-primary"></i>Store Locations</h6>
                </div>
                <div class="card-body">
                    <div class="mb-3 p-3 rounded bg-light">
                        <div class="text-muted small fw-semibold mb-1">
                            <i class="fas fa-arrow-up text-danger me-1"></i>Origin Store (መላኪያ መጋዘን)
                        </div>
                        <div class="fs-6 fw-bold text-dark">{{ $transfer->fromStore->name ?? 'N/A' }}</div>
                        <small class="text-muted">{{ $transfer->fromStore->location ?? '' }}</small>
                    </div>

                    <div class="mb-3 p-3 rounded bg-light">
                        <div class="text-muted small fw-semibold mb-1">
                            <i class="fas fa-arrow-down text-success me-1"></i>Destination Store (መቀበያ መጋዘን)
                        </div>
                        <div class="fs-6 fw-bold text-dark">{{ $transfer->toStore->name ?? 'N/A' }}</div>
                        <small class="text-muted">{{ $transfer->toStore->location ?? '' }}</small>
                    </div>

                    <table class="table table-sm table-borderless mb-0">
                        <tr>
                            <td class="text-muted small" style="width:40%;">Required Date:</td>
                            <td class="small fw-semibold">{{ $transfer->required_date ? $transfer->required_date->format('M d, Y') : 'Immediate' }}</td>
                        </tr>
                        <tr>
                            <td class="text-muted small">Purpose / Notes:</td>
                            <td class="small">{{ $transfer->reason ?: 'No notes provided' }}</td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>

        {{-- Driver & Logistics Assignment --}}
        <div class="col-lg-4 col-md-6">
            <div class="card shadow-sm border-0 rounded-3 h-100">
                <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                    <h6 class="mb-0 fw-bold text-dark"><i class="fas fa-id-card me-2 text-primary"></i>Driver &amp; Logistics</h6>
                    @if($isAdmin)
                        <button type="button" class="btn btn-sm btn-outline-primary py-0 px-2" data-bs-toggle="modal" data-bs-target="#assignDriverModal">
                            <i class="fas fa-pen me-1"></i>Edit
                        </button>
                    @endif
                </div>
                <div class="card-body">
                    @if($transfer->driver)
                        <div class="d-flex align-items-center gap-3 mb-3 p-3 rounded bg-light">
                            <div class="p-3 rounded-circle bg-primary bg-opacity-10 text-primary">
                                <i class="fas fa-user-tie fa-2x"></i>
                            </div>
                            <div>
                                <h6 class="fw-bold text-dark mb-0">{{ $transfer->driver->full_name }}</h6>
                                <div class="text-muted small">
                                    <i class="fas fa-phone text-success me-1"></i>{{ $transfer->driver->phone ?: 'No phone on file' }}
                                </div>
                                <div class="text-muted small">
                                    <i class="fas fa-building text-secondary me-1"></i>{{ $transfer->driver->department ?? 'General Service' }}
                                </div>
                            </div>
                        </div>

                        <table class="table table-sm table-borderless mb-0">
                            <tr>
                                <td class="text-muted small" style="width:45%;">Vehicle Plate #:</td>
                                <td class="small"><strong class="font-monospace text-dark">{{ $transfer->vehicle_plate_no ?: 'Not specified' }}</strong></td>
                            </tr>
                            <tr>
                                <td class="text-muted small">Assigned By:</td>
                                <td class="small">{{ $transfer->approvedBy->name ?? 'System' }}</td>
                            </tr>
                            <tr>
                                <td class="text-muted small">Assigned At:</td>
                                <td class="small">{{ $transfer->approved_at ? $transfer->approved_at->format('M d, Y H:i') : 'N/A' }}</td>
                            </tr>
                            @if($transfer->dispatch_notes)
                            <tr>
                                <td class="text-muted small">Driver Notes:</td>
                                <td class="small text-muted">{{ $transfer->dispatch_notes }}</td>
                            </tr>
                            @endif
                        </table>
                    @else
                        <div class="text-center py-4 text-muted">
                            <i class="fas fa-id-badge fa-3x mb-2 opacity-25"></i>
                            <p class="small mb-3">No driver assigned yet for this transfer.</p>
                            @if($isAdmin)
                                <button type="button" class="btn btn-warning btn-sm shadow-sm text-dark fw-semibold" data-bs-toggle="modal" data-bs-target="#assignDriverModal">
                                    <i class="fas fa-plus me-1"></i>Assign Driver Now
                                </button>
                            @endif
                        </div>
                    @endif
                </div>
            </div>
        </div>

        {{-- Physical Slips & Documents --}}
        <div class="col-lg-4 col-md-12">
            <div class="card shadow-sm border-0 rounded-3 h-100">
                <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                    <h6 class="mb-0 fw-bold text-dark"><i class="fas fa-receipt me-2 text-primary"></i>Waybill &amp; Slips</h6>
                    <button type="button" class="btn btn-sm btn-outline-secondary py-0 px-2" data-bs-toggle="modal" data-bs-target="#physicalSlipModal">
                        <i class="fas fa-pen me-1"></i>Quick Slip #
                    </button>
                </div>
                <div class="card-body">
                    {{-- Outgoing Slip Box --}}
                    <div class="p-3 rounded mb-3 border {{ $transfer->outgoing_slip_file || $transfer->outgoing_slip_no ? 'border-primary border-opacity-25 bg-primary bg-opacity-10' : 'bg-light' }}">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <span class="fw-semibold small text-dark"><i class="fas fa-file-export me-1 text-primary"></i>Outgoing Physical Slip / Waybill</span>
                            @if($transfer->outgoing_slip_url)
                                <a href="{{ $transfer->outgoing_slip_url }}" target="_blank" class="btn btn-xs btn-primary btn-sm py-0 px-2">
                                    <i class="fas fa-external-link-alt me-1"></i>View Slip
                                </a>
                            @endif
                        </div>
                        <div class="font-monospace fw-bold text-dark">
                            {{ $transfer->outgoing_slip_no ?: $transfer->physical_slip_no ?: 'Not recorded yet' }}
                        </div>
                        @if($transfer->dispatchedBy)
                            <div class="text-muted small" style="font-size:0.75rem;">
                                Dispatched by {{ $transfer->dispatchedBy->name }} on {{ $transfer->dispatched_at ? $transfer->dispatched_at->format('M d, Y H:i') : '' }}
                            </div>
                        @endif
                    </div>

                    {{-- Receiving Slip Box --}}
                    <div class="p-3 rounded border {{ $transfer->receiving_slip_file || $transfer->receiving_slip_no ? 'border-success border-opacity-25 bg-success bg-opacity-10' : 'bg-light' }}">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <span class="fw-semibold small text-dark"><i class="fas fa-file-import me-1 text-success"></i>Signed Receiving Slip / GRN</span>
                            @if($transfer->receiving_slip_url)
                                <a href="{{ $transfer->receiving_slip_url }}" target="_blank" class="btn btn-xs btn-success btn-sm py-0 px-2">
                                    <i class="fas fa-external-link-alt me-1"></i>View Slip
                                </a>
                            @endif
                        </div>
                        <div class="font-monospace fw-bold text-dark">
                            {{ $transfer->receiving_slip_no ?: ($transfer->status === 'completed' ? 'Verified into Store' : 'Pending Receipt') }}
                        </div>
                        @if($transfer->receivedBy)
                            <div class="text-muted small" style="font-size:0.75rem;">
                                Received by {{ $transfer->receivedBy->name }} on {{ $transfer->received_at ? $transfer->received_at->format('M d, Y H:i') : '' }}
                            </div>
                        @endif
                        @if($transfer->receiving_notes)
                            <div class="text-muted small mt-1" style="font-size:0.75rem;">
                                Notes: {{ $transfer->receiving_notes }}
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>

    </div>

    {{-- Transfer Items Table with Sent & Received Breakdown --}}
    <div class="card shadow-sm border-0 rounded-3 mb-4">
        <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div>
                <h6 class="mb-0 fw-bold text-dark"><i class="fas fa-boxes-stacked me-2 text-primary"></i>Transferred Materials Inventory Details</h6>
                <small class="text-muted">Compare requested, sent by origin storekeeper, and received by destination storekeeper</small>
            </div>
            <div class="d-flex gap-2 flex-wrap align-items-center">
                @if(in_array($transfer->status, ['draft', 'pending_approval', 'approved']) && $isAdmin)
                    <button type="button" class="btn btn-outline-primary btn-sm shadow-sm" data-bs-toggle="modal" data-bs-target="#adjustMaterialsModal">
                        <i class="fas fa-sliders me-1"></i>Adjust Materials &amp; Qty
                    </button>
                    @if(isset($compatibleTransfers) && $compatibleTransfers->isNotEmpty())
                        <button type="button" class="btn btn-warning btn-sm text-dark fw-bold shadow-sm" data-bs-toggle="modal" data-bs-target="#mergeIntoModal">
                            <i class="fas fa-code-merge me-1"></i>Merge Transfers ({{ $compatibleTransfers->count() }})
                        </button>
                    @endif
                @endif

                @if(empty($transfer->driver_employee_id) && in_array($transfer->status, ['draft', 'pending_approval', 'approved']) && ($isAdmin || $isSenderStore))
                    <button type="button" class="btn btn-outline-danger btn-sm shadow-sm fw-semibold" data-bs-toggle="modal" data-bs-target="#deleteTransferModal" title="Delete this transfer (Permitted prior to driver assignment)">
                        <i class="fas fa-trash-can me-1"></i>Delete Transfer
                    </button>
                @endif

                @if(in_array($transfer->status, ['draft', 'approved']) && ($isSenderStore || $isAdmin))
                    <button type="button" class="btn btn-primary btn-sm shadow-sm" data-bs-toggle="modal" data-bs-target="#dispatchModal">
                        <i class="fas fa-truck-fast me-1"></i>Dispatch Sent Items
                    </button>
                @endif

                @if($transfer->status === 'in_transit' && ($isReceiverStore || $isAdmin))
                    <button type="button" class="btn btn-success btn-sm shadow-sm" data-bs-toggle="modal" data-bs-target="#receiveModal">
                        <i class="fas fa-box-open me-1"></i>Receive Materials into Stock
                    </button>
                @endif
            </div>
        </div>

        @if(isset($compatibleTransfers) && $compatibleTransfers->isNotEmpty() && $isAdmin && in_array($transfer->status, ['draft', 'pending_approval', 'approved']))
        <div class="alert alert-info border-0 shadow-sm mx-3 mt-3 mb-0 d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div>
                <i class="fas fa-code-merge text-primary me-2 fa-lg"></i>
                <strong>{{ $compatibleTransfers->count() }} other pending transfer(s)</strong> share this exact route: 
                <span class="badge bg-white text-dark">{{ $transfer->fromStore->name ?? 'Origin' }} &rarr; {{ $transfer->toStore->name ?? 'Destination' }}</span>
                <span class="text-muted small d-block">e.g. {{ $compatibleTransfers->pluck('transfer_no')->implode(', ') }}</span>
            </div>
            <button type="button" class="btn btn-primary btn-sm fw-bold shadow-sm" data-bs-toggle="modal" data-bs-target="#mergeIntoModal">
                <i class="fas fa-code-merge me-1"></i>Merge Them Into #{{ $transfer->transfer_no }}
            </button>
        </div>
        @endif

        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-3" style="width: 50px;">#</th>
                            <th>Material / Product Name</th>
                            <th>SKU / Code</th>
                            <th class="text-end">Requested Qty</th>
                            <th class="text-end">Sent Qty (Origin)</th>
                            <th class="text-end">Received Qty (Dest.)</th>
                            <th class="text-center">Unit</th>
                            <th class="text-center">Variance / Status</th>
                            @if(in_array($transfer->status, ['draft', 'pending_approval', 'approved']) && $isAdmin)
                                <th class="text-end pe-3">Actions</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($transfer->items as $item)
                        @php
                            $variance = ($item->received_quantity > 0) ? ($item->received_quantity - $item->sent_quantity) : 0;
                            $canAdjustItems = in_array($transfer->status, ['draft', 'pending_approval', 'approved']) && $isAdmin;
                        @endphp
                        <tr>
                            <td class="ps-3">{{ $loop->iteration }}</td>
                            <td>
                                <strong class="text-dark">{{ $item->product->name ?? 'N/A' }}</strong>
                                @if($item->product && $item->product->category)
                                    <div class="text-muted small">{{ $item->product->category->name ?? '' }}</div>
                                @endif
                            </td>
                            <td>
                                <span class="font-monospace text-muted small">{{ $item->product->code ?? $item->product->sku ?? '—' }}</span>
                            </td>
                            <td class="text-end fw-bold text-dark">
                                {{ number_format($item->requested_quantity, 2) }}
                            </td>
                            <td class="text-end fw-bold text-primary">
                                @if($item->sent_quantity > 0 || in_array($transfer->status, ['in_transit', 'completed']))
                                    {{ number_format($item->sent_quantity > 0 ? $item->sent_quantity : $item->requested_quantity, 2) }}
                                @else
                                    <span class="text-muted small">Pending</span>
                                @endif
                            </td>
                            <td class="text-end fw-bold text-success">
                                @if($transfer->status === 'completed')
                                    {{ number_format($item->received_quantity > 0 ? $item->received_quantity : ($item->sent_quantity > 0 ? $item->sent_quantity : $item->requested_quantity), 2) }}
                                @else
                                    <span class="text-muted small">In Transit</span>
                                @endif
                            </td>
                            <td class="text-center">
                                <span class="badge bg-light text-dark border">{{ $item->unit }}</span>
                            </td>
                            <td class="text-center">
                                @if($transfer->status === 'completed')
                                    @if($variance == 0)
                                        <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25">
                                            <i class="fas fa-check me-1"></i>Exact Match
                                        </span>
                                    @elseif($variance < 0)
                                        <span class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25">
                                            Short by {{ number_format(abs($variance), 2) }} {{ $item->unit }}
                                        </span>
                                    @else
                                        <span class="badge bg-info bg-opacity-10 text-info border border-info border-opacity-25">
                                            +{{ number_format($variance, 2) }} {{ $item->unit }} Extra
                                        </span>
                                    @endif
                                @elseif($transfer->status === 'in_transit')
                                    <span class="badge bg-info bg-opacity-10 text-info border border-info border-opacity-25">
                                        <i class="fas fa-truck-moving me-1"></i>With Driver
                                    </span>
                                @else
                                    <span class="badge bg-secondary bg-opacity-10 text-secondary border">
                                        Pending Dispatch
                                    </span>
                                @endif
                            </td>
                            @if($canAdjustItems)
                            <td class="text-end pe-3 text-nowrap">
                                <div class="d-inline-flex gap-1 align-items-center">
                                    <button type="button" class="btn btn-xs btn-outline-primary btn-sm py-0 px-2 btn-move-item"
                                            data-item-id="{{ $item->id }}"
                                            data-product-name="{{ $item->product->name ?? 'Material' }}"
                                            data-product-code="{{ $item->product->code ?? '' }}"
                                            data-quantity="{{ $item->requested_quantity }}"
                                            data-unit="{{ $item->unit }}"
                                            title="Move or Split this item">
                                        <i class="fas fa-arrows-split-up-and-left me-1"></i>Split / Move
                                    </button>

                                    @if(empty($transfer->driver_employee_id))
                                    <button type="button" class="btn btn-xs btn-outline-danger btn-sm py-0 px-2 btn-delete-single-item"
                                            data-item-id="{{ $item->id }}"
                                            data-product-name="{{ $item->product->name ?? 'Material' }}"
                                            data-quantity="{{ number_format($item->requested_quantity, 2) }} {{ $item->unit }}"
                                            data-delete-url="{{ Route::has('store-manager.transfers.items.delete') ? route('store-manager.transfers.items.delete', [$transfer, $item]) : url('store-manager/transfers/'.$transfer->id.'/items/'.$item->id.'/delete') }}"
                                            title="Delete this material line (Permitted before driver assignment)">
                                        <i class="fas fa-trash-can me-1"></i>Delete
                                    </button>
                                    @endif
                                </div>
                            </td>
                            @endif
                        </tr>
                        @empty
                        <tr>
                            <td colspan="{{ in_array($transfer->status, ['draft', 'pending_approval', 'approved']) && $isAdmin ? 9 : 8 }}" class="text-center py-4 text-muted">No items found in this transfer record</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div>

{{-- MODAL 1: Assign Driver (General Service / Store Manager / Admin) --}}
<div class="modal fade" id="assignDriverModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <form action="{{ route('store-manager.transfers.assign-driver', $transfer) }}" method="POST">
                @csrf
                <div class="modal-header py-3 px-4" style="background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%); border-bottom: 1px solid rgba(255,255,255,0.1);">
                    <div class="d-flex align-items-center gap-3">
                        <div class="rounded-3 p-2 d-flex align-items-center justify-content-center shadow-sm" style="background: rgba(245, 158, 11, 0.18); border: 1px solid rgba(251, 191, 36, 0.3); width: 40px; height: 40px;">
                            <i class="fas fa-id-badge" style="color: #fbbf24; font-size: 1.1rem;"></i>
                        </div>
                        <div>
                            <h5 class="modal-title fw-bold mb-0" style="color: #ffffff !important; font-size: 1.15rem;">Assign Driver &amp; Vehicle</h5>
                            <span class="small" style="color: #94a3b8; font-size: 0.8rem;">General service transport dispatch &amp; SMS notification</span>
                        </div>
                    </div>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4 bg-white">
                    <p class="text-muted small mb-3">
                        Select an active driver to transport materials from <strong>{{ $transfer->fromStore->name ?? 'Origin Store' }}</strong> to <strong>{{ $transfer->toStore->name ?? 'Destination Store' }}</strong>. An automated SMS notification with transfer details will be sent to the driver.
                    </p>

                    <div class="mb-3">
                        <label class="form-label fw-bold small text-uppercase" style="color: #334155;">Driver <span class="text-danger">*</span></label>
                        <select name="driver_employee_id" class="form-select form-select-lg rounded-3" style="font-size: 0.95rem; border: 1.5px solid #cbd5e1;" required>
                            <option value="">-- Select Driver --</option>
                            @foreach($drivers as $drv)
                                <option value="{{ $drv->id }}" {{ old('driver_employee_id', $transfer->driver_employee_id) == $drv->id ? 'selected' : '' }}>
                                    {{ $drv->full_name }} {{ $drv->phone ? '('.$drv->phone.')' : '' }} - {{ $drv->department ?? 'General Service' }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold small text-uppercase" style="color: #334155;">Vehicle Plate Number</label>
                        <input type="text" name="vehicle_plate_no" class="form-control rounded-3" style="border: 1.5px solid #cbd5e1;" placeholder="e.g. 3-45678 AA / 2-98765 ET" value="{{ old('vehicle_plate_no', $transfer->vehicle_plate_no) }}">
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold small text-uppercase" style="color: #334155;">Logistics &amp; Dispatch Instructions</label>
                        <textarea name="dispatch_notes" rows="2" class="form-control rounded-3" style="border: 1.5px solid #cbd5e1;" placeholder="Special handling notes, delivery route, or required arrival timing...">{{ old('dispatch_notes', $transfer->dispatch_notes) }}</textarea>
                    </div>
                </div>
                <div class="modal-footer py-3 px-4" style="background: #f8fafc; border-top: 1px solid #e2e8f0;">
                    <button type="button" class="btn btn-light border px-4 fw-semibold" style="color: #475569;" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn text-white px-4 fw-bold shadow-sm" style="background: linear-gradient(135deg, #d97706 0%, #b45309 100%);">
                        <i class="fas fa-paper-plane me-1.5"></i>Confirm &amp; Notify Driver
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- MODAL 2: Outgoing Store Keeper Dispatch & Slip Upload --}}
<div class="modal fade" id="dispatchModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <form action="{{ route('store-manager.transfers.dispatch', $transfer) }}" method="POST" enctype="multipart/form-data">
                @csrf
                <div class="modal-header py-3 px-4" style="background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%); border-bottom: 1px solid rgba(255,255,255,0.1);">
                    <div class="d-flex align-items-center gap-3">
                        <div class="rounded-3 p-2 d-flex align-items-center justify-content-center shadow-sm" style="background: rgba(59, 130, 246, 0.18); border: 1px solid rgba(96, 165, 250, 0.3); width: 40px; height: 40px;">
                            <i class="fas fa-truck-fast" style="color: #60a5fa; font-size: 1.1rem;"></i>
                        </div>
                        <div>
                            <h5 class="modal-title fw-bold mb-0" style="color: #ffffff !important; font-size: 1.15rem;">Dispatch Material &amp; Upload Outgoing Slip</h5>
                            <span class="small" style="color: #94a3b8; font-size: 0.8rem;">Deduct origin inventory and begin transit to site</span>
                        </div>
                    </div>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4 bg-white">
                    <div class="alert alert-info border-0 shadow-sm p-3 small mb-3 rounded-3" style="background-color: #f0f7ff; color: #1e3a8a; border-left: 4px solid #3b82f6 !important;">
                        <i class="fas fa-info-circle me-1.5"></i>
                        <strong>Important:</strong> Upon submitting this dispatch, the confirmed sent quantities will be <strong>automatically deducted from your store ({{ $transfer->fromStore->name ?? 'Origin Store' }}) inventory</strong>, and the status will update to <em>In Transit</em>.
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-uppercase" style="color: #334155;">Outgoing Physical Slip / Waybill # <span class="text-danger">*</span></label>
                            <input type="text" name="outgoing_slip_no" class="form-control font-monospace rounded-3" style="border: 1.5px solid #cbd5e1;" placeholder="e.g. SLIP-09823 or WB-2026-44" value="{{ old('outgoing_slip_no', $transfer->outgoing_slip_no ?: $transfer->physical_slip_no) }}" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-uppercase" style="color: #334155;">Vehicle Plate # (Verification)</label>
                            <input type="text" name="vehicle_plate_no" class="form-control rounded-3" style="border: 1.5px solid #cbd5e1;" placeholder="e.g. 3-45678 AA" value="{{ old('vehicle_plate_no', $transfer->vehicle_plate_no) }}">
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-bold small text-uppercase" style="color: #334155;">Attach Outgoing Physical Slip / Waybill Document (PDF / Image)</label>
                            <input type="file" name="outgoing_slip_file" class="form-control rounded-3" style="border: 1.5px solid #cbd5e1;" accept=".jpeg,.jpg,.png,.pdf,.webp">
                            <small class="text-muted">Upload a clear photo or scanned copy of the signed physical outgoing paper slip.</small>
                        </div>
                    </div>

                    <h6 class="fw-bold border-bottom pb-2 mb-3 mt-4 small text-uppercase" style="color: #0f172a; letter-spacing: 0.04em;">Confirm Sent Quantities</h6>
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered align-middle mb-0" style="border-color: #e2e8f0;">
                            <thead class="table-light">
                                <tr>
                                    <th>Item</th>
                                    <th class="text-center" style="width: 130px;">Requested Qty</th>
                                    <th class="text-center" style="width: 150px;">Sent Qty <span class="text-danger">*</span></th>
                                    <th class="text-center" style="width: 80px;">Unit</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($transfer->items as $item)
                                <tr>
                                    <td>
                                        <strong style="color: #0f172a;">{{ $item->product->name ?? 'Item' }}</strong>
                                        <div class="text-muted small font-monospace">{{ $item->product->code ?? '' }}</div>
                                    </td>
                                    <td class="text-center fw-semibold">{{ number_format($item->requested_quantity, 2) }}</td>
                                    <td>
                                        <input type="number" step="0.001" min="0.001" name="items[{{ $item->id }}][sent_qty]" class="form-control form-control-sm text-end fw-bold text-primary" value="{{ old('items.'.$item->id.'.sent_qty', $item->sent_quantity > 0 ? $item->sent_quantity : $item->requested_quantity) }}" required>
                                    </td>
                                    <td class="text-center"><span class="badge bg-light text-dark border">{{ $item->unit }}</span></td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer py-3 px-4" style="background: #f8fafc; border-top: 1px solid #e2e8f0;">
                    <button type="button" class="btn btn-light border px-4 fw-semibold" style="color: #475569;" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn text-white px-4 fw-bold shadow-sm" style="background: linear-gradient(135deg, #1e3a8a 0%, #0f172a 100%);">
                        <i class="fas fa-truck-fast me-1.5"></i>Submit Dispatch &amp; Deduct Stock
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- MODAL 3: Incoming Store Keeper Inspect & Receive Materials --}}
<div class="modal fade" id="receiveModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <form action="{{ route('store-manager.transfers.receive', $transfer) }}" method="POST" enctype="multipart/form-data">
                @csrf
                <div class="modal-header py-3 px-4" style="background: linear-gradient(135deg, #065f46 0%, #064e3b 100%); border-bottom: 1px solid rgba(255,255,255,0.1);">
                    <div class="d-flex align-items-center gap-3">
                        <div class="rounded-3 p-2 d-flex align-items-center justify-content-center shadow-sm" style="background: rgba(16, 185, 129, 0.2); border: 1px solid rgba(52, 211, 153, 0.3); width: 40px; height: 40px;">
                            <i class="fas fa-box-open" style="color: #34d399; font-size: 1.1rem;"></i>
                        </div>
                        <div>
                            <h5 class="modal-title fw-bold mb-0" style="color: #ffffff !important; font-size: 1.15rem;">Inspect &amp; Receive Materials into Store</h5>
                            <span class="small" style="color: #a7f3d0; font-size: 0.8rem;">Confirm arrival and add stock to destination inventory</span>
                        </div>
                    </div>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4 bg-white">
                    <div class="alert alert-success border-0 shadow-sm p-3 small mb-3 rounded-3" style="background-color: #ecfdf5; color: #065f46; border-left: 4px solid #10b981 !important;">
                        <i class="fas fa-check-circle me-1.5"></i>
                        <strong>Stock Inflow:</strong> Upon confirming receipt, the verified quantities will be <strong>automatically added to your store ({{ $transfer->toStore->name ?? 'Destination Store' }}) inventory</strong>, completing this transfer.
                    </div>

                    {{-- Outgoing Slip Inspection Preview --}}
                    @if($transfer->outgoing_slip_url || $transfer->outgoing_slip_no || $transfer->physical_slip_no)
                    <div class="card border-0 p-3 mb-3 rounded-3" style="background: #f8fafc; border: 1.5px solid #e2e8f0 !important;">
                        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                            <div>
                                <span class="text-uppercase fw-bold d-block" style="color: #64748b; font-size: 0.72rem; letter-spacing: 0.05em;">Origin Store Waybill / Slip #:</span>
                                <strong class="font-monospace fs-6" style="color: #0f172a;">{{ $transfer->outgoing_slip_no ?: $transfer->physical_slip_no }}</strong>
                            </div>
                            @if($transfer->outgoing_slip_url)
                                <a href="{{ $transfer->outgoing_slip_url }}" target="_blank" class="btn btn-sm btn-outline-primary fw-semibold">
                                    <i class="fas fa-external-link-alt me-1"></i>Open Attached Outgoing Slip
                                </a>
                            @endif
                        </div>
                    </div>
                    @endif

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-uppercase" style="color: #334155;">Signed Receiving Slip / GRN # (Optional)</label>
                            <input type="text" name="receiving_slip_no" class="form-control font-monospace rounded-3" style="border: 1.5px solid #cbd5e1;" placeholder="e.g. GRN-2026-99" value="{{ old('receiving_slip_no', $transfer->receiving_slip_no) }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-uppercase" style="color: #334155;">Attach Signed Receiving Document (Optional)</label>
                            <input type="file" name="receiving_slip_file" class="form-control rounded-3" style="border: 1.5px solid #cbd5e1;" accept=".jpeg,.jpg,.png,.pdf,.webp">
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-bold small text-uppercase" style="color: #334155;">Inspection / Receiving Notes</label>
                            <textarea name="receiving_notes" rows="2" class="form-control rounded-3" style="border: 1.5px solid #cbd5e1;" placeholder="Condition of materials, remarks, or notes...">{{ old('receiving_notes', $transfer->receiving_notes) }}</textarea>
                        </div>
                    </div>

                    <h6 class="fw-bold border-bottom pb-2 mb-3 mt-4 small text-uppercase" style="color: #0f172a; letter-spacing: 0.04em;">Verify Received Quantities</h6>
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered align-middle mb-0" style="border-color: #e2e8f0;">
                            <thead class="table-light">
                                <tr>
                                    <th>Item</th>
                                    <th class="text-center" style="width: 120px;">Sent Qty</th>
                                    <th class="text-center" style="width: 150px;">Received Qty <span class="text-danger">*</span></th>
                                    <th class="text-center" style="width: 80px;">Unit</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($transfer->items as $item)
                                <tr>
                                    <td>
                                        <strong style="color: #0f172a;">{{ $item->product->name ?? 'Item' }}</strong>
                                        <div class="text-muted small font-monospace">{{ $item->product->code ?? '' }}</div>
                                    </td>
                                    <td class="text-center fw-semibold text-primary">{{ number_format($item->sent_quantity > 0 ? $item->sent_quantity : $item->requested_quantity, 2) }}</td>
                                    <td>
                                        <input type="number" step="0.001" min="0" name="items[{{ $item->id }}][received_qty]" class="form-control form-control-sm text-end fw-bold text-success" value="{{ old('items.'.$item->id.'.received_qty', $item->received_quantity > 0 ? $item->received_quantity : ($item->sent_quantity > 0 ? $item->sent_quantity : $item->requested_quantity)) }}" required>
                                    </td>
                                    <td class="text-center"><span class="badge bg-light text-dark border">{{ $item->unit }}</span></td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer py-3 px-4" style="background: #f8fafc; border-top: 1px solid #e2e8f0;">
                    <button type="button" class="btn btn-light border px-4 fw-semibold" style="color: #475569;" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn text-white px-4 fw-bold shadow-sm" style="background: linear-gradient(135deg, #059669 0%, #047857 100%);">
                        <i class="fas fa-check-circle me-1.5"></i>Confirm Receipt &amp; Add Stock to Inventory
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- MODAL 4: Quick Edit Physical Slip # --}}
<div class="modal fade" id="physicalSlipModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <form action="{{ route('store-manager.transfers.physical-slip', $transfer) }}" method="POST">
                @csrf
                <div class="modal-header py-3 px-4" style="background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%); border-bottom: 1px solid rgba(255,255,255,0.1);">
                    <div class="d-flex align-items-center gap-3">
                        <div class="rounded-3 p-2 d-flex align-items-center justify-content-center shadow-sm" style="background: rgba(59, 130, 246, 0.18); border: 1px solid rgba(96, 165, 250, 0.3); width: 40px; height: 40px;">
                            <i class="fa-solid fa-file-invoice" style="color: #60a5fa; font-size: 1.1rem;"></i>
                        </div>
                        <div>
                            <h5 class="modal-title fw-bold mb-0" style="color: #ffffff !important; font-size: 1.15rem;">Physical Slip / Waybill Number</h5>
                            <span class="small" style="color: #94a3b8; font-size: 0.8rem;">Delivery paper note reference</span>
                        </div>
                    </div>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4 bg-white">
                    <div class="mb-3">
                        <label class="form-label fw-bold small text-uppercase" style="color: #334155;">Physical Slip Number <span class="text-danger">*</span></label>
                        <input type="text" name="physical_slip_no" class="form-control font-monospace rounded-3" style="border: 1.5px solid #cbd5e1;" placeholder="e.g. SLIP-09823 or WB-2026-44" value="{{ old('physical_slip_no', $transfer->physical_slip_no ?: $transfer->outgoing_slip_no) }}" required>
                        <small class="text-muted mt-1 d-block">Enter the physical paper slip or delivery receipt number that accompanies this transfer.</small>
                    </div>
                </div>
                <div class="modal-footer py-3 px-4" style="background: #f8fafc; border-top: 1px solid #e2e8f0;">
                    <button type="button" class="btn btn-light border px-4 fw-semibold" style="color: #475569;" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn text-white px-4 fw-bold shadow-sm" style="background: linear-gradient(135deg, #1e3a8a 0%, #0f172a 100%);">
                        <i class="fa-solid fa-save me-1.5"></i>Save Slip #
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- MODAL 5: Reject Transfer --}}
<div class="modal fade" id="rejectModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <form action="{{ route('store-manager.transfers.reject', $transfer) }}" method="POST">
                @csrf
                <div class="modal-header py-3 px-4" style="background: linear-gradient(135deg, #991b1b 0%, #7f1d1d 100%); border-bottom: 1px solid rgba(255,255,255,0.1);">
                    <div class="d-flex align-items-center gap-3">
                        <div class="rounded-3 p-2 d-flex align-items-center justify-content-center shadow-sm" style="background: rgba(239, 68, 68, 0.2); border: 1px solid rgba(248, 113, 113, 0.3); width: 40px; height: 40px;">
                            <i class="fas fa-ban" style="color: #f87171; font-size: 1.1rem;"></i>
                        </div>
                        <div>
                            <h5 class="modal-title fw-bold mb-0" style="color: #ffffff !important; font-size: 1.15rem;">Reject Transfer</h5>
                            <span class="small" style="color: #fca5a5; font-size: 0.8rem;">Cancel transfer request and record rationale</span>
                        </div>
                    </div>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4 bg-white">
                    <div class="mb-3">
                        <label class="form-label fw-bold small text-uppercase" style="color: #334155;">Rejection Reason <span class="text-danger">*</span></label>
                        <textarea name="rejection_reason" rows="3" class="form-control rounded-3" style="border: 1.5px solid #cbd5e1;" placeholder="Specify reason for cancelling or rejecting this transfer..." required></textarea>
                    </div>
                </div>
                <div class="modal-footer py-3 px-4" style="background: #f8fafc; border-top: 1px solid #e2e8f0;">
                    <button type="button" class="btn btn-light border px-4 fw-semibold" style="color: #475569;" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn text-white px-4 fw-bold shadow-sm" style="background: linear-gradient(135deg, #dc2626 0%, #b91c1c 100%);">Confirm Rejection</button>
                </div>
            </form>
        </div>
    </div>
</div>

@if(in_array($transfer->status, ['draft', 'pending_approval', 'approved']) && $isAdmin)
{{-- MODAL 6: Material Work Adjustment (Edit/Add/Remove Items & Assign Driver) --}}
<div class="modal fade" id="adjustMaterialsModal" tabindex="-1">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <form action="{{ Route::has('store-manager.transfers.adjust-items') ? route('store-manager.transfers.adjust-items', $transfer) : url('store-manager/transfers/'.$transfer->id.'/adjust-items') }}" method="POST">
                @csrf
                <div class="modal-header py-3 px-4" style="background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%); border-bottom: 1px solid rgba(255,255,255,0.1);">
                    <div class="d-flex align-items-center gap-3">
                        <div class="rounded-3 p-2 d-flex align-items-center justify-content-center shadow-sm" style="background: rgba(16, 185, 129, 0.18); border: 1px solid rgba(52, 211, 153, 0.3); width: 40px; height: 40px;">
                            <i class="fas fa-sliders" style="color: #34d399; font-size: 1.1rem;"></i>
                        </div>
                        <div>
                            <h5 class="modal-title fw-bold mb-0" style="color: #ffffff !important; font-size: 1.15rem;">Material Work Adjustment &amp; Logistics</h5>
                            <span class="small" style="color: #94a3b8; font-size: 0.8rem;">Adjust line item quantities, add/remove materials, and assign transport</span>
                        </div>
                    </div>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4 bg-white">
                    <div class="alert alert-info border-0 shadow-sm p-3 small mb-3 rounded-3" style="background-color: #f0f7ff; color: #1e3a8a; border-left: 4px solid #3b82f6 !important;">
                        <i class="fas fa-info-circle me-1.5"></i>
                        Adjust requested material quantities, add new line items to this transfer, or mark unneeded items for removal before dispatching to site.
                    </div>

                    <div class="table-responsive mb-3">
                        <table class="table table-bordered table-sm align-middle" id="adjustmentItemsTable" style="border-color: #e2e8f0;">
                            <thead class="table-light">
                                <tr>
                                    <th style="min-width: 280px;">Product / Material <span class="text-danger">*</span></th>
                                    <th class="text-center" style="width: 160px;">Requested Qty <span class="text-danger">*</span></th>
                                    <th class="text-center" style="width: 120px;">Unit <span class="text-danger">*</span></th>
                                    <th class="text-center" style="width: 100px;">Remove?</th>
                                </tr>
                            </thead>
                            <tbody id="adjustmentTableBody">
                                @foreach($transfer->items as $idx => $adjItem)
                                <tr>
                                    <td>
                                        <input type="hidden" name="items[{{ $idx }}][id]" value="{{ $adjItem->id }}">
                                        <select name="items[{{ $idx }}][product_id]" class="form-select form-select-sm" required>
                                            @foreach($products as $p)
                                                <option value="{{ $p->id }}" {{ $adjItem->product_id == $p->id ? 'selected' : '' }}>
                                                    {{ $p->name }} {{ $p->code ? '('.$p->code.')' : '' }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td>
                                        <input type="number" step="0.001" min="0.001" name="items[{{ $idx }}][requested_quantity]" class="form-control form-control-sm text-end fw-bold" value="{{ $adjItem->requested_quantity }}" required>
                                    </td>
                                    <td>
                                        <input type="text" name="items[{{ $idx }}][unit]" class="form-control form-control-sm text-center" value="{{ $adjItem->unit }}" required>
                                    </td>
                                    <td class="text-center">
                                        <div class="form-check d-flex justify-content-center">
                                            <input class="form-check-input" type="checkbox" name="items[{{ $idx }}][delete]" value="1" title="Check to delete this item">
                                        </div>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <button type="button" class="btn btn-outline-success btn-sm mb-4 fw-semibold" id="btnAddAdjustmentItem">
                        <i class="fas fa-plus me-1"></i>Add Another Material Line
                    </button>

                    {{-- Driver & Vehicle Section within Adjustment Modal --}}
                    <div class="card border-0 shadow-sm p-3 rounded-3" style="background: #f8fafc; border: 1.5px solid #e2e8f0 !important;">
                        <h6 class="fw-bold mb-3 d-flex align-items-center gap-2" style="color: #0f172a;">
                            <span class="p-1 rounded-2 text-white d-inline-flex align-items-center justify-content-center" style="width: 26px; height: 26px; background: #2563eb; font-size: 0.8rem;"><i class="fas fa-id-badge"></i></span>
                            Assign Driver &amp; Logistics (Optional)
                        </h6>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label fw-bold small text-uppercase" style="color: #334155;">Driver</label>
                                <select name="driver_employee_id" class="form-select form-select-sm">
                                    <option value="">-- Keep Current / Assign Later --</option>
                                    @foreach($drivers as $drv)
                                        <option value="{{ $drv->id }}" {{ $transfer->driver_employee_id == $drv->id ? 'selected' : '' }}>
                                            {{ $drv->full_name }} {{ $drv->phone ? '('.$drv->phone.')' : '' }} - {{ $drv->department ?? 'General Service' }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold small text-uppercase" style="color: #334155;">Vehicle Plate Number</label>
                                <input type="text" name="vehicle_plate_no" class="form-control form-control-sm" placeholder="e.g. 3-45678 AA" value="{{ $transfer->vehicle_plate_no }}">
                            </div>
                            <div class="col-12">
                                <label class="form-label fw-bold small text-uppercase" style="color: #334155;">Dispatch / Handling Notes</label>
                                <textarea name="dispatch_notes" rows="2" class="form-control form-control-sm" placeholder="Consolidated handling notes, route, or timing...">{{ $transfer->dispatch_notes }}</textarea>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer py-3 px-4" style="background: #f8fafc; border-top: 1px solid #e2e8f0;">
                    <button type="button" class="btn btn-light border px-4 fw-semibold" style="color: #475569;" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn text-white px-4 fw-bold shadow-sm" style="background: linear-gradient(135deg, #1e3a8a 0%, #0f172a 100%);">
                        <i class="fas fa-save me-1.5"></i>Save Work Adjustments
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- MODAL 7: Move or Split Single Item --}}
<div class="modal fade" id="moveItemModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <form action="{{ Route::has('store-manager.transfers.move-item') ? route('store-manager.transfers.move-item', $transfer) : url('store-manager/transfers/'.$transfer->id.'/move-item') }}" method="POST">
                @csrf
                <input type="hidden" name="transfer_item_id" id="moveItemId">
                <div class="modal-header py-3 px-4" style="background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%); border-bottom: 1px solid rgba(255,255,255,0.1);">
                    <div class="d-flex align-items-center gap-3">
                        <div class="rounded-3 p-2 d-flex align-items-center justify-content-center shadow-sm" style="background: rgba(59, 130, 246, 0.18); border: 1px solid rgba(96, 165, 250, 0.3); width: 40px; height: 40px;">
                            <i class="fas fa-arrows-split-up-and-left" style="color: #60a5fa; font-size: 1.1rem;"></i>
                        </div>
                        <div>
                            <h5 class="modal-title fw-bold mb-0" style="color: #ffffff !important; font-size: 1.15rem;">Move or Split Material</h5>
                            <span class="small" style="color: #94a3b8; font-size: 0.8rem;">Re-route or separate items to another transfer</span>
                        </div>
                    </div>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4 bg-white">
                    <div class="p-3 rounded-3 mb-3 border" style="background: #f8fafc; border-color: #e2e8f0 !important;">
                        <div class="d-flex align-items-center justify-content-between mb-1">
                            <span class="text-uppercase fw-bold" style="color: #64748b; font-size: 0.72rem; letter-spacing: 0.05em;">Selected Material</span>
                            <span class="badge rounded-pill px-2.5 py-1" style="background: #e0f2fe; color: #0284c7; font-weight: 700; font-size: 0.75rem;">Source Line Item</span>
                        </div>
                        <div class="fw-bold fs-6" id="moveItemTitle" style="color: #0f172a;">—</div>
                        <div class="small mt-1" style="color: #475569;">
                            Available Quantity in this transfer: <strong id="moveItemAvailQty" style="color: #0284c7; font-weight: 700;">—</strong>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold small text-uppercase" style="color: #334155; letter-spacing: 0.04em;">
                            Quantity to Move / Split <span class="text-danger">*</span>
                        </label>
                        <div class="input-group">
                            <input type="number" step="0.001" min="0.001" name="move_quantity" id="moveItemQtyInput" class="form-control form-control-lg fw-bold" style="border: 1.5px solid #cbd5e1; font-size: 1.15rem; color: #0f172a;" required>
                            <span class="input-group-text font-monospace fw-bold px-3" id="moveItemUnitBadge" style="background: #f1f5f9; border: 1.5px solid #cbd5e1; border-left: 0; color: #334155;">pcs</span>
                        </div>
                        <small class="text-muted mt-1 d-block" style="font-size: 0.8rem;">Enter partial quantity to split the item, or full quantity to move the entire line item.</small>
                    </div>

                    <div class="mb-2">
                        <label class="form-label fw-bold small text-uppercase mb-2" style="color: #334155; letter-spacing: 0.04em;">
                            Destination Target <span class="text-danger">*</span>
                        </label>
                        
                        @if(isset($compatibleTransfers) && $compatibleTransfers->isNotEmpty())
                        <div class="target-card-wrapper mb-2" id="wrapperExistingTarget">
                            <div class="p-3 rounded-3" id="cardExistingTarget" style="background: #f0f7ff; border: 2px solid #3b82f6 !important; cursor: pointer; transition: all 0.2s ease;">
                                <div class="d-flex align-items-start gap-2">
                                    <input class="form-check-input mt-1 flex-shrink-0" type="radio" name="target_mode" id="targetModeExisting" value="existing" checked style="cursor: pointer;">
                                    <div class="w-100">
                                        <label for="targetModeExisting" class="d-block mb-0" style="cursor: pointer;">
                                            <strong class="d-block" style="color: #1e3a8a; font-size: 0.92rem; font-weight: 700;">Move to an existing pending transfer:</strong>
                                        </label>
                                        <select name="target_transfer_id" class="form-select form-select-sm mt-2 fw-medium" id="existingTargetSelect" style="background: #ffffff; border: 1.5px solid #93c5fd; color: #0f172a; font-weight: 600;">
                                            @foreach($compatibleTransfers as $ct)
                                                <option value="{{ $ct->id }}">
                                                    {{ $ct->transfer_no }} ({{ $ct->items->count() }} item(s), Req: {{ $ct->requestedBy->name ?? 'User' }})
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>
                        @endif

                        <div class="target-card-wrapper m-0" id="wrapperNewTarget">
                            <div class="p-3 rounded-3" id="cardNewTarget" style="background: #ffffff; border: 1.5px solid #e2e8f0 !important; cursor: pointer; transition: all 0.2s ease;">
                                <div class="d-flex align-items-start gap-2">
                                    <input class="form-check-input mt-1 flex-shrink-0" type="radio" name="target_mode" id="targetModeNew" value="new" {{ (!isset($compatibleTransfers) || $compatibleTransfers->isEmpty()) ? 'checked' : '' }} style="cursor: pointer;">
                                    <div class="w-100">
                                        <label for="targetModeNew" class="d-block mb-0" style="cursor: pointer;">
                                            <strong class="d-block" id="labelTitleNewTarget" style="color: #0f172a; font-size: 0.92rem; font-weight: 700;">Separate into a brand new transfer request</strong>
                                            <span class="small d-block mt-1" style="color: #64748b; font-size: 0.82rem;">Creates a new pending transfer for the same route with this material.</span>
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer py-3 px-4" style="background: #f8fafc; border-top: 1px solid #e2e8f0;">
                    <button type="button" class="btn btn-light border px-4 fw-semibold" style="color: #475569;" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn text-white px-4 fw-bold shadow-sm" style="background: linear-gradient(135deg, #1e3a8a 0%, #0f172a 100%);">
                        <i class="fas fa-check me-1.5"></i>Confirm Move / Separation
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- MODAL 8: Merge Other Compatible Transfers Into This Transfer --}}
@if(isset($compatibleTransfers) && $compatibleTransfers->isNotEmpty())
<div class="modal fade" id="mergeIntoModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <form action="{{ Route::has('store-manager.transfers.merge-into') ? route('store-manager.transfers.merge-into', $transfer) : url('store-manager/transfers/'.$transfer->id.'/merge-into') }}" method="POST">
                @csrf
                <div class="modal-header py-3 px-4" style="background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%); border-bottom: 1px solid rgba(255,255,255,0.1);">
                    <div class="d-flex align-items-center gap-3">
                        <div class="rounded-3 p-2 d-flex align-items-center justify-content-center shadow-sm" style="background: rgba(245, 158, 11, 0.18); border: 1px solid rgba(251, 191, 36, 0.3); width: 40px; height: 40px;">
                            <i class="fas fa-code-merge" style="color: #fbbf24; font-size: 1.1rem;"></i>
                        </div>
                        <div>
                            <h5 class="modal-title fw-bold mb-0" style="color: #ffffff !important; font-size: 1.15rem;">Merge Other Transfers Into #{{ $transfer->transfer_no }}</h5>
                            <span class="small" style="color: #94a3b8; font-size: 0.8rem;">Consolidate pending transfers sharing route into this record</span>
                        </div>
                    </div>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4 bg-white">
                    <div class="alert alert-info border-0 shadow-sm p-3 small mb-3 rounded-3" style="background-color: #f0f7ff; color: #1e3a8a; border-left: 4px solid #3b82f6 !important;">
                        <i class="fas fa-info-circle me-1.5"></i>
                        Select which pending transfers going from <strong>{{ $transfer->fromStore->name ?? 'Origin' }}</strong> to <strong>{{ $transfer->toStore->name ?? 'Destination' }}</strong> you would like to merge into <strong>#{{ $transfer->transfer_no }}</strong>.
                    </div>

                    <h6 class="fw-bold text-dark mb-2 small text-uppercase" style="letter-spacing: 0.04em;">Select Transfers to Merge:</h6>
                    <div class="d-flex flex-column gap-2 mb-4">
                        @foreach($compatibleTransfers as $ct)
                        <div class="p-3 rounded-3 border" style="background: #ffffff; border: 1.5px solid #e2e8f0 !important;">
                            <div class="form-check m-0">
                                <input class="form-check-input" type="checkbox" name="source_transfer_ids[]" value="{{ $ct->id }}" id="chk_merge_{{ $ct->id }}" checked>
                                <label class="form-check-label w-100 ps-1" for="chk_merge_{{ $ct->id }}">
                                    <div class="d-flex justify-content-between align-items-center flex-wrap">
                                        <strong class="font-monospace fs-6" style="color: #1e3a8a;">{{ $ct->transfer_no }}</strong>
                                        <span class="badge" style="background: #f1f5f9; color: #334155; border: 1px solid #cbd5e1; font-weight: 600;">{{ $ct->items->count() }} item(s)</span>
                                    </div>
                                    <small class="d-block mt-1" style="color: #64748b;">
                                        Requested by <strong style="color: #1e293b;">{{ $ct->requestedBy->name ?? 'User' }}</strong> &bull; Items: 
                                        {{ $ct->items->map(fn($i) => ($i->product->name ?? 'Item') . ' (' . number_format($i->requested_quantity, 1) . ' ' . $i->unit . ')')->implode(', ') }}
                                    </small>
                                </label>
                            </div>
                        </div>
                        @endforeach
                    </div>

                    <div class="form-check form-switch mb-4 p-3 rounded-3 ms-0 border" style="background: #f8fafc; border-color: #e2e8f0 !important;">
                        <input class="form-check-input ms-0 me-2" type="checkbox" name="consolidate_duplicates" value="1" id="chkConsolidateInto" checked>
                        <label class="form-check-label fw-semibold" style="color: #0f172a;" for="chkConsolidateInto">
                            Consolidate duplicate items (Sum quantities for matching products)
                        </label>
                    </div>

                    {{-- Driver assignment within merge modal --}}
                    <div class="card border-0 shadow-sm p-3 rounded-3 mb-2" style="background: #f8fafc; border: 1.5px solid #e2e8f0 !important;">
                        <h6 class="fw-bold mb-3 d-flex align-items-center gap-2" style="color: #0f172a;">
                            <span class="p-1 rounded-2 text-white d-inline-flex align-items-center justify-content-center" style="width: 26px; height: 26px; background: #2563eb; font-size: 0.8rem;"><i class="fas fa-id-badge"></i></span>
                            Assign Driver &amp; Vehicle (Optional)
                        </h6>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label fw-bold small text-uppercase" style="color: #334155;">Driver</label>
                                <select name="driver_employee_id" class="form-select form-select-sm">
                                    <option value="">-- Keep Current / Assign Later --</option>
                                    @foreach($drivers as $drv)
                                        <option value="{{ $drv->id }}" {{ $transfer->driver_employee_id == $drv->id ? 'selected' : '' }}>
                                            {{ $drv->full_name }} {{ $drv->phone ? '('.$drv->phone.')' : '' }} - {{ $drv->department ?? 'General Service' }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold small text-uppercase" style="color: #334155;">Vehicle Plate Number</label>
                                <input type="text" name="vehicle_plate_no" class="form-control form-control-sm" placeholder="e.g. 3-45678 AA" value="{{ $transfer->vehicle_plate_no }}">
                            </div>
                            <div class="col-12">
                                <label class="form-label fw-bold small text-uppercase" style="color: #334155;">Consolidated Dispatch Notes</label>
                                <textarea name="dispatch_notes" rows="2" class="form-control form-control-sm" placeholder="Instructions for driver...">{{ $transfer->dispatch_notes }}</textarea>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer py-3 px-4" style="background: #f8fafc; border-top: 1px solid #e2e8f0;">
                    <button type="button" class="btn btn-light border px-4 fw-semibold" style="color: #475569;" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn text-white px-4 fw-bold shadow-sm" style="background: linear-gradient(135deg, #d97706 0%, #b45309 100%);">
                        <i class="fas fa-code-merge me-1.5"></i>Merge All Selected Into #{{ $transfer->transfer_no }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif

{{-- MODAL 9: Delete Transfer (Only allowed before driver assignment) --}}
@if(empty($transfer->driver_employee_id) && in_array($transfer->status, ['draft', 'pending_approval', 'approved']))
<div class="modal fade" id="deleteTransferModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <form action="{{ Route::has('store-manager.transfers.delete') ? route('store-manager.transfers.delete', $transfer) : (Route::has('store-manager.transfers.destroy') ? route('store-manager.transfers.destroy', $transfer) : url('store-manager/transfers/'.$transfer->id.'/delete')) }}" method="POST">
                @csrf
                <div class="modal-header py-3 px-4" style="background: linear-gradient(135deg, #991b1b 0%, #7f1d1d 100%); border-bottom: 1px solid rgba(255,255,255,0.1);">
                    <div class="d-flex align-items-center gap-3">
                        <div class="rounded-3 p-2 d-flex align-items-center justify-content-center shadow-sm" style="background: rgba(239, 68, 68, 0.2); border: 1px solid rgba(248, 113, 113, 0.3); width: 40px; height: 40px;">
                            <i class="fas fa-trash-can" style="color: #f87171; font-size: 1.1rem;"></i>
                        </div>
                        <div>
                            <h5 class="modal-title fw-bold mb-0" style="color: #ffffff !important; font-size: 1.15rem;">Delete Transfer #{{ $transfer->transfer_no }}</h5>
                            <span class="small" style="color: #fca5a5; font-size: 0.8rem;">Permanently remove transfer and its line items</span>
                        </div>
                    </div>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4 bg-white">
                    <div class="alert alert-warning border-0 shadow-sm p-3 small mb-3 rounded-3" style="background: #fffbeb; color: #92400e; border-left: 4px solid #f59e0b !important;">
                        <i class="fas fa-triangle-exclamation me-1.5"></i>
                        <strong>Safe Deletion:</strong> No driver has been assigned to this transfer yet, so it can safely be removed.
                    </div>
                    <p class="text-dark mb-2">
                        Are you sure you want to permanently delete Transfer <strong>#{{ $transfer->transfer_no }}</strong>?
                    </p>
                    <div class="p-3 rounded-3 border" style="background: #f8fafc; border-color: #e2e8f0 !important;">
                        <div class="small text-muted d-block mb-1 text-uppercase" style="font-size: 0.72rem; letter-spacing: 0.04em;">Route &amp; Line Items:</div>
                        <div class="fw-bold text-dark">{{ $transfer->fromStore->name ?? 'Origin Store' }} &rarr; {{ $transfer->toStore->name ?? 'Destination Store' }}</div>
                        <div class="small text-muted mt-1">{{ $transfer->items->count() }} material line item(s) will be deleted.</div>
                    </div>
                </div>
                <div class="modal-footer py-3 px-4" style="background: #f8fafc; border-top: 1px solid #e2e8f0;">
                    <button type="button" class="btn btn-light border px-4 fw-semibold" style="color: #475569;" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn text-white px-4 fw-bold shadow-sm" style="background: linear-gradient(135deg, #dc2626 0%, #b91c1c 100%);">
                        <i class="fas fa-trash-can me-1.5"></i>Yes, Delete Transfer
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- MODAL 10: Delete Single Material Line Item --}}
<div class="modal fade" id="deleteItemModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <form id="deleteItemForm" method="POST" action="">
                @csrf
                <div class="modal-header py-3 px-4" style="background: linear-gradient(135deg, #991b1b 0%, #7f1d1d 100%); border-bottom: 1px solid rgba(255,255,255,0.1);">
                    <div class="d-flex align-items-center gap-3">
                        <div class="rounded-3 p-2 d-flex align-items-center justify-content-center shadow-sm" style="background: rgba(239, 68, 68, 0.2); border: 1px solid rgba(248, 113, 113, 0.3); width: 40px; height: 40px;">
                            <i class="fas fa-trash-can" style="color: #f87171; font-size: 1.1rem;"></i>
                        </div>
                        <div>
                            <h5 class="modal-title fw-bold mb-0" style="color: #ffffff !important; font-size: 1.15rem;">Delete Material Line</h5>
                            <span class="small" style="color: #fca5a5; font-size: 0.8rem;">Remove material row from this transfer</span>
                        </div>
                    </div>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4 bg-white">
                    <p class="text-dark mb-3">
                        Are you sure you want to remove this material line from Transfer <strong>#{{ $transfer->transfer_no }}</strong>?
                    </p>
                    <div class="p-3 rounded-3 border mb-3" style="background: #f8fafc; border-color: #e2e8f0 !important;">
                        <div class="text-uppercase fw-bold text-muted small" style="font-size: 0.72rem; letter-spacing: 0.04em;">Material To Remove:</div>
                        <div class="fw-bold fs-6 text-dark mt-1" id="delItemProductName">—</div>
                        <div class="small text-muted mt-1">Quantity: <strong class="text-dark" id="delItemQuantity">—</strong></div>
                    </div>
                    <div class="small text-muted">
                        <i class="fas fa-info-circle me-1"></i>Note: If this is the only material item in this transfer, deleting it will also delete the transfer record.
                    </div>
                </div>
                <div class="modal-footer py-3 px-4" style="background: #f8fafc; border-top: 1px solid #e2e8f0;">
                    <button type="button" class="btn btn-light border px-4 fw-semibold" style="color: #475569;" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn text-white px-4 fw-bold shadow-sm" style="background: linear-gradient(135deg, #dc2626 0%, #b91c1c 100%);">
                        <i class="fas fa-trash-can me-1.5"></i>Confirm Delete
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif

<script>
document.addEventListener('DOMContentLoaded', function () {
    // Dynamic Row Add in Material Work Adjustment Modal
    let nextItemIdx = {{ $transfer->items->count() + 10 }};
    const btnAddAdjustmentItem = document.getElementById('btnAddAdjustmentItem');
    const adjustmentTableBody = document.getElementById('adjustmentTableBody');

    if (btnAddAdjustmentItem && adjustmentTableBody) {
        btnAddAdjustmentItem.addEventListener('click', function () {
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td>
                    <select name="items[${nextItemIdx}][product_id]" class="form-select form-select-sm" required>
                        <option value="">-- Select Material --</option>
                        @foreach($products as $p)
                            <option value="{{ $p->id }}">{{ $p->name }} {{ $p->code ? '('.$p->code.')' : '' }}</option>
                        @endforeach
                    </select>
                </td>
                <td>
                    <input type="number" step="0.001" min="0.001" name="items[${nextItemIdx}][requested_quantity]" class="form-control form-control-sm text-end fw-bold" placeholder="0.00" required>
                </td>
                <td>
                    <input type="text" name="items[${nextItemIdx}][unit]" class="form-control form-control-sm text-center" value="pcs" required>
                </td>
                <td class="text-center">
                    <button type="button" class="btn btn-xs btn-outline-danger btn-sm py-0 px-2 btn-remove-row">
                        <i class="fas fa-times"></i>
                    </button>
                </td>
            `;
            adjustmentTableBody.appendChild(tr);
            nextItemIdx++;

            tr.querySelector('.btn-remove-row').addEventListener('click', function () {
                tr.remove();
            });
        });
    }

    // Split / Move Item Modal trigger
    document.querySelectorAll('.btn-move-item').forEach(btn => {
        btn.addEventListener('click', function () {
            const itemId = this.dataset.itemId;
            const productName = this.dataset.productName;
            const productCode = this.dataset.productCode;
            const quantity = this.dataset.quantity;
            const unit = this.dataset.unit;

            document.getElementById('moveItemId').value = itemId;
            document.getElementById('moveItemTitle').textContent = productName + (productCode ? ' (' + productCode + ')' : '');
            document.getElementById('moveItemAvailQty').textContent = parseFloat(quantity).toLocaleString() + ' ' + unit;
            document.getElementById('moveItemQtyInput').value = quantity;
            document.getElementById('moveItemQtyInput').max = quantity;
            document.getElementById('moveItemUnitBadge').textContent = unit;

            const modal = new bootstrap.Modal(document.getElementById('moveItemModal'));
            modal.show();
        });
    });

    // Target Selection Card Highlight Sync in Move Item Modal
    const radioExisting = document.getElementById('targetModeExisting');
    const radioNew = document.getElementById('targetModeNew');
    const cardExisting = document.getElementById('cardExistingTarget');
    const cardNew = document.getElementById('cardNewTarget');
    const wrapperExisting = document.getElementById('wrapperExistingTarget');
    const wrapperNew = document.getElementById('wrapperNewTarget');

    function syncMoveCardStyles() {
        if (cardExisting && radioExisting) {
            if (radioExisting.checked) {
                cardExisting.style.background = '#f0f7ff';
                cardExisting.style.border = '2px solid #3b82f6';
            } else {
                cardExisting.style.background = '#ffffff';
                cardExisting.style.border = '1.5px solid #e2e8f0';
            }
        }
        if (cardNew && radioNew) {
            if (radioNew.checked) {
                cardNew.style.background = '#f0f7ff';
                cardNew.style.border = '2px solid #3b82f6';
            } else {
                cardNew.style.background = '#ffffff';
                cardNew.style.border = '1.5px solid #e2e8f0';
            }
        }
    }

    if (wrapperExisting && radioExisting) {
        wrapperExisting.addEventListener('click', function (e) {
            if (e.target.id !== 'existingTargetSelect' && !e.target.closest('#existingTargetSelect')) {
                radioExisting.checked = true;
                syncMoveCardStyles();
            }
        });
    }

    if (wrapperNew && radioNew) {
        wrapperNew.addEventListener('click', function () {
            radioNew.checked = true;
            syncMoveCardStyles();
        });
    }

    if (radioExisting) radioExisting.addEventListener('change', syncMoveCardStyles);
    if (radioNew) radioNew.addEventListener('change', syncMoveCardStyles);

    // Single item delete modal trigger
    document.querySelectorAll('.btn-delete-single-item').forEach(btn => {
        btn.addEventListener('click', function () {
            const productName = this.dataset.productName;
            const quantity = this.dataset.quantity;
            const deleteUrl = this.dataset.deleteUrl;

            const nameEl = document.getElementById('delItemProductName');
            const qtyEl = document.getElementById('delItemQuantity');
            const formEl = document.getElementById('deleteItemForm');

            if (nameEl) nameEl.textContent = productName;
            if (qtyEl) qtyEl.textContent = quantity;
            if (formEl) formEl.action = deleteUrl;

            const modal = new bootstrap.Modal(document.getElementById('deleteItemModal'));
            modal.show();
        });
    });
});
</script>
@endif


<script>
document.addEventListener('DOMContentLoaded', function () {
    @if(request('dispatch') == 1 || session('open_dispatch'))
        var dispatchModalEl = document.getElementById('dispatchModal');
        if (dispatchModalEl) {
            var dModal = new bootstrap.Modal(dispatchModalEl);
            dModal.show();
        }
    @endif

    @if(request('receive') == 1 || session('open_receive'))
        var receiveModalEl = document.getElementById('receiveModal');
        if (receiveModalEl) {
            var rModal = new bootstrap.Modal(receiveModalEl);
            rModal.show();
        }
    @endif
});
</script>
@endsection
