<script setup>
import { Brain, Gauge, ListChecks, Settings2, SlidersHorizontal, Sparkles } from '@lucide/vue'
import { computed, onMounted, ref } from 'vue'
import EraLmsLayout from '@/Layouts/EraLmsLayout.vue'

const props = defineProps({
  apiHeaders: { type: Object, default: () => ({}) },
})

const LABELS = {
  vi: {
    pageTitle: 'Ngân hàng câu hỏi',
    breadcrumb: 'Ngân hàng câu hỏi / Danh sách',
    subtitle: 'Quản lý bộ đề và ngân hàng câu hỏi theo chuẩn quốc tế, có phân loại Bloom, CLO/PLO, độ khó và phạm vi sử dụng.',
    createButton: 'Tạo ngân hàng',
    searchPlaceholder: 'Tìm kiếm theo mã / tên / mô tả',
    actions: {
      clone: 'Nhân bản',
      review: 'Gửi duyệt',
      approve: 'Duyệt',
      create: 'Lưu ngân hàng',
      openAi: 'Trí tuệ nhân tạo tư vấn cho ngân hàng này',
    },
    filters: {
      statusAll: 'Tất cả trạng thái',
      visibilityAll: 'Tất cả phạm vi',
      reload: 'Tải lại',
      minQuestions: 'Số câu tối thiểu',
      includeEmpty: 'Hiển thị ngân hàng chưa có câu',
      onlyDraft: 'Chỉ xem trạng thái nháp',
      includeArchived: 'Bao gồm ngân hàng đã lưu trữ',
      sortBy: 'Sắp xếp theo',
      sortOrder: 'Thứ tự',
      asc: 'Tăng dần',
      desc: 'Giảm dần',
    },
    headers: {
      code: 'Mã',
      name: 'Ngân hàng',
      visibility: 'Phạm vi',
      status: 'Trạng thái',
      questions: 'Số câu',
      createdAt: 'Ngày tạo',
      actions: 'Hành động',
    },
    panel: {
      title: 'Thống kê nhanh',
      selectedTitle: 'Thông tin ngân hàng đang chọn',
      selectedEmpty: 'Chưa chọn ngân hàng',
      totalBanks: 'Tổng số ngân hàng đang hiển thị',
      published: 'Đã duyệt/xuất bản',
      questionCount: 'Số câu trong ngân hàng',
      visibility: 'Phạm vi',
      quality: 'Độ phủ dữ liệu',
      qualityOk: 'Khá đầy đủ',
      qualityLow: 'Cần bổ sung',
      risk: 'Ngân hàng có cảnh báo (ít câu)',
      quickLinks: 'Liên kết nhanh đến module',
      related: 'Dữ liệu liên quan',
      relatedHint: 'Xây dựng theo bộ lọc hiện tại để xử lý nhóm nhanh.',
      topBanks: 'Top ngân hàng theo số câu',
    },
    advanced: {
      title: 'Cài đặt nâng cao',
      hint: 'Dùng cho xử lý hàng loạt và duyệt nhanh.',
      labelDraftOnly: 'Chỉ hiển thị nháp',
      labelArchived: 'Hiển thị đã lưu trữ',
      reset: 'Đặt lại bộ lọc',
      apply: 'Áp dụng',
    },
    ai: {
      title: 'Trợ lý trí tuệ nhân tạo',
      hint: 'Mở nhanh luồng trợ lý cho ngân hàng đang chọn.',
      analysis: 'Phân tích chất lượng',
      generation: 'Sinh câu hỏi từ ngân hàng',
      mapping: 'Gợi ý ánh xạ CLO/PLO',
      go: 'Mở trợ lý',
    },
    empty: 'Không có dữ liệu phù hợp',
    statusLabel: {
      empty: 'Chưa có',
      loading: 'Đang tải...',
      loaded: 'Đã tải danh sách ngân hàng.',
      notFound: 'Chưa chọn ngân hàng.',
      invalidCreate: 'Nhập mã và tên ngân hàng.',
      created: 'Đã tạo ngân hàng câu hỏi.',
      reviewed: 'Đã gửi duyệt',
      cloned: 'Đã nhân bản',
      approved: 'Đã duyệt',
      error: 'Không gọi được API ngân hàng câu hỏi.',
    },
  },
  en: {
    pageTitle: 'Ngân hàng câu hỏi',
    breadcrumb: 'Ngân hàng câu hỏi / Danh sách',
    subtitle: 'Quản lý bộ đề và ngân hàng câu hỏi theo chuẩn quốc tế, có phân loại Bloom, CLO/PLO, độ khó và phạm vi sử dụng.',
    createButton: 'Tạo ngân hàng',
    searchPlaceholder: 'Tìm theo mã / tên / mô tả',
    actions: {
      clone: 'Nhân bản',
      review: 'Gửi duyệt',
      approve: 'Duyệt',
      create: 'Lưu ngân hàng',
      openAi: 'Mở trợ lý trí tuệ nhân tạo cho ngân hàng này',
    },
    filters: {
      statusAll: 'Tất cả trạng thái',
      visibilityAll: 'Tất cả phạm vi',
      reload: 'Tải lại',
      minQuestions: 'Số câu tối thiểu',
      includeEmpty: 'Hiển thị ngân hàng trống',
      onlyDraft: 'Chỉ bản nháp',
      includeArchived: 'Bao gồm đã lưu trữ',
      sortBy: 'Sắp xếp theo',
      sortOrder: 'Thứ tự',
      asc: 'Tăng dần',
      desc: 'Giảm dần',
    },
    headers: {
      code: 'Mã',
      name: 'Ngân hàng',
      visibility: 'Phạm vi',
      status: 'Trạng thái',
      questions: 'Số câu',
      createdAt: 'Ngày tạo',
      actions: 'Hành động',
    },
    panel: {
      title: 'Thống kê nhanh',
      selectedTitle: 'Ngân hàng đang chọn',
      selectedEmpty: 'Chưa chọn ngân hàng',
      totalBanks: 'Ngân hàng đang hiển thị',
      published: 'Đã xuất bản/đã duyệt',
      questionCount: 'Số câu',
      visibility: 'Phạm vi',
      quality: 'Phạm vi bao phủ',
      qualityOk: 'Ổn định',
      qualityLow: 'Cần bổ sung',
      risk: 'Ngân hàng cần chú ý',
      quickLinks: 'Liên kết nhanh',
      related: 'Dữ liệu liên quan',
      relatedHint: 'Tạo theo bộ lọc và sắp xếp hiện tại để xử lý hàng loạt.',
      topBanks: 'Top ngân hàng theo số câu',
    },
    advanced: {
      title: 'Cài đặt nâng cao',
      hint: 'Dùng cho xử lý hàng loạt và duyệt nhanh.',
      labelDraftOnly: 'Chỉ xem nháp',
      labelArchived: 'Hiện đã lưu trữ',
      reset: 'Đặt lại bộ lọc',
      apply: 'Áp dụng',
    },
    ai: {
      title: 'Trợ lý trí tuệ nhân tạo',
      hint: 'Mở luồng trợ lý cho ngân hàng đang chọn.',
      analysis: 'Phân tích chất lượng',
      generation: 'Sinh câu hỏi',
      mapping: 'Đề xuất ánh xạ CLO/PLO',
      go: 'Mở trợ lý',
    },
    empty: 'Không có dữ liệu phù hợp',
    statusLabel: {
      empty: 'Không có dữ liệu',
      loading: 'Đang tải...',
      loaded: 'Đã tải danh sách ngân hàng.',
      notFound: 'Chưa chọn ngân hàng.',
      invalidCreate: 'Nhập mã và tên ngân hàng.',
      created: 'Đã tạo ngân hàng câu hỏi.',
      reviewed: 'Đã gửi duyệt',
      cloned: 'Đã nhân bản',
      approved: 'Đã duyệt',
      error: 'Không gọi được API ngân hàng câu hỏi.',
    },
  },
}

