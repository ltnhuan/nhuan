<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import EraLmsLayout from '@/Layouts/EraLmsLayout.vue'

const storageKey = 'eralms.learning_path.rules.v2'
const saveStatus = ref('')
const selectedCourse = ref('course-foundations')
const selectedTargetId = ref(102)
const activeStep = ref(1)

const courses = [
  { id: 'course-foundations', title: 'EraLMS Enterprise Foundations', owner: 'Khoa CNTT', standard: 'SCORM/xAPI ready' },
  { id: 'course-quality', title: 'Academic Quality Assurance', owner: 'Phòng QA', standard: 'OBE aligned' },
]

const pathItems = [
  { id: 101, type: 'lesson', section: 'Chương 1', title: 'Bài 1: Tổng quan hệ thống', duration: '12 phút', status: 'published' },
  { id: 102, type: 'lesson', section: 'Chương 1', title: 'Bài 2: Quy trình học', duration: '18 phút', status: 'published' },
  { id: 103, type: 'quiz', section: 'Chương 1', title: 'Quiz Chương 1', duration: '15 câu', status: 'review' },
  { id: 104, type: 'video', section: 'Chương 2', title: 'Video: Thiết lập khóa học', duration: '22 phút', status: 'draft' },
  { id: 105, type: 'assignment', section: 'Chương 2', title: 'Assignment: Thiết kế outline', duration: 'Rubric 100 điểm', status: 'published' },
]

const requirementTemplates = [
  { type: 'component_completed', label: 'Hoàn thành nội dung', defaultValue: 101, valueLabel: 'Nội dung yêu cầu' },
  { type: 'quiz_score_min', label: 'Quiz đạt điểm tối thiểu', defaultValue: 103, valueLabel: 'Quiz yêu cầu', minKey: 'min_score', minLabel: 'Điểm tối thiểu', minDefault: 70 },
  { type: 'video_watch_percent', label: 'Xem video tối thiểu', defaultValue: 104, valueLabel: 'Video yêu cầu', minKey: 'min_percent', minLabel: 'Tỷ lệ xem (%)', minDefault: 90 },
  { type: 'assignment_score_min', label: 'Assignment đạt điểm tối thiểu', defaultValue: 105, valueLabel: 'Assignment yêu cầu', minKey: 'min_score', minLabel: 'Điểm tối thiểu', minDefault: 70 },
  { type: 'manual_approval', label: 'Giảng viên duyệt thủ công', valueLabel: 'Người duyệt' },
  { type: 'date_after', label: 'Mở sau ngày', valueLabel: 'Thời điểm mở' },
]

const rule = ref({
  title: 'Mở Bài 2 sau khi hoàn thành nền tảng',
  mode: 'sequential',
  unlock_behavior: 'all_required',
  message_locked: 'Bạn cần hoàn thành điều kiện tiên quyết trước khi mở nội dung này.',
  requires: [
    { id: crypto.randomUUID(), type: 'component_completed', component_id: 101 },
    { id: crypto.randomUUID(), type: 'quiz_score_min', component_id: 103, min_score: 70 },
    { id: crypto.randomUUID(), type: 'video_watch_percent', component_id: 104, min_percent: 90 },
  ],
  adaptive_routes: [
    { id: crypto.randomUUID(), when: 'score < 70', target: 'Mở bài ôn tập Chương 1' },
    { id: crypto.randomUUID(), when: 'score >= 90', target: 'Mở case study nâng cao' },
  ],
})

const selectedCourseData = computed(() => courses.find((course) => course.id === selectedCourse.value) || courses[0])
const selectedTarget = computed(() => pathItems.find((item) => item.id === selectedTargetId.value) || pathItems[0])
const hasRequirements = computed(() => rule.value.requires.length > 0)
const joiner = computed(() => rule.value.unlock_behavior === 'all_required' ? ' và ' : ' hoặc ')

const previewText = computed(() => {
  if (!hasRequirements.value) return 'Chưa có điều kiện. Nội dung sẽ mở tự do.'
  return rule.value.requires.map(describeRequirement).join(joiner.value)
})

const validationChecks = computed(() => {
  const checks = [
    { label: 'Target học tập đã chọn', ok: Boolean(selectedTarget.value?.id) },
    { label: 'Có ít nhất một điều kiện mở khóa', ok: hasRequirements.value },
    { label: 'Không tự khóa chính nội dung đang cấu hình', ok: !rule.value.requires.some((item) => item.component_id === selectedTargetId.value) },
    { label: 'Điểm/tỷ lệ nằm trong chuẩn 0-100', ok: rule.value.requires.every((item) => {
      const value = item.min_score ?? item.min_percent
      return value === undefined || (Number(value) >= 0 && Number(value) <= 100)
    }) },
  ]

  return checks
})

