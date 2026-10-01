<?php

namespace App\Policies;

use App\Models\Tag;
use App\Models\User;

class TagPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isSuperAdmin() || $user->hasPermission('players.view');
    }

    public function view(User $user, Tag $tag): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return $user->hasPermission('players.view') && $user->hasAccessToPlatform($tag->platform_id);
    }

    public function create(User $user): bool
    {
        return $user->isSuperAdmin() || $user->hasPermission('players.create') || $user->hasPermission('players.update');
    }

    public function update(User $user, Tag $tag): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return $user->hasPermission('players.update') && $user->hasAccessToPlatform($tag->platform_id);
    }

    public function delete(User $user, Tag $tag): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return $user->hasPermission('players.delete') && $user->hasAccessToPlatform($tag->platform_id);
    }
}
