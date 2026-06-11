<?php

namespace App\Services;

use App\Models\ActivityType;
use App\Models\CourseComponent;
use Illuminate\Support\Collection;

class ActivityRegistryService
{
    public function register(string $key, array $attributes): ActivityType
    {
        return ActivityType::query()->updateOrCreate(['key' => $key], $attributes + [
            'name' => $key,
            'enabled' => true,
            'config_schema' => ['type' => 'object'],
            'grading_supported' => false,
            'completion_supported' => true,
        ]);
    }

    public function allEnabled(): Collection
    {
        return ActivityType::query()->where('enabled', true)->orderBy('name')->get();
    }

    public function supportsCompletion(string $key): bool
    {
        return (bool) ActivityType::query()->where('key', $key)->value('completion_supported');
    }

    public function supportsGrading(string $key): bool
    {
        return (bool) ActivityType::query()->where('key', $key)->value('grading_supported');
    }

    public function validateComponentConfig(CourseComponent|array $component): array
    {
        $type = $component instanceof CourseComponent ? $component->component_type : $component['component_type'];
        $config = $component instanceof CourseComponent ? ($component->config ?? []) : ($component['config'] ?? []);
        $activity = ActivityType::query()->where('key', $type)->first();
        $errors = [];

        if (! $activity || ! $activity->enabled) {
            return ["Activity type {$type} chưa được đăng ký hoặc đang tắt."];
        }

        $schema = $activity->config_schema ?? [];
        foreach ($schema['required'] ?? [] as $field) {
            if (! array_key_exists($field, $config)) {
                $errors[] = "Thiếu cấu hình {$field} cho {$type}.";
            }
        }

        return $errors;
    }
}
