<script setup>
import { computed, onMounted, ref } from 'vue'
import { BookOpenText, CheckCircle2, ChevronLeft, FileText, MessageCircle, PenLine, Play, RotateCcw, ScrollText, Video } from '@lucide/vue'
import EraLmsLayout from '@/Layouts/EraLmsLayout.vue'

const props = defineProps({
  sessionUser: { type: Object, default: null },
  apiHeaders: { type: Object, default: () => ({}) },
})

defineEmits(['logout'])

const loading = ref(false)
const error = ref('')
const studio = ref(null)
const activeComponentId = ref(null)
const completed = ref(new Set())

const course = computed(() => studio.value?.course || null)
const outline = computed(() => studio.value?.outline || [])
const outlineGroups = computed(() => {
  const roots = outline.value || []
  const rootUnits = roots.filter((node) => node.type === 'unit')
  const groups = roots
    .filter((node) => node.type !== 'unit')
    .map((node) => ({ ...node, units: collectUnits(node.children || []) }))

  if (rootUnits.length) {
    groups.unshift({
      id: 'root-units',
      title: course.value?.title || 'Bài học',
      units: rootUnits,
    })
  }

  return groups
})
const units = computed(() => collectUnits(outline.value))
const components = computed(() => units.value.flatMap((unit) => (unit.components || []).map((component) => ({ ...component, unit }))))
const activeComponent = computed(() => components.value.find((item) => Number(item.id) === Number(activeComponentId.value)) || components.value[0] || null)
const activeUnit = computed(() => activeComponent.value?.unit || units.value[0] || null)
const completedCount = computed(() => completed.value.size)
const progressPercent = computed(() => components.value.length ? Math.round(completedCount.value * 100 / components.value.length) : 0)
const nextComponent = computed(() => {
  const index = components.value.findIndex((item) => Number(item.id) === Number(activeComponent.value?.id))
  return index >= 0 ? components.value[index + 1] : null
})
const previousComponent = computed(() => {
  const index = components.value.findIndex((item) => Number(item.id) === Number(activeComponent.value?.id))
  return index > 0 ? components.value[index - 1] : null
})
const activeIsCompleted = computed(() => activeComponent.value ? completed.value.has(activeComponent.value.id) : false)
const firstQuizComponent = computed(() => components.value.find((item) => item.component_type === 'quiz') || null)

function collectUnits(nodes) {
  return (nodes || []).flatMap((node) => [
    ...(node.type === 'unit' ? [node] : []),
    ...collectUnits(node.children || []),
  ])
}

function iconFor(type) {
  return type === 'video' ? Play : type === 'live_session' ? Video : type === 'forum' ? MessageCircle : type === 'assignment' ? PenLine : type === 'quiz' ? ScrollText : type === 'pdf' || type === 'file' ? FileText : BookOpenText
}

function labelFor(type) {
  const labels = { text: 'Bài đọc', video: 'Video bài giảng', pdf: 'Tài liệu', file: 'Tài liệu', quiz: 'Quiz', assignment: 'Bài tập', forum: 'Thảo luận', live_session: 'Buổi học trực tuyến', scorm: 'SCORM' }
  return labels[type] || type || 'Hoạt động'
}

function accentFor(type) {
  if (type === 'video') return 'bg-violet-50 text-violet-700'
  if (type === 'quiz') return 'bg-amber-50 text-amber-700'
  if (type === 'assignment') return 'bg-orange-50 text-orange-700'
  if (type === 'forum' || type === 'live_session') return 'bg-cyan-50 text-cyan-700'
  if (type === 'pdf' || type === 'file') return 'bg-red-50 text-red-700'
  return 'bg-blue-50 text-blue-700'
}

function selectComponent(component) {
  activeComponentId.value = component.id
  const params = new URLSearchParams(window.location.search)
  params.set('component_id', component.id)
  window.history.replaceState({}, '', `/courses/learn?${params.toString()}`)
}

async function markComplete() {
  if (!activeComponent.value) return
  try {
    await fetch(`/api/v1/components/${activeComponent.value.id}/complete`, {
      method: 'POST',
      headers: props.apiHeaders,
      body: JSON.stringify({ evidence: { source: 'learner_page' } }),
    })
  } catch (err) {
    // The page still updates locally so demo/permission-limited sessions can continue learning.
  }
  completed.value = new Set([...completed.value, activeComponent.value.id])
  if (nextComponent.value) selectComponent(nextComponent.value)
}

