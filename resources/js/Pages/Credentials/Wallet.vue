<script setup>
import EraLmsLayout from '@/Layouts/EraLmsLayout.vue'
const items = ['Course Certificate','Completion Badge','AI Skill Badge','Micro Credential','Skill Credential']
</script>
<template>
  <EraLmsLayout><template #breadcrumb>Learner / Credential Wallet</template>
    <section class="mx-auto max-w-7xl px-6 py-5">
      <div class="flex items-center justify-between"><h1 class="text-lg font-semibold">Learner Credential Wallet</h1><button class="rounded-md border px-3 py-2 text-sm">Sync Portfolio</button></div>
      <div class="mt-5 grid gap-3 md:grid-cols-5"><div v-for="item in items" :key="item" class="border bg-white p-4"><div class="text-xs text-slate-500">Credential</div><div class="mt-2 text-sm font-semibold">{{ item }}</div><div class="mt-4 h-2 rounded bg-emerald-500"></div></div></div>
      <div class="mt-4 border bg-white"><div class="grid grid-cols-5 border-b px-4 py-3 text-xs font-semibold text-slate-500"><span>Tên</span><span>Loại</span><span>Ngày cấp</span><span>Trạng thái</span><span>Verify</span></div><div v-for="item in items" :key="item" class="grid grid-cols-5 px-4 py-3 text-sm"><span>{{ item }}</span><span>Digital</span><span>05/06/2026</span><span class="text-emerald-700">Issued</span><span class="font-mono text-xs">QR + Hash</span></div></div>
    </section>
  </EraLmsLayout>
</template>
