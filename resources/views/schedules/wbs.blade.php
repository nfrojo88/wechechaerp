@extends('layouts.app')

@section('title', 'WBS Manager – ' . $schedule->name)

@push('styles')
<style>
/* ── WBS Tab Bar ───────────────────────────────────────────────── */
.wbs-tab-bar {
    display: flex;
    gap: 0;
    border-bottom: 2px solid var(--gray-200);
    margin-bottom: 24px;
}
.wbs-tab-btn {
    background: none; border: none; padding: 12px 20px;
    font-size: 13.5px; font-weight: 600;
    color: var(--gray-500);
    border-bottom: 3px solid transparent;
    margin-bottom: -2px;
    cursor: pointer;
    transition: color var(--transition), border-color var(--transition);
    display: inline-flex; align-items: center; gap: 7px;
}
.wbs-tab-btn.active {
    color: var(--brand-600);
    border-bottom-color: var(--brand-600);
}
.wbs-tab-btn:hover:not(.active) { color: var(--gray-700); }

/* ── Task Tree ─────────────────────────────────────────────────── */
.task-list { display: flex; flex-direction: column; gap: 6px; }

.task-row {
    display: flex; align-items: center; gap: 12px;
    background: white;
    border: 1px solid var(--gray-200);
    border-radius: var(--radius-md);
    padding: 12px 16px;
    transition: box-shadow var(--transition), border-color var(--transition);
}
.task-row:hover {
    box-shadow: var(--shadow-sm);
    border-color: var(--gray-300);
}
.task-row.level-1 { margin-left: 28px; background: var(--gray-50); }
.task-row.level-2 { margin-left: 56px; background: var(--gray-50); }

/* WBS code pill */
.wbs-code {
    background: var(--brand-50);
    color: var(--brand-600);
    font-size: 11px; font-weight: 700;
    padding: 3px 9px;
    border-radius: 20px;
    min-width: 36px; text-align: center;
    flex-shrink: 0;
    border: 1px solid var(--brand-100);
}

.task-name {
    font-weight: 600; font-size: 13.5px;
    color: var(--gray-800); flex: 1;
    min-width: 0;
}

.milestone-dot {
    display: inline-flex; align-items: center; gap: 4px;
    background: var(--accent-light);
    color: #92400e;
    font-size: 10.5px; font-weight: 700;
    padding: 2px 8px; border-radius: 20px;
    margin-left: 6px;
}

