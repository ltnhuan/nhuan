<script setup>
import DashboardShell from '@/Components/Lms/Dashboards/DashboardShell.vue'

defineProps({
  dashboardKey: { type: String, required: true },
  title: { type: String, required: true },
  endpoint: { type: String, required: true },
  sessionUser: { type: Object, default: null },
  apiHeaders: { type: Object, default: () => ({}) },
})

defineEmits(['logout'])
</script>

<template>
  <DashboardShell
    :dashboard-key="dashboardKey"
    :title="title"
    :endpoint="endpoint"
    :session-user="sessionUser"
    :api-headers="apiHeaders"
    @logout="$emit('logout')"
  />
</template>
