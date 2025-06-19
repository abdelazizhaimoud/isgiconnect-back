<?php

namespace App\Http\Controllers\Api\V1\User;

use App\Http\Controllers\Api\ApiController;
use App\Models\User\User;
use App\Models\Content\Content;
use App\Models\System\Activity;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class UserController extends ApiController
{
    /**
     * Get user's dashboard data.
     */
    public function dashboard(Request $request): JsonResponse
    {
        $user = $request->user();

        $dashboardData = [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'avatar_url' => $user->profile?->avatar_url,
                'member_since' => $user->created_at,
            ],
            'statistics' => [
                'content_created' => $user->contents()->count(),
                'content_published' => $user->contents()->where('status', 'published')->count(),
                'total_views' => $user->contents()->sum('view_count'),
                'total_likes' => $user->contents()->sum('like_count'),
                'comments_made' => $user->comments()->count(),
                'media_uploaded' => $user->media()->count(),
            ],
            'recent_content' => $user->contents()
                ->with('contentType')
                ->latest()
                ->limit(5)
                ->get()
                ->map(function ($content) {
                    return [
                        'id' => $content->id,
                        'title' => $content->title,
                        'status' => $content->status,
                        'type' => $content->contentType->name,
                        'view_count' => $content->view_count,
                        'created_at' => $content->created_at,
                    ];
                }),
            'recent_activities' => $user->activities()
                ->latest()
                ->limit(10)
                ->get()
                ->map(function ($activity) {
                    return [
                        'action' => $activity->action,
                        'description' => $activity->description,
                        'created_at' => $activity->created_at,
                    ];
                }),
            'unread_notifications' => $user->unreadNotifications()->count(),
        ];

        return $this->successResponse($dashboardData, 'Dashboard data retrieved successfully');
    }

    /**
     * Get user's content.
     */
    public function content(Request $request): JsonResponse
    {
        $user = $request->user();
        
        $query = $user->contents()->with(['contentType', 'categories', 'tags']);

        // Apply filters
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('content_type')) {
            $query->whereHas('contentType', function ($q) use ($request) {
                $q->where('slug', $request->content_type);
            });
        }

        if ($request->has('search')) {
            $query->search($request->search);
        }

        // Apply sorting
        $sortBy = $request->get('sort_by', 'created_at');
        $sortDir = $request->get('sort_dir', 'desc');
        $query->orderBy($sortBy, $sortDir);

        $content = $query->paginate($request->get('per_page', 15));

        $content->getCollection()->transform(function ($item) {
            return [
                'id' => $item->id,
                'title' => $item->title,
                'slug' => $item->slug,
                'excerpt' => $item->excerpt,
                'status' => $item->status,
                'is_featured' => $item->is_featured,
                'view_count' => $item->view_count,
                'like_count' => $item->like_count,
                'comment_count' => $item->comment_count,
                'published_at' => $item->published_at,
                'created_at' => $item->created_at,
                'updated_at' => $item->updated_at,
                'content_type' => [
                    'id' => $item->contentType->id,
                    'name' => $item->contentType->name,
                    'slug' => $item->contentType->slug,
                ],
                'categories' => $item->categories->map(function ($category) {
                    return [
                        'id' => $category->id,
                        'name' => $category->name,
                        'slug' => $category->slug,
                    ];
                }),
                'tags' => $item->tags->pluck('name')->toArray(),
                'featured_image_url' => $item->featured_image_url,
            ];
        });

        return $this->successResponse($content, 'User content retrieved successfully');
    }

    /**
     * Create new content.
     */
    public function createContent(Request $request): JsonResponse
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'content_type_id' => 'required|exists:content_types,id',
            'slug' => 'sometimes|string|max:255|unique:contents,slug',
            'excerpt' => 'nullable|string',
            'content' => 'nullable|string',
            'meta_data' => 'nullable|array',
            'custom_fields' => 'nullable|array',
            'status' => 'sometimes|in:draft,published,pending_review',
            'featured_image' => 'nullable|string',
            'is_featured' => 'boolean',
            'allow_comments' => 'boolean',
            'seo_title' => 'nullable|string|max:255',
            'seo_description' => 'nullable|string|max:500',
            'seo_keywords' => 'nullable|array',
            'categories' => 'nullable|array',
            'categories.*' => 'exists:categories,id',
            'tags' => 'nullable|array',
        ]);

        $content = Content::create([
            'content_type_id' => $request->content_type_id,
            'user_id' => $this->requireCurrentUserId(),
            'title' => $request->title,
            'slug' => $request->slug,
            'excerpt' => $request->excerpt,
            'content' => $request->content,
            'meta_data' => $request->meta_data,
            'custom_fields' => $request->custom_fields,
            'status' => $request->get('status', 'draft'),
            'featured_image' => $request->featured_image,
            'is_featured' => $request->get('is_featured', false),
            'allow_comments' => $request->get('allow_comments', true),
            'seo_title' => $request->seo_title,
            'seo_description' => $request->seo_description,
            'seo_keywords' => $request->seo_keywords,
            'published_at' => $request->status === 'published' ? now() : null,
        ]);

        // Attach categories
        if ($request->has('categories')) {
            $content->categories()->sync($request->categories);
        }

        // Attach tags
        if ($request->has('tags')) {
            $tagIds = [];
            foreach ($request->tags as $tagName) {
                $tag = \App\Models\Content\Tag::firstOrCreate(['name' => $tagName]);
                $tag->incrementUsage();
                $tagIds[] = $tag->id;
            }
            $content->tags()->sync($tagIds);
        }

        // Log activity
        Activity::logCreated($content);

        $content->load(['contentType', 'categories', 'tags']);

        return $this->successResponse($content, 'Content created successfully', 201);
    }

    /**
     * Update user's content.
     */
    public function updateContent(Request $request, Content $content): JsonResponse
    {
        // Check if user owns this content
        if ($content->user_id !== $this->requireCurrentUserId()) {
            return $this->errorResponse('Unauthorized to update this content', 403);
        }

        $request->validate([
            'title' => 'sometimes|string|max:255',
            'slug' => 'sometimes|string|max:255|unique:contents,slug,' . $content->id,
            'excerpt' => 'nullable|string',
            'content' => 'nullable|string',
            'meta_data' => 'nullable|array',
            'custom_fields' => 'nullable|array',
            'status' => 'sometimes|in:draft,published,pending_review',
            'featured_image' => 'nullable|string',
            'is_featured' => 'boolean',
            'allow_comments' => 'boolean',
            'seo_title' => 'nullable|string|max:255',
            'seo_description' => 'nullable|string|max:500',
            'seo_keywords' => 'nullable|array',
            'categories' => 'nullable|array',
            'categories.*' => 'exists:categories,id',
            'tags' => 'nullable|array',
        ]);

        $originalData = $content->toArray();

        // Update content data
        $updateData = $request->only([
            'title', 'slug', 'excerpt', 'content', 'meta_data',
            'custom_fields', 'status', 'featured_image', 'is_featured',
            'allow_comments', 'seo_title', 'seo_description', 'seo_keywords'
        ]);

        // Handle published_at when status changes to published
        if ($request->has('status') && $request->status === 'published' && !$content->published_at) {
            $updateData['published_at'] = now();
        }

        $content->update($updateData);

        // Update categories
        if ($request->has('categories')) {
            $content->categories()->sync($request->categories);
        }

        // Update tags
        if ($request->has('tags')) {
            // Decrement usage count for old tags
            foreach ($content->tags as $oldTag) {
                $oldTag->decrementUsage();
            }

            $tagIds = [];
            foreach ($request->tags as $tagName) {
                $tag = \App\Models\Content\Tag::firstOrCreate(['name' => $tagName]);
                $tag->incrementUsage();
                $tagIds[] = $tag->id;
            }
            $content->tags()->sync($tagIds);
        }

        // Log activity
        $changes = array_diff_assoc($content->fresh()->toArray(), $originalData);
        Activity::logUpdated($content, $changes);

        $content->load(['contentType', 'categories', 'tags']);

        return $this->successResponse($content, 'Content updated successfully');
    }

    /**
     * Delete user's content.
     */
    public function deleteContent(Content $content): JsonResponse
    {
        // Check if user owns this content
        if ($content->user_id !== $this->requireCurrentUserId()) {
            return $this->errorResponse('Unauthorized to delete this content', 403);
        }

        // Decrement tag usage counts
        foreach ($content->tags as $tag) {
            $tag->decrementUsage();
        }

        // Log activity before deletion
        Activity::logDeleted($content, [
            'title' => $content->title,
            'type' => $content->contentType->name,
        ]);

        $content->delete();

        return $this->successResponse(null, 'Content deleted successfully');
    }

    /**
     * Get user's media.
     */
    public function media(Request $request): JsonResponse
    {
        $user = $request->user();
        
        $query = $user->media()->with('folder');

        // Apply filters
        if ($request->has('type')) {
            $query->byType($request->type);
        }

        if ($request->has('folder_id')) {
            $query->where('folder_id', $request->folder_id);
        }

        if ($request->has('search')) {
            $query->where('name', 'LIKE', '%' . $request->search . '%');
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
                'url' => $item->getUrl(),
                'type' => $item->type,
                'alt_text' => $item->alt_text,
                'caption' => $item->caption,
                'download_count' => $item->download_count,
                'created_at' => $item->created_at,
                'folder' => $item->folder ? [
                    'id' => $item->folder->id,
                    'name' => $item->folder->name,
                ] : null,
            ];
        });

        return $this->successResponse($media, 'User media retrieved successfully');
    }

    /**
     * Get user's comments.
     */
    public function comments(Request $request): JsonResponse
    {
        $user = $request->user();
        
        $query = $user->comments()->with(['commentable']);

        // Apply filters
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Apply sorting
        $sortBy = $request->get('sort_by', 'created_at');
        $sortDir = $request->get('sort_dir', 'desc');
        $query->orderBy($sortBy, $sortDir);

        $comments = $query->paginate($request->get('per_page', 15));

        $comments->getCollection()->transform(function ($comment) {
            return [
                'id' => $comment->id,
                'content' => $comment->content,
                'status' => $comment->status,
                'like_count' => $comment->like_count,
                'created_at' => $comment->created_at,
                'commentable' => [
                    'type' => $comment->commentable_type,
                    'id' => $comment->commentable_id,
                    'title' => $comment->commentable->title ?? 'N/A',
                ],
            ];
        });

        return $this->successResponse($comments, 'User comments retrieved successfully');
    }

    /**
     * Get user's activities.
     */
    public function activities(Request $request): JsonResponse
    {
        $user = $request->user();
        
        $query = $user->activities()->with('subject');

        // Apply filters
        if ($request->has('action')) {
            $query->where('action', $request->action);
        }

        if ($request->has('date_from')) {
            $query->where('created_at', '>=', $request->date_from);
        }

        if ($request->has('date_to')) {
            $query->where('created_at', '<=', $request->date_to);
        }

        $activities = $query->latest()->paginate($request->get('per_page', 20));

        $activities->getCollection()->transform(function ($activity) {
            return [
                'id' => $activity->id,
                'action' => $activity->action,
                'description' => $activity->description,
                'subject_type' => $activity->subject_type,
                'subject_id' => $activity->subject_id,
                'properties' => $activity->properties,
                'ip_address' => $activity->ip_address,
                'created_at' => $activity->created_at,
            ];
        });

        return $this->successResponse($activities, 'User activities retrieved successfully');
    }

    /**
     * Get user's followers.
     */
    public function followers(Request $request): JsonResponse
    {
        $user = $request->user();
        
        // This would require a followers relationship - placeholder implementation
        $followers = collect([]); // Replace with actual followers query
        
        return $this->successResponse($followers, 'User followers retrieved successfully');
    }

    /**
     * Get users that current user is following.
     */
    public function following(Request $request): JsonResponse
    {
        $user = $request->user();
        
        // This would require a following relationship - placeholder implementation
        $following = collect([]); // Replace with actual following query
        
        return $this->successResponse($following, 'User following retrieved successfully');
    }

    /**
     * Follow a user.
     */
    public function followUser(Request $request, User $userToFollow): JsonResponse
    {
        $currentUser = $request->user();
        
        if ($currentUser->id === $userToFollow->id) {
            return $this->errorResponse('Cannot follow yourself', 400);
        }
        
        // Implementation would depend on your followers system
        // This is a placeholder
        
        Activity::log('user_followed', $userToFollow, [
            'follower_id' => $currentUser->id,
        ]);
        
        return $this->successResponse(null, 'User followed successfully');
    }

    /**
     * Unfollow a user.
     */
    public function unfollowUser(Request $request, User $userToUnfollow): JsonResponse
    {
        $currentUser = $request->user();
        
        // Implementation would depend on your followers system
        // This is a placeholder
        
        Activity::log('user_unfollowed', $userToUnfollow, [
            'unfollower_id' => $currentUser->id,
        ]);
        
        return $this->successResponse(null, 'User unfollowed successfully');
    }

    /**
     * Get user's favorites/bookmarks.
     */
    public function favorites(Request $request): JsonResponse
    {
        $user = $request->user();
        
        // This would require a favorites/bookmarks system
        // Placeholder implementation
        $favorites = collect([]);
        
        return $this->successResponse($favorites, 'User favorites retrieved successfully');
    }

    /**
     * Add content to favorites.
     */
    public function addToFavorites(Request $request, Content $content): JsonResponse
    {
        $user = $request->user();
        
        // Implementation would depend on your favorites system
        // This is a placeholder
        
        Activity::log('content_favorited', $content, [
            'user_id' => $user->id,
        ]);
        
        return $this->successResponse(null, 'Content added to favorites');
    }

    /**
     * Remove content from favorites.
     */
    public function removeFromFavorites(Request $request, Content $content): JsonResponse
    {
        $user = $request->user();
        
        // Implementation would depend on your favorites system
        // This is a placeholder
        
        Activity::log('content_unfavorited', $content, [
            'user_id' => $user->id,
        ]);
        
        return $this->successResponse(null, 'Content removed from favorites');
    }

    /**
     * Get user's statistics.
     */
    public function statistics(Request $request): JsonResponse
    {
        $user = $request->user();

        $stats = [
            'content' => [
                'total' => $user->contents()->count(),
                'published' => $user->contents()->where('status', 'published')->count(),
                'draft' => $user->contents()->where('status', 'draft')->count(),
                'pending_review' => $user->contents()->where('status', 'pending_review')->count(),
                'total_views' => $user->contents()->sum('view_count'),
                'total_likes' => $user->contents()->sum('like_count'),
                'average_views' => round($user->contents()->avg('view_count'), 2),
            ],
            'engagement' => [
                'comments_made' => $user->comments()->count(),
                'comments_received' => \App\Models\Content\Comment::whereHas('commentable', function ($query) use ($user) {
                    $query->where('user_id', $user->id);
                })->count(),
            ],
            'media' => [
                'total_uploaded' => $user->media()->count(),
                'total_size' => $user->media()->sum('size'),
                'images' => $user->media()->images()->count(),
                'videos' => $user->media()->videos()->count(),
            ],
            'activity' => [
                'total_activities' => $user->activities()->count(),
                'activities_this_month' => $user->activities()->where('created_at', '>=', now()->startOfMonth())->count(),
                'last_active' => $user->activities()->latest()->value('created_at'),
            ],
            'account' => [
                'member_since' => $user->created_at,
                'days_active' => $user->created_at->diffInDays(now()),
                'profile_completion' => $this->calculateProfileCompletion($user),
            ],
        ];

        return $this->successResponse($stats, 'User statistics retrieved successfully');
    }

    /**
     * Calculate profile completion percentage.
     */
    private function calculateProfileCompletion(User $user): int
    {
        $fields = [
            'name' => !empty($user->name),
            'email' => !empty($user->email),
            'profile.first_name' => !empty($user->profile?->first_name),
            'profile.last_name' => !empty($user->profile?->last_name),
            'profile.bio' => !empty($user->profile?->bio),
            'profile.avatar' => !empty($user->profile?->avatar),
            'profile.phone' => !empty($user->profile?->phone),
            'profile.website' => !empty($user->profile?->website),
        ];

        $completed = array_sum($fields);
        $total = count($fields);

        return round(($completed / $total) * 100);
    }

    /**
     * Search users by name or email, excluding the authenticated user.
     */
    public function searchUsers(Request $request): JsonResponse
    {
        $userId = Auth::id();

        if (!$userId) {
            return response()->json(['status' => 'error', 'message' => 'Unauthenticated.'], 401);
        }

        $searchTerm = $request->query('search');

        $query = User::with(['profile', 'friendRequestsSent', 'friendRequestsReceived'])
            ->where('id', '!=', $userId)
            ->where(function ($q) use ($searchTerm) {
                $q->where('name', 'like', '%' . $searchTerm . '%')
                    ->orWhere('email', 'like', '%' . $searchTerm . '%')
                    ->orWhereHas('profile', function ($q) use ($searchTerm) {
                        $q->where('first_name', 'like', '%' . $searchTerm . '%')
                            ->orWhere('last_name', 'like', '%' . $searchTerm . '%')
                            ->orWhere('username', 'like', '%' . $searchTerm . '%');
                    });
            });

        $users = $query->get();

        $friends = Auth::user()->friends();

        $data = $users->map(function ($user) use ($userId, $friends) {
            $friendshipStatus = 'none';
            $friendRequestId = null;

            if ($friends->contains($user)) {
                $friendshipStatus = 'friends';
            } else {
                $sentRequest = $user->friendRequestsReceived->where('sender_id', $userId)->where('status', 'pending')->first();
                $receivedRequest = $user->friendRequestsSent->where('receiver_id', $userId)->where('status', 'pending')->first();

                if ($sentRequest) {
                    $friendshipStatus = 'request_sent';
                } elseif ($receivedRequest) {
                    $friendshipStatus = 'request_received';
                    $friendRequestId = $receivedRequest->id;
                }
            }

            return [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'avatar_url' => $user->profile?->avatar_url ?? '/images/user/default.jpg',
                'username' => $user->profile?->username,
                'bio' => $user->profile?->bio,
                'location' => $user->profile?->location,
                'created_at' => $user->created_at->diffForHumans(),
                'friendship_status' => $friendshipStatus,
                'friend_request_id' => $friendRequestId,
            ];
        });

        return response()->json(['status' => 'success', 'data' => $data]);
    }

    /**
     * Get a list of friends for the authenticated user.
     */
    public function getFriends(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();

            if (!$user) {
                return response()->json(['status' => 'error', 'message' => 'Unauthenticated.'], 401);
            }

            // Use the friends() relationship to get the collection of friend User models
            $friendsCollection = $user->friends();

            // Map over the collection to format the data
            $friends = $friendsCollection->map(function ($friend) {
                return [
                    'id' => $friend->id,
                    'name' => $friend->name,
                    'email' => $friend->email,
                    'avatar_url' => $friend->profile?->avatar_url ?? '/images/user/default.jpg',
                    'username' => $friend->profile?->username,
                    'bio' => $friend->profile?->bio,
                    'location' => $friend->profile?->location,
                    'created_at' => $friend->created_at->diffForHumans(),
                ];
            });

            return response()->json([
                'status' => 'success',
                'data' => $friends
            ]);
        } catch (\Exception $e) {
            // Return the actual error message for debugging
            return response()->json([
                'status' => 'error',
                'message' => 'An error occurred while fetching friends.',
                'error_details' => $e->getMessage(),
                'trace' => $e->getTraceAsString() // Be careful with this in production
            ], 500);
        }
    }
}