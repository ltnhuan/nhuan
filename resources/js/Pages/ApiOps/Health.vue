<script setup>
import { computed, onMounted, ref } from 'vue'
import ApiOpsShell from '@/Components/ApiOps/ApiOpsShell.vue'
import ApiStatusCard from '@/Components/ApiOps/ApiStatusCard.vue'
import ApiLatencyChart from '@/Components/ApiOps/ApiLatencyChart.vue'

const props = defineProps({
  apiHeaders: { type: Object, required: true },
  sessionUser: { type: Object, default: null },
})

const health = ref({ cards: {}, charts: {} })
const notice = ref('')

const cards = computed(() => [
  ['Đang ổn định', health.value.cards.healthy || 0, 'Hệ thống/endpoint', 'green'],
  ['Cần theo dõi', health.value.cards.degraded || 0, 'Cần rà soát', 'amber'],
  ['Mất kết nối', health.value.cards.down || 0, 'Nghiêm trọng', 'red'],
  ['Độ trễ TB', `${health.value.cards.average_latency || 0}ms`, 'Lần kiểm tra mới nhất', 'blue'],
])

async function load() {
  const response = await fetch('/api/v1/api-ops/health', { headers: props.apiHeaders })
  health.value = (await response.json()).data || { cards: {}, charts: {} }
}

async function checkNow() {
  const response = await fetch('/api/v1/api-ops/health/check-now', { method: 'POST', headers: props.apiHeaders })
  await response.json()
  notice.value = 'Đã tạo snapshot kiểm tra sức khỏe API.'
  await load()
}

onMounted(load)
</script>

<template>
  <ApiOpsShell :session-user="sessionUser" title="Theo dõi sức khỏe API" subtitle="Theo dõi tình trạng hệ thống và endpoint, độ trễ, tỷ lệ lỗi, lần thành công/lỗi gần nhất và kiểm tra thủ công.">
    <div class="flex justify-end"><button class="rounded-md bg-slate-950 px-3 py-2 text-sm font-semibold text-white" @click="checkNow">Kiểm tra ngay</button></div>
    <div v-if="notice" class="rounded-md border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-700">{{ notice }}</div>
    <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4"><ApiStatusCard v-for="card in cards" :key="card[0]" :label="card[0]" :value="card[1]" :meta="card[2]" :tone="card[3]" /></div>
    <ApiLatencyChart :items="health.charts.latency_trend || []" />
  </ApiOpsShell>
</template>
