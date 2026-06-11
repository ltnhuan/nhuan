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

const rows = ref([])
const summary = ref({})
const loadingPage = ref(false)
const { loading, toast, runAction } = useLmsAction(props.apiHeaders)

const actions = [
  { action_key: 'action.scan', label: 'Quét hành động', route: '/api/v1/admin/lms/action-check/scan', method: 'POST' },
  { action_key: 'action.sync', label: 'Đồng bộ hành động', route: '/api/v1/admin/lms/action-check/sync-actions', method: 'POST', confirm_required: true, confirm_message: 'Đồng bộ danh mục hành động?' },
  { action_key: 'action.fix_permissions', label: 'Sửa quyền thiếu', route: '/api/v1/admin/lms/action-check/fix-permissions', method: 'POST', confirm_required: true, confirm_message: 'Tạo các quyền còn thiếu?' },
  { action_key: 'action.clear_cache', label: 'Xóa bộ đệm', route: '/api/v1/admin/lms/action-check/clear-cache', method: 'POST', confirm_required: true, confirm_message: 'Xóa cache route/config/app?' },
  { action_key: 'action.smoke', label: 'Chạy kiểm tra nhanh', route: '/api/v1/admin/lms/action-check/smoke-test', method: 'POST', confirm_required: true, confirm_message: 'Chạy smoke test action flow?' },
]

const issueRows = computed(() => rows.value.filter((row) => row.status !== 'OK'))
const summaryLabels = {
  total: 'Tổng số',
  ok: 'Hợp lệ',
  issues: 'Vấn đề',
  missing_routes: 'Thiếu route',
  missing_permissions: 'Thiếu quyền',
  missing_api: 'Thiếu API',
}
const actionLabels = {
  'Scan actions': 'Quét hành động',
  'Sync actions': 'Đồng bộ hành động',
  'Fix missing permissions': 'Sửa quyền thiếu',
  'Clear cache': 'Xóa bộ đệm',
  'Run smoke test': 'Chạy kiểm tra nhanh',
  'Xem danh sách System Check': 'Xem danh sách kiểm tra hệ thống',
  'Actions System Check': 'Thao tác kiểm tra hệ thống',
  'Ask course': 'Hỏi theo khóa học',
  'Run snapshot': 'Chạy snapshot',
  'Recalculate risk score': 'Tính lại điểm rủi ro',
  'Resolve alert': 'Xử lý cảnh báo',
  'Acknowledge Alerts': 'Ghi nhận cảnh báo',
}

function summaryLabel(key) {
  return summaryLabels[key] || key.replaceAll('_', ' ')
}

function actionLabel(label) {
  const direct = actionLabels[label]
  if (direct) return direct

  return String(label || '')
    .replace('Xem danh sách Dashboard', 'Xem danh sách tổng quan')
    .replace('Xem danh sách Analytics', 'Xem danh sách phân tích')
    .replace('Xem danh sách Metrics', 'Xem danh sách chỉ số')
    .replace('Xem danh sách Risks', 'Xem danh sách rủi ro')
    .replace('Tạo Scan', 'Tạo lượt quét')
    .replace('Dashboard', 'Tổng quan')
    .replace('Analytics', 'Phân tích')
    .replace('Metrics', 'Chỉ số')
    .replace('Risks', 'Rủi ro')
}

async function load() {
  loadingPage.value = true
  try {
    const response = await fetch('/api/v1/admin/lms/action-check', { headers: props.apiHeaders })
    const payload = await response.json()
    const data = payload.data || {}
    rows.value = data.actions || []
    summary.value = data.summary || {}
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
    reload: load,
  })

  if (data?.actions) {
    rows.value = data.actions
    summary.value = data.summary || {}
  }
}

onMounted(load)
</script>

<template>
  <EraLmsLayout :session-user="sessionUser" @logout="$emit('logout')">
    <template #breadcrumb>Admin / Kiểm tra hành động LMS</template>

    <section class="space-y-5">
      <div v-if="toast.show" class="rounded-md px-4 py-3 text-sm font-semibold" :class="toast.type === 'success' ? 'bg-emerald-50 text-emerald-700' : 'bg-red-50 text-red-700'">
        {{ toast.message }}
      </div>

      <div class="rounded-lg border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-200 bg-slate-950 px-6 py-5 text-white">
          <div class="text-xs font-semibold uppercase tracking-[0.18em] text-cyan-200">Luồng hành động EraLMS</div>
          <h1 class="mt-2 text-2xl font-bold">Kiểm tra hành động hệ thống</h1>
          <p class="mt-2 text-sm text-slate-300">Quét hành động nút bấm, API route, controller, quyền và bộ xử lý giao diện để phát hiện luồng bị đứt.</p>
        </div>
        <div class="p-4">
          <ActionBar :actions="actions" :loading-map="loading" @run="run" />
        </div>
      </div>

      <div class="grid gap-4 md:grid-cols-4">
        <div v-for="(value, key) in summary" :key="key" class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
          <div class="text-xs font-semibold uppercase text-slate-500">{{ summaryLabel(key) }}</div>
          <div class="mt-2 text-2xl font-bold text-slate-950">{{ value }}</div>
        </div>
      </div>

      <section class="rounded-lg border border-slate-200 bg-white shadow-sm">
        <div class="flex items-center justify-between border-b border-slate-200 px-4 py-3">
          <h2 class="text-sm font-bold text-slate-950">Danh sách hành động</h2>
          <span class="text-xs text-slate-500">{{ issueRows.length }} vấn đề</span>
        </div>
        <div class="overflow-auto">
          <table class="w-full text-left text-xs">
            <thead class="bg-slate-50 uppercase text-slate-500">
              <tr>
                <th class="px-3 py-2">Module</th>
                <th class="px-3 py-2">Mã hành động</th>
                <th class="px-3 py-2">Nhãn nút</th>
                <th class="px-3 py-2">Route</th>
                <th class="px-3 py-2">Điểm cuối API</th>
                <th class="px-3 py-2">Quyền</th>
                <th class="px-3 py-2">Controller</th>
                <th class="px-3 py-2">Trạng thái</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              <tr v-if="loadingPage">
                <td class="px-3 py-6 text-center text-slate-500" colspan="8">Đang quét action...</td>
              </tr>
              <tr v-for="row in rows" v-else :key="row.id || row.action_key">
                <td class="px-3 py-2 font-semibold text-slate-700">{{ row.module }}</td>
                <td class="px-3 py-2 text-slate-700">{{ row.action_key }}</td>
                <td class="px-3 py-2 text-slate-700">{{ actionLabel(row.label) }}</td>
                <td class="px-3 py-2 text-slate-500">{{ row.route_name }}</td>
                <td class="px-3 py-2 text-slate-500">{{ row.api_endpoint }}</td>
                <td class="px-3 py-2 text-slate-500">{{ row.permission_key }}</td>
                <td class="max-w-xs truncate px-3 py-2 text-slate-500">{{ row.controller_method }}</td>
                <td class="px-3 py-2"><StatusBadge :status="row.status" /></td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>
    </section>
  </EraLmsLayout>
</template>