const locale = (() => {
  const requested = new URLSearchParams(window.location.search).get('lang')?.toLowerCase()
  return requested === 'en' ? 'en' : 'vi'
})()

const t = computed(() => LABELS[locale])

const loading = ref(true)
const message = ref('')
const banks = ref([])
const selectedId = ref(null)
const filters = ref({
  status: '',
  visibility: '',
})
const searchQuery = ref('')
const sortBy = ref('name')
const sortDir = ref('desc')
const minQuestions = ref(0)
const includeEmptyBanks = ref(true)
const includeArchived = ref(false)
const onlyDraft = ref(false)
const formOpen = ref(false)
const form = ref({
  code: '',
  name: '',
  visibility: 'tenant',
  description: '',
})

const visibleBanks = computed(() => {
  const keyword = searchQuery.value.trim().toLowerCase()

  const rows = banks.value
    .filter((bank) => (filters.value.status ? bank.status === filters.value.status : true))
    .filter((bank) => (filters.value.visibility ? bank.visibility === filters.value.visibility : true))
    .filter((bank) => (includeArchived.value ? true : bank.status !== 'archived'))
    .filter((bank) => (onlyDraft.value ? bank.status === 'draft' : true))
    .filter((bank) => (includeEmptyBanks.value ? true : Number(bank.questions_count || 0) > 0))
    .filter((bank) => Number(bank.questions_count || 0) >= Number(minQuestions.value || 0))
    .filter((bank) => {
      if (!keyword) return true
      const text = `${bank.code || ''} ${bank.name || ''} ${bank.description || ''}`.toLowerCase()
      return text.includes(keyword)
    })

  const sorted = [...rows]
  sorted.sort((a, b) => {
    const dir = sortDir.value === 'asc' ? 1 : -1
    if (sortBy.value === 'questions') {
      return (Number(a.questions_count || 0) - Number(b.questions_count || 0)) * dir
    }
    if (sortBy.value === 'created_at') {
      const aValue = Number(new Date(a.created_at || 0).getTime())
      const bValue = Number(new Date(b.created_at || 0).getTime())
      return (aValue - bValue) * dir
    }

    const aValue = String(({
      name: a.name || '',
      code: a.code || '',
      visibility: a.visibility || '',
      status: a.status || '',
    })[sortBy.value] || '')

    const bValue = String(({
      name: b.name || '',
      code: b.code || '',
      visibility: b.visibility || '',
      status: b.status || '',
    })[sortBy.value] || '')

    return aValue.localeCompare(bValue) * dir
  })

  return sorted
})

