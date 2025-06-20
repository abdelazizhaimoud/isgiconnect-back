<?php

use App\Http\Controllers\Api\V1\Content\ContentController;
use App\Http\Controllers\JobPostingController;
use App\Http\Controllers\ApplicationController;
use App\Http\Controllers\RecruitmentAnalyticsController;
use App\Http\Controllers\Auth\StudentAuthController;
use Illuminate\Http\Request;
use App\Http\Controllers\Api\V1\Social\FriendRequestController;
use App\Http\Controllers\Api\V1\Content\CommentController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Api\V1\User\UserController;
use App\Http\Controllers\Api\V1\ChatController;
use App\Http\Controllers\Api\V1\AdminDashboardController;
use App\Http\Controllers\Api\V1\Admin\AdminContentController;
use App\Http\Controllers\Api\V1\GroupController;
use App\Http\Controllers\Api\V1\User\NotificationController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/
Route::get('all-users', [StudentAuthController::class, 'getAllUsers']);

Route::post('login', [StudentAuthController::class, 'login']);
Route::post('signup/student', [StudentAuthController::class, 'signup']);
Route::middleware(['auth:sanctum'])->group(function () {

    Route::get('/user', [StudentAuthController::class, 'getStudentWithToken']);
    Route::post('logout', [StudentAuthController::class, 'logout']);

    Route::prefix('admin')->group(function () {
        // Existing statistics route
        Route::get('/dashboard/statistics', [AdminDashboardController::class, 'getStatistics']);
        
        // Admin users routes
        Route::get('/users', [AdminDashboardController::class, 'getUsers']);
        Route::put('/users/{id}/status', [AdminDashboardController::class, 'updateUserStatus']);
        Route::delete('/users/{id}', [AdminDashboardController::class, 'deleteUser']);
        
        // New content management routes
        Route::get('/posts', [AdminDashboardController::class, 'getPosts']);
        Route::get('/posts/{id}', [AdminDashboardController::class, 'getPost']);
        Route::put('/posts/{id}/status', [AdminDashboardController::class, 'updatePostStatus']);
        Route::put('/posts/{id}/feature', [AdminDashboardController::class, 'togglePostFeature']);
        Route::put('/posts/{id}/sticky', [AdminDashboardController::class, 'togglePostSticky']);
        Route::put('/posts/{id}/comments', [AdminDashboardController::class, 'togglePostComments']);
        Route::delete('/posts/{id}', [AdminDashboardController::class, 'deletePost']);
        Route::post('/posts/bulk', [AdminDashboardController::class, 'bulkPostActions']);
        Route::get('/content/statistics', [AdminDashboardController::class, 'getContentStatistics']);
        Route::get('/activities', [AdminDashboardController::class, 'getRecentActivities']);
        Route::get('/dashboard/statistics', [AdminDashboardController::class, 'getStatistics']);

        Route::get('/content/statistics', [AdminContentController::class, 'getStatistics']);
        Route::get('/content', [AdminContentController::class, 'index']);
        Route::get('/content/types', [AdminContentController::class, 'getContentTypes']);
        Route::get('/content/{id}', [AdminContentController::class, 'show']);
        Route::post('/content/{id}/pin', [AdminContentController::class, 'togglePin']);
        Route::patch('/content/{id}/status', [AdminContentController::class, 'updateStatus']);
        Route::get('/content/reported', [AdminContentController::class, 'getReportedPosts']);
        Route::put('/content/{id}/status', [AdminContentController::class, 'updateStatus']);
    });

    Route::prefix('posts')->group(function () {
        Route::get('/', [ContentController::class, 'index']);
        Route::post('/', [ContentController::class, 'store']);
        Route::get('/search', [ContentController::class, 'search']);
        Route::put('/{id}', [ContentController::class, 'update']);
        Route::delete('/{id}', [ContentController::class, 'destroy']);
        Route::get('/featured', [ContentController::class, 'featured']);
        Route::get('/trending', [ContentController::class, 'trending']);
        Route::get('/{slug}', [ContentController::class, 'show']);
        Route::get('/{slug}/related', [ContentController::class, 'related']);
        Route::post('/{id}/report', [ContentController::class, 'report']);
        Route::get('/author/{authorId}', [ContentController::class, 'byAuthor']);

        // Like/Unlike
        Route::post('/{id}/like', [ContentController::class, 'like']);
        Route::delete('/{id}/like', [ContentController::class, 'unlike']);
        // Comments
        Route::post('/{id}/comments', [CommentController::class, 'store']);
        Route::get('/{id}/comments', [CommentController::class, 'index']);
        // Route::get('/comments/{comment}/replies', [CommentController::class, 'replies']);
    });

    Route::prefix('chat')->group(function () {
        Route::get('/conversations', [ChatController::class, 'getConversations']);
        Route::post('/conversations/direct', [ChatController::class, 'startDirectConversation']);
        Route::get('/conversations/{conversationId}/messages', [ChatController::class, 'getMessages']);
        Route::post('/conversations/{conversationId}/messages', [ChatController::class, 'sendMessage']);
        Route::get('/users/search', [ChatController::class, 'searchUsers']);
    });

    
    // Friend Requests
    Route::post('/friend-requests', [FriendRequestController::class, 'store']);
    Route::get('/friend-requests/sent', [FriendRequestController::class, 'getSentRequests']);
    Route::post('/friend-requests/cancel', [FriendRequestController::class, 'cancel']);
    Route::get('/friend-requests/received', [FriendRequestController::class, 'getReceivedRequests']);
    Route::post('/friend-requests/accept', [FriendRequestController::class, 'acceptRequest']);
    Route::post('/friend-requests/reject', [FriendRequestController::class, 'rejectRequest']);
    
    Route::get('/user/friends', [UserController::class, 'getFriends']);
    // Protected Job Postings Routes (for companies to create, update, delete)
    Route::get('company/job-postings', [JobPostingController::class, 'companyIndex']);
    Route::apiResource('job-postings', JobPostingController::class)->except(['index', 'show']);


    // Applications Routes (assuming these are protected)
    Route::apiResource('applications', ApplicationController::class);

    // Recruitment Analytics Route (assuming this is protected)
    Route::get('recruitment-analytics', [RecruitmentAnalyticsController::class, 'index']);

    

        // Like/Unlike
        Route::post('/{id}/like', [ContentController::class, 'like']);
        Route::delete('/{id}/like', [ContentController::class, 'unlike']);
        // Comments
        Route::post('/{id}/comments', [CommentController::class, 'store']);
        Route::get('/{id}/comments', [CommentController::class, 'index']);
        // Route::get('/comments/{comment}/replies', [CommentController::class, 'replies']);
    });
