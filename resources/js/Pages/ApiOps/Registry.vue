<script setup>
import { computed, inject, onMounted, reactive, ref } from 'vue'
import { Activity, Eye, FlaskConical, Pencil } from '@lucide/vue'
import ApiOpsShell from '@/Components/ApiOps/ApiOpsShell.vue'
import ApiHealthBadge from '@/Components/ApiOps/ApiHealthBadge.vue'
import JsonSchemaEditor from '@/Components/ApiOps/JsonSchemaEditor.vue'
import PayloadViewer from '@/Components/ApiOps/PayloadViewer.vue'

const props = defineProps({
  apiHeaders: { type: Object, required: true },
  sessionUser: { type: Object, default: null },
})

const systems = ref([])
const endpoints = ref([])
const notice = ref('')
const error = ref('')
const navigateTo = inject('navigateTo', (url) => { window.location.href = url })
const editingSystemId = ref(null)
const editingEndpointId = ref(null)
const selectedDetail = ref(null)
const selectedDetailType = ref('')

const defaultSystem = () => ({ code: 'NEW-SYSTEM', name: 'Hệ thống mới', type: 'third_party', base_url: 'https://api.example.test', environment: 'staging', auth_type: 'api_key', owner_team: 'Integration', status: 'active', settings: { mock: true } })
const defaultEndpoint = () => ({ system_id: '', code: 'NEW_ENDPOINT', name: 'Endpoint mới', method: 'GET', path: '/health', module: 'integration', purpose: 'Kiểm tra sức khỏe', status: 'active', request_schema: { required: [], sensitive_fields: ['token'] }, response_schema: { type: 'object' } })

const systemForm = reactive(defaultSystem())
const endpointForm = reactive(defaultEndpoint())

const detailTitle = computed(() => {
  if (!selectedDetail.value) return ''
  return selectedDetailType.value === 'system'
    ? `${selectedDetail.value.code} · ${selectedDetail.value.name}`
    : `${selectedDetail.value.method} ${selectedDetail.value.path}`
})

async function load() {
  const [systemsResponse, endpointsResponse] = await Promise.all([
    fetch('/api/v1/api-ops/systems?per_page=100', { headers: props.apiHeaders }),
    fetch('/api/v1/api-ops/endpoints?per_page=100', { headers: props.apiHeaders }),
  ])
  const systemsJson = await systemsResponse.json()
  const endpointsJson = await endpointsResponse.json()
  systems.value = systemsJson.data?.data || []
  endpoints.value = endpointsJson.data?.data || []
  endpointForm.system_id ||= systems.value[0]?.id || ''
}

async function submitSystem() {
  const url = editingSystemId.value ? `/api/v1/api-ops/systems/${editingSystemId.value}` : '/api/v1/api-ops/systems'
  const method = editingSystemId.value ? 'PUT' : 'POST'
  if (await submit(url, method, systemForm, editingSystemId.value ? 'Đã cập nhật hệ thống API.' : 'Đã lưu hệ thống API.')) {
    resetSystemForm()
  }
}

async function submitEndpoint() {
  const url = editingEndpointId.value ? `/api/v1/api-ops/endpoints/${editingEndpointId.value}` : '/api/v1/api-ops/endpoints'
  const method = editingEndpointId.value ? 'PUT' : 'POST'
  if (await submit(url, method, endpointForm, editingEndpointId.value ? 'Đã cập nhật endpoint.' : 'Đã lưu endpoint.')) {
    resetEndpointForm()
  }
}

async function submit(url, method, payload, message) {
  notice.value = ''
  error.value = ''
  try {
    const response = await fetch(url, { method, headers: props.apiHeaders, body: JSON.stringify(payload) })
    const data = await response.json()
    if (!response.ok || data.success === false) throw new Error(data.message || 'Không lưu được dữ liệu.')
    notice.value = message
    await load()
    return true
  } catch (exception) {
    error.value = exception.message
    return false
  }
}

