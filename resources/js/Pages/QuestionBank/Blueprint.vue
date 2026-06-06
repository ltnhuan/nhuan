<script setup>
import { computed, onMounted, ref } from 'vue'
import EraLmsLayout from '@/Layouts/EraLmsLayout.vue'

const props = defineProps({
  apiHeaders: { type: Object, default: () => ({}) },
})

const loading = ref(true)
const message = ref('')
const blueprints = ref([])
const banks = ref([])
const selectedId = ref(null)
const preview = ref(null)
const form = ref({
  code: '',
  name: '',
  question_bank_id: null,
  total_questions: 30,
  total_score: 20,
  duration_minutes: 45,
  status: 'active',
})

const selected = computed(() => blueprints.value.find((item) => item.id === selectedId.value) || blueprints.value[0] || null)
const sections = computed(() => selected.value?.config?.sections || [])
const previewQuestions = computed(() => Array.isArray(preview.value?.questions) ? preview.value.questions : (Array.isArray(preview.value) ? preview.value : []))

onMounted(load)

async function api(path, options = {}) {
  const response = await fetch(`/api/v1${path}`, {
    ...options,
    headers: { ...props.apiHeaders, ...(options.headers || {}) },
  })
  const data = await response.json().catch(() => ({}))
  if (!response.ok) throw new Error(data.message || 'Không gọi được API blueprint.')
  return data.data || data
}

async function load() {
  loading.value = true
  try {
    const [bpData, bankData] = await Promise.all([
      api('/exam-blueprints?per_page=50'),
      api('/question-banks?per_page=100'),
    ])
    blueprints.value = bpData.data || bpData
    banks.value = bankData.data || bankData
    selectedId.value ||= blueprints.value[0]?.id || null
    form.value.question_bank_id ||= banks.value[0]?.id || null
    message.value = 'Đã tải blueprint.'
  } catch (error) {
    message.value = error.message
  } finally {
    loading.value = false
  }
}

async function createBlueprint() {
  if (!form.value.code.trim() || !form.value.name.trim() || !form.value.question_bank_id) {
    message.value = 'Nhập mã, tên và chọn ngân hàng câu hỏi.'
    return
  }

  try {
    const created = await api('/exam-blueprints', {
      method: 'POST',
      body: JSON.stringify({
        ...form.value,
        config: {
          sections: [
            { name: 'Nhận biết', question_count: 20, difficulty: ['easy'], bloom_level: ['remember', 'understand'], score_each: 0.5 },
            { name: 'Vận dụng', question_count: 10, difficulty: ['medium', 'hard'], bloom_level: ['apply', 'analyze'], score_each: 1 },
          ],
          randomize_questions: true,
          randomize_options: true,
        },
      }),
    })
    await load()
    selectedId.value = created.id
    message.value = 'Đã tạo blueprint.'
  } catch (error) {
    message.value = error.message
  }
}

async function generatePreview() {
  if (!selected.value) {
    message.value = 'Chọn blueprint trước khi sinh preview.'
    return
  }

  try {
    preview.value = await api(`/exam-blueprints/${selected.value.id}/generate-preview`, { method: 'POST' })
    const count = Array.isArray(preview.value?.questions) ? preview.value.questions.length : Array.isArray(preview.value) ? preview.value.length : 0
    message.value = `Đã sinh preview ${count} câu.`
  } catch (error) {
    message.value = error.message
  }
}
</script>

