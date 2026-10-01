<?php

namespace App\Policies;

use App\Models\DataSubjectRequest;
use App\Models\User;

class DataSubjectRequestPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyPermission(['privacy.view', 'privacy.requests.view', 'privacy.audit.view']);
    }

    public function view(User $user, ?DataSubjectRequest $request = null): bool
    {
        return $user->hasAnyPermission(['privacy.view', 'privacy.requests.view']);
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('privacy.requests.create');
    }

    public function update(User $user, ?DataSubjectRequest $request = null): bool
    {
        return $user->hasPermissionTo('privacy.requests.update');
    }

    public function process(User $user, ?DataSubjectRequest $request = null): bool
    {
        return $user->hasPermissionTo('privacy.requests.process');
    }

    public function export(User $user, ?DataSubjectRequest $request = null): bool
    {
        return $user->hasPermissionTo('privacy.requests.export');
    }

    public function delete(User $user, ?DataSubjectRequest $request = null): bool
    {
        return $user->hasPermissionTo('privacy.requests.delete');
    }
}
