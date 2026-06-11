<script setup>
import { computed, ref } from 'vue'

const props = defineProps({
  title: String,
  columns: Array,
  rows: { type: Array, default: () => [] },
  filters: { type: Array, default: () => [] },
  loading: { type: Boolean, default: false },
  rowHeight: { type: Number, default: 44 },
  maxBodyHeight: { type: Number, default: 520 },
})

const scrollTop = ref(0)
const buffer = 8
const visibleCount = computed(() => Math.ceil(props.maxBodyHeight / props.rowHeight) + buffer * 2)
const startIndex = computed(() => Math.max(0, Math.floor(scrollTop.value / props.rowHeight) - buffer))
const endIndex = computed(() => Math.min(props.rows.length, startIndex.value + visibleCount.value))
const visibleRows = computed(() => props.rows.slice(startIndex.value, endIndex.value))
const topSpacerHeight = computed(() => startIndex.value * props.rowHeight)
const bottomSpacerHeight = computed(() => Math.max(0, (props.rows.length - endIndex.value) * props.rowHeight))

function onScroll(event) {
  scrollTop.value = event.target.scrollTop
}

function rowPayload(row) {
  return JSON.stringify(row ?? {})
}

function rowId(row) {
  return row?.id || row?.code || row?.key || row?.action_key || row?.title || ''
}

function rowTitle(row) {
  return row?.title || row?.name || row?.full_name || row?.email || row?.code || rowId(row)
}

function columnKey(column) {
  return typeof column === 'object' ? column.key : column
}

function columnLabel(column) {
  return typeof column === 'object' ? column.label : column
}
</script>

<template>
  <section class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
    <div class="border-b border-slate-200 bg-white p-4">
      <div class="flex flex-wrap items-center gap-3">
        <div>
          <h2 class="text-base font-semibold text-slate-950">{{ title }}</h2>
          <p class="mt-1 text-xs text-slate-500">Dữ liệu lấy từ API/module hiện hành.</p>
        </div>
        <input class="ml-auto h-10 w-72 rounded-md border border-slate-300 bg-slate-50 px-3 text-sm outline-none focus:border-blue-500 focus:bg-white focus:ring-4 focus:ring-blue-100" placeholder="Tìm nhanh" />
        <select v-for="filter in filters" :key="filter" class="h-10 rounded-md border border-slate-300 bg-white px-3 text-sm text-slate-700"><option>{{ filter }}</option></select>
        <button class="h-10 rounded-md bg-slate-950 px-4 text-sm font-medium text-white hover:bg-slate-800">Tạo mới</button>
      </div>
    </div>
    <div class="overflow-auto" :style="{ maxHeight: `${maxBodyHeight}px` }" @scroll="onScroll">
      <table class="w-full text-left text-sm">
        <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
          <tr>
            <th class="w-10 px-4 py-3"><input type="checkbox" /></th>
            <th v-for="column in columns" :key="columnKey(column)" class="whitespace-nowrap px-4 py-3">{{ columnLabel(column) }}</th>
            <th class="whitespace-nowrap px-4 py-3">Thao tác</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
          <tr v-if="loading" v-for="index in 8" :key="`loading-${index}`" :style="{ height: `${rowHeight}px` }">
            <td class="px-4 py-3" :colspan="(columns?.length || 0) + 2">
              <div class="h-4 w-full animate-pulse rounded bg-slate-100"></div>
            </td>
          </tr>
          <tr v-if="!loading && topSpacerHeight > 0" aria-hidden="true">
            <td :colspan="(columns?.length || 0) + 2" :style="{ height: `${topSpacerHeight}px`, padding: 0 }"></td>
          </tr>
          <tr
            v-for="row in visibleRows"
            v-if="!loading"
            :key="row.code || row.title"
            class="cursor-pointer hover:bg-slate-50"
            :style="{ height: `${rowHeight}px` }"
            :data-row-id="rowId(row)"
            :data-row-title="rowTitle(row)"
            :data-row-payload="rowPayload(row)"
          >
            <td class="px-4 py-3"><input type="checkbox" /></td>
            <td v-for="column in columns" :key="columnKey(column)" class="whitespace-nowrap px-4 py-3 text-slate-700">{{ row[columnKey(column)] || row[String(columnKey(column)).toLowerCase()] }}</td>
            <td class="whitespace-nowrap px-4 py-3"><button class="font-medium text-blue-700 hover:text-blue-900">Chi tiết</button></td>
          </tr>
          <tr v-if="!loading && bottomSpacerHeight > 0" aria-hidden="true">
            <td :colspan="(columns?.length || 0) + 2" :style="{ height: `${bottomSpacerHeight}px`, padding: 0 }"></td>
          </tr>
        </tbody>
      </table>
    </div>
    <div class="flex items-center justify-between border-t border-slate-200 bg-slate-50 px-4 py-3 text-xs text-slate-500">
      <span>Hiển thị {{ rows?.length || 0 }} bản ghi</span>
      <span>Trang 1 / 1</span>
    </div>
  </section>
</template>
