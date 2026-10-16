@extends('layouts.app')
@section('title', 'Income Record #' . $income->income_no)
@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-1 text-gray-800 fw-bold">
                <i class="fas fa-file-invoice-dollar text-success me-2"></i>Income Record {{ $income->income_no }}
            </h1>
            <p class="text-muted small mb-0">Recorded on {{ $income->income_date ? $income->income_date->format('d M Y') : '—' }} &bull; <span class="badge bg-light text-dark border font-monospace">{{ \App\Services\EthiopianDate::formatDateEc($income->income_date) }}</span></p>
        </div>
        <div class="d-flex align-items-center gap-2">
            <a href="{{ route('income.index') }}" class="btn btn-outline-secondary rounded-pill px-3 shadow-sm">
                <i class="fas fa-arrow-left me-1"></i>Back to List
            </a>
            @if($income->status === 'draft')
                <form action="{{ route('income.confirm', $income) }}" method="POST" class="d-inline" onsubmit="return confirm('Confirm this income record?');">
                    @csrf
                    <button type="submit" class="btn btn-success rounded-pill px-4 shadow-sm fw-bold">
                        <i class="fas fa-check me-1"></i>Confirm Record
                    </button>
                </form>
            @endif
            <form action="{{ route('income.destroy', $income) }}" method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to DELETE income record #{{ $income->income_no }}?\n\nThis will permanently delete this income receipt, remove its 15% Output VAT, revert deposited balances, and delete all bank and ledger movements across all sections.');">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn btn-danger rounded-pill px-3 shadow-sm fw-bold">
                    <i class="fas fa-trash-alt me-1.5"></i>Delete Record
                </button>
            </form>
        </div>
    </div>

    <div class="row g-4">
        <!-- Main Income Details -->
        <div class="col-lg-8">
            <div class="card shadow-sm border-0 rounded-4 mb-4">
                <div class="card-header bg-white border-bottom py-3 px-4 d-flex justify-content-between align-items-center">
                    <h6 class="fw-bold text-dark mb-0"><i class="fa-solid fa-circle-info text-primary me-2"></i>General Overview</h6>
                    <span class="badge {{ $income->status === 'confirmed' ? 'bg-success' : 'bg-warning text-dark' }} px-3 py-1.5 rounded-pill">
                        {{ ucfirst($income->status) }}
                    </span>
                </div>
                <div class="card-body p-4">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <span class="text-muted small d-block">Income Division / Source</span>
                            <strong class="text-dark fs-6">{{ ucfirst($income->category) }}</strong>
                        </div>
                        <div class="col-md-6">
                            <span class="text-muted small d-block">Project / Site</span>
                            <strong class="text-dark fs-6">{{ $income->project->name ?? '—' }}</strong>
                        </div>
                        <div class="col-md-6">
                            <span class="text-muted small d-block">Deposited to Account (COA)</span>
                            <strong class="text-dark"><i class="fa-solid fa-building-columns text-primary me-1"></i>{{ $income->deposit_account_name }}</strong>
                        </div>
                        <div class="col-md-6">
                            <span class="text-muted small d-block">Payment Method</span>
                            <span class="text-dark text-capitalize">{{ str_replace('_', ' ', $income->payment_method) }}</span>
                        </div>
                        <div class="col-12">
                            <span class="text-muted small d-block">Description</span>
                            <p class="text-dark mb-0">{{ $income->description ?: 'No description provided.' }}</p>
                        </div>
                        @if(!empty($income->notes))
                        <div class="col-12">
                            <span class="text-muted small d-block">Notes</span>
                            <p class="text-muted mb-0 small">{{ $income->notes }}</p>
                        </div>
                        @endif
                    </div>
                </div>
            </div>

            <!-- ERCA eTax Sales Receipt Details -->
            <div class="card shadow-sm border-0 rounded-4 mb-4">
                <div class="card-header bg-white border-bottom py-3 px-4 d-flex justify-content-between align-items-center">
                    <h6 class="fw-bold text-primary mb-0"><i class="fa-solid fa-file-invoice me-2"></i>ERCA eTax Sales Receipt Specifications (13 Columns)</h6>
                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill px-2.5 py-1 small">Official eTax Format</span>
                </div>
                <div class="card-body p-4">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <span class="text-muted small d-block">Col A: Seller TIN</span>
                            <strong class="text-dark font-monospace">{{ $income->formatted_seller_tin }}</strong>
                            <small class="text-muted d-block" style="font-size: 0.75rem;">(Wechecha Construction)</small>
                        </div>
                        <div class="col-md-6">
                            <span class="text-muted small d-block">Col B: Buyer TIN</span>
                            <strong class="text-dark font-monospace">{{ $income->formatted_buyer_tin ?: '—' }}</strong>
                        </div>
                        <div class="col-md-6">
                            <span class="text-muted small d-block">Col C: Buyer Name</span>
                            <strong class="text-dark">{{ $income->buyer_name ?: ($income->project->client_name ?? 'Walk-in Customer') }}</strong>
                        </div>
                        <div class="col-md-6">
                            <span class="text-muted small d-block">Col D: Transaction</span>
                            <span class="badge bg-light text-dark border">{{ $income->transaction_type ?: 'Cash Sales' }}</span>
                        </div>
                        <div class="col-md-6">
                            <span class="text-muted small d-block">Col E: Receipt Category</span>
                            <span class="text-dark">{{ $income->receipt_category ?: 'Cash Sales Fiscal Receipt' }}</span>
                        </div>
                        <div class="col-md-6">
                            <span class="text-muted small d-block">Col F: Receipt Used</span>
                            <span class="text-dark">{{ $income->receipt_used ?: 'Fiscal Cash Register' }}</span>
                        </div>
                        <div class="col-md-6">
                            <span class="text-muted small d-block">Col G: MRC (Machine Code)</span>
                            <span class="font-monospace text-dark fw-bold">{{ $income->mrc_no ?: '—' }}</span>
                        </div>
                        <div class="col-md-6">
                            <span class="text-muted small d-block">Col H: Receipt No (FS#)</span>
                            <span class="badge bg-success bg-opacity-10 text-success border border-success-subtle font-monospace fw-bold fs-6">
                                {{ $income->formatted_receipt_no ?: '—' }}
                            </span>
                        </div>
                        <div class="col-md-4">
                            <span class="text-muted small d-block">Col I: Commodity Type</span>
                            <span class="text-dark">{{ $income->commodity_type ?: 'Services' }}</span>
                        </div>
                        <div class="col-md-4">
                            <span class="text-muted small d-block">Col J: Quantity</span>
                            <span class="text-dark font-monospace">{{ number_format($income->quantity ?: 1, 2) }}</span>
                        </div>
                        <div class="col-md-4">
                            <span class="text-muted small d-block">Col K: Price</span>
                            <span class="text-dark font-monospace fw-bold">ETB {{ number_format($income->unit_price ?: $income->amount, 2) }}</span>
                        </div>
                        <div class="col-md-6">
                            <span class="text-muted small d-block">Col L: Receipt Date (EC)</span>
                            <span class="text-dark font-monospace fw-bold">{{ \App\Services\EthiopianDate::formatDateEc($income->receipt_date ?: $income->income_date) }}</span>
                            <small class="text-muted d-block" style="font-size: 0.75rem;">(GC: {{ $income->receipt_date ? $income->receipt_date->format('d-m-Y') : ($income->income_date ? $income->income_date->format('d-m-Y') : '—') }})</small>
                        </div>
                        <div class="col-md-6">
                            <span class="text-muted small d-block">Col M: Encoded By</span>
                            <span class="badge bg-light text-secondary border font-monospace">{{ $income->encoded_by ?: 'wechacha' }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Sidebar / Financial Breakdown & Receipt Slip -->
        <div class="col-lg-4">
            <!-- Tax Breakdown -->
            @php
                $gross = (float)$income->amount;
                $vat = $gross * 0.15;
                $wht = $gross * 0.03;
                $net = $gross + $vat - $wht;
            @endphp
            <div class="card shadow-sm border-0 rounded-4 mb-4">
                <div class="card-header bg-white border-bottom py-3 px-4">
                    <h6 class="fw-bold text-dark mb-0"><i class="fa-solid fa-calculator text-success me-2"></i>Financial Summary</h6>
                </div>
                <div class="card-body p-4">
                    <div class="d-flex justify-content-between mb-2 pb-2 border-bottom">
                        <span class="text-muted">Gross Amount</span>
                        <strong class="text-dark font-monospace">ETB {{ number_format($gross, 2) }}</strong>
                    </div>
                    <div class="d-flex justify-content-between mb-2 pb-2 border-bottom">
                        <span class="text-muted">VAT (+15%)</span>
                        <strong class="text-success font-monospace">+ETB {{ number_format($vat, 2) }}</strong>
                    </div>
                    <div class="d-flex justify-content-between mb-2 pb-2 border-bottom">
                        <span class="text-muted">WHT (-3%)</span>
                        <strong class="text-danger font-monospace">-ETB {{ number_format($wht, 2) }}</strong>
                    </div>
                    <div class="d-flex justify-content-between pt-2">
                        <span class="fw-bold text-dark">Net Total</span>
                        <strong class="text-primary font-monospace fs-5">ETB {{ number_format($net, 2) }}</strong>
                    </div>
                </div>
            </div>

            <!-- Receipt Slip Preview -->
            <div class="card shadow-sm border-0 rounded-4 mb-4">
                <div class="card-header bg-white border-bottom py-3 px-4">
                    <h6 class="fw-bold text-dark mb-0"><i class="fa-solid fa-receipt text-primary me-2"></i>Physical Receipt Slip</h6>
                </div>
                <div class="card-body p-4 text-center">
                    @if(!empty($income->receipt_url))
                        <div class="mb-3">
                            <a href="{{ $income->receipt_url }}" target="_blank">
                                @if(preg_match('/\.(jpg|jpeg|png|webp)$/i', $income->receipt_file_path))
                                    <img src="{{ $income->receipt_url }}" alt="Receipt Slip" class="img-fluid rounded-3 shadow-xs border" style="max-height: 240px; object-fit: contain;">
                                @else
                                    <div class="p-4 bg-light rounded-3 border">
                                        <i class="fa-solid fa-file-pdf fs-1 text-danger mb-2"></i>
                                        <p class="small text-muted mb-0">PDF Receipt Document</p>
                                    </div>
                                @endif
                            </a>
                        </div>
                        <a href="{{ $income->receipt_url }}" target="_blank" class="btn btn-outline-primary btn-sm rounded-pill px-3 shadow-xs">
                            <i class="fa-solid fa-arrow-up-right-from-square me-1"></i>View Full Receipt
                        </a>
                    @else
                        <div class="py-4 text-muted">
                            <i class="fa-solid fa-file-circle-question fs-2 mb-2 d-block opacity-50"></i>
                            <span class="small">No physical receipt slip attached to this record.</span>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
