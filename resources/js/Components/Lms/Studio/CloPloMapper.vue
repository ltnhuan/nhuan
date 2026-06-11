<script setup>
defineProps({ modelValue: { type: Array, default: () => [] } })
</script>
<template><section class="rounded-md border p-3"><div class="text-xs font-bold uppercase text-slate-500">CLO/PLO mapping</div><div class="mt-2 text-xs text-slate-700">{{ modelValue.length }} outcome(s)</div></section></template>
