<?php

namespace App\Http\Controllers\Api\V1\Content;

use App\Http\Controllers\Api\ApiController;
use App\Models\Content\Comment;
use App\Models\Content\Post;
use App\Models\System\Activity;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CommentController extends ApiController
{
    /**
     * Get comments for content.
     */
    public function index(Request $request, string $contentSlug): JsonResponse
    {
        $content = Content::where('slug', $contentSlug)->published()->first();

        if (!$content) {
            return $this->errorResponse('Content not found', 404);
        }

        $query = $content->comments()
                        ->with(['user.profile', 'children.user.profile'])
                        ->approved()
                        ->roots();

        // Apply sorting
        $sortBy = $request->get('sort_by', 'created_at');
        $sortDir = $request->get('sort_dir', 'desc');
        $query->orderBy($sortBy, $sortDir);

        $comments = $query->paginate($request->get('per_page', 15));

        $comments->getCollection()->transform(function ($comment) {
            return [
                'id' => $comment->id,
                'content' => $comment->content,
                'author_name' => $comment->author_name,
                'like_count' => $comment->like_count,
                'dislike_count' => $comment->dislike_count,
                'created_at' => $comment->created_at,
                'author' => $comment->user ? [
                    'id' => $comment->user->id,
                    'name' => $comment->user->name,
                    'avatar_url' => $comment->user->profile?->avatar_url,
                ] : null,
                'replies_count' => $comment->children->count(),
                'replies' => $comment->children->map(function ($reply) {
                    return [
                        'id' => $reply->id,
                        'content' => $reply->content,
                        'author_name' => $reply->author_name,
                        'like_count' => $reply->like_count,
                        'created_at' => $reply->created_at,
                        'author' => $reply->user ? [
                            'id' => $reply->user->id,
                            'name' => $reply->user->name,
                            'avatar_url' => $reply->user->profile?->avatar_url,
                        ] : null,
                    ];
                }),
            ];
        });

        return $this->successResponse($comments, 'Comments retrieved successfully');
    }

    /**
     * Create a new comment.
     */
    public function store(Request $request, int $postId): JsonResponse
    {
        $post = Post::published()->find($postId);
        if (!$post) {
            return $this->errorResponse('Post not found', 404);
        }
        if (!$post->allow_comments) {
            return $this->errorResponse('Comments are not allowed for this post', 403);
        }

        if (Auth::check()) {
            $request->validate([
                'content' => 'required|string|max:2000',
                'parent_id' => 'sometimes|exists:comments,id',
            ]);
        } else {
            $request->validate([
                'content' => 'required|string|max:2000',
                'parent_id' => 'sometimes|exists:comments,id',
                'author_name' => 'required|string|max:255',
                'author_email' => 'required|email|max:255',
            ]);
        }

        $commentData = [
            'commentable_type' => Post::class,
            'commentable_id' => $post->id,
            'parent_id' => $request->parent_id,
            'content' => $request->content,
            'status' => Auth::check() ? 'approved' : 'pending',
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'user_id' => Auth::check() ? Auth::id() : null,
        ];
        if (!Auth::check()) {
            $commentData['author_name'] = $request->author_name;
            $commentData['author_email'] = $request->author_email;
        }

        $comment = \App\Models\Content\Comment::create($commentData);

        // Increment post comment count if approved
        if ($comment->status === 'approved') {
            $post->increment('comments_count');
        }

        // Log activity
        \App\Models\System\Activity::log('comment_created', $comment, [
            'post_id' => $post->id,
            'post_title' => $post->title,
        ]);

        $comment->load(['user.profile']);
        $commentResponse = [
            'id' => $comment->id,
            'content' => $comment->content,
            'author_name' => $comment->author_name,
            'status' => $comment->status,
            'created_at' => $comment->created_at,
            'author' => $comment->user ? [
                'id' => $comment->user->id,
                'name' => $comment->user->name,
                'avatar_url' => $comment->user->profile?->avatar_url,
            ] : null,
        ];
        return $this->successResponse($commentResponse, 'Comment created successfully', 201);
    }

    /**
     * Update a comment.
     */
    public function update(Request $request, Comment $comment): JsonResponse
    {
        // Check if user owns this comment
        if (!Auth::check() || $comment->user_id !== Auth::id()) {
            return $this->errorResponse('Unauthorized to update this comment', 403);
        }

        $request->validate([
            'content' => 'required|string|max:2000',
        ]);

        $originalContent = $comment->content;
        $comment->update([
            'content' => $request->content,
        ]);

        // Log activity
        Activity::logUpdated($comment, [
            'old_content' => $originalContent,
            'new_content' => $request->content,
        ]);

        return $this->successResponse($comment, 'Comment updated successfully');
    }

    /**
     * Delete a comment.
     */
    public function destroy(Comment $comment): JsonResponse
    {
        // Check if user owns this comment or is admin
        if (!Auth::check() || 
            ($comment->user_id !== Auth::id() && !auth()->user()->hasRole('admin'))) {
            return $this->errorResponse('Unauthorized to delete this comment', 403);
        }

        // Update content comment count
        if ($comment->status === 'approved') {
            $comment->commentable->decrement('comment_count');
        }

        // Log activity before deletion
        Activity::logDeleted($comment, [
            'content' => $comment->content,
            'commentable_type' => $comment->commentable_type,
            'commentable_id' => $comment->commentable_id,
        ]);

        $comment->delete();

        return $this->successResponse(null, 'Comment deleted successfully');
    }

    /**
     * Like a comment.
     */
    public function like(Comment $comment): JsonResponse
    {
        if (!Auth::check()) {
            return $this->errorResponse('Authentication required', 401);
        }

        // Check if already liked (you'd need to implement a comment_likes table)
        // For now, just increment the like count
        $comment->increment('like_count');

        // Log activity
        Activity::log('comment_liked', $comment, [
            'liker_id' => Auth::id(),
        ]);

        return $this->successResponse([
            'liked' => true,
            'like_count' => $comment->like_count,
        ], 'Comment liked successfully');
    }

    /**
     * Unlike a comment.
     */
    public function unlike(Comment $comment): JsonResponse
    {
        if (!Auth::check()) {
            return $this->errorResponse('Authentication required', 401);
        }

        // Decrement like count (with minimum of 0)
        if ($comment->like_count > 0) {
            $comment->decrement('like_count');
        }

        // Log activity
        Activity::log('comment_unliked', $comment, [
            'unliker_id' => Auth::id(),
        ]);

        return $this->successResponse([
            'liked' => false,
            'like_count' => $comment->like_count,
        ], 'Comment unliked successfully');
    }

    /**
     * Report a comment.
     */
    public function report(Request $request, Comment $comment): JsonResponse
    {
        $request->validate([
            'reason' => 'required|string|in:spam,inappropriate,harassment,other',
            'description' => 'nullable|string|max:500',
        ]);

        // Log the report
        Activity::log('comment_reported', $comment, [
            'reporter_id' => Auth::id(),
            'reason' => $request->reason,
            'description' => $request->description,
            'ip_address' => $request->ip(),
        ]);

        return $this->successResponse(null, 'Comment reported successfully');
    }

    /**
     * Get comment replies.
     */
    public function replies(Request $request, Comment $comment): JsonResponse
    {
        $replies = $comment->children()
                          ->with(['user.profile'])
                          ->approved()
                          ->orderBy('created_at', 'asc')
                          ->paginate($request->get('per_page', 10));

        $replies->getCollection()->transform(function ($reply) {
            return [
                'id' => $reply->id,
                'content' => $reply->content,
                'author_name' => $reply->author_name,
                'like_count' => $reply->like_count,
                'created_at' => $reply->created_at,
                'author' => $reply->user ? [
                    'id' => $reply->user->id,
                    'name' => $reply->user->name,
                    'avatar_url' => $reply->user->profile?->avatar_url,
                ] : null,
            ];
        });

        return $this->successResponse($replies, 'Comment replies retrieved successfully');
    }
}