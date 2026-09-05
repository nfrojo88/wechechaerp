@extends('layouts.app')

@section('title', 'Project Schedules')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0">Project Schedules & Timelines</h1>
    @can('create', App\Models\Schedule::class)
    <a href="{{ route('schedules.create') }}" class="btn btn-primary">
        <i class="fa-solid fa-calendar-plus me-1"></i> Create Schedule
    </a>
    @endcan
</div>

<div class="card border-0 shadow-sm">
    <div class="card-header bg-transparent py-3 d-flex justify-content-between align-items-center">
        <form method="GET" action="{{ route('schedules.index') }}" class="d-flex gap-2">
            <select name="project_id" class="form-select form-select-sm" style="min-width: 250px;">
                <option value="">All Projects</option>
                @foreach($projects as $project)
                <option value="{{ $project->id }}" @selected(request('project_id') == $project->id)>
                    {{ $project->name }} ({{ $project->code }})
                </option>
                @endforeach
            </select>
            <button type="submit" class="btn btn-sm btn-outline-secondary">Filter</button>
            @if(request('project_id'))
            <a href="{{ route('schedules.index') }}" class="btn btn-sm btn-outline-danger">Clear</a>
            @endif
        </form>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Schedule Name</th>
                        <th>Project</th>
                        <th>Duration</th>
                        <th style="width: 20%">Progress</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($schedules as $schedule)
                    <tr>
                        <td class="fw-semibold">{{ $schedule->name }}</td>
                        <td>
                            @if($schedule->project)
                                <a href="{{ route('projects.show', $schedule->project) }}" class="text-decoration-none">
                                    {{ $schedule->project->name }}
                                </a>
                            @else
                                <span class="text-muted">N/A</span>
                            @endif
                        </td>
                        <td>
                            <div class="small">
                                <strong>Start:</strong> {{ $schedule->start_date->format('d M Y') }}<br>
                                <strong>End:</strong> {{ $schedule->end_date->format('d M Y') }}
                            </div>
                        </td>
                        <td>
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <span class="small fw-semibold">{{ $schedule->progress }}%</span>
                            </div>
                            <div class="progress" style="height: 6px;">
                                <div class="progress-bar {{ $schedule->progress == 100 ? 'bg-success' : 'bg-primary' }}" 
                                     role="progressbar" 
                                     @style(["width: {$schedule->progress}%"]) 
                                     aria-valuenow="{{ $schedule->progress }}" 
                                     aria-valuemin="0" 
                                     aria-valuemax="100"></div>
                            </div>
                        </td>
                        <td>
                            @php
                                $badge = match($schedule->status) {
                                    'draft' => 'secondary',
                                    'active' => 'primary',
                                    'delayed' => 'danger',
                                    'completed' => 'success',
                                    default => 'secondary'
                                };
                            @endphp
                            <span class="badge bg-{{ $badge }}">{{ strtoupper($schedule->status) }}</span>
                        </td>
                        <td class="text-end">
                            @can('view', $schedule)
                            <a href="{{ route('schedules.show', $schedule) }}" class="btn btn-sm btn-outline-info" title="Open Gantt Chart">
                                <i class="fa-solid fa-chart-gantt"></i> Open Gantt
                            </a>
                            @endcan

                            @if(!$schedule->sent_to_coordinator)
                                @can('update', $schedule)
                                <a href="{{ route('schedules.wbs', $schedule) }}" class="btn btn-sm btn-outline-success" title="Manage WBS">
                                    <i class="fa-solid fa-sitemap"></i> Manage WBS
                                </a>
                                <a href="{{ route('schedules.edit', $schedule) }}" class="btn btn-sm btn-outline-primary" title="Edit Schedule">
                                    <i class="fa-solid fa-pen"></i>
                                </a>
                                @endcan
                                @can('update', $schedule)
                                <form method="POST"
                                      action="{{ route('schedules.send-to-coordinator', $schedule) }}"
                                      class="d-inline"
                                      onsubmit="return confirm('Send this schedule to the coordinator? It will become read-only.');">
                                    @csrf
                                    <button type="submit"
                                            class="btn btn-sm btn-outline-warning"
                                            title="Send to Coordinator">
                                        <i class="fa-solid fa-paper-plane"></i> Send to Coordinator
                                    </button>
                                </form>
                                @endcan
                                @can('delete', $schedule)
                                <form method="POST"
                                      action="{{ route('schedules.destroy', $schedule) }}"
                                      class="d-inline"
                                      onsubmit="return confirm('Delete this schedule? Any linked ERP Plan will also be unlinked.');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete">
                                        <i class="fa-solid fa-trash"></i>
                                    </button>
                                </form>
                                @endcan
                            @else
                                {{-- Sent to coordinator — read-only badge --}}
                                <span class="badge bg-warning text-dark" title="Sent on {{ $schedule->sent_at?->format('d M Y H:i') }}">
                                    <i class="fa-solid fa-paper-plane me-1"></i>Sent to Coordinator
                                </span>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="text-center py-5 text-muted">
                            <i class="fa-regular fa-calendar-xmark fa-3x mb-3 opacity-50"></i>
                            <p class="mb-0">No project schedules found.</p>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if($schedules->hasPages())
    <div class="card-footer bg-transparent">
        {{ $schedules->links() }}
    </div>
    @endif
</div>
@endsection
