<script setup>
import EraLmsLayout from '@/Layouts/EraLmsLayout.vue'
import { computed, onMounted, reactive, ref } from 'vue'
import { Download, RefreshCw } from '@lucide/vue'
import AlertListCard from './AlertListCard.vue'
import BarChartCard from './BarChartCard.vue'
import BenchmarkBadge from './BenchmarkBadge.vue'
import DashboardFilterBar from './DashboardFilterBar.vue'
import DataQualityBadge from './DataQualityBadge.vue'
import DrilldownDrawer from './DrilldownDrawer.vue'
import EmptyMetricState from './EmptyMetricState.vue'
import ForecastCard from './ForecastCard.vue'
import FunnelChartCard from './FunnelChartCard.vue'
import HeatmapCard from './HeatmapCard.vue'
import KpiCard from './KpiCard.vue'
import LineChartCard from './LineChartCard.vue'
import LoadingSkeletonGrid from './LoadingSkeletonGrid.vue'
import PieChartCard from './PieChartCard.vue'
import RadarChartCard from './RadarChartCard.vue'

const props = defineProps({
  dashboardKey: { type: String, required: true },
  title: { type: String, required: true },
  endpoint: { type: String, required: true },
  sessionUser: { type: Object, default: null },
  apiHeaders: { type: Object, default: () => ({}) },
})

const emit = defineEmits(['logout'])
const loading = ref(true)
const exporting = ref(false)
const error = ref('')
const dashboard = ref(null)
const drawerOpen = ref(false)
const drawerData = ref({ columns: [], rows: [] })
const exportNotice = ref('')

const filters = reactive({
  from: new Date(new Date().setDate(new Date().getDate() - 89)).toISOString().split('T')[0],
  to: new Date().toISOString().split('T')[0],
  academic_year_id: '',
  semester_id: '',
  campus_id: '',
  faculty_id: '',
  program_id: '',
  class_id: '',
  course_id: '',
  user_id: '',
  teacher_id: '',
})

const chartComponents = {
  line: LineChartCard,
  timeline: LineChartCard,
  bar_by_faculty: BarChartCard,
  pie: PieChartCard,
  funnel: FunnelChartCard,
  heatmap: HeatmapCard,
  radar: RadarChartCard,
}

const tableCharts = computed(() => (dashboard.value?.charts || []).filter((chart) => chart.type === 'table'))
const visualCharts = computed(() => (dashboard.value?.charts || []).filter((chart) => chart.type !== 'table'))

function params(extra = {}) {
  const q = new URLSearchParams()
  Object.entries({ ...filters, ...extra }).forEach(([key, value]) => {
    if (value !== null && value !== undefined && value !== '') q.set(key, String(value))
  })
  return q.toString()
}

function normalizePayload(payload) {
  return payload?.success && payload?.data ? payload.data : payload
}

async function load() {
  loading.value = true
  error.value = ''
  try {
    const q = params()
    const response = await fetch(`${props.endpoint}${q ? `?${q}` : ''}`, { headers: props.apiHeaders })
    const payload = normalizePayload(await response.json().catch(() => ({})))
    if (!response.ok) throw new Error(payload.message || 'Không tải được dashboard.')
    dashboard.value = payload
  } catch (err) {
    error.value = err.message
  } finally {
    loading.value = false
  }
}

async function rebuild() {
  loading.value = true
  error.value = ''
  try {
    const response = await fetch('/api/v1/analytics/rebuild-snapshots', {
      method: 'POST',
      headers: props.apiHeaders,
      body: JSON.stringify({ scope: 'tenant' }),
    })
    const payload = await response.json().catch(() => ({}))
    if (!response.ok) throw new Error(payload.message || 'Không rebuild được snapshot.')
    await load()
  } catch (err) {
    error.value = err.message
    loading.value = false
  }
}

async function openDrilldown(kpi) {
  const q = params({ dashboard_key: props.dashboardKey, metric_key: kpi.drilldown_metric_key || kpi.key })
  const response = await fetch(`/api/v1/analytics/drilldown?${q}`, { headers: props.apiHeaders })
  const payload = normalizePayload(await response.json().catch(() => ({})))
  drawerData.value = payload
  drawerOpen.value = true
}

async function exportDashboard(format = 'json') {
  exporting.value = true
  exportNotice.value = ''
  try {
    const response = await fetch('/api/v1/dashboards/export', {
      method: 'POST',
      headers: props.apiHeaders,
      body: JSON.stringify({ dashboard_key: props.dashboardKey, format, filters: { ...filters } }),
    })
    const payload = normalizePayload(await response.json().catch(() => ({})))
    if (!response.ok) throw new Error(payload.message || 'Không export được dashboard.')
    exportNotice.value = payload.filename || 'Đã tạo export snapshot.'
  } catch (err) {
    exportNotice.value = err.message
  } finally {
    exporting.value = false
  }
}
</script>

