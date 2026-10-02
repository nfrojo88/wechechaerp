@extends('layouts.app')
@section('title', 'Edit Daily Manpower Sheet')
@section('content')
<div class="container-fluid">
    {{-- Header --}}
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 pb-3 border-bottom gap-2">
        <div>
            <div class="d-flex align-items-center gap-2">
                <a href="{{ route('manpower-approval.site-engineer.index') }}" class="btn btn-sm btn-outline-secondary">
                    <i class="fa-solid fa-arrow-left"></i>
                </a>
                <h4 class="fw-bold text-dark mb-0">
                    <i class="fa-solid fa-file-pen text-primary me-2"></i>Edit Manpower Sheet: <span class="font-monospace text-primary">{{ $sheet->sheet_number }}</span>
                </h4>
            </div>
            <p class="text-muted small mb-0 mt-1">
                Edit roster lines, review reviewer feedback, and resubmit for approval.
            </p>
        </div>
    </div>

    {{-- Highlighted Rejection Box if Returned --}}
    @if($sheet->status === 'Rejected')
    <div class="alert alert-danger border-0 shadow-xs rounded-3 p-3 mb-3">
        <div class="d-flex align-items-start gap-2">
            <i class="fa-solid fa-circle-exclamation text-danger fs-4 mt-0.5"></i>
            <div>
                <strong class="text-danger d-block">Returned by {{ ucwords(str_replace('_', ' ', $sheet->rejected_by_stage)) }}</strong>
                <div class="fw-bold text-dark mt-1">Reason: <span class="badge bg-danger">{{ $sheet->rejection_reason }}</span></div>
                <div class="text-muted small mt-1">{{ $sheet->rejection_comment }}</div>
            </div>
        </div>
    </div>
    @endif

    <form action="{{ route('manpower-approval.site-engineer.update', $sheet->id) }}" method="POST" id="sheetForm">
        @csrf
        @method('PUT')
        <input type="hidden" name="action" id="formActionInput" value="save_draft">

        {{-- Sheet Header Card --}}
        <div class="card border-0 shadow-xs rounded-3 mb-3 bg-white">
            <div class="card-header bg-white py-2 px-3 border-bottom">
                <strong class="text-dark small"><i class="fa-solid fa-circle-info text-primary me-1"></i>Sheet Metadata</strong>
            </div>
            <div class="card-body p-3">
                <div class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label small fw-bold text-dark mb-1">Date</label>
                        <input type="date" class="form-control form-control-sm" value="{{ $sheet->date->toDateString() }}" readonly style="background-color: #f8f9fa;">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-bold text-dark mb-1">Project / Site</label>
                        <input type="text" class="form-control form-control-sm" value="{{ $sheet->project?->name }}" readonly style="background-color: #f8f9fa;">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-bold text-dark mb-1">Trade Category</label>
                        <input type="text" name="trade" class="form-control form-control-sm" value="{{ old('trade', $sheet->trade) }}">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-bold text-dark mb-1">Gang / Subcontractor</label>
                        <input type="text" name="gang_subcontractor" class="form-control form-control-sm" value="{{ old('gang_subcontractor', $sheet->gang_subcontractor) }}">
                    </div>
                </div>
            </div>
        </div>

        {{-- Worker Lines Card --}}
        <div class="card border-0 shadow-xs rounded-3 mb-3 bg-white">
            <div class="card-header bg-white py-2 px-3 border-bottom d-flex flex-wrap justify-content-between align-items-center gap-2">
                <div class="d-flex align-items-center gap-2">
                    <strong class="text-dark small"><i class="fa-solid fa-users text-primary me-1"></i>Labor Attendance Roster</strong>
                    <span class="badge bg-light text-dark border font-monospace" id="rosterCountBadge">{{ $sheet->lines->count() }} Workers</span>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <select id="workerPickerSelect" class="form-select form-select-sm" style="min-width: 250px;">
                        <option value="">+ Add worker from master...</option>
                        @foreach($workers as $w)
                        <option value="{{ $w->id }}" data-name="{{ $w->name }}" data-code="{{ $w->worker_code }}" data-rate="{{ $w->daily_rate }}" data-trade="{{ $w->trade }}" data-phone="{{ $w->phone }}">
                            {{ $w->name }} ({{ $w->trade ?: 'Laborer' }} &bull; Rate: ETB {{ number_format($w->daily_rate, 2) }})
                        </option>
                        @endforeach
                    </select>
                    <button type="button" class="btn btn-sm btn-success shadow-xs flex-shrink-0" onclick="addSelectedWorkerToRoster()">
                        <i class="fa-solid fa-plus me-1"></i>Add to Roster
                    </button>
                </div>
            </div>

            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-bordered align-middle mb-0 small text-center" id="rosterTable">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 40px;">#</th>
                                <th class="text-start" style="min-width: 170px;">Worker Name &amp; Code</th>
                                <th style="min-width: 100px;">Trade</th>
                                <th style="width: 110px;">Check In</th>
                                <th style="width: 110px;">Check Out</th>
                                <th style="width: 80px;">Reg. Hours</th>
                                <th style="width: 80px;">OT Hours</th>
                                <th style="width: 110px;">Daily Rate (ETB)</th>
                                <th style="width: 120px;">Amount (ETB)</th>
                                <th style="min-width: 120px;">Attendance Status</th>
                                <th style="min-width: 140px;">Remarks / Note</th>
                                <th style="width: 50px;"></th>
                            </tr>
                        </thead>
                        <tbody id="rosterTbody">
                            @foreach($sheet->lines as $idx => $line)
                            <tr id="rosterRow_{{ $line->worker_id }}">
                                <td class="font-monospace text-muted">{{ $idx + 1 }}</td>
                                <td class="text-start">
                                    <input type="hidden" name="lines[{{ $idx }}][worker_id]" value="{{ $line->worker_id }}">
                                    <strong class="text-dark d-block">{{ $line->worker?->name }}</strong>
                                    <span class="font-monospace text-primary small" style="font-size: 0.68rem;">{{ $line->worker?->worker_code }}</span>
                                </td>
                                <td>
                                    <input type="text" class="form-control form-control-sm text-center" value="{{ $line->worker?->trade }}" readonly style="background-color: #f8f9fa;">
                                </td>
                                <td>
                                    <input type="time" name="lines[{{ $idx }}][check_in]" class="form-control form-control-sm text-center" value="{{ $line->check_in }}">
                                </td>
                                <td>
                                    <input type="time" name="lines[{{ $idx }}][check_out]" class="form-control form-control-sm text-center" value="{{ $line->check_out }}">
                                </td>
                                <td>
                                    <input type="number" step="0.5" min="0" max="24" name="lines[{{ $idx }}][regular_hours]" class="form-control form-control-sm text-center reg-hours-input" value="{{ (float)$line->regular_hours }}" onchange="recalcRow({{ $idx }})" required>
                                </td>
                                <td>
                                    <input type="number" step="0.5" min="0" max="24" name="lines[{{ $idx }}][overtime_hours]" class="form-control form-control-sm text-center ot-hours-input" value="{{ (float)$line->overtime_hours }}" onchange="recalcRow({{ $idx }})">
                                </td>
                                <td>
                                    <input type="number" step="0.01" min="0" name="lines[{{ $idx }}][daily_rate]" class="form-control form-control-sm text-center rate-input" value="{{ (float)$line->daily_rate }}" onchange="recalcRow({{ $idx }})" required>
                                </td>
                                <td>
                                    <input type="number" step="0.01" min="0" name="lines[{{ $idx }}][amount]" class="form-control form-control-sm text-center fw-bold font-monospace amount-input" value="{{ (float)$line->amount }}" readonly style="background-color: #f0fdf4; color: #166534;">
                                    @if($line->adjusted_amount !== null)
                                    <div class="text-danger small font-monospace" style="font-size: 0.68rem;" title="Adjusted by approver">
                                        Adj: ETB {{ number_format($line->adjusted_amount, 2) }}
                                    </div>
                                    @endif
                                </td>
                                <td>
                                    <select name="lines[{{ $idx }}][attendance_status]" class="form-select form-select-sm text-center status-select">
                                        <option value="present" {{ $line->attendance_status === 'present' ? 'selected' : '' }}>Present</option>
                                        <option value="absent" {{ $line->attendance_status === 'absent' ? 'selected' : '' }}>Absent</option>
                                        <option value="unrostered" {{ $line->attendance_status === 'unrostered' ? 'selected' : '' }}>Unrostered Punch</option>
                                    </select>
                                </td>
                                <td>
                                    <input type="text" name="lines[{{ $idx }}][remark]" class="form-control form-control-sm" placeholder="Remarks..." value="{{ $line->remark }}">
                                </td>
                                <td class="text-center">
                                    <button type="button" class="btn btn-xs btn-outline-danger" onclick="removeRosterRow('{{ $line->worker_id }}')" title="Remove worker">
                                        <i class="fa-solid fa-trash"></i>
                                    </button>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                        <tfoot class="table-light fw-bold font-monospace">
                            <tr>
                                <td colspan="5" class="text-end">TOTALS:</td>
                                <td id="footRegHours">{{ (float)$sheet->total_regular_hours }}h</td>
                                <td id="footOtHours">{{ (float)$sheet->total_overtime_hours }}h</td>
                                <td>—</td>
                                <td class="text-success fs-6" id="footTotalAmount">ETB {{ number_format($sheet->total_amount, 2) }}</td>
                                <td colspan="3"></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>

        {{-- Notes and Actions --}}
        <div class="card border-0 shadow-xs rounded-3 mb-4 bg-white">
            <div class="card-body p-3">
                <div class="mb-3">
                    <label class="form-label small fw-bold text-dark mb-1">Site Engineer Remarks / Resubmission Note</label>
                    <textarea name="notes" rows="2" class="form-control form-control-sm" placeholder="Explain adjustments or remarks made in response to reviewer feedback...">{{ old('notes', $sheet->notes) }}</textarea>
                </div>

                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 pt-2 border-top">
                    <a href="{{ route('manpower-approval.site-engineer.index') }}" class="btn btn-sm btn-outline-secondary">
                        Cancel
                    </a>
                    <div class="d-flex align-items-center gap-2">
                        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="submitFormWithAction('save_draft')">
                            <i class="fa-solid fa-floppy-disk me-1"></i>Save Changes
                        </button>
                        <button type="button" class="btn btn-sm btn-primary shadow-xs" onclick="submitFormWithAction('submit')">
                            <i class="fa-solid fa-paper-plane me-1"></i>Resubmit to Planning Manager
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

