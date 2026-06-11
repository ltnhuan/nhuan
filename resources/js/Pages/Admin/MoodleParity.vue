<script setup>
import { computed, onMounted, ref } from 'vue'
import EraLmsLayout from '@/Layouts/EraLmsLayout.vue'
import ActionBar from '@/Components/Lms/ActionBar.vue'
import StatusBadge from '@/Components/Lms/StatusBadge.vue'
import { useLmsAction } from '@/composables/useLmsAction'

const props = defineProps({
  sessionUser: { type: Object, default: null },
  apiHeaders: { type: Object, default: () => ({}) },
})

defineEmits(['logout'])

const summary = ref({})
const source = ref({})
const features = ref([])
const syncPlan = ref(null)
const loadingPage = ref(false)
const { loading, toast, runAction } = useLmsAction(props.apiHeaders)

const actions = [
  { action_key: 'moodle.parity.report', label: 'Cập nhật báo cáo', route: '/api/v1/admin/lms/moodle-parity', method: 'GET' },
  { action_key: 'moodle.parity.sync', label: 'Đồng bộ hàm chuẩn', route: '/api/v1/admin/lms/moodle-parity/sync', method: 'POST', confirm_required: true, confirm_message: 'Đồng bộ action, quyền và menu theo chức năng LMS chuẩn?' },
  { action_key: 'action.smoke', label: 'Chạy smoke test hệ thống', route: '/api/v1/admin/lms/action-check/smoke-test', method: 'POST', confirm_required: true, confirm_message: 'Chạy smoke test action flow?' },
]

const groupedFeatures = computed(() => ({
  covered: features.value.filter((feature) => feature.status === 'covered'),
  partial: features.value.filter((feature) => feature.status === 'partial'),
  planned: features.value.filter((feature) => feature.status === 'planned'),
}))

function statusLabel(status) {
  return {
    covered: 'Đủ',
    partial: 'Một phần',
    planned: 'Dự kiến',
  }[status] || status
}

function coverageClass(percent) {
  if (percent >= 80) return 'bg-emerald-500'
  if (percent > 0) return 'bg-amber-500'
  return 'bg-slate-300'
}

function applyReport(data) {
  summary.value = data.summary || {}
  source.value = data.source || {}
  features.value = data.features || []
}

async function load() {
  loadingPage.value = true
  try {
    const response = await fetch('/api/v1/admin/lms/moodle-parity', { headers: props.apiHeaders })
    const payload = await response.json()
    applyReport(payload.data || {})
  } finally {
    loadingPage.value = false
  }
}

async function run(action) {
  const data = await runAction({
    actionKey: action.action_key,
    url: action.route,
    method: action.method,
    confirm: action.confirm_required,
    confirmMessage: action.confirm_message,
    reload: action.action_key === 'moodle.parity.sync' ? load : null,
  })

  if (action.action_key === 'moodle.parity.report' && data) {
    applyReport(data)
  }

  if (action.action_key === 'moodle.parity.sync' && data) {
    syncPlan.value = data.parity || null
  }
}

onMounted(load)
</script>

