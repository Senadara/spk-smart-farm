<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class TenantScope implements Scope
{
    /**
     * Apply the scope to a given Eloquent query builder.
     */
    public function apply(Builder $builder, Model $model)
    {
        $user = session('user');

        if ($user && isset($user['role'])) {
            if ($user['role'] === 'pjawab') {
                $builder->where('owner_id', $user['id']);
            } elseif ($user['role'] === 'petugas') {
                $builder->where('owner_id', $user['owner_id']);
            }
        }
    }
}
