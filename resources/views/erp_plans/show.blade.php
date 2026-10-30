@extends('layouts.app')

@section('title', $erp_plan->name)

@push('styles')
<style>
@import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap');
* { font-family: 'Inter', sans-serif; }

/* ─── Stat Cards ──────────────────────────────────────── */
.stat-grid { display: grid; grid-template-columns: repeat(5,1fr); gap: 16px; margin-bottom: 28px; }
@media(max-width:992px){ .stat-grid { grid-template-columns: repeat(2,1fr); } }

.stat-card {
    background: #fff;
    border-radius: 14px;
    border: 2px solid #e2e8f0;
    padding: 20px 22px;
    transition: box-shadow .2s;
}
.stat-card:hover { box-shadow: 0 6px 20px rgba(0,0,0,.09); }
.stat-card.blue  { border-color: #3b82f6; }
.stat-card.green { border-color: #22c55e; }
.stat-card.red   { border-color: #ef4444; }
.stat-card.gray  { border-color: #94a3b8; }
.stat-card.cyan  { border-color: #06b6d4; background: linear-gradient(135deg,#ecfeff,#e0f2fe); }

.stat-num  { font-size: 2rem; font-weight: 800; line-height: 1; margin-bottom: 4px; }
.stat-label{ font-size: .7rem; font-weight: 700; letter-spacing: .8px; text-transform: uppercase; color: #64748b; }
.stat-card.blue  .stat-num { color: #3b82f6; }
.stat-card.green .stat-num { color: #22c55e; }
.stat-card.red   .stat-num { color: #ef4444; }
.stat-card.gray  .stat-num { color: #64748b; }
.stat-card.cyan  .stat-num { color: #0369a1; font-size: 1.5rem; }
.budget-row { display: flex; justify-content: space-between; margin-top: 2px; font-size: .75rem; color: #475569; }
.budget-row span { font-weight: 600; color: #1e293b; }

/* ─── Tabs ────────────────────────────────────────────── */
.plan-tabs { border-bottom: 2px solid #e2e8f0; margin-bottom: 24px; display: flex; gap: 4px; flex-wrap: wrap; }
.plan-tab {
    padding: 10px 18px; border: none; background: none; cursor: pointer;
    font-size: .85rem; font-weight: 600; color: #64748b;
    border-bottom: 3px solid transparent; margin-bottom: -2px;
    border-radius: 8px 8px 0 0; transition: all .15s;
    display: flex; align-items: center; gap: 7px;
}
.plan-tab:hover { color: #1e293b; background: #f8fafc; }
.plan-tab.active { color: #2563eb; border-bottom-color: #2563eb; background: #eff6ff; }
.tab-pane { display: none; }
.tab-pane.active { display: block; }

/* ─── Gantt Chart ─────────────────────────────────────── */
.gantt-wrap { background: #fff; border-radius: 14px; border: 1px solid #e2e8f0; overflow: hidden; }
.gantt-header { background: #0f172a; color: #fff; display: flex; }
.gantt-left-head { width: 340px; min-width: 340px; padding: 14px 20px; font-size: .8rem; font-weight: 700; letter-spacing: .6px; display: flex; align-items: center; justify-content: space-between; border-right: 1px solid rgba(255,255,255,.1); }
.gantt-months { flex: 1; overflow: hidden; display: flex; }
.gantt-month { flex: 1; text-align: center; padding: 14px 8px; font-size: .78rem; font-weight: 700; letter-spacing: .5px; border-right: 1px solid rgba(255,255,255,.08); }

.gantt-body { overflow-x: auto; }
.gantt-row { display: flex; border-bottom: 1px solid #f1f5f9; min-height: 52px; align-items: stretch; }
.gantt-row:hover { background: #fafbff; }
.gantt-row.parent-row { background: #f1f5f9 !important; font-weight: 700; }

.gantt-left { width: 340px; min-width: 340px; padding: 10px 16px; border-right: 1px solid #e2e8f0; display: flex; align-items: center; gap: 10px; }
.task-wbs   { font-size: .7rem; color: #94a3b8; font-weight: 600; min-width: 28px; }
.task-name  { font-size: .85rem; font-weight: 600; color: #1e293b; flex: 1; }
.task-pct   { font-size: .75rem; font-weight: 700; }
.pct-done   { color: #22c55e; }
.pct-zero   { color: #94a3b8; }

.gantt-bars { flex: 1; position: relative; display: flex; align-items: center; padding: 8px 0; }
.today-line {
    position: absolute; top: 0; bottom: 0; width: 2px;
    background: #f59e0b; z-index: 10;
}
.gantt-bar {
    position: absolute;
    height: 22px;
    border-radius: 6px;
    display: flex; align-items: center;
    font-size: .65rem; font-weight: 700; color: #fff; padding: 0 8px;
    overflow: hidden; white-space: nowrap;
    transition: opacity .2s;
    cursor: pointer;
}
.gantt-bar:hover { opacity: .85; }
.bar-pending    { background: linear-gradient(90deg,#3b82f6,#60a5fa); }
.bar-in_progress{ background: linear-gradient(90deg,#8b5cf6,#a78bfa); }
.bar-completed  { background: linear-gradient(90deg,#22c55e,#4ade80); }
.bar-on_hold    { background: linear-gradient(90deg,#f59e0b,#fcd34d); color: #78350f; }
.bar-delayed    { background: repeating-linear-gradient(45deg,#ef4444,#ef4444 8px,#dc2626 8px,#dc2626 16px); }

.late-badge { background: #ef4444; color: #fff; font-size: 10px; padding: 1px 6px; border-radius: 4px; margin-left: 4px; }

/* ─── Task Table ──────────────────────────────────────── */
.ttable { width: 100%; border-collapse: collapse; }
.ttable th {
    padding: 11px 14px; font-size: .72rem; text-transform: uppercase;
    letter-spacing: .6px; color: #64748b; font-weight: 700;
    background: #f8fafc; border-bottom: 2px solid #e2e8f0; white-space: nowrap;
}
.ttable td { padding: 12px 14px; border-bottom: 1px solid #f1f5f9; vertical-align: middle; font-size: .875rem; }
.ttable tr:hover td { background: #fafbff; }
.ttable tr.parent-tr td { background: #f1f5f9; font-weight: 700; }

.prog-bar { height: 6px; border-radius: 3px; background: #e2e8f0; overflow: hidden; }
.prog-bar-fill { height: 100%; border-radius: 3px; background: linear-gradient(90deg,#22c55e,#4ade80); }

/* ─── Resource Schedule ───────────────────────────────── */
.res-schedule { background: #fff; border-radius: 14px; border: 1px solid #e2e8f0; overflow: hidden; }
.res-schedule-head { padding: 16px 22px; border-bottom: 1px solid #e2e8f0; display: flex; align-items: center; gap: 10px; }

.rtable { width: 100%; border-collapse: collapse; }
.rtable th { padding: 11px 16px; font-size: .72rem; text-transform: uppercase; letter-spacing: .6px; color: #64748b; font-weight: 700; background: #f8fafc; border-bottom: 2px solid #e2e8f0; }
.rtable td { padding: 12px 16px; border-bottom: 1px solid #f1f5f9; font-size: .875rem; }
.rtable tr:hover td { background: #fafbff; }

.status-badge { display: inline-block; padding: 3px 10px; border-radius: 50px; font-size: 11px; font-weight: 600; }
.sb-pending    { background: #e2e8f0; color: #475569; }
.sb-in_progress{ background: #ede9fe; color: #5b21b6; }
.sb-completed  { background: #dcfce7; color: #15803d; }
.sb-on_hold    { background: #fef9c3; color: #78350f; }
</style>
@endpush

@section('content')

@php
    $tasks        = $erp_plan->tasks;
    $totalTasks   = $tasks->count();
    $completed    = $tasks->where('status','completed')->count();
    $delayed      = 0;
    $today        = now()->startOfDay();
    foreach ($tasks as $t) {
        if ($t->end_date && \Carbon\Carbon::parse($t->end_date)->lt($today) && $t->actual_progress < 100) $delayed++;
    }
    $progress = $erp_plan->overall_progress ?? ($totalTasks > 0 ? round($tasks->avg('actual_progress'), 1) : 0);
    $allResources = $tasks->flatMap->resources;
    $laborCost    = $allResources->where('resource_type','manpower')->sum('total_cost');
    $matCost      = $allResources->where('resource_type','material')->sum('total_cost');
    $equipCost    = $allResources->where('resource_type','equipment')->sum('total_cost');
    $calcBudget   = $laborCost + $matCost + $equipCost;

    // Gantt date range
    $planStart = $erp_plan->plan_start_date ?? now()->startOfMonth();
    $planEnd   = $erp_plan->plan_end_date   ?? now()->addMonths(3)->endOfMonth();
    $totalDays = max(1, $planStart->diffInDays($planEnd));

    // Build months for header
    $months = [];
    $cur = $planStart->copy()->startOfMonth();
    while ($cur->lte($planEnd)) {
        $months[] = $cur->copy();
        $cur->addMonth();
    }
@endphp

{{-- ── Header Bar ─────────────────────────────────────── --}}
<div class="d-flex justify-content-between align-items-start mb-4 flex-wrap gap-3">
    <div>
        <h1 class="page-title mb-1">
            <i class="fa-solid fa-diagram-project me-2 text-primary"></i>{{ $erp_plan->name }}
        </h1>
        <p class="text-muted small mb-0">
            Project: <strong>{{ $erp_plan->project->name ?? '—' }}</strong>
            &nbsp;|&nbsp; Created by {{ $erp_plan->creator->name ?? '—' }}
            @if($erp_plan->plan_start_date)
            &nbsp;|&nbsp; {{ $erp_plan->plan_start_date->format('d M Y') }} → {{ $erp_plan->plan_end_date->format('d M Y') }}
            @endif
        </p>
    </div>
    <div class="d-flex gap-2">
        @php
            $sc = ['draft'=>'secondary','active'=>'success','on_hold'=>'warning','completed'=>'primary','cancelled'=>'danger'];
        @endphp
        <span class="badge bg-{{ $sc[$erp_plan->status] ?? 'secondary' }} fs-6 px-3 py-2">
            {{ ucfirst(str_replace('_',' ',$erp_plan->status)) }}
        </span>
        <a href="{{ route('erp-plans.edit', $erp_plan) }}" class="btn btn-outline-primary">
            <i class="fa-solid fa-pen me-1"></i> Edit
        </a>
        <a href="{{ route('erp-plans.index') }}" class="btn btn-outline-secondary">
            <i class="fa-solid fa-arrow-left me-1"></i> Back
        </a>
    </div>
</div>

@if(session('success'))
<div class="alert alert-success alert-dismissible fade show mb-4">
    <i class="fa-solid fa-circle-check me-2"></i>{{ session('success') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
@endif

{{-- ── Stats Row ─────────────────────────────────────────── --}}
<div class="stat-grid">
    <div class="stat-card blue">
        <div class="stat-num">{{ $totalTasks }}</div>
        <div class="stat-label">Total Tasks</div>
    </div>
    <div class="stat-card green">
        <div class="stat-num">{{ $completed }}</div>
        <div class="stat-label">Completed</div>
    </div>
    <div class="stat-card red">
        <div class="stat-num">{{ $delayed }}</div>
        <div class="stat-label">Delayed</div>
    </div>
    <div class="stat-card gray">
        <div class="stat-num">{{ number_format($progress,1) }}%</div>
        <div class="stat-label">Overall Progress</div>
        <div class="prog-bar mt-2"><div class="prog-bar-fill" style="width:{{ $progress }}%;"></div></div>
    </div>
    <div class="stat-card cyan">
        <div class="stat-num">ETB {{ number_format($calcBudget,2) }}</div>
        <div class="stat-label">Calculated Project Budget</div>
        <div class="mt-2">
            <div class="budget-row"><span class="text-muted">Labor:</span><span>ETB {{ number_format($laborCost,2) }}</span></div>
            <div class="budget-row"><span class="text-muted">Materials:</span><span>ETB {{ number_format($matCost,2) }}</span></div>
            <div class="budget-row"><span class="text-muted">Equipment:</span><span>ETB {{ number_format($equipCost,2) }}</span></div>
        </div>
    </div>
</div>

{{-- ── Tabs ─────────────────────────────────────────────── --}}
<div class="plan-tabs">
    <button class="plan-tab active" onclick="switchTab('gantt',this)">
        <i class="fa-solid fa-chart-gantt"></i> Gantt Chart
    </button>
    <button class="plan-tab" onclick="switchTab('tasktable',this)">
        <i class="fa-solid fa-table-list"></i> Task Table
    </button>
    <button class="plan-tab" onclick="switchTab('manpower',this)">
        <i class="fa-solid fa-users"></i> Manpower Schedule
    </button>
    <button class="plan-tab" onclick="switchTab('equipment',this)">
        <i class="fa-solid fa-gears"></i> Equipment Schedule
    </button>
    <button class="plan-tab" onclick="switchTab('tools',this)">
        <i class="fa-solid fa-screwdriver-wrench"></i> Tools Schedule
    </button>
    <button class="plan-tab" onclick="switchTab('material',this)">
        <i class="fa-solid fa-boxes-stacked"></i> Material Schedule
    </button>
</div>

{{-- ════════════════════════════════════════ --}}
{{--  TAB 1: GANTT CHART                      --}}
{{-- ════════════════════════════════════════ --}}
<div class="tab-pane active" id="tab-gantt">
    <div class="gantt-wrap">
        {{-- Header --}}
        <div class="gantt-header">
            <div class="gantt-left-head">
                TASK NAME
                <span style="font-size:.65rem;opacity:.6;cursor:default;">⇔ drag to resize</span>
            </div>
            <div class="gantt-months">
                @foreach($months as $m)
                <div class="gantt-month">{{ strtoupper($m->format('M Y')) }}</div>
                @endforeach
            </div>
        </div>

        {{-- Rows --}}
        <div class="gantt-body">
            @forelse($tasks->sortBy('sort_order') as $task)
            @php
                $tStart  = $task->start_date ? \Carbon\Carbon::parse($task->start_date) : $planStart;
                $tEnd    = $task->end_date   ? \Carbon\Carbon::parse($task->end_date)   : $planEnd;
                $leftPct = max(0, min(100, $planStart->diffInDays($tStart) / $totalDays * 100));
                $widPct  = max(1,  min(100 - $leftPct, $planStart->diffInDays($tEnd)   / $totalDays * 100 - $leftPct));
                $todayPct= max(0, min(100, $planStart->diffInDays($today) / $totalDays * 100));
                $isLate  = $tEnd->lt($today) && ($task->actual_progress ?? 0) < 100;
                $barCls  = $isLate ? 'bar-delayed' : 'bar-' . ($task->status ?? 'pending');
                $pct     = $task->actual_progress ?? 0;
            @endphp
            <div class="gantt-row {{ $task->parent_task_id ? '' : 'parent-row' }}">
                <div class="gantt-left">
                    <span class="task-wbs">{{ $task->wbs_code }}</span>
                    <span class="task-name" style="{{ $task->parent_task_id ? '' : 'font-size:.9rem;' }}">
                        {{ $task->name }}
                        @if($isLate)
                        <span class="late-badge">+{{ $tEnd->diffInDays($today) }}d late</span>
                        @endif
                    </span>
                    <span class="task-pct {{ $pct > 0 ? 'pct-done' : 'pct-zero' }}">{{ $pct }}%</span>
                    <span style="font-size:14px;color:#94a3b8;cursor:pointer;" title="Add sub-task">+</span>
                    <span style="font-size:14px;color:#94a3b8;cursor:pointer;" title="Edit task">✎</span>
                </div>
                <div class="gantt-bars">
                    {{-- Today line --}}
                    <div class="today-line" style="left:{{ $todayPct }}%;"></div>
                    {{-- Task bar --}}
                    <div class="gantt-bar {{ $barCls }}"
                         style="left:{{ $leftPct }}%;width:{{ $widPct }}%;"
                         title="{{ $task->name }}: {{ $tStart->format('d M') }} → {{ $tEnd->format('d M Y') }}">
                        @if($widPct > 8){{ $task->name }}@endif
                    </div>
                </div>
            </div>
            @empty
            <div style="padding:60px;text-align:center;color:#94a3b8;">
                <i class="fa-solid fa-chart-gantt fa-3x mb-3" style="opacity:.2;display:block;"></i>
                No tasks in this plan yet.
            </div>
            @endforelse
        </div>
    </div>
</div>

{{-- ════════════════════════════════════════ --}}
{{--  TAB 2: TASK TABLE                       --}}
{{-- ════════════════════════════════════════ --}}
<div class="tab-pane" id="tab-tasktable">
    <div class="res-schedule">
        <div class="table-responsive">
            <table class="ttable">
                <thead>
                    <tr>
                        <th>WBS</th>
                        <th>Task Name</th>
                        <th>Start</th>
                        <th>End</th>
                        <th>Duration</th>
                        <th>Status</th>
                        <th style="min-width:130px;">Progress</th>
                        <th class="text-end">Planned Cost</th>
                        <th>Resources</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($tasks->sortBy('sort_order') as $task)
                    @php
                        $tEnd2  = $task->end_date ? \Carbon\Carbon::parse($task->end_date) : null;
                        $isLate2= $tEnd2 && $tEnd2->lt($today) && ($task->actual_progress ?? 0) < 100;
                    @endphp
                    <tr class="{{ $task->parent_task_id ? '' : 'parent-tr' }}">
                        <td><code class="small">{{ $task->wbs_code }}</code></td>
                        <td>
                            <span class="fw-semibold">{{ $task->name }}</span>
                            @if($isLate2)<span class="late-badge ms-1">Late</span>@endif
                        </td>
                        <td class="small text-muted">{{ $task->start_date->format('d M Y') ?? '—' }}</td>
                        <td class="small text-muted">{{ $task->end_date->format('d M Y') ?? '—' }}</td>
                        <td class="small">{{ $task->duration_days ? $task->duration_days.'d' : '—' }}</td>
                        <td>
                            <span class="status-badge sb-{{ $task->status ?? 'pending' }}">
                                {{ ucfirst(str_replace('_',' ',$task->status ?? 'pending')) }}
                            </span>
                        </td>
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                <div class="prog-bar flex-grow-1">
                                    <div class="prog-bar-fill" style="width:{{ $task->actual_progress ?? 0 }}%;"></div>
                                </div>
                                <span class="small fw-semibold" style="white-space:nowrap;">{{ $task->actual_progress ?? 0 }}%</span>
                            </div>
                        </td>
                        <td class="text-end small fw-semibold">
                            {{ $task->planned_cost ? 'ETB '.number_format($task->planned_cost,0) : '—' }}
                        </td>
                        <td>
                            @foreach($task->resources->groupBy('resource_type') as $rtype => $ress)
                            <span class="badge me-1"
                                  style="background:{{ $rtype==='material' ? '#dbeafe' : ($rtype==='manpower' ? '#dcfce7' : '#fef9c3') }};
                                         color:{{ $rtype==='material' ? '#1e40af' : ($rtype==='manpower' ? '#14532d' : '#78350f') }};">
                                {{ $ress->count() }} {{ ucfirst($rtype) }}
                            </span>
                            @endforeach
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="9" class="text-center py-5 text-muted">No tasks found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

{{-- ════════════════════════════════════════ --}}
{{--  TAB 3: MANPOWER SCHEDULE                --}}
{{-- ════════════════════════════════════════ --}}
<div class="tab-pane" id="tab-manpower">
    @include('erp_plans.partials.resource-schedule', ['resourceType' => 'manpower', 'title' => 'Manpower Schedule', 'icon' => 'fa-users', 'color' => '#22c55e', 'tasks' => $tasks])
</div>

{{-- ════════════════════════════════════════ --}}
{{--  TAB 4: EQUIPMENT SCHEDULE               --}}
{{-- ════════════════════════════════════════ --}}
<div class="tab-pane" id="tab-equipment">
    @include('erp_plans.partials.resource-schedule', ['resourceType' => 'equipment', 'title' => 'Equipment Schedule', 'icon' => 'fa-gears', 'color' => '#f59e0b', 'tasks' => $tasks])
</div>

{{-- ════════════════════════════════════════ --}}
{{--  TAB 5: TOOLS SCHEDULE                   --}}
{{-- ════════════════════════════════════════ --}}
<div class="tab-pane" id="tab-tools">
    @include('erp_plans.partials.resource-schedule', ['resourceType' => 'tools', 'title' => 'Tools Schedule', 'icon' => 'fa-screwdriver-wrench', 'color' => '#8b5cf6', 'tasks' => $tasks])
</div>

{{-- ════════════════════════════════════════ --}}
{{--  TAB 6: MATERIAL SCHEDULE                --}}
{{-- ════════════════════════════════════════ --}}
<div class="tab-pane" id="tab-material">
    @include('erp_plans.partials.resource-schedule', ['resourceType' => 'material', 'title' => 'Material Schedule', 'icon' => 'fa-boxes-stacked', 'color' => '#3b82f6', 'tasks' => $tasks])
</div>

@endsection

@push('scripts')
<script>
function switchTab(name, btn) {
    document.querySelectorAll('.tab-pane').forEach(p => p.classList.remove('active'));
    document.querySelectorAll('.plan-tab').forEach(b => b.classList.remove('active'));
    document.getElementById('tab-' + name).classList.add('active');
    btn.classList.add('active');
}
</script>
@endpush
