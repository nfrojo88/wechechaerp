<?php

namespace App\Policies;

use App\Models\Expense;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class ExpensePolicy
{
    use HandlesAuthorization;

    public function before(User $user, $ability)
    {
        if ($user->hasAnyRole(['global_admin', 'admin', 'gm', 'general_manager', 'coordinator', 'Coordinator'])) {
            return true;
        }
    }

    public function viewAny(User $user)
    {
        $roleNames = strtolower(implode(' ', $user->getRoleNames()->toArray()));
        return $user->can('finance.view') || $user->can('finance.approve') || str_contains($roleNames, 'hr') || str_contains($roleNames, 'gm') || str_contains($roleNames, 'coordinator') || $user->can('hr.view');
    }

    public function view(User $user, Expense $e)
    {
        $roleNames = strtolower(implode(' ', $user->getRoleNames()->toArray()));
        return $user->can('finance.view') || $user->can('finance.approve') || str_contains($roleNames, 'hr') || str_contains($roleNames, 'gm') || str_contains($roleNames, 'coordinator') || $user->can('hr.view');
    }

    public function create(User $user)
    {
        return $user->can('finance.view') || $user->can('finance.approve') || $user->can('finance.create');
    }

    public function update(User $user, Expense $e)
    {
        return ($user->can('finance.view') || $user->can('finance.approve')) && $e->status === 'pending';
    }

    public function approve(User $user, Expense $e)
    {
        $roleNames = strtolower(implode(' ', $user->getRoleNames()->toArray()));
        return $user->can('finance.approve') || str_contains($roleNames, 'gm') || str_contains($roleNames, 'coordinator') || $user->hasAnyRole(['gm', 'general_manager', 'coordinator', 'Coordinator', 'admin', 'global_admin']);
    }
}
