<script setup>
import EnterpriseDashboardPage from './EnterpriseDashboardPage.vue'
defineProps({ sessionUser: { type: Object, default: null }, apiHeaders: { type: Object, default: () => ({}) } })
defineEmits(['logout'])
</script>
<template><EnterpriseDashboardPage dashboard-key="executive" title="Executive Dashboard / BGH" endpoint="/api/v1/dashboards/executive" :session-user="sessionUser" :api-headers="apiHeaders" @logout="$emit('logout')" /></template>
