<?php

namespace App\Models\Chat;

use App\Models\User\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Conversation extends Model
{
    protected $fillable = [
        'type',
        'name',
        'description',
        'avatar',
        'is_active',
        'created_by',
        'last_message_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'last_message_at' => 'datetime',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function participants(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'conversation_participants')
                    ->withPivot(['role', 'joined_at', 'last_read_at', 'is_muted'])
                    ->withTimestamps();
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class)->orderBy('created_at', 'desc');
    }

    public function latestMessage(): HasMany
    {
        return $this->hasMany(Message::class)->latest()->limit(1);
    }

    public function isDirectConversation(): bool
    {
        return $this->type === 'direct';
    }

    public function isGroupConversation(): bool
    {
        return $this->type === 'group';
    }

    public function hasParticipant(int $userId): bool
    {
        return $this->participants()->where('user_id', $userId)->exists();
    }

    public function getUnreadCount(int $userId): int
    {
        $participant = $this->participants()->where('user_id', $userId)->first();
        
        if (!$participant || !$participant->pivot->last_read_at) {
            return $this->messages()->count();
        }

        return $this->messages()
                    ->where('created_at', '>', $participant->pivot->last_read_at)
                    ->where('sender_id', '!=', $userId)
                    ->count();
    }
}