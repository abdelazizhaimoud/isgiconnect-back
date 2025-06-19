<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\BaseController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use App\Models\User\User;
use App\Models\Content\Post;
use App\Models\System\Activity;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\JsonResponse;
use App\Models\Content\Like;
use App\Models\Content\Comment;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Illuminate\Support\Facades\Auth;
use Spatie\Activitylog\Models\Activity as ActivityLog;

class AdminDashboardController extends BaseController 
{
    /**
     * Return statistics for the admin dashboard (tableau de bord)
     */
    public function getStatistics(Request $request)
    {
        $today = Carbon::today();
        $lastWeek = Carbon::today()->subDays(7);
        $previousWeek = Carbon::today()->subDays(14);

        // Users
        $totalUsers = User::count();
        $activeUsers = User::where('status', 'active')->count();
        $newUsersToday = User::whereDate('created_at', $today)->count();

        // Posts
        $totalPosts = Post::count();
        $postsToday = Post::whereDate('created_at', $today)->count();

        // Reports (using 'action' column in Activity model)
        $totalReports = Activity::where('action', 'report')->count();

        // User growth: % growth in new users this week vs previous week
        $usersLastWeek = User::whereBetween('created_at', [$lastWeek, $today])->count();
        $usersPreviousWeek = User::whereBetween('created_at', [$previousWeek, $lastWeek])->count();
        $userGrowth = $usersPreviousWeek > 0 ? (($usersLastWeek - $usersPreviousWeek) / $usersPreviousWeek) * 100 : 0;

        // Engagement rate: active users / total users * 100
        $engagementRate = $totalUsers > 0 ? ($activeUsers / $totalUsers) * 100 : 0;

        return response()->json([
            'totalUsers' => $totalUsers,
            'activeUsers' => $activeUsers,
            'totalPosts' => $totalPosts,
            'totalReports' => $totalReports,
            'newUsersToday' => $newUsersToday,
            'postsToday' => $postsToday,
            'userGrowth' => round($userGrowth, 2),
            'engagementRate' => round($engagementRate, 2),
        ]);
    }
/**
     * Get all posts for content management with filters and pagination
     */
    public function getPosts(Request $request): JsonResponse
    {
        $request->validate([
            'per_page' => 'integer|min:1|max:100',
            'page' => 'integer|min:1',
            'status' => 'string|in:published,draft,pending,archived',
            'content_type_id' => 'integer|exists:content_types,id',
            'user_id' => 'integer|exists:users,id',
            'search' => 'string|max:255',
            'sort_by' => 'string|in:created_at,updated_at,published_at,like_count,comment_count,view_count',
            'sort_order' => 'string|in:asc,desc',
            'date_from' => 'date',
            'date_to' => 'date',
            'is_featured' => 'boolean',
            'is_sticky' => 'boolean',
        ]);

        $perPage = $request->get('per_page', 20);
        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');

        $query = Post::with([
            'user:id,name,email',
            'user.profile:user_id,avatar,first_name,last_name',
            'contentType:id,name,slug',
            'categories:id,name,slug',
            'tags:id,name,slug',
            'comments' => function($q) {
                $q->select('id', 'post_id')->whereNull('parent_id');
            }
        ])->select([
            'id', 'content_type_id', 'user_id', 'parent_id', 'title', 'excerpt', 
            'content', 'status', 'featured_image', 'is_featured', 'is_sticky', 
            'allow_comments', 'view_count', 'like_count', 'comment_count', 
            'published_at', 'created_at', 'updated_at', 'images'
        ]);

        // Apply filters
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('content_type_id')) {
            $query->where('content_type_id', $request->content_type_id);
        }

