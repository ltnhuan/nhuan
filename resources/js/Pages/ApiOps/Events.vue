<script setup>
import { inject, onMounted, ref } from 'vue'
import { Activity, Ban, Eye, RotateCcw } from '@lucide/vue'
import ApiOpsShell from '@/Components/ApiOps/ApiOpsShell.vue'
import ApiHealthBadge from '@/Components/ApiOps/ApiHealthBadge.vue'
import PayloadViewer from '@/Components/ApiOps/PayloadViewer.vue'

const props = defineProps({
  apiHeaders: { type: Object, required: true },
  sessionUser: { type: Object, default: null },
})

const events = ref([])
const selected = ref(null)
const notice = ref('')
const navigateTo = inject('navigateTo', (url) => { window.location.href = url })

async function load() {
  const response = await fetch('/api/v1/api-ops/events?per_page=50', { headers: props.apiHeaders })
  const data = await response.json()
  events.value = data.data?.data || []
}

async function action(event, type) {
  const response = await fetch(`/api/v1/api-ops/events/${event.id}/${type}`, { method: 'POST', headers: props.apiHeaders })
  await response.json()
  notice.value = type === 'retry' ? 'Đã đưa event vào xử lý lại.' : 'Đã bỏ qua event.'
  await load()
}

function monitorEvent(event) {
  const systemId = event.target_system_id || event.source_system_id
  const suffix = systemId ? `&system_id=${encodeURIComponent(systemId)}` : ''
  navigateTo(`/admin/api-ops/health?source=event${suffix}`)
}

onMounted(load)
</script>

<template>
  <ApiOpsShell :session-user="sessionUser" title="Luồng sự kiện" subtitle="Theo dõi event vào/ra, retry, bỏ qua, trạng thái xử lý và idempotency key.">
    <div v-if="notice" class="rounded-md border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-700">{{ notice }}</div>
    <section class="overflow-x-auto rounded-md border border-slate-200 bg-white">
      <table class="min-w-full text-left text-sm">
        <thead class="bg-slate-50 text-xs uppercase text-slate-500"><tr><th class="px-3 py-2">Sự kiện</th><th class="px-3 py-2">Đối tượng</th><th class="px-3 py-2">Trạng thái</th><th class="px-3 py-2">Idempotency</th><th class="px-3 py-2">Số lần thử</th><th class="px-3 py-2">Thao tác</th></tr></thead>
        <tbody class="divide-y divide-slate-100">
          <tr v-for="event in events" :key="event.id" class="hover:bg-slate-50">
            <td class="px-3 py-2 font-semibold">{{ event.event_key }}</td>
            <td class="px-3 py-2">{{ event.entity_type }} #{{ event.entity_id || '-' }}</td>
            <td class="px-3 py-2"><ApiHealthBadge :status="event.status" /></td>
            <td class="px-3 py-2 font-mono text-xs">{{ event.idempotency_key }}</td>
            <td class="px-3 py-2">{{ event.attempts }}</td>
            <td class="px-3 py-2">
              <div class="flex flex-wrap gap-2">
                <button class="inline-flex items-center gap-1 rounded-md border px-2 py-1 text-xs font-semibold" @click="selected = event"><Eye class="h-3.5 w-3.5" />Mở chi tiết</button>
                <button class="inline-flex items-center gap-1 rounded-md border px-2 py-1 text-xs font-semibold" @click="monitorEvent(event)"><Activity class="h-3.5 w-3.5" />Theo dõi</button>
                <button class="inline-flex items-center gap-1 rounded-md border px-2 py-1 text-xs font-semibold" @click="action(event, 'retry')"><RotateCcw class="h-3.5 w-3.5" />Thử lại</button>
                <button class="inline-flex items-center gap-1 rounded-md border px-2 py-1 text-xs font-semibold" @click="action(event, 'ignore')"><Ban class="h-3.5 w-3.5" />Bỏ qua</button>
              </div>
            </td>
          </tr>
          <tr v-if="!events.length">
            <td colspan="6" class="px-3 py-8 text-center text-sm text-slate-500">Chưa có event.</td>
          </tr>
        </tbody>
      </table>
    </section>
    <div v-if="selected" class="fixed inset-0 z-50 flex justify-end bg-slate-950/40" @click.self="selected = null">
      <aside class="h-full w-full max-w-2xl overflow-auto bg-white p-4">
        <div class="mb-3 flex justify-between gap-3"><div><div class="text-xs font-semibold uppercase text-slate-500">Chi tiết event</div><h2 class="font-bold">{{ selected.event_key }}</h2></div><button class="rounded-md border px-3 py-2 text-sm" @click="selected = null">Đóng</button></div>
        <div class="grid gap-4">
          <PayloadViewer title="Payload event" :payload="selected.payload" />
          <PayloadViewer title="Thông tin event" :payload="selected" />
        </div>
      </aside>
    </div>
  </ApiOpsShell>
</template>
