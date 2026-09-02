@extends('layouts.app')
@php use Illuminate\Support\Str; @endphp

@section('title', 'Letter: ' . $letter->letter_number)

@section('content')
<div class="container-fluid py-3">

    {{-- Top Header --}}
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
        <div>
            <div class="d-flex align-items-center gap-2 mb-1">
                <h3 class="fw-bold mb-0">
                    <i class="fa-solid fa-file-lines text-primary me-2"></i>{{ $letter->letter_number }}
                </h3>
                @if($letter->type === 'incoming')
                    <span class="badge bg-info bg-opacity-10 text-info border border-info border-opacity-25 px-2 py-1">
                        <i class="fa-solid fa-arrow-down-left me-1"></i> Incoming
                    </span>
                @else
                    <span class="badge bg-warning bg-opacity-10 text-warning border border-warning border-opacity-25 px-2 py-1">
                        <i class="fa-solid fa-arrow-up-right me-1"></i> Outgoing
                    </span>
                @endif

                @if($letter->priority === 'urgent')
                    <span class="badge bg-danger px-2 py-1"><i class="fa-solid fa-fire me-1"></i>URGENT</span>
                @endif

                @php
                    $badgeClass = match($letter->status) {
                        'pending'    => 'bg-warning text-dark',
                        'viewed'     => 'bg-info text-dark',
                        'redirected' => 'bg-primary text-white',
                        'closed'     => 'bg-success text-white',
                        default      => 'bg-secondary text-white'
                    };
                @endphp
                <span class="badge {{ $badgeClass }} px-2 py-1">{{ ucfirst($letter->status) }}</span>
            </div>
            <p class="text-muted small mb-0">Registered on <strong>{{ $letter->created_at->format('M d, Y H:i') }}</strong> by <strong>{{ $letter->creator->name ?? 'Secretary' }}</strong></p>
        </div>

        <div class="d-flex gap-2">
            <a href="{{ route('letters.index') }}" class="btn btn-outline-secondary shadow-sm">
                <i class="fa-solid fa-arrow-left me-1"></i> Back to Inbox
            </a>
            @if($letter->status !== 'closed')
                <button type="button" class="btn btn-outline-primary shadow-sm" data-bs-toggle="modal" data-bs-target="#redirectModal">
                    <i class="fa-solid fa-share me-1"></i> Redirect / Forward
                </button>
                <button type="button" class="btn btn-success shadow-sm fw-bold" data-bs-toggle="modal" data-bs-target="#closeModal">
                    <i class="fa-solid fa-check-double me-1"></i> Mark Reviewed / Closed
                </button>
            @endif
        </div>
    </div>

    {{-- Alerts --}}
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show border-start border-4 border-success" role="alert">
            <i class="fa-solid fa-circle-check me-2"></i>{{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show border-start border-4 border-danger" role="alert">
            <i class="fa-solid fa-circle-exclamation me-2"></i>{{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <div class="row g-4">
        {{-- Left Column: Letter Content, Metadata & Attachments --}}
        <div class="col-lg-8">
            {{-- Subject & Specification Card --}}
            <div class="card border-0 shadow-sm rounded-3 mb-4">
                <div class="card-header bg-white py-3 border-0">
                    <h5 class="fw-bold text-dark mb-0">
                        <i class="fa-solid fa-heading text-primary me-2"></i>{{ $letter->subject }}
                    </h5>
                </div>
                <div class="card-body pt-0">
                    <div class="p-3 bg-light rounded-3 mb-3 border">
                        <h6 class="fw-bold text-secondary small text-uppercase mb-2">Specification & Content</h6>
                        <div class="text-dark" style="white-space: pre-wrap; line-height: 1.6;">{{ $letter->specification }}</div>
                    </div>

                    {{-- Metadata Grid --}}
                    <div class="row g-2 small">
                        <div class="col-md-6">
                            <div class="p-2 border rounded bg-white">
                                <span class="text-muted d-block">Official Letter Date:</span>
                                <strong class="text-dark">{{ $letter->date ? $letter->date->format('F d, Y') : '-' }}</strong>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="p-2 border rounded bg-white">
                                <span class="text-muted d-block">{{ $letter->type === 'incoming' ? 'Sender / Origin Organization:' : 'Recipient Organization:' }}</span>
                                <strong class="text-dark">{{ $letter->type === 'incoming' ? ($letter->sender ?? 'N/A') : ($letter->recipient_organization ?? 'N/A') }}</strong>
                            </div>
                        </div>
                        @if($letter->sender_department)
                        <div class="col-md-6">
                            <div class="p-2 border rounded bg-white">
                                <span class="text-muted d-block">Originating Department:</span>
                                <strong class="text-dark">{{ $letter->sender_department }}</strong>
                            </div>
                        </div>
                        @endif
                        <div class="col-md-6">
                            <div class="p-2 border rounded bg-white">
                                <span class="text-muted d-block">Created By:</span>
                                <strong class="text-dark">{{ $letter->creator->name ?? 'Secretary' }} ({{ $letter->creator->email ?? '' }})</strong>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Attachments Section & Inline Previews --}}
            <div class="card border-0 shadow-sm rounded-3 mb-4">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                    <h5 class="fw-bold mb-0">
                        <i class="fa-solid fa-paperclip text-primary me-2"></i>Attached Documents ({{ $letter->attachments->count() }})
                    </h5>
                </div>
                <div class="card-body">
                    @forelse($letter->attachments as $att)
                        <div class="border rounded-3 p-3 mb-3 bg-white shadow-sm">
                            <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">
                                <div class="d-flex align-items-center gap-2">
                                    @if($att->is_pdf)
                                        <i class="fa-solid fa-file-pdf fa-2x text-danger"></i>
                                    @else
                                        <i class="fa-solid fa-file-image fa-2x text-primary"></i>
                                    @endif
                                    <div>
                                        <div class="fw-bold text-dark">{{ $att->file_name }}</div>
                                        <small class="text-muted">{{ $att->formatted_size }} • Uploaded by {{ $att->uploader->name ?? 'Staff' }} on {{ $att->created_at->format('M d, Y') }}</small>
                                    </div>
                                </div>
                                <div class="d-flex gap-2">
                                    <a href="{{ route('letters.attachments.preview', $att->id) }}" class="btn btn-sm btn-outline-secondary" target="_blank">
                                        <i class="fa-solid fa-external-link me-1"></i> Open Full View
                                    </a>
                                    <a href="{{ route('letters.attachments.download', $att->id) }}" class="btn btn-sm btn-outline-primary" target="_blank">
                                        <i class="fa-solid fa-download me-1"></i> Download
                                    </a>
                                </div>
                            </div>

                            {{-- Inline Preview --}}
                            @php
                                $previewUrl = route('letters.attachments.preview', $att->id);
                            @endphp
                            @if($att->is_pdf)
                                <div class="mt-2 border rounded bg-light overflow-hidden" style="height: 520px;">
                                    <iframe src="{{ $previewUrl }}" width="100%" height="100%" style="border: none;">
                                        <p class="p-3 text-center text-muted">Your browser does not support inline PDF viewing. <a href="{{ route('letters.attachments.download', $att->id) }}">Click here to download.</a></p>
                                    </iframe>
                                </div>
                            @elseif($att->is_image)
                                <div class="mt-2 text-center p-2 bg-light rounded border">
                                    <a href="{{ $previewUrl }}" target="_blank">
                                        <img src="{{ $previewUrl }}"
                                             alt="{{ $att->file_name }}"
                                             class="img-fluid rounded shadow-sm"
                                             style="max-height: 450px; object-fit: contain; cursor: zoom-in;">
                                    </a>
                                </div>
                            @endif
                        </div>
                    @empty
                        <div class="text-center py-4 text-muted">
                            <i class="fa-solid fa-paperclip fa-2x mb-2 d-block opacity-50"></i>
                            No document attachments uploaded for this letter.
                        </div>
                    @endforelse
                </div>
            </div>
        </div>

        {{-- Right Column: Actions & Routing History Timeline --}}
        <div class="col-lg-4">
            @php
                $currentUser = auth()->user();
                $isSecretaryOnly = $currentUser && $currentUser->hasRole('secretary') && !$currentUser->hasAnyRole(['admin', 'global_admin', 'gm', 'manager', 'director', 'hr_manager', 'finance_head']);
            @endphp

            {{-- Quick Action Card --}}
            @if($letter->status !== 'closed')
            <div class="card border-0 shadow-sm rounded-3 mb-4 border-start border-4 border-primary">
                <div class="card-header bg-white py-3">
                    <h5 class="fw-bold mb-0"><i class="fa-solid fa-bolt text-warning me-2"></i>Action Controls</h5>
                </div>
                <div class="card-body">
                    <p class="small text-muted mb-3">
                        @if($isSecretaryOnly)
                            As Secretary, you can forward this letter to the assigned manager or department for decision-making.
                        @else
                            You can forward this letter to another colleague/department or record a decision and close it.
                        @endif
                    </p>

                    @if($isRedirectedToFinance || $isFinanceOrAdmin)
                        <div class="alert alert-warning border-0 shadow-sm rounded-3 py-2 px-3 small mb-3">
                            <i class="fa-solid fa-hand-holding-dollar text-warning fa-lg me-1"></i>
                            <strong>Finance Action:</strong> You can give a final decision and optionally process payment disbursement &amp; expense booking directly.
                        </div>
                    @endif

                    <div class="d-grid gap-2">
                        <button type="button" class="btn btn-outline-primary py-2 fw-semibold" data-bs-toggle="modal" data-bs-target="#redirectModal">
                            <i class="fa-solid fa-share me-1"></i> Redirect / Forward Letter
                        </button>

                        @if(!$isSecretaryOnly)
                            <button type="button" class="btn btn-success py-2 fw-bold" data-bs-toggle="modal" data-bs-target="#closeModal">
                                <i class="fa-solid fa-check-double me-1"></i> Give Decision &amp; Close Letter
                            </button>
                        @else
                            <div class="alert alert-info py-2 px-3 mb-0 small border-0 bg-info bg-opacity-10 text-dark rounded-3">
                                <i class="fa-solid fa-shield-halved text-info me-1"></i>
                                <strong>Secretary Access:</strong> You can create and forward letters. Official decisions &amp; closure are reserved for assigned managers.
                            </div>
                        @endif
                    </div>
                </div>
            </div>
            @else
            {{-- Letter Closed / Final Decision Card --}}
            <div class="card border-0 shadow-sm rounded-3 mb-4 bg-success bg-opacity-10 border border-success border-opacity-25">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center justify-content-between mb-1">
                        <div class="d-flex align-items-center gap-2 text-success fw-bold">
                            <i class="fa-solid fa-circle-check fa-lg"></i> Letter Closed / Final Decision Recorded
                        </div>
                        @if($letter->payment_amount > 0)
                            <span class="badge bg-success shadow-sm px-2 py-1">
                                <i class="fa-solid fa-money-bill-wave me-1"></i>ETB {{ number_format($letter->payment_amount, 2) }} Paid
                            </span>
                        @endif
                    </div>
                    <p class="small text-muted mb-1">Decided &amp; closed by <strong>{{ $letter->closer->name ?? 'Staff' }}</strong> on {{ $letter->closed_at ? $letter->closed_at->format('M d, Y H:i') : '' }}.</p>
                    @if($letter->closing_notes)
                        <div class="small p-2 bg-white rounded border text-dark mt-2">
                            <strong>Decision / Closing Notes:</strong> {{ $letter->closing_notes }}
                        </div>
                    @endif

                    {{-- Financial Settlement Details --}}
                    @if($letter->payment_amount > 0)
                        <div class="card border-0 rounded-3 mt-3 shadow-sm" style="background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%); border: 1px solid #86efac !important;">
                            <div class="card-body p-3">
                                <div class="d-flex align-items-center justify-content-between mb-2 pb-2 border-bottom border-success border-opacity-25">
                                    <div class="fw-bold text-success">
                                        <i class="fa-solid fa-receipt me-1"></i> Financial Payment &amp; Expense Settlement
                                    </div>
                                    <span class="badge bg-success text-white">Disbursed</span>
                                </div>
                                <div class="row g-2 small text-dark">
                                    <div class="col-sm-6">
                                        <div class="text-muted" style="font-size: 0.78rem;">Amount Disbursed:</div>
                                        <div class="fw-bold text-success fs-6">ETB {{ number_format($letter->payment_amount, 2) }}</div>
                                    </div>
                                    <div class="col-sm-6">
                                        <div class="text-muted" style="font-size: 0.78rem;">Paid From Account:</div>
                                        <div class="fw-bold">{{ $letter->paid_from_account ?? ($letter->chartOfAccount->name ?? 'Company Account') }}</div>
                                    </div>
                                    <div class="col-sm-6">
                                        <div class="text-muted" style="font-size: 0.78rem;">Reference / Cheque:</div>
                                        <div class="fw-semibold font-monospace">{{ $letter->payment_reference ?? 'N/A' }}</div>
                                    </div>
                                    <div class="col-sm-6">
                                        <div class="text-muted" style="font-size: 0.78rem;">Paid By / Date:</div>
                                        <div>{{ $letter->payer->name ?? 'Finance Officer' }} on {{ optional($letter->paid_at)->format('M d, Y') }}</div>
                                    </div>
                                </div>

                                <div class="d-flex gap-2 mt-3 pt-2 border-top border-success border-opacity-25 flex-wrap">
                                    @if($letter->expense_request_id)
                                        <a href="{{ route('expense-requests.show', $letter->expense_request_id) }}" class="btn btn-sm btn-outline-success bg-white shadow-sm">
                                            <i class="fa-solid fa-file-invoice-dollar me-1"></i> View Expense Request
                                        </a>
                                    @endif
                                    @if($letter->payment_voucher_path)
                                        <a href="{{ asset('storage/' . $letter->payment_voucher_path) }}" target="_blank" class="btn btn-sm btn-outline-primary bg-white shadow-sm">
                                            <i class="fa-solid fa-paperclip me-1"></i> View Payment Voucher
                                        </a>
                                    @endif
                                    <a href="{{ route('expense-requests.history') }}" class="btn btn-sm btn-link text-success text-decoration-none ms-auto">
                                        Company Expense History &rarr;
                                    </a>
                                </div>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
            @endif

            {{-- Routing History Timeline --}}
            <div class="card border-0 shadow-sm rounded-3">
                <div class="card-header bg-white py-3 border-bottom">
                    <h5 class="fw-bold mb-0">
                        <i class="fa-solid fa-timeline text-primary me-2"></i>Routing History &amp; Audit Trail
                    </h5>
                </div>
                <div class="card-body p-3">
                    <div class="timeline position-relative ps-3" style="border-left: 2px solid #dee2e6;">
                        @forelse($letter->recipients as $recipient)
                            <div class="timeline-item mb-4 position-relative ps-3">
                                {{-- Circle dot --}}
                                <div class="position-absolute rounded-circle bg-primary" 
                                     style="width: 12px; height: 12px; left: -20px; top: 4px; border: 2px solid white;"></div>
                                
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <span class="badge bg-secondary small">
                                        {{ ucfirst(str_replace('_', ' ', $recipient->action)) }}
                                    </span>
                                    <small class="text-muted">{{ $recipient->created_at->format('M d, H:i') }}</small>
                                </div>

                                <div class="small fw-semibold text-dark">
                                    From: <span class="text-primary">{{ $recipient->fromUser->name ?? 'Secretary' }}</span>
                                </div>
                                <div class="small text-muted">
                                    To: <strong class="text-dark">{{ $recipient->recipient_label }}</strong>
                                    <span class="badge bg-success-subtle text-success border border-success-subtle ms-1" style="font-size: 0.68rem;">
                                        <i class="fa-solid fa-mobile-screen me-1"></i>SMS Dispatched
                                    </span>
                                </div>

                                @if($recipient->notes)
                                    <div class="small bg-light p-2 rounded mt-2 text-secondary border">
                                        <i class="fa-solid fa-comment-dots me-1 text-muted"></i> "{{ $recipient->notes }}"
                                    </div>
                                @endif

                                @if($recipient->viewed_at)
                                    <div class="small text-success mt-1" style="font-size: 0.75rem;">
                                        <i class="fa-solid fa-eye me-1"></i> Viewed on {{ $recipient->viewed_at->format('M d, Y H:i') }}
                                    </div>
                                @endif
                            </div>
                        @empty
                            <div class="text-muted small text-center py-3">No routing logs recorded yet.</div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Modal 1: Redirect / Forward Letter --}}