<script>
let rosterIndex = {{ $sheet->lines->count() + 10 }};

function submitFormWithAction(action) {
    const tableBody = document.getElementById('rosterTbody');
    if (action === 'submit' && tableBody.children.length === 0) {
        alert('Please add at least one worker to the roster before submitting.');
        return;
    }
    document.getElementById('formActionInput').value = action;
    document.getElementById('sheetForm').submit();
}

function addSelectedWorkerToRoster() {
    const select = document.getElementById('workerPickerSelect');
    const opt = select.options[select.selectedIndex];
    if (!opt || !opt.value) return;

    const workerId = opt.value;
    const name = opt.getAttribute('data-name');
    const code = opt.getAttribute('data-code') || 'WRK';
    const rate = opt.getAttribute('data-rate') || 0;
    const trade = opt.getAttribute('data-trade') || 'General';

    const existing = document.querySelector(`input[name*="[worker_id]"][value="${workerId}"]`);
    if (existing) {
        alert('This worker is already in the roster.');
        return;
    }

    appendWorkerRow({
        workerId: workerId,
        name: name,
        code: code,
        trade: trade,
        dailyRate: rate,
        checkIn: '08:00',
        checkOut: '17:00',
        regularHours: 8.0,
        overtimeHours: 0,
        attendanceStatus: 'present',
        source: 'manual',
        remark: ''
    });

    select.selectedIndex = 0;
}

