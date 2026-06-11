<script setup>
import { ref } from 'vue'
import EraLmsLayout from '@/Layouts/EraLmsLayout.vue'

const categories = [
  { name: 'Quiz', weight: 30, method: 'Trọng số', items: ['Quiz chương 1', 'Quiz chương 2'] },
  { name: 'Bài tập', weight: 30, method: 'Trung bình', items: ['Bài tập thực hành', 'Dự án nhóm'] },
  { name: 'Chuyên cần', weight: 10, method: 'Tổng', items: ['Chuyên cần'] },
  { name: 'Thi cuối kỳ', weight: 30, method: 'Công thức', items: ['Thi cuối kỳ'] },
]

const formula = ref('(quiz * 0.3) + (bai_tap * 0.3) + (chuyen_can * 0.1) + (cuoi_ky * 0.3)')
</script>

<template>
  <EraLmsLayout>
    <template #breadcrumb>Sổ điểm / Trình tạo</template>

    <section class="border-b bg-white">
      <div class="mx-auto max-w-7xl px-6 py-5">
        <div class="flex items-center justify-between">
          <div>
            <h1 class="text-lg font-semibold">Trình tạo sổ điểm</h1>
            <p class="mt-1 text-sm text-slate-600">Cấu hình nhóm điểm, trọng số, nguồn mục và công thức tính điểm.</p>
          </div>
          <div class="flex gap-2">
            <button class="rounded-md border px-3 py-2 text-sm">Lưu nháp</button>
            <button class="rounded-md bg-slate-950 px-3 py-2 text-sm text-white">Kích hoạt</button>
          </div>
        </div>

        <div class="mt-5 grid gap-3 md:grid-cols-5">
          <input class="rounded-md border px-3 py-2 text-sm md:col-span-2" value="Sổ điểm Lập trình Web - Kỳ 1" />
          <select class="rounded-md border px-3 py-2 text-sm">
            <option>Trọng số</option>
            <option>Điểm</option>
            <option>Công thức</option>
          </select>
          <input class="rounded-md border px-3 py-2 text-sm" value="Đạt 50%" />
          <button class="rounded-md border px-3 py-2 text-sm">Thêm nhóm</button>
        </div>
      </div>
    </section>

    <section class="mx-auto grid max-w-7xl gap-4 px-6 py-5 lg:grid-cols-[1fr_360px]">
      <div class="space-y-3">
        <article v-for="cat in categories" :key="cat.name" class="border bg-white">
          <div class="grid grid-cols-[40px_1fr_120px_140px_120px] items-center border-b px-4 py-3 text-sm">
            <button class="h-8 rounded-md border">↕</button>
            <strong>{{ cat.name }}</strong>
            <input class="rounded-md border px-2 py-1 text-right" :value="`${cat.weight}%`" />
            <select class="rounded-md border px-2 py-1"><option>{{ cat.method }}</option></select>
            <button class="rounded-md border px-2 py-1">Thêm mục</button>
          </div>
          <div class="divide-y">
            <div v-for="item in cat.items" :key="item" class="grid grid-cols-[40px_1fr_130px_110px_120px] items-center px-4 py-3 text-sm">
              <button class="h-8 rounded-md border">↕</button>
              <span>{{ item }}</span>
              <select class="rounded-md border px-2 py-1">
                <option>Quiz</option>
                <option>Bài tập</option>
                <option>Thủ công</option>
              </select>
              <input class="rounded-md border px-2 py-1 text-right" value="10" />
              <label class="flex items-center gap-2">
                <input type="checkbox" checked />
                Bắt buộc
              </label>
            </div>
          </div>
        </article>
      </div>

      <aside class="border bg-white p-4">
        <h2 class="text-sm font-semibold">Công thức</h2>
        <input v-model="formula" class="mt-3 w-full rounded-md border p-3 font-mono text-sm" />
        <div class="mt-4 space-y-3 text-sm">
          <div class="flex justify-between"><span>Tổng trọng số</span><strong>100%</strong></div>
          <div class="flex justify-between"><span>Mục bắt buộc</span><strong>6</strong></div>
          <div class="flex justify-between"><span>Mục nguồn tự động</span><strong>5</strong></div>
        </div>
        <button class="mt-5 w-full rounded-md bg-blue-900 px-3 py-2 text-sm text-white">Kiểm tra công thức</button>
      </aside>
    </section>
  </EraLmsLayout>
</template>
