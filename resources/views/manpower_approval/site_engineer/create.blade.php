@extends('layouts.app')
@section('title', 'New Daily Manpower Sheet')
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
                    <i class="fa-solid fa-file-pen text-primary me-2"></i>Create Daily Manpower Sheet
                </h4>
            </div>
            <p class="text-muted small mb-0 mt-1">
                Select site labor roster, compare against biometric machine punches, and submit to Planning Manager.
            </p>
        </div>
        <div class="d-flex align-items-center gap-2">
            <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#quickAddWorkerModal">
                <i class="fa-solid fa-user-plus me-1"></i>Quick Add Worker
            </button>
        </div>
    </div>

    @if($errors->any())
    <div class="alert alert-danger alert-dismissible fade show border-0 shadow-xs rounded-3 p-3 mb-3">
        <strong class="d-block mb-1"><i class="fa-solid fa-triangle-exclamation me-1"></i>Please correct the following errors:</strong>
        <ul class="mb-0 small ps-3">
            @foreach($errors->all() as $err)
            <li>{{ $err }}</li>
            @endforeach
        </ul>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    @endif

    <form action="{{ route('manpower-approval.site-engineer.store') }}" method="POST" id="sheetForm">
        @csrf
        <input type="hidden" name="action" id="formActionInput" value="save_draft">

        {{-- Sheet Header Card --}}
        <div class="card border-0 shadow-xs rounded-3 mb-3 bg-white">
            <div class="card-header bg-white py-2 px-3 border-bottom">
                <strong class="text-dark small"><i class="fa-solid fa-circle-info text-primary me-1"></i>Sheet Metadata</strong>
            </div>
            <div class="card-body p-3">
                <div class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label small fw-bold text-dark mb-1">Date <span class="text-danger">*</span></label>
                        <input type="date" name="date" id="sheetDate" class="form-control form-control-sm" value="{{ old('date', $defaultDate) }}" required onchange="fetchMachinePunches()">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-bold text-dark mb-1">Project / Site <span class="text-danger">*</span></label>
                        <select name="project_id" id="sheetProjectId" class="form-select form-select-sm" required onchange="fetchMachinePunches()">
                            <option value="">Select Project...</option>
                            @foreach($projects as $p)
                            <option value="{{ $p->id }}" {{ old('project_id', $selectedProjectId) == $p->id ? 'selected' : '' }}>{{ $p->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-bold text-dark mb-1">Trade Category</label>
                        <input type="text" name="trade" class="form-control form-control-sm" placeholder="e.g. Masonry, Steel Fixing, Concrete, General" value="{{ old('trade') }}" list="tradeSuggestions">
                        <datalist id="tradeSuggestions">
                            <option value="General Labor">
                            <option value="Masonry">
                            <option value="Carpentry">
                            <option value="Steel Fixing">
                            <option value="Plumbing">
                            <option value="Electrical">
                            <option value="Painting">
                        </datalist>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-bold text-dark mb-1">Gang / Subcontractor</label>
                        <input type="text" name="gang_subcontractor" class="form-control form-control-sm" placeholder="e.g. Gang A, Direct Labor, Subcon XYZ" value="{{ old('gang_subcontractor') }}">
                    </div>
                </div>
            </div>
        </div>

        {{-- Machine Punches Comparison Banner --}}
        <div class="card border-0 shadow-xs rounded-3 mb-3 bg-light border" id="machineSyncBanner">
            <div class="card-body py-2 px-3 d-flex flex-wrap align-items-center justify-content-between gap-2">
                <div class="d-flex align-items-center gap-2">
                    <i class="fa-solid fa-fingerprint text-primary fs-5"></i>
                    <div>
                        <strong class="text-dark small">ZKTeco Biometric Machine Punch Auto-Verification</strong>
                        <div class="text-muted" style="font-size: 0.72rem;" id="machineSyncStatusText">
                            Syncing punches for selected project and date...
                        </div>
                    </div>
                </div>
                <button type="button" class="btn btn-xs btn-outline-primary shadow-xs" onclick="fetchMachinePunches()">
                    <i class="fa-solid fa-rotate me-1"></i>Re-scan Punches
                </button>
            </div>
        </div>

        {{-- Worker Lines Card --}}
        <div class="card border-0 shadow-xs rounded-3 mb-3 bg-white">
            <div class="card-header bg-white py-2 px-3 border-bottom d-flex flex-wrap justify-content-between align-items-center gap-2">
                <div class="d-flex align-items-center gap-2">
                    <strong class="text-dark small"><i class="fa-solid fa-users text-primary me-1"></i>Labor Attendance Roster</strong>
                    <span class="badge bg-light text-dark border font-monospace" id="rosterCountBadge">0 Workers</span>
                </div>
                <div class="d-flex align-items-center gap-2">
                    {{-- Worker Quick Search & Add --}}
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
                            {{-- Dynamically populated rows --}}
                        </tbody>
                        <tfoot class="table-light fw-bold font-monospace">
                            <tr>
                                <td colspan="5" class="text-end">TOTALS:</td>
                                <td id="footRegHours">0.0</td>
                                <td id="footOtHours">0.0</td>
                                <td>—</td>
                                <td class="text-success fs-6" id="footTotalAmount">ETB 0.00</td>
                                <td colspan="3"></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                <div id="emptyRosterNotice" class="text-center py-5 text-muted">
                    <i class="fa-solid fa-user-clock fs-2 mb-2 d-block text-secondary"></i>
                    No workers added to this sheet yet. Use the selector above or click "Quick Add Worker".
                </div>
            </div>
        </div>

        {{-- Notes and Actions --}}
        <div class="card border-0 shadow-xs rounded-3 mb-4 bg-white">
            <div class="card-body p-3">
                <div class="mb-3">
                    <label class="form-label small fw-bold text-dark mb-1">Site Engineer Remarks / Activity Notes</label>
                    <textarea name="notes" rows="2" class="form-control form-control-sm" placeholder="Describe the work executed today, weather conditions, or any special labor notes..."></textarea>
                </div>

                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 pt-2 border-top">
                    <div class="text-muted small">
                        <i class="fa-solid fa-shield-halved text-success me-1"></i>Submitting will forward this sheet directly to the Planning Manager approval inbox.
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="submitFormWithAction('save_draft')">
                            <i class="fa-solid fa-floppy-disk me-1"></i>Save Draft
                        </button>
                        <button type="button" class="btn btn-sm btn-primary shadow-xs" onclick="submitFormWithAction('submit')">
                            <i class="fa-solid fa-paper-plane me-1"></i>Submit to Planning Manager
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

{{-- MODAL: Quick Add Worker --}}
<div class="modal fade" id="quickAddWorkerModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-3 border-0 shadow">
            <div class="modal-header bg-primary text-white py-2 px-3">
                <h6 class="modal-title fw-bold">
                    <i class="fa-solid fa-user-plus me-1"></i>Quick Add Worker to Master
                </h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-3">
                <div class="mb-2">
                    <label class="form-label small fw-bold">Worker Full Name <span class="text-danger">*</span></label>
                    <input type="text" id="qwName" class="form-control form-control-sm" placeholder="e.g. Abebe Bikila" required>
                </div>
                <div class="row g-2 mb-2">
                    <div class="col-6">
                        <label class="form-label small fw-bold">Phone Number</label>
                        <input type="text" id="qwPhone" class="form-control form-control-sm" placeholder="09xxxxxxxx">
                    </div>
                    <div class="col-6">
                        <label class="form-label small fw-bold">Trade <span class="text-danger">*</span></label>
                        <input type="text" id="qwTrade" class="form-control form-control-sm" placeholder="e.g. Mason, Laborer" required>
                    </div>
                </div>
                <div class="mb-2">
                    <label class="form-label small fw-bold">Daily Wage Rate (ETB) <span class="text-danger">*</span></label>
                    <input type="number" step="0.01" id="qwDailyRate" class="form-control form-control-sm" placeholder="e.g. 500.00" required>
                </div>
                <div id="qwError" class="text-danger small d-none"></div>
            </div>
            <div class="modal-footer bg-light py-2 px-3">
                <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-sm btn-primary" onclick="executeQuickAddWorker()">
                    <i class="fa-solid fa-check me-1"></i>Save &amp; Add to Roster
                </button>
            </div>
        </div>
    </div>
</div>

<script>
let rosterIndex = 0;
let machinePunchesMap = {}; // device_user_id => [punches]

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

    // Prevent duplicate worker on the same sheet
    const existing = document.querySelector(`input[name="lines[${workerId}][worker_id]"]`);
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
    document.getElementById('emptyRosterNotice').classList.add('d-none');
    const tbody = document.getElementById('rosterTbody');
    const idx = rosterIndex++;

    // Calculate default amount
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
    const tbody = document.getElementById('rosterTbody');
    if (tbody.children.length === 0) {
        document.getElementById('emptyRosterNotice').classList.remove('d-none');
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

function fetchMachinePunches() {
    const projId = document.getElementById('sheetProjectId').value;
    const date = document.getElementById('sheetDate').value;
    const statusText = document.getElementById('machineSyncStatusText');

    if (!projId || !date) return;

    statusText.textContent = `Scanning punches for project #${projId} on ${date}...`;

    fetch(`{{ route('manpower-approval.fetch-punches') }}?site_id=${projId}&date=${date}`)
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                const totalPunches = (data.raw_punches?.length || 0) + (data.device_logs?.length || 0);
                statusText.innerHTML = `<span class="text-success"><i class="fa-solid fa-circle-check me-1"></i>Found <strong>${totalPunches}</strong> machine punch records for this site/date.</span>`;
            }
        })
        .catch(() => {
            statusText.textContent = 'Unable to connect to machine logs service.';
        });
}

