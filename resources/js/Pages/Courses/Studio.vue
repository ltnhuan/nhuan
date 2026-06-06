<script setup>
import { computed, onMounted, ref } from 'vue'
import { BookOpenText, CheckCircle2, Database, FileText, FolderOpen, ListChecks, MessageCircle, PenLine, Play, Plus, ScrollText, SlidersHorizontal, Video, Workflow, X } from '@lucide/vue'
import EraLmsLayout from '@/Layouts/EraLmsLayout.vue'
import StudioTopBar from '@/Components/Lms/Studio/StudioTopBar.vue'
import CourseOutline from '@/Components/Lms/Studio/CourseOutline.vue'
import ComponentCard from '@/Components/Lms/Studio/ComponentCard.vue'
import ComponentPicker from '@/Components/Lms/Studio/ComponentPicker.vue'
import InspectorPanel from '@/Components/Lms/Studio/InspectorPanel.vue'
import ActivityLogDrawer from '@/Components/Lms/Studio/ActivityLogDrawer.vue'
import ContentReplaceModal from '@/Components/Lms/Studio/ContentReplaceModal.vue'
import { useStudioActions } from '@/composables/useStudioActions'

const props = defineProps({
  sessionUser: { type: Object, default: null },
  apiHeaders: { type: Object, default: () => ({}) },
})

defineEmits(['logout'])

const {
  studio, course, loading, toast, loadFirstCourse, loadCourse, refreshStudio,
  createSection, createUnit, updateSection, reorderSection, createComponent, updateComponent,
  deleteComponent, duplicateComponent, replaceComponentContent, submitReview,
  approveCourse, publishCourse, saveDraft,
} = useStudioActions(props.apiHeaders)

const selectedUnit = ref(null)
const selectedComponent = ref(null)
const historyOpen = ref(false)
const replaceOpen = ref(false)
const pickerOpen = ref(false)
const replaceTarget = ref(null)
const repositoryItems = ref([])
const loadingPage = ref(false)
const courseManagerOpen = ref(false)
const courses = ref([])
const loadingCourses = ref(false)
const creatingCourse = ref(false)
const sourceOpen = ref(false)
const sourceLoading = ref(false)
const sourceItems = ref([])
const sourceTarget = ref(null)
const sourceTitle = ref('Chọn dữ liệu')
const youtubeQuickUrl = ref('')

const outline = computed(() => studio.value?.outline || [])
const checklist = computed(() => studio.value?.checklist || { groups: {} })
const selectedComponents = computed(() => selectedUnit.value?.components || [])
const lessonProgress = computed(() => {
  const hasOutline = outline.value.length > 0
  const hasUnit = Boolean(firstUnit(outline.value))
  const hasActivities = selectedComponents.value.length > 0
  const checklistItems = Object.values(checklist.value?.groups || {}).flat()
  const readyItems = checklistItems.length ? checklistItems.filter((item) => item.passed).length : 0
  const publishReady = checklistItems.length ? readyItems === checklistItems.length : hasActivities

  return [
    { label: 'Tạo khung', description: 'Có chương và bài học', done: hasOutline && hasUnit },
    { label: 'Chọn bài', description: 'Đang soạn một bài học', done: Boolean(selectedUnit.value) },
    { label: 'Thêm hoạt động', description: 'Nội dung người học sẽ làm', done: hasActivities },
    { label: 'Kiểm tra & xuất bản', description: checklistItems.length ? `${readyItems}/${checklistItems.length} mục đạt` : 'Sẵn sàng xem thử', done: publishReady },
  ]
})
const nextStep = computed(() => lessonProgress.value.find((item) => !item.done) || lessonProgress.value[lessonProgress.value.length - 1])
const totalMinutes = computed(() => selectedComponents.value.reduce((total, item) => total + Number(item.config?.estimated_minutes || 0), 0))
const activityPalette = [
  ['text', 'Text', 'Bài học văn bản', BookOpenText, 'blue'],
  ['video', 'Video', 'Video bài giảng', Play, 'violet'],
  ['pdf', 'File', 'Tài liệu PDF/Doc', FileText, 'red'],
  ['quiz', 'Quiz', 'Bài kiểm tra', ScrollText, 'amber'],
  ['assignment', 'Assignment', 'Bài tập', PenLine, 'orange'],
  ['forum', 'Forum', 'Diễn đàn', MessageCircle, 'emerald'],
  ['scorm', 'SCORM', 'SCORM/xAPI', Workflow, 'indigo'],
  ['live_session', 'Live session', 'Buổi học trực tuyến', Video, 'cyan'],
]

