<?php

namespace App\Policies;

use App\Models\Player;
use App\Models\User;

class PlayerPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isSuperAdmin() || $user->hasPermission('players.view');
    }

    public function view(User $user, Player $player): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return $user->hasPermission('players.view') && $user->hasAccessToPlatform($player->platform_id);
    }

    public function create(User $user): bool
    {
        return $user->isSuperAdmin() || $user->hasPermission('players.create');
    }

    public function update(User $user, Player $player): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return $user->hasPermission('players.update') && $user->hasAccessToPlatform($player->platform_id);
    }

    public function delete(User $user, Player $player): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return $user->hasPermission('players.delete') && $user->hasAccessToPlatform($player->platform_id);
    }

    public function manageTags(User $user, Player $player): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return $user->hasPermission('players.update') && $user->hasAccessToPlatform($player->platform_id);
    }
}
