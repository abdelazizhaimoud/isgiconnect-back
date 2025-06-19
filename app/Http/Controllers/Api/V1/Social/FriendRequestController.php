<?php

namespace App\Http\Controllers\Api\V1\Social;

use App\Http\Controllers\Controller;
use App\Models\FriendRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\JsonResponse;
use App\Models\Friend;
use Illuminate\Support\Facades\Log;

class FriendRequestController extends Controller
{
            public function store(Request $request): JsonResponse
    {
        Log::info('FriendRequestController@store: Received request.');
        $validator = Validator::make($request->all(), [
            'receiver_id' => 'required|exists:users,id',
        ]);

        if ($validator->fails()) {
            Log::error('FriendRequestController@store: Validation failed.', ['errors' => $validator->errors()]);
            return response()->json(['status' => 'error', 'message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        $senderId = Auth::id();
        $receiverId = (int) $request->input('receiver_id');
        Log::info('FriendRequestController@store: Data', ['sender_id' => $senderId, 'receiver_id' => $receiverId]);

        if ($senderId === $receiverId) {
            Log::warning('FriendRequestController@store: User tried to send a friend request to themselves.', ['user_id' => $senderId]);
            return response()->json(['status' => 'error', 'message' => 'You cannot send a friend request to yourself.'], 400);
        }

        // Check if users are already friends
        Log::info('FriendRequestController@store: Checking if users are already friends.');
        $isFriend = Friend::where(function ($query) use ($senderId, $receiverId) {
            $query->where('user_id', $senderId)->where('friend_id', $receiverId);
        })->orWhere(function ($query) use ($senderId, $receiverId) {
            $query->where('user_id', $receiverId)->where('friend_id', $senderId);
        })->exists();
        Log::info('FriendRequestController@store: Friendship status.', ['is_friend' => $isFriend]);

        if ($isFriend) {
            return response()->json(['status' => 'error', 'message' => 'You are already friends with this user.'], 400);
        }

        // Check for an existing pending friend request
        Log::info('FriendRequestController@store: Checking for existing pending requests.');
        $existingRequest = FriendRequest::where(function ($query) use ($senderId, $receiverId) {
            $query->where('sender_id', $senderId)->where('receiver_id', $receiverId);
        })->orWhere(function ($query) use ($senderId, $receiverId) {
            $query->where('sender_id', $receiverId)->where('receiver_id', $senderId);
        })->where('status', 'pending')->exists();
        Log::info('FriendRequestController@store: Existing request status.', ['exists' => $existingRequest]);

        if ($existingRequest) {
            return response()->json(['status' => 'error', 'message' => 'A friend request is already pending.'], 400);
        }

        try {
            Log::info('FriendRequestController@store: Attempting to create FriendRequest.');
            $friendRequest = FriendRequest::create([
                'sender_id' => $senderId,
                'receiver_id' => $receiverId,
                'status' => 'pending',
            ]);
            Log::info('FriendRequestController@store: FriendRequest created successfully.', ['request_id' => $friendRequest->id]);

            return response()->json([
                'status' => 'success',
                'message' => 'Friend request sent successfully.',
                'data' => $friendRequest,
            ], 201);
        } catch (\Exception $e) {
            Log::error('FriendRequestController@store: Exception caught.', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return response()->json([
                'status' => 'error',
                'message' => 'An unexpected error occurred while sending the friend request.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get a list of pending friend requests sent by the authenticated user.
     */
    public function getSentRequests(Request $request): JsonResponse
    {
        $userId = Auth::id();

        if (!$userId) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthenticated.'
            ], 401);
        }

        $sentRequests = FriendRequest::where('sender_id', $userId)
            ->where('status', 'pending')
            ->pluck('receiver_id')
            ->toArray();

        return response()->json([
            'status' => 'success',
            'data' => $sentRequests
        ]);
    }

    /**
     * Cancel a pending friend request.
     */
    public function cancel(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'receiver_id' => 'required|exists:users,id'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $senderId = Auth::id();
            $receiverId = $request->receiver_id;

            $friendRequest = FriendRequest::where('sender_id', $senderId)
                                ->where('receiver_id', $receiverId)
                                ->where('status', 'pending')
                                ->first();

            if (!$friendRequest) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Pending friend request not found.'
                ], 404);
            }

            $friendRequest->delete();

            return response()->json([
                'status' => 'success',
                'message' => 'Friend request cancelled successfully.'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error cancelling friend request.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get a list of pending friend requests received by the authenticated user.
     */
    public function getReceivedRequests(Request $request): JsonResponse
    {
        $userId = Auth::id();
        \Illuminate\Support\Facades\Log::info('getReceivedRequests method called. User ID: ' . $userId);

        if (!$userId) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthenticated.'
            ], 401);
        }

        $receivedRequests = FriendRequest::with('sender.profile')
            ->where('receiver_id', $userId)
            ->where('status', 'pending')
            ->get()
            ->map(function ($request) {
                return [
                    'id' => $request->id,
                    'sender_id' => $request->sender->id,
                    'sender_name' => $request->sender->name,
                    'sender_email' => $request->sender->email,
                    'sender_avatar_url' => $request->sender->profile?->avatar_url ?? '/images/user/default.jpg',
                    'created_at' => $request->created_at->diffForHumans(),
                ];
            });

        \Illuminate\Support\Facades\Log::info('Received requests data: ' . json_encode($receivedRequests));

        return response()->json([
            'status' => 'success',
            'data' => $receivedRequests
        ]);
    }

    /**
     * Accept a pending friend request.
     */
    public function acceptRequest(Request $request): JsonResponse
    {
        try {
            \Illuminate\Support\Facades\Log::info('acceptRequest: Method started.');
            $validator = Validator::make($request->all(), [
                'request_id' => 'required|exists:friend_requests,id'
            ]);

            if ($validator->fails()) {
                \Illuminate\Support\Facades\Log::error('acceptRequest: Validation failed.', ['errors' => $validator->errors()]);
                return response()->json([
                    'status' => 'error',
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $userId = Auth::id();
            $requestId = $request->request_id;
            \Illuminate\Support\Facades\Log::info('acceptRequest: Data', ['auth_user_id' => $userId, 'request_id' => $requestId]);


            $friendRequest = FriendRequest::where('id', $requestId)
                                ->where('receiver_id', $userId)
                                ->where('status', 'pending')
                                ->first();

            if (!$friendRequest) {
                \Illuminate\Support\Facades\Log::warning('acceptRequest: Friend request not found.', ['request_id' => $requestId, 'receiver_id' => $userId]);
                return response()->json([
                    'status' => 'error',
                    'message' => 'Pending friend request not found or not for this user.'
                ], 404);
            }
            \Illuminate\Support\Facades\Log::info('acceptRequest: Friend request found.', ['friend_request' => $friendRequest]);

            // Update status to accepted
            $friendRequest->update(['status' => 'accepted']);
            \Illuminate\Support\Facades\Log::info('acceptRequest: Friend request status updated to accepted.');


            // Add entries to the friends table (if not already friends)
            \Illuminate\Support\Facades\Log::info('acceptRequest: Preparing to create friend entry.', [
                'sender_id' => $friendRequest->sender_id, 
                'receiver_id' => $friendRequest->receiver_id
            ]);
            $user1 = min($friendRequest->sender_id, $friendRequest->receiver_id);
            $user2 = max($friendRequest->sender_id, $friendRequest->receiver_id);

            \Illuminate\Support\Facades\Log::info('Attempting to create friend entry for user1: ' . $user1 . ' and user2: ' . $user2);

            $friendEntry = \App\Models\Friend::firstOrCreate(
                ['user_id' => $user1, 'friend_id' => $user2],
                ['created_at' => now(), 'updated_at' => now()]
            );
            \Illuminate\Support\Facades\Log::info('Friend entry firstOrCreate result:' . $friendEntry->toJson());

            return response()->json([
                'status' => 'success',
                'message' => 'Friend request accepted successfully.'
            ]);

        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('acceptRequest: Exception caught.', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return response()->json([
                'status' => 'error',
                'message' => 'Error accepting friend request.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Reject a pending friend request.
     */
    public function rejectRequest(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'request_id' => 'required|exists:friend_requests,id'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $userId = Auth::id();
            $requestId = $request->request_id;

            $friendRequest = FriendRequest::where('id', $requestId)
                                ->where('receiver_id', $userId)
                                ->where('status', 'pending')
                                ->first();

            if (!$friendRequest) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Pending friend request not found or not for this user.'
                ], 404);
            }

            $friendRequest->update(['status' => 'rejected']); // Set status to rejected

            return response()->json([
                'status' => 'success',
                'message' => 'Friend request rejected successfully.'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error rejecting friend request.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
