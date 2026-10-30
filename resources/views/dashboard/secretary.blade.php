@extends('layouts.app')
@section('title', 'Secretary Dashboard')

@section('content')
<div class="wc-container py-3 py-md-4">

    {{-- ── Header ──────────────────────────────────────────────────────────────── --}}
    <x-page-header
        title="Secretary Dashboard"
        :subtitle="'Welcome, ' . auth()->user()->name . ' — Official Letter Registry, Office Logistics & Administration'"
        badge="Administration"
    >
        <x-slot name="actions">
            <div class="d-flex gap-2 flex-wrap align-items-center">
                <span class="badge bg-white text-secondary border px-3 py-2 shadow-xs rounded-pill d-none d-sm-inline-flex align-items-center">
                    <i class="fa-regular fa-calendar text-primary me-2"></i>{{ now()->format('l, F j, Y') }}
                </span>
                <a href="{{ route('purchase-requests.create') }}" class="btn btn-success btn-sm shadow-xs" style="min-height: 38px; display: inline-flex; align-items: center;">
                    <i class="fa-solid fa-cart-plus me-1"></i> New PR (HO)
                </a>
                <a href="{{ route('letters.create') }}" class="btn btn-primary btn-sm shadow-xs" style="min-height: 38px; display: inline-flex; align-items: center;">
                    <i class="fa-solid fa-pen-to-square me-1"></i> Register Letter
                </a>
                <a href="{{ route('letters.index', ['tab' => 'all']) }}" class="btn btn-outline-secondary btn-sm shadow-xs" style="min-height: 38px; display: inline-flex; align-items: center;">
                    <i class="fa-solid fa-clock-rotate-left me-1"></i> Registry
                </a>
            </div>
        </x-slot>
    </x-page-header>

    {{-- ── KPI Summary Cards ─────────────────────────────────────────────────── --}}
    <div class="row g-3 mb-4">
        {{-- Total Letters History --}}
        <div class="col-12 col-sm-6 col-xl-2">
            <a href="{{ route('letters.index', ['tab' => 'all']) }}" class="text-decoration-none">
                <x-kpi-card
                    title="Total Letters"
                    :value="$totalLetters"
                    subtitle="Complete Registry History"
                    icon="fa-solid fa-envelope-open-text"
                    tone="purple"
                />
            </a>
        </div>

        {{-- Incoming Letters --}}
        <div class="col-12 col-sm-6 col-xl-2">
            <a href="{{ route('letters.index', ['tab' => 'all', 'type' => 'incoming']) }}" class="text-decoration-none">
                <x-kpi-card
                    title="Incoming (ገቢ)"
                    :value="$incomingLettersCount"
                    subtitle="Received Correspondence"
                    icon="fa-solid fa-arrow-down-left"
                    tone="info"
                />
            </a>
        </div>

        {{-- Outgoing Letters --}}
        <div class="col-12 col-sm-6 col-xl-2">
            <a href="{{ route('letters.index', ['tab' => 'all', 'type' => 'outgoing']) }}" class="text-decoration-none">
                <x-kpi-card
                    title="Outgoing (ወጪ)"
                    :value="$outgoingLettersCount"
                    subtitle="Dispatches & Sent Letters"
                    icon="fa-solid fa-arrow-up-right"
                    tone="warning"
                />
            </a>
        </div>

        {{-- Pending / Active Letters --}}
        <div class="col-12 col-sm-6 col-xl-2">
            <a href="{{ route('letters.index', ['tab' => 'all', 'status' => 'pending']) }}" class="text-decoration-none">
                <x-kpi-card
                    title="Pending Letters"
                    :value="$pendingLetters"
                    subtitle="Awaiting Action"
                    icon="fa-solid fa-clock-rotate-left"
                    tone="danger"
                />
            </a>
        </div>

        {{-- Office Supply Requests --}}
        <div class="col-12 col-sm-6 col-xl-2">
            <a href="{{ \Illuminate\Support\Facades\Route::has('office-requests.index') ? route('office-requests.index') : url('/office-requests') }}" class="text-decoration-none">
                <x-kpi-card
                    title="Office Supplies"
                    :value="$myOfficeRequestsCount ?? 0"
                    :subtitle="(($pendingOfficeRequestsCount ?? 0) > 0) ? ($pendingOfficeRequestsCount . ' Pending Review') : 'Supply Requisitions'"
                    icon="fa-solid fa-boxes-stacked"
                    tone="primary"
                />
            </a>
        </div>

        {{-- Employee Official Letters --}}
        <div class="col-12 col-sm-6 col-xl-2">
            <a href="{{ route('employee-letters.index') }}" class="text-decoration-none">
                <x-kpi-card
                    title="Employee Letters"
                    :value="$employeeLettersCount"
                    subtitle="Warnings & Notices"
                    icon="fa-solid fa-file-signature"
                    tone="success"
                />
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
                    <a href="{{ route('purchase-requests.create') }}" class="btn btn-success btn-sm px-3" style="min-height: 38px; display: inline-flex; align-items: center;">
                        <i class="fa-solid fa-cart-plus me-1"></i> Create PR (HO)
                    </a>
                    <a href="{{ route('procurement.my-queue') }}" class="btn btn-outline-primary btn-sm px-3" style="min-height: 38px; display: inline-flex; align-items: center;">
                        <i class="fa-solid fa-list-check me-1"></i> My PRs ({{ $myPurchaseRequestsCount ?? 0 }})
                    </a>
                    <a href="{{ route('letters.create') }}" class="btn btn-primary btn-sm px-3" style="min-height: 38px; display: inline-flex; align-items: center;">
                        <i class="fa-solid fa-plus me-1"></i> Register Letter
                    </a>
                    <a href="{{ route('letters.index', ['tab' => 'all', 'type' => 'incoming']) }}" class="btn btn-outline-info btn-sm px-3" style="min-height: 38px; display: inline-flex; align-items: center;">
                        <i class="fa-solid fa-arrow-down-left me-1"></i> Incoming Registry
                    </a>
                    <a href="{{ route('letters.index', ['tab' => 'all', 'type' => 'outgoing']) }}" class="btn btn-outline-warning text-dark btn-sm px-3" style="min-height: 38px; display: inline-flex; align-items: center;">
                        <i class="fa-solid fa-arrow-up-right me-1"></i> Outgoing Registry
                    </a>
                    <a href="{{ route('employee-letters.create') }}" class="btn btn-outline-success btn-sm px-3" style="min-height: 38px; display: inline-flex; align-items: center;">
                        <i class="fa-solid fa-file-pen me-1"></i> Employee Letter
                    </a>
                    <a href="{{ \Illuminate\Support\Facades\Route::has('office-requests.create') ? route('office-requests.create') : url('/office-requests/create') }}" class="btn btn-outline-secondary btn-sm px-3" style="min-height: 38px; display: inline-flex; align-items: center;">
                        <i class="fa-solid fa-boxes-stacked me-1"></i> Request Supplies
                    </a>
                    <a href="{{ route('expense-requests.index') }}" class="btn btn-outline-dark btn-sm px-3" style="min-height: 38px; display: inline-flex; align-items: center;">
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
            <div class="card border-0 shadow-sm rounded-3 mb-4 bg-white">
                <div class="card-header bg-white border-bottom py-3 d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <div>
                        <h6 class="mb-0 fw-bold text-dark">
                            <i class="fa-solid fa-clock-rotate-left text-primary me-2"></i>Letter History &amp; Registry
                        </h6>
                        <small class="text-muted">Browse incoming, outgoing, and employee letters with status tracking</small>
                    </div>
                    <div class="d-flex gap-2">
                        <a href="{{ route('letters.index', ['tab' => 'all']) }}" class="btn btn-sm btn-primary rounded-pill px-3" style="min-height: 34px; display: inline-flex; align-items: center;">
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
                        <div class="table-responsive wc-table-mobile-cards">
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
                                        <td data-label="Letter #" class="ps-3 fw-bold">
                                            <a href="{{ route('letters.show', $letter->id) }}" class="text-decoration-none text-primary font-monospace">
                                                {{ $letter->letter_number ?: '#'.$letter->id }}
                                            </a>
                                            @if($letter->priority === 'urgent')
                                                <span class="badge bg-danger ms-1" style="font-size:0.6rem;"><i class="fa-solid fa-fire me-1"></i>URGENT</span>
                                            @endif
                                        </td>
                                        <td data-label="Type">
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
                                        <td data-label="Subject">
                                            <div class="fw-semibold text-dark text-truncate" style="max-width: 220px;" title="{{ $letter->subject }}">
                                                {{ $letter->subject }}
                                            </div>
                                            @if($letter->specification)
                                                <small class="text-muted text-truncate d-block" style="max-width: 220px;">
                                                    {{ Str::limit($letter->specification, 45) }}
                                                </small>
                                            @endif
                                        </td>
                                        <td data-label="Date">
                                            <small class="text-muted">{{ optional($letter->date)->format('d M Y') ?? '-' }}</small>
                                        </td>
                                        <td data-label="Party">
                                            <small class="text-dark fw-semibold d-block text-truncate" style="max-width: 180px;">
                                                {{ $letter->type === 'incoming' ? ($letter->sender ?: 'External Sender') : ($letter->recipient_organization ?: 'Recipient') }}
                                            </small>
                                            @if($letter->attachments && $letter->attachments->count() > 0)
                                                <span class="badge bg-light text-muted border px-1" style="font-size: 0.65rem;">
                                                    <i class="fa-solid fa-paperclip me-1"></i>{{ $letter->attachments->count() }} file(s)
                                                </span>
                                            @endif
                                        </td>
                                        <td data-label="Status" class="text-center">
                                            <x-status-badge :status="$letter->status ?? 'pending'" />
                                        </td>
                                        <td data-label="Action" class="text-end pe-3">
                                            <a href="{{ route('letters.show', $letter->id) }}" class="btn btn-sm btn-outline-primary rounded-pill px-2" style="min-width: 32px; min-height: 32px; display: inline-flex; align-items: center; justify-content: center;" title="View Full Details">
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
                        <div class="table-responsive wc-table-mobile-cards">
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
                                        <td data-label="Letter #" class="ps-3 fw-bold">
                                            <a href="{{ route('letters.show', $letter->id) }}" class="text-decoration-none text-info font-monospace">
                                                {{ $letter->letter_number ?: '#'.$letter->id }}
                                            </a>
                                            @if($letter->priority === 'urgent')
                                                <span class="badge bg-danger ms-1" style="font-size:0.6rem;"><i class="fa-solid fa-fire me-1"></i>URGENT</span>
                                            @endif
                                        </td>
                                        <td data-label="Subject">
                                            <div class="fw-semibold text-dark text-truncate" style="max-width: 240px;">{{ $letter->subject }}</div>
                                            @if($letter->specification)
                                                <small class="text-muted text-truncate d-block" style="max-width: 240px;">{{ Str::limit($letter->specification, 45) }}</small>
                                            @endif
                                        </td>
                                        <td data-label="Sender">
                                            <span class="fw-semibold text-dark">{{ $letter->sender ?: 'External Entity' }}</span>
                                            @if($letter->sender_department)
                                                <small class="text-muted d-block">{{ $letter->sender_department }}</small>
                                            @endif
                                        </td>
                                        <td data-label="Date">
                                            <small class="text-muted">{{ optional($letter->date)->format('d M Y') ?? '-' }}</small>
                                        </td>
                                        <td data-label="Attachments">
                                            @if($letter->attachments && $letter->attachments->count() > 0)
                                                <span class="badge bg-light text-muted border">
                                                    <i class="fa-solid fa-paperclip text-info me-1"></i>{{ $letter->attachments->count() }} file(s)
                                                </span>
                                            @else
                                                <small class="text-muted">—</small>
                                            @endif
                                        </td>
                                        <td data-label="Status" class="text-center">
                                            <x-status-badge :status="$letter->status ?? 'pending'" />
                                        </td>
                                        <td data-label="Action" class="text-end pe-3">
                                            <a href="{{ route('letters.show', $letter->id) }}" class="btn btn-sm btn-outline-info rounded-pill px-2" style="min-width: 32px; min-height: 32px; display: inline-flex; align-items: center; justify-content: center;">
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
                        <div class="table-responsive wc-table-mobile-cards">
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
                                        <td data-label="Letter #" class="ps-3 fw-bold">
                                            <a href="{{ route('letters.show', $letter->id) }}" class="text-decoration-none text-warning font-monospace">
                                                {{ $letter->letter_number ?: '#'.$letter->id }}
                                            </a>
                                            @if($letter->priority === 'urgent')
                                                <span class="badge bg-danger ms-1" style="font-size:0.6rem;"><i class="fa-solid fa-fire me-1"></i>URGENT</span>
                                            @endif
                                        </td>
                                        <td data-label="Subject">
                                            <div class="fw-semibold text-dark text-truncate" style="max-width: 240px;">{{ $letter->subject }}</div>
                                            @if($letter->specification)
                                                <small class="text-muted text-truncate d-block" style="max-width: 240px;">{{ Str::limit($letter->specification, 45) }}</small>
                                            @endif
                                        </td>
                                        <td data-label="Recipient">
                                            <span class="fw-semibold text-dark">{{ $letter->recipient_organization ?: 'Recipient Organization' }}</span>
                                        </td>
                                        <td data-label="Date">
                                            <small class="text-muted">{{ optional($letter->date)->format('d M Y') ?? '-' }}</small>
                                        </td>
                                        <td data-label="Registered By">
                                            <small class="text-muted">{{ $letter->creator->name ?? 'Secretary' }}</small>
                                        </td>
                                        <td data-label="Status" class="text-center">
                                            <x-status-badge :status="$letter->status ?? 'pending'" />
                                        </td>
                                        <td data-label="Action" class="text-end pe-3">
                                            <a href="{{ route('letters.show', $letter->id) }}" class="btn btn-sm btn-outline-warning text-dark rounded-pill px-2" style="min-width: 32px; min-height: 32px; display: inline-flex; align-items: center; justify-content: center;">
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
                        <div class="table-responsive wc-table-mobile-cards">
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
                                        <td data-label="Ref #" class="ps-3 fw-bold">
                                            <a href="{{ route('employee-letters.show', $empLtr->id) }}" class="text-decoration-none text-primary font-monospace">
                                                {{ $empLtr->reference_number ?: 'LTR-#'.$empLtr->id }}
                                            </a>
                                        </td>
                                        <td data-label="Employee">
                                            <span class="fw-semibold text-dark">{{ $empLtr->employee->full_name ?? 'N/A' }}</span>
                                            <small class="text-muted font-monospace d-block">{{ $empLtr->employee->employee_code ?? '' }}</small>
                                        </td>
                                        <td data-label="Type">
                                            <span class="badge {{ $empLtr->badge_class ?? 'bg-secondary' }}">
                                                <i class="{{ $empLtr->icon ?? 'fa-solid fa-file-lines' }} me-1"></i>{{ $empLtr->type_label }}
                                            </span>
                                        </td>
                                        <td data-label="Subject">
                                            <span class="text-dark fw-semibold text-truncate d-block" style="max-width: 200px;">{{ $empLtr->title }}</span>
                                        </td>
                                        <td data-label="Date">
                                            <small class="text-muted">{{ optional($empLtr->issued_date)->format('d M Y') ?? '-' }}</small>
                                        </td>
                                        <td data-label="Ack. Status" class="text-center">
                                            <x-status-badge :status="$empLtr->acknowledgement_status ?? 'acknowledged'" />
                                        </td>
                                        <td data-label="Action" class="text-end pe-3">
                                            <a href="{{ route('employee-letters.show', $empLtr->id) }}" class="btn btn-sm btn-outline-secondary rounded-pill px-2" style="min-width: 32px; min-height: 32px; display: inline-flex; align-items: center; justify-content: center;">
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
            <div class="card border-0 shadow-sm rounded-3 bg-white">
                <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <h6 class="mb-0 fw-bold text-dark">
                        <i class="fa-solid fa-boxes-stacked text-warning me-2"></i>My Office Supply Requests (የቢሮ እቃዎች ጥያቄዎች)
                    </h6>
                    <div class="d-flex gap-2">
                        <a href="{{ \Illuminate\Support\Facades\Route::has('office-requests.create') ? route('office-requests.create') : url('/office-requests/create') }}" class="btn btn-sm btn-primary rounded-pill px-3" style="min-height: 34px; display: inline-flex; align-items: center;">
                            <i class="fa-solid fa-plus me-1"></i> New Request
                        </a>
                        <a href="{{ \Illuminate\Support\Facades\Route::has('office-requests.index') ? route('office-requests.index') : url('/office-requests') }}" class="btn btn-sm btn-outline-secondary rounded-pill px-3" style="min-height: 34px; display: inline-flex; align-items: center;">View All</a>
                    </div>
                </div>
                <div class="table-responsive wc-table-mobile-cards">
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
                                <td data-label="PR #" class="ps-3">
                                    <span class="fw-bold text-primary font-monospace" style="font-size:0.8rem;">{{ $offReq->pr_no }}</span>
                                </td>
                                <td data-label="Purpose">
                                    <span class="fw-semibold text-dark">{{ $offReq->office_purpose ?: 'Office Supplies' }}</span>
                                </td>
                                <td data-label="Items">
                                    <small class="text-muted">{{ $offReq->items->count() }} items</small>
                                </td>
                                <td data-label="Status" class="text-center">
                                    <x-status-badge :status="$offReq->status_label ?? $offReq->status" />
                                </td>
                                <td data-label="Date" class="text-center">
                                    <small class="text-muted">{{ optional($offReq->created_at)->format('d M Y') }}</small>
                                </td>
                                <td data-label="Action" class="text-end pe-3">
                                    <a href="{{ \Illuminate\Support\Facades\Route::has('office-requests.show') ? route('office-requests.show', $offReq) : url('/office-requests/' . $offReq->id) }}" class="btn btn-sm btn-outline-secondary rounded-pill px-2" style="min-width: 32px; min-height: 32px; display: inline-flex; align-items: center; justify-content: center;">
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
            <div class="card border-0 shadow-sm rounded-3 mb-4 bg-white">
                <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center">
                    <h6 class="mb-0 fw-bold text-dark">
                        <i class="fa-solid fa-folder-tree text-info me-2"></i>Letters &amp; Correspondence Navigation
                    </h6>
                </div>
                <div class="card-body p-0">
                    <div class="list-group list-group-flush">
                        <a href="{{ route('letters.index', ['tab' => 'all']) }}" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center px-3 py-3">
                            <span><i class="fa-solid fa-clock-rotate-left text-primary me-2"></i><strong>All Letter History</strong></span>
                            <span class="badge bg-primary rounded-pill">{{ $totalLetters }}</span>
                        </a>
                        <a href="{{ route('letters.index', ['tab' => 'all', 'type' => 'incoming']) }}" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center px-3 py-3">
                            <span><i class="fa-solid fa-arrow-down-left text-info me-2"></i><strong>Incoming Letters (ገቢ)</strong></span>
                            <span class="badge bg-info text-dark rounded-pill">{{ $incomingLettersCount }}</span>
                        </a>
                        <a href="{{ route('letters.index', ['tab' => 'all', 'type' => 'outgoing']) }}" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center px-3 py-3">
                            <span><i class="fa-solid fa-arrow-up-right text-warning me-2"></i><strong>Outgoing Letters (ወጪ)</strong></span>
                            <span class="badge bg-warning text-dark rounded-pill">{{ $outgoingLettersCount }}</span>
                        </a>
                        <a href="{{ route('employee-letters.index') }}" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center px-3 py-3">
                            <span><i class="fa-solid fa-file-signature text-success me-2"></i><strong>Employee Official Letters</strong></span>
                            <span class="badge bg-success rounded-pill">{{ $employeeLettersCount }}</span>
                        </a>
                        <a href="{{ route('letters.create') }}" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center px-3 py-3 text-primary fw-semibold">
                            <span><i class="fa-solid fa-plus-circle text-primary me-2"></i>Register New Letter</span>
                            <i class="fa-solid fa-chevron-right text-muted small"></i>
                        </a>
                    </div>
                </div>
            </div>

            {{-- Head Office Purchase Requests Card --}}
            <div class="card border-0 shadow-sm rounded-3 mb-4 bg-white">
                <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center">
                    <h6 class="mb-0 fw-bold text-dark">
                        <i class="fa-solid fa-cart-shopping text-primary me-2"></i>Head Office Purchase Requests
                    </h6>
                    <div class="d-flex gap-1">
                        <a href="{{ route('purchase-requests.create') }}" class="btn btn-sm btn-outline-success rounded-pill px-2" style="min-height: 30px; display: inline-flex; align-items: center;" title="New Purchase Request">
                            <i class="fa-solid fa-plus me-1"></i> New
                        </a>
                        <a href="{{ route('procurement.my-queue') }}" class="btn btn-sm btn-outline-primary rounded-pill px-2" style="min-height: 30px; display: inline-flex; align-items: center;" title="View All in My Queue">
                            <i class="fa-solid fa-list me-1"></i> All
                        </a>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="list-group list-group-flush">
                        @forelse($recentPurchaseRequests ?? [] as $pr)
                            <a href="{{ route('purchase-requests.show', $pr->id) }}" class="list-group-item list-group-item-action px-3 py-3">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <div class="fw-bold text-primary font-monospace" style="font-size:0.82rem;">{{ $pr->pr_no }}</div>
                                        <small class="text-muted">{{ $pr->created_at->format('M d, Y') }} &bull; {{ $pr->items->count() }} item(s)</small>
                                    </div>
                                    <x-status-badge :status="$pr->status" />
                                </div>
                            </a>
                        @empty
                            <div class="p-3 text-center text-muted small">
                                No Head Office purchase requests created yet.
                                <a href="{{ route('purchase-requests.create') }}" class="d-block mt-1">Create one now &rarr;</a>
                            </div>
                        @endforelse
                    </div>
                </div>
            </div>

            {{-- My Expense Requests --}}
            <div class="card border-0 shadow-sm rounded-3 bg-white">
                <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center">
                    <h6 class="mb-0 fw-bold text-dark">
                        <i class="fa-solid fa-hand-holding-dollar text-success me-2"></i>My Ask Money (Petty Cash)
                    </h6>
                    <a href="{{ route('expense-requests.index') }}" class="btn btn-sm btn-outline-success rounded-pill px-3" style="min-height: 30px; display: inline-flex; align-items: center;">View All</a>
                </div>
                <div class="card-body p-0">
                    <ul class="list-group list-group-flush">
                        @forelse($myExpenseRequests as $exp)
                        <li class="list-group-item px-3 py-3">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <span class="fw-semibold d-block text-dark" style="font-size:0.85rem;">{{ $exp->purpose ?? $exp->title ?? 'Request #'.$exp->id }}</span>
                                    <small class="text-muted font-tabular">Br {{ number_format($exp->amount ?? 0, 2) }}</small>
                                </div>
                                <x-status-badge :status="$exp->status ?? 'Pending'" />
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
