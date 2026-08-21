@extends('layouts.app')
@section('title', 'Maintenance Request — ' . $maintenanceRequest->request_no)
@section('content')
<div class="container-fluid">
    <div class="d-flex align-items-center gap-3 mb-4">
        <a href="{{ route('profile.edit') }}" class="btn btn-sm btn-outline-secondary rounded-pill">
            <i class="fa-solid fa-arrow-left me-1"></i>Back to Profile
        </a>
        <div>
            <h1 class="h4 mb-0 fw-bold">🔧 {{ $maintenanceRequest->request_no }}</h1>
            <div class="text-muted small">Submitted {{ $maintenanceRequest->created_at->format('d M Y, H:i') }}</div>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <div class="row g-4">
        {{-- Main Details --}}
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-header bg-white border-0 py-3 rounded-top-4 d-flex justify-content-between align-items-center">
                    <h5 class="mb-0 fw-bold">Request Details</h5>
                    @php $sb = $maintenanceRequest->status_badge; $ub = $maintenanceRequest->urgency_badge; @endphp
                    <div class="d-flex gap-2">
                        <span class="badge {{ $ub['class'] }} rounded-pill px-3">{{ $ub['label'] }}</span>
                        <span class="badge {{ $sb['class'] }} rounded-pill px-3">
                            <i class="fa-solid {{ $sb['icon'] }} me-1"></i>{{ $sb['label'] }}
                        </span>
                    </div>
                </div>
                <div class="card-body p-4">
                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <div class="p-3 bg-light rounded-3">
                                <div class="small text-muted mb-1">Asset Name</div>
                                <strong>{{ $maintenanceRequest->asset_name }}</strong>
                                @if($maintenanceRequest->asset_code)
                                    <span class="badge bg-dark font-monospace ms-2">{{ $maintenanceRequest->asset_code }}</span>
                                @endif
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="p-3 bg-light rounded-3">
                                <div class="small text-muted mb-1">Issue Type</div>
                                <strong>{{ $maintenanceRequest->issue_type_label }}</strong>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="p-3 bg-light rounded-3">
                                <div class="small text-muted mb-1">Urgency</div>
                                <span class="badge {{ $ub['class'] }}">{{ $ub['label'] }}</span>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="p-3 bg-light rounded-3">
                                <div class="small text-muted mb-1">Current Status</div>
                                <span class="badge {{ $sb['class'] }}">
                                    <i class="fa-solid {{ $sb['icon'] }} me-1"></i>{{ $sb['label'] }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <div class="small fw-semibold text-muted mb-2">Issue Description</div>
                        <div class="p-3 bg-light rounded-3" style="white-space: pre-wrap; font-size: 0.9rem;">{{ $maintenanceRequest->description }}</div>
                    </div>

                    @if($maintenanceRequest->admin_notes)
                    <div class="alert alert-info border-0 rounded-3 mb-0">
                        <div class="fw-semibold small mb-1"><i class="fa-solid fa-comment-dots me-1"></i>Admin Notes / Response</div>
                        <div style="white-space: pre-wrap; font-size: 0.9rem;">{{ $maintenanceRequest->admin_notes }}</div>
                    </div>
                    @endif
                </div>
            </div>
        </div>

        {{-- Sidebar Info --}}
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm rounded-4 mb-3">
                <div class="card-body p-4">
                    <h6 class="fw-bold mb-3"><i class="fa-solid fa-circle-info me-2 text-primary"></i>Request Info</h6>
                    <div class="d-flex flex-column gap-2" style="font-size: 0.88rem;">
                        <div class="d-flex justify-content-between">
                            <span class="text-muted">Request No.</span>
                            <span class="fw-semibold font-monospace">{{ $maintenanceRequest->request_no }}</span>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span class="text-muted">Submitted</span>
                            <span>{{ $maintenanceRequest->created_at->format('d M Y') }}</span>
                        </div>
                        @if($maintenanceRequest->assignedTo)
                        <div class="d-flex justify-content-between">
                            <span class="text-muted">Assigned To</span>
                            <span class="fw-semibold">{{ $maintenanceRequest->assignedTo->name }}</span>
                        </div>
                        @endif
                        @if($maintenanceRequest->resolved_at)
                        <div class="d-flex justify-content-between">
                            <span class="text-muted">Resolved</span>
                            <span class="text-success fw-semibold">{{ $maintenanceRequest->resolved_at->format('d M Y') }}</span>
                        </div>
                        @endif
                    </div>
                </div>
            </div>

            {{-- Status Timeline --}}
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-body p-4">
                    <h6 class="fw-bold mb-3"><i class="fa-solid fa-timeline me-2 text-warning"></i>Status Timeline</h6>
                    <div class="d-flex flex-column gap-3" style="font-size: 0.85rem;">
                        @foreach(['pending' => ['Submitted', 'fa-clock', 'warning'], 'in_progress' => ['In Progress', 'fa-wrench', 'primary'], 'resolved' => ['Resolved', 'fa-circle-check', 'success'], 'closed' => ['Closed', 'fa-xmark-circle', 'secondary']] as $step => [$label, $icon, $color])
                        @php
                            $statuses = ['pending', 'in_progress', 'resolved', 'closed'];
                            $currentIdx = array_search($maintenanceRequest->status, $statuses);
                            $stepIdx = array_search($step, $statuses);
                            $isDone = $stepIdx <= $currentIdx;
                        @endphp
                        <div class="d-flex align-items-center gap-3">
                            <div class="rounded-circle d-flex align-items-center justify-content-center flex-shrink-0"
                                 style="width:30px;height:30px;background:{{ $isDone ? "var(--bs-$color)" : '#e5e7eb' }};">
                                <i class="fa-solid {{ $icon }} text-{{ $isDone ? 'white' : 'secondary' }}" style="font-size:0.75rem;"></i>
                            </div>
                            <span class="{{ $isDone ? 'fw-semibold text-dark' : 'text-muted' }}">{{ $label }}</span>
                            @if($step === $maintenanceRequest->status)
                                <span class="badge bg-{{ $color }} ms-auto" style="font-size:0.65rem;">Now</span>
                            @endif
                        </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
