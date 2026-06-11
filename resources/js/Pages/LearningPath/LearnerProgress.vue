<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import EraLmsLayout from '@/Layouts/EraLmsLayout.vue'

const props = defineProps({
  sessionUser: { type: Object, default: null },
  apiHeaders: { type: Object, default: () => ({}) },
})

const courses = ref([])
const selectedCourseId = ref('')
const learners = ref([])
const selectedLearnerId = ref('')
const loadingCourses = ref(false)
const loadingLearners = ref(false)
const error = ref('')
const search = ref('')
const statusFilter = ref('Tất cả')
const sortBy = ref('full_name')
const sortDirection = ref('asc')
const message = ref('')

const modelTabs = [
  {
    id: 'international',
    shortLabel: 'International',
    label: 'Chỉ số quốc tế',
    metrics: [
      { key: 'completionRate', label: 'Completion rate', unit: '%', target: 80 },
      { key: 'activitySignals', label: 'Hoạt động', unit: 'điểm', target: 60 },
      { key: 'scormConformance', label: 'SCORM/xAPI', unit: 'điểm', target: 75 },
      { key: 'caliperQuality', label: 'Caliper', unit: 'điểm', target: 75 },
    ],
  },
  {
    id: 'mooc',
    shortLabel: 'MOOC',
    label: 'Chỉ số MOOC',
    metrics: [
      { key: 'watchRate', label: 'Watch rate', unit: '%', target: 78 },
      { key: 'quizMastery', label: 'Quiz pass', unit: '%', target: 70 },
      { key: 'assignmentQuality', label: 'Assignment', unit: '%', target: 80 },
      { key: 'forumEngagement', label: 'Forum', unit: 'điểm', target: 60 },
      { key: 'peerReview', label: 'Peer review', unit: '/5', target: 3.5 },
    ],
  },
  {
    id: 'idp',
    shortLabel: 'IDP',
    label: 'Chỉ số IDP',
    metrics: [
      { key: 'goalAlignment', label: 'Goal alignment', unit: '%', target: 80 },
      { key: 'milestoneProgress', label: 'Milestone', unit: '%', target: 70 },
      { key: 'mentorCheckins', label: 'Mentor', unit: 'lần', target: 2 },
      { key: 'skillGapReduction', label: 'Skill gap', unit: '%', target: 30 },
    ],
  },
  {
    id: 'longLife',
    shortLabel: 'Long-life',
    label: 'Chỉ số Long-life',
    metrics: [
      { key: 'microLearningHours', label: 'Micro-learning', unit: 'giờ', target: 20 },
      { key: 'portfolioArtifacts', label: 'Portfolio', unit: 'mục', target: 4 },
      { key: 'externalBadges', label: 'Badges', unit: 'chứng chỉ', target: 2 },
      { key: 'learningCircles', label: 'Learning circles', unit: 'nhóm', target: 3 },
    ],
  },
]

const detailTabs = [
  { id: 'overview', label: 'Tổng quan' },
  { id: 'metrics', label: 'Chỉ số' },
  { id: 'related', label: 'Dữ liệu liên quan' },
]

const activeModelTab = ref('international')
const activeDetailTab = ref('overview')

const statusOptions = ['Tất cả', 'Đang học', 'Hoàn thành', 'Bị khóa', 'Bị kẹt', 'Chưa bắt đầu', 'Rủi ro cao']

const selectedCourse = computed(() => courses.value.find((course) => String(course.id) === String(selectedCourseId.value)) || null)
const selectedLearner = computed(() => learners.value.find((row) => row.id === selectedLearnerId.value) || learners.value[0] || null)
const activeModel = computed(() => modelTabs.find((tab) => tab.id === activeModelTab.value) || modelTabs[0])

const filteredRows = computed(() => {
  return learners.value.filter((row) => {
    if (statusFilter.value !== 'Tất cả' && row.status !== statusFilter.value) return false
    const q = search.value.trim().toLowerCase()
    if (!q) return true
    return (
      row.full_name.toLowerCase().includes(q)
      || row.email.toLowerCase().includes(q)
      || (row.learner_code || '').toLowerCase().includes(q)
    )
  })
})

