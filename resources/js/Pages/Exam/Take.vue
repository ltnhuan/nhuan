<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { AlertTriangle, CheckCircle2, Clock3, Flag, Send } from '@lucide/vue'
import EraLmsLayout from '@/Layouts/EraLmsLayout.vue'

const props = defineProps({
  sessionUser: { type: Object, default: null },
  apiHeaders: { type: Object, default: () => ({}) },
})

defineEmits(['logout'])

const loading = ref(false)
const starting = ref(false)
const submitting = ref(false)
const error = ref('')
const exam = ref(null)
const attempt = ref(null)
const questions = ref([])
const currentIndex = ref(0)
const answers = ref({})
const textAnswers = ref({})
const secondsLeft = ref(0)
let timer = null

const currentQuestion = computed(() => questions.value[currentIndex.value] || null)
const answeredCount = computed(() => questions.value.filter((question) => question.is_answered || answers.value[question.id]).length)
const markedCount = computed(() => questions.value.filter((question) => question.is_marked_review).length)
const displayTime = computed(() => {
  const minutes = Math.max(0, Math.floor(secondsLeft.value / 60))
  const seconds = Math.max(0, secondsLeft.value % 60)
  return `${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`
})

function authHeaders(extra = {}) {
  return { 'Content-Type': 'application/json', ...props.apiHeaders, ...extra }
}

async function api(url, options = {}) {
  const response = await fetch(url, {
    ...options,
    headers: authHeaders(options.headers || {}),
  })
  const payload = await response.json().catch(() => ({}))
  if (!response.ok) throw new Error(payload.message || 'Không thực hiện được thao tác thi.')
  return payload.data || payload
}

async function resolveExamId(params) {
  const directExamId = Number(params.get('exam_id'))
  if (directExamId) return directExamId

  const courseId = Number(params.get('course_id'))
  const componentId = Number(params.get('component_id'))
  if (!courseId || !componentId) return null

  const studioPayload = await api(`/api/v1/courses/${courseId}/studio`, { method: 'GET', headers: props.apiHeaders })
  const component = collectComponents(studioPayload.outline || []).find((item) => Number(item.id) === componentId)
  const resolvedExamId = component?.config?.exam_id || null
  if (resolvedExamId) {
    params.set('exam_id', resolvedExamId)
    window.history.replaceState({}, '', `/exams/take?${params.toString()}`)
  }
  return resolvedExamId
}

function collectComponents(nodes) {
  return (nodes || []).flatMap((node) => [
    ...(node.components || []),
    ...collectComponents(node.children || []),
  ])
}

async function boot() {
  loading.value = true
  error.value = ''
  try {
    const params = new URLSearchParams(window.location.search)
    const examId = await resolveExamId(params)
    if (!examId) {
      error.value = 'Không tìm thấy đề thi gắn với quiz này. Vui lòng mở lại từ nút “Làm bài thi” hoặc vào Studio chọn “Load đề trắc nghiệm”.'
      return
    }

    exam.value = await api(`/api/v1/exams/${examId}`, { method: 'GET', headers: props.apiHeaders })
    await startAttempt(examId)
  } catch (err) {
    error.value = err.message || 'Không mở được bài thi.'
  } finally {
    loading.value = false
  }
}

async function startAttempt(examId) {
  starting.value = true
  try {
    attempt.value = await api(`/api/v1/exams/${examId}/attempts/start`, { method: 'POST', body: JSON.stringify({ source: 'course_learn' }) })
    await loadAttemptState()
    startTimer()
  } finally {
    starting.value = false
  }
}

async function loadAttemptState() {
  if (!attempt.value?.id) return
  const state = await api(`/api/v1/exam-attempts/${attempt.value.id}`, { method: 'GET', headers: props.apiHeaders })
  attempt.value = state.attempt
  questions.value = state.questions || []
  answers.value = Object.fromEntries(questions.value.filter((question) => question.is_answered).map((question) => [question.id, true]))
  secondsLeft.value = remainingSeconds()
}

function remainingSeconds() {
  const duration = Number(exam.value?.duration_minutes || attempt.value?.exam?.duration_minutes || 45) * 60
  const startedAt = attempt.value?.started_at ? new Date(attempt.value.started_at).getTime() : Date.now()
  const elapsed = Math.floor((Date.now() - startedAt) / 1000)
  return Math.max(0, duration - elapsed)
}

