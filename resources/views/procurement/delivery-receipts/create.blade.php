@extends('layouts.app')
@section('title', 'Log Delivery Receipt')

@section('content')
<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Log Delivery Receipt</h1>
        <a href="{{ route('delivery-receipts.index') }}" class="btn btn-sm btn-secondary shadow-sm">
            <i class="fas fa-arrow-left fa-sm text-white-50"></i> Back to List
        </a>
    </div>

    <!-- Step 1: Select PO -->
    <div class="card shadow mb-4">
        <div class="card-body">
            <form action="{{ route('delivery-receipts.create') }}" method="GET" class="form-inline">
                <label class="mr-2">Select Purchase Order:</label>
                <select name="po_id" class="form-control mr-2" required onchange="this.form.submit()">
                    <option value="">-- Choose PO --</option>
                    @foreach($pos as $po)
                        <option value="{{ $po->id }}" {{ request('po_id') == $po->id ? 'selected' : '' }}>
                            {{ $po->po_no }} - {{ $po->supplier->name ?? 'Unknown Supplier' }}
                        </option>
                    @endforeach
                </select>
                <noscript><button type="submit" class="btn btn-primary">Load</button></noscript>
            </form>
        </div>
    </div>

    @if(request('po_id'))
    <!-- Step 2: Receive Items -->
    @php
        $selectedPo = \App\Models\PurchaseOrder::with('items.product')->find(request('po_id'));
    @endphp
    
    @if($selectedPo)
    <form action="{{ route('delivery-receipts.store') }}" method="POST" enctype="multipart/form-data" onsubmit="const fileInput = this.querySelector('input[name=\'slip_receipt\']'); if (!fileInput || !fileInput.files || fileInput.files.length === 0) { alert('Slip Upload Required: Physical receiving slip upload is strictly required. Material intake cannot be accepted without uploading the physical receiving slip (Model 19 / GRN).'); fileInput.focus(); return false; } const btn = this.querySelector('button[type=submit]'); if (btn) { btn.disabled = true; btn.innerHTML = '<i class=\'fas fa-spinner fa-spin me-1\'></i> Processing Intake...'; }">
        @csrf
        <input type="hidden" name="purchase_order_id" value="{{ $selectedPo->id }}">
        
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Receipt Details</h6>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label>Receive To Store <span class="text-danger">*</span></label>
                        <select name="store_id" class="form-control" required>
                            <option value="">-- Select Store --</option>
                            @foreach($stores as $store)
                                <option value="{{ $store->id }}">{{ $store->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label>Received Date <span class="text-danger">*</span></label>
                        <input type="date" name="received_date" class="form-control" value="{{ date('Y-m-d') }}" required>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label>Challan / Invoice No</label>
                        <input type="text" name="challan_no" class="form-control">
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label>Vehicle No</label>
                        <input type="text" name="vehicle_no" class="form-control">
                    </div>
                    <div class="col-md-8 mb-3">
                        <label>Notes</label>
                        <input type="text" name="notes" class="form-control">
                    </div>
                </div>
            </div>
        </div>

        <!-- Mandatory Slip Receipt Upload Card -->
        <div class="card shadow mb-4 border-left-danger">
            <div class="card-header py-3 d-flex justify-content-between align-items-center bg-white">
                <h6 class="m-0 font-weight-bold text-danger">
                    <i class="fas fa-file-invoice me-1"></i> Physical Receiving Slip Document (Model 19 / GRN) <span class="text-danger">*</span>
                </h6>
                <span class="badge badge-danger text-white px-2 py-1">STRICTLY REQUIRED</span>
            </div>
            <div class="card-body">
                <div class="alert alert-warning py-2 px-3 small border-left-warning mb-3">
                    <i class="fas fa-exclamation-triangle text-warning me-1"></i>
                    <strong>Intake cannot be accepted without slip:</strong> You must attach a clear photo or PDF scan of the physical receiving slip (Model 19 / GRN) signed by the store keeper before completing intake. Submissions without this slip will not be accepted.
                </div>
                <div class="form-group mb-0">
                    <label class="font-weight-bold text-dark small text-uppercase">Upload Slip Receipt / Scan / Photo <span class="text-danger">*</span></label>
                    <input type="file" name="slip_receipt" class="form-control-file @error('slip_receipt') is-invalid @enderror" accept=".pdf,.jpg,.jpeg,.png,.webp" required>
                    <small class="text-muted d-block mt-1">Supported formats: PDF, JPG, JPEG, PNG, WEBP (Max 10MB)</small>
                    @error('slip_receipt')
                        <div class="text-danger small font-weight-bold mt-1">{{ $message }}</div>
                    @enderror
                </div>
            </div>
        </div>

        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Items Received</h6>
            </div>
            <div class="card-body p-0">
                <table class="table table-bordered mb-0">
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th>Ordered Qty</th>
                            <th>Qty Received <span class="text-danger">*</span></th>
                            <th>Qty Accepted <span class="text-danger">*</span></th>
                            <th>Rejection Reason</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($selectedPo->items as $index => $item)
                        <tr>
                            <td>
                                {{ $item->product->name }}
                                <input type="hidden" name="items[{{$index}}][product_id]" value="{{ $item->product_id }}">
                                <input type="hidden" name="items[{{$index}}][po_item_id]" value="{{ $item->id }}">
                                <input type="hidden" name="items[{{$index}}][unit]" value="{{ $item->unit }}">
                                <input type="hidden" name="items[{{$index}}][unit_price]" value="{{ $item->unit_price }}">
                            </td>
                            <td>{{ $item->quantity }} {{ $item->unit }}</td>
                            <td>
                                <input type="number" name="items[{{$index}}][quantity_received]" class="form-control" step="0.01" min="0" required onchange="document.getElementById('acc_{{$index}}').value = this.value">
                            </td>
                            <td>
                                <input type="number" id="acc_{{$index}}" name="items[{{$index}}][accepted_quantity]" class="form-control" step="0.01" min="0" required>
                            </td>
                            <td>
                                <input type="text" name="items[{{$index}}][rejection_reason]" class="form-control" placeholder="If qty accepted < received">
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="card-footer text-right">
                <button type="submit" class="btn btn-success"><i class="fas fa-boxes"></i> Log Delivery & Update Inventory</button>
            </div>
        </div>
    </form>
    @endif
    @endif
</div>
@endsection
