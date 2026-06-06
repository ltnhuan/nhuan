<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { Eye, Plus, RefreshCw, Save, Send, WandSparkles, XCircle } from '@lucide/vue'
import EraLmsLayout from '@/Layouts/EraLmsLayout.vue'

const props = defineProps({
  apiHeaders: { type: Object, default: () => ({}) },
})

const loading = ref(true)
const busy = ref(false)
const message = ref('')
const exams = ref([])
const courses = ref([])
const banks = ref([])
const blueprints = ref([])
const selectedExamId = ref(null)
const selectedExam = ref(null)
const preview = ref(null)
const activePanel = ref('configure')
const filters = ref({ status: '', exam_type: '' })

const form = ref(blankExam())
const blueprintForm = ref(blankBlueprint())
const sectionRows = ref([
  { name: 'Nhận biết', question_count: 20, difficulty: ['easy'], bloom_level: ['remember', 'understand'], score_each: 0.5 },
  { name: 'Vận dụng', question_count: 10, difficulty: ['medium', 'hard'], bloom_level: ['apply', 'analyze'], score_each: 1 },
])

const selectedBlueprint = computed(() => blueprints.value.find((item) => item.id === Number(form.value.blueprint_id)) || null)
const selectedBank = computed(() => banks.value.find((item) => item.id === Number(form.value.question_bank_id)) || null)
const examQuestions = computed(() => selectedExam.value?.questions || [])
const examSections = computed(() => selectedExam.value?.sections || [])
const previewSections = computed(() => preview.value?.sections || [])
const previewQuestionCount = computed(() => previewSections.value.reduce((sum, section) => sum + (section.questions?.length || 0), 0))
const builtScore = computed(() => examQuestions.value.reduce((sum, row) => sum + Number(row.score || 0), 0))

watch(selectedBlueprint, (blueprint) => {
  if (!blueprint) return
  form.value.question_bank_id = blueprint.question_bank_id || form.value.question_bank_id
  form.value.total_score = blueprint.total_score || form.value.total_score
  form.value.duration_minutes = blueprint.duration_minutes || form.value.duration_minutes
  if (blueprint.config?.sections?.length) {
    sectionRows.value = blueprint.config.sections.map((section) => ({
      name: section.name || 'Section',
      question_count: Number(section.question_count || 0),
      difficulty: arrayValue(section.difficulty),
      bloom_level: arrayValue(section.bloom_level),
      score_each: Number(section.score_each || 1),
    }))
  }
})

onMounted(loadAll)

function blankExam() {
  return {
    code: `EXAM-${Date.now().toString().slice(-6)}`,
    title: 'Bài kiểm tra mới',
    description: '',
    exam_type: 'quiz',
    delivery_mode: 'self_paced',
    course_id: '',
    question_bank_id: '',
    blueprint_id: '',
    total_score: 10,
    pass_score: 5,
    duration_minutes: 45,
    max_attempts: 1,
    shuffle_questions: true,
    shuffle_options: true,
    show_result_mode: 'after_submit',
    show_correct_answers: false,
    open_at: '',
    close_at: '',
    settings: { proctoring_level: 1 },
  }
}

function blankBlueprint() {
  return {
    code: `BP-${Date.now().toString().slice(-5)}`,
    name: 'Blueprint mới',
    status: 'active',
  }
}

async function api(path, options = {}) {
  const response = await fetch(`/api/v1${path}`, {
    ...options,
    headers: { ...props.apiHeaders, ...(options.headers || {}) },
  })
  const data = await response.json().catch(() => ({}))
  if (!response.ok) throw new Error(data.message || 'Không gọi được API exam builder.')
  return data.data || data
}

async function loadAll() {
  loading.value = true
  try {
    const [examData, courseData, bankData, blueprintData] = await Promise.all([
      api(`/exams?${examQuery()}`),
      api('/courses?per_page=100'),
      api('/question-banks?per_page=100'),
      api('/exam-blueprints?per_page=100'),
    ])
    exams.value = examData.data || examData
    courses.value = courseData.data || courseData
    banks.value = bankData.data || bankData
    blueprints.value = blueprintData.data || blueprintData
    hydrateDefaults()
    if (!selectedExamId.value && exams.value[0]) selectedExamId.value = exams.value[0].id
    if (selectedExamId.value) await loadExam(selectedExamId.value, false)
    message.value = 'Đã tải Exam Builder.'
  } catch (error) {
    message.value = error.message
  } finally {
    loading.value = false
  }
}