const sortedRows = computed(() => {
  const rows = [...filteredRows.value]
  const direction = sortDirection.value === 'asc' ? 1 : -1
  return rows.sort((left, right) => {
    const leftValue = getPathValue(left, sortBy.value)
    const rightValue = getPathValue(right, sortBy.value)
    if (typeof leftValue === 'number' && typeof rightValue === 'number') return (leftValue - rightValue) * direction
    return String(leftValue || '').localeCompare(String(rightValue || '')) * direction
  })
})

const selectedModelMetrics = computed(() => {
  const row = selectedLearner.value
  if (!row) return []
  return activeModel.value.metrics.map((metric) => ({
    ...metric,
    value: getPathValue(row.metrics?.[activeModel.value.id], metric.key),
  }))
})

const totalStats = computed(() => {
  const total = learners.value.length
  const completed = learners.value.filter((row) => row.status === 'Hoàn thành').length
  const inRisk = learners.value.filter((row) => row.riskLevel === 'Cao').length
  const avgProgress = total ? Math.round(learners.value.reduce((sum, row) => sum + row.overallProgress, 0) / total) : 0
  return { total, completed, inRisk, avgProgress }
})

watch(() => selectedCourseId.value, () => {
  if (selectedCourseId.value) loadLearners()
})

watch(() => sortedRows.value.length, (total) => {
  if (!total) {
    selectedLearnerId.value = ''
    return
  }
  if (!selectedLearnerId.value || !sortedRows.value.find((row) => row.id === selectedLearnerId.value)) {
    selectedLearnerId.value = sortedRows.value[0].id
  }
})

onMounted(loadCourses)

async function loadCourses() {
  loadingCourses.value = true
  error.value = ''
  try {
    const payload = await fetchJson('/api/v1/courses?status=published&per_page=100')
    const items = parsePayload(payload)
    courses.value = items.map((course) => ({
      id: course.id,
      title: course.title || `Khóa học #${course.id}`,
      code: course.code || '',
    }))
    if (!selectedCourseId.value && courses.value.length) {
      selectedCourseId.value = String(courses.value[0].id)
    }
  } catch (err) {
    error.value = err.message || 'Không thể tải danh sách khóa học.'
  } finally {
    loadingCourses.value = false
  }
}

async function loadLearners() {
  if (!selectedCourseId.value) return
  loadingLearners.value = true
  error.value = ''
  try {
    const params = new URLSearchParams({
      course_id: String(selectedCourseId.value),
      per_page: '500',
    }).toString()

    const [enrollResp, progressResp, metricResp, riskResp] = await Promise.allSettled([
      fetchJson(`/api/v1/enrollment/records?${params}`),
      fetchJson(`/api/v1/courses/${selectedCourseId.value}/progress/users?${params}`),
      fetchJson(`/api/v1/analytics/metrics?${params}`),
      fetchJson(`/api/v1/analytics/risks?${params}`),
    ])

    const failedEndpoints = []
    if (enrollResp.status === 'rejected') failedEndpoints.push(`enrollment/records: ${enrollResp.reason?.message || 'Không phản hồi'}`)
    if (progressResp.status === 'rejected') failedEndpoints.push(`progress/users: ${progressResp.reason?.message || 'Không phản hồi'}`)
    if (metricResp.status === 'rejected') failedEndpoints.push(`analytics/metrics: ${metricResp.reason?.message || 'Không phản hồi'}`)
    if (riskResp.status === 'rejected') failedEndpoints.push(`analytics/risks: ${riskResp.reason?.message || 'Không phản hồi'}`)

    const enrollmentRows = enrollResp.status === 'fulfilled' ? parsePayload(enrollResp.value) : []
    const progressRows = progressResp.status === 'fulfilled' ? parsePayload(progressResp.value) : []
    const metricRows = metricResp.status === 'fulfilled' ? parsePayload(metricResp.value) : []
    const riskRows = riskResp.status === 'fulfilled' ? parsePayload(riskResp.value) : []

    const progressByUser = new Map()
    progressRows.forEach((row) => progressByUser.set(Number(row.user_id), row))
    const metricByUser = new Map()
    metricRows.forEach((row) => metricByUser.set(Number(row.user_id), row))
    const riskByUser = new Map()
    riskRows.forEach((row) => {
      const id = Number(row.user_id || row.learner?.id)
      if (id) riskByUser.set(id, row)
    })

    learners.value = enrollmentRows.map((record) => buildLearnerRow(
      record,
      progressByUser.get(Number(record.user_id)),
      metricByUser.get(Number(record.user_id)),
      riskByUser.get(Number(record.user_id))
    ))
    if (failedEndpoints.length) {
      error.value = `Một số API chưa phản hồi: ${failedEndpoints.join('; ')}`
    }
  } catch (err) {
    error.value = err.message || 'Không thể tải dữ liệu học viên.'
  } finally {
    loadingLearners.value = false
  }
}

