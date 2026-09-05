@extends('layouts.app')

@section('title', 'Gantt – ' . $schedule->name)

@push('styles')
<style>
/* ── Gantt specific styles ─────────────────────────────────────── */
.gantt-view-toggle .btn { font-size: 12.5px; }
.gantt-view-toggle .btn.active {
    background: var(--brand-600); color: white;
    border-color: var(--brand-600);
}

/* ── Split Gantt Layout ────────────────────────────────────────── */
.gantt-wrap {
    display: flex;
    overflow: hidden;
    border: 1px solid var(--gray-200);
    border-radius: var(--radius-lg);
    background: white;
    min-height: 420px;
}

/* Left – task list panel */
.gantt-left {
    width: 380px;
    min-width: 280px;
    flex-shrink: 0;
    border-right: 2px solid var(--gray-200);
    overflow-y: auto;
    overflow-x: hidden;
}

.gantt-left-header {
    display: flex;
    background: var(--gray-50);
    border-bottom: 1px solid var(--gray-200);
    padding: 0;
    position: sticky; top: 0; z-index: 10;
}
.gantt-lh-cell {
    font-size: 10.5px; font-weight: 700; letter-spacing: .5px;
    text-transform: uppercase; color: var(--gray-500);
    padding: 10px 10px; border-right: 1px solid var(--gray-200);
    display: flex; align-items: center;
}
.gantt-lh-cell:last-child { border-right: none; }

