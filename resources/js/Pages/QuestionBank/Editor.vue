<script setup>
import { computed, defineAsyncComponent, ref } from 'vue'
import EraLmsLayout from '@/Layouts/EraLmsLayout.vue'

const RichTextEditor = defineAsyncComponent(() => import('@/Components/Lms/RichTextEditor.vue'))

defineProps({
  apiHeaders: { type: Object, default: () => ({}) },
})

const type = ref('single_choice')
const questionStem = ref('<p>Nhập nội dung câu hỏi có dấu tiếng Việt đầy đủ.</p>')
const message = ref('')
const options = ref([
  { content: 'Đáp án A', correct: true, feedback: 'Phản hồi cho đáp án A' },
  { content: 'Đáp án B', correct: false, feedback: 'Phản hồi cho đáp án B' },
])
const fillBlanks = ref([{ key: 'blank_1', answer: 'EraLMS' }])
const pairs = ref([{ left: 'CLO', right: 'Course Learning Outcome' }, { left: 'PLO', right: 'Program Learning Outcome' }])
const previewTitle = computed(() => type.value === 'essay' ? 'Câu tự luận' : 'Câu hỏi có đáp án cấu trúc')
const typeLabels = {
  single_choice: 'Một đáp án',
  multiple_choice: 'Nhiều đáp án',
  true_false: 'Đúng/Sai',
  essay: 'Tự luận',
  fill_blank: 'Điền khuyết',
  matching: 'Ghép đôi',
  audio: 'Audio',
  image: 'Hình ảnh',
  video: 'Video',
}

function previewQuestion() {
  message.value = `Đã cập nhật preview cho ${typeLabels[type.value]}.`
}

function saveDraft() {
  message.value = 'Đã lưu nháp câu hỏi trên màn hình soạn.'
}

function submitReview() {
  message.value = 'Đã đánh dấu câu hỏi sẵn sàng gửi duyệt.'
}
</script>