function parsePayload(payload) {
  if (!payload) return []
  if (Array.isArray(payload)) return payload
  if (Array.isArray(payload.data?.data)) return payload.data.data
  if (Array.isArray(payload.data)) return payload.data
  if (Array.isArray(payload.result)) return payload.result
  if (Array.isArray(payload.items)) return payload.items
  if (Array.isArray(payload.rows)) return payload.rows
  return []
}

async function fetchJson(url) {
  const response = await fetch(url, { headers: { Accept: 'application/json', ...props.apiHeaders } })
  const payload = await response.json().catch(() => ({}))
  if (!response.ok) throw new Error(payload?.message || `Lỗi API ${response.status}`)
  return payload
}

function buildLearnerRow(record, progressRow, metricRow, riskRow) {
  const user = record.learner || {}
  const progress = Number(progressRow?.progress_percent || record.completion_percent || 0)
  const riskScore = Number(record.risk_score || riskRow?.risk_score || 0)
  const riskLevel = riskScore >= 70 ? 'Cao' : riskScore >= 45 ? 'Trung bình' : 'Bình thường'
  const status = mapStatus(record.status, progress, riskScore)
  const metrics = deriveMetrics(progress, metricRow)

  return {
    id: `learner-${record.id || record.user_id}`,
    full_name: user.full_name || user.name || `Học viên ${record.user_id}`,
    email: user.email || '',
    learner_code: user.code || user.student_code || `SV-${record.user_id}`,
    status,
    riskLevel,
    riskScore: Math.round(riskScore),
    overallProgress: clamp(Math.round(progress)),
    metrics,
    lastActiveText: formatDate(progressRow?.last_accessed_at || user?.metadata?.last_active_at),
    activities: [
      `Tiến độ: ${Math.round(progress)}%`,
      `Rủi ro: ${riskLevel}`,
      `Hoạt động gần nhất: ${formatDate(progressRow?.last_accessed_at)}`,
    ],
    sources: {
      lrsRecords: Number(metricRow?.lrs_events || 0),
      caliperEvents: Number(metricRow?.caliper_events || 0),
      xapiStatements: Number(metricRow?.xapi_statements || 0),
      completionEvidence: `${Math.round(progress)}%`,
    },
    interventions: [
      'Nhắc nhở học viên theo dõi tiến độ',
      'Đặt mentor review 1:1',
      'Đặt nhắc nhở theo mốc thời gian',
    ],
  }
}

