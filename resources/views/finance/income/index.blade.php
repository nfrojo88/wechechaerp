@extends('layouts.app')
@section('title', 'Company Income & Sales Receipts')
@section('content')
<div class="container-fluid">
    <!-- Header -->
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
        <div>
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <h1 class="h3 mb-0 fw-bold text-dark"><i class="fas fa-hand-holding-dollar text-success me-2"></i>Company Income Track</h1>
                <span class="badge bg-success-subtle text-success border border-success-subtle px-2.5 py-1 rounded-pill small">
                    <i class="fa-solid fa-file-invoice me-1"></i>ERCA eTax 13-Col Sales Format
                </span>
            </div>
            <p class="text-muted mb-0 mt-1">Monitor company revenues across divisions and export compliant ERCA eTax Sales Declarations</p>
        </div>

        <div class="d-flex align-items-center gap-2 flex-wrap">
            {{-- Export Excel (.xlsx) --}}
            <div class="btn-group shadow-xs">
                <button type="button" class="btn btn-success btn-sm fw-bold dropdown-toggle rounded-pill px-3 py-2 shadow-sm" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="fa-solid fa-file-excel me-1"></i>Export Excel (.xlsx)
                </button>
                <ul class="dropdown-menu dropdown-menu-end shadow-sm" style="min-width: 260px;">
                    <li class="dropdown-header small text-uppercase fw-bold text-success">
                        <i class="fa-solid fa-file-invoice me-1"></i>Sales Declaration (13 Columns)
                    </li>
                    <li>
                        <a class="dropdown-item small fw-semibold" href="{{ route('income.export-excel') }}">
                            <i class="fa-solid fa-table-cells me-2 text-success"></i>Export All Records
                        </a>
                    </li>
                    <li>
                        <a class="dropdown-item small text-primary fw-semibold" href="#" onclick="openIncomeDateExportModal('excel'); return false;">
                            <i class="fa-solid fa-calendar-days me-2 text-primary"></i>Export by Date Range (.xlsx)...
                        </a>
                    </li>
                    @if(request()->anyFilled(['category', 'project_id', 'from_date', 'to_date', 'search']))
                    <li>
                        <a class="dropdown-item small text-info" href="{{ route('income.export-excel', request()->all()) }}">
                            <i class="fa-solid fa-filter me-2"></i>Export Current Filtered View
                        </a>
                    </li>
                    @endif
                </ul>
            </div>

            {{-- Export CSV --}}
            <div class="btn-group shadow-xs">
                <button type="button" class="btn btn-outline-success btn-sm fw-bold dropdown-toggle rounded-pill px-3 py-2 shadow-sm" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="fa-solid fa-file-csv me-1"></i>Export CSV
                </button>
                <ul class="dropdown-menu dropdown-menu-end shadow-sm" style="min-width: 260px;">
                    <li class="dropdown-header small text-uppercase fw-bold text-success">
                        <i class="fa-solid fa-file-invoice me-1"></i>Sales Declaration (13 Columns)
                    </li>
                    <li>
                        <a class="dropdown-item small fw-semibold" href="{{ route('income.export-csv') }}">
                            <i class="fa-solid fa-file-csv me-2 text-success"></i>Export All as CSV (UTF-8 BOM)
                        </a>
                    </li>
                    <li>
                        <a class="dropdown-item small text-primary fw-semibold" href="#" onclick="openIncomeDateExportModal('csv'); return false;">
                            <i class="fa-solid fa-calendar-days me-2 text-primary"></i>Export by Date Range (.csv)...
                        </a>
                    </li>
                    @if(request()->anyFilled(['category', 'project_id', 'from_date', 'to_date', 'search']))
                    <li>
                        <a class="dropdown-item small text-info" href="{{ route('income.export-csv', request()->all()) }}">
                            <i class="fa-solid fa-filter me-2"></i>Export Current Filtered CSV
                        </a>
                    </li>
                    @endif
                </ul>
            </div>

            <button type="button" class="btn btn-primary rounded-pill px-4 py-2 fw-bold shadow-sm" data-bs-toggle="modal" data-bs-target="#recordIncomeModal">
                <i class="fas fa-plus me-1"></i> Record Income &amp; Receipt
            </button>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show shadow-sm rounded-4" role="alert">
            <i class="fa-solid fa-circle-check me-2"></i>{{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show shadow-sm rounded-4" role="alert">
            <i class="fa-solid fa-triangle-exclamation me-2"></i>{{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    @if($errors->any())
        <div class="alert alert-danger alert-dismissible fade show shadow-sm rounded-4" role="alert">
            <h6 class="fw-bold mb-1"><i class="fa-solid fa-circle-xmark me-2"></i>Could not save income record:</h6>
            <ul class="mb-0 ps-3 small">
                @foreach($errors->all() as $err)
                    <li>{{ $err }}</li>
                @endforeach
            </ul>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <!-- Stats Cards -->
    <div class="row g-3 mb-4">
        <div class="col">
            <div class="card shadow-sm border-0 h-100" style="border-bottom: 3px solid #10b981 !important;">
                <div class="card-body">
                    <p class="text-muted text-uppercase fw-semibold mb-1" style="font-size: 0.75rem;">Total Balance (Bank + Cash)</p>
                    <div class="d-flex justify-content-between align-items-center">
                        <h4 class="mb-0 fw-bold text-success">ETB {{ number_format($totalBalance, 2) }}</h4>
                        <i class="fas fa-university text-success opacity-50 fs-3"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col">
            <div class="card shadow-sm border-0 h-100" style="border-bottom: 3px solid #6366f1 !important;">
                <div class="card-body">
                    <p class="text-muted text-uppercase fw-semibold mb-1" style="font-size: 0.75rem;">Total Revenue</p>
                    <div class="d-flex justify-content-between align-items-center">
                        <h4 class="mb-0 fw-bold text-dark">ETB {{ number_format($totalRevenue, 2) }}</h4>
                        <i class="fas fa-wallet text-secondary opacity-50 fs-3"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col">
            <div class="card shadow-sm border-0 h-100" style="border-bottom: 3px solid #0ea5e9 !important;">
                <div class="card-body">
                    <p class="text-muted text-uppercase fw-semibold mb-1" style="font-size: 0.75rem;">Transportation</p>
                    <div class="d-flex justify-content-between align-items-center">
                        <h4 class="mb-0 fw-bold text-info">ETB {{ number_format($transportationTotal, 2) }}</h4>
                        <i class="fas fa-truck text-info opacity-50 fs-3"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col">
            <div class="card shadow-sm border-0 h-100" style="border-bottom: 3px solid #f59e0b !important;">
                <div class="card-body">
                    <p class="text-muted text-uppercase fw-semibold mb-1" style="font-size: 0.75rem;">Rental</p>
                    <div class="d-flex justify-content-between align-items-center">
                        <h4 class="mb-0 fw-bold text-warning">ETB {{ number_format($rentalTotal, 2) }}</h4>
                        <i class="fas fa-tractor text-warning opacity-50 fs-3"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col">
            <div class="card shadow-sm border-0 h-100" style="border-bottom: 3px solid #8b5cf6 !important;">
                <div class="card-body">
                    <p class="text-muted text-uppercase fw-semibold mb-1" style="font-size: 0.75rem;">Construction</p>
                    <div class="d-flex justify-content-between align-items-center">
                        <h4 class="mb-0 fw-bold text-primary" style="color: #8b5cf6 !important;">ETB {{ number_format($constructionTotal, 2) }}</h4>
                        <i class="fas fa-building text-primary opacity-50 fs-3" style="color: #8b5cf6 !important;"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filter Section -->
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-header bg-white border-0 pt-3 pb-0 d-flex justify-content-between align-items-center">
            <h6 class="fw-bold mb-0 text-dark"><i class="fas fa-filter me-2 text-primary"></i>Filter Income &amp; Receipt Records</h6>
            @if(request()->anyFilled(['category', 'project_id', 'from_date', 'to_date', 'search']))
                <a href="{{ route('income.index') }}" class="btn btn-outline-secondary btn-sm rounded-pill px-3 py-1 text-decoration-none">
                    <i class="fa-solid fa-rotate-left me-1"></i>Reset Filters
                </a>
            @endif
        </div>
        <div class="card-body">
            <form method="GET" action="{{ route('income.index') }}">
                <div class="row g-3">
                    <div class="col-md-2">
                        <label class="form-label fw-semibold text-muted small mb-1">Income Division</label>
                        <select name="category" class="form-select bg-light border text-dark form-select-sm rounded-3">
                            <option value="">All Divisions</option>
                            <option value="transportation" {{ request('category') == 'transportation' ? 'selected' : '' }}>Transportation</option>
                            <option value="rental" {{ request('category') == 'rental' ? 'selected' : '' }}>Rental</option>
                            <option value="construction" {{ request('category') == 'construction' ? 'selected' : '' }}>Construction</option>
                            <option value="other" {{ request('category') == 'other' ? 'selected' : '' }}>Other Income</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label fw-semibold text-muted small mb-1">Project / Site</label>
                        <select name="project_id" class="form-select bg-light border text-dark form-select-sm rounded-3">
                            <option value="">All Sites</option>
                            @foreach($projects as $p)
                                <option value="{{ $p->id }}" {{ request('project_id') == $p->id ? 'selected' : '' }}>{{ $p->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold text-muted small mb-1">
                            <i class="fa-regular fa-calendar-days text-primary me-1"></i>Ethiopian Tax Period
                        </label>
                        <div class="input-group input-group-sm">
                            <select name="ethiopian_month" class="form-select bg-light border text-dark fw-semibold" onchange="this.form.submit()">
                                <option value="all" {{ $selectedEthMonth === 'all' ? 'selected' : '' }}>All Months (Full Year)</option>
                                @foreach($availableEthMonths as $mId => $m)
                                    <option value="{{ $mId }}" {{ $selectedEthMonth == $mId ? 'selected' : '' }}>
                                        {{ $m['label'] }}
                                    </option>
                                @endforeach
                            </select>
                            <select name="ethiopian_year" class="form-select bg-light border text-dark fw-semibold" style="max-width: 110px;" onchange="this.form.submit()">
                                @foreach($availableEthYears as $y)
                                    <option value="{{ $y }}" {{ $selectedEthYear == $y ? 'selected' : '' }}>
                                        {{ $y }} ዓ.ም.
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold text-muted small mb-1">Search FS#, MRC#, Buyer, TIN</label>
                        <input type="text" name="search" class="form-control form-control-sm bg-light border text-dark rounded-3" placeholder="FS No, Buyer TIN, Client..." value="{{ request('search') }}">
                    </div>
                    <div class="col-md-2 d-flex align-items-end gap-2">
                        <button type="submit" class="btn btn-primary btn-sm flex-grow-1 shadow-sm rounded-pill fw-semibold">
                            <i class="fas fa-search me-1"></i> Filter
                        </button>
                        <a href="{{ route('income.index') }}" class="btn btn-light btn-sm border shadow-sm rounded-circle p-2" title="Reset">
                            <i class="fas fa-redo"></i>
                        </a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Table Section -->
    <div class="card shadow-sm border-0">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light text-secondary" style="font-size: 0.82rem;">
                        <tr>
                            <th class="ps-4">INCOME REF</th>
                            <th>DATE (EC)</th>
                            <th>DIVISION</th>
                            <th>PROJECT / SITE</th>
                            <th>BUYER &amp; TIN</th>
                            <th>RECEIPT (FS# &amp; MRC)</th>
                            <th>COMMODITY</th>
                            <th class="text-end">GROSS AMOUNT</th>
                            <th class="text-end">VAT (15%)</th>
                            <th class="text-end">NET AMOUNT</th>
                            <th class="text-center">RECEIPT SLIP</th>
                            <th class="text-center">ENCODED BY</th>
                            <th class="text-center pe-4">ACTION</th>
                        </tr>
                    </thead>
                    <tbody style="font-size: 0.90rem;">
                        @forelse($incomes as $inc)
                        @php
                            $gross = (float)$inc->amount;
                            $vat = $gross * 0.15;
                            $wht = $gross * 0.03;
                            $net = $gross + $vat - $wht;
                            
                            $badgeColor = 'bg-secondary';
                            $icon = 'fa-tag';
                            if($inc->category == 'transportation') { $badgeColor = 'bg-info text-white'; $icon = 'fa-truck'; }
                            if($inc->category == 'rental') { $badgeColor = 'bg-warning text-dark'; $icon = 'fa-tractor'; }
                            if($inc->category == 'construction') { $badgeColor = 'bg-primary text-white'; $icon = 'fa-building'; }
                        @endphp
                        <tr>
                            <td class="ps-4 text-nowrap">
                                <span class="fw-bold text-dark font-monospace">#{{ substr($inc->income_no, -5) }}</span>
                                <div class="text-muted small" style="font-size: 0.75rem;">{{ $inc->income_no }}</div>
                            </td>
                            <td class="text-nowrap">
                                <span class="fw-semibold text-dark">{{ $inc->income_date ? \App\Services\EthiopianDate::formatDateEc($inc->income_date, 'DD/MM/YYYY EC') : ($inc->receipt_date ? \App\Services\EthiopianDate::formatDateEc($inc->receipt_date, 'DD/MM/YYYY EC') : '—') }}</span>
                            </td>
                            <td>
                                <span class="badge rounded-pill {{ $badgeColor }} px-2.5 py-1.5 fw-semibold shadow-xs">
                                    <i class="fas {{ $icon }} me-1"></i> {{ ucfirst($inc->category) }}
                                </span>
                            </td>
                            <td class="text-muted text-truncate" style="max-width: 140px;" title="{{ $inc->project->name ?? '—' }}">
                                <div class="text-dark fw-semibold text-truncate">{{ $inc->project->name ?? '—' }}</div>
                                <div class="text-muted small text-truncate" style="font-size: 0.74rem;" title="{{ $inc->deposit_account_name }}">
                                    <i class="fa-solid fa-building-columns me-1 text-primary"></i>{{ $inc->deposit_account_name }}
                                </div>
                            </td>
                            <td>
                                <div class="fw-semibold text-dark text-truncate" style="max-width: 140px;" title="{{ $inc->buyer_name }}">
                                    {{ $inc->buyer_name ?: ($inc->project->client_name ?? 'Walk-in Customer') }}
                                </div>
                                @if(!empty($inc->formatted_buyer_tin))
                                    <span class="badge bg-light text-secondary border font-monospace small px-1.5 py-0.5">
                                        TIN: {{ $inc->formatted_buyer_tin }}
                                    </span>
                                @else
                                    <span class="text-muted small">—</span>
                                @endif
                            </td>
                            <td>
                                @if(!empty($inc->formatted_receipt_no))
                                    <span class="badge bg-success bg-opacity-10 text-success border border-success-subtle font-monospace fw-bold px-2 py-1">
                                        {{ $inc->formatted_receipt_no }}
                                    </span>
                                @else
                                    <span class="text-muted small">No FS#</span>
                                @endif
                                @if(!empty($inc->mrc_no))
                                    <div class="text-muted font-monospace" style="font-size: 0.75rem;">
                                        MRC: {{ $inc->mrc_no }}
                                    </div>
                                @endif
                            </td>
                            <td>
                                <span class="badge bg-light text-dark border px-2 py-1 small">
                                    {{ $inc->commodity_type ?: 'Services' }}
                                </span>
                            </td>
                            <td class="text-end fw-bold text-dark">
                                ETB {{ number_format($gross, 2) }}
                            </td>
                            <td class="text-end text-success fw-semibold">
                                +{{ number_format($vat, 2) }}
                            </td>
                            <td class="text-end fw-bold text-primary">
                                ETB {{ number_format($net, 2) }}
                            </td>
                            <td class="text-center">
                                @if(!empty($inc->receipt_url))
                                    <a href="{{ $inc->receipt_url }}" target="_blank" class="btn btn-sm btn-outline-primary rounded-pill px-2.5 py-1 shadow-xs" title="View Uploaded Receipt Slip">
                                        <i class="fa-solid fa-receipt me-1"></i>Slip
                                    </a>
                                @else
                                    <span class="text-muted small">—</span>
                                @endif
                            </td>
                            <td class="text-center font-monospace small text-muted">
                                {{ $inc->encoded_by ?: 'wechacha' }}
                            </td>
                            <td class="text-center pe-4">
                                <div class="d-inline-flex align-items-center gap-1.5">
                                    <a href="{{ route('income.show', $inc) }}" class="btn btn-sm btn-outline-secondary rounded-circle shadow-2xs d-inline-flex align-items-center justify-content-center" style="width: 30px; height: 30px;" title="View Details">
                                        <i class="fas fa-eye small"></i>
                                    </a>
                                    @if($inc->status == 'draft')
                                        <form action="{{ route('income.confirm', $inc) }}" method="POST" class="d-inline" onsubmit="return confirm('Confirm this income record?');">
                                            @csrf
                                            <button type="submit" class="btn btn-sm btn-success rounded-circle shadow-2xs d-inline-flex align-items-center justify-content-center" style="width: 30px; height: 30px;" title="Confirm Income">
                                                <i class="fas fa-check small"></i>
                                            </button>
                                        </form>
                                    @else
                                        <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-2 py-1 small" title="Confirmed & Deposited">
                                            <i class="fas fa-check-circle me-1"></i>Confirmed
                                        </span>
                                    @endif
                                    <form action="{{ route('income.destroy', $inc) }}" method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to DELETE income record #{{ $inc->income_no }}?\n\nThis will permanently delete this income receipt, remove its 15% Output VAT, revert deposited balances, and delete all bank and ledger movements across all sections.');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger rounded-circle shadow-2xs d-inline-flex align-items-center justify-content-center" style="width: 30px; height: 30px;" title="Delete this income record and reverse all VAT & movements">
                                            <i class="fas fa-trash-alt small"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="13" class="text-center py-5 text-muted">
                                <i class="fa-solid fa-inbox fs-2 text-muted mb-2 d-block"></i>
                                No company income records found matching the criteria.
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card-footer bg-white border-0 py-3 d-flex justify-content-between align-items-center">
            <span class="text-muted small">Showing {{ $incomes->firstItem() ?? 0 }} to {{ $incomes->lastItem() ?? 0 }} of {{ $incomes->total() }} records</span>
            {{ $incomes->links() }}
        </div>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════════════════════ -->
<!-- RECORD INCOME & RECEIPT MODAL                                          -->
<!-- ═══════════════════════════════════════════════════════════════════════ -->
<div class="modal fade" id="recordIncomeModal" tabindex="-1" aria-labelledby="recordIncomeModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header bg-light border-bottom py-3 px-4">
                <div class="d-flex align-items-center gap-2">
                    <span class="badge bg-success p-2 rounded-circle text-white">
                        <i class="fas fa-hand-holding-dollar fs-6"></i>
                    </span>
                    <div>
                        <h5 class="modal-title fw-bold text-dark mb-0" id="recordIncomeModalLabel">Record Company Income &amp; Sales Receipt</h5>
                        <small class="text-muted">Compliant with Ethiopian ERCA eTax Sales Declaration (13 Columns)</small>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            
            <form action="{{ route('income.store') }}" method="POST" enctype="multipart/form-data" id="incomeRecordForm">
                @csrf
                <input type="hidden" name="payment_method" value="bank_transfer">
                <input type="hidden" name="receipt_file_path" id="scannedReceiptPath" value="">

                <div class="modal-body p-4">
                    {{-- ── SECTION 1: OCR AUTO-SCAN RECEIPT ── --}}
                    <div class="card border border-primary border-opacity-25 bg-primary bg-opacity-10 rounded-3 mb-4 shadow-xs">
                        <div class="card-body p-3">
                            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-2">
                                <label class="form-label fw-bold text-primary mb-0 small">
                                    <i class="fa-solid fa-camera-viewfinder me-1"></i>Attach Receipt Slip / AI Vision OCR Scan
                                </label>
                                <span class="badge bg-primary text-white rounded-pill px-2 py-0.5 small">Auto-Extracts FS#, MRC, TIN, Amount</span>
                            </div>
                            <div class="row g-2 align-items-center">
                                <div class="col-md-8">
                                    <input type="file" name="receipt_file" id="modalReceiptFile" class="form-control bg-white border" accept="image/jpeg,image/png,image/webp,application/pdf">
                                    <div class="form-text small text-muted">Upload fiscal sales slip, cash receipt, or bank receipt (JPG, PNG, PDF up to 25MB).</div>
                                </div>
                                <div class="col-md-4 d-grid">
                                    <button type="button" class="btn btn-primary fw-semibold shadow-xs" id="btnScanOcr" onclick="triggerOcrScan();">
                                        <i class="fa-solid fa-microchip me-1"></i><span id="scanBtnText">Extract with AI OCR</span>
                                    </button>
                                </div>
                            </div>
                            <div id="ocrStatusAlert" class="mt-2 d-none alert alert-info py-2 px-3 small mb-0 rounded-2"></div>
                        </div>
                    </div>

                    <div class="row g-3">
                        {{-- ── LEFT COLUMN: Basic Income Data ── --}}
                        <div class="col-lg-6 border-end">
                            <h6 class="fw-bold text-dark border-bottom pb-2 mb-3">
                                <i class="fa-solid fa-wallet text-success me-1"></i>Income Information
                            </h6>

                            <div class="mb-3">
                                <label class="form-label fw-semibold small text-dark">Income Division / Source <span class="text-danger">*</span></label>
                                <select name="category" id="incomeCategorySelect" class="form-select bg-light border text-dark" required onchange="handleCategoryChange();">
                                    <option value="transportation">Transportation</option>
                                    <option value="rental">Rental</option>
                                    <option value="construction">Construction</option>
                                    <option value="other">Other Income</option>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-semibold small text-dark">Project / Site (Optional)</label>
                                <select name="project_id" id="incomeProjectSelect" class="form-select bg-light border text-dark">
                                    <option value="">Select Project Site...</option>
                                    @foreach($projects as $p)
                                        <option value="{{ $p->id }}">{{ $p->name }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="mb-3">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <label class="form-label fw-semibold small text-dark mb-0">
                                        <i class="fa-solid fa-building-columns text-primary me-1"></i>Deposit to Bank / Cash Account <span class="text-danger">*</span>
                                    </label>
                                    <span class="badge bg-primary bg-opacity-10 text-primary border border-primary-subtle rounded-pill small" style="font-size: 0.70rem;">
                                        <i class="fa-solid fa-sitemap me-1"></i>From COA
                                    </span>
                                </div>
                                <select name="chart_of_account_id" id="modalDepositAccount" class="form-select bg-light border text-dark" required onchange="handleDepositAccountChange();">
                                    <option value="">Select Account from COA...</option>
                                    @foreach($coaAccounts as $coa)
                                        @php
                                            $linkedBank = $coa->bankAccounts->first();
                                            $bankDetail = $linkedBank ? " • A/C: {$linkedBank->account_number} ({$linkedBank->bank_name})" : "";
                                        @endphp
                                        <option value="{{ $coa->id }}" 
                                                data-bank-id="{{ $linkedBank?->id ?? '' }}"
                                                data-balance="{{ $coa->current_balance }}">
                                            [{{ $coa->code }}] {{ $coa->name }}{{ $bankDetail }} — Bal: ETB {{ number_format($coa->current_balance, 2) }}
                                        </option>
                                    @endforeach
                                </select>
                                <input type="hidden" name="bank_account_id" id="modalBankAccountId" value="">
                                <div class="form-text small text-muted" style="font-size: 0.75rem;">
                                    <i class="fa-solid fa-circle-check text-success me-1"></i>The deposited money will be automatically added to this Bank / COA account balance.
                                </div>
                            </div>

                            <div class="row g-2 mb-3">
                                <div class="col-6">
                                    <label class="form-label fw-semibold small text-dark">Gross Amount (ETB) <span class="text-danger">*</span></label>
                                    <input type="number" step="0.01" name="amount" id="modalAmount" class="form-control bg-light border text-dark fw-bold" required placeholder="0.00">
                                </div>
                                <div class="col-6">
                                    <label class="form-label fw-semibold small text-dark">Income Date (GC or EC) <span class="text-danger">*</span></label>
                                    <input type="date" name="income_date" id="modalIncomeDate" class="form-control bg-light border text-dark" value="{{ date('Y-m-d') }}">
                                    <input type="text" name="income_date_ec" id="modalIncomeDateEc" class="form-control form-control-sm bg-light border text-dark font-monospace mt-1" placeholder="DD/MM/YYYY EC" value="{{ \App\Services\EthiopianDate::formatDateEc(now(), 'DD/MM/YYYY EC') }}">
                                    <small class="text-muted" style="font-size:0.68rem;">Current (EC): {{ \App\Services\EthiopianDate::formatDateEc(now()) }}</small>
                                </div>
                            </div>

                            <!-- Computed Tax Box -->
                            <div class="p-3 mb-3 bg-light border rounded-3">
                                <div class="row g-2 text-center">
                                    <div class="col-3 border-end">
                                        <small class="text-muted d-block" style="font-size: 0.72rem;">GROSS</small>
                                        <strong class="text-dark small" id="lblGross">0.00</strong>
                                    </div>
                                    <div class="col-3 border-end">
                                        <small class="text-muted d-block" style="font-size: 0.72rem;">VAT (+15%)</small>
                                        <strong class="text-success small" id="lblVat">0.00</strong>
                                    </div>
                                    <div class="col-3 border-end">
                                        <small class="text-muted d-block" style="font-size: 0.72rem;">WHT (-3%)</small>
                                        <strong class="text-danger small" id="lblWht">0.00</strong>
                                    </div>
                                    <div class="col-3">
                                        <small class="text-muted d-block" style="font-size: 0.72rem;">NET TOTAL</small>
                                        <strong class="text-primary small" id="lblNet">0.00</strong>
                                    </div>
                                </div>
                            </div>

                            <div class="mb-2">
                                <label class="form-label fw-semibold small text-dark">Description / Reference</label>
                                <textarea name="description" class="form-control bg-light border text-dark" rows="2" placeholder="Income details, contract reference..."></textarea>
                            </div>
                        </div>

                        {{-- ── RIGHT COLUMN: ERCA eTax Sales Receipt Details ── --}}
                        <div class="col-lg-6">
                            <h6 class="fw-bold text-primary border-bottom pb-2 mb-3">
                                <i class="fa-solid fa-file-invoice text-primary me-1"></i>ERCA eTax Sales Receipt Details
                            </h6>

                            <div class="row g-2 mb-2">
                                <div class="col-6">
                                    <label class="form-label fw-semibold small text-dark">Seller TIN (Company)</label>
                                    <input type="text" name="seller_tin" id="modalSellerTin" class="form-control form-control-sm bg-light border font-monospace fw-bold" value="38480010" readonly>
                                    <small class="text-muted" style="font-size: 0.70rem;">Wechecha Construction TIN</small>
                                </div>
                                <div class="col-6">
                                    <label class="form-label fw-semibold small text-dark">Buyer / Client TIN</label>
                                    <input type="text" name="buyer_tin" id="modalBuyerTin" class="form-control form-control-sm bg-light border font-monospace" placeholder="e.g. 5201, 6870105">
                                    <small class="text-muted" style="font-size: 0.70rem;">Client tax registration</small>
                                </div>
                            </div>

                            <div class="mb-2">
                                <label class="form-label fw-semibold small text-dark">Buyer / Client Trade Name</label>
                                <input type="text" name="buyer_name" id="modalBuyerName" class="form-control form-control-sm bg-light border" placeholder="e.g. Ethiopian Roads Authority, Private Client">
                            </div>

                            <div class="row g-2 mb-2">
                                <div class="col-6">
                                    <label class="form-label fw-semibold small text-dark">Receipt No (FS Number)</label>
                                    <input type="text" name="receipt_no" id="modalReceiptNo" class="form-control form-control-sm bg-light border font-monospace fw-bold text-success" placeholder="e.g. FS00003898">
                                </div>
                                <div class="col-6">
                                    <label class="form-label fw-semibold small text-dark">MRC (Machine Code)</label>
                                    <input type="text" name="mrc_no" id="modalMrcNo" class="form-control form-control-sm bg-light border font-monospace" placeholder="e.g. DDB0000032">
                                </div>
                            </div>

                            <div class="row g-2 mb-2">
                                <div class="col-6">
                                    <label class="form-label fw-semibold small text-dark">Transaction Type</label>
                                    <select name="transaction_type" class="form-select form-select-sm bg-light border">
                                        <option value="Cash Sales" selected>Cash Sales</option>
                                        <option value="Credit Sales">Credit Sales</option>
                                        <option value="Local Sales">Local Sales</option>
                                        <option value="Local Sales with VAT">Local Sales with VAT</option>
                                        <option value="Advance Payment">Advance Payment</option>
                                    </select>
                                </div>
                                <div class="col-6">
                                    <label class="form-label fw-semibold small text-dark">Receipt Category</label>
                                    <select name="receipt_category" class="form-select form-select-sm bg-light border">
                                        <option value="Cash Sales Fiscal Receipt" selected>Cash Sales Fiscal Receipt</option>
                                        <option value="VAT Invoice">VAT Invoice</option>
                                        <option value="Manual Receipt">Manual Receipt</option>
                                        <option value="Credit Sales Invoice">Credit Sales Invoice</option>
                                    </select>
                                </div>
                            </div>

                            <div class="row g-2 mb-2">
                                <div class="col-6">
                                    <label class="form-label fw-semibold small text-dark">Receipt Used</label>
                                    <select name="receipt_used" class="form-select form-select-sm bg-light border">
                                        <option value="Fiscal Cash Register" selected>Fiscal Cash Register</option>
                                        <option value="Electronic Cash Register">Electronic Cash Register</option>
                                        <option value="Sales Register Machine">Sales Register Machine</option>
                                        <option value="Manual Receipt">Manual Receipt</option>
                                    </select>
                                </div>
                                <div class="col-6">
                                    <label class="form-label fw-semibold small text-dark">Commodity Type</label>
                                    <select name="commodity_type" id="modalCommodityType" class="form-select form-select-sm bg-light border">
                                        <option value="Services" selected>Services</option>
                                        <option value="Goods">Goods</option>
                                        <option value="Transportation">Transportation</option>
                                        <option value="Rental">Rental</option>
                                        <option value="Construction">Construction</option>
                                    </select>
                                </div>
                            </div>

                            <div class="row g-2 mb-2">
                                <div class="col-4">
                                    <label class="form-label fw-semibold small text-dark">Quantity</label>
                                    <input type="number" step="0.01" name="quantity" class="form-control form-control-sm bg-light border" value="1.00">
                                </div>
                                <div class="col-4">
                                    <label class="form-label fw-semibold small text-dark">Receipt Date</label>
                                    <input type="date" name="receipt_date" id="modalReceiptDate" class="form-control form-control-sm bg-light border" value="{{ date('Y-m-d') }}">
                                    <input type="text" name="receipt_date_ec" id="modalReceiptDateEc" class="form-control form-control-sm bg-light border font-monospace mt-1" placeholder="DD/MM/YYYY EC" value="{{ \App\Services\EthiopianDate::formatDateEc(now(), 'DD/MM/YYYY EC') }}">
                                </div>
                                <div class="col-4">
                                    <label class="form-label fw-semibold small text-dark">Encoded By</label>
                                    <input type="text" name="encoded_by" class="form-control form-control-sm bg-light border font-monospace" value="wechacha">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="modal-footer bg-light border-top py-3 px-4">
                    <button type="button" class="btn btn-light border rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary rounded-pill px-4 shadow-sm fw-bold">
                        <i class="fas fa-save me-1"></i>Save Income &amp; Receipt
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════════════════════ -->
<!-- DATE RANGE EXPORT MODAL (13 COLUMNS ERCA E-TAX FORMAT)                -->
<!-- ═══════════════════════════════════════════════════════════════════════ -->
<div class="modal fade" id="incomeExportDateModal" tabindex="-1" aria-labelledby="incomeExportDateModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header border-bottom py-3 px-4 bg-light">
                <div class="d-flex align-items-center gap-2">
                    <span class="badge bg-success p-2 rounded-circle text-white">
                        <i class="fa-solid fa-file-excel fs-6" id="modalExportIcon"></i>
                    </span>
                    <div>
                        <h5 class="modal-title fw-bold text-dark mb-0" id="incomeExportDateModalLabel">Export Sales Declaration</h5>
                        <small class="text-muted">Official ERCA eTax 13-Column Format</small>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            
            <form id="incomeExportForm" method="GET" action="{{ route('income.export-excel') }}">
                <div class="modal-body p-4">
                    <!-- Format Selection -->
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-dark mb-1">Export Format</label>
                        <div class="d-flex gap-3">
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="export_format_choice" id="fmt_excel" value="excel" checked onchange="updateExportTarget();">
                                <label class="form-check-label small fw-semibold text-dark" for="fmt_excel">
                                    <i class="fa-solid fa-file-excel text-success me-1"></i>Excel (.xlsx)
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="export_format_choice" id="fmt_csv" value="csv" onchange="updateExportTarget();">
                                <label class="form-check-label small fw-semibold text-dark" for="fmt_csv">
                                    <i class="fa-solid fa-file-csv text-primary me-1"></i>CSV (UTF-8 BOM)
                                </label>
                            </div>
                        </div>
                    </div>

                    <!-- Date Presets -->
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-dark mb-1">Quick Date Presets</label>
                        <div class="d-flex flex-wrap gap-1.5">
                            <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill px-2.5 py-1 small" onclick="setExportDates('today')">Today</button>
                            <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill px-2.5 py-1 small" onclick="setExportDates('yesterday')">Yesterday</button>
                            <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill px-2.5 py-1 small" onclick="setExportDates('this_week')">This Week</button>
                            <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill px-2.5 py-1 small" onclick="setExportDates('this_month')">This Month</button>
                            <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill px-2.5 py-1 small" onclick="setExportDates('last_month')">Last Month</button>
                            <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill px-2.5 py-1 small" onclick="setExportDates('all')">All Dates</button>
                        </div>
                    </div>

                    <!-- Ethiopian Month & Year Selector -->
                    <div class="mb-3 p-3 bg-light rounded-3 border">
                        <label class="form-label small fw-bold text-uppercase text-dark d-block mb-1" style="font-size:0.72rem; letter-spacing:0.5px;">
                            <i class="fa-regular fa-calendar-days text-primary me-1"></i>Ethiopian Tax Period (ወርና ዓ.ም.)
                        </label>
                        <div class="row g-2">
                            <div class="col-7">
                                <select name="ethiopian_month" class="form-select form-select-sm fw-semibold">
                                    @foreach($availableEthMonths as $mId => $m)
                                        <option value="{{ $mId }}" {{ $selectedEthMonth == $mId ? 'selected' : '' }}>
                                            {{ $m['label'] }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-5">
                                <select name="ethiopian_year" class="form-select form-select-sm fw-semibold">
                                    @foreach($availableEthYears as $y)
                                        <option value="{{ $y }}" {{ $selectedEthYear == $y ? 'selected' : '' }}>
                                            {{ $y }} ዓ.ም.
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Custom Date Pickers -->
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-bold text-dark mb-1">From Date</label>
                            <input type="date" name="from_date" id="export_from_date" class="form-control form-control-sm bg-light border">
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-bold text-dark mb-1">To Date</label>
                            <input type="date" name="to_date" id="export_to_date" class="form-control form-control-sm bg-light border">
                        </div>
                    </div>

                    <!-- Division Filter -->
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-dark mb-1">Division (Optional)</label>
                        <select name="category" class="form-select form-select-sm bg-light border">
                            <option value="">All Divisions</option>
                            <option value="transportation">Transportation</option>
                            <option value="rental">Rental</option>
                            <option value="construction">Construction</option>
                            <option value="other">Other</option>
                        </select>
                    </div>

                    <!-- Encoded By Field -->
                    <div class="mb-2">
                        <label class="form-label small fw-bold text-dark mb-1">eTax Encoded By Username</label>
                        <input type="text" name="encoded_by" class="form-control form-control-sm bg-light border font-monospace" value="wechacha" placeholder="wechacha">
                    </div>
                </div>

                <div class="modal-footer bg-light border-top py-3 px-4">
                    <button type="button" class="btn btn-light border rounded-pill px-3" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success rounded-pill px-4 fw-bold shadow-sm" id="btnDoExport">
                        <i class="fa-solid fa-download me-1"></i>Download Export
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const amountInput = document.getElementById('modalAmount');
    const lblGross = document.getElementById('lblGross');
    const lblVat = document.getElementById('lblVat');
    const lblWht = document.getElementById('lblWht');
    const lblNet = document.getElementById('lblNet');

    function updateCalculations() {
        const gross = parseFloat(amountInput.value) || 0;
        const vat = gross * 0.15;
        const wht = gross * 0.03;
        const net = gross + vat - wht;

        lblGross.textContent = gross.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
        lblVat.textContent = vat.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
        lblWht.textContent = wht.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
        lblNet.textContent = net.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    }

    if (amountInput) {
        amountInput.addEventListener('input', updateCalculations);
    }
});

function handleCategoryChange() {
    const cat = document.getElementById('incomeCategorySelect').value;
    const cmd = document.getElementById('modalCommodityType');
    if (cmd) {
        if (cat === 'construction') {
            cmd.value = 'Goods';
        } else {
            cmd.value = 'Services';
        }
    }
}

function handleDepositAccountChange() {
    const select = document.getElementById('modalDepositAccount');
    if (!select) return;
    const opt = select.options[select.selectedIndex];
    const bankId = opt ? (opt.getAttribute('data-bank-id') || '') : '';
    const hiddenBank = document.getElementById('modalBankAccountId');
    if (hiddenBank) {
        hiddenBank.value = bankId;
    }
}

// Trigger OCR scanning for uploaded receipt slip
function triggerOcrScan() {
    const fileInput = document.getElementById('modalReceiptFile');
    if (!fileInput || !fileInput.files || fileInput.files.length === 0) {
        alert('Please choose a receipt slip image or PDF first.');
        fileInput.focus();
        return;
    }

    const btn = document.getElementById('btnScanOcr');
    const btnText = document.getElementById('scanBtnText');
    const statusAlert = document.getElementById('ocrStatusAlert');

    const file = fileInput.files[0];
    const formData = new FormData();
    formData.append('receipt_file', file);
    formData.append('_token', '{{ csrf_token() }}');

    btn.disabled = true;
    btnText.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-1"></i>Scanning OCR...';
    statusAlert.classList.remove('d-none', 'alert-danger', 'alert-success');
    statusAlert.classList.add('alert-info');
    statusAlert.innerHTML = '<i class="fa-solid fa-microchip fa-spin me-1"></i>AI Vision OCR processing receipt slip... Please wait.';

    fetch('{{ route("income.scan-receipt") }}', {
        method: 'POST',
        body: formData,
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(res => res.json())
    .then(data => {
        btn.disabled = false;
        btnText.innerHTML = '<i class="fa-solid fa-microchip me-1"></i>Extract with AI OCR';

        if (data.success) {
            statusAlert.classList.remove('alert-info');
            statusAlert.classList.add('alert-success');
            statusAlert.innerHTML = '<i class="fa-solid fa-circle-check me-1"></i>Receipt extracted successfully! Fields pre-populated.';

            // Populate form fields
            if (data.buyer_tin) document.getElementById('modalBuyerTin').value = data.buyer_tin;
            if (data.buyer_name) document.getElementById('modalBuyerName').value = data.buyer_name;
            if (data.receipt_no) document.getElementById('modalReceiptNo').value = data.receipt_no;
            if (data.mrc_no) document.getElementById('modalMrcNo').value = data.mrc_no;
            if (data.receipt_date) document.getElementById('modalReceiptDate').value = data.receipt_date;
            if (data.receipt_date_ec) {
                const rEc = document.getElementById('modalReceiptDateEc');
                if (rEc) rEc.value = data.receipt_date_ec;
                const iEc = document.getElementById('modalIncomeDateEc');
                if (iEc) iEc.value = data.receipt_date_ec;
            }
            if (data.amount && parseFloat(data.amount) > 0) {
                const amtInput = document.getElementById('modalAmount');
                amtInput.value = parseFloat(data.amount).toFixed(2);
                amtInput.dispatchEvent(new Event('input'));
            }
            if (data.file_path) {
                document.getElementById('scannedReceiptPath').value = data.file_path;
            }
        } else {
            statusAlert.classList.remove('alert-info');
            statusAlert.classList.add('alert-danger');
            statusAlert.innerHTML = '<i class="fa-solid fa-triangle-exclamation me-1"></i>OCR Warning: ' + (data.message || 'Could not auto-extract fields. Please type manually.');
        }
    })
    .catch(err => {
        btn.disabled = false;
        btnText.innerHTML = '<i class="fa-solid fa-microchip me-1"></i>Extract with AI OCR';
        statusAlert.classList.remove('alert-info');
        statusAlert.classList.add('alert-danger');
        statusAlert.innerHTML = '<i class="fa-solid fa-circle-xmark me-1"></i>Scan error: ' + err.message;
    });
}

// Open Export Date Modal
function openIncomeDateExportModal(format) {
    const excelRadio = document.getElementById('fmt_excel');
    const csvRadio = document.getElementById('fmt_csv');
    if (format === 'csv') {
        csvRadio.checked = true;
    } else {
        excelRadio.checked = true;
    }
    updateExportTarget();

    // Default to this month
    setExportDates('this_month');

    const modal = new bootstrap.Modal(document.getElementById('incomeExportDateModal'));
    modal.show();
}

function updateExportTarget() {
    const form = document.getElementById('incomeExportForm');
    const btn = document.getElementById('btnDoExport');
    const icon = document.getElementById('modalExportIcon');
    const isCsv = document.getElementById('fmt_csv').checked;

    if (isCsv) {
        form.action = '{{ route("income.export-csv") }}';
        btn.className = 'btn btn-outline-success rounded-pill px-4 fw-bold shadow-sm';
        btn.innerHTML = '<i class="fa-solid fa-file-csv me-1"></i>Download CSV';
        icon.className = 'fa-solid fa-file-csv fs-6';
    } else {
        form.action = '{{ route("income.export-excel") }}';
        btn.className = 'btn btn-success rounded-pill px-4 fw-bold shadow-sm';
        btn.innerHTML = '<i class="fa-solid fa-file-excel me-1"></i>Download Excel (.xlsx)';
        icon.className = 'fa-solid fa-file-excel fs-6';
    }
}

function setExportDates(preset) {
    const fromInput = document.getElementById('export_from_date');
    const toInput = document.getElementById('export_to_date');
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
        const yesterday = new Date(now);
        yesterday.setDate(yesterday.getDate() - 1);
        const yStr = formatDate(yesterday);
        fromInput.value = yStr;
        toInput.value = yStr;
    } else if (preset === 'this_week') {
        const day = now.getDay();
        const diff = now.getDate() - day + (day === 0 ? -6 : 1);
        const monday = new Date(now.setDate(diff));
        fromInput.value = formatDate(monday);
        toInput.value = formatDate(new Date());
    } else if (preset === 'this_month') {
        const firstDay = new Date(now.getFullYear(), now.getMonth(), 1);
        fromInput.value = formatDate(firstDay);
        toInput.value = formatDate(now);
    } else if (preset === 'last_month') {
        const firstDay = new Date(now.getFullYear(), now.getMonth() - 1, 1);
        const lastDay = new Date(now.getFullYear(), now.getMonth(), 0);
        fromInput.value = formatDate(firstDay);
        toInput.value = formatDate(lastDay);
    } else if (preset === 'all') {
        fromInput.value = '';
        toInput.value = '';
    }
}

@if($errors->any() || session('error'))
document.addEventListener('DOMContentLoaded', function() {
    const modalEl = document.getElementById('recordIncomeModal');
    if (modalEl) {
        const modal = new bootstrap.Modal(modalEl);
        modal.show();
    }
});
@endif
</script>
@endsection
