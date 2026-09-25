@extends('layouts.app')

@section('title', 'My Maintenance Requests')

@section('content')
<div class="container-fluid py-4">

    {{-- ── Header ── --}}
    <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-3">
        <div>
            <h1 class="h4 fw-bold mb-0">
                <i class="fa-solid fa-clipboard-list text-warning me-2"></i>
                My Maintenance Requests
            </h1>
            <p class="text-muted small mb-0 mt-1">Track and submit maintenance requests for equipment and site assets</p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('foreman.fixed-assets') }}" class="btn btn-outline-secondary fw-semibold btn-sm d-flex align-items-center">
                <i class="fa-solid fa-truck-monster me-1"></i> Site Assets
            </a>
            <button type="button" class="btn btn-warning fw-semibold text-dark shadow-sm d-flex align-items-center" data-bs-toggle="modal" data-bs-target="#reportMaintenanceModal">
                <i class="fa-solid fa-plus me-1"></i> Report New Issue
            </button>
        </div>
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

    {{-- ── Stats ── --}}
    <div class="row g-3 mb-4">
        @php
            $statConfig = [
                'pending'               => ['label' => 'Pending',              'color' => '#f59e0b', 'icon' => 'fa-clock'],
                'in_progress'           => ['label' => 'In Progress (GS)',     'color' => '#3b82f6', 'icon' => 'fa-wrench'],
                'sent_to_store_manager' => ['label' => 'Sent to Store Mgr',   'color' => '#8b5cf6', 'icon' => 'fa-paper-plane'],
                'resolved'              => ['label' => 'Resolved',             'color' => '#10b981', 'icon' => 'fa-circle-check'],
            ];
        @endphp
        @foreach($statConfig as $key => $cfg)
        <div class="col-6 col-lg-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body d-flex align-items-center gap-3 py-3">
                    <div class="rounded-circle d-flex align-items-center justify-content-center text-white flex-shrink-0"
                         style="width:46px;height:46px;background:{{ $cfg['color'] }};">
                        <i class="fa-solid {{ $cfg['icon'] }}"></i>
                    </div>
                    <div>
                        <div class="fs-4 fw-bold lh-1">{{ $stats[$key] ?? 0 }}</div>
                        <div class="small text-muted">{{ $cfg['label'] }}</div>
                    </div>
                </div>
            </div>
        </div>
        @endforeach
    </div>

    {{-- ── Status Filter ── --}}
    <div class="d-flex gap-2 mb-4 flex-wrap">
        <a href="{{ route('foreman.my-maintenance-requests') }}"
           class="btn btn-sm {{ !request('status') ? 'btn-dark' : 'btn-outline-secondary' }}">All</a>
        <a href="{{ route('foreman.my-maintenance-requests', ['status' => 'pending']) }}"
           class="btn btn-sm {{ request('status') === 'pending' ? 'btn-warning text-dark' : 'btn-outline-secondary' }}">Pending</a>
        <a href="{{ route('foreman.my-maintenance-requests', ['status' => 'in_progress']) }}"
           class="btn btn-sm {{ request('status') === 'in_progress' ? 'btn-primary' : 'btn-outline-secondary' }}">In Progress</a>
        <a href="{{ route('foreman.my-maintenance-requests', ['status' => 'sent_to_store_manager']) }}"
           class="btn btn-sm {{ request('status') === 'sent_to_store_manager' ? 'btn-info text-dark' : 'btn-outline-secondary' }}">Sent to Store Mgr</a>
        <a href="{{ route('foreman.my-maintenance-requests', ['status' => 'resolved']) }}"
           class="btn btn-sm {{ request('status') === 'resolved' ? 'btn-success' : 'btn-outline-secondary' }}">Resolved</a>
        <a href="{{ route('foreman.my-maintenance-requests', ['status' => 'closed']) }}"
           class="btn btn-sm {{ request('status') === 'closed' ? 'btn-secondary' : 'btn-outline-secondary' }}">Closed</a>
    </div>

    {{-- ── Requests List ── --}}
    @if($requests->isEmpty())
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center py-5 text-muted">
                <i class="fa-solid fa-clipboard-check fa-3x mb-3 opacity-25"></i>
                <p class="mb-2 fw-semibold">No maintenance requests found.</p>
                <div class="d-flex justify-content-center gap-2 flex-wrap">
                    <button type="button" class="btn btn-warning text-dark fw-bold shadow-sm px-4" data-bs-toggle="modal" data-bs-target="#reportMaintenanceModal">
                        <i class="fa-solid fa-wrench me-1"></i> Report a Maintenance Issue
                    </button>
                    <a href="{{ route('foreman.fixed-assets') }}" class="btn btn-outline-secondary btn-sm px-3 d-flex align-items-center">
                        <i class="fa-solid fa-truck-monster me-1"></i> Browse Site Assets
                    </a>
                </div>
            </div>
        </div>
    @else
        <div class="d-flex flex-column gap-3">
            @foreach($requests as $mr)
            @php
                $statusBadge  = $mr->status_badge;
                $urgencyBadge = $mr->urgency_badge;
            @endphp
            <div class="card border-0 shadow-sm">
                <div class="card-body p-4">
                    <div class="row align-items-start g-3">

                        {{-- Left: Request Info --}}
                        <div class="col-md-8">
                            <div class="d-flex align-items-center gap-3 mb-2 flex-wrap">
                                <span class="font-monospace fw-bold text-primary fs-6">{{ $mr->request_no }}</span>
                                <span class="badge {{ $statusBadge['class'] }} px-3 py-2">
                                    <i class="fa-solid {{ $statusBadge['icon'] }} me-1"></i>{{ $statusBadge['label'] }}
                                </span>
                                <span class="badge {{ $urgencyBadge['class'] }} px-2">{{ $urgencyBadge['label'] }}</span>
                            </div>

                            <h6 class="fw-bold mb-1">{{ $mr->asset_name }}</h6>
                            @if($mr->asset_code)
                                <div class="text-muted small mb-2">
                                    <i class="fa-solid fa-tag me-1"></i>{{ $mr->asset_code }}
                                </div>
                            @endif
                            <div class="text-muted small mb-3">
                                <span class="me-3"><i class="fa-solid fa-triangle-exclamation me-1"></i>{{ $mr->issue_type_label }}</span>
                                <span><i class="fa-solid fa-calendar me-1"></i>Reported: {{ $mr->created_at->format('d M Y, H:i') }}</span>
                            </div>
                            <p class="text-secondary small mb-0" style="line-height:1.6;">
                                {{ Str::limit($mr->description, 200) }}
                            </p>
                        </div>

                        {{-- Right: Timeline --}}
                        <div class="col-md-4">
                            <div class="bg-light rounded-3 p-3">
                                <p class="fw-semibold small mb-3 text-dark">
                                    <i class="fa-solid fa-timeline me-1"></i> Request Timeline
                                </p>

                                {{-- Step 1: Submitted --}}
                                <div class="d-flex align-items-start gap-2 mb-2">
                                    <div class="rounded-circle bg-success d-flex align-items-center justify-content-center text-white flex-shrink-0" style="width:22px;height:22px;font-size:10px;margin-top:2px;">
                                        <i class="fa-solid fa-check"></i>
                                    </div>
                                    <div>
                                        <div class="small fw-semibold">Submitted</div>
                                        <div class="small text-muted">{{ $mr->created_at->format('d M Y') }}</div>
                                    </div>
                                </div>

                                {{-- Step 2: GS Pickup --}}
                                <div class="d-flex align-items-start gap-2 mb-2">
                                    <div class="rounded-circle {{ in_array($mr->status, ['in_progress','sent_to_store_manager','resolved','closed']) ? 'bg-primary' : 'bg-light border' }} d-flex align-items-center justify-content-center text-white flex-shrink-0" style="width:22px;height:22px;font-size:10px;margin-top:2px;">
                                        <i class="fa-solid {{ in_array($mr->status, ['in_progress','sent_to_store_manager','resolved','closed']) ? 'fa-check' : 'fa-ellipsis' }}"></i>
                                    </div>
                                    <div>
                                        <div class="small fw-semibold {{ in_array($mr->status, ['in_progress','sent_to_store_manager','resolved','closed']) ? '' : 'text-muted' }}">
                                            General Service Pickup
                                        </div>
                                        <div class="small text-muted">
                                            {{ in_array($mr->status, ['in_progress','sent_to_store_manager','resolved','closed']) ? 'In Progress' : 'Awaiting...' }}
                                        </div>
                                    </div>
                                </div>

                                {{-- Step 3: Outcome --}}
                                <div class="d-flex align-items-start gap-2">
                                    @if($mr->status === 'resolved')
                                        <div class="rounded-circle bg-success d-flex align-items-center justify-content-center text-white flex-shrink-0" style="width:22px;height:22px;font-size:10px;margin-top:2px;">
                                            <i class="fa-solid fa-check"></i>
                                        </div>
                                        <div>
                                            <div class="small fw-semibold text-success">Repaired & Returned</div>
                                            @if($mr->resolved_at)
                                                <div class="small text-muted">{{ $mr->resolved_at->format('d M Y') }}</div>
                                            @endif
                                        </div>
                                    @elseif($mr->status === 'sent_to_store_manager')
                                        <div class="rounded-circle bg-info d-flex align-items-center justify-content-center text-white flex-shrink-0" style="width:22px;height:22px;font-size:10px;margin-top:2px;">
                                            <i class="fa-solid fa-arrow-right"></i>
                                        </div>
                                        <div>
                                            <div class="small fw-semibold text-info">Sent to Store Manager</div>
                                            <div class="small text-muted">Asset may be damaged</div>
                                        </div>
                                    @elseif($mr->status === 'closed')
                                        <div class="rounded-circle bg-secondary d-flex align-items-center justify-content-center text-white flex-shrink-0" style="width:22px;height:22px;font-size:10px;margin-top:2px;">
                                            <i class="fa-solid fa-xmark"></i>
                                        </div>
                                        <div>
                                            <div class="small fw-semibold text-secondary">Closed</div>
                                        </div>
                                    @else
                                        <div class="rounded-circle bg-light border d-flex align-items-center justify-content-center flex-shrink-0" style="width:22px;height:22px;font-size:10px;margin-top:2px;">
                                            <i class="fa-solid fa-ellipsis text-muted"></i>
                                        </div>
                                        <div>
                                            <div class="small fw-semibold text-muted">Outcome Pending</div>
                                        </div>
                                    @endif
                                </div>

                                {{-- GS Notes --}}
                                @if($mr->admin_notes)
                                    <div class="mt-3 pt-3 border-top">
                                        <p class="small fw-semibold mb-1 text-muted">
                                            <i class="fa-solid fa-comment me-1"></i> GS Notes:
                                        </p>
                                        <p class="small mb-0 text-dark">{{ Str::limit($mr->admin_notes, 120) }}</p>
                                    </div>
                                @endif
                            </div>

                            {{-- Assigned GS Staff --}}
                            @if($mr->assignedTo)
                                <div class="mt-2 d-flex align-items-center gap-2 text-muted small">
                                    <i class="fa-solid fa-user-gear"></i>
                                    Assigned to: <strong>{{ $mr->assignedTo->name }}</strong>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
            @endforeach
        </div>

        {{-- Pagination --}}
        @if($requests->hasPages())
            <div class="mt-4">
                {{ $requests->links() }}
            </div>
        @endif
    @endif