const completionRate = computed(() => Math.round(validationChecks.value.filter((check) => check.ok).length / validationChecks.value.length * 100))

function templateFor(type) {
  return requirementTemplates.find((template) => template.type === type) || requirementTemplates[0]
}

function itemTitle(id) {
  return pathItems.find((item) => item.id === Number(id))?.title || 'Chưa chọn nội dung'
}

function describeRequirement(item) {
  const template = templateFor(item.type)
  if (item.type === 'manual_approval') return 'Giảng viên duyệt hoàn thành'
  if (item.type === 'date_after') return `Mở sau ${item.unlock_at || 'ngày đã chọn'}`
  if (item.type === 'quiz_score_min') return `${itemTitle(item.component_id)} đạt tối thiểu ${item.min_score || 0} điểm`
  if (item.type === 'assignment_score_min') return `${itemTitle(item.component_id)} đạt tối thiểu ${item.min_score || 0} điểm`
  if (item.type === 'video_watch_percent') return `${itemTitle(item.component_id)} xem tối thiểu ${item.min_percent || 0}%`
  return `${template.label}: ${itemTitle(item.component_id)}`
}

function addRequirement(type) {
  const template = templateFor(type)
  const next = { id: crypto.randomUUID(), type }
  if (template.defaultValue) next.component_id = template.defaultValue
  if (template.minKey) next[template.minKey] = template.minDefault
  if (type === 'date_after') next.unlock_at = new Date(Date.now() + 86400000).toISOString().slice(0, 10)
  rule.value.requires.push(next)
  activeStep.value = 3
}

function removeRequirement(id) {
  rule.value.requires = rule.value.requires.filter((item) => item.id !== id)
}

function addAdaptiveRoute() {
  rule.value.adaptive_routes.push({ id: crypto.randomUUID(), when: 'score >= 80', target: 'Mở nội dung nâng cao' })
}

function removeAdaptiveRoute(id) {
  rule.value.adaptive_routes = rule.value.adaptive_routes.filter((route) => route.id !== id)
}

function persistRule() {
  const payload = {
    course: selectedCourse.value,
    target_id: selectedTargetId.value,
    target_title: selectedTarget.value.title,
    rule: rule.value,
    saved_at: new Date().toISOString(),
  }
  localStorage.setItem(storageKey, JSON.stringify(payload))
  saveStatus.value = `Đã lưu cấu hình cho ${selectedTarget.value.title}.`
}

function loadRule() {
  const stored = localStorage.getItem(storageKey)
  if (!stored) return

  try {
    const payload = JSON.parse(stored)
    selectedCourse.value = payload.course || selectedCourse.value
    selectedTargetId.value = payload.target_id || selectedTargetId.value
    rule.value = payload.rule || rule.value
    saveStatus.value = `Đã tải bản lưu gần nhất: ${new Date(payload.saved_at).toLocaleString('vi-VN')}.`
  } catch {
    saveStatus.value = 'Không đọc được bản lưu cục bộ.'
  }
}

watch(selectedTargetId, () => {
  rule.value.title = `Quy tắc mở khóa: ${selectedTarget.value.title}`
})

onMounted(loadRule)
</script>