const statusMap = {
  draft: 'Nháp',
  review: 'Chờ duyệt',
  approved: 'Đã duyệt',
  published: 'Đã xuất bản',
  archived: 'Đã lưu trữ',
  denied: 'Từ chối',
  suspended: 'Tạm dừng',
}
const visibilityMap = {
  tenant: 'Toàn viện',
  faculty: 'Khoa/Bộ môn',
  private: 'Riêng tư',
  course: 'Theo khóa học',
  chapter: 'Theo chương',
  global: 'Toàn hệ thống',
}

const standardBankNames = {
  'QB-MC': 'Bộ đề nền tảng chung theo chuẩn quốc tế',
  'QB-CNTT': 'Bộ đề Công nghệ thông tin theo chuẩn ACM/IEEE',
  'QB-DL': 'Bộ đề Du lịch theo chuẩn nghề ASEAN',
  'QB-NN': 'Bộ đề Ngoại ngữ theo Khung năng lực 6 bậc',
  'QB-VH9': 'Bộ đề Văn hóa 9+ theo chuẩn chương trình mới',
}

function bankName(bank) {
  if (!bank) return ''
  return standardBankNames[bank.code] || bank.name || '-'
}

function labelStatus(value) {
  if (!value) return '-'
  return statusMap[value] || value
}

function labelVisibility(value) {
  if (!value) return '-'
  return visibilityMap[value] || value
}

const selected = computed(() => {
  return banks.value.find((bank) => bank.id === selectedId.value) || visibleBanks.value[0] || null
})
const publishedCount = computed(() => banks.value.filter((bank) => ['published', 'approved'].includes(bank.status)).length)
const totalQuestions = computed(() => banks.value.reduce((sum, bank) => sum + (bank.questions_count || 0), 0))
const visibleSummary = computed(() => `${visibleBanks.value.length} / ${banks.value.length}`)