function toneClass(tone) {
  const tones = {
    blue: 'bg-blue-50 text-blue-700 ring-blue-100',
    violet: 'bg-violet-50 text-violet-700 ring-violet-100',
    red: 'bg-red-50 text-red-700 ring-red-100',
    amber: 'bg-amber-50 text-amber-700 ring-amber-100',
    orange: 'bg-orange-50 text-orange-700 ring-orange-100',
    emerald: 'bg-emerald-50 text-emerald-700 ring-emerald-100',
    indigo: 'bg-indigo-50 text-indigo-700 ring-indigo-100',
    cyan: 'bg-cyan-50 text-cyan-700 ring-cyan-100',
  }
  return tones[tone] || tones.blue
}

async function boot() {
  loadingPage.value = true
  try {
    const params = new URLSearchParams(window.location.search)
    const courseId = Number(params.get('course_id'))
    if (courseId) await loadCourse(courseId)
    else await loadFirstCourse()
    selectedUnit.value = firstUnit(outline.value)
  } finally {
    loadingPage.value = false
  }
}

async function loadCourses() {
  loadingCourses.value = true
  try {
    const response = await fetch('/api/v1/courses?per_page=50', { headers: props.apiHeaders })
    const payload = await response.json()
    courses.value = payload.data || []
  } finally {
    loadingCourses.value = false
  }
}

async function openCourseManager() {
  courseManagerOpen.value = true
  await loadCourses()
}

async function selectCourse(item) {
  await loadCourse(item.id)
  selectedUnit.value = firstUnit(outline.value)
  selectedComponent.value = null
  courseManagerOpen.value = false
  window.history.replaceState({}, '', `/courses/studio?course_id=${item.id}`)
}

async function createNewLesson() {
  const title = window.prompt('Tên bài mới', 'Bài học mới')
  if (!title) return
  creatingCourse.value = true
  try {
    const response = await fetch('/api/v1/courses', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', ...props.apiHeaders },
      body: JSON.stringify({
        code: `LESSON-${Date.now().toString().slice(-6)}`,
        title,
        level: 'college',
        course_type: 'blended',
        visibility: 'tenant',
        language: 'vi',
      }),
    })
    const payload = await response.json()
    const created = payload.data || payload
    await selectCourse(created)
  } finally {
    creatingCourse.value = false
  }
}

function firstUnit(nodes) {
  for (const node of nodes || []) {
    if (node.type === 'unit') return node
    const found = firstUnit(node.children || [])
    if (found) return found
  }
  return null
}

function findUnitById(nodes, id) {
  for (const node of nodes || []) {
    if (node.id === id) return node
    const found = findUnitById(node.children || [], id)
    if (found) return found
  }
  return null
}

async function addSection() {
  const title = window.prompt('Tên chương mới', `Chương ${Date.now().toString().slice(-4)}`)
  if (!title) return
  await createSection({ type: 'section', title, status: 'draft' })
  selectedUnit.value = firstUnit(outline.value)
}

async function addUnit(section) {
  section = section || outline.value[0]
  if (!section) return
  const title = window.prompt('Tên unit mới', `Unit ${Date.now().toString().slice(-4)}`)
  if (!title) return
  await createUnit(section.id, { title, status: 'draft' })
  selectedUnit.value = firstUnit(outline.value)
}

