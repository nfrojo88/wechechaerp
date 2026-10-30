@extends('layouts.app')

@section('title', 'Wechecha ERP Design System & Style Guide')

@section('content')
<div class="wc-container py-3">

    {{-- 1. Page Header Component --}}
    <x-page-header 
        title="Design System & UI Components Style Guide" 
        subtitle="Minimalist, mobile-first design system for all Wechecha ERP reports, dashboards, and export views."
        :breadcrumbs="['Developer Hub' => url('/dev/roles'), 'Style Guide' => '']"
        :backUrl="url('/dashboard')">
        <x-slot name="actions">
            <x-export-menu 
                excelUrl="#excel" 
                csvUrl="#csv" 
                pdfUrl="#pdf" 
                :print="true" />
            <button class="wc-btn-touch wc-btn-primary" @click="$dispatch('notify', { message: 'Primary action triggered smoothly!', type: 'success' })">
                <i class="fa-solid fa-plus"></i>
                <span>Add Record</span>
            </button>
        </x-slot>
    </x-page-header>

    {{-- System Flash / Toast Component Preview --}}
    <x-toast />

    {{-- Responsive Device Viewport Indicator --}}
    <div class="d-flex align-items-center justify-content-between p-3 mb-4 bg-white border rounded" style="border-radius: var(--wc-radius-md);">
        <div class="d-flex align-items-center gap-2">
            <span class="badge bg-dark font-monospace">Device Preview</span>
            <span class="small text-muted">Current simulated / active screen resolution test band:</span>
        </div>
        <div class="d-flex gap-1">
            <span class="badge bg-primary-subtle text-primary d-inline d-sm-none">Mobile (&lt;640px)</span>
            <span class="badge bg-info-subtle text-info d-none d-sm-inline d-lg-none">Tablet (640–1023px)</span>
            <span class="badge bg-warning-subtle text-warning d-none d-lg-inline d-xl-none">Small Laptop (1024–1365px)</span>
            <span class="badge bg-success-subtle text-success d-none d-xl-inline">17-inch Laptop (&ge;1366px)</span>
        </div>
    </div>

    {{-- 2. KPI Cards Row (2 col mobile, 4 col desktop) --}}
    <div class="row g-3 mb-4">
        <div class="col-6 col-xl-3">
            <x-kpi-card 
                label="Total Revenue" 
                value="ETB 4.82M" 
                delta="+14.2%" 
                trend="up" 
                icon="fa-solid fa-wallet" 
                theme="success" 
                subtext="Vs previous billing cycle" />
        </div>
        <div class="col-6 col-xl-3">
            <x-kpi-card 
                label="Site Fuel Burn" 
                value="12,450 L" 
                delta="+3.8%" 
                trend="down" 
                icon="fa-solid fa-gas-pump" 
                theme="danger" 
                subtext="18 fleet machines active" />
        </div>
        <div class="col-6 col-xl-3">
            <x-kpi-card 
                label="Pending Approvals" 
                value="9" 
                delta="-2" 
                trend="neutral" 
                icon="fa-solid fa-clock" 
                theme="warning" 
                subtext="GM & Finance review queue" />
        </div>
        <div class="col-6 col-xl-3">
            <x-kpi-card 
                label="Active Workforce" 
                value="342 Staff" 
                delta="98.5%" 
                trend="up" 
                icon="fa-solid fa-users" 
                theme="primary" 
                subtext="Biometric presence today" />
        </div>
    </div>

    {{-- 3. Filter Bar Component --}}
    <x-filter-bar 
        :action="url('/style-guide')" 
        :activeFiltersCount="2" 
        searchPlaceholder="Search materials, projects, or staff...">
        
        <div style="min-width: 160px;">
            <select name="project_id" class="form-select form-select-sm" style="height: var(--wc-touch-target);">
                <option value="">All Projects</option>
                <option value="1" selected>Head Office Tower</option>
                <option value="2">Gelan Warehouse</option>
            </select>
        </div>

        <div style="min-width: 140px;">
            <select name="status" class="form-select form-select-sm" style="height: var(--wc-touch-target);">
                <option value="">All Statuses</option>
                <option value="approved">Approved</option>
                <option value="pending" selected>Pending</option>
            </select>
        </div>
    </x-filter-bar>

    {{-- 4. Status Badges Palette Section --}}
    <div class="wc-card p-3 p-md-4 mb-4">
        <h6 class="fw-bold text-dark mb-3">Semantic Status Badges (<code style="font-size:0.8rem;">&lt;x-status-badge&gt;</code>)</h6>
        <div class="d-flex flex-wrap gap-2 align-items-center">
            <x-status-badge status="approved" />
            <x-status-badge status="completed" />
            <x-status-badge status="paid" />
            <x-status-badge status="pending" />
            <x-status-badge status="in_progress" />
            <x-status-badge status="submitted" />
            <x-status-badge status="rejected" />
            <x-status-badge status="cancelled" />
            <x-status-badge status="critical" />
            <x-status-badge status="draft" />
            <x-status-badge status="settled" />
            <x-status-badge status="info" label="Informational" />
        </div>
    </div>

    {{-- 5. Data Table Component (Card mode on mobile, sticky headers on desktop) --}}
    <div class="mb-4">
        <h6 class="fw-bold text-dark mb-2">Responsive Data Table (<code style="font-size:0.8rem;">&lt;x-data-table&gt;</code>)</h6>
        <p class="text-muted small mb-3">Transforms smoothly from full tabular view with right-aligned accounting figures on laptop to stacked touch cards on phones without horizontal overflow.</p>

        <x-data-table :headers="[
            'Code / Ref',
            'Item Description',
            'Project Site',
            ['label' => 'Quantity', 'align' => 'right'],
            ['label' => 'Total Amount (ETB)', 'align' => 'right'],
            'Status',
            ['label' => 'Actions', 'align' => 'center']
        ]">
            {{-- Row 1 --}}
            <tr>
                <td data-label="Code / Ref" class="fw-bold font-monospace text-primary">#PR-2026-0041</td>
                <td data-label="Item Description">Reinforcement Steel Bar 16mm (Grade 60)</td>
                <td data-label="Project Site"><span class="badge bg-light text-dark border">Head Office Tower</span></td>
                <td data-label="Quantity" class="text-end wc-num font-monospace">45.00 Qtl</td>
                <td data-label="Total Amount" class="text-end wc-num font-monospace fw-bold">ETB 450,000.00</td>
                <td data-label="Status"><x-status-badge status="approved" /></td>
                <td data-label="Actions" class="text-center">
                    <button class="wc-btn-touch wc-btn-outline p-1 px-2.5" title="View details">
                        <i class="fa-solid fa-eye me-1"></i> View
                    </button>
                </td>
            </tr>

            {{-- Row 2 --}}
            <tr>
                <td data-label="Code / Ref" class="fw-bold font-monospace text-primary">#PR-2026-0042</td>
                <td data-label="Item Description">OPC Cement (Dangote 42.5N)</td>
                <td data-label="Project Site"><span class="badge bg-light text-dark border">Gelan Warehouse</span></td>
                <td data-label="Quantity" class="text-end wc-num font-monospace">200.00 Bags</td>
                <td data-label="Total Amount" class="text-end wc-num font-monospace fw-bold">ETB 198,000.00</td>
                <td data-label="Status"><x-status-badge status="pending" /></td>
                <td data-label="Actions" class="text-center">
                    <button class="wc-btn-touch wc-btn-outline p-1 px-2.5" title="View details">
                        <i class="fa-solid fa-eye me-1"></i> View
                    </button>
                </td>
            </tr>

            {{-- Row 3 --}}
            <tr>
                <td data-label="Code / Ref" class="fw-bold font-monospace text-primary">#PR-2026-0043</td>
                <td data-label="Item Description">River Sand Washed (Fine Aggregate)</td>
                <td data-label="Project Site"><span class="badge bg-light text-dark border">Bole Site #3</span></td>
                <td data-label="Quantity" class="text-end wc-num font-monospace">30.00 m³</td>
                <td data-label="Total Amount" class="text-end wc-num font-monospace fw-bold">ETB 75,000.00</td>
                <td data-label="Status"><x-status-badge status="rejected" /></td>
                <td data-label="Actions" class="text-center">
                    <button class="wc-btn-touch wc-btn-outline p-1 px-2.5" title="View details">
                        <i class="fa-solid fa-eye me-1"></i> View
                    </button>
                </td>
            </tr>
        </x-data-table>
    </div>

    {{-- 6. Form Fields & Stepper Sample --}}
    <div class="row g-3 mb-4">
        <div class="col-lg-6">
            <div class="wc-card p-3 p-md-4 h-100">
                <h6 class="fw-bold text-dark mb-3">Touch-Friendly Form Controls (<code style="font-size:0.8rem;">&lt;x-form-field&gt;</code>)</h6>
                <x-form-field 
                    label="Transaction Title" 
                    name="title" 
                    placeholder="e.g. Fuel Requisition for Crane #4" 
                    required="true" 
                    value="Fuel Requisition for Crane #4" />

                <div class="row g-2">
                    <div class="col-6">
                        <x-form-field 
                            label="Litres Requested" 
                            name="litres" 
                            type="number" 
                            placeholder="0.00" 
                            value="120.00" 
                            hint="Fuel voucher limit: 250L" />
                    </div>
                    <div class="col-6">
                        <x-form-field 
                            label="Odometer / KM" 
                            name="km" 
                            type="number" 
                            placeholder="Current KM" 
                            value="45230" />
                    </div>
                </div>

                <x-form-field 
                    label="Notes / Specification" 
                    name="notes" 
                    type="textarea" 
                    placeholder="Enter any work details..." />
            </div>
        </div>

        <div class="col-lg-6">
            <div class="wc-card p-3 p-md-4 h-100">
                <h6 class="fw-bold text-dark mb-3">Skeleton Loaders (<code style="font-size:0.8rem;">&lt;x-skeleton-loader&gt;</code>)</h6>
                <p class="text-muted small">Prevents layout shift on slow 3G mobile connections.</p>
                <x-skeleton-loader type="table" :rows="3" />
            </div>
        </div>
    </div>

    {{-- 7. Approval Bar Component (Sticky on Mobile, Inline on Desktop) --}}
    <div class="mb-4">
        <h6 class="fw-bold text-dark mb-2">Workflow Approval Bar (<code style="font-size:0.8rem;">&lt;x-approval-bar&gt;</code>)</h6>
        <x-approval-bar 
            status="pending" 
            sendGmUrl="#sendGm" 
            approveUrl="#approve" 
            rejectUrl="#reject" 
            itemName="Sample Report" />
    </div>

    {{-- 8. Empty State Component Sample --}}
    <div class="wc-card p-3 mb-4">
        <x-empty-state 
            icon="fa-chart-pie" 
            title="Empty Analytics State Example" 
            description="When filters return zero matching rows, this component displays a friendly call-to-action." 
            actionUrl="#reset" 
            actionText="Reset Active Filters" 
            actionIcon="fa-rotate-left" />
    </div>

    {{-- 9. Universal Print Header & Signature Block Sample --}}
    <div class="wc-card p-3 p-md-4 mb-4">
        <h6 class="fw-bold text-dark mb-2">Universal Print Header &amp; Signatures (<code style="font-size:0.8rem;">&lt;x-print-header&gt;</code> &amp; <code style="font-size:0.8rem;">&lt;x-print-signatures&gt;</code>)</h6>
        <p class="text-muted small">Automatically rendered upon print while staying hidden on screen display.</p>
        <div class="border rounded p-3 bg-light">
            <x-print-header 
                title="Sample Formal Execution Report" 
                subtitle="Quarterly Operations & Cost Center Audit" 
                dateRange="01 Oct 2026 to 31 Oct 2026" />
            <div class="text-center py-3 text-muted small fst-italic">
                (Document body tables, charts and analytical matrices appear here in printable format)
            </div>
            <x-print-signatures 
                preparedTitle="Prepared By" 
                preparedRole="Site Engineer" 
                checkedTitle="Verified By" 
                checkedRole="Project Manager" 
                approvedTitle="Approved By" 
                approvedRole="General Manager" />
        </div>
    </div>

</div>
@endsection