function hydrateDefaults() {
  form.value.course_id ||= courses.value[0]?.id || ''
  form.value.question_bank_id ||= banks.value[0]?.id || ''
  form.value.blueprint_id ||= blueprints.value[0]?.id || ''
}

function examQuery() {
  const query = new URLSearchParams({ per_page: '50' })
  if (filters.value.status) query.set('status', filters.value.status)
  if (filters.value.exam_type) query.set('exam_type', filters.value.exam_type)
  return query.toString()
}

async function loadExam(id, showMessage = true) {
  if (!id) return
  try {
    selectedExam.value = await api(`/exams/${id}`)
    selectedExamId.value = selectedExam.value.id
    if (showMessage) message.value = `Đang xem ${selectedExam.value.title}.`
  } catch (error) {
    message.value = error.message
  }
}

function toExamPayload(source = form.value) {
  return {
    ...source,
    course_id: source.course_id ? Number(source.course_id) : null,
    question_bank_id: source.question_bank_id ? Number(source.question_bank_id) : null,
    blueprint_id: source.blueprint_id ? Number(source.blueprint_id) : null,
    total_score: Number(source.total_score || 0),
    pass_score: source.pass_score === '' || source.pass_score === null ? null : Number(source.pass_score),
    duration_minutes: source.duration_minutes ? Number(source.duration_minutes) : null,
    max_attempts: Number(source.max_attempts || 1),
    open_at: source.open_at || null,
    close_at: source.close_at || null,
  }
}

function blueprintPayload() {
  const totalQuestions = sectionRows.value.reduce((sum, row) => sum + Number(row.question_count || 0), 0)
  const totalScore = sectionRows.value.reduce((sum, row) => sum + Number(row.question_count || 0) * Number(row.score_each || 0), 0)
  return {
    code: blueprintForm.value.code,
    name: blueprintForm.value.name,
    question_bank_id: Number(form.value.question_bank_id),
    total_questions: totalQuestions,
    total_score: totalScore,
    duration_minutes: Number(form.value.duration_minutes || 45),
    status: blueprintForm.value.status,
    config: {
      sections: sectionRows.value.map((row) => ({
        name: row.name,
        question_count: Number(row.question_count || 0),
        difficulty: arrayValue(row.difficulty),
        bloom_level: arrayValue(row.bloom_level),
        score_each: Number(row.score_each || 1),
      })),
      randomize_questions: true,
      randomize_options: true,
    },
  }
}

async function createBlueprint() {
  if (!form.value.question_bank_id) {
    message.value = 'Chọn ngân hàng câu hỏi trước khi tạo blueprint.'
    return
  }
  if (!blueprintForm.value.code.trim() || !blueprintForm.value.name.trim()) {
    message.value = 'Nhập mã và tên blueprint.'
    return
  }

  busy.value = true
  try {
    const blueprint = await api('/exam-blueprints', { method: 'POST', body: JSON.stringify(blueprintPayload()) })
    await refreshBlueprints()
    form.value.blueprint_id = blueprint.id
    message.value = 'Đã tạo blueprint từ cấu hình section.'
  } catch (error) {
    message.value = error.message
  } finally {
    busy.value = false
  }
}

async function previewBlueprint() {
  if (!form.value.blueprint_id) {
    message.value = 'Chọn hoặc tạo blueprint trước khi preview.'
    return
  }

  busy.value = true
  try {
    preview.value = await api(`/exam-blueprints/${form.value.blueprint_id}/generate-preview`, { method: 'POST' })
    activePanel.value = 'preview'
    message.value = `Preview có ${previewQuestionCount.value} câu.`
  } catch (error) {
    message.value = error.message
  } finally {
    busy.value = false
  }
}

async function createExam() {
  if (!form.value.code.trim() || !form.value.title.trim()) {
    message.value = 'Nhập mã và tiêu đề bài kiểm tra.'
    return
  }
  if (!form.value.question_bank_id || !form.value.blueprint_id) {
    message.value = 'Chọn ngân hàng câu hỏi và blueprint trước khi tạo exam.'
    return
  }

  busy.value = true
  try {
    const exam = await api('/exams', { method: 'POST', body: JSON.stringify(toExamPayload()) })
    await refreshExams()
    await loadExam(exam.id)
    activePanel.value = 'build'
    message.value = 'Đã tạo bài kiểm tra draft.'
  } catch (error) {
    message.value = error.message
  } finally {
    busy.value = false
  }
}

