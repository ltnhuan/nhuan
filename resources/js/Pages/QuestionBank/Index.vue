<script setup>
import { computed, onMounted, ref } from 'vue'
import EraLmsLayout from '@/Layouts/EraLmsLayout.vue'

const props = defineProps({
  apiHeaders: { type: Object, default: () => ({}) },
})

const loading = ref(true)
const message = ref('')
const banks = ref([])
const selectedId = ref(null)
const filters = ref({ status: '', visibility: '' })
const formOpen = ref(false)
const form = ref({ code: '', name: '', visibility: 'tenant', description: '' })

const selected = computed(() => banks.value.find((bank) => bank.id === selectedId.value) || banks.value[0] || null)
const publishedCount = computed(() => banks.value.filter((bank) => ['published', 'approved'].includes(bank.status)).length)
const totalQuestions = computed(() => banks.value.reduce((sum, bank) => sum + (bank.questions_count || 0), 0))

onMounted(load)

async function api(path, options = {}) {
  const response = await fetch(`/api/v1${path}`, {
    ...options,
    headers: { ...props.apiHeaders, ...(options.headers || {}) },
  })
  const data = await response.json().catch(() => ({}))
  if (!response.ok) throw new Error(data.message || 'Không gọi được API ngân hàng câu hỏi.')
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
    selectedId.value ||= banks.value[0]?.id || null
    message.value = 'Đã tải danh sách ngân hàng.'
  } catch (error) {
    message.value = error.message
  } finally {
    loading.value = false
  }
}

async function createBank() {
  if (!form.value.code.trim() || !form.value.name.trim()) {
    message.value = 'Nhập mã và tên ngân hàng.'
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
    message.value = 'Đã tạo ngân hàng câu hỏi.'
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
      message.value = `Đã clone ${bank.name}.`
      return
    }
    if (action === 'review') {
      await api(`/question-banks/${bank.id}/submit-review`, { method: 'POST' })
      await load()
      message.value = `Đã gửi duyệt ${bank.name}.`
      return
    }
    if (action === 'approve') {
      await api(`/question-banks/${bank.id}/approve`, { method: 'POST' })
      await load()
      message.value = `Đã duyệt ${bank.name}.`
    }
  } catch (error) {
    message.value = error.message
  }
}
</script>

