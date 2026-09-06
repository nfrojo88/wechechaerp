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
                    <div class="card-header bg-light-subtle py-2 px-3 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-1">
                        <span class="fw-bold text-dark small">
                            <i class="fa-solid fa-calendar-days text-primary me-1"></i>Monthly Tax Compliance Breakdown (የወርሃዊ ሪፖርት)
                        </span>
                        <small class="text-muted">Aggregated by payment &amp; disbursement date</small>
                    </div>
                    <div class="card-body p-0">
                        @if(empty($taxData['monthly_report']))
                            <div class="text-center py-5">
                                <i class="fa-solid fa-receipt fa-3x text-muted mb-3 opacity-50"></i>
                                <h6 class="fw-bold text-dark">No Tax Records Found</h6>
                                <p class="text-muted small mb-3">VAT and 3% Withholding Tax entries will be recorded here automatically when invoices and payments are processed.</p>
                                <a href="{{ route('finance.tax-deductions.index') }}" class="btn btn-sm btn-outline-primary rounded-pill px-3">
                                    <i class="fa-solid fa-arrow-right me-1"></i>Go to Tax Deductions Ledger
                                </a>
                            </div>
                        @else
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0" style="font-size: 0.85rem;">
                                    <thead class="table-light small text-uppercase fw-bold">
                                        <tr>
                                            <th class="ps-3">Month / Period</th>
                                            <th class="text-center">Vouchers</th>
                                            <th class="text-end">Base Invoiced (ETB)</th>
                                            <th class="text-end text-info">VAT (15% / VAT B)</th>
                                            <th class="text-end text-danger">3% WHT (ቅድመ ግብር)</th>
                                            <th class="text-end text-success">Net Disbursed (ETB)</th>
                                            <th class="text-center">WHT Slips</th>
                                            <th class="text-end pe-3">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($taxData['monthly_report'] as $m)
                                        <tr>
                                            <td class="ps-3">
                                                <div class="fw-bold text-dark">
                                                    <i class="fa-regular fa-calendar-check text-primary me-1"></i>{{ $m['month_label'] }}
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
                                        @endforeach
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
            <div class="col-xl-4">
                <div class="card border shadow-xs rounded-3 h-100">
                    <div class="card-header bg-light-subtle py-2 px-3 border-bottom d-flex justify-content-between align-items-center">
                        <span class="fw-bold text-dark small">
                            <i class="fa-solid fa-chart-column text-info me-1"></i>Monthly VAT &amp; WHT Trend
                        </span>
                        <small class="text-muted">VAT vs 3% WHT</small>
                    </div>
                    <div class="card-body p-3 d-flex flex-column justify-content-between">
                        <div id="tax-trend-data"
                            data-labels="@json(collect($taxData['monthly_report'] ?? [])->reverse()->pluck('month_label'))"
                            data-vat="@json(collect($taxData['monthly_report'] ?? [])->reverse()->pluck('vat_amount'))"
                            data-wht="@json(collect($taxData['monthly_report'] ?? [])->reverse()->pluck('wht_amount'))"
                            class="d-none"></div>

                        <div style="position:relative; height:200px; width:100%;">
                            <canvas id="taxComplianceTrendChart"></canvas>
                        </div>

                        <div class="mt-3 pt-2 border-top">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <span class="small text-muted"><i class="fa-solid fa-square text-info me-1"></i>Total VAT Collected</span>
                                <strong class="small text-info">+ ETB {{ number_format($taxData['total_vat_amount'] ?? 0, 2) }}</strong>
                            </div>
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="small text-muted"><i class="fa-solid fa-square text-danger me-1"></i>Total 3% WHT Deducted</span>
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
document.addEventListener("DOMContentLoaded", function () {
    var taxTrendEl = document.getElementById("tax-trend-data");
    if (taxTrendEl && typeof Chart !== 'undefined') {
        var labels = JSON.parse(taxTrendEl.dataset.labels || '[]');
        var vatData = JSON.parse(taxTrendEl.dataset.vat || '[]');
        var whtData = JSON.parse(taxTrendEl.dataset.wht || '[]');

        var ctx = document.getElementById("taxComplianceTrendChart");
        if (ctx && labels.length > 0) {
            new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [
                        {
                            label: 'Total VAT (15%)',
                            data: vatData,
                            backgroundColor: 'rgba(13, 202, 240, 0.8)',
                            borderColor: '#0dcaf0',
                            borderWidth: 1,
                            borderRadius: 4,
                        },
                        {
                            label: '3% Withholding Tax',
                            data: whtData,
                            backgroundColor: 'rgba(220, 53, 69, 0.8)',
                            borderColor: '#dc3545',
                            borderWidth: 1,
                            borderRadius: 4,
                        }
                    ]
                },
                options: {
                    maintainAspectRatio: false,
                    responsive: true,
                    plugins: {
                        legend: { position: 'bottom', labels: { boxWidth: 10, font: { size: 10 } } },
                        tooltip: {
                            callbacks: {
                                label: function(ctx) {
                                    return ctx.dataset.label + ': ' + new Intl.NumberFormat().format(ctx.parsed.y) + ' ETB';
                                }
                            }
                        }
                    },
                    scales: {
                        x: { grid: { display: false } },
                        y: {
                            ticks: {
                                callback: function(val) {
                                    if (val >= 1000000) return (val/1000000).toFixed(1) + 'M';
                                    if (val >= 1000) return (val/1000).toFixed(0) + 'K';
                                    return val;
                                }
                            }
                        }
                    }
                }
            });
        }
    }
});
</script>
