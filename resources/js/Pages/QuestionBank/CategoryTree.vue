<script setup>
import { computed, onMounted, ref } from 'vue'
import EraLmsLayout from '@/Layouts/EraLmsLayout.vue'

const props = defineProps({
  apiHeaders: { type: Object, default: () => ({}) },
})

const loading = ref(true)
const saving = ref(false)
const message = ref('')
const banks = ref([])
const categories = ref([])
const questions = ref([])
const selectedBankId = ref(null)
const selectedCategory = ref(null)
const mode = ref('create')
const form = ref({
  id: null,
  parent_id: null,
  code: '',
  name: '',
  description: '',
  sort_order: 1,
})

const activeBank = computed(() => banks.value.find((bank) => bank.id === selectedBankId.value) || null)
const flatCategories = computed(() => flattenCategories(categories.value))
const selectedQuestionCount = computed(() => questions.value.length)

onMounted(load)

async function api(path, options = {}) {
  const response = await fetch(`/api/v1${path}`, {
    ...options,
    headers: {
      ...props.apiHeaders,
      ...(options.headers || {}),
    },
  })
  const data = await response.json().catch(() => ({}))

  if (!response.ok) {
    throw new Error(data.message || 'Không gọi được API danh mục câu hỏi.')
  }

  return data.data || data
}

async function load() {
  loading.value = true
  try {
    const data = await api('/question-banks?per_page=100')
    banks.value = data.data || data
    selectedBankId.value ||= banks.value[0]?.id || null
    await loadCategories()
    message.value = 'Đã tải cây danh mục.'
  } catch (error) {
    message.value = error.message
  } finally {
    loading.value = false
  }
}

async function loadCategories() {
  if (!selectedBankId.value) {
    categories.value = []
    questions.value = []
    return
  }

  categories.value = await api(`/question-categories?question_bank_id=${selectedBankId.value}`)
  const first = flatCategories.value[0] || null
  if (!selectedCategory.value || !flatCategories.value.some((item) => item.id === selectedCategory.value.id)) {
    selectCategory(first)
  } else {
    selectCategory(flatCategories.value.find((item) => item.id === selectedCategory.value.id))
  }
}

async function loadQuestions(category = selectedCategory.value) {
  if (!category) {
    questions.value = []
    return
  }

  const data = await api(`/questions?category_id=${category.id}&per_page=12`)
  questions.value = data.data || data
}

function flattenCategories(items, level = 0, parent = null) {
  return items.flatMap((item) => {
    const row = { ...item, level, parent_name: parent?.name || null }
    return [row, ...flattenCategories(item.children || [], level + 1, item)]
  })
}

async function changeBank() {
  selectedCategory.value = null
  resetForm()
  await loadCategories()
  message.value = `Đang xem danh mục của ${activeBank.value?.name || 'ngân hàng đã chọn'}.`
}

function selectCategory(category) {
  selectedCategory.value = category
  if (category) {
    mode.value = 'edit'
    form.value = {
      id: category.id,
      parent_id: category.parent_id || null,
      code: category.code || '',
      name: category.name || '',
      description: category.description || '',
      sort_order: category.sort_order || 1,
    }
    loadQuestions(category)
  } else {
    resetForm()
  }
}

function resetForm(parent = selectedCategory.value) {
  mode.value = 'create'
  const nextOrder = flatCategories.value.filter((item) => (item.parent_id || null) === (parent?.id || null)).length + 1
  form.value = {
    id: null,
    parent_id: parent?.id || null,
    code: nextCode(parent),
    name: '',
    description: '',
    sort_order: nextOrder,
  }
}

function nextCode(parent) {
  const prefix = parent?.code ? `${parent.code}.` : 'CAT-'
  return `${prefix}${String(Date.now()).slice(-4)}`
}

