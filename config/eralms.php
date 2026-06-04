<?php

return [
    'default_tenant_code' => env('ERALMS_DEFAULT_TENANT', 'VABIS'),
    'repository_disk' => env('ERALMS_REPOSITORY_DISK', 'local'),
    'cache_prefix' => 'eralms',
    'tenant_header' => 'X-Tenant-Code',
    'audit' => [
        'queue' => env('ERALMS_AUDIT_QUEUE', false),
    ],
    'menu' => [
        ['key' => 'dashboard', 'label' => 'Tổng quan', 'route' => '/dashboard', 'icon' => 'layout-dashboard'],
        ['key' => 'courses', 'label' => 'Quản trị khóa học', 'route' => '/courses', 'icon' => 'book-open', 'permission' => 'course.view'],
        ['key' => 'studio', 'label' => 'Studio bài giảng', 'route' => '/courses/studio', 'icon' => 'blocks', 'permission' => 'lesson.manage'],
        ['key' => 'repository', 'label' => 'Kho học liệu', 'route' => '/repository', 'icon' => 'folder-tree', 'permission' => 'repository.view'],
        ['key' => 'learning_path', 'label' => 'Lộ trình học tập', 'route' => '/learning-path', 'icon' => 'route', 'permission' => 'learning_path.view'],
        ['key' => 'quiz', 'label' => 'Kiểm tra online', 'route' => '/quizzes', 'icon' => 'badge-check', 'permission' => 'quiz.manage'],
        ['key' => 'assignment', 'label' => 'Bài tập', 'route' => '/assignments', 'icon' => 'clipboard-list', 'permission' => 'assignment.manage'],
        ['key' => 'gradebook', 'label' => 'Sổ điểm', 'route' => '/gradebook', 'icon' => 'table-properties', 'permission' => 'grade.view'],
        ['key' => 'attendance', 'label' => 'Điểm danh online', 'route' => '/attendance', 'icon' => 'calendar-check'],
        ['key' => 'sis', 'label' => 'Đồng bộ SIS', 'route' => '/sis', 'icon' => 'refresh-cw', 'permission' => 'sis.sync.manage'],
        ['key' => 'reports', 'label' => 'Báo cáo', 'route' => '/reports', 'icon' => 'chart-column', 'permission' => 'report.view'],
        ['key' => 'ai', 'label' => 'AI trợ giảng', 'route' => '/ai', 'icon' => 'sparkles', 'permission' => 'ai.use'],
        ['key' => 'settings', 'label' => 'Cấu hình', 'route' => '/settings', 'icon' => 'settings', 'permission' => 'core.tenant.manage'],
    ],
];
