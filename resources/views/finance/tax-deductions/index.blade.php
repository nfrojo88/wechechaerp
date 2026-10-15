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
                    {{-- Primary Tax Settlement Actions Group --}}
                    <div class="d-flex align-items-center gap-2 flex-wrap">
                        <div class="btn-group shadow-sm rounded-pill overflow-hidden" role="group">
                            {{-- Settle VAT (Payable to ERCA) or Credit Carried Forward --}}
                            @if(!empty($netVatDetails['is_payable']))
                                <button type="button" class="btn btn-danger text-white btn-sm px-3 py-2 fw-semibold d-inline-flex align-items-center gap-1.5" data-bs-toggle="modal" data-bs-target="#payVatModal">
                                    <i class="fa-solid fa-file-invoice-dollar"></i>
                                    <span>Settle VAT</span>
                                    <span class="badge bg-white text-danger rounded-pill ms-1 font-monospace">
                                        ETB {{ number_format($netVatDetails['payable_amount'] ?? $netVatDetails['amount_payable'] ?? abs($netVatDetails['net'] ?? 0), 2) }}
                                    </span>
                                </button>
                            @else
                                <button type="button" class="btn btn-outline-success btn-sm px-3 py-2 fw-semibold d-inline-flex align-items-center gap-1.5" disabled title="Credit carried forward to next month">
                                    <i class="fa-solid fa-arrow-turn-up"></i>
                                    <span>Credit carried forward</span>
                                    <span class="badge bg-success text-white rounded-pill ms-1 font-monospace">
                                        {{ $netVatDetails['signed_net_formatted'] ?? '0.00' }}
                                    </span>
                                </button>
                            @endif

                            {{-- Pay 3% Withholding Tax --}}
                            <button type="button" class="btn btn-danger btn-sm px-3 py-2 fw-semibold d-inline-flex align-items-center gap-1.5" data-bs-toggle="modal" data-bs-target="#payWithholdingModal">
                                <i class="fa-solid fa-hand-holding-dollar"></i>
                                <span>Pay 3% WHT</span>
                                <span class="badge bg-white text-danger rounded-pill ms-1 font-monospace">
                                    ETB {{ number_format($unsettledWhtAmount, 2) }}
                                </span>
                            </button>
                        </div>

                        {{-- Clear VAT & Withholding (Start from Zero) --}}
                        <button type="button" class="btn btn-warning text-dark btn-sm rounded-pill px-3 py-2 shadow-sm fw-bold d-inline-flex align-items-center gap-1.5" data-bs-toggle="modal" data-bs-target="#adminClearTaxModal" title="Clear VAT & Withholding for a month to start active cycle from zero (0.00 ETB)">
                            <i class="fa-solid fa-broom text-dark"></i>
                            <span>Clear Month (Start 0.00)</span>
                        </button>
                    </div>

                    {{-- Utilities Group --}}
                    <div class="d-flex align-items-center gap-2">
                        <button type="button" class="btn btn-primary btn-sm rounded-pill px-3 py-2 shadow-xs fw-semibold d-inline-flex align-items-center gap-1.5" data-bs-toggle="modal" data-bs-target="#addReceiptModal">
                            <i class="fa-solid fa-cloud-arrow-up"></i>
                            <span>+ Add Receipt (OCR)</span>
                        </button>
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
    {{-- ── ERCA Line 100 Monthly Declaration Card ─────────────────────────────── --}}
    <div class="card border-0 shadow-sm rounded-4 mb-4 bg-white overflow-hidden border-top border-4 border-primary">
        <div class="card-header bg-white border-bottom py-3 px-3 px-md-4 d-flex flex-column flex-lg-row justify-content-between align-items-start align-items-lg-center gap-3">
            <div class="d-flex align-items-center gap-3">
                <div class="p-2.5 rounded-3 bg-primary bg-opacity-10 text-primary fs-4">
                    <i class="fa-solid fa-file-shield"></i>
                </div>
                <div>
                    <div class="d-flex align-items-center gap-2 flex-wrap">
                        <h5 class="fw-bold mb-0 text-dark">ERCA Line 100 Declaration &bull; {{ $monthRange['month_en'] ?? '' }} ({{ $monthRange['month_am'] ?? '' }}) {{ $selectedEthYear }} EC</h5>
                        <span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill px-2.5 py-0.5 small fw-semibold">
                            Monthly Ethiopian Period
                        </span>
                    </div>
                    <small class="text-muted">
                        Gregorian Window: {{ $monthRange['start']->format('M d, Y') }} &ndash; {{ $monthRange['end']->format('M d, Y') }} ({{ $monthRange['days'] }} Days)
                    </small>
                </div>
            </div>

            {{-- Period Selector Form --}}
            <form method="GET" action="{{ route('finance.tax-deductions.index') }}" class="d-flex align-items-center gap-2 flex-wrap ms-lg-auto">
                <input type="hidden" name="tab" value="{{ $tab }}">
                <input type="hidden" name="cycle" value="{{ $cycle }}">
                @if(request('vat_source'))
                    <input type="hidden" name="vat_source" value="{{ request('vat_source') }}">
                @endif
                <label class="small fw-bold text-muted text-uppercase mb-0 me-1">
                    <i class="fa-regular fa-calendar-days text-primary me-1"></i>Period (EC):
                </label>
                <select name="ethiopian_month" class="form-select form-select-sm fw-semibold" style="min-width: 165px;" onchange="this.form.submit()">
                    @foreach($availableEthMonths as $mId => $m)
                        <option value="{{ $mId }}" {{ $selectedEthMonth == $mId ? 'selected' : '' }}>
                            {{ $m['label'] }}
                        </option>
                    @endforeach
                </select>
                <select name="ethiopian_year" class="form-select form-select-sm fw-semibold" style="width: 110px;" onchange="this.form.submit()">
                    @foreach($availableEthYears as $y)
                        <option value="{{ $y }}" {{ $selectedEthYear == $y ? 'selected' : '' }}>
                            {{ $y }} ዓ.ም.
                        </option>
                    @endforeach
                </select>
                <button type="submit" class="btn btn-sm btn-primary rounded-pill px-3 fw-semibold">
                    Apply
                </button>
                <button type="button" class="btn btn-sm btn-outline-warning text-dark rounded-pill px-3 fw-bold d-inline-flex align-items-center gap-1.5 shadow-2xs" onclick="openAdminClearModalForMonth({{ $selectedEthYear }}, {{ $selectedEthMonth }}, '{{ $monthRange['month_en'] ?? '' }}')" title="Clear {{ $monthRange['month_en'] ?? 'this month' }} to start from zero (0.00 ETB)">
                    <i class="fa-solid fa-broom text-warning"></i>
                    <span>Clear Month (0.00)</span>
                </button>
            </form>
        </div>

        <div class="card-body p-3 p-md-4">
            <div class="row g-3">
                {{-- 1. Sales Base | Output VAT --}}
                <div class="col-12 col-sm-6 col-xl-2">
                    <div class="p-3 rounded-4 bg-light bg-opacity-75 border h-100 d-flex flex-column justify-content-between">
                        <div>
                            <span class="text-muted small text-uppercase fw-bold d-block" style="font-size: 0.72rem;">Sales Base (ገቢ)</span>
                            <div class="fs-5 fw-bold text-dark font-monospace mt-1">ETB {{ number_format($salesBase, 2) }}</div>
                        </div>
                        <div class="mt-3 pt-2 border-top border-secondary-subtle">
                            <span class="text-muted small text-uppercase fw-bold d-block" style="font-size: 0.68rem;">Output VAT (15%)</span>
                            <span class="fw-bold text-danger font-monospace">ETB {{ number_format($outputVat, 2) }}</span>
                        </div>
                    </div>
                </div>

                {{-- 2. Purchases Base | Input VAT --}}
                <div class="col-12 col-sm-6 col-xl-2">
                    <div class="p-3 rounded-4 bg-light bg-opacity-75 border h-100 d-flex flex-column justify-content-between">
                        <div>
                            <span class="text-muted small text-uppercase fw-bold d-block" style="font-size: 0.72rem;">Purchases Base (ግዢ)</span>
                            <div class="fs-5 fw-bold text-dark font-monospace mt-1">ETB {{ number_format($purchasesBase, 2) }}</div>
                        </div>
                        <div class="mt-3 pt-2 border-top border-secondary-subtle">
                            <span class="text-muted small text-uppercase fw-bold d-block" style="font-size: 0.68rem;">Input VAT (Creditable)</span>
                            <span class="fw-bold text-success font-monospace">ETB {{ number_format($inputVat, 2) }}</span>
                        </div>
                    </div>
                </div>

                {{-- 3. Opening Credit --}}
                <div class="col-12 col-sm-6 col-xl-2">
                    <div class="p-3 rounded-4 bg-light bg-opacity-75 border h-100 d-flex flex-column justify-content-between">
                        <div>
                            <span class="text-muted small text-uppercase fw-bold d-block" style="font-size: 0.72rem;">Opening Credit</span>
                            <span class="text-muted d-block" style="font-size: 0.68rem;">የቀደመ ወር ተዘዋዋሪ ክሬዲት</span>
                            <div class="fs-5 fw-bold text-info font-monospace mt-1">+ ETB {{ number_format($openingCredit, 2) }}</div>
                        </div>
                        <div class="mt-3 pt-2 border-top border-secondary-subtle">
                            <span class="badge bg-info-subtle text-info border border-info-subtle rounded-pill small px-2 py-0.5" style="font-size: 0.68rem;">
                                From prev month
                            </span>
                        </div>
                    </div>
                </div>

                {{-- 4. Signed Net VAT --}}
                <div class="col-12 col-sm-6 col-xl-2">
                    <div class="p-3 rounded-4 border h-100 d-flex flex-column justify-content-between {{ !empty($netVatDetails['is_payable']) ? 'bg-danger bg-opacity-10 border-danger' : (!empty($netVatDetails['is_credit']) ? 'bg-success bg-opacity-10 border-success' : 'bg-light') }}">
                        <div>
                            <span class="text-muted small text-uppercase fw-bold d-block" style="font-size: 0.72rem;">Signed Net VAT</span>
                            <span class="text-muted d-block" style="font-size: 0.68rem;">የተጣራ ቫት (Input - Output)</span>
                            <div class="fs-4 fw-bold font-monospace mt-1 {{ $netVatDetails['text_class'] ?? 'text-dark' }}">
                                {{ $netVatDetails['signed_net_formatted'] ?? '0.00' }}
                            </div>
                        </div>
                        <div class="mt-2 pt-2 border-top border-secondary-subtle">
                            <span class="badge {{ $netVatDetails['badge_class'] ?? 'bg-secondary' }} rounded-pill small px-2 py-0.5" style="font-size: 0.68rem;">
                                {{ $netVatDetails['label'] ?? '' }}
                            </span>
                        </div>
                    </div>
                </div>

                {{-- 5. Withholding Tax (3%) --}}
                <div class="col-12 col-sm-6 col-xl-2">
                    <div class="p-3 rounded-4 bg-light bg-opacity-75 border h-100 d-flex flex-column justify-content-between">
                        <div>
                            <span class="text-muted small text-uppercase fw-bold d-block" style="font-size: 0.72rem;">Withholding Tax (3%)</span>
                            <span class="text-muted d-block" style="font-size: 0.68rem;">የተቀነሰ ቅድመ ግብር</span>
                            <div class="fs-5 fw-bold text-danger font-monospace mt-1">- ETB {{ number_format($monthWithholding, 2) }}</div>
                        </div>
                        <div class="mt-3 pt-2 border-top border-secondary-subtle">
                            <span class="badge bg-warning-subtle text-dark border border-warning-subtle rounded-pill small px-2 py-0.5" style="font-size: 0.68rem;">
                                Separate Line (Not Net)
                            </span>
                        </div>
                    </div>
                </div>

                {{-- 6. Net Disbursed --}}
                <div class="col-12 col-sm-6 col-xl-2">
                    <div class="p-3 rounded-4 bg-light bg-opacity-75 border h-100 d-flex flex-column justify-content-between">
                        <div>
                            <span class="text-muted small text-uppercase fw-bold d-block" style="font-size: 0.72rem;">Net Disbursed</span>
                            <span class="text-muted d-block" style="font-size: 0.68rem;">የተጣራ የተከፈለ ወጪ</span>
                            <div class="fs-5 fw-bold text-success font-monospace mt-1">ETB {{ number_format($monthNetDisbursed, 2) }}</div>
                        </div>
                        <div class="mt-3 pt-2 border-top border-secondary-subtle">
                            <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill small px-2 py-0.5" style="font-size: 0.68rem;">
                                Purchases Base - WHT
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ── 12-Month Trend Table (Meskerem to Pagume) ────────────────────────────── --}}
    <div class="card border-0 shadow-sm rounded-4 mb-4 bg-white overflow-hidden">
        <div class="card-header bg-white border-bottom py-3 px-3 px-md-4 d-flex justify-content-between align-items-center">
            <div class="d-flex align-items-center gap-2">
                <div class="p-2 rounded-3 bg-primary bg-opacity-10 text-primary fs-5">
                    <i class="fa-solid fa-chart-line"></i>
                </div>
                <div>
                    <h6 class="fw-bold mb-0 text-dark">Ethiopian Year {{ $selectedEthYear }} EC VAT Trend (Meskerem &ndash; Pagume)</h6>
                    <small class="text-muted">13-Month Output, Input, Opening Credit, and Signed Net reconciliation</small>
                </div>
            </div>
            <span class="badge bg-light text-muted border rounded-pill px-3 py-1.5 font-monospace">
                {{ $selectedEthYear }} ዓ.ም.
            </span>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 text-nowrap">
                <thead class="bg-light-subtle text-muted text-uppercase fw-bold" style="font-size: 0.72rem; letter-spacing: 0.5px;">
                    <tr>
                        <th class="ps-4 py-3">Month (EC)</th>
                        <th class="py-3">Gregorian Window</th>
                        <th class="py-3 text-end text-danger">Output VAT (15%)</th>
                        <th class="py-3 text-end text-success">Input VAT (Creditable)</th>
                        <th class="py-3 text-end text-info">Opening Credit</th>
                        <th class="py-3 text-end text-dark">Signed Net VAT</th>
                        <th class="py-3 text-center">Status</th>
                        <th class="py-3 text-center pe-4">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    @foreach($monthlyTrend as $mNum => $mTrend)
                        <tr class="{{ $mTrend['is_current'] ? 'table-primary bg-primary bg-opacity-10 fw-semibold' : '' }}">
                            <td class="ps-4">
                                <span class="fw-bold font-monospace text-dark">{{ $mNum }}.</span>
                                <span class="fw-bold {{ $mTrend['is_current'] ? 'text-primary' : 'text-dark' }}">{{ $mTrend['month_name'] }}</span>
                                <small class="text-muted">({{ $mTrend['month_am'] }})</small>
                                @if($mTrend['is_current'])
                                    <span class="badge bg-primary text-white rounded-pill px-2 py-0.5 ms-1 small" style="font-size: 0.65rem;">Selected</span>
                                @endif
                            </td>
                            <td class="small text-muted font-monospace">
                                {{ $mTrend['start_date'] }} &rarr; {{ $mTrend['end_date'] }}
                            </td>
                            <td class="text-end font-monospace text-danger">
                                ETB {{ number_format($mTrend['output_vat'], 2) }}
                            </td>
                            <td class="text-end font-monospace text-success">
                                ETB {{ number_format($mTrend['input_vat'], 2) }}
                            </td>
                            <td class="text-end font-monospace text-info">
                                + ETB {{ number_format($mTrend['opening_credit'], 2) }}
                            </td>
                            <td class="text-end font-monospace fw-bold {{ $mTrend['text_class'] }}">
                                {{ $mTrend['signed_net_formatted'] }}
                            </td>
                            <td class="text-center">
                                <span class="badge {{ $mTrend['badge_class'] }} rounded-pill px-2.5 py-1 small">
                                    {{ $mTrend['label'] }}
                                </span>
                            </td>
                            <td class="text-center pe-4">
                                <div class="d-inline-flex align-items-center gap-1.5">
                                    <button type="button" 
                                            class="btn btn-sm btn-primary rounded-pill px-3 py-1 small d-inline-flex align-items-center gap-1.5 shadow-xs fw-semibold"
                                            id="btn-drawer-{{ $mNum }}"
                                            data-year="{{ $selectedEthYear }}"
                                            data-month="{{ $mNum }}"
                                            data-name-en="{{ $mTrend['month_name'] }}"
                                            data-name-am="{{ $mTrend['month_am'] }}"
                                            onclick="toggleMonthInlineDrawer(this.dataset.year, this.dataset.month, this.dataset.nameEn, this.dataset.nameAm)"
                                            title="View detailed transaction movements for {{ $mTrend['month_name'] }} directly in page">
                                        <i class="fa-solid fa-receipt" id="icon-drawer-{{ $mNum }}"></i>
                                        <span id="text-drawer-{{ $mNum }}">Movements</span>
                                    </button>
                                    <button type="button" 
                                            class="btn btn-sm btn-outline-secondary rounded-pill px-2 py-1 small"
                                            data-year="{{ $selectedEthYear }}"
                                            data-month="{{ $mNum }}"
                                            data-name-en="{{ $mTrend['month_name'] }}"
                                            data-name-am="{{ $mTrend['month_am'] }}"
                                            onclick="openMonthMovementsModal(this.dataset.year, this.dataset.month, this.dataset.nameEn, this.dataset.nameAm)"
                                            title="Open in Fullscreen Dialog">
                                        <i class="fa-solid fa-up-right-from-square"></i>
                                    </button>
                                    <a href="{{ route('finance.tax-deductions.index', array_merge(request()->query(), ['ethiopian_year' => $selectedEthYear, 'ethiopian_month' => $mNum])) }}" 
                                       class="btn btn-sm {{ $mTrend['is_current'] ? 'btn-outline-primary active' : 'btn-outline-secondary' }} rounded-pill px-2.5 py-1 small" 
                                       title="Filter whole page to this month">
                                        <i class="fa-solid fa-filter"></i>
                                    </a>
                                    <button type="button" 
                                            class="btn btn-sm btn-outline-warning text-dark rounded-pill px-2 py-1 small" 
                                            onclick="openAdminClearModalForMonth({{ $selectedEthYear }}, {{ $mNum }}, '{{ $mTrend['month_name'] }}')"
                                            title="Clear {{ $mTrend['month_name'] }} to start from zero">
                                        <i class="fa-solid fa-broom"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        {{-- Expandable Inline Drawer Row for this Month --}}
                        <tr id="trend-drawer-row-{{ $mNum }}" class="trend-drawer-row d-none" style="background-color: #f8fafc;">
                            <td colspan="8" class="p-0 border-top-0 border-bottom">
                                <div id="trend-drawer-content-{{ $mNum }}" class="p-2 p-md-3">
                                    {{-- Dynamically loaded inline movement details --}}
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
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
                            <button type="button" class="btn btn-sm btn-primary fw-bold shadow-xs" data-bs-toggle="modal" data-bs-target="#addReceiptModal">
                                <i class="fa-solid fa-cloud-arrow-up me-1"></i>+ Add Receipt (OCR)
                            </button>
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
                                        <label class="form-label small fw-semibold text-dark mb-1">
                                            <i class="fa-regular fa-calendar-days text-primary me-1"></i>Receipt Date (Optional override)
                                        </label>
                                        <input type="date" id="vat-receipt-date-input" class="form-control form-control-sm" value="{{ now()->format('Y-m-d') }}">
                                        <small class="text-muted" style="font-size:0.68rem;">Will save for this date, or auto-detect from OCR</small>
                                    </div>
                                    <div class="mb-2">
                                        <label class="form-label small fw-semibold text-dark mb-1">Tag Project (Optional)</label>
                                        <select id="vat-project-select" class="form-select form-select-sm">
                                            <option value="">-- No Project (General Company Expense) --</option>
                                            @foreach($ocrProjects ?? [] as $proj)
                                                <option value="{{ $proj->id }}">{{ $proj->name }}@if(!empty($proj->code)) ({{ $proj->code }})@endif @if(!empty($proj->status) && $proj->status !== 'active') [{{ ucfirst($proj->status) }}]@endif</option>
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
                            <select name="ethiopian_month" class="form-select" onchange="this.form.submit()">
                                @foreach($availableEthMonths as $mId => $m)
                                    <option value="{{ $mId }}" {{ $selectedEthMonth == $mId ? 'selected' : '' }}>
                                        {{ $m['label'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-6 col-md-3">
                            <select name="ethiopian_year" class="form-select" onchange="this.form.submit()">
                                @foreach($availableEthYears as $y)
                                    <option value="{{ $y }}" {{ $selectedEthYear == $y ? 'selected' : '' }}>
                                        {{ $y }} ዓ.ም.
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-12 col-md-2 d-flex gap-1.5">
                            <button type="submit" class="btn btn-primary flex-fill" title="Apply Filter">
                                <i class="fa-solid fa-filter me-1"></i>Filter
                            </button>
                            @if(request('search') || request('ethiopian_month') || request('ethiopian_year'))
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
                                        {{ $rcp && $rcp->receipt_date ? \App\Services\EthiopianDate::formatDateEc($rcp->receipt_date, 'DD/MM/YYYY EC') : '—' }}
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
                            <select name="ethiopian_month" class="form-select" onchange="this.form.submit()">
                                @foreach($availableEthMonths as $mId => $m)
                                    <option value="{{ $mId }}" {{ $selectedEthMonth == $mId ? 'selected' : '' }}>
                                        {{ $m['label'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-6 col-md-2">
                            <select name="ethiopian_year" class="form-select" onchange="this.form.submit()">
                                @foreach($availableEthYears as $y)
                                    <option value="{{ $y }}" {{ $selectedEthYear == $y ? 'selected' : '' }}>
                                        {{ $y }} ዓ.ም.
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-12 col-md-1 d-flex gap-1.5">
                            <button type="submit" class="btn btn-primary flex-fill" title="Apply Filter">
                                <i class="fa-solid fa-filter"></i>
                            </button>
                            @if(request('search') || request('category') || request('vat_type') || request('ethiopian_month') || request('ethiopian_year'))
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
                                        <div class="small text-muted">{{ $item->created_at ? \App\Services\EthiopianDate::formatDateEc($item->created_at, 'DD/MM/YYYY EC') : '—' }}</div>
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

{{-- ── MODAL: Add Receipt & Create Tax Record via AI OCR ────────────────────────────── --}}
<div class="modal fade" id="addReceiptModal" tabindex="-1" aria-labelledby="addReceiptModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable my-3">
        <form id="addReceiptForm" enctype="multipart/form-data" class="modal-content border-0 shadow-lg rounded-4 overflow-hidden" style="max-height: calc(100vh - 2rem);">
            @csrf
            <input type="hidden" name="file_path" id="add_rcpt_file_path" value="">

            <div class="modal-header bg-primary text-white py-3 px-4 flex-shrink-0">
                <div class="d-flex align-items-center gap-2">
                    <div class="bg-white bg-opacity-20 p-2 rounded-circle text-white d-flex align-items-center justify-content-center" style="width:38px; height:38px;">
                        <i class="fa-solid fa-receipt fs-5"></i>
                    </div>
                    <div>
                        <h5 class="modal-title fw-bold mb-0 text-white" id="addReceiptModalLabel">
                            Add Receipt Slip &amp; Tax Record (AI OCR)
                        </h5>
                        <small class="text-white-50">Upload or capture receipt slip &bull; AI auto-detects date &amp; amounts &bull; Adds to ledger for that date</small>
                    </div>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body p-4" style="overflow-y: auto; max-height: calc(100vh - 180px); overscroll-behavior: contain; -webkit-overflow-scrolling: touch;">
                {{-- ── OCR UPLOAD / SCAN BOX ── --}}
                <div class="card border-2 border-dashed border-primary border-opacity-50 bg-primary bg-opacity-10 rounded-3 mb-4 shadow-xs" id="addRcptDropzone" style="transition: all 0.2s ease;">
                    <div class="card-body p-4 text-center">
                        <div class="mb-2">
                            <span class="d-inline-flex align-items-center justify-content-center bg-primary text-white rounded-circle shadow-xs" style="width: 48px; height: 48px;">
                                <i class="fa-solid fa-cloud-arrow-up fs-4"></i>
                            </span>
                        </div>
                        <h6 class="fw-bold text-dark mb-1">
                            Drag &amp; Drop Receipt Slip Here, or <span class="text-primary text-decoration-underline cursor-pointer" onclick="document.getElementById('add_rcpt_file_input').click();">Browse</span>
                        </h6>
                        <p class="text-muted small mb-3" style="font-size:0.75rem;">
                            Supports JPG, PNG, WEBP and PDF files up to 25MB &bull; Multi-engine AI OCR extracts TIN, FS#, MRC, Date &amp; VAT
                        </p>

                        <input type="file" name="receipt_file" id="add_rcpt_file_input" class="d-none" accept="image/jpeg,image/png,image/webp,application/pdf" onchange="if(this.files.length) handleModalReceiptScan(this.files[0])">
                        <input type="file" id="add_rcpt_camera_input" class="d-none" accept="image/*" capture="environment" onchange="if(this.files.length) handleModalReceiptScan(this.files[0])">

                        <div class="d-flex justify-content-center gap-2 flex-wrap">
                            <button type="button" class="btn btn-primary btn-sm fw-semibold rounded-pill px-3 shadow-xs" onclick="document.getElementById('add_rcpt_file_input').click();">
                                <i class="fa-solid fa-folder-open me-1"></i>Choose Slip
                            </button>
                            <button type="button" class="btn btn-outline-primary btn-sm fw-semibold rounded-pill px-3 shadow-xs" onclick="document.getElementById('add_rcpt_camera_input').click();">
                                <i class="fa-solid fa-camera me-1"></i>Take Photo
                            </button>
                        </div>

                        {{-- OCR Scanning Progress & Status Alert --}}
                        <div id="addRcptOcrStatus" class="mt-3 d-none alert alert-info py-2 px-3 small mb-0 rounded-3 text-start"></div>
                    </div>
                </div>

                {{-- ── FORM FIELDS: REVIEW & CUSTOMIZE ── --}}
                <div class="row g-3">
                    {{-- TARGET RECEIPT DATE --}}
                    <div class="col-md-6">
                        <label class="form-label small fw-bold text-dark mb-1">
                            <i class="fa-regular fa-calendar-days text-primary me-1"></i>Receipt Date <span class="text-danger">*</span>
                        </label>
                        <input type="date" name="receipt_date" id="add_rcpt_date" class="form-control fw-bold border-primary-subtle bg-light" value="{{ now()->format('Y-m-d') }}" required>
                        <div class="form-text small text-muted" style="font-size:0.72rem;">
                            Record will be booked and ledgered for this exact date.
                        </div>
                    </div>

                    {{-- TAX TREATMENT --}}
                    <div class="col-md-6">
                        <label class="form-label small fw-bold text-dark mb-1">
                            <i class="fa-solid fa-percent text-info me-1"></i>Tax Deduction Type <span class="text-danger">*</span>
                        </label>
                        <select name="tax_type" id="add_rcpt_tax_type" class="form-select fw-semibold" onchange="onTaxTypeChange()">
                            <option value="vat" selected>15% VAT Only (ERCA Line 100)</option>
                            <option value="withholding">3% Withholding Tax Only</option>
                            <option value="both">Both 15% VAT &amp; 3% Withholding</option>
                        </select>
                    </div>

                    {{-- FS NUMBER --}}
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold text-dark mb-1">
                            <i class="fa-solid fa-receipt text-secondary me-1"></i>Fiscal Slip No. (FS #) <span class="text-danger">*</span>
                        </label>
                        <input type="text" name="fs_no" id="add_rcpt_fs_no" class="form-control text-uppercase" placeholder="e.g. FS00003898" required>
                    </div>

                    {{-- MRC --}}
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold text-dark mb-1">
                            <i class="fa-solid fa-cash-register text-secondary me-1"></i>Machine Reg. Code (MRC)
                        </label>
                        <input type="text" name="mrc_no" id="add_rcpt_mrc_no" class="form-control text-uppercase" placeholder="e.g. DDB0000032">
                    </div>

                    {{-- SELLER TIN --}}
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold text-dark mb-1">
                            <i class="fa-solid fa-id-card text-secondary me-1"></i>Seller / Supplier TIN <span class="text-danger">*</span>
                        </label>
                        <input type="text" name="vendor_tin" id="add_rcpt_vendor_tin" class="form-control" placeholder="10-digit TIN (e.g. 0005201889)" required>
                    </div>

                    {{-- SELLER NAME --}}
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold text-dark mb-1">
                            <i class="fa-solid fa-shop text-secondary me-1"></i>Seller / Supplier Business Name
                        </label>
                        <input type="text" name="vendor_name" id="add_rcpt_vendor_name" class="form-control" placeholder="e.g. General Hardware Trading">
                    </div>

                    {{-- PURCHASER TIN --}}
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold text-dark mb-1">
                            <i class="fa-solid fa-building text-secondary me-1"></i>Purchaser TIN (Wechecha)
                        </label>
                        <input type="text" name="buyer_tin" id="add_rcpt_buyer_tin" class="form-control" value="38480010">
                    </div>

                    {{-- ── FINANCIAL CORE & ERCA LINE 100 BREAKDOWN ── --}}
                    <div class="col-12">
                        <div class="card border border-success border-opacity-25 bg-success bg-opacity-10 rounded-3 p-3 shadow-xs" id="add_rcpt_financials_card">
                            <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">
                                <div>
                                    <span class="small fw-bold text-success text-uppercase d-block" style="font-size:0.75rem; letter-spacing:0.5px;">
                                        <i class="fa-solid fa-file-invoice-dollar me-1"></i>ERCA Purchase Type &amp; Financials (Line 100)
                                    </span>
                                    <small class="text-muted" style="font-size:0.68rem;">Auto-scanned &amp; calculated from fiscal receipt</small>
                                </div>
                                <div class="d-flex align-items-center gap-2">
                                    <button type="button" class="btn btn-xs btn-outline-success fw-bold rounded-pill px-2.5 py-1 shadow-xs d-inline-flex align-items-center gap-1" onclick="document.getElementById('add_rcpt_file_input').click();" title="Scan receipt slip to auto-fill these fields">
                                        <i class="fa-solid fa-camera-viewfinder"></i>
                                        <span>Scan Receipt to Fill</span>
                                    </button>
                                    <span class="badge bg-success text-white rounded-pill px-2.5 py-1" style="font-size:0.68rem;" id="financials_formula_badge">
                                        Base + 15% VAT = Total
                                    </span>
                                </div>
                            </div>

                            <div class="row g-2">
                                {{-- ERCA PURCHASE TYPE --}}
                                <div class="col-md-3">
                                    <label class="form-label small fw-bold text-dark mb-1">
                                        <i class="fa-solid fa-tag text-success me-1"></i>ERCA Purchase Type <span class="text-danger">*</span>
                                    </label>
                                    <select name="purchase_type" id="add_rcpt_purchase_type" class="form-select form-select-sm fw-bold border-success-subtle">
                                        <option value="PUR_GD" selected>PUR_GD (Goods / Materials - 3)</option>
                                        <option value="PUR_SR">PUR_SR (Services - 4)</option>
                                    </select>
                                    <small class="text-muted" style="font-size:0.68rem;">Auto-detected from receipt</small>
                                </div>

                                {{-- TOTAL AMOUNT --}}
                                <div class="col-md-3">
                                    <label class="form-label small fw-bold text-dark mb-1">
                                        <i class="fa-solid fa-coins text-success me-1"></i>Total Amount (ETB) <span class="text-danger">*</span>
                                    </label>
                                    <input type="number" step="0.01" name="total_amount" id="add_rcpt_total_amount" class="form-control form-control-sm fw-bold fs-6 text-success border-success" placeholder="0.00" required oninput="onTotalAmountChange()">
                                    <small class="text-muted" style="font-size:0.68rem;">Cash paid / Grand total</small>
                                </div>

                                {{-- BASE SUBTOTAL --}}
                                <div class="col-md-3">
                                    <label class="form-label small fw-bold text-dark mb-1">
                                        Base Subtotal (excl. VAT)
                                    </label>
                                    <input type="number" step="0.01" name="subtotal" id="add_rcpt_subtotal" class="form-control form-control-sm fw-semibold" placeholder="0.00" oninput="onSubtotalChange()">
                                    <small class="text-muted" style="font-size:0.68rem;">Taxable value before VAT</small>
                                </div>

                                {{-- 15% VAT AMOUNT --}}
                                <div class="col-md-3">
                                    <label class="form-label small fw-bold text-dark mb-1">
                                        15% VAT Amount (ETB)
                                    </label>
                                    <input type="number" step="0.01" name="vat_amount" id="add_rcpt_vat_amount" class="form-control form-control-sm fw-semibold" placeholder="0.00" oninput="onVatAmountChange()">
                                    <small class="text-muted" style="font-size:0.68rem;">15% VAT from slip</small>
                                </div>

                                {{-- WITHHOLDING AMOUNT --}}
                                <div class="col-md-3" id="add_rcpt_wht_wrapper" style="display: none;">
                                    <label class="form-label small fw-bold text-dark mb-1">
                                        3% Withholding Amount (ETB)
                                    </label>
                                    <input type="number" step="0.01" name="withholding_amount" id="add_rcpt_withholding_amount" class="form-control form-control-sm" placeholder="0.00">
                                    <small class="text-muted" style="font-size:0.68rem;">3% of base subtotal</small>
                                </div>
                            </div>

                            {{-- Real-time Extracted Financials Status Feedback --}}
                            <div id="add_rcpt_financials_status" class="mt-2.5 p-2 rounded-2 bg-white bg-opacity-80 border border-success border-opacity-50 small d-none text-dark">
                                <div class="d-flex align-items-center justify-content-between flex-wrap gap-1">
                                    <span id="add_rcpt_financials_status_text">
                                        <i class="fa-solid fa-circle-check text-success me-1"></i>ERCA Financials auto-filled from scanned slip.
                                    </span>
                                    <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill font-monospace" id="add_rcpt_financials_calc_badge">
                                        15% VAT Verified
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- PROJECT --}}
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold text-dark mb-1">
                            <i class="fa-solid fa-diagram-project text-secondary me-1"></i>Project / Site
                        </label>
                        <select name="project_id" id="add_rcpt_project_id" class="form-select">
                            <option value="">Select Project (Optional)...</option>
                            @foreach($ocrProjects ?? [] as $proj)
                                <option value="{{ $proj->id }}">
                                    {{ $proj->name }}@if(!empty($proj->code)) ({{ $proj->code }})@endif @if(!empty($proj->status) && $proj->status !== 'active') [{{ ucfirst($proj->status) }}]@endif
                                </option>
                            @endforeach
                        </select>
                        <small class="text-muted" style="font-size:0.68rem;">Showing all company projects</small>
                    </div>

                    {{-- CATEGORY --}}
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold text-dark mb-1">
                            <i class="fa-solid fa-folder-tree text-secondary me-1"></i>Category
                        </label>
                        <select name="category" id="add_rcpt_category" class="form-select" onchange="onCategoryChange()">
                            <option value="material" selected>Material / Supply</option>
                            <option value="fuel">Fuel &amp; Oil</option>
                            <option value="equipment">Equipment &amp; Tools</option>
                            <option value="subcontractor">Subcontractor</option>
                            <option value="machinery">Machinery Rental</option>
                            <option value="office">Office &amp; Admin</option>
                            <option value="spare_parts">Spare Parts</option>
                            <option value="other">Other</option>
                        </select>
                    </div>

                    {{-- DESCRIPTION --}}
                    <div class="col-12">
                        <label class="form-label small fw-semibold text-dark mb-1">
                            Particulars / Description
                        </label>
                        <input type="text" name="description" id="add_rcpt_description" class="form-control" placeholder="e.g. Cement, rebar, or machine maintenance invoice">
                    </div>
                </div>
            </div>

            <div class="modal-footer bg-light border-0 py-3 px-4 d-flex justify-content-between flex-shrink-0">
                <button type="button" class="btn btn-secondary rounded-pill px-3" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary fw-bold rounded-pill px-4 shadow-xs" id="btnAddRcptSubmit">
                    <i class="fa-solid fa-cloud-arrow-up me-1"></i>Save &amp; Add to Ledger
                </button>
            </div>
        </form>
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

                    {{-- Ethiopian Month & Year Selector --}}
                    <div class="mb-3 p-3 bg-light rounded-3 border">
                        <label class="form-label small fw-bold text-uppercase text-dark d-block mb-1" style="font-size:0.72rem; letter-spacing:0.5px;">
                            <i class="fa-regular fa-calendar-days text-primary me-1"></i>Ethiopian Tax Period (ወርና ዓ.ም.)
                        </label>
                        <div class="row g-2">
                            <div class="col-7">
                                <select name="ethiopian_month" id="export_eth_month" class="form-select form-select-sm fw-semibold">
                                    @foreach($availableEthMonths as $mId => $m)
                                        <option value="{{ $mId }}" {{ $selectedEthMonth == $mId ? 'selected' : '' }}>
                                            {{ $m['label'] }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-5">
                                <select name="ethiopian_year" id="export_eth_year" class="form-select form-select-sm fw-semibold">
                                    @foreach($availableEthYears as $y)
                                        <option value="{{ $y }}" {{ $selectedEthYear == $y ? 'selected' : '' }}>
                                            {{ $y }} ዓ.ም.
                                        </option>
                                    @endforeach
                                </select>
                            </div>
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

{{-- ── 13-Month Movement & Transaction Audit Modal ────────────────────────────── --}}
<div class="modal fade" id="monthMovementsModal" tabindex="-1" aria-labelledby="monthMovementsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable my-3" style="max-width: 1240px;">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <style>
                /* Prevent CSS transform containing block trapping modals inside content-wrapper */
                .content-wrapper > .modal,
                #monthMovementsModal,
                #adminClearTaxModal {
                    animation: none !important;
                    transform: none !important;
                    z-index: 1070 !important;
                }
                .modal-backdrop {
                    z-index: 1060 !important;
                }
                #monthMovementsModal .modal-body {
                    min-height: 520px;
                    background-color: #f8fafc;
                }
                #monthMovementsModal thead.sticky-header th {
                    background-color: #f1f5f9 !important;
                    position: sticky;
                    top: 0;
                    z-index: 5;
                    box-shadow: 0 1px 2px rgba(0,0,0,0.06);
                }
                #monthMovementsModal tfoot.sticky-footer td {
                    background-color: #f8fafc !important;
                    position: sticky;
                    bottom: 0;
                    z-index: 5;
                    box-shadow: 0 -1px 2px rgba(0,0,0,0.06);
                }
                #monthMovementsModal .kpi-card {
                    border-radius: 14px;
                    background: #fff;
                    transition: transform 0.15s ease, box-shadow 0.15s ease;
                }
                .trend-drawer-row {
                    transition: all 0.2s ease-in-out;
                }
            </style>
            {{-- Modal Header --}}
            <div class="modal-header bg-dark text-white py-3 px-4 flex-shrink-0" style="background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%) !important;">
                <div class="d-flex align-items-center gap-2.5">
                    <div class="p-2 rounded-circle text-warning" style="background: rgba(245,158,11,0.2);">
                        <i class="fa-solid fa-chart-pie fs-5"></i>
                    </div>
                    <div>
                        <h5 class="modal-title fw-bold mb-0 text-white" id="mMovementsTitle">Ethiopian Month Financial Movements</h5>
                        <small class="text-white-50" id="mMovementsWindow">Gregorian Date Window &amp; Movements Summary</small>
                    </div>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <button type="button" class="btn btn-sm btn-light text-primary rounded-pill px-3 fw-semibold shadow-xs" onclick="printMonthMovements()">
                        <i class="fa-solid fa-print me-1"></i>Print Report
                    </button>
                    <a id="mMovementsFilterBtn" href="#" class="btn btn-sm btn-outline-light rounded-pill px-3 fw-semibold">
                        <i class="fa-solid fa-filter me-1"></i>Filter Ledger
                    </a>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
            </div>

            {{-- Modal Body --}}
            <div class="modal-body p-3 p-md-4">
                {{-- Spinner Loading State --}}
                <div id="mMovementsSpinner" class="text-center py-5 my-4">
                    <div class="spinner-border text-primary" style="width: 3rem; height: 3rem;" role="status"></div>
                    <div class="mt-3 fw-bold text-dark fs-6">Retrieving detailed financial movements...</div>
                    <small class="text-muted">Fetching Company Income receipts, Purchases, and Creditable Tax lines</small>
                </div>

                {{-- Error Alert --}}
                <div id="mMovementsError" class="alert alert-danger rounded-4 shadow-sm" style="display: none;">
                    <i class="fa-solid fa-triangle-exclamation me-2"></i>
                    <span id="mMovementsErrorMsg">Failed to load financial movements.</span>
                </div>

                {{-- Loaded Content Container --}}
                <div id="mMovementsContent" style="display: none;">
                    {{-- 1. Top KPI Summary Strip: 5 Balanced Columns --}}
                    <div class="row g-2.5 mb-3 row-cols-1 row-cols-sm-2 row-cols-md-3 row-cols-lg-5">
                        {{-- 1. Sales & Output VAT --}}
                        <div class="col">
                            <div class="card border-0 shadow-sm kpi-card h-100 border-top border-4 border-primary">
                                <div class="card-body p-2.5">
                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                        <span class="text-muted small text-uppercase fw-bold" style="font-size: 0.68rem;">Sales Base (ገቢ)</span>
                                        <span id="mMovementsIncomeCount" class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill" style="font-size: 0.65rem;">0 receipts</span>
                                    </div>
                                    <div class="fs-6 fw-bold text-dark font-monospace" id="mMovementsSalesBase">ETB 0.00</div>
                                    <div class="mt-1.5 pt-1.5 border-top border-secondary-subtle d-flex justify-content-between align-items-center">
                                        <span class="text-muted small" style="font-size: 0.72rem;">Output VAT (15%)</span>
                                        <span class="fw-bold text-danger font-monospace small" id="mMovementsOutputVat">ETB 0.00</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- 2. Purchases & Input VAT --}}
                        <div class="col">
                            <div class="card border-0 shadow-sm kpi-card h-100 border-top border-4 border-success">
                                <div class="card-body p-2.5">
                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                        <span class="text-muted small text-uppercase fw-bold" style="font-size: 0.68rem;">Purchases Base (ግዢ)</span>
                                        <span id="mMovementsExpenseCount" class="badge bg-success-subtle text-success border border-success-subtle rounded-pill" style="font-size: 0.65rem;">0 items</span>
                                    </div>
                                    <div class="fs-6 fw-bold text-dark font-monospace" id="mMovementsPurchasesBase">ETB 0.00</div>
                                    <div class="mt-1.5 pt-1.5 border-top border-secondary-subtle d-flex justify-content-between align-items-center">
                                        <span class="text-muted small" style="font-size: 0.72rem;">Input VAT (15%)</span>
                                        <span class="fw-bold text-success font-monospace small" id="mMovementsInputVat">ETB 0.00</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- 3. Opening Credit --}}
                        <div class="col">
                            <div class="card border-0 shadow-sm kpi-card h-100 border-top border-4 border-info">
                                <div class="card-body p-2.5">
                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                        <span class="text-muted small text-uppercase fw-bold" style="font-size: 0.68rem;">Opening Credit</span>
                                        <span class="badge bg-info-subtle text-info border border-info-subtle rounded-pill" style="font-size: 0.65rem;">Prev month</span>
                                    </div>
                                    <div class="fs-6 fw-bold text-info font-monospace" id="mMovementsOpeningCredit">+ ETB 0.00</div>
                                    <div class="mt-1.5 pt-1.5 border-top border-secondary-subtle d-flex justify-content-between align-items-center">
                                        <span class="text-muted small" style="font-size: 0.72rem;">Creditable Pool</span>
                                        <span class="fw-bold text-dark font-monospace small" id="mMovementsTotalCreditable">ETB 0.00</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- 4. Signed Net VAT --}}
                        <div class="col">
                            <div class="card border-0 shadow-sm kpi-card h-100 border-top border-4 border-warning" id="mMovementsNetCard">
                                <div class="card-body p-2.5">
                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                        <span class="text-muted small text-uppercase fw-bold" style="font-size: 0.68rem;">Signed Net VAT</span>
                                        <span id="mMovementsNetBadge" class="badge bg-secondary rounded-pill small" style="font-size: 0.65rem;">Status</span>
                                    </div>
                                    <div class="fs-6 fw-bold font-monospace" id="mMovementsSignedNet">0.00</div>
                                    <div class="mt-1.5 pt-1.5 border-top border-secondary-subtle">
                                        <small class="text-muted d-block text-truncate" id="mMovementsNetInstruction" style="font-size: 0.72rem;">Net reconciliation</small>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- 5. Withholding Tax (3%) --}}
                        <div class="col">
                            <div class="card border-0 shadow-sm kpi-card h-100 border-top border-4 border-danger">
                                <div class="card-body p-2.5">
                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                        <span class="text-muted small text-uppercase fw-bold" style="font-size: 0.68rem;">3% Withholding</span>
                                        <span class="badge bg-warning-subtle text-dark border border-warning-subtle rounded-pill" style="font-size: 0.65rem;">Separate</span>
                                    </div>
                                    <div class="fs-6 fw-bold text-danger font-monospace" id="mMovementsWht">- ETB 0.00</div>
                                    <div class="mt-1.5 pt-1.5 border-top border-secondary-subtle d-flex justify-content-between align-items-center">
                                        <span class="text-muted small" style="font-size: 0.72rem;">Net Disbursed</span>
                                        <span class="fw-bold text-success font-monospace small" id="mMovementsNetDisbursed">ETB 0.00</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- 2. Interactive Navigation Tabs --}}
                    <ul class="nav nav-pills gap-2 mb-3 bg-white p-2 rounded-4 shadow-sm border" id="monthMovementTab" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active rounded-pill fw-semibold small d-inline-flex align-items-center gap-1.5" id="pills-income-tab" data-bs-toggle="pill" data-bs-target="#pills-income" type="button" role="tab">
                                <i class="fa-solid fa-arrow-down-long text-success"></i>
                                <span>Where Income Came From (ገቢ)</span>
                                <span class="badge bg-success-subtle text-success rounded-pill ms-1" id="mTabIncomeBadge">0</span>
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link rounded-pill fw-semibold small d-inline-flex align-items-center gap-1.5" id="pills-expense-tab" data-bs-toggle="pill" data-bs-target="#pills-expense" type="button" role="tab">
                                <i class="fa-solid fa-arrow-up-long text-danger"></i>
                                <span>Purchases &amp; Paid Expenses (ግዢ)</span>
                                <span class="badge bg-danger-subtle text-danger rounded-pill ms-1" id="mTabExpenseBadge">0</span>
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link rounded-pill fw-semibold small d-inline-flex align-items-center gap-1.5" id="pills-ocr-tab" data-bs-toggle="pill" data-bs-target="#pills-ocr" type="button" role="tab">
                                <i class="fa-solid fa-receipt text-primary"></i>
                                <span>Scanned OCR Receipts</span>
                                <span class="badge bg-primary-subtle text-primary rounded-pill ms-1" id="mTabOcrBadge">0</span>
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link rounded-pill fw-semibold small d-inline-flex align-items-center gap-1.5" id="pills-audit-tab" data-bs-toggle="pill" data-bs-target="#pills-audit" type="button" role="tab">
                                <i class="fa-solid fa-scale-balanced text-dark"></i>
                                <span>ERCA Line 100 Audit Sheet</span>
                            </button>
                        </li>
                    </ul>

                    {{-- 3. Tab Contents --}}
                    <div class="tab-content" id="monthMovementTabContent">
                        {{-- ── TAB 1: INCOMES & SALES ──────────────────────────── --}}
                        <div class="tab-pane fade show active" id="pills-income" role="tabpanel">
                            <div class="card border-0 shadow-sm rounded-4 overflow-hidden bg-white">
                                <div class="card-header bg-white border-bottom py-3 px-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
                                    <div class="d-flex align-items-center gap-2">
                                        <span class="badge bg-success-subtle text-success p-2 rounded-3"><i class="fa-solid fa-hand-holding-dollar fs-6"></i></span>
                                        <div>
                                            <h6 class="fw-bold mb-0 text-dark">Company Income &amp; Sales Receipts</h6>
                                            <small class="text-muted">Sources of all company revenues, client payments, and 15% Output VAT</small>
                                        </div>
                                    </div>
                                    <div style="min-width: 250px;">
                                        <input type="text" class="form-control form-control-sm rounded-pill" placeholder="Search customer, invoice #, project..." onkeyup="filterMovementTable('mIncomeTable', this.value)">
                                    </div>
                                </div>
                                <div class="table-responsive">
                                    <table class="table table-hover align-middle mb-0" id="mIncomeTable">
                                        <thead class="sticky-header text-muted text-uppercase fw-bold" style="font-size: 0.7rem;">
                                            <tr>
                                                <th class="ps-3 py-2.5">#</th>
                                                <th class="py-2.5">Date (EC / GC)</th>
                                                <th class="py-2.5">Income # / Ref</th>
                                                <th class="py-2.5">Customer / Payer</th>
                                                <th class="py-2.5">Project</th>
                                                <th class="py-2.5">Receiving Account</th>
                                                <th class="py-2.5 text-end">Sales Base</th>
                                                <th class="py-2.5 text-end text-danger">Output VAT (15%)</th>
                                                <th class="py-2.5 text-end text-success pe-3">Gross Received</th>
                                            </tr>
                                        </thead>
                                        <tbody id="mIncomeTableBody" class="divide-y">
                                            {{-- Rows inserted via JS --}}
                                        </tbody>
                                        <tfoot class="sticky-footer fw-bold border-top">
                                            <tr>
                                                <td colspan="6" class="ps-3 py-2 text-uppercase small text-muted">Total Sales &amp; Output VAT</td>
                                                <td class="text-end py-2 font-monospace text-dark" id="mIncomeTotalBase">ETB 0.00</td>
                                                <td class="text-end py-2 font-monospace text-danger" id="mIncomeTotalVat">ETB 0.00</td>
                                                <td class="text-end py-2 font-monospace text-success pe-3" id="mIncomeTotalGross">ETB 0.00</td>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>
                            </div>
                        </div>

                        {{-- ── TAB 2: PURCHASES & EXPENSES ─────────────────────── --}}
                        <div class="tab-pane fade" id="pills-expense" role="tabpanel">
                            <div class="card border-0 shadow-sm rounded-4 overflow-hidden bg-white">
                                <div class="card-header bg-white border-bottom py-3 px-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
                                    <div class="d-flex align-items-center gap-2">
                                        <span class="badge bg-danger-subtle text-danger p-2 rounded-3"><i class="fa-solid fa-file-invoice fs-6"></i></span>
                                        <div>
                                            <h6 class="fw-bold mb-0 text-dark">Purchases, Petty Cash &amp; Paid Expense Vouchers</h6>
                                            <small class="text-muted">Creditable business expenditures, 15% Input VAT, and 3% Withholding Deductions</small>
                                        </div>
                                    </div>
                                    <div style="min-width: 250px;">
                                        <input type="text" class="form-control form-control-sm rounded-pill" placeholder="Search payee, voucher #, category..." onkeyup="filterMovementTable('mExpenseTable', this.value)">
                                    </div>
                                </div>
                                <div class="table-responsive">
                                    <table class="table table-hover align-middle mb-0" id="mExpenseTable">
                                        <thead class="sticky-header text-muted text-uppercase fw-bold" style="font-size: 0.7rem;">
                                            <tr>
                                                <th class="ps-3 py-2.5">#</th>
                                                <th class="py-2.5">Date (EC / GC)</th>
                                                <th class="py-2.5">Voucher / Ref #</th>
                                                <th class="py-2.5">Payee / Supplier</th>
                                                <th class="py-2.5">Category &amp; Project</th>
                                                <th class="py-2.5">Disbursement Account</th>
                                                <th class="py-2.5 text-end">Purchases Base</th>
                                                <th class="py-2.5 text-end text-success">Input VAT (15%)</th>
                                                <th class="py-2.5 text-end text-danger">3% WHT</th>
                                                <th class="py-2.5 text-end text-dark pe-3">Net Disbursed</th>
                                            </tr>
                                        </thead>
                                        <tbody id="mExpenseTableBody" class="divide-y">
                                            {{-- Rows inserted via JS --}}
                                        </tbody>
                                        <tfoot class="sticky-footer fw-bold border-top">
                                            <tr>
                                                <td colspan="6" class="ps-3 py-2 text-uppercase small text-muted">Total Purchases &amp; Deductions</td>
                                                <td class="text-end py-2 font-monospace text-dark" id="mExpenseTotalBase">ETB 0.00</td>
                                                <td class="text-end py-2 font-monospace text-success" id="mExpenseTotalVat">ETB 0.00</td>
                                                <td class="text-end py-2 font-monospace text-danger" id="mExpenseTotalWht">- ETB 0.00</td>
                                                <td class="text-end py-2 font-monospace text-dark pe-3" id="mExpenseTotalNet">ETB 0.00</td>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>
                            </div>
                        </div>

                        {{-- ── TAB 3: SCANNED OCR RECEIPTS ─────────────────────── --}}
                        <div class="tab-pane fade" id="pills-ocr" role="tabpanel">
                            <div class="card border-0 shadow-sm rounded-4 overflow-hidden bg-white">
                                <div class="card-header bg-white border-bottom py-3 px-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
                                    <div class="d-flex align-items-center gap-2">
                                        <span class="badge bg-primary-subtle text-primary p-2 rounded-3"><i class="fa-solid fa-camera-retro fs-6"></i></span>
                                        <div>
                                            <h6 class="fw-bold mb-0 text-dark">Scanned OCR Receipts (ERCA Cash Register Invoices)</h6>
                                            <small class="text-muted">Itemized receipts with verified Vendor TIN, FS Numbers, and Machine MRC</small>
                                        </div>
                                    </div>
                                    <div style="min-width: 250px;">
                                        <input type="text" class="form-control form-control-sm rounded-pill" placeholder="Search vendor, FS #, item..." onkeyup="filterMovementTable('mOcrTable', this.value)">
                                    </div>
                                </div>
                                <div class="table-responsive">
                                    <table class="table table-hover align-middle mb-0" id="mOcrTable">
                                        <thead class="sticky-header text-muted text-uppercase fw-bold" style="font-size: 0.7rem;">
                                            <tr>
                                                <th class="ps-3 py-2.5">#</th>
                                                <th class="py-2.5">Date (EC / GC)</th>
                                                <th class="py-2.5">FS # / Receipt #</th>
                                                <th class="py-2.5">Vendor Name &amp; TIN</th>
                                                <th class="py-2.5">Project / Category</th>
                                                <th class="py-2.5">Item Description</th>
                                                <th class="py-2.5 text-end">Base Value</th>
                                                <th class="py-2.5 text-end text-success">VAT (15%)</th>
                                                <th class="py-2.5 text-end text-dark pe-3">Value After VAT</th>
                                            </tr>
                                        </thead>
                                        <tbody id="mOcrTableBody" class="divide-y">
                                            {{-- Rows inserted via JS --}}
                                        </tbody>
                                        <tfoot class="sticky-footer fw-bold border-top">
                                            <tr>
                                                <td colspan="6" class="ps-3 py-2 text-uppercase small text-muted">Total Scanned Invoices</td>
                                                <td class="text-end py-2 font-monospace text-dark" id="mOcrTotalBase">ETB 0.00</td>
                                                <td class="text-end py-2 font-monospace text-success" id="mOcrTotalVat">ETB 0.00</td>
                                                <td class="text-end py-2 font-monospace text-dark pe-3" id="mOcrTotalAfter">ETB 0.00</td>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>
                            </div>
                        </div>

                        {{-- ── TAB 4: ERCA LINE 100 AUDIT RECONCILIATION ────────── --}}
                        <div class="tab-pane fade" id="pills-audit" role="tabpanel">
                            <div class="card border-0 shadow-sm rounded-4 overflow-hidden bg-white p-4">
                                <div class="d-flex align-items-center justify-content-between mb-4 pb-3 border-bottom">
                                    <div>
                                        <h5 class="fw-bold mb-1 text-dark">
                                            <i class="fa-solid fa-stamp text-primary me-2"></i>ERCA VAT Line 100 Monthly Declaration Audit
                                        </h5>
                                        <p class="text-muted small mb-0">Official net tax reconciliation formula for Ethiopian Revenue and Customs Authority filing</p>
                                    </div>
                                    <span class="badge bg-primary text-white rounded-pill px-3 py-1.5 font-monospace" id="mAuditPeriodBadge">
                                        Period EC
                                    </span>
                                </div>

                                <div class="row g-4">
                                    {{-- Calculation breakdown list --}}
                                    <div class="col-12 col-lg-7">
                                        <div class="list-group list-group-flush rounded-4 border overflow-hidden">
                                            <div class="list-group-item d-flex justify-content-between align-items-center py-3 bg-light">
                                                <span class="fw-bold text-dark text-uppercase small">Audit Component / ERCA Line</span>
                                                <span class="fw-bold text-dark text-uppercase small">Amount (ETB)</span>
                                            </div>
                                            <div class="list-group-item d-flex justify-content-between align-items-center py-2.5">
                                                <div>
                                                    <span class="fw-semibold text-dark">1. Total Taxable Sales Base (ገቢ)</span>
                                                    <small class="text-muted d-block">All customer invoice receipts issued in this month</small>
                                                </div>
                                                <span class="font-monospace fw-bold text-dark" id="mAuditSalesBase">0.00</span>
                                            </div>
                                            <div class="list-group-item d-flex justify-content-between align-items-center py-2.5 bg-danger-subtle bg-opacity-25">
                                                <div>
                                                    <span class="fw-semibold text-danger">2. Output VAT Collected (15%)</span>
                                                    <small class="text-muted d-block">Tax collected on behalf of the government</small>
                                                </div>
                                                <span class="font-monospace fw-bold text-danger" id="mAuditOutputVat">0.00</span>
                                            </div>
                                            <div class="list-group-item d-flex justify-content-between align-items-center py-2.5">
                                                <div>
                                                    <span class="fw-semibold text-dark">3. Total Taxable Purchases Base (ግዢ)</span>
                                                    <small class="text-muted d-block">Creditable procurement, petty cash, and OCR invoices</small>
                                                </div>
                                                <span class="font-monospace fw-bold text-dark" id="mAuditPurchasesBase">0.00</span>
                                            </div>
                                            <div class="list-group-item d-flex justify-content-between align-items-center py-2.5 bg-success-subtle bg-opacity-25">
                                                <div>
                                                    <span class="fw-semibold text-success">4. Creditable Input VAT Paid (15%)</span>
                                                    <small class="text-muted d-block">Direct tax paid on qualifying business purchases (VAT B excluded)</small>
                                                </div>
                                                <span class="font-monospace fw-bold text-success" id="mAuditInputVat">0.00</span>
                                            </div>
                                            <div class="list-group-item d-flex justify-content-between align-items-center py-2.5 bg-info-subtle bg-opacity-25">
                                                <div>
                                                    <span class="fw-semibold text-info">5. Opening Credit Balance Brought Forward</span>
                                                    <small class="text-muted d-block">Positive VAT credit balance carried forward from preceding month</small>
                                                </div>
                                                <span class="font-monospace fw-bold text-info" id="mAuditOpeningCredit">+ 0.00</span>
                                            </div>
                                            <div class="list-group-item d-flex justify-content-between align-items-center py-2.5 bg-light">
                                                <div>
                                                    <span class="fw-bold text-dark">6. Total Creditable Pool = (4) + (5)</span>
                                                    <small class="text-muted d-block">Total allowable tax credits applicable against Output VAT</small>
                                                </div>
                                                <span class="font-monospace fw-bold text-dark" id="mAuditTotalCreditable">0.00</span>
                                            </div>
                                            <div class="list-group-item d-flex justify-content-between align-items-center py-3 border-top border-2" id="mAuditNetRow">
                                                <div>
                                                    <span class="fs-6 fw-bold text-dark">7. Signed Net VAT = (6) &minus; (2)</span>
                                                    <small class="d-block" id="mAuditNetStatusText">Formula: Total Creditable Input &minus; Output VAT</small>
                                                </div>
                                                <span class="font-monospace fs-5 fw-bold" id="mAuditNetSigned">0.00</span>
                                            </div>
                                            <div class="list-group-item d-flex justify-content-between align-items-center py-2.5 bg-warning-subtle bg-opacity-25">
                                                <div>
                                                    <span class="fw-semibold text-dark">8. 3% Withholding Tax (Separate Line)</span>
                                                    <small class="text-muted d-block">Remitted independently to ERCA (Not netted against VAT)</small>
                                                </div>
                                                <span class="font-monospace fw-bold text-danger" id="mAuditWht">- 0.00</span>
                                            </div>
                                        </div>
                                    </div>

                                    {{-- Executive Audit Conclusion & Action --}}
                                    <div class="col-12 col-lg-5">
                                        <div class="p-4 rounded-4 border h-100 d-flex flex-column justify-content-between" id="mAuditDecisionBox">
                                            <div>
                                                <div class="d-flex align-items-center gap-2 mb-3">
                                                    <span class="p-2 rounded-circle bg-primary text-white"><i class="fa-solid fa-gavel fs-6"></i></span>
                                                    <h6 class="fw-bold mb-0 text-dark">Filing Decision &amp; Audit Trail</h6>
                                                </div>
                                                <div class="p-3 rounded-3 mb-3" id="mAuditDecisionBanner">
                                                    {{-- Decision injected via JS --}}
                                                </div>
                                                <div class="small text-muted space-y-2">
                                                    <div class="d-flex gap-2 mb-2">
                                                        <i class="fa-solid fa-circle-check text-success mt-1"></i>
                                                        <span><strong>Ethiopian Calendar Rule:</strong> Bounded strictly within the Ethiopian month. Last-day transactions cannot leak into next cycle.</span>
                                                    </div>
                                                    <div class="d-flex gap-2 mb-2">
                                                        <i class="fa-solid fa-circle-check text-success mt-1"></i>
                                                        <span><strong>Negative Balance:</strong> Output &gt; Input indicates a payment liability due to ERCA by the 30th day.</span>
                                                    </div>
                                                    <div class="d-flex gap-2">
                                                        <i class="fa-solid fa-circle-check text-success mt-1"></i>
                                                        <span><strong>Positive Balance:</strong> Input &gt; Output constitutes a refundable or carry-forward credit into the next month's opening balance.</span>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="pt-3 border-top mt-3 d-flex gap-2">
                                                <button type="button" class="btn btn-primary btn-sm rounded-pill px-3 w-100 fw-semibold" onclick="printMonthMovements()">
                                                    <i class="fa-solid fa-print me-1"></i>Print ERCA Audit
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Modal Footer --}}
            <div class="modal-footer bg-light border-top py-2.5 px-4 d-flex justify-content-between align-items-center flex-shrink-0">
                <span class="small text-muted font-monospace" id="mMovementsFooterSummary">
                    <i class="fa-solid fa-circle-info text-primary me-1"></i>All movements reconciled strictly in Ethiopian calendar era.
                </span>
                <button type="button" class="btn btn-secondary btn-sm rounded-pill px-4" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

