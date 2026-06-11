<script setup>
import { computed } from 'vue'

const props = defineProps({
  items: { type: Array, default: () => [] },
})

const rows = computed(() => {
  const source = props.items.length ? props.items : [
    { day: 'D-6', latency_ms: 120 },
    { day: 'D-5', latency_ms: 180 },
    { day: 'D-4', latency_ms: 260 },
    { day: 'D-3', latency_ms: 210 },
    { day: 'D-2', latency_ms: 360 },
    { day: 'D-1', latency_ms: 300 },
    { day: 'Hôm nay', latency_ms: 240 },
  ]
  const max = Math.max(...source.map((item) => Number(item.latency_ms || 0)), 1)
  return source.map((item) => ({ ...item, width: Math.max(6, Math.round((Number(item.latency_ms || 0) / max) * 100)) }))
})
</script>

<template>
  <section class="rounded-md border border-slate-200 bg-white p-4">
    <h2 class="text-sm font-semibold text-slate-900">Xu hướng độ trễ</h2>
    <div class="mt-4 space-y-3">
      <div v-for="item in rows" :key="item.day" class="grid grid-cols-[80px_1fr_64px] items-center gap-3 text-xs">
        <span class="truncate text-slate-500">{{ item.day }}</span>
        <div class="h-3 rounded bg-slate-100">
          <div class="h-3 rounded bg-cyan-700" :style="{ width: `${item.width}%` }"></div>
        </div>
        <span class="text-right font-mono text-slate-700">{{ Math.round(item.latency_ms || 0) }}ms</span>
      </div>
    </div>
  </section>
</template>
