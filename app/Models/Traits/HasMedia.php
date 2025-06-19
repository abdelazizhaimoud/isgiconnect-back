<?php

namespace App\Models\Traits;

use App\Models\Media\Media;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Http\UploadedFile;

trait HasMedia
{
    /**
     * Get all media for the model.
     */
    public function media(): MorphMany
    {
        return $this->morphMany(Media::class, 'mediable');
    }

    /**
     * Get media by collection.
     */
    public function getMedia(string $collection = 'default')
    {
        return $this->media()
                    ->where('collection_name', $collection)
                    ->orderBy('order_column')
                    ->get();
    }

    /**
     * Get first media from collection.
     */
    public function getFirstMedia(string $collection = 'default'): ?Media
    {
        return $this->getMedia($collection)->first();
    }

    /**
     * Get first media URL from collection.
     */
    public function getFirstMediaUrl(string $collection = 'default', string $conversion = ''): string
    {
        $media = $this->getFirstMedia($collection);
        
        if (!$media) {
            return '';
        }

        return $media->getUrl($conversion);
    }

    /**
     * Add media from file.
     */
    public function addMedia(UploadedFile $file, string $collection = 'default'): Media
    {
        $fileName = $this->generateUniqueFileName($file);
        $path = $file->storeAs('media', $fileName, 'public');
        
        return $this->media()->create([
            'name' => $file->getClientOriginalName(),
            'file_name' => $fileName,
            'mime_type' => $file->getMimeType(),
            'extension' => $file->getClientOriginalExtension(),
            'size' => $file->getSize(),
            'disk' => 'public',
            'path' => $path,
            'url' => asset('storage/' . $path),
            'collection_name' => $collection,
            'user_id' => auth()->id(),
            'order_column' => $this->getNextOrderColumn($collection),
        ]);
    }

    /**
     * Add media from URL.
     */
    public function addMediaFromUrl(string $url, string $collection = 'default'): Media
    {
        $fileName = basename($url);
        $pathInfo = pathinfo($fileName);
        
        return $this->media()->create([
            'name' => $fileName,
            'file_name' => $fileName,
            'mime_type' => 'image/jpeg', // Default, should be detected
            'extension' => $pathInfo['extension'] ?? '',
            'size' => 0, // Should be fetched
            'disk' => 'public',
            'path' => $url,
            'url' => $url,
            'collection_name' => $collection,
            'user_id' => auth()->id(),
            'order_column' => $this->getNextOrderColumn($collection),
        ]);
    }

    /**
     * Clear media collection.
     */
    public function clearMediaCollection(string $collection = 'default'): void
    {
        $this->getMedia($collection)->each->delete();
    }

    /**
     * Check if model has media in collection.
     */
    public function hasMedia(string $collection = 'default'): bool
    {
        return $this->getMedia($collection)->isNotEmpty();
    }

    /**
     * Generate unique file name.
     */
    protected function generateUniqueFileName(UploadedFile $file): string
    {
        return uniqid() . '.' . $file->getClientOriginalExtension();
    }

    /**
     * Get next order column value.
     */
    protected function getNextOrderColumn(string $collection): int
    {
        $lastMedia = $this->media()
                          ->where('collection_name', $collection)
                          ->orderByDesc('order_column')
                          ->first();
        
        return $lastMedia ? $lastMedia->order_column + 1 : 1;
    }

    /**
     * Get media collections.
     */
    public function getMediaCollections(): array
    {
        return $this->media()
                    ->distinct()
                    ->pluck('collection_name')
                    ->toArray();
    }
}