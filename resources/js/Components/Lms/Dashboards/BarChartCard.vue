<script setup>
import { computed } from 'vue'
import EmptyMetricState from './EmptyMetricState.vue'

const props = defineProps({
  chart: { type: Object, required: true },
})

const rows = computed(() => Array.isArray(props.chart.data) ? props.chart.data : [])
const maxValue = computed(() => Math.max(1, ...rows.value.map((row) => Number(row.value) || 0)))
</script>

<template>
  <section class="rounded-md border bg-white p-4">
    <h3 class="mb-3 truncate text-sm font-semibold text-slate-900">{{ chart.title }}</h3>
    <EmptyMetricState v-if="!rows.length" />
    <div v-else class="space-y-2">
      <div v-for="row in rows" :key="row.label" class="grid grid-cols-[120px_1fr_56px] items-center gap-2 text-xs">
        <span class="truncate text-slate-600">{{ row.label }}</span>
        <span class="h-3 rounded bg-slate-100"><span class="block h-3 rounded bg-cyan-600" :style="{ width: `${Math.min(100, ((Number(row.value) || 0) / maxValue) * 100)}%` }" /></span>
        <span class="text-right font-semibold text-slate-700">{{ row.value }}</span>
      </div>
    </div>
  </section>
</template>