function deriveMetrics(progress, metricRow) {
  const p = Number(progress || 0)
  const quiz = normalizeToPercent(metricRow?.quiz_score || 0)
  const assignment = normalizeToPercent(metricRow?.assignment_score || 0)
  const watch = normalizeToPercent(metricRow?.video_watch_rate || 0)
  const forum = Number(metricRow?.forum_engagement || 0)
  const peer = Number(metricRow?.peer_review || 0)
  const loginCount = Number(metricRow?.login_count || 0)
  const studyMinutes = Number(metricRow?.study_time_minutes || 0)
  const completionVelocity = Number((studyMinutes / 60).toFixed(1))

  const international = {
    completionRate: clamp(p),
    activitySignals: clamp(loginCount * 0.5),
    scormConformance: clamp((p + normalizeToPercent(metricRow?.scorm_conformance || 0)) / 2),
    caliperQuality: clamp((watch + quiz + assignment) / 3),
    completionVelocity,
  }
  international.score = clamp(Math.round((international.completionRate * 0.4) + (international.scormConformance * 0.3) + (international.caliperQuality * 0.2)))

  const mooc = {
    watchRate: watch,
    quizMastery: quiz,
    assignmentQuality: assignment,
    forumEngagement: clamp(forum),
    peerReview: clamp(peer, 0, 5),
  }
  mooc.score = clamp(Math.round((mooc.watchRate * 0.25) + (mooc.quizMastery * 0.25) + (mooc.assignmentQuality * 0.25) + (Math.min(100, mooc.forumEngagement * 1.5) * 0.15) + (mooc.peerReview / 5 * 100 * 0.1)))

  const idp = {
    goalAlignment: normalizeToPercent(metricRow?.goal_alignment || 0),
    milestoneProgress: normalizeToPercent(metricRow?.milestone_progress || 0),
    mentorCheckins: Number(metricRow?.mentor_checkins || 0),
    skillGapReduction: normalizeToPercent(metricRow?.skill_gap_reduction || 0),
    selfAssessment: Number(metricRow?.self_assessment || 0),
  }
  idp.score = clamp(Math.round((idp.goalAlignment * 0.35) + (idp.milestoneProgress * 0.35) + (Math.min(100, idp.mentorCheckins / 3 * 100) * 0.15) + (idp.skillGapReduction * 0.15)))

  const longLife = {
    microLearningHours: Number(metricRow?.micro_learning_hours || 0),
    portfolioArtifacts: Number(metricRow?.portfolio_artifacts || 0),
    externalBadges: Number(metricRow?.external_badges || 0),
    learningCircles: Number(metricRow?.learning_circles || 0),
    crossRoleProjects: Number(metricRow?.cross_role_projects || 0),
  }
  longLife.score = clamp(Math.round(
    (Math.min(100, longLife.microLearningHours / 30 * 100) * 0.25)
    + (Math.min(100, longLife.portfolioArtifacts / 4 * 100) * 0.25)
    + (Math.min(100, longLife.externalBadges / 2 * 100) * 0.2)
    + (Math.min(100, longLife.learningCircles / 3 * 100) * 0.15)
    + (Math.min(100, longLife.crossRoleProjects / 2 * 100) * 0.15)
  ))

  return { international, mooc, idp, longLife }
}

function mapStatus(status, progress, riskScore = 0) {
  if (status === 'completed' || status === 'đã hoàn thành') return 'Hoàn thành'
  if (status === 'locked' || status === 'bị khóa') return 'Bị khóa'
  if (riskScore >= 70) return 'Bị kẹt'
  if ((progress || 0) === 0) return 'Chưa bắt đầu'
  return 'Đang học'
}

function getPathValue(row, key) {
  if (!row) return 0
  if (!key.includes('.')) return row[key]
  return key.split('.').reduce((value, part) => (value ? value[part] : 0), row)
}

function normalizeToPercent(value) {
  const n = Number(value)
  if (!Number.isFinite(n)) return 0
  if (n > 1) return clamp(Math.round(n))
  return clamp(Math.round(n * 100))
}

function clamp(v, min = 0, max = 100) {
  const n = Number(v)
  if (!Number.isFinite(n)) return min
  if (n < min) return min
  if (n > max) return max
  return n
}

function formatDate(value) {
  if (!value) return 'Chưa có dữ liệu'
  const d = new Date(value)
  if (Number.isNaN(d.getTime())) return value
  return d.toLocaleString('vi-VN', { hour12: false })
}

function statusTone(status) {
  switch (status) {
    case 'Hoàn thành':
      return 'bg-emerald-50 text-emerald-700 border-emerald-200'
    case 'Bị khóa':
      return 'bg-slate-100 text-slate-700 border-slate-300'
    case 'Bị kẹt':
    case 'Rủi ro cao':
      return 'bg-rose-50 text-rose-700 border-rose-200'
    case 'Chưa bắt đầu':
      return 'bg-amber-50 text-amber-700 border-amber-200'
    default:
      return 'bg-cyan-50 text-cyan-700 border-cyan-200'
  }
}

function riskTone(level) {
  if (level === 'Cao') return 'bg-rose-100 text-rose-700 border-rose-300'
  if (level === 'Trung bình') return 'bg-amber-100 text-amber-700 border-amber-300'
  return 'bg-emerald-100 text-emerald-700 border-emerald-300'
}

function metricTone(value, target) {
  const v = Number(value || 0)
  if (v >= target) return 'bg-emerald-100 text-emerald-700 border-emerald-300'
  if (v >= target * 0.75) return 'bg-amber-100 text-amber-700 border-amber-300'
  return 'bg-rose-100 text-rose-700 border-rose-300'
}

