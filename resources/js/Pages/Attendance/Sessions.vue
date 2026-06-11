<script setup>
import { computed, onMounted, ref } from 'vue'
import EraLmsLayout from '@/Layouts/EraLmsLayout.vue'

const props = defineProps({
  apiHeaders: { type: Object, required: true },
  sessionUser: { type: Object, default: null },
})

const sessions = ref([])
const selectedId = ref('')
const loading = ref(true)
const error = ref('')
const methods = ['QR', 'OTP', 'Manual', 'Duration', 'Auto']
const selected = computed(() => sessions.value.find((item) => String(item.id) === String(selectedId.value)) || sessions.value[0] || null)

async function load() {
  loading.value = true
  error.value = ''
  try {
    const response = await fetch('/api/v1/attendance-sessions?per_page=100', { headers: props.apiHeaders })
    const data = await response.json()
    if (!response.ok) throw new Error(data.message || 'Không tải được phiên điểm danh.')
    sessions.value = data.data || []
    selectedId.value = selected.value?.id || ''
  } catch (exception) {
    error.value = exception.message
  } finally {
    loading.value = false
  }
}

async function run(action) {
  if (!selected.value) return
  error.value = ''
  try {
    const response = await fetch(`/api/v1/attendance-sessions/${selected.value.id}/${action}`, { method: 'POST', headers: props.apiHeaders })
    const data = await response.json()
    if (!response.ok) throw new Error(data.message || `Không thể ${action} phiên điểm danh.`)
    await load()
  } catch (exception) {
    error.value = exception.message
  }
}

onMounted(load)
</script>

<template>
  <EraLmsLayout :session-user="sessionUser">
    <template #breadcrumb>Điểm danh online / Phiên điểm danh</template>
    <section class="mx-auto grid max-w-7xl gap-4 px-4 py-5 sm:px-6 lg:grid-cols-[360px_1fr]">
      <aside class="border bg-white p-4">
        <h1 class="text-lg font-semibold">Phiên điểm danh</h1>
        <div v-if="error" class="mt-4 rounded-md border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-800">{{ error }}</div>
        <div v-if="loading" class="mt-4 rounded-md bg-slate-50 p-4 text-sm text-slate-500">Đang tải dữ liệu...</div>
        <div v-else-if="!sessions.length" class="mt-4 rounded-md bg-slate-50 p-4 text-sm text-slate-500">Chưa có phiên điểm danh.</div>
        <div v-else class="mt-4 space-y-3 text-sm">
          <select v-model="selectedId" class="w-full rounded-md border px-3 py-2"><option v-for="session in sessions" :key="session.id" :value="session.id">{{ session.title }}</option></select>
          <input class="w-full rounded-md border px-3 py-2" readonly :value="selected?.method" />
          <input class="w-full rounded-md border px-3 py-2" readonly :value="`${selected?.open_at || '-'} - ${selected?.close_at || '-'}`" />
          <div class="grid grid-cols-3 gap-2">
            <button class="rounded-md border py-2" @click="run('open')">Mở</button><button class="rounded-md border py-2" @click="run('close')">Đóng</button><button class="rounded-md bg-slate-950 py-2 text-white" @click="run('lock')">Khóa</button>
          </div>
        </div>
        <div class="mt-5 grid aspect-square place-items-center border bg-slate-50 break-all p-4 font-mono text-xs">{{ selected?.qr_token || 'QR token chưa có' }}</div>
        <div class="mt-3 rounded-md border p-3 text-center text-2xl font-semibold">{{ selected?.otp_code || '------' }}</div>
      </aside>
      <section class="border bg-white p-4">
        <h2 class="text-sm font-semibold">Cấu hình nhanh</h2>
        <div class="mt-4 grid gap-3 md:grid-cols-5"><button v-for="method in methods" :key="method" class="rounded-md border px-3 py-4 text-sm hover:bg-blue-50">{{ method }}</button></div>
        <div class="mt-5 overflow-hidden border">
          <table class="w-full text-left text-sm">
            <thead class="bg-slate-50 text-xs uppercase text-slate-500"><tr><th class="px-4 py-3">Phiên</th><th class="px-4 py-3">Loại</th><th class="px-4 py-3">Mở lúc</th><th class="px-4 py-3">Đóng lúc</th><th class="px-4 py-3">Trạng thái</th><th class="px-4 py-3">Số bản ghi</th></tr></thead>
            <tbody class="divide-y">
              <tr v-if="loading"><td class="px-4 py-6 text-slate-500" colspan="6">Đang tải dữ liệu...</td></tr>
              <tr v-else-if="!sessions.length"><td class="px-4 py-6 text-slate-500" colspan="6">Không có dữ liệu.</td></tr>
              <tr v-for="session in sessions" v-else :key="session.id"><td class="px-4 py-3 font-medium">{{ session.title }}</td><td class="px-4 py-3">{{ session.method }}</td><td class="px-4 py-3">{{ session.open_at }}</td><td class="px-4 py-3">{{ session.close_at || '-' }}</td><td class="px-4 py-3">{{ session.status }}</td><td class="px-4 py-3">{{ session.records_count ?? 0 }}</td></tr>
            </tbody>
          </table>
        </div>
      </section>
    </section>
  </EraLmsLayout>
</template>
