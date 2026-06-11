<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import {
  Download,
  Filter,
  GraduationCap,
  LockKeyhole,
  Mail,
  Phone,
  Plus,
  RefreshCw,
  Search,
  ShieldCheck,
  SlidersHorizontal,
  UnlockKeyhole,
  UserCheck,
  Users,
} from '@lucide/vue'
import EraLmsLayout from '@/Layouts/EraLmsLayout.vue'

const props = defineProps({
  apiHeaders: { type: Object, default: () => ({}) },
})

const rows = ref([])
const meta = ref({ current_page: 1, last_page: 1, total: 0, per_page: 100 })
const loading = ref(false)
const error = ref('')
const search = ref('')
const userType = ref('student')
const status = ref('')
const page = ref(1)
const selectedUser = ref(null)
const debounceTimer = ref(null)

const userTypes = [
  { value: 'student', label: 'Học viên', icon: GraduationCap },
  { value: 'teacher', label: 'Giảng viên', icon: UserCheck },
  { value: 'staff', label: 'Nhân sự', icon: ShieldCheck },
  { value: 'admin', label: 'Quản trị', icon: ShieldCheck },
  { value: '', label: 'Tất cả', icon: Users },
]

const statuses = [
  { value: '', label: 'Tất cả trạng thái' },
  { value: 'active', label: 'Đang hoạt động' },
  { value: 'inactive', label: 'Ngưng hoạt động' },
  { value: 'locked', label: 'Đã khóa' },
  { value: 'graduated', label: 'Đã tốt nghiệp' },
  { value: 'reserved', label: 'Bảo lưu' },
]

const statusTone = {
  active: 'border-emerald-200 bg-emerald-50 text-emerald-700',
  inactive: 'border-slate-200 bg-slate-50 text-slate-600',
  locked: 'border-rose-200 bg-rose-50 text-rose-700',
  graduated: 'border-blue-200 bg-blue-50 text-blue-700',
  reserved: 'border-amber-200 bg-amber-50 text-amber-700',
}

const statusLabel = {
  active: 'Đang hoạt động',
  inactive: 'Ngưng hoạt động',
  locked: 'Đã khóa',
  graduated: 'Đã tốt nghiệp',
  reserved: 'Bảo lưu',
}

const typeLabel = {
  student: 'Học viên',
  teacher: 'Giảng viên',
  staff: 'Nhân sự',
  admin: 'Quản trị',
  parent: 'Phụ huynh',
  external: 'Bên ngoài',
}

const activeCount = computed(() => rows.value.filter((user) => user.status === 'active').length)
const lockedCount = computed(() => rows.value.filter((user) => user.status === 'locked').length)
const visibleRange = computed(() => {
  if (!meta.value.total) return '0'
  const start = ((meta.value.current_page - 1) * meta.value.per_page) + 1
  const end = Math.min(meta.value.current_page * meta.value.per_page, meta.value.total)
  return `${number(start)}-${number(end)}`
})

function number(value) {
  return new Intl.NumberFormat('vi-VN').format(Number(value || 0))
}

function initials(name) {
  return String(name || 'U')
    .split(' ')
    .filter(Boolean)
    .slice(-2)
    .map((part) => part[0])
    .join('')
    .toUpperCase()
}

function profileCompleteness(user) {
  const fields = ['code', 'full_name', 'email', 'phone', 'user_type', 'status']
  const filled = fields.filter((field) => Boolean(user?.[field])).length
  return Math.round((filled / fields.length) * 100)
}

async function loadUsers() {
  loading.value = true
  error.value = ''

  const params = new URLSearchParams({
    per_page: '100',
    page: String(page.value),
  })
  if (search.value.trim()) params.set('search', search.value.trim())
  if (userType.value) params.set('user_type', userType.value)
  if (status.value) params.set('status', status.value)

  try {
    const response = await fetch(`/api/v1/core/users?${params}`, {
      headers: props.apiHeaders,
    })
    const payload = await response.json()

    if (!response.ok) throw new Error(payload.message || 'Không tải được danh sách người dùng.')

    rows.value = payload.data || []
    meta.value = {
      current_page: payload.current_page || 1,
      last_page: payload.last_page || 1,
      total: payload.total || rows.value.length,
      per_page: payload.per_page || 100,
    }
    selectedUser.value = rows.value[0] || null
  } catch (err) {
    error.value = err.message || 'Không tải được danh sách người dùng.'
  } finally {
    loading.value = false
  }
}

