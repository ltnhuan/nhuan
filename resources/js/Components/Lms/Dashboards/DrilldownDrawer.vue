<script setup>
defineProps({
  open: { type: Boolean, default: false },
  drilldown: { type: Object, default: () => ({ columns: [], rows: [] }) },
})

defineEmits(['close'])
</script>

<template>
  <div v-if="open" class="fixed inset-0 z-50 flex justify-end bg-slate-950/50 backdrop-blur-sm" @click.self="$emit('close')">
    <section class="h-full w-full max-w-5xl overflow-hidden bg-white shadow-2xl">
      <header class="flex items-center justify-between border-b px-5 py-4">
        <div>
          <div class="text-xs font-semibold uppercase text-cyan-700">Drill-down</div>
          <h2 class="text-lg font-bold text-slate-950">{{ drilldown.metric_key }}</h2>
        </div>
        <button class="rounded-md border px-3 py-2 text-sm font-semibold" @click="$emit('close')">Đóng</button>
      </header>
      <div class="h-[calc(100vh-73px)] overflow-auto p-5">
        <table class="min-w-full text-sm">
          <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500">
            <tr>
              <th v-for="column in drilldown.columns" :key="column" class="px-3 py-2">{{ column }}</th>
            </tr>
          </thead>
          <tbody class="divide-y">
            <tr v-for="(row, index) in drilldown.rows" :key="index">
              <td v-for="column in drilldown.columns" :key="column" class="px-3 py-2 align-top text-slate-700">
                <span v-if="typeof row[column] !== 'object'">{{ row[column] ?? '-' }}</span>
                <pre v-else class="whitespace-pre-wrap text-xs">{{ JSON.stringify(row[column], null, 2) }}</pre>
              </td>
            </tr>
            <tr v-if="!drilldown.rows?.length">
              <td :colspan="drilldown.columns?.length || 1" class="px-3 py-6 text-center text-slate-500">Chưa có dữ liệu đủ để tính</td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>
  </div>
</template>