function appendWorkerRow(data) {
    const tbody = document.getElementById('rosterTbody');
    const idx = rosterIndex++;

    const regH = parseFloat(data.regularHours) || 8.0;
    const otH = parseFloat(data.overtimeHours) || 0;
    const rate = parseFloat(data.dailyRate) || 0;
    const initialAmount = (rate * (regH / 8.0)) + (rate / 8.0 * otH * 1.25);

    const tr = document.createElement('tr');
    tr.id = `rosterRow_${data.workerId}`;
    tr.innerHTML = `
        <td class="font-monospace text-muted">${tbody.children.length + 1}</td>
        <td class="text-start">
            <input type="hidden" name="lines[${idx}][worker_id]" value="${data.workerId}">
            <strong class="text-dark d-block">${data.name}</strong>
            <span class="font-monospace text-primary small" style="font-size: 0.68rem;">${data.code}</span>
        </td>
        <td>
            <input type="text" class="form-control form-control-sm text-center" value="${data.trade}" readonly style="background-color: #f8f9fa;">
        </td>
        <td>
            <input type="time" name="lines[${idx}][check_in]" class="form-control form-control-sm text-center" value="${data.checkIn}">
        </td>
        <td>
            <input type="time" name="lines[${idx}][check_out]" class="form-control form-control-sm text-center" value="${data.checkOut}">
        </td>
        <td>
            <input type="number" step="0.5" min="0" max="24" name="lines[${idx}][regular_hours]" class="form-control form-control-sm text-center reg-hours-input" value="${regH}" onchange="recalcRow(${idx})" required>
        </td>
        <td>
            <input type="number" step="0.5" min="0" max="24" name="lines[${idx}][overtime_hours]" class="form-control form-control-sm text-center ot-hours-input" value="${otH}" onchange="recalcRow(${idx})">
        </td>
        <td>
            <input type="number" step="0.01" min="0" name="lines[${idx}][daily_rate]" class="form-control form-control-sm text-center rate-input" value="${rate.toFixed(2)}" onchange="recalcRow(${idx})" required>
        </td>
        <td>
            <input type="number" step="0.01" min="0" name="lines[${idx}][amount]" class="form-control form-control-sm text-center fw-bold font-monospace amount-input" value="${initialAmount.toFixed(2)}" readonly style="background-color: #f0fdf4; color: #166534;">
        </td>
        <td>
            <select name="lines[${idx}][attendance_status]" class="form-select form-select-sm text-center status-select">
                <option value="present" ${data.attendanceStatus === 'present' ? 'selected' : ''}>Present</option>
                <option value="absent" ${data.attendanceStatus === 'absent' ? 'selected' : ''}>Absent</option>
                <option value="unrostered" ${data.attendanceStatus === 'unrostered' ? 'selected' : ''}>Unrostered Punch</option>
            </select>
        </td>
        <td>
            <input type="text" name="lines[${idx}][remark]" class="form-control form-control-sm" placeholder="Remarks..." value="${data.remark}">
        </td>
        <td class="text-center">
            <button type="button" class="btn btn-xs btn-outline-danger" onclick="removeRosterRow('${data.workerId}')" title="Remove worker">
                <i class="fa-solid fa-trash"></i>
            </button>
        </td>
    `;
    tbody.appendChild(tr);
    recalcSheetTotals();
    updateRosterCount();
}

