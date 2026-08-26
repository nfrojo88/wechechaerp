@extends('layouts.app')
@section('title', 'Secretary Dashboard')

@section('content')
<div class="container-fluid px-4">

    {{-- ── Header ──────────────────────────────────────────────────────────────── --}}
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
        <div>
            <h1 class="h3 mb-1 fw-bold text-dark">
                <i class="fa-solid fa-id-badge text-primary me-2"></i>Secretary Dashboard
            </h1>
            <p class="mb-0 text-muted small">
                Welcome, <strong>{{ auth()->user()->name }}</strong> — {{ now()->format('l, F j Y') }}
            </p>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <a href="{{ route('office-requests.create') }}" class="btn btn-warning shadow-sm text-dark fw-bold">
                <i class="fa-solid fa-boxes-stacked me-1"></i> New Office Request (የቢሮ እቃ መጠየቅ)
            </a>
            <a href="{{ route('letters.create') }}" class="btn btn-primary shadow-sm">
                <i class="fa-solid fa-pen-to-square me-1"></i> Register Letter
            </a>
            <a href="{{ route('letters.index') }}" class="btn btn-outline-secondary shadow-sm">
                <i class="fa-solid fa-inbox me-1"></i> All Letters
            </a>
        </div>
    </div>

    {{-- ── KPI Summary Cards ─────────────────────────────────────────────────── --}}
    <div class="row g-3 mb-4">
        {{-- Total Letters --}}
        <div class="col-6 col-md-4 col-xl">
            <div class="card border-0 shadow-sm rounded-3 h-100" style="border-left: 4px solid #6366f1 !important;">
                <div class="card-body p-3">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <p class="text-muted fw-semibold mb-1" style="font-size: 0.72rem; letter-spacing: .06em; text-transform: uppercase;">Total Letters</p>
                            <h3 class="fw-bold text-dark mb-0">{{ $totalLetters }}</h3>
                        </div>
                        <div class="p-2 rounded-3" style="background: rgba(99,102,241,.12);">
                            <i class="fa-solid fa-envelope fa-lg" style="color:#6366f1;"></i>
                        </div>
                    </div>
                    <small class="text-muted mt-2 d-block" style="font-size:0.73rem;">All registered correspondence</small>
                </div>
            </div>
        </div>

        {{-- Pending / Active Letters --}}
        <div class="col-6 col-md-4 col-xl">
            <div class="card border-0 shadow-sm rounded-3 h-100" style="border-left: 4px solid #f59e0b !important;">
                <div class="card-body p-3">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <p class="text-muted fw-semibold mb-1" style="font-size: 0.72rem; letter-spacing: .06em; text-transform: uppercase;">Pending Letters</p>
                            <h3 class="fw-bold text-warning mb-0">{{ $pendingLetters }}</h3>
                        </div>
                        <div class="p-2 rounded-3" style="background: rgba(245,158,11,.12);">
                            <i class="fa-solid fa-clock fa-lg text-warning"></i>
                        </div>
                    </div>
                    <small class="text-muted mt-2 d-block" style="font-size:0.73rem;">Awaiting decision or response</small>
                </div>
            </div>
        </div>

        {{-- Office Supply Requests --}}
        <div class="col-6 col-md-4 col-xl">
            <a href="{{ route('office-requests.index') }}" class="text-decoration-none">
                <div class="card border-0 shadow-sm rounded-3 h-100" style="border-left: 4px solid #f97316 !important;">
                    <div class="card-body p-3">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <p class="text-muted fw-semibold mb-1" style="font-size: 0.72rem; letter-spacing: .06em; text-transform: uppercase;">Office Supplies (የቢሮ እቃ)</p>
                                <h3 class="fw-bold text-dark mb-0">{{ $myOfficeRequestsCount ?? 0 }}</h3>
                            </div>
                            <div class="p-2 rounded-3" style="background: rgba(249,115,22,.12);">
                                <i class="fa-solid fa-boxes-stacked fa-lg" style="color:#f97316;"></i>
                            </div>
                        </div>
                        @if(($pendingOfficeRequestsCount ?? 0) > 0)
                            <small class="text-warning fw-semibold mt-2 d-block" style="font-size:0.73rem;">
                                <i class="fa-solid fa-hourglass-half me-1"></i>{{ $pendingOfficeRequestsCount }} Pending HR Review
                            </small>
                        @else
                            <small class="text-muted mt-2 d-block" style="font-size:0.73rem;">Your supply requisitions</small>
                        @endif
                    </div>
                </div>
            </a>
        </div>

        {{-- Closed / Resolved Letters --}}
        <div class="col-6 col-md-4 col-xl">
            <div class="card border-0 shadow-sm rounded-3 h-100" style="border-left: 4px solid #10b981 !important;">
                <div class="card-body p-3">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <p class="text-muted fw-semibold mb-1" style="font-size: 0.72rem; letter-spacing: .06em; text-transform: uppercase;">Closed Letters</p>
                            <h3 class="fw-bold text-success mb-0">{{ $closedLetters }}</h3>
                        </div>
                        <div class="p-2 rounded-3" style="background: rgba(16,185,129,.12);">
                            <i class="fa-solid fa-circle-check fa-lg text-success"></i>
                        </div>
                    </div>
                    <small class="text-muted mt-2 d-block" style="font-size:0.73rem;">Completed letters</small>
                </div>
            </div>
        </div>

        {{-- My Registered Letters --}}
        <div class="col-6 col-md-4 col-xl">
            <div class="card border-0 shadow-sm rounded-3 h-100" style="border-left: 4px solid #3b82f6 !important;">
                <div class="card-body p-3">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <p class="text-muted fw-semibold mb-1" style="font-size: 0.72rem; letter-spacing: .06em; text-transform: uppercase;">My Letters</p>
                            <h3 class="fw-bold text-primary mb-0">{{ $myLettersCount }}</h3>
                        </div>
                        <div class="p-2 rounded-3" style="background: rgba(59,130,246,.12);">
                            <i class="fa-solid fa-user-pen fa-lg text-primary"></i>
                        </div>
                    </div>
                    <small class="text-muted mt-2 d-block" style="font-size:0.73rem;">Created under your account</small>
                </div>
            </div>
        </div>
    </div>

    {{-- ── Quick Actions ─────────────────────────────────────────────────────── --}}
    <div class="row g-3 mb-4">
        <div class="col-12">
            <div class="card border-0 shadow-sm rounded-3 bg-white">
                <div class="card-body p-3">
                    <p class="text-muted fw-semibold mb-3" style="font-size:0.8rem; text-transform:uppercase; letter-spacing:.05em;">Quick Actions</p>
                    <div class="d-flex flex-wrap gap-2">
                        <a href="{{ route('letters.create') }}" class="btn btn-primary btn-sm px-3">
                            <i class="fa-solid fa-plus me-1"></i> Register New Letter
                        </a>
                        <a href="{{ route('letters.index', ['tab' => 'inbox']) }}" class="btn btn-outline-info btn-sm px-3">
                            <i class="fa-solid fa-inbox me-1"></i> Letter Inbox
                        </a>
                        <a href="{{ route('letters.index', ['tab' => 'sent']) }}" class="btn btn-outline-secondary btn-sm px-3">
                            <i class="fa-solid fa-paper-plane me-1"></i> Sent Letters
                        </a>
                        <a href="{{ route('letters.index', ['tab' => 'all']) }}" class="btn btn-outline-dark btn-sm px-3">
                            <i class="fa-solid fa-list me-1"></i> All Correspondence
                        </a>
                        <a href="{{ route('office-requests.create') }}" class="btn btn-warning btn-sm px-3 text-dark fw-semibold">
                            <i class="fa-solid fa-boxes-stacked me-1"></i> Request Office Supplies (የቢሮ እቃ መጠየቅ)
                        </a>
                        <a href="{{ route('office-requests.index') }}" class="btn btn-outline-warning btn-sm px-3 text-dark">
                            <i class="fa-solid fa-clipboard-list me-1"></i> My Office Requests ({{ $myOfficeRequestsCount ?? 0 }})
                        </a>
                        <a href="{{ route('expense-requests.index') }}" class="btn btn-outline-success btn-sm px-3">
                            <i class="fa-solid fa-hand-holding-dollar me-1"></i> Ask Money
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ── Main Content Columns ─────────────────────────────────────────────── --}}
    <div class="row g-4">

        {{-- Left: Recent Letters (All) --}}
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm rounded-3 mb-4">
                <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center">
                    <h6 class="mb-0 fw-bold text-dark">
                        <i class="fa-solid fa-envelope-open-text text-primary me-2"></i>Recent Correspondence
                    </h6>
                    <a href="{{ route('letters.index') }}" class="btn btn-sm btn-outline-primary">View All</a>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-3">Ref #</th>
                                <th>Subject</th>
                                <th>Type</th>
                                <th>Registered By</th>
                                <th class="text-center">Status</th>
                                <th class="text-center">Date</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($recentLetters as $letter)
                            <tr>
                                <td class="ps-3">
                                    <span class="fw-bold text-primary font-monospace" style="font-size:0.8rem;">{{ $letter->reference_number ?? '#'.$letter->id }}</span>
                                </td>
                                <td>
                                    <span class="fw-semibold text-dark d-block" style="max-width:220px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">
                                        {{ $letter->subject }}
                                    </span>
                                    <small class="text-muted">{{ Str::limit($letter->sender_name ?? '', 30) }}</small>
                                </td>
                                <td>
                                    <span class="badge bg-info text-dark">{{ ucfirst($letter->letter_type ?? 'General') }}</span>
                                </td>
                                <td>
                                    <small class="text-muted">{{ $letter->creator->name ?? 'N/A' }}</small>
                                </td>
                                <td class="text-center">
                                    @php
                                        $statusColor = match($letter->status ?? '') {
                                            'closed'      => 'success',
                                            'pending'     => 'warning',
                                            'forwarded'   => 'info',
                                            'in_progress' => 'primary',
                                            default       => 'secondary',
                                        };
                                    @endphp
                                    <span class="badge bg-{{ $statusColor }} text-{{ in_array($letter->status, ['pending']) ? 'dark' : '' }}">
                                        {{ ucfirst(str_replace('_', ' ', $letter->status ?? 'Unknown')) }}
                                    </span>
                                </td>
                                <td class="text-center">
                                    <small class="text-muted">{{ optional($letter->created_at)->format('d M Y') }}</small>
                                </td>
                                <td>
                                    <a href="{{ route('letters.show', $letter) }}" class="btn btn-sm btn-outline-secondary">
                                        <i class="fa-solid fa-eye"></i>
                                    </a>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="7" class="text-center py-4 text-muted">
                                    <i class="fa-solid fa-inbox fa-2x mb-2 d-block opacity-25"></i>
                                    No letters registered yet.
                                    <a href="{{ route('letters.create') }}" class="d-block mt-1 small">Register your first letter →</a>
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- My Office Supply Requests Table --}}
            <div class="card border-0 shadow-sm rounded-3">
                <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center">
                    <h6 class="mb-0 fw-bold text-dark">
                        <i class="fa-solid fa-boxes-stacked text-warning me-2"></i>My Office Supply Requests (የቢሮ እቃዎች ጥያቄዎች)
                    </h6>
                    <div class="d-flex gap-2">
                        <a href="{{ route('office-requests.create') }}" class="btn btn-sm btn-primary">
                            <i class="fa-solid fa-plus me-1"></i> New Request
                        </a>
                        <a href="{{ route('office-requests.index') }}" class="btn btn-sm btn-outline-secondary">View All</a>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-3">PR #</th>
                                <th>Purpose / Category</th>
                                <th>Items</th>
                                <th class="text-center">Status</th>
                                <th class="text-center">Date</th>
                                <th></th>
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
                                <td>
                                    <a href="{{ route('office-requests.show', $offReq) }}" class="btn btn-sm btn-outline-secondary">
                                        <i class="fa-solid fa-eye"></i>
                                    </a>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="6" class="text-center py-4 text-muted">
                                    <i class="fa-solid fa-boxes-stacked fa-2x mb-2 d-block opacity-25"></i>
                                    No office supply requests yet.
                                    <a href="{{ route('office-requests.create') }}" class="d-block mt-1 small">Ask for office materials / stationery →</a>
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        {{-- Right column --}}
        <div class="col-lg-4">

            {{-- Correspondence Status Summary Card --}}
            <div class="card border-0 shadow-sm rounded-3 mb-4">
                <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center">
                    <h6 class="mb-0 fw-bold text-dark">
                        <i class="fa-solid fa-folder-tree text-info me-2"></i>Letters Navigation
                    </h6>
                </div>
                <div class="card-body p-0">
                    <div class="list-group list-group-flush">
                        <a href="{{ route('letters.index', ['tab' => 'inbox']) }}" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center px-3 py-2">
                            <span><i class="fa-solid fa-inbox text-primary me-2"></i>Inbox (Assigned To You)</span>
                            <i class="fa-solid fa-chevron-right text-muted small"></i>
                        </a>
                        <a href="{{ route('letters.index', ['tab' => 'sent']) }}" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center px-3 py-2">
                            <span><i class="fa-solid fa-paper-plane text-success me-2"></i>Sent Letters</span>
                            <i class="fa-solid fa-chevron-right text-muted small"></i>
                        </a>
                        <a href="{{ route('letters.index', ['tab' => 'all']) }}" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center px-3 py-2">
                            <span><i class="fa-solid fa-envelope-open-text text-info me-2"></i>All Correspondence</span>
                            <span class="badge bg-secondary rounded-pill">{{ $totalLetters }}</span>
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
                        <i class="fa-solid fa-hand-holding-dollar text-success me-2"></i>My Ask Money
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
                            No requests yet.
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
