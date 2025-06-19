<?php

namespace App\Models\Chat;

use App\Models\User\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Message extends Model
{
    protected $fillable = [
        'conversation_id',
        'sender_id',
        'reply_to_id',
        'type',
        'content',
        'attachments',
        'metadata',
        'is_edited',
        'edited_at',
    ];

    protected $casts = [
        'attachments' => 'array',
        'metadata' => 'array',
        'is_edited' => 'boolean',
        'edited_at' => 'datetime',
    ];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function replyTo(): BelongsTo
    {
        return $this->belongsTo(Message::class, 'reply_to_id');
    }

    public function isTextMessage(): bool
    {
        return $this->type === 'text';
    }

    public function isImageMessage(): bool
    {
        return $this->type === 'image';
    }

    public function isFileMessage(): bool
    {
        return $this->type === 'file';
    }

    public function isSystemMessage(): bool
    {
        return $this->type === 'system';
    }
}