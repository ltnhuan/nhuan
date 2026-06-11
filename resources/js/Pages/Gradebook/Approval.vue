<script setup>
import EraLmsLayout from '@/Layouts/EraLmsLayout.vue'

const steps = [
  ['Giảng viên gửi', 'submitted', 'Đã gửi'],
  ['Trưởng khoa duyệt', 'approved', 'Đã duyệt'],
  ['Phòng đào tạo khóa', 'locked', 'Đang chờ khóa'],
  ['Đồng bộ SIS', 'synced', 'Đã đồng bộ'],
]

const batches = [
  { title: 'Lập trình Web K1', status: 'Chờ duyệt', learners: 63, avg: '76.2%' },
  { title: 'Tiếng Anh A2', status: 'Đã duyệt', learners: 58, avg: '81.4%' },
  { title: 'Điện cơ bản', status: 'Đã khóa', learners: 62, avg: '72.8%' },
]
</script>

<template>
  <EraLmsLayout>
    <template #breadcrumb>Sổ điểm / Duyệt điểm</template>
    <section class="mx-auto max-w-7xl px-6 py-5">
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-lg font-semibold">Luồng duyệt điểm</h1>
          <p class="mt-1 text-sm text-slate-600">Luồng gửi duyệt, phê duyệt, khóa và chuẩn bị đồng bộ SIS.</p>
        </div>
        <button class="rounded-md bg-slate-950 px-3 py-2 text-sm text-white">Đồng bộ SIS</button>
      </div>

      <div class="mt-5 grid gap-3 md:grid-cols-4">
        <div v-for="step in steps" :key="step[0]" class="border bg-white p-4">
          <div class="text-xs uppercase text-slate-500">{{ step[1] }}</div>
          <div class="mt-2 font-semibold">{{ step[0] }}</div>
          <div class="mt-3 text-sm text-slate-600">{{ step[2] }}</div>
        </div>
      </div>

      <div class="mt-5 overflow-hidden border bg-white">
        <table class="w-full text-left text-sm">
          <thead class="bg-slate-50 text-xs uppercase text-slate-500">
            <tr>
              <th class="px-4 py-3">Lớp</th>
              <th class="px-4 py-3">Trạng thái</th>
              <th class="px-4 py-3">Học viên</th>
              <th class="px-4 py-3">Điểm TB</th>
              <th class="px-4 py-3">Hành động</th>
            </tr>
          </thead>
          <tbody class="divide-y">
            <tr v-for="batch in batches" :key="batch.title">
              <td class="px-4 py-3 font-medium">{{ batch.title }}</td>
              <td class="px-4 py-3">{{ batch.status }}</td>
              <td class="px-4 py-3">{{ batch.learners }}</td>
              <td class="px-4 py-3">{{ batch.avg }}</td>
              <td class="px-4 py-3">
                <div class="flex gap-2">
                  <button class="rounded-md border px-2 py-1">Duyệt</button>
                  <button class="rounded-md border px-2 py-1">Từ chối</button>
                  <button class="rounded-md border px-2 py-1">Khoá</button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>
  </EraLmsLayout>
</template>
