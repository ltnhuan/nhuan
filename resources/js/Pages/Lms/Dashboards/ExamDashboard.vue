<script setup>
import EnterpriseDashboardPage from './EnterpriseDashboardPage.vue'
defineProps({ sessionUser: { type: Object, default: null }, apiHeaders: { type: Object, default: () => ({}) } })
defineEmits(['logout'])
</script>
<template><EnterpriseDashboardPage dashboard-key="exam" title="Exam Dashboard / Khảo thí" endpoint="/api/v1/dashboards/exam" :session-user="sessionUser" :api-headers="apiHeaders" @logout="$emit('logout')" /></template>
