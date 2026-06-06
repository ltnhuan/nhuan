<script setup>
import ActionButton from './ActionButton.vue'

defineProps({
  actions: { type: Array, default: () => [] },
  loadingMap: { type: Object, default: () => ({}) },
  userPermissions: { type: Array, default: () => [] },
})

const emit = defineEmits(['run'])
</script>

<template>
  <div class="flex flex-wrap gap-2">
    <ActionButton
      v-for="action in actions"
      :key="action.action_key || action.actionKey"
      :action-key="action.action_key || action.actionKey"
      :label="action.label"
      :icon="action.icon"
      :permission="action.permission_key || action.permission"
      :user-permissions="userPermissions"
      :danger="action.danger || action.confirm_required"
      :confirm="action.confirm_required"
      :loading="Boolean(loadingMap[action.action_key || action.actionKey])"
      :on-click="() => emit('run', action)"
    />
  </div>
</template>
