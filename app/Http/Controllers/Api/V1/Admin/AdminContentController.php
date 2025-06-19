<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Api\V1\BaseController;
use App\Models\Content\Post;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use App\Models\System\Activity;
use Illuminate\Support\Facades\Auth;

class AdminContentController extends BaseController
{
    /**
     * Get content statistics for admin dashboard
     */
    public function getStatistics(): JsonResponse
    {
        $stats = [
            'published' => Post::where('status', 'published')->count(),
            'pending' => Post::where('status', 'pending')->count(),
            'drafts' => Post::where('status', 'draft')->count(),
            'archived' => Post::where('status', 'archived')->count(),
            'deleted' => Post::onlyTrashed()->count(),
        ];

        return $this->sendResponse($stats, 'Content statistics retrieved successfully');
    }

    /**
     * Get content list with filters
     */
    public function index(Request $request): JsonResponse
    {
        $query = Post::with(['user.profile', 'contentType', 'categories', 'tags'])
            ->withCount(['likes', 'comments', 'reports']);

        // Apply search filter
        if ($request->has('search')) {
            $search = $request->get('search');
            $query->where(function($q) use ($search) {
                $q->where('content', 'like', "%{$search}%")
                  ->orWhereHas('user', function($q) use ($search) {
                      $q->where('name', 'like', "%{$search}%");
                  });
            });
        }

        // Apply content type filter
        if ($request->has('type') && $request->get('type') !== 'all') {
            $query->whereHas('contentType', function($q) use ($request) {
                $q->where('slug', $request->get('type'));
            });
        }

        // Apply status filter
        if ($request->has('status') && $request->get('status') !== 'all') {
            $query->where('status', $request->get('status'));
        }

        // Apply sorting
        $sort = $request->get('sort', 'latest');
        switch ($sort) {
            case 'oldest':
                $query->orderBy('created_at', 'asc');
                break;
            case 'most_liked':
                $query->orderBy('likes_count', 'desc');
                break;
            case 'most_commented':
                $query->orderBy('comments_count', 'desc');
                break;
            case 'most_reported':
                $query->orderBy('reports_count', 'desc');
                break;
            case 'latest':
            default:
                $query->orderBy('created_at', 'desc');
                break;
        }

        $perPage = $request->get('per_page', 15);
        $posts = $query->paginate($perPage);

        // Transform the data for the frontend
        $posts->getCollection()->transform(function ($post) {
            return [
                'id' => $post->id,
                'content' => $post->content,
                'author' => $post->user->name,
                'type' => $post->contentType->name,
                'status' => $post->status,
                'likes' => $post->likes_count,
                'comments' => $post->comments_count,
                'reports' => $post->reports_count,
                'date' => $post->created_at->format('Y-m-d H:i'),
                'is_pinned' => $post->is_pinned,
                'engagement_score' => $post->likes_count + ($post->comments_count * 2),
            ];
        });

        return $this->sendResponse($posts, 'Content list retrieved successfully');
    }

    /**
     * Toggle pin status of a post
     */
    public function togglePin(int $id): JsonResponse
    {
        $post = Post::findOrFail($id);
        $post->is_pinned = !$post->is_pinned;
        $post->save();

        return $this->sendResponse([
            'is_pinned' => $post->is_pinned
        ], 'Post pin status updated successfully');
    }

    /**
     * Get content types for filter dropdown
     */
    public function getContentTypes(): JsonResponse
    {
        $types = DB::table('content_types')
            ->select('id', 'name', 'slug')
            ->orderBy('name')
            ->get();

        return $this->sendResponse($types, 'Content types retrieved successfully');
    }

    /**
     * Get post details for admin view
     */
    public function show(int $id): JsonResponse
    {
        $post = Post::with([
            'user.profile',
            'contentType',
            'categories',
            'tags',
            'comments.user.profile',
            'reports.user'
        ])
        ->withCount(['likes', 'comments', 'reports'])
        ->findOrFail($id);

        return $this->sendResponse($post, 'Post details retrieved successfully');
    }

    /**
     * Update post status.
     */
    public function updateStatus(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'status' => 'required|in:published,draft,pending,archived,deleted'
        ]);

        $post = Post::findOrFail($id);
        $post->status = $validated['status'];

        if($validated['status'] === 'deleted'){
            $post->deleted_at = now();
        }
        
        // If status is published and no published_at date, set it
        if ($validated['status'] === 'published' && !$post->published_at) {
            $post->published_at = now();
        }
        
        $post->save();

        // Log the status change
        Activity::logUpdated($post, [
            'status_changed_by' => Auth::id(),
            'old_status' => $post->getOriginal('status'),
            'new_status' => $validated['status']
        ]);

        return $this->sendResponse($post, 'Post status updated successfully');
    }

    /**
     * Get reported posts
     */
    public function getReportedPosts(Request $request): JsonResponse
    {
        $query = Post::with(['user.profile', 'contentType'])
            ->withCount(['likes', 'comments', 'reports'])
            ->whereHas('reports')
            ->orderBy('reports_count', 'desc');

        $perPage = $request->get('per_page', 15);
        $posts = $query->paginate($perPage);

        return $this->sendResponse($posts, 'Reported posts retrieved successfully');
    }
} 