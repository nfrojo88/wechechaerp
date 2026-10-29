<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Receipts Register & Evidence Slips — {{ $monthInfo['title'] ?? 'Tax Audit' }} — Wechecha Construction</title>

    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Noto+Sans+Ethiopic:wght@400;500;600;700&display=swap" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <!-- html2pdf.js for client-side PDF export -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    <!-- Mozilla PDF.js for rendering uploaded PDF receipts to high-DPI canvas -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>

    <style>
        :root {
            --primary: #1e3a8a;
            --primary-dark: #0f172a;
            --secondary: #0284c7;
            --success: #15803d;
            --warning: #b45309;
            --danger: #b91c1c;
            --text-main: #1e293b;
            --text-muted: #64748b;
            --border-color: #cbd5e1;
            --bg-light: #f8fafc;
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
            padding: 24px 0 40px 0;
            font-size: 11px;
            line-height: 1.4;
        }

        /* ── Top Floating Action Toolbar (Hidden in Print & PDF) ─────── */
        .no-print-toolbar {
            max-width: 1080px;
            width: calc(100% - 32px);
            margin: 0 auto 20px auto;
            background: #ffffff;
            padding: 10px 18px;
            border-radius: 12px;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.2), 0 8px 10px -6px rgba(0, 0, 0, 0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
            border: 1px solid #e2e8f0;
            position: sticky;
            top: 15px;
            z-index: 1000;
        }

        .toolbar-group {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }

        .month-selector-form {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            background: #f1f5f9;
            border: 1px solid #cbd5e1;
            border-radius: 24px;
            padding: 3px 6px;
        }

        .month-nav-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 26px;
            height: 26px;
            border-radius: 50%;
            background: #ffffff;
            border: 1px solid #cbd5e1;
            color: #475569;
            text-decoration: none;
            transition: all 0.15s ease;
            box-shadow: 0 1px 2px rgba(0,0,0,0.05);
            font-size: 11px;
        }

        .month-nav-btn:hover {
            background: #1e3a8a;
            color: #ffffff;
            border-color: #1e3a8a;
        }

        .month-select-input {
            border: none;
            background: transparent;
            font-size: 11.5px;
            color: #0f172a;
            font-weight: 700;
            padding: 3px 6px;
            cursor: pointer;
            outline: none;
        }

        .month-select-input:focus {
            outline: none;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 12px;
            font-weight: 600;
            padding: 7px 14px;
            border-radius: 8px;
            border: 1px solid transparent;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.15s ease-in-out;
            line-height: 1.2;
        }

        .btn-danger { background: #dc2626; color: #fff; }
        .btn-danger:hover { background: #b91c1c; }

        .btn-primary { background: #1e3a8a; color: #fff; }
        .btn-primary:hover { background: #0f172a; }

        .btn-light { background: #f1f5f9; color: #334155; border-color: #cbd5e1; }
        .btn-light:hover { background: #e2e8f0; color: #0f172a; }

        .btn-pill {
            border-radius: 20px;
            padding: 4px 11px;
            font-size: 11px;
        }

        .btn-pill.active {
            background: #1e3a8a;
            color: #ffffff;
            border-color: #1e3a8a;
        }

        /* ── PDF Container & Page Physical Sheets (Exact A4 Portrait) ── */
        #receiptsPdfDocument {
            width: 840px;
            margin: 0 auto;
        }

        /* Each physical page is exactly A4 */
        .pdf-page-sheet {
            width: 840px;
            height: 1188px; /* Standard A4 ratio */
            min-height: 1188px;
            max-height: 1188px;
            background: #ffffff;
            margin: 0 auto 30px auto;
            padding: 28px 32px;
            border-radius: 6px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.25);
            border: 1px solid #cbd5e1;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            box-sizing: border-box;
            page-break-after: always !important;
            break-after: page !important;
            page-break-inside: avoid !important;
            break-inside: avoid !important;
            overflow: hidden;
            position: relative;
        }

        /* ── Page 1: Header Styles ─────────────────────────────────── */
        .header-section {
            border-bottom: 2px solid #1e3a8a;
            padding-bottom: 12px;
            margin-bottom: 14px;
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 16px;
        }

        .company-header h1 {
            font-size: 16px;
            font-weight: 800;
            color: #0f172a;
            letter-spacing: -0.02em;
            margin-bottom: 2px;
            text-transform: uppercase;
        }

        .company-header h2 {
            font-size: 12px;
            font-weight: 700;
            color: #1e3a8a;
            margin-bottom: 3px;
        }

        .company-header p {
            font-size: 9.5px;
            color: var(--text-muted);
            line-height: 1.35;
        }

        .report-badge-box {
            text-align: right;
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            gap: 4px;
        }

        .badge-pill {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 9px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.03em;
        }

        .badge-red { background: #fee2e2; color: #991b1b; border: 1px solid #fca5a5; }
        .badge-blue { background: #e0f2fe; color: #0369a1; border: 1px solid #bae6fd; }
        .badge-green { background: #dcfce7; color: #15803d; border: 1px solid #86efac; }
        .badge-dark { background: #0f172a; color: #ffffff; }

        /* ── Summary KPI Strip ──────────────────────────────────────── */
        .kpi-strip {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 8px;
            margin-bottom: 14px;
        }

        .kpi-card {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            padding: 8px 10px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        .kpi-card .kpi-label {
            font-size: 8.5px;
            font-weight: 700;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.04em;
            margin-bottom: 2px;
            display: flex;
            align-items: center;
            gap: 3px;
        }

        .kpi-card .kpi-value {
            font-size: 12.5px;
            font-weight: 800;
            color: #0f172a;
            font-family: 'Inter', monospace;
        }

        .kpi-card .kpi-sub {
            font-size: 8px;
            color: var(--text-muted);
            margin-top: 1px;
        }

        /* ── Section Titles ────────────────────────────────────────── */
        .section-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: #f1f5f9;
            border-left: 4px solid #1e3a8a;
            padding: 6px 10px;
            margin-bottom: 8px;
            border-radius: 0 4px 4px 0;
        }

        .section-header h3 {
            font-size: 11px;
            font-weight: 700;
            color: #0f172a;
            text-transform: uppercase;
            letter-spacing: 0.03em;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .section-header span {
            font-size: 9.5px;
            color: var(--text-muted);
            font-weight: 600;
        }

        /* ── Master Register Table ─────────────────────────────────── */
        .table-wrap {
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            overflow: hidden;
            flex-grow: 1;
            max-height: 720px;
            overflow-y: auto;
        }

        table.receipts-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 9.5px;
            text-align: left;
        }

        table.receipts-table thead {
            background: #1e293b;
            color: #ffffff;
            position: sticky;
            top: 0;
            z-index: 2;
        }

        table.receipts-table th {
            padding: 6px 7px;
            font-size: 8.5px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.03em;
            border-bottom: 1px solid #cbd5e1;
        }

        table.receipts-table td {
            padding: 5px 7px;
            border-bottom: 1px solid #f1f5f9;
            color: #334155;
            vertical-align: middle;
        }

        table.receipts-table tbody tr:nth-child(even) {
            background: #f8fafc;
        }

        table.receipts-table tfoot {
            background: #f1f5f9;
            font-weight: 800;
            border-top: 2px solid #cbd5e1;
            position: sticky;
            bottom: 0;
        }

        table.receipts-table tfoot td {
            padding: 6px 7px;
            color: #0f172a;
            font-size: 10px;
        }

        .font-mono {
            font-family: 'Inter', monospace;
            font-variant-numeric: tabular-nums;
        }

        .text-right { text-align: right; }
        .text-center { text-align: center; }

        /* ── ONE FULL PAGE PER UPLOADED RECEIPT PICTURE ─────────────── */
        .receipt-page-micro-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1.5px solid #1e3a8a;
            padding-bottom: 6px;
            margin-bottom: 8px;
        }

        .receipt-page-micro-header .brand-title {
            font-size: 11px;
            font-weight: 800;
            color: #0f172a;
            letter-spacing: -0.01em;
            text-transform: uppercase;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .receipt-page-micro-header .page-tag {
            font-size: 9.5px;
            font-weight: 700;
            color: #1e3a8a;
            font-family: 'Inter', monospace;
        }

        /* ── Comprehensive Receipt Description Box ─────────────────── */
        .receipt-desc-card {
            background: #ffffff;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            padding: 10px 14px;
            margin-bottom: 10px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        }

        .desc-card-top-row {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            border-bottom: 1px solid #f1f5f9;
            padding-bottom: 6px;
            margin-bottom: 6px;
        }

        .desc-vendor-name {
            font-size: 13px;
            font-weight: 800;
            color: #0f172a;
        }

        .desc-vendor-tin {
            font-size: 10px;
            color: #475569;
            font-family: 'Inter', monospace;
        }

        .desc-card-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 8px;
            margin-bottom: 8px;
        }

        .desc-cell {
            background: #f8fafc;
            border: 1px solid #f1f5f9;
            border-radius: 4px;
            padding: 5px 8px;
        }

        .desc-cell .cell-label {
            font-size: 7.5px;
            font-weight: 700;
            text-transform: uppercase;
            color: var(--text-muted);
            letter-spacing: 0.03em;
            margin-bottom: 1px;
        }

        .desc-cell .cell-value {
            font-size: 10.5px;
            font-weight: 700;
            color: #0f172a;
        }

        .desc-financial-bar {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            background: #f1f5f9;
            border-radius: 5px;
            padding: 6px 10px;
            border: 1px solid #e2e8f0;
            text-align: center;
        }

        .desc-fin-col {
            border-right: 1px solid #cbd5e1;
            padding: 0 8px;
        }

        .desc-fin-col:last-child {
            border-right: none;
        }

        .desc-fin-label {
            font-size: 8px;
            font-weight: 700;
            text-transform: uppercase;
            color: var(--text-muted);
            margin-bottom: 1px;
        }

        .desc-fin-amount {
            font-size: 12px;
            font-weight: 800;
            font-family: 'Inter', monospace;
        }

        .desc-purpose-line {
            margin-top: 6px;
            font-size: 9.5px;
            color: #334155;
            background: #f8fafc;
            padding: 4px 8px;
            border-radius: 4px;
            border-left: 3px solid #1e3a8a;
        }

        /* ── Full-Page Focus Picture Stage ─────────────────────────── */
        .receipt-picture-stage {
            flex-grow: 1;
            height: 770px;
            max-height: 770px;
            width: 100%;
            background: #f8fafc;
            border: 2px dashed #94a3b8;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            position: relative;
            padding: 10px;
            box-sizing: border-box;
            text-align: center;
        }

        .receipt-picture-stage img {
            max-width: 100%;
            max-height: 750px;
            width: auto;
            height: auto;
            object-fit: contain;
            margin: auto;
            display: block;
            border-radius: 4px;
            box-shadow: 0 4px 14px rgba(0, 0, 0, 0.15);
            background: #ffffff;
        }

        /* ── High-DPI Uploaded PDF Canvas Stage ───────────────────── */
        .pdf-canvas-container {
            width: 100%;
            height: 100%;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            position: relative;
            overflow: hidden;
        }

        .pdf-rendered-canvas {
            max-width: 100%;
            max-height: 720px;
            width: auto;
            height: auto;
            object-fit: contain;
            border-radius: 4px;
            box-shadow: 0 4px 14px rgba(0, 0, 0, 0.16);
            background: #ffffff;
            display: none; /* Shown after render */
        }

        .pdf-loading-indicator {
            text-align: center;
            padding: 30px;
            color: #64748b;
        }

        .pdf-loading-indicator i {
            font-size: 36px;
            color: #1e3a8a;
            margin-bottom: 12px;
            display: block;
        }

        .pdf-loading-indicator div {
            font-size: 11.5px;
            font-weight: 600;
            color: #0f172a;
        }

        .pdf-loading-indicator .sub-meta {
            font-size: 9px;
            color: #94a3b8;
            margin-top: 4px;
            font-family: 'Inter', monospace;
        }

        .pdf-info-floating-bar {
            position: absolute;
            bottom: 8px;
            display: none; /* Shown after render */
            align-items: center;
            gap: 12px;
            background: rgba(15, 23, 42, 0.90);
            backdrop-filter: blur(4px);
            color: #ffffff;
            padding: 5px 14px;
            border-radius: 20px;
            font-size: 9.5px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.25);
            z-index: 5;
        }

        .pdf-info-floating-bar a.pdf-open-link {
            color: #60a5fa;
            text-decoration: underline;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }

        .pdf-info-floating-bar a.pdf-open-link:hover {
            color: #93c5fd;
        }

        .receipt-pdf-placeholder {
            text-align: center;
            padding: 40px 20px;
            color: #475569;
        }

        .receipt-pdf-placeholder i {
            font-size: 64px;
            color: #dc2626;
            margin-bottom: 16px;
            display: block;
        }

        .receipt-pdf-placeholder h4 {
            font-size: 16px;
            font-weight: 700;
            color: #0f172a;
            margin-bottom: 6px;
        }

        .receipt-pdf-placeholder p {
            font-size: 11px;
            color: #64748b;
            margin-bottom: 16px;
        }

        .receipt-pdf-placeholder a.doc-link {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 16px;
            border-radius: 6px;
            background: #1e3a8a;
            color: #ffffff;
            font-weight: 600;
            text-decoration: none;
            font-size: 12px;
        }

        .receipt-voucher-placeholder {
            text-align: center;
            padding: 40px 20px;
            color: #475569;
        }

        .receipt-voucher-placeholder i {
            font-size: 54px;
            color: #64748b;
            margin-bottom: 12px;
            display: block;
            opacity: 0.6;
        }

        /* ── Full Page Footer ──────────────────────────────────────── */
        .receipt-page-footer {
            border-top: 1px solid #cbd5e1;
            padding-top: 8px;
            margin-top: 10px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 8.5px;
            color: #64748b;
        }

        /* ── Sign-off Block ─────────────────────────────────────────── */
        .signoff-section {
            border: 1px solid #cbd5e1;
            background: #f8fafc;
            border-radius: 6px;
            padding: 10px 14px;
            margin-top: 12px;
        }

        .signoff-title {
            font-size: 10px;
            font-weight: 700;
            color: #0f172a;
            text-transform: uppercase;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .signoff-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 16px;
            text-align: center;
        }

        .signoff-box {
            border-top: 1px dashed #94a3b8;
            padding-top: 6px;
        }

        .signoff-role {
            font-size: 9px;
            font-weight: 700;
            color: #1e3a8a;
            margin-bottom: 1px;
        }

        .signoff-name {
            font-size: 9px;
            color: #475569;
        }

        .signoff-date {
            font-size: 8px;
            color: #94a3b8;
            margin-top: 3px;
        }

        /* ── Print Specific Rules ───────────────────────────────────── */
        @media print {
            body {
                background: #ffffff !important;
                padding: 0 !important;
            }

            .no-print-toolbar {
                display: none !important;
            }

            .pdf-info-floating-bar {
                display: none !important;
            }

            #receiptsPdfDocument {
                width: 100% !important;
                margin: 0 !important;
            }

            .pdf-page-sheet {
                width: 100% !important;
                height: 100vh !important;
                min-height: 100vh !important;
                max-height: 100vh !important;
                margin: 0 !important;
                padding: 18px 24px !important;
                border: none !important;
                box-shadow: none !important;
                border-radius: 0 !important;
                page-break-after: always !important;
                break-after: page !important;
            }

            .receipt-picture-stage {
                height: 790px !important;
                max-height: 790px !important;
                border-color: #cbd5e1 !important;
            }

            .receipt-picture-stage img {
                max-height: 770px !important;
            }

            .pdf-rendered-canvas {
                max-height: 770px !important;
                box-shadow: none !important;
            }
        }
    </style>
</head>
<body>

    {{-- ── Floating Action Toolbar (Hidden in Print & PDF export) ──────────────── --}}
    <div class="no-print-toolbar">
        {{-- Left: Back Button & Month/Year Selector Form --}}
        <div class="toolbar-group">
            <a href="{{ route('finance.tax-deductions.movements', ['ethiopian_year' => $selectedEthYear, 'ethiopian_month' => $selectedEthMonth]) }}" class="btn btn-light" title="Return to Financial Movements & Tax Audit">
                <i class="fa-solid fa-arrow-left"></i>
                <span>Movements</span>
            </a>

            {{-- Quick Ethiopian Month & Year Selector --}}
            <form method="GET" action="{{ route('finance.tax-deductions.movements-receipts-pdf') }}" class="month-selector-form" id="receiptsMonthForm">
                <input type="hidden" name="type" value="{{ $receiptFilter }}">
                <input type="hidden" name="format" value="{{ $formatFilter }}">

                {{-- Previous Month Button --}}
                <a href="{{ route('finance.tax-deductions.movements-receipts-pdf', ['ethiopian_year' => $prevYear, 'ethiopian_month' => $prevMonth, 'type' => $receiptFilter, 'format' => $formatFilter]) }}" 
                   class="month-nav-btn" 
                   title="Previous Month">
                    <i class="fa-solid fa-chevron-left"></i>
                </a>

                {{-- Year Dropdown --}}
                <select name="ethiopian_year" class="month-select-input" onchange="this.form.submit()" title="Select Ethiopian Year">
                    @foreach($availableEthYears as $y)
                        <option value="{{ $y }}" {{ $selectedEthYear == $y ? 'selected' : '' }}>{{ $y }} ዓ.ም.</option>
                    @endforeach
                </select>

                {{-- Month Dropdown --}}
                <select name="ethiopian_month" class="month-select-input" style="color: #1e3a8a;" onchange="this.form.submit()" title="Select Ethiopian Month">
                    @foreach($availableEthMonths as $mNum => $m)
                        @php
                            $monthText = is_array($m) ? ($m['label'] ?? (($m['name_en'] ?? '') . ' (' . ($m['name_am'] ?? '') . ')')) : "{$mNum}. {$m}";
                        @endphp
                        <option value="{{ $mNum }}" {{ $selectedEthMonth == $mNum ? 'selected' : '' }}>
                            {{ $monthText }}
                        </option>
                    @endforeach
                </select>

                {{-- Next Month Button --}}
                <a href="{{ route('finance.tax-deductions.movements-receipts-pdf', ['ethiopian_year' => $nextYear, 'ethiopian_month' => $nextMonth, 'type' => $receiptFilter, 'format' => $formatFilter]) }}" 
                   class="month-nav-btn" 
                   title="Next Month">
                    <i class="fa-solid fa-chevron-right"></i>
                </a>
            </form>
        </div>

        {{-- Center: Tax Type & Format Filter Pills --}}
        <div class="toolbar-group">
            <span style="font-size: 11px; font-weight: 700; color: #64748b;">Tax:</span>
            <a href="{{ route('finance.tax-deductions.movements-receipts-pdf', ['ethiopian_year' => $selectedEthYear, 'ethiopian_month' => $selectedEthMonth, 'type' => 'all', 'format' => $formatFilter]) }}" 
               class="btn btn-light btn-pill {{ $receiptFilter === 'all' ? 'active' : '' }}">
                All ({{ $receiptsSummary['total_count'] }})
            </a>
            <a href="{{ route('finance.tax-deductions.movements-receipts-pdf', ['ethiopian_year' => $selectedEthYear, 'ethiopian_month' => $selectedEthMonth, 'type' => 'vat', 'format' => $formatFilter]) }}" 
               class="btn btn-light btn-pill {{ $receiptFilter === 'vat' ? 'active' : '' }}">
                15% VAT ({{ $receiptsSummary['vat_count'] }})
            </a>
            <a href="{{ route('finance.tax-deductions.movements-receipts-pdf', ['ethiopian_year' => $selectedEthYear, 'ethiopian_month' => $selectedEthMonth, 'type' => 'tas', 'format' => $formatFilter]) }}" 
               class="btn btn-light btn-pill {{ $receiptFilter === 'tas' ? 'active' : '' }}">
                0% TAS ({{ $receiptsSummary['tas_count'] }})
            </a>

            <span style="color: #cbd5e1; margin: 0 2px;">|</span>

            <span style="font-size: 11px; font-weight: 700; color: #64748b;">Format:</span>
            <a href="{{ route('finance.tax-deductions.movements-receipts-pdf', ['ethiopian_year' => $selectedEthYear, 'ethiopian_month' => $selectedEthMonth, 'type' => $receiptFilter, 'format' => 'all']) }}" 
               class="btn btn-light btn-pill {{ $formatFilter === 'all' ? 'active' : '' }}">
                All
            </a>
            <a href="{{ route('finance.tax-deductions.movements-receipts-pdf', ['ethiopian_year' => $selectedEthYear, 'ethiopian_month' => $selectedEthMonth, 'type' => $receiptFilter, 'format' => 'image']) }}" 
               class="btn btn-light btn-pill {{ $formatFilter === 'image' ? 'active' : '' }}" title="Show receipts uploaded as photos/images">
                <i class="fa-solid fa-image" style="font-size: 10px;"></i>
                <span>Pics ({{ $receiptsSummary['image_count'] }})</span>
            </a>
            <a href="{{ route('finance.tax-deductions.movements-receipts-pdf', ['ethiopian_year' => $selectedEthYear, 'ethiopian_month' => $selectedEthMonth, 'type' => $receiptFilter, 'format' => 'pdf']) }}" 
               class="btn btn-light btn-pill {{ $formatFilter === 'pdf' ? 'active' : '' }}" title="Show receipts uploaded as PDF documents">
                <i class="fa-solid fa-file-pdf" style="font-size: 10px; color: #ef4444;"></i>
                <span>PDFs ({{ $receiptsSummary['pdf_count'] }})</span>
            </a>
        </div>

        {{-- Right: Print & PDF Download Actions --}}
        <div class="toolbar-group">
            <button type="button" class="btn btn-primary" onclick="window.print()" title="Print via Browser / Save as PDF">
                <i class="fa-solid fa-print"></i>
                <span>Print</span>
            </button>
            <button type="button" class="btn btn-danger" id="btnDownloadPdf" onclick="downloadReceiptsPdf()" title="Download full PDF file with one receipt picture per page">
                <i class="fa-solid fa-file-arrow-down"></i>
                <span>Download Receipts PDF</span>
            </button>
        </div>
    </div>

    {{-- ── Main PDF Document Container ────────────────────────────────────────── --}}
    <div id="receiptsPdfDocument">

        {{-- ══════════════════════════════════════════════════════════════════════
             PAGE 1: COVER & MASTER RECEIPTS REGISTER (SUMMARY SCHEDULE)
             ══════════════════════════════════════════════════════════════════════ --}}
        <div class="pdf-page-sheet" id="pdfPage1">

            {{-- ── Header Section ─────────────────────────────────────────────── --}}
            <div class="header-section">
                <div class="company-header">
                    <h1>Wechecha Construction P.L.C.</h1>
                    <h2>ወቼቻ ኮንስትራክሽን ኃ/የተ/የግ/ማ &bull; Tax Audit Receipts Register</h2>
                    <p>
                        <strong>TIN:</strong> 0054711385 &bull; 
                        <strong>VAT Reg:</strong> 2938471002 &bull; 
                        <strong>ERCA Compliance:</strong> Line 100 Verification &bull; Addis Ababa, Ethiopia
                    </p>
                    <p style="margin-top: 3px;">
                        <i class="fa-regular fa-calendar-check" style="color: #1e3a8a;"></i> 
                        <strong>Period:</strong> {{ $monthInfo['title'] ?? 'Ethiopian Month' }} 
                        <span style="color: #94a3b8; margin: 0 4px;">&bull;</span> 
                        <strong>GC Window:</strong> {{ $monthInfo['gregorian_window'] ?? 'Period' }}
                    </p>
                </div>

                <div class="report-badge-box">
                    <span class="badge-pill badge-red">
                        <i class="fa-solid fa-receipt"></i> Only Added Receipts
                    </span>
                    <span class="badge-pill badge-green">
                        <i class="fa-solid fa-shield-halved"></i> Zero Duplicates Guaranteed
                    </span>
                    @if(!empty($receiptsSummary['duplicates_blocked_count']))
                        <span class="badge-pill badge-dark" title="{{ $receiptsSummary['duplicates_blocked_count'] }} duplicate copies blocked from PDF creation">
                            <i class="fa-solid fa-ban" style="color: #ef4444;"></i> {{ $receiptsSummary['duplicates_blocked_count'] }} Duplicate(s) Blocked
                        </span>
                    @endif
                    <span style="font-size: 8.5px; color: #64748b; margin-top: 4px;">
                        Generated: {{ now()->format('d M Y, H:i') }} &bull; Page 1 of {{ $addedReceipts->count() + 1 }}
                    </span>
                </div>
            </div>

            {{-- ── Executive Summary KPI Cards ─────────────────────────────────── --}}
            <div class="kpi-strip">
                <div class="kpi-card">
                    <div class="kpi-label"><i class="fa-solid fa-file-invoice" style="color:#1e3a8a;"></i> Unique Receipts</div>
                    <div class="kpi-value">{{ $addedReceipts->count() }} <span style="font-size: 8.5px; color: var(--text-muted); font-weight: 500;">/ {{ $receiptsSummary['total_count'] }}</span></div>
                    <div class="kpi-sub">
                        {{ $receiptsSummary['vat_count'] }} VAT &bull; {{ $receiptsSummary['tas_count'] }} TAS
                        @if(!empty($receiptsSummary['duplicates_blocked_count']))
                            &bull; <span style="color: #b91c1c; font-weight: 700;">{{ $receiptsSummary['duplicates_blocked_count'] }} dup blocked</span>
                        @endif
                    </div>
                </div>
                <div class="kpi-card">
                    <div class="kpi-label"><i class="fa-solid fa-coins" style="color:#0284c7;"></i> Total Base Value</div>
                    <div class="kpi-value text-right">ETB {{ number_format($receiptsSummary['total_base'], 2) }}</div>
                    <div class="kpi-sub">Taxable / Pre-tax Amount</div>
                </div>
                <div class="kpi-card">
                    <div class="kpi-label"><i class="fa-solid fa-percent" style="color:#15803d;"></i> 15% Input VAT</div>
                    <div class="kpi-value text-right" style="color: #15803d;">ETB {{ number_format($receiptsSummary['total_vat'], 2) }}</div>
                    <div class="kpi-sub">Creditable to ERCA</div>
                </div>
                <div class="kpi-card">
                    <div class="kpi-label"><i class="fa-solid fa-money-bill-wave" style="color:#0f172a;"></i> Total Disbursed</div>
                    <div class="kpi-value text-right">ETB {{ number_format($receiptsSummary['total_gross'], 2) }}</div>
                    <div class="kpi-sub">Total with VAT / Settlement</div>
                </div>
                <div class="kpi-card">
                    <div class="kpi-label"><i class="fa-solid fa-paperclip" style="color:#b45309;"></i> Evidence Files</div>
                    <div class="kpi-value text-right" style="color:#b45309;">{{ $receiptsSummary['with_file_count'] }} Slips</div>
                    <div class="kpi-sub">{{ $receiptsSummary['image_count'] }} Pics &bull; {{ $receiptsSummary['pdf_count'] }} PDFs</div>
                </div>
            </div>

            {{-- ── Master Register Schedule Table ─────────────────────────────── --}}
            <div class="section-header">
                <h3><i class="fa-solid fa-list-check" style="color: #1e3a8a;"></i> Master Receipts Register &amp; Voucher Index</h3>
                <span>Total {{ $addedReceipts->count() }} Verified Receipts Added</span>
            </div>

            <div class="table-wrap">
                <table class="receipts-table">
                    <thead>
                        <tr>
                            <th style="width: 24px;">#</th>
                            <th style="width: 75px;">Date (EC)</th>
                            <th style="width: 90px;">FS No. / Ref</th>
                            <th>Vendor Name &amp; TIN</th>
                            <th>Project / Category</th>
                            <th>Item Description</th>
                            <th class="text-right" style="width: 85px;">Base (ETB)</th>
                            <th class="text-right" style="width: 75px;">VAT 15%</th>
                            <th class="text-right" style="width: 90px;">Total (ETB)</th>
                            <th class="text-center" style="width: 50px;">Page #</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($addedReceipts as $idx => $rec)
                            <tr>
                                <td class="font-mono text-center">{{ $idx + 1 }}</td>
                                <td>
                                    <strong class="font-mono" style="color: #0f172a;">{{ $rec['date_ec'] }}</strong>
                                    <div style="font-size: 7.5px; color: #64748b;">{{ $rec['date_gc'] }}</div>
                                </td>
                                <td>
                                    <span class="font-mono" style="font-weight: 700; color: #1e3a8a;">{{ $rec['fs_no'] }}</span>
                                    @if(!empty($rec['is_pdf']))
                                        <span class="badge-pill badge-red" style="font-size: 7.5px; padding: 1px 5px; margin-left: 2px;">PDF</span>
                                    @elseif(!empty($rec['file_url']))
                                        <span class="badge-pill badge-blue" style="font-size: 7.5px; padding: 1px 5px; margin-left: 2px;">PIC</span>
                                    @endif
                                    @if(!empty($rec['mrc_no']) && $rec['mrc_no'] !== '-')
                                        <div style="font-size: 7.5px; color: #64748b;">MRC: {{ $rec['mrc_no'] }}</div>
                                    @endif
                                </td>
                                <td>
                                    <strong style="color: #0f172a;">{{ Str::limit($rec['vendor_name'], 25) }}</strong>
                                    <div class="font-mono" style="font-size: 7.5px; color: #64748b;">TIN: {{ $rec['vendor_tin'] }}</div>
                                </td>
                                <td>
                                    <span style="font-weight: 600;">{{ $rec['project_name'] }}</span>
                                    <div style="font-size: 7.5px; color: #64748b; text-transform: capitalize;">{{ $rec['category'] }}</div>
                                </td>
                                <td>
                                    <div style="max-width: 150px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="{{ $rec['item_description'] }}">
                                        {{ $rec['item_description'] }}
                                    </div>
                                </td>
                                <td class="text-right font-mono">{{ number_format($rec['base_value'], 2) }}</td>
                                <td class="text-right font-mono" style="color: {{ $rec['vat_amount'] > 0 ? '#15803d' : '#94a3b8' }};">
                                    {{ $rec['vat_amount'] > 0 ? number_format($rec['vat_amount'], 2) : '0% (TAS)' }}
                                </td>
                                <td class="text-right font-mono" style="font-weight: 700; color: #0f172a;">
                                    {{ number_format($rec['total_value'], 2) }}
                                </td>
                                <td class="text-center font-mono" style="font-weight: 700; color: #1e3a8a;">
                                    Page {{ $idx + 2 }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="10" class="text-center" style="padding: 24px; color: #94a3b8;">
                                    <i class="fa-solid fa-inbox" style="font-size: 24px; display: block; margin-bottom: 6px;"></i>
                                    No receipts found matching the selected filter criteria for this Ethiopian month.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="6" class="text-right">Grand Total ({{ $addedReceipts->count() }} Receipts):</td>
                            <td class="text-right font-mono">{{ number_format($receiptsSummary['total_base'], 2) }}</td>
                            <td class="text-right font-mono" style="color: #15803d;">{{ number_format($receiptsSummary['total_vat'], 2) }}</td>
                            <td class="text-right font-mono">{{ number_format($receiptsSummary['total_gross'], 2) }}</td>
                            <td class="text-center font-mono">{{ $addedReceipts->count() }} Pages</td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            {{-- ── Page 1 Bottom Sign-off Block ────────────────────────────────── --}}
            <div class="signoff-section">
                <div class="signoff-title">
                    <i class="fa-solid fa-signature" style="color: #1e3a8a;"></i> 
                    Tax Audit &amp; ERCA Line 100 Summary Authorization
                </div>
                <div class="signoff-grid">
                    <div class="signoff-box">
                        <div class="signoff-role">Prepared by (Accountant)</div>
                        <div class="signoff-name">{{ auth()->user()->name ?? 'Accountant' }}</div>
                        <div class="signoff-date">Date: ________________________</div>
                    </div>
                    <div class="signoff-box">
                        <div class="signoff-role">Audited by (Internal Auditor)</div>
                        <div class="signoff-name">Compliance &amp; Tax Review</div>
                        <div class="signoff-date">Date: ________________________</div>
                    </div>
                    <div class="signoff-box">
                        <div class="signoff-role">Approved by (Finance Head / GM)</div>
                        <div class="signoff-name">Official ERCA Declaration</div>
                        <div class="signoff-date">Signature &amp; Stamp: _____________</div>
                    </div>
                </div>
            </div>

        </div>

        {{-- ══════════════════════════════════════════════════════════════════════
             SUBSEQUENT PAGES: ONE FULL PAGE PER UPLOADED RECEIPT PICTURE
             ══════════════════════════════════════════════════════════════════════ --}}
        @foreach($addedReceipts as $idx => $rec)
            <div class="pdf-page-sheet" id="receiptEvidencePage{{ $idx + 1 }}">

                {{-- ── Page Top Micro-Header ───────────────────────────────────── --}}
                <div class="receipt-page-micro-header">
                    <div class="brand-title">
                        <i class="fa-solid fa-building" style="color: #1e3a8a;"></i>
                        <span>Wechecha Construction &bull; Receipts Tax Audit Evidence</span>
                    </div>
                    <div class="page-tag">
                        <span>Receipt #{{ $idx + 1 }} of {{ $addedReceipts->count() }}</span>
                        <span style="color: #94a3b8; margin: 0 4px;">&bull;</span>
                        <span>Document Page {{ $idx + 2 }}</span>
                    </div>
                </div>

                {{-- ── Comprehensive Receipt Description Box ───────────────────── --}}
                <div class="receipt-desc-card">
                    <div class="desc-card-top-row">
                        <div>
                            <div class="desc-vendor-name">
                                {{ $rec['vendor_name'] }}
                            </div>
                            <div class="desc-vendor-tin">
                                <strong>Vendor TIN:</strong> {{ $rec['vendor_tin'] }} 
                                <span style="color: #cbd5e1; margin: 0 4px;">|</span>
                                <strong>Purchaser TIN:</strong> {{ $rec['purchaser_tin'] ?? '0054711385' }}
                            </div>
                        </div>
                        <div style="text-align: right; display: flex; flex-direction: column; align-items: flex-end; gap: 3px;">
                            <span class="badge-pill {{ $rec['is_vat_receipt'] ? 'badge-green' : 'badge-blue' }}" style="font-size: 10px; padding: 4px 10px;">
                                <i class="fa-solid {{ $rec['is_vat_receipt'] ? 'fa-percent' : 'fa-receipt' }}"></i>
                                {{ $rec['is_vat_receipt'] ? '15% VAT Invoiced' : '0% TAS Exempt' }}
                            </span>
                            @if(!empty($rec['duplicates_blocked']))
                                <span class="badge-pill badge-dark" style="font-size: 8px; padding: 2px 7px;" title="{{ $rec['duplicates_blocked'] }} duplicate copy(ies) blocked and consolidated">
                                    <i class="fa-solid fa-clone" style="color: #f59e0b;"></i> {{ $rec['duplicates_blocked'] }} Duplicate Blocked
                                </span>
                            @endif
                        </div>
                    </div>

                    <div class="desc-card-grid">
                        <div class="desc-cell">
                            <div class="cell-label">Fiscal Receipt (FS No)</div>
                            <div class="cell-value font-mono" style="color: #1e3a8a;">{{ $rec['fs_no'] }}</div>
                        </div>
                        <div class="desc-cell">
                            <div class="cell-label">Machine MRC / Ref</div>
                            <div class="cell-value font-mono">{{ $rec['mrc_no'] !== '-' ? $rec['mrc_no'] : ($rec['request_number'] ?? '-') }}</div>
                        </div>
                        <div class="desc-cell">
                            <div class="cell-label">Receipt Date (EC)</div>
                            <div class="cell-value font-mono">{{ $rec['date_ec'] }}</div>
                        </div>
                        <div class="desc-cell">
                            <div class="cell-label">Receipt Date (GC)</div>
                            <div class="cell-value font-mono" style="color: #475569;">{{ $rec['date_gc'] }}</div>
                        </div>
                        <div class="desc-cell" style="grid-column: span 2;">
                            <div class="cell-label">Destination Project / Site</div>
                            <div class="cell-value">{{ $rec['project_name'] }}</div>
                        </div>
                        <div class="desc-cell" style="grid-column: span 2;">
                            <div class="cell-label">Expense Classification &amp; Category</div>
                            <div class="cell-value" style="text-transform: capitalize;">{{ $rec['category'] }}</div>
                        </div>
                    </div>

                    {{-- Financial Breakdown Bar --}}
                    <div class="desc-financial-bar">
                        <div class="desc-fin-col">
                            <div class="desc-fin-label">Base Value (Pre-Tax)</div>
                            <div class="desc-fin-amount" style="color: #0f172a;">ETB {{ number_format($rec['base_value'], 2) }}</div>
                        </div>
                        <div class="desc-fin-col">
                            <div class="desc-fin-label">VAT Amount (15%)</div>
                            <div class="desc-fin-amount" style="color: {{ $rec['vat_amount'] > 0 ? '#15803d' : '#64748b' }};">
                                {{ $rec['vat_amount'] > 0 ? '+ ETB ' . number_format($rec['vat_amount'], 2) : '0% VAT (0.00)' }}
                            </div>
                        </div>
                        <div class="desc-fin-col">
                            <div class="desc-fin-label">Total Payable / Disbursed</div>
                            <div class="desc-fin-amount" style="color: #1e3a8a; font-size: 13px;">ETB {{ number_format($rec['total_value'], 2) }}</div>
                        </div>
                    </div>

                    {{-- Purpose & Description --}}
                    <div class="desc-purpose-line">
                        <strong>Item Description / Purpose:</strong> {{ $rec['item_description'] }}
                    </div>
                </div>

                {{-- ── Full Page Focus Picture Stage ───────────────────────────── --}}
                <div class="receipt-picture-stage">
                    @php
                        $imageSrc = $rec['image_base64'] ?? $rec['file_url'];
                        $pdfSrc   = $rec['pdf_base64'] ?? $rec['file_url'];
                    @endphp

                    @if(!empty($rec['is_pdf']) && !empty($pdfSrc))
                        {{-- ── VISUALLY RENDERED PDF RECEIPT DOCUMENT ON CANVAS ── --}}
                        <div class="pdf-canvas-container" id="pdfContainer_{{ $idx }}" data-idx="{{ $idx }}" data-pdf-src="{{ $pdfSrc }}">
                            {{-- Canvas where Page 1 of the PDF is rendered crisp and clean --}}
                            <canvas id="pdfCanvas_{{ $idx }}" class="pdf-rendered-canvas"></canvas>

                            {{-- Loading Spinner indicator --}}
                            <div id="pdfLoading_{{ $idx }}" class="pdf-loading-indicator">
                                <i class="fa-solid fa-spinner fa-spin"></i>
                                <div>Rendering Scanned PDF Receipt Document...</div>
                                <div class="sub-meta">{{ basename($rec['file_url'] ?? 'receipt.pdf') }}</div>
                            </div>

                            {{-- Fallback Card if JS is disabled or PDF cannot load --}}
                            <div id="pdfFallback_{{ $idx }}" class="receipt-pdf-placeholder" style="display: none;">
                                <i class="fa-solid fa-file-pdf"></i>
                                <h4>Official Scanned PDF Document Attached</h4>
                                <p>
                                    Voucher Reference: <strong>{{ $rec['request_number'] }}</strong> &bull; FS No: <strong>{{ $rec['fs_no'] }}</strong>
                                </p>
                                <a href="{{ $rec['file_url'] }}" target="_blank" class="doc-link">
                                    <i class="fa-solid fa-arrow-up-right-from-square"></i>
                                    <span>Open / View High-Resolution PDF Document</span>
                                </a>
                            </div>

                            {{-- Floating Info Pill at bottom of picture stage --}}
                            <div class="pdf-info-floating-bar" id="pdfBar_{{ $idx }}">
                                <span>
                                    <i class="fa-solid fa-file-pdf" style="color: #ef4444; margin-right: 4px;"></i>
                                    Scanned PDF Receipt <strong id="pdfPageInfo_{{ $idx }}">(Page 1)</strong>
                                </span>
                                <a href="{{ $rec['file_url'] }}" target="_blank" class="pdf-open-link" title="Open multi-page original PDF in separate tab">
                                    <span>Open Raw PDF</span>
                                    <i class="fa-solid fa-arrow-up-right-from-square" style="font-size: 8px;"></i>
                                </a>
                            </div>
                        </div>
                    @elseif(!empty($imageSrc))
                        {{-- Uploaded Picture Focus: Large, Crisp, Centered --}}
                        <img src="{{ $imageSrc }}" 
                             alt="Receipt Slip - FS #{{ $rec['fs_no'] }} - {{ $rec['vendor_name'] }}" 
                             loading="lazy" 
                             crossorigin="anonymous">
                    @else
                        {{-- No Picture Attached Placeholder --}}
                        <div class="receipt-voucher-placeholder">
                            <i class="fa-solid fa-receipt"></i>
                            <h4 style="font-size: 14px; font-weight: 700; color: #0f172a; margin-bottom: 4px;">Verified Fiscal Cash Register Voucher on File</h4>
                            <p style="font-size: 10px; color: #64748b;">
                                Electronic Cash Register Entry Registered in Tax Audit Ledger.<br>
                                Machine MRC: <strong>{{ $rec['mrc_no'] }}</strong> &bull; Fiscal Slip Ref: <strong>{{ $rec['fs_no'] }}</strong>
                            </p>
                        </div>
                    @endif
                </div>

                {{-- ── Page Bottom Verification Footer ─────────────────────────── --}}
                <div class="receipt-page-footer">
                    <div>
                        <i class="fa-solid fa-shield-check" style="color: #15803d; margin-right: 3px;"></i>
                        <strong>Verified Tax Audit Evidence:</strong> FS No. {{ $rec['fs_no'] }} &bull; Vendor TIN: {{ $rec['vendor_tin'] }}
                    </div>
                    <div>
                        Auditor Signature: __________________________ &bull; Stamp: __________________
                    </div>
                    <div>
                        Page {{ $idx + 2 }} of {{ $addedReceipts->count() + 1 }}
                    </div>
                </div>

            </div>
        @endforeach

    </div>

    {{-- ── Client-side PDF Generation Script ───────────────────────────────────── --}}
    <script>
        // Configure Mozilla PDF.js
        if (typeof pdfjsLib !== 'undefined') {
            pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';
        }

        let pdfRenderPromises = [];

        function renderPdfPage(container) {
            const idx = container.getAttribute('data-idx');
            const pdfSource = container.getAttribute('data-pdf-src');
            const canvas = document.getElementById('pdfCanvas_' + idx);
            const loading = document.getElementById('pdfLoading_' + idx);
            const fallback = document.getElementById('pdfFallback_' + idx);
            const bar = document.getElementById('pdfBar_' + idx);
            const pageInfo = document.getElementById('pdfPageInfo_' + idx);

            if (!pdfSource || !canvas || typeof pdfjsLib === 'undefined') {
                if (loading) loading.style.display = 'none';
                if (fallback) fallback.style.display = 'block';
                return Promise.resolve();
            }

            let docParams;
            try {
                if (pdfSource.startsWith('data:application/pdf;base64,')) {
                    const base64Data = pdfSource.replace('data:application/pdf;base64,', '');
                    const rawBytes = atob(base64Data);
                    const uint8Array = new Uint8Array(rawBytes.length);
                    for (let i = 0; i < rawBytes.length; i++) {
                        uint8Array[i] = rawBytes.charCodeAt(i);
                    }
                    docParams = { data: uint8Array };
                } else {
                    docParams = { url: pdfSource };
                }
            } catch (e) {
                console.error('Error preparing PDF data for idx ' + idx, e);
                docParams = { url: pdfSource };
            }

            const loadingTask = pdfjsLib.getDocument(docParams);
            return loadingTask.promise.then(function(pdfDoc) {
                return pdfDoc.getPage(1).then(function(page) {
                    const unscaledViewport = page.getViewport({ scale: 1.0 });
                    const targetWidth = 760;
                    const targetHeight = 700;
                    const scaleX = targetWidth / unscaledViewport.width;
                    const scaleY = targetHeight / unscaledViewport.height;
                    const fitScale = Math.min(scaleX, scaleY, 2.0);

                    // High-DPI render (crisp print & html2canvas)
                    const renderScale = Math.max(fitScale * 1.5, 1.2);
                    const viewport = page.getViewport({ scale: renderScale });

                    canvas.height = viewport.height;
                    canvas.width = viewport.width;

                    const ctx = canvas.getContext('2d');
                    const renderContext = {
                        canvasContext: ctx,
                        viewport: viewport
                    };

                    return page.render(renderContext).promise.then(function() {
                        if (loading) loading.style.display = 'none';
                        canvas.style.display = 'block';
                        if (bar) bar.style.display = 'inline-flex';
                        if (pageInfo) pageInfo.textContent = '(Page 1 of ' + pdfDoc.numPages + ')';
                    });
                });
            }).catch(function(err) {
                console.warn('PDF.js render error for idx ' + idx + ':', err);
                if (loading) loading.style.display = 'none';
                if (fallback) fallback.style.display = 'block';
            });
        }

        function initAllPdfs() {
            const containers = document.querySelectorAll('.pdf-canvas-container');
            pdfRenderPromises = Array.from(containers).map(renderPdfPage);
            return Promise.all(pdfRenderPromises);
        }

        window.addEventListener('DOMContentLoaded', function() {
            initAllPdfs();
        });

        async function downloadReceiptsPdf() {
            const btn = document.getElementById('btnDownloadPdf');
            const originalHtml = btn.innerHTML;
            btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Rendering Documents &amp; Generating...';
            btn.disabled = true;

            try {
                await Promise.all(pdfRenderPromises);
            } catch (e) {
                console.warn('Wait for PDFs error:', e);
            }

            await new Promise(r => setTimeout(r, 250));

            const element = document.getElementById('receiptsPdfDocument');
            const filename = 'Wechecha-Receipts-Audit-{{ Str::slug($monthInfo["title"] ?? "period") }}-{{ date("Y-m-d") }}.pdf';

            const opt = {
                margin:       [0, 0, 0, 0],
                filename:     filename,
                image:        { type: 'jpeg', quality: 0.98 },
                html2canvas:  { scale: 2, useCORS: true, logging: false },
                jsPDF:        { unit: 'mm', format: 'a4', orientation: 'portrait' },
                pagebreak:    { mode: ['css', 'legacy'] }
            };

            html2pdf().set(opt).from(element).save().then(function() {
                btn.innerHTML = originalHtml;
                btn.disabled = false;
            }).catch(function(err) {
                console.error('PDF export error:', err);
                btn.innerHTML = originalHtml;
                btn.disabled = false;
                window.print();
            });
        }

        // Auto download trigger if query string ?download=1 is present
        @if(!empty($autoDownload))
            window.addEventListener('DOMContentLoaded', function() {
                Promise.all(pdfRenderPromises).then(function() {
                    setTimeout(downloadReceiptsPdf, 600);
                }).catch(function() {
                    setTimeout(downloadReceiptsPdf, 800);
                });
            });
        @endif
    </script>
</body>
</html>
