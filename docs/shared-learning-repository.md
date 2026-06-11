# Shared Learning Repository

Kho học liệu dùng chung được seed bằng `Database\Seeders\SharedLearningRepositorySeeder`.

## Cấu trúc cây

Seeder tạo folder gốc `Repository`, sau đó tạo 8 nhóm:

- `THPT`
- `Văn hóa 9+`
- `CNTT`
- `Du lịch`
- `Marketing`
- `Tiếng Anh`
- `Tiếng Hoa`
- `Tiếng Hàn`

Mỗi nhóm có 7 thư mục con:

- `Video`
- `PDF`
- `Slide`
- `Quiz Template`
- `Assignment Template`
- `Rubric Template`
- `Exam Blueprint`

## Metadata

Mỗi item mẫu có metadata JSON:

- `cap_do`
- `mon_hoc`
- `khoa`
- `CLO`
- `PLO`
- `thoi_luong_phut`
- `repository_path`
- `current_version`

## Dữ liệu mẫu và version history

Seeder tạo 5.000 file repository mẫu dưới prefix storage path `eralms/shared-repository/`.

Mỗi item có 2 bản ghi trong `content_versions`:

- `v1`: khởi tạo học liệu mẫu.
- `v2`: chuẩn hóa metadata CLO/PLO và thời lượng.

Seeder được gọi mặc định từ `DatabaseSeeder` khi `ERALMS_SEED_SHARED_REPOSITORY=true`.
