<script setup>
import { onMounted, ref } from 'vue'
import EraLmsLayout from '@/Layouts/EraLmsLayout.vue'

const props = defineProps({
  apiHeaders: { type: Object, required: true },
  sessionUser: { type: Object, default: null },
})

const jobs = ref([])
const loading = ref(true)
const error = ref('')

async function load() {
  loading.value = true
  error.value = ''
  try {
    const response = await fetch('/api/v1/integrations/sync-jobs?per_page=100', { headers: props.apiHeaders })
    const data = await response.json()
    if (!response.ok) throw new Error(data.message || 'Không tải được công việc đồng bộ.')
    jobs.value = data.data || []
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
    <template #breadcrumb>Trung tâm tích hợp / Công việc đồng bộ</template>
    <section class="mx-auto max-w-7xl px-4 py-5 sm:px-6">
      <div class="flex flex-wrap items-center justify-between gap-3">
        <h1 class="text-lg font-semibold">Công việc đồng bộ</h1>
        <button class="rounded-md bg-slate-950 px-3 py-2 text-sm text-white" :disabled="loading" @click="load">Tải lại</button>
      </div>
      <div v-if="error" class="mt-4 rounded-md border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">{{ error }}</div>
      <div class="mt-5 overflow-hidden border bg-white">
        <table class="w-full text-left text-sm">
          <thead class="bg-slate-50 text-xs uppercase text-slate-500"><tr><th class="px-4 py-3">Loại công việc</th><th class="px-4 py-3">Đối tượng</th><th class="px-4 py-3">Trạng thái</th><th class="px-4 py-3">Tổng</th><th class="px-4 py-3">Đã xử lý</th><th class="px-4 py-3">Bắt đầu</th></tr></thead>
          <tbody class="divide-y">
            <tr v-if="loading"><td class="px-4 py-6 text-slate-500" colspan="6">Đang tải dữ liệu...</td></tr>
            <tr v-else-if="!jobs.length"><td class="px-4 py-6 text-slate-500" colspan="6">Chưa có công việc đồng bộ.</td></tr>
            <tr v-for="job in jobs" v-else :key="job.id">
              <td class="px-4 py-3">{{ job.job_type }}</td><td class="px-4 py-3">{{ job.entity_type }}</td><td class="px-4 py-3">{{ job.status }}</td><td class="px-4 py-3">{{ job.total_records ?? 0 }}</td><td class="px-4 py-3">{{ job.processed_records ?? 0 }}</td><td class="px-4 py-3">{{ job.started_at || '-' }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>
  </EraLmsLayout>
</template>
