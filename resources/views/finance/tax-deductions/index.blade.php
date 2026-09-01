@extends('layouts.app')

@section('title', 'VAT & Withholding Tax Deductions Ledger')

@section('content')
<div class="container-fluid px-3 px-md-4 py-4">

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
                    <span class="fs-4 fw-bold text-success">{{ $totalRecords }}</span>
                    <span class="text-muted small">Uploaded &amp; Verified</span>
                </div>
                <div class="text-muted small" style="font-size:0.75rem;">የተያያዙ እና የተረጋገጡ ደረሰኞች</div>
            </div>
        </div>
    </div>

    {{-- Filter Bar & Navigation Tabs --}}
    <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4 bg-white">
        <div class="card-header bg-white border-bottom py-3 px-4">
            <ul class="nav nav-pills card-header-pills gap-2 flex-wrap">
                <li class="nav-item">
                    <a class="nav-link rounded-pill px-3 py-1 fw-semibold {{ $tab === 'all' ? 'active bg-primary text-white shadow-sm' : 'text-secondary' }}" 
                       href="{{ route('finance.tax-deductions.index', array_merge(request()->query(), ['tab' => 'all'])) }}">
                        <i class="fa-solid fa-receipt me-1"></i> All Verified Slips ({{ $totalRecords }})
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link rounded-pill px-3 py-1 fw-semibold {{ $tab === 'withholding' ? 'active bg-danger text-white shadow-sm' : 'text-secondary' }}" 
                       href="{{ route('finance.tax-deductions.index', array_merge(request()->query(), ['tab' => 'withholding'])) }}">
                        <i class="fa-solid fa-scissors me-1"></i> 3% Withholding Tax
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link rounded-pill px-3 py-1 fw-semibold {{ $tab === 'vat' ? 'active bg-info text-white shadow-sm' : 'text-secondary' }}" 
                       href="{{ route('finance.tax-deductions.index', array_merge(request()->query(), ['tab' => 'vat'])) }}">
                        <i class="fa-solid fa-percent me-1"></i> VAT Applied
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link rounded-pill px-3 py-1 fw-semibold {{ $tab === 'paid' ? 'active bg-success text-white shadow-sm' : 'text-secondary' }}" 
                       href="{{ route('finance.tax-deductions.index', array_merge(request()->query(), ['tab' => 'paid'])) }}">
                        <i class="fa-solid fa-check-circle me-1"></i> Paid &amp; Completed
                    </a>
                </li>
            </ul>
        </div>


        {{-- Filter Inputs --}}
        <div class="card-body p-3 p-md-4 bg-light-subtle border-bottom">
            <form method="GET" action="{{ route('finance.tax-deductions.index') }}" class="row g-2 align-items-center">
                <input type="hidden" name="tab" value="{{ $tab }}">

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
                        <a href="{{ route('finance.tax-deductions.index', ['tab' => $tab]) }}" class="btn btn-outline-danger btn-sm" title="Clear Filters">
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
                        <th>Status</th>
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

                            {{-- Requester --}}
                            <td>
                                <div class="fw-semibold text-dark">{{ $item->user->name ?? 'N/A' }}</div>
                                <div class="small text-muted">{{ $item->user->email ?? '' }}</div>
                            </td>

                            {{-- Category --}}
                            <td>
                                @if($item->category === 'Service')
                                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-2 py-1">
                                        🤝 Service (አገልግሎት)
                                    </span>
                                @elseif($item->category === 'Contract Work')
                                    <span class="badge bg-purple-subtle text-purple border border-purple-subtle px-2 py-1" style="background:#f3e8ff; color:#7e22ce;">
                                        📝 Contract Work (ኮንትራት)
                                    </span>
                                @elseif($item->category === 'Transport')
                                    <span class="badge bg-info-subtle text-info border border-info-subtle px-2 py-1">
                                        🚚 Transport
                                    </span>
                                @else
                                    <span class="badge bg-secondary-subtle text-secondary border px-2 py-1">
                                        {{ $item->category }}
                                    </span>
                                @endif
                            </td>

                            {{-- Base Invoiced --}}
                            <td class="text-end fw-bold text-dark">
                                ETB {{ number_format($gross, 2) }}
                            </td>

                            {{-- VAT --}}
                            <td class="text-end">
                                @if($item->vat_amount > 0)
                                    <div class="fw-bold text-info">+ ETB {{ number_format($item->vat_amount, 2) }}</div>
                                    <span class="badge bg-info-subtle text-info" style="font-size:0.65rem;">
                                        {{ $item->vat_type === 'vat_b' ? 'VAT B (15% Incl.)' : '+15% VAT Added' }}
                                    </span>
                                @else
                                    <span class="text-muted small">0.00</span>
                                @endif
                            </td>

                            {{-- 3% Withholding Tax --}}
                            <td class="text-end">
                                @if($wht > 0)
                                    <div class="fw-bold text-danger">- ETB {{ number_format($wht, 2) }}</div>
                                    <span class="badge bg-danger-subtle text-danger" style="font-size:0.65rem;">
                                        3% WHT Deducted
                                    </span>
                                @elseif($hasWht)
                                    <span class="badge bg-warning-subtle text-warning" style="font-size:0.65rem;">3% Active (Pending)</span>
                                @else
                                    <span class="text-muted small">None</span>
                                @endif
                            </td>

                            {{-- Net Paid --}}
                            <td class="text-end">
                                <div class="fw-bold fs-6 text-success">ETB {{ number_format($net, 2) }}</div>
                            </td>

                            {{-- Paying Account --}}
                            <td>
                                <div class="small fw-semibold text-dark">
                                    {{ $item->chartOfAccount->name ?? ($item->bankAccount->bank_name ?? 'Default Petty Cash') }}
                                </div>
                                @if($item->chartOfAccount && $item->chartOfAccount->code)
                                    <span class="badge bg-light text-muted border" style="font-size:0.65rem;">
                                        COA: {{ $item->chartOfAccount->code }}
                                    </span>
                                @endif
                            </td>

                            {{-- Withholding Slip --}}
                            <td class="text-center">
                                @if(!empty($item->withholding_receipt))
                                    <a href="{{ $item->withholding_receipt_url }}" target="_blank" 
                                       class="btn btn-outline-danger btn-sm rounded-pill px-2 py-0 shadow-xs" 
                                       title="View 3% Withholding Tax Slip">
                                        <i class="fa-solid fa-file-pdf text-danger me-1"></i> Slip
                                    </a>
                                    @if(!empty($item->withholding_receipt_number))
                                        <div class="font-monospace text-muted" style="font-size:0.65rem;">#{{ $item->withholding_receipt_number }}</div>
                                    @endif
                                @elseif($hasWht)
                                    <span class="badge bg-danger-subtle text-danger border border-danger-subtle" title="Official Withholding receipt missing">
                                        <i class="fa-solid fa-triangle-exclamation me-1"></i> Missing
                                    </span>
                                @else
                                    <span class="text-muted small">&mdash;</span>
                                @endif
                            </td>

                            {{-- Status --}}
                            <td>
                                {!! $item->status_badge !!}
                            </td>

                            {{-- Actions --}}
                            <td class="text-center pe-4">
                                <button type="button" class="btn btn-outline-secondary btn-sm rounded-3 px-2 py-1 shadow-xs" 
                                        data-bs-toggle="modal" 
                                        data-bs-target="#taxDetailModal{{ $item->id }}"
                                        title="View Tax Breakdown Details">
                                    <i class="fa-solid fa-eye text-primary"></i>
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="11" class="text-center py-5 text-muted">
                                <i class="fa-solid fa-receipt fa-3x mb-3 text-secondary opacity-25"></i>
                                <h6 class="fw-bold text-dark">No Tax Deductions Found</h6>
                                <p class="small text-muted mb-0">No expense transactions matching the tax and category filter criteria.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($records->hasPages())
            <div class="card-footer bg-white border-top py-3 px-4">
                {{ $records->links() }}
            </div>
        @endif
    </div>

