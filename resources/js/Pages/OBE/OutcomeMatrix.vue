<script setup>
import EraLmsLayout from '@/Layouts/EraLmsLayout.vue'
const plos=['PLO1','PLO2','PLO3','PLO4','PLO5']
const clos=['CLO1','CLO2','CLO3','CLO4','CLO5','CLO6']
const heat=(i,j)=>['bg-rose-100 text-rose-700','bg-amber-100 text-amber-800','bg-emerald-100 text-emerald-800'][(i+j)%3]
</script>
<template><EraLmsLayout><template #breadcrumb>OBE / Outcome Matrix</template><section class="mx-auto max-w-7xl px-6 py-5"><div class="flex items-center justify-between"><div><h1 class="text-lg font-semibold">Dynamic Outcome Matrix</h1><p class="mt-1 text-sm text-slate-600">Heatmap PLO -> CLO -> Course -> Lesson -> Quiz/Assignment.</p></div><button class="rounded-md border px-3 py-2 text-sm">Export matrix</button></div><div class="mt-5 overflow-auto border bg-white"><table class="w-full min-w-[800px] text-center text-sm"><thead class="bg-slate-50 text-xs uppercase text-slate-500"><tr><th class="px-4 py-3 text-left">CLO/PLO</th><th v-for="plo in plos" :key="plo" class="px-4 py-3">{{plo}}</th></tr></thead><tbody class="divide-y"><tr v-for="(clo,i) in clos" :key="clo"><td class="px-4 py-3 text-left font-medium">{{clo}}</td><td v-for="(plo,j) in plos" :key="plo" class="px-3 py-2"><span class="block rounded-md px-3 py-2" :class="heat(i,j)">{{(i+j)%3===0?'0.0':((i+j)%3===1?'0.5':'0.8')}}</span></td></tr></tbody></table></div></section></EraLmsLayout></template>
