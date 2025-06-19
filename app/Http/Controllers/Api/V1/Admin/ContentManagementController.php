<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Api\ApiController;
use App\Models\Content\Post;
use App\Models\Content\ContentType;
use App\Models\Content\Category;
use App\Models\Content\Tag;
use App\Models\System\Activity;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ContentManagementController extends ApiController
{
    /**
     * Get all content with filtering and pagination.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Post::with(['user', 'contentType', 'categories', 'tags']);

        // Apply filters
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('content_type')) {
            $query->whereHas('contentType', function ($q) use ($request) {
                $q->where('slug', $request->content_type);
            });
        }

        if ($request->has('category')) {
            $query->whereHas('categories', function ($q) use ($request) {
                $q->where('slug', $request->category);
            });
        }

        if ($request->has('author')) {
            $query->where('user_id', $request->author);
        }

        if ($request->has('search')) {
            $query->search($request->search);
        }

        if ($request->has('date_from')) {
            $query->where('created_at', '>=', $request->date_from);
        }

        if ($request->has('date_to')) {
            $query->where('created_at', '<=', $request->date_to);
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
                'published_at' => $item->published_at,
                'created_at' => $item->created_at,
                'updated_at' => $item->updated_at,
                'author' => [
                    'id' => $item->user->id,
                    'name' => $item->user->name,
                ],
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

        return $this->successResponse($content, 'Content retrieved successfully');
    }

    /**
     * Get content details.
     */
    public function show(Content $content): JsonResponse
    {
        $content->load(['user', 'contentType', 'categories', 'tags', 'comments', 'media']);

        $contentData = [
            'id' => $content->id,
            'title' => $content->title,
            'slug' => $content->slug,
            'excerpt' => $content->excerpt,
            'content' => $content->content,
            'meta_data' => $content->meta_data,
            'custom_fields' => $content->custom_fields,
            'status' => $content->status,
            'featured_image' => $content->featured_image,
            'featured_image_url' => $content->featured_image_url,
            'is_featured' => $content->is_featured,
            'is_sticky' => $content->is_sticky,
            'allow_comments' => $content->allow_comments,
            'view_count' => $content->view_count,
            'like_count' => $content->like_count,
            'comment_count' => $content->comment_count,
            'seo_title' => $content->seo_title,
            'seo_description' => $content->seo_description,
            'seo_keywords' => $content->seo_keywords,
            'published_at' => $content->published_at,
            'created_at' => $content->created_at,
            'updated_at' => $content->updated_at,
            'author' => [
                'id' => $content->user->id,
                'name' => $content->user->name,
                'email' => $content->user->email,
            ],
            'content_type' => $content->contentType,
            'categories' => $content->categories,
            'tags' => $content->tags,
            'comments_count' => $content->comments->count(),
            'media_count' => $content->media->count(),
        ];

        return $this->successResponse($contentData, 'Content details retrieved successfully');
    }

    /**
     * Create new content.
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'content_type_id' => 'required|exists:content_types,id',
            'slug' => 'sometimes|string|max:255|unique:contents,slug',
            'excerpt' => 'nullable|string',
            'content' => 'nullable|string',
            'meta_data' => 'nullable|array',
            'custom_fields' => 'nullable|array',
            'status' => 'required|in:draft,published,archived,pending_review',
            'featured_image' => 'nullable|string',
            'is_featured' => 'boolean',
            'is_sticky' => 'boolean',
            'allow_comments' => 'boolean',
            'seo_title' => 'nullable|string|max:255',
            'seo_description' => 'nullable|string|max:500',
            'seo_keywords' => 'nullable|array',
            'published_at' => 'nullable|date',
            'categories' => 'nullable|array',
            'categories.*' => 'exists:categories,id',
            'tags' => 'nullable|array',
        ]);

        $content = Content::create([
            'content_type_id' => $request->content_type_id,
            'user_id' => auth()->id(),
            'title' => $request->title,
            'slug' => $request->slug,
            'excerpt' => $request->excerpt,
            'content' => $request->content,
            'meta_data' => $request->meta_data,
            'custom_fields' => $request->custom_fields,
            'status' => $request->status,
            'featured_image' => $request->featured_image,
            'is_featured' => $request->get('is_featured', false),
            'is_sticky' => $request->get('is_sticky', false),
            'allow_comments' => $request->get('allow_comments', true),
            'seo_title' => $request->seo_title,
            'seo_description' => $request->seo_description,
            'seo_keywords' => $request->seo_keywords,
            'published_at' => $request->status === 'published' ? ($request->published_at ?? now()) : $request->published_at,
        ]);

        // Attach categories
        if ($request->has('categories')) {
            $content->categories()->sync($request->categories);
        }

        // Attach tags
        if ($request->has('tags')) {
            $tagIds = [];
            foreach ($request->tags as $tagName) {
                $tag = Tag::firstOrCreate(['name' => $tagName]);
                $tag->incrementUsage();
                $tagIds[] = $tag->id;
            }
            $content->tags()->sync($tagIds);
        }

        // Log activity
        Activity::logCreated($content, ['created_by_admin' => auth()->id()]);

        $content->load(['user', 'contentType', 'categories', 'tags']);

        return $this->successResponse($content, 'Content created successfully', 201);
    }

    /**
     * Update content.
     */
    public function update(Request $request, Content $content): JsonResponse
    {
        $request->validate([
            'title' => 'sometimes|string|max:255',
            'content_type_id' => 'sometimes|exists:content_types,id',
            'slug' => ['sometimes', 'string', 'max:255', Rule::unique('contents')->ignore($content->id)],
            'excerpt' => 'nullable|string',
            'content' => 'nullable|string',
            'meta_data' => 'nullable|array',
            'custom_fields' => 'nullable|array',
            'status' => 'sometimes|in:draft,published,archived,pending_review',
            'featured_image' => 'nullable|string',
            'is_featured' => 'boolean',
            'is_sticky' => 'boolean',
            'allow_comments' => 'boolean',
            'seo_title' => 'nullable|string|max:255',
            'seo_description' => 'nullable|string|max:500',
            'seo_keywords' => 'nullable|array',
            'published_at' => 'nullable|date',
            'categories' => 'nullable|array',
            'categories.*' => 'exists:categories,id',
            'tags' => 'nullable|array',
        ]);

        $originalData = $content->toArray();

        // Update content data
        $updateData = $request->only([
            'title', 'content_type_id', 'slug', 'excerpt', 'content', 'meta_data',
            'custom_fields', 'status', 'featured_image', 'is_featured', 'is_sticky',
            'allow_comments', 'seo_title', 'seo_description', 'seo_keywords', 'published_at'
        ]);

        // Handle published_at when status changes to published
        if ($request->has('status') && $request->status === 'published' && !$content->published_at) {
            $updateData['published_at'] = $request->published_at ?? now();
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
                $tag = Tag::firstOrCreate(['name' => $tagName]);
                $tag->incrementUsage();
                $tagIds[] = $tag->id;
            }
            $content->tags()->sync($tagIds);
        }

        // Log activity
        $changes = array_diff_assoc($content->fresh()->toArray(), $originalData);
        Activity::logUpdated($content, $changes, ['updated_by_admin' => auth()->id()]);

        $content->load(['user', 'contentType', 'categories', 'tags']);

        return $this->successResponse($content, 'Content updated successfully');
    }

    /**
     * Delete content.
     */
    public function destroy(Content $content): JsonResponse
    {
        // Decrement tag usage counts
        foreach ($content->tags as $tag) {
            $tag->decrementUsage();
        }

        // Log activity before deletion
        Activity::logDeleted($content, [
            'deleted_by_admin' => auth()->id(),
            'title' => $content->title,
            'type' => $content->contentType->name,
        ]);

        $content->delete();

        return $this->successResponse(null, 'Content deleted successfully');
    }

    /**
     * Bulk update content.
     */
    public function bulkUpdate(Request $request): JsonResponse
    {
        $request->validate([
            'content_ids' => 'required|array',
            'content_ids.*' => 'exists:contents,id',
            'action' => 'required|in:publish,unpublish,archive,delete,feature,unfeature',
            'data' => 'sometimes|array',
        ]);

        $contentIds = $request->content_ids;
        $action = $request->action;
        $affectedCount = 0;

        $contents = Content::whereIn('id', $contentIds)->get();

        foreach ($contents as $content) {
            switch ($action) {
                case 'publish':
                    $content->update([
                        'status' => 'published',
                        'published_at' => $content->published_at ?? now(),
                    ]);
                    Activity::logCustom('bulk_published', "Content '{$content->title}' was published", $content);
                    $affectedCount++;
                    break;

                case 'unpublish':
                    $content->update(['status' => 'draft']);
                    Activity::logCustom('bulk_unpublished', "Content '{$content->title}' was unpublished", $content);
                    $affectedCount++;
                    break;

                case 'archive':
                    $content->update(['status' => 'archived']);
                    Activity::logCustom('bulk_archived', "Content '{$content->title}' was archived", $content);
                    $affectedCount++;
                    break;

                case 'feature':
                    $content->update(['is_featured' => true]);
                    Activity::logCustom('bulk_featured', "Content '{$content->title}' was featured", $content);
                    $affectedCount++;
                    break;

                case 'unfeature':
                    $content->update(['is_featured' => false]);
                    Activity::logCustom('bulk_unfeatured', "Content '{$content->title}' was unfeatured", $content);
                    $affectedCount++;
                    break;

                case 'delete':
                    // Decrement tag usage counts
                    foreach ($content->tags as $tag) {
                        $tag->decrementUsage();
                    }
                    Activity::logDeleted($content, ['bulk_deleted_by_admin' => auth()->id()]);
                    $content->delete();
                    $affectedCount++;
                    break;
            }
        }

        return $this->successResponse(
            ['affected_count' => $affectedCount],
            "Bulk {$action} completed successfully"
        );
    }

    /**
     * Get content types.
     */
    public function contentTypes(): JsonResponse
    {
        $contentTypes = ContentType::active()->ordered()->get();

        return $this->successResponse($contentTypes, 'Content types retrieved successfully');
    }

    /**
     * Get categories.
     */
    public function categories(Request $request): JsonResponse
    {
        $query = Category::with('parent')->active();

        if ($request->has('content_type_id')) {
            $query->where('content_type_id', $request->content_type_id);
        }

        $categories = $query->ordered()->get();

        return $this->successResponse($categories, 'Categories retrieved successfully');
    }

    /**
     * Get tags.
     */
    public function tags(Request $request): JsonResponse
    {
        $query = Tag::active();

        if ($request->has('search')) {
            $query->where('name', 'LIKE', '%' . $request->search . '%');
        }

        $tags = $query->popular()->get();

        return $this->successResponse($tags, 'Tags retrieved successfully');
    }

    /**
     * Get content statistics.
     */
    public function statistics(): JsonResponse
    {
        $stats = [
            'total_content' => Content::count(),
            'published_content' => Content::where('status', 'published')->count(),
            'draft_content' => Content::where('status', 'draft')->count(),
            'archived_content' => Content::where('status', 'archived')->count(),
            'featured_content' => Content::where('is_featured', true)->count(),
            'total_views' => Content::sum('view_count'),
            'content_by_type' => ContentType::withCount('contents')->get()->map(function ($type) {
                return [
                    'type' => $type->name,
                    'count' => $type->contents_count,
                ];
            }),
            'content_by_author' => Content::select('user_id')
                ->with('user:id,name')
                ->groupBy('user_id')
                ->selectRaw('user_id, count(*) as count')
                ->orderByDesc('count')
                ->limit(10)
                ->get()
                ->map(function ($item) {
                    return [
                        'author' => $item->user->name,
                        'count' => $item->count,
                    ];
                }),
            'recent_activity' => [
                'today' => Content::whereDate('created_at', today())->count(),
                'this_week' => Content::where('created_at', '>=', now()->startOfWeek())->count(),
                'this_month' => Content::where('created_at', '>=', now()->startOfMonth())->count(),
            ],
        ];

        return $this->successResponse($stats, 'Content statistics retrieved successfully');
    }

    /**
     * Duplicate content.
     */
    public function duplicate(Content $content): JsonResponse
    {
        $newContent = $content->replicate();
        $newContent->title = $content->title . ' (Copy)';
        $newContent->slug = null; // Will be auto-generated
        $newContent->status = 'draft';
        $newContent->published_at = null;
        $newContent->view_count = 0;
        $newContent->like_count = 0;
        $newContent->comment_count = 0;
        $newContent->user_id = auth()->id();
        $newContent->save();

        // Copy relationships
        $newContent->categories()->sync($content->categories->pluck('id'));
        $newContent->tags()->sync($content->tags->pluck('id'));

        // Log activity
        Activity::logCreated($newContent, [
            'duplicated_from' => $content->id,
            'original_title' => $content->title,
        ]);

        $newContent->load(['user', 'contentType', 'categories', 'tags']);

        return $this->successResponse($newContent, 'Content duplicated successfully', 201);
    }
}