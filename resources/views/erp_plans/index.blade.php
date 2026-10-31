@extends('layouts.app')

@section('title', 'ERP Plans & Schedules')

@section('content')
<style>
@import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap');
* { font-family: 'Inter', sans-serif; }

/* ── Hero Header ───────────────────────────────────── */
.plans-hero {
    background: linear-gradient(135deg, #0f172a 0%, #1e3a8a 55%, #2563eb 100%);
    border-radius: 18px;
    padding: 32px 36px;
    margin-bottom: 32px;
    position: relative;
    overflow: hidden;
}
.plans-hero::before {
    content: '';
    position: absolute; top: -80px; right: -80px;
    width: 300px; height: 300px;
    background: rgba(255,255,255,.04);
    border-radius: 50%;
    pointer-events: none;
}
.plans-hero::after {
    content: '';
    position: absolute; bottom: -60px; right: 120px;
    width: 180px; height: 180px;
    background: rgba(255,255,255,.03);
    border-radius: 50%;
    pointer-events: none;
}
.hero-title { font-size: 1.8rem; font-weight: 800; color: #fff; margin: 0 0 6px; }
.hero-sub   { color: rgba(255,255,255,.6); font-size: .9rem; margin: 0; }
.hero-cta {
    background: #fff;
    color: #1e3a8a;
    border: none;
    font-weight: 700;
    padding: 10px 22px;
    border-radius: 10px;
    font-size: .875rem;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    transition: all .2s;
    white-space: nowrap;
}
.hero-cta:hover { background: #eff6ff; color: #1e3a8a; transform: translateY(-1px); box-shadow: 0 6px 16px rgba(0,0,0,.15); }

/* ── Summary Stats ─────────────────────────────────── */
.stats-row { display: flex; gap: 16px; margin-bottom: 28px; flex-wrap: wrap; }
.stat-pill {
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 14px 22px;
    display: flex;
    align-items: center;
    gap: 12px;
    flex: 1;
    min-width: 140px;
    box-shadow: 0 1px 3px rgba(0,0,0,.06);
}
.stat-pill-icon {
    width: 40px; height: 40px;
    border-radius: 10px;
    display: flex; align-items: center; justify-content: center;
    font-size: 1rem;
    flex-shrink: 0;
}
.stat-pill-num  { font-size: 1.4rem; font-weight: 800; line-height: 1; }
.stat-pill-lbl  { font-size: .7rem; font-weight: 600; letter-spacing: .6px; text-transform: uppercase; color: #94a3b8; }

/* ── Plan Cards ────────────────────────────────────── */
.plans-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(340px, 1fr));
    gap: 20px;
}
.plan-card {
    background: #fff;
    border-radius: 16px;
    border: 1px solid #e2e8f0;
    box-shadow: 0 2px 8px rgba(0,0,0,.06);
    overflow: hidden;
    transition: transform .2s, box-shadow .2s;
}
.plan-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 10px 32px rgba(37,99,235,.12);
}

.plan-card-top {
    padding: 20px 22px 14px;
    border-bottom: 1px solid #f1f5f9;
}
.plan-title {
    font-size: 1rem;
    font-weight: 700;
    color: #1e293b;
    text-decoration: none;
    display: block;
    margin-bottom: 4px;
    transition: color .15s;
}
.plan-title:hover { color: #2563eb; }
.plan-project-tag {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    background: #eff6ff;
    color: #1d4ed8;
    font-size: .72rem;
    font-weight: 600;
    padding: 3px 10px;
    border-radius: 50px;
    margin-bottom: 14px;
}

/* Progress bar */
.prog-wrap { margin-bottom: 8px; }
.prog-label { display: flex; justify-content: space-between; margin-bottom: 4px; font-size: .72rem; color: #94a3b8; font-weight: 600; }
.prog-bar { height: 7px; background: #f1f5f9; border-radius: 4px; overflow: hidden; }
.prog-fill { height: 100%; border-radius: 4px; transition: width .5s; }
.prog-fill.blue   { background: linear-gradient(90deg,#3b82f6,#60a5fa); }
.prog-fill.green  { background: linear-gradient(90deg,#22c55e,#4ade80); }
.prog-fill.orange { background: linear-gradient(90deg,#f59e0b,#fcd34d); }

/* Plan card meta row */
.plan-meta {
    padding: 14px 22px;
    display: flex;
    gap: 16px;
    flex-wrap: wrap;
    border-bottom: 1px solid #f1f5f9;
}
.meta-chip {
    display: flex;
    align-items: center;
    gap: 5px;
    font-size: .75rem;
    color: #64748b;
}
.meta-chip i { font-size: .7rem; color: #94a3b8; }
.meta-chip strong { color: #1e293b; }

/* Status badge */
.s-badge {
    font-size: .68rem;
    font-weight: 700;
    padding: 3px 10px;
    border-radius: 50px;
    text-transform: uppercase;
    letter-spacing: .4px;
}
.s-draft     { background: #f1f5f9; color: #64748b; }
.s-active    { background: #dcfce7; color: #15803d; }
.s-on_hold   { background: #fef9c3; color: #a16207; }
.s-completed { background: #dbeafe; color: #1d4ed8; }
.s-cancelled { background: #fee2e2; color: #b91c1c; }

/* Plan card footer */
.plan-card-foot {
    padding: 12px 22px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    background: #fafbfd;
}
.view-btn {
    background: linear-gradient(135deg,#1e3a8a,#2563eb);
    color: #fff;
    border: none;
    padding: 7px 18px;
    border-radius: 8px;
    font-size: .8rem;
    font-weight: 700;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    transition: opacity .2s, transform .1s;
}
.view-btn:hover { opacity: .88; transform: translateY(-1px); color: #fff; }
.del-btn {
    background: none;
    border: none;
    color: #dc2626;
    cursor: pointer;
    padding: 6px 10px;
    border-radius: 8px;
    transition: background .15s;
}
.del-btn:hover { background: #fee2e2; }

/* Empty state */
.empty-hero {
    background: #fff;
    border: 2px dashed #e2e8f0;
    border-radius: 18px;
    padding: 60px 30px;
    text-align: center;
}
.empty-icon {
    width: 80px; height: 80px;
    background: #eff6ff;
    border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    margin: 0 auto 20px;
    font-size: 2rem;
    color: #3b82f6;
}

/* Custom Tabs styling */
.premium-tabs {
    border-bottom: 2px solid #e2e8f0;
    margin-bottom: 24px;
}
.premium-tabs .nav-link {
    color: #64748b;
    font-weight: 600;
    border: none;
    border-bottom: 2px solid transparent;
    padding: 12px 20px;
    margin-bottom: -2px;
    transition: all 0.2s;
}
.premium-tabs .nav-link:hover {
    color: #1e3a8a;
}
.premium-tabs .nav-link.active {
    color: #1e3a8a;
    background: transparent;
    border-bottom: 2px solid #1e3a8a;
}
</style>

@if(session('success'))
<div class="alert alert-success alert-dismissible fade show mb-4 border-0 shadow-sm">
    <i class="fa-solid fa-circle-check me-2"></i>{{ session('success') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
@endif

{{-- ── Hero Header ──────────────────────────────────── --}}
<div class="plans-hero">
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
        <div>
            <h1 class="hero-title">
                <i class="fa-solid fa-diagram-project me-2" style="opacity:.8;"></i>Take-Offs & Schedules
            </h1>
            <p class="hero-sub">View your quantity take-offs and project timelines</p>
        </div>
    </div>
</div>

{{-- ── Summary Stats ────────────────────────────────── --}}
@php
    $totalTakeoffs = $takeoffs->count();
    $activeTakeoffs = $takeoffs->where('status', 'active')->count();
    $draftTakeoffs = $takeoffs->where('status', 'draft')->count();
    $completedTakeoffs = $takeoffs->where('status', 'completed')->count();
@endphp
<div class="stats-row">
    <div class="stat-pill">
        <div class="stat-pill-icon" style="background:#eff6ff;">
            <i class="fa-solid fa-calculator" style="color:#3b82f6;"></i>
        </div>
        <div>
            <div class="stat-pill-num" style="color:#1e293b;">{{ $totalTakeoffs }}</div>
            <div class="stat-pill-lbl">Total Take-Offs</div>
        </div>
    </div>
    <div class="stat-pill">
        <div class="stat-pill-icon" style="background:#dcfce7;">
            <i class="fa-solid fa-circle-play" style="color:#22c55e;"></i>
        </div>
        <div>
            <div class="stat-pill-num" style="color:#22c55e;">{{ $activeTakeoffs }}</div>
            <div class="stat-pill-lbl">Active</div>
        </div>
    </div>
    <div class="stat-pill">
        <div class="stat-pill-icon" style="background:#fef9c3;">
            <i class="fa-solid fa-pen-to-square" style="color:#f59e0b;"></i>
        </div>
        <div>
            <div class="stat-pill-num" style="color:#f59e0b;">{{ $draftTakeoffs }}</div>
            <div class="stat-pill-lbl">Draft</div>
        </div>
    </div>
    <div class="stat-pill">
        <div class="stat-pill-icon" style="background:#dbeafe;">
            <i class="fa-solid fa-circle-check" style="color:#3b82f6;"></i>
        </div>
        <div>
            <div class="stat-pill-num" style="color:#3b82f6;">{{ $completedTakeoffs }}</div>
            <div class="stat-pill-lbl">Completed</div>
        </div>
    </div>
</div>

<ul class="nav nav-tabs premium-tabs" id="planTabs" role="tablist">
    <li class="nav-item" role="presentation">
        <button class="nav-link {{ !request()->has('schedules_page') ? 'active' : '' }}" id="erp-plans-tab" data-bs-toggle="tab" data-bs-target="#erp-plans" type="button" role="tab">
            <i class="fa-solid fa-calculator me-1"></i> Quantity Take-Offs
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link {{ request()->has('schedules_page') ? 'active' : '' }}" id="schedules-tab" data-bs-toggle="tab" data-bs-target="#schedules" type="button" role="tab">
            <i class="fa-solid fa-calendar-days me-1"></i> Project Schedules
        </button>
    </li>
</ul>

<div class="tab-content" id="planTabsContent">
    <!-- ERP PLANS TAB -->
    <div class="tab-pane fade {{ !request()->has('schedules_page') ? 'show active' : '' }}" id="erp-plans" role="tabpanel">
        {{-- ── Take-Off Cards ───────────────────────────────────── --}}
        @if($takeoffs->isEmpty())
        <div class="empty-hero">
            <div class="empty-icon">
                <i class="fa-solid fa-calculator"></i>
            </div>
            <h5 class="fw-bold mb-2">No Take-Offs Yet</h5>
            <p class="text-muted small mb-4">
                There are no Take-Offs available to display.
            </p>
        </div>
        @else
        <div class="plans-grid">
            @foreach($takeoffs as $takeoff)
            @php
                $sCls = 's-' . ($takeoff->status ?? 'draft');
            @endphp
            <div class="plan-card">
                {{-- Top --}}
                <div class="plan-card-top">
                    <div class="d-flex align-items-start justify-content-between gap-2 mb-2">
                        <a href="{{ route('takeoff.show', $takeoff) }}" class="plan-title">
                            {{ $takeoff->name }}
                        </a>
                        <span class="s-badge {{ $sCls }} flex-shrink-0">
                            {{ ucfirst(str_replace('_',' ',$takeoff->status)) }}
                        </span>
                    </div>

                    @if($takeoff->project)
                    <div class="plan-project-tag">
                        <i class="fa-solid fa-building"></i>
                        {{ $takeoff->project->name }}
                    </div>
                    @endif
                </div>

                {{-- Meta --}}
                <div class="plan-meta">
                    @if($takeoff->date)
                    <div class="meta-chip">
                        <i class="fa-solid fa-calendar-range"></i>
                        <span>{{ $takeoff->date->format('d M Y') }}</span>
                    </div>
                    @endif
                    <div class="meta-chip ms-auto">
                        <i class="fa-solid fa-user"></i>
                        <span>{{ $takeoff->creator->name ?? '—' }}</span>
                    </div>
                </div>

                {{-- Footer --}}
                <div class="plan-card-foot">
                    <div class="text-muted small">
                        <i class="fa-solid fa-clock me-1"></i>
                        {{ $takeoff->created_at->format('d M Y') }}
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <a href="{{ route('takeoff.show', $takeoff) }}" class="view-btn">
                            <i class="fa-solid fa-calculator fa-sm"></i> View Take-Off
                        </a>
                    </div>
                </div>
            </div>
            @endforeach
        </div>
        @endif
    </div>

    <!-- SCHEDULES TAB -->
    <div class="tab-pane fade {{ request()->has('schedules_page') ? 'show active' : '' }}" id="schedules" role="tabpanel">
        <div class="plans-grid">
            @forelse($schedules as $schedule)
            <div class="plan-card">
                <div class="plan-card-top">
                    <div class="d-flex align-items-start justify-content-between gap-2 mb-2">
                        <a href="{{ route('schedules.show', $schedule) }}" class="plan-title">
                            {{ $schedule->name }}
                        </a>
                        @php
                            $sStatus = strtolower($schedule->status);
                            $schedCls = match($sStatus) {
                                'active' => 's-active',
                                'draft' => 's-draft',
                                'delayed' => 's-cancelled',
                                'completed' => 's-completed',
                                default => 's-draft'
                            };
                        @endphp
                        <span class="s-badge {{ $schedCls }} flex-shrink-0">
                            {{ strtoupper($schedule->status) }}
                        </span>
                    </div>

                    @if($schedule->project)
                    <div class="plan-project-tag">
                        <i class="fa-solid fa-building"></i>
                        {{ $schedule->project->name }}
                    </div>
                    @endif

                    <div class="prog-wrap">
                        <div class="prog-label">
                            <span>Schedule Progress</span>
                            <span>{{ $schedule->progress }}%</span>
                        </div>
                        <div class="prog-bar">
                            <div class="prog-fill {{ $schedule->progress == 100 ? 'green' : 'blue' }}" style="width:{{ $schedule->progress }}%;"></div>
                        </div>
                    </div>
                </div>

                <div class="plan-meta">
                    <div class="meta-chip">
                        <i class="fa-solid fa-calendar-range"></i>
                        <span>{{ $schedule->start_date ? $schedule->start_date->format('d M Y') : '—' }} → {{ $schedule->end_date ? $schedule->end_date->format('d M Y') : '—' }}</span>
                    </div>
                </div>

                <div class="plan-card-foot">
                    <div class="text-muted small">
                        <i class="fa-solid fa-clock me-1"></i>
                        Updated {{ $schedule->updated_at->format('d M Y') }}
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        @php
                            $erpPlan = \App\Models\ErpPlanHeader::where('project_id', $schedule->project_id)->latest()->first();
                        @endphp
                        @if($erpPlan)
                        <a href="{{ route('erp-plans.show', $erpPlan) }}" class="view-btn">
                            <i class="fa-solid fa-diagram-project fa-sm me-1"></i> Show ERP
                        </a>
                        @else
                        <a href="{{ route('schedules.show', $schedule) }}" class="view-btn">
                            <i class="fa-solid fa-chart-gantt fa-sm me-1"></i> Show ERP
                        </a>
                        @endif
                    </div>
                </div>
            </div>
            @empty
            <div class="empty-hero" style="grid-column: 1 / -1;">
                <div class="empty-icon" style="background:#fef2f2; color:#ef4444;">
                    <i class="fa-regular fa-calendar-xmark"></i>
                </div>
                <h5 class="fw-bold mb-2">No Project Schedules Found</h5>
                <p class="text-muted small mb-4">
                    There are no schedules available to display.
                </p>
            </div>
            @endforelse
        </div>

    </div>
</div>
@endsection