const statusStats = computed(() => banks.value.reduce((acc, bank) => {
  const key = String(bank.status || 'draft')
  acc[key] = (acc[key] || 0) + 1
  return acc
}, {}))

const visibilityStats = computed(() => banks.value.reduce((acc, bank) => {
  const key = String(bank.visibility || 'private')
  acc[key] = (acc[key] || 0) + 1
  return acc
}, {}))

const riskBanks = computed(() => visibleBanks.value.filter((bank) => Number(bank.questions_count || 0) < 5).length)

const selectedCoverage = computed(() => {
  const count = Number(selected.value?.questions_count || 0)
  return count >= 10 ? t.value.panel.qualityOk : t.value.panel.qualityLow
})

const topBanks = computed(() => [...banks.value]
  .sort((a, b) => Number(b.questions_count || 0) - Number(a.questions_count || 0))
  .slice(0, 3))

const aiContext = computed(() => {
  if (!selected.value) return ''
  return `bank_id=${selected.value.id}&bank_code=${encodeURIComponent(selected.value.code || '')}&bank_name=${encodeURIComponent(bankName(selected.value))}`
})

function getAiLink(section) {
  const params = new URLSearchParams()
  params.set('v', Date.now().toString())
  params.set('source', 'question-bank')
  if (selected.value?.id) params.set('bank_id', String(selected.value.id))
  return `/ai?${params.toString()}#${section}`
}

async function api(path, options = {}) {
  const response = await fetch(`/api/v1${path}`, {
    ...options,
    headers: { ...props.apiHeaders, ...(options.headers || {}) },
  })
  const data = await response.json().catch(() => ({}))
  if (!response.ok) throw new Error(data.message || t.value.statusLabel.error)
  return data.data || data
}

async function load() {
  loading.value = true
  try {
    const query = new URLSearchParams({ per_page: '50' })
    if (filters.value.status) query.set('status', filters.value.status)
    if (filters.value.visibility) query.set('visibility', filters.value.visibility)
    const data = await api(`/question-banks?${query}`)
    banks.value = data.data || data
    if (!banks.value.length) {
      selectedId.value = null
    } else if (!banks.value.find((bank) => bank.id === selectedId.value)) {
      selectedId.value = visibleBanks.value[0]?.id || banks.value[0]?.id || null
    }
    message.value = t.value.statusLabel.loaded
  } catch (error) {
    message.value = error.message
  } finally {
    loading.value = false
  }
}

async function createBank() {
  if (!form.value.code.trim() || !form.value.name.trim()) {
    message.value = t.value.statusLabel.invalidCreate
    return
  }

  try {
    const bank = await api('/question-banks', {
      method: 'POST',
      body: JSON.stringify(form.value),
    })
    form.value = { code: '', name: '', visibility: 'tenant', description: '' }
    formOpen.value = false
    await load()
    selectedId.value = bank.id
    message.value = t.value.statusLabel.created
  } catch (error) {
    message.value = error.message
  }
}

async function rowAction(action, bank) {
  try {
    if (action === 'clone') {
      const cloned = await api(`/question-banks/${bank.id}/clone`, { method: 'POST' })
      await load()
      selectedId.value = cloned.id
      message.value = `${t.value.statusLabel.cloned}: ${bankName(bank)}`
      return
    }
    if (action === 'review') {
      await api(`/question-banks/${bank.id}/submit-review`, { method: 'POST' })
      await load()
      message.value = `${t.value.statusLabel.reviewed}: ${bankName(bank)}`
      return
    }
    if (action === 'approve') {
      await api(`/question-banks/${bank.id}/approve`, { method: 'POST' })
      await load()
      message.value = `${t.value.statusLabel.approved}: ${bankName(bank)}`
    }
  } catch (error) {
    message.value = error.message
  }
}

function resetAdvanced() {
  searchQuery.value = ''
  filters.value = { status: '', visibility: '' }
  sortBy.value = 'name'
  sortDir.value = 'desc'
  minQuestions.value = 0
  includeEmptyBanks.value = true
  includeArchived.value = false
  onlyDraft.value = false
}
</script>

