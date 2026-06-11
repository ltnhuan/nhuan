<script setup>
import { inject, onMounted, reactive, ref } from 'vue'
import { Activity, Eye, FlaskConical, Pencil, RotateCcw } from '@lucide/vue'
import ApiOpsShell from '@/Components/ApiOps/ApiOpsShell.vue'
import ApiHealthBadge from '@/Components/ApiOps/ApiHealthBadge.vue'
import PayloadViewer from '@/Components/ApiOps/PayloadViewer.vue'
import WebhookDeliveryTable from '@/Components/ApiOps/WebhookDeliveryTable.vue'

const props = defineProps({
  apiHeaders: { type: Object, required: true },
  sessionUser: { type: Object, default: null },
})

const systems = ref([])
const webhooks = ref([])
const deliveries = ref([])
const notice = ref('')
const error = ref('')
const selected = ref(null)
const editingWebhookId = ref(null)
const defaultForm = () => ({ system_id: '', name: 'Webhook mới', url: 'https://hooks.example.test/api', secret: 'secret', subscribed_events: ['sis.student.created'], status: 'active', settings: { mock: true } })
const form = reactive(defaultForm())
const navigateTo = inject('navigateTo', (url) => { window.location.href = url })

async function load() {
  const trackedWebhookId = new URLSearchParams(window.location.search).get('webhook_endpoint_id')
  const [optionsResponse, webhooksResponse, deliveriesResponse] = await Promise.all([
    fetch('/api/v1/api-ops/options?include=systems', { headers: props.apiHeaders }),
    fetch('/api/v1/api-ops/webhooks?per_page=50', { headers: props.apiHeaders }),
    fetch(`/api/v1/api-ops/webhook-deliveries?per_page=50${trackedWebhookId ? `&webhook_endpoint_id=${trackedWebhookId}` : ''}`, { headers: props.apiHeaders }),
  ])
  systems.value = (await optionsResponse.json()).data?.systems || []
  webhooks.value = (await webhooksResponse.json()).data?.data || []
  deliveries.value = (await deliveriesResponse.json()).data?.data || []
  form.system_id ||= systems.value[0]?.id || ''
}

async function createWebhook() {
  notice.value = ''
  error.value = ''
  const url = editingWebhookId.value ? `/api/v1/api-ops/webhooks/${editingWebhookId.value}` : '/api/v1/api-ops/webhooks'
  const method = editingWebhookId.value ? 'PUT' : 'POST'
  try {
    const response = await fetch(url, { method, headers: props.apiHeaders, body: JSON.stringify(form) })
    const data = await response.json()
    if (!response.ok || data.success === false) throw new Error(data.message || 'Không lưu được webhook.')
    notice.value = editingWebhookId.value ? 'Đã cập nhật webhook.' : 'Đã lưu webhook.'
    resetForm()
    await load()
  } catch (exception) {
    error.value = exception.message
  }
}

async function webhookAction(webhook, action) {
  const response = await fetch(`/api/v1/api-ops/webhooks/${webhook.id}/${action}`, { method: 'POST', headers: props.apiHeaders })
  await response.json()
  notice.value = action === 'test' ? 'Đã kiểm tra webhook.' : 'Đã retry các lần gửi webhook bị lỗi.'
  await load()
}

function editWebhook(webhook) {
  editingWebhookId.value = webhook.id
  Object.assign(form, {
    system_id: webhook.system_id || systems.value[0]?.id || '',
    name: webhook.name || '',
    url: webhook.url || '',
    secret: webhook.secret || '***MASKED***',
    subscribed_events: webhook.subscribed_events || [],
    status: webhook.status || 'active',
    settings: webhook.settings || {},
  })
}

function resetForm() {
  editingWebhookId.value = null
  Object.assign(form, defaultForm(), { system_id: systems.value[0]?.id || '' })
}

function monitorWebhook(webhook) {
  navigateTo(`/admin/api-ops/webhooks?webhook_endpoint_id=${encodeURIComponent(webhook.id)}`)
}

onMounted(load)
</script>

