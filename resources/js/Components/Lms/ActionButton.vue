<script setup>
import { computed } from 'vue'

const props = defineProps({
  actionKey: { type: String, required: true },
  label: { type: String, required: true },
  icon: { type: String, default: '' },
  permission: { type: String, default: '' },
  userPermissions: { type: Array, default: () => [] },
  loading: { type: Boolean, default: false },
  danger: { type: Boolean, default: false },
  confirm: { type: Boolean, default: false },
  disabled: { type: Boolean, default: false },
  onClick: { type: Function, required: true },
})

const canShow = computed(() => !props.permission || props.userPermissions.includes(props.permission))
</script>

<template>
  <button
    v-if="canShow"
    :data-action-key="actionKey"
    type="button"
    class="inline-flex h-9 items-center gap-2 rounded-md border px-3 text-sm font-semibold disabled:cursor-not-allowed disabled:opacity-60"
    :class="danger ? 'border-red-200 bg-red-50 text-red-700 hover:bg-red-100' : 'border-slate-300 bg-white text-slate-700 hover:bg-slate-50'"
    :disabled="disabled || loading"
    @click="onClick"
  >
    <span v-if="loading" class="h-3 w-3 animate-spin rounded-full border-2 border-current border-t-transparent"></span>
    <span v-else-if="icon" class="text-xs">{{ icon }}</span>
    <span>{{ label }}</span>
  </button>
</template>
