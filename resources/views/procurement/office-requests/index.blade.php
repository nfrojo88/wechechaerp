@extends('layouts.app')

@section('title', 'Office Material Requests')

@section('content')
<div class="container-fluid py-4">
    <!-- Header -->
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
        <div>
            <h3 class="fw-bold mb-1 text-dark">
                <i class="fa-solid fa-boxes-stacked text-primary me-2"></i>Office Material Requests
            </h3>
            <p class="text-muted small mb-0">
                Streamlined Requisition &rarr; HR Money Approval &rarr; Finance Assignment &rarr; Payment Disbursement
            </p>
        </div>
        <a href="{{ route('office-requests.create') }}" class="btn btn-primary px-4 shadow-sm fw-bold rounded-pill">
            <i class="fa-solid fa-plus me-1"></i> New Office Requisition
        </a>
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

    <!-- Workflow Stage Tabs -->
    <div class="card border-0 shadow-sm rounded-4 mb-4">
        <div class="card-body p-2">
            <ul class="nav nav-pills gap-2 flex-wrap">
                <li class="nav-item">
                    <a class="nav-link rounded-pill {{ $tab === 'all' ? 'active bg-primary text-white fw-bold shadow-sm' : 'text-dark' }}" 
                       href="{{ route('office-requests.index', ['tab' => 'all']) }}">
                        <i class="fa-solid fa-layer-group me-1"></i> All Requests
                        <span class="badge {{ $tab === 'all' ? 'bg-white text-primary' : 'bg-secondary bg-opacity-10 text-dark' }} ms-1">{{ $stats['all'] }}</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link rounded-pill {{ $tab === 'pending_hr' ? 'active bg-warning text-dark fw-bold shadow-sm' : 'text-dark' }}" 
                       href="{{ route('office-requests.index', ['tab' => 'pending_hr']) }}">
                        <i class="fa-solid fa-clock me-1 text-warning"></i> Pending HR Review
                        <span class="badge {{ $tab === 'pending_hr' ? 'bg-dark text-white' : 'bg-warning text-dark' }} ms-1">{{ $stats['pending_hr'] }}</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link rounded-pill {{ $tab === 'finance_queue' ? 'active text-white fw-bold shadow-sm' : 'text-dark' }}" 
                       style="{{ $tab === 'finance_queue' ? 'background: #7c3aed;' : '' }}"
                       href="{{ route('office-requests.index', ['tab' => 'finance_queue']) }}">
                        <i class="fa-solid fa-wallet me-1" style="color: #7c3aed;"></i> Finance Queue
                        <span class="badge {{ $tab === 'finance_queue' ? 'bg-white text-dark' : 'text-white' }}" style="{{ $tab !== 'finance_queue' ? 'background:#7c3aed;' : '' }} ms-1">{{ $stats['finance_queue'] }}</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link rounded-pill {{ $tab === 'paid' ? 'active bg-success text-white fw-bold shadow-sm' : 'text-dark' }}" 
                       href="{{ route('office-requests.index', ['tab' => 'paid']) }}">
                        <i class="fa-solid fa-circle-check me-1 text-success"></i> Paid &amp; Completed
                        <span class="badge {{ $tab === 'paid' ? 'bg-white text-success' : 'bg-success text-white' }} ms-1">{{ $stats['paid'] }}</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link rounded-pill {{ $tab === 'rejected' ? 'active bg-danger text-white fw-bold shadow-sm' : 'text-dark' }}" 
                       href="{{ route('office-requests.index', ['tab' => 'rejected']) }}">
                        <i class="fa-solid fa-circle-xmark me-1 text-danger"></i> Rejected
                        <span class="badge {{ $tab === 'rejected' ? 'bg-white text-danger' : 'bg-danger text-white' }} ms-1">{{ $stats['rejected'] }}</span>
                    </a>
                </li>
            </ul>
        </div>
    </div>

    <!-- Requests Table Card -->
    <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
        <div class="card-header bg-white border-bottom py-3 d-flex flex-wrap justify-content-between align-items-center gap-3">
            <h6 class="fw-bold mb-0 text-dark">
                <i class="fa-solid fa-list text-primary me-2"></i>Requisitions List ({{ $requests->total() }})
            </h6>

            <!-- Search -->
            <form method="GET" action="{{ route('office-requests.index') }}" class="d-flex gap-2">
                <input type="hidden" name="tab" value="{{ $tab }}">
                <div class="input-group input-group-sm">
                    <input type="text" name="search" class="form-control" placeholder="Search Req #, Purpose, Requester..." value="{{ request('search') }}">
                    <button class="btn btn-outline-secondary" type="submit"><i class="fa-solid fa-search"></i></button>
                    @if(request('search'))
                        <a href="{{ route('office-requests.index', ['tab' => $tab]) }}" class="btn btn-outline-danger"><i class="fa-solid fa-xmark"></i></a>
                    @endif
                </div>
            </form>
        </div>

        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light text-secondary text-uppercase small" style="font-size: 0.75rem;">
                        <tr>
                            <th class="ps-4 py-3">Req Number</th>
                            <th>Purpose / Category</th>
                            <th>Requested By</th>
                            <th>Items Summary</th>
                            <th>Approved Budget</th>
                            <th>Stage / Status</th>
                            <th>Date</th>
                            <th class="text-end pe-4">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($requests as $req)
                            <tr>
                                <td class="ps-4">
                                    <a href="{{ route('office-requests.show', $req->id) }}" class="fw-bold text-decoration-none text-primary">
                                        {{ $req->request_no }}
                                    </a>
                                    @if($req->urgency === 'urgent')
                                        <span class="badge bg-warning text-dark ms-1" style="font-size: 0.65rem;">Urgent</span>
                                    @elseif($req->urgency === 'emergency')
                                        <span class="badge bg-danger text-white ms-1" style="font-size: 0.65rem;">Emergency</span>
                                    @endif
                                </td>
                                <td>
                                    <div class="fw-semibold text-dark">{{ $req->office_purpose }}</div>
                                    @if($req->justification)
                                        <small class="text-muted d-block text-truncate" style="max-width: 220px;">{{ $req->justification }}</small>
                                    @endif
                                </td>
                                <td>
                                    <div class="fw-semibold text-dark">{{ $req->requestedBy?->name ?? 'Secretary' }}</div>
                                    <small class="text-muted"><i class="fa-solid fa-building me-1"></i>Head Office</small>
                                </td>
                                <td>
                                    <span class="badge bg-light text-dark border">
                                        <i class="fa-solid fa-boxes-stacked me-1 text-primary"></i>{{ $req->items->count() }} {{ \Illuminate\Support\Str::plural('item', $req->items->count()) }}
                                    </span>
                                    <div class="text-muted" style="font-size: 0.75rem;">
                                        {{ $req->items->take(2)->pluck('item_name')->implode(', ') }}{{ $req->items->count() > 2 ? '...' : '' }}
                                    </div>
                                </td>
                                <td>
                                    @if($req->amount !== null)
                                        <div class="fw-bold text-success fs-6">ETB {{ number_format((float)$req->amount, 2) }}</div>
                                        @if($req->hrReviewer)
                                            <small class="text-muted" style="font-size: 0.72rem;">Set by {{ $req->hrReviewer->name }}</small>
                                        @endif
                                    @else
                                        <span class="text-muted small fst-italic">Pending HR budget</span>
                                    @endif
                                </td>
                                <td>
                                    {!! $req->status_badge['badge'] !!}
                                    @if($req->status === \App\Models\OfficeMaterialRequest::STATUS_ASSIGNED_TO_FINANCE && $req->assignedStaff)
                                        <div class="text-muted mt-1" style="font-size: 0.72rem;">
                                            <i class="fa-solid fa-user-check me-1 text-info"></i>Staff: {{ $req->assignedStaff->name }}
                                        </div>
                                    @endif
                                </td>
                                <td>
                                    <div class="small text-muted">{{ $req->created_at ? $req->created_at->format('M d, Y') : '-' }}</div>
                                    <div class="text-muted" style="font-size: 0.7rem;">{{ $req->created_at ? $req->created_at->format('h:i A') : '' }}</div>
                                </td>
                                <td class="text-end pe-4">
                                    <div class="d-flex justify-content-end align-items-center gap-1 flex-wrap">
                                        <a href="{{ route('office-requests.show', $req->id) }}" class="btn btn-outline-primary btn-sm rounded-pill px-3" title="View Details">
                                            <i class="fa-solid fa-eye me-1"></i> View
                                        </a>

                                        {{-- Step 2 Action: HR Money Review --}}
                                        @if($req->status === \App\Models\OfficeMaterialRequest::STATUS_PENDING_HR && $isHr)
                                            <button type="button" class="btn btn-warning btn-sm fw-bold text-dark rounded-pill px-3 shadow-sm" 
                                                    data-bs-toggle="modal" 
                                                    data-bs-target="#hrApproveModal{{ $req->id }}">
                                                <i class="fa-solid fa-money-bill-wave me-1"></i> Add Money &amp; Approve
                                            </button>
                                        @endif

                                        {{-- Step 3 Action: Finance Head Assignment --}}
                                        @if(in_array($req->status, [\App\Models\OfficeMaterialRequest::STATUS_APPROVED_BY_HR, \App\Models\OfficeMaterialRequest::STATUS_ASSIGNED_TO_FINANCE]) && $isFinance)
                                            <button type="button" class="btn btn-sm text-white fw-bold rounded-pill px-3 shadow-sm" style="background:#7c3aed;"
                                                    data-bs-toggle="modal" 
                                                    data-bs-target="#financeAssignModal{{ $req->id }}">
                                                <i class="fa-solid fa-user-gear me-1"></i> Assign Account
                                            </button>
                                            <button type="button" class="btn btn-success btn-sm fw-bold rounded-pill px-3 shadow-sm"
                                                    data-bs-toggle="modal" 
                                                    data-bs-target="#markPaidModal{{ $req->id }}">
                                                <i class="fa-solid fa-money-bill-transfer me-1"></i> Disburse / Pay
                                            </button>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center py-5 text-muted">
                                    <i class="fa-solid fa-inbox fa-3x mb-3 text-secondary opacity-25 d-block"></i>
                                    No office material requests found in this view.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        @if($requests->hasPages())
            <div class="card-footer bg-white border-0 py-3">
                {{ $requests->links() }}
            </div>
        @endif
    </div>
