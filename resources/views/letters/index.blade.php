@extends('layouts.app')

@section('title', 'My Letters - Correspondence Inbox')

@section('content')
<div class="container-fluid py-3">

    {{-- Header --}}
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
        <div>
            <h3 class="fw-bold mb-1">
                <i class="fa-solid fa-inbox text-primary me-2"></i>Correspondence & Letters
                <span class="fs-6 text-muted ms-2">(Official Organization Letters Inbox)</span>
            </h3>
            <p class="text-muted small mb-0">View incoming/outgoing letters dispatched to you or your department, preview attachments, and redirect or resolve letters.</p>
        </div>
        <div class="d-flex gap-2">
            @if(auth()->user()->hasAnyRole(['admin', 'global_admin', 'secretary']))
            <a href="{{ route('letters.create') }}" class="btn btn-primary shadow-sm px-3 fw-bold">
                <i class="fa-solid fa-plus me-1"></i> New Letter
            </a>
            <a href="{{ route('letters.dashboard') }}" class="btn btn-outline-secondary shadow-sm px-3">
                <i class="fa-solid fa-chart-line me-1"></i> Dashboard
            </a>
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

    {{-- Navigation Tabs --}}
    <div class="card border-0 shadow-sm rounded-3 mb-4">
        <div class="card-header bg-white p-0 border-bottom">
            <ul class="nav nav-tabs card-header-tabs m-0 border-0">
                <li class="nav-item">
                    <a class="nav-link px-4 py-3 fw-semibold {{ $tab === 'inbox' ? 'active border-bottom border-primary border-3 text-primary bg-light' : 'text-secondary' }}" 
                       href="{{ route('letters.index', ['tab' => 'inbox']) }}">
                        <i class="fa-solid fa-inbox me-2"></i>My Inbox
                        @if($inboxCount > 0)
                            <span class="badge bg-danger rounded-pill ms-2">{{ $inboxCount }}</span>
                        @endif
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link px-4 py-3 fw-semibold {{ $tab === 'sent' ? 'active border-bottom border-primary border-3 text-primary bg-light' : 'text-secondary' }}" 
                       href="{{ route('letters.index', ['tab' => 'sent']) }}">
                        <i class="fa-solid fa-paper-plane me-2"></i>Sent / Created by Me
                        <span class="badge bg-secondary bg-opacity-25 text-dark rounded-pill ms-2">{{ $sentCount }}</span>
                    </a>
                </li>
                @if($isAdminOrSecretary)
                <li class="nav-item">
                    <a class="nav-link px-4 py-3 fw-semibold {{ $tab === 'all' ? 'active border-bottom border-primary border-3 text-primary bg-light' : 'text-secondary' }}" 
                       href="{{ route('letters.index', ['tab' => 'all']) }}">
                        <i class="fa-solid fa-layer-group me-2"></i>All Organization Letters
                        <span class="badge bg-primary bg-opacity-10 text-primary rounded-pill ms-2">{{ $allCount }}</span>
                    </a>
                </li>
                @endif
            </ul>
        </div>

        {{-- Filters Bar --}}
        <div class="card-body p-3 bg-light border-bottom">
            <form method="GET" action="{{ route('letters.index') }}" class="row g-2 align-items-center">
                <input type="hidden" name="tab" value="{{ $tab }}">

                <div class="col-lg-3 col-md-6">
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-white"><i class="fa-solid fa-magnifying-glass text-muted"></i></span>
                        <input type="text" name="search" class="form-control" placeholder="Search letter #, subject, sender..." value="{{ request('search') }}">
                    </div>
                </div>

                <div class="col-lg-2 col-md-3">
                    <select name="type" class="form-select form-select-sm" onchange="this.form.submit()">
                        <option value="">-- All Types --</option>
                        <option value="incoming" {{ request('type') === 'incoming' ? 'selected' : '' }}>Incoming</option>
                        <option value="outgoing" {{ request('type') === 'outgoing' ? 'selected' : '' }}>Outgoing</option>
                    </select>
                </div>

                <div class="col-lg-2 col-md-3">
                    <select name="status" class="form-select form-select-sm" onchange="this.form.submit()">
                        <option value="">-- All Statuses --</option>
                        <option value="pending" {{ request('status') === 'pending' ? 'selected' : '' }}>Pending</option>
                        <option value="viewed" {{ request('status') === 'viewed' ? 'selected' : '' }}>Viewed</option>
                        <option value="redirected" {{ request('status') === 'redirected' ? 'selected' : '' }}>Redirected</option>
                        <option value="closed" {{ request('status') === 'closed' ? 'selected' : '' }}>Closed</option>
                    </select>
                </div>

                <div class="col-lg-2 col-md-3">
                    <select name="priority" class="form-select form-select-sm" onchange="this.form.submit()">
                        <option value="">-- Priority --</option>
                        <option value="normal" {{ request('priority') === 'normal' ? 'selected' : '' }}>Normal</option>
                        <option value="urgent" {{ request('priority') === 'urgent' ? 'selected' : '' }}>Urgent</option>
                    </select>
                </div>

                <div class="col-lg-3 col-md-9 d-flex gap-2">
                    <button type="submit" class="btn btn-sm btn-primary px-3">
                        <i class="fa-solid fa-filter me-1"></i> Filter
                    </button>
                    @if(request('search') || request('type') || request('status') || request('priority') || request('date_from') || request('date_to'))
                        <a href="{{ route('letters.index', ['tab' => $tab]) }}" class="btn btn-sm btn-outline-danger">
                            <i class="fa-solid fa-xmark"></i> Clear
                        </a>
                    @endif
                </div>
            </form>
        </div>

        {{-- Letters Table --}}
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-3">Letter #</th>
                            <th>Type</th>
                            <th>Subject</th>
                            <th>Date</th>
                            <th>Origin / Destination</th>
                            <th>Current Assignee</th>
                            <th>Attachments</th>
                            <th>Status</th>
                            <th class="text-end pe-3">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($letters as $letter)
                        <tr>
                            {{-- Letter # & Priority --}}
                            <td class="ps-3 fw-bold">
                                <a href="{{ route('letters.show', $letter->id) }}" class="text-decoration-none text-primary fw-bold">
                                    {{ $letter->letter_number }}
                                </a>
                                @if($letter->priority === 'urgent')
                                    <span class="badge bg-danger ms-1" style="font-size: 0.65rem;"><i class="fa-solid fa-fire me-1"></i>URGENT</span>
                                @endif
                            </td>

                            {{-- Type --}}
                            <td>
                                @if($letter->type === 'incoming')
                                    <span class="badge bg-info bg-opacity-10 text-info border border-info border-opacity-25">
                                        <i class="fa-solid fa-arrow-down-left me-1"></i> Incoming
                                    </span>
                                @else
                                    <span class="badge bg-warning bg-opacity-10 text-warning border border-warning border-opacity-25">
                                        <i class="fa-solid fa-arrow-up-right me-1"></i> Outgoing
                                    </span>
                                @endif
                            </td>

                            {{-- Subject --}}
                            <td>
                                <div class="fw-semibold text-dark text-truncate" style="max-width: 250px;" title="{{ $letter->subject }}">
                                    {{ $letter->subject }}
                                </div>
                                <small class="text-muted text-truncate d-block" style="max-width: 250px;">
                                    {{ Str::limit($letter->specification, 60) }}
                                </small>
                            </td>

                            {{-- Date --}}
                            <td class="small text-muted text-nowrap">
                                <i class="fa-regular fa-calendar me-1"></i>{{ $letter->date ? $letter->date->format('M d, Y') : '-' }}
                            </td>

                            {{-- Origin / Destination --}}
                            <td class="small">
                                @if($letter->type === 'incoming')
                                    <span class="fw-semibold text-dark">{{ $letter->sender ?? 'External Sender' }}</span>
                                    @if($letter->sender_department)
                                        <small class="text-muted d-block">{{ $letter->sender_department }}</small>
                                    @endif
                                @else
                                    <span class="fw-semibold text-dark">{{ $letter->recipient_organization ?? 'External Destination' }}</span>
                                @endif
                            </td>

                            {{-- Current Assignee --}}
                            <td class="small">
                                @if($letter->latestRecipient)
                                    <span class="badge bg-light text-dark border">
                                        <i class="fa-solid fa-user-tag me-1 text-primary"></i>{{ $letter->latestRecipient->recipient_label }}
                                    </span>
                                @else
                                    <span class="text-muted">General</span>
                                @endif
                            </td>

                            {{-- Attachments --}}
                            <td>
                                @if($letter->attachments->count() > 0)
                                    <span class="badge bg-secondary bg-opacity-10 text-dark border">
                                        <i class="fa-solid fa-paperclip text-primary me-1"></i>{{ $letter->attachments->count() }} file(s)
                                    </span>
                                @else
                                    <span class="text-muted small">None</span>
                                @endif
                            </td>

                            {{-- Status --}}
                            <td>
                                @php
                                    $badgeClass = match($letter->status) {
                                        'pending'    => 'bg-warning text-dark',
                                        'viewed'     => 'bg-info text-dark',
                                        'redirected' => 'bg-primary text-white',
                                        'closed'     => 'bg-success text-white',
                                        default      => 'bg-secondary text-white'
                                    };
                                @endphp
                                <span class="badge {{ $badgeClass }}">{{ ucfirst($letter->status) }}</span>
                                @if($letter->payment_amount > 0)
                                    <div class="mt-1">
                                        <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 fw-semibold" style="font-size: 0.68rem;">
                                            <i class="fa-solid fa-coins me-1"></i>ETB {{ number_format($letter->payment_amount, 2) }}
                                        </span>
                                    </div>
                                @endif
                            </td>

                            {{-- Action --}}
                            <td class="text-end pe-3 text-nowrap">
                                <a href="{{ route('letters.show', $letter->id) }}" class="btn btn-sm btn-outline-primary px-2">
                                    <i class="fa-solid fa-eye me-1"></i> Review & Action
                                </a>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="9" class="text-center py-5 text-muted">
                                <i class="fa-solid fa-inbox fa-3x mb-3 text-muted opacity-50 d-block"></i>
                                <h5>No letters found in this view</h5>
                                <p class="small text-muted mb-3">When letters are sent or redirected to you, they will appear here.</p>
                                @if(auth()->user()->hasAnyRole(['admin', 'global_admin', 'secretary']))
                                    <a href="{{ route('letters.create') }}" class="btn btn-sm btn-primary">
                                        <i class="fa-solid fa-plus me-1"></i> Create First Letter
                                    </a>
                                @endif
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        @if($letters->hasPages())
        <div class="card-footer bg-white border-top py-3">
            {{ $letters->links() }}
        </div>
        @endif
    </div>
</div>
@endsection
