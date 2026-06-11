<script setup>
import { computed, onMounted, ref } from 'vue'
import { Brain, ChevronLeft, ChevronRight, Compass, Filter, LineChart, RefreshCw, Search, Target, TrendingUp, Users } from '@lucide/vue'
import EraLmsLayout from '@/Layouts/EraLmsLayout.vue'

const props = defineProps({
  sessionUser: { type: Object, default: null },
  apiHeaders: { type: Object, default: () => ({}) },
})

defineEmits(['logout'])

const loading = ref(true)
const error = ref('')
const personalData = ref(null)
const overviewData = ref(null)
const activeTab = ref('idp')
const learnerTab = ref('learning')
const search = ref('')
const selectedClassId = ref('')
const selectedCohortId = ref('')
const selectedYear = ref('')
const selectedStatus = ref('')
const page = ref(1)
const perPage = ref(10)
const selectedCandidateId = ref(null)

const isLearner = computed(() => props.sessionUser?.user_type === 'student')
const detailPayload = computed(() => (isLearner.value ? personalData.value : overviewData.value?.selected_candidate || null))
const detailMetrics = computed(() => detailPayload.value?.metrics || [])
const detailSkills = computed(() => detailPayload.value?.skills || [])
const detailCourses = computed(() => detailPayload.value?.courses || [])
const detailActions = computed(() => detailPayload.value?.idp?.actions || [])
const detailAttempts = computed(() => detailPayload.value?.attempts || [])
const detailPrompt = computed(() => detailPayload.value?.analysis_prompt || '')
const detailScore = computed(() => Number(detailPayload.value?.digital_twin?.readiness_score || 0))
const detailLearner = computed(() => detailPayload.value?.learner || {})
const twinTabs = computed(() => detailPayload.value?.tabs || {})
const timeline360 = computed(() => detailPayload.value?.timeline_360 || [])
const riskEngine = computed(() => detailPayload.value?.risk_engine || {})
const advisor = computed(() => detailPayload.value?.advisor || {})
const viewModes = computed(() => detailPayload.value?.views || [])
const learnerTabs = [
  { id: 'learning', label: 'Learning' },
  { id: 'grades', label: 'Grades' },
  { id: 'attendance', label: 'Attendance' },
  { id: 'risk', label: 'Risk' },
  { id: 'portfolio', label: 'Portfolio' },
  { id: 'skills', label: 'Skills' },
  { id: 'career', label: 'Career' },
  { id: 'certificates', label: 'Certificates' },
]
const portfolioTabItems = computed(() => {
  const sections = twinTabs.value?.portfolio?.sections || {}
  return Object.values(sections).flat().slice(0, 8)
})
const credentialTimeline = computed(() => twinTabs.value?.certificates?.timeline || [])
const careerReadiness = computed(() => twinTabs.value?.career?.sections?.career_readiness || [])
const overviewSummary = computed(() => overviewData.value?.summary || {})
const overviewMeta = computed(() => overviewData.value?.candidates?.meta || {})
const candidateRows = computed(() => overviewData.value?.candidates?.data || [])
const breakdownClasses = computed(() => overviewData.value?.breakdowns?.classes || [])
const breakdownCohorts = computed(() => overviewData.value?.breakdowns?.cohorts || [])
const breakdownYears = computed(() => overviewData.value?.breakdowns?.years || [])
const classOptions = computed(() => overviewData.value?.options?.classes || [])
const cohortOptions = computed(() => overviewData.value?.options?.cohorts || [])
const yearOptions = computed(() => overviewData.value?.options?.years || [])
const selectedCandidateSummary = computed(() => candidateRows.value.find((row) => row.user?.id === selectedCandidateId.value) || null)

const overviewCards = computed(() => [
  { label: 'Tổng thí sinh', value: Number(overviewSummary.value.total_candidates || 0), detail: 'Số hồ sơ đang nằm trong phạm vi lọc' },
  { label: 'Đang hoạt động', value: Number(overviewSummary.value.active_candidates || 0), detail: 'Thí sinh có trạng thái active/enrolled' },
  { label: 'Hoàn thành', value: Number(overviewSummary.value.completed_candidates || 0), detail: 'Thí sinh đã hoàn thành' },
  { label: 'Cần chú ý', value: Number(overviewSummary.value.at_risk_candidates || 0), detail: 'Risk score cao hoặc đang gián đoạn' },
  { label: 'Hoàn thành TB', value: Number(overviewSummary.value.avg_completion_percent || 0).toFixed(1), suffix: '%', detail: 'Mức hoàn thành trung bình' },
  { label: 'Risk TB', value: Number(overviewSummary.value.avg_risk_score || 0).toFixed(1), suffix: '', detail: 'Điểm rủi ro trung bình' },
])

const radarPoints = computed(() => {
  const base = [
    ['Học tập', Number(detailMetrics.value[0]?.value || 0), 150, 24],
    ['Thi', Number(detailMetrics.value[1]?.value || 0), 268, 96],
    ['Kỹ năng', Number(detailMetrics.value[2]?.value || 0), 222, 236],
    ['Portfolio', Number(detailMetrics.value[3]?.value || 0), 78, 236],
    ['Risk', Math.max(0, 100 - Number(detailPayload.value?.digital_twin?.risk_score || 0)), 32, 96],
  ]
  return base
    .map(([, value, x, y]) => `${150 + (x - 150) * value / 100},${150 + (y - 150) * value / 100}`)
    .join(' ')
})

