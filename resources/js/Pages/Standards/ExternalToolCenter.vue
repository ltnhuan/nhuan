<script setup>
import { onMounted, ref } from 'vue'
import EraLmsLayout from '@/Layouts/EraLmsLayout.vue'

const props = defineProps({ apiHeaders: { type: Object, required: true }, sessionUser: { type: Object, default: null } })
defineEmits(['logout'])
const tools = ref([])
const analytics = ref({ external_tools: {} })
const statusLabels = {
  active: 'Hoạt động',
  inactive: 'Ngưng hoạt động',
  pending: 'Đang chờ',
}

function statusLabel(value) {
  return statusLabels[value] || value || '-'
}

async function load() {
  const [toolResponse, analyticsResponse] = await Promise.all([
    fetch('/api/v1/learning-standards/external-tools', { headers: props.apiHeaders }),
    fetch('/api/v1/learning-standards/analytics', { headers: props.apiHeaders }),
  ])
  tools.value = (await toolResponse.json()).data || []
  analytics.value = await analyticsResponse.json()
}

onMounted(load)
</script>

<template>
  <EraLmsLayout :session-user="sessionUser" @logout="$emit('logout')">
    <template #breadcrumb>Chuẩn học tập / Trung tâm công cụ bên ngoài</template>
    <section class="mx-auto max-w-7xl px-6 py-5">
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-lg font-semibold">Trung tâm công cụ bên ngoài</h1>
          <p class="mt-1 text-sm text-slate-600">Đăng ký công cụ ngoài như Zoom, Teams, Google Meet, phòng lab, công cụ AI và nội dung từ nhà xuất bản.</p>
        </div>
        <button class="rounded-md bg-slate-950 px-3 py-2 text-sm text-white">Thêm công cụ</button>
      </div>
      <div class="mt-5 grid gap-3 md:grid-cols-5">
          <div v-for="(total, category) in analytics.external_tools" :key="category" class="border bg-white p-4"><div class="text-sm capitalize text-slate-600">{{ category }}</div><strong class="mt-2 block">{{ total }}</strong></div>
        </div>
      <div class="mt-5 grid gap-3 md:grid-cols-2 xl:grid-cols-3">
        <article v-for="tool in tools" :key="tool.id" class="border bg-white p-4">
          <div class="flex items-start justify-between gap-3"><div><h2 class="text-sm font-semibold">{{ tool.name }}</h2><p class="mt-1 text-sm text-slate-600">{{ tool.provider }} · {{ tool.category }}</p></div><span class="rounded bg-slate-100 px-2 py-1 text-xs">{{ statusLabel(tool.status) }}</span></div>
          <div class="mt-4 text-sm text-slate-700">{{ tool.launch_url }}</div>
        </article>
      </div>
    </section>
  </EraLmsLayout>
</template>
