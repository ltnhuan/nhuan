<?php

use App\Support\PerformanceQueues;

return [
    'domain' => env('HORIZON_DOMAIN'),
    'path' => env('HORIZON_PATH', 'horizon'),
    'use' => 'default',
    'prefix' => env('HORIZON_PREFIX', 'eralms_horizon:'),
    'middleware' => ['web'],
    'waits' => [
        'redis:default' => 30,
        'redis:video-processing' => 60,
        'redis:quiz-grading' => 10,
        'redis:assignment-processing' => 30,
        'redis:analytics' => 120,
        'redis:notification' => 20,
        'redis:certificate' => 30,
        'redis:sync-sis' => 180,
        'redis:ai' => 60,
    ],
    'trim' => [
        'recent' => 60,
        'pending' => 60,
        'completed' => 10080,
        'recent_failed' => 10080,
        'failed' => 10080,
        'monitored' => 10080,
    ],
    'environments' => [
        'production' => [
            'learner-flow' => [
                'connection' => 'redis',
                'queue' => [
                    PerformanceQueues::DEFAULT,
                    PerformanceQueues::QUIZ_GRADING,
                    PerformanceQueues::NOTIFICATION,
                    PerformanceQueues::CERTIFICATE,
                ],
                'balance' => 'auto',
                'autoScalingStrategy' => 'time',
                'minProcesses' => 4,
                'maxProcesses' => 24,
                'tries' => 3,
                'timeout' => 60,
                'nice' => 0,
            ],
            'media-ai-analytics' => [
                'connection' => 'redis',
                'queue' => [
                    PerformanceQueues::VIDEO_PROCESSING,
                    PerformanceQueues::ASSIGNMENT_PROCESSING,
                    PerformanceQueues::ANALYTICS,
                    PerformanceQueues::AI,
                    PerformanceQueues::SYNC_SIS,
                ],
                'balance' => 'auto',
                'autoScalingStrategy' => 'time',
                'minProcesses' => 2,
                'maxProcesses' => 16,
                'tries' => 3,
                'timeout' => 300,
                'nice' => 5,
            ],
        ],
        'local' => [
            'default' => [
                'connection' => 'redis',
                'queue' => PerformanceQueues::all(),
                'balance' => 'auto',
                'minProcesses' => 1,
                'maxProcesses' => 4,
                'tries' => 2,
                'timeout' => 120,
            ],
        ],
    ],
];
