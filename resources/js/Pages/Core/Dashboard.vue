<script setup>
import { computed, onMounted, ref } from 'vue'
import EraLmsLayout from '@/Layouts/EraLmsLayout.vue'

const props = defineProps({
  sessionUser: { type: Object, default: null },
  apiHeaders: { type: Object, default: () => ({}) },
})

defineEmits(['logout'])

const loading = ref(false)
const errors = ref([])
const activeTab = ref('learning')
const core = ref(null)
const datasets = ref({
  courses: [],
  repository: [],
  enrollments: [],
  sections: [],
  videos: [],
  questionBanks: [],
  exams: [],
  assignments: [],
  gradebooks: [],
  attendance: [],
  surveys: [],
  campaigns: [],
  alerts: [],
  risks: [],
  credentials: [],
  syncJobs: [],
})

const endpoints = [
  ['core', '/api/v1/core/dashboard'],
  ['courses', '/api/v1/courses?per_page=8'],
  ['repository', '/api/v1/repository/items?per_page=8'],
  ['enrollments', '/api/v1/enrollment/records?per_page=8'],
  ['sections', '/api/v1/enrollment/sections?per_page=8'],
  ['videos', '/api/v1/videos?per_page=8'],
  ['questionBanks', '/api/v1/question-banks?per_page=8'],
  ['exams', '/api/v1/exams?per_page=8'],
  ['assignments', '/api/v1/assignments?per_page=8'],
  ['gradebooks', '/api/v1/gradebooks?per_page=8'],
  ['attendance', '/api/v1/attendance-sessions?per_page=8'],
  ['surveys', '/api/v1/surveys/forms?per_page=8'],
  ['campaigns', '/api/v1/surveys/campaigns?per_page=8'],
  ['alerts', '/api/v1/analytics/alerts?per_page=8'],
  ['risks', '/api/v1/analytics/risks?per_page=8'],
  ['credentials', '/api/v1/credentials/certificates?per_page=8'],
  ['syncJobs', '/api/v1/integrations/sync-jobs?per_page=8'],
]

const tabs = [
  { key: 'learning', label: 'Learning Ops' },
  { key: 'assessment', label: 'Assessment' },
  { key: 'learner', label: 'Learners' },
  { key: 'quality', label: 'Quality' },
  { key: 'platform', label: 'Platform' },
]

const flowRows = computed(() => [
  {
    key: 'foundation',
    label: 'Thiết lập nền',
    accent: 'bg-slate-950',
    nodes: [
      node('Tenant', '/', core.value?.tenant?.name || 'VABIS', 'active'),
      node('Người dùng', '/settings/users', count(core.value?.students_count) + ' học viên', health(core.value?.locked_users_count, 0)),
      node('Ghi danh', '/enrollment', `${datasets.value.enrollments.length} bản ghi`, datasets.value.enrollments.length ? 'active' : 'empty'),
      node('Lớp học', '/enrollment', `${datasets.value.sections.length} lớp`, datasets.value.sections.length ? 'active' : 'empty'),
    ],
  },
  {
    key: 'content',
    label: 'Nội dung học tập',
    accent: 'bg-blue-700',
    nodes: [
      node('Khóa học', '/courses', `${datasets.value.courses.length} khóa`, datasets.value.courses.length ? 'active' : 'empty'),
      node('Studio', '/courses/studio', 'Soạn bài', 'ready'),
      node('Kho học liệu', '/repository', `${datasets.value.repository.length} item`, datasets.value.repository.length ? 'active' : 'empty'),
      node('Video', '/videos', `${datasets.value.videos.length} video`, datasets.value.videos.length ? 'active' : 'empty'),
      node('Learning path', '/learning-path', 'Rule & progress', 'ready'),
    ],
  },
  {
    key: 'assessment',
    label: 'Đánh giá',
    accent: 'bg-violet-700',
    nodes: [
      node('Ngân hàng câu hỏi', '/question-banks', `${datasets.value.questionBanks.length} banks`, datasets.value.questionBanks.length ? 'active' : 'empty'),
      node('Exam', '/exams', `${datasets.value.exams.length} đề`, datasets.value.exams.length ? 'active' : 'empty'),
      node('Assignment', '/assignments', `${datasets.value.assignments.length} bài`, datasets.value.assignments.length ? 'active' : 'empty'),
      node('Gradebook', '/gradebook', `${datasets.value.gradebooks.length} sổ`, datasets.value.gradebooks.length ? 'active' : 'empty'),
      node('Attendance', '/attendance', `${datasets.value.attendance.length} phiên`, datasets.value.attendance.length ? 'active' : 'empty'),
    ],
  },
  {
    key: 'quality',
    label: 'Chất lượng & năng lực',
    accent: 'bg-emerald-700',
    nodes: [
      node('Survey', '/surveys', `${datasets.value.surveys.length} form`, datasets.value.surveys.length ? 'active' : 'empty'),
      node('Campaign', '/surveys', `${datasets.value.campaigns.length} đợt`, datasets.value.campaigns.length ? 'active' : 'empty'),
      node('OBE', '/obe', 'Outcome matrix', 'ready'),
      node('Credential', '/credentials', `${datasets.value.credentials.length} mẫu`, datasets.value.credentials.length ? 'active' : 'empty'),
      node('Career', '/career', 'Portfolio', 'ready'),
    ],
  },
  {
    key: 'platform',
    label: 'Tích hợp & vận hành',
    accent: 'bg-amber-600',
    nodes: [
      node('Analytics', '/analytics', `${datasets.value.alerts.length} cảnh báo`, datasets.value.alerts.length ? 'warning' : 'active'),
      node('SIS', '/sis', `${datasets.value.syncJobs.length} jobs`, datasets.value.syncJobs.length ? 'active' : 'empty'),
      node('Standards', '/standards/scorm', 'SCORM/LTI/xAPI', 'ready'),
      node('AI', '/ai', 'Trợ giảng', 'ready'),
      node('System check', '/admin/lms/system-check', `${errors.value.length} API lỗi`, errors.value.length ? 'warning' : 'active'),
    ],
  },
])

