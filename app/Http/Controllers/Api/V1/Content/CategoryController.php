<?php

namespace App\Http\Controllers\Api\V1\Content;

use App\Http\Controllers\Api\ApiController;
use App\Models\Content\Category;
use App\Models\Content\Content;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CategoryController extends ApiController
{
    /**
     * Get all categories.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Category::with(['parent', 'children'])->active();

        // Filter by content type if specified
        if ($request->has('content_type_id')) {
            $query->where('content_type_id', $request->content_type_id);
        }

        // Filter root categories only
        if ($request->boolean('roots_only')) {
            $query->roots();
        }

        $categories = $query->ordered()->get();

        $categories->transform(function ($category) {
            return [
                'id' => $category->id,
                'name' => $category->name,
                'slug' => $category->slug,
                'description' => $category->description,
                'image_url' => $category->image_url,
                'color' => $category->color,
                'icon' => $category->icon,
                'content_count' => $category->content_count,
                'parent_id' => $category->parent_id,
                'depth' => $category->depth,
                'has_children' => $category->hasChildren(),
                'children' => $category->children->map(function ($child) {
                    return [
                        'id' => $child->id,
                        'name' => $child->name,
                        'slug' => $child->slug,
                        'content_count' => $child->content_count,
                        'color' => $child->color,
                    ];
                }),
            ];
        });

        return $this->successResponse($categories, 'Categories retrieved successfully');
    }

    /**
     * Get category tree structure.
     */
    public function tree(Request $request): JsonResponse
    {
        $query = Category::with(['children.children'])->active()->roots();

        if ($request->has('content_type_id')) {
            $query->where('content_type_id', $request->content_type_id);
        }

        $categories = $query->ordered()->get();

        $tree = $this->buildCategoryTree($categories);

        return $this->successResponse($tree, 'Category tree retrieved successfully');
    }

    /**
     * Get category details with content.
     */
    public function show(Request $request, string $slug): JsonResponse
    {
        $category = Category::with(['parent', 'children'])
                           ->where('slug', $slug)
                           ->active()
                           ->first();

        if (!$category) {
            return $this->errorResponse('Category not found', 404);
        }

        // Get category content with pagination
        $contentQuery = $category->contents()
                                ->with(['user.profile', 'contentType', 'tags'])
                                ->published();

        // Apply sorting
        $sortBy = $request->get('sort_by', 'published_at');
        $sortDir = $request->get('sort_dir', 'desc');
        $contentQuery->orderBy($sortBy, $sortDir);

        $content = $contentQuery->paginate($request->get('per_page', 15));

        $content->getCollection()->transform(function ($item) {
            return [
                'id' => $item->id,
                'title' => $item->title,
                'slug' => $item->slug,
                'excerpt' => $item->excerpt,
                'featured_image_url' => $item->featured_image_url,
                'view_count' => $item->view_count,
                'like_count' => $item->like_count,
                'published_at' => $item->published_at,
                'author' => [
                    'id' => $item->user->id,
                    'name' => $item->user->name,
                    'avatar_url' => $item->user->profile?->avatar_url,
                ],
                'content_type' => $item->contentType->name,
                'tags' => $item->tags->pluck('name'),
            ];
        });

        $categoryData = [
            'id' => $category->id,
            'name' => $category->name,
            'slug' => $category->slug,
            'description' => $category->description,
            'image_url' => $category->image_url,
            'color' => $category->color,
            'icon' => $category->icon,
            'content_count' => $category->content_count,
            'parent' => $category->parent ? [
                'id' => $category->parent->id,
                'name' => $category->parent->name,
                'slug' => $category->parent->slug,
            ] : null,
            'children' => $category->children->map(function ($child) {
                return [
                    'id' => $child->id,
                    'name' => $child->name,
                    'slug' => $child->slug,
                    'content_count' => $child->content_count,
                ];
            }),
            'breadcrumbs' => $category->breadcrumbs,
            'seo' => [
                'title' => $category->seo_title ?? $category->name,
                'description' => $category->seo_description ?? $category->description,
            ],
            'content' => $content,
        ];

        return $this->successResponse($categoryData, 'Category retrieved successfully');
    }

    /**
     * Get popular categories.
     */
    public function popular(Request $request): JsonResponse
    {
        $limit = $request->get('limit', 10);

        $categories = Category::active()
                            ->orderByDesc('content_count')
                            ->limit($limit)
                            ->get();

        $categories->transform(function ($category) {
            return [
                'id' => $category->id,
                'name' => $category->name,
                'slug' => $category->slug,
                'description' => $category->description,
                'color' => $category->color,
                'icon' => $category->icon,
                'content_count' => $category->content_count,
            ];
        });

        return $this->successResponse($categories, 'Popular categories retrieved successfully');
    }

    /**
     * Get category statistics.
     */
    public function statistics(string $slug): JsonResponse
    {
        $category = Category::where('slug', $slug)->active()->first();

        if (!$category) {
            return $this->errorResponse('Category not found', 404);
        }

        $stats = [
            'total_content' => $category->contents()->count(),
            'published_content' => $category->contents()->published()->count(),
            'total_views' => $category->contents()->sum('view_count'),
            'total_likes' => $category->contents()->sum('like_count'),
            'average_views' => round($category->contents()->avg('view_count'), 2),
            'top_authors' => $category->contents()
                                   ->published()
                                   ->with('user')
                                   ->select('user_id')
                                   ->groupBy('user_id')
                                   ->orderByRaw('COUNT(*) DESC')
                                   ->limit(5)
                                   ->get()
                                   ->map(function ($content) {
                                       return [
                                           'id' => $content->user->id,
                                           'name' => $content->user->name,
                                           'content_count' => $content->user->contents()
                                               ->whereHas('categories', function ($q) use ($category) {
                                                   $q->where('categories.id', $category->id);
                                               })
                                               ->published()
                                               ->count(),
                                       ];
                                   }),
            'recent_activity' => [
                'this_week' => $category->contents()
                                      ->published()
                                      ->where('published_at', '>=', now()->startOfWeek())
                                      ->count(),
                'this_month' => $category->contents()
                                       ->published()
                                       ->where('published_at', '>=', now()->startOfMonth())
                                       ->count(),
            ],
        ];

        return $this->successResponse($stats, 'Category statistics retrieved successfully');
    }

    /**
     * Build hierarchical category tree.
     */
    private function buildCategoryTree($categories, $parentId = null): array
    {
        $tree = [];

        foreach ($categories as $category) {
            if ($category->parent_id === $parentId) {
                $categoryData = [
                    'id' => $category->id,
                    'name' => $category->name,
                    'slug' => $category->slug,
                    'description' => $category->description,
                    'color' => $category->color,
                    'icon' => $category->icon,
                    'content_count' => $category->content_count,
                    'children' => $this->buildCategoryTree($categories, $category->id),
                ];

                $tree[] = $categoryData;
            }
        }

        return $tree;
    }
}