<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import EraLmsLayout from '@/Layouts/EraLmsLayout.vue'

const props = defineProps({
  sessionUser: { type: Object, default: null },
  apiHeaders: { type: Object, default: () => ({}) },
})

const mainTabs = [
  { id: 'learners', label: 'Theo dõi học viên' },
  { id: 'rules', label: 'Thiết lập lộ trình (old builder)' },
]

const modelTabs = [
  {
    id: 'international',
    label: 'Chỉ số quốc tế',
    shortLabel: 'International',
    description: 'Theo dõi completion, activity signals, caliper/xAPI-style và tốc độ học tập.',
    metrics: [
      { key: 'completionRate', label: 'Completion Rate', unit: '%', target: 80, kind: 'percent' },
      { key: 'xapiEvents', label: 'Tín hiệu hoạt động', unit: 'sự kiện', target: 80, kind: 'number' },
      { key: 'scormConformance', label: 'Conformance score', unit: 'điểm', target: 75, kind: 'number' },
      { key: 'caliperQuality', label: 'Quality score', unit: 'điểm', target: 75, kind: 'number' },
      { key: 'completionVelocity', label: 'Tốc độ hoàn thành', unit: 'giờ/tuần', target: 12, kind: 'number' },
    ],
  },
  {
    id: 'mooc',
    label: 'MOOC',
    shortLabel: 'MOOC',
    description: 'Đánh giá chuẩn MOOC: engagement, watch rate, quiz, assignment.',
    metrics: [
      { key: 'watchRate', label: 'Tỷ lệ xem video', unit: '%', target: 78, kind: 'percent' },
      { key: 'forumEngagement', label: 'Forum engagement', unit: 'điểm', target: 60, kind: 'number' },
      { key: 'quizMastery', label: 'Quiz pass rate', unit: '%', target: 70, kind: 'percent' },
      { key: 'assignmentQuality', label: 'Assignment quality', unit: '%', target: 80, kind: 'percent' },
      { key: 'peerReview', label: 'Peer review', unit: '/5', target: 3.5, kind: 'rating' },
    ],
  },
  {
    id: 'idp',
    label: 'IDP',
    shortLabel: 'IDP',
    description: 'Tiến bộ mục tiêu phát triển cá nhân: tiến độ milestone, mentor, skill gap.',
    metrics: [
      { key: 'goalAlignment', label: 'Goal alignment', unit: '%', target: 80, kind: 'percent' },
      { key: 'milestoneProgress', label: 'Milestone completed', unit: '%', target: 70, kind: 'percent' },
      { key: 'mentorCheckins', label: 'Mentor check-ins', unit: 'lần', target: 2, kind: 'number' },
      { key: 'skillGapReduction', label: 'Giảm skill gap', unit: '%', target: 30, kind: 'percent' },
      { key: 'selfAssessment', label: 'Self assessment', unit: '/5', target: 4, kind: 'rating' },
    ],
  },
  {
    id: 'longLife',
    label: 'Long-life',
    shortLabel: 'Long-life',
    description: 'Chỉ báo học tập suốt đời: micro-learning, portfolio, badges.',
    metrics: [
      { key: 'microLearningHours', label: 'Micro-learning', unit: 'giờ', target: 20, kind: 'number' },
      { key: 'portfolioArtifacts', label: 'Portfolio artifacts', unit: 'mục', target: 4, kind: 'number' },
      { key: 'externalBadges', label: 'External badges', unit: 'chứng chỉ', target: 2, kind: 'number' },
      { key: 'learningCircles', label: 'Learning circles', unit: 'nhóm', target: 3, kind: 'number' },
      { key: 'crossRoleProjects', label: 'Cross-role tasks', unit: 'nhiệm vụ', target: 2, kind: 'number' },
    ],
  },
]

const detailTabs = [
  { id: 'metrics', label: 'Chỉ số' },
  { id: 'related', label: 'Dữ liệu liên quan' },
  { id: 'intervention', label: 'Can thiệp' },
]

const statusOptions = ['Tất cả', 'Đang học', 'Hoàn thành', 'Bị khóa', 'Bị kẹt', 'Chưa bắt đầu', 'Rủi ro cao']
const sortOptions = [
  { key: 'full_name', label: 'Tên học viên', type: 'text' },
  { key: 'overallProgress', label: 'Tiến độ tổng', type: 'number' },
  { key: 'riskScore', label: 'Rủi ro', type: 'number' },
  { key: 'international.score', label: 'Điểm International', type: 'number' },
  { key: 'mooc.score', label: 'Điểm MOOC', type: 'number' },
  { key: 'idp.score', label: 'Điểm IDP', type: 'number' },
  { key: 'longLife.score', label: 'Điểm Long-life', type: 'number' },
]

const requirementTemplates = [
  { type: 'component_completed', label: 'Hoàn thành component', minKey: null, valueLabel: 'Component yêu cầu' },
  { type: 'quiz_score_min', label: 'Quiz đạt điểm tối thiểu', minKey: 'min_score', minDefault: 70, valueLabel: 'Quiz yêu cầu' },
  { type: 'assignment_score_min', label: 'Assignment đạt điểm tối thiểu', minKey: 'min_score', minDefault: 70, valueLabel: 'Assignment yêu cầu' },
  { type: 'video_watch_percent', label: 'Xem video tối thiểu', minKey: 'min_percent', minDefault: 90, valueLabel: 'Video yêu cầu' },
  { type: 'manual_approval', label: 'Giảng viên duyệt thủ công', minKey: null, valueLabel: 'Vai trò phê duyệt' },
  { type: 'date_after', label: 'Mở sau ngày', minKey: null, valueLabel: 'Thời điểm mở' },
]

const activeMainTab = ref('learners')
const activeModelTab = ref('international')
const activeDetailTab = ref('metrics')

const search = ref('')
const statusFilter = ref('Tất cả')
const sortBy = ref('overallProgress')
const sortDirection = ref('desc')
const selectedLearnerId = ref('')

const courses = ref([])
const selectedCourseId = ref(null)
const selectedCourse = computed(() => courses.value.find((course) => String(course.id) === String(selectedCourseId.value)) || null)
const pathItems = ref([])
const learners = ref([])
const rules = ref([])
const loading = ref(false)
const savingRule = ref(false)
const isBootstrapped = ref(false)
const message = ref('')
const error = ref('')
const selectedRuleId = ref('')
const activeRulePanel = ref('edit')

const ruleForm = ref({
  title: '',
  description: '',
  target_type: 'course_component',
  target_id: null,
  rule_type: 'sequential',
  config: {
    unlock_behavior: 'all_required',
    message_locked: 'Bạn cần hoàn thành điều kiện tiên quyết trước khi mở nội dung này.',
    requires: [],
    adaptive_routes: [],
  },
})

const activeModel = computed(() => modelTabs.find((model) => model.id === activeModelTab.value) || modelTabs[0])

const filteredRows = computed(() => learners.value.filter((row) => {
  if (statusFilter.value !== 'Tất cả' && statusForFilter(row) !== statusFilter.value) return false

  const q = search.value.trim().toLowerCase()
  if (!q) return true
  return (
    row.full_name.toLowerCase().includes(q)
    || row.email.toLowerCase().includes(q)
    || (row.learner_code || '').toLowerCase().includes(q)
    || (row.cohort || '').toLowerCase().includes(q)
  )
}))

const sortedRows = computed(() => {
  const rows = [...filteredRows.value]
  const direction = sortDirection.value === 'asc' ? 1 : -1

  return rows.sort((left, right) => {
    const leftValue = getCellValue(left, sortBy.value)
    const rightValue = getCellValue(right, sortBy.value)

    if (typeof leftValue === 'number' && typeof rightValue === 'number') {
      return (leftValue - rightValue) * direction
    }
    return String(leftValue || '').localeCompare(String(rightValue || '')) * direction
  })
})

const selectedLearner = computed(() => {
  return learners.value.find((row) => row.id === selectedLearnerId.value) || sortedRows.value[0] || null
})