function startTimer() {
  clearInterval(timer)
  timer = setInterval(() => {
    secondsLeft.value = Math.max(0, secondsLeft.value - 1)
    if (secondsLeft.value === 0) submitAttempt()
  }, 1000)
}

async function chooseAnswer(option) {
  if (!currentQuestion.value || !attempt.value?.id) return
  const question = currentQuestion.value
  answers.value = { ...answers.value, [question.id]: option.option_key }
  await api(`/api/v1/exam-attempts/${attempt.value.id}/autosave`, {
    method: 'POST',
    body: JSON.stringify({
      attempt_question_id: question.id,
      answer_data: { option_key: option.option_key, selected: option.option_key },
    }),
  })
  question.is_answered = true
}

async function saveTextAnswer() {
  if (!currentQuestion.value || !attempt.value?.id) return
  const question = currentQuestion.value
  const value = textAnswers.value[question.id] || ''
  answers.value = { ...answers.value, [question.id]: value || true }
  await api(`/api/v1/exam-attempts/${attempt.value.id}/autosave`, {
    method: 'POST',
    body: JSON.stringify({
      attempt_question_id: question.id,
      answer_data: { text: value },
    }),
  })
  question.is_answered = Boolean(value.trim())
}

async function toggleReview() {
  if (!currentQuestion.value || !attempt.value?.id) return
  const next = !currentQuestion.value.is_marked_review
  currentQuestion.value.is_marked_review = next
  await api(`/api/v1/exam-attempts/${attempt.value.id}/mark-review`, {
    method: 'POST',
    body: JSON.stringify({ attempt_question_id: currentQuestion.value.id, marked: next }),
  })
}

async function submitAttempt() {
  if (!attempt.value?.id || submitting.value) return
  submitting.value = true
  try {
    await api(`/api/v1/exam-attempts/${attempt.value.id}/submit`, { method: 'POST' })
    clearInterval(timer)
    window.location.href = `/exams/results?exam_id=${exam.value?.id || attempt.value.exam_id}`
  } catch (err) {
    error.value = err.message || 'Không nộp được bài thi.'
  } finally {
    submitting.value = false
  }
}

function goQuestion(index) {
  currentIndex.value = Math.max(0, Math.min(index, questions.value.length - 1))
}

function mediaKind(url = '') {
  const value = String(url).toLowerCase()
  if (/\.(png|jpg|jpeg|webp|gif|svg)(\?|$)/.test(value)) return 'image'
  if (/\.(mp3|wav|ogg)(\?|$)/.test(value)) return 'audio'
  if (/\.(mp4|webm|ogg)(\?|$)/.test(value)) return 'video'
  return ''
}

function questionMediaUrl(question) {
  return question?.question?.metadata?.media_url || question?.question?.media_url || ''
}

function questionMediaKind(question) {
  const explicit = question?.question?.metadata?.media_type || ''
  return explicit || mediaKind(questionMediaUrl(question))
}

function optionMediaUrl(option) {
  return option?.media_url || option?.metadata?.media_url || ''
}

function questionMedia(question) {
  return question?.question?.metadata?.media_url || question?.question?.metadata?.image_url || question?.question?.metadata?.audio_url || question?.question?.metadata?.video_url || ''
}

function recordExamEvent(name) {
  if (!attempt.value?.id) return
  fetch(`/api/v1/exam-attempts/${attempt.value.id}/events`, {
    method: 'POST',
    headers: authHeaders(),
    body: JSON.stringify({ event_type: name, metadata: { source: 'take_page' } }),
  }).catch(() => {})
}

onMounted(() => {
  boot()
  document.addEventListener('visibilitychange', () => recordExamEvent(document.hidden ? 'tab_hidden' : 'tab_visible'))
  document.addEventListener('copy', () => recordExamEvent('copy_attempt'))
  document.addEventListener('paste', () => recordExamEvent('paste_attempt'))
})

onBeforeUnmount(() => clearInterval(timer))
</script>

