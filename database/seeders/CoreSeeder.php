<?php

namespace Database\Seeders;

use App\Models\AcademicUnit;
use App\Models\Campus;
use App\Models\LmsUser;
use App\Models\Organization;
use App\Models\Permission;
use App\Models\Role;
use App\Models\SystemSetting;
use App\Models\Tenant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CoreSeeder extends Seeder
{
    public function run(): void
    {
        $tenant = Tenant::query()->updateOrCreate(['code' => 'VABIS'], ['name' => 'VABIS LMS', 'legal_name' => 'Trường Cao đẳng VABIS', 'domain' => 'lms.vabis.edu.vn', 'status' => 'active', 'primary_color' => '#0f4c81', 'secondary_color' => '#f59e0b', 'locale' => 'vi', 'timezone' => 'Asia/Ho_Chi_Minh', 'settings' => ['white_label' => true]]);
        foreach ([['VT', 'Vũng Tàu'], ['ONLINE', 'Online Campus']] as [$code, $name]) {
            Campus::query()->updateOrCreate(['tenant_id' => $tenant->id, 'code' => $code], ['name' => $name, 'status' => 'active']);
        }
        $org = Organization::query()->updateOrCreate(['tenant_id' => $tenant->id, 'code' => 'VABIS-COLLEGE'], ['name' => 'VABIS College', 'type' => 'school', 'status' => 'active', 'settings' => []]);
        $units = ['Khoa Công nghệ', 'Khoa Du lịch', 'Khoa Cơ khí', 'Khoa Điện - Điện tử', 'Khoa Ngoại ngữ', 'Khoa Kinh tế', 'Khoa Hàng hải', 'Khoa Dầu khí', 'Khoa Logistics', 'Văn hóa 9+', 'Phòng Đào tạo', 'Phòng Khảo thí', 'Phòng CTSV', 'Trung tâm Ngoại ngữ', 'Trung tâm Doanh nghiệp', 'Phòng IT', 'Thư viện', 'Đảm bảo chất lượng', 'Ban Giám hiệu'];
        foreach ($units as $i => $name) {
            AcademicUnit::query()->updateOrCreate(['tenant_id' => $tenant->id, 'code' => 'UNIT'.str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT)], ['organization_id' => $org->id, 'name' => $name, 'type' => str_contains($name, 'Phòng') ? 'training_office' : 'faculty', 'status' => 'active']);
        }
        $permissions = ['core.tenant.view','core.tenant.manage','core.user.view','core.user.create','core.user.update','core.user.lock','core.role.manage','course.view','course.create','course.update','course.delete','course.review','course.approve','course.publish','lesson.manage','quiz.manage','assignment.manage','grade.view','grade.approve','sis.sync.manage','report.view','ai.use','repository.view','repository.upload','repository.manage','repository.approve','learning_path.view','learning_path.manage','progress.view_own','progress.view_class','progress.recalculate','completion.approve','completion.override'];
        foreach ($permissions as $key) {
            [$module, $action] = explode('.', $key, 2);
            Permission::query()->updateOrCreate(['key' => $key], ['module' => $module, 'action' => $action, 'description' => 'Cho phép '.$key]);
        }
        $roles = ['super_admin'=>'Quản trị hệ thống','tenant_admin'=>'Quản trị tenant','campus_admin'=>'Quản trị cơ sở','academic_admin'=>'Quản trị đào tạo','faculty_manager'=>'Quản lý khoa','training_officer'=>'Cán bộ đào tạo','teacher'=>'Giảng viên','student'=>'Sinh viên','parent'=>'Phụ huynh','external_learner'=>'Học viên ngoài','sis_integration_bot'=>'Bot tích hợp SIS'];
        foreach ($roles as $name => $display) {
            $role = Role::query()->updateOrCreate(['tenant_id' => $tenant->id, 'name' => $name], ['guard_name' => 'web', 'display_name' => $display, 'scope' => $name === 'super_admin' ? 'system' : 'tenant', 'description' => $display]);
            $allowed = $name === 'student' ? ['course.view','progress.view_own','ai.use'] : $permissions;
            DB::table('role_permission')->where('role_id', $role->id)->delete();
            foreach (Permission::query()->whereIn('key', $allowed)->pluck('id') as $permissionId) {
                DB::table('role_permission')->updateOrInsert(['role_id' => $role->id, 'permission_id' => $permissionId]);
            }
        }
        foreach ([['admin.lms@vabis.edu.vn','ADMIN','Quản trị LMS','admin'],['daotao.lms@vabis.edu.vn','DAOTAO','Cán bộ đào tạo','staff'],['khoa.lms@vabis.edu.vn','KHOA','Quản lý khoa','staff'],['gv.lms@vabis.edu.vn','GVDEMO','Giảng viên Demo','teacher'],['sv.lms@vabis.edu.vn','SVDEMO','Sinh viên Demo','student']] as [$email, $code, $name, $type]) {
            LmsUser::query()->updateOrCreate(['tenant_id' => $tenant->id, 'email' => $email], ['code' => $code, 'full_name' => $name, 'user_type' => $type, 'status' => 'active', 'metadata' => ['demo' => true]]);
        }
        for ($i = 1; $i <= 90; $i++) {
            LmsUser::query()->updateOrCreate(['tenant_id' => $tenant->id, 'code' => 'GV'.str_pad((string) $i, 4, '0', STR_PAD_LEFT)], ['full_name' => 'Giảng viên/Cán bộ '.$i, 'email' => 'gv'.$i.'@vabis.edu.vn', 'user_type' => $i <= 60 ? 'teacher' : 'staff', 'status' => 'active', 'metadata' => []]);
        }
        for ($i = 1; $i <= 5000; $i++) {
            LmsUser::query()->updateOrCreate(['tenant_id' => $tenant->id, 'code' => 'SV'.str_pad((string) $i, 5, '0', STR_PAD_LEFT)], ['full_name' => 'Học viên '.$i, 'email' => 'sv'.$i.'@vabis.edu.vn', 'user_type' => 'student', 'status' => 'active', 'metadata' => ['cohort' => 'Demo']]);
        }

        $demoRoleMap = [
            'admin.lms@vabis.edu.vn' => 'tenant_admin',
            'daotao.lms@vabis.edu.vn' => 'training_officer',
            'khoa.lms@vabis.edu.vn' => 'faculty_manager',
            'gv.lms@vabis.edu.vn' => 'teacher',
            'sv.lms@vabis.edu.vn' => 'student',
        ];
        foreach ($demoRoleMap as $email => $roleName) {
            $user = LmsUser::query()->where('tenant_id', $tenant->id)->where('email', $email)->first();
            $role = Role::query()->where('tenant_id', $tenant->id)->where('name', $roleName)->first();
            if ($user && $role) {
                DB::table('user_role_scope')->updateOrInsert([
                    'user_id' => $user->id,
                    'role_id' => $role->id,
                    'tenant_id' => $tenant->id,
                    'campus_id' => null,
                    'academic_unit_id' => null,
                    'course_id' => null,
                    'class_id' => null,
                ]);
            }
        }

        $teacherRole = Role::query()->where('tenant_id', $tenant->id)->where('name', 'teacher')->first();
        $studentRole = Role::query()->where('tenant_id', $tenant->id)->where('name', 'student')->first();
        LmsUser::query()->where('tenant_id', $tenant->id)->where('user_type', 'teacher')->limit(90)->get()->each(function (LmsUser $user) use ($teacherRole, $tenant) {
            if ($teacherRole) {
                DB::table('user_role_scope')->updateOrInsert(['user_id' => $user->id, 'role_id' => $teacherRole->id, 'tenant_id' => $tenant->id, 'campus_id' => null, 'academic_unit_id' => null, 'course_id' => null, 'class_id' => null]);
            }
        });
        LmsUser::query()->where('tenant_id', $tenant->id)->where('user_type', 'student')->limit(5000)->get()->each(function (LmsUser $user) use ($studentRole, $tenant) {
            if ($studentRole) {
                DB::table('user_role_scope')->updateOrInsert(['user_id' => $user->id, 'role_id' => $studentRole->id, 'tenant_id' => $tenant->id, 'campus_id' => null, 'academic_unit_id' => null, 'course_id' => null, 'class_id' => null]);
            }
        });

        SystemSetting::query()->updateOrCreate(['tenant_id' => $tenant->id, 'group' => 'ui', 'key' => 'shell'], ['value' => ['density' => 'compact', 'language' => 'vi']]);
        SystemSetting::query()->updateOrCreate(['tenant_id' => $tenant->id, 'group' => 'ui', 'key' => 'menu'], ['value' => config('eralms.menu')]);
    }
}
