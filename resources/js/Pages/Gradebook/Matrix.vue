<script setup>
import { defineAsyncComponent, ref } from 'vue'
import EraLmsLayout from '@/Layouts/EraLmsLayout.vue'

const RichTextEditor = defineAsyncComponent(() => import('@/Components/Lms/RichTextEditor.vue'))

defineProps({
  apiHeaders: { type: Object, default: () => ({}) },
})

const items = ['Quiz 30%', 'Assignment 30%', 'Attendance 10%', 'Final 30%']
const rows = Array.from({ length: 18 }, (_, i) => ({
  code: `SV${String(i + 1).padStart(5, '0')}`,
  name: `Học viên ${i + 1}`,
  scores: [8.2, 7.5, 9.5, 6.8].map((v) => (v + (i % 5) / 10).toFixed(1)),
  total: (74 + i % 12).toFixed(1),
  status: i % 6 === 0 ? 'Không đạt' : 'Đạt',
}))
const logs = ['GV Demo override Final từ 6.0 sang 6.8', 'Pull Quiz từ exam result', 'Phòng đào tạo khóa điểm chuyên cần']
const overrideReason = ref('<p>Lý do override và minh chứng kèm theo.</p>')
</script>

<template>
  <EraLmsLayout>
    <template #breadcrumb>Sổ điểm / Matrix</template>

    <section class="border-b bg-white">
      <div class="mx-auto max-w-7xl px-6 py-5">
        <div class="flex items-center justify-between">
          <div>
            <h1 class="text-lg font-semibold">Grade Matrix</h1>
            <p class="mt-1 text-sm text-slate-600">Bảng điểm dạng Excel, tối ưu cho lớp lớn và có lịch sử chỉnh sửa.</p>
          </div>
          <div class="flex gap-2">
            <button class="rounded-md border px-3 py-2 text-sm">Pull nguồn</button>
            <button class="rounded-md border px-3 py-2 text-sm">Recalculate</button>
            <button class="rounded-md bg-slate-950 px-3 py-2 text-sm text-white">Submit duyệt</button>
          </div>
        </div>
      </div>
    </section>

    <section class="mx-auto grid max-w-7xl gap-4 px-6 py-5 lg:grid-cols-[1fr_360px]">
      <div class="overflow-auto border bg-white" style="max-height:620px">
        <table class="w-full min-w-[980px] table-fixed text-left text-sm">
          <thead class="sticky top-0 bg-slate-50 text-xs uppercase text-slate-500">
            <tr>
              <th class="w-28 px-3 py-3">Mã HV</th>
              <th class="w-48 px-3 py-3">Học viên</th>
              <th v-for="item in items" :key="item" class="w-36 px-3 py-3">{{ item }}</th>
              <th class="w-28 px-3 py-3">Tổng</th>
              <th class="w-32 px-3 py-3">Trạng thái</th>
            </tr>
          </thead>
          <tbody class="divide-y">
            <tr v-for="row in rows" :key="row.code" class="hover:bg-blue-50">
              <td class="px-3 py-2 font-medium">{{ row.code }}</td>
              <td class="px-3 py-2">{{ row.name }}</td>
              <td v-for="score in row.scores" :key="score" class="px-3 py-2"><input class="w-20 rounded-md border px-2 py-1 text-right" :value="score" /></td>
              <td class="px-3 py-2 font-semibold">{{ row.total }}%</td>
              <td class="px-3 py-2"><span class="rounded-md px-2 py-1 text-xs" :class="row.status === 'Đạt' ? 'bg-emerald-50 text-emerald-700' : 'bg-rose-50 text-rose-700'">{{ row.status }}</span></td>
            </tr>
          </tbody>
        </table>
      </div>

      <aside class="border bg-white p-4">
        <h2 class="text-sm font-semibold">Lịch sử chỉnh sửa</h2>
        <div class="mt-4 space-y-3">
          <div v-for="log in logs" :key="log" class="border-l-2 border-blue-800 pl-3 text-sm">
            <p>{{ log }}</p>
            <span class="text-xs text-slate-500">Hôm nay 09:30</span>
          </div>
        </div>
        <div class="mt-5">
          <RichTextEditor v-model="overrideReason" :api-headers="apiHeaders" min-height="180px" placeholder="Lý do override, có thể đính kèm media..." />
        </div>
        <button class="mt-3 w-full rounded-md bg-blue-900 px-3 py-2 text-sm text-white">Ghi override</button>
      </aside>
    </section>
  </EraLmsLayout>
</template>
