<script setup>
import { defineAsyncComponent, ref } from 'vue'
import EraLmsLayout from '@/Layouts/EraLmsLayout.vue'

const RichTextEditor = defineAsyncComponent(() => import('@/Components/Lms/RichTextEditor.vue'))

defineProps({
  apiHeaders: { type: Object, default: () => ({}) },
})

const rows = [
  { student: 'Học viên 1', question: 'Tự luận 3', score: '-' },
  { student: 'Học viên 2', question: 'Case study 1', score: '-' },
]
const gradingComment = ref('<p>Nhận xét cho học viên.</p>')
</script>

<template>
  <EraLmsLayout>
    <template #breadcrumb>Kiểm tra online / Chấm tay</template>

    <section class="mx-auto grid max-w-7xl gap-4 px-6 py-5 lg:grid-cols-[1fr_420px]">
      <main class="overflow-hidden border bg-white">
        <table class="w-full text-left text-sm">
          <thead class="bg-slate-50">
            <tr><th class="px-4 py-3">Học viên</th><th class="px-4 py-3">Câu hỏi</th><th class="px-4 py-3">Điểm</th></tr>
          </thead>
          <tbody>
            <tr v-for="r in rows" :key="r.student">
              <td class="px-4 py-3">{{ r.student }}</td>
              <td class="px-4 py-3">{{ r.question }}</td>
              <td class="px-4 py-3">{{ r.score }}</td>
            </tr>
          </tbody>
        </table>
      </main>

      <aside class="border bg-white p-4">
        <h2 class="text-sm font-semibold">Chấm câu tự luận</h2>
        <div class="mt-3">
          <RichTextEditor v-model="gradingComment" :api-headers="apiHeaders" min-height="220px" placeholder="Nhận xét, chèn ảnh/audio/video minh chứng..." />
        </div>
        <input class="mt-3 w-full rounded-md border px-3 py-2 text-sm" placeholder="Điểm" />
        <button class="mt-4 w-full rounded-md bg-slate-950 px-3 py-2 text-sm text-white">Lưu điểm</button>
      </aside>
    </section>
  </EraLmsLayout>
</template>
