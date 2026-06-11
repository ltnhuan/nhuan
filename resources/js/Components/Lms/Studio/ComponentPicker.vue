<script setup>
import { computed, ref, watch } from 'vue'
import { BookOpenText, FileText, MessageCircle, PenLine, Play, Presentation, ScrollText, Video } from '@lucide/vue'

const props = defineProps({ open: { type: Boolean, default: false } })
const emit = defineEmits(['pick', 'close'])

const types = [
  { key: 'text', label: 'Bài học văn bản', sub: 'Soạn nội dung đọc', icon: BookOpenText, rule: 'view', title: 'Bài học văn bản', standard: 'html5' },
  { key: 'video', label: 'Video', sub: 'Gắn video từ kho học liệu hoặc YouTube', icon: Play, rule: 'view_percent', title: 'Video bài giảng', standard: 'xapi' },
  { key: 'pdf', label: 'Tài liệu', sub: 'Load PDF/File từ kho', icon: FileText, rule: 'view', title: 'Tài liệu học tập', standard: 'html5' },
  { key: 'quiz', label: 'Bài kiểm tra', sub: 'Load câu hỏi từ kho đề', icon: ScrollText, rule: 'score', title: 'Bài kiểm tra', standard: 'qti_3' },
  { key: 'assignment', label: 'Bài tập', sub: 'Load bài tập đã tạo', icon: PenLine, rule: 'submission', title: 'Bài tập', standard: 'lti_1_3_ags' },
  { key: 'forum', label: 'Diễn đàn', sub: 'Tạo thảo luận', icon: MessageCircle, rule: 'participation', title: 'Diễn đàn thảo luận', standard: 'xapi' },
  { key: 'scorm', label: 'SCORM / xAPI', sub: 'Load gói SCORM, xAPI hoặc cmi5', icon: Presentation, rule: 'package_status', title: 'Gói học liệu chuẩn', standard: 'scorm_2004' },
  { key: 'live_session', label: 'Buổi học trực tuyến', sub: 'Tạo buổi học live', icon: Video, rule: 'attendance', title: 'Buổi học trực tuyến', standard: 'lti_1_3' },
]

const selectedType = ref('quiz')
const form = ref({})

const selectedMeta = computed(() => types.find((item) => item.key === selectedType.value) || types[0])
const isAssessment = computed(() => ['quiz', 'assignment'].includes(selectedType.value))
const usesRepository = computed(() => ['video', 'pdf', 'quiz', 'assignment', 'scorm'].includes(selectedType.value))
const isMedia = computed(() => ['video', 'live_session'].includes(selectedType.value))

function defaultsFor(type) {
  const meta = types.find((item) => item.key === type) || types[0]
  return {
    title: meta.title,
    estimated_minutes: type === 'quiz' ? 45 : type === 'live_session' ? 60 : 10,
    required: true,
    release_mode: 'immediate',
    prerequisite_mode: 'previous_required',
    completion_rule: meta.rule,
    mastery_score: type === 'quiz' ? 50 : 80,
    total_score: 100,
    max_attempts: type === 'quiz' ? 1 : 0,
    grade_weight: isAssessmentType(type) ? 10 : 0,
    shuffle_questions: type === 'quiz',
    shuffle_options: type === 'quiz',
    show_feedback: 'after_submit',
    standard_profile: meta.standard,
    tracking_profile: type === 'quiz' ? 'answered_scored_completed' : 'experienced_completed',
    captions_required: type === 'video',
    transcript_required: type === 'video',
    alt_text_required: ['text', 'pdf', 'quiz'].includes(type),
    open_in_new_window: type === 'scorm',
    require_secure_launch: ['scorm', 'live_session', 'assignment'].includes(type),
  }
}

function isAssessmentType(type) {
  return ['quiz', 'assignment'].includes(type)
}

function resetForm(type = selectedType.value) {
  form.value = defaultsFor(type)
}

function selectType(type) {
  selectedType.value = type
  resetForm(type)
}

