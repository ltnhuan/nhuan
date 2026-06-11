<script setup>
import EnterpriseDashboardPage from './EnterpriseDashboardPage.vue'
defineProps({ sessionUser: { type: Object, default: null }, apiHeaders: { type: Object, default: () => ({}) } })
defineEmits(['logout'])
</script>
<template><EnterpriseDashboardPage dashboard-key="teacher" title="Teacher Dashboard / Giảng viên" endpoint="/api/v1/dashboards/teacher" :session-user="sessionUser" :api-headers="apiHeaders" @logout="$emit('logout')" /></template>
