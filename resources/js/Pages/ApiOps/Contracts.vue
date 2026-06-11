<script setup>
import { inject, onMounted, reactive, ref } from 'vue'
import { Activity, CheckCircle2, Eye, Pencil } from '@lucide/vue'
import ApiOpsShell from '@/Components/ApiOps/ApiOpsShell.vue'
import ApiHealthBadge from '@/Components/ApiOps/ApiHealthBadge.vue'
import JsonSchemaEditor from '@/Components/ApiOps/JsonSchemaEditor.vue'
import DataContractVersionDiff from '@/Components/ApiOps/DataContractVersionDiff.vue'
import PayloadViewer from '@/Components/ApiOps/PayloadViewer.vue'

const props = defineProps({
  apiHeaders: { type: Object, required: true },
  sessionUser: { type: Object, default: null },
})

const systems = ref([])
const contracts = ref([])
const notice = ref('')
const error = ref('')
const selected = ref(null)
const editingContractId = ref(null)
const defaultForm = () => ({ system_id: '', entity_type: 'student', version: 'v2', schema: { required: ['id', 'code'], properties: { id: { type: 'string' }, code: { type: 'string' } } } })
const form = reactive(defaultForm())
const navigateTo = inject('navigateTo', (url) => { window.location.href = url })

async function load() {
  const [optionsResponse, contractsResponse] = await Promise.all([
    fetch('/api/v1/api-ops/options?include=systems', { headers: props.apiHeaders }),
    fetch('/api/v1/api-ops/contracts?per_page=100', { headers: props.apiHeaders }),
  ])
  systems.value = (await optionsResponse.json()).data?.systems || []
  contracts.value = (await contractsResponse.json()).data?.data || []
  form.system_id ||= systems.value[0]?.id || ''
}

async function createContract() {
  notice.value = ''
  error.value = ''
  const url = editingContractId.value ? `/api/v1/api-ops/contracts/${editingContractId.value}` : '/api/v1/api-ops/contracts'
  const method = editingContractId.value ? 'PUT' : 'POST'
  try {
    const response = await fetch(url, { method, headers: props.apiHeaders, body: JSON.stringify(form) })
    const data = await response.json()
    if (!response.ok || data.success === false) throw new Error(data.message || 'Không lưu được contract.')
    notice.value = editingContractId.value ? 'Đã cập nhật contract.' : 'Đã lưu contract.'
    resetForm()
    await load()
  } catch (exception) {
    error.value = exception.message
  }
}

async function activate(contract) {
  const response = await fetch(`/api/v1/api-ops/contracts/${contract.id}/activate`, { method: 'POST', headers: props.apiHeaders })
  await response.json()
  notice.value = 'Đã kích hoạt contract.'
  await load()
}

function editContract(contract) {
  editingContractId.value = contract.id
  Object.assign(form, {
    system_id: contract.system_id || systems.value[0]?.id || '',
    entity_type: contract.entity_type || '',
    version: contract.version || '',
    schema: contract.schema || {},
  })
}

function resetForm() {
  editingContractId.value = null
  Object.assign(form, defaultForm(), { system_id: systems.value[0]?.id || '' })
}

function monitorContract(contract) {
  const suffix = contract.system_id ? `&system_id=${encodeURIComponent(contract.system_id)}` : ''
  navigateTo(`/admin/api-ops/health?source=contract${suffix}`)
}

onMounted(load)
</script>