/* ── Status & Priority Badges ──────────────────────────────────── */
.badge-status, .badge-priority {
    font-size: 11px; font-weight: 600;
    padding: 3px 10px; border-radius: 20px;
    white-space: nowrap;
}
.badge-status.not-started  { background: var(--gray-100); color: var(--gray-600); }
.badge-status.in-progress  { background: #dbeafe; color: #1e40af; }
.badge-status.completed    { background: #d1fae5; color: #065f46; }
.badge-status.delayed      { background: #fee2e2; color: #991b1b; }
.badge-status.blocked      { background: #fef3c7; color: #92400e; }

.badge-priority.High   { background: #fee2e2; color: #b91c1c; }
.badge-priority.Medium { background: #fef3c7; color: #92400e; }
.badge-priority.Low    { background: #d1fae5; color: #065f46; }

/* ── Task type meta ────────────────────────────────────────────── */
.task-type-tag {
    font-size: 11.5px; color: var(--gray-400); font-weight: 500;
    white-space: nowrap;
}
.task-date-tag {
    font-size: 11.5px; color: var(--gray-400);
    white-space: nowrap;
}

/* ── Predecessor badge ─────────────────────────────────────────── */
.pred-badge {
    display: inline-flex; align-items: center; gap: 4px;
    font-size: 11px; color: var(--gray-400); white-space: nowrap;
}

/* ── Empty state ───────────────────────────────────────────────── */
.empty-state {
    text-align: center; padding: 64px 20px;
    background: white;
    border: 2px dashed var(--gray-200);
    border-radius: var(--radius-lg);
}
.empty-state i { font-size: 2.8rem; color: var(--gray-300); }
.empty-state h6 { color: var(--gray-700); font-weight: 700; margin-top: 14px; }
.empty-state p  { color: var(--gray-400); font-size: 13px; margin-bottom: 20px; }

/* ── Baseline cards ────────────────────────────────────────────── */
.baseline-item {
    display: flex; align-items: center; justify-content: space-between;
    padding: 14px 18px;
    border: 1px solid var(--gray-200);
    border-radius: var(--radius-md);
    background: white;
    transition: box-shadow var(--transition);
}
.baseline-item:hover { box-shadow: var(--shadow-sm); }
.baseline-name { font-weight: 700; font-size: 13.5px; color: var(--gray-800); }
.baseline-date { font-size: 11.5px; color: var(--gray-400); margin-top: 2px; }

/* ── WBS code auto box ─────────────────────────────────────────── */
.wbs-auto-box {
    background: var(--gray-50);
    border: 1.5px solid var(--gray-200);
    border-radius: var(--radius-md);
    padding: 9px 13px;
    font-size: 13px;
    color: var(--gray-400);
    display: flex; align-items: center; gap: 7px;
}
</style>
@endpush

@section('content')

{{-- ── Page Header ─────────────────────────────────────────────── --}}
<div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-4">
    <div>
        <h1 class="page-title">
            <i class="fa-solid fa-sitemap me-2 text-primary" style="font-size:1.1rem;"></i>WBS Manager
            <span class="text-muted fw-normal" style="font-size:16px;"> – {{ $schedule->name }}</span>
        </h1>
        <div class="mt-1 d-flex align-items-center gap-2" style="font-size:12.5px; color:var(--gray-400);">
            <i class="fa-solid fa-calendar-days"></i>
            <span>{{ $schedule->start_date->format('d M Y') }} → {{ $schedule->end_date->format('d M Y') }}</span>
            <span class="text-muted">·</span>
            <i class="fa-solid fa-folder-open"></i>
            @if($schedule->project)
                <a href="{{ route('projects.show', $schedule->project) }}" class="text-decoration-none fw-semibold" style="color:var(--brand-500);">
                    {{ $schedule->project->name }}
                </a>
            @else
                <span class="text-decoration-none fw-semibold text-muted">N/A</span>
            @endif
        </div>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addTaskModal">
            <i class="fa-solid fa-plus"></i> Add Task
        </button>
        <button class="btn" style="background:var(--accent);color:white;" data-bs-toggle="modal" data-bs-target="#saveBaselineModal">
            <i class="fa-solid fa-camera"></i> Save Baseline
        </button>
        <a href="{{ route('schedules.show', $schedule) }}" class="btn btn-outline-secondary">
            <i class="fa-solid fa-chart-gantt"></i> Open Gantt
        </a>
        <a href="{{ route('schedules.index') }}" class="btn btn-outline-secondary">
            <i class="fa-solid fa-arrow-left"></i> Back
        </a>
    </div>
</div>

{{-- ── Stats Row ────────────────────────────────────────────────── --}}
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="card border-0">
            <div class="card-body py-3">
                <div class="d-flex align-items-center gap-3">
                    <div style="width:40px;height:40px;background:var(--brand-50);border-radius:var(--radius-md);display:flex;align-items:center;justify-content:center;">
                        <i class="fa-solid fa-list-check" style="color:var(--brand-500);"></i>
                    </div>
                    <div>
                        <div style="font-size:22px;font-weight:800;color:var(--gray-900);line-height:1;">{{ $allTasks->count() }}</div>
                        <div style="font-size:11.5px;color:var(--gray-400);font-weight:500;">Total Tasks</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card border-0">
            <div class="card-body py-3">
                <div class="d-flex align-items-center gap-3">
                    <div style="width:40px;height:40px;background:#d1fae5;border-radius:var(--radius-md);display:flex;align-items:center;justify-content:center;">
                        <i class="fa-solid fa-circle-check" style="color:#059669;"></i>
                    </div>
                    <div>
                        <div style="font-size:22px;font-weight:800;color:var(--gray-900);line-height:1;">{{ $allTasks->where('status','Completed')->count() }}</div>
                        <div style="font-size:11.5px;color:var(--gray-400);font-weight:500;">Completed</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card border-0">
            <div class="card-body py-3">
                <div class="d-flex align-items-center gap-3">
                    <div style="width:40px;height:40px;background:#dbeafe;border-radius:var(--radius-md);display:flex;align-items:center;justify-content:center;">
                        <i class="fa-solid fa-spinner" style="color:#1d4ed8;"></i>
                    </div>
                    <div>
                        <div style="font-size:22px;font-weight:800;color:var(--gray-900);line-height:1;">{{ $allTasks->where('status','In Progress')->count() }}</div>
                        <div style="font-size:11.5px;color:var(--gray-400);font-weight:500;">In Progress</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card border-0">
            <div class="card-body py-3">
                <div class="d-flex align-items-center gap-3">
                    <div style="width:40px;height:40px;background:#fef3c7;border-radius:var(--radius-md);display:flex;align-items:center;justify-content:center;">
                        <i class="fa-solid fa-clock-rotate-left" style="color:var(--accent-hover);"></i>
                    </div>
                    <div>
                        <div style="font-size:22px;font-weight:800;color:var(--gray-900);line-height:1;">{{ $baselines->count() }}</div>
                        <div style="font-size:11.5px;color:var(--gray-400);font-weight:500;">Baselines Saved</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- ── Tabs ─────────────────────────────────────────────────────── --}}
<div class="wbs-tab-bar">
    <button class="wbs-tab-btn active" onclick="showTab('wbs', this)">
        <i class="fa-solid fa-sitemap"></i> Work Breakdown Structure
        <span class="badge bg-primary ms-1">{{ $allTasks->count() }}</span>
    </button>
    <button class="wbs-tab-btn" onclick="showTab('baseline', this)">
        <i class="fa-solid fa-clock-rotate-left"></i> Baseline &amp; Variance
        <span class="badge bg-secondary ms-1">{{ $baselines->count() }}</span>
    </button>
</div>

{{-- ══ WBS TAB ══════════════════════════════════════════════════════════════ --}}
<div id="tab-wbs">
    <div class="card border-0">
        <div class="card-header d-flex justify-content-between align-items-center">
            <div class="fw-bold" style="color:var(--gray-700);">
                <i class="fa-solid fa-sitemap me-2 text-primary"></i>Task Hierarchy
            </div>
            <span style="font-size:12px;color:var(--gray-400);">{{ $allTasks->count() }} task(s) · Auto WBS codes</span>
        </div>
        <div class="card-body">
            @if($tasks->isEmpty())
                <div class="empty-state">
                    <i class="fa-solid fa-sitemap d-block"></i>
                    <h6>No Tasks Yet</h6>
                    <p>Start building your Work Breakdown Structure by adding your first task.</p>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addTaskModal">
                        <i class="fa-solid fa-plus"></i> Add First Task
                    </button>
                </div>
            @else
                <div class="task-list">
                    @foreach($tasks as $task)
                        @include('schedules._wbs_task_row', ['task' => $task, 'level' => 0, 'schedule' => $schedule])
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</div>

{{-- ══ BASELINE TAB ══════════════════════════════════════════════════════════ --}}
<div id="tab-baseline" style="display:none;">
    <div class="row g-4">

        {{-- Saved Versions --}}
        <div class="col-lg-4">
            <div class="card border-0 h-100">
                <div class="card-header">
                    <span class="fw-bold" style="color:var(--gray-700);">
                        <i class="fa-solid fa-layer-group me-2 text-primary"></i>Saved Versions
                    </span>
                </div>
                <div class="card-body">
                    @if($baselines->isEmpty())
                        <div class="text-center py-5" style="color:var(--gray-400);">
                            <i class="fa-solid fa-box-open fa-2x mb-3 d-block" style="opacity:.4;"></i>
                            <p class="small mb-0">No baselines saved yet.</p>
                        </div>
                    @else
                        <div class="d-flex flex-column gap-2">
                            @foreach($baselines as $bl)
                            <div class="baseline-item">
                                <div>
                                    <div class="baseline-name">{{ $bl->version_name }}</div>
                                    <div class="baseline-date">{{ $bl->created_at->format('d M Y, H:i') }}</div>
                                </div>
                                <span class="badge bg-primary">{{ count($bl->snapshot_data) }} tasks</span>
                            </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        </div>

        {{-- Variance Analysis --}}
        <div class="col-lg-8">
            <div class="card border-0">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span class="fw-bold" style="color:var(--gray-700);">
                        <i class="fa-solid fa-chart-bar me-2 text-primary"></i>Variance Analysis
                    </span>
                    <span class="badge bg-secondary">Comparison: Current vs Baseline</span>
                </div>
                <div class="card-body p-0">
                    @if($baselines->isEmpty())
                        <div class="text-center py-5" style="color:var(--gray-400);">
                            <i class="fa-solid fa-chart-simple fa-2x mb-3 d-block" style="opacity:.4;"></i>
                            <p class="small mb-1">No baseline to compare against.</p>
                            <p class="small">Save a baseline to start tracking variance.</p>
                        </div>
                    @else
                        @php $latestBaseline = $baselines->first(); @endphp
                        <div class="px-4 py-3 border-bottom" style="background:var(--gray-50);">
                            <small class="text-muted">Comparing against: </small>
                            <strong style="color:var(--brand-600);">{{ $latestBaseline->version_name }}</strong>
                            <small class="text-muted ms-2">· {{ $latestBaseline->created_at->format('d M Y') }}</small>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>Task Name</th>
                                        <th>WBS</th>
                                        <th>Baseline Start</th>
                                        <th>Baseline End</th>
                                        <th>Current Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($latestBaseline->snapshot_data as $snap)
                                    @php $live = $allTasks->firstWhere('id', $snap['id']); @endphp
                                    <tr>
                                        <td class="fw-semibold">{{ $snap['name'] }}</td>
                                        <td><span class="wbs-code">{{ $snap['wbs_code'] }}</span></td>
                                        <td>{{ $snap['start_date'] ?? '—' }}</td>
                                        <td>{{ $snap['end_date'] ?? '—' }}</td>
                                        <td>
                                            @if($live)
                                                @php $sc = strtolower(str_replace([' ','_'], '-', $live->status)); @endphp
                                                <span class="badge-status {{ $sc }}">
                                                    {{ str_replace('_',' ', $live->status) }}
                                                </span>
                                            @else
                                                <span class="badge bg-danger">Deleted</span>
                                            @endif
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>
        </div>

    </div>
</div>


{{-- ════════════════════ ADD TASK MODAL ════════════════════ --}}
<div class="modal fade" id="addTaskModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <form action="{{ route('schedules.tasks.store', $schedule) }}" method="POST">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fa-solid fa-circle-plus me-2" style="color:var(--brand-500);"></i>Add New Task
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">

                        {{-- Task Name + WBS code --}}
                        <div class="col-md-7">
                            <label class="form-label" for="task_name">Task Name <span class="text-danger">*</span></label>
                            <input type="text" name="name" id="task_name" class="form-control" placeholder="Enter task name" required>
                        </div>
                        <div class="col-md-5">
                            <label class="form-label">Task Code (WBS)</label>
                            <div class="wbs-auto-box">
                                <i class="fa-solid fa-wand-magic-sparkles" style="color:var(--gray-400);"></i>
                                Auto-generated
                            </div>
                            <div class="form-text">System calculated based on hierarchy.</div>
                        </div>

                        {{-- Parent Task --}}
                        <div class="col-12">
                            <label class="form-label" for="parent_task_id">Parent Task</label>
                            <select name="parent_task_id" id="parent_task_id" class="form-select">
                                <option value="">-- No Parent (Root) --</option>
                                @foreach($allTasks as $pt)
                                <option value="{{ $pt->id }}">{{ $pt->wbs_code }} – {{ $pt->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        {{-- Status --}}
                        <div class="col-md-12">
                            <label class="form-label" for="task_status">Status <span class="text-danger">*</span></label>
                            <select name="status" id="task_status" class="form-select">
                                <option selected>Not Started</option>
                                <option>In Progress</option>
                                <option>Completed</option>
                                <option>Delayed</option>
                                <option>Blocked</option>
                            </select>
                        </div>

                        {{-- Dates --}}
                        <div class="col-md-6">
                            <label class="form-label" for="task_start">Start Date</label>
                            <input type="date" name="start_date" id="task_start" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="task_end">End Date</label>
                            <input type="date" name="end_date" id="task_end" class="form-control">
                        </div>

                        {{-- Milestone --}}
                        <div class="col-md-12 d-flex align-items-end pb-1">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="is_milestone"
                                       value="1" id="is_milestone" role="switch">
                                <label class="form-check-label fw-semibold" for="is_milestone">
                                    <i class="fa-solid fa-diamond me-1" style="color:var(--accent);font-size:.75rem;"></i>
                                    Is this a Milestone?
                                </label>
                            </div>
                        </div>

                        {{-- Predecessor --}}
                        <div class="col-12">
                            <hr class="my-1" style="border-color:var(--gray-200);">
                            <label class="form-label">
                                <i class="fa-solid fa-link me-1" style="color:var(--gray-400);"></i>Task Dependency (Predecessor)
                            </label>
                            <select name="predecessor_id" class="form-select">
                                <option value="">-- None --</option>
                                @foreach($allTasks as $pt)
                                <option value="{{ $pt->id }}">{{ $pt->wbs_code }} – {{ $pt->name }}</option>
                                @endforeach
                            </select>
                        </div>

                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fa-solid fa-floppy-disk"></i> Save Task
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- ════════════════════ SAVE BASELINE MODAL ════════════════════ --}}
<div class="modal fade" id="saveBaselineModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form action="{{ route('schedules.baselines.store', $schedule) }}" method="POST">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fa-solid fa-camera me-2" style="color:var(--accent);"></i>Save New Baseline
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">

                    {{-- Info alert with dynamic count --}}
                    <div class="alert alert-warning mb-3" id="baseline-alert">
                        <i class="fa-solid fa-triangle-exclamation"></i>
                        A snapshot of all <strong id="baseline-task-count">{{ $allTasks->count() }} task(s)</strong>
                        will be saved. This baseline can be used for variance analysis later.
                    </div>

                    {{-- Up-to Parent Task selector --}}
                    <div class="mb-3">
                        <label class="form-label fw-semibold" for="up_to_parent_task_id">
                            <i class="fa-solid fa-layer-group me-1 text-primary"></i>
                            Snapshot Up To Parent Task
                        </label>
                        <select name="up_to_parent_task_id" id="up_to_parent_task_id" class="form-select"
                                onchange="updateBaselineCount(this)">
                            <option value="">— All tasks ({{ $allTasks->count() }} total) —</option>
                            @php
                                /* Only root-level (parent) tasks for selection */
                                $rootTasks = $allTasks->whereNull('parent_task_id')->sortBy('wbs_code');
                            @endphp
                            @foreach($rootTasks as $pt)
                            <option value="{{ $pt->id }}"
                                    data-count="{{ $allTasks->where('parent_task_id', $pt->id)->count() + 1 }}">
                                {{ $pt->wbs_code }} – {{ $pt->name }}
                            </option>
                            @endforeach
                        </select>
                        <div class="form-text text-muted">
                            Select a parent task to limit the snapshot to that task and its children only.
                            Leave blank to snapshot <strong>all tasks</strong>.
                        </div>
                    </div>

                    {{-- Baseline Version Name --}}
                    <div>
                        <label class="form-label fw-semibold" for="version_name">
                            Baseline Version Name <span class="text-danger">*</span>
                        </label>
                        <input type="text" name="version_name" id="version_name" class="form-control"
                               placeholder="e.g. Baseline v1.0 – July 2026" required
                               value="Baseline v{{ $baselines->count() + 1 }} – {{ now()->format('M Y') }}">
                    </div>

                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">
                        <i class="fa-solid fa-camera"></i> Save Baseline
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>


{{-- ════════════════════ EDIT DATES MODAL ════════════════════ --}}
<div class="modal fade" id="editTaskDatesModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form id="editTaskDatesForm" action="#" method="POST">
                @csrf
                @method('PUT')
                <div class="modal-header">
                    <h5 class="modal-title" id="editTaskDatesTitle">
                        <i class="fa-solid fa-pen-to-square me-2" style="color:var(--brand-500);"></i>Edit Task
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-12">
                            <label class="form-label" for="edit_task_name">Task Name</label>
                            <input type="text" name="name" id="edit_task_name" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="edit_task_start">Start Date</label>
                            <input type="date" name="start_date" id="edit_task_start" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="edit_task_end">End Date</label>
                            <input type="date" name="end_date" id="edit_task_end" class="form-control">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fa-solid fa-floppy-disk"></i> Update Task
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script>
function showTab(tab, el) {
    document.querySelectorAll('.wbs-tab-btn').forEach(b => b.classList.remove('active'));
    el.classList.add('active');
    document.querySelectorAll('#tab-wbs, #tab-baseline').forEach(t => t.style.display = 'none');
    document.getElementById('tab-' + tab).style.display = '';
}

function setupEditTaskDatesModal(btn) {
    const action = btn.getAttribute('data-action');
    const start = btn.getAttribute('data-start');
    const end = btn.getAttribute('data-end');
    const taskName = btn.getAttribute('data-taskname');
    const rawName = btn.getAttribute('data-rawname');
    
    document.getElementById('editTaskDatesForm').action = action;
    document.getElementById('edit_task_name').value = rawName;
    document.getElementById('edit_task_start').value = start;
    document.getElementById('edit_task_end').value = end;
    
    if (start) {
        document.getElementById('edit_task_end').min = start;
    }
    
    document.getElementById('editTaskDatesTitle').innerHTML = '<i class="fa-solid fa-pen-to-square me-2" style="color:var(--brand-500);"></i>Edit Task: ' + taskName;
}

document.addEventListener('DOMContentLoaded', function() {
    // ── Helper to bind strict Start / End Date constraints ──
    function bindDateConstraint(startInputId, endInputId, formSelector) {
        const startInput = document.getElementById(startInputId);
        const endInput = document.getElementById(endInputId);
        if (!startInput || !endInput) return;

        startInput.addEventListener('change', function() {
            if (this.value) {
                endInput.min = this.value;
                if (endInput.value && endInput.value < this.value) {
                    alert('End Date cannot be earlier than Start Date. Automatically resetting End Date to match Start Date.');
                    endInput.value = this.value;
                }
            } else {
                endInput.removeAttribute('min');
            }
        });

        endInput.addEventListener('change', function() {
            if (startInput.value && this.value && this.value < startInput.value) {
                alert('End Date cannot be earlier than Start Date (' + startInput.value + '). Please select an End Date on or after the Start Date.');
                this.value = startInput.value;
            }
        });

        if (formSelector) {
            const form = document.querySelector(formSelector);
            if (form) {
                form.addEventListener('submit', function(e) {
                    if (startInput.value && endInput.value && endInput.value < startInput.value) {
                        e.preventDefault();
                        alert('Cannot save: End Date (' + endInput.value + ') is earlier than Start Date (' + startInput.value + '). End Date must be on or after Start Date.');
                        endInput.focus();
                    }
                });
            }
        }
    }

    // Bind Add Task Modal
    bindDateConstraint('task_start', 'task_end', '#addTaskModal form');
    // Bind Edit Task Modal
    bindDateConstraint('edit_task_start', 'edit_task_end', '#editTaskDatesForm');
});

function updateBaselineCount(selectEl) {
    const totalCount = parseInt('{{ $allTasks->count() }}', 10) || 0;
    const countEl = document.getElementById('baseline-task-count');
    if (!countEl) return;

    if (!selectEl.value) {
        countEl.textContent = totalCount + ' task(s)';
    } else {
        const selectedOpt = selectEl.options[selectEl.selectedIndex];
        const count = selectedOpt ? selectedOpt.getAttribute('data-count') : totalCount;
        countEl.textContent = count + ' task(s)';
    }
}
</script>
@endpush
