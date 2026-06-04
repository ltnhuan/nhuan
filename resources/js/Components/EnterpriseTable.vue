<script setup>
defineProps({ title: String, columns: Array, rows: Array, filters: { type: Array, default: () => [] } })
</script>

<template>
  <section class="rounded-lg border bg-white shadow-sm">
    <div class="flex items-center gap-2 border-b p-3">
      <h2 class="text-sm font-semibold">{{ title }}</h2>
      <input class="ml-auto h-8 w-64 rounded-md border px-2 text-xs" placeholder="Tìm nhanh" />
      <select v-for="filter in filters" :key="filter" class="h-8 rounded-md border px-2 text-xs"><option>{{ filter }}</option></select>
      <button class="h-8 rounded-md bg-blue-900 px-3 text-xs text-white">Tạo mới</button>
    </div>
    <table class="w-full text-left text-xs">
      <thead class="bg-slate-100 text-slate-600"><tr><th class="w-8 p-2"><input type="checkbox" /></th><th v-for="column in columns" :key="column" class="p-2">{{ column }}</th><th class="p-2">Thao tác</th></tr></thead>
      <tbody>
        <tr v-for="row in rows" :key="row.code || row.title" class="border-t hover:bg-slate-50"><td class="p-2"><input type="checkbox" /></td><td v-for="column in columns" :key="column" class="p-2">{{ row[column] || row[column.toLowerCase()] }}</td><td class="p-2"><button class="text-blue-700">Chi tiết</button></td></tr>
      </tbody>
    </table>
    <div class="flex items-center justify-between border-t p-3 text-xs text-slate-500"><span>Hiển thị dữ liệu phân trang</span><span>1 / 1</span></div>
  </section>
</template>
