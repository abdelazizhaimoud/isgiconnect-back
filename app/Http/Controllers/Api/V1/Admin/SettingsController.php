<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Api\ApiController;
use App\Models\System\Setting;
use App\Models\System\Activity;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;

class SettingsController extends ApiController
{
    /**
     * Get all settings grouped by category.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Setting::query();

        // Filter by group if specified
        if ($request->has('group')) {
            $query->where('group', $request->group);
        }

        // Filter by public/private if specified
        if ($request->has('is_public')) {
            $query->where('is_public', $request->boolean('is_public'));
        }

        // Filter by editable if specified
        if ($request->has('is_editable')) {
            $query->where('is_editable', $request->boolean('is_editable'));
        }

        $settings = $query->orderBy('group')->orderBy('sort_order')->get();

        // Group settings by their group
        $groupedSettings = $settings->groupBy('group')->map(function ($group, $groupName) {
            return [
                'group' => $groupName,
                'settings' => $group->map(function ($setting) {
                    return [
                        'id' => $setting->id,
                        'key' => $setting->key,
                        'value' => Setting::castValue($setting->value, $setting->type),
                        'type' => $setting->type,
                        'description' => $setting->description,
                        'is_public' => $setting->is_public,
                        'is_editable' => $setting->is_editable,
                        'validation_rules' => $setting->validation_rules,
                        'sort_order' => $setting->sort_order,
                    ];
                }),
            ];
        })->values();

        return $this->successResponse($groupedSettings, 'Settings retrieved successfully');
    }

    /**
     * Get settings by group.
     */
    public function getByGroup(string $group): JsonResponse
    {
        $settings = Setting::getByGroup($group);
        return $this->successResponse($settings, "Settings for group '{$group}' retrieved successfully");
    }

    /**
     * Get public settings only.
     */
    public function getPublic(): JsonResponse
    {
        $settings = Setting::getPublic();
        return $this->successResponse($settings, 'Public settings retrieved successfully');
    }

    /**
     * Get a specific setting.
     */
    public function show(string $key): JsonResponse
    {
        $setting = Setting::where('key', $key)->first();

        if (!$setting) {
            return $this->errorResponse('Setting not found', 404);
        }

        $settingData = [
            'id' => $setting->id,
            'key' => $setting->key,
            'value' => Setting::castValue($setting->value, $setting->type),
            'type' => $setting->type,
            'group' => $setting->group,
            'description' => $setting->description,
            'is_public' => $setting->is_public,
            'is_editable' => $setting->is_editable,
            'validation_rules' => $setting->validation_rules,
            'sort_order' => $setting->sort_order,
            'created_at' => $setting->created_at,
            'updated_at' => $setting->updated_at,
        ];

        return $this->successResponse($settingData, 'Setting retrieved successfully');
    }

