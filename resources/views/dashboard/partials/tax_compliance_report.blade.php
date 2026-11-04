{{-- ═══ VAT & WITHHOLDING TAX COMPLIANCE REPORT (TAX COMPLIANCE LEDGER) ═══════ --}}
<div id="vat-withholding-report" class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4 bg-white">
    {{-- Section Header --}}
    <div class="card-header bg-white border-bottom py-3 px-3 px-md-4 d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div class="d-flex align-items-center gap-2">
            <div class="p-2 rounded-3 bg-danger bg-opacity-10 text-danger fs-5 d-flex align-items-center justify-content-center" style="width:40px; height:40px;">
                <i class="fa-solid fa-receipt"></i>
            </div>
            <div>
                <div class="d-flex align-items-center gap-2 flex-wrap">
                    <h6 class="mb-0 fw-bold text-dark fs-6">VAT &amp; Withholding Tax Monthly Compliance Report</h6>
                    <span class="badge bg-danger-subtle text-danger border border-danger-subtle rounded-pill px-2 py-0" style="font-size: 0.7rem;">
                        Tax Compliance Ledger
                    </span>
                    <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-2 py-0" style="font-size: 0.7rem;">
                        <i class="fa-solid fa-calendar-check me-1"></i>የኢትዮጵያ ዘመን አቆጣጠር (Ethiopian Calendar)
                    </span>
                </div>
                <small class="text-muted d-block" style="font-size: 0.78rem;">
                    የቫት እና የ3% ቅድመ ግብር ተቀናሾች መከታተያ እና ወርሃዊ ሪፖርት (ERCA Filing Tracking)
                </small>
            </div>
        </div>
        <div class="d-flex align-items-center gap-2 flex-wrap">
            <button type="button" onclick="window.print()" class="btn btn-outline-secondary btn-sm rounded-pill px-3 shadow-xs">
                <i class="fa-solid fa-print me-1"></i> Print
            </button>
            <a href="{{ route('finance.tax-deductions.export-csv') }}" class="btn btn-success btn-sm rounded-pill px-3 shadow-xs fw-semibold">
                <i class="fa-solid fa-file-excel me-1"></i> Export CSV (ERCA)
            </a>
            <a href="{{ route('finance.tax-deductions.index') }}" class="btn btn-outline-primary btn-sm rounded-pill px-3 shadow-xs fw-semibold">
                <i class="fa-solid fa-arrow-up-right-from-square me-1"></i> Open Full Ledger
            </a>
        </div>
    </div>

    <div class="card-body p-3 p-md-4">
        {{-- ═══ 5 EXECUTIVE KPI CARDS (Matching Tax Compliance Ledger design) ═══ --}}
        <div class="row g-3 mb-4">
            {{-- Card 1: TOTAL VAT (15% / VAT B) --}}
            <div class="col-12 col-sm-6 col-xl">
                <div class="card border-0 shadow-sm rounded-4 h-100 p-3 bg-white border-start border-4 border-info" style="box-shadow: 0 4px 15px rgba(0,0,0,0.04) !important;">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <span class="text-muted small text-uppercase fw-bold" style="font-size:0.75rem; letter-spacing:0.5px;">TOTAL VAT (15% / VAT B)</span>
                        <span class="badge bg-info-subtle text-info rounded-pill px-2 py-1"><i class="fa-solid fa-percent"></i></span>
                    </div>
                    <div class="fs-4 fw-bold text-info">+ ETB {{ number_format($taxData['total_vat_amount'] ?? 0, 2) }}</div>
                    <div class="text-muted small" style="font-size:0.75rem;">ጠቅላላ የተጨመረ/የተካተተ ቫት</div>
                </div>
            </div>

            {{-- Card 2: WITHHOLDING TAX (3%) --}}
            <div class="col-12 col-sm-6 col-xl">
                <div class="card border-0 shadow-sm rounded-4 h-100 p-3 bg-white border-start border-4 border-danger" style="box-shadow: 0 4px 15px rgba(0,0,0,0.04) !important;">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <span class="text-muted small text-uppercase fw-bold" style="font-size:0.75rem; letter-spacing:0.5px;">WITHHOLDING TAX (3%)</span>
                        <span class="badge bg-danger-subtle text-danger rounded-pill px-2 py-1"><i class="fa-solid fa-hand-holding-dollar"></i></span>
                    </div>
                    <div class="fs-4 fw-bold text-danger">- ETB {{ number_format($taxData['total_withholding_amount'] ?? 0, 2) }}</div>
                    <div class="text-muted small" style="font-size:0.75rem;">ጠቅላላ የተቀነሰ 3% ቅድመ ግብር</div>
                </div>
            </div>

            {{-- Card 3: BASE INVOICED AMOUNT --}}
            <div class="col-12 col-sm-6 col-xl">
                <div class="card border-0 shadow-sm rounded-4 h-100 p-3 bg-white border-start border-4 border-primary" style="box-shadow: 0 4px 15px rgba(0,0,0,0.04) !important;">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <span class="text-muted small text-uppercase fw-bold" style="font-size:0.75rem; letter-spacing:0.5px;">Base Invoiced Amount</span>
                        <span class="badge bg-primary-subtle text-primary rounded-pill px-2 py-1"><i class="fa-solid fa-file-invoice"></i></span>
                    </div>
                    <div class="fs-4 fw-bold text-dark">ETB {{ number_format($taxData['total_gross_base'] ?? 0, 2) }}</div>
                    <div class="text-muted small" style="font-size:0.75rem;">ጠቅላላ የመነሻ ዋጋ</div>
                </div>
            </div>

            {{-- Card 4: NET DISBURSED / PAID --}}
            <div class="col-12 col-sm-6 col-xl">
                <div class="card border-0 shadow-sm rounded-4 h-100 p-3 bg-white border-start border-4 border-success" style="box-shadow: 0 4px 15px rgba(0,0,0,0.04) !important;">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <span class="text-muted small text-uppercase fw-bold" style="font-size:0.75rem; letter-spacing:0.5px;">Net Disbursed / Paid</span>
                        <span class="badge bg-success-subtle text-success rounded-pill px-2 py-1"><i class="fa-solid fa-circle-check"></i></span>
                    </div>
                    <div class="fs-4 fw-bold text-success">ETB {{ number_format($taxData['total_net_disbursed'] ?? 0, 2) }}</div>
                    <div class="text-muted small" style="font-size:0.75rem;">ጠቅላላ የተጣራ የተከፈለ</div>
                </div>
            </div>

            {{-- Card 5: VERIFIED WHT SLIPS --}}
            <div class="col-12 col-sm-6 col-xl">
                <div class="card border-0 shadow-sm rounded-4 h-100 p-3 bg-white border-start border-4 border-warning" style="box-shadow: 0 4px 15px rgba(0,0,0,0.04) !important;">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <span class="text-muted small text-uppercase fw-bold" style="font-size:0.75rem; letter-spacing:0.5px;">Verified WHT Slips</span>
                        <span class="badge bg-warning-subtle text-warning rounded-pill px-2 py-1"><i class="fa-solid fa-paperclip"></i></span>
                    </div>
                    <div class="d-flex align-items-baseline gap-2">
                        <span class="fs-4 fw-bold text-success">{{ $taxData['slips_attached_count'] ?? 0 }}</span>
                        <span class="text-muted small">/ {{ $taxData['total_records'] ?? 0 }} Uploaded</span>
                    </div>
                    <div class="text-muted small" style="font-size:0.75rem;">የተያያዙ እና የተረጋገጡ ደረሰኞች</div>
                </div>
            </div>
        </div>

        {{-- ═══ MONTHLY TAX COMPLIANCE BREAKDOWN TABLE & TREND ═════════════════ --}}
        <div class="row g-3">
            {{-- Monthly Breakdown Table --}}
            <div class="col-xl-8">
                <div class="card border shadow-xs rounded-3 h-100">
                    <div class="card-header bg-light-subtle py-2 px-3 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <div class="d-flex align-items-center gap-2">
                            <span class="fw-bold text-dark small">
                                <i class="fa-solid fa-calendar-days text-primary me-1"></i>Monthly Tax Compliance Breakdown (የወርሃዊ ሪፖርት)
                            </span>
                            <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-2 py-0" id="taxCalendarActiveBadge" style="font-size:0.7rem;">
                                <i class="fa-solid fa-calendar-check me-1"></i>የኢትዮጵያ አቆጣጠር (E.C.)
                            </span>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <div class="btn-group btn-group-sm shadow-xs" role="group" aria-label="Tax Calendar View">
                                <button type="button" class="btn btn-xs btn-primary active py-1 px-2 fw-semibold" id="taxCalToggleEth" title="Ethiopian Calendar (ERCA eTax Standard)" style="font-size:0.75rem;">
                                    <i class="fa-solid fa-calendar-day me-1"></i>የኢትዮጵያ (E.C.)
                                </button>
                                <button type="button" class="btn btn-xs btn-outline-secondary py-1 px-2 fw-semibold" id="taxCalToggleGreg" title="Gregorian Calendar (G.C.)" style="font-size:0.75rem;">
                                    <i class="fa-solid fa-globe me-1"></i>Gregorian (G.C.)
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        @php
                            $ecReports = $taxData['monthly_report_ec'] ?? $taxData['monthly_report'] ?? [];
                            $gcReports = $taxData['monthly_report_gc'] ?? [];
                            $hasAnyReports = !empty($ecReports) || !empty($gcReports);
                        @endphp

                        @if(!$hasAnyReports)
                            <div class="text-center py-5">
                                <i class="fa-solid fa-receipt fa-3x text-muted mb-3 opacity-50"></i>
                                <h6 class="fw-bold text-dark">No Tax Records Found</h6>
                                <p class="text-muted small mb-3">VAT and 3% Withholding Tax entries will be recorded here automatically when invoices and payments are processed.</p>
                                <a href="{{ route('finance.tax-deductions.index') }}" class="btn btn-sm btn-outline-primary rounded-pill px-3">
                                    <i class="fa-solid fa-arrow-right me-1"></i>Go to Tax Deductions Ledger
                                </a>
                            </div>
                        @else
                            {{-- 1. ETHIOPIAN CALENDAR BREAKDOWN (Default ERCA View) --}}
                            <div id="taxComplianceViewEc" class="table-responsive">
                                <table class="table table-hover align-middle mb-0" style="font-size: 0.85rem;">
                                    <thead class="table-light small text-uppercase fw-bold">
                                        <tr>
                                            <th class="ps-3">Month / Tax Period (ወር / የግብር ዘመን)</th>
                                            <th class="text-center">Vouchers (ሰነዶች)</th>
                                            <th class="text-end">Base Invoiced (ETB)</th>
                                            <th class="text-end text-info">VAT (15% / VAT B)</th>
                                            <th class="text-end text-danger">3% WHT (ቅድመ ግብር)</th>
                                            <th class="text-end text-success">Net Disbursed (ETB)</th>
                                            <th class="text-center">WHT Slips</th>
                                            <th class="text-end pe-3">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse($ecReports as $m)
                                        <tr>
                                            <td class="ps-3">
                                                <div class="fw-bold text-dark">
                                                    <i class="fa-regular fa-calendar-check text-primary me-1"></i>{{ $m['month_label'] }}
                                                </div>
                                                <div class="d-flex align-items-center gap-1 flex-wrap mt-0">
                                                    @if(!empty($m['eth_period_label']))
                                                        <span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill font-monospace" style="font-size:0.68rem;">
                                                            {{ $m['eth_period_label'] }}
                                                        </span>
                                                    @endif
                                                    <span class="text-muted font-monospace" style="font-size:0.68rem;">
                                                        (GC: {{ $m['from_date'] }} to {{ $m['to_date'] }})
                                                    </span>
                                                </div>
                                            </td>
                                            <td class="text-center">
                                                <span class="badge bg-secondary-subtle text-secondary rounded-pill px-2">
                                                    {{ $m['count'] }}
                                                </span>
                                            </td>
                                            <td class="text-end fw-semibold text-dark">
                                                {{ number_format($m['gross_base'], 2) }}
                                            </td>
                                            <td class="text-end fw-bold text-info">
                                                + {{ number_format($m['vat_amount'], 2) }}
                                            </td>
                                            <td class="text-end fw-bold text-danger">
                                                - {{ number_format($m['wht_amount'], 2) }}
                                            </td>
                                            <td class="text-end fw-semibold text-success">
                                                {{ number_format($m['net_disbursed'], 2) }}
                                            </td>
                                            <td class="text-center">
                                                @if($m['slips_count'] > 0)
                                                    <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-2">
                                                        <i class="fa-solid fa-paperclip me-1"></i>{{ $m['slips_count'] }} attached
                                                    </span>
                                                @else
                                                    <span class="badge bg-warning-subtle text-warning border border-warning-subtle rounded-pill px-2">
                                                        No slips
                                                    </span>
                                                @endif
                                            </td>
                                            <td class="text-end pe-3">
                                                <div class="btn-group btn-group-sm">
                                                    <a href="{{ route('finance.tax-deductions.index', ['ethiopian_year' => $m['eth_year'] ?? '', 'ethiopian_month' => $m['eth_month'] ?? '', 'from_date' => $m['from_date'], 'to_date' => $m['to_date']]) }}" 
                                                       class="btn btn-sm btn-outline-primary rounded-start px-2" title="View monthly vouchers in Tax Ledger">
                                                        <i class="fa-solid fa-eye me-1"></i>View
                                                    </a>
                                                    <a href="{{ route('finance.tax-deductions.export-csv', ['ethiopian_year' => $m['eth_year'] ?? '', 'ethiopian_month' => $m['eth_month'] ?? '', 'from_date' => $m['from_date'], 'to_date' => $m['to_date']]) }}" 
                                                       class="btn btn-sm btn-outline-success rounded-end px-2" title="Export monthly CSV (ERCA)">
                                                        <i class="fa-solid fa-file-excel"></i>
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                        @empty
                                        <tr>
                                            <td colspan="8" class="text-center py-4 text-muted small">No Ethiopian calendar entries recorded.</td>
                                        </tr>
                                        @endforelse
                                    </tbody>
                                    <tfoot class="table-light fw-bold">
                                        <tr>
                                            <td class="ps-3" colspan="2">Grand Totals (ጠቅላላ ድምር)</td>
                                            <td class="text-end text-dark">{{ number_format($taxData['total_gross_base'] ?? 0, 2) }} ETB</td>
                                            <td class="text-end text-info">+ {{ number_format($taxData['total_vat_amount'] ?? 0, 2) }} ETB</td>
                                            <td class="text-end text-danger">- {{ number_format($taxData['total_withholding_amount'] ?? 0, 2) }} ETB</td>
                                            <td class="text-end text-success">{{ number_format($taxData['total_net_disbursed'] ?? 0, 2) }} ETB</td>
                                            <td class="text-center text-success">{{ $taxData['slips_attached_count'] ?? 0 }}</td>
                                            <td class="text-end pe-3">
                                                <a href="{{ route('finance.tax-deductions.index') }}" class="btn btn-xs btn-primary rounded-pill px-2" style="font-size:0.75rem;">
                                                    All Records
                                                </a>
                                            </td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>

                            {{-- 2. GREGORIAN CALENDAR BREAKDOWN (Alternative View) --}}
                            <div id="taxComplianceViewGc" class="table-responsive d-none">
                                <table class="table table-hover align-middle mb-0" style="font-size: 0.85rem;">
                                    <thead class="table-light small text-uppercase fw-bold">
                                        <tr>
                                            <th class="ps-3">Month / Period (G.C.)</th>
                                            <th class="text-center">Vouchers</th>
                                            <th class="text-end">Base Invoiced (ETB)</th>
                                            <th class="text-end text-info">VAT (15% / VAT B)</th>
                                            <th class="text-end text-danger">3% WHT</th>
                                            <th class="text-end text-success">Net Disbursed (ETB)</th>
                                            <th class="text-center">WHT Slips</th>
                                            <th class="text-end pe-3">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse($gcReports as $m)
                                        <tr>
                                            <td class="ps-3">
                                                <div class="fw-bold text-dark">
                                                    <i class="fa-regular fa-calendar-check text-secondary me-1"></i>{{ $m['month_label'] }}
                                                </div>
                                                <small class="text-muted font-monospace" style="font-size:0.72rem;">{{ $m['from_date'] }} to {{ $m['to_date'] }}</small>
                                            </td>
                                            <td class="text-center">
                                                <span class="badge bg-secondary-subtle text-secondary rounded-pill px-2">
                                                    {{ $m['count'] }}
                                                </span>
                                            </td>
                                            <td class="text-end fw-semibold text-dark">
                                                {{ number_format($m['gross_base'], 2) }}
                                            </td>
                                            <td class="text-end fw-bold text-info">
                                                + {{ number_format($m['vat_amount'], 2) }}
                                            </td>
                                            <td class="text-end fw-bold text-danger">
                                                - {{ number_format($m['wht_amount'], 2) }}
                                            </td>
                                            <td class="text-end fw-semibold text-success">
                                                {{ number_format($m['net_disbursed'], 2) }}
                                            </td>
                                            <td class="text-center">
                                                @if($m['slips_count'] > 0)
                                                    <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-2">
                                                        <i class="fa-solid fa-paperclip me-1"></i>{{ $m['slips_count'] }} attached
                                                    </span>
                                                @else
                                                    <span class="badge bg-warning-subtle text-warning border border-warning-subtle rounded-pill px-2">
                                                        No slips
                                                    </span>
                                                @endif
                                            </td>
                                            <td class="text-end pe-3">
                                                <div class="btn-group btn-group-sm">
                                                    <a href="{{ route('finance.tax-deductions.index', ['from_date' => $m['from_date'], 'to_date' => $m['to_date']]) }}" 
                                                       class="btn btn-sm btn-outline-primary rounded-start px-2" title="View monthly vouchers">
                                                        <i class="fa-solid fa-eye me-1"></i>View
                                                    </a>
                                                    <a href="{{ route('finance.tax-deductions.export-csv', ['from_date' => $m['from_date'], 'to_date' => $m['to_date']]) }}" 
                                                       class="btn btn-sm btn-outline-success rounded-end px-2" title="Export monthly CSV">
                                                        <i class="fa-solid fa-file-excel"></i>
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                        @empty
                                        <tr>
                                            <td colspan="8" class="text-center py-4 text-muted small">No Gregorian calendar entries recorded.</td>
                                        </tr>
                                        @endforelse
                                    </tbody>
                                    <tfoot class="table-light fw-bold">
                                        <tr>
                                            <td class="ps-3" colspan="2">Grand Totals</td>
                                            <td class="text-end text-dark">{{ number_format($taxData['total_gross_base'] ?? 0, 2) }} ETB</td>
                                            <td class="text-end text-info">+ {{ number_format($taxData['total_vat_amount'] ?? 0, 2) }} ETB</td>
                                            <td class="text-end text-danger">- {{ number_format($taxData['total_withholding_amount'] ?? 0, 2) }} ETB</td>
                                            <td class="text-end text-success">{{ number_format($taxData['total_net_disbursed'] ?? 0, 2) }} ETB</td>
                                            <td class="text-center text-success">{{ $taxData['slips_attached_count'] ?? 0 }}</td>
                                            <td class="text-end pe-3">
                                                <a href="{{ route('finance.tax-deductions.index') }}" class="btn btn-xs btn-primary rounded-pill px-2" style="font-size:0.75rem;">
                                                    All Records
                                                </a>
                                            </td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            {{-- Monthly Tax Trend Chart --}}
            @php
                $ecList = collect($taxData['monthly_report_ec'] ?? $taxData['monthly_report'] ?? [])->reverse()->values();
                $ecLabels = $ecList->map(fn($item) => $item['chart_label'] ?? $item['short_label'] ?? $item['month_label'])->values();
                $ecVat = $ecList->pluck('vat_amount')->map(fn($v) => (float)$v)->values();
                $ecWht = $ecList->pluck('wht_amount')->map(fn($v) => (float)$v)->values();
                $ecGross = $ecList->pluck('gross_base')->map(fn($v) => (float)$v)->values();
                $ecNet = $ecList->pluck('net_disbursed')->map(fn($v) => (float)$v)->values();

                $gcList = collect($taxData['monthly_report_gc'] ?? [])->reverse()->values();
                $gcLabels = $gcList->map(fn($item) => $item['chart_label'] ?? $item['short_label'] ?? $item['month_label'])->values();
                $gcVat = $gcList->pluck('vat_amount')->map(fn($v) => (float)$v)->values();
                $gcWht = $gcList->pluck('wht_amount')->map(fn($v) => (float)$v)->values();
                $gcGross = $gcList->pluck('gross_base')->map(fn($v) => (float)$v)->values();
                $gcNet = $gcList->pluck('net_disbursed')->map(fn($v) => (float)$v)->values();

                // Initial calendar selection: default Ethiopian
                $initialCal = $taxData['calendar'] ?? 'ethiopian';
                $initLabels = ($initialCal === 'gregorian') ? $gcLabels : $ecLabels;
                $initVat    = ($initialCal === 'gregorian') ? $gcVat : $ecVat;
                $initWht    = ($initialCal === 'gregorian') ? $gcWht : $ecWht;

                // Fallback: If breakdown list is empty but grand totals exist, display aggregate totals
                if ($initLabels->isEmpty() && (($taxData['total_vat_amount'] ?? 0) > 0 || ($taxData['total_withholding_amount'] ?? 0) > 0)) {
                    $initLabels = collect(['Current Total']);
                    $initVat    = collect([(float)($taxData['total_vat_amount'] ?? 0)]);
                    $initWht    = collect([(float)($taxData['total_withholding_amount'] ?? 0)]);
                }
            @endphp

            <div class="col-xl-4">
                <div class="card border shadow-xs rounded-3 h-100">
                    <div class="card-header bg-light-subtle py-2 px-3 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <div class="d-flex align-items-center gap-1">
                            <span class="fw-bold text-dark small">
                                <i class="fa-solid fa-chart-column text-info me-1"></i>Monthly VAT &amp; WHT Trend
                            </span>
                            <span class="badge bg-success-subtle text-success rounded-pill ms-1" id="taxChartCalendarBadge" style="font-size:0.7rem;">
                                {{ ($initialCal === 'gregorian') ? 'Gregorian (G.C.)' : 'የኢትዮጵያ (E.C.)' }}
                            </span>
                        </div>
                        <div class="btn-group btn-group-sm" role="group" aria-label="Chart view mode">
                            <button type="button" class="btn btn-xs btn-outline-secondary active py-0 px-2" id="taxChartModeBar" title="Bar Chart view" style="font-size:0.75rem;">
                                <i class="fa-solid fa-chart-column"></i>
                            </button>
                            <button type="button" class="btn btn-xs btn-outline-secondary py-0 px-2" id="taxChartModeLine" title="Trend Line view" style="font-size:0.75rem;">
                                <i class="fa-solid fa-chart-line"></i>
                            </button>
                        </div>
                    </div>
                    <div class="card-body p-3 d-flex flex-column justify-content-between">
                        <div id="taxComplianceChartWrapper" style="position:relative; height:210px; width:100%;">
                            <canvas id="taxComplianceTrendChart"></canvas>
                        </div>

                        <div id="taxComplianceTrendEmpty" class="text-center py-5 d-none">
                            <i class="fa-solid fa-chart-simple fa-2x text-muted mb-2 opacity-50"></i>
                            <h6 class="text-muted fw-semibold small mb-1">No Tax Trend Data</h6>
                            <p class="text-muted small mb-0" style="font-size:0.75rem;">VAT and 3% WHT entries will be plotted automatically here.</p>
                        </div>

                        <div class="mt-3 pt-2 border-top">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <span class="small text-muted"><i class="fa-solid fa-circle text-info me-1" style="font-size:0.65rem;"></i>Total VAT (15% / B)</span>
                                <strong class="small text-info">+ ETB {{ number_format($taxData['total_vat_amount'] ?? 0, 2) }}</strong>
                            </div>
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="small text-muted"><i class="fa-solid fa-circle text-danger me-1" style="font-size:0.65rem;"></i>Total 3% WHT (ቅድመ ግብር)</span>
                                <strong class="small text-danger">- ETB {{ number_format($taxData['total_withholding_amount'] ?? 0, 2) }}</strong>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    var ecLabels = @json($ecLabels);
    var ecVat = @json($ecVat);
    var ecWht = @json($ecWht);

    var gcLabels = @json($gcLabels);
    var gcVat = @json($gcVat);
    var gcWht = @json($gcWht);

    var currentCalendar = @json($initialCal);
    var currentChartType = 'bar';
    var chartInstance = null;

    function getActiveDataset() {
        var isEc = (currentCalendar === 'ethiopian');
        var l = isEc ? ecLabels : gcLabels;
        var v = isEc ? ecVat : gcVat;
        var w = isEc ? ecWht : gcWht;

        var labels = Array.isArray(l) ? l : Object.values(l || {});
        var vatData = Array.isArray(v) ? v : Object.values(v || {});
        var whtData = Array.isArray(w) ? w : Object.values(w || {});

        return { labels: labels, vatData: vatData, whtData: whtData };
    }

    function ensureChartJs(callback) {
        if (typeof Chart !== 'undefined') {
            callback();
            return;
        }

        var existingScript = document.getElementById('chartjs-cdn-script');
        if (!existingScript) {
            var script = document.createElement('script');
            script.id = 'chartjs-cdn-script';
            script.src = 'https://cdn.jsdelivr.net/npm/chart.js';
            script.onload = function () { callback(); };
            document.head.appendChild(script);
        } else {
            var waitInterval = setInterval(function () {
                if (typeof Chart !== 'undefined') {
                    clearInterval(waitInterval);
                    callback();
                }
            }, 100);
        }
    }

    function drawChart() {
        var canvas = document.getElementById("taxComplianceTrendChart");
        if (!canvas) return;

        var emptyEl = document.getElementById("taxComplianceTrendEmpty");
        var wrapperEl = document.getElementById("taxComplianceChartWrapper");
        var active = getActiveDataset();

        if (!active.labels || active.labels.length === 0) {
            if (emptyEl) emptyEl.classList.remove("d-none");
            if (wrapperEl) wrapperEl.classList.add("d-none");
            return;
        }

        if (emptyEl) emptyEl.classList.add("d-none");
        if (wrapperEl) wrapperEl.classList.remove("d-none");

        if (chartInstance) {
            chartInstance.destroy();
            chartInstance = null;
        }

        var isBar = (currentChartType === 'bar');

        chartInstance = new Chart(canvas, {
            type: currentChartType,
            data: {
                labels: active.labels,
                datasets: [
                    {
                        label: 'Total VAT (15%)',
                        data: active.vatData,
                        backgroundColor: isBar ? 'rgba(13, 202, 240, 0.85)' : 'rgba(13, 202, 240, 0.15)',
                        borderColor: '#0dcaf0',
                        borderWidth: isBar ? 1 : 2.5,
                        borderRadius: isBar ? 6 : 0,
                        fill: !isBar,
                        tension: 0.35,
                        pointBackgroundColor: '#0dcaf0',
                        pointBorderColor: '#ffffff',
                        pointBorderWidth: 2,
                        pointRadius: isBar ? 0 : 4,
                        pointHoverRadius: 6,
                        maxBarThickness: 45,
                    },
                    {
                        label: '3% Withholding Tax',
                        data: active.whtData,
                        backgroundColor: isBar ? 'rgba(220, 53, 69, 0.85)' : 'rgba(220, 53, 69, 0.15)',
                        borderColor: '#dc3545',
                        borderWidth: isBar ? 1 : 2.5,
                        borderRadius: isBar ? 6 : 0,
                        fill: !isBar,
                        tension: 0.35,
                        pointBackgroundColor: '#dc3545',
                        pointBorderColor: '#ffffff',
                        pointBorderWidth: 2,
                        pointRadius: isBar ? 0 : 4,
                        pointHoverRadius: 6,
                        maxBarThickness: 45,
                    }
                ]
            },
            options: {
                maintainAspectRatio: false,
                responsive: true,
                animation: {
                    duration: 700,
                    easing: 'easeOutQuart'
                },
                plugins: {
                    legend: {
                        position: 'top',
                        align: 'end',
                        labels: {
                            boxWidth: 10,
                            boxHeight: 10,
                            usePointStyle: true,
                            pointStyle: 'circle',
                            font: { size: 11, weight: '600', family: "'Inter', system-ui, sans-serif" },
                            padding: 10
                        }
                    },
                    tooltip: {
                        backgroundColor: 'rgba(15, 23, 42, 0.92)',
                        padding: 10,
                        cornerRadius: 8,
                        titleFont: { size: 12, weight: '700' },
                        bodyFont: { size: 11 },
                        callbacks: {
                            label: function(ctx) {
                                var val = Number(ctx.parsed.y || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                                return ' ' + ctx.dataset.label + ': ' + val + ' ETB';
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        grid: { display: false },
                        ticks: {
                            font: { size: 11, weight: '500' },
                            color: '#64748b'
                        }
                    },
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: 'rgba(226, 232, 240, 0.65)',
                            drawBorder: false
                        },
                        ticks: {
                            font: { size: 10 },
                            color: '#94a3b8',
                            callback: function(val) {
                                if (val >= 1000000) return (val/1000000).toFixed(1) + 'M';
                                if (val >= 1000) return (val/1000).toFixed(0) + 'K';
                                return Number(val).toLocaleString();
                            }
                        }
                    }
                }
            }
        });
    }

    function switchCalendar(newCal) {
        if (currentCalendar === newCal) return;
        currentCalendar = newCal;

        var btnEth = document.getElementById("taxCalToggleEth");
        var btnGreg = document.getElementById("taxCalToggleGreg");
        var viewEc = document.getElementById("taxComplianceViewEc");
        var viewGc = document.getElementById("taxComplianceViewGc");
        var badgeTbl = document.getElementById("taxCalendarActiveBadge");
        var badgeChart = document.getElementById("taxChartCalendarBadge");

        if (newCal === 'ethiopian') {
            if (btnEth) { btnEth.classList.add('btn-primary', 'active'); btnEth.classList.remove('btn-outline-secondary'); }
            if (btnGreg) { btnGreg.classList.remove('btn-primary', 'active'); btnGreg.classList.add('btn-outline-secondary'); }
            if (viewEc) viewEc.classList.remove('d-none');
            if (viewGc) viewGc.classList.add('d-none');
            if (badgeTbl) badgeTbl.innerHTML = '<i class="fa-solid fa-calendar-check me-1"></i>የኢትዮጵያ አቆጣጠር (E.C.)';
            if (badgeChart) badgeChart.textContent = 'የኢትዮጵያ (E.C.)';
        } else {
            if (btnGreg) { btnGreg.classList.add('btn-primary', 'active'); btnGreg.classList.remove('btn-outline-secondary'); }
            if (btnEth) { btnEth.classList.remove('btn-primary', 'active'); btnEth.classList.add('btn-outline-secondary'); }
            if (viewGc) viewGc.classList.remove('d-none');
            if (viewEc) viewEc.classList.add('d-none');
            if (badgeTbl) badgeTbl.innerHTML = '<i class="fa-solid fa-globe me-1"></i>Gregorian (G.C.)';
            if (badgeChart) badgeChart.textContent = 'Gregorian (G.C.)';
        }

        drawChart();
    }

    function init() {
        ensureChartJs(function () {
            drawChart();

            var btnBar = document.getElementById("taxChartModeBar");
            var btnLine = document.getElementById("taxChartModeLine");
            if (btnBar && btnLine) {
                btnBar.addEventListener("click", function () {
                    if (currentChartType === 'bar') return;
                    currentChartType = 'bar';
                    btnBar.classList.add('active');
                    btnLine.classList.remove('active');
                    drawChart();
                });

                btnLine.addEventListener("click", function () {
                    if (currentChartType === 'line') return;
                    currentChartType = 'line';
                    btnLine.classList.add('active');
                    btnBar.classList.remove('active');
                    drawChart();
                });
            }

            var btnCalEth = document.getElementById("taxCalToggleEth");
            var btnCalGreg = document.getElementById("taxCalToggleGreg");
            if (btnCalEth) {
                btnCalEth.addEventListener("click", function () {
                    switchCalendar('ethiopian');
                });
            }
            if (btnCalGreg) {
                btnCalGreg.addEventListener("click", function () {
                    switchCalendar('gregorian');
                });
            }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener("DOMContentLoaded", init);
    } else {
        init();
    }
    window.addEventListener("load", function () {
        if (!chartInstance) {
            init();
        }
    });
})();
</script>
