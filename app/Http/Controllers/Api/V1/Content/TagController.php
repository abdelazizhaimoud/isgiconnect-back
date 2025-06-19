<?php

namespace App\Http\Controllers\Api\V1\Content;

use App\Http\Controllers\Api\ApiController;
use App\Models\Content\Tag;
use App\Models\Content\Content;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TagController extends ApiController
{
    /**
     * Get all tags.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Tag::active();

        // Search tags
        if ($request->has('search')) {
            $query->where('name', 'LIKE', '%' . $request->search . '%');
        }

        // Filter by minimum usage
        if ($request->has('min_usage')) {
            $query->where('usage_count', '>=', $request->min_usage);
        }

        // Apply sorting
        $sortBy = $request->get('sort_by', 'usage_count');
        $sortDir = $request->get('sort_dir', 'desc');
        $query->orderBy($sortBy, $sortDir);

        $tags = $query->paginate($request->get('per_page', 50));

        $tags->getCollection()->transform(function ($tag) {
            return [
                'id' => $tag->id,
                'name' => $tag->name,
                'slug' => $tag->slug,
                'description' => $tag->description,
                'color' => $tag->color,
                'usage_count' => $tag->usage_count,
            ];
        });

        return $this->successResponse($tags, 'Tags retrieved successfully');
    }

    /**
     * Get popular tags.
     */
    public function popular(Request $request): JsonResponse
    {
        $limit = $request->get('limit', 20);

        $tags = Tag::active()
                  ->popular($limit)
                  ->get();

        $tags->transform(function ($tag) {
            return [
                'id' => $tag->id,
                'name' => $tag->name,
                'slug' => $tag->slug,
                'color' => $tag->color,
                'usage_count' => $tag->usage_count,
            ];
        });

        return $this->successResponse($tags, 'Popular tags retrieved successfully');
    }

    /**
     * Get tag details with content.
     */
    public function show(Request $request, string $slug): JsonResponse
    {
        $tag = Tag::where('slug', $slug)->active()->first();

        if (!$tag) {
            return $this->errorResponse('Tag not found', 404);
        }

        // Get tagged content with pagination
        $contentQuery = $tag->contents()
                           ->with(['user.profile', 'contentType', 'categories'])
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
                'categories' => $item->categories->pluck('name'),
            ];
        });

        $tagData = [
            'id' => $tag->id,
            'name' => $tag->name,
           'slug' => $tag->slug,
           'description' => $tag->description,
           'color' => $tag->color,
           'usage_count' => $tag->usage_count,
           'content' => $content,
       ];

       return $this->successResponse($tagData, 'Tag retrieved successfully');
   }

   /**
    * Get tag cloud data.
    */
   public function cloud(Request $request): JsonResponse
   {
       $limit = $request->get('limit', 50);
       $minUsage = $request->get('min_usage', 1);

       $tags = Tag::active()
                 ->where('usage_count', '>=', $minUsage)
                 ->orderByDesc('usage_count')
                 ->limit($limit)
                 ->get();

       // Calculate tag sizes for cloud visualization
       $maxUsage = $tags->max('usage_count');
       $minSize = 12;
       $maxSize = 32;

       $tagCloud = $tags->map(function ($tag) use ($maxUsage, $minSize, $maxSize) {
           $size = $minSize + (($tag->usage_count / $maxUsage) * ($maxSize - $minSize));
           
           return [
               'id' => $tag->id,
               'name' => $tag->name,
               'slug' => $tag->slug,
               'color' => $tag->color,
               'usage_count' => $tag->usage_count,
               'size' => round($size),
               'weight' => round($tag->usage_count / $maxUsage, 2),
           ];
       });

       return $this->successResponse($tagCloud, 'Tag cloud retrieved successfully');
   }

   /**
    * Get related tags.
    */
   public function related(string $slug): JsonResponse
   {
       $tag = Tag::where('slug', $slug)->active()->first();

       if (!$tag) {
           return $this->errorResponse('Tag not found', 404);
       }

       // Find tags that appear together with this tag
       $relatedTags = Tag::whereHas('contents', function ($query) use ($tag) {
           $query->whereHas('tags', function ($q) use ($tag) {
               $q->where('tags.id', $tag->id);
           });
       })
       ->where('id', '!=', $tag->id)
       ->withCount(['contents' => function ($query) use ($tag) {
           $query->whereHas('tags', function ($q) use ($tag) {
               $q->where('tags.id', $tag->id);
           });
       }])
       ->orderByDesc('contents_count')
       ->limit(10)
       ->get();

       $relatedTags->transform(function ($relatedTag) {
           return [
               'id' => $relatedTag->id,
               'name' => $relatedTag->name,
               'slug' => $relatedTag->slug,
               'color' => $relatedTag->color,
               'usage_count' => $relatedTag->usage_count,
               'relation_strength' => $relatedTag->contents_count,
           ];
       });

       return $this->successResponse($relatedTags, 'Related tags retrieved successfully');
   }

   /**
    * Suggest tags based on content.
    */
   public function suggest(Request $request): JsonResponse
   {
       $request->validate([
           'content' => 'required|string|min:10',
           'limit' => 'sometimes|integer|min:1|max:20',
       ]);

       $content = strtolower($request->content);
       $limit = $request->get('limit', 10);

       // Simple tag suggestion based on keyword matching
       $tags = Tag::active()
                 ->where(function ($query) use ($content) {
                     $words = explode(' ', $content);
                     foreach ($words as $word) {
                         if (strlen($word) > 3) {
                             $query->orWhere('name', 'LIKE', '%' . $word . '%');
                         }
                     }
                 })
                 ->orderByDesc('usage_count')
                 ->limit($limit)
                 ->get();

       $suggestions = $tags->map(function ($tag) {
           return [
               'id' => $tag->id,
               'name' => $tag->name,
               'slug' => $tag->slug,
               'usage_count' => $tag->usage_count,
           ];
       });

       return $this->successResponse($suggestions, 'Tag suggestions retrieved successfully');
   }

   /**
    * Get trending tags.
    */
   public function trending(Request $request): JsonResponse
   {
       $days = $request->get('days', 7);
       $limit = $request->get('limit', 15);

       // Get tags from content published in the last X days
       $trendingTags = Tag::whereHas('contents', function ($query) use ($days) {
           $query->published()
                 ->where('published_at', '>=', now()->subDays($days));
       })
       ->withCount(['contents' => function ($query) use ($days) {
           $query->published()
                 ->where('published_at', '>=', now()->subDays($days));
       }])
       ->orderByDesc('contents_count')
       ->limit($limit)
       ->get();

       $trendingTags->transform(function ($tag) {
           return [
               'id' => $tag->id,
               'name' => $tag->name,
               'slug' => $tag->slug,
               'color' => $tag->color,
               'usage_count' => $tag->usage_count,
               'trending_score' => $tag->contents_count,
           ];
       });

       return $this->successResponse($trendingTags, 'Trending tags retrieved successfully');
   }

   /**
    * Get tag statistics.
    */
   public function statistics(string $slug): JsonResponse
   {
       $tag = Tag::where('slug', $slug)->active()->first();

       if (!$tag) {
           return $this->errorResponse('Tag not found', 404);
       }

       $stats = [
           'total_content' => $tag->contents()->count(),
           'published_content' => $tag->contents()->published()->count(),
           'total_views' => $tag->contents()->sum('view_count'),
           'total_likes' => $tag->contents()->sum('like_count'),
           'average_views' => round($tag->contents()->avg('view_count'), 2),
           'top_authors' => $tag->contents()
                              ->published()
                              ->with('user')
                              ->select('user_id')
                              ->groupBy('user_id')
                              ->orderByRaw('COUNT(*) DESC')
                              ->limit(5)
                              ->get()
                              ->map(function ($content) use ($tag) {
                                  return [
                                      'id' => $content->user->id,
                                      'name' => $content->user->name,
                                      'content_count' => $content->user->contents()
                                          ->whereHas('tags', function ($q) use ($tag) {
                                              $q->where('tags.id', $tag->id);
                                          })
                                          ->published()
                                          ->count(),
                                  ];
                              }),
           'content_types' => $tag->contents()
                                ->published()
                                ->with('contentType')
                                ->get()
                                ->groupBy('contentType.name')
                                ->map(function ($items, $typeName) {
                                    return [
                                        'type' => $typeName,
                                        'count' => $items->count(),
                                    ];
                                })
                                ->values(),
           'recent_activity' => [
               'this_week' => $tag->contents()
                                ->published()
                                ->where('published_at', '>=', now()->startOfWeek())
                                ->count(),
               'this_month' => $tag->contents()
                                 ->published()
                                 ->where('published_at', '>=', now()->startOfMonth())
                                 ->count(),
           ],
       ];

       return $this->successResponse($stats, 'Tag statistics retrieved successfully');
   }
}