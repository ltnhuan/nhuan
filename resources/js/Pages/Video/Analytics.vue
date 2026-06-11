<script setup>
import { AlertTriangle, BarChart3, Brain, CheckCircle2, Clock3, Download, Eye, Pencil, RefreshCw, Search, SkipForward, Upload, Users, X } from '@lucide/vue'
import { computed, nextTick, onMounted, ref, watch } from 'vue'
import EraLmsLayout from '@/Layouts/EraLmsLayout.vue'

const props = defineProps({
  apiHeaders: { type: Object, default: () => ({}) },
  breadcrumb: { type: String, default: 'Video / Phân tích' },
  pageTitle: { type: String, default: 'Bảng phân tích video' },
  pageDescription: { type: String, default: 'Theo dõi hiệu suất khai thác, cảnh báo rủi ro học liệu và gợi ý tối ưu.' },
})

const loading = ref(false)
const saving = ref(false)
const runningProcess = ref(false)
const deleting = ref(false)
const analytics = ref({ summary: {}, progress: [], top_videos: [] })
const videos = ref([])
const courses = ref([])
const videoMeta = ref({ current_page: 1, last_page: 1, total: 0, per_page: 20 })
const selected = ref(null)
const detailOpen = ref(false)
const uploadOpen = ref(false)
const playback = ref(null)
const toast = ref('')
const filters = ref({
  q: '',
  course_id: '',
  processing_status: '',
  visibility: '',
  date_range: '30',
  per_page: 20,
  page: 1,
})
const form = ref(emptyForm())
const uploadForm = ref({ title: '', visibility: 'course', file: null })
const progressPage = ref(1)
const progressPerPage = 8
const aiInsightRunning = ref(false)
const playbackLoading = ref(false)

const dateRangeOptions = ['7', '14', '30', '90', 'all']

const summaryCards = computed(() => [
  ['Kho video', analytics.value.summary?.videos || videos.value.length, 'Tổng số assets trong hệ thống'],
  ['Đang sẵn sàng', analytics.value.summary?.ready || 0, `${analytics.value.summary?.failed || 0} lỗi`],
  ['Hoàn thành', `${analytics.value.summary?.completion_rate || 0}%`, `${analytics.value.summary?.completed || 0} bản ghi hoàn thành`],
  ['Người học theo dõi', analytics.value.summary?.learners_tracked || 0, 'Theo dõi trạng thái xem của người học'],
  ['Xem TB', `${analytics.value.summary?.avg_watch_percent || 0}%`, 'Trung bình % đã xem tại điểm cuối'],
  ['Cảnh báo nghi ngờ', analytics.value.summary?.suspicious_high || 0, 'Điểm nghi ngờ >= 60'],
])

const progressRows = computed(() => normalizedProgress.value)
const topVideos = computed(() => analytics.value.top_videos || [])

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
      require_heartbeat: true,
      allow_download: false,
    },
  }
}

const processingStatusLabels = {
  ready: 'Sẵn sàng',
  processing: 'Đang xử lý',
  pending: 'Chờ xử lý',
  failed: 'Lỗi',
  archived: 'Đã lưu trữ',
}

const visibilityLabels = {
  private: 'Riêng tư',
  course: 'Khóa học',
  tenant: 'Toàn hệ thống',
  public: 'Công khai',
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
  const payloadText = response.status === 204 ? '' : await response.text().catch(() => '')
  let payload = null
  try {
    payload = payloadText ? JSON.parse(payloadText) : null
  } catch (_error) {
    payload = null
  }

  if (!response.ok) {
    const fallback = typeof payloadText === 'string' ? payloadText.trim() : ''
    throw new Error((payload && typeof payload === 'object' && (payload.message || payload.error)) || fallback || 'Không xử lý được yêu cầu.')
  }

  return payload
}

function buildListParams() {
  const params = new URLSearchParams()
  Object.entries(filters.value).forEach(([key, value]) => {
    if (value === '' || value === null || key === 'date_range') return
    params.set(key, String(value))
  })
  return params
}

function buildAnalyticsParams() {
  const params = new URLSearchParams()
  if (filters.value.course_id) params.set('course_id', String(filters.value.course_id))
  if (filters.value.q) params.set('q', String(filters.value.q))
  return params
}

async function loadPage(page = 1) {
  loading.value = true
  filters.value.page = page
  progressPage.value = 1
  try {
    const listParams = buildListParams()
    listParams.set('page', String(page))
    const [videoPayload, analyticsPayload, coursePayload] = await Promise.all([
      api(`/api/v1/videos?${listParams.toString()}`),
      api(`/api/v1/videos/analytics?${buildAnalyticsParams().toString()}`),
      api('/api/v1/courses?per_page=100').catch(() => ({ data: [] })),
    ])
    videos.value = videoPayload.data || videoPayload
    analytics.value = analyticsPayload
    courses.value = coursePayload.data || coursePayload
    videoMeta.value = {
      current_page: videoPayload.current_page || 1,
      last_page: videoPayload.last_page || 1,
      total: videoPayload.total || videos.value.length,
      per_page: videoPayload.per_page || filters.value.per_page,
    }
    if (!selected.value && videos.value.length) {
      selected.value = videos.value[0]
    }
  } finally {
    loading.value = false
  }
}

function openCreate() {
  selected.value = null
  form.value = emptyForm()
  uploadOpen.value = true
}

function openDetail(video) {
  if (!video) return
  selected.value = video
  playback.value = null
  form.value = {
    ...emptyForm(),
    ...video,
    settings: { ...emptyForm().settings, ...(video.settings || {}) },
  }
  detailOpen.value = true
}

async function openAndPreview(video) {
  openDetail(video)
  await nextTick()
  await loadPlayback(video)
}

async function saveVideo() {
  if (!form.value.id) return
  saving.value = true
  try {
    const updated = await api(`/api/v1/videos/${form.value.id}`, {
      method: 'PUT',
      body: JSON.stringify({
        title: form.value.title,
        description: form.value.description,
        duration_seconds: Number(form.value.duration_seconds || 0),
        processing_status: form.value.processing_status,
        visibility: form.value.visibility,
        thumbnail_url: form.value.thumbnail_url,
        subtitle_path: form.value.subtitle_path,
        transcript_path: form.value.transcript_path,
        settings: {
          min_watch_percent: Number(form.value.settings?.min_watch_percent || 0),
          max_playback_rate_for_completion: Number(form.value.settings?.max_playback_rate_for_completion || 1),
          require_heartbeat: Boolean(form.value.settings?.require_heartbeat),
          allow_download: Boolean(form.value.settings?.allow_download),
        },
      }),
    })
    toast.value = 'Đã lưu thay đổi video.'
    selected.value = updated
    await loadPage(filters.value.page)
  } finally {
    saving.value = false
  }
}

