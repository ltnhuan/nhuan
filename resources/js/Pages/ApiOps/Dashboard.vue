<script setup>
import { computed, inject, onMounted, ref } from 'vue'
import ApiOpsShell from '@/Components/ApiOps/ApiOpsShell.vue'
import ApiStatusCard from '@/Components/ApiOps/ApiStatusCard.vue'
import ApiLatencyChart from '@/Components/ApiOps/ApiLatencyChart.vue'
import ApiErrorRateChart from '@/Components/ApiOps/ApiErrorRateChart.vue'

const props = defineProps({
  apiHeaders: { type: Object, required: true },
  sessionUser: { type: Object, default: null },
})

const loading = ref(true)
const error = ref('')
const dashboard = ref({ cards: {}, charts: {} })
const navigateTo = inject('navigateTo', (url) => { window.location.href = url })

const cards = computed(() => [
  ['Hệ thống kết nối', dashboard.value.cards.systems_total || 0, 'Đã đăng ký', 'slate'],
  ['Đang ổn định', dashboard.value.cards.healthy || 0, 'Snapshot mới nhất', 'green'],
  ['Cần theo dõi/mất kết nối', `${dashboard.value.cards.degraded || 0}/${dashboard.value.cards.down || 0}`, 'Cần kiểm tra', (dashboard.value.cards.down || 0) ? 'red' : 'amber'],
  ['Request hôm nay', dashboard.value.cards.requests_today || 0, `${dashboard.value.cards.error_rate || 0}% lỗi`, 'blue'],
  ['Sync job lỗi', dashboard.value.cards.sync_jobs_failed || 0, 'Lỗi đang mở', 'red'],
  ['Webhook lỗi', dashboard.value.cards.webhook_failed || 0, 'Lỗi gửi webhook', 'red'],
  ['Event đang chờ', dashboard.value.cards.event_pending || 0, 'Chờ/thử lại', 'amber'],
  ['Độ trễ trung bình', `${dashboard.value.cards.average_latency || 0}ms`, 'Theo health check', 'slate'],
])

async function load() {
  loading.value = true
  error.value = ''
  try {
    const response = await fetch('/api/v1/api-ops/dashboard', { headers: props.apiHeaders })
    const data = await response.json()
    if (!response.ok || data.success === false) throw new Error(data.message || 'Không tải được dashboard.')
    dashboard.value = data.data
  } catch (exception) {
    error.value = exception.message
  } finally {
    loading.value = false
  }
}

function go(route) {
  navigateTo(route)
}

onMounted(load)
</script>

<template>
  <ApiOpsShell :session-user="sessionUser" title="Tổng quan vận hành API" subtitle="Theo dõi realtime sức khỏe API, lưu lượng request, webhook, event, sync job và cảnh báo.">
    <div class="flex flex-wrap justify-end gap-2">
      <button class="rounded-md border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50" @click="go('/admin/api-ops/registry')">Mở danh bạ API</button>
      <button class="rounded-md border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50" @click="go('/admin/api-ops/requests')">Xem log request</button>
      <button class="rounded-md border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50" @click="go('/admin/api-ops/health')">Theo dõi sức khỏe</button>
      <button class="rounded-md bg-slate-950 px-3 py-2 text-sm font-semibold text-white hover:bg-slate-800 disabled:opacity-50" :disabled="loading" @click="load">
        {{ loading ? 'Đang làm mới' : 'Làm mới dashboard' }}
      </button>
    </div>
    <div v-if="error" class="rounded-md border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-700">{{ error }}</div>
    <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
      <ApiStatusCard v-for="card in cards" :key="card[0]" :label="card[0]" :value="card[1]" :meta="card[2]" :tone="card[3]" />
    </div>
    <div class="grid gap-4 xl:grid-cols-2">
      <ApiLatencyChart :items="dashboard.charts.latency_trend || []" />
      <ApiErrorRateChart :items="dashboard.charts.error_by_system || []" />
    </div>
    <div class="grid gap-4 xl:grid-cols-2">
      <ApiErrorRateChart :items="dashboard.charts.sync_job_status || {}" />
      <ApiErrorRateChart :items="dashboard.charts.webhook_delivery_status || {}" />
    </div>
  </ApiOpsShell>
</template>
