<script setup>
defineProps({
  benchmark: { type: Object, required: true },
})

const classes = {
  below: 'border-red-200 bg-red-50 text-red-700',
  normal: 'border-slate-200 bg-slate-50 text-slate-700',
  good: 'border-emerald-200 bg-emerald-50 text-emerald-700',
  excellent: 'border-cyan-200 bg-cyan-50 text-cyan-700',
}
</script>

<template>
  <span class="inline-flex items-center gap-2 rounded-md border px-2 py-1 text-xs font-semibold" :class="classes[benchmark.status] || classes.normal">
    <span>{{ benchmark.benchmark_key }}</span>
    <span>{{ benchmark.current_value }}</span>
  </span>
</template>
