@extends('layouts.app')

@section('title', 'Stores')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0">Stores / Sites</h1>
    @can('stores.create')
    <a href="{{ route('stores.create') }}" class="btn btn-primary">
        <i class="fa-solid fa-plus me-1"></i> New Store
    </a>
    @endcan
</div>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <form action="{{ route('stores.index') }}" method="GET" class="row g-3">
            <div class="col-md-5">
                <div class="input-group">
                    <span class="input-group-text bg-light border-end-0"><i class="fa-solid fa-search text-muted"></i></span>
                    <input type="text" name="search" class="form-control border-start-0 ps-0" placeholder="Search by name, code, or address..." value="{{ request('search') }}">
                </div>
            </div>
            <div class="col-md-4">
                <select name="type" class="form-select">
                    <option value="">All Types</option>
                    @foreach(['site', 'warehouse', 'yard'] as $type)
                        <option value="{{ $type }}" {{ request('type') === $type ? 'selected' : '' }}>
                            {{ ucfirst($type) }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <button type="submit" class="btn btn-primary w-100"><i class="fa-solid fa-filter me-1"></i> Filter</button>
            </div>
        </form>
    </div>
</div>

<div class="row g-4">
    @forelse($stores as $store)
    <div class="col-sm-6 col-xl-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <h6 class="mb-1 fw-semibold">{{ $store->name }}</h6>
                        <code class="small">{{ $store->code }}</code>
                    </div>
                    <span class="badge bg-{{ $store->is_active ? 'success' : 'secondary' }}">
                        {{ $store->is_active ? 'Active' : 'Inactive' }}
                    </span>
                </div>
                <hr class="my-2">
                <div class="small text-muted mb-1">
                    <i class="fa-solid fa-tag me-1"></i> {{ ucfirst($store->type) }}
                </div>
                @if($store->project)
                <div class="small text-muted mb-1">
                    <i class="fa-solid fa-building-columns me-1"></i> {{ $store->project->name }}
                </div>
                @endif
                @if($store->manager)
                <div class="small text-muted mb-1">
                    <i class="fa-solid fa-user me-1"></i> {{ $store->manager->name }}
                </div>
                @endif
                @if($store->address)
                <div class="small text-muted mb-1">
                    <i class="fa-solid fa-location-dot me-1"></i> {{ $store->address }}
                </div>
                @endif
                @if($store->pettyCashAccount)
                <div class="small text-muted">
                    <i class="fa-solid fa-wallet text-success me-1"></i> {{ $store->pettyCashAccount->name }} [{{ $store->pettyCashAccount->code }}]
                </div>
                @endif
            </div>
            <div class="card-footer bg-transparent border-0 d-flex justify-content-between align-items-center">
                <a href="{{ route('stores.show', $store) }}" class="btn btn-sm btn-outline-secondary">
                    <i class="fa-solid fa-eye me-1"></i> View
                </a>
                @can('stores.edit')
                <a href="{{ route('stores.edit', $store) }}" class="btn btn-sm btn-outline-primary">
                    <i class="fa-solid fa-pen"></i>
                </a>
                @endcan
            </div>
        </div>
    </div>
    @empty
    <div class="col-12">
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center text-muted py-5">
                <i class="fa-solid fa-warehouse fa-3x mb-3 opacity-25"></i>
                <p>No stores found. <a href="{{ route('stores.create') }}">Create your first store.</a></p>
            </div>
        </div>
    </div>
    @endforelse
</div>

@if($stores->hasPages())
<div class="mt-4">
    {{ $stores->links() }}
</div>
@endif
@endsection
