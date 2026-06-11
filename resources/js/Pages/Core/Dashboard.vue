<script setup>
import { computed, onMounted, ref } from 'vue'
import { Award, BarChart3, BookOpen, Brain, CheckCircle2, ClipboardCheck, Compass, GraduationCap, PlayCircle, RefreshCw, Target, TrendingUp } from '@lucide/vue'
import EraLmsLayout from '@/Layouts/EraLmsLayout.vue'

const props = defineProps({
  sessionUser: { type: Object, default: null },
  apiHeaders: { type: Object, default: () => ({}) },
})

defineEmits(['logout'])

const loading = ref(false)
const errors = ref([])
const activeTab = ref('learning')
const activeLearnerTab = ref('assigned')
const core = ref(null)
const digitalTwin = ref(null)
const learnerExamRows = ref([])
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

const chartPalette = ['#2563eb', '#059669', '#d97706', '#7c3aed', '#0f766e', '#dc2626']
const adminCharts = computed(() => [
  chart('Module dữ liệu', [
    ['Khóa', datasets.value.courses.length],
    ['Lớp', datasets.value.sections.length],
    ['Học liệu', datasets.value.repository.length],
    ['Video', datasets.value.videos.length],
  ]),
  chart('Đánh giá', [
    ['Exam', datasets.value.exams.length],
    ['Assignment', datasets.value.assignments.length],
    ['Gradebook', datasets.value.gradebooks.length],
    ['Question bank', datasets.value.questionBanks.length],
  ]),
  chart('Người học', [
    ['Enrolled', datasets.value.enrollments.length],
    ['Attendance', datasets.value.attendance.length],
    ['Credential', datasets.value.credentials.length],
    ['Survey', datasets.value.surveys.length],
  ]),
  chart('Chất lượng', [
    ['Survey', datasets.value.surveys.length],
    ['Campaign', datasets.value.campaigns.length],
    ['Risk', datasets.value.risks.length],
    ['Alert', datasets.value.alerts.length],
  ]),
  chart('Nền tảng', [
    ['Sync jobs', datasets.value.syncJobs.length],
    ['API lỗi', errors.value.length],
    ['Campus', Number(core.value?.campuses_count || 0)],
    ['Đơn vị', Number(core.value?.academic_units_count || 0)],
  ]),
  chart('Xuất bản khóa học', [
    ['Published', publishedCount(datasets.value.courses)],
    ['Draft/Khác', Math.max(datasets.value.courses.length - publishedCount(datasets.value.courses), 0)],
  ]),
])

const learnerCharts = computed(() => [
  chart('Tiến độ học', [
    ['Được gán', learnerAssigned.value.length],
    ['Đang học', learnerInProgress.value.length],
    ['Hoàn thành', learnerCompleted.value.length],
  ]),
  chart('Kết quả thi', [
    ['Đạt', learnerExamRows.value.filter((row) => Number(row.pass_rate || 0) >= 50).length],
    ['Cần xem lại', learnerExamRows.value.filter((row) => Number(row.pass_rate || 0) < 50).length],
  ]),
  chart('Hoạt động', [
    ['Bài tập', datasets.value.assignments.length],
    ['Chứng chỉ', datasets.value.credentials.length],
    ['Lớp mở', learnerAvailable.value.length],
  ]),
  chart('Khóa học', [
    ['Đăng ký được', learnerAvailable.value.length],
    ['Đã ghi danh', learnerAssigned.value.length],
  ]),
])

const learnerKpis = computed(() => [
  { label: 'Khóa được gán', value: learnerAssigned.value.length, detail: `${learnerInProgress.value.length} đang học`, icon: BookOpen, href: '/courses/learn', color: 'from-blue-600 to-cyan-500' },
  { label: 'Kết quả thi', value: learnerExamRows.value.length, detail: `${learnerExamRows.value.filter((row) => Number(row.pass_rate || 0) >= 50).length} bài đạt`, icon: ClipboardCheck, href: '/exams/results', color: 'from-emerald-600 to-teal-500' },
  { label: 'IDP readiness', value: Math.round(Number(digitalTwin.value?.digital_twin?.readiness_score || 0)), detail: digitalTwin.value?.idp?.goal || 'Chưa có dữ liệu IDP', icon: Brain, href: '/career/digital-twin', color: 'from-violet-600 to-fuchsia-500' },
  { label: 'Portfolio', value: Math.round(Number(digitalTwin.value?.metrics?.[3]?.value || 0)), detail: 'Hồ sơ năng lực', icon: Award, href: '/career', color: 'from-amber-500 to-orange-500' },
])

