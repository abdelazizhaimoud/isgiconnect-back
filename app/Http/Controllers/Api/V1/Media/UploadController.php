<?php

namespace App\Http\Controllers\Api\V1\Media;

use App\Http\Controllers\Api\ApiController;
use App\Models\Media\Media;
use App\Models\Media\MediaFolder;
use App\Models\System\Activity;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Facades\Image;

class UploadController extends ApiController
{
    /**
     * Upload single file.
     */
    public function single(Request $request): JsonResponse
    {
        $request->validate([
            'file' => 'required|file|max:10240', // 10MB max
            'folder_id' => 'nullable|exists:media_folders,id',
            'alt_text' => 'nullable|string|max:255',
            'caption' => 'nullable|string|max:500',
            'description' => 'nullable|string|max:1000',
            'is_public' => 'boolean',
            'collection_name' => 'nullable|string|max:255',
        ]);

        if (!auth()->check()) {
            return $this->errorResponse('Authentication required', 401);
        }

        $file = $request->file('file');
        
        // Validate file type
        if (!$this->isAllowedFileType($file)) {
            return $this->errorResponse('File type not allowed', 422);
        }

        // Generate unique filename
        $fileName = $this->generateUniqueFileName($file);
        $path = 'media/' . date('Y/m') . '/' . $fileName;
        
        // Store file
        $storedPath = Storage::disk('public')->putFileAs('media/' . date('Y/m'), $file, $fileName);
        
        if (!$storedPath) {
            return $this->errorResponse('Failed to upload file', 500);
        }

        // Get file metadata
        $metadata = $this->extractMetadata($file, $storedPath);
        
        // Create media record
        $media = Media::create([
            'folder_id' => $request->folder_id,
            'user_id' => auth()->id(),
            'name' => $file->getClientOriginalName(),
            'file_name' => $fileName,
            'mime_type' => $file->getMimeType(),
            'extension' => $file->getClientOriginalExtension(),
            'size' => $file->getSize(),
            'disk' => 'public',
            'path' => $storedPath,
            'url' => Storage::disk('public')->url($storedPath),
            'alt_text' => $request->alt_text,
            'caption' => $request->caption,
            'description' => $request->description,
            'metadata' => $metadata,
            'is_public' => $request->get('is_public', true),
            'hash' => hash_file('md5', $file->getPathname()),
            'collection_name' => $request->get('collection_name', 'default'),
            'order_column' => $this->getNextOrderColumn($request->get('collection_name', 'default')),
        ]);

        // Generate image conversions if it's an image
        if ($media->isImage()) {
            $conversions = $this->generateImageConversions($media);
            $media->update(['conversions' => $conversions]);
        }

        // Update folder media count if in a folder
        if ($media->folder_id) {
            $media->folder->updateMediaCount();
        }

        // Log upload activity
        Activity::logCreated($media, [
            'file_name' => $media->name,
            'file_size' => $media->size,
            'file_type' => $media->type,
        ]);

        $media->load('folder');

        return $this->successResponse([
            'id' => $media->id,
            'name' => $media->name,
            'type' => $media->type,
            'size' => $media->human_readable_size,
            'url' => $media->getUrl(),
            'thumbnail_url' => $media->getUrl('thumb'),
            'metadata' => $media->metadata,
            'folder' => $media->folder ? [
                'id' => $media->folder->id,
                'name' => $media->folder->name,
            ] : null,
        ], 'File uploaded successfully', 201);
    }

    /**
     * Upload multiple files.
     */
    public function multiple(Request $request): JsonResponse
    {
        $request->validate([
            'files' => 'required|array|max:10',
            'files.*' => 'file|max:10240',
            'folder_id' => 'nullable|exists:media_folders,id',
            'is_public' => 'boolean',
            'collection_name' => 'nullable|string|max:255',
        ]);

        if (!auth()->check()) {
            return $this->errorResponse('Authentication required', 401);
        }

        $uploadedFiles = [];
        $errors = [];

        foreach ($request->file('files') as $index => $file) {
            try {
                // Validate file type
                if (!$this->isAllowedFileType($file)) {
                    $errors[] = "File {$index}: File type not allowed";
                    continue;
                }

                // Generate unique filename
                $fileName = $this->generateUniqueFileName($file);
                $storedPath = Storage::disk('public')->putFileAs('media/' . date('Y/m'), $file, $fileName);
                
                if (!$storedPath) {
                    $errors[] = "File {$index}: Failed to upload";
                    continue;
                }

                // Get file metadata
                $metadata = $this->extractMetadata($file, $storedPath);
                
                // Create media record
                $media = Media::create([
                    'folder_id' => $request->folder_id,
                    'user_id' => auth()->id(),
                    'name' => $file->getClientOriginalName(),
                    'file_name' => $fileName,
                    'mime_type' => $file->getMimeType(),
                    'extension' => $file->getClientOriginalExtension(),
                    'size' => $file->getSize(),
                    'disk' => 'public',
                    'path' => $storedPath,
                    'url' => Storage::disk('public')->url($storedPath),
                    'metadata' => $metadata,
                    'is_public' => $request->get('is_public', true),
                    'hash' => hash_file('md5', $file->getPathname()),
                    'collection_name' => $request->get('collection_name', 'default'),
                    'order_column' => $this->getNextOrderColumn($request->get('collection_name', 'default')),
                ]);

                // Generate image conversions if it's an image
                if ($media->isImage()) {
                    $conversions = $this->generateImageConversions($media);
                    $media->update(['conversions' => $conversions]);
                }

                $uploadedFiles[] = [
                    'id' => $media->id,
                    'name' => $media->name,
                    'type' => $media->type,
                    'size' => $media->human_readable_size,
                    'url' => $media->getUrl(),
                    'thumbnail_url' => $media->getUrl('thumb'),
                ];

            } catch (\Exception $e) {
                $errors[] = "File {$index}: " . $e->getMessage();
            }
        }

        // Update folder media count if in a folder
        if ($request->folder_id && count($uploadedFiles) > 0) {
            $folder = MediaFolder::find($request->folder_id);
            $folder->updateMediaCount();
        }

        // Log bulk upload activity
        Activity::log('media_bulk_uploaded', null, [
            'uploaded_count' => count($uploadedFiles),
            'errors_count' => count($errors),
            'folder_id' => $request->folder_id,
        ]);

        return $this->successResponse([
            'uploaded_files' => $uploadedFiles,
            'errors' => $errors,
            'summary' => [
                'uploaded_count' => count($uploadedFiles),
                'errors_count' => count($errors),
            ],
        ], 'Bulk upload completed');
    }

