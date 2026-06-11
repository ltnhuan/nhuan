<script setup>
import { computed, onMounted, ref } from 'vue'
import { Award, BookOpen, ClipboardCheck, FileText, Lock, MessageSquare, PlayCircle, RefreshCw } from '@lucide/vue'
import EraLmsLayout from '@/Layouts/EraLmsLayout.vue'

const props = defineProps({
  sessionUser: { type: Object, default: null },
  apiHeaders: { type: Object, default: () => ({}) },
})

defineEmits(['logout'])

const loading = ref(true)
const error = ref('')
const courses = ref([])
const selectedCourseId = ref(new URLSearchParams(window.location.search).get('course_id') || '')
const journey = ref(null)

const nodes = computed(() => journey.value?.timeline || [])
const summary = computed(() => journey.value?.summary || {})

const iconMap = {
  video: PlayCircle,
  quiz: ClipboardCheck,
  assignment: FileText,
  forum: MessageSquare,
  certificate: Award,
  lesson: BookOpen,
}

async function loadCourses() {
  const response = await fetch('/api/v1/student/courses', { headers: props.apiHeaders })
  const payload = await response.json().catch(() => ({}))
  if (!response.ok) throw new Error(payload.message || 'Không tải được khóa học.')
  courses.value = payload.all || payload.data?.all || []
  if (!selectedCourseId.value && courses.value.length) {
    selectedCourseId.value = String(courses.value[0].course_id)
  }
}

async function loadJourney() {
  if (!selectedCourseId.value) return
  const response = await fetch(`/api/v1/student/journey/${selectedCourseId.value}`, { headers: props.apiHeaders })
  const payload = await response.json().catch(() => ({}))
  if (!response.ok) throw new Error(payload.message || 'Không tải được Learning Journey.')
  journey.value = payload.data || payload
  const url = new URL(window.location.href)
  url.searchParams.set('course_id', selectedCourseId.value)
  window.history.replaceState({}, '', url)
}

async function load() {
  loading.value = true
  error.value = ''
  try {
    if (!courses.value.length) await loadCourses()
    await loadJourney()
  } catch (err) {
    error.value = err.message || 'Không tải được Learning Journey.'
  } finally {
    loading.value = false
  }
}

function nodeClass(status) {
  return {
    completed: 'border-emerald-200 bg-emerald-50 text-emerald-800',
    current: 'border-blue-200 bg-blue-50 text-blue-800',
    locked: 'border-slate-200 bg-slate-50 text-slate-500',
  }[status] || 'border-slate-200 bg-white text-slate-700'
}

function iconFor(node) {
  return iconMap[node.type] || BookOpen
}

onMounted(load)
</script>

<template>
  <EraLmsLayout :session-user="sessionUser" @logout="$emit('logout')">
    <template #breadcrumb>Người học / Learning Journey</template>
    <section class="mx-auto max-w-6xl space-y-4 px-4 py-5 sm:px-6">
      <header class="rounded-md border border-slate-200 bg-white p-4 shadow-sm">
        <div class="flex flex-wrap items-end justify-between gap-3">
          <div>
            <h1 class="text-2xl font-bold text-slate-950">Learning Journey Timeline</h1>
            <p class="mt-1 text-sm text-slate-600">{{ journey?.course?.title || 'Chọn khóa học để xem hành trình.' }}</p>
          </div>
          <div class="flex flex-wrap gap-2">
            <select v-model="selectedCourseId" class="h-10 rounded-md border border-slate-300 bg-white px-3 text-sm" @change="load">
              <option v-for="course in courses" :key="course.course_id" :value="String(course.course_id)">{{ course.code }} - {{ course.title }}</option>
            </select>
            <button class="inline-flex h-10 items-center gap-2 rounded-md bg-slate-950 px-3 text-sm font-semibold text-white" :disabled="loading" @click="load">
              <RefreshCw class="h-4 w-4" /> Tải lại
            </button>
          </div>
        </div>
      </header>

      <div v-if="loading" class="rounded-md border bg-white p-6 text-sm text-slate-500">Đang tải timeline...</div>
      <div v-else-if="error" class="rounded-md border border-red-200 bg-red-50 p-6 text-sm font-semibold text-red-700">{{ error }}</div>

      <template v-else>
        <div class="grid gap-3 sm:grid-cols-4">
          <div class="rounded-md border border-slate-200 bg-white p-4"><div class="text-xs text-slate-500">Total</div><div class="mt-1 text-2xl font-bold">{{ summary.total_nodes || 0 }}</div></div>
          <div class="rounded-md border border-slate-200 bg-white p-4"><div class="text-xs text-slate-500">Completed</div><div class="mt-1 text-2xl font-bold">{{ summary.completed_nodes || 0 }}</div></div>
          <div class="rounded-md border border-slate-200 bg-white p-4"><div class="text-xs text-slate-500">Locked</div><div class="mt-1 text-2xl font-bold">{{ summary.locked_nodes || 0 }}</div></div>
          <div class="rounded-md border border-slate-200 bg-white p-4"><div class="text-xs text-slate-500">Current</div><div class="mt-1 truncate text-sm font-bold">{{ summary.current_node?.title || '-' }}</div></div>
        </div>

        <section class="rounded-md border border-slate-200 bg-white p-4 shadow-sm">
          <div class="space-y-3">
            <article v-for="(node, index) in nodes" :key="node.id" class="relative grid gap-3 rounded-md border p-4 md:grid-cols-[56px_1fr_180px]" :class="nodeClass(node.status)">
              <div class="grid h-12 w-12 place-items-center rounded-md bg-white/70">
                <component :is="node.status === 'locked' ? Lock : iconFor(node)" class="h-5 w-5" />
              </div>
              <div class="min-w-0">
                <div class="flex flex-wrap items-center gap-2">
                  <h2 class="font-bold text-slate-950">{{ node.title }}</h2>
                  <span class="rounded bg-white px-2 py-1 text-xs font-semibold capitalize">{{ node.status }}</span>
                </div>
                <div class="mt-1 text-xs text-slate-500">{{ node.section }} · {{ node.type }} · {{ node.time }} phút</div>
                <div class="mt-2 h-2 rounded-full bg-white/80"><div class="h-2 rounded-full bg-blue-600" :style="{ width: `${Math.min(100, Number(node.progress || 0))}%` }"></div></div>
                <div class="mt-2 text-xs">{{ node.unlock_condition }}</div>
              </div>
              <div class="flex items-center justify-start md:justify-end">
                <a :href="node.href" class="rounded-md px-3 py-2 text-sm font-semibold" :class="node.status === 'locked' ? 'border border-slate-300 text-slate-500' : 'bg-slate-950 text-white'">{{ node.action }}</a>
              </div>
              <span v-if="index < nodes.length - 1" class="absolute -bottom-3 left-10 hidden h-3 w-px bg-slate-300 md:block"></span>
            </article>
          </div>
        </section>
      </template>
    </section>
  </EraLmsLayout>
</template>
