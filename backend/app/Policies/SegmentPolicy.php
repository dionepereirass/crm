<?php

namespace App\Policies;

use App\Models\Segment;
use App\Models\User;

class SegmentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isSuperAdmin() || $user->hasPermission('segments.view');
    }

    public function view(User $user, Segment $segment): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return $user->hasPermission('segments.view') && $user->hasAccessToPlatform($segment->platform_id);
    }

    public function create(User $user): bool
    {
        return $user->isSuperAdmin() || $user->hasPermission('segments.create');
    }

    public function update(User $user, Segment $segment): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return $user->hasPermission('segments.update') && $user->hasAccessToPlatform($segment->platform_id);
    }

    public function delete(User $user, Segment $segment): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return $user->hasPermission('segments.delete') && $user->hasAccessToPlatform($segment->platform_id);
    }

    public function activate(User $user, Segment $segment): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return $user->hasPermission('segments.activate') && $user->hasAccessToPlatform($segment->platform_id);
    }

    public function preview(User $user): bool
    {
        return $user->isSuperAdmin() || $user->hasPermission('segments.preview');
    }

    public function refresh(User $user, Segment $segment): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return $user->hasPermission('segments.refresh') && $user->hasAccessToPlatform($segment->platform_id);
    }

    public function members(User $user, Segment $segment): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return $user->hasPermission('segments.members') && $user->hasAccessToPlatform($segment->platform_id);
    }

    public function export(User $user, Segment $segment): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return $user->hasPermission('segments.export') && $user->hasAccessToPlatform($segment->platform_id);
    }
}
