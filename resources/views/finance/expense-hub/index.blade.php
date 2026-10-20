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
        <div class="col-xl-3 col-md-4 col-sm-6">
            <div class="card border-0 shadow-sm h-100 rounded-3 border-start border-success border-4 bg-success bg-opacity-10">
                <div class="card-body p-3">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <span class="text-success-emphasis text-uppercase fw-bold text-xxs">VAT Applies (Inputs)</span>
                        <span class="badge bg-success rounded-pill px-2 py-0.5 text-xxs">15% Taxable</span>
                    </div>
                    <h5 class="mb-0 fw-bold text-success">{{ number_format($summary['vat_applies_total'], 2) }} <small class="text-xxs">ETB</small></h5>
                    <div class="mt-2 text-xxs text-dark d-flex justify-content-between">
                        <span>Input VAT (15%):</span>
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
                    <div class="mt-2 text-xxs text-muted">
                        <span>Exempt, salaries, informal</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- 4. Pending Decision (Highlighted clearly per prompt) -->
        <div class="col-xl-3 col-md-6 col-sm-6">
            <div class="card border-0 shadow-sm h-100 rounded-3 border-start border-warning border-4 bg-warning bg-opacity-10">
                <div class="card-body p-3">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <span class="text-warning-emphasis text-uppercase fw-bold text-xxs">
                            <i class="fa-solid fa-triangle-exclamation me-1"></i> Pending Decision
                        </span>
                        <span class="badge bg-warning text-dark fw-bold rounded-pill px-2 py-0.5 text-xxs">
                            {{ $summary['pending_count'] }} Unclassified
                        </span>
                    </div>
                    <h5 class="mb-0 fw-bold text-warning-emphasis">{{ number_format($summary['pending_total'], 2) }} <small class="text-xxs">ETB</small></h5>
                    <div class="mt-2 text-xxs text-muted">
                        <span class="text-danger fw-semibold">Excluded from VAT filing until decided</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- 5. CapEx Separate -->
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
                <div class="col-md-3 col-sm-6">
                    <label class="form-label text-xxs fw-semibold text-muted mb-1">Search Keyword / Ref / TIN</label>
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-white"><i class="fa-solid fa-magnifying-glass text-muted"></i></span>
                        <input type="text" name="search" class="form-control" placeholder="Ref #, supplier, TIN, desc..." value="{{ request('search') }}">
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
                        <option value="pending_decision" @selected(request('vat_status') === 'pending_decision')>Pending Decision</option>
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
                <div class="col-md-1 col-sm-6 d-flex gap-1">
                    <button type="submit" class="btn btn-sm btn-primary w-100" title="Apply Filters">
                        <i class="fa-solid fa-filter"></i>
                    </button>
                    @if(request()->hasAny(['search', 'project_id', 'source_module', 'vat_status', 'date_from', 'date_to', 'supplier', 'category']))
                    <a href="{{ route('finance.expense-hub.index', ['tab' => $activeTab]) }}" class="btn btn-sm btn-outline-secondary" title="Reset Filters">
                        <i class="fa-solid fa-rotate-left"></i>
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
                        <th style="min-width: 140px;">Source & Ref #</th>
                        <th style="min-width: 130px;">Project / Site</th>
                        <th style="min-width: 160px;">Payee / Supplier</th>
                        <th style="min-width: 100px;">Vendor TIN</th>
                        <th class="text-end" style="min-width: 105px;">Amount (ETB)</th>
                        <th class="text-center" style="min-width: 130px;">VAT Decision</th>
                        <th class="text-end" style="min-width: 95px;">Base (ETB)</th>
                        <th class="text-end" style="min-width: 95px;">VAT (15%)</th>
                        <th class="text-center" style="min-width: 60px;">Receipt</th>
                        <th class="text-end" style="min-width: 75px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($paginatedExpenses as $item)
                    @php
                        $statusBadgeClass = match($item->vat_status) {
                            'vat_applies', \App\Models\ExpenseHubVatDecision::STATUS_VAT_APPLIES => 'bg-success text-white',
                            'no_vat', \App\Models\ExpenseHubVatDecision::STATUS_NO_VAT           => 'bg-secondary text-white',
                            default                                                              => 'bg-warning text-dark border border-warning',
                        };
                        $statusLabel = match($item->vat_status) {
                            'vat_applies', \App\Models\ExpenseHubVatDecision::STATUS_VAT_APPLIES => 'VAT Applies',
                            'no_vat', \App\Models\ExpenseHubVatDecision::STATUS_NO_VAT           => 'No VAT',
                            default                                                              => 'Pending Decision',
                        };
                    @endphp
                    <tr class="{{ !empty($item->warnings) ? 'table-warning bg-opacity-10' : '' }}">
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

                        <!-- Source Module & Ref -->
                        <td>
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
                                <button type="button" class="btn btn-xs rounded-pill px-2.5 py-1 text-xxs fw-semibold {{ $statusBadgeClass }}"
                                        onclick="openDecisionModal({{ json_encode($item) }})" title="Click to review or edit VAT decision">
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
                        </td>

                        <!-- Base & VAT -->
                        <td class="text-end text-xs text-muted">
                            {{ $item->vat_status === 'vat_applies' ? number_format($item->decision_base_amount, 2) : '—' }}
                        </td>
                        <td class="text-end text-xs {{ $item->decision_vat_amount > 0 ? 'text-success fw-bold' : 'text-muted' }}">
                            {{ $item->vat_status === 'vat_applies' && $item->decision_vat_amount > 0 ? number_format($item->decision_vat_amount, 2) : '—' }}
                        </td>

                        <!-- Receipt Link -->
                        <td class="text-center">
                            @if($item->receipt_url)
                                <a href="{{ asset('storage/' . ltrim($item->receipt_url, '/')) }}" target="_blank" 
                                   class="btn btn-xs btn-outline-info rounded-pill px-2 py-0.5" title="View attached receipt / voucher">
                                    <i class="fa-solid fa-paperclip"></i>
                                </a>
                            @else
                                <span class="text-muted text-xxs">—</span>
                            @endif
                        </td>

                        <!-- Action -->
                        <td class="text-end">
                            @if($canDecideVat)
                            <button type="button" class="btn btn-xs btn-light border rounded-pill px-2 py-1 text-xs text-dark"
                                    onclick="openDecisionModal({{ json_encode($item) }})" title="Open Decision Studio">
                                <i class="fa-solid fa-sliders"></i>
                            </button>
                            @endif
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
                        <td class="text-center fw-bold">{{ $v->vat_category ?: 'G' }}</td>
                        <td class="text-center">{{ $v->calendar_type ?: 'E' }}</td>
                        <td class="text-center"><span class="badge bg-light text-dark border">{{ $v->purchase_type ?: 3 }}</span></td>
                        <td class="font-monospace fw-semibold">{{ $v->tin ?: '—' }}</td>
                        <td class="fw-semibold text-dark">{{ $v->payee_supplier }}</td>
                        <td class="text-nowrap">{{ $ethDate }}</td>
                        <td class="text-muted">{{ $v->mrc_no ?: '—' }}</td>
                        <td class="fw-semibold">{{ $v->fs_no ?: '—' }}</td>
                        <td>{{ Str::limit($v->item_desc ?: $v->description, 30) }}</td>
                        <td class="text-center">{{ $v->uom ?: '9' }}</td>
                        <td class="text-end">{{ number_format($v->qty, 2) }}</td>
                        <td class="text-end">{{ number_format($v->unit_price, 2) }}</td>
                        <td class="text-end fw-semibold">{{ number_format($v->decision_base_amount, 2) }}</td>
                        <td class="text-end fw-bold text-success">{{ number_format($v->decision_vat_amount, 2) }}</td>
                        <td class="text-end fw-bold text-dark">{{ number_format($v->decision_total_amount, 2) }}</td>
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
            <form id="decisionForm" method="POST" action="{{ route('finance.expense-hub.decision') }}">
                @csrf
                <input type="hidden" name="source_type" id="mSourceType">
                <input type="hidden" name="source_id" id="mSourceId">
                <input type="hidden" name="amount" id="mAmount">

                <div class="modal-body p-4">
                    <!-- Item Overview Row -->
                    <div class="p-3 bg-light rounded-3 mb-3 border">
                        <div class="row g-2 text-xs">
                            <div class="col-md-4">
                                <span class="text-muted d-block">Payee / Supplier:</span>
                                <strong id="mPayee" class="text-dark"></strong>
                            </div>
                            <div class="col-md-4">
                                <span class="text-muted d-block">Source Module:</span>
                                <strong id="mSourceModule" class="text-dark"></strong>
                            </div>
                            <div class="col-md-4">
                                <span class="text-muted d-block">Disbursed Amount:</span>
                                <strong id="mAmountDisplay" class="text-dark fs-6"></strong> ETB
                            </div>
                        </div>
                    </div>

                    <!-- VAT Decision Radio Group -->
                    <div class="mb-3">
                        <label class="form-label text-xs fw-bold text-uppercase text-muted">Select VAT Classification <span class="text-danger">*</span></label>
                        <div class="d-flex gap-2">
                            <input type="radio" class="btn-check" name="vat_status" id="optVatApplies" value="vat_applies" onchange="toggleVatFields()">
                            <label class="btn btn-outline-success flex-fill py-2 text-xs fw-semibold" for="optVatApplies">
                                <i class="fa-solid fa-check me-1"></i> VAT Applies (Input VAT)
                            </label>

                            <input type="radio" class="btn-check" name="vat_status" id="optNoVat" value="no_vat" onchange="toggleVatFields()">
                            <label class="btn btn-outline-secondary flex-fill py-2 text-xs fw-semibold" for="optNoVat">
                                <i class="fa-solid fa-ban me-1"></i> No VAT (Exempt / Salary)
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

                            <!-- Live Calculation Preview Card -->
                            <div class="col-12">
                                <div class="p-2.5 bg-light rounded-3 d-flex justify-content-between align-items-center text-xs border">
                                    <div>Base Subtotal: <strong id="prevBase" class="text-dark">0.00</strong> ETB</div>
                                    <div>+ 15% VAT: <strong id="prevVat" class="text-success fs-6">0.00</strong> ETB</div>
                                    <div>= Grand Total: <strong id="prevTotal" class="text-primary">0.00</strong> ETB</div>
                                </div>
                            </div>

                            <!-- Official VAT Report Metadata -->
                            <div class="col-md-4">
                                <label class="form-label text-xs">Vendor TIN (10 Digits)</label>
                                <input type="text" name="custom_seller_tin" id="mTin" class="form-control form-control-sm" placeholder="e.g. 0012345678">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label text-xs">Fiscal Receipt # (FS No)</label>
                                <input type="text" name="custom_fs_no" id="mFsNo" class="form-control form-control-sm" placeholder="e.g. 00045892">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label text-xs">MRC Number</label>
                                <input type="text" name="custom_mrc_no" id="mMrcNo" class="form-control form-control-sm" placeholder="e.g. CAS1029">
                            </div>
                        </div>
                    </div>

                    <!-- No VAT Justification Section -->
                    <div id="noVatSection" class="p-3 border rounded-3 mb-3 bg-light d-none">
                        <div class="row g-2">
                            <div class="col-md-6">
                                <label class="form-label text-xs fw-semibold text-dark">Reason for No VAT <span class="text-danger">*</span></label>
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

