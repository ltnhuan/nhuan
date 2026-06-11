<script setup>
defineProps({
  job: { type: Object, default: () => ({}) },
})

function pct(value, total) {
  return Math.round((Number(value || 0) / Math.max(Number(total || 0), 1)) * 100)
}
</script>

<template>
  <div class="space-y-1">
    <div class="flex justify-between text-xs text-slate-500">
      <span>{{ job.success_count || 0 }} thành công</span>
      <span>{{ job.failed_count || 0 }} lỗi</span>
    </div>
    <div class="flex h-2 overflow-hidden rounded bg-slate-100">
      <div class="bg-emerald-500" :style="{ width: `${pct(job.success_count, job.total_count)}%` }"></div>
      <div class="bg-rose-500" :style="{ width: `${pct(job.failed_count, job.total_count)}%` }"></div>
    </div>
  </div>
</template>
