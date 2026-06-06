<script setup>
defineProps({
  open: { type: Boolean, default: false },
  title: { type: String, default: 'Xác nhận thao tác' },
  message: { type: String, default: 'Bạn chắc chắn muốn tiếp tục?' },
  loading: { type: Boolean, default: false },
})

defineEmits(['confirm', 'cancel'])
</script>

<template>
  <div v-if="open" class="fixed inset-0 z-50 grid place-items-center bg-slate-950/40 p-4">
    <section class="w-full max-w-md rounded-lg bg-white shadow-xl">
      <div class="border-b border-slate-200 px-5 py-4">
        <h2 class="text-base font-bold text-slate-950">{{ title }}</h2>
      </div>
      <p class="px-5 py-4 text-sm text-slate-600">{{ message }}</p>
      <div class="flex justify-end gap-2 border-t border-slate-200 px-5 py-4">
        <button class="rounded-md border border-slate-300 px-3 py-2 text-sm font-semibold" :disabled="loading" @click="$emit('cancel')">Hủy</button>
        <button class="rounded-md bg-red-600 px-3 py-2 text-sm font-semibold text-white disabled:opacity-60" :disabled="loading" @click="$emit('confirm')">Xác nhận</button>
      </div>
    </section>
  </div>
</template>
