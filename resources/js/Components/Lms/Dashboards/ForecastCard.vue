<script setup>
defineProps({
  forecasts: { type: Array, default: () => [] },
})
</script>

<template>
  <section class="rounded-md border bg-white">
    <div class="border-b px-4 py-3 text-sm font-semibold">Dự báo</div>
    <div class="grid gap-3 p-4 lg:grid-cols-2">
      <div v-for="forecast in forecasts" :key="forecast.id" class="rounded-md border bg-slate-50 p-3">
        <div class="text-xs font-semibold uppercase text-slate-500">{{ forecast.forecast_key }}</div>
        <div class="mt-2 text-2xl font-bold text-slate-950">{{ forecast.predicted_value }}</div>
        <div class="mt-1 text-xs text-slate-500">Confidence {{ Math.round((forecast.confidence || 0) * 100) }}% · {{ forecast.horizon }}</div>
        <p class="mt-2 text-sm text-slate-700">{{ forecast.explanation }}</p>
      </div>
      <div v-if="!forecasts.length" class="text-sm text-slate-500">Chưa có forecast cho scope hiện tại.</div>
    </div>
  </section>
</template>
