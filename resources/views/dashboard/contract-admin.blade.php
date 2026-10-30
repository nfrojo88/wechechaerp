@extends('layouts.app')

@section('title', 'Contract Admin Dashboard')

@section('content')
<div class="wc-container py-3 py-md-4">

    {{-- Header --}}
    <x-page-header
        title="Contract Admin Dashboard"
        subtitle="BOQ tracking, interim payment certificates (IPC), client billing &amp; earned value"
        badge="Contracts &amp; Commercial"
    >
        <x-slot name="actions">
            <div class="d-flex gap-2 flex-wrap align-items-center">
                <a href="{{ route('client-ipcs.create') }}" class="btn btn-primary btn-sm shadow-xs" style="min-height: 38px; display: inline-flex; align-items: center;">
                    <i class="fa-solid fa-file-invoice-dollar me-1"></i> New Payment Certificate (IPC)
                </a>
                <a href="{{ route('boqs.index') }}" class="btn btn-outline-secondary btn-sm shadow-xs" style="min-height: 38px; display: inline-flex; align-items: center;">
                    <i class="fa-solid fa-table-list me-1"></i> All BOQs
                </a>
            </div>
        </x-slot>
    </x-page-header>

    {{-- ── KPI CARDS ──────────────────────────────────────────────────────── --}}
    <div class="row g-3 mb-4">
        <div class="col-12 col-sm-6 col-xl-2">
            <x-kpi-card
                title="Active Projects"
                :value="$kpi['active_projects']"
                subtitle="In portfolio"
                icon="fa-solid fa-building"
                tone="primary"
            />
        </div>
        <div class="col-12 col-sm-6 col-xl-2">
            <x-kpi-card
                title="Approved BOQ"
                :value="'ETB ' . number_format($kpi['total_boq_value'], 0)"
                subtitle="Total contract BOQ"
                icon="fa-solid fa-file-contract"
                tone="success"
            />
        </div>
        <div class="col-12 col-sm-6 col-xl-2">
            <x-kpi-card
                title="Certified (Client)"
                :value="'ETB ' . number_format($kpi['total_certified'], 0)"
                subtitle="Approved IPC volume"
                icon="fa-solid fa-certificate"
                tone="warning"
            />
        </div>
        <div class="col-12 col-sm-6 col-xl-2">
            <x-kpi-card
                title="Payment (This Mo.)"
                :value="'ETB ' . number_format($kpi['payment_this_month'], 0)"
                subtitle="Collected cash"
                icon="fa-solid fa-money-bill-wave"
                tone="info"
            />
        </div>
        <div class="col-12 col-sm-6 col-xl-2">
            <x-kpi-card
                title="Pending Client IPCs"
                :value="$kpi['pending_client_ipcs']"
                subtitle="Awaiting client cert"
                icon="fa-solid fa-file-invoice"
                tone="purple"
            />
        </div>
        <div class="col-12 col-sm-6 col-xl-2">
            <x-kpi-card
                title="Pending Subcon IPCs"
                :value="$kpi['pending_subcon_ipcs']"
                subtitle="Subcontractor claims"
                icon="fa-solid fa-users-between-lines"
                tone="danger"
            />
        </div>
    </div>

    {{-- ── PROJECT BOQ PROGRESS ─────────────────────────────────────────── --}}
    <div class="card shadow-sm border-0 mb-4 bg-white">
        <div class="card-header bg-white d-flex justify-content-between align-items-center py-3 border-bottom flex-wrap gap-2">
            <span class="fw-semibold text-dark"><i class="fa-solid fa-chart-gantt me-2 text-primary"></i>Project BOQ &amp; Payment Progress</span>
            <a href="{{ route('boqs.index') }}" class="btn btn-sm btn-outline-primary rounded-pill px-3" style="min-height: 34px; display: inline-flex; align-items: center;">View All BOQs</a>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive wc-table-mobile-cards">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-3">Project</th>
                            <th class="text-end">Approved BOQ</th>
                            <th class="text-end">IPC Certified</th>
                            <th class="text-end">Actual Paid</th>
                            <th>% Certified</th>
                            <th class="text-end pe-3">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($projectBOQProgress as $row)
                        <tr>
                            <td data-label="Project" class="ps-3 fw-semibold text-dark">{{ $row['project']->name }}</td>
                            <td data-label="Approved BOQ" class="text-end font-tabular">ETB {{ number_format($row['boq_total'], 0) }}</td>
                            <td data-label="IPC Certified" class="text-end font-tabular text-warning fw-semibold">ETB {{ number_format($row['certified'], 0) }}</td>
                            <td data-label="Actual Paid" class="text-end font-tabular text-success fw-bold">ETB {{ number_format($row['paid'], 0) }}</td>
                            <td data-label="% Certified" style="min-width:160px;">
                                <div class="d-flex align-items-center gap-2">
                                    <div class="progress flex-grow-1" style="height:8px; border-radius:4px;">
                                        <div class="progress-bar {{ $row['pct'] >= 75 ? 'bg-success' : ($row['pct'] >= 40 ? 'bg-warning' : 'bg-primary') }}"
                                             style="{{ 'width:'.$row['pct'].'%' }}"></div>
                                    </div>
                                    <small class="fw-bold font-tabular" style="min-width:38px;">{{ $row['pct'] }}%</small>
                                </div>
                            </td>
                            <td data-label="Actions" class="text-end pe-3">
                                <div class="d-inline-flex gap-1">
                                    <a href="{{ route('boqs.index') }}?project_id={{ $row['project']->id }}" class="btn btn-sm btn-outline-info rounded-pill px-2" style="min-width: 34px; min-height: 34px; display: inline-flex; align-items: center; justify-content: center;" title="View BOQ"><i class="fa-solid fa-table-list"></i></a>
                                    <a href="{{ route('client-ipcs.create') }}?project_id={{ $row['project']->id }}" class="btn btn-sm btn-outline-primary rounded-pill px-2" style="min-width: 34px; min-height: 34px; display: inline-flex; align-items: center; justify-content: center;" title="New IPC"><i class="fa-solid fa-plus"></i></a>
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr><td colspan="6" class="text-center text-muted py-4">No active projects with BOQs found.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="row g-4 mb-4">

        {{-- ── CLIENT IPCs (Payment Certificates) ─────────────────────── --}}
        <div class="col-lg-7">
            <div class="card shadow-sm border-0 h-100 bg-white">
                <div class="card-header bg-white d-flex justify-content-between align-items-center py-3 border-bottom flex-wrap gap-2">
                    <span class="fw-semibold text-dark"><i class="fa-solid fa-file-invoice-dollar me-2 text-warning"></i>Company IPCs (Sent to Client)</span>
                    <a href="{{ route('client-ipcs.index') }}" class="btn btn-sm btn-outline-primary rounded-pill px-3" style="min-height: 34px; display: inline-flex; align-items: center;">View All</a>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive wc-table-mobile-cards">
                        <table class="table table-hover align-middle mb-0" style="font-size: 0.88rem;">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-3">IPC No</th>
                                    <th>Project</th>
                                    <th>Period</th>
                                    <th class="text-end">Gross</th>
                                    <th class="text-end">Net</th>
                                    <th>Status</th>
                                    <th class="text-end pe-3">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($clientIpcs as $ipc)
                                <tr>
                                    <td data-label="IPC No" class="ps-3 fw-semibold">
                                        <a href="{{ route('client-ipcs.show', $ipc) }}" class="text-primary text-decoration-none font-monospace">
                                            {{ $ipc->ipc_no }}
                                        </a>
                                    </td>
                                    <td data-label="Project">{{ Str::limit($ipc->project->name ?? '—', 20) }}</td>
                                    <td data-label="Period" class="small text-muted">{{ $ipc->period_from->format('d M') }}–{{ $ipc->period_to->format('d M Y') }}</td>
                                    <td data-label="Gross" class="text-end font-tabular">{{ number_format($ipc->gross_amount, 0) }}</td>
                                    <td data-label="Net" class="text-end font-tabular fw-semibold text-success">{{ number_format($ipc->net_amount, 0) }}</td>
                                    <td data-label="Status">
                                        <x-status-badge :status="$ipc->status" />
                                    </td>
                                    <td data-label="Action" class="text-end pe-3">
                                        <div class="d-inline-flex gap-1">
                                            <a href="{{ route('client-ipcs.show', $ipc) }}" class="btn btn-sm btn-outline-secondary rounded-pill px-2" style="min-width: 32px; min-height: 32px; display: inline-flex; align-items: center; justify-content: center;" title="View Details"><i class="fa-solid fa-eye"></i></a>
                                            @if(in_array($ipc->status, ['draft','under_review']))
                                            <a href="{{ route('client-ipcs.edit', $ipc) }}" class="btn btn-sm btn-outline-primary rounded-pill px-2" style="min-width: 32px; min-height: 32px; display: inline-flex; align-items: center; justify-content: center;" title="Edit IPC"><i class="fa-solid fa-pen"></i></a>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                                @empty
                                <tr><td colspan="7" class="text-center text-muted py-3">No IPCs found. <a href="{{ route('client-ipcs.create') }}">Create one →</a></td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        {{-- ── DAILY REPORT EARNED VALUE ───────────────────────────────── --}}
        <div class="col-lg-5">
            <div class="card shadow-sm border-0 h-100 bg-white">
                <div class="card-header bg-white py-3 border-bottom">
                    <span class="fw-semibold text-dark"><i class="fa-solid fa-calculator me-2 text-success"></i>Earned Value (Daily Reports → BOQ)</span>
                    <p class="small text-muted mb-0 mt-1">Sum of approved daily report work × BOQ rates vs. certified amount</p>
                </div>
                <div class="card-body p-0">
                    @forelse($dailyEarnedValue as $row)
                    <div class="px-3 py-3 border-bottom">
                        <div class="d-flex justify-content-between align-items-start mb-1">
                            <span class="fw-semibold small text-dark">{{ $row['project']->name }}</span>
                            <span class="badge bg-light text-dark border small font-tabular">BOQ: ETB {{ number_format($row['boq_total'], 0) }}</span>
                        </div>
                        <div class="row g-1 small">
                            <div class="col-6">
                                <p class="mb-0 text-muted">Earned (Site Work)</p>
                                <p class="mb-0 fw-bold text-primary font-tabular">ETB {{ number_format($row['earned'], 0) }}</p>
                            </div>
                            <div class="col-6">
                                <p class="mb-0 text-muted">IPC Certified</p>
                                <p class="mb-0 fw-bold text-success font-tabular">ETB {{ number_format($row['certified'], 0) }}</p>
                            </div>
                        </div>
                        @if($row['boq_total'] > 0)
                        @php $pct = min(round($row['certified'] / $row['boq_total'] * 100, 1), 100); @endphp
                        <div class="progress mt-2" style="height:6px; border-radius:3px;">
                            <div class="progress-bar bg-success" style="{{ 'width:'.$pct.'%' }}"></div>
                        </div>
                        @endif
                    </div>
                    @empty
                    <div class="text-center text-muted py-4 small">
                        <i class="fa-solid fa-file-circle-xmark fa-2x d-block mb-2 opacity-25"></i>
                        No approved BOQs with daily reports yet.
                    </div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">

        {{-- ── ALL BOQs TABLE ──────────────────────────────────────────── --}}
        <div class="col-lg-8">
            <div class="card shadow-sm border-0 bg-white">
                <div class="card-header bg-white d-flex justify-content-between align-items-center py-3 border-bottom flex-wrap gap-2">
                    <span class="fw-semibold text-dark"><i class="fa-solid fa-table-list me-2 text-info"></i>All Project BOQs</span>
                    <a href="{{ route('boqs.create') }}" class="btn btn-sm btn-outline-success rounded-pill px-3" style="min-height: 34px; display: inline-flex; align-items: center;">
                        <i class="fa-solid fa-plus me-1"></i>New BOQ
                    </a>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive wc-table-mobile-cards">
                        <table class="table table-hover align-middle mb-0" style="font-size: 0.88rem;">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-3">Ref No</th>
                                    <th>Project</th>
                                    <th>Title</th>
                                    <th class="text-end">Total Amount</th>
                                    <th>Status</th>
                                    <th class="text-end pe-3">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($allBoqs as $boq)
                                <tr>
                                    <td data-label="Ref No" class="ps-3 fw-semibold small font-monospace">{{ $boq->reference_number }}</td>
                                    <td data-label="Project" class="small">{{ $boq->project->name ?? '—' }}</td>
                                    <td data-label="Title" class="small">{{ Str::limit($boq->title, 30) }}</td>
                                    <td data-label="Total Amount" class="text-end font-tabular fw-semibold">ETB {{ number_format($boq->total_amount, 0) }}</td>
                                    <td data-label="Status">
                                        <x-status-badge :status="$boq->status" />
                                    </td>
                                    <td data-label="Actions" class="text-end pe-3">
                                        <div class="d-inline-flex gap-1">
                                            <a href="{{ route('boqs.show', $boq) }}" class="btn btn-sm btn-outline-secondary rounded-pill px-2" style="min-width: 32px; min-height: 32px; display: inline-flex; align-items: center; justify-content: center;" title="View BOQ"><i class="fa-solid fa-eye"></i></a>
                                            <a href="{{ route('client-ipcs.create') }}?boq_id={{ $boq->id }}&project_id={{ $boq->project_id }}" class="btn btn-sm btn-outline-primary rounded-pill px-2" style="min-width: 32px; min-height: 32px; display: inline-flex; align-items: center; justify-content: center;" title="Create IPC from BOQ"><i class="fa-solid fa-file-invoice-dollar"></i></a>
                                        </div>
                                    </td>
                                </tr>
                                @empty
                                <tr><td colspan="6" class="text-center text-muted py-4">No BOQs found. <a href="{{ route('boqs.create') }}">Create one →</a></td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        {{-- ── RIGHT COLUMN ────────────────────────────────────────────── --}}
        <div class="col-lg-4">

            {{-- Recent Payments --}}
            <div class="card shadow-sm border-0 mb-4 bg-white">
                <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                    <span class="fw-semibold text-dark"><i class="fa-solid fa-money-bill-wave me-2 text-success"></i>Recent Payments</span>
                    <a href="{{ route('payments.create') }}" class="btn btn-sm btn-outline-success rounded-pill px-2" style="min-height: 30px; display: inline-flex; align-items: center;"><i class="fa-solid fa-plus"></i></a>
                </div>
                <div class="card-body p-0">
                    @forelse($recentPayments as $pmt)
                    <div class="d-flex justify-content-between align-items-center px-3 py-2 border-bottom">
                        <div>
                            <p class="mb-0 fw-semibold small text-dark font-monospace">{{ $pmt->reference_number }}</p>
                            <p class="mb-0 text-muted" style="font-size:.72rem;">{{ $pmt->project->name ?? '—' }} · {{ $pmt->payment_type }}</p>
                        </div>
                        <div class="text-end">
                            <p class="mb-0 fw-bold small text-success font-tabular">ETB {{ number_format($pmt->amount, 0) }}</p>
                            <p class="mb-0 text-muted" style="font-size:.72rem;">{{ $pmt->payment_date->format('d M Y') }}</p>
                        </div>
                    </div>
                    @empty
                    <p class="text-center text-muted py-3 small">No payments recorded.</p>
                    @endforelse
                </div>
            </div>

            {{-- Subcon IPC Quick View --}}
            <div class="card shadow-sm border-0 bg-white">
                <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                    <span class="fw-semibold text-dark"><i class="fa-solid fa-users-between-lines me-2 text-danger"></i>Subcon IPCs</span>
                    <a href="{{ route('ipcs.index') }}" class="btn btn-sm btn-outline-danger rounded-pill px-3" style="min-height: 30px; display: inline-flex; align-items: center;">All</a>
                </div>
                <div class="card-body p-0">
                    @forelse($subconIpcs as $ipc)
                    @php $sc = $ipc->agreement?->subcontractor ?? null; @endphp
                    <div class="d-flex justify-content-between align-items-center px-3 py-2 border-bottom">
                        <div>
                            <p class="mb-0 fw-semibold small text-dark font-monospace">{{ $ipc->ipc_no ?? 'IPC-' . $ipc->id }}</p>
                            <p class="mb-0 text-muted" style="font-size:.72rem;">{{ $sc->name ?? ($ipc->agreement->subcontractor_name ?? '—') }}</p>
                        </div>
                        <div class="text-end">
                            <x-status-badge :status="$ipc->status" />
                            <p class="mb-0 text-muted font-tabular" style="font-size:.72rem;">ETB {{ number_format($ipc->current_certified ?? $ipc->net_amount ?? 0, 0) }}</p>
                        </div>
                    </div>
                    @empty
                    <p class="text-center text-muted py-3 small">No subcon IPCs.</p>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
