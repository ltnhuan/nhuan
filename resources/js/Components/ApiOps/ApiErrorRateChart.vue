<script setup>
import { computed } from 'vue'

const props = defineProps({
  items: { type: [Array, Object], default: () => [] },
})

const rows = computed(() => {
  const source = Array.isArray(props.items)
    ? props.items.map((item) => ({ label: item.system || item.status || item.label, total: Number(item.total || item.value || 0) }))
    : Object.entries(props.items || {}).map(([label, total]) => ({ label, total: Number(total || 0) }))
  const fallback = [{ label: 'Chưa có lỗi', total: 0 }]
  const list = source.length ? source : fallback
  const max = Math.max(...list.map((item) => item.total), 1)
  return list.map((item) => ({ ...item, width: item.total === 0 ? 4 : Math.round((item.total / max) * 100) }))
})
</script>

<template>
  <section class="rounded-md border border-slate-200 bg-white p-4">
    <h2 class="text-sm font-semibold text-slate-900">Phân bố lỗi</h2>
    <div class="mt-4 space-y-3">
      <div v-for="item in rows" :key="item.label" class="grid grid-cols-[120px_1fr_48px] items-center gap-3 text-xs">
        <span class="truncate text-slate-500">{{ item.label }}</span>
        <div class="h-3 rounded bg-slate-100">
          <div class="h-3 rounded bg-rose-600" :style="{ width: `${item.width}%` }"></div>
        </div>
        <span class="text-right font-mono text-slate-700">{{ item.total }}</span>
      </div>
    </div>
  </section>
</template>
