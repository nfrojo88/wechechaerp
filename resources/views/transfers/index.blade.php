@extends('layouts.app')
@section('title', 'Transfers')
@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0"><i class="fas fa-exchange-alt me-2"></i>Store Transfers</h1>
        <a href="{{ route('transfers.create') }}" class="btn btn-primary"><i class="fas fa-plus me-1"></i>New Transfer</a>
    </div>
    @if(session('success'))<div class="alert alert-success alert-dismissible fade show">{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>@endif
    <div class="card shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-dark">
                        <tr><th>Transfer No</th><th>From</th><th>To</th><th>Requested By</th><th>Required Date</th><th>Status</th><th class="text-center">Action</th></tr>
                    </thead>
                    <tbody>
                        @forelse($transfers as $t)
                        <tr>
                            <td><strong>{{ $t->transfer_no }}</strong></td>
                            <td>{{ $t->fromStore->name ?? 'Main Store' }}</td>
                            <td>{{ $t->toStore->name ?? 'Workshop / Site' }}</td>
                            <td>{{ $t->requestedBy->name ?? 'Staff' }}</td>
                            <td>{{ optional($t->required_date)->format('d M Y') ?? '-' }}</td>
                            <td>
                                @php $colors = ['draft'=>'secondary','pending_approval'=>'warning','approved'=>'info','in_transit'=>'primary','completed'=>'success','rejected'=>'danger','cancelled'=>'dark']; @endphp
                                <span class="badge bg-{{ $colors[$t->status] ?? 'secondary' }}">{{ str_replace('_',' ',ucfirst($t->status)) }}</span>
                            </td>
                            <td class="text-center">
                                <a href="{{ route('transfers.show', $t) }}" class="btn btn-sm btn-outline-info"><i class="fas fa-eye"></i></a>
                            </td>
                        </tr>
                        @empty
                        <tr><td colspan="7" class="text-center py-4 text-muted">No transfers found.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card-footer">{{ $transfers->links() }}</div>
    </div>
</div>
@endsection
