<script setup>
import { computed, ref } from 'vue'
import EraLmsLayout from '@/Layouts/EraLmsLayout.vue'

const props = defineProps({
  apiHeaders: { type: Object, default: () => ({}) },
})

const file = ref(null)
const format = ref('json')
const message = ref('')
const importing = ref(false)
const job = ref(null)
const previewRows = computed(() => file.value
  ? [
      `File: ${file.value.name}`,
      `Kích thước: ${(file.value.size / 1024).toFixed(1)} KB`,
      `Format: ${format.value.toUpperCase()}`,
    ]
  : ['Chọn file JSON, CSV, XLSX, GIFT hoặc QTI để xem preview import.'])

function onFile(event) {
  file.value = event.target.files?.[0] || null
  if (file.value) {
    const ext = file.value.name.split('.').pop()?.toLowerCase()
    if (['json', 'csv', 'xlsx', 'gift', 'qti'].includes(ext)) format.value = ext
    message.value = `Đã chọn ${file.value.name}.`
  }
}

async function startImport() {
  if (!file.value) {
    message.value = 'Chọn file trước khi import.'
    return
  }

  importing.value = true
  try {
    const form = new FormData()
    form.append('file', file.value)
    form.append('format', format.value)
    const headers = { ...props.apiHeaders }
    delete headers['Content-Type']
    const response = await fetch('/api/v1/question-imports', {
      method: 'POST',
      headers,
      body: form,
    })
    const data = await response.json().catch(() => ({}))
    if (!response.ok) throw new Error(data.message || 'Không tạo được job import.')
    job.value = data.data || data
    message.value = `Đã tạo job import #${job.value.id}.`
  } catch (error) {
    message.value = error.message
  } finally {
    importing.value = false
  }
}
</script>

<template>
  <EraLmsLayout>
    <template #breadcrumb>Ngân hàng câu hỏi / Import</template>
    <section class="mx-auto grid max-w-6xl gap-4 px-6 py-5 lg:grid-cols-[1fr_340px]">
      <main class="border bg-white p-4">
        <h1 class="text-lg font-semibold">Import câu hỏi</h1>
        <p class="mt-1 text-sm text-slate-600">Chọn file và gửi vào API import job.</p>
        <div v-if="message" class="mt-4 rounded-md border border-cyan-200 bg-cyan-50 px-4 py-3 text-sm text-cyan-900">{{ message }}</div>
        <div class="mt-4 grid gap-3 md:grid-cols-2">
          <input type="file" class="rounded-md border px-3 py-2 text-sm" accept=".json,.csv,.xlsx,.gift,.qti" @change="onFile" />
          <select v-model="format" class="rounded-md border px-3 py-2 text-sm"><option value="json">JSON</option><option value="csv">CSV</option><option value="xlsx">XLSX</option><option value="gift">GIFT</option><option value="qti">QTI</option></select>
        </div>
        <h2 class="mt-6 text-sm font-semibold">Preview dữ liệu</h2>
        <div class="mt-3 space-y-2 text-sm">
          <div v-for="row in previewRows" :key="row" class="rounded-md bg-slate-50 p-3">{{ row }}</div>
        </div>
      </main>
      <aside class="border bg-white p-4">
        <h2 class="text-sm font-semibold">Báo lỗi import</h2>
        <p class="mt-2 text-sm text-slate-600">Các dòng thiếu đáp án đúng, sai loại câu hỏi hoặc thiếu CLO/PLO sẽ được ghi vào job import để tải báo cáo lỗi.</p>
        <button class="mt-4 w-full rounded-md bg-slate-950 px-3 py-2 text-sm text-white disabled:opacity-50" :disabled="importing" @click="startImport">{{ importing ? 'Đang import...' : 'Bắt đầu import' }}</button>
        <div v-if="job" class="mt-4 rounded-md bg-slate-50 p-3 text-sm">
          <div class="font-semibold">Job #{{ job.id }}</div>
          <div class="mt-3 grid grid-cols-2 gap-2">
            <div class="rounded-md border bg-white p-2"><div class="text-xs text-slate-500">Trạng thái</div><div class="font-semibold">{{ job.status || 'queued' }}</div></div>
            <div class="rounded-md border bg-white p-2"><div class="text-xs text-slate-500">Format</div><div class="font-semibold">{{ job.format || format.toUpperCase() }}</div></div>
            <div class="rounded-md border bg-white p-2"><div class="text-xs text-slate-500">Tổng dòng</div><div class="font-semibold">{{ job.total_rows ?? '-' }}</div></div>
            <div class="rounded-md border bg-white p-2"><div class="text-xs text-slate-500">Lỗi</div><div class="font-semibold">{{ job.failed_rows ?? 0 }}</div></div>
          </div>
          <div v-if="job.error_report_path" class="mt-3 rounded-md border border-amber-200 bg-amber-50 p-2 text-amber-800">Có báo cáo lỗi import cần xử lý.</div>
        </div>
      </aside>
    </section>
  </EraLmsLayout>
</template>