function restartLesson() {
  if (!activeComponent.value) return
  completed.value = new Set([...completed.value].filter((id) => Number(id) !== Number(activeComponent.value.id)))
}

function quizHref(component) {
  const examId = component?.config?.exam_id || component?.config?.quiz_id || component?.content_id
  const params = new URLSearchParams()
  if (examId) params.set('exam_id', examId)
  if (course.value?.id) params.set('course_id', course.value.id)
  if (component?.id) params.set('component_id', component.id)
  return `/exams/take?${params.toString()}`
}

function assignmentHref(component) {
  const params = new URLSearchParams()
  const assignmentId = component?.config?.assignment_id || component?.content_id
  if (assignmentId) params.set('assignment_id', assignmentId)
  if (course.value?.id) params.set('course_id', course.value.id)
  if (component?.id) params.set('component_id', component.id)
  return `/assignments/submission?${params.toString()}`
}

function journeyHref() {
  return course.value?.id ? `/student/journey?course_id=${course.value.id}` : '/student/journey'
}

function meetingHref(component) {
  return component?.config?.meeting_url || component?.config?.join_url || ''
}

function mediaUrl(component) {
  return component?.config?.media_url
    || component?.config?.video_url
    || component?.config?.audio_url
    || component?.config?.image_url
    || component?.content?.download_url
    || ''
}

function mediaKind(component) {
  const explicit = component?.config?.media_type || ''
  const url = String(mediaUrl(component)).toLowerCase()
  if (explicit) return explicit
  if (component?.component_type === 'video') return 'video'
  if (component?.component_type === 'audio') return 'audio'
  if (/\.(png|jpg|jpeg|webp|gif|svg)(\?|$)/.test(url)) return 'image'
  if (/\.(mp3|wav|ogg)(\?|$)/.test(url)) return 'audio'
  if (/\.(mp4|webm|mov|ogg)(\?|$)/.test(url)) return 'video'
  if (component?.component_type === 'pdf' || component?.component_type === 'file') return 'file'
  return ''
}

function lessonHtml(component) {
  return component?.config?.html || component?.config?.body_html || ''
}

function lessonText(component) {
  return component?.config?.body || component?.config?.text || component?.config?.instructions || component?.config?.prompt || ''
}

async function load() {
  const params = new URLSearchParams(window.location.search)
  let courseId = Number(params.get('course_id'))
  if (!courseId) {
    courseId = await resolveFirstLearnerCourseId()
  }
  if (!courseId) {
    error.value = 'Thiếu course_id để mở trang học.'
    return
  }

  loading.value = true
  error.value = ''
  try {
    const response = await fetch(`/api/v1/courses/${courseId}/studio`, { headers: props.apiHeaders })
    if (!response.ok) throw new Error('Không tải được dữ liệu khóa học.')
    studio.value = await response.json()
    activeComponentId.value = Number(params.get('component_id')) || components.value[0]?.id || null
  } catch (err) {
    error.value = err.message || 'Không tải được dữ liệu khóa học.'
  } finally {
    loading.value = false
  }
}

async function resolveFirstLearnerCourseId() {
  const response = await fetch('/api/v1/enrollment/records?per_page=1&mine=1', { headers: props.apiHeaders })
  if (!response.ok) return null
  const data = await response.json().catch(() => ({}))
  const rows = Array.isArray(data?.data?.data) ? data.data.data : (Array.isArray(data?.data) ? data.data : [])
  const first = rows[0]
  return first?.course?.id || first?.course_id || null
}

onMounted(load)
</script>

