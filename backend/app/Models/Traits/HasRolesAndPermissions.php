<?php

namespace App\Models\Traits;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Collection;

trait HasRolesAndPermissions
{
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'role_user')
            ->withPivot('created_at');
    }

    /**
     * Check if user has a specific role or any role in array.
     */
    public function hasRole(string|array $roles): bool
    {
        $roleSlugs = is_array($roles) ? $roles : [$roles];

        return $this->roles->contains(function (Role $role) use ($roleSlugs) {
            return in_array($role->slug, $roleSlugs, true);
        });
    }

    /**
     * Super Admin has all privileges.
     */
    public function isSuperAdmin(): bool
    {
        return $this->hasRole('SUPER_ADMIN');
    }

    /**
     * Check if user has permission (directly via assigned roles, or if super admin).
     */
    public function hasPermission(string $permissionSlug): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        foreach ($this->roles as $role) {
            if ($role->permissions->contains('slug', $permissionSlug)) {
                return true;
            }
        }

        return false;
    }

    public function hasPermissionTo(string $permissionSlug): bool
    {
        return $this->hasPermission($permissionSlug);
    }

    public function hasAnyPermission(array $permissionSlugs): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        foreach ($permissionSlugs as $slug) {
            if ($this->hasPermission($slug)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Retrieve all unique permissions granted to this user.
     */
    public function getAllPermissions(): Collection
    {
        if ($this->isSuperAdmin()) {
            return Permission::all();
        }

        return $this->roles->flatMap(function (Role $role) {
            return $role->permissions;
        })->unique('id')->values();
    }

    /**
     * Assign a role by slug or model instance.
     */
    public function assignRole(Role|string $role): void
    {
        if (is_string($role)) {
            $role = Role::where('slug', $role)->firstOrFail();
        }

        $this->roles()->syncWithoutDetaching([$role->id]);
        $this->load('roles.permissions');
    }
}
