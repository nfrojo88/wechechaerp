@php
    $diameters    = [8, 10, 12, 14, 16, 20, 24, 32];
    $unitWeights  = [8=>0.395, 10=>0.617, 12=>0.889, 14=>1.210, 16=>1.580, 20=>2.469, 24=>3.550, 32=>6.313];
    $globalItemCounter        = 1;
    $grandTotalLengthPerDia   = array_fill_keys($diameters, 0);
@endphp

{{-- ── Card header ─────────────────────────────────────────────── --}}
<div class="card-header d-flex align-items-center gap-2 py-2" style="background:#fff;">
    <i class="fa-solid fa-bars-progress text-danger"></i>
    <span class="fw-bold">Rebar Schedule</span>
    <span class="badge bg-secondary ms-auto">{{ $takeoff->sections->count() }} section(s)</span>
    @if($canEdit)
        <a href="{{ route('rebar-products.index') }}" class="btn btn-sm btn-outline-secondary py-0 px-2 shadow-sm" title="Rebar Settings">
            <i class="fa-solid fa-gear"></i>
        </a>
    @endif
</div>

<div class="card-body p-0">
<div class="table-responsive">
<table class="table table-bordered align-middle mb-0" style="font-size:12.5px; min-width:1100px;">
    <thead>
        <tr style="background:#c2410c; color:#fff;">
            <th rowspan="2" class="align-middle text-center" style="width:160px;">LOCATION</th>
            <th rowspan="2" class="align-middle text-center" style="width:70px;">BAR DIA</th>
            <th rowspan="2" class="align-middle text-center" style="width:90px;">BAR LENGTH</th>
            <th rowspan="2" class="align-middle text-center" style="width:80px;">NO OF BAR</th>
            <th rowspan="2" class="align-middle text-center" style="width:95px;">NO OF MEMBER</th>
            <th rowspan="2" class="align-middle text-center" style="width:95px;">TOTAL NO OF BAR</th>
            <th colspan="{{ count($diameters) }}" class="text-center" style="border-bottom:1px solid rgba(255,255,255,.3);">
                TOTAL LENGTH PER DIA (m)
            </th>
            @if($canEdit)
                <th rowspan="2" class="align-middle text-center" style="width:80px;">ACTIONS</th>
            @endif
        </tr>
        <tr style="background:#9a3412; color:#fff;">
            @foreach($diameters as $dia)
                <th class="text-center" style="font-size:11px; padding:5px 6px;">DIA {{ $dia }}</th>
            @endforeach
        </tr>
    </thead>
    <tbody>
        @forelse($takeoff->sections as $section)
            @php
                $sectionBars         = 0;
                $sectionLengthPerDia = array_fill_keys($diameters, 0);
            @endphp

            {{-- ── Section header row ───────────────────────────────── --}}
            @if($section->name)
            <tr style="background:linear-gradient(90deg,#7c1d0c 0%,#9a3412 100%); color:#fff;">
                <td colspan="{{ 6 + count($diameters) + ($canEdit ? 1 : 0) }}"
                    style="padding:8px 14px; border-color:#b45309;">
                    <div style="display:flex; align-items:center; gap:10px;">
                        {{-- Name + task badge --}}
                        <i class="fa-solid fa-layer-group" style="opacity:.7; font-size:11px;"></i>
                        <span style="font-size:12px; font-weight:700; letter-spacing:.5px; text-transform:uppercase;">
                            {{ $section->name }}
                        </span>
                        @if($section->task)
                            <span style="font-size:10px; font-weight:600; background:rgba(255,255,255,.15);
                                         color:#fed7aa; border:1px solid rgba(255,255,255,.2);
                                         border-radius:999px; padding:2px 9px; letter-spacing:.3px;">
                                <i class="fa-solid fa-list-check me-1" style="font-size:9px;"></i>{{ $section->task->name }}
                            </span>
                        @endif

                        {{-- Delete button — always on the right --}}
                        @if($canEdit)
                        <form method="POST"
                              action="{{ route('takeoff.sections.destroy', [$takeoff, $section]) }}"
                              onsubmit="return confirm('Delete section \'{{ addslashes($section->name) }}\' and all its items?')"
                              style="margin-left:auto;">
                            @csrf @method('DELETE')
                            <button type="submit"
                                    style="display:inline-flex; align-items:center; gap:5px;
                                           background:rgba(239,68,68,.18); color:#fca5a5;
                                           border:1px solid rgba(239,68,68,.35); border-radius:5px;
                                           padding:3px 10px; font-size:11px; font-weight:600;
                                           cursor:pointer; transition:all .15s; white-space:nowrap;"
                                    onmouseover="this.style.background='rgba(239,68,68,.35)'"
                                    onmouseout="this.style.background='rgba(239,68,68,.18)'"
                                    title="Delete this section">
                                <i class="fa-solid fa-trash-can"></i> Delete Section
                            </button>
                        </form>
                        @endif
                    </div>
                </td>
            </tr>
            @endif

            {{-- ── Section items ───────────────────────────────────────── --}}
            @foreach($section->items as $item)
                @if($item->is_header)
                    <tr class="table-light">
                        <td colspan="{{ 6 + count($diameters) }}" class="fw-bold" style="color:#c2410c; border-left:3px solid #f97316; padding-left:12px;">
                            {{ $item->element }}
                        </td>
                    @if($canEdit)
                        <td>
                            <div class="actions-cell">
                                <form method="POST" action="{{ route('takeoff.items.toggle-header', [$takeoff, $item]) }}" class="d-inline">
                                    @csrf @method('PATCH')
                                    <button class="action-btn header" title="Toggle Header">H</button>
                                </form>
                                <button type="button" onclick="showInlineForm('{{ $section->id }}')" class="action-btn add" title="Add"><i class="fa-solid fa-plus"></i></button>
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
                        $dia          = $item->calculation_data['bar_dia'] ?? null;
                        $barLength    = floatval($item->calculation_data['bar_length'] ?? 0);
                        $noOfBar      = intval($item->calculation_data['no_of_bar'] ?? 0);
                        $noOfMember   = intval($item->count ?? 1);
                        $totalNoOfBar = $noOfBar * $noOfMember;
                        $totalLength  = $barLength * $totalNoOfBar;

                        $sectionBars += $totalNoOfBar;
                        if ($dia && in_array($dia, $diameters)) {
                            $sectionLengthPerDia[$dia]  += $totalLength;
                            $grandTotalLengthPerDia[$dia] += $totalLength;
                        }
                    @endphp

                    {{-- ── View row ── --}}
                    <tr class="text-center rebar-view-row" id="view-row-{{ $item->id }}">
                        <td class="fw-semibold text-start text-primary">{{ $item->element }}</td>
                        <td class="fw-bold">{{ $dia ?? '—' }} <span class="text-muted small">ø</span></td>
                        <td>{{ $barLength > 0 ? $barLength : '—' }}</td>
                        <td>{{ $noOfBar }}</td>
                        <td>{{ $noOfMember }}</td>
                        <td class="fw-semibold">{{ $totalNoOfBar }}</td>
                        @foreach($diameters as $d)
                            <td>{{ ($dia == $d && $totalLength > 0) ? number_format($totalLength, 2) : '' }}</td>
                        @endforeach
                        @if($canEdit)
                        <td>
                            <div class="actions-cell">
                                <button type="button" onclick="showEditRow('{{ $item->id }}')" class="action-btn edit" title="Edit">
                                    <i class="fa-solid fa-pen-to-square"></i>
                                </button>
                                <form method="POST" action="{{ route('takeoff.items.toggle-header', [$takeoff, $item]) }}" class="d-inline">
                                    @csrf @method('PATCH')
                                    <button class="action-btn header" title="Make Header">H</button>
                                </form>
                                <button type="button" onclick="showInlineForm('{{ $section->id }}')" class="action-btn add" title="Add"><i class="fa-solid fa-plus"></i></button>
                                <form method="POST" action="{{ route('takeoff.items.destroy', [$takeoff, $item]) }}" class="d-inline" onsubmit="return confirm('Delete this item?')">
                                    @csrf @method('DELETE')
                                    <button class="action-btn del" title="Delete"><i class="fa-solid fa-xmark"></i></button>
                                </form>
                            </div>
                        </td>
                        @endif
                    </tr>

                    {{-- ── Inline edit row (hidden by default) ── --}}
                    @if($canEdit)
                    <tr class="rebar-edit-row d-none" id="edit-row-{{ $item->id }}" style="background:#eff6ff;">
                        <form method="POST" action="{{ route('takeoff.items.update', [$takeoff, $item]) }}">
                            @csrf @method('PATCH')
                            <td style="padding:5px;">
                                <input type="text" name="element"
                                       value="{{ $item->element }}"
                                       placeholder="Location..."
                                       required
                                       style="width:100%; padding:4px 6px; border:1px solid #93c5fd; border-radius:4px; font-size:12px; background:#fff;">
                            </td>
                            <td style="padding:5px;">
                                <select name="bar_dia"
                                        style="width:100%; border:1px solid #93c5fd; border-radius:4px; padding:4px 5px; font-size:12px;">
                                    <option value="">Dia</option>
                                    @foreach($diameters as $d)
                                        <option value="{{ $d }}" {{ (string)$dia === (string)$d ? 'selected' : '' }}>{{ $d }}</option>
                                    @endforeach
                                </select>
                            </td>
                            <td style="padding:5px;">
                                <input type="number" step="0.01" name="bar_length"
                                       value="{{ $barLength > 0 ? $barLength : '' }}"
                                       placeholder="Length"
                                       style="width:100%; padding:4px 6px; border:1px solid #93c5fd; border-radius:4px; font-size:12px; background:#fff;">
                            </td>
                            <td style="padding:5px;">
                                <input type="number" name="no_of_bar"
                                       value="{{ $noOfBar > 0 ? $noOfBar : '' }}"
                                       placeholder="No of Bar"
                                       style="width:100%; padding:4px 6px; border:1px solid #93c5fd; border-radius:4px; font-size:12px; background:#fff;">
                            </td>
                            <td style="padding:5px;">
                                <input type="number" name="count"
                                       value="{{ $noOfMember }}"
                                       placeholder="Members"
                                       min="1"
                                       style="width:100%; padding:4px 6px; border:1px solid #93c5fd; border-radius:4px; font-size:12px; background:#fff;">
                            </td>
                            <td></td>
                            <td colspan="{{ count($diameters) }}"></td>
                            <td style="text-align:center; padding:5px; white-space:nowrap;">
                                <button type="submit"
                                        class="btn btn-sm btn-primary py-0 px-2 fw-bold me-1"
                                        style="font-size:11px;">
                                    <i class="fa-solid fa-check me-1"></i>Update
                                </button>
                                <button type="button"
                                        onclick="hideEditRow('{{ $item->id }}')"
                                        class="btn btn-sm btn-outline-secondary border-0 py-0 px-1">✕</button>
                            </td>
                        </form>
                    </tr>
                    @endif

                @endif
            @endforeach

            {{-- ── Section subtotals ─────────────────────────────────── --}}
            <tr class="table-secondary text-center" style="font-size:11px; font-weight:600;">
                <td colspan="3"></td>
                <td colspan="2" class="text-end text-muted">SECTION BARS (pcs)</td>
                <td>{{ $sectionBars }}</td>
                <td colspan="{{ count($diameters) + ($canEdit ? 1 : 0) }}"></td>
            </tr>
            <tr class="table-secondary text-center" style="font-size:11px; font-weight:600;">
                <td colspan="5" class="text-end text-muted">SECTION LENGTH (m)</td>
                <td></td>
                @foreach($diameters as $d)
                    <td>{{ $sectionLengthPerDia[$d] > 0 ? number_format($sectionLengthPerDia[$d], 2) : '—' }}</td>
                @endforeach
                @if($canEdit)<td></td>@endif
            </tr>
            <tr class="table-secondary text-center" style="font-size:11px;">
                <td colspan="5" class="text-end text-muted">UNIT WEIGHT (kg/m)</td>
                <td></td>
                @foreach($diameters as $d)
                    <td class="text-muted">{{ number_format($unitWeights[$d], 3) }}</td>
                @endforeach
                @if($canEdit)<td></td>@endif
            </tr>
            <tr class="text-center" style="background:#fff7ed; font-weight:700; font-size:12px; color:#c2410c;">
                <td colspan="5" class="text-end">SECTION WEIGHT (kg)</td>
                <td></td>
                @foreach($diameters as $d)
                    <td>{{ $sectionLengthPerDia[$d] > 0 ? number_format($sectionLengthPerDia[$d] * $unitWeights[$d], 2) : '—' }}</td>
                @endforeach
                @if($canEdit)<td></td>@endif
            </tr>

            {{-- ── Inline Add Row ─────────────────────────────────────── --}}
            @if($canEdit)
            <tr class="add-row-trigger" id="trigger-{{ $section->id }}">
                <td colspan="{{ 7 + count($diameters) }}">
                    <button type="button" class="btn-add-inline" onclick="showInlineForm('{{ $section->id }}')"
                            style="border-color:#fdba74; color:#ea580c;">
                        <i class="fa-solid fa-plus me-1"></i> Add Rebar Item
                    </button>
                </td>
            </tr>
            <tr class="inline-add-row d-none" id="inline-form-{{ $section->id }}">
                <form action="{{ route('takeoff.items.store', $takeoff) }}" method="POST">
                @csrf
                <input type="hidden" name="takeoff_section_id" value="{{ $section->id }}">
                <input type="hidden" name="result_quantity" value="0">
                <input type="hidden" name="result_unit" value="kg">
                <td style="min-width:140px; padding:5px;">
                    <input type="text" name="element" placeholder="Location..." required
                           style="width:100%; padding:4px 6px; border:1px solid #d1d5db; border-radius:4px; font-size:12px;">
                </td>
                <td style="padding:5px;">
                    <select name="bar_dia" style="width:100%; border:1px solid #d1d5db; border-radius:4px; padding:4px 5px; font-size:12px;">
                        <option value="">Dia</option>
                        @foreach($diameters as $d)
                            <option value="{{ $d }}">{{ $d }}</option>
                        @endforeach
                    </select>
                </td>
                <td style="padding:5px;">
                    <input type="number" step="0.01" name="bar_length" placeholder="Length"
                           style="width:100%; padding:4px 6px; border:1px solid #d1d5db; border-radius:4px; font-size:12px;">
                </td>
                <td style="padding:5px;">
                    <input type="number" name="no_of_bar" placeholder="Qty"
                           style="width:100%; padding:4px 6px; border:1px solid #d1d5db; border-radius:4px; font-size:12px;">
                </td>
                <td style="padding:5px;">
                    <input type="number" name="count" value="1" placeholder="Members"
                           style="width:100%; padding:4px 6px; border:1px solid #d1d5db; border-radius:4px; font-size:12px;">
                </td>
                <td></td>
                <td colspan="{{ count($diameters) }}"></td>
                <td style="text-align:center; padding:5px; white-space:nowrap;">
                    <button type="submit" class="btn btn-sm btn-danger py-0 px-2 fw-bold me-1" style="font-size:11px;">Save</button>
                    <button type="button" onclick="hideInlineForm('{{ $section->id }}')"
                            class="btn btn-sm btn-outline-secondary border-0 py-0 px-1">✕</button>
                </td>
                </form>
            </tr>
            @endif

        @empty
            <tr>
                <td colspan="{{ 7 + count($diameters) }}" class="text-center py-5 text-muted">
                    <i class="fa-solid fa-ruler-combined fa-3x d-block mb-3" style="opacity:.2;"></i>
                    No sections found.
                    @if($canEdit)
                        <a href="#" data-bs-toggle="modal" data-bs-target="#addSectionModal" class="text-primary">Add a section</a> first.
                    @endif
                </td>
            </tr>
        @endforelse

        {{-- ── Grand Totals ─────────────────────────────────────────── --}}
        @if($takeoff->sections->count() > 0)
        <tr class="text-center" style="background:#1e293b; color:#fff; font-weight:700; font-size:12.5px;">
            <td colspan="5" class="text-end">TOTAL LENGTH PER DIA (m)</td>
            <td></td>
            @foreach($diameters as $d)
                <td>{{ $grandTotalLengthPerDia[$d] > 0 ? number_format($grandTotalLengthPerDia[$d], 2) : '—' }}</td>
            @endforeach
            @if($canEdit)<td></td>@endif
        </tr>
        <tr class="text-center" style="background:#334155; color:#cbd5e1; font-size:11px;">
            <td colspan="5" class="text-end">UNIT WEIGHT (kg/m)</td>
            <td></td>
            @foreach($diameters as $d)
                <td>{{ number_format($unitWeights[$d], 3) }}</td>
            @endforeach
            @if($canEdit)<td></td>@endif
        </tr>
        <tr class="text-center" style="background:#0f172a; color:#fff; font-weight:700; font-size:13.5px;">
            <td colspan="5" class="text-end" style="color:#fb923c;">GRAND TOTAL WEIGHT (kg)</td>
            <td></td>
            @php $grandTotalWeight = 0; @endphp
            @foreach($diameters as $d)
                @php $w = $grandTotalLengthPerDia[$d] * $unitWeights[$d]; $grandTotalWeight += $w; @endphp
                <td style="color:#fdba74;">{{ $w > 0 ? number_format($w, 2) : '—' }}</td>
            @endforeach
            @if($canEdit)<td></td>@endif
        </tr>
        @endif

    </tbody>
</table>
</div>
</div>

{{-- ── Add Section Modal (rebar) ────────────────────────────────── --}}
@if($canEdit)
<div class="card-footer d-flex align-items-center gap-2 bg-white border-top py-2">
    <button type="button" class="btn btn-sm btn-outline-secondary shadow-sm" data-bs-toggle="modal" data-bs-target="#addSectionModal">
        <i class="fa-solid fa-layer-group me-1"></i> Add Section
    </button>
    @if($takeoff->sections->flatMap->items->count() > 0)
        <button type="button" id="btnRebarCutOptimize"
                class="btn btn-sm btn-success shadow-sm ms-auto btnRebarCutOptimizeTrigger"
                data-url="{{ route('takeoff.rebar-cut-optimize', $takeoff) }}"
                data-erp-url="{{ route('takeoff.rebar-erp-convert', $takeoff) }}">
            <i class="fa-solid fa-scissors me-1"></i> Convert to Materials (Cut Optimization)
        </button>
    @endif
</div>
@endif
