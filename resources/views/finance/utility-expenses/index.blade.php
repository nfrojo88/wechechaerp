@extends('layouts.app')

@section('title', 'Utility Expenses Management - የፍጆታ ወጪ አስተዳደር')

@section('content')
<div class="container-fluid py-3">

    {{-- Page Header --}}
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
        <div>
            <h3 class="fw-bold mb-0 text-dark">
                <i class="fa-solid fa-bolt text-warning me-2"></i>Utility Expenses Management
                <span class="fs-6 text-muted ms-2">(የፍጆታ ወጪ አስተዳደር)</span>
            </h3>
            <p class="text-muted small mb-0 mt-1">
                Manage and disburse institutional utility bills (WiFi, Office Rentals, Electricity, Water &amp; Facilities). Select funding COA accounts and assign staff to disburse payment.
            </p>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <button type="button" class="btn btn-warning text-dark shadow-sm px-3 fw-bold" data-bs-toggle="modal" data-bs-target="#createUtilityModal">
                <i class="fa-solid fa-plus-circle me-1"></i> New Utility Expense (አዲስ የፍጆታ ወጪ)
            </button>
            <a href="{{ route('utility-expenses.index') }}" class="btn btn-outline-secondary shadow-sm px-3">
                <i class="fa-solid fa-rotate me-1"></i> Refresh
            </a>
        </div>
    </div>

    {{-- Alerts --}}
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show border-start border-4 border-success shadow-xs" role="alert">
            <i class="fa-solid fa-circle-check me-2"></i>{{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show border-start border-4 border-danger shadow-xs" role="alert">
            <i class="fa-solid fa-circle-exclamation me-2"></i>{{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    @if(isset($errors) && $errors->any())
        <div class="alert alert-danger alert-dismissible fade show shadow-xs" role="alert">
            <strong><i class="fa-solid fa-triangle-exclamation me-2"></i>Please check the errors below:</strong>
            <ul class="mb-0 mt-1 ps-3">
                @foreach($errors->all() as $err)
                    <li>{{ $err }}</li>
                @endforeach
            </ul>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    {{-- Summary KPI Cards --}}
    <div class="row g-3 mb-4">
        <div class="col-sm-6 col-xl-3">
            <div class="card border-0 shadow-sm h-100 rounded-3 border-start border-4 border-primary">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <span class="text-muted small fw-semibold text-uppercase">Total Utility Expenses</span>
                        <div class="rounded-circle bg-primary-subtle text-primary p-2 d-flex align-items-center justify-content-center" style="width: 38px; height: 38px;">
                            <i class="fa-solid fa-receipt"></i>
                        </div>
                    </div>
                    <h4 class="fw-bold mb-0 text-dark">ETB {{ number_format($counters['total_amount'], 2) }}</h4>
                    <small class="text-muted">{{ $counters['total_count'] }} recorded bills in total</small>
                </div>
            </div>
        </div>

        <div class="col-sm-6 col-xl-3">
            <div class="card border-0 shadow-sm h-100 rounded-3 border-start border-4 border-warning">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <span class="text-muted small fw-semibold text-uppercase">Pending / Assigned to Pay</span>
                        <div class="rounded-circle bg-warning-subtle text-warning p-2 d-flex align-items-center justify-content-center" style="width: 38px; height: 38px;">
                            <i class="fa-solid fa-clock-rotate-left"></i>
                        </div>
                    </div>
                    <h4 class="fw-bold mb-0 text-warning-emphasis">ETB {{ number_format($counters['pending_amount'], 2) }}</h4>
                    <small class="text-muted">{{ $counters['pending_count'] }} bills awaiting disbursement</small>
                </div>
            </div>
        </div>

        <div class="col-sm-6 col-xl-3">
            <div class="card border-0 shadow-sm h-100 rounded-3 border-start border-4 border-success">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <span class="text-muted small fw-semibold text-uppercase">Paid Utilities (የተከፈሉ)</span>
                        <div class="rounded-circle bg-success-subtle text-success p-2 d-flex align-items-center justify-content-center" style="width: 38px; height: 38px;">
                            <i class="fa-solid fa-circle-check"></i>
                        </div>
                    </div>
                    <h4 class="fw-bold mb-0 text-success">ETB {{ number_format($counters['paid_amount'], 2) }}</h4>
                    <small class="text-muted">{{ $counters['paid_count'] }} bills disbursed successfully</small>
                </div>
            </div>
        </div>

        <div class="col-sm-6 col-xl-3">
            <div class="card border-0 shadow-sm h-100 rounded-3 border-start border-4 border-info">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <span class="text-muted small fw-semibold text-uppercase">WiFi &amp; Rentals Breakdown</span>
                        <div class="rounded-circle bg-info-subtle text-info p-2 d-flex align-items-center justify-content-center" style="width: 38px; height: 38px;">
                            <i class="fa-solid fa-wifi"></i>
                        </div>
                    </div>
                    <div class="d-flex justify-content-between small text-muted">
                        <span><i class="fa-solid fa-wifi text-primary me-1"></i>WiFi:</span>
                        <strong class="text-dark">ETB {{ number_format($counters['wifi_amount'], 2) }}</strong>
                    </div>
                    <div class="d-flex justify-content-between small text-muted mt-1">
                        <span><i class="fa-solid fa-building text-danger me-1"></i>Rent:</span>
                        <strong class="text-dark">ETB {{ number_format($counters['rental_amount'], 2) }}</strong>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Main Content Card --}}
    <div class="card border-0 shadow-sm mb-4 rounded-3 overflow-hidden">
        {{-- Navigation Tabs --}}
        <div class="card-header bg-white border-bottom-0 pt-3 px-3">
            <ul class="nav nav-tabs card-header-tabs gap-1">
                <li class="nav-item">
                    <a class="nav-link fw-semibold {{ $tab === 'all' ? 'active text-primary fw-bold' : 'text-secondary' }}" 
                       href="{{ route('utility-expenses.index', array_merge(request()->except('page', 'tab'), ['tab' => 'all'])) }}">
                        <i class="fa-solid fa-list me-1"></i> All Utilities (ሁሉንም)
                        <span class="badge bg-secondary ms-1">{{ $counters['total_count'] }}</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link fw-semibold {{ $tab === 'pending' ? 'active text-warning-emphasis fw-bold' : 'text-secondary' }}" 
                       href="{{ route('utility-expenses.index', array_merge(request()->except('page', 'tab'), ['tab' => 'pending'])) }}">
                        <i class="fa-solid fa-clock-rotate-left me-1"></i> Assigned to Pay (ክፍያ በመጠባበቅ ላይ)
                        @if($counters['pending_count'] > 0)
                            <span class="badge bg-warning text-dark ms-1">{{ $counters['pending_count'] }}</span>
                        @endif
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link fw-semibold {{ $tab === 'paid' ? 'active text-success fw-bold' : 'text-secondary' }}" 
                       href="{{ route('utility-expenses.index', array_merge(request()->except('page', 'tab'), ['tab' => 'paid'])) }}">
                        <i class="fa-solid fa-circle-check me-1"></i> Paid History (የተከፈሉ)
                        <span class="badge bg-success ms-1">{{ $counters['paid_count'] }}</span>
                    </a>
                </li>
            </ul>
        </div>

        <div class="card-body p-0">
            {{-- Filter & Search Bar --}}
            <div class="p-3 bg-light border-bottom">
                <form method="GET" action="{{ route('utility-expenses.index') }}" class="row g-2 align-items-center">
                    <input type="hidden" name="tab" value="{{ $tab }}">
                    
                    <div class="col-md-3">
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-white"><i class="fa-solid fa-magnifying-glass text-muted"></i></span>
                            <input type="text" name="search" class="form-control" placeholder="Search voucher, title, ref..." value="{{ request('search') }}">
                        </div>
                    </div>

                    <div class="col-md-3">
                        <select name="utility_type" class="form-select form-select-sm" onchange="this.form.submit()">
                            <option value="">All Utility Reasons (ሁሉም ዓይነቶች)</option>
                            @foreach($utilityTypes as $typeKey => $cfg)
                                <option value="{{ $typeKey }}" {{ request('utility_type') === $typeKey ? 'selected' : '' }}>
                                    {{ $cfg['label'] }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-3">
                        <select name="coa_id" class="form-select form-select-sm" onchange="this.form.submit()">
                            <option value="">All COA Accounts (ሁሉም ሂሳቦች)</option>
                            @foreach($coaAccounts as $coa)
                                <option value="{{ $coa->id }}" {{ request('coa_id') == $coa->id ? 'selected' : '' }}>
                                    [{{ $coa->code }}] {{ $coa->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-3 d-flex gap-2">
                        <button type="submit" class="btn btn-sm btn-secondary w-100">
                            <i class="fa-solid fa-filter me-1"></i> Filter
                        </button>
                        @if(request()->hasAny(['search', 'utility_type', 'coa_id', 'date_from', 'date_to', 'assigned_to']))
                            <a href="{{ route('utility-expenses.index', ['tab' => $tab]) }}" class="btn btn-sm btn-outline-danger" title="Clear filters">
                                <i class="fa-solid fa-xmark"></i>
                            </a>
                        @endif
                    </div>
                </form>
            </div>

            {{-- Table --}}
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-3" style="width: 130px;">Voucher #</th>
                            <th>Reason / Utility Type</th>
                            <th>Amount (ETB)</th>
                            <th>Funding COA Account</th>
                            <th>Assigned to Pay</th>
                            <th>Status</th>
                            <th>Period / Due</th>
                            <th>Bill Slip</th>
                            <th class="pe-3 text-end" style="min-width: 140px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($utilityExpenses as $exp)
                        @php
                            $cfg = $exp->type_config;
                            $user = auth()->user();
                            $userRoleNames = strtolower(implode(' ', $user->getRoleNames()->toArray()));
                            $canPay = ($exp->status === \App\Models\UtilityExpense::STATUS_PENDING) && (
                                $exp->assigned_to === $user->id ||
                                str_contains($userRoleNames, 'finance_head') ||
                                str_contains($userRoleNames, 'finance_manager') ||
                                $user->hasAnyRole(['Finance head', 'finance_head', 'admin', 'global_admin'])
                            );
                        @endphp
                        <tr>
                            <td class="ps-3">
                                <span class="fw-bold text-dark font-monospace">{{ $exp->expense_number }}</span>
                                @if($exp->priority === 'urgent')
                                    <span class="badge bg-danger text-white d-block mt-1" style="font-size:0.65rem;">URGENT</span>
                                @elseif($exp->priority === 'high')
                                    <span class="badge bg-warning text-dark d-block mt-1" style="font-size:0.65rem;">HIGH</span>
                                @endif
                            </td>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <div class="rounded-circle bg-{{ $cfg['color'] }}-subtle text-{{ $cfg['color'] }} p-2 d-flex align-items-center justify-content-center flex-shrink-0" style="width: 32px; height: 32px;">
                                        <i class="{{ $cfg['icon'] }}"></i>
                                    </div>
                                    <div>
                                        <div class="fw-bold text-dark">{{ $exp->title }}</div>
                                        <div class="small text-muted">{{ $cfg['short_label'] }} @if($exp->reference_no)<span class="ms-1 font-monospace">Ref: {{ $exp->reference_no }}</span>@endif</div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <span class="fw-bold fs-6 text-dark font-monospace">
                                    {{ number_format($exp->amount, 2) }}
                                </span>
                                <small class="text-muted d-block" style="font-size:0.7rem;">ETB</small>
                            </td>
                            <td>
                                @if($exp->coa)
                                    <div class="text-truncate" style="max-width: 200px;" title="[{{ $exp->coa->code }}] {{ $exp->coa->name }}">
                                        <span class="badge bg-light text-primary border font-monospace">
                                            [{{ $exp->coa->code }}]
                                        </span>
                                        <span class="fw-semibold small text-dark ms-1">{{ $exp->coa->name }}</span>
                                    </div>
                                    <div class="text-muted" style="font-size: 0.72rem;">
                                        Bal: ETB {{ number_format($exp->coa->current_balance ?? 0, 2) }}
                                    </div>
                                @else
                                    <span class="text-muted small">N/A</span>
                                @endif
                            </td>
                            <td>
                                @if($exp->assignedPerson)
                                    <div class="d-flex align-items-center">
                                        <div class="avatar-sm bg-warning-subtle text-dark fw-bold rounded-circle me-2 d-flex align-items-center justify-content-center" style="width: 28px; height: 28px; font-size: 0.75rem;">
                                            {{ strtoupper(substr($exp->assignedPerson->name, 0, 1)) }}
                                        </div>
                                        <div>
                                            <div class="fw-semibold text-dark small">{{ $exp->assignedPerson->name }}</div>
                                            <small class="text-muted" style="font-size: 0.7rem;">{{ $exp->assignedPerson->employee->role_title ?? $exp->assignedPerson->employee->department ?? 'Staff' }}</small>
                                        </div>
                                    </div>
                                @else
                                    <span class="text-muted small">Unassigned</span>
                                @endif
                            </td>
                            <td>
                                {!! $exp->status_badge !!}
                                @if($exp->status === \App\Models\UtilityExpense::STATUS_PAID && $exp->paid_at)
                                    <div class="text-muted mt-1" style="font-size: 0.7rem;">
                                        <i class="fa-regular fa-clock me-1"></i>{{ $exp->paid_at->format('d M Y') }}
                                        @if($exp->payer)
                                            <span class="d-block text-truncate">By: {{ $exp->payer->name }}</span>
                                        @endif
                                    </div>
                                @endif
                            </td>
                            <td>
                                @if($exp->billing_period)
                                    <div class="small fw-semibold text-dark"><i class="fa-regular fa-calendar me-1 text-secondary"></i>{{ $exp->billing_period }}</div>
                                @endif
                                @if($exp->due_date)
                                    <small class="text-muted" style="font-size: 0.72rem;">Due: {{ $exp->due_date->format('d M Y') }}</small>
                                @endif
                                @if(!$exp->billing_period && !$exp->due_date)
                                    <small class="text-muted">{{ $exp->created_at->format('d M Y') }}</small>
                                @endif
                            </td>
                            <td>
                                @if($exp->bill_attachment)
                                    <a href="{{ asset('storage/' . $exp->bill_attachment) }}" target="_blank" class="badge bg-light text-primary border py-1 px-2 text-decoration-none shadow-xs" title="View Bill Slip">
                                        <i class="fa-solid fa-file-invoice me-1"></i>View Bill
                                    </a>
                                @endif
                                @if($exp->payment_receipt)
                                    <a href="{{ asset('storage/' . $exp->payment_receipt) }}" target="_blank" class="badge bg-success-subtle text-success border border-success py-1 px-2 text-decoration-none shadow-xs mt-1 d-inline-block" title="View Payment Receipt">
                                        <i class="fa-solid fa-receipt me-1"></i>Receipt
                                    </a>
                                @endif
                                @if(!$exp->bill_attachment && !$exp->payment_receipt)
                                    <span class="text-muted small">—</span>
                                @endif
                            </td>
                            <td class="pe-3 text-end">
                                <div class="d-flex justify-content-end gap-1">
                                    {{-- Disburse Payment Action Button --}}
                                    @if($canPay)
                                        <button type="button" class="btn btn-sm btn-success fw-bold py-1 px-2 shadow-sm" data-bs-toggle="modal" data-bs-target="#payModal{{ $exp->id }}" title="Disburse payment for this utility expense">
                                            <i class="fa-solid fa-money-bill-wave me-1"></i>Pay
                                        </button>
                                    @endif

                                    {{-- Details Button --}}
                                    <button type="button" class="btn btn-sm btn-light border py-1 px-2" data-bs-toggle="modal" data-bs-target="#viewModal{{ $exp->id }}" title="View Full Details">
                                        <i class="fa-solid fa-eye"></i>
                                    </button>

                                    {{-- Delete / Cancel Button (Finance Head / Admin if not paid) --}}
                                    @if($exp->status !== \App\Models\UtilityExpense::STATUS_PAID && ($isFinanceHead || $isAdmin))
                                        <form method="POST" action="{{ route('utility-expenses.destroy', $exp->id) }}" class="d-inline" onsubmit="return confirm('Are you sure you want to delete utility expense #{{ $exp->expense_number }}?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-outline-danger py-1 px-2" title="Delete">
                                                <i class="fa-solid fa-trash-can"></i>
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>

                        {{-- Record Payment Modal --}}
                        @if($canPay)
                        <div class="modal fade" id="payModal{{ $exp->id }}" tabindex="-1" aria-hidden="true">
                            <div class="modal-dialog modal-dialog-centered">
                                <div class="modal-content border-0 shadow rounded-4 overflow-hidden">
                                    <form method="POST" action="{{ route('utility-expenses.pay', $exp->id) }}" enctype="multipart/form-data">
                                        @csrf
                                        <div class="modal-header bg-success text-white py-3 px-4">
                                            <h5 class="modal-title fw-bold">
                                                <i class="fa-solid fa-money-bill-wave me-2"></i>Disburse Utility Payment
                                            </h5>
                                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                        </div>
                                        <div class="modal-body p-4">
                                            {{-- Expense Summary Card --}}
                                            <div class="p-3 bg-light rounded-3 border mb-3">
                                                <div class="d-flex justify-content-between align-items-center mb-1">
                                                    <span class="badge bg-primary-subtle text-primary fw-bold font-monospace">{{ $exp->expense_number }}</span>
                                                    <span class="fw-bold fs-5 text-success">ETB {{ number_format($exp->amount, 2) }}</span>
                                                </div>
                                                <div class="fw-bold text-dark">{{ $exp->title }}</div>
                                                <div class="small text-muted mt-1">
                                                    <i class="fa-solid fa-sitemap me-1 text-primary"></i>COA: <strong>[{{ $exp->coa->code ?? '' }}] {{ $exp->coa->name ?? 'General COA' }}</strong>
                                                </div>
                                            </div>

                                            <div class="row g-3">
                                                <div class="col-md-6">
                                                    <label class="form-label fw-bold small">Payment Amount (ETB) <span class="text-danger">*</span></label>
                                                    <input type="number" step="0.01" min="0.01" name="paid_amount" class="form-control fw-bold fs-6" value="{{ $exp->amount }}" required>
                                                </div>

                                                <div class="col-md-6">
                                                    <label class="form-label fw-bold small">Payment Date <span class="text-danger">*</span></label>
                                                    <input type="date" name="payment_date" class="form-control" value="{{ date('Y-m-d') }}" required>
                                                </div>

                                                <div class="col-md-6">
                                                    <label class="form-label fw-bold small">Payment Method <span class="text-danger">*</span></label>
                                                    <select name="payment_method" class="form-select" required>
                                                        <option value="bank_transfer">Bank Transfer / CBE Birr / Telebirr</option>
                                                        <option value="cash">Cash (ጥሬ ገንዘብ)</option>
                                                        <option value="check">Bank Cheque (ቼክ)</option>
                                                        <option value="other">Other Payment Method</option>
                                                    </select>
                                                </div>

                                                <div class="col-md-6">
                                                    <label class="form-label fw-bold small">Transaction Ref / Cheque #</label>
                                                    <input type="text" name="payment_reference" class="form-control" placeholder="e.g. TXN-9988123 or Chq #">
                                                </div>

                                                <div class="col-12">
                                                    <label class="form-label fw-bold small">Payment Receipt / Bank Slip</label>
                                                    <input type="file" name="payment_receipt" class="form-control" accept=".pdf,.jpg,.jpeg,.png,.webp">
                                                    <small class="text-muted">Attach stamped bank slip, transfer screenshot, or receipt.</small>
                                                </div>

                                                <div class="col-12">
                                                    <label class="form-label fw-bold small">Payment Notes / Disbursal Remarks</label>
                                                    <textarea name="payment_notes" class="form-control" rows="2" placeholder="Optional payment disbursal remarks..."></textarea>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="modal-footer bg-light py-2 px-4">
                                            <button type="button" class="btn btn-secondary rounded-pill px-3" data-bs-dismiss="modal">Cancel</button>
                                            <button type="submit" class="btn btn-success fw-bold rounded-pill px-4">
                                                <i class="fa-solid fa-check me-1"></i> Confirm &amp; Disburse Payment
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                        @endif

                        {{-- View Details Modal --}}
                        <div class="modal fade" id="viewModal{{ $exp->id }}" tabindex="-1" aria-hidden="true">
                            <div class="modal-dialog modal-lg modal-dialog-centered">
                                <div class="modal-content border-0 shadow rounded-4 overflow-hidden">
                                    <div class="modal-header bg-dark text-white py-3 px-4">
                                        <div class="d-flex align-items-center gap-2">
                                            <i class="{{ $cfg['icon'] }} fs-5 text-warning"></i>
                                            <h5 class="modal-title fw-bold mb-0">Utility Expense #{{ $exp->expense_number }}</h5>
                                        </div>
                                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                    </div>
                                    <div class="modal-body p-4">
                                        <div class="row g-3">
                                            <div class="col-md-8">
                                                <h4 class="fw-bold text-dark mb-1">{{ $exp->title }}</h4>
                                                <p class="text-muted small mb-3">{{ $cfg['label'] }}</p>
                                            </div>
                                            <div class="col-md-4 text-md-end">
                                                <div class="text-muted small">Expense Amount</div>
                                                <h3 class="fw-bold text-success mb-0">ETB {{ number_format($exp->amount, 2) }}</h3>
                                                <div class="mt-1">{!! $exp->status_badge !!}</div>
                                            </div>

                                            <div class="col-12"><hr class="my-1"></div>

                                            <div class="col-md-6">
                                                <div class="p-3 bg-light rounded-3 border">
                                                    <div class="fw-bold text-muted small text-uppercase mb-2"><i class="fa-solid fa-sitemap me-1 text-primary"></i>Funding COA Account</div>
                                                    @if($exp->coa)
                                                        <div class="fw-bold text-dark fs-6">[{{ $exp->coa->code }}] {{ $exp->coa->name }}</div>
                                                        <div class="small text-muted mt-1">Type: {{ ucfirst($exp->coa->type ?? 'Asset') }} | Bal: ETB {{ number_format($exp->coa->current_balance ?? 0, 2) }}</div>
                                                    @else
                                                        <span class="text-muted">No COA Account</span>
                                                    @endif
                                                </div>
                                            </div>

                                            <div class="col-md-6">
                                                <div class="p-3 bg-light rounded-3 border">
                                                    <div class="fw-bold text-muted small text-uppercase mb-2"><i class="fa-solid fa-user-check me-1 text-warning"></i>Assigned Person to Pay</div>
                                                    @if($exp->assignedPerson)
                                                        <div class="fw-bold text-dark fs-6">{{ $exp->assignedPerson->name }}</div>
                                                        <div class="small text-muted mt-1">{{ $exp->assignedPerson->email }} | {{ $exp->assignedPerson->employee->role_title ?? 'Finance Staff' }}</div>
                                                    @else
                                                        <span class="text-muted">Unassigned</span>
                                                    @endif
                                                </div>
                                            </div>

                                            <div class="col-md-4">
                                                <small class="text-muted d-block">Billing Period</small>
                                                <span class="fw-semibold text-dark">{{ $exp->billing_period ?: 'N/A' }}</span>
                                            </div>
                                            <div class="col-md-4">
                                                <small class="text-muted d-block">Bill / Invoice Reference #</small>
                                                <span class="fw-semibold text-dark font-monospace">{{ $exp->reference_no ?: 'N/A' }}</span>
                                            </div>
                                            <div class="col-md-4">
                                                <small class="text-muted d-block">Due Date</small>
                                                <span class="fw-semibold text-dark">{{ $exp->due_date ? $exp->due_date->format('d M Y') : 'N/A' }}</span>
                                            </div>

                                            @if($exp->description)
                                            <div class="col-12">
                                                <small class="text-muted d-block">Description &amp; Notes</small>
                                                <div class="p-2 bg-light rounded border text-dark small">{{ $exp->description }}</div>
                                            </div>
                                            @endif

                                            {{-- Payment Disbursal Details if Paid --}}
                                            @if($exp->status === \App\Models\UtilityExpense::STATUS_PAID)
                                            <div class="col-12">
                                                <div class="p-3 bg-success-subtle rounded-3 border border-success">
                                                    <h6 class="fw-bold text-success mb-2"><i class="fa-solid fa-circle-check me-1"></i>Payment Disbursal Record</h6>
                                                    <div class="row g-2 small">
                                                        <div class="col-md-4">
                                                            <span class="text-muted">Disbursed Amount:</span>
                                                            <strong class="d-block text-dark">ETB {{ number_format($exp->paid_amount ?: $exp->amount, 2) }}</strong>
                                                        </div>
                                                        <div class="col-md-4">
                                                            <span class="text-muted">Payment Method:</span>
                                                            <strong class="d-block text-dark">{{ ucfirst(str_replace('_', ' ', $exp->payment_method ?? 'N/A')) }}</strong>
                                                        </div>
                                                        <div class="col-md-4">
                                                            <span class="text-muted">Transaction Ref:</span>
                                                            <strong class="d-block text-dark font-monospace">{{ $exp->payment_reference ?: 'N/A' }}</strong>
                                                        </div>
                                                        <div class="col-md-6 mt-2">
                                                            <span class="text-muted">Paid By:</span>
                                                            <strong class="d-block text-dark">{{ $exp->payer->name ?? 'Staff' }}</strong>
                                                        </div>
                                                        <div class="col-md-6 mt-2">
                                                            <span class="text-muted">Payment Date:</span>
                                                            <strong class="d-block text-dark">{{ $exp->payment_date ? $exp->payment_date->format('d M Y') : ($exp->paid_at ? $exp->paid_at->format('d M Y') : 'N/A') }}</strong>
                                                        </div>
                                                        @if($exp->payment_notes)
                                                        <div class="col-12 mt-2">
                                                            <span class="text-muted">Payment Remarks:</span>
                                                            <div class="text-dark">{{ $exp->payment_notes }}</div>
                                                        </div>
                                                        @endif
                                                    </div>
                                                </div>
                                            </div>
                                            @endif

                                            {{-- Attachments --}}
                                            <div class="col-12">
                                                <div class="d-flex gap-2 flex-wrap">
                                                    @if($exp->bill_attachment)
                                                        <a href="{{ asset('storage/' . $exp->bill_attachment) }}" target="_blank" class="btn btn-sm btn-outline-primary">
                                                            <i class="fa-solid fa-file-invoice me-1"></i> View Bill / Invoice Slip
                                                        </a>
                                                    @endif
                                                    @if($exp->payment_receipt)
                                                        <a href="{{ asset('storage/' . $exp->payment_receipt) }}" target="_blank" class="btn btn-sm btn-outline-success">
                                                            <i class="fa-solid fa-receipt me-1"></i> View Payment Receipt
                                                        </a>
                                                    @endif
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="modal-footer bg-light py-2 px-4">
                                        <button type="button" class="btn btn-secondary rounded-pill px-3" data-bs-dismiss="modal">Close</button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        @empty
                        <tr>
                            <td colspan="9" class="text-center py-5 text-muted">
                                <i class="fa-solid fa-bolt fs-1 text-warning opacity-50 mb-3 d-block"></i>
                                <h6 class="fw-bold">No Utility Expenses Found</h6>
                                <p class="small mb-3">Click below to record a new utility bill (WiFi, Rent, Electricity, Water, etc.).</p>
                                <button type="button" class="btn btn-sm btn-warning text-dark fw-bold px-3 shadow-sm" data-bs-toggle="modal" data-bs-target="#createUtilityModal">
                                    <i class="fa-solid fa-plus-circle me-1"></i> New Utility Expense
                                </button>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{-- Pagination --}}
            @if($utilityExpenses->hasPages())
                <div class="p-3 border-top bg-light d-flex justify-content-between align-items-center">
                    <div class="small text-muted">Showing {{ $utilityExpenses->firstItem() }} - {{ $utilityExpenses->lastItem() }} of {{ $utilityExpenses->total() }} records</div>
                    {{ $utilityExpenses->links() }}
                </div>
            @endif
        </div>
    </div>
</div>

{{-- Create Utility Expense Modal --}}
<div class="modal fade" id="createUtilityModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow rounded-4 overflow-hidden">
            <form method="POST" action="{{ route('utility-expenses.store') }}" enctype="multipart/form-data">
                @csrf
                <div class="modal-header bg-warning bg-gradient text-dark py-3 px-4">
                    <h5 class="modal-title fw-bold">
                        <i class="fa-solid fa-bolt me-2 text-dark"></i>New Utility Expense (አዲስ የፍጆታ ወጪ መዝግብ)
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="row g-3">
                        {{-- Reason / Utility Type --}}
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Reason / Utility Type (የወጪው ዓይነት) <span class="text-danger">*</span></label>
                            <select name="utility_type" id="createUtilityType" class="form-select fw-semibold" onchange="onUtilityTypeChanged(this)" required>
                                <option value="" disabled selected>-- Select Utility Reason --</option>
                                @foreach($utilityTypes as $k => $item)
                                    <option value="{{ $k }}" data-default-title="{{ $item['short_label'] }} Payment">
                                        {{ $item['label'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        {{-- Expense Title / Subject --}}
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Expense Title / Description (የወጪው ስም) <span class="text-danger">*</span></label>
                            <input type="text" name="title" id="createUtilityTitle" class="form-control fw-semibold" placeholder="e.g. WiFi Bill - Head Office or Bole Office Rent" required>
                        </div>

                        {{-- Amount --}}
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Amount (ETB) <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text fw-bold bg-light">ETB</span>
                                <input type="number" step="0.01" min="0.01" name="amount" class="form-control fw-bold fs-5 text-dark" placeholder="0.00" required>
                            </div>
                        </div>

                        {{-- Account from COA --}}
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Select Account from COA (የሂሳብ ቋት ይምረጡ) <span class="text-danger">*</span></label>
                            <select name="coa_id" class="form-select fw-semibold" required>
                                <option value="" disabled selected>-- Select COA Account to Pay From --</option>
                                @foreach($coaAccounts as $acc)
                                    <option value="{{ $acc->id }}">
                                        [{{ $acc->code }}] {{ $acc->name }} (ETB {{ number_format($acc->current_balance ?? 0, 2) }})
                                    </option>
                                @endforeach
                            </select>
                            <small class="text-muted d-block mt-1" style="font-size:0.75rem;">
                                <i class="fa-solid fa-circle-info me-1"></i>Funds will be deducted from this account when payment is disbursed.
                            </small>
                        </div>

                        {{-- Assign Person to Pay --}}
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Send to Assigned Person to Pay (ክፍያ የሚፈጽም ሰው) <span class="text-danger">*</span></label>
                            <select name="assigned_to" class="form-select fw-semibold" required>
                                <option value="" disabled selected>-- Select Person / Cashier / Staff to Pay --</option>
                                @foreach($assignableUsers as $u)
                                    <option value="{{ $u->id }}" {{ Auth::id() == $u->id ? 'selected' : '' }}>
                                        👤 {{ $u->name }} ({{ $u->employee->role_title ?? $u->employee->department ?? 'Staff' }})
                                    </option>
                                @endforeach
                            </select>
                            <small class="text-muted d-block mt-1" style="font-size:0.75rem;">
                                <i class="fa-solid fa-paper-plane me-1"></i>This person will receive the task in their queue to disburse payment.
                            </small>
                        </div>

                        {{-- Billing Period --}}
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Billing Period (የክፍያ ወቅት / ወር)</label>
                            <input type="text" name="billing_period" class="form-control" placeholder="e.g. October 2026, Meskerem 2018, Q3">
                        </div>

                        {{-- Bill Reference / Invoice # --}}
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Bill Reference / Invoice # (ደረሰኝ / ቁጥር)</label>
                            <input type="text" name="reference_no" class="form-control font-monospace" placeholder="e.g. INV-2026-1049, Ethio-Tel #">
                        </div>

                        {{-- Due Date --}}
                        <div class="col-md-3">
                            <label class="form-label fw-bold">Due Date (የመክፈያ ቀን)</label>
                            <input type="date" name="due_date" class="form-control">
                        </div>

                        {{-- Priority --}}
                        <div class="col-md-3">
                            <label class="form-label fw-bold">Priority (አስቸኳይነት)</label>
                            <select name="priority" class="form-select">
                                <option value="normal" selected>Normal</option>
                                <option value="high">High</option>
                                <option value="urgent">Urgent</option>
                                <option value="low">Low</option>
                            </select>
                        </div>

                        {{-- Bill Attachment --}}
                        <div class="col-12">
                            <label class="form-label fw-bold">Bill Slip / Invoice Attachment (የቢል / ደረሰኝ ሰነድ)</label>
                            <input type="file" name="bill_attachment" class="form-control" accept=".pdf,.jpg,.jpeg,.png,.webp">
                            <small class="text-muted">Upload bill document, scanned rental agreement, Ethio Telecom or EEU electricity bill slip.</small>
                        </div>

                        {{-- Description / Notes --}}
                        <div class="col-12">
                            <label class="form-label fw-bold">Description / Additional Notes</label>
                            <textarea name="description" class="form-control" rows="2" placeholder="Additional details, landlord account details, service ID, meter number, etc..."></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light py-3 px-4">
                    <button type="button" class="btn btn-secondary rounded-pill px-3" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning text-dark fw-bold rounded-pill px-4 shadow-sm">
                        <i class="fa-solid fa-paper-plane me-1"></i> Send to Assigned Person to Pay
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function onUtilityTypeChanged(selectEl) {
    const selectedOpt = selectEl.options[selectEl.selectedIndex];
    const defaultTitle = selectedOpt.getAttribute('data-default-title');
    const titleInput = document.getElementById('createUtilityTitle');
    if (titleInput && defaultTitle && (!titleInput.value || titleInput.value.trim() === '')) {
        titleInput.value = defaultTitle;
    }
}
</script>
@endsection
