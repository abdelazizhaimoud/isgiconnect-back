<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Models\Content\Content;
use App\Models\Content\Category;
use App\Models\Content\Tag;
use App\Models\User\User;
use App\Models\Media\Media;
use App\Models\System\Activity;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SearchController extends ApiController
{
    /**
     * Global search across all content types.
     */
    public function global(Request $request): JsonResponse
    {
        $request->validate([
            'q' => 'required|string|min:2|max:255',
            'types' => 'sometimes|array',
            'types.*' => 'in:content,users,media,categories,tags',
            'limit' => 'sometimes|integer|min:1|max:50',
            'include_private' => 'sometimes|boolean',
        ]);

        $query = $request->q;
        $searchTypes = $request->get('types', ['content', 'users', 'media', 'categories', 'tags']);
        $limit = $request->get('limit', 10);
        $includePrivate = $request->get('include_private', false);

        $results = [];

        // Search Content
        if (in_array('content', $searchTypes)) {
            $results['content'] = $this->searchContent($query, $limit, $includePrivate);
        }

        // Search Users
        if (in_array('users', $searchTypes)) {
            $results['users'] = $this->searchUsers($query, $limit);
        }

        // Search Media
        if (in_array('media', $searchTypes)) {
            $results['media'] = $this->searchMedia($query, $limit, $includePrivate);
        }

        // Search Categories
        if (in_array('categories', $searchTypes)) {
            $results['categories'] = $this->searchCategories($query, $limit);
        }

        // Search Tags
        if (in_array('tags', $searchTypes)) {
            $results['tags'] = $this->searchTags($query, $limit);
        }

        // Calculate total results
        $totalResults = collect($results)->sum(function ($items) {
            return is_array($items) ? count($items) : $items->count();
        });

        // Log search activity
        if (auth()->check()) {
            Activity::log('global_search_performed', null, [
                'query' => $query,
                'types' => $searchTypes,
                'total_results' => $totalResults,
                'user_id' => auth()->id(),
                'ip_address' => $request->ip(),
            ]);
        }

        return $this->successResponse([
            'query' => $query,
            'total_results' => $totalResults,
            'results' => $results,
            'search_time' => microtime(true) - LARAVEL_START,
        ], 'Search completed successfully');
    }

    /**
     * Advanced search with filters.
     */
    public function advanced(Request $request): JsonResponse
    {
        $request->validate([
            'q' => 'required|string|min:2|max:255',
            'content_type_id' => 'sometimes|exists:content_types,id',
            'category_id' => 'sometimes|exists:categories,id',
            'tag_ids' => 'sometimes|array',
            'tag_ids.*' => 'exists:tags,id',
            'author_id' => 'sometimes|exists:users,id',
            'date_from' => 'sometimes|date',
            'date_to' => 'sometimes|date|after:date_from',
            'status' => 'sometimes|in:published,draft,archived',
            'sort_by' => 'sometimes|in:relevance,date,views,likes,title',
            'sort_dir' => 'sometimes|in:asc,desc',
            'per_page' => 'sometimes|integer|min:1|max:100',
        ]);

        $query = Content::with(['user.profile', 'contentType', 'categories', 'tags'])
                       ->search($request->q);

        // Apply filters
        if ($request->has('content_type_id')) {
            $query->where('content_type_id', $request->content_type_id);
        }

        if ($request->has('category_id')) {
            $query->whereHas('categories', function ($q) use ($request) {
                $q->where('categories.id', $request->category_id);
            });
        }

        if ($request->has('tag_ids')) {
            $query->whereHas('tags', function ($q) use ($request) {
                $q->whereIn('tags.id', $request->tag_ids);
            });
        }

        if ($request->has('author_id')) {
            $query->where('user_id', $request->author_id);
        }

        if ($request->has('date_from')) {
            $query->where('published_at', '>=', $request->date_from);
        }

        if ($request->has('date_to')) {
            $query->where('published_at', '<=', $request->date_to);
        }

        if ($request->has('status')) {
            if ($request->status === 'published') {
                $query->published();
            } else {
                $query->where('status', $request->status);
            }
        } else {
            $query->published(); // Default to published content
        }

        // Apply sorting
        $sortBy = $request->get('sort_by', 'relevance');
        $sortDir = $request->get('sort_dir', 'desc');

        switch ($sortBy) {
            case 'date':
                $query->orderBy('published_at', $sortDir);
                break;
            case 'views':
                $query->orderBy('view_count', $sortDir);
                break;
            case 'likes':
                $query->orderBy('like_count', $sortDir);
                break;
            case 'title':
                $query->orderBy('title', $sortDir);
                break;
            case 'relevance':
            default:
                // For relevance, we'll use a simple scoring system
                $query->orderByRaw($this->buildRelevanceQuery($request->q), $sortDir);
                break;
        }

        $results = $query->paginate($request->get('per_page', 15));

        // Transform results
        $results->getCollection()->transform(function ($content) use ($request) {
            return [
                'id' => $content->id,
                'title' => $content->title,
                'slug' => $content->slug,
                'excerpt' => $content->getSearchExcerpt('content', $request->q),
                'featured_image_url' => $content->featured_image_url,
                'view_count' => $content->view_count,
                'like_count' => $content->like_count,
                'published_at' => $content->published_at,
                'relevance_score' => $this->calculateRelevanceScore($content, $request->q),
                'author' => [
                    'id' => $content->user->id,
                    'name' => $content->user->name,
                    'avatar_url' => $content->user->profile?->avatar_url,
                ],
                'content_type' => [
                    'id' => $content->contentType->id,
                    'name' => $content->contentType->name,
                ],
                'categories' => $content->categories->map(function ($category) {
                    return [
                        'id' => $category->id,
                        'name' => $category->name,
                        'slug' => $category->slug,
                    ];
                }),
                'tags' => $content->tags->pluck('name'),
            ];
        });

        // Log advanced search
        if (auth()->check()) {
            Activity::log('advanced_search_performed', null, [
                'query' => $request->q,
                'filters' => $request->only(['content_type_id', 'category_id', 'tag_ids', 'author_id', 'date_from', 'date_to']),
                'results_count' => $results->total(),
            ]);
        }

        return $this->successResponse($results, 'Advanced search completed successfully');
    }

    /**
     * Get search suggestions/autocomplete.
     */
    public function suggestions(Request $request): JsonResponse
    {
        $request->validate([
            'q' => 'required|string|min:1|max:255',
            'limit' => 'sometimes|integer|min:1|max:20',
        ]);

        $query = $request->q;
        $limit = $request->get('limit', 10);

        $suggestions = [];

        // Content suggestions
        $contentSuggestions = Content::published()
            ->where('title', 'LIKE', "%{$query}%")
            ->orderByDesc('view_count')
            ->limit($limit)
            ->pluck('title')
            ->unique()
            ->values();

        // Tag suggestions
        $tagSuggestions = Tag::active()
            ->where('name', 'LIKE', "%{$query}%")
            ->orderByDesc('usage_count')
            ->limit($limit)
            ->pluck('name')
            ->unique()
            ->values();

        // Category suggestions
        $categorySuggestions = Category::active()
            ->where('name', 'LIKE', "%{$query}%")
            ->orderByDesc('content_count')
            ->limit($limit)
            ->pluck('name')
            ->unique()
            ->values();

        // User suggestions
        $userSuggestions = User::where('name', 'LIKE', "%{$query}%")
            ->where('status', 'active')
            ->limit($limit)
            ->pluck('name')
            ->unique()
            ->values();

        // Combine and prioritize suggestions
        $allSuggestions = collect()
            ->merge($contentSuggestions->map(fn($item) => ['text' => $item, 'type' => 'content']))
            ->merge($tagSuggestions->map(fn($item) => ['text' => $item, 'type' => 'tag']))
            ->merge($categorySuggestions->map(fn($item) => ['text' => $item, 'type' => 'category']))
            ->merge($userSuggestions->map(fn($item) => ['text' => $item, 'type' => 'user']))
            ->take($limit);

        return $this->successResponse($allSuggestions, 'Search suggestions retrieved successfully');
    }

    /**
     * Get popular search terms.
     */
    public function popular(Request $request): JsonResponse
    {
        $limit = $request->get('limit', 10);
        $period = $request->get('period', 'week'); // week, month, all

        // Get search terms from activity logs
        $query = Activity::where('action', 'global_search_performed');

        if ($period === 'week') {
            $query->where('created_at', '>=', now()->subWeek());
        } elseif ($period === 'month') {
            $query->where('created_at', '>=', now()->subMonth());
        }

        $searchTerms = $query->whereNotNull('properties->query')
            ->select(DB::raw('JSON_UNQUOTE(JSON_EXTRACT(properties, "$.query")) as search_term'))
            ->groupBy('search_term')
            ->orderByRaw('COUNT(*) DESC')
            ->limit($limit)
            ->get()
            ->map(function ($item) {
                return [
                    'term' => $item->search_term,
                    'count' => Activity::where('action', 'global_search_performed')
                        ->where('properties->query', $item->search_term)
                        ->count(),
                ];
            });

        return $this->successResponse($searchTerms, 'Popular search terms retrieved successfully');
    }

    /**
     * Get search trends.
     */
    public function trends(Request $request): JsonResponse
    {
        $days = $request->get('days', 7);

        // Get search trends over time
        $trends = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $date = now()->subDays($i);
            $searchCount = Activity::where('action', 'global_search_performed')
                ->whereDate('created_at', $date)
                ->count();

            $trends[] = [
                'date' => $date->format('Y-m-d'),
                'search_count' => $searchCount,
            ];
        }

        // Get top search terms for the period
        $topTerms = Activity::where('action', 'global_search_performed')
            ->where('created_at', '>=', now()->subDays($days))
            ->whereNotNull('properties->query')
            ->select(DB::raw('JSON_UNQUOTE(JSON_EXTRACT(properties, "$.query")) as search_term'))
            ->groupBy('search_term')
            ->orderByRaw('COUNT(*) DESC')
            ->limit(10)
            ->get()
            ->map(function ($item) use ($days) {
                return [
                    'term' => $item->search_term,
                    'count' => Activity::where('action', 'global_search_performed')
                        ->where('created_at', '>=', now()->subDays($days))
                        ->where('properties->query', $item->search_term)
                        ->count(),
                ];
            });

        return $this->successResponse([
            'period_days' => $days,
            'trends' => $trends,
            'top_terms' => $topTerms,
            'total_searches' => collect($trends)->sum('search_count'),
        ], 'Search trends retrieved successfully');
    }

    /**
     * Search within specific content type.
     */
    public function contentType(Request $request, string $contentTypeSlug): JsonResponse
    {
        $request->validate([
            'q' => 'required|string|min:2|max:255',
            'per_page' => 'sometimes|integer|min:1|max:100',
        ]);

        $contentType = \App\Models\Content\ContentType::where('slug', $contentTypeSlug)->first();

        if (!$contentType) {
            return $this->errorResponse('Content type not found', 404);
        }

        $results = Content::with(['user.profile', 'categories', 'tags'])
            ->published()
            ->where('content_type_id', $contentType->id)
            ->search($request->q)
            ->orderByDesc('published_at')
            ->paginate($request->get('per_page', 15));

        $results->getCollection()->transform(function ($content) use ($request) {
            return [
                'id' => $content->id,
                'title' => $content->title,
                'slug' => $content->slug,
                'excerpt' => $content->getSearchExcerpt('content', $request->q),
                'featured_image_url' => $content->featured_image_url,
                'view_count' => $content->view_count,
                'published_at' => $content->published_at,
                'author' => [
                    'id' => $content->user->id,
                    'name' => $content->user->name,
                ],
                'categories' => $content->categories->pluck('name'),
                'tags' => $content->tags->pluck('name'),
            ];
        });

        return $this->successResponse([
            'content_type' => [
                'id' => $contentType->id,
                'name' => $contentType->name,
                'slug' => $contentType->slug,
            ],
            'results' => $results,
        ], "Search results for {$contentType->name} retrieved successfully");
    }

    /**
     * Export search results.
     */
    public function export(Request $request): JsonResponse
    {
        $request->validate([
            'q' => 'required|string|min:2|max:255',
            'format' => 'sometimes|in:json,csv',
            'limit' => 'sometimes|integer|min:1|max:1000',
        ]);

        $format = $request->get('format', 'json');
        $limit = $request->get('limit', 100);

        // Perform search
        $results = Content::with(['user', 'contentType', 'categories', 'tags'])
            ->published()
            ->search($request->q)
            ->limit($limit)
            ->get();

        $exportData = $results->map(function ($content) {
            return [
                'id' => $content->id,
                'title' => $content->title,
                'slug' => $content->slug,
                'author' => $content->user->name,
                'content_type' => $content->contentType->name,
                'categories' => $content->categories->pluck('name')->implode(', '),
                'tags' => $content->tags->pluck('name')->implode(', '),
                'view_count' => $content->view_count,
                'published_at' => $content->published_at,
            ];
        });

        // Log export
        if (auth()->check()) {
            Activity::log('search_results_exported', null, [
                'query' => $request->q,
                'format' => $format,
                'results_count' => $exportData->count(),
            ]);
        }

        return $this->successResponse([
            'query' => $request->q,
            'format' => $format,
            'results' => $exportData,
            'exported_at' => now()->toISOString(),
            'total_results' => $exportData->count(),
        ], 'Search results exported successfully');
    }

    /**
     * Search content.
     */
    private function searchContent(string $query, int $limit, bool $includePrivate = false): array
    {
        $contentQuery = Content::with(['user.profile', 'contentType'])
            ->search($query)
            ->orderByDesc('view_count');

        if (!$includePrivate) {
            $contentQuery->published();
        }

        return $contentQuery->limit($limit)
            ->get()
            ->map(function ($content) {
                return [
                    'id' => $content->id,
                    'title' => $content->title,
                    'slug' => $content->slug,
                    'excerpt' => $content->excerpt,
                    'type' => 'content',
                    'content_type' => $content->contentType->name,
                    'author' => $content->user->name,
                    'published_at' => $content->published_at,
                ];
            })
            ->toArray();
    }

    /**
     * Search users.
     */
    private function searchUsers(string $query, int $limit): array
    {
        return User::with('profile')
            ->where('name', 'LIKE', "%{$query}%")
            ->where('status', 'active')
            ->limit($limit)
            ->get()
            ->map(function ($user) {
                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'avatar_url' => $user->profile?->avatar_url,
                    'type' => 'user',
                    'content_count' => $user->contents()->published()->count(),
                ];
            })
            ->toArray();
    }

    /**
     * Search media.
     */
    private function searchMedia(string $query, int $limit, bool $includePrivate = false): array
    {
        $mediaQuery = Media::with('user')
            ->where(function ($q) use ($query) {
                $q->where('name', 'LIKE', "%{$query}%")
                  ->orWhere('caption', 'LIKE', "%{$query}%");
            });

        if (!$includePrivate) {
            $mediaQuery->where('is_public', true);
        }

        return $mediaQuery->limit($limit)
            ->get()
            ->map(function ($media) {
                return [
                    'id' => $media->id,
                    'name' => $media->name,
                    'type' => 'media',
                    'media_type' => $media->type,
                    'url' => $media->getUrl(),
                    'thumbnail_url' => $media->getUrl('thumb'),
                    'uploader' => $media->user->name,
                ];
            })
            ->toArray();
    }

    /**
     * Search categories.
     */
    private function searchCategories(string $query, int $limit): array
    {
        return Category::active()
            ->where('name', 'LIKE', "%{$query}%")
            ->orderByDesc('content_count')
            ->limit($limit)
            ->get()
            ->map(function ($category) {
                return [
                    'id' => $category->id,
                    'name' => $category->name,
                    'slug' => $category->slug,
                    'type' => 'category',
                    'content_count' => $category->content_count,
                ];
            })
            ->toArray();
    }

    /**
     * Search tags.
     */
    private function searchTags(string $query, int $limit): array
    {
        return Tag::active()
            ->where('name', 'LIKE', "%{$query}%")
            ->orderByDesc('usage_count')
            ->limit($limit)
            ->get()
            ->map(function ($tag) {
                return [
                    'id' => $tag->id,
                    'name' => $tag->name,
                    'slug' => $tag->slug,
                    'type' => 'tag',
                    'usage_count' => $tag->usage_count,
                ];
            })
            ->toArray();
    }

    /**
     * Build relevance query for sorting.
     */
    private function buildRelevanceQuery(string $query): string
    {
        $terms = explode(' ', $query);
        $conditions = [];

        foreach ($terms as $term) {
            $conditions[] = "CASE WHEN title LIKE '%{$term}%' THEN 10 ELSE 0 END";
            $conditions[] = "CASE WHEN content LIKE '%{$term}%' THEN 5 ELSE 0 END";
            $conditions[] = "CASE WHEN excerpt LIKE '%{$term}%' THEN 3 ELSE 0 END";
        }

        return '(' . implode(' + ', $conditions) . ')';
    }

    /**
     * Calculate relevance score for content.
     */
    private function calculateRelevanceScore($content, string $query): int
    {
        $score = 0;
        $terms = explode(' ', strtolower($query));

        foreach ($terms as $term) {
            // Title matches get highest score
            if (str_contains(strtolower($content->title), $term)) {
                $score += 10;
            }

            // Content matches get medium score
            if (str_contains(strtolower($content->content), $term)) {
                $score += 5;
            }

            // Excerpt matches get low score
            if (str_contains(strtolower($content->excerpt), $term)) {
                $score += 3;
            }
        }

        return $score;
    }
}