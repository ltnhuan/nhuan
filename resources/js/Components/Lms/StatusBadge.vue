<script setup>
import { computed } from 'vue'

const props = defineProps({ status: { type: String, default: 'OK' } })

const statusLabels = {
  OK: 'OK',
  active: 'Hoạt động',
  inactive: 'Ngưng hoạt động',
  draft: 'Bản nháp',
  published: 'Đã phát hành',
  pending: 'Đang chờ',
  ready: 'Sẵn sàng',
  processing: 'Đang xử lý',
  completed: 'Hoàn tất',
  failed: 'Thất bại',
  archived: 'Đã lưu trữ',
  locked: 'Bị khóa',
  open: 'Đang mở',
  closed: 'Đã đóng',
  late_grace: 'Cho nộp trễ',
  not_open: 'Chưa mở',
}

const label = computed(() => statusLabels[props.status] || props.status)
</script>

<template>
  <span
    class="inline-flex rounded-full px-2 py-1 text-xs font-bold"
    :class="status === 'OK' || status === 'active' || status === 'published' || status === 'completed' ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700'"
  >
    {{ label }}
  </span>
</template>
