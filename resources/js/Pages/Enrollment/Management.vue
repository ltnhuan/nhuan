<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import EraLmsLayout from '@/Layouts/EraLmsLayout.vue'

const props = defineProps({
  sessionUser: { type: Object, default: null },
  apiHeaders: { type: Object, default: () => ({}) },
})

defineEmits(['logout'])

const statuses = ['pending', 'active', 'suspended', 'completed', 'withdrawn', 'expired']
const sources = ['manual', 'bulk', 'sis', 'self', 'invite', 'api']

const loading = ref(false)
const actionLoading = ref(false)
const error = ref('')
const selected = ref(new Set())
const records = ref([])
const analytics = ref(null)
const pagination = ref({ total: 0, current_page: 1, last_page: 1, per_page: 100 })
const detail = ref(null)
const editing = ref(null)
const editError = ref('')
const filters = reactive({ q: '', status: '', source: '' })
const editForm = reactive({
  source: 'manual',
  status: 'pending',
  sis_enrollment_id: '',
  completion_percent: 0,
  risk_score: 0,
  expires_at: '',
  metadata_note: '',
})

const selectedCount = computed(() => selected.value.size)
const totalCount = computed(() => pagination.value.total || records.value.length)
const activeCount = computed(() => analytics.value?.by_status?.active || records.value.filter((item) => item.status === 'active').length)
const completedRate = computed(() => {
  const completed = analytics.value?.by_status?.completed || records.value.filter((item) => item.status === 'completed').length
  const total = totalCount.value || records.value.length
  return total ? Math.round((completed / total) * 1000) / 10 : 0
})
const highRiskCount = computed(() => records.value.filter((item) => Number(item.risk_score || 0) >= 70).length)

onMounted(loadPage)

async function loadPage(page = 1) {
  loading.value = true
  error.value = ''

  try {
    const params = new URLSearchParams({ per_page: '100', page: String(page) })
    if (filters.q) params.set('q', filters.q)
    if (filters.status) params.set('status', filters.status)
    if (filters.source) params.set('source', filters.source)

    const [recordResponse, analyticsResponse] = await Promise.all([
      fetchJson(`/api/v1/enrollment/records?${params.toString()}`),
      fetchJson('/api/v1/enrollment/analytics').catch(() => null),
    ])

    records.value = dataList(recordResponse)
    pagination.value = {
      total: recordResponse.total || records.value.length,
      current_page: recordResponse.current_page || page,
      last_page: recordResponse.last_page || 1,
      per_page: recordResponse.per_page || 100,
    }
    analytics.value = analyticsResponse?.data ?? analyticsResponse
    selected.value = new Set()
  } catch (err) {
    error.value = err.message || 'Không tải được danh sách ghi danh.'
  } finally {
    loading.value = false
  }
}

async function fetchJson(url, options = {}) {
  const response = await fetch(url, {
    ...options,
    headers: {
      Accept: 'application/json',
      ...props.apiHeaders,
      ...(options.headers || {}),
    },
  })
  const data = await response.json().catch(() => ({}))
  if (!response.ok) throw new Error(data.message || `HTTP ${response.status}`)
  return data
}

function dataList(payload) {
  if (Array.isArray(payload)) return payload
  if (Array.isArray(payload?.data)) return payload.data
  return []
}

function toggle(id) {
  const next = new Set(selected.value)
  next.has(id) ? next.delete(id) : next.add(id)
  selected.value = next
}

function openDetail(record) {
  detail.value = record
}

function closeDetail() {
  detail.value = null
}

function openEdit(record) {
  editing.value = record
  editError.value = ''
  editForm.source = record.source || 'manual'
  editForm.status = record.status || 'pending'
  editForm.sis_enrollment_id = record.sis_enrollment_id || ''
  editForm.completion_percent = Math.round(Number(record.completion_percent || 0))
  editForm.risk_score = Math.round(Number(record.risk_score || 0))
  editForm.expires_at = toDateTimeInput(record.expires_at)
  editForm.metadata_note = record.metadata?.note || ''
}

function closeEdit() {
  editing.value = null
  editError.value = ''
}