<div class="modal fade" id="redirectModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title fw-bold"><i class="fa-solid fa-share me-2"></i>Redirect / Forward Letter</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="{{ route('letters.redirect', $letter->id) }}" method="POST">
                @csrf
                <div class="modal-body p-4">
                    <p class="small text-muted mb-3">Forward this letter to another colleague or role with forwarding instructions. This will be added to the official audit timeline.</p>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Target Mode <span class="text-danger">*</span></label>
                        <div class="d-flex gap-3">
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="send_target_type" id="modalTargetUser" value="user" checked onchange="toggleModalTarget()">
                                <label class="form-check-label fw-semibold" for="modalTargetUser">Specific Person</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="send_target_type" id="modalTargetRole" value="role" onchange="toggleModalTarget()">
                                <label class="form-check-label fw-semibold" for="modalTargetRole">Role / Department</label>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3" id="modalUserBox">
                        <label class="form-label fw-bold">Select Person <span class="text-danger">*</span></label>
                        <select name="to_user_id" id="modalToUser" class="form-select" required>
                            <option value="">-- Choose User --</option>
                            @foreach($users as $u)
                                <option value="{{ $u->id }}">{{ $u->name }} ({{ $u->email }})</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="mb-3 d-none" id="modalRoleBox">
                        <label class="form-label fw-bold">Select Role / Department <span class="text-danger">*</span></label>
                        <select name="to_role_name" id="modalToRole" class="form-select">
                            <option value="">-- Choose Role --</option>
                            @foreach($roles as $r)
                                <option value="{{ $r }}">{{ ucfirst(str_replace(['_', '-'], ' ', $r)) }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Forwarding Notes / Remarks <span class="text-danger">*</span></label>
                        <textarea name="redirection_notes" class="form-control" rows="3" 
                                  placeholder="e.g., Forwarding to Finance team for payment approval and processing..." required></textarea>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary fw-bold px-4">
                        <i class="fa-solid fa-paper-plane me-1"></i> Forward Letter
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Modal 2: Mark Reviewed / Closed / Payment Settlement --}}
<div class="modal fade" id="closeModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title fw-bold">
                    <i class="fa-solid fa-check-double me-2"></i>Give Final Decision &amp; Close Letter
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="{{ route('letters.close', $letter->id) }}" method="POST" enctype="multipart/form-data">
                @csrf
                <div class="modal-body p-4">
                    <p class="small text-muted mb-3">Provide a summary of the action taken, resolution, or financial settlement for this letter to mark it officially closed.</p>
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">Resolution Notes / Decision Summary <span class="text-danger">*</span></label>
                        <textarea name="closing_notes" class="form-control" rows="3" 
                                  placeholder="e.g., Payment approved and disbursed to vendor according to attached invoice and contract terms." required></textarea>
                    </div>

                    {{-- Financial Payment Option Card --}}
                    <div class="card border rounded-3 p-3 mb-3 bg-light">
                        <div class="form-check form-switch mb-2">
                            <input class="form-check-input" type="checkbox" role="switch" name="record_payment" id="recordPaymentSwitch" value="1" onchange="togglePaymentFields()" {{ ($isRedirectedToFinance || $isFinanceOrAdmin) ? 'checked' : '' }}>
                            <label class="form-check-label fw-bold text-dark" for="recordPaymentSwitch">
                                <i class="fa-solid fa-coins text-warning me-1"></i> Disburse Payment &amp; Record Company Expense (ክፍያ ይፈጽሙና ወጪ ይመዝገቡ)
                            </label>
                        </div>
                        <small class="text-muted">Enable this if this letter entails money to be paid/disbursed. This will record an official Expense in Finance records and deduct the money from the chosen Cash/Bank account.</small>

                        {{-- Collapsible Payment Fields --}}
                        <div id="paymentFieldsContainer" class="mt-3 pt-3 border-top {{ ($isRedirectedToFinance || $isFinanceOrAdmin) ? '' : 'd-none' }}">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label fw-bold text-dark">Gross / Base Invoice Amount (ETB) <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <span class="input-group-text bg-white fw-bold">ETB</span>
                                        <input type="number" step="0.01" min="0.01" name="gross_amount" id="modalGrossAmount" class="form-control fw-bold fs-6 text-dark" placeholder="0.00" oninput="recalculateLetterTax()">
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label fw-bold text-dark">Paid From (Cash / Bank Account) <span class="text-danger">*</span></label>
                                    <select name="chart_of_account_id" id="modalCoaId" class="form-select">
                                        <option value="">-- Select Cash / Bank Account --</option>
                                        @foreach($cashAndBankAccounts ?? [] as $coa)
                                            <option value="{{ $coa->id }}">
                                                {{ $coa->code }} - {{ $coa->name }} (Bal: ETB {{ number_format($coa->current_balance ?? 0, 2) }})
                                            </option>
                                        @endforeach
                                    </select>
                                </div>

                                {{-- Service Tax & Deduction Config (VAT & Withholding) --}}
                                <div class="col-12">
                                    <div class="card border border-primary-subtle bg-white rounded-3 p-3 shadow-xs">
                                        <div class="d-flex justify-content-between align-items-center mb-2 pb-1 border-bottom">
                                            <strong class="text-primary small text-uppercase">
                                                <i class="fa-solid fa-receipt me-1"></i>Service Tax &amp; Deduction Config (VAT &amp; Withholding)
                                            </strong>
                                            <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-2 py-0">Tax Calculation</span>
                                        </div>

                                        <div class="row g-3">
                                            {{-- VAT Option --}}
                                            <div class="col-md-6">
                                                <label class="form-label small fw-semibold mb-1">VAT Option (ቫት)</label>
                                                <select name="vat_type" id="modalVatType" class="form-select form-select-sm" onchange="recalculateLetterTax()">
                                                    <option value="none">No VAT (0% / ያለ ቫት)</option>
                                                    <option value="exclusive">15% VAT Added (+15% ተጨማሪ ቫት)</option>
                                                    <option value="vat_b">15% VAT Included / VAT B (ከቫት 15% ጋር የተካተተ - ቫት ቢ)</option>
                                                </select>
                                                <input type="hidden" name="vat_rate" id="modalVatRate" value="15.00">
                                                <input type="hidden" name="vat_amount" id="modalVatAmount" value="0.00">
                                            </div>

                                            {{-- Withholding Tax --}}
                                            <div class="col-md-6">
                                                <label class="form-label small fw-semibold mb-1">Withholding Tax (የቅድመ ግብር 3%)</label>
                                                <div class="form-check form-switch mt-1">
                                                    <input class="form-check-input" type="checkbox" role="switch" name="has_withholding" value="1" 
                                                           id="modalWithholdingToggle" onchange="recalculateLetterTax()">
                                                    <label class="form-check-label small" for="modalWithholdingToggle">
                                                        Apply 3% Service Withholding Deduction
                                                    </label>
                                                </div>
                                                <input type="hidden" name="withholding_rate" id="modalWithholdingRate" value="3.00">
                                                <input type="hidden" name="withholding_amount" id="modalWithholdingAmount" value="0.00">
                                            </div>
                                        </div>

                                        {{-- Real-time Tax Breakdown Card --}}
                                        <div class="mt-3 p-2 bg-light rounded border shadow-sm">
                                            <div class="row text-center g-2 small">
                                                <div class="col-3 border-end">
                                                    <span class="text-muted d-block" style="font-size:0.75rem;">Base Amount</span>
                                                    <strong class="text-dark" id="displayLetterBase">ETB 0.00</strong>
                                                </div>
                                                <div class="col-3 border-end">
                                                    <span class="text-muted d-block" style="font-size:0.75rem;">VAT (15%)</span>
                                                    <strong class="text-info" id="displayLetterVat">+ ETB 0.00</strong>
                                                </div>
                                                <div class="col-3 border-end">
                                                    <span class="text-muted d-block" style="font-size:0.75rem;">Withholding (3%)</span>
                                                    <strong class="text-danger" id="displayLetterWht">- ETB 0.00</strong>
                                                </div>
                                                <div class="col-3">
                                                    <span class="text-muted d-block" style="font-size:0.75rem;">Net Payable</span>
                                                    <strong class="text-success" id="displayLetterNet">ETB 0.00</strong>
                                                </div>
                                            </div>
                                        </div>

                                        {{-- Withholding Tax Receipt & Slip Upload Section --}}
                                        <div id="withholdingReceiptSection" class="mt-3 p-3 bg-light rounded-3 border border-danger-subtle shadow-sm" style="display:none;">
                                            <div class="d-flex align-items-center justify-content-between mb-2">
                                                <label class="form-label small fw-bold text-danger text-uppercase mb-0">
                                                    <i class="fa-solid fa-file-invoice-dollar me-1"></i>Withholding Tax Receipt / Slip Upload (የቅድመ ግብር ደረሰኝ) <span class="text-danger">*</span>
                                                </label>
                                                <span class="badge bg-danger-subtle text-danger border border-danger-subtle px-2 py-0">Required for 3% WHT</span>
                                            </div>
                                            <div class="row g-2 align-items-center">
                                                <div class="col-md-7">
                                                    <input type="file" name="withholding_receipt" id="modalWithholdingReceipt" 
                                                           class="form-control form-control-sm" 
                                                           accept="image/jpeg,image/png,image/jpg,application/pdf,image/webp">
                                                    <small class="text-muted" style="font-size:0.75rem;">Upload official Withholding receipt image or PDF.</small>
                                                </div>
                                                <div class="col-md-5">
                                                    <input type="text" name="withholding_receipt_number" id="modalWithholdingReceiptNo" 
                                                           class="form-control form-control-sm" 
                                                           placeholder="WHT Receipt / Voucher #">
                                                    <small class="text-muted" style="font-size:0.75rem;">Voucher / Receipt Serial # (Optional)</small>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <input type="hidden" name="payment_amount" id="modalPaymentAmount" value="0.00">
                                <input type="hidden" name="net_amount" id="modalNetAmount" value="0.00">

                                <div class="col-md-6">
                                    <label class="form-label fw-semibold text-dark">Expense Category</label>
                                    <select name="expense_category" class="form-select">
                                        @foreach($expenseCategories ?? [] as $key => $label)
                                            <option value="{{ $key }}" {{ $key === 'Service' ? 'selected' : '' }}>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label fw-semibold text-dark">Related Project (Optional)</label>
                                    <select name="project_id" class="form-select">
                                        <option value="">-- General / Head Office Expense --</option>
                                        @foreach($projects ?? [] as $proj)
                                            <option value="{{ $proj->id }}">{{ $proj->name ?? $proj->project_name }}</option>
                                        @endforeach
                                    </select>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label fw-semibold text-dark">Payment Reference / Cheque #</label>
                                    <input type="text" name="payment_reference" class="form-control font-monospace" placeholder="e.g. CHQ-10492 or FT-88219">
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label fw-semibold text-dark">Payment Voucher / Receipt Attachment</label>
                                    <input type="file" name="payment_voucher" class="form-control" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success fw-bold px-4" id="btnLetterSettle">
                        <i class="fa-solid fa-check-double me-1"></i> Confirm Decision &amp; Settle (<span id="btnLetterAmount">ETB 0.00</span>)
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function toggleModalTarget() {
    const isRole = document.getElementById('modalTargetRole').checked;
    const userBox = document.getElementById('modalUserBox');
    const roleBox = document.getElementById('modalRoleBox');
    const userSelect = document.getElementById('modalToUser');
    const roleSelect = document.getElementById('modalToRole');

    if (isRole) {
        userBox.classList.add('d-none');
        roleBox.classList.remove('d-none');
        userSelect.removeAttribute('required');
        roleSelect.setAttribute('required', 'required');
    } else {
        userBox.classList.remove('d-none');
        roleBox.classList.add('d-none');
        userSelect.setAttribute('required', 'required');
        roleSelect.removeAttribute('required');
    }
}

