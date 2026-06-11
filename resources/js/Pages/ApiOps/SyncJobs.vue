<script setup>
import { inject, onMounted, reactive, ref } from 'vue'
import { Activity, Eye, Play, RotateCcw, X } from '@lucide/vue'
import ApiOpsShell from '@/Components/ApiOps/ApiOpsShell.vue'
import ApiHealthBadge from '@/Components/ApiOps/ApiHealthBadge.vue'
import PayloadViewer from '@/Components/ApiOps/PayloadViewer.vue'
import SyncJobProgress from '@/Components/ApiOps/SyncJobProgress.vue'

const props = defineProps({
  apiHeaders: { type: Object, required: true },
  sessionUser: { type: Object, default: null },
})

const systems = ref([])
const jobs = ref([])
const notice = ref('')
const selected = ref(null)
const form = reactive({ system_id: '', job_type: 'full_sync', entity_type: 'student' })
const navigateTo = inject('navigateTo', (url) => { window.location.href = url })

async function load() {
  const [optionsResponse, jobsResponse] = await Promise.all([
    fetch('/api/v1/api-ops/options?include=systems', { headers: props.apiHeaders }),
    fetch('/api/v1/api-ops/sync-jobs?per_page=50', { headers: props.apiHeaders }),
  ])
  systems.value = (await optionsResponse.json()).data?.systems || []
  jobs.value = (await jobsResponse.json()).data?.data || []
  form.system_id ||= systems.value[0]?.id || ''
}

function monitorJob(job) {
  const suffix = job.system_id ? `&system_id=${encodeURIComponent(job.system_id)}` : ''
  navigateTo(`/admin/api-ops/health?source=sync-job${suffix}`)
}

async function createJob() {
  const response = await fetch('/api/v1/api-ops/sync-jobs', { method: 'POST', headers: props.apiHeaders, body: JSON.stringify(form) })
  await response.json()
  notice.value = 'Đã tạo sync job.'
  await load()
}

async function jobAction(job, action) {
  const response = await fetch(`/api/v1/api-ops/sync-jobs/${job.id}/${action}`, { method: 'POST', headers: props.apiHeaders })
  await response.json()
  notice.value = action === 'run'
    ? 'Đã chạy sync job.'
    : action === 'retry'
      ? 'Đã chạy lại các dòng lỗi.'
      : 'Đã hủy sync job.'
  await load()
}

onMounted(load)
</script>

<template>
  <ApiOpsShell :session-user="sessionUser" title="Công việc sync" subtitle="Tạo job đồng bộ full/incremental/push/pull, chạy queue, retry dòng lỗi và theo dõi tiến độ.">
    <div v-if="notice" class="rounded-md border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-700">{{ notice }}</div>
    <section class="rounded-md border border-slate-200 bg-white p-4">
      <div class="flex flex-wrap items-end gap-3">
        <label><span class="text-xs font-semibold uppercase text-slate-500">Hệ thống</span><select v-model="form.system_id" class="mt-1 h-10 rounded-md border px-2 text-sm"><option v-for="system in systems" :key="system.id" :value="system.id">{{ system.code }}</option></select></label>
        <label><span class="text-xs font-semibold uppercase text-slate-500">Loại job</span><select v-model="form.job_type" class="mt-1 h-10 rounded-md border px-2 text-sm"><option>full_sync</option><option>incremental_sync</option><option>manual_retry</option><option>push</option><option>pull</option></select></label>
        <label><span class="text-xs font-semibold uppercase text-slate-500">Loại dữ liệu</span><input v-model="form.entity_type" class="mt-1 h-10 rounded-md border px-2 text-sm" /></label>
        <button class="rounded-md bg-slate-950 px-3 py-2 text-sm font-semibold text-white" @click="createJob">Tạo job</button>
      </div>
    </section>
    <section class="overflow-x-auto rounded-md border border-slate-200 bg-white">
      <table class="min-w-full text-left text-sm">
        <thead class="bg-slate-50 text-xs uppercase text-slate-500"><tr><th class="px-3 py-2">Job</th><th class="px-3 py-2">Loại</th><th class="px-3 py-2">Dữ liệu</th><th class="px-3 py-2">Trạng thái</th><th class="px-3 py-2">Tiến độ</th><th class="px-3 py-2">Thao tác</th></tr></thead>
        <tbody class="divide-y divide-slate-100">
          <tr v-for="job in jobs" :key="job.id">
            <td class="px-3 py-2 font-mono text-xs">#{{ job.id }}</td>
            <td class="px-3 py-2">{{ job.job_type }}</td>
            <td class="px-3 py-2">{{ job.entity_type }}</td>
            <td class="px-3 py-2"><ApiHealthBadge :status="job.status" /></td>
            <td class="px-3 py-2 min-w-52"><SyncJobProgress :job="job" /></td>
            <td class="px-3 py-2">
              <div class="flex flex-wrap gap-2">
                <button class="inline-flex items-center gap-1 rounded-md border px-2 py-1 text-xs font-semibold" @click="selected = job"><Eye class="h-3.5 w-3.5" />Mở chi tiết</button>
                <button class="inline-flex items-center gap-1 rounded-md border px-2 py-1 text-xs font-semibold" @click="monitorJob(job)"><Activity class="h-3.5 w-3.5" />Theo dõi</button>
                <button class="inline-flex items-center gap-1 rounded-md border px-2 py-1 text-xs font-semibold" @click="jobAction(job, 'run')"><Play class="h-3.5 w-3.5" />Chạy</button>
                <button class="inline-flex items-center gap-1 rounded-md border px-2 py-1 text-xs font-semibold" @click="jobAction(job, 'retry')"><RotateCcw class="h-3.5 w-3.5" />Chạy lại</button>
                <button class="inline-flex items-center gap-1 rounded-md border px-2 py-1 text-xs font-semibold" @click="jobAction(job, 'cancel')"><X class="h-3.5 w-3.5" />Hủy</button>
              </div>
            </td>
          </tr>
          <tr v-if="!jobs.length">
            <td colspan="6" class="px-3 py-8 text-center text-sm text-slate-500">Chưa có sync job.</td>
          </tr>
        </tbody>
      </table>
    </section>
    <div v-if="selected" class="fixed inset-0 z-50 flex justify-end bg-slate-950/40" @click.self="selected = null">
      <aside class="h-full w-full max-w-2xl overflow-auto bg-white">
        <header class="sticky top-0 z-10 flex items-center justify-between gap-3 border-b bg-white px-4 py-3">
          <div>
            <div class="text-xs font-semibold uppercase text-slate-500">Thông tin chi tiết</div>
            <h2 class="mt-1 text-base font-bold">Sync job #{{ selected.id }}</h2>
          </div>
          <button class="rounded-md border px-3 py-2 text-sm font-semibold" @click="selected = null">Đóng</button>
        </header>
        <div class="grid gap-4 p-4">
          <SyncJobProgress :job="selected" />
          <PayloadViewer title="Chi tiết sync job" :payload="selected" />
          <PayloadViewer title="Báo cáo lỗi" :payload="selected.error_report || {}" />
        </div>
      </aside>
    </div>
  </ApiOpsShell>
</template>
