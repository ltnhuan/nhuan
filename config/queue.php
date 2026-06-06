<?php

return [
    'default' => env('QUEUE_CONNECTION', env('APP_ENV') === 'testing' ? 'sync' : 'redis'),
    'names' => [
        'default' => 'default',
        'video_processing' => 'video-processing',
        'quiz_grading' => 'quiz-grading',
        'assignment_processing' => 'assignment-processing',
        'analytics' => 'analytics',
        'notification' => 'notification',
        'certificate' => 'certificate',
        'sync_sis' => 'sync-sis',
        'ai' => 'ai',
    ],
    'connections' => [
        'sync' => [
            'driver' => 'sync',
        ],
        'database' => [
            'driver' => 'database',
            'connection' => env('DB_QUEUE_CONNECTION'),
            'table' => env('DB_QUEUE_TABLE', 'jobs'),
            'queue' => env('DB_QUEUE', 'default'),
            'retry_after' => (int) env('DB_QUEUE_RETRY_AFTER', 90),
            'after_commit' => false,
        ],
        'redis' => [
            'driver' => 'redis',
            'connection' => env('REDIS_QUEUE_CONNECTION', 'default'),
            'queue' => env('REDIS_QUEUE', 'default'),
            'retry_after' => (int) env('REDIS_QUEUE_RETRY_AFTER', 90),
            'block_for' => (int) env('REDIS_QUEUE_BLOCK_FOR', 5),
            'after_commit' => false,
        ],
    ],
    'failed' => [
        'driver' => env('QUEUE_FAILED_DRIVER', 'database-uuids'),
        'database' => env('DB_CONNECTION', 'pgsql'),
        'table' => 'failed_jobs',
    ],
];
