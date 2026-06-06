<script setup>
import { computed, ref } from 'vue'
import ActionBar from './ActionBar.vue'

const props = defineProps({
  columns: { type: Array, default: () => [] },
  rows: { type: Array, default: () => [] },
  rowActions: { type: Array, default: () => [] },
  bulkActions: { type: Array, default: () => [] },
  loadingMap: { type: Object, default: () => ({}) },
})

const emit = defineEmits(['run'])
const search = ref('')
const selected = ref([])

const filteredRows = computed(() => {
  if (!search.value) return props.rows
  const keyword = search.value.toLowerCase()
  return props.rows.filter((row) => JSON.stringify(row).toLowerCase().includes(keyword))
})

function rowPayload(row) {
  return JSON.stringify(row ?? {})
}

function rowId(row) {
  return row?.id || row?.code || row?.key || row?.action_key || row?.title || ''
}

function rowTitle(row) {
  return row?.title || row?.name || row?.full_name || row?.label || row?.action_key || rowId(row)
}
</script>

<template>
  <section class="rounded-lg border border-slate-200 bg-white">
    <div class="flex flex-wrap items-center gap-3 border-b border-slate-200 p-3">
      <input v-model="search" class="h-9 w-72 rounded-md border border-slate-300 px-3 text-sm" placeholder="Tìm kiếm" />
      <ActionBar v-if="bulkActions.length" :actions="bulkActions" :loading-map="loadingMap" @run="(action) => $emit('run', action, selected)" />
    </div>
    <div class="overflow-auto">
      <table class="w-full text-left text-sm">
        <thead class="bg-slate-50 text-xs uppercase text-slate-500">
          <tr>
            <th class="w-10 px-3 py-2"><input type="checkbox" /></th>
            <th v-for="column in columns" :key="column.key" class="px-3 py-2">{{ column.label }}</th>
            <th class="px-3 py-2">Thao tác</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
          <tr
            v-for="row in filteredRows"
            :key="row.id || row.action_key"
            class="cursor-pointer hover:bg-slate-50"
            :data-row-id="rowId(row)"
            :data-row-title="rowTitle(row)"
            :data-row-payload="rowPayload(row)"
          >
            <td class="px-3 py-2"><input v-model="selected" type="checkbox" :value="row" /></td>
            <td v-for="column in columns" :key="column.key" class="px-3 py-2">{{ row[column.key] }}</td>
            <td class="px-3 py-2">
              <ActionBar :actions="rowActions" :loading-map="loadingMap" @run="(action) => $emit('run', action, row)" />
            </td>
          </tr>
        </tbody>
      </table>
    </div>
    <div class="border-t border-slate-200 px-3 py-2 text-xs text-slate-500">Hiển thị {{ filteredRows.length }} bản ghi</div>
  </section>
</template>
