<script setup>
import { computed, onMounted, ref } from 'vue'
import EraLmsLayout from '@/Layouts/EraLmsLayout.vue'

const props = defineProps({
  apiHeaders: { type: Object, required: true },
  sessionUser: { type: Object, default: null },
})

const courses = ref([])
const selectedCourseId = ref('')
const rows = ref([])
const loading = ref(true)
const error = ref('')

const enough = computed(() => rows.value.filter((row) => Number(row.attendance_percent || 0) >= Number(row.required_percent || 80)).length)
const notEnough = computed(() => rows.value.length - enough.value)
const warning = computed(() => rows.value.filter((row) => {
  const percent = Number(row.attendance_percent || 0)
  const required = Number(row.required_percent || 80)
  return percent < required && percent >= required - 10
}).length)

async function loadCourses() {
  const response = await fetch('/api/v1/courses?per_page=100', { headers: props.apiHeaders })
  const data = await response.json()
  if (!response.ok) throw new Error(data.message || 'Không tải được khóa học.')
  courses.value = data.data || []
  selectedCourseId.value = courses.value[0]?.id || ''
}

async function loadSummary() {
  if (!selectedCourseId.value) {
    rows.value = []
    return
  }
  const response = await fetch(`/api/v1/courses/${selectedCourseId.value}/attendance-summary?per_page=200`, { headers: props.apiHeaders })
  const data = await response.json()
  if (!response.ok) throw new Error(data.message || 'Không tải được điều kiện dự thi.')
  rows.value = data.data || []
}

async function load() {
  loading.value = true
  error.value = ''
  try {
    if (!courses.value.length) await loadCourses()
    await loadSummary()
  } catch (exception) {
    error.value = exception.message
  } finally {
    loading.value = false
  }
}

async function recalculate() {
  if (!selectedCourseId.value) return
  error.value = ''
  try {
    const response = await fetch(`/api/v1/courses/${selectedCourseId.value}/recalculate-eligibility`, { method: 'POST', headers: props.apiHeaders })
    const data = await response.json()
    if (!response.ok) throw new Error(data.message || 'Không recalculate được điều kiện dự thi.')
    await loadSummary()
  } catch (exception) {
    error.value = exception.message
  }
}

onMounted(load)
</script>

<template>
  <EraLmsLayout :session-user="sessionUser">
    <template #breadcrumb>Điểm danh online / Eligibility</template>
    <section class="mx-auto max-w-7xl px-4 py-5 sm:px-6">
      <div class="flex flex-wrap items-center justify-between gap-3">
        <div><h1 class="text-lg font-semibold">Eligibility Dashboard</h1><p class="mt-1 text-sm text-slate-600">Điều kiện dự thi theo chuyên cần và dữ liệu điểm danh đã ghi nhận.</p></div>
        <div class="flex gap-2"><button class="rounded-md border px-3 py-2 text-sm" :disabled="loading || !selectedCourseId" @click="recalculate">Recalculate</button><button class="rounded-md bg-slate-950 px-3 py-2 text-sm text-white" :disabled="loading" @click="load">Tải lại</button></div>
      </div>
      <div class="mt-4">
        <select v-model="selectedCourseId" class="w-full rounded-md border px-3 py-2 text-sm md:w-96" @change="loadSummary">
          <option value="">Chọn khóa học</option><option v-for="course in courses" :key="course.id" :value="course.id">{{ course.code }} - {{ course.title }}</option>
        </select>
      </div>
      <div v-if="error" class="mt-4 rounded-md border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">{{ error }}</div>
      <div class="mt-5 grid gap-3 md:grid-cols-4">
        <div class="border bg-white p-4"><div class="text-sm text-slate-600">Rule</div><strong class="mt-2 block">Theo cấu hình course</strong></div>
        <div class="border bg-white p-4"><div class="text-sm text-slate-600">Đủ điều kiện</div><strong class="mt-2 block">{{ loading ? '...' : enough }}</strong></div>
        <div class="border bg-white p-4"><div class="text-sm text-slate-600">Cảnh báo</div><strong class="mt-2 block">{{ loading ? '...' : warning }}</strong></div>
        <div class="border bg-white p-4"><div class="text-sm text-slate-600">Không đủ</div><strong class="mt-2 block">{{ loading ? '...' : notEnough }}</strong></div>
      </div>
      <div class="mt-5 overflow-hidden border bg-white">
        <table class="w-full text-left text-sm">
          <thead class="bg-slate-50 text-xs uppercase text-slate-500"><tr><th class="px-4 py-3">User ID</th><th class="px-4 py-3">Chuyên cần</th><th class="px-4 py-3">Required</th><th class="px-4 py-3">Absent</th><th class="px-4 py-3">Dự thi</th></tr></thead>
          <tbody class="divide-y">
            <tr v-if="loading"><td class="px-4 py-6 text-slate-500" colspan="5">Đang tải dữ liệu...</td></tr>
            <tr v-else-if="!rows.length"><td class="px-4 py-6 text-slate-500" colspan="5">Không có dữ liệu eligibility cho khóa học này.</td></tr>
            <tr v-for="row in rows" v-else :key="row.id">
              <td class="px-4 py-3 font-medium">{{ row.user_id }}</td><td class="px-4 py-3">{{ row.attendance_percent ?? 0 }}%</td><td class="px-4 py-3">{{ row.required_percent ?? 80 }}%</td><td class="px-4 py-3">{{ row.absent_count ?? 0 }}</td><td class="px-4 py-3"><span class="rounded-md px-2 py-1 text-xs" :class="Number(row.attendance_percent || 0) >= Number(row.required_percent || 80) ? 'bg-emerald-50 text-emerald-700' : 'bg-rose-50 text-rose-700'">{{ Number(row.attendance_percent || 0) >= Number(row.required_percent || 80) ? 'Đủ' : 'Không đủ' }}</span></td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>
  </EraLmsLayout>
</template>
