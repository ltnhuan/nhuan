<script setup>
import { computed, defineAsyncComponent, onMounted, ref } from 'vue'
import {
  BookOpen,
  Bot,
  Brain,
  CalendarDays,
  CheckCircle2,
  ChevronRight,
  ClipboardCheck,
  FileText,
  Flame,
  Link2,
  ListChecks,
  Loader2,
  MessageSquare,
  PlayCircle,
  Search,
  Send,
  ShieldCheck,
  Sparkles,
  Target,
  UploadCloud,
  WandSparkles,
  Video,
} from '@lucide/vue'
import EraLmsLayout from '@/Layouts/EraLmsLayout.vue'

const RichTextEditor = defineAsyncComponent(() => import('@/Components/Lms/RichTextEditor.vue'))

const props = defineProps({
  sessionUser: { type: Object, default: null },
  apiHeaders: { type: Object, default: () => ({}) },
})

defineEmits(['logout'])

const assistantModes = [
  { key: 'tutor', label: 'Tutor', hint: 'Hỏi theo nguồn', icon: Brain },
  { key: 'planner', label: 'Planner', hint: 'Kế hoạch học', icon: CalendarDays },
  { key: 'assignment', label: 'Assignment', hint: 'Rubric và checklist', icon: ClipboardCheck },
  { key: 'video', label: 'Video', hint: 'Transcript', icon: Video },
  { key: 'quiz', label: 'Quiz', hint: 'Luyện tập', icon: ListChecks },
  { key: 'flashcard', label: 'Cards', hint: 'Ôn nhanh', icon: WandSparkles },
]

const sourceTypeText = {
  pdf: 'PDF',
  ppt: 'Slide',
  docx: 'Tài liệu',
  repository: 'Repository',
  video_transcript: 'Transcript',
  assignment: 'Assignment',
  slide: 'Slide',
}

const toneClass = {
  blue: 'border-blue-200 bg-blue-50 text-blue-800 hover:border-blue-300 hover:bg-blue-100',
  green: 'border-emerald-200 bg-emerald-50 text-emerald-800 hover:border-emerald-300 hover:bg-emerald-100',
  yellow: 'border-amber-200 bg-amber-50 text-amber-900 hover:border-amber-300 hover:bg-amber-100',
  red: 'border-rose-200 bg-rose-50 text-rose-800 hover:border-rose-300 hover:bg-rose-100',
  purple: 'border-violet-200 bg-violet-50 text-violet-800 hover:border-violet-300 hover:bg-violet-100',
}

const workspace = ref(null)
const documents = ref([])
const analytics = ref(null)
const selectedDocumentId = ref(null)
const selectedCourseId = ref(null)
const activeMode = ref('tutor')
const question = ref('Tóm tắt bài đang học và cho tôi 3 câu tự kiểm.')
const messages = ref([
  {
    role: 'assistant',
    text: 'Chọn nguồn học liệu, đặt câu hỏi, rồi tôi sẽ trả lời theo citation và đề xuất bước học tiếp theo.',
    citations: [],
  },
])
const answer = ref(null)
const summary = ref(null)
const quiz = ref(null)
const flashcards = ref([])
const ingestForm = ref({
  title: 'Học liệu AI mới',
  source_type: 'pdf',
  content: 'AI Study Companion trả lời theo nguồn học liệu, tạo kế hoạch học, sinh quiz, flashcard, checklist bài tập và nhắc người học bước tiếp theo. Khi thiếu nguồn, trợ lý nói rõ giới hạn và đề xuất ingest thêm PDF, slide hoặc transcript.',
})
const booting = ref(true)
const loading = ref(false)
const busyAction = ref(null)
const statusMessage = ref('')

const selectedDocument = computed(() => documents.value.find((item) => item.id === selectedDocumentId.value))
const activeModeMeta = computed(() => assistantModes.find((mode) => mode.key === activeMode.value) || assistantModes[0])
const assistant = computed(() => workspace.value?.assistant || {})
const learner = computed(() => workspace.value?.learner || {})
const context = computed(() => workspace.value?.context || {})
const courses = computed(() => workspace.value?.courses || [])
const selectedCourse = computed(() => courses.value.find((course) => course.id === selectedCourseId.value) || workspace.value?.course || null)
const knowledge = computed(() => workspace.value?.knowledge || {})
const related = computed(() => workspace.value?.related || { quizzes: [], flashcards: [], conversations: [] })
const studyPlan = computed(() => workspace.value?.study_plan || { today: [], this_week: {} })
const safetyRules = computed(() => workspace.value?.safety || [])
const promptChips = computed(() => workspace.value?.suggestions?.length ? workspace.value.suggestions : fallbackSuggestions())
const tools = computed(() => workspace.value?.tools?.length ? workspace.value.tools : assistantModes.map((mode) => ({
  key: mode.key,
  label: mode.label,
  assistant_type: mode.key,
  description: mode.hint,
})))
const mindmapJson = computed(() => summary.value ? JSON.stringify(summary.value.mindmap, null, 2) : '')
const sourceCountLabel = computed(() => `${knowledge.value.scope_documents || documents.value.length || 0}/${knowledge.value.documents_ready || 0}`)
const selectedCourseProgress = computed(() => Math.round(Number(selectedCourse.value?.progress_percent || 0)))

