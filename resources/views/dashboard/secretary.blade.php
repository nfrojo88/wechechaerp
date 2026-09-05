@extends('layouts.app')
@section('title', 'Secretary Dashboard')

@section('content')
<div class="container-fluid px-4 py-3">

    {{-- ── Header ──────────────────────────────────────────────────────────────── --}}
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
        <div>
            <h1 class="h3 mb-1 fw-bold text-dark">
                <i class="fa-solid fa-id-badge text-primary me-2"></i>Secretary Dashboard
            </h1>
            <p class="mb-0 text-muted small">
                Welcome, <strong>{{ auth()->user()->name }}</strong> — {{ now()->format('l, F j, Y') }} · Official Letter Registry &amp; Administration
            </p>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <a href="{{ route('letters.create') }}" class="btn btn-primary shadow-sm">
                <i class="fa-solid fa-pen-to-square me-1"></i> Register New Letter
            </a>
            <a href="{{ route('letters.index', ['tab' => 'all', 'type' => 'incoming']) }}" class="btn btn-outline-info shadow-sm">
                <i class="fa-solid fa-arrow-down-left me-1"></i> Incoming Letters
            </a>
            <a href="{{ route('letters.index', ['tab' => 'all', 'type' => 'outgoing']) }}" class="btn btn-outline-warning shadow-sm text-dark">
                <i class="fa-solid fa-arrow-up-right me-1"></i> Outgoing Letters
            </a>
            <a href="{{ route('letters.index', ['tab' => 'all']) }}" class="btn btn-outline-secondary shadow-sm">
                <i class="fa-solid fa-clock-rotate-left me-1"></i> Full Letter History
            </a>
        </div>
    </div>

    {{-- ── KPI Summary Cards ─────────────────────────────────────────────────── --}}
    <div class="row g-3 mb-4">
        {{-- Total Letters History --}}
        <div class="col-6 col-md-4 col-xl-2">
            <a href="{{ route('letters.index', ['tab' => 'all']) }}" class="text-decoration-none">
                <div class="card border-0 shadow-sm rounded-3 h-100" style="border-left: 4px solid #6366f1 !important;">
                    <div class="card-body p-3">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <p class="text-muted fw-semibold mb-1" style="font-size: 0.7rem; letter-spacing: .06em; text-transform: uppercase;">Total Letters</p>
                                <h3 class="fw-bold text-dark mb-0">{{ $totalLetters }}</h3>
                            </div>
                            <div class="p-2 rounded-3" style="background: rgba(99,102,241,.12);">
                                <i class="fa-solid fa-envelope-open-text fa-lg" style="color:#6366f1;"></i>
                            </div>
                        </div>
                        <small class="text-muted mt-2 d-block" style="font-size:0.72rem;">Complete Registry History</small>
                    </div>
                </div>
            </a>
        </div>

        {{-- Incoming Letters --}}
        <div class="col-6 col-md-4 col-xl-2">
            <a href="{{ route('letters.index', ['tab' => 'all', 'type' => 'incoming']) }}" class="text-decoration-none">
                <div class="card border-0 shadow-sm rounded-3 h-100" style="border-left: 4px solid #0ea5e9 !important;">
                    <div class="card-body p-3">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <p class="text-muted fw-semibold mb-1" style="font-size: 0.7rem; letter-spacing: .06em; text-transform: uppercase;">Incoming (ገቢ)</p>
                                <h3 class="fw-bold text-info mb-0">{{ $incomingLettersCount }}</h3>
                            </div>
                            <div class="p-2 rounded-3" style="background: rgba(14,165,233,.12);">
                                <i class="fa-solid fa-arrow-down-left fa-lg text-info"></i>
                            </div>
                        </div>
                        <small class="text-muted mt-2 d-block" style="font-size:0.72rem;">Received Correspondence</small>
                    </div>
                </div>
            </a>
        </div>

        {{-- Outgoing Letters --}}
        <div class="col-6 col-md-4 col-xl-2">
            <a href="{{ route('letters.index', ['tab' => 'all', 'type' => 'outgoing']) }}" class="text-decoration-none">
                <div class="card border-0 shadow-sm rounded-3 h-100" style="border-left: 4px solid #f59e0b !important;">
                    <div class="card-body p-3">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <p class="text-muted fw-semibold mb-1" style="font-size: 0.7rem; letter-spacing: .06em; text-transform: uppercase;">Outgoing (ወጪ)</p>
                                <h3 class="fw-bold text-warning mb-0">{{ $outgoingLettersCount }}</h3>
                            </div>
                            <div class="p-2 rounded-3" style="background: rgba(245,158,11,.12);">
                                <i class="fa-solid fa-arrow-up-right fa-lg text-warning"></i>
                            </div>
                        </div>
                        <small class="text-muted mt-2 d-block" style="font-size:0.72rem;">Dispatches &amp; Sent Letters</small>
                    </div>
                </div>
            </a>
        </div>

        {{-- Pending / Active Letters --}}
        <div class="col-6 col-md-4 col-xl-2">
            <a href="{{ route('letters.index', ['tab' => 'all', 'status' => 'pending']) }}" class="text-decoration-none">
                <div class="card border-0 shadow-sm rounded-3 h-100" style="border-left: 4px solid #ef4444 !important;">
                    <div class="card-body p-3">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <p class="text-muted fw-semibold mb-1" style="font-size: 0.7rem; letter-spacing: .06em; text-transform: uppercase;">Pending Letters</p>
                                <h3 class="fw-bold text-danger mb-0">{{ $pendingLetters }}</h3>
                            </div>
                            <div class="p-2 rounded-3" style="background: rgba(239,68,68,.12);">
                                <i class="fa-solid fa-clock-rotate-left fa-lg text-danger"></i>
                            </div>
                        </div>
                        <small class="text-muted mt-2 d-block" style="font-size:0.72rem;">Awaiting Decision / Action</small>
                    </div>
                </div>
            </a>
        </div>

        {{-- Office Supply Requests --}}
        <div class="col-6 col-md-4 col-xl-2">
            <a href="{{ \Illuminate\Support\Facades\Route::has('office-requests.index') ? route('office-requests.index') : url('/office-requests') }}" class="text-decoration-none">
                <div class="card border-0 shadow-sm rounded-3 h-100" style="border-left: 4px solid #f97316 !important;">
                    <div class="card-body p-3">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <p class="text-muted fw-semibold mb-1" style="font-size: 0.7rem; letter-spacing: .06em; text-transform: uppercase;">Office Supplies</p>
                                <h3 class="fw-bold text-dark mb-0">{{ $myOfficeRequestsCount ?? 0 }}</h3>
                            </div>
                            <div class="p-2 rounded-3" style="background: rgba(249,115,22,.12);">
                                <i class="fa-solid fa-boxes-stacked fa-lg" style="color:#f97316;"></i>
                            </div>
                        </div>
                        @if(($pendingOfficeRequestsCount ?? 0) > 0)
                            <small class="text-warning fw-semibold mt-2 d-block" style="font-size:0.72rem;">
                                <i class="fa-solid fa-hourglass-half me-1"></i>{{ $pendingOfficeRequestsCount }} Pending Review
                            </small>
                        @else
                            <small class="text-muted mt-2 d-block" style="font-size:0.72rem;">Supply Requisitions</small>
                        @endif
                    </div>
                </div>
            </a>
        </div>

        {{-- Employee Official Letters --}}
        <div class="col-6 col-md-4 col-xl-2">
            <a href="{{ route('employee-letters.index') }}" class="text-decoration-none">
                <div class="card border-0 shadow-sm rounded-3 h-100" style="border-left: 4px solid #10b981 !important;">
                    <div class="card-body p-3">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <p class="text-muted fw-semibold mb-1" style="font-size: 0.7rem; letter-spacing: .06em; text-transform: uppercase;">Employee Letters</p>
                                <h3 class="fw-bold text-success mb-0">{{ $employeeLettersCount }}</h3>
                            </div>
                            <div class="p-2 rounded-3" style="background: rgba(16,185,129,.12);">
                                <i class="fa-solid fa-file-signature fa-lg text-success"></i>
                            </div>
                        </div>
                        <small class="text-muted mt-2 d-block" style="font-size:0.72rem;">Warnings, Commendations</small>
                    </div>
                </div>
            </a>
        </div>
    </div>

    {{-- ── Quick Actions Bar ─────────────────────────────────────────────────── --}}
    <div class="card border-0 shadow-sm rounded-3 bg-white mb-4">
        <div class="card-body p-3">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
                <span class="text-muted fw-bold small text-uppercase" style="letter-spacing:.05em;">
                    <i class="fa-solid fa-bolt text-warning me-1"></i>Quick Actions:
                </span>
                <div class="d-flex flex-wrap gap-2">
                    <a href="{{ route('letters.create') }}" class="btn btn-primary btn-sm px-3">
                        <i class="fa-solid fa-plus me-1"></i> Register New Letter
                    </a>
                    <a href="{{ route('letters.index', ['tab' => 'all', 'type' => 'incoming']) }}" class="btn btn-outline-info btn-sm px-3">
                        <i class="fa-solid fa-arrow-down-left me-1"></i> Incoming Registry
                    </a>
                    <a href="{{ route('letters.index', ['tab' => 'all', 'type' => 'outgoing']) }}" class="btn btn-outline-warning text-dark btn-sm px-3">
                        <i class="fa-solid fa-arrow-up-right me-1"></i> Outgoing Registry
                    </a>
                    <a href="{{ route('employee-letters.create') }}" class="btn btn-outline-success btn-sm px-3">
                        <i class="fa-solid fa-file-pen me-1"></i> Issue Official Employee Letter
                    </a>
                    <a href="{{ \Illuminate\Support\Facades\Route::has('office-requests.create') ? route('office-requests.create') : url('/office-requests/create') }}" class="btn btn-outline-secondary btn-sm px-3">
                        <i class="fa-solid fa-boxes-stacked me-1"></i> Request Supplies
                    </a>
                    <a href="{{ route('expense-requests.index') }}" class="btn btn-outline-dark btn-sm px-3">
                        <i class="fa-solid fa-hand-holding-dollar me-1"></i> Ask Money
                    </a>
                </div>
            </div>
        </div>
    </div>

    {{-- ── Main Content Area ────────────────────────────────────────────────── --}}
    <div class="row g-4">

        {{-- Left: Comprehensive Letter History Tabs --}}
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm rounded-3 mb-4">
                <div class="card-header bg-white border-bottom py-3 d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <div>
                        <h6 class="mb-0 fw-bold text-dark">
                            <i class="fa-solid fa-clock-rotate-left text-primary me-2"></i>Letter History &amp; Registry
                        </h6>
                        <small class="text-muted">Browse incoming, outgoing, and employee letters with status tracking</small>
                    </div>
                    <div class="d-flex gap-2">
                        <a href="{{ route('letters.index', ['tab' => 'all']) }}" class="btn btn-sm btn-primary">
                            <i class="fa-solid fa-list me-1"></i> Open Full Registry
                        </a>
                    </div>
                </div>

                {{-- Letter History Nav Tabs --}}
                <div class="bg-light px-3 pt-2 border-bottom">
                    <ul class="nav nav-tabs card-header-tabs border-0" id="letterHistoryTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active fw-semibold text-dark" id="all-letters-tab" data-bs-toggle="tab" data-bs-target="#allLettersPane" type="button" role="tab">
                                <i class="fa-solid fa-layer-group text-primary me-1"></i> All Letters
                                <span class="badge bg-primary bg-opacity-10 text-primary rounded-pill ms-1">{{ $recentLetters->count() }}</span>
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link fw-semibold text-dark" id="incoming-tab" data-bs-toggle="tab" data-bs-target="#incomingPane" type="button" role="tab">
                                <i class="fa-solid fa-arrow-down-left text-info me-1"></i> Incoming (ገቢ)
                                <span class="badge bg-info bg-opacity-15 text-info rounded-pill ms-1">{{ $incomingLetters->count() }}</span>
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link fw-semibold text-dark" id="outgoing-tab" data-bs-toggle="tab" data-bs-target="#outgoingPane" type="button" role="tab">
                                <i class="fa-solid fa-arrow-up-right text-warning me-1"></i> Outgoing (ወጪ)
                                <span class="badge bg-warning bg-opacity-15 text-dark rounded-pill ms-1">{{ $outgoingLetters->count() }}</span>
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link fw-semibold text-dark" id="employee-letters-tab" data-bs-toggle="tab" data-bs-target="#employeeLettersPane" type="button" role="tab">
                                <i class="fa-solid fa-file-signature text-success me-1"></i> Employee Letters
                                <span class="badge bg-success bg-opacity-15 text-success rounded-pill ms-1">{{ $recentEmployeeLetters->count() }}</span>
                            </button>
                        </li>
                    </ul>
                </div>

                {{-- Tab Content Panes --}}
                <div class="tab-content" id="letterHistoryTabContent">

                    {{-- ── 1. All Letters History ── --}}
                    <div class="tab-pane fade show active" id="allLettersPane" role="tabpanel">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0" style="font-size: 0.88rem;">
                                <thead class="table-light">
                                    <tr>
                                        <th class="ps-3">Letter #</th>
                                        <th>Type</th>
                                        <th>Subject</th>
                                        <th>Date</th>
                                        <th>Organization / Party</th>
                                        <th class="text-center">Status</th>
                                        <th class="text-end pe-3">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($recentLetters as $letter)
                                    <tr>
                                        <td class="ps-3 fw-bold">
                                            <a href="{{ route('letters.show', $letter->id) }}" class="text-decoration-none text-primary font-monospace">
                                                {{ $letter->letter_number ?: '#'.$letter->id }}
                                            </a>
                                            @if($letter->priority === 'urgent')
                                                <span class="badge bg-danger ms-1" style="font-size:0.6rem;"><i class="fa-solid fa-fire me-1"></i>URGENT</span>
                                            @endif
                                        </td>
                                        <td>
                                            @if($letter->type === 'incoming')
                                                <span class="badge bg-info bg-opacity-10 text-info border border-info border-opacity-25 px-2 py-1">
                                                    <i class="fa-solid fa-arrow-down-left me-1"></i> Incoming
                                                </span>
                                            @else
                                                <span class="badge bg-warning bg-opacity-10 text-warning border border-warning border-opacity-25 px-2 py-1">
                                                    <i class="fa-solid fa-arrow-up-right me-1"></i> Outgoing
                                                </span>
                                            @endif
                                        </td>
                                        <td>
                                            <div class="fw-semibold text-dark text-truncate" style="max-width: 220px;" title="{{ $letter->subject }}">
                                                {{ $letter->subject }}
                                            </div>
                                            @if($letter->specification)
                                                <small class="text-muted text-truncate d-block" style="max-width: 220px;">
                                                    {{ Str::limit($letter->specification, 45) }}
                                                </small>
                                            @endif
                                        </td>
                                        <td>
                                            <small class="text-muted">{{ optional($letter->date)->format('d M Y') ?? '-' }}</small>
                                        </td>
                                        <td>
                                            <small class="text-dark fw-semibold d-block text-truncate" style="max-width: 180px;">
                                                {{ $letter->type === 'incoming' ? ($letter->sender ?: 'External Sender') : ($letter->recipient_organization ?: 'Recipient') }}
                                            </small>
                                            @if($letter->attachments && $letter->attachments->count() > 0)
                                                <span class="badge bg-light text-muted border px-1" style="font-size: 0.65rem;">
                                                    <i class="fa-solid fa-paperclip me-1"></i>{{ $letter->attachments->count() }} file(s)
                                                </span>
                                            @endif
                                        </td>
                                        <td class="text-center">
                                            @php
                                                $statusColor = match($letter->status ?? '') {
                                                    'closed'     => 'success',
                                                    'pending'    => 'warning',
                                                    'redirected' => 'info',
                                                    'viewed'     => 'primary',
                                                    default      => 'secondary',
                                                };
                                            @endphp
                                            <span class="badge bg-{{ $statusColor }} text-{{ in_array($letter->status, ['pending', 'redirected']) ? 'dark' : '' }} px-2 py-1">
                                                {{ ucfirst($letter->status ?? 'pending') }}
                                            </span>
                                        </td>
                                        <td class="text-end pe-3">
                                            <a href="{{ route('letters.show', $letter->id) }}" class="btn btn-sm btn-outline-primary py-1 px-2" title="View Full Details">
                                                <i class="fa-solid fa-eye"></i>
                                            </a>
                                        </td>
                                    </tr>
                                    @empty
                                    <tr>
                                        <td colspan="7" class="text-center py-4 text-muted">
                                            <i class="fa-solid fa-envelope-open-text fa-2x mb-2 d-block opacity-25"></i>
                                            No letters recorded in history yet.
                                            <a href="{{ route('letters.create') }}" class="d-block mt-1 small">Register new letter now →</a>
                                        </td>
                                    </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>

                    {{-- ── 2. Incoming Letters History ── --}}
                    <div class="tab-pane fade" id="incomingPane" role="tabpanel">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0" style="font-size: 0.88rem;">
                                <thead class="table-light">
                                    <tr>
                                        <th class="ps-3">Letter #</th>
                                        <th>Subject</th>
                                        <th>Sender / Organization</th>
                                        <th>Date Received</th>
                                        <th>Attachments</th>
                                        <th class="text-center">Status</th>
                                        <th class="text-end pe-3">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($incomingLetters as $letter)
                                    <tr>
                                        <td class="ps-3 fw-bold">
                                            <a href="{{ route('letters.show', $letter->id) }}" class="text-decoration-none text-info font-monospace">
                                                {{ $letter->letter_number ?: '#'.$letter->id }}
                                            </a>
                                            @if($letter->priority === 'urgent')
                                                <span class="badge bg-danger ms-1" style="font-size:0.6rem;"><i class="fa-solid fa-fire me-1"></i>URGENT</span>
                                            @endif
                                        </td>
                                        <td>
                                            <div class="fw-semibold text-dark text-truncate" style="max-width: 240px;">{{ $letter->subject }}</div>
                                            @if($letter->specification)
                                                <small class="text-muted text-truncate d-block" style="max-width: 240px;">{{ Str::limit($letter->specification, 45) }}</small>
                                            @endif
                                        </td>
                                        <td>
                                            <span class="fw-semibold text-dark">{{ $letter->sender ?: 'External Entity' }}</span>
                                            @if($letter->sender_department)
                                                <small class="text-muted d-block">{{ $letter->sender_department }}</small>
                                            @endif
                                        </td>
                                        <td>
                                            <small class="text-muted">{{ optional($letter->date)->format('d M Y') ?? '-' }}</small>
                                        </td>
                                        <td>
                                            @if($letter->attachments && $letter->attachments->count() > 0)
                                                <span class="badge bg-light text-muted border">
                                                    <i class="fa-solid fa-paperclip text-info me-1"></i>{{ $letter->attachments->count() }} file(s)
                                                </span>
                                            @else
                                                <small class="text-muted">—</small>
                                            @endif
                                        </td>
                                        <td class="text-center">
                                            <span class="badge bg-{{ $letter->status === 'closed' ? 'success' : 'warning' }} text-{{ $letter->status === 'closed' ? 'white' : 'dark' }} px-2 py-1">
                                                {{ ucfirst($letter->status ?? 'pending') }}
                                            </span>
                                        </td>
                                        <td class="text-end pe-3">
                                            <a href="{{ route('letters.show', $letter->id) }}" class="btn btn-sm btn-outline-info py-1 px-2">
                                                <i class="fa-solid fa-eye"></i>
                                            </a>
                                        </td>
                                    </tr>
                                    @empty
                                    <tr>
                                        <td colspan="7" class="text-center py-4 text-muted">
                                            <i class="fa-solid fa-arrow-down-left fa-2x mb-2 d-block opacity-25 text-info"></i>
                                            No incoming letters registered yet.
                                            <a href="{{ route('letters.create') }}" class="d-block mt-1 small">Register an incoming letter →</a>
                                        </td>
                                    </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>

                    {{-- ── 3. Outgoing Letters History ── --}}
                    <div class="tab-pane fade" id="outgoingPane" role="tabpanel">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0" style="font-size: 0.88rem;">
                                <thead class="table-light">
                                    <tr>
                                        <th class="ps-3">Letter #</th>
                                        <th>Subject</th>
                                        <th>Recipient Organization</th>
                                        <th>Dispatch Date</th>
                                        <th>Registered By</th>
                                        <th class="text-center">Status</th>
                                        <th class="text-end pe-3">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($outgoingLetters as $letter)
                                    <tr>
                                        <td class="ps-3 fw-bold">
                                            <a href="{{ route('letters.show', $letter->id) }}" class="text-decoration-none text-warning font-monospace">
                                                {{ $letter->letter_number ?: '#'.$letter->id }}
                                            </a>
                                            @if($letter->priority === 'urgent')
                                                <span class="badge bg-danger ms-1" style="font-size:0.6rem;"><i class="fa-solid fa-fire me-1"></i>URGENT</span>
                                            @endif
                                        </td>
                                        <td>
                                            <div class="fw-semibold text-dark text-truncate" style="max-width: 240px;">{{ $letter->subject }}</div>
                                            @if($letter->specification)
                                                <small class="text-muted text-truncate d-block" style="max-width: 240px;">{{ Str::limit($letter->specification, 45) }}</small>
                                            @endif
                                        </td>
                                        <td>
                                            <span class="fw-semibold text-dark">{{ $letter->recipient_organization ?: 'Recipient Organization' }}</span>
                                        </td>
                                        <td>
                                            <small class="text-muted">{{ optional($letter->date)->format('d M Y') ?? '-' }}</small>
                                        </td>
                                        <td>
                                            <small class="text-muted">{{ $letter->creator?->name ?? 'Secretary' }}</small>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge bg-{{ $letter->status === 'closed' ? 'success' : 'warning' }} text-{{ $letter->status === 'closed' ? 'white' : 'dark' }} px-2 py-1">
                                                {{ ucfirst($letter->status ?? 'pending') }}
                                            </span>
                                        </td>
                                        <td class="text-end pe-3">
                                            <a href="{{ route('letters.show', $letter->id) }}" class="btn btn-sm btn-outline-warning text-dark py-1 px-2">
                                                <i class="fa-solid fa-eye"></i>
                                            </a>
                                        </td>
                                    </tr>
                                    @empty
                                    <tr>
                                        <td colspan="7" class="text-center py-4 text-muted">
                                            <i class="fa-solid fa-arrow-up-right fa-2x mb-2 d-block opacity-25 text-warning"></i>
                                            No outgoing letters registered yet.
                                            <a href="{{ route('letters.create') }}" class="d-block mt-1 small">Register an outgoing dispatch letter →</a>
                                        </td>
                                    </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>

                    {{-- ── 4. Employee Official Letters History ── --}}
                    <div class="tab-pane fade" id="employeeLettersPane" role="tabpanel">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0" style="font-size: 0.88rem;">
                                <thead class="table-light">
                                    <tr>
                                        <th class="ps-3">Ref #</th>
                                        <th>Employee Name</th>
                                        <th>Letter Type</th>
                                        <th>Subject</th>
                                        <th>Issued Date</th>
                                        <th class="text-center">Ack. Status</th>
                                        <th class="text-end pe-3">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($recentEmployeeLetters as $empLtr)
                                    <tr>
                                        <td class="ps-3 fw-bold">
                                            <a href="{{ route('employee-letters.show', $empLtr->id) }}" class="text-decoration-none text-primary font-monospace">
                                                {{ $empLtr->reference_number ?: 'LTR-#'.$empLtr->id }}
                                            </a>
                                        </td>
                                        <td>
                                            <span class="fw-semibold text-dark">{{ $empLtr->employee?->full_name ?? 'N/A' }}</span>
                                            <small class="text-muted font-monospace d-block">{{ $empLtr->employee?->employee_code ?? '' }}</small>
                                        </td>
                                        <td>
                                            <span class="badge {{ $empLtr->badge_class ?? 'bg-secondary' }}">
                                                <i class="{{ $empLtr->icon ?? 'fa-solid fa-file-lines' }} me-1"></i>{{ $empLtr->type_label }}
                                            </span>
                                        </td>
                                        <td>
                                            <span class="text-dark fw-semibold text-truncate d-block" style="max-width: 200px;">{{ $empLtr->title }}</span>
                                        </td>
                                        <td>
                                            <small class="text-muted">{{ optional($empLtr->issued_date)->format('d M Y') ?? '-' }}</small>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge bg-{{ $empLtr->acknowledgement_status === 'acknowledged' ? 'success' : 'secondary' }}">
                                                {{ ucfirst(str_replace('_', ' ', $empLtr->acknowledgement_status ?? 'acknowledged')) }}
                                            </span>
                                        </td>
                                        <td class="text-end pe-3">
                                            <a href="{{ route('employee-letters.show', $empLtr->id) }}" class="btn btn-sm btn-outline-secondary py-1 px-2">
                                                <i class="fa-solid fa-eye"></i>
                                            </a>
                                        </td>
                                    </tr>
                                    @empty
                                    <tr>
                                        <td colspan="7" class="text-center py-4 text-muted">
                                            <i class="fa-solid fa-file-signature fa-2x mb-2 d-block opacity-25 text-success"></i>
                                            No employee letters issued yet.
                                            <a href="{{ route('employee-letters.create') }}" class="d-block mt-1 small">Issue an official employee letter →</a>
                                        </td>
                                    </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>

                </div>
            </div>

            {{-- My Office Supply Requests Table --}}
            <div class="card border-0 shadow-sm rounded-3">
                <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center">
                    <h6 class="mb-0 fw-bold text-dark">
                        <i class="fa-solid fa-boxes-stacked text-warning me-2"></i>My Office Supply Requests (የቢሮ እቃዎች ጥያቄዎች)
                    </h6>
                    <div class="d-flex gap-2">
                        <a href="{{ \Illuminate\Support\Facades\Route::has('office-requests.create') ? route('office-requests.create') : url('/office-requests/create') }}" class="btn btn-sm btn-primary">
                            <i class="fa-solid fa-plus me-1"></i> New Request
                        </a>
                        <a href="{{ \Illuminate\Support\Facades\Route::has('office-requests.index') ? route('office-requests.index') : url('/office-requests') }}" class="btn btn-sm btn-outline-secondary">View All</a>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" style="font-size: 0.88rem;">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-3">PR #</th>
                                <th>Purpose / Category</th>
                                <th>Items</th>
                                <th class="text-center">Status</th>
                                <th class="text-center">Date</th>
                                <th class="text-end pe-3">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($myOfficeRequests as $offReq)
                            <tr>
                                <td class="ps-3">
                                    <span class="fw-bold text-primary font-monospace" style="font-size:0.8rem;">{{ $offReq->pr_no }}</span>
                                </td>
                                <td>
                                    <span class="fw-semibold text-dark">{{ $offReq->office_purpose ?: 'Office Supplies' }}</span>
                                </td>
                                <td>
                                    <small class="text-muted">{{ $offReq->items->count() }} items</small>
                                </td>
                                <td class="text-center">
                                    @if($offReq->status === 'pending_hr_approval')
                                        <span class="badge bg-warning text-dark">Pending HR/Coordinator</span>
                                    @elseif($offReq->status === 'approved')
                                        <span class="badge bg-success">Approved</span>
                                    @elseif($offReq->status === 'rejected')
                                        <span class="badge bg-danger">Rejected</span>
                                    @else
                                        <span class="badge bg-info text-dark">{{ $offReq->status_label }}</span>
                                    @endif
                                </td>
                                <td class="text-center">
                                    <small class="text-muted">{{ optional($offReq->created_at)->format('d M Y') }}</small>
                                </td>
                                <td class="text-end pe-3">
                                    <a href="{{ \Illuminate\Support\Facades\Route::has('office-requests.show') ? route('office-requests.show', $offReq) : url('/office-requests/' . $offReq->id) }}" class="btn btn-sm btn-outline-secondary py-1 px-2">
                                        <i class="fa-solid fa-eye"></i>
                                    </a>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="6" class="text-center py-4 text-muted">
                                    <i class="fa-solid fa-boxes-stacked fa-2x mb-2 d-block opacity-25"></i>
                                    No office supply requests yet.
                                    <a href="{{ \Illuminate\Support\Facades\Route::has('office-requests.create') ? route('office-requests.create') : url('/office-requests/create') }}" class="d-block mt-1 small">Ask for office materials / stationery →</a>
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        {{-- Right Column: Registry Navigation & My Ask Money --}}
        <div class="col-lg-4">

            {{-- Correspondence Status Summary Card --}}
            <div class="card border-0 shadow-sm rounded-3 mb-4">
                <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center">
                    <h6 class="mb-0 fw-bold text-dark">
                        <i class="fa-solid fa-folder-tree text-info me-2"></i>Letters &amp; Correspondence Navigation
                    </h6>
                </div>
                <div class="card-body p-0">
                    <div class="list-group list-group-flush">
                        <a href="{{ route('letters.index', ['tab' => 'all']) }}" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center px-3 py-2">
                            <span><i class="fa-solid fa-clock-rotate-left text-primary me-2"></i><strong>All Letter History</strong></span>
                            <span class="badge bg-primary rounded-pill">{{ $totalLetters }}</span>
                        </a>
                        <a href="{{ route('letters.index', ['tab' => 'all', 'type' => 'incoming']) }}" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center px-3 py-2">
                            <span><i class="fa-solid fa-arrow-down-left text-info me-2"></i><strong>Incoming Letters (ገቢ)</strong></span>
                            <span class="badge bg-info text-dark rounded-pill">{{ $incomingLettersCount }}</span>
                        </a>
                        <a href="{{ route('letters.index', ['tab' => 'all', 'type' => 'outgoing']) }}" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center px-3 py-2">
                            <span><i class="fa-solid fa-arrow-up-right text-warning me-2"></i><strong>Outgoing Letters (ወጪ)</strong></span>
                            <span class="badge bg-warning text-dark rounded-pill">{{ $outgoingLettersCount }}</span>
                        </a>
                        <a href="{{ route('employee-letters.index') }}" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center px-3 py-2">
                            <span><i class="fa-solid fa-file-signature text-success me-2"></i><strong>Employee Official Letters</strong></span>
                            <span class="badge bg-success rounded-pill">{{ $employeeLettersCount }}</span>
                        </a>
                        <a href="{{ route('letters.create') }}" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center px-3 py-2 text-primary fw-semibold">
                            <span><i class="fa-solid fa-plus-circle text-primary me-2"></i>Register New Letter</span>
                            <i class="fa-solid fa-chevron-right text-muted small"></i>
                        </a>
                    </div>
                </div>
            </div>

            {{-- My Expense Requests --}}
            <div class="card border-0 shadow-sm rounded-3">
                <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center">
                    <h6 class="mb-0 fw-bold text-dark">
                        <i class="fa-solid fa-hand-holding-dollar text-success me-2"></i>My Ask Money (Petty Cash)
                    </h6>
                    <a href="{{ route('expense-requests.index') }}" class="btn btn-sm btn-outline-success">View All</a>
                </div>
                <div class="card-body p-0">
                    <ul class="list-group list-group-flush">
                        @forelse($myExpenseRequests as $exp)
                        <li class="list-group-item px-3 py-2">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <span class="fw-semibold d-block" style="font-size:0.85rem;">{{ $exp->purpose ?? $exp->title ?? 'Request #'.$exp->id }}</span>
                                    <small class="text-muted">Br {{ number_format($exp->amount ?? 0, 2) }}</small>
                                </div>
                                @php
                                    $expColor = str_contains(strtolower($exp->status ?? ''), 'approved') ? 'success'
                                              : (str_contains(strtolower($exp->status ?? ''), 'reject') ? 'danger' : 'warning');
                                @endphp
                                <span class="badge bg-{{ $expColor }} text-{{ $expColor === 'warning' ? 'dark' : '' }}" style="font-size:0.7rem;">
                                    {{ $exp->status ?? 'Pending' }}
                                </span>
                            </div>
                        </li>
                        @empty
                        <li class="list-group-item text-center py-3 text-muted">
                            No expense requests yet.
                            <a href="{{ route('expense-requests.index') }}" class="d-block mt-1 small">Submit one →</a>
                        </li>
                        @endforelse
                    </ul>
                </div>
            </div>

        </div>
    </div>

</div>
@endsection