async function saveCategory() {
  if (!selectedBankId.value) {
    message.value = 'Chọn ngân hàng trước khi lưu danh mục.'
    return
  }
  if (!form.value.code.trim() || !form.value.name.trim()) {
    message.value = 'Nhập mã và tên danh mục.'
    return
  }

  saving.value = true
  try {
    const payload = {
      question_bank_id: selectedBankId.value,
      parent_id: form.value.parent_id || null,
      code: form.value.code.trim(),
      name: form.value.name.trim(),
      description: form.value.description || null,
      sort_order: Number(form.value.sort_order) || 1,
    }
    const saved = mode.value === 'edit' && form.value.id
      ? await api(`/question-categories/${form.value.id}`, { method: 'PUT', body: JSON.stringify(payload) })
      : await api('/question-categories', { method: 'POST', body: JSON.stringify(payload) })

    await loadCategories()
    const category = flatCategories.value.find((item) => item.id === saved.id)
    if (category) selectCategory(category)
    message.value = mode.value === 'edit' ? 'Đã cập nhật danh mục.' : 'Đã tạo danh mục mới.'
  } catch (error) {
    message.value = error.message
  } finally {
    saving.value = false
  }
}

async function deleteCategory() {
  if (!selectedCategory.value) return
  if ((selectedCategory.value.children || []).length) {
    message.value = 'Danh mục còn danh mục con, hãy xóa hoặc chuyển danh mục con trước.'
    return
  }

  saving.value = true
  try {
    await fetch(`/api/v1/question-categories/${selectedCategory.value.id}`, {
      method: 'DELETE',
      headers: props.apiHeaders,
    }).then((response) => {
      if (!response.ok) throw new Error('Không xóa được danh mục.')
    })
    selectedCategory.value = null
    await loadCategories()
    message.value = 'Đã xóa danh mục.'
  } catch (error) {
    message.value = error.message
  } finally {
    saving.value = false
  }
}

async function moveCategory(direction) {
  const category = selectedCategory.value
  if (!category) return
  const siblings = flatCategories.value
    .filter((item) => (item.parent_id || null) === (category.parent_id || null))
    .sort((a, b) => (a.sort_order || 0) - (b.sort_order || 0))
  const index = siblings.findIndex((item) => item.id === category.id)
  const target = siblings[index + direction]
  if (!target) {
    message.value = direction < 0 ? 'Danh mục đã ở đầu nhóm.' : 'Danh mục đã ở cuối nhóm.'
    return
  }

  saving.value = true
  try {
    await Promise.all([
      api(`/question-categories/${category.id}`, { method: 'PUT', body: JSON.stringify({ sort_order: target.sort_order }) }),
      api(`/question-categories/${target.id}`, { method: 'PUT', body: JSON.stringify({ sort_order: category.sort_order }) }),
    ])
    await loadCategories()
    const moved = flatCategories.value.find((item) => item.id === category.id)
    if (moved) selectCategory(moved)
    message.value = 'Đã đổi thứ tự danh mục.'
  } catch (error) {
    message.value = error.message
  } finally {
    saving.value = false
  }
}

async function reassignQuestion(question) {
  if (!selectedCategory.value) return
  message.value = `Chọn danh mục đích trong cây rồi dùng form để chuyển "${question.title}".`
}
</script>

