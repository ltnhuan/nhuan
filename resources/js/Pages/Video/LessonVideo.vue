<script setup>
import { computed, nextTick, onMounted, ref } from 'vue'
import { BookOpenText, Eye, ExternalLink, Pencil, Play, RefreshCw, Save, Search, Settings2, SkipForward, X } from '@lucide/vue'
import EraLmsLayout from '@/Layouts/EraLmsLayout.vue'
import VideoPlayer from '@/Components/VideoPlayer.vue'

const props = defineProps({
  apiHeaders: {
    type: Object,
    default: () => ({}),
  },
})

const loading = ref(false)
const saving = ref(false)
const playbackLoading = ref(false)
const runningProcess = ref(false)
const activeProcessVideoId = ref(null)
const videos = ref([])
const courses = ref([])
const meta = ref({ current_page: 1, last_page: 1, total: 0, per_page: 12 })
const toast = ref('')
const filters = ref({
  q: '',
  course_id: '',
  processing_status: '',
  visibility: '',
  per_page: 12,
  page: 1,
})

const selected = ref(null)
const selectedAnalytics = ref(null)
const playback = ref(null)
const form = ref(emptyForm())
const analyticsSummary = ref({})
const topVideoSummaries = ref([])
const playlistMeta = ref({ course: '', component: '' })

const routeSearch = new URLSearchParams(window.location.search || '')
const queryFilterCourse = routeSearch.get('course_id') || ''
const queryFilterComponent = routeSearch.get('component_id') || ''

if (queryFilterCourse) {
  filters.value.course_id = queryFilterCourse
}
if (queryFilterComponent) {
  playlistMeta.value.component = queryFilterComponent
}

const cards = computed(() => [
  {
    label: 'Tổng video',
    value: analyticsSummary.value.videos || meta.value.total || 0,
    detail: 'Danh sách assets video khả dụng',
    accent: 'from-indigo-500 to-cyan-500',
    border: 'border-indigo-200',
  },
  {
    label: 'Đang sẵn sàng',
    value: analyticsSummary.value.ready || 0,
    detail: 'Có thể phát ngay',
    accent: 'from-emerald-500 to-teal-500',
    border: 'border-emerald-200',
  },
  {
    label: 'Đang xử lý',
    value: analyticsSummary.value.processing || 0,
    detail: 'Chờ xử lý + đang xử lý',
    accent: 'from-amber-500 to-orange-500',
    border: 'border-amber-200',
  },
  {
    label: 'Tỉ lệ hoàn thành',
    value: `${analyticsSummary.value.completion_rate || 0}%`,
    detail: `${analyticsSummary.value.completed || 0} học viên đạt chuẩn`,
    accent: 'from-violet-500 to-fuchsia-500',
    border: 'border-violet-200',
  },
])

const visibleVideos = computed(() => {
  const componentId = playlistMeta.value.component
  if (!componentId) return videos.value
  return videos.value.filter((video) => String(video.component?.id || video.component_id || '') === String(componentId))
})

const topSummaryIndex = computed(() => {
  const map = new Map()
  for (const item of topVideoSummaries.value || []) {
    map.set(item.video_asset_id, item)
  }
  return map
})

const selectedCourse = computed(() => resolveUsageText(selected.value, 'course'))
const selectedComponent = computed(() => resolveUsageText(selected.value, 'component'))

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
      min_watch_percent: 90,
      max_playback_rate_for_completion: 1.5,
      heartbeat_interval_seconds: 12,
      require_heartbeat: true,
      allow_seek: false,
      allow_download: false,
      captions_required: false,
      transcript_required: false,
      anti_fake_level: 'standard',
      source_type: 'uploaded_file',
      source_url: '',
      source_profile: 'uploaded',
      encoding_profile: 'adaptive-hls-720p',
      drm_policy: 'signed-url',
      cdn_region: 'ap-southeast',
    },
  }
}

function showToast(message) {
  toast.value = message
  window.setTimeout(() => {
    if (toast.value === message) toast.value = ''
  }, 5000)
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
  const raw = response.status === 204 ? '' : await response.text().catch(() => '')
  let payload = null
  try {
    payload = raw ? JSON.parse(raw) : null
  } catch (_err) {
    payload = null
  }
  if (!response.ok) {
    throw new Error(typeof payload === 'object' && payload?.message ? payload.message : raw || 'Không xử lý được yêu cầu.')
  }
  return payload
}