<template>
  <EraLmsLayout :session-user="sessionUser" @logout="$emit('logout')">
    <template #breadcrumb>Khóa học / Trang học</template>

    <div class="min-h-[calc(100vh-7rem)] bg-slate-100">
      <div v-if="loading" class="rounded-md border border-slate-200 bg-white p-6 text-sm text-slate-500">Đang tải trang học...</div>
      <div v-else-if="error" class="rounded-md border border-red-200 bg-red-50 p-6 text-sm font-semibold text-red-700">{{ error }}</div>
      <div v-else class="grid gap-4 lg:grid-cols-[320px_minmax(0,1fr)]">
        <aside class="overflow-hidden rounded-md border border-slate-200 bg-white">
          <div class="border-b border-slate-200 p-4">
            <a href="/" class="inline-flex items-center gap-2 text-xs font-semibold text-slate-600 hover:text-blue-700">
              <ChevronLeft class="h-4 w-4" />
              Quay lại dashboard
            </a>
            <h1 class="mt-3 text-base font-bold text-slate-950">{{ course?.title || 'Khóa học' }}</h1>
            <div class="mt-3 h-2 rounded-full bg-slate-100">
              <div class="h-2 rounded-full bg-emerald-600" :style="{ width: `${progressPercent}%` }"></div>
            </div>
            <p class="mt-2 text-xs text-slate-500">{{ completedCount }}/{{ components.length }} hoạt động hoàn thành</p>
            <a v-if="firstQuizComponent" :href="quizHref(firstQuizComponent)" class="mt-3 inline-flex h-9 items-center gap-2 rounded-md bg-amber-600 px-3 text-xs font-semibold text-white hover:bg-amber-700">
              <ScrollText class="h-4 w-4" />
              Làm bài thi
            </a>
          </div>

          <div class="max-h-[calc(100vh-17rem)] overflow-auto p-3">
            <section v-for="section in outlineGroups" :key="section.id" class="mb-4">
              <h2 class="px-2 text-xs font-bold uppercase text-slate-500">{{ section.title }}</h2>
              <div class="mt-2 space-y-1">
                <div v-for="unit in section.units || []" :key="unit.id" class="rounded-md border border-slate-100">
                  <div class="border-b border-slate-100 bg-slate-50 px-3 py-2 text-sm font-semibold text-slate-900">{{ unit.title }}</div>
              <button
                v-for="component in unit.components || []"
                :key="component.id"
                class="flex w-full items-center gap-2 px-3 py-2.5 text-left text-sm hover:bg-blue-50"
                    :class="Number(component.id) === Number(activeComponent?.id) ? 'bg-blue-50 text-blue-800' : 'text-slate-700'"
                    @click="selectComponent(component)"
                  >
                    <CheckCircle2 v-if="completed.has(component.id)" class="h-4 w-4 shrink-0 text-emerald-600" />
                    <component v-else :is="iconFor(component.component_type)" class="h-4 w-4 shrink-0 text-slate-400" />
                    <span class="min-w-0 flex-1 truncate">{{ component.title }}</span>
                    <span v-if="component.component_type === 'quiz'" class="rounded bg-amber-100 px-1.5 py-0.5 text-[10px] font-bold text-amber-700">Thi</span>
                  </button>
                </div>
              </div>
            </section>
          </div>
        </aside>

        <main class="min-w-0 overflow-hidden rounded-md border border-slate-200 bg-white">
          <header class="border-b border-slate-200 px-5 py-4">
            <div class="text-xs font-bold uppercase text-blue-700">{{ activeUnit?.title || 'Bài học' }}</div>
            <h2 class="mt-1 text-xl font-bold text-slate-950">{{ activeComponent?.title || 'Chưa có hoạt động' }}</h2>
            <p class="mt-1 text-sm text-slate-500">{{ labelFor(activeComponent?.component_type) }} · {{ activeComponent?.config?.estimated_minutes || 0 }} phút · {{ activeIsCompleted ? 'Đã học' : 'Đang học' }}</p>
          </header>

          <section v-if="activeComponent" class="p-5">
            <div v-if="activeComponent.component_type === 'text' && (lessonHtml(activeComponent) || lessonText(activeComponent))" class="rounded-md border border-slate-200 bg-white p-6">
              <div class="flex items-start gap-4">
                <span class="grid h-12 w-12 shrink-0 place-items-center rounded-md" :class="accentFor(activeComponent.component_type)">
                  <BookOpenText class="h-6 w-6" />
                </span>
                <div class="min-w-0">
                  <h3 class="text-lg font-bold text-slate-950">{{ activeComponent.title }}</h3>
                  <div v-if="lessonHtml(activeComponent)" class="mt-3 max-w-3xl text-sm leading-6 text-slate-700" v-html="lessonHtml(activeComponent)"></div>
                  <p v-else class="mt-3 max-w-3xl whitespace-pre-line text-sm leading-6 text-slate-700">{{ lessonText(activeComponent) }}</p>
                </div>
              </div>
            </div>
            <div v-else-if="mediaKind(activeComponent) === 'video' && mediaUrl(activeComponent)" class="overflow-hidden rounded-md border border-slate-200 bg-slate-950">
              <video class="aspect-video w-full bg-black" :src="mediaUrl(activeComponent)" :poster="activeComponent.config?.poster_url" controls preload="metadata" playsinline></video>
            </div>
            <div v-else-if="mediaKind(activeComponent) === 'audio' && mediaUrl(activeComponent)" class="rounded-md border border-slate-200 bg-white p-4">
              <audio class="w-full" :src="mediaUrl(activeComponent)" controls preload="metadata"></audio>
            </div>
            <div v-else-if="mediaKind(activeComponent) === 'image' && mediaUrl(activeComponent)" class="overflow-hidden rounded-md border border-slate-200 bg-white">
              <img :src="mediaUrl(activeComponent)" class="max-h-[520px] w-full object-contain" alt="Lesson media" />
            </div>
            <div v-else-if="activeComponent.component_type === 'video'" class="rounded-md border border-dashed border-slate-300 bg-slate-50 p-6">
              <div class="mx-auto max-w-lg text-center">
                <span class="mx-auto grid h-14 w-14 place-items-center rounded-md bg-violet-50 text-violet-700">
                  <Play class="h-7 w-7" />
                </span>
                <h3 class="mt-4 text-lg font-bold text-slate-950">Video đang chờ cập nhật</h3>
                <p class="mt-2 text-sm leading-6 text-slate-600">Bài này đã có trong lộ trình nhưng chưa gắn file phát. Bạn có thể học bài khác hoặc xem toàn bộ journey của khóa.</p>
                <div class="mt-4 flex flex-wrap justify-center gap-2">
                  <button v-if="nextComponent" class="rounded-md bg-slate-950 px-4 py-2 text-sm font-semibold text-white" @click="selectComponent(nextComponent)">Học bài tiếp theo</button>
                  <a :href="journeyHref()" class="rounded-md border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700">Xem journey</a>
                  <a href="/courses" class="rounded-md border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700">My Learning</a>
                </div>
              </div>
            </div>
            <div v-else-if="activeComponent.component_type === 'quiz' || activeComponent.component_type === 'assignment' || activeComponent.component_type === 'forum' || activeComponent.component_type === 'live_session'" class="rounded-md border border-slate-200 bg-slate-50 p-6">
              <span class="grid h-12 w-12 place-items-center rounded-md" :class="accentFor(activeComponent.component_type)">
                <component :is="iconFor(activeComponent.component_type)" class="h-6 w-6" />
              </span>
              <h3 class="mt-4 text-lg font-bold text-slate-950">{{ labelFor(activeComponent.component_type) }}</h3>
              <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-600">
                Hoạt động này nằm trong lộ trình học. Mở hành động bên dưới để làm bài, nộp bài hoặc vào phiên học.
              </p>
              <div class="mt-4 flex flex-wrap gap-2">
                <a v-if="activeComponent.component_type === 'quiz'" :href="quizHref(activeComponent)" class="rounded-md bg-amber-600 px-4 py-2 text-sm font-semibold text-white">Làm quiz</a>
                <a v-if="activeComponent.component_type === 'assignment'" :href="assignmentHref(activeComponent)" class="rounded-md bg-orange-600 px-4 py-2 text-sm font-semibold text-white">Nộp bài</a>
                <a v-if="activeComponent.component_type === 'live_session' && meetingHref(activeComponent)" :href="meetingHref(activeComponent)" target="_blank" rel="noreferrer" class="rounded-md bg-cyan-600 px-4 py-2 text-sm font-semibold text-white">Vào lớp</a>
                <button class="rounded-md border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700" @click="markComplete">Đánh dấu hoàn thành</button>
              </div>
            </div>
            <div v-else class="rounded-md border border-dashed border-slate-300 bg-slate-50 p-6">
              <div class="mx-auto max-w-lg text-center">
                <span class="mx-auto grid h-14 w-14 place-items-center rounded-md" :class="accentFor(activeComponent.component_type)">
                  <component :is="iconFor(activeComponent.component_type)" class="h-7 w-7" />
                </span>
                <h3 class="mt-4 text-lg font-bold text-slate-950">Nội dung đang chờ cập nhật</h3>
                <p class="mt-2 text-sm leading-6 text-slate-600">Bài học đã có trong outline nhưng chưa có nội dung hiển thị. Bạn có thể chuyển sang bài kế tiếp hoặc xem journey để chọn hoạt động khác.</p>
                <div class="mt-4 flex flex-wrap justify-center gap-2">
                  <button v-if="nextComponent" class="rounded-md bg-slate-950 px-4 py-2 text-sm font-semibold text-white" @click="selectComponent(nextComponent)">Học bài tiếp theo</button>
                  <a :href="journeyHref()" class="rounded-md border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700">Xem journey</a>
                  <a href="/courses" class="rounded-md border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700">My Learning</a>
                </div>
              </div>
            </div>

            <div class="mt-5 flex flex-wrap items-center justify-between gap-3 border-t border-slate-200 pt-4">
              <div class="text-sm text-slate-600">
                Điều kiện hoàn thành: {{ activeComponent.required ? 'Bắt buộc' : 'Không bắt buộc' }}
              </div>
              <div class="flex flex-wrap gap-2">
                <button class="inline-flex h-10 items-center gap-2 rounded-md border border-slate-300 px-4 text-sm font-semibold text-slate-700 disabled:opacity-50" :disabled="!previousComponent" @click="selectComponent(previousComponent)">
                  Bài trước
                </button>
                <a v-if="activeComponent.component_type === 'quiz'" :href="quizHref(activeComponent)" class="inline-flex h-10 items-center gap-2 rounded-md bg-amber-600 px-4 text-sm font-semibold text-white hover:bg-amber-700">
                  <ScrollText class="h-4 w-4" />
                  Làm bài thi
                </a>
                <a v-if="activeComponent.component_type === 'quiz'" href="/exams/results" class="inline-flex h-10 items-center gap-2 rounded-md border border-slate-300 px-4 text-sm font-semibold text-slate-700">
                  Xem lại kết quả
                </a>
                <a v-if="activeComponent.component_type === 'live_session' && meetingHref(activeComponent)" :href="meetingHref(activeComponent)" target="_blank" rel="noreferrer" class="inline-flex h-10 items-center gap-2 rounded-md bg-cyan-600 px-4 text-sm font-semibold text-white hover:bg-cyan-700">
                  <Video class="h-4 w-4" />
                  Vào lớp
                </a>
                <button v-if="activeIsCompleted" class="inline-flex h-10 items-center gap-2 rounded-md border border-slate-300 px-4 text-sm font-semibold text-slate-700" @click="restartLesson">
                  <RotateCcw class="h-4 w-4" />
                  Học lại
                </button>
                <button class="inline-flex h-10 items-center gap-2 rounded-md bg-blue-600 px-4 text-sm font-semibold text-white hover:bg-blue-700" @click="markComplete">
                  <CheckCircle2 class="h-4 w-4" />
                  {{ nextComponent ? 'Hoàn thành và học tiếp' : 'Hoàn thành bài cuối' }}
                </button>
                <button class="inline-flex h-10 items-center gap-2 rounded-md border border-slate-300 px-4 text-sm font-semibold text-slate-700 disabled:opacity-50" :disabled="!nextComponent" @click="selectComponent(nextComponent)">
                  Bài tiếp theo
                </button>
              </div>
            </div>
          </section>
          <div v-else class="p-8">
            <div class="mx-auto max-w-lg rounded-md border border-dashed border-slate-300 bg-slate-50 p-6 text-center">
              <span class="mx-auto grid h-14 w-14 place-items-center rounded-md bg-blue-50 text-blue-700">
                <BookOpenText class="h-7 w-7" />
              </span>
              <h2 class="mt-4 text-lg font-bold text-slate-950">Khóa học chưa có hoạt động</h2>
              <p class="mt-2 text-sm leading-6 text-slate-600">Giảng viên có thể đang chuẩn bị nội dung. Bạn có thể quay lại My Learning, xem journey hoặc chọn khóa khác để tiếp tục học.</p>
              <div class="mt-4 flex flex-wrap justify-center gap-2">
                <a href="/courses" class="rounded-md bg-slate-950 px-4 py-2 text-sm font-semibold text-white">My Learning</a>
                <a :href="journeyHref()" class="rounded-md border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700">Xem journey</a>
                <a href="/" class="rounded-md border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700">Về Home</a>
              </div>
            </div>
          </div>
        </main>
      </div>
    </div>
  </EraLmsLayout>
</template>
