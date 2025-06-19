<?php

namespace App\Models\Traits;

use App\Models\User\Permission;
use Illuminate\Support\Collection;

trait HasPermissions
{
    /**
     * Get all permissions through roles.
     */
    public function getPermissions(): Collection
    {
        return $this->roles()
                    ->with('permissions')
                    ->get()
                    ->pluck('permissions')
                    ->flatten()
                    ->unique('id');
    }

    /**
     * Check if user has permission.
     */
    public function hasPermission(string $permission): bool
    {
        return $this->getPermissions()
                    ->contains('slug', $permission);
    }

    /**
     * Check if user has any of the given permissions.
     */
    public function hasAnyPermission(array $permissions): bool
    {
        $userPermissions = $this->getPermissions()->pluck('slug')->toArray();
        
        return !empty(array_intersect($permissions, $userPermissions));
    }

    /**
     * Check if user has all of the given permissions.
     */
    public function hasAllPermissions(array $permissions): bool
    {
        $userPermissions = $this->getPermissions()->pluck('slug')->toArray();
        
        return empty(array_diff($permissions, $userPermissions));
    }

    /**
     * Check if user can perform action on resource.
     */
    public function can(string $action, string $resource = null): bool
    {
        if ($resource) {
            $permission = "{$resource}.{$action}";
        } else {
            $permission = $action;
        }

        return $this->hasPermission($permission);
    }

    /**
     * Get permissions by group.
     */
    public function getPermissionsByGroup(string $group): Collection
    {
        return $this->getPermissions()
                    ->where('group', $group);
    }

    /**
     * Get permissions by resource.
     */
    public function getPermissionsByResource(string $resource): Collection
    {
        return $this->getPermissions()
                    ->where('resource', $resource);
    }

    /**
     * Get all permission slugs.
     */
    public function getPermissionSlugs(): array
    {
        return $this->getPermissions()->pluck('slug')->toArray();
    }

    /**
     * Check if user is super admin (has all permissions).
     */
    public function isSuperAdmin(): bool
    {
        return $this->hasRole('super-admin');
    }
}