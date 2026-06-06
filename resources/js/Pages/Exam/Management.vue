<script setup>
import { computed, onMounted, ref } from 'vue'
import EraLmsLayout from '@/Layouts/EraLmsLayout.vue'
import ActionBar from '@/Components/Lms/ActionBar.vue'
import StatusBadge from '@/Components/Lms/StatusBadge.vue'
import { useLmsAction } from '@/composables/useLmsAction'

const props = defineProps({
  sessionUser: { type: Object, default: null },
  apiHeaders: { type: Object, default: () => ({}) },
})

defineEmits(['logout'])

const exams = ref([])
const courseOptions = ref([])
const questionBankOptions = ref([])
const blueprintOptions = ref([])
const userOptions = ref([])
const classOptions = ref([])
const selectedExam = ref(null)
const selectedAttempts = ref([])
const selectedResults = ref([])
const detailTab = ref('overview')
const loadingPage = ref(false)
const creating = ref(false)
const editingExam = ref(null)
const assigningExam = ref(null)
const assignMode = ref('user')
const assignForm = ref({ user_id: '', class_id: '' })
const search = ref('')
const statusFilter = ref('')
const typeFilter = ref('')
const { loading, toast, validationErrors, runAction } = useLmsAction(props.apiHeaders)

const blankForm = () => ({
  code: `EXAM-${Date.now().toString().slice(-6)}`,
  title: 'Bài kiểm tra mới',
  description: '',
  exam_type: 'quiz',
  delivery_mode: 'self_paced',
  status: 'draft',
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
  course_id: '',
  question_bank_id: '',
  blueprint_id: '',
})

const form = ref(blankForm())
const editForm = ref(blankForm())

const filteredExams = computed(() => {
  const keyword = search.value.trim().toLowerCase()
  return exams.value.filter((exam) => {
    const matchesKeyword = !keyword || `${exam.code} ${exam.title}`.toLowerCase().includes(keyword)
    const matchesStatus = !statusFilter.value || exam.status === statusFilter.value
    const matchesType = !typeFilter.value || exam.exam_type === typeFilter.value
    return matchesKeyword && matchesStatus && matchesType
  })
})

const stats = computed(() => ({
  total: exams.value.length,
  draft: exams.value.filter((exam) => exam.status === 'draft').length,
  published: exams.value.filter((exam) => exam.status === 'published').length,
  closed: exams.value.filter((exam) => exam.status === 'closed').length,
}))

const rowActions = computed(() => [
  { action_key: 'exam.view', label: 'Xem', local: 'view' },
  { action_key: 'exam.update', label: 'Sửa', local: 'edit' },
  { action_key: 'exam.build', label: 'Build', route: (exam) => `/api/v1/exams/${exam.id}/build-from-blueprint`, method: 'POST', confirm_required: true, confirm_message: 'Build lại đề từ blueprint?' },
  { action_key: 'exam.publish', label: 'Publish', route: (exam) => `/api/v1/exams/${exam.id}/publish`, method: 'POST', confirm_required: true, confirm_message: 'Publish bài kiểm tra này?' },
  { action_key: 'exam.close', label: 'Close', route: (exam) => `/api/v1/exams/${exam.id}/close`, method: 'POST', confirm_required: true, confirm_message: 'Đóng bài kiểm tra này?' },
  { action_key: 'exam.assign_user', label: 'Gán user', local: 'assign_user' },
  { action_key: 'exam.assign_class', label: 'Gán lớp', local: 'assign_class' },
  { action_key: 'exam.results', label: 'Kết quả', local: 'results' },
])

