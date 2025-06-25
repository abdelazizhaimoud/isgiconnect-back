<?php

namespace App\Models\User;

use App\Models\Content\Content;
use App\Models\Content\Comment;
use App\Models\Media\Media;
use App\Models\System\Activity;
use App\Models\System\Notification;
use App\Models\Traits\HasRoles;

// use App\Models\Traits\HasPermissions;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Database\Factories\User\UserFactory;
use App\Models\Friend;
use App\Models\FriendRequest;
use App\Models\User\Profile;
use App\Models\Content\Post;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class User extends Authenticatable
{

    use HasApiTokens, HasFactory, Notifiable, HasRoles, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'username',
        'email',
        'password',
        'username',
        'status',
        'role',
        'email_verification_token',
        'email_verification_token_expires_at',
        'password_reset_token',
        'password_reset_token_expires_at',
        'last_login_at',
        'last_login_ip',
        'login_attempts',
        'locked_until',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    /**
     * Create a new factory instance for the model.
     *
     * @return \Illuminate\Database\Eloquent\Factories\Factory
     */
    protected static function newFactory()
    {
        return UserFactory::new();
    }

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'email_verification_token',
        'password_reset_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'email_verification_token_expires_at' => 'datetime',
        'password_reset_token_expires_at' => 'datetime',
        'last_login_at' => 'datetime',
        'locked_until' => 'datetime',
        'password' => 'hashed',
    ];

    /**
     * Get the user's profile.
     */
    public function profile()
    {
        return $this->hasOne(Profile::class);
    }

    /**
     * Get posts created by the user.
     */
    public function posts()
    {
        return $this->hasMany(Post::class);
    }

    /**
     * Get comments made by the user.
     */
    public function comments()
    {
        return $this->hasMany(Comment::class);
    }

    /**
     * Get media uploaded by the user.
     */
    public function media()
    {
        return $this->hasMany(Media::class);
    }

    /**
     * Get activities performed by the user.
     */
    public function activities()
    {
        return $this->hasMany(Activity::class);
    }

    /**
     * Check if user is active.
     */
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Check if user is locked.
     */
    public function isLocked(): bool
    {
        return $this->locked_until && $this->locked_until->isFuture();
    }

    /**
     * Check if email is verified.
     */
    public function hasVerifiedEmail(): bool
    {
        return !is_null($this->email_verified_at);
    }

    /**
     * Get the friends where this user is the sender (user_id).
     */
        /**
     * Get all friends for the user.
     *
     * This method returns a collection of User models who are friends with the current user.
     */
    public function friends()
    {
        // Get the friend IDs from the 'friends' table where the current user is involved.
        $friendships = Friend::where('user_id', $this->id)
                             ->orWhere('friend_id', $this->id)
                             ->get();

        // Collect all the IDs of the other users in the friendships.
        $friendIds = $friendships->map(function ($friendship) {
            return $friendship->user_id == $this->id ? $friendship->friend_id : $friendship->user_id;
        });

        // Return the User models for the collected friend IDs.
        return User::whereIn('id', $friendIds)->get();
    }

    /**
     * Get all friend requests sent by this user.
     */
        public function friendRequestsSent()
    {
        return $this->hasMany(FriendRequest::class, 'sender_id');
    }

    /**
     * Get all friend requests received by this user.
     */
        public function friendRequestsReceived()
    {
        return $this->hasMany(FriendRequest::class, 'receiver_id');
    }

    // public function roles()
    // {
    //     return $this->belongsToMany(Role::class);
    // }
}