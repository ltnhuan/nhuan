<script setup>
import { defineAsyncComponent, ref } from 'vue'
import EraLmsLayout from '@/Layouts/EraLmsLayout.vue'

const RichTextEditor = defineAsyncComponent(() => import('@/Components/Lms/RichTextEditor.vue'))

defineProps({
  apiHeaders: { type: Object, default: () => ({}) },
})

const feedback = ref('<p>Bài làm đạt yêu cầu, cần bổ sung minh chứng bước kiểm tra cuối.</p>')

const submissions = [
  { learner: 'Học viên 001', status: 'Chờ chấm', time: '2026-06-03 10:12', late: false },
  { learner: 'Học viên 018', status: 'Nộp trễ', time: '2026-06-04 08:31', late: true },
  { learner: 'Học viên 044', status: 'Đã chấm', time: '2026-06-02 15:44', late: false },
]

const criteria = [
  { title: 'Độ đúng yêu cầu', max: 4, score: 3.5 },
  { title: 'Chất lượng triển khai', max: 3, score: 2.5 },
  { title: 'Trình bày và minh chứng', max: 3, score: 2.5 },
]
</script>

<template>
  <EraLmsLayout>
    <template #breadcrumb>Bài tập / Chấm bài</template>

    <section class="border-b bg-white">
      <div class="mx-auto max-w-7xl px-6 py-5">
        <div class="flex items-center justify-between gap-4">
          <div>
            <h1 class="text-lg font-semibold">Teacher Grading</h1>
            <p class="mt-1 text-sm text-slate-600">Chấm bài bằng rubric, nhận xét, trả bài yêu cầu sửa và duyệt điểm cuối.</p>
          </div>
          <button class="rounded-md border px-4 py-2 text-sm">Duyệt điểm hàng loạt</button>
        </div>
        <div class="mt-5 grid gap-3 md:grid-cols-4">
          <select class="rounded-md border px-3 py-2 text-sm"><option>Tất cả bài tập</option></select>
          <select class="rounded-md border px-3 py-2 text-sm"><option>Chưa nộp</option><option>Đã nộp</option><option>Nộp trễ</option><option>Đã chấm</option></select>
          <select class="rounded-md border px-3 py-2 text-sm"><option>Tất cả lớp</option></select>
          <input class="rounded-md border px-3 py-2 text-sm" placeholder="Tìm học viên" />
        </div>
      </div>
    </section>

    <section class="mx-auto grid max-w-7xl gap-4 px-6 py-5 xl:grid-cols-[380px_1fr]">
      <aside class="border bg-white">
        <div class="border-b p-4 text-sm font-semibold">Danh sách bài nộp</div>
        <div class="divide-y">
          <button v-for="item in submissions" :key="item.learner" class="flex w-full items-center justify-between px-4 py-3 text-left text-sm hover:bg-slate-50">
            <span><span class="block font-medium">{{ item.learner }}</span><span class="text-xs text-slate-500">{{ item.time }}</span></span>
            <span :class="item.late ? 'text-rose-700' : 'text-slate-700'">{{ item.status }}</span>
          </button>
        </div>
      </aside>

      <main class="grid gap-4 lg:grid-cols-[1fr_340px]">
        <div class="space-y-4">
          <div class="border bg-white p-5">
            <h2 class="text-sm font-semibold">Bài làm</h2>
            <p class="mt-3 text-sm text-slate-700">Học viên trình bày quy trình kiểm tra an toàn, đính kèm ảnh minh chứng và video thao tác.</p>
            <div class="mt-4 rounded-md border bg-slate-50 p-3 text-sm">submission-report.pdf · safety-video.mp4 · link minh chứng</div>
          </div>
          <div class="border bg-white p-5">
            <h2 class="text-sm font-semibold">Rubric</h2>
            <div class="mt-3 divide-y">
              <div v-for="criterion in criteria" :key="criterion.title" class="grid grid-cols-[1fr_120px] gap-3 py-3 text-sm">
                <div><div class="font-medium">{{ criterion.title }}</div><div class="text-xs text-slate-500">Tối đa {{ criterion.max }} điểm</div></div>
                <input class="rounded-md border px-3 py-2 text-right" :value="criterion.score" />
              </div>
            </div>
          </div>
        </div>

        <aside class="space-y-4">
          <div class="border bg-white p-4">
            <h2 class="text-sm font-semibold">Nhận xét</h2>
            <div class="mt-3">
              <RichTextEditor v-model="feedback" :api-headers="apiHeaders" min-height="180px" placeholder="Nhận xét, chèn ảnh/audio/video minh chứng..." />
            </div>
            <button class="mt-3 w-full rounded-md border px-3 py-2 text-sm">AI gợi ý nhận xét</button>
            <div class="mt-3 rounded-md bg-slate-50 p-3 text-sm text-slate-700">AI suggested score: 8.1/10</div>
          </div>
          <div class="border bg-white p-4">
            <h2 class="text-sm font-semibold">Điểm</h2>
            <input class="mt-3 w-full rounded-md border px-3 py-2 text-sm" value="8.5" />
            <div class="mt-3 grid grid-cols-2 gap-2">
              <button class="rounded-md border px-3 py-2 text-sm">Trả bài</button>
              <button class="rounded-md bg-slate-950 px-3 py-2 text-sm text-white">Lưu điểm</button>
            </div>
            <button class="mt-2 w-full rounded-md bg-emerald-700 px-3 py-2 text-sm text-white">Duyệt điểm</button>
          </div>
        </aside>
      </main>
    </section>
  </EraLmsLayout>
</template>
