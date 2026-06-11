<script setup>
import { computed, inject } from 'vue'
import { Activity, ClipboardList, Database, FileJson, GitBranch, HeartPulse, Network, Play, RadioTower, Repeat, Webhook } from '@lucide/vue'
import EraLmsLayout from '@/Layouts/EraLmsLayout.vue'

defineProps({
  sessionUser: { type: Object, default: null },
  title: { type: String, default: 'Trung tâm vận hành API' },
  subtitle: { type: String, default: 'Quản lý tập trung hệ thống API, gateway, webhook, mapping, event, health, contract và console kiểm thử.' },
})

const currentPath = window.location.pathname
const navigateTo = inject('navigateTo', (url) => { window.location.href = url })
const nav = [
  { label: 'Tổng quan', route: '/admin/api-ops', icon: Activity },
  { label: 'Danh bạ API', route: '/admin/api-ops/registry', icon: Network },
  { label: 'Nhật ký gọi API', route: '/admin/api-ops/requests', icon: ClipboardList },
  { label: 'Luồng sự kiện', route: '/admin/api-ops/events', icon: GitBranch },
  { label: 'Webhook', route: '/admin/api-ops/webhooks', icon: Webhook },
  { label: 'Ánh xạ dữ liệu', route: '/admin/api-ops/mappings', icon: Repeat },
  { label: 'Ánh xạ định danh', route: '/admin/api-ops/entity-mappings', icon: Database },
  { label: 'Công việc sync', route: '/admin/api-ops/sync-jobs', icon: RadioTower },
  { label: 'Sức khỏe API', route: '/admin/api-ops/health', icon: HeartPulse },
  { label: 'Kiểm thử API', route: '/admin/api-ops/console', icon: Play },
  { label: 'Hợp đồng dữ liệu', route: '/admin/api-ops/contracts', icon: FileJson },
]

const activeRoute = computed(() => nav
  .map((item) => item.route)
  .filter((route) => route === '/admin/api-ops' ? currentPath === route : currentPath.startsWith(route))
  .sort((a, b) => b.length - a.length)[0] || '/admin/api-ops')

function navigate(route) {
  navigateTo(route)
}
</script>

<template>
  <EraLmsLayout :session-user="sessionUser">
    <template #breadcrumb>Trung tâm vận hành API</template>
    <section class="space-y-4">
      <header class="rounded-md border border-slate-200 bg-white px-4 py-4">
        <div class="flex flex-wrap items-start justify-between gap-3">
          <div class="min-w-0">
            <h1 class="text-xl font-bold text-slate-950">{{ title }}</h1>
            <p class="mt-1 max-w-4xl text-sm text-slate-600">{{ subtitle }}</p>
          </div>
          <div class="rounded-md border border-cyan-200 bg-cyan-50 px-3 py-2 text-xs font-semibold text-cyan-800">Gateway API nội bộ</div>
        </div>
        <nav class="mt-4 flex gap-2 overflow-x-auto pb-1">
          <button
            v-for="item in nav"
            :key="item.route"
            class="inline-flex h-10 shrink-0 items-center gap-2 rounded-md border px-3 text-sm font-semibold"
            :class="activeRoute === item.route ? 'border-slate-950 bg-slate-950 text-white' : 'border-slate-300 bg-white text-slate-700 hover:bg-slate-50'"
            @click="navigate(item.route)"
          >
            <component :is="item.icon" class="h-4 w-4" />
            {{ item.label }}
          </button>
        </nav>
      </header>

      <slot />
    </section>
  </EraLmsLayout>
</template>
