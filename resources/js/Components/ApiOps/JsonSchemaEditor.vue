<script setup>
import { ref, watch } from 'vue'

const props = defineProps({
  modelValue: { type: Object, default: () => ({}) },
  label: { type: String, default: 'Schema JSON' },
})
const emit = defineEmits(['update:modelValue'])

const text = ref(JSON.stringify(props.modelValue || {}, null, 2))
const error = ref('')

watch(() => props.modelValue, (value) => {
  text.value = JSON.stringify(value || {}, null, 2)
}, { deep: true })

function apply() {
  try {
    const parsed = JSON.parse(text.value || '{}')
    error.value = ''
    emit('update:modelValue', parsed)
  } catch (exception) {
    error.value = exception.message
  }
}
</script>

<template>
  <label class="block">
    <span class="text-xs font-semibold uppercase text-slate-500">{{ label }}</span>
    <textarea v-model="text" rows="8" class="mt-1 w-full rounded-md border border-slate-300 bg-white px-3 py-2 font-mono text-xs outline-none focus:border-cyan-500 focus:ring-4 focus:ring-cyan-100" @blur="apply"></textarea>
    <span v-if="error" class="mt-1 block text-xs text-rose-600">{{ error }}</span>
  </label>
</template>
