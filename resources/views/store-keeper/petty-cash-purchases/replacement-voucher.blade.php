@extends('layouts.app')
@section('title', 'Voucher #' . $replenishment->request_no . ' - Petty Cash Replacement')

@section('content')
<div class="container-fluid px-4 py-3">

    {{-- Top Action Bar --}}
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4 d-print-none">
        <div class="d-flex align-items-center gap-3">
            <a href="{{ route('store-keeper.petty-cash-purchases.index') }}" class="btn btn-outline-secondary btn-sm shadow-sm">
                <i class="fa-solid fa-arrow-left me-1"></i> Back to Purchases
            </a>
            <div>
                <h4 class="fw-bold text-dark mb-0 font-monospace">{{ $replenishment->request_no }}</h4>
                <small class="text-muted">Petty Cash Replacement / Disbursement Voucher</small>
            </div>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a href="{{ route('store-keeper.petty-cash-purchases.create') }}" class="btn btn-success btn-sm shadow-sm px-3 fw-bold">
                <i class="fa-solid fa-cart-shopping me-1"></i> Buy Material with this Fund
            </a>
            <button type="button" class="btn btn-primary btn-sm shadow-sm px-3" onclick="window.print()">
                <i class="fa-solid fa-print me-1"></i> Print Voucher
            </button>
        </div>
    </div>

    {{-- Printable Voucher Card --}}
    <div class="card border shadow-sm rounded-4 bg-white mx-auto overflow-hidden" style="max-width: 900px;" id="printableVoucher">
        <div class="card-body p-4 p-md-5">

            {{-- Voucher Header --}}
            <div class="border-bottom pb-4 mb-4">
                <div class="row align-items-center">
                    <div class="col-8">
                        <div class="d-flex align-items-center gap-2 mb-1">
                            <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 px-2.5 py-1 text-uppercase fw-bold" style="font-size:0.75rem;">
                                Official ERP Cash Voucher
                            </span>
                            @if($replenishment->status === 'fulfilled')
                                <span class="badge bg-success text-white px-2 py-1"><i class="fa-solid fa-circle-check me-1"></i>Disbursed &amp; Credited</span>
                            @else
                                <span class="badge bg-warning text-dark px-2 py-1"><i class="fa-solid fa-clock me-1"></i>Pending Review</span>
                            @endif
                        </div>
                        <h3 class="fw-bold text-dark mb-1">PETTY CASH REPLACEMENT VOUCHER</h3>
                        <p class="text-muted small mb-0">
                            Spot fund disbursement to Store Keeper for immediate site material procurement.
                        </p>
                    </div>
                    <div class="col-4 text-end">
                        <small class="text-muted text-uppercase d-block fw-bold" style="font-size:0.7rem;">Voucher Number</small>
                        <h4 class="fw-bold font-monospace text-primary mb-1">{{ $replenishment->request_no }}</h4>
                        <small class="text-muted d-block font-monospace">
                            Date: {{ optional($replenishment->fulfilled_at ?? $replenishment->created_at)->format('d M, Y') }}
                        </small>
                    </div>
                </div>
            </div>

            {{-- Amount Highlight Banner --}}
            <div class="p-3 rounded-3 mb-4 d-flex flex-wrap justify-content-between align-items-center gap-3" style="background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%); border: 1px solid #86efac;">
                <div>
                    <small class="text-success text-uppercase fw-bold d-block" style="font-size: 0.72rem; letter-spacing: 0.05em;">Replacement Amount Disbursed</small>
                    <h2 class="fw-bold text-success mb-0 font-monospace">
                        ETB {{ number_format($replenishment->fulfilled_amount ?? $replenishment->requested_amount, 2) }}
                    </h2>
                </div>
                <div class="text-md-end">
                    <span class="badge bg-white text-dark border shadow-xs px-3 py-2 font-monospace">
                        <i class="fa-solid fa-money-bill-transfer text-success me-1"></i>{{ $replenishment->payment_method ?: 'Bank Transfer / Cash' }}
                    </span>
                    @if($replenishment->fulfillment_reference)
                        <small class="d-block text-muted font-monospace mt-1">Ref #: <strong>{{ $replenishment->fulfillment_reference }}</strong></small>
                    @endif
                </div>
            </div>

            {{-- Two Column Details --}}
            <div class="row g-4 mb-4">
                {{-- Left: Disbursing Info --}}
                <div class="col-md-6">
                    <div class="p-3 rounded-3 bg-light border h-100">
                        <h6 class="fw-bold text-dark mb-3 border-bottom pb-2">
                            <i class="fa-solid fa-building-columns text-primary me-2"></i>Disbursement Source
                        </h6>
                        <dl class="row mb-0 small">
                            <dt class="col-sm-5 text-muted">Source Account:</dt>
                            <dd class="col-sm-7 fw-semibold text-dark mb-2">
                                @if($replenishment->sourceCoa)
                                    [{{ $replenishment->sourceCoa->code }}] {{ $replenishment->sourceCoa->name }}
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </dd>

                            <dt class="col-sm-5 text-muted">Disbursed By:</dt>
                            <dd class="col-sm-7 fw-semibold text-dark mb-2">
                                {{ $replenishment->financeHead->name ?? auth()->user()->name ?? 'Finance Department' }}
                            </dd>

                            <dt class="col-sm-5 text-muted">Disbursement Date:</dt>
                            <dd class="col-sm-7 text-dark mb-2">
                                {{ optional($replenishment->fulfilled_at ?? $replenishment->created_at)->format('d F, Y (H:i)') }}
                            </dd>

                            <dt class="col-sm-5 text-muted">Payment Mode:</dt>
                            <dd class="col-sm-7 text-dark mb-0">
                                <span class="badge bg-light text-dark border">{{ $replenishment->payment_method ?: 'Direct Cash' }}</span>
                            </dd>
                        </dl>
                    </div>
                </div>

                {{-- Right: Destination / Store Keeper Info --}}
                <div class="col-md-6">
                    <div class="p-3 rounded-3 bg-light border h-100">
                        <h6 class="fw-bold text-dark mb-3 border-bottom pb-2">
                            <i class="fa-solid fa-warehouse text-success me-2"></i>Recipient &amp; Destination Fund
                        </h6>
                        <dl class="row mb-0 small">
                            <dt class="col-sm-5 text-muted">Destination Store:</dt>
                            <dd class="col-sm-7 fw-semibold text-dark mb-2">
                                {{ $replenishment->store->name ?? 'Site Store' }}
                                @if($replenishment->store?->code)
                                    <small class="text-muted font-monospace">({{ $replenishment->store->code }})</small>
                                @endif
                            </dd>

                            <dt class="col-sm-5 text-muted">Site Petty Cash Fund:</dt>
                            <dd class="col-sm-7 text-dark mb-2">
                                <strong class="text-primary">{{ $replenishment->chartOfAccount->name ?? 'Site Petty Cash' }}</strong>
                                <small class="text-muted font-monospace d-block">[{{ $replenishment->chartOfAccount->code ?? 'N/A' }}]</small>
                            </dd>

                            <dt class="col-sm-5 text-muted">Recipient Keeper:</dt>
                            <dd class="col-sm-7 fw-semibold text-dark mb-2">
                                {{ $replenishment->recipient_name ?: ($replenishment->requester->name ?? 'Store Keeper') }}
                            </dd>

                            <dt class="col-sm-5 text-muted">Keeper Phone:</dt>
                            <dd class="col-sm-7 text-dark mb-0 font-monospace">
                                {{ $replenishment->recipient_phone ?: ($replenishment->requester->phone ?? '—') }}
                            </dd>
                        </dl>
                    </div>
                </div>
            </div>

            {{-- Purpose / Description --}}
            <div class="mb-4">
                <h6 class="fw-bold text-dark mb-2"><i class="fa-solid fa-circle-info text-secondary me-2"></i>Disbursement Purpose &amp; Information</h6>
                <div class="p-3 bg-light rounded-3 border small text-dark">
                    {{ $replenishment->notes ?: 'Site Petty Cash replenishment for urgent store materials, consumables, and spot site procurements.' }}
                </div>
            </div>

            {{-- Financial Accounting Journal Entry Lines --}}
            @if($replenishment->journalEntry)
            <div class="mb-4">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h6 class="fw-bold text-dark mb-0">
                        <i class="fa-solid fa-scale-balanced text-primary me-2"></i>Double-Entry Journal Entry
                    </h6>
                    <span class="badge bg-light text-dark font-monospace border">
                        Entry #: {{ $replenishment->journalEntry->entry_no }}
                    </span>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm table-bordered align-middle mb-0 small">
                        <thead class="table-light">
                            <tr>
                                <th>Account Code &amp; Name</th>
                                <th>Description / Memo</th>
                                <th class="text-end" style="width: 130px;">Debit (ETB)</th>
                                <th class="text-end" style="width: 130px;">Credit (ETB)</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($replenishment->journalEntry->lines as $line)
                            <tr>
                                <td>
                                    <strong>[{{ $line->account->code ?? 'N/A' }}]</strong> {{ $line->account->name ?? 'Account' }}
                                </td>
                                <td class="text-muted">{{ $line->description }}</td>
                                <td class="text-end font-monospace {{ $line->side === 'debit' ? 'fw-bold text-success' : 'text-muted' }}">
                                    {{ $line->side === 'debit' ? number_format($line->amount, 2) : '—' }}
                                </td>
                                <td class="text-end font-monospace {{ $line->side === 'credit' ? 'fw-bold text-danger' : 'text-muted' }}">
                                    {{ $line->side === 'credit' ? number_format($line->amount, 2) : '—' }}
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
            @endif

            {{-- Attached Slip Preview (if any) --}}
            @if($replenishment->attachment_path)
            <div class="mb-4 d-print-none">
                <h6 class="fw-bold text-dark mb-2"><i class="fa-solid fa-paperclip text-secondary me-2"></i>Attached Bank Slip / Receipt</h6>
                <div class="p-3 bg-light rounded-3 border text-center">
                    @php
                        $ext = strtolower(pathinfo($replenishment->attachment_path, PATHINFO_EXTENSION));
                        $isImg = in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif']);
                    @endphp
                    @if($isImg)
                        <a href="{{ asset($replenishment->attachment_path) }}" target="_blank">
                            <img src="{{ asset($replenishment->attachment_path) }}" class="img-fluid rounded border shadow-xs" style="max-height: 250px; object-fit: contain;" alt="Receipt Slip">
                        </a>
                    @else
                        <a href="{{ asset($replenishment->attachment_path) }}" target="_blank" class="btn btn-outline-primary btn-sm">
                            <i class="fa-solid fa-file-pdf me-1"></i> Open Attached Document ({{ strtoupper($ext) }})
                        </a>
                    @endif
                    <small class="d-block text-muted mt-2 font-monospace">{{ basename($replenishment->attachment_path) }}</small>
                </div>
            </div>
            @endif

            {{-- Signature Block --}}
            <div class="pt-4 mt-4 border-top">
                <div class="row text-center">
                    <div class="col-4">
                        <div style="border-top: 1.5px solid #000; margin: 40px auto 5px auto; width: 80%;"></div>
                        <strong class="d-block small text-dark">Disbursed By</strong>
                        <small class="text-muted" style="font-size:0.75rem;">Finance Officer / Cashier</small>
                        <small class="d-block text-muted font-monospace" style="font-size:0.7rem;">{{ $replenishment->financeHead->name ?? auth()->user()->name }}</small>
                    </div>
                    <div class="col-4">
                        <div style="border-top: 1.5px solid #000; margin: 40px auto 5px auto; width: 80%;"></div>
                        <strong class="d-block small text-dark">Received By</strong>
                        <small class="text-muted" style="font-size:0.75rem;">Store Keeper / Custodian</small>
                        <small class="d-block text-muted font-monospace" style="font-size:0.7rem;">{{ $replenishment->recipient_name ?: ($replenishment->requester->name ?? 'Store Keeper') }}</small>
                    </div>
                    <div class="col-4">
                        <div style="border-top: 1.5px solid #000; margin: 40px auto 5px auto; width: 80%;"></div>
                        <strong class="d-block small text-dark">Approved By</strong>
                        <small class="text-muted" style="font-size:0.75rem;">Project Manager / Finance Head</small>
                        <small class="d-block text-muted font-monospace" style="font-size:0.7rem;">Verified</small>
                    </div>
                </div>
            </div>

        </div>
    </div>

</div>
@endsection
