<script setup>
import { reactive, watch } from 'vue'
import TransformRuleEditor from './TransformRuleEditor.vue'

const props = defineProps({
  systems: { type: Array, default: () => [] },
  mapping: { type: Object, default: null },
})
const emit = defineEmits(['create', 'validate', 'cancel'])

const defaultForm = () => ({
  source_system_id: '',
  target_system_id: '',
  entity_type: 'student',
  source_field: 'student_code',
  target_field: 'code',
  transform_rule: { type: 'copy' },
  is_required: true,
  default_value: '',
  payload: { student_code: 'SV001', full_name: 'Nguyen Van A' },
})

const form = reactive(defaultForm())

watch(() => props.mapping, (mapping) => {
  if (!mapping) {
    Object.assign(form, defaultForm())
    return
  }

  Object.assign(form, {
    source_system_id: mapping.source_system_id || '',
    target_system_id: mapping.target_system_id || '',
    entity_type: mapping.entity_type || 'student',
    source_field: mapping.source_field || '',
    target_field: mapping.target_field || '',
    transform_rule: mapping.transform_rule || { type: 'copy' },
    is_required: Boolean(mapping.is_required),
    default_value: mapping.default_value || '',
    status: mapping.status || 'active',
    payload: form.payload,
  })
}, { immediate: true })
</script>

<template>
  <section class="rounded-md border border-slate-200 bg-white p-4">
    <div class="flex items-center justify-between gap-2">
      <h2 class="text-sm font-semibold">{{ mapping ? 'Sửa ánh xạ dữ liệu' : 'Tạo ánh xạ dữ liệu' }}</h2>
      <button v-if="mapping" class="rounded-md border px-2 py-1 text-xs font-semibold text-slate-700" @click="emit('cancel')">Hủy sửa</button>
    </div>
    <div class="mt-4 grid gap-3 md:grid-cols-3">
      <label>
        <span class="text-xs font-semibold uppercase text-slate-500">Hệ thống nguồn</span>
        <select v-model="form.source_system_id" class="mt-1 h-10 w-full rounded-md border border-slate-300 bg-white px-2 text-sm">
          <option value="">Chọn hệ thống</option>
          <option v-for="system in systems" :key="system.id" :value="system.id">{{ system.code }}</option>
        </select>
      </label>
      <label>
        <span class="text-xs font-semibold uppercase text-slate-500">Hệ thống đích</span>
        <select v-model="form.target_system_id" class="mt-1 h-10 w-full rounded-md border border-slate-300 bg-white px-2 text-sm">
          <option value="">Chọn hệ thống</option>
          <option v-for="system in systems" :key="system.id" :value="system.id">{{ system.code }}</option>
        </select>
      </label>
      <label>
        <span class="text-xs font-semibold uppercase text-slate-500">Loại dữ liệu</span>
        <input v-model="form.entity_type" class="mt-1 h-10 w-full rounded-md border border-slate-300 px-2 text-sm" />
      </label>
      <label>
        <span class="text-xs font-semibold uppercase text-slate-500">Trường nguồn</span>
        <input v-model="form.source_field" class="mt-1 h-10 w-full rounded-md border border-slate-300 px-2 text-sm" />
      </label>
      <label>
        <span class="text-xs font-semibold uppercase text-slate-500">Trường đích</span>
        <input v-model="form.target_field" class="mt-1 h-10 w-full rounded-md border border-slate-300 px-2 text-sm" />
      </label>
      <label class="flex items-end gap-2 pb-2 text-sm font-semibold text-slate-700">
        <input v-model="form.is_required" type="checkbox" class="h-4 w-4 rounded border-slate-300" />
        Bắt buộc
      </label>
      <div class="md:col-span-2">
        <TransformRuleEditor v-model="form.transform_rule" />
      </div>
      <label>
        <span class="text-xs font-semibold uppercase text-slate-500">Giá trị mặc định</span>
        <input v-model="form.default_value" class="mt-1 h-10 w-full rounded-md border border-slate-300 px-2 text-sm" />
      </label>
    </div>
    <div class="mt-4 flex flex-wrap justify-end gap-2">
      <button class="rounded-md border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50" @click="emit('validate', form)">Xem trước ánh xạ</button>
      <button class="rounded-md bg-slate-950 px-3 py-2 text-sm font-semibold text-white hover:bg-slate-800" @click="emit('create', form)">{{ mapping ? 'Cập nhật ánh xạ' : 'Lưu ánh xạ' }}</button>
    </div>
  </section>
</template>