</div>

{{-- ═══════════════════════════════════════════════════════════
     MODALS RENDERED OUTSIDE THE TABLE (CLEAN Z-INDEX)
═══════════════════════════════════════════════════════════ --}}
@foreach($requests as $req)

    {{-- Step 2 Modal: HR Add Money & Approve --}}
    @if($req->status === \App\Models\OfficeMaterialRequest::STATUS_PENDING_HR && $isHr)
    <div class="modal fade" id="hrApproveModal{{ $req->id }}" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form method="POST" action="{{ route('office-requests.hr-approve', $req->id) }}" class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
                @csrf
                <div class="modal-header bg-warning text-dark py-3 px-4">
                    <h5 class="modal-title fw-bold mb-0">
                        <i class="fa-solid fa-circle-check me-2"></i>HR Money &amp; Budget Approval
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4 bg-white">
                    <div class="alert alert-warning py-2 px-3 small mb-3 border-start border-4 border-warning">
                        <strong>{{ $req->request_no }}</strong> &bull; Requested by {{ $req->requestedBy?->name ?? 'Secretary' }}
                        <div class="text-muted mt-1">{{ $req->office_purpose }} ({{ $req->items->count() }} items)</div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold text-dark small text-uppercase">Approved Amount (ETB) <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-0 fw-bold">ETB</span>
                            <input type="number" name="amount" class="form-control bg-light border-0 fw-bold fs-5 text-success" step="0.01" min="0.01" placeholder="e.g. 3500.00" required>
                        </div>
                        <div class="form-text small">Enter the total approved budget money for these office materials.</div>
                    </div>

                    <div class="mb-0">
                        <label class="form-label fw-bold text-dark small text-uppercase">HR Remarks / Notes (ማስታወሻ)</label>
                        <textarea name="hr_notes" rows="3" class="form-control bg-light border-0" placeholder="e.g. Approved budget for monthly stationery supplies..."></textarea>
                    </div>
                </div>
                <div class="modal-footer bg-light border-0 py-3 px-4">
                    <button type="button" class="btn btn-light rounded-pill px-3" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning rounded-pill px-4 fw-bold shadow-sm">
                        <i class="fa-solid fa-check me-1"></i> Approve &amp; Send to Finance
                    </button>
                </div>
            </form>
        </div>
    </div>
    @endif

    {{-- Step 3 Modal: Finance Head Assigns COA/Bank & Staff --}}
    @if(in_array($req->status, [\App\Models\OfficeMaterialRequest::STATUS_APPROVED_BY_HR, \App\Models\OfficeMaterialRequest::STATUS_ASSIGNED_TO_FINANCE]) && $isFinance)
    <div class="modal fade" id="financeAssignModal{{ $req->id }}" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form method="POST" action="{{ route('office-requests.finance-assign', $req->id) }}" class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
                @csrf
                <div class="modal-header text-white py-3 px-4" style="background:#7c3aed;">
                    <h5 class="modal-title fw-bold mb-0">
                        <i class="fa-solid fa-user-gear me-2"></i>Finance Assignment: {{ $req->request_no }}
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4 bg-white">
                    <div style="background:#ede9fe;border:1px solid #7c3aed;border-radius:8px;padding:10px 14px;margin-bottom:14px;font-size:0.88rem;color:#5b21b6;">
                        <div class="d-flex justify-content-between align-items-center">
                            <span>Approved Budget:</span>
                            <strong class="fs-5">ETB {{ number_format((float)$req->amount, 2) }}</strong>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold text-dark small text-uppercase">Funding Account (Chart of Accounts) <span class="text-danger">*</span></label>
                        <select name="coa_id" id="modalCoaSelect{{ $req->id }}" class="form-select bg-light border-0" onchange="syncModalCoaToStaff('{{ $req->id }}')" required>
                            <option value="" disabled selected>-- Select Expense Account --</option>
                            @foreach($coaAccounts as $coa)
                                @php $linkedBank = $coa->bankAccounts->first(); @endphp
                                <option value="{{ $coa->id }}" 
                                        data-bank-id="{{ $linkedBank?->id ?? '' }}"
                                        data-staff-id="{{ $coa->assigned_to ?? $linkedBank?->assigned_to ?? '' }}"
                                        data-staff-name="{{ $coa->manager?->name ?? $linkedBank?->assignedStaff?->name ?? '' }}"
                                        {{ $req->coa_id == $coa->id ? 'selected' : '' }}>
                                    [{{ $coa->code }}] {{ $coa->name }}
                                </option>
                            @endforeach
                        </select>
                        <input type="hidden" name="bank_account_id" id="modalBankInput{{ $req->id }}" value="{{ $req->bank_account_id }}">
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold text-dark small text-uppercase">Assign Finance Staff</label>
                        <select name="assigned_finance_staff_id" id="modalStaffSelect{{ $req->id }}" class="form-select bg-light border-0">
                            <option value="">-- Assign Staff / Self --</option>
                            @foreach($financeStaff as $staff)
                                <option value="{{ $staff->id }}" {{ $req->assigned_finance_staff_id == $staff->id ? 'selected' : '' }}>
                                    {{ $staff->name }} ({{ ucfirst(str_replace('_', ' ', $staff->roles->first()?->name ?? 'Staff')) }})
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="mb-0">
                        <label class="form-label fw-bold text-dark small text-uppercase">Finance Head Notes</label>
                        <textarea name="finance_head_notes" rows="2" class="form-control bg-light border-0" placeholder="Instructions for cashier/staff...">{{ $req->finance_head_notes }}</textarea>
                    </div>
                </div>
                <div class="modal-footer bg-light border-0 py-3 px-4">
                    <button type="button" class="btn btn-light rounded-pill px-3" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn text-white rounded-pill px-4 fw-bold shadow-sm" style="background:#7c3aed;">
                        <i class="fa-solid fa-check me-1"></i> Save Assignment
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- Step 4 Modal: Disburse Payment --}}
    <div class="modal fade" id="markPaidModal{{ $req->id }}" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form method="POST" action="{{ route('office-requests.mark-paid', $req->id) }}" class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
                @csrf
                <div class="modal-header bg-success text-white py-3 px-4">
                    <h5 class="modal-title fw-bold mb-0">
                        <i class="fa-solid fa-money-bill-transfer me-2"></i>Disburse Payment: {{ $req->request_no }}
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4 bg-white">
                    <div class="alert alert-success py-2 px-3 small mb-3 border-start border-4 border-success">
                        <div class="d-flex justify-content-between">
                            <span>Approved Budget:</span>
                            <strong class="fs-5">ETB {{ number_format((float)$req->amount, 2) }}</strong>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold text-dark small text-uppercase">Actual Disbursed Amount (ETB)</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-0 fw-bold">ETB</span>
                            <input type="number" name="paid_amount" class="form-control bg-light border-0 fw-bold fs-5 text-success" step="0.01" min="0.01" value="{{ $req->amount }}">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold text-dark small text-uppercase">Voucher / Transaction Reference No.</label>
                        <input type="text" name="payment_reference" class="form-control bg-light border-0" placeholder="e.g. VC-2026-08-001 or FT26081234">
                    </div>

                    <div class="mb-0">
                        <label class="form-label fw-bold text-dark small text-uppercase">Disbursement Notes</label>
                        <textarea name="payment_notes" rows="2" class="form-control bg-light border-0" placeholder="e.g. Cash disbursed to secretary from Petty Cash..."></textarea>
                    </div>
                </div>
                <div class="modal-footer bg-light border-0 py-3 px-4">
                    <button type="button" class="btn btn-light rounded-pill px-3" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success rounded-pill px-4 fw-bold shadow-sm">
                        <i class="fa-solid fa-check-double me-1"></i> Confirm Paid &amp; Complete
                    </button>
                </div>
            </form>
        </div>
    </div>
    @endif

@endforeach

@push('scripts')
<script>
function syncModalCoaToStaff(reqId) {
    const coaSelect = document.getElementById('modalCoaSelect' + reqId);
    const bankInput = document.getElementById('modalBankInput' + reqId);
    const staffSelect = document.getElementById('modalStaffSelect' + reqId);

    if (!coaSelect) return;
    const opt = coaSelect.options[coaSelect.selectedIndex];
    if (!opt || !opt.value) return;

    const bankId = opt.getAttribute('data-bank-id');
    const staffId = opt.getAttribute('data-staff-id');

    if (bankInput && bankId) {
        bankInput.value = bankId;
    }
    if (staffSelect && staffId) {
        staffSelect.value = staffId;
    }
}
</script>
@endpush


@endsection

