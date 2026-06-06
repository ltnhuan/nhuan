<script setup>
import { computed, ref } from 'vue'
import { Plus, Search } from '@lucide/vue'
import SectionCard from './SectionCard.vue'

const props = defineProps({ outline: { type: Array, default: () => [] }, selectedUnitId: Number })
defineEmits(['select-unit', 'add-section', 'add-unit', 'rename', 'duplicate', 'delete', 'move'])

const search = ref('')
const filtered = computed(() => props.outline.filter((section) => !search.value || JSON.stringify(section).toLowerCase().includes(search.value.toLowerCase())))
</script>

<template>
  <aside class="w-[300px] shrink-0 border-r border-slate-200 bg-white">
    <div class="border-b border-slate-200 p-4">
      <h2 class="text-sm font-bold uppercase text-slate-950">1. Chọn bài học</h2>
      <div class="relative mt-3">
        <Search class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
        <input v-model="search" class="h-10 w-full rounded-md border border-slate-300 bg-slate-50 pl-9 pr-3 text-sm outline-none focus:border-blue-500 focus:bg-white focus:ring-4 focus:ring-blue-100" placeholder="Tìm chương / bài học" />
      </div>
      <div class="mt-3 grid grid-cols-2 gap-2">
        <button class="inline-flex h-10 items-center justify-center gap-2 rounded-md border border-slate-300 bg-white px-3 text-xs font-semibold text-slate-700 hover:bg-slate-50" @click="$emit('add-section')">
          <Plus class="h-4 w-4" />Thêm chương
        </button>
        <button class="inline-flex h-10 items-center justify-center gap-2 rounded-md border border-slate-300 bg-white px-3 text-xs font-semibold text-slate-700 hover:bg-slate-50" @click="$emit('add-unit', filtered[0])">
          <Plus class="h-4 w-4" />Thêm bài
        </button>
      </div>
    </div>
    <div class="max-h-[calc(100vh-15rem)] space-y-3 overflow-auto p-4">
      <SectionCard
        v-for="section in filtered"
        :key="section.id"
        :section="section"
        :selected-unit-id="selectedUnitId"
        @select-unit="$emit('select-unit', $event)"
        @add-unit="$emit('add-unit', section)"
        @move="$emit('move', $event)"
      />
    </div>
  </aside>
</template>
