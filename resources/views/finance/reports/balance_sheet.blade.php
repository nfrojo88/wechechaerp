@extends('layouts.app')

@section('title', 'Balance Sheet (Financial Position) — Financial Reports')

@section('content')
@php
    $totalLiabilitiesAndEquity = $totalLiabilities + $totalEquity;
    $isBalanced = round($totalAssets, 2) === round($totalLiabilitiesAndEquity, 2);
    $difference = abs($totalAssets - $totalLiabilitiesAndEquity);
@endphp

<div class="container-fluid px-0">
    <x-print-header title="Balance Sheet (Statement of Financial Position)" :dateRange="'As of ' . \Carbon\Carbon::parse($asOfDate)->format('F j, Y')" />

    {{-- Page Header --}}
    <x-page-header
        title="Balance Sheet (Financial Position)"
        subtitle="Snapshot of enterprise assets, liabilities, and retained equity as of {{ \Carbon\Carbon::parse($asOfDate)->format('F j, Y') }}."
        :breadcrumbs="[
            ['label' => 'Dashboard', 'url' => route('dashboard')],
            ['label' => 'Finance', 'url' => route('dashboard.finance')],
            ['label' => 'Reports', 'url' => route('reports.index')],
            ['label' => 'Balance Sheet']
        ]">
        <x-slot:actions>
            <a href="{{ route('reports.index') }}" class="btn btn-outline-secondary btn-sm rounded-pill px-3 py-2 shadow-xs fw-semibold">
                <i class="fa-solid fa-arrow-left me-1.5"></i>Reports Hub
            </a>
            <x-export-menu :print="true" label="Print / Export" />
            <a href="{{ route('reports.cash-flow') }}" class="btn btn-primary btn-sm rounded-pill px-3 py-2 shadow-xs fw-semibold">
                <i class="fa-solid fa-water me-1.5"></i>Cash Flow
            </a>
        </x-slot:actions>
    </x-page-header>

    {{-- Filter Bar --}}
    <div class="card border-0 shadow-sm rounded-4 mb-4">
        <div class="card-body p-3">
            <form method="GET" action="{{ route('reports.balance-sheet') }}" class="row g-2 align-items-end">
                <div class="col-12 col-sm-6 col-md-4 col-lg-3">
                    <label class="form-label text-muted small fw-bold text-uppercase mb-1" style="font-size: 0.72rem;">As Of Date</label>
                    <input type="date" name="as_of_date" class="form-control form-control-sm" value="{{ $asOfDate }}" required>
                </div>
                <div class="col-12 col-sm-6 col-md-4 col-lg-3 d-flex gap-2">
                    <button type="submit" class="btn btn-primary btn-sm rounded-pill px-4 py-2 w-100 fw-semibold shadow-xs">
                        <i class="fa-solid fa-rotate me-1.5"></i>Update Position
                    </button>
                    <a href="{{ route('reports.balance-sheet') }}" class="btn btn-outline-secondary btn-sm rounded-pill px-3 py-2 shadow-xs" title="Reset to today">
                        <i class="fa-solid fa-rotate-left"></i>
                    </a>
                </div>
            </form>
        </div>
    </div>

    {{-- Out of Balance Alert --}}
    @if(!$isBalanced)
        <div class="alert alert-danger border-0 shadow-sm rounded-4 mb-4 d-flex align-items-center gap-3 p-3 bg-danger bg-opacity-10 border-start border-4 border-danger">
            <div class="p-2 bg-danger text-white rounded-circle fs-5 flex-shrink-0">
                <i class="fa-solid fa-triangle-exclamation"></i>
            </div>
            <div class="flex-grow-1 text-dark">
                <strong class="d-block text-danger">Balance Sheet Out of Balance!</strong>
                Total Assets do not match Total Liabilities &amp; Equity. Variance is 
                <span class="font-tabular fw-bold text-danger">ETB {{ number_format($difference, 2) }}</span>.
                Verify current year earnings closing or unmatched ledger mappings.
            </div>
            <a href="{{ route('reports.trial-balance') }}" class="btn btn-sm btn-danger rounded-pill px-3 fw-semibold">
                Inspect Trial Balance
            </a>
        </div>
    @endif

    {{-- Executive KPI Metrics --}}
    <div class="row g-3 mb-4">
        <div class="col-6 col-lg-3">
            <x-kpi-card
                title="Total Assets"
                :value="'ETB ' . number_format($totalAssets, 2)"
                :changeLabel="$assets->count() . ' asset accounts'"
                icon="fa-solid fa-building"
                color="success" />
        </div>
        <div class="col-6 col-lg-3">
            <x-kpi-card
                title="Total Liabilities"
                :value="'ETB ' . number_format($totalLiabilities, 2)"
                :changeLabel="$liabilities->count() . ' liability obligations'"
                icon="fa-solid fa-file-invoice-dollar"
                color="danger" />
        </div>
        <div class="col-6 col-lg-3">
            <x-kpi-card
                title="Total Equity"
                :value="'ETB ' . number_format($totalEquity, 2)"
                :changeLabel="'Includes ETB ' . number_format($netIncome, 2) . ' net income'"
                icon="fa-solid fa-vault"
                color="info" />
        </div>
        <div class="col-6 col-lg-3">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-body p-3 p-md-4 d-flex align-items-center gap-3">
                    <div class="p-3 rounded-4 {{ $isBalanced ? 'bg-success bg-opacity-10 text-success' : 'bg-danger bg-opacity-10 text-danger' }} fs-3 flex-shrink-0">
                        <i class="fa-solid {{ $isBalanced ? 'fa-scale-balanced' : 'fa-triangle-exclamation' }}"></i>
                    </div>
                    <div>
                        <div class="text-muted small text-uppercase fw-bold" style="font-size: 0.72rem; letter-spacing: 0.5px;">A = L + E Equation</div>
                        <div class="fs-5 fw-bold {{ $isBalanced ? 'text-success' : 'text-danger' }}">
                            {{ $isBalanced ? 'Balanced' : 'Unbalanced' }}
                        </div>
                        <span class="text-muted small">
                            {{ $isBalanced ? 'Assets equal Liabilities + Equity' : 'Variance: ETB ' . number_format($difference, 2) }}
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Balance Sheet Two-Column Layout --}}
    <div class="wc-printable-area mb-4">
        {{-- Formal Print Header (Only visible on print) --}}
        <div class="d-none d-print-block p-4 border-bottom text-center mb-4">
            <h2 class="fw-bold mb-1" style="color: #0F2944;">WECHECHA CONSTRUCTION ERP</h2>
            <h4 class="text-secondary fw-semibold mb-1">Official Balance Sheet Statement</h4>
            <p class="text-muted small mb-0">As of {{ \Carbon\Carbon::parse($asOfDate)->format('F j, Y') }} | Currency: ETB</p>
        </div>

        <div class="row g-4">
            {{-- ── LEFT COLUMN: ASSETS ── --}}
            <div class="col-12 col-lg-6">
                <div class="card border-0 shadow-sm rounded-4 h-100 overflow-hidden">
                    <div class="card-header bg-white py-3 px-4 border-bottom d-flex align-items-center justify-content-between">
                        <div class="d-flex align-items-center gap-2">
                            <span class="p-2 rounded-3 bg-success bg-opacity-10 text-success fs-6">
                                <i class="fa-solid fa-circle-check"></i>
                            </span>
                            <h6 class="fw-bold mb-0 text-success text-uppercase" style="letter-spacing: 0.5px;">1. Assets</h6>
                        </div>
                        <span class="badge bg-success bg-opacity-10 text-success rounded-pill px-2.5 py-1 small">
                            {{ $assets->count() }} Accounts
                        </span>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0 wc-table-mobile-cards">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-4 text-secondary text-uppercase small fw-bold" style="width: 100px;">Code</th>
                                    <th class="text-secondary text-uppercase small fw-bold">Account Name</th>
                                    <th class="text-end pe-4 text-secondary text-uppercase small fw-bold">Balance (ETB)</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($assets as $acc)
                                    <tr>
                                        <td class="ps-4 font-monospace fw-bold text-success" data-label="Code">
                                            {{ $acc->code }}
                                        </td>
                                        <td data-label="Account Name">
                                            <div class="fw-semibold text-dark">{{ $acc->name }}</div>
                                            @if(!empty($acc->subtype))
                                                <span class="text-muted small" style="font-size: 0.72rem;">{{ str_replace('_', ' ', ucfirst($acc->subtype)) }}</span>
                                            @endif
                                        </td>
                                        <td class="pe-4 text-end font-tabular fw-semibold text-dark" data-label="Balance (ETB)">
                                            {{ number_format($acc->computed_balance, 2) }}
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="3" class="text-center py-4 text-muted">
                                            No asset balances recorded.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                            <tfoot class="table-success bg-opacity-25 border-top border-2">
                                <tr class="fw-bold fs-6">
                                    <td colspan="2" class="ps-4 py-3 text-uppercase small text-dark align-middle">
                                        Total Assets
                                    </td>
                                    <td class="pe-4 py-3 text-end font-tabular text-success align-middle" style="border-bottom: 3px double #0F2944;">
                                        <span class="small text-muted me-1">ETB</span>{{ number_format($totalAssets, 2) }}
                                    </td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>

            {{-- ── RIGHT COLUMN: LIABILITIES & EQUITY ── --}}
            <div class="col-12 col-lg-6">
                <div class="card border-0 shadow-sm rounded-4 h-100 overflow-hidden d-flex flex-column">
                    {{-- Section 2A: Liabilities --}}
                    <div class="card-header bg-white py-3 px-4 border-bottom d-flex align-items-center justify-content-between">
                        <div class="d-flex align-items-center gap-2">
                            <span class="p-2 rounded-3 bg-danger bg-opacity-10 text-danger fs-6">
                                <i class="fa-solid fa-file-invoice-dollar"></i>
                            </span>
                            <h6 class="fw-bold mb-0 text-danger text-uppercase" style="letter-spacing: 0.5px;">2. Liabilities</h6>
                        </div>
                        <span class="badge bg-danger bg-opacity-10 text-danger rounded-pill px-2.5 py-1 small">
                            {{ $liabilities->count() }} Obligations
                        </span>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0 wc-table-mobile-cards">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-4 text-secondary text-uppercase small fw-bold" style="width: 100px;">Code</th>
                                    <th class="text-secondary text-uppercase small fw-bold">Account Name</th>
                                    <th class="text-end pe-4 text-secondary text-uppercase small fw-bold">Balance (ETB)</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($liabilities as $acc)
                                    <tr>
                                        <td class="ps-4 font-monospace fw-bold text-danger" data-label="Code">
                                            {{ $acc->code }}
                                        </td>
                                        <td data-label="Account Name">
                                            <div class="fw-semibold text-dark">{{ $acc->name }}</div>
                                            @if(!empty($acc->subtype))
                                                <span class="text-muted small" style="font-size: 0.72rem;">{{ str_replace('_', ' ', ucfirst($acc->subtype)) }}</span>
                                            @endif
                                        </td>
                                        <td class="pe-4 text-end font-tabular fw-semibold text-dark" data-label="Balance (ETB)">
                                            {{ number_format($acc->computed_balance, 2) }}
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="3" class="text-center py-3 text-muted">
                                            No liabilities recorded.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                            <tfoot class="table-danger bg-opacity-25 border-top border-2">
                                <tr class="fw-bold">
                                    <td colspan="2" class="ps-4 py-2 text-uppercase small text-dark align-middle">
                                        Total Liabilities
                                    </td>
                                    <td class="pe-4 py-2 text-end font-tabular text-danger align-middle">
                                        <span class="small text-muted me-1">ETB</span>{{ number_format($totalLiabilities, 2) }}
                                    </td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>

                    {{-- Section 2B: Equity --}}
                    <div class="card-header bg-white py-3 px-4 border-top border-bottom d-flex align-items-center justify-content-between mt-3">
                        <div class="d-flex align-items-center gap-2">
                            <span class="p-2 rounded-3 bg-info bg-opacity-10 text-info fs-6">
                                <i class="fa-solid fa-vault"></i>
                            </span>
                            <h6 class="fw-bold mb-0 text-primary text-uppercase" style="letter-spacing: 0.5px;">3. Equity &amp; Retained Earnings</h6>
                        </div>
                        <span class="badge bg-info bg-opacity-10 text-info rounded-pill px-2.5 py-1 small">
                            Capital
                        </span>
                    </div>
                    <div class="table-responsive flex-grow-1">
                        <table class="table table-hover align-middle mb-0 wc-table-mobile-cards">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-4 text-secondary text-uppercase small fw-bold" style="width: 100px;">Code</th>
                                    <th class="text-secondary text-uppercase small fw-bold">Account Name</th>
                                    <th class="text-end pe-4 text-secondary text-uppercase small fw-bold">Balance (ETB)</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($equity as $acc)
                                    <tr>
                                        <td class="ps-4 font-monospace fw-bold text-info" data-label="Code">
                                            {{ $acc->code }}
                                        </td>
                                        <td data-label="Account Name">
                                            <div class="fw-semibold text-dark">{{ $acc->name }}</div>
                                            @if(!empty($acc->subtype))
                                                <span class="text-muted small" style="font-size: 0.72rem;">{{ str_replace('_', ' ', ucfirst($acc->subtype)) }}</span>
                                            @endif
                                        </td>
                                        <td class="pe-4 text-end font-tabular fw-semibold text-dark" data-label="Balance (ETB)">
                                            {{ number_format($acc->computed_balance, 2) }}
                                        </td>
                                    </tr>
                                @empty
                                @endforelse
                                
                                {{-- Computed Current Year Net Income --}}
                                <tr class="bg-light">
                                    <td class="ps-4 font-monospace fw-bold text-primary" data-label="Code">
                                        <span class="badge bg-primary bg-opacity-10 text-primary rounded-pill">AUTO</span>
                                    </td>
                                    <td data-label="Account Name">
                                        <div class="fw-semibold text-dark">Current Period Net Income (Retained)</div>
                                        <span class="text-muted small" style="font-size: 0.72rem;">Automatically computed from Revenue &minus; Expenses</span>
                                    </td>
                                    <td class="pe-4 text-end font-tabular fw-bold {{ $netIncome >= 0 ? 'text-success' : 'text-danger' }}" data-label="Balance (ETB)">
                                        {{ number_format($netIncome, 2) }}
                                    </td>
                                </tr>
                            </tbody>
                            <tfoot class="table-info bg-opacity-25 border-top border-2">
                                <tr class="fw-bold">
                                    <td colspan="2" class="ps-4 py-2 text-uppercase small text-dark align-middle">
                                        Total Equity (Including Net Income)
                                    </td>
                                    <td class="pe-4 py-2 text-end font-tabular text-primary align-middle">
                                        <span class="small text-muted me-1">ETB</span>{{ number_format($totalEquity, 2) }}
                                    </td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>

                    {{-- Combined Total L & E --}}
                    <div class="p-3 p-md-4 bg-dark text-white d-flex align-items-center justify-content-between mt-auto">
                        <div>
                            <span class="fs-6 fw-bold text-uppercase" style="letter-spacing: 1px;">TOTAL LIABILITIES &amp; EQUITY</span>
                            <div class="text-white-50 small">Total Liabilities + Total Equity</div>
                        </div>
                        <div class="text-end font-tabular fs-5 fw-bold" style="border-bottom: 3px double #ffffff;">
                            <span class="fs-6 me-1 text-white-50">ETB</span>{{ number_format($totalLiabilitiesAndEquity, 2) }}
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>

    {{-- Official Print Signature Block --}}
    <x-print-signatures 
        preparedTitle="Prepared By" 
        preparedRole="Senior Accountant" 
        checkedTitle="Verified By" 
        checkedRole="Finance Head" 
        approvedTitle="Approved By" 
        approvedRole="General Manager" />
</div>
@endsection