async function changeLock(user) {
  const action = user.status === 'locked' ? 'unlock' : 'lock'
  const response = await fetch(`/api/v1/core/users/${user.id}/${action}`, {
    method: 'POST',
    headers: props.apiHeaders,
  })
  const payload = await response.json()

  if (!response.ok) {
    error.value = payload.message || 'Không cập nhật được trạng thái tài khoản.'
    return
  }

  rows.value = rows.value.map((item) => item.id === user.id ? payload : item)
  selectedUser.value = payload
}

function setType(value) {
  userType.value = value
  page.value = 1
}

function setPage(nextPage) {
  page.value = Math.min(Math.max(1, nextPage), meta.value.last_page || 1)
}

watch([search, userType, status], () => {
  window.clearTimeout(debounceTimer.value)
  debounceTimer.value = window.setTimeout(() => {
    page.value = 1
    loadUsers()
  }, 250)
})

watch(page, loadUsers)
onMounted(loadUsers)
</script>

<template>
  <EraLmsLayout>
    <template #breadcrumb>Cấu hình / Quản lý người dùng</template>

    <div class="space-y-4">
      <section class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
        <div class="grid gap-5 border-b border-slate-200 bg-slate-950 p-5 text-white lg:grid-cols-[1fr_auto]">
          <div class="min-w-0">
            <div class="text-xs font-semibold uppercase tracking-wide text-cyan-300">Quản lý danh tính và truy cập</div>
            <h1 class="mt-2 text-2xl font-bold tracking-tight">Quản lý người dùng</h1>
            <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-300">
              Danh sách mặc định hiển thị đầy đủ học viên từ API, kèm bộ lọc vận hành, trạng thái tài khoản và hồ sơ liên hệ.
            </p>
          </div>
          <div class="flex flex-wrap items-start gap-2">
            <button class="inline-flex h-10 items-center gap-2 rounded-md border border-white/15 bg-white/10 px-3 text-sm font-semibold text-white hover:bg-white/15">
              <Download class="h-4 w-4" /> Xuất file
            </button>
            <button class="inline-flex h-10 items-center gap-2 rounded-md bg-cyan-400 px-3 text-sm font-semibold text-slate-950 hover:bg-cyan-300">
              <Plus class="h-4 w-4" /> Tạo user
            </button>
          </div>
        </div>

        <div class="grid gap-px bg-slate-200 md:grid-cols-4">
          <article class="bg-white p-4">
            <div class="flex items-center justify-between text-xs font-semibold uppercase text-slate-500">
              Tổng người dùng <Users class="h-4 w-4 text-slate-400" />
            </div>
            <div class="mt-2 text-2xl font-bold text-slate-950">{{ number(meta.total) }}</div>
            <div class="mt-1 text-xs text-slate-500">Theo bộ lọc hiện tại</div>
          </article>
          <article class="bg-white p-4">
            <div class="flex items-center justify-between text-xs font-semibold uppercase text-slate-500">
              Đang hoạt động <UserCheck class="h-4 w-4 text-emerald-500" />
            </div>
            <div class="mt-2 text-2xl font-bold text-slate-950">{{ number(activeCount) }}</div>
            <div class="mt-1 text-xs text-slate-500">Trong trang đang xem</div>
          </article>
          <article class="bg-white p-4">
            <div class="flex items-center justify-between text-xs font-semibold uppercase text-slate-500">
              Đã khóa <LockKeyhole class="h-4 w-4 text-rose-500" />
            </div>
            <div class="mt-2 text-2xl font-bold text-slate-950">{{ number(lockedCount) }}</div>
            <div class="mt-1 text-xs text-slate-500">Cần rà soát truy cập</div>
          </article>
          <article class="bg-white p-4">
            <div class="flex items-center justify-between text-xs font-semibold uppercase text-slate-500">
              Đang hiển thị <SlidersHorizontal class="h-4 w-4 text-cyan-600" />
            </div>
            <div class="mt-2 text-2xl font-bold text-slate-950">{{ visibleRange }}</div>
            <div class="mt-1 text-xs text-slate-500">Trang {{ meta.current_page }} / {{ meta.last_page }}</div>
          </article>
        </div>
      </section>

      <section class="grid gap-4 xl:grid-cols-[1fr_360px]">
        <div class="min-w-0 rounded-lg border border-slate-200 bg-white shadow-sm">
          <div class="border-b border-slate-200 p-4">
            <div class="flex flex-col gap-3 xl:flex-row xl:items-center">
              <div class="relative min-w-0 flex-1">
                <Search class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                <input
                  v-model="search"
                  class="h-11 w-full rounded-md border border-slate-300 bg-slate-50 pl-9 pr-3 text-sm outline-none focus:border-cyan-500 focus:bg-white focus:ring-4 focus:ring-cyan-100"
                  placeholder="Tìm theo mã, họ tên hoặc email"
                />
              </div>
              <div class="flex flex-wrap gap-2">
                <button
                  v-for="type in userTypes"
                  :key="type.value"
                  class="inline-flex h-10 items-center gap-2 rounded-md border px-3 text-sm font-semibold transition"
                  :class="userType === type.value ? 'border-cyan-300 bg-cyan-50 text-cyan-800' : 'border-slate-300 bg-white text-slate-700 hover:bg-slate-50'"
                  @click="setType(type.value)"
                >
                  <component :is="type.icon" class="h-4 w-4" /> {{ type.label }}
                </button>
              </div>
              <select v-model="status" class="h-10 rounded-md border border-slate-300 bg-white px-3 text-sm text-slate-700 outline-none focus:border-cyan-500 focus:ring-4 focus:ring-cyan-100">
                <option v-for="item in statuses" :key="item.value" :value="item.value">{{ item.label }}</option>
              </select>
              <button class="inline-flex h-10 items-center justify-center gap-2 rounded-md border border-slate-300 bg-white px-3 text-sm font-semibold text-slate-700 hover:bg-slate-50" @click="loadUsers">
                <RefreshCw class="h-4 w-4" :class="{ 'animate-spin': loading }" /> Tải lại
              </button>
            </div>
          </div>

          <div v-if="error" class="m-4 rounded-md border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-700">{{ error }}</div>

          <div class="overflow-x-auto" data-disable-row-popup="true">
            <table class="w-full min-w-[980px] text-left text-sm">
              <thead class="border-b border-slate-200 bg-slate-50 text-xs uppercase text-slate-500">
                <tr>
                  <th class="px-4 py-3">Người dùng</th>
                  <th class="px-4 py-3">Liên hệ</th>
                  <th class="px-4 py-3">Loại</th>
                  <th class="px-4 py-3">Trạng thái</th>
                  <th class="px-4 py-3">Hồ sơ</th>
                  <th class="px-4 py-3 text-right">Thao tác</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-100">
                <tr v-if="loading" v-for="index in 8" :key="index">
                  <td colspan="6" class="px-4 py-4"><div class="h-8 animate-pulse rounded bg-slate-100"></div></td>
                </tr>
                <tr v-for="user in rows" v-else :key="user.id" class="hover:bg-slate-50/80" :class="{ 'bg-cyan-50/40': selectedUser?.id === user.id }">
                  <td class="px-4 py-3">
                    <button class="flex min-w-0 items-center gap-3 text-left" @click="selectedUser = user">
                      <span class="grid h-10 w-10 shrink-0 place-items-center rounded-md bg-slate-950 text-xs font-bold text-white">{{ initials(user.full_name) }}</span>
                      <span class="min-w-0">
                        <span class="block truncate font-semibold text-slate-950">{{ user.full_name }}</span>
                        <span class="mt-0.5 block font-mono text-xs text-slate-500">{{ user.code || '-' }}</span>
                      </span>
                    </button>
                  </td>
                  <td class="px-4 py-3">
                    <div class="flex items-center gap-2 text-slate-700"><Mail class="h-4 w-4 text-slate-400" /> {{ user.email || '-' }}</div>
                    <div class="mt-1 flex items-center gap-2 text-xs text-slate-500"><Phone class="h-3.5 w-3.5 text-slate-400" /> {{ user.phone || 'Chưa có số điện thoại' }}</div>
                  </td>
                  <td class="px-4 py-3 text-slate-700">{{ typeLabel[user.user_type] || user.user_type || '-' }}</td>
                  <td class="px-4 py-3">
                    <span class="inline-flex rounded-full border px-2.5 py-1 text-xs font-semibold" :class="statusTone[user.status] || statusTone.inactive">
                      {{ statusLabel[user.status] || user.status || '-' }}
                    </span>
                  </td>
                  <td class="px-4 py-3">
                    <div class="h-2 w-28 overflow-hidden rounded-full bg-slate-100">
                      <div class="h-full rounded-full bg-cyan-500" :style="{ width: `${profileCompleteness(user)}%` }"></div>
                    </div>
                    <div class="mt-1 text-xs text-slate-500">{{ profileCompleteness(user) }}% hoàn thiện</div>
                  </td>
                  <td class="px-4 py-3">
                    <div class="flex justify-end gap-2">
                      <button class="rounded-md border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-white" @click="selectedUser = user">
                        Chi tiết
                      </button>
                      <button class="inline-flex items-center gap-1 rounded-md border px-3 py-2 text-xs font-semibold" :class="user.status === 'locked' ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'border-rose-200 bg-rose-50 text-rose-700'" @click="changeLock(user)">
                        <UnlockKeyhole v-if="user.status === 'locked'" class="h-3.5 w-3.5" />
                        <LockKeyhole v-else class="h-3.5 w-3.5" />
                        {{ user.status === 'locked' ? 'Mở khóa' : 'Khóa' }}
                      </button>
                    </div>
                  </td>
                </tr>
                <tr v-if="!loading && !rows.length">
                  <td colspan="6" class="px-4 py-12 text-center text-sm text-slate-500">
                    Không có người dùng phù hợp bộ lọc hiện tại.
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <footer class="flex flex-col gap-3 border-t border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-600 sm:flex-row sm:items-center sm:justify-between">
            <div><Filter class="mr-1 inline h-4 w-4" /> Hiển thị {{ visibleRange }} trong {{ number(meta.total) }} bản ghi</div>
            <div class="flex gap-2">
              <button class="rounded-md border border-slate-300 bg-white px-3 py-2 font-semibold disabled:opacity-40" :disabled="meta.current_page <= 1" @click="setPage(meta.current_page - 1)">Trước</button>
              <button class="rounded-md border border-slate-300 bg-white px-3 py-2 font-semibold disabled:opacity-40" :disabled="meta.current_page >= meta.last_page" @click="setPage(meta.current_page + 1)">Sau</button>
            </div>
          </footer>
        </div>

        <aside class="rounded-lg border border-slate-200 bg-white shadow-sm">
          <div class="border-b border-slate-200 p-4">
            <div class="text-xs font-semibold uppercase text-slate-500">Hồ sơ được chọn</div>
            <h2 class="mt-1 truncate text-lg font-bold text-slate-950">{{ selectedUser?.full_name || 'Chưa chọn người dùng' }}</h2>
          </div>
          <div v-if="selectedUser" class="space-y-4 p-4">
            <div class="flex items-center gap-3">
              <div class="grid h-14 w-14 place-items-center rounded-md bg-slate-950 text-sm font-bold text-white">{{ initials(selectedUser.full_name) }}</div>
              <div class="min-w-0">
                <div class="truncate font-semibold text-slate-950">{{ selectedUser.code }}</div>
                <div class="truncate text-sm text-slate-500">{{ selectedUser.email }}</div>
              </div>
            </div>
            <dl class="grid gap-3 text-sm">
              <div class="rounded-md border border-slate-200 bg-slate-50 p-3">
                <dt class="text-xs font-semibold uppercase text-slate-500">Loại tài khoản</dt>
                <dd class="mt-1 text-slate-950">{{ typeLabel[selectedUser.user_type] || selectedUser.user_type }}</dd>
              </div>
              <div class="rounded-md border border-slate-200 bg-slate-50 p-3">
                <dt class="text-xs font-semibold uppercase text-slate-500">Trạng thái</dt>
                <dd class="mt-1 text-slate-950">{{ statusLabel[selectedUser.status] || selectedUser.status }}</dd>
              </div>
              <div class="rounded-md border border-slate-200 bg-slate-50 p-3">
                <dt class="text-xs font-semibold uppercase text-slate-500">Điện thoại</dt>
                <dd class="mt-1 text-slate-950">{{ selectedUser.phone || '-' }}</dd>
              </div>
            </dl>
            <button class="inline-flex w-full items-center justify-center gap-2 rounded-md bg-slate-950 px-3 py-2.5 text-sm font-semibold text-white hover:bg-slate-800" @click="changeLock(selectedUser)">
              <UnlockKeyhole v-if="selectedUser.status === 'locked'" class="h-4 w-4" />
              <LockKeyhole v-else class="h-4 w-4" />
              {{ selectedUser.status === 'locked' ? 'Mở khóa tài khoản' : 'Khóa tài khoản' }}
            </button>
          </div>
          <div v-else class="p-4 text-sm text-slate-500">Chọn một dòng để xem hồ sơ vận hành.</div>
        </aside>
      </section>
    </div>
  </EraLmsLayout>
</template>
