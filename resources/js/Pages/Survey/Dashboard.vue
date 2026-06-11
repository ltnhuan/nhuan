<script setup>
import EraLmsLayout from '@/Layouts/EraLmsLayout.vue'

const metrics = [
  ['Chiến dịch đang chạy', '18', '+4 tuần này'],
  ['Điểm TB', '4.18/5', '+0.22'],
  ['NPS', '47', '+9'],
  ['Minh chứng', '126', 'AUN-QA, nội bộ'],
]

const heatmap = [
  ['Chuyên môn', 4.6, 'bg-emerald-600'],
  ['Phương pháp', 3.8, 'bg-amber-500'],
  ['Tương tác', 4.1, 'bg-lime-600'],
  ['Đúng giờ', 4.7, 'bg-emerald-700'],
  ['Hỗ trợ học viên', 3.5, 'bg-orange-500'],
  ['Khối lượng học tập', 3.2, 'bg-rose-500'],
]

const trends = [3.7, 3.9, 4.0, 3.8, 4.2, 4.3, 4.18]

const improvements = [
  ['Tăng hỗ trợ học viên', 'Đang xử lý', 'Giảng viên', '15/06'],
  ['Cân đối khối lượng bài tập', 'Mở', 'Đào tạo', '20/06'],
  ['Bổ sung ví dụ thực tế', 'Hoàn tất', 'Khoa', '01/06'],
]
</script>

<template>
  <EraLmsLayout>
    <template #breadcrumb>Khảo sát / Tổng quan</template>
    <section class="bg-white border-b">
      <div class="mx-auto max-w-7xl px-6 py-5">
        <div class="flex items-center justify-between">
          <div>
            <h1 class="text-lg font-semibold">Bảng điều khiển khảo sát và cải tiến chất lượng</h1>
            <p class="mt-1 text-sm text-slate-600">BGH, Khoa, Đào tạo và Giảng viên theo dõi khảo sát, NPS, hành động cải tiến và minh chứng kiểm định.</p>
          </div>
          <div class="flex gap-2">
            <button class="rounded-md border px-3 py-2 text-sm">Xuất Excel</button>
            <button class="rounded-md bg-slate-950 px-3 py-2 text-sm text-white">Xuất PDF</button>
          </div>
        </div>
        <div class="mt-5 flex gap-2 text-sm">
          <button class="rounded-md bg-blue-900 px-3 py-2 text-white">BGH</button>
          <button class="rounded-md border px-3 py-2">Khoa</button>
          <button class="rounded-md border px-3 py-2">Đào tạo</button>
          <button class="rounded-md border px-3 py-2">Giảng viên</button>
        </div>
      </div>
    </section>

    <section class="mx-auto max-w-7xl px-6 py-5">
      <div class="grid gap-3 md:grid-cols-4">
        <article v-for="metric in metrics" :key="metric[0]" class="border bg-white p-4">
          <div class="text-xs uppercase text-slate-500">{{ metric[0] }}</div>
          <div class="mt-2 text-2xl font-semibold">{{ metric[1] }}</div>
          <div class="mt-1 text-sm text-emerald-700">{{ metric[2] }}</div>
        </article>
      </div>

      <div class="mt-4 grid gap-4 lg:grid-cols-[1fr_420px]">
        <section class="border bg-white p-4">
          <div class="flex items-center justify-between">
            <h2 class="text-sm font-semibold">Biểu đồ đánh giá theo tiêu chí</h2>
            <select class="rounded-md border px-2 py-1 text-sm"><option>Theo khoa</option><option>Theo lớp</option><option>Theo khóa học</option></select>
          </div>
          <div class="mt-4 grid gap-3 md:grid-cols-2">
            <div v-for="item in heatmap" :key="item[0]" class="grid grid-cols-[150px_1fr_48px] items-center gap-3 text-sm">
              <span>{{ item[0] }}</span>
              <div class="h-8 bg-slate-100"><div class="h-8" :class="item[2]" :style="{ width: `${item[1] * 20}%` }"></div></div>
              <strong class="text-right">{{ item[1] }}</strong>
            </div>
          </div>
        </section>

        <section class="border bg-white p-4">
          <h2 class="text-sm font-semibold">Biến động điểm trung bình</h2>
          <div class="mt-5 flex h-52 items-end gap-3 border-b border-l px-4">
            <div v-for="(value, index) in trends" :key="index" class="flex flex-1 flex-col items-center gap-2">
              <div class="w-full rounded-t bg-cyan-700" :style="{ height: `${value * 38}px` }"></div>
              <span class="text-xs text-slate-500">T{{ index + 1 }}</span>
            </div>
          </div>
        </section>
      </div>

      <div class="mt-4 grid gap-4 lg:grid-cols-[1fr_360px]">
        <section class="border bg-white">
          <div class="grid grid-cols-[1fr_120px_120px_90px] border-b px-4 py-3 text-xs font-semibold uppercase text-slate-500">
            <span>Vấn đề</span><span>Trạng thái</span><span>Đơn vị</span><span>Hạn</span>
          </div>
          <div v-for="row in improvements" :key="row[0]" class="grid grid-cols-[1fr_120px_120px_90px] px-4 py-3 text-sm">
            <span>{{ row[0] }}</span><span>{{ row[1] }}</span><span>{{ row[2] }}</span><span>{{ row[3] }}</span>
          </div>
        </section>

        <section class="border bg-white p-4">
          <h2 class="text-sm font-semibold">Minh chứng kiểm định</h2>
          <div class="mt-4 space-y-3 text-sm">
            <div class="flex justify-between"><span>Minh chứng khảo sát</span><strong>84</strong></div>
            <div class="flex justify-between"><span>Lịch sử cải tiến</span><strong>42</strong></div>
            <div class="flex justify-between"><span>Chuẩn liên kết</span><strong>AUN-QA</strong></div>
          </div>
          <button class="mt-5 w-full rounded-md border px-3 py-2 text-sm">Mở kho minh chứng</button>
        </section>
      </div>
    </section>
  </EraLmsLayout>
</template>
