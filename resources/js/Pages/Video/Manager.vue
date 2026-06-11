<script setup>
import { computed, nextTick, onMounted, ref } from 'vue'
import { Eye, FileCog, ListFilter, Pencil, Play, RefreshCw, Save, Search, Settings2, SkipForward, Upload, X } from '@lucide/vue'
import EraLmsLayout from '@/Layouts/EraLmsLayout.vue'

const props = defineProps({ apiHeaders: { type: Object, default: () => ({}) } })

const loading = ref(false)
const saving = ref(false)
const runningProcess = ref(false)
const videos = ref([])
const courses = ref([])
const analytics = ref({ summary: {}, top_videos: [] })
const selected = ref(null)
const playback = ref(null)
const toast = ref('')
const filters = ref({ q: '', course_id: '', processing_status: '', visibility: '', per_page: 12, page: 1 })
const meta = ref({ current_page: 1, last_page: 1, total: 0, per_page: 12 })
const form = ref(emptyForm())

const cards = computed(() => [
  {
    label: 'Tổng video',
    value: analytics.value.summary?.videos || meta.value.total || 0,
    detail: 'Đa nguồn tải lên, HLS, nguồn ngoài',
    accent: 'from-blue-500 to-cyan-500',
    border: 'border-blue-200',
  },
  {
    label: 'Sẵn sàng',
    value: analytics.value.summary?.ready || 0,
    detail: 'Có thể phát cho người học',
    accent: 'from-emerald-500 to-teal-500',
    border: 'border-emerald-200',
  },
  {
    label: 'Đang xử lý',
    value: analytics.value.summary?.processing || 0,
    detail: 'Chờ xử lý / hàng đợi',
    accent: 'from-amber-500 to-orange-500',
    border: 'border-amber-200',
  },
  {
    label: 'Hoàn thành',
    value: `${analytics.value.summary?.completion_rate || 0}%`,
    detail: `${analytics.value?.summary?.completed || 0} lượt đạt chuẩn`,
    accent: 'from-indigo-500 to-violet-500',
    border: 'border-indigo-200',
  },
])

const supportsNativeHls = computed(() => {
  if (typeof document === 'undefined') return false
  const testVideo = document.createElement('video')
  return testVideo.canPlayType('application/vnd.apple.mpegurl') !== ''
})

function embedUrlFromSource(url, sourceType) {
  const trim = String(url || '').trim()
  if (!trim) return ''
  if (sourceType === 'youtube') {
    const match = trim.match(/(?:youtube\.com\/watch\?v=|youtu\.be\/)([A-Za-z0-9_-]{6,})/)
    if (!match?.[1]) return trim
    return `https://www.youtube.com/embed/${match[1]}`
  }
  if (sourceType === 'vimeo') {
    const match = trim.match(/vimeo\.com\/(?:video\/)?(\d+)/)
    if (!match?.[1]) return trim
    return `https://player.vimeo.com/video/${match[1]}`
  }
  return trim
}

function emptyForm() {
  return {
    id: null,
    title: '',
    description: '',
    duration_seconds: 0,
    processing_status: 'ready',
    visibility: 'course',
    thumbnail_url: '',
    subtitle_path: '',
    transcript_path: '',
    settings: {
      source_type: 'uploaded_file',
      source_url: '',
      source_profile: 'MP4 upload',
      encoding_profile: 'adaptive-hls-720p',
      drm_policy: 'signed-url',
      cdn_region: 'ap-southeast',
      min_watch_percent: 90,
      max_playback_rate_for_completion: 1.5,
      allow_seek: true,
      allow_download: false,
      require_heartbeat: true,
      heartbeat_interval_seconds: 12,
      anti_fake_level: 'standard',
      question_bank_ids: [],
      exam_blueprints: [],
      learning_outcomes: [],
      captions_required: true,
      transcript_required: true,
    },
  }
}

async function api(path, options = {}) {
  const response = await fetch(path, {
    ...options,
    headers: {
      Accept: 'application/json',
      ...(options.body instanceof FormData ? {} : { 'Content-Type': 'application/json' }),
      ...props.apiHeaders,
      ...(options.headers || {}),
    },
  })
  const payload = response.status === 204 ? null : await response.json().catch(() => null)
  if (!response.ok) throw new Error(payload?.message || 'Không xử lý được yêu cầu.')
  return payload
}

