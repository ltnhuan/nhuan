<script setup>
import EraLmsLayout from '@/Layouts/EraLmsLayout.vue'
import EnterpriseTable from '@/Components/EnterpriseTable.vue'
const rows = [{ action: 'update', module: 'core.users', entity_type: 'App\\Models\\LmsUser', actor_id: 1, created_at: '2026-06-04 08:00' }]
</script>
<template><EraLmsLayout><EnterpriseTable title="Audit logs" :columns="['action','module','entity_type','actor_id','created_at']" :rows="rows" :filters="['Module','Action','Actor','Ngày']" /><section class="mt-3 rounded-lg border bg-white p-4 text-sm">Drawer hiển thị before/after JSON, IP, user agent và entity id. Audit create/update/delete/lock/unlock/approve/publish được ghi qua AuditLogService.</section></EraLmsLayout></template>