const dashboard = computed(() => {
  const rows = filteredRows.value
  const total = rows.length
  const avgProgress = total ? Math.round(rows.reduce((sum, row) => sum + Number(row.overallProgress || 0), 0) / total) : 0
  const highRisk = rows.filter((row) => row.riskLevel === 'Cao').length
  const completeRate = total ? Math.round((rows.filter((row) => row.status === 'Hoàn thành').length / total) * 100) : 0
  const internationalReady = rows.filter((row) => row.metrics?.international?.score >= 80).length
  return { total, avgProgress, highRisk, completeRate, internationalReady }
})

const selectedModelMetrics = computed(() => {
  const row = selectedLearner.value
  if (!row) return []
  return activeModel.value.metrics.map((metric) => ({
    ...metric,
    value: getCellValue(row.metrics?.[activeModel.value.id], metric.key),
  }))
})

const relatedData = computed(() => {
  const row = selectedLearner.value
  if (!row) return []
  return [
    { label: 'Mã học viên', value: row.learner_code || '-' },
    { label: 'Môn học', value: row.course_code || '-' },
    { label: 'Enrollment ID', value: row.id },
    { label: 'Hoạt động gần nhất', value: row.lastActive },
    { label: 'Risk score', value: `${row.riskScore}/100` },
    { label: 'Nhãn trạng thái', value: row.status },
  ]
})

const ruleTargets = computed(() => {
  if (!pathItems.value.length) return []
  return [
    ...pathItems.value.filter((item) => item.kind === 'section').map((item) => ({
      value: item.id,
      kind: 'course_section',
      label: `Section: ${item.section} · ${item.title}`,
    })),
    ...pathItems.value.filter((item) => item.kind === 'component').map((item) => ({
      value: item.id,
      kind: 'course_component',
      label: `Component: ${item.section} · ${item.title}`,
    })),
  ]
})

const sectionOptions = computed(() => pathItems.value.filter((item) => item.kind === 'section'))
const componentOptions = computed(() => pathItems.value.filter((item) => item.kind === 'component'))

const selectedRule = computed(() => rules.value.find((rule) => String(rule.id) === String(selectedRuleId.value)) || null)

const selectedRuleTarget = computed(() => {
  if (!selectedRule.value) return null
  const type = selectedRule.value.target_type
  const key = Number(selectedRule.value.target_id)
  return ruleTargets.value.find((target) => target.kind === type && Number(target.value) === key) || null
})

const selectedTargetItem = computed(() => {
  const type = ruleForm.value.target_type
  const targetType = type === 'course_section' ? 'section' : 'component'
  return pathItems.value.find((item) => item.kind === targetType && item.id === Number(ruleForm.value.target_id)) || null
}
)

watch(
  selectedCourseId,
  (courseId) => {
    if (!courseId) return
    refreshCourseContext()
  },
  { immediate: true },
)

watch(
  () => selectedLearner.value?.id,
  (current) => {
    if (current) selectedLearnerId.value = current
  },
)

onMounted(loadCourses)

async function loadCourses() {
  loading.value = true
  error.value = ''

  try {
    const payload = await fetchJson('/api/v1/courses?status=published&per_page=100&order=updated_at')
    const items = dataList(payload)
    courses.value = items.map((course) => ({
      id: course.id,
      title: course.title || `Khóa học #${course.id}`,
      code: course.code,
      owner_name: course.owner?.full_name || 'N/A',
      standard: course.standard || 'SCORM/xAPI Ready',
    }))
    if (!selectedCourseId.value && courses.value.length) {
      selectedCourseId.value = String(courses.value[0].id)
    }
  } catch (err) {
    error.value = err.message || 'Không load được danh sách khóa học.'
  } finally {
    loading.value = false
  }
}

function parsePayload(payload) {
  return extractPayloadArray(payload)
}

function extractPayloadArray(payload) {
  if (!payload) return []
  if (Array.isArray(payload)) return payload
  if (Array.isArray(payload.data?.data)) return payload.data.data
  if (Array.isArray(payload.data)) return payload.data
  if (Array.isArray(payload.result)) return payload.result
  if (Array.isArray(payload.items)) return payload.items
  if (Array.isArray(payload.rows)) return payload.rows
  if (Array.isArray(payload.payload)) return payload.payload
  return []
}

function dataList(payload) {
  return parsePayload(payload) || []
}

async function fetchJson(url, options = {}) {
  const response = await fetch(url, {
    ...options,
    headers: {
      Accept: 'application/json',
      ...props.apiHeaders,
      ...(options.headers || {}),
    },
  })
  const payload = await response.json().catch(() => ({}))
  if (!response.ok) throw new Error(payload?.message || `Lỗi HTTP ${response.status}`)
  return payload
}

async function refreshCourseContext() {
  if (!selectedCourseId.value) return

  loading.value = true
  error.value = ''
  message.value = ''
  isBootstrapped.value = true

  try {
    const perCourseParams = new URLSearchParams({
      course_id: String(selectedCourseId.value),
      per_page: '300',
    })
    const [enrollmentsResp, progressResp, metricsResp, risksResp, studioResp, rulesResp] = await Promise.allSettled([
      fetchJson(`/api/v1/enrollment/records?${perCourseParams.toString()}`),
      fetchJson(`/api/v1/courses/${selectedCourseId.value}/progress/users?per_page=300`),
      fetchJson(`/api/v1/analytics/metrics?course_id=${selectedCourseId.value}&per_page=300`),
      fetchJson(`/api/v1/analytics/risks?course_id=${selectedCourseId.value}&per_page=300`),
      fetchJson(`/api/v1/courses/${selectedCourseId.value}/studio`),
      fetchJson(`/api/v1/courses/${selectedCourseId.value}/learning-path/rules`),
    ])

    const failedEndpoints = []
    if (enrollmentsResp.status === 'rejected') failedEndpoints.push(`enrollment/records: ${enrollmentsResp.reason?.message || 'Không phản hồi'}`)
    if (progressResp.status === 'rejected') failedEndpoints.push(`progress/users: ${progressResp.reason?.message || 'Không phản hồi'}`)
    if (metricsResp.status === 'rejected') failedEndpoints.push(`analytics/metrics: ${metricsResp.reason?.message || 'Không phản hồi'}`)
    if (risksResp.status === 'rejected') failedEndpoints.push(`analytics/risks: ${risksResp.reason?.message || 'Không phản hồi'}`)
    if (studioResp.status === 'rejected') failedEndpoints.push(`courses/${selectedCourseId.value}/studio: ${studioResp.reason?.message || 'Không phản hồi'}`)
    if (rulesResp.status === 'rejected') failedEndpoints.push(`learning-path/rules: ${rulesResp.reason?.message || 'Không phản hồi'}`)

    const enrollmentRows = enrollmentsResp.status === 'fulfilled' ? dataList(enrollmentsResp.value) : []
    const progressRows = progressResp.status === 'fulfilled' ? dataList(progressResp.value) : []
    const metricsRows = metricsResp.status === 'fulfilled' ? dataList(metricsResp.value) : []
    const riskRows = risksResp.status === 'fulfilled' ? dataList(risksResp.value) : []
    const studioPayload = studioResp.status === 'fulfilled' ? studioResp.value : null
    const rulesPayload = rulesResp.status === 'fulfilled' ? parsePayload(rulesResp.value) : []

    const progressByUser = new Map()
    progressRows.forEach((row) => progressByUser.set(Number(row.user_id), row))

    const latestMetricByUser = new Map()
    metricsRows.forEach((row) => {
      const key = Number(row.user_id)
      if (!latestMetricByUser.has(key)) {
        latestMetricByUser.set(key, row)
      }
    })

    const latestRiskByUser = new Map()
    riskRows.forEach((row) => latestRiskByUser.set(Number(row.learner?.id || row.user_id), row))

    learners.value = enrollmentRows.map((record) => buildLearnerRow(record, progressByUser.get(Number(record.user_id)), latestMetricByUser.get(Number(record.user_id)), latestRiskByUser.get(Number(record.user_id))))

    if (learners.value.length && !selectedLearnerId.value) {
      selectedLearnerId.value = learners.value[0].id
    }

    const studio = studioPayload || {}
    const outline = studio.outline || studioPayload?.data?.outline || []
    pathItems.value = flattenOutline(outline)
    rules.value = Array.isArray(rulesPayload) ? rulesPayload.map((rule) => ({
      ...rule,
      is_active: !!rule.is_active,
    })) : []

    if (rules.value.length) {
      selectedRuleId.value = String(rules.value[0].id)
      loadRuleForm(rules.value[0])
    } else {
      selectedRuleId.value = ''
      resetRuleForm()
    }

    if (failedEndpoints.length) {
      error.value = `Một số API chưa phản hồi: ${failedEndpoints.join('; ')}`
    }
  } catch (err) {
    error.value = err.message || 'Không thể nạp dữ liệu khóa học.'
  } finally {
    loading.value = false
  }
}

