<script setup>
import { computed, onMounted, ref } from 'vue'
import {
  Activity,
  Award,
  BarChart3,
  BookOpen,
  ChevronRight,
  ClipboardCheck,
  ExternalLink,
  GraduationCap,
  Play,
  RefreshCw,
  ShieldAlert,
  Target,
  TrendingUp,
  Users,
} from '@lucide/vue'
import EraLmsLayout from '@/Layouts/EraLmsLayout.vue'

const props = defineProps({
  sessionUser: { type: Object, default: null },
  apiHeaders: { type: Object, default: () => ({}) },
})

defineEmits(['logout'])

const loading = ref(true)
const error = ref('')
const dashboard = ref(null)
const selectedCourseId = ref(null)
const drillTab = ref('courses')

const courses = computed(() => dashboard.value?.course_grades || [])
const charts = computed(() => dashboard.value?.charts || {})
const metrics = computed(() => dashboard.value?.metrics || {})
const analytics = computed(() => dashboard.value?.analytics || {})
const forecast = computed(() => dashboard.value?.forecast || {})
const context = computed(() => dashboard.value?.context || {})
const selectedCourse = computed(() => courses.value.find((course) => Number(course.gradebook_id) === Number(selectedCourseId.value)) || courses.value[0] || null)
const visibleCourses = computed(() => courses.value.slice(0, 12))
const trend = computed(() => (charts.value.line || []).slice(-16))
const weakestCourse = computed(() => courses.value.filter((course) => course.percent !== null && course.percent !== undefined).sort((a, b) => Number(a.percent || 0) - Number(b.percent || 0))[0] || null)
const primaryCourse = computed(() => selectedCourse.value || courses.value[0] || null)

const metricCards = computed(() => [
  { label: 'Current GPA', value: formatGpa(metrics.value.current_gpa), detail: selectedCourse.value?.course_code || context.value.current_semester, icon: GraduationCap, tone: 'blue' },
  { label: 'Semester GPA', value: formatGpa(metrics.value.semester_gpa), detail: context.value.current_semester || '-', icon: TrendingUp, tone: 'emerald' },
  { label: 'Program GPA', value: formatGpa(metrics.value.program_gpa), detail: `${context.value.credits_completed || 0}/${context.value.credits_attempted || 0} tín chỉ`, icon: Award, tone: 'violet' },
  { label: 'Forecast GPA', value: formatGpa(metrics.value.expected_gpa), detail: `Graduation ${formatGpa(metrics.value.graduation_gpa)}`, icon: Target, tone: 'amber' },
])

const trendPoints = computed(() => {
  const values = trend.value
  if (!values.length) return ''

  const width = 360
  const height = 142
  const pad = 18
  const step = values.length > 1 ? (width - pad * 2) / (values.length - 1) : 0

  return values.map((item, index) => {
    const x = pad + (index * step)
    const y = height - pad - ((Number(item.percent || 0) / 100) * (height - pad * 2))
    return `${x},${y}`
  }).join(' ')
})

const trendDots = computed(() => {
  const values = trend.value
  if (!values.length) return []

  const width = 360
  const height = 142
  const pad = 18
  const step = values.length > 1 ? (width - pad * 2) / (values.length - 1) : 0

  return values.map((item, index) => ({
    ...item,
    x: pad + (index * step),
    y: height - pad - ((Number(item.percent || 0) / 100) * (height - pad * 2)),
  }))
})

const distributionMax = computed(() => Math.max(1, ...(charts.value.distribution || []).map((item) => Number(item.value || 0))))
const radar = computed(() => charts.value.radar || [])
const radarAxes = computed(() => {
  const count = Math.max(radar.value.length, 1)
  const center = 72
  const radius = 54

  return radar.value.map((item, index) => {
    const angle = ((Math.PI * 2) / count) * index - (Math.PI / 2)
    return {
      ...item,
      x: center + Math.cos(angle) * radius,
      y: center + Math.sin(angle) * radius,
      labelX: center + Math.cos(angle) * (radius + 16),
      labelY: center + Math.sin(angle) * (radius + 16),
    }
  })
})
const radarPolygon = computed(() => {
  const count = Math.max(radar.value.length, 1)
  const center = 72
  const radius = 54

  return radar.value.map((item, index) => {
    const angle = ((Math.PI * 2) / count) * index - (Math.PI / 2)
    const value = Math.max(0, Math.min(100, Number(item.value || 0)))
    const pointRadius = radius * value / 100
    return `${center + Math.cos(angle) * pointRadius},${center + Math.sin(angle) * pointRadius}`
  }).join(' ')
})

