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
            --text-muted: #6b7280;
            --border-color: #e5e7eb;
            --bg-light: #f9fafb;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: 'Inter', 'Noto Sans Ethiopic', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
        }

        body {
            background-color: #64748b;
            color: var(--text-main);
            padding: 20px 0;
            font-size: 11.5px;
            line-height: 1.35;
        }

        /* ── Top Floating Action Toolbar ────────────────────────────── */
        .no-print-toolbar {
            max-width: 1140px;
            margin: 0 auto 20px auto;
            background: #ffffff;
            padding: 12px 20px;
            border-radius: 10px;
            box-shadow: 0 4px 14px rgba(0, 0, 0, 0.15);
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

        /* ── PDF Page Physical Sheet (A4 Landscape) ─────────────────── */
        .pdf-page {
            width: 1140px;
            min-height: 780px;
            background: #ffffff;
            margin: 0 auto 25px auto;
            padding: 24px 28px 20px 28px;
            border-radius: 6px;
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.2);
            border: 1px solid #cbd5e1;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            position: relative;
            page-break-after: always;
            break-after: page;
        }

        /* ── Page Header ───────────────────────────────────────────── */
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 2px solid #1e3a8a;
            padding-bottom: 12px;
            margin-bottom: 12px;
        }

        .company-brand {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .company-logo {
            width: 48px;
            height: 48px;
            object-fit: contain;
            border-radius: 6px;
        }

        .company-info h1 {
            font-size: 16px;
            font-weight: 800;
            color: #0f172a;
            letter-spacing: -0.02em;
            margin-bottom: 2px;
        }

        .company-info p {
            font-size: 10.5px;
            color: var(--text-muted);
            font-weight: 500;
        }

        .report-meta-box {
            text-align: right;
            font-size: 10.5px;
            line-height: 1.45;
        }

        .report-meta-box .report-badge {
            background: #eef2ff;
            color: #1e3a8a;
            padding: 2px 8px;
            border-radius: 4px;
            font-weight: 700;
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            display: inline-block;
            margin-bottom: 4px;
            border: 1px solid #c7d2fe;
        }

        /* ── Store Header Bar ──────────────────────────────────────── */
        .store-header-bar {
            background: #1e293b;
            color: #ffffff;
            padding: 7px 12px;
            border-radius: 5px 5px 0 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0;
        }

        .store-header-title {
            font-size: 12px;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .store-header-type {
            font-size: 9.5px;
            font-weight: 600;
            background: rgba(255, 255, 255, 0.18);
            padding: 1px 7px;
            border-radius: 10px;
            margin-left: 6px;
        }

        .store-header-stats {
            font-size: 10.5px;
            color: #94a3b8;
            font-weight: 500;
        }

        .store-header-stats strong {
            color: #38bdf8;
        }

        /* ── Materials Data Table ──────────────────────────────────── */
        .inv-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 10.5px;
            background: #ffffff;
            border: 1px solid var(--border-color);
            border-top: none;
            margin-bottom: 12px;
        }

        .inv-table th {
            background: #f1f5f9;
            color: #334155;
            font-size: 9.5px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            padding: 6px 8px;
            border-bottom: 2px solid #cbd5e1;
            border-right: 1px solid #e2e8f0;
        }

        .inv-table th:last-child {
            border-right: none;
        }

        .inv-table td {
            padding: 5.5px 8px;
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
            padding: 7px 8px;
        }

        .text-start  { text-align: left; }
        .text-center { text-align: center; }
        .text-end    { text-align: right; }

        .font-mono {
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
        }

        .badge-status {
            display: inline-block;
            padding: 1.5px 6px;
            border-radius: 10px;
            font-size: 9px;
            font-weight: 600;
            text-transform: uppercase;
        }
        .badge-available    { background: #dcfce7; color: #15803d; }
        .badge-low-stock    { background: #fef3c7; color: #b45309; }
        .badge-out-of-stock { background: #fee2e2; color: #b91c1c; }

        /* ── 3-Column Ethiopian Store Signatures (አስርካቢ፣ ተረካቢ፣ አረካካቢ) ─ */
        .signature-section-amharic {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 16px;
            margin-top: auto;
            padding-top: 10px;
            border-top: 1.5px solid #cbd5e1;
            page-break-inside: avoid;
        }

        .sign-card {
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            padding: 8px 12px;
            background: #f8fafc;
        }

        .sign-role-title {
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 1.5px solid #1e3a8a;
            padding-bottom: 4px;
            margin-bottom: 6px;
        }

        .sign-role-title .am-title {
            font-size: 12px;
            font-weight: 700;
            color: #1e3a8a;
            font-family: 'Noto Sans Ethiopic', 'Inter', sans-serif;
        }

        .sign-role-title .en-title {
            font-size: 9.5px;
            color: #64748b;
            font-weight: 600;
            text-transform: uppercase;
        }

        .sign-lines {
            display: flex;
            flex-direction: column;
            gap: 4.5px;
        }

        .sign-field {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 10px;
            color: #334155;
        }

        .sign-field .lbl {
            font-weight: 600;
            min-width: 75px;
        }

        .sign-field .dots {
            color: #94a3b8;
            font-weight: normal;
            flex-grow: 1;
            text-align: right;
            font-family: monospace;
            letter-spacing: -0.5px;
        }

        /* ── Page Footer ───────────────────────────────────────────── */
        .page-footer-bar {
            margin-top: 8px;
            padding-top: 6px;
            border-top: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 9.5px;
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

            .pdf-page {
                width: 100% !important;
                min-height: 100vh !important;
                height: 100vh !important;
                box-shadow: none !important;
                border: none !important;
                padding: 5mm 6mm !important;
                margin: 0 !important;
                page-break-after: always !important;
                break-after: page !important;
                page-break-inside: avoid !important;
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

            .sign-card {
                background: #ffffff !important;
                border: 1px solid #94a3b8 !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }

            @page {
                size: A4 landscape;
                margin: 6mm;
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
                <i class="fas fa-file-pdf text-danger me-1"></i> Inventory PDF by Store (አስርካቢ፣ ተረካቢ፣ አረካካቢ)
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

    <!-- ── Flatten and Chunk Store Pages ────────────────────────────── -->
    @php
        $itemsPerPage = 14; // Optimum rows per A4 landscape sheet with 3-signature block
        $allPages = [];

        foreach ($structuredStores as $storeData) {
            $st = $storeData['store'];
            $items = $storeData['items'];
            $itemChunks = !empty($items) ? array_chunk($items, $itemsPerPage) : [[]];
            $totalStorePages = count($itemChunks);

            foreach ($itemChunks as $chunkIdx => $chunkItems) {
                $allPages[] = [
                    'store'            => $st,
                    'items'            => $chunkItems,
                    'chunk_index'      => $chunkIdx,
                    'is_first_page'    => ($chunkIdx === 0),
                    'is_last_page'     => ($chunkIdx === $totalStorePages - 1),
                    'page_in_store'    => $chunkIdx + 1,
                    'total_store_pages'=> $totalStorePages,
                    'store_totals'     => $storeData,
                ];
            }
        }
        $totalReportPages = count($allPages);
    @endphp

    <div id="pdfReportContent">
        @forelse($allPages as $globalIdx => $pageData)
            @php
                $currStore = $pageData['store'];
                $pageItems = $pageData['items'];
                $storeTotals = $pageData['store_totals'];
                $itemOffset = $pageData['chunk_index'] * $itemsPerPage;
            @endphp

            <!-- ── A4 Landscape Physical Page ────────────────────────── -->
            <div class="pdf-page">

                <div>
                    <!-- Page Header -->
                    <div class="page-header">
                        <div class="company-brand">
                            <img src="https://res.cloudinary.com/dg1ijsqx6/image/upload/v1785238806/Gemini_Generated_Image_4aap624aap624aap_1_djaxwl.png" alt="Company Logo" class="company-logo">
                            <div class="company-info">
                                <h1>WECHECHA CONSTRUCTION ERP</h1>
                                <p><i class="fas fa-warehouse me-1"></i> Store Inventory &amp; Material Handover Audit</p>
                            </div>
                        </div>

                        <div class="report-meta-box">
                            <div class="report-badge">Official Store Audit</div>
                            <div style="font-weight:800; color:#1e3a8a; font-size:12px;">{{ $reportTitle }}</div>
                            <div><strong>Date:</strong> {{ $generatedAt->format('M d, Y h:i A') }} &bull; <strong>Page:</strong> {{ $globalIdx + 1 }} of {{ $totalReportPages }}</div>
                        </div>
                    </div>

                    <!-- Store Header Bar -->
                    <div class="store-header-bar">
                        <div class="store-header-title">
                            <i class="fas fa-store"></i> {{ $currStore->name }}
                            <span class="store-header-type">{{ $currStore->type ?? 'Site Store' }}</span>
                            @if($currStore->location)
                                <small style="color:#cbd5e1; font-weight:normal; font-size:9.5px;">&bull; {{ $currStore->location }}</small>
                            @endif
                        </div>
                        <div class="store-header-stats">
                            Store Page: <strong>{{ $pageData['page_in_store'] }} / {{ $pageData['total_store_pages'] }}</strong>
                            | Total Store Items: <strong>{{ $storeTotals['total_items'] }}</strong>
                            | Value: <strong>{{ number_format($storeTotals['total_value'], 2) }} ETB</strong>
                        </div>
                    </div>

                    <!-- Materials Data Table -->
                    <table class="inv-table">
                        <thead>
                            <tr>
                                <th class="text-center" style="width:35px;">#</th>
                                <th class="text-start">Product / Material</th>
                                <th class="text-start" style="width:85px;">SKU / Code</th>
                                <th class="text-start" style="width:105px;">Category</th>
                                <th class="text-center" style="width:45px;">Unit</th>
                                <th class="text-end" style="width:80px;">On-Hand</th>
                                <th class="text-end" style="width:70px;">Reserved</th>
                                <th class="text-end" style="width:80px;">Available</th>
                                <th class="text-end" style="width:80px;">Unit Cost</th>
                                <th class="text-end" style="width:105px;">Total Value</th>
                                <th class="text-center" style="width:70px;">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($pageItems as $subIdx => $itm)
                                <tr>
                                    <td class="text-center font-mono text-muted" style="font-size:9.5px;">{{ $itemOffset + $subIdx + 1 }}</td>
                                    <td class="text-start">
                                        <strong style="color:#0f172a;">{{ $itm['product_name'] }}</strong>
                                    </td>
                                    <td class="text-start font-mono" style="font-size:9.5px; color:#475569;">
                                        {{ $itm['sku'] }}
                                    </td>
                                    <td class="text-start text-muted" style="font-size:9.5px;">
                                        {{ $itm['category'] }}
                                    </td>
                                    <td class="text-center">
                                        <span style="background:#f1f5f9; padding:1px 5px; border-radius:3px; font-weight:600; font-size:9.5px;">{{ $itm['unit'] }}</span>
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
                                    <td colspan="11" class="text-center" style="padding:25px; color:#94a3b8;">
                                        No material inventory records found for this store matching current criteria.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                        @if($pageData['is_last_page'] && count($pageItems) > 0)
                            <tfoot>
                                <tr>
                                    <td colspan="5" class="text-end" style="text-transform:uppercase; font-size:10px; color:#475569;">
                                        Subtotal ({{ $currStore->name }}):
                                    </td>
                                    <td class="text-end font-mono fw-bold">{{ number_format($storeTotals['total_on_hand'], 2) }}</td>
                                    <td class="text-end font-mono text-muted">{{ number_format($storeTotals['total_reserved'], 2) }}</td>
                                    <td class="text-end font-mono fw-bold" style="color:#059669;">{{ number_format($storeTotals['total_available'], 2) }}</td>
                                    <td></td>
                                    <td class="text-end font-mono fw-bold" style="color:#1e3a8a; font-size:11.5px;">{{ number_format($storeTotals['total_value'], 2) }} ETB</td>
                                    <td></td>
                                </tr>
                            </tfoot>
                        @endif
                    </table>

                    @if($globalIdx === $totalReportPages - 1 && count($structuredStores) > 1)
                        <div class="grand-summary-box" style="background:#f8fafc; border:1.5px solid #1e3a8a; border-radius:6px; padding:7px 14px; margin-bottom:10px; display:flex; justify-content:space-between; align-items:center;">
                            <div style="font-weight:700; color:#1e3a8a; font-size:10.5px; text-transform:uppercase;">
                                <i class="fas fa-calculator me-1"></i> GRAND TOTAL (ALL {{ count($structuredStores) }} STORES):
                            </div>
                            <div style="font-size:10.5px; color:#334155;">
                                Total On-Hand: <strong class="font-mono">{{ number_format($grandTotalOnHand, 2) }}</strong> &bull;
                                Available: <strong class="font-mono" style="color:#059669;">{{ number_format($grandTotalAvailable, 2) }}</strong> &bull;
                                Valuation: <strong class="font-mono" style="color:#1e3a8a; font-size:11.5px;">{{ number_format($grandTotalValue, 2) }} ETB</strong>
                            </div>
                        </div>
                    @endif
                </div>

                <!-- ── BOTTOM SECTION OF EVERY PAGE: "አስርካቢ፣ ተረካቢ፣ አረካካቢ" ── -->
                <div>
                    <div class="signature-section-amharic">
                        <!-- 1. አስርካቢ (Handed Over / Issued By) -->
                        <div class="sign-card">
                            <div class="sign-role-title">
                                <span class="am-title">አስርካቢ / አስረካቢ</span>
                                <span class="en-title">(Handed Over By)</span>
                            </div>
                            <div class="sign-lines">
                                <div class="sign-field">
                                    <span class="lbl">ስም / Name:</span>
                                    <span class="dots">___________________________</span>
                                </div>
                                <div class="sign-field">
                                    <span class="lbl">ፊርማ / Sign:</span>
                                    <span class="dots">___________________________</span>
                                </div>
                                <div class="sign-field">
                                    <span class="lbl">ቀን / Date:</span>
                                    <span class="dots">___________________________</span>
                                </div>
                            </div>
                        </div>

                        <!-- 2. ተረካቢ (Received By) -->
                        <div class="sign-card">
                            <div class="sign-role-title">
                                <span class="am-title">ተረካቢ</span>
                                <span class="en-title">(Received By)</span>
                            </div>
                            <div class="sign-lines">
                                <div class="sign-field">
                                    <span class="lbl">ስም / Name:</span>
                                    <span class="dots">___________________________</span>
                                </div>
                                <div class="sign-field">
                                    <span class="lbl">ፊርማ / Sign:</span>
                                    <span class="dots">___________________________</span>
                                </div>
                                <div class="sign-field">
                                    <span class="lbl">ቀን / Date:</span>
                                    <span class="dots">___________________________</span>
                                </div>
                            </div>
                        </div>

                        <!-- 3. አረካካቢ (Reconciled / Audited / Witnessed By) -->
                        <div class="sign-card">
                            <div class="sign-role-title">
                                <span class="am-title">አረካካቢ</span>
                                <span class="en-title">(Audited / Reconciled By)</span>
                            </div>
                            <div class="sign-lines">
                                <div class="sign-field">
                                    <span class="lbl">ስም / Name:</span>
                                    <span class="dots">___________________________</span>
                                </div>
                                <div class="sign-field">
                                    <span class="lbl">ፊርማ / Sign:</span>
                                    <span class="dots">___________________________</span>
                                </div>
                                <div class="sign-field">
                                    <span class="lbl">ቀን / Date:</span>
                                    <span class="dots">___________________________</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Page Footer Bar -->
                    <div class="page-footer-bar">
                        <div>
                            ConstructPro ERP &bull; Wechecha Construction &bull; {{ $currStore->name }}
                        </div>
                        <div>
                            Official Count Sheet &bull; Confidential Document &bull; Sheet {{ $globalIdx + 1 }} of {{ $totalReportPages }}
                        </div>
                    </div>
                </div>

            </div>
        @empty
            <div class="pdf-page" style="justify-content:center; align-items:center; text-align:center;">
                <i class="fas fa-boxes-stacked" style="font-size:42px; color:#94a3b8; margin-bottom:12px; display:block;"></i>
                <h3 style="color:#334155; font-size:16px; font-weight:700;">No Inventory Records Found</h3>
                <p style="color:#64748b; font-size:12px; margin-top:5px;">There are no inventory records matching the selected store or filter criteria.</p>
            </div>
        @endforelse
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
                margin:       [0, 0, 0, 0],
                filename:     '{{ Str::slug($reportTitle) }}-{{ date("Y-m-d") }}.pdf',
                image:        { type: 'jpeg', quality: 0.98 },
                html2canvas:  { scale: 2, useCORS: true, logging: false },
                jsPDF:        { unit: 'mm', format: 'a4', orientation: 'landscape' },
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