function flattenOutline(nodes, parentTitle = '') {
  if (!Array.isArray(nodes)) return []
  return nodes.flatMap((section) => {
    const title = section.title || `Phần ${section.id}`
    const label = parentTitle ? `${parentTitle} / ${title}` : title
    const child = flattenOutline(section.children || [], label)
    const sectionNode = {
      id: Number(section.id),
      kind: 'section',
      title,
      section: label,
      code: `S-${section.id}`,
      item_type: section.type || 'section',
    }
    const components = Array.isArray(section.components) ? section.components.map((component) => ({
      id: Number(component.id),
      kind: 'component',
      title: component.title || `Component ${component.id}`,
      section: `${label}`,
      code: `${component.component_type?.toUpperCase() || 'COM'}-${component.id}`,
      item_type: component.component_type,
    })) : []
    return [sectionNode, ...components, ...child]
  })
}

function statusForFilter(row) {
  if (row.status === 'Đang học') return 'Đang học'
  if (row.status === 'Đã hoàn thành') return 'Hoàn thành'
  if (row.status === 'Bị khóa') return 'Bị khóa'
  if (row.riskLevel === 'Cao') return 'Rủi ro cao'
  if (Number(row.overallProgress || 0) === 0) return 'Chưa bắt đầu'
  if (row.riskScore >= 70) return 'Bị kẹt'
  return 'Đang học'
}

function buildLearnerRow(record, progress, metricRow, riskRow) {
  const user = record.learner || {}
  const code = user.code || `SV-${record.user_id || user.id}`
  const name = user.full_name || user.name || `Học viên ${code}`
  const email = user.email || ''
  const completion = Number(record.completion_percent ?? progress?.progress_percent ?? 0)
  const riskScore = Number(record.risk_score ?? riskRow?.risk_score ?? 0)
  const riskLevel = toRiskLevel(riskScore)
  const status = mapStatus(record.status)
  const lastAccessAt = progress?.last_accessed_at || user?.metadata?.last_active_at || null
  const metric = buildMetrics(progress, metricRow, riskScore)

  const base = {
    id: `enroll-${record.id}`,
    learner_id: Number(record.user_id || record.id),
    raw_enrollment_id: record.id,
    full_name: name,
    email,
    learner_code: code,
    cohort: user.metadata?.cohort || record.classSection?.name || 'N/A',
    overallProgress: clamp(Math.round(Number(completion || 0))),
    riskScore: Math.round(clamp(riskScore)),
    riskLevel,
    status,
    last_accessed_at: lastAccessAt,
    lastActive: formatLastActive(lastAccessAt),
    lastActiveHours: toElapsedHours(lastAccessAt),
    blocker: record.metadata?.blocker || (riskLevel === 'Cao' ? 'Cần can thiệp học tập' : 'Không có'),
    nextAction: nextActionByRisk(riskLevel, completion),
    overallEvent: record.metadata?.overall_event || 'Đang theo dõi hoạt động học tập',
    metrics: metric,
    course_code: record.course?.code || selectedCourse.value?.code || '',
    course_title: record.course?.title || selectedCourse.value?.title || '',
    sources: {
      lrsRecords: metric?.international?.xapiEvents || 0,
      caliperEvents: metric?.international?.caliperQuality || 0,
      xapiStatements: metric?.international?.xapiEvents || 0,
      completionEvidence: `${metric?.international?.completionRate || 0}%`,
      courseSignals: metric?.international?.scormConformance || 0,
    },
    interventions: riskRow?.recommendations || [
      'Đặt reminder học lại hoạt động bắt buộc.',
      'Theo dõi điểm quiz và assignment hàng ngày.',
      'Cập nhật mentor review theo khối chặn.',
    ],
    activity: (user.metadata?.recent_activity || []).length
      ? user.metadata.recent_activity
      : ['Không có sự kiện mới trong khoảng thời gian vừa qua'],
  }

  return base
}

function buildMetrics(progress, metricRow, riskScore) {
  const completion = clamp(Number(progress?.progress_percent || 0))
  const loginFrequency = Number(metricRow?.login_frequency || 0)
  const studyMinutes = Number(metricRow?.study_time_minutes || 0)
  const videoCompletion = normalizePercent(metricRow?.video_completion || 0)
  const assignmentCompletion = normalizePercent(metricRow?.assignment_completion || 0)
  const quizScore = normalizePercent(metricRow?.quiz_score || 0)
  const attendance = normalizePercent(metricRow?.attendance || 0)
  const forumActivity = Number(metricRow?.forum_activity || 0)

  const international = {
    completionRate: completion,
    xapiEvents: Math.round(loginFrequency + studyMinutes / 20),
    scormConformance: Number(((completion + attendance) / 2).toFixed(1)),
    caliperQuality: Number(((videoCompletion + assignmentCompletion + quizScore) / 3).toFixed(1)),
    completionVelocity: Number((studyMinutes / 60).toFixed(1)),
  }
  international.score = Math.round((international.completionRate * 0.45) + (international.scormConformance * 0.25) + (international.caliperQuality * 0.2) + clamp(Math.min(100, international.xapiEvents)))

  const mooc = {
    watchRate: videoCompletion,
    forumEngagement: clamp(forumActivity * 10),
    quizMastery: quizScore,
    assignmentQuality: assignmentCompletion,
    peerReview: Math.round(clamp(riskScore ? (riskScore < 70 ? 4 : 3.1) : 0) * 10) / 10,
  }
  mooc.score = Math.round((mooc.watchRate * 0.35) + (mooc.forumEngagement * 0.25) + (mooc.quizMastery * 0.2) + (mooc.assignmentQuality * 0.2))

  const idp = {
    goalAlignment: clamp(100 - clamp(riskScore) * 0.7),
    milestoneProgress: completion,
    mentorCheckins: clamp(Math.round(loginFrequency / 7)),
    skillGapReduction: clamp(100 - clamp(riskScore)),
    selfAssessment: Number((clamp(100 - clamp(riskScore)) / 20).toFixed(1)),
  }
  idp.score = Math.round((idp.goalAlignment * 0.35) + (idp.milestoneProgress * 0.35) + (idp.skillGapReduction * 0.25) + (idp.selfAssessment * 10))

  const longLife = {
    microLearningHours: Number((studyMinutes / 60).toFixed(1)),
    portfolioArtifacts: 0,
    externalBadges: 0,
    learningCircles: 0,
    crossRoleProjects: 0,
  }
  longLife.score = Math.round(
    clamp(longLife.microLearningHours * 4)
    + clamp(longLife.portfolioArtifacts * 12)
    + clamp(longLife.externalBadges * 10)
    + clamp(longLife.learningCircles * 8)
    + clamp(longLife.crossRoleProjects * 8),
  )
  longLife.score = Math.min(longLife.score, 100)

  return { international, mooc, idp, longLife }
}

function toRiskLevel(score) {
  const normalized = clamp(Number(score || 0))
  if (normalized >= 70) return 'Cao'
  if (normalized >= 40) return 'Trung bình'
  return 'Thấp'
}

