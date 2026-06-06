<script setup>
defineProps({ modelValue: { type: Object, default: () => ({ type: 'view' }) } })
</script>
<template><section class="rounded-md border p-3"><div class="text-xs font-bold uppercase text-slate-500">Completion rule</div><div class="mt-2 text-xs text-slate-700">{{ modelValue.type || 'view' }}</div></section></template>
