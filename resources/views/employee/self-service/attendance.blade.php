@extends('layouts.app')

@section('content')
<div class="container-fluid py-3">
    {{-- Page Header --}}
    <div class="page-header mb-4">
        <div class="row align-items-center">
            <div class="col">
                <div class="d-flex align-items-center gap-2">
                    <div class="bg-primary bg-opacity-10 text-primary p-2 rounded-3">
                        <i class="fa-solid fa-calendar-check fa-lg"></i>
                    </div>
                    <div>
                        <h2 class="page-title mb-0 fw-bold">My Attendance / የእኔ ክትትል</h2>
                        <small class="text-muted">
                            {{ $employee->full_name }} &bull; 
                            <span class="font-monospace text-primary fw-semibold">{{ $employee->employee_code ?? 'EMP' }}</span>
                            @if(!empty($employee->device_user_id))
                                &bull; <span class="badge bg-light text-secondary border font-monospace" style="font-size: 0.7rem;">PIN: {{ $employee->device_user_id }}</span>
                            @endif
                        </small>
                    </div>
                </div>
            </div>
            <div class="col-auto d-flex align-items-center gap-2">
                <a href="{{ route('employee.dashboard') }}" class="btn btn-outline-secondary btn-sm rounded-pill px-3">
                    <i class="fa-solid fa-arrow-left me-1"></i> Back to Dashboard
                </a>
            </div>
        </div>
    </div>

    {{-- Period Filter Toolbar --}}
    <div class="card shadow-sm border-0 mb-4 bg-white">
        <div class="card-body py-3 px-4">
            <form method="GET" action="{{ route('employee.attendance') }}" id="periodFilterForm" class="row g-3 align-items-center justify-content-between">
                <div class="col-auto d-flex align-items-center gap-2 flex-wrap">
                    <label for="periodSelect" class="form-label mb-0 fw-bold text-dark small text-nowrap">
                        <i class="fa-regular fa-calendar-days text-primary me-1"></i> Attendance Period:
                    </label>
                    <select name="period" id="periodSelect" class="form-select form-select-sm fw-semibold border-primary-subtle shadow-xs" style="min-width: 280px;" onchange="document.getElementById('periodFilterForm').submit()">
                        <optgroup label="Ethiopian Payroll Periods (የደመወዝ ክፍለ ጊዜ)">
                            @foreach ($availablePeriods as $p)
                                <option value="{{ $p['period_key'] }}" {{ ($selectedPeriodKey === $p['period_key']) ? 'selected' : '' }}>
                                    {{ $p['label_am'] }} &bull; {{ $p['month_en'] }} {{ $p['eth_year'] }}
                                </option>
                            @endforeach
                        </optgroup>
                    </select>
                </div>

                <div class="col-auto d-flex align-items-center gap-2 flex-wrap">
                    {{-- Active Period Range Badge --}}
                    @if(!empty($selectedPeriod['start_greg']) && !empty($selectedPeriod['end_greg']))
                        <div class="d-inline-flex align-items-center gap-2 px-3 py-1.5 rounded-pill bg-light border text-secondary small">
                            <i class="fa-solid fa-calendar-range text-primary"></i>
                            <span class="fw-semibold text-dark">{{ \Carbon\Carbon::parse($selectedPeriod['start_greg'])->format('d M Y') }}</span>
                            <span class="text-muted">—</span>
                            <span class="fw-semibold text-dark">{{ \Carbon\Carbon::parse($selectedPeriod['end_greg'])->format('d M Y') }}</span>
                            <span class="badge bg-primary text-white rounded-pill px-2 py-0.5 ms-1">{{ count($sheetDays) }} days</span>
                        </div>
                    @endif

                    {{-- Quick Today Button --}}
                    @if($selectedPeriodKey !== $currentPeriod['period_key'])
                        <a href="{{ route('employee.attendance') }}" class="btn btn-outline-primary btn-sm rounded-pill px-2.5 py-1" title="Go to Current Period">
                            <i class="fa-solid fa-rotate-left me-1"></i> Current Period
                        </a>
                    @endif
                </div>
            </form>
        </div>
    </div>

    {{-- Monthly Summary Statistics Cards --}}
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="card shadow-sm border-0 border-start border-4 border-success h-100">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <span class="text-muted small text-uppercase fw-semibold d-block">Present Days</span>
                            <h3 class="text-success fw-bold mb-0 mt-1">{{ $summary['present'] }}</h3>
                            <small class="text-muted" style="font-size: 0.72rem;">On-duty &amp; clocked in</small>
                        </div>
                        <div class="bg-success bg-opacity-10 text-success p-2.5 rounded-circle">
                            <i class="fa-solid fa-user-check fa-lg"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card shadow-sm border-0 border-start border-4 border-danger h-100">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <span class="text-muted small text-uppercase fw-semibold d-block">Absent Days</span>
                            <h3 class="text-danger fw-bold mb-0 mt-1">{{ $summary['absent'] }}</h3>
                            <small class="text-muted" style="font-size: 0.72rem;">Missed regular shifts</small>
                        </div>
                        <div class="bg-danger bg-opacity-10 text-danger p-2.5 rounded-circle">
                            <i class="fa-solid fa-user-xmark fa-lg"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card shadow-sm border-0 border-start border-4 border-primary h-100">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <span class="text-muted small text-uppercase fw-semibold d-block">Approved Leaves</span>
                            <h3 class="text-primary fw-bold mb-0 mt-1">{{ $summary['leave'] }}</h3>
                            <small class="text-muted" style="font-size: 0.72rem;">Official sanctioned leave</small>
                        </div>
                        <div class="bg-primary bg-opacity-10 text-primary p-2.5 rounded-circle">
                            <i class="fa-solid fa-calendar-day fa-lg"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card shadow-sm border-0 border-start border-4 border-warning h-100">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <span class="text-muted small text-uppercase fw-semibold d-block">Late Punches</span>
                            <h3 class="text-warning-emphasis fw-bold mb-0 mt-1">
                                {{ $summary['late_days'] }}
                                @if($summary['penalty_days'] > 0)
                                    <span class="text-danger small fs-6">({{ $summary['penalty_days'] }}d penalty)</span>
                                @endif
                            </h3>
                            <small class="text-muted" style="font-size: 0.72rem;">After 08:40 AM standard</small>
                        </div>
                        <div class="bg-warning bg-opacity-15 text-warning p-2.5 rounded-circle">
                            <i class="fa-solid fa-clock-rotate-left fa-lg"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- 1. Period Sheet View (Exact Match with Image 1: Dual Ethiopian Header & Daily Punch Cards) --}}
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div>
                <h5 class="mb-0 fw-bold text-dark">
                    <i class="fa-solid fa-table-cells me-2 text-primary"></i>Period Attendance Sheet (የወሩ ቀን በቀን ሰንጠረዥ)
                </h5>
                <small class="text-muted">
                    {{ $selectedPeriod['label_am'] ?? '' }} &bull; {{ $selectedPeriod['label_en'] ?? '' }}
                </small>
            </div>
            <div class="d-flex align-items-center gap-2">
                <span class="badge bg-light text-muted border small">
                    <i class="fa-solid fa-arrows-left-right me-1"></i> Scroll horizontally to view all days
                </span>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive" style="max-height: 280px;">
                <table class="table table-bordered align-middle mb-0 text-center small attendance-matrix-table mode-times">
                    <thead class="table-light sticky-top" style="z-index: 5;">
                        {{-- Row 1: Ethiopian Day & Month (Matching Image 1: 26 MESK, 27 MESK, 28 MESK...) --}}
                        <tr>
                            <th class="sticky-col-header text-start align-middle px-3" style="min-width: 170px; left: 0; z-index: 6; background-color: #f8fafc;">
                                <div class="fw-bold text-dark" style="font-size: 0.78rem;">DATE / ቀን</div>
                                <div class="text-muted" style="font-size: 0.65rem;">Ethiopian Calendar</div>
                            </th>
                            @foreach($sheetDays as $item)
                                @php 
                                    $d = $item['day'];
                                    $isToday = ($d['greg_date'] === today()->toDateString());
                                @endphp
                                <th class="p-1 date-col-header {{ $isToday ? 'border-primary bg-primary bg-opacity-10' : ($d['is_sunday'] ? 'bg-secondary bg-opacity-10 text-muted' : ($d['is_saturday'] ? 'bg-warning bg-opacity-10 text-warning-emphasis' : '')) }}" style="min-width: 82px;">
                                    <div class="fw-bold {{ $isToday ? 'text-primary' : 'text-dark' }}" style="font-size: 0.8rem;">
                                        {{ $d['eth_day'] }}
                                    </div>
                                    <div class="text-uppercase fw-semibold {{ $isToday ? 'text-primary' : 'text-muted' }}" style="font-size: 0.64rem; letter-spacing: 0.5px;">
                                        {{ strtoupper(substr($d['eth_label_en'], 0, 4)) }}
                                    </div>
                                </th>
                            @endforeach
                        </tr>

                        {{-- Row 2: Gregorian Day & Month with Day of Week (Matching Image 1: 06 OCT TUE, 07 OCT WED...) --}}
                        <tr>
                            <th class="sticky-col-header text-start align-middle px-3 text-muted" style="font-size: 0.68rem; left: 0; z-index: 6; background-color: #f8fafc;">
                                <div>Gregorian &bull; Day</div>
                            </th>
                            @foreach($sheetDays as $item)
                                @php 
                                    $d = $item['day'];
                                    $isToday = ($d['greg_date'] === today()->toDateString());
                                @endphp
                                <th class="p-1 date-col-header text-muted {{ $isToday ? 'border-primary bg-primary bg-opacity-10' : ($d['is_sunday'] ? 'bg-secondary bg-opacity-10' : ($d['is_saturday'] ? 'bg-warning bg-opacity-10' : '')) }}" style="font-size: 0.65rem; min-width: 82px;">
                                    <div class="{{ $isToday ? 'text-primary fw-semibold' : '' }}">{{ $d['greg_day'] }} {{ $d['greg_month'] }}</div>
                                    <div class="fw-semibold {{ $isToday ? 'text-primary' : 'text-dark' }}">{{ $d['day_name_en'] }}</div>
                                </th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            {{-- Sticky Employee Info Cell --}}
                            <td class="text-start sticky-col-cell bg-white px-3 py-2" style="left: 0; z-index: 4;">
                                <strong class="text-dark d-block text-truncate" style="font-size: 0.85rem;" title="{{ $employee->full_name }}">
                                    {{ $employee->full_name }}
                                </strong>
                                <div class="text-muted" style="font-size: 0.7rem;">
                                    <span class="font-monospace text-primary fw-semibold">{{ $employee->employee_code ?? 'EMP' }}</span>
                                    &bull; {{ $employee->department ?? 'Staff' }}
                                </div>
                            </td>

                            {{-- Daily Attendance Cards (Present ->07:08 AM [->05:35 PM, Absent A, etc.) --}}
                            @foreach($sheetDays as $item)
                                @php
                                    $code = $item['code'];
                                    $cellClass = $item['class'];
                                    $punchIn = $item['punch_in'];
                                    $punchOut = $item['punch_out'];
                                    $isLate = $item['is_late'];
                                    $lateMin = $item['late_min'];
                                @endphp
                                <td class="p-0 position-relative cell-container {{ $cellClass }}" title="{{ $item['label'] }}">
                                    <div class="d-flex flex-column align-items-center justify-content-center p-1 w-100 h-100" style="min-height: 52px; min-width: 82px;">
                                        @if($code === 'P')
                                            {{-- Present Clock In --}}
                                            <div class="fw-bold font-monospace text-truncate w-100 text-center {{ $isLate ? 'text-warning-emphasis' : 'text-success' }}" style="font-size: 0.73rem; line-height: 1.15;">
                                                <i class="fa-solid fa-arrow-right-to-bracket me-0.5 opacity-75" style="font-size: 0.58rem;"></i>{{ $punchIn ?? '—' }}
                                            </div>
                                            {{-- Present Clock Out --}}
                                            <div class="font-monospace text-truncate w-100 text-center text-secondary" style="font-size: 0.68rem; line-height: 1.15; opacity: 0.85;">
                                                <i class="fa-solid fa-arrow-right-from-bracket me-0.5 opacity-75" style="font-size: 0.58rem;"></i>{{ $punchOut ?? '—' }}
                                            </div>
                                            @if($isLate)
                                                <span class="badge bg-warning text-dark border border-warning position-absolute top-0 end-0 px-1 py-0 shadow-xs" style="font-size: 0.52rem; transform: scale(0.85); transform-origin: top right;" title="Late by {{ $lateMin }}m">
                                                    +{{ $lateMin }}m
                                                </span>
                                            @endif
                                        @elseif($code === 'A')
                                            <div class="fw-bold badge-letter text-danger" style="font-size: 0.95rem; line-height: 1;">A</div>
                                            <div class="text-danger fw-semibold" style="font-size: 0.58rem; letter-spacing: 0.5px;">ABSENT</div>
                                        @elseif($code === 'L')
                                            <div class="fw-bold badge-letter text-primary" style="font-size: 0.95rem; line-height: 1;">L</div>
                                            <div class="text-primary fw-semibold" style="font-size: 0.58rem;">LEAVE</div>
                                        @elseif($code === 'H')
                                            <div class="fw-bold badge-letter text-purple" style="font-size: 0.95rem; line-height: 1;">H</div>
                                            <div class="text-purple fw-semibold" style="font-size: 0.58rem;">HOLIDAY</div>
                                        @elseif($code === 'S')
                                            <div class="fw-bold badge-letter text-info" style="font-size: 0.95rem; line-height: 1;">S</div>
                                            <div class="text-info fw-semibold" style="font-size: 0.58rem;">SITE</div>
                                        @elseif($code === 'SUN')
                                            <span class="text-muted fw-bold" style="font-size: 0.72rem;">SUN</span>
                                        @else
                                            <span class="text-muted opacity-50">—</span>
                                        @endif
                                    </div>
                                </td>
                            @endforeach
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- 2. Detailed Attendance Log Table (With Dual Ethiopian & Gregorian Date Format) --}}
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div>
                        <h5 class="mb-0 fw-bold text-dark">
                            <i class="fa-solid fa-list-check me-2 text-primary"></i>Detailed Attendance Logs (ዝርዝር የዕለት ምዝገባ)
                        </h5>
                        <small class="text-muted">Day-by-day punches with morning and afternoon sessions</small>
                    </div>
                    <div>
                        <span class="badge bg-light text-dark border">
                            Total Records: {{ $attendance->total() }}
                        </span>
                    </div>
                </div>

                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="bg-light">
                                <tr>
                                    {{-- DATE COLUMN (Dual Calendar Format) --}}
                                    <th rowspan="2" class="align-middle text-nowrap px-3 py-2 text-muted fw-bold" style="min-width: 220px;">
                                        DATE / ቀን
                                        <div class="small fw-normal text-primary" style="font-size: 0.68rem; letter-spacing: 0.4px;">ETHIOPIAN &bull; GREGORIAN</div>
                                    </th>
                                    <th rowspan="2" class="align-middle text-center py-2 text-muted fw-bold">STATUS</th>
                                    <th colspan="2" class="text-center text-primary border-bottom-0 pb-1 pt-2 fw-bold" style="background-color: rgba(13, 110, 253, 0.03);">
                                        <i class="fa-solid fa-sun text-warning me-1"></i> Morning Session
                                    </th>
                                    <th colspan="2" class="text-center text-primary border-bottom-0 pb-1 pt-2 fw-bold" style="background-color: rgba(255, 193, 7, 0.05);">
                                        <i class="fa-solid fa-cloud-sun text-warning me-1"></i> Afternoon Session
                                    </th>
                                    <th rowspan="2" class="align-middle text-center py-2 text-muted fw-bold">DURATION</th>
                                    <th rowspan="2" class="align-middle py-2 text-muted fw-bold">NOTES</th>
                                </tr>
                                <tr>
                                    <th class="text-center small text-muted border-top-0 pt-0 pb-2" style="background-color: rgba(13, 110, 253, 0.03); min-width: 110px;">Check-In</th>
                                    <th class="text-center small text-muted border-top-0 pt-0 pb-2" style="background-color: rgba(13, 110, 253, 0.03); min-width: 110px;">Check-Out</th>
                                    <th class="text-center small text-muted border-top-0 pt-0 pb-2" style="background-color: rgba(255, 193, 7, 0.05); min-width: 110px;">Check-In</th>
                                    <th class="text-center small text-muted border-top-0 pt-0 pb-2" style="background-color: rgba(255, 193, 7, 0.05); min-width: 110px;">Check-Out</th>
                                </tr>
                            </thead>
                            <tbody>
                                @php
                                    $amharicDays = [
                                        'Mon' => 'ሰኞ',
                                        'Tue' => 'ማክሰኞ',
                                        'Wed' => 'ረቡዕ',
                                        'Thu' => 'ሐሙስ',
                                        'Fri' => 'ዓርብ',
                                        'Sat' => 'ቅዳሜ',
                                        'Sun' => 'እሑድ',
                                    ];
                                @endphp

                                @forelse ($attendance as $record)
                                    @php
                                        $attDate = $record->attendance_date;
                                        $et = $attDate ? \App\Helpers\EthiopianCalendar::toEthiopian($attDate) : [];
                                        $ethDay = $et['day'] ?? '—';
                                        $ethMonthCode = !empty($et['month_en']) ? strtoupper(substr($et['month_en'], 0, 4)) : '—';
                                        $ethMonthAm = $et['month_am'] ?? '';
                                        $dayAmharic = $attDate ? ($amharicDays[$attDate->format('D')] ?? '') : '';
                                    @endphp
                                    <tr>
                                        {{-- Dual Date Format matching Image 1: 28 MESK + 08 OCT THU --}}
                                        <td class="text-nowrap px-3 py-2.5">
                                            <div class="d-flex align-items-center gap-2.5">
                                                {{-- Ethiopian Badge --}}
                                                <div class="text-center rounded px-2 py-1 shadow-xs border bg-white flex-shrink-0" style="min-width: 58px; border-color: #cbd5e1 !important;">
                                                    <div class="fw-bold text-dark lh-1" style="font-size: 1.05rem;">{{ $ethDay }}</div>
                                                    <div class="text-primary text-uppercase fw-bold" style="font-size: 0.65rem; letter-spacing: 0.5px;">{{ $ethMonthCode }}</div>
                                                </div>
                                                <div>
                                                    <div class="fw-bold text-dark" style="font-size: 0.88rem;">
                                                        {{ $attDate ? $attDate->format('d M Y') : '—' }}
                                                        @if($attDate)
                                                            <span class="badge bg-light text-secondary border font-monospace ms-1" style="font-size: 0.68rem;">{{ strtoupper($attDate->format('D')) }}</span>
                                                        @endif
                                                    </div>
                                                    <div class="text-muted small" style="font-size: 0.74rem;">
                                                        {{ $ethMonthAm }} {{ $ethDay }} &bull; {{ $dayAmharic }} ({{ $attDate ? $attDate->format('l') : '—' }})
                                                    </div>
                                                </div>
                                            </div>
                                        </td>

                                        {{-- Status --}}
                                        <td class="text-center text-nowrap">
                                            @php
                                                $status = strtolower($record->status ?? '');
                                                $statusNorm = str_replace('-', '_', $status);
                                            @endphp
                                            @if ($statusNorm === 'present')
                                                <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1">Present</span>
                                            @elseif ($statusNorm === 'absent')
                                                <span class="badge bg-danger-subtle text-danger border border-danger-subtle px-2 py-1">Absent</span>
                                            @elseif ($statusNorm === 'leave')
                                                <span class="badge bg-warning-subtle text-warning border border-warning-subtle px-2 py-1">Leave</span>
                                            @elseif ($statusNorm === 'half_day')
                                                <span class="badge bg-info-subtle text-info border border-info-subtle px-2 py-1">Half Day</span>
                                            @elseif ($status)
                                                <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle px-2 py-1">{{ ucfirst(str_replace('_', ' ', $status)) }}</span>
                                            @else
                                                <span class="text-muted">—</span>
                                            @endif
                                        </td>

                                        {{-- Morning Check-In --}}
                                        <td class="text-center text-nowrap" style="background-color: rgba(13, 110, 253, 0.015);">
                                            @php
                                                $mIn = $record->morning_in ?? ($record->check_in && \Carbon\Carbon::parse($record->check_in)->hour < 12 ? $record->check_in : null);
                                                $isLateMIn = $mIn && (substr(trim($mIn), 0, 5) > '08:40');
                                            @endphp
                                            @if($mIn)
                                                <span class="badge {{ $isLateMIn ? 'bg-warning-subtle text-warning border-warning-subtle' : 'bg-success-subtle text-success border-success-subtle' }} border font-monospace px-2 py-1" title="Morning Check-In">
                                                    <i class="fa-solid fa-arrow-right me-1 small"></i>{{ \Carbon\Carbon::parse($mIn)->format('h:i A') }}
                                                </span>
                                            @else
                                                <span class="text-muted">—</span>
                                            @endif
                                        </td>

                                        {{-- Morning Check-Out --}}
                                        <td class="text-center text-nowrap" style="background-color: rgba(13, 110, 253, 0.015);">
                                            @if($record->morning_out)
                                                <span class="badge bg-info-subtle text-info border border-info-subtle font-monospace px-2 py-1" title="Morning Check-Out">
                                                    <i class="fa-solid fa-arrow-left me-1 small"></i>{{ \Carbon\Carbon::parse($record->morning_out)->format('h:i A') }}
                                                </span>
                                            @else
                                                <span class="text-muted">—</span>
                                            @endif
                                        </td>

                                        {{-- Afternoon Check-In --}}
                                        <td class="text-center text-nowrap" style="background-color: rgba(255, 193, 7, 0.025);">
                                            @if($record->afternoon_in)
                                                <span class="badge bg-success-subtle text-success border border-success-subtle font-monospace px-2 py-1" title="Afternoon Check-In">
                                                    <i class="fa-solid fa-arrow-right me-1 small"></i>{{ \Carbon\Carbon::parse($record->afternoon_in)->format('h:i A') }}
                                                </span>
                                            @else
                                                <span class="text-muted">—</span>
                                            @endif
                                        </td>

                                        {{-- Afternoon Check-Out --}}
                                        <td class="text-center text-nowrap" style="background-color: rgba(255, 193, 7, 0.025);">
                                            @php
                                                $aOut = $record->afternoon_out ?? ($record->check_out && \Carbon\Carbon::parse($record->check_out)->hour >= 12 ? $record->check_out : null);
                                            @endphp
                                            @if($aOut)
                                                <span class="badge bg-info-subtle text-info border border-info-subtle font-monospace px-2 py-1" title="Afternoon Check-Out">
                                                    <i class="fa-solid fa-arrow-left me-1 small"></i>{{ \Carbon\Carbon::parse($aOut)->format('h:i A') }}
                                                </span>
                                            @else
                                                <span class="text-muted">—</span>
                                            @endif
                                        </td>

                                        {{-- Duration / Hours --}}
                                        <td class="text-center text-nowrap">
                                            @if($record->hours_worked && (float)$record->hours_worked > 0)
                                                <span class="fw-bold text-dark">{{ number_format($record->hours_worked, 1) }} hrs</span>
                                            @elseif($record->check_in && $record->check_out)
                                                @php
                                                    try {
                                                        $ci = \Carbon\Carbon::parse($record->check_in);
                                                        $co = \Carbon\Carbon::parse($record->check_out);
                                                        $diff = $ci->diff($co)->format('%h:%I');
                                                    } catch (\Exception $e) {
                                                        $diff = null;
                                                    }
                                                @endphp
                                                {{ $diff ? $diff . ' hrs' : '—' }}
                                            @else
                                                <span class="text-muted">—</span>
                                            @endif
                                        </td>

                                        {{-- Notes --}}
                                        <td>
                                            @if($record->notes)
                                                @if(str_contains(strtolower($record->notes), 'late'))
                                                    <span class="badge bg-warning-subtle text-warning border border-warning-subtle px-2 py-1">
                                                        <i class="fa-solid fa-clock me-1"></i>{{ $record->notes }}
                                                    </span>
                                                @else
                                                    <span class="text-secondary small">{{ $record->notes }}</span>
                                                @endif
                                            @else
                                                <span class="text-muted">—</span>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="8" class="text-center text-muted py-5">
                                            <i class="fa-solid fa-calendar-times fa-2x mb-2 text-muted d-block opacity-50"></i>
                                            No attendance records found for this period
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    @if ($attendance->hasPages())
                        <div class="p-3 border-top">
                            {{ $attendance->appends(request()->query())->links() }}
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

