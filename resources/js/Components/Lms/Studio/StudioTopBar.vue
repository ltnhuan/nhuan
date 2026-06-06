<script setup>
import { Bell, ChevronDown, Clock3, Eye, FolderOpen, HelpCircle, History, Save, Send, Settings2, Workflow } from '@lucide/vue'
import StatusBadge from '@/Components/Lms/StatusBadge.vue'

defineProps({ course: Object, loading: Object })
defineEmits(['save', 'preview', 'submit', 'approve', 'publish', 'history', 'settings', 'manage'])
</script>

<template>
  <header class="sticky top-0 z-20 border border-slate-200 bg-white/95 shadow-sm shadow-slate-200/60 backdrop-blur">
    <div class="flex min-h-24 items-center gap-4 px-5">
      <div class="grid h-11 w-11 shrink-0 place-items-center rounded-md bg-blue-50 text-blue-700 ring-1 ring-blue-100">
        <Workflow class="h-5 w-5" />
      </div>
      <div class="min-w-0">
        <div class="mb-1 flex items-center gap-2 text-xs text-slate-500">
          <span>Trang chủ</span>
          <span>›</span>
          <span>Khóa học</span>
          <span>›</span>
          <span>Quản trị mạng nâng cao</span>
        </div>
        <div class="flex flex-wrap items-center gap-2">
          <h1 class="truncate text-xl font-bold text-slate-950">{{ course?.title || 'Quản trị mạng nâng cao' }}</h1>
          <StatusBadge :status="course?.status || 'draft'" />
        </div>
        <div class="mt-1 text-xs text-slate-500">Mã khóa học: {{ course?.code || 'NET301' }} · Studio soạn bài theo tiến trình học</div>
      </div>
      <div class="ml-auto hidden items-center gap-3 xl:flex">
        <button class="grid h-9 w-9 place-items-center rounded-md text-slate-600 hover:bg-slate-100" title="Trợ giúp">
          <HelpCircle class="h-4 w-4" />
        </button>
        <button class="relative grid h-9 w-9 place-items-center rounded-md text-slate-600 hover:bg-slate-100" title="Thông báo">
          <Bell class="h-4 w-4" />
          <span class="absolute right-1 top-1 rounded-full bg-red-500 px-1.5 text-[10px] font-bold text-white">12</span>
        </button>
        <div class="grid h-8 w-8 place-items-center rounded-full bg-gradient-to-br from-amber-200 to-emerald-300 text-xs font-bold text-slate-800">GV</div>
        <ChevronDown class="h-4 w-4 text-slate-500" />
      </div>
      <div class="flex flex-wrap justify-end gap-2">
        <div class="inline-flex h-10 items-center gap-2 rounded-md bg-emerald-50 px-3 text-xs font-semibold text-emerald-700 ring-1 ring-emerald-100">
          Trạng thái: <span class="font-bold">{{ course?.status || 'Published' }}</span>
          <ChevronDown class="h-3.5 w-3.5" />
        </div>
        <button class="inline-flex h-10 items-center gap-2 rounded-md border border-blue-200 bg-blue-50 px-3 text-xs font-semibold text-blue-700 hover:bg-blue-100" @click="$emit('manage')"><FolderOpen class="h-4 w-4" />Bài đã soạn</button>
        <button class="inline-flex h-10 items-center gap-2 rounded-md border border-slate-300 bg-white px-3 text-xs font-semibold text-slate-700 hover:bg-slate-50" @click="$emit('preview')"><Eye class="h-4 w-4" />Xem thử</button>
        <button class="inline-flex h-10 items-center gap-2 rounded-md border border-slate-300 bg-white px-3 text-xs font-semibold text-slate-700 hover:bg-slate-50" @click="$emit('history')"><History class="h-4 w-4" />Lịch sử</button>
        <button class="inline-flex h-10 items-center gap-2 rounded-md bg-blue-600 px-4 text-xs font-semibold text-white shadow-sm shadow-blue-200 hover:bg-blue-700" :disabled="loading?.['studio.course.save_draft']" @click="$emit('save')"><Save class="h-4 w-4" />Lưu bài học</button>
        <button class="inline-flex h-10 items-center gap-2 rounded-md border border-slate-300 bg-white px-3 text-xs font-semibold text-slate-700 hover:bg-slate-50" @click="$emit('submit')"><Send class="h-4 w-4" />Gửi duyệt</button>
        <button class="inline-flex h-10 items-center gap-2 rounded-md border border-slate-300 bg-white px-3 text-xs font-semibold text-slate-700 hover:bg-slate-50" @click="$emit('publish')"><Clock3 class="h-4 w-4" />Xuất bản</button>
        <button class="inline-flex h-10 items-center gap-2 rounded-md border border-slate-300 bg-white px-3 text-xs font-semibold text-slate-700 hover:bg-slate-50" @click="$emit('settings')"><Settings2 class="h-4 w-4" />Thao tác</button>
      </div>
    </div>
  </header>
</template>
