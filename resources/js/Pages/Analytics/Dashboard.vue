<script setup>
import EraLmsLayout from '@/Layouts/EraLmsLayout.vue'
import { computed, onMounted, reactive, ref } from 'vue'

const props = defineProps({
  sessionUser: { type: Object, default: null },
  apiHeaders: { type: Object, default: () => ({}) },
})
defineEmits(['logout'])

const audience = ref('executive')
const loading = ref(true)
const data = ref(null)
const error = ref(null)
const isReportPage = window.location.pathname.startsWith('/reports')

const activeReportTab = ref('overview')
const filters = reactive({
  courseId: '',
  from: new Date(new Date().setDate(new Date().getDate() - 30)).toISOString().split('T')[0],
  to: new Date().toISOString().split('T')[0],
})

const reportModules = reactive({
  overviewLoaded: false,
  metrics: {
    loading: false,
    loaded: false,
    items: [],
    error: null,
  },
  risks: {
    loading: false,
    loaded: false,
    items: [],
    error: null,
  },
  alerts: {
    loading: false,
    loaded: false,
    items: [],
    error: null,
  },
})

const audiences = [
  ['executive', 'BGH'],
  ['training', 'Đào tạo'],
  ['faculty', 'Khoa'],
  ['teacher', 'Giảng viên'],
  ['student', 'Sinh viên'],
]

const reportTabs = [
  ['overview', 'Tổng quan'],
  ['metrics', 'Chỉ số'],
  ['risks', 'Rủi ro'],
  ['alerts', 'Cảnh báo'],
]

const reportFilterLabel = computed(() => {
  const parts = ['Phân tích']
  if (filters.courseId) parts.push(`Mã khóa học: ${filters.courseId}`)
  if (filters.from) parts.push(`Từ ngày: ${filters.from}`)
  if (filters.to) parts.push(`Đến ngày: ${filters.to}`)
  return parts.join(' · ')
})

const kpiLabels = {
  learners: 'Người học',
  avg_progress: 'Tiến độ trung bình',
  avg_grade: 'Điểm trung bình',
  avg_engagement: 'Mức tương tác',
  avg_risk_score: 'Điểm rủi ro trung bình',
  open_alerts: 'Cảnh báo mở',
}

function kpiLabel(key) {
  return kpiLabels[key] || key.replaceAll('_', ' ')
}

const maxTrend = computed(() => Math.max(...(data.value?.progress_trend || []).map((item) => item.progress), 100))

const maxRiskValue = computed(() => Math.max(1, ...Object.values(data.value?.risk_distribution || {}).map((value) => Number(value) || 0)))

const riskBars = computed(() => {
  const distribution = data.value?.risk_distribution || {}
  const maxValue = maxRiskValue.value
  return Object.entries(distribution).map(([key, value]) => ({ key, value, width: `${Math.min(100, ((Number(value) || 0) / maxValue) * 100)}%` }))
})

const maxGradeValue = computed(() => Math.max(1, ...Object.values(data.value?.grade_distribution || {}).map((value) => Number(value) || 0)))

const gradeBars = computed(() => Object.entries(data.value?.grade_distribution || {}).map(([label, value]) => ({
  label,
  value,
  width: `${Math.min(100, ((Number(value) || 0) / maxGradeValue.value) * 100)}%`,
})))

const riskLabels = {
  low: 'Thấp',
  medium: 'Trung bình',
  high: 'Cao',
  critical: 'Rất cao',
}

const moduleLabels = {
  metrics: 'chỉ số',
  risks: 'hồ sơ rủi ro',
  alerts: 'cảnh báo',
}

const severityLabels = {
  low: 'Thấp',
  medium: 'Trung bình',
  high: 'Cao',
  critical: 'Rất cao',
}

function riskLabel(key) {
  return riskLabels[key] || key
}

function moduleLabel(route) {
  return moduleLabels[route] || route
}

function severityLabel(level) {
  return severityLabels[level] || level
}

function formatKpiRateLabel(label) {
  return `${label} điểm`
}

const metricsSummary = computed(() => {
  if (!data.value?.kpis) return { learners: 0, alerts: 0 }
  return {
    learners: data.value.kpis.learners || 0,
    alerts: data.value.kpis.open_alerts || 0,
  }
})

function buildParams(extras = {}) {
  const params = new URLSearchParams()
  if (filters.courseId) params.set('course_id', filters.courseId)
  if (filters.from) params.set('from', filters.from)
  if (filters.to) params.set('to', filters.to)
  Object.entries(extras).forEach(([key, value]) => {
    if (value !== null && value !== undefined && value !== '') {
      params.set(key, String(value))
    }
  })
  return params.toString()
}

