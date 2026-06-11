<script setup>
import { onMounted, ref } from 'vue'
import EraLmsLayout from '@/Layouts/EraLmsLayout.vue'

const props = defineProps({ apiHeaders: { type: Object, required: true }, sessionUser: { type: Object, default: null } })
defineEmits(['logout'])
const registrations = ref([])
const launches = ref([])
const statusLabels = {
  active: 'Hoạt động',
  pending: 'Đang chờ',
  completed: 'Hoàn tất',
  failed: 'Thất bại',
}

function statusLabel(value) {
  return statusLabels[value] || value || '-'
}

async function load() {
  const [registrationResponse, launchResponse] = await Promise.all([
    fetch('/api/v1/learning-standards/lti/registrations', { headers: props.apiHeaders }),
    fetch('/api/v1/learning-standards/lti/launches', { headers: props.apiHeaders }),
  ])
  registrations.value = (await registrationResponse.json()).data || []
  launches.value = (await launchResponse.json()).data || []
}

onMounted(load)
</script>

<template>
  <EraLmsLayout :session-user="sessionUser" @logout="$emit('logout')">
    <template #breadcrumb>Chuẩn học tập / Đăng ký LTI</template>
    <section class="mx-auto max-w-7xl px-6 py-5">
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-lg font-semibold">Đăng ký LTI</h1>
          <p class="mt-1 text-sm text-slate-600">EraLMS kết nối với nhà cung cấp công cụ học tập qua chuẩn LTI 1.3.</p>
        </div>
        <button class="rounded-md bg-slate-950 px-3 py-2 text-sm text-white">Đăng ký công cụ</button>
      </div>
      <div class="mt-5 grid gap-4 lg:grid-cols-[1fr_360px]">
        <div class="overflow-hidden border bg-white">
          <table class="w-full text-left text-sm">
            <thead class="bg-slate-100 text-xs uppercase text-slate-600"><tr><th class="p-3">Nhà cung cấp</th><th class="p-3">Định danh phát hành</th><th class="p-3">Mã ứng dụng</th><th class="p-3">Trạng thái</th></tr></thead>
            <tbody><tr v-for="item in registrations" :key="item.id" class="border-t"><td class="p-3 font-medium">{{ item.name }}</td><td class="p-3">{{ item.issuer }}</td><td class="p-3">{{ item.client_id }}</td><td class="p-3">{{ statusLabel(item.status) }}</td></tr></tbody>
          </table>
        </div>
        <aside class="border bg-white p-4">
          <h2 class="text-sm font-semibold">Lần gọi gần nhất</h2>
          <div class="mt-4 space-y-3 text-sm">
            <div v-for="launch in launches" :key="launch.id" class="border-b pb-2"><div class="font-medium">{{ launch.target_link_uri }}</div><div class="text-slate-500">{{ statusLabel(launch.status) }} · {{ launch.launched_at }}</div></div>
          </div>
        </aside>
      </div>
    </section>
  </EraLmsLayout>
</template>