const kpis = computed(() => [
  { label: 'Người học', value: count(core.value?.students_count), detail: `${count(core.value?.teachers_staff_count)} GV/cán bộ`, href: '/settings/users' },
  { label: 'Khóa học', value: count(datasets.value.courses.length), detail: `${publishedCount(datasets.value.courses)} published`, href: '/courses' },
  { label: 'Đánh giá', value: count(datasets.value.exams.length + datasets.value.assignments.length), detail: 'Exam + Assignment', href: '/exams' },
  { label: 'Cảnh báo', value: count(datasets.value.alerts.length + datasets.value.risks.length), detail: 'Risk/alert cần theo dõi', href: '/analytics' },
])

const learnerAssigned = computed(() => datasets.value.enrollments.slice(0, 4))
const learnerAvailable = computed(() => datasets.value.sections.filter((item) => ['open', 'published', 'active'].includes(item.status || item.section_type)).slice(0, 4))
const learnerInProgress = computed(() => learnerAssigned.value.filter((item) => ['active', 'in_progress', 'enrolled'].includes(item.status)).slice(0, 4))
const learnerCompleted = computed(() => learnerAssigned.value.filter((item) => ['completed', 'passed'].includes(item.status)).slice(0, 4))
const learnerResults = computed(() => datasets.value.exams.map((item) => ({
  id: item.id,
  title: item.title,
  course: item.course?.title || 'Chưa gắn khóa học',
  detail: `${item.attempts_count || 0} lượt làm`,
})).slice(0, 4))

const tabRows = computed(() => ({
  learning: [
    ...datasets.value.courses.map((item) => row(item.code, item.title, item.status, item.owner?.full_name || item.academic_unit?.name, '/courses')),
    ...datasets.value.repository.map((item) => row(item.item_type, item.title, item.status, item.owner?.full_name, '/repository')),
    ...datasets.value.videos.map((item) => row(item.status || item.item_type, item.title || item.filename, item.processing_status || item.status, item.duration_seconds ? `${Math.round(item.duration_seconds / 60)} phút` : item.mime_type, '/videos')),
  ],
  assessment: [
    ...datasets.value.exams.map((item) => row(item.code, item.title, item.status, `${item.questions_count || 0} câu · ${item.attempts_count || 0} attempts`, '/exams')),
    ...datasets.value.assignments.map((item) => row(item.assignment_type, item.title, item.status, item.course?.title || 'Chưa gắn khóa học', '/assignments')),
    ...datasets.value.gradebooks.map((item) => row(item.code || `GB-${item.id}`, item.title || item.name, item.status, `${item.items_count || 0} items`, '/gradebook')),
  ],
  learner: [
    ...datasets.value.enrollments.map((item) => row(item.learner?.code || `#${item.id}`, item.learner?.full_name || 'Learner', item.status, item.course?.title || item.class_section?.name, '/enrollment')),
    ...datasets.value.sections.map((item) => row(item.code, item.name, item.section_type, item.course?.title, '/enrollment')),
    ...datasets.value.attendance.map((item) => row(item.code || `ATT-${item.id}`, item.title || item.session_name || 'Attendance session', item.status, item.checkin_method || item.mode, '/attendance')),
  ],
  quality: [
    ...datasets.value.surveys.map((item) => row(item.code, item.title, item.status, item.survey_type, '/surveys')),
    ...datasets.value.campaigns.map((item) => row(item.code || `CAM-${item.id}`, item.title || item.name, item.status, item.target_type, '/surveys')),
    ...datasets.value.risks.map((item) => row(item.risk_level, item.learner?.full_name || 'Learner', `${item.risk_score || 0}`, item.course?.title, '/analytics')),
  ],
  platform: [
    ...datasets.value.alerts.map((item) => row(item.severity, item.title || item.alert_type, item.status, item.learner?.full_name || item.course?.title, '/analytics')),
    ...datasets.value.syncJobs.map((item) => row(item.job_type || `JOB-${item.id}`, item.entity_type || item.system_code || 'Sync job', item.status, syncJobDetail(item), '/sis/sync-jobs')),
    ...errors.value.map((item) => row('API', item.key, 'error', item.message, '/admin/lms/system-check')),
  ],
}))

