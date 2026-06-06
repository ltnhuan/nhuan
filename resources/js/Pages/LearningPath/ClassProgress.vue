<script setup>
import { computed, ref } from 'vue'
import EraLmsLayout from '@/Layouts/EraLmsLayout.vue'

const filters = ['Tất cả', 'Chưa bắt đầu', 'Đang học', 'Chậm tiến độ', 'Hoàn thành', 'Bị kẹt']
const selectedFilter = ref('Tất cả')
const query = ref('')
const actionNotice = ref('')

const rows = ref([
  { id: 'u001', full_name: 'Nguyễn Minh An', status: 'Đang học', progress_percent: 35, risk_level: 'medium', blocker: 'Quiz dưới 70 điểm', next_action: 'Giao bài ôn tập', last_event: 'Xem video 63%' },
  { id: 'u002', full_name: 'Trần Hoàng Bảo', status: 'Hoàn thành', progress_percent: 100, risk_level: 'low', blocker: '-', next_action: 'Cấp chứng nhận', last_event: 'Hoàn thành assignment' },
  { id: 'u003', full_name: 'Lê Khánh Chi', status: 'Bị kẹt', progress_percent: 22, risk_level: 'high', blocker: 'Chờ GV duyệt assignment', next_action: 'Duyệt thủ công', last_event: 'Nộp assignment' },
  { id: 'u004', full_name: 'Phạm Quốc Dũng', status: 'Chậm tiến độ', progress_percent: 18, risk_level: 'high', blocker: 'Chưa xem video bắt buộc', next_action: 'Nhắc học viên', last_event: 'Đăng nhập 3 ngày trước' },
])

const filteredRows = computed(() => rows.value.filter((row) => {
  const byFilter = selectedFilter.value === 'Tất cả' || row.status === selectedFilter.value
  const byQuery = !query.value || row.full_name.toLowerCase().includes(query.value.toLowerCase()) || row.blocker.toLowerCase().includes(query.value.toLowerCase())
  return byFilter && byQuery
}))

const selectedLearner = ref(rows.value[0])
const summary = computed(() => ({
  completion: Math.round(rows.value.reduce((sum, row) => sum + row.progress_percent, 0) / rows.value.length),
  blocked: rows.value.filter((row) => row.status === 'Bị kẹt').length,
  risk: rows.value.filter((row) => row.risk_level === 'high').length,
  done: rows.value.filter((row) => row.status === 'Hoàn thành').length,
}))

function selectLearner(row) {
  selectedLearner.value = row
}

function applyAction(type) {
  if (!selectedLearner.value) return
  if (type === 'approve') {
    selectedLearner.value.status = 'Đang học'
    selectedLearner.value.blocker = '-'
    selectedLearner.value.next_action = 'Theo dõi tiếp'
    actionNotice.value = `Đã duyệt blocker cho ${selectedLearner.value.full_name}.`
    return
  }

  if (type === 'remind') {
    actionNotice.value = `Đã tạo nhắc học tập cho ${selectedLearner.value.full_name}.`
    return
  }

  actionNotice.value = `Đã xuất hồ sơ tiến độ của ${selectedLearner.value.full_name}.`
}
</script>

