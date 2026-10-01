<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    /**
     * Determine whether the user can view any users.
     */
    public function viewAny(User $user): bool
    {
        return $user->isSuperAdmin() || $user->hasPermission('users.view');
    }

    /**
     * Determine whether the user can view a specific user.
     */
    public function view(User $user, User $model): bool
    {
        if ($user->isSuperAdmin() || $user->id === $model->id) {
            return true;
        }

        return $user->hasPermission('users.view');
    }

    /**
     * Determine whether the user can create users.
     */
    public function create(User $user): bool
    {
        return $user->isSuperAdmin() || $user->hasPermission('users.create');
    }

    /**
     * Determine whether the user can update the user.
     */
    public function update(User $user, User $model): bool
    {
        if ($user->isSuperAdmin() || $user->id === $model->id) {
            return true;
        }

        return $user->hasPermission('users.update');
    }

    /**
     * Determine whether the user can delete the user.
     */
    public function delete(User $user, User $model): bool
    {
        // Users cannot delete themselves
        if ($user->id === $model->id) {
            return false;
        }

        return $user->isSuperAdmin() || $user->hasPermission('users.delete');
    }
}
