<script setup>
import { ChevronDown, ChevronUp, Plus } from '@lucide/vue'
import UnitCard from './UnitCard.vue'

defineProps({ section: Object, selectedUnitId: Number })
defineEmits(['select-unit', 'add-unit', 'move'])
</script>

<template>
  <section class="rounded-md border border-slate-200 bg-white p-3 shadow-sm">
    <div class="flex items-center justify-between gap-2">
      <div class="min-w-0">
        <div class="truncate text-sm font-bold text-slate-900">{{ section.title }}</div>
        <div class="mt-1 text-[11px] text-slate-500">{{ section.children?.length || 0 }} bài học · {{ section.status || 'Chưa bắt đầu' }}</div>
      </div>
      <div class="flex gap-1">
        <button class="grid h-7 w-7 place-items-center rounded border border-slate-200 bg-white text-slate-500 hover:bg-slate-50" title="Đưa lên" @click="$emit('move', { section, direction: 'up' })"><ChevronUp class="h-3.5 w-3.5" /></button>
        <button class="grid h-7 w-7 place-items-center rounded border border-slate-200 bg-white text-slate-500 hover:bg-slate-50" title="Đưa xuống" @click="$emit('move', { section, direction: 'down' })"><ChevronDown class="h-3.5 w-3.5" /></button>
      </div>
    </div>
    <div class="mt-2 space-y-1">
      <UnitCard
        v-for="unit in section.children"
        :key="unit.id"
        :unit="unit"
        :active="selectedUnitId === unit.id"
        @select="$emit('select-unit', unit)"
      />
    </div>
    <button class="mt-2 inline-flex h-9 w-full items-center justify-center gap-2 rounded-md border border-slate-300 bg-white px-2 text-[11px] font-semibold text-slate-700 hover:bg-slate-50" @click="$emit('add-unit')">
      <Plus class="h-3.5 w-3.5" />Thêm bài học
    </button>
  </section>
</template>
