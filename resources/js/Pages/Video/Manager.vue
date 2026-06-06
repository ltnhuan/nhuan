<script setup>
import EraLmsLayout from '@/Layouts/EraLmsLayout.vue'

const videos = [
  { title: 'Video bài giảng 1', course: 'Môn chung 1', status: 'Sẵn sàng', duration: '08:20', size: '51 MB', quality: '720p', uploader: 'Giảng viên Demo' },
  { title: 'Video bài giảng 2', course: 'Môn chung 2', status: 'Đang xử lý', duration: '10:40', size: '68 MB', quality: 'HLS chờ xử lý', uploader: 'Giảng viên Demo' },
  { title: 'Video bài giảng 3', course: 'Môn chung 3', status: 'Sẵn sàng', duration: '12:30', size: '72 MB', quality: '360p, 720p', uploader: 'Đào tạo LMS' },
]

const statusClass = {
  'Sẵn sàng': 'bg-emerald-50 text-emerald-700 ring-emerald-200',
  'Đang xử lý': 'bg-amber-50 text-amber-800 ring-amber-200',
  'Lỗi xử lý': 'bg-rose-50 text-rose-700 ring-rose-200',
}
</script>

<template>
  <EraLmsLayout>
    <template #breadcrumb>Video / Quản lý video</template>

    <section class="border-b bg-white">
      <div class="mx-auto max-w-7xl px-6 py-5">
        <div class="flex flex-wrap items-center justify-between gap-3">
          <div>
            <h1 class="text-lg font-semibold text-slate-950">Quản lý video học tập</h1>
            <p class="mt-1 text-sm text-slate-600">Tải lên, xử lý HLS, gắn vào bài học và theo dõi trạng thái phát hành.</p>
          </div>
          <div class="flex gap-2">
            <button class="rounded-md border border-slate-300 px-4 py-2 text-sm">Đồng bộ metadata</button>
            <button class="rounded-md bg-slate-950 px-4 py-2 text-sm font-medium text-white">Tải video lên</button>
          </div>
        </div>

        <div class="mt-5 grid gap-3 md:grid-cols-4">
          <input class="rounded-md border border-slate-300 px-3 py-2 text-sm" placeholder="Tìm theo tên video" />
          <select class="rounded-md border border-slate-300 px-3 py-2 text-sm"><option>Tất cả khóa học</option></select>
          <select class="rounded-md border border-slate-300 px-3 py-2 text-sm"><option>Tất cả trạng thái</option></select>
          <select class="rounded-md border border-slate-300 px-3 py-2 text-sm"><option>Tất cả người tải</option></select>
        </div>
      </div>
    </section>

    <section class="mx-auto max-w-7xl px-6 py-5">
      <div class="overflow-hidden border bg-white">
        <table class="w-full text-left text-sm">
          <thead class="bg-slate-50 text-xs uppercase text-slate-500">
            <tr>
              <th class="px-4 py-3">Video</th>
              <th class="px-4 py-3">Khóa học</th>
              <th class="px-4 py-3">Trạng thái</th>
              <th class="px-4 py-3">Metadata</th>
              <th class="px-4 py-3">Người tải</th>
              <th class="px-4 py-3">Thao tác</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100">
            <tr v-for="video in videos" :key="video.title" class="hover:bg-slate-50">
              <td class="px-4 py-3 font-medium text-slate-950">{{ video.title }}</td>
              <td class="px-4 py-3 text-slate-700">{{ video.course }}</td>
              <td class="px-4 py-3"><span class="rounded-full px-2.5 py-1 text-xs font-medium ring-1" :class="statusClass[video.status]">{{ video.status }}</span></td>
              <td class="px-4 py-3 text-slate-700">{{ video.duration }} · {{ video.size }} · {{ video.quality }}</td>
              <td class="px-4 py-3 text-slate-700">{{ video.uploader }}</td>
              <td class="px-4 py-3"><button class="text-sm font-medium text-blue-700">Gắn bài học</button></td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>
  </EraLmsLayout>
</template>
