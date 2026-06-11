<script setup>
import { computed, onMounted, ref } from 'vue'
import EraLmsLayout from '@/Layouts/EraLmsLayout.vue'

const props = defineProps({
  apiHeaders: { type: Object, required: true },
  sessionUser: { type: Object, default: null },
})

const loading = ref(true)
const error = ref('')
const health = ref({ systems: {}, events: {}, deliveries: {}, queues: {}, conflicts_open: 0 })

const cards = computed(() => [
  ['Hệ thống tích hợp', total(health.value.systems), statusText(health.value.systems)],
  ['Sự kiện', total(health.value.events), statusText(health.value.events)],
  ['Phân phối webhook', total(health.value.deliveries), statusText(health.value.deliveries)],
  ['Xung đột mở', health.value.conflicts_open || 0, 'Ánh xạ'],
])

const bars = computed(() => {
  const eventTotal = Math.max(total(health.value.events), 1)
  return Object.entries(health.value.events || {}).map(([status, count]) => [status, Math.round((count / eventTotal) * 100), count])
})
const statusLabels = {
  active: 'Hoạt động',
  pending: 'Đang chờ',
  completed: 'Hoàn tất',
  failed: 'Thất bại',
  processing: 'Đang xử lý',
  mapping: 'Ánh xạ',
  'sync-sis': 'Đồng bộ SIS',
}

function statusLabel(value) {
  return statusLabels[value] || value || '-'
}

function total(bucket) {
  return Object.values(bucket || {}).reduce((sum, value) => sum + Number(value || 0), 0)
}

function statusText(bucket) {
  const keys = Object.keys(bucket || {})
  return keys.length ? keys.map(statusLabel).join(', ') : 'không có dữ liệu'
}

async function load() {
  loading.value = true
  error.value = ''

  try {
    const response = await fetch('/api/v1/integrations/health', { headers: props.apiHeaders })
    const data = await response.json()
    if (!response.ok) throw new Error(data.message || 'Không tải được trạng thái tích hợp.')
    health.value = data
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
    <template #breadcrumb>Trung tâm tích hợp / Tổng quan</template>
    <section class="mx-auto max-w-7xl px-4 py-5 sm:px-6">
      <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
          <h1 class="text-lg font-semibold">Bảng điều khiển tích hợp</h1>
          <p class="mt-1 text-sm text-slate-600">Theo dõi kết nối SIS, hàng chờ sự kiện tồn đọng, hàng đợi và kết quả đồng bộ.</p>
        </div>
        <button class="rounded-md bg-slate-950 px-3 py-2 text-sm text-white" :disabled="loading" @click="load">Kiểm tra tình trạng</button>
      </div>

      <div v-if="error" class="mt-4 rounded-md border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">{{ error }}</div>

      <div class="mt-5 grid gap-3 md:grid-cols-4">
        <div v-for="card in cards" :key="card[0]" class="border bg-white p-4">
          <div class="text-sm text-slate-600">{{ card[0] }}</div>
          <strong class="mt-2 block text-2xl">{{ loading ? '...' : card[1] }}</strong>
          <div class="mt-1 truncate text-xs text-slate-500">{{ card[2] }}</div>
        </div>
      </div>

      <div class="mt-5 grid gap-4 lg:grid-cols-[1fr_360px]">
        <div class="border bg-white p-4">
          <h2 class="text-sm font-semibold">Phân bố trạng thái sự kiện</h2>
          <div v-if="!loading && !bars.length" class="mt-4 rounded-md bg-slate-50 p-4 text-sm text-slate-500">Chưa có sự kiện tích hợp.</div>
          <div v-else class="mt-4 space-y-3">
            <div v-for="bar in bars" :key="bar[0]">
              <div class="mb-1 flex justify-between text-sm"><span>{{ statusLabel(bar[0]) }}</span><span>{{ bar[2] }} bản ghi</span></div>
              <div class="h-3 rounded bg-slate-100"><div class="h-3 rounded bg-blue-900" :style="{ width: `${bar[1]}%` }"></div></div>
            </div>
          </div>
        </div>
        <aside class="border bg-white p-4">
          <h2 class="text-sm font-semibold">Hàng đợi</h2>
          <div class="mt-4 space-y-2 text-sm">
            <div v-for="(status, queue) in health.queues" :key="queue" class="flex justify-between border-b border-slate-100 py-2">
              <span>{{ statusLabel(queue) }}</span>
              <strong>{{ statusLabel(status) }}</strong>
            </div>
          </div>
        </aside>
      </div>
    </section>
  </EraLmsLayout>
</template>
