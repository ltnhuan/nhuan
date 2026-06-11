<script setup>
import PieChartCard from './PieChartCard.vue'

defineProps({
  chart: { type: Object, required: true },
})
</script>

<template>
  <PieChartCard :chart="chart" />
</template>
