<?php

namespace App\Services;

use App\Models\ActivityType;

class ActivityRegistryService
{
    public function register(string $key, array $attributes): ActivityType
    {
        return ActivityType::query()->updateOrCreate(['key' => $key], $attributes + ['enabled' => true]);
    }

    public function supportsCompletion(string $key): bool
    {
        return (bool) ActivityType::query()->where('key', $key)->value('completion_supported');
    }

    public function supportsGrading(string $key): bool
    {
        return (bool) ActivityType::query()->where('key', $key)->value('grading_supported');
    }
}
