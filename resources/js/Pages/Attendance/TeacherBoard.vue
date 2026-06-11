<script setup>
import { onMounted, ref } from 'vue'
import EraLmsLayout from '@/Layouts/EraLmsLayout.vue'

const props = defineProps({
  apiHeaders: { type: Object, required: true },
  sessionUser: { type: Object, default: null },
})

const rows = ref([])
const loading = ref(true)
const error = ref('')

async function load() {
  loading.value = true
  error.value = ''
  try {
    const response = await fetch('/api/v1/attendance-sessions?per_page=100', { headers: props.apiHeaders })
    const data = await response.json()
    if (!response.ok) throw new Error(data.message || 'Không tải được phiên điểm danh.')
    rows.value = data.data || []
  } catch (exception) {
    error.value = exception.message
  } finally {
    loading.value = false
  }
}

onMounted(load)
</script>

<template>
  <EraLmsLayout :session-user="sessionUser">
    <template #breadcrumb>Điểm danh online / Bảng giáo viên</template>
    <section class="border-b bg-white">
      <div class="mx-auto max-w-7xl px-4 py-5 sm:px-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
          <div><h1 class="text-lg font-semibold">Bảng điểm danh giáo viên</h1><p class="mt-1 text-sm text-slate-600">Theo dõi phiên điểm danh, trạng thái khóa và số bản ghi đã nhận.</p></div>
          <button class="rounded-md bg-slate-950 px-3 py-2 text-sm text-white" :disabled="loading" @click="load">Tải lại</button>
        </div>
      </div>
    </section>
    <section class="mx-auto max-w-7xl px-4 py-5 sm:px-6">
      <div v-if="error" class="mb-4 rounded-md border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">{{ error }}</div>
      <div class="overflow-hidden border bg-white">
        <table class="w-full text-left text-sm">
          <thead class="bg-slate-50 text-xs uppercase text-slate-500"><tr><th class="px-4 py-3">Phiên</th><th class="px-4 py-3">Khóa học</th><th class="px-4 py-3">Phương thức</th><th class="px-4 py-3">Mở lúc</th><th class="px-4 py-3">Trạng thái</th><th class="px-4 py-3">Số bản ghi</th></tr></thead>
          <tbody class="divide-y">
            <tr v-if="loading"><td class="px-4 py-6 text-slate-500" colspan="6">Đang tải dữ liệu...</td></tr>
            <tr v-else-if="!rows.length"><td class="px-4 py-6 text-slate-500" colspan="6">Chưa có phiên điểm danh.</td></tr>
            <tr v-for="row in rows" v-else :key="row.id"><td class="px-4 py-3 font-medium">{{ row.title }}</td><td class="px-4 py-3">{{ row.course_id }}</td><td class="px-4 py-3">{{ row.method }}</td><td class="px-4 py-3">{{ row.open_at }}</td><td class="px-4 py-3">{{ row.status }}</td><td class="px-4 py-3">{{ row.records_count ?? 0 }}</td></tr>
          </tbody>
        </table>
      </div>
    </section>
  </EraLmsLayout>
</template>
