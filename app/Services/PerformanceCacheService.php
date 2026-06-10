<?php

namespace App\Services;

use Closure;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Cache\TaggableStore;

class PerformanceCacheService
{
    public function __construct(private readonly CacheRepository $cache)
    {
    }

    public function tenantSettings(int $tenantId, Closure $resolver): mixed
    {
        return $this->remember('tenant_settings', "tenant:{$tenantId}", $resolver);
    }

    public function userPermissions(int $tenantId, int $userId, Closure $resolver): mixed
    {
        return $this->remember('user_permissions', "tenant:{$tenantId}:user:{$userId}", $resolver);
    }

    public function courseOutline(int $tenantId, int $courseId, Closure $resolver): mixed
    {
        return $this->remember('course_outline', "tenant:{$tenantId}:course:{$courseId}", $resolver);
    }

    public function lessonMetadata(int $tenantId, int $componentId, Closure $resolver): mixed
    {
        return $this->remember('lesson_metadata', "tenant:{$tenantId}:component:{$componentId}", $resolver);
    }

    public function enrollmentList(int $tenantId, int $courseId, ?int $classId, Closure $resolver): mixed
    {
        return $this->remember('enrollment_list', "tenant:{$tenantId}:course:{$courseId}:class:".($classId ?? 'all'), $resolver);
    }

    public function learningPathRules(int $tenantId, int $courseId, Closure $resolver): mixed
    {
        return $this->remember('learning_path_rules', "tenant:{$tenantId}:course:{$courseId}", $resolver);
    }

    public function quizConfig(int $tenantId, int $quizId, Closure $resolver): mixed
    {
        return $this->remember('quiz_config', "tenant:{$tenantId}:quiz:{$quizId}", $resolver);
    }

    public function gradebookConfig(int $tenantId, int $gradebookId, Closure $resolver): mixed
    {
        return $this->remember('gradebook_config', "tenant:{$tenantId}:gradebook:{$gradebookId}", $resolver);
    }

    public function dashboardSummary(int $tenantId, string $scope, Closure $resolver): mixed
    {
        return $this->remember('dashboard_summary', "tenant:{$tenantId}:scope:{$scope}", $resolver);
    }

    public function forgetTenant(int $tenantId): void
    {
        if (! $this->supportsTags()) {
            return;
        }

        foreach (array_keys(config('cache.eralms.ttl', [])) as $segment) {
            $this->cache->tags(["eralms", "tenant:{$tenantId}", $segment])->flush();
        }
    }

    private function remember(string $segment, string $key, Closure $resolver): mixed
    {
        $ttl = (int) config("cache.eralms.ttl.{$segment}", 300);
        $cacheKey = "eralms:{$segment}:{$key}";

        if ($this->supportsTags()) {
            return $this->cache->tags($this->tags($segment, $key))->remember($cacheKey, $ttl, $resolver);
        }

        return $this->cache->remember($cacheKey, $ttl, $resolver);
    }

    private function tags(string $segment, string $key): array
    {
        preg_match('/(?:^|:)tenant:(\d+)(?::|$)/', $key, $matches);

        return array_values(array_filter([
            'eralms',
            $segment,
            isset($matches[1]) ? "tenant:{$matches[1]}" : null,
        ]));
    }

    private function supportsTags(): bool
    {
        return method_exists($this->cache, 'getStore')
            && $this->cache->getStore() instanceof TaggableStore;
    }
}
