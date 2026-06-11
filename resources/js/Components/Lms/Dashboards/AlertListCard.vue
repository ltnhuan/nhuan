<script setup>
defineProps({
  alerts: { type: Array, default: () => [] },
})

const severityClass = {
  critical: 'border-red-200 bg-red-50 text-red-700',
  high: 'border-orange-200 bg-orange-50 text-orange-700',
  medium: 'border-amber-200 bg-amber-50 text-amber-700',
  low: 'border-slate-200 bg-slate-50 text-slate-700',
}
</script>

<template>
  <section class="rounded-md border bg-white">
    <div class="border-b px-4 py-3 text-sm font-semibold">Cảnh báo và hành động</div>
    <div class="divide-y">
      <div v-for="alert in alerts" :key="`${alert.source || 'alert'}-${alert.id}`" class="grid gap-2 p-4 text-sm md:grid-cols-[1fr_120px_220px]">
        <div>
          <div class="font-semibold text-slate-900">{{ alert.title || alert.message }}</div>
          <div class="mt-1 text-xs text-slate-500">{{ alert.message }}</div>
        </div>
        <div><span class="rounded-md border px-2 py-1 text-xs font-semibold" :class="severityClass[alert.severity] || severityClass.low">{{ alert.severity }}</span></div>
        <div class="text-xs text-slate-600">{{ alert.recommended_action || alert.recommended_actions?.join?.(', ') || '-' }}</div>
      </div>
      <div v-if="!alerts.length" class="p-4 text-sm text-slate-500">Không có cảnh báo đang mở.</div>
    </div>
  </section>
</template>
