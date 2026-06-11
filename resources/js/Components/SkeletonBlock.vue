<script setup>
defineProps({
  lines: { type: Number, default: 1 },
  height: { type: String, default: '1rem' },
})
</script>

<template>
  <div class="space-y-2" aria-hidden="true">
    <div
      v-for="line in lines"
      :key="line"
      class="animate-pulse rounded bg-slate-100"
      :style="{ height, width: line === lines && lines > 1 ? '72%' : '100%' }"
    ></div>
  </div>
</template>
