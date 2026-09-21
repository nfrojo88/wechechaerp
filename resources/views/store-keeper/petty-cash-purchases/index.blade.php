@extends('layouts.app')
@section('title', 'Petty Cash Material Purchases - Store Keeper')

@section('content')
<div class="container-fluid px-4 py-3">

    {{-- ── Top Navigation & Page Title ────────────────────────────────────────── --}}
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
        <div>
            <div class="d-flex align-items-center gap-2 mb-1">
                <h3 class="fw-bold text-dark mb-0">
                    <i class="fa-solid fa-cart-flatbed text-success me-2"></i>Petty Cash Material Purchases
                </h3>
                <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 px-2 py-1">
                    <i class="fa-solid fa-unlock me-1"></i>Direct Site Intake
                </span>
            </div>
            <p class="text-muted small mb-0">
                Direct spot material purchases using Petty Cash funds with immediate inventory intake for 
                <strong>{{ $assignedStore->name ?? 'Site Store' }}</strong>.
            </p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a href="{{ route('store-keeper.petty-cash-purchases.create') }}" class="btn btn-primary btn-sm shadow-sm px-3 fw-bold text-white">
                <i class="fa-solid fa-hand-holding-dollar me-1"></i> Ask for Replenishment (Audit)
            </a>
            <a href="{{ route('finance.replenishments.index', ['tab' => 'under_audit']) }}" class="btn btn-outline-primary btn-sm shadow-sm px-2.5 fw-semibold" title="View company Petty Cash Replenishments & Audit Queue">
                <i class="fa-solid fa-scale-balanced me-1"></i> Audit Hub
            </a>
            @canany(['admin', 'global_admin', 'finance_head', 'store_manager'])
            <button type="button" class="btn btn-outline-warning btn-sm shadow-sm px-2.5 fw-bold text-dark" data-bs-toggle="modal" data-bs-target="#sendReplacementModal" title="Direct spot disbursement voucher (Admin / Finance override)">
                <i class="fa-solid fa-money-bill-transfer me-1"></i> Direct Disburse
            </button>
            @endcanany
            @if($assignedStore)
                <a href="{{ route('store-manager.inventory.all') }}" class="btn btn-outline-secondary btn-sm shadow-sm">
                    <i class="fa-solid fa-boxes-stacked me-1"></i> View Stock
                </a>
            @endif
            <a href="{{ route('store-keeper.petty-cash-purchases.create') }}" class="btn btn-success btn-sm shadow-sm px-3 fw-bold">
                <i class="fa-solid fa-plus-circle me-1"></i> Buy Material (Petty Cash)
            </a>
        </div>
    </div>

    {{-- ── KPI Cards ────────────────────────────────────────────────────────── --}}
    <div class="row g-3 mb-4">
        {{-- Total Spent --}}
        <div class="col-sm-6 col-xl-4">
            <div class="card border-0 shadow-sm rounded-3 h-100" style="border-left: 4px solid #10b981 !important;">
                <div class="card-body p-3">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <p class="text-muted fw-semibold mb-1" style="font-size: 0.72rem; letter-spacing: .06em; text-transform: uppercase;">Total Petty Cash Spent</p>
                            <h3 class="fw-bold text-success mb-0">ETB {{ number_format($totalSpent, 2) }}</h3>
                        </div>
                        <div class="p-2 rounded-3" style="background: rgba(16,185,129,.12);">
                            <i class="fa-solid fa-wallet fa-lg text-success"></i>
                        </div>
                    </div>
                    <small class="text-muted mt-2 d-block" style="font-size:0.73rem;">Cumulative direct purchases on site</small>
                </div>
            </div>
        </div>

        {{-- Total Purchases Count --}}
        <div class="col-sm-6 col-xl-4">
            <div class="card border-0 shadow-sm rounded-3 h-100" style="border-left: 4px solid #0284c7 !important;">
                <div class="card-body p-3">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <p class="text-muted fw-semibold mb-1" style="font-size: 0.72rem; letter-spacing: .06em; text-transform: uppercase;">Purchases Logged</p>
                            <h3 class="fw-bold text-dark mb-0">{{ number_format($totalPurchases) }}</h3>
                        </div>
                        <div class="p-2 rounded-3" style="background: rgba(2,132,199,.12);">
                            <i class="fa-solid fa-receipt fa-lg text-primary"></i>
                        </div>
                    </div>
                    <small class="text-muted mt-2 d-block" style="font-size:0.73rem;">Verified vendor cash receipts</small>
                </div>
            </div>
        </div>

        {{-- Current Petty Cash Fund Status --}}
        <div class="col-sm-6 col-xl-4">
            <div class="card border-0 shadow-sm rounded-3 h-100" style="border-left: 4px solid #f59e0b !important;">
                <div class="card-body p-3">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <p class="text-muted fw-semibold mb-1" style="font-size: 0.72rem; letter-spacing: .06em; text-transform: uppercase;">Site Petty Cash Balance</p>
                            <h3 class="fw-bold text-warning mb-0">
                                ETB {{ number_format($pettyCashAccount->current_balance ?? 0, 2) }}
                            </h3>
                        </div>
                        <div class="p-2 rounded-3" style="background: rgba(245,158,11,.12);">
                            <i class="fa-solid fa-coins fa-lg text-warning"></i>
                        </div>
                    </div>
                    <div class="d-flex justify-content-between align-items-center mt-2">
                        <small class="text-muted" style="font-size:0.73rem;">
                            Fund: <strong>{{ $pettyCashAccount->name ?? 'Site Petty Cash' }}</strong> [{{ $pettyCashAccount->code ?? 'Site Fund' }}]
                        </small>
                        <a href="{{ route('store-keeper.petty-cash-purchases.create') }}" class="btn btn-xs btn-outline-primary fw-bold">
                            <i class="fa-solid fa-hand-holding-dollar me-1"></i>Ask Replenish
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ── Filters & Search ─────────────────────────────────────────────────── --}}
    <div class="card border-0 shadow-sm rounded-3 mb-4 bg-white">
        <div class="card-body p-3">
            <form method="GET" action="{{ route('store-keeper.petty-cash-purchases.index') }}" class="row g-2 align-items-center">
                <div class="col-md-4">
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-light border-0"><i class="fa-solid fa-magnifying-glass text-muted"></i></span>
                        <input type="text" name="search" class="form-control bg-light border-0" placeholder="Search purchase #, supplier, receipt #..." value="{{ request('search') }}">
                    </div>
                </div>

                @if(!$isStoreKeeper)
                <div class="col-md-3">
                    <select name="store_id" class="form-select form-select-sm bg-light border-0">
                        <option value="">-- All Stores --</option>
                        @foreach($stores as $st)
                            <option value="{{ $st->id }}" {{ request('store_id') == $st->id ? 'selected' : '' }}>{{ $st->name }}</option>
                        @endforeach
                    </select>
                </div>
                @endif

                <div class="col-md-2 col-6">
                    <input type="date" name="from_date" class="form-control form-control-sm bg-light border-0" value="{{ request('from_date') }}" title="From Date">
                </div>
                <div class="col-md-2 col-6">
                    <input type="date" name="to_date" class="form-control form-control-sm bg-light border-0" value="{{ request('to_date') }}" title="To Date">
                </div>

                <div class="col-md-1 d-flex gap-1">
                    <button type="submit" class="btn btn-primary btn-sm w-100"><i class="fa-solid fa-filter me-1"></i>Filter</button>
                    @if(request()->hasAny(['search', 'store_id', 'from_date', 'to_date']))
                        <a href="{{ route('store-keeper.petty-cash-purchases.index') }}" class="btn btn-outline-secondary btn-sm" title="Clear Filters"><i class="fa-solid fa-xmark"></i></a>
                    @endif
                </div>
            </form>
        </div>
    </div>

    {{-- ── Tab Navigation: Purchases vs Replacements ────────────────────────── --}}
    @php
        $activeTab = request('tab', 'purchases');
    @endphp
    <ul class="nav nav-pills mb-3 gap-2" id="pettyCashTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <a href="{{ route('store-keeper.petty-cash-purchases.index', array_merge(request()->except(['tab', 'rep_page']), ['tab' => 'purchases'])) }}" 
               class="nav-link py-2 px-3 fw-semibold rounded-3 shadow-xs {{ $activeTab !== 'replacements' ? 'active bg-success text-white' : 'bg-white text-dark border' }}">
                <i class="fa-solid fa-cart-flatbed me-1.5"></i> Material Purchases
                <span class="badge {{ $activeTab !== 'replacements' ? 'bg-white text-success' : 'bg-light text-dark' }} ms-1.5">{{ number_format($totalPurchases) }}</span>
            </a>
        </li>
        <li class="nav-item" role="presentation">
            <a href="{{ route('store-keeper.petty-cash-purchases.index', array_merge(request()->except(['tab', 'page']), ['tab' => 'replacements'])) }}" 
               class="nav-link py-2 px-3 fw-semibold rounded-3 shadow-xs {{ $activeTab === 'replacements' ? 'active bg-warning text-dark' : 'bg-white text-dark border' }}">
                <i class="fa-solid fa-money-bill-transfer me-1.5 text-warning"></i> Replacement Money Dispatched
                <span class="badge {{ $activeTab === 'replacements' ? 'bg-dark text-white' : 'bg-light text-dark' }} ms-1.5">{{ $replenishments->total() }}</span>
            </a>
        </li>
    </ul>

    @if($activeTab !== 'replacements')
    {{-- ── Purchases Table ─────────────────────────────────────────────────── --}}
    <div class="card border-0 shadow-sm rounded-3 mb-4">
        <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center">
            <h6 class="mb-0 fw-bold text-dark">
                <i class="fa-solid fa-list-check text-primary me-2"></i>Purchase Log &amp; Goods Intake History
            </h6>
            <span class="text-muted small">Showing {{ $purchases->firstItem() ?? 0 }}-{{ $purchases->lastItem() ?? 0 }} of {{ $purchases->total() }}</span>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3">Purchase #</th>
                        <th>Date</th>
                        <th>Store Location</th>
                        <th>Supplier / Vendor</th>
                        <th>Receipt #</th>
                        <th>Items Count</th>
                        <th class="text-end">Total Amount</th>
                        <th>Receive Slip (GRN)</th>
                        <th class="text-end pe-3">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($purchases as $p)
                    <tr>
                        <td class="ps-3">
                            <a href="{{ route('store-keeper.petty-cash-purchases.show', $p) }}" class="fw-bold font-monospace text-primary text-decoration-none">
                                {{ $p->purchase_no }}
                            </a>
                        </td>
                        <td>
                            <span class="text-dark small">{{ optional($p->purchase_date)->format('d M Y') }}</span>
                            <small class="text-muted d-block" style="font-size:0.7rem;">By {{ $p->purchaser->name ?? 'Store Keeper' }}</small>
                        </td>
                        <td>
                            <span class="badge bg-light text-dark border">
                                <i class="fa-solid fa-warehouse me-1 text-secondary"></i>{{ $p->store->name ?? 'Main Store' }}
                            </span>
                        </td>
                        <td>
                            <strong class="text-dark d-block small">{{ $p->supplier_name ?: 'Local Vendor' }}</strong>
                        </td>
                        <td>
                            @if($p->receipt_no)
                                <span class="badge bg-secondary font-monospace" style="font-size: 0.75rem;">{{ $p->receipt_no }}</span>
                            @else
                                <span class="text-muted small">—</span>
                            @endif
                            @if($p->attachment_path)
                                <a href="{{ asset($p->attachment_path) }}" target="_blank" class="ms-1 text-primary" title="View Receipt Document">
                                    <i class="fa-solid fa-paperclip"></i>
                                </a>
                            @endif
                        </td>
                        <td>
                            <span class="badge bg-info text-dark">{{ $p->items->count() }} {{ Str::plural('item', $p->items->count()) }}</span>
                        </td>
                        <td class="text-end">
                            <strong class="text-success font-monospace">ETB {{ number_format($p->total_amount, 2) }}</strong>
                        </td>
                        <td>
                            @if($p->deliveryReceipt)
                                <span class="badge bg-success font-monospace" style="font-size:0.75rem;">
                                    <i class="fa-solid fa-check-circle me-1"></i>{{ $p->deliveryReceipt->dr_no }}
                                </span>
                            @else
                                <span class="badge bg-light text-secondary">Direct Added</span>
                            @endif
                        </td>
                        <td class="text-end pe-3">
                            <a href="{{ route('store-keeper.petty-cash-purchases.show', $p) }}" class="btn btn-sm btn-outline-primary shadow-xs">
                                <i class="fa-solid fa-eye me-1"></i>View
                            </a>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="9" class="text-center py-5 text-muted">
                            <i class="fa-solid fa-cart-flatbed fa-3x mb-3 text-secondary opacity-25 d-block"></i>
                            <h6 class="fw-bold">No Petty Cash Material Purchases Recorded</h6>
                            <p class="small text-muted mb-3">You can buy small site materials directly using Petty Cash and have them added immediately to store inventory.</p>
                            <a href="{{ route('store-keeper.petty-cash-purchases.create') }}" class="btn btn-success btn-sm">
                                <i class="fa-solid fa-plus-circle me-1"></i> Record First Petty Cash Buy
                            </a>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($purchases->hasPages())
        <div class="card-footer bg-white border-top py-2 px-3">
            {{ $purchases->links() }}
        </div>
        @endif
    </div>
    @else
    {{-- ── Replacement Money Dispatched Table ───────────────────────────── --}}
    <div class="card border-0 shadow-sm rounded-3 mb-4">
        <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center">
            <h6 class="mb-0 fw-bold text-dark">
                <i class="fa-solid fa-money-bill-transfer text-warning me-2"></i>Replacement Money Dispatched &amp; Cash Refills
            </h6>
            <span class="text-muted small">Showing {{ $replenishments->firstItem() ?? 0 }}-{{ $replenishments->lastItem() ?? 0 }} of {{ $replenishments->total() }}</span>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3">Voucher #</th>
                        <th>Disbursed Date</th>
                        <th>Store &amp; Site Fund</th>
                        <th>Recipient Store Keeper</th>
                        <th>Disbursing Source (Bank/Cash)</th>
                        <th>Method &amp; Ref #</th>
                        <th class="text-end">Amount Disbursed</th>
                        <th>Status</th>
                        <th class="text-end pe-3">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($replenishments as $rep)
                    <tr>
                        <td class="ps-3">
                            <a href="{{ route('store-keeper.petty-cash-purchases.replacement-voucher', $rep->id) }}" class="fw-bold font-monospace text-primary text-decoration-none">
                                {{ $rep->request_no }}
                            </a>
                        </td>
                        <td>
                            <span class="text-dark small">{{ optional($rep->fulfilled_at ?? $rep->created_at)->format('d M Y') }}</span>
                            <small class="text-muted d-block" style="font-size:0.7rem;">By {{ $rep->financeHead->name ?? 'Finance Head' }}</small>
                        </td>
                        <td>
                            <strong class="d-block small text-dark">{{ $rep->store->name ?? 'Store' }}</strong>
                            <small class="text-muted font-monospace">{{ $rep->chartOfAccount->name ?? 'Site Fund' }}</small>
                        </td>
                        <td>
                            <span class="fw-semibold text-dark small">{{ $rep->recipient_name ?: ($rep->requester->name ?? 'Store Keeper') }}</span>
                            @if($rep->recipient_phone)
                                <small class="text-muted font-monospace d-block" style="font-size:0.7rem;">{{ $rep->recipient_phone }}</small>
                            @endif
                        </td>
                        <td>
                            @if($rep->sourceCoa)
                                <span class="badge bg-light text-dark border">
                                    [{{ $rep->sourceCoa->code }}] {{ $rep->sourceCoa->name }}
                                </span>
                            @else
                                <span class="text-muted small">—</span>
                            @endif
                        </td>
                        <td>
                            <span class="badge bg-light text-secondary">{{ $rep->payment_method ?: 'Bank Transfer' }}</span>
                            @if($rep->fulfillment_reference)
                                <small class="text-muted font-monospace d-block" style="font-size:0.7rem;">Ref: {{ $rep->fulfillment_reference }}</small>
                            @endif
                        </td>
                        <td class="text-end">
                            <strong class="text-success font-monospace fs-6">
                                ETB {{ number_format($rep->fulfilled_amount ?? $rep->requested_amount, 2) }}
                            </strong>
                        </td>
                        <td>
                            @if($rep->status === 'fulfilled')
                                <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 px-2 py-1">
                                    <i class="fa-solid fa-check me-1"></i>Credited
                                </span>
                            @else
                                <span class="badge bg-warning text-dark px-2 py-1">{{ ucfirst($rep->status) }}</span>
                            @endif
                        </td>
                        <td class="text-end pe-3">
                            <a href="{{ route('store-keeper.petty-cash-purchases.replacement-voucher', $rep->id) }}" class="btn btn-sm btn-outline-primary shadow-xs">
                                <i class="fa-solid fa-print me-1"></i>Voucher
                            </a>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="9" class="text-center py-5 text-muted">
                            <i class="fa-solid fa-hand-holding-dollar fa-3x mb-3 text-secondary opacity-25 d-block"></i>
                            <h6 class="fw-bold">No Replacement Money Records Found</h6>
                            <p class="small text-muted mb-3">Send replacement money to site store keepers so they have funds available to purchase spot materials.</p>
                            <button type="button" class="btn btn-warning btn-sm fw-bold text-dark shadow-xs" data-bs-toggle="modal" data-bs-target="#sendReplacementModal">
                                <i class="fa-solid fa-paper-plane me-1"></i> Send First Replacement Money
                            </button>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($replenishments->hasPages())
        <div class="card-footer bg-white border-top py-2 px-3">
            {{ $replenishments->links() }}
        </div>
        @endif
    </div>
    @endif

