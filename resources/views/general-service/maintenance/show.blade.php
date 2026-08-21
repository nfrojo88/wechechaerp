@extends('layouts.app')
@section('title', 'Maintenance — ' . $maintenanceRequest->request_no)
@section('content')
<div class="container-fluid">

    {{-- Header --}}
    <div class="d-flex align-items-center gap-3 mb-4">
        <a href="{{ route('general-service.maintenance.index') }}" class="btn btn-sm btn-outline-secondary rounded-pill">
            <i class="fa-solid fa-arrow-left me-1"></i>Back to List
        </a>
        <div>
            <h1 class="h4 mb-0 fw-bold">
                <i class="fa-solid fa-screwdriver-wrench me-2 text-warning"></i>{{ $maintenanceRequest->request_no }}
            </h1>
            <div class="text-muted small">Submitted {{ $maintenanceRequest->created_at->format('d M Y, H:i') }}</div>
        </div>
        @php $sb = $maintenanceRequest->status_badge; $ub = $maintenanceRequest->urgency_badge; @endphp
        <div class="ms-auto d-flex gap-2">
            <span class="badge {{ $ub['class'] }} rounded-pill px-3 fs-6">{{ $ub['label'] }}</span>
            <span class="badge {{ $sb['class'] }} rounded-pill px-3 fs-6">
                <i class="fa-solid {{ $sb['icon'] }} me-1"></i>{{ $sb['label'] }}
            </span>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm rounded-3">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <div class="row g-4">

        {{-- Left: Request Details --}}
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm rounded-4 mb-4">
                <div class="card-header bg-white border-0 py-3 rounded-top-4">
                    <h5 class="mb-0 fw-bold"><i class="fa-solid fa-file-lines me-2 text-muted"></i>Request Details</h5>
                </div>
                <div class="card-body p-4">
                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <div class="p-3 rounded-3 border">
                                <div class="small text-muted mb-1">Employee</div>
                                <strong class="d-block">{{ $maintenanceRequest->employee->full_name ?? 'N/A' }}</strong>
                                <span class="badge bg-dark font-monospace mt-1">{{ $maintenanceRequest->employee->employee_code ?? '' }}</span>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="p-3 rounded-3 border">
                                <div class="small text-muted mb-1">Asset</div>
                                <strong class="d-block">{{ $maintenanceRequest->asset_name }}</strong>
                                @if($maintenanceRequest->asset_code)
                                    <span class="badge bg-dark font-monospace mt-1">{{ $maintenanceRequest->asset_code }}</span>
                                @endif
                                @if($maintenanceRequest->fixedAssetUnit)
                                    <div class="small text-muted mt-1">{{ $maintenanceRequest->fixedAssetUnit->parentAsset->name ?? '' }}</div>
                                @endif
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="p-3 rounded-3 border">
                                <div class="small text-muted mb-1">Issue Type</div>
                                <strong>{{ $maintenanceRequest->issue_type_label }}</strong>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="p-3 rounded-3 border">
                                <div class="small text-muted mb-1">Urgency</div>
                                <span class="badge {{ $ub['class'] }} rounded-pill px-3">{{ $ub['label'] }}</span>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <div class="fw-semibold small text-muted mb-2">Description from Employee</div>
                        <div class="p-3 bg-light rounded-3" style="white-space: pre-wrap; font-size: 0.9rem; border-left: 3px solid #f59e0b;">
                            {{ $maintenanceRequest->description }}
                        </div>
                    </div>
                </div>
            </div>

            {{-- Update Status Card --}}
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-header bg-white border-0 py-3 rounded-top-4">
                    <h5 class="mb-0 fw-bold"><i class="fa-solid fa-pen-to-square me-2 text-primary"></i>Update Request</h5>
                </div>
                <div class="card-body p-4">
                    <form method="POST" action="{{ route('general-service.maintenance.update-status', $maintenanceRequest) }}">
                        @csrf
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Status <span class="text-danger">*</span></label>
                                <select name="status" class="form-select" required>
                                    <option value="pending" {{ $maintenanceRequest->status === 'pending' ? 'selected' : '' }}>⏳ Pending</option>
                                    <option value="in_progress" {{ $maintenanceRequest->status === 'in_progress' ? 'selected' : '' }}>🔧 In Progress</option>
                                    <option value="resolved" {{ $maintenanceRequest->status === 'resolved' ? 'selected' : '' }}>✅ Resolved</option>
                                    <option value="closed" {{ $maintenanceRequest->status === 'closed' ? 'selected' : '' }}>🔒 Closed</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Assign To (Optional)</label>
                                <select name="assigned_to_user_id" class="form-select">
                                    <option value="">— Not assigned —</option>
                                    @foreach($staff as $s)
                                        <option value="{{ $s->id }}" {{ $maintenanceRequest->assigned_to_user_id == $s->id ? 'selected' : '' }}>
                                            {{ $s->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-12">
                                <label class="form-label fw-semibold">Admin Notes / Response to Employee</label>
                                <textarea name="admin_notes" class="form-control" rows="4"
                                    placeholder="Add notes about what was done, what the employee needs to know, ETA for repair, etc.">{{ old('admin_notes', $maintenanceRequest->admin_notes) }}</textarea>
                                <div class="form-text">This note will be visible to the employee on their maintenance request page.</div>
                            </div>
                            <div class="col-12 d-flex justify-content-end">
                                <button type="submit" class="btn btn-primary fw-bold px-5">
                                    <i class="fa-solid fa-floppy-disk me-2"></i>Save Updates
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        {{-- Right: Sidebar --}}
        <div class="col-lg-4">
            {{-- Quick Info --}}
            <div class="card border-0 shadow-sm rounded-4 mb-3">
                <div class="card-body p-4">
                    <h6 class="fw-bold mb-3"><i class="fa-solid fa-circle-info me-2 text-primary"></i>Request Info</h6>
                    <div class="d-flex flex-column gap-2" style="font-size: 0.88rem;">
                        <div class="d-flex justify-content-between">
                            <span class="text-muted">Request No.</span>
                            <span class="fw-semibold font-monospace text-primary">{{ $maintenanceRequest->request_no }}</span>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span class="text-muted">Reported By</span>
                            <span>{{ $maintenanceRequest->reportedBy->name ?? 'N/A' }}</span>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span class="text-muted">Submitted</span>
                            <span>{{ $maintenanceRequest->created_at->format('d M Y, H:i') }}</span>
                        </div>
                        @if($maintenanceRequest->assignedTo)
                        <div class="d-flex justify-content-between">
                            <span class="text-muted">Assigned To</span>
                            <span class="fw-semibold text-primary">{{ $maintenanceRequest->assignedTo->name }}</span>
                        </div>
                        @endif
                        @if($maintenanceRequest->resolved_at)
                        <div class="d-flex justify-content-between">
                            <span class="text-muted">Resolved At</span>
                            <span class="text-success fw-semibold">{{ $maintenanceRequest->resolved_at->format('d M Y') }}</span>
                        </div>
                        @endif
                        <hr class="my-1">
                        <div class="d-flex justify-content-between">
                            <span class="text-muted">Days Open</span>
                            <span class="fw-semibold {{ $maintenanceRequest->created_at->diffInDays() > 3 ? 'text-danger' : '' }}">
                                {{ $maintenanceRequest->created_at->diffInDays() }} days
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Status Timeline --}}
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-body p-4">
                    <h6 class="fw-bold mb-3"><i class="fa-solid fa-timeline me-2 text-warning"></i>Status Timeline</h6>
                    <div class="d-flex flex-column gap-3" style="font-size: 0.85rem;">
                        @foreach(['pending' => ['Submitted / Pending', 'fa-clock', 'warning'], 'in_progress' => ['In Progress', 'fa-wrench', 'primary'], 'resolved' => ['Resolved', 'fa-circle-check', 'success'], 'closed' => ['Closed', 'fa-xmark-circle', 'secondary']] as $step => [$label, $icon, $color])
                        @php
                            $statuses = ['pending', 'in_progress', 'resolved', 'closed'];
                            $currentIdx = array_search($maintenanceRequest->status, $statuses);
                            $stepIdx = array_search($step, $statuses);
                            $isDone = $stepIdx <= $currentIdx;
                        @endphp
                        <div class="d-flex align-items-center gap-3">
                            <div class="rounded-circle d-flex align-items-center justify-content-center flex-shrink-0"
                                 style="width:34px;height:34px;background:{{ $isDone ? "var(--bs-$color)" : '#e5e7eb' }};">
                                <i class="fa-solid {{ $icon }} {{ $isDone ? 'text-white' : 'text-secondary' }}" style="font-size:0.75rem;"></i>
                            </div>
                            <span class="{{ $isDone ? 'fw-semibold text-dark' : 'text-muted' }}">{{ $label }}</span>
                            @if($step === $maintenanceRequest->status)
                                <span class="badge bg-{{ $color }} ms-auto" style="font-size:0.65rem;">Current</span>
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
