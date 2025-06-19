<?php

namespace App\Models\System;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Setting extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'key',
        'value',
        'type',
        'group',
        'description',
        'is_public',
        'is_editable',
        'validation_rules',
        'sort_order',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'is_public' => 'boolean',
        'is_editable' => 'boolean',
        'validation_rules' => 'array',
    ];

    /**
     * Cache key prefix.
     */
    protected static string $cachePrefix = 'setting:';

    /**
     * Boot the model.
     */
    protected static function boot(): void
    {
        parent::boot();

        static::saved(function ($setting) {
            Cache::forget(static::$cachePrefix . $setting->key);
            Cache::forget('settings:all');
            Cache::forget('settings:public');
        });

        static::deleted(function ($setting) {
            Cache::forget(static::$cachePrefix . $setting->key);
            Cache::forget('settings:all');
            Cache::forget('settings:public');
        });
    }

    /**
     * Get setting value by key.
     */
    public static function get(string $key, $default = null)
    {
        $cacheKey = static::$cachePrefix . $key;
        
        return Cache::remember($cacheKey, 3600, function () use ($key, $default) {
            $setting = static::where('key', $key)->first();
            
            if (!$setting) {
                return $default;
            }

            return static::castValue($setting->value, $setting->type);
        });
    }

    /**
     * Set setting value.
     */
    public static function set(string $key, $value, string $type = 'string'): void
    {
        $setting = static::firstOrNew(['key' => $key]);
        $setting->value = is_array($value) || is_object($value) ? json_encode($value) : $value;
        $setting->type = $type;
        $setting->save();
    }

    /**
     * Get all settings.
     */
    public static function all(): array
    {
        return Cache::remember('settings:all', 3600, function () {
            return static::query()
                        ->get()
                        ->mapWithKeys(function ($setting) {
                            return [
                                $setting->key => static::castValue($setting->value, $setting->type)
                            ];
                        })
                        ->toArray();
        });
    }

    /**
     * Get public settings only.
     */
    public static function getPublic(): array
    {
        return Cache::remember('settings:public', 3600, function () {
            return static::where('is_public', true)
                        ->get()
                        ->mapWithKeys(function ($setting) {
                            return [
                                $setting->key => static::castValue($setting->value, $setting->type)
                            ];
                        })
                        ->toArray();
        });
    }

    /**
     * Get settings by group.
     */
    public static function getByGroup(string $group): array
    {
        return static::where('group', $group)
                    ->orderBy('sort_order')
                    ->get()
                    ->mapWithKeys(function ($setting) {
                        return [
                            $setting->key => static::castValue($setting->value, $setting->type)
                        ];
                    })
                    ->toArray();
    }

    /**
     * Cast value to appropriate type.
     */
    protected static function castValue($value, string $type)
    {
        switch ($type) {
            case 'boolean':
                return filter_var($value, FILTER_VALIDATE_BOOLEAN);
            case 'integer':
                return (int) $value;
            case 'float':
                return (float) $value;
            case 'array':
            case 'json':
                return json_decode($value, true);
            default:
                return $value;
        }
    }

    /**
     * Scope for public settings.
     */
    public function scopePublic($query)
    {
        return $query->where('is_public', true);
    }

    /**
     * Scope for editable settings.
     */
    public function scopeEditable($query)
    {
        return $query->where('is_editable', true);
    }

    /**
     * Scope by group.
     */
    public function scopeByGroup($query, string $group)
    {
        return $query->where('group', $group);
    }
}