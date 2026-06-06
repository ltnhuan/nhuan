<script setup>
import { BookOpenText, FileText, MessageCircle, PenLine, Play, Presentation, ScrollText, Video } from '@lucide/vue'

defineProps({ open: { type: Boolean, default: false } })
const types = [
  ['text', 'Bài học văn bản', 'Soạn nội dung đọc', BookOpenText],
  ['video', 'Video', 'Gắn video từ kho học liệu', Play],
  ['pdf', 'Tài liệu', 'Load PDF/File từ kho', FileText],
  ['quiz', 'Bài kiểm tra', 'Load câu hỏi từ kho đề', ScrollText],
  ['assignment', 'Bài tập', 'Load bài tập đã tạo', PenLine],
  ['forum', 'Diễn đàn', 'Tạo thảo luận', MessageCircle],
  ['scorm', 'SCORM / xAPI', 'Load gói SCORM', Presentation],
  ['live_session', 'Buổi học trực tuyến', 'Tạo buổi học live', Video],
]
defineEmits(['pick', 'close'])
</script>

<template>
  <div v-if="open" class="fixed inset-0 z-50">
    <button class="absolute inset-0 bg-slate-950/10" @click="$emit('close')"></button>
    <div class="absolute right-4 top-4 h-[calc(100vh-2rem)] w-[600px] overflow-hidden rounded-xl border border-slate-200 bg-white shadow-2xl">
      <div class="flex h-16 items-center justify-between border-b border-slate-200 px-5">
        <div>
          <h2 class="text-base font-bold text-slate-950">Thêm hoạt động vào tiến trình</h2>
          <p class="mt-0.5 text-xs text-slate-500">Chọn loại hoạt động. Với quiz, tài liệu hoặc bài tập, màn hình sẽ mở bước load dữ liệu từ kho.</p>
        </div>
        <button class="grid h-9 w-9 place-items-center rounded-md text-slate-500 hover:bg-slate-100" @click="$emit('close')">×</button>
      </div>
      <div class="grid grid-cols-2 gap-3 p-5">
        <button
          v-for="[key, label, sub, Icon] in types"
          :key="key"
          class="group flex min-h-28 items-center gap-3 rounded-lg border border-slate-200 bg-slate-50 p-4 text-left transition hover:border-blue-200 hover:bg-blue-50"
          @click="$emit('pick', key)"
        >
          <span class="grid h-12 w-12 shrink-0 place-items-center rounded-lg bg-white text-blue-600 shadow-sm group-hover:bg-blue-600 group-hover:text-white">
            <component :is="Icon" class="h-6 w-6" />
          </span>
          <span class="min-w-0">
            <span class="block text-sm font-bold text-slate-900">{{ label }}</span>
            <span class="mt-1 block text-xs text-slate-500">{{ sub }}</span>
          </span>
        </button>
      </div>
      <div class="absolute bottom-0 left-0 right-0 border-t border-slate-200 bg-white p-4">
        <button class="h-11 w-full rounded-md border border-slate-300 bg-white text-sm font-semibold text-slate-700 hover:bg-slate-50" @click="$emit('close')">Hủy</button>
      </div>
    </div>
  </div>
</template>
