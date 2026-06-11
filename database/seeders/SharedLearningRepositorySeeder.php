<?php

namespace Database\Seeders;

use App\Models\AcademicUnit;
use App\Models\ContentRepositoryItem;
use App\Models\ContentVersion;
use App\Models\LmsUser;
use App\Models\Tenant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SharedLearningRepositorySeeder extends Seeder
{
    public const SAMPLE_ITEM_COUNT = 5000;

    private const ROOT_TITLE = 'Repository';

    private const STORAGE_PREFIX = 'eralms/shared-repository/';

    private const AREAS = [
        'THPT',
        'Van hoa 9+',
        'CNTT',
        'Du lich',
        'Marketing',
        'Tieng Anh',
        'Tieng Hoa',
        'Tieng Han',
    ];

    private const AREA_TITLES = [
        'THPT' => 'THPT',
        'Van hoa 9+' => 'Văn hóa 9+',
        'CNTT' => 'CNTT',
        'Du lich' => 'Du lịch',
        'Marketing' => 'Marketing',
        'Tieng Anh' => 'Tiếng Anh',
        'Tieng Hoa' => 'Tiếng Hoa',
        'Tieng Han' => 'Tiếng Hàn',
    ];

    private const TYPE_FOLDERS = [
        'video' => ['title' => 'Video', 'extension' => 'mp4', 'mime' => 'video/mp4'],
        'pdf' => ['title' => 'PDF', 'extension' => 'pdf', 'mime' => 'application/pdf'],
        'slide' => ['title' => 'Slide', 'extension' => 'pptx', 'mime' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation'],
        'quiz_template' => ['title' => 'Quiz Template', 'extension' => 'xlsx', 'mime' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
        'assignment_template' => ['title' => 'Assignment Template', 'extension' => 'docx', 'mime' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
        'rubric_template' => ['title' => 'Rubric Template', 'extension' => 'xlsx', 'mime' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
        'exam_blueprint' => ['title' => 'Exam Blueprint', 'extension' => 'xlsx', 'mime' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
    ];

    private const SUBJECTS = [
        'THPT' => ['Toán', 'Ngữ văn', 'Vật lý', 'Hóa học', 'Sinh học', 'Lịch sử', 'Địa lý'],
        'Van hoa 9+' => ['Toán 9+', 'Ngữ văn 9+', 'Khoa học tự nhiên 9+', 'Lịch sử - Địa lý 9+', 'Giáo dục công dân'],
        'CNTT' => ['Lập trình căn bản', 'Cơ sở dữ liệu', 'Mạng máy tính', 'Thiết kế web', 'An toàn thông tin'],
        'Du lich' => ['Nghiệp vụ lễ tân', 'Hướng dẫn du lịch', 'Quản trị khách sạn', 'Tuyến điểm du lịch', 'Dịch vụ nhà hàng'],
        'Marketing' => ['Marketing căn bản', 'Digital Marketing', 'Nghiên cứu thị trường', 'Truyền thông thương hiệu', 'Bán hàng'],
        'Tieng Anh' => ['Tiếng Anh giao tiếp', 'Tiếng Anh nghề nghiệp', 'Ngữ pháp ứng dụng', 'Nghe nói', 'Đọc viết'],
        'Tieng Hoa' => ['Tiếng Hoa sơ cấp', 'Tiếng Hoa giao tiếp', 'Hán tự ứng dụng', 'Nghe nói Hoa ngữ', 'Tiếng Hoa du lịch'],
        'Tieng Han' => ['Tiếng Hàn sơ cấp', 'Tiếng Hàn giao tiếp', 'Hangul ứng dụng', 'Nghe nói Hàn ngữ', 'Tiếng Hàn du lịch'],
    ];

    private const FACULTY_BY_AREA = [
        'THPT' => 'Phòng Đào tạo',
        'Van hoa 9+' => 'Văn hóa 9+',
        'CNTT' => 'Khoa Công nghệ',
        'Du lich' => 'Khoa Du lịch',
        'Marketing' => 'Khoa Kinh tế',
        'Tieng Anh' => 'Khoa Ngoại ngữ',
        'Tieng Hoa' => 'Khoa Ngoại ngữ',
        'Tieng Han' => 'Khoa Ngoại ngữ',
    ];

    public function run(): void
    {
        $tenant = Tenant::query()->where('code', 'VABIS')->firstOrFail();
        $owner = LmsUser::query()
            ->where('tenant_id', $tenant->id)
            ->whereIn('email', ['admin.lms@vabis.edu.vn', 'gv.lms@vabis.edu.vn'])
            ->orderByRaw("case when email = 'admin.lms@vabis.edu.vn' then 0 else 1 end")
            ->first();

        $unitIdsByName = AcademicUnit::query()
            ->where('tenant_id', $tenant->id)
            ->pluck('id', 'name')
            ->all();

        $root = $this->folder($tenant->id, null, self::ROOT_TITLE, 'Kho học liệu dùng chung toàn trường.', $owner?->id);
        $typeFolderIds = $this->createFolderTree($tenant->id, $root->id, $owner?->id, $unitIdsByName);

        $this->removePreviousSampleItems($tenant->id);
        $this->insertSampleItems($tenant->id, $owner?->id ?? 1, $typeFolderIds, $unitIdsByName);
    }

    private function createFolderTree(int $tenantId, int $rootId, ?int $ownerId, array $unitIdsByName): array
    {
        $typeFolderIds = [];

        foreach (self::AREAS as $areaKey) {
            $faculty = self::FACULTY_BY_AREA[$areaKey];
            $area = $this->folder(
                $tenantId,
                $rootId,
                self::AREA_TITLES[$areaKey],
                'Nhóm học liệu dùng chung cho '.$faculty.'.',
                $ownerId,
                $unitIdsByName[$faculty] ?? null,
                ['area_key' => $areaKey, 'khoa' => $faculty]
            );

            foreach (self::TYPE_FOLDERS as $type => $definition) {
                $folder = $this->folder(
                    $tenantId,
                    $area->id,
                    $definition['title'],
                    'Thư mục '.$definition['title'].' của '.self::AREA_TITLES[$areaKey].'.',
                    $ownerId,
                    $unitIdsByName[$faculty] ?? null,
                    ['area_key' => $areaKey, 'item_type' => $type, 'khoa' => $faculty]
                );

                $typeFolderIds[$areaKey][$type] = $folder->id;
            }
        }

        return $typeFolderIds;
    }

    private function folder(
        int $tenantId,
        ?int $parentId,
        string $title,
        string $description,
        ?int $ownerId,
        ?int $academicUnitId = null,
        array $metadata = []
    ): ContentRepositoryItem {
        return ContentRepositoryItem::query()->updateOrCreate(
            [
                'tenant_id' => $tenantId,
                'parent_id' => $parentId,
                'item_type' => 'folder',
                'title' => $title,
            ],
            [
                'academic_unit_id' => $academicUnitId,
                'description' => $description,
                'owner_id' => $ownerId ?? 1,
                'visibility' => 'tenant',
                'status' => 'published',
                'metadata' => array_merge([
                    'seed' => 'shared_learning_repository',
                    'cap_do' => $title === self::ROOT_TITLE ? 'Dùng chung' : $title,
                ], $metadata),
            ]
        );
    }

    private function removePreviousSampleItems(int $tenantId): void
    {
        $sampleIds = ContentRepositoryItem::query()
            ->where('tenant_id', $tenantId)
            ->where('storage_path', 'like', self::STORAGE_PREFIX.'%')
            ->pluck('id');

        if ($sampleIds->isEmpty()) {
            return;
        }

        ContentVersion::query()->whereIn('content_item_id', $sampleIds)->delete();
        ContentRepositoryItem::query()->whereIn('id', $sampleIds)->delete();
    }

    private function insertSampleItems(int $tenantId, int $ownerId, array $typeFolderIds, array $unitIdsByName): void
    {
        $now = now();
        $rows = [];

        for ($i = 1; $i <= self::SAMPLE_ITEM_COUNT; $i++) {
            $areaKey = self::AREAS[($i - 1) % count(self::AREAS)];
            $typeKeys = array_keys(self::TYPE_FOLDERS);
            $type = $typeKeys[($i - 1) % count($typeKeys)];
            $definition = self::TYPE_FOLDERS[$type];
            $faculty = self::FACULTY_BY_AREA[$areaKey];
            $subjects = self::SUBJECTS[$areaKey];
            $subject = $subjects[($i - 1) % count($subjects)];
            $level = self::AREA_TITLES[$areaKey];
            $duration = 15 + (($i * 5) % 166);
            $sequence = str_pad((string) $i, 5, '0', STR_PAD_LEFT);
            $storagePath = self::STORAGE_PREFIX.'item-'.$sequence.'.'.$definition['extension'];

            $rows[] = [
                'tenant_id' => $tenantId,
                'parent_id' => $typeFolderIds[$areaKey][$type],
                'academic_unit_id' => $unitIdsByName[$faculty] ?? null,
                'item_type' => $type,
                'title' => $this->title($areaKey, $type, $subject, $i),
                'description' => 'Học liệu mẫu dùng chung cho '.$subject.' thuộc '.$level.'.',
                'storage_path' => $storagePath,
                'mime_type' => $definition['mime'],
                'file_size' => 256000 + ($i * 137),
                'checksum' => hash('sha256', $storagePath.'|'.$tenantId),
                'owner_id' => $ownerId,
                'visibility' => 'tenant',
                'status' => $i % 11 === 0 ? 'approved' : 'published',
                'metadata' => json_encode([
                    'seed' => 'shared_learning_repository',
                    'sample_repository_item' => true,
                    'cap_do' => $level,
                    'mon_hoc' => $subject,
                    'khoa' => $faculty,
                    'CLO' => ['CLO'.(($i % 5) + 1), 'CLO'.((($i + 1) % 5) + 1)],
                    'PLO' => ['PLO'.(($i % 6) + 1)],
                    'thoi_luong_phut' => $duration,
                    'repository_path' => [
                        self::ROOT_TITLE,
                        self::AREA_TITLES[$areaKey],
                        $definition['title'],
                    ],
                    'version_policy' => 'major_minor',
                    'current_version' => '2',
                ], JSON_UNESCAPED_UNICODE),
                'created_at' => $now,
                'updated_at' => $now,
            ];

            if (count($rows) === 500) {
                DB::table('content_repository_items')->insert($rows);
                $rows = [];
            }
        }

        if ($rows !== []) {
            DB::table('content_repository_items')->insert($rows);
        }

        $this->insertVersions($tenantId, $ownerId);
    }

    private function insertVersions(int $tenantId, int $ownerId): void
    {
        $now = now();
        $versionRows = [];

        ContentRepositoryItem::query()
            ->where('tenant_id', $tenantId)
            ->where('storage_path', 'like', self::STORAGE_PREFIX.'%')
            ->orderBy('id')
            ->chunkById(500, function ($items) use (&$versionRows, $ownerId, $now): void {
                foreach ($items as $item) {
                    foreach ([1, 2] as $version) {
                        $versionRows[] = [
                            'tenant_id' => $item->tenant_id,
                            'content_item_id' => $item->id,
                            'version' => (string) $version,
                            'storage_path' => Str::replaceLast('.', "-v{$version}.", $item->storage_path),
                            'checksum' => hash('sha256', $item->storage_path.'|v'.$version),
                            'file_size' => max(1, (int) $item->file_size - ((2 - $version) * 1024)),
                            'change_note' => $version === 1 ? 'Khởi tạo học liệu mẫu.' : 'Chuẩn hóa metadata CLO/PLO và thời lượng.',
                            'created_by' => $ownerId,
                            'created_at' => $now->copy()->subDays(2 - $version),
                            'updated_at' => $now->copy()->subDays(2 - $version),
                        ];
                    }

                    if (count($versionRows) >= 1000) {
                        DB::table('content_versions')->insert($versionRows);
                        $versionRows = [];
                    }
                }
            });

        if ($versionRows !== []) {
            DB::table('content_versions')->insert($versionRows);
        }
    }

    private function title(string $areaKey, string $type, string $subject, int $index): string
    {
        $typeTitle = self::TYPE_FOLDERS[$type]['title'];

        return self::AREA_TITLES[$areaKey].' - '.$subject.' - '.$typeTitle.' mẫu '.str_pad((string) $index, 5, '0', STR_PAD_LEFT);
    }
}
