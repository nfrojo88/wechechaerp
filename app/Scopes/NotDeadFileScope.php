<?php

namespace App\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class NotDeadFileScope implements Scope
{
    /**
     * Apply the scope to a given Eloquent query builder.
     * Automatically filters out archived "Dead File" employees centrally from all active queries.
     */
    public function apply(Builder $builder, Model $model)
    {
        $table = $model->getTable();
        $builder->where(function ($q) use ($table) {
            $q->where("{$table}.is_dead_file", false)
              ->orWhereNull("{$table}.is_dead_file");
        })->where(function ($q) use ($table) {
            $q->where("{$table}.status", '!=', 'dead_file')
              ->orWhereNull("{$table}.status");
        });
    }
}
