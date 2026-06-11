<script setup>
import { computed, onMounted, ref } from 'vue'
import EraLmsLayout from '@/Layouts/EraLmsLayout.vue'

const props = defineProps({
  sessionUser: { type: Object, default: null },
  apiHeaders: { type: Object, default: () => ({}) },
})

defineEmits(['logout'])

const loading = ref(false)
const running = ref('')
const payload = ref(null)
const selectedModule = ref('course')
const selectedIssueIds = ref([])
const modules = ['course', 'repository', 'question_bank', 'exam', 'assignment', 'gradebook', 'attendance', 'learning_path', 'sis', 'certificate', 'user_permission']

const summary = computed(() => payload.value?.summary || {})
const latestIssues = computed(() => payload.value?.latest_issues || [])
const autoFixable = computed(() => payload.value?.auto_fixable || [])
const checks = computed(() => payload.value?.checks || [])

async function request(url, options = {}) {
  const response = await fetch(url, {
    ...options,
    headers: {
      ...props.apiHeaders,
      ...(options.headers || {}),
    },
  })
  const data = await response.json()
  if (!response.ok) throw new Error(data.message || 'Không thể thực hiện thao tác.')
  return data.data || data
}

async function load() {
  loading.value = true
  try {
    payload.value = await request('/api/v1/admin/lms/data-integrity')
  } finally {
    loading.value = false
  }
}

async function runAll() {
  running.value = 'all'
  try {
    await request('/api/v1/admin/lms/data-integrity/run', { method: 'POST' })
    await load()
  } finally {
    running.value = ''
  }
}

async function runModule() {
  running.value = selectedModule.value
  try {
    await request(`/api/v1/admin/lms/data-integrity/run/${selectedModule.value}`, { method: 'POST' })
    await load()
  } finally {
    running.value = ''
  }
}

async function autoFixSelected() {
  if (!selectedIssueIds.value.length) return
  await request('/api/v1/admin/lms/data-integrity/auto-fix', {
    method: 'POST',
    body: JSON.stringify({ issue_ids: selectedIssueIds.value }),
  })
  selectedIssueIds.value = []
  await load()
}

async function ignoreSelected() {
  if (!selectedIssueIds.value.length) return
  await request('/api/v1/admin/lms/data-integrity/ignore', {
    method: 'POST',
    body: JSON.stringify({ issue_ids: selectedIssueIds.value }),
  })
  selectedIssueIds.value = []
  await load()
}

function toggleIssue(id) {
  selectedIssueIds.value = selectedIssueIds.value.includes(id)
    ? selectedIssueIds.value.filter((value) => value !== id)
    : [...selectedIssueIds.value, id]
}

function exportReport() {
  window.open('/api/v1/admin/lms/data-integrity/export', '_blank', 'noopener')
}

function viewEntity(issue) {
  const routes = {
    course: '/courses/studio',
    course_component: '/courses/studio',
    content_repository_item: '/repository',
    question: '/question-banks',
    question_bank: '/question-banks',
    exam: '/exams',
    assignment: '/assignments',
    gradebook: '/gradebook',
    attendance_session: '/attendance',
    certificate_issue: '/credentials',
    integration_mapping: '/sis/mapping',
    lms_user: '/settings/users',
  }
  window.location.href = routes[issue.entity_type] || '/'
}

function severityClass(severity) {
  return {
    critical: 'bg-red-100 text-red-800',
    error: 'bg-orange-100 text-orange-800',
    warning: 'bg-amber-100 text-amber-800',
    info: 'bg-blue-100 text-blue-800',
  }[severity] || 'bg-slate-100 text-slate-700'
}

onMounted(load)
</script>