        if ($request->has('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        if ($request->has('is_featured')) {
            $query->where('is_featured', $request->boolean('is_featured'));
        }

        if ($request->has('is_sticky')) {
            $query->where('is_sticky', $request->boolean('is_sticky'));
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('content', 'like', "%{$search}%")
                  ->orWhere('excerpt', 'like', "%{$search}%");
            });
        }

        if ($request->has('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->has('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        // Apply sorting
        $query->orderBy($sortBy, $sortOrder);

        $posts = $query->paginate($perPage);

        // Transform the data
        $posts->getCollection()->transform(function ($post) {
            return [
                'id' => $post->id,
                'title' => $post->title ?? substr($post->content, 0, 50) . '...',
                'excerpt' => $post->excerpt,
                'content' => $post->content,
                'status' => $post->status,
                'featured_image' => $post->featured_image,
                'featured_image_url' => $post->featured_image ? asset('storage/' . $post->featured_image) : null,
                'images' => $post->images ?? [],
                'is_featured' => $post->is_featured,
                'is_sticky' => $post->is_sticky,
                'allow_comments' => $post->allow_comments,
                'view_count' => $post->view_count ?? 0,
                'like_count' => $post->like_count ?? 0,
                'comment_count' => $post->comment_count ?? 0,
                'published_at' => $post->published_at,
                'created_at' => $post->created_at,
                'updated_at' => $post->updated_at,
                'author' => [
                    'id' => $post->user->id,
                    'name' => $post->user->name,
                    'email' => $post->user->email,
                    'avatar' => $post->user->profile?->avatar,
                    'full_name' => trim(($post->user->profile?->first_name ?? '') . ' ' . ($post->user->profile?->last_name ?? ''))
                ],
                'content_type' => $post->contentType ? [
                    'id' => $post->contentType->id,
                    'name' => $post->contentType->name,
                    'slug' => $post->contentType->slug,
                ] : null,
                'categories' => $post->categories->map(function ($category) {
                    return [
                        'id' => $category->id,
                        'name' => $category->name,
                        'slug' => $category->slug,
                    ];
                }),
                'tags' => $post->tags->map(function ($tag) {
                    return [
                        'id' => $tag->id,
                        'name' => $tag->name,
                        'slug' => $tag->slug,
                    ];
                }),
                'comments_count' => $post->comments->count(),
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $posts->items(),
            'pagination' => [
                'current_page' => $posts->currentPage(),
                'last_page' => $posts->lastPage(),
                'per_page' => $posts->perPage(),
                'total' => $posts->total(),
                'from' => $posts->firstItem(),
                'to' => $posts->lastItem(),
                'has_next_page' => $posts->hasMorePages(),
                'has_prev_page' => $posts->currentPage() > 1,
            ],
            'filters' => [
                'status' => $request->status,
                'content_type_id' => $request->content_type_id,
                'user_id' => $request->user_id,
                'search' => $request->search,
                'is_featured' => $request->is_featured,
                'is_sticky' => $request->is_sticky,
            ]
        ]);
    }

    /**
     * Get a single post by ID for detailed view
     */
    public function getPost($id): JsonResponse
    {
        $post = Post::with([
            'user:id,name,email',
            'user.profile:user_id,avatar,first_name,last_name',
            'contentType:id,name,slug',
            'categories:id,name,slug',
            'tags:id,name,slug',
            'comments.user:id,name',
            'comments.user.profile:user_id,avatar,first_name,last_name',
            'likes.user:id,name'
        ])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $post->id,
                'title' => $post->title,
                'excerpt' => $post->excerpt,
                'content' => $post->content,
                'status' => $post->status,
                'featured_image' => $post->featured_image,
                'featured_image_url' => $post->featured_image_url,
                'images' => $post->images ?? [],
                'is_featured' => $post->is_featured,
                'is_sticky' => $post->is_sticky,
                'allow_comments' => $post->allow_comments,
                'view_count' => $post->view_count ?? 0,
                'like_count' => $post->like_count ?? 0,
                'comment_count' => $post->comment_count ?? 0,
                'meta_data' => $post->meta_data,
                'custom_fields' => $post->custom_fields,
                'seo_title' => $post->seo_title,
                'seo_description' => $post->seo_description,
                'seo_keywords' => $post->seo_keywords,
                'published_at' => $post->published_at,
                'created_at' => $post->created_at,
                'updated_at' => $post->updated_at,
                'author' => [
                    'id' => $post->user->id,
                    'name' => $post->user->name,
                    'email' => $post->user->email,
                    'avatar' => $post->user->profile?->avatar,
                ],
                'content_type' => $post->contentType,
                'categories' => $post->categories,
                'tags' => $post->tags,
                'comments' => $post->comments->map(function ($comment) {
                    return [
                        'id' => $comment->id,
                        'content' => $comment->content,
                        'created_at' => $comment->created_at,
                        'user' => [
                            'id' => $comment->user->id,
                            'name' => $comment->user->name,
                            'avatar' => $comment->user->profile?->avatar,
                        ]
                    ];
                }),
                'likes' => $post->likes->map(function ($like) {
                    return [
                        'id' => $like->id,
                        'user_name' => $like->user->name,
                        'created_at' => $like->created_at,
                    ];
                })
            ]
        ]);
    }

    /**
     * Update post status (publish, draft, archive, etc.)
     */
    public function updatePostStatus(Request $request, $id): JsonResponse
    {
        $request->validate([
            'status' => 'required|in:published,draft,pending,archived',
            'reason' => 'sometimes|string|max:500'
        ]);

        $post = Post::findOrFail($id);
        $oldStatus = $post->status;
        
        $post->status = $request->status;
        
        // If publishing, set published_at if not already set
        if ($request->status === 'published' && !$post->published_at) {
            $post->published_at = now();
        }
        
        $post->save();

        // Log the status change
        Activity::log('post_status_changed', $post, [
            'admin_id' => auth()->id(),
            'old_status' => $oldStatus,
            'new_status' => $request->status,
            'reason' => $request->reason ?? null,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Post status updated successfully',
            'data' => [
                'id' => $post->id,
                'status' => $post->status,
                'published_at' => $post->published_at,
            ]
        ]);
    }

    /**
     * Feature/unfeature a post
     */
    public function togglePostFeature(Request $request, $id): JsonResponse
    {
        $post = Post::findOrFail($id);
        $post->is_featured = !$post->is_featured;
        $post->save();

        // Log the feature toggle
        Activity::log('post_featured_toggled', $post, [
            'admin_id' => auth()->id(),
            'is_featured' => $post->is_featured,
        ]);

        return response()->json([
            'success' => true,
            'message' => $post->is_featured ? 'Post featured successfully' : 'Post unfeatured successfully',
            'data' => [
                'id' => $post->id,
                'is_featured' => $post->is_featured,
            ]
        ]);
    }

    /**
     * Make post sticky/unsticky
     */
    public function togglePostSticky(Request $request, $id): JsonResponse
    {
        $post = Post::findOrFail($id);
        $post->is_sticky = !$post->is_sticky;
        $post->save();

        // Log the sticky toggle
        Activity::log('post_sticky_toggled', $post, [
            'admin_id' => auth()->id(),
            'is_sticky' => $post->is_sticky,
        ]);

        return response()->json([
            'success' => true,
            'message' => $post->is_sticky ? 'Post made sticky successfully' : 'Post unstickied successfully',
            'data' => [
                'id' => $post->id,
                'is_sticky' => $post->is_sticky,
            ]
        ]);
    }

    /**
     * Toggle comments on/off for a post
     */
    public function togglePostComments(Request $request, $id): JsonResponse
    {
        $post = Post::findOrFail($id);
        $post->allow_comments = !$post->allow_comments;
        $post->save();

        // Log the comments toggle
        Activity::log('post_comments_toggled', $post, [
            'admin_id' => auth()->id(),
            'allow_comments' => $post->allow_comments,
        ]);

        return response()->json([
            'success' => true,
            'message' => $post->allow_comments ? 'Comments enabled for post' : 'Comments disabled for post',
            'data' => [
                'id' => $post->id,
                'allow_comments' => $post->allow_comments,
            ]
        ]);
    }

    /**
     * Delete a post (admin action)
     */
    public function deletePost(Request $request, $id): JsonResponse
    {
        $request->validate([
            'reason' => 'sometimes|string|max:500'
        ]);

        $post = Post::findOrFail($id);

        // Store post data for logging before deletion
        $postData = [
            'id' => $post->id,
            'title' => $post->title,
            'author_id' => $post->user_id,
            'content_preview' => substr($post->content, 0, 100),
            'status' => $post->status,
        ];

        // Delete associated images from storage
        if ($post->images) {
            foreach ($post->images as $image) {
                if (isset($image['url'])) {
                    $path = str_replace(asset('storage/'), '', $image['url']);
                    if (Storage::disk('public')->exists($path)) {
                        Storage::disk('public')->delete($path);
                    }
                }
            }
        }

        // Delete featured image if exists
        if ($post->featured_image && Storage::disk('public')->exists($post->featured_image)) {
            Storage::disk('public')->delete($post->featured_image);
        }

        // Delete the post (this will also delete related likes, comments via cascade)
        $post->delete();

        // Log the deletion
        Activity::log('post_deleted_by_admin', null, [
            'admin_id' => auth()->id(),
            'post_data' => $postData,
            'reason' => $request->reason ?? null,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Post deleted successfully'
        ]);
    }

    /**
     * Bulk actions on posts
     */
    public function bulkPostActions(Request $request): JsonResponse
    {
        $request->validate([
            'action' => 'required|in:delete,publish,draft,archive,feature,unfeature,sticky,unsticky',
            'post_ids' => 'required|array|min:1',
            'post_ids.*' => 'integer|exists:posts,id',
            'reason' => 'sometimes|string|max:500'
        ]);

        $postIds = $request->post_ids;
        $action = $request->action;
        $reason = $request->reason;

        $posts = Post::whereIn('id', $postIds)->get();
        $updatedCount = 0;

        foreach ($posts as $post) {
            switch ($action) {
                case 'delete':
                    // Delete images
                    if ($post->images) {
                        foreach ($post->images as $image) {
                            if (isset($image['url'])) {
                                $path = str_replace(asset('storage/'), '', $image['url']);
                                if (Storage::disk('public')->exists($path)) {
                                    Storage::disk('public')->delete($path);
                                }
                            }
                        }
                    }
                    $post->delete();
                    break;

                case 'publish':
                    $post->status = 'published';
                    if (!$post->published_at) {
                        $post->published_at = now();
                    }
                    $post->save();
                    break;

                case 'draft':
                    $post->status = 'draft';
                    $post->save();
                    break;

                case 'archive':
                    $post->status = 'archived';
                    $post->save();
                    break;

                case 'feature':
                    $post->is_featured = true;
                    $post->save();
                    break;

                case 'unfeature':
                    $post->is_featured = false;
                    $post->save();
                    break;

                case 'sticky':
                    $post->is_sticky = true;
                    $post->save();
                    break;

                case 'unsticky':
                    $post->is_sticky = false;
                    $post->save();
                    break;
            }
            
            $updatedCount++;
        }

        // Log bulk action
        Activity::log('bulk_post_action', null, [
            'admin_id' => auth()->id(),
            'action' => $action,
            'post_ids' => $postIds,
            'updated_count' => $updatedCount,
            'reason' => $reason,
        ]);

        return response()->json([
            'success' => true,
            'message' => "Bulk action '{$action}' applied to {$updatedCount} posts successfully",
            'data' => [
                'action' => $action,
                'updated_count' => $updatedCount,
                'post_ids' => $postIds,
            ]
        ]);
    }

    /**
     * Get content statistics for admin dashboard
     */
    public function getContentStatistics(): JsonResponse
    {
        $today = Carbon::today();
        $lastWeek = Carbon::today()->subDays(7);
        $lastMonth = Carbon::today()->subMonth();

        $stats = [
            'posts' => [
                'total' => Post::count(),
                'published' => Post::where('status', 'published')->count(),
                'draft' => Post::where('status', 'draft')->count(),
                'pending' => Post::where('status', 'pending')->count(),
                'archived' => Post::where('status', 'archived')->count(),
                'featured' => Post::where('is_featured', true)->count(),
                'today' => Post::whereDate('created_at', $today)->count(),
                'this_week' => Post::where('created_at', '>=', $lastWeek)->count(),
                'this_month' => Post::where('created_at', '>=', $lastMonth)->count(),
            ],
            'engagement' => [
                'total_likes' => Like::count(),
                'total_comments' => Comment::count(),
                'avg_likes_per_post' => round(Post::avg('like_count') ?? 0, 2),
                'avg_comments_per_post' => round(Post::avg('comment_count') ?? 0, 2),
                'avg_views_per_post' => round(Post::avg('view_count') ?? 0, 2),
            ],
            'trending' => [
                'most_liked_today' => Post::whereDate('created_at', $today)
                    ->orderBy('like_count', 'desc')
                    ->limit(5)
                    ->get(['id', 'title', 'like_count', 'user_id']),
                'most_viewed_week' => Post::where('created_at', '>=', $lastWeek)
                    ->orderBy('view_count', 'desc')
                    ->limit(5)
                    ->get(['id', 'title', 'view_count', 'user_id']),
            ]
        ];

        return response()->json([
            'success' => true,
            'data' => $stats
        ]);
    }

    /**
     * Get recent activities related to posts
     */
    public function getRecentActivities(Request $request): JsonResponse
    {
        $perPage = $request->get('per_page', 20);
        
        $activities = Activity::with(['user:id,name,email'])
            ->whereIn('action', [
                'post_created', 'post_updated', 'post_deleted', 'post_status_changed',
                'post_featured_toggled', 'post_sticky_toggled', 'post_comments_toggled',
                'post_liked', 'post_unliked', 'bulk_post_action'
            ])
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $activities->items(),
            'pagination' => [
                'current_page' => $activities->currentPage(),
                'last_page' => $activities->lastPage(),
                'per_page' => $activities->perPage(),
                'total' => $activities->total(),
            ]
        ]);
    }

    /**
     * Get all users with pagination and filters
     */
    public function getUsers(Request $request): JsonResponse
    {
        Log::info($request);
        $validated = $request->validate([
            'search' => 'nullable|string|max:255',
            'status' => 'nullable|in:active,suspended,banned,pending,inactive',
            'sort_by' => 'nullable|in:name,email,created_at',
            'sort_order' => 'nullable|in:asc,desc',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $query = User::where('role', '!=', 'admin')
            ->withCount('posts')
            ->select('id', 'name', 'email', 'status', 'created_at');

        // Apply search filter if provided
        if (!empty($validated['search'])) {
            $searchTerm = $validated['search'];
            $query->where(function($q) use ($searchTerm) {
                $q->where('name', 'like', "%{$searchTerm}%")
                  ->orWhere('email', 'like', "%{$searchTerm}%");
            });
        }

        // Apply status filter if provided and not 'all'
        if (!empty($validated['status']) && $validated['status'] !== 'all') {
            $query->where('status', $validated['status']);
        }

        // Apply sorting
        $sortBy = $validated['sort_by'] ?? 'created_at';
        $sortOrder = $validated['sort_order'] ?? 'desc';
        $query->orderBy($sortBy, $sortOrder);

        // Get paginated results
        $perPage = $validated['per_page'] ?? 15;
        $users = $query->paginate($perPage);

        // Transform the results
        $users->getCollection()->transform(function ($user) {
            return [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'status' => $user->status,
                'created_at' => $user->created_at,
                'posts_count' => $user->posts_count,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $users->items(),
            'meta' => [
                'current_page' => $users->currentPage(),
                'last_page' => $users->lastPage(),
                'per_page' => $users->perPage(),
                'total' => $users->total(),
            ]
        ]);
    }

    /**
     * Update user status
     */
    public function updateUserStatus(Request $request, $id): JsonResponse
    {
        Log::info($request);
        $request->validate([
            'status' => 'required|string|in:active,suspended,banned,pending,inactive'
        ]);

        $user = User::findOrFail($id);
        $oldStatus = $user->status;
        $user->status = $request->status;
        $user->save();

        // Log the activity
        Activity::log('user_status_updated', $user, [
            'old_status' => $oldStatus,
            'new_status' => $request->status,
            'updated_by' => auth()->id()
        ]);

        return response()->json([
            'success' => true,
            'message' => 'User status updated successfully',
            'data' => [
                'id' => $user->id,
                'status' => $user->status
            ]
        ]);
    }

    /**
     * Delete a user (soft delete)
     */
    public function deleteUser($id): JsonResponse
    {
        try {
            $user = User::findOrFail($id);
            
            // Prevent deleting the last admin
            if ($user->role === 'admin' && User::where('role', 'admin')->count() <= 1) {
                return $this->sendError('Cannot delete the last admin user', [], 400);
            }

            // Store user data for logging
            $userData = [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'status' => $user->status
            ];

            // Log the deletion activity
            Activity::create([
                'causer_type' => User::class,
                'causer_id' => Auth::id(),
                'subject_type' => User::class,
                'subject_id' => $user->id,
                'description' => 'user_deleted',
                'action' => 'user_deleted',
                'properties' => [
                    'admin_id' => Auth::id(),
                    'user_data' => $userData,
                    'reason' => request('reason')
                ],
                'created_at' => now(),
                'updated_at' => now()
            ]);

            // Perform soft delete
            $user->delete();

            return $this->sendResponse(null, 'User deleted successfully');
        } catch (ModelNotFoundException $e) {
            return $this->sendError('User not found', [], 404);
        } catch (\Exception $e) {
            return $this->sendError('Error deleting user: ' . $e->getMessage(), [], 500);
        }
    }
}
