<script setup>
import { computed, ref } from 'vue'

const props = defineProps({
  tabs: { type: Array, default: () => [] },
  modelValue: { type: String, default: '' },
})

const emit = defineEmits(['update:modelValue'])
const visited = ref(new Set([props.modelValue || props.tabs[0]?.key].filter(Boolean)))
const activeKey = computed({
  get: () => props.modelValue || props.tabs[0]?.key,
  set: (value) => {
    visited.value.add(value)
    emit('update:modelValue', value)
  },
})
</script>

<template>
  <div>
    <div class="flex gap-1 border-b border-slate-200">
      <button
        v-for="tab in tabs"
        :key="tab.key"
        class="px-3 py-2 text-sm font-medium"
        :class="activeKey === tab.key ? 'border-b-2 border-blue-700 text-blue-700' : 'text-slate-600 hover:text-slate-950'"
        type="button"
        @click="activeKey = tab.key"
      >
        {{ tab.label }}
      </button>
    </div>
    <div class="pt-4">
      <template v-for="tab in tabs" :key="tab.key">
        <section v-show="activeKey === tab.key" v-if="visited.has(tab.key)">
          <slot :name="tab.key"></slot>
        </section>
      </template>
    </div>
  </div>
</template>
