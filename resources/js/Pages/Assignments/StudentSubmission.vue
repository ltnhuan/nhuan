<script setup>
import { defineAsyncComponent, ref } from 'vue'
import EraLmsLayout from '@/Layouts/EraLmsLayout.vue'

const RichTextEditor = defineAsyncComponent(() => import('@/Components/Lms/RichTextEditor.vue'))

defineProps({
  apiHeaders: { type: Object, default: () => ({}) },
})

const submissionBody = ref('<p>Mô tả bài làm, quy trình thực hiện và chèn minh chứng media tại đây.</p>')

const history = [
  { no: 1, status: 'Submitted', at: '2026-06-01 09:20', score: '-' },
  { no: 2, status: 'Returned', at: '2026-06-02 14:10', score: '-' },
  { no: 3, status: 'Graded', at: '2026-06-03 16:45', score: '8.5/10' },
]
</script>

<template>
  <EraLmsLayout>
    <template #breadcrumb>Bài tập / Nộp bài</template>

    <section class="mx-auto grid max-w-7xl gap-4 px-6 py-5 lg:grid-cols-[minmax(0,1fr)_360px]">
      <main class="min-w-0 space-y-4">
        <div class="border bg-white p-5">
          <div class="flex items-start justify-between gap-4">
            <div>
              <h1 class="text-lg font-semibold">Báo cáo thực hành an toàn</h1>
              <p class="mt-1 text-sm text-slate-600">Nộp minh chứng thực hành, mô tả quy trình và link video quan sát. Điểm đạt mở khóa bài tiếp theo: 5/10.</p>
            </div>
            <span class="rounded bg-amber-50 px-2 py-1 text-xs text-amber-700">Due 2026-06-12</span>
          </div>
          <div class="mt-5 grid gap-3 md:grid-cols-4">
            <div class="border bg-slate-50 p-3 text-sm"><div class="text-slate-500">Loại</div><div class="font-medium">Cá nhân</div></div>
            <div class="border bg-slate-50 p-3 text-sm"><div class="text-slate-500">Nộp</div><div class="font-medium">Mixed</div></div>
            <div class="border bg-slate-50 p-3 text-sm"><div class="text-slate-500">Lần nộp</div><div class="font-medium">3/3</div></div>
            <div class="border bg-slate-50 p-3 text-sm"><div class="text-slate-500">Rubric</div><div class="font-medium">10 điểm</div></div>
          </div>
        </div>

        <div class="border bg-white p-5">
          <h2 class="text-sm font-semibold">Bài nộp</h2>
          <div class="mt-4 space-y-3">
            <RichTextEditor v-model="submissionBody" :api-headers="apiHeaders" min-height="260px" placeholder="Nội dung bài nộp, ảnh, âm thanh, video..." />
            <input class="w-full rounded-md border px-3 py-2 text-sm" placeholder="Link bài làm hoặc video" />
            <div class="flex items-center justify-between rounded-md border border-dashed px-4 py-5 text-sm">
              <span class="text-slate-600">Kéo thả file hoặc chọn file</span>
              <button class="rounded-md border px-3 py-2">Chọn file</button>
            </div>
            <div class="flex gap-2">
              <button class="rounded-md border px-4 py-2 text-sm">Lưu nháp</button>
              <button class="rounded-md bg-slate-950 px-4 py-2 text-sm text-white">Nộp bài</button>
              <button class="rounded-md border px-4 py-2 text-sm">Nộp lại</button>
            </div>
          </div>
        </div>
      </main>

      <aside class="min-w-0 space-y-4">
        <div class="border bg-white p-4">
          <h2 class="text-sm font-semibold">Lịch sử nộp</h2>
          <div class="mt-3 divide-y text-sm">
            <div v-for="item in history" :key="item.no" class="flex items-center justify-between py-3">
              <div><div class="font-medium">Phiên bản {{ item.no }}</div><div class="text-xs text-slate-500">{{ item.at }}</div></div>
              <div class="text-right"><div>{{ item.status }}</div><div class="text-xs text-slate-500">{{ item.score }}</div></div>
            </div>
          </div>
        </div>
        <div class="border bg-white p-4 text-sm">
          <h2 class="font-semibold">Điểm và nhận xét</h2>
          <div class="mt-3 bg-emerald-50 p-3 text-emerald-800">8.5/10 · Đạt</div>
          <p class="mt-3 text-slate-700">Bài làm đầy đủ minh chứng, cần bổ sung ảnh chụp bước kiểm tra cuối.</p>
        </div>
      </aside>
    </section>
  </EraLmsLayout>
</template>
