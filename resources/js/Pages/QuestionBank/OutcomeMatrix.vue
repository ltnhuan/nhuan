<script setup>
import { computed, onMounted, ref } from 'vue'
import EraLmsLayout from '@/Layouts/EraLmsLayout.vue'

const props = defineProps({
  apiHeaders: { type: Object, default: () => ({}) },
})

const loading = ref(true)
const message = ref('')
const rows = ref([])
const typeFilter = ref('')

const filteredRows = computed(() => rows.value.filter((row) => !typeFilter.value || row.type === typeFilter.value))
const missingTotal = computed(() => filteredRows.value.reduce((sum, row) => sum + row.missing, 0))

onMounted(load)

async function api(path) {
  const response = await fetch(`/api/v1${path}`, { headers: props.apiHeaders })
  const data = await response.json().catch(() => ({}))
  if (!response.ok) throw new Error(data.message || 'Không tải được ma trận outcome.')
  return data.data || data
}

async function load() {
  loading.value = true
  try {
    const [coverage, outcomes] = await Promise.all([
      api('/question-coverage'),
      api('/learning-outcomes?per_page=100'),
    ])
    const outcomeRows = outcomes.data || outcomes
    const coverageRows = Array.isArray(coverage) ? coverage : []
    rows.value = outcomeRows.map((outcome) => {
      const coverageRow = coverageRows.find((item) => item.outcome === outcome.code) || {}
      const byBloom = coverageRow.by_bloom || {}
      const remember = Number(byBloom.remember || 0) + Number(byBloom.understand || 0)
      const apply = Number(byBloom.apply || 0)
      const analyze = Number(byBloom.analyze || 0)
      const total = Number(coverageRow.total || remember + apply + analyze)
      return {
        id: outcome.id,
        clo: outcome.code,
        name: outcome.name,
        type: outcome.type,
        remember,
        apply,
        analyze,
        total,
        missing: Math.max(0, 10 - total),
      }
    })
    message.value = 'Đã tải ma trận phủ chuẩn đầu ra.'
  } catch (error) {
    message.value = error.message
  } finally {
    loading.value = false
  }
}

function focusOutcome(row) {
  message.value = row.missing
    ? `${row.clo} còn thiếu ${row.missing} câu. Mở trang soạn câu hỏi để bổ sung mapping.`
    : `${row.clo} đã đủ mức phủ tối thiểu.`
}
</script>

<template>
  <EraLmsLayout>
    <template #breadcrumb>Ngân hàng câu hỏi / Ma trận CLO/PLO</template>
    <section class="mx-auto max-w-7xl px-6 py-5">
      <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
          <h1 class="text-lg font-semibold text-slate-950">Ma trận phủ chuẩn đầu ra</h1>
          <p class="mt-1 text-sm text-slate-600">Load outcome và coverage từ API, lọc CLO/PLO và cảnh báo thiếu câu.</p>
        </div>
        <div class="flex gap-2">
          <select v-model="typeFilter" class="rounded-md border px-3 py-2 text-sm">
            <option value="">Tất cả</option>
            <option value="CLO">CLO</option>
            <option value="PLO">PLO</option>
          </select>
          <button class="rounded-md border px-3 py-2 text-sm font-semibold" @click="load">Tải lại</button>
        </div>
      </div>
      <div v-if="message" class="mt-4 rounded-md border border-cyan-200 bg-cyan-50 px-4 py-3 text-sm text-cyan-900">{{ message }}</div>
      <div class="mt-4 grid gap-3 md:grid-cols-3">
        <div class="rounded-md border bg-white p-4 text-sm"><div class="text-slate-500">Outcome</div><div class="mt-1 text-xl font-semibold">{{ filteredRows.length }}</div></div>
        <div class="rounded-md border bg-white p-4 text-sm"><div class="text-slate-500">Thiếu câu</div><div class="mt-1 text-xl font-semibold">{{ missingTotal }}</div></div>
        <div class="rounded-md border bg-white p-4 text-sm"><div class="text-slate-500">Trạng thái</div><div class="mt-1 text-xl font-semibold">{{ missingTotal ? 'Cần bổ sung' : 'Đủ phủ' }}</div></div>
      </div>
      <div class="mt-4 overflow-hidden border bg-white">
        <div v-if="loading" class="p-5 text-sm text-slate-500">Đang tải...</div>
        <table v-else class="w-full text-left text-sm">
          <thead class="bg-slate-50 text-xs uppercase text-slate-500"><tr><th class="px-4 py-3">CLO/PLO</th><th class="px-4 py-3">Tên</th><th class="px-4 py-3">Nhớ/Hiểu</th><th class="px-4 py-3">Vận dụng</th><th class="px-4 py-3">Phân tích</th><th class="px-4 py-3">Cần bổ sung</th><th class="px-4 py-3"></th></tr></thead>
          <tbody class="divide-y">
            <tr v-for="row in filteredRows" :key="row.id">
              <td class="px-4 py-3 font-medium">{{ row.clo }}</td>
              <td class="px-4 py-3">{{ row.name }}</td>
              <td class="px-4 py-3">{{ row.remember }}</td>
              <td class="px-4 py-3">{{ row.apply }}</td>
              <td class="px-4 py-3">{{ row.analyze }}</td>
              <td class="px-4 py-3" :class="row.missing ? 'text-amber-700' : 'text-emerald-700'">{{ row.missing }}</td>
              <td class="px-4 py-3 text-right"><button class="text-cyan-700" @click="focusOutcome(row)">Chi tiết</button></td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>
  </EraLmsLayout>
</template>