async function moveSection({ section, direction }) {
  const list = [...outline.value]
  const index = list.findIndex((item) => item.id === section.id)
  const target = direction === 'up' ? index - 1 : index + 1
  if (index < 0 || target < 0 || target >= list.length) return
  const swapped = [...list]
  const currentSort = swapped[index].sort_order
  swapped[index].sort_order = swapped[target].sort_order
  swapped[target].sort_order = currentSort
  await reorderSection(swapped.map((item, idx) => ({ id: item.id, parent_id: item.parent_id || null, sort_order: idx + 1 })))
}

async function addComponent(type) {
  pickerOpen.value = false
  if (!selectedUnit.value) return
  const currentUnitId = selectedUnit.value.id
  const title = window.prompt(`Tên ${type}`, `${type.toUpperCase()} component`)
  if (!title) return

  const body = {
    section_id: selectedUnit.value.id,
    component_type: type === 'live_session' ? 'forum' : type,
    title,
    required: true,
    status: 'draft',
    config: { estimated_minutes: 10, completion_rule: { type: 'view' }, clo_mapping: [] },
  }

  if (['video', 'pdf', 'file', 'scorm'].includes(body.component_type)) {
    await ensureRepositoryItems()
    const picked = repositoryItems.value[0]
    if (picked) body.content_id = picked.id
  }

  await createComponent(body)
  selectedUnit.value = findUnitById(outline.value, currentUnitId) || firstUnit(outline.value)
  const created = selectedUnit.value?.components?.[selectedUnit.value.components.length - 1]
  if (created && ['quiz', 'pdf', 'file', 'video', 'scorm', 'assignment'].includes(created.component_type)) {
    selectedComponent.value = created
    await openSourcePicker(created)
  }
}

async function editComponent(component) {
  const title = window.prompt('Tên component', component.title)
  if (!title) return
  await updateComponent(component.id, { title, config: component.config || {} })
}

async function saveUnit(unit, body) {
  await updateSection(unit.id, body)
  selectedUnit.value = findUnitById(outline.value, unit.id) || firstUnit(outline.value)
}

async function saveComponent(component, body) {
  const currentUnitId = selectedUnit.value?.id
  await updateComponent(component.id, body)
  const refreshed = findUnitById(outline.value, currentUnitId) || firstUnit(outline.value)
  selectedUnit.value = refreshed
  selectedComponent.value = refreshed?.components?.find((item) => item.id === component.id) || null
}

async function openSourcePicker(component) {
  sourceTarget.value = component
  sourceOpen.value = true
  sourceLoading.value = true
  sourceItems.value = []
  youtubeQuickUrl.value = component.config?.youtube_url || ''
  try {
    let url = '/api/v1/repository/items?per_page=50'
    sourceTitle.value = 'Chọn học liệu từ kho'
    if (component.component_type === 'quiz') {
      url = '/api/v1/exams/select-options'
      sourceTitle.value = 'Chọn đề trắc nghiệm đã cấu hình'
    } else if (component.component_type === 'assignment') {
      url = '/api/v1/assignments?per_page=50'
      sourceTitle.value = 'Chọn bài tập'
    }
    const response = await fetch(url, { headers: props.apiHeaders })
    const payload = await response.json()
    sourceItems.value = payload.data || payload || []
  } finally {
    sourceLoading.value = false
  }
}

async function attachYoutube() {
  if (!sourceTarget.value || !youtubeQuickUrl.value.trim()) return
  const target = sourceTarget.value
  await updateComponent(target.id, {
    title: target.title,
    config: {
      ...(target.config || {}),
      source_type: 'youtube',
      youtube_url: youtubeQuickUrl.value.trim(),
      media_url: youtubeQuickUrl.value.trim(),
    },
  })
  sourceOpen.value = false
  sourceTarget.value = null
}

