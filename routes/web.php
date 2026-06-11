<?php

use App\Models\LmsUser;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;

Route::get('/login', function () {
    return appView();
})->name('login');

Route::post('/login', function (Request $request) {
    $credentials = $request->validate([
        'email' => ['required', 'email'],
        'password' => ['required', 'string'],
    ]);

    $tenant = Tenant::query()
        ->where('code', config('eralms.default_tenant_code', 'VABIS'))
        ->where('status', 'active')
        ->first();

    $user = LmsUser::query()
        ->where('tenant_id', $tenant?->id)
        ->where('email', Str::lower($credentials['email']))
        ->where('status', 'active')
        ->first();

    if (! $user || (($user->metadata['demo_password'] ?? null) !== $credentials['password'])) {
        return response()->json([
            'message' => 'Email hoặc mật khẩu không đúng.',
        ], 422);
    }

    Session::put('demo_user_email', $user->email);

    return response()->json([
        'redirect' => '/',
        'user' => $user,
    ])->cookie('eralms_demo_user', $user->email, 120, '/', null, false, true, false, 'lax');
});

Route::post('/logout', function () {
    Session::forget('demo_user_email');

    return response()
        ->json(['redirect' => '/login'])
        ->withoutCookie('eralms_demo_user');
});

Route::get('/trienkhai', function () {
    return response()->file(public_path('trienkhai/index.html'));
})->name('trienkhai');

Route::get('/trienkhai/{page}', function (string $page) {
    $page = trim($page, '/');
    $file = public_path("trienkhai/{$page}.html");

    if (! is_file($file)) {
        abort(404);
    }

    return response()->file($file);
})->where('page', '.*')->name('trienkhai.page');

$spaRoutes = [
    'dashboard' => '/',
    'lms.dashboards' => '/dashboards',
    'lms.dashboards.executive' => '/dashboards/executive',
    'lms.dashboards.academic' => '/dashboards/academic',
    'lms.dashboards.faculty' => '/dashboards/faculty',
    'lms.dashboards.teacher' => '/dashboards/teacher',
    'lms.dashboards.student' => '/dashboards/student',
    'lms.dashboards.exam' => '/dashboards/exam',
    'lms.dashboards.attendance' => '/dashboards/attendance',
    'lms.dashboards.gradebook' => '/dashboards/gradebook',
    'lms.dashboards.integration' => '/dashboards/integration',
    'lms.dashboards.content' => '/dashboards/content',
    'lms.dashboards.risk' => '/dashboards/risk',
    'lms.dashboards.certificate' => '/dashboards/certificate',
    'lms.dashboards.ai' => '/dashboards/ai',
    'admin.lms.system-check' => '/admin/lms/system-check',
    'admin.lms.action-check' => '/admin/lms/action-check',
    'admin.lms.data-integrity' => '/admin/lms/data-integrity',
    'admin.api-ops' => '/admin/api-ops',
    'admin.api-ops.registry' => '/admin/api-ops/registry',
    'admin.api-ops.requests' => '/admin/api-ops/requests',
    'admin.api-ops.events' => '/admin/api-ops/events',
    'admin.api-ops.webhooks' => '/admin/api-ops/webhooks',
    'admin.api-ops.mappings' => '/admin/api-ops/mappings',
    'admin.api-ops.entity-mappings' => '/admin/api-ops/entity-mappings',
    'admin.api-ops.sync-jobs' => '/admin/api-ops/sync-jobs',
    'admin.api-ops.health' => '/admin/api-ops/health',
    'admin.api-ops.console' => '/admin/api-ops/console',
    'admin.api-ops.contracts' => '/admin/api-ops/contracts',
    'lms.analytics' => '/analytics',
    'lms.reports' => '/reports',
    'lms.ai' => '/ai',
    'lms.mobile' => '/mobile',
    'lms.courses.index' => '/courses',
    'lms.courses.studio' => '/courses/studio',
    'lms.repository' => '/repository',
    'lms.learning-path' => '/learning-path',
    'lms.enrollment' => '/enrollment',
    'lms.videos' => '/videos',
    'lms.question-banks' => '/question-banks',
    'lms.exams' => '/exams',
    'lms.assignments' => '/assignments',
    'lms.assignments.deadlines' => '/assignments/deadlines',
    'lms.gradebook' => '/gradebook',
    'lms.attendance' => '/attendance',
    'lms.community' => '/community',
    'lms.surveys' => '/surveys',
    'lms.career' => '/career',
    'lms.obe' => '/obe',
    'lms.standards' => '/standards',
    'lms.moodle-parity' => '/moodle-parity',
    'lms.sis' => '/sis',
    'lms.certificates' => '/credentials',
    'lms.security' => '/security',
    'lms.plugins' => '/plugins',
    'lms.backup' => '/backup',
    'lms.uat' => '/uat',
    'lms.settings' => '/settings',
];

foreach ($spaRoutes as $name => $uri) {
    Route::get($uri, fn () => appView())->name($name);
}

Route::get('/{any?}', function () {
    return appView();
})->where('any', '.*');

if (! function_exists('appPayload')) {
    function appView()
    {
        return response()
            ->view('app', appPayload())
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
            ->header('Pragma', 'no-cache')
            ->header('Expires', '0');
    }

    function appPayload(): array
    {
        $tenant = Tenant::query()
            ->where('code', config('eralms.default_tenant_code', 'VABIS'))
            ->where('status', 'active')
            ->select(['id', 'code', 'name', 'status', 'settings'])
            ->first();

        $email = Session::get('demo_user_email') ?: request()->cookie('eralms_demo_user');
        $user = $email
            ? LmsUser::query()
                ->where('tenant_id', $tenant?->id)
                ->where('email', $email)
                ->select(['id', 'tenant_id', 'code', 'full_name', 'email', 'user_type', 'status', 'metadata'])
                ->first()
            : null;

        return [
            'appPayload' => [
                'csrfToken' => csrf_token(),
                'isAuthenticated' => (bool) $user,
                'demoUserEmail' => $user?->email,
                'user' => $user,
                'tenant' => $tenant,
                'demoCredentials' => [
                    'email' => 'admin.lms@vabis.edu.vn',
                    'password' => 'admin123456',
                    'accounts' => [
                        ['label' => 'Admin LMS', 'email' => 'admin.lms@vabis.edu.vn', 'password' => 'admin123456'],
                        ['label' => 'Đào tạo', 'email' => 'daotao.lms@vabis.edu.vn', 'password' => 'admin123456'],
                        ['label' => 'Khoa', 'email' => 'khoa.lms@vabis.edu.vn', 'password' => 'admin123456'],
                        ['label' => 'Giảng viên', 'email' => 'gv.lms@vabis.edu.vn', 'password' => 'admin123456'],
                        ['label' => 'Sinh viên', 'email' => 'sv.lms@vabis.edu.vn', 'password' => 'admin123456'],
                        ['label' => 'Phụ huynh', 'email' => 'phuhuynh.lms@vabis.edu.vn', 'password' => 'admin123456'],
                        ['label' => 'BGH', 'email' => 'bgh.lms@vabis.edu.vn', 'password' => 'admin123456'],
                        ['label' => 'Learner test', 'email' => 'learner.test@vabis.edu.vn', 'password' => 'learner123456'],
                    ],
                ],
            ],
        ];
    }
}
