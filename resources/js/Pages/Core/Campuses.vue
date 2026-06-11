<script setup>
import EraLmsLayout from '@/Layouts/EraLmsLayout.vue'
import EnterpriseTable from '@/Components/EnterpriseTable.vue'
const rows = [{ code: 'VT', name: 'Vũng Tàu', phone: '0254...', email: 'campus@vabis.edu.vn', status: 'active' }, { code: 'ONLINE', name: 'Cơ sở trực tuyến', phone: '-', email: 'online@vabis.edu.vn', status: 'active' }]
</script>
<template><EraLmsLayout><EnterpriseTable title="Quản lý Campus" :columns="['code','name','phone','email','status']" :rows="rows" :filters="['Trạng thái','Tenant']" /><section class="mt-3 rounded-lg border bg-white p-4 text-sm">Drawer chi tiết campus gồm địa chỉ, người phụ trách, trạng thái và bulk action kích hoạt/tạm khóa.</section></EraLmsLayout></template>