<template>
  <EraLmsLayout :session-user="sessionUser" @logout="emit('logout')">
    <template #breadcrumb>Dashboard dữ liệu / {{ title }}</template>

    <div class="space-y-4">
      <header class="flex flex-wrap items-start gap-3">
        <div>
          <h1 class="text-xl font-semibold text-slate-950">{{ dashboard?.title || title }}</h1>
          <div class="mt-1 flex flex-wrap items-center gap-2 text-xs text-slate-500">
            <span>{{ dashboardKey }}</span>
            <DataQualityBadge v-if="dashboard?.data_quality" :quality="dashboard.data_quality" />
          </div>
        </div>
        <div class="ml-auto flex flex-wrap gap-2">
          <button class="inline-flex h-9 items-center gap-2 rounded-md border border-slate-300 bg-white px-3 text-sm font-semibold text-slate-700" :disabled="loading" @click="load">
            <RefreshCw class="h-4 w-4" />
            Làm mới
          </button>
          <button class="inline-flex h-9 items-center gap-2 rounded-md bg-cyan-700 px-3 text-sm font-semibold text-white" :disabled="exporting" @click="exportDashboard('excel')">
            <Download class="h-4 w-4" />
            Export
          </button>
        </div>
      </header>

      <DashboardFilterBar :model-value="filters" :loading="loading" @update:modelValue="Object.assign(filters, $event)" @apply="load" @rebuild="rebuild" />

      <div v-if="exportNotice" class="rounded-md border border-cyan-200 bg-cyan-50 px-3 py-2 text-sm text-cyan-800">{{ exportNotice }}</div>
      <div v-if="error" class="rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">{{ error }}</div>

      <LoadingSkeletonGrid v-if="loading" />

      <template v-else-if="dashboard">
        <div v-if="dashboard.data_quality?.status === 'missing'" class="rounded-md border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">
          {{ dashboard.data_quality.message }}
        </div>

        <section class="grid gap-3 md:grid-cols-3 xl:grid-cols-6">
          <KpiCard v-for="kpi in dashboard.kpis" :key="kpi.key" :item="kpi" @drilldown="openDrilldown" />
        </section>

        <section class="grid gap-4 xl:grid-cols-2">
          <component
            :is="chartComponents[chart.type] || LineChartCard"
            v-for="chart in visualCharts"
            :key="chart.key"
            :chart="chart"
          />
        </section>

        <section v-for="chart in tableCharts" :key="chart.key" class="rounded-md border bg-white">
          <div class="border-b px-4 py-3 text-sm font-semibold">{{ chart.title }}</div>
          <div class="overflow-auto">
            <table v-if="chart.data?.length" class="min-w-full text-sm">
              <tbody class="divide-y">
                <tr v-for="row in chart.data" :key="row.id">
                  <td class="px-4 py-3 font-semibold text-slate-800">{{ row.dimensions?.label || row.dimensions?.course_title || row.dimensions?.class_name || row.id }}</td>
                  <td class="px-4 py-3 text-slate-600">{{ row.data_quality }}</td>
                  <td class="px-4 py-3 text-xs text-slate-500">{{ JSON.stringify(row.metrics) }}</td>
                </tr>
              </tbody>
            </table>
            <div v-else class="p-4"><EmptyMetricState /></div>
          </div>
        </section>

        <section class="grid gap-4 xl:grid-cols-[1.2fr_0.8fr]">
          <AlertListCard :alerts="dashboard.alerts || []" />
          <ForecastCard :forecasts="dashboard.forecasts || []" />
        </section>

        <section class="rounded-md border bg-white">
          <div class="flex items-center justify-between border-b px-4 py-3">
            <div class="text-sm font-semibold">Drill-down table</div>
            <button class="rounded-md border px-3 py-1.5 text-sm font-semibold" @click="drawerOpen = true">Mở drawer</button>
          </div>
          <div class="overflow-auto">
            <table class="min-w-full text-sm">
              <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500">
                <tr>
                  <th v-for="column in dashboard.drilldown?.columns || []" :key="column" class="px-3 py-2">{{ column }}</th>
                </tr>
              </thead>
              <tbody class="divide-y">
                <tr v-for="(row, index) in (dashboard.drilldown?.rows || []).slice(0, 8)" :key="index">
                  <td v-for="column in dashboard.drilldown.columns" :key="column" class="px-3 py-2">{{ row[column] ?? '-' }}</td>
                </tr>
                <tr v-if="!dashboard.drilldown?.rows?.length">
                  <td class="px-3 py-4 text-slate-500" :colspan="dashboard.drilldown?.columns?.length || 1">Chưa có dữ liệu đủ để tính</td>
                </tr>
              </tbody>
            </table>
          </div>
        </section>

        <section class="flex flex-wrap gap-2">
          <BenchmarkBadge v-for="benchmark in dashboard.benchmarks || []" :key="benchmark.id" :benchmark="benchmark" />
        </section>
      </template>
    </div>

    <DrilldownDrawer :open="drawerOpen" :drilldown="drawerData.rows ? drawerData : dashboard?.drilldown" @close="drawerOpen = false" />
  </EraLmsLayout>
</template>
