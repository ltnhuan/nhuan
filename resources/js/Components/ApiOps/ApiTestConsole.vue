<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { Play, Save } from '@lucide/vue'
import PayloadViewer from './PayloadViewer.vue'

const props = defineProps({
  apiHeaders: { type: Object, required: true },
})

const systems = ref([])
const endpoints = ref([])
const systemId = ref('')
const endpointId = ref('')
const headersText = ref('{"x-test":"api-ops"}')
const bodyText = ref('{"id":"sample-1"}')
const result = ref(null)
const error = ref('')
const loading = ref(false)

const filteredEndpoints = computed(() => endpoints.value.filter((endpoint) => !systemId.value || Number(endpoint.system_id) === Number(systemId.value)))
const selectedEndpoint = computed(() => endpoints.value.find((endpoint) => Number(endpoint.id) === Number(endpointId.value)) || null)

watch(systemId, () => {
  if (!filteredEndpoints.value.some((endpoint) => Number(endpoint.id) === Number(endpointId.value))) {
    endpointId.value = filteredEndpoints.value[0]?.id || ''
  }
})

async function loadOptions() {
  const response = await fetch('/api/v1/api-ops/options?include=systems,endpoints', { headers: props.apiHeaders })
  const json = await response.json()
  systems.value = json.data?.systems || []
  endpoints.value = json.data?.endpoints || []
  systemId.value = systems.value[0]?.id || ''
  endpointId.value = endpoints.value[0]?.id || ''
}

async function run() {
  loading.value = true
  error.value = ''
  result.value = null
  try {
    const response = await fetch('/api/v1/api-ops/console/test-request', {
      method: 'POST',
      headers: props.apiHeaders,
      body: JSON.stringify({
        endpoint_id: endpointId.value,
        headers: JSON.parse(headersText.value || '{}'),
        body: JSON.parse(bodyText.value || '{}'),
      }),
    })
    const data = await response.json()
    if (!response.ok || data.success === false) throw new Error(data.message || 'Không gửi được request test.')
    result.value = data.data
  } catch (exception) {
    error.value = exception.message
  } finally {
    loading.value = false
  }
}

function loadSample() {
  const endpoint = selectedEndpoint.value
  headersText.value = JSON.stringify({
    'x-test': 'api-ops',
    'x-endpoint-code': endpoint?.code || 'SAMPLE_ENDPOINT',
  }, null, 2)
  bodyText.value = JSON.stringify({
    id: 'sample-1',
    code: 'SV001',
    endpoint: endpoint?.path || '/health',
  }, null, 2)
  result.value = null
  error.value = ''
}

onMounted(loadOptions)
</script>

<template>
  <section class="rounded-md border border-slate-200 bg-white p-4">
    <div class="flex flex-wrap items-center justify-between gap-3">
      <h2 class="text-sm font-semibold">Console kiểm thử API</h2>
      <div class="flex gap-2">
        <button class="inline-flex items-center gap-2 rounded-md border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50" @click="loadSample">
          <Save class="h-4 w-4" />
          Tạo mẫu
        </button>
        <button class="inline-flex items-center gap-2 rounded-md bg-slate-950 px-3 py-2 text-sm font-semibold text-white hover:bg-slate-800 disabled:opacity-50" :disabled="loading || !endpointId" @click="run">
          <Play class="h-4 w-4" />
          {{ loading ? 'Đang chạy' : 'Gửi request test' }}
        </button>
      </div>
    </div>

    <div v-if="error" class="mt-3 rounded-md border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-700">{{ error }}</div>

    <div class="mt-4 grid gap-4 lg:grid-cols-[360px_1fr]">
      <aside class="space-y-3">
        <label class="block">
          <span class="text-xs font-semibold uppercase text-slate-500">Hệ thống</span>
          <select v-model="systemId" class="mt-1 h-10 w-full rounded-md border border-slate-300 bg-white px-2 text-sm">
            <option v-for="system in systems" :key="system.id" :value="system.id">{{ system.code }} · {{ system.environment }}</option>
          </select>
        </label>
        <label class="block">
          <span class="text-xs font-semibold uppercase text-slate-500">Endpoint</span>
          <select v-model="endpointId" class="mt-1 h-10 w-full rounded-md border border-slate-300 bg-white px-2 text-sm">
            <option v-for="endpoint in filteredEndpoints" :key="endpoint.id" :value="endpoint.id">{{ endpoint.method }} {{ endpoint.path }}</option>
          </select>
        </label>
        <label class="block">
          <span class="text-xs font-semibold uppercase text-slate-500">Header</span>
          <textarea v-model="headersText" rows="7" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 font-mono text-xs"></textarea>
        </label>
      </aside>
      <div class="grid gap-4">
        <label class="block">
          <span class="text-xs font-semibold uppercase text-slate-500">Body request</span>
          <textarea v-model="bodyText" rows="10" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 font-mono text-xs"></textarea>
        </label>
        <PayloadViewer v-if="result" title="Kết quả phản hồi" :payload="result" />
      </div>
    </div>
  </section>
</template>
