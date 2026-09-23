@extends('layouts.app')

@section('title', 'Receipt Analyzer')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <h1 class="h3 mb-0 fw-bold">
            <i class="fa-solid fa-receipt text-primary me-2"></i>Receipt Analyzer
        </h1>
        <p class="text-muted small mb-0">Upload and track receipts with automatic OCR parsing</p>
    </div>
    @if(auth()->user()->hasPermissionTo('manage-receipts'))
    <a href="{{ route('receipts.create') }}" class="btn btn-primary">
        <i class="fa-solid fa-upload me-1"></i> Upload Receipt
    </a>
    @endif
</div>

{{-- ── Stats Cards ─────────────────────────────────────────────────────── --}}
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="rounded-circle d-flex align-items-center justify-content-center"
                     style="width:42px;height:42px;background:rgba(99,102,241,0.12);">
                    <i class="fa-solid fa-receipt" style="color:#6366f1;"></i>
                </div>
                <div>
                    <div class="fs-4 fw-bold">{{ number_format($stats['total']) }}</div>
                    <div class="text-muted small">Total Receipts</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="rounded-circle d-flex align-items-center justify-content-center"
                     style="width:42px;height:42px;background:rgba(245,158,11,0.12);">
                    <i class="fa-solid fa-clock" style="color:#f59e0b;"></i>
                </div>
                <div>
                    <div class="fs-4 fw-bold">{{ number_format($stats['pending']) }}</div>
                    <div class="text-muted small">Pending</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="rounded-circle d-flex align-items-center justify-content-center"
                     style="width:42px;height:42px;background:rgba(16,185,129,0.12);">
                    <i class="fa-solid fa-circle-check" style="color:#10b981;"></i>
                </div>
                <div>
                    <div class="fs-4 fw-bold">{{ number_format($stats['approved']) }}</div>
                    <div class="text-muted small">Approved</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="rounded-circle d-flex align-items-center justify-content-center"
                     style="width:42px;height:42px;background:rgba(59,130,246,0.12);">
                    <i class="fa-solid fa-coins" style="color:#3b82f6;"></i>
                </div>
                <div>
                    <div class="fs-4 fw-bold">{{ number_format($stats['value'], 0) }}</div>
                    <div class="text-muted small">Approved Value (ETB)</div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- ── Filters ──────────────────────────────────────────────────────────── --}}
<div class="card border-0 shadow-sm mb-3">
    <div class="card-body py-2">
        <form method="GET" action="{{ route('receipts.index') }}" class="row g-2 align-items-end">
            <div class="col-sm-6 col-md-2">
                <label class="form-label form-label-sm text-muted mb-1">Status</label>
                <select name="status" id="filter-status" class="form-select form-select-sm">
                    <option value="">All Statuses</option>
                    <option value="pending"  @selected(request('status')=='pending')>Pending</option>
                    <option value="approved" @selected(request('status')=='approved')>Approved</option>
                    <option value="rejected" @selected(request('status')=='rejected')>Rejected</option>
                </select>
            </div>
            <div class="col-sm-6 col-md-2">
                <label class="form-label form-label-sm text-muted mb-1">Category</label>
                <select name="category" id="filter-category" class="form-select form-select-sm">
                    <option value="">All Categories</option>
                    @foreach($categories as $key => $label)
                    <option value="{{ $key }}" @selected(request('category')==$key)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-sm-6 col-md-2">
                <label class="form-label form-label-sm text-muted mb-1">Project</label>
                <select name="project_id" id="filter-project" class="form-select form-select-sm">
                    <option value="">All Projects</option>
                    @foreach($projects as $p)
                    <option value="{{ $p->id }}" @selected(request('project_id')==$p->id)>{{ $p->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-sm-6 col-md-2">
                <label class="form-label form-label-sm text-muted mb-1">From</label>
                <input type="date" name="date_from" id="filter-date-from" class="form-control form-control-sm" value="{{ request('date_from') }}">
            </div>
            <div class="col-sm-6 col-md-2">
                <label class="form-label form-label-sm text-muted mb-1">To</label>
                <input type="date" name="date_to" id="filter-date-to" class="form-control form-control-sm" value="{{ request('date_to') }}">
            </div>
            <div class="col-sm-6 col-md-2 d-flex gap-2">
                <button type="submit" id="btn-filter-receipts" class="btn btn-sm btn-primary flex-fill">
                    <i class="fa-solid fa-filter me-1"></i>Filter
                </button>
                @if(request()->hasAny(['status','category','project_id','date_from','date_to']))
                <a href="{{ route('receipts.index') }}" id="btn-clear-filter" class="btn btn-sm btn-outline-secondary">
                    <i class="fa-solid fa-xmark"></i>
                </a>
                @endif
            </div>
        </form>
    </div>
</div>

{{-- ── Table ────────────────────────────────────────────────────────────── --}}
<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th style="width:140px;">Receipt No.</th>
                        <th>Vendor</th>
                        <th>Project</th>
                        <th>Category</th>
                        <th>Date</th>
                        <th class="text-end">Amount (ETB)</th>
                        <th>OCR</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($receipts as $r)
                    <tr>
                        <td>
                            <a href="{{ route('receipts.show', $r) }}" class="fw-semibold text-decoration-none small">
                                {{ $r->receipt_number }}
                            </a>
                        </td>
                        <td class="small">{{ $r->vendor_name ?? '—' }}</td>
                        <td class="small text-muted">{{ $r->project->name ?? '—' }}</td>
                        <td>
                            <span class="badge bg-secondary bg-opacity-75 small">
                                {{ $categories[$r->category] ?? $r->category }}
                            </span>
                        </td>
                        <td class="small text-muted">
                            {{ $r->receipt_date ? $r->receipt_date->format('d M Y') : '—' }}
                        </td>
                        <td class="text-end fw-bold small">{{ number_format($r->total_amount, 2) }}</td>
                        <td>
                            @if($r->parse_status === 'parsed')
                                <span class="badge bg-success-subtle text-success small"><i class="fa-solid fa-check me-1"></i>Parsed</span>
                            @elseif($r->parse_status === 'failed')
                                <span class="badge bg-danger-subtle text-danger small"><i class="fa-solid fa-xmark me-1"></i>Failed</span>
                            @else
                                <span class="badge bg-secondary-subtle text-secondary small">Pending</span>
                            @endif
                        </td>
                        <td>
                            @php
                                $badge = match($r->status) {
                                    'approved' => 'success',
                                    'rejected' => 'danger',
                                    default    => 'warning',
                                };
                            @endphp
                            <span class="badge bg-{{ $badge }}">{{ ucfirst($r->status) }}</span>
                        </td>
                        <td class="text-end">
                            <a href="{{ route('receipts.show', $r) }}" class="btn btn-sm btn-outline-primary">
                                <i class="fa-solid fa-eye"></i>
                            </a>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="9" class="text-center py-5 text-muted">
                            <i class="fa-solid fa-receipt fa-3x mb-3 d-block opacity-25"></i>
                            No receipts found.
                            @if(auth()->user()->hasPermissionTo('manage-receipts'))
                                <br><a href="{{ route('receipts.create') }}" class="btn btn-sm btn-primary mt-2">Upload First Receipt</a>
                            @endif
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if($receipts->hasPages())
    <div class="card-footer bg-transparent">
        {{ $receipts->links() }}
    </div>
    @endif
</div>
@endsection
