@extends('layouts.app')

@section('title', 'VAT & Withholding Tax Deductions Ledger')

@section('content')
<div class="container-fluid px-3 px-md-4 py-4">

    {{-- Alerts --}}
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm rounded-4 mb-4" role="alert">
            <div class="d-flex align-items-center gap-2">
                <i class="fa-solid fa-circle-check fs-5 text-success"></i>
                <div>{{ session('success') }}</div>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm rounded-4 mb-4" role="alert">
            <div class="d-flex align-items-center gap-2">
                <i class="fa-solid fa-triangle-exclamation fs-5 text-danger"></i>
                <div>{{ session('error') }}</div>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    {{-- Pending Settlements Notification Banner --}}
    @if(isset($pendingSettlements) && $pendingSettlements->isNotEmpty())
        @foreach($pendingSettlements as $ps)
            <div class="alert alert-warning border-0 rounded-4 shadow-sm mb-4 d-flex flex-column flex-lg-row align-items-start align-items-lg-center justify-content-between gap-3 p-3 p-md-4 bg-warning bg-opacity-10 border-start border-4 border-warning">
                <div class="d-flex align-items-start gap-3">
                    <div class="p-3 bg-warning text-dark rounded-circle shadow-xs fs-4">
                        <i class="fa-solid fa-hourglass-half"></i>
                    </div>
                    <div>
                        <div class="d-flex align-items-center gap-2 flex-wrap mb-1">
                            <span class="badge bg-warning text-dark fw-bold px-2 py-1">TAX REMITTANCE ASSIGNED</span>
                            <span class="fw-bold text-dark fs-6">{{ $ps->settlement_number }}</span>
                            <span class="text-muted small">({{ optional($ps->created_at)->format('M d, Y H:i') }})</span>
                        </div>
                        <div class="text-dark small">
                            Finance Head assigned: <strong class="text-primary">{{ $ps->assignedStaff?->name ?? 'Finance Staff' }}</strong> &bull; 
                            Tax Amount to Pay: <strong class="text-danger fs-6">ETB {{ number_format($ps->total_tax_paid, 2) }}</strong> 
                            <span class="text-muted">(VAT: ETB {{ number_format($ps->vat_amount, 2) }} | 3% WHT: ETB {{ number_format($ps->withholding_amount, 2) }})</span> &bull; 
                            Covering <strong>{{ $ps->records_count }}</strong> tax deduction records.
                        </div>
                        <div class="text-muted small mt-1">
                            <i class="fa-solid fa-circle-info me-1 text-warning"></i>
                            Awaiting Finance Staff to disburse tax payment to ERCA / Bank and upload official receipt slip.
                        </div>
                    </div>
                </div>
                <div class="d-flex align-items-center gap-2 flex-wrap mt-2 mt-lg-0">
                    <button type="button" class="btn btn-success btn-sm rounded-pill px-3 shadow-sm fw-semibold" 
                            data-bs-toggle="modal" data-bs-target="#recordPaymentModal{{ $ps->id }}">
                        <i class="fa-solid fa-file-circle-check me-1"></i> Record Payment &amp; Upload Receipt
                    </button>
                    <form method="POST" action="{{ route('finance.tax-deductions.settle.cancel', $ps) }}" onsubmit="return confirm('Cancel this tax remittance request and return records to active pool?');" class="d-inline">
                        @csrf
                        <button type="submit" class="btn btn-outline-danger btn-sm rounded-pill px-3">
                            <i class="fa-solid fa-xmark me-1"></i> Cancel
                        </button>
                    </form>
                </div>
            </div>
        @endforeach
    @endif

    {{-- Header & Actions --}}
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-3 mb-4">
        <div>
            <div class="d-flex align-items-center gap-2">
                <div class="p-2 rounded-3 bg-danger bg-opacity-10 text-danger fs-4">
                    <i class="fa-solid fa-receipt"></i>
                </div>
                <div>
                    <h2 class="h4 fw-bold text-dark mb-0">VAT &amp; Withholding Tax Deductions</h2>
                    <p class="text-muted small mb-0">የቫት እና የ3% ቅድመ ግብር ተቀናሾች መከታተያ እና ሪፖርት (Tax Compliance Ledger)</p>
                </div>
            </div>
        </div>
        <div class="d-flex align-items-center gap-2 flex-wrap">
            {{-- Pay VAT & Withhold Action Button --}}
            <button type="button" class="btn btn-danger btn-sm rounded-pill px-3 shadow-sm fw-semibold" data-bs-toggle="modal" data-bs-target="#payTaxModal">
                <i class="fa-solid fa-money-bill-transfer me-1"></i> Pay VAT &amp; Withhold (Reset to Zero)
            </button>

            <button type="button" onclick="window.print()" class="btn btn-outline-secondary btn-sm rounded-pill px-3 shadow-xs">
                <i class="fa-solid fa-print me-1"></i> Print Report
            </button>
            <a href="{{ route('finance.tax-deductions.export-csv', request()->query()) }}" class="btn btn-success btn-sm rounded-pill px-3 shadow-xs fw-semibold">
                <i class="fa-solid fa-file-excel me-1"></i> Export to CSV (ERCA)
            </a>
            <a href="{{ route('expenses.index') }}" class="btn btn-outline-primary btn-sm rounded-pill px-3 shadow-xs">
                <i class="fa-solid fa-arrow-left me-1"></i> Approvals Hub
            </a>
        </div>
    </div>

    {{-- Cycle Switcher Navigation Bar --}}
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3 bg-white p-2 rounded-4 shadow-xs border">
        <div class="d-flex align-items-center gap-2 flex-wrap">
            <span class="small fw-bold text-muted text-uppercase ms-2 me-1"><i class="fa-solid fa-filter me-1 text-primary"></i>View Cycle:</span>
            
            <a href="{{ route('finance.tax-deductions.index', array_merge(request()->except('page', 'settlements_page'), ['cycle' => 'active', 'tab' => $tab === 'settlements' ? 'all' : $tab])) }}" 
               class="btn btn-sm rounded-pill px-3 fw-semibold {{ $cycle === 'active' && $tab !== 'settlements' ? 'btn-primary shadow-xs' : 'btn-light text-secondary' }}">
                <i class="fa-solid fa-bolt me-1 text-warning"></i> Current Active Period (Start from Zero)
                @if($unsettledCount > 0)
                    <span class="badge bg-white text-primary ms-1">{{ $unsettledCount }}</span>
                @else
                    <span class="badge bg-success text-white ms-1">0 - Clean</span>
                @endif
            </a>

            <a href="{{ route('finance.tax-deductions.index', array_merge(request()->except('page', 'settlements_page'), ['cycle' => 'all', 'tab' => $tab === 'settlements' ? 'all' : $tab])) }}" 
               class="btn btn-sm rounded-pill px-3 fw-semibold {{ $cycle === 'all' && $tab !== 'settlements' ? 'btn-primary shadow-xs' : 'btn-light text-secondary' }}">
                <i class="fa-solid fa-layer-group me-1"></i> All-Time Full Ledger
            </a>

            <a href="{{ route('finance.tax-deductions.index', array_merge(request()->except('page', 'settlements_page'), ['cycle' => 'settled', 'tab' => $tab === 'settlements' ? 'all' : $tab])) }}" 
               class="btn btn-sm rounded-pill px-3 fw-semibold {{ $cycle === 'settled' && $tab !== 'settlements' ? 'btn-primary shadow-xs' : 'btn-light text-secondary' }}">
                <i class="fa-solid fa-circle-check me-1 text-success"></i> Past Settled Records (Paid to ERCA)
            </a>

            <a href="{{ route('finance.tax-deductions.index', array_merge(request()->except('page', 'settlements_page'), ['tab' => 'settlements'])) }}" 
               class="btn btn-sm rounded-pill px-3 fw-semibold {{ $tab === 'settlements' ? 'btn-danger shadow-xs text-white' : 'btn-light text-danger' }}">
                <i class="fa-solid fa-file-invoice-dollar me-1"></i> Tax Payment Settlements ({{ $settlements->total() }})
            </a>
        </div>

        <div class="me-2 text-muted small">
            @if($cycle === 'active')
                <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1"><i class="fa-solid fa-rotate-right me-1"></i>Active Cycle: Accumulating</span>
            @elseif($cycle === 'settled')
                <span class="badge bg-info-subtle text-info border border-info-subtle px-2 py-1"><i class="fa-solid fa-box-archive me-1"></i>Archived Paid Taxes</span>
            @else
                <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle px-2 py-1"><i class="fa-solid fa-database me-1"></i>All Records</span>
            @endif
        </div>
    </div>

    {{-- KPI Metric Summary Cards --}}
    <div class="row g-3 mb-4">
        {{-- Total Base Invoiced --}}
        <div class="col-12 col-sm-6 col-xl">
            <div class="card border-0 shadow-sm rounded-4 h-100 p-3 bg-white border-start border-4 border-primary">
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <span class="text-muted small text-uppercase fw-bold">Base Invoiced Amount</span>
                    <span class="badge bg-primary-subtle text-primary rounded-pill"><i class="fa-solid fa-file-invoice"></i></span>
                </div>
                <div class="fs-4 fw-bold text-dark">ETB {{ number_format($totalGrossBase, 2) }}</div>
                <div class="text-muted small" style="font-size:0.75rem;">ጠቅላላ የመነሻ ዋጋ</div>
            </div>
        </div>

        {{-- Total VAT --}}
        <div class="col-12 col-sm-6 col-xl">
            <div class="card border-0 shadow-sm rounded-4 h-100 p-3 bg-white border-start border-4 border-info">
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <span class="text-muted small text-uppercase fw-bold">Total VAT (15% / VAT B)</span>
                    <span class="badge bg-info-subtle text-info rounded-pill"><i class="fa-solid fa-percent"></i></span>
                </div>
                <div class="fs-4 fw-bold text-info">+ ETB {{ number_format($totalVatAmount, 2) }}</div>
                <div class="text-muted small" style="font-size:0.75rem;">ጠቅላላ የተጨመረ/የተካተተ ቫት</div>
            </div>
        </div>

        {{-- Total Withholding Tax --}}
        <div class="col-12 col-sm-6 col-xl">
            <div class="card border-0 shadow-sm rounded-4 h-100 p-3 bg-white border-start border-4 border-danger">
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <span class="text-muted small text-uppercase fw-bold">Withholding Tax (3%)</span>
                    <span class="badge bg-danger-subtle text-danger rounded-pill"><i class="fa-solid fa-hand-holding-dollar"></i></span>
                </div>
                <div class="fs-4 fw-bold text-danger">- ETB {{ number_format($totalWithholdingAmount, 2) }}</div>
                <div class="text-muted small" style="font-size:0.75rem;">ጠቅላላ የተቀነሰ 3% ቅድመ ግብር</div>
            </div>
        </div>

        {{-- Total Net Disbursed --}}
        <div class="col-12 col-sm-6 col-xl">
            <div class="card border-0 shadow-sm rounded-4 h-100 p-3 bg-white border-start border-4 border-success">
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <span class="text-muted small text-uppercase fw-bold">Net Disbursed / Paid</span>
                    <span class="badge bg-success-subtle text-success rounded-pill"><i class="fa-solid fa-circle-check"></i></span>
                </div>
                <div class="fs-4 fw-bold text-success">ETB {{ number_format($totalNetDisbursed, 2) }}</div>
                <div class="text-muted small" style="font-size:0.75rem;">ጠቅላላ የተጣራ የተከፈለ</div>
            </div>
        </div>

        {{-- Verified Slips Count --}}
        <div class="col-12 col-sm-6 col-xl">
            <div class="card border-0 shadow-sm rounded-4 h-100 p-3 bg-white border-start border-4 border-warning">
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <span class="text-muted small text-uppercase fw-bold">Verified WHT Slips</span>
                    <span class="badge bg-warning-subtle text-warning rounded-pill"><i class="fa-solid fa-paperclip"></i></span>
                </div>
                <div class="d-flex align-items-baseline gap-2">
                    <span class="fs-4 fw-bold text-success">{{ $slipsAttachedCount }}</span>
                    <span class="text-muted small">/ {{ $totalWhtTransactions }} Verified</span>
                </div>
                <div class="text-muted small" style="font-size:0.75rem;">የተያያዙ እና የተረጋገጡ ደረሰኞች</div>
            </div>
        </div>
    </div>

    @if($tab === 'settlements')
        {{-- ── Settlements History Table ───────────────────────────────────────── --}}
        <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4 bg-white">
            <div class="card-header bg-white border-bottom py-3 px-4 d-flex align-items-center justify-content-between">
                <div class="d-flex align-items-center gap-2">
                    <div class="p-2 rounded-3 bg-danger bg-opacity-10 text-danger">
                        <i class="fa-solid fa-file-invoice-dollar"></i>
                    </div>
                    <div>
                        <h6 class="fw-bold mb-0 text-dark">Tax Remittance &amp; Payment History (የተከፈሉ ታክሶች ታሪክ)</h6>
                        <small class="text-muted">History of VAT &amp; Withholding Tax batches remitted to ERCA with proof of payment slips.</small>
                    </div>
                </div>
                <button type="button" class="btn btn-danger btn-sm rounded-pill px-3 shadow-xs fw-semibold" data-bs-toggle="modal" data-bs-target="#payTaxModal">
                    <i class="fa-solid fa-plus me-1"></i> New Tax Remittance
                </button>
            </div>

            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light small text-uppercase fw-bold">
                        <tr>
                            <th class="ps-4">Settlement #</th>
                            <th>Date / Period</th>
                            <th>Tax Type</th>
                            <th class="text-end">Base Amount</th>
                            <th class="text-end text-info">VAT Paid</th>
                            <th class="text-end text-danger">WHT Paid</th>
                            <th class="text-end text-dark fw-bold">Total Paid (ETB)</th>
                            <th>Assigned Staff</th>
                            <th>Paying Account</th>
                            <th>Receipt Slip</th>
                            <th>Status</th>
                            <th class="text-center pe-4">Actions</th>
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
                                    <div>{{ optional($st->created_at)->format('d M Y, h:i A') }}</div>
                                    @if($st->paid_at)
                                        <small class="text-success"><i class="fa-solid fa-check me-1"></i>Paid: {{ $st->paid_at->format('d M Y') }}</small>
                                    @endif
                                </td>
                                <td>
                                    @if($st->tax_type === 'both')
                                        <span class="badge bg-primary-subtle text-primary">VAT (15%) + WHT (3%)</span>
                                    @elseif($st->tax_type === 'vat')
                                        <span class="badge bg-info-subtle text-info">VAT (15%) Only</span>
                                    @else
                                        <span class="badge bg-danger-subtle text-danger">3% Withholding Only</span>
                                    @endif
                                </td>
                                <td class="text-end font-monospace small">ETB {{ number_format($st->total_base_amount, 2) }}</td>
                                <td class="text-end font-monospace text-info small">+ ETB {{ number_format($st->vat_amount, 2) }}</td>
                                <td class="text-end font-monospace text-danger small">- ETB {{ number_format($st->withholding_amount, 2) }}</td>
                                <td class="text-end font-monospace fw-bold text-dark">ETB {{ number_format($st->total_tax_paid, 2) }}</td>
                                <td>
                                    <div class="fw-semibold text-dark">{{ $st->assignedStaff?->name ?? 'Unassigned' }}</div>
                                    @if($st->financeHead)
                                        <small class="text-muted" style="font-size: 0.72rem;">By: {{ $st->financeHead->name }}</small>
                                    @endif
                                </td>
                                <td>
                                    <div class="small fw-semibold">{{ $st->chartOfAccount->name ?? ($st->bankAccount->bank_name ?? 'Bank/COA') }}</div>
                                    @if($st->payment_reference)
                                        <small class="text-muted font-monospace" style="font-size:0.7rem;">Ref: {{ $st->payment_reference }}</small>
                                    @endif
                                </td>
                                <td>
                                    @if($st->attachment)
                                        <a href="{{ asset($st->attachment) }}" target="_blank" class="btn btn-xs btn-outline-success rounded-pill px-2 py-0" style="font-size:0.75rem;">
                                            <i class="fa-solid fa-paperclip me-1"></i> Bank Receipt
                                        </a>
                                    @else
                                        <span class="badge bg-light text-muted border">No slip</span>
                                    @endif
                                </td>
                                <td>
                                    @if($st->status === 'paid')
                                        <span class="badge bg-success rounded-pill px-2.5 py-1"><i class="fa-solid fa-check me-1"></i>Paid to ERCA</span>
                                    @elseif($st->status === 'pending_payment')
                                        <span class="badge bg-warning text-dark rounded-pill px-2.5 py-1"><i class="fa-solid fa-clock me-1"></i>Pending Payment</span>
                                    @else
                                        <span class="badge bg-secondary rounded-pill px-2.5 py-1">{{ ucfirst($st->status) }}</span>
                                    @endif
                                </td>
                                <td class="text-center pe-4">
                                    @if($st->status === 'pending_payment')
                                        <button type="button" class="btn btn-sm btn-success rounded-pill px-3 shadow-xs" 
                                                data-bs-toggle="modal" data-bs-target="#recordPaymentModal{{ $st->id }}">
                                            <i class="fa-solid fa-file-invoice-dollar me-1"></i> Pay
                                        </button>
                                    @else
                                        <span class="text-muted small"><i class="fa-solid fa-circle-check text-success"></i> Settled</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="12" class="text-center py-5">
                                    <div class="text-muted fs-5 mb-2"><i class="fa-solid fa-folder-open text-secondary fs-2"></i></div>
                                    <div class="fw-bold text-dark">No Tax Remittance Batches Recorded Yet</div>
                                    <p class="text-muted small mb-3">When you click "Pay VAT &amp; Withhold", settlements will be logged here with complete ERCA proof slips.</p>
                                    <button type="button" class="btn btn-danger btn-sm rounded-pill px-3 shadow-sm" data-bs-toggle="modal" data-bs-target="#payTaxModal">
                                        <i class="fa-solid fa-money-bill-transfer me-1"></i> Pay VAT &amp; Withhold Now
                                    </button>
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
            <div class="card-header bg-white border-bottom py-3 px-4">
                <ul class="nav nav-pills card-header-pills gap-2 flex-wrap">
                    <li class="nav-item">
                        <a class="nav-link rounded-pill px-3 py-1 fw-semibold {{ $tab === 'all' || $tab === 'paid' ? 'active bg-primary text-white shadow-sm' : 'text-secondary' }}" 
                           href="{{ route('finance.tax-deductions.index', array_merge(request()->query(), ['tab' => 'all'])) }}">
                            <i class="fa-solid fa-receipt me-1"></i> All Tax Items ({{ $totalRecords }})
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link rounded-pill px-3 py-1 fw-semibold {{ $tab === 'withholding' ? 'active bg-danger text-white shadow-sm' : 'text-secondary' }}" 
                           href="{{ route('finance.tax-deductions.index', array_merge(request()->query(), ['tab' => 'withholding'])) }}">
                            <i class="fa-solid fa-scissors me-1"></i> 3% Withholding Tax ({{ $totalWhtTransactions }})
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link rounded-pill px-3 py-1 fw-semibold {{ $tab === 'vat' ? 'active bg-info text-white shadow-sm' : 'text-secondary' }}" 
                           href="{{ route('finance.tax-deductions.index', array_merge(request()->query(), ['tab' => 'vat'])) }}">
                            <i class="fa-solid fa-percent me-1"></i> VAT Applied
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link rounded-pill px-3 py-1 fw-semibold {{ $tab === 'slips' ? 'active bg-success text-white shadow-sm' : 'text-secondary' }}" 
                           href="{{ route('finance.tax-deductions.index', array_merge(request()->query(), ['tab' => 'slips'])) }}">
                            <i class="fa-solid fa-file-circle-check me-1"></i> WHT Slips Attached ({{ $slipsAttachedCount }})
                        </a>
                    </li>
                </ul>
            </div>

            {{-- Filter Inputs --}}
            <div class="card-body p-3 p-md-4 bg-light-subtle border-bottom">
                <form method="GET" action="{{ route('finance.tax-deductions.index') }}" class="row g-2 align-items-center">
                    <input type="hidden" name="tab" value="{{ $tab }}">
                    <input type="hidden" name="cycle" value="{{ $cycle }}">

                    <div class="col-12 col-md-3">
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-white border-end-0"><i class="fa-solid fa-magnifying-glass text-muted"></i></span>
                            <input type="text" name="search" class="form-control form-control-sm border-start-0" 
                                   placeholder="Search Ref, Voucher, Requester..." value="{{ request('search') }}">
                        </div>
                    </div>

                    <div class="col-6 col-md-2">
                        <select name="category" class="form-select form-select-sm" onchange="this.form.submit()">
                            <option value="">All Categories</option>
                            <option value="Service" {{ request('category') === 'Service' ? 'selected' : '' }}>🤝 Service (አገልግሎት)</option>
                            <option value="Contract Work" {{ request('category') === 'Contract Work' ? 'selected' : '' }}>📝 Contract Work (የኮንትራት ስራ)</option>
                            <option value="Transport" {{ request('category') === 'Transport' ? 'selected' : '' }}>🚚 Transport (ትራንስፖርት)</option>
                            <option value="Loading & Unloading" {{ request('category') === 'Loading & Unloading' ? 'selected' : '' }}>📦 Loading &amp; Unloading</option>
                            <option value="Maintenance" {{ request('category') === 'Maintenance' ? 'selected' : '' }}>🔧 Maintenance</option>
                            <option value="Other" {{ request('category') === 'Other' ? 'selected' : '' }}>✨ Other</option>
                        </select>
                    </div>

                    <div class="col-6 col-md-2">
                        <select name="vat_type" class="form-select form-select-sm" onchange="this.form.submit()">
                            <option value="">All VAT Types</option>
                            <option value="exclusive" {{ request('vat_type') === 'exclusive' ? 'selected' : '' }}>15% Added (+15% ቫት)</option>
                            <option value="vat_b" {{ request('vat_type') === 'vat_b' ? 'selected' : '' }}>15% Included (VAT B)</option>
                            <option value="none" {{ request('vat_type') === 'none' ? 'selected' : '' }}>No VAT (0%)</option>
                        </select>
                    </div>

                    <div class="col-6 col-md-2">
                        <input type="date" name="from_date" class="form-control form-control-sm" value="{{ request('from_date') }}" title="From Date">
                    </div>

                    <div class="col-6 col-md-2">
                        <input type="date" name="to_date" class="form-control form-control-sm" value="{{ request('to_date') }}" title="To Date">
                    </div>

                    <div class="col-12 col-md-1 d-flex gap-1">
                        <button type="submit" class="btn btn-primary btn-sm flex-fill" title="Apply Filter">
                            <i class="fa-solid fa-filter"></i>
                        </button>
                        @if(request('search') || request('category') || request('vat_type') || request('from_date') || request('to_date'))
                            <a href="{{ route('finance.tax-deductions.index', ['tab' => $tab, 'cycle' => $cycle]) }}" class="btn btn-outline-danger btn-sm" title="Clear Filters">
                                <i class="fa-solid fa-xmark"></i>
                            </a>
                        @endif
                    </div>
                </form>
            </div>

            {{-- Tax Deductions Table --}}
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light small text-uppercase fw-bold">
                        <tr>
                            <th class="ps-4">Voucher / Ref #</th>
                            <th>Requester / Project</th>
                            <th>Category</th>
                            <th class="text-end">Base Invoiced (ETB)</th>
                            <th class="text-end">VAT (15% / VAT B)</th>
                            <th class="text-end text-danger">3% Withholding Tax</th>
                            <th class="text-end text-success">Net Paid (ETB)</th>
                            <th>Paying Account</th>
                            <th class="text-center">WHT Slip</th>
                            <th>Cycle Status</th>
                            <th class="text-center pe-4">Actions</th>
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
                                        <span class="badge bg-light text-dark border font-monospace" style="font-size: 0.7rem;">
                                            Ref: {{ $item->payment_reference }}
                                        </span>
                                    @endif
                                </td>

                                {{-- Requester / Project --}}
                                <td>
                                    <div class="fw-semibold text-dark">{{ $item->user->name ?? ($item->employee->full_name ?? 'N/A') }}</div>
                                    @if($item->project_id && $item->project)
                                        <span class="badge bg-light text-secondary border fw-normal" style="font-size: 0.7rem;">
                                            <i class="fa-solid fa-building me-1"></i>{{ $item->project->name }}
                                        </span>
                                    @elseif($item->purchaseRequest)
                                        <span class="badge bg-light text-secondary border fw-normal" style="font-size: 0.7rem;">
                                            <i class="fa-solid fa-cart-shopping me-1"></i>PR #{{ $item->purchaseRequest->pr_number ?? $item->purchaseRequest->id }}
                                        </span>
                                    @elseif($item->creditStoreLedger)
                                        <span class="badge bg-light text-secondary border fw-normal" style="font-size: 0.7rem;">
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
                                    <span class="badge bg-{{ $catColor }}-subtle text-{{ $catColor }} border border-{{ $catColor }}-subtle">
                                        {{ $item->category }}
                                    </span>
                                </td>

                                {{-- Base Invoiced --}}
                                <td class="text-end font-monospace fw-semibold">
                                    ETB {{ number_format($gross, 2) }}
                                </td>

                                {{-- VAT --}}
                                <td class="text-end font-monospace">
                                    @if((float)($item->vat_amount ?? 0) > 0)
                                        <span class="text-info fw-bold">+ ETB {{ number_format((float)$item->vat_amount, 2) }}</span>
                                        <div class="text-muted small" style="font-size:0.7rem;">
                                            {{ $item->vat_type === 'vat_b' ? 'VAT B (15% Incl.)' : '15% Added' }}
                                        </div>
                                    @else
                                        <span class="text-muted small">—</span>
                                    @endif
                                </td>

                                {{-- Withholding Tax (3%) --}}
                                <td class="text-end font-monospace">
                                    @if($hasWht && $wht > 0)
                                        <span class="text-danger fw-bold">- ETB {{ number_format($wht, 2) }}</span>
                                        <div class="text-muted small" style="font-size:0.7rem;">3% WHT Deducted</div>
                                    @else
                                        <span class="text-muted small">—</span>
                                    @endif
                                </td>

                                {{-- Net Disbursed --}}
                                <td class="text-end font-monospace fw-bold text-success">
                                    ETB {{ number_format($net, 2) }}
                                </td>

                                {{-- Paying Account --}}
                                <td>
                                    <div class="small fw-semibold text-dark">
                                        {{ $item->chartOfAccount->name ?? ($item->bankAccount->bank_name ?? 'Petty Cash') }}
                                    </div>
                                    @if($item->paidBy)
                                        <small class="text-muted" style="font-size: 0.72rem;">Paid by: {{ $item->paidBy->name }}</small>
                                    @endif
                                </td>

                                {{-- WHT Slip Status --}}
                                <td class="text-center">
                                    @if(!empty($item->withholding_receipt))
                                        <a href="{{ $item->withholding_receipt_url }}" target="_blank" class="btn btn-xs btn-outline-danger rounded-pill px-2 py-0" style="font-size:0.75rem;" title="View Attached 3% WHT Receipt Slip">
                                            <i class="fa-solid fa-paperclip me-1"></i> Slip Uploaded
                                        </a>
                                    @elseif($hasWht)
                                        <span class="badge bg-danger-subtle text-danger border border-danger-subtle" style="font-size:0.7rem;" title="Withholding applied but slip not yet attached">
                                            <i class="fa-solid fa-triangle-exclamation me-1"></i> Missing Slip
                                        </span>
                                    @else
                                        <span class="text-muted small">—</span>
                                    @endif
                                </td>

                                {{-- Cycle / Settle Status --}}
                                <td>
                                    @if($isSettled)
                                        <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill">
                                            <i class="fa-solid fa-check me-1"></i> Settled (Paid ERCA)
                                        </span>
                                    @elseif($item->tax_settlement_id)
                                        <span class="badge bg-warning-subtle text-warning border border-warning-subtle rounded-pill">
                                            <i class="fa-solid fa-hourglass-half me-1"></i> Remittance Pending
                                        </span>
                                    @else
                                        <span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill">
                                            <i class="fa-solid fa-clock me-1"></i> Active (Unsettled)
                                        </span>
                                    @endif
                                </td>

                                {{-- Actions --}}
                                <td class="text-center pe-4">
                                    <button type="button" class="btn btn-outline-secondary btn-sm rounded-pill px-2.5 shadow-xs" 
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
                                            <div class="p-3 bg-success bg-opacity-10 text-success rounded-circle d-inline-block fs-2 mb-3">
                                                <i class="fa-solid fa-circle-check"></i>
                                            </div>
                                            <h5 class="fw-bold text-dark">Starting from Zero: All Taxes Are Clean &amp; Settled!</h5>
                                            <p class="text-muted small mx-auto" style="max-width: 480px;">
                                                All VAT and 3% Withholding Tax deductions for previous purchases have been remitted to ERCA. 
                                                The current active cycle starts from zero (ETB 0.00). Any new taxable payments made will accumulate here.
                                            </p>
                                            <div class="d-flex justify-content-center gap-2 mt-3">
                                                <a href="{{ route('finance.tax-deductions.index', ['cycle' => 'all']) }}" class="btn btn-outline-primary btn-sm rounded-pill px-3">
                                                    <i class="fa-solid fa-layer-group me-1"></i> View All-Time Tax Ledger
                                                </a>
                                                <a href="{{ route('finance.tax-deductions.index', ['tab' => 'settlements']) }}" class="btn btn-outline-danger btn-sm rounded-pill px-3">
                                                    <i class="fa-solid fa-receipt me-1"></i> View Past Tax Remittances
                                                </a>
                                            </div>
                                        </div>
                                    @else
                                        <div class="text-muted py-4">
                                            <i class="fa-solid fa-receipt fs-2 mb-2 d-block text-secondary"></i>
                                            No tax deduction records found matching your filters.
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
        </div>
    @endif

