<script setup>
import EnterpriseDashboardPage from './EnterpriseDashboardPage.vue'
defineProps({ sessionUser: { type: Object, default: null }, apiHeaders: { type: Object, default: () => ({}) } })
defineEmits(['logout'])
</script>
<template><EnterpriseDashboardPage dashboard-key="student" title="Student Dashboard / Học viên" endpoint="/api/v1/dashboards/student" :session-user="sessionUser" :api-headers="apiHeaders" @logout="$emit('logout')" /></template>
