<script setup>
import EraLmsLayout from '@/Layouts/EraLmsLayout.vue'
import EnterpriseTable from '@/Components/EnterpriseTable.vue'

const rows = [
  { code: 'COURSE001', title: 'Môn chung 1', level: 'college', course_type: 'blended', academic_unit: 'Khoa Công nghệ', status: 'draft' },
  { code: 'COURSE002', title: 'Tiếng Anh giao tiếp', level: 'language_center', course_type: 'self_paced', academic_unit: 'Ngoại ngữ', status: 'review' },
]
</script>

<template>
  <EraLmsLayout>
    <EnterpriseTable
      title="Quản trị khóa học"
      :columns="['code','title','level','course_type','academic_unit','status']"
      :rows="rows"
      :filters="['Trạng thái','Khoa','Loại khóa','Hệ đào tạo','Campus']"
    />
    <section class="mt-3 grid grid-cols-3 gap-3 text-sm">
      <div class="rounded-lg border bg-white p-4">
        <h3 class="font-semibold">Bulk action</h3>
        <p class="mt-2 text-xs text-slate-600">Publish/archive nhiều khóa nếu đủ quyền course.publish/course.delete.</p>
      </div>
      <div class="rounded-lg border bg-white p-4">
        <h3 class="font-semibold">Drawer xem nhanh</h3>
        <p class="mt-2 text-xs text-slate-600">Mô tả, owner, phiên bản, lịch sử duyệt và cấu trúc outline.</p>
      </div>
      <div class="rounded-lg border bg-white p-4">
        <h3 class="font-semibold">Bộ lọc enterprise</h3>
        <p class="mt-2 text-xs text-slate-600">Trạng thái, khoa, loại khóa, level, campus, visibility.</p>
      </div>
    </section>
  </EraLmsLayout>
</template>