const learnerFlow = computed(() => [
  { label: 'Được gán', value: learnerAssigned.value.length, href: '/courses/learn', icon: GraduationCap },
  { label: 'Học bài', value: learnerInProgress.value.length, href: '/courses/learn', icon: PlayCircle },
  { label: 'Làm thi', value: learnerExamRows.value.length, href: '/exams/take', icon: ClipboardCheck },
  { label: 'Xem kết quả', value: learnerResultRows.value.length, href: '/exams/results', icon: BarChart3 },
  { label: 'IDP', value: Math.round(Number(digitalTwin.value?.digital_twin?.readiness_score || 0)), href: '/career/digital-twin', icon: Compass },
])

const learnerIdpActions = computed(() => (digitalTwin.value?.idp?.actions || []).slice(0, 3))
const learnerSkills = computed(() => (digitalTwin.value?.skills || []).slice(0, 5))

const horizontalFlowNodes = computed(() => flowRows.value.flatMap((flow) => flow.nodes.map((item) => ({ ...item, group: flow.label, accent: flow.accent }))))

const learnerTabs = computed(() => ({
  assigned: learnerAssigned.value.map((item) => row(item.course?.code || `#${item.id}`, item.course?.title || item.class_section?.name, item.status, `${item.completion_percent || 0}% hoàn thành`, `/courses/learn?course_id=${item.course?.id || item.course_id}`)),
  available: learnerAvailable.value.map((item) => row(item.code, item.course?.title || item.name, item.status || item.section_type, item.name, '/')),
  completed: learnerCompleted.value.map((item) => row(item.course?.code || `#${item.id}`, item.course?.title || item.class_section?.name, item.status, 'Có thể học lại bài đã học', `/courses/learn?course_id=${item.course?.id || item.course_id}`)),
  results: learnerResultRows.value.map((item) => row('Exam', item.title, 'published', `${item.course} · ${item.detail}`, '/exams/results')),
}))