    /**
     * Create a new setting.
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'key' => 'required|string|unique:settings,key|max:255',
            'value' => 'required',
            'type' => 'required|in:string,integer,boolean,float,array,json',
            'group' => 'required|string|max:255',
            'description' => 'nullable|string',
            'is_public' => 'boolean',
            'is_editable' => 'boolean',
            'validation_rules' => 'nullable|array',
            'sort_order' => 'integer',
        ]);

        // Process value based on type
        $value = $this->processValue($request->value, $request->type);

        $setting = Setting::create([
            'key' => $request->key,
            'value' => $value,
            'type' => $request->type,
            'group' => $request->group,
            'description' => $request->description,
            'is_public' => $request->get('is_public', false),
            'is_editable' => $request->get('is_editable', true),
            'validation_rules' => $request->validation_rules,
            'sort_order' => $request->get('sort_order', 0),
        ]);

        // Log activity
        Activity::logCreated($setting, [
            'key' => $setting->key,
            'group' => $setting->group,
        ]);

        return $this->successResponse($setting, 'Setting created successfully', 201);
    }

    /**
     * Update a setting.
     */
    public function update(Request $request, Setting $setting): JsonResponse
    {
        $request->validate([
            'value' => 'required',
            'type' => 'sometimes|in:string,integer,boolean,float,array,json',
            'group' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'is_public' => 'boolean',
            'is_editable' => 'boolean',
            'validation_rules' => 'nullable|array',
            'sort_order' => 'integer',
        ]);

        // Check if setting is editable
        if (!$setting->is_editable) {
            return $this->errorResponse('This setting is not editable', 403);
        }

        $oldValue = $setting->value;
        $newType = $request->get('type', $setting->type);

        // Process value based on type
        $value = $this->processValue($request->value, $newType);

        // Validate the new value if validation rules exist
        if ($setting->validation_rules) {
            $validator = Validator::make(['value' => $request->value], [
                'value' => $setting->validation_rules
            ]);

            if ($validator->fails()) {
                return $this->errorResponse('Validation failed', 422, $validator->errors()->toArray());
            }
        }

        $setting->update([
            'value' => $value,
            'type' => $newType,
            'group' => $request->get('group', $setting->group),
            'description' => $request->get('description', $setting->description),
            'is_public' => $request->get('is_public', $setting->is_public),
            'validation_rules' => $request->get('validation_rules', $setting->validation_rules),
            'sort_order' => $request->get('sort_order', $setting->sort_order),
        ]);

        // Log activity
        Activity::logUpdated($setting, [
            'old_value' => $oldValue,
            'new_value' => $value,
        ]);

        return $this->successResponse($setting, 'Setting updated successfully');
    }

    /**
     * Update multiple settings at once.
     */
    public function bulkUpdate(Request $request): JsonResponse
    {
        $request->validate([
            'settings' => 'required|array',
            'settings.*.key' => 'required|string|exists:settings,key',
            'settings.*.value' => 'required',
        ]);

        $updatedSettings = [];
        $errors = [];

        foreach ($request->settings as $settingData) {
            $setting = Setting::where('key', $settingData['key'])->first();

            if (!$setting) {
                $errors[] = "Setting with key '{$settingData['key']}' not found";
                continue;
            }

            if (!$setting->is_editable) {
                $errors[] = "Setting '{$settingData['key']}' is not editable";
                continue;
            }

            // Validate the new value if validation rules exist
            if ($setting->validation_rules) {
                $validator = Validator::make(['value' => $settingData['value']], [
                    'value' => $setting->validation_rules
                ]);

                if ($validator->fails()) {
                    $errors[] = "Validation failed for '{$settingData['key']}': " . 
                               implode(', ', $validator->errors()->get('value'));
                    continue;
                }
            }

            $oldValue = $setting->value;
            $value = $this->processValue($settingData['value'], $setting->type);

            $setting->update(['value' => $value]);

            // Log activity
            Activity::logUpdated($setting, [
                'old_value' => $oldValue,
                'new_value' => $value,
                'bulk_update' => true,
            ]);

            $updatedSettings[] = $setting->key;
        }

        if (!empty($errors)) {
            return $this->errorResponse('Some settings could not be updated', 422, $errors);
        }

        return $this->successResponse([
            'updated_settings' => $updatedSettings,
            'count' => count($updatedSettings),
        ], 'Settings updated successfully');
    }

    /**
     * Delete a setting.
     */
    public function destroy(Setting $setting): JsonResponse
    {
        if (!$setting->is_editable) {
            return $this->errorResponse('This setting cannot be deleted', 403);
        }

        // Log activity before deletion
        Activity::logDeleted($setting, [
            'key' => $setting->key,
            'group' => $setting->group,
            'value' => $setting->value,
        ]);

        $setting->delete();

        return $this->successResponse(null, 'Setting deleted successfully');
    }

    /**
     * Clear settings cache.
     */
    public function clearCache(): JsonResponse
    {
        Cache::flush();

        Activity::logCustom(
            'cache_cleared',
            'Settings cache was cleared by admin',
            null,
            ['admin_id' => auth()->id()]
        );

        return $this->successResponse(null, 'Settings cache cleared successfully');
    }