<template>
  <EraLmsLayout :session-user="sessionUser" @logout="$emit('logout')">
    <template #breadcrumb>Đang làm bài thi</template>

    <section class="min-h-[calc(100vh-7rem)] bg-slate-100">
      <div v-if="loading || starting" class="rounded-md border border-slate-200 bg-white p-6 text-sm text-slate-500">Đang mở phòng thi...</div>
      <div v-else-if="error" class="rounded-md border border-red-200 bg-red-50 p-6 text-sm font-semibold text-red-700">
        <div class="flex items-start gap-2">
          <AlertTriangle class="mt-0.5 h-4 w-4 shrink-0" />
          <span>{{ error }}</span>
        </div>
      </div>

      <div v-else class="grid gap-4 lg:grid-cols-[280px_minmax(0,1fr)_280px]">
        <aside class="rounded-md border border-slate-200 bg-white p-4">
          <h2 class="text-sm font-bold text-slate-950">Danh sách câu hỏi</h2>
          <div class="mt-3 grid grid-cols-5 gap-2">
            <button
              v-for="(question, index) in questions"
              :key="question.id"
              class="h-10 rounded-md border text-sm font-bold"
              :class="[
                index === currentIndex ? 'border-blue-600 bg-blue-600 text-white' : '',
                index !== currentIndex && question.is_marked_review ? 'border-amber-200 bg-amber-50 text-amber-800' : '',
                index !== currentIndex && !question.is_marked_review && (question.is_answered || answers[question.id]) ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : '',
                index !== currentIndex && !question.is_marked_review && !(question.is_answered || answers[question.id]) ? 'border-slate-200 bg-white text-slate-600' : '',
              ]"
              @click="goQuestion(index)"
            >
              {{ question.display_order }}
            </button>
          </div>
          <div class="mt-4 space-y-2 text-xs text-slate-600">
            <div>Đã trả lời: {{ answeredCount }}</div>
            <div>Chưa trả lời: {{ questions.length - answeredCount }}</div>
            <div>Đánh dấu xem lại: {{ markedCount }}</div>
          </div>
        </aside>

        <main class="rounded-md border border-slate-200 bg-white p-5">
          <div class="flex flex-wrap items-start justify-between gap-3 border-b border-slate-200 pb-4">
            <div>
              <div class="text-xs font-bold uppercase text-blue-700">{{ exam?.title || 'Bài thi' }}</div>
              <h1 class="mt-1 text-xl font-bold text-slate-950">Câu {{ currentQuestion?.display_order || 1 }}</h1>
              <p class="mt-1 text-sm text-slate-500">Điểm câu: {{ currentQuestion?.score || 0 }}</p>
            </div>
            <button class="inline-flex h-10 items-center gap-2 rounded-md border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700" @click="toggleReview">
              <Flag class="h-4 w-4" />
              {{ currentQuestion?.is_marked_review ? 'Bỏ đánh dấu' : 'Đánh dấu xem lại' }}
            </button>
          </div>

          <div v-if="currentQuestion" class="py-5">
            <div class="rounded-md border border-slate-200 bg-slate-50 p-4">
              <h2 class="text-base font-bold text-slate-950">{{ currentQuestion.question?.title }}</h2>
              <p class="mt-3 whitespace-pre-line text-sm leading-7 text-slate-800">{{ currentQuestion.question?.stem }}</p>
              <div v-if="questionMediaUrl(currentQuestion)" class="mt-4 overflow-hidden rounded-md border border-slate-200 bg-white">
                <img v-if="questionMediaKind(currentQuestion) === 'image'" :src="questionMediaUrl(currentQuestion)" class="max-h-96 w-full object-contain" alt="Question media" />
                <audio v-else-if="questionMediaKind(currentQuestion) === 'audio'" :src="questionMediaUrl(currentQuestion)" class="w-full p-4" controls preload="metadata" />
                <video v-else-if="questionMediaKind(currentQuestion) === 'video'" :src="questionMediaUrl(currentQuestion)" class="max-h-[420px] w-full bg-black" controls preload="metadata" playsinline />
                <a v-else :href="questionMediaUrl(currentQuestion)" target="_blank" rel="noreferrer" class="block p-3 text-sm font-semibold text-blue-700">Mở học liệu đính kèm</a>
              </div>
            </div>

            <div v-if="(currentQuestion.options || []).length" class="mt-4 space-y-3">
              <label
                v-for="option in currentQuestion.options || []"
                :key="option.option_key"
                class="flex cursor-pointer items-start gap-3 rounded-md border bg-white p-4 text-sm hover:border-blue-300 hover:bg-blue-50"
                :class="answers[currentQuestion.id] === option.option_key ? 'border-blue-500 ring-1 ring-blue-200' : 'border-slate-200'"
              >
                <input class="mt-1" type="radio" :name="`question-${currentQuestion.id}`" :checked="answers[currentQuestion.id] === option.option_key" @change="chooseAnswer(option)" />
                <span class="min-w-0 flex-1">
                  <span class="font-semibold text-slate-900">{{ option.option_key }}.</span>
                  <span class="ml-1 text-slate-800">{{ option.content }}</span>
                  <img v-if="mediaKind(optionMediaUrl(option)) === 'image'" :src="optionMediaUrl(option)" class="mt-3 max-h-64 rounded-md border border-slate-200 object-contain" alt="Option media" />
                  <audio v-else-if="mediaKind(optionMediaUrl(option)) === 'audio'" :src="optionMediaUrl(option)" class="mt-3 w-full" controls preload="metadata" />
                  <video v-else-if="mediaKind(optionMediaUrl(option)) === 'video'" :src="optionMediaUrl(option)" class="mt-3 max-h-80 w-full rounded-md bg-black" controls preload="metadata" playsinline />
                </span>
              </label>
            </div>
            <div v-else class="mt-4 rounded-md border border-slate-200 bg-white p-4">
              <label class="block text-sm font-semibold text-slate-700">Câu trả lời</label>
              <textarea v-model="textAnswers[currentQuestion.id]" rows="6" class="mt-2 w-full rounded-md border border-slate-300 px-3 py-2 text-sm outline-none focus:border-blue-500 focus:ring-4 focus:ring-blue-100" placeholder="Nhập câu trả lời cho câu hỏi hình ảnh, âm thanh, video hoặc tự luận này."></textarea>
              <button class="mt-3 rounded-md bg-blue-600 px-4 py-2 text-sm font-semibold text-white" @click="saveTextAnswer">Lưu đáp án</button>
            </div>
          </div>

          <div class="mt-4 flex flex-wrap justify-between gap-2 border-t border-slate-200 pt-4">
            <button class="h-10 rounded-md border border-slate-300 px-4 text-sm font-semibold text-slate-700 disabled:opacity-50" :disabled="currentIndex === 0" @click="goQuestion(currentIndex - 1)">Câu trước</button>
            <button class="h-10 rounded-md bg-blue-600 px-4 text-sm font-semibold text-white disabled:opacity-50" :disabled="currentIndex >= questions.length - 1" @click="goQuestion(currentIndex + 1)">Câu tiếp theo</button>
          </div>
        </main>

        <aside class="space-y-4">
          <div class="rounded-md border border-slate-200 bg-white p-4">
            <div class="flex items-center gap-2 text-sm font-bold text-slate-950">
              <Clock3 class="h-4 w-4 text-red-600" />
              Thời gian còn lại
            </div>
            <div class="mt-3 text-3xl font-bold text-red-700">{{ displayTime }}</div>
            <div class="mt-2 text-xs text-slate-500">{{ exam?.duration_minutes || 45 }} phút · {{ exam?.max_attempts || 1 }} lần làm</div>
          </div>
          <div class="rounded-md border border-slate-200 bg-white p-4 text-sm text-slate-700">
            <div class="font-bold text-slate-950">Quy chế làm bài</div>
            <p class="mt-2 text-xs leading-5">Hệ thống tự lưu đáp án, ghi nhận chuyển tab, copy/paste và tự nộp khi hết giờ.</p>
          </div>
          <button class="inline-flex h-11 w-full items-center justify-center gap-2 rounded-md bg-emerald-600 px-4 text-sm font-semibold text-white hover:bg-emerald-700 disabled:opacity-60" :disabled="submitting" @click="submitAttempt">
            <Send class="h-4 w-4" />
            Nộp bài
          </button>
          <div class="rounded-md bg-emerald-50 p-3 text-xs text-emerald-700">
            <CheckCircle2 class="mr-1 inline h-4 w-4" />
            Đang ở màn hình thi trắc nghiệm, không phải danh sách đề.
          </div>
        </aside>
      </div>
    </section>
  </EraLmsLayout>
</template>
