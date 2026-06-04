<script setup>
import EraLmsLayout from '@/Layouts/EraLmsLayout.vue'

const outline = [
  { title: 'Chương 1: Nhập môn', children: [{ title: 'Bài 1.1: Tổng quan', components: ['Text', 'Video', 'Quiz'] }] },
  { title: 'Chương 2: Thực hành', children: [{ title: 'Bài 2.1: Case study', components: ['PDF', 'Assignment'] }] },
]
const components = ['Text','Video','PDF','File','Link','SCORM placeholder','Quiz placeholder','Assignment placeholder','Forum placeholder']
</script>

<template>
  <EraLmsLayout>
    <template #breadcrumb>Khóa học / Studio bài giảng</template>
    <div class="grid grid-cols-[300px_1fr_320px] gap-3">
      <aside class="rounded-lg border bg-white p-3">
        <div class="mb-2 flex justify-between text-sm font-semibold">
          <span>Course outline</span>
          <button class="text-blue-700">+ Section</button>
        </div>
        <div v-for="section in outline" :key="section.title" class="mb-2 rounded-md border p-2 text-sm">
          <div class="font-medium">{{ section.title }}</div>
          <div v-for="unit in section.children" :key="unit.title" class="mt-2 rounded bg-slate-50 p-2">
            <div class="text-xs font-medium">{{ unit.title }}</div>
            <div class="mt-1 flex flex-wrap gap-1">
              <span v-for="component in unit.components" :key="component" class="rounded-full bg-white px-2 py-1 text-[11px] text-slate-600">{{ component }}</span>
            </div>
          </div>
          <div class="mt-2 flex gap-1 text-[11px]"><button class="rounded border px-2 py-1">↑</button><button class="rounded border px-2 py-1">↓</button><button class="rounded border px-2 py-1">+ Unit</button></div>
        </div>
      </aside>
      <main class="rounded-lg border bg-white p-3">
        <div class="flex flex-wrap gap-2">
          <button v-for="component in components" :key="component" class="rounded-md border px-2 py-1 text-xs">+ {{ component }}</button>
        </div>
        <div class="mt-4 rounded-lg border border-dashed p-8 text-center text-sm text-slate-500">
          Chọn unit để soạn nội dung. Hỗ trợ version snapshot, validation trước publish, preview as student và reorder bằng nút lên/xuống.
        </div>
        <div class="mt-3 flex gap-2">
          <button class="rounded bg-slate-800 px-3 py-2 text-xs text-white">Preview as student</button>
          <button class="rounded bg-amber-600 px-3 py-2 text-xs text-white">Submit review</button>
          <button class="rounded bg-blue-900 px-3 py-2 text-xs text-white">Validate & Publish</button>
        </div>
      </main>
      <aside class="rounded-lg border bg-white p-3 text-sm">
        <h3 class="font-semibold">Settings / Visibility / Completion</h3>
        <label class="mt-3 block text-xs">Trạng thái</label>
        <select class="mt-1 w-full rounded border px-2 py-1 text-xs"><option>Draft</option><option>Review</option><option>Approved</option><option>Published</option></select>
        <label class="mt-3 block text-xs">Completion</label>
        <select class="mt-1 w-full rounded border px-2 py-1 text-xs"><option>Required components</option><option>Manual approval</option></select>
        <div class="mt-4 rounded-md bg-amber-50 p-2 text-xs text-amber-700">Cảnh báo publish: video/pdf/file/scorm phải có học liệu repository; activity type phải enabled.</div>
      </aside>
    </div>
  </EraLmsLayout>
</template>