async function api(path, options = {}) {
  const hasBody = options.body !== undefined
  const response = await fetch(`/api/v1${path}`, {
    ...options,
    headers: {
      Accept: 'application/json',
      ...(hasBody ? { 'Content-Type': 'application/json' } : {}),
      ...props.apiHeaders,
      ...(options.headers || {}),
    },
  })

  const data = await response.json()
  if (!response.ok) {
    throw new Error(data.message || 'Không thể gọi AI API.')
  }

  return data
}

async function loadData() {
  booting.value = true
  statusMessage.value = ''

  try {
    applyWorkspaceData(await api(workspacePath()), false)
  } catch (error) {
    statusMessage.value = error.message
  } finally {
    booting.value = false
  }
}

async function refreshWorkspace() {
  applyWorkspaceData(await api(workspacePath()), true)
}

function workspacePath() {
  const params = new URLSearchParams()
  if (selectedCourseId.value) {
    params.set('course_id', selectedCourseId.value)
  }
  if (selectedDocumentId.value) {
    params.set('document_id', selectedDocumentId.value)
  }

  return `/ai/workspace${params.toString() ? `?${params.toString()}` : ''}`
}

function applyWorkspaceData(workspaceData, preserveDocument = true) {
  workspace.value = workspaceData
  analytics.value = { top_questions: workspaceData.knowledge?.top_questions || [] }
  documents.value = (workspaceData.documents || []).map((document) => normalizeDocument(document))

  const courseFromContext = workspaceData.context?.selected_course_id || workspaceData.course?.id || workspaceData.courses?.[0]?.id || null
  selectedCourseId.value = selectedCourseId.value || courseFromContext

  const preferredDocumentId = preserveDocument && documents.value.some((document) => document.id === selectedDocumentId.value)
    ? selectedDocumentId.value
    : workspaceData.context?.selected_document_id || documents.value[0]?.id || null

  selectedDocumentId.value = preferredDocumentId
}

async function changeCourse() {
  selectedDocumentId.value = null
  summary.value = null
  quiz.value = null
  flashcards.value = []
  await refreshWorkspace()
}

async function changeDocument() {
  const document = selectedDocument.value
  if (document?.course_id) {
    selectedCourseId.value = document.course_id
  }
  summary.value = null
  quiz.value = null
  flashcards.value = []
  await refreshWorkspace()
}

async function selectDocument(document) {
  selectedDocumentId.value = document.id
  if (document.course_id) {
    selectedCourseId.value = document.course_id
  }
  summary.value = null
  quiz.value = null
  flashcards.value = []
  await refreshWorkspace()
}

async function ask(customPrompt = null, mode = null) {
  const prompt = String(customPrompt || question.value || '').trim()
  if (!prompt) {
    return
  }

  const assistantType = mode || activeMode.value
  loading.value = true
  busyAction.value = 'ask'
  statusMessage.value = ''
  messages.value.push({ role: 'user', text: prompt, citations: [] })
  question.value = customPrompt ? question.value : ''

  try {
    const response = await api('/ai/ask', {
      method: 'POST',
      body: JSON.stringify({
        course_id: selectedDocument.value?.course_id || selectedCourseId.value || null,
        document_id: selectedDocumentId.value || null,
        assistant_type: assistantType,
        question: prompt,
      }),
    })

    answer.value = response
    messages.value.push({
      role: 'assistant',
      text: response.answer,
      citations: response.citations || [],
    })
    await refreshWorkspace()
  } catch (error) {
    messages.value.push({ role: 'assistant', text: error.message, citations: [] })
  } finally {
    loading.value = false
    busyAction.value = null
  }
}

async function runSuggestion(suggestion) {
  activeMode.value = suggestion.assistant_type || activeMode.value

  if (suggestion.tool === 'quiz') {
    question.value = suggestion.prompt
    await generateQuiz()
    return
  }

  await ask(suggestion.prompt, suggestion.assistant_type)
}

async function runPlanItem(item) {
  const action = String(item.action || '').toLowerCase()
  if (action.includes('quiz')) {
    await generateQuiz()
    return
  }
  if (action.includes('flashcard')) {
    await generateFlashcards()
    return
  }

  await ask(item.prompt || item.title, 'planner')
}

async function ingest() {
  busyAction.value = 'ingest'
  statusMessage.value = ''

  try {
    const document = await api('/ai/ingest', {
      method: 'POST',
      body: JSON.stringify({
        ...ingestForm.value,
        course_id: selectedCourseId.value || null,
        metadata: { source: 'ai_workspace_ui' },
      }),
    })

    documents.value = [normalizeDocument(document), ...documents.value]
    selectedDocumentId.value = document.id
    statusMessage.value = 'Đã nạp học liệu vào AI workspace.'
    await refreshWorkspace()
  } catch (error) {
    statusMessage.value = error.message
  } finally {
    busyAction.value = null
  }
}

async function generateSummary() {
  if (!selectedDocumentId.value) {
    statusMessage.value = 'Chưa có nguồn học liệu để tóm tắt.'
    return
  }

  busyAction.value = 'summary'
  statusMessage.value = ''

  try {
    summary.value = await api('/ai/summary', {
      method: 'POST',
      body: JSON.stringify({
        course_id: selectedDocument.value?.course_id || selectedCourseId.value || null,
        document_id: selectedDocumentId.value,
        title: selectedDocument.value?.title,
      }),
    })
  } catch (error) {
    statusMessage.value = error.message
  } finally {
    busyAction.value = null
  }
}

