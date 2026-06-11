<script setup>
import { computed, onMounted, ref } from 'vue'
import EraLmsLayout from '@/Layouts/EraLmsLayout.vue'

const props = defineProps({
  apiHeaders: { type: Object, required: true },
  sessionUser: { type: Object, default: null },
})

const loading = ref(false)
const detailLoading = ref(false)
const error = ref('')
const exams = ref([])
const classRows = ref([])
const candidates = ref([])
const attemptDetail = ref(null)
const selectedExamId = ref('')
const selectedClassId = ref('')
const selectedRow = ref(null)
const search = ref('')
const status = ref('')

const isLearner = computed(() => props.sessionUser?.user_type === 'student')
const filteredCandidates = computed(() => {
  const term = search.value.trim().toLowerCase()
  return candidates.value.filter((item) => {
    const text = [item.user?.full_name, item.user?.email, item.user?.code].filter(Boolean).join(' ').toLowerCase()
    const state = item.pass_status || item.status
    return (!term || text.includes(term)) && (!status.value || state === status.value)
  })
})

async function load() {
  loading.value = true
  error.value = ''
  try {
    const [examData, rowData] = await Promise.all([
      isLearner.value ? Promise.resolve([]) : getJson('/api/v1/exams?per_page=100'),
      getJson('/api/v1/exam-results/classes'),
    ])
    exams.value = isLearner.value ? [] : dataList(examData)
    classRows.value = Array.isArray(rowData) ? rowData : dataList(rowData)
    if (isLearner.value && classRows.value.length) {
      await openClass(classRows.value[0])
    }
  } catch (exception) {
    error.value = exception.message || 'Không tải được kết quả thi.'
  } finally {
    loading.value = false
  }
}

async function applyFilters() {
  loading.value = true
  error.value = ''
  selectedRow.value = null
  candidates.value = []
  attemptDetail.value = null
  const params = new URLSearchParams()
  if (selectedExamId.value) params.set('exam_id', selectedExamId.value)
  if (selectedClassId.value) params.set('class_id', selectedClassId.value)
  try {
    const data = await getJson(`/api/v1/exam-results/classes?${params.toString()}`)
    classRows.value = Array.isArray(data) ? data : dataList(data)
  } catch (exception) {
    error.value = exception.message || 'Không lọc được kết quả thi.'
  } finally {
    loading.value = false
  }
}

async function openClass(row) {
  if (!row?.class?.id || !row?.exam?.id) return
  selectedRow.value = row
  attemptDetail.value = null
  detailLoading.value = true
  error.value = ''
  try {
    candidates.value = await getJson(`/api/v1/exam-results/classes/${row.class.id}/exams/${row.exam.id}/candidates`)
    if (isLearner.value && candidates.value.length) {
      const mine = candidates.value.find((item) => item.user?.id === props.sessionUser?.id) || candidates.value[0]
      await openAttempt(mine)
    }
  } catch (exception) {
    error.value = exception.message || 'Không tải được danh sách thí sinh.'
  } finally {
    detailLoading.value = false
  }
}

async function openAttempt(candidate) {
  if (!candidate?.attempt_id) return
  detailLoading.value = true
  error.value = ''
  try {
    attemptDetail.value = await getJson(`/api/v1/exam-results/attempts/${candidate.attempt_id}/detail`)
  } catch (exception) {
    error.value = exception.message || 'Không tải được chi tiết bài làm.'
  } finally {
    detailLoading.value = false
  }
}

async function getJson(url) {
  const response = await fetch(url, { headers: props.apiHeaders })
  const data = await response.json().catch(() => ({}))
  if (!response.ok) throw new Error(data.message || `HTTP ${response.status}`)
  return data.data ?? data
}

function dataList(data) {
  if (Array.isArray(data)) return data
  if (Array.isArray(data?.data)) return data.data
  return []
}

function fmt(value, suffix = '') {
  if (value === null || value === undefined || value === '') return '-'
  return `${value}${suffix}`
}

function dateTime(value) {
  if (!value) return '-'
  return new Date(value).toLocaleString('vi-VN')
}

