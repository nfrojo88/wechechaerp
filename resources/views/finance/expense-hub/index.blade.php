@extends('layouts.app')

@section('title', 'Expense Hub & VAT Decision Engine')

@section('content')
<div class="container-fluid px-3 py-3">

    <!-- ── Header ──────────────────────────────────────────────────────────── -->
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
            <h1 class="h3 mb-1 text-dark fw-bold d-flex align-items-center gap-2">
                <i class="fa-solid fa-calculator text-primary"></i> Expense Hub &amp; VAT Decision Engine
            </h1>
            <p class="text-muted small mb-0">
                Consolidated cash-basis disbursement ledger across all 9 operational modules. Determine VAT eligibility, calculate base &amp; 15% VAT, and export official Ministry of Revenue declarations.
            </p>
        </div>
        <div class="d-flex flex-wrap align-items-center gap-2">
            <a href="{{ route('finance.expense-hub.export-hub', request()->query()) }}" class="btn btn-outline-success btn-sm shadow-xs">
                <i class="fa-solid fa-file-excel me-1"></i> Export Hub (Excel)
            </a>
            <a href="{{ route('finance.expense-hub.export-tas', request()->query()) }}" class="btn btn-dark btn-sm shadow-xs" title="Download 9-Column e-Tax Purchase TAS Declaration (pro.doc format)">
                <i class="fa-solid fa-file-excel text-success me-1"></i> Purchase TAS Excel (9 Cols)
            </a>
            <a href="{{ route('finance.expense-hub.export-vat', request()->query()) }}" class="btn btn-primary btn-sm shadow-xs">
                <i class="fa-solid fa-download me-1"></i> Official VAT Report (15 Cols)
            </a>
        </div>
    </div>

    <!-- ── Top Summary KPI Cards ─────────────────────────────────────────────── -->
    <div class="row g-3 mb-4">
        <!-- 1. Total Spent -->
        <div class="col-xl-2 col-md-4 col-sm-6">
            <div class="card border-0 shadow-sm h-100 rounded-3 border-start border-primary border-4">
                <div class="card-body p-3">
                    <span class="text-muted text-uppercase fw-semibold text-xxs d-block mb-1">Total Disbursed</span>
                    <h5 class="mb-0 fw-bold text-dark">{{ number_format($summary['total_spent'], 2) }}</h5>
                    <div class="mt-2 text-xxs text-muted d-flex justify-content-between">
                        <span>Records:</span>
                        <span class="fw-semibold text-dark">{{ number_format($summary['total_count']) }}</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- 2. VAT Applies -->
        <div class="col-xl-2 col-md-4 col-sm-6">
            <div class="card border-0 shadow-sm h-100 rounded-3 border-start border-success border-4 bg-success bg-opacity-10">
                <div class="card-body p-3">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <span class="text-success-emphasis text-uppercase fw-bold text-xxs">VAT Applies (Inputs)</span>
                        <span class="badge bg-success rounded-pill px-2 py-0.5 text-xxs">15% Taxable</span>
                    </div>
                    <h5 class="mb-0 fw-bold text-success">{{ number_format($summary['vat_applies_total'], 2) }} <small class="text-xxs">ETB</small></h5>
                    <div class="mt-2 text-xxs text-dark d-flex justify-content-between">
                        <span>Input VAT:</span>
                        <strong class="text-success">{{ number_format($summary['total_input_vat'], 2) }} ETB</strong>
                    </div>
                </div>
            </div>
        </div>

        <!-- 3. No VAT -->
        <div class="col-xl-2 col-md-4 col-sm-6">
            <div class="card border-0 shadow-sm h-100 rounded-3 border-start border-secondary border-4">
                <div class="card-body p-3">
                    <span class="text-muted text-uppercase fw-semibold text-xxs d-block mb-1">No VAT (Exempt/Salary)</span>
                    <h5 class="mb-0 fw-bold text-dark">{{ number_format($summary['no_vat_total'], 2) }}</h5>
                    <div class="mt-2 text-xxs text-muted d-flex justify-content-between align-items-center">
                        <span>Exempt, salaries, informal</span>
                        @if(($summary['verified_no_receipt_count'] ?? 0) > 0)
                        <span class="badge bg-info bg-opacity-10 text-info border border-info border-opacity-25" title="Total verified without receipt">
                            <i class="fa-solid fa-check-double me-0.5"></i> {{ $summary['verified_no_receipt_count'] }} w/o slip
                        </span>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <!-- 4. Withholding Tax (3%) -->
        <div class="col-xl-2 col-md-4 col-sm-6">
            <div class="card border-0 shadow-sm h-100 rounded-3 border-start border-warning border-4 bg-warning bg-opacity-10">
                <div class="card-body p-3">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <span class="text-warning-emphasis text-uppercase fw-bold text-xxs">
                            <i class="fa-solid fa-stamp me-1"></i> Withholding (3%)
                        </span>
                        <span class="badge bg-warning text-dark fw-bold rounded-pill px-2 py-0.5 text-xxs">
                            {{ $summary['withholding_count'] ?? 0 }} Items
                        </span>
                    </div>
                    <h5 class="mb-0 fw-bold text-warning-emphasis">{{ number_format($summary['withholding_total'] ?? 0, 2) }} <small class="text-xxs">ETB</small></h5>
                    <div class="mt-2 text-xxs text-muted">
                        <span>3% Tax Deductions Ledger</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- 5. Pending Decision -->
        <div class="col-xl-2 col-md-6 col-sm-6">
            <div class="card border-0 shadow-sm h-100 rounded-3 border-start border-danger border-4 bg-danger bg-opacity-10">
                <div class="card-body p-3">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <span class="text-danger text-uppercase fw-bold text-xxs">
                            <i class="fa-solid fa-triangle-exclamation me-1"></i> Pending
                        </span>
                        <span class="badge bg-danger text-white fw-bold rounded-pill px-2 py-0.5 text-xxs">
                            {{ $summary['pending_count'] }} Unclassified
                        </span>
                    </div>
                    <h5 class="mb-0 fw-bold text-danger">{{ number_format($summary['pending_total'], 2) }} <small class="text-xxs">ETB</small></h5>
                    <div class="mt-2 text-xxs text-muted">
                        <span>Needs VAT/Tax classification</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- 6. CapEx Separate -->
        <div class="col-xl-2 col-md-6 col-sm-6">
            <div class="card border-0 shadow-sm h-100 rounded-3 border-start border-info border-4">
                <div class="card-body p-3">
                    <span class="text-muted text-uppercase fw-semibold text-xxs d-block mb-1">CapEx (Fixed Assets)</span>
                    <h5 class="mb-0 fw-bold text-info">{{ number_format($summary['capex_total'], 2) }}</h5>
                    <div class="mt-2 text-xxs text-muted">
                        <span>Capital Line 65/75</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ── Navigation Tabs ───────────────────────────────────────────────────── -->
    <ul class="nav nav-pills mb-3 gap-2" id="expenseHubTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <a class="nav-link rounded-pill px-3 py-1.5 fw-semibold text-sm {{ $activeTab === 'all_expenses' ? 'active shadow-xs' : 'bg-white border text-dark' }}" 
               href="{{ route('finance.expense-hub.index', array_merge(request()->except(['page']), ['tab' => 'all_expenses'])) }}">
                <i class="fa-solid fa-list me-1.5"></i> All Expenses Hub
                <span class="badge {{ $activeTab === 'all_expenses' ? 'bg-white text-primary' : 'bg-secondary' }} rounded-pill ms-1 text-xxs">
                    {{ $allExpenses->count() }}
                </span>
            </a>
        </li>
        <li class="nav-item" role="presentation">
            <a class="nav-link rounded-pill px-3 py-1.5 fw-semibold text-sm {{ $activeTab === 'vat_report' ? 'active shadow-xs bg-success' : 'bg-white border text-dark' }}" 
               href="{{ route('finance.expense-hub.index', array_merge(request()->except(['page']), ['tab' => 'vat_report'])) }}">
                <i class="fa-solid fa-receipt me-1.5"></i> Official VAT Report (15 Cols)
                <span class="badge {{ $activeTab === 'vat_report' ? 'bg-white text-success' : 'bg-success' }} rounded-pill ms-1 text-xxs">
                    {{ $vatReportItems->count() }}
                </span>
            </a>
        </li>
        <li class="nav-item" role="presentation">
            <a class="nav-link rounded-pill px-3 py-1.5 fw-semibold text-sm {{ $activeTab === 'vat_summary' ? 'active shadow-xs bg-dark' : 'bg-white border text-dark' }}" 
               href="{{ route('finance.expense-hub.index', array_merge(request()->except(['page']), ['tab' => 'vat_summary'])) }}">
                <i class="fa-solid fa-chart-column me-1.5"></i> VAT Monthly Summary
                @if($summary['pending_count'] > 0)
                <span class="badge bg-warning text-dark rounded-pill ms-1 text-xxs" title="{{ $summary['pending_count'] }} Pending Items">
                    {{ $summary['pending_count'] }} Pending
                </span>
                @endif
            </a>
        </li>
    </ul>

    <!-- ── Filter & Search Toolbar ───────────────────────────────────────────── -->
    <div class="card border-0 shadow-sm rounded-3 mb-3">
        <div class="card-body p-3">
            <form method="GET" action="{{ route('finance.expense-hub.index') }}" class="row g-2 align-items-end">
                <input type="hidden" name="tab" value="{{ $activeTab }}">

                <!-- Search term -->
                <div class="col-md-2 col-sm-6">
                    <label class="form-label text-xxs fw-semibold text-muted mb-1">Search Keyword / Ref / TIN</label>
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-white"><i class="fa-solid fa-magnifying-glass text-muted"></i></span>
                        <input type="text" name="search" class="form-control" placeholder="Ref #, supplier, TIN..." value="{{ request('search') }}">
                    </div>
                </div>

                <!-- Supplier / Merchant Name Filter -->
                <div class="col-md-2 col-sm-6">
                    <label class="form-label text-xxs fw-semibold text-muted mb-1">Supplier / Merchant Name</label>
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-white"><i class="fa-solid fa-store text-muted"></i></span>
                        <input type="text" name="supplier" class="form-control" placeholder="Supplier name..." value="{{ request('supplier') }}">
                    </div>
                </div>

                <!-- Project -->
                <div class="col-md-2 col-sm-6">
                    <label class="form-label text-xxs fw-semibold text-muted mb-1">Project / Site</label>
                    <select name="project_id" class="form-select form-select-sm">
                        <option value="">All Projects</option>
                        @foreach($projects as $p)
                        <option value="{{ $p->id }}" @selected(request('project_id') == $p->id)>{{ $p->name }}</option>
                        @endforeach
                    </select>
                </div>

                <!-- Source Module -->
                <div class="col-md-2 col-sm-6">
                    <label class="form-label text-xxs fw-semibold text-muted mb-1">Source Module</label>
                    <select name="source_module" class="form-select form-select-sm">
                        <option value="">All 9 Modules</option>
                        @foreach($sourceModules as $modKey => $modLabel)
                        <option value="{{ $modKey }}" @selected(request('source_module') == $modKey)>{{ $modLabel }}</option>
                        @endforeach
                    </select>
                </div>

                <!-- VAT Status Filter -->
                <div class="col-md-2 col-sm-6">
                    <label class="form-label text-xxs fw-semibold text-muted mb-1">VAT Status</label>
                    <select name="vat_status" class="form-select form-select-sm">
                        <option value="">All Statuses</option>
                        <option value="vat_applies" @selected(request('vat_status') === 'vat_applies')>VAT Applies (Inputs)</option>
                        <option value="no_vat" @selected(request('vat_status') === 'no_vat')>No VAT (Exempt/Salary)</option>
                        <option value="verified_no_receipt" @selected(request('vat_status') === 'verified_no_receipt')>Verified Without Receipt</option>
                        <option value="pending_decision" @selected(request('vat_status') === 'pending_decision')>Pending Decision</option>
                    </select>
                </div>

                <!-- Withholding Tax Filter -->
                <div class="col-md-2 col-sm-6">
                    <label class="form-label text-xxs fw-semibold text-muted mb-1">Withholding Tax</label>
                    <select name="withholding" class="form-select form-select-sm">
                        <option value="">All Withholding</option>
                        <option value="yes" @selected(request('withholding') === 'yes')>3% WHT Deducted</option>
                        <option value="no" @selected(request('withholding') === 'no')>No Withholding</option>
                    </select>
                </div>

                <!-- Date Range -->
                <div class="col-md-2 col-sm-6">
                    <label class="form-label text-xxs fw-semibold text-muted mb-1">Date Range</label>
                    <div class="input-group input-group-sm">
                        <input type="date" name="date_from" class="form-control" value="{{ request('date_from') }}" title="Date From">
                        <span class="input-group-text bg-white px-1">to</span>
                        <input type="date" name="date_to" class="form-control" value="{{ request('date_to') }}" title="Date To">
                    </div>
                </div>

                <!-- Submit / Clear -->
                <div class="col-12 text-end d-flex justify-content-end gap-1 mt-1">
                    <button type="submit" class="btn btn-sm btn-primary px-3 shadow-xs" title="Apply Filters">
                        <i class="fa-solid fa-filter me-1"></i> Filter
                    </button>
                    @if(request()->hasAny(['search', 'project_id', 'source_module', 'vat_status', 'withholding', 'date_from', 'date_to', 'supplier', 'category']))
                    <a href="{{ route('finance.expense-hub.index', ['tab' => $activeTab]) }}" class="btn btn-sm btn-outline-secondary px-3" title="Reset Filters">
                        <i class="fa-solid fa-rotate-left me-1"></i> Reset
                    </a>
                    @endif
                </div>
            </form>
        </div>
    </div>

    <!-- ── TAB 1: ALL EXPENSES HUB ───────────────────────────────────────────── -->
    @if($activeTab === 'all_expenses')
    <div class="card border-0 shadow-sm rounded-3">
        <!-- Bulk Action Header Bar -->
        <div class="card-header bg-white py-2.5 border-bottom border-light d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div class="d-flex align-items-center gap-2">
                <div class="form-check mb-0">
                    <input class="form-check-input" type="checkbox" id="selectAllCheckbox" title="Select All on page">
                    <label class="form-check-label text-xs fw-bold text-dark" for="selectAllCheckbox">Select All</label>
                </div>
                <div id="bulkActionContainer" class="d-none align-items-center gap-2 ms-3">
                    <span class="text-xs text-muted fw-semibold"><span id="selectedCountBadge">0</span> selected</span>
                    @if($canDecideVat)
                    <button type="button" class="btn btn-xs btn-outline-success rounded-pill px-2.5" onclick="openBulkModal('vat_applies')">
                        <i class="fa-solid fa-check me-1"></i> Bulk: VAT Applies
                    </button>
                    <button type="button" class="btn btn-xs btn-outline-secondary rounded-pill px-2.5" onclick="openBulkModal('no_vat')">
                        <i class="fa-solid fa-ban me-1"></i> Bulk: No VAT
                    </button>
                    <button type="button" class="btn btn-xs btn-outline-info rounded-pill px-2.5" onclick="openBulkModal('verified_no_receipt')">
                        <i class="fa-solid fa-check-double me-1"></i> Bulk: Verified w/o Receipt
                    </button>
                    @endif
                </div>
            </div>
            <div class="text-xs text-muted">
                Showing {{ $paginatedExpenses->firstItem() ?? 0 }} - {{ $paginatedExpenses->lastItem() ?? 0 }} of {{ $paginatedExpenses->total() }} expenses
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 text-sm">
                <thead class="table-light text-xxs text-uppercase text-muted">
                    <tr>
                        <th width="35" class="text-center">#</th>
                        <th style="min-width: 85px;">Date</th>
                        <th style="min-width: 250px;">Expense Details &amp; Audit Trail</th>
                        <th style="min-width: 120px;">Project / Site</th>
                        <th style="min-width: 150px;">Payee / Supplier</th>
                        <th style="min-width: 95px;">Vendor TIN</th>
                        <th class="text-end" style="min-width: 105px;">Amount (ETB)</th>
                        <th class="text-center" style="min-width: 130px;">VAT Decision</th>
                        <th class="text-end" style="min-width: 95px;">Base (ETB)</th>
                        <th class="text-end" style="min-width: 95px;">VAT (15%)</th>
                        <th class="text-center" style="min-width: 60px;">Receipt</th>
                        <th class="text-end" style="min-width: 140px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($paginatedExpenses as $item)
                    @php
                        $isVerifiedNoReceipt = !empty($item->is_verified_without_receipt);
                        $statusBadgeClass = match(true) {
                            $isVerifiedNoReceipt                                                 => 'bg-info bg-opacity-20 text-info border border-info',
                            $item->vat_status === 'vat_applies',
                            $item->vat_status === \App\Models\ExpenseHubVatDecision::STATUS_VAT_APPLIES => 'bg-success text-white',
                            $item->vat_status === 'no_vat',
                            $item->vat_status === \App\Models\ExpenseHubVatDecision::STATUS_NO_VAT       => 'bg-secondary text-white',
                            default                                                              => 'bg-warning text-dark border border-warning',
                        };
                        $statusLabel = match(true) {
                            $isVerifiedNoReceipt                                                 => 'Verified w/o Receipt',
                            $item->vat_status === 'vat_applies',
                            $item->vat_status === \App\Models\ExpenseHubVatDecision::STATUS_VAT_APPLIES => 'VAT Applies',
                            $item->vat_status === 'no_vat',
                            $item->vat_status === \App\Models\ExpenseHubVatDecision::STATUS_NO_VAT       => 'No VAT',
                            default                                                              => 'Pending Decision',
                        };
                    @endphp
                    <tr class="{{ !empty($item->warnings) ? 'table-warning bg-opacity-10' : '' }}"
                        id="hub-row-{{ $item->source_type }}-{{ $item->source_id }}"
                        data-source-type="{{ $item->source_type }}"
                        data-source-id="{{ $item->source_id }}"
                        data-ref-no="{{ $item->reference_no ?? '' }}"
                        data-payee="{{ $item->payee_supplier ?? '' }}"
                        data-tin="{{ $item->tin ?? '' }}"
                        data-amount="{{ $item->amount ?? 0 }}"
                        data-vat-status="{{ $item->vat_status ?? 'pending_decision' }}"
                        data-is-verified-without-receipt="{{ !empty($item->is_verified_without_receipt) ? '1' : '0' }}"
                        data-vat-rate="{{ $item->vat_rate ?? 15 }}"
                        data-is-inclusive="{{ !empty($item->is_inclusive) ? '1' : '0' }}"
                        data-base-amount="{{ $item->decision_base_amount ?? 0 }}"
                        data-vat-amount="{{ $item->decision_vat_amount ?? 0 }}"
                        data-total-amount="{{ $item->decision_total_amount ?? ($item->amount ?? 0) }}"
                        data-receipt-url="{{ $item->receipt_url ?? '' }}"
                        data-disbursement-date="{{ $item->disbursement_date ?? '' }}"
                        data-module-label="{{ $item->source_module_label ?? 'Expense' }}"
                        data-fs-no="{{ $item->fs_no ?? '' }}"
                        data-mrc-no="{{ $item->mrc_no ?? '' }}"
                        data-purpose="{{ $item->purpose_detail ?? ($item->description ?? '') }}"
                        data-reason-code="{{ $item->reason_code ?? '' }}"
                        data-reason-note="{{ $item->reason_note ?? '' }}"
                        data-has-withholding="{{ !empty($item->has_withholding) || (float)($item->withholding_amount ?? 0) > 0 ? '1' : '0' }}"
                        data-withholding-rate="{{ $item->withholding_rate ?? 3 }}"
                        data-withholding-amount="{{ $item->withholding_amount ?? 0 }}"
                        data-withholding-receipt="{{ $item->withholding_receipt ?? '' }}"
                        data-withholding-receipt-number="{{ $item->withholding_receipt_number ?? '' }}"
                        data-net-payable-amount="{{ $item->net_payable_amount ?? 0 }}"
                        data-created-by="{{ $item->created_by_name ?? '' }}"
                        data-created-at="{{ $item->created_at_formatted ?? '' }}"
                        data-approved-by="{{ $item->approved_by_name ?? '' }}"
                        data-approved-at="{{ $item->approved_at_formatted ?? '' }}"
                        data-paid-by="{{ $item->paid_by_name ?? '' }}"
                        data-paid-at="{{ $item->paid_at_formatted ?? '' }}"
                        data-view-url="{{ $item->view_url ?? '' }}">
                        <!-- Checkbox -->
                        <td class="text-center">
                            <input class="form-check-input item-checkbox" type="checkbox" 
                                   value="{{ $item->source_type }}:{{ $item->source_id }}:{{ $item->amount }}"
                                   data-status="{{ $item->vat_status }}">
                        </td>

                        <!-- Date -->
                        <td class="text-nowrap text-xs text-muted">
                            {{ $item->disbursement_date ? \Carbon\Carbon::parse($item->disbursement_date)->format('d M Y') : '—' }}
                        </td>

                        <!-- Source Module, Purpose & Audit Trail -->
                        <td>
                            <div class="d-flex align-items-center gap-1.5 flex-wrap">
                                <div class="fw-semibold text-dark text-xs text-truncate" style="max-width: 170px;">
                                    @if($item->view_url)
                                        <a href="{{ $item->view_url }}" target="_blank" class="text-decoration-none text-dark" title="Open source record">
                                            {{ $item->reference_no }} <i class="fa-solid fa-arrow-up-right-from-square text-muted text-xxs ms-1"></i>
                                        </a>
                                    @else
                                        {{ $item->reference_no }}
                                    @endif
                                </div>
                                <span class="badge bg-light text-muted border text-xxs py-0.5 px-1.5">
                                    {{ $item->source_module_label }}
                                </span>
                            </div>

                            <!-- What the expense was for -->
                            <div class="text-xs text-secondary mt-1 text-truncate" style="max-width: 280px;" title="{{ $item->purpose_detail ?? $item->description }}">
                                <i class="fa-solid fa-tag text-muted me-1"></i><strong>For:</strong> {{ Str::limit($item->purpose_detail ?? $item->description, 50) }}
                            </div>

                            <!-- Complete 3-step Audit Badges -->
                            <div class="d-flex flex-wrap gap-1 mt-1 text-xxs">
                                <span class="badge bg-white text-dark border py-0.5 px-1.5" title="Initiated / Created By: {{ $item->created_by_name ?? '—' }} ({{ $item->created_at_formatted ?? '' }})">
                                    <i class="fa-solid fa-user-pen text-primary me-0.5"></i>{{ Str::limit($item->created_by_name ?? '—', 15) }}
                                </span>
                                <span class="badge bg-white text-dark border py-0.5 px-1.5" title="Approved By: {{ $item->approved_by_name ?? '—' }} ({{ $item->approved_at_formatted ?? '' }})">
                                    <i class="fa-solid fa-circle-check text-success me-0.5"></i>{{ Str::limit($item->approved_by_name ?? '—', 15) }}
                                </span>
                                <span class="badge bg-white text-dark border py-0.5 px-1.5" title="Paid By: {{ $item->paid_by_name ?? '—' }} on {{ $item->disbursement_date ? \Carbon\Carbon::parse($item->disbursement_date)->format('d M Y') : '—' }}">
                                    <i class="fa-solid fa-money-bill-wave text-secondary me-0.5"></i>{{ Str::limit($item->paid_by_name ?? '—', 15) }}
                                </span>
                            </div>
                        </td>

                        <!-- Project -->
                        <td class="text-xs">
                            <span class="fw-semibold text-dark">{{ Str::limit($item->project_name, 20) }}</span>
                        </td>

                        <!-- Payee / Supplier -->
                        <td>
                            <div class="fw-semibold text-dark text-xs text-truncate" style="max-width: 180px;" title="{{ $item->payee_supplier }}">
                                {{ $item->payee_supplier }}
                            </div>
                            @if(!empty($item->warnings))
                                <div class="text-danger text-xxs mt-0.5 d-flex align-items-center gap-1" title="{{ implode('; ', $item->warnings) }}">
                                    <i class="fa-solid fa-triangle-exclamation"></i>
                                    <span>{{ $item->warnings[0] }}</span>
                                </div>
                            @endif
                        </td>

                        <!-- TIN -->
                        <td class="text-xs font-monospace">
                            @if($item->tin)
                                <span class="badge bg-light text-dark border">{{ $item->tin }}</span>
                            @else
                                <span class="text-muted text-xxs">—</span>
                            @endif
                        </td>

                        <!-- Amount (Gross) -->
                        <td class="text-end fw-bold text-dark text-xs">
                            {{ number_format($item->amount, 2) }}
                        </td>

                        <!-- VAT Decision Badge (Interactive) -->
                        <td class="text-center">
                            @if($canDecideVat && !$item->is_locked)
                                <button type="button" class="btn btn-xs rounded-pill px-2.5 py-1 text-xxs fw-semibold {{ $statusBadgeClass }} btn-open-decision"
                                        data-source-type="{{ $item->source_type }}"
                                        data-source-id="{{ $item->source_id }}"
                                        onclick="openDecisionModal('{{ $item->source_type }}', '{{ $item->source_id }}', this)" title="Click to review or edit VAT decision">
                                    {{ $statusLabel }} <i class="fa-solid fa-pen ms-1 opacity-75"></i>
                                </button>
                            @else
                                <span class="badge rounded-pill px-2.5 py-1 text-xxs fw-semibold {{ $statusBadgeClass }}">
                                    {{ $statusLabel }}
                                    @if($item->is_locked) <i class="fa-solid fa-lock ms-1"></i> @endif
                                </span>
                            @endif

                            @if($item->vat_status === 'no_vat' && $item->reason_code)
                                <div class="text-xxs text-muted mt-0.5 text-truncate" style="max-width: 130px;" title="{{ $item->reason_note ?: ($reasons[$item->reason_code] ?? $item->reason_code) }}">
                                    {{ $reasons[$item->reason_code] ?? $item->reason_code }}
                                </div>
                            @endif

                            @if(!empty($item->has_withholding) || (float)($item->withholding_amount ?? 0) > 0)
                                <div class="mt-0.5">
                                    <span class="badge bg-warning bg-opacity-20 text-dark border border-warning text-xxs" title="3% Withholding Deducted: {{ number_format($item->withholding_amount, 2) }} ETB">
                                        <i class="fa-solid fa-stamp text-warning me-0.5"></i> 3% WHT ({{ number_format($item->withholding_amount, 2) }})
                                    </span>
                                </div>
                            @endif
                        </td>

                        <!-- Base & VAT -->
                        <td class="text-end text-xs text-muted">
                            {{ $item->vat_status === 'vat_applies' ? number_format($item->decision_base_amount, 2) : '—' }}
                        </td>
                        <td class="text-end text-xs {{ $item->decision_vat_amount > 0 ? 'text-success fw-bold' : 'text-muted' }}">
                            {{ $item->vat_status === 'vat_applies' && $item->decision_vat_amount > 0 ? number_format($item->decision_vat_amount, 2) : '—' }}
                        </td>

                        <!-- Receipt Link -->
                        <td class="text-center text-nowrap">
                            @if($item->receipt_url)
                                <a href="{{ Str::startsWith($item->receipt_url, ['http://', 'https://']) ? $item->receipt_url : asset('storage/' . ltrim($item->receipt_url, '/')) }}" target="_blank" 
                                   class="btn btn-xs btn-outline-info rounded-pill px-2 py-0.5" title="View attached receipt / voucher">
                                    <i class="fa-solid fa-paperclip"></i>
                                </a>
                            @elseif($isVerifiedNoReceipt)
                                <span class="badge bg-light text-muted border text-xxs" title="Audited and verified without receipt">
                                    <i class="fa-solid fa-check-double text-info me-0.5"></i> No Slip
                                </span>
                            @else
                                @if($canDecideVat && !$item->is_locked)
                                    <button type="button" class="btn btn-xs btn-outline-primary border-dashed rounded-pill px-2 py-0.5 text-xxs"
                                            onclick="openAddReceiptAction('{{ $item->source_type }}', '{{ $item->source_id }}', this)" title="Add Receipt to this expense">
                                        <i class="fa-solid fa-plus me-0.5"></i> Add
                                    </button>
                                @else
                                    <span class="text-muted text-xxs">—</span>
                                @endif
                            @endif

                            @if(!empty($item->withholding_receipt))
                                <a href="{{ Str::startsWith($item->withholding_receipt, ['http://', 'https://']) ? $item->withholding_receipt : asset('storage/' . ltrim($item->withholding_receipt, '/')) }}" target="_blank" 
                                   class="btn btn-xs btn-outline-warning rounded-pill px-1.5 py-0.5 ms-0.5" title="View Withholding Receipt #{{ $item->withholding_receipt_number ?? '' }}">
                                    <i class="fa-solid fa-stamp"></i> WHT
                                </a>
                            @endif
                        </td>

                        <!-- Action -->
                        <td class="text-end text-nowrap">
                            <div class="d-inline-flex align-items-center gap-1">
                                <button type="button" class="btn btn-xs btn-outline-secondary rounded-pill px-2 py-1 text-xs fw-semibold btn-view-details"
                                        data-source-type="{{ $item->source_type }}"
                                        data-source-id="{{ $item->source_id }}"
                                        onclick="openExpenseDetailModal('{{ $item->source_type }}', '{{ $item->source_id }}', this)" title="View Full Purpose, Line Items &amp; Audit Trail">
                                    <i class="fa-solid fa-circle-info"></i>
                                </button>

                                @if($canDecideVat && !$item->is_locked)
                                <div class="dropdown d-inline-block">
                                    <button class="btn btn-xs btn-primary dropdown-toggle rounded-pill px-2.5 py-1 text-xs fw-semibold shadow-xs"
                                            type="button" data-bs-toggle="dropdown" data-bs-auto-close="true" aria-expanded="false">
                                        <i class="fa-solid fa-bolt me-1"></i> Actions
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-end shadow-lg border-0 rounded-3 p-1.5 text-xs" style="min-width: 250px; z-index: 1055;">
                                        <li class="dropdown-header text-xxs text-uppercase fw-bold text-muted px-2 py-1">
                                            <i class="fa-solid fa-sliders me-1"></i> Expense Tax &amp; Receipt
                                        </li>
                                        <li>
                                            <a class="dropdown-item py-1.5 px-2.5 rounded-2 d-flex align-items-center gap-2" href="#"
                                               onclick="openAddReceiptAction('{{ $item->source_type }}', '{{ $item->source_id }}', this); return false;">
                                                <div class="bg-primary bg-opacity-10 text-primary rounded-circle p-1.5 d-flex align-items-center justify-content-center" style="width: 28px; height: 28px;">
                                                    <i class="fa-solid fa-cloud-arrow-up text-xxs"></i>
                                                </div>
                                                <div>
                                                    <div class="fw-bold text-dark text-xs">Add Receipt</div>
                                                    <div class="text-xxs text-muted">Upload slip or scan with AI OCR</div>
                                                </div>
                                            </a>
                                        </li>
                                        <li>
                                            <a class="dropdown-item py-1.5 px-2.5 rounded-2 d-flex align-items-center gap-2" href="#"
                                               onclick="openAddVatAction('{{ $item->source_type }}', '{{ $item->source_id }}', this); return false;">
                                                <div class="bg-success bg-opacity-10 text-success rounded-circle p-1.5 d-flex align-items-center justify-content-center" style="width: 28px; height: 28px;">
                                                    <i class="fa-solid fa-percent text-xxs"></i>
                                                </div>
                                                <div>
                                                    <div class="fw-bold text-success text-xs">Add VAT Payment</div>
                                                    <div class="text-xxs text-muted">Input VAT at 15% rate</div>
                                                </div>
                                            </a>
                                        </li>
                                        <li>
                                            <a class="dropdown-item py-1.5 px-2.5 rounded-2 d-flex align-items-center gap-2" href="#"
                                               onclick="openNonVatAction('{{ $item->source_type }}', '{{ $item->source_id }}', this); return false;">
                                                <div class="bg-secondary bg-opacity-10 text-secondary rounded-circle p-1.5 d-flex align-items-center justify-content-center" style="width: 28px; height: 28px;">
                                                    <i class="fa-solid fa-receipt text-xxs"></i>
                                                </div>
                                                <div>
                                                    <div class="fw-bold text-dark text-xs">Non-VAT Payment</div>
                                                    <div class="text-xxs text-muted">0% VAT / TAS exempt purchase</div>
                                                </div>
                                            </a>
                                        </li>
                                        <li>
                                            <a class="dropdown-item py-1.5 px-2.5 rounded-2 d-flex align-items-center gap-2" href="#"
                                               onclick="openVerifiedWithoutReceiptAction('{{ $item->source_type }}', '{{ $item->source_id }}', this); return false;">
                                                <div class="bg-info bg-opacity-10 text-info rounded-circle p-1.5 d-flex align-items-center justify-content-center" style="width: 28px; height: 28px;">
                                                    <i class="fa-solid fa-check-double text-xxs"></i>
                                                </div>
                                                <div>
                                                    <div class="fw-bold text-info text-xs">Verified Without Receipt</div>
                                                    <div class="text-xxs text-muted">Petty cash / informal vendor / no slip</div>
                                                </div>
                                            </a>
                                        </li>
                                        <li><hr class="dropdown-divider my-1"></li>
                                        <li>
                                            <a class="dropdown-item py-1.5 px-2.5 rounded-2 d-flex align-items-center gap-2 text-muted" href="#"
                                               onclick="openExpenseDetailModal('{{ $item->source_type }}', '{{ $item->source_id }}', this); return false;">
                                                <i class="fa-solid fa-circle-info fa-fw text-secondary"></i>
                                                <span class="text-dark">View Purpose &amp; Line Items</span>
                                            </a>
                                        </li>
                                        <li>
                                            <a class="dropdown-item py-1.5 px-2.5 rounded-2 d-flex align-items-center gap-2 text-muted" href="#"
                                               onclick="openDecisionModal('{{ $item->source_type }}', '{{ $item->source_id }}', this); return false;">
                                                <i class="fa-solid fa-sliders fa-fw text-secondary"></i>
                                                <span class="text-dark">Full Studio (3% WHT, TAS)</span>
                                            </a>
                                        </li>
                                    </ul>
                                </div>
                                @elseif($item->is_locked)
                                <span class="badge bg-light text-muted border text-xxs py-1 px-2" title="Month is locked">
                                    <i class="fa-solid fa-lock me-1"></i> Locked
                                </span>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="12" class="text-center py-5 text-muted">
                            <i class="fa-solid fa-calculator fa-3x mb-3 opacity-25 text-secondary"></i>
                            <h6 class="fw-bold mb-1">No expenses found</h6>
                            <p class="text-xs mb-3">Adjust your filters or clear them to view all expenses.</p>
                            <a href="{{ route('finance.expense-hub.index', ['tab' => $activeTab]) }}" class="btn btn-sm btn-outline-secondary">Reset Filters</a>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($paginatedExpenses->hasPages())
        <div class="card-footer bg-white py-3 border-top border-light d-flex justify-content-between align-items-center">
            <div class="text-xs text-muted">
                Showing {{ $paginatedExpenses->firstItem() }} to {{ $paginatedExpenses->lastItem() }} of {{ $paginatedExpenses->total() }} records
            </div>
            <div>
                {{ $paginatedExpenses->links() }}
            </div>
        </div>
        @endif
    </div>
    @endif

    <!-- ── TAB 2: OFFICIAL VAT REPORT (15 COLUMNS) ───────────────────────────── -->
    @if($activeTab === 'vat_report')
    <div class="card border-0 shadow-sm rounded-3">
        <div class="card-header bg-white py-2.5 border-bottom border-light d-flex justify-content-between align-items-center">
            <div>
                <h6 class="mb-0 fw-bold text-dark d-flex align-items-center gap-2">
                    <i class="fa-solid fa-file-excel text-success"></i> Official Ministry of Revenue (MOR/ERCA) Purchase VAT Format
                </h6>
                <span class="text-xxs text-muted">Matches exact 15-column format from <code>FinanceTaxReportController.php</code>. Only records with "VAT applies" are included.</span>
            </div>
            <a href="{{ route('finance.expense-hub.export-vat', request()->query()) }}" class="btn btn-success btn-sm shadow-xs">
                <i class="fa-solid fa-file-excel me-1"></i> Download Official XLSX / CSV
            </a>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 text-xs table-bordered border-light">
                <thead class="table-light text-xxs text-uppercase text-muted">
                    <tr>
                        <th>1. VAT Cat</th>
                        <th>2. Cal</th>
                        <th>3. Purc Type</th>
                        <th>4. TIN</th>
                        <th>5. Seller Name</th>
                        <th>6. Date (EC)</th>
                        <th>7. MRC No</th>
                        <th>8. FS Receipt #</th>
                        <th>9. Description / Item</th>
                        <th>10. UOM</th>
                        <th class="text-end">11. Qty</th>
                        <th class="text-end">12. Unit Price</th>
                        <th class="text-end">13. Total Base</th>
                        <th class="text-end">14. VAT (15%)</th>
                        <th class="text-end">15. Value After VAT</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($vatReportItems as $v)
                    @php
                        $ethDate = $v->disbursement_date
                            ? \App\Services\EthiopianDate::formatDateEc($v->disbursement_date, 'DD/MM/YYYY EC')
                            : \App\Services\EthiopianDate::formatDateEc(now(), 'DD/MM/YYYY EC');
                    @endphp
                    <tr>
                        <td class="text-center fw-bold">{{ $v->vat_category ?? 'G' }}</td>
                        <td class="text-center">{{ $v->calendar_type ?? 'E' }}</td>
                        <td class="text-center"><span class="badge bg-light text-dark border">{{ $v->purchase_type ?? 3 }}</span></td>
                        <td class="font-monospace fw-semibold">{{ $v->tin ?? '—' }}</td>
                        <td class="fw-semibold text-dark">{{ $v->payee_supplier ?? '—' }}</td>
                        <td class="text-nowrap">{{ $ethDate }}</td>
                        <td class="text-muted">{{ $v->mrc_no ?? '—' }}</td>
                        <td class="fw-semibold">{{ $v->fs_no ?? '—' }}</td>
                        <td>{{ Str::limit($v->item_desc ?? ($v->description ?? ''), 30) }}</td>
                        <td class="text-center">{{ $v->uom ?? '9' }}</td>
                        <td class="text-end">{{ number_format($v->qty ?? 1, 2) }}</td>
                        <td class="text-end">{{ number_format($v->unit_price ?? 0, 2) }}</td>
                        <td class="text-end fw-semibold">{{ number_format($v->decision_base_amount ?? 0, 2) }}</td>
                        <td class="text-end fw-bold text-success">{{ number_format($v->decision_vat_amount ?? 0, 2) }}</td>
                        <td class="text-end fw-bold text-dark">{{ number_format($v->decision_total_amount ?? 0, 2) }}</td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="15" class="text-center py-5 text-muted">
                            <i class="fa-solid fa-receipt fa-3x mb-3 opacity-25 text-success"></i>
                            <h6 class="fw-bold mb-1">No items with "VAT applies" yet</h6>
                            <p class="text-xs mb-0">Classify expenses under the "All Expenses Hub" tab to populate this official filing report.</p>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
                @if($vatReportItems->isNotEmpty())
                <tfoot class="table-light fw-bold">
                    <tr>
                        <td colspan="12" class="text-end text-uppercase">Totals (ETB):</td>
                        <td class="text-end">{{ number_format($vatReportItems->sum('decision_base_amount'), 2) }}</td>
                        <td class="text-end text-success">{{ number_format($vatReportItems->sum('decision_vat_amount'), 2) }}</td>
                        <td class="text-end text-dark">{{ number_format($vatReportItems->sum('decision_total_amount'), 2) }}</td>
                    </tr>
                </tfoot>
                @endif
            </table>
        </div>
    </div>
    @endif

    <!-- ── TAB 3: VAT MONTHLY SUMMARY ────────────────────────────────────────── -->
    @if($activeTab === 'vat_summary')
    <div class="card border-0 shadow-sm rounded-3">
        <div class="card-header bg-white py-2.5 border-bottom border-light d-flex justify-content-between align-items-center">
            <div>
                <h6 class="mb-0 fw-bold text-dark d-flex align-items-center gap-2">
                    <i class="fa-solid fa-chart-column text-primary"></i> Monthly VAT Filing &amp; Pending Reconciliation
                </h6>
                <span class="text-xxs text-muted">Summary of Input VAT, Exempt/Salary Outflows, and Pending Decisions per month.</span>
            </div>
            @if($isFinanceHead)
            <button type="button" class="btn btn-outline-dark btn-sm rounded-pill px-3 shadow-xs" data-bs-toggle="modal" data-bs-target="#monthLockModal">
                <i class="fa-solid fa-lock me-1"></i> Lock / Unlock Month
            </button>
            @endif
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 text-xs">
                <thead class="table-light text-xxs text-uppercase text-muted">
                    <tr>
                        <th>Month Period</th>
                        <th class="text-end">Total Gross Outflow</th>
                        <th class="text-end">Taxable Base Subtotal</th>
                        <th class="text-end text-success">Total Input VAT (15%)</th>
                        <th class="text-end">No-VAT Amount</th>
                        <th class="text-end text-warning-emphasis">Pending Decision Amount</th>
                        <th class="text-center">Pending Count</th>
                        <th class="text-center">Month Lock Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($monthlyVatSummary as $m)
                    <tr>
                        <td class="fw-bold text-dark fs-6">{{ $m->month_label }}</td>
                        <td class="text-end fw-bold">{{ number_format($m->total_gross, 2) }}</td>
                        <td class="text-end">{{ number_format($m->vat_base, 2) }}</td>
                        <td class="text-end fw-bold text-success fs-6">{{ number_format($m->input_vat, 2) }} ETB</td>
                        <td class="text-end text-muted">{{ number_format($m->no_vat, 2) }}</td>
                        <td class="text-end fw-bold {{ $m->pending > 0 ? 'text-warning-emphasis' : 'text-muted' }}">
                            {{ number_format($m->pending, 2) }} ETB
                        </td>
                        <td class="text-center">
                            @if($m->pending_count > 0)
                                <span class="badge bg-warning text-dark rounded-pill px-2.5 py-1 fw-bold">
                                    ⚠️ {{ $m->pending_count }} Pending
                                </span>
                            @else
                                <span class="badge bg-success bg-opacity-20 text-success rounded-pill px-2.5 py-1">
                                    <i class="fa-solid fa-check"></i> 100% Classified
                                </span>
                            @endif
                        </td>
                        <td class="text-center">
                            @if($m->is_locked)
                                <span class="badge bg-dark rounded-pill px-2.5 py-1"><i class="fa-solid fa-lock me-1"></i> Locked</span>
                            @else
                                <span class="badge bg-light text-muted border rounded-pill px-2.5 py-1">Open</span>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="8" class="text-center py-5 text-muted">
                            <p class="mb-0">No monthly summary data available.</p>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @endif

