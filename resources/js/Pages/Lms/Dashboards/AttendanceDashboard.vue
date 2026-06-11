<script setup>
import EnterpriseDashboardPage from './EnterpriseDashboardPage.vue'
defineProps({ sessionUser: { type: Object, default: null }, apiHeaders: { type: Object, default: () => ({}) } })
defineEmits(['logout'])
</script>
<template><EnterpriseDashboardPage dashboard-key="attendance" title="Attendance Dashboard / Chuyên cần" endpoint="/api/v1/dashboards/attendance" :session-user="sessionUser" :api-headers="apiHeaders" @logout="$emit('logout')" /></template>
