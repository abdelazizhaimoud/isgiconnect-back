<?php

namespace App\Models\Content;

use App\Models\User\User;
use App\Models\Media\Media;
use App\Models\System\Activity;
use App\Models\Traits\Searchable;
use App\Models\Traits\HasMedia;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Post extends Model
{
    use HasFactory, Searchable, HasMedia, SoftDeletes;

    /**
     * Get the likes for the post.
     */
    public function likes()
    {
        return $this->hasMany(Like::class);
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'content_type_id',
        'user_id',
        'parent_id',
        'title',
        'excerpt',
        'content',
        'meta_data',
        'custom_fields',
        'status',
        'featured_image',
        'is_featured',
        'is_sticky',
        'allow_comments',
        'view_count',
        'like_count',
        'comment_count',
        'sort_order',
        'seo_title',
        'seo_description',
        'seo_keywords',
        'published_at',
        'images',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'meta_data' => 'array',
        'custom_fields' => 'array',
        'is_featured' => 'boolean',
        'is_sticky' => 'boolean',
        'allow_comments' => 'boolean',
        'seo_keywords' => 'array',
        'published_at' => 'datetime',
        'images' => 'array',
    ];

    /**
     * Get the content type that owns the content.
     */
    public function contentType()
    {
        return $this->belongsTo(ContentType::class);
    }

    /**
     * Get the user that owns the content.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the parent content.
     */
    public function parent()
    {
        return $this->belongsTo(Post::class, 'parent_id');
    }

    /**
     * Get the children content.
     */
    public function children()
    {
        return $this->hasMany(Post::class, 'parent_id');
    }

    /**
     * Get the categories for the content.
     */
    public function categories()
    {
        return $this->belongsToMany(Category::class, 'content_category', 'post_id', 'category_id')
                    ->withPivot('is_primary')
                    ->withTimestamps();
    }

    /**
     * Get the tags for the content.
     */
    public function tags()
    {
        return $this->belongsToMany(Tag::class, 'content_tag')
                    ->withTimestamps();
    }

    /**
     * Get the comments for the content.
     */
    public function comments()
    {
        return $this->morphMany(Comment::class, 'commentable');
    }

    /**
     * Get the media attachments.
     */
    public function media()
    {
        return $this->morphMany(Media::class, 'mediable');
    }

    /**
     * Get the reports for this post.
     */
    public function reports()
    {
        return $this->morphMany(Activity::class, 'subject')
                    ->where('action', 'report');
    }

    /**
     * Scope only published content.
     */
    public function scopePublished($query)
    {
        return $query->where('status', 'published')
                    ->where('published_at', '<=', now());
    }

    /**
     * Scope only featured content.
     */
    public function scopeFeatured($query)
    {
        return $query->where('is_featured', true);
    }

    /**
     * Scope content by status.
     */
    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Check if content is published.
     */
    public function isPublished(): bool
    {
        return $this->status === 'published' && 
               $this->published_at && 
               $this->published_at->isPast();
    }

    /**
     * Get the primary category.
     */
    public function getPrimaryCategoryAttribute()
    {
        return $this->categories()->wherePivot('is_primary', true)->first();
    }

    /**
     * Get the featured image URL.
     */
    public function getFeaturedImageUrlAttribute(): ?string
    {
        if ($this->featured_image) {
            return asset('storage/' . $this->featured_image);
        }
        
        return null;
    }

    /**
     * Get the excerpt or generate from content.
     */
    public function getExcerptAttribute($value): string
    {
        if ($value) {
            return $value;
        }

        return str($this->content)->limit(160);
    }
}