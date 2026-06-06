<script setup>
import { computed, onMounted, ref } from 'vue'
import EraLmsLayout from '@/Layouts/EraLmsLayout.vue'
import { useLmsAction } from '@/composables/useLmsAction'

const props = defineProps({
  apiHeaders: { type: Object, required: true },
})

const loading = ref(false)
const error = ref('')
const assignments = ref([])
const courses = ref([])
const rubrics = ref([])
const creating = ref(false)
const { loading: actionLoading, toast, validationErrors, runAction } = useLmsAction(props.apiHeaders)
const form = ref({
  course_id: '',
  rubric_id: '',
  title: 'Bài tập mới',
  description: '',
  assignment_type: 'individual',
  submission_type: 'mixed',
  status: 'draft',
  open_at: '',
  due_at: '',
  max_score: 10,
  pass_score: 5,
  max_submissions: 1,
  allow_late: true,
})
const stats = computed(() => {
  const total = assignments.value.length
  const relative = assignments.value.filter((item) => item.deadlineMode === 'relative').length
  const lateOpen = assignments.value.filter((item) => item.phase === 'late_grace').length
  const closed = assignments.value.filter((item) => item.phase === 'closed').length

  return { total, relative, lateOpen, closed }
})

function formatDate(value) {
  if (!value) return '-'

  return new Intl.DateTimeFormat('vi-VN', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  }).format(new Date(value))
}

function phaseFor(item) {
  const engine = item.settings?.deadline_engine || {}
  const dueAt = engine.deadline_mode === 'relative' ? null : (engine.due_at || item.due_at)
  const graceAt = engine.grace_period_ends_at
  const now = Date.now()

  if (engine.open_at && now < new Date(engine.open_at).getTime()) return 'not_open'
  if (dueAt && now <= new Date(dueAt).getTime()) return 'open'
  if ((engine.allow_late ?? item.allow_late) && (!graceAt || now <= new Date(graceAt).getTime())) return 'late_grace'
  if (dueAt || graceAt) return 'closed'

  return 'open'
}

function normalizeAssignment(item) {
  const engine = item.settings?.deadline_engine || {}

  return {
    id: `ASM-${String(item.id).padStart(3, '0')}`,
    title: item.title,
    course: courseLabel(item.course),
    type: item.assignment_type,
    submission: item.submission_type,
    status: item.status,
    deadlineMode: engine.deadline_mode || 'absolute',
    due: engine.deadline_mode === 'relative'
      ? `${engine.relative_duration_days || 0}d ${engine.relative_duration_hours || 0}h ${engine.relative_duration_minutes || 0}m`
      : formatDate(engine.due_at || item.due_at),
    grace: formatDate(engine.grace_period_ends_at),
    individualCount: engine.individual_deadlines?.length || 0,
    phase: phaseFor(item),
    rubric: item.rubric?.title || 'Chưa gắn',
    submitted: item.submissions_count ?? 0,
    late: item.late_submissions_count ?? 0,
  }
}

async function loadAssignments() {
  loading.value = true
  error.value = ''

  try {
    const response = await fetch('/api/v1/assignments?per_page=30', {
      headers: props.apiHeaders,
    })
    const data = await response.json()
    if (!response.ok) throw new Error(data.message || 'Không tải được danh sách bài tập.')
    assignments.value = (data.data || []).map(normalizeAssignment)
  } catch (err) {
    error.value = err.message || 'Không tải được danh sách bài tập.'
  } finally {
    loading.value = false
  }
}

async function loadOptions() {
  const [courseResponse, rubricResponse] = await Promise.all([
    fetch('/api/v1/courses?per_page=100', { headers: props.apiHeaders }),
    fetch('/api/v1/rubrics?per_page=100', { headers: props.apiHeaders }),
  ])
  const coursePayload = await courseResponse.json()
  const rubricPayload = await rubricResponse.json()
  courses.value = coursePayload.data?.data || coursePayload.data || []
  rubrics.value = rubricPayload.data?.data || rubricPayload.data || []
}

function courseLabel(course) {
  return course ? `${course.code || '#'} - ${course.title}` : 'Chưa gắn khóa học'
}

function rubricLabel(rubric) {
  return rubric ? `${rubric.title} (${rubric.max_score || 0}đ)` : 'Chưa gắn rubric'
}

function payload() {
  return {
    ...form.value,
    course_id: form.value.course_id ? Number(form.value.course_id) : null,
    rubric_id: form.value.rubric_id ? Number(form.value.rubric_id) : null,
    max_score: Number(form.value.max_score || 0),
    pass_score: Number(form.value.pass_score || 0),
    max_submissions: Number(form.value.max_submissions || 1),
    open_at: form.value.open_at || null,
    due_at: form.value.due_at || null,
  }
}

async function createAssignment() {
  await runAction({
    actionKey: 'assignment.create',
    url: '/api/v1/assignments',
    method: 'POST',
    body: payload(),
    reload: async () => {
      creating.value = false
      form.value.title = 'Bài tập mới'
      form.value.description = ''
      await loadAssignments()
    },
  })
}

