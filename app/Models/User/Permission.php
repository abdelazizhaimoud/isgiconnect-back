<?php

namespace App\Models\User;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Permission extends Model
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
        'group',
        'resource',
        'action',
    ];

    /**
     * Get the roles that belong to the permission.
     */
    public function roles()
    {
        return $this->belongsToMany(Role::class)
                    ->withTimestamps();
    }

    /**
     * Scope permissions by group.
     */
    public function scopeByGroup($query, string $group)
    {
        return $query->where('group', $group);
    }

    /**
     * Scope permissions by resource.
     */
    public function scopeByResource($query, string $resource)
    {
        return $query->where('resource', $resource);
    }

    /**
     * Get permission by resource and action.
     */
    public static function findByResourceAction(string $resource, string $action): ?self
    {
        return static::where('resource', $resource)
                    ->where('action', $action)
                    ->first();
    }
}