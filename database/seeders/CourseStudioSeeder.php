<?php

namespace Database\Seeders;

use App\Models\ActivityType;
use App\Models\ContentRepositoryItem;
use App\Models\Course;
use App\Models\CourseCategory;
use App\Models\CourseComponent;
use App\Models\CourseSection;
use App\Models\LmsUser;
use App\Models\Tenant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CourseStudioSeeder extends Seeder
{
    public function run(): void
    {
        $tenant = Tenant::query()->where('code', 'VABIS')->firstOrFail();
        $owner = LmsUser::query()->where('tenant_id', $tenant->id)->where('user_type', 'teacher')->first();
        foreach (['Môn chung','Khoa Công nghệ','Khoa Du lịch','Văn hóa 9+','Ngoại ngữ','Doanh nghiệp'] as $i => $name) {
            CourseCategory::query()->updateOrCreate(['tenant_id' => $tenant->id, 'code' => 'CAT'.($i + 1)], ['name' => $name, 'description' => $name, 'sort_order' => $i + 1, 'status' => 'active']);
            ContentRepositoryItem::query()->updateOrCreate(['tenant_id' => $tenant->id, 'title' => $name, 'item_type' => 'folder'], ['owner_id' => $owner?->id ?? 1, 'visibility' => 'tenant', 'status' => 'published', 'metadata' => []]);
        }
        foreach (['text','video','pdf','file','scorm','quiz','assignment','forum','survey','wiki','live_session','certificate','external_tool'] as $type) {
            ActivityType::query()->updateOrCreate(['key' => $type], ['name' => Str::headline($type), 'description' => 'Activity '.$type, 'icon' => $type, 'enabled' => true, 'config_schema' => ['type' => 'object'], 'grading_supported' => in_array($type, ['quiz','assignment'], true), 'completion_supported' => true]);
        }
        $titles = array_merge(array_map(fn ($i) => 'Môn chung '.$i, range(1, 5)), array_map(fn ($i) => 'Cơ sở ngành '.$i, range(1, 5)), array_map(fn ($i) => 'Chuyên ngành '.$i, range(1, 5)), ['Tiếng Anh giao tiếp','Tiếng Nhật cơ bản','Tiếng Hàn cơ bản','An toàn doanh nghiệp','Kỹ năng quản lý']);
        foreach ($titles as $i => $title) {
            $course = Course::query()->updateOrCreate(['tenant_id' => $tenant->id, 'code' => 'COURSE'.str_pad((string) ($i + 1), 3, '0', STR_PAD_LEFT)], ['title' => $title, 'slug' => Str::slug($title), 'short_description' => 'Khóa học mẫu '.$title, 'description' => 'Nội dung đào tạo '.$title, 'level' => $i < 5 ? 'college' : ($i > 17 ? 'corporate' : 'intermediate_vocational'), 'course_type' => 'blended', 'status' => $i % 3 === 0 ? 'published' : 'draft', 'visibility' => 'internal', 'language' => 'vi', 'estimated_hours' => 30, 'owner_id' => $owner?->id ?? 1, 'settings' => ['learning_mode' => 'free']]);
            for ($s = 1; $s <= 3; $s++) {
                $section = CourseSection::query()->updateOrCreate(['tenant_id' => $tenant->id, 'course_id' => $course->id, 'type' => 'section', 'sort_order' => $s], ['title' => 'Chương '.$s, 'description' => 'Mục tiêu chương '.$s, 'status' => 'draft', 'settings' => []]);
                for ($u = 1; $u <= 3; $u++) {
                    $unit = CourseSection::query()->updateOrCreate(['tenant_id' => $tenant->id, 'course_id' => $course->id, 'parent_id' => $section->id, 'type' => 'unit', 'sort_order' => $u], ['title' => 'Bài '.$s.'.'.$u, 'description' => 'Unit '.$u, 'status' => 'draft', 'settings' => []]);
                    foreach (['text','video','quiz'] as $c => $type) {
                        CourseComponent::query()->updateOrCreate(['tenant_id' => $tenant->id, 'course_id' => $course->id, 'section_id' => $unit->id, 'sort_order' => $c + 1], ['component_type' => $type, 'title' => Str::headline($type).' '.$s.'.'.$u.'.'.($c + 1), 'config' => ['min_watch_percent' => 90, 'min_score' => 70], 'required' => true, 'status' => 'draft']);
                    }
                }
            }
        }
    }
}
