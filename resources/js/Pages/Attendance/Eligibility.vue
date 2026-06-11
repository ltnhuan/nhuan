<script setup>
import { computed, onMounted, ref } from 'vue'
import { AlertTriangle, ArrowRight, CalendarCheck, CheckCircle2, RefreshCw } from '@lucide/vue'
import EraLmsLayout from '@/Layouts/EraLmsLayout.vue'

const props = defineProps({
  apiHeaders: { type: Object, required: true },
  sessionUser: { type: Object, default: null },
})

const loading = ref(true)
const error = ref('')
const payload = ref(null)

const summary = computed(() => payload.value?.summary || {})
const monthly = computed(() => payload.value?.charts?.monthly_attendance || [])
const courses = computed(() => payload.value?.charts?.course_attendance || [])
const alerts = computed(() => payload.value?.alerts || {})
const riskClasses = computed(() => alerts.value?.risk_classes || [])
const recentRecords = computed(() => payload.value?.recent_records || [])
const forecast = computed(() => payload.value?.forecast || {})
const eligibleCourses = computed(() => courses.value.filter((course) => course.eligible_for_exam))
const blockedCourses = computed(() => courses.value.filter((course) => !course.eligible_for_exam))

const stats = computed(() => [
  { label: 'Attendance Rate', value: `${Number(summary.value.attendance_rate || 0).toFixed(1)}%`, tone: 'text-blue-700' },
  { label: 'Absent', value: summary.value.absent || 0, tone: 'text-red-700' },
  { label: 'Late', value: summary.value.late || 0, tone: 'text-amber-700' },
  { label: 'Excused', value: summary.value.excused || 0, tone: 'text-emerald-700' },
])

async function load() {
  loading.value = true
  error.value = ''
  try {
    const response = await fetch('/api/v1/student/attendance', { headers: props.apiHeaders })
    const data = await response.json().catch(() => ({}))
    if (!response.ok) throw new Error(data.message || 'Không tải được Attendance Center.')
    payload.value = data.data || data
  } catch (err) {
    error.value = err.message || 'Không tải được Attendance Center.'
  } finally {
    loading.value = false
  }
}

function statusLabel(status) {
  return {
    present: 'Có mặt',
    checked_in: 'Có mặt',
    late: 'Đi trễ',
    excused: 'Có phép',
    absent: 'Vắng',
  }[status] || status || '-'
}

function statusClass(status) {
  if (['present', 'checked_in', 'excused'].includes(status)) return 'bg-emerald-50 text-emerald-700'
  if (status === 'late') return 'bg-amber-50 text-amber-700'
  if (status === 'absent') return 'bg-red-50 text-red-700'
  return 'bg-slate-100 text-slate-700'
}

onMounted(load)
</script>