onMounted(async () => {
  await Promise.all([loadAssignments(), loadOptions()])
})
</script>

<template>
  <EraLmsLayout>
    <template #breadcrumb>Bài tập / Quản lý</template>

    <section class="border-b bg-white">
      <div class="mx-auto max-w-7xl px-6 py-5">
        <div class="flex items-center justify-between gap-4">
          <div>
            <h1 class="text-lg font-semibold">Assignment Management</h1>
            <p class="mt-1 text-sm text-slate-600">Giao bài theo khóa học, lớp, nhóm hoặc cá nhân; kiểm soát hạn nộp, rubric và dữ liệu sẵn sàng đẩy SIS.</p>
          </div>
          <button class="rounded-md bg-slate-950 px-4 py-2 text-sm text-white" @click="loadAssignments">Làm mới</button>
        </div>
        <div class="mt-5 grid gap-3 md:grid-cols-4">
          <div class="rounded-md border bg-slate-50 p-3">
            <div class="text-xs text-slate-500">Bài tập</div>
            <div class="mt-1 text-lg font-semibold">{{ stats.total }}</div>
          </div>
          <div class="rounded-md border bg-slate-50 p-3">
            <div class="text-xs text-slate-500">Relative deadline</div>
            <div class="mt-1 text-lg font-semibold">{{ stats.relative }}</div>
          </div>
          <div class="rounded-md border bg-slate-50 p-3">
            <div class="text-xs text-slate-500">Đang grace</div>
            <div class="mt-1 text-lg font-semibold">{{ stats.lateOpen }}</div>
          </div>
          <div class="rounded-md border bg-slate-50 p-3">
            <div class="text-xs text-slate-500">Đã đóng</div>
            <div class="mt-1 text-lg font-semibold">{{ stats.closed }}</div>
          </div>
        </div>
        <div class="mt-5 grid gap-3 md:grid-cols-4">
          <select class="rounded-md border px-3 py-2 text-sm"><option>Tất cả khóa học</option><option>An toàn lao động</option></select>
          <select class="rounded-md border px-3 py-2 text-sm"><option>Tất cả trạng thái</option><option>Draft</option><option>Published</option><option>Closed</option></select>
          <input class="rounded-md border px-3 py-2 text-sm" placeholder="Due date từ" />
          <input class="rounded-md border px-3 py-2 text-sm" placeholder="Due date đến" />
        </div>
      </div>
    </section>

    <section class="mx-auto max-w-7xl px-6 pt-5">
      <div v-if="toast.show" class="mb-4 rounded-md px-4 py-3 text-sm font-semibold" :class="toast.type === 'success' ? 'bg-emerald-50 text-emerald-700' : 'bg-red-50 text-red-700'">{{ toast.message }}</div>
      <button class="rounded-md bg-slate-950 px-4 py-2 text-sm font-semibold text-white" @click="creating = !creating">{{ creating ? 'Đóng form tạo' : 'Tạo bài tập' }}</button>
      <div v-if="creating" class="mt-4 rounded-lg border bg-white p-4">
        <h2 class="text-sm font-semibold text-slate-950">Tạo bài tập mới</h2>
        <div class="mt-3 grid gap-3 md:grid-cols-4">
          <input v-model="form.title" class="h-10 rounded-md border px-3 text-sm md:col-span-2" placeholder="Tiêu đề bài tập" />
          <select v-model="form.course_id" class="h-10 rounded-md border px-3 text-sm">
            <option value="">Chọn khóa học</option>
            <option v-for="course in courses" :key="course.id" :value="course.id">{{ courseLabel(course) }}</option>
          </select>
          <select v-model="form.rubric_id" class="h-10 rounded-md border px-3 text-sm">
            <option value="">Chọn rubric</option>
            <option v-for="rubric in rubrics" :key="rubric.id" :value="rubric.id">{{ rubricLabel(rubric) }}</option>
          </select>
          <select v-model="form.assignment_type" class="h-10 rounded-md border px-3 text-sm"><option value="individual">Cá nhân</option><option value="group">Nhóm</option><option value="class">Lớp</option><option value="project">Project</option></select>
          <select v-model="form.submission_type" class="h-10 rounded-md border px-3 text-sm"><option value="mixed">Mixed</option><option value="file">File</option><option value="text">Text</option><option value="url">URL</option><option value="video">Video</option></select>
          <input v-model="form.open_at" type="datetime-local" class="h-10 rounded-md border px-3 text-sm" />
          <input v-model="form.due_at" type="datetime-local" class="h-10 rounded-md border px-3 text-sm" />
          <input v-model.number="form.max_score" type="number" class="h-10 rounded-md border px-3 text-sm" placeholder="Max score" />
          <input v-model.number="form.pass_score" type="number" class="h-10 rounded-md border px-3 text-sm" placeholder="Pass score" />
          <input v-model.number="form.max_submissions" type="number" class="h-10 rounded-md border px-3 text-sm" placeholder="Max submissions" />
          <label class="inline-flex items-center gap-2 text-sm"><input v-model="form.allow_late" type="checkbox" /> Cho phép nộp trễ</label>
          <textarea v-model="form.description" class="min-h-20 rounded-md border px-3 py-2 text-sm md:col-span-4" placeholder="Mô tả"></textarea>
        </div>
        <div class="mt-3 flex gap-2">
          <button class="rounded-md bg-blue-700 px-3 py-2 text-sm font-semibold text-white disabled:opacity-60" :disabled="actionLoading['assignment.create']" @click="createAssignment">Lưu bài tập</button>
          <button class="rounded-md border px-3 py-2 text-sm font-semibold" @click="creating = false">Hủy</button>
        </div>
        <div v-if="Object.keys(validationErrors).length" class="mt-3 rounded-md bg-red-50 p-3 text-xs text-red-700">{{ validationErrors }}</div>
      </div>
    </section>

    <section class="mx-auto grid max-w-7xl gap-4 px-6 py-5 xl:grid-cols-[1fr_360px]">
      <div class="overflow-hidden border bg-white">
        <div v-if="error" class="border-b border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">{{ error }}</div>
        <div v-if="loading" class="border-b border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-600">Đang tải dữ liệu bài tập...</div>
        <table class="w-full text-left text-sm">
          <thead class="bg-slate-50 text-xs uppercase text-slate-500">
            <tr><th class="px-4 py-3">Mã</th><th class="px-4 py-3">Bài tập</th><th class="px-4 py-3">Loại</th><th class="px-4 py-3">Deadline</th><th class="px-4 py-3">Grace</th><th class="px-4 py-3">Rubric</th><th class="px-4 py-3">Nộp</th><th class="px-4 py-3">Trạng thái</th></tr>
          </thead>
          <tbody class="divide-y">
            <tr v-for="item in assignments" :key="item.id">
              <td class="px-4 py-3 font-medium">{{ item.id }}</td>
              <td class="px-4 py-3"><div class="font-medium">{{ item.title }}</div><div class="text-xs text-slate-500">{{ item.course }} · {{ item.submission }}</div></td>
              <td class="px-4 py-3"><div>{{ item.type }}</div><div class="text-xs text-slate-500">{{ item.deadlineMode }}</div></td>
              <td class="px-4 py-3"><div>{{ item.due }}</div><div class="text-xs text-slate-500">{{ item.individualCount }} gia hạn riêng</div></td>
              <td class="px-4 py-3">{{ item.grace }}</td>
              <td class="px-4 py-3">{{ item.rubric }}</td>
              <td class="px-4 py-3">{{ item.submitted }} <span class="text-xs text-rose-600">+{{ item.late }} trễ</span></td>
              <td class="px-4 py-3"><span class="rounded bg-emerald-50 px-2 py-1 text-xs text-emerald-700">{{ item.status }} · {{ item.phase }}</span></td>
            </tr>
            <tr v-if="!loading && !assignments.length">
              <td class="px-4 py-6 text-center text-slate-500" colspan="8">Chưa có dữ liệu bài tập mẫu.</td>
            </tr>
          </tbody>
        </table>
      </div>

      <aside class="space-y-4">
        <div class="border bg-white p-4">
          <h2 class="text-sm font-semibold">Tạo/Cấu hình nhanh</h2>
          <div class="mt-4 space-y-3 text-sm">
            <div class="rounded-md border border-slate-200 bg-slate-50 p-3">Dùng form “Tạo bài tập” phía trên để lưu trực tiếp qua API và chọn khóa học/rubric bằng tên.</div>
            <div class="grid grid-cols-2 gap-3">
              <select class="rounded-md border px-3 py-2"><option>Cá nhân</option><option>Nhóm</option><option>Lớp</option><option>Project</option></select>
              <select class="rounded-md border px-3 py-2"><option>Mixed</option><option>File</option><option>Text</option><option>URL</option><option>Video</option></select>
            </div>
            <div class="grid grid-cols-2 gap-3">
              <input class="rounded-md border px-3 py-2" placeholder="Open at" />
              <input class="rounded-md border px-3 py-2" placeholder="Due at" />
            </div>
            <div class="grid grid-cols-2 gap-3">
              <input class="rounded-md border px-3 py-2" placeholder="Max score" />
              <input class="rounded-md border px-3 py-2" placeholder="Pass score" />
            </div>
            <input class="w-full rounded-md border px-3 py-2" placeholder="Max submissions" />
            <label class="flex items-center gap-2"><input type="checkbox" class="h-4 w-4" /> Cho phép nộp trễ</label>
            <select class="w-full rounded-md border px-3 py-2"><option>Gắn rubric</option><option v-for="rubric in rubrics" :key="rubric.id">{{ rubricLabel(rubric) }}</option></select>
          </div>
        </div>
        <div class="border bg-white p-4 text-sm">
          <h2 class="font-semibold">SIS readiness</h2>
          <dl class="mt-3 grid grid-cols-2 gap-3">
            <div class="bg-slate-50 p-3"><dt>Đã chấm</dt><dd class="font-semibold">312</dd></div>
            <div class="bg-slate-50 p-3"><dt>Sẵn sàng sync</dt><dd class="font-semibold">286</dd></div>
          </dl>
        </div>
      </aside>
    </section>
  </EraLmsLayout>
</template>
