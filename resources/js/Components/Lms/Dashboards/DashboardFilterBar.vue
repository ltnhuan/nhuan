<script setup>
import { Filter, RotateCcw } from '@lucide/vue'

const props = defineProps({
  modelValue: { type: Object, required: true },
  loading: { type: Boolean, default: false },
})

const emit = defineEmits(['update:modelValue', 'apply', 'rebuild'])

const fields = [
  ['from', 'Từ ngày', 'date'],
  ['to', 'Đến ngày', 'date'],
  ['academic_year_id', 'Năm học', 'number'],
  ['semester_id', 'Học kỳ', 'number'],
  ['campus_id', 'Campus', 'number'],
  ['faculty_id', 'Khoa', 'number'],
  ['program_id', 'Ngành', 'number'],
  ['class_id', 'Lớp', 'number'],
  ['course_id', 'Khóa học', 'number'],
  ['user_id', 'Học viên', 'number'],
  ['teacher_id', 'Giảng viên', 'number'],
]

function update(key, value) {
  emit('update:modelValue', { ...props.modelValue, [key]: value })
}
</script>

<template>
  <section class="rounded-md border bg-white p-3">
    <div class="grid gap-2 md:grid-cols-4 xl:grid-cols-6">
      <label v-for="[key, label, type] in fields" :key="key" class="block min-w-0 text-xs font-semibold text-slate-600">
        <span class="mb-1 block truncate">{{ label }}</span>
        <input
          :type="type"
          :value="modelValue[key] || ''"
          class="h-9 w-full rounded-md border border-slate-300 px-2 text-sm outline-none focus:border-cyan-500 focus:ring-4 focus:ring-cyan-100"
          @input="update(key, $event.target.value)"
        />
      </label>
      <div class="flex items-end gap-2">
        <button class="inline-flex h-9 items-center gap-2 rounded-md bg-slate-950 px-3 text-sm font-semibold text-white" :disabled="loading" @click="$emit('apply')">
          <Filter class="h-4 w-4" />
          Lọc
        </button>
        <button class="inline-flex h-9 items-center gap-2 rounded-md border border-slate-300 px-3 text-sm font-semibold text-slate-700" :disabled="loading" @click="$emit('rebuild')">
          <RotateCcw class="h-4 w-4" />
          Rebuild
        </button>
      </div>
    </div>
  </section>
</template>
