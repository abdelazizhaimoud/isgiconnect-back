<?php

namespace App\Models\Content;

use App\Models\User\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Comment extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'commentable_type',
        'commentable_id',
        'user_id',
        'parent_id',
        'author_name',
        'author_email',
        'content',
        'status',
        'ip_address',
        'user_agent',
        'like_count',
        'dislike_count',
        'meta_data',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'meta_data' => 'array',
    ];

    /**
     * Get the commentable model.
     */
    public function commentable()
    {
        return $this->morphTo();
    }

    /**
     * Get the user that owns the comment.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the parent comment.
     */
    public function parent()
    {
        return $this->belongsTo(Comment::class, 'parent_id');
    }

    /**
     * Get the children comments.
     */
    public function children()
    {
        return $this->hasMany(Comment::class, 'parent_id');
    }

    /**
     * Scope only approved comments.
     */
    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    /**
     * Scope only pending comments.
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope root comments (no parent).
     */
    public function scopeRoots($query)
    {
        return $query->whereNull('parent_id');
    }

    /**
     * Check if comment is approved.
     */
    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    /**
     * Get the author name.
     */
    public function getAuthorNameAttribute($value): string
    {
        if ($this->user) {
            return $this->user->name;
        }

        return $value ?? 'Anonymous';
    }

    /**
     * Check if comment has replies.
     */
    public function hasReplies(): bool
    {
        return $this->children()->exists();
    }
}