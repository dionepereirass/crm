<?php

namespace App\Policies;

use App\Models\Provider;
use App\Models\User;

class ProviderPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isSuperAdmin() || $user->hasPermission('providers.view');
    }

    public function view(User $user, Provider $provider): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return $user->hasPermission('providers.view') && $user->hasAccessToPlatform($provider->platform_id);
    }

    public function create(User $user): bool
    {
        return $user->isSuperAdmin() || $user->hasPermission('providers.create');
    }

    public function update(User $user, Provider $provider): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return $user->hasPermission('providers.update') && $user->hasAccessToPlatform($provider->platform_id);
    }

    public function delete(User $user, Provider $provider): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return $user->hasPermission('providers.delete') && $user->hasAccessToPlatform($provider->platform_id);
    }

    public function activate(User $user, Provider $provider): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return $user->hasPermission('providers.activate') && $user->hasAccessToPlatform($provider->platform_id);
    }

    public function health(User $user, Provider $provider): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return $user->hasPermission('providers.health') && $user->hasAccessToPlatform($provider->platform_id);
    }

    public function test(User $user, Provider $provider): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return $user->hasPermission('providers.test') && $user->hasAccessToPlatform($provider->platform_id);
    }
}
