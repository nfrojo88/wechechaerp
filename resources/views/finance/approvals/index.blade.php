@extends('layouts.app')

@php
    $authUser = auth()->user();
    $authUserId = auth()->id();
    $authRoleNames = strtolower(implode(' ', $authUser ? $authUser->getRoleNames()->toArray() : []));
    $isAuditorUser = !empty($isAuditor) || ($authUser && ($authUser->hasAnyRole(['auditor', 'Auditor', 'audit', 'internal_auditor', 'audit_team']) || str_contains($authRoleNames, 'audit')));
@endphp

@section('title', $isAuditorUser ? 'Expense Audit & Tracking' : 'Expense Track & Approve')

@section('content')

<div class="container-fluid px-2 px-md-3">
    <!-- Header -->
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
        <div>
            <h1 class="h3 mb-1 text-dark fw-bold">
                <i class="fa-solid fa-file-invoice-dollar text-primary me-2"></i>{{ $isAuditorUser ? 'Expense Audit & Tracking' : 'Expense Track & Approve' }}
            </h1>
            <p class="text-muted small mb-0">{{ $isAuditorUser ? 'Internal Audit stream of company expenses, approvals, and disbursement records for financial oversight.' : 'Track all employee expense requests, operational expenses, GM/HR approvals, and paid history in one unified location.' }}</p>
        </div>
        <div class="d-flex gap-2">
            @if(!$isAuditorUser)
                <a href="{{ url('/expense-requests') }}" class="btn btn-outline-primary btn-sm rounded-3 px-3">
                    <i class="fa-solid fa-hand-holding-dollar me-1"></i> Ask Money Portal
                </a>
                <a href="{{ route('expenses.create') }}" class="btn btn-primary btn-sm rounded-3 px-3 shadow-sm">
                    <i class="fa-solid fa-plus me-1"></i> New Direct Expense
                </a>
            @else
                <a href="{{ route('dashboard.audit') }}" class="btn btn-outline-info btn-sm rounded-3 px-3">
                    <i class="fa-solid fa-chart-pie me-1"></i> Audit Dashboard
                </a>
            @endif
        </div>
    </div>

    <!-- Quick Filter Navigation Tabs -->
    <div class="card border-0 shadow-sm mb-4 rounded-4 overflow-hidden">
        <div class="card-body p-2 bg-light">
            <ul class="nav nav-pills nav-fill gap-1 flex-nowrap overflow-auto" id="expenseTabs" style="white-space: nowrap;">
                <li class="nav-item">
                    <a class="nav-link rounded-3 fw-semibold py-2 {{ $activeTab === 'all' ? 'active shadow-sm bg-dark text-white' : 'text-secondary bg-white' }}" 
                       href="{{ request()->fullUrlWithQuery(['tab' => 'all', 'page' => 1]) }}">
                        <i class="fa-solid fa-list me-1"></i> All Expenses
                        <span class="badge {{ $activeTab === 'all' ? 'bg-primary text-white' : 'bg-secondary' }} ms-1">{{ $tabCounts['all'] }}</span>
                    </a>
                </li>
                @if(!empty($isAdmin) || !empty($isHR) || $isAuditorUser)
                <li class="nav-item">
                    <a class="nav-link rounded-3 fw-semibold py-2 {{ $activeTab === 'pending_hr' ? 'active shadow-sm bg-warning text-dark' : 'text-secondary bg-white' }}" 
                       href="{{ request()->fullUrlWithQuery(['tab' => 'pending_hr', 'page' => 1]) }}">
                        <i class="fa-solid fa-user-check me-1"></i> HR Review
                        <span class="badge {{ $activeTab === 'pending_hr' ? 'bg-dark text-white' : 'bg-warning text-dark' }} ms-1">{{ $tabCounts['pending_hr'] }}</span>
                    </a>
                </li>
                @endif
                @if(!empty($isAdmin) || !empty($isGM) || $isAuditorUser)
                <li class="nav-item">
                    <a class="nav-link rounded-3 fw-semibold py-2 {{ $activeTab === 'pending_gm' ? 'active shadow-sm bg-info text-white' : 'text-secondary bg-white' }}" 
                       href="{{ request()->fullUrlWithQuery(['tab' => 'pending_gm', 'page' => 1]) }}">
                        <i class="fa-solid fa-user-shield me-1"></i> GM Review
                        <span class="badge {{ $activeTab === 'pending_gm' ? 'bg-white text-info' : 'bg-info text-white' }} ms-1">{{ $tabCounts['pending_gm'] }}</span>
                    </a>
                </li>
                @endif
                <li class="nav-item">
                    <a class="nav-link rounded-3 fw-semibold py-2 {{ in_array($activeTab, ['finance_queue', 'not_paid', 'unpaid']) ? 'active shadow-sm bg-warning text-dark' : 'text-secondary bg-white' }}" 
                       href="{{ request()->fullUrlWithQuery(['tab' => 'not_paid', 'page' => 1]) }}">
                        <i class="fa-solid fa-hourglass-half me-1 text-warning"></i> {{ $isAuditorUser ? 'Unpaid Records' : 'Not Paid / Pending Payment' }}
                        <span class="badge {{ in_array($activeTab, ['finance_queue', 'not_paid', 'unpaid']) ? 'bg-dark text-white' : 'bg-warning text-dark' }} ms-1">{{ $tabCounts['not_paid'] ?? 0 }}</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link rounded-3 fw-semibold py-2 {{ $activeTab === 'paid' ? 'active shadow-sm bg-success text-white' : 'text-secondary bg-white' }}" 
                       href="{{ request()->fullUrlWithQuery(['tab' => 'paid', 'page' => 1]) }}">
                        <i class="fa-solid fa-circle-check me-1"></i> Paid History
                        <span class="badge {{ $activeTab === 'paid' ? 'bg-white text-success' : 'bg-success text-white' }} ms-1">{{ $tabCounts['paid'] }}</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link rounded-3 fw-semibold py-2 {{ $activeTab === 'rejected' ? 'active shadow-sm bg-danger text-white' : 'text-secondary bg-white' }}" 
                       href="{{ request()->fullUrlWithQuery(['tab' => 'rejected', 'page' => 1]) }}">
                        <i class="fa-solid fa-circle-xmark me-1"></i> Rejected
                        <span class="badge {{ $activeTab === 'rejected' ? 'bg-white text-danger' : 'bg-danger text-white' }} ms-1">{{ $tabCounts['rejected'] }}</span>
                    </a>
                </li>
            </ul>
        </div>
    </div>


    <!-- Filter Bar Card -->
    <div class="card border-0 shadow-sm mb-4 rounded-4">
        <div class="card-body p-3">
            <form method="GET" action="{{ url('/expenses') }}" class="row g-2 align-items-end">
                <input type="hidden" name="tab" value="{{ $activeTab }}">
                
                <div class="col-md-3">
                    <label class="form-label small text-muted mb-1 fw-bold">Search</label>
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-light border-end-0"><i class="fa-solid fa-magnifying-glass text-muted"></i></span>
                        <input type="text" name="search" class="form-control border-start-0" placeholder="REQ #, employee, keyword..." value="{{ request('search') }}">
                    </div>
                </div>

                <div class="col-md-2">
                    <label class="form-label small text-muted mb-1 fw-bold">Project / Dept</label>
                    <select name="project" class="form-select form-select-sm">
                        <option value="all">All Projects & Depts</option>
                        @foreach($projects as $p)
                            <option value="{{ $p->name }}" {{ request('project') == $p->name ? 'selected' : '' }}>{{ $p->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-2">
                    <label class="form-label small text-muted mb-1 fw-bold">Category</label>
                    <select name="category" class="form-select form-select-sm">
                        <option value="all">All Categories</option>
                        @php
                            $catLabels = [
                                'Service' => 'Service (አገልግሎት)',
                                'Transport' => 'Transport (ትራንስፖርት)',
                                'Loading & Unloading' => 'Loading & Unloading (መጫን እና ማውረድ)',
                                'Contract Work' => 'Contract Work (የኮንትራት ስራ)',
                                'Office Material' => 'Office Material (የቢሮ እቃ)',
                                'Maintenance' => 'Maintenance & Repairs (ጥገና)',
                                'Purchase' => 'Purchase (ግዢ)',
                                'Other' => 'Other (ሌሎች)',
                            ];
                        @endphp
                        @foreach($categories as $cat)
                            <option value="{{ $cat }}" {{ request('category') == $cat ? 'selected' : '' }}>{{ $catLabels[$cat] ?? $cat }}</option>
                        @endforeach
                    </select>
                </div>


                <div class="col-md-3">
                    <label class="form-label small text-muted mb-1 fw-bold">Date Range</label>
                    <div class="d-flex gap-1">
                        <input type="date" name="date_from" class="form-control form-control-sm" value="{{ request('date_from') }}">
                        <input type="date" name="date_to" class="form-control form-control-sm" value="{{ request('date_to') }}">
                    </div>
                </div>

                <div class="col-md-2 d-flex gap-2">
                    <button type="submit" class="btn btn-sm btn-dark w-100 rounded-3">
                        <i class="fa-solid fa-filter me-1"></i> Filter
                    </button>
                    @if(request()->hasAny(['search', 'project', 'category', 'date_from', 'date_to']))
                        <a href="{{ url('/expenses?tab=' . $activeTab) }}" class="btn btn-sm btn-outline-danger rounded-3" title="Clear Filters">
                            <i class="fa-solid fa-rotate-left"></i>
                        </a>
                    @endif
                </div>
            </form>
        </div>
    </div>

    <!-- Main Expenses Table Card -->
    <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" style="min-width: 1000px;">
                    <thead class="table-light text-uppercase small text-muted fw-bold">
                        <tr>
                            <th class="ps-4 py-3" style="width: 13%;">ID / REQ #</th>
                            <th class="py-3" style="width: 10%;">Date</th>
                            <th class="py-3" style="width: 16%;">Requester / Dept</th>
                            <th class="py-3" style="width: 13%;">Category</th>
                            <th class="py-3" style="width: 20%;">Description</th>
                            <th class="text-end py-3" style="width: 10%;">Amount</th>
                            <th class="text-center py-3" style="width: 10%;">Status</th>
                            <th class="text-end pe-4 py-3" style="width: 8%;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($paginatedItems as $item)
                            <tr>
                                <td class="ps-4">
                                    <div class="fw-bold text-dark font-monospace" style="font-size: .85rem;">{{ $item->id_formatted }}</div>
                                    <span class="badge bg-light text-muted border px-2 py-0" style="font-size: .65rem; text-transform: uppercase;">
                                        {{ str_replace('_', ' ', $item->type) }}
                                    </span>
                                </td>
                                <td>
                                    <span class="text-dark fw-semibold" style="font-size: .85rem;">{{ \Carbon\Carbon::parse($item->date)->format('M d, Y') }}</span>
                                    <div class="small text-muted" style="font-size: .7rem;">{{ \Carbon\Carbon::parse($item->date)->format('H:i') }}</div>
                                </td>
                                <td>
                                    <div class="fw-bold text-dark" style="font-size: .85rem;">{{ $item->applicant_name }}</div>
                                    <span class="text-muted" style="font-size: .75rem;">
                                        <i class="fa-solid fa-building me-1 opacity-50"></i>{{ $item->project }}
                                    </span>
                                </td>
                                <td>
                                    @php
                                        $catColor = match($item->category) {
                                            'Service' => 'primary',
                                            'Transport' => 'info',
                                            'Loading & Unloading', 'Loading / Unloading', 'Loading Unloading' => 'warning',
                                            'Contract Work' => 'dark',
                                            'Office Material' => 'secondary',
                                            'Maintenance' => 'danger',
                                            default => 'primary',
                                        };
                                        $catAmharic = match($item->category) {
                                            'Service' => 'አገልግሎት',
                                            'Transport' => 'ትራንስፖርት',
                                            'Loading & Unloading', 'Loading / Unloading', 'Loading Unloading' => 'መጫን/ማውረድ',
                                            'Contract Work' => 'ኮንትራት ስራ',
                                            'Office Material' => 'ቢሮ እቃ',
                                            'Maintenance' => 'ጥገና',
                                            default => '',
                                        };
                                    @endphp
                                    <span class="badge bg-{{ $catColor }}-subtle text-{{ $catColor }} border border-{{ $catColor }}-subtle px-2 py-1 rounded-pill" style="font-size: .75rem;">
                                        {{ $item->category }} {{ $catAmharic ? "({$catAmharic})" : '' }}
                                    </span>
                                </td>

                                <td>
                                    <div class="text-dark" style="font-size: .85rem; max-width: 260px; word-break: break-word;" title="{{ $item->description }}">
                                        {{ Str::limit($item->description, 60) }}
                                    </div>
                                    @if(!empty($item->rejection_reason))
                                        <div class="small text-danger fw-semibold" style="font-size: .72rem;">
                                            <i class="fa-solid fa-triangle-exclamation me-1"></i>{{ Str::limit($item->rejection_reason, 45) }}
                                        </div>
                                    @endif
                                </td>
                                <td class="text-end">
                                    <div class="fw-bold text-success" style="font-size: .95rem;">ETB {{ number_format($item->net_amount, 2) }}</div>
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-{{ $item->color }} rounded-pill px-2 py-1" style="font-size: .75rem;">
                                        {{ $item->status }}
                                    </span>
                                </td>
                                <td class="text-end pe-4">
                                    <div class="btn-group btn-group-sm">
                                        <!-- Detail Button -->
                                        <button type="button" class="btn btn-light border btn-sm rounded-start-3" 
                                                data-bs-toggle="modal" 
                                                data-bs-target="#detailModal{{ $item->id_raw }}_{{ $item->type }}" 
                                                title="View Details">
                                            <i class="fa-solid fa-eye text-primary"></i>
                                        </button>

                                        @if(!$isAuditorUser)
                                            @if($item->type === 'expense_request')
                                                @php $req = $item->raw_model; @endphp
                                                
                                                <!-- HR / Coordinator Review Action Button -->
                                                @if($req->status === \App\Models\ExpenseRequest::STATUS_PENDING_HR && (auth()->user()->hasAnyRole(['HR Manager', 'hr_manager', 'HR Officer', 'hr_officer', 'Coordinator', 'coordinator', 'admin', 'global_admin']) || auth()->user()->can('hr.view')))
                                                    <button type="button" class="btn btn-warning btn-sm text-dark fw-semibold" 
                                                            data-bs-toggle="modal" 
                                                            data-bs-target="#hrReviewModal{{ $req->id }}"
                                                            title="Review & Approve">
                                                        <i class="fa-solid fa-user-check me-1"></i> HR/Coordinator Review
                                                    </button>
                                                @endif

                                                <!-- GM Review Action Button -->
                                                @if($req->status === \App\Models\ExpenseRequest::STATUS_PENDING_GM && (auth()->user()->hasAnyRole(['General Manager', 'gm', 'admin', 'global_admin'])))
                                                    <button type="button" class="btn btn-info btn-sm text-white fw-semibold" 
                                                            data-bs-toggle="modal" 
                                                            data-bs-target="#gmReviewModal{{ $req->id }}"
                                                            title="GM Review">
                                                        <i class="fa-solid fa-user-shield me-1"></i> GM Review
                                                    </button>
                                                @endif

                                                <!-- Finance Assign / Pay Action Button -->
                                                @if(in_array($req->status, [\App\Models\ExpenseRequest::STATUS_APPROVED_ASSIGNED, \App\Models\ExpenseRequest::STATUS_ASSIGNED, 'Assigned to Finance', 'Approved - Assigned to Finance']))
                                                    @php
                                                        $authUser = auth()->user();
                                                        $authRoleNames = strtolower(implode(' ', $authUser->getRoleNames()->toArray()));
                                                        $isFinHeadOrAdmin = $authUser->hasAnyRole(['Finance head', 'finance_head', 'finance_manager', 'admin', 'global_admin']) 
                                                            || str_contains($authRoleNames, 'finance_head') 
                                                            || str_contains($authRoleNames, 'finance_manager') 
                                                            || str_contains($authRoleNames, 'admin');

                                                        $isAssignedToMe = (
                                                            $req->assigned_finance_staff_id == $authUser->id ||
                                                            $req->finance_staff_id == $authUser->id ||
                                                            ($req->chartOfAccount && $req->chartOfAccount->assigned_to == $authUser->id) ||
                                                            ($req->coa && $req->coa->assigned_to == $authUser->id)
                                                        );
                                                    @endphp

                                                    @if($isAssignedToMe)
                                                        <button type="button" class="btn btn-success btn-sm text-white fw-bold shadow-sm" 
                                                                data-bs-toggle="modal" 
                                                                data-bs-target="#payModal{{ $req->id }}"
                                                                title="Pay this assigned expense">
                                                            <i class="fa-solid fa-money-bill-wave me-1"></i> Pay
                                                        </button>
                                                    @elseif($isFinHeadOrAdmin)
                                                        <button type="button" class="btn btn-primary btn-sm text-white fw-semibold" 
                                                                data-bs-toggle="modal" 
                                                                data-bs-target="#financeAssignModal{{ $req->id }}"
                                                                title="Assign Account &amp; Finance Custodian">
                                                            <i class="fa-solid fa-user-tag me-1"></i> Assign
                                                        </button>
                                                    @endif
                                                @endif

                                                <!-- Paid Indicator Button -->
                                                @if($req->status === \App\Models\ExpenseRequest::STATUS_PAID)
                                                    <button type="button" class="btn btn-success-subtle text-success border border-success-subtle btn-sm fw-semibold"
                                                            data-bs-toggle="modal" 
                                                            data-bs-target="#detailModal{{ $item->id_raw }}_{{ $item->type }}">
                                                        <i class="fa-solid fa-check-circle me-1"></i> Paid
                                                    </button>
                                                @endif

                                            @elseif($item->type === 'expense')
                                                @if(strtolower($item->status_raw) === 'pending')
                                                    <form method="POST" action="{{ $item->route_approve }}" class="d-inline">
                                                        @csrf
                                                        <button type="submit" class="btn btn-success btn-sm"><i class="fa-solid fa-check"></i> Approve</button>
                                                    </form>
                                                @endif

                                            @elseif($item->type === 'purchase_request')
                                                @if($item->status_key === 'finance_queue')
                                                    @php
                                                        $prPayment = $item->raw_model->payment;
                                                        $prCoa = $item->raw_model->chartOfAccount ?? null;
                                                        $isAssignedToMe = (
                                                            ($prPayment && (int)$prPayment->assigned_finance_staff_id === (int)auth()->id()) ||
                                                            ($prCoa && (int)$prCoa->assigned_to === (int)auth()->id())
                                                        );
                                                    @endphp

                                                    @if($isAssignedToMe)
                                                        <button type="button" class="btn btn-success btn-sm text-white fw-bold shadow-sm" 
                                                                data-bs-toggle="modal" 
                                                                data-bs-target="#payPrModal{{ $item->id_raw }}"
                                                                title="Disburse payment for this Purchase Request">
                                                            <i class="fa-solid fa-money-bill-wave me-1"></i> Pay
                                                        </button>
                                                    @endif
                                                    <a href="{{ $item->route_show }}" class="btn btn-outline-primary btn-sm" title="View Purchase Request details">
                                                        <i class="fa-solid fa-eye me-1"></i> PR
                                                    </a>
                                                @elseif($item->status_key === 'paid')
                                                    <a href="{{ $item->route_show }}" class="btn btn-success-subtle text-success border border-success-subtle btn-sm fw-semibold" title="View completed PR">
                                                        <i class="fa-solid fa-check-circle me-1"></i> Paid
                                                    </a>
                                                @endif

                                            @elseif($item->type === 'office_supply_request')
                                                @php
                                                    $officeReq = $item->raw_model;
                                                    $isAssignedToMe = (int)$item->assigned_staff_id === (int)auth()->id() || ($officeReq->coa && (int)$officeReq->coa->assigned_to === (int)auth()->id());
                                                    $isFinHeadOrAdmin = !empty($isAdmin) || (!empty($isFinance) && (auth()->user()->hasAnyRole(['Finance head', 'finance_head', 'finance_manager']) || str_contains(strtolower(auth()->user()->roles->pluck('name')->implode(' ')), 'head')));
                                                @endphp

                                                @if($item->status_key === 'finance_queue')
                                                    @if($isAssignedToMe)
                                                        <button type="button" class="btn btn-success btn-sm text-white fw-bold shadow-sm" 
                                                                data-bs-toggle="modal" 
                                                                data-bs-target="#payOfficeModal{{ $item->id_raw }}"
                                                                title="Disburse payment for this Office Request">
                                                            <i class="fa-solid fa-money-bill-wave me-1"></i> Pay
                                                        </button>
                                                    @elseif($isFinHeadOrAdmin)
                                                        <button type="button" class="btn btn-sm text-white fw-bold shadow-sm" style="background:#7c3aed;" 
                                                                data-bs-toggle="modal" 
                                                                data-bs-target="#financeOfficeAssignModal{{ $item->id_raw }}"
                                                                title="Assign Funding Account &amp; Staff">
                                                            <i class="fa-solid fa-user-tag me-1"></i> Assign
                                                        </button>
                                                    @endif
                                                    <a href="{{ $item->route_show }}" class="btn btn-outline-primary btn-sm" title="View Office Material Request">
                                                        <i class="fa-solid fa-eye me-1"></i> View
                                                    </a>
                                                @elseif($item->status_key === 'paid')
                                                    <a href="{{ $item->route_show }}" class="btn btn-success-subtle text-success border border-success-subtle btn-sm fw-semibold" title="View completed Office Request">
                                                        <i class="fa-solid fa-check-circle me-1"></i> Paid
                                                    </a>
                                                @endif
                                            @endif
                                        @endif


                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center py-5 text-muted">
                                    <i class="fa-solid fa-inbox fa-3x mb-3 opacity-25"></i>
                                    <p class="mb-0 fw-semibold">No expenses found matching the selected tab or criteria.</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if($paginatedItems->hasPages())
            <div class="card-footer bg-white border-0 py-3">
                {{ $paginatedItems->links() }}
            </div>
        @endif
    </div>
</div>

<!-- ========================================== -->
<!-- MODALS RENDERED OUTSIDE TABLE (CLEAN DOM)  -->
<!-- ========================================== -->
@foreach($paginatedItems as $item)
    <!-- 1. Detail Modal -->
    <div class="modal fade" id="detailModal{{ $item->id_raw }}_{{ $item->type }}" tabindex="-1" aria-labelledby="detailModalLabel{{ $item->id_raw }}" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
                <div class="modal-header bg-white border-bottom py-3 px-4">
                    <div class="d-flex align-items-center gap-2">
                        <span class="badge bg-primary-subtle text-primary p-2 rounded-3 fs-6">
                            <i class="fa-solid fa-file-invoice"></i>
                        </span>
                        <div>
                            <h5 class="modal-title fw-bold text-dark mb-0" id="detailModalLabel{{ $item->id_raw }}">
                                {{ $item->id_formatted }}
                            </h5>
                            <span class="text-muted small text-uppercase">{{ str_replace('_', ' ', $item->type) }} Details</span>
                        </div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4 bg-white">
                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <div class="p-3 bg-light rounded-3 border">
                                <div class="small text-muted text-uppercase fw-bold mb-1">Requester & Department</div>
                                <div class="fw-bold text-dark fs-6">{{ $item->applicant_name }}</div>
                                <div class="small text-muted"><i class="fa-solid fa-building me-1"></i>{{ $item->project }}</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="p-3 bg-light rounded-3 border">
                                <div class="small text-muted text-uppercase fw-bold mb-1">Amount & Category</div>
                                <div class="fw-bold text-success fs-5">ETB {{ number_format($item->net_amount, 2) }}</div>
                                <div class="small text-muted"><i class="fa-solid fa-tag me-1"></i>Category: <strong>{{ $item->category }}</strong></div>
                                @if(isset($item->raw_model) && ($item->raw_model->vat_amount > 0 || $item->raw_model->withholding_amount > 0 || ($item->raw_model->gross_amount && $item->raw_model->gross_amount != $item->net_amount)))
                                    <div class="mt-2 pt-2 border-top small">
                                        <div class="d-flex justify-content-between text-muted">
                                            <span>Base/Invoice:</span>
                                            <span>ETB {{ number_format($item->raw_model->gross_amount ?? $item->net_amount, 2) }}</span>
                                        </div>
                                        @if($item->raw_model->vat_amount > 0)
                                        <div class="d-flex justify-content-between text-info">
                                            <span>VAT ({{ $item->raw_model->vat_type === 'vat_b' ? 'VAT B Incl.' : '15%' }}):</span>
                                            <span>+ ETB {{ number_format($item->raw_model->vat_amount, 2) }}</span>
                                        </div>
                                        @endif
                                        @if($item->raw_model->withholding_amount > 0)
                                        <div class="d-flex justify-content-between text-danger">
                                            <span>Withholding Tax ({{ $item->raw_model->withholding_rate ?? 3 }}%):</span>
                                            <span>- ETB {{ number_format($item->raw_model->withholding_amount, 2) }}</span>
                                        </div>
                                        @endif

                                    </div>
                                @endif
                            </div>
                        </div>

                    </div>

                    <div class="mb-4">
                        <label class="form-label small text-muted text-uppercase fw-bold">Description & Purpose</label>
                        <div class="p-3 border rounded-3 bg-light text-dark">
                            {{ $item->description }}
                        </div>
                    </div>

                    @if($item->status_key === 'finance_queue')
                        <div class="p-3 mb-4 rounded-3 border border-warning bg-warning bg-opacity-10">
                            <div class="d-flex align-items-center justify-content-between mb-2">
                                <h6 class="fw-bold text-dark mb-0">
                                    <i class="fa-solid fa-hourglass-half text-warning me-1"></i> Not Paid / Awaiting Finance Disbursement
                                </h6>
                                <span class="badge bg-warning text-dark font-monospace">Pending Payment</span>
                            </div>
                            <div class="row g-2 small text-dark">
                                <div class="col-md-6">
                                    <span class="text-muted d-block">Assigned Finance Staff:</span>
                                    <strong>{{ $item->raw_model->assignedFinanceStaff->name ?? ($item->raw_model->assignedStaff->name ?? ($item->raw_model->financeStaff->name ?? 'Finance Department Pool')) }}</strong>
                                </div>
                                <div class="col-md-6">
                                    <span class="text-muted d-block">Funding Source / Account:</span>
                                    <strong>{{ $item->coa_name ?? ($item->bank_name ?? 'Pending Account Selection') }}</strong>
                                </div>
                                <div class="col-md-6 mt-2">
                                    <span class="text-muted d-block">GM / HR Approval:</span>
                                    <strong>{{ optional($item->raw_model->gm_approved_at ?? $item->raw_model->created_at)->format('d M Y, h:i A') }}</strong>
                                </div>
                                <div class="col-md-6 mt-2">
                                    <span class="text-muted d-block">Amount Due to Pay:</span>
                                    <strong class="text-danger fs-6">ETB {{ number_format($item->net_amount, 2) }}</strong>
                                </div>
                            </div>
                        </div>
                    @endif

                    @if($item->type === 'expense_request')
                        @php 
                            $req = $item->raw_model; 
                            $pcr = $req->linked_replenishment;
                        @endphp

                        @if($pcr)
                            {{-- 🧾 Full Petty Cash Replenishment Statement & All Individual Receipts/Vouchers --}}
                            <div class="card border border-primary border-opacity-25 rounded-4 mb-4 overflow-hidden shadow-xs">
                                <div class="card-header bg-primary bg-opacity-10 py-3 px-4 d-flex justify-content-between align-items-center flex-wrap gap-2">
                                    <div class="d-flex align-items-center gap-2">
                                        <i class="fa-solid fa-receipt text-primary fs-5"></i>
                                        <div>
                                            <h6 class="mb-0 fw-bold text-dark">Petty Cash Replenishment Breakdown (#{{ $pcr->request_no }})</h6>
                                            <small class="text-muted">Account: <strong>[{{ $pcr->chartOfAccount->code ?? '1010' }}] {{ $pcr->chartOfAccount->name ?? 'Petty Cash' }}</strong> &bull; Custodian: <strong>{{ $pcr->requester->name ?? 'Custodian' }}</strong></small>
                                        </div>
                                    </div>
                                    <div class="d-flex align-items-center gap-2">
                                        @if($pcr->attachment_url)
                                            <a href="{{ $pcr->attachment_url }}" target="_blank" class="btn btn-sm btn-primary rounded-pill px-3 shadow-xs">
                                                <i class="fa-solid fa-file-invoice me-1"></i> View Scanned Receipts Document
                                            </a>
                                        @endif
                                    </div>
                                </div>

                                <div class="card-body p-0">
                                    {{-- Audit Verification Banner if audited --}}
                                    @if($pcr->audited_by)
                                        <div class="p-3 bg-success bg-opacity-10 border-bottom border-success border-opacity-25 d-flex align-items-center justify-content-between flex-wrap gap-2">
                                            <div class="d-flex align-items-center gap-2">
                                                <i class="fa-solid fa-circle-check text-success fs-5"></i>
                                                <div>
                                                    <strong class="text-dark">Audited &amp; Cleared by Internal Auditor:</strong>
                                                    <span class="text-muted small ms-1">{{ $pcr->auditor->name ?? 'Internal Auditor' }} on {{ optional($pcr->audited_at)->format('d M Y, H:i') }}</span>
                                                    @if($pcr->audit_notes)
                                                        <div class="text-dark small mt-1"><em>"{{ $pcr->audit_notes }}"</em></div>
                                                    @endif
                                                </div>
                                            </div>
                                            <span class="badge bg-success font-monospace px-3 py-1">Audit Cleared</span>
                                        </div>
                                    @endif

                                    {{-- Summary Stats in Bar --}}
                                    <div class="row g-0 text-center border-bottom bg-light">
                                        <div class="col-md-3 p-3 border-end">
                                            <div class="text-muted small text-uppercase fw-bold" style="font-size:0.72rem;">Start Balance</div>
                                            <div class="fw-bold text-dark fs-6 font-monospace">ETB {{ number_format($pcr->current_balance_at_request, 2) }}</div>
                                        </div>
                                        <div class="col-md-3 p-3 border-end">
                                            <div class="text-muted small text-uppercase fw-bold" style="font-size:0.72rem;">Total Spent (Reconciled)</div>
                                            <div class="fw-bold text-danger fs-6 font-monospace">ETB {{ number_format($pcr->total_expenses_amount, 2) }}</div>
                                        </div>
                                        <div class="col-md-3 p-3 border-end">
                                            <div class="text-muted small text-uppercase fw-bold" style="font-size:0.72rem;">Replenishment Requested</div>
                                            <div class="fw-bold text-success fs-6 font-monospace">ETB {{ number_format($pcr->requested_amount, 2) }}</div>
                                        </div>
                                        <div class="col-md-3 p-3">
                                            <div class="text-muted small text-uppercase fw-bold" style="font-size:0.72rem;">Receipts / Vouchers</div>
                                            <div class="fw-bold text-primary fs-6">{{ $pcr->items->count() }} Attached</div>
                                        </div>
                                    </div>

                                    {{-- Itemized Table of Every Single Receipt/Voucher --}}
                                    <div class="table-responsive">
                                        <table class="table table-hover table-sm align-middle mb-0" style="font-size: 0.85rem;">
                                            <thead class="table-light text-muted small text-uppercase">
                                                <tr>
                                                    <th class="ps-3 py-2" style="width: 40px;">#</th>
                                                    <th class="py-2" style="width: 110px;">Date</th>
                                                    <th class="py-2" style="width: 140px;">Voucher / Ref #</th>
                                                    <th class="py-2">Payee / Description</th>
                                                    <th class="py-2">Target Account / Category</th>
                                                    <th class="py-2 pe-3 text-end" style="width: 130px;">Amount (ETB)</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @forelse($pcr->items as $idx => $receiptItem)
                                                    <tr>
                                                        <td class="ps-3 text-muted">{{ $idx + 1 }}</td>
                                                        <td>{{ optional($receiptItem->entry_date)->format('M d, Y') ?? '—' }}</td>
                                                        <td>
                                                            <span class="badge bg-light text-dark border font-monospace">{{ $receiptItem->reference ?: 'Voucher' }}</span>
                                                        </td>
                                                        <td>
                                                            <div class="fw-semibold text-dark">{{ $receiptItem->description }}</div>
                                                        </td>
                                                        <td>
                                                            <span class="badge bg-secondary bg-opacity-10 text-dark">{{ $receiptItem->target_account_name ?: 'General' }}</span>
                                                        </td>
                                                        <td class="pe-3 text-end fw-bold font-monospace text-dark">
                                                            ETB {{ number_format($receiptItem->amount, 2) }}
                                                        </td>
                                                    </tr>
                                                @empty
                                                    <tr>
                                                        <td colspan="6" class="text-center py-3 text-muted">No individual line vouchers recorded for this cycle.</td>
                                                    </tr>
                                                @endforelse
                                            </tbody>
                                            <tfoot class="table-light">
                                                <tr class="fw-bold">
                                                    <td colspan="5" class="text-end pe-2">Total Verified Receipts:</td>
                                                    <td class="pe-3 text-end font-monospace text-success fs-6">
                                                        ETB {{ number_format($pcr->items->sum('amount') ?: $pcr->requested_amount, 2) }}
                                                    </td>
                                                </tr>
                                            </tfoot>
                                        </table>
                                    </div>

                                    @if($pcr->notes)
                                        <div class="p-3 bg-light border-top">
                                            <small class="text-muted text-uppercase fw-bold d-block mb-1">Custodian Notes:</small>
                                            <div class="small text-dark">{{ $pcr->notes }}</div>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        @endif

                        @if($req->maintenanceRequest)
                            {{-- Maintenance Request Summary Card --}}
                            <div class="card border border-warning border-opacity-25 rounded-4 mb-4 overflow-hidden shadow-xs">
                                <div class="card-header bg-warning bg-opacity-10 py-3 px-4">
                                    <h6 class="mb-0 fw-bold text-dark"><i class="fa-solid fa-screwdriver-wrench text-warning me-2"></i>Linked Maintenance Ticket: {{ $req->maintenanceRequest->request_no }}</h6>
                                </div>
                                <div class="card-body p-3">
                                    <div class="row g-2 small">
                                        <div class="col-md-6"><strong>Asset / Equipment:</strong> {{ $req->maintenanceRequest->asset_name }} ({{ $req->maintenanceRequest->asset_code ?? 'Tag' }})</div>
                                        <div class="col-md-6"><strong>Issue Category:</strong> {{ $req->maintenanceRequest->issue_type_label }}</div>
                                        <div class="col-12 mt-2"><strong>Fault Description:</strong> {{ $req->maintenanceRequest->description }}</div>
                                    </div>
                                </div>
                            </div>
                        @endif
                    @endif

                    @if($item->attachment_url)
                        <div class="mb-3">
                            <label class="form-label small text-muted text-uppercase fw-bold">Attachment / Supporting Invoice Receipt</label>
                            <div>
                                <a href="{{ $item->attachment_url }}" target="_blank" class="btn btn-outline-primary btn-sm rounded-3 px-3 shadow-sm">
                                    <i class="fa-solid fa-paperclip me-1"></i> Open / Download Supporting Document
                                </a>
                            </div>
                        </div>
                    @endif

                    @if(isset($item->raw_model->withholding_receipt) && !empty($item->raw_model->withholding_receipt))
                        <div class="mb-3">
                            <label class="form-label small text-muted text-uppercase fw-bold">
                                <i class="fa-solid fa-file-invoice-dollar text-danger me-1"></i>3% Withholding Tax Receipt / Slip (የቅድመ ግብር ደረሰኝ)
                            </label>
                            <div>
                                <a href="{{ $item->raw_model->withholding_receipt_url }}" target="_blank" class="btn btn-outline-danger btn-sm rounded-3 px-3 shadow-sm">
                                    <i class="fa-solid fa-file-pdf me-1"></i> View / Download Withholding Receipt @if(!empty($item->raw_model->withholding_receipt_number)) (Ref: {{ $item->raw_model->withholding_receipt_number }}) @endif
                                </a>
                            </div>
                        </div>
                    @endif

                    @if($item->type === 'expense_request')
                        @php $req = $item->raw_model; @endphp
                        <div class="p-3 border rounded-3 bg-light">
                            <div class="fw-bold text-dark mb-2"><i class="fa-solid fa-timeline me-1 text-primary"></i> Review & Payment Timeline</div>
                            <ul class="list-unstyled mb-0 small text-muted">
                                <li class="mb-1"><i class="fa-solid fa-circle-dot me-2 text-secondary"></i> Submitted on: <strong class="text-dark">{{ $req->created_at->format('M d, Y H:i') }}</strong></li>
                                @if($req->hr_reviewed_at)
                                    <li class="mb-1"><i class="fa-solid fa-circle-check me-2 text-warning"></i> HR Review by <strong class="text-dark">{{ $req->hrReviewer->name ?? 'HR' }}</strong> on {{ $req->hr_reviewed_at->format('M d, Y H:i') }}</li>
                                @endif
                                @if($req->gm_approved_at)
                                    <li class="mb-1"><i class="fa-solid fa-circle-check me-2 text-info"></i> GM Approval by <strong class="text-dark">{{ $req->gmApprover->name ?? 'GM' }}</strong> on {{ $req->gm_approved_at->format('M d, Y H:i') }}</li>
                                @endif
                                @if($req->paid_at)
                                    <li class="mb-1 text-success"><i class="fa-solid fa-circle-check me-2"></i> <strong>Paid on {{ $req->paid_at->format('M d, Y H:i') }}</strong> by {{ $req->paidBy->name ?? 'Finance' }} | Ref: {{ $req->payment_reference ?? 'N/A' }}</li>
                                @endif
                                @if($req->rejection_reason)
                                    <li class="mt-2 text-danger fw-semibold"><i class="fa-solid fa-triangle-exclamation me-2"></i> Rejection Reason: {{ $req->rejection_reason }}</li>
                                @endif
                            </ul>
                        </div>
                    @endif
                </div>
                <div class="modal-footer bg-light border-0 py-3 px-4">
                    <button type="button" class="btn btn-secondary rounded-3 px-4" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    @if($item->type === 'expense_request')
        @php 
            $req = $item->raw_model; 
            $pcr = $req->linked_replenishment;
        @endphp

        <!-- 2. HR Review Modal -->
        <div class="modal fade" id="hrReviewModal{{ $req->id }}" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
                    <form method="POST" action="{{ route('expense-requests.hr-review', $req->id) }}">
                        @csrf
                        <div class="modal-header bg-white border-bottom py-3 px-4">
                            <div class="d-flex align-items-center gap-2">
                                <span class="badge bg-warning-subtle text-warning p-2 rounded-3 fs-6">
                                    <i class="fa-solid fa-user-check"></i>
                                </span>
                                <div>
                                    <h5 class="modal-title fw-bold text-dark mb-0">HR / Coordinator Review: {{ $req->request_number }}</h5>
                                    <span class="text-muted small">Employee Expense &amp; Transport Verification</span>
                                </div>
                            </div>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body p-4 bg-white">
                            <div class="p-3 bg-light rounded-3 mb-3 border">
                                <div class="d-flex justify-content-between">
                                    <span class="text-muted small">Employee / Beneficiary:</span>
                                    <span class="fw-bold text-dark">{{ $req->employee->full_name ?? $item->applicant_name }}</span>
                                </div>
                                @if($req->category)
                                <div class="d-flex justify-content-between mt-1">
                                    <span class="text-muted small">Category:</span>
                                    <span class="badge bg-secondary text-white">{{ $req->category }}</span>
                                </div>
                                @endif
                                <div class="d-flex justify-content-between mt-1">
                                    <span class="text-muted small">Amount:</span>
                                    <span class="fw-bold text-success">ETB {{ number_format($req->amount, 2) }}</span>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label small fw-bold text-dark">Decision Action</label>
                                <select name="action" class="form-select" id="hrAction{{ $req->id }}" onchange="document.getElementById('hrReason{{ $req->id }}').style.display = this.value === 'reject' ? 'block' : 'none';">
                                    <option value="approve">Approve {{ $req->amount > 5000 ? '(Forward to GM > 5k ETB)' : '(Forward to Finance Head)' }}</option>
                                    <option value="reject">Reject Request</option>
                                </select>
                            </div>
                            <div class="mb-3" id="hrReason{{ $req->id }}" style="display: none;">
                                <label class="form-label small fw-bold text-danger">Rejection Reason *</label>
                                <textarea name="rejection_reason" class="form-control" rows="3" placeholder="Provide clear reason for rejection..."></textarea>
                            </div>
                        </div>
                        <div class="modal-footer bg-light border-0 py-3 px-4">
                            <button type="button" class="btn btn-secondary rounded-3" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-primary rounded-3 px-4">Submit Decision</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- 3. GM Review Modal -->
        <div class="modal fade" id="gmReviewModal{{ $req->id }}" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered {{ $pcr ? 'modal-xl' : 'modal-lg' }}">
                <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
                    <form method="POST" action="{{ route('expense-requests.gm-review', $req->id) }}">
                        @csrf
                        <div class="modal-header bg-white border-bottom py-3 px-4">
                            <div class="d-flex align-items-center gap-2">
                                <span class="badge bg-info-subtle text-info p-2 rounded-3 fs-6">
                                    <i class="fa-solid fa-user-shield"></i>
                                </span>
                                <div>
                                    <h5 class="modal-title fw-bold text-dark mb-0">GM Approval: {{ $req->request_number }}</h5>
                                    <span class="text-muted small">High-Value Expense Authorization &amp; Receipt Inspection</span>
                                </div>
                            </div>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body p-4 bg-white">
                            <div class="row g-3 mb-4">
                                <div class="col-md-6">
                                    <div class="p-3 bg-light rounded-3 border h-100">
                                        <div class="small text-muted text-uppercase fw-bold mb-1">Requester &amp; Department</div>
                                        <div class="fw-bold text-dark fs-6">{{ $item->applicant_name }}</div>
                                        <div class="small text-muted"><i class="fa-solid fa-building me-1"></i>{{ $item->project }}</div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="p-3 bg-light rounded-3 border h-100">
                                        <div class="small text-muted text-uppercase fw-bold mb-1">Total Requested Amount</div>
                                        <div class="fw-bold text-success fs-4 font-monospace">ETB {{ number_format($req->amount, 2) }}</div>
                                        <div class="small text-muted"><i class="fa-solid fa-tag me-1"></i>Category: <strong>{{ $item->category }}</strong></div>
                                    </div>
                                </div>
                            </div>

                            @if($pcr)
                                {{-- GM View: Comprehensive Petty Cash Replenishment Statement & All Receipts --}}
                                <div class="card border border-primary border-opacity-25 rounded-4 mb-4 overflow-hidden shadow-xs">
                                    <div class="card-header bg-primary bg-opacity-10 py-3 px-4 d-flex justify-content-between align-items-center flex-wrap gap-2">
                                        <div class="d-flex align-items-center gap-2">
                                            <i class="fa-solid fa-receipt text-primary fs-5"></i>
                                            <div>
                                                <h6 class="mb-0 fw-bold text-dark">Petty Cash Statement &amp; Verified Receipts (#{{ $pcr->request_no }})</h6>
                                                <small class="text-muted">Account: <strong>[{{ $pcr->chartOfAccount->code ?? '1010' }}] {{ $pcr->chartOfAccount->name ?? 'Petty Cash' }}</strong> &bull; Custodian: <strong>{{ $pcr->requester->name ?? 'Custodian' }}</strong></small>
                                            </div>
                                        </div>
                                        @if($pcr->attachment_url)
                                            <a href="{{ $pcr->attachment_url }}" target="_blank" class="btn btn-sm btn-primary rounded-pill px-3 shadow-xs">
                                                <i class="fa-solid fa-file-invoice me-1"></i> View Scanned Receipts Booklet
                                            </a>
                                        @endif
                                    </div>

                                    <div class="card-body p-0">
                                        @if($pcr->audited_by)
                                            <div class="p-3 bg-success bg-opacity-10 border-bottom border-success border-opacity-25 d-flex align-items-center justify-content-between flex-wrap gap-2">
                                                <div class="d-flex align-items-center gap-2">
                                                    <i class="fa-solid fa-circle-check text-success fs-5"></i>
                                                    <div>
                                                        <strong class="text-dark">Internal Audit Verified &amp; Cleared:</strong>
                                                        <span class="text-muted small ms-1">{{ $pcr->auditor->name ?? 'Internal Auditor' }} on {{ optional($pcr->audited_at)->format('d M Y, H:i') }}</span>
                                                        @if($pcr->audit_notes)
                                                            <div class="text-dark small mt-1"><em>"{{ $pcr->audit_notes }}"</em></div>
                                                        @endif
                                                    </div>
                                                </div>
                                                <span class="badge bg-success font-monospace px-3 py-1">Audit Cleared</span>
                                            </div>
                                        @endif

                                        <div class="row g-0 text-center border-bottom bg-light">
                                            <div class="col-md-3 p-3 border-end">
                                                <div class="text-muted small text-uppercase fw-bold" style="font-size:0.72rem;">Start Balance</div>
                                                <div class="fw-bold text-dark fs-6 font-monospace">ETB {{ number_format($pcr->current_balance_at_request, 2) }}</div>
                                            </div>
                                            <div class="col-md-3 p-3 border-end">
                                                <div class="text-muted small text-uppercase fw-bold" style="font-size:0.72rem;">Total Spent (Reconciled)</div>
                                                <div class="fw-bold text-danger fs-6 font-monospace">ETB {{ number_format($pcr->total_expenses_amount, 2) }}</div>
                                            </div>
                                            <div class="col-md-3 p-3 border-end">
                                                <div class="text-muted small text-uppercase fw-bold" style="font-size:0.72rem;">Replenishment Requested</div>
                                                <div class="fw-bold text-success fs-6 font-monospace">ETB {{ number_format($pcr->requested_amount, 2) }}</div>
                                            </div>
                                            <div class="col-md-3 p-3">
                                                <div class="text-muted small text-uppercase fw-bold" style="font-size:0.72rem;">Receipts / Vouchers</div>
                                                <div class="fw-bold text-primary fs-6">{{ $pcr->items->count() }} Attached</div>
                                            </div>
                                        </div>

                                        <div class="table-responsive" style="max-height: 320px; overflow-y: auto;">
                                            <table class="table table-hover table-sm align-middle mb-0" style="font-size: 0.85rem;">
                                                <thead class="table-light text-muted small text-uppercase sticky-top">
                                                    <tr>
                                                        <th class="ps-3 py-2" style="width: 40px;">#</th>
                                                        <th class="py-2" style="width: 110px;">Date</th>
                                                        <th class="py-2" style="width: 140px;">Voucher / Ref #</th>
                                                        <th class="py-2">Payee / Description</th>
                                                        <th class="py-2">Target Account / Category</th>
                                                        <th class="py-2 pe-3 text-end" style="width: 130px;">Amount (ETB)</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    @forelse($pcr->items as $idx => $receiptItem)
                                                        <tr>
                                                            <td class="ps-3 text-muted">{{ $idx + 1 }}</td>
                                                            <td>{{ optional($receiptItem->entry_date)->format('M d, Y') ?? '—' }}</td>
                                                            <td>
                                                                <span class="badge bg-light text-dark border font-monospace">{{ $receiptItem->reference ?: 'Voucher' }}</span>
                                                            </td>
                                                            <td>
                                                                <div class="fw-semibold text-dark">{{ $receiptItem->description }}</div>
                                                            </td>
                                                            <td>
                                                                <span class="badge bg-secondary bg-opacity-10 text-dark">{{ $receiptItem->target_account_name ?: 'General' }}</span>
                                                            </td>
                                                            <td class="pe-3 text-end fw-bold font-monospace text-dark">
                                                                ETB {{ number_format($receiptItem->amount, 2) }}
                                                            </td>
                                                        </tr>
                                                    @empty
                                                        <tr>
                                                            <td colspan="6" class="text-center py-3 text-muted">No individual line vouchers recorded for this cycle.</td>
                                                        </tr>
                                                    @endforelse
                                                </tbody>
                                                <tfoot class="table-light">
                                                    <tr class="fw-bold">
                                                        <td colspan="5" class="text-end pe-2">Total Verified Receipts:</td>
                                                        <td class="pe-3 text-end font-monospace text-success fs-6">
                                                            ETB {{ number_format($pcr->items->sum('amount') ?: $pcr->requested_amount, 2) }}
                                                        </td>
                                                    </tr>
                                                </tfoot>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            @endif

                            @if($req->attachment_url)
                                <div class="mb-3 p-3 bg-light rounded-3 border d-flex justify-content-between align-items-center">
                                    <div>
                                        <strong class="text-dark d-block"><i class="fa-solid fa-paperclip text-primary me-1"></i> Attached Supporting Invoice / Receipt Document</strong>
                                        <small class="text-muted">Direct proof / voucher uploaded by applicant</small>
                                    </div>
                                    <a href="{{ $req->attachment_url }}" target="_blank" class="btn btn-sm btn-primary rounded-pill px-3 shadow-xs">
                                        <i class="fa-solid fa-arrow-up-right-from-square me-1"></i> Open Attachment
                                    </a>
                                </div>
                            @endif

                            <div class="mb-3">
                                <label class="form-label small fw-bold text-dark">GM Action Decision</label>
                                <select name="action" class="form-select" id="gmAction{{ $req->id }}" onchange="document.getElementById('gmReason{{ $req->id }}').style.display = this.value === 'reject' ? 'block' : 'none';">
                                    <option value="approve">Approve (Send to Finance Head for Disbursement)</option>
                                    <option value="reject">Reject Request</option>
                                </select>
                            </div>
                            <div class="mb-3" id="gmReason{{ $req->id }}" style="display: none;">
                                <label class="form-label small fw-bold text-danger">Rejection Reason *</label>
                                <textarea name="rejection_reason" class="form-control" rows="3" placeholder="State reason for GM rejection..."></textarea>
                            </div>
                        </div>
                        <div class="modal-footer bg-light border-0 py-3 px-4">
                            <button type="button" class="btn btn-secondary rounded-3" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-primary rounded-3 px-4 fw-bold">Submit GM Decision</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- 4. Finance Assign Modal -->
        @php
            $isAssignedToMe = $authUserId && (
                $req->assigned_finance_staff_id == $authUserId ||
                $req->finance_staff_id == $authUserId ||
                ($req->chartOfAccount && $req->chartOfAccount->assigned_to == $authUserId) ||
                ($req->coa && $req->coa->assigned_to == $authUserId)
            );
        @endphp
        <div class="modal fade" id="financeAssignModal{{ $req->id }}" tabindex="-1" aria-hidden="true">

            <div class="modal-dialog modal-dialog-centered {{ $isAssignedToMe ? 'modal-lg' : 'modal-md' }}">
                <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
                    <div class="modal-header bg-white border-bottom py-3 px-4">
                        <div class="d-flex align-items-center gap-2">
                            <span class="badge bg-primary-subtle text-primary p-2 rounded-3 fs-6">
                                <i class="fa-solid fa-user-tag"></i>
                            </span>
                            <div>
                                <h5 class="modal-title fw-bold text-dark mb-0">Finance Assignment: {{ $req->request_number }}</h5>
                                <span class="text-muted small">Total: <strong>ETB {{ number_format($req->amount, 2) }}</strong> &bull; Requester: <strong>{{ $item->applicant_name }}</strong></span>
                            </div>
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body p-4 bg-white">
                        @if($isAssignedToMe)
                            <div class="row g-4">
                                <!-- Option 1: Assign COA & Staff -->
                                <div class="col-md-6 border-end pe-md-4">
                                    <div class="d-flex align-items-center gap-2 mb-3">
                                        <span class="badge bg-primary text-white rounded-circle">1</span>
                                        <h6 class="fw-bold text-dark mb-0">Assign Account &amp; Staff</h6>
                                    </div>
                                    <form method="POST" action="{{ route('expense-requests.finance-assign', $req->id) }}">
                                        @csrf
                                        <div class="mb-3">
                                            <label class="form-label small fw-bold text-dark">Chart of Account (COA) <span class="text-danger">*</span></label>
                                            <select name="coa_id" 
                                                    id="coaSelect{{ $req->id }}"
                                                    class="form-select form-select-sm bg-white" 
                                                    onchange="autoSelectFinanceStaff(this, {{ $req->id }})"
                                                    required>
                                                <option value="" data-staff-id="" data-staff-name="">-- Select COA Account --</option>
                                                @foreach($chartOfAccounts as $coa)
                                                    @php
                                                        $isSelected = ($req->coa_id == $coa->id || $req->chart_of_account_id == $coa->id);
                                                        $assignedStaffId = $coa->assigned_to ?? '';
                                                        $assignedStaffName = $coa->manager->name ?? '';
                                                    @endphp
                                                    <option value="{{ $coa->id }}" 
                                                            data-staff-id="{{ $assignedStaffId }}" 
                                                            data-staff-name="{{ $assignedStaffName }}"
                                                            {{ $isSelected ? 'selected' : '' }}>
                                                        {{ $coa->code }} - {{ $coa->name }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label small fw-bold text-dark">Assigned Finance Staff</label>
                                            <select name="assigned_finance_staff_id" 
                                                    id="financeStaffSelect{{ $req->id }}" 
                                                    class="form-select form-select-sm bg-white">
                                                <option value="">Auto-assigned from COA or Select Staff...</option>
                                                @foreach($financeStaff as $st)
                                                    <option value="{{ $st->id }}" {{ ($req->assigned_finance_staff_id == $st->id || $req->finance_staff_id == $st->id) ? 'selected' : '' }}>
                                                        {{ $st->name }}
                                                    </option>
                                                @endforeach
                                            </select>
                                            <div id="autoStaffBadge{{ $req->id }}" class="mt-2" style="display: none;"></div>
                                        </div>
                                        <button type="submit" class="btn btn-outline-primary btn-sm w-100 rounded-3 py-2 fw-semibold">
                                            <i class="fa-solid fa-floppy-disk me-1"></i> Save Assignment
                                        </button>
                                    </form>
                                </div>

                                <!-- Option 2: Direct Mark as Paid -->
                                <div class="col-md-6 ps-md-4">
                                    <div class="d-flex align-items-center gap-2 mb-3">
                                        <span class="badge bg-success text-white rounded-circle">2</span>
                                        <h6 class="fw-bold text-dark mb-0">Disburse &amp; Mark Paid</h6>
                                    </div>
                                    <form method="POST" action="{{ route('expense-requests.mark-paid', $req->id) }}">
                                        @csrf
                                        <div class="mb-3">
                                            <label class="form-label small fw-bold text-dark">Payment Reference / Txn #</label>
                                            <input type="text" name="payment_reference" class="form-control form-control-sm bg-white" placeholder="e.g. FT260823-001, Cash Voucher #">
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label small fw-bold text-dark">Payment Notes</label>
                                            <textarea name="payment_notes" class="form-control form-control-sm bg-white" rows="2" placeholder="Payment remarks..."></textarea>
                                        </div>
                                        <button type="submit" class="btn btn-success btn-sm w-100 rounded-3 py-2 fw-semibold">
                                            <i class="fa-solid fa-circle-check me-1"></i> Confirm Paid (ETB {{ number_format($req->amount, 2) }})
                                        </button>
                                    </form>
                                </div>
                            </div>
                        @else
                            <div class="p-3 bg-light rounded-3 border mb-3">
                                <div class="d-flex align-items-center gap-2">
                                    <i class="fa-solid fa-user-tag text-primary fs-5"></i>
                                    <div>
                                        <strong class="text-dark d-block">Assign Account &amp; Custodian</strong>
                                        <small class="text-muted">As Finance Head, designate the paying Chart of Account and assigned custodian. Once saved, only the assigned custodian can execute disbursement.</small>
                                    </div>
                                </div>
                            </div>
                            <form method="POST" action="{{ route('expense-requests.finance-assign', $req->id) }}">
                                @csrf
                                <div class="mb-3">
                                    <label class="form-label small fw-bold text-dark">Chart of Account (COA) <span class="text-danger">*</span></label>
                                    <select name="coa_id" 
                                            id="coaSelect{{ $req->id }}"
                                            class="form-select form-select-sm bg-white" 
                                            onchange="autoSelectFinanceStaff(this, {{ $req->id }})"
                                            required>
                                        <option value="" data-staff-id="" data-staff-name="">-- Select COA Account --</option>
                                        @foreach($chartOfAccounts as $coa)
                                            @php
                                                $isSelected = ($req->coa_id == $coa->id || $req->chart_of_account_id == $coa->id);
                                                $assignedStaffId = $coa->assigned_to ?? '';
                                                $assignedStaffName = $coa->manager->name ?? '';
                                            @endphp
                                            <option value="{{ $coa->id }}" 
                                                    data-staff-id="{{ $assignedStaffId }}" 
                                                    data-staff-name="{{ $assignedStaffName }}"
                                                    {{ $isSelected ? 'selected' : '' }}>
                                                {{ $coa->code }} - {{ $coa->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label small fw-bold text-dark">Assigned Finance Staff / Custodian</label>
                                    <select name="assigned_finance_staff_id" 
                                            id="financeStaffSelect{{ $req->id }}" 
                                            class="form-select form-select-sm bg-white">
                                        <option value="">Auto-assigned from COA or Select Staff...</option>
                                        @foreach($financeStaff as $st)
                                            <option value="{{ $st->id }}" {{ ($req->assigned_finance_staff_id == $st->id || $req->finance_staff_id == $st->id) ? 'selected' : '' }}>
                                                {{ $st->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                    <div id="autoStaffBadge{{ $req->id }}" class="mt-2" style="display: none;"></div>
                                </div>
                                <button type="submit" class="btn btn-primary btn-sm w-100 rounded-3 py-2 fw-semibold">
                                    <i class="fa-solid fa-floppy-disk me-1"></i> Save Assignment
                                </button>
                            </form>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <!-- 5. Dedicated Pay Modal for Assigned Finance Staff -->
        <div class="modal fade" id="payModal{{ $req->id }}" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-lg">
                <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
                    <form method="POST" action="{{ route('expense-requests.mark-paid', $req->id) }}" id="payForm{{ $req->id }}" enctype="multipart/form-data">
                        @csrf

                        <div class="modal-header bg-success text-white border-0 py-3 px-4">
                            <div class="d-flex align-items-center gap-2">
                                <span class="badge bg-white bg-opacity-20 text-white p-2 rounded-3 fs-6">
                                    <i class="fa-solid fa-money-bill-wave"></i>
                                </span>
                                <div>
                                    <h5 class="modal-title fw-bold mb-0">Disburse Payment: {{ $req->request_number }}</h5>
                                    <span class="text-white-50 small">Amount: <strong>ETB {{ number_format($req->amount, 2) }}</strong> &bull; Requester: <strong>{{ $item->applicant_name }}</strong></span>
                                </div>
                            </div>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body p-4 bg-white">
                            <div class="p-3 bg-light rounded-3 mb-3 border">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <span class="text-muted small text-uppercase fw-bold">Approved Request Budget:</span>
                                    <strong class="text-success fs-5">ETB {{ number_format($req->amount, 2) }}</strong>
                                </div>
                                <div class="small text-muted mb-1"><i class="fa-solid fa-sitemap me-1"></i> Paying Account: <strong>{{ $req->chartOfAccount->name ?? ($req->coa->name ?? 'Default Petty Cash') }}</strong></div>
                                <div class="small text-muted"><i class="fa-solid fa-list me-1"></i> Description: {{ $req->description }}</div>
                            </div>

                            <!-- Category & Gross Invoice Amount -->
                            <div class="row g-3 mb-3">
                                <div class="col-md-6">
                                    <label class="form-label small fw-bold text-dark text-uppercase">
                                        <i class="fa-solid fa-tags text-primary me-1"></i>Expense Category <span class="text-danger">*</span>
                                    </label>
                                    <select name="category" id="modalCategory{{ $req->id }}" class="form-select form-select-sm fw-semibold" onchange="toggleDisburseTaxSection({{ $req->id }})">
                                        <option value="Service" {{ $req->category === 'Service' ? 'selected' : '' }}>🤝 Service (አገልግሎት)</option>
                                        <option value="Transport" {{ $req->category === 'Transport' ? 'selected' : '' }}>🚚 Transport (ትራንስፖርት)</option>
                                        <option value="Loading & Unloading" {{ in_array($req->category, ['Loading & Unloading', 'Loading / Unloading', 'Loading Unloading']) ? 'selected' : '' }}>📦 Loading &amp; Unloading (መጫን እና ማውረድ)</option>
                                        <option value="Contract Work" {{ $req->category === 'Contract Work' ? 'selected' : '' }}>📝 Contract Work (የኮንትራት ስራ)</option>
                                        <option value="Office Material" {{ $req->category === 'Office Material' ? 'selected' : '' }}>📁 Office Material (የቢሮ እቃ)</option>
                                        <option value="Maintenance" {{ $req->category === 'Maintenance' ? 'selected' : '' }}>🔧 Maintenance &amp; Repairs (ጥገና)</option>
                                        <option value="Other" {{ $req->category === 'Other' ? 'selected' : '' }}>✨ Other (ሌሎች)</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small fw-bold text-dark text-uppercase">
                                        <i class="fa-solid fa-calculator text-success me-1"></i>Gross / Base Invoice Amount (ETB)
                                    </label>
                                    <input type="number" step="0.01" min="0.01" name="gross_amount" id="modalGrossAmount{{ $req->id }}" 
                                           class="form-control form-control-sm fw-bold fs-6 text-dark" 
                                           value="{{ $req->gross_amount ?? $req->amount }}"
                                           oninput="recalculateDisbursement({{ $req->id }})">
                                </div>
                            </div>

                            <!-- Service Tax / Deduction Options (VAT & Withholding) -->
                            <div class="card border border-primary-subtle bg-light-subtle rounded-3 p-3 mb-3" id="serviceTaxPanel{{ $req->id }}">
                                <div class="d-flex justify-content-between align-items-center mb-2 pb-1 border-bottom">
                                    <strong class="text-primary small text-uppercase">
                                        <i class="fa-solid fa-receipt me-1"></i>Service Tax &amp; Deduction Config (VAT &amp; Withholding)
                                    </strong>
                                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-2 py-0">Tax Calculation</span>
                                </div>

                                <div class="row g-3">
                                    <!-- VAT Type -->
                                    <div class="col-md-6">
                                        <label class="form-label small fw-semibold mb-1">VAT Option (ቫት)</label>
                                        <select name="vat_type" id="modalVatType{{ $req->id }}" class="form-select form-select-sm" onchange="recalculateDisbursement({{ $req->id }})">
                                            <option value="none" {{ ($req->vat_type ?? 'none') === 'none' ? 'selected' : '' }}>No VAT (0% / ያለ ቫት)</option>
                                            <option value="exclusive" {{ ($req->vat_type ?? '') === 'exclusive' ? 'selected' : '' }}>15% VAT Added (+15% ተጨማሪ ቫት)</option>
                                            <option value="vat_b" {{ in_array(($req->vat_type ?? ''), ['vat_b', 'inclusive']) ? 'selected' : '' }}>15% VAT Included / VAT B (ከቫት 15% ጋር የተካተተ - ቫት ቢ)</option>
                                        </select>
                                        <input type="hidden" name="vat_rate" id="modalVatRate{{ $req->id }}" value="{{ $req->vat_rate ?? 15.00 }}">
                                        <input type="hidden" name="vat_amount" id="modalVatAmount{{ $req->id }}" value="{{ $req->vat_amount ?? 0 }}">
                                    </div>

                                    <!-- Withholding Tax -->
                                    <div class="col-md-6">
                                        <label class="form-label small fw-semibold mb-1">Withholding Tax (የቅድመ ግብር 3%)</label>
                                        <div class="form-check form-switch mt-1">
                                            <input class="form-check-input" type="checkbox" role="switch" name="has_withholding" value="1" 
                                                   id="modalWithholdingToggle{{ $req->id }}" 
                                                   {{ ($req->has_withholding ?? false) ? 'checked' : '' }}
                                                   onchange="recalculateDisbursement({{ $req->id }})">
                                            <label class="form-check-label small" for="modalWithholdingToggle{{ $req->id }}">
                                                Apply 3% Service Withholding Deduction
                                            </label>
                                        </div>
                                        <input type="hidden" name="withholding_rate" id="modalWithholdingRate{{ $req->id }}" value="{{ $req->withholding_rate ?? 3.00 }}">
                                        <input type="hidden" name="withholding_amount" id="modalWithholdingAmount{{ $req->id }}" value="{{ $req->withholding_amount ?? 0 }}">
                                    </div>
                                </div>

                                <!-- Real-time Breakdown Summary Table -->
                                <div class="mt-3 p-2 bg-white rounded border shadow-sm">
                                    <div class="row text-center g-2 small">
                                        <div class="col-3 border-end">
                                            <span class="text-muted d-block" style="font-size:0.75rem;">Base Amount</span>
                                            <strong class="text-dark" id="displayBaseAmount{{ $req->id }}">ETB {{ number_format($req->gross_amount ?? $req->amount, 2) }}</strong>
                                        </div>
                                        <div class="col-3 border-end">
                                            <span class="text-muted d-block" style="font-size:0.75rem;">VAT (15%)</span>
                                            <strong class="text-info" id="displayVatAmount{{ $req->id }}">+ ETB {{ number_format($req->vat_amount ?? 0, 2) }}</strong>
                                        </div>
                                        <div class="col-3 border-end">
                                            <span class="text-muted d-block" style="font-size:0.75rem;">Withholding (3%)</span>
                                            <strong class="text-danger" id="displayWhtAmount{{ $req->id }}">- ETB {{ number_format($req->withholding_amount ?? 0, 2) }}</strong>
                                        </div>
                                        <div class="col-3">
                                            <span class="text-muted d-block" style="font-size:0.75rem;">Net Payable</span>
                                            <strong class="text-success" id="displayNetAmount{{ $req->id }}">ETB {{ number_format($req->effective_payable_amount, 2) }}</strong>
                                        </div>
                                    </div>
                                </div>

                                <!-- Withholding Tax Receipt & Voucher Upload Section (Visible & Required when 3% WHT is ON) -->
                                <div id="withholdingReceiptSection{{ $req->id }}" class="mt-3 p-3 bg-white rounded-3 border border-danger-subtle shadow-sm" style="{{ ($req->has_withholding ?? false) ? '' : 'display:none;' }}">
                                    <div class="d-flex align-items-center justify-content-between mb-2">
                                        <label class="form-label small fw-bold text-danger text-uppercase mb-0">
                                            <i class="fa-solid fa-file-invoice-dollar me-1"></i>Withholding Tax Receipt / Slip Upload (የቅድመ ግብር ደረሰኝ) <span class="text-danger">*</span>
                                        </label>
                                        <span class="badge bg-danger-subtle text-danger border border-danger-subtle px-2 py-0">Required for 3% WHT</span>
                                    </div>
                                    <div class="row g-2 align-items-center">
                                        <div class="col-md-7">
                                            <input type="file" name="withholding_receipt" id="modalWithholdingReceipt{{ $req->id }}" 
                                                   class="form-control form-control-sm" 
                                                   accept="image/jpeg,image/png,image/jpg,application/pdf,image/webp"
                                                   data-has-existing="{{ !empty($req->withholding_receipt) ? '1' : '0' }}"
                                                   {{ ($req->has_withholding ?? false) && empty($req->withholding_receipt) ? 'required' : '' }}>
                                            <small class="text-muted" style="font-size:0.75rem;">Upload official Withholding receipt image or PDF.</small>
                                        </div>
                                        <div class="col-md-5">
                                            <input type="text" name="withholding_receipt_number" id="modalWithholdingReceiptNo{{ $req->id }}" 
                                                   class="form-control form-control-sm" 
                                                   placeholder="WHT Receipt / Voucher #" 
                                                   value="{{ $req->withholding_receipt_number ?? '' }}">
                                            <small class="text-muted" style="font-size:0.75rem;">Voucher / Receipt Serial # (Optional)</small>
                                        </div>
                                    </div>
                                    @if(!empty($req->withholding_receipt))
                                        <div class="mt-2 text-success small">
                                            <i class="fa-solid fa-circle-check me-1"></i> Existing slip uploaded: 
                                            <a href="{{ $req->withholding_receipt_url }}" target="_blank" class="fw-bold text-decoration-underline text-success">View Current Withholding Slip</a>
                                        </div>
                                    @endif
                                </div>
                            </div>


                            <input type="hidden" name="net_amount" id="modalNetAmount{{ $req->id }}" value="{{ $req->effective_payable_amount }}">
                            <input type="hidden" name="paid_amount" id="modalPaidAmount{{ $req->id }}" value="{{ $req->effective_payable_amount }}">

                            <div class="mb-3">
                                <label class="form-label small fw-bold text-dark">Payment Reference / Voucher # <span class="text-danger">*</span></label>
                                <input type="text" name="payment_reference" class="form-control bg-light border-0" placeholder="e.g. FT260823-001, Cash Voucher #" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label small fw-bold text-dark">Payment Notes (Optional)</label>
                                <textarea name="payment_notes" class="form-control bg-light border-0" rows="2" placeholder="Enter any payment notes or disbursement remarks..."></textarea>
                            </div>
                        </div>
                        <div class="modal-footer bg-light border-0 py-3 px-4">
                            <button type="button" class="btn btn-light rounded-pill px-3" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-success rounded-pill px-4 fw-bold shadow-sm" id="btnConfirmPaid{{ $req->id }}">
                                <i class="fa-solid fa-circle-check me-1"></i> Confirm Paid (<span id="btnPayAmount{{ $req->id }}">ETB {{ number_format($req->effective_payable_amount, 2) }}</span>)
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

    @endif

    @if($item->type === 'purchase_request' && $item->status_key === 'finance_queue')
    <div class="modal fade" id="payPrModal{{ $item->id_raw }}" tabindex="-1" aria-labelledby="payPrModalLabel{{ $item->id_raw }}" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
                <form method="POST" action="{{ route('purchase-requests.execute-payment', $item->id_raw) }}">
                    @csrf
                    <div class="modal-header bg-success text-white py-3 px-4">
                        <div class="d-flex align-items-center gap-2">
                            <i class="fa-solid fa-money-bill-transfer fs-5"></i>
                            <h5 class="modal-title fw-bold mb-0" id="payPrModalLabel{{ $item->id_raw }}">
                                Disburse Payment: {{ $item->id_formatted }}
                            </h5>
                        </div>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body p-4 bg-white">
                        <div class="alert alert-info py-2 px-3 small mb-3 border-start border-4 border-info">
                            <div class="d-flex justify-content-between mb-1">
                                <span class="text-muted">Payment Amount:</span>
                                <strong class="text-success fs-5">ETB {{ number_format($item->net_amount, 2) }}</strong>
                            </div>
                            <div class="d-flex justify-content-between mb-1">
                                <span class="text-muted">Funding Account:</span>
                                <strong class="text-dark">{{ $item->coa_name ?? 'COA Assigned' }}</strong>
                            </div>
                            @if($item->assigned_staff_name)
                            <div class="d-flex justify-content-between">
                                <span class="text-muted">Assigned Staff:</span>
                                <strong class="text-dark">{{ $item->assigned_staff_name }}</strong>
                            </div>
                            @endif
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-bold text-dark text-uppercase">
                                Bank Transaction No. / Cheque Reference No. <span class="text-danger">*</span>
                            </label>
                            <input type="text" name="transaction_reference" class="form-control form-control-sm mb-1" placeholder="e.g. TXN-10928374 or CHQ-004921" required>
                            <div class="form-text small text-muted">A valid bank transaction ID or cheque reference number is mandatory.</div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-bold text-muted text-uppercase">
                                Disbursement Remarks / Notes (Optional)
                            </label>
                            <textarea name="notes" class="form-control form-control-sm" rows="2" placeholder="Optional notes, recipient details, etc."></textarea>
                        </div>
                        <div class="p-2 bg-light rounded small text-muted">
                            <i class="fa-solid fa-circle-info text-primary me-1"></i> Once disbursed, the double-entry journal will post automatically, and the Procurement Team will be prompted to upload the vendor receipt.
                        </div>
                    </div>
                    <div class="modal-footer bg-light border-0 py-3 px-4">
                        <button type="button" class="btn btn-light rounded-pill px-3" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success rounded-pill px-4 fw-bold shadow-sm">
                            <i class="fa-solid fa-check-double me-1"></i> Confirm & Execute Payment (ETB {{ number_format($item->net_amount, 2) }})
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    @endif

    @if($item->type === 'office_supply_request' && $item->status_key === 'finance_queue')
    @php 
        $officeReq = $item->raw_model; 
        $isOfficeAssignedToMe = (int)$item->assigned_staff_id === (int)auth()->id() || ($officeReq->coa && (int)$officeReq->coa->assigned_to === (int)auth()->id());
    @endphp

    <!-- 1. Finance Head Assign & Decision Modal -->
    <div class="modal fade" id="financeOfficeAssignModal{{ $item->id_raw }}" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered {{ $isOfficeAssignedToMe ? 'modal-lg' : 'modal-md' }}">
            <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
                <div class="modal-header text-white py-3 px-4" style="background:#7c3aed;">
                    <div class="d-flex align-items-center gap-2">
                        <span class="badge bg-white bg-opacity-20 text-white p-2 rounded-3 fs-6">
                            <i class="fa-solid fa-user-gear"></i>
                        </span>
                        <div>
                            <h5 class="modal-title fw-bold mb-0">Finance Decision: {{ $item->id_formatted }}</h5>
                            <span class="text-white-50 small">Amount: <strong>ETB {{ number_format($item->net_amount, 2) }}</strong> &bull; Requested by: <strong>{{ $item->applicant_name }}</strong></span>
                        </div>
                    </div>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4 bg-white">
                    @if($isOfficeAssignedToMe)
                        <div class="row g-4">
                            <!-- Option 1: Assign COA & Finance Staff -->
                            <div class="col-md-6 border-end pe-md-4">
                                <div class="d-flex align-items-center gap-2 mb-3">
                                    <span class="badge bg-primary text-white rounded-circle">1</span>
                                    <h6 class="fw-bold text-dark mb-0">Assign Funding &amp; Staff</h6>
                                </div>
                                <form method="POST" action="{{ route('office-requests.finance-assign', $item->id_raw) }}">
                                    @csrf
                                    <div class="mb-3">
                                        <label class="form-label small fw-bold text-dark text-uppercase">Funding Account (COA) <span class="text-danger">*</span></label>
                                        <select name="coa_id" id="modalCoaSelect{{ $item->id_raw }}" class="form-select form-select-sm bg-light border-0" onchange="syncModalCoaToStaff('{{ $item->id_raw }}')" required>
                                            <option value="" disabled selected>-- Select Expense Account --</option>
                                            @foreach($chartOfAccounts as $coa)
                                                <option value="{{ $coa->id }}" 
                                                        data-staff-id="{{ $coa->assigned_to }}"
                                                        data-staff-name="{{ $coa->manager?->name }}"
                                                        {{ $officeReq->coa_id == $coa->id ? 'selected' : '' }}>
                                                    [{{ $coa->code }}] {{ $coa->name }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label small fw-bold text-dark text-uppercase">Assign Finance Staff</label>
                                        <select name="assigned_finance_staff_id" id="modalStaffSelect{{ $item->id_raw }}" class="form-select form-select-sm bg-light border-0">
                                            <option value="">-- Assign Staff / Self --</option>
                                            @foreach($financeStaff as $staff)
                                                <option value="{{ $staff->id }}" {{ $officeReq->assigned_finance_staff_id == $staff->id ? 'selected' : '' }}>
                                                    {{ $staff->name }} ({{ ucfirst(str_replace('_', ' ', $staff->roles->first()?->name ?? 'Staff')) }})
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <button type="submit" class="btn btn-outline-primary btn-sm w-100 rounded-3 py-2 fw-semibold">
                                        <i class="fa-solid fa-floppy-disk me-1"></i> Save Assignment
                                    </button>
                                </form>
                            </div>

                            <!-- Option 2: Direct Disburse & Mark Paid -->
                            <div class="col-md-6 ps-md-4">
                                <div class="d-flex align-items-center gap-2 mb-3">
                                    <span class="badge bg-success text-white rounded-circle">2</span>
                                    <h6 class="fw-bold text-dark mb-0">Disburse &amp; Mark Paid</h6>
                                </div>
                                <form method="POST" action="{{ route('office-requests.mark-paid', $item->id_raw) }}">
                                    @csrf
                                    <div class="mb-3">
                                        <label class="form-label small fw-bold text-dark text-uppercase">Payment Voucher Ref #</label>
                                        <input type="text" name="payment_reference" class="form-control form-control-sm bg-light border-0" placeholder="e.g. VC-2026-08-001, Cash Voucher #">
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label small fw-bold text-dark text-uppercase">Payment Notes</label>
                                        <textarea name="payment_notes" class="form-control form-control-sm bg-light border-0" rows="2" placeholder="Payment remarks..."></textarea>
                                    </div>
                                    <button type="submit" class="btn btn-success btn-sm w-100 rounded-3 py-2 fw-semibold">
                                        <i class="fa-solid fa-circle-check me-1"></i> Confirm Paid (ETB {{ number_format($item->net_amount, 2) }})
                                    </button>
                                </form>
                            </div>
                        </div>
                    @else
                        <div class="p-3 bg-light rounded-3 border mb-3">
                            <div class="d-flex align-items-center gap-2">
                                <i class="fa-solid fa-user-gear text-primary fs-5"></i>
                                <div>
                                    <strong class="text-dark d-block">Finance Head Assignment Portal</strong>
                                    <small class="text-muted">Designate the funding account (COA) and assign the custodian staff to disburse payment.</small>
                                </div>
                            </div>
                        </div>
                        <form method="POST" action="{{ route('office-requests.finance-assign', $item->id_raw) }}">
                            @csrf
                            <div class="mb-3">
                                <label class="form-label small fw-bold text-dark text-uppercase">Funding Account (COA) <span class="text-danger">*</span></label>
                                <select name="coa_id" id="modalCoaSelect{{ $item->id_raw }}" class="form-select form-select-sm bg-light border-0" onchange="syncModalCoaToStaff('{{ $item->id_raw }}')" required>
                                    <option value="" disabled selected>-- Select Expense Account --</option>
                                    @foreach($chartOfAccounts as $coa)
                                        <option value="{{ $coa->id }}" 
                                                data-staff-id="{{ $coa->assigned_to }}"
                                                data-staff-name="{{ $coa->manager?->name }}"
                                                {{ $officeReq->coa_id == $coa->id ? 'selected' : '' }}>
                                            [{{ $coa->code }}] {{ $coa->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label small fw-bold text-dark text-uppercase">Assign Finance Staff / Custodian</label>
                                <select name="assigned_finance_staff_id" id="modalStaffSelect{{ $item->id_raw }}" class="form-select form-select-sm bg-light border-0">
                                    <option value="">-- Assign Staff / Self --</option>
                                    @foreach($financeStaff as $staff)
                                        <option value="{{ $staff->id }}" {{ $officeReq->assigned_finance_staff_id == $staff->id ? 'selected' : '' }}>
                                            {{ $staff->name }} ({{ ucfirst(str_replace('_', ' ', $staff->roles->first()?->name ?? 'Staff')) }})
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <button type="submit" class="btn btn-primary btn-sm w-100 rounded-3 py-2 fw-semibold">
                                <i class="fa-solid fa-floppy-disk me-1"></i> Save Assignment
                            </button>
                        </form>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <!-- 2. Pay Modal for Assigned Finance Person -->
    <div class="modal fade" id="payOfficeModal{{ $item->id_raw }}" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
                <form method="POST" action="{{ route('office-requests.mark-paid', $item->id_raw) }}">
                    @csrf
                    <div class="modal-header bg-success text-white border-0 py-3 px-4">
                        <div class="d-flex align-items-center gap-2">
                            <span class="badge bg-white bg-opacity-20 text-white p-2 rounded-3 fs-6">
                                <i class="fa-solid fa-money-bill-wave"></i>
                            </span>
                            <div>
                                <h5 class="modal-title fw-bold mb-0">Disburse Payment: {{ $item->id_formatted }}</h5>
                                <span class="text-white-50 small">Amount: <strong>ETB {{ number_format($item->net_amount, 2) }}</strong> &bull; Requester: <strong>{{ $item->applicant_name }}</strong></span>
                            </div>
                        </div>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body p-4 bg-white">
                        <div class="p-3 bg-light rounded-3 mb-3 border">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <span class="text-muted small text-uppercase fw-bold">Approved Budget:</span>
                                <strong class="text-success fs-5">ETB {{ number_format($item->net_amount, 2) }}</strong>
                            </div>
                            <div class="small text-muted mb-1"><i class="fa-solid fa-sitemap me-1"></i> Funding Account: <strong>{{ $officeReq->coa?->name ?? 'Head Office Expense' }}</strong></div>
                            <div class="small text-muted"><i class="fa-solid fa-list me-1"></i> Purpose: {{ $item->description }}</div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label small fw-bold text-dark text-uppercase">Payment Voucher Ref #</label>
                            <input type="text" name="payment_reference" class="form-control bg-light border-0" placeholder="e.g. VC-2026-08-001, FT-098234">
                        </div>

                        <div class="mb-3">
                            <label class="form-label small fw-bold text-dark text-uppercase">Payment Remarks / Notes</label>
                            <textarea name="payment_notes" class="form-control bg-light border-0" rows="2" placeholder="e.g. Disbursed cash from Petty Cash to secretary..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer bg-light border-0 py-3 px-4">
                        <button type="button" class="btn btn-light rounded-pill px-3" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success rounded-pill px-4 fw-bold shadow-sm">
                            <i class="fa-solid fa-check-double me-1"></i> Confirm Paid &amp; Complete (ETB {{ number_format($item->net_amount, 2) }})
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    @endif

@endforeach


<script>
/**
 * Automatically select the assigned staff member custodian linked to the selected COA account
 */
function autoSelectFinanceStaff(selectEl, requestId) {
    const selectedOption = selectEl.options[selectEl.selectedIndex];
    if (!selectedOption) return;

    const staffId = selectedOption.getAttribute('data-staff-id');
    const staffName = selectedOption.getAttribute('data-staff-name');
    const staffSelect = document.getElementById('financeStaffSelect' + requestId);
    const badgeEl = document.getElementById('autoStaffBadge' + requestId);

    if (staffSelect) {
        if (staffId && staffId !== '') {
            staffSelect.value = staffId;
            if (badgeEl) {
                badgeEl.innerHTML = '<span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1" style="font-size: .75rem;"><i class="fa-solid fa-link me-1"></i>Auto-assigned from COA: <strong>' + (staffName || 'Staff #' + staffId) + '</strong></span>';
                badgeEl.style.display = 'block';
            }
        } else {
            if (badgeEl) {
                badgeEl.style.display = 'none';
            }
        }
    }
}

/**
 * Toggle tax section when Service or Contract Work is chosen
 */
function toggleDisburseTaxSection(reqId) {
    const taxPanel = document.getElementById('serviceTaxPanel' + reqId);
    if (taxPanel) {
        taxPanel.style.display = 'block';
    }
    recalculateDisbursement(reqId);
}

/**
 * Real-time VAT and Withholding Tax calculation
 */
function recalculateDisbursement(reqId) {
    const grossInput = document.getElementById('modalGrossAmount' + reqId);
    const vatTypeSelect = document.getElementById('modalVatType' + reqId);
    const whtToggle = document.getElementById('modalWithholdingToggle' + reqId);
    
    if (!grossInput) return;
    const gross = parseFloat(grossInput.value) || 0;
    const vatType = vatTypeSelect ? vatTypeSelect.value : 'none';
    const vatRate = 15.00;
    const hasWht = whtToggle ? whtToggle.checked : false;
    const whtRate = 3.00;


    let vatAmount = 0.0;
    let baseAmount = gross;
    let whtAmount = 0.0;
    let netAmount = gross;

    if (vatType === 'exclusive') {
        vatAmount = Math.round(gross * (vatRate / 100) * 100) / 100;
        baseAmount = gross;
        const totalGrossWithVat = gross + vatAmount;
        if (hasWht) {
            whtAmount = Math.round(baseAmount * (whtRate / 100) * 100) / 100;
        }
        netAmount = Math.round((totalGrossWithVat - whtAmount) * 100) / 100;
    } else if (vatType === 'inclusive' || vatType === 'vat_b') {
        baseAmount = Math.round((gross / (1 + (vatRate / 100))) * 100) / 100;
        vatAmount = Math.round((gross - baseAmount) * 100) / 100;
        if (hasWht) {
            whtAmount = Math.round(baseAmount * (whtRate / 100) * 100) / 100;
        }
        netAmount = Math.round((gross - whtAmount) * 100) / 100;
    } else {
        baseAmount = gross;
        vatAmount = 0.0;
        if (hasWht) {
            whtAmount = Math.round(baseAmount * (whtRate / 100) * 100) / 100;
        }
        netAmount = Math.round((gross - whtAmount) * 100) / 100;
    }

    // Set hidden inputs
    const hiddenVat = document.getElementById('modalVatAmount' + reqId);
    const hiddenWht = document.getElementById('modalWithholdingAmount' + reqId);
    const hiddenNet = document.getElementById('modalNetAmount' + reqId);
    const hiddenPaid = document.getElementById('modalPaidAmount' + reqId);

    if (hiddenVat) hiddenVat.value = vatAmount.toFixed(2);
    if (hiddenWht) hiddenWht.value = whtAmount.toFixed(2);
    if (hiddenNet) hiddenNet.value = netAmount.toFixed(2);
    if (hiddenPaid) hiddenPaid.value = netAmount.toFixed(2);

    // Update display labels
    const dispBase = document.getElementById('displayBaseAmount' + reqId);
    const dispVat = document.getElementById('displayVatAmount' + reqId);
    const dispWht = document.getElementById('displayWhtAmount' + reqId);
    const dispNet = document.getElementById('displayNetAmount' + reqId);
    const btnPaySpan = document.getElementById('btnPayAmount' + reqId);

    const fmt = num => 'ETB ' + num.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

    if (dispBase) dispBase.innerText = fmt(baseAmount);
    if (dispVat) dispVat.innerText = (vatAmount > 0 ? '+ ' : '') + fmt(vatAmount);
    if (dispWht) dispWht.innerText = (whtAmount > 0 ? '- ' : '') + fmt(whtAmount);
    if (dispNet) dispNet.innerText = fmt(netAmount);
    if (btnPaySpan) btnPaySpan.innerText = fmt(netAmount);

    // Toggle Withholding Receipt Upload Requirement and Visibility
    const whtReceiptGroup = document.getElementById('withholdingReceiptSection' + reqId);
    const whtReceiptInput = document.getElementById('modalWithholdingReceipt' + reqId);
    if (whtReceiptGroup) {
        if (hasWht) {
            whtReceiptGroup.style.display = 'block';
            if (whtReceiptInput && whtReceiptInput.dataset.hasExisting !== '1') {
                whtReceiptInput.required = true;
            }
        } else {
            whtReceiptGroup.style.display = 'none';
            if (whtReceiptInput) {
                whtReceiptInput.required = false;
            }
        }
    }
}


// Auto-trigger on modal open to reflect current COA custodian and tax state
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('[id^="financeAssignModal"]').forEach(function (modal) {
        modal.addEventListener('shown.bs.modal', function () {
            const coaSelect = modal.querySelector('select[name="coa_id"]');
            if (coaSelect && coaSelect.selectedIndex > 0) {
                const reqId = modal.id.replace('financeAssignModal', '');
                autoSelectFinanceStaff(coaSelect, reqId);
            }
        });
    });

    document.querySelectorAll('[id^="payModal"]').forEach(function (modal) {
        modal.addEventListener('shown.bs.modal', function () {
            const reqId = modal.id.replace('payModal', '');
            recalculateDisbursement(reqId);
        });
    });
});
</script>


<style>
/* Clean custom styles for Expense Track table */
.table thead th {
    font-weight: 700;
    letter-spacing: 0.5px;
    background-color: #f8fafc !important;
    border-bottom: 2px solid #e2e8f0;
}
.table tbody tr:hover {
    background-color: #f8faff;
}
.modal-content {
    background-color: #ffffff !important;
}
.form-control, .form-select {
    border-color: #cbd5e1;
}
.form-control:focus, .form-select:focus {
    border-color: #3b82f6;
    box-shadow: 0 0 0 0.2rem rgba(59, 130, 246, 0.15);
}
</style>
@endsection
