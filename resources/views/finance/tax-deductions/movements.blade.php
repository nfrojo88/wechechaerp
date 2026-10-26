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
            <button class="nav-link {{ $activeTab === 'tas' ? 'active' : '' }} rounded-pill fw-semibold small d-inline-flex align-items-center gap-1.5" 
                    id="page-tas-tab" data-bs-toggle="pill" data-bs-target="#page-tas" type="button" role="tab">
                <i class="fa-solid fa-file-invoice text-primary"></i>
                <span>TAS Purchases (0% VAT / Fuel Receipts — e-Tax Format)</span>
                <span class="badge bg-primary-subtle text-primary rounded-pill ms-1">{{ $movementsData['tas_purchases_count'] ?? count($movementsData['tas_purchases'] ?? []) }}</span>
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
                    <div class="d-flex align-items-center gap-2 d-print-none flex-wrap">
                        <div class="btn-group btn-group-sm shadow-xs rounded-pill p-0.5 bg-light border me-1" role="group">
                            <button type="button" class="btn btn-sm rounded-pill px-2.5 py-1 fw-semibold btn-danger text-white shadow-xs btn-expense-filter" id="btnExpenseFilter-all" onclick="filterExpensePurchaseType('all')" title="Show All Pending Purchases & Expenses">
                                <i class="fa-solid fa-list me-1"></i>All Purchases (<span id="filterCountAllPurchases">{{ count($movementsData['expenses'] ?? []) }}</span>)
                            </button>
                            <button type="button" class="btn btn-sm rounded-pill px-2.5 py-1 fw-semibold text-muted btn-expense-filter" id="btnExpenseFilter-vat" onclick="filterExpensePurchaseType('vat')" title="Purchases with 15% Input VAT">
                                <i class="fa-solid fa-percent me-1"></i>VAT Purchases (<span id="filterCountVatPurchases">{{ count($movementsData['vat_purchases'] ?? []) }}</span>)
                            </button>
                            <button type="button" class="btn btn-sm rounded-pill px-2.5 py-1 fw-semibold text-muted btn-expense-filter" id="btnExpenseFilter-tas" onclick="filterExpensePurchaseType('tas')" title="Purchases with 0% VAT (Declared in TAS)">
                                <i class="fa-solid fa-receipt me-1"></i>TAS Purchases (0% VAT) (<span id="filterCountTasPurchases">{{ count($movementsData['tas_purchases'] ?? []) }}</span>)
                            </button>
                            <button type="button" class="btn btn-sm rounded-pill px-2.5 py-1 fw-semibold text-muted btn-expense-filter" id="btnExpenseFilter-missing_receipt" onclick="filterExpensePurchaseType('missing_receipt')" title="Purchases missing receipts and not verified">
                                <i class="fa-solid fa-triangle-exclamation me-1 text-danger"></i>Missing Receipt (<span id="filterCountMissingReceipt">{{ $movementsData['summary']['missing_receipt_count'] ?? 0 }}</span>)
                            </button>
                        </div>
                        <button type="button" class="btn btn-sm rounded-pill px-2.5 py-1 fw-semibold btn-outline-info shadow-xs d-inline-flex align-items-center gap-1.5" id="btnOpenVerifiedArchive" onclick="openVerifiedArchiveModal()" title="View operational expenses verified without fiscal receipt">
                            <i class="fa-solid fa-box-archive text-info"></i>
                            <span>Verified (No Receipt) Archive</span>
                            <span class="badge bg-info-subtle text-info rounded-pill ms-0.5" id="filterCountVerifiedNoReceipt">{{ $movementsData['summary']['verified_no_receipt_count'] ?? count($movementsData['verified_no_receipt'] ?? []) }}</span>
                        </button>
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
                        <div style="min-width: 200px;">
                            <input type="text" class="form-control form-control-sm rounded-pill" placeholder="Search payee, voucher #, category..." onkeyup="filterLocalTable('pageExpenseTable', this.value)">
                        </div>
                    </div>
                </div>

                {{-- Dedicated KPI cards separating VAT Purchases and TAS Purchases --}}
                <div class="row g-2 px-3 pt-3 pb-2 bg-light bg-opacity-50 border-bottom">
                    <div class="col-md-6">
                        <div class="card border border-success-subtle bg-success-subtle bg-opacity-25 rounded-3 p-2.5 h-100">
                            <div class="d-flex align-items-center justify-content-between mb-1.5">
                                <span class="badge bg-success text-white rounded-pill px-2.5 py-1 small fw-bold">
                                    <i class="fa-solid fa-percent me-1"></i>VAT Purchases (15% VAT)
                                </span>
                                <span class="text-success fw-bold font-monospace small" id="kpiVatPurchasesCount">{{ $movementsData['summary']['vat_purchases_count'] ?? count($movementsData['vat_purchases'] ?? []) }} Vouchers / Receipts</span>
                            </div>
                            <div class="d-flex justify-content-between align-items-baseline text-nowrap gap-2 flex-wrap">
                                <div>
                                    <small class="text-muted d-block" style="font-size:0.7rem;">Base (Excl. VAT)</small>
                                    <span class="fw-bold font-monospace text-dark" id="kpiVatPurchasesBase">ETB {{ number_format($movementsData['summary']['vat_purchases_base'] ?? 0, 2) }}</span>
                                </div>
                                <div>
                                    <small class="text-success d-block" style="font-size:0.7rem;">15% Input VAT</small>
                                    <span class="fw-bold font-monospace text-success" id="kpiVatPurchasesVat">+ ETB {{ number_format($movementsData['summary']['vat_purchases_vat'] ?? 0, 2) }}</span>
                                </div>
                                <div>
                                    <small class="text-muted d-block" style="font-size:0.7rem;">3% WHT</small>
                                    <span class="fw-bold font-monospace text-danger" id="kpiVatPurchasesWht">{{ ($movementsData['summary']['vat_purchases_wht'] ?? 0) > 0 ? '- ETB ' . number_format($movementsData['summary']['vat_purchases_wht'] ?? 0, 2) : '-' }}</span>
                                </div>
                                <div>
                                    <small class="text-dark d-block" style="font-size:0.7rem;">Net Disbursed</small>
                                    <span class="fw-bold font-monospace text-dark" id="kpiVatPurchasesNet">ETB {{ number_format($movementsData['summary']['vat_purchases_net'] ?? 0, 2) }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card border border-primary-subtle bg-primary-subtle bg-opacity-25 rounded-3 p-2.5 h-100">
                            <div class="d-flex align-items-center justify-content-between mb-1.5">
                                <span class="badge bg-primary text-white rounded-pill px-2.5 py-1 small fw-bold">
                                    <i class="fa-solid fa-receipt me-1"></i>TAS Purchases (0% VAT Receipts)
                                </span>
                                <span class="text-primary fw-bold font-monospace small" id="kpiTasPurchasesCount">{{ $movementsData['summary']['tas_purchases_count'] ?? count($movementsData['tas_purchases'] ?? []) }} Vouchers / Receipts</span>
                            </div>
                            <div class="d-flex justify-content-between align-items-baseline text-nowrap gap-2 flex-wrap">
                                <div>
                                    <small class="text-muted d-block" style="font-size:0.7rem;">Expenditure Base</small>
                                    <span class="fw-bold font-monospace text-dark" id="kpiTasPurchasesBase">ETB {{ number_format($movementsData['summary']['tas_purchases_base'] ?? 0, 2) }}</span>
                                </div>
                                <div>
                                    <small class="text-muted d-block" style="font-size:0.7rem;">VAT (0% / Exempt)</small>
                                    <span class="fw-bold font-monospace text-secondary">ETB 0.00</span>
                                </div>
                                <div>
                                    <small class="text-muted d-block" style="font-size:0.7rem;">3% WHT</small>
                                    <span class="fw-bold font-monospace text-danger" id="kpiTasPurchasesWht">{{ ($movementsData['summary']['tas_purchases_wht'] ?? 0) > 0 ? '- ETB ' . number_format($movementsData['summary']['tas_purchases_wht'] ?? 0, 2) : '-' }}</span>
                                </div>
                                <div>
                                    <small class="text-dark d-block" style="font-size:0.7rem;">Net Disbursed</small>
                                    <span class="fw-bold font-monospace text-dark" id="kpiTasPurchasesNet">ETB {{ number_format($movementsData['summary']['tas_purchases_net'] ?? 0, 2) }}</span>
                                </div>
                            </div>
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
                                <th class="py-2.5 text-end text-dark">Net Disbursed</th>
                                <th class="py-2.5 text-center pe-3 d-print-none">Actions / Slip</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            @forelse($movementsData['expenses'] ?? [] as $idx => $exp)
                                @php
                                    $expBase = (float)($exp['base_amount'] ?? 0);
                                    $expVat  = (float)($exp['vat_amount'] ?? 0);
                                    $expWht  = (float)($exp['withholding_amount'] ?? 0);
                                    $expNet  = (float)($exp['net_disbursed'] ?? ($expBase + $expVat - $expWht));
                                    $isVatExp = !empty($exp['is_vat_purchase']) || $expVat > 0;
                                    $cleanExpTin = preg_replace('/\D/', '', (string)($exp['seller_tin'] ?? ''));
                                    $hasValidExpTin = strlen($cleanExpTin) === 10;
                                    $hasPartialExpTin = !empty($cleanExpTin) && strlen($cleanExpTin) !== 10;
                                    $hasExpWht = !empty($exp['has_withholding']) || $expWht > 0;
                                    $expSlip = $exp['receipt_slip_url'] ?? ($exp['attachment_url'] ?? null);
                                    $isExpPdf = $expSlip && str_contains(strtolower($expSlip), '.pdf');
                                    $expWhtSlip = $exp['withholding_receipt_url'] ?? null;
                                @endphp
                                <tr class="expense-row" 
                                    id="page-expense-row-{{ $exp['id'] ?? $idx }}"
                                    data-idx="{{ $idx }}"
                                    data-expense-id="{{ $exp['id'] ?? '' }}"
                                    data-receipt-id="{{ $exp['receipt_id'] ?? '' }}"
                                    data-fs="{{ ($exp['fs_no'] ?? '') !== '-' ? ($exp['fs_no'] ?? '') : '' }}"
                                    data-purchase-category="{{ $isVatExp ? 'vat' : 'tas' }}"
                                    data-verified-no-receipt="{{ !empty($exp['is_verified_without_receipt']) ? '1' : '0' }}"
                                    data-has-receipt="{{ !empty($exp['has_receipt']) ? '1' : '0' }}"
                                    data-base="{{ $expBase }}"
                                    data-vat="{{ $expVat }}"
                                    data-wht="{{ $expWht }}"
                                    data-net="{{ $expNet }}">
                                    <td class="ps-3 text-muted font-monospace small">{{ $idx + 1 }}</td>
                                    <td>
                                        <span class="fw-bold font-monospace text-dark d-block">{{ $exp['date_ec'] ?? '-' }}</span>
                                        <small class="text-muted" style="font-size:0.75rem;">{{ $exp['date_gc'] ?? '-' }}</small>
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-center gap-1 flex-wrap">
                                            <span class="badge bg-light text-dark border font-monospace">{{ $exp['request_number'] ?? '-' }}</span>
                                            @if($isVatExp)
                                                <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill font-monospace" style="font-size:0.65rem;">
                                                    <i class="fa-solid fa-percent me-0.5"></i>VAT Purchase
                                                </span>
                                            @else
                                                <span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill font-monospace" style="font-size:0.65rem;">
                                                    <i class="fa-solid fa-receipt me-0.5"></i>TAS Purchase (0% VAT)
                                                </span>
                                            @endif
                                            @if(!empty($exp['is_fuel']))
                                                <span class="badge bg-warning text-dark border border-warning-subtle rounded-pill font-monospace" style="font-size:0.65rem;">
                                                    <i class="fa-solid fa-gas-pump me-0.5"></i>Fuel (TAS)
                                                </span>
                                            @endif
                                            @if(($exp['source_type'] ?? '') === 'petty_cash')
                                                <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle rounded-pill small" style="font-size:0.65rem;"><i class="fa-solid fa-coins me-0.5"></i>Petty Cash</span>
                                            @elseif(($exp['source_type'] ?? '') === 'direct_expense')
                                                <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle rounded-pill small" style="font-size:0.65rem;">Direct</span>
                                            @endif
                                            @if(!empty($exp['is_verified_without_receipt']))
                                                <span class="badge bg-info-subtle text-info border border-info-subtle rounded-pill font-monospace cell-exp-verified-no-rec-badge" style="font-size:0.65rem;">
                                                    <i class="fa-solid fa-shield-check me-0.5"></i>Verified (No Receipt)
                                                </span>
                                            @elseif(empty($exp['has_receipt']))
                                                <span class="badge bg-danger-subtle text-danger border border-danger-subtle rounded-pill font-monospace cell-exp-missing-rec-badge" style="font-size:0.65rem;">
                                                    <i class="fa-solid fa-triangle-exclamation me-0.5"></i>Missing Receipt
                                                </span>
                                            @endif
                                        </div>
                                        @if(!empty($exp['payment_reference']) && $exp['payment_reference'] !== '-')
                                            <small class="text-muted d-block font-monospace">Ref: {{ $exp['payment_reference'] }}</small>
                                        @endif
                                        <small class="text-primary font-monospace cell-exp-fs {{ (!empty($exp['fs_no']) && $exp['fs_no'] !== '-') ? 'd-block' : 'd-none' }}" style="font-size:0.7rem;"><i class="fa-solid fa-barcode me-0.5"></i>FS #: {{ $exp['fs_no'] ?? '' }}</small>
                                        <small class="text-muted font-monospace cell-exp-mrc {{ (!empty($exp['mrc_no']) && $exp['mrc_no'] !== '-') ? 'd-block' : 'd-none' }}" style="font-size:0.68rem;">MRC: {{ $exp['mrc_no'] ?? '' }}</small>
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-center gap-1 flex-wrap mb-0.5">
                                            @if($hasValidExpTin)
                                                <span class="badge bg-success-subtle text-success border border-success-subtle font-monospace px-1.5 py-0.5 rounded-2 cell-exp-tin fw-bold" title="ERCA Valid 10-Digit Seller TIN">
                                                    <i class="fa-solid fa-circle-check text-success me-0.5"></i>{{ $cleanExpTin }}
                                                </span>
                                            @elseif($hasPartialExpTin)
                                                <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle font-monospace px-1.5 py-0.5 rounded-2 cell-exp-tin fw-bold" title="{{ strlen($cleanExpTin) }}/10 Digits - Incomplete ERCA TIN">
                                                    <i class="fa-solid fa-triangle-exclamation text-warning me-0.5"></i>{{ $exp['seller_tin'] }}
                                                </span>
                                            @else
                                                <span class="badge bg-danger-subtle text-danger border border-danger-subtle font-monospace px-1.5 py-0.5 rounded-2 cell-exp-tin fw-semibold" title="Missing Seller TIN">
                                                    <i class="fa-solid fa-circle-xmark me-0.5"></i>No TIN
                                                </span>
                                            @endif

                                            <button type="button" 
                                                    class="btn btn-xs {{ $hasValidExpTin ? 'btn-link text-muted p-0' : 'btn-outline-danger rounded-pill px-1.5 py-0.5 shadow-2xs' }} d-inline-flex align-items-center gap-1 fw-semibold d-print-none" 
                                                    onclick="openExpenseInspector({{ $idx }})" 
                                                    title="{{ $hasValidExpTin ? 'Edit Seller TIN / Slip' : 'Fill Seller TIN in Inspector' }}">
                                                <i class="fa-solid fa-pen-to-square"></i>
                                                @if(!$hasValidExpTin)
                                                    <span style="font-size:0.65rem;">Fill TIN</span>
                                                @endif
                                            </button>
                                        </div>
                                        <span class="fw-semibold text-dark d-block text-truncate cell-exp-payee" style="max-width: 175px;">{{ $exp['payee'] ?? 'Vendor' }}</span>
                                    </td>
                                    <td>
                                        <span class="badge bg-info-subtle text-info border border-info-subtle rounded-pill small">{{ $exp['category'] ?? 'General' }}</span>
                                        <small class="text-muted d-block" style="font-size:0.75rem;">{{ $exp['project_name'] ?? 'General' }}</small>
                                    </td>
                                    <td class="small text-muted">
                                        <i class="fa-solid fa-wallet me-1 text-danger"></i>{{ $exp['account_name'] ?? '-' }}
                                    </td>
                                    <td class="text-end font-monospace cell-exp-base">ETB {{ number_format($expBase, 2) }}</td>
                                    <td class="text-end font-monospace text-success fw-semibold cell-exp-vat">ETB {{ number_format($expVat, 2) }}</td>
                                    <td class="text-end font-monospace text-danger cell-exp-wht">
                                        @if($expWht > 0)
                                            <div class="fw-bold">- ETB {{ number_format($expWht, 2) }}</div>
                                            <small class="text-danger d-block font-monospace" style="font-size:0.68rem;">
                                                <i class="fa-solid fa-hand-holding-dollar me-0.5"></i>{{ number_format($exp['withholding_rate'] ?? 3, 1) }}% WHT
                                            </small>
                                        @else
                                            <span class="text-muted">-</span>
                                            <button type="button" class="btn btn-xs btn-outline-warning text-dark border-warning rounded-pill px-1.5 py-0.2 d-block ms-auto mt-0.5 d-print-none shadow-2xs" onclick="toggleExpenseQuickRow({{ $idx }})" style="font-size:0.65rem;" title="Fill Withholding Tax Deduction">
                                                <i class="fa-solid fa-plus me-0.5"></i>WHT
                                            </button>
                                        @endif
                                    </td>
                                    <td class="text-end font-monospace text-dark fw-bold cell-exp-net">
                                        ETB {{ number_format($expNet, 2) }}
                                    </td>
                                    <td class="text-center pe-3 d-print-none">
                                        <div class="d-inline-flex align-items-center gap-1 flex-wrap justify-content-center">
                                            {{-- Inline Quick Fill Row Toggle --}}
                                            <button type="button" 
                                                    class="btn btn-xs btn-outline-primary rounded-pill px-2 py-1 d-inline-flex align-items-center gap-1 fw-semibold shadow-2xs btn-quick-toggle" 
                                                    onclick="toggleExpenseQuickRow({{ $idx }})" 
                                                    id="btn-quick-row-{{ $exp['id'] ?? $idx }}"
                                                    title="Expand Inline Row to Fill Receipt & Withholding Details">
                                                <i class="fa-solid fa-pen-to-square"></i>
                                                <span class="quick-toggle-text">Fill / Edit</span>
                                            </button>

                                            {{-- Inspector Modal Button --}}
                                            <button type="button" 
                                                    class="btn btn-xs btn-outline-secondary rounded-pill px-2 py-1 d-inline-flex align-items-center gap-1 fw-semibold shadow-2xs" 
                                                    onclick="openExpenseInspector({{ $idx }})" 
                                                    title="Open ERCA Slip Inspector to Edit FS / TIN / Amounts & Scanned Slip">
                                                <i class="fa-solid fa-receipt"></i>
                                                <span>Inspector</span>
                                            </button>

                                            {{-- Detail Modal Button --}}
                                            <button type="button" 
                                                    class="btn btn-xs btn-outline-info rounded-pill px-2 py-1 d-inline-flex align-items-center gap-1 fw-semibold shadow-2xs" 
                                                    onclick="openExpenseDetailModal({{ $idx }})" 
                                                    title="View Full Expense Request, Withholding Tax & Purpose Details">
                                                <i class="fa-solid fa-circle-info text-info"></i>
                                                <span>Detail</span>
                                            </button>

                                            {{-- Verify Without Receipt Action Button --}}
                                            <button type="button" 
                                                    class="btn btn-xs {{ !empty($exp['is_verified_without_receipt']) ? 'btn-info text-white' : 'btn-outline-warning text-dark' }} rounded-pill px-2 py-0.5 d-inline-flex align-items-center gap-1 shadow-2xs btn-verify-no-receipt" 
                                                    onclick="toggleVerifyWithoutReceipt('{{ $exp['id'] ?? $idx }}', '{{ $exp['request_number'] ?? '' }}', {{ $idx }}, {{ !empty($exp['is_verified_without_receipt']) ? '1' : '0' }})" 
                                                    id="btn-verify-no-rec-{{ $exp['id'] ?? $idx }}"
                                                    title="{{ !empty($exp['is_verified_without_receipt']) ? 'Verified without fiscal receipt (Click to toggle)' : 'Verify expense without fiscal cash receipt (Operational 0% VAT)' }}">
                                                <i class="fa-solid {{ !empty($exp['is_verified_without_receipt']) ? 'fa-shield-check' : 'fa-shield-halved text-warning' }}"></i>
                                                <span class="verify-btn-text">{{ !empty($exp['is_verified_without_receipt']) ? 'Verified (No Rec)' : 'Verify No Rec' }}</span>
                                            </button>

                                            {{-- Scanned Slip Link or Attach Button --}}
                                            @if(!empty($expSlip))
                                                <a href="{{ $expSlip }}" target="_blank" class="btn btn-xs {{ $isExpPdf ? 'btn-outline-danger' : 'btn-outline-primary' }} rounded-pill px-2 py-0.5 d-inline-flex align-items-center gap-1 shadow-2xs cell-exp-slip-link" title="View Scanned Receipt Slip">
                                                    <i class="fa-solid {{ $isExpPdf ? 'fa-file-pdf text-danger' : 'fa-receipt text-primary' }}"></i>
                                                    <span>{{ $isExpPdf ? 'PDF Slip' : 'Slip' }}</span>
                                                </a>
                                            @else
                                                <button type="button" class="btn btn-xs btn-outline-dashed rounded-pill px-2 py-0.5 text-muted border cell-exp-attach-btn" onclick="openExpenseInspector({{ $idx }})" title="Attach or Upload Receipt Slip" style="font-size:0.68rem;">
                                                    <i class="fa-solid fa-arrow-up-from-bracket me-0.5"></i>Attach
                                                </button>
                                            @endif

                                            @if(!empty($expWhtSlip))
                                                <a href="{{ $expWhtSlip }}" target="_blank" class="btn btn-xs btn-outline-danger rounded-pill px-2 py-0.5 d-inline-flex align-items-center gap-1 shadow-2xs cell-exp-wht-slip-link" style="font-size:0.68rem;" title="View Attached Withholding Tax (WHT) Receipt / Certificate">
                                                    <i class="fa-solid fa-file-invoice-dollar text-danger"></i>
                                                    <span>WHT Slip</span>
                                                </a>
                                            @endif
                                        </div>
                                    </td>
                                </tr>

                                {{-- ── INLINE QUICK FILL ROW: RECEIPT & WITHHOLDING ── --}}
                                <tr class="expense-quick-fill-row d-none bg-light bg-opacity-75 border-bottom border-primary border-2" id="expense-quick-fill-row-{{ $exp['id'] ?? $idx }}" data-idx="{{ $idx }}">
                                    <td colspan="11" class="p-3">
                                        <div class="card border-0 shadow-sm rounded-3 overflow-hidden bg-white">
                                            <div class="card-header bg-dark text-white py-2 px-3 d-flex align-items-center justify-content-between flex-wrap gap-2" style="background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%) !important;">
                                                <div class="d-flex align-items-center gap-2">
                                                    <span class="badge bg-primary text-white rounded-pill px-2 py-1 small font-monospace">
                                                        <i class="fa-solid fa-file-invoice me-1"></i>{{ $exp['request_number'] ?? ('EXP #' . ($idx + 1)) }}
                                                    </span>
                                                    <span class="fw-bold text-white small">Fill Receipt Details &amp; Withholding Tax (WHT)</span>
                                                    <span class="text-white-50 small d-none d-md-inline">&bull; {{ $exp['payee'] ?? 'Vendor' }} &bull; Base: ETB {{ number_format($expBase, 2) }}</span>
                                                </div>
                                                <div class="d-flex align-items-center gap-2">
                                                    <button type="button" class="btn btn-xs btn-outline-info rounded-pill px-2.5 py-0.5 text-white border-light border-opacity-50" onclick="openExpenseInspector({{ $idx }})">
                                                        <i class="fa-solid fa-up-right-and-down-left-from-center me-1"></i>Full Inspector
                                                    </button>
                                                    <button type="button" class="btn btn-xs btn-link text-white-50 p-0 fs-6" onclick="toggleExpenseQuickRow({{ $idx }})" title="Close Quick Row">
                                                        <i class="fa-solid fa-xmark"></i>
                                                    </button>
                                                </div>
                                            </div>
                                            <div class="card-body p-3">
                                                <div id="quickRowAlert-{{ $exp['id'] ?? $idx }}" class="alert d-none py-1.5 px-3 small rounded-3 mb-2.5"></div>

                                                <form id="quickRowForm-{{ $exp['id'] ?? $idx }}" onsubmit="saveExpenseQuickRow(event, {{ $idx }}); return false;">
                                                    <input type="hidden" name="id" value="{{ $exp['receipt_id'] ?: ($exp['id'] ?? '') }}">
                                                    <input type="hidden" name="receipt_id" value="{{ $exp['receipt_id'] ? ('REC-' . $exp['receipt_id']) : (str_starts_with((string)$exp['id'], 'EXP-') || str_starts_with((string)$exp['id'], 'PROC-') || str_starts_with((string)$exp['id'], 'CS-') || str_starts_with((string)$exp['id'], 'PC-') ? $exp['id'] : 'EXP-' . $exp['id']) }}">
                                                    <input type="hidden" name="expense_request_id" value="{{ $exp['id'] ?? '' }}">
                                                    <input type="hidden" name="request_number" value="{{ $exp['request_number'] ?? '' }}">
                                                    <input type="hidden" name="receipt_path" value="{{ $exp['attachment_url'] ?? '' }}">
                                                    <input type="hidden" name="withholding_receipt_path" value="{{ $exp['withholding_receipt_url'] ?? '' }}">

                                                    <div class="row g-3">
                                                        {{-- ── SECTION 1: FISCAL RECEIPT DETAILS ── --}}
                                                        <div class="col-12 col-lg-5 border-end-lg pe-lg-3">
                                                            <div class="d-flex align-items-center justify-content-between mb-2 pb-1 border-bottom">
                                                                <span class="fw-bold small text-dark"><i class="fa-solid fa-receipt text-primary me-1"></i>1. Fiscal Cash Receipt Details</span>
                                                                <span class="badge bg-light text-muted border font-monospace" style="font-size:0.65rem;">ERCA Fiscal</span>
                                                            </div>

                                                            <div class="p-2 bg-info bg-opacity-10 rounded-2 border border-info border-opacity-25 mb-2.5">
                                                                <div class="form-check form-switch mb-0">
                                                                    <input class="form-check-input" type="checkbox" name="is_verified_without_receipt" value="1" id="quickVerifyNoRecToggle-{{ $exp['id'] ?? $idx }}" {{ !empty($exp['is_verified_without_receipt']) ? 'checked' : '' }} onchange="onToggleQuickVerifyNoRec({{ $idx }}, this.checked)">
                                                                    <label class="form-check-label small fw-semibold text-dark" for="quickVerifyNoRecToggle-{{ $exp['id'] ?? $idx }}">
                                                                        <i class="fa-solid fa-shield-check text-info me-1"></i>Verify Without Receipt (Operational 0% VAT)
                                                                    </label>
                                                                </div>
                                                            </div>

                                                            <div class="mb-2">
                                                                <label class="form-label small fw-semibold text-muted mb-0.5" style="font-size:0.72rem;">Supplier / Payee Name <span class="text-danger">*</span></label>
                                                                <input type="text" name="seller_name" class="form-control form-control-sm rounded-2 font-monospace" value="{{ $exp['payee'] ?? '' }}" required placeholder="e.g. TotalEnergies Marketing Ethiopia">
                                                            </div>

                                                            <div class="row g-2 mb-2">
                                                                <div class="col-6">
                                                                    <label class="form-label small fw-semibold text-muted mb-0.5" style="font-size:0.72rem;">Seller TIN (10 Digits) <span class="text-danger">*</span></label>
                                                                    <input type="text" name="supplier_tin" maxlength="12" class="form-control form-control-sm font-monospace fw-bold" value="{{ ($exp['seller_tin'] ?? '') !== '-' ? ($exp['seller_tin'] ?? '') : '' }}" placeholder="10 Digits" oninput="this.value = this.value.replace(/\D/g, '').slice(0, 10)">
                                                                    <div class="form-text text-muted" style="font-size:0.65rem;">Must be 10 digits</div>
                                                                </div>
                                                                <div class="col-6">
                                                                    <label class="form-label small fw-semibold text-muted mb-0.5" style="font-size:0.72rem;">FS / Receipt # <span class="text-danger">*</span></label>
                                                                    <input type="text" name="fs_no" maxlength="15" class="form-control form-control-sm font-monospace text-primary fw-bold" value="{{ ($exp['fs_no'] ?? '') !== '-' ? ($exp['fs_no'] ?? '') : '' }}" placeholder="e.g. 00012345">
                                                                    <div class="form-text text-muted" style="font-size:0.65rem;">8-digit fiscal slip</div>
                                                                </div>
                                                            </div>

                                                            <div class="row g-2 mb-2">
                                                                <div class="col-6">
                                                                    <label class="form-label small fw-semibold text-muted mb-0.5" style="font-size:0.72rem;">Machine MRC #</label>
                                                                    <input type="text" name="mrc_no" class="form-control form-control-sm font-monospace" value="{{ ($exp['mrc_no'] ?? '') !== '-' ? ($exp['mrc_no'] ?? '') : '' }}" placeholder="e.g. MTR12345678">
                                                                </div>
                                                                <div class="col-6">
                                                                    <label class="form-label small fw-semibold text-muted mb-0.5" style="font-size:0.72rem;">Receipt Date</label>
                                                                    <input type="date" name="receipt_date" class="form-control form-control-sm font-monospace" value="{{ !empty($exp['raw_date']) ? \Carbon\Carbon::parse($exp['raw_date'])->format('Y-m-d') : '' }}">
                                                                </div>
                                                            </div>

                                                            <div>
                                                                <label class="form-label small fw-semibold text-muted mb-0.5" style="font-size:0.72rem;">Attach Scanned Slip (Image / PDF)</label>
                                                                <div class="d-flex align-items-center gap-1.5">
                                                                    <input type="file" name="receipt_file" class="form-control form-control-sm" accept=".jpg,.jpeg,.png,.webp,.pdf">
                                                                    @if(!empty($expSlip))
                                                                        <a href="{{ $expSlip }}" target="_blank" class="btn btn-xs btn-outline-info text-nowrap py-1 px-2" title="View Current Slip">
                                                                            <i class="fa-solid fa-receipt"></i> View
                                                                        </a>
                                                                    @endif
                                                                </div>
                                                            </div>
                                                        </div>

                                                        {{-- ── SECTION 2: WITHHOLDING TAX (WHT) ── --}}
                                                        <div class="col-12 col-lg-4 border-end-lg pe-lg-3">
                                                            <div class="d-flex align-items-center justify-content-between mb-2 pb-1 border-bottom">
                                                                <span class="fw-bold small text-dark"><i class="fa-solid fa-hand-holding-dollar text-warning me-1"></i>2. Withholding Tax (WHT)</span>
                                                                <div class="form-check form-switch mb-0">
                                                                    <input class="form-check-input" type="checkbox" name="has_withholding" value="1" id="quickWhtToggle-{{ $exp['id'] ?? $idx }}" {{ (!empty($exp['has_withholding']) || $expWht > 0) ? 'checked' : '' }} onchange="toggleQuickWhtSection({{ $idx }}, this.checked)">
                                                                    <label class="form-check-label small fw-semibold text-dark" for="quickWhtToggle-{{ $exp['id'] ?? $idx }}">Apply WHT</label>
                                                                </div>
                                                            </div>

                                                            <div id="quickWhtBody-{{ $exp['id'] ?? $idx }}" class="{{ (!empty($exp['has_withholding']) || $expWht > 0) ? '' : 'd-none' }}">
                                                                <div class="row g-2 mb-2">
                                                                    <div class="col-6">
                                                                        <label class="form-label small fw-semibold text-muted mb-0.5" style="font-size:0.72rem;">WHT Rate</label>
                                                                        <select name="withholding_rate" class="form-select form-select-sm font-monospace" onchange="calcQuickWht({{ $idx }})">
                                                                            <option value="2" {{ (float)($exp['withholding_rate'] ?? 0) == 2 ? 'selected' : '' }}>2.0% (Goods Supply)</option>
                                                                            <option value="3" {{ ((float)($exp['withholding_rate'] ?? 3) == 3 || empty($exp['withholding_rate'])) ? 'selected' : '' }}>3.0% (Services Standard)</option>
                                                                            <option value="30" {{ (float)($exp['withholding_rate'] ?? 0) == 30 ? 'selected' : '' }}>30.0% (No TIN / Unregistered)</option>
                                                                        </select>
                                                                    </div>
                                                                    <div class="col-6">
                                                                        <label class="form-label small fw-semibold text-danger mb-0.5" style="font-size:0.72rem;">WHT Deduction (ETB)</label>
                                                                        <input type="number" step="0.01" min="0" name="withholding_amount" class="form-control form-control-sm font-monospace text-danger fw-bold" value="{{ $expWht > 0 ? number_format($expWht, 2, '.', '') : '' }}" oninput="calcQuickNet({{ $idx }})">
                                                                    </div>
                                                                </div>

                                                                <div class="mb-2">
                                                                    <label class="form-label small fw-semibold text-muted mb-0.5" style="font-size:0.72rem;">WHT Certificate / Voucher #</label>
                                                                    <input type="text" name="withholding_receipt_number" class="form-control form-control-sm font-monospace" value="{{ ($exp['withholding_receipt_number'] ?? '') !== '-' ? ($exp['withholding_receipt_number'] ?? '') : '' }}" placeholder="e.g. WHT-2026-0098">
                                                                </div>

                                                                <div>
                                                                    <label class="form-label small fw-semibold text-muted mb-0.5" style="font-size:0.72rem;">Attach WHT Certificate (Image / PDF)</label>
                                                                    <div class="d-flex align-items-center gap-1.5">
                                                                        <input type="file" name="withholding_file" class="form-control form-control-sm" accept=".jpg,.jpeg,.png,.webp,.pdf">
                                                                        @if(!empty($exp['withholding_receipt_url']))
                                                                            <a href="{{ $exp['withholding_receipt_url'] }}" target="_blank" class="btn btn-xs btn-outline-warning text-nowrap py-1 px-2" title="View WHT Certificate">
                                                                                <i class="fa-solid fa-file-invoice-dollar"></i> View
                                                                            </a>
                                                                        @endif
                                                                    </div>
                                                                </div>
                                                            </div>

                                                            <div id="quickWhtNone-{{ $exp['id'] ?? $idx }}" class="{{ (!empty($exp['has_withholding']) || $expWht > 0) ? 'd-none' : '' }} text-center py-4 bg-light rounded-2 text-muted">
                                                                <i class="fa-solid fa-shield-halved text-secondary me-1"></i>No Withholding Tax deduction applied.
                                                                <small class="d-block text-muted mt-1">Toggle switch above to record 2%, 3%, or 30% WHT.</small>
                                                            </div>
                                                        </div>

                                                        {{-- ── SECTION 3: RECONCILIATION & ACTION ── --}}
                                                        <div class="col-12 col-lg-3 d-flex flex-column justify-content-between">
                                                            <div>
                                                                <div class="d-flex align-items-center justify-content-between mb-2 pb-1 border-bottom">
                                                                    <span class="fw-bold small text-dark"><i class="fa-solid fa-calculator text-success me-1"></i>3. Financial Adjustment</span>
                                                                    <span class="badge bg-primary text-white rounded-pill px-2 py-0.5" style="font-size:0.65rem;">
                                                                        <i class="fa-solid fa-lock-open me-1"></i>Unlocked (Admin)
                                                                    </span>
                                                                </div>

                                                                <div class="bg-light p-2.5 rounded-3 border mb-2 font-monospace" style="font-size:0.75rem;">
                                                                    {{-- Taxable Base Input --}}
                                                                    <div class="mb-1.5">
                                                                        <div class="d-flex justify-content-between align-items-center mb-0.5">
                                                                            <label class="form-label text-muted mb-0" style="font-size:0.7rem;">Taxable Base:</label>
                                                                            <span class="text-xs text-muted font-monospace">ETB</span>
                                                                        </div>
                                                                        <input type="number" step="0.01" min="0" name="total_value" id="quickBase-{{ $exp['id'] ?? $idx }}" class="form-control form-control-sm font-monospace fw-semibold py-1 px-2" value="{{ number_format($expBase, 2, '.', '') }}" oninput="calcQuickAmounts({{ $idx }}, 'base')">
                                                                    </div>

                                                                    {{-- Input VAT Input + Quick Buttons --}}
                                                                    <div class="mb-1.5">
                                                                        <div class="d-flex justify-content-between align-items-center mb-0.5">
                                                                            <label class="form-label text-success mb-0" style="font-size:0.7rem;">Input VAT:</label>
                                                                            <div class="btn-group btn-group-xs" role="group">
                                                                                <button type="button" class="btn btn-outline-success py-0 px-1" style="font-size:0.65rem;" onclick="setQuickVat15({{ $idx }})" title="Set 15% VAT">15%</button>
                                                                                <button type="button" class="btn btn-outline-secondary py-0 px-1" style="font-size:0.65rem;" onclick="setQuickVatZero({{ $idx }})" title="Set 0% VAT">0%</button>
                                                                            </div>
                                                                        </div>
                                                                        <input type="number" step="0.01" min="0" name="vat_amount" id="quickVat-{{ $exp['id'] ?? $idx }}" class="form-control form-control-sm font-monospace fw-bold text-success py-1 px-2" value="{{ number_format($expVat, 2, '.', '') }}" oninput="calcQuickAmounts({{ $idx }}, 'vat')">
                                                                    </div>

                                                                    {{-- Gross Total Input --}}
                                                                    <div class="mb-1.5">
                                                                        <div class="d-flex justify-content-between align-items-center mb-0.5">
                                                                            <label class="form-label text-dark mb-0 fw-bold" style="font-size:0.7rem;">Gross Total:</label>
                                                                            <span class="text-xs text-muted font-monospace">ETB</span>
                                                                        </div>
                                                                        <input type="number" step="0.01" min="0" name="value_after_vat" id="quickGross-{{ $exp['id'] ?? $idx }}" class="form-control form-control-sm font-monospace fw-bold text-dark py-1 px-2" value="{{ number_format($expBase + $expVat, 2, '.', '') }}" oninput="calcQuickAmounts({{ $idx }}, 'gross')">
                                                                    </div>

                                                                    {{-- WHT Deducted Display --}}
                                                                    <div class="d-flex justify-content-between mb-1 pt-1 border-top">
                                                                        <span class="text-danger">WHT Deducted:</span>
                                                                        <span class="fw-bold text-danger quick-wht-display" id="quickWhtDisplay-{{ $exp['id'] ?? $idx }}">- ETB {{ number_format($expWht, 2) }}</span>
                                                                    </div>

                                                                    {{-- Net Disbursed Display --}}
                                                                    <div class="d-flex justify-content-between pt-1 border-top fw-bold">
                                                                        <span class="text-dark">Net Disbursed:</span>
                                                                        <span class="text-dark quick-net-display" id="quickNetDisplay-{{ $exp['id'] ?? $idx }}">ETB {{ number_format($expNet, 2) }}</span>
                                                                    </div>
                                                                </div>

                                                                <input type="hidden" name="item_description" value="{{ $exp['description'] ?: ($exp['category'] ?? 'General Purchase') }}">
                                                                <input type="hidden" name="category" value="{{ $exp['category'] ?? 'materials' }}">
                                                                <input type="hidden" name="purchase_type" value="{{ $exp['purc_type'] ?? 'PUR_GD' }}">
                                                            </div>

                                                            <div class="pt-2">
                                                                <button type="submit" class="btn btn-primary btn-sm w-100 rounded-pill py-2 fw-semibold shadow-xs d-inline-flex align-items-center justify-content-center gap-1.5" id="btnQuickSave-{{ $exp['id'] ?? $idx }}">
                                                                    <i class="fa-solid fa-circle-check"></i>
                                                                    <span>Save Receipt &amp; Withholding</span>
                                                                </button>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="11" class="text-center py-4 text-muted">
                                        <i class="fa-solid fa-folder-open me-2 text-secondary"></i>No purchases or paid expenses recorded for this Ethiopian month.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                        <tfoot class="table-light fw-bold border-top">
                            <tr>
                                <td colspan="6" class="ps-3 py-2 text-uppercase small text-muted" id="pageExpenseFootLabel">Total Purchases &amp; Deductions</td>
                                <td class="text-end py-2 font-monospace text-dark" id="pageExpenseFootBase">ETB {{ number_format($purchasesBase, 2) }}</td>
                                <td class="text-end py-2 font-monospace text-success" id="pageExpenseFootVat">ETB {{ number_format($inputVat, 2) }}</td>
                                <td class="text-end py-2 font-monospace text-danger" id="pageExpenseFootWht">- ETB {{ number_format($wht, 2) }}</td>
                                <td class="text-end py-2 font-monospace text-dark" id="pageExpenseFootNet">ETB {{ number_format($netDisbursed, 2) }}</td>
                                <td class="d-print-none"></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>

        {{-- ── TAB 3: TAS PURCHASES (e-Tax Declaration pro.doc - Both VAT & 0% with Seller TIN) ──────── --}}
        @php
            $rawTasPurchases = $movementsData['tas_purchases'] ?? [];
            $tasTotalCount = count($rawTasPurchases);
            $tasWithTinCount = 0;
            $tasMissingTinCount = 0;
            $tasVatCount = 0;
            $tasZeroVatCount = 0;
            $tasWithWhtCount = 0;
            $tasTotalBase = 0;
            $tasTotalVat = 0;
            $tasTotalWht = 0;
            $tasTotalGross = 0;
            $tasTotalNet = 0;
            foreach ($rawTasPurchases as $tpItem) {
                $cTin = preg_replace('/\D/', '', (string)($tpItem['seller_tin'] ?? ''));
                if (strlen($cTin) === 10 || (strlen($cTin) >= 9 && ($tpItem['seller_tin'] ?? '') !== '-')) {
                    $tasWithTinCount++;
                } else {
                    $tasMissingTinCount++;
                }
                $isVatItem = !empty($tpItem['is_vat_purchase']) || (float)($tpItem['vat_amount'] ?? 0) > 0;
                if ($isVatItem) {
                    $tasVatCount++;
                } else {
                    $tasZeroVatCount++;
                }
                $bAmt = (float)($tpItem['base_amount'] ?? ($tpItem['amount'] ?? 0));
                $vAmt = (float)($tpItem['vat_amount'] ?? 0);
                $gAmt = (float)($tpItem['total_amount'] ?? ($tpItem['gross_amount'] ?? ($bAmt + $vAmt)));
                $itemWht = (float)($tpItem['withholding_amount'] ?? 0);
                if ($itemWht > 0 || !empty($tpItem['has_withholding'])) {
                    $tasWithWhtCount++;
                    $tasTotalWht += $itemWht;
                }
                $tasTotalBase += $bAmt;
                $tasTotalVat += $vAmt;
                $tasTotalGross += $gAmt;
                $tasTotalNet += (float)($tpItem['net_disbursed'] ?? ($gAmt - $itemWht));
            }
            $tasTinCompliancePercent = $tasTotalCount > 0 ? round(($tasWithTinCount / $tasTotalCount) * 100) : 0;
        @endphp
        <div class="tab-pane fade {{ $activeTab === 'tas' ? 'show active' : '' }}" id="page-tas" role="tabpanel">
            <div class="card border-0 shadow-sm rounded-4 overflow-hidden bg-white">
                <div class="card-header bg-white border-bottom py-3 px-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div class="d-flex align-items-center gap-2">
                        <span class="badge bg-primary-subtle text-primary p-2 rounded-3"><i class="fa-solid fa-file-invoice fs-6"></i></span>
                        <div>
                            <div class="d-flex align-items-center gap-2 flex-wrap">
                                <h6 class="fw-bold mb-0 text-dark">TAS Purchases (0% VAT / Fuel Receipts — e-Tax Format)</h6>
                                <span class="badge bg-primary text-white rounded-pill small fw-semibold px-2.5 py-0.5">
                                    <i class="fa-solid fa-receipt me-1"></i>e-Tax Purchase Declaration (All Qualified Purchases)
                                </span>
                            </div>
                            <small class="text-muted">Purchases and expenses with registered Seller TIN &amp; receipt added (Both 15% VAT and 0% TAS / Fuel) declared under Ethiopian e-Tax (pro.doc 9-column standard)</small>
                        </div>
                    </div>
                    <div class="d-flex align-items-center gap-2 d-print-none flex-wrap">
                        {{-- Quick Filters: All e-Tax, VAT (15%), 0% VAT / Fuel, With WHT --}}
                        <div class="btn-group btn-group-sm shadow-xs rounded-pill p-0.5 bg-light border me-1" role="group">
                            <button type="button" 
                                    class="btn btn-sm rounded-pill px-2.5 py-1 fw-semibold btn-primary text-white shadow-xs" 
                                    id="btnTasFilterAll" 
                                    onclick="filterTasByTin('all')" 
                                    title="Show All e-Tax Purchases with Seller TIN & Receipt">
                                <i class="fa-solid fa-list me-1"></i>All e-Tax (<span id="tasCountAll">{{ $tasTotalCount }}</span>)
                            </button>
                            <button type="button" 
                                    class="btn btn-sm rounded-pill px-2.5 py-1 fw-semibold text-muted" 
                                    id="btnTasFilterVat" 
                                    onclick="filterTasByTin('vat')" 
                                    title="Show 15% VAT Purchases with Seller TIN Added">
                                <i class="fa-solid fa-percent text-success me-1"></i>VAT (15%) (<span id="tasCountVat">{{ $tasVatCount }}</span>)
                            </button>
                            <button type="button" 
                                    class="btn btn-sm rounded-pill px-2.5 py-1 fw-semibold text-muted" 
                                    id="btnTasFilterTas" 
                                    onclick="filterTasByTin('tas')" 
                                    title="Show 0% VAT / Fuel Receipts with Seller TIN Added">
                                <i class="fa-solid fa-gas-pump text-primary me-1"></i>0% VAT / Fuel (<span id="tasCountTas">{{ $tasZeroVatCount }}</span>)
                            </button>
                            <button type="button" 
                                    class="btn btn-sm rounded-pill px-2.5 py-1 fw-semibold text-muted" 
                                    id="btnTasFilterWithWht" 
                                    onclick="filterTasByTin('with_wht')" 
                                    title="Show Purchases with Withholding Tax (WHT)">
                                <i class="fa-solid fa-hand-holding-dollar text-danger me-1"></i>With WHT (<span id="tasCountWithWht">{{ $tasWithWhtCount }}</span>)
                            </button>
                            @if($tasMissingTinCount > 0)
                                <button type="button" 
                                        class="btn btn-sm rounded-pill px-2.5 py-1 fw-semibold text-muted" 
                                        id="btnTasFilterMissingTin" 
                                        onclick="filterTasByTin('missing_tin')" 
                                        title="Show Records Missing Seller TIN">
                                    <i class="fa-solid fa-circle-exclamation text-danger me-1"></i>Missing TIN (<span id="tasCountMissingTin">{{ $tasMissingTinCount }}</span>)
                                </button>
                            @endif
                        </div>

                        <a href="{{ route('finance.tax-deductions.export-excel', ['type' => 'purchase_tas', 'ethiopian_year' => $selectedEthYear, 'ethiopian_month' => $selectedEthMonth]) }}" 
                           class="btn btn-sm btn-primary text-white rounded-pill px-3 py-1.5 fw-semibold shadow-xs d-inline-flex align-items-center gap-1.5">
                            <i class="fa-solid fa-file-excel"></i>
                            <span>Export Purchase TAS Excel (9 Cols)</span>
                        </a>
                        <div style="min-width: 220px;">
                            <input type="text" class="form-control form-control-sm rounded-pill" placeholder="Search FS #, vendor TIN, payee..." onkeyup="filterLocalTable('pageTasTable', this.value)">
                        </div>
                    </div>
                </div>

                {{-- Summary Banner with Seller TIN Status & Withholding Tax --}}
                <div class="row g-2 px-3 pt-3 pb-2 bg-light bg-opacity-50 border-bottom">
                    <div class="col-sm-6 col-md-3">
                        <div class="card border border-primary-subtle bg-primary-subtle bg-opacity-25 rounded-3 p-2.5 h-100">
                            <small class="text-muted d-block text-uppercase fw-bold" style="font-size:0.65rem;">1. Total e-Tax Declarations</small>
                            <span class="fw-bold font-monospace text-primary fs-6">{{ $tasTotalCount }} Receipts / Vouchers</span>
                            <small class="text-success fw-semibold" style="font-size:0.7rem;"><i class="fa-solid fa-check-double me-1"></i>Receipt &amp; Seller TIN Added</small>
                        </div>
                    </div>
                    <div class="col-sm-6 col-md-3">
                        <div class="card border border-success-subtle bg-success-subtle bg-opacity-20 rounded-3 p-2.5 h-100">
                            <div class="d-flex align-items-center justify-content-between">
                                <small class="text-muted d-block text-uppercase fw-bold" style="font-size:0.65rem;">2. Purchase Tax Types</small>
                                <span class="badge bg-success rounded-pill" style="font-size:0.62rem;" id="summaryTasTinBadge">
                                    {{ $tasWithTinCount }} / {{ $tasTotalCount }} TIN Valid
                                </span>
                            </div>
                            <div class="d-flex align-items-baseline gap-1 mt-0.5">
                                <span class="fw-bold font-monospace text-success fs-6">{{ $tasVatCount }} VAT (15%)</span>
                                <span class="text-muted small">|</span>
                                <span class="fw-bold font-monospace text-primary fs-6">{{ $tasZeroVatCount }} 0% / Fuel</span>
                            </div>
                            <small class="text-muted" style="font-size:0.7rem;">ERCA 10-Digit Seller TIN Added</small>
                        </div>
                    </div>
                    <div class="col-sm-6 col-md-3">
                        <div class="card border border-primary-subtle bg-primary-subtle bg-opacity-25 rounded-3 p-2.5 h-100">
                            <div class="d-flex align-items-center justify-content-between">
                                <small class="text-muted d-block text-uppercase fw-bold" style="font-size:0.65rem;">3. Base, VAT &amp; WHT</small>
                                <span class="badge {{ $tasWithWhtCount > 0 ? 'bg-danger text-white' : 'bg-secondary-subtle text-muted' }} rounded-pill font-monospace" style="font-size:0.62rem;">
                                    {{ $tasWithWhtCount > 0 ? $tasWithWhtCount . ' With WHT' : '0 WHT' }}
                                </span>
                            </div>
                            <span class="fw-bold font-monospace text-dark fs-6 mt-0.5 d-block">Base: ETB {{ number_format($tasTotalBase, 2) }}</span>
                            <div class="d-flex align-items-center gap-2 flex-wrap">
                                @if($tasTotalVat > 0)
                                    <small class="text-success fw-semibold font-monospace" style="font-size:0.7rem;">
                                        <i class="fa-solid fa-percent me-0.5"></i>VAT: +ETB {{ number_format($tasTotalVat, 2) }}
                                    </small>
                                @endif
                                @if($tasTotalWht > 0)
                                    <small class="text-danger fw-semibold font-monospace" style="font-size:0.7rem;">
                                        <i class="fa-solid fa-hand-holding-dollar me-0.5"></i>WHT: -ETB {{ number_format($tasTotalWht, 2) }}
                                    </small>
                                @endif
                            </div>
                        </div>
                    </div>
                    <div class="col-sm-6 col-md-3">
                        <div class="card border border-secondary-subtle bg-white rounded-3 p-2.5 h-100">
                            <div class="d-flex align-items-center justify-content-between">
                                <small class="text-muted d-block text-uppercase fw-bold" style="font-size:0.65rem;">4. Gross &amp; Net Disbursed</small>
                                <span class="badge bg-secondary-subtle text-dark rounded-pill fw-bold" style="font-size:0.62rem;">
                                    <i class="fa-solid fa-file-lines me-0.5"></i>9 Cols
                                </span>
                            </div>
                            <span class="fw-bold font-monospace text-dark fs-6 mt-0.5 d-block">Gross: ETB {{ number_format($tasTotalGross, 2) }}</span>
                            <small class="text-success fw-bold d-block font-monospace" style="font-size:0.7rem;">Net Disbursed: ETB {{ number_format($tasTotalNet, 2) }}</small>
                        </div>
                    </div>
                </div>

                {{-- Table with 9 Columns --}}
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" id="pageTasTable">
                        <thead class="table-light text-muted text-uppercase fw-bold" style="font-size: 0.68rem;">
                            <tr>
                                <th class="ps-3 py-2.5">#</th>
                                <th class="py-2.5">1. Purchaser TIN</th>
                                <th class="py-2.5">2. Seller TIN &amp; Tax Type</th>
                                <th class="py-2.5 text-end">3. Amount (ETB)</th>
                                <th class="py-2.5">4. Receipt / FS #</th>
                                <th class="py-2.5">5. Receipt Date</th>
                                <th class="py-2.5">6. Encoding Date</th>
                                <th class="py-2.5">7. Machine MRC</th>
                                <th class="py-2.5 text-center">8. PurcType</th>
                                <th class="py-2.5">9. Encoded By</th>
                                <th class="py-2.5 text-center pe-3 d-print-none">Actions / Slip</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            @forelse($rawTasPurchases as $idx => $tas)
                                @php
                                    $bAmt = (float)($tas['base_amount'] ?? ($tas['amount'] ?? 0));
                                    $vAmt = (float)($tas['vat_amount'] ?? 0);
                                    $gAmt = (float)($tas['total_amount'] ?? ($tas['gross_amount'] ?? ($bAmt + $vAmt)));
                                    $tWht = (float)($tas['withholding_amount'] ?? 0);
                                    $tNet = (float)($tas['net_disbursed'] ?? ($gAmt - $tWht));
                                    $hasWht = !empty($tas['has_withholding']) || $tWht > 0;
                                    $isVat = !empty($tas['is_vat_purchase']) || $vAmt > 0;
                                    $slipUrl = $tas['receipt_slip_url'] ?? ($tas['attachment_url'] ?? null);
                                    $whtSlipUrl = $tas['withholding_receipt_url'] ?? null;
                                    $isFuel = !empty($tas['is_fuel']);
                                    $isPdf = $slipUrl && str_contains(strtolower($slipUrl), '.pdf');
                                    $cleanSellerTin = preg_replace('/\D/', '', (string)($tas['seller_tin'] ?? ''));
                                    $hasValidSellerTin = strlen($cleanSellerTin) === 10 || (strlen($cleanSellerTin) >= 9 && ($tas['seller_tin'] ?? '') !== '-');
                                    $hasPartialSellerTin = !empty($cleanSellerTin) && strlen($cleanSellerTin) < 9;
                                @endphp
                                <tr data-has-tin="{{ $hasValidSellerTin ? '1' : '0' }}"
                                    data-has-vat="{{ $isVat ? '1' : '0' }}"
                                    data-has-wht="{{ $hasWht ? '1' : '0' }}"
                                    data-base="{{ $bAmt }}"
                                    data-vat="{{ $vAmt }}"
                                    data-gross="{{ $gAmt }}"
                                    data-wht="{{ $tWht }}"
                                    data-net="{{ $tNet }}"
                                    data-receipt-id="{{ $tas['receipt_id'] ?? ($tas['id'] ?? '') }}"
                                    data-fs="{{ ($tas['fs_no'] ?? '') !== '-' ? ($tas['fs_no'] ?? '') : '' }}"
                                    data-expense-id="{{ $tas['id'] ?? '' }}"
                                    id="page-tas-row-{{ $tas['id'] ?? $idx }}"
                                    class="tas-row">
                                    <td class="ps-3 text-muted font-monospace small">{{ $idx + 1 }}</td>
                                    <td>
                                        <span class="badge bg-light text-dark border font-monospace">{{ $tas['purchaser_tin'] ?? '0038480010' }}</span>
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-center gap-1.5 flex-wrap">
                                            @if($hasValidSellerTin)
                                                <span class="badge bg-success-subtle text-success border border-success-subtle font-monospace px-2 py-0.5 rounded-2 cell-tas-tin fw-bold" title="ERCA Valid Seller TIN">
                                                    <i class="fa-solid fa-circle-check text-success me-1"></i>{{ $cleanSellerTin ?: $tas['seller_tin'] }}
                                                </span>
                                            @elseif($hasPartialSellerTin)
                                                <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle font-monospace px-2 py-0.5 rounded-2 cell-tas-tin fw-bold" title="{{ strlen($cleanSellerTin) }} Digits Seller TIN">
                                                    <i class="fa-solid fa-triangle-exclamation text-warning me-1"></i>{{ $tas['seller_tin'] }}
                                                </span>
                                            @else
                                                <span class="badge bg-danger-subtle text-danger border border-danger-subtle font-monospace px-2 py-0.5 rounded-2 cell-tas-tin fw-semibold" title="Missing Seller TIN">
                                                    <i class="fa-solid fa-circle-xmark me-1"></i>No Seller TIN
                                                </span>
                                            @endif

                                            @if($isVat)
                                                <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill font-monospace" style="font-size:0.62rem;" title="15% VAT Purchase">
                                                    <i class="fa-solid fa-percent me-0.5"></i>15% VAT
                                                </span>
                                            @elseif($isFuel)
                                                <span class="badge bg-warning text-dark border border-warning-subtle rounded-pill font-monospace" style="font-size:0.62rem;" title="Fuel Receipt (0% VAT)">
                                                    <i class="fa-solid fa-gas-pump me-0.5"></i>Fuel (0% VAT)
                                                </span>
                                            @else
                                                <span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill font-monospace" style="font-size:0.62rem;" title="0% VAT TAS Receipt">
                                                    <i class="fa-solid fa-tag me-0.5"></i>0% VAT
                                                </span>
                                            @endif

                                            @if($hasWht)
                                                <span class="badge bg-danger-subtle text-danger border border-danger-subtle rounded-pill font-monospace" style="font-size:0.62rem;" title="Withholding Tax Deducted">
                                                    <i class="fa-solid fa-hand-holding-dollar me-0.5"></i>WHT
                                                </span>
                                            @endif

                                            <button type="button" 
                                                    class="btn btn-xs {{ $hasValidSellerTin ? 'btn-link text-muted p-0' : 'btn-outline-danger rounded-pill px-2 py-0.5 shadow-2xs' }} d-inline-flex align-items-center gap-1 fw-semibold d-print-none cell-tas-tin-btn" 
                                                    onclick="openPageTasInspector({{ $idx }})" 
                                                    title="{{ $hasValidSellerTin ? 'Edit Seller TIN / Slip' : 'Fill Seller TIN in Inspector' }}">
                                                <i class="fa-solid fa-pen-to-square"></i>
                                                @if(!$hasValidSellerTin)
                                                    <span>Fill TIN</span>
                                                @endif
                                            </button>
                                        </div>
                                        <small class="text-muted d-block text-truncate cell-tas-vendor" style="max-width: 175px;">{{ $tas['payee'] ?? ($tas['seller_name'] ?? 'Vendor') }}</small>
                                    </td>
                                    <td class="text-end font-monospace text-dark cell-tas-amount">
                                        <div class="fw-bold">ETB {{ number_format($gAmt, 2) }}</div>
                                        @if($isVat)
                                            <small class="text-muted d-block font-monospace" style="font-size:0.68rem;" title="Base + VAT (15%) Breakdown">
                                                Base: {{ number_format($bAmt, 2) }} <span class="text-success">+ VAT: {{ number_format($vAmt, 2) }}</span>
                                            </small>
                                        @endif
                                        @if($hasWht)
                                            <small class="text-danger d-block font-monospace" style="font-size:0.68rem;" title="Withholding Tax (WHT) Deducted">
                                                <i class="fa-solid fa-hand-holding-dollar me-0.5"></i>-ETB {{ number_format($tWht, 2) }}
                                                @if(!empty($tas['withholding_rate']) && $tas['withholding_rate'] > 0)
                                                    ({{ number_format($tas['withholding_rate'], 1) }}%)
                                                @endif
                                            </small>
                                            <small class="text-success fw-semibold d-block font-monospace" style="font-size:0.68rem;" title="Net Disbursed / Paid Amount">
                                                Net: ETB {{ number_format($tNet, 2) }}
                                            </small>
                                        @endif
                                    </td>
                                    <td>
                                        <span class="badge bg-light text-primary border font-monospace cell-tas-fs">{{ $tas['fs_no'] ?: ($tas['request_number'] ?? '-') }}</span>
                                        @if(!empty($tas['request_number']) && $tas['request_number'] !== ($tas['fs_no'] ?? ''))
                                            <small class="text-muted d-block font-monospace" style="font-size:0.7rem;">{{ $tas['request_number'] }}</small>
                                        @endif
                                    </td>
                                    <td>
                                        <span class="fw-bold font-monospace text-dark d-block cell-tas-date-ec">{{ $tas['receipt_date_ec'] ?: ($tas['date_ec'] ?? '-') }}</span>
                                        @if(!empty($tas['date_gc']))
                                            <small class="text-muted" style="font-size:0.7rem;">{{ $tas['date_gc'] }}</small>
                                        @endif
                                    </td>
                                    <td>
                                        <span class="font-monospace text-muted d-block">{{ $tas['encoding_date_ec'] ?: '-' }}</span>
                                    </td>
                                    <td>
                                        <span class="badge bg-light text-secondary border font-monospace cell-tas-mrc">{{ $tas['mrc_no'] ?: '-' }}</span>
                                    </td>
                                    <td class="text-center font-monospace small">
                                        <span class="badge bg-secondary-subtle text-secondary rounded-pill cell-tas-purc-type">{{ $tas['purc_type'] ?? 'PUR_GD' }}</span>
                                    </td>
                                    <td>
                                        <span class="small text-muted font-monospace">{{ $tas['encoded_by'] ?? 'wechacha' }}</span>
                                    </td>
                                    <td class="text-center pe-3 d-print-none">
                                        <div class="d-inline-flex align-items-center gap-1 flex-wrap justify-content-center">
                                            <button type="button" 
                                                    class="btn btn-xs btn-outline-info rounded-pill px-2.5 py-1 d-inline-flex align-items-center gap-1 fw-semibold cell-tas-detail-btn shadow-2xs" 
                                                    onclick="openTasExpenseDetailModal({{ $idx }})" 
                                                    title="View Full Expense Request, Withholding Tax & Purpose Details">
                                                <i class="fa-solid fa-circle-info text-info"></i>
                                                <span>Detail</span>
                                            </button>
                                            <button type="button" 
                                                    class="btn btn-xs {{ $hasValidSellerTin ? 'btn-outline-secondary' : 'btn-primary text-white shadow-2xs' }} rounded-pill px-2.5 py-1 d-inline-flex align-items-center gap-1 fw-semibold cell-tas-action-btn" 
                                                    onclick="openPageTasInspector({{ $idx }})" 
                                                    title="Open ERCA Slip Inspector to Edit Seller TIN / FS / Amounts">
                                                <i class="fa-solid fa-pencil"></i>
                                                <span>{{ $hasValidSellerTin ? 'Edit' : 'Fill TIN' }}</span>
                                            </button>
                                            @if($slipUrl)
                                                <a href="{{ $slipUrl }}" target="_blank" class="btn btn-xs {{ $isPdf ? 'btn-outline-danger' : 'btn-outline-primary' }} rounded-pill px-2.5 py-1 d-inline-flex align-items-center gap-1 shadow-2xs" title="View Scanned Fuel / Purchase Slip">
                                                    <i class="fa-solid {{ $isPdf ? 'fa-file-pdf text-danger' : 'fa-receipt text-primary' }}"></i>
                                                    <span>{{ $isPdf ? 'PDF Slip' : 'See Slip' }}</span>
                                                </a>
                                            @else
                                                <button type="button" class="btn btn-xs btn-outline-dashed rounded-pill px-2 py-0.5 text-muted border" onclick="openPageTasInspector({{ $idx }})" title="Attach or Upload Receipt Slip" style="font-size:0.68rem;">
                                                    <i class="fa-solid fa-arrow-up-from-bracket me-0.5"></i>Attach
                                                </button>
                                            @endif
                                            @if(!empty($whtSlipUrl))
                                                <a href="{{ $whtSlipUrl }}" target="_blank" class="btn btn-xs btn-outline-danger rounded-pill px-2 py-0.5 d-inline-flex align-items-center gap-1 shadow-2xs" style="font-size:0.68rem;" title="View Attached Withholding Tax (WHT) Receipt / Certificate">
                                                    <i class="fa-solid fa-file-invoice-dollar text-danger"></i>
                                                    <span>WHT Slip</span>
                                                </a>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="11" class="text-center py-4 text-muted">
                                        <i class="fa-solid fa-folder-open me-2 text-secondary"></i>No TAS purchases (0% VAT receipts or fuel expenses) recorded for this Ethiopian month.
                                    </td>
                                </tr>
                            @endforelse
                            <tr id="pageTasEmptyFilterRow" class="d-none">
                                <td colspan="11" class="text-center py-4 text-muted">
                                    <i class="fa-solid fa-filter me-2 text-secondary"></i>No TAS purchases match the selected filter.
                                </td>
                            </tr>
                        </tbody>
                        <tfoot class="table-light fw-bold border-top">
                            <tr>
                                <td colspan="3" class="ps-3 py-2 text-uppercase small text-muted">
                                    Total e-Tax Purchases (<span id="pageTasFootCount">{{ $tasTotalCount }}</span> items)
                                </td>
                                <td class="text-end py-2 font-monospace text-dark" id="pageTasFootAmount">
                                    <div>ETB {{ number_format($tasTotalGross, 2) }}</div>
                                    @if($tasTotalVat > 0)
                                        <small class="text-success d-block font-monospace" style="font-size:0.68rem;" id="pageTasFootVat">VAT (15%): + ETB {{ number_format($tasTotalVat, 2) }}</small>
                                    @endif
                                    @if($tasTotalWht > 0)
                                        <small class="text-danger d-block font-monospace" style="font-size:0.68rem;" id="pageTasFootWht">WHT: - ETB {{ number_format($tasTotalWht, 2) }}</small>
                                        <small class="text-success d-block font-monospace" style="font-size:0.68rem;" id="pageTasFootNet">Net: ETB {{ number_format($tasTotalNet, 2) }}</small>
                                    @endif
                                </td>
                                <td colspan="7" class="pe-3 d-print-none"></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>

        {{-- ── TAB 4: SCANNED OCR RECEIPTS ───────────────────────────────────── --}}
        <div class="tab-pane fade {{ $activeTab === 'ocr' ? 'show active' : '' }}" id="page-ocr" role="tabpanel">
            <div class="card border-0 shadow-sm rounded-4 overflow-hidden bg-white">
                <div class="card-header bg-white border-bottom py-3 px-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div class="d-flex align-items-center gap-2">
                        <span class="badge bg-primary-subtle text-primary p-2 rounded-3"><i class="fa-solid fa-camera-retro fs-6"></i></span>
                        <div>
                            <div class="d-flex align-items-center gap-2">
                                <h6 class="fw-bold mb-0 text-dark">Scanned OCR Receipts (ERCA Cash Register Invoices)</h6>
                                <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill small fw-semibold px-2.5 py-0.5">
                                    <i class="fa-solid fa-shield-check me-1"></i>Verified Receipts
                                </span>
                            </div>
                            <small class="text-muted">Itemized receipts with verified 15% VAT or 0% TAS, Vendor TIN, FS Numbers, and Machine MRC</small>
                        </div>
                    </div>
                    <div class="d-flex align-items-center gap-2 d-print-none flex-wrap">
                        <div class="btn-group btn-group-sm shadow-xs rounded-pill p-0.5 bg-light border me-1" role="group">
                            <button type="button" 
                                    class="btn btn-sm rounded-pill px-2.5 py-1 fw-semibold {{ ($vatOnly ?? true) ? 'btn-success text-white shadow-xs' : 'text-muted' }}" 
                                    id="btnPageOcrVat" 
                                    onclick="filterPageOcrCategory('vat')" 
                                    title="Show Only Receipts with 15% VAT">
                                <i class="fa-solid fa-percent me-1"></i>Only VAT Receipts ({{ $movementsData['summary']['ocr_vat_count'] ?? count($movementsData['vat_ocr_items'] ?? []) }})
                            </button>
                            <button type="button" 
                                    class="btn btn-sm rounded-pill px-2.5 py-1 fw-semibold text-muted" 
                                    id="btnPageOcrTas" 
                                    onclick="filterPageOcrCategory('tas')" 
                                    title="Show Receipts with 0% VAT (TAS / Exempt)">
                                <i class="fa-solid fa-receipt me-1"></i>TAS Receipts (0% VAT) ({{ $movementsData['summary']['ocr_tas_count'] ?? count($movementsData['tas_ocr_items'] ?? []) }})
                            </button>
                            <button type="button" 
                                    class="btn btn-sm rounded-pill px-2.5 py-1 fw-semibold {{ !($vatOnly ?? true) ? 'btn-secondary text-white shadow-xs' : 'text-muted' }}" 
                                    id="btnPageOcrAll" 
                                    onclick="filterPageOcrCategory('all')" 
                                    title="Show All Scanned Invoices (including 0% VAT / exempt)">
                                <i class="fa-solid fa-list me-1"></i>All Scanned ({{ $movementsData['summary']['ocr_all_count'] ?? count($movementsData['all_ocr_items'] ?? ($movementsData['ocr_items'] ?? [])) }})
                            </button>
                        </div>
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
                        <div style="min-width: 220px;">
                            <input type="text" class="form-control form-control-sm rounded-pill" placeholder="Search vendor, FS #, item..." onkeyup="filterLocalTable('pageOcrTable', this.value)">
                        </div>
                    </div>
                </div>

                {{-- Deduplication Summary Bar --}}
                @php
                    $allOcrList = $movementsData['all_ocr_items'] ?? ($movementsData['ocr_items'] ?? []);
                    $totalScanned = (int)($movementsData['summary']['ocr_total_scanned'] ?? count($allOcrList));
                    $dupsRemoved = (int)($movementsData['summary']['ocr_duplicates_removed'] ?? count($movementsData['duplicates_removed'] ?? []));
                    $uniqueUsed = (int)($movementsData['summary']['ocr_unique_used'] ?? count($allOcrList));
                    $needsReviewCount = (int)($movementsData['summary']['ocr_needs_review_count'] ?? 0);
                @endphp
                <div class="px-3 py-2 bg-light bg-opacity-75 border-bottom d-flex align-items-center justify-content-between flex-wrap gap-2">
                    <div class="d-flex align-items-center gap-3 flex-wrap">
                        <div class="d-flex align-items-center gap-1.5 small">
                            <span class="text-muted fw-semibold"><i class="fa-solid fa-file-invoice me-1 text-secondary"></i>Total Receipts Scanned:</span>
                            <span class="badge bg-secondary text-white rounded-pill font-monospace fw-bold px-2 py-0.5">{{ $totalScanned }}</span>
                        </div>
                        <div class="d-flex align-items-center gap-1.5 small">
                            <span class="text-muted fw-semibold"><i class="fa-solid fa-clone me-1 text-danger"></i>Duplicates Removed:</span>
                            @if($dupsRemoved > 0)
                                <span class="badge bg-danger text-white rounded-pill font-monospace fw-bold px-2 py-0.5">{{ $dupsRemoved }}</span>
                            @else
                                <span class="badge bg-light text-muted border rounded-pill font-monospace px-2 py-0.5">0</span>
                            @endif
                        </div>
                        <div class="d-flex align-items-center gap-1.5 small">
                            <span class="text-muted fw-semibold"><i class="fa-solid fa-check-double me-1 text-success"></i>Unique Receipts Used:</span>
                            <span class="badge bg-success text-white rounded-pill font-monospace fw-bold px-2 py-0.5">{{ $uniqueUsed }}</span>
                        </div>
                        @if($needsReviewCount > 0)
                            <div class="d-flex align-items-center gap-1.5 small">
                                <span class="badge bg-warning text-dark border border-warning rounded-pill fw-semibold px-2.5 py-1 d-inline-flex align-items-center gap-1 shadow-xs" title="Missing or unreadable FS No. or Seller TIN — kept in matrix, manual review needed">
                                    <i class="fa-solid fa-triangle-exclamation"></i>
                                    <span>{{ $needsReviewCount }} Needs manual review</span>
                                </span>
                            </div>
                        @endif
                    </div>
                    <div class="small text-muted d-flex align-items-center gap-1">
                        <i class="fa-solid fa-circle-info text-primary"></i>
                        <span>Deduplicated by <strong>FS No.</strong> &amp; <strong>Seller TIN</strong> (keeps latest receipt).</span>
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
                            @forelse($allOcrList as $idx => $ocr)
                                @php
                                    $b = (float)($ocr['base_value'] ?? 0);
                                    $v = (float)($ocr['vat_amount'] ?? 0);
                                    $a = (float)($ocr['value_after_vat'] ?? ($ocr['total_value'] ?? 0));
                                    $ocrSumBase += $b;
                                    $ocrSumVat  += $v;
                                    $ocrSumAfter += $a;
                                    $hasSlip = !empty($ocr['file_url']);
                                    $isPdf = !empty($ocr['is_pdf']);
                                    $isVatReceipt = !empty($ocr['is_vat_receipt']) || $v > 0;
                                    $needsReview = !empty($ocr['needs_manual_review']);
                                @endphp
                                <tr id="page-ocr-row-{{ $ocr['id'] }}"
                                    data-idx="{{ $idx }}"
                                    data-id="{{ $ocr['id'] }}"
                                    data-receipt-id="{{ $ocr['receipt_id'] ?? '' }}"
                                    data-is-vat="{{ $isVatReceipt ? '1' : '0' }}"
                                    data-ocr-category="{{ $isVatReceipt ? 'vat' : 'tas' }}"
                                    style="{{ (($vatOnly ?? true) && !$isVatReceipt) ? 'display: none;' : '' }}">
                                    <td class="ps-3 text-muted font-monospace small">{{ $idx + 1 }}</td>
                                    <td>
                                        <span class="fw-bold font-monospace text-dark d-block cell-date-ec">{{ $ocr['date_ec'] ?? '-' }}</span>
                                        <small class="text-muted cell-date-gc" style="font-size:0.75rem;">{{ $ocr['date_gc'] ?? '-' }}</small>
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-center gap-1.5 flex-wrap">
                                            <span class="badge bg-light text-primary border font-monospace cell-fs">{{ $ocr['fs_no'] ?? '-' }}</span>
                                            @if($needsReview)
                                                <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle rounded-pill small fw-semibold px-2 py-0.5 d-inline-flex align-items-center gap-1" title="{{ $ocr['review_reason'] ?? 'Needs manual review' }}">
                                                    <i class="fa-solid fa-triangle-exclamation"></i>Needs manual review
                                                </span>
                                            @endif
                                        </div>
                                        <small class="text-muted d-block cell-receipt-no" style="font-size:0.75rem;">{{ $ocr['receipt_number'] ?? '' }}</small>
                                        @if(!empty($ocr['mrc_no']) && $ocr['mrc_no'] !== '-')
                                            <small class="text-muted font-monospace d-block cell-mrc" style="font-size:0.7rem;">MRC: {{ $ocr['mrc_no'] }}</small>
                                        @else
                                            <small class="text-muted font-monospace d-block cell-mrc" style="font-size:0.7rem; display:none;"></small>
                                        @endif
                                    </td>
                                    <td>
                                        <span class="fw-semibold text-dark d-block cell-vendor">{{ $ocr['vendor_name'] ?? 'Vendor' }}</span>
                                        <small class="text-muted font-monospace cell-tin" style="font-size:0.75rem;">TIN: {{ $ocr['vendor_tin'] ?? '-' }}</small>
                                    </td>
                                    <td>
                                        <span class="badge bg-secondary-subtle text-secondary rounded-pill small cell-project">{{ $ocr['project_name'] ?? 'General' }}</span>
                                        <small class="text-muted d-block cell-category" style="font-size:0.75rem;">{{ $ocr['category'] ?? '' }}</small>
                                    </td>
                                    <td class="small text-dark text-truncate" style="max-width: 220px;" title="{{ $ocr['item_description'] ?? '' }}">
                                        <span class="cell-desc">{{ $ocr['item_description'] ?? '-' }}</span>
                                    </td>
                                    <td class="text-end font-monospace"><span class="cell-base">ETB {{ number_format($b, 2) }}</span></td>
                                    <td class="text-end font-monospace">
                                        @if($isVatReceipt)
                                            <span class="cell-vat text-success fw-semibold">ETB {{ number_format($v, 2) }}</span>
                                        @else
                                            <span class="badge bg-light text-muted border font-monospace" style="font-size:0.68rem;">0% VAT (TAS)</span>
                                            <span class="cell-vat d-none">0.00</span>
                                        @endif
                                    </td>
                                    <td class="text-end font-monospace text-dark fw-bold"><span class="cell-total">ETB {{ number_format($a, 2) }}</span></td>
                                    <td class="text-center pe-3 d-print-none">
                                        <button type="button" class="btn btn-xs {{ $hasSlip ? ($isPdf ? 'btn-outline-danger' : 'btn-outline-info') : 'btn-outline-primary' }} rounded-pill px-2.5 py-1 d-inline-flex align-items-center gap-1.5 shadow-xs" 
                                                onclick="openPageOcrInspector({{ $idx }})" title="Inspect Receipt Slip & Edit Declaration">
                                            <i class="fa-solid {{ $isPdf ? 'fa-file-pdf text-danger' : ($hasSlip ? 'fa-receipt text-info' : 'fa-pen-to-square text-primary') }}"></i>
                                            <span>{{ $hasSlip ? ($isPdf ? 'PDF Slip' : 'See Slip') : 'Inspect' }}</span>
                                            <i class="fa-solid fa-pen-to-square ms-0.5 text-muted" style="font-size: 0.65rem;"></i>
                                        </button>
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
                                <td class="text-end py-2 font-monospace text-dark" id="pageOcrSumBase">ETB {{ number_format($ocrSumBase, 2) }}</td>
                                <td class="text-end py-2 font-monospace text-success" id="pageOcrSumVat">ETB {{ number_format($ocrSumVat, 2) }}</td>
                                <td class="text-end py-2 font-monospace text-dark" id="pageOcrSumAfter">ETB {{ number_format($ocrSumAfter, 2) }}</td>
                                <td class="pe-3 d-print-none"></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>

            {{-- ── DUPLICATES REMOVED TABLE ──────────────────────────────────── --}}
            <div class="card border-0 shadow-sm rounded-4 overflow-hidden bg-white mt-4">
                <div class="card-header bg-white border-bottom py-3 px-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div class="d-flex align-items-center gap-2">
                        <span class="badge bg-danger-subtle text-danger p-2 rounded-3"><i class="fa-solid fa-clone fs-6"></i></span>
                        <div>
                            <div class="d-flex align-items-center gap-2">
                                <h6 class="fw-bold mb-0 text-dark">Duplicates Removed</h6>
                                @php $dupList = $movementsData['duplicates_removed'] ?? []; @endphp
                                @if(count($dupList) > 0)
                                    <span class="badge bg-danger text-white rounded-pill small fw-semibold px-2.5 py-0.5">
                                        {{ $dupsRemoved }} Duplicates Excluded
                                    </span>
                                @else
                                    <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill small fw-semibold px-2.5 py-0.5">
                                        <i class="fa-solid fa-check me-1"></i>0 Duplicates
                                    </span>
                                @endif
                            </div>
                            <small class="text-muted">Receipts with identical FS No. &amp; Seller TIN — only the latest receipt was kept; older duplicates excluded from matrix and totals</small>
                        </div>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" id="pageOcrDuplicatesTable">
                        <thead class="table-light text-muted text-uppercase fw-bold" style="font-size: 0.7rem;">
                            <tr>
                                <th class="ps-3 py-2.5">FS No.</th>
                                <th class="py-2.5">Seller TIN</th>
                                <th class="py-2.5">Kept Receipt (date/ref)</th>
                                <th class="py-2.5">Removed Receipt(s) (date/ref)</th>
                                <th class="py-2.5 pe-3">Reason</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            @forelse($dupList as $dup)
                                <tr>
                                    <td class="ps-3">
                                        <span class="badge bg-light text-primary border font-monospace fs-6">{{ $dup['fs_no'] ?? '-' }}</span>
                                    </td>
                                    <td>
                                        <span class="fw-semibold text-dark d-block">{{ $dup['vendor_name'] ?? 'Vendor' }}</span>
                                        <small class="text-muted font-monospace" style="font-size: 0.75rem;">TIN: {{ $dup['seller_tin'] ?? '-' }}</small>
                                    </td>
                                    <td>
                                        <div class="d-inline-flex align-items-center gap-2 p-2 rounded-3 bg-success-subtle bg-opacity-50 border border-success-subtle">
                                            <span class="badge bg-success text-white rounded-pill px-2 py-0.5 small fw-semibold">
                                                <i class="fa-solid fa-check me-1"></i>Kept
                                            </span>
                                            <div>
                                                <span class="fw-bold font-monospace text-dark d-block" style="font-size: 0.8rem;">{{ $dup['kept_receipt']['ref'] ?? '' }}</span>
                                                <small class="text-muted" style="font-size: 0.75rem;">{{ $dup['kept_receipt']['date'] ?? '-' }} &bull; ETB {{ number_format($dup['kept_receipt']['amount'] ?? 0, 2) }}</small>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="d-flex flex-column gap-1.5">
                                            @foreach($dup['removed_receipts'] ?? [] as $rem)
                                                <div class="d-inline-flex align-items-center gap-2 p-1.5 rounded-3 bg-danger-subtle bg-opacity-35 border border-danger-subtle">
                                                    <span class="badge bg-danger text-white rounded-pill px-2 py-0.5 small fw-semibold">
                                                        <i class="fa-solid fa-trash-can me-1"></i>Removed
                                                    </span>
                                                    <div>
                                                        <span class="font-monospace text-muted d-block text-decoration-line-through" style="font-size: 0.8rem;">{{ $rem['ref'] ?? '' }}</span>
                                                        <small class="text-muted" style="font-size: 0.75rem;">{{ $rem['date'] ?? '-' }} &bull; ETB {{ number_format($rem['amount'] ?? 0, 2) }}</small>
                                                    </div>
                                                </div>
                                            @endforeach
                                        </div>
                                    </td>
                                    <td class="pe-3">
                                        <span class="small text-muted">{{ $dup['reason'] ?? 'Identical FS No. and Seller TIN' }}</span>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="text-center py-4 text-muted">
                                        <i class="fa-solid fa-shield-check text-success me-2 fs-5"></i>No duplicate OCR receipts found for this period. Every receipt has a unique FS No. and Seller TIN.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
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

{{-- ── Side-by-Side OCR Inspector & Live Edit Modal ────────────────────────────── --}}
<div class="modal fade" id="movementSlipModal" tabindex="-1" aria-labelledby="movementSlipModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl modal-dialog-scrollable my-lg-3" style="max-height: 94vh;">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden d-flex flex-column" style="max-height: 94vh;">
            
            {{-- Fixed Modal Header --}}
            <div class="modal-header bg-dark text-white py-2.5 px-4 flex-shrink-0" style="background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%) !important;">
                <div class="d-flex align-items-center gap-2.5">
                    <div class="bg-primary bg-opacity-25 text-info p-2 rounded-circle d-flex align-items-center justify-content-center" style="width:36px; height:36px;">
                        <i class="fa-solid fa-receipt fs-5"></i>
                    </div>
                    <div>
                        <h5 class="modal-title fw-bold mb-0 text-white fs-6" id="movementSlipModalLabel">
                            ERCA Cash Register Invoice &amp; Fiscal Slip Inspector &mdash; <span id="movementModalFsNoDisplay" class="font-monospace text-info"></span>
                        </h5>
                        <small class="text-white-50" style="font-size:0.75rem;">
                            Side-by-side reconciliation: Edit declaration &amp; fiscal details on the left &bull; Inspect scanned receipt on the right
                        </small>
                    </div>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <span class="badge bg-primary text-white rounded-pill px-2.5 py-1 small font-monospace"><i class="fa-solid fa-lock-open me-1"></i>Amount Adjustment Unlocked (Global Admin)</span>
                    <span id="movementModalHeaderStatusBadge" class="badge bg-info-subtle text-info border border-info-subtle rounded-pill px-2.5 py-1 small font-monospace">ERCA Invoice</span>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
            </div>

            {{-- Scrollable Modal Body (Side-by-Side) --}}
            <div class="modal-body p-3 p-lg-4 bg-light bg-opacity-50" style="overflow-y: auto;">
                <div class="row g-3 g-lg-4 align-items-stretch">
                    
                    {{-- LEFT COLUMN: Option to edit details to meet our system --}}
                    <div class="col-12 col-lg-6">
                        <div class="card border-0 shadow-xs rounded-3 h-100 bg-white">
                            <div class="card-header bg-white border-bottom py-2.5 px-3 d-flex align-items-center justify-content-between">
                                <div class="d-flex align-items-center gap-2">
                                    <i class="fa-solid fa-pen-to-square text-primary"></i>
                                    <span class="fw-bold text-dark small text-uppercase">Fiscal Declaration &amp; System Adjustment</span>
                                </div>
                                <span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill font-monospace" id="movement_edit_ocr_badge_id">Invoice #</span>
                            </div>
                            <div class="card-body p-3">
                                <div id="movementOcrModalAlert" class="alert d-none py-2 px-3 small rounded-3 mb-3"></div>

                                <form id="movementOcrModalEditForm" novalidate onsubmit="saveMovementOcrSlipEdits(event); return false;">
                                    <input type="hidden" id="movement_edit_ocr_item_id" name="id">
                                    <input type="hidden" id="movement_edit_receipt_id" name="receipt_id">
                                    <input type="hidden" id="movement_edit_expense_request_id" name="expense_request_id">
                                    <input type="hidden" id="movement_edit_request_number" name="request_number">
                                    <input type="hidden" id="movement_edit_receipt_file_path" name="receipt_path">
                                    <input type="file" id="movementSlipFileInput" name="receipt_file" class="d-none" accept=".jpg,.jpeg,.png,.webp,.pdf" onchange="if(this.files &amp;&amp; this.files[0]) handleMovementReceiptFileSelect(this.files[0])">
                                    <input type="file" id="movementSlipCameraInput" class="d-none" accept="image/*" capture="environment" onchange="if(this.files &amp;&amp; this.files[0]) handleMovementReceiptFileSelect(this.files[0])">

                                    {{-- Supplier Name --}}
                                    <div class="mb-2.5">
                                        <label class="form-label small fw-bold text-dark mb-1">
                                            <i class="fa-solid fa-store text-muted me-1"></i>Supplier / Merchant Name <span class="text-danger">*</span>
                                        </label>
                                        <input type="text" class="form-control form-control-sm rounded-2" id="movement_edit_seller_name" name="seller_name" required placeholder="e.g. TotalEnergies Marketing Ethiopia">
                                    </div>

                                    {{-- Row: Supplier TIN & FS # --}}
                                    <div class="row g-2 mb-2.5">
                                        <div class="col-6">
                                            <label class="form-label small fw-bold text-dark mb-1">
                                                <i class="fa-solid fa-id-card text-muted me-1"></i>Supplier TIN <span class="text-danger">*</span>
                                            </label>
                                            <div class="input-group input-group-sm">
                                                <input type="text" class="form-control font-monospace fw-bold" id="movement_edit_supplier_tin" name="supplier_tin" maxlength="12" placeholder="10 Digits" oninput="validateMovementTin(this.value)">
                                                <span class="input-group-text p-1 px-2" id="movement_edit_tin_badge" title="TIN Status"><i class="fa-solid fa-minus text-muted"></i></span>
                                            </div>
                                            <div class="form-text text-muted" style="font-size:0.68rem;" id="movement_edit_tin_hint">ERCA requires 10 digits</div>
                                        </div>
                                        <div class="col-6">
                                            <label class="form-label small fw-bold text-dark mb-1">
                                                <i class="fa-solid fa-receipt text-muted me-1"></i>FS / Receipt # <span class="text-danger">*</span>
                                            </label>
                                            <div class="input-group input-group-sm">
                                                <input type="text" class="form-control font-monospace fw-bold text-primary" id="movement_edit_fs_no" name="fs_no" maxlength="15" placeholder="e.g. 00012345" oninput="validateMovementFs(this.value)">
                                                <span class="input-group-text p-1 px-2" id="movement_edit_fs_badge" title="FS # Status"><i class="fa-solid fa-minus text-muted"></i></span>
                                            </div>
                                            <div class="form-text text-muted" style="font-size:0.68rem;" id="movement_edit_fs_hint">Standard 8-digit fiscal slip</div>
                                        </div>
                                    </div>

                                    {{-- Row: MRC # & Receipt Date --}}
                                    <div class="row g-2 mb-2.5">
                                        <div class="col-6">
                                            <label class="form-label small fw-bold text-dark mb-1">
                                                <i class="fa-solid fa-microchip text-muted me-1"></i>MRC # (Machine Registration)
                                            </label>
                                            <input type="text" class="form-control form-control-sm font-monospace" id="movement_edit_mrc_no" name="mrc_no" placeholder="e.g. MTR12345678">
                                        </div>
                                        <div class="col-6">
                                            <label class="form-label small fw-bold text-dark mb-1">
                                                <i class="fa-regular fa-calendar text-muted me-1"></i>Receipt Date
                                            </label>
                                            <input type="date" class="form-control form-control-sm font-monospace" id="movement_edit_receipt_date" name="receipt_date" onchange="syncMovementDateToEc(this.value)">
                                            <div class="form-text text-primary font-monospace fw-semibold" style="font-size:0.68rem;" id="movement_edit_date_ec_preview"></div>
                                        </div>
                                    </div>

                                    {{-- Item Description --}}
                                    <div class="mb-2.5">
                                        <label class="form-label small fw-bold text-dark mb-1">
                                            <i class="fa-solid fa-tag text-muted me-1"></i>Item Description / Particulars <span class="text-danger">*</span>
                                        </label>
                                        <input type="text" class="form-control form-control-sm rounded-2" id="movement_edit_item_description" name="item_description" required placeholder="e.g. Fuel, Cement, Office stationery">
                                    </div>

                                    {{-- Project & Category --}}
                                    <div class="row g-2 mb-2.5">
                                        <div class="col-6">
                                            <label class="form-label small fw-bold text-dark mb-1">
                                                <i class="fa-solid fa-building text-muted me-1"></i>Project Attribution
                                            </label>
                                            <select class="form-select form-select-sm" id="movement_edit_project_id" name="project_id">
                                                <option value="">-- General Company / HQ --</option>
                                                @foreach($ocrProjects ?? [] as $proj)
                                                    <option value="{{ $proj->id }}">{{ $proj->name }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="col-6">
                                            <label class="form-label small fw-bold text-dark mb-1">
                                                <i class="fa-solid fa-folder text-muted me-1"></i>Expense Category
                                            </label>
                                            <select class="form-select form-select-sm" id="movement_edit_category" name="category">
                                                @foreach($ocrCategories ?? [] as $catKey => $catLabel)
                                                    <option value="{{ $catKey }}">{{ $catLabel }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>

                                    {{-- VAT Category & Purchase Type --}}
                                    <div class="row g-2 mb-2.5">
                                        <div class="col-6">
                                            <label class="form-label small fw-bold text-dark mb-1">
                                                <i class="fa-solid fa-shapes text-muted me-1"></i>VAT Category
                                            </label>
                                            <select class="form-select form-select-sm" id="movement_edit_vat_category" name="vat_category">
                                                <option value="G">Goods (G)</option>
                                                <option value="S">Services (S)</option>
                                                <option value="E">Exempt / Zero VAT (E - TAS)</option>
                                            </select>
                                        </div>
                                        <div class="col-6">
                                            <label class="form-label small fw-bold text-dark mb-1">
                                                <i class="fa-solid fa-list-ol text-muted me-1"></i>ERCA Purchase Type
                                            </label>
                                            <select class="form-select form-select-sm" id="movement_edit_purchase_type" name="purchase_type">
                                                <option value="1">1 - Raw Materials / Inputs</option>
                                                <option value="2">2 - Capital Goods / Equipment</option>
                                                <option value="3">3 - General Operating &amp; Consumables</option>
                                                <option value="4">4 - Subcontracting Services</option>
                                                <option value="5">5 - Utilities &amp; Telecommunications</option>
                                                <option value="6">6 - Other Taxable Inward Supplies</option>
                                                <option value="PUR_GD">PUR_GD - e-Tax Goods (TAS Standard)</option>
                                                <option value="PUR_SVC">PUR_SVC - e-Tax Services (TAS Standard)</option>
                                            </select>
                                        </div>
                                    </div>

                                    {{-- Verify Without Receipt Switch --}}
                                    <div class="p-2.5 bg-info bg-opacity-10 rounded-3 border border-info border-opacity-25 mb-2.5">
                                        <div class="d-flex align-items-center justify-content-between">
                                            <div class="form-check form-switch mb-0">
                                                <input class="form-check-input" type="checkbox" id="movement_edit_is_verified_without_receipt" name="is_verified_without_receipt" value="1" onchange="onToggleVerifyWithoutReceiptModal(this.checked)">
                                                <label class="form-check-label small fw-bold text-dark" for="movement_edit_is_verified_without_receipt">
                                                    <i class="fa-solid fa-shield-check text-info me-1"></i>Verify Without Receipt (Operational / No Fiscal Slip)
                                                </label>
                                            </div>
                                            <span class="badge bg-secondary-subtle text-muted font-monospace" style="font-size:0.68rem;" id="movement_verified_no_rec_badge">0% Exempt</span>
                                        </div>
                                        <small class="text-muted d-block mt-1" style="font-size:0.72rem;">
                                            Mark this expense as an approved operational expenditure without an official fiscal machine receipt (applies 0% VAT / exempt for ERCA audit).
                                        </small>
                                    </div>

                                    {{-- Financial Amounts & Live Recalculation --}}
                                    <div class="p-2.5 bg-light rounded-3 border mb-0">
                                        <div class="d-flex align-items-center justify-content-between mb-2">
                                            <span class="small fw-bold text-dark">
                                                <i class="fa-solid fa-calculator text-primary me-1"></i>Financial Reconciliation
                                            </span>
                                            <span class="badge bg-secondary-subtle text-secondary" style="font-size:0.68rem;">Live Synced</span>
                                        </div>
                                        <div class="row g-2">
                                            <div class="col-4">
                                                <label class="form-label small fw-semibold text-muted mb-0.5" style="font-size:0.7rem;">Taxable Base</label>
                                                <div class="input-group input-group-sm">
                                                    <span class="input-group-text px-1 text-muted" style="font-size:0.7rem;">ETB</span>
                                                    <input type="number" step="0.01" min="0" class="form-control font-monospace fw-semibold" id="movement_edit_total_value" name="total_value" required oninput="recalcMovementFromBase(this.value)">
                                                </div>
                                            </div>
                                            <div class="col-4">
                                                <label class="form-label small fw-semibold text-info mb-0.5" style="font-size:0.7rem;">VAT Amount</label>
                                                <div class="input-group input-group-sm">
                                                    <span class="input-group-text px-1 text-info" style="font-size:0.7rem;">+</span>
                                                    <input type="number" step="0.01" min="0" class="form-control font-monospace fw-bold text-info" id="movement_edit_vat_amount" name="vat_amount" required oninput="recalcMovementFromVat(this.value)">
                                                </div>
                                            </div>
                                            <div class="col-4">
                                                <label class="form-label small fw-semibold text-success mb-0.5" style="font-size:0.7rem;">Total Value</label>
                                                <div class="input-group input-group-sm">
                                                    <span class="input-group-text px-1 text-success" style="font-size:0.7rem;">=</span>
                                                    <input type="number" step="0.01" min="0" class="form-control font-monospace fw-bold text-success" id="movement_edit_value_after_vat" name="value_after_vat" required oninput="recalcMovementFromTotal(this.value)">
                                                </div>
                                            </div>
                                        </div>
                                        <div class="d-flex justify-content-between align-items-center mt-2 pt-1 border-top" style="font-size:0.72rem;">
                                            <button type="button" class="btn btn-link btn-xs p-0 text-decoration-none text-muted" onclick="autoCalcMovementVatFromBase()">
                                                <i class="fa-solid fa-wand-magic-sparkles me-1 text-primary"></i>Base &rarr; 15% VAT
                                            </button>
                                            <button type="button" class="btn btn-link btn-xs p-0 text-decoration-none text-primary fw-semibold" onclick="setMovementZeroVat()">
                                                <i class="fa-solid fa-receipt me-1"></i>0% VAT (TAS Purchase)
                                            </button>
                                            <button type="button" class="btn btn-link btn-xs p-0 text-decoration-none text-muted" onclick="autoExtractMovementBaseFromTotal()">
                                                <i class="fa-solid fa-wand-magic-sparkles me-1 text-success"></i>Total &rarr; Base (/1.15)
                                            </button>
                                        </div>
                                    </div>

                                    {{-- Withholding Tax (WHT) Section --}}
                                    <div class="p-2.5 bg-warning bg-opacity-10 rounded-3 border border-warning border-opacity-25 mt-2.5">
                                        <div class="d-flex align-items-center justify-content-between mb-1.5">
                                            <div class="form-check form-switch mb-0">
                                                <input class="form-check-input" type="checkbox" id="movement_edit_has_withholding" name="has_withholding" value="1" onchange="toggleMovementWithholding(this.checked)">
                                                <label class="form-check-label small fw-bold text-dark" for="movement_edit_has_withholding">
                                                    <i class="fa-solid fa-hand-holding-dollar text-warning me-1"></i>Apply Withholding Tax (WHT)
                                                </label>
                                            </div>
                                            <span class="badge bg-secondary-subtle text-muted font-monospace" style="font-size:0.68rem;" id="movement_wht_status_badge">No WHT</span>
                                        </div>

                                        <div id="movement_wht_fields_container" class="d-none">
                                            <div class="row g-2 mb-2">
                                                <div class="col-4">
                                                    <label class="form-label small fw-semibold text-muted mb-0.5" style="font-size:0.7rem;">WHT Rate</label>
                                                    <select class="form-select form-select-sm font-monospace" id="movement_edit_withholding_rate" name="withholding_rate" onchange="onMovementWhtRateChange(this.value)">
                                                        <option value="2">2.0% (Goods Supply)</option>
                                                        <option value="3" selected>3.0% (Services Standard)</option>
                                                        <option value="30">30.0% (No TIN / Unregistered)</option>
                                                        <option value="custom">Custom Rate...</option>
                                                    </select>
                                                    <input type="number" step="0.1" min="0" max="100" class="form-control form-control-sm font-monospace d-none mt-1" id="movement_edit_custom_wht_rate" placeholder="Custom %" oninput="recalcMovementWht()">
                                                </div>
                                                <div class="col-4">
                                                    <label class="form-label small fw-semibold text-danger mb-0.5" style="font-size:0.7rem;">WHT Amount</label>
                                                    <div class="input-group input-group-sm">
                                                        <span class="input-group-text px-1 text-danger" style="font-size:0.7rem;">-</span>
                                                        <input type="number" step="0.01" min="0" class="form-control font-monospace fw-bold text-danger" id="movement_edit_withholding_amount" name="withholding_amount" oninput="recalcMovementWhtCustomAmount(this.value)">
                                                    </div>
                                                </div>
                                                <div class="col-4">
                                                    <label class="form-label small fw-semibold text-dark mb-0.5" style="font-size:0.7rem;">Net Disbursed</label>
                                                    <div class="input-group input-group-sm">
                                                        <span class="input-group-text px-1 text-dark" style="font-size:0.7rem;">=</span>
                                                        <input type="text" class="form-control font-monospace fw-bold text-dark bg-white" id="movement_edit_net_disbursed_display" readonly>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="row g-2">
                                                <div class="col-6">
                                                    <label class="form-label small fw-semibold text-muted mb-0.5" style="font-size:0.7rem;">WHT Certificate / Voucher #</label>
                                                    <input type="text" class="form-control form-control-sm font-monospace" id="movement_edit_withholding_receipt_number" name="withholding_receipt_number" placeholder="e.g. WHT-2026-0042">
                                                </div>
                                                <div class="col-6">
                                                    <label class="form-label small fw-semibold text-muted mb-0.5" style="font-size:0.7rem;">WHT Certificate Voucher Slip</label>
                                                    <div class="d-flex align-items-center gap-1">
                                                        <input type="file" class="form-control form-control-sm" id="movement_edit_withholding_file" name="withholding_file" accept=".jpg,.jpeg,.png,.webp,.pdf">
                                                        <a id="movement_edit_current_wht_slip_link" href="#" target="_blank" class="btn btn-xs btn-outline-warning text-nowrap d-none py-1 px-2" title="View attached WHT certificate">
                                                            <i class="fa-solid fa-file-invoice"></i> View
                                                        </a>
                                                    </div>
                                                    <input type="hidden" id="movement_edit_withholding_receipt_path" name="withholding_receipt_path">
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                    {{-- RIGHT COLUMN: Receipt Slip Preview --}}
                    <div class="col-12 col-lg-6">
                        <div class="card border-0 shadow-xs rounded-3 h-100 bg-white d-flex flex-column">
                            <div class="card-header bg-white border-bottom py-2 px-3 d-flex align-items-center justify-content-between flex-shrink-0 flex-wrap gap-2">
                                <div class="d-flex align-items-center gap-2">
                                    <i class="fa-solid fa-image text-info"></i>
                                    <span class="fw-bold text-dark small text-uppercase">Original Scanned Receipt Slip</span>
                                </div>
                                <div class="d-flex align-items-center gap-1.5 flex-wrap">
                                    <button type="button" class="btn btn-primary btn-sm py-1 px-2.5 rounded-pill shadow-xs d-inline-flex align-items-center gap-1.5 fw-semibold" onclick="triggerMovementReceiptUpload()" title="Upload or Replace Receipt Slip">
                                        <i class="fa-solid fa-arrow-up-from-bracket"></i>
                                        <span>Upload Slip</span>
                                    </button>
                                    <button type="button" class="btn btn-outline-secondary btn-sm py-1 px-2 rounded-pill shadow-xs" onclick="triggerMovementReceiptCamera()" title="Take Photo with Camera">
                                        <i class="fa-solid fa-camera"></i>
                                    </button>
                                    <div class="btn-group btn-group-sm shadow-xs ms-1" role="group">
                                        <button type="button" class="btn btn-outline-secondary btn-sm py-1 px-2" onclick="zoomPageSlip(0.15)" title="Zoom In"><i class="fa-solid fa-magnifying-glass-plus"></i></button>
                                        <button type="button" class="btn btn-outline-secondary btn-sm py-1 px-2" onclick="zoomPageSlip(-0.15)" title="Zoom Out"><i class="fa-solid fa-magnifying-glass-minus"></i></button>
                                        <button type="button" class="btn btn-outline-secondary btn-sm py-1 px-2" onclick="rotatePageSlip()" title="Rotate 90°"><i class="fa-solid fa-rotate-right"></i></button>
                                        <button type="button" class="btn btn-outline-secondary btn-sm py-1 px-2" onclick="resetPageSlip()" title="Reset View"><i class="fa-solid fa-arrows-rotate"></i></button>
                                        <a id="movementModalSlipOpenNewTab" href="#" target="_blank" class="btn btn-outline-secondary btn-sm py-1 px-2" title="Open in New Tab"><i class="fa-solid fa-up-right-from-square"></i></a>
                                    </div>
                                </div>
                            </div>
                            <div class="card-body p-2 flex-grow-1 d-flex flex-column">
                                {{-- Upload & AI OCR Scanning Progress Bar --}}
                                <div id="movementUploadProgress" class="alert alert-info py-2 px-3 small rounded-3 mb-2 d-none align-items-center justify-content-between flex-shrink-0">
                                    <div class="d-flex align-items-center gap-2 text-truncate me-2">
                                        <i class="fa-solid fa-circle-notch fa-spin text-info fs-6 flex-shrink-0" id="movementUploadSpinner"></i>
                                        <span id="movementUploadStatusText" class="text-truncate">Processing slip with AI Vision OCR...</span>
                                    </div>
                                    <span class="badge bg-info text-white rounded-pill px-2 py-0.5 flex-shrink-0" id="movementUploadBadge">Scanning</span>
                                </div>

                                <div class="flex-grow-1 position-relative border rounded-3 bg-light d-flex align-items-center justify-content-center p-2" style="min-height: 420px; max-height: 520px; overflow: auto; transition: border-color 0.2s, background-color 0.2s;" id="movementSlipViewport">
                                    <img id="movementSlipImg" src="" alt="Receipt Slip" class="img-fluid rounded shadow-sm" style="max-height: 480px; width: auto; object-fit: contain; transition: transform 0.2s ease;">
                                    <iframe id="movementSlipPdf" src="" class="w-100 rounded d-none" style="height: 480px; border: none;"></iframe>

                                    {{-- Interactive Dropzone Empty State --}}
                                    <div id="movementSlipEmpty" class="text-center p-4 d-none w-100 h-100 d-flex flex-column align-items-center justify-content-center">
                                        <div class="p-3 bg-primary bg-opacity-10 text-primary rounded-circle d-inline-block mb-2 shadow-xs">
                                            <i class="fa-solid fa-cloud-arrow-up fs-2"></i>
                                        </div>
                                        <div class="fw-bold text-dark small mb-1">No Scanned Slip File Attached</div>
                                        <div class="text-muted mb-3" style="font-size:0.78rem; max-width: 320px;">
                                            Drag &amp; drop receipt slip here (JPG, PNG, PDF up to 25MB) or choose an action below to attach &amp; auto-extract:
                                        </div>
                                        <div class="d-flex gap-2 justify-content-center flex-wrap">
                                            <button type="button" class="btn btn-primary btn-sm rounded-pill px-3 shadow-xs fw-semibold" onclick="triggerMovementReceiptUpload()">
                                                <i class="fa-solid fa-arrow-up-from-bracket me-1.5"></i>Upload Receipt / Slip
                                            </button>
                                            <button type="button" class="btn btn-outline-secondary btn-sm rounded-pill px-3" onclick="triggerMovementReceiptCamera()">
                                                <i class="fa-solid fa-camera me-1.5"></i>Take Photo
                                            </button>
                                        </div>
                                        <div class="text-muted mt-3 pt-2 border-top w-75" style="font-size:0.72rem;">
                                            <i class="fa-solid fa-wand-magic-sparkles text-primary me-1"></i>AI OCR will auto-extract FS #, Supplier TIN, Name &amp; Amounts into the form on the left.
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="card-footer bg-light border-top py-2 px-3 d-flex align-items-center justify-content-between flex-shrink-0 flex-wrap gap-2">
                                <span class="small text-muted font-monospace text-truncate me-2" id="movementSlipFilename" style="font-size:0.75rem; max-width: 220px;">Document: Receipt Slip</span>
                                <div class="d-flex gap-1.5 align-items-center flex-wrap">
                                    <button type="button" class="btn btn-outline-primary btn-sm rounded-pill px-2.5 shadow-xs" onclick="triggerMovementReceiptUpload()" title="Upload or Replace Slip">
                                        <i class="fa-solid fa-arrow-up-from-bracket me-1"></i>Replace Slip
                                    </button>
                                    <a id="movementSlipDownloadBtn" href="#" target="_blank" download class="btn btn-outline-info btn-sm rounded-pill px-2.5 shadow-xs">
                                        <i class="fa-solid fa-download me-1"></i>Download
                                    </a>
                                    <a id="movementModalStudioLink" href="{{ route('admin.ocr.index') }}" target="_blank" class="btn btn-outline-secondary btn-sm rounded-pill px-2.5">
                                        <i class="fa-solid fa-wand-magic-sparkles me-1"></i>OCR Studio
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Fixed Modal Footer --}}
            <div class="modal-footer bg-white border-top py-2.5 px-4 d-flex justify-content-between align-items-center flex-shrink-0">
                <div class="d-flex align-items-center gap-2 text-muted small">
                    <i class="fa-solid fa-shield-halved text-success"></i>
                    <span class="d-none d-sm-inline">Reconciles ERCA VAT Cash Register Invoices with System Financial Ledgers</span>
                </div>
                <div class="d-flex gap-2 align-items-center">
                    <button type="button" class="btn btn-outline-secondary rounded-pill px-3.5 py-1.5" data-bs-dismiss="modal">
                        Close
                    </button>
                    <button type="button" id="movement_edit_submit_btn" onclick="saveMovementOcrSlipEdits(event)" class="btn btn-primary rounded-pill px-4 py-1.5 shadow-sm fw-semibold">
                        <i class="fa-solid fa-floppy-disk me-1.5"></i>Save Changes &amp; Update System
                    </button>
                </div>
            </div>

        </div>
    </div>
</div>

{{-- ── TAS Purchases Expense & Withholding Tax (WHT) Detail Modal ── --}}
<div class="modal fade" id="tasExpenseDetailModal" tabindex="-1" aria-labelledby="tasExpenseDetailModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable my-lg-3" style="max-height: 94vh;">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden d-flex flex-column" style="max-height: 94vh;">

            {{-- Header --}}
            <div class="modal-header bg-dark text-white py-2.5 px-4 flex-shrink-0" style="background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%) !important;">
                <div class="d-flex align-items-center gap-2.5">
                    <div class="p-2 bg-info bg-opacity-20 text-info rounded-3 border border-info border-opacity-25 d-flex align-items-center justify-content-center shadow-xs">
                        <i class="fa-solid fa-file-invoice-dollar fs-5"></i>
                    </div>
                    <div>
                        <div class="d-flex align-items-center gap-2 flex-wrap">
                            <h5 class="modal-title fs-6 fw-bold text-white mb-0" id="tasExpenseDetailModalLabel">
                                Expense &amp; Withholding Tax (WHT) Detail
                            </h5>
                            <span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill font-monospace" style="font-size:0.72rem;" id="tasModalVoucherBadge">
                                EXP-0000
                            </span>
                            <span class="badge rounded-pill fw-semibold" style="font-size:0.72rem;" id="tasModalWhtStatusBadge">
                                WHT Status
                            </span>
                        </div>
                        <div class="text-white-50 small mt-0.5" style="font-size:0.75rem;">
                            <span id="tasModalPayeeSubtitle">Vendor</span> &bull;
                            <span class="font-monospace text-warning fw-semibold" id="tasModalAmountSubtitle">ETB 0.00</span> &bull;
                            <span id="tasModalCategorySubtitle">TAS Purchase (0% VAT)</span>
                        </div>
                    </div>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            {{-- Body --}}
            <div class="modal-body p-3 p-lg-4 bg-light bg-opacity-50" style="overflow-y: auto;">

                {{-- Financial Breakdown 4-Cards Row --}}
                <div class="row g-2 mb-3">
                    <div class="col-6 col-md-3">
                        <div class="card border border-primary-subtle bg-white shadow-2xs rounded-3 p-2.5 text-center h-100">
                            <small class="text-muted text-uppercase fw-bold d-block" style="font-size:0.65rem;">Gross Base</small>
                            <span class="fs-6 fw-bold font-monospace text-primary" id="tasCardGross">ETB 0.00</span>
                            <small class="text-muted" style="font-size:0.68rem;">Taxable Base</small>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="card border border-info-subtle bg-white shadow-2xs rounded-3 p-2.5 text-center h-100">
                            <small class="text-muted text-uppercase fw-bold d-block" style="font-size:0.65rem;">VAT (0%)</small>
                            <span class="fs-6 fw-bold font-monospace text-info" id="tasCardVat">ETB 0.00</span>
                            <small class="text-muted" style="font-size:0.68rem;">TAS / Exempt</small>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="card border border-danger-subtle bg-white shadow-2xs rounded-3 p-2.5 text-center h-100" id="tasCardWhtBox">
                            <small class="text-muted text-uppercase fw-bold d-block" style="font-size:0.65rem;" id="tasCardWhtLabel">WHT (3%)</small>
                            <span class="fs-6 fw-bold font-monospace text-danger" id="tasCardWht">- ETB 0.00</span>
                            <small class="text-muted" style="font-size:0.68rem;" id="tasCardWhtRate">Withholding Tax</small>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="card border border-success-subtle bg-white shadow-2xs rounded-3 p-2.5 text-center h-100">
                            <small class="text-muted text-uppercase fw-bold d-block" style="font-size:0.65rem;">Net Disbursed</small>
                            <span class="fs-6 fw-bold font-monospace text-success" id="tasCardNet">ETB 0.00</span>
                            <small class="text-muted" style="font-size:0.68rem;">Paid to Vendor</small>
                        </div>
                    </div>
                </div>

                {{-- WHT Deduction Detail Banner --}}
                <div class="card border rounded-3 mb-3 overflow-hidden shadow-2xs" id="tasWhtInfoCard">
                    <div class="card-header py-2 px-3 bg-danger bg-opacity-10 border-bottom border-danger-subtle d-flex align-items-center justify-content-between flex-wrap gap-2">
                        <div class="d-flex align-items-center gap-2">
                            <i class="fa-solid fa-hand-holding-dollar text-danger"></i>
                            <span class="fw-bold small text-dark" id="tasWhtBannerTitle">Withholding Tax (WHT) Compliance &amp; Certificate</span>
                        </div>
                        <span class="badge rounded-pill fw-semibold font-monospace" style="font-size:0.7rem;" id="tasWhtSettledBadge">
                            Settled
                        </span>
                    </div>
                    <div class="card-body p-3 bg-white">
                        <div class="row g-3">
                            <div class="col-sm-6 col-md-3">
                                <small class="text-muted d-block" style="font-size:0.7rem;">WHT Applied Rate</small>
                                <span class="fw-bold font-monospace fs-6 text-danger" id="tasWhtRateDisplay">3.0%</span>
                                <small class="text-muted d-block" style="font-size:0.68rem;" id="tasWhtRateSub">Services standard</small>
                            </div>
                            <div class="col-sm-6 col-md-3">
                                <small class="text-muted d-block" style="font-size:0.7rem;">WHT Amount Deducted</small>
                                <span class="fw-bold font-monospace fs-6 text-danger" id="tasWhtAmountDisplay">ETB 0.00</span>
                                <small class="text-muted d-block" style="font-size:0.68rem;">Retained for ERCA</small>
                            </div>
                            <div class="col-sm-6 col-md-3">
                                <small class="text-muted d-block" style="font-size:0.7rem;">WHT Certificate / Receipt #</small>
                                <span class="fw-bold font-monospace text-dark" id="tasWhtReceiptNoDisplay">-</span>
                                <small class="text-muted d-block" style="font-size:0.68rem;">Official slip number</small>
                            </div>
                            <div class="col-sm-6 col-md-3">
                                <small class="text-muted d-block" style="font-size:0.7rem;">WHT Certificate Document</small>
                                <div id="tasWhtDocBtnWrapper" class="mt-0.5">
                                    <span class="badge bg-light text-muted border" id="tasWhtNoDocBadge" style="font-size:0.72rem;">No Slip Attached</span>
                                    <a href="#" target="_blank" class="btn btn-xs btn-outline-danger rounded-pill px-2.5 py-1 d-none align-items-center gap-1 shadow-2xs" id="tasWhtDocLink">
                                        <i class="fa-solid fa-file-pdf"></i>
                                        <span>View WHT Slip</span>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Two-Column Grid: Voucher Details vs ERCA e-Tax Declaration --}}
                <div class="row g-3 mb-3">
                    {{-- Column 1: Voucher & Operational Details --}}
                    <div class="col-md-6">
                        <div class="card border rounded-3 h-100 shadow-2xs">
                            <div class="card-header py-2 px-3 bg-light border-bottom d-flex align-items-center justify-content-between">
                                <div class="d-flex align-items-center gap-2">
                                    <i class="fa-solid fa-file-lines text-primary"></i>
                                    <span class="fw-bold text-dark small text-uppercase">Voucher Details</span>
                                </div>
                                <span class="badge bg-secondary-subtle text-secondary rounded-pill" style="font-size:0.68rem;" id="tasModalSourceLabel">Expense Voucher</span>
                            </div>
                            <div class="card-body p-3">
                                <div class="d-flex flex-column gap-2">
                                    <div class="d-flex justify-content-between border-bottom pb-1.5">
                                        <span class="text-muted small">Voucher / Request #:</span>
                                        <span class="font-monospace fw-bold text-dark small" id="tasDetRequestNo">-</span>
                                    </div>
                                    <div class="d-flex justify-content-between border-bottom pb-1.5">
                                        <span class="text-muted small">Payee / Beneficiary:</span>
                                        <span class="fw-semibold text-dark small text-end" id="tasDetPayee">-</span>
                                    </div>
                                    <div class="d-flex justify-content-between border-bottom pb-1.5">
                                        <span class="text-muted small">Project:</span>
                                        <span class="fw-semibold text-dark small" id="tasDetProject">-</span>
                                    </div>
                                    <div class="d-flex justify-content-between border-bottom pb-1.5">
                                        <span class="text-muted small">Category:</span>
                                        <span class="badge bg-light text-dark border small" id="tasDetCategory">-</span>
                                    </div>
                                    <div class="d-flex justify-content-between border-bottom pb-1.5">
                                        <span class="text-muted small">Expense Account / Method:</span>
                                        <span class="fw-semibold text-secondary small" id="tasDetAccount">-</span>
                                    </div>
                                    <div class="d-flex justify-content-between border-bottom pb-1.5">
                                        <span class="text-muted small">Voucher Date (EC):</span>
                                        <span class="font-monospace text-dark small" id="tasDetDateEc">-</span>
                                    </div>
                                    <div class="d-flex justify-content-between border-bottom pb-1.5">
                                        <span class="text-muted small">Voucher Date (GC):</span>
                                        <span class="font-monospace text-muted small" id="tasDetDateGc">-</span>
                                    </div>
                                    <div class="d-flex justify-content-between">
                                        <span class="text-muted small">Payment Reference:</span>
                                        <span class="font-monospace text-muted small" id="tasDetPaymentRef">-</span>
                                    </div>
                                </div>

                                {{-- Purpose & Description Callout --}}
                                <div class="mt-3 p-2.5 bg-light rounded-3 border">
                                    <div class="d-flex align-items-center gap-1.5 text-muted mb-1">
                                        <i class="fa-solid fa-quote-left fs-6 text-primary"></i>
                                        <span class="fw-bold text-uppercase" style="font-size:0.65rem;">Purpose / Line Description</span>
                                    </div>
                                    <p class="small text-dark mb-0 fst-italic" id="tasDetDescription" style="white-space: pre-wrap;">-</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Column 2: ERCA 9-Columns e-Tax Declaration & Fiscal Metadata --}}
                    <div class="col-md-6">
                        <div class="card border rounded-3 h-100 shadow-2xs">
                            <div class="card-header py-2 px-3 bg-light border-bottom d-flex align-items-center justify-content-between">
                                <div class="d-flex align-items-center gap-2">
                                    <i class="fa-solid fa-building-columns text-success"></i>
                                    <span class="fw-bold text-dark small text-uppercase">ERCA e-Tax Declaration</span>
                                </div>
                                <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill font-monospace" style="font-size:0.68rem;">0% VAT e-Tax</span>
                            </div>
                            <div class="card-body p-3">
                                <div class="d-flex flex-column gap-2">
                                    <div class="d-flex justify-content-between border-bottom pb-1.5 align-items-center">
                                        <span class="text-muted small">Supplier TIN:</span>
                                        <div class="d-flex align-items-center gap-1.5">
                                            <span class="font-monospace fw-bold small" id="tasDetSupplierTin">-</span>
                                            <span class="badge rounded-pill" style="font-size:0.65rem;" id="tasDetTinStatusBadge">Valid</span>
                                        </div>
                                    </div>
                                    <div class="d-flex justify-content-between border-bottom pb-1.5">
                                        <span class="text-muted small">Fiscal Cash Reg. (FS #):</span>
                                        <span class="font-monospace fw-semibold text-primary small" id="tasDetFsNo">-</span>
                                    </div>
                                    <div class="d-flex justify-content-between border-bottom pb-1.5">
                                        <span class="text-muted small">Machine Reg. Code (MRC):</span>
                                        <span class="font-monospace text-dark small" id="tasDetMrcNo">-</span>
                                    </div>
                                    <div class="d-flex justify-content-between border-bottom pb-1.5">
                                        <span class="text-muted small">Purchaser TIN:</span>
                                        <span class="font-monospace text-dark small" id="tasDetPurchaserTin">-</span>
                                    </div>
                                    <div class="d-flex justify-content-between border-bottom pb-1.5">
                                        <span class="text-muted small">Purchase Type (ERCA Code):</span>
                                        <span class="badge bg-primary-subtle text-primary border border-primary-subtle font-monospace small" id="tasDetPurcType">G</span>
                                    </div>
                                    <div class="d-flex justify-content-between border-bottom pb-1.5">
                                        <span class="text-muted small">Receipt Date (EC):</span>
                                        <span class="font-monospace text-dark small" id="tasDetReceiptDateEc">-</span>
                                    </div>
                                    <div class="d-flex justify-content-between border-bottom pb-1.5">
                                        <span class="text-muted small">Encoding Date (EC):</span>
                                        <span class="font-monospace text-dark small" id="tasDetEncodingDateEc">-</span>
                                    </div>
                                    <div class="d-flex justify-content-between">
                                        <span class="text-muted small">Encoded By User:</span>
                                        <span class="fw-semibold text-dark small" id="tasDetEncodedBy">-</span>
                                    </div>
                                </div>

                                {{-- Attached Receipt Slip Quick Box --}}
                                <div class="mt-3 p-2.5 bg-light rounded-3 border">
                                    <div class="d-flex align-items-center justify-content-between">
                                        <div class="d-flex align-items-center gap-1.5 text-muted">
                                            <i class="fa-solid fa-receipt text-primary"></i>
                                            <span class="fw-bold text-uppercase" style="font-size:0.65rem;">Receipt Slip Attached</span>
                                        </div>
                                        <div id="tasReceiptSlipActionBox">
                                            <a href="#" target="_blank" class="btn btn-xs btn-outline-primary rounded-pill px-2.5 py-0.5 d-none align-items-center gap-1" id="tasModalReceiptSlipBtn">
                                                <i class="fa-solid fa-up-right-from-square"></i>
                                                <span>View Full Slip</span>
                                            </a>
                                            <span class="badge bg-light text-muted border" id="tasModalNoSlipBadge" style="font-size:0.7rem;">No Slip File</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Interactive Slip Preview Container --}}
                <div class="card border rounded-3 overflow-hidden shadow-2xs d-none" id="tasSlipPreviewCard">
                    <div class="card-header py-2 px-3 bg-light border-bottom d-flex align-items-center justify-content-between">
                        <div class="d-flex align-items-center gap-2">
                            <i class="fa-solid fa-eye text-primary"></i>
                            <span class="fw-bold text-dark small text-uppercase">Receipt Slip Document Preview</span>
                        </div>
                        <a href="#" target="_blank" id="tasSlipPreviewOpenLink" class="btn btn-xs btn-outline-secondary rounded-pill px-2 py-0.5">
                            <i class="fa-solid fa-up-right-from-square me-1"></i>Open in New Tab
                        </a>
                    </div>
                    <div class="card-body p-2 text-center bg-dark bg-opacity-10 d-flex align-items-center justify-content-center" style="min-height: 220px; max-height: 380px; overflow: auto;">
                        <img id="tasSlipPreviewImg" src="" alt="Receipt Slip Preview" class="img-fluid rounded shadow-sm d-none" style="max-height: 360px; object-fit: contain;">
                        <iframe id="tasSlipPreviewPdf" src="" class="w-100 rounded d-none" style="height: 360px; border: none;"></iframe>
                    </div>
                </div>

            </div>

            {{-- Footer --}}
            <div class="modal-footer bg-white border-top py-2.5 px-4 d-flex justify-content-between align-items-center flex-shrink-0">
                <div class="d-flex align-items-center gap-2 text-muted small">
                    <i class="fa-solid fa-shield-halved text-primary"></i>
                    <span class="d-none d-sm-inline">Complies with ERCA 0% VAT TAS &amp; Withholding Tax (WHT) Requirements</span>
                </div>
                <div class="d-flex gap-2 align-items-center">
                    <button type="button" class="btn btn-outline-primary rounded-pill px-3 py-1.5 shadow-2xs" id="tasDetailEditInInspectorBtn" onclick="openPageTasInspectorFromDetail()">
                        <i class="fa-solid fa-pencil me-1.5"></i>Edit in ERCA Inspector
                    </button>
                    <button type="button" class="btn btn-secondary rounded-pill px-3.5 py-1.5" data-bs-dismiss="modal">
                        Close
                    </button>
                </div>
            </div>

        </div>
    </div>
