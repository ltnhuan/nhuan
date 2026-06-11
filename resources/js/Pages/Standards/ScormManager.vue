<script setup>
import { onMounted, ref } from 'vue'
import EraLmsLayout from '@/Layouts/EraLmsLayout.vue'

const props = defineProps({ apiHeaders: { type: Object, required: true }, sessionUser: { type: Object, default: null } })
defineEmits(['logout'])
const packages = ref([])
const analytics = ref({ scorm_usage: {} })
const statusLabels = {
  active: 'Hoạt động',
  ready: 'Sẵn sàng',
  draft: 'Bản nháp',
  pending: 'Đang chờ',
  processing: 'Đang xử lý',
  completed: 'Hoàn tất',
  failed: 'Thất bại',
}

function statusLabel(value) {
  return statusLabels[value] || value || '-'
}

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
    <template #breadcrumb>Chuẩn học tập / Quản lý SCORM</template>
    <section class="mx-auto max-w-7xl px-6 py-5">
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-lg font-semibold">Quản lý SCORM</h1>
          <p class="mt-1 text-sm text-slate-600">Quản lý gói SCORM 1.2/2004, chạy học liệu và theo dõi tiến độ, điểm số, hoàn thành.</p>
        </div>
        <button class="rounded-md bg-slate-950 px-3 py-2 text-sm text-white">Tải ZIP lên</button>
      </div>
      <div class="mt-5 grid gap-3 md:grid-cols-4">
        <div class="border bg-white p-4"><div class="text-sm text-slate-600">Gói học liệu</div><strong class="mt-2 block">{{ analytics.scorm_usage?.packages || 0 }}</strong></div>
        <div class="border bg-white p-4"><div class="text-sm text-slate-600">Lượt mở</div><strong class="mt-2 block">{{ analytics.scorm_usage?.attempts || 0 }}</strong></div>
        <div class="border bg-white p-4"><div class="text-sm text-slate-600">Hoàn thành</div><strong class="mt-2 block">{{ analytics.scorm_usage?.completed_attempts || 0 }}</strong></div>
        <div class="border bg-white p-4"><div class="text-sm text-slate-600">Điểm trung bình</div><strong class="mt-2 block">{{ analytics.scorm_usage?.average_score || 0 }}</strong></div>
      </div>
      <div class="mt-5 overflow-hidden border bg-white">
        <table class="w-full text-left text-sm">
          <thead class="bg-slate-100 text-xs uppercase text-slate-600"><tr><th class="p-3">Gói</th><th class="p-3">Chuẩn</th><th class="p-3">Nguồn mở</th><th class="p-3">Trạng thái</th></tr></thead>
          <tbody><tr v-for="item in packages" :key="item.id" class="border-t"><td class="p-3 font-medium">{{ item.title }}</td><td class="p-3">{{ item.standard }}</td><td class="p-3">{{ item.launch_path }}</td><td class="p-3">{{ statusLabel(item.status) }}</td></tr></tbody>
        </table>
      </div>
    </section>
  </EraLmsLayout>
</template>
