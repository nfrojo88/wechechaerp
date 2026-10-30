@extends('layouts.app')

@section('title', 'General Ledger Drill-Down — Financial Reports')

@section('content')
@php
    $selectedAccount = $accountId ? $accounts->firstWhere('id', $accountId) : null;
@endphp

<div class="container-fluid px-0">
    <x-print-header title="General Ledger Drill-Down" :dateRange="\Carbon\Carbon::parse($startDate)->format('M d, Y') . ' to ' . \Carbon\Carbon::parse($endDate)->format('M d, Y')" />

    {{-- Page Header --}}
    <x-page-header
        title="General Ledger Drill-Down"
        subtitle="Chronological transaction log across journal entries, vouchers, and ledger accounts from {{ \Carbon\Carbon::parse($startDate)->format('M d, Y') }} to {{ \Carbon\Carbon::parse($endDate)->format('M d, Y') }}."
        :breadcrumbs="[
            ['label' => 'Dashboard', 'url' => route('dashboard')],
            ['label' => 'Finance', 'url' => route('dashboard.finance')],
            ['label' => 'Reports', 'url' => route('reports.index')],
            ['label' => 'General Ledger']
        ]">
        <x-slot name="actions">
            <a href="{{ route('reports.index') }}" class="btn btn-outline-secondary btn-sm rounded-pill px-3 py-2 shadow-xs fw-semibold">
                <i class="fa-solid fa-arrow-left me-1.5"></i>Reports Hub
            </a>
            <x-export-menu :print="true" label="Print / Export" />
            <a href="{{ route('coa.index') }}" class="btn btn-primary btn-sm rounded-pill px-3 py-2 shadow-xs fw-semibold">
                <i class="fa-solid fa-book-open me-1.5"></i>Chart of Accounts
            </a>
        </x-slot>
    </x-page-header>

    {{-- Filter Bar --}}
    <div class="card border-0 shadow-sm rounded-4 mb-4">
        <div class="card-body p-3">
            <form method="GET" action="{{ route('reports.general-ledger') }}" class="row g-2 align-items-end">
                <div class="col-12 col-sm-6 col-md-3">
                    <label class="form-label text-muted small fw-bold text-uppercase mb-1" style="font-size: 0.72rem;">From Date</label>
                    <input type="date" name="start_date" class="form-control form-control-sm" value="{{ $startDate }}" required>
                </div>
                <div class="col-12 col-sm-6 col-md-3">
                    <label class="form-label text-muted small fw-bold text-uppercase mb-1" style="font-size: 0.72rem;">To Date</label>
                    <input type="date" name="end_date" class="form-control form-control-sm" value="{{ $endDate }}" required>
                </div>
                <div class="col-12 col-md-4">
                    <label class="form-label text-muted small fw-bold text-uppercase mb-1" style="font-size: 0.72rem;">Account Filter</label>
                    <select name="account_id" class="form-select form-select-sm">
                        <option value="">All Accounts (Complete General Ledger)</option>
                        @foreach($accounts as $acc)
                            <option value="{{ $acc->id }}" {{ $accountId == $acc->id ? 'selected' : '' }}>
                                {{ $acc->code }} — {{ $acc->name }} ({{ ucfirst($acc->type) }})
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12 col-md-2 d-flex gap-2">
                    <button type="submit" class="btn btn-primary btn-sm rounded-pill px-3 py-2 w-100 fw-semibold shadow-xs">
                        <i class="fa-solid fa-filter me-1.5"></i>Filter
                    </button>
                    <a href="{{ route('reports.general-ledger') }}" class="btn btn-outline-secondary btn-sm rounded-pill px-3 py-2 shadow-xs" title="Reset filters">
                        <i class="fa-solid fa-rotate-left"></i>
                    </a>
                </div>
            </form>
        </div>
    </div>

    {{-- Scope Ribbon --}}
    <div class="card border-0 shadow-sm rounded-4 mb-4 bg-light">
        <div class="card-body p-3 d-flex flex-wrap align-items-center justify-content-between gap-3">
            <div class="d-flex align-items-center gap-2">
                <span class="badge bg-primary rounded-pill px-3 py-1.5 fw-semibold small">
                    <i class="fa-solid fa-layer-group me-1"></i>
                    {{ $selectedAccount ? $selectedAccount->code . ' — ' . $selectedAccount->name : 'All Chart of Accounts' }}
                </span>
                @if($selectedAccount)
                    <span class="badge bg-white text-muted border rounded-pill px-2.5 py-1 small">
                        Type: {{ ucfirst(str_replace('_', ' ', $selectedAccount->type)) }}
                    </span>
                @endif
            </div>
            <div class="d-flex align-items-center gap-3 text-muted small">
                <span>
                    Total Entries: <strong class="text-dark font-monospace">{{ number_format($entries->total()) }}</strong>
                </span>
                <span>&bull;</span>
                <span>
                    Page <strong class="text-dark">{{ $entries->currentPage() }}</strong> of <strong class="text-dark">{{ $entries->lastPage() }}</strong>
                </span>
            </div>
        </div>
    </div>

    {{-- General Ledger Master Table --}}
    <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4 wc-printable-area">
        {{-- Formal Print Header (Only visible on print) --}}
        <div class="d-none d-print-block p-4 border-bottom text-center">
            <h2 class="fw-bold mb-1" style="color: #0F2944;">WECHECHA CONSTRUCTION ERP</h2>
            <h4 class="text-secondary fw-semibold mb-1">General Ledger Drill-Down Register</h4>
            <p class="text-muted small mb-0">
                Period: {{ \Carbon\Carbon::parse($startDate)->format('F j, Y') }} — {{ \Carbon\Carbon::parse($endDate)->format('F j, Y') }} 
                | Account: {{ $selectedAccount ? $selectedAccount->code . ' - ' . $selectedAccount->name : 'All Accounts' }}
            </p>
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 wc-table-mobile-cards">
                <thead class="table-light">
                    <tr>
                        <th class="ps-4 text-secondary text-uppercase small fw-bold" style="width: 120px;">Date</th>
                        <th class="text-secondary text-uppercase small fw-bold" style="width: 130px;">Voucher Ref</th>
                        <th class="text-secondary text-uppercase small fw-bold">Account</th>
                        <th class="text-secondary text-uppercase small fw-bold">Narration / Description</th>
                        <th class="text-end text-secondary text-uppercase small fw-bold" style="width: 150px;">Debit (ETB)</th>
                        <th class="text-end text-secondary text-uppercase small fw-bold" style="width: 150px;">Credit (ETB)</th>
                        <th class="pe-4 text-secondary text-uppercase small fw-bold text-center" style="width: 130px;">Posted By</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($entries as $entry)
                        @php
                            $isDebit = $entry->side === 'debit';
                            $amount = (float) $entry->amount;
                        @endphp
                        <tr>
                            <td class="ps-4 text-nowrap font-monospace text-muted small" data-label="Date">
                                {{ \Carbon\Carbon::parse($entry->entry_date)->format('M d, Y') }}
                            </td>
                            <td data-label="Voucher Ref">
                                <span class="badge bg-light text-dark border font-monospace px-2.5 py-1" style="font-size: 0.75rem;">
                                    {{ $entry->reference ?: 'JV-' . $entry->journal_entry_id }}
                                </span>
                            </td>
                            <td data-label="Account">
                                <div class="fw-semibold text-dark">
                                    {{ $entry->account->name ?? 'N/A' }}
                                </div>
                                <span class="badge bg-primary bg-opacity-10 text-primary font-monospace" style="font-size: 0.7rem;">
                                    {{ $entry->account->code ?? '—' }}
                                </span>
                            </td>
                            <td data-label="Description">
                                <span class="text-dark small lh-sm d-inline-block" style="max-width: 320px;">
                                    {{ $entry->je_description ?: '—' }}
                                </span>
                            </td>
                            <td class="text-end font-tabular fw-semibold {{ $isDebit ? 'text-success' : 'text-muted opacity-25' }}" data-label="Debit (ETB)">
                                {{ $isDebit ? number_format($amount, 2) : '—' }}
                            </td>
                            <td class="text-end font-tabular fw-semibold {{ !$isDebit ? 'text-danger' : 'text-muted opacity-25' }}" data-label="Credit (ETB)">
                                {{ !$isDebit ? number_format($amount, 2) : '—' }}
                            </td>
                            <td class="pe-4 text-center text-nowrap" data-label="Posted By">
                                <span class="badge bg-secondary bg-opacity-10 text-secondary rounded-pill px-2.5 py-1 small">
                                    {{ $entry->journalEntry?->creator?->name ?? 'System' }}
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center py-5">
                                <x-empty-state
                                    title="No Journal Transactions Found"
                                    description="No journal entry lines match the selected date range and account filters."
                                    icon="fa-solid fa-book-open" />
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($entries->hasPages())
            <div class="card-footer bg-white border-0 py-3 px-4 d-flex justify-content-between align-items-center flex-wrap gap-2">
                <span class="text-muted small">
                    Showing {{ $entries->firstItem() ?? 0 }} to {{ $entries->lastItem() ?? 0 }} of {{ $entries->total() }} records
                </span>
                <div>
                    {{ $entries->withQueryString()->links() }}
                </div>
            </div>
        @endif

    </div>

    {{-- Official Print Signature Block --}}
    <x-print-signatures 
        preparedTitle="Prepared By" 
        preparedRole="Accountant" 
        checkedTitle="Verified By" 
        checkedRole="Finance Head" 
        approvedTitle="Approved By" 
        approvedRole="Internal Auditor" />
</div>
@endsection
