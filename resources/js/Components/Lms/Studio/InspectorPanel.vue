<script setup>
import { computed, ref, watch } from 'vue'
import { CheckCircle2, Database, ExternalLink, Eye, Save, ToggleRight, X } from '@lucide/vue'
import CompletionRuleEditor from './CompletionRuleEditor.vue'
import CloPloMapper from './CloPloMapper.vue'
import PublishChecklist from './PublishChecklist.vue'

const props = defineProps({ course: Object, unit: Object, component: Object, checklist: Object, apiHeaders: { type: Object, default: () => ({}) } })
const emit = defineEmits(['save-unit', 'save-component', 'load-source'])

const statusItems = [
  ['Draft', 'Nháp', 'bg-amber-100 text-amber-700'],
  ['Review', 'Chờ duyệt', 'bg-blue-100 text-blue-700'],
  ['Approved', 'Đã duyệt', 'bg-emerald-100 text-emerald-700'],
  ['Published', 'Đã xuất bản', 'bg-teal-100 text-teal-700'],
  ['Archived', 'Lưu trữ', 'bg-red-100 text-red-700'],
]
const actionItems = [
  'Lưu nháp: Lưu thay đổi hiện tại',
  'Gửi duyệt: Gửi khóa học đến người duyệt',
  'Duyệt: Duyệt khóa học dành cho reviewer',
  'Xuất bản: Public cho người học',
  'Xem trước: Mô phỏng giao diện người học',
  'Lịch sử: Xem lịch sử thay đổi',
  'Thao tác khác: Clone, Archive, Export...',
]
const checklistItems = computed(() => Object.values(props.checklist?.groups || {}).flat())
const checklistPassed = computed(() => checklistItems.value.filter((item) => item.passed).length)
const editingComponent = computed(() => Boolean(props.component))
const activeTab = ref('settings')
const examDetail = ref(null)
const examLoading = ref(false)
const questionPreviewOpen = ref(false)
const draft = ref({
  title: '',
  description: '',
  estimated_minutes: 35,
  required: true,
  duration_minutes: 45,
  pass_score: 50,
  total_score: 100,
  max_attempts: 1,
  shuffle_questions: true,
  shuffle_options: true,
  youtube_url: '',
  live_provider: 'zoom',
  meeting_url: '',
  min_attended_minutes: 1,
})
const sourceLabel = computed(() => {
  if (props.component?.component_type === 'quiz') return 'Load đề trắc nghiệm'
  if (['pdf', 'file', 'video', 'scorm'].includes(props.component?.component_type)) return 'Load từ kho học liệu'
  if (props.component?.component_type === 'assignment') return 'Load bài tập'
  return ''
})
const quizQuestionCount = computed(() => examDetail.value?.questions?.length || props.component?.config?.questions_count || 0)
const quizNote = computed(() => props.component?.config?.exam_description || examDetail.value?.description || 'Chưa có ghi chú đề.')
const currentInfo = computed(() => {
  const target = props.component || props.unit || props.course || {}
  return [
    ['Đối tượng', props.component ? 'Hoạt động học tập' : props.unit ? 'Bài học / unit' : 'Khóa học'],
    ['Tên', target.title || target.name || '-'],
    ['Loại', props.component?.component_type || props.unit?.section_type || props.course?.course_type || '-'],
    ['Trạng thái', target.status || '-'],
    ['Bắt buộc', props.component ? (props.component.required ? 'Có' : 'Không') : '-'],
    ['Thời lượng', `${props.component?.config?.estimated_minutes || props.unit?.settings?.estimated_minutes || 0} phút`],
  ]
})
const configInfo = computed(() => props.component?.config || props.unit?.settings || {})

watch(() => [props.unit, props.component], () => {
  const target = props.component || props.unit || props.course || {}
  draft.value = {
    title: target.title || '',
    description: target.description || '',
    estimated_minutes: props.component?.config?.estimated_minutes || props.unit?.settings?.estimated_minutes || 35,
    required: props.component?.required ?? true,
    duration_minutes: props.component?.config?.duration_minutes || 45,
    pass_score: props.component?.config?.pass_score || props.component?.config?.completion_rule?.score || 50,
    total_score: props.component?.config?.total_score || 100,
    max_attempts: props.component?.config?.max_attempts || 1,
    shuffle_questions: props.component?.config?.shuffle_questions ?? true,
    shuffle_options: props.component?.config?.shuffle_options ?? true,
    youtube_url: props.component?.config?.youtube_url || '',
    live_provider: props.component?.config?.provider || 'zoom',
    meeting_url: props.component?.config?.meeting_url || props.component?.config?.join_url || '',
    min_attended_minutes: props.component?.config?.min_attended_minutes || 1,
  }
}, { immediate: true })

