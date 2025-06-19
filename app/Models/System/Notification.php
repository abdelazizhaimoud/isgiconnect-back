<?php

namespace App\Models\System;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    use HasFactory;

    /**
     * Indicates if the IDs are auto-incrementing.
     */
    public $incrementing = false;

    /**
     * The "type" of the primary key ID.
     */
    protected $keyType = 'string';

    /**
     * Disable updated_at timestamp.
     */
    public $timestamps = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'id',
        'type',
        'notifiable_type',
        'notifiable_id',
        'data',
        'channel',
        'priority',
        'read_at',
        'sent_at',
        'metadata',
        'created_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'data' => 'array',
        'metadata' => 'array',
        'read_at' => 'datetime',
        'sent_at' => 'datetime',
        'created_at' => 'datetime',
    ];

    /**
     * Get the notifiable entity.
     */
    public function notifiable()
    {
        return $this->morphTo();
    }

    /**
     * Mark notification as read.
     */
    public function markAsRead(): void
    {
        if (!$this->read_at) {
            $this->update(['read_at' => now()]);
        }
    }

    /**
     * Mark notification as unread.
     */
    public function markAsUnread(): void
    {
        $this->update(['read_at' => null]);
    }

    /**
     * Check if notification is read.
     */
    public function isRead(): bool
    {
        return !is_null($this->read_at);
    }

    /**
     * Check if notification is unread.
     */
    public function isUnread(): bool
    {
        return is_null($this->read_at);
    }

    /**
     * Scope unread notifications.
     */
    public function scopeUnread($query)
    {
        return $query->whereNull('read_at');
    }

    /**
     * Scope read notifications.
     */
    public function scopeRead($query)
    {
        return $query->whereNotNull('read_at');
    }

    /**
     * Scope by priority.
     */
    public function scopeByPriority($query, string $priority)
    {
        return $query->where('priority', $priority);
    }

    /**
     * Scope high priority notifications.
     */
    public function scopeHighPriority($query)
    {
        return $query->whereIn('priority', ['high', 'urgent']);
    }

    /**
     * Scope by channel.
     */
    public function scopeByChannel($query, string $channel)
    {
        return $query->where('channel', $channel);
    }

    /**
     * Scope recent notifications.
     */
    public function scopeRecent($query, int $days = 7)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }

    /**
     * Get notification title.
     */
    public function getTitleAttribute(): string
    {
        return $this->data['title'] ?? 'Notification';
    }

    /**
     * Get notification message.
     */
    public function getMessageAttribute(): string
    {
        return $this->data['message'] ?? $this->data['body'] ?? '';
    }

   /**
    * Get notification URL.
    */
    public function getUrlAttribute(): ?string
    {
        return $this->data['url'] ?? $this->data['action_url'] ?? null;
    }

    /**
        * Get notification icon.
        */
    public function getIconAttribute(): string
    {
        return $this->data['icon'] ?? 'bell';
    }

    /**
        * Get priority color for UI.
        */
    public function getPriorityColorAttribute(): string
    {
        return match($this->priority) {
            'low' => 'gray',
            'normal' => 'blue',
            'high' => 'orange',
            'urgent' => 'red',
            default => 'blue',
        };
    }

    /**
        * Create a new notification.
        */
    public static function send($notifiable, string $type, array $data, array $options = []): self
    {
        return static::create([
            'id' => \Illuminate\Support\Str::uuid(),
            'type' => $type,
            'notifiable_type' => get_class($notifiable),
            'notifiable_id' => $notifiable->id,
            'data' => $data,
            'channel' => $options['channel'] ?? 'database',
            'priority' => $options['priority'] ?? 'normal',
            'metadata' => $options['metadata'] ?? [],
            'created_at' => now(),
        ]);
    }
    }