<template>
  <EraLmsLayout :session-user="sessionUser" @logout="$emit('logout')">
    <template #breadcrumb>Admin / Tương thích Moodle</template>

    <section class="space-y-5">
      <div v-if="toast.show" class="rounded-md px-4 py-3 text-sm font-semibold" :class="toast.type === 'success' ? 'bg-emerald-50 text-emerald-700' : 'bg-red-50 text-red-700'">
        {{ toast.message }}
      </div>

      <div class="rounded-lg border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-200 bg-slate-950 px-6 py-5 text-white">
          <div class="text-xs font-semibold uppercase tracking-[0.18em] text-cyan-200">Bản đồ năng lực chuẩn LMS</div>
          <h1 class="mt-2 text-2xl font-bold">Chức năng tương thích Moodle</h1>
          <p class="mt-2 text-sm text-slate-300">{{ source.strategy || 'Đối chiếu chức năng chuẩn LMS với module EraLMS đang có.' }}</p>
        </div>
        <div class="p-4">
          <ActionBar :actions="actions" :loading-map="loading" @run="run" />
        </div>
      </div>

      <div class="grid gap-4 md:grid-cols-5">
        <div v-for="(value, key) in summary" :key="key" class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
          <div class="text-xs font-semibold uppercase text-slate-500">{{ key.replaceAll('_', ' ') }}</div>
          <div class="mt-2 text-2xl font-bold text-slate-950">{{ value }}</div>
        </div>
      </div>

      <div v-if="syncPlan" class="rounded-lg border border-cyan-200 bg-cyan-50 p-4 text-sm text-cyan-950">
        <div class="font-bold">Đồng bộ đã xong</div>
        <div class="mt-2">Module đã cập nhật: {{ syncPlan.updated_existing.join(', ') }}</div>
        <div class="mt-1">Module dự kiến: {{ syncPlan.planned_modules.join(', ') }}</div>
      </div>

      <section class="grid gap-4 lg:grid-cols-3">
        <div class="rounded-lg border border-emerald-200 bg-white p-4 shadow-sm">
          <div class="text-sm font-bold text-emerald-700">Đã đủ</div>
          <div class="mt-2 text-3xl font-bold text-slate-950">{{ groupedFeatures.covered.length }}</div>
        </div>
        <div class="rounded-lg border border-amber-200 bg-white p-4 shadow-sm">
          <div class="text-sm font-bold text-amber-700">Một phần</div>
          <div class="mt-2 text-3xl font-bold text-slate-950">{{ groupedFeatures.partial.length }}</div>
        </div>
        <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
          <div class="text-sm font-bold text-slate-700">Dự kiến</div>
          <div class="mt-2 text-3xl font-bold text-slate-950">{{ groupedFeatures.planned.length }}</div>
        </div>
      </section>

      <section class="rounded-lg border border-slate-200 bg-white shadow-sm">
        <div class="flex items-center justify-between border-b border-slate-200 px-4 py-3">
          <h2 class="text-sm font-bold text-slate-950">Phạm vi chức năng</h2>
          <span class="text-xs text-slate-500">{{ features.length }} nhóm chức năng</span>
        </div>
        <div class="overflow-auto">
          <table class="w-full text-left text-xs">
            <thead class="bg-slate-50 uppercase text-slate-500">
              <tr>
                <th class="px-3 py-2">Chức năng chuẩn</th>
                <th class="px-3 py-2">Module EraLMS</th>
                <th class="px-3 py-2">Mức phủ</th>
                <th class="px-3 py-2">Trạng thái</th>
                <th class="px-3 py-2">Route đã có</th>
                <th class="px-3 py-2">Route thiếu</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              <tr v-if="loadingPage">
                <td class="px-3 py-6 text-center text-slate-500" colspan="6">Đang tải coverage...</td>
              </tr>
              <tr v-for="feature in features" v-else :key="feature.feature_key">
                <td class="px-3 py-2">
                  <div class="font-semibold text-slate-800">{{ feature.moodle_feature }}</div>
                  <div class="mt-1 max-w-sm text-slate-500">{{ feature.label }}</div>
                </td>
                <td class="px-3 py-2 font-semibold text-slate-700">{{ feature.eralms_module }}</td>
                <td class="px-3 py-2">
                  <div class="flex min-w-32 items-center gap-2">
                    <div class="h-2 w-24 rounded-full bg-slate-100">
                      <div class="h-2 rounded-full" :class="coverageClass(feature.coverage_percent)" :style="{ width: `${feature.coverage_percent}%` }"></div>
                    </div>
                    <span class="font-semibold text-slate-700">{{ feature.coverage_percent }}%</span>
                  </div>
                </td>
                <td class="px-3 py-2"><StatusBadge :status="statusLabel(feature.status)" /></td>
                <td class="px-3 py-2 text-slate-500">{{ feature.existing_routes.length }}</td>
                <td class="px-3 py-2 text-slate-500">{{ feature.missing_routes.length }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>
    </section>
  </EraLmsLayout>
</template>
