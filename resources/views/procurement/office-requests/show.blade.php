@extends('layouts.app')

@section('title', 'Office Request ' . $officeRequest->request_no)

@section('content')
<div class="container-fluid py-4">
    <!-- Header -->
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-1">
                    <li class="breadcrumb-item"><a href="{{ route('office-requests.index') }}">Office Material Requests</a></li>
                    <li class="breadcrumb-item active" aria-current="page">{{ $officeRequest->request_no }}</li>
                </ol>
            </nav>
            <div class="d-flex align-items-center gap-3">
                <h3 class="fw-bold mb-0 text-dark">{{ $officeRequest->request_no }}</h3>
                {!! $officeRequest->status_badge['badge'] !!}
                @if($officeRequest->urgency === 'urgent')
                    <span class="badge bg-warning text-dark">Urgent</span>
                @elseif($officeRequest->urgency === 'emergency')
                    <span class="badge bg-danger text-white">Emergency</span>
                @endif
            </div>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('office-requests.index') }}" class="btn btn-outline-secondary">
                <i class="fa-solid fa-arrow-left me-1"></i> Back to List
            </a>
            @if($officeRequest->status !== \App\Models\OfficeMaterialRequest::STATUS_PAID && $officeRequest->status !== \App\Models\OfficeMaterialRequest::STATUS_REJECTED && ($isHr || $isFinance))
                <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#rejectModal">
                    <i class="fa-solid fa-ban me-1"></i> Reject
                </button>
            @endif
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fa-solid fa-circle-check me-2"></i>{{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fa-solid fa-circle-exclamation me-2"></i>{{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <!-- ═════════════════════════════════════════════════════════════ -->
    <!-- 4-STEP VISUAL WORKFLOW PROGRESS BAR                          -->
    <!-- ═════════════════════════════════════════════════════════════ -->
    <div class="card border-0 shadow-sm rounded-4 mb-4">
        <div class="card-body p-4">
            <div class="row text-center g-3 position-relative">
                <!-- Step 1 -->
                <div class="col-3">
                    <div class="d-inline-flex align-items-center justify-content-center rounded-circle bg-success text-white mb-2" style="width: 44px; height: 44px;">
                        <i class="fa-solid fa-check"></i>
                    </div>
                    <div class="fw-bold small text-dark">1. Requisition</div>
                    <div class="text-muted" style="font-size: 0.75rem;">{{ $officeRequest->requestedBy?->name ?? 'Secretary' }}</div>
                    <div class="text-muted" style="font-size: 0.7rem;">{{ $officeRequest->created_at ? $officeRequest->created_at->format('M d, Y') : '' }}</div>
                </div>

                <!-- Step 2 -->
                <div class="col-3">
                    @php $step2Done = in_array($officeRequest->status, [\App\Models\OfficeMaterialRequest::STATUS_APPROVED_BY_HR, \App\Models\OfficeMaterialRequest::STATUS_ASSIGNED_TO_FINANCE, \App\Models\OfficeMaterialRequest::STATUS_PAID]); @endphp
                    <div class="d-inline-flex align-items-center justify-content-center rounded-circle {{ $step2Done ? 'bg-success text-white' : ($officeRequest->status === \App\Models\OfficeMaterialRequest::STATUS_PENDING_HR ? 'bg-warning text-dark ring' : 'bg-light text-muted border') }} mb-2" style="width: 44px; height: 44px;">
                        <i class="fa-solid {{ $step2Done ? 'fa-check' : 'fa-money-bill-wave' }}"></i>
                    </div>
                    <div class="fw-bold small {{ $step2Done ? 'text-success' : ($officeRequest->status === \App\Models\OfficeMaterialRequest::STATUS_PENDING_HR ? 'text-warning fw-bold' : 'text-muted') }}">2. HR Money Approval</div>
                    @if($officeRequest->amount !== null)
                        <div class="fw-bold text-success" style="font-size: 0.8rem;">ETB {{ number_format((float)$officeRequest->amount, 2) }}</div>
                    @else
                        <div class="text-muted" style="font-size: 0.75rem;">Awaiting Review</div>
                    @endif
                </div>

                <!-- Step 3 -->
                <div class="col-3">
                    @php $step3Done = in_array($officeRequest->status, [\App\Models\OfficeMaterialRequest::STATUS_ASSIGNED_TO_FINANCE, \App\Models\OfficeMaterialRequest::STATUS_PAID]); @endphp
                    <div class="d-inline-flex align-items-center justify-content-center rounded-circle {{ $step3Done ? 'bg-success text-white' : ($officeRequest->status === \App\Models\OfficeMaterialRequest::STATUS_APPROVED_BY_HR ? 'text-white' : 'bg-light text-muted border') }} mb-2" style="width: 44px; height: 44px; {{ $officeRequest->status === \App\Models\OfficeMaterialRequest::STATUS_APPROVED_BY_HR ? 'background:#7c3aed;' : '' }}">
                        <i class="fa-solid {{ $step3Done ? 'fa-check' : 'fa-user-gear' }}"></i>
                    </div>
                    <div class="fw-bold small {{ $step3Done ? 'text-success' : ($officeRequest->status === \App\Models\OfficeMaterialRequest::STATUS_APPROVED_BY_HR ? 'text-purple fw-bold' : 'text-muted') }}">3. Finance Assignment</div>
                    <div class="text-muted" style="font-size: 0.75rem;">
                        {{ $officeRequest->assignedStaff ? 'Assigned: ' . $officeRequest->assignedStaff->name : ($step2Done ? 'Finance Queue' : 'Pending') }}
                    </div>
                </div>

                <!-- Step 4 -->
                <div class="col-3">
                    @php $step4Done = ($officeRequest->status === \App\Models\OfficeMaterialRequest::STATUS_PAID); @endphp
                    <div class="d-inline-flex align-items-center justify-content-center rounded-circle {{ $step4Done ? 'bg-success text-white' : 'bg-light text-muted border' }} mb-2" style="width: 44px; height: 44px;">
                        <i class="fa-solid {{ $step4Done ? 'fa-check-double' : 'fa-hand-holding-dollar' }}"></i>
                    </div>
                    <div class="fw-bold small {{ $step4Done ? 'text-success' : 'text-muted' }}">4. Payment &amp; Completed</div>
                    <div class="text-muted" style="font-size: 0.75rem;">
                        {{ $step4Done ? 'Paid (' . ($officeRequest->paid_at ? $officeRequest->paid_at->format('M d') : 'Done') . ')' : 'Pending Disburse' }}
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <!-- Left Column: Details & Items -->
        <div class="col-lg-8">
            <!-- Requisition Summary Card -->
            <div class="card border-0 shadow-sm rounded-4 mb-4">
                <div class="card-header bg-white border-bottom py-3">
                    <h6 class="fw-bold mb-0 text-dark"><i class="fa-solid fa-circle-info text-primary me-2"></i>Requisition Information</h6>
                </div>
                <div class="card-body p-4">
                    <div class="row g-3">
                        <div class="col-sm-6 col-md-4">
                            <div class="text-muted small text-uppercase">Purpose / Category</div>
                            <div class="fw-bold text-dark mt-1">{{ $officeRequest->office_purpose }}</div>
                        </div>
                        <div class="col-sm-6 col-md-4">
                            <div class="text-muted small text-uppercase">Requested By</div>
                            <div class="fw-bold text-dark mt-1">{{ $officeRequest->requestedBy?->name ?? 'Secretary' }}</div>
                            <div class="text-muted small">{{ $officeRequest->requestedBy?->email }}</div>
                        </div>
                        <div class="col-sm-6 col-md-4">
                            <div class="text-muted small text-uppercase">Date Requested</div>
                            <div class="fw-bold text-dark mt-1">{{ $officeRequest->created_at ? $officeRequest->created_at->format('M d, Y h:i A') : '-' }}</div>
                        </div>
                        <div class="col-sm-6 col-md-4">
                            <div class="text-muted small text-uppercase">Required By Date</div>
                            <div class="fw-bold text-dark mt-1">{{ $officeRequest->required_date ? $officeRequest->required_date->format('M d, Y') : 'Immediate' }}</div>
                        </div>
                        <div class="col-sm-6 col-md-4">
                            <div class="text-muted small text-uppercase">Department / Location</div>
                            <div class="fw-bold text-dark mt-1"><i class="fa-solid fa-building me-1 text-primary"></i> Head Office (ዋና ቢሮ)</div>
                        </div>
                        <div class="col-sm-6 col-md-4">
                            <div class="text-muted small text-uppercase">Approved Budget</div>
                            <div class="fw-bold text-success fs-5 mt-1">
                                {{ $officeRequest->amount !== null ? 'ETB ' . number_format((float)$officeRequest->amount, 2) : 'Pending' }}
                            </div>
                        </div>
                        @if($officeRequest->justification)
                            <div class="col-12 border-top pt-3">
                                <div class="text-muted small text-uppercase mb-1">Justification / Reason (ዝርዝር ምክንያት)</div>
                                <div class="p-3 bg-light rounded-3 text-dark">{{ $officeRequest->justification }}</div>
                            </div>
                        @endif
                        @if($officeRequest->attachment)
                            <div class="col-12 border-top pt-3">
                                <div class="text-muted small text-uppercase mb-1">Supporting Document / Attachment</div>
                                <a href="{{ \Illuminate\Support\Facades\Storage::url($officeRequest->attachment) }}" target="_blank" class="btn btn-outline-primary btn-sm rounded-pill px-3">
                                    <i class="fa-solid fa-paperclip me-1"></i> View Attachment
                                </a>
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Items Table -->
            <div class="card border-0 shadow-sm rounded-4 mb-4">
                <div class="card-header bg-white border-bottom py-3">
                    <h6 class="fw-bold mb-0 text-dark">
                        <i class="fa-solid fa-boxes-stacked text-primary me-2"></i>Requested Materials &amp; Items ({{ $officeRequest->items->count() }})
                    </h6>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light text-secondary text-uppercase small" style="font-size: 0.78rem;">
                                <tr>
                                    <th class="ps-4 py-3">#</th>
                                    <th>Item Description</th>
                                    <th class="text-end">Quantity</th>
                                    <th>Unit</th>
                                    <th>Specifications</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($officeRequest->items as $idx => $item)
                                    <tr>
                                        <td class="ps-4 text-muted">{{ $idx + 1 }}</td>
                                        <td>
                                            <div class="fw-bold text-dark">{{ $item->item_name }}</div>
                                            @if($item->product && $item->product->code)
                                                <small class="text-muted">Code: {{ $item->product->code }}</small>
                                            @endif
                                        </td>
                                        <td class="text-end fw-bold text-primary fs-6">
                                            {{ number_format((float)$item->quantity, 2) }}
                                        </td>
                                        <td>
                                            <span class="badge bg-light text-dark border">{{ $item->unit }}</span>
                                        </td>
                                        <td>
                                            <span class="text-muted small">{{ $item->specifications ?: 'Standard' }}</span>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right Column: Interactive Action Box & Timeline -->
        <div class="col-lg-4">

            {{-- ═══════════════════════════════════════════════════ --}}
            {{-- STEP 2 ACTION BOX: HR MONEY APPROVAL                --}}
            {{-- ═══════════════════════════════════════════════════ --}}
            @if($officeRequest->status === \App\Models\OfficeMaterialRequest::STATUS_PENDING_HR)
                <div class="card border-0 shadow-sm rounded-4 mb-4 border-top border-warning border-4">
                    <div class="card-header bg-white py-3">
                        <h6 class="fw-bold mb-0 text-warning">
                            <i class="fa-solid fa-gavel me-2"></i>Step 2: HR Money Approval
                        </h6>
                    </div>
                    <div class="card-body p-4">
                        @if($isHr)
                            <form method="POST" action="{{ route('office-requests.hr-approve', $officeRequest->id) }}">
                                @csrf
                                <div class="mb-3">
                                    <label class="form-label fw-bold text-dark small text-uppercase">Approved Amount (ETB) <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <span class="input-group-text bg-light border-0 fw-bold">ETB</span>
                                        <input type="number" name="amount" class="form-control bg-light border-0 fw-bold fs-5 text-success" step="0.01" min="0.01" placeholder="e.g. 4500.00" required>
                                    </div>
                                    <div class="form-text small">Enter approved budget for these materials.</div>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label fw-bold text-dark small text-uppercase">HR Notes / Remarks</label>
                                    <textarea name="hr_notes" rows="3" class="form-control bg-light border-0" placeholder="e.g. Approved standard office items..."></textarea>
                                </div>

                                <button type="submit" class="btn btn-warning w-100 fw-bold py-2 shadow-sm rounded-pill">
                                    <i class="fa-solid fa-check me-1"></i> Approve &amp; Send to Finance
                                </button>
                            </form>
                        @else
                            <div class="alert alert-warning py-3 mb-0 small">
                                <i class="fa-solid fa-clock me-1"></i> This request is currently awaiting HR / Coordinator money review &amp; approval.
                            </div>
                        @endif
                    </div>
                </div>
            @endif

            {{-- ═══════════════════════════════════════════════════ --}}
            {{-- STEP 3 & 4 ACTION BOX: FINANCE ASSIGN & PAY         --}}
            {{-- ═══════════════════════════════════════════════════ --}}
            @if(in_array($officeRequest->status, [\App\Models\OfficeMaterialRequest::STATUS_APPROVED_BY_HR, \App\Models\OfficeMaterialRequest::STATUS_ASSIGNED_TO_FINANCE]))
                <div class="card border-0 shadow-sm rounded-4 mb-4 border-top border-4" style="border-color:#7c3aed !important;">
                    <div class="card-header bg-white py-3">
                        <h6 class="fw-bold mb-0" style="color:#7c3aed;">
                            <i class="fa-solid fa-wallet me-2"></i>Finance Decision &amp; Payment
                        </h6>
                    </div>
                    <div class="card-body p-4">
                        <div class="p-3 bg-light rounded-3 mb-3">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <span class="text-muted small text-uppercase">Approved Budget:</span>
                                <strong class="text-success fs-5">ETB {{ number_format((float)$officeRequest->amount, 2) }}</strong>
                            </div>
                            @if($officeRequest->hrReviewer)
                                <div class="text-muted small">Approved by: <strong>{{ $officeRequest->hrReviewer->name }}</strong></div>
                            @endif
                        </div>

                        @if($isFinance)
                            <!-- Quick Assign Form -->
                            <form method="POST" action="{{ route('office-requests.finance-assign', $officeRequest->id) }}" class="mb-3">
                                @csrf
                                <div class="mb-2">
                                    <label class="form-label fw-bold text-dark small text-uppercase">Funding Account <span class="text-danger">*</span></label>
                                    <select name="coa_id" class="form-select form-select-sm bg-light border-0" required>
                                        <option value="" disabled selected>-- Select Chart of Account --</option>
                                        @foreach($coaAccounts as $coa)
                                            <option value="{{ $coa->id }}" {{ $officeRequest->coa_id == $coa->id ? 'selected' : '' }}>
                                                [{{ $coa->code }}] {{ $coa->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="mb-2">
                                    <label class="form-label fw-bold text-dark small text-uppercase">Bank Account</label>
                                    <select name="bank_account_id" class="form-select form-select-sm bg-light border-0">
                                        <option value="">-- Optional Bank --</option>
                                        @foreach($bankAccounts as $bank)
                                            <option value="{{ $bank->id }}" {{ $officeRequest->bank_account_id == $bank->id ? 'selected' : '' }}>
                                                {{ $bank->bank_name }} ({{ $bank->account_number }})
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label fw-bold text-dark small text-uppercase">Assign Staff</label>
                                    <select name="assigned_finance_staff_id" class="form-select form-select-sm bg-light border-0">
                                        <option value="">-- Assign Staff / Self --</option>
                                        @foreach($financeStaff as $staff)
                                            <option value="{{ $staff->id }}" {{ $officeRequest->assigned_finance_staff_id == $staff->id ? 'selected' : '' }}>
                                                {{ $staff->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <button type="submit" class="btn btn-sm text-white w-100 fw-bold py-2 rounded-pill shadow-sm mb-3" style="background:#7c3aed;">
                                    <i class="fa-solid fa-user-check me-1"></i> Update Assignment
                                </button>
                            </form>

                            <hr class="my-3">

                            <!-- Quick Pay Form -->
                            <form method="POST" action="{{ route('office-requests.mark-paid', $officeRequest->id) }}">
                                @csrf
                                <div class="mb-2">
                                    <label class="form-label fw-bold text-dark small text-uppercase">Payment Voucher Ref</label>
                                    <input type="text" name="payment_reference" class="form-control form-control-sm bg-light border-0" placeholder="e.g. VC-2026-08-001">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label fw-bold text-dark small text-uppercase">Disbursement Notes</label>
                                    <input type="text" name="payment_notes" class="form-control form-control-sm bg-light border-0" placeholder="e.g. Paid from Petty Cash">
                                </div>
                                <button type="submit" class="btn btn-success w-100 fw-bold py-2 rounded-pill shadow-sm">
                                    <i class="fa-solid fa-circle-check me-1"></i> Disburse &amp; Mark Paid
                                </button>
                            </form>
                        @else
                            <div class="alert py-3 mb-0 small" style="background:#ede9fe; color:#5b21b6;">
                                <i class="fa-solid fa-clock me-1"></i> Requisition has been approved by HR with budget <strong>ETB {{ number_format((float)$officeRequest->amount, 2) }}</strong> and is pending Finance Head disbursement.
                            </div>
                        @endif
                    </div>
                </div>
            @endif

            {{-- ═══════════════════════════════════════════════════ --}}
            {{-- STEP 4: PAID & COMPLETED INFO                       --}}
            {{-- ═══════════════════════════════════════════════════ --}}
            @if($officeRequest->status === \App\Models\OfficeMaterialRequest::STATUS_PAID)
                <div class="card border-0 shadow-sm rounded-4 mb-4 border-top border-success border-4 bg-white">
                    <div class="card-header bg-white py-3">
                        <h6 class="fw-bold mb-0 text-success"><i class="fa-solid fa-circle-check me-2"></i>Payment Disbursed &amp; Completed</h6>
                    </div>
                    <div class="card-body p-4">
                        <div class="p-3 bg-success bg-opacity-10 rounded-3 mb-3 border border-success border-opacity-25">
                            <div class="text-muted small text-uppercase">Total Disbursed Amount</div>
                            <div class="fw-bold text-success fs-4 mt-1">ETB {{ number_format((float)$officeRequest->amount, 2) }}</div>
                        </div>
                        <ul class="list-unstyled mb-0 small text-muted">
                            <li class="mb-2"><i class="fa-solid fa-user-check me-2 text-success"></i> Paid by: <strong class="text-dark">{{ $officeRequest->paidBy?->name ?? 'Finance' }}</strong></li>
                            <li class="mb-2"><i class="fa-solid fa-calendar me-2 text-success"></i> Date: <strong class="text-dark">{{ $officeRequest->paid_at ? $officeRequest->paid_at->format('M d, Y h:i A') : '-' }}</strong></li>
                            @if($officeRequest->payment_reference)
                                <li class="mb-2"><i class="fa-solid fa-receipt me-2 text-success"></i> Reference: <strong class="text-dark">{{ $officeRequest->payment_reference }}</strong></li>
                            @endif
                            @if($officeRequest->coa)
                                <li class="mb-2"><i class="fa-solid fa-sitemap me-2 text-success"></i> Account: <strong class="text-dark">[{{ $officeRequest->coa->code }}] {{ $officeRequest->coa->name }}</strong></li>
                            @endif
                            @if($officeRequest->bankAccount)
                                <li class="mb-2"><i class="fa-solid fa-building-columns me-2 text-success"></i> Bank: <strong class="text-dark">{{ $officeRequest->bankAccount->bank_name }}</strong></li>
                            @endif
                            @if($officeRequest->payment_notes)
                                <li class="p-2 bg-light rounded text-dark mt-2">Notes: {{ $officeRequest->payment_notes }}</li>
                            @endif
                        </ul>
                    </div>
                </div>
            @endif

            {{-- ═══════════════════════════════════════════════════ --}}
            {{-- REJECTED INFO                                       --}}
            {{-- ═══════════════════════════════════════════════════ --}}
            @if($officeRequest->status === \App\Models\OfficeMaterialRequest::STATUS_REJECTED)
                <div class="card border-0 shadow-sm rounded-4 mb-4 border-top border-danger border-4 bg-white">
                    <div class="card-header bg-white py-3">
                        <h6 class="fw-bold mb-0 text-danger"><i class="fa-solid fa-circle-xmark me-2"></i>Request Rejected</h6>
                    </div>
                    <div class="card-body p-4">
                        <div class="text-muted small text-uppercase">Rejection Reason</div>
                        <div class="p-3 bg-danger bg-opacity-10 text-danger rounded-3 mt-1 fw-semibold">
                            {{ $officeRequest->rejection_reason ?: 'Request was rejected.' }}
                        </div>
                        <div class="small text-muted mt-2">
                            Rejected by {{ $officeRequest->rejectedBy?->name ?? 'Approver' }} on {{ $officeRequest->rejected_at ? $officeRequest->rejected_at->format('M d, Y h:i A') : '-' }}
                        </div>
                    </div>
                </div>
            @endif

            <!-- Timeline Card -->
            <div class="card border-0 shadow-sm rounded-4 mb-4">
                <div class="card-header bg-white border-bottom py-3">
                    <h6 class="fw-bold mb-0 text-dark"><i class="fa-solid fa-timeline text-primary me-2"></i>Workflow Timeline</h6>
                </div>
                <div class="card-body p-4">
                    <ul class="list-unstyled mb-0 position-relative">
                        <!-- Step 1 Submission -->
                        <li class="d-flex gap-3 pb-3 border-start border-2 border-primary ps-3 ms-2 position-relative">
                            <span class="position-absolute top-0 start-0 translate-middle bg-primary rounded-circle p-1" style="width: 12px; height: 12px;"></span>
                            <div>
                                <div class="fw-bold small text-dark">Office Request Submitted</div>
                                <div class="text-muted" style="font-size: 0.75rem;">By {{ $officeRequest->requestedBy?->name ?? 'Secretary' }} &bull; {{ $officeRequest->created_at ? $officeRequest->created_at->format('M d, Y h:i A') : '' }}</div>
                            </div>
                        </li>

                        <!-- Step 2 HR Review -->
                        @if($officeRequest->hr_reviewed_at)
                            <li class="d-flex gap-3 pb-3 border-start border-2 border-warning ps-3 ms-2 position-relative">
                                <span class="position-absolute top-0 start-0 translate-middle bg-warning rounded-circle p-1" style="width: 12px; height: 12px;"></span>
                                <div>
                                    <div class="fw-bold small text-dark">Approved by HR / Coordinator</div>
                                    <div class="text-muted" style="font-size: 0.75rem;">By {{ $officeRequest->hrReviewer?->name ?? 'HR' }} &bull; {{ $officeRequest->hr_reviewed_at->format('M d, Y h:i A') }}</div>
                                    <div class="text-success fw-bold small mt-1">Budget: ETB {{ number_format((float)$officeRequest->amount, 2) }}</div>
                                    @if($officeRequest->hr_notes)
                                        <div class="p-2 bg-light rounded mt-1 small text-dark">{{ $officeRequest->hr_notes }}</div>
                                    @endif
                                </div>
                            </li>
                        @endif

                        <!-- Step 3 Finance Assignment -->
                        @if($officeRequest->finance_assigned_at)
                            <li class="d-flex gap-3 pb-3 border-start border-2 ps-3 ms-2 position-relative" style="border-color:#7c3aed !important;">
                                <span class="position-absolute top-0 start-0 translate-middle rounded-circle p-1" style="width: 12px; height: 12px; background:#7c3aed;"></span>
                                <div>
                                    <div class="fw-bold small text-dark">Assigned in Finance Hub</div>
                                    <div class="text-muted" style="font-size: 0.75rem;">By {{ $officeRequest->financeHead?->name ?? 'Finance Head' }} &bull; {{ $officeRequest->finance_assigned_at->format('M d, Y h:i A') }}</div>
                                    @if($officeRequest->assignedStaff)
                                        <div class="text-muted small">Assigned Staff: <strong>{{ $officeRequest->assignedStaff->name }}</strong></div>
                                    @endif
                                    @if($officeRequest->coa)
                                        <div class="text-muted small">Account: <strong>{{ $officeRequest->coa->name }}</strong></div>
                                    @endif
                                </div>
                            </li>
                        @endif

                        <!-- Step 4 Paid -->
                        @if($officeRequest->paid_at)
                            <li class="d-flex gap-3 pb-0 border-start border-2 border-success ps-3 ms-2 position-relative">
                                <span class="position-absolute top-0 start-0 translate-middle bg-success rounded-circle p-1" style="width: 12px; height: 12px;"></span>
                                <div>
                                    <div class="fw-bold small text-success">Payment Disbursed &amp; Completed</div>
                                    <div class="text-muted" style="font-size: 0.75rem;">By {{ $officeRequest->paidBy?->name ?? 'Finance' }} &bull; {{ $officeRequest->paid_at->format('M d, Y h:i A') }}</div>
                                    @if($officeRequest->payment_reference)
                                        <div class="text-muted small">Ref: <strong>{{ $officeRequest->payment_reference }}</strong></div>
                                    @endif
                                </div>
                            </li>
                        @endif
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- REJECT MODAL -->
<div class="modal fade" id="rejectModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form method="POST" action="{{ route('office-requests.reject', $officeRequest->id) }}" class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            @csrf
            <div class="modal-header bg-danger text-white py-3 px-4">
                <h5 class="modal-title fw-bold mb-0"><i class="fa-solid fa-circle-xmark me-2"></i>Reject Office Request</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4 bg-white">
                <div class="mb-3">
                    <label class="form-label fw-bold text-dark small text-uppercase">Rejection Reason <span class="text-danger">*</span></label>
                    <textarea name="rejection_reason" rows="3" class="form-control bg-light border-0" placeholder="Explain why this request is rejected..." required></textarea>
                </div>
            </div>
            <div class="modal-footer bg-light border-0 py-3 px-4">
                <button type="button" class="btn btn-light rounded-pill px-3" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-danger rounded-pill px-4 fw-bold shadow-sm">
                    <i class="fa-solid fa-ban me-1"></i> Confirm Rejection
                </button>
            </div>
        </form>
    </div>
</div>

@endsection
