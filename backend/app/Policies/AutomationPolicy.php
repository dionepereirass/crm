<?php

namespace App\Policies;

use App\Models\Automation;
use App\Models\User;

class AutomationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('automations.view');
    }

    public function view(User $user, Automation $automation): bool
    {
        if (!$user->hasPermission('automations.view')) {
            return false;
        }

        return $this->belongsToUserPlatform($user, $automation->platform_id);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('automations.create');
    }

    public function update(User $user, Automation $automation): bool
    {
        if (!$user->hasPermission('automations.update')) {
            return false;
        }

        return $this->belongsToUserPlatform($user, $automation->platform_id);
    }

    public function delete(User $user, Automation $automation): bool
    {
        if (!$user->hasPermission('automations.delete')) {
            return false;
        }

        return $this->belongsToUserPlatform($user, $automation->platform_id);
    }

    public function activate(User $user, Automation $automation): bool
    {
        if (!$user->hasPermission('automations.activate')) {
            return false;
        }

        return $this->belongsToUserPlatform($user, $automation->platform_id);
    }

    public function pause(User $user, Automation $automation): bool
    {
        if (!$user->hasPermission('automations.pause')) {
            return false;
        }

        return $this->belongsToUserPlatform($user, $automation->platform_id);
    }

    public function graph(User $user, Automation $automation): bool
    {
        if (!$user->hasPermission('automations.update')) {
            return false;
        }

        return $this->belongsToUserPlatform($user, $automation->platform_id);
    }

    public function runs(User $user, Automation $automation): bool
    {
        if (!$user->hasPermission('automations.runs')) {
            return false;
        }

        return $this->belongsToUserPlatform($user, $automation->platform_id);
    }

    protected function belongsToUserPlatform(User $user, int $platformId): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return $user->platforms()->where('platforms.id', $platformId)->exists();
    }
}