<template>
  <EraLmsLayout :session-user="sessionUser" @logout="$emit('logout')">
    <template #breadcrumb>Data Integrity</template>

    <section class="space-y-4">
      <div class="rounded-lg border border-slate-200 bg-white shadow-sm">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 px-5 py-4">
          <div>
            <h1 class="text-lg font-bold text-slate-950">Toàn vẹn dữ liệu</h1>
            <p class="mt-1 text-sm text-slate-600">Kiểm tra liên kết khóa học, lớp, học viên, học liệu, đề thi, điểm, chuyên cần, SIS và chứng chỉ.</p>
          </div>
          <div class="flex flex-wrap gap-2">
            <button class="rounded-md border border-slate-300 px-3 py-2 text-sm font-semibold" :disabled="loading" @click="load">Làm mới</button>
            <button class="rounded-md bg-slate-950 px-3 py-2 text-sm font-semibold text-white disabled:opacity-60" :disabled="!!running" @click="runAll">{{ running === 'all' ? 'Đang chạy...' : 'Run all checks' }}</button>
            <button class="rounded-md border border-slate-300 px-3 py-2 text-sm font-semibold" @click="exportReport">Export report</button>
          </div>
        </div>

        <div class="grid gap-3 border-b border-slate-200 bg-slate-50 p-4 md:grid-cols-6">
          <div class="rounded-md border bg-white p-3"><div class="text-xs text-slate-500">Tổng check</div><div class="mt-1 text-lg font-semibold">{{ summary.total_checks || 0 }}</div></div>
          <div class="rounded-md border bg-white p-3"><div class="text-xs text-slate-500">Passed</div><div class="mt-1 text-lg font-semibold text-emerald-700">{{ summary.passed || 0 }}</div></div>
          <div class="rounded-md border bg-white p-3"><div class="text-xs text-slate-500">Warning</div><div class="mt-1 text-lg font-semibold text-amber-700">{{ summary.warning || 0 }}</div></div>
          <div class="rounded-md border bg-white p-3"><div class="text-xs text-slate-500">Error</div><div class="mt-1 text-lg font-semibold text-orange-700">{{ summary.error || 0 }}</div></div>
          <div class="rounded-md border bg-white p-3"><div class="text-xs text-slate-500">Critical</div><div class="mt-1 text-lg font-semibold text-red-700">{{ summary.critical || 0 }}</div></div>
          <div class="rounded-md border bg-white p-3"><div class="text-xs text-slate-500">Auto-fix</div><div class="mt-1 text-lg font-semibold text-blue-700">{{ summary.auto_fixable || 0 }}</div></div>
        </div>

        <div class="flex flex-wrap items-center gap-2 border-b border-slate-200 p-4">
          <select v-model="selectedModule" class="h-10 rounded-md border border-slate-300 bg-white px-3 text-sm">
            <option v-for="module in modules" :key="module" :value="module">{{ module }}</option>
          </select>
          <button class="rounded-md border border-slate-300 px-3 py-2 text-sm font-semibold" :disabled="!!running" @click="runModule">Run module check</button>
          <button class="rounded-md border border-slate-300 px-3 py-2 text-sm font-semibold disabled:opacity-60" :disabled="!selectedIssueIds.length" @click="autoFixSelected">Auto fix selected</button>
          <button class="rounded-md border border-slate-300 px-3 py-2 text-sm font-semibold disabled:opacity-60" :disabled="!selectedIssueIds.length" @click="ignoreSelected">Ignore selected</button>
        </div>
      </div>

      <div class="grid gap-4 xl:grid-cols-[1fr_360px]">
        <section class="rounded-lg border border-slate-200 bg-white shadow-sm">
          <div class="border-b border-slate-200 px-5 py-4">
            <h2 class="text-base font-bold text-slate-950">Issue mới nhất</h2>
          </div>
          <div class="overflow-auto">
            <table class="w-full text-left text-sm">
              <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                <tr>
                  <th class="px-4 py-3"></th>
                  <th class="px-4 py-3">Module</th>
                  <th class="px-4 py-3">Severity</th>
                  <th class="px-4 py-3">Entity</th>
                  <th class="px-4 py-3">Message</th>
                  <th class="px-4 py-3">Thao tác</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-100">
                <tr v-if="loading"><td colspan="6" class="px-4 py-8 text-center text-slate-500">Đang tải...</td></tr>
                <tr v-for="issue in latestIssues" :key="issue.id" class="hover:bg-slate-50">
                  <td class="px-4 py-3"><input type="checkbox" :checked="selectedIssueIds.includes(issue.id)" @change="toggleIssue(issue.id)" /></td>
                  <td class="px-4 py-3 font-semibold">{{ issue.module }}</td>
                  <td class="px-4 py-3"><span class="rounded px-2 py-1 text-xs font-semibold" :class="severityClass(issue.severity)">{{ issue.severity }}</span></td>
                  <td class="px-4 py-3 font-mono text-xs">{{ issue.entity_type }}#{{ issue.entity_id }}</td>
                  <td class="px-4 py-3">{{ issue.message }}</td>
                  <td class="px-4 py-3"><button class="text-sm font-semibold text-blue-700" @click="viewEntity(issue)">View entity</button></td>
                </tr>
                <tr v-if="!loading && !latestIssues.length"><td colspan="6" class="px-4 py-8 text-center text-slate-500">Chưa có issue mở.</td></tr>
              </tbody>
            </table>
          </div>
        </section>

        <aside class="space-y-4">
          <section class="rounded-lg border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 px-5 py-4">
              <h2 class="text-base font-bold text-slate-950">Auto-fix khả dụng</h2>
            </div>
            <div class="divide-y divide-slate-100">
              <button v-for="issue in autoFixable" :key="issue.id" class="block w-full px-4 py-3 text-left text-sm hover:bg-slate-50" @click="toggleIssue(issue.id)">
                <div class="font-semibold text-slate-900">{{ issue.entity_type }}#{{ issue.entity_id }}</div>
                <div class="mt-1 text-slate-600">{{ issue.message }}</div>
              </button>
              <div v-if="!autoFixable.length" class="px-4 py-6 text-sm text-slate-500">Không có issue auto-fix.</div>
            </div>
          </section>

          <section class="rounded-lg border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 px-5 py-4">
              <h2 class="text-base font-bold text-slate-950">Check gần nhất</h2>
            </div>
            <div class="max-h-[520px] divide-y divide-slate-100 overflow-auto">
              <div v-for="check in checks" :key="check.id" class="px-4 py-3 text-sm">
                <div class="flex items-center justify-between gap-3">
                  <span class="font-semibold text-slate-900">{{ check.title }}</span>
                  <span class="text-xs font-semibold" :class="check.status === 'passed' ? 'text-emerald-700' : 'text-red-700'">{{ check.status }}</span>
                </div>
                <div class="mt-1 text-xs text-slate-500">{{ check.module }} · {{ check.failed_count }} lỗi</div>
              </div>
            </div>
          </section>
        </aside>
      </div>
    </section>
  </EraLmsLayout>
</template>