async function updateExam() {
  if (!selectedExam.value) {
    message.value = 'Chọn exam trước khi cập nhật.'
    return
  }

  busy.value = true
  try {
    await api(`/exams/${selectedExam.value.id}`, { method: 'PUT', body: JSON.stringify(toExamPayload()) })
    await refreshExams()
    await loadExam(selectedExam.value.id)
    message.value = 'Đã cập nhật cấu hình exam.'
  } catch (error) {
    message.value = error.message
  } finally {
    busy.value = false
  }
}

async function buildExam() {
  if (!selectedExam.value) {
    message.value = 'Tạo hoặc chọn exam trước khi build.'
    return
  }
  if (!selectedExam.value.blueprint_id) {
    message.value = 'Exam chưa gắn blueprint.'
    return
  }

  busy.value = true
  try {
    await api(`/exams/${selectedExam.value.id}/build-from-blueprint`, { method: 'POST' })
    await loadExam(selectedExam.value.id)
    activePanel.value = 'build'
    message.value = `Đã build ${examQuestions.value.length} câu vào đề.`
  } catch (error) {
    message.value = error.message
  } finally {
    busy.value = false
  }
}

async function publishExam() {
  if (!selectedExam.value) return
  busy.value = true
  try {
    await api(`/exams/${selectedExam.value.id}/publish`, { method: 'POST' })
    await refreshExams()
    await loadExam(selectedExam.value.id)
    message.value = 'Đã publish exam.'
  } catch (error) {
    message.value = error.message
  } finally {
    busy.value = false
  }
}

async function closeExam() {
  if (!selectedExam.value) return
  busy.value = true
  try {
    await api(`/exams/${selectedExam.value.id}/close`, { method: 'POST' })
    await refreshExams()
    await loadExam(selectedExam.value.id)
    message.value = 'Đã đóng exam.'
  } catch (error) {
    message.value = error.message
  } finally {
    busy.value = false
  }
}

async function refreshExams() {
  const data = await api(`/exams?${examQuery()}`)
  exams.value = data.data || data
}

async function refreshBlueprints() {
  const data = await api('/exam-blueprints?per_page=100')
  blueprints.value = data.data || data
}

function useSelectedExam() {
  if (!selectedExam.value) return
  form.value = {
    ...blankExam(),
    ...selectedExam.value,
    course_id: selectedExam.value.course_id || '',
    question_bank_id: selectedExam.value.question_bank_id || '',
    blueprint_id: selectedExam.value.blueprint_id || '',
    open_at: toInputDateTime(selectedExam.value.open_at),
    close_at: toInputDateTime(selectedExam.value.close_at),
    settings: selectedExam.value.settings || { proctoring_level: 1 },
  }
  activePanel.value = 'configure'
  message.value = `Đã nạp cấu hình ${selectedExam.value.title} vào form.`
}

function newDraft() {
  form.value = blankExam()
  hydrateDefaults()
  blueprintForm.value = blankBlueprint()
  preview.value = null
  activePanel.value = 'configure'
  message.value = 'Đã tạo form draft mới.'
}

function addSection() {
  sectionRows.value.push({ name: `Section ${sectionRows.value.length + 1}`, question_count: 5, difficulty: [], bloom_level: [], score_each: 1 })
}

function removeSection(index) {
  sectionRows.value.splice(index, 1)
}

function arrayValue(value) {
  if (Array.isArray(value)) return value
  if (!value) return []
  return String(value).split(',').map((item) => item.trim()).filter(Boolean)
}

function optionLabel(item, fields = ['code', 'title']) {
  if (!item) return '-'
  return fields.map((field) => item[field]).filter(Boolean).join(' - ')
}

function formatDate(value) {
  if (!value) return '-'
  return new Intl.DateTimeFormat('vi-VN', { dateStyle: 'short', timeStyle: 'short' }).format(new Date(value))
}

function toInputDateTime(value) {
  if (!value) return ''
  const date = new Date(value)
  return new Date(date.getTime() - date.getTimezoneOffset() * 60000).toISOString().slice(0, 16)
}
</script>

