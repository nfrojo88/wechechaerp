@extends('layouts.app')
@section('title', 'Delivery Receipt Details')

@section('content')
<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Delivery Receipt: {{ $deliveryReceipt->dr_no }}</h1>
        <div>
            <a href="{{ route('delivery-receipts.index') }}" class="btn btn-sm btn-secondary shadow-sm">
                <i class="fas fa-arrow-left fa-sm text-white-50"></i> Back
            </a>
            <button class="btn btn-sm btn-primary shadow-sm"><i class="fas fa-print"></i> Print</button>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-4">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Receipt Information</h6>
                </div>
                <div class="card-body">
                    <table class="table table-sm table-borderless">
                        <tr>
                            <th>Status:</th>
                            <td>
                                <span class="badge badge-success">{{ ucfirst($deliveryReceipt->status) }}</span>
                            </td>
                        </tr>
                        <tr>
                            <th>Purchase Order:</th>
                            <td>{{ $deliveryReceipt->purchaseOrder->po_no ?? 'N/A' }}</td>
                        </tr>
                        <tr>
                            <th>Supplier:</th>
                            <td>{{ $deliveryReceipt->purchaseOrder->supplier->name ?? 'N/A' }}</td>
                        </tr>
                        <tr>
                            <th>Store Received:</th>
                            <td>{{ $deliveryReceipt->store->name ?? 'Unknown Store' }}</td>
                        </tr>
                        <tr>
                            <th>Received Date:</th>
                            <td>{{ $deliveryReceipt->received_date->format('M d, Y') }}</td>
                        </tr>
                        <tr>
                            <th>Received By:</th>
                            <td>{{ $deliveryReceipt->receivedBy->name ?? 'System' }}</td>
                        </tr>
                        <tr>
                            <th>Challan No:</th>
                            <td>{{ $deliveryReceipt->challan_no ?? '-' }}</td>
                        </tr>
                        <tr>
                            <th>Vehicle No:</th>
                            <td>{{ $deliveryReceipt->vehicle_no ?? '-' }}</td>
                        </tr>
                        @if($deliveryReceipt->slip_receipt_url)
                        <tr>
                            <th>Physical Slip:</th>
                            <td>
                                <a href="{{ $deliveryReceipt->slip_receipt_url }}" target="_blank" class="btn btn-sm btn-outline-primary py-0 px-2">
                                    <i class="fas fa-file-arrow-down me-1"></i> View Uploaded Slip
                                </a>
                            </td>
                        </tr>
                        @endif
                    </table>
                    <hr>
                    <strong>Notes:</strong>
                    <p class="text-muted">{{ $deliveryReceipt->notes ?? 'No additional notes.' }}</p>
                </div>
            </div>
        </div>

        <div class="col-lg-8">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Received Items</h6>
                </div>
                <div class="card-body p-0">
                    <table class="table table-striped mb-0">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Product</th>
                                <th>Qty Received</th>
                                <th>Qty Accepted</th>
                                <th>Qty Rejected</th>
                                <th>Rejection Reason</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($deliveryReceipt->items as $index => $item)
                            <tr>
                                <td>{{ $index + 1 }}</td>
                                <td>{{ $item->product->name ?? 'Unknown' }}</td>
                                <td>{{ number_format($item->quantity_received, 2) }} {{ $item->unit }}</td>
                                <td><strong class="text-success">{{ number_format($item->accepted_quantity, 2) }}</strong></td>
                                <td>
                                    @if($item->rejected_quantity > 0)
                                        <span class="text-danger">{{ number_format($item->rejected_quantity, 2) }}</span>
                                    @else
                                        0.00
                                    @endif
                                </td>
                                <td>{{ $item->rejection_reason ?? '-' }}</td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="6" class="text-center">No items found.</td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            
            <div class="alert alert-info shadow-sm">
                <i class="fas fa-info-circle mr-2"></i> Inventory for the accepted items has been automatically updated in the selected store.
            </div>
        </div>
    </div>
</div>
@endsection