const statusOptions = [
  { value: '', label: 'Tất cả trạng thái' },
  { value: 'active', label: 'Active' },
  { value: 'enrolled', label: 'Enrolled' },
  { value: 'in_progress', label: 'In progress' },
  { value: 'completed', label: 'Completed' },
  { value: 'suspended', label: 'Suspended' },
  { value: 'withdrawn', label: 'Withdrawn' },
  { value: 'expired', label: 'Expired' },
  { value: 'pending', label: 'Pending' },
]

async function load() {
  if (isLearner.value) {
    await loadPersonal()
    return
  }

  await loadOverview()
}

async function loadPersonal() {
  loading.value = true
  error.value = ''
  try {
    const response = await fetch('/api/v1/student/digital-twin', { headers: props.apiHeaders })
    const payload = await response.json().catch(() => ({}))
    if (!response.ok) throw new Error(payload.message || 'Không tải được IDP cá nhân.')
    personalData.value = payload.data || payload
  } catch (err) {
    error.value = err.message || 'Không tải được IDP cá nhân.'
  } finally {
    loading.value = false
  }
}

async function loadOverview({ focusCandidateId = null, resetPage = false } = {}) {
  loading.value = true
  error.value = ''
  if (resetPage) page.value = 1

  const params = new URLSearchParams({
    scope: 'overview',
    page: String(page.value),
    per_page: String(perPage.value),
  })
  if (search.value.trim()) params.set('q', search.value.trim())
  if (selectedClassId.value) params.set('class_section_id', selectedClassId.value)
  if (selectedCohortId.value) params.set('cohort_id', selectedCohortId.value)
  if (selectedYear.value) params.set('year', selectedYear.value)
  if (selectedStatus.value) params.set('status', selectedStatus.value)
  if (focusCandidateId) params.set('user_id', String(focusCandidateId))

  try {
    const response = await fetch(`/api/v1/career/digital-twin?${params.toString()}`, { headers: props.apiHeaders })
    const payload = await response.json().catch(() => ({}))
    if (!response.ok) throw new Error(payload.message || 'Không tải được danh sách thí sinh.')
    overviewData.value = payload.data || payload
    selectedCandidateId.value = overviewData.value?.selected_candidate_id || focusCandidateId || null
  } catch (err) {
    error.value = err.message || 'Không tải được danh sách thí sinh.'
  } finally {
    loading.value = false
  }
}

async function refresh() {
  if (isLearner.value) {
    await loadPersonal()
    return
  }
  await loadOverview({ focusCandidateId: selectedCandidateId.value })
}

async function applyFilters() {
  page.value = 1
  selectedCandidateId.value = null
  await loadOverview()
}

async function clearFilters() {
  search.value = ''
  selectedClassId.value = ''
  selectedCohortId.value = ''
  selectedYear.value = ''
  selectedStatus.value = ''
  page.value = 1
  selectedCandidateId.value = null
  await loadOverview()
}

async function openCandidate(candidate) {
  if (!candidate?.user?.id) return
  selectedCandidateId.value = candidate.user.id
  await loadOverview({ focusCandidateId: candidate.user.id })
}

async function changePage(step) {
  const nextPage = Math.max(1, Math.min(Number(overviewMeta.value.last_page || 1), Number(overviewMeta.value.current_page || 1) + step))
  if (nextPage === Number(overviewMeta.value.current_page || 1)) return
  page.value = nextPage
  selectedCandidateId.value = null
  await loadOverview()
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
  if (['active', 'completed', 'enrolled', 'in_progress', 'graded', 'submitted'].includes(value)) return 'bg-emerald-50 text-emerald-700'
  if (['suspended', 'withdrawn', 'expired', 'failed'].includes(value)) return 'bg-red-50 text-red-700'
  return 'bg-slate-100 text-slate-700'
}

function statCardClass(index) {
  return index % 2 === 0 ? 'bg-slate-50' : 'bg-white'
}

onMounted(load)
</script>