function formatMetric(metric, value) {
  const v = Number(value || 0)
  if (metric.unit === '/5') return `${v.toFixed(1)}/5`
  if (metric.unit === 'h/ngày' || metric.unit === 'giờ') return `${v.toFixed(1)} ${metric.unit}`
  return `${Math.round(v)} ${metric.unit}`
}

function metricPercent(metric, value) {
  const target = Number(metric.target || 100)
  const current = Number(value || 0)
  if (!target) return clamp(current)
  return clamp(Math.round((current / target) * 100))
}

function runAction(action) {
  if (!selectedLearner.value) return
  message.value = `Đã tạo hành động "${action}" cho ${selectedLearner.value.full_name}`
}

function exportLearnerCsv() {
  if (!selectedLearner.value) return
  const row = selectedLearner.value
  const header = ['learner_id', 'full_name', 'email', 'learner_code', 'status', 'risk_level', 'risk_score', 'progress', 'international_score', 'mooc_score', 'idp_score', 'longlife_score']
  const values = [
    row.id,
    `"${row.full_name}"`,
    `"${row.email}"`,
    row.learner_code,
    row.status,
    row.riskLevel,
    row.riskScore,
    row.overallProgress,
    row.metrics?.international?.score || 0,
    row.metrics?.mooc?.score || 0,
    row.metrics?.idp?.score || 0,
    row.metrics?.longLife?.score || 0,
  ]
  const blob = new Blob([`\uFEFF${header.join(',')}\n${values.join(',')}`], { type: 'text/csv;charset=utf-8;' })
  const url = URL.createObjectURL(blob)
  const link = document.createElement('a')
  link.href = url
  link.setAttribute('download', `learner-${row.id}.csv`)
  document.body.appendChild(link)
  link.click()
  document.body.removeChild(link)
  URL.revokeObjectURL(url)
}

function formatLearnerProfileRows(row) {
  return [
    ['Mã học viên', row.learner_code],
    ['Họ tên', row.full_name],
    ['Email', row.email],
    ['Trạng thái', row.status],
    ['Mức rủi ro', row.riskLevel],
    ['Điểm rủi ro', `${row.riskScore}/100`],
    ['Tiến độ', `${row.overallProgress}%`],
    ['Hoạt động gần nhất', row.lastActiveText],
    ['Khối chặn', row.blocker || 'Không có'],
    ['Chỉ số quốc tế', row.metrics?.international?.score || 0],
    ['Chỉ số MOOC', row.metrics?.mooc?.score || 0],
    ['Chỉ số IDP', row.metrics?.idp?.score || 0],
    ['Chỉ số Long-life', row.metrics?.longLife?.score || 0],
    ...selectedModelMetrics.value.map((metric) => [metric.label, formatMetric(metric, metric.value)]),
  ]
}

function exportLearnerPdf() {
  if (!selectedLearner.value) return

  const row = selectedLearner.value
  const rows = formatLearnerProfileRows(row)
  const html = `
    <html>
      <head>
        <meta charset="utf-8" />
        <title>Hồ sơ học viên</title>
        <style>
          body { font-family: Arial, sans-serif; padding: 24px; color: #111827; }
          h1 { margin: 0 0 16px; }
          .section { margin-bottom: 16px; }
          .section-title { font-weight: 700; margin-bottom: 8px; }
          table { border-collapse: collapse; width: 100%; }
          td { border: 1px solid #e5e7eb; padding: 8px 10px; font-size: 13px; }
        </style>
      </head>
      <body>
        <h1>Hồ sơ học viên theo Learning Path</h1>
        <div class=\"section\">
          <div class=\"section-title\">Thông tin hồ sơ</div>
          <table>${rows.map((item) => `<tr><td>${item[0]}</td><td>${item[1]}</td></tr>`).join('')}</table>
        </div>
        <div class=\"section\">
          <div class=\"section-title\">Canh báo can thiệp nhanh</div>
          <div>${row.nextAction || 'Không có'}</div>
        </div>
      </body>
    </html>
  `
  const tab = window.open('', '_blank')
  if (!tab) {
    error.value = 'Trình duyệt chặn popup, không thể xuất PDF.'
    return
  }
  tab.document.open()
  tab.document.write(html)
  tab.document.close()
  tab.focus()
  tab.print()
}
</script>

