<script setup>
defineProps({ status: { type: String, default: 'OK' } })
</script>

<template>
  <span
    class="inline-flex rounded-full px-2 py-1 text-xs font-bold"
    :class="status === 'OK' ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700'"
  >
    {{ status }}
  </span>
</template>