const learnerTabLabels = [
  { key: 'assigned', label: 'Khóa được gán' },
  { key: 'available', label: 'Có thể đăng ký' },
  { key: 'completed', label: 'Đã hoàn thành' },
  { key: 'results', label: 'Kết quả thi' },
]

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
const learnerResultRows = computed(() => learnerExamRows.value.map((row) => ({
  id: `${row.class?.id}-${row.exam?.id}`,
  title: row.exam?.title || 'Bài thi',
  course: row.class?.course?.title || row.class?.name || 'Lớp học',
  detail: `${row.average_score || 0} điểm · ${row.pass_rate || 0}% đạt`,
})).slice(0, 4))
const isLearner = computed(() => props.sessionUser?.user_type === 'student')
const roleDashboard = computed(() => core.value?.demo_dashboard || null)
const roleCards = computed(() => roleDashboard.value?.cards || [])
const roleWorklist = computed(() => roleDashboard.value?.worklist || [])
const roleSignals = computed(() => roleDashboard.value?.signals || [])

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

  const activeEndpoints = isLearner.value
    ? [
        ['core', '/api/v1/core/dashboard'],
        ['courses', '/api/v1/courses?per_page=8'],
        ['enrollments', '/api/v1/enrollment/records?per_page=8&mine=1'],
        ['sections', '/api/v1/enrollment/sections?per_page=8'],
        ['assignments', '/api/v1/assignments?per_page=8'],
        ['credentials', '/api/v1/credentials/certificates?per_page=8'],
        ['digitalTwin', '/api/v1/career/digital-twin'],
      ]
    : endpoints
  const results = await Promise.allSettled(activeEndpoints.map(([key, url]) => fetchJson(key, url)))
  for (const result of results) {
    if (result.status === 'fulfilled') {
      const { key, data } = result.value
      if (key === 'core') core.value = data
      else if (key === 'digitalTwin') digitalTwin.value = data
      else datasets.value[key] = dataList(data)
    } else {
      errors.value.push(result.reason)
    }
  }

  if (isLearner.value) {
    try {
      learnerExamRows.value = dataList(await fetchJson('learnerExamRows', '/api/v1/exam-results/classes').then((result) => result.data))
    } catch {
      learnerExamRows.value = []
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
  if (isLearner.value && key === 'enrollments') {
    url = '/api/v1/enrollment/records?per_page=8&mine=1'
  }
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

function chart(title, items) {
  const total = items.reduce((sum, item) => sum + Number(item[1] || 0), 0)
  const max = Math.max(...items.map((item) => Number(item[1] || 0)), 1)
  return {
    title,
    total,
    items: items.map(([label, value], index) => ({
      label,
      value: Number(value || 0),
      percent: Math.round(Number(value || 0) * 100 / max),
      share: total ? Math.round(Number(value || 0) * 100 / total) : 0,
      color: chartPalette[index % chartPalette.length],
    })),
  }
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

function toneClass(tone) {
  return {
    blue: 'border-blue-200 bg-blue-50 text-blue-900',
    emerald: 'border-emerald-200 bg-emerald-50 text-emerald-900',
    amber: 'border-amber-200 bg-amber-50 text-amber-900',
    violet: 'border-violet-200 bg-violet-50 text-violet-900',
    rose: 'border-rose-200 bg-rose-50 text-rose-900',
    slate: 'border-slate-200 bg-slate-50 text-slate-900',
  }[tone] || 'border-slate-200 bg-white text-slate-900'
}

function toneDot(tone) {
  return {
    blue: 'bg-blue-600',
    emerald: 'bg-emerald-600',
    amber: 'bg-amber-500',
    violet: 'bg-violet-600',
    rose: 'bg-rose-600',
    slate: 'bg-slate-600',
  }[tone] || 'bg-slate-600'
}

onMounted(() => {
  const tab = new URLSearchParams(window.location.search).get('learner_tab')
  if (['assigned', 'available', 'completed', 'results'].includes(tab)) {
    activeLearnerTab.value = tab
  }
  loadDashboard()
})
</script>

<template>
  <EraLmsLayout :session-user="sessionUser" @logout="$emit('logout')">
    <template #breadcrumb>Tổng quan</template>

    <section class="space-y-5">
      <section v-if="roleDashboard" class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
        <div class="grid gap-0 xl:grid-cols-[1fr_360px]">
          <div class="bg-slate-950 p-6 text-white">
            <div class="flex flex-wrap items-center justify-between gap-3">
              <div>
                <div class="text-xs font-bold uppercase tracking-wide text-cyan-200">Demo dashboard riêng</div>
                <h1 class="mt-2 text-3xl font-bold">{{ roleDashboard.title }}</h1>
                <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-200">{{ roleDashboard.subtitle }}</p>
              </div>
              <button class="rounded-md bg-white px-4 py-2 text-sm font-semibold text-slate-950 disabled:opacity-60" :disabled="loading" @click="loadDashboard">
                {{ loading ? 'Đang tải...' : 'Tải lại dữ liệu' }}
              </button>
            </div>
          </div>
          <div class="border-t border-slate-200 bg-slate-50 p-6 xl:border-l xl:border-t-0">
            <div class="text-sm font-bold text-slate-950">{{ sessionUser?.full_name }}</div>
            <div class="mt-4 space-y-3 text-sm">
              <div class="flex justify-between gap-3"><span class="text-slate-500">Email</span><span class="font-semibold text-slate-950">{{ sessionUser?.email }}</span></div>
              <div class="flex justify-between gap-3"><span class="text-slate-500">Persona</span><span class="font-semibold">{{ roleDashboard.persona }}</span></div>
              <div class="flex justify-between gap-3"><span class="text-slate-500">Tenant</span><span class="font-semibold">{{ core?.tenant?.name || 'VABIS LMS' }}</span></div>
            </div>
          </div>
        </div>

        <div class="grid gap-4 border-t border-slate-200 p-5 md:grid-cols-2 xl:grid-cols-3">
          <article v-for="item in roleCards" :key="item.label" class="rounded-lg border p-4" :class="toneClass(item.tone)">
            <div class="flex items-start justify-between gap-3">
              <div>
                <div class="text-xs font-bold uppercase opacity-70">{{ item.label }}</div>
                <div class="mt-3 text-3xl font-bold">{{ item.value }}</div>
              </div>
              <span class="mt-1 h-3 w-3 rounded-full" :class="toneDot(item.tone)"></span>
            </div>
            <div class="mt-3 line-clamp-2 text-sm opacity-80">{{ item.detail }}</div>
          </article>
        </div>

        <div class="grid gap-4 border-t border-slate-200 p-5 xl:grid-cols-[1fr_360px]">
          <section class="rounded-lg border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 px-4 py-3">
              <h2 class="text-sm font-bold text-slate-950">Việc cần chú ý</h2>
            </div>
            <div class="divide-y divide-slate-100">
              <a v-for="item in roleWorklist" :key="`${item.label}-${item.status}`" :href="item.href" class="grid gap-2 px-4 py-3 text-sm hover:bg-slate-50 md:grid-cols-[1fr_180px_120px]">
                <span class="font-semibold text-slate-950">{{ item.label }}</span>
                <span class="text-slate-600">{{ item.meta }}</span>
                <span class="font-semibold text-blue-700">{{ item.status }}</span>
              </a>
              <div v-if="!roleWorklist.length" class="px-4 py-8 text-center text-sm text-slate-500">Chưa có việc cần chú ý.</div>
            </div>
          </section>

          <aside class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
            <h2 class="text-sm font-bold text-slate-950">Tín hiệu live</h2>
            <div class="mt-4 space-y-3">
              <div v-for="item in roleSignals" :key="item.label" class="rounded-md border border-slate-200 bg-slate-50 p-3">
                <div class="flex items-center justify-between gap-3">
                  <span class="text-xs font-bold uppercase text-slate-500">{{ item.label }}</span>
                  <span class="text-lg font-bold text-slate-950">{{ item.value }}</span>
                </div>
                <div class="mt-1 text-xs text-slate-600">{{ item.detail }}</div>
              </div>
            </div>
          </aside>
        </div>
      </section>

      <section v-if="isLearner" class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
        <div class="relative bg-slate-950 p-6 text-white">
          <div class="absolute inset-y-0 right-0 hidden w-1/2 bg-gradient-to-l from-cyan-500/25 via-blue-500/10 to-transparent lg:block"></div>
          <div class="relative grid gap-6 xl:grid-cols-[1fr_360px]">
            <div>
              <div class="inline-flex items-center gap-2 rounded-full bg-white/10 px-3 py-1 text-xs font-bold uppercase text-cyan-100">
                <GraduationCap class="h-4 w-4" /> Learner command center
              </div>
              <h1 class="mt-4 max-w-3xl text-3xl font-bold">Không gian học tập cá nhân, kết quả thi và định hướng IDP</h1>
              <p class="mt-3 max-w-3xl text-sm leading-6 text-slate-200">
                Dashboard tổng hợp khóa được gán, tiến độ học, bài thi, kết quả đã công bố và Digital Twin để gợi ý hành động học tiếp.
              </p>
              <div class="mt-5 flex flex-wrap gap-2">
                <a href="/courses/learn" class="inline-flex items-center gap-2 rounded-md bg-cyan-400 px-4 py-2 text-sm font-bold text-slate-950"><PlayCircle class="h-4 w-4" /> Học tiếp</a>
                <a href="/career/digital-twin" class="inline-flex items-center gap-2 rounded-md border border-white/25 px-4 py-2 text-sm font-semibold text-white"><Brain class="h-4 w-4" /> Xem Digital Twin</a>
                <button class="inline-flex items-center gap-2 rounded-md border border-white/25 px-4 py-2 text-sm font-semibold text-white disabled:opacity-60" :disabled="loading" @click="loadDashboard">
                  <RefreshCw class="h-4 w-4" /> {{ loading ? 'Đang tải...' : 'Tải lại dữ liệu' }}
                </button>
              </div>
            </div>
            <div class="rounded-lg border border-white/10 bg-white/10 p-5 backdrop-blur">
              <div class="text-sm font-bold">{{ sessionUser?.full_name || 'Người học' }}</div>
              <div class="mt-1 text-xs text-slate-300">{{ sessionUser?.email }}</div>
              <div class="mt-5">
                <div class="flex items-end justify-between">
                  <span class="text-xs uppercase text-slate-300">IDP readiness</span>
                  <strong class="text-3xl">{{ Math.round(Number(digitalTwin?.digital_twin?.readiness_score || 0)) }}</strong>
                </div>
                <div class="mt-3 h-2 rounded-full bg-white/20">
                  <div class="h-2 rounded-full bg-cyan-400" :style="{ width: `${Math.min(100, Number(digitalTwin?.digital_twin?.readiness_score || 0))}%` }"></div>
                </div>
              </div>
              <div class="mt-5 grid grid-cols-2 gap-3 text-sm">
                <div class="rounded-md bg-white/10 p-3"><div class="text-slate-300">Đang học</div><strong>{{ learnerInProgress.length }}</strong></div>
                <div class="rounded-md bg-white/10 p-3"><div class="text-slate-300">Kết quả thi</div><strong>{{ learnerExamRows.length }}</strong></div>
              </div>
            </div>
          </div>
        </div>
      </section>

      <section v-if="isLearner" class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
        <a v-for="item in learnerKpis" :key="item.label" :href="item.href" class="group overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm hover:border-blue-300">
          <div class="h-1 bg-gradient-to-r" :class="item.color"></div>
          <div class="p-4">
            <div class="flex items-start justify-between gap-3">
              <div>
                <div class="text-xs font-semibold uppercase text-slate-500">{{ item.label }}</div>
                <div class="mt-2 text-3xl font-bold text-slate-950">{{ item.value }}</div>
              </div>
              <div class="rounded-lg bg-slate-100 p-2 text-slate-700 group-hover:bg-blue-50 group-hover:text-blue-700">
                <component :is="item.icon" class="h-5 w-5" />
              </div>
            </div>
            <div class="mt-3 line-clamp-2 text-sm text-slate-600">{{ item.detail }}</div>
          </div>
        </a>
      </section>

      <section v-if="isLearner" class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
        <div class="flex flex-wrap items-center justify-between gap-3">
          <div>
            <h2 class="text-sm font-bold text-slate-950">Flow học tập cá nhân</h2>
            <p class="mt-1 text-xs text-slate-500">Đi từ khóa được gán đến học bài, làm thi, xem kết quả và cập nhật IDP.</p>
          </div>
          <a href="/career/digital-twin" class="text-sm font-semibold text-blue-700">Mở IDP</a>
        </div>
        <div class="mt-4 overflow-x-auto">
          <div class="flex min-w-max gap-3">
            <a v-for="(step, index) in learnerFlow" :key="step.label" :href="step.href" class="relative w-44 rounded-lg border border-slate-200 bg-slate-50 p-4 text-sm hover:border-blue-300 hover:bg-blue-50">
              <component :is="step.icon" class="h-5 w-5 text-blue-700" />
              <div class="mt-3 font-bold text-slate-950">{{ step.label }}</div>
              <div class="mt-1 text-xs text-slate-500">{{ step.value }} dữ liệu</div>
              <span v-if="index < learnerFlow.length - 1" class="absolute -right-2 top-1/2 h-px w-4 bg-slate-300"></span>
            </a>
          </div>
        </div>
      </section>

      <section v-if="isLearner" class="grid gap-4 xl:grid-cols-[1fr_380px]">
        <div class="grid gap-4 md:grid-cols-2">
          <article v-for="chartItem in learnerCharts" :key="chartItem.title" class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
            <div class="flex items-center justify-between gap-3">
              <h2 class="text-sm font-bold text-slate-950">{{ chartItem.title }}</h2>
              <span class="text-2xl font-bold text-slate-950">{{ chartItem.total }}</span>
            </div>
            <div class="mt-4 space-y-3">
              <div v-for="item in chartItem.items" :key="item.label">
                <div class="mb-1 flex justify-between text-xs text-slate-600"><span>{{ item.label }}</span><span>{{ item.value }}</span></div>
                <div class="h-2 rounded-full bg-slate-100"><div class="h-2 rounded-full" :style="{ width: `${item.percent}%`, backgroundColor: item.color }"></div></div>
              </div>
            </div>
          </article>
        </div>
        <aside class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
          <h2 class="flex items-center gap-2 text-sm font-bold text-slate-950"><Target class="h-4 w-4 text-emerald-700" /> Gợi ý IDP gần nhất</h2>
          <div class="mt-4 space-y-3">
            <div v-for="action in learnerIdpActions" :key="`${action.phase}-${action.focus}`" class="rounded-md border border-slate-200 bg-slate-50 p-3">
              <div class="flex items-center justify-between gap-2 text-xs"><strong class="text-slate-950">{{ action.phase }}</strong><span class="rounded bg-white px-2 py-1 text-slate-600">{{ action.focus }}</span></div>
              <p class="mt-2 text-sm leading-5 text-slate-700">{{ action.action }}</p>
            </div>
            <div v-if="!learnerIdpActions.length" class="text-sm text-slate-500">Chưa có gợi ý IDP.</div>
          </div>
          <div class="mt-4 border-t border-slate-200 pt-4">
            <h3 class="text-xs font-bold uppercase text-slate-500">Skill gap</h3>
            <div v-for="skill in learnerSkills" :key="skill.name" class="mt-3">
              <div class="mb-1 flex justify-between text-xs"><span>{{ skill.name }}</span><span>{{ skill.score }}/100</span></div>
              <div class="h-2 rounded-full bg-slate-100"><div class="h-2 rounded-full bg-violet-600" :style="{ width: `${Math.min(100, skill.score)}%` }"></div></div>
            </div>
          </div>
        </aside>
      </section>

      <div v-if="!isLearner" class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
        <div class="grid gap-0 xl:grid-cols-[1fr_380px]">
          <div class="bg-gradient-to-br from-slate-950 via-blue-950 to-cyan-900 p-6 text-white">
            <div class="text-xs font-bold uppercase tracking-wide text-blue-700">EraLMS Operations Console</div>
            <h1 class="mt-2 text-3xl font-bold">Dashboard vận hành LMS hiện đại</h1>
            <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-200">
              Dashboard này load dữ liệu từ các module chính và trình bày theo flow: thiết lập, nội dung, đánh giá, chất lượng, tích hợp và vận hành.
            </p>
            <div class="mt-5 flex flex-wrap gap-2">
              <button class="rounded-md bg-white px-4 py-2 text-sm font-semibold text-slate-950 disabled:opacity-60" :disabled="loading" @click="loadDashboard">
                {{ loading ? 'Đang tải...' : 'Tải lại dữ liệu' }}
              </button>
              <a href="/admin/lms/system-check" class="rounded-md border border-white/30 px-4 py-2 text-sm font-semibold text-white">System check</a>
              <a href="/moodle-parity" class="rounded-md border border-white/30 px-4 py-2 text-sm font-semibold text-white">Moodle parity</a>
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

      <div v-if="!isLearner" class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
        <a v-for="item in kpis" :key="item.label" :href="item.href" class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm hover:border-blue-300 hover:bg-blue-50/40">
          <div class="text-xs font-semibold uppercase text-slate-500">{{ item.label }}</div>
          <div class="mt-3 text-3xl font-bold text-slate-950">{{ item.value }}</div>
          <div class="mt-2 text-sm text-slate-600">{{ item.detail }}</div>
        </a>
      </div>

      <section v-if="!isLearner" class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
        <article v-for="chartItem in adminCharts" :key="chartItem.title" class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
          <div class="flex items-start justify-between gap-3">
            <div>
              <h2 class="text-sm font-bold text-slate-950">{{ chartItem.title }}</h2>
              <p class="mt-1 text-xs text-slate-500">Dữ liệu live từ API module</p>
            </div>
            <span class="rounded-md bg-slate-950 px-2 py-1 text-sm font-bold text-white">{{ chartItem.total }}</span>
          </div>
          <div class="mt-4 grid grid-cols-[110px_1fr] gap-4">
            <svg viewBox="0 0 120 120" class="h-28 w-28">
              <circle cx="60" cy="60" r="44" fill="none" stroke="#e2e8f0" stroke-width="14" />
              <circle
                v-for="(item, index) in chartItem.items"
                :key="item.label"
                cx="60"
                cy="60"
                r="44"
                fill="none"
                :stroke="item.color"
                stroke-width="14"
                stroke-linecap="round"
                :stroke-dasharray="`${Math.max(item.share, 4)} 100`"
                :transform="`rotate(${index * 82 - 90} 60 60)`"
              />
              <text x="60" y="64" text-anchor="middle" class="fill-slate-950 text-lg font-bold">{{ chartItem.total }}</text>
            </svg>
            <div class="space-y-2">
              <div v-for="item in chartItem.items" :key="item.label">
                <div class="mb-1 flex justify-between gap-2 text-xs"><span class="truncate text-slate-600">{{ item.label }}</span><span class="font-semibold text-slate-950">{{ item.value }}</span></div>
                <div class="h-2 rounded-full bg-slate-100"><div class="h-2 rounded-full" :style="{ width: `${item.percent}%`, backgroundColor: item.color }"></div></div>
              </div>
            </div>
          </div>
        </article>
      </section>

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
              <div v-for="item in (isLearner ? learnerResultRows : learnerResults)" :key="item.id" class="text-sm">
                <div class="font-semibold text-slate-900">{{ item.title }}</div>
                <div class="text-xs text-slate-500">{{ item.course }} · {{ item.detail }}</div>
                <a href="/exams/results" class="mt-1 inline-block text-xs font-semibold text-blue-700">Xem kết quả</a>
              </div>
              <div v-if="!(isLearner ? learnerResultRows : learnerResults).length" class="text-sm text-slate-500">Chưa có kết quả thi.</div>
            </div>
          </div>
        </div>
      </section>

      <section v-if="isLearner" class="rounded-lg border border-slate-200 bg-white shadow-sm">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 px-5 py-4">
          <div>
            <h2 class="text-sm font-bold text-slate-950">Dữ liệu liên quan</h2>
            <p class="mt-1 text-xs text-slate-500">Các dữ liệu learner thường cần xem trong quá trình học.</p>
          </div>
          <div class="flex flex-wrap gap-2">
            <button v-for="tab in learnerTabLabels" :key="tab.key" class="rounded-md px-3 py-2 text-sm font-semibold" :class="activeLearnerTab === tab.key ? 'bg-blue-700 text-white' : 'border border-slate-300 text-slate-700'" @click="activeLearnerTab = tab.key">
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
                <th class="px-4 py-3">Chi tiết</th>
                <th class="px-4 py-3">Thao tác</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              <tr v-for="item in learnerTabs[activeLearnerTab]" :key="`${activeLearnerTab}-${item.key}-${item.title}`" class="hover:bg-slate-50">
                <td class="px-4 py-3 font-semibold">{{ item.key }}</td>
                <td class="px-4 py-3">{{ item.title }}</td>
                <td class="px-4 py-3"><span class="rounded bg-slate-100 px-2 py-1 text-xs">{{ item.status }}</span></td>
                <td class="px-4 py-3 text-slate-600">{{ item.detail }}</td>
                <td class="px-4 py-3"><a :href="item.href" class="font-semibold text-blue-700">Mở</a></td>
              </tr>
              <tr v-if="!learnerTabs[activeLearnerTab].length">
                <td colspan="5" class="px-4 py-8 text-center text-slate-500">Chưa có dữ liệu cho tab này.</td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>

      <section v-if="!isLearner" class="rounded-lg border border-slate-200 bg-white shadow-sm">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 px-5 py-4">
          <div>
            <h2 class="text-sm font-bold text-slate-950">Flow chức năng ngang</h2>
            <p class="mt-1 text-xs text-slate-500">Các chức năng chính được xếp ngang theo luồng vận hành, có thể click để mở module.</p>
          </div>
          <div class="text-xs text-slate-500">{{ horizontalFlowNodes.length }} chức năng chính</div>
        </div>
        <div class="overflow-x-auto p-5">
          <div class="flex min-w-max items-stretch gap-3">
            <a v-for="(item, index) in horizontalFlowNodes" :key="`${item.group}-${item.label}`" :href="item.href" class="relative w-48 rounded-lg border px-4 py-3 text-sm hover:shadow-sm" :class="stateClass(item.state)">
              <div class="flex items-center gap-2">
                <span class="h-2.5 w-2.5 rounded-full" :class="item.accent"></span>
                <span class="truncate text-xs font-semibold uppercase opacity-70">{{ item.group }}</span>
              </div>
              <div class="mt-3 font-bold">{{ item.label }}</div>
              <div class="mt-2 text-xs">{{ item.metric }}</div>
              <span v-if="index < horizontalFlowNodes.length - 1" class="absolute -right-2 top-1/2 hidden h-px w-4 bg-slate-300 md:block"></span>
            </a>
          </div>
        </div>
      </section>

      <section v-if="!isLearner" class="rounded-lg border border-slate-200 bg-white shadow-sm">
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
