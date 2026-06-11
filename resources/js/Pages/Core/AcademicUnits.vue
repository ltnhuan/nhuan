<script setup>
import EraLmsLayout from '@/Layouts/EraLmsLayout.vue'
import EnterpriseTable from '@/Components/EnterpriseTable.vue'
const rows = [{ code: 'UNIT01', name: 'Khoa Công nghệ', type: 'faculty', status: 'active' }, { code: 'UNIT11', name: 'Phòng Đào tạo', type: 'training_office', status: 'active' }]
</script>
<template><EraLmsLayout><EnterpriseTable title="Quản lý Khoa/Bộ môn/Đơn vị" :columns="['code','name','type','status']" :rows="rows" :filters="['Loại đơn vị','Trạng thái']" /><section class="mt-3 rounded-lg border bg-white p-4 text-sm">Cây tổ chức hỗ trợ trường/khoa/bộ môn/phòng ban, parent-child và lọc theo tenant.</section></EraLmsLayout></template>