async function chooseSource(item) {
  if (!sourceTarget.value) return
  const target = sourceTarget.value
  const configKey = target.component_type === 'quiz' ? 'exam_id' : target.component_type === 'assignment' ? 'assignment_id' : null
  const quizConfig = target.component_type === 'quiz'
    ? {
        exam_id: item.id,
        exam_title: item.title,
        exam_type: item.exam_type,
        duration_minutes: item.duration_minutes || 45,
        pass_score: item.pass_score || 50,
        total_score: item.total_score || 100,
        max_attempts: item.max_attempts || 1,
        shuffle_questions: item.shuffle_questions ?? true,
        shuffle_options: item.shuffle_options ?? true,
        show_result_mode: item.show_result_mode || 'immediately',
        completion_rule: { type: 'score', score: item.pass_score || 50 },
        estimated_minutes: item.duration_minutes || target.config?.estimated_minutes || 45,
      }
    : {}
  const body = {
    title: item.title || item.name || target.title,
    config: { ...(target.config || {}), ...(configKey ? { [configKey]: item.id } : {}), ...quizConfig },
  }
  if (!configKey) body.content_id = item.id
  await updateComponent(target.id, body)
  sourceOpen.value = false
  sourceTarget.value = null
}

async function preview(component = null) {
  if (!course.value) return
  const params = new URLSearchParams({ course_id: course.value.id })
  if (component?.id) params.set('component_id', component.id)
  window.location.href = `/courses/learn?${params.toString()}`
}

async function openReplace(component) {
  replaceTarget.value = component
  await ensureRepositoryItems()
  replaceOpen.value = true
}

async function chooseReplacement(item) {
  if (!replaceTarget.value) return
  await replaceComponentContent(replaceTarget.value.id, item.id)
  replaceOpen.value = false
  replaceTarget.value = null
}

async function ensureRepositoryItems() {
  if (repositoryItems.value.length) return
  const response = await fetch('/api/v1/repository/items?per_page=50', { headers: props.apiHeaders })
  const payload = await response.json()
  repositoryItems.value = payload.data || []
}

async function save() {
  if (!course.value) return
  await saveDraft({ settings: { ...(course.value.settings || {}), studio_saved_at: new Date().toISOString() } })
}

onMounted(boot)
</script>

