<script setup>
import { computed } from 'vue'
import EmptyMetricState from './EmptyMetricState.vue'

const props = defineProps({
  chart: { type: Object, required: true },
})

const rows = computed(() => (Array.isArray(props.chart.data) ? props.chart.data : []).filter((row) => row.value !== null && row.value !== undefined))
const total = computed(() => Math.max(1, rows.value.reduce((sum, row) => sum + Number(row.value || 0), 0)))
const colors = ['bg-emerald-500', 'bg-sky-500', 'bg-amber-500', 'bg-red-500', 'bg-slate-500']
</script>

<template>
  <section class="rounded-md border bg-white p-4">
    <h3 class="mb-3 truncate text-sm font-semibold text-slate-900">{{ chart.title }}</h3>
    <EmptyMetricState v-if="!rows.length" />
    <div v-else class="space-y-3">
      <div class="flex h-4 overflow-hidden rounded bg-slate-100">
        <span
          v-for="(row, index) in rows"
          :key="row.key"
          class="h-4"
          :class="colors[index % colors.length]"
          :style="{ width: `${Math.max(2, (Number(row.value || 0) / total) * 100)}%` }"
        />
      </div>
      <div class="grid gap-2 text-xs">
        <div v-for="(row, index) in rows" :key="row.key" class="flex items-center gap-2">
          <span class="h-2.5 w-2.5 rounded-sm" :class="colors[index % colors.length]" />
          <span class="truncate text-slate-600">{{ row.label }}</span>
          <span class="ml-auto font-semibold">{{ row.value }}</span>
        </div>
      </div>
    </div>
  </section>
</template>
