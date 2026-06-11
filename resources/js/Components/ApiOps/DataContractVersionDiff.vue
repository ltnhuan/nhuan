<script setup>
import { computed } from 'vue'

const props = defineProps({
  left: { type: Object, default: () => ({}) },
  right: { type: Object, default: () => ({}) },
})

const rows = computed(() => {
  const keys = Array.from(new Set([...Object.keys(props.left || {}), ...Object.keys(props.right || {})]))
  return keys.map((key) => ({
    key,
    left: JSON.stringify(props.left?.[key] ?? null),
    right: JSON.stringify(props.right?.[key] ?? null),
    changed: JSON.stringify(props.left?.[key] ?? null) !== JSON.stringify(props.right?.[key] ?? null),
  }))
})
</script>

<template>
  <section class="rounded-md border border-slate-200 bg-white p-4">
    <h2 class="text-sm font-semibold">So sánh phiên bản contract</h2>
    <div class="mt-3 overflow-x-auto">
      <table class="min-w-full text-left text-xs">
        <thead class="bg-slate-50 uppercase text-slate-500">
          <tr>
            <th class="px-3 py-2">Trường</th>
            <th class="px-3 py-2">Hiện tại</th>
            <th class="px-3 py-2">Sắp áp dụng</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
          <tr v-for="row in rows" :key="row.key" :class="{ 'bg-amber-50': row.changed }">
            <td class="px-3 py-2 font-semibold">{{ row.key }}</td>
            <td class="px-3 py-2 font-mono">{{ row.left }}</td>
            <td class="px-3 py-2 font-mono">{{ row.right }}</td>
          </tr>
        </tbody>
      </table>
    </div>
  </section>
</template>
