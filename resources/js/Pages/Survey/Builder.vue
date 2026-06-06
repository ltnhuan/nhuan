<script setup>
import EraLmsLayout from '@/Layouts/EraLmsLayout.vue'
import { computed, ref } from 'vue'

const questionTypes = [
  { key: 'text', label: 'Text' },
  { key: 'rating', label: 'Rating' },
  { key: 'matrix', label: 'Matrix' },
  { key: 'mcq', label: 'MCQ' },
  { key: 'multi_select', label: 'Multi Select' },
  { key: 'nps', label: 'NPS' },
]

const questions = ref([
  { code: 'expertise', question_type: 'rating', prompt: 'Chuyên môn', required: true },
  { code: 'methodology', question_type: 'rating', prompt: 'Phương pháp giảng dạy', required: true },
  { code: 'interaction', question_type: 'rating', prompt: 'Tương tác với học viên', required: true },
  { code: 'course_matrix', question_type: 'matrix', prompt: 'Nội dung / Bài giảng / Khối lượng / Đánh giá', rows: ['Nội dung', 'Bài giảng', 'Khối lượng', 'Đánh giá'], columns: [1, 2, 3, 4, 5] },
  { code: 'teacher_nps', question_type: 'nps', prompt: 'Recommendation Score', required: true },
])
const draggedIndex = ref(null)
const selected = ref(questions.value[0])

const payload = computed(() => ({
  code: 'SURVEY-BUILDER-DEMO',
  title: 'Mẫu khảo sát chất lượng',
  survey_type: 'teacher_evaluation',
  questions: questions.value.map((question, index) => ({ ...question, sort_order: index + 1 })),
}))

const payloadSummary = computed(() => ({
  code: payload.value.code,
  title: payload.value.title,
  surveyType: payload.value.survey_type,
  questionCount: payload.value.questions.length,
  requiredCount: payload.value.questions.filter((question) => question.required).length,
}))

function addQuestion(type) {
  const next = questions.value.length + 1
  const question = {
    code: `${type}_${next}`,
    question_type: type,
    prompt: type === 'nps' ? 'Bạn có giới thiệu khóa học/giảng viên này không?' : 'Câu hỏi mới',
    required: false,
    options: ['Lựa chọn 1', 'Lựa chọn 2'],
    rows: ['Tiêu chí 1', 'Tiêu chí 2'],
    columns: [1, 2, 3, 4, 5],
  }
  questions.value.push(question)
  selected.value = question
}

function dropQuestion(index) {
  if (draggedIndex.value === null || draggedIndex.value === index) return
  const moved = questions.value.splice(draggedIndex.value, 1)[0]
  questions.value.splice(index, 0, moved)
  draggedIndex.value = null
}
</script>

