<script setup>
defineProps({ type: { type: String, default: 'button' } })
</script>

<template>
  <button :type="type" class="inline-flex h-10 items-center justify-center rounded-md bg-slate-950 px-4 text-sm font-semibold text-white transition hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-50">
    <slot />
  </button>
</template>