async function uploadVideo() {
  if (!uploadForm.value.file) {
    toast.value = 'Chưa có file để upload.'
    return
  }
  saving.value = true
  try {
    const body = new FormData()
    body.append('title', uploadForm.value.title || uploadForm.value.file.name)
    body.append('visibility', uploadForm.value.visibility)
    body.append('file', uploadForm.value.file)
    const created = await api('/api/v1/videos/upload', { method: 'POST', body })
    uploadOpen.value = false
    uploadForm.value = { title: '', visibility: 'course', file: null }
    toast.value = 'Đã upload video mới.'
    await loadPage(1)
    openDetail(created)
  } finally {
    saving.value = false
  }
}

async function deleteVideo(video) {
  if (!video?.id) return
  if (!window.confirm(`Xóa video "${video.title}" và toàn bộ tracking liên quan?`)) return
  deleting.value = true
  try {
    await api(`/api/v1/videos/${video.id}`, { method: 'DELETE' })
    detailOpen.value = false
    toast.value = 'Đã xóa video.'
    await loadPage(filters.value.page)
  } finally {
    deleting.value = false
  }
}

async function loadPlayback(video = selected.value) {
  const target = video || selected.value
  if (!target?.id) {
    toast.value = 'Chọn video trước khi xem trước.'
    return
  }
  playbackLoading.value = true
  try {
    playback.value = await api(`/api/v1/videos/${target.id}/playback-url`)
  } catch (error) {
    toast.value = error?.message || 'Không thể tạo link phát.'
    playback.value = null
  } finally {
    playbackLoading.value = false
  }
}

async function processVideo(video = selected.value) {
  if (!video?.id) {
    toast.value = 'Chưa có video để chạy xử lý.'
    return
  }
  runningProcess.value = true
  try {
    await api(`/api/v1/videos/${video.id}/process`, { method: 'POST' })
    toast.value = 'Video đã vào hàng xử lý.'
    await loadPage(filters.value.page)
  } catch (error) {
    toast.value = error?.message || 'Không thể chạy xử lý video.'
  } finally {
    runningProcess.value = false
  }
}

function exportCsv() {
  const lines = [
    ['Video', 'Người dùng', 'Khóa học', 'Đã xem %', 'Hoàn thành', 'Nghi ngờ', 'Xem lần cuối'].join(','),
    ...progressRows.value.map((row) => [
      csv(row.asset?.title || `Video #${row.video_asset_id}`),
      row.user_id,
      csv(resolveUsageText(findVideo(row.video_asset_id), 'course')),
      row.watch_percent,
      row.is_completed ? 'yes' : 'no',
      row.suspicious_score,
      row.last_watched_at || '',
    ].join(',')),
  ]
  const blob = new Blob([lines.join('\n')], { type: 'text/csv;charset=utf-8' })
  const url = URL.createObjectURL(blob)
  const link = document.createElement('a')
  link.href = url
  link.download = 'video-analytics-progress.csv'
  link.click()
  URL.revokeObjectURL(url)
}

function csv(value) {
  return `"${String(value || '').replaceAll('"', '""')}"`
}

function parseDate(value) {
  if (!value) return null
  const parsed = new Date(value)
  return Number.isNaN(parsed.getTime()) ? null : parsed
}

function formatDuration(seconds) {
  const value = Number(seconds || 0)
  const m = Math.floor(value / 60)
  const s = value % 60
  return `${m}:${String(s).padStart(2, '0')}`
}

function formatSize(bytes) {
  const value = Number(bytes || 0)
  if (value >= 1024 * 1024) return `${(value / 1024 / 1024).toFixed(1)} MB`
  if (value >= 1024) return `${(value / 1024).toFixed(1)} KB`
  return `${value} B`
}

function formatDateTime(value) {
  const parsed = parseDate(value)
  if (!parsed) return '-'
  return `${parsed.toLocaleDateString('vi-VN')} ${parsed.toLocaleTimeString('vi-VN', { hour: '2-digit', minute: '2-digit' })}`
}

function formatPercent(value, digits = 1) {
  const parsed = Number(value || 0)
  return `${Number.isFinite(parsed) ? parsed.toFixed(digits) : 0}%`
}

function questionBankText(video) {
  const banks = video?.usage?.question_banks || []
  if (!banks.length) return 'Chưa gắn ngân hàng đề'
  return banks.map((bank) => bank.code || bank.id).join(', ')
}