</div>

<!-- ── MODAL: COMPREHENSIVE EXPENDITURE & AUDIT BREAKDOWN ──────────────────── -->
<div class="modal fade" id="expenseDetailModal" tabindex="-1" aria-labelledby="expenseDetailModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-light py-2.5 d-flex justify-content-between align-items-center">
                <div class="d-flex align-items-center gap-2">
                    <span class="badge bg-primary px-2.5 py-1 text-xs" id="dModalModuleBadge">Module</span>
                    <h6 class="modal-title fw-bold text-dark mb-0" id="expenseDetailModalLabel">
                        Expenditure Audit &amp; Details: <span id="dRefNo" class="text-primary font-monospace"></span>
                    </h6>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <!-- Top Summary Alert / Card -->
                <div class="p-3 bg-light rounded-3 border mb-3">
                    <div class="row g-3 align-items-center">
                        <div class="col-md-6">
                            <span class="text-muted text-xxs text-uppercase fw-bold d-block">Payee / Beneficiary / Vendor</span>
                            <div class="fw-bold text-dark fs-6" id="dPayee">—</div>
                            <div class="text-muted text-xs mt-1">
                                TIN: <span class="font-monospace fw-semibold text-dark" id="dTin">—</span>
                                &bull; Project: <span class="fw-semibold text-dark" id="dProject">—</span>
                            </div>
                        </div>
                        <div class="col-md-6 text-md-end">
                            <span class="text-muted text-xxs text-uppercase fw-bold d-block">Disbursed Amount</span>
                            <div class="fs-4 fw-bold text-primary"><span id="dAmount">0.00</span> <span class="fs-6 text-muted">ETB</span></div>
                            <span class="badge" id="dVatBadge">Pending</span>
                        </div>
                    </div>
                </div>

                <!-- Section 1: What was this expense for? (Purpose & Scope) -->
                <div class="card border rounded-3 mb-3 shadow-none">
                    <div class="card-header bg-white py-2 border-bottom d-flex align-items-center gap-1.5">
                        <i class="fa-solid fa-clipboard-list text-primary"></i>
                        <span class="fw-bold text-xs text-dark">What Was This Expense For? (Purpose &amp; Scope Breakdown)</span>
                    </div>
                    <div class="card-body p-3">
                        <div class="p-2.5 bg-light rounded-2 border text-dark fw-medium text-xs mb-3" id="dPurposeDetail">
                            —
                        </div>

                        <!-- Itemized Materials / Line Items Table -->
                        <div id="dLineItemsContainer" class="d-none">
                            <h6 class="text-xxs text-uppercase text-muted fw-bold mb-2 d-flex align-items-center gap-1">
                                <i class="fa-solid fa-list-check text-secondary"></i> Itemized Scope / Materials Breakdown:
                            </h6>
                            <div class="table-responsive rounded-2 border">
                                <table class="table table-sm table-hover align-middle mb-0 text-xs">
                                    <thead class="table-light text-xxs text-muted text-uppercase">
                                        <tr>
                                            <th>Item / Specification</th>
                                            <th class="text-center">Qty</th>
                                            <th class="text-center">Unit</th>
                                            <th class="text-end">Unit Rate (ETB)</th>
                                            <th class="text-end">Total Amount (ETB)</th>
                                        </tr>
                                    </thead>
                                    <tbody id="dLineItemsTableBody"></tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Section 2: Complete Operational Audit Trail (Created By, Approved By, Paid By) -->
                <div class="card border rounded-3 mb-3 shadow-none">
                    <div class="card-header bg-white py-2 border-bottom d-flex align-items-center gap-1.5">
                        <i class="fa-solid fa-shield-halved text-success"></i>
                        <span class="fw-bold text-xs text-dark">Operational &amp; Approval Audit Trail</span>
                    </div>
                    <div class="card-body p-3">
                        <div class="row g-3">
                            <!-- Step 1: Created By -->
                            <div class="col-md-4">
                                <div class="p-2.5 rounded-3 bg-light border h-100">
                                    <div class="d-flex align-items-center gap-1.5 mb-1.5">
                                        <span class="badge bg-primary bg-opacity-10 text-primary rounded-circle p-1">
                                            <i class="fa-solid fa-user-pen"></i>
                                        </span>
                                        <span class="fw-bold text-xs text-dark">1. Initiated / Created By</span>
                                    </div>
                                    <div class="fw-semibold text-dark text-xs" id="dCreatedBy">—</div>
                                    <div class="text-xxs text-muted mt-1" id="dCreatedAt"><i class="fa-regular fa-clock me-1"></i>—</div>
                                </div>
                            </div>

                            <!-- Step 2: Approved By -->
                            <div class="col-md-4">
                                <div class="p-2.5 rounded-3 bg-light border h-100">
                                    <div class="d-flex align-items-center gap-1.5 mb-1.5">
                                        <span class="badge bg-success bg-opacity-10 text-success rounded-circle p-1">
                                            <i class="fa-solid fa-circle-check"></i>
                                        </span>
                                        <span class="fw-bold text-xs text-dark">2. Approved By</span>
                                    </div>
                                    <div class="fw-semibold text-dark text-xs" id="dApprovedBy">—</div>
                                    <div class="text-xxs text-muted mt-1" id="dApprovedAt"><i class="fa-regular fa-clock me-1"></i>—</div>
                                </div>
                            </div>

                            <!-- Step 3: Paid / Disbursed By -->
                            <div class="col-md-4">
                                <div class="p-2.5 rounded-3 bg-light border h-100">
                                    <div class="d-flex align-items-center gap-1.5 mb-1.5">
                                        <span class="badge bg-info bg-opacity-10 text-info rounded-circle p-1">
                                            <i class="fa-solid fa-money-bill-wave"></i>
                                        </span>
                                        <span class="fw-bold text-xs text-dark">3. Paid &amp; Disbursed By</span>
                                    </div>
                                    <div class="fw-semibold text-dark text-xs" id="dPaidBy">—</div>
                                    <div class="text-xxs text-muted mt-1" id="dPaidAt"><i class="fa-regular fa-calendar-check me-1"></i>—</div>
                                    <div class="text-xxs text-muted mt-0.5" id="dPaymentMethod">Method: —</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Section 3: VAT Classification & MOR 15-Column Status -->
                <div class="card border rounded-3 mb-0 shadow-none">
                    <div class="card-header bg-white py-2 border-bottom d-flex justify-content-between align-items-center">
                        <span class="fw-bold text-xs text-dark d-flex align-items-center gap-1.5">
                            <i class="fa-solid fa-stamp text-warning"></i> VAT Classification &amp; MOR ERCA Compliance
                        </span>
                    </div>
                    <div class="card-body p-3">
                        <div class="row g-2 text-xs">
                            <div class="col-md-3">
                                <span class="text-muted d-block text-xxs">VAT Status:</span>
                                <strong id="dVatStatusLabel" class="text-dark"></strong>
                            </div>
                            <div class="col-md-3">
                                <span class="text-muted d-block text-xxs">Taxable Base Subtotal:</span>
                                <strong id="dBaseAmount" class="text-dark">0.00</strong> ETB
                            </div>
                            <div class="col-md-3">
                                <span class="text-muted d-block text-xxs">VAT Amount (15%):</span>
                                <strong id="dVatAmount" class="text-success">0.00</strong> ETB
                            </div>
                            <div class="col-md-3">
                                <span class="text-muted d-block text-xxs">Total Value:</span>
                                <strong id="dTotalAmount" class="text-dark">0.00</strong> ETB
                            </div>
                            <div class="col-md-4 mt-2">
                                <span class="text-muted d-block text-xxs">Fiscal Receipt # (FS):</span>
                                <span id="dFsNo" class="font-monospace fw-bold text-dark">—</span>
                            </div>
                            <div class="col-md-4 mt-2">
                                <span class="text-muted d-block text-xxs">Machine Registration # (MRC):</span>
                                <span id="dMrcNo" class="font-monospace text-dark">—</span>
                            </div>
                            <div class="col-md-4 mt-2">
                                <span class="text-muted d-block text-xxs">Exemption / Reason:</span>
                                <span id="dExemptionReason" class="text-muted">—</span>
                            </div>

                            <!-- Withholding Tax Section in Details -->
                            <div class="col-md-4 mt-2 pt-2 border-top">
                                <span class="text-muted d-block text-xxs">Withholding Tax (3%):</span>
                                <strong id="dWhtAmount" class="text-danger">—</strong>
                            </div>
                            <div class="col-md-4 mt-2 pt-2 border-top">
                                <span class="text-muted d-block text-xxs">Withholding Voucher #:</span>
                                <span id="dWhtReceiptNo" class="font-monospace text-dark">—</span>
                            </div>
                            <div class="col-md-4 mt-2 pt-2 border-top">
                                <span class="text-muted d-block text-xxs">Net Payable Amount:</span>
                                <strong id="dNetPayable" class="text-primary font-monospace">—</strong>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light py-2 px-4 d-flex justify-content-between align-items-center">
                <div>
                    <a id="dViewRecordBtn" href="#" target="_blank" class="btn btn-sm btn-outline-dark rounded-pill px-3 text-xs d-none">
                        <i class="fa-solid fa-arrow-up-right-from-square me-1"></i> View Source Record
                    </a>
                    <a id="dReceiptBtn" href="#" target="_blank" class="btn btn-sm btn-outline-info rounded-pill px-3 text-xs d-none ms-1">
                        <i class="fa-solid fa-paperclip me-1"></i> View Receipt File
                    </a>
                    <a id="dWhtReceiptBtn" href="#" target="_blank" class="btn btn-sm btn-outline-warning rounded-pill px-3 text-xs d-none ms-1">
                        <i class="fa-solid fa-stamp me-1"></i> View Withholding Receipt
                    </a>
                </div>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-sm btn-secondary rounded-pill px-3 text-xs" data-bs-dismiss="modal">Close</button>
                    @if($canDecideVat)
                    <button type="button" id="dBtnOpenDecision" class="btn btn-sm btn-primary rounded-pill px-3 text-xs fw-semibold">
                        <i class="fa-solid fa-sliders me-1"></i> Record / Edit VAT Decision
                    </button>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ── MODAL: SINGLE ROW VAT DECISION STUDIO ───────────────────────────────── -->
