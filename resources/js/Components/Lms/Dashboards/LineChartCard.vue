<script setup>
import { computed } from 'vue'
import EmptyMetricState from './EmptyMetricState.vue'

const props = defineProps({
  chart: { type: Object, required: true },
})

const metricKeys = computed(() => props.chart.metrics || [])
const rows = computed(() => Array.isArray(props.chart.data) ? props.chart.data : [])
const points = computed(() => {
  const values = rows.value.map((row) => Number(row[metricKeys.value[0]]) || 0)
  const max = Math.max(1, ...values)
  return values.map((value, index) => {
    const x = rows.value.length <= 1 ? 0 : (index / (rows.value.length - 1)) * 100
    const y = 100 - ((value / max) * 90)
    return `${x},${y}`
  }).join(' ')
})
</script>

<template>
  <section class="rounded-md border bg-white p-4">
    <div class="mb-3 flex items-center justify-between gap-2">
      <h3 class="truncate text-sm font-semibold text-slate-900">{{ chart.title }}</h3>
      <span class="text-xs text-slate-500">{{ rows.length }} điểm</span>
    </div>
    <EmptyMetricState v-if="!rows.length" />
    <svg v-else viewBox="0 0 100 100" preserveAspectRatio="none" class="h-56 w-full overflow-visible rounded-md border border-slate-100 bg-slate-50">
      <polyline :points="points" fill="none" stroke="#0891b2" stroke-width="2.5" vector-effect="non-scaling-stroke" />
    </svg>
  </section>
</template>
