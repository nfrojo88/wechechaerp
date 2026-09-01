<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Builder;

/**
 * @mixin \Illuminate\Database\Eloquent\Model
 * @method static void addGlobalScope(string $scope, \Closure $implementation)
 */
trait ScopesByStore
{
    /**
     * The "booted" method of the model.
     *
     * @return void
     */
    protected static function booted()
    {
        $user = auth()->user();
        if ($user && $user->roles && in_array($user->roles->first()?->name ?? '', config('erp.store_scoped_roles', ['store_keeper', 'site_engineer']))) {
            static::addGlobalScope('store', function (Builder $query) use ($user) {
                if ($user->store_id) {
                    $table = $query->getModel()->getTable();
                    if (\Illuminate\Support\Facades\Schema::hasColumn($table, 'store_id')) {
                        $query->where($table . '.store_id', $user->store_id);
                    }
                }
            });
        }
    }
}