function badgeClass(value) {
  if (['passed', 'graded', 'submitted'].includes(value)) return 'bg-emerald-50 text-emerald-700'
  if (['failed', 'not_started'].includes(value)) return 'bg-red-50 text-red-700'
  return 'bg-slate-100 text-slate-700'
}

onMounted(load)
</script>

<template>
  <EraLmsLayout :session-user="sessionUser">
    <template #breadcrumb>{{ isLearner ? 'Người học / Kết quả thi' : 'Kiểm tra online / Kết quả' }}</template>

    <section class="space-y-5">
      <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
        <div class="flex flex-wrap items-start justify-between gap-4">
          <div>
            <h1 class="text-xl font-bold text-slate-950">{{ isLearner ? 'Kết quả thi của tôi' : 'Kết quả bài kiểm tra theo lớp' }}</h1>
            <p class="mt-1 text-sm text-slate-600">{{ isLearner ? 'Xem lại điểm, trạng thái và chi tiết từng câu đã làm.' : 'Xem tổng hợp từng lớp, từng thí sinh và chi tiết từng attempt.' }}</p>
          </div>
          <button class="rounded-md bg-slate-950 px-4 py-2 text-sm font-semibold text-white disabled:opacity-60" :disabled="loading" @click="isLearner ? load() : applyFilters()">
            {{ loading ? 'Đang tải...' : 'Tải lại dữ liệu' }}
          </button>
        </div>

        <div v-if="!isLearner" class="mt-5 grid gap-3 md:grid-cols-4">
          <select v-model="selectedExamId" class="rounded-md border border-slate-300 px-3 py-2 text-sm">
            <option value="">Tất cả bài thi</option>
            <option v-for="exam in exams" :key="exam.id" :value="exam.id">{{ exam.code }} - {{ exam.title }}</option>
          </select>
          <input v-model="selectedClassId" class="rounded-md border border-slate-300 px-3 py-2 text-sm" placeholder="Nhập class_id" />
          <input v-model="search" class="rounded-md border border-slate-300 px-3 py-2 text-sm" placeholder="Tìm thí sinh" />
          <select v-model="status" class="rounded-md border border-slate-300 px-3 py-2 text-sm">
            <option value="">Tất cả trạng thái</option>
            <option value="passed">Đạt</option>
            <option value="failed">Chưa đạt</option>
            <option value="not_started">Chưa làm</option>
            <option value="submitted">Đã nộp</option>
            <option value="graded">Đã chấm</option>
          </select>
        </div>
      </div>

      <div v-if="error" class="rounded-md border border-red-200 bg-red-50 p-4 text-sm font-semibold text-red-700">{{ error }}</div>

      <section class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_420px]">
        <div class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
          <div class="border-b border-slate-200 px-5 py-4 text-sm font-bold text-slate-950">{{ isLearner ? 'Bài thi đã làm' : 'Tổng hợp theo lớp' }}</div>
          <div class="overflow-auto">
            <table class="w-full text-left text-sm">
              <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                <tr>
                  <th class="px-4 py-3">Lớp</th>
                  <th class="px-4 py-3">Bài thi</th>
                  <th class="px-4 py-3">Thí sinh</th>
                  <th class="px-4 py-3">Đã làm</th>
                  <th class="px-4 py-3">Điểm TB</th>
                  <th class="px-4 py-3">Tỉ lệ đạt</th>
                  <th class="px-4 py-3">Chi tiết</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-100">
                <tr v-for="row in classRows" :key="`${row.class?.id}-${row.exam?.id}`" class="hover:bg-slate-50">
                  <td class="px-4 py-3"><div class="font-semibold">{{ row.class?.name || '-' }}</div><div class="text-xs text-slate-500">{{ row.class?.code || row.class?.course?.title }}</div></td>
                  <td class="px-4 py-3">{{ row.exam?.title || '-' }}</td>
                  <td class="px-4 py-3">{{ row.candidates_count }}</td>
                  <td class="px-4 py-3">{{ row.submitted_count }} / {{ row.missing_count }} chưa làm</td>
                  <td class="px-4 py-3">{{ fmt(row.average_score) }}</td>
                  <td class="px-4 py-3">{{ fmt(row.pass_rate, '%') }}</td>
                  <td class="px-4 py-3"><button class="font-semibold text-blue-700" @click="openClass(row)">Xem</button></td>
                </tr>
                <tr v-if="!loading && !classRows.length"><td colspan="7" class="px-4 py-8 text-center text-slate-500">Chưa có dữ liệu kết quả thi.</td></tr>
              </tbody>
            </table>
          </div>
        </div>

        <aside class="rounded-lg border border-slate-200 bg-white shadow-sm">
          <div class="border-b border-slate-200 px-5 py-4">
            <div class="text-sm font-bold text-slate-950">{{ selectedRow?.class?.name || 'Chọn bài thi' }}</div>
            <div class="mt-1 text-xs text-slate-500">{{ selectedRow?.exam?.title || 'Danh sách thí sinh sẽ hiển thị tại đây.' }}</div>
          </div>
          <div class="max-h-[32rem] overflow-auto divide-y divide-slate-100">
            <button v-for="candidate in filteredCandidates" :key="candidate.user?.id" class="block w-full px-5 py-3 text-left hover:bg-blue-50" @click="openAttempt(candidate)">
              <div class="flex items-center justify-between gap-3">
                <div class="min-w-0">
                  <div class="truncate text-sm font-semibold text-slate-950">{{ candidate.user?.full_name || candidate.user?.email || 'Thí sinh' }}</div>
                  <div class="truncate text-xs text-slate-500">{{ candidate.user?.email || candidate.user?.code }}</div>
                </div>
                <span class="shrink-0 rounded px-2 py-1 text-xs font-semibold" :class="badgeClass(candidate.pass_status || candidate.status)">{{ candidate.pass_status || candidate.status }}</span>
              </div>
              <div class="mt-2 text-xs text-slate-600">Điểm {{ fmt(candidate.score) }}/{{ fmt(candidate.max_score) }} · {{ candidate.attempts_count }} lần · {{ dateTime(candidate.submitted_at) }}</div>
            </button>
            <div v-if="selectedRow && !detailLoading && !filteredCandidates.length" class="p-5 text-sm text-slate-500">Không có thí sinh phù hợp bộ lọc.</div>
          </div>
        </aside>
      </section>

      <section v-if="attemptDetail" class="rounded-lg border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-200 px-5 py-4">
          <h2 class="text-sm font-bold text-slate-950">Chi tiết bài làm: {{ attemptDetail.attempt?.user?.full_name }}</h2>
          <p class="mt-1 text-xs text-slate-500">Attempt #{{ attemptDetail.attempt?.attempt_no }} · {{ fmt(attemptDetail.attempt?.score) }}/{{ fmt(attemptDetail.attempt?.max_score) }} · {{ dateTime(attemptDetail.attempt?.submitted_at) }}</p>
        </div>
        <div class="divide-y divide-slate-100">
          <article v-for="question in attemptDetail.questions" :key="question.id" class="p-5">
            <div class="flex flex-wrap items-center justify-between gap-3">
              <h3 class="text-sm font-bold text-slate-950">Câu {{ question.display_order }}: {{ question.question?.title || question.question?.code }}</h3>
              <span class="rounded px-2 py-1 text-xs font-semibold" :class="question.is_correct ? 'bg-emerald-50 text-emerald-700' : 'bg-red-50 text-red-700'">{{ question.is_correct ? 'Đúng' : 'Sai/Chưa chấm' }} · {{ fmt(question.earned_score) }}/{{ fmt(question.score) }}</span>
            </div>
            <p class="mt-2 text-sm leading-6 text-slate-700" v-html="question.question?.stem || ''"></p>
            <div class="mt-3 grid gap-3 md:grid-cols-2">
              <pre class="overflow-auto rounded-md bg-slate-50 p-3 text-xs text-slate-700">{{ question.answer || 'Chưa có đáp án' }}</pre>
              <pre class="overflow-auto rounded-md bg-slate-50 p-3 text-xs text-slate-700">{{ question.options || [] }}</pre>
            </div>
          </article>
        </div>
      </section>
    </section>
  </EraLmsLayout>
</template>
