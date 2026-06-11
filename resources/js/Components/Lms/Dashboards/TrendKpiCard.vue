<script setup>
import KpiCard from './KpiCard.vue'

defineProps({
  item: { type: Object, required: true },
})

defineEmits(['drilldown'])
</script>

<template>
  <KpiCard :item="item" @drilldown="$emit('drilldown', $event)" />
</template>
