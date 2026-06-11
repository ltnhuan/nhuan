<script setup>
import { BookOpenText, FileText, GripVertical, MessageCircle, PenLine, Play, Replace, ScrollText, Trash2, Video } from '@lucide/vue'

defineProps({ component: Object, active: Boolean })
defineEmits(['edit', 'preview', 'replace', 'duplicate', 'delete'])

function iconName(type) {
  return type === 'video' ? Play : type === 'live_session' ? Video : type === 'forum' ? MessageCircle : type === 'assignment' ? PenLine : type === 'quiz' ? ScrollText : type === 'pdf' || type === 'file' ? FileText : BookOpenText
}

function labelFor(type) {
  const labels = { text: 'Văn bản', video: 'Video', pdf: 'Tài liệu', file: 'Tài liệu', quiz: 'Kiểm tra', assignment: 'Bài tập', forum: 'Diễn đàn', live_session: 'Phiên trực tuyến', scorm: 'SCORM' }
  return labels[type] || type
}

function accentFor(type) {
  if (type === 'video') return 'bg-violet-50 text-violet-700'
  if (type === 'quiz') return 'bg-amber-50 text-amber-700'
  if (type === 'forum' || type === 'live_session') return 'bg-cyan-50 text-cyan-700'
  if (type === 'pdf' || type === 'file') return 'bg-red-50 text-red-700'
  return 'bg-blue-50 text-blue-700'
}
</script>

<template>
  <article
    class="group grid min-h-16 cursor-pointer items-center gap-3 border-b px-3 py-3 text-sm transition last:border-b-0"
    :class="active ? 'border-blue-200 bg-blue-50 ring-1 ring-inset ring-blue-200' : 'border-slate-200 bg-white hover:bg-slate-50'"
    style="grid-template-columns: 24px minmax(160px, 1fr) 60px 66px 72px 124px;"
  >
    <GripVertical class="h-4 w-4 text-slate-300" />
    <div class="min-w-0">
      <div class="flex items-center gap-3">
          <span class="grid h-9 w-9 shrink-0 place-items-center rounded-md" :class="accentFor(component.component_type)">
            <component :is="iconName(component.component_type)" class="h-5 w-5" />
          </span>
          <div class="min-w-0">
            <h3 class="truncate text-sm font-bold text-slate-950">{{ component.title }}</h3>
            <p class="mt-1 truncate text-xs text-slate-500">
              <span v-if="component.config?.completion_rule?.score">Điểm đạt: {{ component.config.completion_rule.score }}%</span>
              <span v-else>{{ component.required ? 'Bắt buộc hoàn thành' : 'Không bắt buộc' }}</span>
            </p>
          </div>
      </div>
    </div>
    <div class="text-xs font-semibold text-slate-600">{{ labelFor(component.component_type) }}</div>
    <div class="text-xs text-slate-600">{{ component.config?.estimated_minutes ? `${component.config.estimated_minutes} phút` : '-' }}</div>
    <div>
      <span class="rounded-full bg-emerald-50 px-2.5 py-1 text-[11px] font-bold text-emerald-700">
        {{ component.status || 'Nháp' }}
      </span>
    </div>
    <div class="flex items-center justify-end gap-1">
      <button class="grid h-7 w-7 place-items-center rounded-md border border-slate-200 bg-white text-slate-600 hover:bg-slate-50" title="Xem thử" @click.stop="$emit('preview', component)"><Play class="h-3.5 w-3.5" /></button>
      <button class="grid h-7 w-7 place-items-center rounded-md border border-slate-200 bg-white text-slate-600 hover:bg-slate-50" title="Thay thế" @click.stop="$emit('replace', component)"><Replace class="h-3.5 w-3.5" /></button>
      <button class="grid h-7 w-7 place-items-center rounded-md border border-slate-200 bg-white text-slate-600 hover:bg-slate-50" title="Sửa" @click.stop="$emit('edit', component)"><PenLine class="h-3.5 w-3.5" /></button>
      <button class="grid h-7 w-7 place-items-center rounded-md border border-red-100 bg-white text-red-600 hover:bg-red-50" title="Xóa" @click.stop="$emit('delete', component)"><Trash2 class="h-3.5 w-3.5" /></button>
    </div>
  </article>
</template>
