<script setup>
import { inject, onMounted, ref } from 'vue'
import { Activity, Eye, Pencil } from '@lucide/vue'
import ApiOpsShell from '@/Components/ApiOps/ApiOpsShell.vue'
import MappingBuilder from '@/Components/ApiOps/MappingBuilder.vue'
import PayloadViewer from '@/Components/ApiOps/PayloadViewer.vue'

const props = defineProps({
  apiHeaders: { type: Object, required: true },
  sessionUser: { type: Object, default: null },
})

const systems = ref([])
const mappings = ref([])
const preview = ref(null)
const notice = ref('')
const error = ref('')
const selected = ref(null)
const editingMapping = ref(null)
const navigateTo = inject('navigateTo', (url) => { window.location.href = url })

async function load() {
  const [optionsResponse, mappingsResponse] = await Promise.all([
    fetch('/api/v1/api-ops/options?include=systems', { headers: props.apiHeaders }),
    fetch('/api/v1/api-ops/mappings?per_page=100', { headers: props.apiHeaders }),
  ])
  systems.value = (await optionsResponse.json()).data?.systems || []
  mappings.value = (await mappingsResponse.json()).data?.data || []
}

async function createMapping(form) {
  notice.value = ''
  error.value = ''
  const url = editingMapping.value ? `/api/v1/api-ops/mappings/${editingMapping.value.id}` : '/api/v1/api-ops/mappings'
  const method = editingMapping.value ? 'PUT' : 'POST'
  try {
    const response = await fetch(url, { method, headers: props.apiHeaders, body: JSON.stringify(form) })
    const data = await response.json()
    if (!response.ok || data.success === false) throw new Error(data.message || 'Không lưu được ánh xạ.')
    notice.value = editingMapping.value ? 'Đã cập nhật ánh xạ.' : 'Đã lưu ánh xạ.'
    editingMapping.value = null
    await load()
  } catch (exception) {
    error.value = exception.message
  }
}

async function validateMapping(form) {
  const response = await fetch('/api/v1/api-ops/mappings/validate', { method: 'POST', headers: props.apiHeaders, body: JSON.stringify(form) })
  preview.value = (await response.json()).data
}

function monitorMapping(mapping) {
  const systemId = mapping.target_system_id || mapping.source_system_id
  const suffix = systemId ? `&system_id=${encodeURIComponent(systemId)}` : ''
  navigateTo(`/admin/api-ops/health?source=mapping${suffix}`)
}

onMounted(load)
</script>

<template>
  <ApiOpsShell :session-user="sessionUser" title="Ánh xạ dữ liệu" subtitle="Thiết lập mapping trường dữ liệu, quy tắc chuyển đổi, xem trước payload và phát hiện lỗi bắt buộc.">
    <div v-if="notice" class="rounded-md border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-700">{{ notice }}</div>
    <div v-if="error" class="rounded-md border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-700">{{ error }}</div>
    <div class="grid gap-4 xl:grid-cols-[1fr_420px]">
      <MappingBuilder :systems="systems" :mapping="editingMapping" @create="createMapping" @validate="validateMapping" @cancel="editingMapping = null" />
      <PayloadViewer title="Payload xem trước" :payload="preview || {}" />
    </div>
    <section class="overflow-x-auto rounded-md border border-slate-200 bg-white">
      <table class="min-w-full text-left text-sm">
        <thead class="bg-slate-50 text-xs uppercase text-slate-500"><tr><th class="px-3 py-2">Loại dữ liệu</th><th class="px-3 py-2">Trường nguồn</th><th class="px-3 py-2">Trường đích</th><th class="px-3 py-2">Bắt buộc</th><th class="px-3 py-2">Quy tắc</th><th class="px-3 py-2">Thao tác</th></tr></thead>
        <tbody class="divide-y divide-slate-100">
          <tr v-for="mapping in mappings" :key="mapping.id">
            <td class="px-3 py-2">{{ mapping.entity_type }}</td>
            <td class="px-3 py-2 font-mono text-xs">{{ mapping.source_field }}</td>
            <td class="px-3 py-2 font-mono text-xs">{{ mapping.target_field }}</td>
            <td class="px-3 py-2">{{ mapping.is_required ? 'Có' : 'Không' }}</td>
            <td class="px-3 py-2">{{ mapping.transform_rule?.type || 'copy' }}</td>
            <td class="px-3 py-2">
              <div class="flex flex-wrap gap-2">
                <button class="inline-flex items-center gap-1 rounded-md border px-2 py-1 text-xs font-semibold" @click="selected = mapping"><Eye class="h-3.5 w-3.5" />Mở chi tiết</button>
                <button class="inline-flex items-center gap-1 rounded-md border px-2 py-1 text-xs font-semibold" @click="editingMapping = mapping"><Pencil class="h-3.5 w-3.5" />Sửa</button>
                <button class="inline-flex items-center gap-1 rounded-md border px-2 py-1 text-xs font-semibold" @click="monitorMapping(mapping)"><Activity class="h-3.5 w-3.5" />Theo dõi</button>
              </div>
            </td>
          </tr>
          <tr v-if="!mappings.length">
            <td colspan="6" class="px-3 py-8 text-center text-sm text-slate-500">Chưa có ánh xạ dữ liệu.</td>
          </tr>
        </tbody>
      </table>
    </section>
    <div v-if="selected" class="fixed inset-0 z-50 flex justify-end bg-slate-950/40" @click.self="selected = null">
      <aside class="h-full w-full max-w-2xl overflow-auto bg-white">
        <header class="sticky top-0 z-10 flex items-center justify-between gap-3 border-b bg-white px-4 py-3">
          <div>
            <div class="text-xs font-semibold uppercase text-slate-500">Thông tin chi tiết</div>
            <h2 class="mt-1 text-base font-bold">{{ selected.entity_type }} · {{ selected.source_field }} → {{ selected.target_field }}</h2>
          </div>
          <button class="rounded-md border px-3 py-2 text-sm font-semibold" @click="selected = null">Đóng</button>
        </header>
        <div class="p-4">
          <PayloadViewer title="Chi tiết ánh xạ" :payload="selected" />
        </div>
      </aside>
    </div>
  </ApiOpsShell>
</template>
