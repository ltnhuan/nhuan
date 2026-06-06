<script setup>
import { computed, onMounted, ref } from 'vue'
import EraLmsLayout from '@/Layouts/EraLmsLayout.vue'

const props = defineProps({
  apiHeaders: { type: Object, required: true },
  sessionUser: { type: Object, default: null },
})

const exams = ref([])
const results = ref([])
const selectedExamId = ref('')
const loading = ref(true)
const error = ref('')

const selectedExam = computed(() => exams.value.find((exam) => String(exam.id) === String(selectedExamId.value)) || null)

async function loadExams() {
  const response = await fetch('/api/v1/exams?per_page=100', { headers: props.apiHeaders })
  const data = await response.json()
  if (!response.ok) throw new Error(data.message || 'Không tải được danh sách bài kiểm tra.')
  exams.value = data.data || []
  selectedExamId.value = exams.value[0]?.id || ''
}

async function loadResults() {
  results.value = []
  if (!selectedExamId.value) return

  const response = await fetch(`/api/v1/exams/${selectedExamId.value}/results?per_page=100`, { headers: props.apiHeaders })
  const data = await response.json()
  if (!response.ok) throw new Error(data.message || 'Không tải được kết quả bài kiểm tra.')
  results.value = data.data || []
}

async function load() {
  loading.value = true
  error.value = ''
  try {
    if (!exams.value.length) await loadExams()
    await loadResults()
  } catch (exception) {
    error.value = exception.message
  } finally {
    loading.value = false
  }
}

onMounted(load)
</script>

<template>
  <EraLmsLayout :session-user="sessionUser">
    <template #breadcrumb>Kiểm tra online / Kết quả</template>
    <section class="mx-auto max-w-7xl px-4 py-5 sm:px-6">
      <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
          <h1 class="text-lg font-semibold">Kết quả bài kiểm tra</h1>
          <p class="mt-1 text-sm text-slate-600">Theo dõi kết quả đã chấm theo từng bài kiểm tra.</p>
        </div>
        <button class="rounded-md border px-4 py-2 text-sm" :disabled="loading" @click="load">Tải lại</button>
      </div>
      <div class="mt-4">
        <select v-model="selectedExamId" class="w-full rounded-md border px-3 py-2 text-sm md:w-96" @change="loadResults">
          <option value="">Chọn bài kiểm tra</option>
          <option v-for="exam in exams" :key="exam.id" :value="exam.id">{{ exam.code }} - {{ exam.title }}</option>
        </select>
      </div>
      <div v-if="error" class="mt-4 rounded-md border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">{{ error }}</div>
      <div class="mt-5 overflow-hidden border bg-white">
        <div v-if="selectedExam" class="border-b border-slate-200 px-4 py-3 text-sm text-slate-600">
          {{ selectedExam.title }} · {{ selectedExam.total_score }} điểm · pass {{ selectedExam.pass_score }}
        </div>
        <table class="w-full text-left text-sm">
          <thead class="bg-slate-50 text-xs uppercase text-slate-500">
            <tr><th class="px-4 py-3">Học viên</th><th class="px-4 py-3">Điểm</th><th class="px-4 py-3">Percent</th><th class="px-4 py-3">Pass</th><th class="px-4 py-3">Attempt</th><th class="px-4 py-3">Submitted</th></tr>
          </thead>
          <tbody class="divide-y">
            <tr v-if="loading"><td class="px-4 py-6 text-slate-500" colspan="6">Đang tải dữ liệu...</td></tr>
            <tr v-else-if="!results.length"><td class="px-4 py-6 text-slate-500" colspan="6">Chưa có kết quả cho bài kiểm tra này.</td></tr>
            <tr v-for="result in results" v-else :key="result.id">
              <td class="px-4 py-3">{{ result.user?.full_name || result.user_id }}</td>
              <td class="px-4 py-3">{{ result.score }} / {{ result.max_score }}</td>
              <td class="px-4 py-3">{{ result.percent ?? '-' }}%</td>
              <td class="px-4 py-3">{{ result.pass_status }}</td>
              <td class="px-4 py-3">{{ result.attempt?.attempt_no || '-' }}</td>
              <td class="px-4 py-3">{{ result.attempt?.submitted_at || result.submitted_at || '-' }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>
  </EraLmsLayout>
</template>