async function requestDashboard() {
  const q = buildParams({ audience: audience.value, per_page: isReportPage ? 20 : undefined })
  const response = await fetch(`/api/v1/analytics/dashboard${q ? `?${q}` : ''}`, { headers: props.apiHeaders })
  const payload = await response.json().catch(() => ({}))
  if (!response.ok) throw new Error(payload.message || 'Không tải được bảng điều khiển phân tích.')
  return payload
}

async function requestModule(route) {
  const moduleState = reportModules[route]
  if (!moduleState || moduleState.loading) return

  moduleState.loading = true
  moduleState.error = null
  moduleState.loaded = false
  moduleState.items = []

  try {
    const q = buildParams({ per_page: 20 })
    const response = await fetch(`/api/v1/analytics/${route}${q ? `?${q}` : ''}`, { headers: props.apiHeaders })
    const payload = await response.json().catch(() => ({}))
    if (!response.ok) throw new Error(payload.message || `Không tải dữ liệu ${moduleLabel(route)}.`)
    moduleState.items = Array.isArray(payload?.data) ? payload.data : []
  } catch (err) {
    moduleState.error = err.message
  } finally {
    moduleState.loading = false
    moduleState.loaded = true
  }
}

async function loadOverview() {
  loading.value = true
  error.value = null

  try {
    data.value = await requestDashboard()
  } catch (err) {
    error.value = err.message
  } finally {
    loading.value = false
    reportModules.overviewLoaded = true
  }
}

async function loadSection(key) {
  if (!isReportPage) return

  const mapping = { metrics: 'metrics', risks: 'risks', alerts: 'alerts' }
  const route = mapping[key]
  if (!route) return

  await requestModule(route)
}

async function refreshCurrent() {
  reportModules.metrics.loaded = false
  reportModules.risks.loaded = false
  reportModules.alerts.loaded = false
  reportModules.metrics.items = []
  reportModules.risks.items = []
  reportModules.alerts.items = []

  await loadOverview()

  if (isReportPage && activeReportTab.value !== 'overview') {
    await loadSection(activeReportTab.value)
  }
}

function switchReportTab(tab) {
  activeReportTab.value = tab

  if (tab === 'metrics' && !reportModules.metrics.loaded) {
    loadSection('metrics')
  }

  if (tab === 'risks' && !reportModules.risks.loaded) {
    loadSection('risks')
  }

  if (tab === 'alerts' && !reportModules.alerts.loaded) {
    loadSection('alerts')
  }
}

function setAudience(value) {
  audience.value = value
  if (isReportPage) {
    refreshCurrent()
  } else {
    loadOverview()
  }
}

onMounted(() => {
  loadOverview()
})
</script>

