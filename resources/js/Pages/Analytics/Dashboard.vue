<script setup>
import EraLmsLayout from '@/Layouts/EraLmsLayout.vue'
import { computed, onMounted, ref } from 'vue'

const props = defineProps({
  sessionUser: { type: Object, default: null },
  apiHeaders: { type: Object, default: () => ({}) },
})
defineEmits(['logout'])

const audience = ref('executive')
const loading = ref(true)
const data = ref(null)
const error = ref(null)
const audiences = [
  ['executive', 'BGH'],
  ['training', 'Đào tạo'],
  ['faculty', 'Khoa'],
  ['teacher', 'Giảng viên'],
  ['student', 'Sinh viên'],
]

const maxTrend = computed(() => Math.max(...(data.value?.progress_trend || []).map((item) => item.progress), 100))
const gradeBars = computed(() => Object.entries(data.value?.grade_distribution || {}).map(([label, value]) => ({ label, value })))

async function load() {
  loading.value = true
  error.value = null
  try {
    const response = await fetch(`/api/v1/analytics/dashboard?audience=${audience.value}`, { headers: props.apiHeaders })
    const payload = await response.json()
    if (!response.ok) throw new Error(payload.message || 'Không tải được dashboard analytics.')
    data.value = payload
  } catch (err) {
    error.value = err.message
  } finally {
    loading.value = false
  }
}

onMounted(load)
</script>

<template>
  <EraLmsLayout :session-user="sessionUser" @logout="$emit('logout')">
    <template #breadcrumb>Learning Analytics</template>

    <div class="mb-4 flex flex-wrap items-center gap-3">
      <h1 class="text-xl font-semibold">Learning Analytics Platform</h1>
      <div class="ml-auto flex rounded-md border bg-white p-1 text-sm">
        <button
          v-for="[key, label] in audiences"
          :key="key"
          class="rounded px-3 py-1.5"
          :class="audience === key ? 'bg-blue-900 text-white' : 'text-slate-600 hover:bg-slate-100'"
          @click="audience = key; load()"
        >
          {{ label }}
        </button>
      </div>
    </div>

    <div v-if="error" class="rounded-md border border-red-200 bg-red-50 p-3 text-sm text-red-700">{{ error }}</div>
    <div v-else-if="loading" class="rounded-md border bg-white p-4 text-sm text-slate-500">Đang tải dữ liệu analytics...</div>

    <template v-else-if="data">
      <div class="grid grid-cols-1 gap-3 md:grid-cols-3 xl:grid-cols-6">
        <div v-for="(value, key) in data.kpis" :key="key" class="rounded-lg border bg-white p-4">
          <div class="text-xs uppercase text-slate-500">{{ key.replaceAll('_', ' ') }}</div>
          <div class="mt-2 text-2xl font-semibold">{{ value }}</div>
        </div>
      </div>

      <div class="mt-4 grid grid-cols-1 gap-4 xl:grid-cols-3">
        <section class="rounded-lg border bg-white p-4 xl:col-span-2">
          <div class="mb-3 text-sm font-semibold">Progress Trend</div>
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
          <div class="mb-3 text-sm font-semibold">Risk Level</div>
          <div class="space-y-3">
            <div v-for="(value, key) in data.risk_distribution" :key="key">
              <div class="mb-1 flex text-xs"><span class="capitalize">{{ key }}</span><span class="ml-auto">{{ value }}</span></div>
              <div class="h-2 rounded bg-slate-100"><div class="h-2 rounded bg-amber-500" :style="{ width: `${Math.min(100, value)}%` }" /></div>
            </div>
          </div>
        </section>
      </div>

      <div class="mt-4 grid grid-cols-1 gap-4 lg:grid-cols-3">
        <section class="rounded-lg border bg-white p-4">
          <div class="mb-3 text-sm font-semibold">Grade Distribution</div>
          <div class="space-y-3">
            <div v-for="bar in gradeBars" :key="bar.label">
              <div class="mb-1 flex text-xs"><span>{{ bar.label }}</span><span class="ml-auto">{{ bar.value }}</span></div>
              <div class="h-3 rounded bg-slate-100"><div class="h-3 rounded bg-emerald-600" :style="{ width: `${Math.min(100, bar.value)}%` }" /></div>
            </div>
          </div>
        </section>

        <section class="rounded-lg border bg-white p-4">
          <div class="mb-3 text-sm font-semibold">Engagement Heatmap</div>
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
          <div class="mb-3 text-sm font-semibold">Completion Funnel</div>
          <div class="space-y-3">
            <div v-for="stage in data.completion_funnel" :key="stage.stage">
              <div class="mb-1 flex text-xs"><span>{{ stage.stage }}</span><span class="ml-auto">{{ stage.rate }}%</span></div>
              <div class="h-3 rounded bg-slate-100"><div class="h-3 rounded bg-sky-600" :style="{ width: `${stage.rate}%` }" /></div>
            </div>
          </div>
        </section>
      </div>

      <section class="mt-4 rounded-lg border bg-white">
        <div class="border-b p-4 text-sm font-semibold">Early Warning Alerts</div>
        <div class="divide-y text-sm">
          <div v-for="alert in data.alerts" :key="alert.id" class="grid gap-2 p-4 md:grid-cols-[1fr_120px_160px]">
            <div>
              <div class="font-medium">{{ alert.message }}</div>
              <div class="text-xs text-slate-500">{{ alert.learner?.full_name }} · {{ alert.course?.title || 'Toàn khóa' }}</div>
            </div>
            <div class="capitalize text-amber-700">{{ alert.severity }}</div>
            <div class="text-xs text-slate-500">{{ alert.recommended_actions?.join(', ') }}</div>
          </div>
          <div v-if="!data.alerts.length" class="p-4 text-slate-500">Không có cảnh báo mở.</div>
        </div>
      </section>
    </template>
  </EraLmsLayout>
</template>