watch(() => props.component?.config?.exam_id, async (examId) => {
  examDetail.value = null
  if (!examId) return
  examLoading.value = true
  try {
    const response = await fetch(`/api/v1/exams/${examId}`, { headers: props.apiHeaders })
    const payload = await response.json()
    examDetail.value = payload.data || payload
  } finally {
    examLoading.value = false
  }
}, { immediate: true })

function saveCurrent() {
  if (props.component) {
    emit('save-component', props.component, {
      title: draft.value.title,
      required: draft.value.required,
      config: {
        ...(props.component.config || {}),
        estimated_minutes: Number(draft.value.estimated_minutes || 0),
        ...(props.component.component_type === 'quiz'
          ? {
              duration_minutes: Number(draft.value.duration_minutes || 0),
              pass_score: Number(draft.value.pass_score || 0),
              total_score: Number(draft.value.total_score || 0),
              max_attempts: Number(draft.value.max_attempts || 1),
              shuffle_questions: draft.value.shuffle_questions,
              shuffle_options: draft.value.shuffle_options,
              completion_rule: { type: 'score', score: Number(draft.value.pass_score || 0) },
            }
          : {}),
        ...(props.component.component_type === 'video'
          ? {
              youtube_url: draft.value.youtube_url,
              source_type: draft.value.youtube_url ? 'youtube' : props.component.config?.source_type,
            }
          : {}),
        ...(props.component.component_type === 'live_session'
          ? {
              provider: draft.value.live_provider,
              meeting_url: draft.value.meeting_url.trim(),
              join_url: draft.value.meeting_url.trim(),
              min_attended_minutes: Number(draft.value.min_attended_minutes || 1),
              completion_rule: { type: 'attendance', min_attended_minutes: Number(draft.value.min_attended_minutes || 1) },
            }
          : {}),
      },
    })
    return
  }
  if (props.unit) {
    emit('save-unit', props.unit, {
      title: draft.value.title,
      description: draft.value.description,
      settings: { ...(props.unit.settings || {}), estimated_minutes: Number(draft.value.estimated_minutes || 0) },
    })
  }
}

function openExamEditor() {
  const examId = props.component?.config?.exam_id
  if (!examId) return
  window.location.href = `/exams/builder?exam_id=${examId}`
}
</script>

