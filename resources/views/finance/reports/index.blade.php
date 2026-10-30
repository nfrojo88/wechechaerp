@extends('layouts.app')

@section('title', 'Financial Statements & Analytics Hub')

@section('content')
<div class="container-fluid px-0">
    <x-page-header
        title="Financial Statements & Analytics Hub"
        subtitle="Comprehensive accounting reports, statutory financial statements, project cost analytics, and ERCA tax compliance engines."
        :breadcrumbs="[
            ['label' => 'Dashboard', 'url' => route('dashboard')],
            ['label' => 'Finance', 'url' => route('dashboard.finance')],
            ['label' => 'Financial Reports']
        ]">
        <x-slot:actions>
            <a href="{{ route('dashboard.finance') }}" class="btn btn-outline-secondary btn-sm rounded-pill px-3 py-2 shadow-xs fw-semibold">
                <i class="fa-solid fa-arrow-left me-1.5"></i>Finance Dashboard
            </a>
            <a href="{{ route('coa.index') }}" class="btn btn-outline-primary btn-sm rounded-pill px-3 py-2 shadow-xs fw-semibold">
                <i class="fa-solid fa-building-columns me-1.5"></i>Chart of Accounts
            </a>
            <a href="{{ route('finance.expense-hub.index') }}" class="btn btn-primary btn-sm rounded-pill px-3 py-2 shadow-xs fw-semibold">
                <i class="fa-solid fa-receipt me-1.5"></i>Expense Hub
            </a>
        </x-slot:actions>
    </x-page-header>

    {{-- ── 1. CORE STATUTORY FINANCIAL STATEMENTS ── --}}
    <div class="mb-4">
        <div class="d-flex align-items-center justify-content-between mb-3">
            <div>
                <h5 class="fw-bold mb-1" style="color: var(--wc-primary, #0F2944);">
                    <i class="fa-solid fa-landmark me-2 text-primary"></i>Statutory Financial Statements
                </h5>
                <p class="text-muted small mb-0">Official GAAP/IFRS compliant financial reporting engines.</p>
            </div>
            <span class="badge bg-primary bg-opacity-10 text-primary rounded-pill px-3 py-1.5 small fw-semibold">
                Core Accounting
            </span>
        </div>

        <div class="row g-3 g-lg-4">
            {{-- Trial Balance --}}
            <div class="col-12 col-md-6 col-xl-3">
                <a href="{{ route('reports.trial-balance') }}" class="text-decoration-none">
                    <div class="card h-100 border-0 shadow-sm rounded-4 wc-report-card">
                        <div class="card-body p-4 d-flex flex-column">
                            <div class="d-flex align-items-center justify-content-between mb-3">
                                <div class="wc-report-icon-box bg-indigo-subtle text-indigo" style="background: rgba(99,102,241,.12); color: #6366f1;">
                                    <i class="fa-solid fa-scale-balanced fs-4"></i>
                                </div>
                                <span class="badge bg-indigo-subtle text-indigo rounded-pill px-2.5 py-1" style="background: rgba(99,102,241,.12); color: #6366f1; font-size: 0.72rem;">
                                    Ledger Audit
                                </span>
                            </div>
                            <h6 class="fw-bold text-dark mb-1 fs-6">Trial Balance</h6>
                            <p class="text-muted small mb-4 flex-grow-1 lh-sm">
                                Full listing of active accounts verifying debit &amp; credit parity with automatic out-of-balance warnings.
                            </p>
                            <div class="d-flex align-items-center text-primary small fw-semibold pt-2 border-top">
                                <span>Open Trial Balance</span>
                                <i class="fa-solid fa-arrow-right ms-auto"></i>
                            </div>
                        </div>
                    </div>
                </a>
            </div>

            {{-- Income Statement (P&L) --}}
            <div class="col-12 col-md-6 col-xl-3">
                <a href="{{ route('reports.income-statement') }}" class="text-decoration-none">
                    <div class="card h-100 border-0 shadow-sm rounded-4 wc-report-card">
                        <div class="card-body p-4 d-flex flex-column">
                            <div class="d-flex align-items-center justify-content-between mb-3">
                                <div class="wc-report-icon-box" style="background: rgba(16,185,129,.12); color: #10b981;">
                                    <i class="fa-solid fa-chart-line fs-4"></i>
                                </div>
                                <span class="badge rounded-pill px-2.5 py-1" style="background: rgba(16,185,129,.12); color: #10b981; font-size: 0.72rem;">
                                    Profit &amp; Loss
                                </span>
                            </div>
                            <h6 class="fw-bold text-dark mb-1 fs-6">Income Statement</h6>
                            <p class="text-muted small mb-4 flex-grow-1 lh-sm">
                                Periodic operating revenue, direct cost of sales, operational overheads, and computed net income or loss.
                            </p>
                            <div class="d-flex align-items-center text-success small fw-semibold pt-2 border-top">
                                <span>View Profit &amp; Loss</span>
                                <i class="fa-solid fa-arrow-right ms-auto"></i>
                            </div>
                        </div>
                    </div>
                </a>
            </div>

            {{-- Balance Sheet --}}
            <div class="col-12 col-md-6 col-xl-3">
                <a href="{{ route('reports.balance-sheet') }}" class="text-decoration-none">
                    <div class="card h-100 border-0 shadow-sm rounded-4 wc-report-card">
                        <div class="card-body p-4 d-flex flex-column">
                            <div class="d-flex align-items-center justify-content-between mb-3">
                                <div class="wc-report-icon-box" style="background: rgba(14,165,233,.12); color: #0ea5e9;">
                                    <i class="fa-solid fa-building-columns fs-4"></i>
                                </div>
                                <span class="badge rounded-pill px-2.5 py-1" style="background: rgba(14,165,233,.12); color: #0ea5e9; font-size: 0.72rem;">
                                    Position
                                </span>
                            </div>
                            <h6 class="fw-bold text-dark mb-1 fs-6">Balance Sheet</h6>
                            <p class="text-muted small mb-4 flex-grow-1 lh-sm">
                                As-of date snapshot of company financial position: current &amp; fixed assets vs. liabilities and equity.
                            </p>
                            <div class="d-flex align-items-center small fw-semibold pt-2 border-top" style="color: #0ea5e9;">
                                <span>View Balance Sheet</span>
                                <i class="fa-solid fa-arrow-right ms-auto"></i>
                            </div>
                        </div>
                    </div>
                </a>
            </div>

            {{-- Cash Flow Statement --}}
            <div class="col-12 col-md-6 col-xl-3">
                <a href="{{ route('reports.cash-flow') }}" class="text-decoration-none">
                    <div class="card h-100 border-0 shadow-sm rounded-4 wc-report-card">
                        <div class="card-body p-4 d-flex flex-column">
                            <div class="d-flex align-items-center justify-content-between mb-3">
                                <div class="wc-report-icon-box" style="background: rgba(245,158,11,.12); color: #f59e0b;">
                                    <i class="fa-solid fa-water fs-4"></i>
                                </div>
                                <span class="badge rounded-pill px-2.5 py-1" style="background: rgba(245,158,11,.12); color: #f59e0b; font-size: 0.72rem;">
                                    Liquidity
                                </span>
                            </div>
                            <h6 class="fw-bold text-dark mb-1 fs-6">Cash Flow Statement</h6>
                            <p class="text-muted small mb-4 flex-grow-1 lh-sm">
                                Indirect cash flow analysis classifying cash movements across operations, investments, and financing.
                            </p>
                            <div class="d-flex align-items-center small fw-semibold pt-2 border-top" style="color: #d97706;">
                                <span>Analyze Cash Flow</span>
                                <i class="fa-solid fa-arrow-right ms-auto"></i>
                            </div>
                        </div>
                    </div>
                </a>
            </div>
        </div>
    </div>

    {{-- ── 2. AUDIT TRAIL & OPERATIONAL COSTING ── --}}
    <div class="mb-4">
        <div class="d-flex align-items-center justify-content-between mb-3">
            <div>
                <h5 class="fw-bold mb-1" style="color: var(--wc-primary, #0F2944);">
                    <i class="fa-solid fa-magnifying-glass-chart me-2 text-primary"></i>Audit Trail &amp; Site Cost Analytics
                </h5>
                <p class="text-muted small mb-0">Detailed transaction drill-downs, budget variances, and project costing.</p>
            </div>
            <span class="badge bg-secondary bg-opacity-10 text-secondary rounded-pill px-3 py-1.5 small fw-semibold">
                Project &amp; Ledger
            </span>
        </div>

        <div class="row g-3 g-lg-4">
            {{-- General Ledger --}}
            <div class="col-12 col-md-6 col-xl-4">
                <a href="{{ route('reports.general-ledger') }}" class="text-decoration-none">
                    <div class="card h-100 border-0 shadow-sm rounded-4 wc-report-card">
                        <div class="card-body p-4 d-flex flex-column">
                            <div class="d-flex align-items-center justify-content-between mb-3">
                                <div class="wc-report-icon-box" style="background: rgba(71,85,105,.12); color: #475569;">
                                    <i class="fa-solid fa-book-open fs-4"></i>
                                </div>
                                <span class="badge rounded-pill px-2.5 py-1" style="background: rgba(71,85,105,.12); color: #475569; font-size: 0.72rem;">
                                    Drill-Down
                                </span>
                            </div>
                            <h6 class="fw-bold text-dark mb-1 fs-6">General Ledger</h6>
                            <p class="text-muted small mb-4 flex-grow-1 lh-sm">
                                Comprehensive journal entry ledger with account-by-account filtering, reference tracking, and author audit trail.
                            </p>
                            <div class="d-flex align-items-center text-secondary small fw-semibold pt-2 border-top">
                                <span>Inspect General Ledger</span>
                                <i class="fa-solid fa-arrow-right ms-auto"></i>
                            </div>
                        </div>
                    </div>
                </a>
            </div>

            {{-- Expense Report by Site --}}
            <div class="col-12 col-md-6 col-xl-4">
                <a href="{{ route('reports.expense-by-site') }}" class="text-decoration-none">
                    <div class="card h-100 border-0 shadow-sm rounded-4 wc-report-card">
                        <div class="card-body p-4 d-flex flex-column">
                            <div class="d-flex align-items-center justify-content-between mb-3">
                                <div class="wc-report-icon-box" style="background: rgba(239,68,68,.12); color: #ef4444;">
                                    <i class="fa-solid fa-location-dot fs-4"></i>
                                </div>
                                <span class="badge rounded-pill px-2.5 py-1" style="background: rgba(239,68,68,.12); color: #ef4444; font-size: 0.72rem;">
                                    Site Costing
                                </span>
                            </div>
                            <h6 class="fw-bold text-dark mb-1 fs-6">Expense Report by Site</h6>
                            <p class="text-muted small mb-4 flex-grow-1 lh-sm">
                                Break down all operational expenses aggregated by Chart of Accounts category and construction site cost centers.
                            </p>
                            <div class="d-flex align-items-center text-danger small fw-semibold pt-2 border-top">
                                <span>View Site Expenses</span>
                                <i class="fa-solid fa-arrow-right ms-auto"></i>
                            </div>
                        </div>
                    </div>
                </a>
            </div>

            {{-- Project Budgets Monitoring --}}
            <div class="col-12 col-md-6 col-xl-4">
                <a href="{{ route('budgets.index') }}" class="text-decoration-none">
                    <div class="card h-100 border-0 shadow-sm rounded-4 wc-report-card">
                        <div class="card-body p-4 d-flex flex-column">
                            <div class="d-flex align-items-center justify-content-between mb-3">
                                <div class="wc-report-icon-box" style="background: rgba(139,92,246,.12); color: #8b5cf6;">
                                    <i class="fa-solid fa-chart-pie fs-4"></i>
                                </div>
                                <span class="badge rounded-pill px-2.5 py-1" style="background: rgba(139,92,246,.12); color: #8b5cf6; font-size: 0.72rem;">
                                    Budget vs Actual
                                </span>
                            </div>
                            <h6 class="fw-bold text-dark mb-1 fs-6">Project Budgets &amp; Variance</h6>
                            <p class="text-muted small mb-4 flex-grow-1 lh-sm">
                                Live comparison between GM-authorized project budgets and actual payments with percentage utilization indicators.
                            </p>
                            <div class="d-flex align-items-center small fw-semibold pt-2 border-top" style="color: #7c3aed;">
                                <span>Monitor Budgets</span>
                                <i class="fa-solid fa-arrow-right ms-auto"></i>
                            </div>
                        </div>
                    </div>
                </a>
            </div>
        </div>
    </div>

    {{-- ── 3. STATUTORY TAX & ERCA COMPLIANCE ── --}}
    <div class="mb-4">
        <div class="d-flex align-items-center justify-content-between mb-3">
            <div>
                <h5 class="fw-bold mb-1" style="color: var(--wc-primary, #0F2944);">
                    <i class="fa-solid fa-shield-halved me-2 text-primary"></i>Statutory Tax &amp; ERCA Compliance
                </h5>
                <p class="text-muted small mb-0">Ethiopian Revenue and Customs Authority declarations, 15% VAT, and 3% Withholding.</p>
            </div>
            <span class="badge bg-danger bg-opacity-10 text-danger rounded-pill px-3 py-1.5 small fw-semibold">
                Ministry of Revenue
            </span>
        </div>

        <div class="row g-3 g-lg-4">
            {{-- VAT & Withholding Tax Deductions Ledger --}}
            <div class="col-12 col-md-6">
                <a href="{{ route('finance.tax-deductions.index') }}" class="text-decoration-none">
                    <div class="card h-100 border-0 shadow-sm rounded-4 wc-report-card">
                        <div class="card-body p-4 d-flex flex-column">
                            <div class="d-flex align-items-center justify-content-between mb-3">
                                <div class="wc-report-icon-box" style="background: rgba(225,29,72,.12); color: #e11d48;">
                                    <i class="fa-solid fa-receipt fs-4"></i>
                                </div>
                                <span class="badge rounded-pill px-2.5 py-1" style="background: rgba(225,29,72,.12); color: #e11d48; font-size: 0.72rem;">
                                    15% VAT &amp; 3% WHT
                                </span>
                            </div>
                            <h6 class="fw-bold text-dark mb-1 fs-6">VAT &amp; Withholding Tax Deductions Ledger</h6>
                            <p class="text-muted small mb-4 flex-grow-1 lh-sm">
                                Track 15% VAT and 3% Withholding Tax deductions across suppliers and subcontracts with OCR receipt scanning, remittance assignments, and Ethiopian calendar monthly settlement workflows.
                            </p>
                            <div class="d-flex align-items-center small fw-semibold pt-2 border-top" style="color: #e11d48;">
                                <span>Open Tax Ledger &amp; Reconciliation</span>
                                <i class="fa-solid fa-arrow-right ms-auto"></i>
                            </div>
                        </div>
                    </div>
                </a>
            </div>

            {{-- Expense Hub & ERCA Declaration Engine --}}
            <div class="col-12 col-md-6">
                <a href="{{ route('finance.expense-hub.index') }}" class="text-decoration-none">
                    <div class="card h-100 border-0 shadow-sm rounded-4 wc-report-card">
                        <div class="card-body p-4 d-flex flex-column">
                            <div class="d-flex align-items-center justify-content-between mb-3">
                                <div class="wc-report-icon-box" style="background: rgba(5,150,105,.12); color: #059669;">
                                    <i class="fa-solid fa-file-invoice-dollar fs-4"></i>
                                </div>
                                <span class="badge rounded-pill px-2.5 py-1" style="background: rgba(5,150,105,.12); color: #059669; font-size: 0.72rem;">
                                    Official ERCA Templates
                                </span>
                            </div>
                            <h6 class="fw-bold text-dark mb-1 fs-6">Expense Hub &amp; MoR / ERCA Declaration Engine</h6>
                            <p class="text-muted small mb-4 flex-grow-1 lh-sm">
                                Review corporate disbursements, verify tax deductibility, export 22-column Master Disbursement register, 9-column Purchase TAS e-Tax declaration, and 15-column ERCA VAT return spreadsheets.
                            </p>
                            <div class="d-flex align-items-center text-success small fw-semibold pt-2 border-top">
                                <span>Access Declaration Engine &amp; Exports</span>
                                <i class="fa-solid fa-arrow-right ms-auto"></i>
                            </div>
                        </div>
                    </div>
                </a>
            </div>
        </div>
    </div>
</div>

<style>
.wc-report-card {
    transition: transform 0.2s cubic-bezier(0.16, 1, 0.3, 1), box-shadow 0.2s ease, border-color 0.2s ease;
    border: 1px solid rgba(0, 0, 0, 0.05) !important;
    background: #ffffff;
}
.wc-report-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 12px 28px rgba(15, 41, 68, 0.08) !important;
    border-color: rgba(15, 41, 68, 0.2) !important;
}
.wc-report-icon-box {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    transition: transform 0.2s ease;
}
.wc-report-card:hover .wc-report-icon-box {
    transform: scale(1.08);
}
@media (max-width: 576px) {
    .wc-report-card .card-body {
        padding: 1.25rem !important;
    }
}
</style>
@endsection
