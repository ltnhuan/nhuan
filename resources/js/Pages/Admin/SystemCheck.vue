<script setup>
import { onMounted, ref } from 'vue'
import EraLmsLayout from '@/Layouts/EraLmsLayout.vue'

const props = defineProps({
  sessionUser: { type: Object, default: null },
  apiHeaders: { type: Object, default: () => ({}) },
})

defineEmits(['logout'])

const loading = ref(false)
const result = ref(null)
const lastAction = ref(null)
const actions = [
  ['sync-permissions', 'Sync permissions'],
  ['clear-permission-cache', 'Clear permission cache'],
  ['clear-route-cache', 'Clear route cache'],
  ['clear-config-cache', 'Clear config cache'],
  ['rebuild-menu', 'Rebuild menu'],
  ['grant-full-admin', 'Grant full admin'],
  ['run-smoke-test', 'Run smoke test'],
]

async function api(path, options = {}) {
  const response = await fetch(`/api/v1/admin/lms/${path}`, {
    ...options,
    headers: {
      ...props.apiHeaders,
      ...(options.headers || {}),
    },
  })
  const payload = await response.json()
  if (!response.ok || payload.success === false) {
    throw new Error(payload.message || 'System check API failed.')
  }
  return payload.data
}

async function load() {
  loading.value = true
  try {
    result.value = await api('system-check')
  } finally {
    loading.value = false
  }
}

async function runAction(action) {
  loading.value = true
  try {
    lastAction.value = { action, data: await api(`system-check/actions/${action}`, { method: 'POST' }) }
    await load()
  } finally {
    loading.value = false
  }
}

onMounted(load)
</script>

<template>
  <EraLmsLayout :session-user="sessionUser" @logout="$emit('logout')">
    <template #breadcrumb>Admin / LMS System Check</template>

    <section class="space-y-5">
      <div class="rounded-lg border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-200 bg-slate-950 px-6 py-5 text-white">
          <div class="text-xs font-semibold uppercase tracking-[0.18em] text-cyan-200">EraLMS Diagnostics</div>
          <h1 class="mt-2 text-2xl font-bold">Kiểm tra route, menu, permission, component và API</h1>
          <p class="mt-2 text-sm text-slate-300">Dùng màn này để phát hiện menu chết, quyền thiếu, user chưa có role, component UI thiếu và lỗi health check.</p>
        </div>
        <div class="flex flex-wrap gap-2 p-4">
          <button
            v-for="[action, label] in actions"
            :key="action"
            class="rounded-md border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 disabled:opacity-50"
            :disabled="loading"
            @click="runAction(action)"
          >
            {{ label }}
          </button>
        </div>
      </div>

      <div v-if="result" class="grid gap-4 md:grid-cols-5">
        <div v-for="(value, key) in result.summary" :key="key" class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
          <div class="text-xs font-semibold uppercase text-slate-500">{{ key.replaceAll('_', ' ') }}</div>
          <div class="mt-2 text-2xl font-bold text-slate-900">{{ value }}</div>
        </div>
      </div>

      <div v-if="lastAction" class="rounded-lg border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-800">
        Đã chạy: <strong>{{ lastAction.action }}</strong>
      </div>

      <div v-if="result" class="grid gap-5 xl:grid-cols-2">
        <section
          v-for="section in [
            ['Menu trỏ route lỗi', result.menu_route_errors],
            ['Permission thiếu', result.missing_permissions],
            ['Button/action chưa có API', result.button_action_missing_api],
            ['User không có role', result.users_without_role],
            ['Component UI thiếu', result.missing_ui_components],
            ['API lỗi 403/404/405/500', result.api_issues],
          ]"
          :key="section[0]"
          class="rounded-lg border border-slate-200 bg-white shadow-sm"
        >
          <div class="border-b border-slate-200 px-4 py-3">
            <h2 class="text-sm font-bold text-slate-900">{{ section[0] }}</h2>
          </div>
          <pre class="max-h-80 overflow-auto p-4 text-xs leading-5 text-slate-700">{{ JSON.stringify(section[1], null, 2) }}</pre>
        </section>
      </div>

      <section v-if="result" class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
        <h2 class="text-sm font-bold text-slate-900">Health check</h2>
        <pre class="mt-3 text-xs leading-5 text-slate-700">{{ JSON.stringify(result.health, null, 2) }}</pre>
      </section>
    </section>
  </EraLmsLayout>
</template>
