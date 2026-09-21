<?php

namespace App\Policies;

use App\Models\Store;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class StorePolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user)
    {
        return $user->hasRole('secretary') || $user->hasPermissionTo('stores.view');
    }

    public function view(User $user, Store $store)
    {
        if ($user->hasRole('global_admin') || $user->hasRole('admin') || $user->hasRole('secretary')) {
            return true;
        }

        if ($user->store_id && $user->store_id !== $store->id) {
            return false;
        }

        return $user->hasPermissionTo('stores.view');
    }

    public function create(User $user)
    {
        if ($user->hasAnyRole(['global_admin', 'admin', 'store_manager'])) {
            return $user->hasPermissionTo('stores.create');
        }
        return false;
    }

    public function update(User $user, Store $store)
    {
        if ($user->hasAnyRole(['global_admin', 'admin', 'store_manager'])) {
            return $user->hasPermissionTo('stores.edit');
        }
        return false;
    }

    public function delete(User $user, Store $store)
    {
        if ($user->hasAnyRole(['global_admin', 'admin', 'store_manager'])) {
            return $user->hasPermissionTo('stores.delete');
        }
        return false;
    }
}