function mapStatus(status) {
  const normalized = String(status || '')
  if (normalized === 'completed') return 'Đã hoàn thành'
  if (normalized === 'active') return 'Đang học'
  if (normalized === 'suspended') return 'Bị khóa'
  if (normalized === 'pending') return 'Chưa bắt đầu'
  if (normalized === 'withdrawn' || normalized === 'expired') return 'Bị rút'
  return 'Đang học'
}

function normalizePercent(value) {
  const raw = Number(value || 0)
  if (raw > 1) return clamp(raw)
  return clamp(raw * 100)
}

function clamp(value) {
  const n = Number(value || 0)
  return Math.min(100, Math.max(0, Number.isFinite(n) ? n : 0))
}

function toElapsedHours(dateValue) {
  if (!dateValue) return null
  const at = new Date(dateValue).getTime()
  if (Number.isNaN(at)) return null
  return Math.max(0, Math.round((Date.now() - at) / (3600 * 1000)))
}

function formatLastActive(dateValue) {
  if (!dateValue) return 'Không có dữ liệu'
  const value = new Date(dateValue)
  if (Number.isNaN(value.getTime())) return 'Không có dữ liệu'
  return new Intl.DateTimeFormat('vi-VN', { dateStyle: 'medium', timeStyle: 'short' }).format(value)
}

function getCellValue(row, path) {
  if (!row) return ''
  return String(path).split('.').reduce((value, key) => (value == null ? '' : value[key]), row)
}

function riskTone(level) {
  if (level === 'Cao') return 'bg-rose-50 text-rose-700 border-rose-200'
  if (level === 'Trung bình') return 'bg-amber-50 text-amber-700 border-amber-200'
  return 'bg-emerald-50 text-emerald-700 border-emerald-200'
}

function metricTone(value, target) {
  const numeric = Number(value || 0)
  if (!Number.isFinite(numeric)) return 'text-slate-700 bg-slate-50'
  if (numeric >= target) return 'text-emerald-700 bg-emerald-50'
  if (numeric >= target * 0.8) return 'text-amber-700 bg-amber-50'
  return 'text-rose-700 bg-rose-50'
}

function progressPercent(metric, value) {
  if (metric.unit === '%') return clamp(Number(value || 0))
  if (metric.unit === '/5') return clamp((Number(value || 0) / 5) * 100)
  if (Number.isFinite(Number(metric.target || 0)) && Number(metric.target || 0) > 0) {
    return clamp((Number(value || 0) / Number(metric.target)) * 100)
  }
  return 0
}

function formatMetricValue(metric, value) {
  if (value === undefined || value === null || Number.isNaN(Number(value))) return '—'
  if (metric.unit === '%') return `${Math.round(Number(value))}%`
  if (metric.unit === '/5') return `${Number(value)} / 5`
  return `${Number(value)} ${metric.unit}`.trim()
}

function selectLearner(row) {
  selectedLearnerId.value = row.id
  message.value = `Đang xem chi tiết ${row.full_name} theo nhóm ${activeModel.value.label}.`
}

function nextActionByRisk(level, progress) {
  if (progress >= 100) return 'Cấp chứng chỉ'
  if (level === 'Cao') return 'Nhắc học & lên lịch mentor'
  if (level === 'Trung bình') return 'Theo dõi weekly'
  return 'Duy trì'
}

function resetRuleForm() {
  ruleForm.value = {
    title: '',
    description: '',
    target_type: 'course_component',
    target_id: null,
    rule_type: 'sequential',
    config: {
      unlock_behavior: 'all_required',
      message_locked: 'Bạn cần hoàn thành điều kiện tiên quyết trước khi mở nội dung này.',
      requires: [],
      adaptive_routes: [],
    },
  }
}

function loadRuleForm(rule) {
  if (!rule) {
    resetRuleForm()
    return
  }

  ruleForm.value = {
    title: rule.title || '',
    description: rule.description || '',
    target_type: rule.target_type || 'course_component',
    target_id: rule.target_id ? Number(rule.target_id) : null,
    rule_type: rule.rule_type || 'sequential',
    config: {
      unlock_behavior: rule.config?.unlock_behavior || 'all_required',
      message_locked: rule.config?.message_locked || 'Bạn cần hoàn thành điều kiện tiên quyết trước khi mở nội dung này.',
      requires: (rule.config?.requires || []).map((item) => ({
        ...item,
        id: item.id || newRandomId(),
      })),
      adaptive_routes: (rule.config?.adaptive_routes || []).map((route) => ({
        ...route,
        id: route.id || newRandomId(),
        target_type: route.target_type || 'course_component',
        target_id: Number(route.target_id || 0) || null,
      })),
    },
  }
}

function templateFor(type) {
  return requirementTemplates.find((template) => template.type === type) || requirementTemplates[0]
}

function addRequirement(type) {
  const template = templateFor(type)
  const next = {
    id: newRandomId(),
    type,
    component_id: template.valueLabel ? null : null,
  }
  if (template.minKey) next[template.minKey] = template.minDefault
  if (template.type === 'date_after') next.unlock_at = new Date().toISOString().slice(0, 10)
  if (componentOptions.value.length) {
    next.component_id = next.component_id || componentOptions.value[0].id
  }
  ruleForm.value.config.requires.push(next)
}

function removeRequirement(id) {
  ruleForm.value.config.requires = ruleForm.value.config.requires.filter((row) => row.id !== id)
}

function addAdaptiveRoute() {
  ruleForm.value.config.adaptive_routes.push({
    id: newRandomId(),
    when: 'score < 70',
    target_type: 'course_component',
    target_id: componentOptions.value.length ? componentOptions.value[0].id : null,
  })
}

function removeAdaptiveRoute(id) {
  ruleForm.value.config.adaptive_routes = ruleForm.value.config.adaptive_routes.filter((route) => route.id !== id)
}

function describeRequirement(requirement) {
  const template = templateFor(requirement.type)
  if (requirement.type === 'manual_approval') return `${template.label}`
  if (requirement.type === 'date_after') return `Mở sau ngày ${requirement.unlock_at || 'đã chọn'}`
  if (requirement.type === 'quiz_score_min') return `${findComponentLabel(requirement.component_id)} đạt tối thiểu ${requirement.min_score || 0} điểm`
  if (requirement.type === 'assignment_score_min') return `${findComponentLabel(requirement.component_id)} đạt tối thiểu ${requirement.min_score || 0} điểm`
  if (requirement.type === 'video_watch_percent') return `${findComponentLabel(requirement.component_id)} xem tối thiểu ${requirement.min_percent || 0}%`
  return `${template.label}: ${findComponentLabel(requirement.component_id || requirement.target_id)}`
}

function findComponentLabel(componentId) {
  const item = componentOptions.value.find((item) => item.id === Number(componentId))
  return item ? item.title : 'component đã chọn'
}

function validateRule() {
  if (!selectedCourseId.value) return 'Chưa có khóa học để lưu rule.'
  if (!ruleForm.value.title.trim()) return 'Vui lòng nhập tên rule.'
  if (!ruleForm.value.target_type) return 'Vui lòng chọn target type.'
  if (ruleForm.value.target_type !== 'course' && !ruleForm.value.target_id) return 'Vui lòng chọn mục tiêu.'
  if (!['course_component', 'course_section', 'course'].includes(ruleForm.value.target_type)) return 'Target type không hợp lệ.'
  if (!Array.isArray(ruleForm.value.config.requires) || !ruleForm.value.config.requires.length) {
    return 'Phải có ít nhất 1 điều kiện mở khóa.'
  }
  if (ruleForm.value.config.requires.some((requirement) => ['component_completed', 'quiz_score_min', 'assignment_score_min', 'video_watch_percent'].includes(requirement.type) && !requirement.component_id)) {
    return 'Một số điều kiện chưa có component.'
  }
  return ''
}

