<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Api\ApiController;
use App\Models\Content\Content;
use App\Models\User\User;
use App\Models\Media\Media;
use App\Models\System\Activity;
use App\Models\System\Notification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends ApiController
{
    /**
     * Get dashboard overview data.
     */
    public function index(Request $request): JsonResponse
    {
        $data = [
            'stats' => $this->getStats(),
            'recent_activities' => $this->getRecentActivities(),
            'recent_content' => $this->getRecentContent(),
            'system_info' => $this->getSystemInfo(),
            'charts' => $this->getChartData($request),
        ];

        return $this->successResponse($data, 'Dashboard data retrieved successfully');
    }

    /**
     * Get general statistics.
     */
    public function stats(): JsonResponse
    {
        $stats = $this->getStats();
        return $this->successResponse($stats, 'Statistics retrieved successfully');
    }

    /**
     * Get recent activities.
     */
    public function activities(Request $request): JsonResponse
    {
        $limit = $request->get('limit', 20);
        
        $activities = Activity::with(['user', 'subject'])
            ->latest('created_at')
            ->limit($limit)
            ->get()
            ->map(function ($activity) {
                return [
                    'id' => $activity->id,
                    'action' => $activity->action,
                    'description' => $activity->description,
                    'user' => $activity->user ? [
                        'id' => $activity->user->id,
                        'name' => $activity->user->name,
                    ] : null,
                    'subject_type' => $activity->subject_type,
                    'subject_id' => $activity->subject_id,
                    'created_at' => $activity->created_at,
                ];
            });

        return $this->successResponse($activities, 'Recent activities retrieved successfully');
    }

    /**
     * Get system status.
     */
    public function systemStatus(): JsonResponse
    {
        $status = [
            'database' => $this->checkDatabaseConnection(),
            'storage' => $this->checkStorageStatus(),
            'cache' => $this->checkCacheStatus(),
            'queue' => $this->checkQueueStatus(),
            'mail' => $this->checkMailStatus(),
        ];

        return $this->successResponse($status, 'System status retrieved successfully');
    }

    /**
     * Get general statistics.
     */
    private function getStats(): array
    {
        return [
            'users' => [
                'total' => User::count(),
                'active' => User::where('status', 'active')->count(),
                'new_this_month' => User::where('created_at', '>=', now()->startOfMonth())->count(),
            ],
            'content' => [
                'total' => Content::count(),
                'published' => Content::where('status', 'published')->count(),
                'draft' => Content::where('status', 'draft')->count(),
                'new_this_month' => Content::where('created_at', '>=', now()->startOfMonth())->count(),
            ],
            'media' => [
                'total' => Media::count(),
                'total_size' => Media::sum('size'),
                'images' => Media::where('mime_type', 'LIKE', 'image/%')->count(),
                'videos' => Media::where('mime_type', 'LIKE', 'video/%')->count(),
            ],
            'activities' => [
                'today' => Activity::whereDate('created_at', today())->count(),
                'this_week' => Activity::where('created_at', '>=', now()->startOfWeek())->count(),
                'this_month' => Activity::where('created_at', '>=', now()->startOfMonth())->count(),
            ],
        ];
    }

    /**
     * Get recent activities.
     */
    private function getRecentActivities(): array
    {
        return Activity::with(['user', 'subject'])
            ->latest('created_at')
            ->limit(10)
            ->get()
            ->map(function ($activity) {
                return [
                    'id' => $activity->id,
                    'action' => $activity->action,
                    'description' => $activity->description,
                    'user_name' => $activity->user?->name ?? 'System',
                    'created_at' => $activity->created_at,
                ];
            })
            ->toArray();
    }

    /**
     * Get recent content.
     */
    private function getRecentContent(): array
    {
        return Content::with(['user', 'contentType'])
            ->latest('created_at')
            ->limit(10)
            ->get()
            ->map(function ($content) {
                return [
                    'id' => $content->id,
                    'title' => $content->title,
                    'status' => $content->status,
                    'author' => $content->user->name,
                    'type' => $content->contentType->name,
                    'created_at' => $content->created_at,
                ];
            })
            ->toArray();
    }

    /**
     * Get system information.
     */
    private function getSystemInfo(): array
    {
        return [
            'laravel_version' => app()->version(),
            'php_version' => PHP_VERSION,
            'server_time' => now()->toISOString(),
            'timezone' => config('app.timezone'),
            'environment' => app()->environment(),
            'debug_mode' => config('app.debug'),
        ];
    }

    /**
     * Get chart data.
     */
    private function getChartData(Request $request): array
    {
        $period = $request->get('period', '7days');
        
        return [
            'users_growth' => $this->getUsersGrowthData($period),
            'content_creation' => $this->getContentCreationData($period),
            'activity_timeline' => $this->getActivityTimelineData($period),
        ];
    }

    /**
     * Get users growth data.
     */
    private function getUsersGrowthData(string $period): array
    {
        $days = $this->getPeriodDays($period);
        $data = [];
        
        for ($i = $days - 1; $i >= 0; $i--) {
            $date = now()->subDays($i);
            $count = User::whereDate('created_at', $date)->count();
            
            $data[] = [
                'date' => $date->format('Y-m-d'),
                'count' => $count,
            ];
        }
        
        return $data;
    }

    /**
     * Get content creation data.
     */
    private function getContentCreationData(string $period): array
    {
        $days = $this->getPeriodDays($period);
        $data = [];
        
        for ($i = $days - 1; $i >= 0; $i--) {
            $date = now()->subDays($i);
            $count = Content::whereDate('created_at', $date)->count();
            
            $data[] = [
                'date' => $date->format('Y-m-d'),
                'count' => $count,
            ];
        }
        
        return $data;
    }

    /**
     * Get activity timeline data.
     */
    private function getActivityTimelineData(string $period): array
    {
        $days = $this->getPeriodDays($period);
        $data = [];
        
        for ($i = $days - 1; $i >= 0; $i--) {
            $date = now()->subDays($i);
            $count = Activity::whereDate('created_at', $date)->count();
            
            $data[] = [
                'date' => $date->format('Y-m-d'),
                'count' => $count,
            ];
        }
        
        return $data;
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
            default => 7,
        };
    }

    /**
     * Check database connection.
     */
    private function checkDatabaseConnection(): array
    {
        try {
            \DB::connection()->getPdo();
            return ['status' => 'connected', 'message' => 'Database connection successful'];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => 'Database connection failed'];
        }
    }

    /**
     * Check storage status.
     */
    private function checkStorageStatus(): array
    {
        try {
            $diskSpace = disk_free_space(storage_path());
            $totalSpace = disk_total_space(storage_path());
            $usedSpace = $totalSpace - $diskSpace;
            $usagePercent = round(($usedSpace / $totalSpace) * 100, 2);
            
            return [
                'status' => $usagePercent < 90 ? 'healthy' : 'warning',
                'usage_percent' => $usagePercent,
                'free_space' => $this->formatBytes($diskSpace),
                'total_space' => $this->formatBytes($totalSpace),
            ];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => 'Storage check failed'];
        }
    }

    /**
     * Check cache status.
     */
    private function checkCacheStatus(): array
    {
        try {
            \Cache::put('test_key', 'test_value', 60);
            $value = \Cache::get('test_key');
            \Cache::forget('test_key');
            
            return [
                'status' => $value === 'test_value' ? 'working' : 'error',
                'driver' => config('cache.default'),
            ];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => 'Cache check failed'];
        }
    }

    /**
     * Check queue status.
     */
    private function checkQueueStatus(): array
    {
        try {
            return [
                'status' => 'configured',
                'driver' => config('queue.default'),
                'connection' => config('queue.connections.' . config('queue.default')),
            ];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => 'Queue check failed'];
        }
    }

    /**
     * Check mail status.
     */
    private function checkMailStatus(): array
    {
        try {
            return [
                'status' => 'configured',
                'driver' => config('mail.default'),
                'from_address' => config('mail.from.address'),
            ];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => 'Mail check failed'];
        }
    }

    /**
     * Format bytes to human readable format.
     */
    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, 2) . ' ' . $units[$i];
    }
}