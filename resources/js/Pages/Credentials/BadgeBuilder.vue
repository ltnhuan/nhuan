<script setup>
import EraLmsLayout from '@/Layouts/EraLmsLayout.vue'
const badges = ['Completion Badge','Excellence Badge','Attendance Badge','Competency Badge','AI Skill Badge']
const rules = ['Hoàn thành khóa học','Đạt điểm tối thiểu','Đạt competency','Hoàn thành learning path','AI skill evidence']
</script>
<template>
  <EraLmsLayout><template #breadcrumb>Digital Credential / Badge Builder</template>
    <section class="mx-auto max-w-7xl px-6 py-5">
      <div class="flex items-center justify-between"><h1 class="text-lg font-semibold">Badge Engine</h1><button class="rounded-md bg-blue-900 px-3 py-2 text-sm text-white">Create Badge Rule</button></div>
      <div class="mt-5 grid gap-4 lg:grid-cols-[1fr_360px]">
        <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
          <div v-for="badge in badges" :key="badge" class="border bg-white p-4">
            <div class="grid h-20 w-20 place-items-center rounded-full border-4 border-amber-400 bg-blue-900 text-xs font-semibold text-white">ERA</div>
            <h2 class="mt-3 text-sm font-semibold">{{ badge }}</h2><p class="mt-1 text-xs text-slate-600">Open Badge ready, portfolio ready, tự động cấp theo rule engine.</p>
          </div>
        </div>
        <aside class="border bg-white p-4">
          <h2 class="text-sm font-semibold">Auto Issue Rules</h2>
          <div class="mt-3 space-y-2"><div v-for="rule in rules" :key="rule" class="flex items-center justify-between rounded-md border px-3 py-2 text-sm"><span>{{ rule }}</span><input type="checkbox" checked></div></div>
        </aside>
      </div>
    </section>
  </EraLmsLayout>
</template>