function payloadRule() {
  const normalizedTargetId = ruleForm.value.target_type === 'course'
    ? null
    : Number(ruleForm.value.target_id) || null

  return {
    target_type: ruleForm.value.target_type,
    target_id: normalizedTargetId,
    rule_type: ruleForm.value.rule_type,
    title: ruleForm.value.title,
    description: ruleForm.value.description || null,
    config: {
      unlock_behavior: ruleForm.value.config.unlock_behavior,
      message_locked: ruleForm.value.config.message_locked,
      requires: ruleForm.value.config.requires.map((item) => {
        const normalized = { ...item }
        delete normalized.id
        if (normalized.component_id === '') delete normalized.component_id
        if (normalized.target_id === '') delete normalized.target_id
        return normalized
      }),
      adaptive_routes: ruleForm.value.config.adaptive_routes.map((route) => {
        const normalized = { ...route }
        delete normalized.id
        if (normalized.target_id === '') delete normalized.target_id
        return normalized
      }),
    },
  }
}

async function saveRule() {
  const validation = validateRule()
  if (validation) {
    error.value = validation
    return
  }

  savingRule.value = true
  error.value = ''
  message.value = ''

  try {
    const payload = payloadRule()
    if (selectedRuleId.value && selectedRule.value?.id) {
      await fetchJson(`/api/v1/learning-path/rules/${selectedRule.value.id}`, {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload),
      })
      message.value = 'Đã cập nhật rule.'
    } else {
      await fetchJson(`/api/v1/courses/${selectedCourseId.value}/learning-path/rules`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload),
      })
      message.value = 'Đã tạo rule mới.'
    }
    await refreshCourseContext()
  } catch (err) {
    error.value = err.message || 'Không lưu được rule.'
  } finally {
    savingRule.value = false
  }
}

async function deleteRule(ruleId) {
  if (!ruleId) return
  if (!window.confirm('Xóa rule này?')) return

  try {
    await fetchJson(`/api/v1/learning-path/rules/${ruleId}`, {
      method: 'DELETE',
    })
    message.value = 'Đã xóa rule.'
    if (selectedRuleId.value === String(ruleId)) {
      selectedRuleId.value = ''
      resetRuleForm()
    }
    await refreshCourseContext()
  } catch (err) {
    error.value = err.message || 'Không xóa được rule.'
  }
}

function selectRule(rule) {
  selectedRuleId.value = String(rule.id)
  loadRuleForm(rule)
  activeRulePanel.value = 'edit'
}

function newRule() {
  selectedRuleId.value = ''
  resetRuleForm()
  activeRulePanel.value = 'edit'
}

function copyRule(rule) {
  const clone = rule
  ruleForm.value = {
    title: `${(clone.title || 'Rule').trim()} (Copy)`,
    description: clone.description || '',
    target_type: clone.target_type || 'course_component',
    target_id: Number(clone.target_id || 0),
    rule_type: clone.rule_type || 'sequential',
    config: {
      unlock_behavior: clone.config?.unlock_behavior || 'all_required',
      message_locked: clone.config?.message_locked || 'Bạn cần hoàn thành điều kiện tiên quyết trước khi mở nội dung này.',
      requires: (clone.config?.requires || []).map((item) => ({ ...item, id: newRandomId() })),
      adaptive_routes: (clone.config?.adaptive_routes || []).map((route) => ({ ...route, id: newRandomId() })),
    },
  }
  selectedRuleId.value = ''
  message.value = 'Đã sao chép rule, có thể chỉnh sửa và bấm lưu để tạo mới.'
}

function newRandomId() {
  if (typeof crypto !== 'undefined' && crypto.randomUUID) return crypto.randomUUID()
  return `id-${Date.now()}-${Math.random().toString(16).slice(2)}`
}

function onTargetTypeChange() {
  if (ruleForm.value.target_type === 'course_section') {
    ruleForm.value.target_id = sectionOptions.value[0]?.id || null
  } else if (ruleForm.value.target_type === 'course_component') {
    ruleForm.value.target_id = componentOptions.value[0]?.id || null
  } else {
    ruleForm.value.target_id = null
  }
}

function runIntervention(type) {
  if (!selectedLearner.value) return
  if (type === 'remind') {
    message.value = `Đã tạo nhắc nhở cho ${selectedLearner.value.full_name}.`
    return
  }
  if (type === 'mentor') {
    message.value = `Đã tạo lịch mentor cho ${selectedLearner.value.full_name}.`
    return
  }
  message.value = `Đã tạo hồ sơ can thiệp cho ${selectedLearner.value.full_name}.`
}

function buildRowForCsv(row) {
  const metric = row.metrics?.[activeModel.value.id] || {}
  return [
    row.full_name,
    row.learner_code,
    row.email,
    row.cohort,
    row.status,
    row.overallProgress,
    row.riskScore,
    row.riskLevel,
    row.lastActive,
    row.metrics?.international?.completionRate || 0,
    row.metrics?.mooc?.score || 0,
    row.metrics?.idp?.score || 0,
    row.metrics?.longLife?.score || 0,
    metric.score || 0,
  ]
}

function toCsvValue(value) {
  const text = String(value ?? '')
  if (text.includes('"') || text.includes(',') || text.includes('\n')) {
    return `"${text.replaceAll('"', '""')}"`
  }
  return text
}

function exportLearnerCsv() {
  if (!selectedLearner.value) return
  const headers = ['Họ tên', 'Mã', 'Email', 'Lớp', 'Trạng thái', 'Tiến độ', 'Rủi ro', 'Mức rủi ro', 'Hoạt động gần nhất', 'International', 'MOOC', 'IDP', 'Long-life', `${activeModel.value.label} tổng`]
  const row = buildRowForCsv(selectedLearner.value)
  const blob = new Blob([`${headers.join(',')}\n${row.map(toCsvValue).join(',')}\n`], { type: 'text/csv;charset=utf-8' })
  const url = URL.createObjectURL(blob)
  const link = document.createElement('a')
  link.href = url
  link.download = `learner-profile-${selectedLearner.value.learner_code || selectedLearner.value.id}.csv`
  link.click()
  URL.revokeObjectURL(url)
}

