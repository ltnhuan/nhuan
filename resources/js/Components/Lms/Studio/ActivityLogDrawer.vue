<script setup>
defineProps({ open: Boolean, logs: { type: Array, default: () => [] } })
defineEmits(['close'])
</script>
<template><div v-if="open" class="fixed inset-0 z-40 flex justify-end bg-slate-950/30"><aside class="w-full max-w-lg bg-white p-4"><button class="float-right rounded border px-2 py-1 text-xs" @click="$emit('close')">Đóng</button><h2 class="font-bold">Lịch sử</h2><div class="mt-4 space-y-2 text-sm"><div v-for="log in logs" :key="log.id" class="border-l-2 border-blue-700 pl-3">{{ log.action }} · {{ log.note }}</div></div></aside></div></template>