<template>
  <ApiOpsShell :session-user="sessionUser" title="Trung tâm webhook" subtitle="Tạo webhook endpoint, kiểm tra chữ ký, retry lỗi gửi và theo dõi phân phối theo từng hệ thống.">
    <div v-if="notice" class="rounded-md border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-700">{{ notice }}</div>
    <div v-if="error" class="rounded-md border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-700">{{ error }}</div>
    <div class="grid gap-4 xl:grid-cols-[360px_1fr]">
      <section class="rounded-md border border-slate-200 bg-white p-4">
        <div class="flex items-center justify-between gap-2">
          <h2 class="text-sm font-semibold">{{ editingWebhookId ? 'Sửa webhook endpoint' : 'Tạo webhook endpoint' }}</h2>
          <button v-if="editingWebhookId" class="rounded-md border px-2 py-1 text-xs font-semibold text-slate-700" @click="resetForm">Hủy sửa</button>
        </div>
        <div class="mt-3 grid gap-2">
          <select v-model="form.system_id" class="h-10 rounded-md border px-2 text-sm"><option v-for="system in systems" :key="system.id" :value="system.id">{{ system.code }}</option></select>
          <input v-model="form.name" class="h-10 rounded-md border px-2 text-sm" placeholder="Tên webhook" />
          <input v-model="form.url" class="h-10 rounded-md border px-2 text-sm" placeholder="URL nhận webhook" />
          <input v-model="form.secret" class="h-10 rounded-md border px-2 text-sm" placeholder="Secret" />
          <select v-model="form.status" class="h-10 rounded-md border px-2 text-sm"><option>active</option><option>inactive</option></select>
          <button class="rounded-md bg-slate-950 px-3 py-2 text-sm font-semibold text-white" @click="createWebhook">{{ editingWebhookId ? 'Cập nhật webhook' : 'Lưu webhook' }}</button>
        </div>
      </section>
      <section class="overflow-x-auto rounded-md border border-slate-200 bg-white">
        <table class="min-w-full text-left text-sm">
          <thead class="bg-slate-50 text-xs uppercase text-slate-500"><tr><th class="px-3 py-2">Webhook</th><th class="px-3 py-2">URL</th><th class="px-3 py-2">Trạng thái</th><th class="px-3 py-2">Thành công gần nhất</th><th class="px-3 py-2">Thao tác</th></tr></thead>
          <tbody class="divide-y divide-slate-100">
            <tr v-for="webhook in webhooks" :key="webhook.id">
              <td class="px-3 py-2 font-semibold">{{ webhook.name }}</td>
              <td class="max-w-md truncate px-3 py-2">{{ webhook.url }}</td>
              <td class="px-3 py-2"><ApiHealthBadge :status="webhook.status" /></td>
              <td class="px-3 py-2">{{ webhook.last_success_at || '-' }}</td>
              <td class="px-3 py-2">
                <div class="flex flex-wrap gap-2">
                  <button class="inline-flex items-center gap-1 rounded-md border px-2 py-1 text-xs font-semibold" @click="selected = webhook"><Eye class="h-3.5 w-3.5" />Mở chi tiết</button>
                  <button class="inline-flex items-center gap-1 rounded-md border px-2 py-1 text-xs font-semibold" @click="editWebhook(webhook)"><Pencil class="h-3.5 w-3.5" />Sửa</button>
                  <button class="inline-flex items-center gap-1 rounded-md border px-2 py-1 text-xs font-semibold" @click="monitorWebhook(webhook)"><Activity class="h-3.5 w-3.5" />Theo dõi</button>
                  <button class="inline-flex items-center gap-1 rounded-md border px-2 py-1 text-xs font-semibold" @click="webhookAction(webhook, 'test')"><FlaskConical class="h-3.5 w-3.5" />Kiểm tra</button>
                  <button class="inline-flex items-center gap-1 rounded-md border px-2 py-1 text-xs font-semibold" @click="webhookAction(webhook, 'retry-failed')"><RotateCcw class="h-3.5 w-3.5" />Retry lỗi</button>
                </div>
              </td>
            </tr>
            <tr v-if="!webhooks.length">
              <td colspan="5" class="px-3 py-8 text-center text-sm text-slate-500">Chưa có webhook.</td>
            </tr>
          </tbody>
        </table>
      </section>
    </div>
    <WebhookDeliveryTable :deliveries="deliveries" />
    <div v-if="selected" class="fixed inset-0 z-50 flex justify-end bg-slate-950/40" @click.self="selected = null">
      <aside class="h-full w-full max-w-2xl overflow-auto bg-white">
        <header class="sticky top-0 z-10 flex items-center justify-between gap-3 border-b bg-white px-4 py-3">
          <div>
            <div class="text-xs font-semibold uppercase text-slate-500">Thông tin chi tiết</div>
            <h2 class="mt-1 text-base font-bold">{{ selected.name }}</h2>
          </div>
          <button class="rounded-md border px-3 py-2 text-sm font-semibold" @click="selected = null">Đóng</button>
        </header>
        <div class="p-4">
          <PayloadViewer title="Chi tiết webhook" :payload="selected" />
        </div>
      </aside>
    </div>
  </ApiOpsShell>
</template>
