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
                <span>TAS Purchases (0% VAT)</span>
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
                            <button type="button" class="btn btn-sm rounded-pill px-2.5 py-1 fw-semibold btn-danger text-white shadow-xs btn-expense-filter" id="btnExpenseFilter-all" onclick="filterExpensePurchaseType('all')" title="Show All Purchases & Expenses">
                                <i class="fa-solid fa-list me-1"></i>All Purchases ({{ count($movementsData['expenses'] ?? []) }})
                            </button>
                            <button type="button" class="btn btn-sm rounded-pill px-2.5 py-1 fw-semibold text-muted btn-expense-filter" id="btnExpenseFilter-vat" onclick="filterExpensePurchaseType('vat')" title="Purchases with 15% Input VAT">
                                <i class="fa-solid fa-percent me-1"></i>VAT Purchases ({{ count($movementsData['vat_purchases'] ?? []) }})
                            </button>
                            <button type="button" class="btn btn-sm rounded-pill px-2.5 py-1 fw-semibold text-muted btn-expense-filter" id="btnExpenseFilter-tas" onclick="filterExpensePurchaseType('tas')" title="Purchases with 0% VAT (Declared in TAS)">
                                <i class="fa-solid fa-receipt me-1"></i>TAS Purchases (0% VAT) ({{ count($movementsData['tas_purchases'] ?? []) }})
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
                                    $isVatExp = !empty($exp['is_vat_purchase']) || $expVat > 0;
                                @endphp
                                <tr class="expense-row" 
                                    data-purchase-category="{{ $isVatExp ? 'vat' : 'tas' }}"
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
                                        </div>
                                        @if(!empty($exp['payment_reference']) && $exp['payment_reference'] !== '-')
                                            <small class="text-muted d-block font-monospace">Ref: {{ $exp['payment_reference'] }}</small>
                                        @endif
                                        @if(!empty($exp['fs_no']) && $exp['fs_no'] !== '-')
                                            <small class="text-primary font-monospace d-block" style="font-size:0.7rem;"><i class="fa-solid fa-barcode me-0.5"></i>FS #: {{ $exp['fs_no'] }}</small>
                                        @endif
                                        @if(!empty($exp['mrc_no']) && $exp['mrc_no'] !== '-')
                                            <small class="text-muted font-monospace d-block" style="font-size:0.68rem;">MRC: {{ $exp['mrc_no'] }}</small>
                                        @endif
                                    </td>
                                    <td>
                                        <span class="fw-semibold text-dark d-block">{{ $exp['payee'] ?? 'Vendor' }}</span>
                                        @if(!empty($exp['seller_tin']) && $exp['seller_tin'] !== '-')
                                            <small class="text-muted font-monospace d-block" style="font-size:0.72rem;">TIN: {{ $exp['seller_tin'] }}</small>
                                        @endif
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
                                    <td class="text-end pe-3">
                                        <div class="font-monospace text-dark fw-bold">ETB {{ number_format($expNet, 2) }}</div>
                                        @php
                                            $expSlip = $exp['receipt_slip_url'] ?? ($exp['attachment_url'] ?? null);
                                        @endphp
                                        @if(!empty($expSlip))
                                            <a href="{{ $expSlip }}" target="_blank" class="btn btn-xs btn-outline-info rounded-pill px-2 py-0.5 d-inline-flex align-items-center gap-1 shadow-2xs mt-1" style="font-size:0.65rem;" title="View Receipt / Voucher Slip">
                                                <i class="fa-solid fa-receipt"></i>
                                                <span>Slip</span>
                                            </a>
                                        @endif
                                    </td>
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
                                <td colspan="6" class="ps-3 py-2 text-uppercase small text-muted" id="pageExpenseFootLabel">Total Purchases &amp; Deductions</td>
                                <td class="text-end py-2 font-monospace text-dark" id="pageExpenseFootBase">ETB {{ number_format($purchasesBase, 2) }}</td>
                                <td class="text-end py-2 font-monospace text-success" id="pageExpenseFootVat">ETB {{ number_format($inputVat, 2) }}</td>
                                <td class="text-end py-2 font-monospace text-danger" id="pageExpenseFootWht">- ETB {{ number_format($wht, 2) }}</td>
                                <td class="text-end py-2 font-monospace text-dark pe-3" id="pageExpenseFootNet">ETB {{ number_format($netDisbursed, 2) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>

        {{-- ── TAB 3: TAS PURCHASES (0% VAT / e-Tax Declaration pro.doc) ──────── --}}
        @php
            $rawTasPurchases = $movementsData['tas_purchases'] ?? [];
            $tasTotalCount = count($rawTasPurchases);
            $tasWithTinCount = 0;
            $tasMissingTinCount = 0;
            foreach ($rawTasPurchases as $tpItem) {
                $cTin = preg_replace('/\D/', '', (string)($tpItem['seller_tin'] ?? ''));
                if (strlen($cTin) === 10) {
                    $tasWithTinCount++;
                } else {
                    $tasMissingTinCount++;
                }
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
                                    <i class="fa-solid fa-receipt me-1"></i>0% VAT e-Tax Declaration
                                </span>
                            </div>
                            <small class="text-muted">Purchases with 0% VAT (Fuel vouchers, exempt receipts, cash register slips) declared under Ethiopian e-Tax (pro.doc 9-column standard)</small>
                        </div>
                    </div>
                    <div class="d-flex align-items-center gap-2 d-print-none flex-wrap">
                        {{-- Quick TIN Filters --}}
                        <div class="btn-group btn-group-sm shadow-xs rounded-pill p-0.5 bg-light border me-1" role="group">
                            <button type="button" 
                                    class="btn btn-sm rounded-pill px-2.5 py-1 fw-semibold btn-primary text-white shadow-xs" 
                                    id="btnTasFilterAll" 
                                    onclick="filterTasByTin('all')" 
                                    title="Show All TAS Purchases">
                                <i class="fa-solid fa-list me-1"></i>All TAS (<span id="tasCountAll">{{ $tasTotalCount }}</span>)
                            </button>
                            <button type="button" 
                                    class="btn btn-sm rounded-pill px-2.5 py-1 fw-semibold text-muted" 
                                    id="btnTasFilterWithTin" 
                                    onclick="filterTasByTin('with_tin')" 
                                    title="Show Records With 10-Digit Seller TIN">
                                <i class="fa-solid fa-circle-check text-success me-1"></i>With Seller TIN (<span id="tasCountWithTin">{{ $tasWithTinCount }}</span>)
                            </button>
                            <button type="button" 
                                    class="btn btn-sm rounded-pill px-2.5 py-1 fw-semibold text-muted" 
                                    id="btnTasFilterMissingTin" 
                                    onclick="filterTasByTin('missing_tin')" 
                                    title="Show Records Missing Seller TIN">
                                <i class="fa-solid fa-circle-exclamation text-danger me-1"></i>Missing TIN (<span id="tasCountMissingTin">{{ $tasMissingTinCount }}</span>)
                            </button>
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

                {{-- Summary Banner with Seller TIN Status --}}
                <div class="row g-2 px-3 pt-3 pb-2 bg-light bg-opacity-50 border-bottom">
                    <div class="col-sm-6 col-md-3">
                        <div class="card border border-primary-subtle bg-primary-subtle bg-opacity-25 rounded-3 p-2.5">
                            <small class="text-muted d-block text-uppercase fw-bold" style="font-size:0.65rem;">Total TAS Purchases</small>
                            <span class="fw-bold font-monospace text-primary fs-6">{{ $tasTotalCount }} Receipts / Vouchers</span>
                        </div>
                    </div>
                    <div class="col-sm-6 col-md-3">
                        <div class="card border {{ $tasMissingTinCount === 0 ? 'border-success-subtle bg-success-subtle bg-opacity-20' : 'border-warning-subtle bg-warning-subtle bg-opacity-25' }} rounded-3 p-2.5">
                            <div class="d-flex align-items-center justify-content-between">
                                <small class="text-muted d-block text-uppercase fw-bold" style="font-size:0.65rem;">2. Seller TIN Status</small>
                                <span class="badge {{ $tasMissingTinCount === 0 ? 'bg-success' : 'bg-warning text-dark' }} rounded-pill" style="font-size:0.62rem;" id="summaryTasTinBadge">
                                    {{ $tasMissingTinCount === 0 ? '100% Filled' : $tasMissingTinCount . ' Need TIN' }}
                                </span>
                            </div>
                            <div class="d-flex align-items-baseline gap-1 mt-0.5">
                                <span class="fw-bold font-monospace text-success fs-6" id="summaryTasWithTinCount">{{ $tasWithTinCount }}</span>
                                <span class="text-muted small">/ {{ $tasTotalCount }} filled</span>
                                <span class="text-muted small ms-auto font-monospace" id="summaryTasTinPercent">({{ $tasTinCompliancePercent }}%)</span>
                            </div>
                        </div>
                    </div>
                    <div class="col-sm-6 col-md-3">
                        <div class="card border border-primary-subtle bg-primary-subtle bg-opacity-25 rounded-3 p-2.5">
                            <small class="text-muted d-block text-uppercase fw-bold" style="font-size:0.65rem;">Expenditure Amount</small>
                            <span class="fw-bold font-monospace text-dark fs-6">ETB {{ number_format($movementsData['tas_purchases_total_amount'] ?? ($movementsData['summary']['tas_purchases_base'] ?? 0), 2) }}</span>
                        </div>
                    </div>
                    <div class="col-sm-6 col-md-3">
                        <div class="card border border-secondary-subtle bg-white rounded-3 p-2.5">
                            <small class="text-muted d-block text-uppercase fw-bold" style="font-size:0.65rem;">Official e-Tax Specification</small>
                            <div class="d-flex align-items-center gap-1.5 mt-0.5 flex-wrap">
                                <span class="badge bg-secondary-subtle text-dark rounded-pill fw-semibold" style="font-size:0.68rem;">0% VAT Exempt</span>
                                <span class="badge bg-warning text-dark rounded-pill fw-bold" style="font-size:0.68rem;"><i class="fa-solid fa-file-lines me-1"></i>pro.doc 9 Cols</span>
                            </div>
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
                                <th class="py-2.5">2. Seller TIN</th>
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
                            @php
                                $tasTotalAmount = 0;
                            @endphp
                            @forelse($rawTasPurchases as $idx => $tas)
                                @php
                                    $tAmt = (float)($tas['amount'] ?? ($tas['base_amount'] ?? 0));
                                    $tasTotalAmount += $tAmt;
                                    $slipUrl = $tas['receipt_slip_url'] ?? ($tas['attachment_url'] ?? null);
                                    $isFuel = !empty($tas['is_fuel']);
                                    $isPdf = $slipUrl && str_contains(strtolower($slipUrl), '.pdf');
                                    $cleanSellerTin = preg_replace('/\D/', '', (string)($tas['seller_tin'] ?? ''));
                                    $hasValidSellerTin = strlen($cleanSellerTin) === 10;
                                    $hasPartialSellerTin = !empty($cleanSellerTin) && strlen($cleanSellerTin) !== 10;
                                @endphp
                                <tr data-has-tin="{{ $hasValidSellerTin ? '1' : '0' }}"
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
                                                <span class="badge bg-success-subtle text-success border border-success-subtle font-monospace px-2 py-0.5 rounded-2 cell-tas-tin fw-bold" title="ERCA Valid 10-Digit Seller TIN">
                                                    <i class="fa-solid fa-circle-check text-success me-1"></i>{{ $cleanSellerTin }}
                                                </span>
                                            @elseif($hasPartialSellerTin)
                                                <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle font-monospace px-2 py-0.5 rounded-2 cell-tas-tin fw-bold" title="{{ strlen($cleanSellerTin) }}/10 Digits - Incomplete ERCA TIN">
                                                    <i class="fa-solid fa-triangle-exclamation text-warning me-1"></i>{{ $tas['seller_tin'] }}
                                                </span>
                                            @else
                                                <span class="badge bg-danger-subtle text-danger border border-danger-subtle font-monospace px-2 py-0.5 rounded-2 cell-tas-tin fw-semibold" title="Missing Seller TIN">
                                                    <i class="fa-solid fa-circle-xmark me-1"></i>No Seller TIN
                                                </span>
                                            @endif

                                            @if($isFuel)
                                                <span class="badge bg-warning text-dark border border-warning-subtle rounded-pill font-monospace" style="font-size:0.62rem;"><i class="fa-solid fa-gas-pump me-0.5"></i>Fuel</span>
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
                                    <td class="text-end font-monospace fw-bold text-dark cell-tas-amount">ETB {{ number_format($tAmt, 2) }}</td>
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
                                        <div class="d-inline-flex align-items-center gap-1">
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
                                                <span class="badge bg-light text-muted border" style="font-size:0.68rem;">No Slip</span>
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
                                    <i class="fa-solid fa-filter me-2 text-secondary"></i>No TAS purchases match the selected Seller TIN filter.
                                </td>
                            </tr>
                        </tbody>
                        <tfoot class="table-light fw-bold border-top">
                            <tr>
                                <td colspan="3" class="ps-3 py-2 text-uppercase small text-muted">
                                    Total TAS Purchases (<span id="pageTasFootCount">{{ $tasTotalCount }}</span> items)
                                </td>
                                <td class="text-end py-2 font-monospace text-dark" id="pageTasFootAmount">ETB {{ number_format($tasTotalAmount, 2) }}</td>
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
                                $allOcrList = $movementsData['all_ocr_items'] ?? ($movementsData['ocr_items'] ?? []);
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
                                        <span class="badge bg-light text-primary border font-monospace cell-fs">{{ $ocr['fs_no'] ?? '-' }}</span>
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
                                </form>
                            </div>
                        </div>
                    </div>

                    {{-- RIGHT COLUMN: Receipt Slip Preview --}}
                    <div class="col-12 col-lg-6">
                        <div class="card border-0 shadow-xs rounded-3 h-100 bg-white d-flex flex-column">
                            <div class="card-header bg-white border-bottom py-2.5 px-3 d-flex align-items-center justify-content-between flex-shrink-0">
                                <div class="d-flex align-items-center gap-2">
                                    <i class="fa-solid fa-image text-info"></i>
                                    <span class="fw-bold text-dark small text-uppercase">Original Scanned Receipt Slip</span>
                                </div>
                                <div class="btn-group btn-group-sm shadow-xs" role="group">
                                    <button type="button" class="btn btn-outline-secondary btn-sm py-1 px-2" onclick="zoomPageSlip(0.15)" title="Zoom In"><i class="fa-solid fa-magnifying-glass-plus"></i></button>
                                    <button type="button" class="btn btn-outline-secondary btn-sm py-1 px-2" onclick="zoomPageSlip(-0.15)" title="Zoom Out"><i class="fa-solid fa-magnifying-glass-minus"></i></button>
                                    <button type="button" class="btn btn-outline-secondary btn-sm py-1 px-2" onclick="rotatePageSlip()" title="Rotate 90°"><i class="fa-solid fa-rotate-right"></i></button>
                                    <button type="button" class="btn btn-outline-secondary btn-sm py-1 px-2" onclick="resetPageSlip()" title="Reset View"><i class="fa-solid fa-arrows-rotate"></i></button>
                                    <a id="movementModalSlipOpenNewTab" href="#" target="_blank" class="btn btn-outline-secondary btn-sm py-1 px-2" title="Open in New Tab"><i class="fa-solid fa-up-right-from-square"></i></a>
                                </div>
                            </div>
                            <div class="card-body p-2 flex-grow-1 d-flex flex-column">
                                <div class="flex-grow-1 position-relative border rounded-3 bg-light d-flex align-items-center justify-content-center p-2" style="min-height: 420px; max-height: 520px; overflow: auto;" id="movementSlipViewport">
                                    <img id="movementSlipImg" src="" alt="Receipt Slip" class="img-fluid rounded shadow-sm" style="max-height: 480px; width: auto; object-fit: contain; transition: transform 0.2s ease;">
                                    <iframe id="movementSlipPdf" src="" class="w-100 rounded d-none" style="height: 480px; border: none;"></iframe>
                                    <div id="movementSlipEmpty" class="text-center p-4 d-none">
                                        <div class="p-3 bg-white bg-opacity-75 rounded-circle d-inline-block text-muted mb-2 shadow-xs">
                                            <i class="fa-solid fa-file-invoice text-secondary fs-2"></i>
                                        </div>
                                        <div class="fw-semibold text-muted small">No Scanned Slip File Attached</div>
                                        <div class="text-muted" style="font-size:0.75rem;">You can still edit and save all fiscal declaration fields on the left.</div>
                                    </div>
                                </div>
                            </div>
                            <div class="card-footer bg-light border-top py-2 px-3 d-flex align-items-center justify-content-between flex-shrink-0">
                                <span class="small text-muted font-monospace" id="movementSlipFilename" style="font-size:0.75rem;">Document: Receipt Slip</span>
                                <div class="d-flex gap-2">
                                    <a id="movementSlipDownloadBtn" href="#" target="_blank" download class="btn btn-outline-info btn-sm rounded-pill px-3 shadow-xs">
                                        <i class="fa-solid fa-download me-1"></i>Download
                                    </a>
                                    <a id="movementModalStudioLink" href="{{ route('admin.ocr.index') }}" target="_blank" class="btn btn-outline-primary btn-sm rounded-pill px-3">
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

{{-- ── Client Scripts & Filtering ────────────────────────────────────────────── --}}
@push('scripts')
<script>
    // Cached items for live inspector and table sync
    window._pageOcrItems = @json($movementsData['all_ocr_items'] ?? ($movementsData['ocr_items'] ?? []));
    window._pageTasPurchases = @json($movementsData['tas_purchases'] ?? []);

    // Toggle TAS Purchases by Seller TIN status: 'all', 'with_tin', 'missing_tin'
    let _activeTasTinFilter = 'all';

    function filterTasByTin(filter) {
        _activeTasTinFilter = filter;

        const btnAll = document.getElementById('btnTasFilterAll');
        const btnWith = document.getElementById('btnTasFilterWithTin');
        const btnMissing = document.getElementById('btnTasFilterMissingTin');

        if (btnAll) btnAll.className = 'btn btn-sm rounded-pill px-2.5 py-1 fw-semibold ' + (filter === 'all' ? 'btn-primary text-white shadow-xs' : 'text-muted');
        if (btnWith) btnWith.className = 'btn btn-sm rounded-pill px-2.5 py-1 fw-semibold ' + (filter === 'with_tin' ? 'btn-success text-white shadow-xs' : 'text-muted');
        if (btnMissing) btnMissing.className = 'btn btn-sm rounded-pill px-2.5 py-1 fw-semibold ' + (filter === 'missing_tin' ? 'btn-danger text-white shadow-xs' : 'text-muted');

        const rows = document.querySelectorAll('#pageTasTable tbody tr.tas-row');
        let visibleCount = 0;
        let totalVisibleAmt = 0;

        rows.forEach(tr => {
            const hasTin = tr.getAttribute('data-has-tin') === '1';
            let matches = false;
            if (filter === 'all') {
                matches = true;
            } else if (filter === 'with_tin') {
                matches = hasTin;
            } else if (filter === 'missing_tin') {
                matches = !hasTin;
            }

            if (matches) {
                tr.style.display = '';
                visibleCount++;
                const amtCell = tr.querySelector('.cell-tas-amount');
                if (amtCell) {
                    const amtVal = parseFloat((amtCell.textContent || '0').replace(/[^0-9.-]/g, '')) || 0;
                    totalVisibleAmt += amtVal;
                }
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
        if (footAmt) footAmt.textContent = 'ETB ' + totalVisibleAmt.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
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
                const rowMatchesCategory = (_activeExpenseFilter === 'all' || tr.getAttribute('data-purchase-category') === _activeExpenseFilter);
                if (!rowMatchesCategory) {
                    tr.style.display = 'none';
                    return;
                }
            }
            if (tableId === 'pageTasTable') {
                if (tr.id === 'pageTasEmptyFilterRow') return;
                const hasTin = tr.getAttribute('data-has-tin') === '1';
                if (_activeTasTinFilter === 'with_tin' && !hasTin) {
                    tr.style.display = 'none';
                    return;
                }
                if (_activeTasTinFilter === 'missing_tin' && hasTin) {
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
            const matchesType = (type === 'all' || rowType === type);
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
        ['all', 'vat', 'tas'].forEach(t => {
            const btn = document.getElementById('btnExpenseFilter-' + t);
            if (!btn) return;
            if (t === type) {
                btn.classList.remove('text-muted');
                if (t === 'vat') {
                    btn.classList.add('btn-success', 'text-white', 'shadow-xs');
                } else if (t === 'tas') {
                    btn.classList.add('btn-primary', 'text-white', 'shadow-xs');
                } else {
                    btn.classList.add('btn-danger', 'text-white', 'shadow-xs');
                }
            } else {
                btn.classList.remove('btn-danger', 'btn-success', 'btn-primary', 'text-white', 'shadow-xs');
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
            footLabel.textContent = type === 'vat' 
                ? `Total VAT Purchases (${visibleCount} items)` 
                : (type === 'tas' ? `Total TAS Purchases (${visibleCount} items)` : `Total Purchases & Deductions (${visibleCount} items)`);
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

    // Set preview file in inspector modal
    function _setPageSlipPreview(url) {
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
            if (emptyEl) emptyEl.classList.remove('d-none');
            if (dlBtn) dlBtn.classList.add('d-none');
            if (newTabBtn) newTabBtn.classList.add('d-none');
            if (fnLabel) fnLabel.textContent = 'No document attached';
            return;
        }

        if (emptyEl) emptyEl.classList.add('d-none');
        if (dlBtn) { dlBtn.classList.remove('d-none'); dlBtn.href = url; }
        if (newTabBtn) { newTabBtn.classList.remove('d-none'); newTabBtn.href = url; }
        if (fnLabel) {
            const parts = url.split('/');
            fnLabel.textContent = 'Document: ' + decodeURIComponent(parts[parts.length - 1] || 'receipt-slip');
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
        if (idInput) idInput.value = '';

        const rcptIdInput = document.getElementById('movement_edit_receipt_id');
        if (rcptIdInput) rcptIdInput.value = tas.receipt_id || (tas.id ? ('EXP-' + tas.id) : (tas.request_number || ''));

        const expIdInput = document.getElementById('movement_edit_expense_request_id');
        if (expIdInput) expIdInput.value = tas.expense_request_id || tas.id || '';

        const reqNumInput = document.getElementById('movement_edit_request_number');
        if (reqNumInput) reqNumInput.value = tas.request_number || '';

        const badgeId = document.getElementById('movement_edit_ocr_badge_id');
        if (badgeId) badgeId.textContent = tas.request_number || ('TAS #' + (idx + 1));

        const fsDisplay = document.getElementById('movementModalFsNoDisplay');
        if (fsDisplay) {
            fsDisplay.textContent = tas.fs_no && tas.fs_no !== '-' 
                ? ('FS #' + tas.fs_no + ' (TAS 0% VAT)') 
                : (tas.request_number ? (tas.request_number + ' (TAS 0% VAT)') : 'TAS Receipt Inspector');
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

        // TAS Purchases are 0% VAT
        const vatCatSelect = document.getElementById('movement_edit_vat_category');
        if (vatCatSelect) vatCatSelect.value = 'E';

        const purcTypeSelect = document.getElementById('movement_edit_purchase_type');
        if (purcTypeSelect) purcTypeSelect.value = tas.purc_type || 'PUR_GD';

        const amt = cleanNum(tas.amount || tas.base_amount);
        const baseInput = document.getElementById('movement_edit_total_value');
        if (baseInput) baseInput.value = amt.toFixed(2);

        const vatInput = document.getElementById('movement_edit_vat_amount');
        if (vatInput) vatInput.value = '0.00';

        const totalInput = document.getElementById('movement_edit_value_after_vat');
        if (totalInput) totalInput.value = amt.toFixed(2);

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
    }

    function recalcMovementFromVat(val) {
        const vat = parseFloat(val) || 0;
        const base = Math.round((vat / 0.15) * 100) / 100;
        const total = Math.round((base + vat) * 100) / 100;

        const baseInput = document.getElementById('movement_edit_total_value');
        const totalInput = document.getElementById('movement_edit_value_after_vat');
        if (baseInput) baseInput.value = base.toFixed(2);
        if (totalInput) totalInput.value = total.toFixed(2);
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
