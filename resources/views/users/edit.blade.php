@extends('layouts.app')

@section('title', 'Edit User')

@section('content')
<div class="d-flex align-items-center mb-4">
    <a href="{{ route('users.index') }}" class="btn btn-sm btn-outline-secondary me-3">
        <i class="fa-solid fa-arrow-left"></i>
    </a>
    <h1 class="h3 mb-0">Edit User: {{ $user->name }}</h1>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body">
        <form method="POST" action="{{ route('users.update', $user) }}">
            @csrf
            @method('PUT')
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Full Name <span class="text-danger">*</span></label>
                    <input type="text" name="name" class="form-control @error('name') is-invalid @enderror"
                           value="{{ old('name', $user->name) }}" required>
                    @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label">Email Address <span class="text-danger">*</span></label>
                    <input type="email" name="email" class="form-control @error('email') is-invalid @enderror"
                           value="{{ old('email', $user->email) }}" required>
                    @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="col-12 mt-4"><h6 class="border-bottom pb-2">System Roles & Access (Multi-Role Assignment)</h6></div>

                <div class="col-12">
                    <label class="form-label fw-bold">Assigned System Role(s) <span class="text-danger">*</span></label>
                    <p class="text-xs text-muted mb-2">Check one or more roles to assign to this user. The user can switch between roles from the top navigation bar or profile.</p>
                    <div class="row g-2 border rounded p-3 bg-light @error('roles') border-danger @enderror" style="max-height: 250px; overflow-y: auto;">
                        @php
                            $userRoleNames = $user->roles->pluck('name')->toArray();
                            $checkedRoles = old('roles', $userRoleNames);
                        @endphp
                        @foreach($roles as $role)
                            <div class="col-md-4 col-sm-6">
                                <div class="form-check p-2 bg-white rounded border shadow-xs h-100">
                                    <input class="form-check-input ms-1 me-2" type="checkbox" name="roles[]" 
                                           value="{{ $role->name }}" id="role_{{ $role->id }}"
                                           {{ in_array($role->name, $checkedRoles) ? 'checked' : '' }}>
                                    <label class="form-check-label small fw-semibold text-dark text-truncate d-block" for="role_{{ $role->id }}">
                                        {{ ucfirst(str_replace('_', ' ', $role->name)) }}
                                    </label>
                                </div>
                            </div>
                        @endforeach
                    </div>
                    @error('roles')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                </div>

                <div class="col-md-6 mt-3">
                    <label class="form-label">Assign to Store (Optional)</label>
                    <select name="store_id" class="form-select @error('store_id') is-invalid @enderror">
                        <option value="">— No Store Restriction —</option>
                        @foreach(\App\Models\Store::where('is_active', true)->get() as $store)
                        <option value="{{ $store->id }}" @selected(old('store_id', $user->store_id) == $store->id)>
                            {{ $store->name }} ({{ $store->code }})
                        </option>
                        @endforeach
                    </select>
                    <div class="form-text">Restricts this user's data view to a specific store.</div>
                    @error('store_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="col-12 mt-3">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" name="is_active" id="isActive" value="1" @checked(old('is_active', $user->is_active))>
                        <label class="form-check-label" for="isActive">User is active</label>
                    </div>
                </div>

                <div class="col-12 d-flex justify-content-end gap-2 mt-4">
                    <a href="{{ route('users.index') }}" class="btn btn-secondary">Cancel</a>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </div>
        </form>
    </div>
</div>
@endsection
