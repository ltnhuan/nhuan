<script setup>
import { ref } from 'vue'
import EraLmsLayout from '@/Layouts/EraLmsLayout.vue'

const selected = ref('Học viên 1')
const rows = [
  { name: 'Học viên 1', status: 'Đã hoàn thành', percent: 95, suspicious: 0, note: 'Hoàn thành hợp lệ' },
  { name: 'Học viên 2', status: 'Xem dưới 50%', percent: 38, suspicious: 0, note: 'Cần nhắc học tiếp' },
  { name: 'Học viên 3', status: 'Suspicious cao', percent: 72, suspicious: 75, note: 'Seek bất thường' },
]
</script>

<template>
  <EraLmsLayout>
    <template #breadcrumb>Video / Phân tích lớp học</template>

    <section class="mx-auto max-w-7xl px-6 py-5">
      <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
          <h1 class="text-lg font-semibold text-slate-950">Phân tích tiến độ xem video</h1>
          <p class="mt-1 text-sm text-slate-600">Dashboard đọc từ summary, không truy vấn raw event log trên màn hình chính.</p>
        </div>
        <button class="rounded-md border border-slate-300 px-4 py-2 text-sm">Xuất báo cáo</button>
      </div>

      <div class="mt-5 grid gap-3 md:grid-cols-4">
        <div class="border bg-white p-4"><div class="text-sm text-slate-500">Chưa xem</div><div class="mt-1 text-2xl font-semibold">128</div></div>
        <div class="border bg-white p-4"><div class="text-sm text-slate-500">Dưới 50%</div><div class="mt-1 text-2xl font-semibold">94</div></div>
        <div class="border bg-white p-4"><div class="text-sm text-slate-500">Hoàn thành</div><div class="mt-1 text-2xl font-semibold">246</div></div>
        <div class="border bg-white p-4"><div class="text-sm text-slate-500">Suspicious cao</div><div class="mt-1 text-2xl font-semibold">12</div></div>
      </div>

      <div class="mt-5 grid gap-4 lg:grid-cols-[1fr_360px]">
        <div class="overflow-hidden border bg-white">
          <table class="w-full text-left text-sm">
            <thead class="bg-slate-50 text-xs uppercase text-slate-500">
              <tr><th class="px-4 py-3">Học viên</th><th class="px-4 py-3">Trạng thái</th><th class="px-4 py-3">Đã xem</th><th class="px-4 py-3">Suspicious</th><th class="px-4 py-3">Ghi chú</th></tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              <tr v-for="row in rows" :key="row.name" class="cursor-pointer hover:bg-slate-50" @click="selected = row.name">
                <td class="px-4 py-3 font-medium">{{ row.name }}</td>
                <td class="px-4 py-3">{{ row.status }}</td>
                <td class="px-4 py-3">{{ row.percent }}%</td>
                <td class="px-4 py-3">{{ row.suspicious }}</td>
                <td class="px-4 py-3">{{ row.note }}</td>
              </tr>
            </tbody>
          </table>
        </div>

        <aside class="border bg-white p-4">
          <h2 class="text-sm font-semibold text-slate-950">Lịch sử xem</h2>
          <p class="mt-1 text-sm text-slate-600">{{ selected }}</p>
          <ol class="mt-4 space-y-3 text-sm text-slate-700">
            <li class="rounded-md border p-3">Bắt đầu phiên xem lúc 08:15</li>
            <li class="rounded-md border p-3">Heartbeat hợp lệ tại 120 giây</li>
            <li class="rounded-md border border-amber-200 bg-amber-50 p-3 text-amber-900">Seek từ 10 giây lên 900 giây trong 3 giây</li>
          </ol>
        </aside>
      </div>
    </section>
  </EraLmsLayout>
</template>
