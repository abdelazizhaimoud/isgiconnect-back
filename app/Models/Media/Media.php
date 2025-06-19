<?php

namespace App\Models\Media;

use App\Models\User\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Media extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'folder_id',
        'user_id',
        'mediable_type',
        'mediable_id',
        'name',
        'file_name',
        'mime_type',
        'extension',
        'size',
        'disk',
        'path',
        'url',
        'alt_text',
        'caption',
        'description',
        'metadata',
        'conversions',
        'download_count',
        'is_public',
        'hash',
        'collection_name',
        'order_column',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'metadata' => 'array',
        'conversions' => 'array',
        'is_public' => 'boolean',
    ];

    /**
     * Get the folder that owns the media.
     */
    public function folder()
    {
        return $this->belongsTo(MediaFolder::class, 'folder_id');
    }

    /**
     * Get the user that uploaded the media.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the mediable model.
     */
    public function mediable()
    {
        return $this->morphTo();
    }

    /**
     * Get the media URL.
     */
    public function getUrl(string $conversion = ''): string
    {
        if ($conversion && isset($this->conversions[$conversion])) {
            return asset('storage/' . $this->conversions[$conversion]['path']);
        }

        return $this->url ?: asset('storage/' . $this->path);
    }

    /**
     * Get media file size in human readable format.
     */
    public function getHumanReadableSizeAttribute(): string
    {
        $bytes = $this->size;
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, 2) . ' ' . $units[$i];
    }

    /**
     * Check if media is an image.
     */
    public function isImage(): bool
    {
        return str_starts_with($this->mime_type, 'image/');
    }

    /**
     * Check if media is a video.
     */
    public function isVideo(): bool
    {
        return str_starts_with($this->mime_type, 'video/');
    }

    /**
     * Check if media is an audio file.
     */
    public function isAudio(): bool
    {
        return str_starts_with($this->mime_type, 'audio/');
    }

    /**
     * Check if media is a document.
     */
    public function isDocument(): bool
    {
        return in_array($this->mime_type, [
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'text/plain',
        ]);
    }

    /**
     * Get media type.
     */
    public function getTypeAttribute(): string
    {
        if ($this->isImage()) return 'image';
        if ($this->isVideo()) return 'video';
        if ($this->isAudio()) return 'audio';
        if ($this->isDocument()) return 'document';
        return 'file';
    }

    /**
     * Get image dimensions.
     */
    public function getDimensions(): array
    {
        if (!$this->isImage()) {
            return ['width' => null, 'height' => null];
        }

        return [
            'width' => $this->metadata['width'] ?? null,
            'height' => $this->metadata['height'] ?? null,
        ];
    }

    /**
     * Increment download count.
     */
    public function incrementDownloads(): void
    {
        $this->increment('download_count');
    }

    /**
     * Delete media file.
     */
    public function deleteFile(): bool
    {
        if (Storage::disk($this->disk)->exists($this->path)) {
            Storage::disk($this->disk)->delete($this->path);
        }

        // Delete conversions
        if ($this->conversions) {
            foreach ($this->conversions as $conversion) {
                if (isset($conversion['path']) && Storage::disk($this->disk)->exists($conversion['path'])) {
                    Storage::disk($this->disk)->delete($conversion['path']);
                }
            }
        }

        return true;
    }

    /**
     * Scope by collection.
     */
    public function scopeInCollection($query, string $collection)
    {
        return $query->where('collection_name', $collection);
    }

    /**
     * Scope by type.
     */
    public function scopeByType($query, string $type)
    {
        return $query->where('mime_type', 'LIKE', $type . '/%');
    }

    /**
     * Scope images only.
     */
    public function scopeImages($query)
    {
        return $query->where('mime_type', 'LIKE', 'image/%');
    }

    /**
     * Scope videos only.
     */
    public function scopeVideos($query)
    {
        return $query->where('mime_type', 'LIKE', 'video/%');
    }

    /**
     * Scope public media.
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
     * Boot the model.
     */
    protected static function boot(): void
    {
        parent::boot();

        static::deleting(function ($media) {
            $media->deleteFile();
        });
    }
}