<template>
  <EraLmsLayout>
    <template #breadcrumb>Kiểm tra online / Builder</template>

    <section class="min-h-[calc(100vh-7rem)] bg-slate-50">
      <div class="border-b bg-white">
        <div class="mx-auto flex max-w-7xl flex-wrap items-center justify-between gap-4 px-6 py-5">
          <div>
            <h1 class="text-xl font-bold text-slate-950">Exam Builder</h1>
            <p class="mt-1 text-sm text-slate-600">Tạo blueprint, cấu hình exam, build câu hỏi, publish và kiểm tra cấu trúc đề.</p>
          </div>
          <div class="flex flex-wrap gap-2">
            <button class="inline-flex items-center gap-2 rounded-md border border-slate-300 bg-white px-3 py-2 text-sm font-semibold" @click="loadAll"><RefreshCw class="h-4 w-4" />Tải lại</button>
            <button class="inline-flex items-center gap-2 rounded-md border border-slate-300 bg-white px-3 py-2 text-sm font-semibold" @click="newDraft"><Plus class="h-4 w-4" />Draft mới</button>
            <button class="inline-flex items-center gap-2 rounded-md bg-slate-950 px-3 py-2 text-sm font-semibold text-white disabled:opacity-50" :disabled="busy" @click="createExam"><Save class="h-4 w-4" />Lưu exam</button>
          </div>
        </div>
      </div>

      <div class="mx-auto grid max-w-7xl gap-4 px-6 py-5 xl:grid-cols-[320px_minmax(0,1fr)_360px]">
        <aside class="min-w-0 space-y-4">
          <div class="border bg-white">
            <div class="border-b px-4 py-3">
              <h2 class="text-sm font-semibold">Danh sách exam</h2>
              <p class="mt-1 text-xs text-slate-500">{{ exams.length }} bài kiểm tra</p>
            </div>
            <div class="grid grid-cols-2 gap-2 p-3">
              <select v-model="filters.status" class="rounded-md border px-2 py-2 text-xs" @change="refreshExams">
                <option value="">Status</option>
                <option value="draft">Draft</option>
                <option value="published">Published</option>
                <option value="closed">Closed</option>
              </select>
              <select v-model="filters.exam_type" class="rounded-md border px-2 py-2 text-xs" @change="refreshExams">
                <option value="">Type</option>
                <option value="quiz">Quiz</option>
                <option value="midterm">Midterm</option>
                <option value="final">Final</option>
                <option value="practice">Practice</option>
              </select>
            </div>
            <div class="max-h-[560px] overflow-auto p-3 pt-0">
              <button
                v-for="exam in exams"
                :key="exam.id"
                class="mb-2 w-full rounded-md border px-3 py-3 text-left text-sm"
                :class="selectedExam?.id === exam.id ? 'border-cyan-500 bg-cyan-50' : 'border-slate-200 bg-white hover:bg-slate-50'"
                @click="loadExam(exam.id)"
              >
                <span class="block truncate font-semibold text-slate-950">{{ exam.title }}</span>
                <span class="mt-1 block text-xs text-slate-500">{{ exam.code }} · {{ exam.status }} · {{ exam.questions_count || 0 }} câu</span>
              </button>
              <div v-if="!loading && !exams.length" class="rounded-md bg-slate-50 p-4 text-sm text-slate-500">Chưa có exam.</div>
            </div>
          </div>
        </aside>

        <main class="min-w-0 space-y-4">
          <div v-if="message" class="rounded-md border border-cyan-200 bg-cyan-50 px-4 py-3 text-sm text-cyan-900">{{ message }}</div>
          <div v-if="loading" class="rounded-md border bg-white p-5 text-sm text-slate-500">Đang tải builder...</div>

          <template v-else>
            <div class="grid gap-3 md:grid-cols-4">
              <button class="rounded-md border px-3 py-3 text-left text-sm" :class="activePanel === 'configure' ? 'border-cyan-500 bg-white text-cyan-800' : 'bg-white'" @click="activePanel = 'configure'">
                <span class="block text-xs uppercase text-slate-500">Step 1</span><span class="font-semibold">Cấu hình</span>
              </button>
              <button class="rounded-md border px-3 py-3 text-left text-sm" :class="activePanel === 'blueprint' ? 'border-cyan-500 bg-white text-cyan-800' : 'bg-white'" @click="activePanel = 'blueprint'">
                <span class="block text-xs uppercase text-slate-500">Step 2</span><span class="font-semibold">Blueprint</span>
              </button>
              <button class="rounded-md border px-3 py-3 text-left text-sm" :class="activePanel === 'preview' ? 'border-cyan-500 bg-white text-cyan-800' : 'bg-white'" @click="activePanel = 'preview'">
                <span class="block text-xs uppercase text-slate-500">Step 3</span><span class="font-semibold">Preview</span>
              </button>
              <button class="rounded-md border px-3 py-3 text-left text-sm" :class="activePanel === 'build' ? 'border-cyan-500 bg-white text-cyan-800' : 'bg-white'" @click="activePanel = 'build'">
                <span class="block text-xs uppercase text-slate-500">Step 4</span><span class="font-semibold">Build</span>
              </button>
            </div>

            <section v-if="activePanel === 'configure'" class="border bg-white">
              <div class="border-b px-5 py-4">
                <h2 class="text-sm font-semibold">Cấu hình bài kiểm tra</h2>
              </div>
              <div class="grid gap-4 p-5 md:grid-cols-2">
                <label class="text-sm"><span class="text-xs font-semibold uppercase text-slate-500">Mã exam</span><input v-model="form.code" class="mt-1 h-10 w-full rounded-md border px-3 text-sm" /></label>
                <label class="text-sm"><span class="text-xs font-semibold uppercase text-slate-500">Tiêu đề</span><input v-model="form.title" class="mt-1 h-10 w-full rounded-md border px-3 text-sm" /></label>
                <label class="text-sm"><span class="text-xs font-semibold uppercase text-slate-500">Khóa học</span><select v-model="form.course_id" class="mt-1 h-10 w-full rounded-md border bg-white px-3 text-sm"><option value="">Không gắn course</option><option v-for="course in courses" :key="course.id" :value="course.id">{{ optionLabel(course) }}</option></select></label>
                <label class="text-sm"><span class="text-xs font-semibold uppercase text-slate-500">Ngân hàng câu hỏi</span><select v-model="form.question_bank_id" class="mt-1 h-10 w-full rounded-md border bg-white px-3 text-sm"><option v-for="bank in banks" :key="bank.id" :value="bank.id">{{ optionLabel(bank, ['code', 'name']) }}</option></select></label>
                <label class="text-sm"><span class="text-xs font-semibold uppercase text-slate-500">Blueprint</span><select v-model="form.blueprint_id" class="mt-1 h-10 w-full rounded-md border bg-white px-3 text-sm"><option value="">Chọn blueprint</option><option v-for="blueprint in blueprints" :key="blueprint.id" :value="blueprint.id">{{ optionLabel(blueprint, ['code', 'name']) }}</option></select></label>
                <label class="text-sm"><span class="text-xs font-semibold uppercase text-slate-500">Loại exam</span><select v-model="form.exam_type" class="mt-1 h-10 w-full rounded-md border bg-white px-3 text-sm"><option value="quiz">Quiz</option><option value="midterm">Midterm</option><option value="final">Final</option><option value="practice">Practice</option></select></label>
                <label class="text-sm"><span class="text-xs font-semibold uppercase text-slate-500">Delivery</span><select v-model="form.delivery_mode" class="mt-1 h-10 w-full rounded-md border bg-white px-3 text-sm"><option value="self_paced">Self paced</option><option value="scheduled">Scheduled</option><option value="remote_proctored">Remote proctored</option></select></label>
                <label class="text-sm"><span class="text-xs font-semibold uppercase text-slate-500">Hiển thị kết quả</span><select v-model="form.show_result_mode" class="mt-1 h-10 w-full rounded-md border bg-white px-3 text-sm"><option value="after_submit">After submit</option><option value="immediately">Immediately</option><option value="after_close">After close</option><option value="manual">Manual</option></select></label>
                <label class="text-sm"><span class="text-xs font-semibold uppercase text-slate-500">Tổng điểm</span><input v-model="form.total_score" type="number" class="mt-1 h-10 w-full rounded-md border px-3 text-sm" /></label>
                <label class="text-sm"><span class="text-xs font-semibold uppercase text-slate-500">Điểm đạt</span><input v-model="form.pass_score" type="number" class="mt-1 h-10 w-full rounded-md border px-3 text-sm" /></label>
                <label class="text-sm"><span class="text-xs font-semibold uppercase text-slate-500">Thời gian phút</span><input v-model="form.duration_minutes" type="number" class="mt-1 h-10 w-full rounded-md border px-3 text-sm" /></label>
                <label class="text-sm"><span class="text-xs font-semibold uppercase text-slate-500">Số lần làm</span><input v-model="form.max_attempts" type="number" class="mt-1 h-10 w-full rounded-md border px-3 text-sm" /></label>
                <label class="text-sm"><span class="text-xs font-semibold uppercase text-slate-500">Mở bài</span><input v-model="form.open_at" type="datetime-local" class="mt-1 h-10 w-full rounded-md border px-3 text-sm" /></label>
                <label class="text-sm"><span class="text-xs font-semibold uppercase text-slate-500">Đóng bài</span><input v-model="form.close_at" type="datetime-local" class="mt-1 h-10 w-full rounded-md border px-3 text-sm" /></label>
                <label class="md:col-span-2 text-sm"><span class="text-xs font-semibold uppercase text-slate-500">Mô tả</span><textarea v-model="form.description" rows="3" class="mt-1 w-full rounded-md border px-3 py-2 text-sm"></textarea></label>
              </div>
              <div class="flex flex-wrap gap-3 border-t px-5 py-4">
                <label class="inline-flex items-center gap-2 text-sm"><input v-model="form.shuffle_questions" type="checkbox" /> Shuffle câu hỏi</label>
                <label class="inline-flex items-center gap-2 text-sm"><input v-model="form.shuffle_options" type="checkbox" /> Shuffle đáp án</label>
                <label class="inline-flex items-center gap-2 text-sm"><input v-model="form.show_correct_answers" type="checkbox" /> Hiện đáp án đúng</label>
              </div>
            </section>

            <section v-else-if="activePanel === 'blueprint'" class="border bg-white">
              <div class="flex flex-wrap items-center justify-between gap-3 border-b px-5 py-4">
                <div><h2 class="text-sm font-semibold">Blueprint sections</h2><p class="mt-1 text-xs text-slate-500">{{ selectedBank?.name || 'Chọn ngân hàng để tạo blueprint' }}</p></div>
                <button class="inline-flex items-center gap-2 rounded-md border px-3 py-2 text-sm font-semibold" @click="addSection"><Plus class="h-4 w-4" />Thêm section</button>
              </div>
              <div class="space-y-3 p-5">
                <div class="grid gap-3 md:grid-cols-2">
                  <input v-model="blueprintForm.code" class="rounded-md border px-3 py-2 text-sm" placeholder="Mã blueprint" />
                  <input v-model="blueprintForm.name" class="rounded-md border px-3 py-2 text-sm" placeholder="Tên blueprint" />
                </div>
                <div v-for="(section, index) in sectionRows" :key="index" class="grid gap-3 rounded-md border p-3 md:grid-cols-[1fr_110px_1fr_1fr_100px_42px]">
                  <input v-model="section.name" class="rounded-md border px-3 py-2 text-sm" />
                  <input v-model="section.question_count" type="number" class="rounded-md border px-3 py-2 text-sm" />
                  <input :value="section.difficulty.join(', ')" class="rounded-md border px-3 py-2 text-sm" placeholder="easy, medium" @input="section.difficulty = arrayValue($event.target.value)" />
                  <input :value="section.bloom_level.join(', ')" class="rounded-md border px-3 py-2 text-sm" placeholder="remember, apply" @input="section.bloom_level = arrayValue($event.target.value)" />
                  <input v-model="section.score_each" type="number" step="0.25" class="rounded-md border px-3 py-2 text-sm" />
                  <button class="grid h-10 place-items-center rounded-md border text-rose-700" @click="removeSection(index)"><XCircle class="h-4 w-4" /></button>
                </div>
              </div>
              <div class="flex flex-wrap gap-2 border-t px-5 py-4">
                <button class="inline-flex items-center gap-2 rounded-md bg-slate-950 px-3 py-2 text-sm font-semibold text-white disabled:opacity-50" :disabled="busy" @click="createBlueprint"><Save class="h-4 w-4" />Tạo blueprint</button>
                <button class="inline-flex items-center gap-2 rounded-md border px-3 py-2 text-sm font-semibold disabled:opacity-50" :disabled="busy" @click="previewBlueprint"><Eye class="h-4 w-4" />Preview blueprint</button>
              </div>
            </section>

            <section v-else-if="activePanel === 'preview'" class="border bg-white">
              <div class="flex flex-wrap items-center justify-between gap-3 border-b px-5 py-4">
                <div><h2 class="text-sm font-semibold">Random preview</h2><p class="mt-1 text-xs text-slate-500">{{ previewQuestionCount }} câu từ {{ previewSections.length }} section</p></div>
                <button class="inline-flex items-center gap-2 rounded-md bg-slate-950 px-3 py-2 text-sm font-semibold text-white disabled:opacity-50" :disabled="busy" @click="previewBlueprint"><WandSparkles class="h-4 w-4" />Sinh lại</button>
              </div>
              <div class="p-5">
                <div v-if="preview?.warnings?.length" class="mb-4 rounded-md border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900">
                  <div v-for="warning in preview.warnings" :key="warning">{{ warning }}</div>
                </div>
                <div v-for="section in previewSections" :key="section.name" class="mb-4 overflow-hidden rounded-md border">
                  <div class="bg-slate-50 px-3 py-2 text-sm font-semibold">{{ section.name }} · {{ section.questions?.length || 0 }} câu</div>
                  <div class="divide-y">
                    <div v-for="question in section.questions || []" :key="question.id" class="grid gap-2 px-3 py-2 text-sm md:grid-cols-[100px_1fr_140px_120px]">
                      <div class="font-medium">{{ question.code }}</div>
                      <div>{{ question.title }}</div>
                      <div>{{ question.question_type }}</div>
                      <div>{{ question.difficulty }} / {{ question.bloom_level }}</div>
                    </div>
                  </div>
                </div>
                <div v-if="!previewSections.length" class="rounded-md bg-slate-50 p-5 text-sm text-slate-500">Chưa có preview. Bấm Preview blueprint để sinh danh sách câu hỏi.</div>
              </div>
            </section>

            <section v-else class="border bg-white">
              <div class="flex flex-wrap items-center justify-between gap-3 border-b px-5 py-4">
                <div><h2 class="text-sm font-semibold">Build & publish</h2><p class="mt-1 text-xs text-slate-500">{{ selectedExam?.title || 'Chưa chọn exam' }}</p></div>
                <div class="flex flex-wrap gap-2">
                  <button class="inline-flex items-center gap-2 rounded-md border px-3 py-2 text-sm font-semibold" @click="useSelectedExam"><RefreshCw class="h-4 w-4" />Nạp vào form</button>
                  <button class="inline-flex items-center gap-2 rounded-md border px-3 py-2 text-sm font-semibold disabled:opacity-50" :disabled="busy" @click="updateExam"><Save class="h-4 w-4" />Cập nhật</button>
                  <button class="inline-flex items-center gap-2 rounded-md bg-cyan-700 px-3 py-2 text-sm font-semibold text-white disabled:opacity-50" :disabled="busy" @click="buildExam"><WandSparkles class="h-4 w-4" />Build</button>
                  <button class="inline-flex items-center gap-2 rounded-md bg-slate-950 px-3 py-2 text-sm font-semibold text-white disabled:opacity-50" :disabled="busy" @click="publishExam"><Send class="h-4 w-4" />Publish</button>
                  <button class="inline-flex items-center gap-2 rounded-md border border-rose-200 px-3 py-2 text-sm font-semibold text-rose-700 disabled:opacity-50" :disabled="busy" @click="closeExam"><XCircle class="h-4 w-4" />Close</button>
                </div>
              </div>
              <div class="grid gap-4 p-5 md:grid-cols-4">
                <div class="rounded-md bg-slate-50 p-3 text-sm"><div class="text-xs text-slate-500">Status</div><div class="mt-1 font-semibold">{{ selectedExam?.status || '-' }}</div></div>
                <div class="rounded-md bg-slate-50 p-3 text-sm"><div class="text-xs text-slate-500">Sections</div><div class="mt-1 font-semibold">{{ examSections.length }}</div></div>
                <div class="rounded-md bg-slate-50 p-3 text-sm"><div class="text-xs text-slate-500">Questions</div><div class="mt-1 font-semibold">{{ examQuestions.length }}</div></div>
                <div class="rounded-md bg-slate-50 p-3 text-sm"><div class="text-xs text-slate-500">Built score</div><div class="mt-1 font-semibold">{{ builtScore }}</div></div>
              </div>
              <div class="px-5 pb-5">
                <div class="overflow-hidden border">
                  <table class="w-full text-left text-sm">
                    <thead class="bg-slate-50 text-xs uppercase text-slate-500"><tr><th class="px-3 py-3">#</th><th class="px-3 py-3">Câu hỏi</th><th class="px-3 py-3">Loại</th><th class="px-3 py-3">Điểm</th></tr></thead>
                    <tbody class="divide-y">
                      <tr v-for="row in examQuestions" :key="row.id">
                        <td class="px-3 py-3">{{ row.sort_order }}</td>
                        <td class="px-3 py-3"><div class="font-medium">{{ row.question?.code }}</div><div class="text-slate-600">{{ row.question?.title }}</div></td>
                        <td class="px-3 py-3">{{ row.question?.question_type }}</td>
                        <td class="px-3 py-3">{{ row.score }}</td>
                      </tr>
                      <tr v-if="!examQuestions.length"><td colspan="4" class="px-3 py-8 text-center text-slate-500">Chưa có câu hỏi. Build từ blueprint để tạo cấu trúc đề.</td></tr>
                    </tbody>
                  </table>
                </div>
              </div>
            </section>
          </template>
        </main>

        <aside class="min-w-0 space-y-4">
          <div class="border bg-white p-4">
            <h2 class="text-sm font-semibold">Exam đang chọn</h2>
            <div class="mt-3 space-y-2 text-sm">
              <div class="flex justify-between"><span class="text-slate-500">Mã</span><strong>{{ selectedExam?.code || '-' }}</strong></div>
              <div class="flex justify-between"><span class="text-slate-500">Trạng thái</span><strong>{{ selectedExam?.status || '-' }}</strong></div>
              <div class="flex justify-between"><span class="text-slate-500">Thời lượng</span><strong>{{ selectedExam?.duration_minutes || '-' }} phút</strong></div>
              <div class="flex justify-between"><span class="text-slate-500">Mở</span><span>{{ formatDate(selectedExam?.open_at) }}</span></div>
              <div class="flex justify-between"><span class="text-slate-500">Đóng</span><span>{{ formatDate(selectedExam?.close_at) }}</span></div>
            </div>
          </div>
          <div class="border bg-white p-4">
            <h2 class="text-sm font-semibold">Nguồn đề</h2>
            <div class="mt-3 space-y-3 text-sm">
              <div class="rounded-md bg-slate-50 p-3"><div class="text-xs text-slate-500">Question bank</div><div class="mt-1 font-semibold">{{ selectedBank?.name || selectedExam?.question_bank?.name || '-' }}</div></div>
              <div class="rounded-md bg-slate-50 p-3"><div class="text-xs text-slate-500">Blueprint</div><div class="mt-1 font-semibold">{{ selectedBlueprint?.name || selectedExam?.blueprint?.name || '-' }}</div></div>
            </div>
          </div>
          <div class="border bg-white p-4">
            <h2 class="text-sm font-semibold">Checklist</h2>
            <div class="mt-3 space-y-2 text-sm">
              <div class="flex justify-between"><span>Có blueprint</span><strong :class="form.blueprint_id ? 'text-emerald-700' : 'text-amber-700'">{{ form.blueprint_id ? 'OK' : 'Thiếu' }}</strong></div>
              <div class="flex justify-between"><span>Có bank</span><strong :class="form.question_bank_id ? 'text-emerald-700' : 'text-amber-700'">{{ form.question_bank_id ? 'OK' : 'Thiếu' }}</strong></div>
              <div class="flex justify-between"><span>Pass score</span><strong :class="Number(form.pass_score) <= Number(form.total_score) ? 'text-emerald-700' : 'text-rose-700'">{{ Number(form.pass_score) <= Number(form.total_score) ? 'OK' : 'Sai' }}</strong></div>
              <div class="flex justify-between"><span>Đã build</span><strong :class="examQuestions.length ? 'text-emerald-700' : 'text-amber-700'">{{ examQuestions.length ? 'OK' : 'Chưa' }}</strong></div>
            </div>
          </div>
        </aside>
      </div>
    </section>
  </EraLmsLayout>
</template>
