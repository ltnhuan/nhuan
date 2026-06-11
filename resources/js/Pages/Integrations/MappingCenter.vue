<script setup>
import { computed, onMounted, ref } from 'vue'
import EraLmsLayout from '@/Layouts/EraLmsLayout.vue'

const props = defineProps({
  apiHeaders: { type: Object, required: true },
  sessionUser: { type: Object, default: null },
})

const rows = ref([])
const loading = ref(true)
const error = ref('')
const filters = ref({ entity_type: '', mapping_status: '', q: '' })

const visibleRows = computed(() => {
  const query = filters.value.q.trim().toLowerCase()
  if (!query) return rows.value
  return rows.value.filter((row) => [row.local_id, row.external_id, row.external_code].some((value) => String(value || '').toLowerCase().includes(query)))
})

async function load() {
  loading.value = true
  error.value = ''
  const params = new URLSearchParams({ per_page: '100' })
  if (filters.value.entity_type) params.set('entity_type', filters.value.entity_type)
  if (filters.value.mapping_status) params.set('mapping_status', filters.value.mapping_status)

  try {
    const response = await fetch(`/api/v1/integrations/mappings?${params}`, { headers: props.apiHeaders })
    const data = await response.json()
    if (!response.ok) throw new Error(data.message || 'Không tải được mapping SIS.')
    rows.value = data.data || []
  } catch (exception) {
    error.value = exception.message
  } finally {
    loading.value = false
  }
}

onMounted(load)
</script>

<template>
  <EraLmsLayout :session-user="sessionUser">
    <template #breadcrumb>Trung tâm tích hợp / Bản đồ ánh xạ</template>
    <section class="mx-auto max-w-7xl px-4 py-5 sm:px-6">
      <div class="flex flex-wrap items-center justify-between gap-3">
        <h1 class="text-lg font-semibold">Trung tâm ánh xạ</h1>
        <button class="rounded-md border px-3 py-2 text-sm" :disabled="loading" @click="load">Tải lại</button>
      </div>
      <div class="mt-4 grid gap-3 md:grid-cols-4">
        <select v-model="filters.entity_type" class="rounded-md border px-3 py-2 text-sm" @change="load">
          <option value="">Tất cả đối tượng</option><option>học viên</option><option>lớp</option><option>khóa học</option><option>ghi danh</option><option>điểm</option><option>điểm danh</option>
        </select>
        <select v-model="filters.mapping_status" class="rounded-md border px-3 py-2 text-sm" @change="load">
          <option value="">Tất cả trạng thái</option><option>đang hoạt động</option><option>xung đột</option><option>ngừng hoạt động</option>
        </select>
        <input v-model="filters.q" class="rounded-md border px-3 py-2 text-sm md:col-span-2" placeholder="Tìm ID nội bộ/ID bên ngoài" />
      </div>
      <div v-if="error" class="mt-4 rounded-md border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">{{ error }}</div>
      <div class="mt-5 overflow-hidden border bg-white">
        <table class="w-full text-left text-sm">
          <thead class="bg-slate-50 text-xs uppercase text-slate-500"><tr><th class="px-4 py-3">Đối tượng</th><th class="px-4 py-3">ID nội bộ</th><th class="px-4 py-3">ID bên ngoài</th><th class="px-4 py-3">Mã bên ngoài</th><th class="px-4 py-3">Trạng thái</th></tr></thead>
          <tbody class="divide-y">
            <tr v-if="loading"><td class="px-4 py-6 text-slate-500" colspan="5">Đang tải dữ liệu...</td></tr>
            <tr v-else-if="!visibleRows.length"><td class="px-4 py-6 text-slate-500" colspan="5">Không có mapping phù hợp.</td></tr>
            <tr v-for="row in visibleRows" v-else :key="row.id">
              <td class="px-4 py-3">{{ row.entity_type }}</td><td class="px-4 py-3">{{ row.local_id }}</td><td class="px-4 py-3">{{ row.external_id }}</td><td class="px-4 py-3">{{ row.external_code || '-' }}</td><td class="px-4 py-3">{{ row.mapping_status }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>
  </EraLmsLayout>
</template>
