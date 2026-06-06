<script setup>
defineProps({ checklist: { type: Object, default: () => ({ groups: {} }) } })
</script>
<template>
  <section>
    <div class="space-y-2">
      <div v-for="(items, group) in checklist?.groups || {}" :key="group">
        <div class="text-[11px] font-bold text-slate-500">{{ group }}</div>
        <div v-for="item in items" :key="item.key" class="mt-1 flex items-center gap-2 text-xs">
          <span :class="item.passed ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700'" class="grid h-4 w-4 place-items-center rounded-full text-[10px] font-bold">{{ item.passed ? '✓' : '!' }}</span>
          <span>{{ item.label }}</span>
        </div>
      </div>
    </div>
  </section>
</template>
