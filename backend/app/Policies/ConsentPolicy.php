<?php

namespace App\Policies;

use App\Models\Consent;
use App\Models\User;

class ConsentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyPermission(['privacy.view', 'privacy.consent.view']);
    }

    public function update(User $user, ?Consent $consent = null): bool
    {
        return $user->hasPermissionTo('privacy.consent.update');
    }
}