async function testEndpoint(endpoint) {
  await submit(`/api/v1/api-ops/endpoints/${endpoint.id}/test`, 'POST', { body: { id: 'sample' }, headers: {} }, `Đã kiểm tra endpoint ${endpoint.code}.`)
}

async function testSystem(system) {
  await submit(`/api/v1/api-ops/systems/${system.id}/test-connection`, 'POST', {}, `Đã kiểm tra kết nối ${system.code}.`)
}

function showDetail(item, type) {
  selectedDetail.value = item
  selectedDetailType.value = type
}

function editSystem(system) {
  editingSystemId.value = system.id
  Object.assign(systemForm, {
    code: system.code || '',
    name: system.name || '',
    type: system.type || 'third_party',
    base_url: system.base_url || '',
    environment: system.environment || 'staging',
    auth_type: system.auth_type || 'api_key',
    owner_team: system.owner_team || '',
    status: system.status || 'active',
    settings: system.settings || {},
  })
}

function editEndpoint(endpoint) {
  editingEndpointId.value = endpoint.id
  Object.assign(endpointForm, {
    system_id: endpoint.system_id || systems.value[0]?.id || '',
    code: endpoint.code || '',
    name: endpoint.name || '',
    method: endpoint.method || 'GET',
    path: endpoint.path || '',
    module: endpoint.module || '',
    purpose: endpoint.purpose || '',
    status: endpoint.status || 'active',
    request_schema: endpoint.request_schema || {},
    response_schema: endpoint.response_schema || {},
  })
}

function resetSystemForm() {
  editingSystemId.value = null
  Object.assign(systemForm, defaultSystem())
}

function resetEndpointForm() {
  editingEndpointId.value = null
  Object.assign(endpointForm, defaultEndpoint(), { system_id: systems.value[0]?.id || '' })
}

function monitor(item) {
  const endpointId = item.path ? `&endpoint_id=${encodeURIComponent(item.id)}` : ''
  const systemId = item.path ? '' : `&system_id=${encodeURIComponent(item.id)}`
  navigateTo(`/admin/api-ops/health?source=registry${systemId}${endpointId}`)
}

onMounted(load)
</script>