const comparisonRows = computed(() => dashboard.value?.comparison || [])
const drillTabs = [
  { key: 'courses', label: 'Course', icon: BookOpen },
  { key: 'quiz', label: 'Quiz', icon: ClipboardCheck },
  { key: 'assignment', label: 'Assignment', icon: Activity },
  { key: 'exam', label: 'Exam', icon: ShieldAlert },
]
const drillRows = computed(() => {
  if (drillTab.value === 'courses') return selectedCourse.value ? [selectedCourse.value] : visibleCourses.value

  const rows = dashboard.value?.drilldown?.[drillTab.value] || []
  if (!selectedCourse.value) return rows

  return rows.filter((item) => item.course_code === selectedCourse.value.course_code)
})

async function load() {
  loading.value = true
  error.value = ''
  try {
    const response = await fetch('/api/v1/student/grades', { headers: props.apiHeaders })
    const payload = await response.json().catch(() => ({}))
    if (!response.ok) throw new Error(payload.message || 'Không tải được dashboard điểm.')
    dashboard.value = payload.data || payload
    selectedCourseId.value = dashboard.value?.course_grades?.[0]?.gradebook_id || null
  } catch (err) {
    error.value = err.message || 'Không tải được dashboard điểm.'
  } finally {
    loading.value = false
  }
}

function selectCourse(course) {
  selectedCourseId.value = course.gradebook_id
}

function formatGpa(value) {
  return Number(value || 0).toFixed(2)
}

function pct(value) {
  return `${Number(value || 0).toFixed(1)}%`
}

function toneClass(tone) {
  return {
    blue: 'border-blue-200 bg-blue-50 text-blue-900',
    emerald: 'border-emerald-200 bg-emerald-50 text-emerald-900',
    violet: 'border-violet-200 bg-violet-50 text-violet-900',
    amber: 'border-amber-200 bg-amber-50 text-amber-900',
  }[tone] || 'border-slate-200 bg-white text-slate-900'
}

function riskClass(risk) {
  return {
    low: 'bg-emerald-50 text-emerald-700 border-emerald-200',
    medium: 'bg-amber-50 text-amber-800 border-amber-200',
    high: 'bg-rose-50 text-rose-700 border-rose-200',
  }[risk] || 'bg-slate-50 text-slate-700 border-slate-200'
}

function passClass(status) {
  return status === 'passed'
    ? 'bg-emerald-50 text-emerald-700 border-emerald-200'
    : 'bg-rose-50 text-rose-700 border-rose-200'
}

function deltaClass(delta) {
  return Number(delta || 0) >= 0 ? 'text-emerald-700' : 'text-rose-700'
}

function barWidth(value, max = 100) {
  return `${Math.max(4, Math.min(100, Number(value || 0) * 100 / Math.max(Number(max || 1), 1)))}%`
}

function itemActionLabel(item) {
  if ((item.type === 'quiz' || item.type === 'exam') && item.source_id) return 'Làm bài'
  if (item.type === 'assignment' && item.source_id) return 'Nộp bài'
  if (item.href) return 'Mở trong khóa'
  return 'Mở'
}

function itemTypeLabel(type) {
  return {
    quiz: 'Quiz',
    assignment: 'Assignment',
    exam: 'Exam',
    attendance: 'Attendance',
    coursework: 'Coursework',
  }[type] || type || 'Grade item'
}

onMounted(load)
</script>

