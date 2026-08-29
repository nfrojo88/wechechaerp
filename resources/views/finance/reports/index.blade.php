@extends('layouts.app')

@section('title', 'Core Financial Statements')

@section('content')
<div class="mb-5">
    <h1 class="h3 mb-1 fw-bold text-dark">
        <i class="fa-solid fa-file-invoice-dollar text-primary me-2"></i>Core Financial Statements
    </h1>
    <p class="text-muted mb-0">Select a report below to view detailed financial data.</p>
</div>

<div class="row g-4">

    {{-- Trial Balance --}}
    <div class="col-md-4">
        <a href="{{ route('reports.trial-balance') }}" class="text-decoration-none">
            <div class="report-card card border-0 h-100" style="border-radius: 16px; box-shadow: 0 2px 12px rgba(0,0,0,.07);">
                <div class="card-body p-4">
                    <div class="d-flex align-items-start mb-3">
                        <div class="report-icon me-3" style="width:52px;height:52px;border-radius:14px;background:rgba(99,102,241,.12);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                            <i class="fa-solid fa-scale-balanced" style="color:#6366f1;font-size:1.4rem;"></i>
                        </div>
                        <div>
                            <h5 class="fw-bold mb-1 text-dark">Trial Balance</h5>
                            <span class="badge" style="background:rgba(99,102,241,.12);color:#6366f1;font-size:.72rem;">Accounting</span>
                        </div>
                    </div>
                    <p class="text-muted small mb-3 lh-base">
                        A comprehensive list of all ledger accounts and their current balances to verify that debits equal credits.
                    </p>
                    <div class="d-flex align-items-center text-primary small fw-semibold">
                        <span>View Report</span>
                        <i class="fa-solid fa-arrow-right ms-2" style="font-size:.75rem;"></i>
                    </div>
                </div>
            </div>
        </a>
    </div>

    {{-- Income Statement --}}
    <div class="col-md-4">
        <a href="{{ route('reports.income-statement') }}" class="text-decoration-none">
            <div class="report-card card border-0 h-100" style="border-radius: 16px; box-shadow: 0 2px 12px rgba(0,0,0,.07);">
                <div class="card-body p-4">
                    <div class="d-flex align-items-start mb-3">
                        <div class="report-icon me-3" style="width:52px;height:52px;border-radius:14px;background:rgba(16,185,129,.12);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                            <i class="fa-solid fa-chart-line" style="color:#10b981;font-size:1.4rem;"></i>
                        </div>
                        <div>
                            <h5 class="fw-bold mb-1 text-dark">Income Statement</h5>
                            <span class="badge" style="background:rgba(16,185,129,.12);color:#10b981;font-size:.72rem;">Profit & Loss</span>
                        </div>
                    </div>
                    <p class="text-muted small mb-3 lh-base">
                        Also known as P&L. Summarizes revenues, costs, and expenses incurred during a specific period.
                    </p>
                    <div class="d-flex align-items-center text-success small fw-semibold">
                        <span>View Report</span>
                        <i class="fa-solid fa-arrow-right ms-2" style="font-size:.75rem;"></i>
                    </div>
                </div>
            </div>
        </a>
    </div>

    {{-- Balance Sheet --}}
    <div class="col-md-4">
        <a href="{{ route('reports.balance-sheet') }}" class="text-decoration-none">
            <div class="report-card card border-0 h-100" style="border-radius: 16px; box-shadow: 0 2px 12px rgba(0,0,0,.07);">
                <div class="card-body p-4">
                    <div class="d-flex align-items-start mb-3">
                        <div class="report-icon me-3" style="width:52px;height:52px;border-radius:14px;background:rgba(14,165,233,.12);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                            <i class="fa-solid fa-building-columns" style="color:#0ea5e9;font-size:1.4rem;"></i>
                        </div>
                        <div>
                            <h5 class="fw-bold mb-1 text-dark">Balance Sheet</h5>
                            <span class="badge" style="background:rgba(14,165,233,.12);color:#0ea5e9;font-size:.72rem;">Financial Position</span>
                        </div>
                    </div>
                    <p class="text-muted small mb-3 lh-base">
                        A snapshot of the company's financial position showing assets, liabilities, and equity at a specific point in time.
                    </p>
                    <div class="d-flex align-items-center text-info small fw-semibold">
                        <span>View Report</span>
                        <i class="fa-solid fa-arrow-right ms-2" style="font-size:.75rem;"></i>
                    </div>
                </div>
            </div>
        </a>
    </div>

    {{-- Cash Flow Statement --}}
    <div class="col-md-4">
        <a href="{{ route('reports.cash-flow') }}" class="text-decoration-none">
            <div class="report-card card border-0 h-100" style="border-radius: 16px; box-shadow: 0 2px 12px rgba(0,0,0,.07);">
                <div class="card-body p-4">
                    <div class="d-flex align-items-start mb-3">
                        <div class="report-icon me-3" style="width:52px;height:52px;border-radius:14px;background:rgba(245,158,11,.12);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                            <i class="fa-solid fa-wave-square" style="color:#f59e0b;font-size:1.4rem;"></i>
                        </div>
                        <div>
                            <h5 class="fw-bold mb-1 text-dark">Cash Flow Statement</h5>
                            <span class="badge" style="background:rgba(245,158,11,.12);color:#f59e0b;font-size:.72rem;">Cash Movement</span>
                        </div>
                    </div>
                    <p class="text-muted small mb-3 lh-base">
                        Shows how changes in balance sheet accounts and income affect cash and cash equivalents (Indirect Method).
                    </p>
                    <div class="d-flex align-items-center text-warning small fw-semibold">
                        <span>View Report</span>
                        <i class="fa-solid fa-arrow-right ms-2" style="font-size:.75rem;"></i>
                    </div>
                </div>
            </div>
        </a>
    </div>

    {{-- General Ledger --}}
    <div class="col-md-4">
        <a href="{{ route('reports.general-ledger') }}" class="text-decoration-none">
            <div class="report-card card border-0 h-100" style="border-radius: 16px; box-shadow: 0 2px 12px rgba(0,0,0,.07);">
                <div class="card-body p-4">
                    <div class="d-flex align-items-start mb-3">
                        <div class="report-icon me-3" style="width:52px;height:52px;border-radius:14px;background:rgba(71,85,105,.12);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                            <i class="fa-solid fa-book-open" style="color:#475569;font-size:1.4rem;"></i>
                        </div>
                        <div>
                            <h5 class="fw-bold mb-1 text-dark">General Ledger</h5>
                            <span class="badge" style="background:rgba(71,85,105,.12);color:#475569;font-size:.72rem;">Transaction Detail</span>
                        </div>
                    </div>
                    <p class="text-muted small mb-3 lh-base">
                        Detailed transaction drill-down for every account in the chart of accounts. Track every debit and credit.
                    </p>
                    <div class="d-flex align-items-center text-secondary small fw-semibold">
                        <span>View Report</span>
                        <i class="fa-solid fa-arrow-right ms-2" style="font-size:.75rem;"></i>
                    </div>
                </div>
            </div>
        </a>
    </div>

    {{-- Expense Report by Site --}}
    <div class="col-md-4">
        <a href="{{ route('reports.expense-by-site') }}" class="text-decoration-none">
            <div class="report-card card border-0 h-100" style="border-radius: 16px; box-shadow: 0 2px 12px rgba(0,0,0,.07);">
                <div class="card-body p-4">
                    <div class="d-flex align-items-start mb-3">
                        <div class="report-icon me-3" style="width:52px;height:52px;border-radius:14px;background:rgba(239,68,68,.12);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                            <i class="fa-solid fa-location-dot" style="color:#ef4444;font-size:1.4rem;"></i>
                        </div>
                        <div>
                            <h5 class="fw-bold mb-1 text-dark">Expense Report by Site</h5>
                            <span class="badge" style="background:rgba(239,68,68,.12);color:#ef4444;font-size:.72rem;">Budget vs Actual</span>
                        </div>
                    </div>
                    <p class="text-muted small mb-3 lh-base">
                        Compares allocated budgets against actual expenses recorded in the General Ledger for each active site.
                    </p>
                    <div class="d-flex align-items-center text-danger small fw-semibold">
                        <span>View Report</span>
                        <i class="fa-solid fa-arrow-right ms-2" style="font-size:.75rem;"></i>
                    </div>
                </div>
            </div>
        </a>
    </div>

    {{-- VAT & Withholding Tax Deductions Ledger --}}
    <div class="col-md-4">
        <a href="{{ route('finance.tax-deductions.index') }}" class="text-decoration-none">
            <div class="report-card card border-0 h-100" style="border-radius: 16px; box-shadow: 0 2px 12px rgba(0,0,0,.07);">
                <div class="card-body p-4">
                    <div class="d-flex align-items-start mb-3">
                        <div class="report-icon me-3" style="width:52px;height:52px;border-radius:14px;background:rgba(239,68,68,.12);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                            <i class="fa-solid fa-receipt" style="color:#ef4444;font-size:1.4rem;"></i>
                        </div>
                        <div>
                            <h5 class="fw-bold mb-1 text-dark">VAT &amp; Withholding Tax</h5>
                            <span class="badge" style="background:rgba(239,68,68,.12);color:#ef4444;font-size:.72rem;">Tax Compliance</span>
                        </div>
                    </div>
                    <p class="text-muted small mb-3 lh-base">
                        Track 15% VAT and 3% Withholding Tax deductions on services, contract works, attached tax slips, and ERCA export.
                    </p>
                    <div class="d-flex align-items-center text-danger small fw-semibold">
                        <span>View Tax Ledger</span>
                        <i class="fa-solid fa-arrow-right ms-2" style="font-size:.75rem;"></i>
                    </div>
                </div>
            </div>
        </a>
    </div>

</div>


<style>
.report-card {
    transition: transform 0.2s ease, box-shadow 0.2s ease;
    cursor: pointer;
}
.report-card:hover {
    transform: translateY(-6px);
    box-shadow: 0 12px 32px rgba(0,0,0,.12) !important;
}
.report-card:hover .report-icon {
    transform: scale(1.1);
    transition: transform 0.2s ease;
}
.report-icon {
    transition: transform 0.2s ease;
}
</style>
@endsection