<template>
  <aside class="w-[300px] shrink-0 border-l border-slate-200 bg-white">
    <div class="grid grid-cols-2 border-b border-slate-200 text-sm font-semibold">
      <button
        class="border-b-2 px-4 py-3"
        :class="activeTab === 'settings' ? 'border-blue-600 text-blue-700' : 'border-transparent text-slate-500 hover:bg-slate-50 hover:text-slate-800'"
        @click="activeTab = 'settings'"
      >
        Thiết lập
      </button>
      <button
        class="border-b-2 px-4 py-3"
        :class="activeTab === 'info' ? 'border-blue-600 text-blue-700' : 'border-transparent text-slate-500 hover:bg-slate-50 hover:text-slate-800'"
        @click="activeTab = 'info'"
      >
        Thông tin
      </button>
    </div>
    <div class="max-h-[calc(100vh-15rem)] space-y-4 overflow-auto p-4 text-sm">
      <template v-if="activeTab === 'settings'">
      <section class="rounded-md border border-blue-200 bg-blue-50/40 p-4">
        <div class="text-xs font-bold uppercase text-blue-700">{{ editingComponent ? 'Đang sửa hoạt động' : 'Đang sửa bài học' }}</div>
        <div class="mt-3 space-y-3 text-xs">
          <label class="block">
            <span class="font-semibold text-slate-600">{{ editingComponent ? 'Tên hoạt động' : 'Tên bài học' }}</span>
            <input v-model="draft.title" class="mt-1 h-10 w-full rounded-md border border-slate-300 bg-white px-3 text-sm" />
          </label>
          <label v-if="!editingComponent" class="block">
            <span class="font-semibold text-slate-600">Mô tả</span>
            <textarea v-model="draft.description" class="mt-1 min-h-20 w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm"></textarea>
          </label>
          <label class="block">
            <span class="font-semibold text-slate-600">Thời lượng ước tính</span>
            <input v-model="draft.estimated_minutes" type="number" min="0" class="mt-1 h-10 w-full rounded-md border border-slate-300 bg-white px-3 text-sm" />
          </label>
          <button
            v-if="sourceLabel"
            class="inline-flex h-10 w-full items-center justify-center gap-2 rounded-md border border-blue-300 bg-white px-3 text-sm font-semibold text-blue-700 hover:bg-blue-50"
            @click="emit('load-source', component)"
          >
            <Database class="h-4 w-4" />
            {{ sourceLabel }}
          </button>
          <section v-if="component?.component_type === 'video'" class="rounded-md border border-violet-200 bg-violet-50 p-3">
            <div class="text-xs font-bold uppercase text-violet-800">Nguồn video</div>
            <label class="mt-3 block">
              <span class="font-semibold text-slate-600">YouTube URL</span>
              <input v-model="draft.youtube_url" class="mt-1 h-10 w-full rounded-md border border-slate-300 bg-white px-3 text-sm" placeholder="https://www.youtube.com/watch?v=..." />
            </label>
            <div class="mt-2 text-xs text-slate-600">Có thể chèn YouTube hoặc bấm “Load từ kho học liệu” để chọn video/audio/hình ảnh/PDF đã upload.</div>
          </section>
          <section v-if="component?.component_type === 'live_session'" class="rounded-md border border-cyan-200 bg-cyan-50 p-3">
            <div class="text-xs font-bold uppercase text-cyan-800">Buổi học trực tuyến</div>
            <label class="mt-3 block">
              <span class="font-semibold text-slate-600">Nền tảng</span>
              <select v-model="draft.live_provider" class="mt-1 h-10 w-full rounded-md border border-slate-300 bg-white px-3 text-sm">
                <option value="zoom">Zoom</option>
                <option value="google_meet">Google Meet</option>
                <option value="teams">Microsoft Teams</option>
                <option value="other">Khác</option>
              </select>
            </label>
            <label class="mt-3 block">
              <span class="font-semibold text-slate-600">Link vào lớp</span>
              <input v-model="draft.meeting_url" class="mt-1 h-10 w-full rounded-md border border-slate-300 bg-white px-3 text-sm" placeholder="https://zoom.us/j/... hoặc https://meet.google.com/..." />
            </label>
            <label class="mt-3 block">
              <span class="font-semibold text-slate-600">Số phút tham dự tối thiểu</span>
              <input v-model="draft.min_attended_minutes" type="number" min="1" class="mt-1 h-10 w-full rounded-md border border-slate-300 bg-white px-3 text-sm" />
            </label>
            <div class="mt-2 text-xs text-slate-600">Người học sẽ thấy nút “Vào lớp” trên trang học và mở link này trong tab mới.</div>
          </section>
          <section v-if="component?.component_type === 'quiz'" class="rounded-md border border-amber-200 bg-amber-50 p-3">
            <div class="text-xs font-bold uppercase text-amber-800">Cài đặt đề trắc nghiệm</div>
            <div class="mt-3 rounded-md bg-white p-3 text-xs text-slate-700">
              <div class="font-bold text-slate-950">{{ component.config?.exam_title || 'Chưa gắn đề thi' }}</div>
              <div class="mt-1 text-slate-500">
                {{ component.config?.exam_code || `#${component.config?.exam_id || '-'}` }} · {{ examLoading ? 'Đang load...' : `${quizQuestionCount} câu` }}
              </div>
              <div class="mt-2 leading-5">{{ quizNote }}</div>
              <div v-if="component.config?.exam_id" class="mt-3 grid grid-cols-2 gap-2">
                <button class="inline-flex h-9 items-center justify-center gap-2 rounded-md border border-slate-300 bg-white px-2 text-xs font-semibold text-slate-700 hover:bg-slate-50" @click="questionPreviewOpen = true">
                  <Eye class="h-3.5 w-3.5" />
                  Xem câu
                </button>
                <button class="inline-flex h-9 items-center justify-center gap-2 rounded-md border border-amber-300 bg-amber-100 px-2 text-xs font-semibold text-amber-800 hover:bg-amber-200" @click="openExamEditor">
                  <ExternalLink class="h-3.5 w-3.5" />
                  Chỉnh đề
                </button>
              </div>
            </div>
            <div class="mt-3 grid grid-cols-2 gap-2">
              <label class="block">
                <span class="font-semibold text-slate-600">Thời gian làm bài</span>
                <input v-model="draft.duration_minutes" type="number" min="1" class="mt-1 h-10 w-full rounded-md border border-slate-300 bg-white px-3 text-sm" />
              </label>
              <label class="block">
                <span class="font-semibold text-slate-600">Số lần làm</span>
                <input v-model="draft.max_attempts" type="number" min="1" class="mt-1 h-10 w-full rounded-md border border-slate-300 bg-white px-3 text-sm" />
              </label>
              <label class="block">
                <span class="font-semibold text-slate-600">Điểm đạt</span>
                <input v-model="draft.pass_score" type="number" min="0" class="mt-1 h-10 w-full rounded-md border border-slate-300 bg-white px-3 text-sm" />
              </label>
              <label class="block">
                <span class="font-semibold text-slate-600">Tổng điểm</span>
                <input v-model="draft.total_score" type="number" min="1" class="mt-1 h-10 w-full rounded-md border border-slate-300 bg-white px-3 text-sm" />
              </label>
            </div>
            <div class="mt-3 space-y-2">
              <label class="flex items-center justify-between gap-3 rounded-md bg-white px-3 py-2">
                <span class="text-xs font-semibold text-slate-700">Đảo thứ tự câu hỏi</span>
                <input v-model="draft.shuffle_questions" type="checkbox" class="h-4 w-4" />
              </label>
              <label class="flex items-center justify-between gap-3 rounded-md bg-white px-3 py-2">
                <span class="text-xs font-semibold text-slate-700">Đảo thứ tự đáp án</span>
                <input v-model="draft.shuffle_options" type="checkbox" class="h-4 w-4" />
              </label>
            </div>
            <div v-if="component?.config?.exam_id" class="mt-3 rounded-md bg-white px-3 py-2 text-xs text-slate-600">
              Đã gắn đề và có thể lưu lại cấu hình thời gian/điểm bằng nút “Lưu thay đổi”.
            </div>
          </section>
          <div class="grid grid-cols-2 gap-2">
            <label class="block">
              <span class="font-semibold text-slate-600">Release date</span>
              <input class="mt-1 h-10 w-full rounded-md border border-slate-300 px-3 text-sm" value="01/05/2026" />
            </label>
            <label class="block">
              <span class="font-semibold text-slate-600">Giờ</span>
              <input class="mt-1 h-10 w-full rounded-md border border-slate-300 px-3 text-sm" value="08:00" />
            </label>
            <label class="block">
              <span class="font-semibold text-slate-600">Due date</span>
              <input class="mt-1 h-10 w-full rounded-md border border-slate-300 px-3 text-sm" value="15/05/2026" />
            </label>
            <label class="block">
              <span class="font-semibold text-slate-600">Giờ</span>
              <input class="mt-1 h-10 w-full rounded-md border border-slate-300 px-3 text-sm" value="23:59" />
            </label>
          </div>
          <label class="block">
            <span class="font-semibold text-slate-600">Prerequisite</span>
            <select class="mt-1 h-10 w-full rounded-md border border-slate-300 px-3 text-sm">
              <option>Không có</option>
              <option>1.1 Khái niệm cơ bản</option>
            </select>
          </label>
          <div class="flex flex-wrap gap-2">
            <span class="rounded-md border border-slate-200 bg-slate-50 px-2.5 py-1 text-xs font-semibold text-slate-700">CLO2</span>
            <span class="rounded-md border border-slate-200 bg-slate-50 px-2.5 py-1 text-xs font-semibold text-slate-700">CLO4</span>
            <button class="rounded-md border border-slate-300 bg-white px-2.5 py-1 text-xs font-semibold text-slate-700">+ Thêm CLO/PLO</button>
          </div>
          <div class="space-y-2 pt-1">
            <div class="flex items-center justify-between">
              <span class="font-semibold text-slate-700">Bắt buộc hoàn thành</span>
              <button class="text-blue-600" @click="draft.required = !draft.required"><ToggleRight class="h-6 w-6" /></button>
            </div>
            <div class="flex items-center justify-between">
              <span class="font-semibold text-slate-700">Hiển thị cho người học</span>
              <ToggleRight class="h-6 w-6 text-blue-600" />
            </div>
          </div>
          <button class="inline-flex h-10 w-full items-center justify-center gap-2 rounded-md bg-blue-600 px-3 text-sm font-semibold text-white hover:bg-blue-700" @click="saveCurrent">
            <Save class="h-4 w-4" />
            Lưu thay đổi
          </button>
        </div>
      </section>
      <section class="rounded-md border border-slate-200 bg-white p-4">
        <div class="text-sm font-bold uppercase text-slate-950">Trạng thái khóa học</div>
        <div class="mt-3 space-y-2">
          <div v-for="[name, desc, tone] in statusItems" :key="name" class="grid grid-cols-[1fr_1.2fr] items-center gap-3 text-xs">
            <div class="flex items-center gap-2 font-semibold text-slate-700">
              <span class="h-2.5 w-2.5 rounded-full" :class="tone"></span>
              {{ name }}
            </div>
            <div class="text-slate-600">{{ desc }}</div>
          </div>
        </div>
      </section>
      <section class="rounded-md border border-slate-200 bg-white p-4">
        <div class="mb-3 flex items-center justify-between">
          <div class="text-sm font-bold uppercase text-slate-950">Checklist publish</div>
          <div class="text-xs font-bold text-blue-700">{{ checklistPassed }}/{{ checklistItems.length }}</div>
        </div>
        <PublishChecklist :checklist="checklist" />
      </section>
      <section class="rounded-md border border-slate-200 bg-white p-4">
        <div class="text-sm font-bold uppercase text-slate-950">Trạng chính</div>
        <div class="mt-3 space-y-2">
          <div v-for="item in actionItems" :key="item" class="flex items-start gap-2 text-xs text-slate-700">
            <CheckCircle2 class="mt-0.5 h-3.5 w-3.5 shrink-0 text-emerald-600" />
            <span>{{ item }}</span>
          </div>
        </div>
      </section>
      <CompletionRuleEditor v-if="component" :model-value="component?.config?.completion_rule || { type: 'view' }" />
      <CloPloMapper v-if="component" :model-value="component?.config?.clo_mapping || []" />
      </template>

      <template v-else>
        <section class="rounded-md border border-slate-200 bg-white p-4">
          <div class="text-xs font-bold uppercase text-blue-700">Thông tin đang chọn</div>
          <div class="mt-3 space-y-2">
            <div v-for="[label, value] in currentInfo" :key="label" class="grid grid-cols-[92px_minmax(0,1fr)] gap-3 text-xs">
              <div class="font-semibold text-slate-500">{{ label }}</div>
              <div class="min-w-0 break-words font-semibold text-slate-800">{{ value }}</div>
            </div>
          </div>
        </section>

        <section v-if="component?.component_type === 'quiz'" class="rounded-md border border-amber-200 bg-amber-50 p-4">
          <div class="text-xs font-bold uppercase text-amber-800">Đề trắc nghiệm đã gắn</div>
          <div class="mt-3 rounded-md bg-white p-3 text-xs text-slate-700">
            <div class="font-bold text-slate-950">{{ component.config?.exam_title || 'Chưa gắn đề thi' }}</div>
            <div class="mt-1 text-slate-500">
              {{ component.config?.exam_code || `#${component.config?.exam_id || '-'}` }} · {{ examLoading ? 'Đang load...' : `${quizQuestionCount} câu` }} · {{ component.config?.duration_minutes || 0 }} phút
            </div>
            <div class="mt-2 leading-5">{{ quizNote }}</div>
            <div v-if="component.config?.exam_id" class="mt-3 grid grid-cols-2 gap-2">
              <button class="inline-flex h-9 items-center justify-center gap-2 rounded-md border border-slate-300 bg-white px-2 text-xs font-semibold text-slate-700 hover:bg-slate-50" @click="questionPreviewOpen = true">
                <Eye class="h-3.5 w-3.5" />
                Xem câu
              </button>
              <button class="inline-flex h-9 items-center justify-center gap-2 rounded-md border border-amber-300 bg-amber-100 px-2 text-xs font-semibold text-amber-800 hover:bg-amber-200" @click="openExamEditor">
                <ExternalLink class="h-3.5 w-3.5" />
                Chỉnh đề
              </button>
            </div>
          </div>
        </section>

        <section class="rounded-md border border-slate-200 bg-white p-4">
          <div class="text-xs font-bold uppercase text-slate-700">Cấu hình lưu trong hệ thống</div>
          <div class="mt-3 space-y-2 text-xs">
            <div v-for="(value, key) in configInfo" :key="key" class="rounded-md bg-slate-50 p-2">
              <div class="font-bold text-slate-700">{{ key }}</div>
              <pre class="mt-1 max-h-28 overflow-auto whitespace-pre-wrap break-words text-slate-600">{{ typeof value === 'object' ? JSON.stringify(value, null, 2) : value }}</pre>
            </div>
            <div v-if="!Object.keys(configInfo || {}).length" class="rounded-md border border-dashed border-slate-300 p-4 text-center text-slate-500">
              Chưa có cấu hình chi tiết.
            </div>
          </div>
        </section>

        <section class="rounded-md border border-slate-200 bg-white p-4">
          <div class="mb-3 flex items-center justify-between">
            <div class="text-xs font-bold uppercase text-slate-700">Checklist publish</div>
            <div class="text-xs font-bold text-blue-700">{{ checklistPassed }}/{{ checklistItems.length }}</div>
          </div>
          <PublishChecklist :checklist="checklist" />
        </section>
      </template>
    </div>

    <div v-if="questionPreviewOpen" class="fixed inset-0 z-50">
      <button class="absolute inset-0 bg-slate-950/25" @click="questionPreviewOpen = false"></button>
      <aside class="absolute right-4 top-4 flex h-[calc(100vh-2rem)] w-[720px] max-w-[calc(100vw-2rem)] flex-col overflow-hidden rounded-xl border border-slate-200 bg-white shadow-2xl">
        <div class="flex h-16 items-center justify-between border-b border-slate-200 px-5">
          <div>
            <h2 class="text-base font-bold text-slate-950">Xem nhanh câu hỏi</h2>
            <p class="mt-0.5 text-xs text-slate-500">{{ examDetail?.title || component?.config?.exam_title }} · {{ quizQuestionCount }} câu</p>
          </div>
          <button class="grid h-9 w-9 place-items-center rounded-md text-slate-500 hover:bg-slate-100" @click="questionPreviewOpen = false">
            <X class="h-4 w-4" />
          </button>
        </div>
        <div class="min-h-0 flex-1 overflow-auto p-4">
          <div v-if="examLoading" class="p-6 text-center text-sm text-slate-500">Đang tải câu hỏi...</div>
          <div v-else-if="!examDetail?.questions?.length" class="rounded-md border border-dashed border-slate-300 p-8 text-center text-sm text-slate-500">Đề này chưa có câu hỏi hoặc chưa build đề.</div>
          <div v-else class="space-y-3">
            <article v-for="(examQuestion, index) in examDetail.questions" :key="examQuestion.id" class="rounded-md border border-slate-200 bg-white p-4">
              <div class="flex items-start justify-between gap-3">
                <div class="text-sm font-bold text-slate-950">Câu {{ index + 1 }} · {{ examQuestion.score || 0 }} điểm</div>
                <div class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-600">{{ examQuestion.question?.question_type || 'question' }}</div>
              </div>
              <div class="mt-2 text-sm font-semibold text-slate-800">{{ examQuestion.question?.title }}</div>
              <div class="mt-1 text-sm leading-6 text-slate-600">{{ examQuestion.question?.stem }}</div>
            </article>
          </div>
        </div>
      </aside>
    </div>
  </aside>
</template>