<template>
  <EraLmsLayout :sessionUser="sessionUser">
    <template #header>
      <div class="border-b border-slate-200 bg-white">
        <div class="mx-auto flex max-w-7xl flex-col gap-2 px-4 py-5 sm:px-6">
          <p class="text-xs uppercase tracking-[0.22em] text-slate-500">Learning Path</p>
          <h1 class="text-2xl font-bold text-slate-950">Theo dõi tiến độ học viên</h1>
          <p class="text-sm text-slate-600">Mở lại giao diện cũ với danh sách học viên + xem chi tiết từng em.</p>
        </div>
      </div>
    </template>

    <section class="mx-auto max-w-7xl px-4 py-5 sm:px-6 space-y-4">
      <div class="rounded-xl border border-slate-200 bg-white p-4">
        <div class="grid gap-3 sm:grid-cols-[1fr_230px_auto] md:items-end">
          <label class="block">
            <span class="text-xs font-semibold uppercase text-slate-500">Khóa học</span>
            <select
              v-model="selectedCourseId"
              class="mt-1 h-11 w-full rounded-md border border-slate-300 bg-white px-3 text-sm"
              :disabled="loadingCourses"
            >
              <option value="">-- Chọn khóa học --</option>
              <option v-for="course in courses" :key="course.id" :value="String(course.id)">
                {{ course.title }} {{ course.code ? `(${course.code})` : '' }}
              </option>
            </select>
          </label>
          <label class="block">
            <span class="text-xs font-semibold uppercase text-slate-500">Trạng thái</span>
            <select v-model="statusFilter" class="mt-1 h-11 w-full rounded-md border border-slate-300 bg-white px-3 text-sm">
              <option v-for="status in statusOptions" :key="status">{{ status }}</option>
            </select>
          </label>
          <button
            class="h-11 rounded-md bg-slate-900 px-4 text-sm font-semibold text-white"
            :disabled="loadingLearners"
            @click="loadLearners"
          >
            {{ loadingLearners ? 'Đang tải...' : 'Làm mới' }}
          </button>
        </div>

        <div class="mt-4 grid gap-2 sm:grid-cols-3">
          <div class="rounded-md border border-slate-200 bg-slate-50 p-3">
            <p class="text-xs text-slate-500">Tổng học viên</p>
            <p class="mt-1 text-xl font-bold text-slate-900">{{ totalStats.total }}</p>
          </div>
          <div class="rounded-md border border-slate-200 bg-slate-50 p-3">
            <p class="text-xs text-slate-500">Hoàn thành</p>
            <p class="mt-1 text-xl font-bold text-slate-900">{{ totalStats.completed }}</p>
          </div>
          <div class="rounded-md border border-slate-200 bg-slate-50 p-3">
            <p class="text-xs text-slate-500">Rủi ro cao</p>
            <p class="mt-1 text-xl font-bold text-rose-700">{{ totalStats.inRisk }}</p>
          </div>
        </div>
      </div>

      <div v-if="error" class="rounded-md border border-rose-300 bg-rose-50 p-3 text-sm text-rose-700">{{ error }}</div>
      <div v-if="message" class="rounded-md border border-emerald-300 bg-emerald-50 p-3 text-sm text-emerald-700">{{ message }}</div>

      <div class="grid gap-4 xl:grid-cols-[390px_1fr]">
        <section class="rounded-xl border border-slate-200 bg-white p-4">
          <div class="flex flex-wrap gap-2">
            <input
              v-model="search"
              class="h-10 flex-1 rounded-md border border-slate-300 px-3 text-sm"
              placeholder="Tìm tên / email / mã học viên"
            />
            <select v-model="sortBy" class="h-10 rounded-md border border-slate-300 bg-white px-3 text-sm">
              <option value="full_name">Tên</option>
              <option value="overallProgress">Tiến độ (%)</option>
              <option value="riskScore">Risk score</option>
            </select>
            <select v-model="sortDirection" class="h-10 rounded-md border border-slate-300 bg-white px-3 text-sm">
              <option value="asc">Tăng dần</option>
              <option value="desc">Giảm dần</option>
            </select>
          </div>
          <p class="mt-2 text-xs text-slate-500">Hiện có: {{ sortedRows.length }} / {{ learners.length }} học viên</p>
          <div class="mt-3 max-h-[62vh] overflow-auto rounded-md border border-slate-200">
            <table class="min-w-full text-sm">
              <thead class="sticky top-0 bg-slate-50 text-xs uppercase text-slate-500">
                <tr>
                  <th class="px-3 py-2 text-left">Học viên</th>
                  <th class="px-3 py-2 text-left">Trạng thái</th>
                  <th class="px-3 py-2 text-left">Tiến độ</th>
                  <th class="px-3 py-2 text-left">Risk</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-100">
                <tr
                  v-for="row in sortedRows"
                  :key="row.id"
                  class="cursor-pointer hover:bg-slate-50"
                  :class="selectedLearnerId === row.id ? 'bg-slate-100' : ''"
                  @click="selectedLearnerId = row.id"
                >
                  <td class="px-3 py-3">
                    <p class="font-semibold text-slate-900">{{ row.full_name }}</p>
                    <p class="text-xs text-slate-500">{{ row.learner_code }} • {{ row.email }}</p>
                  </td>
                  <td class="px-3 py-3">
                    <span class="inline-flex rounded-full border px-2 py-0.5 text-xs font-semibold" :class="statusTone(row.status)">
                      {{ row.status }}
                    </span>
                  </td>
                  <td class="px-3 py-3">
                    <div class="h-2 w-28 rounded-full bg-slate-100">
                      <div class="h-2 rounded-full bg-cyan-600" :style="{ width: row.overallProgress + '%' }"></div>
                    </div>
                    <p class="mt-1 text-xs text-slate-500">{{ row.overallProgress }}%</p>
                  </td>
                  <td class="px-3 py-3">
                    <span class="inline-flex rounded-full border px-2 py-0.5 text-xs font-semibold" :class="riskTone(row.riskLevel)">
                      {{ row.riskLevel }} ({{ row.riskScore }})
                    </span>
                  </td>
                </tr>
                <tr v-if="!sortedRows.length">
                  <td colspan="4" class="px-3 py-6 text-center text-sm text-slate-500">Không có học viên phù hợp.</td>
                </tr>
              </tbody>
            </table>
          </div>
        </section>

        <section class="space-y-4">
          <div class="rounded-xl border border-slate-200 bg-white p-4">
            <div v-if="selectedLearner">
              <div class="flex items-start justify-between gap-3">
                <div>
                  <p class="text-xs uppercase text-slate-500">Chi tiết học viên</p>
                  <h2 class="text-lg font-bold text-slate-900">{{ selectedLearner.full_name }}</h2>
                  <p class="text-sm text-slate-600">{{ selectedLearner.email }}</p>
                </div>
                <span class="rounded-full border border-slate-200 px-2 py-1 text-sm">{{ selectedLearner.status }}</span>
              </div>
              <div class="mt-3 grid gap-2 sm:grid-cols-3">
                <div class="rounded-md border border-slate-200 p-3">
                  <p class="text-xs text-slate-500">Tiến độ</p>
                  <p class="mt-1 text-xl font-bold">{{ selectedLearner.overallProgress }}%</p>
                </div>
                <div class="rounded-md border border-slate-200 p-3">
                  <p class="text-xs text-slate-500">Risk</p>
                  <p class="mt-1 text-xl font-bold">{{ selectedLearner.riskScore }}</p>
                </div>
                <div class="rounded-md border border-slate-200 p-3">
                  <p class="text-xs text-slate-500">Hoạt động gần nhất</p>
                  <p class="mt-1 text-sm font-semibold">{{ selectedLearner.lastActiveText }}</p>
                </div>
              </div>
            </div>
            <div v-else class="rounded-md border border-dashed border-slate-300 p-6 text-sm text-slate-500 text-center">
              Chọn một khóa học và click học viên để xem chi tiết.
            </div>
          </div>

          <div class="rounded-xl border border-slate-200 bg-white p-4">
            <div class="flex gap-2">
              <button
                v-for="tab in detailTabs"
                :key="tab.id"
                class="rounded-md px-3 py-1.5 text-sm font-semibold"
                :class="activeDetailTab === tab.id ? 'bg-slate-900 text-white' : 'border border-slate-200 text-slate-700'"
                @click="activeDetailTab = tab.id"
              >
                {{ tab.label }}
              </button>
            </div>

            <div class="mt-4">
              <div v-if="activeDetailTab === 'overview' && selectedLearner">
                <div class="grid gap-2 sm:grid-cols-2">
                  <div class="rounded-md border border-slate-200 p-3">
                    <p class="text-xs text-slate-500">Risk level</p>
                    <span class="mt-1 inline-flex rounded-full border px-2 py-1 text-sm font-semibold" :class="riskTone(selectedLearner.riskLevel)">
                      {{ selectedLearner.riskLevel }}
                    </span>
                  </div>
                  <div class="rounded-md border border-slate-200 p-3">
                    <p class="text-xs text-slate-500">Dữ liệu từ hệ thống</p>
                    <ul class="mt-1 text-sm text-slate-700">
                      <li>SCORM/xAPI: {{ selectedLearner?.sources?.lrsRecords || 0 }}</li>
                      <li>Caliper: {{ selectedLearner?.sources?.caliperEvents || 0 }}</li>
                      <li>xAPI statements: {{ selectedLearner?.sources?.xapiStatements || 0 }}</li>
                    </ul>
                  </div>
                </div>
                <ul class="mt-3 list-disc pl-5 text-sm text-slate-600">
                  <li v-for="act in selectedLearner?.activities" :key="act">{{ act }}</li>
                </ul>
                <div class="mt-4 flex gap-2">
                  <button class="rounded-md bg-emerald-700 px-3 py-2 text-sm font-semibold text-white" @click="exportLearnerCsv">Xuất CSV</button>
                  <button class="rounded-md bg-cyan-700 px-3 py-2 text-sm font-semibold text-white" @click="exportLearnerPdf">Xuất PDF</button>
                </div>
              </div>

              <div v-else-if="activeDetailTab === 'metrics' && selectedLearner" class="space-y-3">
                <div class="flex flex-wrap gap-2">
                  <button
                    v-for="model in modelTabs"
                    :key="model.id"
                    class="rounded-full border px-4 py-1 text-sm"
                    :class="activeModelTab === model.id ? 'bg-slate-900 text-white' : 'text-slate-700 border-slate-200'"
                    @click="activeModelTab = model.id"
                  >
                    {{ model.shortLabel }}
                  </button>
                </div>
                <div class="grid gap-3">
                  <div v-for="metric in selectedModelMetrics" :key="`${activeModelTab}-${metric.key}`" class="rounded-md border border-slate-200 p-3">
                    <div class="flex items-center justify-between">
                      <p class="font-semibold text-slate-800">{{ metric.label }}</p>
                      <span class="inline-flex rounded-full border px-2 py-0.5 text-xs font-semibold" :class="metricTone(metric.value, metric.target)">
                        {{ formatMetric(metric, metric.value) }} / target {{ metric.target }}{{ metric.unit === '/5' ? '' : metric.unit }}
                      </span>
                    </div>
                    <div class="mt-2 h-2 rounded-full bg-slate-100">
                      <div class="h-2 rounded-full bg-cyan-600" :style="{ width: `${metricPercent(metric, metric.value)}%` }"></div>
                    </div>
                  </div>
                </div>
              </div>

              <div v-else-if="activeDetailTab === 'related' && selectedLearner" class="grid gap-2">
                <div class="rounded-md border border-slate-200 p-3"><span class="text-xs text-slate-500">Mã học viên:</span> {{ selectedLearner.learner_code }}</div>
                <div class="rounded-md border border-slate-200 p-3"><span class="text-xs text-slate-500">Khóa học:</span> {{ selectedCourse?.title || 'N/A' }}</div>
                <div class="rounded-md border border-slate-200 p-3">
                  <p class="text-xs text-slate-500">Can thiệp nhanh</p>
                  <div class="mt-2 grid gap-2 sm:grid-cols-2">
                    <button class="rounded-md bg-cyan-700 px-3 py-2 text-sm text-white" @click="runAction('Nhắc nhở')">Nhắc nhở</button>
                    <button class="rounded-md bg-emerald-700 px-3 py-2 text-sm text-white" @click="runAction('Mentor')">Mentor</button>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </section>
      </div>
    </section>
  </EraLmsLayout>
</template>
