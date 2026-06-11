<script setup>
import { inject, onMounted, ref } from 'vue'
import { Activity, Ban, CheckCircle2, Eye } from '@lucide/vue'
import ApiOpsShell from '@/Components/ApiOps/ApiOpsShell.vue'
import ApiHealthBadge from '@/Components/ApiOps/ApiHealthBadge.vue'
import PayloadViewer from '@/Components/ApiOps/PayloadViewer.vue'

const props = defineProps({
  apiHeaders: { type: Object, required: true },
  sessionUser: { type: Object, default: null },
})

const mappings = ref([])
const notice = ref('')
const selected = ref(null)
const navigateTo = inject('navigateTo', (url) => { window.location.href = url })

async function load() {
  const response = await fetch('/api/v1/api-ops/entity-mappings?per_page=100', { headers: props.apiHeaders })
  mappings.value = (await response.json()).data?.data || []
}

function monitorMapping(mapping) {
  const systemId = mapping.system_id || mapping.source_system_id || mapping.target_system_id
  const suffix = systemId ? `&system_id=${encodeURIComponent(systemId)}` : ''
  navigateTo(`/admin/api-ops/health?source=entity-mapping${suffix}`)
}

async function resolve(mapping, action = 'activate') {
  const response = await fetch('/api/v1/api-ops/entity-mappings/resolve-conflict', { method: 'POST', headers: props.apiHeaders, body: JSON.stringify({ mapping_id: mapping.id, action }) })
  await response.json()
  notice.value = action === 'activate' ? 'Đã xử lý ánh xạ định danh.' : 'Đã bỏ qua ánh xạ định danh.'
  await load()
}

onMounted(load)
</script>

<template>
  <ApiOpsShell :session-user="sessionUser" title="Ánh xạ định danh" subtitle="Quản lý ID nội bộ và ID ngoài hệ thống, lọc xung đột, gộp trùng và xử lý mapping lỗi.">
    <div v-if="notice" class="rounded-md border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-700">{{ notice }}</div>
    <section class="overflow-x-auto rounded-md border border-slate-200 bg-white">
      <table class="min-w-full text-left text-sm">
        <thead class="bg-slate-50 text-xs uppercase text-slate-500"><tr><th class="px-3 py-2">Loại dữ liệu</th><th class="px-3 py-2">ID nội bộ</th><th class="px-3 py-2">ID ngoài</th><th class="px-3 py-2">Mã ngoài</th><th class="px-3 py-2">Trạng thái</th><th class="px-3 py-2">Thao tác</th></tr></thead>
        <tbody class="divide-y divide-slate-100">
          <tr v-for="mapping in mappings" :key="mapping.id">
            <td class="px-3 py-2">{{ mapping.entity_type }}</td>
            <td class="px-3 py-2 font-mono text-xs">{{ mapping.local_id }}</td>
            <td class="px-3 py-2 font-mono text-xs">{{ mapping.external_id }}</td>
            <td class="px-3 py-2">{{ mapping.external_code || '-' }}</td>
            <td class="px-3 py-2"><ApiHealthBadge :status="mapping.status" /></td>
            <td class="px-3 py-2">
              <div class="flex flex-wrap gap-2">
                <button class="inline-flex items-center gap-1 rounded-md border px-2 py-1 text-xs font-semibold" @click="selected = mapping"><Eye class="h-3.5 w-3.5" />Mở chi tiết</button>
                <button class="inline-flex items-center gap-1 rounded-md border px-2 py-1 text-xs font-semibold" @click="monitorMapping(mapping)"><Activity class="h-3.5 w-3.5" />Theo dõi</button>
                <button class="inline-flex items-center gap-1 rounded-md border px-2 py-1 text-xs font-semibold" @click="resolve(mapping, 'activate')"><CheckCircle2 class="h-3.5 w-3.5" />Xử lý</button>
                <button class="inline-flex items-center gap-1 rounded-md border px-2 py-1 text-xs font-semibold" @click="resolve(mapping, 'ignore')"><Ban class="h-3.5 w-3.5" />Bỏ qua</button>
              </div>
            </td>
          </tr>
          <tr v-if="!mappings.length">
            <td colspan="6" class="px-3 py-8 text-center text-sm text-slate-500">Chưa có ánh xạ định danh.</td>
          </tr>
        </tbody>
      </table>
    </section>
    <div v-if="selected" class="fixed inset-0 z-50 flex justify-end bg-slate-950/40" @click.self="selected = null">
      <aside class="h-full w-full max-w-2xl overflow-auto bg-white">
        <header class="sticky top-0 z-10 flex items-center justify-between gap-3 border-b bg-white px-4 py-3">
          <div>
            <div class="text-xs font-semibold uppercase text-slate-500">Thông tin chi tiết</div>
            <h2 class="mt-1 text-base font-bold">{{ selected.entity_type }} #{{ selected.local_id }}</h2>
          </div>
          <button class="rounded-md border px-3 py-2 text-sm font-semibold" @click="selected = null">Đóng</button>
        </header>
        <div class="p-4">
          <PayloadViewer title="Chi tiết ánh xạ định danh" :payload="selected" />
        </div>
      </aside>
    </div>
  </ApiOpsShell>
</template>
