@extends('layouts.app')

@section('title', 'Daily Consumption Details — ' . $materialUsage->usage_no)

@section('content')
<div class="container-fluid py-3">

    {{-- Breadcrumb & Top Bar --}}
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
        <div>
            <div class="d-flex align-items-center gap-2 mb-1">
                <a href="{{ route('material-usages.index') }}" class="text-decoration-none text-secondary small">
                    <i class="fa-solid fa-boxes-packing me-1"></i>Daily Consumptions
                </a>
                <span class="text-muted">/</span>
                <span class="text-dark small fw-bold">{{ $materialUsage->usage_no }}</span>
            </div>
            <h1 class="h3 fw-bold text-dark mb-0 font-monospace">
                {{ $materialUsage->usage_no }}
            </h1>
        </div>

        <div class="d-flex gap-2">
            <a href="{{ route('material-usages.index') }}" class="btn btn-outline-secondary btn-sm rounded-pill px-3">
                <i class="fa-solid fa-arrow-left me-1"></i> Back to List
            </a>
            <a href="{{ route('material-usages.print', $materialUsage) }}" target="_blank" class="btn btn-outline-primary btn-sm rounded-pill px-3 shadow-xs">
                <i class="fa-solid fa-print me-1"></i> Print Slip (SIV)
            </a>
            @if($materialUsage->status === 'draft')
                <form action="{{ route('material-usages.confirm', $materialUsage) }}" method="POST" class="d-inline" onsubmit="return confirm('Confirm and deduct store inventory stock now?')">
                    @csrf
                    <button type="submit" class="btn btn-success btn-sm rounded-pill px-3 fw-bold shadow-xs">
                        <i class="fa-solid fa-check-double me-1"></i> Confirm &amp; Deduct Stock
                    </button>
                </form>
            @endif
        </div>
    </div>

    {{-- Flash Alerts --}}
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm mb-4" role="alert">
            <i class="fa-solid fa-circle-check me-2"></i>{{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm mb-4" role="alert">
            <i class="fa-solid fa-triangle-exclamation me-2"></i>{{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    {{-- Overview Card --}}
    <div class="row g-3 mb-4">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm rounded-4 h-100 overflow-hidden">
                <div class="card-header bg-light py-3 px-4 border-bottom d-flex justify-content-between align-items-center">
                    <strong class="text-dark"><i class="fa-solid fa-info-circle text-primary me-2"></i>Consumption Log Overview</strong>
                    @if($materialUsage->status === 'confirmed')
                        <span class="badge bg-success-subtle text-success border border-success-subtle px-3 py-1">
                            <i class="fa-solid fa-circle-check me-1"></i>Confirmed &amp; Stock Deducted
                        </span>
                    @else
                        <span class="badge bg-warning-subtle text-warning border border-warning-subtle px-3 py-1">
                            <i class="fa-solid fa-hourglass-half me-1"></i>Draft
                        </span>
                    @endif
                </div>
                <div class="card-body p-4">
                    <div class="row g-3">
                        <div class="col-sm-6">
                            <span class="text-muted small d-block">Issuing Store:</span>
                            <div class="fw-bold text-dark fs-6">
                                <i class="fa-solid fa-warehouse text-info me-1"></i>{{ $materialUsage->store->name ?? 'Store' }}
                            </div>
                            <small class="text-muted">Code: {{ $materialUsage->store->code ?? 'N/A' }}</small>
                        </div>

                        <div class="col-sm-6">
                            <span class="text-muted small d-block">Project Site:</span>
                            <div class="fw-bold text-dark fs-6">
                                <i class="fa-solid fa-building text-primary me-1"></i>{{ $materialUsage->project->name ?? 'Project' }}
                            </div>
                            <small class="text-muted">Code: {{ $materialUsage->project->code ?? 'N/A' }}</small>
                        </div>

                        <div class="col-sm-6">
                            <span class="text-muted small d-block">Date of Consumption:</span>
                            <div class="fw-semibold text-dark">
                                <i class="fa-regular fa-calendar me-1"></i>{{ optional($materialUsage->usage_date)->format('l, d F Y') }}
                            </div>
                        </div>

                        <div class="col-sm-6">
                            <span class="text-muted small d-block">Received / Consumed By:</span>
                            <div class="fw-bold text-dark">
                                <i class="fa-solid fa-user-tag text-success me-1"></i>{{ $materialUsage->consumed_by_name ?? 'Site Staff' }}
                            </div>
                        </div>

                        @if($materialUsage->activity_type)
                        <div class="col-12">
                            <span class="text-muted small d-block">Activity / Site Area:</span>
                            <div class="fw-semibold text-dark bg-light p-2 rounded border">
                                <i class="fa-solid fa-trowel-bricks text-warning me-1"></i>{{ $materialUsage->activity_type }}
                            </div>
                        </div>
                        @endif

                        @if($materialUsage->description)
                        <div class="col-12">
                            <span class="text-muted small d-block">Description &amp; Notes:</span>
                            <div class="text-dark small bg-light p-2 rounded border">
                                {{ $materialUsage->description }}
                            </div>
                        </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card border-0 shadow-sm rounded-4 h-100 overflow-hidden">
                <div class="card-header bg-light py-3 px-4 border-bottom">
                    <strong class="text-dark"><i class="fa-solid fa-stamp text-success me-2"></i>Audit &amp; Verification</strong>
                </div>
                <div class="card-body p-4">
                    <ul class="list-unstyled mb-0">
                        <li class="mb-3 pb-3 border-bottom">
                            <span class="text-muted small d-block">Recorded By (Store Keeper):</span>
                            <strong class="text-dark">{{ $materialUsage->createdBy->name ?? 'Staff' }}</strong>
                            <small class="text-muted d-block">{{ optional($materialUsage->created_at)->format('d M Y, h:i A') }}</small>
                        </li>

                        <li class="mb-3 pb-3 border-bottom">
                            <span class="text-muted small d-block">External Ref / SIV Slip:</span>
                            <strong class="text-dark font-monospace">{{ $materialUsage->slip_number ?? 'None' }}</strong>
                        </li>

                        @if($materialUsage->confirmed_at)
                        <li>
                            <span class="text-muted small d-block">Stock Deduction Verified:</span>
                            <strong class="text-success"><i class="fa-solid fa-check me-1"></i>{{ optional($materialUsage->confirmed_at)->format('d M Y, h:i A') }}</strong>
                        </li>
                        @endif
                    </ul>
                </div>
            </div>
        </div>
    </div>

    {{-- Consumed Items Table --}}
    <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
        <div class="card-header bg-white py-3 px-4 border-bottom d-flex justify-content-between align-items-center">
            <h5 class="card-title fw-bold text-dark mb-0">
                <i class="fa-solid fa-cubes-stacked text-primary me-2"></i>Consumed Material Items ({{ $materialUsage->items->count() }})
            </h5>
            <span class="badge bg-light text-dark border px-3 py-1 font-monospace">
                Total Quantity: {{ number_format($materialUsage->total_quantity, 2) }}
            </span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light text-secondary small text-uppercase">
                        <tr>
                            <th class="ps-4">#</th>
                            <th>Material / Product Name</th>
                            <th>Item Code</th>
                            <th class="text-end">Quantity Consumed</th>
                            <th>Unit</th>
                            <th>Purpose / Remarks</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($materialUsage->items as $idx => $item)
                        <tr>
                            <td class="ps-4 text-muted small">{{ $idx + 1 }}</td>
                            <td>
                                <strong class="text-dark">{{ $item->product->name ?? 'Item' }}</strong>
                            </td>
                            <td>
                                <span class="badge bg-light text-dark border font-monospace">{{ $item->product->item_code ?? 'PRD' }}</span>
                            </td>
                            <td class="text-end fw-bold text-dark fs-6">
                                {{ number_format($item->effective_quantity, 3) }}
                            </td>
                            <td>
                                <span class="badge bg-info-subtle text-info border border-info-subtle">{{ $item->unit ?? ($item->product->unit ?? 'pcs') }}</span>
                            </td>
                            <td class="small text-muted">
                                {{ $item->remarks ?? ($item->notes ?? '—') }}
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div>
@endsection