/* task rows left */
.gantt-task-row {
    display: flex;
    border-bottom: 1px solid var(--gray-100);
    align-items: center;
    min-height: 42px;
    transition: background var(--transition);
}
.gantt-task-row:hover { background: var(--brand-50); }
.gantt-task-row.is-child  { background: #fafbfc; }
.gantt-task-row.is-child:hover  { background: var(--brand-50); }

/* specific column widths for flex layout */
.col-wbs { width: 44px; flex-shrink: 0; }
.col-name { flex: 1; min-width: 0; }
.col-start { width: 70px; flex-shrink: 0; }
.col-end { width: 72px; flex-shrink: 0; }

.gantt-tc {
    padding: 7px 10px;
    font-size: 12.5px;
    color: var(--gray-700);
    border-right: 1px solid var(--gray-100);
    height: 100%;
    display: flex; align-items: center;
}
.gantt-tc:last-child { border-right: none; }

.gantt-tc.wbs-col {
    justify-content: center;
    background: var(--gray-50);
}
.wbs-pill {
    background: var(--brand-50); color: var(--brand-600);
    font-size: 10px; font-weight: 700;
    padding: 2px 7px; border-radius: 20px;
    border: 1px solid var(--brand-100);
    white-space: nowrap;
}
.gantt-task-name {
    font-weight: 600; font-size: 12.5px;
    color: var(--gray-800); white-space: nowrap;
    overflow: hidden; text-overflow: ellipsis;
}
.gantt-task-name.child-indent { padding-left: 16px; }
.gantt-task-name.grandchild-indent { padding-left: 32px; }

.gantt-milestone-dot {
    width: 6px; height: 6px;
    background: var(--accent); border-radius: 50%;
    display: inline-block; margin-right: 5px; flex-shrink: 0;
}
.gantt-date-cell { font-size: 11px; color: var(--gray-400); }

/* Right – Gantt bars panel */
.gantt-right {
    flex: 1;
    overflow-x: auto;
    overflow-y: auto;
    position: relative;
}

.gantt-header-row {
    display: flex;
    position: sticky; top: 0; z-index: 10;
    background: var(--gray-50);
    border-bottom: 1px solid var(--gray-200);
    min-width: max-content;
}
.gantt-month-label {
    font-size: 10.5px; font-weight: 700; letter-spacing: .4px;
    text-transform: uppercase; color: var(--gray-500);
    text-align: center;
    border-right: 1px solid var(--gray-200);
    padding: 5px 0 3px;
}
.gantt-day-row {
    display: flex;
    min-width: max-content;
}
.gantt-day-cell {
    width: 28px; flex-shrink: 0;
    border-right: 1px solid var(--gray-100);
    font-size: 9.5px; color: var(--gray-400);
    text-align: center;
    padding: 3px 0;
    font-weight: 600;
}
.gantt-day-cell.weekend { background: #fafbff; }
.gantt-day-cell.today   { background: #dbeafe; color: var(--brand-600); font-weight: 800; }

/* Gantt body rows */
.gantt-body { min-width: max-content; }
.gantt-bar-row {
    display: flex;
    border-bottom: 1px solid var(--gray-100);
    min-height: 42px;
    align-items: center;
    position: relative;
}
.gantt-bar-row:hover { background: rgba(59,130,246,.04); }
.gantt-day-bg {
    width: 28px; flex-shrink: 0;
    border-right: 1px solid var(--gray-100);
    height: 42px;
}
.gantt-day-bg.weekend { background: #fafbff; }
.gantt-day-bg.today   { background: rgba(59,130,246,.07); }

/* The bar itself */
.gantt-bar {
    position: absolute;
    height: 18px;
    border-radius: 20px;
    z-index: 5;
    display: flex; align-items: center;
    padding: 0 8px;
    font-size: 10px; font-weight: 700; color: white;
    white-space: nowrap; overflow: hidden;
    cursor: default;
    transition: filter .15s;
    box-shadow: 0 2px 6px rgba(0,0,0,.15);
}
.gantt-bar:hover { filter: brightness(1.1); }
.gantt-bar.status-not-started  { background: linear-gradient(90deg,#94a3b8,#cbd5e1); color: var(--gray-700); }
.gantt-bar.status-in-progress  { background: linear-gradient(90deg,var(--brand-500),var(--brand-400)); }
.gantt-bar.status-completed    { background: linear-gradient(90deg,#059669,#10b981); }
.gantt-bar.status-delayed      { background: linear-gradient(90deg,#dc2626,#ef4444); }
.gantt-bar.status-blocked      { background: linear-gradient(90deg,#d97706,var(--accent)); }

/* Milestone diamond */
.gantt-milestone {
    position: absolute;
    width: 14px; height: 14px;
    background: var(--accent);
    transform: rotate(45deg);
    z-index: 5;
    box-shadow: 0 2px 6px rgba(245,158,11,.4);
}

/* Today line */
.gantt-today-line {
    position: absolute;
    top: 0; bottom: 0;
    width: 2px;
    background: rgba(59,130,246,.5);
    z-index: 8;
    pointer-events: none;
}

/* ── Table view ────────────────────────────────────────────────── */
#view-table { display: none; }

/* ── Status badges (reuse from WBS) ───────────────────────────── */
.bs { font-size: 10.5px; font-weight: 600; padding: 3px 9px; border-radius: 20px; }
.bs-not-started  { background: var(--gray-100); color: var(--gray-600); }
.bs-in-progress  { background: #dbeafe; color: #1e40af; }
.bs-completed    { background: #d1fae5; color: #065f46; }
.bs-delayed      { background: #fee2e2; color: #991b1b; }
.bs-blocked      { background: #fef3c7; color: #92400e; }
.bp-high   { background: #fee2e2; color: #b91c1c; }
.bp-medium { background: #fef3c7; color: #92400e; }
.bp-low    { background: #d1fae5; color: #065f46; }
</style>
@endpush

@section('content')

{{-- ── Page Header ─────────────────────────────────────────────── --}}
<div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-4">
    <div>
        <h1 class="page-title">
            <i class="fa-solid fa-chart-gantt me-2 text-primary" style="font-size:1.1rem;"></i>
            {{ $schedule->name }}
        </h1>
        <div class="mt-1 d-flex align-items-center gap-2" style="font-size:12.5px;color:var(--gray-400);">
            <i class="fa-solid fa-folder-open"></i>
            @if($schedule->project)
                <a href="{{ route('projects.show', $schedule->project) }}" class="text-decoration-none fw-semibold" style="color:var(--brand-500);">
                    {{ $schedule->project->name }}
                </a>
            @else
                <span class="text-decoration-none fw-semibold text-muted">N/A</span>
            @endif
            <span class="text-muted">·</span>
            <i class="fa-regular fa-calendar"></i>
            <span>{{ $schedule->start_date->format('d M Y') }} – {{ $schedule->end_date->format('d M Y') }}</span>
        </div>
    </div>
    <div class="d-flex gap-2 flex-wrap align-items-center">
        {{-- View toggle --}}
        <div class="btn-group gantt-view-toggle" role="group">
            <button type="button" class="btn btn-sm btn-outline-secondary active" id="btn-gantt" onclick="switchView('gantt')">
                <i class="fa-solid fa-bars-staggered me-1"></i>Gantt
            </button>
            <button type="button" class="btn btn-sm btn-outline-secondary" id="btn-table" onclick="switchView('table')">
                <i class="fa-solid fa-table me-1"></i>Table
            </button>
        </div>

        @if(!$schedule->sent_to_coordinator)
            <a href="{{ route('schedules.wbs', $schedule) }}" class="btn btn-success btn-sm">
                <i class="fa-solid fa-sitemap"></i> Manage WBS
            </a>
            @can('schedules.edit')
            <a href="{{ route('schedules.edit', $schedule) }}" class="btn btn-primary btn-sm">
                <i class="fa-solid fa-pen"></i> Edit
            </a>
            @endcan
        @else
            <span class="badge bg-warning text-dark px-3 py-2 fs-6">
                <i class="fa-solid fa-lock me-1"></i> Read-Only (Sent to Coordinator)
            </span>
        @endif

        <a href="{{ route('schedules.index') }}" class="btn btn-outline-secondary btn-sm">
            <i class="fa-solid fa-arrow-left"></i> Back
        </a>
    </div>
</div>

{{-- ── KPI Row ──────────────────────────────────────────────────── --}}
<div class="row g-3 mb-4">
    @php
        $badge = match($schedule->status) {
            'draft'     => ['secondary', 'DRAFT'],
            'active'    => ['primary',   'ACTIVE'],
            'delayed'   => ['danger',    'DELAYED'],
            'completed' => ['success',   'COMPLETED'],
            default     => ['secondary', strtoupper($schedule->status)],
        };
        $totalDays   = $schedule->start_date->diffInDays($schedule->end_date);
        $elapsedDays = min($schedule->start_date->diffInDays(now()), $totalDays);
        $taskCount   = $allTasks->count();
        $doneCount   = $allTasks->where('status','Completed')->count();
    @endphp
    <div class="col-6 col-md-3">
        <div class="card border-0">
            <div class="card-body py-3">
                <div class="d-flex align-items-center gap-3">
                    <div style="width:40px;height:40px;background:var(--brand-50);border-radius:var(--radius-md);display:flex;align-items:center;justify-content:center;">
                        <i class="fa-regular fa-calendar" style="color:var(--brand-500);"></i>
                    </div>
                    <div>
                        <div style="font-size:18px;font-weight:800;color:var(--gray-900);line-height:1;">{{ $totalDays }}d</div>
                        <div style="font-size:11px;color:var(--gray-400);font-weight:500;">Total Duration</div>
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
                        <i class="fa-solid fa-chart-line" style="color:#059669;"></i>
                    </div>
                    <div>
                        <div style="font-size:18px;font-weight:800;color:var(--gray-900);line-height:1;">{{ $schedule->progress }}%</div>
                        <div style="font-size:11px;color:var(--gray-400);font-weight:500;">Progress</div>
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
                        <i class="fa-solid fa-list-check" style="color:#1d4ed8;"></i>
                    </div>
                    <div>
                        <div style="font-size:18px;font-weight:800;color:var(--gray-900);line-height:1;">{{ $doneCount }}/{{ $taskCount }}</div>
                        <div style="font-size:11px;color:var(--gray-400);font-weight:500;">Tasks Done</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card border-0">
            <div class="card-body py-3">
                <div class="d-flex align-items-center gap-3">
                    <div style="width:40px;height:40px;background:var(--accent-light);border-radius:var(--radius-md);display:flex;align-items:center;justify-content:center;">
                        <i class="fa-solid fa-flag" style="color:var(--accent-hover);"></i>
                    </div>
                    <div>
                        <div>
                            <span class="badge bg-{{ $badge[0] }}">{{ $badge[1] }}</span>
                        </div>
                        <div style="font-size:11px;color:var(--gray-400);font-weight:500;margin-top:2px;">Schedule Status</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- ═══════════════════════════ GANTT VIEW ════════════════════════════════ --}}
<div id="view-gantt">
<div class="card border-0">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span class="fw-bold" style="color:var(--gray-700);">
            <i class="fa-solid fa-bars-staggered me-2 text-primary"></i>Gantt Chart
        </span>
        <div class="d-flex align-items-center gap-3" style="font-size:12px;color:var(--gray-400);">
            <span><span style="width:10px;height:10px;border-radius:50%;background:var(--brand-500);display:inline-block;margin-right:4px;"></span>In Progress</span>
            <span><span style="width:10px;height:10px;border-radius:50%;background:#10b981;display:inline-block;margin-right:4px;"></span>Completed</span>
            <span><span style="width:10px;height:10px;border-radius:50%;background:#94a3b8;display:inline-block;margin-right:4px;"></span>Not Started</span>
            <span><span style="width:10px;height:10px;border-radius:50%;background:#ef4444;display:inline-block;margin-right:4px;"></span>Delayed</span>
        </div>
    </div>
    <div class="card-body p-0">
        @if($allTasks->isEmpty())
            <div class="text-center py-5" style="color:var(--gray-400);">
                <i class="fa-solid fa-chart-gantt fa-3x d-block mb-3" style="opacity:.3;"></i>
                <p class="fw-semibold mb-1">No tasks yet</p>
                <p class="small mb-3">Add tasks from the WBS Manager to see them on the Gantt chart.</p>
                <a href="{{ route('schedules.wbs', $schedule) }}" class="btn btn-primary btn-sm">
                    <i class="fa-solid fa-sitemap"></i> Open WBS Manager
                </a>
            </div>
        @else
            <div class="gantt-wrap" id="ganttContainer">
                {{-- ── Left Panel: Task List ────────────────────────────── --}}
                <div class="gantt-left" id="ganttLeft">
                    <div class="gantt-left-header">
                        <div class="gantt-lh-cell col-wbs" style="justify-content:center;">WBS</div>
                        <div class="gantt-lh-cell col-name">Task Name</div>
                        <div class="gantt-lh-cell col-start">Start</div>
                        <div class="gantt-lh-cell col-end" style="border-right:none;">End</div>
                    </div>
                    @foreach($allTasks as $task)
                    @php
                        $indent = $task->parent_task_id ? ($task->parent && $task->parent->parent_task_id ? 'grandchild-indent' : 'child-indent') : '';
                        $rowClass = $task->parent_task_id ? 'is-child' : '';
                    @endphp
                    <div class="gantt-task-row {{ $rowClass }}" data-task-id="{{ $task->id }}">
                        <div class="gantt-tc wbs-col col-wbs">
                            <span class="wbs-pill">{{ $task->wbs_code }}</span>
                        </div>
                        <div class="gantt-tc col-name" style="overflow:hidden;">
                            @if($task->is_milestone)<span class="gantt-milestone-dot"></span>@endif
                            <span class="gantt-task-name {{ $indent }}" title="{{ $task->name }}">{{ $task->name }}</span>
                        </div>
                        <div class="gantt-tc gantt-date-cell col-start">
                            {{ optional($task->start_date)->format('d/m') ?? '—' }}
                        </div>
                        <div class="gantt-tc gantt-date-cell col-end">
                            {{ optional($task->end_date)->format('d/m') ?? '—' }}
                        </div>
                    </div>
                    @endforeach
                </div>

                {{-- ── Right Panel: Gantt Bars ──────────────────────────── --}}
                <div class="gantt-right" id="ganttRight">
                    {{-- Headers are rendered by JS --}}
                    <div id="ganttHeaderArea"></div>
                    <div class="gantt-body" id="ganttBody"></div>
                </div>
            </div>
        @endif
    </div>
</div>
</div>

{{-- ═══════════════════════════ TABLE VIEW ════════════════════════════════ --}}
<div id="view-table">
<div class="card border-0">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span class="fw-bold" style="color:var(--gray-700);">
            <i class="fa-solid fa-table me-2 text-primary"></i>Task Table
            <span class="badge bg-primary ms-2">{{ $allTasks->count() }}</span>
        </span>
        <a href="{{ route('schedules.wbs', $schedule) }}" class="btn btn-sm btn-outline-primary">
            <i class="fa-solid fa-plus"></i> Add Task
        </a>
    </div>
    <div class="card-body p-0">
        @if($allTasks->isEmpty())
            <div class="text-center py-5" style="color:var(--gray-400);">
                <i class="fa-solid fa-list-check fa-3x d-block mb-3" style="opacity:.3;"></i>
                <p class="small">No tasks yet. Add tasks from WBS Manager.</p>
            </div>
        @else
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>WBS</th>
                        <th>Task Name</th>
                        <th>Type</th>
                        <th>Priority</th>
                        <th>Status</th>
                        <th>Start</th>
                        <th>End</th>
                        <th>Planned Cost</th>
                        <th>Predecessor</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($allTasks as $task)
                    @php
                        $sc  = 'bs-' . strtolower(str_replace([' ','_'], '-', $task->status));
                        $pc  = 'bp-' . strtolower($task->priority);
                        $ind = $task->parent_task_id ? 'ps-4' : '';
                    @endphp
                    <tr>
                        <td><span class="wbs-pill">{{ $task->wbs_code }}</span></td>
                        <td class="{{ $ind }}">
                            @if($task->is_milestone)
                                <i class="fa-solid fa-diamond me-1" style="color:var(--accent);font-size:.7rem;"></i>
                            @endif
                            <span class="fw-semibold">{{ $task->name }}</span>
                        </td>
                        <td style="font-size:12px;color:var(--gray-500);">{{ $task->type }}</td>
                        <td><span class="bs {{ $pc }}" style="font-size:10.5px;font-weight:600;padding:3px 9px;border-radius:20px;">{{ $task->priority }}</span></td>
                        <td><span class="bs {{ $sc }}">{{ str_replace('_',' ', $task->status) }}</span></td>
                        <td style="font-size:12px;color:var(--gray-500);">{{ optional($task->start_date)->format('d M Y') ?? '—' }}</td>
                        <td style="font-size:12px;color:var(--gray-500);">{{ optional($task->end_date)->format('d M Y') ?? '—' }}</td>
                        <td style="font-size:12px;">
                            @if($task->planned_cost > 0)
                                <span class="fw-semibold" style="color:var(--gray-700);">
                                    ETB {{ number_format($task->planned_cost, 0) }}
                                </span>
                            @else
                                <span style="color:var(--gray-300);">—</span>
                            @endif
                        </td>
                        <td style="font-size:12px;color:var(--gray-400);">
                            {{ $task->predecessor ? $task->predecessor->wbs_code . ' – ' . $task->predecessor->name : '—' }}
                        </td>
                    </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr>
                        <th colspan="7" class="text-end">Total Planned Cost</th>
                        <th>ETB {{ number_format($allTasks->sum('planned_cost'), 0) }}</th>
                        <th></th>
                    </tr>
                </tfoot>
            </table>
        </div>
        @endif
    </div>
</div>
</div>

@endsection

@php
    $ganttTasks = $allTasks->map(function($t) {
        return [
            'id'        => $t->id,
            'wbs'       => $t->wbs_code,
            'name'      => $t->name,
            'start'     => optional($t->start_date)->format('Y-m-d'),
            'end'       => optional($t->end_date)->format('Y-m-d'),
            'status'    => strtolower(str_replace([' ','_'], '-', $t->status)),
            'milestone' => (bool) $t->is_milestone,
        ];
    })->values();
    $ganttTasksJson  = json_encode($ganttTasks);
    $schedStartJson  = $schedule->start_date->format('Y-m-d');
    $schedEndJson    = $schedule->end_date->format('Y-m-d');
@endphp

@push('scripts')
<script type="application/json" id="gantt-tasks-data">{!! $ganttTasksJson !!}</script>
<script>
/* ── View toggle ─────────────────────────────────────────────── */
function switchView(v) {
    document.getElementById('view-gantt').style.display = v === 'gantt' ? 'block' : 'none';
    document.getElementById('view-table').style.display = v === 'table' ? 'block' : 'none';
    document.getElementById('btn-gantt').classList.toggle('active', v === 'gantt');
    document.getElementById('btn-table').classList.toggle('active', v === 'table');
}

/* ── Gantt chart builder ─────────────────────────────────────── */
(function() {
    // Task data from Blade (pre-encoded in PHP block to avoid Blade parse issues)
    const tasks     = JSON.parse(document.getElementById('gantt-tasks-data').textContent || '[]');
    if (!tasks.length) return;

    // Determine date range from schedule
    const schedStart = new Date('{{ $schedStartJson }}');
    const schedEnd   = new Date('{{ $schedEndJson }}');

    // Expand range to cover all task dates too
    let minDate = new Date(schedStart), maxDate = new Date(schedEnd);
    tasks.forEach(t => {
        if (t.start) { const d = new Date(t.start); if (d < minDate) minDate = d; }
        if (t.end)   { const d = new Date(t.end);   if (d > maxDate) maxDate = d; }
    });

    // Build array of all days
    const days = [];
    let cur = new Date(minDate);
    while (cur <= maxDate) { days.push(new Date(cur)); cur.setDate(cur.getDate()+1); }

    const DAY_W = 28; // px per day
    const today = new Date(); today.setHours(0,0,0,0);

    // Group days by month for header
    const months = [];
    days.forEach((d, i) => {
        const key = d.getFullYear() + '-' + d.getMonth();
        if (!months.length || months[months.length-1].key !== key) {
            months.push({ key, label: d.toLocaleString('default',{month:'short',year:'2-digit'}), start: i, count: 1 });
        } else {
            months[months.length-1].count++;
        }
    });

    // ── Render header ─────────────────────────────────────────
    const headerArea = document.getElementById('ganttHeaderArea');
    headerArea.style.position = 'sticky';
    headerArea.style.top = '0';
    headerArea.style.zIndex = '10';
    headerArea.style.background = 'var(--gray-50)';
    headerArea.style.borderBottom = '1px solid var(--gray-200)';

    // Month row
    const monthRow = document.createElement('div');
    monthRow.style.cssText = 'display:flex;min-width:max-content;border-bottom:1px solid var(--gray-200);';
    months.forEach(m => {
        const el = document.createElement('div');
        el.className = 'gantt-month-label';
        el.style.width = (m.count * DAY_W) + 'px';
        el.textContent = m.label;
        monthRow.appendChild(el);
    });
    headerArea.appendChild(monthRow);

    // Day row
    const dayRow = document.createElement('div');
    dayRow.style.cssText = 'display:flex;min-width:max-content;';
    days.forEach(d => {
        const el = document.createElement('div');
        el.className = 'gantt-day-cell';
        const dow = d.getDay();
        if (dow === 0 || dow === 6) el.classList.add('weekend');
        const isToday = d.toDateString() === today.toDateString();
        if (isToday) el.classList.add('today');
        el.textContent = d.getDate();
        dayRow.appendChild(el);
    });
    headerArea.appendChild(dayRow);

    // ── Render body rows ──────────────────────────────────────
    const body = document.getElementById('ganttBody');
    const totalWidth = days.length * DAY_W;

    // Today line
    const todayIdx = days.findIndex(d => d.toDateString() === today.toDateString());

    tasks.forEach((task, rowIdx) => {
        const row = document.createElement('div');
        row.className = 'gantt-bar-row';
        row.style.position = 'relative';
        row.style.minWidth = totalWidth + 'px';

        // Background day cells
        days.forEach((d, di) => {
            const cell = document.createElement('div');
            cell.className = 'gantt-day-bg';
            const dow = d.getDay();
            if (dow === 0 || dow === 6) cell.classList.add('weekend');
            if (d.toDateString() === today.toDateString()) cell.classList.add('today');
            row.appendChild(cell);
        });

        // Today vertical line
        if (todayIdx >= 0) {
            const tl = document.createElement('div');
            tl.className = 'gantt-today-line';
            tl.style.left = (todayIdx * DAY_W + DAY_W/2) + 'px';
            row.appendChild(tl);
        }

        if (task.start && task.end) {
            const sDate = new Date(task.start);
            const eDate = new Date(task.end);
            const startIdx = days.findIndex(d => d.toDateString() === sDate.toDateString());
            const endIdx   = days.findIndex(d => d.toDateString() === eDate.toDateString());

            if (startIdx >= 0) {
                const barLeft  = startIdx * DAY_W + 3;
                const barWidth = Math.max((endIdx - startIdx + 1) * DAY_W - 6, 10);

                if (task.milestone) {
                    const ms = document.createElement('div');
                    ms.className = 'gantt-milestone';
                    ms.style.left  = (barLeft + barWidth/2 - 7) + 'px';
                    ms.style.top   = '14px';
                    ms.title = task.name + ' (Milestone)';
                    row.appendChild(ms);
                } else {
                    const bar = document.createElement('div');
                    bar.className = `gantt-bar status-${task.status}`;
                    bar.style.left  = barLeft + 'px';
                    bar.style.width = barWidth + 'px';
                    bar.style.top   = '12px';
                    bar.title = `${task.name}\n${task.start} → ${task.end}`;
                    if (barWidth > 50) bar.textContent = task.name;
                    row.appendChild(bar);
                }
            }
        }

        body.appendChild(row);
    });

    // Sync scroll between left and right panels
    const left  = document.getElementById('ganttLeft');
    const right = document.getElementById('ganttRight');
    right.addEventListener('scroll', () => { left.scrollTop = right.scrollTop; });
    left.addEventListener('scroll',  () => { right.scrollTop = left.scrollTop; });

    // Scroll to today
    if (todayIdx > 5) {
        setTimeout(() => { right.scrollLeft = Math.max(0, (todayIdx - 5) * DAY_W); }, 100);
    }
})();
</script>
@endpush
