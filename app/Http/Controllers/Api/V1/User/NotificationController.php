<?php

namespace App\Http\Controllers\Api\V1\User;

use App\Http\Controllers\Api\ApiController;
use App\Models\System\Notification;
use App\Models\System\Activity;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Events\TestEvent;

class NotificationController extends ApiController
{
    /**
     * Get user's notifications.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        
        $query = $user->notifications();

        // Apply filters
        if ($request->has('read')) {
            if ($request->boolean('read')) {
                $query->read();
            } else {
                $query->unread();
            }
        }

        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        if ($request->has('priority')) {
            $query->byPriority($request->priority);
        }

        if ($request->has('channel')) {
            $query->byChannel($request->channel);
        }

        // Apply sorting
        $sortBy = $request->get('sort_by', 'created_at');
        $sortDir = $request->get('sort_dir', 'desc');
        $query->orderBy($sortBy, $sortDir);

        $notifications = $query->paginate($request->get('per_page', 20));

        $notifications->getCollection()->transform(function ($notification) {
            return [
                'id' => $notification->id,
                'type' => $notification->type,
                'title' => $notification->title,
                'message' => $notification->message,
                'url' => $notification->url,
                'icon' => $notification->icon,
                'priority' => $notification->priority,
                'priority_color' => $notification->priority_color,
                'channel' => $notification->channel,
                'is_read' => $notification->isRead(),
                'read_at' => $notification->read_at,
                'created_at' => $notification->created_at,
                'data' => $notification->data,
            ];
        });

        return $this->successResponse($notifications, 'Notifications retrieved successfully');
    }

    /**
     * Get unread notifications count.
     */
    public function unreadCount(Request $request): JsonResponse
    {
        $user = $request->user();
        $count = $user->unreadNotifications()->count();

        return $this->successResponse(['count' => $count], 'Unread notifications count retrieved');
    }

    /**
     * Get notification by ID.
     */
    public function show(Request $request, string $notificationId): JsonResponse
    {
        $user = $request->user();
        
        $notification = $user->notifications()
                            ->where('id', $notificationId)
                            ->first();

        if (!$notification) {
            return $this->errorResponse('Notification not found', 404);
        }

        $notificationData = [
            'id' => $notification->id,
            'type' => $notification->type,
            'title' => $notification->title,
            'message' => $notification->message,
            'url' => $notification->url,
            'icon' => $notification->icon,
            'priority' => $notification->priority,
            'priority_color' => $notification->priority_color,
            'channel' => $notification->channel,
            'is_read' => $notification->isRead(),
            'read_at' => $notification->read_at,
            'sent_at' => $notification->sent_at,
            'created_at' => $notification->created_at,
            'data' => $notification->data,
            'metadata' => $notification->metadata,
        ];

        return $this->successResponse($notificationData, 'Notification retrieved successfully');
    }

    /**
     * Mark notification as read.
     */
    public function markAsRead(Request $request, string $notificationId): JsonResponse
    {
        $user = $request->user();
        
        $notification = $user->notifications()
                            ->where('id', $notificationId)
                            ->first();

        if (!$notification) {
            return $this->errorResponse('Notification not found', 404);
        }

        if ($notification->isUnread()) {
            $notification->markAsRead();
            
            // Log activity
            Activity::log('notification_read', null, [
                'notification_id' => $notification->id,
                'notification_type' => $notification->type,
            ]);
        }

        return $this->successResponse(null, 'Notification marked as read');
    }

    /**
     * Mark notification as unread.
     */
    public function markAsUnread(Request $request, string $notificationId): JsonResponse
    {
        $user = $request->user();
        
        $notification = $user->notifications()
                            ->where('id', $notificationId)
                            ->first();

        if (!$notification) {
            return $this->errorResponse('Notification not found', 404);
        }

        if ($notification->isRead()) {
            $notification->markAsUnread();
            
            // Log activity
            Activity::log('notification_unread', null, [
                'notification_id' => $notification->id,
                'notification_type' => $notification->type,
            ]);
        }

        return $this->successResponse(null, 'Notification marked as unread');
    }

    /**
     * Mark all notifications as read.
     */
    public function markAllAsRead(Request $request): JsonResponse
    {
        $user = $request->user();
        
        $unreadCount = $user->unreadNotifications()->count();
        $user->unreadNotifications()->update(['read_at' => now()]);

        // Log activity
        Activity::log('all_notifications_read', null, [
            'marked_count' => $unreadCount,
        ]);

        return $this->successResponse([
            'marked_count' => $unreadCount,
        ], 'All notifications marked as read');
    }