<template>
  <EraLmsLayout :session-user="sessionUser" @logout="$emit('logout')">
    <template #breadcrumb>{{ isLearner ? 'Digital Twin 360' : 'Hồ sơ nghề nghiệp / Dashboard thí sinh' }}</template>
    <section class="min-h-[calc(100vh-6rem)] bg-slate-100 px-3 py-3 lg:px-5">
      <div v-if="loading && !personalData && !overviewData" class="rounded-md border bg-white p-6 text-sm text-slate-500">Đang phân tích dữ liệu...</div>
      <div v-else-if="error" class="rounded-md border border-red-200 bg-red-50 p-6 text-sm font-semibold text-red-700">{{ error }}</div>

      <div v-else-if="isLearner" class="mx-auto max-w-7xl space-y-5">
        <header class="rounded-md border border-slate-200 bg-white p-5 shadow-sm">
          <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
              <div class="inline-flex items-center gap-2 rounded-full bg-blue-50 px-3 py-1 text-xs font-bold uppercase text-blue-700">
                <Brain class="h-4 w-4" /> Student Digital Twin 360
              </div>
              <h1 class="mt-3 text-2xl font-bold text-slate-950">Bản đồ học tập, rủi ro và kế hoạch cá nhân</h1>
              <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-600">
                Mô hình dùng tiến độ khóa học, kết quả thi, kỹ năng, portfolio và rủi ro học tập để đề xuất kế hoạch 30/60/90 ngày.
              </p>
              <div class="mt-4 flex flex-wrap gap-2">
                <a href="/" class="rounded-md border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700">Home</a>
                <a href="/courses" class="rounded-md border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700">My Learning</a>
                <a href="/student/tasks" class="rounded-md border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700">Task Center</a>
                <button class="inline-flex items-center gap-2 rounded-md bg-slate-950 px-3 py-2 text-sm font-semibold text-white" :disabled="loading" @click="refresh">
                  <RefreshCw class="h-4 w-4" /> Tải lại
                </button>
              </div>
            </div>
            <div class="min-w-52 rounded-md bg-slate-950 p-4 text-white">
              <div class="text-xs uppercase text-slate-300">Điểm sẵn sàng</div>
              <div class="mt-2 text-4xl font-bold">{{ detailScore }}</div>
              <div class="mt-3 h-2 rounded-full bg-slate-700">
                <div class="h-2 rounded-full bg-cyan-400" :style="{ width: `${Math.min(100, detailScore)}%` }"></div>
              </div>
            </div>
          </div>
        </header>

        <div class="grid gap-4 lg:grid-cols-[360px_minmax(0,1fr)]">
          <aside class="space-y-4">
            <div class="rounded-md border border-slate-200 bg-white p-4 shadow-sm">
              <h2 class="flex items-center gap-2 text-sm font-bold text-slate-950"><LineChart class="h-4 w-4 text-blue-700" /> Bản đồ năng lực</h2>
              <svg viewBox="0 0 300 280" class="mt-3 h-72 w-full">
                <polygon points="150,24 268,96 222,236 78,236 32,96" fill="none" stroke="#cbd5e1" />
                <polygon points="150,60 226,106 196,196 104,196 74,106" fill="none" stroke="#e2e8f0" />
                <polygon :points="radarPoints" fill="#0891b222" stroke="#0891b2" stroke-width="2" />
                <text x="150" y="18" text-anchor="middle" class="fill-slate-700 text-[11px]">Học tập</text>
                <text x="276" y="100" class="fill-slate-700 text-[11px]">Thi</text>
                <text x="226" y="256" class="fill-slate-700 text-[11px]">Kỹ năng</text>
                <text x="34" y="256" class="fill-slate-700 text-[11px]">Hồ sơ</text>
                <text x="8" y="100" class="fill-slate-700 text-[11px]">Rủi ro</text>
              </svg>
            </div>
            <div class="rounded-md border border-slate-200 bg-white p-4 shadow-sm">
              <h2 class="text-sm font-bold text-slate-950">Prompt phân tích chuẩn IDP</h2>
              <p class="mt-3 rounded-md bg-slate-50 p-3 text-xs leading-5 text-slate-700">{{ detailPrompt }}</p>
            </div>
          </aside>

          <main class="space-y-4">
            <div class="grid gap-3 md:grid-cols-4">
              <div v-for="item in detailMetrics" :key="item.label" class="rounded-md border border-slate-200 bg-white p-4 shadow-sm">
                <div class="text-xs font-semibold uppercase text-slate-500">{{ item.label }}</div>
                <div class="mt-2 text-2xl font-bold text-slate-950">{{ item.value }}<span class="text-sm text-slate-500">{{ item.suffix }}</span></div>
              </div>
            </div>

            <div class="rounded-md border border-slate-200 bg-white shadow-sm">
              <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 p-3">
                <div>
                  <div class="text-xs font-bold uppercase text-blue-700">Student Digital Twin 360</div>
                  <h2 class="mt-1 text-sm font-bold text-slate-950">Executive · Teacher · Advisor · Student self view</h2>
                </div>
                <div class="flex flex-wrap gap-1">
                  <span v-for="view in viewModes" :key="view" class="rounded-full bg-slate-100 px-2 py-1 text-[11px] font-semibold text-slate-600">{{ view }}</span>
                </div>
              </div>
              <div class="flex flex-wrap gap-2 border-b border-slate-200 p-3">
                <button v-for="tab in learnerTabs" :key="tab.id" class="rounded-md px-3 py-2 text-xs font-semibold sm:text-sm" :class="learnerTab === tab.id ? 'bg-slate-950 text-white' : 'bg-slate-100 text-slate-700'" @click="learnerTab = tab.id">{{ tab.label }}</button>
              </div>

              <div v-if="learnerTab === 'learning'" class="p-4">
                <div class="grid gap-3 md:grid-cols-2">
                  <div v-for="course in twinTabs.learning || []" :key="course.course" class="rounded-md border border-slate-200 p-3 text-sm">
                    <div class="flex items-center justify-between gap-3"><strong>{{ course.course }}</strong><span>{{ course.progress }}%</span></div>
                    <div class="mt-2 h-2 rounded-full bg-slate-100"><div class="h-2 rounded-full bg-blue-600" :style="{ width: `${Math.min(100, Number(course.progress || 0))}%` }"></div></div>
                    <div class="mt-2 text-xs text-slate-500">{{ course.risk_level }} · {{ course.last_accessed_at || '-' }}</div>
                  </div>
                  <div v-if="!(twinTabs.learning || []).length" class="rounded-md border border-dashed border-slate-300 p-4 text-sm text-slate-500">Chưa có dữ liệu học tập.</div>
                </div>
              </div>

              <div v-else-if="learnerTab === 'grades'" class="p-4">
                <div class="overflow-auto">
                  <table class="w-full text-left text-sm">
                    <thead class="bg-slate-50 text-xs uppercase text-slate-500"><tr><th class="px-3 py-2">Gradebook</th><th class="px-3 py-2">Percent</th><th class="px-3 py-2">Letter</th><th class="px-3 py-2">Status</th></tr></thead>
                    <tbody class="divide-y divide-slate-100">
                      <tr v-for="grade in twinTabs.grades || []" :key="grade.id"><td class="px-3 py-2">{{ grade.gradebook_id }}</td><td class="px-3 py-2">{{ grade.percent }}%</td><td class="px-3 py-2">{{ grade.letter_grade || '-' }}</td><td class="px-3 py-2">{{ grade.pass_status || '-' }}</td></tr>
                      <tr v-if="!(twinTabs.grades || []).length"><td colspan="4" class="px-3 py-5 text-center text-slate-500">Chưa có dữ liệu điểm.</td></tr>
                    </tbody>
                  </table>
                </div>
              </div>

              <div v-else-if="learnerTab === 'attendance'" class="p-4">
                <div class="grid gap-3 md:grid-cols-4">
                  <div v-for="item in [{label:'Rate',value:`${twinTabs.attendance?.summary?.attendance_rate || 0}%`},{label:'Absent',value:twinTabs.attendance?.summary?.absent || 0},{label:'Late',value:twinTabs.attendance?.summary?.late || 0},{label:'Excused',value:twinTabs.attendance?.summary?.excused || 0}]" :key="item.label" class="rounded-md border border-slate-200 bg-slate-50 p-3">
                    <div class="text-xs font-semibold uppercase text-slate-500">{{ item.label }}</div>
                    <div class="mt-2 text-2xl font-bold text-slate-950">{{ item.value }}</div>
                  </div>
                </div>
                <div class="mt-4 rounded-md bg-slate-50 p-3 text-sm text-slate-600">Exam eligibility {{ twinTabs.attendance?.forecast?.exam_eligibility || 0 }}% · Graduation {{ twinTabs.attendance?.forecast?.graduation_eligibility || '-' }}</div>
              </div>

              <div v-else-if="learnerTab === 'risk'" class="p-4">
                <div class="grid gap-3 md:grid-cols-3">
                  <div v-for="(value, key) in riskEngine" :key="key" class="rounded-md border border-slate-200 p-4">
                    <div class="text-xs font-semibold uppercase text-slate-500">{{ key }}</div>
                    <div class="mt-2 text-3xl font-bold text-slate-950">{{ Number(value || 0).toFixed(1) }}</div>
                  </div>
                </div>
                <div class="mt-4 rounded-md bg-blue-50 p-4 text-sm leading-6 text-blue-900">{{ advisor.learning_advice }}</div>
              </div>

              <div v-else-if="learnerTab === 'portfolio'" class="p-4">
                <div class="grid gap-3 md:grid-cols-2">
                  <div v-for="item in portfolioTabItems" :key="item.id" class="rounded-md border border-slate-200 p-3 text-sm">
                    <strong>{{ item.title }}</strong>
                    <div class="mt-1 text-xs text-slate-500">{{ item.item_type }} · {{ item.status }}</div>
                  </div>
                  <div v-if="!portfolioTabItems.length" class="rounded-md border border-dashed border-slate-300 p-4 text-sm text-slate-500">Chưa có portfolio item.</div>
                </div>
              </div>

              <div v-else-if="learnerTab === 'skills'" class="p-4">
                <div v-for="skill in twinTabs.skills || []" :key="skill.name" class="mb-3 rounded-md border border-slate-200 p-3">
                  <div class="flex justify-between text-sm"><strong>{{ skill.name }}</strong><span>{{ skill.score }}/100 · gap {{ skill.gap }}</span></div>
                  <div class="mt-2 h-2 rounded-full bg-slate-100"><div class="h-2 rounded-full bg-blue-600" :style="{ width: `${Math.min(100, skill.score)}%` }"></div></div>
                </div>
              </div>

              <div v-else-if="learnerTab === 'career'" class="p-4">
                <div class="grid gap-3 md:grid-cols-2">
                  <div v-for="action in careerReadiness" :key="`${action.phase}-${action.focus}`" class="rounded-md border border-slate-200 p-3 text-sm">
                    <div class="font-bold text-slate-950">{{ action.phase }} · {{ action.focus }}</div>
                    <p class="mt-2 leading-6 text-slate-600">{{ action.action }}</p>
                  </div>
                </div>
              </div>

              <div v-else class="p-4">
                <div class="grid gap-3 md:grid-cols-3">
                  <div v-for="item in [{label:'Certificates',value:twinTabs.certificates?.summary?.certificates || 0},{label:'Badges',value:twinTabs.certificates?.summary?.badges || 0},{label:'Verified',value:twinTabs.certificates?.summary?.verified || 0}]" :key="item.label" class="rounded-md border border-slate-200 p-4">
                    <div class="text-xs font-semibold uppercase text-slate-500">{{ item.label }}</div>
                    <div class="mt-2 text-2xl font-bold text-slate-950">{{ item.value }}</div>
                  </div>
                </div>
                <div class="mt-4 space-y-2">
                  <div v-for="item in credentialTimeline.slice(0, 5)" :key="`${item.type}-${item.title}-${item.at}`" class="rounded-md border border-slate-200 p-3 text-sm">
                    <strong>{{ item.title }}</strong><div class="mt-1 text-xs text-slate-500">{{ item.type }} · {{ item.status }} · {{ item.at || '-' }}</div>
                  </div>
                </div>
              </div>

              <div class="border-t border-slate-200 p-3">
                <div class="grid gap-2 md:grid-cols-5">
                  <div v-for="step in timeline360" :key="step.stage" class="rounded-md border border-slate-200 bg-slate-50 p-3 text-center text-xs font-semibold text-slate-700">
                    <TrendingUp class="mx-auto mb-2 h-4 w-4 text-blue-700" /> {{ step.stage }}
                    <div class="mt-1 font-normal text-slate-500">{{ step.status }}</div>
                  </div>
                </div>
              </div>
            </div>

            <div class="rounded-md border border-slate-200 bg-white shadow-sm">
              <div class="flex flex-wrap gap-2 border-b border-slate-200 p-3">
                <button v-for="tab in [{id:'idp',label:'Kế hoạch IDP'},{id:'skills',label:'Khoảng cách kỹ năng'},{id:'courses',label:'Dữ liệu học'},{id:'exam',label:'Kết quả thi'}]" :key="tab.id" class="rounded-md px-3 py-2 text-sm font-semibold" :class="activeTab === tab.id ? 'bg-slate-950 text-white' : 'bg-slate-100 text-slate-700'" @click="activeTab = tab.id">{{ tab.label }}</button>
              </div>

              <div v-if="activeTab === 'idp'" class="p-4">
                <div class="flex items-center gap-2 text-sm font-bold text-slate-950"><Target class="h-4 w-4 text-emerald-700" /> {{ detailPayload?.idp?.goal || 'Chưa có mục tiêu IDP' }}</div>
                <div class="mt-4 grid gap-3 md:grid-cols-2">
                  <div v-for="action in detailActions" :key="`${action.phase}-${action.focus}`" class="rounded-md border border-slate-200 bg-slate-50 p-4">
                    <div class="flex items-center justify-between gap-2"><strong class="text-sm text-slate-950">{{ action.phase }}</strong><span class="rounded-full bg-white px-2 py-1 text-xs text-slate-600">{{ action.focus }}</span></div>
                    <p class="mt-3 text-sm leading-6 text-slate-700">{{ action.action }}</p>
                    <div class="mt-3 text-xs font-semibold text-blue-700">Bằng chứng: {{ action.evidence }}</div>
                  </div>
                </div>
              </div>

              <div v-else-if="activeTab === 'skills'" class="p-4">
                <div v-for="skill in detailSkills" :key="skill.name" class="mb-3 rounded-md border border-slate-200 p-3">
                  <div class="flex justify-between text-sm"><strong>{{ skill.name }}</strong><span>{{ skill.score }}/100 · chênh {{ skill.gap }}</span></div>
                  <div class="mt-2 h-2 rounded-full bg-slate-100"><div class="h-2 rounded-full bg-blue-600" :style="{ width: `${Math.min(100, skill.score)}%` }"></div></div>
                </div>
              </div>

              <div v-else-if="activeTab === 'courses'" class="p-4">
                <div v-for="course in detailCourses" :key="course.course" class="mb-3 grid gap-2 rounded-md border border-slate-200 p-3 text-sm md:grid-cols-[1fr_120px_120px]">
                  <strong>{{ course.course }}</strong><span>{{ course.progress }}%</span><span>{{ course.risk_level }}</span>
                </div>
              </div>

              <div v-else class="p-4">
                <div v-for="attempt in detailAttempts" :key="`${attempt.exam}-${attempt.submitted_at}`" class="mb-3 grid gap-2 rounded-md border border-slate-200 p-3 text-sm md:grid-cols-[1fr_120px_120px]">
                  <strong>{{ attempt.exam }}</strong><span>{{ attempt.score }}/{{ attempt.max_score }}</span><span>{{ attempt.status }}</span>
                </div>
              </div>
            </div>

            <div class="rounded-md border border-slate-200 bg-white p-4 shadow-sm">
              <h2 class="flex items-center gap-2 text-sm font-bold text-slate-950"><Compass class="h-4 w-4 text-amber-600" /> Luồng định hướng</h2>
              <div class="mt-4 grid gap-2 md:grid-cols-5">
                <div v-for="step in ['Dữ liệu học', 'IDP cá nhân', 'Khoảng cách kỹ năng', 'Kế hoạch IDP 30/60/90', 'Hồ sơ/Nhà tuyển dụng']" :key="step" class="rounded-md border border-slate-200 bg-slate-50 p-3 text-center text-xs font-semibold text-slate-700">
                  <TrendingUp class="mx-auto mb-2 h-4 w-4 text-blue-700" /> {{ step }}
                </div>
              </div>
            </div>
          </main>
        </div>
      </div>

      <div v-else class="mx-auto max-w-7xl space-y-5">
        <header class="rounded-md border border-slate-200 bg-white p-5 shadow-sm">
          <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
              <div class="inline-flex items-center gap-2 rounded-full bg-slate-950 px-3 py-1 text-xs font-bold uppercase text-white">
                <Users class="h-4 w-4" /> Dashboard thí sinh
              </div>
              <h1 class="mt-3 text-2xl font-bold text-slate-950">Tổng hợp thí sinh digital twin</h1>
              <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-600">
                Danh sách có lọc, phân trang, thống kê theo lớp, theo đợt, theo năm và tra cứu nhanh từng cá nhân để mở IDP hoặc dữ liệu liên quan.
              </p>
            </div>
            <div class="flex gap-2">
              <button class="rounded-md border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700" :disabled="loading" @click="clearFilters">
                Xoá lọc
              </button>
              <button class="rounded-md bg-slate-950 px-4 py-2 text-sm font-semibold text-white" :disabled="loading" @click="refresh">
                {{ loading ? 'Đang tải...' : 'Tải lại' }}
              </button>
            </div>
          </div>

          <div class="mt-5 grid gap-3 lg:grid-cols-6">
            <label class="lg:col-span-2">
              <span class="mb-1 block text-xs font-semibold uppercase text-slate-500">Tìm theo cá nhân</span>
              <div class="relative">
                <Search class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                <input v-model="search" class="w-full rounded-md border border-slate-300 py-2 pl-9 pr-3 text-sm" placeholder="Tên, email, mã hoặc mã ghi danh" @keyup.enter="applyFilters" />
              </div>
            </label>
            <label>
              <span class="mb-1 block text-xs font-semibold uppercase text-slate-500">Theo lớp</span>
              <select v-model="selectedClassId" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                <option value="">Tất cả lớp</option>
                <option v-for="item in classOptions" :key="item.id" :value="item.id">{{ item.code }} - {{ item.label }}</option>
              </select>
            </label>
            <label>
              <span class="mb-1 block text-xs font-semibold uppercase text-slate-500">Theo đợt</span>
              <select v-model="selectedCohortId" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                <option value="">Tất cả đợt</option>
                <option v-for="item in cohortOptions" :key="item.id" :value="item.id">{{ item.code }} - {{ item.label }}</option>
              </select>
            </label>
            <label>
              <span class="mb-1 block text-xs font-semibold uppercase text-slate-500">Theo năm</span>
              <select v-model="selectedYear" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                <option value="">Tất cả năm</option>
                <option v-for="year in yearOptions" :key="year" :value="year">{{ year }}</option>
              </select>
            </label>
            <label>
              <span class="mb-1 block text-xs font-semibold uppercase text-slate-500">Trạng thái</span>
              <select v-model="selectedStatus" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                <option v-for="item in statusOptions" :key="item.value || 'all'" :value="item.value">{{ item.label }}</option>
              </select>
            </label>
          </div>

          <div class="mt-4 flex flex-wrap items-center gap-2">
            <button class="inline-flex items-center gap-2 rounded-md bg-blue-700 px-4 py-2 text-sm font-semibold text-white" :disabled="loading" @click="applyFilters">
              <Filter class="h-4 w-4" /> Áp dụng lọc
            </button>
            <label class="inline-flex items-center gap-2 rounded-md border border-slate-300 px-3 py-2 text-sm text-slate-700">
              <span>Trang</span>
              <select v-model="perPage" class="rounded-md border border-slate-300 px-2 py-1 text-sm" @change="applyFilters">
                <option :value="10">10</option>
                <option :value="20">20</option>
                <option :value="50">50</option>
              </select>
            </label>
            <span class="text-xs text-slate-500">Nhấn Enter trong ô tìm kiếm để áp dụng nhanh.</span>
          </div>
        </header>

        <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-6">
          <div v-for="(card, index) in overviewCards" :key="card.label" class="rounded-md border border-slate-200 bg-white p-4 shadow-sm" :class="statCardClass(index)">
            <div class="text-xs font-semibold uppercase text-slate-500">{{ card.label }}</div>
            <div class="mt-2 text-2xl font-bold text-slate-950">{{ card.value }}<span v-if="card.suffix" class="text-sm text-slate-500">{{ card.suffix }}</span></div>
            <div class="mt-2 text-xs leading-5 text-slate-500">{{ card.detail }}</div>
          </div>
        </div>

        <section class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_420px]">
          <div class="space-y-5">
            <div class="overflow-hidden rounded-md border border-slate-200 bg-white shadow-sm">
              <div class="border-b border-slate-200 px-5 py-4">
                <div class="text-sm font-bold text-slate-950">Danh sách thí sinh</div>
                <div class="mt-1 text-xs text-slate-500">Trang {{ overviewMeta.current_page || 1 }} / {{ overviewMeta.last_page || 1 }} · {{ overviewMeta.total || 0 }} thí sinh</div>
              </div>
              <div class="overflow-auto">
                <table class="w-full text-left text-sm">
                  <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                    <tr>
                      <th class="px-4 py-3">Thí sinh</th>
                      <th class="px-4 py-3">Lớp / Đợt</th>
                      <th class="px-4 py-3">Hoàn thành</th>
                      <th class="px-4 py-3">Risk</th>
                      <th class="px-4 py-3">Trạng thái</th>
                      <th class="px-4 py-3">IDP</th>
                    </tr>
                  </thead>
                  <tbody class="divide-y divide-slate-100">
                    <tr v-for="candidate in candidateRows" :key="candidate.user?.id" class="hover:bg-slate-50" :class="selectedCandidateId === candidate.user?.id ? 'bg-blue-50/60' : ''">
                      <td class="px-4 py-3">
                        <div class="font-semibold text-slate-950">{{ candidate.user?.full_name || 'Thí sinh' }}</div>
                        <div class="text-xs text-slate-500">{{ candidate.user?.code || '-' }} · {{ candidate.user?.email || '-' }}</div>
                        <div class="mt-1 text-[11px] text-slate-400">Hoạt động cuối: {{ dateTime(candidate.stats?.last_activity_at) }}</div>
                      </td>
                      <td class="px-4 py-3">
                        <div class="font-semibold text-slate-950">{{ candidate.primary_enrollment?.class_section?.name || candidate.primary_enrollment?.course?.title || '-' }}</div>
                        <div class="text-xs text-slate-500">{{ candidate.primary_enrollment?.class_section?.code || candidate.primary_enrollment?.course?.code || '-' }}</div>
                        <div class="mt-1 text-[11px] text-slate-400">{{ candidate.primary_enrollment?.cohort?.name || candidate.primary_enrollment?.cohort_group?.name || 'Chưa gắn đợt' }}</div>
                      </td>
                      <td class="px-4 py-3">
                        <div class="font-semibold text-slate-950">{{ fmt(candidate.stats?.avg_completion_percent, '%') }}</div>
                        <div class="mt-1 h-2 rounded-full bg-slate-100"><div class="h-2 rounded-full bg-blue-600" :style="{ width: `${Math.min(100, Number(candidate.stats?.avg_completion_percent || 0))}%` }"></div></div>
                      </td>
                      <td class="px-4 py-3">
                        <div class="font-semibold text-slate-950">{{ fmt(candidate.stats?.avg_risk_score) }}</div>
                        <div class="mt-1 text-[11px] text-slate-400">Readiness {{ fmt(candidate.stats?.readiness_score) }}</div>
                      </td>
                      <td class="px-4 py-3">
                        <span class="rounded px-2 py-1 text-xs font-semibold" :class="badgeClass(candidate.primary_enrollment?.status)">{{ candidate.primary_enrollment?.status || 'unknown' }}</span>
                      </td>
                      <td class="px-4 py-3">
                        <button class="font-semibold text-blue-700" @click="openCandidate(candidate)">Xem IDP</button>
                      </td>
                    </tr>
                    <tr v-if="!candidateRows.length && !loading">
                      <td colspan="6" class="px-4 py-8 text-center text-slate-500">Không có thí sinh phù hợp bộ lọc.</td>
                    </tr>
                  </tbody>
                </table>
              </div>
              <div class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-200 px-5 py-4">
                <div class="text-xs text-slate-500">
                  <span class="font-semibold text-slate-700">{{ overviewMeta.total || 0 }}</span> kết quả
                </div>
                <div class="flex items-center gap-2">
                  <button class="inline-flex items-center gap-2 rounded-md border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700 disabled:opacity-50" :disabled="loading || Number(overviewMeta.current_page || 1) <= 1" @click="changePage(-1)">
                    <ChevronLeft class="h-4 w-4" /> Trước
                  </button>
                  <button class="inline-flex items-center gap-2 rounded-md border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700 disabled:opacity-50" :disabled="loading || Number(overviewMeta.current_page || 1) >= Number(overviewMeta.last_page || 1)" @click="changePage(1)">
                    Sau <ChevronRight class="h-4 w-4" />
                  </button>
                </div>
              </div>
            </div>

            <div class="grid gap-4 lg:grid-cols-3">
              <div class="rounded-md border border-slate-200 bg-white p-4 shadow-sm">
                <h2 class="text-sm font-bold text-slate-950">Theo lớp</h2>
                <div class="mt-4 space-y-3">
                  <div v-for="item in breakdownClasses" :key="item.id || item.code" class="rounded-md border border-slate-200 bg-slate-50 p-3 text-sm">
                    <div class="flex items-center justify-between gap-3">
                      <div class="min-w-0">
                        <div class="truncate font-semibold text-slate-950">{{ item.name || '-' }}</div>
                        <div class="truncate text-xs text-slate-500">{{ item.code || '-' }} · {{ item.course_title || '' }}</div>
                      </div>
                      <div class="text-right">
                        <div class="font-bold text-slate-950">{{ item.candidates_count }}</div>
                        <div class="text-[11px] text-slate-500">thí sinh</div>
                      </div>
                    </div>
                    <div class="mt-2 text-[11px] text-slate-500">Hoàn thành TB {{ fmt(item.avg_completion_percent, '%') }} · Risk TB {{ fmt(item.avg_risk_score) }}</div>
                  </div>
                </div>
              </div>

              <div class="rounded-md border border-slate-200 bg-white p-4 shadow-sm">
                <h2 class="text-sm font-bold text-slate-950">Theo đợt</h2>
                <div class="mt-4 space-y-3">
                  <div v-for="item in breakdownCohorts" :key="item.id || item.code" class="rounded-md border border-slate-200 bg-slate-50 p-3 text-sm">
                    <div class="flex items-center justify-between gap-3">
                      <div class="min-w-0">
                        <div class="truncate font-semibold text-slate-950">{{ item.name || '-' }}</div>
                        <div class="truncate text-xs text-slate-500">{{ item.code || '-' }}</div>
                      </div>
                      <div class="text-right">
                        <div class="font-bold text-slate-950">{{ item.candidates_count }}</div>
                        <div class="text-[11px] text-slate-500">thí sinh</div>
                      </div>
                    </div>
                    <div class="mt-2 text-[11px] text-slate-500">Hoàn thành TB {{ fmt(item.avg_completion_percent, '%') }} · Risk TB {{ fmt(item.avg_risk_score) }}</div>
                  </div>
                </div>
              </div>

              <div class="rounded-md border border-slate-200 bg-white p-4 shadow-sm">
                <h2 class="text-sm font-bold text-slate-950">Theo năm</h2>
                <div class="mt-4 space-y-3">
                  <div v-for="item in breakdownYears" :key="item.academic_year || 'unknown'" class="rounded-md border border-slate-200 bg-slate-50 p-3 text-sm">
                    <div class="flex items-center justify-between gap-3">
                      <div class="min-w-0">
                        <div class="truncate font-semibold text-slate-950">{{ item.academic_year || '-' }}</div>
                        <div class="truncate text-xs text-slate-500">Năm ghi danh</div>
                      </div>
                      <div class="text-right">
                        <div class="font-bold text-slate-950">{{ item.candidates_count }}</div>
                        <div class="text-[11px] text-slate-500">thí sinh</div>
                      </div>
                    </div>
                    <div class="mt-2 text-[11px] text-slate-500">Hoàn thành TB {{ fmt(item.avg_completion_percent, '%') }} · Risk TB {{ fmt(item.avg_risk_score) }}</div>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <aside class="space-y-4">
            <div class="rounded-md border border-slate-200 bg-white p-4 shadow-sm">
              <div class="flex items-start justify-between gap-3">
                <div>
                  <div class="text-xs font-bold uppercase text-blue-700">IDP thí sinh</div>
                  <h2 class="mt-1 text-lg font-bold text-slate-950">{{ detailLearner?.name || detailLearner?.full_name || 'Chọn thí sinh' }}</h2>
                  <p class="mt-1 text-xs text-slate-500">{{ detailLearner?.code || detailLearner?.email || 'Nhấn "Xem IDP" ở danh sách' }}</p>
                </div>
                <div class="min-w-24 rounded-md bg-slate-950 px-3 py-2 text-right text-white">
                  <div class="text-[10px] uppercase text-slate-300">Readiness</div>
                  <div class="text-2xl font-bold">{{ detailScore }}</div>
                </div>
              </div>
              <div class="mt-4 h-2 rounded-full bg-slate-100">
                <div class="h-2 rounded-full bg-cyan-500" :style="{ width: `${Math.min(100, detailScore)}%` }"></div>
              </div>
              <div v-if="selectedCandidateSummary" class="mt-3 rounded-md bg-slate-50 p-3 text-xs text-slate-600">
                {{ selectedCandidateSummary?.primary_enrollment?.class_section?.name || selectedCandidateSummary?.primary_enrollment?.course?.title || 'Chưa có lớp gắn' }} ·
                {{ selectedCandidateSummary?.primary_enrollment?.cohort?.name || selectedCandidateSummary?.primary_enrollment?.cohort_group?.name || 'Chưa có đợt' }}
              </div>
            </div>

            <div class="rounded-md border border-slate-200 bg-white p-4 shadow-sm">
              <h3 class="flex items-center gap-2 text-sm font-bold text-slate-950"><LineChart class="h-4 w-4 text-blue-700" /> Bản đồ năng lực</h3>
              <svg viewBox="0 0 300 280" class="mt-3 h-64 w-full">
                <polygon points="150,24 268,96 222,236 78,236 32,96" fill="none" stroke="#cbd5e1" />
                <polygon points="150,60 226,106 196,196 104,196 74,106" fill="none" stroke="#e2e8f0" />
                <polygon :points="radarPoints" fill="#0891b222" stroke="#0891b2" stroke-width="2" />
              </svg>
              <div class="space-y-2">
                <div v-for="item in detailMetrics" :key="item.label" class="text-sm">
                  <div class="flex justify-between"><span>{{ item.label }}</span><strong>{{ item.value }}{{ item.suffix }}</strong></div>
                  <div class="mt-1 h-2 rounded-full bg-slate-100"><div class="h-2 rounded-full bg-blue-700" :style="{ width: `${Math.min(100, Number(item.value || 0))}%` }"></div></div>
                </div>
              </div>
            </div>

            <div class="rounded-md border border-slate-200 bg-white shadow-sm">
              <div class="flex flex-wrap gap-2 border-b border-slate-200 p-3">
                <button v-for="tab in [{id:'idp',label:'Kế hoạch IDP'},{id:'skills',label:'Khoảng cách kỹ năng'},{id:'courses',label:'Dữ liệu học'},{id:'exam',label:'Kết quả thi'}]" :key="tab.id" class="rounded-md px-3 py-2 text-xs font-semibold" :class="activeTab === tab.id ? 'bg-slate-950 text-white' : 'bg-slate-100 text-slate-700'" @click="activeTab = tab.id">{{ tab.label }}</button>
              </div>

              <div v-if="activeTab === 'idp'" class="p-4">
                <div class="flex items-center gap-2 text-sm font-bold text-slate-950"><Target class="h-4 w-4 text-emerald-700" /> {{ detailPayload?.idp?.goal || 'Chưa có mục tiêu IDP' }}</div>
                <div class="mt-4 grid gap-3">
                  <div v-for="action in detailActions" :key="`${action.phase}-${action.focus}`" class="rounded-md border border-slate-200 bg-slate-50 p-3">
                    <div class="flex items-center justify-between gap-2"><strong class="text-sm text-slate-950">{{ action.phase }}</strong><span class="rounded-full bg-white px-2 py-1 text-[11px] text-slate-600">{{ action.focus }}</span></div>
                    <p class="mt-2 text-sm leading-6 text-slate-700">{{ action.action }}</p>
                    <div class="mt-2 text-[11px] font-semibold text-blue-700">Bằng chứng: {{ action.evidence }}</div>
                  </div>
                  <div v-if="!detailActions.length" class="text-sm text-slate-500">Chưa có hành động IDP.</div>
                </div>
              </div>

              <div v-else-if="activeTab === 'skills'" class="p-4">
                <div v-for="skill in detailSkills" :key="skill.name" class="mb-3 rounded-md border border-slate-200 p-3">
                  <div class="flex justify-between text-sm"><strong>{{ skill.name }}</strong><span>{{ skill.score }}/100 · chênh {{ skill.gap }}</span></div>
                  <div class="mt-2 h-2 rounded-full bg-slate-100"><div class="h-2 rounded-full bg-blue-600" :style="{ width: `${Math.min(100, skill.score)}%` }"></div></div>
                </div>
                <div v-if="!detailSkills.length" class="text-sm text-slate-500">Chưa có dữ liệu kỹ năng.</div>
              </div>

              <div v-else-if="activeTab === 'courses'" class="p-4">
                <div v-for="course in detailCourses" :key="course.course" class="mb-3 grid gap-2 rounded-md border border-slate-200 p-3 text-sm md:grid-cols-[1fr_120px_120px]">
                  <strong>{{ course.course }}</strong><span>{{ course.progress }}%</span><span>{{ course.risk_level }}</span>
                </div>
                <div v-if="!detailCourses.length" class="text-sm text-slate-500">Chưa có dữ liệu khóa học.</div>
              </div>

              <div v-else class="p-4">
                <div v-for="attempt in detailAttempts" :key="`${attempt.exam}-${attempt.submitted_at}`" class="mb-3 grid gap-2 rounded-md border border-slate-200 p-3 text-sm md:grid-cols-[1fr_120px_120px]">
                  <strong>{{ attempt.exam }}</strong><span>{{ attempt.score }}/{{ attempt.max_score }}</span><span>{{ attempt.status }}</span>
                </div>
                <div v-if="!detailAttempts.length" class="text-sm text-slate-500">Chưa có kết quả thi.</div>
              </div>
            </div>
          </aside>
        </section>
      </div>
    </section>
  </EraLmsLayout>
</template>
