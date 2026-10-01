<?php

namespace App\Policies;

use App\Models\Template;
use App\Models\User;

class TemplatePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isSuperAdmin() || $user->hasPermission('templates.view');
    }

    public function view(User $user, Template $template): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return $user->hasPermission('templates.view') && $user->hasAccessToPlatform($template->platform_id);
    }

    public function create(User $user): bool
    {
        return $user->isSuperAdmin() || $user->hasPermission('templates.create');
    }

    public function update(User $user, Template $template): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return $user->hasPermission('templates.update') && $user->hasAccessToPlatform($template->platform_id);
    }

    public function delete(User $user, Template $template): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return $user->hasPermission('templates.delete') && $user->hasAccessToPlatform($template->platform_id);
    }

    public function publish(User $user, Template $template): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return $user->hasPermission('templates.publish') && $user->hasAccessToPlatform($template->platform_id);
    }

    public function archive(User $user, Template $template): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return $user->hasPermission('templates.archive') && $user->hasAccessToPlatform($template->platform_id);
    }

    public function preview(User $user): bool
    {
        return $user->isSuperAdmin() || $user->hasPermission('templates.preview');
    }

    public function duplicate(User $user, Template $template): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return $user->hasPermission('templates.duplicate') && $user->hasAccessToPlatform($template->platform_id);
    }

    public function versions(User $user, Template $template): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return $user->hasPermission('templates.versions') && $user->hasAccessToPlatform($template->platform_id);
    }

    public function restore(User $user, Template $template): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return $user->hasPermission('templates.restore') && $user->hasAccessToPlatform($template->platform_id);
    }
}