<template>
  <EraLmsLayout :session-user="sessionUser">
    <template #breadcrumb>Attendance Center</template>
    <section class="mx-auto max-w-7xl px-3 py-3 sm:px-5">
      <div class="rounded-md border border-slate-200 bg-white p-4 shadow-sm">
        <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
          <div class="text-xs font-bold uppercase text-blue-700">Exam eligibility</div>
          <h1 class="mt-1 text-xl font-bold text-slate-950">Attendance & Eligibility Center</h1>
          <p class="mt-1 text-sm text-slate-600">Biết ngay khóa nào đủ điều kiện thi, khóa nào cần xử lý chuyên cần.</p>
        </div>
          <div class="flex flex-wrap gap-2">
            <a href="/attendance/checkin" class="inline-flex items-center gap-2 rounded-md bg-slate-950 px-3 py-2 text-sm font-semibold text-white">
              <CheckCircle2 class="h-4 w-4" /> Check-in
            </a>
            <button class="grid h-10 w-10 place-items-center rounded-md border border-slate-300 bg-white text-slate-700 disabled:opacity-60" :disabled="loading" title="Tải lại" @click="load">
              <RefreshCw class="h-4 w-4" />
            </button>
          </div>
        </div>
      </div>

      <div v-if="loading" class="mt-5 rounded-md border bg-white p-6 text-sm text-slate-500">Đang tải dữ liệu chuyên cần...</div>
      <div v-else-if="error" class="mt-5 rounded-md border border-red-200 bg-red-50 p-6 text-sm font-semibold text-red-700">{{ error }}</div>

      <template v-else>
        <div class="mt-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
          <div v-for="item in stats" :key="item.label" class="rounded-md border border-slate-200 bg-white p-4 shadow-sm">
            <div class="text-xs font-semibold uppercase text-slate-500">{{ item.label }}</div>
            <div class="mt-2 text-3xl font-bold" :class="item.tone">{{ item.value }}</div>
          </div>
        </div>

        <div class="mt-5 grid gap-3 lg:grid-cols-2">
          <div class="rounded-md border border-emerald-200 bg-emerald-50 p-4">
            <div class="text-xs font-bold uppercase text-emerald-700">Đủ điều kiện thi</div>
            <div class="mt-1 text-3xl font-bold text-emerald-900">{{ eligibleCourses.length }}</div>
            <p class="mt-1 text-sm text-emerald-800">khóa có attendance đạt ngưỡng.</p>
          </div>
          <div class="rounded-md border border-red-200 bg-red-50 p-4">
            <div class="text-xs font-bold uppercase text-red-700">Cần xử lý</div>
            <div class="mt-1 text-3xl font-bold text-red-900">{{ blockedCourses.length }}</div>
            <p class="mt-1 text-sm text-red-800">khóa chưa đủ điều kiện hoặc có cảnh báo.</p>
          </div>
        </div>

        <div class="mt-5 grid gap-4 lg:grid-cols-[minmax(0,1fr)_360px]">
          <main class="space-y-4">
            <div class="rounded-md border border-slate-200 bg-white p-4 shadow-sm">
              <h2 class="flex items-center gap-2 text-sm font-bold text-slate-950"><CalendarCheck class="h-4 w-4 text-blue-700" /> Monthly Attendance</h2>
              <div class="mt-4 grid gap-3 md:grid-cols-3 xl:grid-cols-6">
                <div v-for="month in monthly" :key="month.month" class="rounded-md border border-slate-200 bg-slate-50 p-3">
                  <div class="text-xs font-semibold text-slate-500">{{ month.month }}</div>
                  <div class="mt-2 text-xl font-bold text-slate-950">{{ month.rate }}%</div>
                  <div class="mt-2 h-2 rounded-full bg-white">
                    <div class="h-2 rounded-full bg-blue-600" :style="{ width: `${Math.min(100, Number(month.rate || 0))}%` }"></div>
                  </div>
                  <div class="mt-2 text-[11px] text-slate-500">P {{ month.present }} · L {{ month.late }} · A {{ month.absent }}</div>
                </div>
                <div v-if="!monthly.length" class="rounded-md border border-dashed border-slate-300 p-4 text-sm text-slate-500 md:col-span-3">Chưa có dữ liệu điểm danh theo tháng.</div>
              </div>
            </div>

            <div class="rounded-md border border-slate-200 bg-white p-4 shadow-sm">
              <h2 class="text-sm font-bold text-slate-950">Course Attendance</h2>
              <div class="mt-4 space-y-3">
                <div v-for="course in courses" :key="`${course.course_id}-${course.class_id}`" class="rounded-md border border-slate-200 p-3">
                  <div class="flex flex-wrap items-center justify-between gap-3">
                    <div class="min-w-0">
                      <div class="truncate font-semibold text-slate-950">{{ course.course_title || `Course #${course.course_id || '-'}` }}</div>
                      <div class="text-xs text-slate-500">{{ course.course_code || `Class #${course.class_id || '-'}` }} · Vắng {{ course.absent_count || 0 }} · Trễ {{ course.late_count || 0 }}</div>
                    </div>
                    <span class="rounded-full px-3 py-1 text-xs font-bold" :class="course.eligible_for_exam ? 'bg-emerald-50 text-emerald-700' : 'bg-red-50 text-red-700'">
                      {{ course.eligible_for_exam ? 'Đủ điều kiện thi' : 'Không đủ điều kiện' }}
                    </span>
                  </div>
                  <div class="mt-3 h-2 rounded-full bg-slate-100">
                    <div class="h-2 rounded-full" :class="course.eligible_for_exam ? 'bg-emerald-600' : 'bg-red-500'" :style="{ width: `${Math.min(100, Number(course.attendance_percent || 0))}%` }"></div>
                  </div>
                  <div class="mt-2 flex items-center justify-between gap-3">
                    <span class="text-xs font-semibold text-slate-600">{{ course.attendance_percent || 0 }}%</span>
                    <a :href="course.href || '/courses'" class="inline-flex items-center gap-1 text-xs font-bold text-blue-700">
                      Mở khóa <ArrowRight class="h-3.5 w-3.5" />
                    </a>
                  </div>
                </div>
                <div v-if="!courses.length" class="rounded-md border border-dashed border-slate-300 p-4 text-sm text-slate-500">Chưa có bảng điều kiện chuyên cần theo khóa.</div>
              </div>
            </div>

            <div class="rounded-md border border-slate-200 bg-white p-4 shadow-sm">
              <h2 class="text-sm font-bold text-slate-950">Recent Attendance Records</h2>
              <div class="mt-3 overflow-auto">
                <table class="w-full text-left text-sm">
                  <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                    <tr><th class="px-3 py-2">Buổi</th><th class="px-3 py-2">Thời gian</th><th class="px-3 py-2">Phút</th><th class="px-3 py-2">Trạng thái</th></tr>
                  </thead>
                  <tbody class="divide-y divide-slate-100">
                    <tr v-for="record in recentRecords" :key="record.id">
                      <td class="px-3 py-2 font-medium">{{ record.session?.title || `Session #${record.attendance_session_id || '-'}` }}</td>
                      <td class="px-3 py-2 text-slate-600">{{ record.checkin_at || '-' }}</td>
                      <td class="px-3 py-2">{{ record.attended_minutes || 0 }}</td>
                      <td class="px-3 py-2"><span class="rounded px-2 py-1 text-xs font-semibold" :class="statusClass(record.status)">{{ statusLabel(record.status) }}</span></td>
                    </tr>
                    <tr v-if="!recentRecords.length"><td colspan="4" class="px-3 py-5 text-center text-slate-500">Chưa có bản ghi điểm danh.</td></tr>
                  </tbody>
                </table>
              </div>
            </div>
          </main>

          <aside class="space-y-4">
            <div class="rounded-md border border-slate-200 bg-white p-4 shadow-sm">
              <h2 class="text-sm font-bold text-slate-950">Forecast</h2>
              <div class="mt-4 space-y-3 text-sm">
                <div class="rounded-md bg-slate-50 p-3">
                  <div class="text-xs font-semibold uppercase text-slate-500">Exam Eligibility</div>
                  <div class="mt-1 text-2xl font-bold text-slate-950">{{ Number(forecast.exam_eligibility || 0).toFixed(1) }}%</div>
                </div>
                <div class="rounded-md bg-slate-50 p-3">
                  <div class="text-xs font-semibold uppercase text-slate-500">Graduation Eligibility</div>
                  <div class="mt-1 text-lg font-bold capitalize text-slate-950">{{ forecast.graduation_eligibility || '-' }}</div>
                </div>
              </div>
            </div>

            <div class="rounded-md border border-slate-200 bg-white p-4 shadow-sm">
              <h2 class="flex items-center gap-2 text-sm font-bold text-slate-950"><AlertTriangle class="h-4 w-4 text-amber-600" /> Alerts</h2>
              <div class="mt-4 rounded-md p-3 text-sm" :class="alerts.below_threshold ? 'bg-red-50 text-red-700' : 'bg-emerald-50 text-emerald-700'">
                {{ alerts.below_threshold ? 'Attendance dưới ngưỡng 80%.' : 'Attendance đang đạt ngưỡng an toàn.' }}
              </div>
              <div class="mt-3 space-y-2">
                <div v-for="item in riskClasses" :key="`${item.course_id}-${item.class_id}`" class="rounded-md border border-red-100 bg-red-50 p-3 text-sm text-red-700">
                  Course #{{ item.course_id }} cần can thiệp: {{ item.attendance_percent }}%
                </div>
                <div v-if="!riskClasses.length" class="rounded-md border border-slate-200 bg-slate-50 p-3 text-sm text-slate-500">Không có lớp rủi ro.</div>
              </div>
            </div>
          </aside>
        </div>
      </template>
    </section>
  </EraLmsLayout>
</template>
