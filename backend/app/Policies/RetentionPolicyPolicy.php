<?php

namespace App\Policies;

use App\Models\RetentionPolicy;
use App\Models\User;

class RetentionPolicyPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyPermission(['privacy.view', 'privacy.retention.view']);
    }

    public function manage(User $user, ?RetentionPolicy $policy = null): bool
    {
        return $user->hasPermissionTo('privacy.retention.manage');
    }
}