function submit() {
  const type = selectedType.value
  const payload = {
    type,
    title: form.value.title || selectedMeta.value.title,
    required: form.value.required,
    config: {
      estimated_minutes: Number(form.value.estimated_minutes) || 0,
      completion_rule: {
        type: form.value.completion_rule,
        mastery_score: Number(form.value.mastery_score) || 0,
      },
      release: {
        mode: form.value.release_mode,
        prerequisite_mode: form.value.prerequisite_mode,
      },
      grading: {
        total_score: Number(form.value.total_score) || 0,
        pass_score: Number(form.value.mastery_score) || 0,
        weight: Number(form.value.grade_weight) || 0,
        max_attempts: Number(form.value.max_attempts) || 0,
        show_feedback: form.value.show_feedback,
      },
      assessment: type === 'quiz' ? {
        shuffle_questions: Boolean(form.value.shuffle_questions),
        shuffle_options: Boolean(form.value.shuffle_options),
        question_delivery: 'one_by_one',
        prevent_backtracking: false,
      } : null,
      accessibility: {
        captions_required: Boolean(form.value.captions_required),
        transcript_required: Boolean(form.value.transcript_required),
        alt_text_required: Boolean(form.value.alt_text_required),
      },
      interoperability: {
        standard_profile: form.value.standard_profile,
        tracking_profile: form.value.tracking_profile,
        secure_launch: Boolean(form.value.require_secure_launch),
        open_in_new_window: Boolean(form.value.open_in_new_window),
      },
      source_policy: {
        must_load_from_repository: usesRepository.value,
        accepted_sources: sourceLabels(type),
      },
      clo_mapping: [],
      ...(type === 'live_session' ? { provider: 'zoom', meeting_url: '', min_attended_minutes: 1 } : {}),
    },
  }

  emit('pick', payload)
}

function sourceLabels(type) {
  if (type === 'quiz') return ['question_bank', 'qti']
  if (type === 'video') return ['repository_video', 'youtube', 'audio']
  if (type === 'scorm') return ['scorm', 'xapi', 'cmi5']
  if (type === 'assignment') return ['assignment_bank', 'lti']
  if (type === 'pdf') return ['repository_file', 'pdf']
  return ['inline']
}

watch(() => props.open, (open) => {
  if (open) resetForm()
}, { immediate: true })
</script>