<template>
  <EraLmsLayout>
    <template #breadcrumb>Ngân hàng câu hỏi / Cây danh mục</template>

    <section class="mx-auto max-w-7xl px-6 py-5">
      <div class="flex flex-wrap items-center justify-between gap-3 border-b bg-white px-5 py-4">
        <div>
          <h1 class="text-lg font-semibold text-slate-950">Cây danh mục câu hỏi</h1>
          <p class="mt-1 text-sm text-slate-600">Quản lý danh mục theo ngân hàng, chọn node để xem câu hỏi và chỉnh metadata.</p>
        </div>
        <div class="flex min-w-0 flex-wrap items-center gap-2">
          <select v-model="selectedBankId" class="h-10 min-w-64 rounded-md border border-slate-300 bg-white px-3 text-sm" @change="changeBank">
            <option v-for="bank in banks" :key="bank.id" :value="bank.id">{{ bank.code }} - {{ bank.name }}</option>
          </select>
          <button class="rounded-md border border-slate-300 px-3 py-2 text-sm font-semibold" @click="load">Tải lại</button>
        </div>
      </div>

      <div v-if="message" class="mt-4 rounded-md border border-cyan-200 bg-cyan-50 px-4 py-3 text-sm text-cyan-900">{{ message }}</div>
      <div v-if="loading" class="mt-4 rounded-md border bg-white p-5 text-sm text-slate-500">Đang tải danh mục...</div>

      <div v-else class="mt-4 grid gap-4 xl:grid-cols-[340px_minmax(0,1fr)] 2xl:grid-cols-[360px_minmax(0,1fr)_360px]">
        <aside class="min-w-0 border bg-white">
          <div class="flex items-center justify-between border-b px-4 py-3">
            <div>
              <h2 class="text-sm font-semibold">Danh mục</h2>
              <p class="mt-1 text-xs text-slate-500">{{ flatCategories.length }} node · {{ activeBank?.name }}</p>
            </div>
            <button class="rounded-md bg-slate-950 px-3 py-2 text-xs font-semibold text-white" @click="resetForm(null)">Thêm gốc</button>
          </div>
          <div class="max-h-[620px] overflow-auto p-3">
            <button
              v-for="category in flatCategories"
              :key="category.id"
              class="mb-2 flex w-full items-center justify-between gap-3 rounded-md border px-3 py-2 text-left text-sm"
              :class="selectedCategory?.id === category.id ? 'border-cyan-500 bg-cyan-50 text-cyan-950' : 'border-slate-200 bg-white hover:bg-slate-50'"
              :style="{ paddingLeft: `${12 + category.level * 22}px` }"
              @click="selectCategory(category)"
            >
              <span class="min-w-0">
                <span class="block truncate font-semibold">{{ category.name }}</span>
                <span class="text-xs text-slate-500">{{ category.code }} · sort {{ category.sort_order }}</span>
              </span>
              <span v-if="category.children?.length" class="shrink-0 rounded-full bg-slate-100 px-2 py-1 text-xs text-slate-600">{{ category.children.length }}</span>
            </button>
            <div v-if="!flatCategories.length" class="rounded-md bg-slate-50 p-4 text-sm text-slate-500">Ngân hàng này chưa có danh mục.</div>
          </div>
        </aside>

        <main class="min-w-0 border bg-white">
          <div class="flex flex-wrap items-center justify-between gap-3 border-b px-4 py-3">
            <div>
              <h2 class="text-sm font-semibold">Câu hỏi trong danh mục</h2>
              <p class="mt-1 text-xs text-slate-500">{{ selectedCategory?.name || 'Chưa chọn danh mục' }} · {{ selectedQuestionCount }} câu đang hiển thị</p>
            </div>
            <div class="flex gap-2">
              <button class="rounded-md border border-slate-300 px-3 py-2 text-xs font-semibold disabled:opacity-50" :disabled="!selectedCategory || saving" @click="moveCategory(-1)">Lên</button>
              <button class="rounded-md border border-slate-300 px-3 py-2 text-xs font-semibold disabled:opacity-50" :disabled="!selectedCategory || saving" @click="moveCategory(1)">Xuống</button>
              <button class="rounded-md border border-slate-300 px-3 py-2 text-xs font-semibold disabled:opacity-50" :disabled="!selectedCategory" @click="resetForm(selectedCategory)">Thêm con</button>
            </div>
          </div>

          <div class="p-4">
            <div v-if="selectedCategory" class="mb-4 grid gap-3 md:grid-cols-4">
              <div class="rounded-md bg-slate-50 p-3 text-sm"><div class="text-xs text-slate-500">Mã</div><div class="mt-1 font-semibold">{{ selectedCategory.code }}</div></div>
              <div class="rounded-md bg-slate-50 p-3 text-sm"><div class="text-xs text-slate-500">Danh mục cha</div><div class="mt-1 font-semibold">{{ selectedCategory.parent_name || 'Gốc' }}</div></div>
              <div class="rounded-md bg-slate-50 p-3 text-sm"><div class="text-xs text-slate-500">Danh mục con</div><div class="mt-1 font-semibold">{{ selectedCategory.children?.length || 0 }}</div></div>
              <div class="rounded-md bg-slate-50 p-3 text-sm"><div class="text-xs text-slate-500">Sort order</div><div class="mt-1 font-semibold">{{ selectedCategory.sort_order }}</div></div>
            </div>

            <div class="overflow-hidden border">
              <table class="w-full text-left text-sm">
                <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                  <tr>
                    <th class="px-3 py-3">Mã</th>
                    <th class="px-3 py-3">Câu hỏi</th>
                    <th class="px-3 py-3">Loại</th>
                    <th class="px-3 py-3">Bloom</th>
                    <th class="px-3 py-3">Trạng thái</th>
                    <th class="px-3 py-3"></th>
                  </tr>
                </thead>
                <tbody class="divide-y">
                  <tr v-for="question in questions" :key="question.id">
                    <td class="px-3 py-3 font-medium">{{ question.code }}</td>
                    <td class="px-3 py-3">{{ question.title }}</td>
                    <td class="px-3 py-3">{{ question.question_type }}</td>
                    <td class="px-3 py-3">{{ question.bloom_level }}</td>
                    <td class="px-3 py-3">{{ question.status }}</td>
                    <td class="px-3 py-3 text-right"><button class="text-cyan-700" @click="reassignQuestion(question)">Chuyển</button></td>
                  </tr>
                  <tr v-if="!questions.length">
                    <td colspan="6" class="px-3 py-8 text-center text-sm text-slate-500">Chưa có câu hỏi trong danh mục đang chọn.</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </main>

        <aside class="min-w-0 border bg-white xl:col-span-2 2xl:col-span-1">
          <div class="border-b px-4 py-3">
            <h2 class="text-sm font-semibold">{{ mode === 'edit' ? 'Sửa danh mục' : 'Tạo danh mục' }}</h2>
            <p class="mt-1 text-xs text-slate-500">Dữ liệu lưu trực tiếp vào API question-categories.</p>
          </div>
          <div class="space-y-3 p-4">
            <label class="block text-sm">
              <span class="text-xs font-semibold uppercase text-slate-500">Parent</span>
              <select v-model="form.parent_id" class="mt-1 h-10 w-full rounded-md border border-slate-300 bg-white px-3 text-sm">
                <option :value="null">Danh mục gốc</option>
                <option v-for="category in flatCategories.filter((item) => item.id !== form.id)" :key="category.id" :value="category.id">
                  {{ '— '.repeat(category.level) }}{{ category.name }}
                </option>
              </select>
            </label>
            <label class="block text-sm">
              <span class="text-xs font-semibold uppercase text-slate-500">Mã danh mục</span>
              <input v-model="form.code" class="mt-1 h-10 w-full rounded-md border border-slate-300 px-3 text-sm" />
            </label>
            <label class="block text-sm">
              <span class="text-xs font-semibold uppercase text-slate-500">Tên danh mục</span>
              <input v-model="form.name" class="mt-1 h-10 w-full rounded-md border border-slate-300 px-3 text-sm" />
            </label>
            <label class="block text-sm">
              <span class="text-xs font-semibold uppercase text-slate-500">Sort order</span>
              <input v-model="form.sort_order" type="number" min="1" class="mt-1 h-10 w-full rounded-md border border-slate-300 px-3 text-sm" />
            </label>
            <label class="block text-sm">
              <span class="text-xs font-semibold uppercase text-slate-500">Mô tả</span>
              <textarea v-model="form.description" rows="4" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm"></textarea>
            </label>
            <div class="grid grid-cols-2 gap-2">
              <button class="rounded-md bg-slate-950 px-3 py-2 text-sm font-semibold text-white disabled:opacity-50" :disabled="saving" @click="saveCategory">{{ saving ? 'Đang lưu...' : 'Lưu' }}</button>
              <button class="rounded-md border border-slate-300 px-3 py-2 text-sm font-semibold" @click="resetForm(selectedCategory)">Tạo mới</button>
            </div>
            <button class="w-full rounded-md border border-rose-200 px-3 py-2 text-sm font-semibold text-rose-700 disabled:opacity-50" :disabled="!selectedCategory || saving" @click="deleteCategory">Xóa danh mục đang chọn</button>
          </div>
        </aside>
      </div>
    </section>
  </EraLmsLayout>
</template>