async function loadPage(page = filters.value.page) {
  loading.value = true
  filters.value.page = page
  try {
    const params = new URLSearchParams()
    Object.entries(filters.value).forEach(([key, value]) => value !== '' && params.set(key, value))
    const [videoPayload, analyticsPayload, coursePayload] = await Promise.all([
      api(`/api/v1/videos?${params.toString()}`),
      api(`/api/v1/videos/analytics?${params.toString()}`),
      api('/api/v1/courses?per_page=100').catch(() => ({ data: [] })),
    ])
    videos.value = videoPayload.data || []
    meta.value = {
      current_page: videoPayload.current_page || 1,
      last_page: videoPayload.last_page || 1,
      total: videoPayload.total || videos.value.length,
      per_page: videoPayload.per_page || filters.value.per_page,
    }
    analytics.value = analyticsPayload
    courses.value = coursePayload.data || coursePayload || []
    if (!selected.value && videos.value.length) openVideo(videos.value[0])
  } finally {
    loading.value = false
  }
}

function openVideo(video) {
  selected.value = video
  playback.value = null
  form.value = {
    ...emptyForm(),
    ...video,
    settings: { ...emptyForm().settings, ...(video.settings || {}) },
  }
}

async function saveVideo() {
  if (!form.value.id) return
  saving.value = true
  try {
    const payload = { ...form.value, settings: normalizedSettings() }
    selected.value = await api(`/api/v1/videos/${form.value.id}`, { method: 'PUT', body: JSON.stringify(payload) })
    toast.value = 'Đã lưu cấu hình video.'
    await loadPage()
  } finally {
    saving.value = false
  }
}

function normalizedSettings() {
  const s = form.value.settings || {}
  return {
    ...s,
    min_watch_percent: Number(s.min_watch_percent || 0),
    max_playback_rate_for_completion: Number(s.max_playback_rate_for_completion || 1),
    heartbeat_interval_seconds: Number(s.heartbeat_interval_seconds || 12),
    allow_seek: Boolean(s.allow_seek),
    allow_download: Boolean(s.allow_download),
    require_heartbeat: Boolean(s.require_heartbeat),
    captions_required: Boolean(s.captions_required),
    transcript_required: Boolean(s.transcript_required),
    question_bank_ids: splitList(s.question_bank_ids).map((id) => Number(id)).filter(Boolean),
    exam_blueprints: splitList(s.exam_blueprints),
    learning_outcomes: splitList(s.learning_outcomes),
  }
}

function splitList(value) {
  if (Array.isArray(value)) return value
  return String(value || '').split(',').map((item) => item.trim()).filter(Boolean)
}

async function loadPlayback(video = form.value) {
  const target = video || form.value
  if (!target?.id) {
    toast.value = 'Chọn một video trước khi xem trước.'
    return
  }

  const sourceType = target.settings?.source_type || 'uploaded_file'
  const sourceUrl = String(target.settings?.source_url || '').trim()

  if (['youtube', 'vimeo'].includes(sourceType) && sourceUrl) {
    playback.value = { kind: 'iframe', url: embedUrlFromSource(sourceUrl, sourceType), sourceType }
    return
  }

  if (sourceType === 'external_url') {
    if (!sourceUrl) {
      toast.value = 'Video chưa có source URL để xem trước.'
      return
    }
    playback.value = { kind: 'link', url: sourceUrl, sourceType }
    return
  }

  try {
    const payload = await api(`/api/v1/videos/${target.id}/playback-url`)
    playback.value = {
      kind: payload.delivery === 'hls' && !supportsNativeHls.value ? 'hls_warning' : 'video',
      sourceType: 'uploaded_file',
      ...payload,
    }
  } catch (error) {
    toast.value = error?.message || 'Không tải được link xem trước.'
    playback.value = null
  }
}

async function openAndPreview(video) {
  openVideo(video)
  await nextTick()
  await loadPlayback(video)
}

async function processVideo(video = form.value) {
  if (!video?.id) {
    toast.value = 'Chọn một video trước khi chạy xử lý.'
    return
  }

  runningProcess.value = true
  try {
    await api(`/api/v1/videos/${video.id}/process`, { method: 'POST' })
    toast.value = 'Đã đưa video vào hàng xử lý.'
    await loadPage()
  } catch (error) {
    toast.value = error?.message || 'Không thể chạy xử lý video.'
  } finally {
    runningProcess.value = false
  }
}

