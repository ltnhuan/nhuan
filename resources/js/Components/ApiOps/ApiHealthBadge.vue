<script setup>
const props = defineProps({
  status: { type: String, default: 'unknown' },
})

const tones = {
  healthy: 'border-emerald-200 bg-emerald-50 text-emerald-700',
  active: 'border-emerald-200 bg-emerald-50 text-emerald-700',
  success: 'border-emerald-200 bg-emerald-50 text-emerald-700',
  degraded: 'border-amber-200 bg-amber-50 text-amber-800',
  retrying: 'border-amber-200 bg-amber-50 text-amber-800',
  pending: 'border-sky-200 bg-sky-50 text-sky-700',
  processing: 'border-sky-200 bg-sky-50 text-sky-700',
  down: 'border-rose-200 bg-rose-50 text-rose-700',
  failed: 'border-rose-200 bg-rose-50 text-rose-700',
  error: 'border-rose-200 bg-rose-50 text-rose-700',
  inactive: 'border-slate-200 bg-slate-50 text-slate-600',
  deprecated: 'border-slate-200 bg-slate-50 text-slate-600',
  maintenance: 'border-violet-200 bg-violet-50 text-violet-700',
}

const labels = {
  healthy: 'Tốt',
  active: 'Đang bật',
  success: 'Thành công',
  degraded: 'Cần theo dõi',
  retrying: 'Đang thử lại',
  pending: 'Đang chờ',
  processing: 'Đang xử lý',
  down: 'Mất kết nối',
  failed: 'Lỗi',
  error: 'Lỗi',
  inactive: 'Tạm tắt',
  deprecated: 'Ngừng dùng',
  maintenance: 'Bảo trì',
  ignored: 'Đã bỏ qua',
  draft: 'Bản nháp',
  unknown: 'Chưa rõ',
}
</script>

<template>
  <span class="inline-flex items-center rounded-md border px-2 py-1 text-xs font-semibold" :class="tones[status] || 'border-slate-200 bg-slate-50 text-slate-600'">
    {{ labels[status] || status || labels.unknown }}
  </span>
</template>
