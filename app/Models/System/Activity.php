<?php

namespace App\Models\System;

use App\Models\User\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Activity extends Model
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
        'user_id',
        'action',
        'description',
        'subject_type',
        'subject_id',
        'properties',
        'changes',
        'ip_address',
        'user_agent',
        'created_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'properties' => 'array',
        'changes' => 'array',
        'created_at' => 'datetime',
    ];

    /**
     * Get the user that performed the activity.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the subject of the activity.
     */
    public function subject()
    {
        return $this->morphTo();
    }

    /**
     * Log an activity.
     */
    public static function log(string $action, $subject = null, array $properties = [], ?User $user = null): self
    {
        $user = $user ?? auth()->user();
        
        return static::create([
            'user_id' => $user?->id,
            'action' => $action,
            'subject_type' => $subject ? get_class($subject) : null,
            'subject_id' => $subject?->id,
            'properties' => $properties,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'created_at' => now(),
        ]);
    }

    /**
     * Log model created activity.
     */
    public static function logCreated($model, array $properties = []): self
    {
        return static::log('created', $model, $properties);
    }

    /**
     * Log model updated activity.
     */
    public static function logUpdated($model, array $properties = [], array $changes = []): self
    {
        $activity = static::log('updated', $model, $properties);
        
        if (!empty($changes)) {
            $activity->update(['changes' => $changes]);
        }

        if (!empty($properties)) {
            $activity->update(['properties' => $properties]);
        }
        
        return $activity;
    }

    /**
     * Log model deleted activity.
     */
    public static function logDeleted($model, array $properties = []): self
    {
        return static::log('deleted', $model, $properties);
    }

    /**
     * Log custom activity.
     */
    public static function logCustom(string $action, string $description, $subject = null, array $properties = []): self
    {
        $activity = static::log($action, $subject, $properties);
        $activity->update(['description' => $description]);
        
        return $activity;
    }

    /**
     * Log a report activity for a post.
     */
    public static function logReport($post, array $data = []): self
    {
        return static::log('report', $post, $data);
    }

    /**
     * Scope activities by user.
     */
    public function scopeByUser($query, User $user)
    {
        return $query->where('user_id', $user->id);
    }

    /**
     * Scope activities by action.
     */
    public function scopeByAction($query, string $action)
    {
        return $query->where('action', $action);
    }

    /**
     * Scope activities by subject type.
     */
    public function scopeBySubjectType($query, string $type)
    {
        return $query->where('subject_type', $type);
    }

    /**
     * Scope activities within date range.
     */
    public function scopeWithinDays($query, int $days)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }

    /**
     * Get human readable description.
     */
    public function getDescriptionAttribute($value): string
    {
        if ($value) {
            return $value;
        }

        $userName = $this->user?->name ?? 'System';
        $subjectName = $this->subject?->title ?? $this->subject?->name ?? 'item';
        
        return match($this->action) {
            'created' => "{$userName} created {$subjectName}",
            'updated' => "{$userName} updated {$subjectName}",
            'deleted' => "{$userName} deleted {$subjectName}",
            'viewed' => "{$userName} viewed {$subjectName}",
            default => "{$userName} performed {$this->action} on {$subjectName}",
        };
    }
}