<template>
  <EraLmsLayout :session-user="sessionUser" @logout="$emit('logout')">
    <template #breadcrumb>Phân tích học tập</template>

    <div class="mb-4 flex flex-wrap items-center gap-3">
      <div>
        <h1 class="text-xl font-semibold">
          {{ isReportPage ? 'Báo cáo phân tích' : 'Nền tảng phân tích học tập' }}
        </h1>
        <p v-if="isReportPage" class="mt-1 text-xs text-slate-500">{{ reportFilterLabel }}</p>
      </div>
      <div class="ml-auto flex rounded-md border bg-white p-1 text-sm">
        <button
          v-for="[key, label] in audiences"
          :key="key"
          class="rounded px-3 py-1.5"
          :class="audience === key ? 'bg-blue-900 text-white' : 'text-slate-600 hover:bg-slate-100'"
          @click="setAudience(key)"
        >
          {{ label }}
        </button>
      </div>
    </div>

    <div v-if="isReportPage" class="mb-4 grid gap-3 rounded-md border bg-white p-4 md:grid-cols-4">
      <label class="text-xs">
        <span class="mb-1 block text-slate-600">Mã khóa học</span>
        <input
          v-model="filters.courseId"
          type="number"
          min="1"
          placeholder="Nhập mã khóa học"
          class="w-full rounded-md border px-3 py-2 text-sm"
        />
      </label>
      <label class="text-xs">
        <span class="mb-1 block text-slate-600">Từ ngày</span>
        <input v-model="filters.from" type="date" class="w-full rounded-md border px-3 py-2 text-sm" />
      </label>
      <label class="text-xs">
        <span class="mb-1 block text-slate-600">Đến ngày</span>
        <input v-model="filters.to" type="date" class="w-full rounded-md border px-3 py-2 text-sm" />
      </label>
      <div class="flex items-end gap-2">
        <button class="rounded-md bg-blue-900 px-3 py-2 text-sm font-semibold text-white" @click="refreshCurrent">Áp dụng bộ lọc</button>
      </div>

      <div class="md:col-span-4 mt-1 flex flex-wrap gap-2 text-sm">
        <button
          v-for="[tabKey, tabLabel] in reportTabs"
          :key="tabKey"
          class="rounded-md border px-3 py-1.5"
          :class="activeReportTab === tabKey ? 'bg-blue-900 text-white border-blue-900' : 'text-slate-600 hover:bg-slate-100'"
          @click="switchReportTab(tabKey)"
        >
          {{ tabLabel }}
        </button>
      </div>
      <div class="md:col-span-4 grid gap-2 rounded-md border p-3 md:grid-cols-3 text-sm">
        <div class="rounded bg-slate-50 p-3"><span class="text-slate-500">Người học:</span> {{ metricsSummary.learners }}</div>
        <div class="rounded bg-slate-50 p-3"><span class="text-slate-500">Cảnh báo mở:</span> {{ metricsSummary.alerts }}</div>
        <button class="rounded-md bg-slate-900 px-3 py-2 font-semibold text-white hover:bg-slate-800" @click="refreshCurrent">Làm mới</button>
      </div>
    </div>

    <div v-if="error" class="rounded-md border border-red-200 bg-red-50 p-3 text-sm text-red-700">{{ error }}</div>
    <div v-else-if="loading" class="rounded-md border bg-white p-4 text-sm text-slate-500">Đang tải dữ liệu phân tích...</div>

    <template v-else-if="data">
      <div class="grid grid-cols-1 gap-3 md:grid-cols-3 xl:grid-cols-6">
        <div v-for="(value, key) in data.kpis" :key="key" class="rounded-lg border bg-white p-4">
          <div class="text-xs uppercase text-slate-500">{{ kpiLabel(key) }}</div>
          <div class="mt-2 text-2xl font-semibold">{{ value }}</div>
        </div>
      </div>

      <div class="mt-4 grid grid-cols-1 gap-4 xl:grid-cols-3">
        <section class="rounded-lg border bg-white p-4 xl:col-span-2">
          <div class="mb-3 text-sm font-semibold">Xu hướng tiến độ</div>
          <div class="flex h-56 items-end gap-1 border-b border-l px-2 pb-2">
            <div
              v-for="item in data.progress_trend.slice(-30)"
              :key="item.date"
              class="min-w-2 flex-1 rounded-t bg-blue-700"
              :style="{ height: `${Math.max(4, (item.progress / maxTrend) * 100)}%` }"
              :title="`${item.date}: ${item.progress}%`"
            />
          </div>
        </section>

        <section class="rounded-lg border bg-white p-4">
          <div class="mb-3 text-sm font-semibold">Mức độ rủi ro</div>
          <div class="space-y-3">
            <div v-for="bar in riskBars" :key="bar.key">
              <div class="mb-1 flex text-xs"><span>{{ riskLabel(bar.key) }}</span><span class="ml-auto">{{ bar.value }}</span></div>
              <div class="h-2 rounded bg-slate-100"><div class="h-2 rounded bg-amber-500" :style="{ width: bar.width }" /></div>
            </div>
          </div>
        </section>
      </div>

      <div class="mt-4 grid grid-cols-1 gap-4 lg:grid-cols-3">
        <section class="rounded-lg border bg-white p-4">
          <div class="mb-3 text-sm font-semibold">Phân bố điểm</div>
          <div class="space-y-3">
            <div v-for="bar in gradeBars" :key="bar.label">
              <div class="mb-1 flex text-xs"><span>{{ formatKpiRateLabel(bar.label) }}</span><span class="ml-auto">{{ bar.value }}</span></div>
              <div class="h-3 rounded bg-slate-100"><div class="h-3 rounded bg-emerald-600" :style="{ width: bar.width }" /></div>
            </div>
          </div>
        </section>

        <section class="rounded-lg border bg-white p-4">
          <div class="mb-3 text-sm font-semibold">Bản đồ tương tác</div>
          <div class="grid grid-cols-7 gap-2">
            <div
              v-for="cell in data.heatmap"
              :key="cell.weekday"
              class="flex aspect-square items-center justify-center rounded text-xs font-semibold text-white"
              :style="{ backgroundColor: `rgba(15, 76, 129, ${Math.min(1, Math.max(0.18, cell.intensity / 12))})` }"
              :title="`Thứ ${cell.weekday + 1}: ${cell.intensity}`"
            >
              {{ cell.weekday + 1 }}
            </div>
          </div>
        </section>

        <section class="rounded-lg border bg-white p-4">
          <div class="mb-3 text-sm font-semibold">Phễu hoàn thành</div>
          <div class="space-y-3">
            <div v-for="stage in data.completion_funnel" :key="stage.stage">
              <div class="mb-1 flex text-xs"><span>{{ stage.stage }}</span><span class="ml-auto">{{ stage.rate }}%</span></div>
              <div class="h-3 rounded bg-slate-100"><div class="h-3 rounded bg-sky-600" :style="{ width: `${stage.rate}%` }" /></div>
            </div>
          </div>
        </section>
      </div>

      <section v-if="isReportPage && activeReportTab === 'metrics'" class="mt-4 rounded-lg border bg-white">
        <div class="border-b p-4 text-sm font-semibold">Chỉ số học tập</div>
        <div class="overflow-auto">
          <div v-if="reportModules.metrics.loading" class="p-4 text-sm text-slate-500">Đang tải chỉ số...</div>
          <div v-else-if="reportModules.metrics.error" class="p-4 text-sm text-red-600">{{ reportModules.metrics.error }}</div>
          <div v-else class="divide-y text-sm">
            <div
              v-for="item in reportModules.metrics.items"
              :key="`${item.id}-${item.user_id}-${item.metric_date}`"
              class="grid gap-2 p-4 md:grid-cols-[120px_120px_120px_1fr]"
            >
              <div>{{ item.metric_date }}</div>
              <div>{{ item.user_id }}</div>
              <div>Đăng nhập: {{ item.login_frequency }}</div>
              <div>Bài kiểm tra: {{ item.quiz_score }} | Video: {{ item.video_completion }} | Bài tập: {{ item.assignment_completion }}</div>
            </div>
            <div v-if="!reportModules.metrics.items.length" class="p-4 text-slate-500">Không có dữ liệu chỉ số theo bộ lọc.</div>
          </div>
        </div>
      </section>

      <section v-if="isReportPage && activeReportTab === 'risks'" class="mt-4 rounded-lg border bg-white">
        <div class="border-b p-4 text-sm font-semibold">Hồ sơ rủi ro</div>
        <div class="overflow-auto">
          <div v-if="reportModules.risks.loading" class="p-4 text-sm text-slate-500">Đang tải hồ sơ rủi ro...</div>
          <div v-else-if="reportModules.risks.error" class="p-4 text-sm text-red-600">{{ reportModules.risks.error }}</div>
          <div v-else class="divide-y text-sm">
            <div v-for="item in reportModules.risks.items" :key="item.id" class="grid gap-2 p-4 md:grid-cols-[140px_110px_1fr]">
              <div>Học viên: {{ item.user_id }}</div>
              <div>Mức: {{ riskLabel(item.risk_level) }}</div>
              <div>Điểm: {{ item.risk_score }} · Tính toán lúc: {{ item.last_calculated_at || '-' }}</div>
            </div>
            <div v-if="!reportModules.risks.items.length" class="p-4 text-slate-500">Không có hồ sơ rủi ro.</div>
          </div>
        </div>
      </section>

      <section v-if="isReportPage && activeReportTab === 'alerts'" class="mt-4 rounded-lg border bg-white">
        <div class="border-b p-4 text-sm font-semibold">Cảnh báo sớm</div>
        <div class="divide-y text-sm">
          <div v-if="reportModules.alerts.loading" class="p-4 text-sm text-slate-500">Đang tải cảnh báo...</div>
          <div v-else-if="reportModules.alerts.error" class="p-4 text-sm text-red-600">{{ reportModules.alerts.error }}</div>
          <template v-else>
            <div v-for="alert in reportModules.alerts.items" :key="alert.id" class="grid gap-2 p-4 md:grid-cols-[1fr_120px_160px]">
              <div>
                <div class="font-medium">{{ alert.message }}</div>
                <div class="text-xs text-slate-500">{{ alert.learner?.full_name }} · {{ alert.course?.title || 'Toàn khóa' }}</div>
              </div>
              <div class="text-amber-700">{{ severityLabel(alert.severity) }}</div>
              <div class="text-xs text-slate-500">{{ alert.recommended_actions?.join(', ') }}</div>
            </div>
            <div v-if="!reportModules.alerts.items.length" class="p-4 text-slate-500">Không có cảnh báo mở.</div>
          </template>
        </div>
      </section>

      <section v-if="isReportPage && activeReportTab === 'overview'" class="mt-4 rounded-lg border bg-white">
        <div class="border-b p-4 text-sm font-semibold">Cảnh báo sớm</div>
        <div class="divide-y text-sm">
          <div v-for="alert in data.alerts" :key="alert.id" class="grid gap-2 p-4 md:grid-cols-[1fr_120px_160px]">
            <div>
              <div class="font-medium">{{ alert.message }}</div>
              <div class="text-xs text-slate-500">{{ alert.learner?.full_name }} · {{ alert.course?.title || 'Toàn khóa' }}</div>
            </div>
            <div class="text-amber-700">{{ severityLabel(alert.severity) }}</div>
            <div class="text-xs text-slate-500">{{ alert.recommended_actions?.join(', ') }}</div>
          </div>
          <div v-if="!data.alerts.length" class="p-4 text-slate-500">Không có cảnh báo mở.</div>
        </div>
      </section>
    </template>
  </EraLmsLayout>
</template>
