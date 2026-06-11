<script setup>
import { computed, onMounted, ref } from 'vue'
import { AlertTriangle, ArrowRight, BellRing, CalendarDays, CheckCheck, Clock, RefreshCw } from '@lucide/vue'
import EraLmsLayout from '@/Layouts/EraLmsLayout.vue'

const props = defineProps({
  sessionUser: { type: Object, default: null },
  apiHeaders: { type: Object, default: () => ({}) },
})

defineEmits(['logout'])

const loading = ref(true)
const error = ref('')
const payload = ref(null)
const message = ref('')

const sections = computed(() => payload.value?.sections || {})
const risk = computed(() => payload.value?.risk || {})
const urgentCount = computed(() => Number(risk.value?.overdue_alerts || 0) + (sections.value?.today || []).length)
const sectionRows = [
  ['overdue', 'Quá hạn', 'Cần xử lý trước', 'rose'],
  ['today', 'Hôm nay', 'Việc cần làm ngay', 'blue'],
  ['this_week', 'Tuần này', 'Sắp đến hạn', 'amber'],
  ['future', 'Sắp tới', 'Có thể lên lịch', 'slate'],
]

async function load() {
  loading.value = true
  error.value = ''
  try {
    const response = await fetch('/api/v1/student/tasks', { headers: props.apiHeaders })
    const data = await response.json().catch(() => ({}))
    if (!response.ok) throw new Error(data.message || 'Không tải được Task Center.')
    payload.value = data.data || data
  } catch (err) {
    error.value = err.message || 'Không tải được Task Center.'
  } finally {
    loading.value = false
  }
}

function priorityClass(priority) {
  return {
    critical: 'border-red-200 bg-red-50 text-red-700',
    high: 'border-orange-200 bg-orange-50 text-orange-700',
    medium: 'border-amber-200 bg-amber-50 text-amber-700',
    low: 'border-slate-200 bg-slate-50 text-slate-700',
  }[priority] || 'border-slate-200 bg-slate-50 text-slate-700'
}

function boardClass(tone) {
  return {
    rose: 'border-red-200 bg-red-50',
    blue: 'border-blue-200 bg-blue-50',
    amber: 'border-amber-200 bg-amber-50',
    slate: 'border-slate-200 bg-slate-50',
  }[tone] || 'border-slate-200 bg-white'
}

function dotClass(tone) {
  return {
    rose: 'bg-red-500',
    blue: 'bg-blue-500',
    amber: 'bg-amber-500',
    slate: 'bg-slate-400',
  }[tone] || 'bg-slate-400'
}

function markBulk(action) {
  message.value = action === 'read' ? 'Đã đánh dấu đã đọc.' : 'Đã tạo nhắc nhở.'
}

onMounted(load)
</script>

