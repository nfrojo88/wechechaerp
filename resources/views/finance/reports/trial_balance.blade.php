@extends('layouts.app')

@section('title', 'Trial Balance — Financial Reports')

@section('content')
@php
    $totalDebit = 0;
    $totalCredit = 0;
    $assetCount = 0;
    $liabilityCount = 0;
    $equityCount = 0;
    $revCount = 0;
    $expCount = 0;

    foreach($accounts as $acc) {
        $totalDebit += $acc->computed_debit;
        $totalCredit += $acc->computed_credit;
        if($acc->type === 'asset') $assetCount++;
        elseif(in_array($acc->type, ['liability', 'current_liability', 'long_term_liability'])) $liabilityCount++;
        elseif(str_contains($acc->type, 'equity')) $equityCount++;
        elseif(in_array($acc->type, ['revenue', 'income'])) $revCount++;
        elseif(in_array($acc->type, ['expense', 'cost_of_sale'])) $expCount++;
    }

    $isBalanced = round($totalDebit, 2) === round($totalCredit, 2);
    $difference = abs($totalDebit - $totalCredit);
@endphp

<div class="container-fluid px-0" x-data="{ search: '', typeFilter: '' }">
    <x-print-header title="Trial Balance Sheet" subtitle="General Ledger Debit & Credit Verification" :dateRange="'As of ' . now()->format('F j, Y')" />

    {{-- Page Header --}}
    <x-page-header
        title="Trial Balance"
        subtitle="Verification of debit and credit equality across all general ledger accounts as of {{ now()->format('F j, Y') }}."
        :breadcrumbs="[
            ['label' => 'Dashboard', 'url' => route('dashboard')],
            ['label' => 'Finance', 'url' => route('dashboard.finance')],
            ['label' => 'Reports', 'url' => route('reports.index')],
            ['label' => 'Trial Balance']
        ]">
        <x-slot:actions>
            <a href="{{ route('reports.index') }}" class="btn btn-outline-secondary btn-sm rounded-pill px-3 py-2 shadow-xs fw-semibold">
                <i class="fa-solid fa-arrow-left me-1.5"></i>Reports Hub
            </a>
            <x-export-menu :print="true" label="Print / Export" />
            <a href="{{ route('coa.index') }}" class="btn btn-primary btn-sm rounded-pill px-3 py-2 shadow-xs fw-semibold">
                <i class="fa-solid fa-book-open me-1.5"></i>Chart of Accounts
            </a>
        </x-slot:actions>
    </x-page-header>

    {{-- Unbalanced Alert --}}
    @if(!$isBalanced)
        <div class="alert alert-danger border-0 shadow-sm rounded-4 mb-4 d-flex align-items-center gap-3 p-3 bg-danger bg-opacity-10 border-start border-4 border-danger">
            <div class="p-2 bg-danger text-white rounded-circle fs-5 flex-shrink-0">
                <i class="fa-solid fa-triangle-exclamation"></i>
            </div>
            <div class="flex-grow-1 text-dark">
                <strong class="d-block text-danger">Trial Balance Discrepancy Detected!</strong>
                Debits and credits do not match. Variance is 
                <span class="font-tabular fw-bold text-danger">ETB {{ number_format($difference, 2) }}</span>. 
                Please inspect unposted or unbalanced journal vouchers in the General Ledger.
            </div>
            <a href="{{ route('reports.general-ledger') }}" class="btn btn-sm btn-danger rounded-pill px-3 fw-semibold">
                Investigate Entries
            </a>
        </div>
    @endif

    {{-- Executive KPI Metrics Cards --}}
    <div class="row g-3 mb-4">
        <div class="col-6 col-lg-3">
            <x-kpi-card
                title="Total Debits"
                :value="'ETB ' . number_format($totalDebit, 2)"
                changeLabel="All active debit balances"
                icon="fa-solid fa-arrow-trend-up"
                color="success" />
        </div>
        <div class="col-6 col-lg-3">
            <x-kpi-card
                title="Total Credits"
                :value="'ETB ' . number_format($totalCredit, 2)"
                changeLabel="All active credit balances"
                icon="fa-solid fa-arrow-trend-down"
                color="info" />
        </div>
        <div class="col-6 col-lg-3">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-body p-3 p-md-4 d-flex align-items-center gap-3">
                    <div class="p-3 rounded-4 {{ $isBalanced ? 'bg-success bg-opacity-10 text-success' : 'bg-danger bg-opacity-10 text-danger' }} fs-3 flex-shrink-0">
                        <i class="fa-solid {{ $isBalanced ? 'fa-scale-balanced' : 'fa-triangle-exclamation' }}"></i>
                    </div>
                    <div>
                        <div class="text-muted small text-uppercase fw-bold" style="font-size: 0.72rem; letter-spacing: 0.5px;">Parity Status</div>
                        <div class="fs-5 fw-bold {{ $isBalanced ? 'text-success' : 'text-danger' }}">
                            {{ $isBalanced ? 'Balanced (0.00 ETB)' : 'Discrepancy' }}
                        </div>
                        <span class="text-muted small">
                            {{ $isBalanced ? 'Debits exactly equal credits' : 'Variance: ETB ' . number_format($difference, 2) }}
                        </span>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <x-kpi-card
                title="Active Accounts"
                :value="number_format($accounts->count())"
                changeLabel="Accounts with ledger activity"
                icon="fa-solid fa-calculator"
                color="primary" />
        </div>
    </div>

    {{-- Filter & Instant Search Toolbar --}}
    <div class="card border-0 shadow-sm rounded-4 mb-4">
        <div class="card-body p-3">
            <div class="row g-2 align-items-center">
                <div class="col-12 col-md-6 col-lg-7">
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-white border-end-0 text-muted">
                            <i class="fa-solid fa-magnifying-glass"></i>
                        </span>
                        <input
                            type="search"
                            class="form-control border-start-0 ps-0"
                            placeholder="Search by account code or account name..."
                            x-model="search"
                            aria-label="Filter trial balance">
                    </div>
                </div>
                <div class="col-6 col-md-3 col-lg-3">
                    <select class="form-select form-select-sm" x-model="typeFilter">
                        <option value="">All Account Types</option>
                        <option value="asset">Assets</option>
                        <option value="liability">Liabilities</option>
                        <option value="equity">Equity</option>
                        <option value="revenue">Revenue</option>
                        <option value="expense">Expenses</option>
                    </select>
                </div>
                <div class="col-6 col-md-3 col-lg-2 text-end">
                    <button
                        type="button"
                        class="btn btn-sm btn-outline-secondary rounded-pill w-100"
                        @click="search = ''; typeFilter = ''"
                        x-show="search || typeFilter">
                        <i class="fa-solid fa-rotate-left me-1"></i>Reset
                    </button>
                    <span class="text-muted small d-inline-block py-1" x-show="!search && !typeFilter">
                        Showing all {{ $accounts->count() }} accounts
                    </span>
                </div>
            </div>
        </div>
    </div>

    {{-- Trial Balance Master Table --}}
    <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4 wc-printable-area">
        {{-- Formal Print Header (Only visible on print) --}}
        <div class="d-none d-print-block p-4 border-bottom text-center">
            <h2 class="fw-bold mb-1" style="color: #0F2944;">WECHECHA CONSTRUCTION ERP</h2>
            <h4 class="text-secondary fw-semibold mb-1">Official Trial Balance Statement</h4>
            <p class="text-muted small mb-0">Generated on {{ now()->format('l, F j, Y — H:i') }} | Currency: ETB (Ethiopian Birr)</p>
        </div>

        <div class="card-header bg-white py-3 px-4 border-bottom d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div class="d-flex align-items-center gap-2">
                <span class="badge bg-primary bg-opacity-10 text-primary rounded-pill px-2.5 py-1 fw-bold small">
                    <i class="fa-solid fa-list-check me-1"></i>General Ledger Summary
                </span>
                <span class="text-muted small">As of {{ now()->format('d M Y') }}</span>
            </div>
            <div class="text-muted small">
                Status: <span class="badge {{ $isBalanced ? 'bg-success' : 'bg-danger' }} rounded-pill">{{ $isBalanced ? 'Balanced' : 'Out of Balance' }}</span>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 wc-table-mobile-cards">
                <thead class="table-light">
                    <tr>
                        <th class="ps-4 text-secondary text-uppercase small fw-bold" style="width: 140px;">Account Code</th>
                        <th class="text-secondary text-uppercase small fw-bold">Account Name</th>
                        <th class="text-center text-secondary text-uppercase small fw-bold" style="width: 150px;">Type</th>
                        <th class="text-end text-secondary text-uppercase small fw-bold pe-4" style="width: 180px;">Debit (ETB)</th>
                        <th class="text-end text-secondary text-uppercase small fw-bold pe-4" style="width: 180px;">Credit (ETB)</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($accounts as $acc)
                        @php
                            $debit = (float) $acc->computed_debit;
                            $credit = (float) $acc->computed_credit;
                            $typeClass = match(strtolower($acc->type)) {
                                'asset', 'current_asset', 'fixed_asset' => 'badge-success',
                                'liability', 'current_liability', 'long_term_liability' => 'badge-warning',
                                'equity', 'equity_dose_not_closed' => 'badge-purple',
                                'revenue', 'income' => 'badge-info',
                                'expense', 'cost_of_sale' => 'badge-danger',
                                default => 'badge-secondary'
                            };
                        @endphp
                        <tr
                            x-show="(
                                !search || 
                                '{{ strtolower($acc->code) }}'.includes(search.toLowerCase()) || 
                                '{{ strtolower(addslashes($acc->name)) }}'.includes(search.toLowerCase())
                            ) && (
                                !typeFilter || 
                                '{{ strtolower($acc->type) }}'.includes(typeFilter.toLowerCase())
                            )">
                            <td class="ps-4 fw-bold font-monospace text-primary" data-label="Account Code">
                                {{ $acc->code }}
                            </td>
                            <td data-label="Account Name">
                                <div class="fw-semibold text-dark">{{ $acc->name }}</div>
                                @if(!empty($acc->subtype))
                                    <span class="text-muted small" style="font-size: 0.75rem;">{{ str_replace('_', ' ', ucfirst($acc->subtype)) }}</span>
                                @endif
                            </td>
                            <td class="text-center" data-label="Account Type">
                                <span class="badge {{ $typeClass }} rounded-pill px-2.5 py-1 text-uppercase" style="font-size: 0.7rem; letter-spacing: 0.5px;">
                                    {{ ucfirst(str_replace('_', ' ', $acc->type)) }}
                                </span>
                            </td>
                            <td class="text-end pe-4 font-tabular fw-semibold {{ $debit > 0 ? 'text-dark' : 'text-muted opacity-50' }}" data-label="Debit (ETB)">
                                {{ $debit > 0 ? number_format($debit, 2) : '—' }}
                            </td>
                            <td class="text-end pe-4 font-tabular fw-semibold {{ $credit > 0 ? 'text-dark' : 'text-muted opacity-50' }}" data-label="Credit (ETB)">
                                {{ $credit > 0 ? number_format($credit, 2) : '—' }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center py-5">
                                <x-empty-state
                                    title="No General Ledger Accounts Found"
                                    description="No posted transactions or initial balances are currently recorded in the Chart of Accounts."
                                    icon="fa-solid fa-scale-balanced" />
                            </td>
                        </tr>
                    @endforelse
                </tbody>
                <tfoot class="bg-light border-top border-2">
                    <tr class="fw-bold fs-6">
                        <td colspan="3" class="text-end text-uppercase pe-4 py-3 text-secondary align-middle">
                            Total Ledger Balance:
                        </td>
                        <td class="text-end pe-4 py-3 font-tabular text-dark align-middle" style="border-bottom: 3px double #0F2944;">
                            <span class="small text-muted me-1">ETB</span>{{ number_format($totalDebit, 2) }}
                        </td>
                        <td class="text-end pe-4 py-3 font-tabular text-dark align-middle" style="border-bottom: 3px double #0F2944;">
                            <span class="small text-muted me-1">ETB</span>{{ number_format($totalCredit, 2) }}
                        </td>
                    </tr>
                </tfoot>
            </table>
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

<style>
.badge-purple {
    background-color: rgba(139, 92, 246, 0.12) !important;
    color: #7c3aed !important;
}
</style>
@endsection
