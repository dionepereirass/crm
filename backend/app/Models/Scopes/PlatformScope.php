<?php

namespace App\Models\Scopes;

use App\Services\Platforms\PlatformContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Auth;

class PlatformScope implements Scope
{
    /**
     * Apply the scope to a given Eloquent query builder.
     */
    public function apply(Builder $builder, Model $model): void
    {
        $context = app(PlatformContext::class);

        // If a specific platform is selected in context, scope queries to it
        if ($context->hasPlatform()) {
            $builder->where($model->qualifyColumn('platform_id'), $context->getPlatformId());
            return;
        }

        // If user is logged in and is NOT super admin, restrict to platforms they have access to
        $user = Auth::user();
        if ($user && method_exists($user, 'isSuperAdmin') && !$user->isSuperAdmin()) {
            $platformIds = $user->platforms->pluck('id')->all();
            $builder->whereIn($model->qualifyColumn('platform_id'), $platformIds);
        }
    }
}
