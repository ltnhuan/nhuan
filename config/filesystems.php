<?php

return [
    'default' => env('FILESYSTEM_DISK', env('APP_ENV') === 'testing' ? 'local' : 's3'),
    'disks' => [
        'local' => [
            'driver' => 'local',
            'root' => storage_path('app/private'),
            'serve' => true,
            'throw' => false,
        ],
        'public' => [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'url' => env('APP_URL').'/storage',
            'visibility' => 'public',
            'throw' => false,
        ],
        's3' => [
            'driver' => 's3',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
            'bucket' => env('AWS_BUCKET'),
            'url' => env('AWS_URL'),
            'endpoint' => env('AWS_ENDPOINT'),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
            'throw' => false,
        ],
        'object_storage' => [
            'driver' => 's3',
            'key' => env('OBJECT_STORAGE_ACCESS_KEY_ID', env('AWS_ACCESS_KEY_ID')),
            'secret' => env('OBJECT_STORAGE_SECRET_ACCESS_KEY', env('AWS_SECRET_ACCESS_KEY')),
            'region' => env('OBJECT_STORAGE_REGION', env('AWS_DEFAULT_REGION', 'us-east-1')),
            'bucket' => env('OBJECT_STORAGE_BUCKET', env('AWS_BUCKET')),
            'url' => env('OBJECT_STORAGE_URL', env('AWS_URL')),
            'endpoint' => env('OBJECT_STORAGE_ENDPOINT', env('AWS_ENDPOINT')),
            'use_path_style_endpoint' => env('OBJECT_STORAGE_PATH_STYLE', env('AWS_USE_PATH_STYLE_ENDPOINT', false)),
            'visibility' => env('OBJECT_STORAGE_VISIBILITY', 'private'),
            'throw' => false,
        ],
        'minio' => [
            'driver' => 's3',
            'key' => env('MINIO_ACCESS_KEY_ID'),
            'secret' => env('MINIO_SECRET_ACCESS_KEY'),
            'region' => env('MINIO_DEFAULT_REGION', 'us-east-1'),
            'bucket' => env('MINIO_BUCKET', 'eralms'),
            'url' => env('MINIO_URL'),
            'endpoint' => env('MINIO_ENDPOINT', 'http://127.0.0.1:9000'),
            'use_path_style_endpoint' => true,
            'throw' => false,
        ],
    ],
    'links' => [
        public_path('storage') => storage_path('app/public'),
    ],
];
