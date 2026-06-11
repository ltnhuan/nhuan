<script setup>
import { inject, ref } from 'vue'
import ApiHealthBadge from './ApiHealthBadge.vue'
import PayloadViewer from './PayloadViewer.vue'

defineProps({
  logs: { type: Array, default: () => [] },
})

const selected = ref(null)
const navigateTo = inject('navigateTo', (url) => { window.location.href = url })

function status(log) {
  if (!log.status_code) return 'pending'
  if (log.status_code >= 500) return 'failed'
  if (log.status_code >= 400) return 'degraded'
  return 'success'
}

function monitorEndpoint(log) {
  const endpointId = log.endpoint_id || log.endpoint?.id
  const suffix = endpointId ? `&endpoint_id=${encodeURIComponent(endpointId)}` : ''
  navigateTo(`/admin/api-ops/health?source=request-log${suffix}`)
}
</script>

<template>
  <div class="overflow-hidden rounded-md border border-slate-200 bg-white">
    <div class="overflow-x-auto">
      <table class="min-w-full text-left text-sm">
        <thead class="bg-slate-50 text-xs uppercase text-slate-500">
          <tr>
            <th class="px-3 py-2">Thời gian</th>
            <th class="px-3 py-2">Chiều</th>
            <th class="px-3 py-2">Phương thức</th>
            <th class="px-3 py-2">URL</th>
            <th class="px-3 py-2">Trạng thái</th>
            <th class="px-3 py-2">Độ trễ</th>
            <th class="px-3 py-2">Thao tác</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
          <tr v-for="log in logs" :key="log.id" class="hover:bg-slate-50">
            <td class="whitespace-nowrap px-3 py-2 text-xs text-slate-500">{{ log.created_at }}</td>
            <td class="px-3 py-2">{{ log.direction }}</td>
            <td class="px-3 py-2 font-mono text-xs">{{ log.method }}</td>
            <td class="max-w-md truncate px-3 py-2">{{ log.url }}</td>
            <td class="px-3 py-2"><ApiHealthBadge :status="status(log)" /></td>
            <td class="px-3 py-2 font-mono text-xs">{{ log.duration_ms || '-' }}ms</td>
            <td class="px-3 py-2">
              <button class="rounded-md border border-slate-300 px-2 py-1 text-xs font-semibold text-slate-700 hover:bg-slate-50" @click="selected = log">Mở chi tiết</button>
            </td>
          </tr>
          <tr v-if="!logs.length">
            <td colspan="7" class="px-3 py-8 text-center text-sm text-slate-500">Chưa có log API.</td>
          </tr>
        </tbody>
      </table>
    </div>

    <div v-if="selected" class="fixed inset-0 z-50 flex justify-end bg-slate-950/40 backdrop-blur-sm" @click.self="selected = null">
      <aside class="h-full w-full max-w-3xl overflow-auto bg-white shadow-2xl">
        <header class="sticky top-0 z-10 flex items-start justify-between gap-3 border-b border-slate-200 bg-white px-4 py-3">
          <div class="min-w-0">
            <div class="text-xs font-semibold uppercase text-slate-500">Chi tiết request</div>
            <h2 class="mt-1 truncate text-base font-bold">{{ selected.method }} {{ selected.url }}</h2>
            <div class="mt-1 font-mono text-xs text-slate-500">{{ selected.request_uuid }}</div>
          </div>
          <div class="flex shrink-0 gap-2">
            <button class="rounded-md border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700" @click="monitorEndpoint(selected)">Theo dõi endpoint</button>
            <button class="rounded-md border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700" @click="selected = null">Đóng</button>
          </div>
        </header>
        <div class="grid gap-4 p-4">
          <PayloadViewer title="Header request" :payload="selected.request_headers" />
          <PayloadViewer title="Body request" :payload="selected.request_body" />
          <PayloadViewer title="Header phản hồi" :payload="selected.response_headers" />
          <PayloadViewer title="Body phản hồi" :payload="selected.response_body" />
        </div>
      </aside>
    </div>
  </div>
</template>
