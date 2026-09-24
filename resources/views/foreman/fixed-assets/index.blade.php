@extends('layouts.app')

@section('title', 'Site Fixed Assets — Foreman View')

@section('content')
<div class="container-fluid py-4">

    {{-- ── Page Header ── --}}
    <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-3">
        <div>
            <h1 class="h4 fw-bold mb-0">
                <i class="fa-solid fa-truck-monster text-warning me-2"></i>
                Fixed Assets — My Site
            </h1>
            @if($store)
                <p class="text-muted small mb-0 mt-1">
                    <i class="fa-solid fa-location-dot me-1"></i>
                    Site: <strong>{{ $store->name }}</strong>
                    @if($store->location) &nbsp;·&nbsp; {{ $store->location }} @endif
                </p>
            @else
                <p class="text-warning small mb-0 mt-1">
                    <i class="fa-solid fa-triangle-exclamation me-1"></i>
                    No site assigned to your account. Contact Admin.
                </p>
            @endif
        </div>
        <a href="{{ route('foreman.my-maintenance-requests') }}" class="btn btn-outline-warning fw-semibold">
            <i class="fa-solid fa-wrench me-1"></i> My Maintenance Requests
        </a>
    </div>

    {{-- ── Flash Messages ── --}}
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm" role="alert">
            <i class="fa-solid fa-circle-check me-2"></i>{{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm" role="alert">
            <i class="fa-solid fa-triangle-exclamation me-2"></i>{{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    {{-- ── KPI Cards ── --}}
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm h-100" style="background: linear-gradient(135deg,#1e3a5f,#2563eb);">
                <div class="card-body text-white text-center py-3">
                    <div class="fs-2 fw-bold">{{ number_format($kpi['total']) }}</div>
                    <div class="small opacity-75">Total Units</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm h-100" style="background: linear-gradient(135deg,#064e3b,#10b981);">
                <div class="card-body text-white text-center py-3">
                    <div class="fs-2 fw-bold">{{ number_format($kpi['in_store']) }}</div>
                    <div class="small opacity-75">In Store / Available</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm h-100" style="background: linear-gradient(135deg,#1e40af,#6366f1);">
                <div class="card-body text-white text-center py-3">
                    <div class="fs-2 fw-bold">{{ number_format($kpi['assigned']) }}</div>
                    <div class="small opacity-75">Assigned</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm h-100" style="background: linear-gradient(135deg,#78350f,#f59e0b);">
                <div class="card-body text-white text-center py-3">
                    <div class="fs-2 fw-bold">{{ number_format($kpi['maintenance']) }}</div>
                    <div class="small opacity-75">Under Maintenance</div>
                </div>
            </div>
        </div>
    </div>

    {{-- ── Filters ── --}}
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body py-3">
            <form method="GET" action="{{ route('foreman.fixed-assets') }}" class="d-flex flex-wrap align-items-center gap-2">
                <input type="text" name="search" class="form-control form-control-sm" style="max-width:220px;"
                       placeholder="Search asset, code, serial..." value="{{ $search }}">

                <select name="status" class="form-select form-select-sm" style="max-width:180px;">
                    <option value="">All Statuses</option>
                    <option value="in_store" {{ $status === 'in_store' ? 'selected' : '' }}>In Store</option>
                    <option value="assigned" {{ $status === 'assigned' ? 'selected' : '' }}>Assigned</option>
                    <option value="maintenance" {{ $status === 'maintenance' ? 'selected' : '' }}>Under Maintenance</option>
                    <option value="disposed" {{ $status === 'disposed' ? 'selected' : '' }}>Disposed</option>
                </select>

                <select name="category" class="form-select form-select-sm" style="max-width:200px;">
                    <option value="">All Categories</option>
                    @foreach($categories as $cat)
                        <option value="{{ $cat }}" {{ $category === $cat ? 'selected' : '' }}>{{ $cat }}</option>
                    @endforeach
                </select>

                <button type="submit" class="btn btn-primary btn-sm">
                    <i class="fa-solid fa-magnifying-glass me-1"></i> Filter
                </button>
                <a href="{{ route('foreman.fixed-assets') }}" class="btn btn-outline-secondary btn-sm">
                    <i class="fa-solid fa-rotate-left me-1"></i> Reset
                </a>
            </form>
        </div>
    </div>

    {{-- ── Asset Units Table ── --}}
    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            @if($units->isEmpty())
                <div class="text-center py-5 text-muted">
                    <i class="fa-solid fa-box-open fa-3x mb-3 opacity-25"></i>
                    <p class="mb-0">No fixed assets found for your site.</p>
                    @if($search || $status || $category)
                        <a href="{{ route('foreman.fixed-assets') }}" class="btn btn-sm btn-outline-secondary mt-3">
                            Clear Filters
                        </a>
                    @endif
                </div>
            @else
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-dark">
                            <tr>
                                <th class="ps-4">Unit Code</th>
                                <th>Asset Name</th>
                                <th>Category</th>
                                <th>Status</th>
                                <th>Condition</th>
                                <th>Assigned To</th>
                                <th>Location</th>
                                <th class="text-end pe-4">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($units as $unit)
                            @php
                                $hasOpenRequest = in_array($unit->id, $openRequestUnitIds);
                                $statusBadge    = $unit->status_badge;
                                $condBadge      = $unit->condition_badge;
                                $canReport      = !$hasOpenRequest && $unit->status !== 'disposed';
                            @endphp
                            <tr>
                                <td class="ps-4">
                                    <span class="font-monospace fw-bold text-primary">{{ $unit->unit_code }}</span>
                                    @if($unit->serial_number)
                                        <div class="small text-muted">SN: {{ $unit->serial_number }}</div>
                                    @endif
                                    @if($unit->plate_number)
                                        <div class="small text-muted">Plate: {{ $unit->plate_number }}</div>
                                    @endif
                                </td>
                                <td>
                                    <span class="fw-semibold">{{ $unit->parentAsset?->name ?? '—' }}</span>
                                    @if($unit->brand || $unit->model)
                                        <div class="small text-muted">{{ trim($unit->brand . ' ' . $unit->model) }}</div>
                                    @endif
                                </td>
                                <td>
                                    <span class="badge bg-light text-dark border">{{ $unit->parentAsset?->category ?? '—' }}</span>
                                </td>
                                <td>
                                    <span class="badge {{ $statusBadge['class'] }}">
                                        <i class="fa-solid {{ $statusBadge['icon'] }} me-1"></i>{{ $statusBadge['label'] }}
                                    </span>
                                </td>
                                <td>
                                    <span class="badge {{ $condBadge['class'] }}">{{ $condBadge['label'] }}</span>
                                </td>
                                <td>
                                    @if($unit->assignedEmployee)
                                        <div class="d-flex align-items-center gap-2">
                                            <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center" style="width:28px;height:28px;font-size:11px;flex-shrink:0;">
                                                {{ strtoupper(substr($unit->assignedEmployee->full_name ?? 'U', 0, 1)) }}
                                            </div>
                                            <span class="small">{{ $unit->assignedEmployee->full_name ?? '—' }}</span>
                                        </div>
                                    @else
                                        <span class="text-muted small">Unassigned</span>
                                    @endif
                                </td>
                                <td class="small text-muted">{{ $unit->current_location ?? '—' }}</td>
                                <td class="text-end pe-4">
                                    @if($hasOpenRequest)
                                        <span class="badge bg-warning text-dark" title="Already has an open maintenance request">
                                            <i class="fa-solid fa-clock me-1"></i> Request Pending
                                        </span>
                                    @elseif($canReport)
                                        <button type="button"
                                                class="btn btn-danger btn-sm shadow-sm"
                                                data-bs-toggle="modal"
                                                data-bs-target="#reportModal"
                                                data-unit-id="{{ $unit->id }}"
                                                data-unit-code="{{ $unit->unit_code }}"
                                                data-asset-name="{{ $unit->parentAsset?->name ?? $unit->unit_code }}"
                                                data-asset-details="{{ trim(($unit->brand ?? '') . ' ' . ($unit->model ?? '')) }}"
                                                onclick="fillReportModal(this)">
                                            <i class="fa-solid fa-wrench me-1"></i> Report Maintenance
                                        </button>
                                    @elseif($unit->status === 'disposed')
                                        <span class="badge bg-danger">Disposed</span>
                                    @endif
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                {{-- Pagination --}}
                @if($units->hasPages())
                    <div class="px-4 py-3 border-top">
                        {{ $units->links() }}
                    </div>
                @endif
            @endif
        </div>
    </div>
</div>

{{-- ── Report Maintenance Modal ── --}}
<div class="modal fade" id="reportModal" tabindex="-1" aria-labelledby="reportModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title fw-bold" id="reportModalLabel">
                    <i class="fa-solid fa-wrench me-2"></i>
                    Report Maintenance Issue
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="{{ route('foreman.maintenance.store') }}">
                @csrf
                <input type="hidden" name="fixed_asset_unit_id" id="reportUnitId">
                <div class="modal-body p-4">

                    {{-- Asset Summary --}}
                    <div class="alert alert-light border rounded-3 mb-4 d-flex align-items-center gap-3">
                        <div class="rounded-circle bg-warning d-flex align-items-center justify-content-center text-dark fw-bold flex-shrink-0" style="width:48px;height:48px;">
                            <i class="fa-solid fa-truck-monster"></i>
                        </div>
                        <div>
                            <div class="fw-bold" id="reportAssetName">—</div>
                            <div class="text-muted small" id="reportUnitCode">—</div>
                            <div class="text-muted small" id="reportAssetDetails"></div>
                        </div>
                    </div>

                    <div class="row g-3">
                        {{-- Issue Type --}}
                        <div class="col-md-6">
                            <label class="form-label fw-semibold required">Issue Type <span class="text-danger">*</span></label>
                            <select name="issue_type" class="form-select" required id="report_issue_type">
                                <option value="">— Select Issue —</option>
                                <option value="breakdown">⚡ Breakdown</option>
                                <option value="damage">💥 Physical Damage</option>
                                <option value="service_due">🔧 Service Due</option>
                                <option value="malfunction">⚠️ Malfunction</option>
                                <option value="needs_repair">🛠️ Needs Repair</option>
                                <option value="other">📋 Other</option>
                            </select>
                        </div>

                        {{-- Urgency --}}
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Urgency <span class="text-danger">*</span></label>
                            <select name="urgency" class="form-select" required id="report_urgency">
                                <option value="normal" selected>🔵 Normal</option>
                                <option value="low">🟢 Low</option>
                                <option value="urgent">🟠 Urgent</option>
                                <option value="critical">🔴 Critical</option>
                            </select>
                        </div>

                        {{-- Description --}}
                        <div class="col-12">
                            <label class="form-label fw-semibold">Description <span class="text-danger">*</span></label>
                            <textarea name="description" class="form-control" rows="4" required
                                      placeholder="Describe the problem in detail. What happened? When did it start? Any visible damage?"></textarea>
                            <div class="form-text">Be as specific as possible to help the General Service team diagnose quickly.</div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger px-4 fw-bold shadow-sm">
                        <i class="fa-solid fa-paper-plane me-2"></i> Submit Maintenance Request
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
function fillReportModal(btn) {
    document.getElementById('reportUnitId').value      = btn.dataset.unitId;
    document.getElementById('reportAssetName').textContent = btn.dataset.assetName;
    document.getElementById('reportUnitCode').textContent  = btn.dataset.unitCode;
    document.getElementById('reportAssetDetails').textContent = btn.dataset.assetDetails || '';
    // Reset selects
    document.getElementById('report_issue_type').value = '';
    document.getElementById('report_urgency').value    = 'normal';
}
</script>
@endpush