async function uploadVideo(event) {
  const file = event.target.files?.[0]
  if (!file) return
  const body = new FormData()
  body.append('title', file.name.replace(/\.[^.]+$/, ''))
  body.append('visibility', 'course')
  body.append('settings[source_type]', 'uploaded_file')
  body.append('settings[encoding_profile]', 'adaptive-hls-720p')
  body.append('file', file)
  const created = await api('/api/v1/videos/upload', { method: 'POST', body })
  toast.value = 'Đã upload video mới.'
  await loadPage(1)
  openVideo(created)
}

function duration(seconds) {
  const value = Number(seconds || 0)
  return `${Math.floor(value / 60)}:${String(value % 60).padStart(2, '0')}`
}

function size(bytes) {
  const value = Number(bytes || 0)
  return value >= 1048576 ? `${(value / 1048576).toFixed(1)} MB` : `${value} B`
}

function statusClass(status) {
  return {
    ready: 'bg-emerald-50 text-emerald-700 ring-emerald-200',
    processing: 'bg-amber-50 text-amber-800 ring-amber-200',
    pending: 'bg-sky-50 text-sky-700 ring-sky-200',
    failed: 'bg-rose-50 text-rose-700 ring-rose-200',
    archived: 'bg-slate-100 text-slate-600 ring-slate-200',
  }[status] || 'bg-slate-50 text-slate-700 ring-slate-200'
}

function statusLabel(status) {
  return {
    ready: 'Sẵn sàng',
    processing: 'Đang xử lý',
    pending: 'Chờ xử lý',
    failed: 'Lỗi',
    archived: 'Lưu trữ',
  }[status] || status
}

function visibilityLabel(visibility) {
  return {
    private: 'Riêng tư',
    course: 'Khóa học',
    tenant: 'Toàn hệ thống',
    public: 'Công khai',
  }[visibility] || visibility
}

onMounted(loadPage)
</script>