<template>
  <EraLmsLayout>
    <template #breadcrumb>Khảo sát / Survey Builder</template>
    <section class="bg-white border-b">
      <div class="mx-auto max-w-7xl px-6 py-5">
        <div class="flex items-center justify-between gap-4">
          <div>
            <h1 class="text-lg font-semibold">Survey Builder</h1>
            <p class="mt-1 text-sm text-slate-600">Kéo thả câu hỏi, cấu hình ẩn danh/định danh và lưu mẫu khảo sát dùng cho campaign.</p>
          </div>
          <div class="flex gap-2">
            <button class="rounded-md border px-3 py-2 text-sm">Lưu nháp</button>
            <button class="rounded-md bg-blue-900 px-3 py-2 text-sm text-white">Xuất bản</button>
          </div>
        </div>
        <div class="mt-5 grid gap-3 md:grid-cols-4">
          <input class="rounded-md border px-3 py-2 text-sm md:col-span-2" value="Mẫu khảo sát chất lượng đào tạo" />
          <select class="rounded-md border px-3 py-2 text-sm">
            <option>Đánh giá giảng viên</option>
            <option>Đánh giá khóa học</option>
            <option>NPS toàn trường</option>
          </select>
          <label class="flex items-center gap-2 rounded-md border px-3 py-2 text-sm"><input type="checkbox" checked /> Ẩn danh mặc định</label>
        </div>
      </div>
    </section>

    <section class="mx-auto grid max-w-7xl gap-4 overflow-hidden px-6 py-5 lg:grid-cols-[220px_minmax(0,1fr)_340px]">
      <aside class="min-w-0 space-y-2">
        <button v-for="type in questionTypes" :key="type.key" class="flex w-full items-center justify-between rounded-md border bg-white px-3 py-2 text-sm hover:bg-sky-50" @click="addQuestion(type.key)">
          <span>{{ type.label }}</span><span class="text-slate-400">+</span>
        </button>
      </aside>

      <div class="min-w-0 space-y-3">
        <article
          v-for="(question, index) in questions"
          :key="question.code"
          draggable="true"
          class="border bg-white"
          :class="selected === question ? 'border-blue-800' : 'border-slate-200'"
          @dragstart="draggedIndex = index"
          @dragover.prevent
          @drop="dropQuestion(index)"
          @click="selected = question"
        >
          <div class="grid min-w-0 grid-cols-[44px_120px_minmax(0,1fr)_90px] items-center gap-3 px-4 py-3 text-sm">
            <button class="h-8 rounded-md border text-slate-500" title="Kéo thả">↕</button>
            <span class="rounded bg-slate-100 px-2 py-1 text-xs font-medium uppercase text-slate-600">{{ question.question_type }}</span>
            <input v-model="question.prompt" class="rounded-md border px-3 py-2 text-sm" />
            <label class="flex items-center gap-2"><input v-model="question.required" type="checkbox" /> Bắt buộc</label>
          </div>
          <div v-if="question.question_type === 'matrix'" class="grid grid-cols-4 gap-2 border-t px-4 py-3 text-xs text-slate-600">
            <span v-for="row in question.rows" :key="row" class="rounded bg-amber-50 px-2 py-1">{{ row }}</span>
          </div>
        </article>
      </div>

      <aside class="min-w-0 border bg-white p-4">
        <h2 class="text-sm font-semibold">Cấu hình câu hỏi</h2>
        <div v-if="selected" class="mt-4 space-y-3 text-sm">
          <label class="block">
            <span class="text-xs text-slate-500">Mã câu hỏi</span>
            <input v-model="selected.code" class="mt-1 w-full rounded-md border px-3 py-2" />
          </label>
          <label class="block">
            <span class="text-xs text-slate-500">Loại</span>
            <select v-model="selected.question_type" class="mt-1 w-full rounded-md border px-3 py-2">
              <option v-for="type in questionTypes" :key="type.key" :value="type.key">{{ type.label }}</option>
            </select>
          </label>
          <label class="block">
            <span class="text-xs text-slate-500">Thang điểm</span>
            <select class="mt-1 w-full rounded-md border px-3 py-2">
              <option>1-5</option>
              <option>0-10</option>
            </select>
          </label>
          <div class="rounded-md bg-slate-50 p-3">
            <div class="mb-2 text-xs font-semibold text-slate-500">Tóm tắt mẫu khảo sát</div>
            <dl class="grid grid-cols-2 gap-2 text-xs">
              <div><dt class="text-slate-500">Mã</dt><dd class="font-semibold">{{ payloadSummary.code }}</dd></div>
              <div><dt class="text-slate-500">Loại</dt><dd class="font-semibold">{{ payloadSummary.surveyType }}</dd></div>
              <div><dt class="text-slate-500">Số câu</dt><dd class="font-semibold">{{ payloadSummary.questionCount }}</dd></div>
              <div><dt class="text-slate-500">Bắt buộc</dt><dd class="font-semibold">{{ payloadSummary.requiredCount }}</dd></div>
            </dl>
          </div>
        </div>
      </aside>
    </section>
  </EraLmsLayout>
</template>
