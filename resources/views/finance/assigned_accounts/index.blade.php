@extends('layouts.app')
@section('title', 'My Assigned Accounts & Petty Cash')
@section('content')
<div class="container-fluid py-3">
    @php
        $isViewingAll = !empty($viewAll);
        $isFinHead = !empty($isFinanceHead);
    @endphp
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
        <div>
            <h1 class="h3 mb-1 text-dark fw-bold">
                <i class="fas fa-wallet me-2 text-primary"></i>{{ $isViewingAll ? 'All Assigned Custodian Accounts' : 'My Assigned Accounts & Petty Cash' }}
            </h1>
            <p class="text-muted small mb-0">Manage your petty cash funds, log payments, track expense cycles, and submit replenishment requests to the Finance Head.</p>
        </div>
        @if($isFinHead)
            <div class="btn-group p-1 bg-light rounded-pill border shadow-sm">
                <a href="{{ route('assigned-accounts.index') }}" class="btn btn-sm rounded-pill px-3 fw-bold {{ !$isViewingAll ? 'btn-primary shadow-sm' : 'text-muted' }}">
                    <i class="fas fa-user-check me-1"></i> My Accounts
                </a>
                <a href="{{ route('assigned-accounts.index', ['view' => 'all']) }}" class="btn btn-sm rounded-pill px-3 fw-bold {{ $isViewingAll ? 'btn-primary shadow-sm' : 'text-muted' }}">
                    <i class="fas fa-users me-1"></i> All Custodians
                </a>
            </div>
        @endif
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show rounded-4 shadow-sm border-0">
            <i class="fas fa-check-circle me-2"></i>{{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <div class="row g-4">
        @forelse($accounts as $account)
            @php
                $pendingCount = \App\Models\PettyCashReplenishment::where('chart_of_account_id', $account->id)->where('status', 'pending')->count();
            @endphp
            <div class="col-md-6 col-lg-4">
                <div class="card shadow-sm h-100 border-0 rounded-4 transition-hover">
                    <div class="card-body p-4 d-flex flex-column justify-content-between">
                        <div>
                            <div class="d-flex justify-content-between align-items-start mb-3">
                                <div>
                                    <span class="badge bg-secondary mb-2 rounded-pill px-3">Code: {{ $account->code }}</span>
                                    <h5 class="card-title fw-bold text-dark mb-1">{{ $account->name }}</h5>
                                    @if($account->manager)
                                        <small class="text-muted d-block">
                                            <i class="fas fa-user-tie me-1"></i> Custodian: {{ $account->manager->name }}
                                        </small>
                                    @endif
                                </div>
                                <div class="rounded-circle shadow-sm" style="width: 48px; height: 48px; min-width: 48px; display: flex; align-items: center; justify-content: center; background-color: #e0f2fe; color: #0284c7;">
                                    <i class="fas fa-wallet fa-lg"></i>
                                </div>
                            </div>
                            
                            <p class="text-muted small mb-4">{{ Str::limit($account->description ?? 'Operational petty cash fund for site and office disbursements.', 80) }}</p>
                        </div>
                        
                        <div>
                            @if($pendingCount > 0)
                                <div class="mb-3">
                                    <span class="badge bg-warning-subtle text-warning-emphasis border border-warning px-3 py-2 rounded-pill small w-100 d-inline-block text-center">
                                        <i class="fas fa-clock me-1"></i> Replenishment Pending Finance Review
                                    </span>
                                </div>
                            @endif

                            <div class="d-flex justify-content-between align-items-end pt-3 border-top">
                                <div>
                                    <span class="text-muted small text-uppercase fw-bold d-block">Current Balance</span>
                                    <h4 class="mb-0 {{ $account->current_balance < 0 ? 'text-danger' : 'text-success' }} fw-bold">
                                        ETB {{ number_format($account->current_balance, 2) }}
                                    </h4>
                                </div>
                                <a href="{{ route('assigned-accounts.show', $account->id) }}" class="btn btn-primary rounded-pill px-4 shadow-sm fw-semibold">
                                    Manage <i class="fas fa-arrow-right ms-1"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        @empty
            <div class="col-12">
                <div class="card shadow-sm border-0 rounded-4 p-5 text-center bg-white">
                    <div class="text-muted mb-3"><i class="fas fa-inbox fa-3x"></i></div>
                    <h5 class="fw-bold text-dark">No Accounts Assigned</h5>
                    <p class="text-muted mb-0">You do not have any petty cash or assigned accounts allocated to your profile.</p>
                </div>
            </div>
        @endforelse
    </div>
</div>
@endsection