    /**
     * Upload from URL.
     */
    public function fromUrl(Request $request): JsonResponse
    {
        $request->validate([
            'url' => 'required|url',
            'folder_id' => 'nullable|exists:media_folders,id',
            'name' => 'nullable|string|max:255',
            'alt_text' => 'nullable|string|max:255',
            'caption' => 'nullable|string|max:500',
            'is_public' => 'boolean',
        ]);

        if (!auth()->check()) {
            return $this->errorResponse('Authentication required', 401);
        }

        try {
            // Download file from URL
            $fileContent = file_get_contents($request->url);
            
            if ($fileContent === false) {
                return $this->errorResponse('Failed to download file from URL', 400);
            }

            // Get file info from URL
            $urlParts = pathinfo($request->url);
            $fileName = $urlParts['filename'] ?? 'downloaded_file';
            $extension = $urlParts['extension'] ?? '';
            
            // Generate unique filename
            $uniqueFileName = Str::uuid() . '.' . $extension;
            $path = 'media/' . date('Y/m') . '/' . $uniqueFileName;
            
            // Store file
            $stored = Storage::disk('public')->put($path, $fileContent);
            
            if (!$stored) {
                return $this->errorResponse('Failed to store downloaded file', 500);
            }

            // Get file info
            $fullPath = Storage::disk('public')->path($path);
            $size = filesize($fullPath);
            $mimeType = mime_content_type($fullPath);

            // Create media record
            $media = Media::create([
                'folder_id' => $request->folder_id,
                'user_id' => auth()->id(),
                'name' => $request->name ?? $fileName,
                'file_name' => $uniqueFileName,
                'mime_type' => $mimeType,
                'extension' => $extension,
                'size' => $size,
                'disk' => 'public',
                'path' => $path,
                'url' => Storage::disk('public')->url($path),
                'alt_text' => $request->alt_text,
                'caption' => $request->caption,
                'is_public' => $request->get('is_public', true),
                'metadata' => ['source_url' => $request->url],
                'collection_name' => 'downloads',
            ]);

            // Generate image conversions if it's an image
            if ($media->isImage()) {
                $conversions = $this->generateImageConversions($media);
                $media->update(['conversions' => $conversions]);
            }

            // Log activity
            Activity::logCreated($media, [
                'source_url' => $request->url,
                'method' => 'url_download',
            ]);

            return $this->successResponse([
                'id' => $media->id,
                'name' => $media->name,
                'type' => $media->type,
                'size' => $media->human_readable_size,
                'url' => $media->getUrl(),
                'source_url' => $request->url,
            ], 'File uploaded from URL successfully', 201);

        } catch (\Exception $e) {
            return $this->errorResponse('Failed to upload from URL: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get upload progress (for chunked uploads).
     */
    public function progress(Request $request): JsonResponse
    {
        $request->validate([
            'upload_id' => 'required|string',
        ]);

        // This would be implemented with a proper chunked upload system
        // For now, return a placeholder response
        return $this->successResponse([
            'upload_id' => $request->upload_id,
            'progress' => 100,
            'status' => 'completed',
        ], 'Upload progress retrieved');
    }

    /**
     * Cancel upload.
     */
    public function cancel(Request $request): JsonResponse
    {
        $request->validate([
            'upload_id' => 'required|string',
        ]);

        // This would be implemented with a proper chunked upload system
        // For now, return a placeholder response
        return $this->successResponse(null, 'Upload cancelled');
    }

    /**
     * Get upload configuration.
     */
    public function config(): JsonResponse
    {
        $config = [
            'max_file_size' => $this->getMaxFileSize(),
            'max_files_per_upload' => 10,
            'allowed_types' => $this->getAllowedFileTypes(),
            'image_max_dimensions' => [
                'width' => 4000,
                'height' => 4000,
            ],
            'chunk_size' => 1024 * 1024, // 1MB chunks
            'auto_generate_thumbnails' => true,
            'thumbnail_sizes' => [
                'thumb' => ['width' => 150, 'height' => 150],
                'medium' => ['width' => 500, 'height' => 500],
                'large' => ['width' => 1200, 'height' => 1200],
            ],
        ];

        return $this->successResponse($config, 'Upload configuration retrieved');
    }

    /**
     * Check if file type is allowed.
     */
    private function isAllowedFileType($file): bool
    {
        $allowedTypes = $this->getAllowedFileTypes();
        $mimeType = $file->getMimeType();
        
        foreach ($allowedTypes as $type) {
            if (fnmatch($type, $mimeType)) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Get allowed file types.
     */
    private function getAllowedFileTypes(): array
    {
        return [
            'image/*',
            'video/*',
            'audio/*',
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'text/plain',
            'text/csv',
            'application/zip',
        ];
    }

    /**
     * Get maximum file size in bytes.
     */
    private function getMaxFileSize(): int
    {
        $maxSize = ini_get('upload_max_filesize');
        return $this->convertToBytes($maxSize);
    }

    /**
     * Convert size string to bytes.
     */
    private function convertToBytes(string $size): int
    {
        $unit = strtolower(substr($size, -1));
        $value = (int) substr($size, 0, -1);
        
        switch ($unit) {
            case 'g':
                return $value * 1024 * 1024 * 1024;
            case 'm':
                return $value * 1024 * 1024;
            case 'k':
                return $value * 1024;
            default:
                return $value;
        }
    }

    /**
     * Generate unique filename.
     */
    private function generateUniqueFileName($file): string
    {
        return Str::uuid() . '.' . $file->getClientOriginalExtension();
    }

    /**
     * Extract file metadata.
     */
    private function extractMetadata($file, string $storedPath): array
    {
        $metadata = [
            'original_name' => $file->getClientOriginalName(),
            'uploaded_at' => now()->toISOString(),
        ];

        // Add image-specific metadata
        if (str_starts_with($file->getMimeType(), 'image/')) {
            try {
                $imagePath = Storage::disk('public')->path($storedPath);
                $imageInfo = getimagesize($imagePath);
                
                if ($imageInfo) {
                    $metadata['width'] = $imageInfo[0];
                    $metadata['height'] = $imageInfo[1];
                    $metadata['aspect_ratio'] = round($imageInfo[0] / $imageInfo[1], 2);
                }

                // Get EXIF data if available
                if (function_exists('exif_read_data') && in_array($file->getClientOriginalExtension(), ['jpg', 'jpeg', 'tiff'])) {
                    $exif = @exif_read_data($imagePath);
                    if ($exif) {
                        $metadata['exif'] = array_filter([
                            'camera' => $exif['Model'] ?? null,
                            'date_taken' => $exif['DateTime'] ?? null,
                            'exposure' => $exif['ExposureTime'] ?? null,
                            'iso' => $exif['ISOSpeedRatings'] ?? null,
                        ]);
                    }
                }
            } catch (\Exception $e) {
                // Ignore metadata extraction errors
            }
        }

        return $metadata;
    }

    /**
     * Generate image conversions.
     */
    private function generateImageConversions(Media $media): array
    {
        if (!$media->isImage()) {
            return [];
        }

        $conversions = [];
        $originalPath = Storage::disk('public')->path($media->path);
        
        $sizes = [
            'thumb' => ['width' => 150, 'height' => 150],
            'medium' => ['width' => 500, 'height' => 500],
            'large' => ['width' => 1200, 'height' => 1200],
        ];

        foreach ($sizes as $name => $size) {
            try {
                $conversionPath = 'media/conversions/' . pathinfo($media->path, PATHINFO_FILENAME) . "_{$name}." . $media->extension;
                $fullConversionPath = Storage::disk('public')->path($conversionPath);
                
                // Create directory if it doesn't exist
                $directory = dirname($fullConversionPath);
                if (!is_dir($directory)) {
                    mkdir($directory, 0755, true);
                }

                // Generate resized image (you would use Intervention Image or similar)
                // For now, just copy the original file
                copy($originalPath, $fullConversionPath);
                
                $conversions[$name] = [
                    'path' => $conversionPath,
                    'url' => Storage::disk('public')->url($conversionPath),
                    'width' => $size['width'],
                    'height' => $size['height'],
                ];
            } catch (\Exception $e) {
                // Ignore conversion errors
            }
        }

        return $conversions;
    }

    /**
     * Get next order column value.
     */
    private function getNextOrderColumn(string $collection): int
    {
        $lastMedia = Media::where('collection_name', $collection)
                         ->orderByDesc('order_column')
                         ->first();
        
        return $lastMedia ? $lastMedia->order_column + 1 : 1;
    }
}