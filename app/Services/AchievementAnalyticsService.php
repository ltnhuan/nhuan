<?php

namespace App\Services;

use App\Models\CompetencyFrameworkItem;
use App\Models\CompetencyRecord;
use App\Models\LearningOutcome;
use App\Models\OutcomeAchievementSummary;

class AchievementAnalyticsService
{
    public function recalculate(int $tenantId, ?int $courseId = null): array
    {
        $updated = 0;
        foreach (LearningOutcome::query()->where('tenant_id', $tenantId)->when($courseId, fn ($q) => $q->where(function ($qq) use ($courseId) { $qq->whereNull('course_id')->orWhere('course_id', $courseId); }))->get() as $outcome) {
            $records = CompetencyRecord::query()->where('tenant_id', $tenantId)->where(function ($q) use ($outcome) { $q->where('learning_outcome_id', $outcome->id)->orWhere('code', $outcome->code); })->get();
            $score = round((float) ($records->avg('score') ?? 0), 2);
            OutcomeAchievementSummary::query()->updateOrCreate(['tenant_id' => $tenantId, 'outcome_id' => $outcome->id, 'course_id' => $courseId, 'class_id' => null, 'user_id' => null], ['achievement_percent' => $score, 'assessed_count' => $records->count(), 'evidence_count' => $records->whereNotNull('evidence')->count(), 'attainment_status' => $score >= 70 ? 'achieved' : ($records->count() ? 'at_risk' : 'not_evaluated'), 'metadata' => ['type' => $outcome->type], 'updated_at' => now()]);
            $updated++;
        }
        foreach (CompetencyFrameworkItem::query()->where('tenant_id', $tenantId)->get() as $item) {
            $records = CompetencyRecord::query()->where('tenant_id', $tenantId)->where('code', $item->code)->get();
            if ($records->isEmpty()) continue;
            OutcomeAchievementSummary::query()->updateOrCreate(['tenant_id' => $tenantId, 'competency_item_id' => $item->id, 'course_id' => $courseId, 'class_id' => null, 'user_id' => null], ['achievement_percent' => round((float) $records->avg('score'), 2), 'assessed_count' => $records->count(), 'evidence_count' => $records->whereNotNull('evidence')->count(), 'attainment_status' => $records->avg('score') >= 70 ? 'achieved' : 'at_risk', 'metadata' => ['type' => 'competency'], 'updated_at' => now()]);
            $updated++;
        }
        return ['updated' => $updated];
    }

    public function dashboard(int $tenantId): array
    {
        $summaries = OutcomeAchievementSummary::query()->where('tenant_id', $tenantId)->get();
        return [
            'clo_percent' => round((float) $summaries->filter(fn ($s) => ($s->metadata['type'] ?? null) === 'CLO')->avg('achievement_percent'), 2),
            'plo_percent' => round((float) $summaries->filter(fn ($s) => ($s->metadata['type'] ?? null) === 'PLO')->avg('achievement_percent'), 2),
            'competency_percent' => round((float) $summaries->filter(fn ($s) => $s->competency_item_id)->avg('achievement_percent'), 2),
            'at_risk_count' => $summaries->where('attainment_status', 'at_risk')->count(),
        ];
    }
}