</div>

{{-- ── Report Maintenance Modal ── --}}
<div class="modal fade" id="reportMaintenanceModal" tabindex="-1" aria-labelledby="reportMaintenanceModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-warning text-dark border-0">
                <h5 class="modal-title fw-bold" id="reportMaintenanceModalLabel">
                    <i class="fa-solid fa-wrench me-2"></i>Report Maintenance Issue
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="{{ route('foreman.maintenance.store') }}" id="reportMaintenanceForm">
                @csrf
                <div class="modal-body p-4">

                    {{-- Asset Source Switcher --}}
                    <div class="mb-4">
                        <label class="form-label fw-bold small text-uppercase text-muted">Select Asset Source</label>
                        <div class="d-flex gap-3">
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="asset_mode" id="modeRegistered" value="registered" checked onchange="toggleAssetMode()">
                                <label class="form-check-label fw-semibold" for="modeRegistered">
                                    <i class="fa-solid fa-truck-monster text-primary me-1"></i> Site / Registered Fixed Asset
                                </label>
                            </div>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="asset_mode" id="modeCustom" value="custom" onchange="toggleAssetMode()">
                                <label class="form-check-label fw-semibold" for="modeCustom">
                                    <i class="fa-solid fa-pen-to-square text-success me-1"></i> Other Equipment / Machine / Custom Asset
                                </label>
                            </div>
                        </div>
                    </div>

                    {{-- 1. Registered Fixed Asset Option --}}
                    <div id="sectionRegisteredAsset" class="mb-3">
                        <label class="form-label fw-semibold">Choose Asset / Equipment <span class="text-danger">*</span></label>
                        <select name="fixed_asset_unit_id" id="modalUnitSelect" class="form-select select2-modal" style="width:100%;">
                            <option value="">— Choose an asset from inventory —</option>
                            @if(isset($availableUnits) && $availableUnits->isNotEmpty())
                                @foreach($availableUnits as $unit)
                                    <option value="{{ $unit->id }}"
                                            data-code="{{ $unit->unit_code }}"
                                            data-name="{{ $unit->parentAsset?->name ?? $unit->unit_code }}">
                                        [{{ $unit->unit_code }}] {{ $unit->parentAsset?->name ?? 'Asset' }}
                                        @if($unit->plate_number) · Plate: {{ $unit->plate_number }} @endif
                                        @if($unit->serial_number) · SN: {{ $unit->serial_number }} @endif
                                        @if($unit->current_location) ({{ $unit->current_location }}) @endif
                                    </option>
                                @endforeach
                            @endif
                        </select>
                        <div class="form-text">
                            Can't find your asset? Switch to "Other Equipment / Machine / Custom Asset" above to type the name manually.
                        </div>
                    </div>

                    {{-- 2. Custom Asset Option --}}
                    <div id="sectionCustomAsset" class="mb-3" style="display:none;">
                        <div class="row g-3">
                            <div class="col-md-7">
                                <label class="form-label fw-semibold">Asset / Machine Name <span class="text-danger">*</span></label>
                                <input type="text" name="asset_name" id="modalCustomName" class="form-control"
                                       placeholder="e.g. Caterpillar Excavator 320, Generator 50 kVA, Dump Truck #2">
                            </div>
                            <div class="col-md-5">
                                <label class="form-label fw-semibold">Code / Plate # (optional)</label>
                                <input type="text" name="asset_code" id="modalCustomCode" class="form-control font-monospace"
                                       placeholder="e.g. ET-3-12345 or AST-002">
                            </div>
                        </div>
                    </div>

                    <div class="row g-3">
                        {{-- Issue Type --}}
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Issue Type <span class="text-danger">*</span></label>
                            <select name="issue_type" class="form-select" required id="modalIssueType">
                                <option value="">— Select Issue Type —</option>
                                <option value="breakdown">⚡ Breakdown (Won't start / completely stopped)</option>
                                <option value="damage">💥 Physical Damage (Body, structural, glass, hit)</option>
                                <option value="service_due">🔧 Service Due (Routine maintenance / oil & filters)</option>
                                <option value="malfunction">⚠️ Malfunction (Overheating, abnormal noise, slow)</option>
                                <option value="needs_repair">🛠️ Needs Repair (Hydraulic leak, worn parts, tires)</option>
                                <option value="other">📋 Other / General Issue</option>
                            </select>
                        </div>

                        {{-- Urgency --}}
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Urgency Priority <span class="text-danger">*</span></label>
                            <select name="urgency" class="form-select" required id="modalUrgency">
                                <option value="normal" selected>🔵 Normal Priority (Standard work queue)</option>
                                <option value="low">🟢 Low Priority (Cosmetic or non-urgent)</option>
                                <option value="urgent">🟠 Urgent (Impacting site operations)</option>
                                <option value="critical">🔴 Critical (Site work completely halted / hazard)</option>
                            </select>
                        </div>

                        {{-- Description --}}
                        <div class="col-12">
                            <label class="form-label fw-semibold">Detailed Description <span class="text-danger">*</span></label>
                            <textarea name="description" id="modalDescription" class="form-control" rows="4" required
                                      placeholder="Describe the problem in detail: What symptoms are observed? When did it start? Where is the machine located right now?"></textarea>
                            <div class="form-text">Providing clear details helps the General Service team prepare the right parts and technicians immediately.</div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light border-0">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning fw-bold text-dark px-4 shadow-sm" id="modalSubmitBtn">
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
function toggleAssetMode() {
    const isCustom = document.getElementById('modeCustom').checked;
    const regSection = document.getElementById('sectionRegisteredAsset');
    const customSection = document.getElementById('sectionCustomAsset');
    const unitSelect = document.getElementById('modalUnitSelect');
    const customName = document.getElementById('modalCustomName');

    if (isCustom) {
        regSection.style.display = 'none';
        customSection.style.display = 'block';
        unitSelect.value = '';
        customName.required = true;
    } else {
        regSection.style.display = 'block';
        customSection.style.display = 'none';
        customName.value = '';
        customName.required = false;
    }
}

document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('reportMaintenanceForm');
    if (form) {
        form.addEventListener('submit', function (e) {
            const isCustom = document.getElementById('modeCustom').checked;
            const unitSelect = document.getElementById('modalUnitSelect');
            const customName = document.getElementById('modalCustomName');

            if (!isCustom && !unitSelect.value) {
                e.preventDefault();
                alert('Please select an asset from the list, or switch to "Other Equipment / Machine / Custom Asset" to enter its name manually.');
                unitSelect.focus();
                return false;
            }

            if (isCustom && !customName.value.trim()) {
                e.preventDefault();
                alert('Please enter the name of the asset or equipment.');
                customName.focus();
                return false;
            }
        });
    }
});
</script>
@endpush