<div class="modal fade" id="decisionModal" tabindex="-1" aria-labelledby="decisionModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-light py-2.5">
                <h6 class="modal-title fw-bold text-dark" id="decisionModalLabel">
                    <i class="fa-solid fa-sliders text-primary me-1.5"></i> Record VAT Decision: <span id="mRefNo" class="text-primary"></span>
                </h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="decisionForm" method="POST" action="{{ route('finance.expense-hub.decision') }}" enctype="multipart/form-data">
                @csrf
                <input type="hidden" name="source_type" id="mSourceType">
                <input type="hidden" name="source_id" id="mSourceId">
                <input type="hidden" name="amount" id="mAmount">
                <input type="hidden" name="custom_receipt_date" id="mReceiptDate">
                <input type="hidden" name="receipt_path" id="mReceiptPath">
                <input type="hidden" name="withholding_receipt" id="mWithholdingReceiptPath">
                <input type="hidden" name="custom_item_description" id="mItemDescription">
                <input type="hidden" name="purc_type" id="mPurcType" value="PUR_GD">
                <input type="hidden" name="is_verified_without_receipt" id="mIsVerifiedWithoutReceipt" value="0">

                <div class="modal-body p-4">
                    <!-- Item Overview Row with Full Purpose & Audit Trail -->
                    <div class="p-3 bg-light rounded-3 mb-3 border">
                        <div class="row g-2 text-xs">
                            <div class="col-md-4">
                                <span class="text-muted d-block text-xxs">Payee / Supplier:</span>
                                <strong id="mPayee" class="text-dark"></strong>
                            </div>
                            <div class="col-md-4">
                                <span class="text-muted d-block text-xxs">Source Module:</span>
                                <strong id="mSourceModule" class="text-dark"></strong>
                            </div>
                            <div class="col-md-4">
                                <span class="text-muted d-block text-xxs">Disbursed Amount:</span>
                                <strong id="mAmountDisplay" class="text-dark fs-6"></strong> ETB
                            </div>
                        </div>
                        <div class="mt-2 pt-2 border-top">
                            <span class="text-muted text-xxs d-block fw-bold text-uppercase"><i class="fa-solid fa-tag me-1 text-primary"></i> Expense Purpose / What for:</span>
                            <div id="mPurpose" class="text-dark fw-semibold text-xs mt-0.5"></div>
                        </div>
                        <div class="row g-2 mt-2 pt-2 border-top text-xxs">
                            <div class="col-md-4">
                                <span class="text-muted d-block"><i class="fa-solid fa-user-pen text-primary me-1"></i> Initiated By:</span>
                                <strong id="mCreatedBy" class="text-dark">—</strong>
                            </div>
                            <div class="col-md-4">
                                <span class="text-muted d-block"><i class="fa-solid fa-circle-check text-success me-1"></i> Approved By:</span>
                                <strong id="mApprovedBy" class="text-dark">—</strong>
                            </div>
                            <div class="col-md-4">
                                <span class="text-muted d-block"><i class="fa-solid fa-money-bill-wave text-secondary me-1"></i> Paid By:</span>
                                <strong id="mPaidBy" class="text-dark">—</strong>
                            </div>
                        </div>
                        <div class="mt-2.5 text-end">
                            <button type="button" onclick="openDetailFromDecision()" class="btn btn-xs btn-outline-secondary rounded-pill px-2.5 py-1 text-xxs">
                                <i class="fa-solid fa-layer-group me-1 text-info"></i> View Full Scope &amp; Line Items Breakdown
                            </button>
                        </div>
                    </div>

                    <!-- AI OCR Receipt Verification Section -->
                    <div class="card border border-primary border-opacity-25 bg-primary bg-opacity-10 rounded-3 mb-3 shadow-none">
                        <div class="card-body p-3">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span class="fw-bold text-xs text-primary d-flex align-items-center gap-1.5">
                                    <i class="fa-solid fa-wand-magic-sparkles"></i> AI OCR Receipt Verification
                                </span>
                                <span id="ocrStatusBadge" class="badge bg-white text-primary border text-xxs shadow-xs">Scan &amp; Verify</span>
                            </div>

                            <!-- Scan Controls -->
                            <div class="row g-2 align-items-center">
                                <div class="col-md-7">
                                    <input type="file" name="receipt_file" id="mOcrFileInput" accept="image/*,.pdf" class="form-control form-control-sm text-xs bg-white" placeholder="Choose receipt file">
                                </div>
                                <div class="col-md-5 d-flex gap-1.5">
                                    <button type="button" id="btnUploadOcrScan" class="btn btn-primary btn-sm flex-fill text-xs shadow-xs" onclick="runOcrUpload()">
                                        <i class="fa-solid fa-wand-magic-sparkles me-1"></i> Scan Upload
                                    </button>
                                    <button type="button" id="btnAttachedOcrScan" class="btn btn-outline-primary btn-sm flex-fill text-xs d-none" onclick="runOcrAttached()">
                                        <i class="fa-solid fa-paperclip me-1"></i> Scan Attached
                                    </button>
                                </div>
                            </div>

                            <!-- Progress Spinner -->
                            <div id="ocrSpinner" class="d-none text-center py-2.5 mt-2">
                                <div class="spinner-border spinner-border-sm text-primary" role="status"></div>
                                <span class="ms-2 text-xs fw-semibold text-primary">Running AI OCR Vision Pipeline &amp; verifying receipt...</span>
                            </div>

                            <!-- OCR Result Box -->
                            <div id="ocrResultBox" class="d-none mt-2.5 p-2.5 rounded-3 bg-white border text-xs"></div>
                        </div>
                    </div>

                    <!-- VAT Decision Radio Group -->
                    <div class="mb-3">
                        <label class="form-label text-xs fw-bold text-uppercase text-muted">Select VAT Classification <span class="text-danger">*</span></label>
                        <div class="d-flex gap-2 flex-wrap">
                            <input type="radio" class="btn-check" name="vat_status" id="optVatApplies" value="vat_applies" onchange="toggleVatFields()">
                            <label class="btn btn-outline-success flex-fill py-2 text-xs fw-semibold" for="optVatApplies">
                                <i class="fa-solid fa-check me-1"></i> VAT Applies (Input VAT)
                            </label>

                            <input type="radio" class="btn-check" name="vat_status" id="optNoVat" value="no_vat" onchange="toggleVatFields()">
                            <label class="btn btn-outline-secondary flex-fill py-2 text-xs fw-semibold" for="optNoVat">
                                <i class="fa-solid fa-receipt me-1"></i> No VAT / TAS (0%)
                            </label>

                            <input type="radio" class="btn-check" name="vat_status" id="optVerifiedNoReceipt" value="verified_no_receipt" onchange="toggleVatFields()">
                            <label class="btn btn-outline-info flex-fill py-2 text-xs fw-semibold" for="optVerifiedNoReceipt">
                                <i class="fa-solid fa-check-double me-1"></i> Verified Without Receipt
                            </label>

                            <input type="radio" class="btn-check" name="vat_status" id="optPending" value="pending_decision" onchange="toggleVatFields()">
                            <label class="btn btn-outline-warning flex-fill py-2 text-xs fw-semibold" for="optPending">
                                <i class="fa-solid fa-clock-rotate-left me-1"></i> Pending Decision
                            </label>
                        </div>
                    </div>

                    <!-- VAT Applies Configuration Section -->
                    <div id="vatAppliesSection" class="p-3 border rounded-3 mb-3 bg-white d-none">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label text-xs fw-semibold">VAT Calculation Mode</label>
                                <div class="btn-group w-100" role="group">
                                    <input type="radio" class="btn-check" name="is_inclusive" id="modeInclusive" value="1" checked onchange="recalculateVatPreview()">
                                    <label class="btn btn-outline-primary btn-sm text-xs" for="modeInclusive">VAT Inclusive</label>
                                    <input type="radio" class="btn-check" name="is_inclusive" id="modeExclusive" value="0" onchange="recalculateVatPreview()">
                                    <label class="btn btn-outline-primary btn-sm text-xs" for="modeExclusive">VAT Exclusive</label>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label text-xs fw-semibold">VAT Rate (%)</label>
                                <input type="number" step="0.01" name="vat_rate" id="mVatRate" class="form-control form-control-sm" value="15.00" oninput="recalculateVatPreview()">
                            </div>
                            <div class="col-md-5">
                                <label class="form-label text-xs fw-semibold">Purchase Type (Line 65-120)</label>
                                <select name="purchase_type" id="mPurchaseType" class="form-select form-select-sm">
                                    @foreach($purchaseTypes as $pCode => $pLabel)
                                    <option value="{{ $pCode }}">{{ $pLabel }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <!-- Official VAT Report Metadata -->
                            <div class="col-md-6">
                                <label class="form-label text-xs fw-semibold">Supplier / Merchant Name</label>
                                <div class="input-group input-group-sm">
                                    <span class="input-group-text bg-light text-muted"><i class="fa-solid fa-store"></i></span>
                                    <input type="text" name="custom_seller_name" id="mSellerName" class="form-control form-control-sm" placeholder="e.g. TotalEnergies Marketing Ethiopia" oninput="syncField('mSellerName', 'mNoVatSellerName')">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label text-xs fw-semibold">Vendor TIN (10 Digits)</label>
                                <div class="input-group input-group-sm">
                                    <span class="input-group-text bg-light text-muted font-monospace"><i class="fa-solid fa-id-card"></i></span>
                                    <input type="text" name="custom_seller_tin" id="mTin" class="form-control form-control-sm" placeholder="e.g. 0012345678" oninput="syncField('mTin', 'mNoVatTin')">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label text-xs fw-semibold">Fiscal Receipt # (FS No)</label>
                                <input type="text" name="custom_fs_no" id="mFsNo" class="form-control form-control-sm font-monospace fw-bold" placeholder="e.g. 00045892" oninput="syncField('mFsNo', 'mNoVatFsNo')">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label text-xs fw-semibold">MRC Number</label>
                                <input type="text" name="custom_mrc_no" id="mMrcNo" class="form-control form-control-sm font-monospace" placeholder="e.g. CAS1029" oninput="syncField('mMrcNo', 'mNoVatMrcNo')">
                            </div>
                        </div>
                    </div>

                    <!-- No VAT Justification & TAS Purchase Section -->
                    <div id="noVatSection" class="p-3 border rounded-3 mb-3 bg-light d-none">
                        <div class="row g-2 mb-3">
                            <div class="col-md-6">
                                <label class="form-label text-xs fw-semibold text-dark">Reason for No VAT / TAS Exemption <span class="text-danger">*</span></label>
                                <select name="no_vat_reason_code" id="mReasonCode" class="form-select form-select-sm">
                                    <option value="">— Select Justification —</option>
                                    @foreach($reasons as $rCode => $rLabel)
                                    <option value="{{ $rCode }}">{{ $rLabel }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label text-xs fw-semibold text-dark">Audit Memo / Notes</label>
                                <input type="text" name="no_vat_reason_note" id="mReasonNote" class="form-control form-control-sm" placeholder="Additional explanation...">
                            </div>
                        </div>

                        <!-- TAS Fiscal Receipt (pro.doc 9-Column e-Tax Format) -->
                        <div class="p-2.5 rounded-3 border bg-white shadow-xs">
                            <div class="d-flex justify-content-between align-items-center mb-2 pb-1 border-bottom">
                                <span class="fw-bold text-xs text-dark d-flex align-items-center gap-1.5">
                                    <i class="fa-solid fa-receipt text-primary"></i> TAS Purchase Fiscal Receipt (pro.doc 9-Column Format)
                                </span>
                                <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 text-xxs">0% VAT TAS e-Tax</span>
                            </div>
                            <div class="row g-2">
                                <div class="col-md-6">
                                    <label class="form-label text-xxs fw-semibold text-muted mb-1">Supplier / Merchant Name</label>
                                    <div class="input-group input-group-sm">
                                        <span class="input-group-text bg-light text-muted py-0"><i class="fa-solid fa-store text-xxs"></i></span>
                                        <input type="text" id="mNoVatSellerName" class="form-control form-control-sm text-xs" placeholder="e.g. TotalEnergies Marketing Ethiopia" oninput="syncField('mNoVatSellerName', 'mSellerName')">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label text-xxs fw-semibold text-muted mb-1">Seller TIN (Vendor TIN)</label>
                                    <div class="input-group input-group-sm">
                                        <span class="input-group-text bg-light text-muted font-monospace py-0"><i class="fa-solid fa-id-card text-xxs"></i></span>
                                        <input type="text" id="mNoVatTin" class="form-control form-control-sm text-xs font-monospace" placeholder="e.g. 5201 or 0012345678" oninput="syncField('mNoVatTin', 'mTin')">
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label text-xxs fw-semibold text-muted mb-1">Receipt No / Fiscal # (FS No)</label>
                                    <input type="text" id="mNoVatFsNo" class="form-control form-control-sm text-xs font-monospace fw-bold" placeholder="e.g. FS00003898 or DHN638Y2E0" oninput="syncField('mNoVatFsNo', 'mFsNo')">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label text-xxs fw-semibold text-muted mb-1">MRC Number (Machine No)</label>
                                    <input type="text" id="mNoVatMrcNo" class="form-control form-control-sm text-xs font-monospace" placeholder="e.g. DDB0000032" oninput="syncField('mNoVatMrcNo', 'mMrcNo')">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label text-xxs fw-semibold text-muted mb-1">Receipt Date</label>
                                    <input type="date" id="mNoVatReceiptDate" class="form-control form-control-sm text-xs" onchange="syncField('mNoVatReceiptDate', 'mReceiptDate')">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label text-xxs fw-semibold text-muted mb-1">PurcType (Commodity Type)</label>
                                    <select id="mNoVatPurcType" class="form-select form-select-sm text-xs" onchange="document.getElementById('mPurcType').value = this.value">
                                        <option value="PUR_GD" selected>PUR_GD (Goods / Fuel / Materials)</option>
                                        <option value="PUR_SER">PUR_SER (Services / Labor / Transport)</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label text-xxs fw-semibold text-muted mb-1">Purchaser TIN</label>
                                    <input type="text" class="form-control form-control-sm text-xs bg-light" value="38480010" readonly title="Default Purchaser TIN">
                                </div>
                            </div>
                            <div class="mt-2 text-xxs text-muted d-flex align-items-center gap-1">
                                <i class="fa-solid fa-circle-info text-primary"></i> 
                                <span>This receipt has 0% VAT. Saving it registers the slip as a <strong>TAS Purchase</strong> and integrates into the 9-column <code>Purchase_TAS.xlsx</code> matching <strong>pro.doc</strong>.</span>
                            </div>
                        </div>
                    </div>

                    <!-- Withholding Tax (3%) & Receipt Upload Section -->
                    <div class="card border border-warning border-opacity-50 bg-warning bg-opacity-10 rounded-3 mb-3 shadow-none">
                        <div class="card-body p-3">
                            <div class="d-flex justify-content-between align-items-center">
                                <div class="form-check form-switch mb-0">
                                    <input class="form-check-input" type="checkbox" name="has_withholding" id="mHasWithholding" value="1" onchange="toggleWithholdingFields()">
                                    <label class="form-check-label fw-bold text-dark text-xs cursor-pointer" for="mHasWithholding">
                                        <i class="fa-solid fa-stamp text-warning me-1"></i> Apply Withholding Tax (3%)
                                    </label>
                                </div>
                                <span class="badge bg-white text-dark border text-xxs shadow-xs" id="whtRateBadge">3.00% Standard Rate</span>
                            </div>

                            <div id="withholdingFieldsSection" class="d-none mt-2.5 pt-2.5 border-top border-warning border-opacity-25">
                                <div class="row g-2 mb-2">
                                    <div class="col-md-4">
                                        <label class="form-label text-xxs fw-semibold text-muted mb-1">Withholding Rate (%)</label>
                                        <div class="input-group input-group-sm">
                                            <input type="number" step="0.01" name="withholding_rate" id="mWithholdingRate" class="form-control text-xs bg-white" value="3.00" oninput="recalculateVatPreview()">
                                            <span class="input-group-text bg-light text-xxs">%</span>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label text-xxs fw-semibold text-muted mb-1">Withholding Tax (ETB)</label>
                                        <div class="input-group input-group-sm">
                                            <input type="number" step="0.01" name="withholding_amount" id="mWithholdingAmount" class="form-control text-xs bg-white fw-bold text-danger" placeholder="0.00" oninput="recalculateVatPreview()">
                                            <span class="input-group-text bg-light text-xxs">ETB</span>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label text-xxs fw-semibold text-muted mb-1">Receipt / Voucher #</label>
                                        <input type="text" name="withholding_receipt_number" id="mWithholdingReceiptNo" class="form-control form-control-sm text-xs bg-white font-monospace" placeholder="e.g. WHT-00982">
                                    </div>
                                </div>

                                <!-- Withholding Receipt File Upload -->
                                <div class="bg-white p-2.5 rounded-3 border border-warning border-opacity-25">
                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                        <label class="form-label text-xxs fw-bold text-dark mb-0 d-flex align-items-center gap-1">
                                            <i class="fa-solid fa-cloud-arrow-up text-primary"></i> Upload Withholding Tax Receipt / Slip
                                        </label>
                                        <span id="currentWithholdingReceiptWrapper" class="d-none">
                                            <a id="currentWithholdingReceiptLink" href="#" target="_blank" class="badge bg-success bg-opacity-10 text-success border border-success text-xxs text-decoration-none">
                                                <i class="fa-solid fa-paperclip me-1"></i> View Current Receipt
                                            </a>
                                        </span>
                                    </div>
                                    <input type="file" name="withholding_receipt_file" id="mWithholdingFile" accept="image/*,.pdf" class="form-control form-control-sm text-xs">
                                    <div class="form-text text-xxs text-muted mt-1">
                                        Attach official ERCA / MOR Withholding Tax Receipt voucher (JPG, PNG, PDF max 25MB).
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Live Calculation Preview Card (Base, VAT, Withholding & Net Payable) -->
                    <div class="p-2.5 bg-light rounded-3 d-flex justify-content-between align-items-center text-xs border flex-wrap gap-2 mb-2">
                        <div>Base Subtotal: <strong id="prevBase" class="text-dark">0.00</strong> ETB</div>
                        <div>+ VAT (15%): <strong id="prevVat" class="text-success fs-6">0.00</strong> ETB</div>
                        <div id="prevWhtContainer" class="d-none text-danger">- 3% WHT: <strong id="prevWht" class="fs-6">0.00</strong> ETB</div>
                        <div>Grand Total: <strong id="prevTotal" class="text-primary">0.00</strong> ETB</div>
                        <div class="border-start ps-2">Payable Net: <strong id="prevNet" class="text-dark fw-bold fs-6">0.00</strong> ETB</div>
                    </div>

                </div>
                <div class="modal-footer bg-light py-2">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-sm btn-primary px-3">
                        <i class="fa-solid fa-check me-1"></i> Save Decision
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ── MODAL: QUICK ADD RECEIPT ─────────────────────────────────────────── -->
<div class="modal fade" id="quickAddReceiptModal" tabindex="-1" aria-labelledby="quickAddReceiptModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-md">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-light py-2.5">
                <h6 class="modal-title fw-bold text-dark" id="quickAddReceiptModalLabel">
                    <i class="fa-solid fa-cloud-arrow-up text-primary me-1.5"></i> Add Receipt: <span id="qarRefNo" class="text-primary"></span>
                </h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="quickAddReceiptForm" method="POST" action="{{ route('finance.expense-hub.decision') }}" enctype="multipart/form-data">
                @csrf
                <input type="hidden" name="source_type" id="qarSourceType">
                <input type="hidden" name="source_id" id="qarSourceId">
                <input type="hidden" name="amount" id="qarAmount">
                <input type="hidden" name="receipt_path" id="qarExistingPath">

                <div class="modal-body p-3">
                    <!-- Expense Summary Badge -->
                    <div class="p-2.5 bg-light rounded-3 border mb-3 text-xs d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <div>
                            <span class="text-muted text-xxs d-block">Payee / Supplier:</span>
                            <strong id="qarPayee" class="text-dark"></strong>
                        </div>
                        <div class="text-end">
                            <span class="text-muted text-xxs d-block">Disbursed Amount:</span>
                            <strong id="qarAmountDisplay" class="text-dark fs-6"></strong> <span class="text-xxs">ETB</span>
                        </div>
                    </div>

                    <!-- Current Attached Receipt Preview if any -->
                    <div id="qarCurrentReceiptAlert" class="d-none alert alert-info py-2 px-3 text-xs mb-3 d-flex justify-content-between align-items-center">
                        <span><i class="fa-solid fa-paperclip me-1"></i> Receipt is already attached</span>
                        <a id="qarCurrentReceiptLink" href="#" target="_blank" class="btn btn-xs btn-info text-white">View Current Receipt</a>
                    </div>

                    <!-- Receipt File Upload Box -->
                    <div class="mb-3">
                        <label class="form-label text-xs fw-bold">Select Receipt File (JPG, PNG, PDF up to 25MB) <span class="text-danger">*</span></label>
                        <input type="file" name="receipt_file" id="qarFileInput" class="form-control form-control-sm text-xs bg-white" accept="image/*,.pdf" required>
                        <div class="d-flex justify-content-between align-items-center mt-1">
                            <span class="text-xxs text-muted">Upload cash slip, invoice, or commercial voucher</span>
                            <button type="button" class="btn btn-xs btn-outline-primary py-0.5 px-2" id="btnQarRunOcr" onclick="runQarOcr()">
                                <i class="fa-solid fa-wand-magic-sparkles me-1"></i> Scan with AI OCR
                            </button>
                        </div>
                    </div>

                    <!-- OCR Progress / Result inside Quick Add -->
                    <div id="qarOcrSpinner" class="d-none text-center py-2 bg-light rounded mb-2">
                        <div class="spinner-border spinner-border-sm text-primary" role="status"></div>
                        <span class="ms-2 text-xs fw-semibold text-primary">Scanning receipt with AI OCR Vision...</span>
                    </div>
                    <div id="qarOcrResult" class="d-none mb-2 p-2 rounded text-xxs"></div>

                    <!-- Classification Option -->
                    <div class="mb-3">
                        <label class="form-label text-xs fw-bold">Tax Treatment</label>
                        <div class="d-flex gap-2">
                            <input type="radio" class="btn-check" name="vat_status" id="qarOptVat" value="vat_applies" checked>
                            <label class="btn btn-outline-success flex-fill py-1.5 text-xs fw-semibold" for="qarOptVat">
                                <i class="fa-solid fa-percent me-1"></i> VAT Applies (15%)
                            </label>

                            <input type="radio" class="btn-check" name="vat_status" id="qarOptNoVat" value="no_vat">
                            <label class="btn btn-outline-secondary flex-fill py-1.5 text-xs fw-semibold" for="qarOptNoVat">
                                <i class="fa-solid fa-receipt me-1"></i> Non-VAT / TAS (0%)
                            </label>
                        </div>
                    </div>

                    <!-- Metadata Fields -->
                    <div class="row g-2 mb-2">
                        <div class="col-6">
                            <label class="form-label text-xxs fw-semibold text-muted mb-0.5">Fiscal Receipt # (FS No)</label>
                            <input type="text" name="custom_fs_no" id="qarFsNo" class="form-control form-control-sm font-monospace text-xs" placeholder="e.g. FS00045892">
                        </div>
                        <div class="col-6">
                            <label class="form-label text-xxs fw-semibold text-muted mb-0.5">Supplier TIN</label>
                            <input type="text" name="custom_seller_tin" id="qarTin" class="form-control form-control-sm font-monospace text-xs" placeholder="e.g. 0012345678">
                        </div>
                        <div class="col-6">
                            <label class="form-label text-xxs fw-semibold text-muted mb-0.5">Supplier / Merchant Name</label>
                            <input type="text" name="custom_seller_name" id="qarSellerName" class="form-control form-control-sm text-xs" placeholder="e.g. TotalEnergies">
                        </div>
                        <div class="col-6">
                            <label class="form-label text-xxs fw-semibold text-muted mb-0.5">Receipt Date</label>
                            <input type="date" name="custom_receipt_date" id="qarReceiptDate" class="form-control form-control-sm text-xs">
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light py-2">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-sm btn-primary px-3" id="qarSubmitBtn">
                        <i class="fa-solid fa-cloud-arrow-up me-1"></i> Save &amp; Attach Receipt
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ── MODAL: VERIFIED WITHOUT RECEIPT ──────────────────────────────────── -->
<div class="modal fade" id="verifiedWithoutReceiptModal" tabindex="-1" aria-labelledby="vworModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-info bg-opacity-10 py-2.5 border-bottom border-info border-opacity-25">
                <h6 class="modal-title fw-bold text-dark" id="vworModalLabel">
                    <i class="fa-solid fa-check-double text-info me-1.5"></i> Verify Without Receipt
                </h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="vworForm" method="POST" action="{{ route('finance.expense-hub.decision') }}">
                @csrf
                <input type="hidden" name="source_type" id="vworSourceType">
                <input type="hidden" name="source_id" id="vworSourceId">
                <input type="hidden" name="amount" id="vworAmount">
                <input type="hidden" name="vat_status" value="no_vat">
                <input type="hidden" name="is_verified_without_receipt" value="1">

                <div class="modal-body p-3">
                    <div class="p-2.5 bg-light rounded-3 border mb-3 text-xs">
                        <div class="d-flex justify-content-between mb-1">
                            <span class="text-muted text-xxs">Ref:</span>
                            <strong id="vworRefNo" class="text-dark"></strong>
                        </div>
                        <div class="d-flex justify-content-between mb-1">
                            <span class="text-muted text-xxs">Payee:</span>
                            <span id="vworPayee" class="text-dark fw-semibold text-truncate" style="max-width: 170px;"></span>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span class="text-muted text-xxs">Amount:</span>
                            <strong id="vworAmountDisplay" class="text-dark"></strong> ETB
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label text-xs fw-bold">Verification Justification <span class="text-danger">*</span></label>
                        <select name="no_vat_reason_code" id="vworReasonCode" class="form-select form-select-sm text-xs" required>
                            <option value="no_fiscal_receipt" selected>No official Fiscal Receipt (FS/Cash Register)</option>
                            <option value="informal_vendor">Informal / Small-holder Vendor without receipt</option>
                            <option value="salary_payroll">Employee Salary / Labor Allowance</option>
                            <option value="exempt_item">Legally VAT-Exempt goods/services</option>
                            <option value="other">Other audited operational expense</option>
                        </select>
                    </div>

                    <div class="mb-2">
                        <label class="form-label text-xs fw-bold">Audit Memo / Note</label>
                        <input type="text" name="no_vat_reason_note" id="vworReasonNote" class="form-control form-control-sm text-xs"
                               value="Audited and verified without receipt (Approved operational expense)">
                    </div>

                    <div class="text-xxs text-muted mt-2 d-flex align-items-center gap-1.5">
                        <i class="fa-solid fa-shield-halved text-info"></i>
                        <span>This will record this payment as verified without receipt and clear audit warnings.</span>
                    </div>
                </div>
                <div class="modal-footer bg-light py-2">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-sm btn-info text-white px-3 fw-semibold" id="vworSubmitBtn">
                        <i class="fa-solid fa-check-double me-1"></i> Confirm &amp; Verify
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ── MODAL: BULK VAT DECISION ───────────────────────────────────────────── -->
<div class="modal fade" id="bulkModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-light py-2.5">
                <h6 class="modal-title fw-bold text-dark">
                    <i class="fa-solid fa-layer-group text-primary me-1.5"></i> Bulk VAT Classification (<span id="bulkCountDisplay">0</span> items)
                </h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="bulkDecisionForm" method="POST" action="{{ route('finance.expense-hub.bulk-decision') }}">
                @csrf
                <div id="bulkHiddenInputs"></div>
                <div class="modal-body p-3">
                    <div class="mb-3">
                        <label class="form-label text-xs fw-bold">Target VAT Status</label>
                        <select name="vat_status" id="bulkStatusSelect" class="form-select form-select-sm" onchange="toggleBulkFields()">
                            <option value="vat_applies">VAT Applies (Inputs)</option>
                            <option value="no_vat">No VAT (Exempt / Salary)</option>
                            <option value="verified_no_receipt">Verified Without Receipt</option>
                            <option value="pending_decision">Pending Decision</option>
                        </select>
                    </div>
                            <option value="pending_decision">Pending Decision</option>
                        </select>
                    </div>

                    <div id="bulkNoVatReasonGroup" class="mb-3 d-none">
                        <label class="form-label text-xs fw-bold">No-VAT Justification <span class="text-danger">*</span></label>
                        <select name="no_vat_reason_code" class="form-select form-select-sm">
                            <option value="">— Select Justification —</option>
                            @foreach($reasons as $rCode => $rLabel)
                            <option value="{{ $rCode }}">{{ $rLabel }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div id="bulkVatRateGroup" class="mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <label class="form-label text-xs fw-bold mb-0">VAT Rate &amp; Mode</label>
                        </div>
                        <div class="row g-2">
                            <div class="col-6">
                                <select name="is_inclusive" class="form-select form-select-sm">
                                    <option value="1">VAT Inclusive (Subtotal/1.15)</option>
                                    <option value="0">VAT Exclusive (Base + 15%)</option>
                                </select>
                            </div>
                            <div class="col-6">
                                <input type="number" step="0.01" name="vat_rate" class="form-control form-control-sm" value="15.00">
                            </div>
                        </div>
                    </div>

                    <!-- Withholding Option in Bulk -->
                    <div class="mb-3 border-top pt-2">
                        <div class="form-check form-switch mb-1">
                            <input class="form-check-input" type="checkbox" name="has_withholding" id="bulkHasWithholding" value="1" onchange="toggleBulkWithholding()">
                            <label class="form-check-label text-xs fw-bold cursor-pointer" for="bulkHasWithholding">
                                <i class="fa-solid fa-stamp text-warning me-1"></i> Apply Withholding Tax (3%)
                            </label>
                        </div>
                        <div id="bulkWithholdingGroup" class="d-none mt-1">
                            <label class="form-label text-xxs text-muted mb-0.5">Withholding Rate (%)</label>
                            <input type="number" step="0.01" name="withholding_rate" class="form-control form-control-sm" value="3.00">
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light py-2">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-sm btn-primary">Apply to All Selected</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ── MODAL: MONTH LOCK GOVERNANCE ──────────────────────────────────────── -->
<div class="modal fade" id="monthLockModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-dark text-white py-2.5">
                <h6 class="modal-title fw-bold text-white">
                    <i class="fa-solid fa-lock me-1.5"></i> Lock or Unlock Month
                </h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-3">
                <p class="text-xs text-muted mb-3">
                    Locking a month freezes all expense rows and prevents further VAT status modifications unless explicitly unlocked with an audit memo.
                </p>
                <form method="POST" action="{{ route('finance.expense-hub.lock-month') }}" class="mb-3">
                    @csrf
                    <div class="row g-2">
                        <div class="col-4">
                            <label class="form-label text-xxs">Year</label>
                            <input type="number" name="year" class="form-control form-control-sm" value="{{ date('Y') }}" required>
                        </div>
                        <div class="col-4">
                            <label class="form-label text-xxs">Month (1-12)</label>
                            <input type="number" name="month" class="form-control form-control-sm" value="{{ date('n') }}" required>
                        </div>
                        <div class="col-4 d-flex align-items-end">
                            <button type="submit" class="btn btn-sm btn-dark w-100">Lock Month</button>
                        </div>
                    </div>
                </form>

                <hr>

                <h6 class="text-xs fw-bold text-danger mb-2">Unlock a Closed Month (Audit Logged)</h6>
                <form method="POST" action="{{ route('finance.expense-hub.unlock-month') }}">
                    @csrf
                    <div class="row g-2">
                        <div class="col-4">
                            <input type="number" name="year" class="form-control form-control-sm" value="{{ date('Y') }}" required>
                        </div>
                        <div class="col-4">
                            <input type="number" name="month" class="form-control form-control-sm" value="{{ date('n') }}" required>
                        </div>
                        <div class="col-12">
                            <input type="text" name="unlock_reason" class="form-control form-control-sm" placeholder="Reason for unlocking month..." required>
                        </div>
                        <div class="col-12">
                            <button type="submit" class="btn btn-sm btn-outline-danger w-100">Unlock Closed Month</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<style>
.text-xxs { font-size: 0.72rem; }
.btn-xs { font-size: 0.75rem; padding: 0.2rem 0.5rem; }
.shadow-xs { box-shadow: 0 1px 2px rgba(0,0,0,0.05); }
</style>

<script>
let currentEditingItem = null;
let currentDetailItem = null;
let lastOcrResult = null;

// Registry populated safely via JSON encoding
window.expenseHubRegistry = window.expenseHubRegistry || {};
@if(!empty($paginatedExpenses))
    @foreach($paginatedExpenses as $pItem)
window.expenseHubRegistry["{{ $pItem->source_type }}_{{ $pItem->source_id }}"] = @json($pItem);
    @endforeach
@endif

function showModalSafe(modalId) {
    const el = (typeof modalId === 'string') ? document.getElementById(modalId) : modalId;
    if (!el) {
        console.error('showModalSafe: element not found', modalId);
        return;
    }

    // 1. Try Bootstrap 5 Modal
    try {
        if (window.bootstrap && window.bootstrap.Modal) {
            const instance = window.bootstrap.Modal.getOrCreateInstance(el);
            instance.show();
            return;
        }
    } catch (e) {
        console.warn('Bootstrap 5 modal show error:', e);
    }

    // 2. Try jQuery Modal
    try {
        if (window.$ && typeof window.$(el).modal === 'function') {
            window.$(el).modal('show');
            return;
        }
    } catch (e2) {
        console.warn('jQuery modal show error:', e2);
    }

    // 3. Direct DOM fallback
    el.classList.add('show');
    el.style.display = 'block';
    el.removeAttribute('aria-hidden');
    el.setAttribute('aria-modal', 'true');
    document.body.classList.add('modal-open');
    let backdrop = document.querySelector('.modal-backdrop');
    if (!backdrop) {
        backdrop = document.createElement('div');
        backdrop.className = 'modal-backdrop fade show';
        backdrop.onclick = function() { hideModalSafe(el); };
        document.body.appendChild(backdrop);
    }
}

function hideModalSafe(modalId) {
    const el = (typeof modalId === 'string') ? document.getElementById(modalId) : modalId;
    if (!el) return;

    try {
        if (window.bootstrap && window.bootstrap.Modal) {
            const instance = window.bootstrap.Modal.getInstance(el);
            if (instance) {
                instance.hide();
                return;
            }
        }
    } catch (e) {}

    try {
        if (window.$ && typeof window.$(el).modal === 'function') {
            window.$(el).modal('hide');
            return;
        }
    } catch (e2) {}

    el.classList.remove('show');
    el.style.display = 'none';
    el.setAttribute('aria-hidden', 'true');
    el.removeAttribute('aria-modal');
    document.body.classList.remove('modal-open');
    const backdrop = document.querySelector('.modal-backdrop');
    if (backdrop) backdrop.remove();
}

function extractItemFromRow(tr) {
    if (!tr) return null;
    if (tr._extractedItem) return tr._extractedItem;

    const ds = tr.dataset || {};
    const item = {
        source_type: ds.sourceType || '',
        source_id: ds.sourceId || '',
        reference_no: ds.refNo || '—',
        payee_supplier: ds.payee || '',
        tin: ds.tin || '',
        amount: parseFloat(ds.amount || 0),
        vat_status: ds.vatStatus || 'pending_decision',
        is_verified_without_receipt: ds.isVerifiedWithoutReceipt === '1',
        vat_rate: parseFloat(ds.vatRate || 15.00),
        is_inclusive: ds.isInclusive !== '0',
        decision_base_amount: parseFloat(ds.baseAmount || 0),
        decision_vat_amount: parseFloat(ds.vatAmount || 0),
        decision_total_amount: parseFloat(ds.totalAmount || ds.amount || 0),
        receipt_url: ds.receiptUrl || '',
        disbursement_date: ds.disbursementDate || '',
        source_module_label: ds.moduleLabel || 'Expense',
        purpose_detail: ds.purpose || '',
        description: ds.purpose || '',
        fs_no: ds.fsNo || '',
        mrc_no: ds.mrcNo || '',
        has_withholding: ds.hasWithholding === '1',
        withholding_rate: parseFloat(ds.withholdingRate || 3.00),
        withholding_amount: parseFloat(ds.withholdingAmount || 0),
        withholding_receipt: ds.withholdingReceipt || '',
        withholding_receipt_number: ds.withholdingReceiptNumber || '',
        net_payable_amount: parseFloat(ds.netPayableAmount || 0),
        reason_code: ds.reasonCode || '',
        reason_note: ds.reasonNote || '',
        purchase_type: parseInt(ds.purchaseType || 3, 10),
        vat_category: ds.vatCategory || 'G',
        created_by_name: ds.createdBy || '',
        created_at_formatted: ds.createdAt || '',
        approved_by_name: ds.approvedBy || '',
        approved_at_formatted: ds.approvedAt || '',
        paid_by_name: ds.paidBy || '',
        paid_at_formatted: ds.paidAt || '',
        view_url: ds.viewUrl || '',
        line_items: []
    };

    tr._extractedItem = item;
    return item;
}

function resolveItem(arg1, arg2, buttonEl) {
    // 1. Direct object
    if (arg1 && typeof arg1 === 'object') {
        return arg1;
    }

    // Determine sourceType and sourceId
    let sourceType = null;
    let sourceId = null;

    if (arg1 && arg2 !== undefined && arg2 !== null) {
        sourceType = String(arg1);
        sourceId = String(arg2);
    } else if (buttonEl) {
        sourceType = buttonEl.getAttribute('data-source-type');
        sourceId = buttonEl.getAttribute('data-source-id');
    }

    // 2. Look up in window.expenseHubRegistry
    if (window.expenseHubRegistry && sourceType && sourceId) {
        const key = sourceType + '_' + sourceId;
        if (window.expenseHubRegistry[key]) {
            return window.expenseHubRegistry[key];
        }
    }

    // 3. Look up from row element dataset
    let row = null;
    if (buttonEl && buttonEl.closest) {
        row = buttonEl.closest('tr');
    }
    if (!row && sourceType && sourceId) {
        row = document.getElementById('hub-row-' + sourceType + '-' + sourceId);
    }
    if (row) {
        return extractItemFromRow(row);
    }

    // 4. Fallback if arg1 is stringified JSON
    if (typeof arg1 === 'string' && (arg1.trim().startsWith('{') || arg1.trim().startsWith('['))) {
        try {
            return JSON.parse(arg1);
        } catch (e) {}
    }

    console.warn('Could not resolve expense item:', { arg1, arg2, buttonEl });
    return null;
}

function escapeHtml(text) {
    if (text === null || text === undefined) return '';
    return text.toString()
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;");
}

function openDetailFromDecision() {
    if (!currentEditingItem) return;
    hideModalSafe('decisionModal');
    setTimeout(() => {
        openExpenseDetailModal(currentEditingItem);
    }, 350);
}

function openExpenseDetailModal(arg1, arg2, buttonEl) {
    const item = resolveItem(arg1, arg2, buttonEl);
    if (!item) {
        console.error('Cannot open Expense Detail: Item not resolved', { arg1, arg2, buttonEl });
        return;
    }
    currentDetailItem = item;

    // Header & Basic Info
    document.getElementById('dModalModuleBadge').textContent = item.source_module_label || 'Expense';
    document.getElementById('dRefNo').textContent = item.reference_no || '—';
    document.getElementById('dPayee').textContent = item.payee_supplier || '—';
    document.getElementById('dTin').textContent = item.tin || '—';
    document.getElementById('dProject').textContent = item.project_name || 'General';
    document.getElementById('dAmount').textContent = parseFloat(item.amount || 0).toLocaleString('en-US', {minimumFractionDigits: 2});

    // VAT Badge
    const vatBadge = document.getElementById('dVatBadge');
    let badgeClass = 'bg-warning text-dark';
    let badgeText = 'Pending Decision';
    if (item.vat_status === 'vat_applies') {
        badgeClass = 'bg-success text-white';
        badgeText = 'VAT Applies (Input VAT)';
    } else if (item.vat_status === 'no_vat') {
        badgeClass = 'bg-secondary text-white';
        badgeText = 'No VAT (Exempt/Salary)';
    }
    vatBadge.className = 'badge rounded-pill px-2.5 py-1 text-xs ' + badgeClass;
    vatBadge.textContent = badgeText;

    // Section 1: Purpose & Line Items
    document.getElementById('dPurposeDetail').textContent = item.purpose_detail || item.description || 'No description provided';
    
    const lineItemsContainer = document.getElementById('dLineItemsContainer');
    const lineItemsTbody = document.getElementById('dLineItemsTableBody');
    lineItemsTbody.innerHTML = '';
    
    if (item.line_items && Array.isArray(item.line_items) && item.line_items.length > 0) {
        lineItemsContainer.classList.remove('d-none');
        item.line_items.forEach(li => {
            const tr = document.createElement('tr');
            const qty = parseFloat(li.quantity || 1).toLocaleString('en-US');
            const unit = li.unit || 'pcs';
            const rate = parseFloat(li.rate || 0).toLocaleString('en-US', {minimumFractionDigits: 2});
            const total = parseFloat(li.total || (li.quantity * li.rate) || 0).toLocaleString('en-US', {minimumFractionDigits: 2});
            tr.innerHTML = `
                <td class="fw-semibold text-dark">${escapeHtml(li.name || 'Item')}</td>
                <td class="text-center font-monospace">${qty}</td>
                <td class="text-center text-muted">${escapeHtml(unit)}</td>
                <td class="text-end font-monospace">${rate}</td>
                <td class="text-end fw-bold text-dark font-monospace">${total}</td>
            `;
            lineItemsTbody.appendChild(tr);
        });
    } else {
        lineItemsContainer.classList.add('d-none');
    }

    // Section 2: Operational Audit Trail
    document.getElementById('dCreatedBy').textContent = item.created_by_name || 'System / Staff';
    document.getElementById('dCreatedAt').innerHTML = '<i class="fa-regular fa-clock me-1"></i> ' + (item.created_at_formatted || '—');

    document.getElementById('dApprovedBy').textContent = item.approved_by_name || '—';
    document.getElementById('dApprovedAt').innerHTML = '<i class="fa-regular fa-clock me-1"></i> ' + (item.approved_at_formatted || '—');

    document.getElementById('dPaidBy').textContent = item.paid_by_name || 'Finance Staff';
    const disDate = item.disbursement_date ? item.disbursement_date : (item.paid_at_formatted || '—');
    document.getElementById('dPaidAt').innerHTML = '<i class="fa-regular fa-calendar-check me-1"></i> ' + disDate;
    document.getElementById('dPaymentMethod').textContent = 'Method: ' + (item.payment_method ? item.payment_method.toUpperCase() : 'CASH / BANK');

    // Section 3: VAT Decision
    document.getElementById('dVatStatusLabel').textContent = badgeText;
    document.getElementById('dBaseAmount').textContent = parseFloat(item.decision_base_amount || 0).toLocaleString('en-US', {minimumFractionDigits: 2});
    document.getElementById('dVatAmount').textContent = parseFloat(item.decision_vat_amount || 0).toLocaleString('en-US', {minimumFractionDigits: 2});
    document.getElementById('dTotalAmount').textContent = parseFloat(item.decision_total_amount || item.amount || 0).toLocaleString('en-US', {minimumFractionDigits: 2});
    document.getElementById('dFsNo').textContent = item.fs_no || '—';
    document.getElementById('dMrcNo').textContent = item.mrc_no || '—';
    document.getElementById('dExemptionReason').textContent = item.reason_note || item.reason_code || '—';

    // Withholding Tax fields
    const whtAmount = parseFloat(item.withholding_amount || 0);
    const hasWht = Boolean(item.has_withholding) || whtAmount > 0;
    const dWhtAmount = document.getElementById('dWhtAmount');
    if (dWhtAmount) {
        dWhtAmount.textContent = hasWht ? (whtAmount.toLocaleString('en-US', {minimumFractionDigits: 2}) + ' ETB') : '—';
    }
    const dWhtReceiptNo = document.getElementById('dWhtReceiptNo');
    if (dWhtReceiptNo) {
        dWhtReceiptNo.textContent = item.withholding_receipt_number || '—';
    }
    const dNetPayable = document.getElementById('dNetPayable');
    if (dNetPayable) {
        const netPayable = parseFloat(item.net_payable_amount || (item.amount - whtAmount) || 0);
        dNetPayable.textContent = hasWht 
            ? (netPayable.toLocaleString('en-US', {minimumFractionDigits: 2}) + ' ETB') 
            : (parseFloat(item.amount || 0).toLocaleString('en-US', {minimumFractionDigits: 2}) + ' ETB');
    }

    // Footer actions
    const viewRecBtn = document.getElementById('dViewRecordBtn');
    if (item.view_url) {
        viewRecBtn.href = item.view_url;
        viewRecBtn.classList.remove('d-none');
    } else {
        viewRecBtn.classList.add('d-none');
    }

    const receiptBtn = document.getElementById('dReceiptBtn');
    if (item.receipt_url) {
        receiptBtn.href = (item.receipt_url.startsWith('http://') || item.receipt_url.startsWith('https://'))
            ? item.receipt_url
            : ('{{ asset("storage") }}/' + item.receipt_url.replace(/^\/+/, ''));
        receiptBtn.classList.remove('d-none');
    } else {
        receiptBtn.classList.add('d-none');
    }

    const whtReceiptBtn = document.getElementById('dWhtReceiptBtn');
    if (whtReceiptBtn) {
        if (item.withholding_receipt) {
            whtReceiptBtn.href = (item.withholding_receipt.startsWith('http://') || item.withholding_receipt.startsWith('https://'))
                ? item.withholding_receipt
                : ('{{ asset("storage") }}/' + item.withholding_receipt.replace(/^\/+/, ''));
            whtReceiptBtn.classList.remove('d-none');
        } else {
            whtReceiptBtn.classList.add('d-none');
        }
    }

    const btnOpenDecision = document.getElementById('dBtnOpenDecision');
    if (btnOpenDecision) {
        btnOpenDecision.onclick = function() {
            hideModalSafe('expenseDetailModal');
            setTimeout(() => openDecisionModal(item), 350);
        };
    }

    showModalSafe('expenseDetailModal');
}

function resetOcrState() {
    lastOcrResult = null;
    const fileInput = document.getElementById('mOcrFileInput');
    if (fileInput) fileInput.value = '';
    const whtFileInput = document.getElementById('mWithholdingFile');
    if (whtFileInput) whtFileInput.value = '';
    const whtReceiptPath = document.getElementById('mWithholdingReceiptPath');
    if (whtReceiptPath) whtReceiptPath.value = '';
    const spinner = document.getElementById('ocrSpinner');
    if (spinner) spinner.classList.add('d-none');
    const resultBox = document.getElementById('ocrResultBox');
    if (resultBox) {
        resultBox.classList.add('d-none');
        resultBox.innerHTML = '';
    }
    const badge = document.getElementById('ocrStatusBadge');
    if (badge) {
        badge.className = 'badge bg-white text-primary border text-xxs shadow-xs';
        badge.textContent = 'Scan & Verify';
    }
    const uploadBtn = document.getElementById('btnUploadOcrScan');
    if (uploadBtn) uploadBtn.disabled = false;
    const attachedBtn = document.getElementById('btnAttachedOcrScan');
    if (attachedBtn) attachedBtn.disabled = false;
    const mReceiptDate = document.getElementById('mReceiptDate');
    if (mReceiptDate) mReceiptDate.value = '';
    const mReceiptPath = document.getElementById('mReceiptPath');
    if (mReceiptPath) mReceiptPath.value = '';
    const mSellerName = document.getElementById('mSellerName');
    if (mSellerName) mSellerName.value = '';
    const noVatSeller = document.getElementById('mNoVatSellerName');
    if (noVatSeller) noVatSeller.value = '';
    const mItemDescription = document.getElementById('mItemDescription');
    if (mItemDescription) mItemDescription.value = '';
    const noVatTin = document.getElementById('mNoVatTin');
    if (noVatTin) noVatTin.value = '';
    const noVatFsNo = document.getElementById('mNoVatFsNo');
    if (noVatFsNo) noVatFsNo.value = '';
    const noVatMrcNo = document.getElementById('mNoVatMrcNo');
    if (noVatMrcNo) noVatMrcNo.value = '';
    const noVatDate = document.getElementById('mNoVatReceiptDate');
    if (noVatDate) noVatDate.value = '';
    const noVatPurcType = document.getElementById('mNoVatPurcType');
    if (noVatPurcType) noVatPurcType.value = 'PUR_GD';
    const mPurcType = document.getElementById('mPurcType');
    if (mPurcType) mPurcType.value = 'PUR_GD';
}

function openDecisionModal(arg1, arg2, buttonEl) {
    const item = resolveItem(arg1, arg2, buttonEl);
    if (!item) {
        console.error('Cannot open VAT Decision: Item not resolved', { arg1, arg2, buttonEl });
        return;
    }
    currentEditingItem = item;
    resetOcrState();

    document.getElementById('mSourceType').value = item.source_type;
    document.getElementById('mSourceId').value = item.source_id;
    document.getElementById('mAmount').value = item.amount;
    document.getElementById('mRefNo').textContent = item.reference_no;
    document.getElementById('mPayee').textContent = item.payee_supplier || '—';
    document.getElementById('mSourceModule').textContent = item.source_module_label;
    document.getElementById('mAmountDisplay').textContent = parseFloat(item.amount).toLocaleString('en-US', {minimumFractionDigits: 2});

    if (item.receipt_url) {
        document.getElementById('mReceiptPath').value = item.receipt_url;
    }
    if (item.disbursement_date) {
        document.getElementById('mReceiptDate').value = item.disbursement_date;
    }
    if (item.payee_supplier) {
        if (document.getElementById('mSellerName')) {
            document.getElementById('mSellerName').value = item.payee_supplier;
        }
        if (document.getElementById('mNoVatSellerName')) {
            document.getElementById('mNoVatSellerName').value = item.payee_supplier;
        }
    }
    if (item.purpose_detail || item.description) {
        document.getElementById('mItemDescription').value = item.purpose_detail || item.description;
    }

    // Populate Purpose & Operational Audit
    document.getElementById('mPurpose').textContent = item.purpose_detail || item.description || '—';
    document.getElementById('mCreatedBy').textContent = (item.created_by_name || '—') + (item.created_at_formatted ? (' (' + item.created_at_formatted + ')') : '');
    document.getElementById('mApprovedBy').textContent = (item.approved_by_name || '—') + (item.approved_at_formatted ? (' (' + item.approved_at_formatted + ')') : '');
    document.getElementById('mPaidBy').textContent = (item.paid_by_name || '—') + (item.disbursement_date ? (' on ' + item.disbursement_date) : '');

    // Populate radio status
    const mIsVworInput = document.getElementById('mIsVerifiedWithoutReceipt');
    if (item.is_verified_without_receipt) {
        const optVwor = document.getElementById('optVerifiedNoReceipt');
        if (optVwor) {
            optVwor.checked = true;
        } else {
            document.getElementById('optNoVat').checked = true;
        }
        if (mIsVworInput) mIsVworInput.value = '1';
    } else if (item.vat_status === 'vat_applies') {
        document.getElementById('optVatApplies').checked = true;
        if (mIsVworInput) mIsVworInput.value = '0';
    } else if (item.vat_status === 'no_vat') {
        document.getElementById('optNoVat').checked = true;
        if (mIsVworInput) mIsVworInput.value = '0';
    } else {
        document.getElementById('optPending').checked = true;
        if (mIsVworInput) mIsVworInput.value = '0';
    }

    // Prefill metadata
    document.getElementById('mTin').value = item.tin || '';
    document.getElementById('mFsNo').value = item.fs_no || '';
    document.getElementById('mMrcNo').value = item.mrc_no || '';
    const noVatTin = document.getElementById('mNoVatTin');
    if (noVatTin) noVatTin.value = item.tin || '';
    const noVatFsNo = document.getElementById('mNoVatFsNo');
    if (noVatFsNo) noVatFsNo.value = item.fs_no || '';
    const noVatMrcNo = document.getElementById('mNoVatMrcNo');
    if (noVatMrcNo) noVatMrcNo.value = item.mrc_no || '';
    const noVatDate = document.getElementById('mNoVatReceiptDate');
    if (noVatDate) noVatDate.value = item.disbursement_date || '';
    const purcTypeVal = (item.vat_category === 'S' || item.purc_type === 'PUR_SER') ? 'PUR_SER' : 'PUR_GD';
    const noVatPurcType = document.getElementById('mNoVatPurcType');
    if (noVatPurcType) noVatPurcType.value = purcTypeVal;
    const mPurcType = document.getElementById('mPurcType');
    if (mPurcType) mPurcType.value = purcTypeVal;
    document.getElementById('mVatRate').value = item.vat_rate || 15.00;
    document.getElementById('mPurchaseType').value = item.purchase_type || (item.is_capex ? 1 : 3);
    document.getElementById('mReasonCode').value = item.reason_code || '';
    document.getElementById('mReasonNote').value = item.reason_note || '';

    if (item.is_inclusive === false) {
        document.getElementById('modeExclusive').checked = true;
    } else {
        document.getElementById('modeInclusive').checked = true;
    }

    // Prefill Withholding Tax & Receipt
    const hasWht = Boolean(item.has_withholding) || parseFloat(item.withholding_amount || 0) > 0;
    const whtCheckbox = document.getElementById('mHasWithholding');
    if (whtCheckbox) {
        whtCheckbox.checked = hasWht;
    }
    const whtRate = document.getElementById('mWithholdingRate');
    if (whtRate) {
        whtRate.value = (item.withholding_rate !== null && item.withholding_rate !== undefined && item.withholding_rate !== '') 
            ? item.withholding_rate 
            : 3.00;
    }
    const whtAmountInput = document.getElementById('mWithholdingAmount');
    if (whtAmountInput) {
        whtAmountInput.value = item.withholding_amount ? parseFloat(item.withholding_amount).toFixed(2) : '';
    }
    const whtReceiptNo = document.getElementById('mWithholdingReceiptNo');
    if (whtReceiptNo) {
        whtReceiptNo.value = item.withholding_receipt_number || '';
    }
    const whtReceiptPath = document.getElementById('mWithholdingReceiptPath');
    if (whtReceiptPath) {
        whtReceiptPath.value = item.withholding_receipt || '';
    }

    const currentWhtWrap = document.getElementById('currentWithholdingReceiptWrapper');
    const currentWhtLink = document.getElementById('currentWithholdingReceiptLink');
    if (currentWhtWrap && currentWhtLink) {
        if (item.withholding_receipt) {
            currentWhtLink.href = (item.withholding_receipt.startsWith('http://') || item.withholding_receipt.startsWith('https://'))
                ? item.withholding_receipt
                : ('{{ asset("storage") }}/' + item.withholding_receipt.replace(/^\/+/, ''));
            currentWhtWrap.classList.remove('d-none');
        } else {
            currentWhtWrap.classList.add('d-none');
        }
    }

    toggleWithholdingFields();

    // Toggle attached scan button
    const attachedBtn = document.getElementById('btnAttachedOcrScan');
    if (attachedBtn) {
        if (item.receipt_url) {
            attachedBtn.classList.remove('d-none');
            attachedBtn.setAttribute('title', 'Scan attached: ' + item.receipt_url);
        } else {
            attachedBtn.classList.add('d-none');
        }
    }

    toggleVatFields();
    recalculateVatPreview();

    showModalSafe('decisionModal');
}

function openDecisionModalWithOcr(arg1, arg2, buttonEl) {
    const item = resolveItem(arg1, arg2, buttonEl);
    if (!item) {
        console.error('Cannot open VAT Decision with OCR: Item not resolved', { arg1, arg2, buttonEl });
        return;
    }
    openDecisionModal(item);
    if (item.receipt_url) {
        runOcrAttached();
    } else {
        const fileInput = document.getElementById('mOcrFileInput');
        if (fileInput) {
            fileInput.focus();
            fileInput.click();
        }
    }
}

// ── Quick Actions from Row Menu ──────────────────────────────────────────────
function openAddReceiptAction(arg1, arg2, buttonEl) {
    const item = resolveItem(arg1, arg2, buttonEl);
    if (!item) {
        console.error('Cannot open Add Receipt: Item not resolved', { arg1, arg2, buttonEl });
        return;
    }
    currentEditingItem = item;

    document.getElementById('qarSourceType').value = item.source_type;
    document.getElementById('qarSourceId').value = item.source_id;
    document.getElementById('qarAmount').value = item.amount;
    document.getElementById('qarExistingPath').value = item.receipt_url || '';

    document.getElementById('qarRefNo').textContent = item.reference_no;
    document.getElementById('qarPayee').textContent = item.payee_supplier || '—';
    document.getElementById('qarAmountDisplay').textContent = parseFloat(item.amount).toLocaleString('en-US', {minimumFractionDigits: 2});

    document.getElementById('qarSellerName').value = item.payee_supplier || '';
    document.getElementById('qarTin').value = item.tin || '';
    document.getElementById('qarFsNo').value = item.fs_no || '';
    document.getElementById('qarReceiptDate').value = item.disbursement_date || '';

    const fileInput = document.getElementById('qarFileInput');
    if (fileInput) fileInput.value = '';
    const spinner = document.getElementById('qarOcrSpinner');
    if (spinner) spinner.classList.add('d-none');
    const resultBox = document.getElementById('qarOcrResult');
    if (resultBox) {
        resultBox.classList.add('d-none');
        resultBox.innerHTML = '';
    }

    const alertBox = document.getElementById('qarCurrentReceiptAlert');
    const alertLink = document.getElementById('qarCurrentReceiptLink');
    if (item.receipt_url) {
        alertLink.href = (item.receipt_url.startsWith('http://') || item.receipt_url.startsWith('https://'))
            ? item.receipt_url
            : ('{{ asset("storage") }}/' + item.receipt_url.replace(/^\/+/, ''));
        alertBox.classList.remove('d-none');
        if (fileInput) fileInput.removeAttribute('required');
    } else {
        alertBox.classList.add('d-none');
        if (fileInput) fileInput.setAttribute('required', 'required');
    }

    if (item.vat_status === 'vat_applies') {
        document.getElementById('qarOptVat').checked = true;
    } else {
        document.getElementById('qarOptNoVat').checked = true;
    }

    showModalSafe('quickAddReceiptModal');
}

function openAddVatAction(arg1, arg2, buttonEl) {
    const item = resolveItem(arg1, arg2, buttonEl);
    if (!item) return;
    openDecisionModal(item);
    document.getElementById('optVatApplies').checked = true;
    toggleVatFields();
    setTimeout(() => {
        const fsInput = document.getElementById('mFsNo');
        if (fsInput) fsInput.focus();
    }, 400);
}

function openNonVatAction(arg1, arg2, buttonEl) {
    const item = resolveItem(arg1, arg2, buttonEl);
    if (!item) return;
    openDecisionModal(item);
    document.getElementById('optNoVat').checked = true;
    toggleVatFields();
    setTimeout(() => {
        const reasonSelect = document.getElementById('mReasonCode');
        if (reasonSelect) reasonSelect.focus();
    }, 400);
}

function openVerifiedWithoutReceiptAction(arg1, arg2, buttonEl) {
    const item = resolveItem(arg1, arg2, buttonEl);
    if (!item) {
        console.error('Cannot open Verified Without Receipt: Item not resolved', { arg1, arg2, buttonEl });
        return;
    }

    document.getElementById('vworSourceType').value = item.source_type;
    document.getElementById('vworSourceId').value = item.source_id;
    document.getElementById('vworAmount').value = item.amount;
    document.getElementById('vworRefNo').textContent = item.reference_no;
    document.getElementById('vworPayee').textContent = item.payee_supplier || '—';
    document.getElementById('vworAmountDisplay').textContent = parseFloat(item.amount).toLocaleString('en-US', {minimumFractionDigits: 2});

    const reasonSelect = document.getElementById('vworReasonCode');
    if (reasonSelect) {
        reasonSelect.value = item.reason_code || 'no_fiscal_receipt';
    }
    const noteInput = document.getElementById('vworReasonNote');
    if (noteInput) {
        noteInput.value = item.reason_note || 'Audited and verified without receipt (Approved operational expense)';
    }

    showModalSafe('verifiedWithoutReceiptModal');
}

function runQarOcr() {
    const fileInput = document.getElementById('qarFileInput');
    if (!fileInput || !fileInput.files || fileInput.files.length === 0) {
        alert('Please choose a receipt image or PDF file to scan first.');
        return;
    }

    const spinner = document.getElementById('qarOcrSpinner');
    const resultBox = document.getElementById('qarOcrResult');
    const ocrBtn = document.getElementById('btnQarRunOcr');

    spinner.classList.remove('d-none');
    resultBox.classList.add('d-none');
    if (ocrBtn) ocrBtn.disabled = true;

    const fd = new FormData();
    fd.append('receipt_file', fileInput.files[0]);
    fd.append('_token', '{{ csrf_token() }}');

    fetch("{{ route('finance.expense-hub.scan-receipt') }}", {
        method: 'POST',
        body: fd,
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(r => r.json())
    .then(data => {
        spinner.classList.add('d-none');
        if (ocrBtn) ocrBtn.disabled = false;

        if (data.success) {
            if (data.fs_no) document.getElementById('qarFsNo').value = data.fs_no;
            if (data.supplier_tin) document.getElementById('qarTin').value = data.supplier_tin;
            if (data.vendor_name) document.getElementById('qarSellerName').value = data.vendor_name;
            if (data.receipt_date) document.getElementById('qarReceiptDate').value = data.receipt_date;
            if (data.file_path) document.getElementById('qarExistingPath').value = data.file_path;

            if (data.vat_amount > 0) {
                document.getElementById('qarOptVat').checked = true;
            } else if (data.is_no_vat) {
                document.getElementById('qarOptNoVat').checked = true;
            }

            resultBox.className = 'alert alert-success py-1.5 px-2 text-xxs mb-2';
            resultBox.innerHTML = '<i class="fa-solid fa-circle-check text-success me-1"></i> OCR extracted: ' +
                (data.vendor_name ? '<strong>' + escapeHtml(data.vendor_name) + '</strong> | ' : '') +
                (data.fs_no ? 'FS: ' + escapeHtml(data.fs_no) + ' | ' : '') +
                (data.vat_amount > 0 ? 'VAT: ' + data.vat_amount.toFixed(2) + ' ETB' : '0% VAT');
            resultBox.classList.remove('d-none');
        } else {
            resultBox.className = 'alert alert-warning py-1.5 px-2 text-xxs mb-2';
            resultBox.innerHTML = '<i class="fa-solid fa-triangle-exclamation me-1"></i> ' + (data.message || 'Could not auto-extract fields. Please fill manually.');
            resultBox.classList.remove('d-none');
        }
    })
    .catch(err => {
        spinner.classList.add('d-none');
        if (ocrBtn) ocrBtn.disabled = false;
        resultBox.className = 'alert alert-danger py-1.5 px-2 text-xxs mb-2';
        resultBox.innerHTML = '<i class="fa-solid fa-triangle-exclamation me-1"></i> Scan request failed. You can still save manually.';
        resultBox.classList.remove('d-none');
    });
}

function runOcrUpload() {
    const fileInput = document.getElementById('mOcrFileInput');
    if (!fileInput || !fileInput.files || fileInput.files.length === 0) {
        alert('Please choose a receipt image or PDF file first.');
        return;
    }

    const file = fileInput.files[0];
    const formData = new FormData();
    formData.append('receipt_file', file);
    formData.append('_token', '{{ csrf_token() }}');

    executeOcrRequest(formData);
}

function runOcrAttached() {
    if (!currentEditingItem || !currentEditingItem.receipt_url) {
        alert('No receipt is attached to this expense record.');
        return;
    }

    const formData = new FormData();
    formData.append('receipt_path', currentEditingItem.receipt_url);
    formData.append('_token', '{{ csrf_token() }}');

    executeOcrRequest(formData);
}

function executeOcrRequest(formData) {
    const spinner = document.getElementById('ocrSpinner');
    const resultBox = document.getElementById('ocrResultBox');
    const badge = document.getElementById('ocrStatusBadge');
    const uploadBtn = document.getElementById('btnUploadOcrScan');
    const attachedBtn = document.getElementById('btnAttachedOcrScan');

    spinner.classList.remove('d-none');
    resultBox.classList.add('d-none');
    badge.className = 'badge bg-warning text-dark text-xxs';
    badge.textContent = 'Processing OCR...';
    if (uploadBtn) uploadBtn.disabled = true;
    if (attachedBtn) attachedBtn.disabled = true;

    fetch("{{ route('finance.expense-hub.scan-receipt') }}", {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'Accept': 'application/json'
        },
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        spinner.classList.add('d-none');
        if (uploadBtn) uploadBtn.disabled = false;
        if (attachedBtn) attachedBtn.disabled = false;

        if (!data.success) {
            badge.className = 'badge bg-danger text-white text-xxs';
            badge.textContent = 'OCR Failed';
            resultBox.className = 'mt-2.5 p-2.5 rounded-3 bg-danger bg-opacity-10 border border-danger text-danger text-xs';
            resultBox.innerHTML = '<i class="fa-solid fa-circle-exclamation me-1"></i> ' + (data.message || 'Failed to scan receipt with OCR.');
            resultBox.classList.remove('d-none');
            return;
        }

        handleOcrSuccess(data);
    })
    .catch(err => {
        spinner.classList.add('d-none');
        if (uploadBtn) uploadBtn.disabled = false;
        if (attachedBtn) attachedBtn.disabled = false;
        badge.className = 'badge bg-danger text-white text-xxs';
        badge.textContent = 'OCR Error';
        resultBox.className = 'mt-2.5 p-2.5 rounded-3 bg-danger bg-opacity-10 border border-danger text-danger text-xs';
        resultBox.innerHTML = '<i class="fa-solid fa-triangle-exclamation me-1"></i> An error occurred during scan: ' + err.message;
        resultBox.classList.remove('d-none');
    });
}

function handleOcrSuccess(res) {
    lastOcrResult = res;
    const badge = document.getElementById('ocrStatusBadge');
    const resultBox = document.getElementById('ocrResultBox');

    const isZeroVat = Boolean(res.is_no_vat) || (parseFloat(res.vat_amount || 0) === 0);
    const isFuel = Boolean(currentEditingItem && (
        (currentEditingItem.reference_no && currentEditingItem.reference_no.includes('FUEL')) ||
        (currentEditingItem.category && currentEditingItem.category.toLowerCase().includes('fuel')) ||
        (currentEditingItem.purpose_detail && currentEditingItem.purpose_detail.toLowerCase().includes('fuel'))
    ));

    // Fill metadata fields (both VAT Applies and TAS No-VAT inputs)
    if (res.supplier_tin) {
        document.getElementById('mTin').value = res.supplier_tin;
        const noVatTin = document.getElementById('mNoVatTin');
        if (noVatTin) noVatTin.value = res.supplier_tin;
    }
    if (res.fs_no) {
        document.getElementById('mFsNo').value = res.fs_no;
        const noVatFsNo = document.getElementById('mNoVatFsNo');
        if (noVatFsNo) noVatFsNo.value = res.fs_no;
    }
    if (res.mrc_no) {
        document.getElementById('mMrcNo').value = res.mrc_no;
        const noVatMrcNo = document.getElementById('mNoVatMrcNo');
        if (noVatMrcNo) noVatMrcNo.value = res.mrc_no;
    }
    if (res.purchase_type) {
        document.getElementById('mPurchaseType').value = res.purchase_type;
    }
    if (res.vat_rate !== undefined) {
        document.getElementById('mVatRate').value = res.vat_rate;
    }
    if (res.file_path) {
        document.getElementById('mReceiptPath').value = res.file_path;
    }
    if (res.receipt_date) {
        document.getElementById('mReceiptDate').value = res.receipt_date;
        const noVatDate = document.getElementById('mNoVatReceiptDate');
        if (noVatDate) noVatDate.value = res.receipt_date;
    }
    if (res.vendor_name) {
        if (document.getElementById('mSellerName')) {
            document.getElementById('mSellerName').value = res.vendor_name;
        }
        if (document.getElementById('mNoVatSellerName')) {
            document.getElementById('mNoVatSellerName').value = res.vendor_name;
        }
    }
    if (res.description) {
        document.getElementById('mItemDescription').value = res.description;
    }

    const purcTypeVal = res.purc_type || (isFuel ? 'PUR_GD' : 'PUR_GD');
    const noVatPurcType = document.getElementById('mNoVatPurcType');
    if (noVatPurcType) noVatPurcType.value = purcTypeVal;
    const mPurcType = document.getElementById('mPurcType');
    if (mPurcType) mPurcType.value = purcTypeVal;

    document.getElementById('modeInclusive').checked = true;

    // Smart classification: If 0% VAT or fuel slip, default to No-VAT (TAS Purchase)
    if (isZeroVat || isFuel) {
        document.getElementById('optNoVat').checked = true;
        const reasonSelect = document.getElementById('mReasonCode');
        if (reasonSelect && !reasonSelect.value) {
            reasonSelect.value = isFuel ? 'fuel_zero_vat' : 'tas_purchase';
        }
        toggleVatFields();

        badge.className = 'badge bg-primary text-white text-xxs shadow-xs';
        badge.innerHTML = '<i class="fa-solid fa-receipt me-1"></i> TAS Purchase (0% VAT)';
    } else {
        document.getElementById('optVatApplies').checked = true;
        toggleVatFields();

        badge.className = 'badge bg-success text-white text-xxs shadow-xs';
        badge.innerHTML = '<i class="fa-solid fa-circle-check me-1"></i> OCR Verified (VAT)';
    }

    recalculateVatPreview();

    // Build OCR Result Box HTML
    let html = `
        <div class="d-flex align-items-center justify-content-between mb-2">
            <div class="d-flex align-items-center gap-1.5">
                <span class="badge ${isZeroVat ? 'bg-primary' : 'bg-success'} text-white">
                    <i class="fa-solid fa-circle-check me-1"></i> ${isZeroVat ? 'OCR Analyzed (0% VAT - TAS Purchase)' : 'OCR Verified (VAT Applies)'}
                </span>
                <span class="text-muted text-xxs">Engine: <strong>${res.engine || 'AI Vision'}</strong> (${res.confidence || 'high'})</span>
            </div>
            ${res.is_duplicate ? '<span class="badge bg-danger text-white"><i class="fa-solid fa-triangle-exclamation me-1"></i> Duplicate FS</span>' : ''}
        </div>
        <div class="row g-2 text-xxs mb-2 border-top border-bottom py-1.5 bg-light rounded">
            <div class="col-4"><strong>FS / Slip #:</strong> <span class="font-monospace fw-bold text-dark">${res.fs_no || '—'}</span></div>
            <div class="col-4"><strong>Seller TIN:</strong> <span class="font-monospace fw-bold text-dark">${res.supplier_tin || '—'}</span></div>
            <div class="col-4"><strong>MRC No:</strong> <span class="font-monospace text-dark">${res.mrc_no || '—'}</span></div>
            <div class="col-4"><strong>Subtotal:</strong> ${parseFloat(res.subtotal || 0).toLocaleString('en-US', {minimumFractionDigits: 2})} ETB</div>
            <div class="col-4 ${isZeroVat ? 'text-secondary' : 'text-success'}"><strong>VAT:</strong> ${isZeroVat ? '0.00 ETB (0% VAT / TAS)' : parseFloat(res.vat_amount || 0).toLocaleString('en-US', {minimumFractionDigits: 2}) + ' ETB'}</div>
            <div class="col-4 text-primary"><strong>Grand Total:</strong> ${parseFloat(res.total_amount || 0).toLocaleString('en-US', {minimumFractionDigits: 2})} ETB</div>
        </div>
    `;

    if (res.duplicate_message) {
        html += `<div class="alert alert-warning py-1 px-2 text-xxs mb-2"><i class="fa-solid fa-triangle-exclamation me-1"></i> ${res.duplicate_message}</div>`;
    }

    html += `
        <div class="d-flex justify-content-between align-items-center pt-1 flex-wrap gap-2">
            <div>
                <span class="text-muted text-xxs"><i class="fa-solid fa-receipt me-1"></i> Ready for e-Tax / TAS Export (9 columns)</span>
            </div>
            <div class="d-flex gap-2">
                <button type="button" class="btn btn-sm btn-dark px-3 shadow-xs fw-semibold" onclick="applyOcrAndSaveTas()" title="Save as TAS Purchase (0% VAT) for pro.doc 9-Column Excel format">
                    <i class="fa-solid fa-file-excel text-success me-1"></i> Save to TAS Excel (0% VAT)
                </button>
                <button type="button" class="btn btn-sm btn-outline-success px-3 shadow-xs fw-semibold" onclick="applyOcrAndSaveVat()" title="Save as 15% VAT Applicable (Line 100 Input VAT)">
                    <i class="fa-solid fa-percent me-1"></i> Save to VAT Now
                </button>
            </div>
        </div>
    `;

    resultBox.className = 'mt-2.5 p-2.5 rounded-3 bg-white border ' + (isZeroVat ? 'border-primary' : 'border-success') + ' border-opacity-50 text-xs shadow-xs';
    resultBox.innerHTML = html;
    resultBox.classList.remove('d-none');
}

function applyOcrAndSaveTas() {
    document.getElementById('optNoVat').checked = true;
    toggleVatFields();

    const sellerVal = document.getElementById('mNoVatSellerName')?.value || document.getElementById('mSellerName')?.value;
    const tinVal = document.getElementById('mNoVatTin')?.value || document.getElementById('mTin')?.value;
    const fsVal  = document.getElementById('mNoVatFsNo')?.value || document.getElementById('mFsNo')?.value;
    const mrcVal = document.getElementById('mNoVatMrcNo')?.value || document.getElementById('mMrcNo')?.value;
    const dtVal  = document.getElementById('mNoVatReceiptDate')?.value || document.getElementById('mReceiptDate')?.value;
    const pTypeVal = document.getElementById('mNoVatPurcType')?.value || 'PUR_GD';

    if (sellerVal && document.getElementById('mSellerName')) document.getElementById('mSellerName').value = sellerVal;
    document.getElementById('mTin').value = tinVal;
    document.getElementById('mFsNo').value = fsVal;
    document.getElementById('mMrcNo').value = mrcVal;
    if (dtVal) document.getElementById('mReceiptDate').value = dtVal;
    document.getElementById('mPurcType').value = pTypeVal;

    const reasonSelect = document.getElementById('mReasonCode');
    if (!reasonSelect.value) {
        const isFuel = Boolean(currentEditingItem && (
            (currentEditingItem.reference_no && currentEditingItem.reference_no.includes('FUEL')) ||
            (currentEditingItem.category && currentEditingItem.category.toLowerCase().includes('fuel'))
        ));
        reasonSelect.value = isFuel ? 'fuel_zero_vat' : 'tas_purchase';
    }

    document.getElementById('decisionForm').submit();
}

function applyOcrAndSaveVat() {
    document.getElementById('optVatApplies').checked = true;
    toggleVatFields();

    const sellerVal = document.getElementById('mSellerName')?.value || document.getElementById('mNoVatSellerName')?.value;
    const tinVal = document.getElementById('mTin')?.value || document.getElementById('mNoVatTin')?.value;
    const fsVal  = document.getElementById('mFsNo')?.value || document.getElementById('mNoVatFsNo')?.value;
    const mrcVal = document.getElementById('mMrcNo')?.value || document.getElementById('mNoVatMrcNo')?.value;
    if (sellerVal && document.getElementById('mSellerName')) document.getElementById('mSellerName').value = sellerVal;
    document.getElementById('mTin').value = tinVal;
    document.getElementById('mFsNo').value = fsVal;
    document.getElementById('mMrcNo').value = mrcVal;

    document.getElementById('decisionForm').submit();
}

function syncField(fromId, toId) {
    const fromEl = document.getElementById(fromId);
    const toEl = document.getElementById(toId);
    if (fromEl && toEl) {
        toEl.value = fromEl.value;
    }
}

document.addEventListener('DOMContentLoaded', function() {
    document.getElementById('decisionForm')?.addEventListener('submit', function() {
        if (document.getElementById('optNoVat')?.checked) {
            const sellerVal = document.getElementById('mNoVatSellerName')?.value;
            const tinVal = document.getElementById('mNoVatTin')?.value;
            const fsVal  = document.getElementById('mNoVatFsNo')?.value;
            const mrcVal = document.getElementById('mNoVatMrcNo')?.value;
            const dtVal  = document.getElementById('mNoVatReceiptDate')?.value;
            const pTypeVal = document.getElementById('mNoVatPurcType')?.value;

            if (sellerVal && document.getElementById('mSellerName')) document.getElementById('mSellerName').value = sellerVal;
            if (tinVal) document.getElementById('mTin').value = tinVal;
            if (fsVal) document.getElementById('mFsNo').value = fsVal;
            const mrcVal = document.getElementById('mNoVatMrcNo')?.value;
            const dtVal  = document.getElementById('mNoVatReceiptDate')?.value;
            const pTypeVal = document.getElementById('mNoVatPurcType')?.value;

            if (tinVal) document.getElementById('mTin').value = tinVal;
            if (fsVal) document.getElementById('mFsNo').value = fsVal;
            if (mrcVal) document.getElementById('mMrcNo').value = mrcVal;
            if (dtVal) document.getElementById('mReceiptDate').value = dtVal;
            if (pTypeVal) document.getElementById('mPurcType').value = pTypeVal;
        }
    });
});

function toggleVatFields() {
    const status = document.querySelector('input[name="vat_status"]:checked')?.value;
    const vatSec = document.getElementById('vatAppliesSection');
    const noVatSec = document.getElementById('noVatSection');
    const mIsVworInput = document.getElementById('mIsVerifiedWithoutReceipt');

    if (status === 'vat_applies') {
        vatSec?.classList.remove('d-none');
        noVatSec?.classList.add('d-none');
        if (mIsVworInput) mIsVworInput.value = '0';
    } else if (status === 'no_vat') {
        vatSec?.classList.add('d-none');
        noVatSec?.classList.remove('d-none');
        if (mIsVworInput) mIsVworInput.value = '0';
    } else if (status === 'verified_no_receipt') {
        vatSec?.classList.add('d-none');
        noVatSec?.classList.remove('d-none');
        if (mIsVworInput) mIsVworInput.value = '1';
        const reasonSelect = document.getElementById('mReasonCode');
        if (reasonSelect && !reasonSelect.value) {
            reasonSelect.value = 'no_fiscal_receipt';
        }
        const reasonNote = document.getElementById('mReasonNote');
        if (reasonNote && !reasonNote.value) {
            reasonNote.value = 'Audited and verified without receipt (Approved operational expense)';
        }
    } else {
        vatSec?.classList.add('d-none');
        noVatSec?.classList.add('d-none');
        if (mIsVworInput) mIsVworInput.value = '0';
    }

    recalculateVatPreview();
}

function toggleWithholdingFields() {
    const isChecked = document.getElementById('mHasWithholding')?.checked;
    const sec = document.getElementById('withholdingFieldsSection');
    const prevWhtContainer = document.getElementById('prevWhtContainer');

    if (isChecked) {
        sec?.classList.remove('d-none');
        prevWhtContainer?.classList.remove('d-none');
    } else {
        sec?.classList.add('d-none');
        prevWhtContainer?.classList.add('d-none');
    }
    recalculateVatPreview();
}

function toggleBulkWithholding() {
    const isChecked = document.getElementById('bulkHasWithholding')?.checked;
    const grp = document.getElementById('bulkWithholdingGroup');
    if (isChecked) {
        grp?.classList.remove('d-none');
    } else {
        grp?.classList.add('d-none');
    }
}

function recalculateVatPreview() {
    if (!currentEditingItem) return;
    const amount = parseFloat(currentEditingItem.amount) || 0;
    const rate = parseFloat(document.getElementById('mVatRate')?.value) || 15.00;
    const isInclusive = document.getElementById('modeInclusive')?.checked ?? true;
    const status = document.querySelector('input[name="vat_status"]:checked')?.value;
    const hasWithholding = document.getElementById('mHasWithholding')?.checked;
    const whtRate = parseFloat(document.getElementById('mWithholdingRate')?.value) || 3.00;

    let base = 0, vat = 0, total = 0;
    if (status === 'vat_applies') {
        if (isInclusive) {
            base = Math.round((amount / (1 + (rate / 100))) * 100) / 100;
            vat = Math.round((amount - base) * 100) / 100;
            total = amount;
        } else {
            base = amount;
            vat = Math.round((amount * (rate / 100)) * 100) / 100;
            total = Math.round((base + vat) * 100) / 100;
        }
    } else {
        base = amount;
        vat = 0;
        total = amount;
    }

    let whtAmount = 0;
    const manualWhtAmtInput = document.getElementById('mWithholdingAmount');
    if (hasWithholding) {
        // Ethiopian 3% Withholding Tax is calculated on taxable base (pre-VAT Subtotal)
        const autoWht = Math.round((base * (whtRate / 100)) * 100) / 100;
        if (manualWhtAmtInput && document.activeElement === manualWhtAmtInput && manualWhtAmtInput.value !== '') {
            whtAmount = parseFloat(manualWhtAmtInput.value) || 0;
        } else {
            whtAmount = autoWht;
            if (manualWhtAmtInput) manualWhtAmtInput.value = whtAmount.toFixed(2);
        }
    } else {
        if (manualWhtAmtInput && document.activeElement !== manualWhtAmtInput) {
            manualWhtAmtInput.value = '';
        }
    }

    const netPayable = Math.max(0, Math.round((total - whtAmount) * 100) / 100);

    const prevBase = document.getElementById('prevBase');
    const prevVat = document.getElementById('prevVat');
    const prevWht = document.getElementById('prevWht');
    const prevTotal = document.getElementById('prevTotal');
    const prevNet = document.getElementById('prevNet');

    if (prevBase) prevBase.textContent = base.toLocaleString('en-US', {minimumFractionDigits: 2});
    if (prevVat) prevVat.textContent = vat.toLocaleString('en-US', {minimumFractionDigits: 2});
    if (prevWht) prevWht.textContent = whtAmount.toLocaleString('en-US', {minimumFractionDigits: 2});
    if (prevTotal) prevTotal.textContent = total.toLocaleString('en-US', {minimumFractionDigits: 2});
    if (prevNet) prevNet.textContent = netPayable.toLocaleString('en-US', {minimumFractionDigits: 2});
}

// ── Bulk Actions Handlers ────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', function () {
    const selectAll = document.getElementById('selectAllCheckbox');
    const checkboxes = document.querySelectorAll('.item-checkbox');
    const bulkContainer = document.getElementById('bulkActionContainer');
    const countBadge = document.getElementById('selectedCountBadge');

    function updateBulkState() {
        const checked = document.querySelectorAll('.item-checkbox:checked');
        const count = checked.length;
        if (count > 0) {
            bulkContainer.classList.remove('d-none');
            bulkContainer.classList.add('d-flex');
            countBadge.textContent = count;
        } else {
            bulkContainer.classList.remove('d-flex');
            bulkContainer.classList.add('d-none');
        }
    }

    if (selectAll) {
        selectAll.addEventListener('change', function () {
            checkboxes.forEach(cb => cb.checked = selectAll.checked);
            updateBulkState();
        });
    }

    checkboxes.forEach(cb => {
        cb.addEventListener('change', updateBulkState);
    });

    // Close button and action button fallbacks
    document.addEventListener('click', function (e) {
        const closeBtn = e.target.closest('[data-bs-dismiss="modal"], [data-dismiss="modal"]');
        if (closeBtn) {
            const modal = closeBtn.closest('.modal');
            if (modal) {
                hideModalSafe(modal);
            }
            return;
        }

        const detailBtn = e.target.closest('.btn-view-details');
        if (detailBtn && !detailBtn.getAttribute('onclick')) {
            e.preventDefault();
            openExpenseDetailModal(detailBtn.dataset.sourceType, detailBtn.dataset.sourceId, detailBtn);
            return;
        }

        const ocrBtn = e.target.closest('.btn-open-ocr');
        if (ocrBtn && !ocrBtn.getAttribute('onclick')) {
            e.preventDefault();
            openDecisionModalWithOcr(ocrBtn.dataset.sourceType, ocrBtn.dataset.sourceId, ocrBtn);
            return;
        }

        const decisionBtn = e.target.closest('.btn-open-decision');
        if (decisionBtn && !decisionBtn.getAttribute('onclick')) {
            e.preventDefault();
            openDecisionModal(decisionBtn.dataset.sourceType, decisionBtn.dataset.sourceId, decisionBtn);
            return;
        }
    });

    // Handle AJAX submission for Quick Verified Without Receipt
    const vworForm = document.getElementById('vworForm');
    if (vworForm) {
        vworForm.addEventListener('submit', function (e) {
            e.preventDefault();
            const form = this;
            const submitBtn = document.getElementById('vworSubmitBtn');
            const originalHtml = submitBtn.innerHTML;
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Verifying...';

            const formData = new FormData(form);

            fetch(form.action, {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                }
            })
            .then(r => r.json())
            .then(data => {
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalHtml;
                if (data.success) {
                    hideModalSafe('verifiedWithoutReceiptModal');

                    const sType = formData.get('source_type');
                    const sId = formData.get('source_id');
                    const row = document.getElementById('hub-row-' + sType + '-' + sId);
                    if (row) {
                        row.dataset.vatStatus = 'no_vat';
                        row.dataset.isVerifiedWithoutReceipt = '1';
                        row.dataset.reasonCode = formData.get('no_vat_reason_code');
                        row.dataset.reasonNote = formData.get('no_vat_reason_note');
                        row.classList.remove('table-warning');

                        // Update decision badge
                        const statusBtn = row.querySelector('.btn-open-decision');
                        if (statusBtn) {
                            statusBtn.className = 'btn btn-xs rounded-pill px-2.5 py-1 text-xxs fw-semibold bg-info bg-opacity-20 text-info border border-info btn-open-decision';
                            statusBtn.innerHTML = 'Verified w/o Receipt <i class="fa-solid fa-check-double ms-1"></i>';
                        }
                        // Update receipt cell if no receipt
                        if (!row.dataset.receiptUrl) {
                            const cells = row.querySelectorAll('td');
                            if (cells.length >= 10) {
                                cells[9].innerHTML = '<span class="badge bg-light text-muted border text-xxs" title="Audited and verified without receipt"><i class="fa-solid fa-check-double text-info me-0.5"></i> No Slip</span>';
                            }
                        }
                    }
                } else {
                    alert('Error: ' + (data.message || 'Could not verify expense without receipt.'));
                }
            })
            .catch(err => {
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalHtml;
                form.submit();
            });
        });
    }

    // Handle Quick Add Receipt submit button feedback
    const qarForm = document.getElementById('quickAddReceiptForm');
    if (qarForm) {
        qarForm.addEventListener('submit', function (e) {
            const fileInput = document.getElementById('qarFileInput');
            const existingPath = document.getElementById('qarExistingPath')?.value;
            if ((!fileInput.files || fileInput.files.length === 0) && !existingPath) {
                e.preventDefault();
                alert('Please choose a receipt file to attach.');
                return;
            }
            const submitBtn = document.getElementById('qarSubmitBtn');
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Uploading &amp; Saving...';
            }
        });
    }
});

