<script setup>
import { CheckCircle2, Circle, GripVertical } from '@lucide/vue'

defineProps({ unit: Object, active: Boolean })
defineEmits(['select'])
</script>

<template>
  <button class="w-full rounded-md border px-3 py-2 text-left text-xs transition" :class="active ? 'border-blue-200 bg-blue-50 text-blue-900 shadow-sm' : 'border-transparent bg-white text-slate-700 hover:border-slate-200 hover:bg-slate-50'" @click="$emit('select')">
    <div class="flex items-center gap-2">
      <GripVertical class="h-3.5 w-3.5 shrink-0 text-slate-300" />
      <span class="truncate font-semibold">{{ unit.title }}</span>
      <CheckCircle2 v-if="unit.status === 'published' || unit.status === 'configured'" class="ml-auto h-4 w-4 shrink-0 text-emerald-500" />
      <Circle v-else class="ml-auto h-4 w-4 shrink-0 text-amber-500" />
    </div>
    <div class="mt-1 pl-5 text-[11px] text-slate-500">{{ unit.components?.length || 0 }} hoạt động</div>
  </button>
</template>