<template>
  <EraLmsLayout>
    <template #breadcrumb>Đào tạo / Tiến độ lớp</template>

    <section class="mx-auto max-w-7xl px-4 py-5 sm:px-6">
      <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
          <div class="text-xs font-semibold uppercase text-cyan-700">Class progress command center</div>
          <h1 class="mt-1 text-xl font-bold text-slate-950">Theo dõi tiến độ lớp</h1>
          <p class="mt-2 text-sm text-slate-600">Tập trung vào người học bị kẹt, rủi ro cao và hành động tiếp theo.</p>
        </div>
        <div class="flex flex-wrap gap-2">
          <a href="/learning-path" class="rounded-md border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700">Sửa lộ trình</a>
          <button class="rounded-md bg-cyan-700 px-4 py-2 text-sm font-semibold text-white" @click="actionNotice = 'Đã tính lại tiến độ lớp từ completion, quiz, video và approval.'">Recalculate progress</button>
        </div>
      </div>

      <div v-if="actionNotice" class="mt-4 rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ actionNotice }}</div>

      <div class="mt-4 grid gap-3 md:grid-cols-4">
        <div class="rounded-lg border border-slate-200 bg-white p-4"><div class="text-xs uppercase text-slate-500">Tiến độ TB</div><div class="mt-1 text-2xl font-bold text-slate-950">{{ summary.completion }}%</div></div>
        <div class="rounded-lg border border-slate-200 bg-white p-4"><div class="text-xs uppercase text-slate-500">Bị kẹt</div><div class="mt-1 text-2xl font-bold text-amber-700">{{ summary.blocked }}</div></div>
        <div class="rounded-lg border border-slate-200 bg-white p-4"><div class="text-xs uppercase text-slate-500">Rủi ro cao</div><div class="mt-1 text-2xl font-bold text-rose-700">{{ summary.risk }}</div></div>
        <div class="rounded-lg border border-slate-200 bg-white p-4"><div class="text-xs uppercase text-slate-500">Hoàn thành</div><div class="mt-1 text-2xl font-bold text-emerald-700">{{ summary.done }}</div></div>
      </div>

      <div class="mt-4 flex flex-wrap gap-2">
        <button v-for="filter in filters" :key="filter" class="rounded-full px-3 py-1.5 text-sm ring-1" :class="selectedFilter === filter ? 'bg-slate-950 text-white ring-slate-950' : 'bg-white text-slate-700 ring-slate-200'" @click="selectedFilter = filter">{{ filter }}</button>
        <input v-model="query" class="ml-auto h-9 min-w-60 rounded-md border border-slate-300 px-3 text-sm" placeholder="Tìm học viên hoặc blocker" />
      </div>

      <div class="mt-4 grid gap-4 xl:grid-cols-[1fr_380px]">
        <section class="overflow-hidden rounded-lg border border-slate-200 bg-white">
          <table class="w-full text-left text-sm">
            <thead class="bg-slate-50 text-xs uppercase text-slate-500">
              <tr>
                <th class="px-4 py-3">Học viên</th>
                <th class="px-4 py-3">Trạng thái</th>
                <th class="px-4 py-3">Tiến độ</th>
                <th class="px-4 py-3">Rủi ro</th>
                <th class="px-4 py-3">Blocker</th>
                <th class="px-4 py-3">Hành động tiếp</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              <tr v-for="row in filteredRows" :key="row.id" class="cursor-pointer hover:bg-slate-50" :class="{ 'bg-cyan-50/60': selectedLearner?.id === row.id }" @click="selectLearner(row)">
                <td class="px-4 py-3 font-semibold text-slate-950">{{ row.full_name }}</td>
                <td class="px-4 py-3">{{ row.status }}</td>
                <td class="px-4 py-3">
                  <div class="h-2 w-28 rounded-full bg-slate-100"><div class="h-2 rounded-full bg-cyan-600" :style="{ width: `${row.progress_percent}%` }"></div></div>
                  <div class="mt-1 text-xs text-slate-500">{{ row.progress_percent }}%</div>
                </td>
                <td class="px-4 py-3"><span class="rounded-full px-2 py-1 text-xs font-semibold" :class="row.risk_level === 'high' ? 'bg-rose-50 text-rose-700' : row.risk_level === 'medium' ? 'bg-amber-50 text-amber-700' : 'bg-emerald-50 text-emerald-700'">{{ row.risk_level }}</span></td>
                <td class="px-4 py-3 text-slate-700">{{ row.blocker }}</td>
                <td class="px-4 py-3 text-slate-700">{{ row.next_action }}</td>
              </tr>
              <tr v-if="!filteredRows.length"><td colspan="6" class="px-4 py-10 text-center text-slate-500">Không có học viên khớp bộ lọc.</td></tr>
            </tbody>
          </table>
        </section>

        <aside class="rounded-lg border border-slate-200 bg-white p-4">
          <h2 class="text-sm font-bold text-slate-950">Hành trình học viên</h2>
          <p class="mt-1 text-sm text-slate-600">{{ selectedLearner.full_name }}</p>
          <dl class="mt-4 grid grid-cols-2 gap-3 text-sm">
            <div class="rounded-md bg-slate-50 p-3"><dt class="text-slate-500">Tiến độ</dt><dd class="mt-1 font-bold text-slate-950">{{ selectedLearner.progress_percent }}%</dd></div>
            <div class="rounded-md bg-slate-50 p-3"><dt class="text-slate-500">Rủi ro</dt><dd class="mt-1 font-bold text-slate-950">{{ selectedLearner.risk_level }}</dd></div>
          </dl>
          <ol class="mt-4 space-y-3 text-sm text-slate-700">
            <li class="rounded-md border border-slate-200 p-3">Sự kiện gần nhất: {{ selectedLearner.last_event }}</li>
            <li class="rounded-md border border-slate-200 p-3">Hành động khuyến nghị: {{ selectedLearner.next_action }}</li>
            <li class="rounded-md border border-amber-200 bg-amber-50 p-3 text-amber-900">Blocker: {{ selectedLearner.blocker }}</li>
          </ol>
          <div class="mt-4 grid gap-2 sm:grid-cols-3">
            <button class="rounded-md bg-emerald-700 px-3 py-2 text-sm font-semibold text-white" @click="applyAction('approve')">Duyệt</button>
            <button class="rounded-md border border-slate-300 px-3 py-2 text-sm font-semibold" @click="applyAction('remind')">Nhắc học</button>
            <button class="rounded-md border border-slate-300 px-3 py-2 text-sm font-semibold" @click="applyAction('export')">Xuất hồ sơ</button>
          </div>
        </aside>
      </div>
    </section>
  </EraLmsLayout>
</template>