    /**
     * Delete notification.
     */
    public function destroy(Request $request, string $notificationId): JsonResponse
    {
        $user = $request->user();
        
        $notification = $user->notifications()
                            ->where('id', $notificationId)
                            ->first();

        if (!$notification) {
            return $this->errorResponse('Notification not found', 404);
        }

        // Log activity before deletion
        Activity::log('notification_deleted', null, [
            'notification_id' => $notification->id,
            'notification_type' => $notification->type,
        ]);

        $notification->delete();

        return $this->successResponse(null, 'Notification deleted successfully');
    }

    /**
     * Bulk mark notifications as read.
     */
    public function bulkMarkAsRead(Request $request): JsonResponse
    {
        $request->validate([
            'notification_ids' => 'required|array',
            'notification_ids.*' => 'string',
        ]);

        $user = $request->user();
        $notificationIds = $request->notification_ids;

        $notifications = $user->notifications()
                             ->whereIn('id', $notificationIds)
                             ->unread()
                             ->get();

        $markedCount = 0;
        foreach ($notifications as $notification) {
            $notification->markAsRead();
            $markedCount++;
        }

        // Log activity
        Activity::log('bulk_notifications_read', null, [
            'marked_count' => $markedCount,
            'notification_ids' => $notificationIds,
        ]);

        return $this->successResponse([
            'marked_count' => $markedCount,
        ], 'Notifications marked as read');
    }

    /**
     * Bulk delete notifications.
     */
    public function bulkDelete(Request $request): JsonResponse
    {
        $request->validate([
            'notification_ids' => 'required|array',
            'notification_ids.*' => 'string',
        ]);

        $user = $request->user();
        $notificationIds = $request->notification_ids;

        $deletedCount = $user->notifications()
                            ->whereIn('id', $notificationIds)
                            ->delete();

        // Log activity
        Activity::log('bulk_notifications_deleted', null, [
            'deleted_count' => $deletedCount,
            'notification_ids' => $notificationIds,
        ]);

        return $this->successResponse([
            'deleted_count' => $deletedCount,
        ], 'Notifications deleted successfully');
    }

    /**
     * Clear all read notifications.
     */
    public function clearRead(Request $request): JsonResponse
    {
        $user = $request->user();
        
        $deletedCount = $user->notifications()
                            ->read()
                            ->delete();

        // Log activity
        Activity::log('read_notifications_cleared', null, [
            'deleted_count' => $deletedCount,
        ]);

        return $this->successResponse([
            'deleted_count' => $deletedCount,
        ], 'Read notifications cleared successfully');
    }

    /**
     * Clear all notifications.
     */
    public function clearAll(Request $request): JsonResponse
    {
        $user = $request->user();
        
        $deletedCount = $user->notifications()->delete();

        // Log activity
        Activity::log('all_notifications_cleared', null, [
            'deleted_count' => $deletedCount,
       ]);

       return $this->successResponse([
           'deleted_count' => $deletedCount,
       ], 'All notifications cleared successfully');
   }

   /**
    * Get notification preferences.
    */
   public function preferences(Request $request): JsonResponse
   {
       $user = $request->user();
       
       $preferences = $user->profile?->preferences['notifications'] ?? [
           'email_notifications' => true,
           'push_notifications' => true,
           'in_app_notifications' => true,
           'marketing_emails' => false,
           'content_updates' => true,
           'comment_notifications' => true,
           'like_notifications' => true,
           'follow_notifications' => true,
           'system_notifications' => true,
           'security_alerts' => true,
       ];

       return $this->successResponse($preferences, 'Notification preferences retrieved');
   }

   /**
    * Update notification preferences.
    */
   public function updatePreferences(Request $request): JsonResponse
   {
       $request->validate([
           'email_notifications' => 'boolean',
           'push_notifications' => 'boolean',
           'in_app_notifications' => 'boolean',
           'marketing_emails' => 'boolean',
           'content_updates' => 'boolean',
           'comment_notifications' => 'boolean',
           'like_notifications' => 'boolean',
           'follow_notifications' => 'boolean',
           'system_notifications' => 'boolean',
           'security_alerts' => 'boolean',
       ]);

       $user = $request->user();
       
       // Get current preferences
       $currentPreferences = $user->profile?->preferences ?? [];
       $notificationPreferences = $currentPreferences['notifications'] ?? [];
       
       // Update notification preferences
       $notificationPreferences = array_merge($notificationPreferences, $request->only([
           'email_notifications',
           'push_notifications',
           'in_app_notifications',
           'marketing_emails',
           'content_updates',
           'comment_notifications',
           'like_notifications',
           'follow_notifications',
           'system_notifications',
           'security_alerts',
       ]));

       // Update user preferences
       $currentPreferences['notifications'] = $notificationPreferences;

       $user->profile()->updateOrCreate(
           ['user_id' => $user->id],
           ['preferences' => $currentPreferences]
       );

       // Log activity
       Activity::log('notification_preferences_updated', null, [
           'updated_preferences' => array_keys($request->only([
               'email_notifications',
               'push_notifications',
               'in_app_notifications',
               'marketing_emails',
               'content_updates',
               'comment_notifications',
               'like_notifications',
               'follow_notifications',
               'system_notifications',
               'security_alerts',
           ])),
       ]);

       return $this->successResponse($notificationPreferences, 'Notification preferences updated');
   }

