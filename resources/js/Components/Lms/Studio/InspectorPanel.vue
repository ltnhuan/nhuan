<script setup>
import { computed, ref, watch } from 'vue'
import { CheckCircle2, Database, Save, ToggleRight } from '@lucide/vue'
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
</script>

<template>
  <aside class="w-[330px] shrink-0 border-l border-slate-200 bg-white">
    <div class="grid grid-cols-2 border-b border-slate-200 text-sm font-semibold">
      <button class="border-b-2 border-blue-600 px-4 py-3 text-blue-700">Thiết lập</button>
      <button class="px-4 py-3 text-slate-500">Thông tin</button>
    </div>
    <div class="max-h-[calc(100vh-15rem)] space-y-4 overflow-auto p-4 text-sm">
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
              Đã gắn đề: <span class="font-bold text-slate-900">{{ component.config.exam_title || `#${component.config.exam_id}` }}</span>
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
    </div>
  </aside>
</template>
