<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\BaseController;
use App\Models\Chat\Conversation;
use App\Models\Chat\Message;
use App\Models\Chat\ConversationParticipant;
use App\Models\User\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Carbon\Carbon;

class ChatController extends BaseController
{
    /**
     * Get user conversations with latest message
     */
    public function getConversations(Request $request): JsonResponse
    {
        if (!Auth::check()) {
            return $this->sendError('Authentication required', 401);
        }

        $userId = Auth::id();
        $perPage = $request->get('per_page', 20);

        $conversations = Conversation::whereHas('participants', function ($query) use ($userId) {
            $query->where('user_id', $userId);
        })->with([
                'participants.user.profile',
                'messages.sender.profile',
                'creator.profile'
            ])
            ->where('is_active', true)
            ->orderByRaw('CASE 
                WHEN last_message_at IS NULL THEN created_at 
                ELSE last_message_at 
            END DESC')
            ->paginate($perPage);
        
        $conversations->getCollection()->transform(function ($conversation) use ($userId) {
            $latestMessage = $conversation->messages->first();
            
            $otherParticipant = null;
            if ($conversation->type === 'direct') {
                $otherParticipant = $conversation->participants
                    ->firstWhere('user_id', '!=', $userId)?->user;
            }

            // Ensure we have a name for the conversation
            $conversationName = $conversation->type === 'direct' 
                ? ($otherParticipant?->name ?? 'Unknown User')
                : ($conversation->name ?? 'Unnamed Conversation');

            return [
                'id' => $conversation->id,
                'type' => $conversation->type,
                'name' => $conversationName,
                'avatar' => $conversation->type === 'direct' 
                    ? ($otherParticipant?->profile?->avatar ?? null)
                    : ($conversation->avatar ?? null),
                'description' => $conversation->description ?? null,
                'is_active' => $conversation->is_active,
                'last_message' => $latestMessage ? [
                    'id' => $latestMessage->id,
                    'content' => $latestMessage->content,
                    'type' => $latestMessage->type,
                    'sender_name' => $latestMessage->sender?->name ?? 'Unknown User',
                    'created_at' => $latestMessage->created_at,
                ] : null,
                'participants_count' => $conversation->participants->count(),
                'last_message_at' => $conversation->last_message_at,
                'created_at' => $conversation->created_at,
            ];
        });

        return $this->sendResponse($conversations, 'Conversations retrieved successfully');
    }

    /**
     * Get messages for a specific conversation
     */
    public function getMessages(Request $request, int $conversationId): JsonResponse
    {
        if (!Auth::check()) {
            return $this->sendError('Authentication required', 401);
        }

        $userId = Auth::id();
        $perPage = $request->get('per_page', 50);

        // Check if user is participant
        $conversation = Conversation::where('id', $conversationId)
            ->whereHas('participants', function ($query) use ($userId) {
                $query->where('user_id', $userId);
            })
            ->first();

        if (!$conversation) {
            return $this->sendError('Conversation not found or access denied', 404);
        }

        // Debug: Log conversation details
        Log::debug('Fetching messages for conversation', [
            'conversation_id' => $conversationId,
            'user_id' => $userId
        ]);

        $messages = Message::where('conversation_id', $conversationId)
            ->with(['sender.profile', 'replyTo.sender.profile'])
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        // Debug: Log message count
        Log::debug('Messages found', [
            'count' => $messages->count(),
            'total' => $messages->total()
        ]);

        $messages->getCollection()->transform(function ($message) use ($userId) {
            return [
                'id' => $message->id,
                'content' => $message->content,
                'type' => $message->type,
                'attachments' => $message->attachments,
                'is_edited' => $message->is_edited,
                'edited_at' => $message->edited_at,
                'sender' => [
                    'id' => $message->sender->id,
                    'name' => $message->sender->name,
                    'username' => $message->sender->username,
                    'avatar' => $message->sender->profile?->avatar,
                ],
                'reply_to' => $message->replyTo ? [
                    'id' => $message->replyTo->id,
                    'content' => $message->replyTo->content,
                    'sender_name' => $message->replyTo->sender->name,
                ] : null,
                'is_own_message' => $message->sender_id === $userId,
                'created_at' => $message->created_at,
            ];
        });

        // Mark messages as read
        $this->markAsRead($conversationId, $userId);

        return $this->sendResponse($messages, 'Messages retrieved successfully');
    }

    /**
     * Send a new message
     */
    public function sendMessage(Request $request, int $conversationId): JsonResponse
    {
        if (!Auth::check()) {
            return $this->sendError('Authentication required', 401);
        }

        $userId = Auth::id();

        // Validate request
        $request->validate([
            'content' => 'required|string|max:5000',
            'type' => 'sometimes|in:text,image,file',
            'reply_to_id' => 'sometimes|exists:messages,id',
            'attachments' => 'sometimes|array',
        ]);

        // Check if user is participant
        $conversation = Conversation::where('id', $conversationId)
            ->whereHas('participants', function ($query) use ($userId) {
                $query->where('user_id', $userId);
            })
            ->first();

        if (!$conversation) {
            return $this->sendError('Conversation not found or access denied', 404);
        }

        DB::beginTransaction();
        try {
            // Create message
            $message = Message::create([
                'conversation_id' => $conversationId,
                'sender_id' => $userId,
                'reply_to_id' => $request->reply_to_id,
                'type' => $request->type ?? 'text',
                'content' => $request->content,
                'attachments' => $request->attachments,
            ]);

            // Update conversation last message time
            $conversation->update([
                'last_message_at' => now()
            ]);

            // Load message with relationships
            $message->load(['sender.profile', 'replyTo.sender.profile']);

            DB::commit();

            $responseMessage = [
                'id' => $message->id,
                'content' => $message->content,
                'type' => $message->type,
                'attachments' => $message->attachments,
                'sender' => [
                    'id' => $message->sender->id,
                    'name' => $message->sender->name,
                    'username' => $message->sender->username,
                    'avatar' => $message->sender->profile?->avatar,
                ],
                'reply_to' => $message->replyTo ? [
                    'id' => $message->replyTo->id,
                    'content' => $message->replyTo->content,
                    'sender_name' => $message->replyTo->sender->name,
                ] : null,
                'is_own_message' => true,
                'created_at' => $message->created_at,
            ];

            return $this->sendResponse($responseMessage, 'Message sent successfully', 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->sendError('Failed to send message', 500);
        }
    }

    /**
     * Start a direct conversation with another user
     */
    public function startDirectConversation(Request $request): JsonResponse
    {
        if (!Auth::check()) {
            return $this->sendError('Authentication required', 401);
        }

        $request->validate([
            'user_id' => 'required|exists:users,id',
        ]);

        $currentUserId = Auth::id();
        $otherUserId = $request->user_id;

        if ($currentUserId === $otherUserId) {
            return $this->sendError('Cannot start conversation with yourself', 400);
        }

        // Check if direct conversation already exists
        $existingConversation = Conversation::where('type', 'direct')
            ->whereHas('participants', function ($query) use ($currentUserId) {
                $query->where('user_id', $currentUserId);
            })
            ->whereHas('participants', function ($query) use ($otherUserId) {
                $query->where('user_id', $otherUserId);
            })
            ->first();

        if ($existingConversation) {
            return $this->sendResponse([
                'conversation_id' => $existingConversation->id,
                'exists' => true
            ], 'Conversation already exists');
        }

        DB::beginTransaction();
        try {
            // Create new direct conversation
            $conversation = Conversation::create([
                'type' => 'direct',
                'created_by' => $currentUserId,
                'is_active' => true,
            ]);

            // Create participant records
            ConversationParticipant::create([
                'conversation_id' => $conversation->id,
                'user_id' => $currentUserId,
                'role' => 'member',
                'joined_at' => now(),
                'last_read_at' => now(),
                'is_muted' => false
            ]);

            ConversationParticipant::create([
                'conversation_id' => $conversation->id,
                'user_id' => $otherUserId,
                'role' => 'member',
                'joined_at' => now(),
                'last_read_at' => now(),
                'is_muted' => false
            ]);

            DB::commit();

            // Load conversation with relationships
            $conversation->load([
                'participants.user',
                'lastMessage.sender'
            ]);

            // Format the conversation for response
            $conversationData = [
                'id' => $conversation->id,
                'type' => $conversation->type,
                'name' => $conversation->name,
                'description' => $conversation->description,
                'avatar' => $conversation->avatar,
                'is_active' => $conversation->is_active,
                'created_by' => $conversation->created_by,
                'created_at' => $conversation->created_at ? Carbon::parse($conversation->created_at)->format('Y-m-d\TH:i:s.u\Z') : null,
                'last_message_at' => $conversation->last_message_at ? Carbon::parse($conversation->last_message_at)->format('Y-m-d\TH:i:s.u\Z') : null,
                'participants' => $conversation->participants->map(function ($participant) {
                    return [
                        'id' => $participant->user_id,
                        'name' => $participant->user->name,
                        'username' => $participant->user->username,
                        'avatar' => $participant->user->avatar,
                        'role' => $participant->role,
                        'is_muted' => $participant->is_muted,
                        'last_read_at' => $participant->last_read_at ? Carbon::parse($participant->last_read_at)->format('Y-m-d\TH:i:s.u\Z') : null,
                    ];
                }),
                'last_message' => $conversation->lastMessage?->only([
                    'id', 'content', 'type', 'attachments', 'created_at',
                    'sender' => ['id', 'name', 'username', 'avatar']
                ]),
            ];

            return $this->sendResponse([
                'conversation' => $conversationData,
                'exists' => false
            ], 'Direct conversation created successfully', 201);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to create conversation in startDirectConversation', [
                'error' => $e->getMessage()
            ]);
            return $this->sendError('Failed to create conversation', 500);
        }
    }