<template>
  <EraLmsLayout>
    <template #breadcrumb>{{ t.breadcrumb }}</template>

    <section class="border-b bg-white">
      <div class="mx-auto max-w-7xl px-6 py-5">
        <div class="flex flex-wrap items-center justify-between gap-3">
          <div>
            <h1 class="text-lg font-semibold text-slate-950">{{ t.pageTitle }}</h1>
            <p class="mt-1 text-sm text-slate-600">{{ t.subtitle }}</p>
          </div>
          <div class="flex flex-wrap gap-2">
            <button class="rounded-md bg-slate-950 px-4 py-2 text-sm font-medium text-white" @click="formOpen = !formOpen">
              {{ t.createButton }}
            </button>
            <a
              :href="getAiLink('assistant')"
              class="inline-flex items-center gap-2 rounded-md border border-cyan-200 bg-cyan-50 px-4 py-2 text-sm font-medium text-cyan-900 hover:bg-cyan-100"
              :title="t.actions.openAi"
            >
              <Sparkles class="h-4 w-4" />
              {{ t.ai.analysis }}
            </a>
          </div>
        </div>

        <div class="mt-5 grid gap-3 md:grid-cols-4 xl:grid-cols-6">
          <input
            v-model="searchQuery"
            class="rounded-md border border-slate-300 px-3 py-2 text-sm"
            :placeholder="t.searchPlaceholder"
          />
          <select v-model="filters.status" class="rounded-md border border-slate-300 px-3 py-2 text-sm">
            <option value="">{{ t.filters.statusAll }}</option>
            <option value="draft">Nháp</option>
            <option value="review">Chờ duyệt</option>
            <option value="approved">Đã duyệt</option>
            <option value="published">Đã xuất bản</option>
          </select>
          <select v-model="filters.visibility" class="rounded-md border border-slate-300 px-3 py-2 text-sm">
            <option value="">{{ t.filters.visibilityAll }}</option>
            <option value="private">Riêng tư</option>
            <option value="faculty">Khoa/Bộ môn</option>
            <option value="tenant">Toàn viện</option>
          </select>
          <button class="rounded-md border border-slate-300 px-3 py-2 text-sm font-semibold" @click="load">
            {{ t.filters.reload }}
          </button>
          <label class="rounded-md border border-slate-200 bg-slate-50 p-2 text-xs text-slate-700">
            <div class="mb-1 text-slate-500">{{ t.filters.minQuestions }}</div>
            <input v-model.number="minQuestions" class="h-10 w-full rounded-md border border-slate-300 px-3 text-sm" type="number" min="0" />
          </label>
          <div class="rounded-md border border-slate-200 bg-slate-50 p-2 text-xs text-slate-700">
            <label class="inline-flex w-full items-center gap-2">
              <input v-model="includeEmptyBanks" type="checkbox" />
              <span>{{ t.filters.includeEmpty }}</span>
            </label>
          </div>
          <label class="rounded-md border border-slate-200 bg-slate-50 p-2 text-xs text-slate-700">
            <div class="mb-1 text-slate-500">{{ t.filters.sortBy }}</div>
            <select v-model="sortBy" class="h-10 w-full rounded-md border border-slate-300 bg-white px-3 text-sm">
              <option value="name">Tên</option>
              <option value="code">Mã</option>
              <option value="status">Trạng thái</option>
              <option value="visibility">Phạm vi</option>
              <option value="questions">Số câu</option>
              <option value="created_at">Ngày tạo</option>
            </select>
          </label>
          <label class="rounded-md border border-slate-200 bg-slate-50 p-2 text-xs text-slate-700">
            <div class="mb-1 text-slate-500">{{ t.filters.sortOrder }}</div>
            <select v-model="sortDir" class="h-10 w-full rounded-md border border-slate-300 bg-white px-3 text-sm">
              <option value="asc">{{ t.filters.asc }}</option>
              <option value="desc">{{ t.filters.desc }}</option>
            </select>
          </label>
          <label class="rounded-md border border-slate-200 bg-slate-50 p-2 text-xs text-slate-700">
            <div class="mb-1 text-slate-500">Bộ lọc bổ sung</div>
            <span class="inline-flex w-full items-center gap-2">
              <input v-model="onlyDraft" type="checkbox" />
              <span>{{ t.advanced.labelDraftOnly }}</span>
            </span>
          </label>
          <label class="rounded-md border border-slate-200 bg-slate-50 p-2 text-xs text-slate-700">
            <div class="mb-1 text-slate-500">Bộ lọc bổ sung</div>
            <span class="inline-flex w-full items-center gap-2">
              <input v-model="includeArchived" type="checkbox" />
              <span>{{ t.advanced.labelArchived }}</span>
            </span>
          </label>
          <div class="flex items-center rounded-md border border-slate-200 bg-slate-50 p-2 text-xs text-slate-700">
            <span class="text-slate-500">{{ t.panel.totalBanks }}: {{ visibleSummary }}</span>
          </div>
          <div class="rounded-md border border-slate-200 bg-slate-50 p-2 text-xs text-slate-700">
            {{ t.panel.relatedHint }}
          </div>
        </div>
      </div>
    </section>

    <section class="mx-auto grid max-w-7xl gap-4 px-6 py-5 lg:grid-cols-[1fr_360px]">
      <main class="min-w-0 space-y-4">
        <div class="rounded-md border bg-white p-4">
          <div v-if="message" class="rounded-md border border-cyan-200 bg-cyan-50 p-3 text-sm text-cyan-900">{{ message }}</div>
          <div class="mt-3 flex flex-wrap gap-2">
            <button class="rounded-full border px-3 py-1 text-xs font-medium" @click="resetAdvanced">{{ t.advanced.reset }}</button>
            <button class="rounded-full border bg-slate-900 px-3 py-1 text-xs font-medium text-white" @click="load">{{ t.advanced.apply }}</button>
          </div>
        </div>

        <div v-if="formOpen" class="grid gap-3 rounded-md border bg-white p-4 md:grid-cols-4">
          <input v-model="form.code" class="rounded-md border px-3 py-2 text-sm" placeholder="Mã ngân hàng" />
          <input v-model="form.name" class="rounded-md border px-3 py-2 text-sm" placeholder="Tên ngân hàng" />
          <select v-model="form.visibility" class="rounded-md border px-3 py-2 text-sm">
            <option value="tenant">Toàn viện</option>
            <option value="faculty">Khoa/Bộ môn</option>
            <option value="private">Riêng tư</option>
          </select>
          <button class="rounded-md bg-slate-950 px-3 py-2 text-sm text-white" @click="createBank">{{ t.actions.create }}</button>
          <input v-model="form.description" class="rounded-md border px-3 py-2 text-sm md:col-span-4" placeholder="Mô tả" />
        </div>

        <div class="overflow-hidden border bg-white">
          <div v-if="loading" class="p-5 text-sm text-slate-500">{{ t.statusLabel.loading }}</div>
          <table v-else class="w-full text-left text-sm">
            <thead class="bg-slate-50 text-xs uppercase text-slate-500">
              <tr>
                <th class="px-4 py-3">{{ t.headers.code }}</th>
                <th class="px-4 py-3">{{ t.headers.name }}</th>
                <th class="px-4 py-3">{{ t.headers.visibility }}</th>
                <th class="px-4 py-3">{{ t.headers.status }}</th>
                <th class="px-4 py-3">{{ t.headers.questions }}</th>
                <th class="px-4 py-3">{{ t.headers.createdAt }}</th>
                <th class="px-4 py-3">{{ t.headers.actions }}</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              <tr v-for="bank in visibleBanks" :key="bank.id" class="cursor-pointer hover:bg-slate-50" :class="selected?.id === bank.id ? 'bg-cyan-50' : ''" @click="selectedId = bank.id">
                <td class="px-4 py-3 font-medium">{{ bank.code }}</td>
                <td class="px-4 py-3">{{ bankName(bank) }}</td>
                <td class="px-4 py-3">{{ labelVisibility(bank.visibility) }}</td>
                <td class="px-4 py-3">{{ labelStatus(bank.status) }}</td>
                <td class="px-4 py-3">{{ bank.questions_count || 0 }}</td>
                <td class="px-4 py-3 text-xs text-slate-500">{{ bank.created_at || '-' }}</td>
                <td class="px-4 py-3">
                  <div class="flex flex-wrap justify-end gap-2">
                    <button
                      class="rounded border px-2 py-1 text-xs"
                      :title="t.actions.clone"
                      @click.stop="rowAction('clone', bank)"
                    >
                      {{ t.actions.clone }}
                    </button>
                    <button
                      class="rounded border px-2 py-1 text-xs"
                      :title="t.actions.review"
                      @click.stop="rowAction('review', bank)"
                    >
                      {{ t.actions.review }}
                    </button>
                    <button
                      class="rounded bg-slate-900 px-2 py-1 text-xs text-white"
                      :title="t.actions.approve"
                      @click.stop="rowAction('approve', bank)"
                    >
                      {{ t.actions.approve }}
                    </button>
                    <a
                      class="inline-flex items-center rounded border border-cyan-200 px-2 py-1 text-xs text-cyan-700"
                      :href="`/ai?v=${Date.now()}&bank_id=${bank.id}&lang=${locale}#assistant`"
                      :title="t.actions.openAi"
                    >
                      <Brain class="mr-1 h-3.5 w-3.5" />
                      Trợ lý
                    </a>
                  </div>
                </td>
              </tr>
              <tr v-if="!visibleBanks.length">
                <td colspan="7" class="px-4 py-8 text-center text-sm text-slate-500">{{ t.empty }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </main>

      <aside class="space-y-4">
        <div class="rounded-lg border bg-white p-4 shadow-sm">
          <h2 class="text-sm font-semibold text-slate-950">{{ t.panel.title }}</h2>
          <p class="mt-1 text-sm text-slate-600">{{ t.panel.selectedTitle }}</p>
          <p class="mt-1 text-sm text-slate-900">{{ selected ? bankName(selected) : t.panel.selectedEmpty }}</p>
          <dl class="mt-3 grid grid-cols-2 gap-3 text-sm">
            <div class="rounded-md bg-slate-50 p-3">
              <dt class="text-slate-500">{{ t.panel.totalBanks }}</dt>
              <dd class="font-semibold">{{ visibleBanks.length }}</dd>
            </div>
            <div class="rounded-md bg-slate-50 p-3">
              <dt class="text-slate-500">{{ t.panel.published }}</dt>
              <dd class="font-semibold">{{ publishedCount }}</dd>
            </div>
            <div class="rounded-md bg-slate-50 p-3">
              <dt class="text-slate-500">{{ t.panel.questionCount }}</dt>
              <dd class="font-semibold">{{ selected?.questions_count || 0 }}</dd>
            </div>
            <div class="rounded-md bg-slate-50 p-3">
              <dt class="text-slate-500">{{ t.panel.quality }}</dt>
              <dd class="font-semibold">{{ selectedCoverage }}</dd>
            </div>
            <div class="rounded-md bg-slate-50 p-3">
              <dt class="text-slate-500">{{ t.panel.risk }}</dt>
              <dd class="font-semibold">{{ riskBanks }}</dd>
            </div>
            <div class="rounded-md bg-slate-50 p-3">
              <dt class="text-slate-500">{{ t.panel.visibility }}</dt>
              <dd class="font-semibold">{{ labelVisibility(selected?.visibility) }}</dd>
            </div>
          </dl>
          <div class="mt-3 rounded-md bg-slate-50 p-3 text-xs text-slate-600">{{ t.panel.relatedHint }}</div>
        </div>

        <div class="rounded-lg border bg-white p-4 shadow-sm">
          <h2 class="text-sm font-semibold text-slate-950 inline-flex items-center gap-2">
            <Settings2 class="h-4 w-4" />
            {{ t.advanced.title }}
          </h2>
          <p class="mt-1 text-xs text-slate-500">{{ t.advanced.hint }}</p>
          <div class="mt-3 rounded-md border border-slate-200 p-3 text-sm">
            <div class="mb-2 text-slate-600">{{ t.panel.quickLinks }}</div>
            <div class="grid gap-2">
              <a class="rounded-md border px-3 py-2" href="/question-banks/editor">Soạn câu hỏi</a>
              <a class="rounded-md border px-3 py-2" href="/question-banks/import">Nhập câu hỏi</a>
              <a class="rounded-md border px-3 py-2" href="/question-banks/blueprints">Ma trận đề</a>
            </div>
          </div>
        </div>

        <div class="rounded-lg border bg-white p-4 shadow-sm">
          <div class="text-sm font-semibold text-slate-950 inline-flex items-center gap-2">
            <SlidersHorizontal class="h-4 w-4" />
            {{ t.ai.title }}
          </div>
          <p class="mt-1 text-xs text-slate-500">{{ t.ai.hint }}</p>
          <div class="mt-3 grid gap-2 text-sm">
          <a
            class="inline-flex items-center justify-between rounded-md border border-cyan-200 bg-cyan-50 px-3 py-2 text-cyan-900 hover:bg-cyan-100"
            :href="`${getAiLink('assistant')}`"
            :title="t.ai.analysis"
          >
              <span class="inline-flex items-center gap-2">
                <Brain class="h-4 w-4" />
                {{ t.ai.analysis }}
              </span>
              <span class="text-xs text-cyan-700">{{ t.ai.go }}</span>
            </a>
            <a
              class="inline-flex items-center justify-between rounded-md border border-indigo-200 bg-indigo-50 px-3 py-2 text-indigo-900 hover:bg-indigo-100"
              :href="`${getAiLink('generation')}`"
              :title="t.ai.generation"
            >
              <span class="inline-flex items-center gap-2">
                <ListChecks class="h-4 w-4" />
                {{ t.ai.generation }}
              </span>
              <span class="text-xs text-indigo-700">{{ t.ai.go }}</span>
            </a>
            <a
              class="inline-flex items-center justify-between rounded-md border border-emerald-200 bg-emerald-50 px-3 py-2 text-emerald-900 hover:bg-emerald-100"
              :href="`${getAiLink('insight')}`"
              :title="t.ai.mapping"
            >
              <span class="inline-flex items-center gap-2">
                <Gauge class="h-4 w-4" />
                {{ t.ai.mapping }}
              </span>
              <span class="text-xs text-emerald-700">{{ t.ai.go }}</span>
            </a>
          </div>
          <p class="mt-3 text-xs text-slate-500">Bối cảnh: {{ aiContext || t.statusLabel.empty }}</p>
        </div>

        <div class="rounded-lg border bg-white p-4 shadow-sm">
          <h2 class="text-sm font-semibold text-slate-950">{{ t.panel.topBanks }}</h2>
          <div class="mt-3 space-y-2 text-sm">
            <div v-for="bank in topBanks" :key="bank.id" class="rounded-md border border-slate-200 bg-slate-50 p-3">
              <div class="text-xs text-slate-500">{{ bank.code }}</div>
              <div class="font-semibold">{{ bankName(bank) }}</div>
              <div class="mt-1 text-xs text-slate-500">{{ bank.questions_count || 0 }} câu</div>
            </div>
            <div v-if="!topBanks.length" class="rounded-md border border-slate-200 bg-slate-50 p-3 text-xs text-slate-500">{{ t.empty }}</div>
          </div>
        </div>

        <div class="rounded-lg border bg-white p-4 shadow-sm">
          <h2 class="text-sm font-semibold text-slate-950">{{ t.panel.related }}</h2>
          <div class="mt-3 grid gap-3 md:grid-cols-2 text-sm">
            <div>
              <div class="mb-1 text-xs text-slate-500">Trạng thái</div>
              <div class="space-y-1">
                <div v-for="(count, key) in statusStats" :key="`s-${key}`" class="rounded-md bg-slate-50 p-2">{{ labelStatus(key) }}: {{ count }}</div>
              </div>
            </div>
            <div>
              <div class="mb-1 text-xs text-slate-500">Phạm vi</div>
              <div class="space-y-1">
                <div v-for="(count, key) in visibilityStats" :key="`v-${key}`" class="rounded-md bg-slate-50 p-2">{{ labelVisibility(key) }}: {{ count }}</div>
              </div>
            </div>
          </div>
        </div>
      </aside>
    </section>
  </EraLmsLayout>
</template>
