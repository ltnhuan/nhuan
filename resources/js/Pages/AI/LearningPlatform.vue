<script setup>
import { computed, defineAsyncComponent, onMounted, ref } from 'vue'
import EraLmsLayout from '@/Layouts/EraLmsLayout.vue'

const RichTextEditor = defineAsyncComponent(() => import('@/Components/Lms/RichTextEditor.vue'))

const props = defineProps({
  sessionUser: { type: Object, default: null },
  apiHeaders: { type: Object, default: () => ({}) },
})

defineEmits(['logout'])

const modes = [
  { key: 'tutor', label: 'Chat Course', hint: 'Hỏi đáp theo nội dung khóa học' },
  { key: 'pdf', label: 'Ask PDF', hint: 'Trích xuất và tóm tắt học liệu' },
  { key: 'video', label: 'Ask Video', hint: 'Hỏi theo transcript video' },
  { key: 'assignment', label: 'Ask Assignment', hint: 'Gợi ý bài tập và rubric' },
]

const activeMode = ref('tutor')
const documents = ref([])
const analytics = ref(null)
const selectedDocumentId = ref(null)
const question = ref('Bài này là gì? Giải thích lại bằng ví dụ thực tế.')
const answer = ref(null)
const summary = ref(null)
const quiz = ref(null)
const flashcards = ref([])
const coach = ref(null)
const outcomes = ref(null)
const loading = ref(false)
const ingestForm = ref({
  title: 'Học liệu AI mới',
  source_type: 'pdf',
  content: 'AI Tutor hỗ trợ người học hỏi đáp theo nội dung khóa học. AI Quiz Generator tạo MCQ, Essay, Fill Blank và Matching. AI Flashcard gồm front, back và difficulty. AI Learning Coach theo dõi tiến độ, điểm và thời gian học.',
})

const selectedDocument = computed(() => documents.value.find((item) => item.id === selectedDocumentId.value))
const mindmapJson = computed(() => summary.value ? JSON.stringify(summary.value.mindmap, null, 2) : '')

