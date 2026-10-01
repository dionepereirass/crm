<?php

namespace App\Policies;

use App\Models\AutomationRun;
use App\Models\User;

class AutomationRunPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('automations.runs');
    }

    public function view(User $user, AutomationRun $run): bool
    {
        if (!$user->hasPermission('automations.runs')) {
            return false;
        }

        return $this->belongsToUserPlatform($user, $run->platform_id);
    }

    public function cancel(User $user, AutomationRun $run): bool
    {
        if (!$user->hasPermission('automations.cancel')) {
            return false;
        }

        return $this->belongsToUserPlatform($user, $run->platform_id);
    }

    protected function belongsToUserPlatform(User $user, int $platformId): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return $user->platforms()->where('platforms.id', $platformId)->exists();
    }
}
