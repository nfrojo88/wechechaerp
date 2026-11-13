@extends('layouts.app')

@section('title', 'Abnormal Balances Audit Report — Financial Reports')

@section('content')
<div class="container-fluid px-0">
    <x-print-header title="Abnormal Balances Audit Report" subtitle="Detection of Inverted Normal-Side General Ledger Accounts" :dateRange="'As of ' . \Carbon\Carbon::parse($asOfDate)->format('F j, Y')" />

    {{-- Page Header --}}
    <x-page-header
        title="Abnormal Balances Report"
        subtitle="Automated audit detection of General Ledger accounts with balances on the wrong normal side as of {{ \Carbon\Carbon::parse($asOfDate)->format('F j, Y') }}."
        :breadcrumbs="[
            ['label' => 'Dashboard', 'url' => route('dashboard')],
            ['label' => 'Finance', 'url' => route('dashboard.finance')],
            ['label' => 'Reports', 'url' => route('reports.index')],
            ['label' => 'Abnormal Balances']
        ]">
        <x-slot name="actions">
            <a href="{{ route('reports.trial-balance') }}" class="btn btn-outline-secondary btn-sm rounded-pill px-3 py-2 shadow-xs fw-semibold">
                <i class="fa-solid fa-arrow-left me-1.5"></i>Trial Balance
            </a>
            <x-export-menu :print="true" label="Print / Export" />
        </x-slot>
    </x-page-header>

    {{-- Date Filter Bar --}}
    <div class="card border-0 shadow-sm rounded-4 mb-4">
        <div class="card-body p-3">
            <form method="GET" action="{{ route('reports.abnormal-balances') }}" class="row g-2 align-items-end">
                <div class="col-12 col-sm-6 col-md-4 col-lg-3">
                    <label class="form-label text-muted small fw-bold text-uppercase mb-1" style="font-size: 0.72rem;">As Of Date</label>
                    <input type="date" name="as_of_date" class="form-control form-control-sm" value="{{ $asOfDate }}" required>
                </div>
                <div class="col-12 col-sm-6 col-md-4 col-lg-3 d-flex gap-2">
                    <button type="submit" class="btn btn-primary btn-sm rounded-pill px-4 py-2 w-100 fw-semibold shadow-xs">
                        <i class="fa-solid fa-magnifying-glass me-1.5"></i>Scan Ledger
                    </button>
                    <a href="{{ route('reports.abnormal-balances') }}" class="btn btn-outline-secondary btn-sm rounded-pill px-3 py-2 shadow-xs" title="Reset">
                        <i class="fa-solid fa-rotate-left"></i>
                    </a>
                </div>
            </form>
        </div>
    </div>

    @if($abnormalAccounts->isEmpty())
        <div class="alert alert-success border-0 shadow-sm rounded-4 p-4 text-center">
            <div class="fs-1 text-success mb-2"><i class="fa-solid fa-circle-check"></i></div>
            <h5 class="fw-bold text-success mb-1">Zero Abnormal Balances Detected</h5>
            <p class="text-muted mb-0 small">All active accounts strictly adhere to their normal accounting nature (Assets/Expenses on Debit, Liabilities/Equity/Revenue on Credit).</p>
        </div>
    @else
        <div class="alert alert-warning border-0 shadow-sm rounded-4 mb-4 d-flex align-items-start gap-3 p-3 bg-warning bg-opacity-10 border-start border-4 border-warning">
            <div class="p-2 bg-warning text-dark rounded-circle fs-5 flex-shrink-0">
                <i class="fa-solid fa-triangle-exclamation"></i>
            </div>
            <div class="flex-grow-1 text-dark">
                <strong class="d-block text-warning-emphasis">Accounting Integrity Warning: {{ $abnormalAccounts->count() }} Inverted Balance(s) Flagged!</strong>
                Accounts listed below carry balances on the opposite side of their standard IFRS classification. In construction accounting, cash advances (Petty Cash 1010) and client-withheld taxes (1300) must be debit assets.
            </div>
        </div>

        <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4">Account Code</th>
                            <th>Account Name</th>
                            <th>Type</th>
                            <th>Normal Side</th>
                            <th>Inverted Position</th>
                            <th class="text-end">Abnormal Amount (ETB)</th>
                            <th>Severity</th>
                            <th class="pe-4">Audit Finding &amp; Remediation</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($abnormalAccounts as $row)
                            <tr>
                                <td class="ps-4 font-monospace fw-bold text-primary">{{ $row['code'] }}</td>
                                <td class="fw-semibold text-dark">{{ $row['name'] }}</td>
                                <td><span class="badge bg-secondary-subtle text-secondary-emphasis rounded-pill px-2.5 py-1 text-uppercase" style="font-size:0.7rem;">{{ $row['type'] }}</span></td>
                                <td><span class="badge bg-light text-muted border text-uppercase" style="font-size:0.7rem;">{{ $row['normal_side'] }}</span></td>
                                <td>
                                    <span class="badge bg-danger bg-opacity-10 text-danger border border-danger text-uppercase fw-bold" style="font-size:0.7rem;">
                                        {{ $row['actual_side'] }}
                                    </span>
                                </td>
                                <td class="text-end font-tabular fw-bold text-danger">
                                    ETB {{ number_format($row['abnormal_amount'], 2) }}
                                </td>
                                <td>
                                    @if($row['risk_level'] === 'high')
                                        <span class="badge bg-danger text-white rounded-pill px-2.5 py-1">High Risk</span>
                                    @else
                                        <span class="badge bg-warning text-dark rounded-pill px-2.5 py-1">Attention</span>
                                    @endif
                                </td>
                                <td class="pe-4">
                                    <div class="small text-dark mb-1">{{ $row['explanation'] }}</div>
                                    <div class="small text-muted"><i class="fa-solid fa-wrench text-primary me-1"></i><strong>Action:</strong> {{ $row['recommended_action'] }}</div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</div>
@endsection
