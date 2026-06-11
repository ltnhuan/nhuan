<script setup>
defineProps({
  label: { type: String, required: true },
  value: { type: [String, Number], default: 0 },
  meta: { type: String, default: '' },
  tone: { type: String, default: 'slate' },
})

const tones = {
  slate: 'border-slate-200 bg-white',
  green: 'border-emerald-200 bg-emerald-50',
  amber: 'border-amber-200 bg-amber-50',
  red: 'border-rose-200 bg-rose-50',
  blue: 'border-sky-200 bg-sky-50',
}
</script>

<template>
  <div class="min-w-0 rounded-md border p-4" :class="tones[tone] || tones.slate">
    <div class="truncate text-xs font-semibold uppercase text-slate-500">{{ label }}</div>
    <div class="mt-2 text-2xl font-bold tabular-nums text-slate-950">{{ value }}</div>
    <div v-if="meta" class="mt-1 truncate text-xs text-slate-600">{{ meta }}</div>
  </div>
</template>