function openDecisionModal(item) {
    currentEditingItem = item;

    document.getElementById('mSourceType').value = item.source_type;
    document.getElementById('mSourceId').value = item.source_id;
    document.getElementById('mAmount').value = item.amount;
    document.getElementById('mRefNo').textContent = item.reference_no;
    document.getElementById('mPayee').textContent = item.payee_supplier || '—';
    document.getElementById('mSourceModule').textContent = item.source_module_label;
    document.getElementById('mAmountDisplay').textContent = parseFloat(item.amount).toLocaleString('en-US', {minimumFractionDigits: 2});

    // Populate radio status
    if (item.vat_status === 'vat_applies') {
        document.getElementById('optVatApplies').checked = true;
    } else if (item.vat_status === 'no_vat') {
        document.getElementById('optNoVat').checked = true;
    } else {
        document.getElementById('optPending').checked = true;
    }

    // Prefill metadata
    document.getElementById('mTin').value = item.tin || '';
    document.getElementById('mFsNo').value = item.fs_no || '';
    document.getElementById('mMrcNo').value = item.mrc_no || '';
    document.getElementById('mVatRate').value = item.vat_rate || 15.00;
    document.getElementById('mPurchaseType').value = item.purchase_type || (item.is_capex ? 1 : 3);
    document.getElementById('mReasonCode').value = item.reason_code || '';
    document.getElementById('mReasonNote').value = item.reason_note || '';

    if (item.is_inclusive === false) {
        document.getElementById('modeExclusive').checked = true;
    } else {
        document.getElementById('modeInclusive').checked = true;
    }

    toggleVatFields();
    recalculateVatPreview();

    new bootstrap.Modal(document.getElementById('decisionModal')).show();
}