// User routes
Route::prefix('users')->group(function () {
    Route::middleware(['auth:sanctum'])->group(function () {
        Route::get('/search', [\App\Http\Controllers\Api\V1\User\UserController::class, 'searchUsers']);
    });
});

// Public groups route (no auth required)

// Group Management Routes - Protected by auth:sanctum
Route::middleware(['auth:sanctum'])->group(function () {
    // User's groups
    Route::get('users/{user}/groups', [GroupController::class, 'userGroups']);
    
    // Group CRUD operations
    Route::prefix('groups')->group(function () {
        // List all groups (with optional pagination)
        Route::get('/', [GroupController::class, 'index']);
        
        // Create new group
        Route::post('/', [GroupController::class, 'store']);
        
        // Group operations by ID
        Route::prefix('{id}')->group(function () {
            // Get group details
            Route::get('/', [GroupController::class, 'show']);
            
            // Update group
            Route::put('/', [GroupController::class, 'update']);
            
            // Delete group
            Route::delete('/', [GroupController::class, 'destroy']);
            
            // Group members management
            Route::prefix('members')->group(function () {
                // Add member to group (admin function)
                Route::post('/', [GroupController::class, 'addMember']);
                
                // Remove member from group (admin function)
                Route::delete('/{userId}', [GroupController::class, 'removeMember']);
                
                // List group members
                Route::get('/', [GroupController::class, 'getMembers']);
                
                // Join a public group (self-service)
                Route::post('/join', [GroupController::class, 'joinGroup']);
                
                // Leave a group (self-service)
                Route::post('/leave', [GroupController::class, 'leaveGroup']);
            });
        });
    });
});

// Publicly accessible job postings (for students to view)
Route::apiResource('job-postings', JobPostingController::class)->only(['index', 'show']);




Route::post('/test', [NotificationController::class, 'test']);