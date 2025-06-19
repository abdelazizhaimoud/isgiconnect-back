<?php

namespace App\Http\Controllers\Api\V1\Content;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\V1\BaseController;
use App\Models\Content\Post;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use App\Models\System\Activity;

class ContentController extends BaseController
{
    /**
     * Display a listing of the posts.
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = $request->get('per_page', 15);
        $posts = Post::with(['user.profile', 'contentType', 'categories', 'tags', 'comments.user'])
                     ->published()
                     ->orderByDesc('published_at')
                     ->paginate($perPage);

        // Add is_owner and liked attributes to each post
        $posts->getCollection()->transform(function ($post) {
            $post->is_owner = Auth::check() ? ($post->user_id === Auth::id()) : false;
            $post->liked = Auth::check() ? $post->likes()->where('user_id', Auth::id())->exists() : false;
            return $post;
        });

        return $this->sendResponse($posts, 'Posts retrieved successfully');
    }

    /**
     * Store a newly created post in storage.
     */
    public function store(Request $request): JsonResponse
    {
        if (!Auth::check()) {
            return $this->sendError('Authentication required', 401);
        }

        $validatedData = $request->validate([
            'content_type_id' => 'required|exists:content_types,id',
            'content' => 'required|string',
            'images' => 'nullable|array',
            'images.*' => 'file|image|mimes:jpeg,png,jpg,gif|max:2048', // Validate as actual image files
            'status' => 'sometimes|in:published,draft,pending,archived',
            'parent_id' => 'nullable|exists:posts,id',
            'allow_comments' => 'sometimes|boolean',
            'published_at' => 'nullable|date',
            'categories' => 'nullable|array',
            'categories.*' => 'exists:categories,id',
            'tags' => 'nullable|array',
            'tags.*' => 'exists:tags,id',
        ]);

        $imageObjects = []; // error here - should restore previous images
        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $imageFile) {
                $path = $imageFile->store('uploads/images', 'public');
                $fullUrl = asset('storage/' . $path);

                // Get image dimensions
                [$width, $height] = getimagesize($imageFile->getRealPath());

                $imageObjects[] = [ // error here - shouldn't store in [][]
                    'alt' => '', // Default alt text, can be extended to allow user input
                    'url' => $fullUrl,
                    'width' => $width,
                    'height' => $height,
                ];
            }
        }

        $post = Post::create([
            'content_type_id' => $validatedData['content_type_id'],
            'user_id' => Auth::id(),
            'content' => $validatedData['content'],
            'images' => $imageObjects,
            'status' => $validatedData['status'] ?? 'draft',
            'parent_id' => $validatedData['parent_id'] ?? null,
            'allow_comments' => $validatedData['allow_comments'] ?? true,
            'published_at' => $validatedData['published_at'] ?? now(),
        ]);

        // Attach categories if provided
        if (isset($validatedData['categories'])) {
            $post->categories()->sync($validatedData['categories']);
        }

        // Attach tags if provided
        if (isset($validatedData['tags'])) {
            $post->tags()->sync($validatedData['tags']);
        }
        $createdBy = Auth::user()->role;

        // Log activity
        Activity::logCreated($post, ['created_by_' . $createdBy => Auth::id()]);

        $post->load(['user.profile', 'contentType', 'categories', 'tags', 'comments.user']);

        // Manually set ownership and liked status for the new post
        $post->is_owner = true;
        $post->liked = false; // A new post is never liked by default

        return $this->sendResponse($post, 'Post created successfully', 201);
    }

    /**
     * Update an existing post. Only the owner can update.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        if (!Auth::check()) {
            return $this->sendError('Authentication required', 401);
        }

        $post = Post::find($id);
        if (!$post) {
            return $this->sendError('Post not found', 404);
        }

        if ($post->user_id !== Auth::id()) {
            return $this->sendError('You are not authorized to update this post', 403);
        }

        $validatedData = $request->validate([
            'title' => 'sometimes|string|max:255',
            'excerpt' => 'sometimes|string|max:500|nullable',
            'content' => 'sometimes|string',
            'status' => 'sometimes|in:published,draft,pending,archived',
            'featured_image' => 'sometimes|string|nullable',
            'is_featured' => 'sometimes|boolean',
            'is_sticky' => 'sometimes|boolean',
            'allow_comments' => 'sometimes|boolean',
            'meta_data' => 'sometimes|array',
            'custom_fields' => 'sometimes|array',
            'seo_title' => 'sometimes|string|max:255|nullable',
            'seo_description' => 'sometimes|string|max:500|nullable',
            'seo_keywords' => 'sometimes|array|nullable',
            'published_at' => 'sometimes|date|nullable',
            'images' => 'sometimes|array|nullable',
            'images.*' => 'sometimes|file|image|mimes:jpeg,png,jpg,gif|max:2048', // Validate as actual image files
            'category_ids' => 'sometimes|array',
            'category_ids.*' => 'exists:categories,id',
            'tag_ids' => 'sometimes|array',
            'tag_ids.*' => 'exists:tags,id',
        ]);

        $post->fill($validatedData);

        // Handle image uploads and existing images
        $currentImages = $post->images ?? [];
        $imagesToKeep = [];

        if ($request->has('removed_image_urls')) {
            $removedImageUrls = json_decode($request->input('removed_image_urls'), true);
            if (!is_array($removedImageUrls) || empty($removedImageUrls)) {
                $imagesToKeep = $currentImages;
            } else {
                foreach ($currentImages as $image) {
                    if (!in_array($image['url'], $removedImageUrls)) {
                        $imagesToKeep[] = $image;
                    } else {
                        // Delete the file from storage
                        $path = str_replace(asset('storage/'), '', $image['url']);
                        if (Storage::disk('public')->exists($path)) {
                            Storage::disk('public')->delete($path);
                        }
                    }
                }
            }
        } else {
            $imagesToKeep = $currentImages;
        }

        $newlyUploadedImages = [];
        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $imageFile) {
                if (!$imageFile || !$imageFile->isValid()) {
                    continue; // Skip empty or invalid files
                }
                $path = $imageFile->store('uploads/images', 'public');
                $fullUrl = asset('storage/' . $path);

                // Get image dimensions
                [$width, $height] = getimagesize($imageFile->getRealPath());

                $newlyUploadedImages[] = [
                    'alt' => '', // Default alt text, can be extended to allow user input
                    'url' => $fullUrl,
                    'width' => $width,
                    'height' => $height,
                ];
            }
        }

        // Filter out empty or invalid image arrays
        $imagesToKeep = array_filter($imagesToKeep, function($img) {
            return is_array($img) && !empty($img) && isset($img['url']);
        });
        // Use 'existing_images' from request if present
        $existingImages = [];
        if ($request->has('existing_images')) {
            $existingImages = json_decode($request->input('existing_images'), true) ?: [];
        }
        // Combine existing images to keep with newly uploaded images
        $finalImages = array_merge($existingImages, $newlyUploadedImages);
        $post->images = $finalImages;

        // Handle relationships if provided
        if (isset($validatedData['category_ids'])) {
            $post->categories()->sync($validatedData['category_ids']);
        }
        if (isset($validatedData['tag_ids'])) {
            $post->tags()->sync($validatedData['tag_ids']);
        }
        $updatedBy = Auth::user()->role;

        // Log activity
        Activity::logUpdated($post, ['updated_by_' . $updatedBy => Auth::id()]);

        $post->save();

        return $this->sendResponse($post->load(['user.profile', 'contentType', 'categories', 'tags']), 'Post updated successfully');
    }

    /**
     * Report the specified post.
     */
    public function report(Request $request, int $id): JsonResponse
    {
        if (!Auth::check()) {
            return $this->sendError('Authentication required', 401);
        }

        $post = Post::find($id);
        if (!$post) {
            return $this->sendError('Post not found', 404);
        }

        if ($post->user_id === Auth::id()) {
            return $this->sendError('You cannot report your own post.', 403);
        }

        $validated = $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        // Store as activity
        Activity::logReport($post, [
            'reported_by' => Auth::id(),
            'reason' => $validated['reason'],
        ]);

        return $this->sendResponse(null, 'Post reported successfully');
    }

    /**
     * Remove the specified post from storage.
     */
    public function destroy(int $id): JsonResponse
    {
        if (!Auth::check()) {
            return $this->sendError('Authentication required', 401);
        }

        $post = Post::find($id);
        if (!$post) {
            return $this->sendError('Post not found', 404);
        }

        if ($post->user_id !== Auth::id()) {
            return $this->sendError('You are not authorized to delete this post', 403);
        }

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

        $post->delete();

        // Log activity
        $deletedBy = Auth::user()->role;
        Activity::logDeleted($post, ['deleted_by_' . $deletedBy => Auth::id()]);

        return $this->sendResponse(null, 'Post deleted successfully', 200);
    }

    /**
     * Get trending content.
     */
    public function trending(Request $request): JsonResponse
    {
        $days = $request->get('days', 7);
        $limit = $request->get('limit', 10);

        $trending = Post::with(['user.profile', 'contentType'])
                      ->where('status', 'published')
                      ->where('created_at', '>=', now()->subDays($days))
                      ->orderByRaw('(likes_count + comments_count * 2) DESC')
                      ->limit($limit)
                      ->get();

        $trending->transform(function ($item) {
            return [
                'id' => $item->id,
                'content' => $item->content,
                'images' => $item->images,
                'likes_count' => $item->likes_count,
                'comments_count' => $item->comments_count,
                'shares_count' => $item->shares_count,
                'trending_score' => $item->likes_count + ($item->comments_count * 2),
                'created_at' => $item->created_at,
                'author' => [
                    'id' => $item->user->id,
                    'name' => $item->user->name,
                    'avatar_url' => $item->user->profile?->avatar_url,
                ],
                'content_type' => $item->contentType ? $item->contentType->name : null,
            ];
        });

        return $this->sendResponse($trending, 'Trending posts retrieved successfully');
    }

    /**
     * Get single post by ID.
     */
    public function show(int $id): JsonResponse
    {
        $post = Post::with([
            'user.profile',
            'contentType',
            'categories',
            'tags',
            'comments.user.profile',
            'media'
        ])
          ->where('id', $id)
          ->where('status', 'published')
          ->firstOrFail();

        // Increment view count
        $post->increment('views_count');

        // Load related content
        $related = Post::where('content_type_id', $post->content_type_id)
                     ->where('id', '!=', $post->id)
                     ->where('status', 'published')
                     ->inRandomOrder()
                     ->limit(4)
                     ->get();

        $userId = Auth::check() ? Auth::id() : null;

        return $this->sendResponse([
            'post' => [
                'id' => $post->id,
                'content' => $post->content,
                'images' => $post->images,
                'likes_count' => $post->likes_count,
                'comments_count' => $post->comments_count,
                'shares_count' => $post->shares_count,
                'created_at' => $post->created_at,
                'status' => $post->status,
                'author' => [
                    'id' => $post->user->id,
                    'name' => $post->user->name,
                    'avatar_url' => $post->user->profile?->avatar_url,
                ],
                'liked' => $userId ? $post->likes()->where('user_id', $userId)->exists() : false,
                'content_type' => $post->contentType ? [
                    'id' => $post->contentType->id,
                    'name' => $post->contentType->name,
                ] : null,
                'categories' => $post->categories->map(function ($category) {
                    return [
                        'id' => $category->id,
                        'name' => $category->name,
                    ];
                }),
                'tags' => $post->tags->map(function ($tag) {
                    return [
                        'id' => $tag->id,
                        'name' => $tag->name,
                    ];
                })
            ],
            'related' => $related
        ], 'Post retrieved successfully');
    }

    /**
     * Get related posts.
     */
    public function related(int $id): JsonResponse
    {
        $post = Post::where('id', $id)
                  ->where('status', 'published')
                  ->first();

        if (!$post) {
            return $this->sendError('Post not found', 404);
        }

        // Find related posts based on categories and tags
        $related = Post::with(['user.profile'])
                     ->where('id', '!=', $post->id)
                     ->where('status', 'published')
                     ->where(function($query) use ($post) {
                         $query->whereHas('categories', function($q) use ($post) {
                             $q->whereIn('categories.id', $post->categories->pluck('id'));
                         });
                     })
                     ->orderByDesc('created_at')
                     ->limit(4)
                     ->get();

        $related->transform(function ($item) {
            return [
                'id' => $item->id,
                'content' => $item->content,
                'images' => $item->images,
                'created_at' => $item->created_at,
                'likes_count' => $item->likes_count,
                'comments_count' => $item->comments_count,
                'author' => [
                    'name' => $item->user->name,
                    'avatar_url' => $item->user->profile?->avatar_url,
                ],
            ];
        });

        return $this->sendResponse($related, 'Related posts retrieved successfully');
    }

    /**
     * Like a post.
     */
    public function like(int $id): JsonResponse
    {
        if (!Auth::check()) {
            return $this->sendError('Authentication required', 401);
        }

        $post = Post::where('id', $id)
                  ->where('status', 'published')
                  ->first();

        if (!$post) {
            return $this->sendError('Post not found', 404);
        }

        // Prevent duplicate likes
        $existingLike = $post->likes()->where('user_id', Auth::id())->first();
        if ($existingLike) {
            // Already liked, just return success
            return $this->sendResponse([
                'liked' => true,
                'likes_count' => $post->likes_count,
            ], 'Post liked successfully');
        }

        // Create the like
        $post->likes()->create([
            'user_id' => Auth::id(),
        ]);

        // Increment the like count
        $post->increment('likes_count');

        // Log like activity
        Activity::log('post_liked', $post, [
            'liker_id' => Auth::id(),
        ]);

        return $this->sendResponse([
            'liked' => true,
            'likes_count' => $post->likes_count,
        ], 'Post liked successfully');
    }

    /**
     * Unlike a post.
     */
    public function unlike(int $id): JsonResponse
    {
        if (!Auth::check()) {
            return $this->sendError('Authentication required', 401);
        }

        $post = Post::where('id', $id)
                  ->where('status', 'published')
                  ->first();

        if (!$post) {
            return $this->sendError('Post not found', 404);
        }

        // Find the like for this user
        $existingLike = $post->likes()->where('user_id', Auth::id())->first();
        if (!$existingLike) {
            return $this->sendError('Post not liked yet', 422);
        }

        // Delete the like
        $existingLike->delete();

        // Decrement the like count (not below 0)
        if ($post->likes_count > 0) {
            $post->decrement('likes_count');
        }

        // Log unlike activity
        Activity::log('post_unliked', $post, [
                        'unliker_id' => Auth::id(),
        ]);

        return $this->sendResponse([
            'liked' => false,
            'likes_count' => $post->likes_count,
        ], 'Post unliked successfully');
    }

    /**
     * Get content by author.
     */
    public function byAuthor(Request $request, int $authorId): JsonResponse
    {
        $content = Post::with(['contentType', 'categories'])
                         ->published()
                         ->where('user_id', $authorId)
                         ->orderByDesc('published_at')
                         ->paginate($request->get('per_page', 15));

        $content->getCollection()->transform(function ($item) {
            return [
                'id' => $item->id,
                'title' => $item->title,
                'slug' => $item->slug,
                'excerpt' => $item->excerpt,
                'featured_image_url' => $item->featured_image_url,
                'view_count' => $item->view_count,
                'published_at' => $item->published_at,
                'content_type' => $item->contentType->name,
                'categories' => $item->categories->pluck('name'),
            ];
        });

        return $this->sendResponse($content, 'Author content retrieved successfully');
    }

    /**
     * Search content.
     */
    public function search(Request $request): JsonResponse
    {
        $request->validate([
            'q' => 'required|string|min:2|max:100',
            'content_type' => 'sometimes|string|exists:content_types,slug',
            'category' => 'sometimes|string|exists:categories,slug', 
            'user_id' => 'sometimes|integer|exists:users,id',
            'per_page' => 'sometimes|integer|min:1|max:50',
            'sort' => 'sometimes|in:latest,oldest,popular,most_liked,most_commented',
        ]);

        $searchTerm = $request->input('q');
        $perPage = $request->input('per_page', 15);
        $sort = $request->input('sort', 'latest');

        // Base query with relationships
        $query = Post::with(['user.profile', 'contentType', 'categories', 'tags'])
                    ->where('status', 'published')
                    ->where(function($q) use ($searchTerm) {
                        $q->where('content', 'like', '%' . $searchTerm . '%');
                        // Also search in title if you have it
                        // $q->orWhere('title', 'like', '%' . $searchTerm . '%');
                    });

        // Apply content type filter
        if ($request->has('content_type')) {
            $query->whereHas('contentType', function ($q) use ($request) {
                $q->where('slug', $request->content_type);
            });
        }

        // Apply category filter
        if ($request->has('category')) {
            $query->whereHas('categories', function ($q) use ($request) {
                $q->where('slug', $request->category);
            });
        }

        // Apply user filter
        if ($request->has('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        // Apply sorting
        switch ($sort) {
            case 'oldest':
                $query->orderBy('published_at', 'asc');
                break;
            case 'popular':
                $query->orderByRaw('(likes_count + comments_count + shares_count) DESC');
                break;
            case 'most_liked':
                $query->orderBy('likes_count', 'desc');
                break;
            case 'most_commented':
                $query->orderBy('comments_count', 'desc');
                break;
            case 'latest':
            default:
                $query->orderBy('published_at', 'desc');
                break;
        }

        // Execute query with pagination
        $results = $query->paginate($perPage);

        // Transform results for API response
        $results->getCollection()->transform(function ($post) {
            $post->is_owner = Auth::check() ? ($post->user_id === Auth::id()) : false;
            $post->liked = Auth::check() ? $post->likes()->where('user_id', Auth::id())->exists() : false;
            
            return [
                'id' => $post->id,
                'content' => $post->content,
                'images' => $post->images ?? [],
                'likes_count' => $post->likes_count ?? 0,
                'comments_count' => $post->comments_count ?? 0,
                'shares_count' => $post->shares_count ?? 0,
                'published_at' => $post->published_at,
                'created_at' => $post->created_at,
                'is_owner' => $post->is_owner,
                'liked' => $post->liked,
                'author' => [
                    'id' => $post->user->id,
                    'name' => $post->user->name,
                    'username' => $post->user->username,
                    'avatar_url' => $post->user->profile?->avatar ?? null,
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
                })
            ];
        });

        // Add search metadata
        $searchMetadata = [
            'search_term' => $searchTerm,
            'total_results' => $results->total(),
            'current_page' => $results->currentPage(),
            'last_page' => $results->lastPage(),
            'per_page' => $results->perPage(),
            'filters_applied' => [
                'content_type' => $request->input('content_type'),
                'category' => $request->input('category'),
                'user_id' => $request->input('user_id'),
                'sort' => $sort,
            ]
        ];

        return $this->sendResponse([
            'posts' => $results->items(),
            'pagination' => [
                'current_page' => $results->currentPage(),
                'last_page' => $results->lastPage(),
                'per_page' => $results->perPage(),
                'total' => $results->total(),
                'from' => $results->firstItem(),
                'to' => $results->lastItem(),
                'has_next_page' => $results->hasMorePages(),
                'has_prev_page' => $results->currentPage() > 1,
                'next_page_url' => $results->nextPageUrl(),
                'prev_page_url' => $results->previousPageUrl(),
            ],
            'search_metadata' => $searchMetadata
        ], 'Search completed successfully');
    }
}