function toPayload(source) {
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

async function load() {
  loadingPage.value = true
  try {
    const params = new URLSearchParams({ per_page: '100' })
    if (statusFilter.value) params.set('status', statusFilter.value)
    if (typeFilter.value) params.set('exam_type', typeFilter.value)
    const response = await fetch(`/api/v1/exams?${params.toString()}`, { headers: props.apiHeaders })
    const payload = await response.json()
    exams.value = payload.data?.data || payload.data || []
  } finally {
    loadingPage.value = false
  }
}

async function loadOptions() {
  const [coursesPayload, banksPayload, blueprintsPayload, usersPayload, classesPayload] = await Promise.all([
    safeApi('/api/v1/courses?per_page=100'),
    safeApi('/api/v1/question-banks?per_page=100'),
    safeApi('/api/v1/exam-blueprints?per_page=100'),
    safeApi('/api/v1/core/users?per_page=100&user_type=student'),
    safeApi('/api/v1/enrollment/sections?per_page=100'),
  ])
  courseOptions.value = payloadList(coursesPayload)
  questionBankOptions.value = payloadList(banksPayload)
  blueprintOptions.value = payloadList(blueprintsPayload)
  userOptions.value = payloadList(usersPayload)
  classOptions.value = payloadList(classesPayload)
}

async function safeApi(url) {
  try {
    const response = await fetch(url, { headers: props.apiHeaders })
    if (!response.ok) return null
    return await response.json()
  } catch {
    return null
  }
}

function payloadList(payload) {
  return payload?.data?.data || payload?.data || []
}

async function createExam() {
  await runAction({
    actionKey: 'exam.create',
    url: '/api/v1/exams',
    method: 'POST',
    body: toPayload(form.value),
    reload: async () => {
      creating.value = false
      form.value = blankForm()
      await load()
    },
  })
}

async function openExam(exam, tab = 'overview') {
  detailTab.value = tab
  const [examResponse, attemptsResponse, resultsResponse] = await Promise.all([
    fetch(`/api/v1/exams/${exam.id}`, { headers: props.apiHeaders }),
    fetch(`/api/v1/exams/${exam.id}/attempts`, { headers: props.apiHeaders }),
    fetch(`/api/v1/exams/${exam.id}/results`, { headers: props.apiHeaders }),
  ])
  const examPayload = await examResponse.json()
  const attemptsPayload = await attemptsResponse.json()
  const resultsPayload = await resultsResponse.json()
  selectedExam.value = examPayload.data || examPayload
  selectedAttempts.value = attemptsPayload.data?.data || attemptsPayload.data || []
  selectedResults.value = resultsPayload.data?.data || resultsPayload.data || []
}

function openEdit(exam) {
  editingExam.value = exam
  editForm.value = {
    ...blankForm(),
    ...exam,
    open_at: toInputDateTime(exam.open_at),
    close_at: toInputDateTime(exam.close_at),
    course_id: exam.course_id || '',
    question_bank_id: exam.question_bank_id || '',
    blueprint_id: exam.blueprint_id || '',
  }
}

async function saveEdit() {
  await runAction({
    actionKey: `exam.update:${editingExam.value.id}`,
    url: `/api/v1/exams/${editingExam.value.id}`,
    method: 'PUT',
    body: toPayload(editForm.value),
    reload: async () => {
      editingExam.value = null
      await load()
    },
  })
}

function openAssign(exam, mode) {
  assigningExam.value = exam
  assignMode.value = mode
  assignForm.value = { user_id: userOptions.value[0]?.id || '', class_id: classOptions.value[0]?.id || '' }
}

async function assignUser() {
  if (!assigningExam.value || !assignForm.value.user_id) return
  await runAction({
    actionKey: `exam.assign_user:${assigningExam.value.id}`,
    url: `/api/v1/exams/${assigningExam.value.id}/assign-user`,
    method: 'POST',
    body: { user_id: Number(assignForm.value.user_id) },
    reload: async () => {
      const exam = assigningExam.value
      assigningExam.value = null
      await openExam(exam, 'attempts')
    },
  })
}

async function assignClass() {
  if (!assigningExam.value || !assignForm.value.class_id) return
  await runAction({
    actionKey: `exam.assign_class:${assigningExam.value.id}`,
    url: `/api/v1/exams/${assigningExam.value.id}/assign-class`,
    method: 'POST',
    body: { class_id: Number(assignForm.value.class_id) },
    confirm: true,
    confirmMessage: 'Gán bài kiểm tra cho lớp?',
    reload: async () => {
      const exam = assigningExam.value
      assigningExam.value = null
      await openExam(exam, 'attempts')
    },
  })
}

async function runRowAction(action, exam) {
  if (action.local === 'view') return openExam(exam)
  if (action.local === 'edit') return openEdit(exam)
  if (action.local === 'assign_user') return openAssign(exam, 'user')
  if (action.local === 'assign_class') return openAssign(exam, 'class')
  if (action.local === 'results') return openExam(exam, 'results')

  await runAction({
    actionKey: `${action.action_key}:${exam.id}`,
    url: action.route(exam),
    method: action.method,
    confirm: action.confirm_required,
    confirmMessage: action.confirm_message,
    reload: async () => {
      await load()
      if (selectedExam.value?.id === exam.id) await openExam(exam, detailTab.value)
    },
  })
}

function formatDate(value) {
  if (!value) return '-'
  return new Intl.DateTimeFormat('vi-VN', { dateStyle: 'short', timeStyle: 'short' }).format(new Date(value))
}

function toInputDateTime(value) {
  if (!value) return ''
  const date = new Date(value)
  const offset = date.getTimezoneOffset()
  return new Date(date.getTime() - offset * 60000).toISOString().slice(0, 16)
}

function percent(result) {
  if (result.percent !== null && result.percent !== undefined) return `${Number(result.percent).toFixed(1)}%`
  if (!result.max_score) return '-'
  return `${((Number(result.score || 0) / Number(result.max_score)) * 100).toFixed(1)}%`
}

function courseLabel(course) {
  return course ? `${course.code || '#'} - ${course.title}` : 'Chưa gắn khóa học'
}

function questionBankLabel(bank) {
  return bank ? `${bank.code || '#'} - ${bank.name}` : 'Chưa chọn ngân hàng'
}

function blueprintLabel(blueprint) {
  return blueprint ? `${blueprint.code || '#'} - ${blueprint.name}` : 'Chưa chọn blueprint'
}

function userLabel(user) {
  return user ? `${user.full_name} (${user.email || user.code || 'user'})` : 'Không có thông tin người học'
}

function classLabel(section) {
  return section ? `${section.code || '#'} - ${section.name} · ${courseLabel(section.course)}` : 'Chưa chọn lớp'
}

onMounted(async () => {
  await Promise.all([load(), loadOptions()])
})
</script>

<template>
  <EraLmsLayout :session-user="sessionUser" @logout="$emit('logout')">
    <template #breadcrumb>Kiểm tra online / Quản lý</template>

    <section class="space-y-4">
      <div v-if="toast.show" class="rounded-md px-4 py-3 text-sm font-semibold" :class="toast.type === 'success' ? 'bg-emerald-50 text-emerald-700' : 'bg-red-50 text-red-700'">
        {{ toast.message }}
      </div>

      <div class="rounded-lg border border-slate-200 bg-white shadow-sm">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 px-5 py-4">
          <div>
            <h1 class="text-lg font-bold text-slate-950">Quản lý bài kiểm tra</h1>
            <p class="mt-1 text-sm text-slate-600">Tạo đề, chỉnh cấu hình, publish/close, gán học viên/lớp và xem attempts/results bằng API thật.</p>
          </div>
          <div class="flex flex-wrap gap-2">
            <button class="rounded-md border border-slate-300 px-3 py-2 text-sm font-semibold" @click="load">Làm mới</button>
            <button class="rounded-md bg-slate-950 px-4 py-2 text-sm font-semibold text-white" @click="creating = true">Tạo bài kiểm tra</button>
          </div>
        </div>

        <div class="grid gap-3 border-b border-slate-200 bg-slate-50 p-4 md:grid-cols-4">
          <div class="rounded-md border bg-white p-3"><div class="text-xs text-slate-500">Tổng bài</div><div class="mt-1 text-lg font-semibold">{{ stats.total }}</div></div>
          <div class="rounded-md border bg-white p-3"><div class="text-xs text-slate-500">Draft</div><div class="mt-1 text-lg font-semibold">{{ stats.draft }}</div></div>
          <div class="rounded-md border bg-white p-3"><div class="text-xs text-slate-500">Published</div><div class="mt-1 text-lg font-semibold">{{ stats.published }}</div></div>
          <div class="rounded-md border bg-white p-3"><div class="text-xs text-slate-500">Closed</div><div class="mt-1 text-lg font-semibold">{{ stats.closed }}</div></div>
        </div>

        <div class="grid gap-3 border-b border-slate-200 bg-slate-50 p-4 md:grid-cols-[1fr_180px_180px]">
          <input v-model="search" class="h-10 rounded-md border border-slate-300 bg-white px-3 text-sm" placeholder="Tìm mã hoặc tên bài kiểm tra" />
          <select v-model="typeFilter" class="h-10 rounded-md border border-slate-300 bg-white px-3 text-sm" @change="load">
            <option value="">Tất cả loại</option>
            <option value="quiz">Quiz</option>
            <option value="midterm">Midterm</option>
            <option value="final">Final</option>
            <option value="practice">Practice</option>
          </select>
          <select v-model="statusFilter" class="h-10 rounded-md border border-slate-300 bg-white px-3 text-sm" @change="load">
            <option value="">Tất cả trạng thái</option>
            <option value="draft">Draft</option>
            <option value="published">Published</option>
            <option value="closed">Closed</option>
          </select>
        </div>

        <div v-if="creating" class="border-b border-slate-200 p-4">
          <h2 class="text-sm font-bold text-slate-950">Tạo bài kiểm tra mới</h2>
          <div class="mt-3 grid gap-3 md:grid-cols-4">
            <input v-model="form.code" class="h-10 rounded-md border px-3 text-sm" placeholder="Mã bài" />
            <input v-model="form.title" class="h-10 rounded-md border px-3 text-sm md:col-span-2" placeholder="Tên bài kiểm tra" />
            <select v-model="form.exam_type" class="h-10 rounded-md border px-3 text-sm">
              <option value="quiz">Quiz</option>
              <option value="midterm">Midterm</option>
              <option value="final">Final</option>
              <option value="practice">Practice</option>
            </select>
            <input v-model.number="form.total_score" type="number" min="0" class="h-10 rounded-md border px-3 text-sm" placeholder="Tổng điểm" />
            <input v-model.number="form.pass_score" type="number" min="0" class="h-10 rounded-md border px-3 text-sm" placeholder="Điểm đạt" />
            <input v-model.number="form.duration_minutes" type="number" min="1" class="h-10 rounded-md border px-3 text-sm" placeholder="Phút làm bài" />
            <input v-model.number="form.max_attempts" type="number" min="1" class="h-10 rounded-md border px-3 text-sm" placeholder="Số lần làm" />
            <input v-model="form.open_at" type="datetime-local" class="h-10 rounded-md border px-3 text-sm" />
            <input v-model="form.close_at" type="datetime-local" class="h-10 rounded-md border px-3 text-sm" />
            <select v-model="form.course_id" class="h-10 rounded-md border px-3 text-sm">
              <option value="">Chọn khóa học</option>
              <option v-for="course in courseOptions" :key="course.id" :value="course.id">{{ courseLabel(course) }}</option>
            </select>
            <select v-model="form.question_bank_id" class="h-10 rounded-md border px-3 text-sm">
              <option value="">Chọn ngân hàng câu hỏi</option>
              <option v-for="bank in questionBankOptions" :key="bank.id" :value="bank.id">{{ questionBankLabel(bank) }}</option>
            </select>
            <select v-model="form.blueprint_id" class="h-10 rounded-md border px-3 text-sm md:col-span-2">
              <option value="">Chọn blueprint</option>
              <option v-for="blueprint in blueprintOptions" :key="blueprint.id" :value="blueprint.id">{{ blueprintLabel(blueprint) }}</option>
            </select>
            <textarea v-model="form.description" class="min-h-20 rounded-md border px-3 py-2 text-sm md:col-span-4" placeholder="Mô tả"></textarea>
            <label class="inline-flex items-center gap-2 text-sm"><input v-model="form.shuffle_questions" type="checkbox" /> Shuffle questions</label>
            <label class="inline-flex items-center gap-2 text-sm"><input v-model="form.shuffle_options" type="checkbox" /> Shuffle options</label>
            <label class="inline-flex items-center gap-2 text-sm"><input v-model="form.show_correct_answers" type="checkbox" /> Hiện đáp án đúng</label>
          </div>
          <div class="mt-3 flex gap-2">
            <button class="rounded-md bg-blue-700 px-3 py-2 text-sm font-semibold text-white disabled:opacity-60" :disabled="loading['exam.create']" @click="createExam">Lưu bài kiểm tra</button>
            <button class="rounded-md border border-slate-300 px-3 py-2 text-sm font-semibold" @click="creating = false">Hủy</button>
          </div>
          <pre v-if="Object.keys(validationErrors).length" class="mt-3 whitespace-pre-wrap rounded-md bg-red-50 p-3 text-xs text-red-700">{{ validationErrors }}</pre>
        </div>

        <div class="overflow-auto">
          <table class="w-full text-left text-sm">
            <thead class="bg-slate-50 text-xs uppercase text-slate-500">
              <tr>
                <th class="px-4 py-3">Mã</th>
                <th class="px-4 py-3">Tên bài</th>
                <th class="px-4 py-3">Loại</th>
                <th class="px-4 py-3">Điểm</th>
                <th class="px-4 py-3">Thời gian</th>
                <th class="px-4 py-3">Trạng thái</th>
                <th class="px-4 py-3">Thao tác</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              <tr v-if="loadingPage"><td colspan="7" class="px-4 py-6 text-center text-slate-500">Đang tải bài kiểm tra...</td></tr>
              <tr v-for="exam in loadingPage ? [] : filteredExams" :key="exam.id" class="hover:bg-slate-50">
                <td class="px-4 py-3 font-semibold">{{ exam.code }}</td>
                <td class="px-4 py-3">
                  <button class="text-left font-semibold text-slate-900 hover:text-blue-700" @click="openExam(exam)">{{ exam.title }}</button>
                  <div class="text-xs text-slate-500">{{ courseLabel(exam.course) }} · {{ exam.delivery_mode || 'self_paced' }}</div>
                </td>
                <td class="px-4 py-3">{{ exam.exam_type }}</td>
                <td class="px-4 py-3">
                  <div>{{ exam.pass_score || '-' }} / {{ exam.total_score || 0 }}</div>
                  <div class="text-xs text-slate-500">{{ exam.questions_count || 0 }} câu · {{ exam.attempts_count || 0 }} attempts</div>
                </td>
                <td class="px-4 py-3">{{ exam.duration_minutes || '-' }} phút</td>
                <td class="px-4 py-3"><StatusBadge :status="exam.status" /></td>
                <td class="min-w-[560px] px-4 py-3">
                  <ActionBar :actions="rowActions" :loading-map="loading" @run="(action) => runRowAction(action, exam)" />
                </td>
              </tr>
              <tr v-if="!loadingPage && !filteredExams.length"><td colspan="7" class="px-4 py-8 text-center text-slate-500">Chưa có bài kiểm tra.</td></tr>
            </tbody>
          </table>
        </div>
      </div>
    </section>

    <div v-if="selectedExam" class="fixed inset-0 z-50 grid place-items-center bg-slate-950/40 p-4">
      <div class="max-h-[88vh] w-full max-w-6xl overflow-auto rounded-lg bg-white shadow-xl">
        <div class="sticky top-0 z-10 flex flex-wrap items-start justify-between gap-3 border-b bg-white px-5 py-4">
          <div>
            <h2 class="text-lg font-bold text-slate-950">{{ selectedExam.title }}</h2>
            <p class="mt-1 text-sm text-slate-500">#{{ selectedExam.id }} · {{ selectedExam.code }} · {{ selectedExam.exam_type }}</p>
          </div>
          <div class="flex flex-wrap gap-2">
            <button class="rounded-md border px-3 py-2 text-sm font-semibold" @click="openEdit(selectedExam); selectedExam = null">Sửa</button>
            <button class="rounded-md border px-3 py-2 text-sm font-semibold" @click="runRowAction(rowActions[3], selectedExam)">Publish</button>
            <button class="rounded-md border px-3 py-2 text-sm font-semibold" @click="runRowAction(rowActions[4], selectedExam)">Close</button>
            <button class="rounded-md border px-3 py-2 text-sm font-semibold" @click="selectedExam = null">Đóng</button>
          </div>
        </div>

        <div class="border-b px-5 py-3">
          <div class="flex flex-wrap gap-2">
            <button class="rounded-md px-3 py-2 text-sm font-semibold" :class="detailTab === 'overview' ? 'bg-slate-950 text-white' : 'border border-slate-300'" @click="detailTab = 'overview'">Tổng quan</button>
            <button class="rounded-md px-3 py-2 text-sm font-semibold" :class="detailTab === 'questions' ? 'bg-slate-950 text-white' : 'border border-slate-300'" @click="detailTab = 'questions'">Cấu trúc câu hỏi</button>
            <button class="rounded-md px-3 py-2 text-sm font-semibold" :class="detailTab === 'attempts' ? 'bg-slate-950 text-white' : 'border border-slate-300'" @click="detailTab = 'attempts'">Attempts</button>
            <button class="rounded-md px-3 py-2 text-sm font-semibold" :class="detailTab === 'results' ? 'bg-slate-950 text-white' : 'border border-slate-300'" @click="detailTab = 'results'">Results</button>
          </div>
        </div>

        <div v-if="detailTab === 'overview'" class="grid gap-4 p-5 lg:grid-cols-[1fr_320px]">
          <main class="rounded-lg border">
            <div class="border-b px-4 py-3"><h3 class="text-sm font-bold">Thông tin bài kiểm tra</h3></div>
            <div class="grid text-sm md:grid-cols-2">
              <div class="border-b px-4 py-3"><div class="text-xs uppercase text-slate-500">Mã</div><div class="mt-1 font-semibold">{{ selectedExam.code }}</div></div>
              <div class="border-b px-4 py-3"><div class="text-xs uppercase text-slate-500">Trạng thái</div><div class="mt-1"><StatusBadge :status="selectedExam.status" /></div></div>
              <div class="border-b px-4 py-3"><div class="text-xs uppercase text-slate-500">Tổng điểm</div><div class="mt-1">{{ selectedExam.total_score }}</div></div>
              <div class="border-b px-4 py-3"><div class="text-xs uppercase text-slate-500">Điểm đạt</div><div class="mt-1">{{ selectedExam.pass_score || '-' }}</div></div>
              <div class="border-b px-4 py-3"><div class="text-xs uppercase text-slate-500">Mở bài</div><div class="mt-1">{{ formatDate(selectedExam.open_at) }}</div></div>
              <div class="border-b px-4 py-3"><div class="text-xs uppercase text-slate-500">Đóng bài</div><div class="mt-1">{{ formatDate(selectedExam.close_at) }}</div></div>
              <div class="px-4 py-3 md:col-span-2"><div class="text-xs uppercase text-slate-500">Mô tả</div><div class="mt-1 whitespace-pre-wrap">{{ selectedExam.description || 'Chưa có mô tả.' }}</div></div>
            </div>
          </main>
          <aside class="space-y-4">
            <div class="rounded-lg border p-4 text-sm">
              <h3 class="font-bold">Cấu hình</h3>
              <div class="mt-3 space-y-2">
                <div class="flex justify-between"><span>Duration</span><span>{{ selectedExam.duration_minutes || '-' }} phút</span></div>
                <div class="flex justify-between"><span>Max attempts</span><span>{{ selectedExam.max_attempts }}</span></div>
                <div class="flex justify-between"><span>Shuffle questions</span><span>{{ selectedExam.shuffle_questions ? 'Có' : 'Không' }}</span></div>
                <div class="flex justify-between"><span>Shuffle options</span><span>{{ selectedExam.shuffle_options ? 'Có' : 'Không' }}</span></div>
                <div class="flex justify-between"><span>Show result</span><span>{{ selectedExam.show_result_mode }}</span></div>
              </div>
            </div>
            <div class="rounded-lg border p-4 text-sm">
              <h3 class="font-bold">Nguồn câu hỏi</h3>
              <div class="mt-3 space-y-2">
                <div class="grid gap-1"><span class="text-slate-500">Question bank</span><span class="font-semibold">{{ questionBankLabel(selectedExam.question_bank) }}</span></div>
                <div class="grid gap-1"><span class="text-slate-500">Blueprint</span><span class="font-semibold">{{ blueprintLabel(selectedExam.blueprint) }}</span></div>
                <div class="grid gap-1"><span class="text-slate-500">Course</span><span class="font-semibold">{{ courseLabel(selectedExam.course) }}</span></div>
              </div>
            </div>
          </aside>
        </div>

        <div v-if="detailTab === 'questions'" class="p-5">
          <div class="rounded-lg border">
            <div class="border-b px-4 py-3"><h3 class="text-sm font-bold">Cấu trúc đề</h3></div>
            <div class="grid gap-4 p-4 lg:grid-cols-2">
              <div>
                <h4 class="text-sm font-semibold">Sections</h4>
                <div class="mt-2 divide-y rounded-md border">
                  <div v-for="section in selectedExam.sections || []" :key="section.id" class="px-3 py-2 text-sm">
                    <div class="font-semibold">{{ section.title }}</div>
                    <div class="text-xs text-slate-500">{{ section.question_count }} câu · {{ section.score }} điểm</div>
                  </div>
                  <div v-if="!(selectedExam.sections || []).length" class="px-3 py-6 text-sm text-slate-500">Chưa build section.</div>
                </div>
              </div>
              <div>
                <h4 class="text-sm font-semibold">Questions</h4>
                <div class="mt-2 divide-y rounded-md border">
                  <div v-for="examQuestion in selectedExam.questions || []" :key="examQuestion.id" class="px-3 py-2 text-sm">
                    <div class="font-semibold">#{{ examQuestion.sort_order }} · {{ examQuestion.score }} điểm</div>
                    <div class="text-xs text-slate-500">{{ examQuestion.question?.title || `Question ${examQuestion.question_id}` }}</div>
                  </div>
                  <div v-if="!(selectedExam.questions || []).length" class="px-3 py-6 text-sm text-slate-500">Chưa có câu hỏi. Dùng Build nếu có blueprint.</div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <div v-if="detailTab === 'attempts'" class="p-5">
          <table class="w-full text-left text-sm">
            <thead class="bg-slate-50 text-xs uppercase text-slate-500"><tr><th class="px-4 py-3">User</th><th class="px-4 py-3">Attempt</th><th class="px-4 py-3">Status</th><th class="px-4 py-3">Score</th><th class="px-4 py-3">Started</th><th class="px-4 py-3">Submitted</th></tr></thead>
            <tbody class="divide-y">
              <tr v-for="attempt in selectedAttempts" :key="attempt.id"><td class="px-4 py-3">{{ userLabel(attempt.user) }}</td><td class="px-4 py-3">{{ attempt.attempt_no }}</td><td class="px-4 py-3">{{ attempt.status }}</td><td class="px-4 py-3">{{ attempt.score || '-' }} / {{ attempt.max_score || '-' }}</td><td class="px-4 py-3">{{ formatDate(attempt.started_at) }}</td><td class="px-4 py-3">{{ formatDate(attempt.submitted_at) }}</td></tr>
              <tr v-if="!selectedAttempts.length"><td colspan="6" class="px-4 py-8 text-center text-slate-500">Chưa có attempt.</td></tr>
            </tbody>
          </table>
        </div>

        <div v-if="detailTab === 'results'" class="p-5">
          <table class="w-full text-left text-sm">
            <thead class="bg-slate-50 text-xs uppercase text-slate-500"><tr><th class="px-4 py-3">User</th><th class="px-4 py-3">Score</th><th class="px-4 py-3">Percent</th><th class="px-4 py-3">Pass</th><th class="px-4 py-3">Published</th></tr></thead>
            <tbody class="divide-y">
              <tr v-for="result in selectedResults" :key="result.id"><td class="px-4 py-3">{{ userLabel(result.user) }}</td><td class="px-4 py-3">{{ result.score }} / {{ result.max_score }}</td><td class="px-4 py-3">{{ percent(result) }}</td><td class="px-4 py-3">{{ result.pass_status || '-' }}</td><td class="px-4 py-3">{{ result.published ? 'Có' : 'Không' }}</td></tr>
              <tr v-if="!selectedResults.length"><td colspan="5" class="px-4 py-8 text-center text-slate-500">Chưa có kết quả.</td></tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <div v-if="assigningExam" class="fixed inset-0 z-50 grid place-items-center bg-slate-950/40 p-4">
      <div class="w-full max-w-xl rounded-lg bg-white p-5 shadow-xl">
        <h2 class="text-base font-bold text-slate-950">{{ assignMode === 'user' ? 'Gán bài kiểm tra cho người học' : 'Gán bài kiểm tra cho lớp' }}</h2>
        <p class="mt-1 text-sm text-slate-500">{{ assigningExam.title }}</p>
        <div class="mt-4">
          <select v-if="assignMode === 'user'" v-model="assignForm.user_id" class="h-10 w-full rounded-md border border-slate-300 px-3 text-sm">
            <option value="">Chọn người học</option>
            <option v-for="user in userOptions" :key="user.id" :value="user.id">{{ userLabel(user) }}</option>
          </select>
          <select v-else v-model="assignForm.class_id" class="h-10 w-full rounded-md border border-slate-300 px-3 text-sm">
            <option value="">Chọn lớp</option>
            <option v-for="section in classOptions" :key="section.id" :value="section.id">{{ classLabel(section) }}</option>
          </select>
        </div>
        <div class="mt-4 flex justify-end gap-2">
          <button class="rounded-md border px-3 py-2 text-sm font-semibold" @click="assigningExam = null">Hủy</button>
          <button v-if="assignMode === 'user'" class="rounded-md bg-blue-700 px-3 py-2 text-sm font-semibold text-white disabled:opacity-60" :disabled="!assignForm.user_id" @click="assignUser">Gán người học</button>
          <button v-else class="rounded-md bg-blue-700 px-3 py-2 text-sm font-semibold text-white disabled:opacity-60" :disabled="!assignForm.class_id" @click="assignClass">Gán lớp</button>
        </div>
      </div>
    </div>

    <div v-if="editingExam" class="fixed inset-0 z-50 grid place-items-center bg-slate-950/40 p-4">
      <div class="max-h-[88vh] w-full max-w-3xl overflow-auto rounded-lg bg-white p-5 shadow-xl">
        <h2 class="text-base font-bold">Sửa bài kiểm tra</h2>
        <div class="mt-3 grid gap-3 md:grid-cols-4">
          <input v-model="editForm.code" class="h-10 rounded-md border px-3 text-sm" />
          <input v-model="editForm.title" class="h-10 rounded-md border px-3 text-sm md:col-span-2" />
          <select v-model="editForm.exam_type" class="h-10 rounded-md border px-3 text-sm"><option value="quiz">Quiz</option><option value="midterm">Midterm</option><option value="final">Final</option><option value="practice">Practice</option></select>
          <input v-model.number="editForm.total_score" type="number" class="h-10 rounded-md border px-3 text-sm" />
          <input v-model.number="editForm.pass_score" type="number" class="h-10 rounded-md border px-3 text-sm" />
          <input v-model.number="editForm.duration_minutes" type="number" class="h-10 rounded-md border px-3 text-sm" />
          <input v-model.number="editForm.max_attempts" type="number" class="h-10 rounded-md border px-3 text-sm" />
          <input v-model="editForm.open_at" type="datetime-local" class="h-10 rounded-md border px-3 text-sm" />
          <input v-model="editForm.close_at" type="datetime-local" class="h-10 rounded-md border px-3 text-sm" />
          <select v-model="editForm.status" class="h-10 rounded-md border px-3 text-sm"><option value="draft">Draft</option><option value="published">Published</option><option value="closed">Closed</option></select>
          <select v-model="editForm.show_result_mode" class="h-10 rounded-md border px-3 text-sm"><option value="after_submit">After submit</option><option value="after_close">After close</option><option value="never">Never</option></select>
          <select v-model="editForm.course_id" class="h-10 rounded-md border px-3 text-sm md:col-span-2">
            <option value="">Chưa gắn khóa học</option>
            <option v-for="course in courseOptions" :key="course.id" :value="course.id">{{ courseLabel(course) }}</option>
          </select>
          <select v-model="editForm.question_bank_id" class="h-10 rounded-md border px-3 text-sm">
            <option value="">Chưa chọn ngân hàng</option>
            <option v-for="bank in questionBankOptions" :key="bank.id" :value="bank.id">{{ questionBankLabel(bank) }}</option>
          </select>
          <select v-model="editForm.blueprint_id" class="h-10 rounded-md border px-3 text-sm">
            <option value="">Chưa chọn blueprint</option>
            <option v-for="blueprint in blueprintOptions" :key="blueprint.id" :value="blueprint.id">{{ blueprintLabel(blueprint) }}</option>
          </select>
          <textarea v-model="editForm.description" class="min-h-24 rounded-md border px-3 py-2 text-sm md:col-span-4"></textarea>
        </div>
        <div class="mt-4 flex justify-end gap-2">
          <button class="rounded-md border px-3 py-2 text-sm font-semibold" @click="editingExam = null">Hủy</button>
          <button class="rounded-md bg-blue-700 px-3 py-2 text-sm font-semibold text-white" @click="saveEdit">Lưu</button>
        </div>
      </div>
    </div>
  </EraLmsLayout>
</template>
