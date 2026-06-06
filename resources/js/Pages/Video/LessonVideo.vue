<script setup>
import EraLmsLayout from '@/Layouts/EraLmsLayout.vue'
import VideoPlayer from '@/Components/VideoPlayer.vue'

const video = {
  id: 1,
  title: 'Video bài giảng: Tổng quan hệ thống EraLMS',
  duration_seconds: 620,
  thumbnail_url: 'https://placehold.co/1280x720/0f172a/ffffff?text=EraLMS+Video',
  watch_percent: 46,
}

const playback = {
  url: '/storage/videos/demo/video-bai-giang-1.mp4?expires=demo&signature=demo',
  delivery: 'original',
}
</script>

<template>
  <EraLmsLayout>
    <template #breadcrumb>Bài học / Video</template>

    <section class="mx-auto grid max-w-7xl gap-4 px-6 py-5 lg:grid-cols-[1fr_320px]">
      <main>
        <VideoPlayer :video="video" :playback="playback" :required-percent="90" />
      </main>

      <aside class="space-y-4">
        <div class="border bg-white p-4">
          <h2 class="text-sm font-semibold text-slate-950">Điều kiện hoàn thành</h2>
          <dl class="mt-3 space-y-3 text-sm">
            <div class="flex justify-between"><dt class="text-slate-500">Thời lượng</dt><dd class="font-medium">10 phút 20 giây</dd></div>
            <div class="flex justify-between"><dt class="text-slate-500">Đã xem</dt><dd class="font-medium">46%</dd></div>
            <div class="flex justify-between"><dt class="text-slate-500">Yêu cầu</dt><dd class="font-medium">90%</dd></div>
            <div class="flex justify-between"><dt class="text-slate-500">Trạng thái</dt><dd class="font-medium text-amber-700">Chưa đủ điều kiện</dd></div>
          </dl>
        </div>

        <div class="border bg-white p-4">
          <h2 class="text-sm font-semibold text-slate-950">Bài tiếp theo</h2>
          <p class="mt-2 text-sm text-slate-700">Bạn cần xem tối thiểu 90% video này trước khi mở quiz chương 1.</p>
        </div>
      </aside>
    </section>
  </EraLmsLayout>
</template>
