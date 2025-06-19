<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Api\ApiController;
use App\Models\User\User;
use App\Models\User\Role;
use App\Models\User\Permission;
use App\Models\System\Activity;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UserManagementController extends ApiController
{
    /**
     * Get all users with filtering and pagination.
     */
    public function index(Request $request): JsonResponse
    {
        $query = User::with(['profile', 'roles']);

        // Apply filters
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('role')) {
            $query->whereHas('roles', function ($q) use ($request) {
                $q->where('slug', $request->role);
            });
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%")
                  ->orWhere('email', 'LIKE', "%{$search}%");
            });
        }

        // Apply sorting
        $sortBy = $request->get('sort_by', 'created_at');
        $sortDir = $request->get('sort_dir', 'desc');
        $query->orderBy($sortBy, $sortDir);

        $users = $query->paginate($request->get('per_page', 15));

        $users->getCollection()->transform(function ($user) {
            return [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'status' => $user->status,
                'email_verified_at' => $user->email_verified_at,
                'last_login_at' => $user->last_login_at,
                'created_at' => $user->created_at,
                'roles' => $user->roles->pluck('name')->toArray(),
                'profile' => $user->profile ? [
                    'first_name' => $user->profile->first_name,
                    'last_name' => $user->profile->last_name,
                    'avatar_url' => $user->profile->avatar_url,
                ] : null,
            ];
        });

        return $this->successResponse($users, 'Users retrieved successfully');
    }

    /**
     * Get user details.
     */
    public function show(User $user): JsonResponse
    {
        $user->load(['profile', 'roles.permissions', 'activities' => function ($query) {
            $query->latest()->limit(10);
        }]);

        $userData = [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'status' => $user->status,
            'email_verified_at' => $user->email_verified_at,
            'last_login_at' => $user->last_login_at,
            'last_login_ip' => $user->last_login_ip,
            'login_attempts' => $user->login_attempts,
            'created_at' => $user->created_at,
            'updated_at' => $user->updated_at,
            'profile' => $user->profile,
            'roles' => $user->roles->map(function ($role) {
                return [
                    'id' => $role->id,
                    'name' => $role->name,
                    'slug' => $role->slug,
                    'level' => $role->level,
                    'permissions' => $role->permissions->pluck('name')->toArray(),
                ];
            }),
            'permissions' => $user->getPermissionSlugs(),
            'recent_activities' => $user->activities->map(function ($activity) {
                return [
                    'action' => $activity->action,
                    'description' => $activity->description,
                    'created_at' => $activity->created_at,
                ];
            }),
        ];

        return $this->successResponse($userData, 'User details retrieved successfully');
    }

    /**
     * Create a new user.
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
            'status' => 'sometimes|in:active,inactive,suspended',
            'roles' => 'sometimes|array',
            'roles.*' => 'exists:roles,id',
            'profile' => 'sometimes|array',
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'status' => $request->get('status', 'active'),
            'email_verified_at' => now(),
        ]);

        // Create profile if provided
        if ($request->has('profile')) {
            $user->profile()->create($request->profile);
        }

        // Assign roles if provided
        if ($request->has('roles')) {
            $user->roles()->sync($request->roles);
        }

        // Log activity
        Activity::logCreated($user, ['created_by_admin' => auth()->id()]);

        $user->load(['profile', 'roles']);

        return $this->successResponse($user, 'User created successfully', 201);
    }

    /**
     * Update user.
     */
    public function update(Request $request, User $user): JsonResponse
    {
        $request->validate([
            'name' => 'sometimes|string|max:255',
            'email' => ['sometimes', 'string', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'password' => 'sometimes|string|min:8|confirmed',
            'status' => 'sometimes|in:active,inactive,suspended',
            'roles' => 'sometimes|array',
            'roles.*' => 'exists:roles,id',
            'profile' => 'sometimes|array',
        ]);

        $originalData = $user->toArray();

        // Update user data
        $userData = $request->only(['name', 'email', 'status']);
        if ($request->has('password')) {
            $userData['password'] = Hash::make($request->password);
        }

        $user->update($userData);

        // Update profile if provided
        if ($request->has('profile')) {
            $user->profile()->updateOrCreate(['user_id' => $user->id], $request->profile);
        }

        // Update roles if provided
        if ($request->has('roles')) {
            $user->roles()->sync($request->roles);
        }

        // Log activity
        $changes = array_diff_assoc($user->fresh()->toArray(), $originalData);
        Activity::logUpdated($user, $changes, ['updated_by_admin' => auth()->id()]);

        $user->load(['profile', 'roles']);

        return $this->successResponse($user, 'User updated successfully');
    }

    /**
     * Delete user.
     */
    public function destroy(User $user): JsonResponse
    {
        // Prevent deleting current admin user
        if ($user->id === auth()->id()) {
            return $this->errorResponse('Cannot delete your own account', 403);
        }

        // Log activity before deletion
        Activity::logDeleted($user, ['deleted_by_admin' => auth()->id()]);

        $user->delete();

        return $this->successResponse(null, 'User deleted successfully');
    }

    /**
     * Bulk update users.
     */
    public function bulkUpdate(Request $request): JsonResponse
    {
        $request->validate([
            'user_ids' => 'required|array',
            'user_ids.*' => 'exists:users,id',
            'action' => 'required|in:activate,deactivate,suspend,delete',
            'data' => 'sometimes|array',
        ]);

        $userIds = $request->user_ids;
        $action = $request->action;
        $currentUserId = auth()->id();

        // Prevent bulk action on current user
        if (in_array($currentUserId, $userIds)) {
            return $this->errorResponse('Cannot perform bulk action on your own account', 403);
        }

        $users = User::whereIn('id', $userIds)->get();
        $affectedCount = 0;

        foreach ($users as $user) {
            switch ($action) {
                case 'activate':
                    $user->update(['status' => 'active']);
                    Activity::logCustom('activated', "User {$user->name} was activated", $user);
                    $affectedCount++;
                    break;

                case 'deactivate':
                    $user->update(['status' => 'inactive']);
                    Activity::logCustom('deactivated', "User {$user->name} was deactivated", $user);
                    $affectedCount++;
                    break;

                case 'suspend':
                    $user->update(['status' => 'suspended']);
                    Activity::logCustom('suspended', "User {$user->name} was suspended", $user);
                    $affectedCount++;
                    break;

                case 'delete':
                    Activity::logDeleted($user, ['bulk_deleted_by_admin' => $currentUserId]);
                    $user->delete();
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
     * Get user roles and permissions.
     */
    public function roles(): JsonResponse
    {
        $roles = Role::with('permissions')->orderBy('level', 'desc')->get();

        $rolesData = $roles->map(function ($role) {
            return [
                'id' => $role->id,
                'name' => $role->name,
                'slug' => $role->slug,
                'description' => $role->description,
                'level' => $role->level,
                'is_default' => $role->is_default,
                'permissions_count' => $role->permissions->count(),
                'users_count' => $role->users()->count(),
            ];
        });

        return $this->successResponse($rolesData, 'Roles retrieved successfully');
    }

    /**
     * Get all permissions.
     */
    public function permissions(): JsonResponse
    {
        $permissions = Permission::orderBy('group')->orderBy('name')->get();

        $permissionsData = $permissions->groupBy('group')->map(function ($group, $groupName) {
            return [
                'group' => $groupName,
                'permissions' => $group->map(function ($permission) {
                    return [
                        'id' => $permission->id,
                        'name' => $permission->name,
                        'slug' => $permission->slug,
                        'description' => $permission->description,
                        'resource' => $permission->resource,
                        'action' => $permission->action,
                    ];
                }),
            ];
        })->values();

        return $this->successResponse($permissionsData, 'Permissions retrieved successfully');
    }

    /**
     * Assign role to user.
     */
    public function assignRole(Request $request, User $user): JsonResponse
    {
        $request->validate([
            'role_id' => 'required|exists:roles,id',
            'expires_at' => 'sometimes|date|after:now',
        ]);

        $role = Role::findOrFail($request->role_id);
        
        $user->assignRole($role, auth()->id(), $request->expires_at);

        Activity::logCustom(
            'role_assigned',
            "Role '{$role->name}' assigned to user {$user->name}",
            $user
        );

        return $this->successResponse(null, 'Role assigned successfully');
    }

    /**
     * Remove role from user.
     */
    public function removeRole(Request $request, User $user): JsonResponse
    {
        $request->validate([
            'role_id' => 'required|exists:roles,id',
        ]);

        $role = Role::findOrFail($request->role_id);
        
        $user->removeRole($role);

        Activity::logCustom(
            'role_removed',
            "Role '{$role->name}' removed from user {$user->name}",
            $user
        );

        return $this->successResponse(null, 'Role removed successfully');
    }

    /**
     * Get user statistics.
     */
    public function statistics(): JsonResponse
    {
        $stats = [
            'total_users' => User::count(),
            'active_users' => User::where('status', 'active')->count(),
            'inactive_users' => User::where('status', 'inactive')->count(),
            'suspended_users' => User::where('status', 'suspended')->count(),
            'verified_users' => User::whereNotNull('email_verified_at')->count(),
            'unverified_users' => User::whereNull('email_verified_at')->count(),
            'users_by_role' => Role::withCount('users')->get()->map(function ($role) {
                return [
                    'role' => $role->name,
                    'count' => $role->users_count,
                ];
            }),
            'recent_registrations' => [
                'today' => User::whereDate('created_at', today())->count(),
                'this_week' => User::where('created_at', '>=', now()->startOfWeek())->count(),
                'this_month' => User::where('created_at', '>=', now()->startOfMonth())->count(),
            ],
        ];

        return $this->successResponse($stats, 'User statistics retrieved successfully');
    }
}