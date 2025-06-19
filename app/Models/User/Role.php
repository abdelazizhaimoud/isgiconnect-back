<?php

namespace App\Models\User;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Role extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'slug',
        'description',
        'is_default',
        'level',
        'meta',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'is_default' => 'boolean',
        'meta' => 'array',
    ];

    /**
     * Get the users that belong to the role.
     */
    public function users()
    {
        return $this->belongsToMany(User::class)
                    ->withPivot(['assigned_at', 'assigned_by', 'expires_at'])
                    ->withTimestamps();
    }

    /**
     * Get the permissions that belong to the role.
     */
    public function permissions()
    {
        return $this->belongsToMany(Permission::class)
                    ->withTimestamps();
    }

    /**
     * Check if role has permission.
     */
    public function hasPermission(string $permission): bool
    {
        return $this->permissions()->where('slug', $permission)->exists();
    }

    /**
     * Give permission to role.
     */
    public function givePermissionTo(string|Permission $permission): void
    {
        if (is_string($permission)) {
            $permission = Permission::where('slug', $permission)->firstOrFail();
        }

        $this->permissions()->syncWithoutDetaching([$permission->id]);
    }

    /**
     * Revoke permission from role.
     */
    public function revokePermissionTo(string|Permission $permission): void
    {
        if (is_string($permission)) {
            $permission = Permission::where('slug', $permission)->firstOrFail();
        }

        $this->permissions()->detach($permission->id);
    }
}