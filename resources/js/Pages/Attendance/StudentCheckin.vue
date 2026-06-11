<script setup>
import { computed, onMounted, ref } from 'vue'
import EraLmsLayout from '@/Layouts/EraLmsLayout.vue'

const props = defineProps({
  apiHeaders: { type: Object, required: true },
  sessionUser: { type: Object, default: null },
})

const sessions = ref([])
const selectedId = ref('')
const otp = ref('')
const qrToken = ref('')
const loading = ref(true)
const message = ref('')
const error = ref('')
const selected = computed(() => sessions.value.find((session) => String(session.id) === String(selectedId.value)) || sessions.value[0] || null)

async function load() {
  loading.value = true
  error.value = ''
  try {
    const response = await fetch('/api/v1/attendance-sessions?status=open&per_page=100', { headers: props.apiHeaders })
    const data = await response.json()
    if (!response.ok) throw new Error(data.message || 'Không tải được phiên đang mở.')
    sessions.value = data.data || []
    selectedId.value = selected.value?.id || ''
    otp.value = selected.value?.otp_code || ''
    qrToken.value = selected.value?.qr_token || ''
  } catch (exception) {
    error.value = exception.message
  } finally {
    loading.value = false
  }
}

async function submitOtp() {
  if (!selected.value) return
  await submit('/api/v1/attendance/checkin-otp', { attendance_session_id: selected.value.id, otp_code: otp.value })
}

async function submitQr() {
  await submit('/api/v1/attendance/checkin-qr', { qr_token: qrToken.value })
}

async function submit(url, payload) {
  error.value = ''
  message.value = ''
  try {
    const response = await fetch(url, { method: 'POST', headers: props.apiHeaders, body: JSON.stringify(payload) })
    const data = await response.json()
    if (!response.ok) throw new Error(data.message || 'Check-in không thành công.')
    message.value = `Đã điểm danh: ${data.status || data.attendance_status || 'present'}`
  } catch (exception) {
    error.value = exception.message
  }
}

onMounted(load)
</script>

<template>
  <EraLmsLayout :session-user="sessionUser">
    <template #breadcrumb>Điểm danh online / Điểm danh</template>
    <section class="mx-auto max-w-3xl px-4 py-5 sm:px-6">
      <div class="border bg-white p-5">
        <div class="flex flex-wrap items-center justify-between gap-3">
          <div><h1 class="text-lg font-semibold">Điểm danh sinh viên</h1><p class="mt-1 text-sm text-slate-600">Nhập OTP hoặc mã QR của phiên đang mở.</p></div>
          <button class="rounded-md border px-3 py-2 text-sm" :disabled="loading" @click="load">Tải lại</button>
        </div>
        <div v-if="error" class="mt-4 rounded-md border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">{{ error }}</div>
        <div v-if="message" class="mt-4 rounded-md bg-emerald-50 p-4 text-sm text-emerald-800">{{ message }}</div>
        <div v-if="loading" class="mt-5 rounded-md bg-slate-50 p-4 text-sm text-slate-500">Đang tải phiên đang mở...</div>
        <div v-else-if="!sessions.length" class="mt-5 rounded-md bg-slate-50 p-4 text-sm text-slate-500">Không có phiên điểm danh đang mở.</div>
        <div v-else class="mt-5 grid gap-4 md:grid-cols-2">
          <div class="border p-4">
            <h2 class="text-sm font-semibold">OTP</h2>
            <select v-model="selectedId" class="mt-3 w-full rounded-md border px-3 py-2 text-sm"><option v-for="session in sessions" :key="session.id" :value="session.id">{{ session.title }}</option></select>
            <input v-model="otp" class="mt-3 w-full rounded-md border px-3 py-3 text-center text-2xl font-semibold tracking-widest" />
            <button class="mt-3 w-full rounded-md bg-blue-900 px-3 py-2 text-sm text-white" @click="submitOtp">Gửi OTP</button>
          </div>
          <div class="border p-4">
            <h2 class="text-sm font-semibold">QR/Di động</h2>
            <textarea v-model="qrToken" rows="6" class="mt-3 w-full rounded-md border px-3 py-2 font-mono text-xs"></textarea>
            <button class="mt-3 w-full rounded-md border px-3 py-2 text-sm" @click="submitQr">Điểm danh bằng mã QR</button>
          </div>
        </div>
      </div>
    </section>
  </EraLmsLayout>
</template>
