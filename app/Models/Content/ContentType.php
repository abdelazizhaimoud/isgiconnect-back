<?php

namespace App\Models\Content;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ContentType extends Model
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
        'icon',
        'fields_schema',
        'settings',
        'is_active',
        'is_hierarchical',
        'supports_comments',
        'supports_media',
        'supports_tags',
        'sort_order',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'fields_schema' => 'array',
        'settings' => 'array',
        'is_active' => 'boolean',
        'is_hierarchical' => 'boolean',
        'supports_comments' => 'boolean',
        'supports_media' => 'boolean',
        'supports_tags' => 'boolean',
    ];



    /**
     * Get the categories for the content type.
     */
    public function categories()
    {
        return $this->hasMany(Category::class);
    }

    /**
     * Scope only active content types.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope content types ordered by sort order.
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order');
    }

    /**
     * Get the posts for this content type.
     */
    public function posts()
    {
        return $this->hasMany(Post::class);
    }
}