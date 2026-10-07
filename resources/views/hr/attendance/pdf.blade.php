<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $reportTitle }} - ConstructPro ERP</title>

    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Noto+Sans+Ethiopic:wght@400;500;600;700&display=swap" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <!-- html2pdf.js for client-side PDF export -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>

    <style>
        :root {
            --primary: #1e3a8a;
            --primary-dark: #0f172a;
            --accent: #f59e0b;
            --text-main: #1f2937;
            --text-muted: #64748b;
            --border-color: #cbd5e1;
            --border-light: #e2e8f0;
            --bg-light: #f8fafc;
            --present-green: #15803d;
            --present-bg: #f0fdf4;
            --absent-red: #b91c1c;
            --absent-bg: #fef2f2;
            --site-blue: #0369a1;
            --site-bg: #f0f9ff;
            --late-orange: #c2410c;
            --late-bg: #fff7ed;
            --sun-gray: #64748b;
            --sun-bg: #f1f5f9;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: 'Inter', 'Noto Sans Ethiopic', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
        }

        body {
            background-color: #475569;
            color: var(--text-main);
            padding: 16px 0;
            font-size: 10px;
            line-height: 1.35;
        }

        /* ── Top Floating Action Toolbar (Non-Print) ─────────────────── */
        .no-print-toolbar {
            max-width: 1200px;
            margin: 0 auto 16px auto;
            background: #ffffff;
            padding: 12px 18px;
            border-radius: 10px;
            box-shadow: 0 4px 14px rgba(0, 0, 0, 0.2);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
            border: 1px solid var(--border-color);
        }

        .toolbar-group {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }

        .filter-form {
            display: flex;
            align-items: center;
            gap: 6px;
            flex-wrap: wrap;
        }

        .form-select-sm, .form-control-sm {
            padding: 5px 8px;
            font-size: 11.5px;
            border-radius: 6px;
            border: 1px solid var(--border-color);
            background-color: #ffffff;
            color: var(--text-main);
            outline: none;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 12px;
            font-weight: 600;
            padding: 7px 13px;
            border-radius: 6px;
            border: none;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.15s ease-in-out;
        }

        .btn-primary { background: #2563eb; color: #ffffff; }
        .btn-primary:hover { background: #1d4ed8; }
        .btn-danger { background: #dc2626; color: #ffffff; }
        .btn-danger:hover { background: #b91c1c; }
        .btn-outline { background: #ffffff; color: #475569; border: 1px solid #cbd5e1; }
        .btn-outline:hover { background: #f1f5f9; }
        .btn-dark { background: #0f172a; color: #ffffff; }

        .btn-toggle-active {
            background-color: #1e3a8a !important;
            color: #ffffff !important;
            border-color: #1e3a8a !important;
        }

        /* ── Standard A4 Page Container (Guaranteed 1 Page Per Employee) ─── */
        .a4-page {
            width: 210mm;
            height: 297mm;
            max-height: 297mm;
            margin: 0 auto 20px auto;
            background: #ffffff;
            padding: 7mm 8mm 6mm 8mm;
            border-radius: 4px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.25);
            position: relative;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            box-sizing: border-box;
            overflow: hidden;
            page-break-after: always;
            page-break-inside: avoid;
            break-after: page;
            break-inside: avoid;
        }

        .a4-landscape-page {
            width: 297mm;
            min-height: 210mm;
            margin: 0 auto 20px auto;
            background: #ffffff;
            padding: 8mm 8mm 8mm 8mm;
            border-radius: 4px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.25);
        }

        /* ── Header Styling ──────────────────────────────────────────── */
        .timesheet-header {
            border-bottom: 2px solid var(--primary);
            padding-bottom: 4px;
            margin-bottom: 3px;
        }

        .header-top {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 10px;
        }

        .company-title {
            font-size: 13px;
            font-weight: 800;
            color: var(--primary);
            text-transform: uppercase;
            letter-spacing: 0.4px;
            line-height: 1.1;
        }

        .company-subtitle {
            font-size: 8px;
            font-weight: 600;
            color: var(--text-muted);
            margin-top: 1px;
        }

        .doc-title-badge {
            background: #0f172a;
            color: #ffffff;
            padding: 3px 8px;
            border-radius: 4px;
            font-size: 9.5px;
            font-weight: 700;
            text-align: right;
            text-transform: uppercase;
            white-space: nowrap;
        }

        /* ── Employee Profile Info Card (A4 Header) ───────────────────── */
        .emp-profile-card {
            background: #f8fafc;
            border: 1px solid var(--border-color);
            border-radius: 4px;
            padding: 4px 8px;
            margin-top: 4px;
            display: grid;
            grid-template-columns: 2fr 1.3fr 1.3fr 1.4fr;
            gap: 6px;
            font-size: 8.5px;
        }

        .profile-field label {
            display: block;
            font-size: 7px;
            font-weight: 700;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.3px;
            margin-bottom: 1px;
        }

        .profile-field span {
            font-weight: 700;
            color: #0f172a;
            font-size: 9.5px;
        }

        /* ── 4-Punch Detailed Table (A4 Size) ────────────────────────── */
        table.timesheet-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 7.8px;
            text-align: center;
            background: #ffffff;
            margin-top: 3px;
        }

        table.timesheet-table th,
        table.timesheet-table td {
            border: 1px solid #cbd5e1;
            padding: 1.6px 2px;
            vertical-align: middle;
            line-height: 1.15;
        }

        table.timesheet-table thead tr:first-child th {
            background-color: #0f172a;
            color: #ffffff;
            font-size: 7.5px;
            font-weight: 700;
            padding: 3px 2px;
        }

        table.timesheet-table thead tr:nth-child(2) th {
            background-color: #f1f5f9;
            color: #1e293b;
            font-size: 7px;
            font-weight: 700;
            padding: 2px;
        }

        .th-morning { background-color: #e0f2fe !important; color: #0369a1 !important; }
        .th-afternoon { background-color: #fef3c7 !important; color: #92400e !important; }

        .time-cell {
            font-family: monospace;
            font-weight: 700;
            font-size: 7.5px;
            white-space: nowrap;
        }

        .time-in-text { color: var(--present-green); }
        .time-in-late { color: var(--late-orange); font-weight: 800; }
        .time-out-text { color: #475569; }

        .late-pill {
            background: #fed7aa;
            color: #9a3412;
            font-size: 6px;
            font-weight: 800;
            padding: 0.5px 2px;
            border-radius: 2px;
            margin-left: 1px;
        }

        /* ── Row State Highlighting ─────────────────────────────────── */
        tr.row-sun { background-color: #f8fafc; color: #94a3b8; }
        tr.row-site { background-color: #f0f9ff; }
        tr.row-leave { background-color: #eff6ff; }
        tr.row-holiday { background-color: #faf5ff; }
        tr.row-absent { background-color: #fef2f2; }

        /* ── Monthly Summary Footer Box ──────────────────────────────── */
        .summary-box {
            background: #f8fafc;
            border: 1px solid var(--border-color);
            border-radius: 4px;
            padding: 4px 8px;
            margin-top: 4px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 4px;
        }

        .summary-item {
            text-align: center;
            padding: 0 4px;
        }

        .summary-val {
            font-family: monospace;
            font-size: 11px;
            font-weight: 800;
            display: block;
            line-height: 1.1;
        }

        .summary-lbl {
            font-size: 6.8px;
            font-weight: 700;
            color: var(--text-muted);
            text-transform: uppercase;
        }

        /* ── Official 3-Signoff Block ────────────────────────────────── */
        .signature-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 12px;
            margin-top: 5px;
            padding-top: 2px;
        }

        .sign-card {
            border-top: 1.2px solid #0f172a;
            padding-top: 2px;
            font-size: 7.5px;
            color: #334155;
        }

        .sign-title {
            font-weight: 800;
            color: #0f172a;
            font-size: 8px;
            text-transform: uppercase;
            margin-bottom: 5px;
        }

        /* ── Matrix Layout Specific ──────────────────────────────────── */
        .matrix-table-wrap {
            overflow-x: auto;
            border: 1px solid var(--border-color);
            margin-top: 8px;
        }

        table.matrix-view-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 8px;
            text-align: center;
        }

        table.matrix-view-table th, table.matrix-view-table td {
            border: 1px solid #cbd5e1;
            padding: 2px 1px;
            vertical-align: middle;
        }

        table.matrix-view-table th.sun-col { background: #334155; }
        table.matrix-view-table th.sun-col-sub { background: #e2e8f0; }
        table.matrix-view-table td.bg-code-p { background-color: #f0fdf4; }
        table.matrix-view-table td.bg-code-late { background-color: #fff7ed; }
        table.matrix-view-table td.bg-code-s { background-color: #f0f9ff; }
        table.matrix-view-table td.bg-code-a { background-color: #fef2f2; }
        table.matrix-view-table td.bg-code-l { background-color: #eff6ff; }
        table.matrix-view-table td.bg-code-h { background-color: #faf5ff; }
        table.matrix-view-table td.bg-code-sun { background-color: #f1f5f9; }
        .punch-late { color: #c2410c; font-weight: 700; }
        .punch-ontime { color: #15803d; font-weight: 700; }

        /* ── Print Media Optimization (Strict 1 Page Per Employee) ─────── */
        @media print {
            body {
                background: #ffffff !important;
                padding: 0 !important;
                color: #000000 !important;
            }

            .no-print-toolbar {
                display: none !important;
            }

            .a4-page {
                width: 100% !important;
                height: 287mm !important;
                max-height: 287mm !important;
                min-height: 0 !important;
                padding: 0 !important;
                margin: 0 !important;
                box-shadow: none !important;
                border-radius: 0 !important;
                page-break-after: always !important;
                page-break-inside: avoid !important;
                break-after: page !important;
                break-inside: avoid !important;
                overflow: hidden !important;
                display: flex !important;
                flex-direction: column !important;
                justify-content: space-between !important;
            }

            .a4-page:last-child {
                page-break-after: auto !important;
                break-after: auto !important;
            }

            .a4-landscape-page {
                width: 100% !important;
                padding: 4mm !important;
                margin: 0 !important;
                box-shadow: none !important;
                page-break-after: auto !important;
            }

            @page {
                size: A4 portrait;
                margin: 5mm;
            }

            .signature-grid {
                page-break-inside: avoid !important;
                break-inside: avoid !important;
            }
        }
    </style>
</head>
<body>

    <!-- ── Floating Action Toolbar (Non-Print) ────────────────────────── -->
    <div class="no-print-toolbar">
        <div class="toolbar-group">
            <a href="{{ route('attendance.index', request()->except(['page'])) }}" class="btn btn-outline" title="Return to Attendance Matrix">
                <i class="fas fa-arrow-left"></i> Back to Roster
            </a>

            <!-- View Layout Switcher: A4 Detailed Timesheet vs Matrix -->
            <div class="btn-group" role="group">
                <button type="button" class="btn btn-outline {{ request('layout', 'timesheet') === 'timesheet' ? 'btn-toggle-active' : '' }}" onclick="switchReportLayout('timesheet')">
                    <i class="fas fa-file-invoice"></i> A4 Detailed Timesheets (4 Sessions)
                </button>
                <button type="button" class="btn btn-outline {{ request('layout') === 'matrix' ? 'btn-toggle-active' : '' }}" onclick="switchReportLayout('matrix')">
                    <i class="fas fa-table-cells"></i> Master Matrix
                </button>
            </div>
        </div>

        <div class="toolbar-group">
            <!-- Filter Form for Month / Date Option / Employee -->
            <form action="{{ route('attendance.export-pdf') }}" method="GET" class="filter-form" id="reportFilterForm">
                <input type="hidden" name="layout" id="layoutInput" value="{{ request('layout', 'timesheet') }}">
                <input type="hidden" name="staff_type" value="{{ $staffType }}">

                <!-- Month / Ethiopian Payroll Period -->
                <select name="period" class="form-select-sm" onchange="this.form.submit()" title="Ethiopian Payroll Month">
                    <optgroup label="Ethiopian Payroll Months (26th–25th)">
                        @foreach($availablePeriods as $p)
                        <option value="{{ $p['period_key'] }}" {{ (!$period['is_custom'] ?? false) && $p['period_key'] === $selectedPeriodKey ? 'selected' : '' }}>
                            {{ $p['full_label'] }} ({{ $p['label_en'] }})
                        </option>
                        @endforeach
                    </optgroup>
                </select>

                <!-- Custom Date Option -->
                <input type="date" name="start_date" value="{{ request('start_date', $period['start_greg']) }}" class="form-control-sm" title="Start Date">
                <span class="text-muted" style="font-size: 10px;">&rarr;</span>
                <input type="date" name="end_date" value="{{ request('end_date', $period['end_greg']) }}" class="form-control-sm" title="End Date">

                @if(request('employee_ids'))
                    @if(is_array(request('employee_ids')))
                        @foreach(request('employee_ids') as $eid)
                            <input type="hidden" name="employee_ids[]" value="{{ $eid }}">
                        @endforeach
                    @else
                        <input type="hidden" name="employee_ids" value="{{ request('employee_ids') }}">
                    @endif
                @endif

                <!-- Filter Single Employee or All -->
                <select name="employee_id" class="form-select-sm" onchange="this.form.submit()" title="Filter specific employee or all staff">
                    @if(request('employee_ids') && is_array(request('employee_ids')) && count(request('employee_ids')) > 1)
                        <option value="">Selected Staff ({{ count($employees) }} Sheets)</option>
                    @else
                        <option value="">All Staff ({{ count($employees) }} Sheets)</option>
                    @endif
                    @foreach($employees as $e)
                    <option value="{{ $e->id }}" {{ request('employee_id') == $e->id ? 'selected' : '' }}>
                        {{ $e->full_name }} ({{ $e->employee_code ?? 'EMP' }})
                    </option>
                    @endforeach
                </select>

                <button type="submit" class="btn btn-primary" title="Apply filter">
                    <i class="fas fa-filter"></i> Apply
                </button>
            </form>
        </div>

        <div class="toolbar-group">
            <button onclick="downloadPdfReport()" id="btnDownloadPdf" class="btn btn-danger">
                <i class="fas fa-file-pdf"></i> Download PDF
            </button>
            <button onclick="window.print()" class="btn btn-dark">
                <i class="fas fa-print"></i> Print (A4 Size)
            </button>
        </div>
    </div>

    @php
        $selectedEmpId  = request('employee_id');
        $rawEmpIds      = request('employee_ids');
        $selectedEmpIds = is_array($rawEmpIds) ? $rawEmpIds : ($rawEmpIds ? explode(',', $rawEmpIds) : []);
        $selectedEmpIds = array_filter(array_map('trim', $selectedEmpIds));

        $activeLayout   = request('layout', 'timesheet');
        $filteredRows   = collect($matrix);

        if ($selectedEmpId) {
            $filteredRows = $filteredRows->filter(fn($r, $k) => $k == $selectedEmpId);
        } elseif (!empty($selectedEmpIds)) {
            $filteredRows = $filteredRows->filter(fn($r, $k) => in_array((string)$k, array_map('strval', $selectedEmpIds)));
        }
    @endphp

    <!-- ── Container For Export Content ──────────────────────────────── -->
    <div id="pdfReportContent">

        @if($activeLayout === 'timesheet')
        <!-- ══════════════════════════════════════════════════════════════════
             MODE 1: A4 DETAILED TIMESHEET (PER EMPLOYEE, FULL 4-PUNCH SESSIONS)
             Morning Clock In & Out, Afternoon Clock In & Out Section
             ══════════════════════════════════════════════════════════════════ -->
        @forelse($filteredRows as $empId => $row)
        @php
            $emp = $row['employee'];
            $summary = $row['summary'];
            $days = $row['days'];
        @endphp
        <div class="a4-page">
            <div>
                <!-- Timesheet Top Header -->
                <div class="timesheet-header">
                    <div class="header-top">
                        <div>
                            <div class="company-title">Wechecha Construction P.L.C</div>
                            <div class="company-subtitle">ConstructPro ERP &bull; Biometric Attendance &amp; Payroll Timesheet (A4)</div>
                        </div>
                        <div class="doc-title-badge">
                            Monthly Attendance Log &bull; የሰዓት ምዝገባ
                        </div>
                    </div>

                    <!-- Employee Profile Summary Card -->
                    <div class="emp-profile-card">
                        <div class="profile-field">
                            <label>Employee Name / የሰራተኛው ስም</label>
                            <span>{{ $emp->full_name }}</span>
                        </div>
                        <div class="profile-field">
                            <label>Employee Code / ID</label>
                            <span>{{ $emp->employee_code ?? 'EMP-' . $emp->id }}</span>
                        </div>
                        <div class="profile-field">
                            <label>Department / Role</label>
                            <span>{{ $emp->role_title ?: ($emp->department ?? 'General') }}</span>
                        </div>
                        <div class="profile-field">
                            <label>Period / ወር (Ethiopian)</label>
                            <span>{{ $period['full_label'] }}</span>
                        </div>
                    </div>
                </div>

                <!-- 4-PUNCH DETAILED TABLE -->
                <table class="timesheet-table">
                    <thead>
                        <tr>
                            <th rowspan="2" style="width: 28px;">#</th>
                            <th rowspan="2" style="width: 105px;">DATE (ETH / GREG)</th>
                            <th rowspan="2" style="width: 44px;">DAY</th>
                            <th colspan="2" class="th-morning">MORNING SESSION (ጠዋት)</th>
                            <th colspan="2" class="th-afternoon">AFTERNOON SESSION (ከሰዓት)</th>
                            <th rowspan="2" style="width: 46px;">HOURS</th>
                            <th rowspan="2" style="width: 65px;">STATUS</th>
                            <th rowspan="2">DUTY / SITE / REMARKS</th>
                        </tr>
                        <tr>
                            <th class="th-morning" style="width: 68px;">CLOCK IN (መግቢያ)</th>
                            <th class="th-morning" style="width: 68px;">CLOCK OUT (መውጫ)</th>
                            <th class="th-afternoon" style="width: 68px;">CLOCK IN (መግቢያ)</th>
                            <th class="th-afternoon" style="width: 68px;">CLOCK OUT (መውጫ)</th>
                        </tr>
                    </thead>
                    <tbody>
                        @php $dayIndex = 1; @endphp
                        @foreach($periodDays as $day)
                        @php
                            $dItem = $days[$day['greg_date']] ?? null;
                            $code = $dItem['code'] ?? '—';
                            $isLate = $dItem['is_late'] ?? false;
                            $lateMin = $dItem['late_minutes'] ?? 0;
                            $mIn = $dItem['morning_in'] ?? null;
                            $mOut = $dItem['morning_out'] ?? null;
                            $aIn = $dItem['afternoon_in'] ?? null;
                            $aOut = $dItem['afternoon_out'] ?? null;
                            $hours = $dItem['hours'] ?? null;
                            $notes = $dItem['notes'] ?? ($dItem['site_name'] ?? ($dItem['leave_title'] ?? ($dItem['holiday_name'] ?? '')));

                            $rowClass = match($code) {
                                'SUN' => 'row-sun',
                                'S'   => 'row-site',
                                'L'   => 'row-leave',
                                'H'   => 'row-holiday',
                                'A'   => 'row-absent',
                                default => ''
                            };
                        @endphp
                        <tr class="{{ $rowClass }}">
                            <td style="color: #64748b; font-family: monospace;">{{ $dayIndex++ }}</td>
                            <td style="text-align: left; padding-left: 6px;">
                                <strong>{{ $day['eth_day'] }} {{ substr($day['eth_label_en'], 0, 4) }}</strong>
                                <span style="color: #64748b; font-size: 8px;">({{ $day['greg_day'] }} {{ $day['greg_month'] }})</span>
                            </td>
                            <td style="font-weight: 600;">
                                {{ $day['day_name_en'] }}
                            </td>

                            <!-- 1. MORNING CLOCK IN -->
                            <td class="time-cell">
                                @if(!empty($mIn))
                                    <span class="{{ $isLate ? 'time-in-late' : 'time-in-text' }}">{{ $mIn }}</span>
                                    @if($isLate)
                                        <span class="late-pill">+{{ $lateMin }}m</span>
                                    @endif
                                @else
                                    <span style="color: #cbd5e1;">—</span>
                                @endif
                            </td>

                            <!-- 2. MORNING CLOCK OUT (Lunch Departure) -->
                            <td class="time-cell">
                                @if(!empty($mOut))
                                    <span class="time-out-text">{{ $mOut }}</span>
                                @else
                                    <span style="color: #cbd5e1;">—</span>
                                @endif
                            </td>

                            <!-- 3. AFTERNOON CLOCK IN (Lunch Return) -->
                            <td class="time-cell">
                                @if(!empty($aIn))
                                    <span class="time-in-text">{{ $aIn }}</span>
                                @else
                                    <span style="color: #cbd5e1;">—</span>
                                @endif
                            </td>

                            <!-- 4. AFTERNOON CLOCK OUT (End of Shift) -->
                            <td class="time-cell">
                                @if(!empty($aOut))
                                    <span class="time-out-text">{{ $aOut }}</span>
                                @else
                                    <span style="color: #cbd5e1;">—</span>
                                @endif
                            </td>

                            <!-- TOTAL HOURS WORKED -->
                            <td style="font-family: monospace; font-weight: 700;">
                                {{ $hours ? number_format($hours, 1) . 'h' : '—' }}
                            </td>

                            <!-- STATUS BADGE -->
                            <td>
                                @if($code === 'P')
                                    <span style="color: #15803d; font-weight: 800;">PRESENT</span>
                                @elseif($code === 'S')
                                    <span style="color: #0369a1; font-weight: 800;">SITE (S)</span>
                                @elseif($code === 'L')
                                    <span style="color: #1d4ed8; font-weight: 800;">LEAVE (L)</span>
                                @elseif($code === 'H')
                                    <span style="color: #7e22ce; font-weight: 800;">HOLIDAY (H)</span>
                                @elseif($code === 'A')
                                    <span style="color: #b91c1c; font-weight: 800;">ABSENT (A)</span>
                                @elseif($code === 'SUN')
                                    <span style="color: #64748b;">SUNDAY</span>
                                @else
                                    <span style="color: #cbd5e1;">—</span>
                                @endif
                            </td>

                            <!-- REMARKS / NOTES -->
                            <td style="text-align: left; font-size: 8px; color: #475569; padding-left: 5px;">
                                {{ $notes ?: '—' }}
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <!-- Page Bottom: Summary & Signatures -->
            <div>
                <!-- Monthly Summary Totals -->
                <div class="summary-box">
                    <div class="summary-item">
                        <span class="summary-val text-success">{{ $summary['present_days'] }}</span>
                        <span class="summary-lbl">Present (P)</span>
                    </div>
                    <div class="summary-item">
                        <span class="summary-val text-info">{{ $summary['site_days'] }}</span>
                        <span class="summary-lbl">Site Work (S)</span>
                    </div>
                    <div class="summary-item">
                        <span class="summary-val text-primary">{{ $summary['leave_days'] }}</span>
                        <span class="summary-lbl">Approved Leave</span>
                    </div>
                    <div class="summary-item">
                        <span class="summary-val" style="color: #7e22ce;">{{ $summary['holiday_days'] }}</span>
                        <span class="summary-lbl">Holidays</span>
                    </div>
                    <div class="summary-item">
                        <span class="summary-val text-warning">{{ $summary['late_days'] }}</span>
                        <span class="summary-lbl">Lates (&gt;08:40)</span>
                    </div>
                    <div class="summary-item">
                        <span class="summary-val text-danger">{{ $summary['penalty_days'] }}</span>
                        <span class="summary-lbl">Penalty (3:1)</span>
                    </div>
                    <div class="summary-item">
                        <span class="summary-val text-danger">{{ $summary['effective_absent'] }}</span>
                        <span class="summary-lbl">Eff. Absent</span>
                    </div>
                    <div class="summary-item">
                        <span class="summary-val text-dark">{{ $summary['total_hours'] }}h</span>
                        <span class="summary-lbl">Total Hours</span>
                    </div>
                </div>

                <!-- 3-Column Formal Verification Signatures -->
                <div class="signature-grid">
                    <div class="sign-card">
                        <div class="sign-title">Employee Signature / የሰራተኛው ፊርማ</div>
                        <div>Name: <strong>{{ $emp->full_name }}</strong></div>
                        <div style="margin-top: 8px;">Signature: ___________________________</div>
                    </div>
                    <div class="sign-card">
                        <div class="sign-title">Verified By: HR / Timekeeper</div>
                        <div>Name: _________________________________</div>
                        <div style="margin-top: 8px;">Signature: ___________________________</div>
                    </div>
                    <div class="sign-card">
                        <div class="sign-title">Approved By: Department Head / GM</div>
                        <div>Name: _________________________________</div>
                        <div style="margin-top: 8px;">Signature: ___________________________</div>
                    </div>
                </div>
            </div>
        </div>
        @empty
        <div class="a4-page" style="text-align: center; padding-top: 40px; color: #64748b;">
            <h5>No employee attendance records found for this period.</h5>
        </div>
        @endforelse

        @else
        <!-- ══════════════════════════════════════════════════════════════════
             MODE 2: MASTER MONTHLY 4-PUNCH MATRIX (LANDSCAPE A4/A3)
             ══════════════════════════════════════════════════════════════════ -->
        <div class="a4-landscape-page">
            <div class="timesheet-header">
                <div class="header-top">
                    <div>
                        <div class="company-title">Wechecha Construction P.L.C</div>
                        <div class="company-subtitle">Master Biometric Attendance Matrix &bull; Clock In &amp; Out Times (4 Sessions)</div>
                    </div>
                    <div class="doc-title-badge">
                        {{ $period['full_label'] }} ({{ $period['start_greg'] }} &rarr; {{ $period['end_greg'] }})
                    </div>
                </div>
            </div>

            <div class="matrix-table-wrap">
                <table class="matrix-view-table">
                    <thead>
                        <tr style="background:#0f172a; color:#fff;">
                            <th rowspan="2" style="width: 140px; text-align: left; padding: 4px;">EMPLOYEE</th>
                            @foreach($periodDays as $day)
                            <th class="{{ $day['is_sunday'] ? 'sun-col' : '' }}" style="min-width: 38px;">
                                <div>{{ $day['eth_day'] }}</div>
                                <div style="font-size: 6.5px;">{{ substr($day['eth_label_en'], 0, 4) }}</div>
                            </th>
                            @endforeach
                            <th rowspan="2">P</th>
                            <th rowspan="2">S</th>
                            <th rowspan="2">L</th>
                            <th rowspan="2">H</th>
                            <th rowspan="2">A</th>
                            <th rowspan="2">Late</th>
                            <th rowspan="2">Pen</th>
                            <th rowspan="2">Eff.Abs</th>
                            <th rowspan="2">Hrs</th>
                        </tr>
                        <tr style="background:#f1f5f9; color:#1e293b;">
                            @foreach($periodDays as $day)
                            <th class="{{ $day['is_sunday'] ? 'sun-col-sub' : '' }}" style="font-size: 7px;">
                                {{ $day['greg_day'] }} {{ $day['day_name_en'] }}
                            </th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($matrix as $empId => $row)
                        @php
                            $emp = $row['employee'];
                            $summary = $row['summary'];
                            $days = $row['days'];
                        @endphp
                        <tr>
                            <td style="text-align: left; padding: 2px 4px;">
                                <strong style="font-size: 8.5px;">{{ $emp->full_name }}</strong>
                                <div style="font-size: 7px; color: #64748b;">{{ $emp->employee_code ?? 'EMP' }} &bull; {{ $emp->role_title ?: ($emp->department ?? 'General') }}</div>
                            </td>

                            @foreach($periodDays as $day)
                            @php
                                $dItem = $days[$day['greg_date']] ?? null;
                                $code = $dItem['code'] ?? '—';
                                $isLate = $dItem['is_late'] ?? false;
                                $lateMin = $dItem['late_minutes'] ?? 0;
                                $mIn = $dItem['morning_in'] ?? null;
                                $mOut = $dItem['morning_out'] ?? null;
                                $aIn = $dItem['afternoon_in'] ?? null;
                                $aOut = $dItem['afternoon_out'] ?? null;

                                $cellBgClass = match($code) {
                                    'P' => $isLate ? 'bg-code-late' : 'bg-code-p',
                                    'S' => 'bg-code-s',
                                    'A' => 'bg-code-a',
                                    'L' => 'bg-code-l',
                                    'H' => 'bg-code-h',
                                    'SUN' => 'bg-code-sun',
                                    default => ''
                                };
                            @endphp
                            <td class="{{ $cellBgClass }}">
                                @if($code === 'P')
                                    <div style="font-size: 7px; line-height: 1.1; font-family: monospace;">
                                        <div class="{{ $isLate ? 'punch-late' : 'punch-ontime' }}">
                                            M: {{ $mIn ? substr($mIn, 0, 5) : '—' }} &bull; {{ $mOut ? substr($mOut, 0, 5) : '—' }}
                                        </div>
                                        <div style="color: #475569;">
                                            A: {{ $aIn ? substr($aIn, 0, 5) : '—' }} &bull; {{ $aOut ? substr($aOut, 0, 5) : '—' }}
                                        </div>
                                    </div>
                                @elseif($code === 'S')
                                    <span style="color:#0369a1; font-weight:800; font-size:7.5px;">S (Site)</span>
                                @elseif($code === 'L')
                                    <span style="color:#1d4ed8; font-weight:800; font-size:7.5px;">L</span>
                                @elseif($code === 'H')
                                    <span style="color:#7e22ce; font-weight:800; font-size:7.5px;">H</span>
                                @elseif($code === 'A')
                                    <span style="color:#b91c1c; font-weight:800; font-size:7.5px;">A</span>
                                @elseif($code === 'SUN')
                                    <span style="color:#94a3b8; font-size:7px;">SUN</span>
                                @else
                                    <span style="color:#cbd5e1;">—</span>
                                @endif
                            </td>
                            @endforeach

                            <td style="font-weight:700; color:#15803d;">{{ $summary['present_days'] }}</td>
                            <td style="font-weight:700; color:#0369a1;">{{ $summary['site_days'] }}</td>
                            <td style="font-weight:700; color:#1d4ed8;">{{ $summary['leave_days'] }}</td>
                            <td style="font-weight:700; color:#7e22ce;">{{ $summary['holiday_days'] }}</td>
                            <td style="font-weight:700; color:#b91c1c;">{{ $summary['absent_days'] }}</td>
                            <td style="font-weight:700; color:#c2410c;">{{ $summary['late_days'] }}</td>
                            <td style="font-weight:700; color:#b91c1c;">{{ $summary['penalty_days'] }}</td>
                            <td style="font-weight:800; background:#b91c1c; color:#fff;">{{ $summary['effective_absent'] }}</td>
                            <td style="font-weight:700;">{{ $summary['total_hours'] }}h</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <!-- Signature block -->
            <div class="signature-grid" style="margin-top: 20px;">
                <div class="sign-card">
                    <div class="sign-title">Prepared By: HR Officer / Timekeeper</div>
                    <div style="margin-top: 14px;">Signature: ___________________________</div>
                </div>
                <div class="sign-card">
                    <div class="sign-title">Verified By: HR Manager</div>
                    <div style="margin-top: 14px;">Signature: ___________________________</div>
                </div>
                <div class="sign-card">
                    <div class="sign-title">Approved By: General Manager</div>
                    <div style="margin-top: 14px;">Signature: ___________________________</div>
                </div>
            </div>
        </div>
        @endif

    </div>

    <!-- ── JavaScript for Layout Switching & PDF Generation ──────────── -->
    <script>
        function switchReportLayout(mode) {
            document.getElementById('layoutInput').value = mode;
            document.getElementById('reportFilterForm').submit();
        }

        function downloadPdfReport() {
            const btn = document.getElementById('btnDownloadPdf');
            const originalText = btn.innerHTML;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Generating A4 PDF...';
            btn.disabled = true;

            const element = document.getElementById('pdfReportContent');
            const isTimesheet = '{{ $activeLayout }}' === 'timesheet';

            const opt = {
                margin:       [3, 3, 3, 3],
                filename:     'Attendance-Timesheet-A4-{{ Str::slug($period["full_label"]) }}-{{ date("Y-m-d") }}.pdf',
                image:        { type: 'jpeg', quality: 0.98 },
                html2canvas:  { scale: 2, useCORS: true, logging: false },
                jsPDF:        { unit: 'mm', format: 'a4', orientation: isTimesheet ? 'portrait' : 'landscape' },
                pagebreak:    { mode: ['css', 'legacy'], after: '.a4-page' }
            };

            html2pdf().set(opt).from(element).save().then(function() {
                btn.innerHTML = originalText;
                btn.disabled = false;
            }).catch(function(err) {
                console.error('PDF generation error:', err);
                btn.innerHTML = originalText;
                btn.disabled = false;
                window.print();
            });
        }
    </script>
</body>
</html>
