<script setup>
import EnterpriseDashboardPage from './EnterpriseDashboardPage.vue'
defineProps({ sessionUser: { type: Object, default: null }, apiHeaders: { type: Object, default: () => ({}) } })
defineEmits(['logout'])
</script>
<template><EnterpriseDashboardPage dashboard-key="gradebook" title="Gradebook Dashboard / Điểm" endpoint="/api/v1/dashboards/gradebook" :session-user="sessionUser" :api-headers="apiHeaders" @logout="$emit('logout')" /></template>
