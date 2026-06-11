<script setup>
const props = defineProps({
  quality: { type: Object, default: () => ({ status: 'missing', message: 'Chưa có dữ liệu đủ để tính' }) },
})

const classes = {
  good: 'border-emerald-200 bg-emerald-50 text-emerald-700',
  warning: 'border-amber-200 bg-amber-50 text-amber-700',
  missing: 'border-slate-200 bg-slate-50 text-slate-600',
  stale: 'border-orange-200 bg-orange-50 text-orange-700',
}

const labels = {
  good: 'Good',
  warning: 'Warning',
  missing: 'Missing',
  stale: 'Stale',
}
</script>

<template>
  <span
    class="inline-flex items-center rounded-md border px-2 py-1 text-xs font-semibold"
    :class="classes[props.quality.status] || classes.missing"
    :title="props.quality.message"
  >
    {{ labels[props.quality.status] || labels.missing }}
  </span>
</template>