<template>
  <EraLmsLayout>
    <template #breadcrumb>Ngân hàng câu hỏi / Soạn câu hỏi</template>

    <section class="space-y-5">
      <div class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
        <div class="grid gap-6 bg-slate-950 px-6 py-6 text-white xl:grid-cols-[1fr_420px]">
          <div>
            <div class="text-xs font-semibold uppercase tracking-[0.18em] text-blue-200">Question Authoring</div>
            <h1 class="mt-2 text-2xl font-bold">Trình soạn câu hỏi chuẩn hóa</h1>
            <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-300">Soạn câu hỏi nhiều định dạng, gắn CLO/Bloom/độ khó, kiểm tra đáp án và xem trước trước khi lưu nháp hoặc gửi duyệt.</p>
          </div>
          <div class="grid grid-cols-3 gap-3 text-center">
            <div class="rounded-md bg-white/10 p-4">
              <div class="text-2xl font-bold">9</div>
              <div class="mt-1 text-xs text-slate-300">Loại câu hỏi</div>
            </div>
            <div class="rounded-md bg-white/10 p-4">
              <div class="text-2xl font-bold">CLO1</div>
              <div class="mt-1 text-xs text-slate-300">Mapping</div>
            </div>
            <div class="rounded-md bg-emerald-500/20 p-4">
              <div class="text-2xl font-bold">Ready</div>
              <div class="mt-1 text-xs text-emerald-100">Validation</div>
            </div>
          </div>
        </div>
        <div class="flex flex-wrap items-center gap-2 px-6 py-3">
          <button class="rounded-md border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50" @click="previewQuestion">Preview</button>
          <button class="rounded-md border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50" @click="saveDraft">Lưu nháp</button>
          <button class="rounded-md bg-blue-600 px-3 py-2 text-sm font-semibold text-white hover:bg-blue-700" @click="submitReview">Gửi duyệt</button>
          <span class="ml-auto rounded-full bg-blue-50 px-3 py-1 text-xs font-semibold text-blue-700">{{ typeLabels[type] }}</span>
        </div>
      </div>
      <div v-if="message" class="rounded-md border border-cyan-200 bg-cyan-50 px-4 py-3 text-sm text-cyan-900">{{ message }}</div>

      <div class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_380px]">
        <main class="min-w-0 space-y-5">
          <section class="rounded-lg border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 px-5 py-4">
              <h2 class="text-sm font-bold text-slate-900">Thiết lập câu hỏi</h2>
              <p class="mt-1 text-xs text-slate-500">Chọn loại câu hỏi, độ khó và taxonomy đánh giá.</p>
            </div>
            <div class="grid gap-4 p-5 md:grid-cols-3">
              <label class="space-y-1">
                <span class="text-xs font-semibold uppercase tracking-wide text-slate-500">Loại câu hỏi</span>
                <select v-model="type" class="h-11 w-full rounded-md border border-slate-300 bg-white px-3 text-sm outline-none focus:border-blue-500 focus:ring-4 focus:ring-blue-100">
                  <option v-for="(label, key) in typeLabels" :key="key" :value="key">{{ label }}</option>
                </select>
              </label>
              <label class="space-y-1">
                <span class="text-xs font-semibold uppercase tracking-wide text-slate-500">Độ khó</span>
                <select class="h-11 w-full rounded-md border border-slate-300 bg-white px-3 text-sm outline-none focus:border-blue-500 focus:ring-4 focus:ring-blue-100"><option>Trung bình</option><option>Dễ</option><option>Khó</option></select>
              </label>
              <label class="space-y-1">
                <span class="text-xs font-semibold uppercase tracking-wide text-slate-500">Bloom</span>
                <select class="h-11 w-full rounded-md border border-slate-300 bg-white px-3 text-sm outline-none focus:border-blue-500 focus:ring-4 focus:ring-blue-100"><option>Vận dụng</option><option>Hiểu</option><option>Phân tích</option></select>
              </label>
            </div>
            <div class="border-t border-slate-200 p-5">
              <div class="space-y-1">
                <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">Nội dung câu hỏi</div>
                <RichTextEditor v-model="questionStem" :api-headers="apiHeaders" min-height="260px" placeholder="Nhập nội dung câu hỏi, chèn ảnh, âm thanh hoặc video..." />
              </div>
            </div>
          </section>

          <section v-if="['single_choice','multiple_choice','true_false','ordering'].includes(type)" class="rounded-lg border border-slate-200 bg-white shadow-sm">
            <div class="flex items-center justify-between gap-3 border-b border-slate-200 px-5 py-4">
              <div>
                <h2 class="text-sm font-bold text-slate-900">Lựa chọn đáp án</h2>
                <p class="mt-1 text-xs text-slate-500">Đánh dấu đáp án đúng và nhập feedback riêng cho từng lựa chọn.</p>
              </div>
              <button class="rounded-md bg-slate-900 px-3 py-2 text-sm font-semibold text-white" @click="options.push({ content: 'Đáp án mới', correct: false, feedback: '' })">Thêm đáp án</button>
            </div>
            <div class="space-y-3 p-5">
              <div v-for="(option, index) in options" :key="index" class="grid gap-3 rounded-md border border-slate-200 bg-slate-50 p-3 md:grid-cols-[44px_1fr_1fr]">
                <label class="grid h-11 place-items-center rounded-md bg-white">
                  <input v-model="option.correct" type="checkbox" class="h-4 w-4 rounded border-slate-300 text-blue-600" />
                </label>
                <input v-model="option.content" class="h-11 rounded-md border border-slate-300 bg-white px-3 text-sm outline-none focus:border-blue-500 focus:ring-4 focus:ring-blue-100" />
                <input v-model="option.feedback" class="h-11 rounded-md border border-slate-300 bg-white px-3 text-sm outline-none focus:border-blue-500 focus:ring-4 focus:ring-blue-100" placeholder="Feedback" />
              </div>
            </div>
          </section>

          <section v-if="type === 'fill_blank'" class="rounded-lg border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 px-5 py-4">
              <h2 class="text-sm font-bold text-slate-900">Đáp án điền khuyết</h2>
            </div>
            <div class="space-y-3 p-5">
              <div v-for="blank in fillBlanks" :key="blank.key" class="grid gap-3 md:grid-cols-2">
                <input v-model="blank.key" class="h-11 rounded-md border border-slate-300 px-3 text-sm outline-none focus:border-blue-500 focus:ring-4 focus:ring-blue-100" />
                <input v-model="blank.answer" class="h-11 rounded-md border border-slate-300 px-3 text-sm outline-none focus:border-blue-500 focus:ring-4 focus:ring-blue-100" />
              </div>
            </div>
          </section>

          <section v-if="type === 'matching'" class="rounded-lg border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 px-5 py-4">
              <h2 class="text-sm font-bold text-slate-900">Cặp ghép đôi</h2>
            </div>
            <div class="space-y-3 p-5">
              <div v-for="pair in pairs" :key="pair.left" class="grid gap-3 md:grid-cols-2">
                <input v-model="pair.left" class="h-11 rounded-md border border-slate-300 px-3 text-sm outline-none focus:border-blue-500 focus:ring-4 focus:ring-blue-100" />
                <input v-model="pair.right" class="h-11 rounded-md border border-slate-300 px-3 text-sm outline-none focus:border-blue-500 focus:ring-4 focus:ring-blue-100" />
              </div>
            </div>
          </section>
        </main>

        <aside class="min-w-0 space-y-5">
          <section class="rounded-lg border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 px-5 py-4">
              <h2 class="text-sm font-bold text-slate-900">Thông tin chuẩn hóa</h2>
              <p class="mt-1 text-xs text-slate-500">Metadata dùng cho lọc, blueprint và phân tích phủ chuẩn đầu ra.</p>
            </div>
            <div class="space-y-3 p-5">
              <input class="h-11 w-full rounded-md border border-slate-300 px-3 text-sm outline-none focus:border-blue-500 focus:ring-4 focus:ring-blue-100" placeholder="Điểm mặc định" />
              <input class="h-11 w-full rounded-md border border-slate-300 px-3 text-sm outline-none focus:border-blue-500 focus:ring-4 focus:ring-blue-100" placeholder="Tag: Thi cuối kỳ, CLO1" />
              <select class="h-11 w-full rounded-md border border-slate-300 bg-white px-3 text-sm outline-none focus:border-blue-500 focus:ring-4 focus:ring-blue-100"><option>Gắn CLO1 - Nhận biết</option></select>
            </div>
          </section>

          <section class="rounded-lg border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 px-5 py-4">
              <h2 class="text-sm font-bold text-slate-900">Preview</h2>
            </div>
            <div class="p-5">
              <div class="rounded-lg border border-blue-200 bg-blue-50 p-4">
                <p class="text-sm font-bold text-slate-900">{{ previewTitle }}</p>
                <p class="mt-2 text-sm leading-6 text-slate-600">Bản xem trước giúp kiểm tra hiển thị trước khi lưu nháp hoặc gửi duyệt.</p>
                <div class="mt-4 space-y-2">
                  <div v-for="option in options" :key="option.content" class="rounded-md bg-white px-3 py-2 text-sm text-slate-700">{{ option.content }}</div>
                </div>
              </div>
            </div>
          </section>

          <section class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
            <div class="grid grid-cols-2 gap-2">
              <button class="rounded-md border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50" @click="saveDraft">Lưu nháp</button>
              <button class="rounded-md bg-slate-950 px-3 py-2 text-sm font-semibold text-white hover:bg-slate-800" @click="submitReview">Gửi duyệt</button>
            </div>
          </section>
        </aside>
      </div>
    </section>
  </EraLmsLayout>
</template>