<template>
  <EraLmsLayout>
    <template #breadcrumb>Ngân hàng câu hỏi / Ma trận đề</template>
    <section class="mx-auto grid max-w-7xl gap-4 px-6 py-5 lg:grid-cols-[1fr_380px]">
      <main class="min-w-0 space-y-4">
        <div class="border bg-white p-4">
          <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
              <h1 class="text-lg font-semibold">Blueprint đề thi</h1>
              <p class="mt-1 text-sm text-slate-600">Tạo blueprint và sinh preview bằng random engine.</p>
            </div>
            <button class="rounded-md border px-3 py-2 text-sm font-semibold" @click="load">Tải lại</button>
          </div>
          <div v-if="message" class="mt-4 rounded-md border border-cyan-200 bg-cyan-50 px-4 py-3 text-sm text-cyan-900">{{ message }}</div>
        </div>

        <div class="overflow-hidden border bg-white">
          <div v-if="loading" class="p-5 text-sm text-slate-500">Đang tải...</div>
          <table v-else class="w-full text-left text-sm">
            <thead class="bg-slate-50 text-xs uppercase text-slate-500"><tr><th class="px-4 py-3">Mã</th><th class="px-4 py-3">Blueprint</th><th class="px-4 py-3">Số câu</th><th class="px-4 py-3">Thời lượng</th><th class="px-4 py-3">Trạng thái</th></tr></thead>
            <tbody class="divide-y">
              <tr v-for="item in blueprints" :key="item.id" class="cursor-pointer hover:bg-slate-50" :class="selected?.id === item.id ? 'bg-cyan-50' : ''" @click="selectedId = item.id">
                <td class="px-4 py-3 font-medium">{{ item.code }}</td>
                <td class="px-4 py-3">{{ item.name }}</td>
                <td class="px-4 py-3">{{ item.total_questions }}</td>
                <td class="px-4 py-3">{{ item.duration_minutes }} phút</td>
                <td class="px-4 py-3">{{ item.status }}</td>
              </tr>
            </tbody>
          </table>
        </div>

        <div class="border bg-white p-4">
          <h2 class="text-sm font-semibold">Sections của blueprint đang chọn</h2>
          <div class="mt-3 grid gap-3">
            <div v-for="section in sections" :key="section.name" class="grid gap-3 rounded-md border p-3 text-sm md:grid-cols-5">
              <div class="font-semibold">{{ section.name }}</div>
              <div>{{ section.question_count }} câu</div>
              <div>{{ (section.difficulty || []).join(', ') }}</div>
              <div>{{ (section.bloom_level || []).join(', ') }}</div>
              <div>{{ section.score_each }} điểm/câu</div>
            </div>
            <div v-if="!sections.length" class="rounded-md bg-slate-50 p-3 text-sm text-slate-500">Blueprint chưa có sections.</div>
          </div>
        </div>
      </main>

      <aside class="space-y-4">
        <div class="border bg-white p-4">
          <h2 class="text-sm font-semibold">Tạo blueprint nhanh</h2>
          <div class="mt-3 grid gap-3">
            <input v-model="form.code" class="rounded-md border px-3 py-2 text-sm" placeholder="Mã blueprint" />
            <input v-model="form.name" class="rounded-md border px-3 py-2 text-sm" placeholder="Tên blueprint" />
            <select v-model="form.question_bank_id" class="rounded-md border px-3 py-2 text-sm">
              <option v-for="bank in banks" :key="bank.id" :value="bank.id">{{ bank.code }} - {{ bank.name }}</option>
            </select>
            <div class="grid grid-cols-3 gap-2">
              <input v-model="form.total_questions" type="number" class="rounded-md border px-3 py-2 text-sm" />
              <input v-model="form.total_score" type="number" class="rounded-md border px-3 py-2 text-sm" />
              <input v-model="form.duration_minutes" type="number" class="rounded-md border px-3 py-2 text-sm" />
            </div>
            <button class="rounded-md bg-slate-950 px-3 py-2 text-sm font-semibold text-white" @click="createBlueprint">Lưu blueprint</button>
          </div>
        </div>
        <div class="border bg-white p-4">
          <h2 class="text-sm font-semibold">Preview đề ngẫu nhiên</h2>
          <p class="mt-2 text-sm text-slate-600">{{ selected?.name || 'Chưa chọn blueprint' }}</p>
          <button class="mt-4 w-full rounded-md bg-slate-950 px-3 py-2 text-sm text-white" @click="generatePreview">Sinh preview</button>
          <div v-if="preview" class="mt-3 max-h-72 overflow-auto rounded-md border border-slate-200">
            <div class="border-b bg-slate-50 px-3 py-2 text-xs font-semibold text-slate-600">Preview {{ previewQuestions.length }} câu</div>
            <div class="divide-y divide-slate-100">
              <div v-for="(question, index) in previewQuestions" :key="question.id || index" class="px-3 py-2 text-sm">
                <div class="font-semibold text-slate-900">{{ index + 1 }}. {{ question.title || question.prompt || question.question_text || `Question ${question.id || index + 1}` }}</div>
                <div class="mt-1 text-xs text-slate-500">{{ question.question_type || question.type || 'question' }} · {{ question.difficulty || '-' }} · {{ question.default_score || question.score || 0 }} điểm</div>
              </div>
              <div v-if="!previewQuestions.length" class="px-3 py-6 text-sm text-slate-500">Blueprint đã chạy nhưng chưa trả câu hỏi preview.</div>
            </div>
          </div>
        </div>
      </aside>
    </section>
  </EraLmsLayout>
</template>