async function saveEdit() {
  if (!editing.value?.id || actionLoading.value) return
  const validation = validateEdit()
  if (validation) {
    editError.value = validation
    return
  }

  actionLoading.value = true
  editError.value = ''
  error.value = ''

  try {
    const updated = await fetchJson(`/api/v1/enrollment/records/${editing.value.id}`, {
      method: 'PATCH',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        source: editForm.source,
        status: editForm.status,
        sis_enrollment_id: editForm.sis_enrollment_id || null,
        completion_percent: Number(editForm.completion_percent),
        risk_score: Number(editForm.risk_score),
        expires_at: editForm.expires_at || null,
        metadata_note: editForm.metadata_note || null,
      }),
    })
    replaceRecord(updated)
    detail.value = updated
    editing.value = null
  } catch (err) {
    editError.value = err.message || 'Không lưu được thông tin ghi danh.'
  } finally {
    actionLoading.value = false
  }
}

function validateEdit() {
  if (!sources.includes(editForm.source)) return 'Nguồn ghi danh không hợp lệ.'
  if (!statuses.includes(editForm.status)) return 'Trạng thái ghi danh không hợp lệ.'
  const completion = Number(editForm.completion_percent)
  const risk = Number(editForm.risk_score)
  if (!Number.isFinite(completion) || completion < 0 || completion > 100) return 'Hoàn thành phải nằm trong khoảng 0-100%.'
  if (!Number.isFinite(risk) || risk < 0 || risk > 100) return 'Risk score phải nằm trong khoảng 0-100.'
  if (editForm.metadata_note.length > 1000) return 'Ghi chú không vượt quá 1000 ký tự.'
  return ''
}

async function transition(record, status) {
  if (!record?.id || actionLoading.value) return
  actionLoading.value = true
  error.value = ''

  try {
    const updated = await fetchJson(`/api/v1/enrollment/records/${record.id}/transition`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ status, metadata: { source: 'enrollment-ui' } }),
    })
    replaceRecord(updated)
    detail.value = updated
  } catch (err) {
    error.value = err.message || 'Không cập nhật được trạng thái ghi danh.'
  } finally {
    actionLoading.value = false
  }
}

async function bulkAction(action) {
  const ids = Array.from(selected.value)
  if (!ids.length || actionLoading.value) return
  actionLoading.value = true
  error.value = ''

  try {
    await fetchJson('/api/v1/enrollment/records/bulk-action', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ ids, action }),
    })
    await loadPage(pagination.value.current_page)
  } catch (err) {
    error.value = err.message || 'Không chạy được thao tác hàng loạt.'
  } finally {
    actionLoading.value = false
  }
}

function replaceRecord(updated) {
  records.value = records.value.map((item) => (item.id === updated.id ? { ...item, ...updated } : item))
}

