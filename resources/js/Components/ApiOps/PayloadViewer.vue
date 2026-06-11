<script setup>
import { computed, ref } from 'vue'
import { Clipboard, Check } from '@lucide/vue'

const props = defineProps({
  title: { type: String, default: 'Dữ liệu JSON' },
  payload: { type: [Object, Array, String, Number, Boolean], default: () => ({}) },
})

const copied = ref(false)
const json = computed(() => typeof props.payload === 'string' ? props.payload : JSON.stringify(props.payload ?? {}, null, 2))

async function copyPayload() {
  await navigator.clipboard?.writeText(json.value)
  copied.value = true
  window.setTimeout(() => { copied.value = false }, 1200)
}
</script>

<template>
  <section class="rounded-md border border-slate-200 bg-white">
    <header class="flex items-center justify-between gap-3 border-b border-slate-200 px-3 py-2">
      <h3 class="text-xs font-semibold uppercase text-slate-500">{{ title }}</h3>
      <button class="inline-flex items-center gap-2 rounded-md border border-slate-300 px-2 py-1 text-xs font-semibold text-slate-700 hover:bg-slate-50" @click="copyPayload">
        <Check v-if="copied" class="h-3.5 w-3.5 text-emerald-600" />
        <Clipboard v-else class="h-3.5 w-3.5" />
        {{ copied ? 'Đã sao chép' : 'Sao chép' }}
      </button>
    </header>
    <pre class="max-h-96 overflow-auto p-3 text-xs leading-relaxed text-slate-800">{{ json }}</pre>
  </section>
</template>
