<script setup>
import { onMounted, ref } from 'vue'
import EraLmsLayout from '@/Layouts/EraLmsLayout.vue'

const props = defineProps({ apiHeaders: { type: Object, required: true }, sessionUser: { type: Object, default: null } })
defineEmits(['logout'])
const packages = ref([])
const analytics = ref({ scorm_usage: {} })

async function load() {
  const [packageResponse, analyticsResponse] = await Promise.all([
    fetch('/api/v1/learning-standards/scorm/packages', { headers: props.apiHeaders }),
    fetch('/api/v1/learning-standards/analytics', { headers: props.apiHeaders }),
  ])
  packages.value = (await packageResponse.json()).data || []
  analytics.value = await analyticsResponse.json()
}

onMounted(load)
</script>

<template>
  <EraLmsLayout :session-user="sessionUser" @logout="$emit('logout')">
    <template #breadcrumb>Learning Standards / SCORM Manager</template>
    <section class="mx-auto max-w-7xl px-6 py-5">
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-lg font-semibold">SCORM Manager</h1>
          <p class="mt-1 text-sm text-slate-600">Quản lý SCORM 1.2, SCORM 2004 package, launch và tracking progress, score, completion.</p>
        </div>
        <button class="rounded-md bg-slate-950 px-3 py-2 text-sm text-white">Upload ZIP</button>
      </div>
      <div class="mt-5 grid gap-3 md:grid-cols-4">
        <div class="border bg-white p-4"><div class="text-sm text-slate-600">Packages</div><strong class="mt-2 block">{{ analytics.scorm_usage?.packages || 0 }}</strong></div>
        <div class="border bg-white p-4"><div class="text-sm text-slate-600">Attempts</div><strong class="mt-2 block">{{ analytics.scorm_usage?.attempts || 0 }}</strong></div>
        <div class="border bg-white p-4"><div class="text-sm text-slate-600">Completed</div><strong class="mt-2 block">{{ analytics.scorm_usage?.completed_attempts || 0 }}</strong></div>
        <div class="border bg-white p-4"><div class="text-sm text-slate-600">Average score</div><strong class="mt-2 block">{{ analytics.scorm_usage?.average_score || 0 }}</strong></div>
      </div>
      <div class="mt-5 overflow-hidden border bg-white">
        <table class="w-full text-left text-sm">
          <thead class="bg-slate-100 text-xs uppercase text-slate-600"><tr><th class="p-3">Package</th><th class="p-3">Standard</th><th class="p-3">Launch</th><th class="p-3">Status</th></tr></thead>
          <tbody><tr v-for="item in packages" :key="item.id" class="border-t"><td class="p-3 font-medium">{{ item.title }}</td><td class="p-3">{{ item.standard }}</td><td class="p-3">{{ item.launch_path }}</td><td class="p-3">{{ item.status }}</td></tr></tbody>
        </table>
      </div>
    </section>
  </EraLmsLayout>
</template>
