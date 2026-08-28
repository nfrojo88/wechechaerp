@extends('layouts.app')
@section('title', $account->name . ' - Petty Cash & Assigned Account')
@section('content')
<style>
.voucher-scroll-box {
    scrollbar-width: thin;
    scrollbar-color: #adb5bd #f8f9fa;
}
.voucher-scroll-box::-webkit-scrollbar {
    width: 8px;
    height: 8px;
}
.voucher-scroll-box::-webkit-scrollbar-track {
    background: #f8f9fa;
    border-radius: 4px;
}
.voucher-scroll-box::-webkit-scrollbar-thumb {
    background: #ced4da;
    border-radius: 4px;
}
.voucher-scroll-box::-webkit-scrollbar-thumb:hover {
    background: #adb5bd;
}
</style>
<div class="container-fluid py-3">

    <!-- Header -->
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
        <div>
            <div class="d-flex align-items-center gap-2 mb-1">
                <h1 class="h3 mb-0 text-dark fw-bold">
                    <i class="fas fa-wallet text-primary me-2"></i>{{ $account->name }}
                </h1>
                <span class="badge bg-dark rounded-pill px-3 py-2">Code: {{ $account->code }}</span>
                @if($account->manager)
                    <span class="badge bg-secondary-subtle text-secondary border px-2 py-1">
                        <i class="fas fa-user-tie me-1"></i> Custodian: {{ $account->manager->name }}
                    </span>
                @endif
            </div>
            <p class="text-muted small mb-0">Imprest Petty Cash & Account Management Portal • Track expenses, request fund replenishment from Finance Head, and maintain transaction ledger.</p>
        </div>
        <div class="d-flex flex-wrap gap-2 align-items-center">
            <a href="{{ route('assigned-accounts.index') }}" class="btn btn-light border shadow-sm rounded-pill px-3">
                <i class="fas fa-arrow-left me-1"></i> Back to Accounts
            </a>

            @if($pendingReplenishment)
                <button type="button" class="btn btn-warning text-dark shadow-sm rounded-pill px-3 fw-bold" data-bs-toggle="modal" data-bs-target="#viewReplenishmentModal_{{ $pendingReplenishment->id }}">
                    <i class="fas fa-clock me-1"></i> Request Pending (ETB {{ number_format($pendingReplenishment->requested_amount, 2) }})
                </button>
            @else
                <button type="button" class="btn btn-primary shadow-sm rounded-pill px-4 fw-bold" data-bs-toggle="modal" data-bs-target="#requestReplenishmentModal">
                    <i class="fas fa-hand-holding-dollar me-2"></i> Ask Money (Replenish Fund)
                </button>
            @endif
        </div>
    </div>

    <!-- Alert Notifications -->
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show rounded-4 shadow-sm border-0 d-flex align-items-center">
            <i class="fas fa-check-circle fa-lg me-2"></i>
            <div>{{ session('success') }}</div>
            <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
        </div>
    @endif
    @if(session('warning'))
        <div class="alert alert-warning alert-dismissible fade show rounded-4 shadow-sm border-0 d-flex align-items-center">
            <i class="fas fa-exclamation-triangle fa-lg me-2"></i>
            <div>{{ session('warning') }}</div>
            <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
        </div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show rounded-4 shadow-sm border-0 d-flex align-items-center">
            <i class="fas fa-times-circle fa-lg me-2"></i>
            <div>{{ session('error') }}</div>
            <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
        </div>
    @endif
    @if($errors->any())
        <div class="alert alert-danger alert-dismissible fade show rounded-4 shadow-sm border-0">
            <ul class="mb-0">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <!-- Metric Summary Cards -->
    <div class="row g-3 mb-4">
        <!-- 1. Current Balance Card -->
        <div class="col-md-4">
            <div class="card shadow-sm border-0 rounded-4 h-100 bg-white">
                <div class="card-body p-4 d-flex flex-column justify-content-between">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <span class="text-uppercase small fw-bold text-primary">Current Balance</span>
                            <h2 class="fw-bold text-dark display-6 mb-0 mt-1">ETB {{ number_format($account->current_balance, 2) }}</h2>
                        </div>
                        <div class="rounded-circle shadow-sm" style="width: 56px; height: 56px; min-width: 56px; display: flex; align-items: center; justify-content: center; background-color: #e0f2fe; color: #0284c7;">
                            <i class="fas fa-vault fa-2x"></i>
                        </div>
                    </div>
                    <div class="pt-3 mt-3 border-top d-flex justify-content-between align-items-center small text-muted">
                        <span>Account Type: <strong class="text-dark">{{ ucfirst($account->type) }}</strong></span>
                        <span class="badge bg-secondary-subtle text-secondary border">Code: {{ $account->code }}</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- 2. Current Cycle Expenses (Since Last Replenishment) -->
        <div class="col-md-4">
            <div class="card shadow-sm border-0 rounded-4 h-100 bg-white">
                <div class="card-body p-4 d-flex flex-column justify-content-between">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <span class="text-uppercase small fw-bold text-danger">Expenses Since Last Replenishment</span>
                            <h2 class="fw-bold text-danger display-6 mb-0 mt-1">ETB {{ number_format($unreplenishedExpensesTotal, 2) }}</h2>
                        </div>
                        <div class="rounded-circle shadow-sm" style="width: 56px; height: 56px; min-width: 56px; display: flex; align-items: center; justify-content: center; background-color: #fee2e2; color: #dc2626;">
                            <i class="fas fa-receipt fa-2x"></i>
                        </div>
                    </div>
                    <div class="pt-3 mt-3 border-top d-flex justify-content-between align-items-center small text-muted">
                        <span><i class="fas fa-list-check me-1"></i> {{ $unreplenishedCount }} payment(s) in active cycle</span>
                        @if($unreplenishedCount > 0 && !$pendingReplenishment)
                            <button type="button" class="btn btn-sm btn-outline-primary rounded-pill px-3" data-bs-toggle="modal" data-bs-target="#requestReplenishmentModal">
                                Send to Finance Head <i class="fas fa-arrow-right ms-1"></i>
                            </button>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <!-- 3. Last Fulfillment / Cycle Status Card -->
        <div class="col-md-4">
            <div class="card shadow-sm border-0 rounded-4 h-100 bg-white">
                <div class="card-body p-4 d-flex flex-column justify-content-between">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <span class="text-uppercase small fw-bold text-success">Latest Fulfillment Milestone</span>
                            @if($lastFulfilled)
                                <h3 class="fw-bold text-success mb-1 mt-1">ETB {{ number_format($lastFulfilled->fulfilled_amount, 2) }}</h3>
                                <p class="text-muted small mb-0">
                                    <i class="fas fa-calendar-check me-1 text-success"></i> Fulfilled on {{ $lastFulfilled->fulfilled_at->format('M d, Y') }}
                                </p>
                            @else
                                <h4 class="fw-bold text-secondary mb-1 mt-1">Initial Cycle</h4>
                                <p class="text-muted small mb-0">Tracking from fund initialization.</p>
                            @endif
                        </div>
                        <div class="rounded-circle shadow-sm" style="width: 56px; height: 56px; min-width: 56px; display: flex; align-items: center; justify-content: center; background-color: #dcfce7; color: #16a34a;">
                            <i class="fas fa-hand-holding-dollar fa-2x"></i>
                        </div>
                    </div>
                    <div class="pt-3 mt-3 border-top d-flex justify-content-between align-items-center small text-muted">
                        @if($lastFulfilled)
                            <span>Source: <strong class="text-dark">{{ $lastFulfilled->sourceCoa->name ?? 'Bank/Cash' }}</strong></span>
                            <span class="badge bg-light text-dark border">#{{ $lastFulfilled->request_no }}</span>
                        @else
                            <span>Next ask will track all expenses to date</span>
                            <span class="badge bg-secondary-subtle text-secondary">Cycle 1</span>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <!-- New Transaction Form -->
        <div class="col-lg-5">
            <div class="card shadow-sm border-0 rounded-4 h-100">
                <div class="card-header bg-white border-0 pt-4 px-4 pb-0 d-flex justify-content-between align-items-center">
                    <h5 class="fw-bold text-dark mb-0">
                        <i class="fas fa-money-bill-wave me-2 text-success"></i>New Transaction
                    </h5>
                    <span class="badge bg-light text-secondary border">Direct Ledger Entry</span>
                </div>
                <div class="card-body p-4">
                    <form method="POST" action="{{ route('assigned-accounts.pay', $account->id) }}">
                        @csrf
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label text-muted small fw-bold text-uppercase">Transaction Type</label>
                                <select name="type" class="form-select bg-light border-0 py-2 text-dark fw-semibold" required>
                                    <option value="payment" selected>Payment (Out / Expense)</option>
                                    <option value="receipt">Receipt (In / Cash In)</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label text-muted small fw-bold text-uppercase">Amount (ETB)</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light border-0 fw-bold text-dark">ETB</span>
                                    <input type="number" step="0.01" min="0.01" name="amount" class="form-control bg-light border-0 py-2 fw-bold text-dark" placeholder="0.00" required>
                                </div>
                            </div>
                            <div class="col-md-12">
                                <label class="form-label text-muted small fw-bold text-uppercase">Target Expense / Offsetting Account</label>
                                <select name="target_account_id" class="form-select bg-light border-0 py-2 text-dark" required>
                                    <option value="">Select target account (e.g. Office Supplies, Site Fuel, Repairs)...</option>
                                    @foreach($targetAccounts as $target)
                                        <option value="{{ $target->id }}">[{{ $target->code }}] {{ $target->name }} ({{ ucfirst($target->type) }})</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label text-muted small fw-bold text-uppercase">Reference / Receipt No</label>
                                <input type="text" name="reference" class="form-control bg-light border-0 py-2 text-dark" placeholder="e.g. REC-9921, Cash Voucher #12">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label text-muted small fw-bold text-uppercase">Date</label>
                                <input type="text" class="form-control bg-light border-0 py-2 text-dark fw-semibold" value="{{ now()->format('M d, Y') }}" readonly>
                            </div>
                            <div class="col-md-12">
                                <label class="form-label text-muted small fw-bold text-uppercase">Description / Purpose</label>
                                <textarea name="description" class="form-control bg-light border-0 text-dark" rows="3" placeholder="Explain the expense details (e.g., Purchased emergency plumbing fittings for block B, fuel for generator...)" required></textarea>
                            </div>
                            <div class="col-md-12 text-end pt-2">
                                <button type="submit" class="btn btn-success rounded-pill px-4 shadow-sm fw-bold">
                                    <i class="fas fa-check-circle me-2"></i> Record Transaction
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Current Cycle Payment History Overview (To be submitted in next Ask Money) -->
        <div class="col-lg-7">
            <div class="card shadow-sm border-0 rounded-4 h-100">
                <div class="card-header bg-white border-0 pt-4 px-4 pb-0 d-flex justify-content-between align-items-center">
                    <div>
                        <h5 class="fw-bold text-dark mb-0">
                            <i class="fas fa-hourglass-half text-warning me-2"></i>Active Cycle Payment History
                        </h5>
                        <p class="text-muted small mb-0">Payments recorded since last fulfillment ({{ $lastFulfilled ? $lastFulfilled->fulfilled_at->format('M d, Y') : 'start' }}). These will be attached when you Ask Money.</p>
                    </div>
                    @if($unreplenishedCount > 0 && !$pendingReplenishment)
                        <button type="button" class="btn btn-sm btn-primary rounded-pill px-3 shadow-sm" data-bs-toggle="modal" data-bs-target="#requestReplenishmentModal">
                            <i class="fas fa-paper-plane me-1"></i> Ask Money
                        </button>
                    @endif
                </div>
                <div class="card-body p-0 mt-3">
                    <div class="table-responsive" style="max-height: 340px; overflow-y: auto;">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="bg-light text-muted small text-uppercase sticky-top">
                                <tr>
                                    <th class="px-3 py-2">Date</th>
                                    <th class="py-2">Ref / Requester</th>
                                    <th class="py-2">Category & Description</th>
                                    <th class="px-3 py-2 text-end">Amount (ETB)</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($unreplenishedExpenses as $exp)
                                    <tr>
                                        <td class="px-3 py-2 text-muted small" style="white-space: nowrap;">
                                            {{ \Carbon\Carbon::parse($exp->date)->format('M d, Y') }}
                                            <div class="text-muted" style="font-size: 0.75rem;">{{ \Carbon\Carbon::parse($exp->date)->format('H:i') }}</div>
                                        </td>
                                        <td class="py-2">
                                            <span class="badge bg-light text-primary border fw-bold mb-1">{{ $exp->reference }}</span>
                                            <div class="fw-semibold text-dark small">{{ $exp->requester }}</div>
                                            @if($exp->department)
                                                <small class="text-muted">{{ $exp->department }}</small>
                                            @endif
                                        </td>
                                        <td class="py-2">
                                            @if($exp->category)
                                                <span class="badge bg-primary-subtle text-primary border mb-1">{{ $exp->category }}</span>
                                            @endif
                                            <div class="text-dark small">{{ Str::limit($exp->description, 45) }}</div>
                                        </td>
                                        <td class="px-3 py-2 text-end fw-bold text-danger" style="white-space: nowrap;">
                                            ETB {{ number_format($exp->amount, 2) }}
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="text-center py-5 text-muted">
                                            <i class="fas fa-check-circle text-success fa-2x mb-2"></i>
                                            <div>All expenses up to date! No un-replenished payments in the current cycle.</div>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="card-footer bg-light border-0 py-3 px-4 d-flex justify-content-between align-items-center rounded-bottom-4">
                    <span class="text-muted small fw-bold">Active Cycle Total ({{ $unreplenishedCount }} items):</span>
                    <span class="fw-bold fs-5 text-danger">ETB {{ number_format($unreplenishedExpensesTotal, 2) }}</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Replenishment Requests & Approvals Section -->
    <div class="card shadow-sm border-0 rounded-4 mb-4">
        <div class="card-header bg-white border-0 pt-4 px-4 pb-0 d-flex justify-content-between align-items-center">
            <div>
                <h5 class="fw-bold text-dark mb-0">
                    <i class="fas fa-file-invoice-dollar text-primary me-2"></i>Replenishment Requests & Finance Head Approvals
                </h5>
                <p class="text-muted small mb-0">Audit log of all fund replenishment cycles, attached payment histories, and fulfillment status.</p>
            </div>
            <div>
                @if(!$pendingReplenishment)
                    <button type="button" class="btn btn-outline-primary btn-sm rounded-pill px-3" data-bs-toggle="modal" data-bs-target="#requestReplenishmentModal">
                        <i class="fas fa-plus me-1"></i> New Request
                    </button>
                @endif
            </div>
        </div>
        <div class="card-body p-0 mt-3">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light text-muted small text-uppercase">
                        <tr>
                            <th class="px-4 py-3">Request No</th>
                            <th class="py-3">Requested By / Date</th>
                            <th class="py-3 text-end">Requested Amount</th>
                            <th class="py-3 text-center">Attached Expenses</th>
                            <th class="py-3">Status</th>
                            <th class="py-3">Fulfillment Details</th>
                            <th class="px-4 py-3 text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($replenishments as $rep)
                            <tr>
                                <td class="px-4">
                                    <span class="fw-bold text-primary">{{ $rep->request_no }}</span>
                                </td>
                                <td>
                                    <div class="fw-semibold text-dark">{{ $rep->requester->name ?? 'Custodian' }}</div>
                                    <small class="text-muted">{{ $rep->created_at->format('M d, Y H:i') }}</small>
                                </td>
                                <td class="text-end fw-bold fs-6 text-dark">
                                    ETB {{ number_format($rep->requested_amount, 2) }}
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-secondary-subtle text-secondary border rounded-pill px-3 py-1">
                                        <i class="fas fa-receipt me-1"></i> {{ $rep->items->count() }} items (ETB {{ number_format($rep->total_expenses_amount, 2) }})
                                    </span>
                                </td>
                                <td>
                                    @if($rep->status === 'fulfilled')
                                        <span class="badge bg-success-subtle text-success border border-success px-3 py-2 rounded-pill">
                                            <i class="fas fa-check-circle me-1"></i> Fulfilled
                                        </span>
                                    @elseif($rep->status === 'rejected')
                                        <span class="badge bg-danger-subtle text-danger border border-danger px-3 py-2 rounded-pill">
                                            <i class="fas fa-times-circle me-1"></i> Rejected
                                        </span>
                                    @else
                                        <span class="badge bg-warning-subtle text-warning-emphasis border border-warning px-3 py-2 rounded-pill">
                                            <i class="fas fa-hourglass-start me-1"></i> Pending Finance Review
                                        </span>
                                    @endif
                                </td>
                                <td>
                                    @if($rep->status === 'fulfilled')
                                        <div class="small fw-semibold text-success">ETB {{ number_format($rep->fulfilled_amount, 2) }} via {{ $rep->sourceCoa->name ?? 'Bank Account' }}</div>
                                        <small class="text-muted">By {{ $rep->financeHead->name ?? 'Finance Head' }} on {{ $rep->fulfilled_at?->format('M d, Y') }}</small>
                                    @elseif($rep->status === 'rejected')
                                        <small class="text-danger"><i class="fas fa-info-circle me-1"></i> {{ Str::limit($rep->rejection_reason, 40) }}</small>
                                    @else
                                        <span class="text-muted small">Awaiting Finance Head fulfillment</span>
                                    @endif
                                </td>
                                <td class="px-4 text-end">
                                    <div class="d-flex justify-content-end gap-1">
                                        <!-- View attached payment history button -->
                                        <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill px-2" title="View Attached Expenses History" data-bs-toggle="modal" data-bs-target="#viewReplenishmentModal_{{ $rep->id }}">
                                            <i class="fas fa-eye me-1"></i> View History
                                        </button>

                                        <!-- Finance Head Action Buttons -->
                                        @if($rep->status === 'pending' && $isFinanceHead)
                                            <button type="button" class="btn btn-sm btn-success rounded-pill px-3 shadow-sm fw-bold" data-bs-toggle="modal" data-bs-target="#fulfillModal_{{ $rep->id }}">
                                                <i class="fas fa-hand-holding-dollar me-1"></i> Fulfill Money
                                            </button>
                                            <button type="button" class="btn btn-sm btn-outline-danger rounded-pill px-2" title="Reject Request" data-bs-toggle="modal" data-bs-target="#rejectModal_{{ $rep->id }}">
                                                <i class="fas fa-times"></i>
                                            </button>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center py-5 text-muted">
                                    <i class="fas fa-receipt fa-2x mb-2 text-secondary"></i>
                                    <div>No replenishment requests yet. Click "Ask Money" to submit your first replenishment request.</div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if($replenishments->hasPages())
            <div class="card-footer bg-white border-0 pt-3">
                {{ $replenishments->links() }}
            </div>
        @endif
    </div>

    <!-- Full General Transaction Ledger History -->
    <div class="card shadow-sm border-0 rounded-4">
        <div class="card-header bg-white border-0 pt-4 px-4 pb-0 d-flex flex-wrap justify-content-between align-items-center gap-3">
            <div>
                <h5 class="fw-bold text-dark mb-0">
                    <i class="fas fa-history me-2 text-info"></i>Transaction Ledger History
                </h5>
                <p class="text-muted small mb-0">Complete historical journal entries and transactions for this account.</p>
            </div>
            
            <form method="GET" action="{{ route('assigned-accounts.show', $account->id) }}" class="d-flex flex-wrap gap-2">
                <input type="date" name="start_date" class="form-control form-control-sm bg-light border-0" value="{{ $startDate }}" placeholder="Start Date">
                <input type="date" name="end_date" class="form-control form-control-sm bg-light border-0" value="{{ $endDate }}" placeholder="End Date">
                <button type="submit" class="btn btn-sm btn-primary rounded-pill px-3 shadow-sm">
                    <i class="fas fa-filter me-1"></i> Filter
                </button>
                @if($startDate || $endDate)
                    <a href="{{ route('assigned-accounts.show', $account->id) }}" class="btn btn-sm btn-light border rounded-pill px-2">Reset</a>
                @endif
            </form>
        </div>
        <div class="card-body p-0 mt-3">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light text-muted small text-uppercase">
                        <tr>
                            <th class="px-4 py-3">Date</th>
                            <th class="py-3">Reference</th>
                            <th class="py-3">Description</th>
                            <th class="py-3">Created By</th>
                            <th class="py-3 text-end">Debit (ETB)</th>
                            <th class="px-4 py-3 text-end">Credit (ETB)</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($entries as $entry)
                            <tr>
                                <td class="px-4">
                                    <span class="badge bg-secondary-subtle text-secondary border">{{ \Carbon\Carbon::parse($entry->entry_date)->format('M d, Y') }}</span>
                                </td>
                                <td><span class="text-primary fw-semibold">{{ $entry->reference }}</span></td>
                                <td>{{ $entry->description }}</td>
                                <td>{{ $entry->journalEntry->creator->name ?? 'System' }}</td>
                                <td class="text-end text-success fw-bold">{{ $entry->side === 'debit' ? number_format($entry->amount, 2) : '-' }}</td>
                                <td class="px-4 text-end text-danger fw-bold">{{ $entry->side === 'credit' ? number_format($entry->amount, 2) : '-' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-center py-5 text-muted">No transactions found for the selected criteria.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if($entries->hasPages())
            <div class="card-footer bg-white border-0 pt-3">
                {{ $entries->links() }}
            </div>
        @endif
    </div>
</div>

<!-- ========================================================================= -->
<!-- MODAL: ASK MONEY / REQUEST REPLENISHMENT FROM FINANCE HEAD               -->
<!-- ========================================================================= -->
<div class="modal fade" id="requestReplenishmentModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content border-0 shadow rounded-4">
            <div class="modal-header bg-primary text-white border-0 py-3">
                <h5 class="modal-title fw-bold">
                    <i class="fas fa-hand-holding-dollar me-2"></i>Ask Money from Finance Head (Replenish Fund)
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="{{ route('assigned-accounts.request-replenishment', $account->id) }}" enctype="multipart/form-data">
                @csrf
                <div class="modal-body p-4">
                    <div class="alert alert-info rounded-4 border-0 d-flex align-items-start mb-4">
                        <i class="fas fa-info-circle fa-lg text-primary me-3 mt-1"></i>
                        <div class="small">
                            <strong>Imprest Replenishment Workflow:</strong> Submitting this request will compile and attach all <strong>{{ $unreplenishedCount }} payment expenses (ETB {{ number_format($unreplenishedExpensesTotal, 2) }})</strong> recorded since the last replenishment milestone ({{ $lastFulfilled ? $lastFulfilled->fulfilled_at->format('M d, Y') : 'initial start' }}). Once Finance Head fulfills the money, the next cycle will automatically begin from that point.
                        </div>
                    </div>

                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label class="form-label text-muted small fw-bold text-uppercase">Current Petty Cash Balance</label>
                            <input type="text" class="form-control bg-light border-0 py-2 fw-bold text-dark fs-5" value="ETB {{ number_format($account->current_balance, 2) }}" readonly>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-muted small fw-bold text-uppercase">Requested Replenishment Amount (ETB)</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light border-0 fw-bold">ETB</span>
                                <input type="number" step="0.01" min="0.01" name="requested_amount" class="form-control bg-light border-0 py-2 fw-bold text-primary fs-5" value="{{ $unreplenishedExpensesTotal > 0 ? $unreplenishedExpensesTotal : '' }}" placeholder="Enter requested amount" required>
                            </div>
                            <small class="text-muted">Suggested: Equal to total expenses incurred in this cycle (ETB {{ number_format($unreplenishedExpensesTotal, 2) }}).</small>
                        </div>
                    </div>

                    <!-- Payment History Preview Table -->
                    <div class="mb-4">
                        <label class="form-label text-muted small fw-bold text-uppercase d-flex justify-content-between align-items-center mb-2">
                            <span><i class="fas fa-paperclip me-1"></i> Attached Payment History ({{ $unreplenishedCount }} Records)</span>
                            <span class="text-danger fw-bold fs-6">Total: ETB {{ number_format($unreplenishedExpensesTotal, 2) }}</span>
                        </label>
                        <div class="border rounded-4 overflow-hidden" style="max-height: 280px; overflow-y: auto;">
                            <table class="table table-sm table-striped table-hover align-middle mb-0 small">
                                <thead class="bg-light text-muted sticky-top">
                                    <tr>
                                        <th class="px-3 py-2">Date</th>
                                        <th class="py-2">Req # / Reference</th>
                                        <th class="py-2">Requester / Dept</th>
                                        <th class="py-2">Category</th>
                                        <th class="py-2">Description</th>
                                        <th class="px-3 py-2 text-end">Amount (ETB)</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($unreplenishedExpenses as $exp)
                                        <tr>
                                            <td class="px-3 py-2 text-muted" style="white-space: nowrap;">
                                                {{ \Carbon\Carbon::parse($exp->date)->format('M d, Y H:i') }}
                                            </td>
                                            <td class="py-2">
                                                <span class="badge bg-light text-primary border fw-bold">{{ $exp->reference }}</span>
                                            </td>
                                            <td class="py-2">
                                                <span class="fw-semibold text-dark">{{ $exp->requester }}</span>
                                                @if($exp->department)
                                                    <span class="text-muted d-block small">{{ $exp->department }}</span>
                                                @endif
                                            </td>
                                            <td class="py-2">
                                                <span class="badge bg-primary-subtle text-primary border">{{ $exp->category }}</span>
                                            </td>
                                            <td class="py-2">{{ Str::limit($exp->description, 50) }}</td>
                                            <td class="px-3 py-2 text-end fw-bold text-danger" style="white-space: nowrap;">
                                                ETB {{ number_format($exp->amount, 2) }}
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="6" class="text-center py-4 text-muted">No expense records in the active cycle.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="row g-3">
                        <div class="col-md-12">
                            <label class="form-label text-muted small fw-bold text-uppercase">Custodian Notes / Request Justification</label>
                            <textarea name="notes" class="form-control bg-light border-0" rows="3" placeholder="Provide any additional remarks or details for the Finance Head..."></textarea>
                        </div>
                        <div class="col-md-12">
                            <label class="form-label text-muted small fw-bold text-uppercase">Attach Supporting Documents / Scanned Receipts (Optional)</label>
                            <input type="file" name="attachment" class="form-control bg-light border-0" accept=".pdf,.jpg,.jpeg,.png">
                            <small class="text-muted">PDF or image file containing signed expense vouchers or physical receipts.</small>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light border-0 py-3 px-4">
                    <button type="button" class="btn btn-light rounded-pill px-3" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary rounded-pill px-4 fw-bold shadow-sm" {{ $unreplenishedCount === 0 ? '' : '' }}>
                        <i class="fas fa-paper-plane me-2"></i> Submit Request to Finance Head
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ========================================================================= -->
<!-- MODALS: VIEW ATTACHED EXPENSE HISTORY / REPLENISHMENT DETAILS            -->
<!-- ========================================================================= -->
@foreach($replenishments as $rep)
<div class="modal fade" id="viewReplenishmentModal_{{ $rep->id }}" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content border-0 shadow rounded-4 overflow-hidden">
            <div class="modal-header bg-dark text-white border-0 py-3">
                <div>
                    <h5 class="modal-title fw-bold mb-0">
                        <i class="fas fa-receipt me-2 text-primary"></i>Replenishment Cycle #{{ $rep->request_no }}
                    </h5>
                    <small class="text-white-50">Requested by {{ $rep->requester->name ?? 'Custodian' }} on {{ $rep->created_at->format('M d, Y H:i') }}</small>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div class="row g-3 mb-4">
                    <div class="col-md-4">
                        <div class="p-3 bg-light rounded-3 border">
                            <span class="text-muted small text-uppercase fw-bold">Requested Amount</span>
                            <h4 class="fw-bold text-primary mb-0 mt-1">ETB {{ number_format($rep->requested_amount, 2) }}</h4>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="p-3 bg-light rounded-3 border">
                            <span class="text-muted small text-uppercase fw-bold">Attached Expenses Total</span>
                            <h4 class="fw-bold text-danger mb-0 mt-1">ETB {{ number_format($rep->total_expenses_amount, 2) }}</h4>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="p-3 bg-light rounded-3 border">
                            <span class="text-muted small text-uppercase fw-bold">Current Cycle Status</span>
                            <div class="mt-1">
                                @if($rep->status === 'pending')
                                    <span class="badge bg-warning text-dark px-3 py-1.5 rounded-pill">Pending Finance Approval</span>
                                @elseif($rep->status === 'fulfilled')
                                    <span class="badge bg-success text-white px-3 py-1.5 rounded-pill">Fulfilled &amp; Disbursed</span>
                                @elseif($rep->status === 'rejected')
                                    <span class="badge bg-danger text-white px-3 py-1.5 rounded-pill">Rejected</span>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>

                @if($rep->notes)
                    <div class="mb-4 p-3 bg-light rounded-3 border">
                        <span class="text-muted small text-uppercase fw-bold d-block mb-1">Custodian Justification / Notes</span>
                        <div class="text-dark">{{ $rep->notes }}</div>
                    </div>
                @endif

                @if($rep->attachment_path)
                    <div class="mb-4 p-3 bg-light rounded-3 border d-flex justify-content-between align-items-center">
                        <div>
                            <strong class="text-dark d-block"><i class="fas fa-file-invoice text-primary me-1"></i> Attached Supporting Document</strong>
                            <small class="text-muted">Uploaded receipt voucher copy</small>
                        </div>
                        <a href="{{ \App\Services\FileUploadService::url($rep->attachment_path) }}" target="_blank" class="btn btn-outline-primary btn-sm rounded-pill px-3 shadow-xs">
                            <i class="fas fa-paperclip me-1"></i> View Supporting Document / Receipt
                        </a>
                    </div>
                @endif

                <!-- Attached Itemized Expenses Table -->
                <div class="mb-4">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <h6 class="fw-bold text-dark mb-0"><i class="fas fa-list me-1 text-primary"></i> Attached Expense Vouchers ({{ $rep->items->count() }} Records)</h6>
                        <span class="badge bg-light text-dark border font-monospace">Total: ETB {{ number_format($rep->total_expenses_amount, 2) }}</span>
                    </div>
                    <div class="border rounded-top-3 shadow-xs" style="max-height: 380px; overflow-y: auto; overflow-x: auto;">
                        <table class="table table-sm table-striped table-hover align-middle mb-0 small">
                            <thead class="bg-light sticky-top shadow-xs" style="z-index: 5;">
                                <tr>
                                    <th class="px-3 py-2.5 text-nowrap bg-light">Date</th>
                                    <th class="py-2.5 text-nowrap bg-light">Voucher # / Ref</th>
                                    <th class="py-2.5 text-nowrap bg-light">Category / Target</th>
                                    <th class="py-2.5 bg-light" style="min-width: 300px;">Description &amp; Beneficiary Details</th>
                                    <th class="px-3 py-2.5 text-end text-nowrap bg-light">Amount (ETB)</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($rep->items as $item)
                                    <tr>
                                        <td class="px-3 py-2 text-muted text-nowrap">{{ $item->entry_date ? \Carbon\Carbon::parse($item->entry_date)->format('M d, Y') : ($item->created_at ? $item->created_at->format('M d, Y') : 'N/A') }}</td>
                                        <td class="py-2 text-nowrap"><span class="badge bg-light text-primary border font-monospace">{{ $item->reference ?: ($item->journal_entry_line_id ? 'JL #' . $item->journal_entry_line_id : 'EXP-' . $item->id) }}</span></td>
                                        <td class="py-2 text-nowrap"><span class="badge bg-secondary-subtle text-dark border">{{ $item->target_account_name ?: 'Petty Cash Expense' }}</span></td>
                                        <td class="py-2">
                                            <div style="word-break: break-word; white-space: normal; line-height: 1.4;">
                                                {{ $item->description }}
                                            </div>
                                        </td>
                                        <td class="px-3 py-2 text-end fw-bold text-danger font-monospace text-nowrap">ETB {{ number_format($item->amount, 2) }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="5" class="text-center py-4 text-muted">No individual items attached.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    @if($rep->items->count() > 0)
                    <div class="d-flex justify-content-between align-items-center bg-light border border-top-0 rounded-bottom-3 px-3 py-2 fw-bold small">
                        <span class="text-dark"><i class="fa-solid fa-receipt text-secondary me-1"></i> Total Vouchers: <span class="badge bg-dark rounded-pill">{{ $rep->items->count() }}</span></span>
                        <span class="text-danger font-monospace fs-6">Grand Total: ETB {{ number_format($rep->items->sum('amount') ?: $rep->total_expenses_amount, 2) }}</span>
                    </div>
                    @endif
                </div>


                @if($rep->status === 'fulfilled')
                    <div class="p-3 bg-success bg-opacity-10 rounded-4 border border-success border-opacity-20">
                        <h6 class="fw-bold text-success mb-2"><i class="fas fa-check-circle me-1"></i> Fulfillment Record</h6>
                        <div class="row g-2 small">
                            <div class="col-md-6">
                                <span class="text-muted">Fulfilled Amount:</span>
                                <strong class="text-success">ETB {{ number_format($rep->fulfilled_amount, 2) }}</strong>
                            </div>
                            <div class="col-md-6">
                                <span class="text-muted">Source Account:</span>
                                <strong>{{ $rep->sourceCoa->name ?? 'Bank Account' }}</strong>
                            </div>
                            <div class="col-md-6">
                                <span class="text-muted">Fulfilled By:</span>
                                <strong>{{ $rep->financeHead->name ?? 'Finance Head' }}</strong>
                            </div>
                            <div class="col-md-6">
                                <span class="text-muted">Date:</span>
                                <strong>{{ $rep->fulfilled_at?->format('M d, Y H:i') }}</strong>
                            </div>
                            @if($rep->fulfillment_reference)
                                <div class="col-md-12">
                                    <span class="text-muted">Reference / Voucher:</span>
                                    <strong>{{ $rep->fulfillment_reference }}</strong>
                                </div>
                            @endif
                            @if($rep->finance_notes)
                                <div class="col-md-12 mt-1">
                                    <span class="text-muted">Finance Notes:</span>
                                    <em>{{ $rep->finance_notes }}</em>
                                </div>
                            @endif
                        </div>
                    </div>
                @elseif($rep->status === 'rejected')
                    <div class="p-3 bg-danger bg-opacity-10 rounded-4 border border-danger border-opacity-20">
                        <h6 class="fw-bold text-danger mb-1"><i class="fas fa-times-circle me-1"></i> Rejection Details</h6>
                        <p class="small text-danger mb-0">{{ $rep->rejection_reason }}</p>
                    </div>
                @endif
            </div>
            <div class="modal-footer bg-light border-0 py-3 px-4">
                <button type="button" class="btn btn-light rounded-pill px-3" data-bs-dismiss="modal">Close</button>
                @if($rep->status === 'pending' && $isFinanceHead)
                    <button type="button" class="btn btn-success rounded-pill px-4 fw-bold shadow-sm" data-bs-dismiss="modal" data-bs-toggle="modal" data-bs-target="#fulfillModal_{{ $rep->id }}">
                        <i class="fas fa-hand-holding-dollar me-1"></i> Proceed to Fulfill
                    </button>
                @endif
            </div>
        </div>
    </div>
</div>

<!-- ========================================================================= -->
<!-- MODAL: FINANCE HEAD FULFILL MONEY / DISBURSE REPLENISHMENT                -->
<!-- ========================================================================= -->
@if($rep->status === 'pending' && $isFinanceHead)
<div class="modal fade" id="fulfillModal_{{ $rep->id }}" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow rounded-4">
            <div class="modal-header bg-success text-white border-0 py-3">
                <h5 class="modal-title fw-bold">
                    <i class="fas fa-hand-holding-dollar me-2"></i>Fulfill Replenishment #{{ $rep->request_no }}
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="{{ route('assigned-accounts.fulfill-replenishment', [$account->id, $rep->id]) }}">
                @csrf
                <div class="modal-body p-4">
                    <div class="p-3 bg-light rounded-3 mb-4">
                        <div class="d-flex justify-content-between mb-1">
                            <span class="text-muted small">Account to Replenish:</span>
                            <span class="fw-bold text-dark">{{ $account->name }} ({{ $account->code }})</span>
                        </div>
                        <div class="d-flex justify-content-between mb-1">
                            <span class="text-muted small">Requested by Custodian:</span>
                            <span class="fw-bold text-dark">{{ $rep->requester->name ?? 'Custodian' }}</span>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span class="text-muted small">Total Expenses Attached:</span>
                            <span class="fw-bold text-danger">ETB {{ number_format($rep->total_expenses_amount, 2) }}</span>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label text-muted small fw-bold text-uppercase">Source Account (Disburse From)</label>
                        <select name="source_coa_id" class="form-select bg-light border-0 py-2" required>
                            <option value="">Select source account (Bank Account / Main Cash)...</option>
                            @foreach($sourceAccounts as $source)
                                <option value="{{ $source->id }}">
                                    [{{ $source->code }}] {{ $source->name }} (Balance: ETB {{ number_format($source->current_balance, 2) }})
                                </option>
                            @endforeach
                        </select>
                        <small class="text-muted">Funds will be transferred out of this account into {{ $account->name }}.</small>
                    </div>

                    <div class="mb-3">
                        <label class="form-label text-muted small fw-bold text-uppercase">Fulfilled Amount (ETB)</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-0 fw-bold">ETB</span>
                            <input type="number" step="0.01" min="0.01" name="fulfilled_amount" class="form-control bg-light border-0 py-2 fw-bold text-success fs-5" value="{{ $rep->requested_amount }}" required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label text-muted small fw-bold text-uppercase">Payment Reference / Check # / Transfer Ref</label>
                        <input type="text" name="reference" class="form-control bg-light border-0 py-2" placeholder="e.g. CBE-TXN-88129, Cheque #004412">
                    </div>

                    <div class="mb-3">
                        <label class="form-label text-muted small fw-bold text-uppercase">Finance Head Remarks / Approval Notes</label>
                        <textarea name="finance_notes" class="form-control bg-light border-0" rows="2" placeholder="Optional notes for the custodian or audit trail..."></textarea>
                    </div>

                    <div class="alert alert-warning border-0 rounded-3 small mb-0">
                        <i class="fas fa-shield-alt me-1"></i> Fulfilling will automatically create a posted Journal Entry, update account balances, and advance the Imprest cycle milestone.
                    </div>
                </div>
                <div class="modal-footer bg-light border-0 py-3 px-4">
                    <button type="button" class="btn btn-light rounded-pill px-3" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success rounded-pill px-4 fw-bold shadow-sm">
                        <i class="fas fa-check-circle me-2"></i> Disburse & Fulfill Fund
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ========================================================================= -->
<!-- MODAL: REJECT REPLENISHMENT REQUEST                                      -->
<!-- ========================================================================= -->
<div class="modal fade" id="rejectModal_{{ $rep->id }}" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow rounded-4">
            <div class="modal-header bg-danger text-white border-0 py-3">
                <h5 class="modal-title fw-bold">
                    <i class="fas fa-times-circle me-2"></i>Reject Replenishment #{{ $rep->request_no }}
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="{{ route('assigned-accounts.reject-replenishment', [$account->id, $rep->id]) }}">
                @csrf
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label text-muted small fw-bold text-uppercase">Reason for Rejection</label>
                        <textarea name="rejection_reason" class="form-control bg-light border-0" rows="4" placeholder="Explain why this replenishment is being rejected (e.g. missing receipts, unauthorized expenses)..." required></textarea>
                    </div>
                </div>
                <div class="modal-footer bg-light border-0 py-3 px-4">
                    <button type="button" class="btn btn-light rounded-pill px-3" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger rounded-pill px-4 fw-bold shadow-sm">
                        <i class="fas fa-times-circle me-2"></i> Confirm Rejection
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif
@endforeach

@endsection
