<?php

namespace App\Models\Traits;

use App\Models\User\Role;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

trait HasRoles
{
    /**
     * Get the roles for the user.
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class)
                    ->withPivot(['assigned_at', 'assigned_by', 'expires_at'])
                    ->withTimestamps();
    }

    /**
     * Check if user has role.
     */
    public function hasRole(string|Role $role): bool
    {
        if (is_string($role)) {
            return $this->roles()->where('slug', $role)->exists();
        }

        return $this->roles()->where('id', $role->id)->exists();
    }

    /**
     * Check if user has any of the given roles.
     */
    public function hasAnyRole(array $roles): bool
    {
        foreach ($roles as $role) {
            if ($this->hasRole($role)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if user has all of the given roles.
     */
    public function hasAllRoles(array $roles): bool
    {
        foreach ($roles as $role) {
            if (!$this->hasRole($role)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Assign role to user.
     */
    public function assignRole(string|Role $role, ?int $assignedBy = null, ?\DateTime $expiresAt = null): void
    {
        if (is_string($role)) {
            $role = Role::where('slug', $role)->firstOrFail();
        }

        $pivotData = [
            'assigned_at' => now(),
            'assigned_by' => $assignedBy,
            'expires_at' => $expiresAt,
        ];

        $this->roles()->syncWithoutDetaching([$role->id => $pivotData]);
    }

    /**
     * Remove role from user.
     */
    public function removeRole(string|Role $role): void
    {
        if (is_string($role)) {
            $role = Role::where('slug', $role)->firstOrFail();
        }

        $this->roles()->detach($role->id);
    }

    /**
     * Sync roles for user.
     */
    public function syncRoles(array $roles): void
    {
        $roleIds = [];
        
        foreach ($roles as $role) {
            if (is_string($role)) {
                $roleModel = Role::where('slug', $role)->firstOrFail();
                $roleIds[] = $roleModel->id;
            } else {
                $roleIds[] = $role->id;
            }
        }

        $this->roles()->sync($roleIds);
    }

    /**
     * Get user's role names.
     */
    public function getRoleNames(): array
    {
        return $this->roles->pluck('name')->toArray();
    }

    /**
     * Check if user is admin.
     */
    public function isAdmin(): bool
    {
        return $this->hasRole('admin');
    }

    /**
     * Get highest role level.
     */
    public function getHighestRoleLevel(): int
    {
        return $this->roles->max('level') ?? 0;
    }
}