async function generateQuiz() {
  if (!selectedDocumentId.value) {
    statusMessage.value = 'Chưa có nguồn học liệu để tạo quiz.'
    return
  }

  busyAction.value = 'quiz'
  statusMessage.value = ''

  try {
    quiz.value = await api('/ai/quizzes', {
      method: 'POST',
      body: JSON.stringify({
        course_id: selectedDocument.value?.course_id || selectedCourseId.value || null,
        document_id: selectedDocumentId.value,
        title: `Quiz luyện tập - ${selectedDocument.value?.title || 'AI Workspace'}`,
        types: ['mcq', 'essay', 'fill_blank', 'matching'],
      }),
    })
    await refreshWorkspace()
  } catch (error) {
    statusMessage.value = error.message
  } finally {
    busyAction.value = null
  }
}

async function generateFlashcards() {
  if (!selectedDocumentId.value) {
    statusMessage.value = 'Chưa có nguồn học liệu để tạo flashcard.'
    return
  }

  busyAction.value = 'flashcards'
  statusMessage.value = ''

  try {
    flashcards.value = await api('/ai/flashcards', {
      method: 'POST',
      body: JSON.stringify({
        course_id: selectedDocument.value?.course_id || selectedCourseId.value || null,
        document_id: selectedDocumentId.value,
        count: 6,
      }),
    })
    await refreshWorkspace()
  } catch (error) {
    statusMessage.value = error.message
  } finally {
    busyAction.value = null
  }
}

function normalizeDocument(document) {
  return {
    ...document,
    chunks_count: Number(document.chunks_count || document.chunks?.length || 0),
  }
}

function fallbackSuggestions() {
  return [
    {
      label: 'Tóm tắt bài đang học',
      tone: 'blue',
      assistant_type: 'pdf',
      tool: 'ask',
      prompt: 'Tóm tắt 5 ý quan trọng nhất và cho tôi 3 câu tự kiểm.',
    },
    {
      label: 'Lập kế hoạch 7 ngày',
      tone: 'green',
      assistant_type: 'planner',
      tool: 'ask',
      prompt: 'Lập kế hoạch học 7 ngày theo tiến độ hiện tại.',
    },
    {
      label: 'Tạo quiz luyện tập',
      tone: 'purple',
      assistant_type: 'quiz',
      tool: 'quiz',
      prompt: 'Tạo quiz luyện tập từ học liệu đang chọn.',
    },
  ]
}

function labelForSource(type) {
  return sourceTypeText[type] || type || 'Nguồn'
}

function labelForRelevance(relevance) {
  return {
    selected: 'Đang chọn',
    ai_demo: 'AI demo',
    course: 'Khóa liên quan',
    workspace: 'Workspace',
  }[relevance] || 'Liên quan'
}

function shortText(value, length = 90) {
  const text = String(value || '')
  return text.length > length ? `${text.slice(0, length)}...` : text
}

function iconForTool(key) {
  return {
    tutor: Brain,
    planner: CalendarDays,
    quiz: ListChecks,
    flashcard: WandSparkles,
    assignment: ClipboardCheck,
    video: Video,
  }[key] || Sparkles
}

function questionTitle(item) {
  return item.stem || item.prompt || item.pairs?.[0]?.left || 'Câu hỏi luyện tập'
}

function formatDate(value) {
  if (!value) {
    return 'Mới cập nhật'
  }

  return new Intl.DateTimeFormat('vi-VN', { day: '2-digit', month: '2-digit' }).format(new Date(value))
}

onMounted(loadData)
</script>

