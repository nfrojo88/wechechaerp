@extends('layouts.app')

@section('title', 'Takeoff Sheet — ' . $takeoff->title)

@push('styles')
<style>
    /* ── Inline add row ──────────────────────────────────────── */
    .inline-add-row td { background: #f0fdf4; border-bottom: 2px solid #86efac; padding: 5px 6px; vertical-align: middle; }
    .inline-add-row input, .inline-add-row select {
        border: 1px solid #d1d5db; border-radius: 4px; padding: 4px 6px;
        font-size: 12px; width: 100%; background: #fff; outline: none; transition: border-color .15s;
    }
    .inline-add-row input:focus, .inline-add-row select:focus {
        border-color: #22c55e; box-shadow: 0 0 0 2px rgba(34,197,94,.15);
    }
    .inline-add-row input[readonly] { background: #f1f5f9; color: #64748b; }
    .add-row-trigger td { background: #fafafa; }
    .btn-add-inline {
        background: none; border: 1px dashed #94a3b8; border-radius: 4px;
        color: #64748b; font-size: 12px; padding: 3px 12px; cursor: pointer; transition: all .15s;
    }
    .btn-add-inline:hover { border-color: #22c55e; color: #16a34a; background: #f0fdf4; }

    /* ── Section header row ──────────────────────────────────── */
    .section-hdr { background: linear-gradient(90deg,#1e293b 0%,#1e3a5f 100%) !important; color: #fff !important; }
    .section-hdr td { color: #fff !important; padding: 8px 12px !important; border-color: #334155 !important; }
    .section-hdr .section-name {
        font-size: 12px; font-weight: 700; letter-spacing: .5px;
        text-transform: uppercase; display: flex; align-items: center; gap: 8px;
    }
    .section-hdr .task-badge {
        font-size: 10px; font-weight: 600; background: rgba(255,255,255,.15);
        color: #bfdbfe; border: 1px solid rgba(255,255,255,.2);
        border-radius: 999px; padding: 2px 8px; letter-spacing: .3px;
    }
    .section-qty { font-size: 13px; font-weight: 700; color: #7dd3fc !important; }
    .section-unit { font-size: 11px; color: #94a3b8 !important; }

    /* ── Rebar section header ────────────────────────────────── */
    .rebar-section-hdr { background: #9a3412 !important; color: #fff !important; }
    .rebar-section-hdr td { color: #fff !important; padding: 9px 12px !important; }

    /* ── Subtotal / grand-total rows ─────────────────────────── */
    .subtotal-row td { background: #eff6ff; font-weight: 600; font-size: 12px; color: #1e40af; }
    .grand-total-row td { background: #1e293b; color: #fff; font-weight: 700; }

    /* ── Item action buttons ─────────────────────────────────── */
    .action-btn {
        display: inline-flex; align-items: center; justify-content: center;
        width: 24px; height: 24px; border-radius: 5px; font-size: 11px;
        border: 1px solid; cursor: pointer; transition: all .15s; background: transparent;
        text-decoration: none; padding: 0;
    }
    .action-btn.edit   { color: #3b82f6; border-color: #bfdbfe; }
    .action-btn.edit:hover   { background: #eff6ff; border-color: #3b82f6; }
    .action-btn.header { color: #6366f1; border-color: #c7d2fe; font-weight: 700; font-size: 10px; }
    .action-btn.header:hover { background: #eef2ff; border-color: #6366f1; }
    .action-btn.add    { color: #22c55e; border-color: #bbf7d0; }
    .action-btn.add:hover    { background: #f0fdf4; border-color: #22c55e; }
    .action-btn.del    { color: #ef4444; border-color: #fecaca; }
    .action-btn.del:hover    { background: #fef2f2; border-color: #ef4444; }
    .action-btn.del-section { color: #ef4444; border-color: transparent; width: auto; padding: 2px 6px; }
    .action-btn.del-section:hover { background: rgba(239,68,68,.15); border-color: rgba(239,68,68,.3); }
    .actions-cell { display: flex; align-items: center; gap: 3px; justify-content: center; }

    /* ── Inline edit row ──────────────────────────────────────── */
    .inline-edit-row td { background: #eff6ff; border-bottom: 2px solid #93c5fd; padding: 5px 6px; vertical-align: middle; }
    .inline-edit-row .edit-label {
        display: inline-flex; align-items: center; justify-content: center;
        width: 28px; height: 28px; background: #3b82f6; color: #fff;
        border-radius: 6px; font-size: 10px; font-weight: 700;
    }

    /* ── Manage-access panel ──────────────────────────────────── */
    .access-badge { display:inline-flex; align-items:center; gap:6px; }
</style>
@endpush

@section('content')
@php
if (!function_exists('evalTakeoffExpr')) {
    function evalTakeoffExpr($expr) {
        if ($expr === null || $expr === '') return null;
        $sanitized = preg_replace('/[^0-9+\-*\/.\(\) ]/', '', (string)$expr);
        if ($sanitized === '') return null;
        try {
            $val = @eval("return ({$sanitized});");
            return is_numeric($val) ? (float)$val : null;
        } catch (\Throwable $e) {
            return null;
        }
    }
}
@endphp
<div class="container-fluid">

    {{-- ── Page Header ──────────────────────────────────────────── --}}
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <div>
            <h1 class="h4 mb-0 fw-bold">
                <i class="fa-solid fa-file-invoice text-primary me-2"></i>{{ $takeoff->title }}
            </h1>
            <p class="text-muted small mb-0 mt-1">
                Project: <strong>{{ $takeoff->project->name ?? '—' }}</strong>
                &nbsp;|&nbsp; Created by: <strong>{{ $takeoff->creator->name ?? '—' }}</strong>
                &nbsp;|&nbsp;
                <span class="badge bg-{{ strtolower($takeoff->status) === 'approved' ? 'success' : (strtolower($takeoff->status) === 'draft' ? 'secondary' : 'primary') }}">
                    {{ strtoupper($takeoff->status) }}
                </span>
            </p>
        </div>
        <div class="d-flex gap-2 mt-2 mt-sm-0">
            @if($canEdit)
                <button type="button" class="btn btn-outline-secondary btn-sm shadow-sm" data-bs-toggle="modal" data-bs-target="#addSectionModal">
                    <i class="fa-solid fa-layer-group me-1"></i> Add Section
                </button>
            @endif
            @if($takeoff->sections->flatMap->items->count() > 0)
                @if(strtolower($takeoff->sheet_type) === 'rebar_schedule' || strtolower($takeoff->category) === 'rebar')
                    <button type="button" class="btn btn-success btn-sm shadow-sm btnRebarCutOptimizeTrigger"
                            data-url="{{ route('takeoff.rebar-cut-optimize', $takeoff) }}"
                            data-erp-url="{{ route('takeoff.rebar-erp-convert', $takeoff) }}">
                        <i class="fa-solid fa-scissors me-1"></i> Convert to Materials
                    </button>
                @else
                    <a href="{{ route('takeoff.convert', $takeoff) }}" class="btn btn-success btn-sm shadow-sm">
                        <i class="fa-solid fa-boxes-stacked me-1"></i> Convert to Materials
                    </a>
                @endif
            @endif
            <a href="{{ route('takeoff.index') }}" class="btn btn-secondary btn-sm shadow-sm">
                <i class="fa-solid fa-arrow-left me-1"></i> Back
            </a>
        </div>
    </div>

    {{-- ── Flash Messages ───────────────────────────────────────── --}}
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show shadow-sm py-2" role="alert">
            <i class="fa-solid fa-circle-check me-2"></i>{{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show shadow-sm py-2" role="alert">
            <i class="fa-solid fa-circle-exclamation me-2"></i>{{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    {{-- ── Edit Access: Creator Panel ───────────────────────────── --}}
    @if($isCreator && ($pendingRequests->isNotEmpty() || $approvedRequests->isNotEmpty()))
        <div class="card shadow-sm mb-4 border-start border-primary border-3">
            <div class="card-header d-flex align-items-center gap-2 py-2">
                <i class="fa-solid fa-users-gear text-primary"></i>
                <span class="fw-bold">Manage Edit Access</span>
                @if($pendingRequests->isNotEmpty())
                    <span class="badge bg-warning text-dark ms-1">{{ $pendingRequests->count() }} pending</span>
                @endif
            </div>
            <div class="card-body p-0">
                <ul class="list-group list-group-flush">
                    @foreach($pendingRequests as $req)
                        <li class="list-group-item d-flex justify-content-between align-items-center bg-warning-subtle">
                            <span>
                                <i class="fa-solid fa-hourglass-half text-warning me-2"></i>
                                <strong>{{ $req->user->name }}</strong> requested edit access
                            </span>
                            <div class="d-flex gap-2">
                                <form action="{{ route('takeoff.approve-edit', $req) }}" method="POST">
                                    @csrf
                                    <button class="btn btn-sm btn-success shadow-sm">
                                        <i class="fa-solid fa-check me-1"></i>Approve
                                    </button>
                                </form>
                                <form action="{{ route('takeoff.reject-edit', $req) }}" method="POST">
                                    @csrf
                                    <button class="btn btn-sm btn-outline-danger shadow-sm">
                                        <i class="fa-solid fa-xmark me-1"></i>Reject
                                    </button>
                                </form>
                            </div>
                        </li>
                    @endforeach
                    @foreach($approvedRequests as $req)
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <span>
                                <i class="fa-solid fa-circle-check text-success me-2"></i>
                                <strong>{{ $req->user->name }}</strong> has edit access
                            </span>
                            <form action="{{ route('takeoff.revoke-edit', $req) }}" method="POST">
                                @csrf
                                <button class="btn btn-sm btn-outline-secondary shadow-sm">
                                    <i class="fa-solid fa-lock me-1"></i>Revoke
                                </button>
                            </form>
                        </li>
                    @endforeach
                </ul>
            </div>
        </div>
    @elseif(!$canEdit)
        <div class="alert alert-info d-flex align-items-center justify-content-between shadow-sm mb-4 py-2" role="alert">
            <span><i class="fa-solid fa-lock me-2"></i> You are viewing this sheet in <strong>read-only</strong> mode.</span>
            @if(!$editRequest || in_array($editRequest->status, ['rejected','revoked']))
                <form action="{{ route('takeoff.request-edit', $takeoff) }}" method="POST" class="m-0">
                    @csrf
                    <button class="btn btn-sm btn-primary shadow-sm">
                        <i class="fa-solid fa-paper-plane me-1"></i>Request Edit Access
                    </button>
                </form>
            @elseif($editRequest->status === 'pending')
                <span class="badge bg-warning text-dark px-3 py-2">
                    <i class="fa-solid fa-hourglass-half me-1"></i> Request Pending
                </span>
            @endif
        </div>
    @endif

    {{-- ── Takeoff Grid ─────────────────────────────────────────── --}}
    <div class="card shadow-sm">
        @if(strtolower($takeoff->sheet_type) === 'rebar_schedule' || strtolower($takeoff->category) === 'rebar')
            @include('takeoff.partials.rebar-grid')
        @else
            {{-- ── Standard Takeoff Grid ── --}}
            <div class="card-header d-flex align-items-center gap-2 py-2">
                <i class="fa-solid fa-table text-primary"></i>
                <span class="fw-bold">Takeoff Items</span>
                <span class="badge bg-secondary ms-auto">{{ $takeoff->sections->count() }} section(s)</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-bordered table-hover align-middle mb-0" style="font-size:13px;">
                        <thead class="table-dark">
                            <tr>
                                <th style="width:42px;">#</th>
                                <th>Item Description</th>
                                <th class="text-center" style="width:90px;">Length (L)</th>
                                <th class="text-center" style="width:90px;">Width (B)</th>
                                <th class="text-center" style="width:90px;">Height (H)</th>
                                <th class="text-center" style="width:80px;">Count</th>
                                <th class="text-end" style="width:90px;">Net Qty</th>
                                <th style="width:70px;">Unit</th>
                                <th class="text-end" style="width:90px;">Rate</th>
                                <th class="text-end" style="width:100px;">Total Cost</th>
                                @if($canEdit)
                                    <th class="text-center" style="width:90px;">Actions</th>
                                @endif
                            </tr>
                        </thead>
                        <tbody>
                            @php $globalItemCounter = 1; @endphp
                            @forelse($takeoff->sections as $section)
                                @php
                                    $sectionTotalQty  = $section->items->sum('result_quantity');
                                    $sectionTotalCost = $section->items->sum('total_cost');
                                    $sectionUnit      = $section->items->first()?->result_unit ?? '';
                                @endphp

                                {{-- Section Header --}}
                                <tr class="section-hdr">
                                    <td colspan="{{ $canEdit ? 7 : 7 }}">
                                        <div class="section-name">
                                            <i class="fa-solid fa-layer-group" style="opacity:.6;font-size:11px;"></i>
                                            {{ $section->name }}
                                            @if($section->task)
                                                <span class="task-badge">
                                                    <i class="fa-solid fa-list-check me-1" style="font-size:9px;"></i>{{ $section->task->name }}
                                                </span>
                                            @endif
                                        </div>
                                    </td>
                                    <td class="text-end section-qty">
                                        {{ number_format($sectionTotalQty, 3) }}
                                    </td>
                                    <td class="section-unit">{{ $sectionUnit }}</td>
                                    <td class="text-end section-unit" style="font-size:12px;">
                                        @if($sectionTotalCost > 0)
                                            {{ number_format($sectionTotalCost, 2) }}
                                        @endif
                                    </td>
                                    @if($canEdit)
                                        <td class="text-center">
                                            <form method="POST" action="{{ route('takeoff.sections.destroy', [$takeoff, $section]) }}"
                                                  onsubmit="return confirm('Delete section and all its items?')">
                                                @csrf @method('DELETE')
                                                <button type="submit" class="action-btn del-section" title="Delete Section">
                                                    <i class="fa-solid fa-trash-can"></i> Delete
                                                </button>
                                            </form>
                                        </td>
                                    @endif
                                </tr>

                                {{-- Items --}}
                                @foreach($section->items as $item)
                                    @if($item->is_header)
                                        <tr class="table-light">
                                            <td class="text-muted small">—</td>
                                            <td colspan="{{ $canEdit ? 8 : 9 }}" class="fw-bold" style="color:#1e3a8a;border-left:3px solid #3b82f6;padding-left:12px;">
                                                {{ $item->element }}
                                            </td>
                                            @if($canEdit)
                                                <td>
                                                    <div class="actions-cell">
                                                        <form method="POST" action="{{ route('takeoff.items.toggle-header', [$takeoff, $item]) }}" class="d-inline">
                                                            @csrf @method('PATCH')
                                                            <button class="action-btn header" title="Toggle Header">H</button>
                                                        </form>
                                                        <button type="button" onclick="showInlineForm('{{ $section->id }}')" class="action-btn add" title="Add Item">
                                                            <i class="fa-solid fa-plus"></i>
                                                        </button>
                                                        <form method="POST" action="{{ route('takeoff.items.destroy', [$takeoff, $item]) }}" class="d-inline" onsubmit="return confirm('Delete this item?')">
                                                            @csrf @method('DELETE')
                                                            <button class="action-btn del" title="Delete"><i class="fa-solid fa-xmark"></i></button>
                                                        </form>
                                                    </div>
                                                </td>
                                            @endif
                                        </tr>
                                    @else
                                         @php
                                             $lenRaw = $item->calculation_data['length'] ?? null;
                                             $lenVal = evalTakeoffExpr($lenRaw);
                                             $widRaw = $item->calculation_data['width'] ?? null;
                                             $widVal = evalTakeoffExpr($widRaw);
                                             $hgtRaw = $item->calculation_data['height'] ?? null;
                                             $hgtVal = evalTakeoffExpr($hgtRaw);
                                             $cntRaw = $item->count ?? null;
                                             $cntVal = evalTakeoffExpr($cntRaw);
                                         @endphp
                                         <tr id="view-row-{{ $item->id }}">
                                             <td class="text-muted">{{ $globalItemCounter++ }}</td>
                                             <td class="fw-semibold">{{ $item->element }}</td>
                                             <td class="text-center formula-cell" data-formula="{{ $lenRaw }}" title="{{ $lenRaw && $lenVal !== null && (string)$lenRaw !== (string)$lenVal ? 'Formula: ' . $lenRaw : '' }}">
                                                 {{ $lenVal !== null ? (str_contains((string)$lenRaw, '+') || str_contains((string)$lenRaw, '-') || str_contains((string)$lenRaw, '*') || str_contains((string)$lenRaw, '/') ? (floor($lenVal) == $lenVal ? number_format($lenVal, 0) : number_format($lenVal, 3)) : $lenRaw) : ($lenRaw ?? '—') }}
                                             </td>
                                             <td class="text-center formula-cell" data-formula="{{ $widRaw }}" title="{{ $widRaw && $widVal !== null && (string)$widRaw !== (string)$widVal ? 'Formula: ' . $widRaw : '' }}">
                                                 {{ $widVal !== null ? (str_contains((string)$widRaw, '+') || str_contains((string)$widRaw, '-') || str_contains((string)$widRaw, '*') || str_contains((string)$widRaw, '/') ? (floor($widVal) == $widVal ? number_format($widVal, 0) : number_format($widVal, 3)) : $widRaw) : ($widRaw ?? '—') }}
                                             </td>
                                             <td class="text-center formula-cell" data-formula="{{ $hgtRaw }}" title="{{ $hgtRaw && $hgtVal !== null && (string)$hgtRaw !== (string)$hgtVal ? 'Formula: ' . $hgtRaw : '' }}">
                                                 {{ $hgtVal !== null ? (str_contains((string)$hgtRaw, '+') || str_contains((string)$hgtRaw, '-') || str_contains((string)$hgtRaw, '*') || str_contains((string)$hgtRaw, '/') ? (floor($hgtVal) == $hgtVal ? number_format($hgtVal, 0) : number_format($hgtVal, 3)) : $hgtRaw) : ($hgtRaw ?? '—') }}
                                             </td>
                                             <td class="text-center formula-cell" data-formula="{{ $cntRaw }}" title="{{ $cntRaw && $cntVal !== null && (string)$cntRaw !== (string)$cntVal ? 'Formula: ' . $cntRaw : '' }}">
                                                 {{ $cntVal !== null ? (str_contains((string)$cntRaw, '+') || str_contains((string)$cntRaw, '-') || str_contains((string)$cntRaw, '*') || str_contains((string)$cntRaw, '/') ? (floor($cntVal) == $cntVal ? number_format($cntVal, 0) : number_format($cntVal, 3)) : $cntRaw) : ($cntRaw ?? 1) }}
                                             </td>
                                             <td class="text-end fw-bold text-primary">{{ number_format($item->result_quantity, 2) }}</td>
                                             <td>{{ $item->result_unit }}</td>
                                             <td class="text-end">{{ $item->unit_rate ? number_format($item->unit_rate, 3) : '—' }}</td>
                                             <td class="text-end fw-semibold">{{ $item->total_cost ? number_format($item->total_cost, 2) : '—' }}</td>
                                             @if($canEdit)
                                                 <td>
                                                     <div class="actions-cell">
                                                         <button type="button" onclick="showEditRow('{{ $item->id }}')" class="action-btn edit" title="Edit Item">
                                                             <i class="fa-solid fa-pen-to-square"></i>
                                                         </button>
                                                         <form method="POST" action="{{ route('takeoff.items.toggle-header', [$takeoff, $item]) }}" class="d-inline">
                                                             @csrf @method('PATCH')
                                                             <button class="action-btn header" title="Make Header">H</button>
                                                         </form>
                                                         <button type="button" onclick="showInlineForm('{{ $section->id }}')" class="action-btn add" title="Add Item">
                                                             <i class="fa-solid fa-plus"></i>
                                                         </button>
                                                         <form method="POST" action="{{ route('takeoff.items.destroy', [$takeoff, $item]) }}" class="d-inline" onsubmit="return confirm('Delete this item?')">
                                                             @csrf @method('DELETE')
                                                             <button class="action-btn del" title="Delete"><i class="fa-solid fa-xmark"></i></button>
                                                         </form>
                                                     </div>
                                                 </td>
                                             @endif
                                         </tr>
                                         @if($canEdit)
                                             <tr class="inline-add-row inline-edit-row d-none" id="edit-row-{{ $item->id }}">
                                                 <form action="{{ route('takeoff.items.update', [$takeoff, $item]) }}" method="POST">
                                                 @csrf @method('PATCH')
                                                 <td class="text-center">
                                                     <span class="edit-label"><i class="fa-solid fa-pen-to-square"></i></span>
                                                 </td>
                                                 <td style="min-width:160px;">
                                                     <input type="text" name="element" value="{{ $item->element }}" required>
                                                 </td>
                                                 <td><input type="text" name="length" class="qty-calc-edit formula-field" data-itemid="{{ $item->id }}" value="{{ $lenRaw }}" placeholder="0"></td>
                                                 <td><input type="text" name="width" class="qty-calc-edit formula-field" data-itemid="{{ $item->id }}" value="{{ $widRaw }}" placeholder="0"></td>
                                                 <td><input type="text" name="height" class="qty-calc-edit formula-field" data-itemid="{{ $item->id }}" value="{{ $hgtRaw }}" placeholder="0"></td>
                                                 <td><input type="text" name="count" class="qty-calc-edit formula-field" data-itemid="{{ $item->id }}" value="{{ $cntRaw ?? 1 }}"></td>
                                                 <td><input type="number" step="0.001" name="result_quantity" id="net-qty-edit-{{ $item->id }}" readonly value="{{ number_format($item->result_quantity, 3, '.', '') }}" placeholder="0.000" required></td>
                                                 <td>
                                                     <select name="result_unit" id="unit-edit-{{ $item->id }}" required>
                                                         <option value="m"   @selected($item->result_unit == 'm')>m</option>
                                                         <option value="m2"  @selected($item->result_unit == 'm2')>m²</option>
                                                         <option value="m3"  @selected($item->result_unit == 'm3')>m³</option>
                                                         <option value="kg"  @selected($item->result_unit == 'kg')>kg</option>
                                                         <option value="ton" @selected($item->result_unit == 'ton')>ton</option>
                                                         <option value="pcs" @selected($item->result_unit == 'pcs')>pcs</option>
                                                         <option value="ls"  @selected($item->result_unit == 'ls')>l.s.</option>
                                                     </select>
                                                 </td>
                                                 <td><input type="number" step="0.01" name="unit_rate" value="{{ $item->unit_rate }}" placeholder="0.00"></td>
                                                 <td></td>
                                                 <td style="white-space:nowrap;">
                                                     <button type="submit" class="btn btn-sm btn-primary py-0 px-2 fw-bold me-1" style="font-size:11px;">Update</button>
                                                     <button type="button" onclick="hideEditRow('{{ $item->id }}')" class="btn btn-sm btn-outline-secondary border-0 py-0 px-1">✕</button>
                                                 </td>
                                                 </form>
                                             </tr>
                                         @endif
                                     @endif
                                @endforeach

                                {{-- Section Subtotal --}}
                                @if($section->items->isNotEmpty())
                                    <tr class="subtotal-row">
                                        <td colspan="{{ $canEdit ? 6 : 6 }}" class="text-end pe-3 small">Section Totals:</td>
                                        <td class="text-end">{{ number_format($sectionTotalQty, 3) }}</td>
                                        <td class="small">{{ $sectionUnit }}</td>
                                        <td class="text-end small">
                                            @if($sectionTotalCost > 0) {{ number_format($sectionTotalCost, 2) }} @endif
                                        </td>
                                        @if($canEdit)<td colspan="2"></td>@else<td></td>@endif
                                    </tr>
                                @endif

                                {{-- Inline Add Row --}}
                                @if($canEdit)
                                    <tr class="add-row-trigger" id="trigger-{{ $section->id }}">
                                        <td colspan="11">
                                            <button type="button" class="btn-add-inline" onclick="showInlineForm('{{ $section->id }}')">
                                                <i class="fa-solid fa-plus me-1"></i> Add Item to "{{ $section->name }}"
                                            </button>
                                        </td>
                                    </tr>
                                    <tr class="inline-add-row d-none" id="inline-form-{{ $section->id }}">
                                        <form action="{{ route('takeoff.items.store', $takeoff) }}" method="POST">
                                        @csrf
                                        <input type="hidden" name="takeoff_section_id" value="{{ $section->id }}">
                                        <td class="text-muted small">+</td>
                                        <td style="min-width:160px;">
                                            <input type="text" name="element" placeholder="Description..." required>
                                        </td>
                                        <td><input type="text" name="length" class="qty-calc-inline" data-sid="{{ $section->id }}" placeholder="0"></td>
                                        <td><input type="text" name="width" class="qty-calc-inline" data-sid="{{ $section->id }}" placeholder="0"></td>
                                        <td><input type="text" name="height" class="qty-calc-inline" data-sid="{{ $section->id }}" placeholder="0"></td>
                                        <td><input type="text" name="count" class="qty-calc-inline" data-sid="{{ $section->id }}" value="1"></td>
                                        <td><input type="number" step="0.001" name="result_quantity" id="net-qty-{{ $section->id }}" readonly placeholder="0.000" required></td>
                                        <td>
                                            <select name="result_unit" id="unit-{{ $section->id }}" required>
                                                <option value="">Unit</option>
                                                <option value="m">m</option>
                                                <option value="m2">m²</option>
                                                <option value="m3">m³</option>
                                                <option value="kg">kg</option>
                                                <option value="ton">ton</option>
                                                <option value="pcs">pcs</option>
                                                <option value="ls">l.s.</option>
                                            </select>
                                        </td>
                                        <td><input type="number" step="0.01" name="unit_rate" placeholder="0.00" class="inline-last-field" data-sid="{{ $section->id }}"></td>
                                        <td></td>
                                        <td style="white-space:nowrap;">
                                            <button type="submit" class="btn btn-sm btn-success py-0 px-2 fw-bold me-1" style="font-size:11px;">Save</button>
                                            <button type="button" onclick="hideInlineForm({{ $section->id }})" class="btn btn-sm btn-outline-secondary border-0 py-0 px-1">✕</button>
                                        </td>
                                        </form>
                                    </tr>
                                @endif

                            @empty
                                <tr>
                                    <td colspan="11" class="text-center py-5 text-muted">
                                        <i class="fa-solid fa-ruler-combined fa-3x d-block mb-3" style="opacity:.2;"></i>
                                        No sections yet.
                                        @if($canEdit)
                                            <a href="#" data-bs-toggle="modal" data-bs-target="#addSectionModal" class="text-primary">Add a section</a> to start.
                                        @endif
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    </div>{{-- /card --}}

</div>{{-- /container-fluid --}}


{{-- ── Add Section Modal ────────────────────────────────────────── --}}
@if($canEdit)
<div class="modal fade" id="addSectionModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form action="{{ route('takeoff.sections.store', $takeoff) }}" method="POST" class="modal-content">
            @csrf
            <div class="modal-header">
                <h5 class="modal-title"><i class="fa-solid fa-layer-group text-primary me-2"></i>Add Section</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label small fw-bold">Section Name <span class="text-danger">*</span></label>
                    <input type="text" name="name" class="form-control form-control-sm" required placeholder="e.g. Ground Floor">
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-bold">Link to Schedule Task <span class="text-muted">(Optional)</span></label>
                    <select name="schedule_task_id" class="form-select form-select-sm">
                        <option value="">-- None --</option>
                        @foreach(\App\Models\Schedule::with('tasks')->where('project_id', $takeoff->project_id)->get() as $schedule)
                            <optgroup label="{{ $schedule->project ? $schedule->project->name . ' — ' : '' }}{{ $schedule->name ?? $schedule->title }}">
                                @foreach($schedule->tasks as $task)
                                    <option value="{{ $task->id }}">{{ $task->wbs_code }} - {{ $task->name }}</option>
                                @endforeach
                            </optgroup>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-sm btn-primary">Save Section</button>
            </div>
        </form>
    </div>
</div>
@endif


@push('scripts')
<script>
    function safeEval(expr) {
        if (!expr) return null;
        try {
            let sanitized = String(expr).replace(/[^0-9+\-*/.() ]/g, '');
            if (!sanitized) return null;
            let val = Function('"use strict";return (' + sanitized + ')')();
            return isFinite(val) ? val : null;
        } catch (e) { return null; }
    }

    function showInlineForm(sectionId) {
        document.getElementById('trigger-' + sectionId).classList.add('d-none');
        document.getElementById('inline-form-' + sectionId).classList.remove('d-none');
        document.querySelector('#inline-form-' + sectionId + ' input[name="element"]').focus();
    }

    function hideInlineForm(sectionId) {
        document.getElementById('inline-form-' + sectionId).classList.add('d-none');
        document.getElementById('trigger-' + sectionId).classList.remove('d-none');
    }

    function showEditRow(itemId) {
        document.getElementById('view-row-' + itemId).classList.add('d-none');
        document.getElementById('edit-row-' + itemId).classList.remove('d-none');
        document.querySelectorAll('#edit-row-' + itemId + ' .formula-field').forEach(input => {
            const raw = input.value.trim();
            if (raw) {
                input.dataset.formula = raw;
                const val = safeEval(raw);
                if (val !== null && document.activeElement !== input) input.value = val;
            }
        });
    }

    function hideEditRow(itemId) {
        document.getElementById('edit-row-' + itemId).classList.add('d-none');
        document.getElementById('view-row-' + itemId).classList.remove('d-none');
    }

    document.addEventListener('DOMContentLoaded', function() {

        // Init formula fields on page load
        document.querySelectorAll('.qty-calc-inline, .qty-calc-edit, .formula-field').forEach(function(input) {
            const raw = input.value.trim();
            if (raw) {
                input.dataset.formula = raw;
                const val = safeEval(raw);
                if (val !== null && document.activeElement !== input) {
                    input.value = val;
                }
            }
        });

        // Focus: show raw equation (e.g. 2+3)
        document.addEventListener('focusin', function(e) {
            const input = e.target;
            if (input.matches && (input.matches('.qty-calc-inline') || input.matches('.qty-calc-edit') || input.matches('.formula-field'))) {
                if (input.dataset.formula) {
                    input.value = input.dataset.formula;
                }
            }
        });

        // Input: update formula storage
        document.addEventListener('input', function(e) {
            const input = e.target;
            if (input.matches && (input.matches('.qty-calc-inline') || input.matches('.qty-calc-edit') || input.matches('.formula-field'))) {
                input.dataset.formula = input.value;
            }
        });

        // Blur: show evaluated result (e.g. 5)
        document.addEventListener('focusout', function(e) {
            const input = e.target;
            if (input.matches && (input.matches('.qty-calc-inline') || input.matches('.qty-calc-edit') || input.matches('.formula-field'))) {
                const raw = input.value.trim();
                if (raw) {
                    input.dataset.formula = raw;
                    const val = safeEval(raw);
                    if (val !== null) {
                        input.value = val;
                    }
                }
            }
        });

        // Submit: restore raw equation before sending to server
        document.addEventListener('submit', function(e) {
            const form = e.target;
            form.querySelectorAll('.qty-calc-inline, .qty-calc-edit, .formula-field').forEach(function(input) {
                if (input.dataset.formula) {
                    input.value = input.dataset.formula;
                }
            });
        });

        document.addEventListener('keydown', function(e) {
            if (e.key !== 'Enter') return;
            const input = e.target;
            const formRow = input.closest('tr.inline-add-row');
            if (!formRow) return;
            e.preventDefault();
            const form = formRow.querySelector('form');
            const desc = form.querySelector('input[name="element"]').value.trim();
            if (!desc) { form.querySelector('input[name="element"]').focus(); return; }
            ensureDefaultsAndSubmit(form);
        });

        document.addEventListener('blur', function(e) {
            const input = e.target;
            if (input.name !== 'element') return;
            const formRow = input.closest('tr.inline-add-row');
            if (!formRow) return;
            const desc = input.value.trim();
            if (!desc) return;
            ensureDefaultsAndSubmit(formRow.querySelector('form'));
        }, true);

        document.addEventListener('submit', function(e) {
            const form = e.target;
            if (!form.closest('tr.inline-add-row')) return;
            if (form.dataset.submitting === 'true') { e.preventDefault(); return; }
            form.dataset.submitting = 'true';
            form.querySelectorAll('button').forEach(btn => {
                btn.disabled = true;
                if (btn.type === 'submit') btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i>';
            });
            form.closest('tr').style.background = '#dcfce7';
        });

        function ensureDefaultsAndSubmit(form) {
            const qtyInput = form.querySelector('input[name="result_quantity"]');
            const unitSel  = form.querySelector('select[name="result_unit"]');
            if (!qtyInput.value || parseFloat(qtyInput.value) === 0) qtyInput.value = '1.000';
            if (!unitSel.value) unitSel.value = 'pcs';
            if (form.dataset.submitting !== 'true') form.requestSubmit();
        }

        document.querySelectorAll('.qty-calc-inline').forEach(function(input) {
            input.addEventListener('input', function() {
                const sid = this.dataset.sid;
                const row = document.getElementById('inline-form-' + sid);
                const l = safeEval((row.querySelector('input[name="length"]').dataset.formula || row.querySelector('input[name="length"]').value).trim());
                const w = safeEval((row.querySelector('input[name="width"]').dataset.formula || row.querySelector('input[name="width"]').value).trim());
                const h = safeEval((row.querySelector('input[name="height"]').dataset.formula || row.querySelector('input[name="height"]').value).trim());
                const c = safeEval((row.querySelector('input[name="count"]').dataset.formula || row.querySelector('input[name="count"]').value).trim());

                let total = 1, hasVal = false, dims = 0;
                if (l !== null && l !== 0) { total *= l; hasVal = true; dims++; }
                if (w !== null && w !== 0) { total *= w; hasVal = true; dims++; }
                if (h !== null && h !== 0) { total *= h; hasVal = true; dims++; }
                if (c !== null) { total *= c; hasVal = true; }

                const netQty = document.getElementById('net-qty-' + sid);
                const unit   = document.getElementById('unit-' + sid);
                netQty.value = hasVal ? total.toFixed(3) : '1.000';
                if (dims === 0 && unit.value === '') unit.value = 'pcs';
                if (dims === 1) unit.value = 'm';
                if (dims === 2) unit.value = 'm2';
                if (dims === 3) unit.value = 'm3';
            });
        });

        document.querySelectorAll('.qty-calc-edit').forEach(function(input) {
            input.addEventListener('input', function() {
                const itemid = this.dataset.itemid;
                const row = document.getElementById('edit-row-' + itemid);
                if (!row) return;
                const l = safeEval((row.querySelector('input[name="length"]').dataset.formula || row.querySelector('input[name="length"]').value).trim());
                const w = safeEval((row.querySelector('input[name="width"]').dataset.formula || row.querySelector('input[name="width"]').value).trim());
                const h = safeEval((row.querySelector('input[name="height"]').dataset.formula || row.querySelector('input[name="height"]').value).trim());
                const c = safeEval((row.querySelector('input[name="count"]').dataset.formula || row.querySelector('input[name="count"]').value).trim());

                let total = 1, hasVal = false, dims = 0;
                if (l !== null && l !== 0) { total *= l; hasVal = true; dims++; }
                if (w !== null && w !== 0) { total *= w; hasVal = true; dims++; }
                if (h !== null && h !== 0) { total *= h; hasVal = true; dims++; }
                if (c !== null) { total *= c; hasVal = true; }

                const netQty = document.getElementById('net-qty-edit-' + itemid);
                const unit   = document.getElementById('unit-edit-' + itemid);
                if (netQty) netQty.value = hasVal ? total.toFixed(3) : '1.000';
                if (unit) {
                    if (dims === 0 && unit.value === '') unit.value = 'pcs';
                    if (dims === 1) unit.value = 'm';
                    if (dims === 2) unit.value = 'm2';
                    if (dims === 3) unit.value = 'm3';
                }
            });
        });
    });
</script>
@endpush

{{-- ═══════════════════════════════════════════════════════════════════ --}}
{{-- CUT OPTIMIZATION RESULT MODAL                                      --}}
{{-- ═══════════════════════════════════════════════════════════════════ --}}
<div class="modal fade" id="cutOptModal" tabindex="-1" aria-labelledby="cutOptModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-scrollable">
    <div class="modal-content" style="border-radius:12px; overflow:hidden;">

      {{-- Modal Header --}}
      <div class="modal-header" style="background:linear-gradient(135deg,#1e293b 0%,#c2410c 100%); color:#fff; border:0; padding:18px 24px;">
        <div>
          <h5 class="modal-title mb-0" id="cutOptModalLabel">
            <i class="fa-solid fa-scissors me-2" style="color:#fb923c;"></i>
            Rebar Cut Optimization Result
          </h5>
          <small class="opacity-75" id="cutOptSubtitle">Standard bar: 12m &nbsp;|&nbsp; Saw kerf: 2cm per cut</small>
        </div>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>

      {{-- Loading spinner --}}
      <div id="cutOptLoading" class="text-center py-5">
        <div class="spinner-border" style="width:3rem;height:3rem;color:#c2410c;" role="status"></div>
        <p class="mt-3 text-muted fw-semibold">Running cut optimization…</p>
      </div>

      {{-- Results body (hidden until loaded) --}}
      <div id="cutOptBody" class="modal-body p-0" style="display:none;">

        {{-- Summary cards row --}}
        <div id="cutOptSummaryCards" class="d-flex flex-wrap gap-3 p-4" style="background:#f8fafc; border-bottom:1px solid #e2e8f0;"></div>

        {{-- Per-diameter cutting plans --}}
        <div id="cutOptDiaTabs" class="p-4"></div>

        {{-- Save to ERP Plan Form --}}
        <div class="p-4" style="background:#f0fdf4; border-top:2px solid #86efac;">
          <h6 class="fw-bold text-success mb-1"><i class="fa-solid fa-floppy-disk me-2"></i>Save to ERP Plan (Separated by Section)</h6>
          <p class="text-muted small mb-3"><i class="fa-solid fa-layer-group text-success me-1"></i> A separate WBS Task will be created in the ERP Plan for each takeoff section with its allocated 12m rebar bars.</p>
          <div class="row g-3">
            <div class="col-md-4">
              <label class="form-label small fw-semibold">Plan Name <span class="text-danger">*</span></label>
              <input type="text" id="erpPlanName" class="form-control form-control-sm" placeholder="e.g. Rebar Procurement Plan" value="Rebar Cut Plan – {{ $takeoff->title }}">
            </div>
            <div class="col-md-3">
              <label class="form-label small fw-semibold">Start Date <span class="text-danger">*</span></label>
              <input type="date" id="erpStartDate" class="form-control form-control-sm" value="{{ now()->format('Y-m-d') }}">
            </div>
            <div class="col-md-3">
              <label class="form-label small fw-semibold">End Date <span class="text-danger">*</span></label>
              <input type="date" id="erpEndDate" class="form-control form-control-sm" value="{{ now()->addDays(30)->format('Y-m-d') }}">
            </div>
            <div class="col-md-2 d-flex align-items-end">
              <button type="button" id="btnSaveErpPlan" class="btn btn-success btn-sm w-100 shadow-sm">
                <i class="fa-solid fa-check me-1"></i> Save Plan
              </button>
            </div>
            <div class="col-12">
              <div id="erpSaveMsg" class="d-none"></div>
            </div>
          </div>
        </div>

      </div>

      {{-- Modal Footer --}}
      <div class="modal-footer" style="background:#f8fafc;">
        <button type="button" class="btn btn-sm btn-outline-secondary" id="btnPrintCutPlan">
          <i class="fa-solid fa-print me-1"></i> Print / Export PDF
        </button>
        <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Close</button>
      </div>

    </div>
  </div>
</div>

{{-- Print-only styles --}}
<style>
@media print {
  body > *:not(#cutOptPrintArea) { display: none !important; }
  #cutOptPrintArea { display: block !important; position: static !important; }
  .modal-backdrop { display:none !important; }
  .no-print { display:none !important; }
}
.cut-pattern-bar {
  display: inline-flex; height: 24px; border-radius: 4px; overflow: hidden;
  width: 100%; max-width: 320px; background:#e2e8f0; border: 1px solid #cbd5e1;
}
.cut-segment { display:inline-flex; align-items:center; justify-content:center; font-size:10px; font-weight:700; color:#fff; }
.cut-waste { display:inline-flex; align-items:center; justify-content:center; font-size:10px; color:#94a3b8; background:#f1f5f9; flex:1; }
</style>

@endsection

@push('scripts')
<script>
// ─────────────────────────────────────────────────────────────────────────
// CUT OPTIMIZATION MODAL LOGIC
// ─────────────────────────────────────────────────────────────────────────
(function() {
    const CSRF   = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
    const COLORS = ['#c2410c','#0369a1','#047857','#7c3aed','#b45309','#be185d','#0f766e','#1d4ed8'];

    function fmt(n, d = 2) { return Number(n).toFixed(d); }

    // Trigger cut optimization on button click
    document.addEventListener('click', function(e) {
        const btn = e.target.closest('.btnRebarCutOptimizeTrigger, #btnRebarCutOptimize');
        if (!btn) return;

        const url    = btn.dataset.url;
        const erpUrl = btn.dataset.erpUrl;

        // Reset modal state
        document.getElementById('cutOptLoading').style.display = '';
        document.getElementById('cutOptBody').style.display    = 'none';
        document.getElementById('cutOptSummaryCards').innerHTML = '';
        document.getElementById('cutOptDiaTabs').innerHTML      = '';
        const msgBox = document.getElementById('erpSaveMsg');
        msgBox.className = 'd-none';
        msgBox.innerHTML = '';

        // Store ERP url on save button
        document.getElementById('btnSaveErpPlan').dataset.erpUrl  = erpUrl;
        document.getElementById('btnSaveErpPlan').dataset.results = '';

        // Show modal
        const modal = new bootstrap.Modal(document.getElementById('cutOptModal'));
        modal.show();

        // POST to cut-optimize endpoint
        fetch(url, {
            method:  'POST',
            headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json', 'Content-Type': 'application/json' },
            body:    JSON.stringify({}),
        })
        .then(r => {
            if (!r.ok) return r.json().then(d => { throw new Error(d.error || r.statusText); });
            return r.json();
        })
        .then(data => {
            if (data.error) throw new Error(data.error);
            renderCutOptResult(data);
        })
        .catch(err => {
            document.getElementById('cutOptLoading').style.display = 'none';
            document.getElementById('cutOptBody').style.display    = '';
            document.getElementById('cutOptDiaTabs').innerHTML =
                `<div class="alert alert-danger"><i class="fa-solid fa-circle-exclamation me-2"></i>${err.message}</div>`;
        });
    });

    // ── Render results ─────────────────────────────────────────────────
    function renderCutOptResult(data) {
        const results        = data.results || [];
        const sectionResults = data.section_results || [];

        document.getElementById('cutOptLoading').style.display = 'none';
        document.getElementById('cutOptBody').style.display    = '';

        const cards = document.getElementById('cutOptSummaryCards');
        const tabs  = document.getElementById('cutOptDiaTabs');

        // Store results for save form
        document.getElementById('btnSaveErpPlan').dataset.results = JSON.stringify(results);

        // ── Grand Summary Cards ──────────────────────────────────────────
        let grandBars = 0, grandWasteKg = 0, grandWeightKg = 0;
        let cardsHtml = '';

        results.forEach(r => {
            const bars   = r.result.total_bars;
            const waste  = r.result.total_waste;
            const wpm    = r.weight_per_meter;
            const wkg    = parseFloat(waste) * wpm;
            grandBars     += bars;
            grandWasteKg  += wkg;
            grandWeightKg += r.total_weight_kg;

            const util = (bars > 0 && data.bar_length > 0)
                ? (r.result.total_length_used / (bars * data.bar_length) * 100).toFixed(1)
                : '0.0';
            const utilColor = parseFloat(util) >= 85 ? '#16a34a' : parseFloat(util) >= 70 ? '#d97706' : '#dc2626';

            cardsHtml += `
            <div style="background:#fff;border:1px solid #e2e8f0;border-radius:10px;padding:12px 16px;min-width:145px;box-shadow:0 1px 4px rgba(0,0,0,.06);">
                <div style="font-size:1.05rem;font-weight:700;color:#c2410c;">Ø${r.dia}mm</div>
                <div style="color:#64748b;font-size:11px;">Diameter</div>
                <div style="font-size:1.3rem;font-weight:700;margin-top:4px;">${bars} <span style="font-size:11px;font-weight:400;color:#64748b;">bars</span></div>
                <div style="font-size:11px;color:#475569;">${fmt(r.total_weight_kg)} kg total</div>
                <div style="margin-top:4px;"><span style="background:${utilColor};color:#fff;padding:2px 7px;border-radius:20px;font-size:10.5px;font-weight:600;">${util}% used</span></div>
            </div>`;
        });

        cardsHtml += `
        <div style="background:#1e293b;border:1px solid #334155;border-radius:10px;padding:12px 16px;min-width:160px;color:#fff;margin-left:auto;">
            <div style="font-size:1.05rem;font-weight:700;color:#fb923c;"><i class="fa-solid fa-calculator me-1"></i> GRAND TOTAL</div>
            <div style="font-size:1.3rem;font-weight:700;margin-top:4px;">${grandBars} <span style="font-size:11px;font-weight:400;opacity:.7;">bars</span></div>
            <div style="font-size:11px;opacity:.8;">${fmt(grandWeightKg)} kg rebar</div>
            <div style="font-size:11px;color:#f87171;">Waste: ${fmt(grandWasteKg)} kg</div>
        </div>`;
        cards.innerHTML = cardsHtml;

        // ── Build Main Content Tabs: By Section vs Overall Summary ──────
        let mainContentHtml = `
        <ul class="nav nav-pills mb-4 gap-2" id="cutOptViewModeTabs">
            <li class="nav-item">
                <button class="nav-link active fw-bold btn-sm shadow-sm" data-bs-toggle="pill" data-bs-target="#viewBySection">
                    <i class="fa-solid fa-layer-group me-1"></i> By Section (${sectionResults.length})
                </button>
            </li>
            <li class="nav-item">
                <button class="nav-link fw-bold btn-sm shadow-sm" data-bs-toggle="pill" data-bs-target="#viewOverall">
                    <i class="fa-solid fa-chart-pie me-1"></i> Overall Summary
                </button>
            </li>
        </ul>

        <div class="tab-content">
            <!-- ── VIEW 1: BY SECTION ── -->
            <div class="tab-pane fade show active" id="viewBySection">`;

        if (sectionResults.length === 0) {
            mainContentHtml += '<div class="alert alert-warning">No section data found in this sheet.</div>';
        } else {
            sectionResults.forEach((sec, sIdx) => {
                let secTotalBars = 0, secTotalWeightKg = 0;
                (sec.results || []).forEach(sr => {
                    secTotalBars += sr.result.total_bars || 0;
                    secTotalWeightKg += sr.total_weight_kg || 0;
                });

                mainContentHtml += `
                <div class="card mb-4 shadow-sm" style="border-left:5px solid #c2410c; border-radius:10px; overflow:hidden;">
                    <div class="card-header bg-white d-flex align-items-center justify-content-between py-2 px-3">
                        <div class="d-flex align-items-center gap-2">
                            <i class="fa-solid fa-folder-tree text-danger fs-5"></i>
                            <h6 class="mb-0 fw-bold" style="color:#9a3412; font-size:1.05rem;">
                                ${sec.section_name}
                            </h6>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <span class="badge bg-secondary">${secTotalBars} bars (12m)</span>
                            <span class="badge" style="background:#c2410c;">${fmt(secTotalWeightKg)} kg</span>
                        </div>
                    </div>
                    <div class="card-body p-3">`;

                // Render diameter tables inside this section
                sec.results.forEach((r, rIdx) => {
                    const di = r.dia;
                    const patterns = r.result.patterns || [];
                    const wpm = r.weight_per_meter;
                    const totalBars = r.result.total_bars;
                    const totalWaste = r.result.total_waste;

                    let rows = '';
                    patterns.forEach((p, pi) => {
                        let barViz = '<div class="cut-pattern-bar">';
                        (p.cuts || []).forEach((cut, ci) => {
                            const pct = ((cut.length * cut.qty) / data.bar_length * 100).toFixed(1);
                            const col = COLORS[ci % COLORS.length];
                            barViz += `<div class="cut-segment" style="width:${pct}%;background:${col};" title="${cut.qty}× ${cut.length}m">${cut.qty}×${cut.length}m</div>`;
                        });
                        if (p.waste > 0.001) {
                            const wpct = (p.waste / data.bar_length * 100).toFixed(1);
                            barViz += `<div class="cut-waste" style="width:${wpct}%;">${fmt(p.waste)}m waste</div>`;
                        }
                        barViz += '</div>';

                        const cutsDetail = (p.cuts || []).map((c, ci) =>
                            `<span class="badge me-1" style="background:${COLORS[ci % COLORS.length]};">${c.qty}× ${c.length}m</span>`
                        ).join('');

                        const utilColor = p.utilization >= 85 ? '#16a34a' : p.utilization >= 70 ? '#d97706' : '#dc2626';

                        rows += `
                        <tr class="text-center">
                            <td class="fw-semibold text-muted small">#${pi + 1}</td>
                            <td class="text-start" style="min-width:220px;">${barViz}</td>
                            <td class="text-start" style="min-width:180px;">${cutsDetail}</td>
                            <td class="fw-bold text-danger">${fmt(p.waste)}m<br><small class="text-muted">${fmt(p.waste * wpm, 3)} kg</small></td>
                            <td><span class="badge" style="background:${utilColor};">${p.utilization}%</span></td>
                            <td class="fw-bold fs-6">${p.bars_used}</td>
                            <td class="text-muted small">${fmt(p.bars_used * p.waste)}m<br>${fmt(p.bars_used * p.waste * wpm, 3)} kg</td>
                        </tr>`;
                    });

                    // Render offcut reuse banner if pieces were fulfilled from offcut pool
                    let offcutBannerHtml = '';
                    const offcuts = r.offcut_fulfilled || [];
                    if (offcuts.length > 0) {
                        offcutBannerHtml = `<div class="alert alert-success py-1 px-2 mb-2 small d-flex align-items-center gap-2" style="font-size:11.5px; background:#dcfce7; border-color:#86efac; color:#166534;">
                            <i class="fa-solid fa-recycle text-success fs-6"></i>
                            <div>
                                <strong>Reused from Offcuts/Wastage:</strong> `;
                        offcuts.forEach(o => {
                            offcutBannerHtml += `<span class="badge bg-success me-1">${o.qty}× ${o.length}m</span> `;
                        });
                        offcutBannerHtml += `<span class="text-muted">(0 new 12m bars needed for these pieces!)</span>
                            </div>
                        </div>`;
                    }

                    mainContentHtml += `
                    <div class="mb-3 p-3 rounded" style="background:#f8fafc; border:1px solid #e2e8f0;">
                        <div class="d-flex align-items-center gap-2 mb-2 flex-wrap">
                            <span class="fw-bold text-dark" style="font-size:0.95rem;">Ø${di}mm</span>
                            <span class="text-muted small">— ${totalBars} new bars needed</span>
                            <span class="badge bg-light text-dark border ms-auto">${fmt(r.total_weight_kg)} kg new bar weight</span>
                            <span class="badge bg-danger">Waste: ${fmt(totalWaste)}m (${fmt(totalWaste * wpm, 2)} kg)</span>
                        </div>
                        ${offcutBannerHtml}
                        <div class="table-responsive">
                        <table class="table table-bordered table-sm align-middle mb-0" style="font-size:12px; background:#fff;">
                            <thead style="background:#1e293b; color:#fff;">
                                <tr class="text-center">
                                    <th style="width:35px;">#</th>
                                    <th style="min-width:200px;">Visual Cut Plan</th>
                                    <th>Pieces per Bar</th>
                                    <th style="width:100px;">Waste / Bar</th>
                                    <th style="width:80px;">Used %</th>
                                    <th style="width:75px;">Bars</th>
                                    <th style="width:110px;">Total Waste</th>
                                </tr>
                            </thead>
                            <tbody>${rows}</tbody>
                        </table>
                        </div>
                    </div>`;
                });

                mainContentHtml += `</div></div>`;
            });
        }

        mainContentHtml += `</div>
            <!-- ── VIEW 2: OVERALL SUMMARY ── -->
            <div class="tab-pane fade" id="viewOverall">`;

        // Render overall diameter tabs
        let overallHtml = '';
        if (results.length > 1) {
            overallHtml += '<ul class="nav nav-tabs mb-3">';
            results.forEach((r, i) => {
                overallHtml += `<li class="nav-item"><a class="nav-link${i === 0 ? ' active' : ''}" data-bs-toggle="tab" href="#ovDiaTab${r.dia}">Ø${r.dia}mm <span class="badge bg-secondary ms-1">${r.result.total_bars} bars</span></a></li>`;
            });
            overallHtml += '</ul><div class="tab-content">';
        } else {
            overallHtml += '<div>';
        }

        results.forEach((r, idx) => {
            const di       = r.dia;
            const patterns = r.result.patterns || [];
            const wpm      = r.weight_per_meter;
            const totalBars  = r.result.total_bars;
            const totalWaste = r.result.total_waste;

            let rows = '';
            patterns.forEach((p, pi) => {
                let barViz = '<div class="cut-pattern-bar">';
                (p.cuts || []).forEach((cut, ci) => {
                    const pct = ((cut.length * cut.qty) / data.bar_length * 100).toFixed(1);
                    const col = COLORS[ci % COLORS.length];
                    barViz += `<div class="cut-segment" style="width:${pct}%;background:${col};" title="${cut.qty}× ${cut.length}m">${cut.qty}×${cut.length}m</div>`;
                });
                if (p.waste > 0.001) {
                    const wpct = (p.waste / data.bar_length * 100).toFixed(1);
                    barViz += `<div class="cut-waste" style="width:${wpct}%;">${fmt(p.waste)}m waste</div>`;
                }
                barViz += '</div>';

                const cutsDetail = (p.cuts || []).map((c, ci) =>
                    `<span class="badge me-1" style="background:${COLORS[ci % COLORS.length]};">${c.qty}× ${c.length}m</span>`
                ).join('');

                const utilColor = p.utilization >= 85 ? '#16a34a' : p.utilization >= 70 ? '#d97706' : '#dc2626';

                rows += `
                <tr class="text-center">
                    <td class="fw-semibold text-muted small">#${pi + 1}</td>
                    <td class="text-start" style="min-width:220px;">${barViz}</td>
                    <td class="text-start" style="min-width:180px;">${cutsDetail}</td>
                    <td class="fw-bold text-danger">${fmt(p.waste)}m<br><small class="text-muted">${fmt(p.waste * wpm, 3)} kg</small></td>
                    <td><span class="badge" style="background:${utilColor};">${p.utilization}%</span></td>
                    <td class="fw-bold fs-6">${p.bars_used}</td>
                    <td class="text-muted small">${fmt(p.bars_used * p.waste)}m<br>${fmt(p.bars_used * p.waste * wpm, 3)} kg</td>
                </tr>`;
            });

            overallHtml += `
            <div class="tab-pane${idx === 0 ? ' show active' : ''}" id="ovDiaTab${di}">
                <div class="d-flex align-items-center gap-3 mb-3 flex-wrap">
                    <div>
                        <span class="fw-bold" style="color:#c2410c;font-size:1.1rem;">Ø${di}mm</span>
                        <span class="text-muted small ms-2">— ${totalBars} bars of 12m needed</span>
                    </div>
                    <span class="badge bg-secondary">${fmt(r.total_weight_kg)} kg total</span>
                    <span class="badge" style="background:#dc2626;">Waste: ${fmt(totalWaste)}m / ${fmt(totalWaste * wpm, 3)} kg</span>
                </div>
                <div class="table-responsive">
                <table class="table table-bordered table-sm align-middle mb-0" style="font-size:12.5px;">
                    <thead style="background:#1e293b;color:#fff;">
                        <tr>
                            <th style="width:42px;">#</th>
                            <th style="min-width:220px;">Visual Cut Plan</th>
                            <th style="min-width:180px;">Pieces per Bar</th>
                            <th style="width:110px;">Waste / Bar</th>
                            <th style="width:85px;">Used %</th>
                            <th style="width:80px;">Bars</th>
                            <th style="width:120px;">Total Waste</th>
                        </tr>
                    </thead>
                    <tbody>${rows}</tbody>
                </table>
                </div>
            </div>`;
        });

        overallHtml += '</div>';
        mainContentHtml += overallHtml + `</div></div>`;

        tabs.innerHTML = mainContentHtml;
    }

    // ── Save to ERP Plan ────────────────────────────────────────────────
    document.getElementById('btnSaveErpPlan').addEventListener('click', function() {
        const btn        = this;
        const erpUrl     = btn.dataset.erpUrl;
        const resultsRaw = btn.dataset.results;
        const planName   = document.getElementById('erpPlanName').value.trim();
        const startDate  = document.getElementById('erpStartDate').value;
        const endDate    = document.getElementById('erpEndDate').value;
        const msgBox     = document.getElementById('erpSaveMsg');

        if (!planName)             { alert('Please enter a plan name.');    return; }
        if (!startDate)            { alert('Please enter a start date.');   return; }
        if (!endDate)              { alert('Please enter an end date.');    return; }
        if (!resultsRaw || resultsRaw === '[]') { alert('Run optimization first.'); return; }

        btn.disabled    = true;
        btn.innerHTML   = '<i class="fa-solid fa-spinner fa-spin me-1"></i> Saving…';
        msgBox.className = 'd-none';

        fetch(erpUrl, {
            method:  'POST',
            headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json', 'Content-Type': 'application/json' },
            body:    JSON.stringify({
                plan_name:       planName,
                plan_start_date: startDate,
                plan_end_date:   endDate,
                results:         JSON.parse(resultsRaw),
            }),
        })
        .then(r => r.json())
        .then(res => {
            btn.disabled  = false;
            btn.innerHTML = '<i class="fa-solid fa-check me-1"></i> Save Plan';
            if (res.success) {
                msgBox.className = 'alert alert-success py-2 small';
                msgBox.innerHTML = `<i class="fa-solid fa-circle-check me-2"></i>${res.message} &nbsp;
                    <a href="${res.plan_url}" class="alert-link fw-bold" target="_blank">View ERP Plan →</a>`;
            } else {
                msgBox.className = 'alert alert-danger py-2 small';
                msgBox.innerHTML = res.message ?? 'An error occurred.';
            }
        })
        .catch(err => {
            btn.disabled  = false;
            btn.innerHTML = '<i class="fa-solid fa-check me-1"></i> Save Plan';
            msgBox.className = 'alert alert-danger py-2 small';
            msgBox.innerHTML = 'Request failed: ' + err;
        });
    });

    // ── Print ────────────────────────────────────────────────────────────
    document.getElementById('btnPrintCutPlan').addEventListener('click', function() {
        window.print();
    });
})();
</script>
@endpush
