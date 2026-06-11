<script setup>
import { computed } from 'vue'
import { BookOpenText, Clock3, FileText, Flag, MessageCircle, PenLine, Play, ScrollText, Video, X } from '@lucide/vue'

defineEmits(['close'])

const props = defineProps({ open: Boolean, item: Object })

const isCoursePreview = computed(() => Boolean(props.item?.course || props.item?.outline))
const isQuizPreview = computed(() => props.item?.component_type === 'quiz')
const youtubeEmbedUrl = computed(() => {
  const url = props.item?.config?.youtube_url || ''
  const id = extractYoutubeId(url)
  return id ? `https://www.youtube.com/embed/${id}` : ''
})
const mediaUrl = computed(() => props.item?.content?.download_url || props.item?.config?.media_url || '')
const mediaType = computed(() => props.item?.content?.mime_type || '')
const outline = computed(() => props.item?.outline || [])
const units = computed(() => collectUnits(outline.value))
const activityCount = computed(() => units.value.reduce((total, unit) => total + (unit.components?.length || 0), 0))
const totalMinutes = computed(() => units.value.reduce((total, unit) => {
  return total + (unit.components || []).reduce((sum, component) => sum + Number(component.config?.estimated_minutes || 0), 0)
}, 0))

function collectUnits(nodes) {
  return (nodes || []).flatMap((node) => [
    ...(node.type === 'unit' ? [node] : []),
    ...collectUnits(node.children || []),
  ])
}

function iconFor(type) {
  return type === 'video' ? Play : type === 'live_session' ? Video : type === 'forum' ? MessageCircle : type === 'assignment' ? PenLine : type === 'quiz' ? ScrollText : type === 'pdf' || type === 'file' ? FileText : BookOpenText
}

function labelFor(type) {
  const labels = { text: 'Văn bản', video: 'Video', pdf: 'Tài liệu', file: 'Tài liệu', quiz: 'Kiểm tra', assignment: 'Bài tập', forum: 'Thảo luận', live_session: 'Phiên trực tuyến', scorm: 'SCORM' }
  return labels[type] || type
}

function extractYoutubeId(url) {
  const value = String(url || '')
  const match = value.match(/(?:youtube\.com\/watch\?v=|youtu\.be\/|youtube\.com\/embed\/)([^&?/]+)/)
  return match?.[1] || ''
}

function isVideoMedia() {
  return props.item?.component_type === 'video' || mediaType.value.startsWith('video/')
}

function isAudioMedia() {
  return mediaType.value.startsWith('audio/')
}

function isImageMedia() {
  return mediaType.value.startsWith('image/') || props.item?.content?.item_type === 'image'
}

function isPdfMedia() {
  return mediaType.value.includes('pdf') || props.item?.content?.item_type === 'pdf'
}

function openInLearner(component = null) {
  const courseId = props.item?.course?.id || component?.course_id || props.item?.course_id
  if (!courseId) return
  const params = new URLSearchParams({ course_id: courseId })
  if (component?.id) params.set('component_id', component.id)
  window.location.href = `/courses/learn?${params.toString()}`
}
</script>