<template>
  <EraLmsLayout :session-user="sessionUser" @logout="$emit('logout')">
    <template #breadcrumb>Task Center</template>
    <section class="mx-auto max-w-7xl space-y-4 px-3 py-3 sm:px-5">
      <header class="rounded-md border border-slate-200 bg-white p-4 shadow-sm">
        <div class="flex flex-wrap items-center justify-between gap-4">
          <div>
            <div class="text-xs font-bold uppercase text-blue-700">Today board</div>
            <h1 class="mt-1 text-2xl font-bold text-slate-950">Task Center</h1>
          </div>
          <div class="flex flex-wrap gap-2">
            <button class="inline-flex h-10 items-center gap-2 rounded-md border border-slate-300 px-3 text-sm font-semibold" @click="markBulk('read')">
              <CheckCheck class="h-4 w-4" /> Mark Read
            </button>
            <button class="inline-flex h-10 items-center gap-2 rounded-md border border-slate-300 px-3 text-sm font-semibold" @click="markBulk('reminder')">
              <BellRing class="h-4 w-4" /> Reminder
            </button>
            <button class="grid h-10 w-10 place-items-center rounded-md bg-slate-950 text-white" :disabled="loading" title="Tải lại" @click="load">
              <RefreshCw class="h-4 w-4" />
            </button>
          </div>
        </div>
      </header>

      <div v-if="message" class="rounded-md border border-emerald-200 bg-emerald-50 p-3 text-sm font-semibold text-emerald-700">{{ message }}</div>
      <div v-if="loading" class="rounded-md border bg-white p-6 text-sm text-slate-500">Đang tải task...</div>
      <div v-else-if="error" class="rounded-md border border-red-200 bg-red-50 p-6 text-sm font-semibold text-red-700">{{ error }}</div>

      <template v-else>
        <section class="grid gap-3 md:grid-cols-3">
          <div class="rounded-md border border-slate-200 bg-white p-4 shadow-sm">
            <div class="text-xs font-bold uppercase text-slate-500">Cần chú ý</div>
            <div class="mt-1 text-3xl font-bold text-slate-950">{{ urgentCount }}</div>
            <div class="mt-1 text-sm text-slate-500">task hôm nay hoặc quá hạn</div>
          </div>
          <div class="rounded-md border border-slate-200 bg-white p-4 shadow-sm">
            <div class="text-xs font-bold uppercase text-slate-500">Tổng task</div>
            <div class="mt-1 text-3xl font-bold text-slate-950">{{ payload?.all?.length || 0 }}</div>
            <div class="mt-1 text-sm text-slate-500">quiz, assignment, exam, survey</div>
          </div>
          <div class="rounded-md border p-4 shadow-sm" :class="risk.overdue_alerts ? 'border-red-200 bg-red-50 text-red-800' : 'border-emerald-200 bg-emerald-50 text-emerald-800'">
            <div class="flex items-center gap-2 text-xs font-bold uppercase">
              <AlertTriangle class="h-4 w-4" /> Overdue
            </div>
            <div class="mt-1 text-3xl font-bold">{{ risk.overdue_alerts || 0 }}</div>
            <div class="mt-1 text-sm">{{ risk.overdue_alerts ? 'cần xử lý trước khi học tiếp' : 'không có task quá hạn' }}</div>
          </div>
        </section>

        <section class="grid gap-4 xl:grid-cols-2">
          <article v-for="[key, label, subtitle, tone] in sectionRows" :key="key" class="rounded-md border bg-white shadow-sm" :class="boardClass(tone)">
            <div class="flex items-center justify-between gap-3 border-b border-white/70 p-4">
              <div class="min-w-0">
                <div class="flex items-center gap-2">
                  <span class="h-2.5 w-2.5 rounded-full" :class="dotClass(tone)"></span>
                  <h2 class="font-bold text-slate-950">{{ label }}</h2>
                </div>
                <p class="mt-1 text-xs text-slate-600">{{ subtitle }}</p>
              </div>
              <span class="rounded-md bg-white px-2 py-1 text-xs font-bold text-slate-700">{{ (sections[key] || []).length }}</span>
            </div>
            <div class="grid gap-3 p-3 md:grid-cols-2">
              <a v-for="task in sections[key] || []" :key="`${key}-${task.id}`" :href="task.href" class="rounded-md border border-slate-200 bg-white p-3 text-sm shadow-sm transition hover:-translate-y-0.5 hover:shadow-md">
                <div class="flex items-start justify-between gap-2">
                  <div class="min-w-0">
                    <div class="line-clamp-2 min-h-10 font-bold text-slate-950">{{ task.title }}</div>
                    <div class="mt-1 truncate text-xs text-slate-500">{{ task.course }}</div>
                  </div>
                  <span class="shrink-0 rounded border px-2 py-1 text-[11px] font-bold uppercase" :class="priorityClass(task.priority)">{{ task.priority }}</span>
                </div>
                <div class="mt-3 flex flex-wrap gap-2 text-xs">
                  <span class="rounded-md bg-slate-100 px-2 py-1 font-semibold text-slate-700">{{ task.type }}</span>
                  <span class="rounded-md bg-slate-100 px-2 py-1 font-semibold text-slate-700">{{ task.status }}</span>
                </div>
                <div class="mt-4 flex items-center justify-between gap-2">
                  <span class="inline-flex min-w-0 items-center gap-1 truncate text-xs text-slate-500">
                    <Clock class="h-3.5 w-3.5 shrink-0" /> {{ task.deadline || '-' }}
                  </span>
                  <span class="inline-flex shrink-0 items-center gap-1 rounded-md bg-slate-950 px-3 py-2 text-xs font-bold text-white">
                    {{ task.action }} <ArrowRight class="h-3.5 w-3.5" />
                  </span>
                </div>
              </a>
              <div v-if="!(sections[key] || []).length" class="rounded-md border border-dashed border-slate-300 bg-white/70 p-5 text-sm text-slate-600 md:col-span-2">
                Không có task trong mục này.
              </div>
            </div>
          </article>
        </section>

        <div v-if="!(payload?.all || []).length" class="rounded-md border border-dashed border-slate-300 bg-white p-8 text-center">
          <div class="mx-auto grid h-12 w-12 place-items-center rounded-md bg-blue-50 text-blue-700">
            <CalendarDays class="h-6 w-6" />
          </div>
          <h2 class="mt-4 text-lg font-bold text-slate-950">Không có việc cần làm</h2>
          <p class="mt-2 text-sm text-slate-500">Bạn có thể quay lại Home hoặc tiếp tục khóa học gần nhất.</p>
          <a href="/" class="mt-4 inline-flex rounded-md bg-slate-950 px-4 py-2 text-sm font-bold text-white">Về Home</a>
        </div>
      </template>
    </section>
  </EraLmsLayout>
</template>
