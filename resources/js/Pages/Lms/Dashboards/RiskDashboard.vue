<script setup>
import EnterpriseDashboardPage from './EnterpriseDashboardPage.vue'
defineProps({ sessionUser: { type: Object, default: null }, apiHeaders: { type: Object, default: () => ({}) } })
defineEmits(['logout'])
</script>
<template><EnterpriseDashboardPage dashboard-key="risk" title="Risk / Early Warning Dashboard" endpoint="/api/v1/dashboards/risk" :session-user="sessionUser" :api-headers="apiHeaders" @logout="$emit('logout')" /></template>