function exportLearnerPdf() {
  if (!selectedLearner.value) return
  const row = selectedLearner.value
  const rows = [
    ['Mã học viên', row.learner_code],
    ['Họ tên', row.full_name],
    ['Email', row.email],
    ['Khóa học', row.course_title],
    ['Trạng thái', row.status],
    ['Tiến độ', `${row.overallProgress}%`],
    ['Rủi ro', `${row.riskScore}/100`],
    ['Khối chặn', row.blocker],
    ['Hành động khuyến nghị', row.nextAction],
  ]

  const html = `
    <html>
      <head>
        <meta charset="utf-8" />
        <title>Hồ sơ học viên</title>
        <style>
          body { font-family: Arial, sans-serif; padding: 20px; color: #111827; }
          h1 { margin: 0 0 12px; font-size: 18px; }
          table { border-collapse: collapse; width: 100%; }
          td, th { border: 1px solid #e5e7eb; padding: 8px 10px; font-size: 13px; }
          .section { margin-top: 16px; }
          .title { font-weight: 700; margin: 0 0 8px; }
        </style>
      </head>
      <body>
        <h1>Hồ sơ học viên theo Learning Path</h1>
        <div class="section">
          <div class="title">Thông tin tổng quát</div>
          <table>${rows.map((item) => `<tr><td>${item[0]}</td><td>${item[1]}</td></tr>`).join('')}</table>
        </div>
        <div class="section">
          <div class="title">Chỉ số theo mô hình đã chọn (${activeModel.value.label})</div>
          <table>${selectedModelMetrics.value.map((metric) => `<tr><td>${metric.label}</td><td>${formatMetricValue(metric, metric.value)} / ${metric.target}${metric.unit}</td></tr>`).join('')}</table>
        </div>
      </body>
    </html>
  `
  const tab = window.open('', '_blank')
  if (!tab) {
    error.value = 'Trình duyệt chặn popup, không thể mở PDF.'
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
  <EraLmsLayout :session-user="sessionUser" @logout="$emit('logout')">
    <template #breadcrumb>Đào tạo / Lộ trình học tập</template>

    <section class="border-b border-slate-200 bg-white">
      <div class="mx-auto flex max-w-7xl flex-wrap items-start justify-between gap-4 px-4 py-5 sm:px-6">
        <div>
          <div class="text-xs font-semibold uppercase tracking-wide text-cyan-700">Learning analytics + rule engine</div>
          <h1 class="mt-1 text-xl font-bold text-slate-950">Lập kế hoạch lộ trình, theo dõi học viên theo chuẩn quốc tế</h1>
          <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-600">
            Màn hình này gom 2 mảng: quản trị learning path theo rule thật và bảng theo dõi học viên theo khóa học thật.
          </p>
        </div>
        <div class="flex w-full flex-wrap gap-2 sm:w-auto">
          <a href="/learning-path/learner-progress" class="rounded-md border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700">Trang tiến độ học viên</a>
          <a href="/learning-path/class-progress" class="rounded-md border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700">Trang tiến độ lớp</a>
          <button
            class="rounded-md border border-slate-300 px-4 py-2 text-sm font-semibold"
            :disabled="loading"
            @click="refreshCourseContext()"
          >
            Làm mới dữ liệu
          </button>
        </div>
      </div>

      <div class="mx-auto mb-4 flex max-w-7xl flex-wrap gap-2 px-4 sm:px-6">
        <button
          v-for="tab in mainTabs"
          :key="tab.id"
          class="rounded-md px-3 py-2 text-sm font-semibold"
          :class="activeMainTab === tab.id ? 'bg-slate-900 text-white' : 'border border-slate-300 text-slate-700'"
          @click="activeMainTab = tab.id"
        >
          {{ tab.label }}
        </button>
      </div>

      <div class="mx-auto max-w-7xl px-4 pb-5 sm:px-6">
        <div class="grid gap-3 md:grid-cols-[1fr_220px]">
          <select
            v-model="selectedCourseId"
            class="h-10 rounded-md border border-slate-300 bg-white px-3 text-sm"
          >
            <option v-for="course in courses" :key="course.id" :value="course.id">
              {{ course.title }} ({{ course.code }})
            </option>
          </select>
          <div class="flex rounded-md border border-slate-200 bg-slate-50 px-3 py-2 text-sm">
            {{ loading ? 'Đang kết nối API...' : `${courses.length} khóa học, ${learners.length} học viên` }}
          </div>
        </div>
      </div>

      <div v-if="error" class="mx-auto mb-4 max-w-7xl rounded-md border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
        {{ error }}
      </div>
      <div v-else-if="message" class="mx-auto mb-4 max-w-7xl rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
        {{ message }}
      </div>
    </section>

    <section v-if="activeMainTab === 'learners'" class="mx-auto max-w-7xl space-y-4 px-4 py-5 sm:px-6">
      <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
        <div class="rounded-lg border border-slate-200 bg-white p-4">
          <p class="text-xs uppercase text-slate-500">Tổng học viên</p>
          <p class="mt-1 text-2xl font-bold text-slate-950">{{ dashboard.total }}</p>
        </div>
        <div class="rounded-lg border border-slate-200 bg-white p-4">
          <p class="text-xs uppercase text-slate-500">Tiến độ TB</p>
          <p class="mt-1 text-2xl font-bold text-slate-950">{{ dashboard.avgProgress }}%</p>
        </div>
        <div class="rounded-lg border border-slate-200 bg-white p-4">
          <p class="text-xs uppercase text-slate-500">Hoàn thành</p>
          <p class="mt-1 text-2xl font-bold text-emerald-700">{{ dashboard.completeRate }}%</p>
        </div>
        <div class="rounded-lg border border-slate-200 bg-white p-4">
          <p class="text-xs uppercase text-slate-500">Rủi ro cao</p>
          <p class="mt-1 text-2xl font-bold text-rose-700">{{ dashboard.highRisk }}</p>
        </div>
        <div class="rounded-lg border border-slate-200 bg-white p-4">
          <p class="text-xs uppercase text-slate-500">International đạt chuẩn</p>
          <p class="mt-1 text-2xl font-bold text-cyan-700">{{ dashboard.internationalReady }}</p>
        </div>
      </div>

      <div class="rounded-lg border border-slate-200 bg-white p-4">
        <div class="flex flex-wrap items-center gap-2">
          <button
            v-for="tab in modelTabs"
            :key="tab.id"
            class="rounded-md px-3 py-2 text-sm font-semibold"
            :class="activeModelTab === tab.id ? 'bg-slate-950 text-white' : 'border border-slate-200 text-slate-700'"
            @click="activeModelTab = tab.id"
          >
            {{ tab.label }}
          </button>
          <p class="ml-auto text-sm text-slate-600">{{ activeModel.description }}</p>
        </div>
      </div>

      <div class="grid gap-3 xl:grid-cols-[1fr_320px]">
        <div class="rounded-lg border border-slate-200 bg-white p-4">
          <div class="flex flex-wrap gap-2">
            <input
              v-model="search"
              class="h-9 min-w-56 flex-1 rounded-md border border-slate-300 px-3 text-sm"
              placeholder="Tìm theo tên, email, mã, lớp..."
            />
            <select v-model="statusFilter" class="h-9 rounded-md border border-slate-300 px-3 text-sm">
              <option v-for="status in statusOptions" :key="status" :value="status">{{ status }}</option>
            </select>
            <select v-model="sortBy" class="h-9 rounded-md border border-slate-300 px-3 text-sm">
              <option v-for="option in sortOptions" :key="option.key" :value="option.key">{{ option.label }}</option>
            </select>
            <button class="rounded-md border border-slate-300 px-3 py-2 text-sm" @click="sortDirection = sortDirection === 'asc' ? 'desc' : 'asc'">
              {{ sortDirection === 'desc' ? 'Giảm dần' : 'Tăng dần' }}
            </button>
          </div>

          <div class="mt-4 overflow-hidden border border-slate-200">
            <table class="w-full text-left text-sm">
              <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                <tr>
                  <th class="px-3 py-3">Học viên</th>
                  <th class="px-3 py-3">Trạng thái</th>
                  <th class="px-3 py-3">Tiến độ tổng</th>
                  <th class="px-3 py-3">Chỉ số {{ activeModel.shortLabel }}</th>
                  <th class="px-3 py-3">Rủi ro</th>
                  <th class="px-3 py-3">Hành động nhanh</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-100">
                <tr
                  v-for="row in sortedRows"
                  :key="row.id"
                  class="hover:bg-slate-50"
                  :class="{ 'bg-cyan-50/50': selectedLearnerId === row.id }"
                  @click="selectLearner(row)"
                >
                  <td class="px-3 py-3">
                    <p class="font-semibold text-slate-950">{{ row.full_name }}</p>
                    <p class="text-xs text-slate-500">{{ row.email }} • {{ row.learner_code }}</p>
                    <p class="text-xs text-slate-500">{{ row.cohort }}</p>
                  </td>
                  <td class="px-3 py-3">
                    <span class="inline-flex items-center rounded-full border px-2 py-1 text-xs font-semibold" :class="riskTone(row.riskLevel)">
                      {{ row.status }} • {{ row.riskLevel }}
                    </span>
                  </td>
                  <td class="px-3 py-3">
                    <div class="h-2 w-28 rounded-full bg-slate-100">
                      <div class="h-2 rounded-full bg-cyan-600" :style="{ width: `${row.overallProgress}%` }"></div>
                    </div>
                    <p class="mt-1 text-xs text-slate-500">{{ row.overallProgress }}%</p>
                  </td>
                  <td class="px-3 py-3">
                    <span class="inline-flex items-center rounded-full px-2 py-1 text-xs font-semibold" :class="metricTone(getCellValue(row, `${activeModel.id}.score`), 70)">
                      {{ getCellValue(row, `${activeModel.id}.score`) || 0 }}
                    </span>
                  </td>
                  <td class="px-3 py-3 text-xs text-slate-600">Score risk: {{ row.riskScore }}</td>
                  <td class="px-3 py-3">
                    <button class="rounded-md bg-slate-900 px-3 py-2 text-xs font-semibold text-white" @click.stop="selectLearner(row)">
                      Xem chi tiết
                    </button>
                  </td>
                </tr>
                <tr v-if="!sortedRows.length">
                  <td colspan="6" class="px-3 py-6 text-center text-sm text-slate-500">Không có học viên phù hợp bộ lọc.</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <aside class="space-y-4">
          <div class="rounded-lg border border-slate-200 bg-white p-4">
            <div class="flex items-start justify-between gap-3">
              <div>
                <p class="text-xs uppercase text-slate-500">Chi tiết học viên</p>
                <h2 class="mt-1 text-sm font-bold text-slate-950">{{ selectedLearner?.full_name }}</h2>
                <p class="mt-1 text-sm text-slate-600">{{ selectedLearner?.email }}</p>
              </div>
              <span class="rounded-full border border-slate-200 px-2.5 py-1 text-xs font-semibold">{{ selectedLearner?.status }}</span>
            </div>
            <div class="mt-3 grid gap-2">
              <div class="rounded-md bg-slate-50 p-3">
                <p class="text-xs text-slate-500">Tiến độ tổng</p>
                <p class="mt-1 text-lg font-semibold text-slate-950">{{ selectedLearner?.overallProgress }}%</p>
              </div>
              <div class="rounded-md bg-slate-50 p-3">
                <p class="text-xs text-slate-500">Khối chặn hiện tại</p>
                <p class="mt-1 text-sm text-slate-800">{{ selectedLearner?.blocker }}</p>
              </div>
              <div class="rounded-md bg-slate-50 p-3">
                <p class="text-xs text-slate-500">Hướng xử lý</p>
                <p class="mt-1 text-sm text-slate-800">{{ selectedLearner?.nextAction }}</p>
              </div>
            </div>

            <div class="mt-3 grid grid-cols-2 gap-2">
              <button class="rounded-md bg-emerald-700 px-3 py-2 text-sm font-semibold text-white" @click="exportLearnerCsv">Export CSV</button>
              <button class="rounded-md bg-cyan-700 px-3 py-2 text-sm font-semibold text-white" @click="exportLearnerPdf">Export PDF</button>
            </div>
          </div>

          <div class="rounded-lg border border-slate-200 bg-white p-4">
            <div class="flex gap-2">
              <button
                v-for="tab in detailTabs"
                :key="tab.id"
                class="rounded-md px-3 py-2 text-xs font-semibold"
                :class="activeDetailTab === tab.id ? 'bg-slate-900 text-white' : 'border border-slate-200 text-slate-700'"
                @click="activeDetailTab = tab.id"
              >
                {{ tab.label }}
              </button>
            </div>

            <div class="mt-4">
              <div v-if="activeDetailTab === 'metrics'" class="space-y-3">
                <div class="mb-3 flex flex-wrap gap-2">
                  <button
                    v-for="tab in modelTabs"
                    :key="tab.id"
                    class="rounded-full border border-slate-300 px-3 py-1 text-xs"
                    :class="activeModelTab === tab.id ? 'bg-slate-900 text-white border-slate-900' : 'bg-white text-slate-700'"
                    @click="activeModelTab = tab.id"
                  >
                    {{ tab.shortLabel }}
                  </button>
                </div>
                <div
                  v-for="metric in selectedModelMetrics"
                  :key="`${activeModel.id}-${metric.key}`"
                  class="rounded-md border border-slate-200 p-3"
                >
                  <div class="flex items-center justify-between gap-2 text-sm">
                    <p class="font-semibold text-slate-800">{{ metric.label }}</p>
                    <span
                      class="rounded-full px-2 py-0.5 text-xs font-semibold"
                      :class="metricTone(metric.value, metric.target)"
                    >
                      {{ formatMetricValue(metric, metric.value) }} / target {{ metric.target }}{{ metric.unit === '/5' ? '' : metric.unit }}
                    </span>
                  </div>
                  <div class="mt-2 h-2 rounded-full bg-slate-100">
                    <div
                      class="h-2 rounded-full"
                      :class="metricTone(metric.value, metric.target).includes('rose') ? 'bg-rose-500' : metricTone(metric.value, metric.target).includes('amber') ? 'bg-amber-500' : 'bg-emerald-500'"
                      :style="{ width: `${progressPercent(metric, metric.value)}%` }"
                    ></div>
                  </div>
                </div>
              </div>

              <div v-else-if="activeDetailTab === 'related'" class="space-y-3">
                <div class="space-y-2">
                  <div v-for="item in relatedData" :key="item.label" class="flex items-center justify-between rounded-md border border-slate-200 p-3">
                    <span class="text-sm text-slate-600">{{ item.label }}</span>
                    <span class="text-sm font-semibold text-slate-900">{{ item.value }}</span>
                  </div>
                </div>
                <ol class="space-y-2 pt-1 text-sm text-slate-600">
                  <li v-for="(item, idx) in selectedLearner?.activity" :key="`${item}-${idx}`" class="rounded-md border border-slate-200 p-2">• {{ item }}</li>
                </ol>
              </div>

              <div v-else class="space-y-2">
                <p class="text-sm text-slate-600">Các hành động can thiệp phù hợp:</p>
                <button class="w-full rounded-md bg-emerald-700 px-3 py-2 text-sm font-semibold text-white" @click="runIntervention('remind')">Nhắc học</button>
                <button class="w-full rounded-md bg-cyan-700 px-3 py-2 text-sm font-semibold text-white" @click="runIntervention('mentor')">Tạo lịch mentor</button>
                <button class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm font-semibold" @click="runIntervention('export')">Xuất hồ sơ</button>
                <div class="rounded-md border border-slate-200 p-3">
                  <p class="text-xs uppercase text-slate-500">Gợi ý tự động</p>
                  <ul class="mt-2 space-y-2 text-sm text-slate-700">
                    <li v-for="item in selectedLearner?.interventions" :key="item">• {{ item }}</li>
                  </ul>
                </div>
              </div>
            </div>
          </div>
        </aside>
      </div>
    </section>

    <section v-else class="mx-auto max-w-7xl space-y-4 px-4 py-5 sm:px-6">
      <div class="grid gap-4 xl:grid-cols-[320px_1fr]">
        <aside class="space-y-4">
          <div class="rounded-lg border border-slate-200 bg-white p-4">
            <h3 class="text-sm font-bold text-slate-950">Danh sách rule hiện tại</h3>
            <p class="mt-2 text-xs text-slate-500">Chọn 1 rule để chỉnh sửa hoặc tạo mới.</p>

            <div class="mt-3 space-y-2">
              <button
                class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700"
                :class="activeRulePanel === 'new' ? 'bg-slate-950 text-white' : ''"
                @click="newRule"
              >
                + Tạo rule mới
              </button>
              <div v-for="rule in rules" :key="rule.id" class="rounded-md border border-slate-200 p-3">
                <p class="font-semibold text-slate-900">{{ rule.title || 'Không tên' }}</p>
                <p class="mt-1 text-xs text-slate-500">{{ rule.target_type }} • {{ rule.rule_type }} • {{ rule.is_active ? 'Bật' : 'Tắt' }}</p>
                <p class="mt-2 text-xs text-slate-500">Target: {{ ruleTargets.find((item) => item.kind === rule.target_type && Number(item.value) === Number(rule.target_id))?.label || 'N/A' }}</p>
                <div class="mt-3 flex flex-wrap gap-2">
                  <button class="rounded-md border border-slate-300 px-2 py-1 text-xs" @click="selectRule(rule)">Mở</button>
                  <button class="rounded-md border border-amber-300 px-2 py-1 text-xs" @click="copyRule(rule)">Sao chép</button>
                  <button class="rounded-md border border-rose-300 px-2 py-1 text-xs" @click="deleteRule(rule.id)">Xóa</button>
                </div>
                <div class="mt-2 text-xs text-slate-600">
                  <p>Preview: <span>{{ describeRequirement(rule.config?.requires?.[0] || {}) || 'Không có điều kiện' }}</span></p>
                  <p class="mt-1">Số điều kiện: {{ (rule.config?.requires || []).length }}</p>
                </div>
              </div>
              <div v-if="!rules.length" class="text-sm text-slate-500">Chưa có rule nào cho khóa học này.</div>
            </div>
          </div>
        </aside>

        <main class="space-y-4">
          <div class="rounded-lg border border-slate-200 bg-white p-4">
            <div class="flex flex-wrap items-center justify-between gap-3">
              <div>
                <h2 class="text-sm font-bold text-slate-950">Thiết lập rule mở khóa</h2>
                <p class="mt-1 text-sm text-slate-600">Sử dụng rule thật từ endpoint `learning-path` để cấu hình lộ trình.</p>
              </div>
              <button
                class="rounded-md bg-cyan-700 px-4 py-2 text-sm font-semibold text-white disabled:opacity-50"
                :disabled="savingRule"
                @click="saveRule"
              >
                {{ savingRule ? 'Đang lưu...' : (selectedRuleId ? 'Cập nhật rule' : 'Tạo rule') }}
              </button>
            </div>

            <div class="mt-4 grid gap-4 md:grid-cols-3">
              <div>
                <label class="text-xs font-semibold uppercase text-slate-500">Khóa học</label>
                <input :value="selectedCourse?.title || ''" class="mt-1 h-10 w-full rounded-md border border-slate-300 bg-slate-50 px-3 text-sm" disabled />
              </div>
              <div>
                <label class="text-xs font-semibold uppercase text-slate-500">Target type</label>
                <select v-model="ruleForm.target_type" class="mt-1 h-10 w-full rounded-md border border-slate-300 bg-white px-3 text-sm" @change="onTargetTypeChange">
                  <option value="course_component">Course component</option>
                  <option value="course_section">Course section</option>
                  <option value="course">Course toàn khóa</option>
                </select>
              </div>
              <div>
                <label class="text-xs font-semibold uppercase text-slate-500">Target</label>
                <select v-if="ruleForm.target_type !== 'course'" v-model="ruleForm.target_id" class="mt-1 h-10 w-full rounded-md border border-slate-300 bg-white px-3 text-sm">
                  <option v-if="ruleForm.target_type === 'course_section'" v-for="item in sectionOptions" :key="`s-${item.id}`" :value="item.id">{{ item.title }}</option>
                  <option v-if="ruleForm.target_type === 'course_component'" v-for="item in componentOptions" :key="`c-${item.id}`" :value="item.id">{{ item.title }}</option>
                </select>
                <div v-else class="mt-1 h-10 rounded-md border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-500">Toàn khóa</div>
              </div>
            </div>

            <div class="mt-4 grid gap-4 md:grid-cols-2">
              <div>
                <label class="text-xs font-semibold uppercase text-slate-500">Tên rule</label>
                <input v-model="ruleForm.title" class="mt-1 h-10 w-full rounded-md border border-slate-300 px-3 text-sm" />
              </div>
              <div>
                <label class="text-xs font-semibold uppercase text-slate-500">Rule type</label>
                <select v-model="ruleForm.rule_type" class="mt-1 h-10 w-full rounded-md border border-slate-300 px-3 text-sm">
                  <option value="sequential">Sequential</option>
                  <option value="prerequisite">Prerequisite</option>
                  <option value="mastery">Mastery</option>
                  <option value="adaptive">Adaptive</option>
                  <option value="date_lock">Date lock</option>
                  <option value="manual_approval">Manual approval</option>
                </select>
              </div>
            </div>

            <label class="mt-4 block">
              <span class="text-xs font-semibold uppercase text-slate-500">Mô tả</span>
              <input v-model="ruleForm.description" class="mt-1 h-10 w-full rounded-md border border-slate-300 px-3 text-sm" />
            </label>

            <label class="mt-3 block">
              <span class="text-xs font-semibold uppercase text-slate-500">Hành vi mở khóa</span>
              <select v-model="ruleForm.config.unlock_behavior" class="mt-1 h-10 w-full rounded-md border border-slate-300 bg-white px-3 text-sm">
                <option value="all_required">Phải đạt tất cả điều kiện</option>
                <option value="any_required">Đạt một trong các điều kiện</option>
              </select>
            </label>

            <label class="mt-4 block">
              <span class="text-xs font-semibold uppercase text-slate-500">Thông báo khi khóa</span>
              <textarea v-model="ruleForm.config.message_locked" rows="3" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm"></textarea>
            </label>
          </div>

          <div class="rounded-lg border border-slate-200 bg-white p-4">
            <div class="flex items-center justify-between">
              <h3 class="text-sm font-bold text-slate-950">Thêm điều kiện</h3>
              <div class="flex gap-2">
                <button v-for="template in requirementTemplates" :key="template.type" class="rounded-md border border-slate-300 px-3 py-2 text-xs font-semibold" @click="addRequirement(template.type)">
                  + {{ template.label }}
                </button>
              </div>
            </div>

            <div class="mt-4 space-y-3">
              <div v-if="!ruleForm.config.requires.length" class="rounded-md border border-dashed border-slate-300 bg-slate-50 p-3 text-sm text-slate-600">
                Chưa có điều kiện mở khóa. Hãy thêm ít nhất 1 điều kiện.
              </div>
              <div v-for="item in ruleForm.config.requires" :key="item.id" class="rounded-md border border-slate-200 bg-slate-50 p-3">
                <div class="grid gap-3 lg:grid-cols-[180px_1fr_130px_auto]">
                  <select v-model="item.type" class="h-10 rounded-md border border-slate-300 bg-white px-3 text-sm">
                    <option v-for="template in requirementTemplates" :key="template.type" :value="template.type">{{ template.label }}</option>
                  </select>
                  <select v-if="item.type !== 'manual_approval' && item.type !== 'date_after'" v-model.number="item.component_id" class="h-10 rounded-md border border-slate-300 bg-white px-3 text-sm">
                    <option v-for="component in componentOptions" :key="`req-${component.id}`" :value="component.id">{{ component.title }}</option>
                  </select>
                  <input
                    v-else-if="item.type === 'date_after'"
                    v-model="item.unlock_at"
                    type="date"
                    class="h-10 rounded-md border border-slate-300 bg-white px-3 text-sm"
                  />
                  <div v-else class="flex h-10 items-center rounded-md border border-slate-200 bg-white px-3 text-sm text-slate-600">Role: Teacher / Admin</div>
                  <input
                    v-if="templateFor(item.type).minKey"
                    v-model.number="item[templateFor(item.type).minKey]"
                    type="number"
                    min="0"
                    max="100"
                    class="h-10 rounded-md border border-slate-300 px-3 text-sm"
                  />
                  <div v-else class="hidden lg:block"></div>
                  <button class="rounded-md border border-rose-200 bg-white px-3 py-2 text-sm font-semibold text-rose-700 hover:bg-rose-50" @click="removeRequirement(item.id)">Xóa</button>
                </div>
                <p class="mt-2 text-sm text-slate-600">{{ describeRequirement(item) }}</p>
              </div>
            </div>

            <div class="mt-5 border-t border-slate-200 pt-4">
              <div class="flex items-center justify-between">
                <h3 class="text-sm font-bold text-slate-950">Adaptive route (tùy chọn)</h3>
                <button class="rounded-md border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700" @click="addAdaptiveRoute">Thêm nhánh</button>
              </div>
              <div class="mt-3 space-y-2">
                <div v-for="route in ruleForm.config.adaptive_routes" :key="route.id" class="grid gap-2 rounded-md border border-slate-200 p-3 md:grid-cols-[1fr_1fr_140px_auto]">
                  <input v-model="route.when" class="h-10 rounded-md border border-slate-300 px-3 text-sm" placeholder="score < 70" />
                  <input v-model="route.target_type" class="h-10 rounded-md border border-slate-300 px-3 text-sm" placeholder="target_type" />
                  <input v-model.number="route.target_id" type="number" min="1" class="h-10 rounded-md border border-slate-300 px-3 text-sm" placeholder="target_id" />
                  <button class="rounded-md border border-slate-300 px-3 py-2 text-sm" @click="removeAdaptiveRoute(route.id)">Xóa</button>
                </div>
              </div>
            </div>
          </div>
        </main>
      </div>
    </section>
  </EraLmsLayout>
</template>
