<?php

namespace App\Models\Content;

use App\Models\Traits\Sluggable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Tag extends Model
{
    use HasFactory, Sluggable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'slug',
        'description',
        'color',
        'meta_data',
        'usage_count',
        'is_active',
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
     * Get the contents for the tag.
     */
    public function contents()
    {
        return $this->belongsToMany(Content::class)
                    ->withTimestamps();
    }

    /**
     * Scope only active tags.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope tags by usage count.
     */
    public function scopePopular($query, int $limit = 10)
    {
        return $query->orderByDesc('usage_count')->limit($limit);
    }

    /**
     * Increment usage count.
     */
    public function incrementUsage(): void
    {
        $this->increment('usage_count');
    }

    /**
     * Decrement usage count.
     */
    public function decrementUsage(): void
    {
        $this->decrement('usage_count');
    }
}