function openBulkModal(targetStatus) {
    const checked = document.querySelectorAll('.item-checkbox:checked');
    if (checked.length === 0) return;

    document.getElementById('bulkCountDisplay').textContent = checked.length;
    document.getElementById('bulkStatusSelect').value = targetStatus;

    const hiddenInputs = document.getElementById('bulkHiddenInputs');
    hiddenInputs.innerHTML = '';
    checked.forEach(cb => {
        const inp = document.createElement('input');
        inp.type = 'hidden';
        inp.name = 'selected_items[]';
        inp.value = cb.value;
        hiddenInputs.appendChild(inp);
    });

    toggleBulkFields();
    showModalSafe('bulkModal');
}

function toggleBulkFields() {
    const st = document.getElementById('bulkStatusSelect')?.value;
    const noVatGrp = document.getElementById('bulkNoVatReasonGroup');
    const vatRateGrp = document.getElementById('bulkVatRateGroup');

    if (st === 'no_vat' || st === 'verified_no_receipt') {
        noVatGrp?.classList.remove('d-none');
        vatRateGrp?.classList.add('d-none');
        if (st === 'verified_no_receipt') {
            const reasonSelect = noVatGrp?.querySelector('select[name="no_vat_reason_code"]');
            if (reasonSelect) reasonSelect.value = 'no_fiscal_receipt';
        }
    } else if (st === 'vat_applies') {
        noVatGrp?.classList.add('d-none');
        vatRateGrp?.classList.remove('d-none');
    } else {
        noVatGrp?.classList.add('d-none');
        vatRateGrp?.classList.add('d-none');
    }
}
</script>
@endsection