   /**
    * Send a test notification.
    */
   public function sendTest(Request $request): JsonResponse
   {
       $request->validate([
           'type' => 'required|in:email,push,in_app',
           'message' => 'sometimes|string|max:255',
       ]);

       $user = $request->user();
       $type = $request->type;
       $message = $request->get('message', 'This is a test notification to verify your settings.');

       switch ($type) {
           case 'in_app':
               // Create in-app notification
               Notification::send($user, 'test_notification', [
                   'title' => 'Test Notification',
                   'message' => $message,
                   'icon' => 'test',
               ], [
                   'channel' => 'database',
                   'priority' => 'normal',
               ]);
               break;

           case 'email':
               // Send test email (would implement actual email sending)
               Log::info("Test email notification would be sent to {$user->email}: {$message}");
               break;

           case 'push':
               // Send test push notification (would implement actual push notification)
               Log::info("Test push notification would be sent to user {$user->id}: {$message}");
               break;
       }

       // Log activity
       Activity::log('test_notification_sent', null, [
           'notification_type' => $type,
           'message' => $message,
       ]);

       return $this->successResponse(null, "Test {$type} notification sent successfully");
   }

   /**
    * Get notification statistics.
    */
   public function statistics(Request $request): JsonResponse
   {
       $user = $request->user();

       $stats = [
           'total_notifications' => $user->notifications()->count(),
           'unread_notifications' => $user->unreadNotifications()->count(),
           'read_notifications' => $user->notifications()->read()->count(),
           'notifications_by_priority' => [
               'urgent' => $user->notifications()->byPriority('urgent')->count(),
               'high' => $user->notifications()->byPriority('high')->count(),
               'normal' => $user->notifications()->byPriority('normal')->count(),
               'low' => $user->notifications()->byPriority('low')->count(),
           ],
           'notifications_by_channel' => [
               'database' => $user->notifications()->byChannel('database')->count(),
               'mail' => $user->notifications()->byChannel('mail')->count(),
               'slack' => $user->notifications()->byChannel('slack')->count(),
           ],
           'recent_activity' => [
               'today' => $user->notifications()->whereDate('created_at', today())->count(),
               'this_week' => $user->notifications()->where('created_at', '>=', now()->startOfWeek())->count(),
               'this_month' => $user->notifications()->where('created_at', '>=', now()->startOfMonth())->count(),
           ],
           'most_recent' => $user->notifications()->latest()->first()?->created_at,
       ];

       return $this->successResponse($stats, 'Notification statistics retrieved');
   }

   /**
    * Get notification types and their descriptions.
    */
   public function types(): JsonResponse
   {
       $types = [
           [
               'type' => 'content_published',
               'name' => 'Content Published',
               'description' => 'When your content is published or approved',
               'category' => 'content',
               'default_enabled' => true,
           ],
           [
               'type' => 'comment_received',
               'name' => 'New Comment',
               'description' => 'When someone comments on your content',
               'category' => 'engagement',
               'default_enabled' => true,
           ],
           [
               'type' => 'content_liked',
               'name' => 'Content Liked',
               'description' => 'When someone likes your content',
               'category' => 'engagement',
               'default_enabled' => true,
           ],
           [
               'type' => 'user_followed',
               'name' => 'New Follower',
               'description' => 'When someone follows you',
               'category' => 'social',
               'default_enabled' => true,
           ],
           [
               'type' => 'content_featured',
               'name' => 'Content Featured',
               'description' => 'When your content is featured',
               'category' => 'content',
               'default_enabled' => true,
           ],
           [
               'type' => 'system_maintenance',
               'name' => 'System Maintenance',
               'description' => 'System maintenance and updates',
               'category' => 'system',
               'default_enabled' => true,
           ],
           [
               'type' => 'security_alert',
               'name' => 'Security Alert',
               'description' => 'Security-related notifications',
               'category' => 'security',
               'default_enabled' => true,
           ],
           [
               'type' => 'password_changed',
               'name' => 'Password Changed',
               'description' => 'When your password is changed',
               'category' => 'security',
               'default_enabled' => true,
           ],
           [
               'type' => 'login_alert',
               'name' => 'Login Alert',
               'description' => 'New login from unrecognized device',
               'category' => 'security',
               'default_enabled' => true,
           ],
           [
               'type' => 'marketing',
               'name' => 'Marketing',
               'description' => 'Marketing emails and promotions',
               'category' => 'marketing',
               'default_enabled' => false,
           ],
       ];

       return $this->successResponse($types, 'Notification types retrieved');
   }

