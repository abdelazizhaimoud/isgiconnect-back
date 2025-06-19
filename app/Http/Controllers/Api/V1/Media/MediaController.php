<?php

namespace App\Http\Controllers\Api\V1\Media;

use App\Http\Controllers\Api\ApiController;
use App\Models\Media\Media;
use App\Models\Media\MediaFolder;
use App\Models\System\Activity;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class MediaController extends ApiController
{
    /**
     * Get media files with filtering and pagination.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Media::with(['folder', 'user.profile']);

        // Apply filters
        if ($request->has('type')) {
            $query->byType($request->type);
        }

        if ($request->has('folder_id')) {
            if ($request->folder_id === 'null' || $request->folder_id === null) {
                $query->whereNull('folder_id');
            } else {
                $query->where('folder_id', $request->folder_id);
            }
        }

        if ($request->has('public_only') && $request->boolean('public_only')) {
            $query->public();
        }

        if ($request->has('search')) {
            $query->where('name', 'LIKE', '%' . $request->search . '%');
        }

        if ($request->has('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        // Apply sorting
        $sortBy = $request->get('sort_by', 'created_at');
        $sortDir = $request->get('sort_dir', 'desc');
        $query->orderBy($sortBy, $sortDir);

        $media = $query->paginate($request->get('per_page', 20));

        $media->getCollection()->transform(function ($item) {
            return [
                'id' => $item->id,
                'name' => $item->name,
                'file_name' => $item->file_name,
                'mime_type' => $item->mime_type,
                'size' => $item->size,
                'human_readable_size' => $item->human_readable_size,
                'type' => $item->type,
                'url' => $item->getUrl(),
                'alt_text' => $item->alt_text,
                'caption' => $item->caption,
                'description' => $item->description,
                'download_count' => $item->download_count,
                'is_public' => $item->is_public,
                'created_at' => $item->created_at,
                'dimensions' => $item->getDimensions(),
                'folder' => $item->folder ? [
                    'id' => $item->folder->id,
                    'name' => $item->folder->name,
                ] : null,
                'uploader' => [
                    'id' => $item->user->id,
                    'name' => $item->user->name,
                    'avatar_url' => $item->user->profile?->avatar_url,
                ],
            ];
        });

        return $this->successResponse($media, 'Media files retrieved successfully');
    }

    /**
     * Get media by ID.
     */
    public function show(Media $media): JsonResponse
    {
        $media->load(['folder', 'user.profile']);

        $mediaData = [
            'id' => $media->id,
            'name' => $media->name,
            'file_name' => $media->file_name,
            'mime_type' => $media->mime_type,
            'extension' => $media->extension,
            'size' => $media->size,
            'human_readable_size' => $media->human_readable_size,
            'type' => $media->type,
            'url' => $media->getUrl(),
            'path' => $media->path,
            'alt_text' => $media->alt_text,
            'caption' => $media->caption,
            'description' => $media->description,
            'metadata' => $media->metadata,
            'conversions' => $media->conversions,
            'download_count' => $media->download_count,
            'is_public' => $media->is_public,
            'hash' => $media->hash,
            'created_at' => $media->created_at,
            'updated_at' => $media->updated_at,
            'dimensions' => $media->getDimensions(),
            'folder' => $media->folder ? [
                'id' => $media->folder->id,
                'name' => $media->folder->name,
                'path' => $media->folder->path,
            ] : null,
            'uploader' => [
                'id' => $media->user->id,
                'name' => $media->user->name,
                'avatar_url' => $media->user->profile?->avatar_url,
            ],
        ];

        return $this->successResponse($mediaData, 'Media file retrieved successfully');
    }

    /**
     * Update media metadata.
     */
    public function update(Request $request, Media $media): JsonResponse
    {
        // Check permissions (owner or admin)
        if (!auth()->check() || 
            ($media->user_id !== auth()->id() && !auth()->user()->hasRole('admin'))) {
            return $this->errorResponse('Unauthorized to update this media', 403);
        }

        $request->validate([
            'name' => 'sometimes|string|max:255',
            'alt_text' => 'nullable|string|max:255',
            'caption' => 'nullable|string|max:500',
            'description' => 'nullable|string|max:1000',
            'is_public' => 'boolean',
        ]);

        $originalData = $media->toArray();

        $media->update($request->only([
            'name', 'alt_text', 'caption', 'description', 'is_public'
        ]));

        // Log activity
        $changes = array_diff_assoc($media->fresh()->toArray(), $originalData);
        Activity::logUpdated($media, $changes);

        return $this->successResponse($media, 'Media updated successfully');
    }

    /**
     * Delete media file.
     */
    public function destroy(Media $media): JsonResponse
    {
        // Check permissions (owner or admin)
        if (!auth()->check() || 
            ($media->user_id !== auth()->id() && !auth()->user()->hasRole('admin'))) {
            return $this->errorResponse('Unauthorized to delete this media', 403);
        }

        // Log activity before deletion
        Activity::logDeleted($media, [
            'name' => $media->name,
            'type' => $media->type,
            'size' => $media->size,
        ]);

        $media->delete(); // This will also delete the file via model observer

        return $this->successResponse(null, 'Media deleted successfully');
    }

    /**
     * Download media file.
     */
    public function download(Media $media): mixed
    {
        if (!$media->is_public && !auth()->check()) {
            return $this->errorResponse('Authentication required', 401);
        }

        if (!Storage::disk($media->disk)->exists($media->path)) {
            return $this->errorResponse('File not found', 404);
        }

        // Increment download count
        $media->incrementDownloads();

        // Log download activity
        if (auth()->check()) {
            Activity::log('media_downloaded', $media, [
                'downloader_id' => auth()->id(),
                'ip_address' => request()->ip(),
            ]);
        }

        return Storage::disk($media->disk)->download($media->path, $media->name);
    }

    /**
     * Get media statistics.
     */
    public function statistics(): JsonResponse
    {
        $stats = [
            'total_files' => Media::count(),
            'total_size' => Media::sum('size'),
            'by_type' => [
                'images' => Media::images()->count(),
                'videos' => Media::videos()->count(),
                'documents' => Media::where('mime_type', 'LIKE', 'application/%')->count(),
                'others' => Media::whereNotIn('mime_type', ['image/%', 'video/%', 'application/%'])->count(),
            ],
            'size_by_type' => [
                'images' => Media::images()->sum('size'),
                'videos' => Media::videos()->sum('size'),
                'documents' => Media::where('mime_type', 'LIKE', 'application/%')->sum('size'),
            ],
            'recent_uploads' => [
                'today' => Media::whereDate('created_at', today())->count(),
                'this_week' => Media::where('created_at', '>=', now()->startOfWeek())->count(),
                'this_month' => Media::where('created_at', '>=', now()->startOfMonth())->count(),
            ],
            'top_uploaders' => Media::select('user_id')
                                  ->with('user:id,name')
                                  ->groupBy('user_id')
                                  ->selectRaw('user_id, count(*) as upload_count')
                                  ->orderByDesc('upload_count')
                                  ->limit(5)
                                  ->get()
                                  ->map(function ($item) {
                                      return [
                                          'user' => $item->user->name,
                                          'upload_count' => $item->upload_count,
                                      ];
                                  }),
        ];

        return $this->successResponse($stats, 'Media statistics retrieved successfully');
    }

    /**
     * Search media files.
     */
    public function search(Request $request): JsonResponse
    {
        $request->validate([
            'q' => 'required|string|min:2',
        ]);

        $query = Media::with(['folder', 'user.profile'])
                     ->where(function ($q) use ($request) {
                         $q->where('name', 'LIKE', '%' . $request->q . '%')
                           ->orWhere('caption', 'LIKE', '%' . $request->q . '%')
                           ->orWhere('description', 'LIKE', '%' . $request->q . '%');
                     });

        // Apply filters
        if ($request->has('type')) {
            $query->byType($request->type);
        }

        if ($request->has('public_only') && $request->boolean('public_only')) {
            $query->public();
        }

        $results = $query->orderByDesc('created_at')
                        ->paginate($request->get('per_page', 20));

        $results->getCollection()->transform(function ($item) {
            return [
                'id' => $item->id,
                'name' => $item->name,
                'type' => $item->type,
                'url' => $item->getUrl(),
                'size' => $item->human_readable_size,
                'created_at' => $item->created_at,
                'uploader' => $item->user->name,
            ];
        });

        return $this->successResponse($results, 'Search results retrieved successfully');
    }

    /**
     * Bulk operations on media files.
     */
    public function bulkAction(Request $request): JsonResponse
    {
        $request->validate([
            'media_ids' => 'required|array',
            'media_ids.*' => 'exists:media,id',
            'action' => 'required|in:delete,make_public,make_private,move_to_folder',
            'folder_id' => 'required_if:action,move_to_folder|exists:media_folders,id',
        ]);

        $mediaIds = $request->media_ids;
        $action = $request->action;
        $affectedCount = 0;

        $mediaFiles = Media::whereIn('id', $mediaIds)->get();

        foreach ($mediaFiles as $media) {
            // Check permissions for each file
            if ($media->user_id !== auth()->id() && !auth()->user()->hasRole('admin')) {
                continue;
            }

            switch ($action) {
                case 'delete':
                    Activity::logDeleted($media, ['bulk_deleted' => true]);
                    $media->delete();
                    $affectedCount++;
                    break;

                case 'make_public':
                    $media->update(['is_public' => true]);
                    $affectedCount++;
                    break;

                case 'make_private':
                    $media->update(['is_public' => false]);
                    $affectedCount++;
                    break;

                case 'move_to_folder':
                    $media->update(['folder_id' => $request->folder_id]);
                    $affectedCount++;
                    break;
            }
        }

        // Log bulk activity
        Activity::log('media_bulk_action', null, [
            'action' => $action,
            'affected_count' => $affectedCount,
            'media_ids' => $mediaIds,
        ]);

        return $this->successResponse([
            'affected_count' => $affectedCount,
        ], "Bulk {$action} completed successfully");
    }
}