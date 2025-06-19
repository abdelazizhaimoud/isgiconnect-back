<?php

namespace App\Models\Traits;

use Illuminate\Support\Str;

trait Sluggable
{
    /**
     * Boot the sluggable trait.
     */
    protected static function bootSluggable(): void
    {
        static::creating(function ($model) {
            if (empty($model->slug)) {
                $model->slug = $model->generateSlug();
            }
        });

        static::updating(function ($model) {
            if ($model->isDirty($model->getSlugSource()) && empty($model->slug)) {
                $model->slug = $model->generateSlug();
            }
        });
    }

    /**
     * Get the source field for the slug.
     */
    protected function getSlugSource(): string
    {
        return property_exists($this, 'slugSource') ? $this->slugSource : 'title';
    }

    /**
     * Get the slug field name.
     */
    protected function getSlugField(): string
    {
        return property_exists($this, 'slugField') ? $this->slugField : 'slug';
    }

    /**
     * Generate a unique slug.
     */
    protected function generateSlug(): string
    {
        $source = $this->getSlugSource();
        $field = $this->getSlugField();
        
        $slug = Str::slug($this->{$source});
        $originalSlug = $slug;
        $counter = 1;

        while ($this->slugExists($slug)) {
            $slug = $originalSlug . '-' . $counter;
            $counter++;
        }

        return $slug;
    }

    /**
     * Check if slug exists.
     */
    protected function slugExists(string $slug): bool
    {
        $field = $this->getSlugField();
        
        $query = static::where($field, $slug);
        
        if ($this->exists) {
            $query->where($this->getKeyName(), '!=', $this->getKey());
        }

        return $query->exists();
    }

    /**
     * Find by slug.
     */
    public static function findBySlug(string $slug)
    {
        $instance = new static;
        $field = $instance->getSlugField();
        
        return static::where($field, $slug)->first();
    }

    /**
     * Find by slug or fail.
     */
    public static function findBySlugOrFail(string $slug)
    {
        $instance = new static;
        $field = $instance->getSlugField();
        
        return static::where($field, $slug)->firstOrFail();
    }

    /**
     * Scope to find by slug.
     */
    public function scopeWhereSlug($query, string $slug)
    {
        $field = $this->getSlugField();
        return $query->where($field, $slug);
    }
}