async function loadDashboard() {
  loading.value = true
  errors.value = []

  const results = await Promise.allSettled(endpoints.map(([key, url]) => fetchJson(key, url)))
  for (const result of results) {
    if (result.status === 'fulfilled') {
      const { key, data } = result.value
      if (key === 'core') core.value = data
      else datasets.value[key] = dataList(data)
    } else {
      errors.value.push(result.reason)
    }
  }

  loading.value = false
}

async function selfEnroll(section) {
  if (!section?.id) return
  await fetch(`/api/v1/enrollment/sections/${section.id}/self-enroll`, {
    method: 'POST',
    headers: props.apiHeaders,
    body: JSON.stringify({}),
  })
  await loadDashboard()
}

async function fetchJson(key, url) {
  const response = await fetch(url, { headers: props.apiHeaders })
  const data = await response.json().catch(() => ({}))
  if (!response.ok) throw { key, message: data.message || `HTTP ${response.status}` }
  return { key, data: data.data ?? data }
}

function dataList(data) {
  if (Array.isArray(data)) return data
  if (Array.isArray(data?.data)) return data.data
  return []
}

function node(label, href, metric, state) {
  return { label, href, metric, state }
}

function row(key, title, status, detail, href) {
  return { key: key || '-', title: title || '-', status: status || '-', detail: detail || '-', href }
}

function count(value) {
  return new Intl.NumberFormat('vi-VN').format(Number(value || 0))
}

function publishedCount(items) {
  return items.filter((item) => ['published', 'approved', 'active'].includes(item.status)).length
}

function syncJobDetail(item) {
  const total = Number(item.total_count || 0)
  const success = Number(item.success_count || 0)
  const failed = Number(item.failed_count || 0)
  if (total > 0) return `${count(success)}/${count(total)} thành công · ${count(failed)} lỗi`
  return item.started_at || item.finished_at || '-'
}

function health(value, threshold) {
  return Number(value || 0) > threshold ? 'warning' : 'active'
}

function stateClass(state) {
  return {
    active: 'border-emerald-200 bg-emerald-50 text-emerald-800',
    ready: 'border-blue-200 bg-blue-50 text-blue-800',
    warning: 'border-amber-200 bg-amber-50 text-amber-900',
    empty: 'border-slate-200 bg-slate-50 text-slate-600',
  }[state] || 'border-slate-200 bg-white text-slate-700'
}

onMounted(loadDashboard)
</script>

