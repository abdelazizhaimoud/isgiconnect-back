<?php

namespace App\Models\Media;

use App\Models\User\User;
use App\Models\Traits\Sluggable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MediaFolder extends Model
{
    use HasFactory, Sluggable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'parent_id',
        'user_id',
        'name',
        'slug',
        'description',
        'permissions',
        'is_public',
        'media_count',
        'sort_order',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'permissions' => 'array',
        'is_public' => 'boolean',
    ];

    /**
     * Slug source field.
     */
    protected string $slugSource = 'name';

    /**
     * Get the user that owns the folder.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the parent folder.
     */
    public function parent()
    {
        return $this->belongsTo(MediaFolder::class, 'parent_id');
    }

    /**
     * Get the children folders.
     */
    public function children()
    {
        return $this->hasMany(MediaFolder::class, 'parent_id')
                    ->orderBy('sort_order');
    }

    /**
     * Get the media in this folder.
     */
    public function media()
    {
        return $this->hasMany(Media::class, 'folder_id')
                    ->orderBy('order_column');
    }

    /**
     * Get all descendants.
     */
    public function descendants()
    {
        $descendants = collect();
        
        foreach ($this->children as $child) {
            $descendants->push($child);
            $descendants = $descendants->merge($child->descendants());
        }
        
        return $descendants;
    }

    /**
     * Get all ancestors.
     */
    public function ancestors()
    {
        $ancestors = collect();
        $parent = $this->parent;
        
        while ($parent) {
            $ancestors->prepend($parent);
            $parent = $parent->parent;
        }
        
        return $ancestors;
    }

    /**
     * Get folder path.
     */
    public function getPathAttribute(): string
    {
        $path = $this->ancestors()->pluck('name')->implode(' / ');
        
        if ($path) {
            $path .= ' / ';
        }
        
        return $path . $this->name;
    }

    /**
     * Get folder breadcrumbs.
     */
    public function getBreadcrumbsAttribute(): array
    {
        $breadcrumbs = $this->ancestors()->map(function ($folder) {
            return [
                'id' => $folder->id,
                'name' => $folder->name,
                'slug' => $folder->slug,
            ];
        })->toArray();

        $breadcrumbs[] = [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
        ];

        return $breadcrumbs;
    }

    /**
     * Update media count.
     */
    public function updateMediaCount(): void
    {
        $count = $this->media()->count();
        $this->update(['media_count' => $count]);
    }

    /**
     * Check if user can access folder.
     */
    public function userCanAccess(User $user): bool
    {
        if ($this->user_id === $user->id) {
            return true;
        }

        if ($this->is_public) {
            return true;
        }

        $permissions = $this->permissions ?? [];
        
        return in_array($user->id, $permissions['users'] ?? []) ||
               !empty(array_intersect($user->getRoleNames(), $permissions['roles'] ?? []));
    }

    /**
     * Scope root folders.
     */
    public function scopeRoots($query)
    {
        return $query->whereNull('parent_id');
    }

    /**
     * Scope public folders.
     */
    public function scopePublic($query)
    {
        return $query->where('is_public', true);
    }

    /**
     * Scope by user.
     */
    public function scopeByUser($query, User $user)
    {
        return $query->where('user_id', $user->id);
    }

    /**
     * Scope accessible by user.
     */
    public function scopeAccessibleBy($query, User $user)
    {
        return $query->where(function ($query) use ($user) {
            $query->where('user_id', $user->id)
                  ->orWhere('is_public', true)
                  ->orWhereJsonContains('permissions->users', $user->id);
        });
    }

    /**
     * Boot the model.
     */
    protected static function boot(): void
    {
        parent::boot();

        static::deleting(function ($folder) {
            // Move children to parent or root
            $folder->children()->update(['parent_id' => $folder->parent_id]);
            
            // Move media to parent or root
            $folder->media()->update(['folder_id' => $folder->parent_id]);
        });
    }
}