<template>
  <EraLmsLayout :session-user="sessionUser" @logout="$emit('logout')">
    <template #breadcrumb>Sổ điểm / Academic Performance</template>

    <section class="mx-auto max-w-7xl space-y-4">
      <div class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
        <div class="grid gap-0 lg:grid-cols-[1fr_340px]">
          <div class="bg-slate-950 p-5 text-white sm:p-6">
            <div class="flex flex-wrap items-center gap-2 text-xs font-semibold uppercase text-cyan-200">
              <span>Student Grade Dashboard</span>
              <span class="rounded border border-white/15 px-2 py-1 text-white/80">{{ context.current_semester || '-' }}</span>
            </div>
            <h1 class="mt-3 text-2xl font-bold sm:text-3xl">Grade & Academic Performance</h1>
            <div class="mt-3 grid gap-2 text-sm text-slate-300 sm:grid-cols-2">
              <span>{{ dashboard?.student?.full_name || sessionUser?.full_name || 'Sinh viên' }}</span>
              <span>{{ dashboard?.student?.code || sessionUser?.code || '-' }}</span>
              <span>{{ context.program || 'Chương trình đào tạo' }}</span>
              <span>{{ context.courses_count || 0 }} học phần có điểm</span>
            </div>
          </div>
          <aside class="border-t border-slate-200 bg-slate-50 p-5 lg:border-l lg:border-t-0">
            <div class="flex items-center justify-between gap-3">
              <div>
                <div class="text-xs font-semibold uppercase text-slate-500">Forecast</div>
                <div class="mt-1 text-2xl font-bold text-slate-950">{{ formatGpa(forecast.graduation_gpa) }}</div>
              </div>
              <button class="inline-flex h-10 items-center gap-2 rounded-md border border-slate-300 bg-white px-3 text-sm font-semibold text-slate-700 hover:bg-slate-100" :disabled="loading" @click="load">
                <RefreshCw class="h-4 w-4" :class="{ 'animate-spin': loading }" />
                Tải lại
              </button>
            </div>
            <div class="mt-4 rounded-md border border-slate-200 bg-white p-3 text-sm">
              <div class="flex justify-between gap-3">
                <span class="text-slate-500">Confidence</span>
                <span class="font-semibold text-slate-950">{{ forecast.confidence || '-' }}</span>
              </div>
              <div class="mt-2 flex justify-between gap-3">
                <span class="text-slate-500">Trend delta</span>
                <span class="font-semibold" :class="deltaClass(forecast.trend_delta)">{{ formatGpa(forecast.trend_delta) }}</span>
              </div>
              <div class="mt-2 flex justify-between gap-3">
                <span class="text-slate-500">API</span>
                <span class="font-semibold text-slate-950">{{ dashboard?.performance?.duration_ms || 0 }}ms</span>
              </div>
            </div>
            <div class="mt-3 grid gap-2">
              <a v-if="primaryCourse?.href" :href="primaryCourse.href" class="inline-flex h-10 items-center justify-center gap-2 rounded-md bg-slate-950 px-3 text-sm font-bold text-white">
                <Play class="h-4 w-4" /> Mở học phần đang chọn
              </a>
              <a v-if="primaryCourse?.journey_href" :href="primaryCourse.journey_href" class="inline-flex h-10 items-center justify-center gap-2 rounded-md border border-slate-300 bg-white px-3 text-sm font-semibold text-slate-700">
                Learning Journey <ExternalLink class="h-4 w-4" />
              </a>
            </div>
          </aside>
        </div>
      </div>

      <div v-if="loading" class="rounded-lg border border-slate-200 bg-white p-5 text-sm text-slate-500 shadow-sm">Đang tải dashboard điểm...</div>
      <div v-else-if="error" class="rounded-lg border border-rose-200 bg-rose-50 p-5 text-sm font-semibold text-rose-700">{{ error }}</div>

      <template v-else>
        <section class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
          <article v-for="item in metricCards" :key="item.label" class="rounded-lg border p-4 shadow-sm" :class="toneClass(item.tone)">
            <div class="flex items-start justify-between gap-3">
              <div>
                <div class="text-xs font-semibold uppercase opacity-70">{{ item.label }}</div>
                <div class="mt-2 text-3xl font-bold">{{ item.value }}</div>
              </div>
              <div class="grid h-10 w-10 place-items-center rounded-md bg-white/70">
                <component :is="item.icon" class="h-5 w-5" />
              </div>
            </div>
            <div class="mt-3 text-sm opacity-80">{{ item.detail }}</div>
          </article>
        </section>

        <section class="grid gap-4 xl:grid-cols-[1.2fr_.8fr]">
          <article class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
            <div class="flex items-center justify-between gap-3">
              <div>
                <h2 class="text-sm font-bold text-slate-950">Grade Trend</h2>
                <p class="mt-1 text-xs text-slate-500">Theo % tổng kết từng học phần.</p>
              </div>
              <BarChart3 class="h-5 w-5 text-blue-700" />
            </div>
            <div class="mt-4 overflow-hidden rounded-md bg-slate-50 p-3">
              <svg viewBox="0 0 360 142" class="h-44 w-full">
                <line x1="18" y1="124" x2="342" y2="124" stroke="#cbd5e1" stroke-width="1" />
                <line x1="18" y1="18" x2="18" y2="124" stroke="#cbd5e1" stroke-width="1" />
                <polyline v-if="trendPoints" :points="trendPoints" fill="none" stroke="#2563eb" stroke-width="4" stroke-linecap="round" stroke-linejoin="round" />
                <g v-for="dot in trendDots" :key="`${dot.label}-${dot.percent}`">
                  <circle :cx="dot.x" :cy="dot.y" r="5" fill="#ffffff" stroke="#2563eb" stroke-width="3" />
                </g>
              </svg>
              <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-4">
                <div v-for="item in trend" :key="`${item.label}-${item.percent}`" class="rounded-md bg-white px-3 py-2 text-xs">
                  <div class="font-semibold text-slate-950">{{ item.label }}</div>
                  <div class="text-slate-500">{{ pct(item.percent) }} · GPA {{ formatGpa(item.gpa) }}</div>
                </div>
              </div>
            </div>
          </article>

          <article class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
            <div class="flex items-center justify-between gap-3">
              <div>
                <h2 class="text-sm font-bold text-slate-950">Grade Distribution</h2>
                <p class="mt-1 text-xs text-slate-500">Phân bổ A/B/C/D/F của sinh viên.</p>
              </div>
              <Award class="h-5 w-5 text-amber-600" />
            </div>
            <div class="mt-4 space-y-3">
              <div v-for="item in charts.distribution || []" :key="item.label">
                <div class="mb-1 flex justify-between text-xs">
                  <span class="font-semibold text-slate-700">{{ item.label }}</span>
                  <span class="text-slate-500">{{ item.value }}</span>
                </div>
                <div class="h-3 overflow-hidden rounded-full bg-slate-100">
                  <div class="h-3 rounded-full bg-amber-500" :style="{ width: barWidth(item.value, distributionMax) }"></div>
                </div>
              </div>
            </div>
          </article>
        </section>

        <section class="grid gap-4 xl:grid-cols-3">
          <article class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm xl:col-span-2">
            <div class="flex flex-wrap items-center justify-between gap-3">
              <div>
                <h2 class="text-sm font-bold text-slate-950">Course Grades</h2>
                <p class="mt-1 text-xs text-slate-500">Click một học phần để xem drilldown.</p>
              </div>
              <div class="flex flex-wrap items-center gap-2">
                <a v-if="weakestCourse?.href" :href="weakestCourse.href" class="rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-xs font-bold text-amber-800">Ôn {{ weakestCourse.course_code }}</a>
                <span class="rounded-md bg-slate-950 px-2 py-1 text-xs font-semibold text-white">{{ visibleCourses.length }}/{{ courses.length }} courses</span>
              </div>
            </div>
            <div class="mt-4 grid gap-3 md:grid-cols-2">
              <button
                v-for="course in visibleCourses"
                :key="course.gradebook_id"
                class="rounded-lg border p-3 text-left transition hover:border-blue-300 hover:bg-blue-50/50"
                :class="Number(selectedCourseId) === Number(course.gradebook_id) ? 'border-blue-400 bg-blue-50 ring-2 ring-blue-100' : 'border-slate-200 bg-white'"
                @click="selectCourse(course)"
              >
                <div class="flex items-start justify-between gap-3">
                  <div class="min-w-0">
                    <div class="truncate text-sm font-bold text-slate-950">{{ course.course_title }}</div>
                    <div class="mt-1 text-xs text-slate-500">{{ course.course_code }} · {{ course.credits }} tín chỉ</div>
                  </div>
                  <span class="rounded-md bg-slate-950 px-2 py-1 text-sm font-bold text-white">{{ course.letter_grade }}</span>
                </div>
                <div class="mt-3 flex items-center gap-3">
                  <div class="h-2 flex-1 overflow-hidden rounded-full bg-slate-100">
                    <div class="h-2 rounded-full bg-blue-600" :style="{ width: barWidth(course.percent) }"></div>
                  </div>
                  <span class="w-14 text-right text-sm font-semibold text-slate-900">{{ pct(course.percent) }}</span>
                </div>
                <div class="mt-3 flex flex-wrap gap-2">
                  <span class="rounded border px-2 py-1 text-xs font-semibold" :class="passClass(course.pass_status)">{{ course.pass_status }}</span>
                  <span class="rounded border px-2 py-1 text-xs font-semibold" :class="riskClass(course.retake_risk)">Risk {{ course.retake_risk }}</span>
                </div>
              </button>
              <div v-if="!courses.length" class="rounded-md border border-slate-200 bg-slate-50 p-4 text-sm text-slate-500 md:col-span-2">Chưa có điểm học phần.</div>
            </div>
            <div v-if="courses.length > visibleCourses.length" class="mt-3 rounded-md border border-slate-200 bg-slate-50 px-3 py-2 text-xs text-slate-500">
              Đang hiển thị {{ visibleCourses.length }} học phần gần nhất trong tổng {{ courses.length }} học phần.
            </div>
          </article>

          <aside class="space-y-4">
            <article class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
              <h2 class="flex items-center gap-2 text-sm font-bold text-slate-950">
                <Users class="h-4 w-4 text-blue-700" />
                Comparison
              </h2>
              <div class="mt-4 space-y-3">
                <div v-for="row in comparisonRows" :key="row.scope" class="rounded-md border border-slate-200 bg-slate-50 p-3">
                  <div class="flex justify-between gap-3 text-sm">
                    <span class="font-semibold text-slate-950">{{ row.scope }}</span>
                    <span class="font-bold" :class="deltaClass(row.delta)">{{ row.delta >= 0 ? '+' : '' }}{{ row.delta }}</span>
                  </div>
                  <div class="mt-2 grid grid-cols-2 gap-2 text-xs">
                    <div><span class="text-slate-500">Student</span><div class="font-semibold text-slate-950">{{ row.student }}</div></div>
                    <div><span class="text-slate-500">Average</span><div class="font-semibold text-slate-950">{{ row.average }}</div></div>
                  </div>
                </div>
              </div>
            </article>

            <article class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
              <h2 class="flex items-center gap-2 text-sm font-bold text-slate-950">
                <Activity class="h-4 w-4 text-emerald-700" />
                Analytics
              </h2>
              <div class="mt-4 grid grid-cols-2 gap-3">
                <div class="rounded-md bg-emerald-50 p-3">
                  <div class="text-xs font-semibold uppercase text-emerald-700">Pass Rate</div>
                  <div class="mt-2 text-2xl font-bold text-emerald-900">{{ pct(analytics.pass_rate) }}</div>
                </div>
                <div class="rounded-md bg-rose-50 p-3">
                  <div class="text-xs font-semibold uppercase text-rose-700">Fail Rate</div>
                  <div class="mt-2 text-2xl font-bold text-rose-900">{{ pct(analytics.fail_rate) }}</div>
                </div>
                <div class="rounded-md bg-blue-50 p-3">
                  <div class="text-xs font-semibold uppercase text-blue-700">Peer Pass</div>
                  <div class="mt-2 text-2xl font-bold text-blue-900">{{ pct(analytics.peer_pass_rate) }}</div>
                </div>
                <div class="rounded-md bg-amber-50 p-3">
                  <div class="text-xs font-semibold uppercase text-amber-700">Retake Risk</div>
                  <div class="mt-2 text-2xl font-bold text-amber-900">{{ pct(analytics.retake_risk) }}</div>
                </div>
              </div>
            </article>
          </aside>
        </section>

        <section class="grid gap-4 xl:grid-cols-[360px_1fr]">
          <article class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
            <h2 class="text-sm font-bold text-slate-950">Radar</h2>
            <div class="mt-4 grid place-items-center">
              <svg viewBox="0 0 144 144" class="h-56 w-56">
                <circle cx="72" cy="72" r="54" fill="#f8fafc" stroke="#e2e8f0" />
                <circle cx="72" cy="72" r="36" fill="none" stroke="#e2e8f0" />
                <circle cx="72" cy="72" r="18" fill="none" stroke="#e2e8f0" />
                <g v-for="axis in radarAxes" :key="axis.key">
                  <line x1="72" y1="72" :x2="axis.x" :y2="axis.y" stroke="#cbd5e1" />
                  <text :x="axis.labelX" :y="axis.labelY" text-anchor="middle" class="fill-slate-600 text-[8px]">{{ axis.label }}</text>
                </g>
                <polygon v-if="radarPolygon" :points="radarPolygon" fill="rgba(37,99,235,.22)" stroke="#2563eb" stroke-width="2" />
              </svg>
            </div>
            <div class="mt-3 space-y-2">
              <div v-for="item in radar" :key="item.key" class="flex justify-between text-xs">
                <span class="text-slate-500">{{ item.label }}</span>
                <span class="font-semibold text-slate-950">{{ pct(item.value) }}</span>
              </div>
            </div>
          </article>

          <article class="rounded-lg border border-slate-200 bg-white shadow-sm">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 p-4">
              <div>
                <h2 class="text-sm font-bold text-slate-950">Drilldown</h2>
                <p class="mt-1 text-xs text-slate-500">{{ selectedCourse?.course_title || 'Chọn học phần' }}</p>
              </div>
              <div class="flex flex-wrap gap-2">
                <button v-for="tab in drillTabs" :key="tab.key" class="inline-flex items-center gap-2 rounded-md px-3 py-2 text-sm font-semibold" :class="drillTab === tab.key ? 'bg-slate-950 text-white' : 'border border-slate-300 text-slate-700 hover:bg-slate-50'" @click="drillTab = tab.key">
                  <component :is="tab.icon" class="h-4 w-4" />
                  {{ tab.label }}
                </button>
              </div>
            </div>
            <div class="grid gap-3 p-4 md:grid-cols-2">
              <template v-if="drillTab === 'courses'">
                <div v-for="course in drillRows" :key="course.gradebook_id" class="rounded-md border border-slate-200 bg-slate-50 p-3">
                  <div class="flex items-start justify-between gap-3">
                    <div>
                      <div class="text-sm font-bold text-slate-950">{{ course.course_code }}</div>
                      <div class="mt-1 text-xs text-slate-500">{{ course.status }} · {{ course.semester }}</div>
                    </div>
                    <div class="text-right">
                      <div class="text-lg font-bold text-slate-950">{{ formatGpa(course.grade_points) }}</div>
                      <div class="text-xs text-slate-500">{{ pct(course.percent) }}</div>
                    </div>
                  </div>
                  <div class="mt-3 flex flex-wrap gap-2">
                    <a v-if="course.href" :href="course.href" class="inline-flex items-center gap-1 rounded-md bg-slate-950 px-3 py-2 text-xs font-bold text-white">
                      Mở học phần <ExternalLink class="h-3.5 w-3.5" />
                    </a>
                    <a v-if="course.journey_href" :href="course.journey_href" class="inline-flex items-center gap-1 rounded-md border border-slate-300 bg-white px-3 py-2 text-xs font-semibold text-slate-700">
                      Journey
                    </a>
                  </div>
                </div>
              </template>
              <template v-else>
                <div v-for="item in drillRows" :key="`${item.course_code}-${item.grade_item_id}`" class="rounded-md border border-slate-200 bg-slate-50 p-3">
                  <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                      <div class="truncate text-sm font-bold text-slate-950">{{ item.title }}</div>
                      <div class="mt-1 text-xs text-slate-500">{{ item.course_code }} · {{ itemTypeLabel(item.type) }} · {{ item.status }}</div>
                    </div>
                    <div class="text-right">
                      <div class="text-lg font-bold text-slate-950">{{ item.score ?? '-' }}/{{ item.max_score }}</div>
                      <div class="text-xs text-slate-500">{{ item.percent === null ? '-' : pct(item.percent) }}</div>
                    </div>
                  </div>
                  <div v-if="item.feedback" class="mt-3 rounded bg-white px-3 py-2 text-xs text-slate-600">{{ item.feedback }}</div>
                  <div class="mt-3 flex flex-wrap gap-2">
                    <a v-if="item.href" :href="item.href" class="inline-flex items-center gap-1 rounded-md bg-slate-950 px-3 py-2 text-xs font-bold text-white">
                      {{ itemActionLabel(item) }} <ExternalLink class="h-3.5 w-3.5" />
                    </a>
                    <a v-if="item.result_href" :href="item.result_href" class="inline-flex items-center gap-1 rounded-md border border-slate-300 bg-white px-3 py-2 text-xs font-semibold text-slate-700">
                      Xem kết quả
                    </a>
                    <a v-if="item.course_href" :href="item.course_href" class="inline-flex items-center gap-1 rounded-md border border-slate-300 bg-white px-3 py-2 text-xs font-semibold text-slate-700">
                      Khóa học
                    </a>
                  </div>
                </div>
              </template>
              <div v-if="!drillRows.length" class="rounded-md border border-slate-200 bg-slate-50 p-4 text-sm text-slate-500 md:col-span-2">Chưa có dữ liệu cho mục này.</div>
            </div>
          </article>
        </section>

        <section class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
          <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
              <h2 class="text-sm font-bold text-slate-950">Forecast & Intervention</h2>
              <p class="mt-1 text-xs text-slate-500">Expected GPA, Graduation GPA và gợi ý học tập.</p>
            </div>
            <a href="/career/digital-twin" class="inline-flex items-center gap-2 rounded-md border border-blue-200 bg-blue-50 px-3 py-2 text-sm font-semibold text-blue-800 hover:bg-blue-100">
              Digital Twin
              <ChevronRight class="h-4 w-4" />
            </a>
          </div>
          <div class="mt-4 grid gap-4 lg:grid-cols-[260px_1fr]">
            <div class="rounded-md bg-slate-950 p-4 text-white">
              <div class="text-xs font-semibold uppercase text-cyan-200">Graduation GPA</div>
              <div class="mt-2 text-4xl font-bold">{{ formatGpa(forecast.graduation_gpa) }}</div>
              <div class="mt-2 text-sm text-slate-300">Expected {{ formatGpa(forecast.expected_gpa) }} · {{ forecast.confidence || '-' }}</div>
            </div>
            <div class="grid gap-3 md:grid-cols-3">
              <div v-for="recommendation in forecast.recommendations || []" :key="recommendation" class="rounded-md border border-slate-200 bg-slate-50 p-3 text-sm leading-5 text-slate-700">
                {{ recommendation }}
              </div>
              <div v-if="!(forecast.recommendations || []).length" class="rounded-md border border-slate-200 bg-slate-50 p-3 text-sm text-slate-500">Chưa có gợi ý.</div>
            </div>
          </div>
        </section>
      </template>
    </section>
  </EraLmsLayout>
</template>