<style>
/* Attendance Matrix Sheet Styles */
.attendance-matrix-table .sticky-col-header,
.attendance-matrix-table .sticky-col-cell {
    position: sticky;
    z-index: 4;
}
.attendance-matrix-table.mode-times .date-col-header,
.attendance-matrix-table.mode-times .cell-container {
    min-width: 82px;
    height: 52px;
}
.cell-container {
    transition: all 0.15s ease-in-out;
}
.cell-container:hover {
    transform: scale(1.04);
    z-index: 10 !important;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.12);
}

/* Semantic Cell Colors matching ERP Matrix */
.cell-present-ontime {
    background-color: #f0fdf4 !important;
    border: 1px solid #bbf7d0 !important;
}
.cell-present-late {
    background-color: #fffbeb !important;
    border: 1px solid #fde68a !important;
}
.cell-site {
    background-color: #f0f9ff !important;
    border: 1px solid #bae6fd !important;
}
.cell-leave {
    background-color: #eef2ff !important;
    border: 1px solid #c7d2fe !important;
}
.cell-holiday {
    background-color: #faf5ff !important;
    border: 1px solid #e9d5ff !important;
}
.cell-sunday {
    background-color: #f9fafb !important;
    border: 1px solid #e5e7eb !important;
}
.cell-sunday-ot {
    background-color: #ecfdf5 !important;
    border: 1px solid #6ee7b7 !important;
}
.cell-absent {
    background-color: #fff1f2 !important;
    border: 1px solid #fecdd3 !important;
}
.cell-upcoming {
    background-color: #ffffff !important;
    border: 1px solid #f3f4f6 !important;
}

.cell-present-ontime .badge-letter { color: #166534; }
.cell-present-late .badge-letter   { color: #b45309; }
.cell-site .badge-letter           { color: #0369a1; }
.cell-leave .badge-letter          { color: #4338ca; }
.cell-holiday .badge-letter        { color: #7e22ce; }
.cell-sunday .badge-letter         { color: #6b7280; }
.cell-absent .badge-letter         { color: #be123c; }
.cell-upcoming .badge-letter       { color: #9ca3af; }

.bg-purple { background-color: #7e22ce !important; }
.text-purple { color: #7e22ce !important; }
</style>
@endsection