function togglePaymentFields() {
    const isChecked = document.getElementById('recordPaymentSwitch').checked;
    const container = document.getElementById('paymentFieldsContainer');
    const grossInput = document.getElementById('modalGrossAmount');
    const coaSelect = document.getElementById('modalCoaId');

    if (isChecked) {
        container.classList.remove('d-none');
        if (grossInput) grossInput.setAttribute('required', 'required');
        if (coaSelect) coaSelect.setAttribute('required', 'required');
        recalculateLetterTax();
    } else {
        container.classList.add('d-none');
        if (grossInput) grossInput.removeAttribute('required');
        if (coaSelect) coaSelect.removeAttribute('required');
    }
}

function recalculateLetterTax() {
    const grossInput = document.getElementById('modalGrossAmount');
    const vatTypeSelect = document.getElementById('modalVatType');
    const whtToggle = document.getElementById('modalWithholdingToggle');

    if (!grossInput) return;
    const gross = parseFloat(grossInput.value) || 0;
    const vatType = vatTypeSelect ? vatTypeSelect.value : 'none';
    const vatRate = 15.00;
    const hasWht = whtToggle ? whtToggle.checked : false;
    const whtRate = 3.00;

    let vatAmount = 0.0;
    let baseAmount = gross;
    let whtAmount = 0.0;
    let netAmount = gross;

    if (vatType === 'exclusive') {
        vatAmount = Math.round(gross * (vatRate / 100) * 100) / 100;
        baseAmount = gross;
        const totalGrossWithVat = gross + vatAmount;
        if (hasWht) {
            whtAmount = Math.round(baseAmount * (whtRate / 100) * 100) / 100;
        }
        netAmount = Math.round((totalGrossWithVat - whtAmount) * 100) / 100;
    } else if (vatType === 'inclusive' || vatType === 'vat_b') {
        baseAmount = Math.round((gross / (1 + (vatRate / 100))) * 100) / 100;
        vatAmount = Math.round((gross - baseAmount) * 100) / 100;
        if (hasWht) {
            whtAmount = Math.round(baseAmount * (whtRate / 100) * 100) / 100;
        }
        netAmount = Math.round((gross - whtAmount) * 100) / 100;
    } else {
        baseAmount = gross;
        vatAmount = 0.0;
        if (hasWht) {
            whtAmount = Math.round(baseAmount * (whtRate / 100) * 100) / 100;
        }
        netAmount = Math.round((gross - whtAmount) * 100) / 100;
    }

    // Set hidden inputs
    const hiddenVat = document.getElementById('modalVatAmount');
    const hiddenWht = document.getElementById('modalWithholdingAmount');
    const hiddenNet = document.getElementById('modalNetAmount');
    const hiddenPayment = document.getElementById('modalPaymentAmount');

    if (hiddenVat) hiddenVat.value = vatAmount.toFixed(2);
    if (hiddenWht) hiddenWht.value = whtAmount.toFixed(2);
    if (hiddenNet) hiddenNet.value = netAmount.toFixed(2);
    if (hiddenPayment) hiddenPayment.value = (netAmount > 0 ? netAmount : gross).toFixed(2);

    // Update display labels
    const dispBase = document.getElementById('displayLetterBase');
    const dispVat = document.getElementById('displayLetterVat');
    const dispWht = document.getElementById('displayLetterWht');
    const dispNet = document.getElementById('displayLetterNet');
    const btnSpan = document.getElementById('btnLetterAmount');

    const fmt = num => 'ETB ' + num.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

    if (dispBase) dispBase.innerText = fmt(baseAmount);
    if (dispVat) dispVat.innerText = (vatAmount > 0 ? '+ ' : '') + fmt(vatAmount);
    if (dispWht) dispWht.innerText = (whtAmount > 0 ? '- ' : '') + fmt(whtAmount);
    if (dispNet) dispNet.innerText = fmt(netAmount);
    if (btnSpan) btnSpan.innerText = fmt(netAmount);

    // Toggle Withholding Receipt Section requirement
    const whtSection = document.getElementById('withholdingReceiptSection');
    const whtInput = document.getElementById('modalWithholdingReceipt');
    if (whtSection) {
        if (hasWht) {
            whtSection.style.display = 'block';
            if (whtInput) whtInput.required = true;
        } else {
            whtSection.style.display = 'none';
            if (whtInput) whtInput.required = false;
        }
    }
}

document.addEventListener('DOMContentLoaded', function() {
    if (document.getElementById('recordPaymentSwitch')) {
        togglePaymentFields();
    }
});
</script>
@endsection