function buildListParams() {
  const params = new URLSearchParams()
  Object.entries(filters.value).forEach(([key, value]) => {
    if (value === '') return
    params.set(key, String(value))
  })
  return params
}

function buildAnalyticsParams() {
  const params = new URLSearchParams()
  if (filters.value.course_id) params.set('course_id', String(filters.value.course_id))
  return params
}

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

function normalizeSettings() {
  const setting = form.value.settings || {}
  return {
    ...setting,
    min_watch_percent: Number(setting.min_watch_percent || 0),
    max_playback_rate_for_completion: Number(setting.max_playback_rate_for_completion || 1),
    heartbeat_interval_seconds: Number(setting.heartbeat_interval_seconds || 12),
    require_heartbeat: Boolean(setting.require_heartbeat),
    allow_seek: Boolean(setting.allow_seek),
    allow_download: Boolean(setting.allow_download),
    captions_required: Boolean(setting.captions_required),
    transcript_required: Boolean(setting.transcript_required),
  }
}

function resolveUsageText(video, key) {
  if (!video) return '-'
  if (key === 'course') {
    return video.usage?.course?.title || video.course?.title || '-'
  }
  if (key === 'component') {
    return video.usage?.component?.title || video.component?.title || '-'
  }
  return (video.usage?.question_banks || []).map((bank) => `${bank.code || bank.id}`).join(', ') || 'Chưa gắn ngân hàng đề'
}