function formatDate(value) {
  if (!value) return '-'
  return new Intl.DateTimeFormat('vi-VN', { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(value))
}

function toDateTimeInput(value) {
  if (!value) return ''
  const date = new Date(value)
  if (Number.isNaN(date.getTime())) return ''
  return date.toISOString().slice(0, 16)
}

function percent(value) {
  return `${Math.round(Number(value || 0))}%`
}

function statusClass(status) {
  return {
    active: 'border-emerald-200 bg-emerald-50 text-emerald-800',
    completed: 'border-blue-200 bg-blue-50 text-blue-800',
    pending: 'border-amber-200 bg-amber-50 text-amber-800',
    suspended: 'border-orange-200 bg-orange-50 text-orange-800',
    withdrawn: 'border-rose-200 bg-rose-50 text-rose-800',
    expired: 'border-slate-200 bg-slate-100 text-slate-700',
  }[status] || 'border-slate-200 bg-white text-slate-700'
}
</script>

<template>
  <EraLmsLayout :session-user="sessionUser" @logout="$emit('logout')">
    <template #breadcrumb>Quản lý ghi danh / Vòng đời ghi danh</template>

    <section class="mx-auto max-w-7xl space-y-4 px-6 py-5">
      <div class="grid gap-3 md:grid-cols-4">
        <div class="border bg-white p-4">
          <div class="text-xs text-slate-500">Tổng ghi danh</div>
          <div class="mt-1 text-2xl font-semibold">{{ totalCount.toLocaleString('vi-VN') }}</div>
        </div>
        <div class="border bg-white p-4">
          <div class="text-xs text-slate-500">Đang học</div>
          <div class="mt-1 text-2xl font-semibold">{{ activeCount.toLocaleString('vi-VN') }}</div>
        </div>
        <div class="border bg-white p-4">
          <div class="text-xs text-slate-500">Tỷ lệ hoàn thành</div>
          <div class="mt-1 text-2xl font-semibold">{{ completedRate }}%</div>
        </div>
        <div class="border bg-white p-4">
          <div class="text-xs text-slate-500">Rủi ro cao trong trang</div>
          <div class="mt-1 text-2xl font-semibold">{{ highRiskCount.toLocaleString('vi-VN') }}</div>
        </div>
      </div>

      <section class="border bg-white">
        <div class="flex flex-wrap items-center gap-2 border-b p-3">
          <h1 class="text-sm font-semibold">Bản ghi ghi danh</h1>
          <input v-model="filters.q" class="ml-auto h-9 w-72 rounded-md border px-3 text-sm" placeholder="Tìm học viên, email, mã học viên" @keyup.enter="loadPage(1)" />
          <select v-model="filters.status" class="h-9 rounded-md border px-2 text-sm" @change="loadPage(1)">
            <option value="">Trạng thái vòng đời</option>
            <option v-for="status in statuses" :key="status" :value="status">{{ status }}</option>
          </select>
          <select v-model="filters.source" class="h-9 rounded-md border px-2 text-sm" @change="loadPage(1)">
            <option value="">Nguồn ghi danh</option>
            <option v-for="source in sources" :key="source" :value="source">{{ source }}</option>
          </select>
          <button class="h-9 rounded-md border px-3 text-sm" :disabled="loading" @click="loadPage(1)">Tải lại</button>
        </div>

        <div class="flex flex-wrap items-center gap-2 border-b bg-slate-50 p-3 text-xs">
          <span class="font-medium">{{ selectedCount }} đã chọn</span>
          <button class="rounded-md border bg-white px-3 py-1.5 disabled:opacity-50" :disabled="!selectedCount || actionLoading" @click="bulkAction('activate')">Kích hoạt</button>
          <button class="rounded-md border bg-white px-3 py-1.5 disabled:opacity-50" :disabled="!selectedCount || actionLoading" @click="bulkAction('suspend')">Tạm dừng</button>
          <button class="rounded-md border bg-white px-3 py-1.5 disabled:opacity-50" :disabled="!selectedCount || actionLoading" @click="bulkAction('complete')">Hoàn thành</button>
          <button class="rounded-md border bg-white px-3 py-1.5 disabled:opacity-50" :disabled="!selectedCount || actionLoading" @click="bulkAction('withdraw')">Rút khỏi lớp</button>
          <span class="ml-auto text-slate-500">Bấm đúp vào một dòng để xem chi tiết</span>
        </div>

        <div v-if="error" class="border-b border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-700">{{ error }}</div>

        <div class="grid grid-cols-[40px_130px_1.4fr_1.5fr_120px_120px_110px_90px_130px] border-b bg-slate-100 px-3 py-2 text-xs font-semibold uppercase text-slate-500">
          <div></div><div>Mã</div><div>Học viên</div><div>Lớp học phần</div><div>Nguồn</div><div>Trạng thái</div><div>Hoàn thành</div><div>Rủi ro</div><div>Thao tác</div>
        </div>

        <div class="max-h-[560px] overflow-auto">
          <div v-if="loading" class="px-3 py-10 text-center text-sm text-slate-500">Đang tải dữ liệu ghi danh...</div>
          <div
            v-for="row in records"
            v-else
            :key="row.id"
            class="grid min-h-12 cursor-pointer grid-cols-[40px_130px_1.4fr_1.5fr_120px_120px_110px_90px_130px] items-center border-b px-3 text-sm hover:bg-blue-50"
            @dblclick.stop="openDetail(row)"
          >
            <input type="checkbox" :checked="selected.has(row.id)" @click.stop @change="toggle(row.id)" />
            <div class="font-mono text-xs">{{ row.code || `ENR-${String(row.id).padStart(6, '0')}` }}</div>
            <div>
              <div class="font-medium text-slate-900">{{ row.learner?.full_name || 'Học viên' }}</div>
              <div class="text-xs text-slate-500">{{ row.learner?.email || row.learner?.code || '-' }}</div>
            </div>
            <div>
              <div>{{ row.class_section?.name || row.classSection?.name || row.course?.title || '-' }}</div>
              <div class="text-xs text-slate-500">{{ row.class_section?.code || row.classSection?.code || row.course?.code || '-' }}</div>
            </div>
            <div>{{ row.source || '-' }}</div>
            <div><span class="rounded-md border px-2 py-1 text-xs" :class="statusClass(row.status)">{{ row.status || '-' }}</span></div>
            <div>{{ percent(row.completion_percent) }}</div>
            <div>{{ Math.round(Number(row.risk_score || 0)) }}</div>
            <div class="flex gap-1">
              <button class="rounded-md border px-2 py-1 text-xs" @click.stop="openDetail(row)">Xem</button>
              <button class="rounded-md border px-2 py-1 text-xs" @click.stop="openEdit(row)">Sửa</button>
            </div>
          </div>
          <div v-if="!loading && !records.length" class="px-3 py-10 text-center text-sm text-slate-500">Không có bản ghi phù hợp.</div>
        </div>

        <div class="flex items-center justify-between gap-3 border-t bg-slate-50 px-3 py-3 text-sm">
          <div>Trang {{ pagination.current_page }} / {{ pagination.last_page }} · {{ totalCount.toLocaleString('vi-VN') }} bản ghi</div>
          <div class="flex gap-2">
            <button class="rounded-md border bg-white px-3 py-1.5 disabled:opacity-50" :disabled="pagination.current_page <= 1 || loading" @click="loadPage(pagination.current_page - 1)">Trước</button>
            <button class="rounded-md border bg-white px-3 py-1.5 disabled:opacity-50" :disabled="pagination.current_page >= pagination.last_page || loading" @click="loadPage(pagination.current_page + 1)">Sau</button>
          </div>
        </div>
      </section>

      <div class="grid gap-4 lg:grid-cols-3">
        <section class="border bg-white p-4">
          <h2 class="text-sm font-semibold">Class Section</h2>
          <div class="mt-3 grid gap-2 text-sm">
            <a class="rounded-md border px-3 py-2 text-left hover:bg-slate-50" href="/enrollment/sections">Lớp học phần</a>
            <a class="rounded-md border px-3 py-2 text-left hover:bg-slate-50" href="/enrollment/sections">Nhóm thực hành</a>
            <a class="rounded-md border px-3 py-2 text-left hover:bg-slate-50" href="/enrollment/sections">Nhóm dự án</a>
            <a class="rounded-md border px-3 py-2 text-left hover:bg-slate-50" href="/enrollment/sections">Nhóm thảo luận</a>
          </div>
        </section>
        <section class="border bg-white p-4">
          <h2 class="text-sm font-semibold">Phân công giảng viên</h2>
          <div class="mt-3 grid gap-2 text-sm">
            <button class="rounded-md border px-3 py-2 text-left">Giáo viên chính</button>
            <button class="rounded-md border px-3 py-2 text-left">Giáo viên hỗ trợ</button>
            <button class="rounded-md border px-3 py-2 text-left">Cố vấn</button>
            <button class="rounded-md border px-3 py-2 text-left">Đánh giá viên</button>
          </div>
        </section>
        <section class="border bg-white p-4">
          <h2 class="text-sm font-semibold">Hàng đợi nhập liệu</h2>
          <div class="mt-3 space-y-2 text-sm">
            <div class="flex justify-between border px-3 py-2"><span>Nhập hàng loạt CSV</span><span>đang xử lý</span></div>
            <div class="flex justify-between border px-3 py-2"><span>Đồng bộ SIS</span><span>đang chờ</span></div>
            <div class="flex justify-between border px-3 py-2"><span>Nhập qua API</span><span>hoàn tất</span></div>
          </div>
        </section>
      </div>
    </section>

    <div v-if="detail" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/50 p-4" @click.self="closeDetail">
      <section class="max-h-[90vh] w-full max-w-4xl overflow-auto rounded-lg bg-white shadow-xl">
        <div class="flex items-start justify-between gap-3 border-b px-5 py-4">
          <div>
            <div class="text-xs font-semibold uppercase text-slate-500">Chi tiết ghi danh</div>
            <h2 class="mt-1 text-lg font-bold text-slate-950">{{ detail.learner?.full_name || 'Học viên' }}</h2>
            <p class="mt-1 text-sm text-slate-500">{{ detail.code || `ENR-${String(detail.id).padStart(6, '0')}` }} · {{ detail.learner?.email || detail.learner?.code || '-' }}</p>
          </div>
          <div class="flex gap-2">
            <button class="rounded-md border px-3 py-1.5 text-sm" @click="openEdit(detail)">Sửa</button>
            <button class="rounded-md border px-3 py-1.5 text-sm" @click="closeDetail">Đóng</button>
          </div>
        </div>

        <div class="grid gap-4 p-5 lg:grid-cols-[1.2fr_.8fr]">
          <div class="space-y-4">
            <section class="rounded-lg border p-4">
              <h3 class="text-sm font-bold text-slate-950">Thông tin học tập</h3>
              <div class="mt-3 grid gap-3 text-sm md:grid-cols-2">
                <div><span class="text-slate-500">Khóa học</span><div class="font-semibold">{{ detail.course?.title || '-' }}</div></div>
                <div><span class="text-slate-500">Mã khóa</span><div class="font-semibold">{{ detail.course?.code || '-' }}</div></div>
                <div><span class="text-slate-500">Lớp học phần</span><div class="font-semibold">{{ detail.class_section?.name || detail.classSection?.name || '-' }}</div></div>
                <div><span class="text-slate-500">Mã lớp</span><div class="font-semibold">{{ detail.class_section?.code || detail.classSection?.code || '-' }}</div></div>
                <div><span class="text-slate-500">Nguồn ghi danh</span><div class="font-semibold">{{ detail.source || '-' }}</div></div>
                <div><span class="text-slate-500">SIS ID</span><div class="font-semibold">{{ detail.sis_enrollment_id || '-' }}</div></div>
              </div>
            </section>

            <section class="rounded-lg border p-4">
              <h3 class="text-sm font-bold text-slate-950">Mốc thời gian</h3>
              <div class="mt-3 grid gap-3 text-sm md:grid-cols-2">
                <div><span class="text-slate-500">Mời</span><div>{{ formatDate(detail.invited_at) }}</div></div>
                <div><span class="text-slate-500">Chấp nhận</span><div>{{ formatDate(detail.accepted_at) }}</div></div>
                <div><span class="text-slate-500">Ghi danh</span><div>{{ formatDate(detail.enrolled_at) }}</div></div>
                <div><span class="text-slate-500">Kích hoạt</span><div>{{ formatDate(detail.activated_at) }}</div></div>
                <div><span class="text-slate-500">Hoàn thành</span><div>{{ formatDate(detail.completed_at) }}</div></div>
                <div><span class="text-slate-500">Hết hạn</span><div>{{ formatDate(detail.expires_at) }}</div></div>
              </div>
            </section>
          </div>

          <aside class="space-y-4">
            <section class="rounded-lg border p-4">
              <h3 class="text-sm font-bold text-slate-950">Trạng thái</h3>
              <div class="mt-3">
                <span class="rounded-md border px-2 py-1 text-xs font-semibold" :class="statusClass(detail.status)">{{ detail.status || '-' }}</span>
              </div>
              <div class="mt-4 grid gap-3 text-sm">
                <div class="flex justify-between"><span class="text-slate-500">Hoàn thành</span><span class="font-semibold">{{ percent(detail.completion_percent) }}</span></div>
                <div class="flex justify-between"><span class="text-slate-500">Risk score</span><span class="font-semibold">{{ Math.round(Number(detail.risk_score || 0)) }}</span></div>
              </div>
            </section>

            <section class="rounded-lg border p-4">
              <h3 class="text-sm font-bold text-slate-950">Thao tác</h3>
              <div class="mt-3 grid grid-cols-2 gap-2">
                <button class="rounded-md border px-3 py-2 text-sm disabled:opacity-50" :disabled="actionLoading" @click="transition(detail, 'active')">Activate</button>
                <button class="rounded-md border px-3 py-2 text-sm disabled:opacity-50" :disabled="actionLoading" @click="transition(detail, 'suspended')">Suspend</button>
                <button class="rounded-md border px-3 py-2 text-sm disabled:opacity-50" :disabled="actionLoading" @click="transition(detail, 'completed')">Complete</button>
                <button class="rounded-md border px-3 py-2 text-sm disabled:opacity-50" :disabled="actionLoading" @click="transition(detail, 'withdrawn')">Withdraw</button>
              </div>
            </section>

            <section class="rounded-lg border p-4">
              <h3 class="text-sm font-bold text-slate-950">Metadata</h3>
              <div v-if="detail.metadata && Object.keys(detail.metadata).length" class="mt-3 space-y-2 text-sm">
                <div v-for="(value, key) in detail.metadata" :key="key" class="flex justify-between gap-3 border-b pb-2">
                  <span class="text-slate-500">{{ key }}</span>
                  <span class="text-right font-medium">{{ Array.isArray(value) ? value.join(', ') : value }}</span>
                </div>
              </div>
              <div v-else class="mt-3 text-sm text-slate-500">Không có metadata bổ sung.</div>
            </section>
          </aside>
        </div>
      </section>
    </div>

    <div v-if="editing" class="fixed inset-0 z-[60] flex items-center justify-center bg-slate-950/50 p-4" @click.self="closeEdit">
      <section class="max-h-[90vh] w-full max-w-3xl overflow-auto rounded-lg bg-white shadow-xl">
        <div class="flex items-start justify-between gap-3 border-b px-5 py-4">
          <div>
            <div class="text-xs font-semibold uppercase text-slate-500">Sửa ghi danh</div>
            <h2 class="mt-1 text-lg font-bold text-slate-950">{{ editing.learner?.full_name || 'Học viên' }}</h2>
            <p class="mt-1 text-sm text-slate-500">{{ editing.course?.title || '-' }} · {{ editing.class_section?.name || editing.classSection?.name || '-' }}</p>
          </div>
          <button class="rounded-md border px-3 py-1.5 text-sm" @click="closeEdit">Đóng</button>
        </div>

        <div class="space-y-4 p-5">
          <div v-if="editError" class="rounded-md border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-700">{{ editError }}</div>

          <section class="rounded-lg border p-4">
            <h3 class="text-sm font-bold text-slate-950">Ràng buộc chính</h3>
            <div class="mt-3 grid gap-4 md:grid-cols-2">
              <label class="text-sm">
                <span class="font-medium text-slate-700">Nguồn ghi danh</span>
                <select v-model="editForm.source" class="mt-1 h-10 w-full rounded-md border px-3">
                  <option v-for="source in sources" :key="source" :value="source">{{ source }}</option>
                </select>
              </label>
              <label class="text-sm">
                <span class="font-medium text-slate-700">Trạng thái</span>
                <select v-model="editForm.status" class="mt-1 h-10 w-full rounded-md border px-3">
                  <option v-for="status in statuses" :key="status" :value="status">{{ status }}</option>
                </select>
              </label>
              <label class="text-sm">
                <span class="font-medium text-slate-700">SIS enrollment ID</span>
                <input v-model.trim="editForm.sis_enrollment_id" maxlength="120" class="mt-1 h-10 w-full rounded-md border px-3" placeholder="SIS-ENR-..." />
              </label>
              <label class="text-sm">
                <span class="font-medium text-slate-700">Ngày hết hạn</span>
                <input v-model="editForm.expires_at" type="datetime-local" class="mt-1 h-10 w-full rounded-md border px-3" />
              </label>
              <label class="text-sm">
                <span class="font-medium text-slate-700">Hoàn thành (%)</span>
                <input v-model.number="editForm.completion_percent" type="number" min="0" max="100" step="1" class="mt-1 h-10 w-full rounded-md border px-3" />
              </label>
              <label class="text-sm">
                <span class="font-medium text-slate-700">Risk score</span>
                <input v-model.number="editForm.risk_score" type="number" min="0" max="100" step="1" class="mt-1 h-10 w-full rounded-md border px-3" />
              </label>
            </div>
          </section>

          <section class="rounded-lg border p-4">
            <h3 class="text-sm font-bold text-slate-950">Ghi chú vận hành</h3>
            <textarea v-model.trim="editForm.metadata_note" maxlength="1000" rows="4" class="mt-3 w-full rounded-md border px-3 py-2 text-sm" placeholder="Lý do sửa, ghi chú từ phòng đào tạo, ghi chú đồng bộ SIS..."></textarea>
            <div class="mt-2 text-right text-xs text-slate-500">{{ editForm.metadata_note.length }}/1000</div>
          </section>

          <div class="flex justify-end gap-2 border-t pt-4">
            <button class="rounded-md border px-4 py-2 text-sm" :disabled="actionLoading" @click="closeEdit">Hủy</button>
            <button class="rounded-md bg-slate-950 px-4 py-2 text-sm font-semibold text-white disabled:opacity-50" :disabled="actionLoading" @click="saveEdit">
              {{ actionLoading ? 'Đang lưu...' : 'Lưu thay đổi' }}
            </button>
          </div>
        </div>
      </section>
    </div>
  </EraLmsLayout>
</template>
