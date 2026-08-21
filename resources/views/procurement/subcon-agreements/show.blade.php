@extends('layouts.app')
@section('title', 'Subcontractor Agreement Details')

@section('content')
<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Agreement: {{ $subconAgreement->agreement_no }}</h1>
        <div>
            <a href="{{ route('subcon-agreements.index') }}" class="btn btn-sm btn-secondary shadow-sm">
                <i class="fas fa-arrow-left fa-sm text-white-50"></i> Back
            </a>
            @if($subconAgreement->status == 'draft')
                <!-- You could add an approval form here -->
                <button class="btn btn-sm btn-success shadow-sm"><i class="fas fa-check"></i> Approve</button>
            @endif
        </div>
    </div>

    <div class="row">
        <div class="col-lg-4">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Overview</h6>
                </div>
                <div class="card-body">
                    <table class="table table-sm table-borderless">
                        <tr>
                            <th>Status:</th>
                            <td>
                                @if($subconAgreement->status == 'draft') <span class="badge badge-secondary">Draft</span>
                                @elseif($subconAgreement->status == 'active') <span class="badge badge-success">Active</span>
                                @elseif($subconAgreement->status == 'completed') <span class="badge badge-info">Completed</span>
                                @else <span class="badge badge-danger">{{ ucfirst($subconAgreement->status) }}</span> @endif
                            </td>
                        </tr>
                        <tr>
                            <th>Project:</th>
                            <td>{{ $subconAgreement->project?->name ?? 'N/A' }}</td>
                        </tr>
                        <tr>
                            <th>Subcontractor:</th>
                            <td>{{ $subconAgreement->subcontractor?->name ?? 'N/A' }}</td>
                        </tr>
                        <tr>
                            <th>Start Date:</th>
                            <td>{{ optional($subconAgreement->start_date)->format('M d, Y') ?? '-' }}</td>
                        </tr>
                        <tr>
                            <th>End Date:</th>
                            <td>{{ optional($subconAgreement->end_date)->format('M d, Y') ?? '-' }}</td>
                        </tr>
                        <tr>
                            <th>Total Value:</th>
                            <td><strong>${{ number_format($subconAgreement->total_amount, 2) }}</strong></td>
                        </tr>
                    </table>
                    <hr>
                    <strong>Work Description:</strong>
                    <p class="text-muted">{{ $subconAgreement->work_description }}</p>
                </div>
            </div>
        </div>

        <div class="col-lg-8">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Work Items (BOQ)</h6>
                </div>
                <div class="card-body p-0">
                    <table class="table table-striped mb-0">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Task Description</th>
                                <th>Quantity</th>
                                <th>Unit Rate</th>
                                <th>Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($subconAgreement->items as $index => $item)
                            <tr>
                                <td>{{ $index + 1 }}</td>
                                <td>{{ $item->task_description }}</td>
                                <td>{{ number_format($item->quantity, 2) }} {{ $item->unit }}</td>
                                <td>${{ number_format($item->unit_rate, 2) }}</td>
                                <td>${{ number_format($item->total_amount, 2) }}</td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="5" class="text-center">No items found.</td>
                            </tr>
                            @endforelse
                        </tbody>
                        <tfoot>
                            <tr>
                                <th colspan="4" class="text-right">Grand Total:</th>
                                <th>${{ number_format($subconAgreement->total_amount, 2) }}</th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