<template>
  <EraLmsLayout>
    <template #breadcrumb>Video / Quản lý nâng cao</template>

    <section class="border-b border-blue-900/30 bg-gradient-to-r from-slate-950 via-blue-950 to-cyan-900 text-white">
      <div class="mx-auto max-w-7xl px-6 py-5">
        <div class="flex flex-wrap items-start justify-between gap-3">
          <div>
            <h1 class="text-xl font-bold text-white">Bảng quản lý video nâng cao</h1>
            <p class="mt-1 text-sm text-blue-100">Quản lý đa nguồn, cấu hình phát, theo dõi hoàn thành, liên kết khóa học, bài học và ngân hàng đề.</p>
          </div>
          <div class="flex gap-2">
            <button class="inline-flex h-10 items-center gap-2 rounded-md border border-white/30 bg-white/5 px-3 text-sm font-semibold text-white hover:bg-white/20" @click="loadPage()">
              <RefreshCw class="h-4 w-4" /> Tải lại
            </button>
            <label class="inline-flex h-10 cursor-pointer items-center gap-2 rounded-md bg-blue-600 px-3 text-sm font-semibold text-white hover:bg-blue-700">
              <Upload class="h-4 w-4" /> Tải lên
              <input class="hidden" type="file" accept="video/mp4,video/quicktime,video/x-msvideo,video/x-matroska" @change="uploadVideo" />
            </label>
          </div>
        </div>

        <div class="mt-5 grid gap-3 rounded-xl border border-white/10 bg-white/5 p-4 backdrop-blur-sm md:grid-cols-[1fr_180px_170px_150px_110px]">
          <label class="relative">
            <Search class="pointer-events-none absolute left-3 top-2.5 h-4 w-4 text-slate-400" />
            <input v-model="filters.q" class="h-10 w-full rounded-md border border-white/20 bg-white px-3 py-2 text-sm text-slate-900 placeholder:text-slate-500 pl-9 pr-3" placeholder="Tìm theo tên, mô tả, tên file..." @keyup.enter="loadPage(1)" />
          </label>
          <select v-model="filters.course_id" class="h-10 rounded-md border border-white/20 bg-white px-3 text-sm text-slate-900" @change="loadPage(1)">
            <option value="">Tất cả khóa học</option>
            <option v-for="course in courses" :key="course.id" :value="course.id">{{ course.title }}</option>
          </select>
          <select v-model="filters.processing_status" class="h-10 rounded-md border border-white/20 bg-white px-3 text-sm text-slate-900" @change="loadPage(1)">
            <option value="">Tất cả trạng thái</option>
            <option value="ready">Sẵn sàng</option>
            <option value="processing">Đang xử lý</option>
            <option value="pending">Chờ xử lý</option>
            <option value="failed">Lỗi</option>
            <option value="archived">Lưu trữ</option>
          </select>
          <select v-model="filters.visibility" class="h-10 rounded-md border border-white/20 bg-white px-3 text-sm text-slate-900" @change="loadPage(1)">
            <option value="">Tất cả phạm vi</option>
            <option value="private">Riêng tư</option>
            <option value="course">Khóa học</option>
            <option value="tenant">Toàn hệ thống</option>
            <option value="public">Công khai</option>
          </select>
          <select v-model="filters.per_page" class="h-10 rounded-md border border-white/20 bg-white px-3 text-sm text-slate-900" @change="loadPage(1)">
            <option :value="12">12 dòng</option>
            <option :value="24">24 dòng</option>
            <option :value="48">48 dòng</option>
          </select>
        </div>
      </div>
    </section>

    <section class="mx-auto max-w-7xl px-6 py-5">
      <div v-if="toast" class="mb-4 flex items-center justify-between rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-800">
        <span>{{ toast }}</span>
        <button class="grid h-7 w-7 place-items-center rounded-md hover:bg-emerald-100" @click="toast = ''"><X class="h-4 w-4" /></button>
      </div>

      <div class="grid gap-3 md:grid-cols-4">
        <div v-for="card in cards" :key="card.label" class="rounded-xl border bg-white p-4 shadow-sm transition hover:shadow-md" :class="card.border">
          <div class="mb-1 inline-flex rounded-md bg-slate-100 px-2.5 py-1 text-xs font-bold uppercase tracking-wide text-slate-600">{{ card.label }}</div>
          <div class="text-2xl font-bold text-slate-950">{{ card.value }}</div>
          <div class="mt-1 text-xs text-slate-500">{{ card.detail }}</div>
          <div :class="`mt-3 h-1 w-full rounded-full bg-gradient-to-r ${card.accent}`"></div>
        </div>
      </div>

      <div class="mt-5 grid gap-4 xl:grid-cols-[minmax(0,1.15fr)_minmax(460px,0.85fr)]">
        <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
          <div class="flex items-center justify-between border-b border-slate-200 px-4 py-3">
            <div class="inline-flex items-center gap-2 font-bold text-slate-950"><ListFilter class="h-4 w-4" /> Kho video</div>
            <div class="text-xs text-slate-500">{{ loading ? 'Đang tải...' : `${meta.total} bản ghi` }}</div>
          </div>
          <div class="overflow-x-auto">
            <table class="w-full min-w-[980px] text-left text-sm">
              <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                <tr>
                  <th class="px-4 py-3">Video</th>
                  <th class="px-4 py-3">Nguồn / Cấu hình</th>
                  <th class="px-4 py-3">Sử dụng trong</th>
                  <th class="px-4 py-3">Thông số</th>
                  <th class="px-4 py-3">Trạng thái</th>
                  <th class="px-4 py-3 text-right">Thao tác</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-100">
                <tr v-for="video in videos" :key="video.id" :class="selected?.id === video.id ? 'bg-blue-50/60' : 'hover:bg-slate-50'">
                  <td class="px-4 py-3">
                    <button class="text-left font-semibold text-slate-950 hover:text-blue-700" @click="openVideo(video)">{{ video.title }}</button>
                    <div class="mt-1 max-w-[260px] truncate text-xs text-slate-500">{{ video.original_filename }}</div>
                  </td>
                  <td class="px-4 py-3 text-slate-700">
                    <div>{{ video.usage?.source_profile || video.settings?.source_type || 'uploaded_file' }}</div>
                    <div class="text-xs text-slate-500">{{ video.usage?.encoding_profile || video.settings?.encoding_profile || 'adaptive-hls' }}</div>
                  </td>
                  <td class="px-4 py-3 text-slate-700">
                    <div class="max-w-[260px] truncate">{{ video.usage?.course?.title || video.course?.title || '-' }}</div>
                    <div class="text-xs text-slate-500">{{ video.usage?.component?.title || video.component?.title || 'Chưa gắn bài học' }}</div>
                    <div class="mt-1 text-xs text-indigo-700">{{ video.usage?.question_banks?.map((bank) => bank.code).join(', ') || 'Chưa gắn ngân hàng đề' }}</div>
                  </td>
                  <td class="px-4 py-3 text-slate-700">{{ duration(video.duration_seconds) }} · {{ size(video.file_size) }} · {{ video.renditions?.length || 0 }} bản mã hóa</td>
                  <td class="px-4 py-3"><span class="rounded-full px-2.5 py-1 text-xs font-semibold ring-1" :class="statusClass(video.processing_status)">{{ statusLabel(video.processing_status) }}</span></td>
                  <td class="px-4 py-3">
                    <div class="flex justify-end gap-1">
                      <button class="grid h-8 w-8 place-items-center rounded-md border border-slate-300 text-slate-700 hover:bg-white" title="Xem trước / chỉnh" @click="openAndPreview(video)"><Eye class="h-4 w-4" /></button>
                      <button class="grid h-8 w-8 place-items-center rounded-md border border-blue-200 text-blue-700 hover:bg-white disabled:opacity-40" title="Thực thi xử lý" :disabled="runningProcess" @click="processVideo(video)"><SkipForward class="h-4 w-4" /></button>
                    </div>
                  </td>
                </tr>
                <tr v-if="!loading && !videos.length"><td colspan="6" class="px-4 py-10 text-center text-slate-500">Không có video phù hợp bộ lọc.</td></tr>
              </tbody>
            </table>
          </div>
          <div class="flex items-center justify-between border-t border-slate-200 px-4 py-3 text-sm">
            <span class="text-slate-500">Trang {{ meta.current_page }} / {{ meta.last_page }}</span>
            <div class="flex gap-2">
              <button class="h-9 rounded-md border border-slate-300 px-3 font-semibold text-slate-700 disabled:opacity-40" :disabled="meta.current_page <= 1" @click="loadPage(meta.current_page - 1)">Trước</button>
              <button class="h-9 rounded-md border border-slate-300 px-3 font-semibold text-slate-700 disabled:opacity-40" :disabled="meta.current_page >= meta.last_page" @click="loadPage(meta.current_page + 1)">Sau</button>
            </div>
          </div>
        </div>

        <aside class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
          <div class="flex items-center justify-between bg-slate-950 px-4 py-3 text-white">
            <div class="inline-flex items-center gap-2 font-bold"><FileCog class="h-4 w-4" /> Xem trước và chỉnh nâng cao</div>
            <button class="inline-flex h-9 items-center gap-2 rounded-md border border-white/20 px-3 text-sm font-semibold text-white disabled:opacity-40" :disabled="!form.id" @click="loadPlayback"><Play class="h-4 w-4" /> Phát</button>
          </div>

          <div v-if="form.id" class="max-h-[760px] overflow-auto p-4">
            <div class="overflow-hidden rounded-xl border border-slate-200 bg-slate-950">
              <div v-if="playback?.kind === 'iframe'" class="aspect-video w-full">
                <iframe :src="playback.url" title="Video preview" class="h-full w-full bg-black" allow="autoplay; fullscreen; picture-in-picture; clipboard-write; encrypted-media" allowfullscreen></iframe>
              </div>
              <video v-else-if="playback?.kind === 'video' && playback?.url" class="aspect-video w-full bg-black" :src="playback.url" :poster="form.thumbnail_url" controls playsinline preload="metadata"></video>
              <a v-else-if="playback?.kind === 'link'" class="flex h-[320px] w-full items-center justify-center text-center text-sm text-white underline decoration-dashed decoration-white/70 underline-offset-4" :href="playback.url" target="_blank" rel="noreferrer">Mở nguồn video trong tab mới</a>
              <div v-else-if="playback?.kind === 'hls_warning'" class="grid gap-3 px-4 py-6 text-sm text-white">
                <p>Trình duyệt này chưa hỗ trợ trực tiếp HLS. Click mở ở tab mới để xem qua liên kết stream.</p>
                <a :href="playback.url" target="_blank" rel="noreferrer" class="inline-flex items-center justify-center rounded-md bg-white px-3 py-2 font-semibold text-slate-950">Mở stream HLS</a>
              </div>
              <div v-else class="grid aspect-video place-items-center text-center text-sm text-white">
                <button class="inline-flex items-center gap-2 rounded-md bg-white px-3 py-2 font-semibold text-slate-950" @click="loadPlayback"><Play class="h-4 w-4" /> Tải link xem trước</button>
              </div>
            </div>

            <div class="mt-4 grid gap-3">
              <section class="rounded-xl border border-slate-200 bg-slate-50 p-4">
              <div class="mb-3 inline-flex items-center gap-2 text-xs font-bold uppercase text-blue-700"><Pencil class="h-4 w-4" /> Siêu dữ liệu</div>
                <input v-model="form.title" class="h-10 w-full rounded-md border border-slate-300 px-3 text-sm font-semibold" />
                <textarea v-model="form.description" class="mt-2 min-h-20 w-full rounded-md border border-slate-300 px-3 py-2 text-sm" placeholder="Mô tả, mục tiêu, ghi chú vận hành"></textarea>
                <div class="mt-2 grid grid-cols-3 gap-2">
                  <input v-model.number="form.duration_seconds" type="number" min="0" class="h-10 rounded-md border border-slate-300 px-3 text-sm" placeholder="Thời lượng (giây)" />
                  <select v-model="form.visibility" class="h-10 rounded-md border border-slate-300 px-3 text-sm"><option value="private">Riêng tư</option><option value="course">Khóa học</option><option value="tenant">Toàn hệ thống</option><option value="public">Công khai</option></select>
                  <select v-model="form.processing_status" class="h-10 rounded-md border border-slate-300 px-3 text-sm"><option value="pending">Chờ xử lý</option><option value="processing">Đang xử lý</option><option value="ready">Sẵn sàng</option><option value="failed">Lỗi</option><option value="archived">Lưu trữ</option></select>
                </div>
              </section>

              <section class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                <div class="mb-3 inline-flex items-center gap-2 text-xs font-bold uppercase text-slate-700"><Settings2 class="h-4 w-4" /> Nguồn và cấu hình phát</div>
                <div class="grid grid-cols-2 gap-2">
                  <select v-model="form.settings.source_type" class="h-10 rounded-md border border-slate-300 px-3 text-sm"><option value="uploaded_file">Tải lên</option><option value="hls">HLS master</option><option value="external_url">URL ngoài</option><option value="youtube">YouTube</option><option value="vimeo">Vimeo</option></select>
                  <input v-model="form.settings.encoding_profile" class="h-10 rounded-md border border-slate-300 px-3 text-sm" placeholder="Hồ sơ mã hóa" />
                  <input v-model="form.settings.source_url" class="h-10 rounded-md border border-slate-300 px-3 text-sm" placeholder="URL nguồn / stream ngoài" />
                  <input v-model="form.settings.cdn_region" class="h-10 rounded-md border border-slate-300 px-3 text-sm" placeholder="Khu vực CDN" />
                  <input v-model="form.settings.drm_policy" class="h-10 rounded-md border border-slate-300 px-3 text-sm" placeholder="Chính sách DRM" />
                  <select v-model="form.settings.anti_fake_level" class="h-10 rounded-md border border-slate-300 px-3 text-sm"><option value="basic">Cơ bản</option><option value="standard">Chuẩn</option><option value="strict">Nghiêm ngặt</option></select>
                </div>
              </section>

              <section class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                <div class="mb-3 text-xs font-bold uppercase text-slate-700">Chuẩn hoàn thành và tracking</div>
                <div class="grid grid-cols-3 gap-2">
                  <input v-model.number="form.settings.min_watch_percent" type="number" min="0" max="100" class="h-10 rounded-md border border-slate-300 px-3 text-sm" placeholder="% xem" />
                  <input v-model.number="form.settings.max_playback_rate_for_completion" type="number" min="0.25" step="0.25" class="h-10 rounded-md border border-slate-300 px-3 text-sm" placeholder="Tốc độ tối đa" />
                  <input v-model.number="form.settings.heartbeat_interval_seconds" type="number" min="5" class="h-10 rounded-md border border-slate-300 px-3 text-sm" placeholder="Khoảng heartbeat (giây)" />
                </div>
                <div class="mt-3 grid grid-cols-2 gap-2">
                  <label class="flex items-center gap-2 rounded-md bg-white px-3 py-2 text-sm font-semibold text-slate-700"><input v-model="form.settings.require_heartbeat" type="checkbox" /> Yêu cầu heartbeat</label>
                  <label class="flex items-center gap-2 rounded-md bg-white px-3 py-2 text-sm font-semibold text-slate-700"><input v-model="form.settings.allow_seek" type="checkbox" /> Cho seek</label>
                  <label class="flex items-center gap-2 rounded-md bg-white px-3 py-2 text-sm font-semibold text-slate-700"><input v-model="form.settings.allow_download" type="checkbox" /> Cho phép tải xuống</label>
                  <label class="flex items-center gap-2 rounded-md bg-white px-3 py-2 text-sm font-semibold text-slate-700"><input v-model="form.settings.captions_required" type="checkbox" /> Bắt buộc phụ đề</label>
                </div>
              </section>

              <section class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                <div class="mb-3 text-xs font-bold uppercase text-indigo-700">Dùng trong khóa học và ngân hàng đề</div>
                <div class="rounded-md bg-slate-50 p-3 text-sm text-slate-700">
                  <div><b>Khóa học:</b> {{ selected?.usage?.course?.title || '-' }}</div>
                  <div class="mt-1"><b>Bài học:</b> {{ selected?.usage?.component?.title || '-' }}</div>
                  <div class="mt-1"><b>Ngân hàng đề:</b> {{ selected?.usage?.question_banks?.map((bank) => `${bank.code} - ${bank.name}`).join('; ') || '-' }}</div>
                </div>
                <input v-model="form.settings.question_bank_ids" class="mt-2 h-10 w-full rounded-md border border-slate-300 px-3 text-sm" placeholder="ID ngân hàng đề, cách nhau bằng dấu phẩy" />
                <input v-model="form.settings.exam_blueprints" class="mt-2 h-10 w-full rounded-md border border-slate-300 px-3 text-sm" placeholder="Blueprint/đề liên quan, cách nhau bằng dấu phẩy" />
                <input v-model="form.settings.learning_outcomes" class="mt-2 h-10 w-full rounded-md border border-slate-300 px-3 text-sm" placeholder="CLO/PLO liên quan, cách nhau bằng dấu phẩy" />
              </section>

              <section class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                <div class="mb-3 text-xs font-bold uppercase text-slate-700">Tài nguyên xem trước</div>
                <input v-model="form.thumbnail_url" class="h-10 w-full rounded-md border border-slate-300 px-3 text-sm" placeholder="URL ảnh đại diện" />
                <input v-model="form.subtitle_path" class="mt-2 h-10 w-full rounded-md border border-slate-300 px-3 text-sm" placeholder="Đường dẫn phụ đề / VTT" />
                <input v-model="form.transcript_path" class="mt-2 h-10 w-full rounded-md border border-slate-300 px-3 text-sm" placeholder="Đường dẫn bản ghi lời thoại" />
              </section>
            </div>
          </div>

          <div v-else class="grid min-h-[420px] place-items-center p-6 text-center text-sm text-slate-500">Chọn một video để xem trước, chỉnh cấu hình và chạy xử lý.</div>

          <div class="flex items-center justify-between border-t border-slate-200 bg-slate-50 px-4 py-3">
            <button class="inline-flex h-10 items-center gap-2 rounded-md border border-blue-200 px-3 text-sm font-semibold text-blue-700 disabled:opacity-40" :disabled="!form.id || runningProcess" @click="processVideo()"><SkipForward class="h-4 w-4" /> {{ runningProcess ? 'Đang chạy...' : 'Thực thi xử lý' }}</button>
            <button class="inline-flex h-10 items-center gap-2 rounded-md bg-blue-600 px-4 text-sm font-semibold text-white disabled:opacity-50" :disabled="saving || !form.id" @click="saveVideo"><Save class="h-4 w-4" /> {{ saving ? 'Đang lưu...' : 'Lưu cấu hình' }}</button>
          </div>
        </aside>
      </div>
    </section>
  </EraLmsLayout>
</template>