function resolveUsageText(video, key) {
  if (!video) return '-'
  if (key === 'course') {
    return video.course?.title || video.usage?.course?.title || '-'
  }
  if (key === 'component') {
    return video.component?.title || video.usage?.component?.title || '-'
  }
  return video.usage?.question_banks?.map((item) => item.code || item.id).join(', ') || '-'
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

function processingStatusLabel(status) {
  return processingStatusLabels[status] || status || '-'
}

function bucketRows(rows, ranges, field) {
  const buckets = ranges.map((range) => ({ ...range, count: 0 }))
  rows.forEach((row) => {
    const value = Number(row?.[field] || 0)
    for (const bucket of buckets) {
      const isLast = buckets.indexOf(bucket) === buckets.length - 1
      if ((value >= bucket.min && value < bucket.max) || (isLast && value >= bucket.min)) {
        bucket.count += 1
        break
      }
    }
  })
  const max = Math.max(...buckets.map((b) => b.count), 1)
  return buckets.map((bucket) => ({ ...bucket, percent: Math.round((bucket.count / max) * 100) }))
}

const normalizedProgress = computed(() =>
  (analytics.value.progress || []).map((row) => ({
    ...row,
    watch_percent: Number(row.watch_percent || 0),
    suspicious_score: Number(row.suspicious_score || 0),
    user_id_num: Number(row.user_id || 0),
    last_watched_at_obj: parseDate(row.last_watched_at),
  })),
)

const watchBuckets = computed(() =>
  bucketRows(
    filteredProgressRows.value,
    [
      { label: '0 - 25%', min: 0, max: 25, color: 'bg-sky-500' },
      { label: '26 - 50%', min: 25, max: 50, color: 'bg-blue-500' },
      { label: '51 - 75%', min: 50, max: 75, color: 'bg-indigo-500' },
      { label: '76 - 90%', min: 75, max: 90, color: 'bg-purple-500' },
      { label: '91 - 100%', min: 90, max: 101, color: 'bg-emerald-500' },
    ],
    'watch_percent',
  ),
)

const suspiciousBuckets = computed(() =>
  bucketRows(
    filteredProgressRows.value,
    [
      { label: '0 - 20', min: 0, max: 20, color: 'bg-emerald-500' },
      { label: '21 - 40', min: 20, max: 40, color: 'bg-blue-500' },
      { label: '41 - 60', min: 40, max: 60, color: 'bg-amber-500' },
      { label: '61 - 80', min: 60, max: 80, color: 'bg-orange-500' },
      { label: '81 - 100', min: 80, max: 101, color: 'bg-rose-500' },
    ],
    'suspicious_score',
  ),
)

const filteredProgressRows = computed(() => {
  if (filters.value.date_range === 'all') return normalizedProgress.value
  const days = Number(filters.value.date_range || 30)
  if (!days) return normalizedProgress.value
  const from = Date.now() - days * 24 * 60 * 60 * 1000
  return normalizedProgress.value.filter((row) => row.last_watched_at_obj && row.last_watched_at_obj.getTime() >= from)
})

const trendWindow = computed(() => (filters.value.date_range === 'all' ? 14 : Number(filters.value.date_range || 30)))

const completionTrend = computed(() => {
  const map = new Map()
  const rows = filteredProgressRows.value
  const days = Math.max(7, Math.min(30, trendWindow.value || 30))
  const end = new Date()
  end.setHours(23, 59, 59, 999)
  const start = new Date(end)
  start.setDate(end.getDate() - days + 1)
  start.setHours(0, 0, 0, 0)

  for (let day = 0; day < days; day += 1) {
    const current = new Date(start)
    current.setDate(start.getDate() + day)
    current.setHours(0, 0, 0, 0)
    const key = current.toISOString().slice(0, 10)
    map.set(key, {
      key,
      dayLabel: current.toLocaleDateString('vi-VN', { day: '2-digit', month: '2-digit' }),
      users: new Set(),
      sessions: 0,
      watchTotal: 0,
      watchCount: 0,
    })
  }

  rows.forEach((row) => {
    if (!row.last_watched_at_obj) return
    const keyDate = new Date(row.last_watched_at_obj)
    keyDate.setHours(0, 0, 0, 0)
    const key = keyDate.toISOString().slice(0, 10)
    const point = map.get(key)
    if (!point) return
    point.sessions += 1
    point.watchTotal += row.watch_percent
    point.watchCount += 1
    if (row.user_id_num) point.users.add(row.user_id_num)
  })

  return [...map.values()].map((point) => ({
    ...point,
    learners: point.users.size,
    avgWatch: point.watchCount ? Number((point.watchTotal / point.watchCount).toFixed(1)) : 0,
    users: undefined,
  }))
})

const trendMax = computed(() => Math.max(...completionTrend.value.map((item) => item.learners), 1))

const videoUsageIndex = computed(() => {
  const map = new Map()
  for (const video of videos.value) {
    map.set(video.id, video)
  }
  return map
})

const topVideosWithUsage = computed(() =>
  (topVideos.value || []).map((item) => {
    const indexedVideo = videoUsageIndex.value.get(item.video_asset_id)
    const video = item?.asset || indexedVideo
    return {
      ...item,
      sourceVideo: video,
      usageCourse: resolveUsageText(video, 'course'),
      usageComponent: resolveUsageText(video, 'component'),
      banks: questionBankText(video),
      riskColor: Number(item.avg_watch || 0) < 50 || Number(item.max_suspicious || 0) >= 60 ? 'text-rose-700' : 'text-emerald-700',
    }
  }),
)

const topRiskVideos = computed(() => {
  return [...topVideosWithUsage.value]
    .map((video) => ({
      ...video,
      riskScore: (Number(video.max_suspicious || 0) * 1.2) + ((100 - Number(video.avg_watch || 0)) * 0.6),
    }))
    .sort((a, b) => b.riskScore - a.riskScore)
    .slice(0, 3)
})

const progressPageCount = computed(() => Math.max(1, Math.ceil(filteredProgressRows.value.length / progressPerPage)))
const pagedProgressRows = computed(() => {
  const start = (progressPage.value - 1) * progressPerPage
  return filteredProgressRows.value.slice(start, start + progressPerPage)
})

const aiInsights = computed(() => {
  const summary = analytics.value.summary || {}
  const learners = Number(summary.learners_tracked || 0)
  const completionRate = Number(summary.completion_rate || 0)
  const avgWatch = Number(summary.avg_watch_percent || 0)
  const suspiciousRate = learners ? (Number(summary.suspicious_high || 0) / learners) * 100 : 0
  const lowCompletionRate = learners ? (Number(summary.below_50 || 0) / learners) * 100 : 0
  const failedRate = Number(summary.videos || 0) ? (Number(summary.failed || 0) / Number(summary.videos || 1)) * 100 : 0
  const processingRate = Number(summary.processing || 0) ? (Number(summary.processing || 0) / Number(summary.videos || 1)) * 100 : 0
  let score = 100
  const risks = []
  const suggestions = []

  if (completionRate < 55) {
    score -= 25
    risks.push({ title: 'Tỷ lệ hoàn thành thấp', detail: `Chỉ ${completionRate}% hồ sơ có trạng thái hoàn thành.` })
    suggestions.push('Rà lại ngưỡng hoàn thành, giảm mức quá cao cho các khóa học có video dài hoặc phức tạp.')
  }
  if (avgWatch < 52) {
    score -= 16
    risks.push({ title: 'Engagement còn thấp', detail: `Watch trung bình chỉ ${avgWatch}%, người học chưa theo dõi đủ nội dung.` })
    suggestions.push('Kẹp điểm dừng theo phân đoạn ngắn hơn; chèn điểm kiểm tra nhanh giữa video.')
  }
  if (suspiciousRate > 12) {
    score -= 20
    risks.push({ title: 'Nhiều hành vi đáng ngờ', detail: `${suspiciousRate.toFixed(1)}% bản ghi có suspicious_score cao.` })
    suggestions.push('Bật yêu cầu nhịp theo dõi, giảm tốc độ phát tối đa và khóa tua nhanh ở các bài trọng tâm.')
  }
  if (lowCompletionRate > 35) {
    score -= 12
    risks.push({ title: 'Phân bố xem thấp', detail: `${lowCompletionRate.toFixed(1)}% người học xem dưới 50%.` })
    suggestions.push('Rà lại cấu trúc video, tối ưu ảnh đại diện/chương mục mở đầu để kéo người học vào học phần.')
  }
  if (failedRate > 8) {
    score -= 12
    risks.push({ title: 'Xử lý encoding lỗi nhiều', detail: `${summary.failed || 0} video failed/processing quá lâu.` })
    suggestions.push('Kiểm tra hàng đợi xử lý, cấu hình mã hóa và thử lại với cấu hình chất lượng thấp hơn.')
  }
  if (processingRate > 20) {
    score -= 8
    risks.push({ title: 'Nhiều video pending', detail: `${Number(summary.processing || 0)} video đang chưa sẵn sàng phát.` })
    suggestions.push('Tối ưu tiến trình xử lý/hàng đợi, đặt cảnh báo khi quá 2 giờ chưa chuyển sang trạng thái sẵn sàng.')
  }

  if (!suggestions.length) {
    suggestions.push('Dữ liệu ổn định trong ngữ cảnh hiện tại; duy trì theo dõi theo tuần để phát hiện sớm dao động.')
  }

  const finalScore = Math.max(0, Math.min(100, Math.round(score)))
  const label = finalScore >= 85 ? 'Tốt' : finalScore >= 60 ? 'Cần tối ưu' : 'Rủi ro'
  return {
    score: finalScore,
    label,
    risks,
    suggestions,
  }
})

const dateRangeLabel = computed(() => (filters.value.date_range === 'all' ? 'Toàn bộ dữ liệu' : `Trong ${filters.value.date_range} ngày gần nhất`))

function setDateRange(value) {
  filters.value.date_range = value
  progressPage.value = 1
}

function findVideo(videoAssetId) {
  return videoUsageIndex.value.get(videoAssetId) || null
}

async function refreshAiInsights() {
  aiInsightRunning.value = true
  await nextTick()
  setTimeout(() => {
    aiInsightRunning.value = false
  }, 350)
}

watch(() => filters.value.q, () => {
  progressPage.value = 1
})

onMounted(() => {
  loadPage(1)
})
</script>

<template>
  <EraLmsLayout>
    <template #breadcrumb>{{ breadcrumb }}</template>

    <section class="border-b border-blue-900/30 bg-gradient-to-r from-slate-950 via-blue-950 to-cyan-950 text-white">
      <div class="mx-auto max-w-7xl px-6 py-5">
        <div class="flex flex-wrap items-start justify-between gap-3">
          <div>
            <h1 class="text-xl font-bold text-white">{{ pageTitle }}</h1>
            <p class="mt-1 text-sm text-blue-100">{{ pageDescription }}</p>
          </div>
          <div class="flex flex-wrap gap-2">
            <button class="inline-flex h-10 items-center gap-2 rounded-md border border-white/30 bg-white/5 px-3 text-sm font-semibold text-white hover:bg-white/20" @click="loadPage(filters.page)">
              <RefreshCw class="h-4 w-4" />
              Tải lại
            </button>
            <button class="inline-flex h-10 items-center gap-2 rounded-md border border-white/30 bg-white/5 px-3 text-sm font-semibold text-white hover:bg-white/20" @click="exportCsv">
              <Download class="h-4 w-4" />
              Xuất CSV
            </button>
            <button class="inline-flex h-10 items-center gap-2 rounded-md bg-blue-600 px-3 text-sm font-semibold text-white hover:bg-blue-700" @click="openCreate">
              <Upload class="h-4 w-4" />
              Tải video lên
            </button>
          </div>
        </div>

        <div class="mt-5 grid gap-3 rounded-xl border border-white/20 bg-white/5 p-4 backdrop-blur-sm md:grid-cols-[1fr_170px_160px_120px]">
          <label class="relative">
            <Search class="pointer-events-none absolute left-3 top-2.5 h-4 w-4 text-blue-200" />
            <input
              v-model="filters.q"
              class="h-10 w-full rounded-md border border-white/20 bg-white px-3 py-2 text-sm text-slate-900 placeholder:text-slate-500 pl-9 pr-3"
              placeholder="Tìm theo tên hoặc mô tả video..."
              @keyup.enter="loadPage(1)"
            />
          </label>
          <select v-model="filters.course_id" class="h-10 rounded-md border border-white/20 bg-white px-3 text-sm text-slate-900" @change="loadPage(1)">
            <option value="">Tất cả khóa học</option>
            <option v-for="course in courses" :key="course.id" :value="course.id">{{ course.title }}</option>
          </select>
          <select v-model="filters.processing_status" class="h-10 rounded-md border border-white/20 bg-white px-3 text-sm text-slate-900" @change="loadPage(1)">
            <option value="">Tất cả trạng thái</option>
            <option v-for="(label, value) in processingStatusLabels" :key="value" :value="value">{{ label }}</option>
          </select>
          <select v-model="filters.visibility" class="h-10 rounded-md border border-white/20 bg-white px-3 text-sm text-slate-900" @change="loadPage(1)">
            <option value="">Tất cả phạm vi</option>
            <option v-for="(label, value) in visibilityLabels" :key="value" :value="value">{{ label }}</option>
          </select>
        </div>

        <div class="mt-4 flex flex-wrap gap-2">
          <button
            v-for="option in dateRangeOptions"
            :key="option"
            class="inline-flex h-8 items-center rounded-full border px-3 text-xs font-semibold transition"
            :class="filters.date_range === option ? 'border-white bg-white/90 text-slate-900' : 'border-white/30 text-blue-100 hover:bg-white/10'"
            @click="setDateRange(option)"
          >
            {{ option === 'all' ? 'Toàn thời gian' : `${option} ngày` }}
          </button>
        </div>
      </div>
    </section>

    <section class="mx-auto max-w-7xl px-6 py-5">
      <div v-if="toast" class="mb-4 flex items-center justify-between rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-800">
        <span>{{ toast }}</span>
        <button class="grid h-7 w-7 place-items-center rounded-md hover:bg-emerald-100" @click="toast = ''">
          <X class="h-4 w-4" />
        </button>
      </div>

      <div class="grid gap-3 md:grid-cols-3 xl:grid-cols-6">
        <div v-for="[label, value, detail] in summaryCards" :key="label" class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
          <div class="text-xs font-bold uppercase text-slate-500">{{ label }}</div>
          <div class="mt-2 text-2xl font-bold text-slate-950">{{ value }}</div>
          <div class="mt-1 text-xs text-slate-500">{{ detail }}</div>
        </div>
      </div>

      <div class="mt-5 grid gap-4 xl:grid-cols-[minmax(0,1.35fr)_minmax(340px,0.65fr)]">
        <div class="space-y-4">
          <article class="overflow-hidden rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <div class="mb-3 flex items-center justify-between">
              <div class="inline-flex items-center gap-2 font-bold text-slate-950">
                <BarChart3 class="h-4 w-4" />
                Xu hướng học tập theo {{ dateRangeLabel }}
              </div>
              <div class="text-xs text-slate-500">{{ completionTrend.length }} ngày</div>
            </div>
            <div class="grid h-40 grid-cols-7 gap-2 md:grid-cols-14">
              <div
                v-for="point in completionTrend"
                :key="point.key"
                class="flex min-w-0 flex-col justify-end gap-1 text-center"
                :title="`${point.dayLabel}: ${point.learners} học viên, ${point.avgWatch}% xem trung bình`"
              >
                <div class="h-full overflow-hidden rounded-full bg-slate-100 px-1">
                  <div
                    class="w-full rounded-full bg-gradient-to-t from-blue-600 to-cyan-500 transition-all"
                    :style="{ height: `${Math.max((point.learners / trendMax) * 100, 4)}%` }"
                  ></div>
                </div>
                <div class="text-[11px] text-slate-500">{{ point.dayLabel }}</div>
                <div class="text-[11px] font-semibold text-slate-700">{{ point.learners }}</div>
              </div>
            </div>
          </article>

          <div class="grid gap-4 lg:grid-cols-2">
            <article class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
              <div class="mb-3 flex items-center justify-between">
                <div class="inline-flex items-center gap-2 text-sm font-bold text-slate-950">
                  <Clock3 class="h-4 w-4" />
                  Phân bố % đã xem
                </div>
                <span class="text-xs text-slate-500">Tổng {{ normalizedProgress.length }} bản ghi</span>
              </div>
              <div class="space-y-2.5">
                <div v-for="bucket in watchBuckets" :key="bucket.label" class="space-y-1">
                  <div class="flex justify-between text-xs text-slate-600">
                    <span>{{ bucket.label }}</span>
                    <span>{{ bucket.count }}</span>
                  </div>
                  <div class="h-2 overflow-hidden rounded-full bg-slate-100">
                    <div
                      class="h-full rounded-full transition-all duration-300"
                      :class="bucket.color"
                      :style="{ width: `${Math.min(bucket.percent, 100)}%` }"
                    ></div>
                  </div>
                </div>
              </div>
            </article>

            <article class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
              <div class="mb-3 flex items-center justify-between">
                <div class="inline-flex items-center gap-2 text-sm font-bold text-slate-950">
                  <AlertTriangle class="h-4 w-4" />
                  Mức cảnh báo suspicious
                </div>
                <span class="text-xs text-slate-500">Dữ liệu lọc: {{ dateRangeLabel }}</span>
              </div>
              <div class="space-y-2.5">
                <div v-for="bucket in suspiciousBuckets" :key="bucket.label" class="space-y-1">
                  <div class="flex justify-between text-xs text-slate-600">
                    <span>{{ bucket.label }}</span>
                    <span>{{ bucket.count }}</span>
                  </div>
                  <div class="h-2 overflow-hidden rounded-full bg-slate-100">
                    <div
                      class="h-full rounded-full transition-all duration-300"
                      :class="bucket.color"
                      :style="{ width: `${Math.min(bucket.percent, 100)}%` }"
                    ></div>
                  </div>
                </div>
              </div>
            </article>
          </div>
        </div>

        <aside class="space-y-4">
          <article class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <div class="mb-3 inline-flex items-center gap-2 text-sm font-bold text-slate-950">
              <Brain class="h-4 w-4" /> AI phân tích hiệu xuất
            </div>
            <div class="rounded-md bg-slate-50 p-4">
              <div class="flex items-center gap-3">
                <div class="relative h-16 w-16 shrink-0 rounded-full border border-slate-200 bg-white">
                  <div class="absolute inset-1 grid place-items-center rounded-full">
                    <span class="text-lg font-bold" :class="aiInsights.score >= 80 ? 'text-emerald-600' : aiInsights.score >= 60 ? 'text-amber-600' : 'text-rose-600'">
                      {{ aiInsights.score }}
                    </span>
                  </div>
                </div>
                <div>
                  <div class="text-xs text-slate-500">Điểm sức khỏe AI</div>
                  <div class="text-base font-bold text-slate-900">{{ aiInsights.label }}</div>
              <button
                class="mt-1 rounded-md border border-slate-300 px-2 py-1 text-xs font-semibold text-slate-700 hover:bg-slate-100"
                :disabled="aiInsightRunning"
                @click="refreshAiInsights"
              >
                    {{ aiInsightRunning ? 'Đang phân tích...' : 'Làm mới AI' }}
                  </button>
                </div>
              </div>
              <div class="mt-4 space-y-3">
                <div v-if="!aiInsights.risks.length" class="rounded-md bg-emerald-50 p-3 text-xs text-emerald-800">
                  Hệ thống đang ổn: không nhận diện rủi ro đáng chú ý theo bộ chỉ số hiện tại.
                </div>
                <ul v-else class="space-y-2">
                  <li v-for="risk in aiInsights.risks" :key="risk.title" class="rounded-md bg-amber-50 p-3 text-sm">
                    <div class="font-semibold text-amber-900">{{ risk.title }}</div>
                    <div class="mt-1 text-xs text-amber-800">{{ risk.detail }}</div>
                  </li>
                </ul>
              </div>
            </div>

            <div class="mt-4">
              <div class="text-xs font-bold uppercase text-slate-600">Gợi ý tối ưu</div>
              <ul class="mt-2 space-y-1 text-xs">
                <li v-for="suggestion in aiInsights.suggestions" :key="suggestion" class="rounded-md bg-slate-50 px-3 py-2 text-slate-700">{{ suggestion }}</li>
              </ul>
            </div>
          </article>

          <article class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <div class="mb-3 inline-flex items-center gap-2 text-sm font-bold text-slate-950">
              <Users class="h-4 w-4" /> Top video cần tối ưu
            </div>
            <div v-if="topRiskVideos.length">
              <article v-for="item in topRiskVideos" :key="item.video_asset_id" class="rounded-md border border-slate-200 bg-slate-50 p-3 text-sm">
                <div class="font-semibold text-slate-950">{{ item.asset?.title || `Video #${item.video_asset_id}` }}</div>
                <div class="mt-1 text-xs text-slate-600">
                  {{ item.learners || 0 }} lượt | TB {{ formatPercent(item.avg_watch || 0) }} | Max suspicious {{ Number(item.max_suspicious || 0) }}
                </div>
                <div class="mt-2 text-xs text-slate-600">Ngân hàng đề: {{ item.banks }}</div>
                <div class="mt-2 h-2 rounded-full bg-slate-200">
                  <div class="h-full rounded-full bg-blue-600" :style="{ width: `${Math.min(Number(item.avg_watch || 0), 100)}%` }"></div>
                </div>
              </article>
            </div>
            <div v-else class="rounded-md border border-slate-200 bg-slate-50 p-3 text-center text-sm text-slate-600">Chưa có dữ liệu rủi ro.</div>
          </article>
        </aside>
      </div>

      <div class="mt-5 grid gap-4 xl:grid-cols-[minmax(0,1.1fr)_minmax(320px,0.9fr)]">
        <section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
          <div class="flex items-center justify-between border-b border-slate-200 px-4 py-3">
            <div class="font-bold text-slate-950">Kho video</div>
            <div class="text-xs text-slate-500">{{ loading ? 'Đang tải...' : `${videoMeta.total} bản ghi` }}</div>
          </div>
          <div class="overflow-x-auto">
            <table class="w-full min-w-[1080px] text-left text-sm">
              <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                <tr>
                  <th class="px-4 py-3">Video</th>
                  <th class="px-4 py-3">Khóa học / Bài học</th>
                  <th class="px-4 py-3">Ngân hàng đề</th>
                  <th class="px-4 py-3">Metadata</th>
                  <th class="px-4 py-3">Trạng thái</th>
                  <th class="px-4 py-3 text-right">Hành động</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-100">
                <tr v-for="video in videos" :key="video.id" :class="selected?.id === video.id ? 'bg-blue-50' : 'hover:bg-slate-50'">
                  <td class="px-4 py-3">
                    <button class="text-left font-semibold text-slate-950 hover:text-blue-700" @click="openDetail(video)">{{ video.title }}</button>
                    <div class="mt-1 max-w-[250px] truncate text-xs text-slate-500">{{ video.original_filename || video.description || 'Chưa có mô tả' }}</div>
                  </td>
                  <td class="px-4 py-3 text-slate-700">
                    <div>{{ resolveUsageText(video, 'course') }}</div>
                    <div class="text-xs text-slate-500">{{ resolveUsageText(video, 'component') }}</div>
                  </td>
                  <td class="px-4 py-3 text-xs text-slate-700">{{ questionBankText(video) }}</td>
                  <td class="px-4 py-3 text-slate-700">
                    <div>{{ formatDuration(video.duration_seconds) }} · {{ formatSize(video.file_size) }}</div>
                    <div class="mt-1 text-xs text-slate-500">{{ video.renditions?.length || 0 }} bản mã hóa</div>
                  </td>
                  <td class="px-4 py-3"><span class="rounded-full px-2.5 py-1 text-xs font-semibold ring-1" :class="statusClass(video.processing_status)">{{ processingStatusLabel(video.processing_status) }}</span></td>
                  <td class="px-4 py-3">
                    <div class="flex justify-end gap-1">
                    <button class="grid h-8 w-8 place-items-center rounded-md border border-slate-300 text-slate-700 hover:bg-white" title="Xem trước và chỉnh" @click="openAndPreview(video)">
                        <Eye class="h-4 w-4" />
                      </button>
                      <button class="grid h-8 w-8 place-items-center rounded-md border border-emerald-200 text-emerald-700 hover:bg-white disabled:opacity-40" :disabled="runningProcess" title="Chạy xử lý" @click="processVideo(video)">
                        <SkipForward class="h-4 w-4" />
                      </button>
                    </div>
                  </td>
                </tr>
                <tr v-if="!loading && !videos.length">
                  <td colspan="6" class="px-4 py-10 text-center text-sm text-slate-500">Không có video phù hợp bộ lọc.</td>
                </tr>
              </tbody>
            </table>
          </div>
          <div class="flex items-center justify-between border-t border-slate-200 px-4 py-3 text-sm">
            <span class="text-slate-500">Trang {{ videoMeta.current_page }} / {{ videoMeta.last_page }}</span>
            <div class="flex gap-2">
              <button
                class="h-9 rounded-md border border-slate-300 px-3 font-semibold text-slate-700 disabled:opacity-40"
                :disabled="videoMeta.current_page <= 1"
                @click="loadPage(videoMeta.current_page - 1)"
              >
                Trước
              </button>
              <button
                class="h-9 rounded-md border border-slate-300 px-3 font-semibold text-slate-700 disabled:opacity-40"
                :disabled="videoMeta.current_page >= videoMeta.last_page"
                @click="loadPage(videoMeta.current_page + 1)"
              >
                Sau
              </button>
            </div>
          </div>
        </section>

        <aside class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
          <div class="border-b border-slate-200 px-4 py-3 font-bold text-slate-950">Top video theo lượt theo dõi</div>
          <div class="max-h-[420px] divide-y divide-slate-100 overflow-auto">
            <article v-for="item in topVideos" :key="item.video_asset_id" class="p-4" :class="item.banks ? '' : 'bg-slate-50'">
              <div class="flex items-start justify-between gap-3">
                <div>
                  <div class="font-semibold text-slate-950">{{ item.asset?.title || `Video #${item.video_asset_id}` }}</div>
                  <div class="mt-1 text-xs text-slate-500">{{ item.learners }} học viên · {{ Number(item.avg_watch || 0).toFixed(1) }}% trung bình</div>
                  <div class="mt-1 text-xs text-slate-500">Khóa học: {{ item.usageCourse || resolveUsageText(item.sourceVideo, 'course') }}</div>
                  <div class="mt-1 text-xs text-indigo-700">Ngân hàng đề: {{ item.banks }}</div>
                </div>
                <div class="rounded-md bg-slate-900/5 px-2.5 py-1 text-xs font-bold" :class="item.riskColor">{{ item.completed_count || 0 }} hoàn thành</div>
              </div>
              <div class="mt-3 h-2 overflow-hidden rounded-full bg-slate-100">
                <div class="h-full rounded-full bg-blue-600" :style="{ width: `${Math.min(Number(item.avg_watch || 0), 100)}%` }"></div>
              </div>
            </article>
            <div v-if="!topVideos.length" class="p-6 text-center text-sm text-slate-500">Chưa có dữ liệu xem video.</div>
          </div>
        </aside>
      </div>

      <section class="mt-5 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="flex items-center justify-between border-b border-slate-200 px-4 py-3">
          <div class="font-bold text-slate-950">Tiến độ người học gần nhất</div>
          <div class="text-xs text-slate-500">{{ filteredProgressRows.length }} bản tóm tắt</div>
        </div>
        <div class="overflow-x-auto">
          <table class="w-full min-w-[860px] text-left text-sm">
            <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
              <tr>
                <th class="px-4 py-3">Video</th>
                <th class="px-4 py-3">Người dùng</th>
                <th class="px-4 py-3">Đã xem</th>
                <th class="px-4 py-3">Hoàn thành</th>
                <th class="px-4 py-3">Nghi ngờ</th>
                <th class="px-4 py-3">Lần cuối</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              <tr v-for="row in pagedProgressRows" :key="row.id" class="hover:bg-slate-50">
                <td class="px-4 py-3 font-semibold text-slate-950">{{ row.asset?.title || `Video #${row.video_asset_id}` }}</td>
                <td class="px-4 py-3 text-slate-700">#{{ row.user_id }}</td>
                <td class="px-4 py-3">
                  <div class="flex items-center gap-3">
                    <div class="h-2 w-24 overflow-hidden rounded-full bg-slate-100">
                      <div class="h-full rounded-full bg-blue-600" :style="{ width: `${Math.min(Number(row.watch_percent || 0), 100)}%` }"></div>
                    </div>
                    <span class="text-slate-700">{{ row.watch_percent }}%</span>
                  </div>
                </td>
                <td class="px-4 py-3" :class="row.is_completed ? 'text-emerald-700' : 'text-slate-500'">{{ row.is_completed ? 'Đã hoàn thành' : 'Đang xem' }}</td>
                <td class="px-4 py-3 font-semibold" :class="Number(row.suspicious_score || 0) >= 60 ? 'text-rose-600' : 'text-slate-700'">{{ row.suspicious_score }}</td>
                <td class="px-4 py-3 text-slate-500">{{ formatDateTime(row.last_watched_at) }}</td>
              </tr>
              <tr v-if="!pagedProgressRows.length">
                <td colspan="6" class="px-4 py-10 text-center text-sm text-slate-500">Chưa có dữ liệu tiến độ.</td>
              </tr>
            </tbody>
          </table>
        </div>
        <div class="flex items-center justify-end gap-2 border-t border-slate-200 px-4 py-3 text-sm">
          <button class="h-8 rounded-md border border-slate-300 px-3 font-semibold text-slate-700 disabled:opacity-40" :disabled="progressPage <= 1" @click="progressPage -= 1">Trước</button>
          <span class="text-xs text-slate-500">{{ progressPage }} / {{ progressPageCount }}</span>
          <button class="h-8 rounded-md border border-slate-300 px-3 font-semibold text-slate-700 disabled:opacity-40" :disabled="progressPage >= progressPageCount" @click="progressPage += 1">Sau</button>
        </div>
      </section>
    </section>

    <div v-if="detailOpen" class="fixed inset-0 z-50">
      <button class="absolute inset-0 bg-slate-950/25" @click="detailOpen = false"></button>
      <aside class="absolute right-4 top-4 flex h-[calc(100vh-2rem)] w-[560px] max-w-[calc(100vw-2rem)] flex-col overflow-hidden rounded-xl border border-slate-200 bg-white shadow-2xl">
        <div class="flex h-16 items-center justify-between border-b border-slate-200 px-5">
          <div>
            <h2 class="text-base font-bold text-slate-950">Chi tiết video</h2>
            <p class="mt-0.5 text-xs text-slate-500">{{ selected?.original_filename || 'Metadata + cấu hình video' }}</p>
          </div>
          <button class="grid h-9 w-9 place-items-center rounded-md text-slate-500 hover:bg-slate-100" @click="detailOpen = false">
            <X class="h-4 w-4" />
          </button>
        </div>
        <div class="min-h-0 flex-1 space-y-4 overflow-auto p-5 text-sm">
          <section class="rounded-xl border border-slate-200 p-4">
            <div class="mb-3 inline-flex items-center gap-2 text-xs font-bold uppercase text-blue-700"><Pencil class="h-4 w-4" />Thông tin chính</div>
            <label class="block text-xs font-semibold text-slate-600">Tên video</label>
            <input v-model="form.title" class="mt-1 h-10 w-full rounded-md border border-slate-300 px-3 text-sm" />
            <label class="mt-3 block text-xs font-semibold text-slate-600">Mô tả</label>
            <textarea v-model="form.description" class="mt-1 min-h-20 w-full rounded-md border border-slate-300 px-3 py-2 text-sm"></textarea>
            <div class="mt-3 grid grid-cols-2 gap-3">
              <label class="block text-xs font-semibold text-slate-600">
                Duration giây
                <input v-model.number="form.duration_seconds" type="number" min="0" class="mt-1 h-10 w-full rounded-md border border-slate-300 px-3 text-sm" />
              </label>
              <label class="block text-xs font-semibold text-slate-600">
                Visibility
                <select v-model="form.visibility" class="mt-1 h-10 w-full rounded-md border border-slate-300 px-3 text-sm">
                  <option v-for="(label, value) in visibilityLabels" :key="value" :value="value">{{ label }}</option>
                </select>
              </label>
            </div>
            <label class="mt-3 block text-xs font-semibold text-slate-600">Trạng thái xử lý</label>
            <select v-model="form.processing_status" class="mt-1 h-10 w-full rounded-md border border-slate-300 px-3 text-sm">
              <option v-for="(label, value) in processingStatusLabels" :key="value" :value="value">{{ label }}</option>
            </select>
            <label class="mt-3 block text-xs font-semibold text-slate-600">Khóa học đang dùng</label>
            <div class="mt-1 rounded-md bg-slate-50 px-3 py-2 text-sm text-slate-700">{{ resolveUsageText(form, 'course') }}</div>
            <label class="mt-3 block text-xs font-semibold text-slate-600">Bài học đang dùng</label>
            <div class="mt-1 rounded-md bg-slate-50 px-3 py-2 text-sm text-slate-700">{{ resolveUsageText(form, 'component') }}</div>
            <label class="mt-3 block text-xs font-semibold text-slate-600">Ngân hàng đề</label>
            <div class="mt-1 rounded-md bg-slate-50 px-3 py-2 text-sm text-slate-700">{{ questionBankText(form) }}</div>
          </section>

          <section class="rounded-xl border border-slate-200 p-4">
            <div class="text-xs font-bold uppercase text-slate-700">Chuẩn phát video</div>
            <div class="mt-3 grid grid-cols-2 gap-3">
              <label class="block text-xs font-semibold text-slate-600">
                Hoàn thành tối thiểu %
                <input v-model.number="form.settings.min_watch_percent" type="number" min="0" max="100" class="mt-1 h-10 w-full rounded-md border border-slate-300 px-3 text-sm" />
              </label>
              <label class="block text-xs font-semibold text-slate-600">
                Playback rate tối đa
                <input v-model.number="form.settings.max_playback_rate_for_completion" type="number" min="0.25" step="0.25" class="mt-1 h-10 w-full rounded-md border border-slate-300 px-3 text-sm" />
              </label>
            </div>
            <label class="mt-3 flex items-center gap-2 rounded-md bg-slate-50 px-3 py-2 text-sm font-semibold text-slate-700">
              <input v-model="form.settings.require_heartbeat" type="checkbox" class="h-4 w-4" />
              Bắt heartbeat để xác thực tiến độ
            </label>
            <label class="mt-2 flex items-center gap-2 rounded-md bg-slate-50 px-3 py-2 text-sm font-semibold text-slate-700">
              <input v-model="form.settings.allow_download" type="checkbox" class="h-4 w-4" />
              Cho phép tải xuống
            </label>
          </section>

          <section class="rounded-xl border border-slate-200 p-4">
            <div class="text-xs font-bold uppercase text-slate-700">Xem trước phát</div>
            <input v-model="form.thumbnail_url" class="mt-3 h-10 w-full rounded-md border border-slate-300 px-3 text-sm" placeholder="URL ảnh đại diện" />
            <input v-model="form.subtitle_path" class="mt-2 h-10 w-full rounded-md border border-slate-300 px-3 text-sm" placeholder="Subtitle path / VTT" />
            <input v-model="form.transcript_path" class="mt-2 h-10 w-full rounded-md border border-slate-300 px-3 text-sm" placeholder="Transcript path" />
            <div v-if="playback?.url" class="mt-3">
              <video class="aspect-video w-full bg-black" :src="playback.url" :poster="form.thumbnail_url" controls playsinline preload="metadata"></video>
            </div>
          </section>
        </div>
        <div class="flex items-center justify-between border-t border-slate-200 bg-white px-5 py-4">
          <button
            class="inline-flex h-10 items-center gap-2 rounded-md border border-rose-200 px-3 text-sm font-semibold text-rose-600 hover:bg-rose-50"
            :disabled="deleting"
            @click="deleteVideo(form)"
          >
            <AlertTriangle class="h-4 w-4" />
            Xóa
          </button>
          <div class="flex gap-2">
            <button
              class="inline-flex h-10 items-center gap-2 rounded-md border border-blue-200 px-3 text-sm font-semibold text-blue-700"
              :disabled="runningProcess"
              @click="processVideo(form)"
            >
              <SkipForward class="h-4 w-4" />
              {{ runningProcess ? 'Đang chạy...' : 'Run process' }}
            </button>
            <button class="inline-flex h-10 items-center gap-2 rounded-md bg-blue-600 px-3 text-sm font-semibold text-white hover:bg-blue-700 disabled:opacity-60" :disabled="saving || playbackLoading" @click="loadPlayback(form)">
              <Eye class="h-4 w-4" />
              {{ playbackLoading ? 'Đang tải...' : (playback ? 'Làm mới link' : 'Xem trước') }}
            </button>
            <button class="inline-flex h-10 items-center gap-2 rounded-md bg-blue-600 px-4 text-sm font-semibold text-white hover:bg-blue-700 disabled:opacity-60" :disabled="saving" @click="saveVideo">
              <CheckCircle2 class="h-4 w-4" />
              {{ saving ? 'Đang lưu...' : 'Lưu thay đổi' }}
            </button>
          </div>
        </div>
      </aside>
    </div>

    <div v-if="uploadOpen" class="fixed inset-0 z-50">
      <button class="absolute inset-0 bg-slate-950/25" @click="uploadOpen = false"></button>
      <div class="absolute left-1/2 top-20 w-[520px] max-w-[calc(100vw-2rem)] -translate-x-1/2 rounded-xl border border-slate-200 bg-white shadow-2xl">
        <div class="flex h-16 items-center justify-between border-b border-slate-200 px-5">
          <div>
            <h2 class="text-base font-bold text-slate-950">Thêm video mới</h2>
            <p class="mt-0.5 text-xs text-slate-500">Tải file MP4/MOV/AVI/MKV vào thư viện video.</p>
          </div>
          <button class="grid h-9 w-9 place-items-center rounded-md text-slate-500 hover:bg-slate-100" @click="uploadOpen = false">
            <X class="h-4 w-4" />
          </button>
        </div>
        <div class="space-y-4 p-5 text-sm">
          <label class="block text-xs font-semibold text-slate-600">
            Tên video
            <input v-model="uploadForm.title" class="mt-1 h-10 w-full rounded-md border border-slate-300 px-3 text-sm" placeholder="Để trống sẽ lấy tên file" />
          </label>
          <label class="block text-xs font-semibold text-slate-600">
            Phạm vi
            <select v-model="uploadForm.visibility" class="mt-1 h-10 w-full rounded-md border border-slate-300 px-3 text-sm">
              <option v-for="(label, value) in visibilityLabels" :key="value" :value="value">{{ label }}</option>
            </select>
          </label>
          <label class="flex min-h-28 cursor-pointer flex-col items-center justify-center rounded-md border border-dashed border-blue-300 bg-blue-50 px-4 py-5 text-center text-sm font-semibold text-blue-700">
            <Upload class="mb-2 h-6 w-6" />
            {{ uploadForm.file?.name || 'Chọn file MP4/MOV/AVI/MKV' }}
            <input class="hidden" type="file" accept="video/mp4,video/quicktime,video/x-msvideo,video/x-matroska" @change="uploadForm.file = $event.target.files?.[0] || null" />
          </label>
        </div>
        <div class="flex justify-end gap-2 border-t border-slate-200 px-5 py-4">
          <button class="h-10 rounded-md border border-slate-300 px-4 text-sm font-semibold text-slate-700 hover:bg-slate-50" @click="uploadOpen = false">Hủy</button>
          <button class="h-10 rounded-md bg-blue-600 px-4 text-sm font-semibold text-white hover:bg-blue-700 disabled:opacity-60" :disabled="saving" @click="uploadVideo">
            {{ saving ? 'Đang tải lên...' : 'Tải lên' }}
          </button>
        </div>
      </div>
    </div>
  </EraLmsLayout>
</template>