{{-- ── Clear Tax (Start From Zero) Modal ────────────────────────────────────── --}}
<div class="modal fade" id="adminClearTaxModal" tabindex="-1" aria-labelledby="adminClearTaxModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <form method="POST" action="{{ route('finance.tax-deductions.admin-clear') }}" enctype="multipart/form-data" class="modal-content border-0 shadow-lg rounded-4 overflow-hidden" id="adminClearTaxForm">
            @csrf
            {{-- Header (Matching System UI style of payVatModal and payWithholdingModal) --}}
            <div class="modal-header bg-warning text-dark py-3 px-4">
                <div class="d-flex align-items-center gap-2.5">
                    <div class="p-2 rounded-circle bg-dark text-warning">
                        <i class="fa-solid fa-broom fs-5"></i>
                    </div>
                    <div>
                        <h5 class="modal-title fw-bold mb-0 text-dark" id="adminClearTaxModalLabel">Clear VAT &amp; Withholding Tax (Start from Zero)</h5>
                        <small class="text-dark-50">Settle accrued tax deductions to reset active liability balance to 0.00 ETB</small>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            {{-- Body --}}
            <div class="modal-body p-4 bg-light">
                {{-- Period Snapshot Card --}}
                <div class="p-3 bg-white rounded-4 border shadow-2xs mb-3">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="small fw-bold text-uppercase text-muted" style="font-size:0.75rem; letter-spacing:0.5px;">Active Deductions Period Snapshot</span>
                        <span class="badge bg-warning text-dark rounded-pill fw-bold" id="clear_modal_period_badge">
                            <span id="clear_modal_month_label">{{ $monthRange['month_en'] ?? 'Selected Month' }}</span> {{ $selectedEthYear }} ዓ.ም.
                        </span>
                    </div>
                    <div class="row g-2 text-center">
                        <div class="col-4">
                            <div class="bg-light p-2.5 rounded-3 border">
                                <div class="text-muted small" style="font-size:0.75rem;">15% Output VAT</div>
                                <div class="fw-bold text-danger font-monospace fs-6">ETB {{ number_format($outputVat, 2) }}</div>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="bg-light p-2.5 rounded-3 border">
                                <div class="text-muted small" style="font-size:0.75rem;">15% Creditable Input</div>
                                <div class="fw-bold text-success font-monospace fs-6">ETB {{ number_format($inputVat, 2) }}</div>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="bg-light p-2.5 rounded-3 border">
                                <div class="text-muted small" style="font-size:0.75rem;">3% Withholding</div>
                                <div class="fw-bold text-danger font-monospace fs-6">ETB {{ number_format($monthWithholding, 2) }}</div>
                            </div>
                        </div>
                    </div>
                    <div class="text-muted small mt-2" style="font-size: 0.78rem;">
                        <i class="fa-solid fa-circle-info text-warning me-1"></i>
                        Clearing stamps matching deductions as settled and resets your active ledger liability to <strong>0.00 ETB (start from zero)</strong>.
                    </div>
                </div>

                {{-- Scope Selector (Specific Month vs All Unsettled) --}}
                <div class="card border rounded-3 mb-3 bg-white shadow-2xs">
                    <div class="card-body p-3">
                        <label class="form-label small fw-bold text-dark text-uppercase mb-2" style="font-size:0.75rem; letter-spacing:0.5px;">
                            <i class="fa-regular fa-calendar-days text-primary me-1"></i>Period Scope
                        </label>
                        <div class="row g-2">
                            <div class="col-sm-6">
                                <label class="border rounded-3 p-2.5 d-flex align-items-start gap-2 cursor-pointer w-100 mb-0" id="opt-scope-month" style="border-color:#f59e0b !important; background:#fffbeb;">
                                    <input type="radio" name="scope" value="month" class="form-check-input mt-1" checked onchange="toggleClearScope('month')">
                                    <div>
                                        <div class="fw-bold text-dark small"><i class="fa-regular fa-calendar text-warning me-1"></i>Specific Ethiopian Month</div>
                                        <div class="text-muted" style="font-size:0.78rem;">Clear the selected month to 0.00 ETB</div>
                                    </div>
                                </label>
                            </div>
                            <div class="col-sm-6">
                                <label class="border rounded-3 p-2.5 d-flex align-items-start gap-2 cursor-pointer w-100 mb-0" id="opt-scope-all">
                                    <input type="radio" name="scope" value="all_active" class="form-check-input mt-1" onchange="toggleClearScope('all_active')">
                                    <div>
                                        <div class="fw-bold text-dark small"><i class="fa-solid fa-layer-group text-primary me-1"></i>All Unsettled Liabilities</div>
                                        <div class="text-muted" style="font-size:0.78rem;">Reset all active pending deductions to 0.00</div>
                                    </div>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Action Type Selector (Clear vs Reopen) --}}
                <div class="card border rounded-3 mb-3 bg-white shadow-2xs">
                    <div class="card-body p-3">
                        <label class="form-label small fw-bold text-dark text-uppercase mb-2" style="font-size:0.75rem; letter-spacing:0.5px;">
                            <i class="fa-solid fa-sliders text-primary me-1"></i>Action Mode
                        </label>
                        <div class="row g-2">
                            <div class="col-sm-6">
                                <label class="border rounded-3 p-2.5 d-flex align-items-start gap-2.5 cursor-pointer w-100 action-mode-option selected-action mb-0" id="opt-action-clear" style="border-color:#f59e0b !important; background:#fffbeb;">
                                    <input type="radio" name="action_type" value="clear" class="form-check-input mt-1" checked onchange="toggleAdminClearActionMode('clear')">
                                    <div>
                                        <div class="fw-bold text-dark small"><i class="fa-solid fa-broom text-warning me-1"></i>Clear to Zero (0.00 ETB)</div>
                                        <div class="text-muted" style="font-size:0.78rem;">Stamp records as settled &amp; reset active monthly tax pool to zero.</div>
                                    </div>
                                </label>
                            </div>
                            <div class="col-sm-6">
                                <label class="border rounded-3 p-2.5 d-flex align-items-start gap-2.5 cursor-pointer w-100 action-mode-option mb-0" id="opt-action-reopen">
                                    <input type="radio" name="action_type" value="reopen" class="form-check-input mt-1" onchange="toggleAdminClearActionMode('reopen')">
                                    <div>
                                        <div class="fw-bold text-dark small"><i class="fa-solid fa-rotate-left text-primary me-1"></i>Re-open Month (Undo)</div>
                                        <div class="text-muted" style="font-size:0.78rem;">Revert settled records back to active unsettled status for recalculation.</div>
                                    </div>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Ethiopian Tax Period (Month & Year) --}}
                <div class="card border rounded-3 mb-3 bg-white shadow-2xs" id="clearScopeMonthBox">
                    <div class="card-body p-3">
                        <label class="form-label small fw-bold text-dark text-uppercase mb-2" style="font-size:0.75rem; letter-spacing:0.5px;">
                            <i class="fa-regular fa-calendar-days text-primary me-1"></i>Ethiopian Tax Period (ወርና ዓ.ም.)
                        </label>
                        <div class="row g-2">
                            <div class="col-7">
                                <label class="form-label small text-muted mb-1" style="font-size:0.75rem;">Month (ወር)</label>
                                <select name="ethiopian_month" id="admin_clear_eth_month" class="form-select form-select-sm fw-semibold" onchange="updateClearModalMonthLabel()">
                                    @foreach($availableEthMonths as $mId => $m)
                                        <option value="{{ $mId }}" {{ $selectedEthMonth == $mId ? 'selected' : '' }}>
                                            {{ $m['label'] }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-5">
                                <label class="form-label small text-muted mb-1" style="font-size:0.75rem;">Year (ዓ.ም.)</label>
                                <select name="ethiopian_year" id="admin_clear_eth_year" class="form-select form-select-sm fw-semibold">
                                    @foreach($availableEthYears as $y)
                                        <option value="{{ $y }}" {{ $selectedEthYear == $y ? 'selected' : '' }}>
                                            {{ $y }} ዓ.ም.
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Tax Type Target --}}
                <div class="card border rounded-3 mb-3 bg-white shadow-2xs">
                    <div class="card-body p-3">
                        <label class="form-label small fw-bold text-dark text-uppercase mb-2" style="font-size:0.75rem; letter-spacing:0.5px;">
                            <i class="fa-solid fa-receipt text-primary me-1"></i>Target Deductions to Clear / Reset
                        </label>
                        <div class="row g-2">
                            <div class="col-md-4">
                                <label class="border rounded-3 p-2.5 d-flex align-items-center gap-2 cursor-pointer w-100 mb-0">
                                    <input type="radio" name="clear_target" value="both" class="form-check-input" checked>
                                    <span class="small fw-semibold text-dark">Both VAT &amp; 3% WHT</span>
                                </label>
                            </div>
                            <div class="col-md-4">
                                <label class="border rounded-3 p-2.5 d-flex align-items-center gap-2 cursor-pointer w-100 mb-0">
                                    <input type="radio" name="clear_target" value="vat" class="form-check-input">
                                    <span class="small fw-semibold text-dark">15% VAT Only</span>
                                </label>
                            </div>
                            <div class="col-md-4">
                                <label class="border rounded-3 p-2.5 d-flex align-items-center gap-2 cursor-pointer w-100 mb-0">
                                    <input type="radio" name="clear_target" value="withholding" class="form-check-input">
                                    <span class="small fw-semibold text-dark">3% Withholding Only</span>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Reference & Notes & Proof Slip --}}
                <div class="card border rounded-3 bg-white shadow-2xs">
                    <div class="card-body p-3">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label small fw-bold text-dark mb-1">
                                    <i class="fa-solid fa-hashtag text-secondary me-1"></i>Official / ERCA Reference Number (Optional)
                                </label>
                                <input type="text" name="reference" class="form-control form-control-sm" placeholder="e.g. ERCA-TAX-CLEAR-2018-02 / Slip #">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold text-dark mb-1">
                                    <i class="fa-solid fa-paperclip text-secondary me-1"></i>Settlement Slip / Proof Document (Optional)
                                </label>
                                <input type="file" name="attachment" class="form-control form-control-sm" accept=".pdf,.jpg,.jpeg,.png">
                            </div>
                            <div class="col-12">
                                <label class="form-label small fw-bold text-dark mb-1">
                                    <i class="fa-regular fa-comment-dots text-secondary me-1"></i>Administrative Notes / Reason
                                </label>
                                <textarea name="admin_notes" rows="2" class="form-control form-control-sm" placeholder="e.g. Approved tax declaration clearance. All deductions reconciled to start from zero."></textarea>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Footer --}}
            <div class="modal-footer bg-light border-top py-2.5 px-4 d-flex justify-content-between align-items-center">
                <button type="button" class="btn btn-outline-secondary btn-sm rounded-pill px-3" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-warning text-dark fw-bold btn-sm rounded-pill px-4 shadow-sm" id="adminClearSubmitBtn">
                    <i class="fa-solid fa-broom me-1.5"></i>
                    <span>Confirm &amp; Clear Month to Zero</span>
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    // ── Add Receipt (AI OCR) Modal Handlers & Automatic Calculations ────────────
    function triggerAddReceiptScan() {
        const input = document.getElementById('add_rcpt_file_input');
        if (!input || !input.files || input.files.length === 0) {
            alert('Please choose or capture a receipt slip file first.');
            input?.click();
            return;
        }
        handleModalReceiptScan(input.files[0]);
    }

    function onTotalAmountChange() {
        const total = parseFloat(document.getElementById('add_rcpt_total_amount')?.value) || 0;
        const taxType = document.getElementById('add_rcpt_tax_type')?.value || 'vat';
        const subtotalEl = document.getElementById('add_rcpt_subtotal');
        const vatEl = document.getElementById('add_rcpt_vat_amount');
        const whtEl = document.getElementById('add_rcpt_withholding_amount');

        if (total <= 0) return;

        if (taxType === 'withholding') {
            if (subtotalEl) subtotalEl.value = total.toFixed(2);
            if (vatEl) vatEl.value = '0.00';
            if (whtEl) whtEl.value = (total * 0.03).toFixed(2);
        } else {
            const base = +(total / 1.15).toFixed(2);
            const vat = +(total - base).toFixed(2);
            if (subtotalEl) subtotalEl.value = base.toFixed(2);
            if (vatEl) vatEl.value = vat.toFixed(2);
            if (whtEl) whtEl.value = (taxType === 'both') ? (base * 0.03).toFixed(2) : '0.00';
        }
    }

    function onSubtotalChange() {
        const subtotal = parseFloat(document.getElementById('add_rcpt_subtotal')?.value) || 0;
        const taxType = document.getElementById('add_rcpt_tax_type')?.value || 'vat';
        const totalEl = document.getElementById('add_rcpt_total_amount');
        const vatEl = document.getElementById('add_rcpt_vat_amount');
        const whtEl = document.getElementById('add_rcpt_withholding_amount');

        if (subtotal <= 0) return;

        if (taxType === 'withholding') {
            if (vatEl) vatEl.value = '0.00';
            if (totalEl) totalEl.value = subtotal.toFixed(2);
            if (whtEl) whtEl.value = (subtotal * 0.03).toFixed(2);
        } else {
            const vat = +(subtotal * 0.15).toFixed(2);
            const total = +(subtotal + vat).toFixed(2);
            if (vatEl) vatEl.value = vat.toFixed(2);
            if (totalEl) totalEl.value = total.toFixed(2);
            if (whtEl) whtEl.value = (taxType === 'both') ? (subtotal * 0.03).toFixed(2) : '0.00';
        }
    }

    function onVatAmountChange() {
        const vat = parseFloat(document.getElementById('add_rcpt_vat_amount')?.value) || 0;
        const taxType = document.getElementById('add_rcpt_tax_type')?.value || 'vat';
        const totalEl = document.getElementById('add_rcpt_total_amount');
        const subtotalEl = document.getElementById('add_rcpt_subtotal');
        const whtEl = document.getElementById('add_rcpt_withholding_amount');

        if (vat <= 0) return;

        const subtotal = +(vat / 0.15).toFixed(2);
        const total = +(subtotal + vat).toFixed(2);
        if (subtotalEl) subtotalEl.value = subtotal.toFixed(2);
        if (totalEl) totalEl.value = total.toFixed(2);
        if (whtEl && taxType === 'both') whtEl.value = (subtotal * 0.03).toFixed(2);
    }

    function onTaxTypeChange() {
        const taxType = document.getElementById('add_rcpt_tax_type')?.value || 'vat';
        const whtWrapper = document.getElementById('add_rcpt_wht_wrapper');
        if (whtWrapper) {
            whtWrapper.style.display = (taxType === 'withholding' || taxType === 'both') ? 'block' : 'none';
        }
        const total = parseFloat(document.getElementById('add_rcpt_total_amount')?.value) || 0;
        if (total > 0) {
            onTotalAmountChange();
        }
    }

    function onCategoryChange() {
        const cat = document.getElementById('add_rcpt_category')?.value || '';
        const purcTypeSelect = document.getElementById('add_rcpt_purchase_type');
        if (!purcTypeSelect) return;
        if (['machinery', 'subcontractor'].includes(cat)) {
            purcTypeSelect.value = 'PUR_SR';
        } else if (['material', 'fuel', 'equipment', 'spare_parts'].includes(cat)) {
            purcTypeSelect.value = 'PUR_GD';
        }
    }

    function handleModalReceiptScan(file) {
        if (!file) return;

        const btn = document.getElementById('btnAddRcptScan');
        const btnText = document.getElementById('addRcptScanBtnText');
        const statusBox = document.getElementById('addRcptOcrStatus');

        if (btn) btn.disabled = true;
        if (btnText) btnText.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-1"></i>Scanning OCR...';

        if (statusBox) {
            statusBox.classList.remove('d-none', 'alert-danger', 'alert-success');
            statusBox.classList.add('alert-info');
            statusBox.innerHTML = '<i class="fa-solid fa-microchip fa-spin me-1"></i>AI Vision OCR analyzing receipt... extracting date, TIN, FS#, and amounts.';
        }

        const formData = new FormData();
        formData.append('receipt_file', file);
        formData.append('_token', '{{ csrf_token() }}');

        fetch('{{ route('finance.tax-deductions.scan-receipt') }}', {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            if (btn) btn.disabled = false;
            if (btnText) btnText.innerHTML = '<i class="fa-solid fa-microchip me-1"></i>Scan with AI OCR';

            if (data.success) {
                // Auto-populate form fields
                if (data.file_path) document.getElementById('add_rcpt_file_path').value = data.file_path;
                if (data.receipt_date) document.getElementById('add_rcpt_date').value = data.receipt_date;
                if (data.fs_no) document.getElementById('add_rcpt_fs_no').value = data.fs_no;
                if (data.mrc_no) document.getElementById('add_rcpt_mrc_no').value = data.mrc_no;
                if (data.vendor_tin) document.getElementById('add_rcpt_vendor_tin').value = data.vendor_tin;
                if (data.vendor_name) document.getElementById('add_rcpt_vendor_name').value = data.vendor_name;
                if (data.buyer_tin) document.getElementById('add_rcpt_buyer_tin').value = data.buyer_tin;

                // Reconcile and calculate ALL amounts read from receipt
                let totalVal = (data.total_amount !== undefined && data.total_amount !== null) ? parseFloat(data.total_amount) : 0;
                let subtotalVal = (data.subtotal !== undefined && data.subtotal !== null) ? parseFloat(data.subtotal) : 0;
                let vatVal = (data.vat_amount !== undefined && data.vat_amount !== null) ? parseFloat(data.vat_amount) : 0;
                let whtVal = (data.withholding_amount !== undefined && data.withholding_amount !== null) ? parseFloat(data.withholding_amount) : 0;

                if (totalVal > 0 && subtotalVal <= 0) {
                    if (vatVal > 0) {
                        subtotalVal = +(totalVal - vatVal).toFixed(2);
                    } else {
                        subtotalVal = +(totalVal / 1.15).toFixed(2);
                        vatVal = +(totalVal - subtotalVal).toFixed(2);
                    }
                } else if (subtotalVal > 0 && totalVal <= 0) {
                    if (vatVal > 0) {
                        totalVal = +(subtotalVal + vatVal).toFixed(2);
                    } else {
                        vatVal = +(subtotalVal * 0.15).toFixed(2);
                        totalVal = +(subtotalVal + vatVal).toFixed(2);
                    }
                } else if (totalVal > 0 && subtotalVal > 0 && vatVal <= 0 && totalVal > subtotalVal) {
                    vatVal = +(totalVal - subtotalVal).toFixed(2);
                } else if (vatVal > 0 && totalVal <= 0 && subtotalVal <= 0) {
                    subtotalVal = +(vatVal / 0.15).toFixed(2);
                    totalVal = +(subtotalVal + vatVal).toFixed(2);
                }

                if (whtVal <= 0 && subtotalVal > 0) {
                    whtVal = +(subtotalVal * 0.03).toFixed(2);
                }

                // Auto-set tax deduction type if VAT detected
                const taxTypeSelect = document.getElementById('add_rcpt_tax_type');
                if (taxTypeSelect && vatVal > 0 && taxTypeSelect.value === 'withholding') {
                    taxTypeSelect.value = 'vat';
                }

                // Auto-populate ERCA purchase type (Goods vs Services)
                const ptSelect = document.getElementById('add_rcpt_purchase_type');
                if (ptSelect && data.purchase_type) {
                    ptSelect.value = (data.purchase_type === 'PUR_SR' || data.purchase_type === 4 || data.purchase_type === '4') ? 'PUR_SR' : 'PUR_GD';
                }

                // Automatically fill all amount fields in ERCA Purchase Type & Financials (Line 100)
                if (totalVal > 0) document.getElementById('add_rcpt_total_amount').value = totalVal.toFixed(2);
                if (subtotalVal > 0) document.getElementById('add_rcpt_subtotal').value = subtotalVal.toFixed(2);
                if (vatVal > 0) document.getElementById('add_rcpt_vat_amount').value = vatVal.toFixed(2);
                if (whtVal > 0) document.getElementById('add_rcpt_withholding_amount').value = whtVal.toFixed(2);

                // Update real-time status inside ERCA Purchase Type & Financials (Line 100) card
                const finCard = document.getElementById('add_rcpt_financials_card');
                const finStatus = document.getElementById('add_rcpt_financials_status');
                const finStatusText = document.getElementById('add_rcpt_financials_status_text');
                const finBadge = document.getElementById('add_rcpt_financials_calc_badge');
                if (finCard) {
                    finCard.classList.remove('bg-opacity-10', 'border-opacity-25');
                    finCard.classList.add('bg-opacity-25', 'border-success', 'border-2');
                    setTimeout(() => {
                        finCard.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                    }, 200);
                }
                if (finStatus && finStatusText) {
                    finStatus.classList.remove('d-none');
                    const ptLabel = ptSelect ? ptSelect.options[ptSelect.selectedIndex]?.text : (data.purchase_type === 'PUR_SR' ? 'PUR_SR (Services - 4)' : 'PUR_GD (Goods - 3)');
                    finStatusText.innerHTML = `<i class="fa-solid fa-circle-check text-success me-1"></i><strong>Scanned &amp; Filled:</strong> ERCA Type: <strong>${ptLabel}</strong> &bull; Total: <strong>ETB ${totalVal.toFixed(2)}</strong> &bull; Base: <strong>ETB ${subtotalVal.toFixed(2)}</strong> &bull; 15% VAT: <strong>ETB ${vatVal.toFixed(2)}</strong>`;
                    if (finBadge) {
                        finBadge.textContent = '✓ Line 100 Reconciled';
                    }
                }

                if (data.vendor_name && (!document.getElementById('add_rcpt_description').value)) {
                    document.getElementById('add_rcpt_description').value = data.vendor_name + (data.fs_no ? (' (FS: ' + data.fs_no + ')') : '');
                }

                if (statusBox) {
                    statusBox.classList.remove('alert-info');
                    statusBox.classList.add('alert-success');
                    let msg = `<i class="fa-solid fa-circle-check me-1"></i><strong>Receipt scanned!</strong> Amounts auto-populated: <strong>Total: ETB ${totalVal.toFixed(2)}</strong> | Base Subtotal: ETB ${subtotalVal.toFixed(2)} | 15% VAT: ETB ${vatVal.toFixed(2)}`;
                    if (data.is_duplicate) {
                        msg += `<br><span class="text-danger fw-bold"><i class="fa-solid fa-triangle-exclamation me-1"></i>Duplicate Warning:</span> ${data.duplicate_message || 'Duplicate receipt FS# detected in records!'}`;
                    }
                    statusBox.innerHTML = msg;
                }
            } else {
                if (statusBox) {
                    statusBox.classList.remove('alert-info');
                    statusBox.classList.add('alert-danger');
                    statusBox.innerHTML = '<i class="fa-solid fa-triangle-exclamation me-1"></i>' + (data.message || 'Could not extract fields. You can enter them manually below.');
                }
            }
        })
        .catch(err => {
            if (btn) btn.disabled = false;
            if (btnText) btnText.innerHTML = '<i class="fa-solid fa-microchip me-1"></i>Scan with AI OCR';
            if (statusBox) {
                statusBox.classList.remove('alert-info');
                statusBox.classList.add('alert-danger');
                statusBox.innerHTML = '<i class="fa-solid fa-circle-xmark me-1"></i>Scan error: ' + err.message;
            }
        });
    }

    document.addEventListener('DOMContentLoaded', function() {
        const dropzone = document.getElementById('addRcptDropzone');
        if (dropzone) {
            ['dragenter', 'dragover'].forEach(name => {
                dropzone.addEventListener(name, (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    dropzone.classList.add('bg-primary', 'bg-opacity-25', 'border-primary');
                });
            });
            ['dragleave', 'drop'].forEach(name => {
                dropzone.addEventListener(name, (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    dropzone.classList.remove('bg-opacity-25');
                });
            });
            dropzone.addEventListener('drop', (e) => {
                const dt = e.dataTransfer;
                if (dt && dt.files && dt.files.length > 0) {
                    handleModalReceiptScan(dt.files[0]);
                }
            });
        }

        const addModalEl = document.getElementById('addReceiptModal');
        if (addModalEl) {
            addModalEl.addEventListener('hidden.bs.modal', function() {
                document.getElementById('addReceiptForm')?.reset();
                const pathEl = document.getElementById('add_rcpt_file_path');
                if (pathEl) pathEl.value = '';
                const statusBox = document.getElementById('addRcptOcrStatus');
                if (statusBox) {
                    statusBox.classList.add('d-none');
                    statusBox.innerHTML = '';
                }
                const dateEl = document.getElementById('add_rcpt_date');
                if (dateEl) dateEl.value = '{{ now()->format('Y-m-d') }}';
                const buyerTinEl = document.getElementById('add_rcpt_buyer_tin');
                if (buyerTinEl) buyerTinEl.value = '38480010';
                const finCard = document.getElementById('add_rcpt_financials_card');
                if (finCard) {
                    finCard.classList.remove('bg-opacity-25', 'border-2');
                    finCard.classList.add('bg-opacity-10');
                }
                const finStatus = document.getElementById('add_rcpt_financials_status');
                if (finStatus) {
                    finStatus.classList.add('d-none');
                }
            });
        }

        const addForm = document.getElementById('addReceiptForm');
        if (addForm) {
            addForm.addEventListener('submit', function(e) {
                e.preventDefault();

                const submitBtn = document.getElementById('btnAddRcptSubmit');
                const origHtml = submitBtn.innerHTML;
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-1"></i>Saving Record...';

                const formData = new FormData(addForm);

                fetch('{{ route('finance.tax-deductions.store-receipt') }}', {
                    method: 'POST',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json'
                    },
                    body: formData
                })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        submitBtn.classList.remove('btn-primary');
                        submitBtn.classList.add('btn-success');
                        submitBtn.innerHTML = '<i class="fa-solid fa-circle-check me-1"></i>Saved Successfully!';
                        setTimeout(() => {
                            window.location.reload();
                        }, 800);
                    } else {
                        submitBtn.disabled = false;
                        submitBtn.innerHTML = origHtml;
                        alert('Error saving receipt: ' + (data.message || 'Please check form inputs.'));
                    }
                })
                .catch(err => {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = origHtml;
                    alert('Submission failed: ' + err.message);
                });
            });
        }
    });

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
        const dateVal = document.getElementById('vat-receipt-date-input')?.value;
        if (dateVal) formData.append('receipt_date', dateVal);
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

    // ── 13-Month Financial Movement & Audit Drilldown ────────────────────────
    let currentMovementsData = null;
    let openDrawerMonth = null;
    const movementsCache = {};

    // Relocate modal to document.body to prevent transform containing block traps
    document.addEventListener('DOMContentLoaded', function () {
        const modalEl = document.getElementById('monthMovementsModal');
        if (modalEl && modalEl.parentElement !== document.body) {
            document.body.appendChild(modalEl);
        }
    });

    function formatMoney(amount) {
        const n = parseFloat(amount) || 0;
        return n.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    // ── INLINE DRAWER: View movements directly inside the table ───────────────
    function toggleMonthInlineDrawer(ethYear, ethMonth, monthEn, monthAm) {
        const drawerRow = document.getElementById(`trend-drawer-row-${ethMonth}`);
        const btn = document.getElementById(`btn-drawer-${ethMonth}`);
        const icon = document.getElementById(`icon-drawer-${ethMonth}`);
        const text = document.getElementById(`text-drawer-${ethMonth}`);
        const contentContainer = document.getElementById(`trend-drawer-content-${ethMonth}`);
        if (!drawerRow || !contentContainer) return;

        // If clicking currently open drawer, close it
        if (!drawerRow.classList.contains('d-none')) {
            closeMonthInlineDrawer(ethMonth);
            return;
        }

        // Close any other open drawer
        if (openDrawerMonth && openDrawerMonth !== ethMonth) {
            closeMonthInlineDrawer(openDrawerMonth);
        }

        // Open this drawer
        drawerRow.classList.remove('d-none');
        openDrawerMonth = ethMonth;
        if (btn) btn.classList.replace('btn-primary', 'btn-dark');
        if (icon) icon.className = 'fa-solid fa-chevron-up';
        if (text) text.innerText = 'Hide';

        // Check cache or fetch
        const cacheKey = `${ethYear}-${ethMonth}`;
        if (movementsCache[cacheKey]) {
            renderInlineDrawer(ethMonth, movementsCache[cacheKey]);
            return;
        }

        // Loading spinner
        contentContainer.innerHTML = `
            <div class="text-center py-4 bg-white rounded-4 border border-secondary-subtle shadow-xs">
                <div class="spinner-border spinner-border-sm text-primary me-2" role="status"></div>
                <span class="text-dark fw-semibold">Retrieving detailed financial movements for ${monthEn} (${monthAm})...</span>
                <div class="text-muted small mt-1">Loading customer sales receipts, company expenditures, and ERCA tax lines</div>
            </div>
        `;

        const url = "{{ route('finance.tax-deductions.month-movements') }}?ethiopian_year=" + ethYear + "&ethiopian_month=" + ethMonth;
        fetch(url, {
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(res => {
            if (!res.ok) throw new Error('HTTP error ' + res.status);
            return res.json();
        })
        .then(data => {
            if (data.success) {
                movementsCache[cacheKey] = data;
                renderInlineDrawer(ethMonth, data);
            } else {
                throw new Error(data.message || 'Error loading transaction movements');
            }
        })
        .catch(err => {
            console.error('Failed to load inline month movements:', err);
            contentContainer.innerHTML = `
                <div class="alert alert-danger rounded-4 py-3 px-4 d-flex align-items-center justify-content-between mb-0">
                    <div>
                        <i class="fa-solid fa-triangle-exclamation me-2"></i>
                        <span>Failed to load transaction movements: ${err.message || 'Unknown error'}</span>
                    </div>
                    <button type="button" class="btn btn-sm btn-outline-danger rounded-pill" onclick="toggleMonthInlineDrawer(${ethYear}, ${ethMonth}, '${monthEn}', '${monthAm}')">
                        <i class="fa-solid fa-rotate-right me-1"></i>Retry
                    </button>
                </div>
            `;
        });
    }

    function closeMonthInlineDrawer(ethMonth) {
        const drawerRow = document.getElementById(`trend-drawer-row-${ethMonth}`);
        const btn = document.getElementById(`btn-drawer-${ethMonth}`);
        const icon = document.getElementById(`icon-drawer-${ethMonth}`);
        const text = document.getElementById(`text-drawer-${ethMonth}`);
        if (drawerRow) drawerRow.classList.add('d-none');
        if (btn) btn.classList.replace('btn-dark', 'btn-primary');
        if (icon) icon.className = 'fa-solid fa-receipt';
        if (text) text.innerText = 'Movements';
        if (openDrawerMonth === ethMonth) openDrawerMonth = null;
    }

    function renderInlineDrawer(monthNum, data) {
        const m = data.month;
        const s = data.summary;
        const container = document.getElementById(`trend-drawer-content-${monthNum}`);
        if (!container) return;

        let incomeRows = '';
        if (data.incomes && data.incomes.length > 0) {
            data.incomes.forEach((inc, idx) => {
                incomeRows += `
                    <tr>
                        <td class="ps-3 font-monospace small text-muted">${idx + 1}</td>
                        <td><span class="fw-bold font-monospace text-dark d-block">${inc.date_ec}</span><small class="text-muted" style="font-size:0.75rem;">${inc.date_gc}</small></td>
                        <td><span class="badge bg-light text-dark border font-monospace">${inc.income_no}</span></td>
                        <td><span class="fw-semibold text-dark">${inc.buyer_name}</span>${inc.buyer_tin !== '-' ? `<small class="text-muted d-block font-monospace" style="font-size:0.75rem;">TIN: ${inc.buyer_tin}</small>` : ''}</td>
                        <td><span class="badge bg-secondary-subtle text-secondary rounded-pill small">${inc.project_name}</span></td>
                        <td class="small text-muted"><i class="fa-solid fa-building-columns me-1 text-primary"></i>${inc.account_name}</td>
                        <td class="text-end font-monospace">ETB ${formatMoney(inc.sales_base)}</td>
                        <td class="text-end font-monospace text-danger fw-semibold">ETB ${formatMoney(inc.output_vat)}</td>
                        <td class="text-end font-monospace text-success fw-bold pe-3">ETB ${formatMoney(inc.gross_amount)}</td>
                    </tr>
                `;
            });
        } else {
            incomeRows = `<tr><td colspan="9" class="text-center py-4 text-muted"><i class="fa-solid fa-folder-open me-2 text-secondary"></i>No company income receipts recorded for this month.</td></tr>`;
        }

        let expenseRows = '';
        if (data.expenses && data.expenses.length > 0) {
            data.expenses.forEach((exp, idx) => {
                expenseRows += `
                    <tr>
                        <td class="ps-3 font-monospace small text-muted">${idx + 1}</td>
                        <td><span class="fw-bold font-monospace text-dark d-block">${exp.date_ec}</span><small class="text-muted" style="font-size:0.75rem;">${exp.date_gc}</small></td>
                        <td><span class="badge bg-light text-dark border font-monospace">${exp.request_number}</span></td>
                        <td><span class="fw-semibold text-dark">${exp.payee}</span></td>
                        <td><span class="badge bg-info-subtle text-info border border-info-subtle rounded-pill small">${exp.category}</span><small class="text-muted d-block" style="font-size:0.75rem;">${exp.project_name}</small></td>
                        <td class="small text-muted"><i class="fa-solid fa-wallet me-1 text-danger"></i>${exp.account_name}</td>
                        <td class="text-end font-monospace">ETB ${formatMoney(exp.base_amount)}</td>
                        <td class="text-end font-monospace text-success fw-semibold">ETB ${formatMoney(exp.vat_amount)}</td>
                        <td class="text-end font-monospace text-danger">${exp.withholding_amount > 0 ? '- ETB ' + formatMoney(exp.withholding_amount) : '-'}</td>
                        <td class="text-end font-monospace text-dark fw-bold pe-3">ETB ${formatMoney(exp.net_disbursed)}</td>
                    </tr>
                `;
            });
        } else {
            expenseRows = `<tr><td colspan="10" class="text-center py-4 text-muted"><i class="fa-solid fa-folder-open me-2 text-secondary"></i>No purchases or paid expenses recorded for this month.</td></tr>`;
        }

        let ocrRows = '';
        if (data.ocr_items && data.ocr_items.length > 0) {
            data.ocr_items.forEach((ocr, idx) => {
                ocrRows += `
                    <tr>
                        <td class="ps-3 font-monospace small text-muted">${idx + 1}</td>
                        <td><span class="fw-bold font-monospace text-dark d-block">${ocr.date_ec}</span><small class="text-muted" style="font-size:0.75rem;">${ocr.date_gc}</small></td>
                        <td><span class="badge bg-light text-primary border font-monospace">${ocr.fs_no}</span></td>
                        <td><span class="fw-semibold text-dark">${ocr.vendor_name}</span><small class="text-muted d-block font-monospace" style="font-size:0.75rem;">TIN: ${ocr.vendor_tin}</small></td>
                        <td><span class="badge bg-secondary-subtle text-secondary rounded-pill small">${ocr.project_name}</span></td>
                        <td class="small text-dark text-truncate" style="max-width: 200px;">${ocr.item_description}</td>
                        <td class="text-end font-monospace">ETB ${formatMoney(ocr.base_value)}</td>
                        <td class="text-end font-monospace text-success fw-semibold">ETB ${formatMoney(ocr.vat_amount)}</td>
                        <td class="text-end font-monospace text-dark fw-bold pe-3">ETB ${formatMoney(ocr.value_after_vat)}</td>
                    </tr>
                `;
            });
        } else {
            ocrRows = `<tr><td colspan="9" class="text-center py-4 text-muted"><i class="fa-solid fa-folder-open me-2 text-secondary"></i>No OCR scanned invoices recorded for this month.</td></tr>`;
        }

        container.innerHTML = `
            <div class="card border border-primary border-opacity-25 shadow-sm rounded-4 overflow-hidden bg-white mb-2">
                <!-- Header Bar -->
                <div class="card-header bg-dark text-white py-2.5 px-3 px-md-4 d-flex flex-wrap justify-content-between align-items-center gap-2" style="background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%) !important;">
                    <div class="d-flex align-items-center gap-2">
                        <span class="p-1.5 rounded-circle text-warning" style="background: rgba(245,158,11,0.2);"><i class="fa-solid fa-calendar-day"></i></span>
                        <div>
                            <h6 class="fw-bold mb-0 text-white">${m.title} &mdash; Transaction Movements &amp; Audit</h6>
                            <small class="text-white-50">${m.gregorian_window}</small>
                        </div>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <button type="button" class="btn btn-sm btn-light text-dark rounded-pill px-3 py-1 fw-semibold shadow-xs" onclick="openMonthMovementsModal(${m.year}, ${m.month}, '${m.month_name}', '${m.month_am}')">
                            <i class="fa-solid fa-up-right-from-square me-1 text-primary"></i>Fullscreen Dialog
                        </button>
                        <a href="{{ route('finance.tax-deductions.index') }}?ethiopian_year=${m.year}&ethiopian_month=${m.month}" class="btn btn-sm btn-outline-light rounded-pill px-3 py-1 fw-semibold">
                            <i class="fa-solid fa-filter me-1"></i>Filter Page
                        </a>
                        <button type="button" class="btn btn-sm btn-outline-light rounded-circle p-1" onclick="closeMonthInlineDrawer(${monthNum})" title="Close Drawer">
                            <i class="fa-solid fa-xmark"></i>
                        </button>
                    </div>
                </div>

                <div class="card-body p-3">
                    <!-- 5-Card KPI Strip -->
                    <div class="row g-2 mb-3 row-cols-1 row-cols-sm-2 row-cols-md-3 row-cols-lg-5">
                        <div class="col">
                            <div class="p-2.5 rounded-3 bg-light border border-secondary-subtle">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <span class="text-muted text-uppercase fw-bold" style="font-size: 0.65rem;">Sales Base (ገቢ)</span>
                                    <span class="badge bg-primary-subtle text-primary rounded-pill" style="font-size: 0.62rem;">${s.income_count} receipts</span>
                                </div>
                                <div class="fw-bold text-dark font-monospace fs-6">ETB ${formatMoney(s.sales_base)}</div>
                                <div class="mt-1 pt-1 border-top border-secondary-subtle d-flex justify-content-between small" style="font-size: 0.7rem;">
                                    <span class="text-muted">Output VAT:</span>
                                    <span class="fw-bold text-danger font-monospace">ETB ${formatMoney(s.output_vat)}</span>
                                </div>
                            </div>
                        </div>
                        <div class="col">
                            <div class="p-2.5 rounded-3 bg-light border border-secondary-subtle">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <span class="text-muted text-uppercase fw-bold" style="font-size: 0.65rem;">Purchases Base (ግዢ)</span>
                                    <span class="badge bg-success-subtle text-success rounded-pill" style="font-size: 0.62rem;">${s.expense_count} items</span>
                                </div>
                                <div class="fw-bold text-dark font-monospace fs-6">ETB ${formatMoney(s.purchases_base)}</div>
                                <div class="mt-1 pt-1 border-top border-secondary-subtle d-flex justify-content-between small" style="font-size: 0.7rem;">
                                    <span class="text-muted">Input VAT:</span>
                                    <span class="fw-bold text-success font-monospace">ETB ${formatMoney(s.input_vat)}</span>
                                </div>
                            </div>
                        </div>
                        <div class="col">
                            <div class="p-2.5 rounded-3 bg-light border border-secondary-subtle">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <span class="text-muted text-uppercase fw-bold" style="font-size: 0.65rem;">Opening Credit</span>
                                    <span class="badge bg-info-subtle text-info rounded-pill" style="font-size: 0.62rem;">Prev Month</span>
                                </div>
                                <div class="fw-bold text-info font-monospace fs-6">+ ETB ${formatMoney(s.opening_credit)}</div>
                                <div class="mt-1 pt-1 border-top border-secondary-subtle d-flex justify-content-between small" style="font-size: 0.7rem;">
                                    <span class="text-muted">Creditable Pool:</span>
                                    <span class="fw-bold text-dark font-monospace">ETB ${formatMoney(s.total_creditable_input)}</span>
                                </div>
                            </div>
                        </div>
                        <div class="col">
                            <div class="p-2.5 rounded-3 ${s.is_payable ? 'bg-danger-subtle border border-danger-subtle' : (s.is_credit ? 'bg-success-subtle border border-success-subtle' : 'bg-light border')}">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <span class="text-muted text-uppercase fw-bold" style="font-size: 0.65rem;">Signed Net VAT</span>
                                    <span class="badge ${s.badge_class} rounded-pill" style="font-size: 0.62rem;">${s.label}</span>
                                </div>
                                <div class="fw-bold font-monospace fs-6 ${s.text_class}">${s.signed_net_formatted}</div>
                                <div class="mt-1 pt-1 border-top border-secondary-subtle small text-truncate" style="font-size: 0.7rem;">
                                    ${s.is_payable ? `ETB ${formatMoney(s.payable_amount)} payable` : (s.is_credit ? `ETB ${formatMoney(s.credit_carry_forward)} carry forward` : 'Settled (0.00)')}
                                </div>
                            </div>
                        </div>
                        <div class="col">
                            <div class="p-2.5 rounded-3 bg-light border border-secondary-subtle">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <span class="text-muted text-uppercase fw-bold" style="font-size: 0.65rem;">3% Withholding</span>
                                    <span class="badge bg-warning-subtle text-dark rounded-pill" style="font-size: 0.62rem;">Separate</span>
                                </div>
                                <div class="fw-bold text-danger font-monospace fs-6">- ETB ${formatMoney(s.withholding_tax)}</div>
                                <div class="mt-1 pt-1 border-top border-secondary-subtle d-flex justify-content-between small" style="font-size: 0.7rem;">
                                    <span class="text-muted">Net Disbursed:</span>
                                    <span class="fw-bold text-success font-monospace">ETB ${formatMoney(s.net_disbursed)}</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Inner Navigation Tabs -->
                    <ul class="nav nav-tabs mb-3" id="drawerTab-${monthNum}" role="tablist">
                        <li class="nav-item">
                            <button class="nav-link active fw-semibold small" id="drawer-income-tab-${monthNum}" data-bs-toggle="tab" data-bs-target="#drawer-income-${monthNum}" type="button" role="tab">
                                <i class="fa-solid fa-arrow-down-long text-success me-1"></i>Where Income Came From (${s.income_count})
                            </button>
                        </li>
                        <li class="nav-item">
                            <button class="nav-link fw-semibold small" id="drawer-expense-tab-${monthNum}" data-bs-toggle="tab" data-bs-target="#drawer-expense-${monthNum}" type="button" role="tab">
                                <i class="fa-solid fa-arrow-up-long text-danger me-1"></i>Purchases &amp; Paid Expenses (${s.expense_count})
                            </button>
                        </li>
                        <li class="nav-item">
                            <button class="nav-link fw-semibold small" id="drawer-ocr-tab-${monthNum}" data-bs-toggle="tab" data-bs-target="#drawer-ocr-${monthNum}" type="button" role="tab">
                                <i class="fa-solid fa-receipt text-primary me-1"></i>Scanned OCR Invoices (${s.ocr_count})
                            </button>
                        </li>
                        <li class="nav-item">
                            <button class="nav-link fw-semibold small" id="drawer-audit-tab-${monthNum}" data-bs-toggle="tab" data-bs-target="#drawer-audit-${monthNum}" type="button" role="tab">
                                <i class="fa-solid fa-scale-balanced text-dark me-1"></i>ERCA Line 100 Audit
                            </button>
                        </li>
                    </ul>

                    <div class="tab-content" id="drawerTabContent-${monthNum}">
                        <!-- Income Tab -->
                        <div class="tab-pane fade show active" id="drawer-income-${monthNum}" role="tabpanel">
                            <div class="table-responsive" style="max-height: 380px;">
                                <table class="table table-hover table-sm align-middle mb-0">
                                    <thead class="bg-light sticky-top text-muted text-uppercase fw-bold" style="font-size: 0.68rem;">
                                        <tr>
                                            <th class="ps-3 py-2">#</th>
                                            <th class="py-2">Date (EC / GC)</th>
                                            <th class="py-2">Income #</th>
                                            <th class="py-2">Buyer / Customer</th>
                                            <th class="py-2">Project</th>
                                            <th class="py-2">Account</th>
                                            <th class="py-2 text-end">Sales Base</th>
                                            <th class="py-2 text-end text-danger">Output VAT</th>
                                            <th class="py-2 text-end pe-3">Gross Total</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y">${incomeRows}</tbody>
                                    <tfoot class="bg-light fw-bold">
                                        <tr>
                                            <td colspan="6" class="ps-3 py-2 small text-uppercase text-muted">Total Sales &amp; Output VAT</td>
                                            <td class="text-end py-2 font-monospace">ETB ${formatMoney(s.sales_base)}</td>
                                            <td class="text-end py-2 font-monospace text-danger">ETB ${formatMoney(s.output_vat)}</td>
                                            <td class="text-end py-2 font-monospace text-success pe-3">ETB ${formatMoney(s.gross_income)}</td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </div>

                        <!-- Expense Tab -->
                        <div class="tab-pane fade" id="drawer-expense-${monthNum}" role="tabpanel">
                            <div class="table-responsive" style="max-height: 380px;">
                                <table class="table table-hover table-sm align-middle mb-0">
                                    <thead class="bg-light sticky-top text-muted text-uppercase fw-bold" style="font-size: 0.68rem;">
                                        <tr>
                                            <th class="ps-3 py-2">#</th>
                                            <th class="py-2">Date (EC / GC)</th>
                                            <th class="py-2">Voucher #</th>
                                            <th class="py-2">Payee / Vendor</th>
                                            <th class="py-2">Category</th>
                                            <th class="py-2">Account</th>
                                            <th class="py-2 text-end">Purchases Base</th>
                                            <th class="py-2 text-end text-success">Input VAT</th>
                                            <th class="py-2 text-end text-danger">3% WHT</th>
                                            <th class="py-2 text-end pe-3">Net Disbursed</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y">${expenseRows}</tbody>
                                    <tfoot class="bg-light fw-bold">
                                        <tr>
                                            <td colspan="6" class="ps-3 py-2 small text-uppercase text-muted">Total Purchases &amp; Deductions</td>
                                            <td class="text-end py-2 font-monospace">ETB ${formatMoney(s.purchases_base)}</td>
                                            <td class="text-end py-2 font-monospace text-success">ETB ${formatMoney(s.input_vat)}</td>
                                            <td class="text-end py-2 font-monospace text-danger">- ETB ${formatMoney(s.withholding_tax)}</td>
                                            <td class="text-end py-2 font-monospace text-dark pe-3">ETB ${formatMoney(s.net_disbursed)}</td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </div>

                        <!-- OCR Tab -->
                        <div class="tab-pane fade" id="drawer-ocr-${monthNum}" role="tabpanel">
                            <div class="table-responsive" style="max-height: 380px;">
                                <table class="table table-hover table-sm align-middle mb-0">
                                    <thead class="bg-light sticky-top text-muted text-uppercase fw-bold" style="font-size: 0.68rem;">
                                        <tr>
                                            <th class="ps-3 py-2">#</th>
                                            <th class="py-2">Date (EC / GC)</th>
                                            <th class="py-2">FS # / Receipt</th>
                                            <th class="py-2">Vendor / TIN</th>
                                            <th class="py-2">Project</th>
                                            <th class="py-2">Description</th>
                                            <th class="py-2 text-end">Base Value</th>
                                            <th class="py-2 text-end text-success">VAT Amount</th>
                                            <th class="py-2 text-end pe-3">Total Value</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y">${ocrRows}</tbody>
                                </table>
                            </div>
                        </div>

                        <!-- Audit Tab -->
                        <div class="tab-pane fade" id="drawer-audit-${monthNum}" role="tabpanel">
                            <div class="p-3 bg-light rounded-3">
                                <div class="row g-3 align-items-center">
                                    <div class="col-md-7">
                                        <ul class="list-group list-group-flush rounded-3 border">
                                            <li class="list-group-item d-flex justify-content-between py-2 small">
                                                <span>1. Taxable Sales Base (ገቢ)</span>
                                                <span class="font-monospace fw-bold">ETB ${formatMoney(s.sales_base)}</span>
                                            </li>
                                            <li class="list-group-item d-flex justify-content-between py-2 small bg-danger-subtle bg-opacity-25">
                                                <span class="text-danger fw-semibold">2. Output VAT Collected (15%)</span>
                                                <span class="font-monospace fw-bold text-danger">ETB ${formatMoney(s.output_vat)}</span>
                                            </li>
                                            <li class="list-group-item d-flex justify-content-between py-2 small">
                                                <span>3. Taxable Purchases Base (ግዢ)</span>
                                                <span class="font-monospace fw-bold">ETB ${formatMoney(s.purchases_base)}</span>
                                            </li>
                                            <li class="list-group-item d-flex justify-content-between py-2 small bg-success-subtle bg-opacity-25">
                                                <span class="text-success fw-semibold">4. Creditable Input VAT (15%)</span>
                                                <span class="font-monospace fw-bold text-success">ETB ${formatMoney(s.input_vat)}</span>
                                            </li>
                                            <li class="list-group-item d-flex justify-content-between py-2 small bg-info-subtle bg-opacity-25">
                                                <span class="text-info fw-semibold">5. Opening Credit from Previous Month</span>
                                                <span class="font-monospace fw-bold text-info">+ ETB ${formatMoney(s.opening_credit)}</span>
                                            </li>
                                            <li class="list-group-item d-flex justify-content-between py-2 small fw-bold bg-white">
                                                <span>6. Total Creditable Pool = (4) + (5)</span>
                                                <span class="font-monospace">ETB ${formatMoney(s.total_creditable_input)}</span>
                                            </li>
                                            <li class="list-group-item d-flex justify-content-between py-2.5 border-top border-2 bg-light">
                                                <span class="fw-bold">7. Signed Net VAT = (6) &minus; (2)</span>
                                                <span class="font-monospace fs-6 fw-bold ${s.text_class}">${s.signed_net_formatted}</span>
                                            </li>
                                        </ul>
                                    </div>
                                    <div class="col-md-5">
                                        <div class="p-3 rounded-3 border ${s.is_payable ? 'border-danger bg-danger-subtle text-danger' : (s.is_credit ? 'border-success bg-success-subtle text-success' : 'border-secondary bg-light')}">
                                            <h6 class="fw-bold mb-1">${s.is_payable ? 'VAT Remittance Due' : (s.is_credit ? 'Credit Carried Forward' : 'Zero Balance')}</h6>
                                            <div class="small">${s.is_payable ? `ETB ${formatMoney(s.payable_amount)} payable to ERCA by 30th.` : (s.is_credit ? `ETB ${formatMoney(s.credit_carry_forward)} carries forward to next month.` : 'Fully reconciled.')}</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        `;
    }

    // ── FULLSCREEN MODAL: View movements in popup modal ───────────────────────
    function openMonthMovementsModal(ethYear, ethMonth, monthEn, monthAm) {
        let modalEl = document.getElementById('monthMovementsModal');
        if (!modalEl) return;

        // Ensure modal is attached directly to document.body outside of transformed containers
        if (modalEl.parentElement !== document.body) {
            document.body.appendChild(modalEl);
        }

        // Set initial modal header & filter link
        document.getElementById('mMovementsTitle').innerText = `${monthEn} (${monthAm}) ${ethYear} ዓ.ም. — Financial Movements`;
        document.getElementById('mMovementsWindow').innerText = 'Loading Gregorian window and transaction audit...';
        
        const filterBtn = document.getElementById('mMovementsFilterBtn');
        if (filterBtn) {
            const baseUrl = "{{ route('finance.tax-deductions.index') }}";
            filterBtn.href = `${baseUrl}?ethiopian_year=${ethYear}&ethiopian_month=${ethMonth}`;
        }

        // Display spinner, hide content & error
        document.getElementById('mMovementsSpinner').style.display = 'block';
        document.getElementById('mMovementsContent').style.display = 'none';
        document.getElementById('mMovementsError').style.display = 'none';

        // Reset tab buttons and panes to initial state (Tab 1 active) without triggering conflicting CSS fade transitions
        const tabButtons = modalEl.querySelectorAll('#monthMovementTab .nav-link');
        tabButtons.forEach((btn, idx) => {
            btn.classList.toggle('active', idx === 0);
            btn.setAttribute('aria-selected', idx === 0 ? 'true' : 'false');
        });
        const tabPanes = modalEl.querySelectorAll('#monthMovementTabContent .tab-pane');
        tabPanes.forEach((pane, idx) => {
            if (idx === 0) {
                pane.classList.add('show', 'active');
            } else {
                pane.classList.remove('show', 'active');
            }
        });

        // Clear any previous table search inputs
        modalEl.querySelectorAll('input[type="text"]').forEach(input => {
            input.value = '';
        });

        // Show Modal safely with fallback
        if (window.bootstrap && bootstrap.Modal) {
            const bsModal = bootstrap.Modal.getOrCreateInstance(modalEl);
            bsModal.show();
        } else if (window.$ && typeof $(modalEl).modal === 'function') {
            $(modalEl).modal('show');
        }

        const cacheKey = `${ethYear}-${ethMonth}`;
        if (movementsCache[cacheKey]) {
            currentMovementsData = movementsCache[cacheKey];
            renderMovementsData(currentMovementsData);
            document.getElementById('mMovementsSpinner').style.display = 'none';
            document.getElementById('mMovementsContent').style.display = 'block';
            return;
        }

        // Fetch movements from server
        const url = "{{ route('finance.tax-deductions.month-movements') }}?ethiopian_year=" + ethYear + "&ethiopian_month=" + ethMonth;
        fetch(url, {
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(res => {
            if (!res.ok) throw new Error('HTTP error ' + res.status);
            return res.json();
        })
        .then(data => {
            if (data.success) {
                movementsCache[cacheKey] = data;
                currentMovementsData = data;
                renderMovementsData(data);
                document.getElementById('mMovementsSpinner').style.display = 'none';
                document.getElementById('mMovementsContent').style.display = 'block';
            } else {
                throw new Error(data.message || 'Error loading transaction movements');
            }
        })
        .catch(err => {
            console.error('Failed to load month movements:', err);
            document.getElementById('mMovementsSpinner').style.display = 'none';
            document.getElementById('mMovementsErrorMsg').innerText = err.message || 'Failed to load transaction movements.';
            document.getElementById('mMovementsError').style.display = 'block';
        });
    }

    function renderMovementsData(data) {
        const m = data.month;
        const s = data.summary;

        // Header info
        document.getElementById('mMovementsTitle').innerHTML = `<i class="fa-solid fa-calendar-day me-2 text-warning"></i>${m.title} &bull; Financial Movements &amp; Tax Audit`;
        document.getElementById('mMovementsWindow').innerText = `Gregorian Period Window: ${m.gregorian_window}`;

        // Top KPI Strip
        document.getElementById('mMovementsSalesBase').innerText = 'ETB ' + formatMoney(s.sales_base);
        document.getElementById('mMovementsOutputVat').innerText = 'ETB ' + formatMoney(s.output_vat);
        document.getElementById('mMovementsIncomeCount').innerText = `${s.income_count} receipts`;

        document.getElementById('mMovementsPurchasesBase').innerText = 'ETB ' + formatMoney(s.purchases_base);
        document.getElementById('mMovementsInputVat').innerText = 'ETB ' + formatMoney(s.input_vat);
        document.getElementById('mMovementsExpenseCount').innerText = `${s.expense_count} expenses`;

        document.getElementById('mMovementsOpeningCredit').innerText = '+ ETB ' + formatMoney(s.opening_credit);
        document.getElementById('mMovementsTotalCreditable').innerText = 'ETB ' + formatMoney(s.total_creditable_input);

        // Signed Net VAT presentation
        const netEl = document.getElementById('mMovementsSignedNet');
        const badgeEl = document.getElementById('mMovementsNetBadge');
        const cardEl = document.getElementById('mMovementsNetCard');
        const instEl = document.getElementById('mMovementsNetInstruction');

        netEl.innerText = s.signed_net_formatted;
        netEl.className = `fs-4 fw-bold font-monospace ${s.text_class}`;
        badgeEl.className = `badge ${s.badge_class} rounded-pill small`;
        badgeEl.innerText = s.label;

        if (s.is_payable) {
            cardEl.className = 'card border-0 shadow-sm kpi-card rounded-4 h-100 bg-white border-top border-4 border-danger bg-danger bg-opacity-10';
            instEl.innerText = `ETB ${formatMoney(s.payable_amount)} payable to ERCA`;
        } else if (s.is_credit) {
            cardEl.className = 'card border-0 shadow-sm kpi-card rounded-4 h-100 bg-white border-top border-4 border-success bg-success bg-opacity-10';
            instEl.innerText = `ETB ${formatMoney(s.credit_carry_forward)} carries forward to next month`;
        } else {
            cardEl.className = 'card border-0 shadow-sm kpi-card rounded-4 h-100 bg-white border-top border-4 border-secondary';
            instEl.innerText = 'Zero balance settled (0.00)';
        }

        // Withholding Tax & Net Disbursed
        document.getElementById('mMovementsWht').innerText = '- ETB ' + formatMoney(s.withholding_tax);
        document.getElementById('mMovementsNetDisbursed').innerText = 'ETB ' + formatMoney(s.net_disbursed);

        // Tab count badges
        document.getElementById('mTabIncomeBadge').innerText = s.income_count;
        document.getElementById('mTabExpenseBadge').innerText = s.expense_count;
        document.getElementById('mTabOcrBadge').innerText = s.ocr_count;

        // 1. Render Incomes Table (Where Income Came From)
        const incomeTbody = document.getElementById('mIncomeTableBody');
        incomeTbody.innerHTML = '';
        if (data.incomes && data.incomes.length > 0) {
            data.incomes.forEach((inc, idx) => {
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td class="ps-3 text-muted font-monospace small">${idx + 1}</td>
                    <td>
                        <span class="fw-bold font-monospace text-dark d-block">${inc.date_ec}</span>
                        <small class="text-muted" style="font-size:0.75rem;">${inc.date_gc}</small>
                    </td>
                    <td>
                        <span class="badge bg-light text-dark border font-monospace">${inc.income_no}</span>
                        ${inc.receipt_no && inc.receipt_no !== '-' ? `<small class="text-muted d-block font-monospace">Ref: ${inc.receipt_no}</small>` : ''}
                    </td>
                    <td>
                        <span class="fw-semibold text-dark d-block">${inc.buyer_name}</span>
                        ${inc.buyer_tin && inc.buyer_tin !== '-' ? `<small class="text-muted font-monospace" style="font-size:0.75rem;">TIN: ${inc.buyer_tin}</small>` : ''}
                    </td>
                    <td>
                        <span class="badge bg-secondary-subtle text-secondary rounded-pill small">${inc.project_name}</span>
                    </td>
                    <td class="small text-muted">
                        <i class="fa-solid fa-building-columns me-1 text-primary"></i>${inc.account_name}
                    </td>
                    <td class="text-end font-monospace">ETB ${formatMoney(inc.sales_base)}</td>
                    <td class="text-end font-monospace text-danger fw-semibold">ETB ${formatMoney(inc.output_vat)}</td>
                    <td class="text-end font-monospace text-success fw-bold pe-3">ETB ${formatMoney(inc.gross_amount)}</td>
                `;
                incomeTbody.appendChild(tr);
            });
        } else {
            incomeTbody.innerHTML = `
                <tr>
                    <td colspan="9" class="text-center py-4 text-muted">
                        <i class="fa-solid fa-folder-open me-2 text-secondary"></i>No income receipts recorded in this Ethiopian month.
                    </td>
                </tr>
            `;
        }
        document.getElementById('mIncomeTotalBase').innerText = 'ETB ' + formatMoney(s.sales_base);
        document.getElementById('mIncomeTotalVat').innerText = 'ETB ' + formatMoney(s.output_vat);
        document.getElementById('mIncomeTotalGross').innerText = 'ETB ' + formatMoney(s.gross_income);

        // 2. Render Expenses Table (Purchases & Expenditures)
        const expenseTbody = document.getElementById('mExpenseTableBody');
        expenseTbody.innerHTML = '';
        if (data.expenses && data.expenses.length > 0) {
            data.expenses.forEach((exp, idx) => {
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td class="ps-3 text-muted font-monospace small">${idx + 1}</td>
                    <td>
                        <span class="fw-bold font-monospace text-dark d-block">${exp.date_ec}</span>
                        <small class="text-muted" style="font-size:0.75rem;">${exp.date_gc}</small>
                    </td>
                    <td>
                        <span class="badge bg-light text-dark border font-monospace">${exp.request_number}</span>
                        ${exp.payment_reference && exp.payment_reference !== '-' ? `<small class="text-muted d-block font-monospace">Ref: ${exp.payment_reference}</small>` : ''}
                    </td>
                    <td>
                        <span class="fw-semibold text-dark d-block">${exp.payee}</span>
                    </td>
                    <td>
                        <span class="badge bg-info-subtle text-info border border-info-subtle rounded-pill small">${exp.category}</span>
                        <small class="text-muted d-block" style="font-size:0.75rem;">${exp.project_name}</small>
                    </td>
                    <td class="small text-muted">
                        <i class="fa-solid fa-wallet me-1 text-danger"></i>${exp.account_name}
                    </td>
                    <td class="text-end font-monospace">ETB ${formatMoney(exp.base_amount)}</td>
                    <td class="text-end font-monospace text-success fw-semibold">ETB ${formatMoney(exp.vat_amount)}</td>
                    <td class="text-end font-monospace text-danger">${exp.withholding_amount > 0 ? '- ETB ' + formatMoney(exp.withholding_amount) : '-'}</td>
                    <td class="text-end font-monospace text-dark fw-bold pe-3">ETB ${formatMoney(exp.net_disbursed)}</td>
                `;
                expenseTbody.appendChild(tr);
            });
        } else {
            expenseTbody.innerHTML = `
                <tr>
                    <td colspan="10" class="text-center py-4 text-muted">
                        <i class="fa-solid fa-folder-open me-2 text-secondary"></i>No purchases or paid expenses recorded for this Ethiopian month.
                    </td>
                </tr>
            `;
        }
        document.getElementById('mExpenseTotalBase').innerText = 'ETB ' + formatMoney(s.purchases_base);
        document.getElementById('mExpenseTotalVat').innerText = 'ETB ' + formatMoney(s.input_vat);
        document.getElementById('mExpenseTotalWht').innerText = '- ETB ' + formatMoney(s.withholding_tax);
        document.getElementById('mExpenseTotalNet').innerText = 'ETB ' + formatMoney(s.net_disbursed);

        // 3. Render OCR Invoices Table
        const ocrTbody = document.getElementById('mOcrTableBody');
        ocrTbody.innerHTML = '';
        let ocrSumBase = 0, ocrSumVat = 0, ocrSumAfter = 0;
        if (data.ocr_items && data.ocr_items.length > 0) {
            data.ocr_items.forEach((ocr, idx) => {
                ocrSumBase += ocr.base_value;
                ocrSumVat += ocr.vat_amount;
                ocrSumAfter += ocr.value_after_vat;

                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td class="ps-3 text-muted font-monospace small">${idx + 1}</td>
                    <td>
                        <span class="fw-bold font-monospace text-dark d-block">${ocr.date_ec}</span>
                        <small class="text-muted" style="font-size:0.75rem;">${ocr.date_gc}</small>
                    </td>
                    <td>
                        <span class="badge bg-light text-primary border font-monospace">${ocr.fs_no}</span>
                        <small class="text-muted d-block" style="font-size:0.75rem;">${ocr.receipt_number}</small>
                    </td>
                    <td>
                        <span class="fw-semibold text-dark d-block">${ocr.vendor_name}</span>
                        <small class="text-muted font-monospace" style="font-size:0.75rem;">TIN: ${ocr.vendor_tin}</small>
                    </td>
                    <td>
                        <span class="badge bg-secondary-subtle text-secondary rounded-pill small">${ocr.project_name}</span>
                        <small class="text-muted d-block" style="font-size:0.75rem;">${ocr.category}</small>
                    </td>
                    <td class="small text-dark text-truncate" style="max-width: 220px;" title="${ocr.item_description}">
                        ${ocr.item_description}
                    </td>
                    <td class="text-end font-monospace">ETB ${formatMoney(ocr.base_value)}</td>
                    <td class="text-end font-monospace text-success fw-semibold">ETB ${formatMoney(ocr.vat_amount)}</td>
                    <td class="text-end font-monospace text-dark fw-bold pe-3">ETB ${formatMoney(ocr.value_after_vat)}</td>
                `;
                ocrTbody.appendChild(tr);
            });
        } else {
            ocrTbody.innerHTML = `
                <tr>
                    <td colspan="9" class="text-center py-4 text-muted">
                        <i class="fa-solid fa-folder-open me-2 text-secondary"></i>No OCR scanned receipts recorded for this Ethiopian month.
                    </td>
                </tr>
            `;
        }
        document.getElementById('mOcrTotalBase').innerText = 'ETB ' + formatMoney(ocrSumBase);
        document.getElementById('mOcrTotalVat').innerText = 'ETB ' + formatMoney(ocrSumVat);
        document.getElementById('mOcrTotalAfter').innerText = 'ETB ' + formatMoney(ocrSumAfter);

        // 4. Render ERCA Line 100 Audit Tab
        document.getElementById('mAuditPeriodBadge').innerText = `${m.title}`;
        document.getElementById('mAuditSalesBase').innerText = formatMoney(s.sales_base);
        document.getElementById('mAuditOutputVat').innerText = formatMoney(s.output_vat);
        document.getElementById('mAuditPurchasesBase').innerText = formatMoney(s.purchases_base);
        document.getElementById('mAuditInputVat').innerText = formatMoney(s.input_vat);
        document.getElementById('mAuditOpeningCredit').innerText = '+ ' + formatMoney(s.opening_credit);
        document.getElementById('mAuditTotalCreditable').innerText = formatMoney(s.total_creditable_input);
        document.getElementById('mAuditNetSigned').innerText = s.signed_net_formatted;
        document.getElementById('mAuditNetSigned').className = `font-monospace fs-5 fw-bold ${s.text_class}`;
        document.getElementById('mAuditWht').innerText = '- ' + formatMoney(s.withholding_tax);

        // Audit Decision Banner
        const decisionBanner = document.getElementById('mAuditDecisionBanner');
        if (s.is_payable) {
            decisionBanner.className = 'p-3 rounded-3 mb-3 bg-danger bg-opacity-10 border border-danger text-danger';
            decisionBanner.innerHTML = `
                <div class="d-flex align-items-center gap-2 mb-1">
                    <i class="fa-solid fa-file-invoice-dollar fs-5"></i>
                    <h6 class="fw-bold mb-0">Remittance Required: ETB ${formatMoney(s.payable_amount)}</h6>
                </div>
                <div class="small">Output VAT on sales exceeded allowable creditable input VAT. This amount is legally payable to ERCA by the 30th of the following Ethiopian month.</div>
            `;
        } else if (s.is_credit) {
            decisionBanner.className = 'p-3 rounded-3 mb-3 bg-success bg-opacity-10 border border-success text-success';
            decisionBanner.innerHTML = `
                <div class="d-flex align-items-center gap-2 mb-1">
                    <i class="fa-solid fa-arrow-turn-up fs-5"></i>
                    <h6 class="fw-bold mb-0">Credit Carried Forward: + ETB ${formatMoney(s.credit_carry_forward)}</h6>
                </div>
                <div class="small">Input VAT on creditable business purchases exceeded Output VAT. This positive credit automatically carries forward as an opening balance into the next Ethiopian month.</div>
            `;
        } else {
            decisionBanner.className = 'p-3 rounded-3 mb-3 bg-secondary bg-opacity-10 border border-secondary text-secondary';
            decisionBanner.innerHTML = `
                <div class="d-flex align-items-center gap-2 mb-1">
                    <i class="fa-solid fa-scale-balanced fs-5"></i>
                    <h6 class="fw-bold mb-0">Zero Balance Reconciled (ETB 0.00)</h6>
                </div>
                <div class="small">Output VAT exactly balances with creditable Input VAT. No payment is owed and no credit carries forward.</div>
            `;
        }

        // Footer summary
        document.getElementById('mMovementsFooterSummary').innerHTML = `
            <i class="fa-solid fa-circle-check text-success me-1"></i>
            Total <strong>${s.total_count}</strong> transactions loaded: <strong>${s.income_count}</strong> income receipts &bull; <strong>${s.expense_count}</strong> expense vouchers &bull; <strong>${s.ocr_count}</strong> scanned OCR invoices.
        `;
    }

    function filterMovementTable(tableId, query) {
        const table = document.getElementById(tableId);
        if (!table) return;
        const tbody = table.querySelector('tbody');
        if (!tbody) return;
        const trs = tbody.querySelectorAll('tr');
        const q = (query || '').toLowerCase().trim();

        trs.forEach(tr => {
            const text = tr.innerText.toLowerCase();
            tr.style.display = text.includes(q) ? '' : 'none';
        });
    }

    function printMonthMovements() {
        if (!currentMovementsData) return;
        const d = currentMovementsData;
        const m = d.month;
        const s = d.summary;

        const printWindow = window.open('', '_blank');
        if (!printWindow) {
            alert('Popup blocker prevented print window from opening. Please allow popups for this site.');
            return;
        }

        let incomeRowsHtml = '';
        d.incomes.forEach((inc, i) => {
            incomeRowsHtml += `
                <tr>
                    <td>${i + 1}</td>
                    <td>${inc.date_ec}<br><small>${inc.date_gc}</small></td>
                    <td>${inc.income_no}<br><small>${inc.receipt_no}</small></td>
                    <td>${inc.buyer_name}<br><small>TIN: ${inc.buyer_tin}</small></td>
                    <td>${inc.project_name}</td>
                    <td>${inc.account_name}</td>
                    <td style="text-align:right;">${formatMoney(inc.sales_base)}</td>
                    <td style="text-align:right;">${formatMoney(inc.output_vat)}</td>
                    <td style="text-align:right; font-weight:bold;">${formatMoney(inc.gross_amount)}</td>
                </tr>
            `;
        });

        let expenseRowsHtml = '';
        d.expenses.forEach((exp, i) => {
            expenseRowsHtml += `
                <tr>
                    <td>${i + 1}</td>
                    <td>${exp.date_ec}<br><small>${exp.date_gc}</small></td>
                    <td>${exp.request_number}</td>
                    <td>${exp.payee}</td>
                    <td>${exp.category} / ${exp.project_name}</td>
                    <td>${exp.account_name}</td>
                    <td style="text-align:right;">${formatMoney(exp.base_amount)}</td>
                    <td style="text-align:right;">${formatMoney(exp.vat_amount)}</td>
                    <td style="text-align:right;">${formatMoney(exp.withholding_amount)}</td>
                    <td style="text-align:right; font-weight:bold;">${formatMoney(exp.net_disbursed)}</td>
                </tr>
            `;
        });

        printWindow.document.write(`
            <!DOCTYPE html>
            <html>
            <head>
                <title>Financial Movement & Tax Audit — ${m.title}</title>
                <style>
                    body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif; padding: 24px; color: #1e293b; }
                    h2, h3, h4 { margin: 0 0 6px 0; }
                    .header { border-bottom: 2px solid #0f172a; padding-bottom: 14px; margin-bottom: 20px; }
                    .kpi-grid { display: grid; grid-template-columns: repeat(5, 1fr); gap: 12px; margin-bottom: 20px; }
                    .kpi-card { border: 1px solid #cbd5e1; border-radius: 8px; padding: 10px; background: #f8fafc; }
                    .kpi-card .lbl { font-size: 11px; text-transform: uppercase; color: #64748b; font-weight: bold; }
                    .kpi-card .val { font-size: 16px; font-weight: bold; margin-top: 4px; font-family: monospace; }
                    table { width: 100%; border-collapse: collapse; margin-bottom: 24px; font-size: 12px; }
                    th, td { border: 1px solid #cbd5e1; padding: 6px 8px; text-align: left; }
                    th { background: #f1f5f9; text-transform: uppercase; font-size: 11px; }
                    .section-title { font-size: 14px; font-weight: bold; margin: 18px 0 8px 0; border-left: 4px solid #2563eb; padding-left: 8px; }
                    @media print { body { padding: 0; } }
                </style>
            </head>
            <body>
                <div class="header">
                    <h2>Wechecha Construction &bull; Financial Movements &amp; Tax Audit</h2>
                    <h4>${m.title} &mdash; ${m.gregorian_window}</h4>
                    <small>Generated on: ${new Date().toLocaleString()}</small>
                </div>

                <div class="kpi-grid">
                    <div class="kpi-card">
                        <div class="lbl">Sales Base</div>
                        <div class="val">ETB ${formatMoney(s.sales_base)}</div>
                        <small>Output VAT: ETB ${formatMoney(s.output_vat)}</small>
                    </div>
                    <div class="kpi-card">
                        <div class="lbl">Purchases Base</div>
                        <div class="val">ETB ${formatMoney(s.purchases_base)}</div>
                        <small>Input VAT: ETB ${formatMoney(s.input_vat)}</small>
                    </div>
                    <div class="kpi-card">
                        <div class="lbl">Opening Credit</div>
                        <div class="val">+ ETB ${formatMoney(s.opening_credit)}</div>
                        <small>Total Creditable: ETB ${formatMoney(s.total_creditable_input)}</small>
                    </div>
                    <div class="kpi-card">
                        <div class="lbl">Signed Net VAT</div>
                        <div class="val">${s.signed_net_formatted}</div>
                        <small>${s.label}</small>
                    </div>
                    <div class="kpi-card">
                        <div class="lbl">3% Withholding</div>
                        <div class="val">- ETB ${formatMoney(s.withholding_tax)}</div>
                        <small>Net Disbursed: ETB ${formatMoney(s.net_disbursed)}</small>
                    </div>
                </div>

                <div class="section-title">1. Incoming Transactions (Company Sales &amp; Output VAT Receipts)</div>
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Date (EC / GC)</th>
                            <th>Income # / Ref</th>
                            <th>Customer / Payer</th>
                            <th>Project</th>
                            <th>Receiving Account</th>
                            <th style="text-align:right;">Sales Base</th>
                            <th style="text-align:right;">Output VAT</th>
                            <th style="text-align:right;">Gross Total</th>
                        </tr>
                    </thead>
                    <tbody>${incomeRowsHtml || '<tr><td colspan="9" style="text-align:center;">No income records</td></tr>'}</tbody>
                </table>

                <div class="section-title">2. Outgoing Transactions (Purchases, Expenditures &amp; Input VAT)</div>
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Date (EC / GC)</th>
                            <th>Voucher #</th>
                            <th>Payee / Vendor</th>
                            <th>Category / Project</th>
                            <th>Paying Account</th>
                            <th style="text-align:right;">Purchases Base</th>
                            <th style="text-align:right;">15% Input VAT</th>
                            <th style="text-align:right;">3% WHT</th>
                            <th style="text-align:right;">Net Disbursed</th>
                        </tr>
                    </thead>
                    <tbody>${expenseRowsHtml || '<tr><td colspan="10" style="text-align:center;">No expense records</td></tr>'}</tbody>
                </table>
            </body>
            </html>
        `);
        printWindow.document.close();
        printWindow.focus();
        setTimeout(() => { printWindow.print(); }, 400);
    }

    // ── Clear Tax (Start from Zero) Handlers ──────────────────────────────────
    function openAdminClearModalForMonth(ethYear, ethMonth, monthLabel) {
        const modalEl = document.getElementById('adminClearTaxModal');
        if (!modalEl) return;

        const yearSelect = document.getElementById('admin_clear_eth_year');
        const monthSelect = document.getElementById('admin_clear_eth_month');

        if (yearSelect && ethYear) {
            yearSelect.value = ethYear;
        }
        if (monthSelect && ethMonth) {
            monthSelect.value = ethMonth;
        }

        // Reset to month scope
        const monthScopeRadio = modalEl.querySelector('input[name="scope"][value="month"]');
        if (monthScopeRadio) {
            monthScopeRadio.checked = true;
            toggleClearScope('month');
        }

        // Update badge label
        updateClearModalMonthLabel(monthLabel);

        // Reset to clear action mode
        const clearRadio = modalEl.querySelector('input[name="action_type"][value="clear"]');
        if (clearRadio) {
            clearRadio.checked = true;
            toggleAdminClearActionMode('clear');
        }

        if (window.bootstrap && bootstrap.Modal) {
            const bsModal = bootstrap.Modal.getOrCreateInstance(modalEl);
            bsModal.show();
        } else if (window.$ && typeof $(modalEl).modal === 'function') {
            $(modalEl).modal('show');
        }
    }

    function toggleClearScope(scope) {
        const optScopeMonth = document.getElementById('opt-scope-month');
        const optScopeAll = document.getElementById('opt-scope-all');
        const monthBox = document.getElementById('clearScopeMonthBox');
        const badgeLabel = document.getElementById('clear_modal_month_label');

        if (scope === 'all_active') {
            if (optScopeMonth) {
                optScopeMonth.style.borderColor = '#e2e8f0';
                optScopeMonth.style.backgroundColor = '#ffffff';
            }
            if (optScopeAll) {
                optScopeAll.style.borderColor = '#f59e0b';
                optScopeAll.style.backgroundColor = '#fffbeb';
            }
            if (monthBox) {
                monthBox.style.opacity = '0.5';
                monthBox.style.pointerEvents = 'none';
            }
            if (badgeLabel) {
                badgeLabel.textContent = 'All Active Pending';
            }
        } else {
            if (optScopeMonth) {
                optScopeMonth.style.borderColor = '#f59e0b';
                optScopeMonth.style.backgroundColor = '#fffbeb';
            }
            if (optScopeAll) {
                optScopeAll.style.borderColor = '#e2e8f0';
                optScopeAll.style.backgroundColor = '#ffffff';
            }
            if (monthBox) {
                monthBox.style.opacity = '1';
                monthBox.style.pointerEvents = 'auto';
            }
            updateClearModalMonthLabel();
        }
    }

    function updateClearModalMonthLabel(customLabel) {
        const badgeLabel = document.getElementById('clear_modal_month_label');
        if (!badgeLabel) return;
        if (customLabel) {
            badgeLabel.textContent = customLabel;
            return;
        }
        const monthSelect = document.getElementById('admin_clear_eth_month');
        if (monthSelect && monthSelect.selectedIndex >= 0) {
            badgeLabel.textContent = monthSelect.options[monthSelect.selectedIndex].text.trim();
        }
    }

    function toggleAdminClearActionMode(mode) {
        const optClear = document.getElementById('opt-action-clear');
        const optReopen = document.getElementById('opt-action-reopen');
        const submitBtn = document.getElementById('adminClearSubmitBtn');

        if (mode === 'reopen') {
            if (optClear) {
                optClear.style.borderColor = '#e2e8f0';
                optClear.style.backgroundColor = '#ffffff';
            }
            if (optReopen) {
                optReopen.style.borderColor = '#0284c7';
                optReopen.style.backgroundColor = '#f0f9ff';
            }
            if (submitBtn) {
                submitBtn.className = 'btn btn-primary fw-bold btn-sm rounded-pill px-4 shadow-sm';
                submitBtn.innerHTML = '<i class="fa-solid fa-rotate-left me-1.5"></i> <span>Confirm &amp; Re-open Month</span>';
            }
        } else {
            if (optClear) {
                optClear.style.borderColor = '#f59e0b';
                optClear.style.backgroundColor = '#fffbeb';
            }
            if (optReopen) {
                optReopen.style.borderColor = '#e2e8f0';
                optReopen.style.backgroundColor = '#ffffff';
            }
            if (submitBtn) {
                submitBtn.className = 'btn btn-warning text-dark fw-bold btn-sm rounded-pill px-4 shadow-sm';
                submitBtn.innerHTML = '<i class="fa-solid fa-broom me-1.5"></i> <span>Confirm &amp; Clear Month to Zero</span>';
            }
        }
    }

    document.addEventListener('DOMContentLoaded', function () {
        // Guarantee modal is never trapped inside .content-wrapper
        const clearModal = document.getElementById('adminClearTaxModal');
        if (clearModal && clearModal.parentElement !== document.body) {
            document.body.appendChild(clearModal);
        }

        const adminForm = document.getElementById('adminClearTaxForm');
        if (adminForm) {
            adminForm.addEventListener('submit', function (e) {
                const actionType = adminForm.querySelector('input[name="action_type"]:checked')?.value || 'clear';
                const scope = adminForm.querySelector('input[name="scope"]:checked')?.value || 'month';
                const monthSelect = document.getElementById('admin_clear_eth_month');
                const yearSelect = document.getElementById('admin_clear_eth_year');
                const monthName = monthSelect?.options[monthSelect.selectedIndex]?.text?.trim() || 'selected month';
                const yearName = yearSelect?.options[yearSelect.selectedIndex]?.text?.trim() || '';

                const targetDesc = (scope === 'all_active') ? 'ALL current active unsettled tax liabilities' : `${monthName} ${yearName}`;

                if (actionType === 'clear') {
                    if (!confirm(`Are you sure you want to CLEAR tax liabilities for ${targetDesc} to ZERO (0.00 ETB)?\n\nAll accrued deductions will be stamped as settled and your active balance will reset to 0.00 ETB.`)) {
                        e.preventDefault();
                    }
                } else {
                    if (!confirm(`Are you sure you want to RE-OPEN ${targetDesc}?\n\nPreviously settled records will be returned to active unsettled status.`)) {
                        e.preventDefault();
                    }
                }
            });
        }
    });
</script>
@endsection