function statusClass(status) {
  return {
    ready: 'bg-emerald-50 text-emerald-700 ring-emerald-200',
    processing: 'bg-amber-50 text-amber-800 ring-amber-200',
    pending: 'bg-sky-50 text-sky-700 ring-sky-200',
    failed: 'bg-rose-50 text-rose-700 ring-rose-200',
    archived: 'bg-slate-100 text-slate-600 ring-slate-200',
  }[status] || 'bg-slate-100 text-slate-700 ring-slate-200'
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

function formatDuration(seconds) {
  const value = Number(seconds || 0)
  return `${Math.floor(value / 60)}:${String(value % 60).padStart(2, '0')}`
}

function formatSize(bytes) {
  const value = Number(bytes || 0)
  if (value >= 1024 * 1024) return `${(value / 1024 / 1024).toFixed(1)} MB`
  if (value >= 1024) return `${(value / 1024).toFixed(1)} KB`
  return `${value} B`
}

function questionBanksText(video) {
  return resolveUsageText(video, 'banks')
}

function completionText(video, field) {
  const item = topSummaryIndex.value.get(video?.id)
  if (!item && !selectedAnalytics.value) return 'Chưa có dữ liệu'
  if (field === 'learners') return `${item?.learners || 0} học viên`
  if (field === 'avg') return `${Number(item?.avg_watch || 0).toFixed(1)}%`
  if (field === 'completed') return `${item?.completed_count || 0} hoàn thành`
  return 'Chưa có dữ liệu'
}

async function loadVideos(page = filters.value.page) {
  loading.value = true
  filters.value.page = page
  try {
    const listParams = buildListParams()
    listParams.set('page', String(page))
    const [videoPayload, analyticsPayload, coursePayload] = await Promise.all([
      api(`/api/v1/videos?${listParams.toString()}`),
      api(`/api/v1/videos/analytics?${buildAnalyticsParams().toString()}`),
      api('/api/v1/courses?per_page=100').catch(() => ({ data: [] })),
    ])

    videos.value = videoPayload.data || []
    meta.value = {
      current_page: videoPayload.current_page || 1,
      last_page: videoPayload.last_page || 1,
      total: videoPayload.total || videos.value.length,
      per_page: videoPayload.per_page || filters.value.per_page,
    }

    analyticsSummary.value = analyticsPayload?.summary || {}
    topVideoSummaries.value = analyticsPayload?.top_videos || []
    courses.value = coursePayload.data || coursePayload || []

    const availableVideos = visibleVideos.value

    if (selected.value) {
      const stillExists = availableVideos.find((video) => String(video.id) === String(selected.value.id))
      if (!stillExists && videos.value.length) {
        await openVideo(availableVideos[0] || videos.value[0])
      }
    } else if (availableVideos.length) {
      await openVideo(availableVideos[0], false)
    } else if (videos.value.length) {
      await openVideo(videos.value[0], false)
    }

    if (!videos.value.length) {
      selected.value = null
      form.value = emptyForm()
      selectedAnalytics.value = null
      playback.value = null
    }
  } catch (error) {
    showToast(error?.message || 'Tải danh sách video thất bại.')
  } finally {
    loading.value = false
  }
}

async function loadSelectedAnalytics(video = selected.value) {
  if (!video?.id) return
  try {
    const payload = await api(`/api/v1/videos/${video.id}`)
    selectedAnalytics.value = payload.analytics || null
  } catch (_error) {
    selectedAnalytics.value = null
  }
}

async function openVideo(video, autoLoadPlayback = false) {
  selected.value = video
  form.value = {
    ...emptyForm(),
    ...video,
    settings: { ...emptyForm().settings, ...(video.settings || {}) },
  }
  playback.value = null
  selectedAnalytics.value = null

  await loadSelectedAnalytics(video)
  if (autoLoadPlayback) {
    await nextTick()
    await loadPlayback(video)
  }
}

function openAndPreview(video) {
  openVideo(video, true)
}

async function loadPlayback(video = selected.value) {
  const target = video || selected.value
  if (!target?.id) {
    showToast('Chọn video trước khi xem trước.')
    return
  }

  const sourceType = target.settings?.source_type || 'uploaded_file'
  const sourceUrl = String(target.settings?.source_url || '').trim()

  if (['youtube', 'vimeo'].includes(sourceType) && sourceUrl) {
    playback.value = {
      kind: 'iframe',
      sourceType,
      url: embedUrlFromSource(sourceUrl, sourceType),
    }
    return
  }

  if (sourceType === 'external_url') {
    if (!sourceUrl) {
      showToast('Video chưa có source URL để xem trước.')
      return
    }
    playback.value = {
      kind: 'link',
      sourceType,
      url: sourceUrl,
    }
    return
  }

  playbackLoading.value = true
  try {
    playback.value = await api(`/api/v1/videos/${target.id}/playback-url`)
    playback.value.kind = 'video'
  } catch (error) {
    playback.value = null
    showToast(error?.message || 'Không tạo được link xem trước.')
  } finally {
    playbackLoading.value = false
  }
}

async function processVideo(video = selected.value) {
  if (!video?.id) {
    showToast('Chưa có video để chạy quy trình xử lý.')
    return
  }
  activeProcessVideoId.value = video.id
  runningProcess.value = true
  try {
    await api(`/api/v1/videos/${video.id}/process`, { method: 'POST' })
    showToast('Đã đưa video vào hàng xử lý.')
    await loadVideos(filters.value.page)
  } catch (error) {
    showToast(error?.message || 'Không thể chạy xử lý video.')
  } finally {
    runningProcess.value = false
    activeProcessVideoId.value = null
  }
}

async function saveVideo() {
  if (!form.value.id) return
  saving.value = true
  try {
    const payload = {
      ...form.value,
      duration_seconds: Number(form.value.duration_seconds || 0),
      settings: normalizeSettings(),
      processing_status: form.value.processing_status,
      visibility: form.value.visibility,
    }

    const updated = await api(`/api/v1/videos/${form.value.id}`, {
      method: 'PUT',
      body: JSON.stringify(payload),
    })
    selected.value = { ...selected.value, ...updated }
    showToast('Đã lưu cấu hình video.')
    await loadVideos(filters.value.page)
  } catch (error) {
    showToast(error?.message || 'Không thể lưu thay đổi.')
  } finally {
    saving.value = false
  }
}

function buildPlayerMeta(video) {
  if (!video) return {}
  return {
    id: video.id,
    title: video.title,
    thumbnail_url: video.thumbnail_url,
    watch_percent: selectedAnalytics.value?.completion_rate || video.watch_percent || 0,
    duration_seconds: Number(video.duration_seconds || 0),
  }
}

function playerSectionTitle(video) {
  if (!video) return 'Chưa chọn bài học'
  return video.title || `Video #${video.id}`
}

onMounted(() => {
  loadVideos(1)
})
</script>

<template>
  <EraLmsLayout>
    <template #breadcrumb>Bài học / Video</template>

    <section class="border-b border-slate-900/20 bg-gradient-to-r from-slate-950 via-blue-950 to-cyan-900 text-white">
      <div class="mx-auto max-w-7xl px-6 py-5">
        <div class="flex flex-wrap items-start justify-between gap-3">
          <div>
            <h1 class="text-xl font-bold text-white">Dashboard video bài học</h1>
            <p class="mt-1 text-sm text-blue-100">Quản lý danh sách video theo khóa học, theo dõi trạng thái, cấu hình phát, và thao tác xem trước / xử lý nhanh.</p>
          </div>
          <button class="inline-flex h-10 items-center gap-2 rounded-md bg-white/10 px-3 text-sm font-semibold hover:bg-white/20" @click="loadVideos(1)">
            <RefreshCw class="h-4 w-4" /> Tải lại
          </button>
        </div>

        <div class="mt-5 grid gap-3 rounded-xl border border-white/10 bg-white/5 p-4 backdrop-blur-sm md:grid-cols-[1fr_220px_170px_150px_130px]">
          <label class="relative">
            <Search class="pointer-events-none absolute left-3 top-2.5 h-4 w-4 text-slate-300" />
            <input
              v-model="filters.q"
              class="h-10 w-full rounded-md border border-white/20 bg-white px-3 py-2 pl-9 text-sm text-slate-900 placeholder:text-slate-500"
              placeholder="Tìm theo tên, mô tả, tên tệp..."
              @keyup.enter="loadVideos(1)"
            />
          </label>
          <select
            v-model="filters.course_id"
            class="h-10 rounded-md border border-white/20 bg-white px-3 text-sm text-slate-900"
            @change="loadVideos(1)"
          >
            <option value="">Tất cả khóa học</option>
            <option v-for="course in courses" :key="course.id" :value="course.id">{{ course.code || course.title }}</option>
          </select>
          <select
            v-model="filters.processing_status"
            class="h-10 rounded-md border border-white/20 bg-white px-3 text-sm text-slate-900"
            @change="loadVideos(1)"
          >
            <option value="">Tất cả trạng thái</option>
            <option value="ready">Sẵn sàng</option>
            <option value="processing">Đang xử lý</option>
            <option value="pending">Chờ xử lý</option>
            <option value="failed">Lỗi</option>
            <option value="archived">Lưu trữ</option>
          </select>
          <select
            v-model="filters.visibility"
            class="h-10 rounded-md border border-white/20 bg-white px-3 text-sm text-slate-900"
            @change="loadVideos(1)"
          >
            <option value="">Tất cả phạm vi</option>
            <option value="private">Riêng tư</option>
            <option value="course">Khóa học</option>
            <option value="tenant">Toàn hệ thống</option>
            <option value="public">Công khai</option>
          </select>
          <select
            v-model="filters.per_page"
            class="h-10 rounded-md border border-white/20 bg-white px-3 text-sm text-slate-900"
            @change="loadVideos(1)"
          >
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
        <div
          v-for="card in cards"
          :key="card.label"
          class="rounded-xl border bg-white p-4 shadow-sm transition hover:shadow-md"
          :class="card.border"
        >
          <div class="mb-1 inline-flex rounded-md bg-slate-100 px-2 py-1 text-xs font-bold uppercase tracking-wide text-slate-600">{{ card.label }}</div>
          <div class="text-2xl font-bold text-slate-950">{{ card.value }}</div>
          <div class="mt-1 text-xs text-slate-500">{{ card.detail }}</div>
          <div :class="`mt-3 h-1 w-full rounded-full bg-gradient-to-r ${card.accent}`"></div>
        </div>
      </div>

      <div class="mt-5 grid gap-4 xl:grid-cols-[minmax(0,1.25fr)_minmax(380px,0.75fr)]">
        <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
          <div class="border-b border-slate-200 px-4 py-3 font-bold text-slate-950">{{ playerSectionTitle(selected) }}</div>

          <div class="p-4">
            <div v-if="selected && playback?.kind === 'video'" class="overflow-hidden rounded-xl border border-slate-200">
              <VideoPlayer
                v-if="playback?.url"
                :video="buildPlayerMeta(selected)"
                :playback="playback"
                :required-percent="Number(form.settings?.min_watch_percent || 90)"
                :api-headers="props.apiHeaders"
                @completed="showToast('Hoàn thành yêu cầu xem trước.')"
              />
            </div>

            <div v-else-if="playback?.kind === 'iframe'" class="overflow-hidden rounded-xl border border-slate-200">
              <iframe :src="playback.url" title="External video preview" class="aspect-video w-full bg-black" allow="autoplay; fullscreen; picture-in-picture; clipboard-write; encrypted-media" allowfullscreen></iframe>
            </div>

            <div v-else-if="playback?.kind === 'link'" class="grid aspect-video place-items-center rounded-xl border border-slate-200 bg-slate-950 text-sm text-white">
              <a class="inline-flex items-center gap-2 rounded-md bg-white px-3 py-2 font-semibold text-slate-950" :href="playback.url" target="_blank" rel="noreferrer">
                Mở nguồn video ngoài
                <ExternalLink class="h-4 w-4" />
              </a>
            </div>

            <div v-else class="grid aspect-video place-items-center rounded-xl border border-dashed border-slate-300 bg-slate-50 text-sm text-slate-500">
              <button
                v-if="selected"
                class="inline-flex items-center gap-2 rounded-md border border-slate-300 bg-white px-3 py-2 font-semibold text-slate-700"
                :disabled="playbackLoading"
                @click="loadPlayback(selected)"
              >
                <Play class="h-4 w-4" />
                {{ playbackLoading ? 'Đang lấy link...' : 'Xem trước video này' }}
              </button>
              <span v-else>Chưa chọn video để xem trước.</span>
            </div>

            <div class="mt-4 grid gap-3 sm:grid-cols-3">
              <section class="rounded-xl border border-slate-200 bg-slate-50 p-3">
                <div class="text-xs font-bold uppercase text-slate-500">Bài học / Ngân hàng đề</div>
                <div class="mt-2 text-sm font-semibold text-slate-900">{{ selectedCourse }}</div>
                <div class="text-xs text-slate-500">{{ selectedComponent }}</div>
                <div class="mt-2 text-xs text-indigo-700">{{ questionBanksText(selected) }}</div>
              </section>
              <section class="rounded-xl border border-slate-200 bg-slate-50 p-3">
                <div class="text-xs font-bold uppercase text-slate-500">Thông số phát chuẩn</div>
                <div class="mt-2 text-sm text-slate-900">Tối thiểu xem: {{ form.settings.min_watch_percent }}%</div>
                <div class="text-sm text-slate-900">Tốc độ tối đa: {{ form.settings.max_playback_rate_for_completion || 1.5 }}x</div>
                <div class="text-xs text-slate-500">Nguồn: {{ form.settings.source_type }}</div>
              </section>
              <section class="rounded-xl border border-slate-200 bg-slate-50 p-3">
                <div class="text-xs font-bold uppercase text-slate-500">Tiến độ trung bình</div>
                <div class="mt-2 text-sm text-slate-900">Người xem: {{ selectedAnalytics?.learners || 0 }}</div>
                <div class="text-sm text-slate-900">Hoàn thành: {{ selectedAnalytics?.completion_rate || 0 }}%</div>
                <div class="text-xs text-slate-500">Watch TB: {{ selectedAnalytics?.avg_watch_percent || 0 }}%</div>
              </section>
            </div>
          </div>

          <div class="flex flex-wrap items-center justify-between gap-2 border-t border-slate-200 p-4">
            <div class="flex flex-wrap items-center gap-2">
              <button class="inline-flex h-10 items-center gap-2 rounded-md border border-slate-300 px-3 text-sm font-semibold text-slate-700 disabled:opacity-40" :disabled="!selected || playbackLoading" @click="loadPlayback(selected)">
                <Eye class="h-4 w-4" />
                {{ playbackLoading ? 'Đang tải...' : 'Xem trước' }}
              </button>
            <button class="inline-flex h-10 items-center gap-2 rounded-md border border-slate-300 px-3 text-sm font-semibold text-emerald-700 disabled:opacity-40" :disabled="runningProcess" @click="processVideo(selected)">
                <SkipForward class="h-4 w-4" />
                {{ runningProcess && activeProcessVideoId === selected?.id ? 'Đang chạy...' : 'Xử lý' }}
              </button>
            </div>

            <button class="inline-flex h-10 items-center gap-2 rounded-md bg-blue-600 px-4 text-sm font-semibold text-white disabled:opacity-50" :disabled="saving" @click="saveVideo">
              <Save class="h-4 w-4" />
              {{ saving ? 'Đang lưu...' : 'Lưu cấu hình' }}
            </button>
          </div>
        </div>

        <aside class="space-y-4">
          <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
            <div class="flex items-center justify-between border-b border-slate-200 px-4 py-3">
              <div class="inline-flex items-center gap-2 text-sm font-bold text-slate-950"><BookOpenText class="h-4 w-4" /> Danh sách video theo bài học</div>
              <span class="text-xs text-slate-500">{{ loading ? 'Đang tải...' : `${visibleVideos.length} bản ghi` }}</span>
            </div>
            <div class="overflow-x-auto">
              <table class="w-full min-w-[940px] text-left text-sm">
                <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                  <tr>
                    <th class="px-4 py-3">Video</th>
                    <th class="px-4 py-3">Khóa học / Bài học</th>
                    <th class="px-4 py-3">Ngân hàng đề</th>
                    <th class="px-4 py-3">Khả dụng</th>
                    <th class="px-4 py-3">Thống kê</th>
                    <th class="px-4 py-3">Thao tác</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                  <tr v-for="video in visibleVideos" :key="video.id" :class="selected?.id === video.id ? 'bg-blue-50' : 'hover:bg-slate-50'">
                    <td class="px-4 py-3">
                      <button class="text-left font-semibold text-slate-950 hover:text-blue-700" @click="openVideo(video)">
                        {{ video.title || `Video #${video.id}` }}
                      </button>
                      <div class="mt-1 max-w-[220px] truncate text-xs text-slate-500">{{ video.original_filename || video.description || '-' }}</div>
                    </td>
                    <td class="px-4 py-3 text-slate-700">
                      <div>{{ resolveUsageText(video, 'course') }}</div>
                      <div class="text-xs text-slate-500">{{ resolveUsageText(video, 'component') }}</div>
                    </td>
                    <td class="px-4 py-3 text-xs text-slate-600">{{ questionBanksText(video) }}</td>
                    <td class="px-4 py-3">
                      <div class="mb-1 flex flex-wrap items-center gap-2">
                        <span class="rounded-full px-2.5 py-1 text-xs font-semibold ring-1" :class="statusClass(video.processing_status)">
                          {{ statusLabel(video.processing_status) }}
                        </span>
                        <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs text-slate-600">{{ visibilityLabel(video.visibility) }}</span>
                      </div>
                      <div class="text-xs text-slate-500">{{ formatDuration(video.duration_seconds) }} · {{ formatSize(video.file_size) }} · {{ video.renditions?.length || 0 }} bản mã hóa</div>
                    </td>
                    <td class="px-4 py-3 text-slate-700">
                      <div>{{ completionText(video, 'learners') }}</div>
                      <div class="text-xs text-slate-500">Xem TB: {{ completionText(video, 'avg') }}</div>
                      <div class="text-xs text-slate-500">Đã hoàn thành: {{ completionText(video, 'completed') }}</div>
                    </td>
                    <td class="px-4 py-3">
                      <div class="flex justify-end gap-1">
                        <button class="grid h-8 w-8 place-items-center rounded-md border border-slate-300 text-slate-700 hover:bg-white" title="Xem trước" @click="openAndPreview(video)">
                          <Eye class="h-4 w-4" />
                        </button>
                        <button class="grid h-8 w-8 place-items-center rounded-md border border-slate-300 text-slate-700 hover:bg-white" title="Chỉnh nhanh" @click="openVideo(video)">
                          <Pencil class="h-4 w-4" />
                        </button>
                        <button
                          class="grid h-8 w-8 place-items-center rounded-md border border-emerald-200 text-emerald-700 hover:bg-white disabled:opacity-40"
                          title="Xử lý"
                          :disabled="runningProcess"
                          @click="processVideo(video)"
                        >
                          <SkipForward class="h-4 w-4" />
                        </button>
                      </div>
                    </td>
                  </tr>
                  <tr v-if="!loading && !visibleVideos.length">
                    <td colspan="6" class="px-4 py-10 text-center text-sm text-slate-500">Không có video phù hợp bộ lọc.</td>
                  </tr>
                </tbody>
              </table>
            </div>
            <div class="flex items-center justify-between border-t border-slate-200 px-4 py-3 text-sm">
              <span class="text-slate-500">Trang {{ meta.current_page }} / {{ meta.last_page }}</span>
              <div class="flex gap-2">
                <button class="h-8 rounded-md border border-slate-300 px-3 font-semibold text-slate-700 disabled:opacity-40" :disabled="meta.current_page <= 1" @click="loadVideos(meta.current_page - 1)">Trước</button>
                <button class="h-8 rounded-md border border-slate-300 px-3 font-semibold text-slate-700 disabled:opacity-40" :disabled="meta.current_page >= meta.last_page" @click="loadVideos(meta.current_page + 1)">Sau</button>
              </div>
            </div>
          </div>

          <section class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <div class="inline-flex items-center gap-2 text-xs font-bold uppercase text-slate-700">
              <Settings2 class="h-4 w-4" />
              Cấu hình nâng cao video đã chọn
            </div>
            <div v-if="selected" class="mt-3 grid gap-3">
              <div class="grid gap-2">
                <label class="text-xs font-semibold text-slate-600">Tên video</label>
                <input v-model="form.title" class="h-10 rounded-md border border-slate-300 px-3 text-sm" />
              </div>
              <div class="grid gap-2">
                <label class="text-xs font-semibold text-slate-600">Mô tả</label>
                <textarea v-model="form.description" class="min-h-20 rounded-md border border-slate-300 px-3 py-2 text-sm"></textarea>
              </div>
              <div class="grid grid-cols-2 gap-3">
                <label class="block text-xs font-semibold text-slate-600">
                  Độ dài (giây)
                  <input v-model.number="form.duration_seconds" type="number" min="0" class="mt-1 h-10 w-full rounded-md border border-slate-300 px-3 text-sm" />
                </label>
                <label class="block text-xs font-semibold text-slate-600">
                  Trạng thái
                  <select v-model="form.processing_status" class="mt-1 h-10 w-full rounded-md border border-slate-300 px-3 text-sm">
                    <option value="pending">Chờ xử lý</option>
                    <option value="processing">Đang xử lý</option>
                    <option value="ready">Sẵn sàng</option>
                    <option value="failed">Lỗi</option>
                    <option value="archived">Lưu trữ</option>
                  </select>
                </label>
              </div>
              <div class="grid grid-cols-3 gap-2">
                <input v-model.number="form.settings.min_watch_percent" type="number" min="0" max="100" class="h-10 rounded-md border border-slate-300 px-3 text-sm" placeholder="Tối thiểu xem (%)" />
                <input v-model.number="form.settings.max_playback_rate_for_completion" type="number" min="0.25" step="0.25" class="h-10 rounded-md border border-slate-300 px-3 text-sm" placeholder="Tốc độ tối đa" />
                <input v-model.number="form.settings.heartbeat_interval_seconds" type="number" min="5" class="h-10 rounded-md border border-slate-300 px-3 text-sm" placeholder="Heartbeat (giây)" />
              </div>
              <label class="grid grid-cols-2 gap-2 text-xs font-semibold text-slate-700">
                <span class="rounded-md bg-slate-50 p-2"><input v-model="form.settings.require_heartbeat" type="checkbox" class="mr-2 align-middle" /> Yêu cầu heartbeat</span>
                <span class="rounded-md bg-slate-50 p-2"><input v-model="form.settings.allow_seek" type="checkbox" class="mr-2 align-middle" /> Cho phép tua</span>
                <span class="rounded-md bg-slate-50 p-2"><input v-model="form.settings.allow_download" type="checkbox" class="mr-2 align-middle" /> Cho phép tải xuống</span>
                <span class="rounded-md bg-slate-50 p-2"><input v-model="form.settings.captions_required" type="checkbox" class="mr-2 align-middle" /> Bắt buộc phụ đề</span>
              </label>

              <label class="text-xs font-semibold text-slate-600">URL nguồn (nếu bên ngoài)</label>
              <input v-model="form.settings.source_url" class="h-10 rounded-md border border-slate-300 px-3 text-sm" />

              <label class="text-xs font-semibold text-slate-600">URL ảnh bìa</label>
              <input v-model="form.thumbnail_url" class="h-10 rounded-md border border-slate-300 px-3 text-sm" />

              <label class="text-xs font-semibold text-slate-600">Trạng thái liên quan</label>
              <div class="rounded-md bg-slate-50 p-3 text-sm text-slate-700">
                <div><b>Khóa học:</b> {{ selectedCourse }}</div>
                <div><b>Bài học:</b> {{ selectedComponent }}</div>
                <div><b>Ngân hàng đề:</b> {{ resolveUsageText(selected, 'banks') }}</div>
              </div>
            </div>
            <div v-else class="rounded-md border border-dashed border-slate-300 p-3 text-center text-sm text-slate-500">Chưa chọn video để chỉnh.</div>
          </section>
        </aside>
      </div>
    </section>
  </EraLmsLayout>
</template>