function removeRosterRow(workerId) {
    const row = document.getElementById(`rosterRow_${workerId}`);
    if (row) {
        row.remove();
        recalcSheetTotals();
        updateRosterCount();
    }
}

function recalcRow(idx) {
    const row = document.querySelector(`input[name="lines[${idx}][worker_id]"]`)?.closest('tr');
    if (!row) return;

    const regH = parseFloat(row.querySelector('.reg-hours-input').value) || 0;
    const otH = parseFloat(row.querySelector('.ot-hours-input').value) || 0;
    const rate = parseFloat(row.querySelector('.rate-input').value) || 0;

    const amt = (rate * (regH / 8.0)) + (rate / 8.0 * otH * 1.25);
    row.querySelector('.amount-input').value = amt.toFixed(2);

    recalcSheetTotals();
}

function recalcSheetTotals() {
    let totReg = 0;
    let totOt = 0;
    let totAmt = 0;

    document.querySelectorAll('.reg-hours-input').forEach(el => totReg += parseFloat(el.value) || 0);
    document.querySelectorAll('.ot-hours-input').forEach(el => totOt += parseFloat(el.value) || 0);
    document.querySelectorAll('.amount-input').forEach(el => totAmt += parseFloat(el.value) || 0);

    document.getElementById('footRegHours').textContent = totReg.toFixed(1) + 'h';
    document.getElementById('footOtHours').textContent = totOt.toFixed(1) + 'h';
    document.getElementById('footTotalAmount').textContent = 'ETB ' + totAmt.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function updateRosterCount() {
    const count = document.getElementById('rosterTbody').children.length;
    document.getElementById('rosterCountBadge').textContent = `${count} Worker(s)`;
}
</script>
@endsection