function toggleVatFields() {
    const status = document.querySelector('input[name="vat_status"]:checked')?.value;
    const vatSec = document.getElementById('vatAppliesSection');
    const noVatSec = document.getElementById('noVatSection');

    if (status === 'vat_applies') {
        vatSec.classList.remove('d-none');
        noVatSec.classList.add('d-none');
    } else if (status === 'no_vat') {
        vatSec.classList.add('d-none');
        noVatSec.classList.remove('d-none');
    } else {
        vatSec.classList.add('d-none');
        noVatSec.classList.add('d-none');
    }
}

function recalculateVatPreview() {
    if (!currentEditingItem) return;
    const amount = parseFloat(currentEditingItem.amount) || 0;
    const rate = parseFloat(document.getElementById('mVatRate').value) || 15.00;
    const isInclusive = document.getElementById('modeInclusive').checked;

    let base = 0, vat = 0, total = 0;
    if (isInclusive) {
        base = Math.round((amount / (1 + (rate / 100))) * 100) / 100;
        vat = Math.round((amount - base) * 100) / 100;
        total = amount;
    } else {
        base = amount;
        vat = Math.round((amount * (rate / 100)) * 100) / 100;
        total = Math.round((base + vat) * 100) / 100;
    }

    document.getElementById('prevBase').textContent = base.toLocaleString('en-US', {minimumFractionDigits: 2});
    document.getElementById('prevVat').textContent = vat.toLocaleString('en-US', {minimumFractionDigits: 2});
    document.getElementById('prevTotal').textContent = total.toLocaleString('en-US', {minimumFractionDigits: 2});
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
    new bootstrap.Modal(document.getElementById('bulkModal')).show();
}

function toggleBulkFields() {
    const st = document.getElementById('bulkStatusSelect').value;
    const noVatGrp = document.getElementById('bulkNoVatReasonGroup');
    const vatRateGrp = document.getElementById('bulkVatRateGroup');

    if (st === 'no_vat') {
        noVatGrp.classList.remove('d-none');
        vatRateGrp.classList.add('d-none');
    } else if (st === 'vat_applies') {
        noVatGrp.classList.add('d-none');
        vatRateGrp.classList.remove('d-none');
    } else {
        noVatGrp.classList.add('d-none');
        vatRateGrp.classList.add('d-none');
    }
}
</script>
@endsection
