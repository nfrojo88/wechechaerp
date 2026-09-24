@extends('layouts.app')

@section('title', 'My Maintenance Requests')

@section('content')
<div class="container-fluid py-4">

    {{-- ── Header ── --}}
    <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-3">
        <div>
            <h1 class="h4 fw-bold mb-0">
                <i class="fa-solid fa-clipboard-list text-warning me-2"></i>
                My Maintenance Requests
            </h1>
            <p class="text-muted small mb-0 mt-1">Track all maintenance requests you have submitted</p>
        </div>
        <a href="{{ route('foreman.fixed-assets') }}" class="btn btn-warning fw-semibold text-dark shadow-sm">
            <i class="fa-solid fa-plus me-1"></i> Report New Issue
        </a>
    </div>

    {{-- ── Flash Messages ── --}}
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm" role="alert">
            <i class="fa-solid fa-circle-check me-2"></i>{{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    {{-- ── Stats ── --}}
    <div class="row g-3 mb-4">
        @php
            $statConfig = [
                'pending'               => ['label' => 'Pending',              'color' => '#f59e0b', 'icon' => 'fa-clock'],
                'in_progress'           => ['label' => 'In Progress (GS)',     'color' => '#3b82f6', 'icon' => 'fa-wrench'],
                'sent_to_store_manager' => ['label' => 'Sent to Store Mgr',   'color' => '#8b5cf6', 'icon' => 'fa-paper-plane'],
                'resolved'              => ['label' => 'Resolved',             'color' => '#10b981', 'icon' => 'fa-circle-check'],
            ];
        @endphp
        @foreach($statConfig as $key => $cfg)
        <div class="col-6 col-lg-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body d-flex align-items-center gap-3 py-3">
                    <div class="rounded-circle d-flex align-items-center justify-content-center text-white flex-shrink-0"
                         style="width:46px;height:46px;background:{{ $cfg['color'] }};">
                        <i class="fa-solid {{ $cfg['icon'] }}"></i>
                    </div>
                    <div>
                        <div class="fs-4 fw-bold lh-1">{{ $stats[$key] ?? 0 }}</div>
                        <div class="small text-muted">{{ $cfg['label'] }}</div>
                    </div>
                </div>
            </div>
        </div>
        @endforeach
    </div>

    {{-- ── Status Filter ── --}}
    <div class="d-flex gap-2 mb-4 flex-wrap">
        <a href="{{ route('foreman.my-maintenance-requests') }}"
           class="btn btn-sm {{ !request('status') ? 'btn-dark' : 'btn-outline-secondary' }}">All</a>
        <a href="{{ route('foreman.my-maintenance-requests', ['status' => 'pending']) }}"
           class="btn btn-sm {{ request('status') === 'pending' ? 'btn-warning text-dark' : 'btn-outline-secondary' }}">Pending</a>
        <a href="{{ route('foreman.my-maintenance-requests', ['status' => 'in_progress']) }}"
           class="btn btn-sm {{ request('status') === 'in_progress' ? 'btn-primary' : 'btn-outline-secondary' }}">In Progress</a>
        <a href="{{ route('foreman.my-maintenance-requests', ['status' => 'sent_to_store_manager']) }}"
           class="btn btn-sm {{ request('status') === 'sent_to_store_manager' ? 'btn-info text-dark' : 'btn-outline-secondary' }}">Sent to Store Mgr</a>
        <a href="{{ route('foreman.my-maintenance-requests', ['status' => 'resolved']) }}"
           class="btn btn-sm {{ request('status') === 'resolved' ? 'btn-success' : 'btn-outline-secondary' }}">Resolved</a>
        <a href="{{ route('foreman.my-maintenance-requests', ['status' => 'closed']) }}"
           class="btn btn-sm {{ request('status') === 'closed' ? 'btn-secondary' : 'btn-outline-secondary' }}">Closed</a>
    </div>

    {{-- ── Requests List ── --}}
    @if($requests->isEmpty())
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center py-5 text-muted">
                <i class="fa-solid fa-clipboard-check fa-3x mb-3 opacity-25"></i>
                <p class="mb-2 fw-semibold">No maintenance requests found.</p>
                <p class="small mb-3">You haven't reported any maintenance issues yet.</p>
                <a href="{{ route('foreman.fixed-assets') }}" class="btn btn-warning text-dark">
                    <i class="fa-solid fa-wrench me-1"></i> Report a Maintenance Issue
                </a>
            </div>
        </div>
    @else
        <div class="d-flex flex-column gap-3">
            @foreach($requests as $mr)
            @php
                $statusBadge  = $mr->status_badge;
                $urgencyBadge = $mr->urgency_badge;
            @endphp
            <div class="card border-0 shadow-sm">
                <div class="card-body p-4">
                    <div class="row align-items-start g-3">

                        {{-- Left: Request Info --}}
                        <div class="col-md-8">
                            <div class="d-flex align-items-center gap-3 mb-2 flex-wrap">
                                <span class="font-monospace fw-bold text-primary fs-6">{{ $mr->request_no }}</span>
                                <span class="badge {{ $statusBadge['class'] }} px-3 py-2">
                                    <i class="fa-solid {{ $statusBadge['icon'] }} me-1"></i>{{ $statusBadge['label'] }}
                                </span>
                                <span class="badge {{ $urgencyBadge['class'] }} px-2">{{ $urgencyBadge['label'] }}</span>
                            </div>

                            <h6 class="fw-bold mb-1">{{ $mr->asset_name }}</h6>
                            @if($mr->asset_code)
                                <div class="text-muted small mb-2">
                                    <i class="fa-solid fa-tag me-1"></i>{{ $mr->asset_code }}
                                </div>
                            @endif
                            <div class="text-muted small mb-3">
                                <span class="me-3"><i class="fa-solid fa-triangle-exclamation me-1"></i>{{ $mr->issue_type_label }}</span>
                                <span><i class="fa-solid fa-calendar me-1"></i>Reported: {{ $mr->created_at->format('d M Y, H:i') }}</span>
                            </div>
                            <p class="text-secondary small mb-0" style="line-height:1.6;">
                                {{ Str::limit($mr->description, 200) }}
                            </p>
                        </div>

                        {{-- Right: Timeline --}}
                        <div class="col-md-4">
                            <div class="bg-light rounded-3 p-3">
                                <p class="fw-semibold small mb-3 text-dark">
                                    <i class="fa-solid fa-timeline me-1"></i> Request Timeline
                                </p>

                                {{-- Step 1: Submitted --}}
                                <div class="d-flex align-items-start gap-2 mb-2">
                                    <div class="rounded-circle bg-success d-flex align-items-center justify-content-center text-white flex-shrink-0" style="width:22px;height:22px;font-size:10px;margin-top:2px;">
                                        <i class="fa-solid fa-check"></i>
                                    </div>
                                    <div>
                                        <div class="small fw-semibold">Submitted</div>
                                        <div class="small text-muted">{{ $mr->created_at->format('d M Y') }}</div>
                                    </div>
                                </div>

                                {{-- Step 2: GS Pickup --}}
                                <div class="d-flex align-items-start gap-2 mb-2">
                                    <div class="rounded-circle {{ in_array($mr->status, ['in_progress','sent_to_store_manager','resolved','closed']) ? 'bg-primary' : 'bg-light border' }} d-flex align-items-center justify-content-center text-white flex-shrink-0" style="width:22px;height:22px;font-size:10px;margin-top:2px;">
                                        <i class="fa-solid {{ in_array($mr->status, ['in_progress','sent_to_store_manager','resolved','closed']) ? 'fa-check' : 'fa-ellipsis' }}"></i>
                                    </div>
                                    <div>
                                        <div class="small fw-semibold {{ in_array($mr->status, ['in_progress','sent_to_store_manager','resolved','closed']) ? '' : 'text-muted' }}">
                                            General Service Pickup
                                        </div>
                                        <div class="small text-muted">
                                            {{ in_array($mr->status, ['in_progress','sent_to_store_manager','resolved','closed']) ? 'In Progress' : 'Awaiting...' }}
                                        </div>
                                    </div>
                                </div>

                                {{-- Step 3: Outcome --}}
                                <div class="d-flex align-items-start gap-2">
                                    @if($mr->status === 'resolved')
                                        <div class="rounded-circle bg-success d-flex align-items-center justify-content-center text-white flex-shrink-0" style="width:22px;height:22px;font-size:10px;margin-top:2px;">
                                            <i class="fa-solid fa-check"></i>
                                        </div>
                                        <div>
                                            <div class="small fw-semibold text-success">Repaired & Returned</div>
                                            @if($mr->resolved_at)
                                                <div class="small text-muted">{{ $mr->resolved_at->format('d M Y') }}</div>
                                            @endif
                                        </div>
                                    @elseif($mr->status === 'sent_to_store_manager')
                                        <div class="rounded-circle bg-info d-flex align-items-center justify-content-center text-white flex-shrink-0" style="width:22px;height:22px;font-size:10px;margin-top:2px;">
                                            <i class="fa-solid fa-arrow-right"></i>
                                        </div>
                                        <div>
                                            <div class="small fw-semibold text-info">Sent to Store Manager</div>
                                            <div class="small text-muted">Asset may be damaged</div>
                                        </div>
                                    @elseif($mr->status === 'closed')
                                        <div class="rounded-circle bg-secondary d-flex align-items-center justify-content-center text-white flex-shrink-0" style="width:22px;height:22px;font-size:10px;margin-top:2px;">
                                            <i class="fa-solid fa-xmark"></i>
                                        </div>
                                        <div>
                                            <div class="small fw-semibold text-secondary">Closed</div>
                                        </div>
                                    @else
                                        <div class="rounded-circle bg-light border d-flex align-items-center justify-content-center flex-shrink-0" style="width:22px;height:22px;font-size:10px;margin-top:2px;">
                                            <i class="fa-solid fa-ellipsis text-muted"></i>
                                        </div>
                                        <div>
                                            <div class="small fw-semibold text-muted">Outcome Pending</div>
                                        </div>
                                    @endif
                                </div>

                                {{-- GS Notes --}}
                                @if($mr->admin_notes)
                                    <div class="mt-3 pt-3 border-top">
                                        <p class="small fw-semibold mb-1 text-muted">
                                            <i class="fa-solid fa-comment me-1"></i> GS Notes:
                                        </p>
                                        <p class="small mb-0 text-dark">{{ Str::limit($mr->admin_notes, 120) }}</p>
                                    </div>
                                @endif
                            </div>

                            {{-- Assigned GS Staff --}}
                            @if($mr->assignedTo)
                                <div class="mt-2 d-flex align-items-center gap-2 text-muted small">
                                    <i class="fa-solid fa-user-gear"></i>
                                    Assigned to: <strong>{{ $mr->assignedTo->name }}</strong>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
            @endforeach
        </div>

        {{-- Pagination --}}
        @if($requests->hasPages())
            <div class="mt-4">
                {{ $requests->links() }}
            </div>
        @endif
    @endif
</div>
@endsection
