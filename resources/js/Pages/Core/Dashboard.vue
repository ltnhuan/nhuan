<script setup>
import EraLmsLayout from '@/Layouts/EraLmsLayout.vue'
const cards = [{label:'Người học', value:'5.000'}, {label:'Giảng viên/cán bộ', value:'90'}, {label:'Khóa học', value:'20'}, {label:'Tiến độ trung bình', value:'68%'}]
</script>
<template><EraLmsLayout><div class="grid grid-cols-4 gap-3"><div v-for="card in cards" :key="card.label" class="rounded-lg border bg-white p-4"><div class="text-xs text-slate-500">{{ card.label }}</div><div class="mt-2 text-2xl font-semibold">{{ card.value }}</div></div></div><section class="mt-4 rounded-lg border bg-white p-4"><h2 class="text-sm font-semibold">Tổng quan vận hành LMS</h2><p class="mt-2 text-sm text-slate-600">Dashboard tải nhanh với dữ liệu tóm tắt, cảnh báo tiến độ, trạng thái tenant/campus và hàng đợi audit.</p></section></EraLmsLayout></template>
