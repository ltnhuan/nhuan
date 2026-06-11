<script setup>
import { ChevronRight } from '@lucide/vue'
import DataQualityBadge from './DataQualityBadge.vue'

defineProps({
  item: { type: Object, required: true },
})

defineEmits(['drilldown'])

function formatValue(value) {
  if (value === null || value === undefined || value === '') return 'N/A'
  return new Intl.NumberFormat('vi-VN', { maximumFractionDigits: 2 }).format(Number(value))
}
</script>

<template>
  <button class="min-h-28 rounded-md border bg-white p-4 text-left shadow-sm transition hover:border-cyan-300 hover:shadow" @click="$emit('drilldown', item)">
    <div class="flex items-start gap-2">
      <div class="min-w-0">
        <div class="truncate text-xs font-semibold uppercase text-slate-500">{{ item.label }}</div>
        <div class="mt-2 text-2xl font-bold text-slate-950">{{ formatValue(item.value) }}<span v-if="item.unit" class="ml-1 text-sm font-semibold text-slate-500">{{ item.unit }}</span></div>
      </div>
      <ChevronRight class="ml-auto h-4 w-4 shrink-0 text-slate-400" />
    </div>
    <div class="mt-3">
      <DataQualityBadge :quality="{ status: item.quality, message: item.quality === 'missing' ? 'Chưa có dữ liệu đủ để tính' : 'Metric từ summary table' }" />
    </div>
  </button>
</template>