async function api(path, options = {}) {
  const response = await fetch(`/api/v1${path}`, {
    ...options,
    headers: {
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
  const [documentData, analyticsData, coachData, outcomeData] = await Promise.all([
    api('/ai/documents?per_page=12'),
    api('/ai/analytics'),
    api('/ai/coach'),
    api('/ai/outcomes'),
  ])
  documents.value = documentData.data || []
  selectedDocumentId.value ||= documents.value[0]?.id || null
  analytics.value = analyticsData
  coach.value = coachData
  outcomes.value = outcomeData
}

async function ingest() {
  loading.value = true
  try {
    const document = await api('/ai/ingest', {
      method: 'POST',
      body: JSON.stringify(ingestForm.value),
    })
    documents.value = [document, ...documents.value]
    selectedDocumentId.value = document.id
    await loadData()
  } finally {
    loading.value = false
  }
}

async function ask() {
  loading.value = true
  try {
    answer.value = await api('/ai/ask', {
      method: 'POST',
      body: JSON.stringify({
        document_id: selectedDocumentId.value,
        assistant_type: activeMode.value,
        question: question.value,
      }),
    })
    await loadData()
  } finally {
    loading.value = false
  }
}

async function generateSummary() {
  summary.value = await api('/ai/summary', {
    method: 'POST',
    body: JSON.stringify({ document_id: selectedDocumentId.value, title: selectedDocument.value?.title }),
  })
}

async function generateQuiz() {
  quiz.value = await api('/ai/quizzes', {
    method: 'POST',
    body: JSON.stringify({ document_id: selectedDocumentId.value, types: ['mcq', 'essay', 'fill_blank', 'matching'] }),
  })
  await loadData()
}

async function generateFlashcards() {
  flashcards.value = await api('/ai/flashcards', {
    method: 'POST',
    body: JSON.stringify({ document_id: selectedDocumentId.value, count: 4 }),
  })
  await loadData()
}

onMounted(loadData)
</script>

<template>
  <EraLmsLayout :session-user="sessionUser" @logout="$emit('logout')">
    <template #breadcrumb>AI Learning Platform</template>

    <div class="space-y-5">
      <section class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
        <div class="grid gap-6 bg-slate-950 px-6 py-6 text-white xl:grid-cols-[1fr_420px]">
          <div>
            <div class="text-xs font-semibold uppercase tracking-[0.18em] text-cyan-200">AI Learning Operations</div>
            <h1 class="mt-2 text-2xl font-bold">Trợ giảng AI, tạo học liệu và phân tích kết quả học tập</h1>
            <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-300">Quản lý pipeline tài liệu, vector store, hỏi đáp theo khóa học, tạo quiz, flashcard và gợi ý học tập từ cùng một màn hình.</p>
            <div class="mt-4 flex flex-wrap gap-2">
              <button class="rounded-md bg-cyan-500 px-4 py-2 text-sm font-semibold text-slate-950 hover:bg-cyan-400" :disabled="loading" @click="ask">Ask AI</button>
              <button class="rounded-md border border-white/20 px-4 py-2 text-sm font-semibold text-white hover:bg-white/10" @click="generateSummary">Generate summary</button>
              <button class="rounded-md border border-white/20 px-4 py-2 text-sm font-semibold text-white hover:bg-white/10" @click="generateQuiz">Create quiz</button>
            </div>
          </div>
          <div class="grid grid-cols-3 gap-3">
            <div class="rounded-md bg-white/10 p-4">
              <div class="text-xs text-slate-300">AI Usage</div>
              <div class="mt-2 text-3xl font-bold">{{ analytics?.ai_usage || 0 }}</div>
            </div>
            <div class="rounded-md bg-white/10 p-4">
              <div class="text-xs text-slate-300">Documents</div>
              <div class="mt-2 text-3xl font-bold">{{ analytics?.learning_impact?.documents_ready || 0 }}</div>
            </div>
            <div class="rounded-md bg-emerald-500/20 p-4">
              <div class="text-xs text-emerald-100">Assets</div>
              <div class="mt-2 text-3xl font-bold">{{ (analytics?.learning_impact?.quizzes_generated || 0) + (analytics?.learning_impact?.flashcards_generated || 0) }}</div>
            </div>
          </div>
        </div>
      </section>

      <div class="grid min-h-[calc(100vh-14rem)] grid-cols-1 gap-5 xl:grid-cols-[1fr_380px]">
      <section class="space-y-4">
        <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
          <section class="rounded-lg border border-slate-200 bg-white shadow-sm">
            <div class="flex items-center justify-between gap-3 border-b border-slate-200 px-5 py-4">
              <div>
                <h2 class="text-sm font-bold text-slate-900">Document Pipeline</h2>
                <p class="mt-1 text-xs text-slate-500">Nạp PDF, slide, transcript và chia chunk cho AI</p>
              </div>
              <button class="rounded-md bg-slate-900 px-4 py-2 text-sm font-semibold text-white disabled:opacity-50" :disabled="loading" @click="ingest">Ingest</button>
            </div>
            <div class="grid gap-3 p-5">
              <input v-model="ingestForm.title" class="h-11 rounded-md border border-slate-300 bg-slate-50 px-3 text-sm outline-none focus:border-blue-500 focus:bg-white focus:ring-4 focus:ring-blue-100" />
              <select v-model="ingestForm.source_type" class="h-11 rounded-md border border-slate-300 bg-white px-3 text-sm outline-none focus:border-blue-500 focus:ring-4 focus:ring-blue-100">
                <option value="pdf">PDF</option>
                <option value="ppt">PPT</option>
                <option value="docx">DOCX</option>
                <option value="video_transcript">Video Transcript</option>
              </select>
              <RichTextEditor v-model="ingestForm.content" :api-headers="apiHeaders" min-height="220px" placeholder="Nội dung học liệu AI, có thể chèn ảnh, audio, video..." />
            </div>
          </section>

          <section class="rounded-lg border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 px-5 py-4">
              <h2 class="text-sm font-bold text-slate-900">Vector Store</h2>
              <p class="mt-1 text-xs text-slate-500">Chọn nguồn tri thức để hỏi đáp và sinh tài nguyên</p>
            </div>
            <div class="max-h-80 divide-y divide-slate-100 overflow-auto p-3">
              <button
                v-for="document in documents"
                :key="document.id"
                class="mb-2 flex w-full items-center justify-between gap-3 rounded-md border px-3 py-3 text-left text-sm transition hover:border-blue-200 hover:bg-blue-50"
                :class="document.id === selectedDocumentId ? 'border-blue-300 bg-blue-50 shadow-sm' : 'border-slate-200 bg-white'"
                @click="selectedDocumentId = document.id"
              >
                <span class="min-w-0">
                  <span class="block truncate font-semibold text-slate-900">{{ document.title }}</span>
                  <span class="mt-1 block text-xs text-slate-500">{{ document.source_type }} · {{ document.chunks_count || document.chunks?.length || 0 }} chunks</span>
                </span>
                <span class="shrink-0 rounded-full bg-slate-100 px-2 py-1 text-xs font-semibold text-slate-600">Ready</span>
              </button>
            </div>
          </section>
        </div>

        <section class="rounded-lg border border-slate-200 bg-white shadow-sm">
          <div class="border-b border-slate-200 px-5 py-4">
            <h2 class="text-sm font-bold text-slate-900">AI Assistant Workspace</h2>
            <p class="mt-1 text-xs text-slate-500">Chọn mode, nhập câu hỏi và xem phản hồi theo học liệu đã chọn</p>
          </div>
          <div class="grid gap-3 p-5 md:grid-cols-4">
            <button
              v-for="mode in modes"
              :key="mode.key"
              class="rounded-md border p-3 text-left"
              :class="activeMode === mode.key ? 'border-blue-600 bg-blue-600 text-white shadow-sm' : 'border-slate-200 bg-white text-slate-700 hover:bg-slate-50'"
              @click="activeMode = mode.key"
            >
              <span class="block text-sm font-bold">{{ mode.label }}</span>
              <span class="mt-1 block text-xs" :class="activeMode === mode.key ? 'text-blue-100' : 'text-slate-500'">{{ mode.hint }}</span>
            </button>
          </div>
          <div class="border-t border-slate-200 p-5">
            <div class="flex gap-2">
              <input v-model="question" class="h-11 min-w-0 flex-1 rounded-md border border-slate-300 bg-slate-50 px-3 text-sm outline-none focus:border-blue-500 focus:bg-white focus:ring-4 focus:ring-blue-100" />
              <button class="rounded-md bg-blue-700 px-5 py-2 text-sm font-semibold text-white disabled:opacity-50" :disabled="loading" @click="ask">Ask</button>
            </div>
            <pre v-if="answer" class="mt-4 max-h-72 overflow-auto whitespace-pre-wrap rounded-md border border-slate-200 bg-slate-50 p-4 text-sm leading-6 text-slate-800">{{ answer.answer }}</pre>
          </div>
        </section>

        <section class="grid grid-cols-1 gap-4 lg:grid-cols-3">
          <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
            <button class="w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm font-semibold hover:bg-slate-50" @click="generateSummary">AI Summary</button>
            <div v-if="summary" class="mt-3 space-y-3 text-sm text-slate-700">
              <p>{{ summary.summary }}</p>
              <ul class="space-y-1">
                <li v-for="point in summary.key_points" :key="point" class="rounded-md bg-slate-50 px-3 py-2">{{ point }}</li>
              </ul>
              <pre class="max-h-40 overflow-auto rounded-md bg-slate-100 p-2 text-xs">{{ mindmapJson }}</pre>
            </div>
          </div>
          <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
            <button class="w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm font-semibold hover:bg-slate-50" @click="generateQuiz">Quiz Generator</button>
            <div v-if="quiz" class="mt-3 space-y-2 text-sm text-slate-700">
              <div class="font-medium">{{ quiz.questions.length }} questions generated</div>
              <div v-for="(item, index) in quiz.questions" :key="index" class="rounded-md bg-slate-50 px-2 py-2">
                <span class="text-xs uppercase text-slate-500">{{ item.type }}</span>
                <div>{{ item.stem || item.prompt || item.pairs?.[0]?.left }}</div>
              </div>
            </div>
          </div>
          <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
            <button class="w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm font-semibold hover:bg-slate-50" @click="generateFlashcards">Flashcards</button>
            <div v-if="flashcards.length" class="mt-3 space-y-2 text-sm text-slate-700">
              <div v-for="card in flashcards" :key="card.id" class="rounded-md bg-slate-50 px-2 py-2">
                <div class="font-medium">{{ card.front }}</div>
                <div class="mt-1 text-xs text-slate-500">{{ card.difficulty }}</div>
              </div>
            </div>
          </div>
        </section>

        <section class="grid grid-cols-1 gap-4 lg:grid-cols-2">
          <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="text-sm font-bold text-slate-900">AI Learning Coach</h2>
            <div class="mt-3 grid grid-cols-3 gap-2 text-sm">
              <div class="rounded-md bg-slate-50 p-3">
                <div class="text-xs text-slate-500">Tiến độ</div>
                <div class="mt-1 font-semibold">{{ coach?.progress_percent || 0 }}%</div>
              </div>
              <div class="rounded-md bg-slate-50 p-3">
                <div class="text-xs text-slate-500">Điểm</div>
                <div class="mt-1 font-semibold">{{ coach?.score_snapshot || 'N/A' }}</div>
              </div>
              <div class="rounded-md bg-slate-50 p-3">
                <div class="text-xs text-slate-500">Phút học</div>
                <div class="mt-1 font-semibold">{{ coach?.study_time_minutes || 0 }}</div>
              </div>
            </div>
            <div class="mt-3 space-y-2 text-sm text-slate-700">
              <div class="rounded-md border px-3 py-2">{{ coach?.next_lesson }}</div>
              <div class="rounded-md border px-3 py-2">{{ coach?.review_recommendation }}</div>
            </div>
          </div>

          <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="text-sm font-bold text-slate-900">AI Outcome Analyzer</h2>
            <div class="mt-3 grid grid-cols-3 gap-2 text-sm">
              <div class="rounded-md bg-slate-50 p-3">
                <div class="text-xs text-slate-500">CLO</div>
                <div class="mt-1 font-semibold">{{ outcomes?.clo?.length || 0 }}</div>
              </div>
              <div class="rounded-md bg-slate-50 p-3">
                <div class="text-xs text-slate-500">PLO</div>
                <div class="mt-1 font-semibold">{{ outcomes?.plo?.length || 0 }}</div>
              </div>
              <div class="rounded-md bg-slate-50 p-3">
                <div class="text-xs text-slate-500">Competency</div>
                <div class="mt-1 font-semibold">{{ outcomes?.competency?.length || 0 }}</div>
              </div>
            </div>
            <div class="mt-3 rounded-md border px-3 py-2 text-sm text-slate-700">{{ outcomes?.coverage_status }}</div>
          </div>
        </section>
      </section>

      <aside class="rounded-lg border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-200 px-5 py-4">
          <h2 class="text-sm font-bold text-slate-900">Right Side AI Assistant</h2>
          <p class="mt-1 text-xs text-slate-500">Prompt nhanh và câu hỏi thường dùng</p>
        </div>
        <div class="m-5 rounded-md bg-slate-50 p-4 text-sm text-slate-700">
          <div class="font-medium">{{ selectedDocument?.title || 'Chưa chọn học liệu' }}</div>
          <div class="mt-1 text-xs text-slate-500">Course content · Repository · Slides · Transcript</div>
        </div>
        <div class="space-y-2 px-5">
          <button class="w-full rounded-md border border-slate-200 bg-white px-3 py-3 text-left text-sm font-medium hover:bg-blue-50 hover:text-blue-700" @click="question = 'Bài này là gì?'">Bài này là gì?</button>
          <button class="w-full rounded-md border border-slate-200 bg-white px-3 py-3 text-left text-sm font-medium hover:bg-blue-50 hover:text-blue-700" @click="question = 'Giải thích lại dễ hiểu hơn.'">Giải thích lại</button>
          <button class="w-full rounded-md border border-slate-200 bg-white px-3 py-3 text-left text-sm font-medium hover:bg-blue-50 hover:text-blue-700" @click="question = 'Cho ví dụ thực tế.'">Ví dụ thực tế</button>
          <button class="w-full rounded-md border border-slate-200 bg-white px-3 py-3 text-left text-sm font-medium hover:bg-blue-50 hover:text-blue-700" @click="question = 'Tạo kế hoạch ôn tập.'">Ôn tập</button>
        </div>
        <div class="p-5">
          <div class="text-xs font-medium uppercase text-slate-500">Top Questions</div>
          <ul class="mt-2 space-y-2 text-sm text-slate-700">
            <li v-for="item in analytics?.top_questions || []" :key="item" class="rounded-md bg-slate-50 px-3 py-2">{{ item }}</li>
          </ul>
        </div>
      </aside>
      </div>
    </div>
  </EraLmsLayout>
</template>
