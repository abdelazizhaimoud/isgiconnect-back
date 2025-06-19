<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Api\ApiController;
use App\Models\System\Activity;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class ProfileController extends ApiController
{
    /**
     * Get user profile.
     */
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();
        $user->load(['profile', 'roles']);

        $profileData = [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'status' => $user->status,
            'email_verified_at' => $user->email_verified_at,
            'last_login_at' => $user->last_login_at,
            'created_at' => $user->created_at,
            'profile' => $user->profile ? [
                'first_name' => $user->profile->first_name,
                'last_name' => $user->profile->last_name,
                'full_name' => $user->profile->full_name,
                'phone' => $user->profile->phone,
                'bio' => $user->profile->bio,
                'avatar' => $user->profile->avatar,
                'avatar_url' => $user->profile->avatar_url,
                'date_of_birth' => $user->profile->date_of_birth,
                'gender' => $user->profile->gender,
                'website' => $user->profile->website,
                'linkedin' => $user->profile->linkedin,
                'twitter' => $user->profile->twitter,
                'facebook' => $user->profile->facebook,
                'address' => $user->profile->address,
                'city' => $user->profile->city,
                'state' => $user->profile->state,
                'country' => $user->profile->country,
                'postal_code' => $user->profile->postal_code,
                'timezone' => $user->profile->timezone,
                'language' => $user->profile->language,
                'preferences' => $user->profile->preferences,
            ] : null,
            'roles' => $user->roles->pluck('name'),
            'permissions' => $user->getPermissionSlugs(),
        ];

        return $this->successResponse($profileData, 'Profile retrieved successfully');
    }

    /**
     * Update user profile.
     */
    public function update(Request $request): JsonResponse
    {
        $user = $request->user();

        $request->validate([
            'name' => 'sometimes|string|max:255',
            'email' => ['sometimes', 'string', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'profile' => 'sometimes|array',
            'profile.first_name' => 'nullable|string|max:255',
            'profile.last_name' => 'nullable|string|max:255',
            'profile.phone' => 'nullable|string|max:20',
            'profile.bio' => 'nullable|string|max:1000',
            'profile.date_of_birth' => 'nullable|date|before:today',
            'profile.gender' => 'nullable|in:male,female,other',
            'profile.website' => 'nullable|url|max:255',
            'profile.linkedin' => 'nullable|url|max:255',
            'profile.twitter' => 'nullable|string|max:255',
            'profile.facebook' => 'nullable|url|max:255',
            'profile.address' => 'nullable|string|max:500',
            'profile.city' => 'nullable|string|max:255',
            'profile.state' => 'nullable|string|max:255',
            'profile.country' => 'nullable|string|max:255',
            'profile.postal_code' => 'nullable|string|max:20',
            'profile.timezone' => 'nullable|string|max:255',
            'profile.language' => 'nullable|string|max:10',
            'profile.preferences' => 'nullable|array',
        ]);

        $originalData = $user->toArray();

        // Update user data
        if ($request->has('name')) {
            $user->update(['name' => $request->name]);
        }

        if ($request->has('email')) {
            // If email is changed, mark as unverified
            if ($user->email !== $request->email) {
                $user->update([
                    'email' => $request->email,
                    'email_verified_at' => null,
                    'status' => 'pending_verification',
                ]);

                // Send new verification email
                $this->sendVerificationEmail($user);
            }
        }

        // Update profile data
        if ($request->has('profile')) {
            $user->profile()->updateOrCreate(
                ['user_id' => $user->id],
                $request->profile
            );
        }

        // Log activity
        $changes = array_diff_assoc($user->fresh()->toArray(), $originalData);
        if (!empty($changes) || $request->has('profile')) {
            Activity::logUpdated($user, $changes, ['profile_updated' => true]);
        }

        $user->load(['profile', 'roles']);

        return $this->successResponse([
            'user' => $user,
            'message' => $request->has('email') && $user->email !== $originalData['email'] 
                ? 'Profile updated. Please verify your new email address.' 
                : 'Profile updated successfully.',
        ], 'Profile updated successfully');
    }

    /**
     * Update avatar.
     */
    public function updateAvatar(Request $request): JsonResponse
    {
        $request->validate([
            'avatar' => 'required|image|mimes:jpeg,png,jpg,gif|max:2048', // 2MB max
        ]);

        $user = $request->user();

        // Delete old avatar if exists
        if ($user->profile && $user->profile->avatar) {
            Storage::disk('public')->delete($user->profile->avatar);
        }

        // Store new avatar
        $avatarPath = $request->file('avatar')->store('avatars', 'public');

        // Update profile
        $user->profile()->updateOrCreate(
            ['user_id' => $user->id],
            ['avatar' => $avatarPath]
        );

        // Log activity
        Activity::log('avatar_updated', $user, ['avatar_path' => $avatarPath]);

        $user->load('profile');

        return $this->successResponse([
            'avatar_url' => $user->profile->avatar_url,
            'avatar_path' => $avatarPath,
        ], 'Avatar updated successfully');
    }

    /**
     * Delete avatar.
     */
    public function deleteAvatar(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->profile && $user->profile->avatar) {
            // Delete file
            Storage::disk('public')->delete($user->profile->avatar);

            // Update profile
            $user->profile->update(['avatar' => null]);

            // Log activity
            Activity::log('avatar_deleted', $user);

            return $this->successResponse(null, 'Avatar deleted successfully');
        }

        return $this->errorResponse('No avatar to delete', 404);
    }

    /**
     * Update preferences.
     */
    public function updatePreferences(Request $request): JsonResponse
    {
        $request->validate([
            'preferences' => 'required|array',
            'preferences.notifications' => 'sometimes|array',
            'preferences.theme' => 'sometimes|string|in:light,dark,auto',
            'preferences.language' => 'sometimes|string|max:10',
            'preferences.timezone' => 'sometimes|string|max:255',
            'preferences.email_notifications' => 'sometimes|boolean',
            'preferences.push_notifications' => 'sometimes|boolean',
            'preferences.marketing_emails' => 'sometimes|boolean',
        ]);

        $user = $request->user();

        // Get current preferences and merge with new ones
        $currentPreferences = $user->profile?->preferences ?? [];
        $newPreferences = array_merge($currentPreferences, $request->preferences);

        // Update profile
        $user->profile()->updateOrCreate(
            ['user_id' => $user->id],
            ['preferences' => $newPreferences]
        );

        // Log activity
        Activity::log('preferences_updated', $user, [
            'updated_preferences' => array_keys($request->preferences),
        ]);

        return $this->successResponse([
            'preferences' => $newPreferences,
        ], 'Preferences updated successfully');
    }

    /**
     * Get user statistics.
     */
    public function statistics(Request $request): JsonResponse
    {
        $user = $request->user();

        $stats = [
            'content_created' => $user->contents()->count(),
            'content_published' => $user->contents()->where('status', 'published')->count(),
            'total_views' => $user->contents()->sum('view_count'),
            'total_likes' => $user->contents()->sum('like_count'),
            'comments_made' => $user->comments()->count(),
            'media_uploaded' => $user->media()->count(),
            'activities_count' => $user->activities()->count(),
            'member_since' => $user->created_at,
            'last_active' => $user->activities()->latest()->value('created_at'),
        ];

        return $this->successResponse($stats, 'User statistics retrieved successfully');
    }

    /**
     * Get user activity history.
     */
    public function activityHistory(Request $request): JsonResponse
    {
        $user = $request->user();
        $perPage = $request->get('per_page', 20);

        $activities = $user->activities()
                          ->with('subject')
                          ->latest()
                          ->paginate($perPage);

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

        return $this->successResponse($activities, 'Activity history retrieved successfully');
    }

    /**
     * Export user data.
     */
    public function exportData(Request $request): JsonResponse
    {
        $user = $request->user();
        $user->load(['profile', 'roles', 'contents', 'comments', 'media', 'activities']);

        $exportData = [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'status' => $user->status,
                'created_at' => $user->created_at,
                'profile' => $user->profile,
                'roles' => $user->roles->pluck('name'),
            ],
            'content' => $user->contents->map(function ($content) {
                return [
                    'id' => $content->id,
                    'title' => $content->title,
                    'status' => $content->status,
                    'created_at' => $content->created_at,
                ];
            }),
            'comments' => $user->comments->map(function ($comment) {
                return [
                    'id' => $comment->id,
                    'content' => $comment->content,
                    'created_at' => $comment->created_at,
                ];
            }),
            'activities' => $user->activities->map(function ($activity) {
                return [
                    'action' => $activity->action,
                    'description' => $activity->description,
                    'created_at' => $activity->created_at,
                ];
            }),
            'exported_at' => now()->toISOString(),
        ];

        // Log export activity
        Activity::log('data_exported', $user);

        return $this->successResponse($exportData, 'User data exported successfully');
    }

    /**
     * Delete user account.
     */
    public function deleteAccount(Request $request): JsonResponse
    {
        $request->validate([
            'password' => 'required|string',
            'confirmation' => 'required|string|in:DELETE',
        ]);

        $user = $request->user();

        // Verify password
        if (!\Hash::check($request->password, $user->password)) {
            return $this->errorResponse('Invalid password', 401);
        }

        // Log deletion before actual deletion
        Activity::log('account_deleted', $user, [
            'deletion_reason' => 'user_requested',
            'ip_address' => $request->ip(),
        ]);

        // Revoke all tokens
        $user->tokens()->delete();

        // Delete user (this will cascade to profile and other related data)
        $user->delete();

        return $this->successResponse(null, 'Account deleted successfully');
    }

    /**
     * Send verification email for new email.
     */
    private function sendVerificationEmail($user): void
    {
        $verificationToken = \Str::random(64);
        $user->update([
            'email_verification_token' => $verificationToken,
            'email_verification_token_expires_at' => now()->addHours(24),
        ]);

        $verificationUrl = config('app.frontend_url') . '/verify-email?token=' . $verificationToken;
        
        // Log verification email sent
        \Log::info("Email verification would be sent to {$user->email} with URL: {$verificationUrl}");
    }
}