<?php

namespace App\Models\System;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Log extends Model
{
    use HasFactory;

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
        'level',
        'message',
        'context',
        'channel',
        'datetime',
        'extra',
        'formatted',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'context' => 'array',
        'extra' => 'array',
        'datetime' => 'datetime',
    ];

    /**
     * Log levels.
     */
    public const LEVELS = [
        'emergency' => 800,
        'alert' => 700,
        'critical' => 600,
        'error' => 500,
        'warning' => 400,
        'notice' => 300,
        'info' => 200,
        'debug' => 100,
    ];

    /**
     * Create a log entry.
     */
    public static function createEntry(string $level, string $message, array $context = [], string $channel = 'app'): self
    {
        return static::create([
            'level' => $level,
            'message' => $message,
            'context' => $context,
            'channel' => $channel,
            'datetime' => now(),
            'extra' => [
                'ip' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'user_id' => auth()->id(),
            ],
        ]);
    }

    /**
     * Log emergency message.
     */
    public static function emergency(string $message, array $context = []): self
    {
        return static::createEntry('emergency', $message, $context);
    }

    /**
     * Log alert message.
     */
    public static function alert(string $message, array $context = []): self
    {
        return static::createEntry('alert', $message, $context);
    }

    /**
     * Log critical message.
     */
    public static function critical(string $message, array $context = []): self
    {
        return static::createEntry('critical', $message, $context);
    }

    /**
     * Log error message.
     */
    public static function error(string $message, array $context = []): self
    {
        return static::createEntry('error', $message, $context);
    }

    /**
     * Log warning message.
     */
    public static function warning(string $message, array $context = []): self
    {
        return static::createEntry('warning', $message, $context);
    }

    /**
     * Log notice message.
     */
    public static function notice(string $message, array $context = []): self
    {
        return static::createEntry('notice', $message, $context);
    }

    /**
     * Log info message.
     */
    public static function info(string $message, array $context = []): self
    {
        return static::createEntry('info', $message, $context);
    }

    /**
     * Log debug message.
     */
    public static function debug(string $message, array $context = []): self
    {
        return static::createEntry('debug', $message, $context);
    }

    /**
     * Scope by level.
     */
    public function scopeByLevel($query, string $level)
    {
        return $query->where('level', $level);
    }

    /**
     * Scope by channel.
     */
    public function scopeByChannel($query, string $channel)
    {
        return $query->where('channel', $channel);
    }

    /**
     * Scope errors and above.
     */
    public function scopeErrors($query)
    {
        return $query->whereIn('level', ['emergency', 'alert', 'critical', 'error']);
    }

    /**
     * Scope warnings and above.
     */
    public function scopeWarnings($query)
    {
        return $query->whereIn('level', ['emergency', 'alert', 'critical', 'error', 'warning']);
    }

    /**
     * Scope recent logs.
     */
    public function scopeRecent($query, int $hours = 24)
    {
        return $query->where('datetime', '>=', now()->subHours($hours));
    }

    /**
     * Get level priority.
     */
    public function getLevelPriorityAttribute(): int
    {
        return static::LEVELS[$this->level] ?? 0;
    }

    /**
     * Get level color for UI.
     */
    public function getLevelColorAttribute(): string
    {
        return match($this->level) {
            'emergency', 'alert', 'critical' => 'red',
            'error' => 'orange',
            'warning' => 'yellow',
            'notice', 'info' => 'blue',
            'debug' => 'gray',
            default => 'blue',
        };
    }
}