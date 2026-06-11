<script setup>
defineProps({
  message: { type: String, default: 'Chưa có dữ liệu đủ để tính' },
})
</script>

<template>
  <div class="rounded-md border border-dashed border-slate-300 bg-slate-50 p-4 text-sm text-slate-600">
    {{ message }}
  </div>
</template>
