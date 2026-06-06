<script setup>
defineProps({
  open: { type: Boolean, default: false },
  title: { type: String, default: 'Form' },
  loading: { type: Boolean, default: false },
  errors: { type: Object, default: () => ({}) },
})

defineEmits(['save', 'cancel'])
</script>

<template>
  <div v-if="open" class="fixed inset-0 z-40 flex justify-end bg-slate-950/30">
    <aside class="h-full w-full max-w-xl overflow-auto bg-white shadow-xl">
      <div class="border-b border-slate-200 px-5 py-4">
        <h2 class="text-base font-bold text-slate-950">{{ title }}</h2>
      </div>
      <div class="space-y-4 p-5">
        <slot :errors="errors"></slot>
      </div>
      <div class="sticky bottom-0 flex justify-end gap-2 border-t border-slate-200 bg-white px-5 py-4">
        <button class="rounded-md border border-slate-300 px-3 py-2 text-sm font-semibold" :disabled="loading" @click="$emit('cancel')">Hủy</button>
        <button class="rounded-md bg-blue-700 px-3 py-2 text-sm font-semibold text-white disabled:opacity-60" :disabled="loading" @click="$emit('save')">Lưu</button>
      </div>
    </aside>
  </div>
</template>