<template>
  <ApiOpsShell :session-user="sessionUser" title="Danh bạ API" subtitle="Quản lý hệ thống tích hợp, endpoint, schema, chính sách retry, rate limit và thao tác kiểm tra.">
    <div v-if="notice" class="rounded-md border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-700">{{ notice }}</div>
    <div v-if="error" class="rounded-md border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-700">{{ error }}</div>
    <div class="grid gap-4 xl:grid-cols-[360px_1fr]">
      <aside class="space-y-4">
        <section class="rounded-md border border-slate-200 bg-white p-4">
          <div class="flex items-center justify-between gap-2">
            <h2 class="text-sm font-semibold">{{ editingSystemId ? 'Sửa hệ thống API' : 'Tạo hệ thống API' }}</h2>
            <button v-if="editingSystemId" class="rounded-md border px-2 py-1 text-xs font-semibold text-slate-700" @click="resetSystemForm">Hủy sửa</button>
          </div>
          <div class="mt-3 grid gap-2">
            <input v-model="systemForm.code" class="h-10 rounded-md border border-slate-300 px-2 text-sm" placeholder="Mã hệ thống" />
            <input v-model="systemForm.name" class="h-10 rounded-md border border-slate-300 px-2 text-sm" placeholder="Tên hệ thống" />
            <select v-model="systemForm.type" class="h-10 rounded-md border border-slate-300 bg-white px-2 text-sm"><option>sis</option><option>lms</option><option>crm</option><option>finance</option><option>hr</option><option>mobile</option><option>exam</option><option>ai</option><option>third_party</option></select>
            <input v-model="systemForm.base_url" class="h-10 rounded-md border border-slate-300 px-2 text-sm" placeholder="Base URL" />
            <input v-model="systemForm.environment" class="h-10 rounded-md border border-slate-300 px-2 text-sm" placeholder="Môi trường" />
            <input v-model="systemForm.owner_team" class="h-10 rounded-md border border-slate-300 px-2 text-sm" placeholder="Đội phụ trách" />
            <select v-model="systemForm.status" class="h-10 rounded-md border border-slate-300 bg-white px-2 text-sm"><option>active</option><option>inactive</option><option>maintenance</option></select>
            <button class="rounded-md bg-slate-950 px-3 py-2 text-sm font-semibold text-white" @click="submitSystem">{{ editingSystemId ? 'Cập nhật hệ thống' : 'Lưu hệ thống' }}</button>
          </div>
        </section>
        <section class="rounded-md border border-slate-200 bg-white p-4">
          <div class="flex items-center justify-between gap-2">
            <h2 class="text-sm font-semibold">{{ editingEndpointId ? 'Sửa endpoint' : 'Tạo endpoint' }}</h2>
            <button v-if="editingEndpointId" class="rounded-md border px-2 py-1 text-xs font-semibold text-slate-700" @click="resetEndpointForm">Hủy sửa</button>
          </div>
          <div class="mt-3 grid gap-2">
            <select v-model="endpointForm.system_id" class="h-10 rounded-md border border-slate-300 bg-white px-2 text-sm"><option v-for="system in systems" :key="system.id" :value="system.id">{{ system.code }}</option></select>
            <input v-model="endpointForm.code" class="h-10 rounded-md border border-slate-300 px-2 text-sm" placeholder="Mã endpoint" />
            <input v-model="endpointForm.name" class="h-10 rounded-md border border-slate-300 px-2 text-sm" placeholder="Tên endpoint" />
            <select v-model="endpointForm.method" class="h-10 rounded-md border border-slate-300 bg-white px-2 text-sm"><option>GET</option><option>POST</option><option>PUT</option><option>PATCH</option><option>DELETE</option></select>
            <input v-model="endpointForm.path" class="h-10 rounded-md border border-slate-300 px-2 text-sm" placeholder="/path" />
            <input v-model="endpointForm.module" class="h-10 rounded-md border border-slate-300 px-2 text-sm" placeholder="Module" />
            <input v-model="endpointForm.purpose" class="h-10 rounded-md border border-slate-300 px-2 text-sm" placeholder="Mục đích" />
            <select v-model="endpointForm.status" class="h-10 rounded-md border border-slate-300 bg-white px-2 text-sm"><option>active</option><option>inactive</option><option>deprecated</option></select>
            <JsonSchemaEditor v-model="endpointForm.request_schema" label="Schema request" />
            <button class="rounded-md bg-slate-950 px-3 py-2 text-sm font-semibold text-white" @click="submitEndpoint">{{ editingEndpointId ? 'Cập nhật endpoint' : 'Lưu endpoint' }}</button>
          </div>
        </section>
      </aside>
      <div class="space-y-4">
        <section class="overflow-x-auto rounded-md border border-slate-200 bg-white">
          <table class="min-w-full text-left text-sm">
            <thead class="bg-slate-50 text-xs uppercase text-slate-500"><tr><th class="px-3 py-2">Hệ thống</th><th class="px-3 py-2">Loại</th><th class="px-3 py-2">Môi trường</th><th class="px-3 py-2">Trạng thái</th><th class="px-3 py-2">Thao tác</th></tr></thead>
            <tbody class="divide-y divide-slate-100">
              <tr v-for="system in systems" :key="system.id">
                <td class="px-3 py-2 font-semibold">{{ system.code }}</td>
                <td class="px-3 py-2">{{ system.type }}</td>
                <td class="px-3 py-2">{{ system.environment }}</td>
                <td class="px-3 py-2"><ApiHealthBadge :status="system.status" /></td>
                <td class="px-3 py-2">
                  <div class="flex flex-wrap gap-2">
                    <button class="inline-flex items-center gap-1 rounded-md border px-2 py-1 text-xs font-semibold" @click="showDetail(system, 'system')"><Eye class="h-3.5 w-3.5" />Mở chi tiết</button>
                    <button class="inline-flex items-center gap-1 rounded-md border px-2 py-1 text-xs font-semibold" @click="editSystem(system)"><Pencil class="h-3.5 w-3.5" />Sửa</button>
                    <button class="inline-flex items-center gap-1 rounded-md border px-2 py-1 text-xs font-semibold" @click="monitor(system)"><Activity class="h-3.5 w-3.5" />Theo dõi</button>
                    <button class="inline-flex items-center gap-1 rounded-md border px-2 py-1 text-xs font-semibold" @click="testSystem(system)"><FlaskConical class="h-3.5 w-3.5" />Kiểm tra</button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </section>
        <section class="overflow-x-auto rounded-md border border-slate-200 bg-white">
          <table class="min-w-full text-left text-sm">
            <thead class="bg-slate-50 text-xs uppercase text-slate-500"><tr><th class="px-3 py-2">Endpoint</th><th class="px-3 py-2">Phương thức</th><th class="px-3 py-2">Đường dẫn</th><th class="px-3 py-2">Trạng thái</th><th class="px-3 py-2">Thao tác</th></tr></thead>
            <tbody class="divide-y divide-slate-100">
              <tr v-for="endpoint in endpoints" :key="endpoint.id">
                <td class="px-3 py-2 font-semibold">{{ endpoint.code }}</td>
                <td class="px-3 py-2 font-mono text-xs">{{ endpoint.method }}</td>
                <td class="px-3 py-2">{{ endpoint.path }}</td>
                <td class="px-3 py-2"><ApiHealthBadge :status="endpoint.status" /></td>
                <td class="px-3 py-2">
                  <div class="flex flex-wrap gap-2">
                    <button class="inline-flex items-center gap-1 rounded-md border px-2 py-1 text-xs font-semibold" @click="showDetail(endpoint, 'endpoint')"><Eye class="h-3.5 w-3.5" />Mở chi tiết</button>
                    <button class="inline-flex items-center gap-1 rounded-md border px-2 py-1 text-xs font-semibold" @click="editEndpoint(endpoint)"><Pencil class="h-3.5 w-3.5" />Sửa</button>
                    <button class="inline-flex items-center gap-1 rounded-md border px-2 py-1 text-xs font-semibold" @click="monitor(endpoint)"><Activity class="h-3.5 w-3.5" />Theo dõi</button>
                    <button class="inline-flex items-center gap-1 rounded-md border px-2 py-1 text-xs font-semibold" @click="testEndpoint(endpoint)"><FlaskConical class="h-3.5 w-3.5" />Kiểm tra</button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </section>
      </div>
    </div>
    <div v-if="selectedDetail" class="fixed inset-0 z-50 flex justify-end bg-slate-950/40" @click.self="selectedDetail = null">
      <aside class="h-full w-full max-w-2xl overflow-auto bg-white">
        <header class="sticky top-0 z-10 flex items-center justify-between gap-3 border-b bg-white px-4 py-3">
          <div>
            <div class="text-xs font-semibold uppercase text-slate-500">Thông tin chi tiết</div>
            <h2 class="mt-1 text-base font-bold">{{ detailTitle }}</h2>
          </div>
          <button class="rounded-md border px-3 py-2 text-sm font-semibold" @click="selectedDetail = null">Đóng</button>
        </header>
        <div class="p-4">
          <PayloadViewer :title="selectedDetailType === 'system' ? 'Chi tiết hệ thống' : 'Chi tiết endpoint'" :payload="selectedDetail" />
        </div>
      </aside>
    </div>
  </ApiOpsShell>
</template>
