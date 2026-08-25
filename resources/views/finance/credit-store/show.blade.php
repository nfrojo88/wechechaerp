@extends('layouts.app')

@section('title', 'Credit Purchase Details - #' . ($ledger->pr_no ?? $ledger->id))

@section('content')
<div class="container-fluid py-3">
    <!-- Breadcrumb & Header -->
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-1 small">
                    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}" class="text-decoration-none">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('finance.credit-store.index') }}" class="text-decoration-none">Credit Store Ledger</a></li>
                    <li class="breadcrumb-item active fw-bold" aria-current="page">PR #{{ $ledger->pr_no ?? $ledger->id }}</li>
                </ol>
            </nav>
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <h1 class="h4 mb-0 fw-bold text-dark">
                    <i class="fa-solid fa-credit-card text-info me-1"></i>
                    Credit Purchase #{{ $ledger->pr_no ?? $ledger->id }}
                </h1>
                @if($ledger->status === 'fully_paid')
                    <span class="badge bg-success-subtle text-success border border-success px-3 py-1 rounded-pill">
                        <i class="fas fa-check-circle me-1"></i> Fully Paid & Liquidated
                    </span>
                @elseif($ledger->status === 'partially_paid')
                    <span class="badge bg-warning-subtle text-warning border border-warning px-3 py-1 rounded-pill">
                        <i class="fas fa-clock me-1"></i> Partially Paid
                    </span>
                @else
                    <span class="badge bg-danger-subtle text-danger border border-danger px-3 py-1 rounded-pill">
                        <i class="fas fa-exclamation-circle me-1"></i> Outstanding Liability
                    </span>
                @endif
            </div>
            <p class="text-muted small mb-0">Project: <strong>{{ $ledger->project?->name ?? '—' }}</strong> | Supplier: <strong>{{ $ledger->supplier_name ?: 'Credit Supplier' }}</strong></p>
        </div>

        <div class="d-flex gap-2">
            <a href="{{ route('finance.credit-store.index') }}" class="btn btn-outline-secondary btn-sm shadow-sm">
                <i class="fas fa-arrow-left me-1"></i> Back to Ledger
            </a>
            @if($ledger->purchase_request_id)
                <a href="{{ route('purchase-requests.show', $ledger->purchase_request_id) }}" class="btn btn-outline-primary btn-sm shadow-sm" target="_blank">
                    <i class="fas fa-external-link-alt me-1"></i> View Purchase Request
                </a>
            @endif
        </div>
    </div>

    <!-- Alert Messages -->
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show shadow-sm" role="alert">
            <i class="fas fa-check-circle me-1"></i> {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    @if($errors->any())
        <div class="alert alert-danger alert-dismissible fade show shadow-sm" role="alert">
            <i class="fas fa-exclamation-circle me-1"></i> <strong>Please resolve the following errors:</strong>
            <ul class="mb-0 mt-1 small">
                @foreach($errors->all() as $err)
                    <li>{{ $err }}</li>
                @endforeach
            </ul>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <!-- Balance Status Progress Bar -->
    @php
        $pctPaid = $ledger->credit_amount > 0 ? min(100, round(($ledger->paid_amount / $ledger->credit_amount) * 100, 1)) : 0;
    @endphp
    <div class="card border-0 shadow-sm rounded-3 mb-4">
        <div class="card-body p-3">
            <div class="row g-3 text-center align-items-center mb-3">
                <div class="col-12 col-md-4 border-end-md">
                    <span class="text-muted small text-uppercase fw-bold">Total Credit Amount</span>
                    <div class="fs-4 fw-bold text-dark mt-1">{{ number_format($ledger->credit_amount, 2) }} <small class="fs-6 text-muted">ETB</small></div>
                </div>
                <div class="col-12 col-md-4 border-end-md">
                    <span class="text-muted small text-uppercase fw-bold">Paid / Liquidated</span>
                    <div class="fs-4 fw-bold text-success mt-1">{{ number_format($ledger->paid_amount, 2) }} <small class="fs-6 text-muted">ETB</small></div>
                </div>
                <div class="col-12 col-md-4">
                    <span class="text-muted small text-uppercase fw-bold">Remaining Payable Balance</span>
                    <div class="fs-4 fw-bold {{ $ledger->remaining_amount > 0 ? 'text-danger' : 'text-muted' }} mt-1">
                        {{ number_format($ledger->remaining_amount, 2) }} <small class="fs-6 text-muted">ETB</small>
                    </div>
                </div>
            </div>

            <div class="progress" style="height: 12px; border-radius: 6px;">
                <div class="progress-bar bg-success progress-bar-striped progress-bar-animated" role="progressbar" style="width: {{ $pctPaid }}%;" aria-valuenow="{{ $pctPaid }}" aria-valuemin="0" aria-valuemax="100">
                    {{ $pctPaid }}%
                </div>
            </div>
            <div class="d-flex justify-content-between small text-muted mt-1 font-monospace" style="font-size: 0.75rem;">
                <span>0.00 ETB (0%)</span>
                <span>{{ $pctPaid }}% Paid</span>
                <span>{{ number_format($ledger->credit_amount, 2) }} ETB</span>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <!-- Left Side: Details & History -->
        <div class="col-12 col-lg-8">
            <!-- Material Items Card -->
            <div class="card border-0 shadow-sm rounded-3 mb-4">
                <div class="card-header bg-white py-3 px-3 border-bottom d-flex align-items-center justify-content-between">
                    <h6 class="mb-0 fw-bold text-dark"><i class="fas fa-boxes-stacked me-2 text-primary"></i> Procured Material Items</h6>
                    <span class="badge bg-light text-muted border">COA: {{ $ledger->coaAccount?->code ?? '5110' }} - {{ $ledger->coaAccount?->name ?? 'Cost Of Material By Credit' }}</span>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" style="font-size: 0.875rem;">
                        <thead class="bg-light text-muted small text-uppercase">
                            <tr>
                                <th class="ps-3">#</th>
                                <th>Item / Material</th>
                                <th class="text-center">Quantity</th>
                                <th class="text-end">Unit Price</th>
                                <th class="text-end pe-3">Line Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            @if($ledger->purchaseRequest && $ledger->purchaseRequest->items->count() > 0)
                                @foreach($ledger->purchaseRequest->items as $idx => $item)
                                    @php
                                        $uPrice = (float)($item->estimated_unit_price ?? $item->unit_price ?? 0);
                                        $lTotal = (float)$item->quantity * $uPrice;
                                    @endphp
                                    <tr>
                                        <td class="ps-3 text-muted">{{ $idx + 1 }}</td>
                                        <td>
                                            <div class="fw-bold text-dark">{{ $item->product?->name ?? $item->item_name ?? 'Material Item' }}</div>
                                            @if($item->product?->item_code)
                                                <small class="text-muted font-monospace">{{ $item->product->item_code }}</small>
                                            @endif
                                        </td>
                                        <td class="text-center fw-semibold">{{ number_format($item->quantity, 2) }} {{ $item->unit ?? $item->product?->unit }}</td>
                                        <td class="text-end font-monospace">{{ number_format($uPrice, 2) }} ETB</td>
                                        <td class="text-end pe-3 fw-bold font-monospace">{{ number_format($lTotal, 2) }} ETB</td>
                                    </tr>
                                @endforeach
                            @else
                                <tr>
                                    <td colspan="5" class="text-center py-3 text-muted">
                                        Direct Purchase Amount: <strong>{{ number_format($ledger->credit_amount, 2) }} ETB</strong>
                                    </td>
                                </tr>
                            @endif
                        </tbody>
                        <tfoot class="bg-light border-top">
                            <tr>
                                <th colspan="4" class="text-end ps-3 text-uppercase small text-muted">Total Credit Purchase Value:</th>
                                <th class="text-end pe-3 fw-bold text-dark font-monospace">{{ number_format($ledger->credit_amount, 2) }} ETB</th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>

            <!-- Payment & Receipt History -->
            <div class="card border-0 shadow-sm rounded-3">
                <div class="card-header bg-white py-3 px-3 border-bottom d-flex align-items-center justify-content-between">
                    <h6 class="mb-0 fw-bold text-dark"><i class="fas fa-receipt me-2 text-success"></i> Payment History & Uploaded Receipts</h6>
                    <span class="badge bg-success-subtle text-success border border-success">{{ $ledger->payments->count() }} Payment(s)</span>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" style="font-size: 0.875rem;">
                        <thead class="bg-light text-muted small text-uppercase">
                            <tr>
                                <th class="ps-3">Date</th>
                                <th class="text-end">Amount Paid</th>
                                <th>Method / Ref</th>
                                <th>Funding Account</th>
                                <th class="text-center">Receipt</th>
                                <th>Recorded By</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($ledger->payments as $pmt)
                                <tr>
                                    <td class="ps-3 fw-semibold">
                                        {{ $pmt->payment_date ? $pmt->payment_date->format('M d, Y') : $pmt->created_at->format('M d, Y') }}
                                    </td>
                                    <td class="text-end fw-bold text-success font-monospace">
                                        {{ number_format($pmt->amount, 2) }} ETB
                                    </td>
                                    <td>
                                        <span class="badge bg-light text-dark border text-uppercase" style="font-size: 0.75rem;">
                                            {{ str_replace('_', ' ', $pmt->payment_method) }}
                                        </span>
                                        @if($pmt->reference_no)
                                            <div class="small text-muted font-monospace mt-1">Ref: {{ $pmt->reference_no }}</div>
                                        @endif
                                    </td>
                                    <td>
                                        @if($pmt->bankAccount)
                                            <div class="small fw-semibold"><i class="fas fa-building-columns text-primary me-1"></i> {{ $pmt->bankAccount->bank_name }}</div>
                                            <div class="small text-muted font-monospace">{{ $pmt->bankAccount->account_number }}</div>
                                        @elseif($pmt->coaAccount)
                                            <div class="small fw-semibold"><i class="fas fa-wallet text-secondary me-1"></i> {{ $pmt->coaAccount->name }}</div>
                                            <div class="small text-muted font-monospace">{{ $pmt->coaAccount->code }}</div>
                                        @else
                                            <span class="text-muted small">Direct Cash / Bank</span>
                                        @endif
                                    </td>
                                    <td class="text-center">
                                        @if($pmt->receipt_path)
                                            <a href="{{ asset($pmt->receipt_path) }}" target="_blank" class="btn btn-sm btn-outline-success py-1 px-2 shadow-sm" title="View / Download Receipt">
                                                <i class="fas fa-paperclip me-1"></i> Receipt
                                            </a>
                                        @else
                                            <span class="text-muted small font-italic">No receipt</span>
                                        @endif
                                    </td>
                                    <td class="small text-muted">
                                        {{ $pmt->recordedByUser?->name ?? 'Finance Head' }}
                                        <div class="text-muted font-monospace" style="font-size: 0.7rem;">{{ $pmt->created_at->format('h:i A') }}</div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="text-center py-4 text-muted">
                                        <i class="fas fa-receipt fa-2x text-secondary opacity-25 mb-2"></i>
                                        <p class="mb-0 small">No payments recorded yet. Use the form on the right to record a payment and upload a receipt.</p>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Right Side: Record Payment Form & Guidance -->
        <div class="col-12 col-lg-4">
            <!-- Record Payment Form -->
            <div class="card border-0 shadow-sm rounded-3 mb-4 border-top border-4 {{ $ledger->remaining_amount > 0 ? 'border-primary' : 'border-success' }}">
                <div class="card-header bg-white py-3 px-3 border-bottom">
                    <h6 class="mb-0 fw-bold text-dark">
                        <i class="fas fa-money-bill-wave text-success me-2"></i>
                        @if($ledger->remaining_amount > 0)
                            Record Supplier Payment
                        @else
                            Credit Fully Settled
                        @endif
                    </h6>
                </div>
                <div class="card-body p-3">
                    @if($ledger->remaining_amount > 0)
                        <form action="{{ route('finance.credit-store.record-payment', $ledger) }}" method="POST" enctype="multipart/form-data">
                            @csrf

                            <div class="mb-3">
                                <label class="form-label small fw-bold text-uppercase text-muted">Payment Amount (ETB) <span class="text-danger">*</span></label>
                                <div class="input-group input-group-sm">
                                    <input type="number" step="0.01" min="0.01" max="{{ $ledger->remaining_amount }}" name="amount" class="form-control fw-bold" value="{{ number_format($ledger->remaining_amount, 2, '.', '') }}" required>
                                    <span class="input-group-text bg-light fw-semibold">ETB</span>
                                </div>
                                <div class="form-text small text-muted">Max payable: {{ number_format($ledger->remaining_amount, 2) }} ETB</div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label small fw-bold text-uppercase text-muted">Payment Date <span class="text-danger">*</span></label>
                                <input type="date" name="payment_date" class="form-control form-control-sm" value="{{ date('Y-m-d') }}" required>
                            </div>

                            <div class="mb-3">
                                <label class="form-label small fw-bold text-uppercase text-muted">Payment Method <span class="text-danger">*</span></label>
                                <select name="payment_method" class="form-select form-select-sm" required>
                                    <option value="bank_transfer" selected>Bank Transfer (Disbursement)</option>
                                    <option value="cash">Cash Payment</option>
                                    <option value="cheque">Cheque</option>
                                    <option value="other">Other Method</option>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label class="form-label small fw-bold text-uppercase text-muted">Funding Bank Account</label>
                                <select name="bank_account_id" class="form-select form-select-sm">
                                    <option value="">-- Select Bank Account (Optional) --</option>
                                    @foreach($bankAccounts as $bk)
                                        <option value="{{ $bk->id }}">{{ $bk->bank_name }} - {{ $bk->account_number }} (Bal: {{ number_format($bk->current_balance ?? 0, 2) }})</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="mb-3">
                                <label class="form-label small fw-bold text-uppercase text-muted">Funding Chart of Account (COA)</label>
                                <select name="coa_account_id" class="form-select form-select-sm">
                                    <option value="">-- Select COA Source --</option>
                                    @foreach($coaAccounts as $ca)
                                        <option value="{{ $ca->id }}">{{ $ca->code }} - {{ $ca->name }} (Bal: {{ number_format($ca->current_balance, 2) }})</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="mb-3">
                                <label class="form-label small fw-bold text-uppercase text-muted">Reference / Cheque / Transaction No</label>
                                <input type="text" name="reference_no" class="form-control form-control-sm" placeholder="e.g. TXN-984321 / CHQ-0012">
                            </div>

                            <div class="mb-3">
                                <label class="form-label small fw-bold text-uppercase text-muted">Upload Payment Receipt / Proof</label>
                                <input type="file" name="receipt_file" class="form-control form-control-sm" accept=".pdf,.jpg,.jpeg,.png,.webp">
                                <div class="form-text small text-muted">Upload scanned receipt, bank slip, or payment confirmation (PDF, JPG, PNG).</div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label small fw-bold text-uppercase text-muted">Payment Notes</label>
                                <textarea name="notes" class="form-control form-control-sm" rows="2" placeholder="Optional remarks on this payment..."></textarea>
                            </div>

                            <button type="submit" class="btn btn-success btn-sm w-100 fw-bold shadow-sm py-2">
                                <i class="fas fa-check-circle me-1"></i> Record Payment & Liquidate Ledger
                            </button>
                        </form>
                    @else
                        <div class="text-center py-4">
                            <div class="text-success mb-2">
                                <i class="fas fa-circle-check fa-3x"></i>
                            </div>
                            <h6 class="fw-bold text-dark">This Credit Purchase is 100% Paid</h6>
                            <p class="text-muted small mb-0">Total of <strong>{{ number_format($ledger->paid_amount, 2) }} ETB</strong> has been fully paid, recorded in double-entry journals, and posted into Company Expenses.</p>
                        </div>
                    @endif
                </div>
            </div>

            <!-- Accounting Guidance Box -->
            <div class="card border-0 shadow-sm rounded-3 bg-light">
                <div class="card-body p-3">
                    <h6 class="fw-bold text-dark mb-2 small text-uppercase"><i class="fas fa-circle-info text-info me-1"></i> Automated Accounting Impact</h6>
                    <ul class="small text-muted ps-3 mb-0" style="line-height: 1.6;">
                        <li><strong>Credit Procurement:</strong> Booked to <span class="badge bg-white text-dark border">COA 5110 - Cost Of Material By Credit</span>.</li>
                        <li><strong>Store Intake:</strong> Goods are received into store inventory immediately without cash delay.</li>
                        <li><strong>Payment Settlement:</strong> When you record a payment here, it automatically creates a journal entry:
                            <div class="mt-1 font-monospace bg-white p-2 rounded border" style="font-size: 0.75rem;">
                                <div><strong>Debit:</strong> COA 5110 (Cost Of Material)</div>
                                <div><strong>Credit:</strong> Bank / Cash Funding Account</div>
                            </div>
                        </li>
                        <li class="mt-1"><strong>Expenses:</strong> Automatically logged in <strong>Company Expenses</strong> for accurate financial reporting.</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