</div>

{{-- ── MODAL: Pay VAT & Withholding (Finance Head Assigns or Pays) ───────────── --}}
<div class="modal fade" id="payTaxModal" tabindex="-1" aria-labelledby="payTaxModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <div class="modal-header bg-danger text-white py-3 px-4">
                <div class="d-flex align-items-center gap-2">
                    <i class="fa-solid fa-money-bill-transfer fs-5"></i>
                    <div>
                        <h5 class="modal-title fw-bold mb-0" id="payTaxModalLabel">Pay VAT &amp; Withholding Tax</h5>
                        <small class="text-white-50">Settle taxes with ERCA / Bank and start active cycle from zero (0.00)</small>
                    </div>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>

            <form method="POST" action="{{ route('finance.tax-deductions.settle') }}" enctype="multipart/form-data">
                @csrf

                <div class="modal-body p-4">
                    {{-- Summary of Unsettled Amounts --}}
                    <div class="p-3 bg-light rounded-4 border mb-4">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="small fw-bold text-uppercase text-muted">Currently Unsettled Tax Liabilities</span>
                            <span class="badge bg-danger rounded-pill">{{ $unsettledCount }} Unpaid Records</span>
                        </div>
                        <div class="row g-2 text-center">
                            <div class="col-4">
                                <div class="bg-white p-2 rounded-3 border">
                                    <div class="text-muted small" style="font-size:0.75rem;">15% VAT Accrued</div>
                                    <div class="fw-bold text-info font-monospace fs-6">+ ETB {{ number_format($unsettledVatAmount, 2) }}</div>
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="bg-white p-2 rounded-3 border">
                                    <div class="text-muted small" style="font-size:0.75rem;">3% WHT Deducted</div>
                                    <div class="fw-bold text-danger font-monospace fs-6">- ETB {{ number_format($unsettledWhtAmount, 2) }}</div>
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="bg-white p-2 rounded-3 border border-danger">
                                    <div class="text-muted small" style="font-size:0.75rem;">Total Tax to Pay</div>
                                    <div class="fw-bold text-danger font-monospace fs-6">ETB {{ number_format($unsettledTotalTax, 2) }}</div>
                                </div>
                            </div>
                        </div>
                        <div class="text-muted small mt-2" style="font-size: 0.78rem;">
                            <i class="fa-solid fa-circle-info text-primary me-1"></i>
                            Once paid or assigned, all these records will be stamped as settled and your active compliance ledger will <strong>start from zero (ETB 0.00)</strong>.
                        </div>
                    </div>

                    {{-- Form Fields --}}
                    <div class="row g-3">
                        {{-- Tax Type to Pay --}}
                        <div class="col-12 col-md-6">
                            <label class="form-label small fw-bold text-dark">Select Taxes to Pay <span class="text-danger">*</span></label>
                            <select name="tax_type" id="settleTaxType" class="form-select" required>
                                <option value="both" selected>Both VAT (15%) + 3% Withholding Tax (ETB {{ number_format($unsettledTotalTax, 2) }})</option>
                                <option value="vat">15% VAT Only (ETB {{ number_format($unsettledVatAmount, 2) }})</option>
                                <option value="withholding">3% Withholding Tax Only (ETB {{ number_format($unsettledWhtAmount, 2) }})</option>
                            </select>
                        </div>

                        {{-- Paying Bank / COA Account --}}
                        <div class="col-12 col-md-6">
                            <label class="form-label small fw-bold text-dark">Disbursement Account (Bank / COA) <span class="text-danger">*</span></label>
                            <select name="account_source" id="settleAccountSource" class="form-select" required>
                                <option value="">-- Choose Funding Account --</option>
                                <optgroup label="Bank Accounts (Commercial Banks)">
                                    @foreach($bankAccounts as $ba)
                                        <option value="bank:{{ $ba->id }}">
                                            {{ $ba->bank_name }} - {{ $ba->account_number }} (Bal: ETB {{ number_format($ba->current_balance, 2) }})
                                        </option>
                                    @endforeach
                                </optgroup>
                                <optgroup label="Chart of Accounts (Cash &amp; Equivalents)">
                                    @foreach($chartOfAccounts as $coa)
                                        <option value="coa:{{ $coa->id }}">
                                            {{ $coa->code }} - {{ $coa->name }} ({{ ucfirst($coa->subtype ?? $coa->type) }})
                                        </option>
                                    @endforeach
                                </optgroup>
                            </select>
                        </div>

                        {{-- Assign Finance Staff --}}
                        <div class="col-12">
                            <label class="form-label small fw-bold text-dark">
                                <i class="fa-solid fa-user-check text-primary me-1"></i> Assign Finance Staff to Process &amp; Pay <span class="text-danger">*</span>
                            </label>
                            <select name="assigned_finance_staff_id" id="settleStaffSelect" class="form-select" required>
                                <option value="">-- Choose Finance Staff --</option>
                                @foreach($financeStaff as $staff)
                                    <option value="{{ $staff->id }}" {{ Auth::id() == $staff->id ? 'selected' : '' }}>
                                        {{ $staff->name }} ({{ $staff->email }})
                                    </option>
                                @endforeach
                            </select>
                            <small class="text-muted d-block mt-1">
                                The Finance Head assigns this tax disbursement task to the selected Finance Staff. The staff member will execute the payment with ERCA / Bank and upload the payment proof slip.
                            </small>
                        </div>

                        {{-- Instant Payment Toggle --}}
                        <div class="col-12">
                            <div class="form-check form-switch p-3 bg-light rounded-3 border">
                                <input class="form-check-input ms-0 me-2" type="checkbox" name="pay_now" value="1" id="payNowCheckbox" onchange="togglePayNowFields(this.checked)">
                                <label class="form-check-label fw-bold text-dark" for="payNowCheckbox">
                                    Record Payment Now (I have already executed the tax payment and have the bank/ERCA receipt)
                                </label>
                                <div class="text-muted small ps-4">Check this if the payment was already completed at the bank or ERCA and you want to enter the receipt reference and upload the slip immediately.</div>
                            </div>
                        </div>

                        {{-- Expanded Instant Payment Fields --}}
                        <div id="payNowFields" class="row g-3 mt-1" style="display: none;">
                            <div class="col-12 col-md-6">
                                <label class="form-label small fw-bold text-dark">Payment Reference / ERCA Receipt # <span class="text-danger">*</span></label>
                                <input type="text" name="payment_reference" id="settlePaymentRef" class="form-control" placeholder="e.g. ETAX-2026-98124 or Bank Slip #">
                            </div>
                            <div class="col-12 col-md-6">
                                <label class="form-label small fw-bold text-dark">Payment Date <span class="text-danger">*</span></label>
                                <input type="date" name="payment_date" class="form-control" value="{{ date('Y-m-d') }}">
                            </div>
                            <div class="col-12">
                                <label class="form-label small fw-bold text-dark">Upload Bank Deposit Slip / ERCA Tax Receipt</label>
                                <input type="file" name="attachment" class="form-control" accept=".pdf,.jpg,.jpeg,.png">
                                <small class="text-muted">Attach stamped bank voucher, ERCA e-tax clearance slip, or deposit receipt (PDF/Image max 10MB).</small>
                            </div>
                            <div class="col-12">
                                <label class="form-label small fw-bold text-dark">Payment Notes</label>
                                <textarea name="payment_notes" class="form-control" rows="2" placeholder="Optional notes regarding this tax settlement..."></textarea>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="modal-footer bg-light border-top py-3 px-4 d-flex justify-content-between">
                    <button type="button" class="btn btn-outline-secondary rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger rounded-pill px-4 shadow-sm fw-semibold">
                        <i class="fa-solid fa-circle-check me-1"></i> Confirm &amp; Start from Zero
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
            <div class="modal-dialog modal-dialog-centered modal-lg">
                <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
                    <div class="modal-header bg-success text-white py-3 px-4">
                        <div class="d-flex align-items-center gap-2">
                            <i class="fa-solid fa-file-invoice-dollar fs-5"></i>
                            <div>
                                <h5 class="modal-title fw-bold mb-0">Record Tax Payment: {{ $ps->settlement_number }}</h5>
                                <small class="text-white-50">Upload bank slip &amp; ERCA receipt to settle taxes and reset ledger to zero</small>
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
                                    <div class="col-4">
                                        <div class="bg-white p-2 rounded-3 border">
                                            <div class="text-muted small" style="font-size:0.75rem;">VAT to Remit</div>
                                            <div class="fw-bold text-info font-monospace fs-6">ETB {{ number_format($ps->vat_amount, 2) }}</div>
                                        </div>
                                    </div>
                                    <div class="col-4">
                                        <div class="bg-white p-2 rounded-3 border">
                                            <div class="text-muted small" style="font-size:0.75rem;">3% WHT to Remit</div>
                                            <div class="fw-bold text-danger font-monospace fs-6">ETB {{ number_format($ps->withholding_amount, 2) }}</div>
                                        </div>
                                    </div>
                                    <div class="col-4">
                                        <div class="bg-white p-2 rounded-3 border border-success">
                                            <div class="text-muted small" style="font-size:0.75rem;">Total Tax Amount Paid</div>
                                            <div class="fw-bold text-success font-monospace fs-6">ETB {{ number_format($ps->total_tax_paid, 2) }}</div>
                                        </div>
                                    </div>
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
                        <span class="badge bg-primary rounded-pill font-monospace">{{ $item->request_number }}</span>
                        <h6 class="modal-title fw-bold text-dark mb-0">Tax Deduction Voucher Details</h6>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    {{-- Tax Figures Grid --}}
                    <div class="row g-2 mb-3 text-center">
                        <div class="col-6 col-md-3">
                            <div class="p-2 bg-light rounded-3 border">
                                <div class="small text-muted" style="font-size:0.75rem;">Base Amount</div>
                                <div class="fw-bold font-monospace text-dark">ETB {{ number_format($gross, 2) }}</div>
                            </div>
                        </div>
                        <div class="col-6 col-md-3">
                            <div class="p-2 bg-light rounded-3 border">
                                <div class="small text-muted" style="font-size:0.75rem;">VAT Rate / Amount</div>
                                <div class="fw-bold font-monospace text-info">+ ETB {{ number_format((float)($item->vat_amount ?? 0), 2) }}</div>
                            </div>
                        </div>
                        <div class="col-6 col-md-3">
                            <div class="p-2 bg-light rounded-3 border">
                                <div class="small text-muted" style="font-size:0.75rem;">3% Withholding Tax</div>
                                <div class="fw-bold font-monospace text-danger">- ETB {{ number_format($wht, 2) }}</div>
                            </div>
                        </div>
                        <div class="col-6 col-md-3">
                            <div class="p-2 bg-light rounded-3 border border-success">
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

<script>
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
