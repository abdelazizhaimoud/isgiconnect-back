<?php

namespace App\Http\Controllers\Api\V1\Media;

use App\Http\Controllers\Api\ApiController;
use App\Models\Media\Media;
use App\Models\Media\MediaFolder;
use App\Models\System\Activity;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GalleryController extends ApiController
{
    /**
     * Get gallery view of media with folders.
     */
    public function index(Request $request): JsonResponse
    {
        $folderId = $request->get('folder_id');
        
        // Get folders
        $foldersQuery = MediaFolder::with('user');
        
        if ($folderId) {
            $foldersQuery->where('parent_id', $folderId);
        } else {
            $foldersQuery->roots();
        }

        // Filter by user access if not admin
        if (!auth()->user()?->hasRole('admin')) {
            $foldersQuery->where(function ($query) {
                $query->where('is_public', true)
                      ->orWhere('user_id', auth()->id());
            });
        }

        $folders = $foldersQuery->orderBy('sort_order')->get();

        // Get media files
        $mediaQuery = Media::with(['user.profile']);
        
        if ($folderId) {
            $mediaQuery->where('folder_id', $folderId);
        } else {
            $mediaQuery->whereNull('folder_id');
        }

        // Apply filters
        if ($request->has('type')) {
            $mediaQuery->byType($request->type);
        }

        if (!auth()->user()?->hasRole('admin')) {
            $mediaQuery->where(function ($query) {
                $query->where('is_public', true)
                      ->orWhere('user_id', auth()->id());
            });
        }

        $media = $mediaQuery->orderBy('created_at', 'desc')
                           ->paginate($request->get('per_page', 24));

        // Transform folders
        $foldersData = $folders->map(function ($folder) {
            return [
                'id' => $folder->id,
                'name' => $folder->name,
                'slug' => $folder->slug,
                'description' => $folder->description,
                'media_count' => $folder->media_count,
                'is_public' => $folder->is_public,
                'created_at' => $folder->created_at,
                'owner' => [
                    'id' => $folder->user->id,
                    'name' => $folder->user->name,
                ],
                'can_access' => $folder->userCanAccess(auth()->user() ?? new \App\Models\User\User()),
            ];
        });

        // Transform media
        $media->getCollection()->transform(function ($item) {
            return [
                'id' => $item->id,
                'name' => $item->name,
                'type' => $item->type,
                'url' => $item->getUrl(),
                'thumbnail_url' => $item->getUrl('thumb'),
                'alt_text' => $item->alt_text,
                'caption' => $item->caption,
                'size' => $item->human_readable_size,
                'dimensions' => $item->getDimensions(),
                'created_at' => $item->created_at,
                'uploader' => [
                    'id' => $item->user->id,
                    'name' => $item->user->name,
                    'avatar_url' => $item->user->profile?->avatar_url,
                ],
            ];
        });

        // Get current folder info if in a folder
        $currentFolder = null;
        if ($folderId) {
            $folder = MediaFolder::find($folderId);
            if ($folder) {
                $currentFolder = [
                    'id' => $folder->id,
                    'name' => $folder->name,
                    'description' => $folder->description,
                    'breadcrumbs' => $folder->breadcrumbs,
                    'parent_id' => $folder->parent_id,
                ];
            }
        }

        return $this->successResponse([
            'current_folder' => $currentFolder,
            'folders' => $foldersData,
            'media' => $media,
        ], 'Gallery data retrieved successfully');
    }

    /**
     * Get media slideshow data.
     */
    public function slideshow(Request $request): JsonResponse
    {
        $query = Media::images();

        // Apply filters
        if ($request->has('folder_id')) {
            if ($request->folder_id === 'null') {
                $query->whereNull('folder_id');
            } else {
                $query->where('folder_id', $request->folder_id);
            }
        }

        if ($request->has('collection_name')) {
            $query->where('collection_name', $request->collection_name);
        }

        // Filter by public/private
        if (!auth()->user()?->hasRole('admin')) {
            $query->where(function ($q) {
                $q->where('is_public', true)
                  ->orWhere('user_id', auth()->id());
            });
        }

        $images = $query->orderBy('created_at', 'desc')->get();

        $slideshowData = $images->map(function ($image) {
            return [
                'id' => $image->id,
                'name' => $image->name,
                'url' => $image->getUrl(),
                'large_url' => $image->getUrl('large'),
                'thumbnail_url' => $image->getUrl('thumb'),
                'alt_text' => $image->alt_text,
                'caption' => $image->caption,
                'description' => $image->description,
                'dimensions' => $image->getDimensions(),
                'created_at' => $image->created_at,
                'uploader' => $image->user->name,
            ];
        });

        return $this->successResponse($slideshowData, 'Slideshow data retrieved successfully');
    }

    /**
     * Get folder structure tree.
     */
    public function folderTree(): JsonResponse
    {
        $query = MediaFolder::with(['children.children', 'user']);

        // Filter by user access if not admin
        if (!auth()->user()?->hasRole('admin')) {
            $query->where(function ($q) {
                $q->where('is_public', true)
                  ->orWhere('user_id', auth()->id());
            });
        }

        $rootFolders = $query->roots()->orderBy('sort_order')->get();

        $tree = $this->buildFolderTree($rootFolders);

        return $this->successResponse($tree, 'Folder tree retrieved successfully');
    }

    /**
     * Create a new folder.
     */
    public function createFolder(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'parent_id' => 'nullable|exists:media_folders,id',
            'description' => 'nullable|string|max:1000',
            'is_public' => 'boolean',
        ]);

        if (!auth()->check()) {
            return $this->errorResponse('Authentication required', 401);
        }

        $folder = MediaFolder::create([
            'parent_id' => $request->parent_id,
            'user_id' => auth()->id(),
            'name' => $request->name,
            'description' => $request->description,
            'is_public' => $request->get('is_public', false),
            'sort_order' => $this->getNextFolderSortOrder($request->parent_id),
        ]);

        // Log activity
        Activity::logCreated($folder, [
            'parent_id' => $request->parent_id,
        ]);

        return $this->successResponse([
            'id' => $folder->id,
            'name' => $folder->name,
            'slug' => $folder->slug,
            'description' => $folder->description,
            'is_public' => $folder->is_public,
            'parent_id' => $folder->parent_id,
        ], 'Folder created successfully', 201);
    }

    /**
     * Update folder.
     */
    public function updateFolder(Request $request, MediaFolder $folder): JsonResponse
    {
        // Check permissions
        if (!auth()->check() || 
            ($folder->user_id !== auth()->id() && !auth()->user()->hasRole('admin'))) {
            return $this->errorResponse('Unauthorized to update this folder', 403);
        }

        $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string|max:1000',
            'is_public' => 'boolean',
        ]);

        $originalData = $folder->toArray();

        $folder->update($request->only(['name', 'description', 'is_public']));

        // Log activity
        $changes = array_diff_assoc($folder->fresh()->toArray(), $originalData);
        Activity::logUpdated($folder, $changes);

        return $this->successResponse($folder, 'Folder updated successfully');
    }

    /**
     * Delete folder.
     */
    public function deleteFolder(MediaFolder $folder): JsonResponse
    {
        // Check permissions
        if (!auth()->check() || 
            ($folder->user_id !== auth()->id() && !auth()->user()->hasRole('admin'))) {
            return $this->errorResponse('Unauthorized to delete this folder', 403);
        }

        // Check if folder has media or subfolders
        if ($folder->media()->count() > 0 || $folder->children()->count() > 0) {
            return $this->errorResponse('Cannot delete folder that contains files or subfolders', 400);
        }

        // Log activity before deletion
        Activity::logDeleted($folder, [
            'name' => $folder->name,
            'media_count' => $folder->media_count,
        ]);

        $folder->delete();

        return $this->successResponse(null, 'Folder deleted successfully');
    }

    /**
     * Move media to folder.
     */
    public function moveMedia(Request $request): JsonResponse
    {
        $request->validate([
            'media_ids' => 'required|array',
            'media_ids.*' => 'exists:media,id',
            'folder_id' => 'nullable|exists:media_folders,id',
        ]);

        if (!auth()->check()) {
            return $this->errorResponse('Authentication required', 401);
        }

        $mediaFiles = Media::whereIn('id', $request->media_ids)->get();
        $movedCount = 0;

        foreach ($mediaFiles as $media) {
            // Check permissions
            if ($media->user_id !== auth()->id() && !auth()->user()->hasRole('admin')) {
                continue;
            }

            $oldFolderId = $media->folder_id;
            $media->update(['folder_id' => $request->folder_id]);

            // Update folder counts
            if ($oldFolderId) {
                MediaFolder::find($oldFolderId)?->updateMediaCount();
            }
            if ($request->folder_id) {
                MediaFolder::find($request->folder_id)?->updateMediaCount();
            }

            $movedCount++;
        }

        // Log activity
        Activity::log('media_moved', null, [
            'media_ids' => $request->media_ids,
            'from_folder_id' => $mediaFiles->first()?->folder_id,
            'to_folder_id' => $request->folder_id,
            'moved_count' => $movedCount,
        ]);

        return $this->successResponse([
            'moved_count' => $movedCount,
        ], "Moved {$movedCount} files successfully");
    }

    /**
     * Get folder contents.
     */
    public function folderContents(Request $request, MediaFolder $folder): JsonResponse
    {
        // Check access permissions
        if (!$folder->userCanAccess(auth()->user() ?? new \App\Models\User\User())) {
return $this->errorResponse('Access denied to this folder', 403);
       }

       // Get subfolders
       $subfolders = $folder->children()
                           ->orderBy('sort_order')
                           ->get()
                           ->map(function ($subfolder) {
                               return [
                                   'id' => $subfolder->id,
                                   'name' => $subfolder->name,
                                   'media_count' => $subfolder->media_count,
                                   'is_public' => $subfolder->is_public,
                                   'created_at' => $subfolder->created_at,
                               ];
                           });

       // Get media files in this folder
       $mediaQuery = $folder->media()->with(['user.profile']);

       // Apply filters
       if ($request->has('type')) {
           $mediaQuery->byType($request->type);
       }

       $media = $mediaQuery->orderBy('created_at', 'desc')
                          ->paginate($request->get('per_page', 24));

       $media->getCollection()->transform(function ($item) {
           return [
               'id' => $item->id,
               'name' => $item->name,
               'type' => $item->type,
               'url' => $item->getUrl(),
               'thumbnail_url' => $item->getUrl('thumb'),
               'alt_text' => $item->alt_text,
               'caption' => $item->caption,
               'size' => $item->human_readable_size,
               'dimensions' => $item->getDimensions(),
               'created_at' => $item->created_at,
               'uploader' => [
                   'id' => $item->user->id,
                   'name' => $item->user->name,
               ],
           ];
       });

       return $this->successResponse([
           'folder' => [
               'id' => $folder->id,
               'name' => $folder->name,
               'description' => $folder->description,
               'breadcrumbs' => $folder->breadcrumbs,
               'parent_id' => $folder->parent_id,
               'media_count' => $folder->media_count,
               'is_public' => $folder->is_public,
           ],
           'subfolders' => $subfolders,
           'media' => $media,
       ], 'Folder contents retrieved successfully');
   }

   /**
    * Get recent uploads.
    */
   public function recent(Request $request): JsonResponse
   {
       $days = $request->get('days', 7);
       $limit = $request->get('limit', 20);

       $query = Media::with(['user.profile', 'folder'])
                    ->where('created_at', '>=', now()->subDays($days));

       // Apply filters
       if ($request->has('type')) {
           $query->byType($request->type);
       }

       // Filter by access permissions
       if (!auth()->user()?->hasRole('admin')) {
           $query->where(function ($q) {
               $q->where('is_public', true)
                 ->orWhere('user_id', auth()->id());
           });
       }

       $recentMedia = $query->orderByDesc('created_at')
                           ->limit($limit)
                           ->get();

       $recentMedia->transform(function ($item) {
           return [
               'id' => $item->id,
               'name' => $item->name,
               'type' => $item->type,
               'url' => $item->getUrl(),
               'thumbnail_url' => $item->getUrl('thumb'),
               'size' => $item->human_readable_size,
               'created_at' => $item->created_at,
               'uploader' => [
                   'id' => $item->user->id,
                   'name' => $item->user->name,
                   'avatar_url' => $item->user->profile?->avatar_url,
               ],
               'folder' => $item->folder ? [
                   'id' => $item->folder->id,
                   'name' => $item->folder->name,
               ] : null,
           ];
       });

       return $this->successResponse($recentMedia, 'Recent uploads retrieved successfully');
   }

   /**
    * Get popular media (most downloaded/viewed).
    */
   public function popular(Request $request): JsonResponse
   {
       $limit = $request->get('limit', 20);
       $period = $request->get('period', 'all'); // all, month, week

       $query = Media::with(['user.profile']);

       // Apply period filter
       if ($period === 'month') {
           $query->where('created_at', '>=', now()->startOfMonth());
       } elseif ($period === 'week') {
           $query->where('created_at', '>=', now()->startOfWeek());
       }

       // Apply type filter
       if ($request->has('type')) {
           $query->byType($request->type);
       }

       // Filter by access permissions
       if (!auth()->user()?->hasRole('admin')) {
           $query->where(function ($q) {
               $q->where('is_public', true)
                 ->orWhere('user_id', auth()->id());
           });
       }

       $popularMedia = $query->orderByDesc('download_count')
                            ->limit($limit)
                            ->get();

       $popularMedia->transform(function ($item) {
           return [
               'id' => $item->id,
               'name' => $item->name,
               'type' => $item->type,
               'url' => $item->getUrl(),
               'thumbnail_url' => $item->getUrl('thumb'),
               'download_count' => $item->download_count,
               'size' => $item->human_readable_size,
               'created_at' => $item->created_at,
               'uploader' => [
                   'id' => $item->user->id,
                   'name' => $item->user->name,
               ],
           ];
       });

       return $this->successResponse($popularMedia, 'Popular media retrieved successfully');
   }

   /**
    * Get gallery statistics.
    */
   public function statistics(): JsonResponse
   {
       $userId = auth()->id();
       $isAdmin = auth()->user()?->hasRole('admin');

       // Base query for user's accessible media
       $baseQuery = Media::query();
       if (!$isAdmin) {
           $baseQuery->where(function ($q) use ($userId) {
               $q->where('is_public', true)
                 ->orWhere('user_id', $userId);
           });
       }

       $stats = [
           'total_files' => (clone $baseQuery)->count(),
           'total_size' => (clone $baseQuery)->sum('size'),
           'by_type' => [
               'images' => (clone $baseQuery)->images()->count(),
               'videos' => (clone $baseQuery)->videos()->count(),
               'documents' => (clone $baseQuery)->where('mime_type', 'LIKE', 'application/%')->count(),
               'others' => (clone $baseQuery)->whereNotIn('mime_type', ['image/%', 'video/%'])->count(),
           ],
           'folders' => MediaFolder::when(!$isAdmin, function ($query) use ($userId) {
               $query->where(function ($q) use ($userId) {
                   $q->where('is_public', true)
                     ->orWhere('user_id', $userId);
               });
           })->count(),
           'recent_uploads' => [
               'today' => (clone $baseQuery)->whereDate('created_at', today())->count(),
               'this_week' => (clone $baseQuery)->where('created_at', '>=', now()->startOfWeek())->count(),
               'this_month' => (clone $baseQuery)->where('created_at', '>=', now()->startOfMonth())->count(),
           ],
       ];

       if ($userId) {
           $stats['my_files'] = Media::where('user_id', $userId)->count();
           $stats['my_folders'] = MediaFolder::where('user_id', $userId)->count();
       }

       return $this->successResponse($stats, 'Gallery statistics retrieved successfully');
   }

   /**
    * Search gallery.
    */
   public function search(Request $request): JsonResponse
   {
       $request->validate([
           'q' => 'required|string|min:2',
           'type' => 'sometimes|string',
           'folder_id' => 'sometimes|exists:media_folders,id',
       ]);

       $query = Media::with(['user.profile', 'folder'])
                    ->where(function ($q) use ($request) {
                        $q->where('name', 'LIKE', '%' . $request->q . '%')
                          ->orWhere('caption', 'LIKE', '%' . $request->q . '%')
                          ->orWhere('description', 'LIKE', '%' . $request->q . '%')
                          ->orWhere('alt_text', 'LIKE', '%' . $request->q . '%');
                    });

       // Apply filters
       if ($request->has('type')) {
           $query->byType($request->type);
       }

       if ($request->has('folder_id')) {
           $query->where('folder_id', $request->folder_id);
       }

       // Filter by access permissions
       if (!auth()->user()?->hasRole('admin')) {
           $query->where(function ($q) {
               $q->where('is_public', true)
                 ->orWhere('user_id', auth()->id());
           });
       }

       $results = $query->orderByDesc('created_at')
                       ->paginate($request->get('per_page', 20));

       $results->getCollection()->transform(function ($item) {
           return [
               'id' => $item->id,
               'name' => $item->name,
               'type' => $item->type,
               'url' => $item->getUrl(),
               'thumbnail_url' => $item->getUrl('thumb'),
               'size' => $item->human_readable_size,
               'created_at' => $item->created_at,
               'uploader' => $item->user->name,
               'folder' => $item->folder?->name,
           ];
       });

       return $this->successResponse($results, 'Search results retrieved successfully');
   }

   /**
    * Build hierarchical folder tree.
    */
   private function buildFolderTree($folders): array
   {
       return $folders->map(function ($folder) {
           return [
               'id' => $folder->id,
               'name' => $folder->name,
               'slug' => $folder->slug,
               'media_count' => $folder->media_count,
               'is_public' => $folder->is_public,
               'owner' => $folder->user->name,
               'children' => $this->buildFolderTree($folder->children),
           ];
       })->toArray();
   }

   /**
    * Get next sort order for folder.
    */
   private function getNextFolderSortOrder(?int $parentId): int
   {
       $lastFolder = MediaFolder::where('parent_id', $parentId)
                               ->orderByDesc('sort_order')
                               ->first();
       
       return $lastFolder ? $lastFolder->sort_order + 1 : 1;
   }
}