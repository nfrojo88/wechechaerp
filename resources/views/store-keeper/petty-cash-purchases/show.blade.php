@extends('layouts.app')
@section('title', 'Purchase #' . $purchase->purchase_no . ' - Petty Cash Buy')

@section('content')
@push('styles')
@include('layouts._store_mobile')
@endpush

<div class="container-fluid">

    {{-- ── Top Bar ───────────────────────────────────────────────────────────── --}}
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
        <div class="d-flex align-items-center gap-3">
            <a href="{{ route('store-keeper.petty-cash-purchases.index') }}" class="btn btn-outline-secondary btn-sm shadow-sm">
                <i class="fa-solid fa-arrow-left"></i>
            </a>
            <div>
                <div class="d-flex align-items-center gap-2 mb-1">
                    <h3 class="fw-bold text-dark mb-0 font-monospace">{{ $purchase->purchase_no }}</h3>
                    <span class="badge bg-success"><i class="fa-solid fa-circle-check me-1"></i>Completed &amp; Stock In</span>
                </div>
                <p class="text-muted small mb-0">
                    Petty cash direct material purchase recorded on {{ optional($purchase->purchase_date)->format('d M, Y') }} 
                    by <strong>{{ $purchase->purchaser->name ?? 'Store Keeper' }}</strong>.
                </p>
            </div>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a href="{{ route('store-keeper.petty-cash-purchases.create') }}" class="btn btn-outline-success btn-sm shadow-sm">
                <i class="fa-solid fa-plus me-1"></i> New Purchase
            </a>
            <button type="button" class="btn btn-primary btn-sm shadow-sm px-3" onclick="window.print()">
                <i class="fa-solid fa-print me-1"></i> Print Voucher
            </button>
        </div>
    </div>

    {{-- ── Status Ribbon ─────────────────────────────────────────────────────── --}}
    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="card border-0 shadow-sm rounded-3 p-3 bg-white border-start border-4 border-success">
                <div class="d-flex align-items-center gap-3">
                    <div class="p-2 rounded-circle bg-success bg-opacity-10 text-success">
                        <i class="fa-solid fa-boxes-stacked fa-lg"></i>
                    </div>
                    <div>
                        <small class="text-muted text-uppercase fw-bold d-block" style="font-size: 0.68rem;">Inventory Credited</small>
                        <strong class="text-dark small">{{ $purchase->store->name ?? 'Store' }}</strong>
                        <span class="badge bg-success bg-opacity-10 text-success ms-1 small">+{{ $purchase->items->count() }} Products</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card border-0 shadow-sm rounded-3 p-3 bg-white border-start border-4 border-primary">
                <div class="d-flex align-items-center gap-3">
                    <div class="p-2 rounded-circle bg-primary bg-opacity-10 text-primary">
                        <i class="fa-solid fa-receipt fa-lg"></i>
                    </div>
                    <div>
                        <small class="text-muted text-uppercase fw-bold d-block" style="font-size: 0.68rem;">Receive Slip (GRN)</small>
                        @if($purchase->deliveryReceipt)
                            <strong class="text-primary font-monospace small">{{ $purchase->deliveryReceipt->dr_no }}</strong>
                        @else
                            <strong class="text-dark small">Direct Intake</strong>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card border-0 shadow-sm rounded-3 p-3 bg-white border-start border-4 border-warning">
                <div class="d-flex align-items-center gap-3">
                    <div class="p-2 rounded-circle bg-warning bg-opacity-10 text-warning">
                        <i class="fa-solid fa-wallet fa-lg"></i>
                    </div>
                    <div>
                        <small class="text-muted text-uppercase fw-bold d-block" style="font-size: 0.68rem;">Petty Cash Deducted</small>
                        <strong class="text-dark small">{{ $purchase->chartOfAccount->name ?? 'Petty Cash' }}</strong>
                        <span class="badge bg-warning text-dark font-monospace ms-1 small">ETB {{ number_format($purchase->total_amount, 2) }}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ── Main Voucher Layout ──────────────────────────────────────────────── --}}
    <div class="row g-4 mb-4">

        {{-- Left: Items & Details --}}
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm rounded-3 mb-4 bg-white">
                <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center">
                    <h6 class="mb-0 fw-bold text-dark">
                        <i class="fa-solid fa-cart-flatbed text-success me-2"></i>Purchased Materials Summary
                    </h6>
                    <span class="badge bg-light text-dark border font-monospace">
                        Total: ETB {{ number_format($purchase->total_amount, 2) }}
                    </span>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-3">#</th>
                                <th>Material / Product</th>
                                <th>SKU / Code</th>
                                <th class="text-center">Quantity</th>
                                <th class="text-end">Unit Price</th>
                                <th class="text-end pe-3">Total Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($purchase->items as $idx => $item)
                            <tr>
                                <td class="ps-3 text-muted small">{{ $idx + 1 }}</td>
                                <td>
                                    <strong class="text-dark d-block">{{ $item->product->name ?? 'Product #'.$item->product_id }}</strong>
                                    @if($item->remarks)
                                        <small class="text-muted font-italic">{{ $item->remarks }}</small>
                                    @endif
                                </td>
                                <td>
                                    <span class="badge bg-light text-secondary font-monospace">{{ $item->product->sku ?? $item->product->code ?? '—' }}</span>
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-success bg-opacity-10 text-success fw-bold px-2 py-1">
                                        +{{ number_format($item->quantity, 2) }} {{ $item->unit }}
                                    </span>
                                </td>
                                <td class="text-end font-monospace">
                                    ETB {{ number_format($item->unit_price, 2) }}
                                </td>
                                <td class="text-end pe-3 font-monospace fw-bold text-dark">
                                    ETB {{ number_format($item->total_price, 2) }}
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                        <tfoot class="table-light">
                            <tr>
                                <th colspan="5" class="text-end ps-3">Grand Total Paid (Petty Cash):</th>
                                <th class="text-end pe-3 font-monospace text-success fs-6">
                                    ETB {{ number_format($purchase->total_amount, 2) }}
                                </th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>

            {{-- Financial Journal Entry Lines --}}
            @if($purchase->journalEntry)
            <div class="card border-0 shadow-sm rounded-3 bg-white">
                <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center">
                    <h6 class="mb-0 fw-bold text-dark">
                        <i class="fa-solid fa-scale-balanced text-primary me-2"></i>Financial Accounting Ledger
                    </h6>
                    <span class="badge bg-light text-dark font-monospace">{{ $purchase->journalEntry->entry_no }}</span>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-3">Account Code &amp; Name</th>
                                <th>Description</th>
                                <th class="text-end">Debit (ETB)</th>
                                <th class="text-end pe-3">Credit (ETB)</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($purchase->journalEntry->lines as $line)
                            <tr>
                                <td class="ps-3">
                                    <strong class="text-dark small">[{{ $line->account->code ?? 'N/A' }}] {{ $line->account->name ?? 'Account' }}</strong>
                                </td>
                                <td class="text-muted small">{{ $line->description }}</td>
                                <td class="text-end font-monospace">
                                    {{ $line->side === 'debit' ? number_format($line->amount, 2) : '—' }}
                                </td>
                                <td class="text-end pe-3 font-monospace text-danger">
                                    {{ $line->side === 'credit' ? number_format($line->amount, 2) : '—' }}
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
            @endif

        </div>

        {{-- Right: Meta Details & Receipt Attachment --}}
        <div class="col-lg-4">

            {{-- Metadata Card --}}
            <div class="card border-0 shadow-sm rounded-3 mb-4 bg-white">
                <div class="card-header bg-white border-bottom py-3">
                    <h6 class="mb-0 fw-bold text-dark">
                        <i class="fa-solid fa-info-circle text-info me-2"></i>Purchase Information
                    </h6>
                </div>
                <div class="card-body p-3">
                    <ul class="list-group list-group-flush small">
                        <li class="list-group-item d-flex justify-content-between px-0 py-2">
                            <span class="text-muted">Supplier / Merchant:</span>
                            <strong class="text-dark">{{ $purchase->supplier_name ?: 'Local Merchant' }}</strong>
                        </li>
                        <li class="list-group-item d-flex justify-content-between px-0 py-2">
                            <span class="text-muted">Cash Receipt / Voucher #:</span>
                            <strong class="font-monospace text-primary">{{ $purchase->receipt_no ?: '—' }}</strong>
                        </li>
                        <li class="list-group-item d-flex justify-content-between px-0 py-2">
                            <span class="text-muted">Purchase Date:</span>
                            <span class="text-dark">{{ optional($purchase->purchase_date)->format('d M, Y') }}</span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between px-0 py-2">
                            <span class="text-muted">Store Location:</span>
                            <span class="badge bg-light text-dark border">{{ $purchase->store->name ?? '—' }}</span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between px-0 py-2">
                            <span class="text-muted">Purchaser (Store Keeper):</span>
                            <strong class="text-dark">{{ $purchase->purchaser->name ?? 'Store Keeper' }}</strong>
                        </li>
                        <li class="list-group-item d-flex justify-content-between px-0 py-2">
                            <span class="text-muted">Petty Cash Fund:</span>
                            <span class="badge bg-warning text-dark font-monospace">{{ $purchase->chartOfAccount->name ?? 'Petty Cash' }}</span>
                        </li>
                        @if($purchase->notes)
                        <li class="list-group-item px-0 py-2">
                            <span class="text-muted d-block mb-1">Notes:</span>
                            <p class="mb-0 text-dark small bg-light p-2 rounded">{{ $purchase->notes }}</p>
                        </li>
                        @endif
                    </ul>
                </div>
            </div>

            {{-- Attached Receipt Preview --}}
            <div class="card border-0 shadow-sm rounded-3 bg-white">
                <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center">
                    <h6 class="mb-0 fw-bold text-dark">
                        <i class="fa-solid fa-paperclip text-secondary me-2"></i>Attached Receipt
                    </h6>
                    @if($purchase->attachment_path)
                        <a href="{{ asset($purchase->attachment_path) }}" target="_blank" class="btn btn-xs btn-outline-primary">
                            <i class="fa-solid fa-arrow-up-right-from-square me-1"></i>Open File
                        </a>
                    @endif
                </div>
                <div class="card-body p-3 text-center">
                    @if($purchase->attachment_path)
                        @php
                            $ext = strtolower(pathinfo($purchase->attachment_path, PATHINFO_EXTENSION));
                            $isImg = in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif']);
                        @endphp
                        @if($isImg)
                            <a href="{{ asset($purchase->attachment_path) }}" target="_blank">
                                <img src="{{ asset($purchase->attachment_path) }}" class="img-fluid rounded border shadow-xs" style="max-height: 250px; object-fit: contain;" alt="Receipt">
                            </a>
                            <small class="text-muted d-block mt-2 font-monospace">{{ basename($purchase->attachment_path) }}</small>
                        @else
                            <div class="p-4 bg-light rounded text-center">
                                <i class="fa-solid fa-file-pdf fa-3x text-danger mb-2"></i>
                                <p class="mb-0 small fw-bold">{{ basename($purchase->attachment_path) }}</p>
                                <a href="{{ asset($purchase->attachment_path) }}" target="_blank" class="btn btn-sm btn-outline-danger mt-2">
                                    <i class="fa-solid fa-download me-1"></i>Download PDF
                                </a>
                            </div>
                        @endif
                    @else
                        <div class="py-4 text-muted">
                            <i class="fa-solid fa-file-circle-xmark fa-2x mb-2 opacity-25"></i>
                            <p class="small mb-0">No physical receipt file attached to this purchase.</p>
                        </div>
                    @endif
                </div>
            </div>

        </div>

    </div>

</div>
@endsection

