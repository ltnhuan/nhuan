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
  { action_key: 'action.scan', label: 'Scan actions', route: '/api/v1/admin/lms/action-check/scan', method: 'POST' },
  { action_key: 'action.sync', label: 'Sync actions', route: '/api/v1/admin/lms/action-check/sync-actions', method: 'POST', confirm_required: true, confirm_message: 'Đồng bộ action registry?' },
  { action_key: 'action.fix_permissions', label: 'Fix missing permissions', route: '/api/v1/admin/lms/action-check/fix-permissions', method: 'POST', confirm_required: true, confirm_message: 'Tạo các permission còn thiếu?' },
  { action_key: 'action.clear_cache', label: 'Clear cache', route: '/api/v1/admin/lms/action-check/clear-cache', method: 'POST', confirm_required: true, confirm_message: 'Xóa cache route/config/app?' },
  { action_key: 'action.smoke', label: 'Run smoke test', route: '/api/v1/admin/lms/action-check/smoke-test', method: 'POST', confirm_required: true, confirm_message: 'Chạy smoke test action flow?' },
]

const issueRows = computed(() => rows.value.filter((row) => row.status !== 'OK'))

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
    <template #breadcrumb>Admin / LMS Action Check</template>

    <section class="space-y-5">
      <div v-if="toast.show" class="rounded-md px-4 py-3 text-sm font-semibold" :class="toast.type === 'success' ? 'bg-emerald-50 text-emerald-700' : 'bg-red-50 text-red-700'">
        {{ toast.message }}
      </div>

      <div class="rounded-lg border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-200 bg-slate-950 px-6 py-5 text-white">
          <div class="text-xs font-semibold uppercase tracking-[0.18em] text-cyan-200">EraLMS Action Flow</div>
          <h1 class="mt-2 text-2xl font-bold">System Action Check</h1>
          <p class="mt-2 text-sm text-slate-300">Quét button action, API route, controller, permission và frontend handler để phát hiện flow bị đứt.</p>
        </div>
        <div class="p-4">
          <ActionBar :actions="actions" :loading-map="loading" @run="run" />
        </div>
      </div>

      <div class="grid gap-4 md:grid-cols-4">
        <div v-for="(value, key) in summary" :key="key" class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
          <div class="text-xs font-semibold uppercase text-slate-500">{{ key.replaceAll('_', ' ') }}</div>
          <div class="mt-2 text-2xl font-bold text-slate-950">{{ value }}</div>
        </div>
      </div>

      <section class="rounded-lg border border-slate-200 bg-white shadow-sm">
        <div class="flex items-center justify-between border-b border-slate-200 px-4 py-3">
          <h2 class="text-sm font-bold text-slate-950">Action registry</h2>
          <span class="text-xs text-slate-500">{{ issueRows.length }} issue(s)</span>
        </div>
        <div class="overflow-auto">
          <table class="w-full text-left text-xs">
            <thead class="bg-slate-50 uppercase text-slate-500">
              <tr>
                <th class="px-3 py-2">Module</th>
                <th class="px-3 py-2">Action key</th>
                <th class="px-3 py-2">Button label</th>
                <th class="px-3 py-2">Route</th>
                <th class="px-3 py-2">API endpoint</th>
                <th class="px-3 py-2">Permission</th>
                <th class="px-3 py-2">Controller</th>
                <th class="px-3 py-2">Status</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              <tr v-if="loadingPage">
                <td class="px-3 py-6 text-center text-slate-500" colspan="8">Đang quét action...</td>
              </tr>
              <tr v-for="row in rows" v-else :key="row.id || row.action_key">
                <td class="px-3 py-2 font-semibold text-slate-700">{{ row.module }}</td>
                <td class="px-3 py-2 text-slate-700">{{ row.action_key }}</td>
                <td class="px-3 py-2 text-slate-700">{{ row.label }}</td>
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
