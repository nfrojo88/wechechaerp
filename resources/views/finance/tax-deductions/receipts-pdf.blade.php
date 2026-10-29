<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Receipts Register — {{ $monthInfo['title'] ?? 'Tax Audit' }} — Wechecha Construction</title>

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

        /* ── Top Floating Action Toolbar ────────────────────────────── */
        .no-print-toolbar {
            max-width: 900px;
            margin: 0 auto 20px auto;
            background: #ffffff;
            padding: 12px 20px;
            border-radius: 12px;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.2), 0 8px 10px -6px rgba(0, 0, 0, 0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
            border: 1px solid #e2e8f0;
        }

        .toolbar-group {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
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
            padding: 5px 12px;
            font-size: 11px;
        }

        .btn-pill.active {
            background: #1e3a8a;
            color: #ffffff;
            border-color: #1e3a8a;
        }

        /* ── PDF Printable Container ────────────────────────────────── */
        .pdf-container {
            width: 900px;
            margin: 0 auto;
            background: #ffffff;
            padding: 28px 32px;
            border-radius: 8px;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.3);
            border: 1px solid #cbd5e1;
        }

        /* ── Header Styles ─────────────────────────────────────────── */
        .header-section {
            border-bottom: 2px solid #1e3a8a;
            padding-bottom: 14px;
            margin-bottom: 16px;
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 16px;
        }

        .company-header h1 {
            font-size: 17px;
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
            margin-bottom: 4px;
        }

        .company-header p {
            font-size: 10px;
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
            font-size: 9.5px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.03em;
        }

        .badge-red { background: #fee2e2; color: #991b1b; border: 1px solid #fca5a5; }
        .badge-blue { background: #e0f2fe; color: #0369a1; border: 1px solid #bae6fd; }
        .badge-green { background: #dcfce7; color: #15803d; border: 1px solid #86efac; }
        .badge-amber { background: #fef3c7; color: #92400e; border: 1px solid #fde68a; }

        /* ── Summary KPI Strip ──────────────────────────────────────── */
        .kpi-strip {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 10px;
            margin-bottom: 18px;
        }

        .kpi-card {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 10px 12px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        .kpi-card .kpi-label {
            font-size: 9px;
            font-weight: 700;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.04em;
            margin-bottom: 4px;
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .kpi-card .kpi-value {
            font-size: 13.5px;
            font-weight: 800;
            color: #0f172a;
            font-family: 'Inter', monospace;
        }

        .kpi-card .kpi-sub {
            font-size: 8.5px;
            color: var(--text-muted);
            margin-top: 2px;
        }

        /* ── Section Titles ────────────────────────────────────────── */
        .section-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: #f1f5f9;
            border-left: 4px solid #1e3a8a;
            padding: 8px 12px;
            margin-bottom: 10px;
            border-radius: 0 6px 6px 0;
        }

        .section-header h3 {
            font-size: 12px;
            font-weight: 700;
            color: #0f172a;
            text-transform: uppercase;
            letter-spacing: 0.03em;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .section-header span {
            font-size: 10px;
            color: var(--text-muted);
            font-weight: 600;
        }

        /* ── Table Styles ──────────────────────────────────────────── */
        .table-wrap {
            margin-bottom: 24px;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            overflow: hidden;
        }

        table.receipts-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 10px;
            text-align: left;
        }

        table.receipts-table thead {
            background: #1e293b;
            color: #ffffff;
        }

        table.receipts-table th {
            padding: 7px 8px;
            font-size: 9px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.03em;
            border-bottom: 1px solid #cbd5e1;
        }

        table.receipts-table td {
            padding: 6px 8px;
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
        }

        table.receipts-table tfoot td {
            padding: 8px;
            color: #0f172a;
            font-size: 10.5px;
        }

        .font-mono {
            font-family: 'Inter', monospace;
            font-variant-numeric: tabular-nums;
        }

        .text-right { text-align: right; }
        .text-center { text-align: center; }

        /* ── Receipt Evidence Cards Grid ────────────────────────────── */
        .voucher-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
            margin-bottom: 24px;
        }

        .voucher-card {
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            background: #ffffff;
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
            page-break-inside: avoid;
            break-inside: avoid;
            display: flex;
            flex-direction: column;
        }

        .voucher-header {
            background: #f8fafc;
            border-bottom: 1px solid #e2e8f0;
            padding: 8px 12px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .voucher-header .voucher-title {
            font-weight: 700;
            color: #0f172a;
            font-size: 11px;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .voucher-body {
            padding: 10px 12px;
            flex-grow: 1;
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .voucher-meta-row {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 6px;
            background: #f8fafc;
            padding: 6px 8px;
            border-radius: 6px;
            border: 1px solid #f1f5f9;
        }

        .meta-item .meta-label {
            font-size: 8px;
            color: var(--text-muted);
            text-transform: uppercase;
            font-weight: 700;
        }

        .meta-item .meta-val {
            font-size: 10px;
            font-weight: 700;
            color: #0f172a;
        }

        .voucher-image-box {
            border: 1px dashed #cbd5e1;
            border-radius: 6px;
            background: #f8fafc;
            min-height: 140px;
            max-height: 220px;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            position: relative;
        }

        .voucher-image-box img {
            max-width: 100%;
            max-height: 220px;
            object-fit: contain;
            display: block;
        }

        .voucher-no-img {
            text-align: center;
            color: var(--text-muted);
            font-size: 10px;
            padding: 20px;
        }

        .voucher-no-img i {
            font-size: 24px;
            margin-bottom: 6px;
            display: block;
            opacity: 0.5;
        }

        /* ── Sign-off Block ─────────────────────────────────────────── */
        .signoff-section {
            border: 1px solid #cbd5e1;
            background: #f8fafc;
            border-radius: 8px;
            padding: 14px 16px;
            margin-top: 24px;
            page-break-inside: avoid;
            break-inside: avoid;
        }

        .signoff-title {
            font-size: 11px;
            font-weight: 700;
            color: #0f172a;
            text-transform: uppercase;
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .signoff-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            text-align: center;
        }

        .signoff-box {
            border-top: 1px dashed #94a3b8;
            padding-top: 8px;
        }

        .signoff-role {
            font-size: 10px;
            font-weight: 700;
            color: #1e3a8a;
            margin-bottom: 2px;
        }

        .signoff-name {
            font-size: 9.5px;
            color: #475569;
        }

        .signoff-date {
            font-size: 8.5px;
            color: #94a3b8;
            margin-top: 4px;
        }

        /* ── Print Specific Rules ───────────────────────────────────── */
        @media print {
            body {
                background: #ffffff !important;
                padding: 0 !important;
                font-size: 9.5px !important;
            }

            .no-print-toolbar {
                display: none !important;
            }

            .pdf-container {
                width: 100% !important;
                margin: 0 !important;
                padding: 0 !important;
                border: none !important;
                box-shadow: none !important;
            }

            .voucher-card {
                page-break-inside: avoid !important;
                break-inside: avoid !important;
            }

            .page-break {
                page-break-before: always !important;
                break-before: page !important;
            }
        }
    </style>
</head>
<body>

    {{-- ── Floating Action Toolbar (Hidden in Print & PDF export) ──────────────── --}}
    <div class="no-print-toolbar">
        <div class="toolbar-group">
            <a href="{{ route('finance.tax-deductions.movements', ['ethiopian_year' => $selectedEthYear, 'ethiopian_month' => $selectedEthMonth]) }}" class="btn btn-light" title="Return to Financial Movements & Tax Audit">
                <i class="fa-solid fa-arrow-left"></i>
                <span>Back to Movements</span>
            </a>
            <div class="toolbar-group" style="margin-left: 10px;">
                <span style="font-size: 11px; font-weight: 700; color: #64748b;">Filter Receipts:</span>
                <a href="{{ route('finance.tax-deductions.movements-receipts-pdf', ['ethiopian_year' => $selectedEthYear, 'ethiopian_month' => $selectedEthMonth, 'type' => 'all']) }}" 
                   class="btn btn-light btn-pill {{ $receiptFilter === 'all' ? 'active' : '' }}">
                    All Added ({{ $receiptsSummary['total_count'] }})
                </a>
                <a href="{{ route('finance.tax-deductions.movements-receipts-pdf', ['ethiopian_year' => $selectedEthYear, 'ethiopian_month' => $selectedEthMonth, 'type' => 'vat']) }}" 
                   class="btn btn-light btn-pill {{ $receiptFilter === 'vat' ? 'active' : '' }}">
                    15% VAT Only ({{ $receiptsSummary['vat_count'] }})
                </a>
                <a href="{{ route('finance.tax-deductions.movements-receipts-pdf', ['ethiopian_year' => $selectedEthYear, 'ethiopian_month' => $selectedEthMonth, 'type' => 'tas']) }}" 
                   class="btn btn-light btn-pill {{ $receiptFilter === 'tas' ? 'active' : '' }}">
                    0% TAS Only ({{ $receiptsSummary['tas_count'] }})
                </a>
            </div>
        </div>

        <div class="toolbar-group">
            <button type="button" class="btn btn-primary" onclick="window.print()" title="Print via Browser / Save as PDF">
                <i class="fa-solid fa-print"></i>
                <span>Print / Save PDF</span>
            </button>
            <button type="button" class="btn btn-danger" id="btnDownloadPdf" onclick="downloadReceiptsPdf()" title="Download PDF File directly to Computer">
                <i class="fa-solid fa-file-arrow-down"></i>
                <span>Download Receipts PDF</span>
            </button>
        </div>
    </div>

    {{-- ── Main PDF Document Container ────────────────────────────────────────── --}}
    <div class="pdf-container" id="receiptsPdfDocument">

        {{-- ── Header Section ─────────────────────────────────────────────────── --}}
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
                <span class="badge-pill badge-blue">
                    <i class="fa-solid fa-shield-check"></i> Verified Evidence
                </span>
                <span style="font-size: 8.5px; color: #64748b; margin-top: 4px;">
                    Generated: {{ now()->format('d M Y, H:i') }} by {{ auth()->user()->name ?? 'Finance' }}
                </span>
            </div>
        </div>

        {{-- ── Executive Summary KPI Cards ─────────────────────────────────────── --}}
        <div class="kpi-strip">
            <div class="kpi-card">
                <div class="kpi-label"><i class="fa-solid fa-file-invoice" style="color:#1e3a8a;"></i> Receipts Count</div>
                <div class="kpi-value">{{ $receiptsSummary['total_count'] }}</div>
                <div class="kpi-sub">{{ $receiptsSummary['vat_count'] }} VAT &bull; {{ $receiptsSummary['tas_count'] }} TAS</div>
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
                <div class="kpi-label"><i class="fa-solid fa-paperclip" style="color:#b45309;"></i> Slips On File</div>
                <div class="kpi-value text-right" style="color:#b45309;">{{ $receiptsSummary['with_file_count'] }} / {{ $receiptsSummary['total_count'] }}</div>
                <div class="kpi-sub">Digital Attachment Proof</div>
            </div>
        </div>

        {{-- ── SECTION 1: MASTER RECEIPTS SCHEDULE (TABULAR AUDIT TRAIL) ────────── --}}
        <div class="section-header">
            <h3><i class="fa-solid fa-list-check" style="color: #1e3a8a;"></i> 1. Master Receipts Register &amp; Voucher Schedule</h3>
            <span>Total {{ $addedReceipts->count() }} Verified Receipts</span>
        </div>

        <div class="table-wrap">
            <table class="receipts-table">
                <thead>
                    <tr>
                        <th style="width: 25px;">#</th>
                        <th style="width: 80px;">Date (EC/GC)</th>
                        <th style="width: 100px;">FS No. / Ref</th>
                        <th>Vendor Name &amp; TIN</th>
                        <th>Project &amp; Category</th>
                        <th>Item Description</th>
                        <th class="text-right" style="width: 90px;">Base (ETB)</th>
                        <th class="text-right" style="width: 75px;">VAT 15%</th>
                        <th class="text-right" style="width: 95px;">Total (ETB)</th>
                        <th class="text-center" style="width: 45px;">Slip</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($addedReceipts as $idx => $rec)
                        <tr>
                            <td class="font-mono text-center">{{ $idx + 1 }}</td>
                            <td>
                                <strong class="font-mono" style="color: #0f172a;">{{ $rec['date_ec'] }}</strong>
                                <div style="font-size: 8px; color: #64748b;">{{ $rec['date_gc'] }}</div>
                            </td>
                            <td>
                                <span class="font-mono" style="font-weight: 700; color: #1e3a8a;">{{ $rec['fs_no'] }}</span>
                                @if(!empty($rec['mrc_no']) && $rec['mrc_no'] !== '-')
                                    <div style="font-size: 7.5px; color: #64748b;">MRC: {{ $rec['mrc_no'] }}</div>
                                @endif
                                @if(!empty($rec['request_number']))
                                    <div style="font-size: 7.5px; color: #94a3b8;">{{ $rec['request_number'] }}</div>
                                @endif
                            </td>
                            <td>
                                <strong style="color: #0f172a;">{{ $rec['vendor_name'] }}</strong>
                                <div class="font-mono" style="font-size: 8px; color: #64748b;">TIN: {{ $rec['vendor_tin'] }}</div>
                            </td>
                            <td>
                                <span style="font-weight: 600;">{{ $rec['project_name'] }}</span>
                                <div style="font-size: 8px; color: #64748b; text-transform: capitalize;">{{ $rec['category'] }}</div>
                            </td>
                            <td>
                                <div style="max-width: 160px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="{{ $rec['item_description'] }}">
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
                            <td class="text-center">
                                @if(!empty($rec['file_url']))
                                    <span style="color: #15803d; font-weight: bold;" title="Scanned Document Attached">
                                        <i class="fa-solid fa-paperclip"></i>
                                    </span>
                                @else
                                    <span style="color: #94a3b8;" title="No file attached">-</span>
                                @endif
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
                        <td colspan="6" class="text-right">Grand Total Summary ({{ $addedReceipts->count() }} Receipts):</td>
                        <td class="text-right font-mono">{{ number_format($receiptsSummary['total_base'], 2) }}</td>
                        <td class="text-right font-mono" style="color: #15803d;">{{ number_format($receiptsSummary['total_vat'], 2) }}</td>
                        <td class="text-right font-mono">{{ number_format($receiptsSummary['total_gross'], 2) }}</td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        </div>

        {{-- ── SECTION 2: RECEIPT EVIDENCE CARDS WITH EMBEDDED SLIPS ───────────── --}}
        <div class="page-break" style="margin-top: 10px;"></div>

        <div class="section-header">
            <h3><i class="fa-solid fa-camera-retro" style="color: #1e3a8a;"></i> 2. Attached Receipts Evidence Slips (Photographic &amp; Document Record)</h3>
            <span>Showing verified attached slips for audit inspection</span>
        </div>

        <div class="voucher-grid">
            @forelse($addedReceipts as $idx => $rec)
                <div class="voucher-card">
                    <div class="voucher-header">
                        <div class="voucher-title">
                            <span style="color: #64748b; font-size: 10px;">#{{ $idx + 1 }}</span>
                            <span>{{ $rec['vendor_name'] }}</span>
                        </div>
                        <span class="badge-pill {{ $rec['is_vat_receipt'] ? 'badge-green' : 'badge-blue' }}">
                            {{ $rec['is_vat_receipt'] ? '15% VAT' : '0% TAS' }}
                        </span>
                    </div>

                    <div class="voucher-body">
                        <div class="voucher-meta-row">
                            <div class="meta-item">
                                <div class="meta-label">FS Number</div>
                                <div class="meta-val font-mono" style="color:#1e3a8a;">{{ $rec['fs_no'] }}</div>
                            </div>
                            <div class="meta-item">
                                <div class="meta-label">Vendor TIN</div>
                                <div class="meta-val font-mono">{{ $rec['vendor_tin'] }}</div>
                            </div>
                            <div class="meta-item">
                                <div class="meta-label">Receipt Date</div>
                                <div class="meta-val font-mono">{{ $rec['date_ec'] }}</div>
                            </div>
                            <div class="meta-item">
                                <div class="meta-label">Base (ETB)</div>
                                <div class="meta-val font-mono">{{ number_format($rec['base_value'], 2) }}</div>
                            </div>
                            <div class="meta-item">
                                <div class="meta-label">VAT 15%</div>
                                <div class="meta-val font-mono" style="color:#15803d;">{{ number_format($rec['vat_amount'], 2) }}</div>
                            </div>
                            <div class="meta-item">
                                <div class="meta-label">Total Amount</div>
                                <div class="meta-val font-mono" style="color:#0f172a;">ETB {{ number_format($rec['total_value'], 2) }}</div>
                            </div>
                        </div>

                        <div style="font-size: 8.5px; color: #475569; display: flex; justify-content: space-between;">
                            <span><strong>Project:</strong> {{ $rec['project_name'] }}</span>
                            <span><strong>Ref:</strong> {{ $rec['request_number'] }}</span>
                        </div>
                        <div style="font-size: 8.5px; color: #475569; font-style: italic;">
                            {{ $rec['item_description'] }}
                        </div>

                        {{-- Scanned Slip Visual Container --}}
                        <div class="voucher-image-box">
                            @if(!empty($rec['file_url']))
                                @if(!empty($rec['is_pdf']))
                                    <div class="voucher-no-img">
                                        <i class="fa-solid fa-file-pdf" style="color: #dc2626; opacity: 1;"></i>
                                        <strong>Attached PDF Document</strong>
                                        <div style="font-size: 8px; color: #64748b; margin-top: 2px;">
                                            <a href="{{ $rec['file_url'] }}" target="_blank" style="color: #0284c7; text-decoration: underline;">
                                                Open Scanned PDF Document
                                            </a>
                                        </div>
                                    </div>
                                @else
                                    <img src="{{ $rec['file_url'] }}" alt="Receipt Slip - {{ $rec['fs_no'] }}" loading="lazy" crossorigin="anonymous">
                                @endif
                            @else
                                <div class="voucher-no-img">
                                    <i class="fa-solid fa-receipt"></i>
                                    <span>Fiscal Cash Register Voucher Record</span>
                                    <div style="font-size: 8px; color: #94a3b8; margin-top: 2px;">FS #{{ $rec['fs_no'] }} &bull; Machine MRC: {{ $rec['mrc_no'] }}</div>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            @empty
                <div style="grid-column: span 2; text-align: center; padding: 30px; color: #94a3b8;">
                    No visual receipt evidence cards available for this period.
                </div>
            @endforelse
        </div>

        {{-- ── SECTION 3: FORMAL AUDIT SIGN-OFF & STAMP BLOCK ───────────────────── --}}
        <div class="signoff-section">
            <div class="signoff-title">
                <i class="fa-solid fa-signature" style="color: #1e3a8a;"></i> 
                Tax Audit &amp; ERCA Submission Authorization Block
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

    {{-- ── Client-side PDF Generation Script ───────────────────────────────────── --}}
    <script>
        function downloadReceiptsPdf() {
            const btn = document.getElementById('btnDownloadPdf');
            const originalHtml = btn.innerHTML;
            btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Generating PDF...';
            btn.disabled = true;

            const element = document.getElementById('receiptsPdfDocument');
            const filename = 'Wechecha-Receipts-Audit-{{ Str::slug($monthInfo["title"] ?? "period") }}-{{ date("Y-m-d") }}.pdf';

            const opt = {
                margin:       [8, 6, 8, 6],
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
                setTimeout(function() {
                    downloadReceiptsPdf();
                }, 800);
            });
        @endif
    </script>
</body>
</html>