    /**
     * Search for users to start conversation
     */
    public function searchUsers(Request $request): JsonResponse
    {
        if (!Auth::check()) {
            return $this->sendError('Authentication required', 401);
        }

        $request->validate([
            'q' => 'required|string|min:2|max:50',
        ]);

        $searchTerm = $request->q;
        $currentUserId = Auth::id();

        $users = User::where('id', '!=', $currentUserId)
            ->where('status', 'active')
            ->where(function ($query) use ($searchTerm) {
                $query->where('name', 'like', "%{$searchTerm}%")
                      ->orWhere('username', 'like', "%{$searchTerm}%")
                      ->orWhere('email', 'like', "%{$searchTerm}%");
            })
            ->with('profile')
            ->limit(20)
            ->get();

        $users->transform(function ($user) {
            return [
                'id' => $user->id,
                'name' => $user->name,
                'username' => $user->username,
                'email' => $user->email,
                'avatar' => $user->profile?->avatar,
            ];
        });

        return $this->sendResponse($users, 'Users found successfully');
    }

    /**
     * Mark conversation as read
     */
    private function markAsRead(int $conversationId, int $userId): void
    {
        DB::table('conversation_participants')
            ->where('conversation_id', $conversationId)
            ->where('user_id', $userId)
            ->update(['last_read_at' => now()]);
    }
}