<template>
  <EraLmsLayout :session-user="sessionUser" @logout="$emit('logout')">
    <template #breadcrumb>Khóa học / Studio bài giảng</template>

    <div class="min-h-[calc(100vh-5rem)] bg-slate-100">
      <StudioTopBar
        :course="course"
        :loading="loading"
        @save="save"
        @preview="preview()"
        @submit="submitReview"
        @approve="approveCourse"
        @publish="publishCourse"
        @history="historyOpen = true"
        @settings="selectedComponent = null"
        @manage="openCourseManager"
      />

      <div v-if="toast.show" class="mx-4 mt-3 rounded-md px-4 py-3 text-sm font-semibold" :class="toast.type === 'success' ? 'bg-emerald-50 text-emerald-700' : 'bg-red-50 text-red-700'">
        {{ toast.message }}
      </div>

      <div v-if="loadingPage" class="p-6 text-sm text-slate-500">Đang tải Course Studio...</div>
      <div v-else-if="!course" class="p-6 text-sm text-slate-500">Chưa có khóa học. Vào /courses để tạo khóa học trước.</div>

      <div v-else class="overflow-hidden rounded-b-xl border-x border-b border-slate-200 bg-white">
        <section class="border-b border-slate-200 bg-white px-5 py-4">
          <div class="grid gap-3 lg:grid-cols-[1fr_280px]">
            <div class="grid gap-2 md:grid-cols-4">
              <div
                v-for="(step, index) in lessonProgress"
                :key="step.label"
                class="rounded-md border px-3 py-3"
                :class="step.done ? 'border-emerald-200 bg-emerald-50' : index === lessonProgress.findIndex((item) => !item.done) ? 'border-blue-200 bg-blue-50' : 'border-slate-200 bg-slate-50'"
              >
                <div class="flex items-center gap-2">
                  <span
                    class="grid h-7 w-7 place-items-center rounded-full text-xs font-bold"
                    :class="step.done ? 'bg-emerald-600 text-white' : 'bg-white text-slate-700 ring-1 ring-slate-200'"
                  >
                    <CheckCircle2 v-if="step.done" class="h-4 w-4" />
                    <span v-else>{{ index + 1 }}</span>
                  </span>
                  <div class="min-w-0">
                    <div class="truncate text-sm font-bold text-slate-950">{{ step.label }}</div>
                    <div class="truncate text-xs text-slate-600">{{ step.description }}</div>
                  </div>
                </div>
              </div>
            </div>
            <div class="rounded-md border border-blue-200 bg-blue-50 p-3">
              <div class="flex items-center gap-2 text-xs font-bold uppercase text-blue-700">
                <ListChecks class="h-4 w-4" />
                Việc cần làm tiếp theo
              </div>
              <div class="mt-1 text-sm font-semibold text-slate-950">{{ nextStep.label }}</div>
              <div class="mt-1 text-xs text-slate-600">{{ nextStep.description }}</div>
            </div>
          </div>
        </section>
        <div class="flex min-h-[calc(100vh-18rem)]">
          <CourseOutline
            :outline="outline"
            :selected-unit-id="selectedUnit?.id"
            @select-unit="(unit) => { selectedUnit = unit; selectedComponent = null }"
            @add-section="addSection"
            @add-unit="addUnit"
            @move="moveSection"
          />

          <main class="min-w-0 flex-1 p-4">
            <div class="mb-4 flex items-start justify-between gap-4">
              <div>
                <div class="mb-2 text-xs font-bold uppercase text-blue-700">Bước 2 · Soạn tiến trình một bài học</div>
                <div class="flex items-center gap-2">
                  <h2 class="text-lg font-bold text-slate-950">{{ selectedUnit?.title || 'Chọn unit để bắt đầu' }}</h2>
                  <button v-if="selectedUnit" class="grid h-7 w-7 place-items-center rounded-md text-blue-600 hover:bg-blue-50 hover:text-blue-700" title="Sửa unit">
                    <PenLine class="h-4 w-4" />
                  </button>
                </div>
                <p class="mt-1 text-xs text-slate-500">
                  {{ selectedComponents.length }} hoạt động học tập · Thời lượng ước tính: {{ totalMinutes || 0 }} phút
                </p>
              </div>
              <div class="flex items-center gap-2">
                <button class="inline-flex h-10 items-center gap-2 rounded-md bg-blue-600 px-4 text-sm font-semibold text-white shadow-sm hover:bg-blue-700" @click="pickerOpen = true"><Plus class="h-4 w-4" />Thêm hoạt động</button>
                <button class="inline-flex h-10 items-center gap-2 rounded-md border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 hover:bg-slate-50"><SlidersHorizontal class="h-4 w-4" />Sắp xếp</button>
              </div>
            </div>

            <div class="overflow-hidden rounded-md border border-slate-200 bg-white">
              <div
                class="grid gap-3 border-b border-slate-200 bg-slate-50 px-3 py-3 text-xs font-bold text-slate-500"
                style="grid-template-columns: 24px minmax(160px, 1fr) 60px 66px 72px 124px;"
              >
                <span></span>
                <span>Tiến trình học</span>
                <span>Loại</span>
                <span>Thời lượng</span>
                <span>Trạng thái</span>
                <span class="text-right">Thao tác</span>
              </div>
              <div v-if="!selectedUnit" class="bg-white p-8 text-center text-sm text-slate-500">Chọn một bài học ở cột trái hoặc tạo bài học mới để bắt đầu.</div>
              <div v-else-if="selectedComponents.length === 0" class="bg-white p-8 text-center">
                <div class="text-sm font-semibold text-slate-800">Bài học này chưa có hoạt động.</div>
                <div class="mt-1 text-sm text-slate-500">Thêm video, tài liệu, quiz hoặc bài tập theo đúng thứ tự người học sẽ thực hiện.</div>
                <button class="mt-4 inline-flex h-10 items-center gap-2 rounded-md bg-blue-600 px-4 text-sm font-semibold text-white hover:bg-blue-700" @click="pickerOpen = true">
                  <Plus class="h-4 w-4" />
                  Thêm hoạt động đầu tiên
                </button>
              </div>
              <ComponentCard
                v-for="component in selectedComponents"
                :key="component.id"
                :component="component"
                :active="selectedComponent?.id === component.id"
                @edit="editComponent"
                @preview="preview"
                @replace="openReplace"
                @duplicate="(item) => duplicateComponent(item.id)"
                @delete="(item) => deleteComponent(item.id)"
                @click="selectedComponent = component"
              />
            </div>
            <button
              v-if="selectedUnit"
              class="mt-4 flex h-16 w-full items-center justify-center gap-2 rounded-md border border-dashed border-blue-300 bg-blue-50/40 text-sm font-semibold text-blue-700 hover:bg-blue-50"
              @click="pickerOpen = true"
            >
              <Plus class="h-4 w-4" />
              Thêm hoạt động vào unit
            </button>
          </main>

          <InspectorPanel
            :course="course"
            :unit="selectedUnit"
            :component="selectedComponent"
            :checklist="checklist"
            :api-headers="apiHeaders"
            @save-unit="saveUnit"
            @save-component="saveComponent"
            @load-source="openSourcePicker"
          />
        </div>
        <div class="border-t border-slate-200 bg-white px-4 py-3">
          <div class="grid grid-cols-2 gap-3 md:grid-cols-4 xl:grid-cols-8">
            <button
              v-for="[key, label, sub, Icon, tone] in activityPalette"
              :key="key"
              class="flex h-16 items-center gap-3 rounded-md border border-slate-200 bg-white px-3 text-left hover:border-blue-200 hover:bg-slate-50"
              @click="addComponent(key)"
            >
              <span class="grid h-10 w-10 shrink-0 place-items-center rounded-md ring-1" :class="toneClass(tone)">
                <component :is="Icon" class="h-5 w-5" />
              </span>
              <span class="min-w-0">
                <span class="block truncate text-sm font-bold text-slate-900">{{ label }}</span>
                <span class="block truncate text-[11px] text-slate-500">{{ sub }}</span>
              </span>
            </button>
          </div>
        </div>
      </div>

      <ActivityLogDrawer :open="historyOpen" :logs="studio?.publish_logs || []" @close="historyOpen = false" />
      <ContentReplaceModal :open="replaceOpen" :items="repositoryItems" @close="replaceOpen = false" @select="chooseReplacement" />
      <ComponentPicker :open="pickerOpen" @close="pickerOpen = false" @pick="addComponent" />

      <div v-if="sourceOpen" class="fixed inset-0 z-50">
        <button class="absolute inset-0 bg-slate-950/20" @click="sourceOpen = false"></button>
        <aside class="absolute right-4 top-4 flex h-[calc(100vh-2rem)] w-[560px] max-w-[calc(100vw-2rem)] flex-col overflow-hidden rounded-xl border border-slate-200 bg-white shadow-2xl">
          <div class="flex h-16 items-center justify-between border-b border-slate-200 px-5">
            <div>
              <h2 class="text-base font-bold text-slate-950">{{ sourceTitle }}</h2>
              <p class="mt-0.5 text-xs text-slate-500">Chọn một mục để gắn ngay vào hoạt động đang sửa.</p>
            </div>
            <button class="grid h-9 w-9 place-items-center rounded-md text-slate-500 hover:bg-slate-100" @click="sourceOpen = false">
              <X class="h-4 w-4" />
            </button>
          </div>
          <div class="min-h-0 flex-1 overflow-auto p-4">
            <div v-if="sourceTarget?.component_type === 'video'" class="mb-4 rounded-md border border-violet-200 bg-violet-50 p-3">
              <div class="text-xs font-bold uppercase text-violet-800">Chèn YouTube nhanh</div>
              <div class="mt-2 flex gap-2">
                <input v-model="youtubeQuickUrl" class="h-10 min-w-0 flex-1 rounded-md border border-slate-300 bg-white px-3 text-sm" placeholder="https://www.youtube.com/watch?v=..." />
                <button class="h-10 rounded-md bg-violet-600 px-4 text-sm font-semibold text-white hover:bg-violet-700" @click="attachYoutube">Chèn</button>
              </div>
            </div>
            <div v-if="sourceLoading" class="p-6 text-center text-sm text-slate-500">Đang load dữ liệu...</div>
            <div v-else class="space-y-2">
              <button
                v-for="item in sourceItems"
                :key="item.id"
                class="flex w-full items-center gap-3 rounded-md border border-slate-200 bg-white p-3 text-left hover:border-blue-200 hover:bg-blue-50"
                @click="chooseSource(item)"
              >
                <span class="grid h-10 w-10 place-items-center rounded-md bg-blue-50 text-blue-700">
                  <Database class="h-5 w-5" />
                </span>
                <span class="min-w-0">
                  <span class="block truncate text-sm font-bold text-slate-950">{{ item.title || item.name }}</span>
                  <span class="mt-1 block truncate text-xs text-slate-500">
                    <template v-if="sourceTarget?.component_type === 'quiz'">
                      {{ item.exam_type || 'quiz' }} · {{ item.duration_minutes || 0 }} phút · đạt {{ item.pass_score || 0 }}/{{ item.total_score || 0 }}
                    </template>
                    <template v-else>{{ item.code || item.status || item.item_type || 'Có thể gắn vào hoạt động' }}</template>
                  </span>
                </span>
              </button>
              <div v-if="sourceItems.length === 0" class="rounded-md border border-dashed border-slate-300 p-8 text-center text-sm text-slate-500">Chưa có dữ liệu trong kho này.</div>
            </div>
          </div>
        </aside>
      </div>

      <div v-if="courseManagerOpen" class="fixed inset-0 z-50">
        <button class="absolute inset-0 bg-slate-950/20" @click="courseManagerOpen = false"></button>
        <aside class="absolute right-4 top-4 flex h-[calc(100vh-2rem)] w-[560px] max-w-[calc(100vw-2rem)] flex-col overflow-hidden rounded-xl border border-slate-200 bg-white shadow-2xl">
          <div class="flex h-16 items-center justify-between border-b border-slate-200 px-5">
            <div>
              <h2 class="text-base font-bold text-slate-950">Quản lý bài đã soạn</h2>
              <p class="mt-0.5 text-xs text-slate-500">Chọn một bài để load lên Studio và tiếp tục sửa.</p>
            </div>
            <button class="grid h-9 w-9 place-items-center rounded-md text-slate-500 hover:bg-slate-100" @click="courseManagerOpen = false">
              <X class="h-4 w-4" />
            </button>
          </div>
          <div class="border-b border-slate-200 bg-slate-50 p-4">
            <button class="inline-flex h-10 items-center gap-2 rounded-md bg-blue-600 px-4 text-sm font-semibold text-white hover:bg-blue-700 disabled:opacity-60" :disabled="creatingCourse" @click="createNewLesson">
              <Plus class="h-4 w-4" />
              Thêm bài mới
            </button>
          </div>
          <div class="min-h-0 flex-1 overflow-auto p-4">
            <div v-if="loadingCourses" class="p-6 text-center text-sm text-slate-500">Đang tải danh sách bài...</div>
            <div v-else class="space-y-2">
              <button
                v-for="item in courses"
                :key="item.id"
                class="flex w-full items-center justify-between gap-3 rounded-md border p-3 text-left hover:border-blue-200 hover:bg-blue-50"
                :class="item.id === course?.id ? 'border-blue-300 bg-blue-50' : 'border-slate-200 bg-white'"
                @click="selectCourse(item)"
              >
                <span class="min-w-0">
                  <span class="block truncate text-sm font-bold text-slate-950">{{ item.title }}</span>
                  <span class="mt-1 block truncate text-xs text-slate-500">{{ item.code }} · {{ item.status }} · {{ item.course_type }}</span>
                </span>
                <span class="inline-flex h-9 shrink-0 items-center gap-2 rounded-md border border-slate-300 bg-white px-3 text-xs font-semibold text-slate-700">
                  <FolderOpen class="h-4 w-4" />
                  Sửa
                </span>
              </button>
              <div v-if="courses.length === 0" class="rounded-md border border-dashed border-slate-300 p-8 text-center text-sm text-slate-500">Chưa có bài nào. Bấm “Thêm bài mới” để tạo.</div>
            </div>
          </div>
        </aside>
      </div>
    </div>
  </EraLmsLayout>
</template>
