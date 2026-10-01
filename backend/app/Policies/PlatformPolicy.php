<?php

namespace App\Policies;

use App\Models\Platform;
use App\Models\User;

class PlatformPolicy
{
    /**
     * Determine whether the user can view any platforms.
     */
    public function viewAny(User $user): bool
    {
        return $user->isSuperAdmin() || $user->hasPermission('platforms.view');
    }

    /**
     * Determine whether the user can view the specific platform.
     */
    public function view(User $user, Platform $platform): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return $user->hasAccessToPlatform($platform) && $user->hasPermission('platforms.view');
    }

    /**
     * Determine whether the user can create platforms.
     */
    public function create(User $user): bool
    {
        return $user->isSuperAdmin() || $user->hasPermission('platforms.create');
    }

    /**
     * Determine whether the user can update the platform.
     */
    public function update(User $user, Platform $platform): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return $user->hasAccessToPlatform($platform) && $user->hasPermission('platforms.update');
    }

    /**
     * Determine whether the user can delete the platform.
     */
    public function delete(User $user, Platform $platform): bool
    {
        return $user->isSuperAdmin();
    }
}
