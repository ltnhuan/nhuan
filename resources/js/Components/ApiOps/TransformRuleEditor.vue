<script setup>
import { computed } from 'vue'

const props = defineProps({
  modelValue: { type: Object, default: () => ({ type: 'copy' }) },
})
const emit = defineEmits(['update:modelValue'])

const type = computed({
  get: () => props.modelValue?.type || 'copy',
  set: (value) => emit('update:modelValue', { ...(props.modelValue || {}), type: value }),
})
const value = computed({
  get: () => props.modelValue?.value || '',
  set: (next) => emit('update:modelValue', { ...(props.modelValue || {}), value: next }),
})
</script>

<template>
  <div class="grid gap-2 sm:grid-cols-[1fr_1fr]">
    <label>
      <span class="text-xs font-semibold uppercase text-slate-500">Quy tắc chuyển đổi</span>
      <select v-model="type" class="mt-1 h-10 w-full rounded-md border border-slate-300 bg-white px-2 text-sm">
        <option value="copy">Giữ nguyên</option>
        <option value="uppercase">Viết hoa</option>
        <option value="lowercase">Viết thường</option>
        <option value="integer">Số nguyên</option>
        <option value="float">Số thập phân</option>
        <option value="boolean">Đúng/sai</option>
        <option value="prefix">Thêm tiền tố</option>
        <option value="suffix">Thêm hậu tố</option>
        <option value="default">Dùng mặc định</option>
      </select>
    </label>
    <label>
      <span class="text-xs font-semibold uppercase text-slate-500">Giá trị</span>
      <input v-model="value" class="mt-1 h-10 w-full rounded-md border border-slate-300 px-2 text-sm" />
    </label>
  </div>
</template>
