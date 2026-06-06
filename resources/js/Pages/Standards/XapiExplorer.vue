<script setup>
import { onMounted, ref } from 'vue'
import EraLmsLayout from '@/Layouts/EraLmsLayout.vue'

const props = defineProps({ apiHeaders: { type: Object, required: true }, sessionUser: { type: Object, default: null } })
defineEmits(['logout'])
const statements = ref([])
const analytics = ref({ xapi_events: { verbs: {} } })

async function load() {
  const [statementResponse, analyticsResponse] = await Promise.all([
    fetch('/api/v1/learning-standards/xapi/statements', { headers: props.apiHeaders }),
    fetch('/api/v1/learning-standards/analytics', { headers: props.apiHeaders }),
  ])
  statements.value = (await statementResponse.json()).data || []
  analytics.value = await analyticsResponse.json()
}

onMounted(load)
</script>

<template>
  <EraLmsLayout :session-user="sessionUser" @logout="$emit('logout')">
    <template #breadcrumb>Learning Standards / xAPI Explorer</template>
    <section class="mx-auto max-w-7xl px-6 py-5">
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-lg font-semibold">xAPI Explorer</h1>
          <p class="mt-1 text-sm text-slate-600">Lưu và tra cứu statements theo Actor, Verb, Object.</p>
        </div>
        <button class="rounded-md bg-slate-950 px-3 py-2 text-sm text-white">Store Statement</button>
      </div>
      <div class="mt-5 grid gap-4 lg:grid-cols-[1fr_320px]">
        <div class="overflow-hidden border bg-white">
          <table class="w-full text-left text-sm">
            <thead class="bg-slate-100 text-xs uppercase text-slate-600"><tr><th class="p-3">Actor</th><th class="p-3">Verb</th><th class="p-3">Object</th><th class="p-3">Stored</th></tr></thead>
            <tbody><tr v-for="statement in statements" :key="statement.id" class="border-t"><td class="p-3">{{ statement.actor?.name || statement.actor?.account?.name }}</td><td class="p-3">{{ statement.verb?.display?.['en-US'] || statement.verb?.id }}</td><td class="p-3">{{ statement.object?.definition?.name?.['en-US'] || statement.object?.id }}</td><td class="p-3">{{ statement.stored_at }}</td></tr></tbody>
          </table>
        </div>
        <aside class="border bg-white p-4">
          <h2 class="text-sm font-semibold">Verb analytics</h2>
          <div class="mt-4 space-y-2 text-sm">
            <div v-for="(total, verb) in analytics.xapi_events?.verbs" :key="verb" class="flex justify-between"><span>{{ verb }}</span><strong>{{ total }}</strong></div>
          </div>
        </aside>
      </div>
    </section>
  </EraLmsLayout>
</template>
