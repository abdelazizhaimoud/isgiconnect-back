<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Group;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class GroupController extends Controller
{
    /**
     * Get all groups (for admin or public listing)
     */
    public function index()
    {
        $groups = Group::with(['creator', 'members'])
            ->where('is_private', false)
            ->paginate(10);

        return response()->json([
            'success' => true,
            'data' => $groups
        ]);
    }

    /**
     * Get groups for a specific user
     */
    public function userGroups($userId)
    {
        // Verify the authenticated user is requesting their own groups
        if (Auth::id() != $userId) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access to user groups'
            ], 403);
        }

        $groups = Group::with(['creator', 'members'])
            ->where('created_by', $userId)
            ->orWhereHas('members', function ($query) use ($userId) {
                $query->where('user_id', $userId);
            })
            ->paginate(10);

        return response()->json([
            'success' => true,
            'data' => $groups
        ]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'description' => 'required|string',
            'is_private' => 'required|boolean',
            'avatar' => 'nullable|image|mimes:jpeg,png,jpg|max:2048'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        
        // Ensure is_private is a boolean
        $isPrivate = filter_var($request->is_private, FILTER_VALIDATE_BOOLEAN);

        $group = Group::create([
            'name' => $request->name,
            'description' => $request->description,
            'created_by' => Auth::id(),
            'is_private' => $isPrivate,
            'avatar' => $request->hasFile('avatar') ? $request->file('avatar')->store('group-avatars', 'public') : null
        ]);

        // Add creator as admin member
        $group->members()->attach(Auth::id(), ['role' => 'admin']);

        return response()->json([
            'success' => true,
            'data' => $group->load(['creator', 'members'])
        ], 201);
    }

    public function show($id)
    {
        $group = Group::with(['creator', 'members'])
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $group
        ]);
    }

    public function update(Request $request, $id)
    {
        $group = Group::findOrFail($id);

        if ($group->created_by !== Auth::id()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'string|max:255',
            'description' => 'string',
            'is_private' => 'boolean',
            'avatar' => 'nullable|image|mimes:jpeg,png,jpg|max:2048'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $group->update($request->all());

        return response()->json([
            'success' => true,
            'data' => $group->load(['creator', 'members'])
        ]);
    }

    public function destroy($id)
    {
        $group = Group::findOrFail($id);

        if ($group->created_by !== Auth::id()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 403);
        }

        $group->delete();

        return response()->json([
            'success' => true,
            'message' => 'Group deleted successfully'
        ]);
    }

    /**
     * Add a member to a group (admin function)
     */
    public function addMember(Request $request, $id)
    {
        $group = Group::findOrFail($id);
        
        if ($group->created_by !== Auth::id()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'user_id' => 'required|exists:users,id',
            'role' => 'required|in:member,admin'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        // Check if user is already a member
        if ($group->members()->where('user_id', $request->user_id)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'User is already a member of this group'
            ], 422);
        }

        $group->members()->attach($request->user_id, ['role' => $request->role]);

        return response()->json([
            'success' => true,
            'message' => 'Member added successfully',
            'data' => [
                'group' => $group->load('members')
            ]
        ]);
    }

    /**
     * Join a public group
     */
    public function joinGroup($id)
    {
        $group = Group::findOrFail($id);
        $user = Auth::user();

        // Check if group is private
        if ($group->is_private) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot join a private group. Please request an invitation.'
            ], 403);
        }

        // Check if user is already a member
        if ($group->members()->where('user_id', $user->id)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'You are already a member of this group'
            ], 422);
        }

        // Add user as a regular member
        $group->members()->attach($user->id, ['role' => 'member']);

        return response()->json([
            'success' => true,
            'message' => 'Successfully joined the group',
            'data' => [
                'group' => $group->load('members')
            ]
        ]);
    }

    /**
     * Leave a group
     */
    public function leaveGroup($id)
    {
        $group = Group::findOrFail($id);
        $user = Auth::user();

        // Check if user is a member
        if (!$group->members()->where('user_id', $user->id)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'You are not a member of this group'
            ], 422);
        }

        // Check if user is the creator (creators can't leave, they must delete or transfer ownership)
        if ($group->created_by === $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Group creator cannot leave. Please delete the group or transfer ownership.'
            ], 403);
        }

        $group->members()->detach($user->id);

        return response()->json([
            'success' => true,
            'message' => 'Successfully left the group'
        ]);
    }

    public function removeMember($id, $userId)
    {
        $group = Group::findOrFail($id);
        
        if ($group->created_by !== Auth::id()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 403);
        }

        $group->members()->detach($userId);

        return response()->json([
            'success' => true,
            'message' => 'Member removed successfully'
        ]);
    }

    public function getMembers($id)
    {
        $group = Group::findOrFail($id);
        $members = $group->members()->paginate(10);

        return response()->json([
            'success' => true,
            'data' => $members
        ]);
    }
} 