function executeQuickAddWorker() {
    const name = document.getElementById('qwName').value.trim();
    const phone = document.getElementById('qwPhone').value.trim();
    const trade = document.getElementById('qwTrade').value.trim();
    const dailyRate = document.getElementById('qwDailyRate').value;
    const errBox = document.getElementById('qwError');

    if (!name || !trade || !dailyRate) {
        errBox.textContent = 'Name, trade, and daily wage rate are required.';
        errBox.classList.remove('d-none');
        return;
    }

    errBox.classList.add('d-none');

    fetch(`{{ route('manpower-approval.quick-add-worker') }}`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}'
        },
        body: JSON.stringify({
            name: name,
            phone: phone,
            trade: trade,
            daily_rate: dailyRate
        })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            const w = data.worker;
            // Add option to picker
            const select = document.getElementById('workerPickerSelect');
            const newOpt = new Option(`${w.name} (${w.trade} • ETB ${parseFloat(w.daily_rate).toFixed(2)})`, w.id);
            newOpt.setAttribute('data-name', w.name);
            newOpt.setAttribute('data-code', w.worker_code);
            newOpt.setAttribute('data-rate', w.daily_rate);
            newOpt.setAttribute('data-trade', w.trade);
            select.add(newOpt);

            // Directly add to roster table
            appendWorkerRow({
                workerId: w.id,
                name: w.name,
                code: w.worker_code,
                trade: w.trade,
                dailyRate: w.daily_rate,
                checkIn: '08:00',
                checkOut: '17:00',
                regularHours: 8.0,
                overtimeHours: 0,
                attendanceStatus: 'present',
                source: 'manual',
                remark: 'Quick Added'
            });

            const modalEl = document.getElementById('quickAddWorkerModal');
            const modal = bootstrap.Modal.getInstance(modalEl);
            if (modal) modal.hide();

            // Clear inputs
            document.getElementById('qwName').value = '';
            document.getElementById('qwPhone').value = '';
            document.getElementById('qwTrade').value = '';
            document.getElementById('qwDailyRate').value = '';
        } else {
            errBox.textContent = data.message || 'Error creating worker.';
            errBox.classList.remove('d-none');
        }
    })
    .catch(() => {
        errBox.textContent = 'Server error occurred.';
        errBox.classList.remove('d-none');
    });
}

document.addEventListener('DOMContentLoaded', function() {
    fetchMachinePunches();
});
</script>
@endsection
