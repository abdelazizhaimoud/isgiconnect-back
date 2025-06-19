<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Api\ApiController;
use App\Models\User\User;
use App\Models\Content\Content;
use App\Models\Content\Category;
use App\Models\Content\Tag;
use App\Models\Media\Media;
use App\Models\System\Activity;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AnalyticsController extends ApiController
{
    /**
     * Get analytics overview.
     */
    public function overview(Request $request): JsonResponse
    {
        $period = $request->get('period', '30days');
        $startDate = $this->getStartDate($period);

        $data = [
            'summary' => $this->getSummaryStats($startDate),
            'trends' => $this->getTrendData($period),
            'top_content' => $this->getTopContent(),
            'top_categories' => $this->getTopCategories(),
            'top_tags' => $this->getTopTags(),
            'user_engagement' => $this->getUserEngagement($startDate),
            'content_performance' => $this->getContentPerformance($startDate),
        ];

        return $this->successResponse($data, 'Analytics overview retrieved successfully');
    }

    /**
     * Get user analytics.
     */
    public function users(Request $request): JsonResponse
    {
        $period = $request->get('period', '30days');
        $startDate = $this->getStartDate($period);

        $data = [
            'registration_trends' => $this->getUserRegistrationTrends($period),
            'activity_trends' => $this->getUserActivityTrends($period),
            'user_distribution' => $this->getUserDistribution(),
            'role_distribution' => $this->getRoleDistribution(),
            'engagement_metrics' => $this->getUserEngagementMetrics($startDate),
            'retention_data' => $this->getUserRetentionData(),
        ];

        return $this->successResponse($data, 'User analytics retrieved successfully');
    }

    /**
     * Get content analytics.
     */
    public function content(Request $request): JsonResponse
    {
        $period = $request->get('period', '30days');
        $startDate = $this->getStartDate($period);

        $data = [
            'creation_trends' => $this->getContentCreationTrends($period),
            'view_trends' => $this->getContentViewTrends($period),
            'status_distribution' => $this->getContentStatusDistribution(),
            'type_distribution' => $this->getContentTypeDistribution(),
            'category_performance' => $this->getCategoryPerformance($startDate),
            'tag_usage' => $this->getTagUsage($startDate),
            'author_productivity' => $this->getAuthorProductivity($startDate),
        ];

        return $this->successResponse($data, 'Content analytics retrieved successfully');
    }

    /**
     * Get media analytics.
     */
    public function media(Request $request): JsonResponse
    {
        $period = $request->get('period', '30days');
        $startDate = $this->getStartDate($period);

        $data = [
            'upload_trends' => $this->getMediaUploadTrends($period),
            'storage_usage' => $this->getStorageUsage(),
            'type_distribution' => $this->getMediaTypeDistribution(),
            'size_analysis' => $this->getMediaSizeAnalysis(),
            'popular_media' => $this->getPopularMedia($startDate),
        ];

        return $this->successResponse($data, 'Media analytics retrieved successfully');
    }

    /**
     * Get system analytics.
     */
    public function system(Request $request): JsonResponse
    {
        $period = $request->get('period', '30days');
        $startDate = $this->getStartDate($period);

        $data = [
            'activity_trends' => $this->getSystemActivityTrends($period),
            'performance_metrics' => $this->getPerformanceMetrics(),
            'error_analysis' => $this->getErrorAnalysis($startDate),
            'resource_usage' => $this->getResourceUsage(),
        ];

        return $this->successResponse($data, 'System analytics retrieved successfully');
    }

    /**
     * Get custom analytics.
     */
    public function custom(Request $request): JsonResponse
    {
        $request->validate([
            'metrics' => 'required|array',
            'metrics.*' => 'string|in:users,content,media,activities,views,registrations',
            'period' => 'string|in:7days,30days,90days,1year',
            'start_date' => 'date',
            'end_date' => 'date|after:start_date',
        ]);

        $startDate = $request->start_date ?? $this->getStartDate($request->get('period', '30days'));
        $endDate = $request->end_date ?? now();

        $data = [];

        foreach ($request->metrics as $metric) {
            switch ($metric) {
                case 'users':
                    $data['users'] = $this->getCustomUserMetrics($startDate, $endDate);
                    break;
                case 'content':
                    $data['content'] = $this->getCustomContentMetrics($startDate, $endDate);
                    break;
                case 'media':
                    $data['media'] = $this->getCustomMediaMetrics($startDate, $endDate);
                    break;
                case 'activities':
                    $data['activities'] = $this->getCustomActivityMetrics($startDate, $endDate);
                    break;
            }
        }

        return $this->successResponse($data, 'Custom analytics retrieved successfully');
    }

    /**
     * Export analytics data.
     */
    public function export(Request $request): JsonResponse
    {
        $request->validate([
            'type' => 'required|in:overview,users,content,media,system',
            'format' => 'string|in:json,csv',
            'period' => 'string|in:7days,30days,90days,1year',
        ]);

        $type = $request->type;
        $format = $request->get('format', 'json');
        $period = $request->get('period', '30days');

        $data = match($type) {
            'overview' => $this->overview($request)->getData(true)['data'],
            'users' => $this->users($request)->getData(true)['data'],
            'content' => $this->content($request)->getData(true)['data'],
            'media' => $this->media($request)->getData(true)['data'],
            'system' => $this->system($request)->getData(true)['data'],
        };

        // Log export activity
        Activity::logCustom(
            'analytics_exported',
            "Analytics data exported: {$type}",
            null,
            [
                'type' => $type,
                'format' => $format,
                'period' => $period,
                'admin_id' => auth()->id(),
            ]
        );

        return $this->successResponse([
            'data' => $data,
            'type' => $type,
            'format' => $format,
            'period' => $period,
            'exported_at' => now()->toISOString(),
        ], 'Analytics data exported successfully');
    }

    /**
     * Get summary statistics.
     */
    private function getSummaryStats($startDate): array
    {
        return [
            'total_users' => User::count(),
            'new_users' => User::where('created_at', '>=', $startDate)->count(),
            'total_content' => Content::count(),
            'new_content' => Content::where('created_at', '>=', $startDate)->count(),
            'total_views' => Content::sum('view_count'),
            'total_media' => Media::count(),
            'storage_used' => Media::sum('size'),
            'total_activities' => Activity::where('created_at', '>=', $startDate)->count(),
        ];
    }

    /**
     * Get trend data.
     */
    private function getTrendData(string $period): array
    {
        $days = $this->getPeriodDays($period);
        $data = [];

        for ($i = $days - 1; $i >= 0; $i--) {
            $date = now()->subDays($i);
            
            $data[] = [
                'date' => $date->format('Y-m-d'),
                'users' => User::whereDate('created_at', $date)->count(),
                'content' => Content::whereDate('created_at', $date)->count(),
                'activities' => Activity::whereDate('created_at', $date)->count(),
            ];
        }

        return $data;
    }

    /**
     * Get top content by views.
     */
    private function getTopContent(int $limit = 10): array
    {
        return Content::with(['user', 'contentType'])
            ->where('status', 'published')
            ->orderByDesc('view_count')
            ->limit($limit)
            ->get()
            ->map(function ($content) {
                return [
                    'id' => $content->id,
                    'title' => $content->title,
                    'views' => $content->view_count,
                    'author' => $content->user->name,
                    'type' => $content->contentType->name,
                    'published_at' => $content->published_at,
                ];
            })
            ->toArray();
    }

    /**
     * Get top categories.
     */
    private function getTopCategories(int $limit = 10): array
    {
        return Category::withCount('contents')
            ->orderByDesc('contents_count')
            ->limit($limit)
            ->get()
            ->map(function ($category) {
                return [
                    'id' => $category->id,
                    'name' => $category->name,
                    'content_count' => $category->contents_count,
                ];
            })
            ->toArray();
    }

    /**
     * Get top tags.
     */
    private function getTopTags(int $limit = 10): array
    {
        return Tag::orderByDesc('usage_count')
            ->limit($limit)
            ->get()
            ->map(function ($tag) {
                return [
                    'id' => $tag->id,
                    'name' => $tag->name,
                    'usage_count' => $tag->usage_count,
                ];
            })
            ->toArray();
    }

    /**
     * Get user engagement metrics.
     */
    private function getUserEngagement($startDate): array
    {
        return [
            'active_users' => User::whereHas('activities', function ($query) use ($startDate) {
                $query->where('created_at', '>=', $startDate);
            })->count(),
            'content_creators' => User::whereHas('contents', function ($query) use ($startDate) {
                $query->where('created_at', '>=', $startDate);
            })->count(),
            'avg_activities_per_user' => round(
                Activity::where('created_at', '>=', $startDate)->count() / max(User::count(), 1),
                2
            ),
        ];
    }

    /**
     * Get content performance.
     */
    private function getContentPerformance($startDate): array
    {
        $content = Content::where('created_at', '>=', $startDate);
        
        return [
            'avg_views_per_content' => round($content->avg('view_count'), 2),
            'most_viewed_day' => Content::select(
                DB::raw('DATE(created_at) as date'),
                DB::raw('SUM(view_count) as total_views')
            )
            ->where('created_at', '>=', $startDate)
            ->groupBy('date')
            ->orderByDesc('total_views')
            ->first()?->date,
        ];
    }

    /**
     * Get user registration trends.
     */
    private function getUserRegistrationTrends(string $period): array
    {
        $days = $this->getPeriodDays($period);
        $data = [];

        for ($i = $days - 1; $i >= 0; $i--) {
            $date = now()->subDays($i);
            $count = User::whereDate('created_at', $date)->count();
            
            $data[] = [
                'date' => $date->format('Y-m-d'),
                'registrations' => $count,
            ];
        }

        return $data;
    }

    /**
     * Get user activity trends.
     */
    private function getUserActivityTrends(string $period): array
    {
        $days = $this->getPeriodDays($period);
        $data = [];

        for ($i = $days - 1; $i >= 0; $i--) {
            $date = now()->subDays($i);
            $activeUsers = Activity::whereDate('created_at', $date)
                ->distinct('user_id')
                ->count('user_id');
            
            $data[] = [
                'date' => $date->format('Y-m-d'),
                'active_users' => $activeUsers,
            ];
        }

        return $data;
    }

    /**
     * Get user distribution by status.
     */
    private function getUserDistribution(): array
    {
        return User::select('status', DB::raw('count(*) as count'))
            ->groupBy('status')
            ->get()
            ->map(function ($item) {
                return [
                    'status' => $item->status,
                    'count' => $item->count,
                ];
            })
            ->toArray();
    }

    /**
     * Get role distribution.
     */
    private function getRoleDistribution(): array
    {
        return DB::table('role_user')
            ->join('roles', 'role_user.role_id', '=', 'roles.id')
            ->select('roles.name', DB::raw('count(*) as count'))
            ->groupBy('roles.name')
            ->get()
            ->map(function ($item) {
                return [
                    'role' => $item->name,
                    'count' => $item->count,
                ];
            })
            ->toArray();
    }

    /**
     * Get start date based on period.
     */
    private function getStartDate(string $period): \Carbon\Carbon
    {
        return match($period) {
            '7days' => now()->subDays(7),
            '30days' => now()->subDays(30),
            '90days' => now()->subDays(90),
            '1year' => now()->subYear(),
            default => now()->subDays(30),
        };
    }

    /**
     * Get number of days for period.
     */
    private function getPeriodDays(string $period): int
    {
        return match($period) {
            '7days' => 7,
            '30days' => 30,
            '90days' => 90,
            '1year' => 365,
            default => 30,
        };
    }

    // Additional helper methods for other analytics would go here...
    // (getUserEngagementMetrics, getContentCreationTrends, etc.)
}