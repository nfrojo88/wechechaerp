<?php

namespace App\Policies;

use App\Models\MaterialRequest;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class MaterialRequestPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user)
    {
        return true;
    }

    public function view(User $user, MaterialRequest $mr)
    {
        if ($user->hasAnyRole(['admin', 'global_admin', 'store_manager', 'Store Manager', 'coordinator', 'Coordinator', 'planning_manager', 'Planning Manager', 'purchase_manager', 'Purchase Manager', 'gm', 'general_manager'])) {
            return true;
        }

        if ($user->hasAnyRole(['site_engineer', 'Site Engineer'])) {
            $assignedProjectIds = $user->projects()->pluck('projects.id');
            if ($user->employee?->project_id) {
                $assignedProjectIds->push($user->employee->project_id);
            }
            if ($user->store && $user->store->project_id) {
                $assignedProjectIds->push($user->store->project_id);
            }
            return $user->id === $mr->created_by || $assignedProjectIds->contains($mr->project_id);
        }

        if ($user->hasAnyRole(['store_keeper', 'Store Keeper', 'storekeeper'])) {
            $assignedStoreIds = collect([$user->store_id])
                ->concat(\App\Models\Store::where('manager_id', $user->id)->pluck('id'))
                ->concat(\App\Models\Store::whereHas('users', fn($q) => $q->where('users.id', $user->id))->pluck('id'))
                ->filter()
                ->unique();
            $assignedProjectIds = \App\Models\Store::whereIn('id', $assignedStoreIds)->whereNotNull('project_id')->pluck('project_id')->unique();

            return $assignedStoreIds->contains($mr->destination_store_id) || $assignedProjectIds->contains($mr->project_id);
        }

        return true;
    }

    public function create(User $user)
    {
        return $user->hasAnyRole(['site_engineer', 'Site Engineer', 'admin', 'global_admin'])
            || $user->can('material_requests.create');
    }

    public function update(User $user, MaterialRequest $mr)
    {
        return ($user->id === $mr->created_by || $user->hasAnyRole(['site_engineer', 'Site Engineer', 'admin', 'global_admin']))
            && in_array($mr->status, ['draft', 'pending_planning']);
    }

    public function approvePlanning(User $user, MaterialRequest $mr)
    {
        return $user->hasAnyRole(['planning_manager', 'Planning Manager', 'planning_team', 'admin', 'global_admin'])
            || $user->can('material_requests.planning_approve');
    }

    public function dispatchCoordinator(User $user, MaterialRequest $mr)
    {
        return $user->hasAnyRole(['coordinator', 'Coordinator', 'admin', 'global_admin'])
            || $user->can('material_requests.coordinator_dispatch');
    }

    public function actionStoreManager(User $user, MaterialRequest $mr)
    {
        return $user->hasAnyRole(['store_manager', 'Store Manager', 'purchase_manager', 'Purchase Manager', 'purchase', 'Purchase', 'purchaser', 'coordinator', 'Coordinator', 'admin', 'global_admin'])
            || $user->can('material_requests.approve');
    }

    public function approve(User $user, MaterialRequest $mr)
    {
        return $user->hasAnyRole(['store_manager', 'Store Manager', 'coordinator', 'Coordinator', 'admin', 'global_admin'])
            || $user->can('material_requests.approve');
    }
}
