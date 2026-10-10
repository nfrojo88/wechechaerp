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
            <p class="text-muted small mb-0">Official inbox for correspondence letters sent to the Secretary. Perform reference numbering, categorization, person assignment, and registration.</p>
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
        <div class="col-12 col-md-4">
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
        <div class="col-12 col-md-4">
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
        <div class="col-12 col-md-4">
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
    <div class="card border-0 shadow-sm rounded-3 overflow-hidden">
        <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center">
            <h5 class="fw-bold mb-0 text-dark"><i class="fa-solid fa-list-check me-2 text-warning"></i>Inbox Letters &amp; Registration Checklist</h5>
            <small class="text-muted"><i class="fa-solid fa-info-circle me-1"></i>Letters can only be registered after Reference Number, Category, and Handled Person are all set.</small>
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light text-muted small text-uppercase fw-bold" style="letter-spacing: 0.5px;">
                    <tr>
                        <th class="ps-3 py-3" style="width: 22%;">Letter &amp; Sender</th>
                        <th class="py-3" style="width: 18%;">1. Reference #</th>
                        <th class="py-3" style="width: 18%;">2. Category</th>
                        <th class="py-3" style="width: 20%;">3. Handled By</th>
                        <th class="py-3 text-center" style="width: 10%;">Status</th>
                        <th class="pe-3 py-3 text-end" style="width: 12%;">Registration</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($letters as $letter)
                        @php
                            $hasNumber = !empty($letter->letter_number);
                            $hasCategory = !empty($letter->category);
                            $hasPerson = !empty($letter->addressed_to_user_id);
                            $canRegister = $hasNumber && $hasCategory && $hasPerson && $letter->status !== \App\Models\Letter::STATUS_REGISTERED;
                        @endphp
                        <tr class="{{ $letter->status === \App\Models\Letter::STATUS_REGISTERED ? 'bg-light-subtle' : '' }}">
                            {{-- Letter & Sender --}}
                            <td class="ps-3">
                                <div class="fw-bold text-dark text-truncate mb-1" style="max-width: 280px;" title="{{ $letter->subject }}">
                                    <a href="{{ route('letters.show', $letter->id) }}" class="text-decoration-none text-dark hover-primary">
                                        {{ $letter->subject }}
                                    </a>
                                </div>
                                <div class="small text-muted d-flex align-items-center gap-1">
                                    <i class="fa-solid fa-user text-secondary"></i>
                                    <span>{{ $letter->creator?->name ?? ($letter->sender ?? 'Employee') }}</span>
                                    @if($letter->priority === 'urgent')
                                        <span class="badge bg-danger rounded-pill ms-1 px-2 py-0.5" style="font-size: 0.65rem;">URGENT</span>
                                    @endif
                                </div>
                                <div class="text-muted small mt-1" style="font-size: 0.72rem;">
                                    Sent: {{ optional($letter->sent_at ?? $letter->created_at)->format('d M Y, h:i A') }}
                                    @if($letter->attachments->isNotEmpty())
                                        &bull; <i class="fa-solid fa-paperclip"></i> {{ $letter->attachments->count() }}
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
                                    <small class="text-muted d-block" style="font-size: 0.7rem;">Locked after assignment</small>
                                @else
                                    <button type="button" class="btn btn-xs btn-outline-warning rounded-pill px-2.5 py-1 text-dark fw-semibold" data-bs-toggle="modal" data-bs-target="#assignNumberModal{{ $letter->id }}">
                                        <i class="fa-solid fa-plus-circle me-1"></i> Assign Ref #
                                    </button>
                                    <div class="text-danger small mt-1" style="font-size: 0.7rem;"><i class="fa-solid fa-circle-xmark me-1"></i>Not assigned</div>
                                @endif
                            </td>

                            {{-- Step 2: Category --}}
                            <td>
                                @if($hasCategory)
                                    <div class="d-flex align-items-center gap-1">
                                        <span class="badge bg-info-subtle text-info border border-info-subtle rounded-pill px-2.5 py-1 fw-medium">
                                            {{ $letter->category }}
                                        </span>
                                        @if($letter->status !== \App\Models\Letter::STATUS_REGISTERED)
                                            <button type="button" class="btn btn-link btn-sm p-0 text-muted" data-bs-toggle="modal" data-bs-target="#categoryModal{{ $letter->id }}" title="Change Category">
                                                <i class="fa-solid fa-pencil" style="font-size: 0.75rem;"></i>
                                            </button>
                                        @endif
                                    </div>
                                @else
                                    <button type="button" class="btn btn-xs btn-outline-warning rounded-pill px-2.5 py-1 text-dark fw-semibold" data-bs-toggle="modal" data-bs-target="#categoryModal{{ $letter->id }}">
                                        <i class="fa-solid fa-tag me-1"></i> Select Category
                                    </button>
                                    <div class="text-danger small mt-1" style="font-size: 0.7rem;"><i class="fa-solid fa-circle-xmark me-1"></i>Not selected</div>
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
                                        @if($letter->status !== \App\Models\Letter::STATUS_REGISTERED)
                                            <button type="button" class="btn btn-link btn-sm p-0 text-muted ms-1" data-bs-toggle="modal" data-bs-target="#personModal{{ $letter->id }}" title="Change Person">
                                                <i class="fa-solid fa-user-pen" style="font-size: 0.75rem;"></i>
                                            </button>
                                        @endif
                                    </div>
                                @else
                                    <button type="button" class="btn btn-xs btn-outline-warning rounded-pill px-2.5 py-1 text-dark fw-semibold" data-bs-toggle="modal" data-bs-target="#personModal{{ $letter->id }}">
                                        <i class="fa-solid fa-user-plus me-1"></i> Select Person
                                    </button>
                                    <div class="text-danger small mt-1" style="font-size: 0.7rem;"><i class="fa-solid fa-circle-xmark me-1"></i>Not selected</div>
                                @endif
                            </td>

                            {{-- Status Badge --}}
                            <td class="text-center">
                                @if($letter->status === \App\Models\Letter::STATUS_REGISTERED)
                                    <span class="badge bg-success rounded-pill px-2.5 py-1">
                                        <i class="fa-solid fa-check me-1"></i>Registered
                                    </span>
                                    <small class="text-muted d-block mt-0.5" style="font-size: 0.68rem;">
                                        {{ optional($letter->registered_at)->format('d M, h:i A') }}
                                    </small>
                                @else
                                    <span class="badge bg-warning text-dark rounded-pill px-2.5 py-1">
                                        <i class="fa-solid fa-clock me-1"></i>Sent
                                    </span>
                                @endif
                            </td>

                            {{-- Registration & View Actions --}}
                            <td class="pe-3 text-end">
                                <div class="d-flex flex-column align-items-end gap-1">
                                    @if($letter->status === \App\Models\Letter::STATUS_REGISTERED)
                                        <a href="{{ route('letters.show', $letter->id) }}" class="btn btn-xs btn-outline-primary rounded-pill px-3 py-1">
                                            <i class="fa-solid fa-eye me-1"></i> View Letter
                                        </a>
                                    @elseif($canRegister)
                                        <form action="{{ route('letters.register', $letter->id) }}" method="POST" onsubmit="return confirm('Register this letter now? An SMS notification will be sent immediately to {{ addslashes($letter->addressedTo?->name) }}.');">
                                            @csrf
                                            <button type="submit" class="btn btn-xs btn-success rounded-pill px-3 py-1 fw-bold shadow-xs">
                                                <i class="fa-solid fa-stamp me-1"></i> Register Letter
                                            </button>
                                        </form>
                                    @else
                                        <button type="button" class="btn btn-xs btn-secondary rounded-pill px-3 py-1 opacity-75" disabled title="Set number, category, and handled person first">
                                            <i class="fa-solid fa-ban me-1"></i> Register (Blocked)
                                        </button>
                                    @endif

                                    <a href="{{ route('letters.show', $letter->id) }}" class="text-muted small text-decoration-none mt-1" style="font-size: 0.72rem;">
                                        Details &amp; Log &rarr;
                                    </a>
                                </div>
                            </td>
                        </tr>

                        {{-- MODAL 1: Assign Reference Number --}}
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

                        {{-- MODAL 2: Choose Category --}}
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

                        {{-- MODAL 3: Select Handled Person --}}
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

</div>
@endsection
