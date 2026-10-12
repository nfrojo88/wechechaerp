@extends('layouts.app')

@section('title', 'VAT & Withholding Tax Deductions Ledger')

@section('content')
<div class="container-fluid px-3 px-md-4 py-4">

    {{-- System Flash Alerts --}}
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm rounded-4 mb-4 d-flex align-items-center gap-3 p-3 bg-success bg-opacity-10 border-start border-4 border-success" role="alert">
            <div class="p-2 bg-success text-white rounded-circle shadow-xs fs-5">
                <i class="fa-solid fa-circle-check"></i>
            </div>
            <div class="flex-grow-1 text-dark fw-medium">{{ session('success') }}</div>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm rounded-4 mb-4 d-flex align-items-center gap-3 p-3 bg-danger bg-opacity-10 border-start border-4 border-danger" role="alert">
            <div class="p-2 bg-danger text-white rounded-circle shadow-xs fs-5">
                <i class="fa-solid fa-triangle-exclamation"></i>
            </div>
            <div class="flex-grow-1 text-dark fw-medium">{{ session('error') }}</div>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    {{-- Pending Settlements Notification Banner --}}
    @if(isset($pendingSettlements) && $pendingSettlements->isNotEmpty())
        @foreach($pendingSettlements as $ps)
            @php
                $isVatOnly = $ps->tax_type === 'vat';
                $isWhtOnly = $ps->tax_type === 'withholding';
                $themeColor = $isVatOnly ? 'info' : ($isWhtOnly ? 'danger' : 'warning');
                $iconClass = $isVatOnly ? 'fa-percent' : ($isWhtOnly ? 'fa-hand-holding-dollar' : 'fa-hourglass-half');
                $taxTitle = $isVatOnly ? '15% VAT Remittance Assigned' : ($isWhtOnly ? '3% Withholding Tax Assigned' : 'Tax Remittance Assigned (VAT + WHT)');
            @endphp
            <div class="card border-0 shadow-sm rounded-4 mb-4 overflow-hidden border-start border-4 border-{{ $themeColor }} bg-white">
                <div class="card-body p-3 p-md-4">
                    <div class="d-flex flex-column flex-lg-row align-items-start align-items-lg-center justify-content-between gap-3">
                        <div class="d-flex align-items-start gap-3">
                            <div class="p-3 bg-{{ $themeColor }} bg-opacity-10 text-{{ $themeColor }} rounded-4 fs-4 flex-shrink-0">
                                <i class="fa-solid {{ $iconClass }}"></i>
                            </div>
                            <div>
                                <div class="d-flex align-items-center gap-2 flex-wrap mb-1">
                                    <span class="badge bg-{{ $themeColor }}-subtle text-{{ $themeColor }} border border-{{ $themeColor }}-subtle fw-bold px-2.5 py-1 rounded-pill small">
                                        {{ $taxTitle }}
                                    </span>
                                    <span class="fw-bold text-dark font-monospace">{{ $ps->settlement_number }}</span>
                                    <span class="text-muted small">({{ optional($ps->created_at)->format('M d, Y H:i') }})</span>
                                </div>
                                <div class="text-dark small">
                                    Finance Head assigned: <strong class="text-primary">{{ $ps->financeHead?->name ?? 'Finance Head' }}</strong> &rarr; <strong class="text-success">{{ $ps->assignedStaff?->name ?? 'Finance Staff' }}</strong> &bull; 
                                    @if($isVatOnly)
                                        VAT to Pay: <strong class="text-info fs-6">ETB {{ number_format($ps->vat_amount, 2) }}</strong> &bull;
                                    @elseif($isWhtOnly)
                                        3% WHT to Pay: <strong class="text-danger fs-6">ETB {{ number_format($ps->withholding_amount, 2) }}</strong> &bull;
                                    @else
                                        Total Tax to Pay: <strong class="text-danger fs-6">ETB {{ number_format($ps->total_tax_paid, 2) }}</strong> 
                                        <span class="text-muted">(VAT: ETB {{ number_format($ps->vat_amount, 2) }} | 3% WHT: ETB {{ number_format($ps->withholding_amount, 2) }})</span> &bull; 
                                    @endif
                                    Covering <strong>{{ $ps->records_count }}</strong> tax deduction records.
                                </div>
                                <div class="text-muted small mt-1 d-flex align-items-center gap-1">
                                    <i class="fa-solid fa-circle-info text-primary"></i>
                                    <span>Awaiting Finance Staff to disburse tax payment to ERCA / Bank and upload official receipt slip.</span>
                                </div>
                            </div>
                        </div>
                        <div class="d-flex align-items-center gap-2 flex-wrap ms-lg-auto">
                            <button type="button" class="btn btn-success btn-sm rounded-pill px-3 py-1.5 shadow-sm fw-semibold" 
                                    data-bs-toggle="modal" data-bs-target="#recordPaymentModal{{ $ps->id }}">
                                <i class="fa-solid fa-file-circle-check me-1"></i> Record Payment &amp; Upload Slip
                            </button>
                            <form method="POST" action="{{ route('finance.tax-deductions.settle.cancel', $ps) }}" onsubmit="return confirm('Cancel this tax remittance request and return records to active pool?');" class="d-inline">
                                @csrf
                                <button type="submit" class="btn btn-outline-danger btn-sm rounded-pill px-3 py-1.5">
                                    <i class="fa-solid fa-xmark me-1"></i> Cancel
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    @endif

    {{-- ── Main Header & Action Hub ───────────────────────────────────────────── --}}
    <div class="card border-0 shadow-sm rounded-4 mb-4 bg-white overflow-hidden">
        <div class="card-body p-3 p-md-4">
            <div class="d-flex flex-column flex-xl-row justify-content-between align-items-start align-items-xl-center gap-3">
                {{-- Title & Subtitle --}}
                <div class="d-flex align-items-center gap-3">
                    <div class="p-3 rounded-4 bg-primary bg-opacity-10 text-primary fs-3 flex-shrink-0">
                        <i class="fa-solid fa-scale-balanced"></i>
                    </div>
                    <div>
                        <div class="d-flex align-items-center gap-2 flex-wrap">
                            <h1 class="h4 fw-bold text-dark mb-0">VAT &amp; Withholding Tax Deductions Ledger</h1>
                            <span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill px-2.5 py-1 small fw-semibold">
                                <i class="fa-solid fa-shield-halved me-1"></i>ERCA Compliance
                            </span>
                        </div>
                        <p class="text-muted small mb-0 mt-1">የቫት እና የ3% ቅድመ ግብር ተቀናሾች መከታተያ፣ ክፍያ እና የሂሳብ ሪፖርት ማጠቃለያ</p>
                    </div>
                </div>

                {{-- Action Button Groups --}}
                <div class="d-flex align-items-center gap-2 flex-wrap w-100 w-xl-auto justify-content-xl-end">
                    {{-- Primary Tax Settlement Actions --}}
                    <div class="btn-group shadow-sm rounded-pill overflow-hidden" role="group">
                        {{-- Pay 15% VAT --}}
                        <button type="button" class="btn btn-info text-white btn-sm px-3 py-2 fw-semibold d-inline-flex align-items-center gap-1.5" data-bs-toggle="modal" data-bs-target="#payVatModal">
                            <i class="fa-solid fa-percent"></i>
                            <span>Pay 15% VAT</span>
                            <span class="badge bg-white text-info rounded-pill ms-1 font-monospace">
                                ETB {{ number_format($unsettledVatAmount, 2) }}
                            </span>
                        </button>

                        {{-- Pay 3% Withholding Tax --}}
                        <button type="button" class="btn btn-danger btn-sm px-3 py-2 fw-semibold d-inline-flex align-items-center gap-1.5" data-bs-toggle="modal" data-bs-target="#payWithholdingModal">
                            <i class="fa-solid fa-hand-holding-dollar"></i>
                            <span>Pay 3% WHT</span>
                            <span class="badge bg-white text-danger rounded-pill ms-1 font-monospace">
                                ETB {{ number_format($unsettledWhtAmount, 2) }}
                            </span>
                        </button>
                    </div>

                    {{-- Utilities Group --}}
                    <div class="d-flex align-items-center gap-2">
                        <button type="button" class="btn btn-outline-success btn-sm rounded-pill px-3 py-2 shadow-xs fw-semibold d-inline-flex align-items-center gap-1.5" onclick="openDateExportModal('{{ $tab }}', 'csv')" title="Select Date Range & Export">
                            <i class="fa-solid fa-file-csv"></i>
                            <span class="d-none d-sm-inline">Export CSV</span>
                        </button>
                        <button type="button" onclick="window.print()" class="btn btn-outline-secondary btn-sm rounded-pill px-3 py-2 shadow-xs fw-semibold d-inline-flex align-items-center gap-1.5">
                            <i class="fa-solid fa-print"></i>
                            <span class="d-none d-sm-inline">Print</span>
                        </button>
                        <a href="{{ route('expenses.index') }}" class="btn btn-outline-primary btn-sm rounded-pill px-3 py-2 shadow-xs fw-semibold d-inline-flex align-items-center gap-1.5">
                            <i class="fa-solid fa-arrow-left"></i>
                            <span class="d-none d-sm-inline">Approvals Hub</span>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ── Cycle Switcher Navigation Bar ───────────────────────────────────────── --}}
    <div class="card border-0 shadow-sm rounded-4 mb-4 bg-white overflow-hidden">
        <div class="card-body p-2 p-md-3">
            <div class="d-flex flex-column flex-lg-row align-items-start align-items-lg-center justify-content-between gap-2">
                <div class="d-flex align-items-center gap-1.5 flex-wrap">
                    <span class="small fw-bold text-muted text-uppercase me-2 d-none d-md-inline-block ps-2">
                        <i class="fa-solid fa-layer-group text-primary me-1"></i> Cycle:
                    </span>

                    {{-- Current Active Period --}}
                    <a href="{{ route('finance.tax-deductions.index', array_merge(request()->except('page', 'settlements_page'), ['cycle' => 'active', 'tab' => $tab === 'settlements' ? 'all' : $tab])) }}" 
                       class="btn btn-sm rounded-pill px-3 py-1.5 fw-semibold d-inline-flex align-items-center gap-1.5 {{ $cycle === 'active' && $tab !== 'settlements' ? 'btn-primary shadow-xs' : 'btn-light text-secondary border-0' }}">
                        <i class="fa-solid fa-bolt text-warning"></i>
                        <span>Current Active Period</span>
                        @if($unsettledCount > 0)
                            <span class="badge {{ $cycle === 'active' && $tab !== 'settlements' ? 'bg-white text-primary' : 'bg-warning-subtle text-dark' }} rounded-pill ms-1">{{ $unsettledCount }}</span>
                        @else
                            <span class="badge bg-success text-white rounded-pill ms-1">Clean (0)</span>
                        @endif
                    </a>

                    {{-- All-Time Ledger --}}
                    <a href="{{ route('finance.tax-deductions.index', array_merge(request()->except('page', 'settlements_page'), ['cycle' => 'all', 'tab' => $tab === 'settlements' ? 'all' : $tab])) }}" 
                       class="btn btn-sm rounded-pill px-3 py-1.5 fw-semibold d-inline-flex align-items-center gap-1.5 {{ $cycle === 'all' && $tab !== 'settlements' ? 'btn-primary shadow-xs' : 'btn-light text-secondary border-0' }}">
                        <i class="fa-solid fa-book-bookmark"></i>
                        <span>All-Time Full Ledger</span>
                    </a>

                    {{-- Past Settled Records --}}
                    <a href="{{ route('finance.tax-deductions.index', array_merge(request()->except('page', 'settlements_page'), ['cycle' => 'settled', 'tab' => $tab === 'settlements' ? 'all' : $tab])) }}" 
                       class="btn btn-sm rounded-pill px-3 py-1.5 fw-semibold d-inline-flex align-items-center gap-1.5 {{ $cycle === 'settled' && $tab !== 'settlements' ? 'btn-primary shadow-xs' : 'btn-light text-secondary border-0' }}">
                        <i class="fa-solid fa-circle-check text-success"></i>
                        <span>Past Settled (ERCA)</span>
                    </a>

                    {{-- Tax Payment Settlements --}}
                    <a href="{{ route('finance.tax-deductions.index', array_merge(request()->except('page', 'settlements_page'), ['tab' => 'settlements'])) }}" 
                       class="btn btn-sm rounded-pill px-3 py-1.5 fw-semibold d-inline-flex align-items-center gap-1.5 {{ $tab === 'settlements' ? 'btn-danger shadow-xs text-white' : 'btn-light text-danger border-0' }}">
                        <i class="fa-solid fa-file-invoice-dollar"></i>
                        <span>Tax Remittance Batches</span>
                        <span class="badge {{ $tab === 'settlements' ? 'bg-white text-danger' : 'bg-danger-subtle text-danger' }} rounded-pill ms-1">
                            {{ $settlements->total() }}
                        </span>
                    </a>
                </div>

                {{-- Status Indicator Pill --}}
                <div class="pe-2 mt-2 mt-lg-0">
                    @if($cycle === 'active')
                        <span class="badge bg-success-subtle text-success border border-success-subtle px-2.5 py-1.5 rounded-pill small fw-medium d-inline-flex align-items-center gap-1">
                            <i class="fa-solid fa-rotate-right"></i>
                            <span>Active Period: Accumulating Liabilities</span>
                        </span>
                    @elseif($cycle === 'settled')
                        <span class="badge bg-info-subtle text-info border border-info-subtle px-2.5 py-1.5 rounded-pill small fw-medium d-inline-flex align-items-center gap-1">
                            <i class="fa-solid fa-box-archive"></i>
                            <span>Archived: Paid to ERCA</span>
                        </span>
                    @else
                        <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle px-2.5 py-1.5 rounded-pill small fw-medium d-inline-flex align-items-center gap-1">
                            <i class="fa-solid fa-database"></i>
                            <span>All Historical Records</span>
                        </span>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- ── KPI Metric Summary Cards ───────────────────────────────────────────── --}}
    <div class="row g-3 mb-4">
        {{-- Card 1: Base Invoiced --}}
        <div class="col-12 col-sm-6 col-xl">
            <div class="card border-0 shadow-sm rounded-4 h-100 bg-white border-top border-4 border-primary">
                <div class="card-body p-3.5 d-flex flex-column justify-content-between">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <div>
                            <span class="text-muted small text-uppercase fw-bold d-block" style="font-size: 0.72rem; letter-spacing: 0.5px;">Base Invoiced</span>
                            <span class="text-muted" style="font-size: 0.7rem;">ጠቅላላ የመነሻ ዋጋ</span>
                        </div>
                        <div class="p-2.5 bg-primary bg-opacity-10 text-primary rounded-3 fs-5">
                            <i class="fa-solid fa-file-invoice"></i>
                        </div>
                    </div>
                    <div>
                        <div class="fs-4 fw-bold text-dark font-monospace mb-1">ETB {{ number_format($totalGrossBase, 2) }}</div>
                        <span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill small px-2 py-0.5" style="font-size: 0.68rem;">
                            Gross Purchases
                        </span>
                    </div>
                </div>
            </div>
        </div>

        {{-- Card 2: Total VAT (Led by OCR) --}}
        <div class="col-12 col-sm-6 col-xl">
            <div class="card border-0 shadow-sm rounded-4 h-100 bg-white border-top border-4 border-info">
                <div class="card-body p-3.5 d-flex flex-column justify-content-between">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <div>
                            <span class="text-muted small text-uppercase fw-bold d-block" style="font-size: 0.72rem; letter-spacing: 0.5px;">VAT 15% (Lead by OCR)</span>
                            <span class="text-muted" style="font-size: 0.7rem;">የቫት ግዢ መግለጫ (Line 100)</span>
                        </div>
                        <div class="p-2.5 bg-info bg-opacity-10 text-info rounded-3 fs-5">
                            <i class="fa-solid fa-camera-viewfinder"></i>
                        </div>
                    </div>
                    <div>
                        <div class="fs-4 fw-bold text-info font-monospace mb-1">
                            + ETB {{ number_format(($ocrStats['total_vat'] ?? 0) > 0 ? $ocrStats['total_vat'] : $totalVatAmount, 2) }}
                        </div>
                        <div class="d-flex justify-content-between align-items-center">
                            @if(($ocrStats['total_vat'] ?? 0) > 0)
                                <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill small px-2 py-0.5" style="font-size: 0.68rem;" title="AI OCR Extracted Invoices">
                                    <i class="fa-solid fa-check me-1"></i>{{ $ocrStats['total_items'] }} OCR Invoices
                                </span>
                            @else
                                <span class="badge bg-info-subtle text-info border border-info-subtle rounded-pill small px-2 py-0.5" style="font-size: 0.68rem;">
                                    Accrued VAT
                                </span>
                            @endif
                            <a href="{{ route('finance.tax-deductions.index', ['tab' => 'vat']) }}" class="text-info text-decoration-none fw-semibold small" style="font-size: 0.75rem;">
                                VAT OCR &rarr;
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Card 3: Total Withholding Tax --}}
        <div class="col-12 col-sm-6 col-xl">
            <div class="card border-0 shadow-sm rounded-4 h-100 bg-white border-top border-4 border-danger">
                <div class="card-body p-3.5 d-flex flex-column justify-content-between">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <div>
                            <span class="text-muted small text-uppercase fw-bold d-block" style="font-size: 0.72rem; letter-spacing: 0.5px;">Withholding Tax (3%)</span>
                            <span class="text-muted" style="font-size: 0.7rem;">የተቀነሰ 3% ቅድመ ግብር</span>
                        </div>
                        <div class="p-2.5 bg-danger bg-opacity-10 text-danger rounded-3 fs-5">
                            <i class="fa-solid fa-hand-holding-dollar"></i>
                        </div>
                    </div>
                    <div>
                        <div class="fs-4 fw-bold text-danger font-monospace mb-1">- ETB {{ number_format($totalWithholdingAmount, 2) }}</div>
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="badge bg-danger-subtle text-danger border border-danger-subtle rounded-pill small px-2 py-0.5" style="font-size: 0.68rem;">
                                Deducted WHT
                            </span>
                            <button type="button" class="btn btn-link text-danger p-0 text-decoration-none fw-semibold small" style="font-size: 0.75rem;" data-bs-toggle="modal" data-bs-target="#payWithholdingModal">
                                Pay WHT &rarr;
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Card 4: Net Disbursed --}}
        <div class="col-12 col-sm-6 col-xl">
            <div class="card border-0 shadow-sm rounded-4 h-100 bg-white border-top border-4 border-success">
                <div class="card-body p-3.5 d-flex flex-column justify-content-between">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <div>
                            <span class="text-muted small text-uppercase fw-bold d-block" style="font-size: 0.72rem; letter-spacing: 0.5px;">Net Disbursed</span>
                            <span class="text-muted" style="font-size: 0.7rem;">ጠቅላላ የተጣራ የተከፈለ</span>
                        </div>
                        <div class="p-2.5 bg-success bg-opacity-10 text-success rounded-3 fs-5">
                            <i class="fa-solid fa-circle-check"></i>
                        </div>
                    </div>
                    <div>
                        <div class="fs-4 fw-bold text-success font-monospace mb-1">ETB {{ number_format($totalNetDisbursed, 2) }}</div>
                        <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill small px-2 py-0.5" style="font-size: 0.68rem;">
                            Effective Payout
                        </span>
                    </div>
                </div>
            </div>
        </div>

        {{-- Card 5: Verified WHT Slips --}}
        <div class="col-12 col-sm-6 col-xl">
            <div class="card border-0 shadow-sm rounded-4 h-100 bg-white border-top border-4 border-warning">
                <div class="card-body p-3.5 d-flex flex-column justify-content-between">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <div>
                            <span class="text-muted small text-uppercase fw-bold d-block" style="font-size: 0.72rem; letter-spacing: 0.5px;">Verified WHT Slips</span>
                            <span class="text-muted" style="font-size: 0.7rem;">የተረጋገጡ ደረሰኞች</span>
                        </div>
                        <div class="p-2.5 bg-warning bg-opacity-10 text-warning rounded-3 fs-5">
                            <i class="fa-solid fa-paperclip"></i>
                        </div>
                    </div>
                    <div>
                        <div class="d-flex align-items-baseline gap-1.5 mb-1">
                            <span class="fs-4 fw-bold text-dark font-monospace">{{ $slipsAttachedCount }}</span>
                            <span class="text-muted small">/ {{ $totalWhtTransactions }}</span>
                            @if($totalWhtTransactions > 0 && $slipsAttachedCount === $totalWhtTransactions)
                                <span class="badge bg-success text-white rounded-pill ms-auto small" style="font-size: 0.65rem;">100%</span>
                            @elseif($missingSlipsCount > 0)
                                <span class="badge bg-danger-subtle text-danger rounded-pill ms-auto small" style="font-size: 0.65rem;">{{ $missingSlipsCount }} Missing</span>
                            @endif
                        </div>
                        <div class="progress" style="height: 5px;">
                            @php
                                $whtSlipPercent = $totalWhtTransactions > 0 ? min(100, round(($slipsAttachedCount / $totalWhtTransactions) * 100)) : 100;
                            @endphp
                            <div class="progress-bar bg-warning" role="progressbar" style="width: {{ $whtSlipPercent }}%"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @if($tab === 'settlements')
        {{-- ── Settlements History Table ───────────────────────────────────────── --}}
        <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4 bg-white">
            <div class="card-header bg-white border-bottom py-3 px-4 d-flex flex-column flex-md-row align-items-start align-items-md-center justify-content-between gap-3">
                <div class="d-flex align-items-center gap-2">
                    <div class="p-2 rounded-3 bg-danger bg-opacity-10 text-danger fs-5">
                        <i class="fa-solid fa-file-invoice-dollar"></i>
                    </div>
                    <div>
                        <h6 class="fw-bold mb-0 text-dark">Tax Remittance &amp; Payment History (የተከፈሉ ታክሶች ታሪክ)</h6>
                        <small class="text-muted">History of VAT &amp; Withholding Tax batches remitted to ERCA with proof of payment slips.</small>
                    </div>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <button type="button" class="btn btn-info text-white btn-sm rounded-pill px-3 py-1.5 shadow-xs fw-semibold" data-bs-toggle="modal" data-bs-target="#payVatModal">
                        <i class="fa-solid fa-percent me-1"></i> Remit VAT (15%)
                    </button>
                    <button type="button" class="btn btn-danger btn-sm rounded-pill px-3 py-1.5 shadow-xs fw-semibold" data-bs-toggle="modal" data-bs-target="#payWithholdingModal">
                        <i class="fa-solid fa-hand-holding-dollar me-1"></i> Remit 3% WHT
                    </button>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light-subtle text-muted text-uppercase fw-bold" style="font-size: 0.72rem; letter-spacing: 0.5px;">
                        <tr>
                            <th class="ps-4 py-3">Settlement #</th>
                            <th class="py-3">Date / Period</th>
                            <th class="py-3">Tax Type</th>
                            <th class="py-3 text-end">Base Amount</th>
                            <th class="py-3 text-end text-info">VAT Paid</th>
                            <th class="py-3 text-end text-danger">WHT Paid</th>
                            <th class="py-3 text-end text-dark">Total Remitted</th>
                            <th class="py-3">Assigned Staff</th>
                            <th class="py-3">Paying Account</th>
                            <th class="py-3 text-center">Receipt Slip</th>
                            <th class="py-3">Status</th>
                            <th class="py-3 text-center pe-4">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        @forelse($settlements as $st)
                            <tr>
                                <td class="ps-4">
                                    <div class="fw-bold font-monospace text-primary">{{ $st->settlement_number }}</div>
                                    <small class="text-muted">{{ $st->records_count }} records covered</small>
                                </td>
                                <td>
                                    <div class="text-dark small fw-medium">{{ optional($st->created_at)->format('d M Y, h:i A') }}</div>
                                    @if($st->paid_at)
                                        <small class="text-success"><i class="fa-solid fa-check me-1"></i>Paid: {{ $st->paid_at->format('d M Y') }}</small>
                                    @endif
                                </td>
                                <td>
                                    @if($st->tax_type === 'both')
                                        <span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill px-2.5 py-1">VAT (15%) + WHT (3%)</span>
                                    @elseif($st->tax_type === 'vat')
                                        <span class="badge bg-info-subtle text-info border border-info-subtle rounded-pill px-2.5 py-1">VAT (15%) Only</span>
                                    @else
                                        <span class="badge bg-danger-subtle text-danger border border-danger-subtle rounded-pill px-2.5 py-1">3% Withholding Only</span>
                                    @endif
                                </td>
                                <td class="text-end font-monospace small">ETB {{ number_format($st->total_base_amount, 2) }}</td>
                                <td class="text-end font-monospace text-info small">+ ETB {{ number_format($st->vat_amount, 2) }}</td>
                                <td class="text-end font-monospace text-danger small">- ETB {{ number_format($st->withholding_amount, 2) }}</td>
                                <td class="text-end font-monospace fw-bold text-dark">ETB {{ number_format($st->total_tax_paid, 2) }}</td>
                                <td>
                                    <div class="fw-semibold text-dark small">{{ $st->assignedStaff?->name ?? 'Unassigned' }}</div>
                                    @if($st->financeHead)
                                        <small class="text-muted" style="font-size: 0.72rem;">By: {{ $st->financeHead->name }}</small>
                                    @endif
                                </td>
                                <td>
                                    <div class="small fw-medium text-dark">{{ $st->chartOfAccount->name ?? ($st->bankAccount->bank_name ?? 'Bank/COA') }}</div>
                                    @if($st->payment_reference)
                                        <small class="text-muted font-monospace" style="font-size:0.7rem;">Ref: {{ $st->payment_reference }}</small>
                                    @endif
                                </td>
                                <td class="text-center">
                                    @if($st->attachment)
                                        <a href="{{ asset($st->attachment) }}" target="_blank" class="btn btn-xs btn-outline-success rounded-pill px-2.5 py-0.5 shadow-xs" style="font-size:0.75rem;">
                                            <i class="fa-solid fa-paperclip me-1"></i> Bank Receipt
                                        </a>
                                    @else
                                        <span class="badge bg-light text-muted border rounded-pill">No slip</span>
                                    @endif
                                </td>
                                <td>
                                    @if($st->status === 'paid')
                                        <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-2.5 py-1 small">
                                            <i class="fa-solid fa-check me-1"></i>Paid to ERCA
                                        </span>
                                    @elseif($st->status === 'pending_payment')
                                        <span class="badge bg-warning-subtle text-warning border border-warning-subtle rounded-pill px-2.5 py-1 small">
                                            <i class="fa-solid fa-clock me-1"></i>Pending Payment
                                        </span>
                                    @else
                                        <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle rounded-pill px-2.5 py-1 small">
                                            {{ ucfirst($st->status) }}
                                        </span>
                                    @endif
                                </td>
                                <td class="text-center pe-4">
                                    @if($st->status === 'pending_payment')
                                        <button type="button" class="btn btn-sm btn-success rounded-pill px-3 py-1 shadow-xs fw-medium" 
                                                data-bs-toggle="modal" data-bs-target="#recordPaymentModal{{ $st->id }}">
                                            <i class="fa-solid fa-file-invoice-dollar me-1"></i> Settle
                                        </button>
                                    @else
                                        <span class="text-muted small"><i class="fa-solid fa-circle-check text-success"></i> Settled</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="12" class="text-center py-5">
                                    <div class="text-muted mb-2"><i class="fa-solid fa-folder-open text-secondary fs-1"></i></div>
                                    <h6 class="fw-bold text-dark">No Tax Remittance Batches Recorded Yet</h6>
                                    <p class="text-muted small mb-3 mx-auto" style="max-width: 480px;">
                                        When you remit accrued taxes to ERCA, settlements will be logged here with complete bank deposit &amp; ERCA tax clearance proof slips.
                                    </p>
                                    <div class="d-flex justify-content-center gap-2 flex-wrap">
                                        <button type="button" class="btn btn-info text-white btn-sm rounded-pill px-3 py-1.5 shadow-sm fw-semibold" data-bs-toggle="modal" data-bs-target="#payVatModal">
                                            <i class="fa-solid fa-percent me-1"></i> Pay 15% VAT Now
                                        </button>
                                        <button type="button" class="btn btn-danger btn-sm rounded-pill px-3 py-1.5 shadow-sm fw-semibold" data-bs-toggle="modal" data-bs-target="#payWithholdingModal">
                                            <i class="fa-solid fa-hand-holding-dollar me-1"></i> Pay 3% Withholding Now
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($settlements->hasPages())
                <div class="card-footer bg-white border-top py-3 px-4">
                    {{ $settlements->links() }}
                </div>
            @endif
        </div>

    @else
        {{-- ── Active / Ledger View ─────────────────────────────────────────────── --}}
        <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4 bg-white">
            {{-- Secondary Sub-Tabs --}}
            <div class="card-header bg-white border-bottom py-3 px-4">
                <ul class="nav nav-pills card-header-pills gap-2 flex-wrap">
                    <li class="nav-item">
                        <a class="nav-link rounded-pill px-3 py-1.5 fw-semibold d-inline-flex align-items-center gap-1.5 {{ $tab === 'all' || $tab === 'paid' ? 'active bg-primary text-white shadow-sm' : 'text-secondary bg-light' }}" 
                           href="{{ route('finance.tax-deductions.index', array_merge(request()->query(), ['tab' => 'all'])) }}">
                            <i class="fa-solid fa-receipt"></i>
                            <span>All Tax Items</span>
                            <span class="badge {{ $tab === 'all' || $tab === 'paid' ? 'bg-white text-primary' : 'bg-secondary-subtle text-secondary' }} rounded-pill ms-1">{{ $totalRecords }}</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link rounded-pill px-3 py-1.5 fw-semibold d-inline-flex align-items-center gap-1.5 {{ $tab === 'withholding' ? 'active bg-danger text-white shadow-sm' : 'text-secondary bg-light' }}" 
                           href="{{ route('finance.tax-deductions.index', array_merge(request()->query(), ['tab' => 'withholding'])) }}">
                            <i class="fa-solid fa-scissors"></i>
                            <span>3% Withholding Tax</span>
                            <span class="badge {{ $tab === 'withholding' ? 'bg-white text-danger' : 'bg-secondary-subtle text-secondary' }} rounded-pill ms-1">{{ $totalWhtTransactions }}</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link rounded-pill px-3 py-1.5 fw-semibold d-inline-flex align-items-center gap-1.5 {{ $tab === 'vat' ? 'active bg-info text-white shadow-sm' : 'text-secondary bg-light' }}" 
                           href="{{ route('finance.tax-deductions.index', array_merge(request()->query(), ['tab' => 'vat'])) }}">
                            <i class="fa-solid fa-camera-viewfinder"></i>
                            <span>VAT (Lead by OCR)</span>
                            <span class="badge {{ $tab === 'vat' ? 'bg-white text-info' : 'bg-info-subtle text-info' }} rounded-pill ms-1">{{ $ocrStats['total_items'] ?? 0 }}</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link rounded-pill px-3 py-1.5 fw-semibold d-inline-flex align-items-center gap-1.5 {{ $tab === 'slips' ? 'active bg-success text-white shadow-sm' : 'text-secondary bg-light' }}" 
                           href="{{ route('finance.tax-deductions.index', array_merge(request()->query(), ['tab' => 'slips'])) }}">
                            <i class="fa-solid fa-file-circle-check"></i>
                            <span>WHT Slips Attached</span>
                            <span class="badge {{ $tab === 'slips' ? 'bg-white text-success' : 'bg-secondary-subtle text-secondary' }} rounded-pill ms-1">{{ $slipsAttachedCount }}</span>
                        </a>
                    </li>
                </ul>
            </div>

            @if($tab === 'vat')
                {{-- ── ERCA VAT PURCHASE DECLARATION (LINE 100) - LEAD BY OCR ─────────── --}}
                <div class="card-body p-3.5 bg-gradient bg-info bg-opacity-10 border-bottom">
                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
                        <div>
                            <div class="d-flex align-items-center gap-2 flex-wrap">
                                <h5 class="fw-bold text-dark mb-0">
                                    <i class="fa-solid fa-camera-viewfinder text-info me-2"></i>ERCA VAT Purchase Declaration (Line 100)
                                </h5>
                                <span class="badge bg-info text-white px-2.5 py-1 rounded-pill small">
                                    <i class="fa-solid fa-microchip me-1"></i>Led by AI OCR
                                </span>
                                <span class="badge bg-success-subtle text-success border border-success-subtle px-2.5 py-1 rounded-pill small">
                                    Official eTax Format
                                </span>
                            </div>
                            <p class="text-muted small mb-0 mt-1">
                                Physical receipt slips and fiscal invoices extracted via multi-engine AI Vision OCR. Automatically validates Supplier TIN (Col D), FS / MRC Numbers (Col G, H), and calculates 15% VAT (Col N) for official ERCA declaration.
                            </p>
                        </div>
                        <div class="d-flex align-items-center gap-2 flex-wrap">
                            <button type="button" class="btn btn-sm btn-success fw-bold shadow-xs" onclick="openDateExportModal('vat', 'excel')" title="Export by Date">
                                <i class="fa-solid fa-file-excel me-1"></i>Export Excel (.xlsx)
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-success fw-bold shadow-xs" onclick="openDateExportModal('vat', 'csv')" title="Export by Date">
                                <i class="fa-solid fa-file-csv me-1"></i>Export CSV
                            </button>
                            <a href="{{ route('admin.ocr.index') }}" target="_blank" class="btn btn-sm btn-primary fw-bold shadow-xs" title="Open full studio with AI settings, chat QC agent, and raw text viewer">
                                <i class="fa-solid fa-up-right-from-square me-1"></i>Full OCR Studio
                            </a>
                            <button type="button" class="btn btn-sm btn-info text-white fw-bold shadow-xs" data-bs-toggle="modal" data-bs-target="#payVatModal">
                                <i class="fa-solid fa-credit-card me-1"></i>Pay VAT
                            </button>
                        </div>
                    </div>
                </div>

                {{-- Live OCR Scanner & Dropzone --}}
                <div class="card-body p-3 p-md-4 bg-light border-bottom">
                    <div class="row g-3 align-items-stretch">
                        {{-- Dropzone Left --}}
                        <div class="col-lg-7">
                            <div id="vat-drop-zone" class="border border-2 border-dashed rounded-3 p-4 text-center bg-white shadow-xs position-relative h-100 d-flex flex-column justify-content-center align-items-center transition-all" style="border-color: #0dcaf0 !important; min-height: 200px;">
                                <input type="file" id="vat-file-input" accept="image/jpeg,image/png,image/webp,application/pdf" class="d-none">
                                <input type="file" id="vat-camera-input" accept="image/*" capture="environment" class="d-none">

                                <div id="vat-drop-prompt" class="py-2 cursor-pointer" onclick="if(event.target.tagName !== 'BUTTON' && event.target.tagName !== 'INPUT' && !event.target.closest('label')) document.getElementById('vat-file-input').click()">
                                    <div class="rounded-circle bg-info bg-opacity-10 text-info p-3 d-inline-flex mb-2">
                                        <i class="fa-solid fa-cloud-arrow-up fa-2x"></i>
                                    </div>
                                    <h6 class="fw-bold text-dark mb-1">Drag &amp; Drop Fiscal Receipt or PDF Here</h6>
                                    <p class="text-muted small mb-3">Instant AI extraction into ERCA VAT Line 100 Declaration</p>
                                    <div class="d-flex justify-content-center gap-2 flex-wrap">
                                        <button type="button" class="btn btn-sm btn-info text-white px-3 fw-semibold shadow-xs" onclick="event.stopPropagation(); document.getElementById('vat-file-input').click()">
                                            <i class="fa-solid fa-folder-open me-1"></i>Browse Receipt File
                                        </button>
                                        <button type="button" class="btn btn-sm btn-outline-dark px-3 fw-semibold shadow-xs" onclick="event.stopPropagation(); document.getElementById('vat-camera-input').click()">
                                            <i class="fa-solid fa-camera me-1"></i>Snap Photo (Camera)
                                        </button>
                                    </div>
                                </div>

                                {{-- Progress indicator --}}
                                <div id="vat-scan-progress" class="w-100 d-none mt-2">
                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                        <small class="fw-bold text-dark" id="vat-scan-status-text">
                                            <i class="fa-solid fa-spinner fa-spin me-1 text-info"></i>Scanning receipt with AI Vision...
                                        </small>
                                        <small class="fw-bold text-info font-monospace" id="vat-scan-pct">0%</small>
                                    </div>
                                    <div class="progress" style="height: 8px;">
                                        <div id="vat-scan-bar" class="progress-bar progress-bar-striped progress-bar-animated bg-info" style="width: 0%"></div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- Optional Settings / Meta Right --}}
                        <div class="col-lg-5">
                            <div class="p-3 bg-white rounded-3 border h-100 d-flex flex-column justify-content-between shadow-xs">
                                <div>
                                    <div class="d-flex align-items-center justify-content-between mb-2">
                                        <span class="small fw-bold text-uppercase text-muted" style="font-size:0.75rem;">Scan Options &amp; Project Tag</span>
                                        <span class="badge bg-primary-subtle text-primary border border-primary-subtle" style="font-size:0.7rem;">
                                            <i class="fa-solid fa-bolt me-1"></i>Auto-Saves to ERCA Table
                                        </span>
                                    </div>
                                    <div class="mb-2">
                                        <label class="form-label small fw-semibold text-dark mb-1">Tag Project (Optional)</label>
                                        <select id="vat-project-select" class="form-select form-select-sm">
                                            <option value="">-- No Project (General Company Expense) --</option>
                                            @foreach($ocrProjects as $proj)
                                                <option value="{{ $proj->id }}">{{ $proj->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label small fw-semibold text-dark mb-1">Category (Optional)</label>
                                        <select id="vat-category-select" class="form-select form-select-sm">
                                            @foreach($ocrCategories as $key => $label)
                                                <option value="{{ $key }}" {{ $key === 'material' ? 'selected' : '' }}>{{ $label }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>

                                {{-- Alert Result Box --}}
                                <div id="vat-scan-result-alert" class="alert alert-light border small py-2 mb-0 d-flex align-items-center gap-2">
                                    <i class="fa-solid fa-circle-info text-info"></i>
                                    <span id="vat-scan-result-msg" class="text-muted">Upload a receipt or snap a photo. Once extracted, the 15-column ERCA record will be automatically recorded below.</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Sub-toggle Pills: OCR Scanned Invoices vs Internal Expense Requests --}}
                <div class="card-body p-3 bg-white border-bottom d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <ul class="nav nav-pills gap-2 flex-wrap mb-0">
                        <li class="nav-item">
                            <a class="nav-link rounded-pill px-3 py-1.5 fw-semibold d-inline-flex align-items-center gap-1.5 {{ ($vatSource ?? 'ocr') !== 'expense' ? 'active bg-info text-white shadow-sm' : 'text-secondary bg-light' }}" 
                               href="{{ route('finance.tax-deductions.index', array_merge(request()->query(), ['tab' => 'vat', 'vat_source' => 'ocr'])) }}">
                                <i class="fa-solid fa-camera-viewfinder"></i>
                                <span>OCR Scanned VAT Invoices (ERCA Line 100)</span>
                                <span class="badge {{ ($vatSource ?? 'ocr') !== 'expense' ? 'bg-white text-info' : 'bg-info-subtle text-info' }} rounded-pill ms-1">{{ $ocrStats['total_items'] ?? 0 }}</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link rounded-pill px-3 py-1.5 fw-semibold d-inline-flex align-items-center gap-1.5 {{ ($vatSource ?? 'ocr') === 'expense' ? 'active bg-info text-white shadow-sm' : 'text-secondary bg-light' }}" 
                               href="{{ route('finance.tax-deductions.index', array_merge(request()->query(), ['tab' => 'vat', 'vat_source' => 'expense'])) }}">
                                <i class="fa-solid fa-file-invoice-dollar"></i>
                                <span>Internal Expense Requests with VAT</span>
                                <span class="badge {{ ($vatSource ?? 'ocr') === 'expense' ? 'bg-white text-info' : 'bg-secondary-subtle text-secondary' }} rounded-pill ms-1">{{ $totalRecords }}</span>
                            </a>
                        </li>
                    </ul>
                    @if(($vatSource ?? 'ocr') !== 'expense')
                        <div class="text-muted small">
                            <i class="fa-solid fa-info-circle text-primary me-1"></i>Showing <strong>{{ $ocrItems->total() }}</strong> extracted fiscal line items
                        </div>
                    @endif
                </div>
            @endif

            @if($tab === 'vat' && ($vatSource ?? 'ocr') !== 'expense')
                {{-- ── OCR Filter Bar ────────────────────────────────────────── --}}
                <div class="card-body p-3 p-md-4 bg-light bg-opacity-50 border-bottom">
                    <form method="GET" action="{{ route('finance.tax-deductions.index') }}" class="row g-2 align-items-center">
                        <input type="hidden" name="tab" value="vat">
                        <input type="hidden" name="vat_source" value="ocr">
                        <input type="hidden" name="cycle" value="{{ $cycle }}">

                        <div class="col-12 col-md-4">
                            <div class="input-group">
                                <span class="input-group-text bg-white border-end-0 text-muted"><i class="fa-solid fa-magnifying-glass"></i></span>
                                <input type="text" name="search" class="form-control border-start-0" 
                                       placeholder="Search FS #, MRC #, Supplier TIN, Supplier Name, Item..." value="{{ request('search') }}">
                            </div>
                        </div>

                        <div class="col-6 col-md-3">
                            <input type="date" name="from_date" class="form-control" value="{{ request('from_date') }}" title="From Date" placeholder="From Date">
                        </div>

                        <div class="col-6 col-md-3">
                            <input type="date" name="to_date" class="form-control" value="{{ request('to_date') }}" title="To Date" placeholder="To Date">
                        </div>

                        <div class="col-12 col-md-2 d-flex gap-1.5">
                            <button type="submit" class="btn btn-primary flex-fill" title="Apply Filter">
                                <i class="fa-solid fa-filter me-1"></i>Filter
                            </button>
                            @if(request('search') || request('from_date') || request('to_date'))
                                <a href="{{ route('finance.tax-deductions.index', ['tab' => 'vat', 'vat_source' => 'ocr', 'cycle' => $cycle]) }}" class="btn btn-outline-danger" title="Clear Filters">
                                    <i class="fa-solid fa-xmark"></i>
                                </a>
                            @endif
                        </div>
                    </form>
                </div>

                {{-- ERCA VAT Invoices Table (15-Column Declaration) --}}
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" id="vat-ocr-table">
                        <thead class="bg-light-subtle text-muted text-uppercase fw-bold" style="font-size: 0.72rem; letter-spacing: 0.5px;">
                            <tr>
                                <th class="ps-4 py-3" style="width: 50px;">#</th>
                                <th class="py-3">Supplier TIN <span class="badge bg-secondary-subtle text-dark border ms-1">Col D</span></th>
                                <th class="py-3">Supplier Name <span class="badge bg-secondary-subtle text-dark border ms-1">Col E</span></th>
                                <th class="py-3">Invoice Date <span class="badge bg-secondary-subtle text-dark border ms-1">Col F</span></th>
                                <th class="py-3">Machine (MRC) # <span class="badge bg-secondary-subtle text-dark border ms-1">Col G</span></th>
                                <th class="py-3">FS / Receipt # <span class="badge bg-secondary-subtle text-dark border ms-1">Col H</span></th>
                                <th class="py-3">Item Description <span class="badge bg-secondary-subtle text-dark border ms-1">Col I</span></th>
                                <th class="py-3 text-end">Base Value <span class="badge bg-secondary-subtle text-dark border ms-1">Col M</span></th>
                                <th class="py-3 text-end text-info">VAT (15%) <span class="badge bg-info-subtle text-info border ms-1">Col N</span></th>
                                <th class="py-3 text-end text-success">Total with VAT <span class="badge bg-success-subtle text-success border ms-1">Col O</span></th>
                                <th class="py-3 text-center">Slip</th>
                                <th class="py-3 text-center pe-4">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            @forelse($ocrItems as $index => $ocrItem)
                                @php
                                    $rcp = $ocrItem->receipt;
                                    $tin = $rcp->vendor_tin ?? '';
                                    $isTinValid = (bool)($rcp->tin_valid ?? (strlen(preg_replace('/\D/', '', $tin)) === 10));
                                    $fsNo = $rcp->fs_no ?? '';
                                    $isFsValid = (bool)($rcp->fs_no_valid ?? (strlen(preg_replace('/\D/', '', $fsNo)) === 8));
                                    $slipUrl = $rcp && $rcp->file_path ? asset('storage/' . $rcp->file_path) : null;
                                @endphp
                                <tr id="ocr-row-{{ $ocrItem->id }}">
                                    <td class="ps-4 text-muted small">{{ $ocrItems->firstItem() + $index }}</td>
                                    
                                    {{-- Supplier TIN (Col D) --}}
                                    <td>
                                        <div class="d-flex align-items-center gap-1.5">
                                            <span class="font-monospace fw-bold {{ $isTinValid ? 'text-dark' : 'text-danger' }}">
                                                {{ $tin ?: 'N/A' }}
                                            </span>
                                            @if($tin)
                                                @if($isTinValid)
                                                    <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-1.5 py-0.5" style="font-size:0.65rem;" title="Valid 10-Digit ERCA TIN">
                                                        <i class="fa-solid fa-check"></i>
                                                    </span>
                                                @else
                                                    <span class="badge bg-danger-subtle text-danger border border-danger-subtle rounded-pill px-1.5 py-0.5" style="font-size:0.65rem;" title="Invalid TIN Length">
                                                        <i class="fa-solid fa-triangle-exclamation"></i>
                                                    </span>
                                                @endif
                                            @endif
                                        </div>
                                    </td>

                                    {{-- Supplier Name (Col E) --}}
                                    <td>
                                        <div class="fw-semibold text-dark small">{{ $rcp->vendor_name ?? 'Unknown Vendor' }}</div>
                                        @if($rcp && $rcp->project)
                                            <span class="badge bg-light text-secondary border fw-normal mt-0.5" style="font-size: 0.68rem;">
                                                <i class="fa-solid fa-building me-1"></i>{{ $rcp->project->name }}
                                            </span>
                                        @endif
                                    </td>

                                    {{-- Receipt Date (Col F) --}}
                                    <td class="small text-muted font-monospace">
                                        {{ $rcp && $rcp->receipt_date ? $rcp->receipt_date->format('d/m/Y') : '—' }}
                                    </td>

                                    {{-- MRC # (Col G) --}}
                                    <td class="small font-monospace text-secondary">
                                        {{ $rcp->mrc_no ?: '—' }}
                                    </td>

                                    {{-- FS / Receipt # (Col H) --}}
                                    <td>
                                        <div class="d-flex align-items-center gap-1.5">
                                            <span class="font-monospace fw-bold text-primary">{{ $fsNo ?: '—' }}</span>
                                            @if($fsNo)
                                                @if($isFsValid)
                                                    <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-1.5 py-0.5" style="font-size:0.65rem;" title="Valid 8-Digit FS #">
                                                        <i class="fa-solid fa-check"></i>
                                                    </span>
                                                @else
                                                    <span class="badge bg-warning-subtle text-warning border border-warning-subtle rounded-pill px-1.5 py-0.5" style="font-size:0.65rem;" title="Check FS # format">
                                                        !
                                                    </span>
                                                @endif
                                            @endif
                                        </div>
                                    </td>

                                    {{-- Item Description (Col I) --}}
                                    <td>
                                        <div class="small text-dark fw-medium">{{ $ocrItem->item_description ?: 'General Purchase' }}</div>
                                        @if($rcp && $rcp->category)
                                            <span class="badge bg-light text-muted border" style="font-size: 0.65rem;">
                                                {{ ucfirst($rcp->category) }}
                                            </span>
                                        @endif
                                    </td>

                                    {{-- Taxable Base (Col M) --}}
                                    <td class="text-end font-monospace fw-semibold small">
                                        ETB {{ number_format((float)$ocrItem->total_value, 2) }}
                                    </td>

                                    {{-- 15% VAT (Col N) --}}
                                    <td class="text-end font-monospace fw-bold text-info small">
                                        + ETB {{ number_format((float)$ocrItem->vat_amount, 2) }}
                                    </td>

                                    {{-- Total with VAT (Col O) --}}
                                    <td class="text-end font-monospace fw-bold text-success small">
                                        ETB {{ number_format((float)$ocrItem->value_after_vat, 2) }}
                                    </td>

                                    {{-- Slip Preview Thumbnail --}}
                                    <td class="text-center">
                                        @if($slipUrl)
                                            <button type="button" class="btn btn-xs btn-outline-info rounded-pill px-2 py-0.5 shadow-xs" 
                                                    onclick="openVatSlipModal('{{ $slipUrl }}', '{{ addslashes($rcp->vendor_name ?? '') }}', '{{ $tin }}', '{{ $fsNo }}', '{{ $rcp->mrc_no ?? '' }}', '{{ optional($rcp->receipt_date)->format('d M Y') }}', '{{ number_format((float)$ocrItem->total_value, 2) }}', '{{ number_format((float)$ocrItem->vat_amount, 2) }}', '{{ number_format((float)$ocrItem->value_after_vat, 2) }}', '{{ route('admin.ocr.index') }}')"
                                                    title="View High-Res Receipt Slip">
                                                <i class="fa-solid fa-image me-1"></i>Slip
                                            </button>
                                        @else
                                            <span class="text-muted small">—</span>
                                        @endif
                                    </td>

                                    {{-- Actions --}}
                                    <td class="text-center pe-4">
                                        <div class="d-flex justify-content-center gap-1">
                                            @if($slipUrl)
                                                <button type="button" class="btn btn-outline-secondary btn-sm rounded-pill px-2 py-0.5" 
                                                        onclick="openVatSlipModal('{{ $slipUrl }}', '{{ addslashes($rcp->vendor_name ?? '') }}', '{{ $tin }}', '{{ $fsNo }}', '{{ $rcp->mrc_no ?? '' }}', '{{ optional($rcp->receipt_date)->format('d M Y') }}', '{{ number_format((float)$ocrItem->total_value, 2) }}', '{{ number_format((float)$ocrItem->vat_amount, 2) }}', '{{ number_format((float)$ocrItem->value_after_vat, 2) }}', '{{ route('admin.ocr.index') }}')"
                                                        title="View Details">
                                                    <i class="fa-solid fa-eye"></i>
                                                </button>
                                            @endif
                                            <button type="button" class="btn btn-outline-danger btn-sm rounded-pill px-2 py-0.5" 
                                                    onclick="deleteOcrItem({{ $ocrItem->id }}, '{{ addslashes($fsNo ?: 'Row #' . ($index + 1)) }}')"
                                                    title="Delete Row">
                                                <i class="fa-solid fa-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="12" class="text-center py-5">
                                        <div class="py-4">
                                            <div class="p-3 bg-info bg-opacity-10 text-info rounded-circle d-inline-block fs-1 mb-3">
                                                <i class="fa-solid fa-camera-viewfinder"></i>
                                            </div>
                                            <h5 class="fw-bold text-dark">No OCR Extracted VAT Receipts Found</h5>
                                            <p class="text-muted small mx-auto" style="max-width: 480px;">
                                                Drag and drop receipts or snap photos in the scanner box above. Multi-engine AI Vision will instantly populate the ERCA VAT Line 100 purchase declaration here.
                                            </p>
                                            <button type="button" class="btn btn-info text-white btn-sm rounded-pill px-3 py-1.5 shadow-xs" onclick="document.getElementById('vat-file-input').click()">
                                                <i class="fa-solid fa-cloud-arrow-up me-1"></i>Scan First Receipt
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                {{-- Pagination for OCR items --}}
                @if($ocrItems->hasPages())
                    <div class="card-footer bg-white border-top py-3 px-4">
                        {{ $ocrItems->links() }}
                    </div>
                @endif

            @else
                {{-- ── STANDARD / EXPENSE REQUESTS TABLE VIEW ──────────────────────── --}}
                {{-- Filter Bar --}}
                <div class="card-body p-3 p-md-4 bg-light bg-opacity-50 border-bottom">
                    <form method="GET" action="{{ route('finance.tax-deductions.index') }}" class="row g-2 align-items-center">
                        <input type="hidden" name="tab" value="{{ $tab }}">
                        @if($tab === 'vat')
                            <input type="hidden" name="vat_source" value="expense">
                        @endif
                        <input type="hidden" name="cycle" value="{{ $cycle }}">

                        <div class="col-12 col-md-3">
                            <div class="input-group">
                                <span class="input-group-text bg-white border-end-0 text-muted"><i class="fa-solid fa-magnifying-glass"></i></span>
                                <input type="text" name="search" class="form-control border-start-0" 
                                       placeholder="Search Ref, Voucher, Requester..." value="{{ request('search') }}">
                            </div>
                        </div>

                        <div class="col-6 col-md-2">
                            <select name="category" class="form-select" onchange="this.form.submit()">
                                <option value="">All Categories</option>
                                <option value="Service" {{ request('category') === 'Service' ? 'selected' : '' }}>🤝 Service (አገልግሎት)</option>
                                <option value="Contract Work" {{ request('category') === 'Contract Work' ? 'selected' : '' }}>📝 Contract Work (የኮንትራት)</option>
                                <option value="Transport" {{ request('category') === 'Transport' ? 'selected' : '' }}>🚚 Transport (ትራንስፖርት)</option>
                                <option value="Loading & Unloading" {{ request('category') === 'Loading & Unloading' ? 'selected' : '' }}>📦 Loading &amp; Unloading</option>
                                <option value="Maintenance" {{ request('category') === 'Maintenance' ? 'selected' : '' }}>🔧 Maintenance</option>
                                <option value="Other" {{ request('category') === 'Other' ? 'selected' : '' }}>✨ Other</option>
                            </select>
                        </div>

                        <div class="col-6 col-md-2">
                            <select name="vat_type" class="form-select" onchange="this.form.submit()">
                                <option value="">All VAT Types</option>
                                <option value="exclusive" {{ request('vat_type') === 'exclusive' ? 'selected' : '' }}>15% Added (+15% ቫት)</option>
                                <option value="vat_b" {{ request('vat_type') === 'vat_b' ? 'selected' : '' }}>15% Included (VAT B)</option>
                                <option value="none" {{ request('vat_type') === 'none' ? 'selected' : '' }}>No VAT (0%)</option>
                            </select>
                        </div>

                        <div class="col-6 col-md-2">
                            <input type="date" name="from_date" class="form-control" value="{{ request('from_date') }}" title="From Date" placeholder="From Date">
                        </div>

                        <div class="col-6 col-md-2">
                            <input type="date" name="to_date" class="form-control" value="{{ request('to_date') }}" title="To Date" placeholder="To Date">
                        </div>

                        <div class="col-12 col-md-1 d-flex gap-1.5">
                            <button type="submit" class="btn btn-primary flex-fill" title="Apply Filter">
                                <i class="fa-solid fa-filter"></i>
                            </button>
                            @if(request('search') || request('category') || request('vat_type') || request('from_date') || request('to_date'))
                                <a href="{{ route('finance.tax-deductions.index', ['tab' => $tab, 'vat_source' => request('vat_source'), 'cycle' => $cycle]) }}" class="btn btn-outline-danger" title="Clear Filters">
                                    <i class="fa-solid fa-xmark"></i>
                                </a>
                            @endif
                        </div>
                    </form>
                </div>

                {{-- Tax Deductions Table --}}
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="bg-light-subtle text-muted text-uppercase fw-bold" style="font-size: 0.72rem; letter-spacing: 0.5px;">
                            <tr>
                                <th class="ps-4 py-3">Voucher / Ref #</th>
                                <th class="py-3">Requester / Project</th>
                                <th class="py-3">Category</th>
                                <th class="py-3 text-end">Base Invoiced</th>
                                <th class="py-3 text-end">VAT (15%)</th>
                                <th class="py-3 text-end text-danger">3% Withholding</th>
                                <th class="py-3 text-end text-success">Net Paid</th>
                                <th class="py-3">Paying Account</th>
                                <th class="py-3 text-center">WHT Slip</th>
                                <th class="py-3">Cycle Status</th>
                                <th class="py-3 text-center pe-4">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            @forelse($records as $item)
                                @php
                                    $gross = (float)($item->gross_amount > 0 ? $item->gross_amount : $item->amount);
                                    $wht = (float)$item->calculated_withholding_amount;
                                    $net = (float)$item->effective_payable_amount;
                                    $hasWht = (bool)($item->has_withholding || $item->withholding_amount > 0 || $wht > 0);
                                    $isSettled = (bool)($item->vat_settled || $item->withholding_settled);
                                @endphp
                                <tr>
                                    {{-- Ref / Voucher # --}}
                                    <td class="ps-4">
                                        <div class="fw-bold font-monospace text-primary">{{ $item->request_number }}</div>
                                        <div class="small text-muted">{{ optional($item->created_at)->format('d M Y, h:i A') }}</div>
                                        @if($item->payment_reference)
                                            <span class="badge bg-light text-dark border font-monospace mt-1" style="font-size: 0.68rem;">
                                                Ref: {{ $item->payment_reference }}
                                            </span>
                                        @endif
                                    </td>

                                    {{-- Requester / Project --}}
                                    <td>
                                        <div class="fw-semibold text-dark small">{{ $item->user->name ?? ($item->employee->full_name ?? 'N/A') }}</div>
                                        @if($item->project_id && $item->project)
                                            <span class="badge bg-light text-secondary border fw-normal mt-0.5" style="font-size: 0.68rem;">
                                                <i class="fa-solid fa-building me-1"></i>{{ $item->project->name }}
                                            </span>
                                        @elseif($item->purchaseRequest)
                                            <span class="badge bg-light text-secondary border fw-normal mt-0.5" style="font-size: 0.68rem;">
                                                <i class="fa-solid fa-cart-shopping me-1"></i>PR #{{ $item->purchaseRequest->pr_number ?? $item->purchaseRequest->id }}
                                            </span>
                                        @elseif($item->creditStoreLedger)
                                            <span class="badge bg-light text-secondary border fw-normal mt-0.5" style="font-size: 0.68rem;">
                                                <i class="fa-solid fa-truck-ramp-box me-1"></i>Credit Store #{{ $item->creditStoreLedger->id }}
                                            </span>
                                        @endif
                                    </td>

                                    {{-- Category --}}
                                    <td>
                                        @php
                                            $catColor = match($item->category) {
                                                'Contract Work' => 'purple',
                                                'Service' => 'warning',
                                                'Transport' => 'info',
                                                'Loading & Unloading' => 'secondary',
                                                default => 'primary'
                                            };
                                        @endphp
                                        <span class="badge bg-{{ $catColor }}-subtle text-{{ $catColor }} border border-{{ $catColor }}-subtle rounded-pill px-2.5 py-1 small">
                                            {{ $item->category }}
                                        </span>
                                    </td>

                                    {{-- Base Invoiced --}}
                                    <td class="text-end font-monospace fw-semibold small">
                                        ETB {{ number_format($gross, 2) }}
                                    </td>

                                    {{-- VAT --}}
                                    <td class="text-end font-monospace small">
                                        @if((float)($item->vat_amount ?? 0) > 0)
                                            <span class="text-info fw-bold">+ ETB {{ number_format((float)$item->vat_amount, 2) }}</span>
                                            <div class="text-muted" style="font-size:0.68rem;">
                                                {{ $item->vat_type === 'vat_b' ? 'VAT B (15% Incl.)' : '15% Added' }}
                                            </div>
                                        @else
                                            <span class="text-muted small">—</span>
                                        @endif
                                    </td>

                                    {{-- Withholding Tax (3%) --}}
                                    <td class="text-end font-monospace small">
                                        @if($hasWht && $wht > 0)
                                            <span class="text-danger fw-bold">- ETB {{ number_format($wht, 2) }}</span>
                                            <div class="text-muted" style="font-size:0.68rem;">3% Deducted</div>
                                        @else
                                            <span class="text-muted small">—</span>
                                        @endif
                                    </td>

                                    {{-- Net Disbursed --}}
                                    <td class="text-end font-monospace fw-bold text-success small">
                                        ETB {{ number_format($net, 2) }}
                                    </td>

                                    {{-- Paying Account --}}
                                    <td>
                                        <div class="small fw-medium text-dark">
                                            {{ $item->chartOfAccount->name ?? ($item->bankAccount->bank_name ?? 'Petty Cash') }}
                                        </div>
                                        @if($item->paidBy)
                                            <small class="text-muted" style="font-size: 0.7rem;">By: {{ $item->paidBy->name }}</small>
                                        @endif
                                    </td>

                                    {{-- WHT Slip Status --}}
                                    <td class="text-center">
                                        @if(!empty($item->withholding_receipt))
                                            <a href="{{ $item->withholding_receipt_url }}" target="_blank" class="btn btn-xs btn-outline-success rounded-pill px-2.5 py-0.5 shadow-xs" style="font-size:0.75rem;" title="View Attached 3% WHT Receipt Slip">
                                                <i class="fa-solid fa-paperclip me-1"></i> Slip
                                            </a>
                                        @elseif($hasWht)
                                            <span class="badge bg-danger-subtle text-danger border border-danger-subtle rounded-pill px-2 py-0.5" style="font-size:0.68rem;" title="Withholding applied but slip not yet attached">
                                                <i class="fa-solid fa-triangle-exclamation me-1"></i> Missing
                                            </span>
                                        @else
                                            <span class="text-muted small">—</span>
                                        @endif
                                    </td>

                                    {{-- Cycle / Settle Status --}}
                                    <td>
                                        <div class="d-flex flex-column gap-1">
                                            @if((float)($item->vat_amount ?? 0) > 0 || in_array($item->vat_type, ['exclusive', 'inclusive', 'vat_b']))
                                                @if($item->vat_settled)
                                                    <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-2 py-0.5" style="font-size: 0.65rem;" title="VAT Settled {{ optional($item->vat_settled_at)->format('M d, Y') }}">
                                                        <i class="fa-solid fa-check me-1"></i>VAT Paid
                                                    </span>
                                                @elseif($item->vat_settlement_id)
                                                    <span class="badge bg-warning-subtle text-warning border border-warning-subtle rounded-pill px-2 py-0.5" style="font-size: 0.65rem;">
                                                        <i class="fa-solid fa-clock me-1"></i>VAT Pending
                                                    </span>
                                                @else
                                                    <span class="badge bg-info-subtle text-info border border-info-subtle rounded-pill px-2 py-0.5" style="font-size: 0.65rem;">
                                                        <i class="fa-solid fa-bolt me-1"></i>VAT Active
                                                    </span>
                                                @endif
                                            @endif

                                            @if($hasWht)
                                                @if($item->withholding_settled)
                                                    <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-2 py-0.5" style="font-size: 0.65rem;" title="3% WHT Settled {{ optional($item->withholding_settled_at)->format('M d, Y') }}">
                                                        <i class="fa-solid fa-check me-1"></i>WHT Paid
                                                    </span>
                                                @elseif($item->withholding_settlement_id)
                                                    <span class="badge bg-warning-subtle text-warning border border-warning-subtle rounded-pill px-2 py-0.5" style="font-size: 0.65rem;">
                                                        <i class="fa-solid fa-clock me-1"></i>WHT Pending
                                                    </span>
                                                @else
                                                    <span class="badge bg-danger-subtle text-danger border border-danger-subtle rounded-pill px-2 py-0.5" style="font-size: 0.65rem;">
                                                        <i class="fa-solid fa-bolt me-1"></i>WHT Active
                                                    </span>
                                                @endif
                                            @endif

                                            @if(!((float)($item->vat_amount ?? 0) > 0 || in_array($item->vat_type, ['exclusive', 'inclusive', 'vat_b'])) && !$hasWht)
                                                <span class="text-muted small">—</span>
                                            @endif
                                        </div>
                                    </td>

                                    {{-- Actions --}}
                                    <td class="text-center pe-4">
                                        <button type="button" class="btn btn-outline-secondary btn-sm rounded-pill px-2.5 py-1 shadow-xs" 
                                                data-bs-toggle="modal" data-bs-target="#taxDetailModal{{ $item->id }}" title="View Full Tax Details">
                                            <i class="fa-solid fa-eye"></i>
                                        </button>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="11" class="text-center py-5">
                                        @if($cycle === 'active')
                                            <div class="py-4">
                                                <div class="p-3 bg-success bg-opacity-10 text-success rounded-circle d-inline-block fs-1 mb-3">
                                                    <i class="fa-solid fa-circle-check"></i>
                                                </div>
                                                <h5 class="fw-bold text-dark">Starting from Zero: All Taxes Are Settled!</h5>
                                                <p class="text-muted small mx-auto" style="max-width: 480px;">
                                                    All VAT and 3% Withholding Tax liabilities from previous periods have been remitted to ERCA. 
                                                    The current active cycle starts from zero (ETB 0.00). Any new taxable payments made will accumulate here.
                                                </p>
                                                <div class="d-flex justify-content-center gap-2 mt-3 flex-wrap">
                                                    <a href="{{ route('finance.tax-deductions.index', ['cycle' => 'all']) }}" class="btn btn-outline-primary btn-sm rounded-pill px-3 py-1.5">
                                                        <i class="fa-solid fa-layer-group me-1"></i> View All-Time Tax Ledger
                                                    </a>
                                                    <a href="{{ route('finance.tax-deductions.index', ['tab' => 'settlements']) }}" class="btn btn-outline-danger btn-sm rounded-pill px-3 py-1.5">
                                                        <i class="fa-solid fa-file-invoice-dollar me-1"></i> View Past Remittance Batches
                                                    </a>
                                                </div>
                                            </div>
                                        @else
                                            <div class="text-muted py-4">
                                                <i class="fa-solid fa-receipt fs-1 mb-2 d-block text-secondary"></i>
                                                <h6 class="fw-bold text-dark">No Tax Records Found</h6>
                                                <p class="text-muted small mb-0">Try changing your search terms or filter criteria.</p>
                                            </div>
                                        @endif
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                {{-- Pagination --}}
                @if($records->hasPages())
                    <div class="card-footer bg-white border-top py-3 px-4">
                        {{ $records->links() }}
                    </div>
                @endif
            @endif
        </div>
    @endif

</div>

{{-- ── MODAL 1: Pay 15% VAT Only ───────────────────────────────────────────── --}}
<div class="modal fade" id="payVatModal" tabindex="-1" aria-labelledby="payVatModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <div class="modal-header bg-info text-white py-3 px-4">
                <div class="d-flex align-items-center gap-2">
                    <i class="fa-solid fa-percent fs-5"></i>
                    <div>
                        <h5 class="modal-title fw-bold mb-0" id="payVatModalLabel">Pay 15% VAT to ERCA</h5>
                        <small class="text-white-50">Settle accrued VAT liabilities with ERCA / Bank and start VAT cycle from zero (0.00)</small>
                    </div>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>

            <form method="POST" action="{{ route('finance.tax-deductions.settle') }}" enctype="multipart/form-data">
                @csrf
                <input type="hidden" name="tax_type" value="vat">

                <div class="modal-body p-4">
                    {{-- Summary of Unsettled VAT Amounts --}}
                    <div class="p-3 bg-light rounded-4 border mb-4">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="small fw-bold text-uppercase text-muted">Currently Unsettled VAT Liabilities</span>
                            <span class="badge bg-info rounded-pill">{{ $unsettledVatCount }} Unpaid VAT Records</span>
                        </div>
                        <div class="row g-2 text-center">
                            <div class="col-6">
                                <div class="bg-white p-2.5 rounded-3 border">
                                    <div class="text-muted small" style="font-size:0.75rem;">Base Invoiced Amount</div>
                                    <div class="fw-bold text-dark font-monospace fs-6">ETB {{ number_format($unsettledVatBaseAmount, 2) }}</div>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="bg-white p-2.5 rounded-3 border border-info">
                                    <div class="text-muted small" style="font-size:0.75rem;">15% VAT Accrued to Remit</div>
                                    <div class="fw-bold text-info font-monospace fs-5">+ ETB {{ number_format($unsettledVatAmount, 2) }}</div>
                                </div>
                            </div>
                        </div>
                        <div class="text-muted small mt-2" style="font-size: 0.78rem;">
                            <i class="fa-solid fa-circle-info text-info me-1"></i>
                            Once paid or assigned, all <strong>{{ $unsettledVatCount }}</strong> records will have VAT stamped as settled and your active VAT liability will <strong>start from zero (ETB 0.00)</strong>. Withholding tax remains untouched.
                        </div>
                    </div>

                    {{-- Form Fields --}}
                    <div class="row g-3">
                        {{-- Paying Bank / COA Account --}}
                        <div class="col-12 col-md-6">
                            <label class="form-label small fw-bold text-dark">Disbursement Account (Bank / COA) <span class="text-danger">*</span></label>
                            <select name="account_source" id="settleVatAccountSource" class="form-select" onchange="syncAssignedStaff(this, 'settleVatStaffSelect', 'settleVatStaffBadge')" required>
                                <option value="">-- Choose Funding Account --</option>
                                <optgroup label="Bank Accounts (Commercial Banks)">
                                    @foreach($bankAccounts as $ba)
                                        @php
                                            $baStaff = $ba->assignedStaff ?? ($ba->coa?->manager ?? null);
                                            $baStaffId = $baStaff?->id ?? '';
                                            $baStaffName = $baStaff?->name ?? '';
                                        @endphp
                                        <option value="bank:{{ $ba->id }}" 
                                                data-assigned-staff-id="{{ $baStaffId }}" 
                                                data-assigned-staff-name="{{ $baStaffName }}">
                                            {{ $ba->bank_name }} - {{ $ba->account_number }} (Bal: ETB {{ number_format($ba->current_balance, 2) }})
                                            @if($baStaffName) [Custodian: {{ $baStaffName }}] @endif
                                        </option>
                                    @endforeach
                                </optgroup>
                                <optgroup label="Chart of Accounts (Cash &amp; Equivalents)">
                                    @foreach($chartOfAccounts as $coa)
                                        @php
                                            $coaStaff = $coa->manager;
                                            $coaStaffId = $coaStaff?->id ?? '';
                                            $coaStaffName = $coaStaff?->name ?? '';
                                        @endphp
                                        <option value="coa:{{ $coa->id }}" 
                                                data-assigned-staff-id="{{ $coaStaffId }}" 
                                                data-assigned-staff-name="{{ $coaStaffName }}">
                                            {{ $coa->code }} - {{ $coa->name }} ({{ ucfirst($coa->subtype ?? $coa->type) }})
                                            @if($coaStaffName) [Custodian: {{ $coaStaffName }}] @endif
                                        </option>
                                    @endforeach
                                </optgroup>
                            </select>
                        </div>

                        {{-- Assign Finance Staff --}}
                        <div class="col-12 col-md-6">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <label class="form-label small fw-bold text-dark mb-0">
                                    <i class="fa-solid fa-user-check text-primary me-1"></i> Assign Finance Staff to Pay <span class="text-danger">*</span>
                                </label>
                                <span id="settleVatStaffBadge" class="badge bg-success-subtle text-success border border-success-subtle d-none" style="font-size:0.7rem;">
                                    <i class="fa-solid fa-wand-magic-sparkles me-1"></i>Auto-assigned from Account
                                </span>
                            </div>
                            <select name="assigned_finance_staff_id" id="settleVatStaffSelect" class="form-select" required>
                                <option value="">-- Choose Finance Staff --</option>
                                @foreach($financeStaff as $staff)
                                    <option value="{{ $staff->id }}">
                                        {{ $staff->name }} ({{ $staff->email }})
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-12">
                            <small class="text-muted d-block">
                                The Finance Head assigns this VAT remittance task to the selected Finance Staff. The staff member will remit VAT to ERCA / Bank and upload the payment proof slip.
                            </small>
                        </div>

                        {{-- Instant Payment Toggle --}}
                        <div class="col-12">
                            <div class="form-check form-switch p-3 bg-light rounded-3 border">
                                <input class="form-check-input ms-0 me-2" type="checkbox" name="pay_now" value="1" id="payVatNowCheckbox" onchange="togglePayNowVat(this.checked)">
                                <label class="form-check-label fw-bold text-dark" for="payVatNowCheckbox">
                                    Record VAT Payment Now (I have already paid ERCA and have the bank/ERCA receipt)
                                </label>
                                <div class="text-muted small ps-4">Check this if VAT was already remitted to ERCA and you want to enter the receipt reference and upload the slip immediately.</div>
                            </div>
                        </div>

                        {{-- Expanded Instant Payment Fields --}}
                        <div id="payVatNowFields" class="row g-3 mt-1" style="display: none;">
                            <div class="col-12 col-md-6">
                                <label class="form-label small fw-bold text-dark">ERCA Receipt / Bank Ref # <span class="text-danger">*</span></label>
                                <input type="text" name="payment_reference" id="settleVatPaymentRef" class="form-control" placeholder="e.g. VAT-2026-98124 or CBE Ref">
                            </div>
                            <div class="col-12 col-md-6">
                                <label class="form-label small fw-bold text-dark">Payment Date <span class="text-danger">*</span></label>
                                <input type="date" name="payment_date" class="form-control" value="{{ date('Y-m-d') }}">
                            </div>
                            <div class="col-12">
                                <label class="form-label small fw-bold text-dark">Upload Bank Deposit Slip / ERCA VAT Receipt</label>
                                <input type="file" name="attachment" class="form-control" accept=".pdf,.jpg,.jpeg,.png">
                                <small class="text-muted">Attach stamped bank voucher, ERCA e-tax clearance slip, or deposit receipt (PDF/Image max 10MB).</small>
                            </div>
                            <div class="col-12">
                                <label class="form-label small fw-bold text-dark">Payment Notes</label>
                                <textarea name="payment_notes" class="form-control" rows="2" placeholder="Optional notes regarding this VAT settlement..."></textarea>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="modal-footer bg-light border-top py-3 px-4 d-flex justify-content-between">
                    <button type="button" class="btn btn-outline-secondary rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-info text-white rounded-pill px-4 shadow-sm fw-semibold" {{ $unsettledVatCount === 0 ? 'disabled' : '' }}>
                        <i class="fa-solid fa-circle-check me-1"></i> Confirm VAT Payment &amp; Start from Zero
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- ── MODAL 2: Pay 3% Withholding Tax Only ─────────────────────────────────── --}}
<div class="modal fade" id="payWithholdingModal" tabindex="-1" aria-labelledby="payWithholdingModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <div class="modal-header bg-danger text-white py-3 px-4">
                <div class="d-flex align-items-center gap-2">
                    <i class="fa-solid fa-hand-holding-dollar fs-5"></i>
                    <div>
                        <h5 class="modal-title fw-bold mb-0" id="payWithholdingModalLabel">Pay 3% Withholding Tax to ERCA</h5>
                        <small class="text-white-50">Remit deducted 3% withholding taxes to ERCA / Bank and start Withholding cycle from zero (0.00)</small>
                    </div>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>

            <form method="POST" action="{{ route('finance.tax-deductions.settle') }}" enctype="multipart/form-data">
                @csrf
                <input type="hidden" name="tax_type" value="withholding">

                <div class="modal-body p-4">
                    {{-- Summary of Unsettled WHT Amounts --}}
                    <div class="p-3 bg-light rounded-4 border mb-4">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="small fw-bold text-uppercase text-muted">Currently Unsettled 3% Withholding Tax</span>
                            <span class="badge bg-danger rounded-pill">{{ $unsettledWhtCount }} Unpaid WHT Records</span>
                        </div>
                        <div class="row g-2 text-center">
                            <div class="col-6">
                                <div class="bg-white p-2.5 rounded-3 border">
                                    <div class="text-muted small" style="font-size:0.75rem;">Base Invoiced Amount</div>
                                    <div class="fw-bold text-dark font-monospace fs-6">ETB {{ number_format($unsettledWhtBaseAmount, 2) }}</div>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="bg-white p-2.5 rounded-3 border border-danger">
                                    <div class="text-muted small" style="font-size:0.75rem;">3% WHT Deducted to Remit</div>
                                    <div class="fw-bold text-danger font-monospace fs-5">- ETB {{ number_format($unsettledWhtAmount, 2) }}</div>
                                </div>
                            </div>
                        </div>
                        <div class="text-muted small mt-2" style="font-size: 0.78rem;">
                            <i class="fa-solid fa-circle-info text-danger me-1"></i>
                            Once paid or assigned, all <strong>{{ $unsettledWhtCount }}</strong> records will have 3% Withholding stamped as settled and your active Withholding liability will <strong>start from zero (ETB 0.00)</strong>. VAT liability remains untouched.
                        </div>
                    </div>

                    {{-- Form Fields --}}
                    <div class="row g-3">
                        {{-- Paying Bank / COA Account --}}
                        <div class="col-12 col-md-6">
                            <label class="form-label small fw-bold text-dark">Disbursement Account (Bank / COA) <span class="text-danger">*</span></label>
                            <select name="account_source" id="settleWhtAccountSource" class="form-select" onchange="syncAssignedStaff(this, 'settleWhtStaffSelect', 'settleWhtStaffBadge')" required>
                                <option value="">-- Choose Funding Account --</option>
                                <optgroup label="Bank Accounts (Commercial Banks)">
                                    @foreach($bankAccounts as $ba)
                                        @php
                                            $baStaff = $ba->assignedStaff ?? ($ba->coa?->manager ?? null);
                                            $baStaffId = $baStaff?->id ?? '';
                                            $baStaffName = $baStaff?->name ?? '';
                                        @endphp
                                        <option value="bank:{{ $ba->id }}" 
                                                data-assigned-staff-id="{{ $baStaffId }}" 
                                                data-assigned-staff-name="{{ $baStaffName }}">
                                            {{ $ba->bank_name }} - {{ $ba->account_number }} (Bal: ETB {{ number_format($ba->current_balance, 2) }})
                                            @if($baStaffName) [Custodian: {{ $baStaffName }}] @endif
                                        </option>
                                    @endforeach
                                </optgroup>
                                <optgroup label="Chart of Accounts (Cash &amp; Equivalents)">
                                    @foreach($chartOfAccounts as $coa)
                                        @php
                                            $coaStaff = $coa->manager;
                                            $coaStaffId = $coaStaff?->id ?? '';
                                            $coaStaffName = $coaStaff?->name ?? '';
                                        @endphp
                                        <option value="coa:{{ $coa->id }}" 
                                                data-assigned-staff-id="{{ $coaStaffId }}" 
                                                data-assigned-staff-name="{{ $coaStaffName }}">
                                            {{ $coa->code }} - {{ $coa->name }} ({{ ucfirst($coa->subtype ?? $coa->type) }})
                                            @if($coaStaffName) [Custodian: {{ $coaStaffName }}] @endif
                                        </option>
                                    @endforeach
                                </optgroup>
                            </select>
                        </div>

                        {{-- Assign Finance Staff --}}
                        <div class="col-12 col-md-6">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <label class="form-label small fw-bold text-dark mb-0">
                                    <i class="fa-solid fa-user-check text-primary me-1"></i> Assign Finance Staff to Pay <span class="text-danger">*</span>
                                </label>
                                <span id="settleWhtStaffBadge" class="badge bg-success-subtle text-success border border-success-subtle d-none" style="font-size:0.7rem;">
                                    <i class="fa-solid fa-wand-magic-sparkles me-1"></i>Auto-assigned from Account
                                </span>
                            </div>
                            <select name="assigned_finance_staff_id" id="settleWhtStaffSelect" class="form-select" required>
                                <option value="">-- Choose Finance Staff --</option>
                                @foreach($financeStaff as $staff)
                                    <option value="{{ $staff->id }}">
                                        {{ $staff->name }} ({{ $staff->email }})
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-12">
                            <small class="text-muted d-block">
                                The Finance Head assigns this 3% Withholding tax remittance task to the selected Finance Staff. The staff member will remit funds to ERCA / Bank and upload the payment proof slip.
                            </small>
                        </div>

                        {{-- Instant Payment Toggle --}}
                        <div class="col-12">
                            <div class="form-check form-switch p-3 bg-light rounded-3 border">
                                <input class="form-check-input ms-0 me-2" type="checkbox" name="pay_now" value="1" id="payWhtNowCheckbox" onchange="togglePayNowWht(this.checked)">
                                <label class="form-check-label fw-bold text-dark" for="payWhtNowCheckbox">
                                    Record WHT Payment Now (I have already paid ERCA and have the bank/ERCA receipt)
                                </label>
                                <div class="text-muted small ps-4">Check this if 3% Withholding was already remitted to ERCA and you want to enter the receipt reference and upload the slip immediately.</div>
                            </div>
                        </div>

                        {{-- Expanded Instant Payment Fields --}}
                        <div id="payWhtNowFields" class="row g-3 mt-1" style="display: none;">
                            <div class="col-12 col-md-6">
                                <label class="form-label small fw-bold text-dark">ERCA Receipt / Bank Ref # <span class="text-danger">*</span></label>
                                <input type="text" name="payment_reference" id="settleWhtPaymentRef" class="form-control" placeholder="e.g. WHT-2026-98124 or CBE Ref">
                            </div>
                            <div class="col-12 col-md-6">
                                <label class="form-label small fw-bold text-dark">Payment Date <span class="text-danger">*</span></label>
                                <input type="date" name="payment_date" class="form-control" value="{{ date('Y-m-d') }}">
                            </div>
                            <div class="col-12">
                                <label class="form-label small fw-bold text-dark">Upload Bank Deposit Slip / ERCA WHT Receipt</label>
                                <input type="file" name="attachment" class="form-control" accept=".pdf,.jpg,.jpeg,.png">
                                <small class="text-muted">Attach stamped bank voucher, ERCA e-tax clearance slip, or deposit receipt (PDF/Image max 10MB).</small>
                            </div>
                            <div class="col-12">
                                <label class="form-label small fw-bold text-dark">Payment Notes</label>
                                <textarea name="payment_notes" class="form-control" rows="2" placeholder="Optional notes regarding this Withholding Tax settlement..."></textarea>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="modal-footer bg-light border-top py-3 px-4 d-flex justify-content-between">
                    <button type="button" class="btn btn-outline-secondary rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger rounded-pill px-4 shadow-sm fw-semibold" {{ $unsettledWhtCount === 0 ? 'disabled' : '' }}>
                        <i class="fa-solid fa-circle-check me-1"></i> Confirm WHT Payment &amp; Start from Zero
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- ── MODALS: Record Payment for Pending Settlements (Assigned Staff Action) ─ --}}
@if(isset($pendingSettlements) && $pendingSettlements->isNotEmpty())
    @foreach($pendingSettlements as $ps)
        <div class="modal fade" id="recordPaymentModal{{ $ps->id }}" tabindex="-1" aria-hidden="true">
            @php
                $isVatOnly = $ps->tax_type === 'vat';
                $isWhtOnly = $ps->tax_type === 'withholding';
                $modalBg = $isVatOnly ? 'bg-info' : ($isWhtOnly ? 'bg-danger' : 'bg-success');
                $modalIcon = $isVatOnly ? 'fa-percent' : ($isWhtOnly ? 'fa-hand-holding-dollar' : 'fa-file-invoice-dollar');
                $modalTitle = $isVatOnly ? "Record 15% VAT Payment: {$ps->settlement_number}" : ($isWhtOnly ? "Record 3% Withholding Tax Payment: {$ps->settlement_number}" : "Record Tax Payment: {$ps->settlement_number}");
                $modalSubtitle = $isVatOnly ? "Upload bank slip & ERCA VAT receipt to reset VAT ledger to zero" : ($isWhtOnly ? "Upload bank slip & ERCA receipt to reset Withholding ledger to zero" : "Upload bank slip & ERCA receipt to settle taxes and reset ledger to zero");
            @endphp
            <div class="modal-dialog modal-dialog-centered modal-lg">
                <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
                    <div class="modal-header {{ $modalBg }} text-white py-3 px-4">
                        <div class="d-flex align-items-center gap-2">
                            <i class="fa-solid {{ $modalIcon }} fs-5"></i>
                            <div>
                                <h5 class="modal-title fw-bold mb-0">{{ $modalTitle }}</h5>
                                <small class="text-white-50">{{ $modalSubtitle }}</small>
                            </div>
                        </div>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>

                    <form method="POST" action="{{ route('finance.tax-deductions.settle.pay', $ps) }}" enctype="multipart/form-data">
                        @csrf

                        <div class="modal-body p-4">
                            {{-- Remittance Breakdown Info --}}
                            <div class="p-3 bg-light rounded-4 border mb-4">
                                <div class="row g-2 text-center">
                                    @if($isVatOnly)
                                        <div class="col-6">
                                            <div class="bg-white p-2.5 rounded-3 border">
                                                <div class="text-muted small" style="font-size:0.75rem;">Base Invoiced Amount</div>
                                                <div class="fw-bold text-dark font-monospace fs-6">ETB {{ number_format($ps->total_base_amount, 2) }}</div>
                                            </div>
                                        </div>
                                        <div class="col-6">
                                            <div class="bg-white p-2.5 rounded-3 border border-info">
                                                <div class="text-muted small" style="font-size:0.75rem;">15% VAT Paid to ERCA</div>
                                                <div class="fw-bold text-info font-monospace fs-6">ETB {{ number_format($ps->vat_amount, 2) }}</div>
                                            </div>
                                        </div>
                                    @elseif($isWhtOnly)
                                        <div class="col-6">
                                            <div class="bg-white p-2.5 rounded-3 border">
                                                <div class="text-muted small" style="font-size:0.75rem;">Base Invoiced Amount</div>
                                                <div class="fw-bold text-dark font-monospace fs-6">ETB {{ number_format($ps->total_base_amount, 2) }}</div>
                                            </div>
                                        </div>
                                        <div class="col-6">
                                            <div class="bg-white p-2.5 rounded-3 border border-danger">
                                                <div class="text-muted small" style="font-size:0.75rem;">3% WHT Paid to ERCA</div>
                                                <div class="fw-bold text-danger font-monospace fs-6">ETB {{ number_format($ps->withholding_amount, 2) }}</div>
                                            </div>
                                        </div>
                                    @else
                                        <div class="col-4">
                                            <div class="bg-white p-2.5 rounded-3 border">
                                                <div class="text-muted small" style="font-size:0.75rem;">VAT to Remit</div>
                                                <div class="fw-bold text-info font-monospace fs-6">ETB {{ number_format($ps->vat_amount, 2) }}</div>
                                            </div>
                                        </div>
                                        <div class="col-4">
                                            <div class="bg-white p-2.5 rounded-3 border">
                                                <div class="text-muted small" style="font-size:0.75rem;">3% WHT to Remit</div>
                                                <div class="fw-bold text-danger font-monospace fs-6">ETB {{ number_format($ps->withholding_amount, 2) }}</div>
                                            </div>
                                        </div>
                                        <div class="col-4">
                                            <div class="bg-white p-2.5 rounded-3 border border-success">
                                                <div class="text-muted small" style="font-size:0.75rem;">Total Tax Remitted</div>
                                                <div class="fw-bold text-success font-monospace fs-6">ETB {{ number_format($ps->total_tax_paid, 2) }}</div>
                                            </div>
                                        </div>
                                    @endif
                                </div>
                                <div class="small text-muted mt-2 text-center">
                                    Assigned by: <strong>{{ $ps->financeHead?->name ?? 'Finance Head' }}</strong> &bull; 
                                    Assigned to: <strong>{{ $ps->assignedStaff?->name ?? 'Finance Staff' }}</strong> &bull; 
                                    Covering <strong>{{ $ps->records_count }}</strong> tax deduction records.
                                </div>
                            </div>

                            <div class="row g-3">
                                <div class="col-12 col-md-6">
                                    <label class="form-label small fw-bold text-dark">Bank Ref / ERCA E-Tax Receipt # <span class="text-danger">*</span></label>
                                    <input type="text" name="payment_reference" class="form-control" required placeholder="e.g. TX-2026-87162 or CBE Ref">
                                </div>

                                <div class="col-12 col-md-6">
                                    <label class="form-label small fw-bold text-dark">Payment Execution Date <span class="text-danger">*</span></label>
                                    <input type="date" name="payment_date" class="form-control" value="{{ date('Y-m-d') }}" required>
                                </div>

                                <div class="col-12 col-md-6">
                                    <label class="form-label small fw-bold text-dark">Payment Method</label>
                                    <select name="payment_method" class="form-select">
                                        <option value="bank_transfer" selected>Bank Transfer / Deposit</option>
                                        <option value="online_etax">ERCA Online E-Tax Portal</option>
                                        <option value="cbe_birr">CBE Birr / Telebirr</option>
                                        <option value="check">Company Check</option>
                                        <option value="cash">Cash Counter Payment</option>
                                    </select>
                                </div>

                                <div class="col-12 col-md-6">
                                    <label class="form-label small fw-bold text-dark">Paid From Account (Bank / COA)</label>
                                    <select name="account_source" class="form-select">
                                        @if($ps->bank_account_id)
                                            <option value="bank:{{ $ps->bank_account_id }}" selected>
                                                Default: {{ $ps->bankAccount?->bank_name }} - {{ $ps->bankAccount?->account_number }}
                                            </option>
                                        @elseif($ps->coa_id)
                                            <option value="coa:{{ $ps->coa_id }}" selected>
                                                Default: {{ $ps->chartOfAccount?->name }} ({{ $ps->chartOfAccount?->code }})
                                            </option>
                                        @endif
                                        @foreach($bankAccounts as $ba)
                                            <option value="bank:{{ $ba->id }}">
                                                {{ $ba->bank_name }} - {{ $ba->account_number }} (Bal: ETB {{ number_format($ba->current_balance, 2) }})
                                            </option>
                                        @endforeach
                                        @foreach($chartOfAccounts as $coa)
                                            <option value="coa:{{ $coa->id }}">
                                                {{ $coa->code }} - {{ $coa->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>

                                <div class="col-12">
                                    <label class="form-label small fw-bold text-dark">
                                        Upload ERCA Tax Receipt / Bank Deposit Slip <span class="text-danger">*</span>
                                    </label>
                                    <input type="file" name="attachment" class="form-control" accept=".pdf,.jpg,.jpeg,.png" required>
                                    <small class="text-muted">Official stamped bank slip or ERCA tax clearance document is required to verify the settlement.</small>
                                </div>

                                <div class="col-12">
                                    <label class="form-label small fw-bold text-dark">Payment Remarks / Notes</label>
                                    <textarea name="payment_notes" class="form-control" rows="2" placeholder="Optional notes for audit records..."></textarea>
                                </div>
                            </div>
                        </div>

                        <div class="modal-footer bg-light border-top py-3 px-4 d-flex justify-content-between">
                            <button type="button" class="btn btn-outline-secondary rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-success rounded-pill px-4 shadow-sm fw-semibold">
                                <i class="fa-solid fa-file-circle-check me-1"></i> Confirm Payment &amp; Start from Zero
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endforeach
@endif

{{-- ── MODALS: Individual Record Detail Modals ───────────────────────────────── --}}
@foreach($records as $item)
    @php
        $gross = (float)($item->gross_amount > 0 ? $item->gross_amount : $item->amount);
        $wht = (float)$item->calculated_withholding_amount;
        $net = (float)$item->effective_payable_amount;
        $hasWht = (bool)($item->has_withholding || $item->withholding_amount > 0 || $wht > 0);
    @endphp
    <div class="modal fade" id="taxDetailModal{{ $item->id }}" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
                <div class="modal-header bg-light border-bottom py-3 px-4">
                    <div class="d-flex align-items-center gap-2">
                        <span class="badge bg-primary rounded-pill font-monospace px-2.5 py-1">{{ $item->request_number }}</span>
                        <h6 class="modal-title fw-bold text-dark mb-0">Tax Deduction Voucher Details</h6>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    {{-- Tax Figures Grid --}}
                    <div class="row g-2 mb-3 text-center">
                        <div class="col-6 col-md-3">
                            <div class="p-2.5 bg-light rounded-3 border">
                                <div class="small text-muted" style="font-size:0.75rem;">Base Amount</div>
                                <div class="fw-bold font-monospace text-dark">ETB {{ number_format($gross, 2) }}</div>
                            </div>
                        </div>
                        <div class="col-6 col-md-3">
                            <div class="p-2.5 bg-light rounded-3 border border-info">
                                <div class="small text-muted" style="font-size:0.75rem;">VAT Rate / Amount</div>
                                <div class="fw-bold font-monospace text-info">+ ETB {{ number_format((float)($item->vat_amount ?? 0), 2) }}</div>
                            </div>
                        </div>
                        <div class="col-6 col-md-3">
                            <div class="p-2.5 bg-light rounded-3 border border-danger">
                                <div class="small text-muted" style="font-size:0.75rem;">3% Withholding Tax</div>
                                <div class="fw-bold font-monospace text-danger">- ETB {{ number_format($wht, 2) }}</div>
                            </div>
                        </div>
                        <div class="col-6 col-md-3">
                            <div class="p-2.5 bg-light rounded-3 border border-success">
                                <div class="small text-muted" style="font-size:0.75rem;">Net Disbursed</div>
                                <div class="fw-bold font-monospace text-success">ETB {{ number_format($net, 2) }}</div>
                            </div>
                        </div>
                    </div>

                    {{-- Configuration Details --}}
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <div class="p-3 rounded-3 border bg-light-subtle h-100">
                                <span class="small text-muted text-uppercase fw-bold d-block mb-1">Tax Configuration</span>
                                <div class="small">
                                    <div><strong>VAT Mode:</strong> {{ $item->vat_type === 'vat_b' ? '15% Included (VAT B)' : ($item->vat_type === 'exclusive' ? '15% Added Exclusive' : 'No VAT (0%)') }}</div>
                                    <div><strong>VAT Rate:</strong> {{ number_format((float)($item->vat_rate ?? 15.00), 2) }}%</div>
                                    <div><strong>Withholding Applied:</strong> {{ $hasWht ? 'YES (3.00% Service Deduction)' : 'NO' }}</div>
                                    <div><strong>Paying Source:</strong> {{ $item->chartOfAccount->name ?? ($item->bankAccount->bank_name ?? 'Petty Cash') }}</div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="p-3 rounded-3 border bg-light-subtle h-100">
                                <span class="small text-muted text-uppercase fw-bold d-block mb-1">Payment &amp; Settlement Data</span>
                                <div class="small">
                                    <div><strong>Payment Ref:</strong> {{ $item->payment_reference ?? 'Pending' }}</div>
                                    <div><strong>Paid Date:</strong> {{ optional($item->paid_at)->format('d M Y, h:i A') ?? 'Pending' }}</div>
                                    <div><strong>Paid By:</strong> {{ $item->paidBy->name ?? 'Finance' }}</div>
                                    <div>
                                        <strong>Settlement Status:</strong> 
                                        @if($item->vat_settled || $item->withholding_settled)
                                            <span class="badge bg-success-subtle text-success border">Settled (Paid to ERCA)</span>
                                        @else
                                            <span class="badge bg-primary-subtle text-primary border">Active (Unsettled)</span>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Description --}}
                    <div class="mb-3">
                        <label class="form-label small text-muted text-uppercase fw-bold mb-1">Description / Details</label>
                        <div class="p-3 bg-light rounded-3 border small text-dark">
                            {{ $item->description }}
                        </div>
                    </div>

                    {{-- Supporting Documents & Withholding Slips --}}
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label small text-muted text-uppercase fw-bold mb-1">Invoice / Expense Attachment</label>
                            <div>
                                @if($item->attachment_url)
                                    <a href="{{ $item->attachment_url }}" target="_blank" class="btn btn-outline-primary btn-sm rounded-3 px-3 shadow-xs">
                                        <i class="fa-solid fa-paperclip me-1"></i> Open Invoiced Document
                                    </a>
                                @else
                                    <span class="text-muted small">No file attached</span>
                                @endif
                            </div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label small text-muted text-uppercase fw-bold mb-1">3% Withholding Tax Receipt / Slip</label>
                            <div>
                                @if(!empty($item->withholding_receipt))
                                    <a href="{{ $item->withholding_receipt_url }}" target="_blank" class="btn btn-outline-danger btn-sm rounded-3 px-3 shadow-xs">
                                        <i class="fa-solid fa-file-pdf me-1"></i> View Official WHT Slip @if(!empty($item->withholding_receipt_number)) (Ref: {{ $item->withholding_receipt_number }}) @endif
                                    </a>
                                @elseif($hasWht)
                                    <span class="badge bg-danger-subtle text-danger border border-danger-subtle p-2">
                                        <i class="fa-solid fa-triangle-exclamation me-1"></i> Receipt Slip Required — Not Yet Uploaded
                                    </span>
                                @else
                                    <span class="text-muted small">N/A (No Withholding)</span>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light border-0 py-3 px-4">
                    <button type="button" class="btn btn-secondary rounded-pill px-4" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
@endforeach

{{-- ── MODAL: High-Res OCR Receipt Slip Preview ────────────────────────────────────── --}}
<div class="modal fade" id="vatReceiptSlipModal" tabindex="-1" aria-labelledby="vatReceiptSlipModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <div class="modal-header bg-dark text-white py-3 px-4">
                <div class="d-flex align-items-center gap-2">
                    <i class="fa-solid fa-receipt text-info fs-5"></i>
                    <div>
                        <h5 class="modal-title fw-bold mb-0" id="vatReceiptSlipModalLabel">
                            Receipt Slip Preview &mdash; <span id="modalFsNoDisplay" class="font-monospace text-info"></span>
                        </h5>
                        <small class="text-white-50">Verified ERCA Fiscal Receipt Document</small>
                    </div>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div class="row g-4 align-items-center">
                    <div class="col-md-7 text-center">
                        <div class="p-2 border rounded-3 bg-light shadow-xs d-inline-block w-100" style="min-height: 280px; max-height: 520px; overflow: auto;">
                            <img id="modalSlipImg" src="" alt="Receipt Slip" class="img-fluid rounded" style="max-height: 480px; object-fit: contain;">
                        </div>
                    </div>
                    <div class="col-md-5">
                        <div class="p-3 bg-light rounded-3 border">
                            <h6 class="fw-bold text-dark border-bottom pb-2 mb-3">
                                <i class="fa-solid fa-file-invoice text-info me-1"></i>ERCA Declaration Data
                            </h6>
                            <div class="d-flex flex-column gap-2 small">
                                <div>
                                    <span class="text-muted text-uppercase fw-bold d-block" style="font-size:0.7rem;">Supplier / Merchant</span>
                                    <span id="modalVendorName" class="fw-bold text-dark fs-6"></span>
                                </div>
                                <div class="row g-2">
                                    <div class="col-6">
                                        <span class="text-muted text-uppercase fw-bold d-block" style="font-size:0.7rem;">Supplier TIN (Col D)</span>
                                        <span id="modalVendorTin" class="font-monospace fw-bold text-primary"></span>
                                    </div>
                                    <div class="col-6">
                                        <span class="text-muted text-uppercase fw-bold d-block" style="font-size:0.7rem;">Receipt Date (Col F)</span>
                                        <span id="modalDate" class="font-monospace text-secondary"></span>
                                    </div>
                                </div>
                                <div class="row g-2">
                                    <div class="col-6">
                                        <span class="text-muted text-uppercase fw-bold d-block" style="font-size:0.7rem;">FS # (Col H)</span>
                                        <span id="modalFsNo" class="font-monospace fw-bold text-dark"></span>
                                    </div>
                                    <div class="col-6">
                                        <span class="text-muted text-uppercase fw-bold d-block" style="font-size:0.7rem;">MRC # (Col G)</span>
                                        <span id="modalMrcNo" class="font-monospace text-secondary"></span>
                                    </div>
                                </div>
                                <hr class="my-1">
                                <div class="d-flex justify-content-between align-items-center">
                                    <span class="text-muted">Taxable Base (Col M):</span>
                                    <span class="font-monospace fw-semibold text-dark">ETB <span id="modalBase"></span></span>
                                </div>
                                <div class="d-flex justify-content-between align-items-center">
                                    <span class="text-muted">VAT 15% (Col N):</span>
                                    <span class="font-monospace fw-bold text-info">+ ETB <span id="modalVat"></span></span>
                                </div>
                                <div class="d-flex justify-content-between align-items-center border-top pt-1 mt-1">
                                    <span class="fw-bold text-dark">Total with VAT (Col O):</span>
                                    <span class="font-monospace fw-bold text-success fs-6">ETB <span id="modalTotal"></span></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light border-0 py-3 px-4 d-flex justify-content-between">
                <a id="modalStudioLink" href="{{ route('admin.ocr.index') }}" target="_blank" class="btn btn-outline-primary btn-sm rounded-pill px-3">
                    <i class="fa-solid fa-up-right-from-square me-1"></i>Open in OCR Studio
                </a>
                <div class="d-flex gap-2">
                    <a id="modalDownloadBtn" href="#" target="_blank" download class="btn btn-info text-white btn-sm rounded-pill px-3 shadow-xs">
                        <i class="fa-solid fa-download me-1"></i>Download Slip
                    </a>
                    <button type="button" class="btn btn-secondary btn-sm rounded-pill px-3" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- ── MODAL: Select Date Range & Export in CSV / Excel ────────────────────────────── --}}
<div class="modal fade" id="exportDateModal" tabindex="-1" aria-labelledby="exportDateModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <div class="modal-header bg-success text-white py-3 px-4">
                <div class="d-flex align-items-center gap-2">
                    <i class="fa-solid fa-file-export fs-5"></i>
                    <div>
                        <h5 class="modal-title fw-bold mb-0" id="exportDateModalLabel">Export Records by Date</h5>
                        <small class="text-white-50">Select custom days or quick presets to download report</small>
                    </div>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>

            <form id="exportDateForm" method="GET" action="{{ route('finance.tax-deductions.export-csv') }}">
                <input type="hidden" name="tab" id="export_modal_tab" value="{{ $tab }}">
                <input type="hidden" name="cycle" value="{{ $cycle }}">
                <input type="hidden" name="category" value="{{ request('category') }}">
                <input type="hidden" name="search" value="{{ request('search') }}">

                <div class="modal-body p-4">
                    {{-- Quick Date Range Presets --}}
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-uppercase text-muted d-block mb-2" style="font-size:0.72rem; letter-spacing:0.5px;">
                            <i class="fa-solid fa-bolt text-warning me-1"></i>Quick Date Presets
                        </label>
                        <div class="d-flex gap-1.5 flex-wrap">
                            <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill px-2.5 py-1 text-xs" onclick="setExportDatePreset('today')">Today</button>
                            <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill px-2.5 py-1 text-xs" onclick="setExportDatePreset('yesterday')">Yesterday</button>
                            <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill px-2.5 py-1 text-xs" onclick="setExportDatePreset('this_week')">This Week</button>
                            <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill px-2.5 py-1 text-xs" onclick="setExportDatePreset('this_month')">This Month</button>
                            <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill px-2.5 py-1 text-xs" onclick="setExportDatePreset('last_month')">Last Month</button>
                            <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill px-2.5 py-1 text-xs" onclick="setExportDatePreset('all')">All Dates</button>
                        </div>
                    </div>

                    {{-- Date Pickers: From Date & To Date --}}
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-bold text-dark mb-1">
                                <i class="fa-regular fa-calendar me-1 text-primary"></i>From Date (Start)
                            </label>
                            <input type="date" class="form-control" id="export_from_date" name="from_date" value="{{ request('from_date') }}">
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-bold text-dark mb-1">
                                <i class="fa-regular fa-calendar-check me-1 text-primary"></i>To Date (End)
                            </label>
                            <input type="date" class="form-control" id="export_to_date" name="to_date" value="{{ request('to_date') }}">
                        </div>
                    </div>

                    {{-- Export Format Selector (CSV vs Excel) --}}
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-uppercase text-muted d-block mb-1" style="font-size:0.72rem; letter-spacing:0.5px;">
                            Export File Format
                        </label>
                        <div class="row g-2">
                            <div class="col-6">
                                <div class="form-check p-2.5 border rounded-3 bg-light d-flex align-items-center gap-2 m-0 cursor-pointer" onclick="document.getElementById('export_fmt_csv').checked = true; updateExportAction();">
                                    <input class="form-check-input m-0 ms-1" type="radio" name="export_format" id="export_fmt_csv" value="csv" checked onchange="updateExportAction()">
                                    <label class="form-check-label small fw-bold text-dark cursor-pointer mb-0" for="export_fmt_csv">
                                        <i class="fa-solid fa-file-csv text-success me-1"></i>CSV (.csv)
                                    </label>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="form-check p-2.5 border rounded-3 bg-light d-flex align-items-center gap-2 m-0 cursor-pointer" onclick="document.getElementById('export_fmt_excel').checked = true; updateExportAction();">
                                    <input class="form-check-input m-0 ms-1" type="radio" name="export_format" id="export_fmt_excel" value="excel" onchange="updateExportAction()">
                                    <label class="form-check-label small fw-bold text-dark cursor-pointer mb-0" for="export_fmt_excel">
                                        <i class="fa-solid fa-file-excel text-success me-1"></i>Excel (.xlsx)
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- ERCA OCR Specific Options (shown when on VAT tab) --}}
                    <div id="export_vat_options_container" class="mb-3 p-3 bg-light rounded-3 border">
                        <label class="form-label small fw-bold text-dark mb-1 d-block">
                            <i class="fa-solid fa-shield-halved text-info me-1"></i>ERCA VAT Specification
                        </label>
                        <div class="mb-2">
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="template" id="tpl_etax" value="etax" checked>
                                <label class="form-check-label small fw-semibold" for="tpl_etax">
                                    eTax Purchase Declaration (9 Cols / Official)
                                </label>
                            </div>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="template" id="tpl_erca15" value="erca_15">
                                <label class="form-check-label small fw-semibold" for="tpl_erca15">
                                    Full ERCA 15-Column Report
                                </label>
                            </div>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="valid_only" id="export_valid_only" value="1">
                            <label class="form-check-label small text-muted" for="export_valid_only">
                                Export only rows with valid 10-digit TIN &amp; 8-digit FS #
                            </label>
                        </div>
                    </div>

                    <div class="alert alert-info border-info-subtle small py-2 mb-0 d-flex align-items-center gap-2">
                        <i class="fa-solid fa-circle-info text-info"></i>
                        <span id="export_preview_summary">Exporting all records. Leave dates blank to export entire cycle.</span>
                    </div>
                </div>

                <div class="modal-footer bg-light border-0 py-3 px-4 d-flex justify-content-between">
                    <button type="button" class="btn btn-secondary rounded-pill px-3" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success fw-bold rounded-pill px-4 shadow-xs" id="btnSubmitExport">
                        <i class="fa-solid fa-download me-1"></i>Download Export
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    // ── Export by Date Modal Handlers ─────────────────────────────────────────
    function openDateExportModal(tab = 'all', defaultFormat = 'csv') {
        const modalEl = document.getElementById('exportDateModal');
        if (!modalEl) return;

        const tabInput = document.getElementById('export_modal_tab');
        if (tabInput) tabInput.value = tab;

        const csvRadio = document.getElementById('export_fmt_csv');
        const excelRadio = document.getElementById('export_fmt_excel');
        if (defaultFormat === 'excel' && excelRadio) {
            excelRadio.checked = true;
        } else if (csvRadio) {
            csvRadio.checked = true;
        }

        const vatOptions = document.getElementById('export_vat_options_container');
        if (vatOptions) {
            vatOptions.style.display = (tab === 'vat') ? 'block' : 'none';
        }

        updateExportAction();
        updateExportSummary();

        const modal = new bootstrap.Modal(modalEl);
        modal.show();
    }

    function setExportDatePreset(preset) {
        const fromInput = document.getElementById('export_from_date');
        const toInput = document.getElementById('export_to_date');
        if (!fromInput || !toInput) return;

        const now = new Date();
        const formatDate = (d) => {
            const year = d.getFullYear();
            const month = String(d.getMonth() + 1).padStart(2, '0');
            const day = String(d.getDate()).padStart(2, '0');
            return `${year}-${month}-${day}`;
        };

        if (preset === 'today') {
            const todayStr = formatDate(now);
            fromInput.value = todayStr;
            toInput.value = todayStr;
        } else if (preset === 'yesterday') {
            const yest = new Date(now);
            yest.setDate(now.getDate() - 1);
            const yestStr = formatDate(yest);
            fromInput.value = yestStr;
            toInput.value = yestStr;
        } else if (preset === 'this_week') {
            const d = new Date(now);
            const dayOfWeek = d.getDay() || 7;
            d.setDate(d.getDate() - dayOfWeek + 1);
            fromInput.value = formatDate(d);
            toInput.value = formatDate(now);
        } else if (preset === 'this_month') {
            const firstDay = new Date(now.getFullYear(), now.getMonth(), 1);
            fromInput.value = formatDate(firstDay);
            toInput.value = formatDate(now);
        } else if (preset === 'last_month') {
            const firstDayLastMonth = new Date(now.getFullYear(), now.getMonth() - 1, 1);
            const lastDayLastMonth = new Date(now.getFullYear(), now.getMonth(), 0);
            fromInput.value = formatDate(firstDayLastMonth);
            toInput.value = formatDate(lastDayLastMonth);
        } else if (preset === 'all') {
            fromInput.value = '';
            toInput.value = '';
        }

        updateExportSummary();
    }

    function updateExportAction() {
        const form = document.getElementById('exportDateForm');
        const tabInput = document.getElementById('export_modal_tab');
        const isExcel = document.getElementById('export_fmt_excel')?.checked;
        const currentTab = tabInput ? tabInput.value : 'all';

        if (!form) return;

        if (currentTab === 'vat') {
            form.action = isExcel 
                ? '{{ route('admin.ocr.export-excel') }}' 
                : '{{ route('admin.ocr.export-csv') }}';
        } else {
            form.action = '{{ route('finance.tax-deductions.export-csv') }}';
        }

        const btn = document.getElementById('btnSubmitExport');
        if (btn) {
            btn.innerHTML = `<i class="fa-solid fa-download me-1"></i>Download ${isExcel ? 'Excel (.xlsx)' : 'CSV (.csv)'}`;
        }
    }

    function updateExportSummary() {
        const fromVal = document.getElementById('export_from_date')?.value;
        const toVal = document.getElementById('export_to_date')?.value;
        const summary = document.getElementById('export_preview_summary');
        if (!summary) return;

        if (fromVal && toVal) {
            if (fromVal === toVal) {
                summary.innerHTML = `Exporting records for day: <strong>${fromVal}</strong>`;
            } else {
                summary.innerHTML = `Exporting records from <strong>${fromVal}</strong> to <strong>${toVal}</strong>`;
            }
        } else if (fromVal) {
            summary.innerHTML = `Exporting records starting from <strong>${fromVal}</strong> onwards`;
        } else if (toVal) {
            summary.innerHTML = `Exporting records up to <strong>${toVal}</strong>`;
        } else {
            summary.innerHTML = `Exporting all available records (all dates).`;
        }
    }

    document.addEventListener('DOMContentLoaded', function() {
        document.getElementById('export_from_date')?.addEventListener('change', updateExportSummary);
        document.getElementById('export_to_date')?.addEventListener('change', updateExportSummary);
    });

    // ── High-Res Slip Modal ──────────────────────────────────────────────────
    function openVatSlipModal(imgUrl, vendor, tin, fs, mrc, date, base, vat, total, studioUrl) {
        document.getElementById('modalSlipImg').src = imgUrl;
        document.getElementById('modalVendorName').textContent = vendor || 'N/A';
        document.getElementById('modalVendorTin').textContent = tin || 'N/A';
        document.getElementById('modalFsNo').textContent = fs || '—';
        document.getElementById('modalFsNoDisplay').textContent = fs ? ('FS #' + fs) : 'Slip View';
        document.getElementById('modalMrcNo').textContent = mrc || '—';
        document.getElementById('modalDate').textContent = date || '—';
        document.getElementById('modalBase').textContent = base || '0.00';
        document.getElementById('modalVat').textContent = vat || '0.00';
        document.getElementById('modalTotal').textContent = total || '0.00';
        document.getElementById('modalDownloadBtn').href = imgUrl;
        if (studioUrl) {
            document.getElementById('modalStudioLink').href = studioUrl;
        }
        const modal = new bootstrap.Modal(document.getElementById('vatReceiptSlipModal'));
        modal.show();
    }

    // ── Delete OCR Row ────────────────────────────────────────────────────────
    function deleteOcrItem(itemId, label) {
        if (!confirm('Are you sure you want to delete ' + label + ' from the ERCA VAT declaration? This will remove the extracted row from the database.')) {
            return;
        }
        fetch('{{ url('/admin/receipt-ocr/item') }}/' + itemId, {
            method: 'DELETE',
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                const row = document.getElementById('ocr-row-' + itemId);
                if (row) {
                    row.style.transition = 'all 0.3s ease';
                    row.style.opacity = '0';
                    setTimeout(() => {
                        row.remove();
                        window.location.reload();
                    }, 300);
                } else {
                    window.location.reload();
                }
            } else {
                alert(data.message || 'Failed to delete row.');
            }
        })
        .catch(err => {
            console.error(err);
            alert('An error occurred while deleting the row.');
        });
    }

    // ── VAT Live OCR File Upload Handler ──────────────────────────────────────
    function handleVatFileUpload(file, forceSave = false) {
        if (!file) return;

        const validTypes = ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'];
        if (!validTypes.includes(file.type) && !file.name.match(/\.(jpe?g|png|webp|pdf)$/i)) {
            alert('Invalid file format. Please upload JPEG, PNG, WEBP, or PDF.');
            return;
        }

        const maxBytes = 25 * 1024 * 1024; // 25MB
        if (file.size > maxBytes) {
            alert('File is too large. Maximum file size is 25MB.');
            return;
        }

        const progressContainer = document.getElementById('vat-scan-progress');
        const progressBar = document.getElementById('vat-scan-bar');
        const progressPct = document.getElementById('vat-scan-pct');
        const statusText = document.getElementById('vat-scan-status-text');
        const alertBox = document.getElementById('vat-scan-result-alert');
        const alertMsg = document.getElementById('vat-scan-result-msg');

        if (progressContainer) progressContainer.classList.remove('d-none');
        if (progressBar) progressBar.style.width = '20%';
        if (progressPct) progressPct.textContent = '20%';
        if (statusText) statusText.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-1 text-info"></i>Uploading receipt file...';

        let progressInterval = setInterval(() => {
            if (!progressBar) return;
            let cur = parseInt(progressBar.style.width) || 20;
            if (cur < 85) {
                cur += 15;
                progressBar.style.width = cur + '%';
                if (progressPct) progressPct.textContent = cur + '%';
                if (cur >= 50 && statusText) {
                    statusText.innerHTML = '<i class="fa-solid fa-microchip fa-spin me-1 text-info"></i>Extracting ERCA fields with AI Vision OCR...';
                }
            }
        }, 600);

        const formData = new FormData();
        formData.append('receipt_file', file);
        formData.append('_token', '{{ csrf_token() }}');
        const projVal = document.getElementById('vat-project-select')?.value;
        if (projVal) formData.append('project_id', projVal);
        const catVal = document.getElementById('vat-category-select')?.value;
        if (catVal) formData.append('category', catVal);
        if (forceSave) formData.append('force_save', '1');

        fetch('{{ route('admin.ocr.process-file') }}', {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            }
        })
        .then(async res => {
            clearInterval(progressInterval);
            const data = await res.json();
            if (!res.ok) {
                throw new Error(data.message || 'Server error ' + res.status);
            }
            return data;
        })
        .then(data => {
            if (progressBar) progressBar.style.width = '100%';
            if (progressPct) progressPct.textContent = '100%';

            if (data.is_duplicate) {
                if (statusText) statusText.innerHTML = '<i class="fa-solid fa-triangle-exclamation text-warning me-1"></i>Duplicate receipt detected';
                if (alertBox) {
                    alertBox.className = 'alert alert-warning border small py-2 mb-0 d-flex align-items-center justify-content-between gap-2';
                    alertBox.innerHTML = `<div><i class="fa-solid fa-triangle-exclamation text-warning me-1"></i><strong>${data.duplicate_message}</strong></div><button type="button" class="btn btn-warning btn-xs fw-bold" id="btn-force-save-vat">Save Anyway</button>`;
                    document.getElementById('btn-force-save-vat')?.addEventListener('click', () => {
                        handleVatFileUpload(file, true);
                    });
                }
                return;
            }

            if (statusText) statusText.innerHTML = '<i class="fa-solid fa-circle-check text-success me-1"></i>Scan Complete &amp; ERCA Record Created!';
            if (alertBox) {
                alertBox.className = 'alert alert-success border border-success-subtle small py-2 mb-0 d-flex align-items-center gap-2';
                const rcp = data.receipt || {};
                const itemsCount = data.items ? data.items.length : 1;
                alertBox.innerHTML = `<i class="fa-solid fa-circle-check text-success fs-5"></i><div><strong>Receipt Extracted:</strong> ${rcp.vendor_name || 'Vendor'} (TIN: ${rcp.vendor_tin || '—'}, FS: ${rcp.fs_no || '—'}) &mdash; <strong>VAT: ETB ${Number(rcp.vat_amount || 0).toLocaleString(undefined, {minimumFractionDigits: 2})}</strong> (${itemsCount} row(s)). Reloading table...</div>`;
            }

            setTimeout(() => {
                window.location.reload();
            }, 1200);
        })
        .catch(err => {
            clearInterval(progressInterval);
            if (progressBar) {
                progressBar.style.width = '100%';
                progressBar.classList.remove('bg-info');
                progressBar.classList.add('bg-danger');
            }
            if (statusText) statusText.innerHTML = '<i class="fa-solid fa-circle-xmark text-danger me-1"></i>Extraction failed: ' + (err.message || 'Unknown error');
            if (alertBox) {
                alertBox.className = 'alert alert-danger border border-danger-subtle small py-2 mb-0 d-flex align-items-center gap-2';
                alertBox.innerHTML = `<i class="fa-solid fa-circle-xmark text-danger"></i><div><strong>Error scanning receipt:</strong> ${err.message || 'Please check the file and try again.'}</div>`;
            }
        });
    }

    // ── Dropzone Event Binding ───────────────────────────────────────────────
    document.addEventListener('DOMContentLoaded', function() {
        const dropZone = document.getElementById('vat-drop-zone');
        const fileInput = document.getElementById('vat-file-input');
        const cameraInput = document.getElementById('vat-camera-input');

        if (dropZone) {
            ['dragenter', 'dragover'].forEach(eventName => {
                dropZone.addEventListener(eventName, function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    dropZone.classList.add('bg-info', 'bg-opacity-10', 'border-primary');
                }, false);
            });

            ['dragleave', 'drop'].forEach(eventName => {
                dropZone.addEventListener(eventName, function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    dropZone.classList.remove('bg-info', 'bg-opacity-10', 'border-primary');
                }, false);
            });

            dropZone.addEventListener('drop', function(e) {
                const dt = e.dataTransfer;
                if (dt && dt.files && dt.files.length) {
                    handleVatFileUpload(dt.files[0]);
                }
            }, false);
        }

        if (fileInput) {
            fileInput.addEventListener('change', function() {
                if (this.files && this.files.length) {
                    handleVatFileUpload(this.files[0]);
                }
            });
        }

        if (cameraInput) {
            cameraInput.addEventListener('change', function() {
                if (this.files && this.files.length) {
                    handleVatFileUpload(this.files[0]);
                }
            });
        }
    });
    function syncAssignedStaff(accountSelect, targetStaffSelectId, badgeId) {
        if (!accountSelect) return;
        const selectedOpt = accountSelect.options[accountSelect.selectedIndex];
        if (!selectedOpt) return;

        const staffId = selectedOpt.getAttribute('data-assigned-staff-id');
        const staffName = selectedOpt.getAttribute('data-assigned-staff-name');
        const staffSelect = document.getElementById(targetStaffSelectId);
        const badge = badgeId ? document.getElementById(badgeId) : null;

        if (!staffSelect) return;

        if (staffId && staffId !== '' && staffId !== '0') {
            let found = false;
            for (let i = 0; i < staffSelect.options.length; i++) {
                if (staffSelect.options[i].value == staffId) {
                    staffSelect.selectedIndex = i;
                    found = true;
                    break;
                }
            }

            // If not already in the select options, dynamically append and select
            if (!found && staffName) {
                const opt = new Option(staffName + ' (Account Custodian)', staffId, true, true);
                staffSelect.add(opt);
                staffSelect.value = staffId;
            }

            if (badge) {
                badge.classList.remove('d-none');
                badge.innerHTML = `<i class="fa-solid fa-wand-magic-sparkles me-1"></i>Auto-assigned: ${staffName || 'Account Custodian'}`;
            }

            // Visual pulse on the assigned staff select
            staffSelect.classList.add('border-success', 'bg-success', 'bg-opacity-10');
            setTimeout(() => {
                staffSelect.classList.remove('border-success', 'bg-success', 'bg-opacity-10');
            }, 1200);
        } else {
            if (badge) {
                badge.classList.add('d-none');
            }
        }
    }

    document.addEventListener('DOMContentLoaded', function() {
        const vatModal = document.getElementById('payVatModal');
        if (vatModal) {
            vatModal.addEventListener('shown.bs.modal', function() {
                const acc = document.getElementById('settleVatAccountSource');
                if (acc && acc.value) {
                    syncAssignedStaff(acc, 'settleVatStaffSelect', 'settleVatStaffBadge');
                }
            });
        }

        const whtModal = document.getElementById('payWithholdingModal');
        if (whtModal) {
            whtModal.addEventListener('shown.bs.modal', function() {
                const acc = document.getElementById('settleWhtAccountSource');
                if (acc && acc.value) {
                    syncAssignedStaff(acc, 'settleWhtStaffSelect', 'settleWhtStaffBadge');
                }
            });
        }
    });

    function togglePayNowVat(isChecked) {
        const fields = document.getElementById('payVatNowFields');
        const refInput = document.getElementById('settleVatPaymentRef');
        if (fields) {
            fields.style.display = isChecked ? 'flex' : 'none';
        }
        if (refInput) {
            refInput.required = isChecked;
        }
    }

    function togglePayNowWht(isChecked) {
        const fields = document.getElementById('payWhtNowFields');
        const refInput = document.getElementById('settleWhtPaymentRef');
        if (fields) {
            fields.style.display = isChecked ? 'flex' : 'none';
        }
        if (refInput) {
            refInput.required = isChecked;
        }
    }

    function togglePayNowFields(isChecked) {
        const fields = document.getElementById('payNowFields');
        const refInput = document.getElementById('settlePaymentRef');
        if (fields) {
            fields.style.display = isChecked ? 'flex' : 'none';
        }
        if (refInput) {
            refInput.required = isChecked;
        }
    }
</script>
@endsection