    /**
     * Export settings.
     */
    public function export(Request $request): JsonResponse
    {
        $query = Setting::query();

        if ($request->has('group')) {
            $query->where('group', $request->group);
        }

        if ($request->has('include_private') && !$request->boolean('include_private')) {
            $query->where('is_public', true);
        }

        $settings = $query->get()->map(function ($setting) {
            return [
                'key' => $setting->key,
                'value' => Setting::castValue($setting->value, $setting->type),
                'type' => $setting->type,
                'group' => $setting->group,
                'description' => $setting->description,
                'is_public' => $setting->is_public,
                'validation_rules' => $setting->validation_rules,
            ];
        });

        // Log activity
        Activity::logCustom(
            'settings_exported',
            'Settings were exported by admin',
            null,
            [
                'admin_id' => auth()->id(),
                'count' => $settings->count(),
                'filters' => $request->only(['group', 'include_private']),
            ]
        );

        return $this->successResponse([
            'settings' => $settings,
            'exported_at' => now()->toISOString(),
            'count' => $settings->count(),
        ], 'Settings exported successfully');
    }

    /**
     * Import settings.
     */
    public function import(Request $request): JsonResponse
    {
        $request->validate([
            'settings' => 'required|array',
            'settings.*.key' => 'required|string',
            'settings.*.value' => 'required',
            'settings.*.type' => 'required|in:string,integer,boolean,float,array,json',
            'settings.*.group' => 'required|string',
            'overwrite_existing' => 'boolean',
        ]);

        $imported = [];
        $skipped = [];
        $errors = [];

        foreach ($request->settings as $settingData) {
            $existingSetting = Setting::where('key', $settingData['key'])->first();

            if ($existingSetting && !$request->get('overwrite_existing', false)) {
                $skipped[] = $settingData['key'];
                continue;
            }

            try {
                $value = $this->processValue($settingData['value'], $settingData['type']);

                Setting::updateOrCreate(
                    ['key' => $settingData['key']],
                    [
                        'value' => $value,
                        'type' => $settingData['type'],
                        'group' => $settingData['group'],
                        'description' => $settingData['description'] ?? null,
                        'is_public' => $settingData['is_public'] ?? false,
                        'is_editable' => $settingData['is_editable'] ?? true,
                        'validation_rules' => $settingData['validation_rules'] ?? null,
                        'sort_order' => $settingData['sort_order'] ?? 0,
                    ]
                );

                $imported[] = $settingData['key'];
            } catch (\Exception $e) {
                $errors[] = "Failed to import '{$settingData['key']}': " . $e->getMessage();
            }
        }

        // Log activity
        Activity::logCustom(
            'settings_imported',
            'Settings were imported by admin',
            null,
            [
                'admin_id' => auth()->id(),
                'imported_count' => count($imported),
                'skipped_count' => count($skipped),
                'errors_count' => count($errors),
            ]
        );

        return $this->successResponse([
            'imported' => $imported,
            'skipped' => $skipped,
            'errors' => $errors,
            'summary' => [
                'imported_count' => count($imported),
                'skipped_count' => count($skipped),
                'errors_count' => count($errors),
            ],
        ], 'Settings import completed');
    }

    /**
     * Get available setting groups.
     */
    public function getGroups(): JsonResponse
    {
        $groups = Setting::select('group')
            ->distinct()
            ->whereNotNull('group')
            ->orderBy('group')
            ->pluck('group')
            ->map(function ($group) {
                return [
                    'name' => $group,
                    'count' => Setting::where('group', $group)->count(),
                ];
            });

        return $this->successResponse($groups, 'Setting groups retrieved successfully');
    }

    /**
     * Process value based on type.
     */
    private function processValue($value, string $type): string
    {
        switch ($type) {
            case 'boolean':
                return $value ? '1' : '0';
            case 'array':
            case 'json':
                return is_string($value) ? $value : json_encode($value);
            case 'integer':
                return (string) (int) $value;
            case 'float':
                return (string) (float) $value;
            default:
                return (string) $value;
        }
    }
}