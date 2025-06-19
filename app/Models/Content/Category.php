<?php

namespace App\Models\Content;

use App\Models\Traits\Sluggable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    use HasFactory, Sluggable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'parent_id',
        'content_type_id',
        'name',
        'slug',
        'description',
        'image',
        'color',
        'icon',
        'meta_data',
        'is_active',
        'sort_order',
        'content_count',
        'seo_title',
        'seo_description',
        'lft',
        'rgt',
        'depth',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'meta_data' => 'array',
        'is_active' => 'boolean',
    ];

    /**
     * Get the content type that owns the category.
     */
    public function contentType()
    {
        return $this->belongsTo(ContentType::class);
    }

    /**
     * Get the parent category.
     */
    public function parent()
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    /**
     * Get the children categories.
     */
    public function children()
    {
        return $this->hasMany(Category::class, 'parent_id');
    }

    /**
     * Get the contents for the category.
     */
    public function contents()
    {
        return $this->belongsToMany(Content::class, 'content_category')
                    ->withPivot('is_primary')
                    ->withTimestamps();
    }

    /**
     * Scope only active categories.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope categories ordered by sort order.
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order');
    }

    /**
     * Scope root categories (no parent).
     */
    public function scopeRoots($query)
    {
        return $query->whereNull('parent_id');
    }

    /**
     * Get all descendants.
     */
    public function descendants()
    {
        return static::where('lft', '>', $this->lft)
                    ->where('rgt', '<', $this->rgt);
    }

    /**
     * Get all ancestors.
     */
    public function ancestors()
    {
        return static::where('lft', '<', $this->lft)
                    ->where('rgt', '>', $this->rgt);
    }

    /**
     * Check if category has children.
     */
    public function hasChildren(): bool
    {
        return $this->children()->exists();
    }

    /**
     * Get the image URL.
     */
    public function getImageUrlAttribute(): ?string
    {
        if ($this->image) {
            return asset('storage/' . $this->image);
        }
        
        return null;
    }
}