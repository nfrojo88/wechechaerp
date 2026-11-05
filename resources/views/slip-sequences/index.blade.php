@extends('layouts.app')

@section('title', 'Slip Sequence Configuration')

@section('content')
<div class="container-fluid">
    <div class="row mb-4">
        <div class="col-md-6">
            <h4><i class="fas fa-stream me-2"></i>Slip Sequence Configuration</h4>
            <p class="text-muted">Manage GRN (Receiving) and SIN (Outgoing) slip sequences</p>
        </div>
        <div class="col-md-6 text-end">
            <form action="{{ route('store-manager.slip-sequences.sync-gaps') }}" method="POST" class="d-inline" onsubmit="return confirm('Check all currently added slips in Store Manager and Store Keeper sections and automatically fill any missing gaps?')">
                @csrf
                <button type="submit" class="btn btn-warning text-dark me-2 shadow-sm" title="Accept all added slips and automatically fill missing gaps">
                    <i class="fas fa-magic me-1"></i>Check & Auto-Fill Missing Slips
                </button>
            </form>
            <a href="{{ route('store-manager.slip-history.index') }}" class="btn btn-outline-primary me-2 shadow-sm">
                <i class="fas fa-history me-1"></i>View Complete Slip History
            </a>
            <a href="{{ route('store-manager.slip-sequences.create') }}" class="btn btn-primary shadow-sm">
                <i class="fas fa-plus me-1"></i>Configure New Sequence
            </a>
        </div>
    </div>

    <!-- Info Box -->
    <div class="alert alert-info border-start border-4" role="alert">
        <i class="fas fa-info-circle me-2"></i>
        <strong>How it works:</strong> Once configured, the system will enforce that every transaction's physical slip number matches the expected sequence. 
        Gaps are only allowed if you declare void/spoiled slips during entry.
    </div>

    <!-- Slips Overview -->
    <div class="row mb-4">
        <div class="col-md-6">
            <div class="card shadow-sm border-start border-4 border-success">
                <div class="card-body">
                    <h6 class="text-success"><i class="fas fa-arrow-down me-2"></i>Receiving (GRN)</h6>
                    <div class="fs-5 fw-bold">Good Received Note</div>
                    <small class="text-muted">assigned when delivery from supplier is recorded</small>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card shadow-sm border-start border-4 border-info">
                <div class="card-body">
                    <h6 class="text-info"><i class="fas fa-arrow-up me-2"></i>Outgoing (SIN)</h6>
                    <div class="fs-5 fw-bold">Store Issue Note</div>
                    <small class="text-muted">assigned when materials are transferred out to another site</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Sequences Table -->
    <div class="card shadow-sm">
        <div class="card-header bg-light">
            <h6 class="mb-0">Active & Inactive Sequences</h6>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Store</th>
                            <th>Type</th>
                            <th>Label</th>
                            <th>Prefix</th>
                            <th>Book Range</th>
                            <th>Next Slip</th>
                            <th>Progress</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($sequences as $seq)
                        <tr>
                            <td><strong>{{ $seq->store->name ?? 'N/A' }}</strong></td>
                            <td>
                                @if($seq->slip_type === 'receive')
                                <span class="badge bg-success"><i class="fas fa-arrow-down me-1"></i>GRN</span>
                                @else
                                <span class="badge bg-info"><i class="fas fa-arrow-up me-1"></i>SIN</span>
                                @endif
                            </td>
                            <td>{{ $seq->label }}</td>
                            <td><code>{{ $seq->prefix ?? '(none)' }}</code></td>
                            <td>
                                <div class="small">
                                    <strong>{{ $seq->book_start_no }}</strong> - <strong>{{ $seq->book_end_no }}</strong>
                                    <br><small class="text-muted">{{ $seq->book_end_no - $seq->book_start_no + 1 }} total</small>
                                </div>
                            </td>
                            <td>
                                @if($seq->status === 'full')
                                <span class="badge bg-secondary fs-6">Complete</span>
                                @else
                                <div class="badge bg-primary fs-6">{{ $seq->getNextSlipNumber() }}</div>
                                @endif
                            </td>
                            <td>
                                @php
                                    $capacity = (int) $seq->book_end_no - (int) $seq->book_start_no + 1;
                                    $pct = $seq->status === 'full' ? 100.0 : $seq->getPercentageUsed();
                                    $used = min($capacity, max((int) $seq->used_count, (int) round(($pct / 100) * $capacity)));
                                @endphp
                                <div class="small">
                                    <strong>{{ $used }} / {{ $capacity }}</strong>
                                    <div class="progress mt-2" style="height: 6px;">
                                        <div class="progress-bar {{ $pct >= 90 ? 'bg-danger' : ($pct >= 70 ? 'bg-warning' : 'bg-primary') }}" role="progressbar" 
                                             style="width: {{ $pct }}%;"
                                             aria-valuenow="{{ $pct }}" 
                                             aria-valuemin="0" aria-valuemax="100"></div>
                                    </div>
                                    <small class="text-muted">{{ $pct }}% used</small>
                                </div>
                            </td>
                            <td>
                                @if($seq->status === 'active')
                                <span class="badge bg-success"><i class="fas fa-check me-1"></i>Active</span>
                                @elseif($seq->status === 'full')
                                <span class="badge bg-danger"><i class="fas fa-exclamation me-1"></i>Full</span>
                                @else
                                <span class="badge bg-secondary">Inactive</span>
                                @endif
                            </td>
                            <td>
                                <a href="{{ route('store-manager.slip-sequences.edit', $seq) }}" class="btn btn-sm btn-outline-primary">
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
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="9" class="text-center py-4 text-muted">
                                No slip sequences configured. <a href="{{ route('store-manager.slip-sequences.create') }}">Create one now</a>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card-footer">
            {{ $sequences->links() }}
        </div>
    </div>
</div>
@endsection
