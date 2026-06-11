<script setup>
import { computed } from 'vue'
import EmptyMetricState from './EmptyMetricState.vue'

const props = defineProps({
  chart: { type: Object, required: true },
})

const rows = computed(() => (Array.isArray(props.chart.data) ? props.chart.data : []).filter((row) => row.value !== null && row.value !== undefined))
const maxValue = computed(() => Math.max(1, ...rows.value.map((row) => Number(row.value) || 0)))
</script>

<template>
  <section class="rounded-md border bg-white p-4">
    <h3 class="mb-3 truncate text-sm font-semibold text-slate-900">{{ chart.title }}</h3>
    <EmptyMetricState v-if="!rows.length" />
    <div v-else class="space-y-3">
      <div v-for="row in rows" :key="row.key" class="text-xs">
        <div class="mb-1 flex">
          <span class="truncate text-slate-600">{{ row.label }}</span>
          <span class="ml-auto font-semibold">{{ row.value }}</span>
        </div>
        <div class="h-4 rounded bg-slate-100">
          <div class="h-4 rounded bg-cyan-600" :style="{ width: `${Math.min(100, ((Number(row.value) || 0) / maxValue) * 100)}%` }" />
        </div>
      </div>
    </div>
  </section>
</template>
