<script setup>
import EnterpriseDashboardPage from './EnterpriseDashboardPage.vue'
defineProps({ sessionUser: { type: Object, default: null }, apiHeaders: { type: Object, default: () => ({}) } })
defineEmits(['logout'])
</script>
<template><EnterpriseDashboardPage dashboard-key="faculty" title="Faculty Dashboard / Khoa" endpoint="/api/v1/dashboards/faculty" :session-user="sessionUser" :api-headers="apiHeaders" @logout="$emit('logout')" /></template>
