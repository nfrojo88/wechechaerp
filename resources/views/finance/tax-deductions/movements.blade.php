@extends('layouts.app')

@section('title', 'Financial Movements & Tax Audit — ' . ($movementsData['month']['title'] ?? 'Ethiopian Month'))

@section('content')
<div class="container-fluid px-3 px-md-4 py-4" id="movementsPageContainer">

    {{-- ── Breadcrumb Navigation ──────────────────────────────────────────────── --}}
    <nav aria-label="breadcrumb" class="mb-3 d-print-none">
        <ol class="breadcrumb mb-0 py-1 px-2 rounded-3 bg-light bg-opacity-75 small">
            <li class="breadcrumb-item"><a href="{{ route('dashboard') }}" class="text-decoration-none text-muted"><i class="fa-solid fa-house me-1"></i>Dashboard</a></li>
            <li class="breadcrumb-item"><a href="{{ route('finance.tax-deductions.index') }}" class="text-decoration-none text-muted">VAT &amp; Withholding Tax</a></li>
            <li class="breadcrumb-item active text-dark fw-semibold" aria-current="page">Financial Movements &amp; Tax Audit</li>
        </ol>
    </nav>

    {{-- ── Main Header & Period Filter Hub ────────────────────────────────────── --}}
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
                            <h1 class="h4 fw-bold text-dark mb-0">Financial Movements &amp; Tax Audit</h1>
                            <span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill px-2.5 py-1 small fw-semibold font-monospace">
                                {{ $movementsData['month']['title'] ?? 'Period' }}
                            </span>
                            @if(!empty($movementsData['summary']['is_cleared']))
                                <span class="badge bg-success text-white rounded-pill px-2.5 py-1 small fw-semibold">
                                    <i class="fa-solid fa-circle-check me-1"></i>Cleared (0.00 ETB)
                                </span>
                            @endif
                        </div>
                        <p class="text-muted small mb-0 mt-1">
                            <i class="fa-regular fa-calendar me-1 text-primary"></i>
                            <strong>Gregorian Period Window:</strong> {{ $movementsData['month']['gregorian_window'] ?? 'Period' }} 
                            &bull; Official ERCA Line 100 VAT &amp; Withholding Tax Reconciliation
                        </p>
                    </div>
                </div>

                {{-- Action & Filter Hub --}}
                <div class="d-flex flex-column flex-sm-row align-items-stretch align-items-sm-center gap-2 flex-wrap w-100 w-xl-auto justify-content-xl-end d-print-none">
                    {{-- Quick Month/Year Selector Form --}}
                    <form method="GET" action="{{ route('finance.tax-deductions.movements') }}" class="d-flex align-items-center gap-1.5 bg-light p-1.5 rounded-pill border">
                        <input type="hidden" name="tab" value="{{ $activeTab }}">
                        
                        {{-- Prev Month Button --}}
                        <a href="{{ route('finance.tax-deductions.movements', ['ethiopian_year' => $prevYear, 'ethiopian_month' => $prevMonth, 'tab' => $activeTab]) }}" 
                           class="btn btn-sm btn-light rounded-circle shadow-xs p-0 d-flex align-items-center justify-content-center" 
                           style="width: 28px; height: 28px;" title="Previous Month">
                            <i class="fa-solid fa-chevron-left" style="font-size: 0.7rem;"></i>
                        </a>

                        {{-- Year Select --}}
                        <select name="ethiopian_year" class="form-select form-select-sm border-0 bg-transparent fw-bold text-dark pe-3" style="width: auto; font-size: 0.8rem;" onchange="this.form.submit()">
                            @foreach($availableEthYears as $y)
                                <option value="{{ $y }}" {{ $selectedEthYear == $y ? 'selected' : '' }}>{{ $y }} ዓ.ም.</option>
                            @endforeach
                        </select>

                        {{-- Month Select --}}
                        <select name="ethiopian_month" class="form-select form-select-sm border-0 bg-transparent fw-bold text-primary pe-3" style="width: auto; font-size: 0.8rem;" onchange="this.form.submit()">
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
                        <a href="{{ route('finance.tax-deductions.movements', ['ethiopian_year' => $nextYear, 'ethiopian_month' => $nextMonth, 'tab' => $activeTab]) }}" 
                           class="btn btn-sm btn-light rounded-circle shadow-xs p-0 d-flex align-items-center justify-content-center" 
                           style="width: 28px; height: 28px;" title="Next Month">
                            <i class="fa-solid fa-chevron-right" style="font-size: 0.7rem;"></i>
                        </a>
                    </form>

                    {{-- Excel Export Dropdown --}}
                    <div class="dropdown">
                        <button class="btn btn-sm btn-success text-white rounded-pill px-3 py-2 fw-semibold shadow-xs dropdown-toggle d-inline-flex align-items-center gap-1.5" type="button" id="movementsExcelDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fa-solid fa-file-excel"></i>
                            <span>Export Excel</span>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end shadow border-0 rounded-4 p-2" aria-labelledby="movementsExcelDropdown" style="min-width: 260px;">
                            <li class="dropdown-header small text-uppercase text-muted fw-bold px-3 py-1">ERCA Tax Export Formats</li>
                            <li>
                                <a class="dropdown-item rounded-3 py-2 px-3 d-flex align-items-center gap-2.5" href="{{ route('finance.tax-deductions.export-excel', ['type' => 'sales', 'ethiopian_year' => $selectedEthYear, 'ethiopian_month' => $selectedEthMonth]) }}">
                                    <div class="p-2 rounded-circle bg-success bg-opacity-10 text-success"><i class="fa-solid fa-hand-holding-dollar fs-6"></i></div>
                                    <div>
                                        <div class="fw-bold text-dark small">1. Sales Excel</div>
                                        <small class="text-muted d-block" style="font-size: 0.7rem;">13 columns (TIN, MRC, Receipt #, Price)</small>
                                    </div>
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item rounded-3 py-2 px-3 d-flex align-items-center gap-2.5" href="{{ route('finance.tax-deductions.export-excel', ['type' => 'purchase_tas', 'ethiopian_year' => $selectedEthYear, 'ethiopian_month' => $selectedEthMonth]) }}">
                                    <div class="p-2 rounded-circle bg-primary bg-opacity-10 text-primary"><i class="fa-solid fa-file-invoice fs-6"></i></div>
                                    <div>
                                        <div class="fw-bold text-dark small">2. Purchase TAS Excel</div>
                                        <small class="text-muted d-block" style="font-size: 0.7rem;">9 columns e-Tax Declaration</small>
                                    </div>
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item rounded-3 py-2 px-3 d-flex align-items-center gap-2.5" href="{{ route('finance.tax-deductions.export-excel', ['type' => 'purchase_vat', 'ethiopian_year' => $selectedEthYear, 'ethiopian_month' => $selectedEthMonth]) }}">
                                    <div class="p-2 rounded-circle bg-danger bg-opacity-10 text-danger"><i class="fa-solid fa-percent fs-6"></i></div>
                                    <div>
                                        <div class="fw-bold text-dark small">3. Purchase VAT Excel</div>
                                        <small class="text-muted d-block" style="font-size: 0.7rem;">ERCA Purchase VAT Report</small>
                                    </div>
                                </a>
                            </li>
                        </ul>
                    </div>

                    {{-- Print Report Button --}}
                    <button type="button" class="btn btn-sm btn-light text-primary rounded-pill px-3 py-2 fw-semibold shadow-xs border" onclick="window.print()">
                        <i class="fa-solid fa-print me-1"></i>Print Report
                    </button>

                    {{-- Back to Tax Deductions Ledger --}}
                    <a href="{{ route('finance.tax-deductions.index', ['ethiopian_year' => $selectedEthYear, 'ethiopian_month' => $selectedEthMonth]) }}" class="btn btn-sm btn-outline-secondary rounded-pill px-3 py-2 fw-semibold shadow-xs">
                        <i class="fa-solid fa-arrow-left me-1"></i>Tax Ledger
                    </a>
                </div>
            </div>
        </div>
    </div>

    {{-- ── 1. Top KPI Summary Strip: 5 Balanced Columns ─────────────────────────── --}}
    @php
        $sum = $movementsData['summary'] ?? [];
        $salesBase = (float)($sum['sales_base'] ?? 0);
        $outputVat = (float)($sum['output_vat'] ?? 0);
        $purchasesBase = (float)($sum['purchases_base'] ?? 0);
        $inputVat = (float)($sum['input_vat'] ?? 0);
        $openingCredit = (float)($sum['opening_credit'] ?? 0);
        $totalCreditable = (float)($sum['total_creditable_input'] ?? ($inputVat + $openingCredit));
        $signedNetFormatted = $sum['signed_net_formatted'] ?? '0.00';
        $statusLabel = $sum['label'] ?? 'Reconciled';
        $badgeClass = $sum['badge_class'] ?? 'bg-secondary';
        $textClass = $sum['text_class'] ?? 'text-dark';
        $isPayable = !empty($sum['is_payable']);
        $isCredit = !empty($sum['is_credit']);
        $wht = (float)($sum['withholding_tax'] ?? 0);
        $netDisbursed = (float)($sum['net_disbursed'] ?? 0);
    @endphp

    <div class="row g-2.5 mb-4 row-cols-1 row-cols-sm-2 row-cols-md-3 row-cols-lg-5">
        {{-- 1. Sales & Output VAT --}}
        <div class="col">
            <div class="card border-0 shadow-sm rounded-4 h-100 bg-white border-top border-4 border-primary">
                <div class="card-body p-3">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <span class="text-muted small text-uppercase fw-bold" style="font-size: 0.68rem;">Sales Base (ገቢ)</span>
                        <span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill" style="font-size: 0.65rem;">
                            {{ $sum['income_count'] ?? 0 }} receipts
                        </span>
                    </div>
                    <div class="fs-5 fw-bold text-dark font-monospace">ETB {{ number_format($salesBase, 2) }}</div>
                    <div class="mt-2 pt-2 border-top border-secondary-subtle d-flex justify-content-between align-items-center">
                        <span class="text-muted small" style="font-size: 0.72rem;">Output VAT (15%)</span>
                        <span class="fw-bold text-danger font-monospace small">+ ETB {{ number_format($outputVat, 2) }}</span>
                    </div>
                </div>
            </div>
        </div>

        {{-- 2. Purchases & Input VAT --}}
        <div class="col">
            <div class="card border-0 shadow-sm rounded-4 h-100 bg-white border-top border-4 border-success">
                <div class="card-body p-3">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <span class="text-muted small text-uppercase fw-bold" style="font-size: 0.68rem;">Purchases Base (ግዢ)</span>
                        <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill" style="font-size: 0.65rem;">
                            {{ $sum['expense_count'] ?? 0 }} items
                        </span>
                    </div>
                    <div class="fs-5 fw-bold text-dark font-monospace">ETB {{ number_format($purchasesBase, 2) }}</div>
                    <div class="mt-2 pt-2 border-top border-secondary-subtle d-flex justify-content-between align-items-center">
                        <span class="text-muted small" style="font-size: 0.72rem;">Input VAT (15%)</span>
                        <span class="fw-bold text-success font-monospace small">ETB {{ number_format($inputVat, 2) }}</span>
                    </div>
                </div>
            </div>
        </div>

        {{-- 3. Opening Credit --}}
        <div class="col">
            <div class="card border-0 shadow-sm rounded-4 h-100 bg-white border-top border-4 border-info">
                <div class="card-body p-3">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <span class="text-muted small text-uppercase fw-bold" style="font-size: 0.68rem;">Opening Credit</span>
                        <span class="badge bg-info-subtle text-info border border-info-subtle rounded-pill" style="font-size: 0.65rem;">Prev month</span>
                    </div>
                    <div class="fs-5 fw-bold text-info font-monospace">+ ETB {{ number_format($openingCredit, 2) }}</div>
                    <div class="mt-2 pt-2 border-top border-secondary-subtle d-flex justify-content-between align-items-center">
                        <span class="text-muted small" style="font-size: 0.72rem;">Creditable Pool</span>
                        <span class="fw-bold text-dark font-monospace small">ETB {{ number_format($totalCreditable, 2) }}</span>
                    </div>
                </div>
            </div>
        </div>

        {{-- 4. Signed Net VAT --}}
        <div class="col">
            <div class="card border-0 shadow-sm rounded-4 h-100 bg-white border-top border-4 {{ $isPayable ? 'border-danger bg-danger bg-opacity-10' : ($isCredit ? 'border-success bg-success bg-opacity-10' : 'border-warning') }}">
                <div class="card-body p-3">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <span class="text-muted small text-uppercase fw-bold" style="font-size: 0.68rem;">Signed Net VAT</span>
                        <span class="badge {{ $badgeClass }} rounded-pill small" style="font-size: 0.65rem;">
                            {{ $statusLabel }}
                        </span>
                    </div>
                    <div class="fs-5 fw-bold font-monospace {{ $textClass }}">{{ $signedNetFormatted }}</div>
                    <div class="mt-2 pt-2 border-top border-secondary-subtle">
                        <small class="text-muted d-block text-truncate" style="font-size: 0.72rem;">
                            @if($isPayable)
                                ETB {{ number_format($sum['payable_amount'] ?? 0, 2) }} payable to ERCA
                            @elseif($isCredit)
                                ETB {{ number_format($sum['credit_carry_forward'] ?? 0, 2) }} carried forward
                            @else
                                Zero balance settled (0.00)
                            @endif
                        </small>
                    </div>
                </div>
            </div>
        </div>

        {{-- 5. Withholding Tax (3%) --}}
        <div class="col">
            <div class="card border-0 shadow-sm rounded-4 h-100 bg-white border-top border-4 border-danger">
                <div class="card-body p-3">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <span class="text-muted small text-uppercase fw-bold" style="font-size: 0.68rem;">3% Withholding</span>
                        <span class="badge bg-warning-subtle text-dark border border-warning-subtle rounded-pill" style="font-size: 0.65rem;">Separate</span>
                    </div>
                    <div class="fs-5 fw-bold text-danger font-monospace">- ETB {{ number_format($wht, 2) }}</div>
                    <div class="mt-2 pt-2 border-top border-secondary-subtle d-flex justify-content-between align-items-center">
                        <span class="text-muted small" style="font-size: 0.72rem;">Net Disbursed</span>
                        <span class="fw-bold text-success font-monospace small">ETB {{ number_format($netDisbursed, 2) }}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ── 2. Interactive Navigation Tabs ───────────────────────────────────────── --}}
    <ul class="nav nav-pills gap-2 mb-3 bg-white p-2 rounded-4 shadow-sm border d-print-none" id="movementsPageTab" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link {{ $activeTab === 'income' ? 'active' : '' }} rounded-pill fw-semibold small d-inline-flex align-items-center gap-1.5" 
                    id="page-income-tab" data-bs-toggle="pill" data-bs-target="#page-income" type="button" role="tab">
                <i class="fa-solid fa-arrow-down-long text-success"></i>
                <span>Where Income Came From (ገቢ)</span>
                <span class="badge bg-success-subtle text-success rounded-pill ms-1">{{ count($movementsData['incomes'] ?? []) }}</span>
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link {{ $activeTab === 'expense' ? 'active' : '' }} rounded-pill fw-semibold small d-inline-flex align-items-center gap-1.5" 
                    id="page-expense-tab" data-bs-toggle="pill" data-bs-target="#page-expense" type="button" role="tab">
                <i class="fa-solid fa-arrow-up-long text-danger"></i>
                <span>Purchases &amp; Paid Expenses (ግዢ)</span>
                <span class="badge bg-danger-subtle text-danger rounded-pill ms-1">{{ count($movementsData['expenses'] ?? []) }}</span>
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link {{ $activeTab === 'ocr' ? 'active' : '' }} rounded-pill fw-semibold small d-inline-flex align-items-center gap-1.5" 
                    id="page-ocr-tab" data-bs-toggle="pill" data-bs-target="#page-ocr" type="button" role="tab">
                <i class="fa-solid fa-receipt text-primary"></i>
                <span>Scanned OCR Receipts</span>
                <span class="badge bg-primary-subtle text-primary rounded-pill ms-1">{{ count($movementsData['ocr_items'] ?? []) }}</span>
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link {{ $activeTab === 'audit' ? 'active' : '' }} rounded-pill fw-semibold small d-inline-flex align-items-center gap-1.5" 
                    id="page-audit-tab" data-bs-toggle="pill" data-bs-target="#page-audit" type="button" role="tab">
                <i class="fa-solid fa-scale-balanced text-dark"></i>
                <span>ERCA Line 100 Audit Sheet</span>
            </button>
        </li>
    </ul>

    {{-- ── 3. Tab Contents ──────────────────────────────────────────────────────── --}}
    <div class="tab-content" id="movementsPageTabContent">

        {{-- ── TAB 1: INCOMES & SALES ────────────────────────────────────────── --}}
        <div class="tab-pane fade {{ $activeTab === 'income' ? 'show active' : '' }}" id="page-income" role="tabpanel">
            <div class="card border-0 shadow-sm rounded-4 overflow-hidden bg-white">
                <div class="card-header bg-white border-bottom py-3 px-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div class="d-flex align-items-center gap-2">
                        <span class="badge bg-success-subtle text-success p-2 rounded-3"><i class="fa-solid fa-hand-holding-dollar fs-6"></i></span>
                        <div>
                            <h6 class="fw-bold mb-0 text-dark">Company Income &amp; Sales Receipts</h6>
                            <small class="text-muted">Sources of all company revenues, client payments, and 15% Output VAT</small>
                        </div>
                    </div>
                    <div class="d-flex align-items-center gap-2 d-print-none">
                        <a href="{{ route('finance.tax-deductions.export-excel', ['type' => 'sales', 'ethiopian_year' => $selectedEthYear, 'ethiopian_month' => $selectedEthMonth]) }}" 
                           class="btn btn-sm btn-outline-success rounded-pill px-3 fw-semibold shadow-xs text-nowrap d-inline-flex align-items-center gap-1.5">
                            <i class="fa-solid fa-file-excel"></i>
                            <span>Export Sales</span>
                        </a>
                        <div style="min-width: 250px;">
                            <input type="text" class="form-control form-control-sm rounded-pill" placeholder="Search customer, invoice #, project..." onkeyup="filterLocalTable('pageIncomeTable', this.value)">
                        </div>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" id="pageIncomeTable">
                        <thead class="table-light text-muted text-uppercase fw-bold" style="font-size: 0.7rem;">
                            <tr>
                                <th class="ps-3 py-2.5">#</th>
                                <th class="py-2.5">Date (EC / GC)</th>
                                <th class="py-2.5">Income # / Ref</th>
                                <th class="py-2.5">Customer / Payer</th>
                                <th class="py-2.5">Project</th>
                                <th class="py-2.5">Receiving Account</th>
                                <th class="py-2.5 text-end">Sales Base</th>
                                <th class="py-2.5 text-end text-danger">Output VAT (+15%)</th>
                                <th class="py-2.5 text-end text-warning">WHT (-3%)</th>
                                <th class="py-2.5 text-end text-success">Net Total</th>
                                <th class="py-2.5 text-center pe-3 d-print-none">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            @forelse($movementsData['incomes'] ?? [] as $idx => $inc)
                                @php
                                    $incBase = (float)($inc['sales_base'] ?? 0);
                                    $incVat  = (float)($inc['output_vat'] ?? 0);
                                    $incWht  = (float)($inc['withholding_amount'] ?? 0);
                                    $incNet  = (float)($inc['net_total'] ?? ($incBase + $incVat - $incWht));
                                @endphp
                                <tr>
                                    <td class="ps-3 text-muted font-monospace small">{{ $idx + 1 }}</td>
                                    <td>
                                        <span class="fw-bold font-monospace text-dark d-block">{{ $inc['date_ec'] ?? '-' }}</span>
                                        <small class="text-muted" style="font-size:0.75rem;">{{ $inc['date_gc'] ?? '-' }}</small>
                                    </td>
                                    <td>
                                        <span class="badge bg-light text-dark border font-monospace">{{ $inc['income_no'] ?? '-' }}</span>
                                        @if(!empty($inc['receipt_no']) && $inc['receipt_no'] !== '-')
                                            <small class="text-muted d-block font-monospace">Ref: {{ $inc['receipt_no'] }}</small>
                                        @endif
                                    </td>
                                    <td>
                                        <span class="fw-semibold text-dark d-block">{{ $inc['buyer_name'] ?? 'Client' }}</span>
                                        @if(!empty($inc['buyer_tin']) && $inc['buyer_tin'] !== '-')
                                            <small class="text-muted font-monospace" style="font-size:0.75rem;">TIN: {{ $inc['buyer_tin'] }}</small>
                                        @endif
                                    </td>
                                    <td>
                                        <span class="badge bg-secondary-subtle text-secondary rounded-pill small">{{ $inc['project_name'] ?? 'General' }}</span>
                                    </td>
                                    <td class="small text-muted">
                                        <i class="fa-solid fa-building-columns me-1 text-primary"></i>{{ $inc['account_name'] ?? '-' }}
                                    </td>
                                    <td class="text-end font-monospace">ETB {{ number_format($incBase, 2) }}</td>
                                    <td class="text-end font-monospace text-danger fw-semibold">+ETB {{ number_format($incVat, 2) }}</td>
                                    <td class="text-end font-monospace text-warning fw-semibold">-ETB {{ number_format($incWht, 2) }}</td>
                                    <td class="text-end font-monospace text-success fw-bold">ETB {{ number_format($incNet, 2) }}</td>
                                    <td class="text-center pe-3 text-nowrap d-print-none">
                                        <div class="d-inline-flex align-items-center gap-1">
                                            @if(!empty($inc['receipt_url']))
                                                <button type="button" class="btn btn-sm btn-outline-primary rounded-pill px-2.5 py-0.5 shadow-xs" style="font-size:0.72rem;" onclick="openSlipModal('{{ $inc['receipt_url'] }}', 'Sales Slip #{{ $inc['income_no'] }}')">
                                                    <i class="fa-solid fa-receipt me-1"></i>Slip
                                                </button>
                                            @endif
                                            @if(!empty($inc['withholding_receipt_url']))
                                                <button type="button" class="btn btn-sm btn-outline-danger rounded-pill px-2.5 py-0.5 shadow-xs" style="font-size:0.72rem;" onclick="openSlipModal('{{ $inc['withholding_receipt_url'] }}', 'WHT Slip #{{ $inc['income_no'] }}')">
                                                    <i class="fa-solid fa-file-invoice-dollar me-1"></i>WHT
                                                </button>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="11" class="text-center py-4 text-muted">
                                        <i class="fa-solid fa-folder-open me-2 text-secondary"></i>No company income receipts recorded in this Ethiopian month.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                        <tfoot class="table-light fw-bold border-top">
                            <tr>
                                <td colspan="6" class="ps-3 py-2 text-uppercase small text-muted">Total Sales &amp; Output VAT</td>
                                <td class="text-end py-2 font-monospace text-dark">ETB {{ number_format($salesBase, 2) }}</td>
                                <td class="text-end py-2 font-monospace text-danger">+ETB {{ number_format($outputVat, 2) }}</td>
                                <td class="text-end py-2 font-monospace text-warning">-ETB {{ number_format($sum['income_wht'] ?? 0, 2) }}</td>
                                <td class="text-end py-2 font-monospace text-success">ETB {{ number_format($sum['income_net'] ?? ($salesBase + $outputVat), 2) }}</td>
                                <td class="pe-3 d-print-none"></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>

        {{-- ── TAB 2: PURCHASES & EXPENSES ───────────────────────────────────── --}}
        <div class="tab-pane fade {{ $activeTab === 'expense' ? 'show active' : '' }}" id="page-expense" role="tabpanel">
            <div class="card border-0 shadow-sm rounded-4 overflow-hidden bg-white">
                <div class="card-header bg-white border-bottom py-3 px-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div class="d-flex align-items-center gap-2">
                        <span class="badge bg-danger-subtle text-danger p-2 rounded-3"><i class="fa-solid fa-file-invoice fs-6"></i></span>
                        <div>
                            <h6 class="fw-bold mb-0 text-dark">Purchases, Petty Cash &amp; Paid Expense Vouchers</h6>
                            <small class="text-muted">Creditable business expenditures, 15% Input VAT, and 3% Withholding Deductions</small>
                        </div>
                    </div>
                    <div class="d-flex align-items-center gap-2 d-print-none">
                        <div class="btn-group shadow-xs rounded-pill overflow-hidden">
                            <a href="{{ route('finance.tax-deductions.export-excel', ['type' => 'purchase_tas', 'ethiopian_year' => $selectedEthYear, 'ethiopian_month' => $selectedEthMonth]) }}" 
                               class="btn btn-sm btn-outline-primary fw-semibold px-3 text-nowrap d-inline-flex align-items-center gap-1">
                                <i class="fa-solid fa-file-excel"></i>
                                <span>Purchase TAS</span>
                            </a>
                            <a href="{{ route('finance.tax-deductions.export-excel', ['type' => 'purchase_vat', 'ethiopian_year' => $selectedEthYear, 'ethiopian_month' => $selectedEthMonth]) }}" 
                               class="btn btn-sm btn-outline-danger fw-semibold px-3 text-nowrap d-inline-flex align-items-center gap-1">
                                <i class="fa-solid fa-file-excel"></i>
                                <span>Purchase VAT</span>
                            </a>
                        </div>
                        <div style="min-width: 250px;">
                            <input type="text" class="form-control form-control-sm rounded-pill" placeholder="Search payee, voucher #, category..." onkeyup="filterLocalTable('pageExpenseTable', this.value)">
                        </div>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" id="pageExpenseTable">
                        <thead class="table-light text-muted text-uppercase fw-bold" style="font-size: 0.7rem;">
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
                        <tbody class="divide-y">
                            @forelse($movementsData['expenses'] ?? [] as $idx => $exp)
                                @php
                                    $expBase = (float)($exp['base_amount'] ?? 0);
                                    $expVat  = (float)($exp['vat_amount'] ?? 0);
                                    $expWht  = (float)($exp['withholding_amount'] ?? 0);
                                    $expNet  = (float)($exp['net_disbursed'] ?? ($expBase + $expVat - $expWht));
                                @endphp
                                <tr>
                                    <td class="ps-3 text-muted font-monospace small">{{ $idx + 1 }}</td>
                                    <td>
                                        <span class="fw-bold font-monospace text-dark d-block">{{ $exp['date_ec'] ?? '-' }}</span>
                                        <small class="text-muted" style="font-size:0.75rem;">{{ $exp['date_gc'] ?? '-' }}</small>
                                    </td>
                                    <td>
                                        <span class="badge bg-light text-dark border font-monospace">{{ $exp['request_number'] ?? '-' }}</span>
                                        @if(!empty($exp['payment_reference']) && $exp['payment_reference'] !== '-')
                                            <small class="text-muted d-block font-monospace">Ref: {{ $exp['payment_reference'] }}</small>
                                        @endif
                                    </td>
                                    <td>
                                        <span class="fw-semibold text-dark d-block">{{ $exp['payee'] ?? 'Vendor' }}</span>
                                    </td>
                                    <td>
                                        <span class="badge bg-info-subtle text-info border border-info-subtle rounded-pill small">{{ $exp['category'] ?? 'General' }}</span>
                                        <small class="text-muted d-block" style="font-size:0.75rem;">{{ $exp['project_name'] ?? 'General' }}</small>
                                    </td>
                                    <td class="small text-muted">
                                        <i class="fa-solid fa-wallet me-1 text-danger"></i>{{ $exp['account_name'] ?? '-' }}
                                    </td>
                                    <td class="text-end font-monospace">ETB {{ number_format($expBase, 2) }}</td>
                                    <td class="text-end font-monospace text-success fw-semibold">ETB {{ number_format($expVat, 2) }}</td>
                                    <td class="text-end font-monospace text-danger">{{ $expWht > 0 ? '- ETB ' . number_format($expWht, 2) : '-' }}</td>
                                    <td class="text-end font-monospace text-dark fw-bold pe-3">ETB {{ number_format($expNet, 2) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="10" class="text-center py-4 text-muted">
                                        <i class="fa-solid fa-folder-open me-2 text-secondary"></i>No purchases or paid expenses recorded for this Ethiopian month.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                        <tfoot class="table-light fw-bold border-top">
                            <tr>
                                <td colspan="6" class="ps-3 py-2 text-uppercase small text-muted">Total Purchases &amp; Deductions</td>
                                <td class="text-end py-2 font-monospace text-dark">ETB {{ number_format($purchasesBase, 2) }}</td>
                                <td class="text-end py-2 font-monospace text-success">ETB {{ number_format($inputVat, 2) }}</td>
                                <td class="text-end py-2 font-monospace text-danger">- ETB {{ number_format($wht, 2) }}</td>
                                <td class="text-end py-2 font-monospace text-dark pe-3">ETB {{ number_format($netDisbursed, 2) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>

        {{-- ── TAB 3: SCANNED OCR RECEIPTS ───────────────────────────────────── --}}
        <div class="tab-pane fade {{ $activeTab === 'ocr' ? 'show active' : '' }}" id="page-ocr" role="tabpanel">
            <div class="card border-0 shadow-sm rounded-4 overflow-hidden bg-white">
                <div class="card-header bg-white border-bottom py-3 px-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div class="d-flex align-items-center gap-2">
                        <span class="badge bg-primary-subtle text-primary p-2 rounded-3"><i class="fa-solid fa-camera-retro fs-6"></i></span>
                        <div>
                            <h6 class="fw-bold mb-0 text-dark">Scanned OCR Receipts (ERCA Cash Register Invoices)</h6>
                            <small class="text-muted">Itemized receipts with verified Vendor TIN, FS Numbers, and Machine MRC</small>
                        </div>
                    </div>
                    <div class="d-flex align-items-center gap-2 d-print-none">
                        <div class="btn-group shadow-xs rounded-pill overflow-hidden">
                            <a href="{{ route('finance.tax-deductions.export-excel', ['type' => 'purchase_tas', 'ethiopian_year' => $selectedEthYear, 'ethiopian_month' => $selectedEthMonth]) }}" 
                               class="btn btn-sm btn-outline-primary fw-semibold px-3 text-nowrap d-inline-flex align-items-center gap-1">
                                <i class="fa-solid fa-file-excel"></i>
                                <span>Purchase TAS</span>
                            </a>
                            <a href="{{ route('finance.tax-deductions.export-excel', ['type' => 'purchase_vat', 'ethiopian_year' => $selectedEthYear, 'ethiopian_month' => $selectedEthMonth]) }}" 
                               class="btn btn-sm btn-outline-danger fw-semibold px-3 text-nowrap d-inline-flex align-items-center gap-1">
                                <i class="fa-solid fa-file-excel"></i>
                                <span>Purchase VAT</span>
                            </a>
                        </div>
                        <div style="min-width: 250px;">
                            <input type="text" class="form-control form-control-sm rounded-pill" placeholder="Search vendor, FS #, item..." onkeyup="filterLocalTable('pageOcrTable', this.value)">
                        </div>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" id="pageOcrTable">
                        <thead class="table-light text-muted text-uppercase fw-bold" style="font-size: 0.7rem;">
                            <tr>
                                <th class="ps-3 py-2.5">#</th>
                                <th class="py-2.5">Date (EC / GC)</th>
                                <th class="py-2.5">FS # / Receipt #</th>
                                <th class="py-2.5">Vendor Name &amp; TIN</th>
                                <th class="py-2.5">Project / Category</th>
                                <th class="py-2.5">Item Description</th>
                                <th class="py-2.5 text-end">Base Value</th>
                                <th class="py-2.5 text-end text-success">VAT (15%)</th>
                                <th class="py-2.5 text-end text-dark">Value After VAT</th>
                                <th class="py-2.5 text-center pe-3 d-print-none">Receipt Slip</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            @php
                                $ocrSumBase = 0;
                                $ocrSumVat = 0;
                                $ocrSumAfter = 0;
                            @endphp
                            @forelse($movementsData['ocr_items'] ?? [] as $idx => $ocr)
                                @php
                                    $b = (float)($ocr['base_value'] ?? 0);
                                    $v = (float)($ocr['vat_amount'] ?? 0);
                                    $a = (float)($ocr['value_after_vat'] ?? 0);
                                    $ocrSumBase += $b;
                                    $ocrSumVat  += $v;
                                    $ocrSumAfter += $a;
                                    $hasSlip = !empty($ocr['file_url']);
                                    $isPdf = !empty($ocr['is_pdf']);
                                @endphp
                                <tr>
                                    <td class="ps-3 text-muted font-monospace small">{{ $idx + 1 }}</td>
                                    <td>
                                        <span class="fw-bold font-monospace text-dark d-block">{{ $ocr['date_ec'] ?? '-' }}</span>
                                        <small class="text-muted" style="font-size:0.75rem;">{{ $ocr['date_gc'] ?? '-' }}</small>
                                    </td>
                                    <td>
                                        <span class="badge bg-light text-primary border font-monospace">{{ $ocr['fs_no'] ?? '-' }}</span>
                                        <small class="text-muted d-block" style="font-size:0.75rem;">{{ $ocr['receipt_number'] ?? '' }}</small>
                                    </td>
                                    <td>
                                        <span class="fw-semibold text-dark d-block">{{ $ocr['vendor_name'] ?? 'Vendor' }}</span>
                                        <small class="text-muted font-monospace" style="font-size:0.75rem;">TIN: {{ $ocr['vendor_tin'] ?? '-' }}</small>
                                    </td>
                                    <td>
                                        <span class="badge bg-secondary-subtle text-secondary rounded-pill small">{{ $ocr['project_name'] ?? 'General' }}</span>
                                        <small class="text-muted d-block" style="font-size:0.75rem;">{{ $ocr['category'] ?? '' }}</small>
                                    </td>
                                    <td class="small text-dark text-truncate" style="max-width: 220px;" title="{{ $ocr['item_description'] ?? '' }}">
                                        {{ $ocr['item_description'] ?? '-' }}
                                    </td>
                                    <td class="text-end font-monospace">ETB {{ number_format($b, 2) }}</td>
                                    <td class="text-end font-monospace text-success fw-semibold">ETB {{ number_format($v, 2) }}</td>
                                    <td class="text-end font-monospace text-dark fw-bold">ETB {{ number_format($a, 2) }}</td>
                                    <td class="text-center pe-3 d-print-none">
                                        @if($hasSlip)
                                            <button type="button" class="btn btn-xs {{ $isPdf ? 'btn-outline-danger' : 'btn-outline-info' }} rounded-pill px-2 py-0.5 d-inline-flex align-items-center gap-1 shadow-xs" 
                                                    onclick="openSlipModal('{{ $ocr['file_url'] }}', '{{ addslashes($ocr['vendor_name'] ?? 'Receipt Slip') }} (FS: {{ $ocr['fs_no'] ?? '' }})', {{ $isPdf ? 'true' : 'false' }})">
                                                <i class="fa-solid {{ $isPdf ? 'fa-file-pdf text-danger' : 'fa-receipt' }}"></i>
                                                <span>{{ $isPdf ? 'PDF' : 'Slip' }}</span>
                                            </button>
                                        @else
                                            <span class="badge bg-light text-muted border" style="font-size:0.7rem;"><i class="fa-solid fa-ban me-1"></i>No Slip</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="10" class="text-center py-4 text-muted">
                                        <i class="fa-solid fa-folder-open me-2 text-secondary"></i>No OCR scanned receipts recorded for this Ethiopian month.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                        <tfoot class="table-light fw-bold border-top">
                            <tr>
                                <td colspan="6" class="ps-3 py-2 text-uppercase small text-muted">Total Scanned Invoices</td>
                                <td class="text-end py-2 font-monospace text-dark">ETB {{ number_format($ocrSumBase, 2) }}</td>
                                <td class="text-end py-2 font-monospace text-success">ETB {{ number_format($ocrSumVat, 2) }}</td>
                                <td class="text-end py-2 font-monospace text-dark">ETB {{ number_format($ocrSumAfter, 2) }}</td>
                                <td class="pe-3 d-print-none"></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>

        {{-- ── TAB 4: ERCA LINE 100 AUDIT RECONCILIATION ──────────────────────── --}}
        <div class="tab-pane fade {{ $activeTab === 'audit' ? 'show active' : '' }}" id="page-audit" role="tabpanel">
            <div class="card border-0 shadow-sm rounded-4 overflow-hidden bg-white p-4">
                <div class="d-flex align-items-center justify-content-between mb-4 pb-3 border-bottom">
                    <div>
                        <h5 class="fw-bold mb-1 text-dark">
                            <i class="fa-solid fa-stamp text-primary me-2"></i>ERCA VAT Line 100 Monthly Declaration Audit
                        </h5>
                        <p class="text-muted small mb-0">Official net tax reconciliation formula for Ethiopian Revenue and Customs Authority filing</p>
                    </div>
                    <span class="badge bg-primary text-white rounded-pill px-3 py-1.5 font-monospace">
                        {{ $movementsData['month']['title'] ?? 'Period' }}
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
                                <span class="font-monospace fw-bold text-dark">{{ number_format($salesBase, 2) }}</span>
                            </div>
                            <div class="list-group-item d-flex justify-content-between align-items-center py-2.5 bg-danger-subtle bg-opacity-25">
                                <div>
                                    <span class="fw-semibold text-danger">2. Output VAT Collected (15%)</span>
                                    <small class="text-muted d-block">Tax collected on behalf of the government</small>
                                </div>
                                <span class="font-monospace fw-bold text-danger">{{ number_format($outputVat, 2) }}</span>
                            </div>
                            <div class="list-group-item d-flex justify-content-between align-items-center py-2.5">
                                <div>
                                    <span class="fw-semibold text-dark">3. Total Taxable Purchases Base (ግዢ)</span>
                                    <small class="text-muted d-block">Creditable procurement, petty cash, and OCR invoices</small>
                                </div>
                                <span class="font-monospace fw-bold text-dark">{{ number_format($purchasesBase, 2) }}</span>
                            </div>
                            <div class="list-group-item d-flex justify-content-between align-items-center py-2.5 bg-success-subtle bg-opacity-25">
                                <div>
                                    <span class="fw-semibold text-success">4. Creditable Input VAT Paid (15%)</span>
                                    <small class="text-muted d-block">Direct tax paid on qualifying business purchases (VAT B excluded)</small>
                                </div>
                                <span class="font-monospace fw-bold text-success">{{ number_format($inputVat, 2) }}</span>
                            </div>
                            <div class="list-group-item d-flex justify-content-between align-items-center py-2.5 bg-info-subtle bg-opacity-25">
                                <div>
                                    <span class="fw-semibold text-info">5. Opening Credit Balance Brought Forward</span>
                                    <small class="text-muted d-block">Positive VAT credit balance carried forward from preceding month</small>
                                </div>
                                <span class="font-monospace fw-bold text-info">+ {{ number_format($openingCredit, 2) }}</span>
                            </div>
                            <div class="list-group-item d-flex justify-content-between align-items-center py-2.5 bg-light">
                                <div>
                                    <span class="fw-bold text-dark">6. Total Creditable Pool = (4) + (5)</span>
                                    <small class="text-muted d-block">Total allowable tax credits applicable against Output VAT</small>
                                </div>
                                <span class="font-monospace fw-bold text-dark">{{ number_format($totalCreditable, 2) }}</span>
                            </div>
                            <div class="list-group-item d-flex justify-content-between align-items-center py-3 border-top border-2">
                                <div>
                                    <span class="fs-6 fw-bold text-dark">7. Signed Net VAT = (2) &minus; (6)</span>
                                    <small class="d-block text-muted">Formula: Output VAT (Income) &minus; Total Creditable Input (Purchases)</small>
                                </div>
                                <span class="font-monospace fs-5 fw-bold {{ $textClass }}">{{ $signedNetFormatted }}</span>
                            </div>
                            <div class="list-group-item d-flex justify-content-between align-items-center py-2.5 bg-warning-subtle bg-opacity-25">
                                <div>
                                    <span class="fw-semibold text-dark">8. 3% Withholding Tax (Separate Line)</span>
                                    <small class="text-muted d-block">Remitted independently to ERCA (Not netted against VAT)</small>
                                </div>
                                <span class="font-monospace fw-bold text-danger">- {{ number_format($wht, 2) }}</span>
                            </div>
                        </div>
                    </div>

                    {{-- Executive Audit Conclusion & Action --}}
                    <div class="col-12 col-lg-5">
                        <div class="p-4 rounded-4 border h-100 d-flex flex-column justify-content-between bg-light bg-opacity-50">
                            <div>
                                <div class="d-flex align-items-center gap-2 mb-3">
                                    <span class="p-2 rounded-circle bg-primary text-white"><i class="fa-solid fa-gavel fs-6"></i></span>
                                    <h6 class="fw-bold mb-0 text-dark">Filing Decision &amp; Audit Trail</h6>
                                </div>

                                {{-- Decision Banner --}}
                                @if($isPayable)
                                    <div class="p-3 rounded-3 mb-3 bg-danger bg-opacity-10 border border-danger text-danger">
                                        <div class="d-flex align-items-center gap-2 mb-1">
                                            <i class="fa-solid fa-file-invoice-dollar fs-5"></i>
                                            <h6 class="fw-bold mb-0">Remittance Required: ETB {{ number_format($sum['payable_amount'] ?? 0, 2) }}</h6>
                                        </div>
                                        <div class="small">Output VAT on sales exceeded allowable creditable input VAT. This amount is legally payable to ERCA by the 30th of the following Ethiopian month.</div>
                                    </div>
                                @elseif($isCredit)
                                    <div class="p-3 rounded-3 mb-3 bg-success bg-opacity-10 border border-success text-success">
                                        <div class="d-flex align-items-center gap-2 mb-1">
                                            <i class="fa-solid fa-arrow-turn-up fs-5"></i>
                                            <h6 class="fw-bold mb-0">Credit Carried Forward: ETB {{ number_format($sum['credit_carry_forward'] ?? 0, 2) }}</h6>
                                        </div>
                                        <div class="small">Input VAT on creditable business purchases exceeded Output VAT. This positive credit automatically carries forward as an opening balance into the next Ethiopian month.</div>
                                    </div>
                                @else
                                    <div class="p-3 rounded-3 mb-3 bg-secondary bg-opacity-10 border border-secondary text-secondary">
                                        <div class="d-flex align-items-center gap-2 mb-1">
                                            <i class="fa-solid fa-scale-balanced fs-5"></i>
                                            <h6 class="fw-bold mb-0">Zero Balance Reconciled (ETB 0.00)</h6>
                                        </div>
                                        <div class="small">Output VAT exactly balances with creditable Input VAT. No payment is owed and no credit carries forward.</div>
                                    </div>
                                @endif

                                <div class="small text-muted space-y-2">
                                    <div class="d-flex gap-2 mb-2">
                                        <i class="fa-solid fa-circle-check text-success mt-1"></i>
                                        <span><strong>Ethiopian Calendar Rule:</strong> Bounded strictly within the Ethiopian month. Last-day transactions cannot leak into next cycle.</span>
                                    </div>
                                    <div class="d-flex gap-2 mb-2">
                                        <i class="fa-solid fa-circle-check text-danger mt-1"></i>
                                        <span><strong>Positive Balance (+):</strong> Output &gt; Input indicates a payment liability due to ERCA by the 30th day.</span>
                                    </div>
                                    <div class="d-flex gap-2">
                                        <i class="fa-solid fa-circle-check text-success mt-1"></i>
                                        <span><strong>Negative Balance (&minus;):</strong> Input &gt; Output constitutes a refundable or carry-forward credit into the next month's opening balance.</span>
                                    </div>
                                </div>
                            </div>

                            <div class="pt-3 border-top mt-3 d-flex flex-column flex-sm-row gap-2 d-print-none">
                                <button type="button" class="btn btn-outline-primary btn-sm rounded-pill px-3 flex-fill fw-semibold" onclick="window.print()">
                                    <i class="fa-solid fa-print me-1"></i>Print ERCA Audit
                                </button>
                                <div class="dropdown flex-fill">
                                    <button type="button" class="btn btn-success btn-sm rounded-pill px-3 w-100 fw-semibold dropdown-toggle d-inline-flex align-items-center justify-content-center gap-1.5 shadow-xs" data-bs-toggle="dropdown">
                                        <i class="fa-solid fa-file-excel"></i>
                                        <span>Export Excel</span>
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-end shadow border-0 rounded-3 p-2">
                                        <li><a class="dropdown-item py-1.5 px-3 rounded-2" href="{{ route('finance.tax-deductions.export-excel', ['type' => 'sales', 'ethiopian_year' => $selectedEthYear, 'ethiopian_month' => $selectedEthMonth]) }}"><i class="fa-solid fa-hand-holding-dollar text-success me-2"></i>1. Sales Excel</a></li>
                                        <li><a class="dropdown-item py-1.5 px-3 rounded-2" href="{{ route('finance.tax-deductions.export-excel', ['type' => 'purchase_tas', 'ethiopian_year' => $selectedEthYear, 'ethiopian_month' => $selectedEthMonth]) }}"><i class="fa-solid fa-file-invoice text-primary me-2"></i>2. Purchase TAS Excel</a></li>
                                        <li><a class="dropdown-item py-1.5 px-3 rounded-2" href="{{ route('finance.tax-deductions.export-excel', ['type' => 'purchase_vat', 'ethiopian_year' => $selectedEthYear, 'ethiopian_month' => $selectedEthMonth]) }}"><i class="fa-solid fa-percent text-danger me-2"></i>3. Purchase VAT Excel</a></li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Footer Summary Banner --}}
    <div class="card border-0 shadow-sm rounded-4 mt-4 bg-white">
        <div class="card-body py-2.5 px-4 d-flex justify-content-between align-items-center flex-wrap gap-2 text-muted small font-monospace">
            <div>
                <i class="fa-solid fa-shield-halved text-success me-1"></i>
                Total <strong>{{ $sum['total_count'] ?? 0 }}</strong> transactions reconciled in <strong>{{ $movementsData['month']['title'] ?? 'Period' }}</strong> 
                ({{ count($movementsData['incomes'] ?? []) }} sales &bull; {{ count($movementsData['expenses'] ?? []) }} purchases &bull; {{ count($movementsData['ocr_items'] ?? []) }} scanned receipts).
            </div>
            <div>
                <i class="fa-solid fa-clock text-primary me-1"></i>Ethiopian Era Cycle
            </div>
        </div>
    </div>
</div>

{{-- ── High-Res Receipt Slip Viewer Modal ────────────────────────────────────── --}}
<div class="modal fade" id="movementSlipModal" tabindex="-1" aria-labelledby="movementSlipModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <div class="modal-header bg-dark text-white py-2.5 px-4 d-flex justify-content-between align-items-center" style="background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%) !important;">
                <div class="d-flex align-items-center gap-2">
                    <i class="fa-solid fa-receipt text-warning fs-5"></i>
                    <div>
                        <h6 class="modal-title fw-bold mb-0 text-white" id="movementSlipModalTitle">Receipt Slip Document</h6>
                        <small class="text-white-50" id="movementSlipModalSubtitle">High-Resolution Document Preview</small>
                    </div>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <div class="btn-group btn-group-sm" role="group">
                        <button type="button" class="btn btn-outline-light btn-sm py-1 px-2" onclick="zoomPageSlip(0.2)" title="Zoom In"><i class="fa-solid fa-magnifying-glass-plus"></i></button>
                        <button type="button" class="btn btn-outline-light btn-sm py-1 px-2" onclick="zoomPageSlip(-0.2)" title="Zoom Out"><i class="fa-solid fa-magnifying-glass-minus"></i></button>
                        <button type="button" class="btn btn-outline-light btn-sm py-1 px-2" onclick="rotatePageSlip()" title="Rotate"><i class="fa-solid fa-rotate-right"></i></button>
                        <button type="button" class="btn btn-outline-light btn-sm py-1 px-2" onclick="resetPageSlip()" title="Reset"><i class="fa-solid fa-arrows-rotate"></i></button>
                    </div>
                    <a id="movementSlipDownloadBtn" href="#" target="_blank" download class="btn btn-sm btn-info text-white rounded-pill px-3">
                        <i class="fa-solid fa-download me-1"></i>Download
                    </a>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
            </div>
            <div class="modal-body p-3 bg-light d-flex align-items-center justify-content-center" style="min-height: 480px; max-height: 75vh; overflow: auto;" id="movementSlipViewport">
                <img id="movementSlipImg" src="" alt="Receipt Slip" class="img-fluid rounded shadow-sm" style="max-height: 65vh; width: auto; object-fit: contain; transition: transform 0.2s ease;">
                <iframe id="movementSlipPdf" src="" class="w-100 rounded d-none" style="height: 65vh; border: none;"></iframe>
            </div>
            <div class="modal-footer bg-white border-top py-2 px-4 d-flex justify-content-between align-items-center">
                <span class="text-muted small font-monospace" id="movementSlipMeta">Official Fiscal Documentation</span>
                <button type="button" class="btn btn-secondary btn-sm rounded-pill px-3.5" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

{{-- ── Client Scripts & Filtering ────────────────────────────────────────────── --}}
@push('scripts')
<script>
    // Live table filtering
    function filterLocalTable(tableId, query) {
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

    // Slip Modal Zoom & Rotate
    let _pageSlipZoom = 1;
    let _pageSlipRotation = 0;

    function _updatePageSlipTransform() {
        const img = document.getElementById('movementSlipImg');
        if (img) {
            img.style.transform = `scale(${_pageSlipZoom}) rotate(${_pageSlipRotation}deg)`;
        }
    }

    function zoomPageSlip(delta) {
        _pageSlipZoom = Math.max(0.4, Math.min(3.5, _pageSlipZoom + delta));
        _updatePageSlipTransform();
    }

    function rotatePageSlip() {
        _pageSlipRotation = (_pageSlipRotation + 90) % 360;
        _updatePageSlipTransform();
    }

    function resetPageSlip() {
        _pageSlipZoom = 1;
        _pageSlipRotation = 0;
        _updatePageSlipTransform();
    }

    function openSlipModal(url, title, isPdf = false) {
        if (!url) return;
        const modalEl = document.getElementById('movementSlipModal');
        if (!modalEl) return;

        const titleEl = document.getElementById('movementSlipModalTitle');
        const imgEl = document.getElementById('movementSlipImg');
        const pdfEl = document.getElementById('movementSlipPdf');
        const downloadBtn = document.getElementById('movementSlipDownloadBtn');
        const metaEl = document.getElementById('movementSlipMeta');

        if (titleEl) titleEl.innerText = title || 'Receipt Slip Document';
        if (downloadBtn) downloadBtn.href = url;
        if (metaEl) {
            const parts = url.split('/');
            metaEl.innerText = 'Document: ' + decodeURIComponent(parts[parts.length - 1] || 'slip');
        }

        resetPageSlip();

        const isDocPdf = isPdf || url.toLowerCase().includes('.pdf') || url.toLowerCase().includes('pdf');

        if (isDocPdf) {
            if (imgEl) imgEl.classList.add('d-none');
            if (pdfEl) {
                pdfEl.src = url;
                pdfEl.classList.remove('d-none');
            }
        } else {
            if (pdfEl) {
                pdfEl.classList.add('d-none');
                pdfEl.src = '';
            }
            if (imgEl) {
                imgEl.src = url;
                imgEl.classList.remove('d-none');
            }
        }

        if (window.bootstrap && bootstrap.Modal) {
            bootstrap.Modal.getOrCreateInstance(modalEl).show();
        } else if (window.$ && typeof $(modalEl).modal === 'function') {
            $(modalEl).modal('show');
        }
    }
</script>
@endpush

<style>
    @media print {
        body {
            background: #fff !important;
            font-size: 11pt !important;
        }
        .d-print-none,
        .navbar,
        .sidebar,
        #sidebar,
        header,
        footer,
        .breadcrumb,
        .btn,
        .dropdown {
            display: none !important;
        }
        .container-fluid {
            padding: 0 !important;
            width: 100% !important;
        }
        .card {
            border: 1px solid #ddd !important;
            box-shadow: none !important;
            page-break-inside: avoid;
        }
        .tab-pane {
            display: block !important;
            opacity: 1 !important;
            visibility: visible !important;
        }
        .tab-content > .tab-pane {
            display: block !important;
            page-break-before: always;
        }
        .tab-content > .tab-pane:first-child {
            page-break-before: avoid;
        }
    }
</style>
@endsection
