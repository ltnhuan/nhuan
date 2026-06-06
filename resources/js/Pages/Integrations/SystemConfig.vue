<script setup>
import { computed, onMounted, ref } from 'vue'
import EraLmsLayout from '@/Layouts/EraLmsLayout.vue'

const props = defineProps({
  apiHeaders: { type: Object, required: true },
  sessionUser: { type: Object, default: null },
})

const systems = ref([])
const selectedId = ref('')
const loading = ref(true)
const testing = ref(false)
const error = ref('')
const testResult = ref(null)

const selected = computed(() => systems.value.find((system) => String(system.id) === String(selectedId.value)) || systems.value[0] || null)

async function load() {
  loading.value = true
  error.value = ''
  try {
    const response = await fetch('/api/v1/integrations/systems?per_page=100', { headers: props.apiHeaders })
    const data = await response.json()
    if (!response.ok) throw new Error(data.message || 'Không tải được system config.')
    systems.value = data.data || []
    selectedId.value = selected.value?.id || ''
  } catch (exception) {
    error.value = exception.message
  } finally {
    loading.value = false
  }
}

async function testConnection() {
  if (!selected.value) return
  testing.value = true
  error.value = ''
  testResult.value = null
  try {
    const response = await fetch(`/api/v1/integrations/systems/${selected.value.id}/test-connection`, { method: 'POST', headers: props.apiHeaders })
    const data = await response.json()
    if (!response.ok) throw new Error(data.message || 'Không test được connection.')
    testResult.value = data
  } catch (exception) {
    error.value = exception.message
  } finally {
    testing.value = false
  }
}

onMounted(load)
</script>

<template>
  <EraLmsLayout :session-user="sessionUser">
    <template #breadcrumb>Integration Hub / System Config</template>
    <section class="mx-auto max-w-5xl px-4 py-5 sm:px-6">
      <div class="border bg-white p-5">
        <div class="flex flex-wrap items-center justify-between gap-3">
          <h1 class="text-lg font-semibold">System Config</h1>
          <div class="flex gap-2">
            <button class="rounded-md border px-3 py-2 text-sm" :disabled="loading" @click="load">Tải lại</button>
            <button class="rounded-md bg-slate-950 px-3 py-2 text-sm text-white" :disabled="testing || !selected" @click="testConnection">Test connection</button>
          </div>
        </div>
        <div v-if="error" class="mt-4 rounded-md border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">{{ error }}</div>
        <div v-if="loading" class="mt-5 rounded-md bg-slate-50 p-4 text-sm text-slate-500">Đang tải cấu hình...</div>
        <div v-else-if="!systems.length" class="mt-5 rounded-md bg-slate-50 p-4 text-sm text-slate-500">Chưa có integration system.</div>
        <div v-else class="mt-5 grid gap-3 md:grid-cols-2">
          <select v-model="selectedId" class="rounded-md border px-3 py-2 text-sm md:col-span-2">
            <option v-for="system in systems" :key="system.id" :value="system.id">{{ system.code }} - {{ system.name }}</option>
          </select>
          <label class="text-sm"><span class="text-xs font-semibold uppercase text-slate-500">Code</span><input class="mt-1 w-full rounded-md border px-3 py-2 text-sm" readonly :value="selected?.code" /></label>
          <label class="text-sm"><span class="text-xs font-semibold uppercase text-slate-500">Name</span><input class="mt-1 w-full rounded-md border px-3 py-2 text-sm" readonly :value="selected?.name" /></label>
          <label class="text-sm"><span class="text-xs font-semibold uppercase text-slate-500">Type</span><input class="mt-1 w-full rounded-md border px-3 py-2 text-sm" readonly :value="selected?.type" /></label>
          <label class="text-sm"><span class="text-xs font-semibold uppercase text-slate-500">Auth</span><input class="mt-1 w-full rounded-md border px-3 py-2 text-sm" readonly :value="selected?.auth_type" /></label>
          <label class="text-sm md:col-span-2"><span class="text-xs font-semibold uppercase text-slate-500">Base URL</span><input class="mt-1 w-full rounded-md border px-3 py-2 text-sm" readonly :value="selected?.base_url" /></label>
          <label class="text-sm"><span class="text-xs font-semibold uppercase text-slate-500">Status</span><input class="mt-1 w-full rounded-md border px-3 py-2 text-sm" readonly :value="selected?.status" /></label>
          <label class="text-sm"><span class="text-xs font-semibold uppercase text-slate-500">Last sync</span><input class="mt-1 w-full rounded-md border px-3 py-2 text-sm" readonly :value="selected?.last_sync_at || '-'" /></label>
        </div>
        <div v-if="testResult" class="mt-5 rounded-md bg-emerald-50 p-4 text-sm text-emerald-800">
          Connection result: {{ testResult.status || testResult.message || 'ok' }}
        </div>
      </div>
    </section>
  </EraLmsLayout>
</template>