<template>
  <EraLmsLayout :session-user="sessionUser" @logout="$emit('logout')">
    <template #breadcrumb>AI Study Companion</template>

    <div class="space-y-4 pb-20">
      <section class="overflow-hidden rounded-md border border-slate-200 bg-white shadow-sm">
        <div class="h-1.5 bg-[linear-gradient(90deg,#2563eb_0%,#22c55e_33%,#f59e0b_66%,#ef4444_100%)]"></div>
        <div class="grid gap-4 px-4 py-4 xl:grid-cols-[minmax(0,1fr)_560px] xl:items-start">
          <div class="flex min-w-0 items-start gap-3">
            <div class="grid h-12 w-12 shrink-0 place-items-center rounded-md border border-blue-100 bg-blue-50">
              <Bot class="h-6 w-6 text-blue-700" />
            </div>
            <div class="min-w-0">
              <div class="flex flex-wrap items-center gap-2">
                <h1 class="text-xl font-bold text-slate-950 sm:text-2xl">{{ assistant.name || 'Era AI Study Companion' }}</h1>
                <span class="rounded-full border border-emerald-200 bg-emerald-50 px-2 py-1 text-xs font-semibold text-emerald-700">For you</span>
                <span class="rounded-full border border-amber-200 bg-amber-50 px-2 py-1 text-xs font-semibold text-amber-700">Gen Z / Alpha</span>
              </div>
              <p class="mt-1 text-sm text-slate-600">{{ learner.name || 'Sinh viên Demo' }} / {{ learner.program || 'Chương trình học cá nhân' }}</p>
              <div class="mt-3 grid gap-2 sm:grid-cols-[minmax(0,1fr)_minmax(0,1fr)]">
                <label class="block">
                  <span class="text-[11px] font-bold uppercase text-slate-500">Khóa học liên quan</span>
                  <select
                    v-model.number="selectedCourseId"
                    class="mt-1 h-10 w-full rounded-md border border-slate-300 bg-slate-50 px-3 text-sm font-semibold text-slate-800 outline-none focus:border-blue-500 focus:bg-white focus:ring-4 focus:ring-blue-100"
                    @change="changeCourse"
                  >
                    <option :value="null">Tự chọn theo người học</option>
                    <option v-for="course in courses" :key="course.id" :value="course.id">
                      {{ course.title }}
                    </option>
                  </select>
                </label>
                <div class="rounded-md border border-slate-200 bg-slate-50 px-3 py-2">
                  <div class="flex items-center justify-between gap-3 text-[11px] font-bold uppercase text-slate-500">
                    <span>{{ selectedCourse?.code || 'Context' }}</span>
                    <span>{{ selectedCourseProgress }}%</span>
                  </div>
                  <div class="mt-2 h-2 overflow-hidden rounded-full bg-white">
                    <div class="h-full rounded-full bg-emerald-500" :style="{ width: `${Math.min(100, selectedCourseProgress)}%` }"></div>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <div class="grid grid-cols-2 gap-2">
            <div class="rounded-md border border-blue-100 bg-blue-50 px-3 py-3">
              <div class="text-xs font-medium text-blue-700">Nguồn đúng scope</div>
              <div class="mt-1 text-xl font-bold text-blue-950">{{ sourceCountLabel }}</div>
            </div>
            <div class="rounded-md border border-emerald-100 bg-emerald-50 px-3 py-3">
              <div class="text-xs font-medium text-emerald-700">Chunk liên quan</div>
              <div class="mt-1 text-xl font-bold text-emerald-950">{{ knowledge.scope_chunks || knowledge.chunks_ready || 0 }}</div>
            </div>
            <div class="rounded-md border border-amber-100 bg-amber-50 px-3 py-3">
              <div class="text-xs font-medium text-amber-700">Quiz liên quan</div>
              <div class="mt-1 text-xl font-bold text-amber-950">{{ knowledge.related_quizzes || 0 }}</div>
            </div>
            <div class="rounded-md border border-rose-100 bg-rose-50 px-3 py-3">
              <div class="text-xs font-medium text-rose-700">Cards liên quan</div>
              <div class="mt-1 text-xl font-bold text-rose-950">{{ knowledge.related_flashcards || 0 }}</div>
            </div>
            <div class="col-span-2 rounded-md border border-slate-200 bg-slate-50 px-3 py-2 text-xs leading-5 text-slate-600">
              <span class="font-bold text-slate-900">Nguồn đang dùng:</span>
              {{ selectedDocument?.title || 'Chưa chọn' }}
              <span v-if="selectedDocument?.course_title"> / {{ selectedDocument.course_title }}</span>
            </div>
          </div>
        </div>

        <div class="border-t border-slate-200 px-4 py-3">
          <div class="flex gap-2 overflow-x-auto pb-1">
            <button
              v-for="suggestion in promptChips"
              :key="suggestion.label"
              type="button"
              class="inline-flex shrink-0 items-center gap-2 rounded-full border px-3 py-2 text-sm font-semibold transition disabled:opacity-60"
              :class="toneClass[suggestion.tone] || toneClass.blue"
              :disabled="loading || !!busyAction"
              @click="runSuggestion(suggestion)"
            >
              <Sparkles class="h-4 w-4" />
              {{ suggestion.label }}
            </button>
          </div>
        </div>
      </section>

      <div v-if="statusMessage" class="rounded-md border border-blue-200 bg-blue-50 px-4 py-3 text-sm font-medium text-blue-800">
        {{ statusMessage }}
      </div>

      <div v-if="booting" class="grid min-h-[420px] place-items-center rounded-md border border-slate-200 bg-white text-slate-600">
        <div class="inline-flex items-center gap-2 text-sm font-semibold">
          <Loader2 class="h-4 w-4 animate-spin" />
          Đang tải AI workspace
        </div>
      </div>

      <div v-else class="grid gap-4 xl:grid-cols-[minmax(0,1fr)_380px]">
        <main class="space-y-4">
          <section id="assistant" class="rounded-md border border-slate-200 bg-white shadow-sm">
            <div class="grid gap-3 border-b border-slate-200 px-4 py-3 lg:grid-cols-[1fr_280px] lg:items-center">
              <div>
                <div class="flex items-center gap-2">
                  <MessageSquare class="h-4 w-4 text-blue-600" />
                  <h2 class="text-sm font-bold text-slate-950">Ask with sources</h2>
                </div>
                <p class="mt-1 text-xs text-slate-500">{{ selectedDocument?.title || 'Chọn học liệu để bắt đầu' }}</p>
              </div>

              <div class="flex items-center gap-2">
                <Search class="h-4 w-4 text-slate-400" />
                <select
                  v-model.number="selectedDocumentId"
                  class="h-10 min-w-0 flex-1 rounded-md border border-slate-300 bg-white px-3 text-sm font-medium text-slate-800 outline-none focus:border-blue-500 focus:ring-4 focus:ring-blue-100"
                  :disabled="!documents.length"
                  @change="changeDocument"
                >
                  <option v-for="document in documents" :key="document.id" :value="document.id">
                    {{ document.title }}
                  </option>
                </select>
              </div>
            </div>

            <div class="grid gap-3 border-b border-slate-200 px-4 py-3 md:grid-cols-3 xl:grid-cols-6">
              <button
                v-for="mode in assistantModes"
                :key="mode.key"
                type="button"
                class="flex min-h-[74px] items-start gap-2 rounded-md border px-3 py-3 text-left transition"
                :class="activeMode === mode.key ? 'border-blue-500 bg-blue-50 text-blue-900 shadow-sm' : 'border-slate-200 bg-white text-slate-700 hover:border-slate-300 hover:bg-slate-50'"
                @click="activeMode = mode.key"
              >
                <component :is="mode.icon" class="mt-0.5 h-4 w-4 shrink-0" />
                <span class="min-w-0">
                  <span class="block truncate text-sm font-bold">{{ mode.label }}</span>
                  <span class="mt-1 block text-xs" :class="activeMode === mode.key ? 'text-blue-700' : 'text-slate-500'">{{ mode.hint }}</span>
                </span>
              </button>
            </div>

            <div class="max-h-[560px] space-y-3 overflow-auto px-4 py-4">
              <div
                v-for="(message, index) in messages"
                :key="`${message.role}-${index}`"
                class="flex"
                :class="message.role === 'user' ? 'justify-end' : 'justify-start'"
              >
                <div
                  class="max-w-[92%] rounded-md border px-4 py-3 text-sm leading-6 md:max-w-[78%]"
                  :class="message.role === 'user'
                    ? 'border-blue-600 bg-blue-600 text-white'
                    : 'border-slate-200 bg-slate-50 text-slate-800'"
                >
                  <div class="whitespace-pre-wrap">{{ message.text }}</div>
                  <div v-if="message.citations?.length" class="mt-3 flex flex-wrap gap-2">
                    <span
                      v-for="citation in message.citations"
                      :key="`${citation.document_id}-${citation.chunk_id}`"
                      class="inline-flex items-center gap-1 rounded-full border border-slate-200 bg-white px-2 py-1 text-xs font-semibold text-slate-600"
                    >
                      <Link2 class="h-3 w-3" />
                      {{ citation.title || `Chunk ${citation.chunk_id}` }}
                    </span>
                  </div>
                </div>
              </div>
            </div>

            <div class="border-t border-slate-200 p-4">
              <div class="rounded-md border border-slate-300 bg-white p-2 focus-within:border-blue-500 focus-within:ring-4 focus-within:ring-blue-100">
                <textarea
                  v-model="question"
                  rows="2"
                  class="block w-full resize-none border-0 px-2 py-2 text-sm text-slate-900 outline-none placeholder:text-slate-400"
                  :placeholder="`${activeModeMeta.label}: nhập câu hỏi học tập...`"
                  @keydown.enter.exact.prevent="ask()"
                />
                <div class="flex items-center justify-between gap-3 border-t border-slate-100 px-2 pt-2">
                  <div class="inline-flex items-center gap-2 text-xs font-medium text-slate-500">
                    <component :is="activeModeMeta.icon" class="h-4 w-4" />
                    {{ activeModeMeta.hint }}
                  </div>
                  <button
                    type="button"
                    class="inline-flex items-center gap-2 rounded-md bg-slate-950 px-4 py-2 text-sm font-semibold text-white transition hover:bg-slate-800 disabled:opacity-60"
                    :disabled="loading || !question.trim()"
                    @click="ask()"
                  >
                    <Loader2 v-if="busyAction === 'ask'" class="h-4 w-4 animate-spin" />
                    <Send v-else class="h-4 w-4" />
                    Gửi
                  </button>
                </div>
              </div>
            </div>
          </section>

          <section id="generation" class="grid gap-4 lg:grid-cols-3">
            <article class="rounded-md border border-slate-200 bg-white p-4 shadow-sm">
              <div class="flex items-center justify-between gap-3">
                <div class="flex items-center gap-2">
                  <FileText class="h-4 w-4 text-blue-600" />
                  <h2 class="text-sm font-bold text-slate-950">Summary</h2>
                </div>
                <button type="button" class="rounded-md border border-slate-300 px-3 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50" :disabled="busyAction === 'summary'" @click="generateSummary">
                  <Loader2 v-if="busyAction === 'summary'" class="h-4 w-4 animate-spin" />
                  <span v-else>Tạo</span>
                </button>
              </div>
              <div v-if="summary" class="mt-4 space-y-3 text-sm text-slate-700">
                <p class="leading-6">{{ summary.summary }}</p>
                <div class="space-y-2">
                  <div v-for="point in summary.key_points" :key="point" class="rounded-md bg-slate-50 px-3 py-2">{{ point }}</div>
                </div>
                <pre class="max-h-36 overflow-auto rounded-md bg-slate-950 p-3 text-xs text-slate-100">{{ mindmapJson }}</pre>
              </div>
              <div v-else class="mt-4 rounded-md border border-dashed border-slate-300 p-4 text-sm text-slate-500">
                Chưa có summary cho nguồn đang chọn.
              </div>
            </article>

            <article class="rounded-md border border-slate-200 bg-white p-4 shadow-sm">
              <div class="flex items-center justify-between gap-3">
                <div class="flex items-center gap-2">
                  <ListChecks class="h-4 w-4 text-amber-600" />
                  <h2 class="text-sm font-bold text-slate-950">Quiz</h2>
                </div>
                <button type="button" class="rounded-md border border-slate-300 px-3 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50" :disabled="busyAction === 'quiz'" @click="generateQuiz">
                  <Loader2 v-if="busyAction === 'quiz'" class="h-4 w-4 animate-spin" />
                  <span v-else>Tạo</span>
                </button>
              </div>
              <div v-if="quiz" class="mt-4 space-y-2 text-sm text-slate-700">
                <div class="font-semibold text-slate-950">{{ quiz.questions?.length || 0 }} câu luyện tập</div>
                <div v-for="(item, index) in quiz.questions || []" :key="index" class="rounded-md bg-amber-50 px-3 py-2">
                  <div class="text-xs font-bold uppercase text-amber-700">{{ item.type || 'mcq' }}</div>
                  <div class="mt-1 leading-5">{{ questionTitle(item) }}</div>
                </div>
              </div>
              <div v-else class="mt-4 rounded-md border border-dashed border-slate-300 p-4 text-sm text-slate-500">
                Tạo quiz từ PDF, slide hoặc transcript.
              </div>
            </article>

            <article class="rounded-md border border-slate-200 bg-white p-4 shadow-sm">
              <div class="flex items-center justify-between gap-3">
                <div class="flex items-center gap-2">
                  <WandSparkles class="h-4 w-4 text-emerald-600" />
                  <h2 class="text-sm font-bold text-slate-950">Flashcards</h2>
                </div>
                <button type="button" class="rounded-md border border-slate-300 px-3 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50" :disabled="busyAction === 'flashcards'" @click="generateFlashcards">
                  <Loader2 v-if="busyAction === 'flashcards'" class="h-4 w-4 animate-spin" />
                  <span v-else>Tạo</span>
                </button>
              </div>
              <div v-if="flashcards.length" class="mt-4 space-y-2 text-sm text-slate-700">
                <div v-for="card in flashcards" :key="card.id" class="rounded-md bg-emerald-50 px-3 py-2">
                  <div class="font-semibold text-emerald-950">{{ card.front }}</div>
                  <div class="mt-1 line-clamp-2 text-xs leading-5 text-emerald-800">{{ card.back }}</div>
                </div>
              </div>
              <div v-else class="mt-4 rounded-md border border-dashed border-slate-300 p-4 text-sm text-slate-500">
                Chưa có flashcard mới trong phiên này.
              </div>
            </article>
          </section>

          <section class="rounded-md border border-slate-200 bg-white shadow-sm">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 px-4 py-3">
              <div>
                <div class="flex items-center gap-2">
                  <Sparkles class="h-4 w-4 text-blue-600" />
                  <h2 class="text-sm font-bold text-slate-950">Dữ liệu liên quan</h2>
                </div>
                <p class="mt-1 text-xs text-slate-500">{{ context.selection_reason || 'Dữ liệu được lọc theo người học, khóa học và học liệu đang chọn.' }}</p>
              </div>
              <span class="rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-xs font-bold text-slate-600">
                Scope: {{ context.scope || 'learner' }}
              </span>
            </div>

            <div class="grid gap-4 p-4 lg:grid-cols-3">
              <div>
                <div class="mb-2 flex items-center justify-between gap-2">
                  <h3 class="text-xs font-bold uppercase text-slate-500">Hội thoại</h3>
                  <span class="text-xs font-semibold text-slate-400">{{ related.conversations?.length || 0 }}</span>
                </div>
                <div class="space-y-2">
                  <button
                    v-for="conversation in related.conversations || []"
                    :key="conversation.id"
                    type="button"
                    class="w-full rounded-md border border-slate-200 bg-slate-50 px-3 py-2 text-left transition hover:border-blue-200 hover:bg-blue-50"
                    @click="question = conversation.question"
                  >
                    <div class="text-sm font-bold text-slate-900">{{ shortText(conversation.question, 70) }}</div>
                    <div class="mt-1 text-xs leading-5 text-slate-500">{{ shortText(conversation.answer_preview, 110) }}</div>
                  </button>
                  <div v-if="!related.conversations?.length" class="rounded-md border border-dashed border-slate-300 p-3 text-sm text-slate-500">
                    Chưa có hội thoại liên quan.
                  </div>
                </div>
              </div>

              <div>
                <div class="mb-2 flex items-center justify-between gap-2">
                  <h3 class="text-xs font-bold uppercase text-slate-500">Quiz</h3>
                  <span class="text-xs font-semibold text-slate-400">{{ related.quizzes?.length || 0 }}</span>
                </div>
                <div class="space-y-2">
                  <div
                    v-for="item in related.quizzes || []"
                    :key="item.id"
                    class="rounded-md border border-amber-100 bg-amber-50 px-3 py-2"
                  >
                    <div class="text-sm font-bold text-amber-950">{{ shortText(item.title, 72) }}</div>
                    <div class="mt-1 text-xs font-semibold text-amber-700">{{ item.questions_count }} câu / {{ item.quiz_type }}</div>
                  </div>
                  <div v-if="!related.quizzes?.length" class="rounded-md border border-dashed border-slate-300 p-3 text-sm text-slate-500">
                    Chưa có quiz liên quan.
                  </div>
                </div>
              </div>

              <div>
                <div class="mb-2 flex items-center justify-between gap-2">
                  <h3 class="text-xs font-bold uppercase text-slate-500">Flashcards</h3>
                  <span class="text-xs font-semibold text-slate-400">{{ related.flashcards?.length || 0 }}</span>
                </div>
                <div class="space-y-2">
                  <div
                    v-for="card in related.flashcards || []"
                    :key="card.id"
                    class="rounded-md border border-emerald-100 bg-emerald-50 px-3 py-2"
                  >
                    <div class="text-sm font-bold text-emerald-950">{{ shortText(card.front, 72) }}</div>
                    <div class="mt-1 text-xs leading-5 text-emerald-800">{{ shortText(card.back, 110) }}</div>
                  </div>
                  <div v-if="!related.flashcards?.length" class="rounded-md border border-dashed border-slate-300 p-3 text-sm text-slate-500">
                    Chưa có flashcard liên quan.
                  </div>
                </div>
              </div>
            </div>
          </section>

          <section id="pipeline" class="rounded-md border border-slate-200 bg-white shadow-sm">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 px-4 py-3">
              <div class="flex items-center gap-2">
                <UploadCloud class="h-4 w-4 text-blue-600" />
                <h2 class="text-sm font-bold text-slate-950">Knowledge ingest</h2>
              </div>
              <button
                type="button"
                class="inline-flex items-center gap-2 rounded-md bg-blue-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-blue-700 disabled:opacity-60"
                :disabled="busyAction === 'ingest'"
                @click="ingest"
              >
                <Loader2 v-if="busyAction === 'ingest'" class="h-4 w-4 animate-spin" />
                <UploadCloud v-else class="h-4 w-4" />
                Nạp học liệu
              </button>
            </div>
            <div class="grid gap-3 p-4 lg:grid-cols-[1fr_220px]">
              <input
                v-model="ingestForm.title"
                class="h-11 rounded-md border border-slate-300 bg-slate-50 px-3 text-sm font-medium outline-none focus:border-blue-500 focus:bg-white focus:ring-4 focus:ring-blue-100"
                placeholder="Tên học liệu"
              />
              <select
                v-model="ingestForm.source_type"
                class="h-11 rounded-md border border-slate-300 bg-white px-3 text-sm font-medium outline-none focus:border-blue-500 focus:ring-4 focus:ring-blue-100"
              >
                <option value="pdf">PDF</option>
                <option value="ppt">Slide</option>
                <option value="docx">DOCX</option>
                <option value="video_transcript">Transcript video</option>
                <option value="assignment">Assignment</option>
                <option value="repository">Repository</option>
              </select>
              <div class="lg:col-span-2">
                <RichTextEditor
                  v-model="ingestForm.content"
                  :api-headers="apiHeaders"
                  min-height="220px"
                  placeholder="Dán nội dung PDF, slide, transcript, rubric hoặc ghi chú bài học..."
                />
              </div>
            </div>
          </section>
        </main>

        <aside class="space-y-4">
          <section id="insight" class="rounded-md border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 px-4 py-3">
              <div class="flex items-center gap-2">
                <Target class="h-4 w-4 text-emerald-600" />
                <h2 class="text-sm font-bold text-slate-950">Today plan</h2>
              </div>
              <p class="mt-1 text-xs font-medium text-slate-500">{{ studyPlan.headline || 'Sẵn sàng học hôm nay' }}</p>
            </div>
            <div class="divide-y divide-slate-100">
              <button
                v-for="item in studyPlan.today || []"
                :key="`${item.time}-${item.title}`"
                type="button"
                class="flex w-full items-center gap-3 px-4 py-3 text-left transition hover:bg-slate-50"
                :disabled="!!busyAction || loading"
                @click="runPlanItem(item)"
              >
                <span class="grid h-10 w-10 shrink-0 place-items-center rounded-md bg-emerald-50 text-xs font-bold text-emerald-700">{{ item.time }}</span>
                <span class="min-w-0 flex-1">
                  <span class="block text-sm font-bold text-slate-900">{{ item.title }}</span>
                  <span class="mt-1 block text-xs font-medium text-slate-500">{{ item.action }}</span>
                </span>
                <ChevronRight class="h-4 w-4 text-slate-400" />
              </button>
            </div>
            <div class="border-t border-slate-200 px-4 py-3 text-sm">
              <div class="flex items-center justify-between gap-3">
                <span class="text-slate-500">Target tuần</span>
                <span class="font-bold text-slate-950">{{ studyPlan.this_week?.target_progress || 0 }}%</span>
              </div>
              <div class="mt-2 rounded-md bg-slate-50 p-3 text-xs leading-5 text-slate-600">
                {{ studyPlan.this_week?.next_lesson || 'Tiếp tục bài học tiếp theo' }}
              </div>
            </div>
          </section>

          <section class="rounded-md border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 px-4 py-3">
              <div class="flex items-center gap-2">
                <BookOpen class="h-4 w-4 text-blue-600" />
                <h2 class="text-sm font-bold text-slate-950">Knowledge sources</h2>
              </div>
            </div>
            <div v-if="documents.length" class="max-h-[420px] divide-y divide-slate-100 overflow-auto">
              <button
                v-for="document in documents"
                :key="document.id"
                type="button"
                class="flex w-full gap-3 px-4 py-3 text-left transition hover:bg-slate-50"
                :class="document.id === selectedDocumentId ? 'bg-blue-50' : 'bg-white'"
                @click="selectDocument(document)"
              >
                <span class="grid h-10 w-10 shrink-0 place-items-center rounded-md border border-slate-200 bg-white">
                  <FileText v-if="document.source_type !== 'video_transcript'" class="h-4 w-4 text-slate-600" />
                  <PlayCircle v-else class="h-4 w-4 text-slate-600" />
                </span>
                <span class="min-w-0 flex-1">
                  <span class="block truncate text-sm font-bold text-slate-900">{{ document.title }}</span>
                  <span class="mt-1 flex flex-wrap items-center gap-2 text-xs font-medium text-slate-500">
                    <span>{{ labelForSource(document.source_type) }}</span>
                    <span>{{ document.chunks_count }} chunks</span>
                    <span class="rounded-full bg-white px-2 py-0.5 text-[11px] font-bold text-slate-600">{{ labelForRelevance(document.relevance) }}</span>
                    <span>{{ formatDate(document.updated_at) }}</span>
                  </span>
                </span>
              </button>
            </div>
            <div v-else class="p-4">
              <div class="rounded-md border border-dashed border-slate-300 p-4 text-sm text-slate-500">
                Chưa có học liệu. Nạp PDF, slide hoặc transcript để AI có nguồn trả lời.
              </div>
            </div>
          </section>

          <section class="rounded-md border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 px-4 py-3">
              <div class="flex items-center gap-2">
                <Sparkles class="h-4 w-4 text-amber-600" />
                <h2 class="text-sm font-bold text-slate-950">AI tools</h2>
              </div>
            </div>
            <div class="grid gap-2 p-4">
              <button
                v-for="tool in tools"
                :key="tool.key"
                type="button"
                class="flex items-start gap-3 rounded-md border border-slate-200 bg-white px-3 py-3 text-left transition hover:border-blue-200 hover:bg-blue-50"
                @click="activeMode = tool.assistant_type || tool.key"
              >
                <component :is="iconForTool(tool.key)" class="mt-0.5 h-4 w-4 shrink-0 text-slate-600" />
                <span class="min-w-0">
                  <span class="block text-sm font-bold text-slate-900">{{ tool.label }}</span>
                  <span class="mt-1 block text-xs leading-5 text-slate-500">{{ tool.description }}</span>
                </span>
              </button>
            </div>
          </section>

          <section class="rounded-md border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 px-4 py-3">
              <div class="flex items-center gap-2">
                <ShieldCheck class="h-4 w-4 text-emerald-600" />
                <h2 class="text-sm font-bold text-slate-950">Trust rules</h2>
              </div>
            </div>
            <div class="space-y-2 p-4">
              <div v-for="rule in safetyRules" :key="rule" class="flex gap-2 rounded-md bg-slate-50 px-3 py-2 text-sm leading-5 text-slate-700">
                <CheckCircle2 class="mt-0.5 h-4 w-4 shrink-0 text-emerald-600" />
                <span>{{ rule }}</span>
              </div>
            </div>
          </section>

          <section class="rounded-md border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 px-4 py-3">
              <div class="flex items-center gap-2">
                <Flame class="h-4 w-4 text-rose-600" />
                <h2 class="text-sm font-bold text-slate-950">Recent questions</h2>
              </div>
            </div>
            <div class="space-y-2 p-4">
              <button
                v-for="item in knowledge.top_questions || analytics?.top_questions || []"
                :key="item"
                type="button"
                class="w-full rounded-md border border-slate-200 bg-white px-3 py-2 text-left text-sm font-medium text-slate-700 transition hover:bg-slate-50"
                @click="question = item"
              >
                {{ item }}
              </button>
              <div v-if="!(knowledge.top_questions || analytics?.top_questions || []).length" class="rounded-md border border-dashed border-slate-300 p-4 text-sm text-slate-500">
                Chưa có lịch sử hỏi đáp.
              </div>
            </div>
          </section>
        </aside>
      </div>

      <div class="fixed inset-x-0 bottom-0 z-20 border-t border-slate-200 bg-white/95 px-3 py-3 shadow-lg backdrop-blur md:hidden">
        <button
          type="button"
          class="flex h-11 w-full items-center justify-center gap-2 rounded-md bg-slate-950 px-4 text-sm font-bold text-white disabled:opacity-60"
          :disabled="loading || !question.trim()"
          @click="ask()"
        >
          <Send class="h-4 w-4" />
          Hỏi AI theo nguồn
        </button>
      </div>
    </div>
  </EraLmsLayout>
</template>
