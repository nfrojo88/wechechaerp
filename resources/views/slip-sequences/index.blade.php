@extends('layouts.app')

@section('title', 'Slip Sequence Configuration')

@section('content')
<div class="container-fluid">
    {{-- Header --}}
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h4 class="mb-0"><i class="fas fa-stream me-2 text-primary"></i>Slip Sequence Configuration</h4>
            <p class="text-muted small mb-0">Separately monitor active in-use sequences and completed / full historical slip books</p>
        </div>
        <div class="d-flex align-items-center gap-2 flex-wrap">
            <form action="{{ route('store-manager.slip-sequences.sync-gaps') }}" method="POST" class="d-inline" onsubmit="return confirm('Check all currently added slips in Store Manager and Store Keeper sections and automatically fill any missing gaps?')">
                @csrf
                <button type="submit" class="btn btn-warning text-dark btn-sm shadow-sm fw-semibold" title="Accept all added slips and automatically fill missing gaps">
                    <i class="fas fa-magic me-1"></i>Check &amp; Auto-Fill Gaps
                </button>
            </form>
            <a href="{{ route('store-manager.slip-history.index') }}" class="btn btn-outline-primary btn-sm shadow-sm">
                <i class="fas fa-history me-1"></i>Complete Slip History
            </a>
            <a href="{{ route('store-manager.slip-sequences.create') }}" class="btn btn-primary btn-sm shadow-sm fw-bold">
                <i class="fas fa-plus me-1"></i>Configure New Sequence
            </a>
        </div>
    </div>

    {{-- Session Alerts --}}
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show shadow-sm mb-3" role="alert">
            <i class="fas fa-check-circle me-2"></i>{{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show shadow-sm mb-3" role="alert">
            <i class="fas fa-exclamation-circle me-2"></i>{{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    {{-- Interactive Metric KPI Cards --}}
    <div class="row g-3 mb-4">
        {{-- Active Books KPI --}}
        <div class="col-md-3 col-sm-6">
            <div class="card shadow-sm border-0 border-start border-4 border-success h-100 kpi-card" role="button" onclick="switchTab('active')">
                <div class="card-body p-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <span class="text-uppercase fw-bold text-success small" style="font-size: 0.75rem; letter-spacing: 0.5px;">Active Sequences</span>
                            <h3 class="fw-bold mb-0 text-dark">{{ $activeCount }}</h3>
                            <small class="text-muted">In-use physical sequence pads</small>
                        </div>
                        <div class="rounded-circle p-2.5 bg-success bg-opacity-10 text-success d-flex align-items-center justify-content-center" style="width: 48px; height: 48px;">
                            <i class="fas fa-play-circle fs-4"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Full / Completed Books KPI --}}
        <div class="col-md-3 col-sm-6">
            <div class="card shadow-sm border-0 border-start border-4 border-danger h-100 kpi-card" role="button" onclick="switchTab('full')">
                <div class="card-body p-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <span class="text-uppercase fw-bold text-danger small" style="font-size: 0.75rem; letter-spacing: 0.5px;">Full / Completed</span>
                            <h3 class="fw-bold mb-0 text-dark">{{ $fullCount }}</h3>
                            <small class="text-muted">Exhausted sequence pads</small>
                        </div>
                        <div class="rounded-circle p-2.5 bg-danger bg-opacity-10 text-danger d-flex align-items-center justify-content-center" style="width: 48px; height: 48px;">
                            <i class="fas fa-check-double fs-4"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Inactive Books KPI --}}
        <div class="col-md-3 col-sm-6">
            <div class="card shadow-sm border-0 border-start border-4 border-secondary h-100 kpi-card" role="button" onclick="switchTab('inactive')">
                <div class="card-body p-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <span class="text-uppercase fw-bold text-secondary small" style="font-size: 0.75rem; letter-spacing: 0.5px;">Inactive / Paused</span>
                            <h3 class="fw-bold mb-0 text-dark">{{ $inactiveCount }}</h3>
                            <small class="text-muted">Temporarily paused sequence pads</small>
                        </div>
                        <div class="rounded-circle p-2.5 bg-secondary bg-opacity-10 text-secondary d-flex align-items-center justify-content-center" style="width: 48px; height: 48px;">
                            <i class="fas fa-pause-circle fs-4"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Total Books KPI --}}
        <div class="col-md-3 col-sm-6">
            <div class="card shadow-sm border-0 border-start border-4 border-primary h-100 kpi-card" role="button" onclick="switchTab('all')">
                <div class="card-body p-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <span class="text-uppercase fw-bold text-primary small" style="font-size: 0.75rem; letter-spacing: 0.5px;">All Sequences</span>
                            <h3 class="fw-bold mb-0 text-dark">{{ $totalCount }}</h3>
                            <small class="text-muted">Total books across all stores</small>
                        </div>
                        <div class="rounded-circle p-2.5 bg-primary bg-opacity-10 text-primary d-flex align-items-center justify-content-center" style="width: 48px; height: 48px;">
                            <i class="fas fa-book fs-4"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Tabs Navigation --}}
    @php
        $activeTab = $currentTab ?? request('tab', 'active');
        if (!in_array($activeTab, ['active', 'full', 'inactive', 'all'])) {
            $activeTab = 'active';
        }
    @endphp

    <ul class="nav nav-pills mb-3 gap-2" id="sequenceTabList" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link {{ $activeTab === 'active' ? 'active' : '' }} fw-semibold" id="tab-active-btn" data-bs-toggle="pill" data-bs-target="#pane-active" type="button" role="tab" onclick="updateUrlTab('active')">
                <i class="fas fa-play-circle me-1 text-success"></i>Active Sequences
                <span class="badge {{ $activeTab === 'active' ? 'bg-white text-success' : 'bg-success' }} ms-1.5">{{ $activeCount }}</span>
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link {{ $activeTab === 'full' ? 'active' : '' }} fw-semibold" id="tab-full-btn" data-bs-toggle="pill" data-bs-target="#pane-full" type="button" role="tab" onclick="updateUrlTab('full')">
                <i class="fas fa-check-double me-1 text-danger"></i>Full / Completed Books
                <span class="badge {{ $activeTab === 'full' ? 'bg-white text-danger' : 'bg-danger' }} ms-1.5">{{ $fullCount }}</span>
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link {{ $activeTab === 'inactive' ? 'active' : '' }} fw-semibold" id="tab-inactive-btn" data-bs-toggle="pill" data-bs-target="#pane-inactive" type="button" role="tab" onclick="updateUrlTab('inactive')">
                <i class="fas fa-pause-circle me-1 text-secondary"></i>Inactive / Paused
                <span class="badge {{ $activeTab === 'inactive' ? 'bg-white text-secondary' : 'bg-secondary' }} ms-1.5">{{ $inactiveCount }}</span>
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link {{ $activeTab === 'all' ? 'active' : '' }} fw-semibold" id="tab-all-btn" data-bs-toggle="pill" data-bs-target="#pane-all" type="button" role="tab" onclick="updateUrlTab('all')">
                <i class="fas fa-list me-1 text-primary"></i>All Sequences
                <span class="badge {{ $activeTab === 'all' ? 'bg-white text-primary' : 'bg-primary' }} ms-1.5">{{ $totalCount }}</span>
            </button>
        </li>
    </ul>

    {{-- Tab Content Panes --}}
    <div class="tab-content" id="sequenceTabContent">

        {{-- 1. ACTIVE SEQUENCES PANE --}}
        <div class="tab-pane fade {{ $activeTab === 'active' ? 'show active' : '' }}" id="pane-active" role="tabpanel">
            <div class="card shadow-sm border-0 rounded-3">
                <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div class="d-flex align-items-center gap-2">
                        <span class="p-1.5 rounded bg-success bg-opacity-10 text-success"><i class="fas fa-play-circle"></i></span>
                        <h6 class="mb-0 fw-bold text-dark">Active Slip Sequences in Operation</h6>
                        <span class="badge bg-success rounded-pill">{{ $activeCount }}</span>
                    </div>
                    <small class="text-muted">These are the currently active pads used to assign next slip numbers for store transactions</small>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-3">Store</th>
                                    <th>Slip Type</th>
                                    <th>Label</th>
                                    <th>Prefix</th>
                                    <th>Book Range</th>
                                    <th>Next Available Slip</th>
                                    <th style="min-width: 170px;">Book Usage Progress</th>
                                    <th>Status</th>
                                    <th class="text-end pe-3">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($activeSequences as $seq)
                                <tr>
                                    <td class="ps-3">
                                        <strong class="text-dark">{{ $seq->store->name ?? 'N/A' }}</strong>
                                        <small class="d-block text-muted">{{ $seq->store->type ?? 'Warehouse' }}</small>
                                    </td>
                                    <td>
                                        @if($seq->slip_type === 'receive')
                                            <span class="badge bg-success bg-opacity-15 text-success border border-success border-opacity-25 px-2 py-1">
                                                <i class="fas fa-arrow-down me-1"></i>GRN (Receiving)
                                            </span>
                                        @else
                                            <span class="badge bg-info bg-opacity-15 text-info border border-info border-opacity-25 px-2 py-1">
                                                <i class="fas fa-arrow-up me-1"></i>SIN (Outgoing)
                                            </span>
                                        @endif
                                    </td>
                                    <td class="fw-semibold text-dark">{{ $seq->label }}</td>
                                    <td><code>{{ $seq->prefix ?: '(none)' }}</code></td>
                                    <td>
                                        <span class="font-monospace fw-bold text-dark">#{{ $seq->book_start_no }}</span>
                                        <span class="text-muted mx-1">→</span>
                                        <span class="font-monospace fw-bold text-dark">#{{ $seq->book_end_no }}</span>
                                        <small class="text-muted d-block">{{ (int)$seq->book_end_no - (int)$seq->book_start_no + 1 }} total slips</small>
                                    </td>
                                    <td>
                                        <span class="badge bg-success fs-6 shadow-xs px-2.5 py-1.5 font-monospace">
                                            <i class="fas fa-play me-1" style="font-size: 0.65rem;"></i>{{ $seq->getNextSlipNumber() }}
                                        </span>
                                    </td>
                                    <td>
                                        @php
                                            $capacity = max(1, (int) $seq->book_end_no - (int) $seq->book_start_no + 1);
                                            $pct = $seq->getPercentageUsed();
                                            $used = min($capacity, max((int) $seq->used_count, (int) round(($pct / 100) * $capacity)));
                                        @endphp
                                        <div class="d-flex justify-content-between small mb-1">
                                            <strong>{{ $used }} / {{ $capacity }} used</strong>
                                            <span class="fw-bold {{ $pct >= 90 ? 'text-danger' : ($pct >= 70 ? 'text-warning' : 'text-success') }}">{{ $pct }}%</span>
                                        </div>
                                        <div class="progress" style="height: 6px;">
                                            <div class="progress-bar {{ $pct >= 90 ? 'bg-danger' : ($pct >= 70 ? 'bg-warning' : 'bg-success') }}" 
                                                 role="progressbar" 
                                                 style="width: {{ $pct }}%;" 
                                                 aria-valuenow="{{ $pct }}" aria-valuemin="0" aria-valuemax="100"></div>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge bg-success text-white px-2.5 py-1">
                                            <i class="fas fa-check-circle me-1"></i>Active
                                        </span>
                                    </td>
                                    <td class="text-end pe-3">
                                        <div class="d-inline-flex gap-1">
                                            <a href="{{ route('store-manager.slip-sequences.edit', $seq) }}" class="btn btn-sm btn-outline-primary shadow-xs" title="Edit Sequence Book & Inspect Slips">
                                                <i class="fas fa-edit me-1"></i>Edit
                                            </a>
                                            <form action="{{ route('store-manager.slip-sequences.deactivate', $seq) }}" method="POST" class="d-inline">
                                                @csrf
                                                <button type="submit" class="btn btn-sm btn-outline-warning text-dark shadow-xs" title="Pause / Deactivate this sequence">
                                                    <i class="fas fa-pause"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="9" class="text-center py-5 text-muted">
                                        <i class="fas fa-stream fa-2x mb-3 text-secondary opacity-50 d-block"></i>
                                        No active slip sequences configured yet.
                                        <div class="mt-2">
                                            <a href="{{ route('store-manager.slip-sequences.create') }}" class="btn btn-sm btn-primary">
                                                <i class="fas fa-plus me-1"></i>Configure Active Sequence
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        {{-- 2. FULL / COMPLETED BOOKS PANE --}}
        <div class="tab-pane fade {{ $activeTab === 'full' ? 'show active' : '' }}" id="pane-full" role="tabpanel">
            <div class="card shadow-sm border-0 rounded-3">
                <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div class="d-flex align-items-center gap-2">
                        <span class="p-1.5 rounded bg-danger bg-opacity-10 text-danger"><i class="fas fa-check-double"></i></span>
                        <h6 class="mb-0 fw-bold text-dark">Exhausted &amp; Completed Sequence Books (Full)</h6>
                        <span class="badge bg-danger rounded-pill">{{ $fullCount }}</span>
                    </div>
                    <small class="text-muted">These sequence books have reached their end slip number or were archived upon starting a new pad</small>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-3">Store</th>
                                    <th>Slip Type</th>
                                    <th>Label</th>
                                    <th>Prefix</th>
                                    <th>Book Range</th>
                                    <th>Total Capacity</th>
                                    <th>Status</th>
                                    <th class="text-end pe-3">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($fullSequences as $seq)
                                <tr>
                                    <td class="ps-3">
                                        <strong class="text-dark">{{ $seq->store->name ?? 'N/A' }}</strong>
                                        <small class="d-block text-muted">{{ $seq->store->type ?? 'Warehouse' }}</small>
                                    </td>
                                    <td>
                                        @if($seq->slip_type === 'receive')
                                            <span class="badge bg-success bg-opacity-15 text-success border border-success border-opacity-25 px-2 py-1">
                                                <i class="fas fa-arrow-down me-1"></i>GRN
                                            </span>
                                        @else
                                            <span class="badge bg-info bg-opacity-15 text-info border border-info border-opacity-25 px-2 py-1">
                                                <i class="fas fa-arrow-up me-1"></i>SIN
                                            </span>
                                        @endif
                                    </td>
                                    <td class="fw-semibold text-dark">{{ $seq->label }}</td>
                                    <td><code>{{ $seq->prefix ?: '(none)' }}</code></td>
                                    <td>
                                        <span class="font-monospace fw-bold text-secondary">#{{ $seq->book_start_no }}</span>
                                        <span class="text-muted mx-1">→</span>
                                        <span class="font-monospace fw-bold text-secondary">#{{ $seq->book_end_no }}</span>
                                    </td>
                                    <td>
                                        <span class="small fw-bold text-dark">{{ (int)$seq->book_end_no - (int)$seq->book_start_no + 1 }} slips</span>
                                        <div class="progress mt-1" style="height: 5px;">
                                            <div class="progress-bar bg-danger" role="progressbar" style="width: 100%;" aria-valuenow="100" aria-valuemin="0" aria-valuemax="100"></div>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge bg-danger text-white px-2.5 py-1">
                                            <i class="fas fa-check-double me-1"></i>Completed / Full
                                        </span>
                                    </td>
                                    <td class="text-end pe-3">
                                        <div class="d-inline-flex gap-1">
                                            <a href="{{ route('store-manager.slip-sequences.edit', $seq) }}" class="btn btn-sm btn-outline-primary shadow-xs" title="Inspect slip audit history & start next book">
                                                <i class="fas fa-eye me-1"></i>Inspect Book
                                            </a>
                                            <a href="{{ route('store-manager.slip-sequences.create') }}?store_id={{ $seq->store_id }}&slip_type={{ $seq->slip_type }}" class="btn btn-sm btn-primary shadow-xs" title="Configure the next sequence book for this store">
                                                <i class="fas fa-plus me-1"></i>Next Book
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="8" class="text-center py-5 text-muted">
                                        <i class="fas fa-box-open fa-2x mb-3 text-secondary opacity-50 d-block"></i>
                                        No completed or full sequence books recorded yet.
                                    </td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        {{-- 3. INACTIVE SEQUENCES PANE --}}
        <div class="tab-pane fade {{ $activeTab === 'inactive' ? 'show active' : '' }}" id="pane-inactive" role="tabpanel">
            <div class="card shadow-sm border-0 rounded-3">
                <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div class="d-flex align-items-center gap-2">
                        <span class="p-1.5 rounded bg-secondary bg-opacity-10 text-secondary"><i class="fas fa-pause-circle"></i></span>
                        <h6 class="mb-0 fw-bold text-dark">Inactive / Paused Sequence Books</h6>
                        <span class="badge bg-secondary rounded-pill">{{ $inactiveCount }}</span>
                    </div>
                    <small class="text-muted">Sequences that have been manually paused</small>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-3">Store</th>
                                    <th>Slip Type</th>
                                    <th>Label</th>
                                    <th>Prefix</th>
                                    <th>Book Range</th>
                                    <th>Next Slip</th>
                                    <th>Status</th>
                                    <th class="text-end pe-3">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($inactiveSequences as $seq)
                                <tr>
                                    <td class="ps-3">
                                        <strong class="text-dark">{{ $seq->store->name ?? 'N/A' }}</strong>
                                        <small class="d-block text-muted">{{ $seq->store->type ?? 'Warehouse' }}</small>
                                    </td>
                                    <td>
                                        @if($seq->slip_type === 'receive')
                                            <span class="badge bg-success bg-opacity-15 text-success border border-success border-opacity-25 px-2 py-1">
                                                <i class="fas fa-arrow-down me-1"></i>GRN
                                            </span>
                                        @else
                                            <span class="badge bg-info bg-opacity-15 text-info border border-info border-opacity-25 px-2 py-1">
                                                <i class="fas fa-arrow-up me-1"></i>SIN
                                            </span>
                                        @endif
                                    </td>
                                    <td class="fw-semibold text-dark">{{ $seq->label }}</td>
                                    <td><code>{{ $seq->prefix ?: '(none)' }}</code></td>
                                    <td>
                                        <span class="font-monospace fw-bold text-secondary">#{{ $seq->book_start_no }}</span>
                                        <span class="text-muted mx-1">→</span>
                                        <span class="font-monospace fw-bold text-secondary">#{{ $seq->book_end_no }}</span>
                                    </td>
                                    <td>
                                        <span class="badge bg-secondary font-monospace">{{ $seq->getNextSlipNumber() }}</span>
                                    </td>
                                    <td>
                                        <span class="badge bg-secondary text-white px-2.5 py-1">
                                            <i class="fas fa-pause me-1"></i>Inactive
                                        </span>
                                    </td>
                                    <td class="text-end pe-3">
                                        <div class="d-inline-flex gap-1">
                                            <a href="{{ route('store-manager.slip-sequences.edit', $seq) }}" class="btn btn-sm btn-outline-primary shadow-xs" title="Edit">
                                                <i class="fas fa-edit me-1"></i>Edit
                                            </a>
                                            <form action="{{ route('store-manager.slip-sequences.reactivate', $seq) }}" method="POST" class="d-inline">
                                                @csrf
                                                <button type="submit" class="btn btn-sm btn-outline-success shadow-xs" title="Reactivate Sequence Book">
                                                    <i class="fas fa-play me-1"></i>Reactivate
                                                </button>
                                            </form>
                                            @if($seq->used_count == 0)
                                            <form action="{{ route('store-manager.slip-sequences.destroy', $seq) }}" method="POST" class="d-inline" onsubmit="return confirm('Delete unused sequence book #{{ $seq->book_start_no }}–#{{ $seq->book_end_no }}?');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-sm btn-outline-danger shadow-xs" title="Delete Unused Book">
                                                    <i class="fas fa-trash-alt"></i>
                                                </button>
                                            </form>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="8" class="text-center py-5 text-muted">
                                        No inactive sequence books found.
                                    </td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        {{-- 4. ALL SEQUENCES PANE --}}
        <div class="tab-pane fade {{ $activeTab === 'all' ? 'show active' : '' }}" id="pane-all" role="tabpanel">
            <div class="card shadow-sm border-0 rounded-3">
                <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div class="d-flex align-items-center gap-2">
                        <span class="p-1.5 rounded bg-primary bg-opacity-10 text-primary"><i class="fas fa-list"></i></span>
                        <h6 class="mb-0 fw-bold text-dark">All Slip Sequences</h6>
                        <span class="badge bg-primary rounded-pill">{{ $totalCount }}</span>
                    </div>
                    <small class="text-muted">Master table across active, full, and inactive books</small>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-3">Store</th>
                                    <th>Type</th>
                                    <th>Label</th>
                                    <th>Prefix</th>
                                    <th>Book Range</th>
                                    <th>Next Slip</th>
                                    <th>Progress</th>
                                    <th>Status</th>
                                    <th class="text-end pe-3">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($sequences as $seq)
                                <tr>
                                    <td class="ps-3">
                                        <strong>{{ $seq->store->name ?? 'N/A' }}</strong>
                                        <small class="d-block text-muted">{{ $seq->store->type ?? 'Warehouse' }}</small>
                                    </td>
                                    <td>
                                        @if($seq->slip_type === 'receive')
                                            <span class="badge bg-success"><i class="fas fa-arrow-down me-1"></i>GRN</span>
                                        @else
                                            <span class="badge bg-info"><i class="fas fa-arrow-up me-1"></i>SIN</span>
                                        @endif
                                    </td>
                                    <td>{{ $seq->label }}</td>
                                    <td><code>{{ $seq->prefix ?: '(none)' }}</code></td>
                                    <td>
                                        <span class="font-monospace fw-bold">#{{ $seq->book_start_no }}</span> - <span class="font-monospace fw-bold">#{{ $seq->book_end_no }}</span>
                                    </td>
                                    <td>
                                        @if($seq->status === 'full')
                                            <span class="badge bg-danger fs-6">Full / End</span>
                                        @else
                                            <div class="badge bg-primary fs-6 font-monospace">{{ $seq->getNextSlipNumber() }}</div>
                                        @endif
                                    </td>
                                    <td>
                                        @php
                                            $capacity = max(1, (int) $seq->book_end_no - (int) $seq->book_start_no + 1);
                                            $pct = $seq->status === 'full' ? 100.0 : $seq->getPercentageUsed();
                                            $used = min($capacity, max((int) $seq->used_count, (int) round(($pct / 100) * $capacity)));
                                        @endphp
                                        <div class="small">
                                            <strong>{{ $used }} / {{ $capacity }}</strong>
                                            <div class="progress mt-1" style="height: 6px;">
                                                <div class="progress-bar {{ $pct >= 90 ? 'bg-danger' : ($pct >= 70 ? 'bg-warning' : 'bg-primary') }}" 
                                                     role="progressbar" 
                                                     style="width: {{ $pct }}%;" 
                                                     aria-valuenow="{{ $pct }}" aria-valuemin="0" aria-valuemax="100"></div>
                                            </div>
                                            <small class="text-muted">{{ $pct }}% used</small>
                                        </div>
                                    </td>
                                    <td>
                                        @if($seq->status === 'active')
                                            <span class="badge bg-success"><i class="fas fa-check me-1"></i>Active</span>
                                        @elseif($seq->status === 'full')
                                            <span class="badge bg-danger"><i class="fas fa-check-double me-1"></i>Full</span>
                                        @else
                                            <span class="badge bg-secondary">Inactive</span>
                                        @endif
                                    </td>
                                    <td class="text-end pe-3">
                                        <div class="d-inline-flex gap-1">
                                            <a href="{{ route('store-manager.slip-sequences.edit', $seq) }}" class="btn btn-sm btn-outline-primary" title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            @if($seq->status === 'active')
                                            <form action="{{ route('store-manager.slip-sequences.deactivate', $seq) }}" method="POST" class="d-inline">
                                                @csrf
                                                <button type="submit" class="btn btn-sm btn-outline-warning" title="Deactivate">
                                                    <i class="fas fa-pause"></i>
                                                </button>
                                            </form>
                                            @elseif($seq->status !== 'full')
                                            <form action="{{ route('store-manager.slip-sequences.reactivate', $seq) }}" method="POST" class="d-inline">
                                                @csrf
                                                <button type="submit" class="btn btn-sm btn-outline-success" title="Reactivate">
                                                    <i class="fas fa-play"></i>
                                                </button>
                                            </form>
                                            @endif
                                            @if($seq->used_count == 0)
                                            <form action="{{ route('store-manager.slip-sequences.destroy', $seq) }}" method="POST" class="d-inline" onsubmit="return confirm('Delete sequence book #{{ $seq->book_start_no }}–#{{ $seq->book_end_no }}?');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete">
                                                    <i class="fas fa-trash-alt"></i>
                                                </button>
                                            </form>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="9" class="text-center py-4 text-muted">
                                        No slip sequences found.
                                    </td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
                @if(method_exists($sequences, 'hasPages') && $sequences->hasPages())
                <div class="card-footer bg-white">
                    {{ $sequences->links() }}
                </div>
                @endif
            </div>
        </div>

    </div>
</div>

<style>
.kpi-card {
    transition: transform 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
    cursor: pointer;
}
.kpi-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.1) !important;
}
.nav-pills .nav-link {
    border-radius: 50rem;
    padding: 0.45rem 1rem;
    color: #475569;
    background-color: #f1f5f9;
    border: 1px solid #e2e8f0;
}
.nav-pills .nav-link.active {
    background-color: #2563eb;
    color: #ffffff;
    border-color: #2563eb;
    box-shadow: 0 2px 4px rgba(37, 99, 235, 0.2);
}
</style>

<script>
function switchTab(tabName) {
    const btn = document.getElementById(`tab-${tabName}-btn`);
    if (btn) {
        const tabTrigger = new bootstrap.Tab(btn);
        tabTrigger.show();
        updateUrlTab(tabName);
    }
}

function updateUrlTab(tabName) {
    if (window.history && window.history.replaceState) {
        const url = new URL(window.location);
        url.searchParams.set('tab', tabName);
        window.history.replaceState({}, '', url);
    }
}
</script>
@endsection
