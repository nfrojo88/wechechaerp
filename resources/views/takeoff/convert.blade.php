@extends('layouts.app')

@section('title', 'Convert Take-Off to ERP Plan')

@push('styles')
<style>
@import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap');

* { font-family: 'Inter', sans-serif; }

/* ─── Hero Header ─── */
.cv-header {
    background: linear-gradient(135deg, #0f172a 0%, #1e3a8a 60%, #1d4ed8 100%);
    border-radius: 16px;
    padding: 28px 32px;
    margin-bottom: 28px;
    position: relative;
    overflow: hidden;
}
.cv-header::before {
    content: '';
    position: absolute;
    top: -60px; right: -60px;
    width: 220px; height: 220px;
    background: rgba(255,255,255,.05);
    border-radius: 50%;
}
.cv-header-title { font-size: 1.5rem; font-weight: 800; color: #fff; margin: 0 0 4px; }
.cv-header-sub   { color: rgba(255,255,255,.65); font-size: .875rem; margin: 0; }

/* ─── Meta Card ─── */
.meta-card {
    background: #fff;
    border-radius: 14px;
    box-shadow: 0 1px 4px rgba(0,0,0,.08);
    border: 1px solid #e2e8f0;
    padding: 24px;
    margin-bottom: 24px;
}
.meta-card h6 { font-weight: 700; color: #1e293b; margin-bottom: 20px; font-size: .875rem; text-transform: uppercase; letter-spacing: .5px; }

/* ─── Section Card ─── */
.section-card {
    background: #fff;
    border-radius: 14px;
    border: 1px solid #e2e8f0;
    box-shadow: 0 1px 4px rgba(0,0,0,.06);
    margin-bottom: 24px;
    overflow: hidden;
    transition: box-shadow .2s, transform .15s;
}
.section-card:hover { box-shadow: 0 6px 24px rgba(37,99,235,.12); }

.section-card-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 18px 24px;
    background: linear-gradient(90deg, #f8fafc 0%, #eff6ff 100%);
    border-bottom: 1px solid #e2e8f0;
    flex-wrap: wrap;
    gap: 12px;
}
.section-name { font-weight: 700; font-size: 1.05rem; color: #1e293b; }
.qty-pill {
    background: #2563eb;
    color: #fff;
    padding: 4px 14px;
    border-radius: 50px;
    font-weight: 700;
    font-size: .8rem;
    letter-spacing: .3px;
}

/* ─── Resource Table ─── */
.res-table { width: 100%; border-collapse: collapse; }
.res-table th {
    padding: 12px 14px;
    font-size: 11px;
    text-transform: uppercase;
    letter-spacing: .6px;
    color: #475569;
    font-weight: 700;
    background: #f8fafc;
    border-bottom: 2px solid #e2e8f0;
    white-space: nowrap;
}
.res-table td { padding: 12px 14px; border-bottom: 1px solid #f1f5f9; vertical-align: middle; }
.res-table tr:last-child td { border-bottom: none; }
.res-table tr:hover td { background: #fafbff; }

/* ─── Type Badges ─── */
.badge-material  { background: #dbeafe; color: #1e40af; }
.badge-manpower  { background: #dcfce7; color: #14532d; }
.badge-equipment { background: #fef9c3; color: #78350f; }
.type-badge { display: inline-flex; align-items: center; gap: 4px; padding: 4px 10px; border-radius: 50px; font-size: 11px; font-weight: 700; white-space: nowrap; }

/* ─── Add row zone ─── */
.add-row-zone { padding: 16px 24px; border-top: 1px dashed #cbd5e1; display: flex; gap: 10px; flex-wrap: wrap; background: #fafbfd; }
.add-btn { font-size: 12px; font-weight: 600; border-radius: 8px !important; }
.remove-btn { color: #dc2626; background: none; border: none; cursor: pointer; border-radius: 6px; padding: 4px 8px; }
.remove-btn:hover { background: #fee2e2; }

/* ─── Sticky footer ─── */
.sticky-bar {
    position: sticky;
    bottom: 0;
    background: rgba(255,255,255,.95);
    backdrop-filter: blur(12px);
    border-top: 2px solid #e2e8f0;
    padding: 14px 28px;
    display: flex;
    gap: 12px;
    align-items: center;
    z-index: 200;
    box-shadow: 0 -6px 20px rgba(0,0,0,.08);
}
.process-btn {
    background: linear-gradient(135deg, #1e3a8a, #2563eb);
    border: none;
    color: #fff;
    font-weight: 700;
    padding: 10px 28px;
    border-radius: 10px;
    font-size: .925rem;
    cursor: pointer;
    transition: opacity .2s, transform .1s;
}
.process-btn:hover { opacity: .9; transform: translateY(-1px); }

/* ─── Load Template button ─── */
.load-tmpl-btn {
    background: linear-gradient(135deg,#7c3aed,#4f46e5);
    color:#fff; border:none; border-radius:8px;
    padding:6px 16px; font-size:12px; font-weight:600;
    cursor:pointer; display:flex; align-items:center; gap:6px;
    transition: opacity .15s;
}
.load-tmpl-btn:hover { opacity:.87; }

/* ─── Collapsible picker panels ─── */
.pick-panel {
    display: none;
    border-radius: 10px;
    padding: 12px 16px;
    gap: 10px;
    align-items: center;
    flex-wrap: wrap;
    margin-bottom: 4px;
}
.pick-panel.active  { display: flex; }
.pick-panel.tmpl-p  { background:#f8faff; border:1px solid #c7d2fe; }
.pick-panel.mat-p   { background:#eff6ff; border:1px solid #93c5fd; }
.pick-panel.mp-p    { background:#f0fdf4; border:1px solid #86efac; }
.pick-panel.eq-p    { background:#fefce8; border:1px solid #fde047; }
.pick-panel select  { flex:1; min-width:220px; }
.unit-tag {
    font-size:11px; color:#475569; background:#e2e8f0;
    padding:3px 10px; border-radius:20px; font-weight:600; white-space:nowrap;
}

.form-control, .form-select { border-radius: 8px !important; font-size: .875rem; }
</style>
@endpush

@section('content')

{{-- ── Hero Header ── --}}
<div class="cv-header">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
        <div>
            <p class="cv-header-title">
                <i class="fa-solid fa-diagram-project me-2"></i>Convert Take-Off to ERP Plan
            </p>
            <p class="cv-header-sub">
                Sheet: <strong style="color:#93c5fd;">{{ $takeoff->title }}</strong>
                &nbsp;·&nbsp; Project: <strong style="color:#93c5fd;">{{ $takeoff->project->name }}</strong>
            </p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('takeoff.show', $takeoff) }}" class="btn btn-light btn-sm fw-semibold">
                <i class="fa-solid fa-xmark me-1"></i> Cancel
            </a>
        </div>
    </div>
</div>

{{-- ── Plan Metadata ── --}}
<div class="meta-card">
    <h6><i class="fa-solid fa-sliders me-2 text-primary"></i>Plan Settings</h6>
    <div class="row g-3">
        <div class="col-md-4">
            <label class="form-label fw-semibold small">Plan Name <span class="text-danger">*</span></label>
            <input type="text" id="meta_plan_name" class="form-control"
                   value="Execution Plan — {{ $takeoff->title }}" required>
        </div>
        <div class="col-md-2">
            <label class="form-label fw-semibold small">Start Date <span class="text-danger">*</span></label>
            <input type="date" id="meta_start_date" class="form-control"
                   value="{{ $takeoff->project->start_date?->format('Y-m-d') ?? now()->format('Y-m-d') }}" required>
        </div>
        <div class="col-md-2">
            <label class="form-label fw-semibold small">End Date <span class="text-danger">*</span></label>
            <input type="date" id="meta_end_date" class="form-control"
                   value="{{ $takeoff->project->end_date?->format('Y-m-d') ?? now()->addDays(90)->format('Y-m-d') }}" required>
        </div>
        <div class="col-md-4">
            <label class="form-label fw-semibold small">Notes</label>
            <input type="text" id="meta_notes" class="form-control"
                   placeholder="e.g. Phase 1 execution plan from takeoff"
                   value="From Takeoff: {{ $takeoff->title }}">
        </div>
    </div>
</div>

{{-- ── Budget Preview ── --}}
<div class="d-flex gap-3 mb-4 flex-wrap">
    <div class="d-flex align-items-center gap-2 px-4 py-2 rounded-3 border" style="background:#eff6ff;">
        <i class="fa-solid fa-flask text-primary"></i>
        <span class="small fw-semibold text-muted">Materials:</span>
        <span class="fw-bold text-primary" id="budget-material">0.00</span>
    </div>
    <div class="d-flex align-items-center gap-2 px-4 py-2 rounded-3 border" style="background:#f0fdf4;">
        <i class="fa-solid fa-users text-success"></i>
        <span class="small fw-semibold text-muted">Manpower:</span>
        <span class="fw-bold text-success" id="budget-manpower">0.00</span>
    </div>
    <div class="d-flex align-items-center gap-2 px-4 py-2 rounded-3 border" style="background:#fefce8;">
        <i class="fa-solid fa-gears text-warning"></i>
        <span class="small fw-semibold text-muted">Equipment:</span>
        <span class="fw-bold text-warning" id="budget-equipment">0.00</span>
    </div>
    <div class="d-flex align-items-center gap-2 px-4 py-2 rounded-3 border" style="background:#0f172a;color:#fff;">
        <i class="fa-solid fa-coins"></i>
        <span class="small fw-semibold" style="opacity:.7;">Total Budget:</span>
        <span class="fw-bold" id="budget-total">0.00</span>
        <span class="small" style="opacity:.5;">ETB</span>
    </div>
</div>

{{-- ── Section Cards ── --}}
@foreach($takeoff->sections as $sIdx => $section)
@php
    $taskName  = $section->task ? (($section->task->wbs_code ? $section->task->wbs_code . ' - ' : '') . $section->task->name) : $section->name;
    $taskStart = $section->task_start_date ?? null;
    $taskEnd   = $section->task_end_date ?? null;
@endphp
<div class="section-card" data-section-idx="{{ $sIdx }}" data-section-id="{{ $section->id }}" data-section-name="{{ $taskName }}">
    <div class="section-card-head">
        <div class="d-flex align-items-center gap-2 flex-wrap">
            <i class="fa-solid fa-layer-group text-primary"></i>
            <span class="section-name">{{ $taskName }}</span>
            @if($section->task)
                <span class="badge bg-info bg-opacity-10 text-info border border-info border-opacity-25 ms-1">
                    <i class="fa-solid fa-calendar-range me-1"></i>
                    @if($taskStart && $taskEnd)
                        {{ \Carbon\Carbon::parse($taskStart)->format('d M Y') }} → {{ \Carbon\Carbon::parse($taskEnd)->format('d M Y') }}
                    @else
                        No dates linked
                    @endif
                </span>
            @else
                <span class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-25 ms-1">
                    <i class="fa-solid fa-exclamation-triangle me-1"></i>No linked schedule task
                </span>
            @endif
        </div>

        {{-- Interactive Activity Controls --}}
        <div class="d-flex align-items-center gap-3 flex-wrap">
            {{-- Worked Quantity --}}
            <div class="d-flex align-items-center gap-1">
                <label class="text-muted small fw-semibold mb-0" for="sec-qty-{{ $sIdx }}">Worked Qty:</label>
                <input type="number" step="0.001" min="0" class="form-control form-control-sm sec-qty fw-bold text-primary"
                       id="sec-qty-{{ $sIdx }}" style="width:110px;"
                       value="{{ $section->total_quantity }}"
                       oninput="updateSectionCalculations('{{ $sIdx }}')">
                <span class="badge bg-secondary bg-opacity-10 text-secondary" id="sec-unit-tag-{{ $sIdx }}">{{ $section->primary_unit ?: 'unit' }}</span>
            </div>

            {{-- Schedule Days --}}
            <div class="d-flex align-items-center gap-1">
                <label class="text-muted small fw-semibold mb-0" for="sec-dur-{{ $sIdx }}">Schedule Days <span class="text-danger">*</span>:</label>
                <input type="number" step="1" min="1" class="form-control form-control-sm sec-dur fw-bold text-dark"
                       id="sec-dur-{{ $sIdx }}" style="width:85px;"
                       value="{{ $section->schedule_duration_days ?: 1 }}"
                       oninput="updateSectionCalculations('{{ $sIdx }}')">
                <span class="text-muted small fw-semibold">Days</span>
            </div>

            {{-- Required Daily Output Badge --}}
            <div class="d-flex align-items-center gap-1">
                <span class="text-muted small fw-semibold">Req. Daily Output:</span>
                <span class="badge bg-primary text-white font-monospace px-2 py-1" id="sec-req-output-{{ $sIdx }}" style="font-size:12px;">
                    0.00 {{ $section->primary_unit }}/day
                </span>
            </div>
        </div>

        {{-- Hidden task start/end date fields --}}
        <input type="hidden" class="sec-task-start" id="sec-task-start-{{ $sIdx }}"
               value="{{ $taskStart ?? '' }}">
        <input type="hidden" class="sec-task-end"   id="sec-task-end-{{ $sIdx }}"
               value="{{ $taskEnd ?? '' }}">
    </div>

    {{-- Validation Alert for Schedule Days --}}
    <div id="sec-alert-{{ $sIdx }}" class="alert alert-danger mb-0 py-2 px-3 rounded-0 border-0 border-bottom d-none">
        <i class="fa-solid fa-triangle-exclamation me-1"></i>
        <strong>Validation Warning:</strong> Schedule Days must be greater than 0 before calculating to avoid division by zero.
    </div>

    <div class="table-responsive">
        <table class="res-table" id="rtable-{{ $sIdx }}">
            <thead>
                <tr>
                    <th style="width:34px;"><input type="checkbox" class="form-check-input sec-all" data-sec="{{ $sIdx }}" checked></th>
                    <th style="width:110px;">Type</th>
                    <th>Resource Name</th>
                    <th>Standard / Source</th>
                    <th style="width:120px;">Std Rate/Qty</th>
                    <th style="width:70px;">UM</th>
                    <th style="width:110px;">Output Ratio</th>
                    <th style="width:190px; background:#eff6ff; color:#1e40af;">Calculated Requirement</th>
                    <th style="width:120px;">Rate (ETB)</th>
                    <th style="width:130px;">Total Cost</th>
                    <th style="width:36px;"></th>
                </tr>
            </thead>
            <tbody id="tbody-{{ $sIdx }}"
                   data-section-total="{{ $section->total_quantity }}"
                   data-section-unit="{{ $section->primary_unit }}"
                   data-section-name="{{ $section->name }}">
            </tbody>
        </table>
    </div>

    <div class="add-row-zone" style="flex-direction:column;gap:10px;">

        {{-- Template Panel --}}
        <div id="tmpl-wrap-{{ $sIdx }}" class="pick-panel tmpl-p">
            <i class="fa-solid fa-magic-wand-sparkles text-primary"></i>
            <span class="small fw-semibold text-muted">
                Select Standard Work for <strong>{{ $section->primary_unit ?: 'any unit' }}</strong>:
            </span>
            <select class="form-select form-select-sm" id="tmpl-sel-{{ $sIdx }}"
                    onchange="expandTemplate('{{ $sIdx }}', this.value)">
                <option value="">— Pick a template —</option>
            </select>
            <button type="button" class="btn btn-sm btn-outline-secondary"
                    onclick="closePanel('tmpl-wrap-{{ $sIdx }}')">Cancel</button>
        </div>

        {{-- Material Pick Panel --}}
        <div id="mat-pick-{{ $sIdx }}" class="pick-panel mat-p">
            <i class="fa-solid fa-flask text-primary"></i>
            <span class="small fw-semibold text-muted">Select registered material:</span>
            <select class="form-select form-select-sm" id="mat-sel-{{ $sIdx }}"
                    onchange="onPickChange('{{ $sIdx }}','material')">
                <option value="">— Pick material —</option>
                @foreach($registeredProducts as $prod)
                <option value="{{ $prod['id'] }}"
                        data-name="{{ $prod['name'] }}"
                        data-unit="{{ $prod['unit'] }}"
                        data-rate="{{ $prod['rate'] }}">
                    {{ $prod['name'] }} ({{ $prod['unit'] }}){{ $prod['rate'] > 0 ? '  —  ETB '.number_format($prod['rate'],2) : '' }}
                </option>
                @endforeach
            </select>
            <span class="unit-tag" id="mat-utag-{{ $sIdx }}">unit: —</span>
            <button type="button" class="btn btn-sm btn-primary" id="mat-addbtn-{{ $sIdx }}"
                    style="display:none" onclick="addPickedRow('{{ $sIdx }}','material')">
                <i class="fa-solid fa-plus me-1"></i>Add Row
            </button>
            <button type="button" class="btn btn-sm btn-outline-secondary"
                    onclick="closePanel('mat-pick-{{ $sIdx }}')">Cancel</button>
        </div>

        {{-- Manpower Pick Panel --}}
        <div id="mp-pick-{{ $sIdx }}" class="pick-panel mp-p">
            <i class="fa-solid fa-users text-success"></i>
            <span class="small fw-semibold text-muted">Select registered role:</span>
            <select class="form-select form-select-sm" id="mp-sel-{{ $sIdx }}"
                    onchange="onPickChange('{{ $sIdx }}','manpower')">
                <option value="">— Pick role —</option>
                @foreach($registeredRoles as $role)
                <option value="{{ $role['id'] }}"
                        data-name="{{ $role['name'] }}"
                        data-unit="{{ $role['unit'] }}"
                        data-rate="{{ $role['rate'] }}">
                    {{ $role['name'] }} ({{ $role['unit'] }}){{ $role['rate'] > 0 ? '  —  ETB '.number_format($role['rate'],2).'/day' : '' }}
                </option>
                @endforeach
            </select>
            <span class="unit-tag" id="mp-utag-{{ $sIdx }}">unit: man-day</span>
            <button type="button" class="btn btn-sm btn-success" id="mp-addbtn-{{ $sIdx }}"
                    style="display:none" onclick="addPickedRow('{{ $sIdx }}','manpower')">
                <i class="fa-solid fa-plus me-1"></i>Add Row
            </button>
            <button type="button" class="btn btn-sm btn-outline-secondary"
                    onclick="closePanel('mp-pick-{{ $sIdx }}')">Cancel</button>
        </div>

        {{-- Equipment Pick Panel --}}
        <div id="eq-pick-{{ $sIdx }}" class="pick-panel eq-p">
            <i class="fa-solid fa-gears text-warning"></i>
            <span class="small fw-semibold text-muted">Select registered equipment:</span>
            <select class="form-select form-select-sm" id="eq-sel-{{ $sIdx }}"
                    onchange="onPickChange('{{ $sIdx }}','equipment')">
                <option value="">— Pick equipment —</option>
                @foreach($registeredEquipment as $eq)
                <option value="{{ $eq['id'] }}"
                        data-name="{{ $eq['name'] }}"
                        data-unit="{{ $eq['unit'] }}"
                        data-rate="{{ $eq['rate'] }}">
                    {{ $eq['name'] }} ({{ $eq['unit'] }}){{ $eq['rate'] > 0 ? '  —  ETB '.number_format($eq['rate'],2).'/hr' : '' }}
                </option>
                @endforeach
            </select>
            <span class="unit-tag" id="eq-utag-{{ $sIdx }}">unit: hour</span>
            <button type="button" class="btn btn-sm btn-warning" id="eq-addbtn-{{ $sIdx }}"
                    style="display:none" onclick="addPickedRow('{{ $sIdx }}','equipment')">
                <i class="fa-solid fa-plus me-1"></i>Add Row
            </button>
            <button type="button" class="btn btn-sm btn-outline-secondary"
                    onclick="closePanel('eq-pick-{{ $sIdx }}')">Cancel</button>
        </div>

        {{-- Button Row --}}
        <div class="d-flex gap-2 flex-wrap align-items-center">
            <button type="button" class="load-tmpl-btn"
                    onclick="openTmplPanel('{{ $sIdx }}', '{{ $section->primary_unit }}')">
                <i class="fa-solid fa-magic-wand-sparkles"></i> Load from Standard Work Template
            </button>
            <span class="text-muted" style="font-size:11px;">or add manually:</span>
            <button type="button" class="btn btn-sm btn-outline-primary add-btn"
                    onclick="openPickPanel('{{ $sIdx }}','material')">
                <i class="fa-solid fa-flask me-1"></i>+ Material
            </button>
            <button type="button" class="btn btn-sm btn-outline-success add-btn"
                    onclick="openPickPanel('{{ $sIdx }}','manpower')">
                <i class="fa-solid fa-users me-1"></i>+ Manpower
            </button>
            <button type="button" class="btn btn-sm btn-outline-warning add-btn"
                    onclick="openPickPanel('{{ $sIdx }}','equipment')">
                <i class="fa-solid fa-gears me-1"></i>+ Equipment
            </button>
            <button type="button" class="btn btn-sm btn-outline-secondary add-btn ms-auto"
                    onclick="addManualFreeRow('{{ $sIdx }}')">
                <i class="fa-solid fa-plus me-1"></i>+ Custom Manual Row
            </button>
        </div>
    </div>
</div>
@endforeach

{{-- ── Sticky Bar ── --}}
<div class="sticky-bar">
    <button type="button" class="process-btn" onclick="submitConversion()">
        <i class="fa-solid fa-diagram-project me-2"></i>Create ERP Plan
    </button>
    <a href="{{ route('takeoff.show', $takeoff) }}" class="btn btn-outline-secondary fw-semibold">
        <i class="fa-solid fa-xmark me-1"></i>Cancel
    </a>
    <div class="ms-auto d-flex gap-3 align-items-center text-muted small">
        <span><i class="fa-solid fa-list-check me-1"></i><span id="rc-label">0 resources</span></span>
    </div>
</div>

{{-- Hidden form --}}
<form id="mainForm" action="{{ route('takeoff.process-conversion', $takeoff) }}" method="POST" style="display:none;">
    @csrf
    <input type="hidden" name="plan_name"       id="f_plan_name">
    <input type="hidden" name="plan_start_date" id="f_start_date">
    <input type="hidden" name="plan_end_date"   id="f_end_date">
    <input type="hidden" name="notes"           id="f_notes">
    <div id="f_body"></div>
</form>

@endsection

@push('scripts')
<script type="application/json" id="data-standard-works">@json($standardWorksJson)</script>
<script type="application/json" id="data-registered-products">@json($registeredProducts)</script>
<script type="application/json" id="data-registered-equipment">@json($registeredEquipment)</script>
<script type="application/json" id="data-registered-roles">@json($registeredRoles)</script>
<script>
// ── Server data ──
const SW      = JSON.parse(document.getElementById('data-standard-works').textContent || '[]');
const MATLIST = JSON.parse(document.getElementById('data-registered-products').textContent || '[]');
const EQLIST  = JSON.parse(document.getElementById('data-registered-equipment').textContent || '[]');
const MPLIST  = JSON.parse(document.getElementById('data-registered-roles').textContent || '[]');

// ── Type config ──
const TYPE_CFG = {
    material:  { cls:'badge-material',  label:'Material',  icon:'fa-flask', swKey:'materials' },
    manpower:  { cls:'badge-manpower',  label:'Manpower',  icon:'fa-users', swKey:'manpower'  },
    equipment: { cls:'badge-equipment', label:'Equipment', icon:'fa-gears', swKey:'equipment' },
};

// ── Panel IDs map ──
const PANEL = {
    material:  s => `mat-pick-${s}`,
    manpower:  s => `mp-pick-${s}`,
    equipment: s => `eq-pick-${s}`,
};
const SEL_ID = {
    material:  s => `mat-sel-${s}`,
    manpower:  s => `mp-sel-${s}`,
    equipment: s => `eq-sel-${s}`,
};
const UTAG = {
    material:  s => `mat-utag-${s}`,
    manpower:  s => `mp-utag-${s}`,
    equipment: s => `eq-utag-${s}`,
};
const ADDBTN = {
    material:  s => `mat-addbtn-${s}`,
    manpower:  s => `mp-addbtn-${s}`,
    equipment: s => `eq-addbtn-${s}`,
};

const picks = {};

function closePanel(id) {
    document.getElementById(id)?.classList.remove('active');
}

function closeAllPanels(sIdx) {
    ['material','manpower','equipment'].forEach(t =>
        document.getElementById(PANEL[t](sIdx))?.classList.remove('active')
    );
    document.getElementById(`tmpl-wrap-${sIdx}`)?.classList.remove('active');
}

// ── TEMPLATE PANEL ──
function openTmplPanel(sIdx, sectionUnit) {
    closeAllPanels(sIdx);
    const sel = document.getElementById(`tmpl-sel-${sIdx}`);
    sel.innerHTML = '<option value="">— Pick a template —</option>';
    let list = SW.filter(sw => !sectionUnit || !sw.unit || sw.unit === sectionUnit);
    if (!list.length) list = SW;
    list.forEach(sw => {
        const avg = parseFloat(sw.avg_output || sw.default_productivity) || 0;
        sel.appendChild(new Option(`${sw.name} (${sw.unit || 'any'}) — Avg Output: ${avg} ${sw.unit}/day`, sw.id));
    });
    document.getElementById(`tmpl-wrap-${sIdx}`).classList.add('active');
}

function expandTemplate(sIdx, swId) {
    if (!swId) return;
    const sw = SW.find(s => String(s.id) === String(swId));
    if (!sw) return;

    const stdAvgOutput          = parseFloat(sw.avg_output || sw.default_productivity) || 0;
    const stdEquipmentAvgOutput = parseFloat(sw.default_equipment_productivity || stdAvgOutput) || 0;

    // Materials
    (sw.materials || []).forEach(item => {
        addPrefilledRow(sIdx, 'material', sw.name, item, null, stdAvgOutput);
    });

    // Manpower
    (sw.manpower || []).forEach(item => {
        addPrefilledRow(sIdx, 'manpower', sw.name, item, null, stdAvgOutput);
    });

    // Equipment
    (sw.equipment || []).forEach(item => {
        addPrefilledRow(sIdx, 'equipment', sw.name, item, null, stdEquipmentAvgOutput);
    });

    closeAllPanels(sIdx);
    updateSectionCalculations(sIdx);
    updateCount();
}

// ── MANUAL PICK PANELS ──
function openPickPanel(sIdx, type) {
    closeAllPanels(sIdx);
    const selEl   = document.getElementById(SEL_ID[type](sIdx));
    const addBtn  = document.getElementById(ADDBTN[type](sIdx));
    const unitTag = document.getElementById(UTAG[type](sIdx));
    selEl.value          = '';
    addBtn.style.display = 'none';
    unitTag.textContent  = 'unit: —';
    picks[`${sIdx}_${type}`] = null;
    document.getElementById(PANEL[type](sIdx)).classList.add('active');
}

function onPickChange(sIdx, type) {
    const selEl   = document.getElementById(SEL_ID[type](sIdx));
    const addBtn  = document.getElementById(ADDBTN[type](sIdx));
    const unitTag = document.getElementById(UTAG[type](sIdx));
    if (!selEl.value) {
        addBtn.style.display = 'none';
        unitTag.textContent  = 'unit: —';
        picks[`${sIdx}_${type}`] = null;
        return;
    }
    const opt  = selEl.options[selEl.selectedIndex];
    const name = opt.dataset.name;
    const unit = opt.dataset.unit;
    const rate = parseFloat(opt.dataset.rate) || 0;
    picks[`${sIdx}_${type}`] = { name, unit, rate };
    unitTag.textContent  = `unit: ${unit}`;
    addBtn.style.display = 'inline-flex';
}

function addPickedRow(sIdx, type) {
    const pick = picks[`${sIdx}_${type}`];
    if (!pick) return;
    addPrefilledRow(sIdx, type, 'Registered Pick', { name: pick.name, quantity: 1, unit: pick.unit }, pick.rate, 0);
    closePanel(PANEL[type](sIdx));
    picks[`${sIdx}_${type}`] = null;
}

// ── FREE-TEXT MANUAL ROW ──
function addManualFreeRow(sIdx) {
    closeAllPanels(sIdx);
    const tbody = document.getElementById(`tbody-${sIdx}`);
    const unit  = tbody.dataset.sectionUnit || '';
    const rowId = `row-${sIdx}-${Date.now()}`;

    const tr = document.createElement('tr');
    tr.id = rowId;
    tr.innerHTML = `
        <td class="text-center">
            <input type="checkbox" class="form-check-input row-inc" checked onchange="updateBudget();updateCount()">
        </td>
        <td>
            <select class="form-select form-select-sm r-type-select" onchange="onTypeSelectChange('${sIdx}','${rowId}',this.value)">
                <option value="material">Material</option>
                <option value="manpower">Manpower</option>
                <option value="equipment">Equipment</option>
            </select>
            <input type="hidden" class="r-type" value="material">
        </td>
        <td>
            <input type="text" class="form-control form-control-sm r-name-h" placeholder="Resource name...">
        </td>
        <td><span class="text-muted small">Custom Manual</span></td>
        <td>
            <input type="number" step="0.0001" min="0" class="form-control form-control-sm r-std-qty"
                   value="1" oninput="calcRow('${sIdx}','${rowId}')">
            <input type="hidden" class="r-std-avg" value="0">
        </td>
        <td>
            <input type="text" class="form-control form-control-sm r-unit-input" value="${unit||'unit'}">
        </td>
        <td>
            <div id="ratio-${rowId}"><span class="badge bg-secondary bg-opacity-10 text-secondary">1.00x</span></div>
            <input type="hidden" class="r-req-output" value="0">
            <input type="hidden" class="r-output-ratio" value="1">
        </td>
        <td style="background:#eff6ff;">
            <div id="pdr-${rowId}" class="fw-bold text-primary" style="font-size:13px;">—</div>
            <input type="hidden" class="r-per-day" value="0">
            <input type="hidden" class="r-qty"     value="0">
        </td>
        <td>
            <input type="number" step="0.01" min="0" class="form-control form-control-sm r-rate"
                   value="0" placeholder="0.00" oninput="calcRow('${sIdx}','${rowId}')">
        </td>
        <td>
            <span class="fw-semibold text-dark" id="rc-${rowId}">0.00</span>
            <input type="hidden" class="r-cost" value="0">
        </td>
        <td>
            <button type="button" class="remove-btn" onclick="removeRow('${rowId}')">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </td>
    `;
    tbody.appendChild(tr);
    calcRow(sIdx, rowId);
    updateCount();
}

function onTypeSelectChange(sIdx, rowId, newType) {
    const tr = document.getElementById(rowId);
    if (!tr) return;
    tr.querySelector('.r-type').value = newType;
    calcRow(sIdx, rowId);
}

// ── PRE-FILLED ROW ──
function addPrefilledRow(sIdx, type, source, item, overrideRate, stdAvgOutput) {
    const tbody  = document.getElementById(`tbody-${sIdx}`);
    const secUnt = tbody.dataset.sectionUnit || '';
    const cfg    = TYPE_CFG[type] || TYPE_CFG.material;
    const rowId  = `row-${sIdx}-${Date.now()}-${Math.random().toString(36).slice(2,6)}`;

    const stdQty = parseFloat(item.quantity) || 1;
    const rate   = (overrideRate !== null && overrideRate !== undefined) ? overrideRate : 0;
    const avgOut = stdAvgOutput || 0;

    const tr = document.createElement('tr');
    tr.id = rowId;
    tr.innerHTML = `
        <td class="text-center">
            <input type="checkbox" class="form-check-input row-inc" checked onchange="updateBudget();updateCount()">
        </td>
        <td>
            <span class="type-badge ${cfg.cls}">
                <i class="fa-solid ${cfg.icon}"></i> ${cfg.label}
            </span>
            <input type="hidden" class="r-type" value="${type}">
        </td>
        <td>
            <input type="text" class="form-control form-control-sm r-name-h" value="${esc(item.name)}">
        </td>
        <td>
            <span class="text-muted small fw-semibold">${esc(source)}</span>
        </td>
        <td>
            <input type="number" step="0.0001" min="0" class="form-control form-control-sm r-std-qty"
                   value="${stdQty}" oninput="calcRow('${sIdx}','${rowId}')">
            <input type="hidden" class="r-std-avg" value="${avgOut}">
        </td>
        <td>
            <input type="text" class="form-control form-control-sm r-unit-input" value="${esc(item.unit || secUnt)}">
        </td>
        <td>
            <div id="ratio-${rowId}"><span class="badge bg-secondary bg-opacity-10 text-secondary">1.00x</span></div>
            <input type="hidden" class="r-req-output" value="0">
            <input type="hidden" class="r-output-ratio" value="1">
        </td>
        <td style="background:#eff6ff;">
            <div id="pdr-${rowId}" class="fw-bold text-primary" style="font-size:13px;">—</div>
            <input type="hidden" class="r-per-day" value="0">
            <input type="hidden" class="r-qty"     value="0">
        </td>
        <td>
            <input type="number" step="0.01" min="0" class="form-control form-control-sm r-rate"
                   value="${rate}" placeholder="0.00" oninput="calcRow('${sIdx}','${rowId}')">
        </td>
        <td>
            <span class="fw-semibold text-dark" id="rc-${rowId}">0.00</span>
            <input type="hidden" class="r-cost" value="0">
        </td>
        <td>
            <button type="button" class="remove-btn" onclick="removeRow('${rowId}')">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </td>
    `;
    tbody.appendChild(tr);
    calcRow(sIdx, rowId);
    updateBudget();
}

// ── CALCULATIONS LOGIC ──
function updateSectionCalculations(sIdx) {
    const qtyInput = document.getElementById(`sec-qty-${sIdx}`);
    const durInput = document.getElementById(`sec-dur-${sIdx}`);
    const alertEl  = document.getElementById(`sec-alert-${sIdx}`);
    const reqOutEl = document.getElementById(`sec-req-output-${sIdx}`);
    const tbody    = document.getElementById(`tbody-${sIdx}`);
    const unit     = tbody?.dataset.sectionUnit || 'unit';

    const W = parseFloat(qtyInput?.value) || 0;
    let D   = parseFloat(durInput?.value);

    // Validation: Schedule Days must be > 0 before calculating
    if (isNaN(D) || D <= 0) {
        if (alertEl) alertEl.classList.remove('d-none');
        if (reqOutEl) reqOutEl.textContent = 'Invalid (Days <= 0)';
        return;
    } else {
        if (alertEl) alertEl.classList.add('d-none');
    }

    const reqDailyOutput = W / D;
    if (reqOutEl) {
        reqOutEl.textContent = `${reqDailyOutput.toFixed(2)} ${unit}/day`;
    }

    // Recalculate all rows in this section
    document.querySelectorAll(`#tbody-${sIdx} tr`).forEach(row => {
        calcRow(sIdx, row.id);
    });
}

function calcRow(sIdx, rowId) {
    const row = document.getElementById(rowId);
    if (!row) return;

    const qtyInput = document.getElementById(`sec-qty-${sIdx}`);
    const durInput = document.getElementById(`sec-dur-${sIdx}`);
    const W = parseFloat(qtyInput?.value) || 0;
    let D   = parseFloat(durInput?.value);

    // Validation check
    if (isNaN(D) || D <= 0) D = 1;

    // Required Daily Output = Worked Quantity / Schedule Days
    const reqDailyOutput = W / D;

    const type   = row.querySelector('.r-type')?.value || 'material';
    const stdQty = parseFloat(row.querySelector('.r-std-qty')?.value) || 0;
    const stdAvg = parseFloat(row.querySelector('.r-std-avg')?.value) || 0;
    const rate   = parseFloat(row.querySelector('.r-rate')?.value)    || 0;
    const unit   = row.querySelector('.r-unit-input')?.value || '';

    // Output Ratio = Required Daily Output / Standard Average Output
    let outputRatio = 1.0;
    if (stdAvg > 0) {
        outputRatio = reqDailyOutput / stdAvg;
    }

    let perDayQty = 0;
    let totalQty  = 0;

    if (type === 'manpower' || type === 'equipment') {
        // Manpower / Equipment per day (scaled by ratio) = Standard Qty * Output Ratio
        perDayQty = stdQty * outputRatio;
        // Total man-days or equipment-days = perDayQty * Schedule Days
        totalQty  = perDayQty * D;
    } else {
        // Material Total = Worked Quantity * Standard Material Rate
        totalQty  = W * stdQty;
        // Material per day = Material Total / Schedule Days
        perDayQty = D > 0 ? (totalQty / D) : totalQty;
    }

    const totalCost = parseFloat((totalQty * rate).toFixed(2));

    // Save hidden input values for backend submission
    if (row.querySelector('.r-req-output')) row.querySelector('.r-req-output').value = reqDailyOutput.toFixed(4);
    if (row.querySelector('.r-output-ratio')) row.querySelector('.r-output-ratio').value = outputRatio.toFixed(4);
    if (row.querySelector('.r-per-day')) row.querySelector('.r-per-day').value = perDayQty.toFixed(4);
    if (row.querySelector('.r-qty')) row.querySelector('.r-qty').value = totalQty.toFixed(4);
    if (row.querySelector('.r-cost')) row.querySelector('.r-cost').value = totalCost;

    // Display Output Ratio
    const ratioBadge = row.querySelector(`#ratio-${rowId}`);
    if (ratioBadge) {
        if (stdAvg > 0) {
            ratioBadge.innerHTML = `
                <span class="badge bg-primary bg-opacity-10 text-primary fw-bold" style="font-size:11px;">
                    ${outputRatio.toFixed(2)}x
                </span>
                <div class="text-muted" style="font-size:10px; margin-top:2px;">Std Output: ${stdAvg}</div>`;
        } else {
            ratioBadge.innerHTML = `<span class="badge bg-secondary bg-opacity-10 text-secondary">1.00x</span>`;
        }
    }

    // Display Requirement Breakdown (Resource Name | Type | UM | Quantity | Per Day or Total)
    const pdrEl = row.querySelector(`#pdr-${rowId}`);
    if (pdrEl) {
        if (type === 'manpower') {
            pdrEl.innerHTML = `
                <div style="line-height:1.3;">
                    <span class="badge bg-success bg-opacity-15 text-success fw-bold" style="font-size:12px;">
                        <i class="fa-solid fa-user me-1"></i>${perDayQty.toFixed(2)} / day
                    </span>
                    <div class="text-muted" style="font-size:11px; margin-top:3px;">
                        Total: <strong>${totalQty.toFixed(2)}</strong> man-days (${D}d)
                    </div>
                </div>`;
        } else if (type === 'equipment') {
            pdrEl.innerHTML = `
                <div style="line-height:1.3;">
                    <span class="badge bg-warning bg-opacity-25 text-dark fw-bold" style="font-size:12px;">
                        <i class="fa-solid fa-truck-monster me-1"></i>${perDayQty.toFixed(2)} / day
                    </span>
                    <div class="text-muted" style="font-size:11px; margin-top:3px;">
                        Total: <strong>${totalQty.toFixed(2)}</strong> equip-days (${D}d)
                    </div>
                </div>`;
        } else {
            pdrEl.innerHTML = `
                <div style="line-height:1.3;">
                    <span class="badge bg-primary bg-opacity-15 text-primary fw-bold" style="font-size:12px;">
                        <i class="fa-solid fa-boxes-stacked me-1"></i>${totalQty.toFixed(2)} ${unit} total
                    </span>
                    <div class="text-muted" style="font-size:11px; margin-top:3px;">
                        Per Day: ${perDayQty.toFixed(2)} ${unit}/day (${D}d)
                    </div>
                </div>`;
        }
    }

    const costEl = row.querySelector(`#rc-${rowId}`);
    if (costEl) {
        costEl.textContent = totalCost.toLocaleString('en-US', { minimumFractionDigits: 2 });
    }

    updateBudget();
}

function updateBudget() {
    const totals = { material:0, manpower:0, equipment:0 };
    document.querySelectorAll('[id^="tbody-"] tr').forEach(row => {
        const cb = row.querySelector('input[type=checkbox]');
        if (!cb?.checked) return;
        const type = row.querySelector('.r-type')?.value;
        const cost = parseFloat(row.querySelector('.r-cost')?.value) || 0;
        if (type && totals[type] !== undefined) totals[type] += cost;
    });
    document.getElementById('budget-material').textContent  = totals.material.toLocaleString('en-US',{minimumFractionDigits:2});
    document.getElementById('budget-manpower').textContent  = totals.manpower.toLocaleString('en-US',{minimumFractionDigits:2});
    document.getElementById('budget-equipment').textContent = totals.equipment.toLocaleString('en-US',{minimumFractionDigits:2});
    document.getElementById('budget-total').textContent = (totals.material+totals.manpower+totals.equipment).toLocaleString('en-US',{minimumFractionDigits:2});
}

function updateCount() {
    const n = document.querySelectorAll('[id^="tbody-"] tr').length;
    document.getElementById('rc-label').textContent = `${n} resource${n!==1?'s':''}`;
}

function removeRow(id) {
    document.getElementById(id)?.remove();
    updateBudget(); updateCount();
}

// ── SECTION SELECT-ALL ──
document.addEventListener('change', e => {
    if (!e.target.classList.contains('sec-all')) return;
    const s = e.target.dataset.sec;
    document.querySelectorAll(`#tbody-${s} input[type=checkbox]`).forEach(cb => cb.checked = e.target.checked);
    updateBudget(); updateCount();
});

// Init calculations on DOM load
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.sec-qty').forEach(el => {
        const sIdx = el.id.replace('sec-qty-', '');
        updateSectionCalculations(sIdx);
    });
});

// ── SUBMIT ──
function submitConversion() {
    const planName  = document.getElementById('meta_plan_name').value.trim();
    const startDate = document.getElementById('meta_start_date').value;
    const endDate   = document.getElementById('meta_end_date').value;

    if (!planName || !startDate || !endDate) {
        alert('Please fill in Plan Name, Start Date and End Date first.');
        return;
    }

    // Check Schedule Days validation across all sections
    let invalidSec = false;
    document.querySelectorAll('.sec-dur').forEach(el => {
        if (parseFloat(el.value) <= 0 || isNaN(parseFloat(el.value))) {
            invalidSec = true;
        }
    });

    if (invalidSec) {
        alert('Validation Error: Schedule Days must be greater than 0 for all sections before calculating.');
        return;
    }

    document.getElementById('f_plan_name').value  = planName;
    document.getElementById('f_start_date').value = startDate;
    document.getElementById('f_end_date').value   = endDate;
    document.getElementById('f_notes').value      = document.getElementById('meta_notes').value;

    const body = document.getElementById('f_body');
    body.innerHTML = '';
    let hasAny = false;

    document.querySelectorAll('.section-card').forEach(card => {
        const si = card.dataset.sectionIdx;
        const secId = card.dataset.sectionId || '';
        const secName = card.dataset.sectionName || '';
        const tbody = document.getElementById(`tbody-${si}`);
        if (!tbody) return;
        const workedQty    = document.getElementById(`sec-qty-${si}`)?.value || '0';
        const scheduleDays = document.getElementById(`sec-dur-${si}`)?.value || '1';
        const taskStart    = document.getElementById(`sec-task-start-${si}`)?.value || '';
        const taskEnd      = document.getElementById(`sec-task-end-${si}`)?.value || '';

        addH(body, `sections[${si}][section_id]`,       secId);
        addH(body, `sections[${si}][section_name]`,     secName);
        addH(body, `sections[${si}][worked_quantity]`,  workedQty);
        addH(body, `sections[${si}][schedule_days]`,    scheduleDays);
        addH(body, `sections[${si}][task_start_date]`,  taskStart);
        addH(body, `sections[${si}][task_end_date]`,    taskEnd);

        let ri = 0;
        tbody.querySelectorAll('tr').forEach(row => {
            const cb = row.querySelector('input[type=checkbox]');
            if (!cb?.checked) return;
            const name = (row.querySelector('.r-name-h')?.value || '').trim();
            if (!name) return;
            hasAny = true;
            addH(body, `sections[${si}][resources][${ri}][name]`,              name);
            addH(body, `sections[${si}][resources][${ri}][type]`,              row.querySelector('.r-type')?.value  || 'material');
            addH(body, `sections[${si}][resources][${ri}][std_resource_qty]`,  row.querySelector('.r-std-qty')?.value || '1');
            addH(body, `sections[${si}][resources][${ri}][std_avg_output]`,    row.querySelector('.r-std-avg')?.value || '0');
            addH(body, `sections[${si}][resources][${ri}][req_daily_output]`,  row.querySelector('.r-req-output')?.value || '0');
            addH(body, `sections[${si}][resources][${ri}][output_ratio]`,      row.querySelector('.r-output-ratio')?.value || '1');
            addH(body, `sections[${si}][resources][${ri}][per_day_qty]`,       row.querySelector('.r-per-day')?.value || '0');
            addH(body, `sections[${si}][resources][${ri}][qty]`,               row.querySelector('.r-qty')?.value   || '0');
            addH(body, `sections[${si}][resources][${ri}][unit]`,              row.querySelector('.r-unit-input')?.value || '');
            addH(body, `sections[${si}][resources][${ri}][rate]`,              row.querySelector('.r-rate')?.value  || '0');
            addH(body, `sections[${si}][resources][${ri}][total_cost]`,        row.querySelector('.r-cost')?.value  || '0');
            ri++;
        });
    });

    if (!hasAny) {
        alert('Please add at least one resource row with a name before creating the plan.');
        return;
    }
    document.getElementById('mainForm').submit();
}

function addH(container, name, val) {
    const i = document.createElement('input');
    i.type = 'hidden'; i.name = name; i.value = val;
    container.appendChild(i);
}

function esc(str) {
    return String(str||'')
        .replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
</script>
@endpush