<template>
  <ApiOpsShell :session-user="sessionUser" title="Hợp đồng dữ liệu" subtitle="Quản lý schema theo loại dữ liệu, phiên bản contract, kích hoạt/ngừng dùng, so sánh phiên bản và nền tảng validate payload.">
    <div v-if="notice" class="rounded-md border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-700">{{ notice }}</div>
    <div v-if="error" class="rounded-md border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-700">{{ error }}</div>
    <div class="grid gap-4 xl:grid-cols-[420px_1fr]">
      <section class="rounded-md border border-slate-200 bg-white p-4">
        <div class="flex items-center justify-between gap-2">
          <h2 class="text-sm font-semibold">{{ editingContractId ? 'Sửa contract' : 'Tạo contract' }}</h2>
          <button v-if="editingContractId" class="rounded-md border px-2 py-1 text-xs font-semibold text-slate-700" @click="resetForm">Hủy sửa</button>
        </div>
        <div class="mt-3 grid gap-2">
          <select v-model="form.system_id" class="h-10 rounded-md border px-2 text-sm"><option v-for="system in systems" :key="system.id" :value="system.id">{{ system.code }}</option></select>
          <input v-model="form.entity_type" class="h-10 rounded-md border px-2 text-sm" placeholder="Loại dữ liệu" />
          <input v-model="form.version" class="h-10 rounded-md border px-2 text-sm" placeholder="Phiên bản" />
          <JsonSchemaEditor v-model="form.schema" />
          <button class="rounded-md bg-slate-950 px-3 py-2 text-sm font-semibold text-white" @click="createContract">{{ editingContractId ? 'Cập nhật contract' : 'Lưu contract' }}</button>
        </div>
      </section>
      <DataContractVersionDiff :left="contracts[0]?.schema || {}" :right="form.schema" />
    </div>
    <section class="overflow-x-auto rounded-md border border-slate-200 bg-white">
      <table class="min-w-full text-left text-sm">
        <thead class="bg-slate-50 text-xs uppercase text-slate-500"><tr><th class="px-3 py-2">Loại dữ liệu</th><th class="px-3 py-2">Phiên bản</th><th class="px-3 py-2">Trạng thái</th><th class="px-3 py-2">Hiệu lực từ</th><th class="px-3 py-2">Thao tác</th></tr></thead>
        <tbody class="divide-y divide-slate-100">
          <tr v-for="contract in contracts" :key="contract.id">
            <td class="px-3 py-2">{{ contract.entity_type }}</td>
            <td class="px-3 py-2 font-mono text-xs">{{ contract.version }}</td>
            <td class="px-3 py-2"><ApiHealthBadge :status="contract.status" /></td>
            <td class="px-3 py-2">{{ contract.effective_from || '-' }}</td>
            <td class="px-3 py-2">
              <div class="flex flex-wrap gap-2">
                <button class="inline-flex items-center gap-1 rounded-md border px-2 py-1 text-xs font-semibold" @click="selected = contract"><Eye class="h-3.5 w-3.5" />Mở chi tiết</button>
                <button class="inline-flex items-center gap-1 rounded-md border px-2 py-1 text-xs font-semibold" @click="editContract(contract)"><Pencil class="h-3.5 w-3.5" />Sửa</button>
                <button class="inline-flex items-center gap-1 rounded-md border px-2 py-1 text-xs font-semibold" @click="monitorContract(contract)"><Activity class="h-3.5 w-3.5" />Theo dõi</button>
                <button class="inline-flex items-center gap-1 rounded-md border px-2 py-1 text-xs font-semibold" @click="activate(contract)"><CheckCircle2 class="h-3.5 w-3.5" />Kích hoạt</button>
              </div>
            </td>
          </tr>
          <tr v-if="!contracts.length">
            <td colspan="5" class="px-3 py-8 text-center text-sm text-slate-500">Chưa có data contract.</td>
          </tr>
        </tbody>
      </table>
    </section>
    <div v-if="selected" class="fixed inset-0 z-50 flex justify-end bg-slate-950/40" @click.self="selected = null">
      <aside class="h-full w-full max-w-2xl overflow-auto bg-white">
        <header class="sticky top-0 z-10 flex items-center justify-between gap-3 border-b bg-white px-4 py-3">
          <div>
            <div class="text-xs font-semibold uppercase text-slate-500">Thông tin chi tiết</div>
            <h2 class="mt-1 text-base font-bold">{{ selected.entity_type }} · {{ selected.version }}</h2>
          </div>
          <button class="rounded-md border px-3 py-2 text-sm font-semibold" @click="selected = null">Đóng</button>
        </header>
        <div class="grid gap-4 p-4">
          <PayloadViewer title="Chi tiết contract" :payload="selected" />
          <PayloadViewer title="Schema" :payload="selected.schema || {}" />
        </div>
      </aside>
    </div>
  </ApiOpsShell>
</template>