<template>
  <div v-if="open" class="fixed inset-0 z-40 flex justify-end bg-slate-950/30">
    <aside class="h-full w-full max-w-4xl overflow-auto bg-white p-5 shadow-2xl">
      <div class="mb-4 flex items-center justify-between">
        <div>
          <div class="text-xs font-bold uppercase text-slate-500">{{ isCoursePreview ? 'Xem trước toàn bộ bài đang thiết kế' : 'Xem trước hoạt động' }}</div>
          <h2 class="mt-1 text-lg font-bold text-slate-950">{{ item?.title || item?.course?.title || 'Xem trước khóa học' }}</h2>
          <p v-if="isCoursePreview" class="mt-1 text-sm text-slate-500">{{ units.length }} bài học · {{ activityCount }} hoạt động · {{ totalMinutes }} phút</p>
        </div>
        <button class="grid h-9 w-9 place-items-center rounded-md border border-slate-200 text-slate-600 hover:bg-slate-50" title="Đóng" @click="$emit('close')">
          <X class="h-4 w-4" />
        </button>
      </div>

      <div v-if="isQuizPreview" class="rounded-md border border-slate-200 bg-white">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 bg-slate-50 px-4 py-3">
          <div>
            <div class="text-sm font-bold text-slate-950">{{ item?.config?.exam_title || item?.title }}</div>
            <div class="mt-1 text-xs text-slate-500">
              {{ item?.config?.duration_minutes || 45 }} phút · Đạt {{ item?.config?.pass_score || 50 }}/{{ item?.config?.total_score || 100 }} · {{ item?.config?.max_attempts || 1 }} lần làm
            </div>
          </div>
          <div class="inline-flex h-10 items-center gap-2 rounded-md bg-red-50 px-3 text-sm font-bold text-red-700 ring-1 ring-red-100">
            <Clock3 class="h-4 w-4" />
            {{ item?.config?.duration_minutes || 45 }}:00
          </div>
        </div>
        <div class="grid min-h-[520px] grid-cols-[220px_1fr]">
          <aside class="border-r border-slate-200 bg-slate-50 p-4">
            <div class="text-xs font-bold uppercase text-slate-500">Danh sách câu</div>
            <div class="mt-3 grid grid-cols-5 gap-2">
              <button
                v-for="n in 20"
                :key="n"
                class="grid h-9 place-items-center rounded-md border text-xs font-bold"
                :class="n === 1 ? 'border-blue-500 bg-blue-600 text-white' : n <= 4 ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'border-slate-200 bg-white text-slate-600'"
              >
                {{ n }}
              </button>
            </div>
            <div class="mt-4 space-y-2 text-xs text-slate-600">
              <div>Đã trả lời: 4</div>
              <div>Chưa trả lời: 16</div>
              <div>Đánh dấu xem lại: 0</div>
            </div>
          </aside>
          <main class="p-5">
            <div class="mb-4 flex items-center justify-between">
              <div class="text-sm font-bold text-slate-950">Câu 1</div>
              <button class="inline-flex h-9 items-center gap-2 rounded-md border border-slate-300 bg-white px-3 text-xs font-semibold text-slate-700">
                <Flag class="h-4 w-4" />
                Đánh dấu xem lại
              </button>
            </div>
            <div class="rounded-md border border-slate-200 bg-white p-4">
              <p class="text-sm font-semibold leading-6 text-slate-900">Đây là câu hỏi trắc nghiệm mô phỏng từ đề đã gắn. Người học chọn một đáp án, hệ thống tự lưu tự động và tính thời gian như bài thi thật.</p>
              <div class="mt-4 space-y-3">
                <label v-for="answer in ['Đáp án A', 'Đáp án B', 'Đáp án C', 'Đáp án D']" :key="answer" class="flex items-center gap-3 rounded-md border border-slate-200 p-3 text-sm hover:bg-blue-50">
                  <input type="radio" name="preview-answer" />
                  <span>{{ answer }}</span>
                </label>
              </div>
            </div>
            <div class="mt-5 flex justify-between">
              <button class="h-10 rounded-md border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700">Câu trước</button>
              <div class="flex gap-2">
                <button class="h-10 rounded-md border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700">Lưu bản nháp</button>
                <button class="h-10 rounded-md bg-blue-600 px-4 text-sm font-semibold text-white">Câu tiếp</button>
                <button class="h-10 rounded-md bg-emerald-600 px-4 text-sm font-semibold text-white">Nộp bài</button>
              </div>
            </div>
            <div class="mt-4 rounded-md bg-amber-50 p-3 text-xs text-amber-800">
              {{ item?.config?.shuffle_questions ? 'Có đảo câu hỏi' : 'Không đảo câu hỏi' }} · {{ item?.config?.shuffle_options ? 'Có đảo đáp án' : 'Không đảo đáp án' }} · Kết quả: {{ item?.config?.show_result_mode || 'immediately' }}
            </div>
          </main>
        </div>
      </div>

      <div v-else-if="!isCoursePreview" class="overflow-hidden rounded-md border border-slate-200 bg-slate-950">
        <iframe
          v-if="youtubeEmbedUrl"
          class="aspect-video w-full"
          :src="youtubeEmbedUrl"
          title="Xem trước YouTube"
          allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
          allowfullscreen
        ></iframe>
        <video v-else-if="isVideoMedia() && mediaUrl" class="aspect-video w-full bg-black" :src="mediaUrl" controls playsinline></video>
        <div v-else-if="isAudioMedia() && mediaUrl" class="bg-white p-6">
          <div class="mb-3 text-sm font-bold text-slate-950">{{ item?.content?.title || item?.title }}</div>
          <audio class="w-full" :src="mediaUrl" controls></audio>
        </div>
        <img v-else-if="isImageMedia() && mediaUrl" :src="mediaUrl" :alt="item?.content?.title || item?.title" class="max-h-[70vh] w-full object-contain bg-white" />
        <iframe v-else-if="isPdfMedia() && mediaUrl" class="h-[70vh] w-full bg-white" :src="mediaUrl" title="Xem trước PDF"></iframe>
        <div v-else class="grid aspect-video place-items-center bg-slate-900 p-8 text-center text-sm text-white">
          Chưa có media để phát. Hãy chèn URL YouTube hoặc tải file video/audio/hình ảnh từ kho học liệu.
        </div>
      </div>

      <div v-if="isCoursePreview" class="space-y-4">
        <section
          v-for="section in outline"
          :key="section.id"
          class="rounded-md border border-slate-200 bg-white"
        >
          <div class="border-b border-slate-200 bg-slate-50 px-4 py-3">
            <h3 class="text-sm font-bold text-slate-950">{{ section.title }}</h3>
            <p class="mt-1 text-xs text-slate-500">{{ section.children?.length || 0 }} bài học</p>
          </div>
          <div class="divide-y divide-slate-100">
            <article
              v-for="unit in section.children || []"
              :key="unit.id"
              class="p-4"
            >
              <div class="flex items-start justify-between gap-3">
                <div>
                  <h4 class="text-sm font-bold text-slate-900">{{ unit.title }}</h4>
                  <p class="mt-1 text-xs text-slate-500">{{ unit.components?.length || 0 }} hoạt động trong bài này</p>
                </div>
                <span class="rounded-full bg-blue-50 px-2.5 py-1 text-xs font-bold text-blue-700">{{ unit.status || 'Nháp' }}</span>
              </div>
              <div v-if="unit.components?.length" class="mt-3 space-y-2">
                <div
                  v-for="component in unit.components"
                  :key="component.id"
                  class="flex items-center gap-3 rounded-md border border-slate-200 bg-white p-3"
                >
                  <span class="grid h-9 w-9 place-items-center rounded-md bg-blue-50 text-blue-700">
                    <component :is="iconFor(component.component_type)" class="h-4 w-4" />
                  </span>
                  <span class="min-w-0 flex-1">
                    <span class="block truncate text-sm font-semibold text-slate-950">{{ component.title }}</span>
                    <span class="mt-0.5 block text-xs text-slate-500">{{ labelFor(component.component_type) }} · {{ component.config?.estimated_minutes || 0 }} phút</span>
                  </span>
                  <button class="inline-flex h-8 items-center gap-2 rounded-md border border-slate-300 bg-white px-3 text-xs font-semibold text-slate-700 hover:bg-slate-50" title="Mở trang học" @click="openInLearner(component)">
                    <Play class="h-3.5 w-3.5" />
                    Xem
                  </button>
                </div>
              </div>
              <div v-else class="mt-3 rounded-md border border-dashed border-slate-300 p-4 text-sm text-slate-500">
                Bài học này chưa có hoạt động.
              </div>
            </article>
          </div>
        </section>
        <div v-if="outline.length === 0" class="rounded-md border border-dashed border-slate-300 p-8 text-center text-sm text-slate-500">
          Chưa có chương hoặc bài học để xem thử.
        </div>
      </div>

      <dl v-if="!isCoursePreview && !isQuizPreview" class="mt-4 space-y-3 text-sm">
        <div>
          <dt class="text-xs font-bold uppercase text-slate-500">Loại</dt>
          <dd class="mt-1 font-semibold text-slate-900">{{ item?.component_type || 'Xem trước khóa học' }}</dd>
        </div>
        <div>
          <dt class="text-xs font-bold uppercase text-slate-500">Mô tả</dt>
          <dd class="mt-1 text-slate-600">Video giải thích chi tiết nội dung và mô phỏng trải nghiệm người học.</dd>
        </div>
      </dl>

      <button class="mt-6 h-10 w-full rounded-md border border-slate-300 bg-white text-sm font-semibold text-slate-700 hover:bg-slate-50" @click="$emit('close')">Đóng</button>
    </aside>
  </div>
</template>
