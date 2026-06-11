<?php

namespace App\Support;

final class PerformanceQueues
{
    public const DEFAULT = 'default';
    public const VIDEO_PROCESSING = 'video-processing';
    public const QUIZ_GRADING = 'quiz-grading';
    public const ASSIGNMENT_PROCESSING = 'assignment-processing';
    public const ANALYTICS = 'analytics';
    public const NOTIFICATION = 'notification';
    public const CERTIFICATE = 'certificate';
    public const SYNC_SIS = 'sync-sis';
    public const AI = 'ai';

    public static function all(): array
    {
        return [
            self::DEFAULT,
            self::VIDEO_PROCESSING,
            self::QUIZ_GRADING,
            self::ASSIGNMENT_PROCESSING,
            self::ANALYTICS,
            self::NOTIFICATION,
            self::CERTIFICATE,
            self::SYNC_SIS,
            self::AI,
        ];
    }
}
