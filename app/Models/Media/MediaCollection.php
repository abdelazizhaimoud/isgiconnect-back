<?php

namespace App\Models\Media;

use App\Models\User\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MediaCollection extends Model
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
        'settings',
        'disk',
        'path_generator',
        'conversions',
        'is_public',
        'user_id',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'settings' => 'array',
        'conversions' => 'array',
        'is_public' => 'boolean',
    ];

    /**
     * Get the user that owns the collection.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the media in this collection.
     */
    public function media()
    {
        return $this->hasMany(Media::class, 'collection_name', 'name');
    }

    /**
     * Get collection settings.
     */
    public function getSetting(string $key, $default = null)
    {
        return $this->settings[$key] ?? $default;
    }

    /**
     * Set collection setting.
     */
    public function setSetting(string $key, $value): void
    {
        $settings = $this->settings ?? [];
        $settings[$key] = $value;
        $this->update(['settings' => $settings]);
    }

    /**
     * Get conversion settings.
     */
    public function getConversion(string $name): ?array
    {
        return $this->conversions[$name] ?? null;
    }

    /**
     * Add conversion.
     */
    public function addConversion(string $name, array $settings): void
    {
        $conversions = $this->conversions ?? [];
        $conversions[$name] = $settings;
        $this->update(['conversions' => $conversions]);
    }

    /**
     * Remove conversion.
     */
    public function removeConversion(string $name): void
    {
        $conversions = $this->conversions ?? [];
        unset($conversions[$name]);
        $this->update(['conversions' => $conversions]);
    }

    /**
     * Check if accepts file type.
     */
    public function acceptsFileType(string $mimeType): bool
    {
        $acceptedTypes = $this->getSetting('accepted_mime_types', []);
        
        if (empty($acceptedTypes)) {
            return true;
        }

        foreach ($acceptedTypes as $type) {
            if (fnmatch($type, $mimeType)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if file size is within limits.
     */
    public function acceptsFileSize(int $size): bool
    {
        $maxSize = $this->getSetting('max_file_size');
        
        if (!$maxSize) {
            return true;
        }

        return $size <= $maxSize;
    }

    /**
     * Get max file size in human readable format.
     */
    public function getMaxFileSizeAttribute(): ?string
    {
        $maxSize = $this->getSetting('max_file_size');
        
        if (!$maxSize) {
            return null;
        }

        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = $maxSize;

        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, 2) . ' ' . $units[$i];
    }

    /**
     * Scope public collections.
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
     * Get default media collection.
     */
    public static function getDefault(): self
    {
        return static::firstOrCreate(
            ['name' => 'default'],
            [
                'slug' => 'default',
                'description' => 'Default media collection',
                'is_public' => true,
                'settings' => [
                    'accepted_mime_types' => ['image/*', 'video/*', 'audio/*', 'application/pdf'],
                    'max_file_size' => 10485760, // 10MB
                ],
                'conversions' => [
                    'thumb' => [
                        'width' => 150,
                        'height' => 150,
                        'quality' => 90,
                    ],
                    'medium' => [
                        'width' => 500,
                        'height' => 500,
                        'quality' => 90,
                    ],
                ],
            ]
        );
    }
}