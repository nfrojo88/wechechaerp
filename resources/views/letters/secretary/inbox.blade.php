@extends('layouts.app')

@section('title', 'Secretary Inbox & Correspondence Registration - Wechacha ERP')

@section('content')
<div class="container-fluid py-3" style="max-width: 1400px;">

    {{-- Header --}}
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
        <div>
            <h3 class="fw-bold mb-1">
                <i class="fa-solid fa-inbox text-warning me-2"></i>Secretary Inbox
            </h3>
            <p class="text-muted small mb-0">Official inbox for correspondence letters sent to the Secretary. Perform reference numbering, categorization, person assignment, approval &amp; registration, or blocking.</p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('letters.index') }}" class="btn btn-outline-secondary shadow-sm">
                <i class="fa-solid fa-clock-rotate-left me-1"></i> Full Letter Registry
            </a>
            <a href="{{ route('letters.my-letters.index') }}" class="btn btn-outline-primary shadow-sm">
                <i class="fa-solid fa-file-pen me-1"></i> My Letters
            </a>
        </div>
    </div>

    {{-- Feedback Alerts --}}
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show border-start border-4 border-success shadow-xs mb-4" role="alert">
            <i class="fa-solid fa-circle-check text-success me-2 fs-5 align-middle"></i>
            <span>{{ session('success') }}</span>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show border-start border-4 border-danger shadow-xs mb-4" role="alert">
            <i class="fa-solid fa-circle-exclamation text-danger me-2 fs-5 align-middle"></i>
            <span>{{ session('error') }}</span>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    {{-- Quick Stat Counters --}}
    <div class="row g-3 mb-4">
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card border-0 shadow-sm rounded-3 p-3 border-start border-4 border-warning h-100">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-muted small text-uppercase fw-bold">Awaiting Registration</div>
                        <h3 class="fw-bold mb-0 text-warning mt-1">{{ $stats['sent'] }}</h3>
                        <small class="text-muted">Requires number, category &amp; handled person</small>
                    </div>
                    <div class="bg-warning-subtle text-warning rounded-circle p-3 fs-3">
                        <i class="fa-solid fa-envelope"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card border-0 shadow-sm rounded-3 p-3 border-start border-4 border-success h-100">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-muted small text-uppercase fw-bold">Registered Letters</div>
                        <h3 class="fw-bold mb-0 text-success mt-1">{{ $stats['registered'] }}</h3>
                        <small class="text-muted">Fully numbered, categorized &amp; dispatched</small>
                    </div>
                    <div class="bg-success-subtle text-success rounded-circle p-3 fs-3">
                        <i class="fa-solid fa-stamp"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card border-0 shadow-sm rounded-3 p-3 border-start border-4 border-danger h-100">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-muted small text-uppercase fw-bold">Blocked / Rejected</div>
                        <h3 class="fw-bold mb-0 text-danger mt-1">{{ $stats['rejected'] ?? 0 }}</h3>
                        <small class="text-muted">Blocked by secretary with notes</small>
                    </div>
                    <div class="bg-danger-subtle text-danger rounded-circle p-3 fs-3">
                        <i class="fa-solid fa-ban"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card border-0 shadow-sm rounded-3 p-3 border-start border-4 border-primary h-100">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-muted small text-uppercase fw-bold">Total Correspondence</div>
                        <h3 class="fw-bold mb-0 text-primary mt-1">{{ $stats['total'] }}</h3>
                        <small class="text-muted">Next Sequential Ref: <span class="fw-bold font-monospace text-dark">{{ $nextSuggestedNumber }}</span></small>
                    </div>
                    <div class="bg-primary-subtle text-primary rounded-circle p-3 fs-3">
                        <i class="fa-solid fa-hashtag"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Filter Toolbar --}}
    <div class="card border-0 shadow-sm rounded-3 mb-4">
        <div class="card-body p-3">
            <form method="GET" action="{{ route('letters.secretary.inbox') }}" class="row g-2 align-items-center">
                <div class="col-12 col-md-4">
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-light border-end-0"><i class="fa-solid fa-search text-muted"></i></span>
                        <input type="text" name="search" class="form-control border-start-0" placeholder="Search subject, sender, ref #..." value="{{ request('search') }}">
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <select name="status" class="form-select form-select-sm" onchange="this.form.submit()">
                        <option value="">All Statuses</option>
                        <option value="sent" {{ request('status') === 'sent' ? 'selected' : '' }}>Sent (Unregistered)</option>
                        <option value="registered" {{ request('status') === 'registered' ? 'selected' : '' }}>Registered</option>
                        <option value="rejected" {{ request('status') === 'rejected' ? 'selected' : '' }}>Blocked / Rejected</option>
                    </select>
                </div>
                <div class="col-6 col-md-3">
                    <select name="category" class="form-select form-select-sm" onchange="this.form.submit()">
                        <option value="">All Categories</option>
                        @foreach($categories as $cat)
                            <option value="{{ $cat }}" {{ request('category') === $cat ? 'selected' : '' }}>{{ $cat }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12 col-md-2 d-flex gap-1">
                    <button type="submit" class="btn btn-sm btn-primary flex-grow-1">Filter</button>
                    @if(request()->hasAny(['status', 'category', 'search', 'date_from', 'date_to']))
                        <a href="{{ route('letters.secretary.inbox') }}" class="btn btn-sm btn-outline-secondary" title="Reset Filters"><i class="fa-solid fa-rotate-left"></i></a>
                    @endif
                </div>
            </form>
        </div>
    </div>

    {{-- Letters Table Card --}}
    <div class="card border-0 shadow-sm rounded-3 overflow-hidden mb-4">
        <div class="card-header bg-white border-bottom py-3 d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div>
                <h5 class="fw-bold mb-0 text-dark"><i class="fa-solid fa-list-check me-2 text-warning"></i>Inbox Letters &amp; Registration Checklist</h5>
            </div>
            <small class="text-muted"><i class="fa-solid fa-info-circle me-1"></i>Click <strong>Review &amp; Process</strong> on any letter to inspect details &amp; attachments, then approve or block.</small>
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light text-muted small text-uppercase fw-bold" style="letter-spacing: 0.5px;">
                    <tr>
                        <th class="ps-3 py-3" style="width: 24%;">Letter &amp; Sender</th>
                        <th class="py-3" style="width: 17%;">1. Reference #</th>
                        <th class="py-3" style="width: 17%;">2. Category</th>
                        <th class="py-3" style="width: 18%;">3. Handled By</th>
                        <th class="py-3 text-center" style="width: 10%;">Status</th>
                        <th class="pe-3 py-3 text-end" style="width: 14%;">Action &amp; Review</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($letters as $letter)
                        @php
                            $hasNumber = !empty($letter->letter_number);
                            $hasCategory = !empty($letter->category);
                            $hasPerson = !empty($letter->addressed_to_user_id);
                            $isRegistered = ($letter->status === \App\Models\Letter::STATUS_REGISTERED);
                            $isRejected = ($letter->status === \App\Models\Letter::STATUS_REJECTED);
                            $canRegister = $hasNumber && $hasCategory && $hasPerson && !$isRegistered && !$isRejected;
                        @endphp
                        <tr class="{{ $isRegistered ? 'bg-light-subtle' : ($isRejected ? 'table-danger-subtle bg-danger-subtle' : '') }}">
                            {{-- Letter & Sender --}}
                            <td class="ps-3">
                                <div class="fw-bold text-dark text-truncate mb-1" style="max-width: 290px;" title="{{ $letter->subject }}">
                                    <a href="javascript:void(0)" data-bs-toggle="modal" data-bs-target="#reviewModal{{ $letter->id }}" class="text-decoration-none text-dark hover-primary fw-bold">
                                        {{ $letter->subject }}
                                    </a>
                                </div>
                                <div class="small text-muted d-flex align-items-center gap-1">
                                    <i class="fa-solid fa-user text-secondary"></i>
                                    <span>{{ $letter->creator?->name ?? ($letter->sender ?? 'Employee') }}</span>
                                    @if($letter->priority === 'urgent')
                                        <span class="badge bg-danger rounded-pill ms-1 px-2 py-0.5" style="font-size: 0.65rem;">URGENT</span>
                                    @elseif($letter->priority === 'high')
                                        <span class="badge bg-warning text-dark rounded-pill ms-1 px-2 py-0.5" style="font-size: 0.65rem;">HIGH</span>
                                    @endif
                                </div>
                                <div class="text-muted small mt-1" style="font-size: 0.72rem;">
                                    Sent: {{ optional($letter->sent_at ?? $letter->created_at)->format('d M Y, h:i A') }}
                                    @if($letter->attachments->isNotEmpty())
                                        &bull; <i class="fa-solid fa-paperclip text-primary"></i> <span class="fw-semibold text-primary">{{ $letter->attachments->count() }} file(s)</span>
                                    @endif
                                </div>
                            </td>

                            {{-- Step 1: Reference Number --}}
                            <td>
                                @if($hasNumber)
                                    <div class="d-flex align-items-center gap-1">
                                        <span class="font-monospace fw-bold text-primary">{{ $letter->letter_number }}</span>
                                        <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill py-0.5 px-1.5" title="Locked sequential number">
                                            <i class="fa-solid fa-lock"></i>
                                        </span>
                                    </div>
                                    <small class="text-muted d-block" style="font-size: 0.7rem;">Locked reference</small>
                                @elseif(!$isRegistered && !$isRejected)
                                    <button type="button" class="btn btn-xs btn-outline-warning rounded-pill px-2.5 py-1 text-dark fw-semibold" data-bs-toggle="modal" data-bs-target="#assignNumberModal{{ $letter->id }}">
                                        <i class="fa-solid fa-plus-circle me-1"></i> Assign Ref #
                                    </button>
                                    <div class="text-danger small mt-1" style="font-size: 0.7rem;"><i class="fa-solid fa-circle-xmark me-1"></i>Not assigned</div>
                                @else
                                    <span class="text-muted small">&mdash;</span>
                                @endif
                            </td>

                            {{-- Step 2: Category --}}
                            <td>
                                @if($hasCategory)
                                    <div class="d-flex align-items-center gap-1">
                                        <span class="badge bg-info-subtle text-info border border-info-subtle rounded-pill px-2.5 py-1 fw-medium">
                                            {{ $letter->category }}
                                        </span>
                                        @if(!$isRegistered && !$isRejected)
                                            <button type="button" class="btn btn-link btn-sm p-0 text-muted" data-bs-toggle="modal" data-bs-target="#categoryModal{{ $letter->id }}" title="Change Category">
                                                <i class="fa-solid fa-pencil" style="font-size: 0.75rem;"></i>
                                            </button>
                                        @endif
                                    </div>
                                @elseif(!$isRegistered && !$isRejected)
                                    <button type="button" class="btn btn-xs btn-outline-warning rounded-pill px-2.5 py-1 text-dark fw-semibold" data-bs-toggle="modal" data-bs-target="#categoryModal{{ $letter->id }}">
                                        <i class="fa-solid fa-tag me-1"></i> Select Category
                                    </button>
                                    <div class="text-danger small mt-1" style="font-size: 0.7rem;"><i class="fa-solid fa-circle-xmark me-1"></i>Not selected</div>
                                @else
                                    <span class="text-muted small">&mdash;</span>
                                @endif
                            </td>

                            {{-- Step 3: Addressed / Handled Person --}}
                            <td>
                                @if($hasPerson && $letter->addressedTo)
                                    <div class="d-flex align-items-center justify-content-between">
                                        <div>
                                            <div class="fw-semibold text-dark small">{{ $letter->addressedTo->name }}</div>
                                            <small class="text-muted" style="font-size: 0.7rem;">{{ $letter->addressedTo->email }}</small>
                                        </div>
                                        @if(!$isRegistered && !$isRejected)
                                            <button type="button" class="btn btn-link btn-sm p-0 text-muted ms-1" data-bs-toggle="modal" data-bs-target="#personModal{{ $letter->id }}" title="Change Person">
                                                <i class="fa-solid fa-user-pen" style="font-size: 0.75rem;"></i>
                                            </button>
                                        @endif
                                    </div>
                                @elseif(!$isRegistered && !$isRejected)
                                    <button type="button" class="btn btn-xs btn-outline-warning rounded-pill px-2.5 py-1 text-dark fw-semibold" data-bs-toggle="modal" data-bs-target="#personModal{{ $letter->id }}">
                                        <i class="fa-solid fa-user-plus me-1"></i> Select Person
                                    </button>
                                    <div class="text-danger small mt-1" style="font-size: 0.7rem;"><i class="fa-solid fa-circle-xmark me-1"></i>Not selected</div>
                                @else
                                    <span class="text-muted small">&mdash;</span>
                                @endif
                            </td>

                            {{-- Status Badge --}}
                            <td class="text-center">
                                @if($isRegistered)
                                    <span class="badge bg-success rounded-pill px-2.5 py-1">
                                        <i class="fa-solid fa-check me-1"></i>Registered
                                    </span>
                                    <small class="text-muted d-block mt-0.5" style="font-size: 0.68rem;">
                                        {{ optional($letter->registered_at)->format('d M, h:i A') }}
                                    </small>
                                @elseif($isRejected)
                                    <span class="badge bg-danger rounded-pill px-2.5 py-1" title="{{ $letter->closing_notes }}">
                                        <i class="fa-solid fa-ban me-1"></i>Blocked
                                    </span>
                                    <small class="text-danger d-block mt-0.5" style="font-size: 0.68rem;">
                                        Rejected
                                    </small>
                                @else
                                    <span class="badge bg-warning text-dark rounded-pill px-2.5 py-1">
                                        <i class="fa-solid fa-clock me-1"></i>Sent
                                    </span>
                                    <small class="text-muted d-block mt-0.5" style="font-size: 0.68rem;">
                                        Unregistered
                                    </small>
                                @endif
                            </td>

                            {{-- Registration & View Actions --}}
                            <td class="pe-3 text-end">
                                <div class="d-flex flex-column align-items-end gap-1">
                                    @if(!$isRegistered && !$isRejected)
                                        <button type="button" class="btn btn-sm btn-primary rounded-pill px-3 py-1 fw-bold shadow-xs" data-bs-toggle="modal" data-bs-target="#reviewModal{{ $letter->id }}">
                                            <i class="fa-solid fa-file-signature me-1"></i> Review &amp; Action
                                        </button>
                                    @elseif($isRegistered)
                                        <button type="button" class="btn btn-sm btn-outline-success rounded-pill px-3 py-1 fw-semibold" data-bs-toggle="modal" data-bs-target="#reviewModal{{ $letter->id }}">
                                            <i class="fa-solid fa-check-double me-1"></i> View Details
                                        </button>
                                    @else
                                        <button type="button" class="btn btn-sm btn-outline-danger rounded-pill px-3 py-1 fw-semibold" data-bs-toggle="modal" data-bs-target="#reviewModal{{ $letter->id }}">
                                            <i class="fa-solid fa-ban me-1"></i> Block Details
                                        </button>
                                    @endif

                                    <a href="{{ route('letters.show', $letter->id) }}" class="text-muted small text-decoration-none mt-1" style="font-size: 0.72rem;">
                                        Full Page &amp; Logs &rarr;
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center py-5">
                                <div class="text-muted mb-3">
                                    <i class="fa-solid fa-inbox fa-3x text-secondary opacity-50"></i>
                                </div>
                                <h6 class="fw-bold text-dark">Secretary Inbox is Clear</h6>
                                <p class="text-muted small mb-0">No correspondence letters matching your current filter are pending in the Secretary Inbox.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($letters->hasPages())
            <div class="card-footer bg-white border-top p-3 d-flex justify-content-end">
                {{ $letters->links() }}
            </div>
        @endif
    </div>

    {{-- ══════════════════════════════════════════════════════════════════════════
         ALL MODALS SAFELY PLACED OUTSIDE THE TABLE TO PREVENT ANY UI GLITCHES
         ══════════════════════════════════════════════════════════════════════════ --}}
    @foreach($letters as $letter)
        @php
            $hasNumber = !empty($letter->letter_number);
            $hasCategory = !empty($letter->category);
            $hasPerson = !empty($letter->addressed_to_user_id);
            $isRegistered = ($letter->status === \App\Models\Letter::STATUS_REGISTERED);
            $isRejected = ($letter->status === \App\Models\Letter::STATUS_REJECTED);
        @endphp

        {{-- ── 1. UNIFIED REVIEW, APPROVE & REGISTER / BLOCK MODAL ── --}}
        <div class="modal fade" id="reviewModal{{ $letter->id }}" tabindex="-1" aria-labelledby="reviewModalLabel{{ $letter->id }}" aria-hidden="true">
            <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
                <div class="modal-content border-0 shadow-lg rounded-3">
                    <div class="modal-header bg-light border-bottom py-3">
                        <div class="d-flex align-items-center gap-2 flex-wrap">
                            <h5 class="modal-title fw-bold text-dark mb-0" id="reviewModalLabel{{ $letter->id }}">
                                <i class="fa-solid fa-file-lines text-primary me-2"></i>{{ $letter->subject }}
                            </h5>
                            @if($isRegistered)
                                <span class="badge bg-success rounded-pill px-2.5 py-1"><i class="fa-solid fa-check me-1"></i>Registered</span>
                            @elseif($isRejected)
                                <span class="badge bg-danger rounded-pill px-2.5 py-1"><i class="fa-solid fa-ban me-1"></i>Blocked / Rejected</span>
                            @else
                                <span class="badge bg-warning text-dark rounded-pill px-2.5 py-1"><i class="fa-solid fa-clock me-1"></i>Awaiting Registration</span>
                            @endif

                            @if($letter->priority === 'urgent')
                                <span class="badge bg-danger rounded-pill px-2 py-0.5">Urgent</span>
                            @elseif($letter->priority === 'high')
                                <span class="badge bg-warning text-dark rounded-pill px-2 py-0.5">High</span>
                            @endif
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>

                    <div class="modal-body p-4">
                        <div class="row g-4">
                            {{-- LEFT COLUMN: Letter Content & Attached Files --}}
                            <div class="col-12 col-lg-7">
                                {{-- Meta Information Strip --}}
                                <div class="bg-light p-3 rounded-3 border mb-3">
                                    <div class="row g-2 small">
                                        <div class="col-6 col-sm-4">
                                            <span class="text-muted d-block">Submitted By:</span>
                                            <strong class="text-dark">{{ $letter->creator?->name ?? ($letter->sender ?? 'N/A') }}</strong>
                                        </div>
                                        <div class="col-6 col-sm-4">
                                            <span class="text-muted d-block">Submission Date:</span>
                                            <strong class="text-dark">{{ optional($letter->sent_at ?? $letter->created_at)->format('d M Y, h:i A') }}</strong>
                                        </div>
                                        <div class="col-6 col-sm-4">
                                            <span class="text-muted d-block">Confidentiality:</span>
                                            <strong class="text-capitalize text-dark">{{ str_replace('_', ' ', $letter->confidentiality ?? 'normal') }}</strong>
                                        </div>
                                        <div class="col-6 col-sm-4">
                                            <span class="text-muted d-block">Current Ref #:</span>
                                            <span class="font-monospace fw-bold text-primary">{{ $letter->letter_number ?: 'Not assigned yet' }}</span>
                                        </div>
                                        <div class="col-6 col-sm-4">
                                            <span class="text-muted d-block">Current Category:</span>
                                            <span class="fw-semibold text-info">{{ $letter->category ?: 'Not selected yet' }}</span>
                                        </div>
                                        <div class="col-6 col-sm-4">
                                            <span class="text-muted d-block">Addressed To:</span>
                                            <span class="fw-semibold text-dark">{{ $letter->addressedTo?->name ?: 'Not assigned yet' }}</span>
                                        </div>
                                    </div>
                                </div>

                                {{-- Specification / Content Box --}}
                                <div class="mb-4">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <label class="form-label fw-bold mb-0 text-dark">
                                            <i class="fa-solid fa-align-left text-primary me-1"></i> Letter Content / Specification
                                        </label>
                                    </div>
                                    <div class="border rounded-3 p-3 bg-white shadow-xs" style="max-height: 260px; overflow-y: auto; font-size: 0.92rem; line-height: 1.6; white-space: pre-wrap; font-family: inherit;">{{ $letter->specification ?: 'No text body or specification provided for this letter.' }}</div>
                                </div>

                                {{-- Attachments Section --}}
                                <div>
                                    <label class="form-label fw-bold mb-2 text-dark">
                                        <i class="fa-solid fa-paperclip text-primary me-1"></i> Attached Documents ({{ $letter->attachments->count() }})
                                    </label>
                                    @if($letter->attachments->isNotEmpty())
                                        <div class="d-flex flex-column gap-2">
                                            @foreach($letter->attachments as $att)
                                                <div class="border rounded-3 p-2.5 bg-white d-flex justify-content-between align-items-center flex-wrap gap-2 shadow-xs">
                                                    <div class="d-flex align-items-center gap-2">
                                                        @if($att->is_pdf)
                                                            <i class="fa-solid fa-file-pdf fa-2x text-danger"></i>
                                                        @elseif($att->is_image)
                                                            <i class="fa-solid fa-file-image fa-2x text-primary"></i>
                                                        @else
                                                            <i class="fa-solid fa-file fa-2x text-secondary"></i>
                                                        @endif
                                                        <div>
                                                            <div class="fw-semibold text-dark text-truncate" style="max-width: 280px;" title="{{ $att->file_name }}">
                                                                {{ $att->file_name }}
                                                            </div>
                                                            <small class="text-muted">{{ $att->formatted_size }} &bull; Uploaded {{ $att->created_at->format('M d, Y') }}</small>
                                                        </div>
                                                    </div>
                                                    <div class="d-flex gap-1">
                                                        <a href="{{ route('letters.attachments.preview', $att->id) }}" target="_blank" class="btn btn-xs btn-outline-secondary">
                                                            <i class="fa-solid fa-eye me-1"></i> Preview
                                                        </a>
                                                        <a href="{{ route('letters.attachments.download', $att->id) }}" target="_blank" class="btn btn-xs btn-outline-primary">
                                                            <i class="fa-solid fa-download me-1"></i> Download
                                                        </a>
                                                    </div>
                                                </div>
                                            @endforeach
                                        </div>
                                    @else
                                        <div class="border rounded-3 p-3 bg-light text-muted small text-center">
                                            <i class="fa-solid fa-file-circle-xmark me-1 text-secondary"></i> No attachments uploaded for this letter.
                                        </div>
                                    @endif
                                </div>
                            </div>

                            {{-- RIGHT COLUMN: Secretary Action & Decision Panel --}}
                            <div class="col-12 col-lg-5">
                                @if($isRegistered)
                                    {{-- Registered State Card --}}
                                    <div class="card border-0 bg-success-subtle border-start border-4 border-success p-3 rounded-3 mb-3">
                                        <h6 class="fw-bold text-success mb-1">
                                            <i class="fa-solid fa-circle-check me-1"></i> Letter Officially Registered
                                        </h6>
                                        <p class="small text-muted mb-2">This letter has been locked, categorized, and dispatched.</p>
                                        <div class="small">
                                            <div><strong>Ref #:</strong> <span class="font-monospace text-primary fw-bold">{{ $letter->letter_number }}</span></div>
                                            <div><strong>Category:</strong> {{ $letter->category }}</div>
                                            <div><strong>Dispatched To:</strong> {{ $letter->addressedTo?->name ?? 'N/A' }}</div>
                                            <div><strong>Registered By:</strong> {{ $letter->registeredBy?->name ?? 'Secretary' }} on {{ optional($letter->registered_at)->format('d M Y, h:i A') }}</div>
                                        </div>
                                        <div class="mt-3">
                                            <a href="{{ route('letters.show', $letter->id) }}" class="btn btn-sm btn-success w-100">
                                                <i class="fa-solid fa-arrow-up-right-from-square me-1"></i> View Full Letter Registry
                                            </a>
                                        </div>
                                    </div>
                                @elseif($isRejected)
                                    {{-- Rejected State Card --}}
                                    <div class="card border-0 bg-danger-subtle border-start border-4 border-danger p-3 rounded-3 mb-3">
                                        <h6 class="fw-bold text-danger mb-1">
                                            <i class="fa-solid fa-circle-xmark me-1"></i> Letter Blocked / Rejected
                                        </h6>
                                        <p class="small text-muted mb-2">This letter was rejected by the Secretary and was not registered.</p>
                                        <div class="small mb-2">
                                            <div><strong>Blocked By:</strong> {{ $letter->closer?->name ?? 'Secretary' }}</div>
                                            <div><strong>Date:</strong> {{ optional($letter->closed_at)->format('d M Y, h:i A') }}</div>
                                        </div>
                                        <div class="p-2 bg-white rounded border border-danger-subtle small">
                                            <strong>Reason:</strong>
                                            <p class="mb-0 text-dark">{{ $letter->closing_notes }}</p>
                                        </div>
                                    </div>
                                @else
                                    {{-- Action Form: Approve & Register OR Block / Reject --}}
                                    <div class="card border-0 shadow-sm rounded-3">
                                        <div class="card-header bg-white py-3 border-bottom">
                                            <h6 class="fw-bold mb-0 text-dark">
                                                <i class="fa-solid fa-stamp text-success me-2"></i>Approve &amp; Register Letter
                                            </h6>
                                            <small class="text-muted">Allocate sequential reference number, category, and person.</small>
                                        </div>
                                        <div class="card-body p-3">
                                            <form action="{{ route('letters.process-register', $letter->id) }}" method="POST">
                                                @csrf

                                                {{-- 1. Reference Number --}}
                                                <div class="mb-3">
                                                    <label class="form-label fw-bold small mb-1">
                                                        1. Reference Number <span class="text-danger">*</span>
                                                    </label>
                                                    @if($letter->is_reference_locked && !empty($letter->letter_number))
                                                        <div class="input-group input-group-sm">
                                                            <input type="text" class="form-control font-monospace fw-bold bg-light" value="{{ $letter->letter_number }}" disabled>
                                                            <span class="input-group-text bg-success text-white"><i class="fa-solid fa-lock"></i></span>
                                                        </div>
                                                        <input type="hidden" name="letter_number" value="{{ $letter->letter_number }}">
                                                        <div class="text-muted small" style="font-size: 0.7rem;">Reference number is locked and immutable.</div>
                                                    @else
                                                        <input type="text" name="letter_number" class="form-control form-control-sm font-monospace fw-bold" value="{{ old('letter_number', $letter->letter_number ?: $nextSuggestedNumber) }}" required>
                                                        <div class="text-muted small" style="font-size: 0.7rem;">Pre-filled with next annual sequential number. Will be locked upon registration.</div>
                                                    @endif
                                                </div>

                                                {{-- 2. Category --}}
                                                <div class="mb-3">
                                                    <label class="form-label fw-bold small mb-1">
                                                        2. Category <span class="text-danger">*</span>
                                                    </label>
                                                    <select name="category" class="form-select form-select-sm" required>
                                                        <option value="">-- Choose Letter Category --</option>
                                                        @foreach($categories as $c)
                                                            <option value="{{ $c }}" {{ old('category', $letter->category) === $c ? 'selected' : '' }}>
                                                                {{ $c }}
                                                            </option>
                                                        @endforeach
                                                    </select>
                                                </div>

                                                {{-- 3. Addressed / Handled Person --}}
                                                <div class="mb-3">
                                                    <label class="form-label fw-bold small mb-1">
                                                        3. Handled By / Addressed To <span class="text-danger">*</span>
                                                    </label>
                                                    <select name="addressed_to_user_id" class="form-select form-select-sm" required>
                                                        <option value="">-- Choose User from List --</option>
                                                        @foreach($users as $u)
                                                            <option value="{{ $u->id }}" {{ old('addressed_to_user_id', $letter->addressed_to_user_id) == $u->id ? 'selected' : '' }}>
                                                                {{ $u->name }} ({{ $u->email }})
                                                            </option>
                                                        @endforeach
                                                    </select>
                                                    <div class="text-muted small" style="font-size: 0.7rem;">Recipient will be notified via SMS and ERP inbox immediately.</div>
                                                </div>

                                                {{-- Approve & Register Submit --}}
                                                <button type="submit" class="btn btn-success fw-bold w-100 py-2 shadow-xs mb-2">
                                                    <i class="fa-solid fa-stamp me-1"></i> Approve &amp; Register Letter
                                                </button>
                                            </form>

                                            {{-- Option to Block / Reject --}}
                                            <div class="border-top pt-3 mt-3">
                                                <div class="d-flex justify-content-between align-items-center mb-2">
                                                    <span class="text-danger fw-bold small"><i class="fa-solid fa-ban me-1"></i> Block / Reject Letter</span>
                                                    <button class="btn btn-link btn-xs text-danger text-decoration-none p-0" type="button" data-bs-toggle="collapse" data-bs-target="#rejectCollapse{{ $letter->id }}" aria-expanded="false">
                                                        Toggle Block Form &darr;
                                                    </button>
                                                </div>
                                                <div class="collapse" id="rejectCollapse{{ $letter->id }}">
                                                    <form action="{{ route('letters.reject', $letter->id) }}" method="POST">
                                                        @csrf
                                                        <div class="mb-2">
                                                            <label class="form-label small fw-semibold text-muted mb-1">Reason for Rejection / Block <span class="text-danger">*</span></label>
                                                            <textarea name="rejection_reason" class="form-control form-control-sm" rows="3" placeholder="Explain why this letter is rejected (e.g. incomplete attachments, incorrect details, unauthorized submission)..." required></textarea>
                                                        </div>
                                                        <button type="submit" class="btn btn-outline-danger btn-sm w-100 fw-bold" onclick="return confirm('Are you sure you want to block/reject this letter? An in-app alert will be sent to the author.');">
                                                            <i class="fa-solid fa-ban me-1"></i> Confirm &amp; Block Letter
                                                        </button>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>

                    <div class="modal-footer bg-light border-top py-2 d-flex justify-content-between">
                        <a href="{{ route('letters.show', $letter->id) }}" class="btn btn-sm btn-outline-secondary">
                            <i class="fa-solid fa-arrow-up-right-from-square me-1"></i> Open Full Letter Registry Page
                        </a>
                        <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Close</button>
                    </div>
                </div>
            </div>
        </div>

        {{-- ── 2. INDIVIDUAL QUICK MODAL: Assign Reference Number ── --}}
        <div class="modal fade" id="assignNumberModal{{ $letter->id }}" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content border-0 shadow">
                    <form action="{{ route('letters.assign-number', $letter->id) }}" method="POST">
                        @csrf
                        <div class="modal-header bg-light">
                            <h6 class="modal-title fw-bold"><i class="fa-solid fa-hashtag text-primary me-2"></i>Assign Reference Number</h6>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body p-4">
                            <div class="alert alert-warning border-0 small mb-3">
                                <i class="fa-solid fa-lock me-1"></i> <strong>Important:</strong> Reference numbers are unique per year, never reused, and <strong>locked immediately</strong> after assignment.
                            </div>

                            <p class="small text-muted mb-2">Subject: <strong>{{ $letter->subject }}</strong></p>

                            <div class="mb-3">
                                <label class="form-label fw-bold">Reference Number</label>
                                <input type="text" name="letter_number" class="form-control font-monospace fw-bold" value="{{ old('letter_number', \App\Models\LetterSequence::peekNext()) }}" required>
                                <div class="form-text">Pre-filled with the next sequential annual number. You can keep it or customize it.</div>
                            </div>
                        </div>
                        <div class="modal-footer bg-light">
                            <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-sm btn-primary fw-semibold"><i class="fa-solid fa-lock me-1"></i> Confirm &amp; Lock Number</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        {{-- ── 3. INDIVIDUAL QUICK MODAL: Choose Category ── --}}
        <div class="modal fade" id="categoryModal{{ $letter->id }}" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content border-0 shadow">
                    <form action="{{ route('letters.set-category', $letter->id) }}" method="POST">
                        @csrf
                        <div class="modal-header bg-light">
                            <h6 class="modal-title fw-bold"><i class="fa-solid fa-tags text-primary me-2"></i>Select Letter Category</h6>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body p-4">
                            <p class="small text-muted mb-3">Subject: <strong>{{ $letter->subject }}</strong></p>

                            <label class="form-label fw-bold">Category <span class="text-danger">*</span></label>
                            <div class="list-group">
                                @foreach($categories as $c)
                                    <label class="list-group-item list-group-item-action d-flex align-items-center gap-2 py-2.5 cursor-pointer">
                                        <input class="form-check-input flex-shrink-0" type="radio" name="category" value="{{ $c }}" {{ old('category', $letter->category) === $c ? 'checked' : '' }} required>
                                        <span class="fw-medium">{{ $c }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                        <div class="modal-footer bg-light">
                            <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-sm btn-primary fw-semibold"><i class="fa-solid fa-check me-1"></i> Save Category</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        {{-- ── 4. INDIVIDUAL QUICK MODAL: Select Handled Person ── --}}
        <div class="modal fade" id="personModal{{ $letter->id }}" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content border-0 shadow">
                    <form action="{{ route('letters.set-handled-person', $letter->id) }}" method="POST">
                        @csrf
                        <div class="modal-header bg-light">
                            <h6 class="modal-title fw-bold"><i class="fa-solid fa-user-check text-primary me-2"></i>Select Addressed / Handled Person</h6>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body p-4">
                            <p class="small text-muted mb-3">Subject: <strong>{{ $letter->subject }}</strong></p>

                            <div class="mb-3">
                                <label class="form-label fw-bold">Select Person <span class="text-danger">*</span></label>
                                <select name="addressed_to_user_id" class="form-select" required>
                                    <option value="">-- Choose Person from User List --</option>
                                    @foreach($users as $u)
                                        <option value="{{ $u->id }}" {{ old('addressed_to_user_id', $letter->addressed_to_user_id) == $u->id ? 'selected' : '' }}>
                                            {{ $u->name }} ({{ $u->email }})
                                        </option>
                                    @endforeach
                                </select>
                                <div class="form-text">This person will receive an SMS notification and ERP inbox dispatch upon registration.</div>
                            </div>
                        </div>
                        <div class="modal-footer bg-light">
                            <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-sm btn-primary fw-semibold"><i class="fa-solid fa-check me-1"></i> Save Person</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endforeach

</div>
@endsection
