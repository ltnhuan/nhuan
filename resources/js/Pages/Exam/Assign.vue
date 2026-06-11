<script setup>
import { computed, onMounted, ref } from 'vue'
import EraLmsLayout from '@/Layouts/EraLmsLayout.vue'

const props = defineProps({
  apiHeaders: { type: Object, required: true },
  sessionUser: { type: Object, default: null },
})

const exams = ref([])
const sections = ref([])
const form = ref({ exam_id: '', class_id: '', available_from: '', available_until: '' })
const loading = ref(true)
const saving = ref(false)
const error = ref('')
const message = ref('')

const selectedExam = computed(() => exams.value.find((exam) => String(exam.id) === String(form.value.exam_id)) || null)
const selectedSection = computed(() => sections.value.find((section) => String(section.id) === String(form.value.class_id)) || null)

async function load() {
  loading.value = true
  error.value = ''
  try {
    const [examResponse, sectionResponse] = await Promise.all([
      fetch('/api/v1/exams?per_page=100', { headers: props.apiHeaders }),
      fetch('/api/v1/enrollment/sections?per_page=100', { headers: props.apiHeaders }),
    ])
    const examData = await examResponse.json()
    const sectionData = await sectionResponse.json()
    if (!examResponse.ok) throw new Error(examData.message || 'Không tải được bài kiểm tra.')
    if (!sectionResponse.ok) throw new Error(sectionData.message || 'Không tải được lớp học.')
    exams.value = examData.data || []
    sections.value = sectionData.data || []
    form.value.exam_id = exams.value[0]?.id || ''
    form.value.class_id = sections.value[0]?.id || ''
  } catch (exception) {
    error.value = exception.message
  } finally {
    loading.value = false
  }
}

async function assignClass() {
  if (!form.value.exam_id || !form.value.class_id) return
  saving.value = true
  error.value = ''
  message.value = ''
  try {
    const response = await fetch(`/api/v1/exams/${form.value.exam_id}/assign-class`, {
      method: 'POST',
      headers: props.apiHeaders,
      body: JSON.stringify({
        class_id: form.value.class_id,
        available_from: form.value.available_from || null,
        available_until: form.value.available_until || null,
      }),
    })
    const data = await response.json()
    if (!response.ok) throw new Error(data.message || 'Không gán được bài kiểm tra.')
    message.value = `Đã gán ${data.assigned || 0} học viên.`
  } catch (exception) {
    error.value = exception.message
  } finally {
    saving.value = false
  }
}

onMounted(load)
</script>

<template>
  <EraLmsLayout :session-user="sessionUser">
    <template #breadcrumb>Kiểm tra online / Gán bài</template>
    <section class="mx-auto grid max-w-6xl gap-4 px-4 py-5 sm:px-6 lg:grid-cols-[1fr_340px]">
      <main class="border bg-white p-4">
        <div class="flex flex-wrap items-center justify-between gap-3">
          <h1 class="text-lg font-semibold">Gán bài kiểm tra</h1>
          <button class="rounded-md border px-3 py-2 text-sm" :disabled="loading" @click="load">Tải lại</button>
        </div>
        <div v-if="error" class="mt-4 rounded-md border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">{{ error }}</div>
        <div v-if="message" class="mt-4 rounded-md bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ message }}</div>
        <div v-if="loading" class="mt-4 rounded-md bg-slate-50 p-4 text-sm text-slate-500">Đang tải dữ liệu...</div>
        <div v-else class="mt-4 grid gap-3 md:grid-cols-2">
          <label class="text-sm"><span class="text-xs font-semibold uppercase text-slate-500">Bài kiểm tra</span><select v-model="form.exam_id" class="mt-1 w-full rounded-md border px-3 py-2 text-sm"><option value="">Chọn bài kiểm tra</option><option v-for="exam in exams" :key="exam.id" :value="exam.id">{{ exam.code }} - {{ exam.title }}</option></select></label>
          <label class="text-sm"><span class="text-xs font-semibold uppercase text-slate-500">Lớp</span><select v-model="form.class_id" class="mt-1 w-full rounded-md border px-3 py-2 text-sm"><option value="">Chọn lớp</option><option v-for="section in sections" :key="section.id" :value="section.id">{{ section.code || section.id }} - {{ section.name || section.title }}</option></select></label>
        </div>
        <div v-if="selectedExam || selectedSection" class="mt-4 rounded-md border p-3 text-sm">
          <div v-if="selectedExam" class="font-semibold">{{ selectedExam.title }}</div>
          <div v-if="selectedSection" class="mt-1 text-slate-600">{{ selectedSection.name || selectedSection.title }} · {{ selectedSection.enrollments_count ?? 0 }} học viên</div>
        </div>
      </main>
      <aside class="border bg-white p-4">
        <h2 class="text-sm font-semibold">Cửa sổ làm bài</h2>
        <input v-model="form.available_from" class="mt-3 w-full rounded-md border px-3 py-2 text-sm" type="datetime-local" />
        <input v-model="form.available_until" class="mt-3 w-full rounded-md border px-3 py-2 text-sm" type="datetime-local" />
        <button class="mt-4 w-full rounded-md bg-slate-950 px-3 py-2 text-sm text-white" :disabled="saving || !form.exam_id || !form.class_id" @click="assignClass">Xác nhận gán</button>
      </aside>
    </section>
  </EraLmsLayout>
</template>
