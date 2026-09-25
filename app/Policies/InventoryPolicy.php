<?php

namespace App\Policies;

use App\Models\Inventory;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class InventoryPolicy
{
    use HandlesAuthorization;

    public function before(User $user, $ability)
    {
        if ($user->hasAnyRole(['global_admin', 'admin', 'store_manager'])) {
            return true;
        }
    }

    public function viewAny(User $user)
    {
        return $user->can('inventory.view') || $user->hasAnyRole(['Coordinator', 'coordinator', 'admin', 'global_admin', 'store_manager', 'store_keeper']);
    }

    public function view(User $user, Inventory $inventory)
    {
        if ($user->hasAnyRole(['global_admin', 'admin', 'store_manager', 'coordinator', 'Coordinator'])) {
            return true;
        }

        if ($user->hasRole('store_keeper')) {
            $assignedStoreId = $user->store_id ?? $user->store?->id ?? \App\Models\Store::where('manager_id', $user->id)->value('id');
            if ($assignedStoreId && (int)$assignedStoreId !== (int)$inventory->store_id) {
                return false;
            }
            return true;
        }

        if ($user->store_id && (int)$user->store_id !== (int)$inventory->store_id) {
            return false;
        }

        return $user->can('inventory.view');
    }

    public function create(User $user)
    {
        return $user->can('inventory.create') || $user->hasAnyRole(['global_admin', 'admin', 'store_manager', 'store_keeper']);
    }

    public function update(User $user, Inventory $inventory)
    {
        if ($user->hasAnyRole(['global_admin', 'admin', 'store_manager'])) {
            return true;
        }

        if ($user->hasRole('store_keeper')) {
            $assignedStoreId = $user->store_id ?? $user->store?->id ?? \App\Models\Store::where('manager_id', $user->id)->value('id');
            if ($assignedStoreId && (int)$assignedStoreId !== (int)$inventory->store_id) {
                return false;
            }
            return true;
        }

        if ($user->store_id && (int)$user->store_id !== (int)$inventory->store_id) {
            return false;
        }

        return $user->can('inventory.edit');
    }

    public function delete(User $user, Inventory $inventory)
    {
        return $user->can('inventory.delete') || $user->hasAnyRole(['global_admin', 'admin']);
    }
}