<template>
  <EraLmsLayout>
    <template #breadcrumb>Đào tạo / Lộ trình học tập</template>

    <section class="border-b border-slate-200 bg-white">
      <div class="mx-auto max-w-7xl px-4 py-5 sm:px-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
          <div class="max-w-2xl">
            <div class="text-xs font-semibold uppercase text-cyan-700">Learning path builder</div>
            <h1 class="mt-1 text-xl font-bold text-slate-950">Thiết kế lộ trình học rõ bước, có kiểm tra chuẩn</h1>
            <p class="mt-2 text-sm leading-6 text-slate-600">Chọn khóa học, chọn nội dung cần mở khóa, thêm điều kiện, kiểm tra rủi ro và lưu cấu hình. Quy trình này bám chuẩn LMS quốc tế: prerequisite, mastery, adaptive route, manual approval và audit-ready preview.</p>
          </div>
          <div class="flex flex-wrap gap-2">
            <a href="/learning-path/learner-progress" class="rounded-md border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Xem người học</a>
            <a href="/learning-path/class-progress" class="rounded-md border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Xem lớp</a>
            <button class="rounded-md bg-cyan-700 px-4 py-2 text-sm font-semibold text-white hover:bg-cyan-800" @click="persistRule">Lưu cấu hình</button>
          </div>
        </div>

        <div v-if="saveStatus" class="mt-4 rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ saveStatus }}</div>

        <div class="mt-5 grid gap-3 md:grid-cols-4">
          <button v-for="step in [1, 2, 3, 4]" :key="step" class="rounded-md border px-3 py-3 text-left text-sm" :class="activeStep === step ? 'border-cyan-600 bg-cyan-50 text-cyan-900' : 'border-slate-200 bg-white text-slate-700'" @click="activeStep = step">
            <span class="block text-xs font-semibold uppercase">Bước {{ step }}</span>
            <span>{{ ['Chọn khóa học', 'Chọn target', 'Điều kiện mở khóa', 'Preview & kiểm tra'][step - 1] }}</span>
          </button>
        </div>
      </div>
    </section>

    <section class="mx-auto grid max-w-7xl gap-4 px-4 py-5 sm:px-6 xl:grid-cols-[300px_1fr_360px]">
      <aside class="space-y-4">
        <div class="rounded-lg border border-slate-200 bg-white p-4">
          <h2 class="text-sm font-bold text-slate-950">1. Khóa học</h2>
          <select v-model="selectedCourse" class="mt-3 h-10 w-full rounded-md border border-slate-300 bg-white px-3 text-sm">
            <option v-for="course in courses" :key="course.id" :value="course.id">{{ course.title }}</option>
          </select>
          <dl class="mt-3 space-y-2 text-sm">
            <div class="rounded-md bg-slate-50 p-3"><dt class="text-xs uppercase text-slate-500">Đơn vị</dt><dd class="font-semibold text-slate-900">{{ selectedCourseData.owner }}</dd></div>
            <div class="rounded-md bg-slate-50 p-3"><dt class="text-xs uppercase text-slate-500">Chuẩn</dt><dd class="font-semibold text-slate-900">{{ selectedCourseData.standard }}</dd></div>
          </dl>
        </div>

        <div class="rounded-lg border border-slate-200 bg-white p-4">
          <h2 class="text-sm font-bold text-slate-950">2. Nội dung cần mở khóa</h2>
          <div class="mt-3 space-y-2">
            <button v-for="item in pathItems" :key="item.id" class="w-full rounded-md border px-3 py-2 text-left text-sm" :class="selectedTargetId === item.id ? 'border-slate-950 bg-slate-950 text-white' : 'border-slate-200 bg-white text-slate-700 hover:bg-slate-50'" @click="selectedTargetId = item.id; activeStep = 2">
              <span class="block text-xs opacity-70">{{ item.section }} · {{ item.type }}</span>
              <span class="font-semibold">{{ item.title }}</span>
            </button>
          </div>
        </div>
      </aside>

      <main class="rounded-lg border border-slate-200 bg-white p-4">
        <div class="flex flex-wrap items-center justify-between gap-3">
          <div>
            <h2 class="text-base font-bold text-slate-950">3. Điều kiện mở khóa</h2>
            <p class="mt-1 text-sm text-slate-600">Target: {{ selectedTarget.title }}</p>
          </div>
          <select v-model="rule.unlock_behavior" class="h-10 rounded-md border border-slate-300 bg-white px-3 text-sm">
            <option value="all_required">Phải đạt tất cả điều kiện</option>
            <option value="any_required">Đạt một trong các điều kiện</option>
          </select>
        </div>

        <label class="mt-4 block">
          <span class="text-xs font-semibold uppercase text-slate-500">Tên rule</span>
          <input v-model="rule.title" class="mt-1 h-10 w-full rounded-md border border-slate-300 px-3 text-sm" />
        </label>

        <div class="mt-4 grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
          <button v-for="template in requirementTemplates" :key="template.type" class="rounded-md border border-slate-300 px-3 py-2 text-left text-sm font-medium text-slate-700 hover:border-cyan-500 hover:bg-cyan-50" @click="addRequirement(template.type)">
            + {{ template.label }}
          </button>
        </div>

        <div class="mt-4 space-y-3">
          <div v-if="!hasRequirements" class="rounded-md border border-dashed border-slate-300 bg-slate-50 p-5 text-center text-sm text-slate-600">Chưa có điều kiện. Thêm ít nhất một điều kiện để rule có ý nghĩa.</div>
          <div v-for="item in rule.requires" :key="item.id" class="rounded-md border border-slate-200 bg-slate-50 p-3">
            <div class="grid gap-3 lg:grid-cols-[180px_1fr_130px_auto]">
              <select v-model="item.type" class="h-10 rounded-md border border-slate-300 bg-white px-3 text-sm">
                <option v-for="template in requirementTemplates" :key="template.type" :value="template.type">{{ template.label }}</option>
              </select>
              <select v-if="item.type !== 'manual_approval' && item.type !== 'date_after'" v-model.number="item.component_id" class="h-10 rounded-md border border-slate-300 bg-white px-3 text-sm">
                <option v-for="pathItem in pathItems" :key="pathItem.id" :value="pathItem.id">{{ pathItem.title }}</option>
              </select>
              <input v-else-if="item.type === 'date_after'" v-model="item.unlock_at" type="date" class="h-10 rounded-md border border-slate-300 bg-white px-3 text-sm" />
              <div v-else class="flex h-10 items-center rounded-md border border-slate-200 bg-white px-3 text-sm text-slate-600">Role: Teacher/Admin</div>
              <input v-if="templateFor(item.type).minKey" v-model.number="item[templateFor(item.type).minKey]" type="number" min="0" max="100" class="h-10 rounded-md border border-slate-300 px-3 text-sm" />
              <div v-else class="hidden lg:block"></div>
              <button class="rounded-md border border-rose-200 bg-white px-3 py-2 text-sm font-semibold text-rose-700 hover:bg-rose-50" @click="removeRequirement(item.id)">Xóa</button>
            </div>
            <p class="mt-2 text-sm text-slate-600">{{ describeRequirement(item) }}</p>
          </div>
        </div>

        <div class="mt-5 border-t border-slate-200 pt-4">
          <div class="flex items-center justify-between gap-3">
            <h3 class="text-sm font-bold text-slate-950">Adaptive route</h3>
            <button class="rounded-md border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700" @click="addAdaptiveRoute">Thêm nhánh</button>
          </div>
          <div class="mt-3 space-y-2">
            <div v-for="route in rule.adaptive_routes" :key="route.id" class="grid gap-2 rounded-md border border-slate-200 p-3 md:grid-cols-[1fr_1fr_auto]">
              <input v-model="route.when" class="h-10 rounded-md border border-slate-300 px-3 text-sm" placeholder="score < 70" />
              <input v-model="route.target" class="h-10 rounded-md border border-slate-300 px-3 text-sm" placeholder="Mở nội dung bổ sung" />
              <button class="rounded-md border border-slate-300 px-3 py-2 text-sm" @click="removeAdaptiveRoute(route.id)">Xóa</button>
            </div>
          </div>
        </div>
      </main>

      <aside class="space-y-4">
        <div class="rounded-lg border border-slate-200 bg-white p-4">
          <h2 class="text-sm font-bold text-slate-950">4. Preview</h2>
          <div class="mt-3 rounded-md bg-slate-50 p-3">
            <div class="text-xs font-semibold uppercase text-slate-500">Khi người học mở</div>
            <div class="mt-1 text-sm font-semibold text-slate-950">{{ selectedTarget.title }}</div>
          </div>
          <p class="mt-3 text-sm leading-6 text-slate-700">{{ previewText }}</p>
          <textarea v-model="rule.message_locked" rows="3" class="mt-3 w-full rounded-md border border-slate-300 px-3 py-2 text-sm"></textarea>
        </div>

        <div class="rounded-lg border border-slate-200 bg-white p-4">
          <div class="flex items-center justify-between">
            <h2 class="text-sm font-bold text-slate-950">Kiểm tra chuẩn</h2>
            <span class="rounded-full bg-cyan-50 px-2.5 py-1 text-xs font-bold text-cyan-800">{{ completionRate }}%</span>
          </div>
          <ul class="mt-3 space-y-2 text-sm">
            <li v-for="check in validationChecks" :key="check.label" class="flex items-start gap-2 rounded-md px-3 py-2" :class="check.ok ? 'bg-emerald-50 text-emerald-800' : 'bg-amber-50 text-amber-900'">
              <span class="font-bold">{{ check.ok ? 'OK' : '!' }}</span>
              <span>{{ check.label }}</span>
            </li>
          </ul>
        </div>
      </aside>
    </section>
  </EraLmsLayout>
</template>
