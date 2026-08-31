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
            <div class="d-flex gap-2">
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
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($transfer->items as $item)
                        @php
                            $variance = ($item->received_quantity > 0) ? ($item->received_quantity - $item->sent_quantity) : 0;
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
                        </tr>
                        @empty
                        <tr>
                            <td colspan="8" class="text-center py-4 text-muted">No items found in this transfer record</td>
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
    <div class="modal-dialog">
        <div class="modal-content border-0 shadow">
            <form action="{{ route('store-manager.transfers.assign-driver', $transfer) }}" method="POST">
                @csrf
                <div class="modal-header bg-warning text-dark">
                    <h5 class="modal-title fw-bold"><i class="fas fa-id-badge me-2"></i>Assign Driver &amp; Vehicle</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted small mb-3">
                        Select an active driver to transport materials from <strong>{{ $transfer->fromStore->name ?? 'Origin Store' }}</strong> to <strong>{{ $transfer->toStore->name ?? 'Destination Store' }}</strong>. An automated SMS notification with transfer details will be sent to the driver.
                    </p>

                    <div class="mb-3">
                        <label class="form-label fw-bold small">Driver <span class="text-danger">*</span></label>
                        <select name="driver_employee_id" class="form-select" required>
                            <option value="">-- Select Driver --</option>
                            @foreach($drivers as $drv)
                                <option value="{{ $drv->id }}" {{ old('driver_employee_id', $transfer->driver_employee_id) == $drv->id ? 'selected' : '' }}>
                                    {{ $drv->full_name }} {{ $drv->phone ? '('.$drv->phone.')' : '' }} - {{ $drv->department ?? 'General Service' }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold small">Vehicle Plate Number</label>
                        <input type="text" name="vehicle_plate_no" class="form-control" placeholder="e.g. 3-45678 AA / 2-98765 ET" value="{{ old('vehicle_plate_no', $transfer->vehicle_plate_no) }}">
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold small">Logistics &amp; Dispatch Instructions</label>
                        <textarea name="dispatch_notes" rows="2" class="form-control" placeholder="Special handling notes, delivery route, or required arrival timing...">{{ old('dispatch_notes', $transfer->dispatch_notes) }}</textarea>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning btn-sm text-dark fw-bold shadow-sm">
                        <i class="fas fa-paper-plane me-1"></i>Confirm &amp; Notify Driver
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- MODAL 2: Outgoing Store Keeper Dispatch & Slip Upload --}}
<div class="modal fade" id="dispatchModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content border-0 shadow">
            <form action="{{ route('store-manager.transfers.dispatch', $transfer) }}" method="POST" enctype="multipart/form-data">
                @csrf
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title fw-bold"><i class="fas fa-truck-fast me-2"></i>Dispatch Material &amp; Upload Outgoing Slip</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-info border-0 shadow-sm p-3 small mb-3">
                        <i class="fas fa-info-circle me-1"></i>
                        <strong>Important:</strong> Upon submitting this dispatch, the confirmed sent quantities will be <strong>automatically deducted from your store ({{ $transfer->fromStore->name ?? 'Origin Store' }}) inventory</strong>, and the status will update to <em>In Transit</em>.
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold small">Outgoing Physical Slip / Waybill # <span class="text-danger">*</span></label>
                            <input type="text" name="outgoing_slip_no" class="form-control font-monospace" placeholder="e.g. SLIP-09823 or WB-2026-44" value="{{ old('outgoing_slip_no', $transfer->outgoing_slip_no ?: $transfer->physical_slip_no) }}" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small">Vehicle Plate # (Verification)</label>
                            <input type="text" name="vehicle_plate_no" class="form-control" placeholder="e.g. 3-45678 AA" value="{{ old('vehicle_plate_no', $transfer->vehicle_plate_no) }}">
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-bold small">Attach Outgoing Physical Slip / Waybill Document (PDF / Image)</label>
                            <input type="file" name="outgoing_slip_file" class="form-control" accept=".jpeg,.jpg,.png,.pdf,.webp">
                            <small class="text-muted">Upload a clear photo or scanned copy of the signed physical outgoing paper slip.</small>
                        </div>
                    </div>

                    <h6 class="fw-bold text-dark border-bottom pb-2 mb-3 mt-4">Confirm Sent Quantities</h6>
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered align-middle mb-0">
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
                                        <strong>{{ $item->product->name ?? 'Item' }}</strong>
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
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm shadow-sm fw-bold">
                        <i class="fas fa-truck-fast me-1"></i>Submit Dispatch &amp; Deduct Stock
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- MODAL 3: Incoming Store Keeper Inspect & Receive Materials --}}
<div class="modal fade" id="receiveModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content border-0 shadow">
            <form action="{{ route('store-manager.transfers.receive', $transfer) }}" method="POST" enctype="multipart/form-data">
                @csrf
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title fw-bold"><i class="fas fa-box-open me-2"></i>Inspect &amp; Receive Materials into Store</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-success border-0 shadow-sm p-3 small mb-3">
                        <i class="fas fa-check-circle me-1"></i>
                        <strong>Stock Inflow:</strong> Upon confirming receipt, the verified quantities will be <strong>automatically added to your store ({{ $transfer->toStore->name ?? 'Destination Store' }}) inventory</strong>, completing this transfer.
                    </div>

                    {{-- Outgoing Slip Inspection Preview --}}
                    @if($transfer->outgoing_slip_url || $transfer->outgoing_slip_no || $transfer->physical_slip_no)
                    <div class="card border-0 bg-light p-3 mb-3 rounded-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <span class="text-muted small d-block">Origin Store Waybill / Slip #:</span>
                                <strong class="font-monospace text-dark">{{ $transfer->outgoing_slip_no ?: $transfer->physical_slip_no }}</strong>
                            </div>
                            @if($transfer->outgoing_slip_url)
                                <a href="{{ $transfer->outgoing_slip_url }}" target="_blank" class="btn btn-sm btn-outline-primary">
                                    <i class="fas fa-external-link-alt me-1"></i>Open Attached Outgoing Slip
                                </a>
                            @endif
                        </div>
                    </div>
                    @endif

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold small">Signed Receiving Slip / GRN # (Optional)</label>
                            <input type="text" name="receiving_slip_no" class="form-control font-monospace" placeholder="e.g. GRN-2026-99" value="{{ old('receiving_slip_no', $transfer->receiving_slip_no) }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small">Attach Signed Receiving Document (Optional)</label>
                            <input type="file" name="receiving_slip_file" class="form-control" accept=".jpeg,.jpg,.png,.pdf,.webp">
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-bold small">Inspection / Receiving Notes</label>
                            <textarea name="receiving_notes" rows="2" class="form-control" placeholder="Condition of materials, remarks, or notes...">{{ old('receiving_notes', $transfer->receiving_notes) }}</textarea>
                        </div>
                    </div>

                    <h6 class="fw-bold text-dark border-bottom pb-2 mb-3 mt-4">Verify Received Quantities</h6>
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered align-middle mb-0">
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
                                        <strong>{{ $item->product->name ?? 'Item' }}</strong>
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
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success btn-sm shadow-sm fw-bold">
                        <i class="fas fa-check-circle me-1"></i>Confirm Receipt &amp; Add Stock to Inventory
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- MODAL 4: Quick Edit Physical Slip # --}}
<div class="modal fade" id="physicalSlipModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content border-0 shadow">
            <form action="{{ route('store-manager.transfers.physical-slip', $transfer) }}" method="POST">
                @csrf
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title fw-bold"><i class="fa-solid fa-file-invoice me-2"></i>Physical Slip / Waybill Number</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-bold small">Physical Slip Number <span class="text-danger">*</span></label>
                        <input type="text" name="physical_slip_no" class="form-control font-monospace" placeholder="e.g. SLIP-09823 or WB-2026-44" value="{{ old('physical_slip_no', $transfer->physical_slip_no ?: $transfer->outgoing_slip_no) }}" required>
                        <small class="text-muted">Enter the physical paper slip or delivery receipt number that accompanies this transfer.</small>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm fw-bold"><i class="fa-solid fa-save me-1"></i>Save Slip #</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- MODAL 5: Reject Transfer --}}
<div class="modal fade" id="rejectModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content border-0 shadow">
            <form action="{{ route('store-manager.transfers.reject', $transfer) }}" method="POST">
                @csrf
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title fw-bold"><i class="fas fa-ban me-2"></i>Reject Transfer</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-bold small">Rejection Reason <span class="text-danger">*</span></label>
                        <textarea name="rejection_reason" rows="3" class="form-control" placeholder="Specify reason for cancelling or rejecting this transfer..." required></textarea>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger btn-sm fw-bold">Confirm Rejection</button>
                </div>
            </form>
        </div>
    </div>
</div>

@endsection