<template>
  <EraLmsLayout :session-user="sessionUser" @logout="$emit('logout')">
    <template #breadcrumb>Tổng quan</template>

    <section class="space-y-5">
      <div class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
        <div class="grid gap-0 xl:grid-cols-[1fr_380px]">
          <div class="p-6">
            <div class="text-xs font-bold uppercase tracking-wide text-blue-700">EraLMS Operations Console</div>
            <h1 class="mt-2 text-2xl font-bold text-slate-950">Luồng vận hành LMS toàn hệ thống</h1>
            <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-600">
              Dashboard này load dữ liệu từ các module chính và trình bày theo flow: thiết lập, nội dung, đánh giá, chất lượng, tích hợp và vận hành.
            </p>
            <div class="mt-5 flex flex-wrap gap-2">
              <button class="rounded-md bg-slate-950 px-4 py-2 text-sm font-semibold text-white disabled:opacity-60" :disabled="loading" @click="loadDashboard">
                {{ loading ? 'Đang tải...' : 'Tải lại dữ liệu' }}
              </button>
              <a href="/admin/lms/system-check" class="rounded-md border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700">System check</a>
              <a href="/moodle-parity" class="rounded-md border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700">Moodle parity</a>
            </div>
          </div>
          <div class="border-t border-slate-200 bg-slate-50 p-6 xl:border-l xl:border-t-0">
            <div class="text-sm font-bold text-slate-950">Tenant hiện tại</div>
            <div class="mt-4 space-y-3 text-sm">
              <div class="flex justify-between gap-3"><span class="text-slate-500">Tên</span><span class="font-semibold text-slate-950">{{ core?.tenant?.name || 'VABIS LMS' }}</span></div>
              <div class="flex justify-between gap-3"><span class="text-slate-500">Campus</span><span class="font-semibold">{{ count(core?.campuses_count) }}</span></div>
              <div class="flex justify-between gap-3"><span class="text-slate-500">Khoa/Bộ môn</span><span class="font-semibold">{{ count(core?.academic_units_count) }}</span></div>
              <div class="flex justify-between gap-3"><span class="text-slate-500">API lỗi</span><span class="font-semibold" :class="errors.length ? 'text-amber-700' : 'text-emerald-700'">{{ errors.length }}</span></div>
            </div>
          </div>
        </div>
      </div>

      <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
        <a v-for="item in kpis" :key="item.label" :href="item.href" class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm hover:border-blue-300 hover:bg-blue-50/40">
          <div class="text-xs font-semibold uppercase text-slate-500">{{ item.label }}</div>
          <div class="mt-3 text-3xl font-bold text-slate-950">{{ item.value }}</div>
          <div class="mt-2 text-sm text-slate-600">{{ item.detail }}</div>
        </a>
      </div>

      <section class="rounded-lg border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-200 px-5 py-4">
          <h2 class="text-sm font-bold text-slate-950">Dashboard learner</h2>
          <p class="mt-1 text-xs text-slate-500">Khóa được gán, khóa có thể đăng ký, tiến độ học và kết quả thi gần đây.</p>
        </div>
        <div class="grid gap-4 p-5 xl:grid-cols-5">
          <div class="rounded-md border border-slate-200 p-4">
            <h3 class="text-sm font-bold text-slate-950">Khóa được gán</h3>
            <div class="mt-3 space-y-3">
              <div v-for="item in learnerAssigned" :key="item.id" class="text-sm">
                <div class="font-semibold text-slate-900">{{ item.course?.title || item.class_section?.name || 'Khóa học' }}</div>
                <div class="text-xs text-slate-500">{{ item.status }}</div>
                <a :href="`/courses/learn?course_id=${item.course?.id || item.course_id}`" class="mt-1 inline-block text-xs font-semibold text-blue-700">Tiếp tục học</a>
              </div>
              <div v-if="!learnerAssigned.length" class="text-sm text-slate-500">Chưa có khóa được gán.</div>
            </div>
          </div>
          <div class="rounded-md border border-slate-200 p-4">
            <h3 class="text-sm font-bold text-slate-950">Có thể đăng ký</h3>
            <div class="mt-3 space-y-3">
              <div v-for="item in learnerAvailable" :key="item.id" class="text-sm">
                <div class="font-semibold text-slate-900">{{ item.course?.title || item.name }}</div>
                <div class="text-xs text-slate-500">{{ item.name || item.code }}</div>
                <button class="mt-1 text-xs font-semibold text-blue-700" @click="selfEnroll(item)">Đăng ký</button>
              </div>
              <div v-if="!learnerAvailable.length" class="text-sm text-slate-500">Chưa có lớp mở đăng ký.</div>
            </div>
          </div>
          <div class="rounded-md border border-slate-200 p-4">
            <h3 class="text-sm font-bold text-slate-950">Đang học</h3>
            <div class="mt-3 space-y-3">
              <div v-for="item in learnerInProgress" :key="item.id" class="text-sm">
                <div class="font-semibold text-slate-900">{{ item.course?.title || item.class_section?.name || 'Khóa học' }}</div>
                <a :href="`/courses/learn?course_id=${item.course?.id || item.course_id}`" class="mt-1 inline-block text-xs font-semibold text-blue-700">Học tiếp</a>
              </div>
              <div v-if="!learnerInProgress.length" class="text-sm text-slate-500">Chưa có khóa đang học.</div>
            </div>
          </div>
          <div class="rounded-md border border-slate-200 p-4">
            <h3 class="text-sm font-bold text-slate-950">Đã hoàn thành</h3>
            <div class="mt-3 space-y-3">
              <div v-for="item in learnerCompleted" :key="item.id" class="text-sm">
                <div class="font-semibold text-slate-900">{{ item.course?.title || item.class_section?.name || 'Khóa học' }}</div>
                <a :href="`/courses/learn?course_id=${item.course?.id || item.course_id}`" class="mt-1 inline-block text-xs font-semibold text-blue-700">Học lại</a>
              </div>
              <div v-if="!learnerCompleted.length" class="text-sm text-slate-500">Chưa có khóa hoàn thành.</div>
            </div>
          </div>
          <div class="rounded-md border border-slate-200 p-4">
            <h3 class="text-sm font-bold text-slate-950">Kết quả thi</h3>
            <div class="mt-3 space-y-3">
              <div v-for="item in learnerResults" :key="item.id" class="text-sm">
                <div class="font-semibold text-slate-900">{{ item.title }}</div>
                <div class="text-xs text-slate-500">{{ item.course }} · {{ item.detail }}</div>
                <a href="/exams/results" class="mt-1 inline-block text-xs font-semibold text-blue-700">Xem kết quả</a>
              </div>
              <div v-if="!learnerResults.length" class="text-sm text-slate-500">Chưa có kết quả thi.</div>
            </div>
          </div>
        </div>
      </section>

      <section class="rounded-lg border border-slate-200 bg-white shadow-sm">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 px-5 py-4">
          <div>
            <h2 class="text-sm font-bold text-slate-950">Flow chức năng</h2>
            <p class="mt-1 text-xs text-slate-500">Các node có thể click để mở module tương ứng.</p>
          </div>
          <div class="text-xs text-slate-500">{{ flowRows.length }} luồng · {{ flowRows.reduce((sum, row) => sum + row.nodes.length, 0) }} chức năng chính</div>
        </div>
        <div class="divide-y divide-slate-100">
          <div v-for="flow in flowRows" :key="flow.key" class="grid gap-4 px-5 py-4 xl:grid-cols-[190px_1fr]">
            <div class="flex items-center gap-3">
              <span class="h-3 w-3 rounded-full" :class="flow.accent"></span>
              <div class="text-sm font-bold text-slate-950">{{ flow.label }}</div>
            </div>
            <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-5">
              <a v-for="item in flow.nodes" :key="`${flow.key}-${item.label}`" :href="item.href" class="rounded-lg border px-3 py-3 text-sm hover:shadow-sm" :class="stateClass(item.state)">
                <div class="font-bold">{{ item.label }}</div>
                <div class="mt-2 text-xs">{{ item.metric }}</div>
              </a>
            </div>
          </div>
        </div>
      </section>

      <section class="rounded-lg border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-200 px-5 py-4">
          <div class="flex flex-wrap gap-2">
            <button v-for="tab in tabs" :key="tab.key" class="rounded-md px-3 py-2 text-sm font-semibold" :class="activeTab === tab.key ? 'bg-slate-950 text-white' : 'border border-slate-300 text-slate-700'" @click="activeTab = tab.key">
              {{ tab.label }}
            </button>
          </div>
        </div>
        <div class="overflow-auto">
          <table class="w-full text-left text-sm">
            <thead class="bg-slate-50 text-xs uppercase text-slate-500">
              <tr>
                <th class="px-4 py-3">Mã/Loại</th>
                <th class="px-4 py-3">Tên dữ liệu</th>
                <th class="px-4 py-3">Trạng thái</th>
                <th class="px-4 py-3">Liên quan</th>
                <th class="px-4 py-3">Mở</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              <tr v-for="item in tabRows[activeTab]" :key="`${activeTab}-${item.key}-${item.title}`" class="hover:bg-slate-50">
                <td class="px-4 py-3 font-semibold">{{ item.key }}</td>
                <td class="px-4 py-3">{{ item.title }}</td>
                <td class="px-4 py-3"><span class="rounded bg-slate-100 px-2 py-1 text-xs">{{ item.status }}</span></td>
                <td class="px-4 py-3 text-slate-600">{{ item.detail }}</td>
                <td class="px-4 py-3"><a :href="item.href" class="font-semibold text-blue-700">Mở module</a></td>
              </tr>
              <tr v-if="!tabRows[activeTab].length">
                <td colspan="5" class="px-4 py-8 text-center text-slate-500">Chưa có dữ liệu cho tab này hoặc API chưa trả dữ liệu.</td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>
    </section>
  </EraLmsLayout>
</template>