</div>

{{-- ── Verified Without Receipt (Operational Expenses 0% VAT) Archive Modal ── --}}
<div class="modal fade" id="verifiedNoReceiptArchiveModal" tabindex="-1" aria-labelledby="verifiedNoReceiptArchiveModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl modal-dialog-scrollable my-lg-3" style="max-height: 94vh;">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden d-flex flex-column" style="max-height: 94vh;">

            {{-- Header --}}
            <div class="modal-header bg-dark text-white py-2.5 px-4 flex-shrink-0" style="background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%) !important;">
                <div class="d-flex align-items-center gap-2.5">
                    <div class="p-2 bg-info bg-opacity-20 text-info rounded-3 border border-info border-opacity-25 d-flex align-items-center justify-content-center shadow-xs">
                        <i class="fa-solid fa-box-archive fs-5"></i>
                    </div>
                    <div>
                        <div class="d-flex align-items-center gap-2 flex-wrap">
                            <h5 class="modal-title fs-6 fw-bold text-white mb-0" id="verifiedNoReceiptArchiveModalLabel">
                                Verified Without Receipt — Operational Expenses Archive
                            </h5>
                            <span class="badge bg-info-subtle text-info border border-info-subtle rounded-pill font-monospace" style="font-size:0.75rem;" id="archiveModalCountBadge">
                                {{ count($movementsData['verified_no_receipt'] ?? []) }} Vouchers
                            </span>
                        </div>
                        <div class="text-white-50 small mt-0.5" style="font-size:0.75rem;">
                            Approved operational expenditures without fiscal receipts (0% VAT / ERCA Exempt) &bull; Removed from pending review
                        </div>
                    </div>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            {{-- Body --}}
            <div class="modal-body p-3 bg-light bg-opacity-50" style="overflow-y: auto;">
                <div class="alert alert-info py-2 px-3 small rounded-3 mb-3 d-flex align-items-center justify-content-between">
                    <div class="d-flex align-items-center gap-2">
                        <i class="fa-solid fa-circle-info text-info fs-6"></i>
                        <span>These expenses have been verified as operational expenditures (local cart/transport, labor, informal vendors) with 0% VAT. They are excluded from the pending vouchers table.</span>
                    </div>
                </div>

                <div class="table-responsive bg-white rounded-3 border shadow-2xs">
                    <table class="table table-hover table-sm align-middle mb-0" id="archiveVerifiedTable">
                        <thead class="table-light">
                            <tr class="text-muted text-uppercase" style="font-size:0.7rem;">
                                <th class="ps-3 py-2" style="width: 40px;">#</th>
                                <th class="py-2">Voucher / Request #</th>
                                <th class="py-2">Date (EC / GC)</th>
                                <th class="py-2">Payee / Merchant</th>
                                <th class="py-2">Project / Category</th>
                                <th class="py-2 text-end">Base Amount</th>
                                <th class="py-2 text-center">Status</th>
                                <th class="py-2 text-center pe-3">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y" id="archiveVerifiedTableBody">
                            @forelse($movementsData['verified_no_receipt'] ?? [] as $aIdx => $aExp)
                                <tr id="archive-row-{{ $aExp['id'] ?? $aIdx }}" data-id="{{ $aExp['id'] ?? '' }}">
                                    <td class="ps-3 text-muted font-monospace small">{{ $aIdx + 1 }}</td>
                                    <td>
                                        <span class="badge bg-light text-dark border font-monospace">{{ $aExp['request_number'] ?? '-' }}</span>
                                        @if(!empty($aExp['payment_reference']) && $aExp['payment_reference'] !== '-')
                                            <small class="text-muted d-block font-monospace" style="font-size:0.7rem;">Ref: {{ $aExp['payment_reference'] }}</small>
                                        @endif
                                    </td>
                                    <td>
                                        <span class="fw-bold font-monospace text-dark d-block">{{ $aExp['date_ec'] ?? '-' }}</span>
                                        <small class="text-muted" style="font-size:0.72rem;">{{ $aExp['date_gc'] ?? '-' }}</small>
                                    </td>
                                    <td>
                                        <span class="fw-semibold text-dark">{{ $aExp['payee'] ?? 'Vendor' }}</span>
                                    </td>
                                    <td>
                                        <span class="badge bg-secondary-subtle text-secondary rounded-pill small">{{ $aExp['category'] ?? 'Expense' }}</span>
                                        <small class="text-muted d-block" style="font-size:0.72rem;">{{ $aExp['project_name'] ?? 'General' }}</small>
                                    </td>
                                    <td class="text-end font-monospace fw-bold text-dark">
                                        ETB {{ number_format((float)($aExp['base_amount'] ?? 0), 2) }}
                                        <small class="text-info d-block" style="font-size:0.68rem;">0% VAT Exempt</small>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge bg-info-subtle text-info border border-info-subtle rounded-pill font-monospace" style="font-size:0.68rem;">
                                            <i class="fa-solid fa-shield-check me-0.5"></i>Verified (No Slip)
                                        </span>
                                    </td>
                                    <td class="text-center pe-3">
                                        <button type="button" 
                                                class="btn btn-xs btn-outline-danger rounded-pill px-2.5 py-1 d-inline-flex align-items-center gap-1 shadow-2xs" 
                                                onclick="restoreFromVerifiedArchive('{{ $aExp['id'] ?? '' }}', '{{ $aExp['request_number'] ?? '' }}', {{ $aIdx }})"
                                                id="btn-restore-archive-{{ $aExp['id'] ?? $aIdx }}"
                                                title="Undo verification and restore back to Purchases & Paid Expenses">
                                            <i class="fa-solid fa-rotate-left"></i>
                                            <span>Undo / Move Back</span>
                                        </button>
                                    </td>
                                </tr>
                            @empty
                                <tr id="archiveEmptyRow">
                                    <td colspan="8" class="text-center py-5 text-muted">
                                        <i class="fa-solid fa-box-open fs-2 text-secondary d-block mb-2"></i>
                                        <span class="fw-semibold">No operational expenses verified without receipt for this month.</span>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- Footer --}}
            <div class="modal-footer bg-white border-top py-2.5 px-4 d-flex justify-content-between align-items-center flex-shrink-0">
                <div class="d-flex align-items-center gap-2 text-muted small">
                    <i class="fa-solid fa-shield-check text-info"></i>
                    <span>Exempt from ERCA Line 100 Input VAT Claims</span>
                </div>
                <button type="button" class="btn btn-secondary rounded-pill px-4 py-1.5" data-bs-dismiss="modal">
                    Close
                </button>
            </div>

        </div>
    </div>
