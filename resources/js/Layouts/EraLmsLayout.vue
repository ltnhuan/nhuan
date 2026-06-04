<script setup>
const menu = [
  'Tổng quan','Quản trị khóa học','Studio bài giảng','Kho học liệu','Lộ trình học tập','Kiểm tra online','Bài tập','Sổ điểm','Điểm danh online','Đồng bộ SIS','Báo cáo','AI trợ giảng','Cấu hình'
]
</script>

<template>
  <div class="min-h-screen bg-slate-50 text-slate-900">
    <aside class="fixed inset-y-0 left-0 w-64 border-r bg-white">
      <div class="flex h-14 items-center border-b px-4 text-sm font-bold text-blue-900">EraLMS Enterprise</div>
      <nav class="space-y-1 p-3 text-sm">
        <button v-for="item in menu" :key="item" class="flex w-full items-center rounded-md px-3 py-2 text-left hover:bg-blue-50">{{ item }}</button>
      </nav>
    </aside>
    <div class="pl-64">
      <header class="sticky top-0 z-10 flex h-14 items-center gap-3 border-b bg-white px-4">
        <div class="text-xs text-slate-500">Trang chủ / <slot name="breadcrumb">Tổng quan</slot></div>
        <input class="ml-auto h-9 w-80 rounded-md border px-3 text-sm" placeholder="Tìm kiếm toàn hệ thống" />
        <select class="h-9 rounded-md border px-2 text-sm"><option>VABIS LMS</option></select>
        <select class="h-9 rounded-md border px-2 text-sm"><option>Vũng Tàu</option><option>Online Campus</option></select>
        <button class="rounded-md border px-3 py-2 text-sm">Thông báo</button>
        <button class="rounded-md bg-blue-900 px-3 py-2 text-sm text-white">Hồ sơ</button>
      </header>
      <main class="p-4">
        <slot />
      </main>
    </div>
  </div>
</template>