</div>

{{-- ── Modal: Send Replacement Money (Available from Index Page) ───────────── --}}
<div class="modal fade" id="sendReplacementModal" tabindex="-1" aria-labelledby="sendReplacementModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <form action="{{ route('store-keeper.petty-cash-purchases.send-replacement') }}" method="POST" enctype="multipart/form-data">
                @csrf
                <div class="modal-header bg-warning text-dark py-3">
                    <div>
                        <h5 class="modal-title fw-bold mb-0" id="sendReplacementModalLabel">
                            <i class="fa-solid fa-money-bill-transfer me-2"></i>Send Replacement Money to Store Keeper
                        </h5>
                        <small class="text-dark-50" style="font-size:0.75rem;">Disburse spot replenishment funds into this Store's Site Petty Cash account</small>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">

                    <div class="row g-3">
                        {{-- Target Store --}}
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small text-dark">
                                Destination Store <span class="text-danger">*</span>
                            </label>
                            <select name="store_id" id="idxModalStoreSelect" class="form-select form-select-sm" required>
                                @foreach($stores as $st)
                                    <option value="{{ $st->id }}" {{ (optional($assignedStore)->id == $st->id) ? 'selected' : '' }}>
                                        {{ $st->name }} ({{ $st->code ?? 'N/A' }})
                                    </option>
                                @endforeach
                            </select>
                            <small class="text-muted d-block mt-1" style="font-size:0.72rem;">Money will be deposited into this store's site petty cash fund</small>
                        </div>

                        {{-- Recipient Store Keeper --}}
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small text-dark">
                                Recipient Store Keeper <span class="text-danger">*</span>
                            </label>
                            <select name="recipient_id" class="form-select form-select-sm" required>
                                @foreach($storeKeepers as $k)
                                    @php
                                        $kPhone = $k->phone ?? $k->employee?->phone ?? $k->employee?->mobile_phone ?? '';
                                    @endphp
                                    <option value="{{ $k->id }}" {{ (optional($assignedStore)->manager_id == $k->id || Auth::id() == $k->id) ? 'selected' : '' }}>
                                        {{ $k->name }} ({{ $k->email }}) {{ $kPhone ? '— ' . $kPhone : '' }}
                                    </option>
                                @endforeach
                            </select>
                            <small class="text-muted d-block mt-1" style="font-size:0.72rem;">Custodian responsible for this site fund</small>
                        </div>

                        {{-- Disbursing / Source Account --}}
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small text-dark">
                                Source Funding Account (Bank / Cash) <span class="text-danger">*</span>
                            </label>
                            <select name="source_coa_id" class="form-select form-select-sm" required>
                                <option value="">-- Choose Paying Bank / Cash Account --</option>
                                @foreach($sourceAccounts as $sa)
                                    <option value="{{ $sa->id }}" {{ $loop->first ? 'selected' : '' }}>
                                        [{{ $sa->code }}] {{ $sa->name }} (ETB {{ number_format($sa->current_balance, 2) }})
                                    </option>
                                @endforeach
                            </select>
                            <small class="text-muted d-block mt-1" style="font-size:0.72rem;">Account credited for this cash transfer</small>
                        </div>

                        {{-- Replacement Amount (ETB) --}}
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small text-dark">
                                Replacement Amount (ETB) <span class="text-danger">*</span>
                            </label>
                            <div class="input-group input-group-sm">
                                <span class="input-group-text fw-bold">ETB</span>
                                <input type="number" step="0.01" min="1" name="amount" id="idxModalRepAmount" class="form-control font-monospace fw-bold fs-6" placeholder="0.00" required>
                            </div>
                            <div class="d-flex gap-1 mt-1.5 flex-wrap">
                                <span class="text-muted small me-1" style="font-size:0.72rem;">Quick:</span>
                                <button type="button" class="btn btn-xs btn-outline-secondary py-0 px-1.5 idx-quick-amt" data-amt="5000">+5,000</button>
                                <button type="button" class="btn btn-xs btn-outline-secondary py-0 px-1.5 idx-quick-amt" data-amt="10000">+10,000</button>
                                <button type="button" class="btn btn-xs btn-outline-secondary py-0 px-1.5 idx-quick-amt" data-amt="25000">+25,000</button>
                                <button type="button" class="btn btn-xs btn-outline-secondary py-0 px-1.5 idx-quick-amt" data-amt="50000">+50,000</button>
                            </div>
                        </div>

                        {{-- Transfer Date --}}
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small text-dark">
                                Transfer / Disbursement Date <span class="text-danger">*</span>
                            </label>
                            <input type="date" name="transfer_date" class="form-control form-control-sm" value="{{ date('Y-m-d') }}" required>
                        </div>

                        {{-- Payment Method --}}
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small text-dark">
                                Payment Method / Mode <span class="text-danger">*</span>
                            </label>
                            <select name="payment_method" class="form-select form-select-sm" required>
                                <option value="Bank Transfer / CBE Birr" selected>Bank Transfer (CBE / Commercial)</option>
                                <option value="Cash in Hand">Cash on Hand (Direct Handover)</option>
                                <option value="Telebirr / Mobile Money">Telebirr / Mobile Money</option>
                                <option value="Awash Bank Transfer">Awash Bank Transfer</option>
                                <option value="Cheque">Bank Cheque</option>
                                <option value="Other">Other Mode</option>
                            </select>
                        </div>

                        {{-- Reference / Voucher # --}}
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small text-dark">
                                Transfer / Voucher Ref # <span class="text-danger">*</span>
                            </label>
                            <input type="text" name="reference_no" class="form-control form-control-sm font-monospace" placeholder="e.g. TRX-94821, VCH-2026-001, Cheque #" required>
                        </div>

                        {{-- Purpose / Reason --}}
                        <div class="col-12">
                            <label class="form-label fw-semibold small text-dark">Disbursement Purpose &amp; Information</label>
                            <textarea name="notes" class="form-control form-control-sm" rows="2" placeholder="Describe the reason or urgent site requirements...">Site Petty Cash replacement fund for spot store materials and site consumables</textarea>
                        </div>

                        {{-- Attachment / Proof --}}
                        <div class="col-md-7">
                            <label class="form-label fw-semibold small text-dark">Bank Transfer Slip / Voucher Proof (Optional)</label>
                            <input type="file" name="attachment" class="form-control form-control-sm" accept=".pdf,.jpg,.jpeg,.png,.webp">
                        </div>

                        {{-- SMS Notification Checkbox --}}
                        <div class="col-md-5 d-flex align-items-center">
                            <div class="form-check form-switch mt-3">
                                <input class="form-check-input" type="checkbox" name="send_sms" value="1" id="idxModalSendSmsCheck" checked>
                                <label class="form-check-label small fw-semibold text-dark" for="idxModalSendSmsCheck">
                                    <i class="fa-solid fa-comment-sms text-success me-1"></i>Send SMS Alert to Keeper
                                </label>
                            </div>
                        </div>
                    </div>

                </div>
                <div class="modal-footer bg-light py-2">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success btn-sm fw-bold px-4">
                        <i class="fa-solid fa-paper-plane me-1"></i> Confirm &amp; Send Replacement
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.idx-quick-amt').forEach(btn => {
        btn.addEventListener('click', function() {
            const amt = this.getAttribute('data-amt');
            const amtInput = document.getElementById('idxModalRepAmount');
            if (amtInput) {
                amtInput.value = parseFloat(amt).toFixed(2);
            }
        });
    });
});
</script>
@endpush
@endsection
