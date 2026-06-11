<script setup>
import { onMounted, reactive, ref } from 'vue'
import ApiOpsShell from '@/Components/ApiOps/ApiOpsShell.vue'
import ApiRequestLogTable from '@/Components/ApiOps/ApiRequestLogTable.vue'

const props = defineProps({
  apiHeaders: { type: Object, required: true },
  sessionUser: { type: Object, default: null },
})

const logs = ref([])
const filters = reactive({ status_code: '', duration_min: '', error: false })

async function load() {
  const params = new URLSearchParams()
  if (filters.status_code) params.set('status_code', filters.status_code)
  if (filters.duration_min) params.set('duration_min', filters.duration_min)
  if (filters.error) params.set('error', '1')
  const response = await fetch(`/api/v1/api-ops/requests?per_page=50&${params}`, { headers: props.apiHeaders })
  const data = await response.json()
  logs.value = data.data?.data || []
}

onMounted(load)
</script>

<template>
  <ApiOpsShell :session-user="sessionUser" title="Nhật ký gọi API" subtitle="Tra cứu request/response, lọc lỗi, xem payload đã che dữ liệu nhạy cảm và theo dõi độ trễ.">
    <section class="rounded-md border border-slate-200 bg-white p-3">
      <div class="flex flex-wrap items-end gap-3">
        <label><span class="text-xs font-semibold uppercase text-slate-500">Mã trạng thái</span><input v-model="filters.status_code" class="mt-1 h-10 w-28 rounded-md border px-2 text-sm" placeholder="500" /></label>
        <label><span class="text-xs font-semibold uppercase text-slate-500">Độ trễ tối thiểu</span><input v-model="filters.duration_min" class="mt-1 h-10 w-32 rounded-md border px-2 text-sm" placeholder="ms" /></label>
        <label class="flex h-10 items-center gap-2 text-sm font-semibold"><input v-model="filters.error" type="checkbox" /> Chỉ xem lỗi</label>
        <button class="rounded-md bg-slate-950 px-3 py-2 text-sm font-semibold text-white" @click="load">Áp dụng bộ lọc</button>
      </div>
    </section>
    <ApiRequestLogTable :logs="logs" />
  </ApiOpsShell>
</template>
