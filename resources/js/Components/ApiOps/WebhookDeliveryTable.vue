<script setup>
import ApiHealthBadge from './ApiHealthBadge.vue'

defineProps({
  deliveries: { type: Array, default: () => [] },
})
</script>

<template>
  <div class="overflow-x-auto rounded-md border border-slate-200 bg-white">
    <table class="min-w-full text-left text-sm">
      <thead class="bg-slate-50 text-xs uppercase text-slate-500">
        <tr>
          <th class="px-3 py-2">ID</th>
          <th class="px-3 py-2">Webhook</th>
          <th class="px-3 py-2">Sự kiện</th>
          <th class="px-3 py-2">Trạng thái</th>
          <th class="px-3 py-2">Phản hồi</th>
          <th class="px-3 py-2">Số lần thử</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-slate-100">
        <tr v-for="delivery in deliveries" :key="delivery.id" class="hover:bg-slate-50">
          <td class="px-3 py-2 font-mono text-xs">{{ delivery.id }}</td>
          <td class="px-3 py-2">{{ delivery.webhook_endpoint_id }}</td>
          <td class="px-3 py-2">{{ delivery.api_event_id }}</td>
          <td class="px-3 py-2"><ApiHealthBadge :status="delivery.status" /></td>
          <td class="px-3 py-2">{{ delivery.response_status || '-' }}</td>
          <td class="px-3 py-2">{{ delivery.attempts }}</td>
        </tr>
      </tbody>
    </table>
  </div>
</template>
