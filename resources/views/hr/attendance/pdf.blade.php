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
            padding: 18px 0;
            font-size: 10px;
            line-height: 1.3;
        }

        /* ── Top Floating Action Toolbar (Non-Print) ─────────────────── */
        .no-print-toolbar {
            max-width: 98%;
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
            padding: 7px 14px;
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

        /* ── Sheet Container (Landscape A3/A4) ───────────────────────── */
        .sheet-container {
            width: 98%;
            max-width: 1750px;
            margin: 0 auto;
            background: #ffffff;
            padding: 16px 20px;
            border-radius: 6px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.25);
        }

        /* ── Report Header ───────────────────────────────────────────── */
        .report-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            border-bottom: 2px solid var(--primary);
            padding-bottom: 12px;
            margin-bottom: 12px;
            gap: 16px;
        }

        .company-brand {
            display: flex;
            flex-direction: column;
        }

        .company-name {
            font-size: 16px;
            font-weight: 800;
            color: var(--primary);
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }

        .company-sub {
            font-size: 10px;
            font-weight: 600;
            color: var(--text-muted);
            margin-top: 1px;
        }

        .report-title-box {
            text-align: center;
            flex-grow: 1;
        }

        .report-title {
            font-size: 15px;
            font-weight: 800;
            color: #0f172a;
            margin-bottom: 3px;
        }

        .report-subtitle {
            font-size: 11px;
            color: #334155;
            font-weight: 600;
        }

        .report-meta-box {
            text-align: right;
            font-size: 9.5px;
            color: var(--text-muted);
            line-height: 1.4;
        }

        /* ── KPI Summary Strip ───────────────────────────────────────── */
        .kpi-strip {
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: var(--bg-light);
            border: 1px solid var(--border-light);
            border-radius: 6px;
            padding: 8px 14px;
            margin-bottom: 12px;
            gap: 10px;
            flex-wrap: wrap;
        }

        .kpi-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 0 8px;
            border-right: 1px solid var(--border-light);
        }

        .kpi-item:last-child {
            border-right: none;
        }

        .kpi-val {
            font-size: 13px;
            font-weight: 800;
            font-family: monospace;
        }

        .kpi-lbl {
            font-size: 8.5px;
            font-weight: 600;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }

        /* ── Matrix Table Styling ────────────────────────────────────── */
        .table-wrapper {
            overflow-x: auto;
            border: 1px solid var(--border-color);
            border-radius: 4px;
            margin-bottom: 14px;
        }

        table.attendance-matrix {
            width: 100%;
            border-collapse: collapse;
            font-size: 9px;
            text-align: center;
            background: #ffffff;
        }

        table.attendance-matrix th,
        table.attendance-matrix td {
            border: 1px solid var(--border-color);
            padding: 3px 2px;
            vertical-align: middle;
        }

        table.attendance-matrix thead tr:first-child th {
            background-color: #0f172a;
            color: #ffffff;
            font-weight: 700;
            font-size: 9px;
            padding: 5px 2px;
        }

        table.attendance-matrix thead tr:nth-child(2) th {
            background-color: #f1f5f9;
            color: #1e293b;
            font-weight: 600;
            font-size: 8.5px;
            padding: 4px 2px;
        }

        .col-emp {
            width: 160px;
            min-width: 160px;
            max-width: 160px;
            text-align: left !important;
            padding: 4px 6px !important;
            background-color: #ffffff;
        }

        .emp-name {
            font-weight: 700;
            color: #0f172a;
            font-size: 9.5px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .emp-meta {
            font-size: 8px;
            color: var(--text-muted);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .emp-pin {
            font-family: monospace;
            font-size: 7.5px;
            background: #f1f5f9;
            padding: 1px 3px;
            border-radius: 3px;
            border: 1px solid #cbd5e1;
            display: inline-block;
        }

        /* ── Daily Cell Content ──────────────────────────────────────── */
        .day-cell {
            padding: 2px 1px !important;
            height: 34px;
            position: relative;
        }

        .time-box {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 1px;
            line-height: 1.1;
        }

        .time-in {
            font-weight: 700;
            font-family: monospace;
            font-size: 8.2px;
            color: var(--present-green);
            white-space: nowrap;
        }

        .time-in.is-late {
            color: var(--late-orange);
            font-weight: 800;
        }

        .time-out {
            font-family: monospace;
            font-size: 7.8px;
            color: #475569;
            white-space: nowrap;
        }

        .badge-late {
            background-color: #fed7aa;
            color: #9a3412;
            font-size: 6.5px;
            font-weight: 700;
            padding: 0.5px 2px;
            border-radius: 2px;
            display: inline-block;
            margin-top: 0.5px;
        }

        .badge-s {
            background-color: #e0f2fe;
            color: #0369a1;
            font-size: 8.5px;
            font-weight: 800;
            padding: 1px 3px;
            border-radius: 3px;
        }

        .badge-a {
            background-color: #fee2e2;
            color: #b91c1c;
            font-size: 8.5px;
            font-weight: 800;
            padding: 1px 3px;
            border-radius: 3px;
        }

        .badge-l {
            background-color: #dbeafe;
            color: #1d4ed8;
            font-size: 8.5px;
            font-weight: 800;
            padding: 1px 3px;
            border-radius: 3px;
        }

        .badge-h {
            background-color: #f3e8ff;
            color: #7e22ce;
            font-size: 8.5px;
            font-weight: 800;
            padding: 1px 3px;
            border-radius: 3px;
        }

        .badge-sun {
            color: #94a3b8;
            font-size: 8px;
            font-weight: 700;
        }

        /* ── Cell Backgrounds ────────────────────────────────────────── */
        .cell-p { background-color: var(--present-bg); }
        .cell-p-late { background-color: var(--late-bg); }
        .cell-s { background-color: var(--site-bg); }
        .cell-a { background-color: var(--absent-bg); }
        .cell-l { background-color: #eff6ff; }
        .cell-h { background-color: #faf5ff; }
        .cell-sun { background-color: var(--sun-bg); color: #94a3b8; }
        .cell-dash { background-color: #ffffff; color: #cbd5e1; }

        /* ── Summary Columns ─────────────────────────────────────────── */
        .col-sum {
            font-weight: 700;
            font-family: monospace;
            font-size: 9px;
            width: 28px;
            min-width: 28px;
        }

        .col-sum-p { background-color: #f0fdf4; color: #166534; }
        .col-sum-s { background-color: #f0f9ff; color: #075985; }
        .col-sum-l { background-color: #eff6ff; color: #1e40af; }
        .col-sum-h { background-color: #faf5ff; color: #6b21a8; }
        .col-sum-a { background-color: #fef2f2; color: #991b1b; }
        .col-sum-late { background-color: #fff7ed; color: #9a3412; }
        .col-sum-pen { background-color: #fee2e2; color: #b91c1c; }
        .col-sum-eff { background-color: #b91c1c; color: #ffffff; }
        .col-sum-hrs { background-color: #f8fafc; color: #0f172a; }

        /* ── Legend & Signatures ─────────────────────────────────────── */
        .report-footer {
            margin-top: 10px;
        }

        .legend-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 8px;
            font-size: 8px;
            color: #475569;
            border-top: 1px dashed var(--border-color);
            padding-top: 6px;
            margin-bottom: 16px;
        }

        .legend-item {
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .sign-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            margin-top: 24px;
            padding-top: 10px;
        }

        .sign-box {
            border-top: 1.5px solid #0f172a;
            padding-top: 6px;
            font-size: 8.5px;
            color: #334155;
        }

        .sign-role {
            font-weight: 800;
            color: #0f172a;
            font-size: 9.5px;
            text-transform: uppercase;
        }

        /* ── Print Media Optimization ────────────────────────────────── */
        @media print {
            body {
                background: #ffffff !important;
                padding: 0 !important;
                color: #000000 !important;
            }

            .no-print-toolbar {
                display: none !important;
            }

            .sheet-container {
                width: 100% !important;
                max-width: 100% !important;
                padding: 0 !important;
                box-shadow: none !important;
                border-radius: 0 !important;
            }

            @page {
                size: landscape;
                margin: 6mm 4mm;
            }

            table.attendance-matrix {
                page-break-inside: auto;
            }

            table.attendance-matrix tr {
                page-break-inside: avoid;
                page-break-after: auto;
            }

            table.attendance-matrix thead {
                display: table-header-group;
            }

            .sign-grid {
                page-break-inside: avoid;
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

            <!-- Quick Filter Form for Month / Date Option -->
            <form action="{{ route('attendance.export-pdf') }}" method="GET" class="filter-form">
                <input type="hidden" name="staff_type" value="{{ $staffType }}">

                <!-- Date Option Mode: Period vs Custom -->
                <select name="period" class="form-select-sm" id="periodSelect" onchange="this.form.submit()">
                    <optgroup label="Ethiopian Payroll Periods (26th–25th)">
                        @foreach($availablePeriods as $p)
                        <option value="{{ $p['period_key'] }}" {{ (!$period['is_custom'] ?? false) && $p['period_key'] === $selectedPeriodKey ? 'selected' : '' }}>
                            {{ $p['full_label'] }} ({{ $p['label_en'] }})
                        </option>
                        @endforeach
                    </optgroup>
                </select>

                <!-- Custom Gregorian Date Range Option -->
                <span class="text-muted small" style="font-size: 11px;">or Dates:</span>
                <input type="date" name="start_date" value="{{ request('start_date', $period['start_greg']) }}" class="form-control-sm" title="Start Date">
                <span class="text-muted" style="font-size: 10px;">&rarr;</span>
                <input type="date" name="end_date" value="{{ request('end_date', $period['end_greg']) }}" class="form-control-sm" title="End Date">

                <!-- Staff Type -->
                <select name="staff_type" class="form-select-sm" onchange="this.form.submit()">
                    <option value="office" {{ $staffType === 'office' ? 'selected' : '' }}>Head Office</option>
                    <option value="site" {{ $staffType === 'site' ? 'selected' : '' }}>Site &amp; Project</option>
                    <option value="driver" {{ $staffType === 'driver' ? 'selected' : '' }}>Driver Dept (GS)</option>
                    <option value="all" {{ $staffType === 'all' ? 'selected' : '' }}>All Staff</option>
                </select>

                <!-- Department Filter -->
                <select name="department" class="form-select-sm" onchange="this.form.submit()">
                    <option value="">All Departments</option>
                    @foreach($departments as $dept)
                    <option value="{{ $dept }}" {{ request('department') === $dept ? 'selected' : '' }}>{{ $dept }}</option>
                    @endforeach
                </select>

                <button type="submit" class="btn btn-primary" title="Apply custom dates and filters">
                    <i class="fas fa-filter"></i> Apply
                </button>
            </form>
        </div>

        <div class="toolbar-group">
            <button onclick="downloadPdfReport()" id="btnDownloadPdf" class="btn btn-danger">
                <i class="fas fa-file-pdf"></i> Download PDF
            </button>
            <button onclick="window.print()" class="btn btn-dark">
                <i class="fas fa-print"></i> Print / Save as PDF
            </button>
        </div>
    </div>

    <!-- ── Report Container for Print / PDF Export ───────────────────── -->
    <div class="sheet-container" id="pdfReportContent">

        <!-- Executive Header -->
        <div class="report-header">
            <div class="company-brand">
                <div class="company-name">Wechecha Construction P.L.C</div>
                <div class="company-sub">ConstructPro ERP &bull; Human Resources &amp; Biometric Attendance System</div>
            </div>

            <div class="report-title-box">
                <div class="report-title">
                    {{ $staffTypeLabel }} Attendance Matrix &bull; Clock In &amp; Out Times
                </div>
                <div class="report-subtitle">
                    Period: <strong>{{ $period['full_label'] }}</strong>
                    @if(!empty($period['label_am']))
                        &bull; {{ $period['label_am'] }}
                    @endif
                    &bull; ({{ $period['start_greg'] }} &rarr; {{ $period['end_greg'] }})
                </div>
            </div>

            <div class="report-meta-box">
                <div><strong>Export Date:</strong> {{ now()->format('M d, Y h:i A') }}</div>
                <div><strong>Ethiopian:</strong> {{ \App\Helpers\EthiopianCalendar::format(today(), 'am') }}</div>
                <div><strong>Generated By:</strong> {{ auth()->user()->name ?? 'System Admin' }}</div>
            </div>
        </div>

        <!-- KPI Summary Strip -->
        <div class="kpi-strip">
            <div class="kpi-item">
                <span class="kpi-val text-dark">{{ number_format($stats['total_staff']) }}</span>
                <span class="kpi-lbl">Total Staff</span>
            </div>
            <div class="kpi-item">
                <span class="kpi-val text-success">{{ number_format($stats['total_present']) }}</span>
                <span class="kpi-lbl">Present Punches</span>
            </div>
            <div class="kpi-item">
                <span class="kpi-val text-info">{{ number_format($stats['total_site']) }}</span>
                <span class="kpi-lbl">Site Deploy (S)</span>
            </div>
            <div class="kpi-item">
                <span class="kpi-val text-warning">{{ number_format($stats['total_late']) }}</span>
                <span class="kpi-lbl">Late Punches (&gt;08:40)</span>
            </div>
            <div class="kpi-item">
                <span class="kpi-val text-danger">{{ number_format($stats['total_absent']) }}</span>
                <span class="kpi-lbl">Base Absent</span>
            </div>
            <div class="kpi-item">
                <span class="kpi-val text-danger">{{ number_format($stats['total_penalty_days']) }}</span>
                <span class="kpi-lbl">Late Penalty Days (3:1)</span>
            </div>
            <div class="kpi-item">
                <span class="kpi-val text-danger" style="color: #b91c1c;">{{ number_format($stats['total_effective_absent']) }}</span>
                <span class="kpi-lbl">Effective Absent Days</span>
            </div>
            <div class="kpi-item">
                <span class="kpi-val text-secondary">{{ count($periodDays) }}</span>
                <span class="kpi-lbl">Period Days</span>
            </div>
        </div>

        <!-- Matrix Table -->
        <div class="table-wrapper">
            <table class="attendance-matrix">
                <thead>
                    <!-- Row 1: Ethiopian Dates -->
                    <tr>
                        <th class="col-emp" rowspan="2">
                            <div>EMPLOYEE INFORMATION</div>
                            <div style="font-size: 7.5px; opacity: 0.8; font-weight: normal;">CODE &bull; DEPT &bull; DEVICE PIN</div>
                        </th>

                        @foreach($periodDays as $day)
                        <th style="min-width: 36px; {{ $day['is_sunday'] ? 'background-color: #334155;' : '' }}">
                            <div>{{ $day['eth_day'] }}</div>
                            <div style="font-size: 6.8px; opacity: 0.85;">{{ substr($day['eth_label_en'], 0, 4) }}</div>
                        </th>
                        @endforeach

                        <!-- Summary Group -->
                        <th class="col-sum col-sum-p" rowspan="2" title="Total Present Days">P</th>
                        <th class="col-sum col-sum-s" rowspan="2" title="Total Site Days">S</th>
                        <th class="col-sum col-sum-l" rowspan="2" title="Approved Leave">L</th>
                        <th class="col-sum col-sum-h" rowspan="2" title="Public Holiday">H</th>
                        <th class="col-sum col-sum-a" rowspan="2" title="Base Absent Days">A</th>
                        <th class="col-sum col-sum-late" rowspan="2" title="Late Punches">Late</th>
                        <th class="col-sum col-sum-pen" rowspan="2" title="Penalty Days">Pen.</th>
                        <th class="col-sum col-sum-eff" rowspan="2" title="Effective Absent = A + Penalty">Eff. Abs</th>
                        <th class="col-sum col-sum-hrs" rowspan="2" title="Total Credited Hours">Hrs</th>
                    </tr>

                    <!-- Row 2: Gregorian Dates & Weekday -->
                    <tr>
                        @foreach($periodDays as $day)
                        <th style="font-size: 7.5px; {{ $day['is_sunday'] ? 'background-color: #e2e8f0; color: #475569;' : '' }}">
                            <div>{{ $day['greg_day'] }} {{ $day['greg_month'] }}</div>
                            <div style="font-weight: 700;">{{ $day['day_name_en'] }}</div>
                        </th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @forelse($matrix as $empId => $row)
                    @php
                        $emp = $row['employee'];
                        $summary = $row['summary'];
                        $days = $row['days'];
                    @endphp
                    <tr>
                        <!-- Left Employee Column -->
                        <td class="col-emp">
                            <div class="emp-name" title="{{ $emp->full_name }}">{{ $emp->full_name }}</div>
                            <div class="emp-meta">
                                <strong>{{ $emp->employee_code ?? 'EMP' }}</strong> &bull; {{ $emp->role_title ?: ($emp->department ?? 'General') }}
                            </div>
                            <div style="margin-top: 1px;">
                                @if(!empty($emp->device_user_id))
                                    <span class="emp-pin" title="Machine PIN">PIN: {{ $emp->device_user_id }}</span>
                                @elseif($emp->isDriver())
                                    <span class="emp-pin" style="color: #15803d;">DRIVER</span>
                                @elseif($emp->isSiteDriverOrRemote())
                                    <span class="emp-pin" style="color: #0369a1;">SITE</span>
                                @else
                                    <span class="emp-pin" style="color: #b91c1c;">NO PIN</span>
                                @endif
                                @if($emp->project)
                                    <span style="font-size: 7.2px; color: #0284c7; margin-left: 2px;">{{ $emp->project->name }}</span>
                                @endif
                            </div>
                        </td>

                        <!-- Daily Status & Clock In/Out Cells -->
                        @foreach($periodDays as $day)
                        @php
                            $dItem = $days[$day['greg_date']] ?? null;
                            $code = $dItem['code'] ?? '—';
                            $isLate = $dItem['is_late'] ?? false;
                            $lateMin = $dItem['late_minutes'] ?? 0;
                            $punchIn = $dItem['punch_in'] ?? null;
                            $punchOut = $dItem['punch_out'] ?? null;

                            $cellBgClass = match($code) {
                                'P' => $isLate ? 'cell-p-late' : 'cell-p',
                                'S' => 'cell-s',
                                'A' => 'cell-a',
                                'L' => 'cell-l',
                                'H' => 'cell-h',
                                'SUN' => 'cell-sun',
                                default => 'cell-dash'
                            };
                        @endphp
                        <td class="day-cell {{ $cellBgClass }}">
                            @if($code === 'P')
                                <div class="time-box">
                                    <span class="time-in {{ $isLate ? 'is-late' : '' }}">
                                        &rarr;| {{ $punchIn ?? '—' }}
                                    </span>
                                    <span class="time-out">
                                        [&rarr; {{ $punchOut ?? '—' }}]
                                    </span>
                                    @if($isLate)
                                        <span class="badge-late">+{{ $lateMin }}m</span>
                                    @endif
                                </div>
                            @elseif($code === 'S')
                                <div class="time-box">
                                    <span class="time-in" style="color: #0369a1;">
                                        &rarr;| {{ $punchIn ?? '08:40 AM' }}
                                    </span>
                                    <span class="time-out">
                                        [&rarr; {{ $punchOut ?? '05:30 PM' }}]
                                    </span>
                                    <span class="badge-s">S</span>
                                </div>
                            @elseif($code === 'L')
                                <span class="badge-l">L</span>
                            @elseif($code === 'H')
                                <span class="badge-h">H</span>
                            @elseif($code === 'A')
                                <span class="badge-a">A</span>
                            @elseif($code === 'SUN')
                                @if(!empty($punchIn))
                                    <div class="time-box">
                                        <span class="time-in">&rarr;| {{ $punchIn }}</span>
                                        <span class="time-out">OT</span>
                                    </div>
                                @else
                                    <span class="badge-sun">SUN</span>
                                @endif
                            @else
                                <span style="color: #cbd5e1;">—</span>
                            @endif
                        </td>
                        @endforeach

                        <!-- Summary Column Values -->
                        <td class="col-sum col-sum-p">{{ $summary['present_days'] }}</td>
                        <td class="col-sum col-sum-s">{{ $summary['site_days'] }}</td>
                        <td class="col-sum col-sum-l">{{ $summary['leave_days'] }}</td>
                        <td class="col-sum col-sum-h">{{ $summary['holiday_days'] }}</td>
                        <td class="col-sum col-sum-a">{{ $summary['absent_days'] }}</td>
                        <td class="col-sum col-sum-late">{{ $summary['late_days'] }}</td>
                        <td class="col-sum col-sum-pen">{{ $summary['penalty_days'] }}</td>
                        <td class="col-sum col-sum-eff">{{ $summary['effective_absent'] }}</td>
                        <td class="col-sum col-sum-hrs">{{ $summary['total_hours'] ?? '0' }}</td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="{{ count($periodDays) + 10 }}" style="padding: 20px; color: #64748b; font-size: 11px;">
                            No active employee records matched the selected criteria for this period.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- Footer: Legend & Official Signatures -->
        <div class="report-footer">
            <div class="legend-row">
                <div class="legend-item">
                    <strong>Legend:</strong>
                </div>
                <div class="legend-item">
                    <span class="badge-s" style="background:#f0fdf4; color:#15803d; border:1px solid #15803d;">&rarr;| In / Out</span>
                    <span>Present (Biometric Punch)</span>
                </div>
                <div class="legend-item">
                    <span class="badge-late">+Late</span>
                    <span>&gt; 08:40 AM Cutoff (3 Lates = 1 Absent Day)</span>
                </div>
                <div class="legend-item">
                    <span class="badge-s">S</span>
                    <span>Site Deployment / Trip (Credited)</span>
                </div>
                <div class="legend-item">
                    <span class="badge-l">L</span>
                    <span>Approved Leave</span>
                </div>
                <div class="legend-item">
                    <span class="badge-h">H</span>
                    <span>Public Holiday</span>
                </div>
                <div class="legend-item">
                    <span class="badge-a">A</span>
                    <span>Absent (Unexcused)</span>
                </div>
                <div class="legend-item">
                    <span class="badge-sun">SUN</span>
                    <span>Sunday Rest Day</span>
                </div>
            </div>

            <!-- Executive Signature Block -->
            <div class="sign-grid">
                <div class="sign-box">
                    <div class="sign-role">Prepared By: HR Officer / Timekeeper</div>
                    <div style="margin-top: 24px;">Name: __________________________________</div>
                    <div style="margin-top: 6px;">Sign &amp; Date: ___________________________</div>
                </div>

                <div class="sign-box">
                    <div class="sign-role">Verified By: HR Manager</div>
                    <div style="margin-top: 24px;">Name: __________________________________</div>
                    <div style="margin-top: 6px;">Sign &amp; Date: ___________________________</div>
                </div>

                <div class="sign-box">
                    <div class="sign-role">Approved By: General Manager</div>
                    <div style="margin-top: 24px;">Name: __________________________________</div>
                    <div style="margin-top: 6px;">Sign &amp; Date: ___________________________</div>
                </div>
            </div>
        </div>

    </div>

    <!-- ── Client-Side PDF Generation Script ─────────────────────────── -->
    <script>
        function downloadPdfReport() {
            const btn = document.getElementById('btnDownloadPdf');
            const originalText = btn.innerHTML;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Generating Landscape PDF...';
            btn.disabled = true;

            const element = document.getElementById('pdfReportContent');
            const opt = {
                margin:       [4, 4, 4, 4],
                filename:     'Attendance-ClockInOut-{{ Str::slug($period["full_label"]) }}-{{ date("Y-m-d") }}.pdf',
                image:        { type: 'jpeg', quality: 0.98 },
                html2canvas:  { scale: 2, useCORS: true, logging: false },
                jsPDF:        { unit: 'mm', format: 'a3', orientation: 'landscape' },
                pagebreak:    { mode: ['css', 'legacy'] }
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
