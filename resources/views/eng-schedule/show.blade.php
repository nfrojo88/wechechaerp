@extends('layouts.app')

@section('title', 'Work Order: ' . $engSchedule->title)

@section('content')
<div class="container-fluid px-4 py-3">

    {{-- Header --}}
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div class="d-flex align-items-center gap-2">
            <a href="{{ route('eng-schedule.index') }}" class="btn btn-sm btn-outline-secondary">
                <i class="fa-solid fa-arrow-left"></i>
            </a>
            <div>
                <h1 class="h4 mb-0 fw-bold" style="color:var(--brand-800)">
                    <i class="fa-solid fa-briefcase me-2 text-primary"></i>{{ $engSchedule->title }}
                </h1>
                <p class="text-muted small mb-0">
                    {{ $engSchedule->project->name ?? '' }}
                    @if($engSchedule->location) · <i class="fa-solid fa-location-dot"></i> {{ $engSchedule->location }} @endif
                </p>
            </div>
        </div>
        <div class="d-flex gap-2">
            @can('update', $engSchedule)
            <a href="{{ route('eng-schedule.edit', $engSchedule) }}" class="btn btn-sm btn-outline-primary">
                <i class="fa-solid fa-pen me-1"></i>Edit
            </a>
            @endcan
            @can('delete', $engSchedule)
            <form method="POST" action="{{ route('eng-schedule.destroy', $engSchedule) }}" onsubmit="return confirm('Cancel this work order?')">
                @csrf @method('DELETE')
                <button type="submit" class="btn btn-sm btn-outline-danger"><i class="fa-solid fa-ban me-1"></i>Cancel</button>
            </form>
            @endcan
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show"><i class="fa-solid fa-check-circle me-2"></i>{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    @endif

    <div class="row g-4">

        {{-- LEFT: Details --}}
        <div class="col-lg-8">

            {{-- Info card --}}
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-sm-6">
                            <p class="text-muted small mb-1">Start</p>
                            <p class="fw-semibold mb-0"><i class="fa-solid fa-play-circle text-success me-1"></i>{{ $engSchedule->start_datetime->format('D, M d Y · H:i') }}</p>
                        </div>
                        <div class="col-sm-6">
                            <p class="text-muted small mb-1">End</p>
                            <p class="fw-semibold mb-0"><i class="fa-solid fa-stop-circle text-danger me-1"></i>{{ $engSchedule->end_datetime->format('D, M d Y · H:i') }}</p>
                        </div>
                        <div class="col-sm-3">
                            <p class="text-muted small mb-1">Priority</p>
                            @php $pColors = ['urgent'=>'danger','high'=>'warning','medium'=>'primary','low'=>'secondary'] @endphp
                            <span class="badge bg-{{ $pColors[$engSchedule->priority] ?? 'secondary' }} fs-6">{{ ucfirst($engSchedule->priority) }}</span>
                        </div>
                        <div class="col-sm-3">
                            <p class="text-muted small mb-1">Status</p>
                            @php $sColors = ['completed'=>'success','in_progress'=>'primary','accepted'=>'info','declined'=>'danger','on_hold'=>'warning','cancelled'=>'secondary','assigned'=>'primary','draft'=>'light'] @endphp
                            <span class="badge bg-{{ $sColors[$engSchedule->status] ?? 'secondary' }} fs-6">{{ ucwords(str_replace('_',' ',$engSchedule->status)) }}</span>
                        </div>
                        <div class="col-sm-3">
                            <p class="text-muted small mb-1">Duration</p>
                            <p class="fw-semibold mb-0">{{ $engSchedule->durationHours() }} hrs</p>
                        </div>
                        <div class="col-sm-3">
                            <p class="text-muted small mb-1">Category</p>
                            <p class="fw-semibold mb-0">{{ $engSchedule->category ?? '—' }}</p>
                        </div>
                        @if($engSchedule->description)
                        <div class="col-12">
                            <p class="text-muted small mb-1">Description</p>
                            <p class="mb-0">{{ $engSchedule->description }}</p>
                        </div>
                        @endif
                        @if($engSchedule->notes)
                        <div class="col-12">
                            <p class="text-muted small mb-1">Notes</p>
                            <p class="mb-0 fst-italic text-muted">{{ $engSchedule->notes }}</p>
                        </div>
                        @endif
                        @if($engSchedule->recurrence_type !== 'none')
                        <div class="col-12">
                            <p class="text-muted small mb-1">Recurrence</p>
                            <p class="mb-0"><i class="fa-solid fa-rotate me-1 text-info"></i>
                                Every {{ $engSchedule->recurrence_interval }} {{ $engSchedule->recurrence_type }}(s)
                                @if($engSchedule->recurrence_end_date) until {{ $engSchedule->recurrence_end_date->format('M d, Y') }} @endif
                            </p>
                        </div>
                        @endif
                    </div>
                </div>
            </div>

            {{-- Update Status (Engineer Only) --}}
            @can('updateStatus', $engSchedule)
            @php $myAssignee = $engSchedule->assignees->where('user_id', auth()->id())->first(); @endphp
            @if(!in_array($engSchedule->status, ['cancelled','completed']))
            <div class="card shadow-sm border-0 mb-4 border-start border-4 border-primary">
                <div class="card-header bg-white fw-semibold py-2">
                    <i class="fa-solid fa-pen-to-square me-2 text-primary"></i>Update My Status
                </div>
                <div class="card-body">
                    <form method="POST" action="{{ route('eng-schedule.update-status', $engSchedule) }}">
                        @csrf @method('PATCH')
                        <div class="row g-2 align-items-end">
                            <div class="col-md-4">
                                <label class="form-label small fw-semibold">New Status</label>
                                <select name="status" class="form-select form-select-sm" id="status-select" onchange="toggleDecline(this.value)">
                                    @php $allowedStatuses = auth()->user()->hasAnyRole(['planning_manager','planning','admin','global_admin'])
                                        ? ['draft','assigned','accepted','declined','in_progress','on_hold','completed','cancelled']
                                        : ['accepted','declined','in_progress','on_hold','completed'] @endphp
                                    @foreach($allowedStatuses as $s)
                                        <option value="{{ $s }}" {{ $engSchedule->status == $s ? 'selected' : '' }}>{{ ucwords(str_replace('_',' ',$s)) }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-3" id="hours-col">
                                <label class="form-label small fw-semibold">Actual Hours</label>
                                <input type="number" name="actual_hours" class="form-control form-control-sm" step="0.5" min="0" value="{{ $myAssignee->actual_hours }}">
                            </div>
                            <div class="col-12" id="decline-col" style="display:none">
                                <label class="form-label small fw-semibold">Reason for Decline / Hold</label>
                                <input type="text" name="decline_reason" class="form-control form-control-sm" placeholder="Please provide a reason...">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small fw-semibold">Notes</label>
                                <input type="text" name="notes" class="form-control form-control-sm" placeholder="Optional note...">
                            </div>
                            <div class="col-md-2">
                                <button type="submit" class="btn btn-primary btn-sm w-100">Update</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
            @endif
            @endcan

            {{-- Comments --}}
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white fw-semibold py-2">
                    <i class="fa-solid fa-comments me-2 text-info"></i>Comments & Notes
                    <span class="badge bg-secondary ms-1">{{ $engSchedule->comments->count() }}</span>
                </div>
                <div class="card-body" style="max-height:400px; overflow-y:auto;">
                    @forelse($engSchedule->comments as $comment)
                    <div class="d-flex gap-3 mb-3">
                        <div class="flex-shrink-0">
                            <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center" style="width:36px;height:36px;font-size:0.8rem;font-weight:700;">
                                {{ strtoupper(substr($comment->user->name ?? '?', 0, 2)) }}
                            </div>
                        </div>
                        <div class="flex-grow-1">
                            <div class="bg-light rounded p-2">
                                <p class="mb-1 fw-semibold small">{{ $comment->user->name ?? 'Unknown' }}
                                    <span class="text-muted fw-normal ms-2">{{ $comment->created_at->diffForHumans() }}</span>
                                </p>
                                <p class="mb-0">{{ $comment->body }}</p>
                            </div>
                        </div>
                    </div>
                    @empty
                    <p class="text-muted text-center small py-3">No comments yet.</p>
                    @endforelse
                </div>
                @can('comment', $engSchedule)
                <div class="card-footer bg-white">
                    <form method="POST" action="{{ route('eng-schedule.add-comment', $engSchedule) }}">
                        @csrf
                        <div class="d-flex gap-2">
                            <input type="text" name="body" class="form-control form-control-sm" placeholder="Add a comment or note..." required>
                            <button type="submit" class="btn btn-sm btn-primary"><i class="fa-solid fa-send"></i></button>
                        </div>
                    </form>
                </div>
                @endcan
            </div>
        </div>

        {{-- RIGHT: Assignees + History --}}
        <div class="col-lg-4">

            {{-- Assigned Engineers --}}
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-white fw-semibold py-2">
                    <i class="fa-solid fa-users me-2 text-warning"></i>Assigned Engineers
                </div>
                <div class="card-body p-0">
                    @foreach($engSchedule->engineers as $eng)
                    @php
                        $pivot = $eng->pivot;
                        $sBadge = ['pending'=>'secondary','accepted'=>'success','declined'=>'danger','in_progress'=>'primary','on_hold'=>'warning','completed'=>'dark'];
                    @endphp
                    <div class="d-flex align-items-center gap-2 px-3 py-2 border-bottom">
                        <div class="rounded-circle bg-secondary text-white d-flex align-items-center justify-content-center flex-shrink-0" style="width:36px;height:36px;font-size:0.8rem;font-weight:700;">
                            {{ strtoupper(substr($eng->name, 0, 2)) }}
                        </div>
                        <div class="flex-grow-1">
                            <p class="mb-0 fw-semibold small">{{ $eng->name }}</p>
                            <p class="mb-0 text-muted" style="font-size:0.73rem;">{{ ucwords(str_replace('_',' ',$eng->getRoleNames()->first() ?? '')) }}</p>
                        </div>
                        <span class="badge bg-{{ $sBadge[$pivot->status] ?? 'secondary' }}">{{ ucfirst($pivot->status) }}</span>
                    </div>
                    @if($pivot->decline_reason)
                        <div class="px-3 py-1 bg-danger bg-opacity-10"><small class="text-danger"><i class="fa-solid fa-exclamation-circle me-1"></i>{{ $pivot->decline_reason }}</small></div>
                    @endif
                    @endforeach
                </div>
            </div>

            {{-- Assigned By --}}
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-body py-2">
                    <p class="text-muted small mb-1">Assigned by</p>
                    <p class="fw-semibold mb-0">{{ $engSchedule->assignedBy->name ?? '—' }}</p>
                    <p class="text-muted small mb-0">{{ $engSchedule->created_at->format('M d, Y H:i') }}</p>
                </div>
            </div>

            {{-- Status History --}}
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white fw-semibold py-2">
                    <i class="fa-solid fa-clock-rotate-left me-2 text-secondary"></i>Status History
                </div>
                <div class="card-body p-0" style="max-height:280px; overflow-y:auto;">
                    @forelse($engSchedule->statusHistory as $hist)
                    <div class="px-3 py-2 border-bottom">
                        <p class="mb-0 small fw-semibold">
                            {{ $hist->changedBy->name ?? 'System' }}
                            <span class="text-muted fw-normal">{{ $hist->created_at->diffForHumans() }}</span>
                        </p>
                        <p class="mb-0 small text-muted">
                            @if($hist->from_status)<span class="text-danger">{{ ucwords(str_replace('_',' ',$hist->from_status)) }}</span> → @endif
                            <span class="text-success">{{ ucwords(str_replace('_',' ',$hist->to_status)) }}</span>
                        </p>
                        @if($hist->notes)<p class="mb-0 small fst-italic text-muted">{{ $hist->notes }}</p>@endif
                    </div>
                    @empty
                    <p class="text-muted text-center small py-3">No history yet.</p>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function toggleDecline(val) {
    document.getElementById('decline-col').style.display = (val === 'declined' || val === 'on_hold') ? '' : 'none';
}
</script>
@endsection