<template>
  <EraLmsLayout>
    <template #breadcrumb>Ngân hàng câu hỏi / Danh sách</template>

    <section class="border-b bg-white">
      <div class="mx-auto max-w-7xl px-6 py-5">
        <div class="flex flex-wrap items-center justify-between gap-3">
          <div>
            <h1 class="text-lg font-semibold text-slate-950">Ngân hàng câu hỏi</h1>
            <p class="mt-1 text-sm text-slate-600">Load dữ liệu thật, lọc trạng thái/phạm vi và thao tác clone, gửi duyệt, duyệt.</p>
          </div>
          <button class="rounded-md bg-slate-950 px-4 py-2 text-sm font-medium text-white" @click="formOpen = !formOpen">Tạo ngân hàng</button>
        </div>
        <div class="mt-5 grid gap-3 md:grid-cols-4">
          <select v-model="filters.status" class="rounded-md border border-slate-300 px-3 py-2 text-sm" @change="load">
            <option value="">Tất cả trạng thái</option>
            <option value="draft">Draft</option>
            <option value="review">Review</option>
            <option value="approved">Approved</option>
            <option value="published">Published</option>
          </select>
          <select v-model="filters.visibility" class="rounded-md border border-slate-300 px-3 py-2 text-sm" @change="load">
            <option value="">Tất cả phạm vi</option>
            <option value="private">Private</option>
            <option value="faculty">Faculty</option>
            <option value="tenant">Tenant</option>
          </select>
          <button class="rounded-md border border-slate-300 px-3 py-2 text-sm font-semibold" @click="load">Tải lại</button>
          <div class="rounded-md bg-slate-50 px-3 py-2 text-sm text-slate-600">{{ banks.length }} banks · {{ totalQuestions }} câu</div>
        </div>
      </div>
    </section>

    <section class="mx-auto grid max-w-7xl gap-4 px-6 py-5 lg:grid-cols-[1fr_360px]">
      <main class="min-w-0 space-y-4">
        <div v-if="message" class="rounded-md border border-cyan-200 bg-cyan-50 px-4 py-3 text-sm text-cyan-900">{{ message }}</div>
        <div v-if="formOpen" class="grid gap-3 border bg-white p-4 md:grid-cols-4">
          <input v-model="form.code" class="rounded-md border px-3 py-2 text-sm" placeholder="Mã ngân hàng" />
          <input v-model="form.name" class="rounded-md border px-3 py-2 text-sm" placeholder="Tên ngân hàng" />
          <select v-model="form.visibility" class="rounded-md border px-3 py-2 text-sm"><option value="tenant">Tenant</option><option value="faculty">Faculty</option><option value="private">Private</option></select>
          <button class="rounded-md bg-slate-950 px-3 py-2 text-sm text-white" @click="createBank">Lưu ngân hàng</button>
          <input v-model="form.description" class="rounded-md border px-3 py-2 text-sm md:col-span-4" placeholder="Mô tả" />
        </div>

        <div class="overflow-hidden border bg-white">
          <div v-if="loading" class="p-5 text-sm text-slate-500">Đang tải...</div>
          <table v-else class="w-full text-left text-sm">
            <thead class="bg-slate-50 text-xs uppercase text-slate-500">
              <tr><th class="px-4 py-3">Mã</th><th class="px-4 py-3">Ngân hàng</th><th class="px-4 py-3">Phạm vi</th><th class="px-4 py-3">Trạng thái</th><th class="px-4 py-3">Số câu</th><th class="px-4 py-3"></th></tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              <tr v-for="bank in banks" :key="bank.id" class="cursor-pointer hover:bg-slate-50" :class="selected?.id === bank.id ? 'bg-cyan-50' : ''" @click="selectedId = bank.id">
                <td class="px-4 py-3 font-medium">{{ bank.code }}</td>
                <td class="px-4 py-3">{{ bank.name }}</td>
                <td class="px-4 py-3">{{ bank.visibility }}</td>
                <td class="px-4 py-3">{{ bank.status }}</td>
                <td class="px-4 py-3">{{ bank.questions_count || 0 }}</td>
                <td class="px-4 py-3">
                  <div class="flex justify-end gap-2">
                    <button class="rounded border px-2 py-1 text-xs" @click.stop="rowAction('clone', bank)">Clone</button>
                    <button class="rounded border px-2 py-1 text-xs" @click.stop="rowAction('review', bank)">Gửi duyệt</button>
                    <button class="rounded bg-slate-900 px-2 py-1 text-xs text-white" @click.stop="rowAction('approve', bank)">Duyệt</button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </main>

      <aside class="space-y-4">
        <div class="border bg-white p-4">
          <h2 class="text-sm font-semibold text-slate-950">Thống kê ngân hàng</h2>
          <p class="mt-1 text-sm text-slate-600">{{ selected?.name || 'Chưa chọn' }}</p>
          <dl class="mt-4 grid grid-cols-2 gap-3 text-sm">
            <div class="rounded-md bg-slate-50 p-3"><dt class="text-slate-500">Tổng bank</dt><dd class="font-semibold">{{ banks.length }}</dd></div>
            <div class="rounded-md bg-slate-50 p-3"><dt class="text-slate-500">Đã duyệt/xuất bản</dt><dd class="font-semibold">{{ publishedCount }}</dd></div>
            <div class="rounded-md bg-slate-50 p-3"><dt class="text-slate-500">Câu trong bank</dt><dd class="font-semibold">{{ selected?.questions_count || 0 }}</dd></div>
            <div class="rounded-md bg-slate-50 p-3"><dt class="text-slate-500">Phạm vi</dt><dd class="font-semibold">{{ selected?.visibility || '-' }}</dd></div>
          </dl>
        </div>
      </aside>
    </section>
  </EraLmsLayout>
</template>