   /**
    * Snooze notifications for a specified time.
    */
   public function snooze(Request $request): JsonResponse
   {
       $request->validate([
           'duration' => 'required|in:15min,1hour,4hours,1day,1week',
           'types' => 'sometimes|array',
           'types.*' => 'string',
       ]);

       $user = $request->user();
       $duration = $request->duration;
       $types = $request->get('types', []);

       // Calculate snooze until time
       $snoozeUntil = match($duration) {
           '15min' => now()->addMinutes(15),
           '1hour' => now()->addHour(),
           '4hours' => now()->addHours(4),
           '1day' => now()->addDay(),
           '1week' => now()->addWeek(),
       };

       // Get current preferences
       $preferences = $user->profile?->preferences ?? [];
       $notificationPreferences = $preferences['notifications'] ?? [];

       // Set snooze settings
       $snoozeSettings = [
           'snoozed_until' => $snoozeUntil->toISOString(),
           'snoozed_types' => $types,
           'snoozed_at' => now()->toISOString(),
       ];

       $notificationPreferences['snooze'] = $snoozeSettings;
       $preferences['notifications'] = $notificationPreferences;

       $user->profile()->updateOrCreate(
           ['user_id' => $user->id],
           ['preferences' => $preferences]
       );

       // Log activity
       Activity::log('notifications_snoozed', null, [
           'duration' => $duration,
           'snooze_until' => $snoozeUntil->toISOString(),
           'types' => $types,
       ]);

       return $this->successResponse([
           'snoozed_until' => $snoozeUntil->toISOString(),
           'duration' => $duration,
           'types' => $types,
       ], 'Notifications snoozed successfully');
   }

   /**
    * Remove snooze from notifications.
    */
   public function unsnooze(Request $request): JsonResponse
   {
       $user = $request->user();

       // Get current preferences
       $preferences = $user->profile?->preferences ?? [];
       $notificationPreferences = $preferences['notifications'] ?? [];

       // Remove snooze settings
       unset($notificationPreferences['snooze']);
       $preferences['notifications'] = $notificationPreferences;

       $user->profile()->updateOrCreate(
           ['user_id' => $user->id],
           ['preferences' => $preferences]
       );

       // Log activity
       Activity::log('notifications_unsnoozed', null);

       return $this->successResponse(null, 'Notifications unsnooze successfully');
   }

   /**
    * Export notifications data.
    */
   public function export(Request $request): JsonResponse
   {
       $request->validate([
           'format' => 'sometimes|in:json,csv',
           'date_from' => 'sometimes|date',
           'date_to' => 'sometimes|date|after:date_from',
           'include_read' => 'boolean',
       ]);

       $user = $request->user();
       $format = $request->get('format', 'json');
       
       $query = $user->notifications();

       // Apply date filters
       if ($request->has('date_from')) {
           $query->where('created_at', '>=', $request->date_from);
       }

       if ($request->has('date_to')) {
           $query->where('created_at', '<=', $request->date_to);
       }

       // Filter read/unread
       if ($request->has('include_read') && !$request->boolean('include_read')) {
           $query->unread();
       }

       $notifications = $query->orderBy('created_at', 'desc')->get()->map(function ($notification) {
           return [
               'id' => $notification->id,
               'type' => $notification->type,
               'title' => $notification->title,
               'message' => $notification->message,
               'priority' => $notification->priority,
               'channel' => $notification->channel,
               'is_read' => $notification->isRead(),
               'read_at' => $notification->read_at,
               'created_at' => $notification->created_at,
           ];
       });

       // Log export activity
       Activity::log('notifications_exported', null, [
           'format' => $format,
           'count' => $notifications->count(),
           'filters' => $request->only(['date_from', 'date_to', 'include_read']),
       ]);

       return $this->successResponse([
           'notifications' => $notifications,
           'format' => $format,
           'exported_at' => now()->toISOString(),
           'count' => $notifications->count(),
       ], 'Notifications exported successfully');
   }

   public function test(Request $request)
   {
       broadcast(new TestEvent($request->message));
       return 'broadcasted';
   }
}