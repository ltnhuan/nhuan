<script setup>
import { computed, onMounted, ref } from 'vue'
import EraLmsLayout from '@/Layouts/EraLmsLayout.vue'

const props = defineProps({
  apiHeaders: { type: Object, required: true },
  sessionUser: { type: Object, default: null },
})

const events = ref([])
const selected = ref(null)
const loading = ref(true)
const error = ref('')
const failedCount = computed(() => events.value.filter((event) => event.status === 'failed').length)

async function load() {
  loading.value = true
  error.value = ''
  try {
    const response = await fetch('/api/v1/integrations/events?per_page=100', { headers: props.apiHeaders })
    const data = await response.json()
    if (!response.ok) throw new Error(data.message || 'Không tải được nhật ký sự kiện.')
    events.value = data.data || []
    selected.value = events.value[0] || null
  } catch (exception) {
    error.value = exception.message
  } finally {
    loading.value = false
  }
}

async function retry(event) {
  try {
    const response = await fetch(`/api/v1/integrations/events/${event.id}/retry`, { method: 'POST', headers: props.apiHeaders })
    const data = await response.json()
    if (!response.ok) throw new Error(data.message || 'Không thể thử lại sự kiện.')
    selected.value = { ...event, ...data }
    await load()
  } catch (exception) {
    error.value = exception.message
  }
}

onMounted(load)
</script>

<template>
  <EraLmsLayout :session-user="sessionUser">
    <template #breadcrumb>Trung tâm tích hợp / Nhật ký sự kiện</template>
    <section class="mx-auto grid max-w-7xl gap-4 px-4 py-5 sm:px-6 lg:grid-cols-[1fr_360px]">
      <div class="overflow-hidden border bg-white">
        <div class="flex items-center justify-between border-b px-4 py-3">
          <div><h1 class="text-sm font-semibold text-slate-950">Sự kiện tích hợp</h1><p class="mt-1 text-xs text-slate-500">{{ events.length }} sự kiện · {{ failedCount }} cần xử lý</p></div>
          <button class="rounded-md border px-3 py-2 text-xs font-semibold" :disabled="loading" @click="load">Tải lại</button>
        </div>
        <div v-if="error" class="border-b border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">{{ error }}</div>
        <table class="w-full text-left text-sm">
          <thead class="bg-slate-50 text-xs uppercase text-slate-500"><tr><th class="px-4 py-3">Sự kiện</th><th class="px-4 py-3">Luồng</th><th class="px-4 py-3">Trạng thái</th><th class="px-4 py-3">Mã idempotency</th><th class="px-4 py-3">Thao tác</th></tr></thead>
          <tbody class="divide-y">
            <tr v-if="loading"><td class="px-4 py-6 text-slate-500" colspan="5">Đang tải dữ liệu...</td></tr>
            <tr v-else-if="!events.length"><td class="px-4 py-6 text-slate-500" colspan="5">Chưa có sự kiện tích hợp.</td></tr>
            <tr v-for="event in events" v-else :key="event.id" class="cursor-pointer hover:bg-slate-50" :class="selected?.id === event.id ? 'bg-cyan-50' : ''" @click="selected = event">
              <td class="px-4 py-3 font-medium">{{ event.event_key || event.type }}</td><td class="px-4 py-3">{{ event.direction }}</td><td class="px-4 py-3"><span class="rounded bg-slate-100 px-2 py-1 text-xs">{{ event.status }}</span></td><td class="px-4 py-3 font-mono text-xs">{{ event.idempotency_key || '-' }}</td><td class="px-4 py-3"><button class="rounded-md border px-2 py-1" @click.stop="retry(event)">Thử lại</button></td>
            </tr>
          </tbody>
        </table>
      </div>
      <aside class="border bg-white p-4">
        <h2 class="text-sm font-semibold">Chi tiết sự kiện</h2>
        <div v-if="selected" class="mt-4 space-y-3 text-sm">
          <div class="rounded-md border bg-slate-50 p-3"><div class="text-xs text-slate-500">Mã sự kiện</div><div class="font-semibold">{{ selected.event_key || selected.type }}</div></div>
          <div class="rounded-md border bg-slate-50 p-3"><div class="text-xs text-slate-500">Đối tượng</div><div class="font-semibold">{{ selected.entity_type || '-' }}</div></div>
          <div class="rounded-md border bg-slate-50 p-3"><div class="text-xs text-slate-500">Hướng</div><div class="font-semibold">{{ selected.direction }}</div></div>
          <div class="rounded-md border bg-slate-50 p-3"><div class="text-xs text-slate-500">Số lần thử</div><div class="font-semibold">{{ selected.attempts ?? 0 }}</div></div>
          <div class="rounded-md border bg-slate-50 p-3"><div class="text-xs text-slate-500">Nội dung gửi</div><pre class="max-h-56 overflow-auto whitespace-pre-wrap text-xs">{{ JSON.stringify(selected.payload || selected.metadata || {}, null, 2) }}</pre></div>
        </div>
        <div v-else class="mt-4 rounded-md bg-slate-50 p-4 text-sm text-slate-500">Chọn một sự kiện để xem chi tiết.</div>
      </aside>
    </section>
  </EraLmsLayout>
</template>
