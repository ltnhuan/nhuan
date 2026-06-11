<?php

return [
    'default' => env('CACHE_STORE', env('APP_ENV') === 'testing' ? 'array' : 'redis'),
    'eralms' => [
        'ttl' => [
            'tenant_settings' => (int) env('CACHE_TTL_TENANT_SETTINGS', 3600),
            'user_permissions' => (int) env('CACHE_TTL_USER_PERMISSIONS', 900),
            'course_outline' => (int) env('CACHE_TTL_COURSE_OUTLINE', 900),
            'lesson_metadata' => (int) env('CACHE_TTL_LESSON_METADATA', 600),
            'enrollment_list' => (int) env('CACHE_TTL_ENROLLMENT_LIST', 600),
            'learning_path_rules' => (int) env('CACHE_TTL_LEARNING_PATH_RULES', 900),
            'quiz_config' => (int) env('CACHE_TTL_QUIZ_CONFIG', 600),
            'gradebook_config' => (int) env('CACHE_TTL_GRADEBOOK_CONFIG', 900),
            'dashboard_summary' => (int) env('CACHE_TTL_DASHBOARD_SUMMARY', 300),
        ],
    ],
    'stores' => [
        'array' => [
            'driver' => 'array',
            'serialize' => false,
        ],
        'database' => [
            'driver' => 'database',
            'connection' => env('DB_CACHE_CONNECTION'),
            'table' => env('DB_CACHE_TABLE', 'cache'),
            'lock_connection' => env('DB_CACHE_LOCK_CONNECTION'),
            'lock_table' => env('DB_CACHE_LOCK_TABLE', 'cache_locks'),
        ],
        'redis' => [
            'driver' => 'redis',
            'connection' => env('REDIS_CACHE_CONNECTION', 'cache'),
            'lock_connection' => env('REDIS_CACHE_LOCK_CONNECTION', 'default'),
        ],
    ],
    'prefix' => env('CACHE_PREFIX', 'eralms_cache_'),
];