<template>
  <div v-if="open" class="fixed inset-0 z-50">
    <button class="absolute inset-0 bg-slate-950/20" @click="emit('close')"></button>
    <div class="absolute right-4 top-4 flex h-[calc(100vh-2rem)] w-[min(980px,calc(100vw-2rem))] flex-col overflow-hidden rounded-xl border border-slate-200 bg-white shadow-2xl">
      <div class="flex h-20 shrink-0 items-center justify-between border-b border-slate-200 px-6">
        <div>
          <h2 class="text-lg font-bold text-slate-950">Thêm hoạt động vào tiến trình</h2>
          <p class="mt-1 text-sm text-slate-500">Chọn loại, cài đặt điều kiện học, chấm điểm, tracking và nguồn dữ liệu trước khi load từ kho.</p>
        </div>
        <button class="grid h-9 w-9 place-items-center rounded-md text-slate-500 hover:bg-slate-100" @click="emit('close')">×</button>
      </div>

      <div class="grid min-h-0 flex-1 grid-cols-[300px_minmax(0,1fr)]">
        <aside class="min-h-0 overflow-y-auto border-r border-slate-200 bg-slate-50 p-4">
          <button
            v-for="item in types"
            :key="item.key"
            class="mb-2 flex w-full items-center gap-3 rounded-lg border p-3 text-left transition"
            :class="selectedType === item.key ? 'border-blue-300 bg-white shadow-sm ring-1 ring-blue-100' : 'border-transparent bg-transparent hover:border-slate-200 hover:bg-white'"
            @click="selectType(item.key)"
          >
            <span class="grid h-11 w-11 shrink-0 place-items-center rounded-lg bg-white text-blue-600 shadow-sm ring-1 ring-slate-200">
              <component :is="item.icon" class="h-5 w-5" />
            </span>
            <span class="min-w-0">
              <span class="block text-sm font-bold text-slate-900">{{ item.label }}</span>
              <span class="mt-0.5 block text-xs text-slate-500">{{ item.sub }}</span>
            </span>
          </button>
        </aside>

        <main class="min-h-0 overflow-y-auto p-5">
          <div class="mb-4 rounded-md border border-blue-100 bg-blue-50 px-4 py-3 text-sm text-blue-900">
            <div class="font-bold">{{ selectedMeta.label }}</div>
            <div class="mt-1 text-blue-800">
              <span v-if="usesRepository">Sau khi tạo, Studio sẽ mở bước load dữ liệu từ kho phù hợp.</span>
              <span v-else>Hoạt động này có thể soạn trực tiếp trong phần chỉnh nội dung.</span>
            </div>
          </div>

          <div class="grid gap-4 xl:grid-cols-2">
            <section class="rounded-lg border border-slate-200 bg-white p-4">
              <h3 class="text-sm font-bold text-slate-900">Thiết lập cơ bản</h3>
              <label class="mt-4 block text-xs font-semibold text-slate-600">Tên hoạt động</label>
              <input v-model="form.title" class="mt-1 h-10 w-full rounded-md border border-slate-300 px-3 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100" />

              <div class="mt-4 grid grid-cols-2 gap-3">
                <label class="block text-xs font-semibold text-slate-600">
                  Thời lượng phút
                  <input v-model.number="form.estimated_minutes" type="number" min="0" class="mt-1 h-10 w-full rounded-md border border-slate-300 px-3 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100" />
                </label>
                <label class="block text-xs font-semibold text-slate-600">
                  Bắt buộc
                  <select v-model="form.required" class="mt-1 h-10 w-full rounded-md border border-slate-300 px-3 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100">
                    <option :value="true">Bắt buộc hoàn thành</option>
                    <option :value="false">Không bắt buộc</option>
                  </select>
                </label>
              </div>

              <div class="mt-4 grid grid-cols-2 gap-3">
                <label class="block text-xs font-semibold text-slate-600">
                  Mở học
                  <select v-model="form.release_mode" class="mt-1 h-10 w-full rounded-md border border-slate-300 px-3 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100">
                    <option value="immediate">Mở ngay</option>
                    <option value="scheduled">Theo lịch</option>
                    <option value="manual">Giảng viên mở thủ công</option>
                  </select>
                </label>
                <label class="block text-xs font-semibold text-slate-600">
                  Điều kiện trước
                  <select v-model="form.prerequisite_mode" class="mt-1 h-10 w-full rounded-md border border-slate-300 px-3 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100">
                    <option value="previous_required">Hoàn thành mục trước</option>
                    <option value="unit_required">Hoàn thành unit trước</option>
                    <option value="none">Không ràng buộc</option>
                  </select>
                </label>
              </div>
            </section>

            <section class="rounded-lg border border-slate-200 bg-white p-4">
              <h3 class="text-sm font-bold text-slate-900">Hoàn thành và tracking</h3>
              <label class="mt-4 block text-xs font-semibold text-slate-600">Quy tắc hoàn thành</label>
              <select v-model="form.completion_rule" class="mt-1 h-10 w-full rounded-md border border-slate-300 px-3 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100">
                <option value="view">Xem nội dung</option>
                <option value="view_percent">Xem đủ phần trăm nội dung</option>
                <option value="score">Đạt điểm yêu cầu</option>
                <option value="submission">Nộp bài</option>
                <option value="attendance">Có mặt đủ thời lượng</option>
                <option value="participation">Có tham gia thảo luận</option>
                <option value="package_status">Theo trạng thái gói SCORM/xAPI</option>
              </select>

              <div class="mt-4 grid grid-cols-2 gap-3">
                <label class="block text-xs font-semibold text-slate-600">
                  Chuẩn nội dung
                  <select v-model="form.standard_profile" class="mt-1 h-10 w-full rounded-md border border-slate-300 px-3 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100">
                    <option value="html5">HTML5 LMS native</option>
                    <option value="qti_3">QTI 3 assessment</option>
                    <option value="lti_1_3">LTI 1.3</option>
                    <option value="lti_1_3_ags">LTI 1.3 + AGS</option>
                    <option value="scorm_2004">SCORM 2004</option>
                    <option value="xapi">xAPI</option>
                    <option value="cmi5">cmi5</option>
                  </select>
                </label>
                <label class="block text-xs font-semibold text-slate-600">
                  Tracking
                  <select v-model="form.tracking_profile" class="mt-1 h-10 w-full rounded-md border border-slate-300 px-3 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100">
                    <option value="experienced_completed">experienced + completed</option>
                    <option value="answered_scored_completed">answered + scored + completed</option>
                    <option value="launched_suspended_completed">launched + suspended + completed</option>
                    <option value="attended_completed">attended + completed</option>
                  </select>
                </label>
              </div>
            </section>

            <section v-if="isAssessment" class="rounded-lg border border-amber-200 bg-amber-50 p-4">
              <h3 class="text-sm font-bold text-amber-950">Cài đặt kiểm tra và chấm điểm</h3>
              <div class="mt-4 grid grid-cols-2 gap-3">
                <label class="block text-xs font-semibold text-slate-700">
                  Điểm đạt
                  <input v-model.number="form.mastery_score" type="number" min="0" class="mt-1 h-10 w-full rounded-md border border-amber-200 px-3 text-sm focus:border-amber-500 focus:outline-none focus:ring-2 focus:ring-amber-100" />
                </label>
                <label class="block text-xs font-semibold text-slate-700">
                  Tổng điểm
                  <input v-model.number="form.total_score" type="number" min="0" class="mt-1 h-10 w-full rounded-md border border-amber-200 px-3 text-sm focus:border-amber-500 focus:outline-none focus:ring-2 focus:ring-amber-100" />
                </label>
                <label class="block text-xs font-semibold text-slate-700">
                  Số lần làm
                  <input v-model.number="form.max_attempts" type="number" min="0" class="mt-1 h-10 w-full rounded-md border border-amber-200 px-3 text-sm focus:border-amber-500 focus:outline-none focus:ring-2 focus:ring-amber-100" />
                </label>
                <label class="block text-xs font-semibold text-slate-700">
                  Trọng số điểm
                  <input v-model.number="form.grade_weight" type="number" min="0" class="mt-1 h-10 w-full rounded-md border border-amber-200 px-3 text-sm focus:border-amber-500 focus:outline-none focus:ring-2 focus:ring-amber-100" />
                </label>
              </div>
              <div v-if="selectedType === 'quiz'" class="mt-4 grid grid-cols-2 gap-3">
                <label class="flex h-10 items-center gap-2 rounded-md bg-white px-3 text-sm font-semibold text-slate-700">
                  <input v-model="form.shuffle_questions" type="checkbox" class="h-4 w-4 rounded border-slate-300 text-blue-600" />
                  Đảo câu hỏi
                </label>
                <label class="flex h-10 items-center gap-2 rounded-md bg-white px-3 text-sm font-semibold text-slate-700">
                  <input v-model="form.shuffle_options" type="checkbox" class="h-4 w-4 rounded border-slate-300 text-blue-600" />
                  Đảo đáp án
                </label>
              </div>
            </section>

            <section class="rounded-lg border border-slate-200 bg-white p-4">
              <h3 class="text-sm font-bold text-slate-900">Accessibility và bảo mật</h3>
              <div class="mt-4 grid gap-2">
                <label v-if="isMedia" class="flex h-10 items-center gap-2 rounded-md bg-slate-50 px-3 text-sm font-semibold text-slate-700">
                  <input v-model="form.captions_required" type="checkbox" class="h-4 w-4 rounded border-slate-300 text-blue-600" />
                  Yêu cầu caption/subtitle
                </label>
                <label v-if="isMedia" class="flex h-10 items-center gap-2 rounded-md bg-slate-50 px-3 text-sm font-semibold text-slate-700">
                  <input v-model="form.transcript_required" type="checkbox" class="h-4 w-4 rounded border-slate-300 text-blue-600" />
                  Yêu cầu transcript
                </label>
                <label class="flex h-10 items-center gap-2 rounded-md bg-slate-50 px-3 text-sm font-semibold text-slate-700">
                  <input v-model="form.alt_text_required" type="checkbox" class="h-4 w-4 rounded border-slate-300 text-blue-600" />
                  Kiểm tra alt text cho hình ảnh
                </label>
                <label class="flex h-10 items-center gap-2 rounded-md bg-slate-50 px-3 text-sm font-semibold text-slate-700">
                  <input v-model="form.require_secure_launch" type="checkbox" class="h-4 w-4 rounded border-slate-300 text-blue-600" />
                  Bắt buộc secure launch/token
                </label>
                <label v-if="selectedType === 'scorm'" class="flex h-10 items-center gap-2 rounded-md bg-slate-50 px-3 text-sm font-semibold text-slate-700">
                  <input v-model="form.open_in_new_window" type="checkbox" class="h-4 w-4 rounded border-slate-300 text-blue-600" />
                  Mở gói trong cửa sổ riêng
                </label>
              </div>
            </section>
          </div>
        </main>
      </div>

      <div class="flex shrink-0 items-center justify-between border-t border-slate-200 bg-white px-5 py-4">
        <div class="text-xs text-slate-500">
          Nguồn load: {{ sourceLabels(selectedType).join(', ') }}
        </div>
        <div class="flex items-center gap-2">
          <button class="h-10 rounded-md border border-slate-300 bg-white px-5 text-sm font-semibold text-slate-700 hover:bg-slate-50" @click="emit('close')">Hủy</button>
          <button class="h-10 rounded-md bg-blue-600 px-5 text-sm font-semibold text-white shadow-sm hover:bg-blue-700" @click="submit">
            Tạo và load dữ liệu
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
