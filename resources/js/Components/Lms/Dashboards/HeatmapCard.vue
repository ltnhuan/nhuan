<script setup>
import { computed } from 'vue'
import EmptyMetricState from './EmptyMetricState.vue'

const props = defineProps({
  chart: { type: Object, required: true },
})

const rows = computed(() => Array.isArray(props.chart.data) ? props.chart.data : [])
</script>

<template>
  <section class="rounded-md border bg-white p-4">
    <h3 class="mb-3 truncate text-sm font-semibold text-slate-900">{{ chart.title }}</h3>
    <EmptyMetricState v-if="!rows.length" />
    <div v-else class="grid grid-cols-2 gap-2 md:grid-cols-3 xl:grid-cols-5">
      <button
        v-for="row in rows"
        :key="row.class_id || row.label"
        class="rounded-md border p-3 text-left"
        :style="{ backgroundColor: `rgba(8, 145, 178, ${Math.max(0.08, Math.min(0.35, (Number(row.progress || 0) / 100) * 0.35))})` }"
      >
        <div class="truncate text-xs font-semibold text-slate-800">{{ row.label }}</div>
        <div class="mt-2 text-lg font-bold">{{ row.progress ?? 'N/A' }}%</div>
        <div class="mt-1 text-xs text-slate-600">CC: {{ row.attendance ?? 'N/A' }}% · Risk: {{ row.risk ?? 'N/A' }}</div>
      </button>
    </div>
  </section>
</template>
