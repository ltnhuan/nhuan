<script setup>
import ApiOpsShell from '@/Components/ApiOps/ApiOpsShell.vue'
import ApiTestConsole from '@/Components/ApiOps/ApiTestConsole.vue'

defineProps({
  apiHeaders: { type: Object, required: true },
  sessionUser: { type: Object, default: null },
})
</script>

<template>
  <ApiOpsShell :session-user="sessionUser" title="Console kiểm thử API" subtitle="Chọn hệ thống và endpoint, nhập header/body, gửi request test, validate schema và xem phản hồi.">
    <ApiTestConsole :api-headers="apiHeaders" />
  </ApiOpsShell>
</template>
