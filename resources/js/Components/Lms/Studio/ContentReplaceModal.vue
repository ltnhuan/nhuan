<script setup>
defineProps({ open: Boolean, items: { type: Array, default: () => [] } })
defineEmits(['close', 'select'])
</script>
<template><div v-if="open" class="fixed inset-0 z-50 grid place-items-center bg-slate-950/40 p-4"><section class="w-full max-w-xl rounded-lg bg-white p-4"><div class="flex justify-between"><h2 class="font-bold">Thay thế học liệu</h2><button class="rounded border px-2 py-1 text-xs" @click="$emit('close')">Đóng</button></div><div class="mt-4 max-h-96 overflow-auto"><button v-for="item in items" :key="item.id" class="mb-2 block w-full rounded border px-3 py-2 text-left text-sm hover:bg-blue-50" @click="$emit('select', item)">{{ item.title }} · {{ item.item_type }}</button></div></section></div></template>
