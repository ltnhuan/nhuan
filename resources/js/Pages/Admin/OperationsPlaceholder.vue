<script setup>
import EraLmsLayout from '@/Layouts/EraLmsLayout.vue'
import ActionBar from '@/Components/Lms/ActionBar.vue'
import { useLmsAction } from '@/composables/useLmsAction'

const props = defineProps({
  sessionUser: { type: Object, default: null },
  apiHeaders: { type: Object, default: () => ({}) },
  title: { type: String, default: 'Chức năng quản trị' },
  subtitle: { type: String, default: 'Màn hình vận hành đã được route đúng và có action diagnostic thật.' },
})

defineEmits(['logout'])

const { loading, toast, runAction } = useLmsAction(props.apiHeaders)

const actions = [
  { action_key: 'action.scan', label: 'Quét hành động', route: '/api/v1/admin/lms/action-check/scan', method: 'POST' },
  { action_key: 'action.sync', label: 'Đồng bộ hành động', route: '/api/v1/admin/lms/action-check/sync-actions', method: 'POST', confirm_required: true, confirm_message: 'Đồng bộ danh mục hành động?' },
  { action_key: 'action.fix_permissions', label: 'Sửa quyền', route: '/api/v1/admin/lms/action-check/fix-permissions', method: 'POST', confirm_required: true, confirm_message: 'Tạo các quyền còn thiếu?' },
  { action_key: 'action.smoke', label: 'Chạy kiểm tra nhanh', route: '/api/v1/admin/lms/action-check/smoke-test', method: 'POST', confirm_required: true, confirm_message: 'Chạy smoke test action flow?' },
  { action_key: 'moodle.parity.report', label: 'Báo cáo tương thích Moodle', route: '/api/v1/admin/lms/moodle-parity', method: 'GET' },
  { action_key: 'moodle.parity.sync', label: 'Đồng bộ tương thích Moodle', route: '/api/v1/admin/lms/moodle-parity/sync', method: 'POST', confirm_required: true, confirm_message: 'Đồng bộ action, quyền và menu theo Moodle parity?' },
]

async function run(action) {
  await runAction({
    actionKey: action.action_key,
    url: action.route,
    method: action.method,
    confirm: action.confirm_required,
    confirmMessage: action.confirm_message,
  })
}
</script>

<template>
  <EraLmsLayout :session-user="sessionUser" @logout="$emit('logout')">
    <template #breadcrumb>Admin / {{ title }}</template>
    <section class="space-y-5">
      <div v-if="toast.show" class="rounded-md px-4 py-3 text-sm font-semibold" :class="toast.type === 'success' ? 'bg-emerald-50 text-emerald-700' : 'bg-red-50 text-red-700'">
        {{ toast.message }}
      </div>

      <div class="rounded-lg border border-slate-200 bg-white shadow-sm">
        <div class="bg-slate-950 px-6 py-5 text-white">
          <h1 class="text-2xl font-bold">{{ title }}</h1>
          <p class="mt-2 text-sm text-slate-300">{{ subtitle }}</p>
        </div>
        <div class="space-y-4 p-4">
          <ActionBar :actions="actions" :loading-map="loading" @run="run" />
          <a class="inline-flex rounded-md border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50" href="/admin/lms/action-check">Mở kiểm tra hành động</a>
        </div>
      </div>
    </section>
  </EraLmsLayout>
</template>
