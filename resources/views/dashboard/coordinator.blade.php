@extends('layouts.app')
@section('title', 'Coordinator Dashboard')

@section('content')
<div class="wc-container py-3 py-md-4">
    <x-page-header
        title="Coordinator Dashboard"
        subtitle="Operations coordination, emergency dispatching, inventory oversight &amp; schedule synchronization"
        badge="Site Coordination"
    >
        <x-slot name="actions">
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <span class="badge bg-white text-secondary border px-3 py-2 shadow-xs rounded-pill d-none d-sm-inline-flex align-items-center">
                    <i class="fa-regular fa-calendar text-primary me-2"></i>{{ now()->format('l, F j, Y') }}
                </span>
                <a href="{{ route('procurement.my-queue') }}" class="btn btn-outline-primary btn-sm shadow-xs" style="min-height: 38px; display: inline-flex; align-items: center;">
                    <i class="fas fa-tasks me-1"></i> Procurement Queue
                </a>
                <a href="#emergencySection" class="btn btn-danger btn-sm shadow-xs" style="min-height: 38px; display: inline-flex; align-items: center;">
                    <i class="fas fa-bolt me-1"></i> Emergency Requests
                </a>
            </div>
        </x-slot>
    </x-page-header>

    @if(request()->routeIs('coordinator.emergency-requests*'))
        <div class="alert alert-danger d-flex align-items-center mb-4 shadow-xs rounded-3 border-0" role="alert">
            <i class="fas fa-bolt fa-lg me-2"></i>
            <div>
                <strong>Emergency Material Requests:</strong> Reviewing high-priority requisitions approved by Planning Manager awaiting your immediate dispatch.
            </div>
        </div>
    @endif

    {{-- KPI Cards Row --}}
    <div class="row g-3 mb-4">
        <!-- Inventory Value -->
        <div class="col-12 col-sm-6 col-xl-3">
            <x-kpi-card
                title="Global Inventory Value"
                :value="'ETB ' . number_format($kpi['total_inventory_value'] ?? 0, 2)"
                subtitle="All stores combined"
                icon="fa-solid fa-boxes-stacked"
                tone="primary"
            />
        </div>
        <!-- Active Schedules -->
        <div class="col-12 col-sm-6 col-xl-3">
            <x-kpi-card
                title="Active Schedules Today"
                :value="$kpi['schedules_today'] ?? 0"
                subtitle="Active site timelines"
                icon="fa-solid fa-calendar-check"
                tone="success"
            />
        </div>
        <!-- Plans Awaiting Review -->
        <div class="col-12 col-sm-6 col-xl-3">
            <x-kpi-card
                title="Plans Awaiting Approval"
                :value="$kpi['pending_plans'] ?? 0"
                subtitle="Pending coordinator review"
                icon="fa-solid fa-tasks"
                tone="warning"
            />
        </div>
        <!-- Emergency Requisitions Approved by Planning -->
        <div class="col-12 col-sm-6 col-xl-3">
            <x-kpi-card
                title="Emergency Requests (Planning)"
                :value="$kpi['approved_emergency_requests'] ?? 0"
                subtitle="Awaiting coordinator dispatch"
                icon="fa-solid fa-bolt"
                tone="danger"
            />
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-8">
            <!-- Emergency Requests Approved by Planning -->
            <div class="card shadow-sm mb-4 border-0 border-start border-4 border-danger bg-white" id="emergencySection">
                <div class="card-header py-3 d-flex justify-content-between align-items-center bg-white border-bottom flex-wrap gap-2">
                    <div>
                        <h6 class="m-0 fw-bold text-danger">
                            <i class="fas fa-bolt me-2"></i>Emergency Requisitions Approved by Planning (Awaiting Dispatch)
                        </h6>
                        <small class="text-muted">High-priority requests approved by Planning Manager requiring your dispatch to Store or PR buy.</small>
                    </div>
                    @php
                        $dedupedPrsCount = $pendingCoordinatorPrs->reject(fn($pr) => $approvedEmergencyRequests->contains('id', $pr->material_request_id))->count();
                        $totalCoordEmergency = ($approvedEmergencyRequests->count() ?? 0) + $dedupedPrsCount;
                    @endphp
                    @if($totalCoordEmergency > 0)
                        <span class="badge bg-danger rounded-pill px-3 py-2">{{ $totalCoordEmergency }} Action Needed</span>
                    @endif
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive wc-table-mobile-cards">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-3">Ref / PR No</th>
                                    <th>Project &amp; Store</th>
                                    <th>Items Summary</th>
                                    <th>Status</th>
                                    <th>Approved Date</th>
                                    <th class="text-end pe-3">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @php $renderedItems = 0; @endphp
                                @foreach($approvedEmergencyRequests as $emr)
                                    @php
                                        $renderedItems++;
                                        $cPr = $emr->purchaseRequests->first();
                                    @endphp
                                    <tr class="table-danger bg-opacity-10">
                                        <td data-label="Ref / PR No" class="ps-3">
                                            <strong class="text-dark">{{ $emr->reference_number }}</strong>
                                            @if($cPr)
                                                <div class="small">
                                                    <a href="{{ route('purchase-requests.show', $cPr->id) }}" class="badge bg-light text-primary border text-decoration-none">
                                                        PR #{{ $cPr->pr_no }}
                                                    </a>
                                                </div>
                                            @endif
                                        </td>
                                        <td data-label="Project & Store">
                                            <strong>{{ $emr->project->name ?? 'General Project' }}</strong>
                                            <small class="text-muted d-block"><i class="fas fa-warehouse me-1"></i>Store: {{ $emr->store->name ?? 'N/A' }}</small>
                                        </td>
                                        <td data-label="Items">
                                            <span class="badge bg-light text-dark border">{{ $emr->items->count() }} item(s)</span>
                                            <small class="text-muted d-block">{{ Str::limit($emr->items->map(fn($it) => $it->product->name)->filter()->join(', '), 25, '...') }}</small>
                                        </td>
                                        <td data-label="Status">
                                            <x-status-badge status="Planning Approved" tone="success" icon="fa-solid fa-circle-check" />
                                        </td>
                                        <td data-label="Approved Date"><small class="text-muted">{{ optional($emr->planning_approved_at)->format('M d, Y') ?? $emr->created_at->format('M d, Y') }}</small></td>
                                        <td data-label="Action" class="text-end pe-3">
                                            <div class="d-inline-flex gap-1">
                                                @if($cPr)
                                                    <form method="POST" action="{{ route('purchase-requests.coordinator-approve', $cPr) }}" class="d-inline">
                                                        @csrf
                                                        <button type="submit" class="btn btn-sm btn-primary fw-bold shadow-xs rounded-pill px-3" style="min-height: 36px;">
                                                            <i class="fas fa-share me-1"></i> Dispatch
                                                        </button>
                                                    </form>
                                                    <a href="{{ route('purchase-requests.show', $cPr->id) }}" class="btn btn-sm btn-outline-info rounded-pill px-2" style="min-width: 36px; min-height: 36px; display: inline-flex; align-items: center; justify-content: center;" title="View Details">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                @else
                                                    <form method="POST" action="{{ route('material-requests.coordinator-dispatch', $emr) }}" class="d-inline">
                                                        @csrf
                                                        <button type="submit" class="btn btn-sm btn-primary fw-bold shadow-xs rounded-pill px-3" style="min-height: 36px;">
                                                            <i class="fas fa-share me-1"></i> Dispatch
                                                        </button>
                                                    </form>
                                                    <a href="{{ route('material-requests.show', $emr) }}" class="btn btn-sm btn-outline-info rounded-pill px-2" style="min-width: 36px; min-height: 36px; display: inline-flex; align-items: center; justify-content: center;" title="View Details">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                @endif
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach

                                @foreach($pendingCoordinatorPrs as $cPr)
                                    @if($approvedEmergencyRequests->contains('id', $cPr->material_request_id))
                                        @continue
                                    @endif
                                    @php $renderedItems++; @endphp
                                    <tr class="table-warning bg-opacity-10">
                                        <td data-label="Ref / PR No" class="ps-3">
                                            <a href="{{ route('purchase-requests.show', $cPr->id) }}" class="fw-bold text-decoration-none">
                                                {{ $cPr->pr_no }}
                                            </a>
                                            @if($cPr->materialRequest)
                                                <div class="small">
                                                    <span class="badge bg-light text-secondary border">#{{ $cPr->materialRequest->reference_number }}</span>
                                                </div>
                                            @endif
                                        </td>
                                        <td data-label="Project & Store">
                                            <strong>{{ $cPr->project->name ?? 'General Project' }}</strong>
                                            <small class="text-muted d-block"><i class="fas fa-warehouse me-1"></i>Store: {{ $cPr->store->name ?? 'N/A' }}</small>
                                        </td>
                                        <td data-label="Items">
                                            <span class="badge bg-light text-dark border">{{ $cPr->items->count() }} item(s)</span>
                                            <small class="text-muted d-block">{{ Str::limit($cPr->items->map(fn($it) => $it->product->name)->filter()->join(', '), 25, '...') }}</small>
                                        </td>
                                        <td data-label="Status">
                                            <x-status-badge status="Awaiting Dispatch" tone="warning" icon="fa-solid fa-hourglass-half" />
                                        </td>
                                        <td data-label="Date"><small class="text-muted">{{ $cPr->created_at->format('M d, Y') }}</small></td>
                                        <td data-label="Action" class="text-end pe-3">
                                            <div class="d-inline-flex gap-1">
                                                <form method="POST" action="{{ route('purchase-requests.coordinator-approve', $cPr) }}" class="d-inline">
                                                    @csrf
                                                    <button type="submit" class="btn btn-sm btn-primary fw-bold shadow-xs rounded-pill px-3" style="min-height: 36px;">
                                                        <i class="fas fa-share me-1"></i> Dispatch
                                                    </button>
                                                </form>
                                                <a href="{{ route('purchase-requests.show', $cPr->id) }}" class="btn btn-sm btn-outline-info rounded-pill px-2" style="min-width: 36px; min-height: 36px; display: inline-flex; align-items: center; justify-content: center;" title="View Details">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach

                                @if($renderedItems === 0)
                                    <tr>
                                        <td colspan="6" class="text-center text-muted py-4">
                                            <i class="fas fa-circle-check text-success fa-2x mb-2 d-block"></i>
                                            All planning-approved emergency requisitions have been dispatched.
                                        </td>
                                    </tr>
                                @endif
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Plans & Schedules Sent by Planning Manager -->
            <div class="card shadow-sm mb-4 border-0 bg-white">
                <div class="card-header py-3 d-flex justify-content-between align-items-center bg-white border-bottom">
                    <h6 class="m-0 fw-bold text-dark"><i class="fas fa-clipboard-check text-primary me-2"></i> ERP Plans &amp; Workflows Sent by Planning Manager</h6>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive wc-table-mobile-cards">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-3">Project</th>
                                    <th>Plan Type</th>
                                    <th>Workflow Stage</th>
                                    <th>Planning Manager Note</th>
                                    <th class="text-end pe-3">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($pendingPlansFromPlanning ?? [] as $workflow)
                                <tr>
                                    <td data-label="Project" class="ps-3 fw-bold text-dark">{{ optional($workflow->project)->name ?? 'N/A' }}</td>
                                    <td data-label="Plan Type"><span class="badge bg-secondary text-uppercase">{{ $workflow->plan_type }}</span></td>
                                    <td data-label="Workflow Stage">
                                        @if($workflow->status === 'planning_manager_approved')
                                            <span class="badge bg-warning text-dark"><i class="fas fa-hourglass-half me-1"></i> Awaiting Your Review</span>
                                        @else
                                            <span class="badge bg-info text-dark">{{ $workflow->nextStepLabel() }}</span>
                                        @endif
                                    </td>
                                    <td data-label="Note"><small class="text-muted">{{ Str::limit($workflow->planning_manager_note ?? 'No notes provided', 30) }}</small></td>
                                    <td data-label="Action" class="text-end pe-3">
                                        <a href="{{ route('plan-workflow.show', $workflow->project_id) }}" class="btn btn-sm btn-primary rounded-pill px-3" style="min-height: 34px; display: inline-flex; align-items: center;">
                                            <i class="fas fa-eye me-1"></i> Review Plan
                                        </a>
                                    </td>
                                </tr>
                                @empty
                                <tr><td colspan="5" class="text-center text-muted py-3">No ERP plan workflows currently submitted by Planning Manager.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Recent Daily Reports -->
            <div class="card shadow-sm mb-4 border-0 bg-white">
                <div class="card-header py-3 d-flex justify-content-between align-items-center bg-white border-bottom flex-wrap gap-2">
                    <h6 class="m-0 fw-bold text-dark"><i class="fas fa-file-lines text-primary me-2"></i> Recent Daily Reports (From Sites)</h6>
                    <a href="{{ route('daily-reports.index') }}" class="btn btn-sm btn-outline-primary rounded-pill px-3" style="min-height: 34px; display: inline-flex; align-items: center;">View All</a>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive wc-table-mobile-cards">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light"><tr><th class="ps-3">Date</th><th>Project</th><th>Submitted By</th><th>Status</th></tr></thead>
                            <tbody>
                                @forelse($recentDailyReports ?? [] as $report)
                                <tr>
                                    <td data-label="Date" class="ps-3 small">{{ \Carbon\Carbon::parse($report->report_date)->format('M d, Y') }}</td>
                                    <td data-label="Project" class="fw-semibold">{{ optional($report->project)->name ?? 'N/A' }}</td>
                                    <td data-label="Submitted By" class="small text-muted">{{ optional($report->creator)->name ?? 'N/A' }}</td>
                                    <td data-label="Status">
                                        <x-status-badge :status="$report->status ?? 'pending'" />
                                    </td>
                                </tr>
                                @empty
                                <tr><td colspan="4" class="text-center text-muted py-3">No recent daily reports found.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Recent Site Schedules -->
            <div class="card shadow-sm mb-4 border-0 bg-white">
                <div class="card-header py-3 d-flex justify-content-between align-items-center bg-white border-bottom flex-wrap gap-2">
                    <h6 class="m-0 fw-bold text-dark"><i class="fas fa-calendar-days text-success me-2"></i> Active Site Schedules</h6>
                    <a href="{{ route('schedules.index') }}" class="btn btn-sm btn-outline-primary rounded-pill px-3" style="min-height: 34px; display: inline-flex; align-items: center;">View All</a>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive wc-table-mobile-cards">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light"><tr><th class="ps-3">Schedule Title</th><th>Project</th><th>Duration</th><th>Status</th><th class="text-end pe-3">Action</th></tr></thead>
                            <tbody>
                                @forelse($recentSchedules ?? [] as $sched)
                                <tr>
                                    <td data-label="Title" class="ps-3 fw-semibold text-dark">{{ $sched->name ?? $sched->title ?? 'Project Schedule' }}</td>
                                    <td data-label="Project">{{ optional($sched->project)->name ?? 'N/A' }}</td>
                                    <td data-label="Duration" class="small text-muted">{{ optional($sched->start_date)->format('M d') }} - {{ optional($sched->end_date)->format('M d, Y') }}</td>
                                    <td data-label="Status">
                                        <x-status-badge :status="$sched->status ?? 'pending'" />
                                    </td>
                                    <td data-label="Action" class="text-end pe-3">
                                        <a href="{{ route('schedules.show', $sched) }}" class="btn btn-sm btn-outline-info rounded-pill px-3" style="min-height: 34px; display: inline-flex; align-items: center;"><i class="fas fa-chart-gantt me-1"></i> View</a>
                                    </td>
                                </tr>
                                @empty
                                <tr><td colspan="5" class="text-center text-muted py-3">No active site schedules found.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card shadow-sm mb-4 border-0 bg-white">
                <div class="card-header py-3 bg-white border-bottom">
                    <h6 class="m-0 fw-bold text-dark"><i class="fa-solid fa-compass text-primary me-2"></i>Coordinator Action Center</h6>
                </div>
                <div class="card-body p-0">
                    <div class="list-group list-group-flush">
                        <a href="#emergencySection" class="list-group-item list-group-item-action d-flex align-items-center justify-content-between border-start border-danger border-4 bg-danger bg-opacity-10 py-3">
                            <div class="d-flex align-items-center">
                                <i class="fas fa-bolt text-danger me-3 fs-5"></i>
                                <div>
                                    <h6 class="mb-0 fw-bold text-danger">Emergency Material Request</h6>
                                    <small class="text-muted">Planning-approved emergency MRs awaiting dispatch</small>
                                </div>
                            </div>
                            @if(($kpi['approved_emergency_requests'] ?? 0) > 0)
                                <span class="badge bg-danger rounded-pill">{{ $kpi['approved_emergency_requests'] }} Urgent</span>
                            @endif
                        </a>
                        <a href="{{ route('procurement.my-queue') }}" class="list-group-item list-group-item-action d-flex align-items-center justify-content-between py-3">
                            <div class="d-flex align-items-center">
                                <i class="fas fa-tasks text-warning me-3 fs-5"></i>
                                <div>
                                    <h6 class="mb-0 fw-bold">Procurement Queue</h6>
                                    <small class="text-muted">Site requisitions &amp; PR intake queue</small>
                                </div>
                            </div>
                        </a>
                        <a href="{{ route('schedules.index') }}" class="list-group-item list-group-item-action d-flex align-items-center py-3">
                            <i class="fas fa-calendar-alt text-success me-3 fs-5"></i>
                            <div>
                                <h6 class="mb-0 fw-bold">Site Schedules</h6>
                                <small class="text-muted">Manage &amp; view project timelines</small>
                            </div>
                        </a>
                        <a href="{{ route('inventory.index') }}" class="list-group-item list-group-item-action d-flex align-items-center py-3">
                            <i class="fas fa-boxes text-info me-3 fs-5"></i>
                            <div>
                                <h6 class="mb-0 fw-bold">All Site Inventory</h6>
                                <small class="text-muted">Track materials across all sites &amp; main store</small>
                            </div>
                        </a>
                        <a href="{{ route('coordinator.forecast') }}" class="list-group-item list-group-item-action d-flex align-items-center py-3">
                            <i class="fas fa-chart-pie text-warning me-3 fs-5"></i>
                            <div>
                                <h6 class="mb-0 fw-bold">Forecast Demand</h6>
                                <small class="text-muted">Analyze Material, Equipment, Manpower</small>
                            </div>
                        </a>
                        <a href="{{ \Illuminate\Support\Facades\Route::has('office-requests.index') ? route('office-requests.index') : url('/office-requests') }}" class="list-group-item list-group-item-action d-flex align-items-center justify-content-between py-3">
                            <div class="d-flex align-items-center">
                                <i class="fas fa-boxes-stacked text-warning me-3 fs-5"></i>
                                <div>
                                    <h6 class="mb-0 fw-bold">Office Supply Requisitions</h6>
                                    <small class="text-muted">Secretary requests for HR &amp; Coordinator review</small>
                                </div>
                            </div>
                            @if(($kpi['pending_office_reqs'] ?? 0) > 0)
                                <span class="badge bg-warning text-dark rounded-pill">{{ $kpi['pending_office_reqs'] }}</span>
                            @endif
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    document.addEventListener("DOMContentLoaded", function () {
        if (window.location.hash === '#emergencySection' || {{ request()->routeIs('coordinator.emergency-requests*') ? 'true' : 'false' }}) {
            const el = document.getElementById('emergencySection');
            if (el) {
                el.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        }
    });
</script>
@endpush