</div>

{{-- Detail Modals --}}
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
                <div class="modal-header bg-dark text-white py-3 px-4">
                    <div class="d-flex align-items-center gap-2">
                        <span class="badge bg-white bg-opacity-20 text-white p-2 rounded-3 fs-6">
                            <i class="fa-solid fa-receipt"></i>
                        </span>
                        <div>
                            <h5 class="modal-title fw-bold mb-0">Tax Deduction Breakdown &mdash; {{ $item->request_number }}</h5>
                            <span class="text-white-50 small">{{ $item->category }} &bull; Requester: {{ $item->user->name ?? 'N/A' }}</span>
                        </div>
                    </div>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4 bg-white">
                    {{-- Summary Box --}}
                    <div class="p-3 bg-light rounded-3 border mb-3">
                        <div class="row g-3 text-center">
                            <div class="col-3 border-end">
                                <span class="text-muted small d-block">Gross / Base Invoice</span>
                                <strong class="fs-6 text-dark">ETB {{ number_format($gross, 2) }}</strong>
                            </div>
                            <div class="col-3 border-end">
                                <span class="text-muted small d-block">15% VAT Amount</span>
                                <strong class="fs-6 text-info">+ ETB {{ number_format($item->vat_amount ?? 0, 2) }}</strong>
                            </div>
                            <div class="col-3 border-end">
                                <span class="text-muted small d-block">3% Withholding Tax</span>
                                <strong class="fs-6 text-danger">- ETB {{ number_format($wht, 2) }}</strong>
                            </div>
                            <div class="col-3">
                                <span class="text-muted small d-block">Net Payable / Paid</span>
                                <strong class="fs-5 text-success">ETB {{ number_format($net, 2) }}</strong>
                            </div>
                        </div>
                    </div>

                    {{-- Details List --}}
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
                                <span class="small text-muted text-uppercase fw-bold d-block mb-1">Payment &amp; Voucher Data</span>
                                <div class="small">
                                    <div><strong>Payment Ref:</strong> {{ $item->payment_reference ?? 'Pending' }}</div>
                                    <div><strong>Paid Date:</strong> {{ optional($item->paid_at)->format('d M Y, h:i A') ?? 'Pending' }}</div>
                                    <div><strong>Paid By:</strong> {{ $item->paidBy->name ?? 'Finance' }}</div>
                                    <div><strong>Status:</strong> {!! $item->status_badge !!}</div>
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

@endsection