</div>

{{-- ── Client Scripts & Filtering ────────────────────────────────────────────── --}}
@push('scripts')
<script>
    // Cached items for live inspector and table sync
    window._pageOcrItems = @json($movementsData['all_ocr_items'] ?? ($movementsData['ocr_items'] ?? []));
    window._pageTasPurchases = @json($movementsData['tas_purchases'] ?? []);
    window._pageExpenses = @json($movementsData['expenses'] ?? []);
    window._pageVerifiedNoReceipt = @json($movementsData['verified_no_receipt'] ?? []);

    // Toggle TAS / e-Tax Purchases by filter: 'all', 'vat', 'tas', 'with_wht', 'with_tin', 'missing_tin'
    let _activeTasTinFilter = 'all';

    function filterTasByTin(filter) {
        _activeTasTinFilter = filter;

        const btnAll = document.getElementById('btnTasFilterAll');
        const btnVat = document.getElementById('btnTasFilterVat');
        const btnTas = document.getElementById('btnTasFilterTas');
        const btnWith = document.getElementById('btnTasFilterWithTin');
        const btnMissing = document.getElementById('btnTasFilterMissingTin');
        const btnWht = document.getElementById('btnTasFilterWithWht');

        if (btnAll) btnAll.className = 'btn btn-sm rounded-pill px-2.5 py-1 fw-semibold ' + (filter === 'all' ? 'btn-primary text-white shadow-xs' : 'text-muted');
        if (btnVat) btnVat.className = 'btn btn-sm rounded-pill px-2.5 py-1 fw-semibold ' + (filter === 'vat' ? 'btn-success text-white shadow-xs' : 'text-muted');
        if (btnTas) btnTas.className = 'btn btn-sm rounded-pill px-2.5 py-1 fw-semibold ' + (filter === 'tas' ? 'btn-primary text-white shadow-xs' : 'text-muted');
        if (btnWith) btnWith.className = 'btn btn-sm rounded-pill px-2.5 py-1 fw-semibold ' + (filter === 'with_tin' ? 'btn-success text-white shadow-xs' : 'text-muted');
        if (btnMissing) btnMissing.className = 'btn btn-sm rounded-pill px-2.5 py-1 fw-semibold ' + (filter === 'missing_tin' ? 'btn-danger text-white shadow-xs' : 'text-muted');
        if (btnWht) btnWht.className = 'btn btn-sm rounded-pill px-2.5 py-1 fw-semibold ' + (filter === 'with_wht' ? 'btn-danger text-white shadow-xs' : 'text-muted');

        const rows = document.querySelectorAll('#pageTasTable tbody tr.tas-row');
        let visibleCount = 0;
        let totalVisibleGross = 0;
        let totalVisibleBase = 0;
        let totalVisibleVat = 0;
        let totalVisibleWht = 0;
        let totalVisibleNet = 0;

        rows.forEach(tr => {
            const hasTin = tr.getAttribute('data-has-tin') === '1';
            const hasVat = tr.getAttribute('data-has-vat') === '1';
            const hasWht = tr.getAttribute('data-has-wht') === '1';
            let matches = false;
            if (filter === 'all') {
                matches = true;
            } else if (filter === 'vat') {
                matches = hasVat;
            } else if (filter === 'tas') {
                matches = !hasVat;
            } else if (filter === 'with_tin') {
                matches = hasTin;
            } else if (filter === 'missing_tin') {
                matches = !hasTin;
            } else if (filter === 'with_wht') {
                matches = hasWht;
            }

            if (matches) {
                tr.style.display = '';
                visibleCount++;
                const grossVal = parseFloat(tr.getAttribute('data-gross') || tr.getAttribute('data-base') || 0) || 0;
                const baseVal  = parseFloat(tr.getAttribute('data-base') || 0) || 0;
                const vatVal   = parseFloat(tr.getAttribute('data-vat') || 0) || 0;
                const whtVal   = parseFloat(tr.getAttribute('data-wht') || 0) || 0;
                const netVal   = parseFloat(tr.getAttribute('data-net') || (grossVal - whtVal)) || 0;
                totalVisibleGross += grossVal;
                totalVisibleBase  += baseVal;
                totalVisibleVat   += vatVal;
                totalVisibleWht   += whtVal;
                totalVisibleNet   += netVal;
            } else {
                tr.style.display = 'none';
            }
        });

        const emptyFilterRow = document.getElementById('pageTasEmptyFilterRow');
        if (emptyFilterRow) {
            if (visibleCount === 0 && rows.length > 0) {
                emptyFilterRow.classList.remove('d-none');
            } else {
                emptyFilterRow.classList.add('d-none');
            }
        }

        const footCount = document.getElementById('pageTasFootCount');
        if (footCount) footCount.textContent = visibleCount;
        const footAmt = document.getElementById('pageTasFootAmount');
        if (footAmt) {
            let footHtml = `<div>ETB ${totalVisibleGross.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</div>`;
            if (totalVisibleVat > 0) {
                footHtml += `<small class="text-success d-block font-monospace" style="font-size:0.68rem;" id="pageTasFootVat">VAT (15%): + ETB ${totalVisibleVat.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</small>`;
            }
            if (totalVisibleWht > 0) {
                footHtml += `<small class="text-danger d-block font-monospace" style="font-size:0.68rem;" id="pageTasFootWht">WHT: - ETB ${totalVisibleWht.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</small>` +
                    `<small class="text-success d-block font-monospace" style="font-size:0.68rem;" id="pageTasFootNet">Net: ETB ${totalVisibleNet.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</small>`;
            }
            footAmt.innerHTML = footHtml;
        }
    }

    // Live table filtering
    function filterLocalTable(tableId, query) {
        const table = document.getElementById(tableId);
        if (!table) return;
        const tbody = table.querySelector('tbody');
        if (!tbody) return;
        const trs = tbody.querySelectorAll('tr');
        const q = (query || '').toLowerCase().trim();

        trs.forEach(tr => {
            if (tableId === 'pageExpenseTable') {
                if (tr.classList.contains('expense-quick-fill-row')) return;
                const rowType = tr.getAttribute('data-purchase-category');
                const hasRec = tr.getAttribute('data-has-receipt') === '1';
                const isVerifiedNoRec = tr.getAttribute('data-verified-no-receipt') === '1';

                let rowMatchesCategory = false;
                if (_activeExpenseFilter === 'all') rowMatchesCategory = true;
                else if (_activeExpenseFilter === 'vat') rowMatchesCategory = (rowType === 'vat');
                else if (_activeExpenseFilter === 'tas') rowMatchesCategory = (rowType === 'tas');
                else if (_activeExpenseFilter === 'missing_receipt') rowMatchesCategory = (!hasRec && !isVerifiedNoRec);
                else if (_activeExpenseFilter === 'verified_no_receipt') rowMatchesCategory = isVerifiedNoRec;

                if (!rowMatchesCategory) {
                    tr.style.display = 'none';
                    return;
                }
            }
            if (tableId === 'pageTasTable') {
                if (tr.id === 'pageTasEmptyFilterRow') return;
                const hasTin = tr.getAttribute('data-has-tin') === '1';
                const hasVat = tr.getAttribute('data-has-vat') === '1';
                const hasWht = tr.getAttribute('data-has-wht') === '1';
                if (_activeTasTinFilter === 'vat' && !hasVat) {
                    tr.style.display = 'none';
                    return;
                }
                if (_activeTasTinFilter === 'tas' && hasVat) {
                    tr.style.display = 'none';
                    return;
                }
                if (_activeTasTinFilter === 'with_tin' && !hasTin) {
                    tr.style.display = 'none';
                    return;
                }
                if (_activeTasTinFilter === 'missing_tin' && hasTin) {
                    tr.style.display = 'none';
                    return;
                }
                if (_activeTasTinFilter === 'with_wht' && !hasWht) {
                    tr.style.display = 'none';
                    return;
                }
            }
            const text = tr.innerText.toLowerCase();
            tr.style.display = text.includes(q) ? '' : 'none';
        });
    }

    // Toggle between All Purchases, VAT Purchases (15% VAT), and TAS Purchases (0% VAT)
    let _activeExpenseFilter = 'all';

    function filterExpensePurchaseType(type) {
        _activeExpenseFilter = type;
        const rows = document.querySelectorAll('#pageExpenseTable tbody tr.expense-row');
        let totalBase = 0, totalVat = 0, totalWht = 0, totalNet = 0, visibleCount = 0;

        rows.forEach(tr => {
            const rowType = tr.getAttribute('data-purchase-category');
            const hasRec = tr.getAttribute('data-has-receipt') === '1';
            const isVerifiedNoRec = tr.getAttribute('data-verified-no-receipt') === '1';

            let matchesType = false;
            if (type === 'all') {
                matchesType = true;
            } else if (type === 'vat') {
                matchesType = (rowType === 'vat');
            } else if (type === 'tas') {
                matchesType = (rowType === 'tas');
            } else if (type === 'missing_receipt') {
                matchesType = (!hasRec && !isVerifiedNoRec);
            } else if (type === 'verified_no_receipt') {
                matchesType = isVerifiedNoRec;
            }

            if (matchesType) {
                tr.style.display = '';
                visibleCount++;
                totalBase += parseFloat(tr.getAttribute('data-base') || 0);
                totalVat  += parseFloat(tr.getAttribute('data-vat') || 0);
                totalWht  += parseFloat(tr.getAttribute('data-wht') || 0);
                totalNet  += parseFloat(tr.getAttribute('data-net') || 0);
            } else {
                tr.style.display = 'none';
            }
        });

        // Update active filter button styles
        ['all', 'vat', 'tas', 'missing_receipt', 'verified_no_receipt'].forEach(t => {
            const btn = document.getElementById('btnExpenseFilter-' + t);
            if (!btn) return;
            if (t === type) {
                btn.classList.remove('text-muted');
                if (t === 'vat') {
                    btn.classList.add('btn-success', 'text-white', 'shadow-xs');
                } else if (t === 'tas') {
                    btn.classList.add('btn-primary', 'text-white', 'shadow-xs');
                } else if (t === 'missing_receipt') {
                    btn.classList.add('btn-danger', 'text-white', 'shadow-xs');
                } else if (t === 'verified_no_receipt') {
                    btn.classList.add('btn-info', 'text-white', 'shadow-xs');
                } else {
                    btn.classList.add('btn-danger', 'text-white', 'shadow-xs');
                }
            } else {
                btn.classList.remove('btn-danger', 'btn-success', 'btn-primary', 'btn-info', 'text-white', 'shadow-xs');
                btn.classList.add('text-muted');
            }
        });

        // Update footers
        const footLabel = document.getElementById('pageExpenseFootLabel');
        const footBase  = document.getElementById('pageExpenseFootBase');
        const footVat   = document.getElementById('pageExpenseFootVat');
        const footWht   = document.getElementById('pageExpenseFootWht');
        const footNet   = document.getElementById('pageExpenseFootNet');

        if (footLabel) {
            if (type === 'vat') {
                footLabel.textContent = `Total VAT Purchases (${visibleCount} items)`;
            } else if (type === 'tas') {
                footLabel.textContent = `Total TAS Purchases (${visibleCount} items)`;
            } else if (type === 'missing_receipt') {
                footLabel.textContent = `Total Missing Receipt Purchases (${visibleCount} items)`;
            } else if (type === 'verified_no_receipt') {
                footLabel.textContent = `Total Verified Without Receipt Purchases (${visibleCount} items)`;
            } else {
                footLabel.textContent = `Total Purchases & Deductions (${visibleCount} items)`;
            }
        }
        if (footBase) footBase.textContent = 'ETB ' + totalBase.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
        if (footVat)  footVat.textContent  = 'ETB ' + totalVat.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
        if (footWht)  footWht.textContent  = (totalWht > 0 ? '- ETB ' : 'ETB ') + totalWht.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
        if (footNet)  footNet.textContent  = 'ETB ' + totalNet.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    }

    // Toggle between Only VAT Receipts (15%), TAS Receipts (0% VAT), and All Scanned OCR Invoices
    let _activePageOcrFilter = '{{ ($vatOnly ?? true) ? "vat" : "all" }}';

    function filterPageOcrCategory(type) {
        _activePageOcrFilter = type;
        try {
            sessionStorage.setItem('movements_ocr_category', type);
            const url = new URL(window.location.href);
            url.searchParams.set('ocr_filter', type);
            window.history.replaceState({ ocr_filter: type }, '', url.toString());
        } catch (e) {}
        const rows = document.querySelectorAll('#pageOcrTable tbody tr');
        let totalBase = 0, totalVat = 0, totalAfter = 0, visibleCount = 0;

        rows.forEach(tr => {
            const isVat = tr.getAttribute('data-is-vat') === '1';
            let matches = true;
            if (type === 'vat') matches = isVat;
            else if (type === 'tas') matches = !isVat;
            else matches = true;

            if (matches) {
                tr.style.display = '';
                visibleCount++;
                const baseSpan = tr.querySelector('.cell-base');
                const vatSpan = tr.querySelector('.cell-vat');
                const totalSpan = tr.querySelector('.cell-total');
                if (baseSpan) {
                    const bVal = parseFloat(baseSpan.textContent.replace(/[^0-9.-]/g, ''));
                    if (!isNaN(bVal)) totalBase += bVal;
                }
                if (vatSpan) {
                    const vVal = parseFloat(vatSpan.textContent.replace(/[^0-9.-]/g, ''));
                    if (!isNaN(vVal)) totalVat += vVal;
                }
                if (totalSpan) {
                    const aVal = parseFloat(totalSpan.textContent.replace(/[^0-9.-]/g, ''));
                    if (!isNaN(aVal)) totalAfter += aVal;
                }
            } else {
                tr.style.display = 'none';
            }
        });

        // Update active filter button styles
        ['vat', 'tas', 'all'].forEach(t => {
            const btn = document.getElementById(t === 'vat' ? 'btnPageOcrVat' : (t === 'tas' ? 'btnPageOcrTas' : 'btnPageOcrAll'));
            if (!btn) return;
            if (t === type) {
                btn.className = 'btn btn-sm rounded-pill px-2.5 py-1 fw-semibold ' + (t === 'vat' ? 'btn-success text-white shadow-xs' : (t === 'tas' ? 'btn-primary text-white shadow-xs' : 'btn-secondary text-white shadow-xs'));
            } else {
                btn.className = 'btn btn-sm rounded-pill px-2.5 py-1 fw-semibold text-muted';
            }
        });

        const sumBaseEl = document.getElementById('pageOcrSumBase');
        const sumVatEl = document.getElementById('pageOcrSumVat');
        const sumAfterEl = document.getElementById('pageOcrSumAfter');
        if (sumBaseEl) sumBaseEl.textContent = 'ETB ' + totalBase.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        if (sumVatEl) sumVatEl.textContent = 'ETB ' + totalVat.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        if (sumAfterEl) sumAfterEl.textContent = 'ETB ' + totalAfter.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
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

    // Trigger file chooser for receipt slip upload
    function triggerMovementReceiptUpload() {
        const input = document.getElementById('movementSlipFileInput');
        if (input) {
            input.value = '';
            input.click();
        }
    }

    // Trigger smartphone camera photo capture for receipt slip
    function triggerMovementReceiptCamera() {
        const input = document.getElementById('movementSlipCameraInput');
        if (input) {
            input.value = '';
            input.click();
        }
    }

    // Handle receipt slip file selection, instant preview, and AI OCR extraction
    function handleMovementReceiptFileSelect(file) {
        if (!file) return;

        if (file.size > 25 * 1024 * 1024) {
            alert('The selected file is too large (' + (file.size / (1024 * 1024)).toFixed(1) + 'MB). Maximum file size is 25MB.');
            return;
        }

        const validTypes = ['image/jpeg', 'image/png', 'image/webp', 'image/jpg', 'application/pdf'];
        const ext = (file.name.split('.').pop() || '').toLowerCase();
        const validExts = ['jpg', 'jpeg', 'png', 'webp', 'pdf'];
        if (!validTypes.includes(file.type) && !validExts.includes(ext)) {
            alert('Invalid file format. Please select a JPG, PNG, WEBP image or a PDF document.');
            return;
        }

        // Show immediate client-side preview
        try {
            const blobUrl = URL.createObjectURL(file);
            _setPageSlipPreview(blobUrl, file.name);
        } catch (e) {
            console.warn('Could not generate object URL:', e);
        }

        // Show upload & scanning alert
        const progBox = document.getElementById('movementUploadProgress');
        const progText = document.getElementById('movementUploadStatusText');
        const progBadge = document.getElementById('movementUploadBadge');
        const progSpinner = document.getElementById('movementUploadSpinner');

        if (progBox) {
            progBox.className = 'alert alert-info py-2 px-3 small rounded-3 mb-2 d-flex align-items-center justify-content-between flex-shrink-0';
            if (progText) progText.innerHTML = `<strong>Uploading &amp; Scanning "${file.name}"...</strong> AI OCR is extracting fiscal metadata.`;
            if (progBadge) {
                progBadge.className = 'badge bg-primary text-white rounded-pill px-2 py-0.5 flex-shrink-0';
                progBadge.textContent = 'AI OCR Scanning';
            }
            if (progSpinner) {
                progSpinner.className = 'fa-solid fa-circle-notch fa-spin text-primary fs-6 flex-shrink-0';
            }
            progBox.classList.remove('d-none');
        }

        // Trigger OCR Scan via backend endpoint
        const formData = new FormData();
        formData.append('receipt_file', file);
        formData.append('_token', '{{ csrf_token() }}');

        fetch('{{ route("finance.tax-deductions.scan-receipt") }}', {
            method: 'POST',
            body: formData,
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(async res => {
            const isJson = (res.headers.get('content-type') || '').includes('application/json');
            const data = isJson ? await res.json() : { success: false, message: await res.text() };
            if (!res.ok) {
                throw new Error(data.message || ('Server error: HTTP ' + res.status));
            }
            return data;
        })
        .then(data => {
            if (data.success) {
                const pathInput = document.getElementById('movement_edit_receipt_file_path');
                if (pathInput && data.file_path) {
                    pathInput.value = data.file_path;
                }

                if (data.file_url) {
                    _setPageSlipPreview(data.file_url, file.name);
                }

                let autofilled = [];

                if (data.fs_no && data.fs_no !== '-') {
                    const fsInput = document.getElementById('movement_edit_fs_no');
                    if (fsInput) {
                        fsInput.value = data.fs_no;
                        validateMovementFs(data.fs_no);
                        autofilled.push('FS #' + data.fs_no);
                    }
                }

                if (data.vendor_tin && data.vendor_tin !== '-') {
                    const tinInput = document.getElementById('movement_edit_supplier_tin');
                    if (tinInput) {
                        tinInput.value = data.vendor_tin;
                        validateMovementTin(data.vendor_tin);
                        autofilled.push('TIN');
                    }
                }

                if (data.vendor_name && data.vendor_name !== 'General Merchant') {
                    const vendorInput = document.getElementById('movement_edit_seller_name');
                    if (vendorInput) {
                        vendorInput.value = data.vendor_name;
                        autofilled.push('Merchant Name');
                    }
                }

                if (data.mrc_no && data.mrc_no !== '-') {
                    const mrcInput = document.getElementById('movement_edit_mrc_no');
                    if (mrcInput) {
                        mrcInput.value = data.mrc_no;
                        autofilled.push('MRC');
                    }
                }

                if (data.receipt_date) {
                    const dateInput = document.getElementById('movement_edit_receipt_date');
                    if (dateInput) {
                        dateInput.value = data.receipt_date;
                        syncMovementDateToEc(data.receipt_date);
                        autofilled.push('Date');
                    }
                }

                const totalVal = parseFloat(data.total_amount || 0);
                const subtotalVal = parseFloat(data.subtotal || 0);
                const vatVal = parseFloat(data.vat_amount || 0);

                if (totalVal > 0 || subtotalVal > 0) {
                    const baseInput = document.getElementById('movement_edit_total_value');
                    const vatInput = document.getElementById('movement_edit_vat_amount');
                    const totalInput = document.getElementById('movement_edit_value_after_vat');

                    if (baseInput) baseInput.value = (subtotalVal > 0 ? subtotalVal : totalVal).toFixed(2);
                    if (vatInput) vatInput.value = vatVal.toFixed(2);
                    if (totalInput) totalInput.value = (totalVal > 0 ? totalVal : (subtotalVal + vatVal)).toFixed(2);
                    autofilled.push('Amounts');
                }

                if (data.purchase_type) {
                    const purcSelect = document.getElementById('movement_edit_purchase_type');
                    if (purcSelect) {
                        for (let opt of purcSelect.options) {
                            if (opt.value === data.purchase_type || opt.value.includes(data.purchase_type)) {
                                purcSelect.value = opt.value;
                                break;
                            }
                        }
                    }
                }

                if (progBox) {
                    progBox.className = 'alert alert-success py-2 px-3 small rounded-3 mb-2 d-flex align-items-center justify-content-between flex-shrink-0';
                    if (progText) {
                        const fieldsStr = autofilled.length > 0 ? ` (Auto-filled: ${autofilled.join(', ')})` : '';
                        progText.innerHTML = `<strong><i class="fa-solid fa-circle-check text-success me-1"></i>Slip Uploaded &amp; Scanned!</strong>${fieldsStr}. Click <strong>Save Changes</strong> to reconcile with system.`;
                    }
                    if (progBadge) {
                        progBadge.className = 'badge bg-success text-white rounded-pill px-2 py-0.5 flex-shrink-0';
                        progBadge.textContent = 'OCR Verified';
                    }
                    if (progSpinner) {
                        progSpinner.className = 'fa-solid fa-check text-success fs-6 flex-shrink-0';
                    }
                }
            } else {
                throw new Error(data.message || 'Could not parse slip');
            }
        })
        .catch(err => {
            console.warn('OCR Scan notice:', err);
            if (progBox) {
                progBox.className = 'alert alert-warning py-2 px-3 small rounded-3 mb-2 d-flex align-items-center justify-content-between flex-shrink-0';
                if (progText) {
                    progText.innerHTML = `<strong><i class="fa-solid fa-paperclip text-warning me-1"></i>Slip attached!</strong> Ready for upload. Click <strong>Save Changes</strong> to store slip and fiscal edits.`;
                }
                if (progBadge) {
                    progBadge.className = 'badge bg-warning text-dark rounded-pill px-2 py-0.5 flex-shrink-0';
                    progBadge.textContent = 'Attached';
                }
                if (progSpinner) {
                    progSpinner.className = 'fa-solid fa-paperclip text-warning fs-6 flex-shrink-0';
                }
            }
        });
    }

    // Set preview file in inspector modal
    function _setPageSlipPreview(url, customFilename) {
        const imgEl = document.getElementById('movementSlipImg');
        const pdfEl = document.getElementById('movementSlipPdf');
        const emptyEl = document.getElementById('movementSlipEmpty');
        const dlBtn = document.getElementById('movementSlipDownloadBtn');
        const newTabBtn = document.getElementById('movementModalSlipOpenNewTab');
        const fnLabel = document.getElementById('movementSlipFilename');

        resetPageSlip();

        if (!url) {
            if (imgEl) { imgEl.classList.add('d-none'); imgEl.src = ''; }
            if (pdfEl) { pdfEl.classList.add('d-none'); pdfEl.src = ''; }
            if (emptyEl) {
                emptyEl.classList.remove('d-none');
                emptyEl.classList.add('d-flex');
            }
            if (dlBtn) dlBtn.classList.add('d-none');
            if (newTabBtn) newTabBtn.classList.add('d-none');
            if (fnLabel) fnLabel.textContent = 'No document attached';
            return;
        }

        if (emptyEl) {
            emptyEl.classList.add('d-none');
            emptyEl.classList.remove('d-flex');
        }
        if (dlBtn) { dlBtn.classList.remove('d-none'); dlBtn.href = url; }
        if (newTabBtn) { newTabBtn.classList.remove('d-none'); newTabBtn.href = url; }
        if (fnLabel) {
            if (customFilename) {
                fnLabel.textContent = 'Document: ' + customFilename;
            } else {
                const parts = url.split('/');
                fnLabel.textContent = 'Document: ' + decodeURIComponent(parts[parts.length - 1] || 'receipt-slip');
            }
        }

        const isPdf = url.toLowerCase().includes('.pdf');
        if (isPdf) {
            if (imgEl) { imgEl.classList.add('d-none'); imgEl.src = ''; }
            if (pdfEl) { pdfEl.src = url; pdfEl.classList.remove('d-none'); }
        } else {
            if (pdfEl) { pdfEl.classList.add('d-none'); pdfEl.src = ''; }
            if (imgEl) { imgEl.src = url; imgEl.classList.remove('d-none'); }
        }
    }

    // Open Inspector and populate form with details
    function openPageOcrInspector(idx) {
        const ocr = (window._pageOcrItems && window._pageOcrItems[idx]) || {};
        const modalEl = document.getElementById('movementSlipModal');
        if (!modalEl) return;

        const cleanNum = (v) => {
            const n = parseFloat(String(v ?? '0').replace(/[^0-9.-]/g, ''));
            return isNaN(n) ? 0 : n;
        };

        // Form elements
        const idInput = document.getElementById('movement_edit_ocr_item_id');
        if (idInput) idInput.value = ocr.id || '';

        const rcptIdInput = document.getElementById('movement_edit_receipt_id');
        if (rcptIdInput) rcptIdInput.value = ocr.receipt_id || '';

        const expIdInput = document.getElementById('movement_edit_expense_request_id');
        if (expIdInput) expIdInput.value = ocr.expense_request_id || '';

        const reqNumInput = document.getElementById('movement_edit_request_number');
        if (reqNumInput) reqNumInput.value = ocr.request_number || '';

        const pathInput = document.getElementById('movement_edit_receipt_file_path');
        if (pathInput) pathInput.value = ocr.file_path || '';

        const fileInput = document.getElementById('movementSlipFileInput');
        if (fileInput) fileInput.value = '';

        const camInput = document.getElementById('movementSlipCameraInput');
        if (camInput) camInput.value = '';

        const progBox = document.getElementById('movementUploadProgress');
        if (progBox) progBox.className = 'alert alert-info py-2 px-3 small rounded-3 mb-2 d-none align-items-center justify-content-between flex-shrink-0';

        const badgeId = document.getElementById('movement_edit_ocr_badge_id');
        if (badgeId) badgeId.textContent = ocr.id ? ('Invoice #' + ocr.id) : ('Item #' + (idx + 1));

        const fsDisplay = document.getElementById('movementModalFsNoDisplay');
        if (fsDisplay) fsDisplay.textContent = ocr.fs_no && ocr.fs_no !== '-' ? ('FS #' + ocr.fs_no) : (ocr.id ? ('Invoice #' + ocr.id) : 'Receipt Inspector');

        const sellerName = document.getElementById('movement_edit_seller_name');
        if (sellerName) sellerName.value = ocr.vendor_name || '';

        const tinInput = document.getElementById('movement_edit_supplier_tin');
        if (tinInput) {
            tinInput.value = (ocr.vendor_tin === '-' ? '' : ocr.vendor_tin) || '';
            validateMovementTin(tinInput.value);
        }

        const fsInput = document.getElementById('movement_edit_fs_no');
        if (fsInput) {
            fsInput.value = (ocr.fs_no === '-' ? '' : ocr.fs_no) || '';
            validateMovementFs(fsInput.value);
        }

        const mrcInput = document.getElementById('movement_edit_mrc_no');
        if (mrcInput) mrcInput.value = (ocr.mrc_no === '-' ? '' : ocr.mrc_no) || '';

        const dateInput = document.getElementById('movement_edit_receipt_date');
        if (dateInput) {
            let ymd = ocr.receipt_date_ymd || '';
            if (!ymd && ocr.date_gc) {
                try {
                    const parsed = new Date(ocr.date_gc);
                    if (!isNaN(parsed.getTime())) {
                        const yr = parsed.getFullYear();
                        const mo = String(parsed.getMonth() + 1).padStart(2, '0');
                        const da = String(parsed.getDate()).padStart(2, '0');
                        ymd = `${yr}-${mo}-${da}`;
                    }
                } catch (e) {}
            }
            dateInput.value = ymd;
        }

        const dateEcPreview = document.getElementById('movement_edit_date_ec_preview');
        if (dateEcPreview) dateEcPreview.textContent = ocr.date_ec ? ('EC: ' + ocr.date_ec) : '';

        const descInput = document.getElementById('movement_edit_item_description');
        if (descInput) descInput.value = ocr.item_description || '';

        const projSelect = document.getElementById('movement_edit_project_id');
        if (projSelect) projSelect.value = ocr.project_id || '';

        const catSelect = document.getElementById('movement_edit_category');
        if (catSelect) catSelect.value = ocr.category || 'materials';

        const vatCatSelect = document.getElementById('movement_edit_vat_category');
        if (vatCatSelect) vatCatSelect.value = ocr.vat_category || 'G';

        const purcTypeSelect = document.getElementById('movement_edit_purchase_type');
        if (purcTypeSelect) purcTypeSelect.value = ocr.purchase_type || 3;

        const baseInput = document.getElementById('movement_edit_total_value');
        if (baseInput) baseInput.value = cleanNum(ocr.base_value).toFixed(2);

        const vatInput = document.getElementById('movement_edit_vat_amount');
        if (vatInput) vatInput.value = cleanNum(ocr.vat_amount).toFixed(2);

        const totalInput = document.getElementById('movement_edit_value_after_vat');
        if (totalInput) totalInput.value = cleanNum(ocr.value_after_vat).toFixed(2);

        // Populate Withholding fields for OCR item
        const hasOcrWht = !!(ocr.has_withholding || (cleanNum(ocr.withholding_amount) > 0));
        const whtCheck = document.getElementById('movement_edit_has_withholding');
        if (whtCheck) {
            whtCheck.checked = hasOcrWht;
            toggleMovementWithholding(hasOcrWht);
        }
        const whtRateSelect = document.getElementById('movement_edit_withholding_rate');
        const ocrWhtRate = cleanNum(ocr.withholding_rate) || 3;
        if (whtRateSelect) {
            if ([2, 3, 30].includes(Math.round(ocrWhtRate))) {
                whtRateSelect.value = String(Math.round(ocrWhtRate));
            } else {
                whtRateSelect.value = 'custom';
                const customRateInput = document.getElementById('movement_edit_custom_wht_rate');
                if (customRateInput) {
                    customRateInput.value = ocrWhtRate;
                    customRateInput.classList.remove('d-none');
                }
            }
        }
        const whtAmtInput = document.getElementById('movement_edit_withholding_amount');
        if (whtAmtInput) {
            whtAmtInput.value = cleanNum(ocr.withholding_amount).toFixed(2);
        }
        const whtCertInput = document.getElementById('movement_edit_withholding_receipt_number');
        if (whtCertInput) {
            whtCertInput.value = (ocr.withholding_receipt_number && ocr.withholding_receipt_number !== '-') ? ocr.withholding_receipt_number : '';
        }
        const whtSlipLink = document.getElementById('movement_edit_current_wht_slip_link');
        if (whtSlipLink) {
            if (ocr.withholding_receipt_url) {
                whtSlipLink.href = ocr.withholding_receipt_url;
                whtSlipLink.classList.remove('d-none');
            } else {
                whtSlipLink.classList.add('d-none');
            }
        }
        recalcMovementWht();

        // Clear alerts
        const alertBox = document.getElementById('movementOcrModalAlert');
        if (alertBox) {
            alertBox.className = 'alert d-none';
            alertBox.innerHTML = '';
        }

        // Set right column preview
        _setPageSlipPreview(ocr.file_url);

        // Show modal
        if (window.bootstrap && bootstrap.Modal) {
            bootstrap.Modal.getOrCreateInstance(modalEl).show();
        } else if (window.$ && typeof $(modalEl).modal === 'function') {
            $(modalEl).modal('show');
        }
    }

    // Open Inspector directly for a TAS Purchase (0% VAT / fuel receipt)
    function openPageTasInspector(idx) {
        const tas = (window._pageTasPurchases && window._pageTasPurchases[idx]) || {};
        const modalEl = document.getElementById('movementSlipModal');
        if (!modalEl) return;

        // Check if this TAS item matches an item in window._pageOcrItems
        let matchingOcrIdx = -1;
        if (window._pageOcrItems && window._pageOcrItems.length > 0) {
            matchingOcrIdx = window._pageOcrItems.findIndex(it => 
                (tas.receipt_id && (it.receipt_id == tas.receipt_id || it.id == tas.receipt_id)) ||
                (tas.fs_no && tas.fs_no !== '-' && it.fs_no === tas.fs_no) ||
                (tas.id && (it.expense_request_id == tas.id || it.receipt_id == tas.id))
            );
        }

        if (matchingOcrIdx !== -1) {
            openPageOcrInspector(matchingOcrIdx);
            // Ensure 0% VAT is preserved
            const vatCat = document.getElementById('movement_edit_vat_category');
            if (vatCat && (!vatCat.value || vatCat.value === 'G')) {
                const vatInput = document.getElementById('movement_edit_vat_amount');
                if (!vatInput || parseFloat(vatInput.value || 0) === 0) {
                    vatCat.value = 'E';
                }
            }
            return;
        }

        const cleanNum = (v) => {
            const n = parseFloat(String(v ?? '0').replace(/[^0-9.-]/g, ''));
            return isNaN(n) ? 0 : n;
        };

        const idInput = document.getElementById('movement_edit_ocr_item_id');
        if (idInput) idInput.value = tas.receipt_item_id || tas.receipt_id || tas.id || '';

        let resolvedTasRcptId = '';
        if (tas.receipt_id) {
            resolvedTasRcptId = String(tas.receipt_id).startsWith('REC-') ? tas.receipt_id : ('REC-' + tas.receipt_id);
        } else if (tas.id) {
            resolvedTasRcptId = String(tas.id).match(/^(EXP|PROC|CS|PC|REC)-/i) ? String(tas.id) : ('EXP-' + tas.id);
        } else if (tas.request_number) {
            resolvedTasRcptId = tas.request_number;
        }
        const rcptIdInput = document.getElementById('movement_edit_receipt_id');
        if (rcptIdInput) rcptIdInput.value = resolvedTasRcptId;

        const expIdInput = document.getElementById('movement_edit_expense_request_id');
        if (expIdInput) expIdInput.value = tas.expense_request_id || tas.id || '';

        const reqNumInput = document.getElementById('movement_edit_request_number');
        if (reqNumInput) reqNumInput.value = tas.request_number || '';

        const pathInput = document.getElementById('movement_edit_receipt_file_path');
        if (pathInput) pathInput.value = tas.receipt_slip_url || tas.attachment || '';

        const fileInput = document.getElementById('movementSlipFileInput');
        if (fileInput) fileInput.value = '';

        const camInput = document.getElementById('movementSlipCameraInput');
        if (camInput) camInput.value = '';

        const progBox = document.getElementById('movementUploadProgress');
        if (progBox) progBox.className = 'alert alert-info py-2 px-3 small rounded-3 mb-2 d-none align-items-center justify-content-between flex-shrink-0';

        const badgeId = document.getElementById('movement_edit_ocr_badge_id');
        if (badgeId) badgeId.textContent = tas.request_number || ('TAS #' + (idx + 1));

        const isVatItem = !!(tas.is_vat_purchase || cleanNum(tas.vat_amount) > 0);

        const fsDisplay = document.getElementById('movementModalFsNoDisplay');
        if (fsDisplay) {
            const taxLabel = isVatItem ? 'VAT 15% Purchase' : (tas.is_fuel ? 'Fuel 0% VAT' : 'TAS 0% VAT');
            fsDisplay.textContent = tas.fs_no && tas.fs_no !== '-' 
                ? ('FS #' + tas.fs_no + ' (' + taxLabel + ')') 
                : (tas.request_number ? (tas.request_number + ' (' + taxLabel + ')') : 'Purchase Inspector');
        }

        const sellerName = document.getElementById('movement_edit_seller_name');
        if (sellerName) sellerName.value = tas.payee || tas.seller_name || '';

        const tinInput = document.getElementById('movement_edit_supplier_tin');
        if (tinInput) {
            const rawTin = tas.seller_tin === '-' ? '' : (tas.seller_tin || '');
            tinInput.value = rawTin;
            validateMovementTin(rawTin);
        }

        const fsInput = document.getElementById('movement_edit_fs_no');
        if (fsInput) {
            const rawFs = tas.fs_no === '-' ? '' : (tas.fs_no || '');
            fsInput.value = rawFs;
            validateMovementFs(rawFs);
        }

        const mrcInput = document.getElementById('movement_edit_mrc_no');
        if (mrcInput) mrcInput.value = (tas.mrc_no === '-' ? '' : tas.mrc_no) || '';

        const dateInput = document.getElementById('movement_edit_receipt_date');
        if (dateInput) {
            let ymd = tas.receipt_date_ymd || '';
            if (!ymd && tas.date_gc) {
                try {
                    const parsed = new Date(tas.date_gc);
                    if (!isNaN(parsed.getTime())) {
                        const yr = parsed.getFullYear();
                        const mo = String(parsed.getMonth() + 1).padStart(2, '0');
                        const da = String(parsed.getDate()).padStart(2, '0');
                        ymd = `${yr}-${mo}-${da}`;
                    }
                } catch (e) {}
            }
            dateInput.value = ymd;
        }

        const dateEcPreview = document.getElementById('movement_edit_date_ec_preview');
        if (dateEcPreview) dateEcPreview.textContent = tas.receipt_date_ec ? ('EC: ' + tas.receipt_date_ec) : (tas.date_ec ? ('EC: ' + tas.date_ec) : '');

        const descInput = document.getElementById('movement_edit_item_description');
        if (descInput) descInput.value = tas.description && tas.description !== '-' ? tas.description : (tas.category || 'TAS Purchase');

        const projSelect = document.getElementById('movement_edit_project_id');
        if (projSelect) projSelect.value = '';

        const catSelect = document.getElementById('movement_edit_category');
        if (catSelect) catSelect.value = tas.is_fuel ? 'fuel' : 'materials';

        // VAT Category: 'G' for 15% VAT, 'E' for 0% exempt / fuel
        const vatCatSelect = document.getElementById('movement_edit_vat_category');
        if (vatCatSelect) vatCatSelect.value = isVatItem ? 'G' : 'E';

        const purcTypeSelect = document.getElementById('movement_edit_purchase_type');
        if (purcTypeSelect) purcTypeSelect.value = tas.purc_type || 'PUR_GD';

        const baseVal = cleanNum(tas.base_amount || tas.amount);
        const vatVal = cleanNum(tas.vat_amount);
        const grossVal = cleanNum(tas.total_amount || tas.gross_amount || (baseVal + vatVal));

        const baseInput = document.getElementById('movement_edit_total_value');
        if (baseInput) baseInput.value = baseVal.toFixed(2);

        const vatInput = document.getElementById('movement_edit_vat_amount');
        if (vatInput) vatInput.value = vatVal.toFixed(2);

        const totalInput = document.getElementById('movement_edit_value_after_vat');
        if (totalInput) totalInput.value = grossVal.toFixed(2);

        // Populate Withholding fields in modal for TAS item
        const hasTasWht = !!(tas.has_withholding || (cleanNum(tas.withholding_amount) > 0));
        const whtCheck = document.getElementById('movement_edit_has_withholding');
        if (whtCheck) {
            whtCheck.checked = hasTasWht;
            toggleMovementWithholding(hasTasWht);
        }
        const whtRateSelect = document.getElementById('movement_edit_withholding_rate');
        const tasWhtRate = cleanNum(tas.withholding_rate) || 3;
        if (whtRateSelect) {
            if ([2, 3, 30].includes(Math.round(tasWhtRate))) {
                whtRateSelect.value = String(Math.round(tasWhtRate));
            } else {
                whtRateSelect.value = 'custom';
                const customRateInput = document.getElementById('movement_edit_custom_wht_rate');
                if (customRateInput) {
                    customRateInput.value = tasWhtRate;
                    customRateInput.classList.remove('d-none');
                }
            }
        }
        const whtAmtInput = document.getElementById('movement_edit_withholding_amount');
        if (whtAmtInput) {
            whtAmtInput.value = cleanNum(tas.withholding_amount).toFixed(2);
        }
        const whtCertInput = document.getElementById('movement_edit_withholding_receipt_number');
        if (whtCertInput) {
            whtCertInput.value = (tas.withholding_receipt_number && tas.withholding_receipt_number !== '-') ? tas.withholding_receipt_number : '';
        }
        const whtSlipLink = document.getElementById('movement_edit_current_wht_slip_link');
        if (whtSlipLink) {
            if (tas.withholding_receipt_url) {
                whtSlipLink.href = tas.withholding_receipt_url;
                whtSlipLink.classList.remove('d-none');
            } else {
                whtSlipLink.classList.add('d-none');
            }
        }
        recalcMovementWht();

        // Clear alerts
        const alertBox = document.getElementById('movementOcrModalAlert');
        if (alertBox) {
            alertBox.className = 'alert d-none';
            alertBox.innerHTML = '';
        }

        // Set preview image / pdf
        const slipUrl = tas.receipt_slip_url || tas.attachment_url;
        _setPageSlipPreview(slipUrl);

        // Show modal
        if (window.bootstrap && bootstrap.Modal) {
            bootstrap.Modal.getOrCreateInstance(modalEl).show();
        } else if (window.$ && typeof $(modalEl).modal === 'function') {
            $(modalEl).modal('show');
        }
    }

    // Active index and source type for expense detail modal
    let _activeTasDetailIdx = null;
    let _activeExpenseDetailType = 'tas'; // 'tas' | 'expense'

    // Shared renderer for Expense Request & Withholding Tax Detail Modal
    function _renderExpenseDetailModal(tas, idx, type) {
        const modalEl = document.getElementById('tasExpenseDetailModal');
        if (!modalEl) return;

        const baseAmount = parseFloat(tas.base_amount || tas.amount || 0) || 0;
        const vatAmount  = parseFloat(tas.vat_amount || 0) || 0;
        const whtAmount  = parseFloat(tas.withholding_amount || 0) || 0;
        const hasWht     = (tas.has_withholding || whtAmount > 0);
        const whtRate    = parseFloat(tas.withholding_rate || (whtAmount > 0 ? (whtAmount / Math.max(1, baseAmount) * 100).toFixed(1) : 0)) || 0;
        const netAmount  = parseFloat(tas.net_disbursed || (baseAmount + vatAmount - whtAmount)) || 0;

        const formatMoney = (val) => 'ETB ' + (parseFloat(val) || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

        // Header subtitles & badges
        const voucherBadge = document.getElementById('tasModalVoucherBadge');
        if (voucherBadge) voucherBadge.textContent = tas.request_number || (tas.id ? ('EXP-' + tas.id) : (type === 'tas' ? ('TAS #' + (idx + 1)) : ('EXP #' + (idx + 1))));

        const payeeSub = document.getElementById('tasModalPayeeSubtitle');
        if (payeeSub) payeeSub.textContent = tas.payee || tas.seller_name || 'Vendor';

        const amtSub = document.getElementById('tasModalAmountSubtitle');
        if (amtSub) amtSub.textContent = formatMoney(baseAmount);

        const catSub = document.getElementById('tasModalCategorySubtitle');
        if (catSub) {
            if (tas.category) {
                catSub.textContent = tas.category;
            } else if (tas.is_fuel) {
                catSub.textContent = 'Fuel Expense';
            } else if (vatAmount > 0) {
                catSub.textContent = 'VAT Purchase (15%)';
            } else {
                catSub.textContent = 'TAS Purchase (0% VAT)';
            }
        }

        const statusBadge = document.getElementById('tasModalWhtStatusBadge');
        if (statusBadge) {
            if (hasWht) {
                statusBadge.className = 'badge rounded-pill fw-semibold bg-danger text-white shadow-2xs';
                statusBadge.innerHTML = `<i class="fa-solid fa-hand-holding-dollar me-1"></i>${whtRate > 0 ? whtRate + '% ' : ''}WHT Deducted`;
            } else {
                statusBadge.className = 'badge rounded-pill fw-semibold bg-secondary-subtle text-secondary border border-secondary-subtle';
                statusBadge.textContent = '0% WHT (No Deduction)';
            }
        }

        // Financial 4-Cards
        const grossEl = document.getElementById('tasCardGross');
        if (grossEl) grossEl.textContent = formatMoney(baseAmount);

        const vatEl = document.getElementById('tasCardVat');
        if (vatEl) vatEl.textContent = formatMoney(vatAmount);

        const whtLabel = document.getElementById('tasCardWhtLabel');
        if (whtLabel) whtLabel.textContent = hasWht ? `WHT (${whtRate}%)` : 'WHT (0%)';

        const whtEl = document.getElementById('tasCardWht');
        if (whtEl) {
            whtEl.textContent = hasWht ? `- ${formatMoney(whtAmount)}` : 'ETB 0.00';
            whtEl.className = 'fs-6 fw-bold font-monospace ' + (hasWht ? 'text-danger' : 'text-muted');
        }

        const whtRateSub = document.getElementById('tasCardWhtRate');
        if (whtRateSub) whtRateSub.textContent = hasWht ? `${whtRate}% Withholding` : 'Not Applicable';

        const netEl = document.getElementById('tasCardNet');
        if (netEl) netEl.textContent = formatMoney(netAmount);

        // WHT Info Card
        const whtSettledBadge = document.getElementById('tasWhtSettledBadge');
        const whtRateDisplay = document.getElementById('tasWhtRateDisplay');
        const whtRateDesc = document.getElementById('tasWhtRateSub');
        const whtAmountDisplay = document.getElementById('tasWhtAmountDisplay');
        const whtReceiptNoDisplay = document.getElementById('tasWhtReceiptNoDisplay');
        const whtNoDocBadge = document.getElementById('tasWhtNoDocBadge');
        const whtDocLink = document.getElementById('tasWhtDocLink');

        if (whtRateDisplay) whtRateDisplay.textContent = hasWht ? `${whtRate}%` : '0.0%';
        if (whtRateDesc) {
            if (!hasWht) {
                whtRateDesc.textContent = 'No withholding tax applied';
            } else if (whtRate >= 30) {
                whtRateDesc.textContent = 'Unregistered vendor rate (30%)';
            } else if (whtRate >= 3) {
                whtRateDesc.textContent = 'Services standard rate (3%)';
            } else if (whtRate >= 2) {
                whtRateDesc.textContent = 'Goods supply standard rate (2%)';
            } else {
                whtRateDesc.textContent = 'Withholding tax rate';
            }
        }
        if (whtAmountDisplay) whtAmountDisplay.textContent = formatMoney(whtAmount);
        if (whtReceiptNoDisplay) whtReceiptNoDisplay.textContent = tas.withholding_receipt_number && tas.withholding_receipt_number !== '-' ? tas.withholding_receipt_number : (hasWht ? 'Pending Certificate #' : 'None');

        if (whtSettledBadge) {
            if (!hasWht) {
                whtSettledBadge.className = 'badge rounded-pill fw-semibold bg-light text-muted border';
                whtSettledBadge.textContent = 'No WHT';
            } else if (tas.withholding_settled) {
                whtSettledBadge.className = 'badge rounded-pill fw-semibold bg-success text-white shadow-2xs';
                whtSettledBadge.innerHTML = '<i class="fa-solid fa-circle-check me-1"></i>Settled with ERCA';
            } else {
                whtSettledBadge.className = 'badge rounded-pill fw-semibold bg-warning-subtle text-warning border border-warning-subtle';
                whtSettledBadge.innerHTML = '<i class="fa-solid fa-clock me-1"></i>Pending Tax Settlement';
            }
        }

        const whtSlipUrl = tas.withholding_receipt_url || null;
        if (whtSlipUrl) {
            if (whtDocLink) {
                whtDocLink.href = whtSlipUrl;
                whtDocLink.classList.remove('d-none');
                whtDocLink.classList.add('d-inline-flex');
            }
            if (whtNoDocBadge) whtNoDocBadge.classList.add('d-none');
        } else {
            if (whtDocLink) {
                whtDocLink.classList.add('d-none');
                whtDocLink.classList.remove('d-inline-flex');
            }
            if (whtNoDocBadge) whtNoDocBadge.classList.remove('d-none');
        }

        // Column 1: Voucher Details
        const detReqNo = document.getElementById('tasDetRequestNo');
        if (detReqNo) detReqNo.textContent = tas.request_number || '-';

        const detPayee = document.getElementById('tasDetPayee');
        if (detPayee) detPayee.textContent = tas.payee || tas.seller_name || '-';

        const detProject = document.getElementById('tasDetProject');
        if (detProject) detProject.textContent = tas.project_name || 'General';

        const detCategory = document.getElementById('tasDetCategory');
        if (detCategory) detCategory.textContent = tas.category || '-';

        const detAccount = document.getElementById('tasDetAccount');
        if (detAccount) detAccount.textContent = tas.account_name || 'Main Operational Account';

        const detDateEc = document.getElementById('tasDetDateEc');
        if (detDateEc) detDateEc.textContent = tas.date_ec || tas.receipt_date_ec || '-';

        const detDateGc = document.getElementById('tasDetDateGc');
        if (detDateGc) detDateGc.textContent = tas.date_gc || tas.raw_date || '-';

        const detPaymentRef = document.getElementById('tasDetPaymentRef');
        if (detPaymentRef) detPaymentRef.textContent = tas.payment_reference || '-';

        const detDesc = document.getElementById('tasDetDescription');
        if (detDesc) detDesc.textContent = tas.description || 'No description entered.';

        // Column 2: ERCA e-Tax Declaration
        const cleanTin = (String(tas.seller_tin || '')).replace(/\D/g, '');
        const hasValidTin = cleanTin.length === 10;

        const detTin = document.getElementById('tasDetSupplierTin');
        if (detTin) {
            detTin.textContent = tas.seller_tin && tas.seller_tin !== '-' ? tas.seller_tin : 'Missing';
            detTin.className = 'font-monospace fw-bold small ' + (hasValidTin ? 'text-dark' : 'text-danger');
        }

        const detTinBadge = document.getElementById('tasDetTinStatusBadge');
        if (detTinBadge) {
            if (hasValidTin) {
                detTinBadge.className = 'badge bg-success-subtle text-success border border-success-subtle rounded-pill font-monospace';
                detTinBadge.innerHTML = '<i class="fa-solid fa-check me-0.5"></i>10 Digits';
            } else {
                detTinBadge.className = 'badge bg-danger-subtle text-danger border border-danger-subtle rounded-pill font-monospace';
                detTinBadge.innerHTML = '<i class="fa-solid fa-triangle-exclamation me-0.5"></i>Missing TIN';
            }
        }

        const detFsNo = document.getElementById('tasDetFsNo');
        if (detFsNo) detFsNo.textContent = tas.fs_no && tas.fs_no !== '-' ? ('FS #' + tas.fs_no) : '-';

        const detMrcNo = document.getElementById('tasDetMrcNo');
        if (detMrcNo) detMrcNo.textContent = tas.mrc_no && tas.mrc_no !== '-' ? tas.mrc_no : '-';

        const detPurchaserTin = document.getElementById('tasDetPurchaserTin');
        if (detPurchaserTin) detPurchaserTin.textContent = tas.purchaser_tin || '-';

        const detPurcType = document.getElementById('tasDetPurcType');
        if (detPurcType) detPurcType.textContent = tas.purc_type || 'G';

        const detReceiptDateEc = document.getElementById('tasDetReceiptDateEc');
        if (detReceiptDateEc) detReceiptDateEc.textContent = tas.receipt_date_ec || tas.date_ec || '-';

        const detEncodingDateEc = document.getElementById('tasDetEncodingDateEc');
        if (detEncodingDateEc) detEncodingDateEc.textContent = tas.encoding_date_ec || '-';

        const detEncodedBy = document.getElementById('tasDetEncodedBy');
        if (detEncodedBy) detEncodedBy.textContent = tas.encoded_by || '-';

        // Receipt Slip Preview
        const slipUrl = tas.receipt_slip_url || tas.attachment_url || null;
        const slipBtn = document.getElementById('tasModalReceiptSlipBtn');
        const noSlipBadge = document.getElementById('tasModalNoSlipBadge');
        const slipPreviewCard = document.getElementById('tasSlipPreviewCard');
        const slipPreviewOpenLink = document.getElementById('tasSlipPreviewOpenLink');
        const slipPreviewImg = document.getElementById('tasSlipPreviewImg');
        const slipPreviewPdf = document.getElementById('tasSlipPreviewPdf');

        if (slipUrl) {
            if (slipBtn) {
                slipBtn.href = slipUrl;
                slipBtn.classList.remove('d-none');
                slipBtn.classList.add('d-inline-flex');
            }
            if (noSlipBadge) noSlipBadge.classList.add('d-none');

            const isPdf = /\.pdf(\?.*)?$/i.test(slipUrl);
            if (slipPreviewCard) slipPreviewCard.classList.remove('d-none');
            if (slipPreviewOpenLink) slipPreviewOpenLink.href = slipUrl;

            if (isPdf) {
                if (slipPreviewImg) { slipPreviewImg.classList.add('d-none'); slipPreviewImg.src = ''; }
                if (slipPreviewPdf) { slipPreviewPdf.classList.remove('d-none'); slipPreviewPdf.src = slipUrl; }
            } else {
                if (slipPreviewPdf) { slipPreviewPdf.classList.add('d-none'); slipPreviewPdf.src = ''; }
                if (slipPreviewImg) { slipPreviewImg.classList.remove('d-none'); slipPreviewImg.src = slipUrl; }
            }
        } else {
            if (slipBtn) {
                slipBtn.classList.add('d-none');
                slipBtn.classList.remove('d-inline-flex');
            }
            if (noSlipBadge) noSlipBadge.classList.remove('d-none');
            if (slipPreviewCard) slipPreviewCard.classList.add('d-none');
            if (slipPreviewImg) slipPreviewImg.src = '';
            if (slipPreviewPdf) slipPreviewPdf.src = '';
        }

        // Show modal
        if (window.bootstrap && bootstrap.Modal) {
            bootstrap.Modal.getOrCreateInstance(modalEl).show();
        } else if (window.$ && typeof $(modalEl).modal === 'function') {
            $(modalEl).modal('show');
        }
    }

    // Open Expense Request & WHT Detail Modal for TAS Purchase
    function openTasExpenseDetailModal(idx) {
        _activeTasDetailIdx = idx;
        _activeExpenseDetailType = 'tas';
        const tas = (window._pageTasPurchases && window._pageTasPurchases[idx]) || {};
        _renderExpenseDetailModal(tas, idx, 'tas');
    }

    // Open Expense Request & WHT Detail Modal for Expense Row
    function openExpenseDetailModal(idx) {
        _activeTasDetailIdx = idx;
        _activeExpenseDetailType = 'expense';
        const exp = (window._pageExpenses && window._pageExpenses[idx]) || {};
        _renderExpenseDetailModal(exp, idx, 'expense');
    }

    // Switch from Detail Modal to ERCA Slip Inspector
    function openPageTasInspectorFromDetail() {
        const modalEl = document.getElementById('tasExpenseDetailModal');
        if (modalEl) {
            if (window.bootstrap && bootstrap.Modal) {
                const inst = bootstrap.Modal.getInstance(modalEl);
                if (inst) inst.hide();
            } else if (window.$ && typeof $(modalEl).modal === 'function') {
                $(modalEl).modal('hide');
            }
        }
        if (_activeTasDetailIdx !== null) {
            setTimeout(() => {
                if (_activeExpenseDetailType === 'expense') {
                    openExpenseInspector(_activeTasDetailIdx);
                } else {
                    openPageTasInspector(_activeTasDetailIdx);
                }
            }, 300);
        }
    }

    // Open Side-by-Side ERCA Inspector for an Expense in #pageExpenseTable
    function openExpenseInspector(idx) {
        const exp = (window._pageExpenses && window._pageExpenses[idx]) || {};
        const modalEl = document.getElementById('movementSlipModal');
        if (!modalEl) return;

        const cleanNum = (v) => {
            const n = parseFloat(String(v ?? '0').replace(/[^0-9.-]/g, ''));
            return isNaN(n) ? 0 : n;
        };

        const idInput = document.getElementById('movement_edit_ocr_item_id');
        if (idInput) idInput.value = exp.receipt_id || exp.id || '';

        let resolvedExpRcptId = '';
        if (exp.receipt_id) {
            resolvedExpRcptId = String(exp.receipt_id).startsWith('REC-') ? exp.receipt_id : ('REC-' + exp.receipt_id);
        } else if (exp.id) {
            resolvedExpRcptId = String(exp.id).match(/^(EXP|PROC|CS|PC|REC)-/i) ? String(exp.id) : ('EXP-' + exp.id);
        } else if (exp.request_number) {
            resolvedExpRcptId = exp.request_number;
        }
        const rcptIdInput = document.getElementById('movement_edit_receipt_id');
        if (rcptIdInput) rcptIdInput.value = resolvedExpRcptId;

        const expIdInput = document.getElementById('movement_edit_expense_request_id');
        if (expIdInput) expIdInput.value = exp.id || '';

        const reqNumInput = document.getElementById('movement_edit_request_number');
        if (reqNumInput) reqNumInput.value = exp.request_number || '';

        const pathInput = document.getElementById('movement_edit_receipt_file_path');
        if (pathInput) pathInput.value = exp.receipt_slip_url || exp.attachment_url || '';

        const fileInput = document.getElementById('movementSlipFileInput');
        if (fileInput) fileInput.value = '';

        const camInput = document.getElementById('movementSlipCameraInput');
        if (camInput) camInput.value = '';

        const progBox = document.getElementById('movementUploadProgress');
        if (progBox) progBox.className = 'alert alert-info py-2 px-3 small rounded-3 mb-2 d-none align-items-center justify-content-between flex-shrink-0';

        const badgeId = document.getElementById('movement_edit_ocr_badge_id');
        if (badgeId) badgeId.textContent = exp.request_number || ('EXP #' + (idx + 1));

        const fsDisplay = document.getElementById('movementModalFsNoDisplay');
        if (fsDisplay) {
            fsDisplay.textContent = exp.fs_no && exp.fs_no !== '-' 
                ? ('FS #' + exp.fs_no + ' (' + (exp.request_number || 'Expense') + ')') 
                : (exp.request_number ? (exp.request_number + ' Expense Inspector') : 'Expense Inspector');
        }

        const sellerName = document.getElementById('movement_edit_seller_name');
        if (sellerName) sellerName.value = exp.payee || exp.seller_name || '';

        const tinInput = document.getElementById('movement_edit_supplier_tin');
        if (tinInput) {
            const rawTin = exp.seller_tin === '-' ? '' : (exp.seller_tin || '');
            tinInput.value = rawTin;
            validateMovementTin(rawTin);
        }

        const fsInput = document.getElementById('movement_edit_fs_no');
        if (fsInput) {
            const rawFs = exp.fs_no === '-' ? '' : (exp.fs_no || '');
            fsInput.value = rawFs;
            validateMovementFs(rawFs);
        }

        const mrcInput = document.getElementById('movement_edit_mrc_no');
        if (mrcInput) mrcInput.value = (exp.mrc_no === '-' ? '' : exp.mrc_no) || '';

        const dateInput = document.getElementById('movement_edit_receipt_date');
        if (dateInput) {
            let ymd = exp.receipt_date_ymd || '';
            if (!ymd && (exp.raw_date || exp.date_gc)) {
                try {
                    const parsed = new Date(exp.raw_date || exp.date_gc);
                    if (!isNaN(parsed.getTime())) {
                        const yr = parsed.getFullYear();
                        const mo = String(parsed.getMonth() + 1).padStart(2, '0');
                        const da = String(parsed.getDate()).padStart(2, '0');
                        ymd = `${yr}-${mo}-${da}`;
                    }
                } catch (e) {}
            }
            dateInput.value = ymd;
        }

        const dateEcPreview = document.getElementById('movement_edit_date_ec_preview');
        if (dateEcPreview) dateEcPreview.textContent = exp.date_ec ? ('EC: ' + exp.date_ec) : '';

        const descInput = document.getElementById('movement_edit_item_description');
        if (descInput) descInput.value = exp.description && exp.description !== '-' ? exp.description : (exp.category || 'Expense Voucher');

        const projSelect = document.getElementById('movement_edit_project_id');
        if (projSelect) projSelect.value = exp.project_id || '';

        const catSelect = document.getElementById('movement_edit_category');
        if (catSelect) catSelect.value = exp.category || 'materials';

        const isVat = cleanNum(exp.vat_amount) > 0 || exp.purchase_category === 'vat';
        const vatCatSelect = document.getElementById('movement_edit_vat_category');
        if (vatCatSelect) vatCatSelect.value = isVat ? 'G' : 'E';

        const purcTypeSelect = document.getElementById('movement_edit_purchase_type');
        if (purcTypeSelect) purcTypeSelect.value = exp.purc_type || 'PUR_GD';

        const baseVal = cleanNum(exp.base_amount || exp.amount);
        const vatVal  = cleanNum(exp.vat_amount);
        const totalVal = cleanNum(exp.gross_amount || exp.total_amount || (baseVal + vatVal));

        const baseInput = document.getElementById('movement_edit_total_value');
        if (baseInput) baseInput.value = baseVal.toFixed(2);

        const vatInput = document.getElementById('movement_edit_vat_amount');
        if (vatInput) vatInput.value = vatVal.toFixed(2);

        const totalInput = document.getElementById('movement_edit_value_after_vat');
        if (totalInput) totalInput.value = totalVal.toFixed(2);

        // Populate Withholding fields
        const hasExpWht = !!(exp.has_withholding || (cleanNum(exp.withholding_amount) > 0));
        const whtCheck = document.getElementById('movement_edit_has_withholding');
        if (whtCheck) {
            whtCheck.checked = hasExpWht;
            toggleMovementWithholding(hasExpWht);
        }
        const whtRateSelect = document.getElementById('movement_edit_withholding_rate');
        const expWhtRate = cleanNum(exp.withholding_rate) || 3;
        if (whtRateSelect) {
            if ([2, 3, 30].includes(Math.round(expWhtRate))) {
                whtRateSelect.value = String(Math.round(expWhtRate));
            } else {
                whtRateSelect.value = 'custom';
                const customRateInput = document.getElementById('movement_edit_custom_wht_rate');
                if (customRateInput) {
                    customRateInput.value = expWhtRate;
                    customRateInput.classList.remove('d-none');
                }
            }
        }
        const whtAmtInput = document.getElementById('movement_edit_withholding_amount');
        if (whtAmtInput) {
            whtAmtInput.value = cleanNum(exp.withholding_amount).toFixed(2);
        }
        const whtCertInput = document.getElementById('movement_edit_withholding_receipt_number');
        if (whtCertInput) {
            whtCertInput.value = (exp.withholding_receipt_number && exp.withholding_receipt_number !== '-') ? exp.withholding_receipt_number : '';
        }
        const whtSlipLink = document.getElementById('movement_edit_current_wht_slip_link');
        if (whtSlipLink) {
            if (exp.withholding_receipt_url) {
                whtSlipLink.href = exp.withholding_receipt_url;
                whtSlipLink.classList.remove('d-none');
            } else {
                whtSlipLink.classList.add('d-none');
            }
        }
        recalcMovementWht();

        // Populate Verify Without Receipt Switch
        const isVerifiedNoRec = !!exp.is_verified_without_receipt;
        const verifiedNoRecSwitch = document.getElementById('movement_edit_is_verified_without_receipt');
        if (verifiedNoRecSwitch) {
            verifiedNoRecSwitch.checked = isVerifiedNoRec;
            onToggleVerifyWithoutReceiptModal(isVerifiedNoRec);
        }

        // Clear alerts
        const alertBox = document.getElementById('movementOcrModalAlert');
        if (alertBox) {
            alertBox.className = 'alert d-none';
            alertBox.innerHTML = '';
        }

        // Set preview
        const slipUrl = exp.receipt_slip_url || exp.attachment_url || exp.attachment;
        _setPageSlipPreview(slipUrl);

        // Show modal
        if (window.bootstrap && bootstrap.Modal) {
            bootstrap.Modal.getOrCreateInstance(modalEl).show();
        } else if (window.$ && typeof $(modalEl).modal === 'function') {
            $(modalEl).modal('show');
        }
    }

    function onToggleVerifyWithoutReceiptModal(isChecked) {
        const badge = document.getElementById('movement_verified_no_rec_badge');
        const vatCategory = document.getElementById('movement_edit_vat_category');
        const vatAmount = document.getElementById('movement_edit_vat_amount');
        const totalValInput = document.getElementById('movement_edit_total_value');
        const afterValInput = document.getElementById('movement_edit_value_after_vat');

        if (badge) {
            badge.textContent = isChecked ? 'Verified (No Slip)' : '0% Exempt';
            badge.className = isChecked ? 'badge bg-info text-white font-monospace' : 'badge bg-secondary-subtle text-muted font-monospace';
        }
        if (isChecked) {
            if (vatCategory) vatCategory.value = 'E';
            if (vatAmount) vatAmount.value = '0.00';
            if (afterValInput && totalValInput) {
                afterValInput.value = totalValInput.value;
            }
            recalcMovementWht();
        }
    }

    function onToggleQuickVerifyNoRec(idx, isChecked) {
        const row = document.getElementById('expense-quick-fill-row-' + idx)
            || document.querySelector(`tr.expense-quick-fill-row[data-idx="${idx}"]`);
        if (!row) return;

        const tinInput = row.querySelector('input[name="supplier_tin"]');
        const fsInput = row.querySelector('input[name="fs_no"]');
        if (isChecked) {
            if (tinInput && !tinInput.value) tinInput.removeAttribute('required');
            if (fsInput && !fsInput.value) fsInput.removeAttribute('required');
        }
    }

    function toggleVerifyWithoutReceipt(expenseId, reqNo, idx, currentStatus) {
        const newStatus = currentStatus === 1 ? 0 : 1;
        const isVerified = (newStatus === 1);

        const btn = document.getElementById('btn-verify-no-rec-' + expenseId)
            || document.querySelector(`tr.expense-row[data-idx="${idx}"] .btn-verify-no-receipt`);
        const origHtml = btn ? btn.innerHTML : '';
        if (btn) {
            btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i>';
            btn.disabled = true;
        }

        const csrfToken = '{{ csrf_token() }}';

        fetch('{{ route("finance.tax-deductions.verify-without-receipt") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({
                expense_id: expenseId,
                request_number: reqNo,
                is_verified: isVerified
            })
        })
        .then(r => r.json())
        .then(data => {
            if (btn) btn.disabled = false;
            if (data.success) {
                if (data.is_verified_without_receipt) {
                    // "after verifay i don't want to see in that section" - remove row from table
                    const tr = document.getElementById('page-expense-row-' + expenseId)
                        || document.querySelector(`tr.expense-row[data-idx="${idx}"]`);
                    const quickRow = document.getElementById('expense-quick-row-' + idx);
                    if (quickRow) quickRow.remove();

                    if (tr) {
                        tr.style.transition = 'all 0.35s ease-out';
                        tr.style.opacity = '0';
                        tr.style.transform = 'translateX(25px)';
                        setTimeout(() => {
                            tr.remove();
                            const tbody = document.querySelector('#pageExpenseTable tbody');
                            if (tbody && tbody.querySelectorAll('tr.expense-row').length === 0) {
                                tbody.innerHTML = `<tr><td colspan="12" class="text-center py-5 text-muted">
                                    <i class="fa-solid fa-circle-check fs-2 text-success d-block mb-2"></i>
                                    <span class="fw-semibold">All paid purchases & vouchers have been verified or completed for this period.</span>
                                </td></tr>`;
                            }
                            updateFilterCounters();
                        }, 350);
                    } else {
                        updateFilterCounters();
                    }

                    // Decrement Tab 2 pill counter
                    const tabBadge = document.querySelector('#page-expense-tab .badge');
                    if (tabBadge) {
                        const cur = parseInt(tabBadge.textContent.trim(), 10) || 0;
                        if (cur > 0) tabBadge.textContent = cur - 1;
                    }

                    // Increment archive counter
                    const archCountSpan = document.getElementById('filterCountVerifiedNoReceipt');
                    if (archCountSpan) {
                        const curArch = parseInt(archCountSpan.textContent.trim(), 10) || 0;
                        archCountSpan.textContent = curArch + 1;
                    }

                    showMovementToast((reqNo || 'Expense') + ' verified without receipt and removed from this section.', 'success');
                } else {
                    syncExpenseRowDom(idx, {
                        is_verified_without_receipt: false
                    });
                    updateFilterCounters();
                    showMovementToast(data.message || 'Verification updated successfully', 'success');
                }
            } else {
                if (btn) btn.innerHTML = origHtml;
                showMovementToast(data.message || 'Failed to update verification', 'danger');
            }
        })
        .catch(err => {
            if (btn) {
                btn.disabled = false;
                btn.innerHTML = origHtml;
            }
            showMovementToast('Error: ' + err.message, 'danger');
        });
    }

    function openVerifiedArchiveModal() {
        const modalEl = document.getElementById('verifiedNoReceiptArchiveModal');
        if (!modalEl) return;
        if (window.bootstrap && bootstrap.Modal) {
            bootstrap.Modal.getOrCreateInstance(modalEl).show();
        } else if (window.$ && typeof $(modalEl).modal === 'function') {
            $(modalEl).modal('show');
        }
    }

    function restoreFromVerifiedArchive(expenseId, reqNo, idx) {
        if (!confirm(`Restore voucher "${reqNo || expenseId}" back to Purchases & Paid Expenses?`)) {
            return;
        }

        const btn = document.getElementById('btn-restore-archive-' + (expenseId || idx));
        const origHtml = btn ? btn.innerHTML : '';
        if (btn) {
            btn.disabled = true;
            btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-1"></i>Restoring...';
        }

        fetch('{{ route("finance.tax-deductions.verify-without-receipt") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({
                expense_id: expenseId,
                request_number: reqNo,
                is_verified: false
            })
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                showMovementToast(data.message || 'Restored to Purchases & Paid Expenses.', 'success');
                const modalEl = document.getElementById('verifiedNoReceiptArchiveModal');
                if (modalEl && window.bootstrap && bootstrap.Modal) {
                    bootstrap.Modal.getInstance(modalEl)?.hide();
                }
                setTimeout(() => {
                    window.location.reload();
                }, 500);
            } else {
                if (btn) {
                    btn.disabled = false;
                    btn.innerHTML = origHtml;
                }
                showMovementToast(data.message || 'Failed to restore voucher', 'danger');
            }
        })
        .catch(err => {
            if (btn) {
                btn.disabled = false;
                btn.innerHTML = origHtml;
            }
            showMovementToast('Error: ' + err.message, 'danger');
        });
    }

    function updateFilterCounters() {
        const rows = document.querySelectorAll('#pageExpenseTable tbody tr.expense-row');
        let totalCount = rows.length;
        let vatCount = 0;
        let tasCount = 0;
        let missingRec = 0;

        rows.forEach(tr => {
            const cat = tr.getAttribute('data-purchase-category');
            if (cat === 'vat') vatCount++;
            else tasCount++;

            const hasRec = tr.getAttribute('data-has-receipt') === '1';
            const isVerified = tr.getAttribute('data-verified-no-receipt') === '1';
            if (!hasRec && !isVerified) {
                missingRec++;
            }
        });

        const allEl = document.getElementById('filterCountAllPurchases');
        if (allEl) allEl.textContent = totalCount;

        const vatEl = document.getElementById('filterCountVatPurchases');
        if (vatEl) vatEl.textContent = vatCount;

        const tasEl = document.getElementById('filterCountTasPurchases');
        if (tasEl) tasEl.textContent = tasCount;

        const missingEl = document.getElementById('filterCountMissingReceipt');
        if (missingEl) missingEl.textContent = missingRec;

        const tabBadge = document.querySelector('#page-expense-tab .badge');
        if (tabBadge) tabBadge.textContent = totalCount;
    }

    function showMovementToast(message, type = 'info') {
        let toastContainer = document.getElementById('movementToastContainer');
        if (!toastContainer) {
            toastContainer = document.createElement('div');
            toastContainer.id = 'movementToastContainer';
            toastContainer.className = 'toast-container position-fixed bottom-0 end-0 p-3';
            toastContainer.style.zIndex = '9999';
            document.body.appendChild(toastContainer);
        }

        const toastEl = document.createElement('div');
        const bgClass = type === 'success' ? 'bg-success text-white' : (type === 'danger' || type === 'error' ? 'bg-danger text-white' : 'bg-primary text-white');
        const iconClass = type === 'success' ? 'fa-circle-check' : (type === 'danger' || type === 'error' ? 'fa-triangle-exclamation' : 'fa-circle-info');

        toastEl.className = `toast align-items-center ${bgClass} border-0 shadow-lg show`;
        toastEl.setAttribute('role', 'alert');
        toastEl.setAttribute('aria-live', 'assertive');
        toastEl.setAttribute('aria-atomic', 'true');
        toastEl.innerHTML = `
            <div class="d-flex">
                <div class="toast-body d-flex align-items-center gap-2 py-2.5 px-3">
                    <i class="fa-solid ${iconClass} fs-6"></i>
                    <span class="small fw-semibold">${message}</span>
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close" onclick="this.closest('.toast').remove()"></button>
            </div>
        `;
        toastContainer.appendChild(toastEl);
        setTimeout(() => {
            toastEl.style.transition = 'opacity 0.5s ease';
            toastEl.style.opacity = '0';
            setTimeout(() => toastEl.remove(), 500);
        }, 4000);
    }

    // Modal Withholding Tax handlers
    function toggleMovementWithholding(isChecked) {
        const container = document.getElementById('movement_wht_fields_container');
        const badge = document.getElementById('movement_wht_status_badge');
        if (container) {
            if (isChecked) {
                container.classList.remove('d-none');
            } else {
                container.classList.add('d-none');
            }
        }
        if (badge) {
            if (isChecked) {
                badge.className = 'badge bg-danger-subtle text-danger border border-danger-subtle font-monospace';
                badge.textContent = 'WHT Active';
            } else {
                badge.className = 'badge bg-secondary-subtle text-muted font-monospace';
                badge.textContent = 'No WHT';
            }
        }
        recalcMovementWht();
    }

    function onMovementWhtRateChange(rateVal) {
        const customInput = document.getElementById('movement_edit_custom_wht_rate');
        if (customInput) {
            if (rateVal === 'custom') {
                customInput.classList.remove('d-none');
                customInput.focus();
            } else {
                customInput.classList.add('d-none');
            }
        }
        recalcMovementWht();
    }

    function recalcMovementWht() {
        const hasWhtCheck = document.getElementById('movement_edit_has_withholding');
        const hasWht = hasWhtCheck ? hasWhtCheck.checked : false;
        const totalInput = document.getElementById('movement_edit_value_after_vat');
        const baseInput = document.getElementById('movement_edit_total_value');
        const whtAmtInput = document.getElementById('movement_edit_withholding_amount');
        const netDisplay = document.getElementById('movement_edit_net_disbursed_display');
        const badge = document.getElementById('movement_wht_status_badge');

        const base = parseFloat(baseInput?.value || 0) || 0;
        const total = parseFloat(totalInput?.value || 0) || 0;

        if (!hasWht) {
            if (whtAmtInput) whtAmtInput.value = '0.00';
            if (netDisplay) netDisplay.value = 'ETB ' + total.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            if (badge) {
                badge.className = 'badge bg-secondary-subtle text-muted font-monospace';
                badge.textContent = 'No WHT';
            }
            return;
        }

        const rateSelect = document.getElementById('movement_edit_withholding_rate');
        let rate = 3.0;
        if (rateSelect) {
            if (rateSelect.value === 'custom') {
                const customInput = document.getElementById('movement_edit_custom_wht_rate');
                rate = parseFloat(customInput?.value || 0) || 0;
            } else {
                rate = parseFloat(rateSelect.value) || 3.0;
            }
        }

        const whtAmount = Math.round(base * (rate / 100) * 100) / 100;
        if (whtAmtInput) {
            whtAmtInput.value = whtAmount.toFixed(2);
        }

        const net = Math.max(0, total - whtAmount);
        if (netDisplay) {
            netDisplay.value = 'ETB ' + net.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        }
        if (badge) {
            badge.className = 'badge bg-danger text-white font-monospace';
            badge.textContent = rate + '% WHT (-' + whtAmount.toFixed(2) + ')';
        }
    }

    function recalcMovementWhtCustomAmount(val) {
        const whtAmount = parseFloat(val) || 0;
        const totalInput = document.getElementById('movement_edit_value_after_vat');
        const netDisplay = document.getElementById('movement_edit_net_disbursed_display');
        const badge = document.getElementById('movement_wht_status_badge');
        const total = parseFloat(totalInput?.value || 0) || 0;

        const net = Math.max(0, total - whtAmount);
        if (netDisplay) {
            netDisplay.value = 'ETB ' + net.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        }
        if (badge) {
            badge.className = 'badge bg-danger text-white font-monospace';
            badge.textContent = 'WHT: -ETB ' + whtAmount.toFixed(2);
        }
    }

    // Inline Quick Fill Row Handlers for #pageExpenseTable
    function toggleExpenseQuickRow(idx) {
        const exp = (window._pageExpenses && window._pageExpenses[idx]) || {};
        const rowId = exp.id ?? idx;
        const quickRow = document.getElementById('expense-quick-fill-row-' + rowId)
            || document.querySelector(`tr.expense-quick-fill-row[data-idx="${idx}"]`);
        if (!quickRow) return;

        const btnToggle = document.getElementById('btn-quick-row-' + rowId)
            || document.querySelector(`#page-expense-row-${rowId} .btn-quick-toggle`);
        const textSpan = btnToggle ? btnToggle.querySelector('.quick-toggle-text') : null;

        if (quickRow.classList.contains('d-none')) {
            // Close any other open quick fill rows
            document.querySelectorAll('tr.expense-quick-fill-row:not(.d-none)').forEach(r => {
                r.classList.add('d-none');
                const rIdx = r.getAttribute('data-idx');
                const otherBtn = document.querySelector(`button[onclick="toggleExpenseQuickRow(${rIdx})"]`);
                if (otherBtn) {
                    otherBtn.classList.remove('btn-primary', 'text-white');
                    otherBtn.classList.add('btn-outline-primary');
                    const oTxt = otherBtn.querySelector('.quick-toggle-text');
                    if (oTxt) oTxt.textContent = 'Fill / Edit';
                }
            });

            quickRow.classList.remove('d-none');
            if (btnToggle) {
                btnToggle.classList.remove('btn-outline-primary');
                btnToggle.classList.add('btn-primary', 'text-white');
                if (textSpan) textSpan.textContent = 'Close';
            }
            quickRow.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        } else {
            quickRow.classList.add('d-none');
            if (btnToggle) {
                btnToggle.classList.remove('btn-primary', 'text-white');
                btnToggle.classList.add('btn-outline-primary');
                if (textSpan) textSpan.textContent = 'Fill / Edit';
            }
        }
    }

    function toggleQuickWhtSection(idx, isChecked) {
        const exp = (window._pageExpenses && window._pageExpenses[idx]) || {};
        const rowId = exp.id ?? idx;
        const body = document.getElementById('quickWhtBody-' + rowId);
        const noneBox = document.getElementById('quickWhtNone-' + rowId);

        if (isChecked) {
            if (body) body.classList.remove('d-none');
            if (noneBox) noneBox.classList.add('d-none');
            calcQuickWht(idx);
        } else {
            if (body) body.classList.add('d-none');
            if (noneBox) noneBox.classList.remove('d-none');
            const whtInput = document.querySelector(`#quickRowForm-${rowId} input[name="withholding_amount"]`);
            if (whtInput) whtInput.value = '';
            calcQuickNet(idx);
        }
    }

    function calcQuickAmounts(idx, source) {
        const exp = (window._pageExpenses && window._pageExpenses[idx]) || {};
        const rowId = exp.id ?? idx;
        const form = document.getElementById('quickRowForm-' + rowId);
        if (!form) return;

        const baseInput  = form.elements['total_value'];
        const vatInput   = form.elements['vat_amount'];
        const grossInput = form.elements['value_after_vat'];

        let base  = parseFloat(baseInput ? baseInput.value : 0) || 0;
        let vat   = parseFloat(vatInput ? vatInput.value : 0) || 0;
        let gross = parseFloat(grossInput ? grossInput.value : 0) || 0;

        if (source === 'base') {
            if (vat > 0) {
                vat = Math.round(base * 0.15 * 100) / 100;
                if (vatInput) vatInput.value = vat.toFixed(2);
            }
            gross = Math.round((base + vat) * 100) / 100;
            if (grossInput) grossInput.value = gross.toFixed(2);
        } else if (source === 'vat') {
            gross = Math.round((base + vat) * 100) / 100;
            if (grossInput) grossInput.value = gross.toFixed(2);
        } else if (source === 'gross') {
            if (vat > 0) {
                base = Math.round((gross / 1.15) * 100) / 100;
                vat  = Math.round((gross - base) * 100) / 100;
                if (baseInput) baseInput.value = base.toFixed(2);
                if (vatInput)  vatInput.value  = vat.toFixed(2);
            } else {
                base = gross;
                if (baseInput) baseInput.value = base.toFixed(2);
                if (vatInput)  vatInput.value  = '0.00';
            }
        }

        calcQuickWht(idx);
    }

    function setQuickVat15(idx) {
        const exp = (window._pageExpenses && window._pageExpenses[idx]) || {};
        const rowId = exp.id ?? idx;
        const form = document.getElementById('quickRowForm-' + rowId);
        if (!form) return;

        const baseInput  = form.elements['total_value'];
        const vatInput   = form.elements['vat_amount'];
        const grossInput = form.elements['value_after_vat'];

        const base = parseFloat(baseInput ? baseInput.value : 0) || 0;
        const vat  = Math.round(base * 0.15 * 100) / 100;
        const gross = Math.round((base + vat) * 100) / 100;

        if (vatInput)  vatInput.value  = vat.toFixed(2);
        if (grossInput) grossInput.value = gross.toFixed(2);

        calcQuickWht(idx);
    }

    function setQuickVatZero(idx) {
        const exp = (window._pageExpenses && window._pageExpenses[idx]) || {};
        const rowId = exp.id ?? idx;
        const form = document.getElementById('quickRowForm-' + rowId);
        if (!form) return;

        const baseInput  = form.elements['total_value'];
        const vatInput   = form.elements['vat_amount'];
        const grossInput = form.elements['value_after_vat'];

        const base = parseFloat(baseInput ? baseInput.value : 0) || 0;
        if (vatInput)  vatInput.value  = '0.00';
        if (grossInput) grossInput.value = base.toFixed(2);

        calcQuickWht(idx);
    }

    function calcQuickWht(idx) {
        const exp = (window._pageExpenses && window._pageExpenses[idx]) || {};
        const rowId = exp.id ?? idx;
        const form = document.getElementById('quickRowForm-' + rowId);
        if (!form) return;

        const rateSelect = form.elements['withholding_rate'];
        const rate = parseFloat(rateSelect ? rateSelect.value : 3) || 3;

        const baseInput = form.elements['total_value'];
        const base = parseFloat(baseInput ? baseInput.value : (exp.base_amount || exp.amount || 0)) || 0;
        const whtAmt = Math.round(base * (rate / 100) * 100) / 100;

        const amtInput = form.elements['withholding_amount'];
        if (amtInput) amtInput.value = whtAmt.toFixed(2);

        calcQuickNet(idx);
    }

    function calcQuickNet(idx) {
        const exp = (window._pageExpenses && window._pageExpenses[idx]) || {};
        const rowId = exp.id ?? idx;
        const form = document.getElementById('quickRowForm-' + rowId);
        if (!form) return;

        const toggle = document.getElementById('quickWhtToggle-' + rowId);
        const isWht = toggle ? toggle.checked : false;

        const baseInput = form.elements['total_value'];
        const vatInput  = form.elements['vat_amount'];

        const base = parseFloat(baseInput ? baseInput.value : (exp.base_amount || exp.amount || 0)) || 0;
        const vat  = parseFloat(vatInput ? vatInput.value : (exp.vat_amount || 0)) || 0;
        const total = base + vat;

        let wht = 0;
        if (isWht) {
            const amtInput = form.elements['withholding_amount'];
            wht = parseFloat(amtInput ? amtInput.value : 0) || 0;
        }

        const net = Math.max(0, total - wht);

        const whtDisp = document.getElementById('quickWhtDisplay-' + rowId);
        const netDisp = document.getElementById('quickNetDisplay-' + rowId);

        if (whtDisp) {
            whtDisp.textContent = isWht && wht > 0 
                ? '- ETB ' + wht.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
                : 'ETB 0.00';
        }
        if (netDisp) {
            netDisp.textContent = 'ETB ' + net.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        }
    }

    function saveExpenseQuickRow(event, idx) {
        if (event && typeof event.preventDefault === 'function') event.preventDefault();
        const exp = (window._pageExpenses && window._pageExpenses[idx]) || {};
        const rowId = exp.id ?? idx;
        const form = document.getElementById('quickRowForm-' + rowId);
        if (!form) return;

        const alertBox = document.getElementById('quickRowAlert-' + rowId);
        const submitBtn = document.getElementById('btnQuickSave-' + rowId);
        const origBtnHtml = submitBtn ? submitBtn.innerHTML : '';

        // Validate supplier name
        const sellerInput = form.elements['seller_name'];
        if (sellerInput && !sellerInput.value.trim()) {
            sellerInput.focus();
            if (alertBox) {
                alertBox.className = 'alert alert-warning py-1.5 px-3 small rounded-3 mb-2.5 d-flex align-items-center gap-2';
                alertBox.innerHTML = '<i class="fa-solid fa-triangle-exclamation text-warning"></i> Supplier / Payee Name is required.';
                alertBox.classList.remove('d-none');
            }
            return;
        }

        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-1.5"></i>Saving...';
        }
        if (alertBox) {
            alertBox.className = 'alert alert-info py-1.5 px-3 small rounded-3 mb-2.5 d-flex align-items-center gap-2';
            alertBox.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-1"></i> Saving receipt and withholding details...';
            alertBox.classList.remove('d-none');
        }

        const formData = new FormData(form);
        formData.set('is_verified_without_receipt', form.elements['is_verified_without_receipt']?.checked ? '1' : '0');

        fetch('{{ route("finance.tax-deductions.update-ocr-item") }}', {
            method: 'POST',
            body: formData,
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(async res => {
            const isJson = (res.headers.get('content-type') || '').includes('application/json');
            const data = isJson ? await res.json() : { success: false, message: await res.text() };
            if (!res.ok) {
                let errMsg = data.message || ('Server returned HTTP ' + res.status);
                if (data.errors && typeof data.errors === 'object') {
                    const errLines = Object.values(data.errors).flat().join('<br>');
                    if (errLines) errMsg = errLines;
                }
                throw new Error(errMsg);
            }
            return data;
        })
        .then(data => {
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.innerHTML = origBtnHtml;
            }

            if (data.success && data.item) {
                const itm = data.item;
                window._hasMovementOcrEditsSaved = true;

                const isVerifiedNoRecChecked = !!(itm.is_verified_without_receipt || form.elements['is_verified_without_receipt']?.checked);
                if (isVerifiedNoRecChecked) {
                    const tr = document.getElementById('page-expense-row-' + rowId)
                        || document.querySelector(`tr.expense-row[data-idx="${idx}"]`);
                    const quickRow = document.getElementById('expense-quick-row-' + idx);
                    if (quickRow) quickRow.remove();
                    if (tr) {
                        tr.style.transition = 'all 0.35s ease-out';
                        tr.style.opacity = '0';
                        tr.style.transform = 'translateX(25px)';
                        setTimeout(() => {
                            tr.remove();
                            updateFilterCounters();
                        }, 350);
                    }
                    const tabBadge = document.querySelector('#page-expense-tab .badge');
                    if (tabBadge) {
                        const cur = parseInt(tabBadge.textContent.trim(), 10) || 0;
                        if (cur > 0) tabBadge.textContent = cur - 1;
                    }
                    const archCountSpan = document.getElementById('filterCountVerifiedNoReceipt');
                    if (archCountSpan) {
                        const curArch = parseInt(archCountSpan.textContent.trim(), 10) || 0;
                        archCountSpan.textContent = curArch + 1;
                    }
                    showMovementToast('Expense verified without receipt and removed from this section.', 'success');
                    return;
                }

                // Update local memory object
                if (window._pageExpenses && window._pageExpenses[idx]) {
                    Object.assign(window._pageExpenses[idx], {
                        payee: itm.vendor_name || form.elements['seller_name']?.value,
                        seller_tin: itm.vendor_tin || form.elements['supplier_tin']?.value,
                        fs_no: itm.fs_no || form.elements['fs_no']?.value,
                        mrc_no: itm.mrc_no || form.elements['mrc_no']?.value,
                        has_withholding: !!itm.has_withholding,
                        withholding_rate: itm.withholding_rate,
                        withholding_amount: itm.withholding_amount,
                        withholding_receipt_number: itm.withholding_receipt_number,
                        withholding_receipt_url: itm.withholding_receipt_url,
                        net_disbursed: itm.net_disbursed,
                        attachment_url: itm.file_url || window._pageExpenses[idx].attachment_url
                    });
                }

                if (alertBox) {
                    alertBox.className = 'alert alert-success py-1.5 px-3 small rounded-3 mb-2.5 d-flex align-items-center justify-content-between gap-2';
                    alertBox.innerHTML = `
                        <div class="d-flex align-items-center gap-2">
                            <i class="fa-solid fa-circle-check text-success"></i>
                            <span class="fw-semibold">${data.message || 'Saved successfully!'}</span>
                        </div>
                        <button type="button" class="btn btn-xs btn-outline-success rounded-pill px-2 py-0.5" onclick="toggleExpenseQuickRow(${idx})">Close Row</button>
                    `;
                }

                // Sync the parent row in the table
                syncExpenseRowDom(idx, itm);
                recomputeExpenseTableFooters();
                updateFilterCounters();

                // Also sync TAS table if matching
                syncMatchingTasRowDom(itm);
            } else {
                if (alertBox) {
                    alertBox.className = 'alert alert-danger py-1.5 px-3 small rounded-3 mb-2.5 d-flex align-items-center gap-2';
                    alertBox.innerHTML = '<i class="fa-solid fa-triangle-exclamation text-danger"></i> ' + (data.message || 'Failed to save.');
                }
            }
        })
        .catch(err => {
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.innerHTML = origBtnHtml;
            }
            if (alertBox) {
                alertBox.className = 'alert alert-danger py-1.5 px-3 small rounded-3 mb-2.5 d-flex align-items-center gap-2';
                alertBox.innerHTML = '<i class="fa-solid fa-triangle-exclamation text-danger"></i> ' + err.message;
            }
        });
    }

    function syncExpenseRowDom(idx, itm) {
        const exp = (window._pageExpenses && window._pageExpenses[idx]) || {};
        const rowId = exp.id ?? idx;
        const tr = document.getElementById('page-expense-row-' + rowId)
            || document.querySelector(`tr.expense-row[data-idx="${idx}"]`);
        if (!tr) return;

        const baseVal = parseFloat(itm.base_value !== undefined ? itm.base_value : (exp.base_amount || exp.amount || 0)) || 0;
        const vatVal = parseFloat(itm.vat_amount !== undefined ? itm.vat_amount : (exp.vat_amount || 0)) || 0;
        const whtVal = parseFloat(itm.withholding_amount !== undefined ? itm.withholding_amount : (exp.withholding_amount || 0)) || 0;
        const whtRate = parseFloat(itm.withholding_rate !== undefined ? itm.withholding_rate : (exp.withholding_rate || 3)) || 3;
        const netVal = Math.max(0, (baseVal + vatVal) - whtVal);

        tr.setAttribute('data-base', baseVal);
        tr.setAttribute('data-vat', vatVal);
        tr.setAttribute('data-wht', whtVal);
        tr.setAttribute('data-net', netVal);

        // Update Payee
        const payeeEl = tr.querySelector('.cell-exp-payee');
        if (payeeEl && (itm.vendor_name || exp.payee)) {
            payeeEl.textContent = itm.vendor_name || exp.payee;
        }

        // Update Seller TIN Badge
        const rawTin = itm.vendor_tin || exp.seller_tin || '';
        const cleanTin = rawTin.replace(/\D/g, '');
        const tinBadge = tr.querySelector('.cell-exp-tin');
        if (tinBadge) {
            if (cleanTin.length === 10) {
                tinBadge.className = 'badge bg-success-subtle text-success border border-success-subtle font-monospace px-1.5 py-0.5 rounded-2 cell-exp-tin fw-bold';
                tinBadge.innerHTML = '<i class="fa-solid fa-circle-check text-success me-0.5"></i>' + cleanTin;
                tinBadge.title = 'ERCA Valid 10-Digit Seller TIN';
            } else if (cleanTin.length > 0) {
                tinBadge.className = 'badge bg-warning-subtle text-warning-emphasis border border-warning-subtle font-monospace px-1.5 py-0.5 rounded-2 cell-exp-tin fw-bold';
                tinBadge.innerHTML = '<i class="fa-solid fa-triangle-exclamation text-warning me-0.5"></i>' + rawTin;
                tinBadge.title = cleanTin.length + '/10 Digits - Incomplete ERCA TIN';
            } else {
                tinBadge.className = 'badge bg-danger-subtle text-danger border border-danger-subtle font-monospace px-1.5 py-0.5 rounded-2 cell-exp-tin fw-semibold';
                tinBadge.innerHTML = '<i class="fa-solid fa-circle-xmark me-0.5"></i>No TIN';
                tinBadge.title = 'Missing Seller TIN';
            }
        }

        // Update FS & MRC
        const fsSmall = tr.querySelector('.cell-exp-fs');
        const fsNo = itm.fs_no || exp.fs_no;
        if (fsSmall) {
            if (fsNo && fsNo !== '-') {
                fsSmall.innerHTML = '<i class="fa-solid fa-barcode me-0.5"></i>FS #: ' + fsNo;
                fsSmall.classList.remove('d-none');
                fsSmall.classList.add('d-block');
            } else {
                fsSmall.classList.remove('d-block');
                fsSmall.classList.add('d-none');
            }
        }

        const mrcSmall = tr.querySelector('.cell-exp-mrc');
        const mrcNo = itm.mrc_no || exp.mrc_no;
        if (mrcSmall) {
            if (mrcNo && mrcNo !== '-') {
                mrcSmall.textContent = 'MRC: ' + mrcNo;
                mrcSmall.classList.remove('d-none');
                mrcSmall.classList.add('d-block');
            } else {
                mrcSmall.classList.remove('d-block');
                mrcSmall.classList.add('d-none');
            }
        }

        // Update Base and VAT Columns
        const baseCell = tr.querySelector('.cell-exp-base');
        if (baseCell) {
            baseCell.textContent = 'ETB ' + baseVal.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        }
        const vatCell = tr.querySelector('.cell-exp-vat');
        if (vatCell) {
            vatCell.textContent = 'ETB ' + vatVal.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        }

        // Update Withholding Column
        const whtCell = tr.querySelector('.cell-exp-wht');
        if (whtCell) {
            if (whtVal > 0) {
                whtCell.innerHTML = `
                    <div class="fw-bold">- ETB ${whtVal.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</div>
                    <small class="text-danger d-block font-monospace" style="font-size:0.68rem;">
                        <i class="fa-solid fa-hand-holding-dollar me-0.5"></i>${whtRate.toFixed(1)}% WHT
                    </small>
                `;
            } else {
                whtCell.innerHTML = `
                    <span class="text-muted">-</span>
                    <button type="button" class="btn btn-xs btn-outline-warning text-dark border-warning rounded-pill px-1.5 py-0.2 d-block ms-auto mt-0.5 d-print-none shadow-2xs" onclick="toggleExpenseQuickRow(${idx})" style="font-size:0.65rem;" title="Fill Withholding Tax Deduction">
                        <i class="fa-solid fa-plus me-0.5"></i>WHT
                    </button>
                `;
            }
        }

        // Update Net Disbursed Column
        const netCell = tr.querySelector('.cell-exp-net');
        if (netCell) {
            netCell.textContent = 'ETB ' + netVal.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        }

        // Update Slip links
        const slipUrl = itm.file_url || exp.attachment_url;
        if (slipUrl) {
            let slipLink = tr.querySelector('.cell-exp-slip-link');
            const attachBtn = tr.querySelector('.cell-exp-attach-btn');
            if (attachBtn) attachBtn.remove();
            if (slipLink) {
                slipLink.href = slipUrl;
            }
        }

        const whtSlipUrl = itm.withholding_receipt_url || exp.withholding_receipt_url;
        if (whtSlipUrl) {
            let whtSlipLink = tr.querySelector('.cell-exp-wht-slip-link');
            if (whtSlipLink) {
                whtSlipLink.href = whtSlipUrl;
            } else {
                const actionsContainer = tr.querySelector('td:last-child div');
                if (actionsContainer) {
                    const newLink = document.createElement('a');
                    newLink.href = whtSlipUrl;
                    newLink.target = '_blank';
                    newLink.className = 'btn btn-xs btn-outline-danger rounded-pill px-2 py-0.5 d-inline-flex align-items-center gap-1 shadow-2xs cell-exp-wht-slip-link';
                    newLink.style.fontSize = '0.68rem';
                    newLink.title = 'View Attached Withholding Tax (WHT) Receipt / Certificate';
                    newLink.innerHTML = '<i class="fa-solid fa-file-invoice-dollar text-danger"></i><span>WHT Slip</span>';
                    actionsContainer.appendChild(newLink);
                }
            }
        }

        // Update Verification without Receipt & Has Receipt attributes
        if (itm.is_verified_without_receipt !== undefined) {
            const isVer = !!itm.is_verified_without_receipt;
            tr.setAttribute('data-verified-no-receipt', isVer ? '1' : '0');
            if (isVer) {
                tr.setAttribute('data-purchase-category', 'tas');
            }

            // Update badge in Column 3
            const badgeContainer = tr.querySelector('td:nth-child(3) .d-flex');
            if (badgeContainer) {
                let verBadge = badgeContainer.querySelector('.cell-exp-verified-no-rec-badge');
                let missBadge = badgeContainer.querySelector('.cell-exp-missing-rec-badge');
                if (isVer) {
                    if (missBadge) missBadge.remove();
                    if (!verBadge) {
                        verBadge = document.createElement('span');
                        verBadge.className = 'badge bg-info-subtle text-info border border-info-subtle rounded-pill font-monospace cell-exp-verified-no-rec-badge';
                        verBadge.style.fontSize = '0.65rem';
                        verBadge.innerHTML = '<i class="fa-solid fa-shield-check me-0.5"></i>Verified (No Receipt)';
                        badgeContainer.appendChild(verBadge);
                    }
                } else {
                    if (verBadge) verBadge.remove();
                    const hasRec = tr.getAttribute('data-has-receipt') === '1';
                    if (!hasRec && !missBadge) {
                        missBadge = document.createElement('span');
                        missBadge.className = 'badge bg-danger-subtle text-danger border border-danger-subtle rounded-pill font-monospace cell-exp-missing-rec-badge';
                        missBadge.style.fontSize = '0.65rem';
                        missBadge.innerHTML = '<i class="fa-solid fa-triangle-exclamation me-0.5"></i>Missing Receipt';
                        badgeContainer.appendChild(missBadge);
                    }
                }
            }

            // Update Action Button in Column 11
            const verBtn = tr.querySelector('.btn-verify-no-receipt');
            if (verBtn) {
                if (isVer) {
                    verBtn.className = 'btn btn-xs btn-info text-white rounded-pill px-2 py-0.5 d-inline-flex align-items-center gap-1 shadow-2xs btn-verify-no-receipt';
                    verBtn.title = 'Verified without fiscal receipt (Click to toggle)';
                    verBtn.innerHTML = '<i class="fa-solid fa-shield-check"></i><span class="verify-btn-text">Verified (No Rec)</span>';
                    verBtn.setAttribute('onclick', `toggleVerifyWithoutReceipt('${exp.id ?? idx}', '${exp.request_number ?? ''}', ${idx}, 1)`);
                } else {
                    verBtn.className = 'btn btn-xs btn-outline-warning text-dark rounded-pill px-2 py-0.5 d-inline-flex align-items-center gap-1 shadow-2xs btn-verify-no-receipt';
                    verBtn.title = 'Verify expense without fiscal cash receipt (Operational 0% VAT)';
                    verBtn.innerHTML = '<i class="fa-solid fa-shield-halved text-warning"></i><span class="verify-btn-text">Verify No Rec</span>';
                    verBtn.setAttribute('onclick', `toggleVerifyWithoutReceipt('${exp.id ?? idx}', '${exp.request_number ?? ''}', ${idx}, 0)`);
                }
            }
        }

        // Flash green
        tr.style.transition = 'background-color 0.4s ease';
        tr.style.backgroundColor = '#d1e7dd';
        setTimeout(() => { tr.style.backgroundColor = ''; }, 2000);
    }

    function recomputeExpenseTableFooters() {
        let sumBase = 0, sumVat = 0, sumWht = 0, sumNet = 0;
        const allRows = document.querySelectorAll('#pageExpenseTable tbody tr.expense-row');
        allRows.forEach(r => {
            sumBase += parseFloat(r.getAttribute('data-base') || 0);
            sumVat  += parseFloat(r.getAttribute('data-vat') || 0);
            sumWht  += parseFloat(r.getAttribute('data-wht') || 0);
            sumNet  += parseFloat(r.getAttribute('data-net') || 0);
        });

        const fBase = document.getElementById('pageExpenseFootBase');
        const fVat  = document.getElementById('pageExpenseFootVat');
        const fWht  = document.getElementById('pageExpenseFootWht');
        const fNet  = document.getElementById('pageExpenseFootNet');

        const fmt = (n) => 'ETB ' + n.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        if (fBase) fBase.textContent = fmt(sumBase);
        if (fVat)  fVat.textContent  = fmt(sumVat);
        if (fWht)  fWht.textContent  = '- ' + fmt(sumWht);
        if (fNet)  fNet.textContent  = fmt(sumNet);
    }

    function syncMatchingTasRowDom(itm) {
        if (!itm) return;
        const tasRows = document.querySelectorAll('#pageTasTable tbody tr.tas-row');
        tasRows.forEach(tasTr => {
            const rowRcptId = tasTr.getAttribute('data-receipt-id');
            const rowFs = tasTr.getAttribute('data-fs');
            const rowExpId = tasTr.getAttribute('data-expense-id');

            const matchById = rowRcptId && (rowRcptId == itm.id || rowRcptId == itm.receipt_id || rowRcptId == ('REC-' + itm.receipt_id));
            const matchByFs = rowFs && itm.fs_no && rowFs === itm.fs_no;
            const matchByExp = rowExpId && (itm.expense_request_id && rowExpId == itm.expense_request_id);

            if (matchById || matchByFs || matchByExp) {
                const fsBadge = tasTr.querySelector('.cell-tas-fs');
                const tinBadge = tasTr.querySelector('.cell-tas-tin');
                const sellerName = tasTr.querySelector('.cell-tas-vendor');
                if (fsBadge && itm.fs_no && itm.fs_no !== '-') fsBadge.textContent = itm.fs_no;
                if (sellerName && itm.vendor_name) sellerName.textContent = itm.vendor_name;

                const rawTin = itm.vendor_tin || '';
                const cleanTinDigits = rawTin.replace(/\D/g, '');
                if (cleanTinDigits.length === 10) {
                    tasTr.setAttribute('data-has-tin', '1');
                    if (tinBadge) {
                        tinBadge.className = 'badge bg-success-subtle text-success border border-success-subtle font-monospace px-2 py-0.5 rounded-2 cell-tas-tin fw-bold';
                        tinBadge.innerHTML = '<i class="fa-solid fa-circle-check text-success me-1"></i>' + cleanTinDigits;
                    }
                }
            }
        });
    }

    // Validation functions
    function validateMovementTin(val) {
        const clean = (val || '').replace(/\D/g, '');
        const badge = document.getElementById('movement_edit_tin_badge');
        const hint = document.getElementById('movement_edit_tin_hint');
        if (!badge) return;

        if (clean.length === 10) {
            badge.innerHTML = '<i class="fa-solid fa-circle-check text-success"></i>';
            if (hint) hint.innerHTML = '<span class="text-success"><i class="fa-solid fa-check me-1"></i>Valid 10-Digit ERCA TIN</span>';
        } else if (clean.length === 0) {
            badge.innerHTML = '<i class="fa-solid fa-minus text-muted"></i>';
            if (hint) hint.innerText = 'ERCA requires 10 digits';
        } else {
            badge.innerHTML = '<i class="fa-solid fa-triangle-exclamation text-warning"></i>';
            if (hint) hint.innerHTML = `<span class="text-warning">${clean.length}/10 digits</span>`;
        }
    }

    function validateMovementFs(val) {
        const clean = (val || '').replace(/\D/g, '');
        const badge = document.getElementById('movement_edit_fs_badge');
        const hint = document.getElementById('movement_edit_fs_hint');
        if (!badge) return;

        if (clean.length === 8) {
            badge.innerHTML = '<i class="fa-solid fa-circle-check text-success"></i>';
            if (hint) hint.innerHTML = '<span class="text-success"><i class="fa-solid fa-check me-1"></i>Valid 8-Digit FS #</span>';
        } else if (clean.length === 0) {
            badge.innerHTML = '<i class="fa-solid fa-minus text-muted"></i>';
            if (hint) hint.innerText = 'Standard 8-digit fiscal slip';
        } else {
            badge.innerHTML = '<i class="fa-solid fa-info-circle text-info"></i>';
            if (hint) hint.innerHTML = `<span class="text-info">${clean.length} digits</span>`;
        }
    }

    // Simple Ethiopian Date conversion for date input preview
    function syncMovementDateToEc(ymd) {
        const preview = document.getElementById('movement_edit_date_ec_preview');
        if (!preview) return;
        if (!ymd) {
            preview.textContent = '';
            return;
        }

        try {
            const parts = ymd.split('-');
            const year = parseInt(parts[0], 10);
            const month = parseInt(parts[1], 10);
            const day = parseInt(parts[2], 10);

            const a = Math.floor((14 - month) / 12);
            const y = year + 4800 - a;
            const m = month + 12 * a - 3;
            const jdn = day + Math.floor((153 * m + 2) / 5) + 365 * y + Math.floor(y / 4) - Math.floor(y / 100) + Math.floor(y / 400) - 32045;

            const ethiopianEpoch = 1723856;
            const r = (jdn - ethiopianEpoch) % 1461;
            const n = (r % 365) + 365 * Math.floor(r / 1460);

            const ethYear = 4 * Math.floor((jdn - ethiopianEpoch) / 1461) + Math.floor(r / 365) - Math.floor(r / 1460);
            const ethMonth = Math.floor(n / 30) + 1;
            const ethDay = (n % 30) + 1;

            const dPad = String(ethDay).padStart(2, '0');
            const mPad = String(ethMonth).padStart(2, '0');
            preview.textContent = `EC: ${dPad}/${mPad}/${ethYear} EC`;
        } catch (e) {
            preview.textContent = '';
        }
    }

    // Financial live calculations
    function recalcMovementFromBase(val) {
        const base = parseFloat(val) || 0;
        const vatCat = document.getElementById('movement_edit_vat_category');
        const isZeroVat = vatCat && vatCat.value === 'E';

        const vat = isZeroVat ? 0 : Math.round(base * 0.15 * 100) / 100;
        const total = Math.round((base + vat) * 100) / 100;

        const vatInput = document.getElementById('movement_edit_vat_amount');
        const totalInput = document.getElementById('movement_edit_value_after_vat');
        if (vatInput) vatInput.value = vat.toFixed(2);
        if (totalInput) totalInput.value = total.toFixed(2);
        recalcMovementWht();
    }

    function recalcMovementFromVat(val) {
        const vat = parseFloat(val) || 0;
        const base = Math.round((vat / 0.15) * 100) / 100;
        const total = Math.round((base + vat) * 100) / 100;

        const baseInput = document.getElementById('movement_edit_total_value');
        const totalInput = document.getElementById('movement_edit_value_after_vat');
        if (baseInput) baseInput.value = base.toFixed(2);
        if (totalInput) totalInput.value = total.toFixed(2);
        recalcMovementWht();
    }

    function recalcMovementFromTotal(val) {
        const total = parseFloat(val) || 0;
        const vatCat = document.getElementById('movement_edit_vat_category');
        const isZeroVat = vatCat && vatCat.value === 'E';

        const base = isZeroVat ? total : Math.round((total / 1.15) * 100) / 100;
        const vat = isZeroVat ? 0 : Math.round((total - base) * 100) / 100;

        const baseInput = document.getElementById('movement_edit_total_value');
        const vatInput = document.getElementById('movement_edit_vat_amount');
        if (baseInput) baseInput.value = base.toFixed(2);
        if (vatInput) vatInput.value = vat.toFixed(2);
        recalcMovementWht();
    }

    function autoCalcMovementVatFromBase() {
        const vatCat = document.getElementById('movement_edit_vat_category');
        if (vatCat && vatCat.value === 'E') {
            vatCat.value = 'G';
        }
        const baseInput = document.getElementById('movement_edit_total_value');
        if (baseInput) recalcMovementFromBase(baseInput.value);
    }

    function setMovementZeroVat() {
        const baseInput = document.getElementById('movement_edit_total_value');
        const vatInput = document.getElementById('movement_edit_vat_amount');
        const totalInput = document.getElementById('movement_edit_value_after_vat');
        const vatCat = document.getElementById('movement_edit_vat_category');
        const purcType = document.getElementById('movement_edit_purchase_type');

        const base = parseFloat(baseInput?.value || 0);
        const total = parseFloat(totalInput?.value || 0);
        const amount = base > 0 ? base : total;

        if (baseInput) baseInput.value = amount.toFixed(2);
        if (vatInput) vatInput.value = '0.00';
        if (totalInput) totalInput.value = amount.toFixed(2);
        if (vatCat) vatCat.value = 'E';
        if (purcType && (purcType.value === '3' || !purcType.value)) {
            purcType.value = 'PUR_GD';
        }
        recalcMovementWht();
    }

    function autoExtractMovementBaseFromTotal() {
        const totalInput = document.getElementById('movement_edit_value_after_vat');
        if (totalInput) recalcMovementFromTotal(totalInput.value);
    }

    // Save edits via AJAX
    function saveMovementOcrSlipEdits(e) {
        if (e && typeof e.preventDefault === 'function') e.preventDefault();
        const form = document.getElementById('movementOcrModalEditForm');
        if (!form) return;

        const submitBtn = document.getElementById('movement_edit_submit_btn');
        const alertBox = document.getElementById('movementOcrModalAlert');
        const origBtnHtml = submitBtn ? submitBtn.innerHTML : '';

        // Client-side validation: Supplier Name is mandatory
        const sellerInput = document.getElementById('movement_edit_seller_name');
        if (sellerInput && !sellerInput.value.trim()) {
            sellerInput.focus();
            if (alertBox) {
                alertBox.className = 'alert alert-warning py-2 px-3 small rounded-3 mb-3 d-flex align-items-center gap-2';
                alertBox.innerHTML = '<i class="fa-solid fa-triangle-exclamation text-warning fs-6"></i> Please enter Supplier / Merchant Name before saving.';
                alertBox.classList.remove('d-none');
            }
            return;
        }

        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-1.5"></i>Updating System...';
        }
        if (alertBox) {
            alertBox.className = 'alert alert-info py-2 px-3 small rounded-3 mb-3 d-flex align-items-center gap-2';
            alertBox.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Saving changes and reconciling system tax records...';
            alertBox.classList.remove('d-none');
        }

        const formData = new FormData(form);
        formData.set('is_verified_without_receipt', form.elements['is_verified_without_receipt']?.checked ? '1' : '0');

        fetch('{{ route("finance.tax-deductions.update-ocr-item") }}', {
            method: 'POST',
            body: formData,
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(async res => {
            const isJson = (res.headers.get('content-type') || '').includes('application/json');
            const data = isJson ? await res.json() : { success: false, message: await res.text() };
            if (!res.ok) {
                if (res.status === 419) {
                    throw new Error('Your session expired. Please refresh the page and try again.');
                }
                let errMsg = data.message || ('Server returned HTTP ' + res.status);
                if (data.errors && typeof data.errors === 'object') {
                    const errLines = Object.values(data.errors).flat().join('<br>');
                    if (errLines) errMsg = errLines;
                }
                throw new Error(errMsg);
            }
            return data;
        })
        .then(data => {
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.innerHTML = origBtnHtml;
            }

            if (data.success && data.item) {
                window._hasMovementOcrEditsSaved = true;
                const itm = data.item;

                if (alertBox) {
                    alertBox.className = 'alert alert-success py-2 px-3 small rounded-3 mb-3 d-flex align-items-center justify-content-between gap-2';
                    alertBox.innerHTML = `
                        <div class="d-flex align-items-center gap-2">
                            <i class="fa-solid fa-circle-check fs-6 text-success"></i>
                            <div>
                                <span class="fw-semibold">${data.message || 'Invoice successfully updated & reconciled with system.'}</span>
                                <small class="d-block text-success-emphasis">All ledger records, e-Tax tables and fiscal numbers are synchronized.</small>
                            </div>
                        </div>
                        <button type="button" class="btn btn-xs btn-outline-success rounded-pill px-2.5 py-1 fw-semibold shadow-xs" onclick="reloadMovementsView()">
                            <i class="fa-solid fa-rotate me-1"></i>Refresh View
                        </button>
                    `;
                }

                // Update cache
                const cacheIdx = (window._pageOcrItems || []).findIndex(it => it.id == itm.id || (itm.receipt_id && it.receipt_id == itm.receipt_id));
                if (cacheIdx !== -1) {
                    window._pageOcrItems[cacheIdx] = Object.assign(window._pageOcrItems[cacheIdx], itm);
                }

                // If receipt slip was uploaded, update preview in modal
                if (itm.file_url) {
                    _setPageSlipPreview(itm.file_url);
                }
                const pathInput = document.getElementById('movement_edit_receipt_file_path');
                if (pathInput && itm.file_path) {
                    pathInput.value = itm.file_path;
                }
                // Hide upload progress banner now that changes are reconciled & saved
                const progBox = document.getElementById('movementUploadProgress');
                if (progBox) progBox.className = 'alert alert-info py-2 px-3 small rounded-3 mb-2 d-none align-items-center justify-content-between flex-shrink-0';

                // Update table row in Scanned OCR Receipts DOM
                const submittedId = form.elements['id'] ? form.elements['id'].value : null;
                const submittedReceiptId = form.elements['receipt_id'] ? form.elements['receipt_id'].value : null;
                const tr = document.getElementById('page-ocr-row-' + itm.id) 
                    || (itm.receipt_id ? document.querySelector(`tr[data-receipt-id="${itm.receipt_id}"]`) : null)
                    || (submittedId ? document.getElementById('page-ocr-row-' + submittedId) : null);

                if (tr) {
                    tr.id = 'page-ocr-row-' + itm.id;
                    tr.setAttribute('data-id', itm.id);
                    if (itm.receipt_id) tr.setAttribute('data-receipt-id', itm.receipt_id);

                    const cellDateEc = tr.querySelector('.cell-date-ec');
                    if (cellDateEc) cellDateEc.textContent = itm.receipt_date_ec || '—';

                    const cellDateGc = tr.querySelector('.cell-date-gc');
                    if (cellDateGc) cellDateGc.textContent = itm.receipt_date_ymd || '—';

                    const cellFs = tr.querySelector('.cell-fs');
                    if (cellFs) cellFs.textContent = itm.fs_no || '—';

                    const cellMrc = tr.querySelector('.cell-mrc');
                    if (cellMrc) {
                        if (itm.mrc_no && itm.mrc_no !== '-') {
                            cellMrc.textContent = 'MRC: ' + itm.mrc_no;
                            cellMrc.style.display = '';
                        } else {
                            cellMrc.style.display = 'none';
                        }
                    }

                    const cellVendor = tr.querySelector('.cell-vendor');
                    if (cellVendor) cellVendor.textContent = itm.vendor_name || '—';

                    const cellTin = tr.querySelector('.cell-tin');
                    if (cellTin) cellTin.textContent = 'TIN: ' + (itm.vendor_tin || '—');

                    const cellProject = tr.querySelector('.cell-project');
                    if (cellProject) cellProject.textContent = itm.project_name || 'General';

                    const cellCategory = tr.querySelector('.cell-category');
                    if (cellCategory) cellCategory.textContent = itm.category || '';

                    const cellDesc = tr.querySelector('.cell-desc');
                    if (cellDesc) cellDesc.textContent = itm.item_description || '—';

                    const cellBase = tr.querySelector('.cell-base');
                    if (cellBase) cellBase.textContent = 'ETB ' + parseFloat(itm.base_value || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

                    const cellVat = tr.querySelector('.cell-vat');
                    if (cellVat) {
                        if (parseFloat(itm.vat_amount || 0) > 0) {
                            cellVat.className = 'cell-vat text-success fw-semibold';
                            cellVat.textContent = 'ETB ' + parseFloat(itm.vat_amount || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                        } else {
                            cellVat.className = 'badge bg-light text-muted border font-monospace';
                            cellVat.textContent = '0% VAT (TAS)';
                        }
                    }

                    const cellTotal = tr.querySelector('.cell-total');
                    if (cellTotal) cellTotal.textContent = 'ETB ' + parseFloat(itm.total_value || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

                    tr.style.transition = 'background-color 0.4s ease';
                    tr.style.backgroundColor = '#d1e7dd';
                    setTimeout(() => { tr.style.backgroundColor = ''; }, 2000);
                }

                // Update matching rows in TAS Purchases Table (#pageTasTable)
                const tasRows = document.querySelectorAll('#pageTasTable tbody tr.tas-row');
                tasRows.forEach(tasTr => {
                    const rowRcptId = tasTr.getAttribute('data-receipt-id');
                    const rowFs = tasTr.getAttribute('data-fs');
                    const rowExpId = tasTr.getAttribute('data-expense-id');

                    const matchById = rowRcptId && (
                        rowRcptId == itm.id || 
                        rowRcptId == itm.receipt_id || 
                        rowRcptId == ('REC-' + itm.receipt_id) || 
                        (submittedReceiptId && (rowRcptId == submittedReceiptId || ('REC-' + rowRcptId) == submittedReceiptId)) ||
                        (submittedId && rowRcptId == submittedId)
                    );
                    const matchByFs = rowFs && itm.fs_no && rowFs === itm.fs_no;
                    const matchByExp = rowExpId && (
                        (submittedReceiptId && (submittedReceiptId == ('EXP-' + rowExpId) || submittedReceiptId == rowExpId)) ||
                        (submittedId && (submittedId == ('EXP-' + rowExpId) || submittedId == rowExpId)) ||
                        (itm.expense_request_id && rowExpId == itm.expense_request_id)
                    );

                    if (matchById || matchByFs || matchByExp) {
                        const fsBadge = tasTr.querySelector('.cell-tas-fs');
                        const tinBadge = tasTr.querySelector('.cell-tas-tin');
                        const sellerName = tasTr.querySelector('.cell-tas-vendor');
                        const amtCell = tasTr.querySelector('.cell-tas-amount');
                        const mrcBadge = tasTr.querySelector('.cell-tas-mrc');
                        const dateEc = tasTr.querySelector('.cell-tas-date-ec');
                        const tinBtn = tasTr.querySelector('.cell-tas-tin-btn');
                        const actionBtn = tasTr.querySelector('.cell-tas-action-btn');

                        if (fsBadge && itm.fs_no && itm.fs_no !== '-') fsBadge.textContent = itm.fs_no;
                        if (sellerName && itm.vendor_name) sellerName.textContent = itm.vendor_name;
                        if (amtCell && itm.total_value !== undefined) amtCell.textContent = 'ETB ' + parseFloat(itm.total_value).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                        if (mrcBadge && itm.mrc_no && itm.mrc_no !== '-') mrcBadge.textContent = itm.mrc_no;
                        if (dateEc && itm.receipt_date_ec) dateEc.textContent = itm.receipt_date_ec;

                        const rawTin = itm.vendor_tin || '';
                        const cleanTinDigits = rawTin.replace(/\D/g, '');
                        if (cleanTinDigits.length === 10) {
                            tasTr.setAttribute('data-has-tin', '1');
                            if (tinBadge) {
                                tinBadge.className = 'badge bg-success-subtle text-success border border-success-subtle font-monospace px-2 py-0.5 rounded-2 cell-tas-tin fw-bold';
                                tinBadge.innerHTML = '<i class="fa-solid fa-circle-check text-success me-1"></i>' + cleanTinDigits;
                                tinBadge.title = 'ERCA Valid 10-Digit Seller TIN';
                            }
                            if (tinBtn) {
                                tinBtn.className = 'btn btn-xs btn-link text-muted p-0 d-inline-flex align-items-center gap-1 fw-semibold d-print-none cell-tas-tin-btn';
                                tinBtn.innerHTML = '<i class="fa-solid fa-pen-to-square"></i>';
                                tinBtn.title = 'Edit Seller TIN / Slip';
                            }
                            if (actionBtn) {
                                actionBtn.className = 'btn btn-xs btn-outline-secondary rounded-pill px-2.5 py-1 d-inline-flex align-items-center gap-1 fw-semibold cell-tas-action-btn';
                                actionBtn.innerHTML = '<i class="fa-solid fa-pencil"></i><span>Edit</span>';
                            }
                        } else if (cleanTinDigits.length > 0) {
                            tasTr.setAttribute('data-has-tin', '0');
                            if (tinBadge) {
                                tinBadge.className = 'badge bg-warning-subtle text-warning-emphasis border border-warning-subtle font-monospace px-2 py-0.5 rounded-2 cell-tas-tin fw-bold';
                                tinBadge.innerHTML = '<i class="fa-solid fa-triangle-exclamation text-warning me-1"></i>' + rawTin;
                                tinBadge.title = cleanTinDigits.length + '/10 Digits - Incomplete ERCA TIN';
                            }
                        } else {
                            tasTr.setAttribute('data-has-tin', '0');
                            if (tinBadge) {
                                tinBadge.className = 'badge bg-danger-subtle text-danger border border-danger-subtle font-monospace px-2 py-0.5 rounded-2 cell-tas-tin fw-semibold';
                                tinBadge.innerHTML = '<i class="fa-solid fa-circle-xmark me-1"></i>No Seller TIN';
                                tinBadge.title = 'Missing Seller TIN';
                            }
                        }

                        tasTr.style.transition = 'background-color 0.4s ease';
                        tasTr.style.backgroundColor = '#d1e7dd';
                        setTimeout(() => { tasTr.style.backgroundColor = ''; }, 2000);
                    }
                });

                // Update cached TAS purchase items
                if (window._pageTasPurchases && window._pageTasPurchases.length > 0) {
                    window._pageTasPurchases.forEach(tp => {
                        const matchExp = tp.id && (submittedReceiptId == ('EXP-' + tp.id) || submittedId == ('EXP-' + tp.id) || (itm.expense_request_id && tp.id == itm.expense_request_id));
                        const matchRcpt = tp.receipt_id && (tp.receipt_id == itm.receipt_id || tp.receipt_id == itm.id || tp.receipt_id == submittedReceiptId);
                        if (matchExp || matchRcpt) {
                            if (itm.vendor_tin) tp.seller_tin = itm.vendor_tin;
                            if (itm.vendor_name) tp.payee = itm.vendor_name;
                            if (itm.fs_no) tp.fs_no = itm.fs_no;
                            if (itm.mrc_no) tp.mrc_no = itm.mrc_no;
                        }
                    });
                }

                // Recompute TAS TIN compliance banner & filter counts
                let liveWithTin = 0;
                let liveMissingTin = 0;
                const allTasDomRows = document.querySelectorAll('#pageTasTable tbody tr.tas-row');
                allTasDomRows.forEach(r => {
                    if (r.getAttribute('data-has-tin') === '1') {
                        liveWithTin++;
                    } else {
                        liveMissingTin++;
                    }
                });
                const totalTasCount = allTasDomRows.length;
                const pct = totalTasCount > 0 ? Math.round((liveWithTin / totalTasCount) * 100) : 0;

                const cWith = document.getElementById('tasCountWithTin');
                if (cWith) cWith.textContent = liveWithTin;
                const cMissing = document.getElementById('tasCountMissingTin');
                if (cMissing) cMissing.textContent = liveMissingTin;
                const sumWith = document.getElementById('summaryTasWithTinCount');
                if (sumWith) sumWith.textContent = liveWithTin;
                const sumBadge = document.getElementById('summaryTasTinBadge');
                if (sumBadge) {
                    sumBadge.className = 'badge ' + (liveMissingTin === 0 ? 'bg-success' : 'bg-warning text-dark') + ' rounded-pill';
                    sumBadge.textContent = liveMissingTin === 0 ? '100% Filled' : (liveMissingTin + ' Need TIN');
                }
                const sumPct = document.getElementById('summaryTasTinPercent');
                if (sumPct) sumPct.textContent = '(' + pct + '%)';

                // Update matching rows in Expenses Table (#pageExpenseTable)
                if (window._pageExpenses && window._pageExpenses.length > 0) {
                    window._pageExpenses.forEach((exp, eIdx) => {
                        const matchExp = exp.id && (submittedReceiptId == ('EXP-' + exp.id) || submittedId == ('EXP-' + exp.id) || (itm.expense_request_id && exp.id == itm.expense_request_id));
                        const matchRcpt = exp.receipt_id && (exp.receipt_id == itm.receipt_id || exp.receipt_id == itm.id || exp.receipt_id == submittedReceiptId);
                        const matchFs = exp.fs_no && itm.fs_no && exp.fs_no === itm.fs_no;
                        if (matchExp || matchRcpt || matchFs) {
                            if (itm.is_verified_without_receipt) {
                                const rowToRemove = document.getElementById('page-expense-row-' + (exp.id ?? eIdx))
                                    || document.querySelector(`tr.expense-row[data-idx="${eIdx}"]`);
                                const qRow = document.getElementById('expense-quick-row-' + eIdx);
                                if (qRow) qRow.remove();
                                if (rowToRemove) {
                                    rowToRemove.remove();
                                }
                                const tabBadge = document.querySelector('#page-expense-tab .badge');
                                if (tabBadge) {
                                    const cur = parseInt(tabBadge.textContent.trim(), 10) || 0;
                                    if (cur > 0) tabBadge.textContent = cur - 1;
                                }
                                const archCountSpan = document.getElementById('filterCountVerifiedNoReceipt');
                                if (archCountSpan) {
                                    const curArch = parseInt(archCountSpan.textContent.trim(), 10) || 0;
                                    archCountSpan.textContent = curArch + 1;
                                }
                            } else {
                                Object.assign(exp, {
                                    payee: itm.vendor_name || exp.payee,
                                    seller_tin: itm.vendor_tin || exp.seller_tin,
                                    fs_no: itm.fs_no || exp.fs_no,
                                    mrc_no: itm.mrc_no || exp.mrc_no,
                                    base_amount: itm.base_value !== undefined ? itm.base_value : exp.base_amount,
                                    vat_amount: itm.vat_amount !== undefined ? itm.vat_amount : exp.vat_amount,
                                    has_withholding: !!itm.has_withholding,
                                    withholding_rate: itm.withholding_rate,
                                    withholding_amount: itm.withholding_amount,
                                    withholding_receipt_number: itm.withholding_receipt_number,
                                    withholding_receipt_url: itm.withholding_receipt_url,
                                    net_disbursed: itm.net_disbursed,
                                    attachment_url: itm.file_url || exp.attachment_url
                                });
                                syncExpenseRowDom(eIdx, itm);
                            }
                        }
                    });
                    recomputeExpenseTableFooters();
                    updateFilterCounters();
                } else {
                    const expRows = document.querySelectorAll('#pageExpenseTable tbody tr');
                    expRows.forEach(expTr => {
                        const rowRcptId = expTr.getAttribute('data-receipt-id');
                        const rowFs = expTr.getAttribute('data-fs');
                        const matchById = rowRcptId && (rowRcptId == itm.id || rowRcptId == itm.receipt_id || rowRcptId == ('REC-' + itm.receipt_id));
                        const matchByFs = rowFs && itm.fs_no && rowFs === itm.fs_no;

                        if (matchById || matchByFs) {
                            const payeeSpan = expTr.querySelector('td:nth-child(4) .fw-semibold');
                            const tinSmall = expTr.querySelector('td:nth-child(4) small');
                            const fsSmall = expTr.querySelector('td:nth-child(3) .text-primary');
                            const baseCell = expTr.querySelector('td:nth-child(7)');
                            const vatCell = expTr.querySelector('td:nth-child(8)');
                            const netCell = expTr.querySelector('td:nth-child(10)');

                            if (payeeSpan && itm.vendor_name) payeeSpan.textContent = itm.vendor_name;
                            if (tinSmall && itm.vendor_tin) tinSmall.textContent = 'TIN: ' + itm.vendor_tin;
                            if (fsSmall && itm.fs_no && itm.fs_no !== '-') fsSmall.innerHTML = '<i class="fa-solid fa-barcode me-0.5"></i>FS #: ' + itm.fs_no;
                            if (baseCell && itm.base_value !== undefined) baseCell.textContent = 'ETB ' + parseFloat(itm.base_value).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                            if (vatCell && itm.vat_amount !== undefined) vatCell.textContent = 'ETB ' + parseFloat(itm.vat_amount).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                            if (netCell && itm.total_value !== undefined) netCell.textContent = 'ETB ' + parseFloat(itm.total_value).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

                            expTr.style.transition = 'background-color 0.4s ease';
                            expTr.style.backgroundColor = '#d1e7dd';
                            setTimeout(() => { expTr.style.backgroundColor = ''; }, 2000);
                        }
                    });
                }

                // Attach modal auto-refresh on close if edits were saved
                const modalEl = document.getElementById('movementSlipModal');
                if (modalEl && !modalEl._hasReloadOnHideListener) {
                    modalEl._hasReloadOnHideListener = true;
                    modalEl.addEventListener('hidden.bs.modal', function () {
                        if (window._hasMovementOcrEditsSaved) {
                            reloadMovementsView();
                        }
                    });
                }

                // Recompute totals in table footer
                let totalBase = 0, totalVat = 0, totalAfter = 0;
                (window._pageOcrItems || []).forEach(it => {
                    totalBase += parseFloat(it.base_value || 0);
                    totalVat += parseFloat(it.vat_amount || 0);
                    totalAfter += parseFloat(it.value_after_vat || it.total_value || 0);
                });
                const sumBaseEl = document.getElementById('pageOcrSumBase');
                const sumVatEl = document.getElementById('pageOcrSumVat');
                const sumAfterEl = document.getElementById('pageOcrSumAfter');
                if (sumBaseEl) sumBaseEl.textContent = 'ETB ' + totalBase.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                if (sumVatEl) sumVatEl.textContent = 'ETB ' + totalVat.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                if (sumAfterEl) sumAfterEl.textContent = 'ETB ' + totalAfter.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            } else {
                if (alertBox) {
                    alertBox.className = 'alert alert-danger py-2 px-3 small rounded-3 mb-3 d-flex align-items-center gap-2';
                    alertBox.innerHTML = '<i class="fa-solid fa-triangle-exclamation text-danger fs-6"></i> ' + (data.message || 'Failed to update record.');
                }
            }
        })
        .catch(err => {
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.innerHTML = origBtnHtml;
            }
            if (alertBox) {
                alertBox.className = 'alert alert-danger py-2 px-3 small rounded-3 mb-3 d-flex align-items-center gap-2';
                alertBox.innerHTML = '<i class="fa-solid fa-triangle-exclamation text-danger fs-6"></i> Error saving changes: ' + err.message;
            }
        });
    }

    function _getActiveTabName() {
        const activeBtn = document.querySelector('#movementsPageTab button.active');
        if (!activeBtn) return 'ocr';
        const target = activeBtn.getAttribute('data-bs-target') || '';
        return target.replace('#page-', '') || 'ocr';
    }

    function reloadMovementsView(forceTab) {
        const tab = forceTab || _getActiveTabName() || sessionStorage.getItem('movements_active_tab') || 'ocr';
        const url = new URL(window.location.href);
        url.searchParams.set('tab', tab);
        const ocrFilter = _activePageOcrFilter || sessionStorage.getItem('movements_ocr_category');
        if (ocrFilter) {
            url.searchParams.set('ocr_filter', ocrFilter);
        }
        window.location.href = url.toString();
    }

    // Backward compatibility helper
    function openSlipModal(url, title, isPdf = false) {
        const foundIdx = (window._pageOcrItems || []).findIndex(it => it.file_url === url);
        if (foundIdx !== -1) {
            openPageOcrInspector(foundIdx);
        } else {
            openPageOcrInspector(0);
            _setPageSlipPreview(url);
        }
    }

    document.addEventListener('DOMContentLoaded', function () {
        // Tab switching and URL/session persistence
        const tabBtns = document.querySelectorAll('#movementsPageTab button[data-bs-toggle="pill"]');
        tabBtns.forEach(btn => {
            btn.addEventListener('shown.bs.tab', function (e) {
                const target = e.target.getAttribute('data-bs-target') || '';
                const tabKey = target.replace('#page-', '') || 'income';
                try {
                    sessionStorage.setItem('movements_active_tab', tabKey);
                    const url = new URL(window.location.href);
                    url.searchParams.set('tab', tabKey);
                    window.history.replaceState({ tab: tabKey }, '', url.toString());
                    const formTabInput = document.querySelector('form input[name="tab"]');
                    if (formTabInput) formTabInput.value = tabKey;
                } catch (err) {}
            });
        });

        // Restore active tab if specified in URL or sessionStorage
        const urlParams = new URLSearchParams(window.location.search);
        const urlTab = urlParams.get('tab');
        const savedTab = urlTab || sessionStorage.getItem('movements_active_tab');
        if (savedTab) {
            const tabBtn = document.getElementById('page-' + savedTab + '-tab');
            if (tabBtn && window.bootstrap && bootstrap.Tab) {
                bootstrap.Tab.getOrCreateInstance(tabBtn).show();
            }
        }

        // Restore active OCR filter if saved
        const urlFilter = urlParams.get('ocr_filter') || sessionStorage.getItem('movements_ocr_category');
        if (urlFilter && (urlFilter === 'all' || urlFilter === 'tas' || urlFilter === 'vat')) {
            filterPageOcrCategory(urlFilter);
        }

        // Drag and drop receipt slip onto viewport
        const slipViewport = document.getElementById('movementSlipViewport');
        if (slipViewport) {
            ['dragenter', 'dragover'].forEach(evtName => {
                slipViewport.addEventListener(evtName, (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    slipViewport.style.borderColor = '#0d6efd';
                    slipViewport.style.backgroundColor = 'rgba(13, 110, 253, 0.05)';
                });
            });

            ['dragleave', 'drop'].forEach(evtName => {
                slipViewport.addEventListener(evtName, (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    slipViewport.style.borderColor = '';
                    slipViewport.style.backgroundColor = '';
                });
            });

            slipViewport.addEventListener('drop', (e) => {
                if (e.dataTransfer && e.dataTransfer.files && e.dataTransfer.files.length > 0) {
                    const droppedFile = e.dataTransfer.files[0];
                    const fileInput = document.getElementById('movementSlipFileInput');
                    if (fileInput) {
                        try {
                            const dt = new DataTransfer();
                            dt.items.add(droppedFile);
                            fileInput.files = dt.files;
                        } catch (err) {}
                    }
                    handleMovementReceiptFileSelect(droppedFile);
                }
            });
        }

        // Auto-refresh when modal closes after saving changes
        const modalEl = document.getElementById('movementSlipModal');
        if (modalEl && !modalEl._hasReloadListener) {
            modalEl._hasReloadListener = true;
            modalEl.addEventListener('hidden.bs.modal', function () {
                if (window._hasMovementOcrEditsSaved) {
                    reloadMovementsView();
                }
            });
        }
    });
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
