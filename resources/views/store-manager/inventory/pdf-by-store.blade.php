<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $reportTitle }} - ConstructPro ERP</title>

    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
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
            --text-muted: #6b7280;
            --border-color: #e5e7eb;
            --bg-light: #f9fafb;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
        }

        body {
            background-color: #f3f4f6;
            color: var(--text-main);
            padding: 20px 0;
            font-size: 12px;
            line-height: 1.4;
        }

        /* ── Top Floating Action Toolbar ────────────────────────────── */
        .no-print-toolbar {
            max-width: 1200px;
            margin: 0 auto 20px auto;
            background: #ffffff;
            padding: 12px 20px;
            border-radius: 10px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
            display: flex;
            justify-content: space-between;
            align-items: center;
            border: 1px solid var(--border-color);
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 13px;
            font-weight: 600;
            padding: 8px 16px;
            border-radius: 6px;
            border: none;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.2s ease;
        }

        .btn-primary { background: #1e3a8a; color: #fff; }
        .btn-primary:hover { background: #1e293b; }

        .btn-danger { background: #dc2626; color: #fff; }
        .btn-danger:hover { background: #b91c1c; }

        .btn-outline-secondary { background: #fff; color: #4b5563; border: 1px solid #d1d5db; }
        .btn-outline-secondary:hover { background: #f3f4f6; color: #111827; }

        /* ── Printable Report Container ─────────────────────────────── */
        .report-page-container {
            max-width: 1200px;
            margin: 0 auto;
            background: #ffffff;
            padding: 35px 40px;
            border-radius: 8px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.06);
            border: 1px solid var(--border-color);
        }

        /* ── Report Header ─────────────────────────────────────────── */
        .report-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            border-bottom: 2px solid #1e3a8a;
            padding-bottom: 18px;
            margin-bottom: 20px;
        }

        .company-brand {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .company-logo {
            width: 60px;
            height: 60px;
            object-fit: contain;
            border-radius: 6px;
        }

        .company-info h1 {
            font-size: 18px;
            font-weight: 800;
            color: #0f172a;
            letter-spacing: -0.02em;
            margin-bottom: 2px;
        }

        .company-info p {
            font-size: 11px;
            color: var(--text-muted);
            font-weight: 500;
        }

        .report-meta-box {
            text-align: right;
            font-size: 11px;
            line-height: 1.5;
        }

        .report-meta-box .report-badge {
            background: #eef2ff;
            color: #1e3a8a;
            padding: 3px 10px;
            border-radius: 4px;
            font-weight: 700;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            display: inline-block;
            margin-bottom: 6px;
            border: 1px solid #c7d2fe;
        }

        /* ── Executive Summary KPI Cards ───────────────────────────── */
        .kpi-row {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 12px;
            margin-bottom: 25px;
        }

        .kpi-card {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            padding: 10px 14px;
            text-align: center;
        }

        .kpi-card.highlight {
            background: #eff6ff;
            border-color: #bfdbfe;
        }

        .kpi-label {
            font-size: 9.5px;
            font-weight: 700;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.03em;
            margin-bottom: 4px;
        }

        .kpi-value {
            font-size: 15px;
            font-weight: 800;
            color: #0f172a;
        }

        .kpi-card.highlight .kpi-value {
            color: #1e3a8a;
        }

        /* ── Store Section ─────────────────────────────────────────── */
        .store-section {
            margin-bottom: 30px;
            page-break-inside: avoid;
        }

        .store-header-bar {
            background: #1e293b;
            color: #ffffff;
            padding: 8px 14px;
            border-radius: 6px 6px 0 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .store-header-title {
            font-size: 13px;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .store-header-type {
            font-size: 10px;
            font-weight: 600;
            background: rgba(255, 255, 255, 0.15);
            padding: 2px 8px;
            border-radius: 12px;
            margin-left: 8px;
        }

        .store-header-stats {
            font-size: 11px;
            color: #94a3b8;
            font-weight: 500;
        }

        .store-header-stats strong {
            color: #38bdf8;
        }

        /* ── Data Tables ───────────────────────────────────────────── */
        .inv-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 11px;
            background: #ffffff;
            border: 1px solid var(--border-color);
            border-top: none;
            border-radius: 0 0 6px 6px;
            overflow: hidden;
        }

        .inv-table th {
            background: #f1f5f9;
            color: #334155;
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            padding: 7px 10px;
            border-bottom: 2px solid #cbd5e1;
            border-right: 1px solid #e2e8f0;
        }

        .inv-table th:last-child {
            border-right: none;
        }

        .inv-table td {
            padding: 7px 10px;
            border-bottom: 1px solid #e2e8f0;
            border-right: 1px solid #f1f5f9;
            vertical-align: middle;
        }

        .inv-table td:last-child {
            border-right: none;
        }

        .inv-table tr:nth-child(even) td {
            background-color: #fbfcfe;
        }

        .inv-table tfoot td {
            background-color: #f8fafc;
            font-weight: 700;
            border-top: 2px solid #cbd5e1;
            padding: 9px 10px;
        }

        .text-start  { text-align: left; }
        .text-center { text-align: center; }
        .text-end    { text-align: right; }

        .font-mono {
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
        }

        .badge-status {
            display: inline-block;
            padding: 2px 7px;
            border-radius: 12px;
            font-size: 9.5px;
            font-weight: 600;
            text-transform: uppercase;
        }
        .badge-available    { background: #dcfce7; color: #15803d; }
        .badge-low-stock    { background: #fef3c7; color: #b45309; }
        .badge-out-of-stock { background: #fee2e2; color: #b91c1c; }

        /* ── Grand Summary Table ───────────────────────────────────── */
        .grand-summary-box {
            background: #f8fafc;
            border: 2px solid #1e3a8a;
            border-radius: 8px;
            padding: 16px 20px;
            margin-top: 25px;
            margin-bottom: 30px;
            page-break-inside: avoid;
        }

        .grand-summary-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 15px;
            text-align: center;
        }

        .grand-item-title {
            font-size: 10px;
            font-weight: 700;
            color: var(--text-muted);
            text-transform: uppercase;
            margin-bottom: 3px;
        }

        .grand-item-value {
            font-size: 16px;
            font-weight: 800;
            color: #1e3a8a;
        }

        /* ── Signature Section ─────────────────────────────────────── */
        .signature-section {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 30px;
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px dashed var(--border-color);
            page-break-inside: avoid;
        }

        .sign-block {
            text-align: center;
        }

        .sign-line {
            border-bottom: 1px solid #94a3b8;
            height: 40px;
            margin-bottom: 8px;
        }

        .sign-title {
            font-size: 11px;
            font-weight: 700;
            color: #334155;
            text-transform: uppercase;
        }

        .sign-subtitle {
            font-size: 10px;
            color: var(--text-muted);
        }

        /* ── Report Footer ─────────────────────────────────────────── */
        .report-footer {
            margin-top: 30px;
            padding-top: 12px;
            border-top: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            font-size: 10px;
            color: #94a3b8;
        }

        /* ── Print Media Optimization ──────────────────────────────── */
        @media print {
            body {
                background: #ffffff;
                padding: 0;
                color: #000;
            }

            .no-print-toolbar {
                display: none !important;
            }

            .report-page-container {
                max-width: 100% !important;
                box-shadow: none !important;
                border: none !important;
                padding: 10px 15px !important;
                margin: 0 !important;
            }

            .store-header-bar {
                background: #1e293b !important;
                color: #ffffff !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }

            .inv-table th {
                background: #e2e8f0 !important;
                color: #000 !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }

            .kpi-card, .grand-summary-box {
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }

            .store-section {
                page-break-inside: avoid;
            }

            @page {
                size: A4 landscape;
                margin: 10mm;
            }
        }
    </style>
</head>
<body>

    <!-- ── Floating Actions Toolbar (Hidden in Print & PDF) ────────── -->
    <div class="no-print-toolbar">
        <div style="display:flex; align-items:center; gap:10px;">
            <a href="{{ route('store-manager.inventory.all') }}" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left"></i> Back to Inventory
            </a>
            <span style="color:#6b7280; font-size:12px;">|</span>
            <span style="font-weight:600; color:#374151;">
                <i class="fas fa-file-pdf text-danger me-1"></i> Inventory PDF Export (By Store)
            </span>
        </div>
        <div style="display:flex; align-items:center; gap:8px;">
            <button onclick="downloadPdfReport()" id="btnDownloadPdf" class="btn btn-danger">
                <i class="fas fa-download"></i> Download PDF
            </button>
            <button onclick="window.print()" class="btn btn-primary">
                <i class="fas fa-print"></i> Print / Save as PDF
            </button>
        </div>
    </div>

    <!-- ── Printable Report Page Container ──────────────────────────── -->
    <div class="report-page-container" id="pdfReportContent">

        <!-- Report Header -->
        <div class="report-header">
            <div class="company-brand">
                <img src="https://res.cloudinary.com/dg1ijsqx6/image/upload/v1785238806/Gemini_Generated_Image_4aap624aap624aap_1_djaxwl.png" alt="Company Logo" class="company-logo">
                <div class="company-info">
                    <h1>WECHECHA CONSTRUCTION ERP</h1>
                    <p><i class="fas fa-warehouse me-1"></i> Store &amp; Inventory Management System</p>
                    <p style="font-size:10px; color:#9ca3af; margin-top:2px;">Head Office &bull; Addis Ababa, Ethiopia</p>
                </div>
            </div>

            <div class="report-meta-box">
                <div class="report-badge">Official Store Audit</div>
                <div style="font-size:14px; font-weight:800; color:#1e3a8a; margin-bottom:4px;">{{ $reportTitle }}</div>
                <div><strong>Date Generated:</strong> {{ $generatedAt->format('M d, Y h:i A') }}</div>
                <div><strong>Generated By:</strong> {{ $generatedBy }}</div>
                @if($search)
                    <div><strong>Search Filter:</strong> "{{ $search }}"</div>
                @endif
                @if($lowStockOnly)
                    <div><span class="badge-status badge-low-stock">Low Stock Filter Active</span></div>
                @endif
            </div>
        </div>

        <!-- Executive Summary KPI Cards -->
        <div class="kpi-row">
            <div class="kpi-card">
                <div class="kpi-label">Active Stores</div>
                <div class="kpi-value">{{ count($structuredStores) }}</div>
            </div>
            <div class="kpi-card">
                <div class="kpi-label">Total Material Lines</div>
                <div class="kpi-value">{{ number_format($grandTotalItemsCount) }}</div>
            </div>
            <div class="kpi-card">
                <div class="kpi-label">Total Stock On-Hand</div>
                <div class="kpi-value">{{ number_format($grandTotalOnHand, 2) }}</div>
            </div>
            <div class="kpi-card">
                <div class="kpi-label">Total Net Available</div>
                <div class="kpi-value" style="color:#059669;">{{ number_format($grandTotalAvailable, 2) }}</div>
            </div>
            <div class="kpi-card highlight">
                <div class="kpi-label">Total Valuation (ETB)</div>
                <div class="kpi-value">{{ number_format($grandTotalValue, 2) }}</div>
            </div>
        </div>

        <!-- ── Store Inventory Sections (Grouped by Store) ──────────── -->
        @forelse($structuredStores as $storeData)
            @php
                $currStore = $storeData['store'];
                $items = $storeData['items'];
            @endphp
            <div class="store-section">
                <!-- Store Header Bar -->
                <div class="store-header-bar">
                    <div class="store-header-title">
                        <i class="fas fa-store"></i> {{ $currStore->name }}
                        <span class="store-header-type">{{ $currStore->type ?? 'Site Store' }}</span>
                        @if($currStore->location)
                            <small style="color:#cbd5e1; font-weight:normal; font-size:10px;">&bull; Location: {{ $currStore->location }}</small>
                        @endif
                    </div>
                    <div class="store-header-stats">
                        Items: <strong>{{ count($items) }}</strong> | Total On-Hand: <strong>{{ number_format($storeData['total_on_hand'], 2) }}</strong> | Value: <strong>{{ number_format($storeData['total_value'], 2) }} ETB</strong>
                    </div>
                </div>

                <!-- Store Items Table -->
                <table class="inv-table">
                    <thead>
                        <tr>
                            <th class="text-center" style="width:35px;">#</th>
                            <th class="text-start">Product / Material</th>
                            <th class="text-start" style="width:90px;">SKU / Code</th>
                            <th class="text-start" style="width:110px;">Category</th>
                            <th class="text-center" style="width:50px;">Unit</th>
                            <th class="text-end" style="width:85px;">On-Hand</th>
                            <th class="text-end" style="width:75px;">Reserved</th>
                            <th class="text-end" style="width:85px;">Available</th>
                            <th class="text-end" style="width:85px;">Unit Cost</th>
                            <th class="text-end" style="width:105px;">Total Value</th>
                            <th class="text-center" style="width:75px;">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($items as $idx => $itm)
                            <tr>
                                <td class="text-center font-mono text-muted" style="font-size:10px;">{{ $idx + 1 }}</td>
                                <td class="text-start">
                                    <strong style="color:#0f172a;">{{ $itm['product_name'] }}</strong>
                                </td>
                                <td class="text-start font-mono" style="font-size:10px; color:#475569;">
                                    {{ $itm['sku'] }}
                                </td>
                                <td class="text-start text-muted" style="font-size:10px;">
                                    {{ $itm['category'] }}
                                </td>
                                <td class="text-center">
                                    <span style="background:#f1f5f9; padding:1px 5px; border-radius:3px; font-weight:600; font-size:10px;">{{ $itm['unit'] }}</span>
                                </td>
                                <td class="text-end font-mono fw-bold">
                                    {{ number_format($itm['on_hand'], 2) }}
                                </td>
                                <td class="text-end font-mono text-muted">
                                    {{ $itm['reserved'] > 0 ? number_format($itm['reserved'], 2) : '—' }}
                                </td>
                                <td class="text-end font-mono fw-bold" style="color:#059669;">
                                    {{ number_format($itm['available'], 2) }}
                                </td>
                                <td class="text-end font-mono text-muted">
                                    {{ $itm['unit_cost'] > 0 ? number_format($itm['unit_cost'], 2) : '—' }}
                                </td>
                                <td class="text-end font-mono fw-bold" style="color:#1e3a8a;">
                                    {{ number_format($itm['total_value'], 2) }}
                                </td>
                                <td class="text-center">
                                    @if($itm['status'] === 'Available')
                                        <span class="badge-status badge-available">In Stock</span>
                                    @elseif($itm['status'] === 'Low Stock')
                                        <span class="badge-status badge-low-stock">Low Stock</span>
                                    @else
                                        <span class="badge-status badge-out-of-stock">Zero Stock</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="11" class="text-center" style="padding:15px; color:#94a3b8;">
                                    No material inventory records found for this store matching current criteria.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                    @if(count($items) > 0)
                        <tfoot>
                            <tr>
                                <td colspan="5" class="text-end" style="text-transform:uppercase; font-size:10px; color:#475569;">
                                    Subtotal ({{ $currStore->name }}):
                                </td>
                                <td class="text-end font-mono fw-bold">{{ number_format($storeData['total_on_hand'], 2) }}</td>
                                <td class="text-end font-mono text-muted">{{ number_format($storeData['total_reserved'], 2) }}</td>
                                <td class="text-end font-mono fw-bold" style="color:#059669;">{{ number_format($storeData['total_available'], 2) }}</td>
                                <td></td>
                                <td class="text-end font-mono fw-bold" style="color:#1e3a8a; font-size:12px;">{{ number_format($storeData['total_value'], 2) }} ETB</td>
                                <td></td>
                            </tr>
                        </tfoot>
                    @endif
                </table>
            </div>
        @empty
            <div style="text-align:center; padding:50px; background:#f8fafc; border-radius:8px; border:1px solid #e2e8f0;">
                <i class="fas fa-boxes-stacked" style="font-size:36px; color:#94a3b8; margin-bottom:12px; display:block;"></i>
                <h3 style="color:#334155; font-size:16px; font-weight:700;">No Inventory Records Found</h3>
                <p style="color:#64748b; font-size:12px;">There are no inventory records matching the selected store or filter criteria.</p>
            </div>
        @endforelse

        <!-- ── Grand Totals Box ────────────────────────────────────── -->
        @if(count($structuredStores) > 0)
        <div class="grand-summary-box">
            <div class="grand-summary-grid">
                <div>
                    <div class="grand-item-title">Total Stores</div>
                    <div class="grand-item-value">{{ count($structuredStores) }}</div>
                </div>
                <div>
                    <div class="grand-item-title">Total Quantity On-Hand</div>
                    <div class="grand-item-value">{{ number_format($grandTotalOnHand, 2) }}</div>
                </div>
                <div>
                    <div class="grand-item-title">Total Available Quantity</div>
                    <div class="grand-item-value" style="color:#059669;">{{ number_format($grandTotalAvailable, 2) }}</div>
                </div>
                <div>
                    <div class="grand-item-title">Grand Total Valuation (ETB)</div>
                    <div class="grand-item-value">{{ number_format($grandTotalValue, 2) }} ETB</div>
                </div>
            </div>
        </div>
        @endif

        <!-- ── Signature Sign-off Block ────────────────────────────── -->
        <div class="signature-section">
            <div class="sign-block">
                <div class="sign-line"></div>
                <div class="sign-title">Prepared By</div>
                <div class="sign-subtitle">{{ $generatedBy }} / Store Keeper</div>
            </div>
            <div class="sign-block">
                <div class="sign-line"></div>
                <div class="sign-title">Verified By</div>
                <div class="sign-subtitle">Store Manager / Auditor</div>
            </div>
            <div class="sign-block">
                <div class="sign-line"></div>
                <div class="sign-title">Approved By</div>
                <div class="sign-subtitle">General Manager / Project Director</div>
            </div>
        </div>

        <!-- ── Report Footer ───────────────────────────────────────── -->
        <div class="report-footer">
            <div>
                ConstructPro Enterprise Resource Planning System &bull; Wechecha Construction
            </div>
            <div>
                Confidential Document &bull; Printed On: {{ now()->format('Y-m-d H:i') }}
            </div>
        </div>

    </div>

    <!-- ── PDF Generation Script ───────────────────────────────────── -->
    <script>
        function downloadPdfReport() {
            const btn = document.getElementById('btnDownloadPdf');
            const originalText = btn.innerHTML;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Generating PDF...';
            btn.disabled = true;

            const element = document.getElementById('pdfReportContent');
            const opt = {
                margin:       [8, 8, 8, 8],
                filename:     '{{ Str::slug($reportTitle) }}-{{ date("Y-m-d") }}.pdf',
                image:        { type: 'jpeg', quality: 0.98 },
                html2canvas:  { scale: 2, useCORS: true, logging: false },
                jsPDF:        { unit: 'mm', format: 'a4', orientation: 'landscape' },
                pagebreak:    { mode: ['avoid-all', 'css', 'legacy'] }
            };

            html2pdf().set(opt).from(element).save().then(function() {
                btn.innerHTML = originalText;
                btn.disabled = false;
            }).catch(function(err) {
                console.error('PDF generation error:', err);
                btn.innerHTML = originalText;
                btn.disabled = false;
                window.print(); // Fallback to browser print if html2pdf errors
            });
        }
    </script>
</body>
</html>
